<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('product_price_histories', function (Blueprint $t) {
      $t->id();
      $t->foreignId('product_id')->constrained()->cascadeOnDelete();
      $t->decimal('old_price', 10, 2)->nullable();
      $t->decimal('new_price', 10, 2);
      $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
      $t->timestamps();
    });
  }
  public function down(): void {
    Schema::dropIfExists('product_price_histories');
  }
};
