<?php

namespace Tests\Unit;

use App\Jobs\WarmDashboardSimpananCacheJob;
use App\Jobs\WarmLandingLoanRiskCacheJob;
use App\Support\LandingLoanRiskCacheService;
use App\Support\ReportCacheVersion;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WarmReportJobUniquenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        Cache::flush();
        parent::tearDown();
    }

    public function test_risk_identity_survives_version_change_and_serialization_without_releasing_new_lock(): void
    {
        $old = new WarmLandingLoanRiskCacheJob('2026-09-30');
        $id = $old->uniqueId();
        $locks = new UniqueLock(Cache::store());
        $this->assertTrue($locks->acquire($old));
        $serialized = serialize($old);
        ReportCacheVersion::bump('pinjaman');
        $new = new WarmLandingLoanRiskCacheJob('2026-09-30');
        $this->assertNotSame($id, $new->uniqueId());
        $this->assertSame($id, $old->uniqueId());
        $restored = unserialize($serialized);
        $this->assertSame($id, $restored->uniqueId());
        $this->assertTrue($locks->acquire($new));
        $locks->release($restored);
        $this->assertFalse($locks->acquire($new));
        $this->assertTrue($locks->acquire($old));
    }

    public function test_legacy_payload_without_version_uses_safe_stable_namespace(): void
    {
        $job = new WarmLandingLoanRiskCacheJob('2026-09-30');
        $payload = $job->__serialize();
        foreach (array_keys($payload) as $key) {
            if (str_ends_with($key, 'reportVersion')) {
                unset($payload[$key]);
            }
        }
        $legacy = (new \ReflectionClass(WarmLandingLoanRiskCacheJob::class))->newInstanceWithoutConstructor();
        $legacy->__unserialize($payload);
        $this->assertSame('2026-09-30:report-vlegacy', $legacy->uniqueId());
        ReportCacheVersion::bump('pinjaman');
        $this->assertSame('2026-09-30:report-vlegacy', unserialize(serialize($legacy))->uniqueId());
        $this->assertNotSame((new WarmLandingLoanRiskCacheJob('2026-09-30'))->uniqueId(), $legacy->uniqueId());
    }

    public function test_pending_warm_jobs_remain_unique_past_three_hour_backlog_but_expire_after_a_day(): void
    {
        $jobs = [
            new WarmLandingLoanRiskCacheJob('2026-09-30'),
            new WarmDashboardSimpananCacheJob('micro-readiness', ['period' => '2026-09-30']),
        ];
        $locks = new UniqueLock(Cache::store());
        foreach ($jobs as $job) {
            $this->assertSame(86400, $job->uniqueFor);
            $this->assertTrue($locks->acquire($job));
        }
        $this->travel(4)->hours();
        foreach ($jobs as $job) {
            $this->assertFalse($locks->acquire($job));
        }
        $this->travel(21)->hours();
        foreach ($jobs as $job) {
            $this->assertTrue($locks->acquire($job));
        }
    }

    public function test_risk_handler_uses_cache_aware_warm_instead_of_forced_rebuild(): void
    {
        $service = $this->createMock(LandingLoanRiskCacheService::class);
        $service->expects($this->once())->method('warm')->with('2026-09-30')->willReturn([]);
        $service->expects($this->never())->method('rebuild');
        (new WarmLandingLoanRiskCacheJob('2026-09-30'))->handle($service);
    }

    public function test_duplicate_risk_handler_does_not_rebuild_or_bump_generation_when_current_cache_exists(): void
    {
        $service = $this->getMockBuilder(LandingLoanRiskCacheService::class)->onlyMethods(['rebuild'])->getMock();
        $service->expects($this->never())->method('rebuild');
        $period = '2026-09-30';
        $key = (new \ReflectionMethod(LandingLoanRiskCacheService::class, 'currentCacheKey'))->invoke($service, $period);
        Cache::put($key, ['period' => $period, 'rows' => [], 'branch_totals' => [], 'area_totals' => []], 3600);
        $generation = $service->generation();
        $job = new WarmLandingLoanRiskCacheJob($period);
        $job->handle($service);
        $job->handle($service);
        $this->assertSame($generation, $service->generation());
    }
}
