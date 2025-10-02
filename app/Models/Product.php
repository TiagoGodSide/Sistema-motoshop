<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Product extends Model
{
    protected $fillable = [
        'name','sku','ean','internal_barcode','price','cost_price',
        'stock','min_stock','is_active','category_id','unit'
    ];

    protected static function booted()
    {
        static::creating(function ($product) {
            // Gera código interno se vier vazio
            if (blank($product->internal_barcode)) {
                // Ex.: INT- + ID provisório aleatório (ajuste ao seu padrão/checagem)
                $product->internal_barcode = 'INT-' . strtoupper(Str::random(8));
            }
        });
    }

    public function category() { return $this->belongsTo(Category::class); }
}
