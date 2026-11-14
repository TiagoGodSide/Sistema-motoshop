<?php

namespace App\Models;

/** Composite-key read model: use forItem(), not find()/save(). */
class OrderItemSnapshot extends ReadOnlyFinancialRecord
{
    public $incrementing = false;
    protected $casts = ['qty' => 'integer', 'unit_price_cents' => 'integer', 'catalog_price_cents' => 'integer',
        'discount_cents' => 'integer', 'total_cents' => 'integer'];
    public static function forItem(int $receiptId, int $itemId): self
    {
        return static::where('operation_receipt_id', $receiptId)->where('order_item_id', $itemId)->sole();
    }
    public function receipt() { return $this->belongsTo(OperationReceipt::class, 'operation_receipt_id'); }
    public function item() { return $this->belongsTo(OrderItem::class, 'order_item_id'); }
}
