<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cash_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $t->enum('type',['IN','OUT']);               // entrada/saída
            $t->decimal('amount',10,2);
            $t->string('payment_method')->nullable();     // dinheiro, pix, cartao...
            $t->string('reason')->nullable();             // Venda PDV 2025-000001, Sangria, Suprimento...
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cash_movements'); }
};