<?php

namespace Tests\Feature;

use App\Data\Orders\CheckoutCommand;
use App\Data\Orders\FinalizeOrderCommand;
use App\Data\Orders\OrderResult;
use App\Data\Orders\PreparedOrderOperation;
use App\Data\Orders\QuoteCommand;
use App\Services\FinancialCoordinator;
use App\Services\OperationReceiptStore;
use App\Services\OrderOperationExecutor;
use App\Services\QuoteVersion;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\Support\FinancialTestCase;

class OrderCommandResultIntegrityTest extends FinancialTestCase
{
    public static function changedIntent(): array
    {
        return [['method'], ['split'], ['product'], ['quantity-price'], ['line-discount'], ['order-discount'], ['requested-price'], ['price-reason']];
    }

    #[DataProvider('changedIntent')]
    public function test_divergent_preparer_is_rejected_and_all_writes_roll_back(string $change): void
    {
        $cash = $this->cash();
        $input = $this->input($this->product(), $cash);
        $different = $input;
        switch ($change) {
            case 'method': $different['payments'][0]['method'] = 'dinheiro'; break;
            case 'split': $different['payments'] = array_fill(0, 2, ['method' => 'pix', 'amount' => '5.00']); break;
            case 'product': $different['items'][0]['product_id'] = $this->product()->id; break;
            case 'quantity-price':
                $different['items'][0]['qty'] = 2;
                $different['items'][0]['expected_unit_price'] = '5.00';
                break;
            case 'line-discount': $input['items'][0]['discount'] = '0.01'; break;
            case 'order-discount': $input['discount'] = '0.01'; $input['discount_reason'] = 'Authorized intent'; break;
            case 'requested-price': $input['items'][0]['requested_unit_price'] = '9.00'; $input['items'][0]['price_reason'] = 'Authorized intent'; break;
            case 'price-reason': $input['items'][0]['price_reason'] = 'Original reason'; break;
        }
        $command = CheckoutCommand::fromArray($input);
        $other = CheckoutCommand::fromArray($different);
        $before = $this->state();
        try {
            app(OrderOperationExecutor::class)->execute($this->actor, $command, fn ($ctx) => $this->prepare($ctx, $other));
            $this->fail('Divergent preparation was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('comando', $exception->getMessage());
        }
        $this->assertSame($before, $this->state());
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_quote_items_are_also_bound_to_the_command(): void
    {
        $input = $this->input($this->product());
        $command = QuoteCommand::fromArray($input);
        $input['items'][0]['product_id'] = $this->product()->id;
        $other = QuoteCommand::fromArray($input);
        $before = $this->state();
        try {
            app(OrderOperationExecutor::class)->execute($this->actor, $command, fn ($ctx) => $this->prepare($ctx, $other));
            $this->fail('Quote substitution was accepted.');
        } catch (InvalidArgumentException) {
        }
        $this->assertSame($before, $this->state());
    }

    public function test_preparation_cannot_replace_the_customer_from_the_command(): void
    {
        $cash = $this->cash();
        $command = CheckoutCommand::fromArray($this->input($this->product(), $cash));
        $before = $this->state();
        try {
            app(OrderOperationExecutor::class)->execute($this->actor, $command, function ($ctx) use ($command) {
                $prepared = $this->prepare($ctx, $command);
                DB::table('orders')->where('id', $prepared->result->data['order_id'])->update(['customer_name' => 'Different customer']);
                return $prepared;
            });
            $this->fail('Customer substitution was accepted.');
        } catch (InvalidArgumentException) {
        }
        $this->assertSame($before, $this->state());
    }

    public function test_finalization_cannot_rewrite_the_locked_quote_during_preparation(): void
    {
        [$executor, $command] = $this->finalization();
        $before = $this->state();
        try {
            $executor->execute($this->actor, $command, function ($ctx) use ($command) {
                DB::table('order_items')->where('order_id', $command->payload()['order_id'])->update(['qty' => 2, 'price' => '5.00']);
                return $this->prepare($ctx, $command);
            });
            $this->fail('The locked quote was rewritten.');
        } catch (InvalidArgumentException) {
        }
        $this->assertSame($before, $this->state());
    }

    public function test_semantically_equal_payment_strings_replay_without_any_writes(): void
    {
        $cash = $this->cash();
        $input = $this->input($this->product(), $cash);
        $input['payments'] = [['method' => 'pix', 'amount' => '6.00'], ['method' => 'dinheiro', 'amount' => '4.00']];
        $command = CheckoutCommand::fromArray($input);
        $executor = app(OrderOperationExecutor::class);
        $first = $executor->execute($this->actor, $command, fn ($ctx) => $this->prepare($ctx, $command));
        $input['payments'] = [['method' => 'dinheiro', 'amount' => '0004'], ['method' => 'pix', 'amount' => '006.0']];
        $equivalent = CheckoutCommand::fromArray($input);
        $this->assertSame($command->hash(), $equivalent->hash());
        DB::table('cash_registers')->where('id', $cash->id)->update(['status' => 'closed']);
        DB::table('orders')->where('id', $first['result']['order_id'])->update(['status' => 'cancelled']);
        $before = $this->state();
        $again = $executor->execute($this->actor, $equivalent, fn () => throw new LogicException('Replay must not prepare.'));
        $this->assertTrue($again['replayed']);
        $this->assertSame($first['result'], $again['result']);
        $this->assertSame($before, $this->state());
    }

    public static function contradictions(): array
    {
        return [['cash'], ['version'], ['result-type'], ['payments'], ['snapshot']];
    }

    #[DataProvider('contradictions')]
    public function test_contradictory_receipts_never_replay_or_repair(string $change): void
    {
        $cash = $this->cash();
        $command = CheckoutCommand::fromArray($this->input($this->product(), $cash));
        $executor = app(OrderOperationExecutor::class);
        $first = $executor->execute($this->actor, $command, fn ($ctx) => $this->prepare($ctx, $command));
        $result = $first['result'];
        switch ($change) {
            case 'cash': $result['cash_register_id'] = $this->cash()->id; break;
            case 'version': $result['contract_version'] = 2; break;
            case 'result-type':
                $result['status'] = 'draft'; $result['cash_register_id'] = null;
                $result['payments'] = $result['cash_movement_ids'] = $result['stock_movement_ids'] = [];
                break;
            case 'payments': $result['payments'][0]['method'] = 'dinheiro'; break;
            case 'snapshot':
                DB::table('order_item_snapshots')->update(['catalog_price_cents' => 500, 'unit_price_cents' => 500, 'qty' => 2]);
                break;
        }
        DB::table('financial_operations')->update(['result' => CanonicalJson::encode($result)]);
        DB::table('operation_receipts')->update(['result_hash' => CanonicalJson::hash($result)]);
        $before = $this->state();
        try {
            $executor->execute($this->actor, $command, fn () => throw new LogicException('No repair or new preparation.'));
            $this->fail('Contradictory receipt was returned.');
        } catch (ServiceUnavailableHttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }
        $this->assertSame($before, $this->state());
    }

    public function test_quote_cannot_recover_a_financial_receipt_even_with_a_matching_identity(): void
    {
        $command = QuoteCommand::fromArray($this->input($this->product()));
        $executor = app(OrderOperationExecutor::class);
        $first = $executor->execute($this->actor, $command, fn ($ctx) => $this->prepare($ctx, $command));
        $cash = $this->cash();
        $id = DB::table('financial_operations')->insertGetId([
            'idempotency_key' => $command->key(), 'operation' => $command->operation(), 'user_id' => $this->actor->id,
            'request_hash' => $command->hash(), 'cash_register_id' => $cash->id,
            'result' => CanonicalJson::encode($first['result']), 'created_at' => now(),
        ]);
        DB::table('operation_receipts')->update(['financial_operation_id' => $id, 'result' => null]);
        $before = $this->state();
        try {
            app(FinancialCoordinator::class)->orderTransaction(fn ($ctx) => app(OperationReceiptStore::class)
                ->recover($ctx, $this->actor, $command->key(), $command->operation(), $command->hash()));
            $this->fail('Quote with a financial parent was recovered.');
        } catch (ServiceUnavailableHttpException) {
        }
        $this->assertSame($before, $this->state());
    }

    public function test_quote_replay_uses_its_own_snapshots_after_successful_finalization(): void
    {
        $executor = app(OrderOperationExecutor::class);
        $quote = QuoteCommand::fromArray($this->input($this->product()));
        $first = $executor->execute($this->actor, $quote, fn ($ctx) => $this->prepare($ctx, $quote));
        $orderId = $first['result']['order_id'];
        $hash = app(FinancialCoordinator::class)->orderTransaction(fn ($ctx) => app(QuoteVersion::class)->current($ctx, $orderId));
        $finalize = FinalizeOrderCommand::fromArray([
            'contract_version' => 1, 'idempotency_key' => (string) Str::uuid(), 'order_id' => $orderId,
            'expected_order_hash' => $hash, 'cash_register_id' => $this->cash()->id,
            'payments' => [['method' => 'pix', 'amount' => '10.00']],
        ]);
        $paid = $executor->execute($this->actor, $finalize, fn ($ctx) => $this->prepare($ctx, $finalize));
        $before = $this->state();
        $again = $executor->execute($this->actor, $quote, fn () => throw new LogicException('No quote reconstruction.'));
        $this->assertTrue($again['replayed']);
        $this->assertSame($first['result'], $again['result']);
        $this->assertSame('paid', DB::table('orders')->where('id', $orderId)->value('status'));
        $againPaid = $executor->execute($this->actor, $finalize, fn () => throw new LogicException('No second finalization.'));
        $this->assertTrue($againPaid['replayed']);
        $this->assertSame($paid['result'], $againPaid['result']);
        $this->assertSame($before, $this->state());
    }

    public static function identityChanges(): array
    {
        return [['user'], ['operation'], ['content']];
    }

    #[DataProvider('identityChanges')]
    public function test_reused_key_with_another_identity_conflicts_before_preparation(string $change): void
    {
        $cash = $this->cash();
        $input = $this->input($this->product(), $cash);
        $command = CheckoutCommand::fromArray($input);
        $executor = app(OrderOperationExecutor::class);
        $executor->execute($this->actor, $command, fn ($ctx) => $this->prepare($ctx, $command));
        $actor = $this->actor;
        if ($change === 'user') {
            $actor = \App\Models\User::factory()->create(['active' => true]);
            $actor->assignRole('admin');
        } elseif ($change === 'operation') {
            unset($input['cash_register_id'], $input['expected_total'], $input['payments']);
            $command = QuoteCommand::fromArray($input);
        } else {
            $input['payments'][0]['method'] = 'dinheiro';
            $command = CheckoutCommand::fromArray($input);
        }
        $before = $this->state();
        try {
            $executor->execute($actor, $command, fn () => throw new LogicException('No new preparation.'));
            $this->fail('Reused identity was accepted.');
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame($before, $this->state());
    }

    private function finalization(): array
    {
        $executor = app(OrderOperationExecutor::class);
        $quote = QuoteCommand::fromArray($this->input($this->product()));
        $result = $executor->execute($this->actor, $quote, fn ($ctx) => $this->prepare($ctx, $quote));
        $orderId = $result['result']['order_id'];
        $hash = app(FinancialCoordinator::class)->orderTransaction(fn ($ctx) => app(QuoteVersion::class)->current($ctx, $orderId));
        return [$executor, FinalizeOrderCommand::fromArray([
            'contract_version' => 1, 'idempotency_key' => (string) Str::uuid(), 'order_id' => $orderId,
            'expected_order_hash' => $hash, 'cash_register_id' => $this->cash()->id,
            'payments' => [['method' => 'pix', 'amount' => '10.00']],
        ])];
    }
}
