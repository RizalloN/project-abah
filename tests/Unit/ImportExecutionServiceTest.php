<?php

namespace Tests\Unit;

use App\Jobs\RunImportJob;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ImportExecutionServiceTest extends TestCase
{
    public function test_dispatch_only_enqueues_once_for_same_job(): void
    {
        Queue::fake();
        Cache::flush();

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')
            ->times(4)
            ->andReturn((object) [
                'id' => 55,
                'status' => 'queued',
                'total_success' => 0,
                'total_failed' => 0,
                'total_files' => 100,
            ]);
        $progressService->shouldReceive('markQueued')->once();

        $service = new ImportExecutionService($progressService);

        $service->dispatch(55);
        $service->dispatch(55);

        Queue::assertPushed(RunImportJob::class, 1);
    }
}
