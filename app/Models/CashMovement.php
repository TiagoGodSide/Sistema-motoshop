<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model {
    protected $fillable = ['cash_register_id','type','amount','payment_method','reason','order_id','user_id'];
    public function register(){ return $this->belongsTo(CashRegister::class,'cash_register_id'); }
}