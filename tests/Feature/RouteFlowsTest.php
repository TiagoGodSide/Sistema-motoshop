<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RouteFlowsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['database.connections.mysql' => null, 'cache.default' => 'array', 'session.driver' => 'array']);

        Artisan::call('migrate', ['--force' => true]);
        $this->seed(RolesAndAdminSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['active' => true, 'role' => 'seller']);
        $user->assignRole($role);

        return $user;
    }

    public static function importProfiles(): array
    {
        return [['admin', ';'], ['gerente', ',']];
    }

    #[DataProvider('importProfiles')]
    public function test_real_csv_handler_imports_products(string $role, string $delimiter): void
    {
        $headers = ['name', 'sku', 'price', 'cost_price', 'stock', 'min_stock', 'unit', 'category', 'is_active'];
        $values = ['Filtro de teste', 'CSV-TEST', '"49,90"', '"20,00"', '3', '1', 'UN', 'Categoria CSV', '1'];
        $csv = "\xEF\xBB\xBF".implode($delimiter, $headers)."\n".implode($delimiter, $values)."\n";

        $this->actingAs($this->userWithRole($role))->post(route('products.import'), [
            'mode' => 'insert',
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ])->assertRedirect(route('products.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('ok', 'Importação concluída: 1 criados, 0 atualizados.');

        $product = Product::sole();
        $this->assertSame('CSV-TEST', $product->sku);
        $this->assertSame(49.9, $product->price);
        $this->assertSame(20.0, $product->cost_price);
        $this->assertSame(3, $product->stock);
        $this->assertNotEmpty($product->internal_barcode);
        $this->assertSame('Categoria CSV', $product->category->name);
    }

    public function test_real_csv_handler_updates_an_existing_sku(): void
    {
        $product = Product::create(['name' => 'Original', 'sku' => 'CSV-UPDATE', 'price' => 10, 'stock' => 3]);
        $csv = "name;sku;price;stock;unit\nAtualizado;CSV-UPDATE;25,50;7;UN\n";

        $this->actingAs($this->userWithRole('admin'))->post(route('products.import'), [
            'mode' => 'upsert',
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ])->assertRedirect(route('products.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('ok', 'Importação concluída: 0 criados, 1 atualizados.');

        $this->assertSame(1, Product::count());
        $this->assertSame('Atualizado', $product->fresh()->name);
        $this->assertSame(25.5, $product->fresh()->price);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_real_csv_handler_reports_duplicate_insert_and_invalid_rows(): void
    {
        $product = Product::create(['name' => 'Original', 'sku' => 'CSV-DUPLICATE', 'price' => 10]);
        $csv = "name;sku;price;stock;min_stock;unit\nDuplicado;CSV-DUPLICATE;20;2;1;UN\n;INVALID;10;1;1;UN\n";

        $this->actingAs($this->userWithRole('admin'))->from(route('products.import.form'))
            ->post(route('products.import'), [
                'mode' => 'insert',
                'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            ])->assertRedirect(route('products.import.form'))->assertSessionHasErrors()
            ->assertSessionHas('ok', 'Importação concluída: 0 criados, 0 atualizados.');

        $this->assertSame(1, Product::count());
        $this->assertSame('Original', $product->fresh()->name);
    }

    public function test_csv_handler_requires_file_and_valid_mode(): void
    {
        $this->actingAs($this->userWithRole('admin'))->post(route('products.import'), ['mode' => 'invalid'])
            ->assertSessionHasErrors(['file', 'mode']);
        $this->assertSame(0, Product::count());
    }

    public static function cashProfiles(): array
    {
        return [['admin'], ['gerente'], ['vendedor']];
    }

    private function financialState(): array
    {
        $state = [];
        foreach (['cash_registers', 'cash_movements', 'orders', 'order_items', 'order_payments',
            'products', 'stock_movements', 'product_price_histories'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }

    #[DataProvider('cashProfiles')]
    public function test_preview_csv_and_print_share_totals_without_writes_or_double_counting(string $role): void
    {
        $user = $this->userWithRole($role);
        $old = CashRegister::create(['status' => 'closed', 'opening_amount' => 9000]);
        CashMovement::create(['cash_register_id' => $old->id, 'type' => 'IN', 'amount' => 5000]);
        $reg = CashRegister::create(['status' => 'open', 'opening_amount' => 100, 'user_opened_id' => $user->id]);
        $order = Order::create(['number' => 'TEST-SPLIT', 'status' => 'paid', 'total' => 150, 'payment_method' => 'mixed']);
        OrderPayment::create(['order_id' => $order->id, 'method' => 'dinheiro', 'amount' => 100]);
        OrderPayment::create(['order_id' => $order->id, 'method' => 'pix', 'amount' => 50]);
        foreach ([['dinheiro', 100], ['pix', 50]] as [$method, $amount]) {
            CashMovement::create(['cash_register_id' => $reg->id, 'order_id' => $order->id,
                'type' => 'IN', 'amount' => $amount, 'payment_method' => $method, 'reason' => 'Venda PDV']);
        }
        CashMovement::create(['cash_register_id' => $reg->id, 'type' => 'IN', 'amount' => 25.50, 'payment_method' => 'cartao']);
        CashMovement::create(['cash_register_id' => $reg->id, 'type' => 'IN', 'amount' => 20.10, 'reason' => 'Suprimento']);
        CashMovement::create(['cash_register_id' => $reg->id, 'type' => 'OUT', 'amount' => 5, 'reason' => 'Ajuste']);
        CashMovement::create(['cash_register_id' => $reg->id, 'type' => 'OUT', 'amount' => 30,
            'payment_method' => 'dinheiro', 'reason' => 'Sangria automática']);
        $before = $this->financialState();

        $preview = $this->actingAs($user)->get(route('cash.close.preview'))->assertOk();
        $print = $this->get(route('cash.close.print'))->assertOk()->assertViewIs('cash.close_print');
        $csv = $this->get(route('cash.close.export'))->assertOk()->assertDownload('fechamento_caixa_'.$reg->id.'.csv');

        foreach ([$preview, $print] as $response) {
            $this->assertSame($reg->id, $response->viewData('reg')->id);
            $this->assertEqualsWithDelta(260.60, $response->viewData('prevClosing'), 0.00001);
            $this->assertEqualsWithDelta(30, $response->viewData('sangrias'), 0.00001);
            $this->assertEqualsWithDelta(195.60, $response->viewData('byMethod')->sum('entradas'), 0.00001);
            $this->assertEqualsWithDelta(35, $response->viewData('byMethod')->sum('saidas'), 0.00001);
            $cash = $response->viewData('byMethod')->firstWhere('payment_method', 'dinheiro');
            $this->assertEqualsWithDelta(70, $cash->saldo, 0.00001);
            $response->assertSee('R$ 260,60');
        }
        $preview->assertSee(route('cash.close.print'), false);
        $print->assertSee($user->name)->assertDontSee('Dinheiro esperado');
        $this->assertEquals($preview->viewData('byMethod')->toArray(), $print->viewData('byMethod')->toArray());
        $content = $csv->streamedContent();
        $this->assertStringContainsString('"Previsão de fechamento";260,60', $content);
        $this->assertStringContainsString('Sangrias;30,00', $content);
        $this->assertStringContainsString('Dinheiro;100,00;30,00;70,00', $content);
        $this->assertStringContainsString('Pix;50,00;0,00;50,00', $content);
        $this->assertStringContainsString('Cartao;25,50;0,00;25,50', $content);
        $this->assertSame($before, $this->financialState());
    }

    public function test_empty_register_and_missing_responsibles_can_be_printed(): void
    {
        $reg = CashRegister::create(['status' => 'open', 'opening_amount' => 75]);
        $this->actingAs($this->userWithRole('admin'))->get(route('cash.close.print'))
            ->assertOk()->assertSee('Sem movimentações.')->assertSee('R$ 75,00')
            ->assertViewHas('reg', fn ($value) => $value->id === $reg->id && $value->openedBy === null && $value->closedBy === null);
        $this->get(route('cash.close.preview'))->assertOk()->assertViewHas('prevClosing', 75.0);
        $csv = $this->get(route('cash.close.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Previsão de fechamento";75,00', $csv);
    }

    public function test_register_responsible_relations_use_existing_foreign_keys(): void
    {
        $opener = $this->userWithRole('vendedor');
        $closer = $this->userWithRole('gerente');
        $reg = CashRegister::create(['status' => 'closed', 'user_opened_id' => $opener->id, 'user_closed_id' => $closer->id]);
        $this->assertTrue($reg->openedBy->is($opener));
        $this->assertTrue($reg->closedBy->is($closer));
    }

    public function test_preview_print_and_csv_return_404_without_an_open_register(): void
    {
        CashRegister::create(['status' => 'closed', 'opening_amount' => 100]);
        $this->actingAs($this->userWithRole('admin'));
        $before = $this->financialState();
        foreach (['cash.close.preview', 'cash.close.print', 'cash.close.export'] as $route) {
            $this->get(route($route))->assertNotFound();
        }
        $this->assertSame($before, $this->financialState());
    }

    public static function blockedProfiles(): array
    {
        return [['vendedor'], ['estoque'], [null], ['inactive'], ['guest']];
    }

    #[DataProvider('blockedProfiles')]
    public function test_real_handlers_keep_their_authorization(?string $profile): void
    {
        $user = User::factory()->create(['role' => 'admin', 'active' => $profile !== 'inactive']);
        if (in_array($profile, ['vendedor', 'estoque', 'inactive'], true)) {
            $user->assignRole($profile === 'inactive' ? 'admin' : $profile);
        }
        if ($profile !== 'guest') {
            $this->actingAs($user);
        }
        $status = $profile === 'guest' ? 302 : 403;
        $before = $this->financialState();
        $this->post(route('products.import'), ['mode' => 'insert'])->assertStatus($status);
        if ($profile !== 'guest') {
            $this->actingAs($user);
        }
        if ($profile !== 'vendedor') {
            $this->get(route('cash.close.print'))->assertStatus($status);
        }
        $this->assertSame($before, $this->financialState());
    }

    public function test_employee_delete_returns_405_and_preserves_user_and_history(): void
    {
        $admin = $this->userWithRole('admin');
        $employee = $this->userWithRole('vendedor');
        $order = Order::create(['number' => 'EMPLOYEE-HISTORY', 'user_id' => $employee->id, 'status' => 'paid', 'total' => 10]);
        $reg = CashRegister::create(['status' => 'open', 'user_opened_id' => $employee->id]);
        CashMovement::create(['cash_register_id' => $reg->id, 'order_id' => $order->id,
            'user_id' => $employee->id, 'type' => 'IN', 'amount' => 10]);
        $before = $this->financialState();
        $userBefore = $employee->fresh()->getAttributes();

        $this->assertNull(app('router')->getRoutes()->getByName('employees.destroy'));
        $this->actingAs($admin)->delete('/employees/'.$employee->id)->assertStatus(405);
        $this->assertSame($userBefore, $employee->fresh()->getAttributes());
        $this->assertSame($before, $this->financialState());
        $this->assertTrue($employee->fresh()->hasRole('vendedor'));

        $this->patch(route('employees.deactivate', $employee))->assertRedirect();
        $this->assertFalse((bool) $employee->fresh()->active);
        $this->assertSame($before, $this->financialState());
        $this->assertSame($employee->id, $order->fresh()->user_id);
        $this->patch(route('employees.activate', $employee))->assertRedirect();
        $this->assertTrue((bool) $employee->fresh()->active);
        $this->assertTrue($employee->fresh()->hasRole('vendedor'));
        $this->assertSame($before, $this->financialState());
    }

    public function test_all_registered_controller_methods_exist(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $action = $route->getActionName();
            if (str_contains($action, '@')) {
                [$class, $method] = explode('@', $action);
                $this->assertTrue(method_exists($class, $method), $route->uri().' '.$action);
            }
        }
    }
}
