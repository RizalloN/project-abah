<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportSimpananMultiPnCsvController;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class ImportSimpananMultiPnCsvControllerTest extends TestCase
{
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

        try {
            $plan = $this->invokeMethod($controller, 'buildDirectCsvLoadPlan', [
                $csvPath,
                ['No', 'Posisi', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
                [0, 1, 2, 3, 4, 5, 6],
            ]);
        } finally {
            @unlink($csvPath);
        }

        $posisiClause = collect($plan['set_clauses'] ?? [])->first(fn (string $clause) => str_contains($clause, '`posisi`'));

        $this->assertNotNull($posisiClause);
        $this->assertStringContainsString('`posisi` = CASE', $posisiClause);
    }

    public function test_direct_csv_load_plan_honors_configured_validation_sample_size(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();
        config()->set('import.direct_load.validation_sample_rows', 1);

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

        try {
            $plan = $this->invokeMethod($controller, 'buildDirectCsvLoadPlan', [
                $csvPath,
                ['No', 'Posisi', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
                [0, 1, 2, 3, 4, 5, 6],
            ]);
        } finally {
            @unlink($csvPath);
        }

        $this->assertIsArray($plan);
        $this->assertNotEmpty($plan['set_clauses'] ?? []);
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
