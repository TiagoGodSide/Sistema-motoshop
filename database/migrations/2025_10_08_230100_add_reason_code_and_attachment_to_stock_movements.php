<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('stock_movements', function (Blueprint $t) {
      $t->string('reason_code', 30)->nullable()->after('reason');       // ex.: 'inventory','loss',...
      $t->string('attachment_path')->nullable()->after('reason_code');  // arquivo opcional
    });
  }
  public function down(): void {
    Schema::table('stock_movements', function (Blueprint $t) {
      $t->dropColumn(['reason_code','attachment_path']);
    });
  }
};
