<?php

namespace App\Data\Orders;

use App\Support\Money;
use InvalidArgumentException;

final readonly class CheckoutCommand extends OrderCommandData
{
    public function operation(): string { return 'pos.checkout'; }

    public static function fromArray(array $input): self
    {
        $key = OrderInput::envelope($input, ['cash_register_id', 'customer_id', 'customer_name', 'items', 'discount', 'discount_reason', 'expected_total', 'payments']);
        $data = OrderInput::cart($input);
        $data['cash_register_id'] = OrderInput::id($input['cash_register_id'] ?? null);
        $total = Money::decimal($input['expected_total'] ?? null);
        $data['expected_total'] = $total->decimalString();
        $data['payments'] = OrderInput::payments($input['payments'] ?? null);
        $sum = Money::cents(0);
        foreach ($data['payments'] as $p) {
            $sum = $sum->add(Money::decimal($p['amount']));
        }
        if ($total->cents === 0 || $sum->cents !== $total->cents) {
            throw new InvalidArgumentException('Liquidação positiva e soma exata obrigatórias.');
        }
        return new self($key, $data);
    }
}
