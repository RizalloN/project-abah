<?php

namespace Tests\Unit;

use App\Http\Controllers\RasioCasaDebiturController;
use App\Jobs\EnsureRasioCasaSnapshotJob;
use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class RasioCasaDebiturControllerSnapshotDeferralTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_inline_rasio_rebuild_is_queued_when_an_import_is_active(): void
    {
        Bus::fake();

        Schema::shouldReceive('hasTable')
            ->twice()
            ->andReturnTrue();

        $importProgressService = Mockery::mock(ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobs')
            ->once()
            ->andReturnTrue();
        $this->app->instance(ImportProgressService::class, $importProgressService);

        $controller = new RasioCasaDebiturController();
        $reflection = new \ReflectionMethod($controller, 'rebuildRasioSnapshotInline');
        $reflection->setAccessible(true);
        $reflection->invoke($controller, '2026-04-01');

        Bus::assertDispatched(EnsureRasioCasaSnapshotJob::class, function (EnsureRasioCasaSnapshotJob $job): bool {
            return $job->period === '2026-04-01';
        });
    }
}
