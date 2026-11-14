<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Read facade. Writes must use the coordinated services, including in maintenance code. */
abstract class ReadOnlyFinancialRecord extends Model
{
    public $timestamps = false;
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        foreach (['creating', 'updating', 'deleting'] as $event) {
            static::$event(fn () => throw new LogicException('Use os serviços financeiros coordenados.'));
        }
    }
}
