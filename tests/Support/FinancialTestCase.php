<?php

namespace Tests\Support;

use App\Data\Orders\CheckoutCommand;
use App\Data\Orders\FinalizeOrderCommand;
use App\Data\Orders\OrderResult;
use App\Data\Orders\PreparedOrderOperation;
use App\Data\Orders\QuoteCommand;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\User;
use App\Services\FinancialContext;
use App\Services\OrderNumberService;
use App\Support\Money;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class FinancialTestCase extends TestCase
{
    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['database.connections.mysql' => null, 'database.connections.sqlite.url' => null,
            'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null']);
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        Artisan::call('migrate', ['--force' => true]);
        $this->seed(RolesAndAdminSeeder::class);
        $this->actor = User::factory()->create(['active' => true, 'role' => 'seller']);
        $this->actor->assignRole('admin');
    }

    protected function product(): Product
    {
        return Product::create(['name' => 'Fixture', 'sku' => 'F-'.Str::uuid(), 'price' => '10.00', 'stock' => 20]);
    }

    protected function cash(): CashRegister
    {
        return CashRegister::create(['status' => 'open', 'opening_amount' => '0.00', 'user_opened_id' => $this->actor->id]);
    }

    protected function input(Product $product, ?CashRegister $cash = null): array
    {
        $data = [
            'contract_version' => 1, 'idempotency_key' => (string) Str::uuid(),
            'items' => [['kind' => 'product', 'product_id' => $product->id, 'qty' => 1, 'expected_unit_price' => '10.00']],
        ];
        return $cash ? $data + ['cash_register_id' => $cash->id, 'expected_total' => '10.00', 'payments' => [['method' => 'pix', 'amount' => '10.00']]] : $data;
    }

    /** Synthetic trusted handler for infrastructure tests; no production controller is invoked. */
    protected function prepare(FinancialContext $context, CheckoutCommand|QuoteCommand|FinalizeOrderCommand $command): PreparedOrderOperation
    {
        $quote = $command instanceof QuoteCommand;
        $data = $command->payload();
        $now = $context->utc();
        if ($command instanceof FinalizeOrderCommand) {
            $orderId = $data['order_id'];
            $header = DB::table('orders')->where('id', $orderId)->first();
            $number = $header->number;
            $items = DB::table('order_items')->where('order_id', $orderId)->select('*')
                ->selectRaw('CAST(price AS CHAR) price_text, CAST(discount AS CHAR) discount_text')->orderBy('id')->get();
        } else {
            $number = app(OrderNumberService::class)->reserve($context);
            $orderId = DB::table('orders')->insertGetId([
                'number' => $number, 'status' => $quote ? 'draft' : 'paid', 'user_id' => $this->actor->id,
                'subtotal' => '0.00', 'discount' => '0.00', 'total' => '0.00', 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($data['items'] as $item) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId, 'product_id' => $item['product_id'], 'qty' => $item['qty'],
                    'price' => $item['expected_unit_price'], 'discount' => $item['discount'], 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $items = DB::table('order_items')->where('order_id', $orderId)->select('*')
                ->selectRaw('CAST(price AS CHAR) price_text, CAST(discount AS CHAR) discount_text')->orderBy('id')->get();
        }
        $subtotal = Money::cents(0);
        $snapshots = [];
        $stockEffects = [];
        foreach ($items as $item) {
            $unit = Money::decimal($item->price_text);
            $discount = Money::decimal($item->discount_text);
            $net = $unit->multiply((int) $item->qty)->subtract($discount);
            $subtotal = $subtotal->add($net);
            $policy = DB::table('products')->where('id', $item->product_id)->value('stock_policy') ?? 'tracked';
            $snapshots[(int) $item->id] = [
                'product_id' => (int) $item->product_id, 'qty' => (int) $item->qty,
                'catalog_price_cents' => $unit->cents, 'unit_price_cents' => $unit->cents,
                'discount_cents' => $discount->cents, 'total_cents' => $net->cents,
                'price_source' => 'catalog', 'price_reason' => null, 'price_approved_by' => null, 'stock_policy' => $policy,
            ];
            if (! $quote && $policy === 'tracked') {
                DB::table('products')->where('id', $item->product_id)->decrement('stock', $item->qty);
                $stockEffects[(int) $item->id] = (int) DB::table('stock_movements')->insertGetId([
                    'product_id' => $item->product_id, 'type' => 'OUT', 'qty' => $item->qty, 'unit_price' => $unit->decimalString(),
                    'reason' => 'Synthetic 4.3.1 fixture', 'user_id' => $this->actor->id, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        $discount = Money::decimal($data['discount'] ?? '0.00');
        $total = $subtotal->subtract($discount);
        DB::table('orders')->where('id', $orderId)->update([
            'status' => $quote ? 'draft' : 'paid', 'subtotal' => $subtotal->decimalString(),
            'discount' => $discount->decimalString(), 'total' => $total->decimalString(),
            'lowered_stock' => ! $quote && count($stockEffects) > 0, 'payment_method' => $quote ? null : 'mixed',
        ]);
        $payments = [];
        $moves = [];
        if (! $quote) {
            foreach ($data['payments'] as $part) {
                $id = DB::table('order_payments')->insertGetId(['order_id' => $orderId, 'method' => $part['method'],
                    'amount' => $part['amount'], 'created_at' => $now, 'updated_at' => $now]);
                $payments[] = ['id' => (int) $id] + $part;
                $moves[] = (int) DB::table('cash_movements')->insertGetId([
                    'cash_register_id' => $data['cash_register_id'], 'order_id' => $orderId, 'user_id' => $this->actor->id,
                    'type' => 'IN', 'amount' => $part['amount'], 'payment_method' => $part['method'],
                    'reason' => 'Synthetic fixture', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        return new PreparedOrderOperation(OrderResult::fromArray([
            'contract_version' => 1, 'operation_key' => $command->key(), 'order_id' => (int) $orderId, 'number' => $number,
            'status' => $quote ? 'draft' : 'paid', 'cash_register_id' => $quote ? null : $data['cash_register_id'],
            'subtotal' => $subtotal->decimalString(), 'discount' => $discount->decimalString(), 'total' => $total->decimalString(),
            'discount_approval' => null, 'payments' => $payments, 'cash_movement_ids' => $moves, 'stock_movement_ids' => array_values($stockEffects),
        ]), $snapshots, $stockEffects);
    }

    protected function state(): string
    {
        $out = [];
        foreach (['orders','order_items','products','order_payments','cash_registers','cash_movements','stock_movements',
            'financial_operations','operation_receipts','order_settlements','order_cash_effects','order_stock_effects',
            'order_item_snapshots','document_sequences'] as $table) {
            $out[$table] = DB::table($table)->get()->toArray();
        }
        return json_encode($out, JSON_THROW_ON_ERROR);
    }
}
