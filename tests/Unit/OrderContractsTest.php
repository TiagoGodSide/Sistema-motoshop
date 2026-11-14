<?php

namespace Tests\Unit;

use App\Contracts\Orders\PriceExceptionAuthorization;
use App\Data\Orders\CatalogProduct;
use App\Data\Orders\CheckoutCommand;
use App\Data\Orders\FinalizeOrderCommand;
use App\Data\Orders\OrderResult;
use App\Data\Orders\QuoteCommand;
use App\Models\User;
use App\Services\OrderAmountCalculator;
use App\Support\CanonicalJson;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OrderContractsTest extends TestCase
{
    private function input(): array
    {
        return [
            'contract_version' => 1, 'idempotency_key' => '2dd9e37c-85f7-456c-a4cd-80c790ecf5e1',
            'cash_register_id' => 4, 'items' => [
                ['kind' => 'product', 'product_id' => 3, 'qty' => 2, 'expected_unit_price' => '49.90'],
            ], 'expected_total' => '99.80', 'payments' => [
                ['method' => 'pix', 'amount' => '50.00'], ['method' => 'dinheiro', 'amount' => '49.80'],
            ],
        ];
    }

    private function actor(): User { $user = new User(); $user->id = 91; return $user; }

    public function test_canonical_hash_normalizes_money_case_whitespace_and_unordered_payments(): void
    {
        $input = $this->input();
        $first = CheckoutCommand::fromArray($input);
        $input['idempotency_key'] = strtoupper($input['idempotency_key']);
        $input['items'][0]['expected_unit_price'] = '0049.9';
        $input['payments'] = array_reverse($input['payments']);
        $second = CheckoutCommand::fromArray($input);
        $this->assertSame($first->hash(), $second->hash());
        $this->assertSame($first->key(), $second->key());
        $this->assertSame('49.90', $second->payload()['items'][0]['expected_unit_price']);
    }

    public static function invalidInputs(): array
    {
        return [
            ['version'], ['key'], ['cash'], ['float'], ['qty'], ['qty-overflow'], ['negative-discount'],
            ['too-large-discount'], ['payment-sum'], ['payment-float'], ['payment-pending'], ['method'],
            ['unknown'], ['authority'], ['stock-switch'], ['service'], ['zero'], ['too-many-items'], ['too-many-payments'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_contract_rejects_invalid_or_authority_fields(string $kind): void
    {
        $input = $this->input();
        switch ($kind) {
            case 'version': $input['contract_version'] = 2; break;
            case 'key': $input['idempotency_key'] = 'bad'; break;
            case 'cash': unset($input['cash_register_id']); break;
            case 'float': $input['items'][0]['expected_unit_price'] = 49.9; break;
            case 'qty': $input['items'][0]['qty'] = 1.5; break;
            case 'qty-overflow': $input['items'][0]['qty'] = Money::MAX_QTY + 1; break;
            case 'negative-discount': $input['discount'] = '-0.01'; break;
            case 'too-large-discount': $input['items'][0]['discount'] = '100.00'; break;
            case 'payment-sum': $input['payments'][0]['amount'] = '49.99'; break;
            case 'payment-float': $input['payments'][0]['amount'] = 50.0; break;
            case 'payment-pending': $input['payments'][0]['status'] = 'pending'; break;
            case 'method': $input['payments'][0]['method'] = 'mixed'; break;
            case 'unknown': $input['unexpected'] = true; break;
            case 'authority': $input['user_id'] = 1; break;
            case 'stock-switch': $input['lowered_stock'] = false; break;
            case 'service': $input['items'][0]['kind'] = 'labor'; break;
            case 'zero': $input['expected_total'] = '0.00'; break;
            case 'too-many-items': $input['items'] = array_fill(0, 201, $input['items'][0]); break;
            case 'too-many-payments': $input['payments'] = array_fill(0, 21, ['method' => 'pix','amount' => '0.01']); break;
        }
        $this->expectException(InvalidArgumentException::class);
        CheckoutCommand::fromArray($input);
    }

    public function test_quote_has_no_cash_or_payment_and_can_have_zero_value(): void
    {
        $input = $this->input();
        unset($input['cash_register_id'],$input['payments'],$input['expected_total']);
        $input['items'][0]['expected_unit_price'] = '0.00';
        $quote = QuoteCommand::fromArray($input);
        $this->assertSame('order.quote.create', $quote->operation());
        $totals = (new OrderAmountCalculator())->calculate($quote, [3 => new CatalogProduct(3, Money::cents(0), true, 0)], $this->actor());
        $this->assertSame('0.00', $totals->total->decimalString());
        $input['cash_register_id'] = 4;
        $this->expectException(InvalidArgumentException::class);
        QuoteCommand::fromArray($input);
    }

    public function test_finalization_accepts_only_order_version_cash_and_confirmed_payments(): void
    {
        $input = ['contract_version' => 1,'idempotency_key' => $this->input()['idempotency_key'], 'order_id' => 9,
            'expected_order_hash' => str_repeat('a',64),'cash_register_id' => 4,'payments' => [['method'=>'pix','amount'=>'0.01']]];
        $cmd = FinalizeOrderCommand::fromArray($input);
        $this->assertSame('order.finalize', $cmd->operation());
        $this->assertSame(9, $cmd->payload()['order_id']);
        $input['items'] = [];
        $this->expectException(InvalidArgumentException::class);
        FinalizeOrderCommand::fromArray($input);
    }

    public function test_calculation_uses_trusted_catalog_and_exact_split(): void
    {
        $cmd = CheckoutCommand::fromArray($this->input());
        $r = (new OrderAmountCalculator())->calculate($cmd, [3 => new CatalogProduct(3, Money::decimal('49.90'), true, 2)], $this->actor());
        $this->assertSame('99.80', $r->subtotal->decimalString());
        $this->assertSame(9980, $r->lines[0]['total_cents']);
        $this->assertNull($r->lines[0]['price_approved_by']);
    }

    public static function catalogConflicts(): array { return [['price'],['inactive'],['stock'],['missing']]; }

    #[DataProvider('catalogConflicts')]
    public function test_server_catalog_conflicts(string $kind): void
    {
        $catalog = [3 => new CatalogProduct(3, Money::decimal($kind === 'price' ? '50.00' : '49.90'), $kind !== 'inactive', $kind === 'stock' ? 1 : 2)];
        if ($kind === 'missing') $catalog = [];
        $this->expectException(ConflictHttpException::class);
        (new OrderAmountCalculator())->calculate(CheckoutCommand::fromArray($this->input()), $catalog, $this->actor());
    }

    public function test_aggregates_stock_without_merging_commercial_lines(): void
    {
        $input = $this->input();
        $input['items'] = [
            ['kind'=>'product','product_id'=>3,'qty'=>1,'expected_unit_price'=>'49.90'],
            ['kind'=>'product','product_id'=>3,'qty'=>1,'expected_unit_price'=>'49.90'],
        ];
        $cmd = CheckoutCommand::fromArray($input);
        $this->assertCount(2, $cmd->payload()['items']);
        $this->expectException(ConflictHttpException::class);
        (new OrderAmountCalculator())->calculate($cmd, [3 => new CatalogProduct(3, Money::decimal('49.90'), true, 1)], $this->actor());
    }

    public function test_untracked_is_server_policy_and_does_not_allow_inactive_product(): void
    {
        $r = (new OrderAmountCalculator())->calculate(CheckoutCommand::fromArray($this->input()),
            [3 => new CatalogProduct(3, Money::decimal('49.90'), true, 0, 'untracked')], $this->actor());
        $this->assertSame('untracked', $r->lines[0]['stock_policy']);
    }

    public function test_exception_is_denied_without_specific_server_approval(): void
    {
        $input = $this->input();
        $input['items'][0]['requested_unit_price'] = '49.00';
        $input['items'][0]['price_reason'] = 'Negociação';
        $input['expected_total'] = '98.00';$input['payments'] = [['method'=>'pix','amount'=>'98.00']];
        $this->expectException(AuthorizationException::class);
        (new OrderAmountCalculator())->calculate(CheckoutCommand::fromArray($input),
            [3 => new CatalogProduct(3, Money::decimal('49.90'), true, 2)], $this->actor());
    }

    public function test_approval_identity_is_derived_from_server_actor(): void
    {
        $input = $this->input();
        $input['items'][0]['requested_unit_price'] = '49.00';
        $input['items'][0]['price_reason'] = 'Negociação';
        $input['items'][0]['discount'] = '0.01';
        $input['discount'] = '0.02';$input['discount_reason'] = 'Acordo';
        $input['expected_total'] = '97.97';$input['payments'] = [['method'=>'pix','amount'=>'97.97']];
        $approval = new class implements PriceExceptionAuthorization {
            public array $calls = [];
            public function authorize(User $actor, string $action, array $context): void { $this->calls[] = [$actor->id,$action]; }
        };
        $result = (new OrderAmountCalculator())->calculate(CheckoutCommand::fromArray($input),
            [3 => new CatalogProduct(3, Money::decimal('49.90'), true, 2)], $this->actor(), $approval);
        $this->assertSame('97.97', $result->total->decimalString());
        $this->assertSame(91, $result->lines[0]['price_approved_by']);
        $this->assertSame([[91,'line-price'],[91,'order-discount']], $approval->calls);
    }

    public function test_canonical_json_rejects_floats_at_any_depth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CanonicalJson::encode(['nested'=>[['amount'=>0.1]]]);
    }

    public function test_subtotal_overflow_is_rejected_even_when_each_line_fits(): void
    {
        $input=$this->input();unset($input['cash_register_id'],$input['payments'],$input['expected_total']);
        $input['items']=array_fill(0,2,['kind'=>'product','product_id'=>3,'qty'=>1,'expected_unit_price'=>'99999999.99']);
        $quote=QuoteCommand::fromArray($input);
        $this->expectException(InvalidArgumentException::class);
        (new OrderAmountCalculator())->calculate($quote,[3=>new CatalogProduct(3,Money::cents(Money::MAX_CENTS),true,0)],$this->actor());
    }

    public function test_aggregated_quantity_overflow_is_rejected_before_addition(): void
    {
        $input=$this->input();unset($input['cash_register_id'],$input['payments'],$input['expected_total']);
        $input['items']=array_fill(0,2,['kind'=>'product','product_id'=>3,'qty'=>Money::MAX_QTY,'expected_unit_price'=>'0.00']);
        $quote=QuoteCommand::fromArray($input);
        $this->expectException(InvalidArgumentException::class);
        (new OrderAmountCalculator())->calculate($quote,[3=>new CatalogProduct(3,Money::cents(0),true,0)],$this->actor());
    }

    public function test_result_rejects_cost_leaks_and_quote_effects(): void
    {
        $data = ['contract_version'=>1,'operation_key'=>$this->input()['idempotency_key'],'order_id'=>1,'number'=>'2026-000001',
            'status'=>'draft','cash_register_id'=>null,'subtotal'=>'0.00','discount'=>'0.00','total'=>'0.00',
            'payments'=>[],'cash_movement_ids'=>[],'stock_movement_ids'=>[]];
        $this->assertSame('0.00', OrderResult::fromArray($data)->data['total']);
        $data['cost_price'] = '1.00';
        $this->expectException(InvalidArgumentException::class);
        OrderResult::fromArray($data);
    }
}
