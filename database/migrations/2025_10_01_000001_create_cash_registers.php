<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cash_registers', function (Blueprint $t) {
            $t->id();
            $t->string('status')->default('open'); // open|closed
            $t->decimal('opening_amount', 10,2)->default(0);
            $t->decimal('closing_amount', 10,2)->nullable();
            $t->timestamp('opened_at')->useCurrent();
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('user_opened_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('user_closed_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cash_registers'); }
};