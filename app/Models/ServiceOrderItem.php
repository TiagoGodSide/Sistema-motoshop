<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrderItem extends Model
{
    protected $fillable = ['service_order_id','type','product_id','description','qty','unit_price','discount','total'];

    public function os(){ return $this->belongsTo(ServiceOrder::class,'service_order_id'); }
    public function product(){ return $this->belongsTo(Product::class); }

    protected static function booted(){
        static::saving(function($it){
            $it->total = max(0, ($it->qty * $it->unit_price) - ($it->discount ?? 0));
        });
        static::saved(function($it){ $it->os->recalcTotals(); });
        static::deleted(function($it){ $it->os->recalcTotals(); });
    }
}
