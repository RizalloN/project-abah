<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Jobs\WarmDashboardSimpananCacheJob;
use App\Jobs\WarmLandingLoanRiskCacheJob;
use App\Support\LandingLoanRiskCacheService;
use App\Support\LandingPayloadState;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

class LandingWorkerReadinessTest extends TestCase
{
    public function test_risk_worker_retries_unavailable_source_and_does_not_duplicate_locked_build(): void
    {
        $service = $this->createMock(LandingLoanRiskCacheService::class);
        $service->expects($this->once())->method('warm')->with('2026-10-07')
            ->willReturn(['available' => false, 'period' => '2026-10-07']);
        $pending = (new WarmLandingLoanRiskCacheJob('2026-10-07'))->withFakeQueueInteractions();
        $pending->handle($service);
        $pending->assertReleased(60);
        $owner = Cache::lock('landing:loan-risk:build:2026-10-07', 1230);
        $this->assertTrue($owner->get());
        try {
            $contender = (new WarmLandingLoanRiskCacheJob('2026-10-07'))->withFakeQueueInteractions();
            $contender->handle($service);
            $contender->assertReleased(60);
        } finally {
            $owner->release();
        }
    }

    public function test_worker_releases_pending_work_then_completes_after_dependency_is_ready(): void
    {
        $controller = $this->createMock(DashboardSimpananController::class);
        $context = ['selectedPeriod' => '2026-10-07', 'landingBranchKey' => 'madiun'];
        $controller->expects($this->exactly(2))->method('warmDashboardSimpananCache')
            ->with('dashboard-payload', $context)->willReturnOnConsecutiveCalls(false, true);
        $pending = (new WarmDashboardSimpananCacheJob('dashboard-payload', $context))->withFakeQueueInteractions();
        $pending->handle($controller);
        $pending->assertReleased(60);
        $ready = (new WarmDashboardSimpananCacheJob('dashboard-payload', $context))->withFakeQueueInteractions();
        $ready->handle($controller);
        $ready->assertNotReleased();
    }

    public function test_controller_reports_lock_contention_without_discarding_the_other_build(): void
    {
        Cache::flush();
        $owner = Cache::lock('landing-worker-test:lock', 930);
        $this->assertTrue($owner->get());
        try {
            $result = app(DashboardSimpananController::class)->warmDashboardSimpananCache('dashboard-payload', [
                'payloadCacheKey' => 'landing-worker-test', 'selectedPeriod' => '2026-10-07',
            ]);
            $this->assertFalse($result);
            $this->assertFalse(Cache::lock('landing-worker-test:lock', 930)->get());
        } finally {
            $owner->release();
        }
    }

    public function test_nested_risk_pending_is_not_saved_as_last_valid_and_recovers(): void
    {
        Cache::flush();
        $controller = app(DashboardSimpananController::class);
        $ready = ['period' => '2026-10-07', 'rankings' => [['rows' => [], 'refresh_pending' => false]]];
        $pending = $ready;
        $pending['rankings'][0]['refresh_pending'] = true;
        $save = new ReflectionMethod($controller, 'cacheLandingPortfolioPayload');
        $isReady = new ReflectionMethod($controller, 'landingPayloadIsReady');
        $save->invoke($controller, 'fresh', 'latest', 'stable', $ready);
        $save->invoke($controller, 'fresh', 'latest', 'stable', $pending);
        $this->assertSame($ready, Cache::get('stable'));
        $this->assertFalse($isReady->invoke($controller, $pending));
        $this->assertTrue(LandingPayloadState::pending(['area6_portfolio' => $pending]));
        $this->travel(31)->seconds();
        $this->assertNull(Cache::get('fresh'));
        $save->invoke($controller, 'fresh', 'latest', 'stable', $ready);
        $this->assertTrue($isReady->invoke($controller, Cache::get('fresh')));
        $this->travelBack();
    }

    public function test_page_recovery_receives_nested_dependency_state(): void
    {
        $html = view('dashboard.partials.landing-page-recovery', ['dashboard' => [
            'landing_summary' => ['decision' => ['refresh_pending' => true]],
        ]])->render();
        $this->assertStringContainsString('recoverLandingPage(true, window, document)', $html);
    }
}
