<?php

namespace App\Services;

use App\Data\Orders\CatalogProduct;
use App\Support\Money;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class OrderStockPolicy
{
    /** Quotes do not reserve stock. Sales must validate aggregated quantities. */
    public function validate(array $items, array $catalog, bool $selling): void
    {
        $quantities = [];
        foreach ($items as $item) {
            $id = $item['product_id'];
            $product = $catalog[$id] ?? null;
            if (! $product instanceof CatalogProduct || $product->id !== $id || ! $product->active) {
                throw new ConflictHttpException('Produto inexistente ou inativo.');
            }
            $current = $quantities[$id] ?? 0;
            if ($item['qty'] > Money::MAX_QTY - $current) {
                throw new InvalidArgumentException('Quantidade agregada fora do limite.');
            }
            $quantities[$id] = $current + $item['qty'];
        }
        foreach ($quantities as $id => $qty) {
            if ($selling && $catalog[$id]->stockPolicy === 'tracked' && $catalog[$id]->stock < $qty) {
                throw new ConflictHttpException('Estoque controlado insuficiente.');
            }
        }
    }
}
