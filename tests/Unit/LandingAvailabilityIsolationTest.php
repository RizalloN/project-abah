<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Jobs\WarmDashboardSimpananCacheJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class LandingAvailabilityIsolationTest extends TestCase
{
    public function test_cold_page_lock_contention_returns_pending_without_source_rebuild(): void
    {
        Cache::flush();
        $controller = app(DashboardSimpananController::class);
        $version = (new ReflectionMethod($controller, 'reportCacheVersion'))->invoke($controller);
        $sourceVersion = (new \ReflectionClass($controller))->getConstant('LANDING_SOURCE_CACHE_VERSION');
        $risk = app(\App\Support\LandingLoanRiskCacheService::class)->generation();
        $lock = Cache::lock('dashboard_simpanan:payload:2026-10-07:'.$sourceVersion.':v'.$version.':risk-v'.$risk.':lock', 300);
        $this->assertTrue($lock->get());
        try {
            $payload = (new ReflectionMethod($controller, 'buildDashboardPayload'))->invoke($controller, '2026-10-07');
            $this->assertTrue($payload['meta']['refresh_pending']);
            $this->assertGreaterThan(now()->timestamp, $payload['meta']['retry_after']);
        } finally {
            $lock->release();
        }
    }

    public function test_auxiliary_failure_preserves_primary_data_and_requests_retry(): void
    {
        $controller = app(DashboardSimpananController::class);
        $payload = ['meta' => ['period' => '2026-10-07'], 'realization' => ['amount' => 123]];
        $reader = static fn (): array => throw new RuntimeException('temporary source failure');
        (new ReflectionMethod($controller, 'appendLandingSection'))->invokeArgs($controller, [&$payload, 'pipeline', $reader]);
        $this->assertSame(123, $payload['realization']['amount']);
        $this->assertTrue($payload['meta']['refresh_pending']);
        $this->assertFalse($payload['pipeline']['available']);
    }

    public function test_decision_refresh_keeps_same_period_last_valid_and_deduplicates_reads(): void
    {
        Cache::flush();
        Bus::fake();
        $controller = app(DashboardSimpananController::class);
        $period = '2026-10-07';
        $branch = (new ReflectionMethod($controller, 'landingBranchCacheKey'))->invoke($controller);
        $key = 'dashboard_simpanan:landing_decision:durable:'.$period.':'.$branch;
        $valid = ['available' => true, 'period' => $period, 'total_nominal' => 0];
        Cache::put($key, $valid, 3600);
        $dashboard = ['area6_portfolio' => ['loan_detail_period' => $period]];
        $method = new ReflectionMethod($controller, 'refreshLandingDecisionSummary');
        $result = $method->invoke($controller, $dashboard, $period, true);
        $this->assertSame($valid, Cache::get($key));
        $this->assertTrue($result['landing_summary']['decision']['refresh_pending']);
        $this->assertSame(0, $result['landing_summary']['decision']['total_nominal']);
        $method->invoke($controller, $dashboard, $period);
        Bus::assertDispatchedTimes(WarmDashboardSimpananCacheJob::class, 1);
    }

    public function test_decision_durable_rejects_pending_error_and_wrong_period(): void
    {
        $controller = app(DashboardSimpananController::class);
        $method = new ReflectionMethod($controller, 'landingDecisionSummaryIsReady');
        $valid = ['available' => true, 'period' => '2026-10-07', 'total_nominal' => 0];
        $this->assertTrue($method->invoke($controller, $valid, '2026-10-07'));
        foreach ([['available' => false], ['refresh_pending' => true], ['error' => 'failure'], ['period' => '2026-10-06']] as $invalid) {
            $this->assertFalse($method->invoke($controller, array_replace($valid, $invalid), '2026-10-07'));
        }
    }
}
