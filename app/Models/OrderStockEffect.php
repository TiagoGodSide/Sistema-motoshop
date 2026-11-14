<?php

namespace App\Models;

class OrderStockEffect extends ReadOnlyFinancialRecord
{
    protected $primaryKey = 'stock_movement_id';
    public $incrementing = false;
    public function receipt() { return $this->belongsTo(OperationReceipt::class, 'operation_receipt_id'); }
    public function movement() { return $this->belongsTo(StockMovement::class, 'stock_movement_id'); }
    public function item() { return $this->belongsTo(OrderItem::class, 'order_item_id'); }
}
