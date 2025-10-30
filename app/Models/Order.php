<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{

    public function customer() { return $this->belongsTo(\App\Models\Customer::class); }
    protected $fillable = [
        'number','customer_name','subtotal','discount','total',
        'payment_method','status','lowered_stock','user_id',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'discount' => 'float',
        'total'    => 'float',
        'lowered_stock' => 'boolean',
    ];

    public function items()   { return $this->hasMany(OrderItem::class); }
    public function user()    { return $this->belongsTo(User::class); }

    public function payments(){ return $this->hasMany(\App\Models\OrderPayment::class); }


}
