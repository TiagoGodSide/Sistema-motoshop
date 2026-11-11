<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('operation', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('request_hash', 64);
            $table->foreignId('cash_register_id')->constrained('cash_registers')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_operations');
    }
};
