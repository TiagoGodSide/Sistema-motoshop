<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('order_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('method', 20);     // dinheiro|pix|cartao|outro|mixed
            $t->decimal('amount', 10, 2);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('order_payments'); }
};
