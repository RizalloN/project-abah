<?php

namespace App\Services\Import\SchemaDrift;

use App\Services\Import\SchemaIntrospectionService;
use App\Support\SchemaMetadataCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Applies accepted schema drift to a registered import table:
 * - addColumns: new nullable columns appended at the end
 * - widenColumns: grow an existing column (VARCHAR length, DECIMAL precision,
 *   INT → BIGINT) so source values stop being truncated or overflowing
 * - validateMappings: a renamed header routed onto an existing column (no DDL)
 *
 * Every change is validated (safe identifier, whitelisted type, widening
 * only) and existing data is never reinterpreted. On MariaDB/MySQL the change
 * is attempted INSTANT, then INPLACE without lock; a blocking table copy is
 * only allowed for small tables, so a live multi-million-row table is never
 * locked by an import decision.
 */
class SchemaEvolutionService
{
    private const PROTECTED_TABLES = [
        'users', 'migrations', 'sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs',
        'job_batches', 'failed_jobs', 'nama_report', 'import_jobs', 'import_schema_drift_events',
        'import_column_format_profiles', 'import_source_header_baselines', 'login_histories',
    ];

    private const MANAGED_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /** Above this estimated row count a blocking table copy is refused. */
    private const COPY_ROW_LIMIT = 300000;

    /** Imports untouched this long are abandoned and must not block forever. */
    private const STALE_IMPORT_SECONDS = 21600;

    /**
     * @param array<int, array{name: string, type: string}> $columns
     * @return array<int, array{name: string, type: string, algorithm: string}>
     */
    public function addColumns(string $table, array $columns): array
    {
        $table = strtolower(trim($table));
        $this->assertEvolvableTable($table);

        $prepared = [];
        $seen = [];
        foreach ($columns as $column) {
            $name = strtolower(trim((string) ($column['name'] ?? '')));
            $type = $this->normalizeType((string) ($column['type'] ?? ''));

            if (!SchemaDriftDetector::isSafeColumnName($name)) {
                throw new RuntimeException("Nama kolom \"{$name}\" tidak valid. Gunakan huruf kecil, angka, dan garis bawah (maks. 64 karakter).");
            }
            if (!SchemaDriftDetector::isAllowedType($type)) {
                throw new RuntimeException("Tipe kolom \"{$type}\" tidak diizinkan.");
            }
            if (isset($seen[$name])) {
                throw new RuntimeException("Nama kolom \"{$name}\" dipakai lebih dari satu kali.");
            }
            $seen[$name] = true;
            $prepared[] = ['name' => $name, 'type' => $type];
        }

        if ($prepared === []) {
            return [];
        }

        return $this->withTableLock($table, function () use ($table, $prepared): array {
            $existing = $this->freshColumns($table);
            foreach ($prepared as $column) {
                // A chosen name that already exists would silently map the new
                // header onto unrelated data; that must be an explicit "map".
                if (isset($existing[$column['name']])) {
                    throw new RuntimeException("Kolom \"{$column['name']}\" sudah ada di tabel {$table}. Pilih nama lain, atau gunakan aksi \"Petakan ke kolom yang ada\".");
                }
            }

            $applied = [];
            foreach ($prepared as $column) {
                $algorithm = $this->runAlter(
                    $table,
                    sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s NULL', $table, $column['name'], $column['type']),
                    fn (Blueprint $blueprint) => $this->blueprintColumn($blueprint, $column['name'], $column['type'])->nullable(),
                    "menambah kolom {$column['name']}"
                );
                $applied[] = $column + ['algorithm' => $algorithm];
            }

            return $applied;
        });
    }

