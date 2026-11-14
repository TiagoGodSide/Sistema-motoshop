<?php

use App\Support\FinancialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        FinancialSchema::verify([
            'order_payments' => ['id' => 'bigint unsigned', 'order_id' => 'bigint unsigned'],
            'cash_movements' => ['id' => 'bigint unsigned', 'order_id' => 'bigint unsigned', 'cash_register_id' => 'bigint unsigned'],
            'order_items' => ['id' => 'bigint unsigned', 'order_id' => 'bigint unsigned', 'product_id' => 'bigint unsigned'],
            'stock_movements' => ['id' => 'bigint unsigned', 'product_id' => 'bigint unsigned'],
        ]);
        Schema::table('order_payments', fn (Blueprint $t) => $t->unique(['id', 'order_id'], 'op_order_uq'));
        Schema::table('cash_movements', fn (Blueprint $t) => $t->unique(['id', 'order_id', 'cash_register_id'], 'cm_context_uq'));
        Schema::table('order_items', fn (Blueprint $t) => $t->unique(['id', 'order_id', 'product_id'], 'oi_context_uq'));
        Schema::table('stock_movements', fn (Blueprint $t) => $t->unique(['id', 'product_id'], 'sm_product_uq'));

        FinancialSchema::create('order_cash_effects', function (Blueprint $t) {
            $t->unsignedBigInteger('cash_movement_id')->primary();
            $t->unsignedBigInteger('settlement_id');
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('cash_register_id');
            $t->unsignedBigInteger('order_payment_id')->nullable()->unique('oce_payment_uq');
            $t->string('effect', 30);
            $t->string('effect_key', 80);
            $t->unique(['settlement_id', 'effect_key'], 'oce_effect_uq');
            $t->index(['cash_movement_id', 'order_id', 'cash_register_id'], 'oce_movement_ix');
            $t->index(['settlement_id', 'order_id', 'cash_register_id'], 'oce_settlement_ix');
            $t->index(['order_payment_id', 'order_id'], 'oce_payment_ix');
            $t->foreign(['cash_movement_id', 'order_id', 'cash_register_id'], 'oce_movement_fk')
                ->references(['id', 'order_id', 'cash_register_id'])->on('cash_movements')->restrictOnDelete()->restrictOnUpdate();
            $t->foreign(['settlement_id', 'order_id', 'cash_register_id'], 'oce_settlement_fk')
                ->references(['id', 'order_id', 'cash_register_id'])->on('order_settlements')->restrictOnDelete()->restrictOnUpdate();
            $t->foreign(['order_payment_id', 'order_id'], 'oce_payment_fk')
                ->references(['id', 'order_id'])->on('order_payments')->restrictOnDelete()->restrictOnUpdate();
        }, ['oce_effect_ck' => "effect in ('payment', 'automatic_withdrawal')"]);

        FinancialSchema::create('order_stock_effects', function (Blueprint $t) {
            $t->unsignedBigInteger('stock_movement_id')->primary();
            $t->unsignedBigInteger('operation_receipt_id');
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('order_item_id');
            $t->unsignedBigInteger('product_id');
            $t->string('effect', 20);
            $t->unique(['operation_receipt_id', 'order_item_id', 'effect'], 'ose_effect_uq');
            $t->index(['stock_movement_id', 'product_id'], 'ose_movement_ix');
            $t->index(['operation_receipt_id', 'order_id'], 'ose_receipt_ix');
            $t->index(['order_item_id', 'order_id', 'product_id'], 'ose_item_ix');
            $t->foreign(['stock_movement_id', 'product_id'], 'ose_movement_fk')
                ->references(['id', 'product_id'])->on('stock_movements')->restrictOnDelete()->restrictOnUpdate();
            $t->foreign(['operation_receipt_id', 'order_id'], 'ose_receipt_fk')
                ->references(['id', 'order_id'])->on('operation_receipts')->restrictOnDelete()->restrictOnUpdate();
            $t->foreign(['order_item_id', 'order_id', 'product_id'], 'ose_item_fk')
                ->references(['id', 'order_id', 'product_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
        }, ['ose_effect_ck' => "effect in ('sale', 'return')"]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_stock_effects');
        Schema::dropIfExists('order_cash_effects');
        Schema::table('stock_movements', fn (Blueprint $t) => $t->dropUnique('sm_product_uq'));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropUnique('oi_context_uq'));
        Schema::table('cash_movements', fn (Blueprint $t) => $t->dropUnique('cm_context_uq'));
        Schema::table('order_payments', fn (Blueprint $t) => $t->dropUnique('op_order_uq'));
    }
};
