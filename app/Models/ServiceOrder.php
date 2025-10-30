<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrder extends Model
{
    protected $fillable = [
        'number','customer_id','vehicle','plate','status',
        'due_date','labor_total','parts_total','discount','total','notes','user_id'
    ];

    public function items(){ return $this->hasMany(ServiceOrderItem::class); }
    public function customer(){ return $this->belongsTo(Customer::class); }
    public function user(){ return $this->belongsTo(User::class); }

    protected static function booted(){
        static::creating(function($m){
            if (empty($m->number)) {
                $seq = str_pad((string)((self::max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT);
                $m->number = 'OS-'.$seq;
            }
        });
    }

    public function recalcTotals(): void
    {
        $labor = $this->items()->where('type','labor')->sum('total');
        $parts = $this->items()->where('type','part')->sum('total');
        $this->labor_total = $labor;
        $this->parts_total = $parts;
        $this->total = max(0, $labor + $parts - ($this->discount ?? 0));
        $this->save();
    }
}
