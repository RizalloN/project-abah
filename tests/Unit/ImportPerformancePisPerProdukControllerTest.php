<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportCleanupController;
use App\Http\Controllers\Import\ImportPerformancePisPerProdukController;
use App\Support\ReportDataSyncService;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class ImportPerformancePisPerProdukControllerTest extends TestCase
{
    public function test_cleanup_successful_import_syncs_report_snapshots_before_cleanup(): void
    {
        $controller = new ImportPerformancePisPerProdukController();

        $syncService = Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('syncImportedTable')
            ->once()
            ->with('performance_pis_per_produk', null, 77, ImportPerformancePisPerProdukController::class);
        $this->app->instance(ReportDataSyncService::class, $syncService);

        $cleanupController = Mockery::mock(ImportCleanupController::class);
        $cleanupController->shouldReceive('cleanupSuccessfulJobArtifacts')
            ->once()
            ->with(77, ['performance/sample.xlsx', 'C:\\temp\\performance_stage.csv'])
            ->andReturn([
                'job_id' => 77,
                'eligible' => true,
                'deleted_files' => [],
                'deleted_directories' => [],
            ]);
        $this->app->instance(ImportCleanupController::class, $cleanupController);

        $this->invokeMethod($controller, 'cleanupSuccessfulImportArtifacts', [
            77,
            'performance/sample.xlsx',
            ['C:\\temp\\performance_stage.csv'],
        ]);
    }

    private function invokeMethod(object $target, string $method, array $arguments)
    {
        $reflection = new ReflectionClass($target);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invokeArgs($target, $arguments);
    }
}
