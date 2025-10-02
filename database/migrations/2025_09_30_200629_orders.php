<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('number')->unique(); // ex. 2025-000001
    $table->string('customer_name')->nullable();
    $table->decimal('subtotal', 10, 2)->default(0);
    $table->decimal('discount', 10, 2)->default(0);
    $table->decimal('total', 10, 2)->default(0);
    $table->string('payment_method')->nullable(); // dinheiro, pix, cartão...
    $table->string('status')->default('open');    // open, paid, cancelled, draft
    $table->boolean('lowered_stock')->default(false);
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // vendedor
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
