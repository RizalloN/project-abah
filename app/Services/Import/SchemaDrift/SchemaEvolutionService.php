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
 * Applies accepted schema drift: adds new nullable columns to a registered
 * import table. Every column is validated (safe identifier, whitelisted type),
 * existing data is never touched, and on MariaDB/MySQL the change is attempted
 * as an INSTANT metadata-only ALTER so even multi-million-row tables do not
 * get rebuilt.
 */
class SchemaEvolutionService
{
    private const PROTECTED_TABLES = [
        'users', 'migrations', 'sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs',
        'job_batches', 'failed_jobs', 'nama_report', 'import_jobs', 'import_schema_drift_events',
        'import_column_format_profiles', 'login_histories',
    ];

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
            $type = strtoupper(preg_replace('/\s+/', '', trim((string) ($column['type'] ?? ''))) ?? '');

            if (!SchemaDriftDetector::isSafeColumnName($name)) {
                throw new RuntimeException("Nama kolom \"{$name}\" tidak valid. Gunakan huruf kecil, angka, dan garis bawah (maks. 64 karakter).");
            }
            if (!SchemaDriftDetector::isAllowedType($type)) {
                throw new RuntimeException("Tipe kolom \"{$type}\" tidak diizinkan.");
            }
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $prepared[] = ['name' => $name, 'type' => $type];
        }

        if ($prepared === []) {
            return [];
        }

        $lock = Cache::lock('import-schema-evolution:' . $table, 120);
        if (!$lock->get()) {
            throw new RuntimeException("Perubahan struktur tabel {$table} sedang diproses oleh permintaan lain. Coba beberapa saat lagi.");
        }

        try {
            $this->assertNoActiveImport($table);

            // Fresh introspection: a long-running worker may hold an older listing.
            $existing = array_keys((new SchemaIntrospectionService())->getColumnMetadata($table));
            $applied = [];
            foreach ($prepared as $column) {
                if (in_array($column['name'], $existing, true)) {
                    $applied[] = $column + ['algorithm' => 'already_exists'];
                    continue;
                }

                $algorithm = $this->addColumn($table, $column['name'], $column['type']);
                $applied[] = $column + ['algorithm' => $algorithm];
                $existing[] = $column['name'];
            }

            $this->invalidateSchemaCaches();
            Log::info('Import schema evolved.', ['table' => $table, 'columns' => $applied]);

            return $applied;
        } finally {
            $lock->release();
        }
    }

    public function assertEvolvableTable(string $table): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $table) || in_array($table, self::PROTECTED_TABLES, true)) {
            throw new RuntimeException("Tabel {$table} tidak boleh diubah otomatis.");
        }

        if (!Schema::hasTable($table)) {
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
            ->whereIn('j.status', ['queued', 'processing'])
            ->whereRaw('LOWER(TRIM(r.table_name)) = ?', [$table])
            ->count();

        if ($active > 0) {
            throw new RuntimeException("Masih ada {$active} import aktif ke tabel {$table}. Tunggu hingga selesai sebelum menambah kolom.");
        }
    }

    private function addColumn(string $table, string $name, string $type): string
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $definition = sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s NULL', $table, $name, $type);
            foreach ([', ALGORITHM=INSTANT' => 'instant', ', ALGORITHM=INPLACE, LOCK=NONE' => 'inplace', '' => 'copy'] as $clause => $algorithm) {
                try {
                    DB::statement($definition . $clause);

                    return $algorithm;
                } catch (Throwable $e) {
                    if ($clause === '') {
                        throw new RuntimeException("Gagal menambah kolom {$name}: " . $e->getMessage(), 0, $e);
                    }
                }
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name, $type): void {
            $this->blueprintColumn($blueprint, $name, $type)->nullable();
        });

        return 'schema_builder';
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
