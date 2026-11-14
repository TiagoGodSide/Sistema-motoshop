<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CashOpeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config([
            'database.connections.mysql' => null,
            'cache.default' => 'array',
            'session.driver' => 'array',
            'logging.default' => 'null',
        ]);

        Artisan::call('migrate', ['--force' => true]);
        $this->seed(RolesAndAdminSeeder::class);
    }

    private function actor(string $role = 'admin'): User
    {
        $user = User::factory()->create(['role' => 'seller', 'active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function payload(): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'opening_amount' => '100.50',
            'open_notes' => 'Fundo de teste',
        ];
    }

    private function financialState(): array
    {
        $state = [];
        foreach (['cash_registers', 'cash_movements', 'orders', 'order_items', 'order_payments',
            'products', 'stock_movements', 'product_price_histories', 'financial_operations'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }

    public static function openingRoles(): array
    {
        return [['admin'], ['gerente'], ['vendedor']];
    }

    #[DataProvider('openingRoles')]
    public function test_one_request_creates_one_register_and_one_persisted_operation(string $role): void
    {
        $user = $this->actor($role);
        $payload = $this->payload();
        $this->actingAs($user)->post(route('cash.open'), $payload)
            ->assertRedirect(route('cash.index'))->assertSessionHasNoErrors();

        $register = CashRegister::sole();
        $operation = DB::table('financial_operations')->sole();
        $this->assertSame('open', $register->status);
        $this->assertEquals(100.50, $register->opening_amount);
        $this->assertSame($user->id, $register->user_opened_id);
        $this->assertSame('Fundo de teste', $register->open_notes);
        $this->assertNotNull($register->opened_at);
        $this->assertNotNull($register->created_at);
        $this->assertNotNull($register->updated_at);
        $this->assertSame('cash.open', $operation->operation);
        $this->assertSame($user->id, $operation->user_id);
        $this->assertSame($register->id, $operation->cash_register_id);
        $this->assertSame($payload['idempotency_key'], $operation->idempotency_key);
        $this->assertSame(64, strlen($operation->request_hash));
        $this->assertSame(1, DB::table('financial_coordination')->where('id', 1)->count());
        $this->assertDatabaseCount('cash_movements', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_form_preserves_key_through_reload_validation_and_success(): void
    {
        $this->actingAs($this->actor());
        $first = $this->get('/cash')->assertOk();
        $key = $first->viewData('openingKey');
        $this->assertTrue(Str::isUuid($key));
        $first->assertSee('name="idempotency_key" value="'.$key.'"', false);
        $this->get('/cash')->assertOk()->assertViewHas('openingKey', $key);
        $payload = ['idempotency_key' => $key, 'opening_amount' => '-1'];
        $this->from('/cash')->post(route('cash.open'), $payload)->assertSessionHasErrors('opening_amount');
        $this->get('/cash')->assertOk()->assertSee('name="idempotency_key" value="'.$key.'"', false);

        $payload['opening_amount'] = '0';
        $this->post(route('cash.open'), $payload)->assertRedirect(route('cash.index'));
        $this->post(route('cash.open'), $payload)->assertRedirect(route('cash.index'));
        $this->assertDatabaseCount('cash_registers', 1);
        $this->assertDatabaseCount('financial_operations', 1);

        // Alteração apenas da fixture em memória, para simular uma fase posterior.
        CashRegister::query()->update(['status' => 'closed']);
        $this->get('/cash')->assertOk()->assertViewHas('openingKey', fn ($value) => Str::isUuid($value) && $value !== $key);
    }

    public function test_opening_timestamps_use_the_database_clock(): void
    {
        $this->actingAs($this->actor());
        $before = DB::selectOne('SELECT CURRENT_TIMESTAMP AS clock')->clock;
        Carbon::setTestNow(Carbon::parse('2001-01-01 00:00:00', 'UTC'));
        try {
            $this->post(route('cash.open'), $this->payload())->assertRedirect();
        } finally {
            Carbon::setTestNow();
        }
        $after = DB::selectOne('SELECT CURRENT_TIMESTAMP AS clock')->clock;
        $register = DB::table('cash_registers')->sole();
        $this->assertGreaterThanOrEqual($before, $register->opened_at);
        $this->assertLessThanOrEqual($after, $register->opened_at);
        $this->assertSame($register->opened_at, $register->created_at);
        $this->assertSame($register->opened_at, $register->updated_at);
    }

    public function test_replay_normalizes_amount_notes_and_key_case_without_writes(): void
    {
        $this->actingAs($this->actor());
        $payload = $this->payload();
        $payload['opening_amount'] = '001.20';
        $payload['open_notes'] = '  Observação  ';
        $this->post(route('cash.open'), $payload)->assertRedirect();
        $before = $this->financialState();
        $payload['opening_amount'] = '1.2';
        $payload['open_notes'] = 'Observação';
        $payload['idempotency_key'] = strtoupper($payload['idempotency_key']);
        $this->post(route('cash.open'), $payload)->assertRedirect()->assertSessionHas('ok', 'Abertura do caixa #1 já registrada.');
        $this->assertSame($before, $this->financialState());
    }

    public function test_changed_content_identity_or_operation_conflicts(): void
    {
        $this->actingAs($this->actor());
        $payload = $this->payload();
        $this->post(route('cash.open'), $payload)->assertRedirect();
        $before = $this->financialState();
        $this->post(route('cash.open'), array_replace($payload, ['opening_amount' => '101.00']))->assertConflict();
        $this->post(route('cash.open'), array_replace($payload, ['open_notes' => 'Outro conteúdo']))->assertConflict();
        $this->actingAs($this->actor())->post(route('cash.open'), $payload)->assertConflict();
        $this->assertSame($before, $this->financialState());

        $this->actingAs(User::findOrFail(DB::table('financial_operations')->value('user_id')));
        $before = $this->financialState();
        // Exercise cross-operation reuse through HTTP. The new composite FK also
        // rejects direct mutation of a linked receipt (covered by structure tests).
        $this->post(route('cash.close'), [
            'idempotency_key' => $payload['idempotency_key'],
            'cash_register_id' => DB::table('financial_operations')->value('cash_register_id'),
        ])->assertConflict();
        $this->assertSame($before, $this->financialState());
    }

    public function test_replay_recovers_original_id_after_later_state_changes(): void
    {
        $this->actingAs($this->actor());
        $payload = $this->payload();
        $this->post(route('cash.open'), $payload)->assertRedirect();
        $original = CashRegister::sole();
        $original->update(['status' => 'closed', 'closing_amount' => 100.50]);

        $this->post(route('cash.open'), $payload)->assertRedirect()
            ->assertSessionHas('ok', 'Abertura do caixa #'.$original->id.' já registrada.');
        $this->assertSame('closed', $original->fresh()->status);
        $this->assertDatabaseCount('cash_registers', 1);

        $this->post(route('cash.open'), $this->payload())->assertRedirect();
        CashRegister::create(['status' => 'open', 'opening_amount' => 999]);
        $before = $this->financialState();
        $this->post(route('cash.open'), $payload)->assertRedirect()
            ->assertSessionHas('ok', 'Abertura do caixa #'.$original->id.' já registrada.');
        $this->assertSame($before, $this->financialState());
    }

    public static function activeRegisterCounts(): array
    {
        return [[1], [2]];
    }

    #[DataProvider('activeRegisterCounts')]
    public function test_new_opening_conflicts_and_preserves_existing_history(int $count): void
    {
        $this->actingAs($this->actor());
        foreach (range(1, $count) as $number) {
            $register = CashRegister::create(['status' => 'open', 'opening_amount' => $number * 50]);
            CashMovement::create(['cash_register_id' => $register->id, 'type' => 'IN', 'amount' => 10]);
        }
        $before = $this->financialState();
        $this->post(route('cash.open'), $this->payload())->assertConflict();
        $this->assertSame($before, $this->financialState());
    }

    public function test_failure_between_register_and_operation_rolls_everything_back(): void
    {
        $failOnce = true;
        DB::connection()->beforeExecuting(function ($query) use (&$failOnce) {
            if ($failOnce && str_starts_with($query, 'insert into "financial_operations"')) {
                $failOnce = false;
                throw new PDOException('Injected operation failure');
            }
        });
        $this->actingAs($this->actor());
        $payload = $this->payload();
        $before = $this->financialState();
        $this->post(route('cash.open'), $payload)->assertStatus(503)->assertHeader('Retry-After', '1');
        $this->assertSame($before, $this->financialState());
        $this->post(route('cash.open'), $payload)->assertRedirect();
        $this->assertDatabaseCount('cash_registers', 1);
        $this->assertDatabaseCount('financial_operations', 1);
    }

    public static function missingTables(): array
    {
        return [['financial_coordination'], ['financial_operations']];
    }

    #[DataProvider('missingTables')]
    public function test_missing_table_stops_opening_before_financial_writes(string $table): void
    {
        $this->actingAs($this->actor());
        Schema::rename($table, $table.'_unavailable');
        $this->post(route('cash.open'), $this->payload())->assertStatus(503);
        $this->assertDatabaseCount('cash_registers', 0);
        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_missing_coordination_row_is_not_recreated_by_requests(): void
    {
        DB::table('financial_coordination')->where('id', 1)->update(['id' => 2]);
        $this->actingAs($this->actor())->post(route('cash.open'), $this->payload())->assertStatus(503);
        $this->assertDatabaseCount('cash_registers', 0);
        $this->assertDatabaseCount('financial_operations', 0);
        $this->assertSame([2], DB::table('financial_coordination')->pluck('id')->all());
    }

    public static function concurrencyErrors(): array
    {
        return [
            ['Deadlock found when trying to get lock', 40001],
            ['Lock wait timeout exceeded; try restarting transaction', 1205],
        ];
    }

    #[DataProvider('concurrencyErrors')]
    public function test_exhausted_concurrency_errors_return_controlled_failure(string $message, int $code): void
    {
        $attempts = 0;
        DB::connection()->beforeExecuting(function ($query) use (&$attempts, $message, $code) {
            if (str_starts_with($query, 'insert into "financial_operations"')) {
                $attempts++;
                throw new PDOException($message, $code);
            }
        });
        $this->actingAs($this->actor());
        $before = $this->financialState();
        $this->post(route('cash.open'), $this->payload())->assertStatus(503);
        $this->assertSame(3, $attempts);
        $this->assertSame($before, $this->financialState());
    }

    public function test_transient_deadlock_retries_with_the_original_key(): void
    {
        $attempts = 0;
        DB::connection()->beforeExecuting(function ($query) use (&$attempts) {
            if (str_starts_with($query, 'insert into "financial_operations"') && ++$attempts === 1) {
                throw new PDOException('Deadlock found when trying to get lock', 40001);
            }
        });
        $payload = $this->payload();
        $this->actingAs($this->actor())->post(route('cash.open'), $payload)->assertRedirect();
        $this->assertSame(2, $attempts);
        $this->assertDatabaseCount('cash_registers', 1);
        $this->assertDatabaseCount('financial_operations', 1);
        $this->assertSame($payload['idempotency_key'], DB::table('financial_operations')->value('idempotency_key'));
    }

    public function test_database_enforces_unique_keys(): void
    {
        $this->actingAs($this->actor())->post(route('cash.open'), $this->payload())->assertRedirect();
        $operation = (array) DB::table('financial_operations')->sole();
        unset($operation['id']);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('financial_operations')->insert($operation);
    }

    public function test_invalid_key_and_monetary_values_do_not_write(): void
    {
        $this->actingAs($this->actor());
        $this->postJson(route('cash.open'), ['opening_amount' => '0'])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        foreach (['-1', '1.234', '100000000', '1e2'] as $amount) {
            $this->postJson(route('cash.open'), array_replace($this->payload(), ['opening_amount' => $amount]))
                ->assertUnprocessable()->assertJsonValidationErrors('opening_amount');
        }
        $this->assertDatabaseCount('cash_registers', 0);
        $this->assertDatabaseCount('financial_operations', 0);
    }
}
