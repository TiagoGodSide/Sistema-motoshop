<?php

use App\Support\FinancialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        FinancialSchema::create('order_settlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique('os_order_uq')->constrained('orders')->restrictOnDelete()->restrictOnUpdate();
            $t->unsignedBigInteger('operation_receipt_id')->unique('os_receipt_uq');
            $t->unsignedBigInteger('financial_operation_id')->unique('os_fin_uq');
            $t->unsignedBigInteger('cash_register_id');
            $t->unsignedBigInteger('total_cents');
            $t->dateTime('recorded_at_utc', 6);
            $t->unique(['id', 'order_id', 'cash_register_id'], 'os_context_uq');
            $t->index(['operation_receipt_id', 'order_id', 'financial_operation_id'], 'os_receipt_ix');
            $t->index(['financial_operation_id', 'cash_register_id'], 'os_cash_ix');
            $t->foreign(['operation_receipt_id', 'order_id', 'financial_operation_id'], 'os_receipt_fk')
                ->references(['id', 'order_id', 'financial_operation_id'])->on('operation_receipts')->restrictOnDelete()->restrictOnUpdate();
            $t->foreign(['financial_operation_id', 'cash_register_id'], 'os_cash_fk')
                ->references(['id', 'cash_register_id'])->on('financial_operations')->restrictOnDelete()->restrictOnUpdate();
        }, ['os_total_ck' => FinancialSchema::integerRange('total_cents', 1, 9999999999)]);
    }

    public function down(): void { Schema::dropIfExists('order_settlements'); }
};