    /**
     * @param array<int, array{name: string, type: string}> $columns
     * @return array<int, array{name: string, type: string, from_type: string, algorithm: string}>
     */
    public function widenColumns(string $table, array $columns): array
    {
        $table = strtolower(trim($table));
        $this->assertEvolvableTable($table);

        return $this->withTableLock($table, function () use ($table, $columns): array {
            $existing = $this->freshColumns($table);
            $plans = [];
            foreach ($columns as $column) {
                $name = strtolower(trim((string) ($column['name'] ?? '')));
                $type = $this->normalizeType((string) ($column['type'] ?? ''));
                $current = $existing[$name] ?? null;
                if ($current === null) {
                    throw new RuntimeException("Kolom \"{$name}\" tidak ditemukan di tabel {$table}.");
                }
                if (in_array($name, self::MANAGED_COLUMNS, true) || str_starts_with($name, 'uniqueid')) {
                    throw new RuntimeException("Kolom \"{$name}\" dikelola sistem dan tidak boleh diubah.");
                }
                if (!SchemaDriftDetector::isAllowedType($type)) {
                    throw new RuntimeException("Tipe kolom \"{$type}\" tidak diizinkan.");
                }

                $fromType = strtoupper((string) $current['type']);
                if ($this->sameType($fromType, $type)) {
                    continue;
                }
                if (!$this->isWidening($fromType, $type)) {
                    throw new RuntimeException("Perubahan {$name} dari {$fromType} ke {$type} bukan pelebaran yang aman; hanya pelebaran yang diizinkan agar data lama tidak berubah.");
                }
                $plans[] = ['name' => $name, 'type' => $type, 'from_type' => $fromType, 'current' => $current];
            }

            $applied = [];
            foreach ($plans as $plan) {
                $algorithm = $this->runAlter(
                    $table,
                    sprintf('ALTER TABLE `%s` MODIFY COLUMN `%s` %s', $table, $plan['name'], $this->columnDefinitionTail($plan['type'], $plan['current'])),
                    fn (Blueprint $blueprint) => $this->blueprintColumn($blueprint, $plan['name'], $plan['type'])
                        ->nullable((bool) ($plan['current']['nullable'] ?? true))
                        ->change(),
                    "melebarkan kolom {$plan['name']}"
                );
                $applied[] = ['name' => $plan['name'], 'type' => $plan['type'], 'from_type' => $plan['from_type'], 'algorithm' => $algorithm];
            }

            return $applied;
        });
    }

    /**
     * @param array<int, array{name: string, source_header?: string|null, source_key?: string|null}> $mappings
     * @return array<int, array{name: string, source_header: string|null, source_key: string|null}>
     */
    public function validateMappings(string $table, array $mappings): array
    {
        if ($mappings === []) {
            return [];
        }

        $table = strtolower(trim($table));
        $this->assertEvolvableTable($table);
        $existing = $this->freshColumns($table);

        $validated = [];
        $targets = [];
        foreach ($mappings as $mapping) {
            $name = strtolower(trim((string) ($mapping['name'] ?? '')));
            if ($name === '' || !isset($existing[$name])) {
                throw new RuntimeException("Kolom tujuan pemetaan \"{$name}\" tidak ada di tabel {$table}.");
            }
            if (in_array($name, self::MANAGED_COLUMNS, true) || str_starts_with($name, 'uniqueid')) {
                throw new RuntimeException("Kolom \"{$name}\" dikelola sistem dan tidak bisa menjadi tujuan pemetaan.");
            }
            if (isset($targets[$name])) {
                throw new RuntimeException("Dua kolom sumber dipetakan ke kolom yang sama ({$name}).");
            }
            $targets[$name] = true;
            $validated[] = [
                'name' => $name,
                'source_header' => $mapping['source_header'] ?? null,
                'source_key' => $mapping['source_key'] ?? null,
            ];
        }

        return $validated;
    }

