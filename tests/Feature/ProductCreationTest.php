<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductCreationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['database.connections.mysql' => null]);

        Artisan::call('migrate', ['--force' => true]);
        $this->seed(RolesAndAdminSeeder::class);
    }

    public static function authorizedProfiles(): array
    {
        return [['admin'], ['gerente']];
    }

    #[DataProvider('authorizedProfiles')]
    public function test_authorized_user_can_open_the_translated_creation_form(string $role): void
    {
        $user = User::factory()->create(['role' => 'seller', 'active' => true]);
        $user->assignRole($role);
        Category::create(['name' => 'Categoria de teste']);

        $this->actingAs($user)->get(route('products.create'))
            ->assertOk()
            ->assertViewIs('products.create')
            ->assertSee('Novo produto')
            ->assertSee('Categoria de teste')
            ->assertSee('Salvar')
            ->assertSee('type="submit"', false)
            ->assertSee('action="'.route('products.store').'"', false)
            ->assertDontSee('ui.save')
            ->assertDontSee('return [', false);

        $this->assertSame(0, Product::count());
        $this->assertSame('seller', $user->fresh()->role);
    }

    public function test_framework_loads_ui_and_error_translations(): void
    {
        $this->assertSame('Salvar', __('ui.save'));
        $this->assertSame('Produtos', __('ui.products'));
        $this->assertSame('Você não tem permissão para acessar esta página.', __('messages.forbidden'));
        $this->assertSame('Página não encontrada.', __('messages.not_found'));
        $this->assertSame('Erro interno. Tente novamente mais tarde.', __('messages.server_error'));
    }
}
