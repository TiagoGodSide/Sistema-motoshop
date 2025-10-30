<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str; // <— importa o Str

class Product extends Model
{
    protected $fillable = [
        'name','sku','ean','internal_barcode','price','cost_price',
        'stock','min_stock','is_active','category_id','unit',
    ];

    // CASTS (conversões automáticas de tipo)
    protected $casts = [
        'price'      => 'float',
        'cost_price' => 'float',
        'stock'      => 'int',
        'min_stock'  => 'int',
        'is_active'  => 'bool',
    ];

    // Geração automática do código interno (garante unicidade)
    protected static function booted()
    {
        static::creating(function ($product) {
            if (blank($product->internal_barcode)) {
                do {
                    $code = 'INT-'.strtoupper(Str::random(8));
                } while (static::where('internal_barcode', $code)->exists());

                $product->internal_barcode = $code;
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // HELPER (escopo): só produtos ativos
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }


    public function scopeBelowMin($q){
    return $q->where('is_active', true)
             ->whereNotNull('min_stock')
             ->whereColumn('stock','<','min_stock');
        }

        public function priceHistories()
            {
                return $this->hasMany(\App\Models\ProductPriceHistory::class);
            }

            public function stockMovements()
            {
                return $this->hasMany(\App\Models\StockMovement::class);
            }
}
