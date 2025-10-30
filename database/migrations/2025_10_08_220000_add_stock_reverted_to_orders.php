<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders','stock_reverted_at')) {
                $t->timestamp('stock_reverted_at')->nullable()->after('lowered_stock');
            }
        });
    }
    public function down(): void {
        Schema::table('orders', function (Blueprint $t) {
            if (Schema::hasColumn('orders','stock_reverted_at')) {
                $t->dropColumn('stock_reverted_at');
            }
        });
    }
};
