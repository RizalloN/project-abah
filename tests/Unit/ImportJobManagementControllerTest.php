<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportJobManagementController;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Support\ManagedReportSnapshotRebuildCoordinator;
use Mockery;
use Tests\TestCase;

class ImportJobManagementControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_force_start_runs_queued_import_inline(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $executionService = Mockery::mock(ImportExecutionService::class);

        $progressService->shouldReceive('findJob')
            ->once()
            ->with(77)
            ->andReturn((object) [
                'id' => 77,
                'status' => 'queued',
                'total_success' => 0,
                'total_failed' => 0,
            ]);
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')
            ->once()
            ->with(77);
        $executionService->shouldReceive('run')
            ->once()
            ->with(77);

        $controller = new ImportJobManagementController(app(ManagedReportSnapshotRebuildCoordinator::class));
        $response = $controller->forceStart(77, $progressService, $executionService);
        $payload = $response->getData(true);

        $this->assertSame('success', $payload['status']);
    }
}
