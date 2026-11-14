<?php

namespace App\Models;

/** Composite-key read model. Allocation belongs to OrderNumberService. */
class DocumentSequence extends ReadOnlyFinancialRecord
{
    public $incrementing = false;
    protected $casts = ['business_year' => 'integer', 'last_value' => 'integer'];
    public static function forYear(int $year): self
    {
        return static::where('scope', 'orders')->where('business_year', $year)->sole();
    }
}
