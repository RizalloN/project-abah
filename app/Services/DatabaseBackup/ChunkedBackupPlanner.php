<?php

namespace App\Services\DatabaseBackup;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lists the parts of a chunked backup. Large period tables are split per
 * period and fingerprinted from an index-only scan (row count and primary key
 * range), which changes whenever a period is re-imported, rebuilt or pruned.
 */
class ChunkedBackupPlanner
{
    public const NULL_PERIOD = '__null__';

    /**
     * @param  array<string, string>  $chunkedTables  table => period column
     * @param  array<int, string>  $schemaOnlyTables
     * @param  array<int, string>  $schemaOnlyPrefixes
     * @return array<int, array<string, mixed>>
     */
    public function plan(
        string $database,
        array $chunkedTables,
        array $schemaOnlyTables,
        array $schemaOnlyPrefixes
    ): array {
        $connection = DB::connection();
        $tables = $this->baseTables($connection, $database);
        if ($tables === []) {
            throw new RuntimeException('Database tidak memiliki tabel untuk di-backup.');
        }

        $parts = [
            ['key' => 'schema', 'file' => 'schema.sql.gz', 'kind' => 'schema', 'table' => null],
        ];

        foreach ($tables as $table) {
            if ($this->isSchemaOnly($table, $schemaOnlyTables, $schemaOnlyPrefixes)) {
                continue;
            }

            $schemaHash = $this->schemaHash($connection, $database, $table);
            $periodColumn = $chunkedTables[$table] ?? null;
            $chunks = $periodColumn !== null
                ? $this->periodFingerprints($connection, $database, $table, $periodColumn)
                : null;

            if ($chunks === null) {
                $parts[] = [
                    'key' => 'data/'.$table,
                    'file' => 'data/'.$table.'.sql.gz',
                    'kind' => 'data',
                    'table' => $table,
                    'schema_hash' => $schemaHash,
                    'fingerprint' => null,
                ];

                continue;
            }

            foreach ($chunks as $chunk) {
                $period = $chunk['period'];
                $name = $period === null ? self::NULL_PERIOD : $this->safeName($period);
                $parts[] = [
                    'key' => 'data/'.$table.'/'.$name,
                    'file' => 'data/'.$table.'/'.$name.'.sql.gz',
                    'kind' => 'data',
                    'table' => $table,
                    'period_column' => $periodColumn,
                    'period' => $period,
                    'where' => $period === null
                        ? '`'.$periodColumn.'` IS NULL'
                        : '`'.$periodColumn.'` = '.$connection->getPdo()->quote($period),
                    'rows' => $chunk['rows'],
                    'schema_hash' => $schemaHash,
                    'fingerprint' => hash('sha256', implode('|', [
                        $schemaHash, $chunk['rows'], $chunk['min_key'], $chunk['max_key'],
                    ])),
                ];
            }
        }

        $parts[] = ['key' => 'post-data', 'file' => 'post-data.sql.gz', 'kind' => 'post', 'table' => null];

        return $parts;
    }

    /**
     * Fingerprint of one period, used to prove a dumped chunk is still the
     * data that was planned.
     */
    public function periodFingerprint(string $database, string $table, string $periodColumn, ?string $period): ?string
    {
        $connection = DB::connection();
        foreach ($this->periodFingerprints($connection, $database, $table, $periodColumn, $period, true) ?? [] as $chunk) {
            return hash('sha256', implode('|', [
                $this->schemaHash($connection, $database, $table), $chunk['rows'], $chunk['min_key'], $chunk['max_key'],
            ]));
        }

        return null;
    }

    /** @return array<int, string> */
    private function baseTables(ConnectionInterface $connection, string $database): array
    {
        return collect($connection->select(
            "SELECT TABLE_NAME AS name FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            [$database]
        ))->pluck('name')->map(static fn ($name): string => (string) $name)->all();
    }

    /**
     * @param  array<int, string>  $schemaOnlyTables
     * @param  array<int, string>  $schemaOnlyPrefixes
     */
    private function isSchemaOnly(string $table, array $schemaOnlyTables, array $schemaOnlyPrefixes): bool
    {
        if (in_array($table, $schemaOnlyTables, true)) {
            return true;
        }

        foreach ($schemaOnlyPrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($table, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function schemaHash(ConnectionInterface $connection, string $database, string $table): string
    {
        $row = (array) $connection->selectOne('SHOW CREATE TABLE '.$this->qualified($database, $table));
        $ddl = (string) ($row['Create Table'] ?? array_values($row)[1] ?? '');
        // The AUTO_INCREMENT counter moves with every insert; it is not schema.
        $ddl = preg_replace('/\sAUTO_INCREMENT=\d+/', '', $ddl) ?? $ddl;

        return hash('sha256', $ddl);
    }

    /**
     * @return array<int, array{period: ?string, rows: int, min_key: string, max_key: string}>|null
     */
    private function periodFingerprints(
        ConnectionInterface $connection,
        string $database,
        string $table,
        string $periodColumn,
        ?string $onlyPeriod = null,
        bool $filtered = false
    ): ?array {
        $quotedTable = $this->qualified($database, $table);
        $indexes = collect($connection->select('SHOW INDEX FROM '.$quotedTable));
        $primary = $indexes->where('Key_name', 'PRIMARY')->pluck('Column_name')->values();
        $periodIndex = $indexes->groupBy('Key_name')
            ->filter(static fn ($columns): bool => (string) $columns->sortBy('Seq_in_index')->first()->Column_name === $periodColumn)
            ->map->count()
            ->sort()
            ->keys()
            ->first();

        // Without a single-column key and a period index the identity cannot
        // be read cheaply; the table is then dumped whole every night.
        if ($primary->count() !== 1 || $periodIndex === null) {
            return null;
        }

        $key = '`'.str_replace('`', '``', (string) $primary->first()).'`';
        $column = '`'.str_replace('`', '``', $periodColumn).'`';
        $where = '';
        $bindings = [];
        if ($filtered) {
            $where = $onlyPeriod === null ? " WHERE {$column} IS NULL" : " WHERE {$column} = ?";
            $bindings = $onlyPeriod === null ? [] : [$onlyPeriod];
        }

        $rows = $connection->select(
            "SELECT {$column} AS period, COUNT(*) AS row_count, MIN({$key}) AS min_key, MAX({$key}) AS max_key
             FROM {$quotedTable} FORCE INDEX (`".str_replace('`', '``', (string) $periodIndex)."`){$where}
             GROUP BY {$column} ORDER BY {$column}",
            $bindings
        );

        return array_map(static fn (object $row): array => [
            'period' => $row->period === null ? null : (string) $row->period,
            'rows' => (int) $row->row_count,
            'min_key' => (string) $row->min_key,
            'max_key' => (string) $row->max_key,
        ], $rows);
    }

    private function qualified(string $database, string $table): string
    {
        return '`'.str_replace('`', '``', $database).'`.`'.str_replace('`', '``', $table).'`';
    }

    private function safeName(string $period): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $period) ?? '';

        return $safe === $period && $safe !== '' ? $safe : $safe.'-'.substr(hash('sha256', $period), 0, 8);
    }
}
