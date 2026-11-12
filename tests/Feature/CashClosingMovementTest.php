<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\User;
use App\Services\FinancialCoordinator;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CashClosingMovementTest extends TestCase
{
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['database.connections.mysql' => null, 'cache.default' => 'array',
            'session.driver' => 'array', 'logging.default' => 'null']);
        Artisan::call('migrate', ['--force' => true]);
        $this->seed(RolesAndAdminSeeder::class);
        $this->actor = User::factory()->create(['role' => 'seller', 'active' => true]);
        $this->actor->assignRole('admin');
        $this->actingAs($this->actor);
    }

    private function register(string $amount = '100.00'): CashRegister
    {
        return CashRegister::create(['status' => 'open', 'opening_amount' => $amount, 'user_opened_id' => $this->actor->id]);
    }

    private function payload(CashRegister $register, string $action): array
    {
        $payload = ['idempotency_key' => (string) Str::uuid(), 'cash_register_id' => $register->id, 'cash_action' => $action];
        if ($action === 'movement') {
            return $payload + ['type' => 'IN', 'amount' => '10.50', 'reason' => 'Suprimento', 'payment_method' => 'dinheiro'];
        }

        return $payload + ['close_notes' => 'Conferido'];
    }

    private function snapshot(): array
    {
        $state = [];
        foreach (['cash_registers', 'cash_movements', 'financial_operations', 'orders', 'order_payments',
            'order_items', 'products', 'stock_movements', 'product_price_histories'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }

    public function test_close_updates_exactly_one_register_with_the_server_balance(): void
    {
        $old = $this->register('999.00');
        $old->update(['status' => 'closed', 'closing_amount' => '999.00']);
        $oldState = $old->fresh()->getAttributes();
        $register = $this->register();
        foreach ([['IN', '150.00', 'Venda', 'dinheiro'], ['IN', '25.50', 'Venda', 'cartao'],
            ['IN', '20.10', 'Suprimento', null], ['OUT', '30.00', 'Sangria', 'dinheiro'],
            ['OUT', '5.00', 'Ajuste', null]] as [$type, $amount, $reason, $method]) {
            $this->post(route('cash.movement'), array_replace($this->payload($register, 'movement'), [
                'type' => $type, 'amount' => $amount, 'reason' => $reason, 'payment_method' => $method,
            ]))->assertRedirect()->assertSessionHasNoErrors();
        }
        $preview = $this->get(route('cash.close.preview'))->assertOk()->assertViewHas('prevClosing', 260.60);
        $this->get(route('cash.close.print'))->assertOk()->assertSee('R$ 260,60');
        $csv = $this->get(route('cash.close.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Previsão de fechamento";260,60', $csv);
        $this->assertEquals(30, $preview->viewData('sangrias'));
        $movementState = DB::table('cash_movements')->get()->toJson();
        $updates = [];
        DB::listen(function ($query) use (&$updates) {
            if (str_starts_with($query->sql, 'update "cash_registers"')) {
                $updates[] = $query;
            }
        });
        $payload = $this->payload($register, 'close') + ['closing_amount' => '0.00'];
        $this->post(route('cash.close'), $payload)->assertRedirect(route('cash.index'))->assertSessionHasNoErrors();
        $closed = $register->fresh();
        $this->assertSame('closed', $closed->status);
        $this->assertEquals(260.60, $closed->closing_amount);
        $this->assertSame('Conferido', $closed->close_notes);
        $this->assertSame($this->actor->id, $closed->user_closed_id);
        $this->assertNotNull($closed->closed_at);
        $this->assertCount(1, $updates);
        $this->assertMatchesRegularExpression('/where (?:"cash_registers"\.)?"id" = \?/', $updates[0]->sql);
        $this->assertSame($register->id, $updates[0]->bindings[array_key_last($updates[0]->bindings)]);
        $this->assertSame($oldState, $old->fresh()->getAttributes());
        $this->assertSame($movementState, DB::table('cash_movements')->get()->toJson());
        $receipt = DB::table('financial_operations')->where('operation', 'cash.close')->sole();
        $this->assertSame('260.60', json_decode($receipt->result, true)['closing_amount']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_payments', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public static function balances(): array
    {
        return [
            ['0.10', [['IN', '0.20'], ['IN', '0.30'], ['OUT', '0.10']], '0.50'],
            ['0.00', [['OUT', '0.30']], '-0.30'],
            ['0.00', [], '0.00'],
        ];
    }

    #[DataProvider('balances')]
    public function test_close_preserves_decimal_precision_and_negative_balances(string $opening, array $movements, string $expected): void
    {
        $register = $this->register($opening);
        $service = app(FinancialCoordinator::class);
        foreach ($movements as [$type, $amount]) {
            $service->movement($this->actor, array_replace($this->payload($register, 'movement'), ['type' => $type, 'amount' => $amount]));
        }
        $result = $service->close($this->actor, $this->payload($register, 'close'));
        $this->assertSame($expected, $result['closing_amount']);
    }

    public function test_closed_result_is_immutable_and_replay_never_touches_a_new_register(): void
    {
        $register = $this->register();
        $payload = $this->payload($register, 'close');
        $service = app(FinancialCoordinator::class);
        $first = $service->close($this->actor, $payload);
        // Simular alterações posteriores somente nas fixtures SQLite.
        $register->update(['closing_amount' => '777.00', 'close_notes' => 'Alterado na fixture']);
        $current = $this->register('200.00');
        $before = $this->snapshot();
        $replay = $service->close($this->actor, $payload);
        $this->assertTrue($replay['replayed']);
        $this->assertSame($first['closing_amount'], $replay['closing_amount']);
        $this->assertSame('100.00', $replay['closing_amount']);
        $this->post(route('cash.close'), $payload)->assertRedirect();
        $this->post(route('cash.close'), $this->payload($register, 'close'))->assertConflict();
        $this->assertSame('open', $current->fresh()->status);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_movement_replay_after_close_recovers_the_original_record(): void
    {
        $register = $this->register();
        $payload = $this->payload($register, 'movement');
        $payload['amount'] = '0010.50';
        $service = app(FinancialCoordinator::class);
        $first = $service->movement($this->actor, $payload);
        $service->close($this->actor, $this->payload($register, 'close'));
        $this->register();
        $before = $this->snapshot();
        $payload['amount'] = '10.5';
        $payload['reason'] = '  Suprimento  ';
        $replay = $service->movement($this->actor, $payload);
        $this->assertTrue($replay['replayed']);
        $this->assertSame($first['cash_movement_id'], $replay['cash_movement_id']);
        $this->post(route('cash.movement'), $this->payload($register, 'movement'))->assertConflict();
        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseCount('cash_movements', 1);
    }

    public static function actions(): array
    {
        return [['close'], ['movement']];
    }

    #[DataProvider('actions')]
    public function test_missing_identity_and_invalid_values_are_rejected(string $action): void
    {
        $register = $this->register();
        $before = $this->snapshot();
        $this->postJson(route('cash.'.$action), [])->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key', 'cash_register_id']);
        $payload = $this->payload($register, $action);
        unset($payload['idempotency_key']);
        $this->postJson(route('cash.'.$action), $payload)->assertUnprocessable();
        if ($action === 'movement') {
            foreach (['0', '-1', '1.234', '100000000'] as $amount) {
                $this->postJson(route('cash.movement'), array_replace($this->payload($register, 'movement'), ['amount' => $amount]))
                    ->assertUnprocessable()->assertJsonValidationErrors('amount');
            }
        }
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('actions')]
    public function test_closed_wrong_or_ambiguous_registers_conflict_without_changes(string $action): void
    {
        $closed = $this->register();
        $closed->update(['status' => 'closed']);
        $before = $this->snapshot();
        $this->post(route('cash.'.$action), $this->payload($closed, $action))->assertConflict();
        $this->assertSame($before, $this->snapshot());
        $active = $this->register();
        $before = $this->snapshot();
        $this->post(route('cash.'.$action), $this->payload($closed, $action))->assertConflict();
        $this->post(route('cash.'.$action), array_replace($this->payload($active, $action), ['cash_register_id' => 999999]))->assertConflict();
        $this->assertSame($before, $this->snapshot());
        $this->register();
        $before = $this->snapshot();
        $this->post(route('cash.'.$action), $this->payload($active, $action))->assertConflict();
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('actions')]
    public function test_reused_keys_reject_other_content_actor_and_operation(string $action): void
    {
        $register = $this->register();
        $payload = $this->payload($register, $action);
        $this->post(route('cash.'.$action), $payload)->assertRedirect();
        $before = $this->snapshot();
        $changed = $action === 'close' ? ['close_notes' => 'Outro conteúdo'] : ['amount' => '10.51'];
        $this->post(route('cash.'.$action), array_replace($payload, $changed))->assertConflict();
        $other = User::factory()->create(['active' => true]);
        $other->assignRole('admin');
        $this->actingAs($other)->post(route('cash.'.$action), $payload)->assertConflict();
        $this->actingAs($this->actor);
        $otherAction = $action === 'close' ? 'movement' : 'close';
        $otherPayload = array_replace($this->payload($register, $otherAction), ['idempotency_key' => $payload['idempotency_key']]);
        $this->post(route('cash.'.$otherAction), $otherPayload)->assertConflict();
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('actions')]
    public function test_operation_receipt_failure_rolls_back_and_original_key_can_retry(string $action): void
    {
        $register = $this->register();
        $payload = $this->payload($register, $action);
        $failOnce = true;
        DB::connection()->beforeExecuting(function ($query) use (&$failOnce) {
            if ($failOnce && str_starts_with($query, 'insert into "financial_operations"')) {
                $failOnce = false;
                throw new PDOException('Injected receipt failure');
            }
        });
        $before = $this->snapshot();
        $this->post(route('cash.'.$action), $payload)->assertStatus(503);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::connection()->transactionLevel());
        $this->post(route('cash.'.$action), $payload)->assertRedirect();
        $after = $this->snapshot();
        $this->post(route('cash.'.$action), $payload)->assertRedirect();
        $this->assertSame($after, $this->snapshot());
        $this->assertDatabaseCount('financial_operations', 1);
    }

    public static function databaseFailures(): array
    {
        return [
            ['close', 'Deadlock found when trying to get lock', 40001],
            ['movement', 'Deadlock found when trying to get lock', 40001],
            ['close', 'Lock wait timeout exceeded; try restarting transaction', 1205],
            ['movement', 'Lock wait timeout exceeded; try restarting transaction', 1205],
        ];
    }

    #[DataProvider('databaseFailures')]
    public function test_simulated_database_conflicts_exhaust_retries_with_full_rollback(string $action, string $message, int $code): void
    {
        $register = $this->register();
        $attempts = 0;
        DB::connection()->beforeExecuting(function ($query) use (&$attempts, $message, $code) {
            if (str_starts_with($query, 'insert into "financial_operations"')) {
                $attempts++;
                throw new PDOException($message, $code);
            }
        });
        $before = $this->snapshot();
        $this->post(route('cash.'.$action), $this->payload($register, $action))->assertStatus(503)->assertHeader('Retry-After', '1');
        $this->assertSame(3, $attempts);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_overflow_does_not_partially_close_the_register(): void
    {
        $register = $this->register('99999999.99');
        app(FinancialCoordinator::class)->movement($this->actor,
            array_replace($this->payload($register, 'movement'), ['amount' => '0.01']));
        $before = $this->snapshot();
        $this->postJson(route('cash.close'), $this->payload($register, 'close'))->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('actions')]
    public function test_missing_coordination_stops_both_operations_without_writes(string $action): void
    {
        $register = $this->register();
        DB::table('financial_coordination')->where('id', 1)->update(['id' => 2]);
        $before = $this->snapshot();
        $this->post(route('cash.'.$action), $this->payload($register, $action))->assertStatus(503);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame([2], DB::table('financial_coordination')->pluck('id')->all());
    }

    public function test_ambiguous_state_has_no_financial_forms_or_arbitrary_reports(): void
    {
        $this->register();
        $this->register();
        $before = $this->snapshot();
        $this->get(route('cash.index'))->assertOk()->assertSee('múltiplos caixas abertos')
            ->assertDontSee('action="'.route('cash.open').'"', false)
            ->assertDontSee('action="'.route('cash.movement').'"', false)
            ->assertDontSee('action="'.route('cash.close').'"', false);
        foreach (['cash.close.preview', 'cash.close.print', 'cash.close.export'] as $route) {
            $this->get(route($route))->assertConflict();
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_forms_share_close_identity_and_keep_actions_separate_after_validation(): void
    {
        $register = $this->register();
        $index = $this->get(route('cash.index'))->assertOk();
        $closeKey = $index->viewData('closingKey');
        $movementKey = $index->viewData('movementKey');
        $this->assertNotSame($closeKey, $movementKey);
        $this->assertTrue(Str::isUuid($closeKey));
        $preview = $this->get(route('cash.close.preview'))->assertOk()->assertViewHas('closingKey', $closeKey);
        $preview->assertSee('id="cashCloseForm"', false)->assertSee('form="cashCloseForm"', false);
        $this->assertSame(1, substr_count($preview->getContent(), 'name="idempotency_key"'));
        $badClose = array_replace($this->payload($register, 'close'), [
            'idempotency_key' => $closeKey, 'close_notes' => str_repeat('x', 2001),
        ]);
        $this->from(route('cash.index'))->post(route('cash.close'), $badClose)->assertSessionHasErrors('close_notes');
        $afterError = $this->get(route('cash.index'))->assertOk();
        foreach (['close' => $closeKey, 'movement' => $movementKey] as $action => $key) {
            $pattern = '~<form[^>]*action="'.preg_quote(route('cash.'.$action), '~').'"[^>]*>(.*?)</form>~s';
            $this->assertSame(1, preg_match($pattern, $afterError->getContent(), $form));
            $this->assertStringContainsString('name="idempotency_key" value="'.$key.'"', $form[1]);
        }
        $payload = array_replace($this->payload($register, 'movement'), ['idempotency_key' => $movementKey, 'amount' => '-1']);
        $this->from(route('cash.index'))->post(route('cash.movement'), $payload)->assertSessionHasErrors('amount');
        $this->get(route('cash.index'))->assertOk()
            ->assertSee('value="'.$closeKey.'"', false)->assertSee('value="'.$movementKey.'"', false);
        $payload['amount'] = '1.00';
        $this->post(route('cash.movement'), $payload)->assertRedirect();
        $next = $this->get(route('cash.index'))->assertOk();
        $this->assertSame($closeKey, $next->viewData('closingKey'));
        $this->assertNotSame($movementKey, $next->viewData('movementKey'));
        $this->post(route('cash.movement'), $payload)->assertRedirect();
        $this->assertDatabaseCount('cash_movements', 1);
    }

    public function test_opening_receipts_remain_compatible_with_nullable_new_columns(): void
    {
        $service = app(FinancialCoordinator::class);
        $payload = ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '50.00'];
        $first = $service->open($this->actor, $payload);
        $receipt = DB::table('financial_operations')->sole();
        $this->assertNull($receipt->result);
        $this->assertNull($receipt->cash_movement_id);
        $before = $this->snapshot();
        $second = $service->open($this->actor, $payload);
        $this->assertSame($first['cash_register_id'], $second['cash_register_id']);
        $this->assertTrue($second['replayed']);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_additive_migration_preserves_an_existing_phase_41_receipt(): void
    {
        // Outra conexão SQLite em memória para testar a atualização sobre dados legados.
        DB::purge('sqlite');
        $migration = database_path('migrations/2026_11_12_000001_add_results_to_financial_operations.php');
        $legacyPaths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => $path !== $migration));
        Artisan::call('migrate', ['--path' => $legacyPaths, '--realpath' => true, '--force' => true]);
        $actor = User::factory()->create(['active' => true]);
        $service = app(FinancialCoordinator::class);
        $payload = ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '25.50'];
        $first = $service->open($actor, $payload);
        $before = (array) DB::table('financial_operations')->sole();
        Artisan::call('migrate', ['--path' => [$migration], '--realpath' => true, '--force' => true]);
        $after = (array) DB::table('financial_operations')->sole();
        $this->assertNull($after['result']);
        $this->assertNull($after['cash_movement_id']);
        unset($after['result'], $after['cash_movement_id']);
        $this->assertSame($before, $after);
        $replay = $service->open($actor, $payload);
        $this->assertSame($first['cash_register_id'], $replay['cash_register_id']);
        $this->assertTrue($replay['replayed']);
        $register = CashRegister::findOrFail($first['cash_register_id']);
        $closed = $service->close($actor, $this->payload($register, 'close'));
        $this->assertSame('25.50', $closed['closing_amount']);
        $this->assertDatabaseCount('financial_operations', 2);
    }

    public function test_movement_receipt_reference_is_unique_and_restricts_deletion(): void
    {
        $register = $this->register();
        $result = app(FinancialCoordinator::class)->movement($this->actor, $this->payload($register, 'movement'));
        $receipt = (array) DB::table('financial_operations')->sole();
        $this->assertSame($result['cash_movement_id'], $receipt['cash_movement_id']);
        unset($receipt['id']);
        $receipt['idempotency_key'] = (string) Str::uuid();
        try {
            DB::table('financial_operations')->insert($receipt);
            $this->fail('Uma movimentação não deve pertencer a dois comprovantes.');
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $this->assertDatabaseCount('financial_operations', 1);
        }
        try {
            CashMovement::whereKey($result['cash_movement_id'])->delete();
            $this->fail('Uma movimentação referenciada deve preservar seu histórico.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseCount('cash_movements', 1);
        }
    }
}
