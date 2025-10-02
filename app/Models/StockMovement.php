<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id','type','qty','unit_price','reason','user_id'
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'float',
    ];

    public function product() { return $this->belongsTo(Product::class); }
}
