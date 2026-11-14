<?php

use App\Support\FinancialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        FinancialSchema::create('document_sequences', function (Blueprint $t) {
            $t->string('scope', 20);
            $t->unsignedSmallInteger('business_year');
            $t->unsignedInteger('last_value');
            $t->dateTime('updated_at_utc', 6);
            $t->primary(['scope', 'business_year'], 'ds_pk');
        }, [
            'ds_year_ck' => FinancialSchema::integerRange('business_year', 1, 9999),
            'ds_sequence_ck' => FinancialSchema::integerRange('last_value', 0, 999999),
        ]);
    }

    public function down(): void { Schema::dropIfExists('document_sequences'); }
};
