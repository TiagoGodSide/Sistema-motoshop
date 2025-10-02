<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model {
    protected $fillable = [
        'status','opening_amount','closing_amount','opened_at','closed_at',
        'user_opened_id','user_closed_id'
    ];
    protected $casts = ['opened_at'=>'datetime','closed_at'=>'datetime'];
    public function movements(){ return $this->hasMany(CashMovement::class); }
    public function scopeOpen($q){ return $q->where('status','open'); }
}