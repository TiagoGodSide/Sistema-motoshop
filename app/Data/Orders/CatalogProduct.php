<?php

namespace App\Data\Orders;

use App\Support\Money;
use InvalidArgumentException;

/** Trusted catalog snapshot supplied by the future locked repository, never by HTTP hydration. */
final readonly class CatalogProduct
{
    public function __construct(
        public int $id, public Money $price, public bool $active,
        public int $stock, public string $stockPolicy = 'tracked',
    ) {
        if ($id < 1 || ! in_array($stockPolicy, ['tracked', 'untracked'], true)) {
            throw new InvalidArgumentException('Política de catálogo inválida.');
        }
    }
}
