<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SQLITE_AUTO_INCREMENT_COLUMNS = [
        'bod_boc' => 'id',
        'failed_jobs' => 'id',
        'import_jobs' => 'id',
        'input_rekanan' => 'id',
        'jobs' => 'id',
        'migrations' => 'id',
        'nama_report' => 'id_report',
        'report_sync_audits' => 'id',
        'users' => 'id',
    ];

    private const MANAGED_TABLES = [
        'bod_boc',
        'brilink_web_laporan_summary_transaksi_brilink_web',
        'cache',
        'cache_locks',
        'casa_brilink_edc',
        'casa_brilink_web',
        'daily_loan_dinamis',
        'dashboard_pinjaman_snapshots',
        'dashboard_simpanan_branch_snapshots',
        'dashboard_simpanan_snapshots',
        'failed_jobs',
        'import_jobs',
        'input_rekanan',
        'jobs',
        'job_batches',
        'jumlah_merchant_detail',
        'lw325_ph',
        'merchant_qris',
        'merchant_qris_volume',
        'nama_report',
        'password_reset_tokens',
        'performance_new_payroll_snapshots',
        'performance_pis_per_produk',
        'rasio_casa_debitur_snapshots',
        'rekening_dormant_snapshots',
        'report_sync_audits',
        'sessions',
        'simpanan_multipn',
        'sv_merchant',
        'users',
        'user_brimo_fin',
        'user_brimo_rpt_v2',
    ];

    private const NAMA_REPORT_SEED = [
        ['id_report' => 1, 'nama_report' => 'Jumlah Merchant Detail', 'table_name' => 'jumlah_merchant_detail', 'active' => 1, 'import_controller' => 'ImportFileController', 'requires_manual_periode' => 0, 'manual_periode_type' => null, 'manual_periode_label' => null, 'manual_periode_help' => null],
        ['id_report' => 2, 'nama_report' => 'SV Merchant', 'table_name' => 'sv_merchant', 'active' => 1, 'import_controller' => 'ImportFileController', 'requires_manual_periode' => 0, 'manual_periode_type' => null, 'manual_periode_label' => null, 'manual_periode_help' => null],
        ['id_report' => 3, 'nama_report' => 'Merchant_QRIS', 'table_name' => 'merchant_qris', 'active' => 1, 'import_controller' => 'ImportFileController', 'requires_manual_periode' => 0, 'manual_periode_type' => null, 'manual_periode_label' => null, 'manual_periode_help' => null],
        ['id_report' => 4, 'nama_report' => 'Merchant QRIS Volume', 'table_name' => 'merchant_qris_volume', 'active' => 1, 'import_controller' => 'ImportFileController', 'requires_manual_periode' => 0, 'manual_periode_type' => null, 'manual_periode_label' => null, 'manual_periode_help' => null],
        ['id_report' => 5, 'nama_report' => 'User Brimo RPT v2', 'table_name' => 'user_brimo_rpt_v2', 'active' => 1, 'import_controller' => 'ImportFileBrimoController', 'requires_manual_periode' => 0, 'manual_periode_type' => null, 'manual_periode_label' => null, 'manual_periode_help' => null],
        ['id_report' => 6, 'nama_report' => 'User Brimo Fin', 'table_name' => 'user_brimo_fin', 'active' => 1, 'import_controller' => 'ImportFileBrimoController', 'requires_manual_periode' => 0, 'manual_periode_type' => null, 'manual_periode_label' => null, 'manual_periode_help' => null],
        ['id_report' => 7, 'nama_report' => 'BRILINK Web - Laporan Summary Transaksi', 'table_name' => 'brilink_web_laporan_summary_transaksi_brilink_web', 'active' => 1, 'import_controller' => 'ImportFileController', 'requires_manual_periode' => 0, 'manual_periode_type' => null, 'manual_periode_label' => null, 'manual_periode_help' => null],
        ['id_report' => 8, 'nama_report' => 'Daily Loan Dinamis ', 'table_name' => 'daily_loan_dinamis', 'active' => 1, 'import_controller' => 'ImportExcelController', 'requires_manual_periode' => 0, 'manual_periode_type' => null, 'manual_periode_label' => null, 'manual_periode_help' => null],
        ['id_report' => 9, 'nama_report' => 'Simpanan MultiPN', 'table_name' => 'simpanan_multipn', 'active' => 1, 'import_controller' => 'ImportExcelController|ImportSimpananMultiPnCsvController', 'requires_manual_periode' => 0, 'manual_periode_type' => null, 'manual_periode_label' => null, 'manual_periode_help' => null],
        ['id_report' => 10, 'nama_report' => 'Input Rekanan', 'table_name' => 'input_rekanan', 'active' => 1, 'import_controller' => 'InputRekananController', 'requires_manual_periode' => 1, 'manual_periode_type' => 'date', 'manual_periode_label' => 'Tanggal Periode', 'manual_periode_help' => 'Input Rekanan wajib diisi tanggal periode manual (YYYY-MM-DD).'],
        ['id_report' => 11, 'nama_report' => 'Nasabah Prioritas BOD BOC', 'table_name' => 'bod_boc', 'active' => 1, 'import_controller' => 'BodBocController', 'requires_manual_periode' => 1, 'manual_periode_type' => 'date', 'manual_periode_label' => 'Tanggal Periode', 'manual_periode_help' => 'Nasabah Prioritas BOD/BOC wajib diisi tanggal periode manual (YYYY-MM-DD).'],
        ['id_report' => 12, 'nama_report' => 'CASA BRILINK WEB', 'table_name' => 'casa_brilink_web', 'active' => 1, 'import_controller' => 'ImportCasaBrilinkController', 'requires_manual_periode' => 1, 'manual_periode_type' => 'month', 'manual_periode_label' => 'Periode Bulan', 'manual_periode_help' => 'Wajib isi periode manual dalam format bulan (YYYY-MM) untuk CASA BRILINK WEB.'],
        ['id_report' => 13, 'nama_report' => 'CASA BRILINK EDC', 'table_name' => 'casa_brilink_edc', 'active' => 1, 'import_controller' => 'ImportCasaBrilinkController', 'requires_manual_periode' => 1, 'manual_periode_type' => 'month', 'manual_periode_label' => 'Periode Bulan', 'manual_periode_help' => 'Wajib isi periode manual dalam format bulan (YYYY-MM) untuk CASA BRILINK EDC.'],
        ['id_report' => 14, 'nama_report' => 'Performance PIS per Produk', 'table_name' => 'performance_pis_per_produk', 'active' => 1, 'import_controller' => 'ImportPerformancePisPerProdukController', 'requires_manual_periode' => 1, 'manual_periode_type' => 'date', 'manual_periode_label' => 'Tanggal Periode', 'manual_periode_help' => 'Wajib isi tanggal periode manual (YYYY-MM-DD) untuk Performance PIS per Produk.'],
        ['id_report' => 15, 'nama_report' => 'Report Nominatif Rekening Pinjaman PH', 'table_name' => 'lw325_ph', 'active' => 1, 'import_controller' => 'ImportReportPhController', 'requires_manual_periode' => 0, 'manual_periode_type' => null, 'manual_periode_label' => null, 'manual_periode_help' => null],
    ];

    public function up(): void
    {
        if ($this->isFreshApplicationSchema()) {
            $this->importSchemaDump(database_path('schema/mysql-schema-bootstrap.sql'));
        }

        $this->createDashboardSimpananSnapshotTables();
        $this->ensureImportJobsSchema();
        $this->ensureInputAndBodColumns();
        $this->ensureNamaReportMetadataColumns();
        $this->ensureRuntimeIndexes();
        if (!in_array(DB::connection()->getDriverName(), ['sqlite'], true)) {
            $this->createSnapshotInvalidationTriggers();
        }
        $this->seedNamaReport();
    }

    public function down(): void
    {
        $this->dropSnapshotInvalidationTriggers();

        foreach (array_reverse(self::MANAGED_TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function isFreshApplicationSchema(): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $tables = DB::table('sqlite_master')
                ->where('type', 'table')
                ->where('name', '<>', 'migrations')
                ->pluck('name');

            return $tables->isEmpty();
        }

        $tables = DB::table('information_schema.tables')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', '<>', 'migrations')
            ->pluck('table_name');

        return $tables->isEmpty();
    }

    private function importSchemaDump(string $path): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Schema dump tidak ditemukan: ' . $path);
        }

        foreach ($this->parseSqlStatements($path) as $statement) {
            if ($this->shouldSkipSchemaStatement($statement)) {
                continue;
            }

            if (DB::connection()->getDriverName() === 'sqlite') {
                $statement = $this->normalizeStatementForSqlite($statement);
            }

            if ($statement === '') {
                continue;
            }

            DB::unprepared($statement);
        }
    }

    private function parseSqlStatements(string $path): array
    {
        $delimiter = ';';
        $buffer = '';
        $statements = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new \RuntimeException('Gagal membaca schema dump: ' . $path);
        }

        foreach ($lines as $line) {
            $trimmedLeft = ltrim($line);

            if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $matches) === 1) {
                $delimiter = $matches[1];
                continue;
            }

            if ($buffer === '' && trim($line) === '') {
                continue;
            }

            if (preg_match('/^\s*--/', $trimmedLeft) === 1) {
                continue;
            }

            if (preg_match('/^\s*\/\*(?!\!)/', $trimmedLeft) === 1) {
                continue;
            }

            $buffer .= $line . PHP_EOL;

            if (!$this->bufferEndsWithDelimiter($buffer, $delimiter)) {
                continue;
            }

            $statement = $this->trimStatementDelimiter($buffer, $delimiter);
            $buffer = '';

            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    private function bufferEndsWithDelimiter(string $buffer, string $delimiter): bool
    {
        $trimmed = rtrim($buffer);

        return $trimmed !== '' && str_ends_with($trimmed, $delimiter);
    }

    private function trimStatementDelimiter(string $buffer, string $delimiter): string
    {
        $trimmed = rtrim($buffer);
        $withoutDelimiter = substr($trimmed, 0, strlen($trimmed) - strlen($delimiter));

        return trim($withoutDelimiter);
    }

    private function shouldSkipSchemaStatement(string $statement): bool
    {
        $normalized = strtolower(trim($statement));

        if ($normalized === '') {
            return true;
        }

        if (str_starts_with($normalized, '/*!')) {
            return true;
        }

        if (str_starts_with($normalized, 'set ')) {
            return true;
        }

        if (str_starts_with($normalized, 'lock tables') || str_starts_with($normalized, 'unlock tables')) {
            return true;
        }

        if (str_starts_with($normalized, 'drop table if exists `migrations`')) {
            return true;
        }

        if (str_starts_with($normalized, 'create table `migrations`')) {
            return true;
        }

        if (str_starts_with($normalized, 'insert into `migrations`')) {
            return true;
        }

        if (str_contains($normalized, 'trigger trg_dld_after_') || str_contains($normalized, 'trigger trg_smp_after_')) {
            return true;
        }

        return false;
    }

    private function normalizeStatementForSqlite(string $statement): string
    {
        $normalized = $statement;

        $normalized = preg_replace('/\s+ENGINE=[^;]+$/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+DEFAULT CHARSET=[^)\s]+/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+COLLATE=[^)\s]+/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+CHARACTER SET\s+\w+(?:\s+COLLATE\s+\w+)?/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bunsigned\b/i', '', $normalized) ?? $normalized;
        $normalized = str_ireplace('DEFAULT current_timestamp() ON UPDATE current_timestamp()', 'DEFAULT CURRENT_TIMESTAMP', $normalized);
        $normalized = str_ireplace('DEFAULT current_timestamp()', 'DEFAULT CURRENT_TIMESTAMP', $normalized);

        if (!preg_match('/^CREATE TABLE `([^`]+)`/i', trim($normalized), $matches)) {
            return trim($normalized);
        }

        $table = $matches[1];
        $autoIncrementColumn = self::SQLITE_AUTO_INCREMENT_COLUMNS[$table] ?? null;

        $lines = preg_split('/\R/', $normalized);
        if ($lines === false) {
            return trim($normalized);
        }

        $filtered = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if ($autoIncrementColumn !== null && preg_match('/^`' . preg_quote($autoIncrementColumn, '/') . '`/i', $trimmed) === 1 && str_contains($trimmed, 'AUTO_INCREMENT')) {
                $filtered[] = '  `' . $autoIncrementColumn . '` integer primary key autoincrement,';
                continue;
            }

            if ($autoIncrementColumn !== null && preg_match('/^PRIMARY KEY\b/i', $trimmed) === 1) {
                continue;
            }

            if (preg_match('/^(?:UNIQUE\s+)?KEY\b/i', $trimmed) === 1) {
                continue;
            }

            if (preg_match('/^INDEX\b/i', $trimmed) === 1) {
                continue;
            }

            if (preg_match('/^FULLTEXT\b/i', $trimmed) === 1 || preg_match('/^SPATIAL\b/i', $trimmed) === 1) {
                continue;
            }

            $filtered[] = $line;
        }

        $normalized = implode(PHP_EOL, $filtered);
        $normalized = preg_replace('/,\s*\n\s*\)/', "\n)", $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if (!str_ends_with($normalized, ';')) {
            $normalized .= ';';
        }

        return $normalized;
    }

    private function createDashboardSimpananSnapshotTables(): void
    {
        if (!Schema::hasTable('dashboard_simpanan_snapshots')) {
            Schema::create('dashboard_simpanan_snapshots', function (Blueprint $table) {
                $table->string('uniqueid_dss', 191)->primary();
                $table->date('snapshot_period');
                $table->decimal('total_balance', 24, 2)->default(0);
                $table->unsignedBigInteger('account_count')->default(0);
                $table->unsignedBigInteger('cif_count')->default(0);
                $table->unsignedInteger('branch_count')->default(0);
                $table->unsignedInteger('unit_count')->default(0);
                $table->decimal('tabungan_balance', 24, 2)->default(0);
                $table->decimal('giro_balance', 24, 2)->default(0);
                $table->decimal('other_balance', 24, 2)->default(0);
                $table->string('top_branch_label', 150)->nullable();
                $table->decimal('top_branch_balance', 24, 2)->default(0);
                $table->unsignedBigInteger('source_row_count')->default(0);
                $table->timestamp('source_updated_at')->nullable();
                $table->timestamps();
            });
            Schema::table('dashboard_simpanan_snapshots', function (Blueprint $table) {
                $table->unique('snapshot_period', 'uq_dss_snapshot_period');
            });
        }

        if (!Schema::hasTable('dashboard_simpanan_branch_snapshots')) {
            Schema::create('dashboard_simpanan_branch_snapshots', function (Blueprint $table) {
                $table->string('uniqueid_dsbs', 191)->primary();
                $table->date('snapshot_period');
                $table->string('kantor_cabang', 150);
                $table->decimal('total_balance', 24, 2)->default(0);
                $table->unsignedTinyInteger('rank_order')->default(0);
                $table->timestamps();
            });
        }

        $this->addIndexIfMissing('dashboard_simpanan_snapshots', 'idx_dss_source_updated_at', ['source_updated_at']);
        $this->addUniqueIfMissing('dashboard_simpanan_branch_snapshots', 'uq_dsbs_period_branch', ['snapshot_period', 'kantor_cabang']);
        $this->addIndexIfMissing('dashboard_simpanan_branch_snapshots', 'idx_dsbs_period_rank', ['snapshot_period', 'rank_order']);
        $this->addIndexIfMissing('dashboard_simpanan_branch_snapshots', 'idx_dsbs_period_balance', ['snapshot_period', 'total_balance']);
    }

    private function ensureInputAndBodColumns(): void
    {
        if (Schema::hasTable('input_rekanan') && !Schema::hasColumn('input_rekanan', 'periode')) {
            Schema::table('input_rekanan', function (Blueprint $table) {
                $table->date('periode')->nullable()->after('id');
            });
        }

        if (Schema::hasTable('bod_boc') && !Schema::hasColumn('bod_boc', 'periode')) {
            Schema::table('bod_boc', function (Blueprint $table) {
                $table->date('periode')->nullable()->after('id');
            });
        }
    }

    private function ensureImportJobsSchema(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (!Schema::hasTable('import_jobs')) {
            return;
        }

        Schema::table('import_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('import_jobs', 'total_success')) {
                $table->unsignedInteger('total_success')->default(0)->after('total_files');
            }

            if (!Schema::hasColumn('import_jobs', 'total_failed')) {
                $table->unsignedInteger('total_failed')->default(0)->after('total_success');
            }

            if (!Schema::hasColumn('import_jobs', 'job_fingerprint')) {
                $table->string('job_fingerprint', 64)->nullable()->after('created_by');
            }
        });

        $this->addIndexIfPossible('import_jobs', 'idx_import_jobs_status_updated_at', ['status', 'updated_at']);
        $this->addIndexIfPossible('import_jobs', 'idx_import_jobs_created_by_status_created_at', ['created_by', 'status', 'created_at']);
        $this->addIndexIfPossible('import_jobs', 'idx_import_jobs_report_created_at', ['id_report', 'created_at']);
        $this->addIndexIfPossible('import_jobs', 'idx_import_jobs_job_fingerprint', ['job_fingerprint'], true);
    }

    private function ensureNamaReportMetadataColumns(): void
    {
        if (!Schema::hasTable('nama_report')) {
            return;
        }

        Schema::table('nama_report', function (Blueprint $table) {
            if (!Schema::hasColumn('nama_report', 'import_controller')) {
                $table->string('import_controller', 150)->nullable()->after('active');
            }

            if (!Schema::hasColumn('nama_report', 'requires_manual_periode')) {
                $table->boolean('requires_manual_periode')->default(false)->after('import_controller');
            }

            if (!Schema::hasColumn('nama_report', 'manual_periode_type')) {
                $table->string('manual_periode_type', 20)->nullable()->after('requires_manual_periode');
            }

            if (!Schema::hasColumn('nama_report', 'manual_periode_label')) {
                $table->string('manual_periode_label', 100)->nullable()->after('manual_periode_type');
            }

            if (!Schema::hasColumn('nama_report', 'manual_periode_help')) {
                $table->string('manual_periode_help', 255)->nullable()->after('manual_periode_label');
            }
        });
    }

    private function ensureRuntimeIndexes(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        $this->addIndexIfPossible('input_rekanan', 'idx_input_rekanan_periode', ['periode']);
        $this->addIndexIfPossible('input_rekanan', 'idx_input_rekanan_cif', ['cif']);
        $this->addIndexIfPossible('input_rekanan', 'idx_input_rekanan_periode_cif', ['periode', 'cif']);

        $this->addIndexIfPossible('bod_boc', 'idx_bod_boc_periode', ['periode']);
        $this->addIndexIfPossible('bod_boc', 'idx_bod_boc_cif', ['cif']);
        $this->addIndexIfPossible('bod_boc', 'idx_bod_boc_periode_cif', ['periode', 'cif']);

        $this->addIndexIfPossible(
            'brilink_web_laporan_summary_transaksi_brilink_web',
            'idx_brilink_summary_periode_cabang',
            ['periode', 'cabang']
        );

        $this->addIndexIfPossible('casa_brilink_web', 'idx_casa_web_periode_mbdesc', ['periode', 'mbdesc']);
        $this->addIndexIfPossible('casa_brilink_edc', 'idx_casa_edc_periode_mbdesc', ['periode', 'mbdesc']);

        $this->addIndexIfPossible('simpanan_multipn', 'idx_smp_posisi_updated', ['posisi', 'updated_at']);
        $this->addIndexIfPossible('daily_loan_dinamis', 'idx_dld_delete_scope', ['periode', 'cabang1', 'uniqueid_namareport']);
        $this->addIndexIfPossible('lw325_ph', 'idx_lw325ph_delete_scope', ['periode', 'kanca', 'uniqueid_namareport']);
        $this->addIndexIfPossible('performance_pis_per_produk', 'idx_pppp_delete_scope', ['posisi', 'kanca', 'uniqueid_namareport']);
    }

    private function seedNamaReport(): void
    {
        if (!Schema::hasTable('nama_report')) {
            return;
        }

        $now = now();

        foreach (self::NAMA_REPORT_SEED as $row) {
            DB::table('nama_report')->updateOrInsert(
                ['id_report' => $row['id_report']],
                [
                    'nama_report' => $row['nama_report'],
                    'table_name' => $row['table_name'],
                    'active' => $row['active'],
                    'import_controller' => $row['import_controller'],
                    'requires_manual_periode' => $row['requires_manual_periode'],
                    'manual_periode_type' => $row['manual_periode_type'],
                    'manual_periode_label' => $row['manual_periode_label'],
                    'manual_periode_help' => $row['manual_periode_help'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function createSnapshotInvalidationTriggers(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        $this->dropSnapshotInvalidationTriggers();

        if (Schema::hasTable('daily_loan_dinamis')) {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_dld_after_ins_invalidate_snapshots
AFTER INSERT ON daily_loan_dinamis
FOR EACH ROW
BEGIN
    IF NEW.periode IS NOT NULL THEN
        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
    END IF;
END
SQL);

            DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_dld_after_upd_invalidate_snapshots
AFTER UPDATE ON daily_loan_dinamis
FOR EACH ROW
BEGIN
    IF NEW.periode IS NOT NULL THEN
        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
    END IF;

    IF OLD.periode IS NOT NULL AND (NEW.periode IS NULL OR OLD.periode <> NEW.periode) THEN
        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
    END IF;
END
SQL);

            DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_dld_after_del_invalidate_snapshots
AFTER DELETE ON daily_loan_dinamis
FOR EACH ROW
BEGIN
    IF OLD.periode IS NOT NULL THEN
        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
    END IF;
END
SQL);
        }

        if (Schema::hasTable('simpanan_multipn')) {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_smp_after_ins_invalidate_snapshots
AFTER INSERT ON simpanan_multipn
FOR EACH ROW
BEGIN
    IF NEW.posisi IS NOT NULL THEN
        DELETE FROM rekening_dormant_snapshots WHERE posisi = NEW.posisi;
        DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = NEW.posisi;
    END IF;
END
SQL);

            DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_smp_after_upd_invalidate_snapshots
AFTER UPDATE ON simpanan_multipn
FOR EACH ROW
BEGIN
    IF NEW.posisi IS NOT NULL THEN
        DELETE FROM rekening_dormant_snapshots WHERE posisi = NEW.posisi;
        DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = NEW.posisi;
    END IF;

    IF OLD.posisi IS NOT NULL AND (NEW.posisi IS NULL OR OLD.posisi <> NEW.posisi) THEN
        DELETE FROM rekening_dormant_snapshots WHERE posisi = OLD.posisi;
        DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = OLD.posisi;
    END IF;
END
SQL);

            DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_smp_after_del_invalidate_snapshots
AFTER DELETE ON simpanan_multipn
FOR EACH ROW
BEGIN
    IF OLD.posisi IS NOT NULL THEN
        DELETE FROM rekening_dormant_snapshots WHERE posisi = OLD.posisi;
        DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = OLD.posisi;
    END IF;
END
SQL);
        }
    }

    private function dropSnapshotInvalidationTriggers(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach ([
            'trg_dld_after_ins_invalidate_snapshots',
            'trg_dld_after_upd_invalidate_snapshots',
            'trg_dld_after_del_invalidate_snapshots',
            'trg_smp_after_ins_invalidate_snapshots',
            'trg_smp_after_upd_invalidate_snapshots',
            'trg_smp_after_del_invalidate_snapshots',
        ] as $trigger) {
            DB::unprepared('DROP TRIGGER IF EXISTS ' . $trigger);
        }
    }

    private function addIndexIfPossible(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->hasIndex($table, $indexName)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function addUniqueIfMissing(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->unique($columns, $indexName);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        $result = DB::select(
            'SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = ?',
            [$indexName]
        );

        return !empty($result);
    }
};
