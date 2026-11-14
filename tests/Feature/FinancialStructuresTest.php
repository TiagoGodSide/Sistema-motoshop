<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialStructuresTest extends TestCase
{
    private array $stageSql = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['database.connections.mysql' => null, 'database.connections.sqlite.url' => null,
            'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null']);
        $stage = glob(database_path('migrations/2026_11_12_0001*.php'));
        $legacy = array_values(array_diff(glob(database_path('migrations/*.php')), $stage));
        Artisan::call('migrate', ['--path' => $legacy, '--realpath' => true, '--force' => true]);
        DB::listen(function ($query) { $this->stageSql[] = $query->sql; });
        Artisan::call('migrate', ['--path' => $stage, '--realpath' => true, '--force' => true]);
    }

    public function test_real_constraints_exist_without_legacy_rebuild(): void
    {
        $this->assertSame(1, (int) DB::scalar('PRAGMA foreign_keys'));
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertDatabaseCount('migrations', 32);
        $counts = [
            'operation_receipts' => 3, 'order_settlements' => 3, 'order_cash_effects' => 3,
            'order_stock_effects' => 3, 'order_item_snapshots' => 3, 'document_sequences' => 0,
        ];
        foreach ($counts as $table => $expected) {
            $this->assertTrue(Schema::hasTable($table));
            $fks = DB::select('PRAGMA foreign_key_list('.$table.')');
            $ids = array_unique(array_column(array_map(fn ($r) => (array) $r, $fks), 'id'));
            $this->assertCount($expected, $ids, $table);
            foreach ($fks as $fk) {
                $this->assertSame('RESTRICT', $fk->on_delete);
                $this->assertSame('RESTRICT', $fk->on_update);
            }
        }
        $this->assertTrue(Schema::hasColumn('products', 'stock_policy'));
        foreach ($this->stageSql as $sql) {
            $this->assertDoesNotMatchRegularExpression('/__temp__|drop table|rename to|insert into .*select /i', $sql);
        }
        $indexes = collect(Schema::getIndexes('financial_operations'))->keyBy('name');
        $this->assertTrue($indexes['fo_identity_uq']['unique']);
        $this->assertTrue($indexes['fo_cash_uq']['unique']);
        $this->assertSame(['id', 'idempotency_key', 'operation', 'user_id', 'request_hash'], $indexes['fo_identity_uq']['columns']);
    }
}
