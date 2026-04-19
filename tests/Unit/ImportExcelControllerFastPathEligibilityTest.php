<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\MySqlBulkLoadService;
use Mockery;
use Tests\TestCase;

class ImportExcelControllerFastPathEligibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(storage_path('app/testing/daily_loan_fast_path.csv'));
        @unlink(storage_path('app/testing/lw325_validated.csv'));
        @unlink(storage_path('app/testing/lw325_invalidated.csv'));
        @unlink(storage_path('app/testing/ssa_simpanan_alignment.csv'));
        @rmdir(storage_path('app/testing'));
        Mockery::close();

        parent::tearDown();
    }

    public function test_daily_loan_fast_path_is_eligible_for_csv_without_filters(): void
    {
        config()->set('import.direct_load.daily_loan.max_rows', 0);

        $service = Mockery::mock(MySqlBulkLoadService::class);
        $service->shouldReceive('supportsNativeBulkLoad')->andReturn(true);
        $this->app->instance(MySqlBulkLoadService::class, $service);

        $relativePath = 'testing/daily_loan_fast_path.csv';
        $absolutePath = storage_path('app/' . $relativePath);
        if (!is_dir(dirname($absolutePath))) {
            @mkdir(dirname($absolutePath), 0777, true);
        }

        file_put_contents($absolutePath, "PERIODE,NOMOR_REKENING1,BAKI_DEBET1\n2026-04-04,123,1000\n");

        $controller = new class extends ImportExcelController {
            public function resolveEligibility(array $params, array $headers): array
            {
                return $this->resolveDirectCsvFastPathEligibility('daily_loan', $params, $headers);
            }
        };

        $result = $controller->resolveEligibility([
            'staged_csv_path' => $absolutePath,
            'total_rows' => 320987,
            'active_filters' => [],
        ], ['PERIODE', 'NOMOR_REKENING1', 'BAKI_DEBET1']);

        $this->assertTrue($result['eligible']);
        $this->assertSame($absolutePath, $result['absolute_path']);
    }

    public function test_simpanan_fast_path_is_eligible_without_row_limit(): void
    {
        config()->set('import.direct_load.simpanan_multipn.max_rows', 0);

        $service = Mockery::mock(MySqlBulkLoadService::class);
        $service->shouldReceive('supportsNativeBulkLoad')->andReturn(true);
        $this->app->instance(MySqlBulkLoadService::class, $service);

        $relativePath = 'testing/daily_loan_fast_path.csv';
        $absolutePath = storage_path('app/' . $relativePath);
        if (!is_dir(dirname($absolutePath))) {
            @mkdir(dirname($absolutePath), 0777, true);
        }

        file_put_contents($absolutePath, "PERIODE,NOMOR_REKENING1,BAKI_DEBET1\n2026-04-04,123,1000\n");

        $controller = new class extends ImportExcelController {
            public function resolveEligibility(array $params, array $headers): array
            {
                return $this->resolveDirectCsvFastPathEligibility('simpanan_multipn', $params, $headers);
            }
        };

        $result = $controller->resolveEligibility([
            'staged_csv_path' => $absolutePath,
            'total_rows' => 320987,
            'active_filters' => [],
        ], ['PERIODE', 'NOMOR_REKENING1', 'BAKI_DEBET1']);

        $this->assertTrue($result['eligible']);
        $this->assertSame($absolutePath, $result['absolute_path']);
    }

    public function test_daily_loan_fast_path_falls_back_when_local_infile_is_unavailable(): void
    {
        $service = Mockery::mock(MySqlBulkLoadService::class);
        $service->shouldReceive('supportsNativeBulkLoad')->andReturn(false);
        $this->app->instance(MySqlBulkLoadService::class, $service);

        $controller = new class extends ImportExcelController {
            public function resolveEligibility(array $params, array $headers): array
            {
                return $this->resolveDirectCsvFastPathEligibility('daily_loan', $params, $headers);
            }
        };

        $result = $controller->resolveEligibility([
            'file_path' => 'testing/missing.csv',
            'total_rows' => 10,
            'active_filters' => [],
        ], ['PERIODE', 'NOMOR_REKENING1', 'BAKI_DEBET1']);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('LOCAL INFILE', $result['reason']);
    }

    public function test_daily_loan_fast_path_falls_back_when_row_limit_is_exceeded(): void
    {
        config()->set('import.direct_load.daily_loan.max_rows', 10);

        $service = Mockery::mock(MySqlBulkLoadService::class);
        $service->shouldReceive('supportsNativeBulkLoad')->andReturn(true);
        $this->app->instance(MySqlBulkLoadService::class, $service);

        $relativePath = 'testing/daily_loan_fast_path.csv';
        $absolutePath = storage_path('app/' . $relativePath);
        if (!is_dir(dirname($absolutePath))) {
            @mkdir(dirname($absolutePath), 0777, true);
        }

        file_put_contents($absolutePath, "PERIODE,NOMOR_REKENING1,BAKI_DEBET1\n2026-04-04,123,1000\n");

        $controller = new class extends ImportExcelController {
            public function resolveEligibility(array $params, array $headers): array
            {
                return $this->resolveDirectCsvFastPathEligibility('daily_loan', $params, $headers);
            }
        };

        $result = $controller->resolveEligibility([
            'staged_csv_path' => $absolutePath,
            'total_rows' => 11,
            'active_filters' => [],
        ], ['PERIODE', 'NOMOR_REKENING1', 'BAKI_DEBET1']);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('melebihi batas fast import', $result['reason']);
    }

    public function test_lw325_period_guard_accepts_valid_first_and_last_samples(): void
    {
        $relativePath = 'testing/lw325_validated.csv';
        $absolutePath = storage_path('app/' . $relativePath);
        if (!is_dir(dirname($absolutePath))) {
            @mkdir(dirname($absolutePath), 0777, true);
        }

        $lines = ['uniqueid_namareport,periode,acctno,kanca,nama_debitur'];
        for ($i = 1; $i <= 25; $i++) {
            $lines[] = sprintf('ROW-%02d,2026-01-31,%015d,KC Madiun,Nasabah %02d', $i, $i, $i);
        }
        file_put_contents($absolutePath, implode("\n", $lines));

        $controller = app(ImportExcelController::class);
        $method = new \ReflectionMethod($controller, 'validateLw325NormalizedPeriods');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, $absolutePath, 10));
    }

    public function test_lw325_period_guard_rejects_blank_period_in_first_or_last_samples(): void
    {
        $relativePath = 'testing/lw325_invalidated.csv';
        $absolutePath = storage_path('app/' . $relativePath);
        if (!is_dir(dirname($absolutePath))) {
            @mkdir(dirname($absolutePath), 0777, true);
        }

        $lines = ['uniqueid_namareport,periode,acctno,kanca,nama_debitur'];
        for ($i = 1; $i <= 25; $i++) {
            $period = $i === 25 ? '' : '2026-01-31';
            $lines[] = sprintf('ROW-%02d,%s,%015d,KC Madiun,Nasabah %02d', $i, $period, $i, $i);
        }
        file_put_contents($absolutePath, implode("\n", $lines));

        $controller = app(ImportExcelController::class);
        $method = new \ReflectionMethod($controller, 'validateLw325NormalizedPeriods');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, $absolutePath, 10));
    }

    public function test_ssa_simpanan_direct_load_rejects_leading_id_column(): void
    {
        $relativePath = 'testing/ssa_simpanan_alignment.csv';
        $absolutePath = storage_path('app/' . $relativePath);
        if (!is_dir(dirname($absolutePath))) {
            @mkdir(dirname($absolutePath), 0777, true);
        }

        file_put_contents(
            $absolutePath,
            "id,Month_Day_Year_of_Posisi,Nama Cabang,Nama Uker,Produk,Saldo\n"
            . "1,2026-04-14,00045 -- KC Madiun (Konsolidasi-MB),00045 -- KC Madiun,Tabungan,1000\n"
        );

        $controller = app(ImportExcelController::class);
        $method = new \ReflectionMethod(ImportExcelController::class, 'buildDirectGenericCsvLoadPlan');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('kolom ekstra `id`');

        $method->invoke(
            $controller,
            'ssa_simpanan',
            $absolutePath,
            [
                'Month_Day_Year_of_Posisi',
                'Nama Cabang',
                'Nama Uker',
                'Produk',
                'Saldo',
            ],
            []
        );
    }
}
