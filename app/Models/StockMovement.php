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

    public const REASONS = [
    'inventory' => 'Inventário/Acerto',
    'loss'      => 'Perda/Quebra',
    'exchange'  => 'Troca/Devolução',
    'gift'      => 'Brinde',
    'other'     => 'Outro',
    ];

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason_code ?? ''] ?? ($this->reason ?: '—');
    }


}
