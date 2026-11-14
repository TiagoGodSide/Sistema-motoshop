<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Additive DDL only. CHECKs are compiled into CREATE, never ALTERed into legacy tables. */
final class FinancialSchema
{
    public static function integerRange(string $column, int $min, int $max, bool $nullable = false): string
    {
        $range = "{$column} between {$min} and {$max}";
        if (DB::connection()->getDriverName() === 'sqlite') {
            $range = "typeof({$column}) = 'integer' and ({$range})";
        }
        return $nullable ? "{$column} is null or ({$range})" : $range;
    }

    public static function verify(array $tables): void
    {
        if (PHP_INT_SIZE !== 8) {
            throw new RuntimeException('Schema financeiro exige PHP de 64 bits.');
        }
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new RuntimeException('Motor não homologável para este schema.');
        }
        foreach ($tables as $table => $columns) {
            if ($driver === 'mysql') {
                $engine = DB::scalar('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
                if ($engine !== 'InnoDB') {
                    throw new RuntimeException("Tabela financeira fora do InnoDB: {$table}.");
                }
            }
            $actual = collect(Schema::getColumns($table))->keyBy('name');
            foreach ($columns as $name => $expected) {
                $column = $actual->get($name);
                $type = strtolower($column['type'] ?? '');
                $valid = $driver === 'mysql' ? $type === $expected
                    : ($expected === 'bigint unsigned' ? $type === 'integer' : in_array($type, ['varchar', 'text'], true));
                if (! $valid) {
                    throw new RuntimeException("Schema incompatível: {$table}.{$name} ({$type}).");
                }
            }
        }
    }

    public static function identityColumns(Blueprint $table): void
    {
        $columns = collect(Schema::getColumns('financial_operations'))->keyBy('name');
        $definitions = [
            'idempotency_key' => $table->uuid('idempotency_key'),
            'operation' => $table->string('operation', 40),
            'request_hash' => $table->char('request_hash', 64),
        ];
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach ($definitions as $name => $definition) {
                $collation = $columns[$name]['collation'] ?? null;
                if (! $collation) {
                    throw new RuntimeException("Collation financeira ausente: {$name}.");
                }
                $definition->collation($collation);
            }
        }
    }

    public static function create(string $name, Closure $definition, array $checks = []): void
    {
        $connection = DB::connection();
        $blueprint = new Blueprint($connection, $name);
        if ($connection->getDriverName() === 'mysql') {
            $blueprint->engine = 'InnoDB';
        }
        $blueprint->create();
        $definition($blueprint);
        foreach ($blueprint->toSql() as $sql) {
            if ($checks && str_starts_with(strtolower($sql), 'create table ')) {
                $position = strrpos($sql, ')');
                if ($position === false) {
                    throw new RuntimeException('CREATE TABLE não reconhecido.');
                }
                $constraints = [];
                foreach ($checks as $key => $condition) {
                    $constraints[] = 'constraint '.$connection->getQueryGrammar()->wrap($key).' check ('.$condition.')';
                }
                $sql = substr($sql, 0, $position).', '.implode(', ', $constraints).substr($sql, $position);
            }
            $connection->statement($sql);
        }
    }
}
