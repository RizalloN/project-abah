<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmReportController;
use App\Support\LandingConsumerOperationalService;
use App\Support\LandingSmeOperationalService;
use App\Support\ReportCacheVersion;
use App\Support\RkaLookupService;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class LandingOperationalPeriodCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();
    }

    public function test_sme_durable_fallback_does_not_cross_selected_periods(): void
    {
        $controller = Mockery::mock(KinerjaRmReportController::class);
        $controller->shouldReceive('landingSmallQuadrantSummary')
            ->once()
            ->with('2026-09-30')
            ->andReturn($this->quadrantPayload('2026-09-30'));
        $service = new LandingSmeOperationalService($controller);

        $this->primeSmeDurable($service, '2026-08-31');

        $result = $this->invoke($service, 'cachedQuadrants', ['2026-09-30', false]);

        $this->assertSame('2026-09-30', $result['period']);
        $this->assertArrayNotHasKey('cache_stale', $result);
    }

    public function test_sme_durable_fallback_is_kept_for_the_same_selected_period(): void
    {
        $controller = Mockery::mock(KinerjaRmReportController::class);
        $controller->shouldReceive('landingSmallQuadrantSummary')
            ->zeroOrMoreTimes()
            ->andReturn($this->quadrantPayload('2026-08-31'));
        $service = new LandingSmeOperationalService($controller);

        $this->primeSmeDurable($service, '2026-08-31');
        Cache::forget($this->smeCacheKey('2026-08-31'));

        $result = $this->invoke($service, 'cachedQuadrants', ['2026-08-31', false]);

        $this->assertSame('2026-08-31', $result['period']);
        $this->assertTrue($result['cache_stale']);
        $this->assertTrue($result['refresh_pending']);
    }

    public function test_consumer_durable_fallback_does_not_cross_selected_periods(): void
    {
        $service = app(LandingConsumerOperationalService::class);
        $scope = UserBranchScope::forKey('madiun');

        $this->primeConsumerDurable($service, '2026-08-31', 'madiun', $scope);

        $result = $this->invoke($service, 'cachedQuadrantPayload', ['2026-09-30', $scope, false]);

        $this->assertNull($result['period']);
        $this->assertArrayNotHasKey('cache_stale', $result);
    }

    public function test_consumer_durable_fallback_is_kept_for_the_same_selected_period(): void
    {
        $service = app(LandingConsumerOperationalService::class);
        $scope = UserBranchScope::forKey('madiun');

        $this->primeConsumerDurable($service, '2026-08-31', 'madiun', $scope);
        Cache::forget($this->consumerCacheKey('2026-08-31', 'madiun'));

        $result = $this->invoke($service, 'cachedQuadrantPayload', ['2026-08-31', $scope, false]);

        $this->assertSame('2026-08-31', $result['period']);
        $this->assertTrue($result['cache_stale']);
        $this->assertTrue($result['refresh_pending']);
    }

    public function test_consumer_period_resolution_never_uses_a_future_snapshot_in_the_selected_month(): void
    {
        Schema::create('performance_rm_snapshots', function (Blueprint $table): void {
            $table->date('periode');
            $table->string('segmen');
            $table->string('cabang');
        });
        DB::table('performance_rm_snapshots')->insert([
            ['periode' => '2026-08-31', 'segmen' => 'CONSUMER', 'cabang' => 'KC MADIUN'],
            ['periode' => '2026-09-10', 'segmen' => 'CONSUMER', 'cabang' => 'KC MADIUN'],
            ['periode' => '2026-09-30', 'segmen' => 'CONSUMER', 'cabang' => 'KC MADIUN'],
        ]);
        $periodQuery = DB::table('performance_rm_snapshots')
            ->where('segmen', 'CONSUMER')
            ->where('cabang', 'KC MADIUN');

        $result = $this->invoke(
            app(LandingConsumerOperationalService::class),
            'resolveQuadrantPeriod',
            [$periodQuery, '2026-09-15']
        );

        $this->assertSame('2026-09-10', $result);
    }

    public function test_sme_period_resolution_falls_back_backward_instead_of_using_a_future_month_date(): void
    {
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $result = $this->invoke($controller, 'resolveLandingSmallPeriod', [
            collect(['2026-09-30', '2026-08-31']),
            '2026-09-15',
        ]);

        $this->assertSame('2026-08-31', $result);
    }

    private function primeSmeDurable(LandingSmeOperationalService $service, string $period): void
    {
        Cache::put($this->smeCacheKey($period), $this->quadrantPayload($period), 60);
        $this->invoke($service, 'cachedQuadrants', [$period, false]);
    }

    /** @param array<string, mixed>|null $scope */
    private function primeConsumerDurable(
        LandingConsumerOperationalService $service,
        string $period,
        string $scopeKey,
        ?array $scope
    ): void {
        Cache::put($this->consumerCacheKey($period, $scopeKey), $this->quadrantPayload($period), 60);
        $this->invoke($service, 'cachedQuadrantPayload', [$period, $scope, false]);
    }

    private function smeCacheKey(string $period): string
    {
        return implode(':', [
            'landing', 'sme', 'quadrants', 'v2',
            ReportCacheVersion::composite(['pinjaman', 'harian']), $period,
        ]);
    }

    private function consumerCacheKey(string $period, string $scopeKey): string
    {
        return implode(':', [
            'landing', 'consumer', 'quadrants', 'v2',
            ReportCacheVersion::composite(['pinjaman', 'consumer']), $period, $scopeKey,
        ]);
    }

    /** @return array<string, mixed> */
    private function quadrantPayload(string $period): array
    {
        return [
            'available' => true,
            'period' => $period,
            'period_label' => $period,
            'branches' => [],
        ];
    }

    /** @param array<int, mixed> $arguments */
    private function invoke(object $target, string $method, array $arguments): mixed
    {
        return (new ReflectionMethod($target, $method))->invoke($target, ...$arguments);
    }
}
