<?php

use App\Support\FinancialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        FinancialSchema::create('order_item_snapshots', function (Blueprint $t) {
            $t->unsignedBigInteger('operation_receipt_id');
            $t->unsignedBigInteger('order_item_id');
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('product_id');
            $t->integer('qty');
            $t->unsignedBigInteger('catalog_price_cents')->nullable();
            $t->unsignedBigInteger('unit_price_cents');
            $t->unsignedBigInteger('discount_cents');
            $t->unsignedBigInteger('total_cents');
            $t->string('price_source', 20);
            $t->string('price_reason', 500)->nullable();
            $t->foreignId('price_approved_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $t->string('stock_policy', 20);
            $t->dateTime('recorded_at_utc', 6);
            $t->primary(['operation_receipt_id', 'order_item_id'], 'ois_pk');
            $t->index(['operation_receipt_id', 'order_id'], 'ois_receipt_ix');
            $t->index(['order_item_id', 'order_id', 'product_id'], 'ois_item_ix');
            $t->foreign(['operation_receipt_id', 'order_id'], 'ois_receipt_fk')
                ->references(['id', 'order_id'])->on('operation_receipts')->restrictOnDelete()->restrictOnUpdate();
            $t->foreign(['order_item_id', 'order_id', 'product_id'], 'ois_item_fk')
                ->references(['id', 'order_id', 'product_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
        }, [
            'ois_qty_ck' => FinancialSchema::integerRange('qty', 1, 2147483647),
            'ois_catalog_ck' => FinancialSchema::integerRange('catalog_price_cents', 0, 9999999999, true),
            'ois_unit_ck' => FinancialSchema::integerRange('unit_price_cents', 0, 9999999999),
            'ois_discount_ck' => FinancialSchema::integerRange('discount_cents', 0, 9999999999),
            'ois_total_ck' => FinancialSchema::integerRange('total_cents', 0, 9999999999),
            'ois_price_source_ck' => "price_source in ('catalog', 'quote', 'negotiated')",
            'ois_stock_policy_ck' => "stock_policy in ('tracked', 'untracked')",
        ]);
        // Scalar ADD COLUMN only: no legacy FK or table reconstruction.
        Schema::table('products', fn (Blueprint $t) => $t->string('stock_policy', 20)->nullable());
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_snapshots');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('stock_policy'));
    }
};
