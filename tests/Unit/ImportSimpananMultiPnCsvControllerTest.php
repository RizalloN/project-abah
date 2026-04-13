<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportSimpananMultiPnCsvController;
use App\Services\Import\ImportCleanupService;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class ImportSimpananMultiPnCsvControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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

        $this->assertStringContainsString('CASE', $planSql);
        $this->assertStringContainsString('DECIMAL(24,2)', $planSql);
        $this->assertStringContainsString("REGEXP '^-?[0-9]+(,[0-9]+)?$'", $planSql);
        $this->assertSame(383158180, (int) ($plan['source_balance_total_cents'] ?? 0));
    }

    public function test_prepare_simpanan_direct_load_source_skips_duplicates_and_malformed_rows(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

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

        $this->assertSame(3, $result['written_rows']);
        $this->assertGreaterThanOrEqual(2, $result['skipped_count']);
        $this->assertGreaterThanOrEqual(1, $result['duplicate_count']);
        $this->assertTrue((bool) ($result['normalized'] ?? false));
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

    public function test_staged_direct_load_fallback_is_blocked_for_local_infile_errors(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

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

    public function test_staged_direct_load_fallback_is_allowed_for_filter_based_reasons(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        $this->assertTrue($this->invokeMethod($controller, 'shouldUseStagedDirectLoadFallback', [
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
