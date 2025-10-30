<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('service_order_items', function (Blueprint $t) {
      $t->id();
      $t->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
      $t->enum('type',['part','labor']);   // peça ou mão de obra
      $t->unsignedBigInteger('product_id')->nullable(); // se for peça
      $t->string('description');
      $t->integer('qty')->default(1);
      $t->decimal('unit_price',10,2)->default(0);
      $t->decimal('discount',10,2)->default(0);
      $t->decimal('total',10,2)->default(0);
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('service_order_items'); }
};
