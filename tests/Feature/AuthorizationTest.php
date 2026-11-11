<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Verificar o destino antes de abrir conexão ou executar migrations.
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['database.connections.mysql' => null]);

        Artisan::call('migrate', ['--force' => true]);
        $this->seed(RolesAndAdminSeeder::class);
    }

    /** Rotas e papéis da matriz aprovada, independentes dos middlewares implementados. */
    private function operations(): array
    {
        return [
            [['products.index', 'products.search'], ['admin', 'gerente', 'vendedor', 'estoque']],
            [['products.create', 'products.store', 'products.edit', 'products.update',
                'products.toggle', 'products.import.form', 'products.import', 'products.template.csv',
                'products.export.csv', 'products.low.csv', 'products.history', 'products.label',
                'products.labels.batch'], ['admin', 'gerente']],
            [['products.adjust', 'categories.index', 'categories.store', 'categories.update',
                'categories.destroy', 'categories.toggle'], ['admin', 'gerente', 'estoque']],
            [['pos.index', 'pos.find', 'pos.checkout', 'customers.find', 'customers.quick',
                'orders.index', 'orders.show', 'orders.receipt', 'orders.receipt.simple', 'orders.finalize',
                'cash.index', 'cash.close.preview', 'cash.close.export', 'cash.open', 'cash.close',
                'cash.movement', 'cash.close.print', 'os.index', 'os.create', 'os.store', 'os.edit',
                'os.update', 'os.items.add', 'os.items.remove', 'os.to.pos'], ['admin', 'gerente', 'vendedor']],
            [['orders.cancel', 'reports.sales.summary', 'reports.sales.summary.csv',
                'reports.sales.products', 'reports.sales.products.csv', 'reports.sales.categories',
                'reports.sales.categories.csv', 'reports.stock.history', 'reports.stock.history.csv',
                'reports.customers', 'reports.customers.csv'], ['admin', 'gerente']],
            [['employees.index', 'employees.store', 'employees.update', 'employees.destroy',
                'employees.deactivate', 'employees.activate'], ['admin']],
        ];
    }

    public static function profiles(): array
    {
        return [
            'admin' => ['admin'],
            'gerente' => ['gerente'],
            'vendedor' => ['vendedor'],
            'estoque' => ['estoque'],
            'sem papel' => [null],
            'visitante' => ['guest'],
            'inativo' => ['inactive'],
        ];
    }

    #[DataProvider('profiles')]
    public function test_operation_routes_enforce_the_approved_matrix(?string $profile): void
    {
        $user = User::factory()->create(['role' => 'admin', 'active' => $profile !== 'inactive']);
        if (in_array($profile, ['admin', 'gerente', 'vendedor', 'estoque', 'inactive'], true)) {
            $user->assignRole($profile === 'inactive' ? 'admin' : $profile);
        }

        $covered = [];
        foreach ($this->operations() as [$names, $roles]) {
            foreach ($names as $name) {
                $covered[] = $name;
                $route = app('router')->getRoutes()->getByName($name);
                $this->assertNotNull($route, $name);

                // Exercitar a pilha HTTP real, encerrando antes dos controllers de negócio.
                // Isso também valida a proteção de rotas cujos handlers são pendências conhecidas.
                $action = $route->getAction();
                unset($action['controller']);
                $action['uses'] = fn () => response('', 204);
                $route->setAction($action);

                $url = route($name, array_fill_keys($route->parameterNames(), 1));
                foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                    if ($profile !== 'guest') {
                        $this->actingAs($user);
                    }
                    $response = $this->call($method, $url);
                    $expected = $profile === 'guest' ? 302 : (in_array($profile, $roles, true) ? 204 : 403);
                    $this->assertSame($expected, $response->status(), "$profile $method $name");
                    if ($profile === 'guest') {
                        $response->assertRedirect(route('login'));
                    }
                }
            }
        }

        $operational = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('role:admin|gerente|vendedor|estoque', $route->middleware(), true))
            ->map->getName()->reject(fn ($name) => $name === 'home')->values()->all();
        $this->assertEqualsCanonicalizing($operational, $covered);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['role' => 'seller', 'active' => true]);
        $user->assignRole($role);

        return $user;
    }

    #[DataProvider('productProfiles')]
    public function test_product_page_renders_only_authorized_actions(string $role, bool $manage, bool $adjust): void
    {
        $product = Product::create(['name' => 'Produto de teste', 'sku' => 'TEST-1', 'price' => 100, 'cost_price' => 43.21]);
        $response = $this->actingAs($this->userWithRole($role))->get('/products');
        $response->assertOk()->assertSee('Produto de teste')->assertDontSee('43,21');

        foreach (['products.edit', 'products.history', 'products.label', 'products.toggle'] as $name) {
            if ($manage) {
                $response->assertSee(route($name, $product), false);
            } else {
                $response->assertDontSee(route($name, $product), false);
            }
        }
        if ($adjust) {
            $response->assertSee(route('products.adjust', $product), false)->assertSee('Salvar ajuste');
        } else {
            $response->assertDontSee(route('products.adjust', $product), false);
        }
        if (! $manage) {
            $response->assertDontSee(route('products.export.csv'), false)
                ->assertDontSee(route('employees.index'), false)
                ->assertDontSee(route('reports.customers'), false);
        }

        foreach ([
            'pos.index' => $role !== 'estoque',
            'orders.index' => $role !== 'estoque',
            'categories.index' => $role !== 'vendedor',
            'employees.index' => $role === 'admin',
            'reports.customers' => $manage,
            'cash.index' => $role !== 'estoque',
        ] as $name => $visible) {
            if ($visible) {
                $response->assertSee('href="'.route($name).'"', false);
            } else {
                $response->assertDontSee('href="'.route($name).'"', false);
            }
        }

        if ($manage) {
            $this->get(route('products.edit', $product))->assertOk()
                ->assertSee(route('products.adjust', $product), false);
        } else {
            $this->get(route('products.edit', $product))->assertForbidden();
        }
    }

    public static function productProfiles(): array
    {
        return [
            ['admin', true, true], ['gerente', true, true],
            ['vendedor', false, false], ['estoque', false, true],
        ];
    }

    public function test_legacy_admin_and_direct_permissions_do_not_grant_a_role(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'active' => true]);
        $user->givePermissionTo('products.view');
        $this->actingAs($user)->get('/products')->assertForbidden();
        $this->assertCount(0, $user->fresh()->roles);
    }

    public function test_seeded_admin_master_can_open_products_with_legacy_seller(): void
    {
        $admin = User::where('name', 'Admin Master')->sole();
        $this->assertSame('seller', $admin->role);
        $this->actingAs($admin)->get('/products')->assertOk();
    }

    public function test_public_registration_does_not_grant_operational_access(): void
    {
        $this->post('/register', [
            'name' => 'Conta de teste',
            'email' => 'registration@example.test',
            'password' => 'Test-password-123',
            'password_confirmation' => 'Test-password-123',
        ])->assertRedirect();

        $this->assertAuthenticated();
        $this->assertCount(0, Auth::user()->roles);
        Auth::forgetGuards(); // Carregar os valores padrão do banco na próxima requisição.
        $this->get('/products')->assertForbidden();
        $this->get('/pos')->assertForbidden();
        $this->post('/pos/checkout')->assertForbidden();
    }

    public function test_role_change_takes_effect_without_updating_legacy_column(): void
    {
        $user = $this->userWithRole('admin');
        $this->actingAs($user)->get('/orders')->assertOk();
        $user->syncRoles(['estoque']);
        $this->get('/orders')->assertForbidden();
        $this->get('/products')->assertOk();
        $this->assertSame('seller', $user->fresh()->role);
    }

    public function test_inactivation_revokes_an_existing_session(): void
    {
        $user = $this->userWithRole('admin');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->get('/products')->assertOk();
        User::whereKey($user->id)->update(['active' => false]);
        Auth::forgetGuards(); // Uma nova requisição carrega novamente o usuário da sessão.
        $this->get('/products')->assertForbidden();
        $this->assertGuest();
        $this->get('/products')->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_order_buttons_match_cancel_and_finalize_permissions(): void
    {
        $order = Order::create(['number' => 'TEST-ORDER', 'status' => 'paid', 'payment_method' => 'dinheiro']);
        $this->actingAs($this->userWithRole('vendedor'))->get(route('orders.show', $order))
            ->assertOk()->assertDontSee(route('orders.cancel', $order), false);
        $this->get('/orders')->assertOk()->assertDontSee(route('orders.cancel', $order), false);
        $order->update(['status' => 'draft']);
        $this->get(route('orders.show', $order))->assertOk()->assertSee(route('orders.finalize', $order), false);
        $order->update(['status' => 'paid']);
        $this->actingAs($this->userWithRole('admin'))->get(route('orders.show', $order))
            ->assertOk()->assertSee(route('orders.cancel', $order), false);
    }
}
