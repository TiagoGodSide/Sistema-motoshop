<?php

namespace Tests\Unit;

use App\Data\Orders\OrderResult;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaymentResultCanonicalizationTest extends TestCase
{
    private function resultData(): array
    {
        return ['contract_version' => 1, 'operation_key' => '2dd9e37c-85f7-456c-a4cd-80c790ecf5e1',
            'order_id' => 1, 'number' => '2026-000001', 'status' => 'paid', 'cash_register_id' => 1,
            'subtotal' => '10.00', 'discount' => '0.00', 'total' => '10.00', 'discount_approval' => null,
            'payments' => [['id' => 1, 'method' => 'pix', 'amount' => '0010.0']],
            'cash_movement_ids' => [1], 'stock_movement_ids' => [1]];
    }

    public function test_result_normalizes_payment_amounts_without_losing_their_ids(): void
    {
        $result = OrderResult::fromArray($this->resultData())->data;
        $this->assertSame('10.00', $result['payments'][0]['amount']);
        $this->assertSame(1, $result['payments'][0]['id']);
        $this->assertSame($result, OrderResult::fromArray($result)->data);
    }

    public function test_equal_parts_with_different_ids_are_legitimate_and_remain_separate(): void
    {
        $data = $this->resultData();
        $data['payments'] = [['id' => 2, 'method' => 'pix', 'amount' => '005'], ['id' => 3, 'method' => 'pix', 'amount' => '5.0']];
        $data['cash_movement_ids'] = [2, 3];
        $result = OrderResult::fromArray($data)->data;
        $this->assertCount(2, $result['payments']);
        $this->assertSame([2, 3], array_column($result['payments'], 'id'));
        $this->assertSame(['5.00', '5.00'], array_column($result['payments'], 'amount'));
    }

    public static function invalidParts(): array
    {
        return [['float'], ['zero'], ['negative'], ['limit'], ['fraction'], ['sum'], ['duplicate-payment'], ['duplicate-cash'], ['duplicate-stock']];
    }

    #[DataProvider('invalidParts')]
    public function test_invalid_or_duplicate_persisted_references_are_rejected(string $change): void
    {
        $data = $this->resultData();
        switch ($change) {
            case 'float': $data['payments'][0]['amount'] = 10.0; break;
            case 'zero': $data['payments'][0]['amount'] = '0.00'; break;
            case 'negative': $data['payments'][0]['amount'] = '-10.00'; break;
            case 'limit': $data['payments'][0]['amount'] = '100000000.00'; break;
            case 'fraction': $data['payments'][0]['amount'] = '10.001'; break;
            case 'sum': $data['payments'][0]['amount'] = '9.99'; break;
            case 'duplicate-payment':
                $data['payments'] = array_fill(0, 2, ['id' => 1, 'method' => 'pix', 'amount' => '5.00']);
                $data['cash_movement_ids'] = [1, 2]; break;
            case 'duplicate-cash': $data['cash_movement_ids'] = [1, 1]; break;
            case 'duplicate-stock': $data['stock_movement_ids'] = [1, 1]; break;
        }
        $this->expectException(InvalidArgumentException::class);
        OrderResult::fromArray($data);
    }
}
