<?php

namespace App\Data\Orders;

final readonly class QuoteCommand extends OrderCommandData
{
    public function operation(): string { return 'order.quote.create'; }

    public static function fromArray(array $input): self
    {
        $key = OrderInput::envelope($input, ['customer_id', 'customer_name', 'items', 'discount', 'discount_reason']);
        return new self($key, OrderInput::cart($input));
    }
}
