<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_registers', 'open_notes')) {
                $table->text('open_notes')->nullable();
            }
            if (!Schema::hasColumn('cash_registers', 'close_notes')) {
                $table->text('close_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            if (Schema::hasColumn('cash_registers', 'open_notes')) {
                $table->dropColumn('open_notes');
            }
            if (Schema::hasColumn('cash_registers', 'close_notes')) {
                $table->dropColumn('close_notes');
            }
        });
    }
};
