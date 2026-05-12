<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportSimpananMultiPnCsvController;
use App\Http\Controllers\Import\ImportIndexController;
use App\Services\Import\ImportCleanupService;
use App\Services\Import\ImportDuplicateGuardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class ImportSimpananMultiPnCsvControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_csv_physical_data_rows_are_counted_exactly_for_large_preview_totals(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();
        $csvPath = storage_path('framework/testing/simpanan_exact_row_count.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\r\n", [
            'No;Posisi;CIFNO;No Rekening;Status;Jenis Simpanan;Saldo IDR',
            '1;28-04-2026;SRVG283;636001000001;9;TABUNGAN;1000',
            '2;28-04-2026;SRVG284;636001000002;9;GIRO;2000',
            '3;28-04-2026;SRVG285;636001000003;9;DEPOSITO;3000',
        ]) . "\r\n");

        try {
            $this->assertSame(3, $this->invokeMethod($controller, 'estimateCsvPhysicalDataRows', [$csvPath]));
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_direct_csv_load_plan_keeps_posisi_assignment_in_set_clause(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();
        config()->set('import.direct_load.validation_sample_rows', 5000);

        Schema::shouldReceive('getColumnListing')
            ->once()
            ->with('simpanan_multipn')
            ->andReturn([
                'id',
                'posisi',
                'cifno',
                'no_rekening',
                'status',
                'jenis_simpanan',
                'saldo_idr',
                'created_at',
                'updated_at',
            ]);

        $csvPath = storage_path('framework/testing/simpanan_fast_import_test.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'No;Posisi;CIFNO;No Rekening;Status;Jenis Simpanan;Saldo IDR',
            '1;04-04-2026;PQ32242;6,36001E+14;9;TABUNGAN;500',
        ]));

        $plan = [];
        try {
            $plan = $this->invokeMethod($controller, 'buildDirectCsvLoadPlan', [
                $csvPath,
                ['No', 'Posisi', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
                [0, 1, 2, 3, 4, 5, 6],
            ]);
        } finally {
            @unlink($csvPath);
            if (!empty($plan['cleanup_path'] ?? '') && file_exists((string) $plan['cleanup_path'])) {
                @unlink((string) $plan['cleanup_path']);
            }
        }

        $posisiClause = collect($plan['set_clauses'] ?? [])->first(fn (string $clause) => str_contains($clause, '`posisi`'));

        $this->assertNotNull($posisiClause);
        $this->assertStringContainsString('`posisi` = CASE', $posisiClause);
    }

    public function test_direct_csv_load_plan_honors_configured_validation_sample_size(): void
    {
        $controller = new class extends ImportSimpananMultiPnCsvController {
            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return 'simpanan_multipn';
            }
        };
        config()->set('import.direct_load.validation_sample_rows', 5000);

        Schema::shouldReceive('getColumnListing')
            ->once()
            ->with('simpanan_multipn')
            ->andReturn([
                'id',
                'posisi',
                'cifno',
                'no_rekening',
                'status',
                'jenis_simpanan',
                'saldo_idr',
                'created_at',
                'updated_at',
            ]);

        $csvPath = storage_path('framework/testing/simpanan_fast_import_validation_sample.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'No;Posisi;CIFNO;No Rekening;Status;Jenis Simpanan;Saldo IDR',
            '1;04-04-2026;PQ32242;636001000001;9;TABUNGAN;500',
            '2;04-04-2026;PQ32243;636001000002;9',
        ]));

        $plan = [];
        try {
            $plan = $this->invokeMethod($controller, 'buildDirectCsvLoadPlan', [
                $csvPath,
                ['No', 'Posisi', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
                [0, 1, 2, 3, 4, 5, 6],
            ]);
        } finally {
            @unlink($csvPath);
            if (!empty($plan['cleanup_path'] ?? '') && file_exists((string) $plan['cleanup_path'])) {
                @unlink((string) $plan['cleanup_path']);
            }
        }

        $this->assertIsArray($plan);
        $this->assertNotEmpty($plan['set_clauses'] ?? []);
    }

    public function test_raw_simpanan_validation_stops_after_configured_sample_limit(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();
        config()->set('import.direct_load.validation_sample_rows', 1);

        $csvPath = storage_path('framework/testing/simpanan_raw_sample_limit.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'No;Posisi;CIFNO;No Rekening;Status;Jenis Simpanan;Saldo IDR',
            '1;04-04-2026;PQ32242;636001000001;9;TABUNGAN;500',
            '2;04-04-2026;PQ32243;636001000002;9',
        ]));

        try {
            $result = $this->invokeMethod($controller, 'inspectRawSimpananMultiPnDirectLoadSource', [
                $csvPath,
                ';',
            ]);
        } finally {
            @unlink($csvPath);
        }

        $this->assertTrue($result['usable'] ?? false);
        $this->assertSame(1, $result['sampled_rows'] ?? null);
        $this->assertGreaterThanOrEqual(1, $result['total_rows'] ?? 0);
        $this->assertSame(['2026-04-04'], $result['period_hints'] ?? []);
    }

    public function test_direct_csv_load_plan_embeds_import_batch_timestamp_for_fast_snapshot_scope_resolution(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        Schema::shouldReceive('getColumnListing')
            ->once()
            ->with('simpanan_multipn')
            ->andReturn([
                'id',
                'posisi',
                'cifno',
                'no_rekening',
                'status',
                'jenis_simpanan',
                'saldo_idr',
                'created_at',
                'updated_at',
            ]);

        $csvPath = storage_path('framework/testing/simpanan_fast_import_batch_marker.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'No;Posisi;CIFNO;No Rekening;Status;Jenis Simpanan;Saldo IDR',
            '1;04-04-2026;PQ32242;636001000001;9;TABUNGAN;500',
        ]));

        $plan = [];
        try {
            $plan = $this->invokeMethod($controller, 'buildDirectCsvLoadPlan', [
                $csvPath,
                ['No', 'Posisi', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
                [0, 1, 2, 3, 4, 5, 6],
            ]);
        } finally {
            @unlink($csvPath);
            if (!empty($plan['cleanup_path'] ?? '') && file_exists((string) $plan['cleanup_path'])) {
                @unlink((string) $plan['cleanup_path']);
            }
        }

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($plan['import_batch_timestamp'] ?? ''));
        $this->assertContains("`created_at` = '" . $plan['import_batch_timestamp'] . "'", $plan['set_clauses']);
        $this->assertContains("`updated_at` = '" . $plan['import_batch_timestamp'] . "'", $plan['set_clauses']);
    }

    public function test_direct_csv_load_plan_uses_decimal_safe_saldo_expression_and_source_balance_crosscheck(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        Schema::shouldReceive('getColumnListing')
            ->once()
            ->with('simpanan_multipn')
            ->andReturn([
                'id',
                'posisi',
                'cifno',
                'no_rekening',
                'status',
                'jenis_simpanan',
                'saldo_idr',
                'created_at',
                'updated_at',
            ]);

        $csvPath = storage_path('framework/testing/simpanan_fast_import_decimal_test.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'No;Posisi;CIFNO;No Rekening;Status;Jenis Simpanan;Saldo IDR',
            '1;04-04-2026;PQ32242;636001000001;9;TABUNGAN;3831081,8',
            '2;04-04-2026;PQ32243;636001000002;9;TABUNGAN;500',
        ]));

        $plan = [];
        try {
            $plan = $this->invokeMethod($controller, 'buildDirectCsvLoadPlan', [
                $csvPath,
                ['No', 'Posisi', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
                [0, 1, 2, 3, 4, 5, 6],
            ]);
        } finally {
            @unlink($csvPath);
            if (!empty($plan['cleanup_path'] ?? '') && file_exists((string) $plan['cleanup_path'])) {
                @unlink((string) $plan['cleanup_path']);
            }
        }

        $planSql = implode("\n", (array) ($plan['set_clauses'] ?? []));

        $this->assertStringContainsString('`saldo_idr` = CASE', $planSql);
        $this->assertStringContainsString('CASE', $planSql);
        $this->assertStringContainsString('DECIMAL(24,2)', $planSql);
        $this->assertStringContainsString('CHAR(13)', $planSql);
        $this->assertStringContainsString("REGEXP '^-?[0-9]+(,[0-9]+)?$'", $planSql);
        $this->assertSame(383158180, (int) ($plan['source_balance_total_cents'] ?? 0));
    }

    public function test_direct_csv_load_plan_tracks_legacy_unique_id_column_alias(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        Schema::shouldReceive('getColumnListing')
            ->once()
            ->with('simpanan_multipn')
            ->andReturn([
                'id',
                'uniqueid_SimoPN',
                'posisi',
                'cifno',
                'no_rekening',
                'status',
                'jenis_simpanan',
                'saldo_idr',
                'created_at',
                'updated_at',
            ]);

        $csvPath = storage_path('framework/testing/simpanan_fast_import_legacy_unique_id.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'No;Posisi;CIFNO;No Rekening;Status;Jenis Simpanan;Saldo IDR',
            '1;04-04-2026;PQ32242;636001000001;9;TABUNGAN;500',
        ]));

        $plan = [];
        try {
            $plan = $this->invokeMethod($controller, 'buildDirectCsvLoadPlan', [
                $csvPath,
                ['No', 'Posisi', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
                [0, 1, 2, 3, 4, 5, 6],
            ]);
        } finally {
            @unlink($csvPath);
            if (!empty($plan['cleanup_path'] ?? '') && file_exists((string) $plan['cleanup_path'])) {
                @unlink((string) $plan['cleanup_path']);
            }
        }

        $planSql = implode("\n", (array) ($plan['set_clauses'] ?? []));

        $this->assertSame('uniqueid_SimoPN', $plan['unique_id_column'] ?? null);
        $this->assertStringContainsString('`uniqueid_SimoPN` = CONCAT(', $planSql);
        $this->assertStringContainsString('SHA1(CONCAT_WS', $planSql);
        $this->assertStringNotContainsString('REPLACE(UUID()', $planSql);
    }

    public function test_simpanan_job_metadata_persists_content_hash_column_for_duplicate_guard(): void
    {
        if (!Schema::hasTable('import_jobs')) {
            Schema::create('import_jobs', function ($table): void {
                $table->id();
                $table->integer('id_report')->nullable();
                $table->string('status')->nullable();
                $table->string('job_content_hash')->nullable();
                $table->text('job_context')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('import_jobs', 'job_content_hash')) {
            $this->markTestSkipped('import_jobs.job_content_hash column is not available in this fixture.');
        }

        $jobId = DB::table('import_jobs')->insertGetId([
            'id_report' => 9,
            'status' => 'processing',
            'job_content_hash' => null,
            'job_context' => json_encode(['table_name' => 'simpanan_multipn']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controller = new ImportSimpananMultiPnCsvController();
        $this->invokeMethod($controller, 'storeSimpananMultiPnJobMetadata', [$jobId, [
            'content_hash' => str_repeat('a', 64),
            'period_hints' => ['2025-05-31'],
            'branch_hints' => ['00045 -- KC Madiun(Konsolidasi-MB)'],
            'table_name' => 'simpanan_multipn',
        ]]);

        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        $context = json_decode((string) $job->job_context, true);

        $this->assertSame(str_repeat('a', 64), $job->job_content_hash);
        $this->assertSame(str_repeat('a', 64), $context['content_hash'] ?? null);
        $this->assertSame(['2025-05-31'], $context['period_hints'] ?? []);
    }

    public function test_content_hash_column_availability_is_not_stuck_on_stale_cache(): void
    {
        cache()->forever('import_guard:content_hash_col_exists', false);

        $this->assertSame(
            Schema::hasColumn('import_jobs', 'job_content_hash'),
            app(ImportDuplicateGuardService::class)->isContentHashColumnAvailable()
        );

        cache()->forget('import_guard:content_hash_col_exists');
    }

    public function test_direct_csv_load_plan_collects_normalized_period_hints_from_source(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        Schema::shouldReceive('getColumnListing')
            ->once()
            ->with('simpanan_multipn')
            ->andReturn([
                'id',
                'uniqueid_SMPN',
                'posisi',
                'cifno',
                'no_rekening',
                'status',
                'jenis_simpanan',
                'saldo_idr',
                'created_at',
                'updated_at',
            ]);

        $csvPath = storage_path('framework/testing/simpanan_fast_import_period_hints.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'No;Posisi;CIFNO;No Rekening;Status;Jenis Simpanan;Saldo IDR',
            '1;20/04/2026;PQ32242;636001000001;9;TABUNGAN;500',
            '2;2026-04-20;PQ32243;636001000002;9;GIRO;700',
            '3;21-04-2026;PQ32244;636001000003;9;DEPOSITO;900',
        ]));

        $plan = [];
        try {
            $plan = $this->invokeMethod($controller, 'buildDirectCsvLoadPlan', [
                $csvPath,
                ['No', 'Posisi', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
                [0, 1, 2, 3, 4, 5, 6],
            ]);
        } finally {
            @unlink($csvPath);
            if (!empty($plan['cleanup_path'] ?? '') && file_exists((string) $plan['cleanup_path'])) {
                @unlink((string) $plan['cleanup_path']);
            }
        }

        $this->assertSame([
            '2026-04-20',
            '2026-04-21',
        ], $plan['period_hints'] ?? []);
    }

    public function test_simpanan_file_fingerprint_uses_content_not_storage_filename(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();
        $firstPath = storage_path('framework/testing/simpanan_fingerprint_a.csv');
        $secondPath = storage_path('framework/testing/simpanan_fingerprint_b.csv');
        if (!is_dir(dirname($firstPath))) {
            @mkdir(dirname($firstPath), 0777, true);
        }

        $content = implode("\n", [
            'POSISI;KANTOR_CABANG;CIFNO;NO_REKENING',
            '04-04-2026;KC MADIUN;A001;1001',
        ]);

        file_put_contents($firstPath, $content);
        file_put_contents($secondPath, $content);

        try {
            $firstHash = $this->invokeMethod($controller, 'calculateFileFingerprint', [$firstPath]);
            $secondHash = $this->invokeMethod($controller, 'calculateFileFingerprint', [$secondPath]);
        } finally {
            @unlink($firstPath);
            @unlink($secondPath);
        }

        $this->assertSame($firstHash, $secondHash);
        $this->assertSame(64, strlen($firstHash));
    }

    public function test_simpanan_scope_collection_reads_periods_and_kantor_cabang(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();
        $csvPath = storage_path('framework/testing/simpanan_scope_hints.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'POSISI;KANTOR_CABANG;CIFNO;NO_REKENING',
            '04-04-2026;KC MADIUN;A001;1001',
            '2026-04-04;KC MADIUN;A002;1002',
            '05/04/2026;KC NGAWI;A003;1003',
        ]));

        try {
            $scopes = $this->invokeMethod($controller, 'collectSimpananMultiPnSnapshotScopes', [$csvPath]);
        } finally {
            @unlink($csvPath);
        }

        $this->assertSame(['2026-04-04', '2026-04-05'], $scopes['periods'] ?? []);
        $this->assertSame(['KC MADIUN', 'KC NGAWI'], $scopes['branches'] ?? []);
    }

    public function test_prepare_simpanan_direct_load_source_preserves_duplicates_and_skips_malformed_rows(): void
    {
        $controller = new class extends ImportSimpananMultiPnCsvController {
            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return 'simpanan_multipn';
            }
        };

        $csvPath = storage_path('framework/testing/simpanan_validator_test.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'POSISI;CIFNO;NO_REKENING;JENIS_SIMPANAN;SALDO_IDR;STATUS',
            '04-04-2026;CIF001;1234567890;TABUNGAN;1000;AKTIF',
            '04-04-2026;CIF999;1234567890;GIRO;2000;AKTIF',
            'BROKEN,ROW,WITH,TOO,MANY,COLUMNS',
            '04-04-2026;CIF002;1234567891;GIRO;2500;AKTIF',
            '05-04-2026;CIF001;1234567890;TABUNGAN;3000;AKTIF',
        ]) . "\n");

        $result = [];
        try {
            $result = $this->invokeMethod($controller, 'prepareSimpananMultiPnDirectLoadSource', [$csvPath, ';']);
        } finally {
            @unlink($csvPath);
            if (!empty($result['path'] ?? '') && file_exists((string) $result['path']) && ($result['cleanup'] ?? false)) {
                @unlink((string) $result['path']);
            }
        }

        $this->assertSame(4, $result['written_rows']);
        $this->assertSame(0, $result['duplicate_count']);
        $this->assertSame(1, $result['skipped_count']);
        $this->assertTrue((bool) ($result['normalized'] ?? false));
    }

    public function test_prepare_simpanan_direct_load_source_removes_exact_duplicate_business_rows(): void
    {
        $controller = new class extends ImportSimpananMultiPnCsvController {
            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return 'simpanan_multipn';
            }

            protected function stageSimpananMultiPnCsvWithPolars(
                ?callable $send,
                string $csvPath,
                ?string $delimiter = null,
                array $activeFilters = [],
                int $jobId = 0,
                array $selectedColumns = [],
                array $normalizedHeaders = []
            ): ?array {
                return null;
            }
        };

        $csvPath = storage_path('framework/testing/simpanan_validator_duplicate_rows.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'No;POSISI;CIFNO;NO_REKENING;JENIS_SIMPANAN;SALDO_IDR;STATUS',
            '1;04-04-2026;CIF001;1234567890;TABUNGAN;1000;AKTIF',
            '2;04/04/2026;CIF001;1234567890;TABUNGAN;1000.00;AKTIF',
            'BROKEN,ROW,WITH,TOO,MANY,COLUMNS',
        ]) . "\n");

        $result = [];
        try {
            $result = $this->invokeMethod($controller, 'prepareSimpananMultiPnDirectLoadSource', [$csvPath, ';']);
        } finally {
            @unlink($csvPath);
            if (!empty($result['path'] ?? '') && file_exists((string) $result['path']) && ($result['cleanup'] ?? false)) {
                @unlink((string) $result['path']);
            }
        }

        $this->assertSame(1, $result['written_rows']);
        $this->assertSame(1, $result['duplicate_count']);
        $this->assertSame(2, $result['skipped_count']);
        $this->assertTrue((bool) ($result['normalized'] ?? false));
    }

    public function test_simpanan_load_slot_recheck_blocks_existing_period_and_branch(): void
    {
        if (!Schema::hasTable('simpanan_multipn')) {
            Schema::create('simpanan_multipn', function ($table): void {
                $table->string('uniqueid_SMPN')->primary();
                $table->date('posisi')->nullable();
                $table->string('kantor_cabang')->nullable();
                $table->timestamps();
            });
        }

        DB::table('simpanan_multipn')->delete();

        DB::table('simpanan_multipn')->insert([
            'uniqueid_SMPN' => 'existing-row',
            'posisi' => '2026-04-04',
            'kantor_cabang' => 'KC MADIUN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah ada di tabel simpanan_multipn');

        $controller = new ImportSimpananMultiPnCsvController();
        $this->invokeMethod($controller, 'assertSimpananMultiPnLoadSlotsEmpty', [[
            'period_hints' => ['2026-04-04'],
            'branch_hints' => ['KC MADIUN'],
        ]]);
    }

    public function test_duplicate_cleanup_query_uses_window_ranked_rows(): void
    {
        $controller = app(ImportIndexController::class);

        [$deleteSql, $periodSql] = $this->invokeMethod($controller, 'buildDuplicateCleanupQueries', ['simpanan_multipn']);

        $this->assertStringContainsString('ROW_NUMBER() OVER', $deleteSql);
        $this->assertStringContainsString('PARTITION BY s.`posisi`', $deleteSql);
        $this->assertStringContainsString('DELETE t FROM `simpanan_multipn` t', $deleteSql);
        $this->assertStringContainsString('t.`uniqueid_SMPN` = d.duplicate_id', $deleteSql);
        $this->assertStringContainsString('SELECT DISTINCT period', $periodSql);
        $this->assertStringContainsString('duplicate_rank > 1', $periodSql);
    }

    public function test_prepare_simpanan_direct_load_source_normalizes_blank_lines_instead_of_using_raw_path(): void
    {
        $controller = new class extends ImportSimpananMultiPnCsvController {
            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return 'simpanan_multipn';
            }
        };

        $csvPath = storage_path('framework/testing/simpanan_validator_blank_lines.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'POSISI;CIFNO;NO_REKENING;JENIS_SIMPANAN;SALDO_IDR;STATUS',
            '20/04/2026;CIF001;1234567890;TABUNGAN;1000;AKTIF',
            '',
            '20/04/2026;CIF002;1234567891;GIRO;2500;AKTIF',
            '',
        ]) . "\n");

        $result = [];
        try {
            $result = $this->invokeMethod($controller, 'prepareSimpananMultiPnDirectLoadSource', [$csvPath, ';']);
        } finally {
            @unlink($csvPath);
            if (!empty($result['path'] ?? '') && file_exists((string) $result['path']) && ($result['cleanup'] ?? false)) {
                @unlink((string) $result['path']);
            }
        }

        $this->assertNotSame($csvPath, $result['path'] ?? '');
        $this->assertTrue((bool) ($result['cleanup'] ?? false));
        $this->assertTrue((bool) ($result['normalized'] ?? false));
        $this->assertSame(2, $result['written_rows'] ?? null);
    }

    public function test_prepare_simpanan_direct_load_source_treats_comma_delimited_staging_output_as_raw_source(): void
    {
        $controller = new class extends ImportSimpananMultiPnCsvController {
            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return 'simpanan_multipn';
            }
        };

        $csvPath = storage_path('framework/testing/simpanan_staged_output_comma.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'posisi,cifno,no_rekening,jenis_simpanan,saldo_idr',
            '2026-04-20,A001,636001000001,TABUNGAN,1000',
            '2026-04-20,A002,636001000002,GIRO,2500',
        ]) . "\n");

        $result = [];
        try {
            $result = $this->invokeMethod($controller, 'prepareSimpananMultiPnDirectLoadSource', [$csvPath, ',']);
        } finally {
            @unlink($csvPath);
            if (!empty($result['path'] ?? '') && file_exists((string) $result['path']) && ($result['cleanup'] ?? false)) {
                @unlink((string) $result['path']);
            }
        }

        $this->assertSame($csvPath, $result['path'] ?? null);
        $this->assertSame('raw', $result['backend'] ?? null);
        $this->assertFalse((bool) ($result['cleanup'] ?? true));
        $this->assertSame(2, $result['written_rows'] ?? null);
        $this->assertSame(2, $result['total_rows'] ?? null);
    }

    public function test_direct_csv_load_bypasses_snapshot_invalidation_during_bulk_load(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();
        $pdo = new SpySnapshotFlagPdo();

        $result = $this->invokeMethod($controller, 'executeLoadDataWithSnapshotInvalidationBypassed', [
            $pdo,
            "LOAD DATA LOCAL INFILE '/tmp/simpanan.csv' INTO TABLE `simpanan_multipn`",
        ]);

        $this->assertSame(321, $result);
        $this->assertSame([
            'SET @skip_snapshot_invalidation = 1',
            "LOAD DATA LOCAL INFILE '/tmp/simpanan.csv' INTO TABLE `simpanan_multipn`",
            'SET @skip_snapshot_invalidation = NULL',
        ], $pdo->statements);
    }

    public function test_direct_csv_load_resets_snapshot_bypass_flag_after_failure(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();
        $pdo = new SpySnapshotFlagPdo(shouldThrowOnLoad: true);

        try {
            $this->invokeMethod($controller, 'executeLoadDataWithSnapshotInvalidationBypassed', [
                $pdo,
                "LOAD DATA LOCAL INFILE '/tmp/simpanan.csv' INTO TABLE `simpanan_multipn`",
            ]);
            $this->fail('Expected bulk load helper to rethrow the load failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated LOAD DATA failure.', $e->getMessage());
        }

        $this->assertSame([
            'SET @skip_snapshot_invalidation = 1',
            "LOAD DATA LOCAL INFILE '/tmp/simpanan.csv' INTO TABLE `simpanan_multipn`",
            'SET @skip_snapshot_invalidation = NULL',
        ], $pdo->statements);
    }

    public function test_staged_direct_load_fallback_is_disabled_for_all_reasons(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        $this->assertFalse($this->invokeMethod($controller, 'shouldUseStagedDirectLoadFallback', [
            '',
        ]));
        $this->assertFalse($this->invokeMethod($controller, 'shouldUseStagedDirectLoadFallback', [
            'LOCAL INFILE tidak aktif di MySQL/PDO. Menggunakan safe path queue.',
        ]));
        $this->assertFalse($this->invokeMethod($controller, 'shouldUseStagedDirectLoadFallback', [
            'Header import tidak tersedia. Menggunakan safe path queue.',
        ]));
        $this->assertFalse($this->invokeMethod($controller, 'shouldUseStagedDirectLoadFallback', [
            'File CSV tidak ditemukan di server.',
        ]));
    }

    public function test_staged_direct_load_fallback_is_disabled_for_filter_based_reasons(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        $this->assertFalse($this->invokeMethod($controller, 'shouldUseStagedDirectLoadFallback', [
            'Filtered import menggunakan safe path queue.',
        ]));
    }

    public function test_snapshot_period_collection_normalizes_and_deduplicates_values(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        $csvPath = storage_path('framework/testing/simpanan_snapshot_period_test.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'POSISI;CIFNO;NO_REKENING',
            '04-04-2026;A001;1001',
            '2026-04-04;A002;1002',
            '04/05/2026;A003;1003',
            '04-04-2026;A004;1004',
        ]));

        try {
            $periods = $this->invokeMethod($controller, 'collectSimpananMultiPnSnapshotPeriods', [$csvPath]);
        } finally {
            @unlink($csvPath);
        }

        $this->assertSame([
            '2026-04-04',
            '2026-05-04',
        ], $periods);
    }

    public function test_cleanup_dispatches_background_snapshot_jobs_for_each_detected_period(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        $cleanupService = Mockery::mock(ImportCleanupService::class);
        $cleanupService->shouldReceive('dispatchImportedJobSync')
            ->twice()
            ->with(42, 'simpanan_multipn', Mockery::type('string'), ImportSimpananMultiPnCsvController::class)
            ->andReturnNull();

        $jobCleanup = Mockery::mock(\App\Http\Controllers\Import\ImportCleanupController::class);
        $jobCleanup->shouldReceive('cleanupSuccessfulJobArtifacts')
            ->once()
            ->with(42, Mockery::type('array'))
            ->andReturnNull();

        app()->instance(ImportCleanupService::class, $cleanupService);
        app()->instance(\App\Http\Controllers\Import\ImportCleanupController::class, $jobCleanup);

        $csvPath = storage_path('framework/testing/simpanan_cleanup_dispatch_test.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'POSISI;CIFNO;NO_REKENING',
            '04-04-2026;A001;1001',
            '04-05-2026;A002;1002',
            '04-04-2026;A003;1003',
        ]));

        try {
            $this->invokeMethod($controller, 'cleanupSuccessfulImportArtifacts', [
                42,
                'relative/path.csv',
                $csvPath,
            ]);
        } finally {
            @unlink($csvPath);
        }

        $this->assertTrue(true);
    }

    public function test_cleanup_successful_import_artifacts_uses_provided_period_hints_without_rescanning_csv(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        $cleanupService = Mockery::mock(ImportCleanupService::class);
        $cleanupService->shouldReceive('dispatchImportedJobSync')
            ->once()
            ->with(55, 'simpanan_multipn', '2026-04-20', ImportSimpananMultiPnCsvController::class)
            ->andReturnNull();

        $jobCleanup = Mockery::mock(\App\Http\Controllers\Import\ImportCleanupController::class);
        $jobCleanup->shouldReceive('cleanupSuccessfulJobArtifacts')
            ->once()
            ->with(55, Mockery::type('array'))
            ->andReturnNull();

        app()->instance(ImportCleanupService::class, $cleanupService);
        app()->instance(\App\Http\Controllers\Import\ImportCleanupController::class, $jobCleanup);

        $csvPath = storage_path('framework/testing/simpanan_cleanup_no_rescan.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, 'BROKEN,CONTENT,SHOULD,NOT,BE,READ');

        try {
            $this->invokeMethod($controller, 'cleanupSuccessfulImportArtifacts', [
                55,
                'relative/path.csv',
                $csvPath,
                ['2026-04-20'],
                '2026-04-20 10:11:12',
            ]);
        } finally {
            @unlink($csvPath);
        }

        $this->assertTrue(true);
    }

    public function test_create_normalized_direct_load_csv_forwards_filters_and_job_metadata_to_polars_stage(): void
    {
        $controller = new class extends ImportSimpananMultiPnCsvController {
            public array $stageCall = [];

            protected function stageSimpananMultiPnCsvWithPolars(
                ?callable $send,
                string $csvPath,
                ?string $delimiter = null,
                array $activeFilters = [],
                int $jobId = 0,
                array $selectedColumns = [],
                array $normalizedHeaders = []
            ): ?array {
                $this->stageCall = [
                    'csvPath' => $csvPath,
                    'delimiter' => $delimiter,
                    'activeFilters' => $activeFilters,
                    'jobId' => $jobId,
                    'selectedColumns' => $selectedColumns,
                    'normalizedHeaders' => $normalizedHeaders,
                ];

                return [
                    'path' => 'staged-output.csv',
                    'cleanup' => false,
                    'normalized' => true,
                    'backend' => 'polars',
                    'written_rows' => 2,
                    'total_rows' => 2,
                ];
            }
        };

        $method = new ReflectionMethod(ImportSimpananMultiPnCsvController::class, 'createNormalizedSimpananMultiPnDirectLoadCsv');
        $method->setAccessible(true);

        $result = $method->invoke(
            $controller,
            '/tmp/input.csv',
            ';',
            null,
            ['posisi' => ['2026-04-20']]
        );

        $this->assertSame('staged-output.csv', $result['path'] ?? null);
        $this->assertSame(['posisi' => ['2026-04-20']], $controller->stageCall['activeFilters'] ?? null);
        $this->assertSame(0, $controller->stageCall['jobId'] ?? null);
    }

    public function test_direct_csv_load_plan_uses_optimized_metadata_without_rescanning_clean_stage(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        Schema::shouldReceive('getColumnListing')
            ->once()
            ->with('simpanan_multipn')
            ->andReturn([
                'id',
                'posisi',
                'cifno',
                'no_rekening',
                'jenis_simpanan',
                'saldo_idr',
                'created_at',
                'updated_at',
            ]);

        $method = new ReflectionMethod(ImportSimpananMultiPnCsvController::class, 'buildDirectCsvLoadPlan');
        $method->setAccessible(true);

        $csvPath = storage_path('framework/testing/simpanan_clean_stage_valid_rows.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'posisi;cifno;no_rekening;jenis_simpanan;saldo_idr',
            '2026-04-20;A001;636001000001;TABUNGAN;500',
            '2026-04-20;A002;636001000002;TABUNGAN;1000',
        ]));

        try {
            $plan = $method->invoke(
                $controller,
                $csvPath,
                ['posisi', 'cifno', 'no_rekening', 'jenis_simpanan', 'saldo_idr'],
                [0, 1, 2, 3, 4],
                null,
                [
                    'assume_clean_source' => true,
                    'delimiter' => ';',
                    'source_headers' => ['posisi', 'cifno', 'no_rekening', 'jenis_simpanan', 'saldo_idr'],
                    'written_rows' => 2,
                    'source_balance_total_cents' => 25000,
                    'period_hints' => ['2026-04-20'],
                    'backend' => 'polars',
                ]
            );
        } finally {
            @unlink($csvPath);
        }

        $this->assertSame($csvPath, $plan['source_path'] ?? null);
        $this->assertSame(['2026-04-20'], $plan['period_hints'] ?? []);
        $this->assertSame(25000, $plan['source_balance_total_cents'] ?? null);
        $this->assertSame(2, $plan['validation_written_rows'] ?? null);
        $this->assertSame('polars', $plan['validation_backend'] ?? null);
        $this->assertSame(['posisi', 'cifno', 'no_rekening', 'jenis_simpanan', 'saldo_idr'], $plan['source_headers'] ?? []);
        $this->assertNull($plan['cleanup_path'] ?? null);
    }

    public function test_direct_csv_load_plan_uses_prepared_source_metadata_without_reloading_csv(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        Schema::shouldReceive('getColumnListing')
            ->once()
            ->with('simpanan_multipn')
            ->andReturn([
                'id',
                'posisi',
                'cifno',
                'no_rekening',
                'status',
                'jenis_simpanan',
                'saldo_idr',
                'created_at',
                'updated_at',
            ]);

        $method = new ReflectionMethod(ImportSimpananMultiPnCsvController::class, 'buildDirectCsvLoadPlan');
        $method->setAccessible(true);

        $csvPath = storage_path('framework/testing/simpanan_prepared_source_invalid.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, 'BROKEN,CONTENT,SHOULD,NOT,BE,READ');

        try {
            $plan = $method->invoke(
                $controller,
                $csvPath,
                ['No', 'Posisi', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
                [0, 1, 2, 3, 4, 5, 6],
                null,
                [
                    'delimiter' => ';',
                    'prepared_source' => [
                        'path' => $csvPath,
                        'cleanup' => false,
                        'normalized' => false,
                        'backend' => 'raw',
                        'headers' => ['No', 'Posisi', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
                        'skipped_rows' => [],
                        'skipped_count' => 0,
                        'duplicate_count' => 0,
                        'written_rows' => 2,
                        'total_rows' => 2,
                        'period_hints' => ['2026-04-20'],
                    ],
                ]
            );
        } finally {
            @unlink($csvPath);
        }

        $this->assertSame($csvPath, $plan['source_path'] ?? null);
        $this->assertSame(['2026-04-20'], $plan['period_hints'] ?? []);
        $this->assertSame(2, $plan['validation_written_rows'] ?? null);
        $this->assertSame('raw', $plan['validation_backend'] ?? null);
    }

    public function test_direct_csv_load_plan_rejects_clean_stage_samples_when_rows_are_all_null(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        $csvPath = storage_path('framework/testing/simpanan_clean_stage_null_rows.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'posisi;cifno;no_rekening;jenis_simpanan;status;saldo_idr',
            ';;;;;',
            ';;;;;',
        ]));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('data null tidak masuk ke database');

            $method = new ReflectionMethod(ImportSimpananMultiPnCsvController::class, 'buildDirectCsvLoadPlan');
            $method->setAccessible(true);
            $method->invoke(
                $controller,
                $csvPath,
                ['posisi', 'cifno', 'no_rekening', 'jenis_simpanan', 'status', 'saldo_idr'],
                [0, 1, 2, 3, 4, 5],
                null,
                [
                    'assume_clean_source' => true,
                    'delimiter' => ';',
                    'source_headers' => ['posisi', 'cifno', 'no_rekening', 'jenis_simpanan', 'status', 'saldo_idr'],
                    'written_rows' => 2,
                    'backend' => 'polars',
                ]
            );
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_resolve_import_job_failure_message_falls_back_when_error_message_is_missing(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();
        $method = new ReflectionMethod(ImportSimpananMultiPnCsvController::class, 'resolveImportJobFailureMessage');
        $method->setAccessible(true);

        $job = (object) [
            'status' => 'failed',
            'message' => '',
        ];

        $message = $method->invoke($controller, $job, 'Fallback message');

        $this->assertSame('Fallback message', $message);
    }

    public function test_resolve_import_job_failure_message_prefers_existing_message_fields(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();
        $method = new ReflectionMethod(ImportSimpananMultiPnCsvController::class, 'resolveImportJobFailureMessage');
        $method->setAccessible(true);

        $job = (object) [
            'status' => 'failed',
            'error_message' => null,
            'message' => 'Import gagal karena validasi.',
        ];

        $message = $method->invoke($controller, $job, 'Fallback message');

        $this->assertSame('Import gagal karena validasi.', $message);
    }

    public function test_format_safe_import_failure_message_sanitizes_runtime_errors(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();
        $method = new ReflectionMethod(ImportSimpananMultiPnCsvController::class, 'formatSafeImportFailureMessage');
        $method->setAccessible(true);

        $message = $method->invoke(
            $controller,
            'Fast import CSV gagal: ',
            new \RuntimeException('Undefined variable $jobId (line 4076)')
        );

        $this->assertSame(
            'Fast import CSV gagal: Import gagal diproses karena kesalahan internal pada worker. Detail teknis sudah dicatat di log server.',
            $message
        );
    }

    private function invokeMethod(object $target, string $method, array $arguments)
    {
        $reflection = new ReflectionClass($target);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invokeArgs($target, $arguments);
    }
}

class SpySnapshotFlagPdo extends \PDO
{
    public array $statements = [];

    public function __construct(private readonly bool $shouldThrowOnLoad = false)
    {
    }

    public function exec(string $statement): int|false
    {
        $this->statements[] = $statement;

        if (str_starts_with($statement, 'LOAD DATA LOCAL INFILE')) {
            if ($this->shouldThrowOnLoad) {
                throw new \RuntimeException('Simulated LOAD DATA failure.');
            }

            return 321;
        }

        return 0;
    }
}
