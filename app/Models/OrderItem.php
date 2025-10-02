<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id','product_id','variation_id','qty','price','discount'
    ];

    protected $casts = [
        'qty'   => 'integer',
        'price' => 'float',
        'discount' => 'float',
    ];

    public function order()   { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