    public function assertEvolvableTable(string $table): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $table) || in_array($table, self::PROTECTED_TABLES, true)) {
            throw new RuntimeException("Tabel {$table} tidak boleh diubah otomatis.");
        }

        if ((new SchemaIntrospectionService())->getColumnMetadata($table) === []) {
            throw new RuntimeException("Tabel {$table} tidak ditemukan.");
        }

        if (Schema::hasTable('nama_report')) {
            $registered = DB::table('nama_report')->whereRaw('LOWER(TRIM(table_name)) = ?', [$table])->exists();
            if (!$registered) {
                throw new RuntimeException("Tabel {$table} bukan tabel tujuan laporan yang terdaftar di Report Management.");
            }
        }
    }

    /**
     * Whether $toType can hold every value of $fromType without changing it.
     */
    public function isWidening(string $fromType, string $toType): bool
    {
        $from = $this->parseType($fromType);
        $to = $this->parseType($toType);
        if ($from === null || $to === null) {
            return false;
        }

        if ($from['family'] === 'text') {
            if ($to['family'] !== 'text') {
                return false;
            }

            return $to['length'] === null || ($from['length'] !== null && $to['length'] > $from['length']);
        }

        if ($from['family'] === 'integer') {
            if ($to['family'] === 'integer') {
                return $to['digits'] > $from['digits'];
            }

            return $to['family'] === 'decimal' && $to['precision'] - $to['scale'] >= $from['digits'];
        }

        if ($from['family'] === 'decimal') {
            return $to['family'] === 'decimal'
                && $to['scale'] >= $from['scale']
                && $to['precision'] - $to['scale'] >= $from['precision'] - $from['scale']
                && ($to['precision'] > $from['precision'] || $to['scale'] > $from['scale']);
        }

        return false;
    }

    private function sameType(string $fromType, string $toType): bool
    {
        $from = $this->parseType($fromType);
        $to = $this->parseType($toType);

        return $from !== null && $to !== null && $from == $to;
    }

    /**
     * @return array{family: string, length?: int|null, digits?: int, precision?: int, scale?: int}|null
     */
    private function parseType(string $type): ?array
    {
        $type = strtolower(trim($type));
        if (preg_match('/^(var)?char\((\d+)\)/', $type, $matches) === 1) {
            return ['family' => 'text', 'length' => (int) $matches[2]];
        }
        if (preg_match('/^(tiny|medium|long)?text\b/', $type) === 1) {
            return ['family' => 'text', 'length' => null];
        }
        if (preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint)\b/', $type, $matches) === 1) {
            $digits = ['tinyint' => 3, 'smallint' => 5, 'mediumint' => 8, 'int' => 10, 'integer' => 10, 'bigint' => 20][$matches[1]];

            return ['family' => 'integer', 'digits' => $digits];
        }
        if (preg_match('/^(decimal|numeric)\((\d+)(?:,\s*(\d+))?\)/', $type, $matches) === 1) {
            return ['family' => 'decimal', 'precision' => (int) $matches[2], 'scale' => (int) ($matches[3] ?? 0)];
        }

        return null;
    }

    /**
     * MODIFY COLUMN replaces the whole definition, so nullability, default,
     * collation and comment are restated exactly as they are today.
     */
    private function columnDefinitionTail(string $type, array $current): string
    {
        $parts = [$type];
        if (!empty($current['collation']) && $this->parseType($type)['family'] === 'text') {
            $parts[] = 'COLLATE ' . $current['collation'];
        }
        $parts[] = !empty($current['nullable']) ? 'NULL' : 'NOT NULL';
        if (array_key_exists('default', $current) && $current['default'] !== null) {
            $parts[] = 'DEFAULT ' . DB::getPdo()->quote((string) $current['default']);
        }
        if (!empty($current['comment'])) {
            $parts[] = 'COMMENT ' . DB::getPdo()->quote((string) $current['comment']);
        }

        return implode(' ', $parts);
    }

    /**
     * Fresh (uncached) column definitions: a long-running worker or the 24h
     * schema cache may hold an older listing.
     *
     * @return array<string, array{type: string, nullable: bool, default: mixed, collation: ?string, comment: ?string}>
     */
    private function freshColumns(string $table): array
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $columns = [];
            foreach (DB::select('PRAGMA table_info("' . str_replace('"', '""', $table) . '")') as $row) {
                $columns[strtolower((string) $row->name)] = [
                    'type' => strtoupper((string) $row->type),
                    'nullable' => (int) $row->notnull === 0,
                    'default' => $row->dflt_value,
                    'collation' => null,
                    'comment' => null,
                ];
            }

            return $columns;
        }

        $columns = [];
        foreach (DB::select('SHOW FULL COLUMNS FROM `' . str_replace('`', '``', $table) . '`') as $row) {
            $columns[strtolower((string) $row->Field)] = [
                'type' => strtoupper((string) $row->Type),
                'nullable' => strtoupper((string) $row->Null) === 'YES',
                'default' => $row->Default,
                'collation' => $row->Collation ?: null,
                'comment' => $row->Comment ?: null,
            ];
        }

        return $columns;
    }

    private function withTableLock(string $table, callable $callback): array
    {
        $lock = Cache::lock('import-schema-evolution:' . $table, 300);
        if (!$lock->get()) {
            throw new RuntimeException("Perubahan struktur tabel {$table} sedang diproses oleh permintaan lain. Coba beberapa saat lagi.");
        }

        try {
            $this->assertNoActiveImport($table);
            $result = $callback();
            $this->invalidateSchemaCaches();
            Log::info('Import schema evolved.', ['table' => $table, 'changes' => $result]);

            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * The application caches schema metadata (in memory and in the cache store
     * for 24 hours). A raw ALTER must invalidate it so the importer sees the new
     * column immediately; long-running workers pick it up via the version stamp.
     */
    private function invalidateSchemaCaches(): void
    {
        try {
            app(SchemaMetadataCache::class)->invalidate(DB::connection());
        } catch (Throwable $e) {
            Log::warning('Schema metadata cache invalidation failed after evolution.', ['error' => $e->getMessage()]);
        }
    }

    private function assertNoActiveImport(string $table): void
    {
        if (!Schema::hasTable('import_jobs') || !Schema::hasTable('nama_report')) {
            return;
        }

        $active = DB::table('import_jobs as j')
            ->join('nama_report as r', 'r.id_report', '=', 'j.id_report')
            ->whereIn('j.status', ['queued', 'staging', 'processing'])
            ->where(function ($query): void {
                $query->whereNull('j.updated_at')
                    ->orWhere('j.updated_at', '>=', now()->subSeconds(self::STALE_IMPORT_SECONDS));
            })
            ->whereRaw('LOWER(TRIM(r.table_name)) = ?', [$table])
            ->count();

        if ($active > 0) {
            throw new RuntimeException("Masih ada {$active} import aktif ke tabel {$table}. Tunggu hingga selesai sebelum mengubah struktur tabel.");
        }
    }

    /**
     * INSTANT → INPLACE (no lock) → COPY, where COPY is only allowed on small
     * tables because it blocks writes for the whole rebuild.
     */
    private function runAlter(string $table, string $statement, callable $schemaBuilderFallback, string $label): string
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $lastError = null;
            foreach ([', ALGORITHM=INSTANT' => 'instant', ', ALGORITHM=INPLACE, LOCK=NONE' => 'inplace'] as $clause => $algorithm) {
                try {
                    DB::statement($statement . $clause);

                    return $algorithm;
                } catch (Throwable $e) {
                    $lastError = $e;
                }
            }

            $rows = $this->estimatedRows($table);
            if ($rows > self::COPY_ROW_LIMIT) {
                throw new RuntimeException(sprintf(
                    'Gagal %s tanpa mengunci tabel: tabel %s berisi ±%s baris sehingga penyalinan ulang tabel akan mengganggu dashboard. Lakukan di jam pemeliharaan. Detail: %s',
                    $label,
                    $table,
                    number_format($rows, 0, ',', '.'),
                    $lastError?->getMessage() ?? '-'
                ));
            }

            try {
                DB::statement($statement);

                return 'copy';
            } catch (Throwable $e) {
                throw new RuntimeException("Gagal {$label}: " . $e->getMessage(), 0, $e);
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($schemaBuilderFallback): void {
            $schemaBuilderFallback($blueprint);
        });

        return 'schema_builder';
    }

    private function estimatedRows(string $table): int
    {
        try {
            return (int) DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('TABLE_ROWS');
        } catch (Throwable) {
            return PHP_INT_MAX;
        }
    }

    private function normalizeType(string $type): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($type)) ?? '');
    }

    private function blueprintColumn(Blueprint $blueprint, string $name, string $type)
    {
        if (preg_match('/^DECIMAL\((\d+),(\d+)\)$/', $type, $matches) === 1) {
            return $blueprint->decimal($name, (int) $matches[1], (int) $matches[2]);
        }
        if (preg_match('/^VARCHAR\((\d+)\)$/', $type, $matches) === 1) {
            return $blueprint->string($name, (int) $matches[1]);
        }

        return match ($type) {
            'DATE' => $blueprint->date($name),
            'DATETIME' => $blueprint->dateTime($name),
            'INT' => $blueprint->integer($name),
            'BIGINT' => $blueprint->bigInteger($name),
            default => $blueprint->text($name),
        };
    }
}
