<?php

namespace App\Services;

use App\Support\CanonicalJson;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class QuoteVersion
{
    public function current(FinancialContext $context, int $orderId): string
    {
        return CanonicalJson::hash($this->snapshot($context, $orderId, true));
    }

    /** Financial content must survive preparation; lifecycle flags may change. */
    public function financialContent(FinancialContext $context, int $orderId): string
    {
        $snapshot = $this->snapshot($context, $orderId, false);
        foreach (['status', 'lowered_stock', 'stock_reverted_at', 'payment_method'] as $field) {
            unset($snapshot['order'][$field]);
        }
        return CanonicalJson::hash($snapshot);
    }

    private function snapshot(FinancialContext $context, int $orderId, bool $requireDraft): array
    {
        $context->assertActive();
        $order = DB::table('orders')->where('id', $orderId)->select('id','number','status','customer_id','customer_name','lowered_stock','stock_reverted_at','payment_method')
            ->selectRaw('CAST(subtotal AS CHAR) as subtotal, CAST(discount AS CHAR) as discount, CAST(total AS CHAR) as total')
            ->lockForUpdate()->first();
        if ($order === null || ($requireDraft && $order->status !== 'draft')) {
            throw new ConflictHttpException('O pedido não é um orçamento finalizável.');
        }
        foreach (['subtotal','discount','total'] as $field) {
            $order->$field = Money::decimal($order->$field)->decimalString();
        }
        $items = DB::table('order_items')->where('order_id', $orderId)->orderBy('id')
            ->select('id','product_id','variation_id','qty')->selectRaw('CAST(price AS CHAR) as price, CAST(discount AS CHAR) as discount')
            ->lockForUpdate()->get()->map(function ($item) {
                $item->price = Money::decimal($item->price)->decimalString();
                $item->discount = Money::decimal($item->discount)->decimalString();
                return (array) $item;
            })->all();
        return ['order' => (array) $order, 'items' => $items];
    }
}
