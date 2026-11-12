<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_operations', function (Blueprint $table) {
            $table->foreignId('cash_movement_id')->nullable()->unique()
                ->constrained('cash_movements')->restrictOnDelete();
            $table->json('result')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('financial_operations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_movement_id');
            $table->dropColumn('result');
        });
    }
};
