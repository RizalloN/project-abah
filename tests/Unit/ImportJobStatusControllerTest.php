<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportJobStatusController;
use App\Services\Import\ImportProgressService;
use Mockery;
use Tests\TestCase;

class ImportJobStatusControllerTest extends TestCase
{
    public function test_controller_returns_json_status_payload(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('getStatusPayload')
            ->once()
            ->with(11)
            ->andReturn([
                'status' => 'queued',
                'job_id' => 11,
                'total_rows' => 25,
                'processed_rows' => 0,
            ]);

        $response = app(ImportJobStatusController::class)->__invoke(11, $progressService);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('"job_id":11', $response->getContent());
        $this->assertStringContainsString('"status":"queued"', $response->getContent());
    }
}
