<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceHistory extends Model
{
    protected $fillable = ['product_id','old_price','new_price','user_id'];
    protected $casts = ['old_price'=>'float','new_price'=>'float'];

    public function product(){ return $this->belongsTo(Product::class); }
    public function user(){ return $this->belongsTo(\App\Models\User::class); }
}
