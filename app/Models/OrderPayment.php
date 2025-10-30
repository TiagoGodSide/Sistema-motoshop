<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    protected $fillable = ['order_id','method','amount'];
    protected $casts = ['amount' => 'float'];

    public function order() { return $this->belongsTo(Order::class); }
}

