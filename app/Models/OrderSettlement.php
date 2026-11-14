<?php

namespace App\Models;

class OrderSettlement extends ReadOnlyFinancialRecord
{
    protected $casts = ['total_cents' => 'integer'];
    public function order() { return $this->belongsTo(Order::class); }
    public function receipt() { return $this->belongsTo(OperationReceipt::class, 'operation_receipt_id'); }
    public function cashEffects() { return $this->hasMany(OrderCashEffect::class, 'settlement_id'); }
}
