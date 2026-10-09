<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmReportController;
use App\Jobs\WarmLandingSmeQuadrantsJob;
use App\Support\LandingConsumerOperationalService;
use App\Support\LandingLoanAnalyticsService;
use App\Support\LandingSmeOperationalService;
use App\Support\ReportCacheVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class LandingPayloadRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('cache.default', 'array');
        Cache::flush();
    }

    public function test_unavailable_payload_expires_quickly_and_never_becomes_last_valid(): void
    {
        foreach ($this->services() as [$service, $save, $read, $nested]) {
            Cache::flush();
            $empty = $this->payload($nested, false);
            $result = $this->invoke($service, $save, ['fresh', 'durable', $empty]);
            $meta = $nested ? $result['meta'] : $result;
            $this->assertTrue($meta['refresh_pending']);
            $this->assertFalse(Cache::has('durable'));
            $this->assertNotNull($this->invoke($service, $read, ['fresh', false]));
            $this->travel(31)->seconds();
            $this->assertNull($this->invoke($service, $read, ['fresh', false]));
            $this->travelBack();
        }
    }

    public function test_temporary_unavailability_preserves_source_backed_zero_values(): void
    {
        foreach ($this->services() as [$service, $save, $read, $nested]) {
            Cache::flush();
            $valid = $this->payload($nested, true);
            $this->invoke($service, $save, ['fresh', 'durable', $valid]);
            $this->assertSame($valid, Cache::get('durable'));
            $fallback = $this->invoke($service, $save, ['fresh', 'durable', $this->payload($nested, false)]);
            $meta = $nested ? $fallback['meta'] : $fallback;
            $this->assertTrue($meta['cache_stale']);
            $this->assertTrue($meta['refresh_pending']);
            $this->assertSame(0, $fallback['value']);
            $this->assertSame($valid, Cache::get('durable'));
            $this->travel(31)->seconds();
            $this->assertNull($this->invoke($service, $read, ['fresh', false]));
            $this->assertSame($valid, $this->invoke($service, $read, ['durable', true]));
            $this->travelBack();
        }
    }

    public function test_legacy_empty_and_error_caches_are_discarded(): void
    {
        foreach ($this->services() as [$service, $save, $read, $nested]) {
            foreach ([false, true] as $durable) {
                $poisoned = $this->payload($nested, false);
                Cache::put('poisoned', $poisoned, now()->addDays(7));
                $this->assertNull($this->invoke($service, $read, ['poisoned', $durable]));
                $this->assertFalse(Cache::has('poisoned'));
                $poisoned = $this->payload($nested, true);
                if ($nested) {
                    $poisoned['meta']['error'] = 'Temporary source failure';
                } else {
                    $poisoned['error'] = 'Temporary source failure';
                }
                Cache::put('poisoned', $poisoned, now()->addDays(7));
                $this->assertNull($this->invoke($service, $read, ['poisoned', $durable]));
            }
        }
    }

    public function test_sme_reader_recovers_after_snapshot_becomes_available_without_version_bump(): void
    {
        $controller = Mockery::mock(KinerjaRmReportController::class);
        $controller->shouldReceive('landingSmallQuadrantSummary')->twice()->with('2026-09-30')
            ->andReturn($this->payload(false, false), $this->payload(false, true));
        $service = new LandingSmeOperationalService($controller);
        $key = 'landing:sme:quadrants:v2:'.ReportCacheVersion::composite(['pinjaman', 'harian']).':2026-09-30';
        Cache::put($key, $this->payload(false, false), now()->addHours(6));
        $first = $this->invoke($service, 'cachedQuadrants', ['2026-09-30', false]);
        $this->assertFalse($first['available']);
        $second = $this->invoke($service, 'cachedQuadrants', ['2026-09-30', false]);
        $this->assertSame($first, $second);
        $this->travel(31)->seconds();
        $recovered = $this->invoke($service, 'cachedQuadrants', ['2026-09-30', false]);
        $this->assertTrue($recovered['available']);
        $this->assertArrayNotHasKey('refresh_pending', $recovered);
        $this->travelBack();
    }

    public function test_sme_external_stable_data_returns_without_foreground_http(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $service = new LandingSmeOperationalService(Mockery::mock(KinerjaRmReportController::class));
        $stable = ['records' => [['amount' => 123]], 'meta' => ['available' => true]];
        Cache::put('landing:sme:external:v9:hot_prospects:stable', $stable, now()->addDays(2));

        foreach ([false, true] as $forceRefresh) {
            $result = $this->invoke($service, 'externalPayload', [$forceRefresh]);
            $this->assertSame($stable['records'], $result['hot_prospects']['records']);
            $this->assertTrue($result['hot_prospects']['meta']['refresh_pending']);
            $this->assertFalse($result['rtl_pipeline']['meta']['available']);
        }
        Http::assertNothingSent();
        $this->assertTrue(Cache::has('landing:sme:external:v9:refresh-pending'));
    }

    public function test_sme_external_failure_retries_after_thirty_seconds_and_preserves_stable_source(): void
    {
        Http::fake(['*' => Http::response('', 503)]);
        $service = new LandingSmeOperationalService(Mockery::mock(KinerjaRmReportController::class));
        $stable = ['records' => [['amount' => 123]], 'meta' => ['available' => true]];
        Cache::put('landing:sme:external:v9:hot_prospects:stable', $stable, now()->addDays(2));
        $result = $this->invoke($service, 'fetchExternalPayload', [5]);
        $this->assertSame($stable['records'], $result['hot_prospects']['records']);
        $this->assertTrue($result['hot_prospects']['meta']['refresh_pending']);
        $this->assertSame($stable, Cache::get('landing:sme:external:v9:hot_prospects:stable'));
        $this->assertTrue(Cache::has('landing:sme:external:v9'));
        $this->travel(31)->seconds();
        $this->assertFalse(Cache::has('landing:sme:external:v9'));
        $this->travelBack();
    }

    public function test_sme_cold_web_request_queues_quadrants_and_worker_populates_the_next_read(): void
    {
        Bus::fake([WarmLandingSmeQuadrantsJob::class]);
        $controller = Mockery::mock(KinerjaRmReportController::class);
        $service = new LandingSmeOperationalService($controller);
        $pending = $this->invoke($service, 'cachedQuadrants', ['2026-09-30', false, true]);
        $this->assertTrue($pending['refresh_pending']);
        $this->assertFalse($pending['available']);
        $controller->shouldNotHaveReceived('landingSmallQuadrantSummary');
        Bus::assertDispatched(WarmLandingSmeQuadrantsJob::class, fn ($job): bool => $job->period === '2026-09-30'
            && $job->queue === 'default');

        $controller->shouldReceive('landingSmallQuadrantSummary')->once()->with('2026-09-30')
            ->andReturn($this->payload(false, true));
        $service->warmQuadrants('2026-09-30');
        $service->warmQuadrants('2026-09-30');
        $ready = $this->invoke($service, 'cachedQuadrants', ['2026-09-30', false, true]);
        $this->assertTrue($ready['available']);
        $this->assertArrayNotHasKey('refresh_pending', $ready);
    }

    public function test_sme_worker_retries_unavailable_output_instead_of_reporting_success(): void
    {
        $controller = Mockery::mock(KinerjaRmReportController::class);
        $controller->shouldReceive('landingSmallQuadrantSummary')->once()->with('2026-09-30')->andReturn($this->payload(false, false));
        $service = new LandingSmeOperationalService($controller);
        $job = new WarmLandingSmeQuadrantsJob('2026-09-30');
        $this->expectException(\RuntimeException::class);
        $job->handle($service);
    }

    public function test_sme_worker_releases_when_another_builder_owns_the_cache_lock(): void
    {
        $version = ReportCacheVersion::composite(['pinjaman', 'harian']);
        $lock = Cache::lock('landing:sme:quadrants:v2:'.$version.':2026-09-30:lock', 900);
        $lock->get();
        try {
            $controller = Mockery::mock(KinerjaRmReportController::class);
            $controller->shouldNotReceive('landingSmallQuadrantSummary');
            $service = new LandingSmeOperationalService($controller);
            $queueJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
            $queueJob->shouldReceive('release')->once()->with(60);
            $job = new WarmLandingSmeQuadrantsJob('2026-09-30');
            $job->setJob($queueJob);
            $job->handle($service);
            $this->assertFalse(Cache::lock('landing:sme:quadrants:v2:'.$version.':2026-09-30:lock', 900)->get());
        } finally {
            $lock->release();
        }
    }

    private function services(): array
    {
        return [
            [new LandingSmeOperationalService(Mockery::mock(KinerjaRmReportController::class)), 'cacheQuadrantPayload', 'readQuadrantCache', false],
            [app(LandingConsumerOperationalService::class), 'cacheQuadrantPayload', 'readQuadrantCache', false],
            [app(LandingLoanAnalyticsService::class), 'cacheAnalyticsPayload', 'readAnalyticsCache', true],
        ];
    }

    private function payload(bool $nested, bool $available): array
    {
        $meta = ['available' => $available, 'period' => $available ? '2026-09-30' : null];
        return $nested ? ['meta' => $meta, 'value' => 0] : $meta + ['value' => 0];
    }

    private function invoke(object $service, string $method, array $arguments): mixed
    {
        return (new ReflectionMethod($service, $method))->invokeArgs($service, $arguments);
    }
}
