<?php

namespace App\Services;

use App\Data\Orders\CheckoutCommand;
use App\Data\Orders\FinalizeOrderCommand;
use App\Data\Orders\OrderInput;
use App\Data\Orders\PreparedOrderOperation;
use App\Data\Orders\QuoteCommand;
use App\Models\User;
use App\Support\CanonicalJson;
use App\Support\Money;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Infrastructure for trusted future handlers, not an HTTP checkout implementation.
 * The handler must lock/revalidate the order/catalog and prepare the business records.
 */
final class OrderOperationExecutor
{
    public function __construct(
        private FinancialCoordinator $coordinator,
        private OperationReceiptStore $receipts,
        private OrderLinkWriter $links,
    ) {}

    public function execute(
        User $actor, CheckoutCommand|QuoteCommand|FinalizeOrderCommand $command, Closure $prepare,
    ): array {
        if (! $actor->active || ! $actor->hasAnyRole(['admin', 'gerente', 'vendedor']) || ! $actor->can('pos.sell')
            || ($command instanceof FinalizeOrderCommand && ! $actor->can('orders.view'))) {
            throw new AuthorizationException('Operação de pedido não autorizada.');
        }
        return $this->coordinator->orderTransaction(function (FinancialContext $context) use ($actor, $command, $prepare) {
            $previous = $this->receipts->recover($context, $actor, $command->key(), $command->operation(), $command->hash());
            if ($previous !== null) {
                // Read the original operation's snapshots, never the mutable order/catalog.
                $receiptId = DB::table('operation_receipts')->where('idempotency_key', $command->key())->value('id');
                $snapshots = DB::table('order_item_snapshots')->where('operation_receipt_id', $receiptId)->get()
                    ->map(fn ($row) => (array) $row)->all();
                try {
                    $this->assertCommandResult($command, $previous, $snapshots);
                } catch (InvalidArgumentException $exception) {
                    throw new ServiceUnavailableHttpException(1, 'Resultado não corresponde ao comando original. Preserve a chave.', $exception);
                }
                return ['result' => $previous, 'replayed' => true];
            }
            $quote = $command instanceof QuoteCommand;
            if (! $quote) {
                $this->coordinator->activeCash($context, $command->payload()['cash_register_id']);
            }
            if ($command instanceof FinalizeOrderCommand
                && ! hash_equals($command->payload()['expected_order_hash'], app(QuoteVersion::class)->current($context, $command->payload()['order_id']))) {
                throw new ConflictHttpException('O orçamento foi alterado; confirme a nova versão.');
            }
            $quoteContent = $command instanceof FinalizeOrderCommand
                ? app(QuoteVersion::class)->financialContent($context, $command->payload()['order_id']) : null;
            $prepared = $prepare($context);
            if (! $prepared instanceof PreparedOrderOperation) {
                throw new InvalidArgumentException('O preparador deve retornar um resultado tipado.');
            }
            $result = $prepared->result->data;
            $this->assertCommandResult($command, $result, $prepared->snapshots);
            if ($quoteContent !== null && ! hash_equals($quoteContent,
                app(QuoteVersion::class)->financialContent($context, $command->payload()['order_id']))) {
                throw new InvalidArgumentException('Conteúdo financeiro do orçamento diverge do comando.');
            }
            if (! $command instanceof FinalizeOrderCommand) {
                $header = DB::table('orders')->where('id', $result['order_id'])->sole(['customer_id', 'customer_name']);
                $data = $command->payload();
                if (($header->customer_id === null ? null : (int) $header->customer_id) !== $data['customer_id']
                    || $header->customer_name !== $data['customer_name']) {
                    throw new InvalidArgumentException('Cliente do pedido diverge do comando.');
                }
            }
            $financialId = null;
            if (! $quote) {
                $financialId = (int) DB::table('financial_operations')->insertGetId([
                    'idempotency_key' => $command->key(), 'operation' => $command->operation(), 'user_id' => $actor->id,
                    'request_hash' => $command->hash(), 'cash_register_id' => $result['cash_register_id'],
                    'cash_movement_id' => null, 'result' => CanonicalJson::encode($result),
                    'created_at' => DB::raw('CURRENT_TIMESTAMP'),
                ]);
            }
            $receiptId = $this->receipts->append($context, $actor, $command->key(), $command->operation(),
                $command->hash(), $result['order_id'], $financialId, $result);
            $this->links->write($context, $receiptId, $prepared);
            return ['result' => $result, 'replayed' => false];
        });
    }

    private function assertCommandResult(
        CheckoutCommand|QuoteCommand|FinalizeOrderCommand $command, array $result, array $snapshots,
    ): void {
        $quote = $command instanceof QuoteCommand;
        $data = $command->payload();
        if ($result['operation_key'] !== $command->key()
            || $result['status'] !== ($quote ? 'draft' : 'paid')
            || (! $quote && $result['cash_register_id'] !== $data['cash_register_id'])
            || ($command instanceof CheckoutCommand && $result['total'] !== $data['expected_total'])
            || ($command instanceof FinalizeOrderCommand && $result['order_id'] !== $data['order_id'])) {
            throw new InvalidArgumentException('Resultado não corresponde ao comando.');
        }
        if (! $quote) {
            $parts = array_map(fn ($part) => ['method' => $part['method'], 'amount' => $part['amount']], $result['payments']);
            if (OrderInput::payments($parts) !== $data['payments']) {
                throw new InvalidArgumentException('Pagamentos não correspondem ao comando.');
            }
        }
        if ($command instanceof FinalizeOrderCommand) {
            return;
        }
        $expected = [];
        $subtotal = Money::cents(0);
        foreach ($data['items'] as $item) {
            $unit = Money::decimal($item['requested_unit_price'] ?? $item['expected_unit_price']);
            $discount = Money::decimal($item['discount']);
            $subtotal = $subtotal->add($unit->multiply($item['qty'])->subtract($discount));
            $expected[] = [
                'product_id' => $item['product_id'], 'qty' => $item['qty'],
                'catalog_price_cents' => Money::decimal($item['expected_unit_price'])->cents,
                'unit_price_cents' => $unit->cents, 'discount_cents' => $discount->cents,
                'price_reason' => $item['price_reason'],
            ];
        }
        $actual = [];
        foreach ($snapshots as $snapshot) {
            $actual[] = array_intersect_key($snapshot, array_flip(array_keys($expected[0])));
        }
        $sort = function (array $rows): array {
            usort($rows, fn ($a, $b) => strcmp(CanonicalJson::encode($a), CanonicalJson::encode($b)));
            return $rows;
        };
        if (CanonicalJson::encode($sort($expected)) !== CanonicalJson::encode($sort($actual))
            || $result['subtotal'] !== $subtotal->decimalString() || $result['discount'] !== $data['discount']
            || $result['total'] !== $subtotal->subtract(Money::decimal($data['discount']))->decimalString()
            || ($result['discount_approval']['reason'] ?? null) !== (Money::decimal($data['discount'])->cents > 0 ? $data['discount_reason'] : null)) {
            throw new InvalidArgumentException('Itens ou descontos não correspondem ao comando.');
        }
    }
}
