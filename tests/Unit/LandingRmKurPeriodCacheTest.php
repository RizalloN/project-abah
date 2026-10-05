<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use App\Support\ReportCacheVersion;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

class LandingRmKurPeriodCacheTest extends TestCase
{
    public function test_switching_dates_does_not_reuse_productivity_from_another_date(): void
    {
        Cache::flush();
        $report = $this->createMock(KinerjaRmMikroReportController::class);
        $report->expects($this->exactly(2))->method('buildEmbeddedPayload')
            ->willReturnCallback(fn ($category, $period) => [
                'rows' => [['pn' => '123', 'nama' => 'RM Test', 'realisasi_deb' => 1,
                    'realisasi_os' => $period === '2026-09-30' ? 100 : 200]],
                'meta' => ['data_period' => $period, 'requested_period' => $period],
            ]);
        $this->app->instance(KinerjaRmMikroReportController::class, $report);
        $controller = app(DashboardSimpananController::class);
        $method = new ReflectionMethod($controller, 'buildLandingRmKurProductivity');
        $sep = $method->invoke($controller, '2026-09-30', null);
        $oct = $method->invoke($controller, '2026-10-02', null);
        $back = $method->invoke($controller, '2026-09-30', null);

        $this->assertSame('2026-09-30', $sep['data_period']);
        $this->assertSame('2026-10-02', $oct['data_period']);
        $this->assertSame(200.0, $oct['total']['realisasi_os']);
        $this->assertSame($sep, $back);
        $this->assertFalse($oct['refresh_pending']);
    }

    public function test_cross_version_fallback_stays_within_requested_period(): void
    {
        Cache::flush();
        $report = $this->createMock(KinerjaRmMikroReportController::class);
        $report->expects($this->exactly(2))->method('buildEmbeddedPayload')
            ->willReturnCallback(fn ($category, $period) => [
                'rows' => [], 'meta' => ['data_period' => $period, 'requested_period' => $period],
            ]);
        $this->app->instance(KinerjaRmMikroReportController::class, $report);
        $controller = app(DashboardSimpananController::class);
        $method = new ReflectionMethod($controller, 'buildLandingRmKurProductivity');
        $method->invoke($controller, '2026-09-30', null);
        $method->invoke($controller, '2026-10-02', null);
        ReportCacheVersion::bump('pinjaman');
        $back = $method->invoke($controller, '2026-09-30', null);

        $this->assertSame('2026-09-30', $back['data_period']);
        $this->assertTrue($back['refresh_pending']);
    }
}
