<?php

namespace App\Models;

class OperationReceipt extends ReadOnlyFinancialRecord
{
    protected $casts = ['result' => 'array', 'contract_version' => 'integer'];
    public function order() { return $this->belongsTo(Order::class); }
    public function actor() { return $this->belongsTo(User::class, 'user_id'); }
    public function settlement() { return $this->hasOne(OrderSettlement::class); }
}
