<?php

use App\Support\FinancialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        FinancialSchema::verify(['financial_operations' => [
            'id' => 'bigint unsigned', 'idempotency_key' => 'char(36)', 'operation' => 'varchar(40)',
            'user_id' => 'bigint unsigned', 'cash_register_id' => 'bigint unsigned', 'request_hash' => 'char(64)',
        ], 'users' => ['id' => 'bigint unsigned'], 'orders' => ['id' => 'bigint unsigned'],
            'cash_registers' => ['id' => 'bigint unsigned'], 'products' => ['id' => 'bigint unsigned']]);
        Schema::table('financial_operations', function (Blueprint $t) {
            $t->unique(['id', 'idempotency_key', 'operation', 'user_id', 'request_hash'], 'fo_identity_uq');
            $t->unique(['id', 'cash_register_id'], 'fo_cash_uq');
        });
        FinancialSchema::create('operation_receipts', function (Blueprint $t) {
            $t->id();
            FinancialSchema::identityColumns($t);
            $t->unique('idempotency_key', 'or_key_uq');
            $t->unsignedSmallInteger('contract_version');
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $t->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete()->restrictOnUpdate();
            $t->unsignedBigInteger('financial_operation_id')->nullable()->unique('or_fin_uq');
            $t->json('result')->nullable();
            $t->char('result_hash', 64);
            $t->dateTime('recorded_at_utc', 6);
            $t->unique(['id', 'order_id'], 'or_order_uq');
            $t->unique(['id', 'order_id', 'financial_operation_id'], 'or_order_fin_uq');
            $t->index(['financial_operation_id', 'idempotency_key', 'operation', 'user_id', 'request_hash'], 'or_fin_identity_ix');
            $t->foreign(['financial_operation_id', 'idempotency_key', 'operation', 'user_id', 'request_hash'], 'or_fin_identity_fk')
                ->references(['id', 'idempotency_key', 'operation', 'user_id', 'request_hash'])->on('financial_operations')
                ->restrictOnDelete()->restrictOnUpdate();
        }, ['or_version_ck' => 'contract_version = 1']);
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_receipts');
        Schema::table('financial_operations', function (Blueprint $t) {
            $t->dropUnique('fo_identity_uq');
            $t->dropUnique('fo_cash_uq');
        });
    }
};
