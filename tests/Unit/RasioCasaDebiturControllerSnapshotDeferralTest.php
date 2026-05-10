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

    public function test_rasio_casa_labels_use_short_position_dates(): void
    {
        $controller = new RasioCasaDebiturController();
        $reflection = new \ReflectionMethod($controller, 'buildLabels');
        $reflection->setAccessible(true);

        $labels = $reflection->invoke($controller, '2026-04-30', '2026-05-07', '2026-03-31', '2025-12-31');

        $this->assertSame([
            'ytd' => '31 Des 25',
            'm2' => '31 Mar 26',
            'prev' => '30 Apr 26',
            'curr' => '07 Mei 26',
        ], $labels);
    }

    public function test_rasio_casa_metrics_include_m2_and_ytd_deltas(): void
    {
        $controller = new RasioCasaDebiturController();
        $reflection = new \ReflectionMethod($controller, 'calculateMetrics');
        $reflection->setAccessible(true);

        $metrics = $reflection->invoke(
            $controller,
            ['os' => 1000, 'casa' => 100],
            ['os' => 1000, 'casa' => 130],
            true,
            true,
            ['os' => 1000, 'casa' => 90],
            ['os' => 1000, 'casa' => 80],
            true,
            true
        );

        $this->assertSame(10.0, $metrics['rasio_prev']);
        $this->assertSame(13.0, $metrics['rasio_curr']);
        $this->assertSame(3.0, $metrics['mtd']);
        $this->assertSame(4.0, $metrics['m2']);
        $this->assertSame(5.0, $metrics['ytd']);
    }

    public function test_per_rm_casa_allocation_is_capped_by_managed_os(): void
    {
        $controller = new RasioCasaDebiturController();
        $reflection = new \ReflectionMethod($controller, 'allocateCasaBalanceForRm');
        $reflection->setAccessible(true);

        $os = 9_933_589_981.41;
        $allocations = $reflection->invoke(
            $controller,
            11_315_746_194.0,
            [
                '00071662 - TUTUT SUSILO' => [
                    'total' => $os,
                    'briguna' => 0,
                    'kpr' => 0,
                    'mikro' => $os,
                    'smc' => 0,
                ],
            ],
            $os
        );

        $this->assertEqualsWithDelta($os, $allocations['00071662 - TUTUT SUSILO']['total'], 0.01);
        $this->assertEqualsWithDelta($os, $allocations['00071662 - TUTUT SUSILO']['mikro'], 0.01);
    }

    public function test_per_rm_casa_allocation_is_split_by_os_share(): void
    {
        $controller = new RasioCasaDebiturController();
        $reflection = new \ReflectionMethod($controller, 'allocateCasaBalanceForRm');
        $reflection->setAccessible(true);

        $tututOs = 1_228_609_149.0;
        $otherOs = 2_335_251_411.0;
        $totalOs = $tututOs + $otherOs;
        $casa = 2_366_885_174.0;

        $allocations = $reflection->invoke(
            $controller,
            $casa,
            [
                '00071662 - TUTUT SUSILO' => [
                    'total' => $tututOs,
                    'briguna' => 0,
                    'kpr' => 0,
                    'mikro' => $tututOs,
                    'smc' => 0,
                ],
                '00000000 - RM LAIN' => [
                    'total' => $otherOs,
                    'briguna' => 0,
                    'kpr' => 0,
                    'mikro' => $otherOs,
                    'smc' => 0,
                ],
            ],
            $totalOs
        );

        $this->assertEqualsWithDelta($casa * ($tututOs / $totalOs), $allocations['00071662 - TUTUT SUSILO']['total'], 0.01);
        $this->assertEqualsWithDelta($casa * ($otherOs / $totalOs), $allocations['00000000 - RM LAIN']['total'], 0.01);
        $this->assertEqualsWithDelta($casa, $allocations['00071662 - TUTUT SUSILO']['total'] + $allocations['00000000 - RM LAIN']['total'], 0.01);
    }
}
