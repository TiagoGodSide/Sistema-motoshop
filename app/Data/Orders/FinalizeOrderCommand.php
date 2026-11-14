<?php

namespace App\Data\Orders;

use InvalidArgumentException;

final readonly class FinalizeOrderCommand extends OrderCommandData
{
    public function operation(): string { return 'order.finalize'; }

    public static function fromArray(array $input): self
    {
        $key = OrderInput::envelope($input, ['order_id', 'expected_order_hash', 'cash_register_id', 'payments']);
        if (! is_string($input['expected_order_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $input['expected_order_hash'])) {
            throw new InvalidArgumentException('Versão verificável do orçamento obrigatória.');
        }
        return new self($key, [
            'order_id' => OrderInput::id($input['order_id'] ?? null),
            'expected_order_hash' => $input['expected_order_hash'],
            'cash_register_id' => OrderInput::id($input['cash_register_id'] ?? null),
            'payments' => OrderInput::payments($input['payments'] ?? null),
        ]);
    }
}
