<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      $t->foreignId('customer_id')->nullable()->after('customer_name')
        ->constrained('customers')->nullOnDelete();
    });
  }
  public function down(): void {
    Schema::table('orders', function (Blueprint $t) {
      $t->dropConstrainedForeignId('customer_id');
    });
  }
};
