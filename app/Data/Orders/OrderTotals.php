<?php

namespace App\Data\Orders;

use App\Support\Money;

final readonly class OrderTotals
{
    public function __construct(
        public Money $subtotal, public Money $discount, public Money $total, public array $lines,
    ) {}
}
