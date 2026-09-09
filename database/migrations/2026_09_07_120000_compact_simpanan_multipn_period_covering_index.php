<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'simpanan_multipn';

    private const INDEX = 'idx_smp_period_covering_counts';

    /** @var array<int, string> */
    private const LEGACY_COLUMNS = [
        'posisi',
        'kantor_cabang',
        'unit_kerja',
        'no_rekening',
        'CIFNO',
        'jenis_simpanan',
        'saldo_idr',
    ];

    /** @var array<int, string> */
    private const COMPACT_COLUMNS = [
        'posisi',
        'kantor_cabang',
        'unit_kerja',
        'saldo_idr',
    ];

    /** @var array<int, string> */
    private const ACTIVE_IMPORT_STATUSES = ['queued', 'staging', 'processing'];

    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = $this->indexColumnMap()[self::INDEX] ?? null;

        if ($columns === self::COMPACT_COLUMNS) {
            return;
        }

        if ($columns !== self::LEGACY_COLUMNS) {
            throw new RuntimeException(sprintf(
                'Index %s.%s tidak sesuai definisi 7 kolom yang telah diaudit; migrasi dihentikan tanpa mengubah indeks.',
                self::TABLE,
                self::INDEX
            ));
        }

        $this->assertNoActiveImports();

        DB::statement(
            'ALTER TABLE `simpanan_multipn` '
            .'DROP INDEX `idx_smp_period_covering_counts`, '
            .'ADD INDEX `idx_smp_period_covering_counts` (`posisi`, `kantor_cabang`, `unit_kerja`, `saldo_idr`), '
            .'ALGORITHM=NOCOPY, LOCK=NONE'
        );
    }

    public function down(): void
    {
        // Rebuilding the audited 11+ GiB legacy index during rollback is unsafe.
    }

    private function assertNoActiveImports(): void
    {
        if (! Schema::hasTable('import_jobs')) {
            throw new RuntimeException('Tabel import_jobs tidak tersedia untuk pemeriksaan writer aktif.');
        }

        $activeJobIds = DB::table('import_jobs')
            ->whereIn('status', self::ACTIVE_IMPORT_STATUSES)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($activeJobIds !== []) {
            throw new RuntimeException(
                'Pemadatan indeks simpanan_multipn dibatalkan karena masih ada job import aktif: '
                .implode(', ', $activeJobIds)
            );
        }
    }

    /** @return array<string, array<int, string>> */
    private function indexColumnMap(): array
    {
        $rows = DB::table('information_schema.statistics')
            ->select('index_name', 'column_name', 'seq_in_index')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', self::TABLE)
            ->orderBy('index_name')
            ->orderBy('seq_in_index')
            ->get();

        $indexes = [];
        foreach ($rows as $row) {
            $indexes[(string) $row->index_name][] = (string) $row->column_name;
        }

        return $indexes;
    }
};
