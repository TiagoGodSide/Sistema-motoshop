<?php

namespace App\Models;

class OrderCashEffect extends ReadOnlyFinancialRecord
{
    protected $primaryKey = 'cash_movement_id';
    public $incrementing = false;
    public function settlement() { return $this->belongsTo(OrderSettlement::class, 'settlement_id'); }
    public function movement() { return $this->belongsTo(CashMovement::class, 'cash_movement_id'); }
    public function payment() { return $this->belongsTo(OrderPayment::class, 'order_payment_id'); }
}
