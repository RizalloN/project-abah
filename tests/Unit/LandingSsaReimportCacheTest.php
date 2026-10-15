<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Jobs\WarmDashboardSimpananCacheJob;
use App\Support\LandingLoanRiskCacheService;
use App\Support\ReportCacheVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class LandingSsaReimportCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();
        DashboardSimpananController::clearSchemaMemo();
        Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->string('snapshot_period');
            $table->string('kanca_label');
            $table->string('kanca_key');
            $table->string('unit_key');
            $table->double('total_os_non_commercial');
            $table->double('total_sml_abs_non_commercial')->default(0);
            $table->double('total_npl_abs_non_commercial')->default(0);
            $table->unsignedInteger('source_loan_row_count')->default(1);
        });
        DB::table('dashboard_harian_snapshots')->insert([
            ['snapshot_period' => '2026-10-14', 'kanca_label' => 'KC Madiun', 'kanca_key' => 'madiun', 'unit_key' => 'madiun', 'total_os_non_commercial' => 125],
            ['snapshot_period' => '2026-10-14', 'kanca_label' => 'KC Ngawi', 'kanca_key' => 'ngawi', 'unit_key' => 'ngawi', 'total_os_non_commercial' => 900],
            ['snapshot_period' => '2026-10-14', 'kanca_label' => 'KC Madiun', 'kanca_key' => 'madiun', 'unit_key' => 'unit-a', 'total_os_non_commercial' => 125],
        ]);
    }

    public function test_old_zero_primary_and_durable_caches_are_rejected_after_ssa_reimport(): void
    {
        $controller = app(DashboardSimpananController::class);
        $old = $this->payload(0);
        foreach ([false, true] as $durable) {
            Cache::put('old', $old, 3600);
            $this->assertNull((new ReflectionMethod($controller, 'readLandingPayloadCache'))->invoke($controller, 'old', $durable));
            $this->assertFalse(Cache::has('old'));
        }
        $this->assertTrue($this->snapshotMatches($controller, $this->payload(1025)));
        $this->assertTrue($this->snapshotMatches($controller, $this->payload(1025)['area6_portfolio']));
    }

    public function test_snapshot_comparison_uses_selected_branch_and_ignores_detail_duplicates(): void
    {
        $controller = app(DashboardSimpananController::class);
        (new ReflectionMethod($controller, 'configureLandingBranchFromKey'))->invoke($controller, 'madiun');
        $this->assertTrue($this->snapshotMatches($controller, $this->payload(125)));
        $this->assertFalse($this->snapshotMatches($controller, $this->payload(1025)));
        $this->assertFalse($this->snapshotMatches($controller, $this->payload(250)));
    }

    public function test_genuine_zero_and_temporarily_missing_snapshot_keep_last_valid_behavior(): void
    {
        $controller = app(DashboardSimpananController::class);
        DB::table('dashboard_harian_snapshots')->update(['total_os_non_commercial' => 0]);
        $this->assertTrue($this->snapshotMatches($controller, $this->payload(0)));
        $this->assertFalse($this->snapshotMatches($controller, $this->payload(1025)));
        DB::table('dashboard_harian_snapshots')->delete();
        $this->assertTrue($this->snapshotMatches($controller, $this->payload(1025)));
    }

    public function test_nonzero_corrections_and_sml_npl_changes_also_invalidate_old_payloads(): void
    {
        $controller = app(DashboardSimpananController::class);
        $this->assertFalse($this->snapshotMatches($controller, $this->payload(1000)));
        $pending = $this->payload(0);
        $pending['meta'] = ['refresh_pending' => true, 'retry_after' => now()->addSeconds(30)->timestamp];
        Cache::put('pending-zero', $pending, 30);
        $this->assertNull((new ReflectionMethod($controller, 'readLandingPayloadCache'))->invoke($controller, 'pending-zero'));
        $pending = $this->payload(1025);
        $pending['area6_portfolio']['cards'][] = ['key' => 'sml', 'realization_raw' => 1];
        $this->assertFalse($this->snapshotMatches($controller, $pending));
        $pending['area6_portfolio']['cards'][1] = ['key' => 'npl', 'realization_raw' => 1];
        $this->assertFalse($this->snapshotMatches($controller, $pending));
    }

    public function test_delete_reimport_and_snapshot_rebuild_never_promote_temporary_zero(): void
    {
        $controller = app(DashboardSimpananController::class);
        $savedRows = DB::table('dashboard_harian_snapshots')->get()->map(fn ($row) => (array) $row)->all();
        $lastValid = $this->publish($controller, $this->payload(1025));
        $this->assertSame($lastValid, Cache::get('durable'));

        DB::table('dashboard_harian_snapshots')->delete();
        ReportCacheVersion::bump('pinjaman');
        $duringDelete = $this->publish($controller, $this->payload(0));
        $this->assertSame(1025.0, data_get($duringDelete, 'area6_portfolio.cards.0.realization_raw'));
        $this->assertTrue(data_get($duringDelete, 'meta.refresh_pending'));
        $this->assertSame($lastValid, Cache::get('stable'));
        $this->assertSame($lastValid, Cache::get('durable'));
        $this->travel(31)->seconds();
        $this->assertNull(Cache::get('primary'));

        // Source import completed, but its asynchronous snapshot has not rebuilt yet.
        ReportCacheVersion::bump('pinjaman');
        $duringRebuild = $this->publish($controller, $this->payload(0));
        $this->assertTrue(data_get($duringRebuild, 'meta.refresh_pending'));
        $this->assertSame($lastValid, Cache::get('durable'));

        DB::table('dashboard_harian_snapshots')->insert($savedRows);
        DB::table('dashboard_harian_snapshots')->where('kanca_key', 'ngawi')->update(['total_os_non_commercial' => 1000]);
        ReportCacheVersion::bump('harian');
        $this->assertNull((new ReflectionMethod($controller, 'readLandingPayloadCache'))->invoke($controller, 'primary'));
        $recovered = $this->publish($controller, $this->payload(1125));
        $this->assertFalse(data_get($recovered, 'meta.refresh_pending', false));
        foreach (['primary', 'latest', 'stable', 'durable'] as $key) {
            $this->assertSame($recovered, Cache::get($key));
        }
        $this->travelBack();
    }

    public function test_no_source_zero_snapshot_is_pending_but_real_zero_can_be_saved(): void
    {
        $controller = app(DashboardSimpananController::class);
        DB::table('dashboard_harian_snapshots')->update(['total_os_non_commercial' => 0, 'source_loan_row_count' => 0]);
        $pending = $this->publish($controller, $this->payload(0));
        $this->assertTrue(data_get($pending, 'meta.refresh_pending'));
        $this->assertNull(Cache::get('durable'));
        $this->assertNull(Cache::get('stable'));

        DB::table('dashboard_harian_snapshots')->update(['source_loan_row_count' => 1]);
        $validZero = $this->publish($controller, $this->payload(0));
        $this->assertSame(0.0, data_get($validZero, 'area6_portfolio.cards.0.realization_raw'));
        $this->assertFalse(data_get($validZero, 'meta.refresh_pending', false));
        $this->assertSame($validZero, Cache::get('durable'));
    }

    public function test_worker_started_before_mutation_cannot_overwrite_last_valid_cache(): void
    {
        $controller = app(DashboardSimpananController::class);
        $oldVersion = (new ReflectionMethod($controller, 'reportCacheVersion'))->invoke($controller);
        ReportCacheVersion::bump('pinjaman');
        $current = $this->publish($controller, $this->payload(1025));
        $staleWorker = $this->publish($controller, $this->payload(1025), $oldVersion);
        $this->assertTrue(data_get($staleWorker, 'meta.refresh_pending'));
        $this->assertSame($current, Cache::get('durable'));
        $this->assertSame($current, Cache::get('stable'));
    }

    public function test_portfolio_cache_uses_same_source_and_generation_guards(): void
    {
        $controller = app(DashboardSimpananController::class);
        $save = new ReflectionMethod($controller, 'cacheLandingPortfolioPayload');
        $version = (new ReflectionMethod($controller, 'reportCacheVersion'))->invoke($controller);
        $valid = $save->invoke($controller, 'portfolio', 'portfolio-latest', 'portfolio-stable', $this->payload(1025)['area6_portfolio'], $version);
        DB::table('dashboard_harian_snapshots')->delete();
        $pending = $save->invoke($controller, 'portfolio', 'portfolio-latest', 'portfolio-stable', $this->payload(0)['area6_portfolio'], $version);
        $this->assertTrue(data_get($pending, 'meta.refresh_pending'));
        $this->assertSame($valid, Cache::get('portfolio-stable'));
        $this->travel(31)->seconds();
        $this->assertNull(Cache::get('portfolio'));
        ReportCacheVersion::bump('pinjaman');
        $pending = $save->invoke($controller, 'portfolio', 'portfolio-latest', 'portfolio-stable', $this->payload(1025)['area6_portfolio'], $version);
        $this->assertTrue(data_get($pending, 'meta.refresh_pending'));
        $this->assertSame($valid, Cache::get('portfolio-latest'));
        $this->travelBack();
    }

    public function test_same_totals_after_reimport_still_schedule_refresh_for_details(): void
    {
        $controller = app(DashboardSimpananController::class);
        $valid = $this->publish($controller, $this->payload(1025));
        ReportCacheVersion::bump('pinjaman');
        $read = (new ReflectionMethod($controller, 'readLandingPayloadCache'))->invoke($controller, 'durable', true);
        $this->assertTrue(data_get($read, 'meta.refresh_pending'));
        $this->assertSame(1025.0, data_get($read, 'area6_portfolio.cards.0.realization_raw'));
        $this->assertSame($valid, Cache::get('durable'));
    }

    public function test_reading_last_valid_primary_without_snapshot_schedules_background_recovery(): void
    {
        Queue::fake();
        $controller = app(DashboardSimpananController::class);
        $valid = $this->publish($controller, $this->payload(1025));
        $source = (new \ReflectionClass($controller))->getConstant('LANDING_SOURCE_CACHE_VERSION');
        $version = (new ReflectionMethod($controller, 'reportCacheVersion'))->invoke($controller);
        $riskVersion = app(LandingLoanRiskCacheService::class)->generation();
        $key = 'dashboard_simpanan:payload:2026-10-14:'.$source.':v'.$version.':risk-v'.$riskVersion;
        Cache::put($key, $valid, 3600);
        DB::table('dashboard_harian_snapshots')->delete();

        $pending = (new ReflectionMethod($controller, 'buildDashboardPayload'))->invoke($controller, '2026-10-14');

        $this->assertTrue(data_get($pending, 'meta.refresh_pending'));
        $this->assertSame(1025.0, data_get($pending, 'area6_portfolio.cards.0.realization_raw'));
        Queue::assertPushed(WarmDashboardSimpananCacheJob::class, fn ($job): bool => $job->type === 'dashboard-payload'
            && $job->context['payloadCacheKey'] === $key);
        $this->assertSame($valid, Cache::get('durable'));
    }

    private function publish(DashboardSimpananController $controller, array $payload, ?string $version = null): array
    {
        $version ??= (new ReflectionMethod($controller, 'reportCacheVersion'))->invoke($controller);

        return (new ReflectionMethod($controller, 'cacheDashboardPayload'))->invoke($controller, 'primary', 'latest', 'stable', $payload, $version, 'durable');
    }

    private function payload(float $os): array
    {
        return ['period' => '2026-10-14', 'area6_portfolio' => ['period' => '2026-10-14', 'cards' => [['key' => 'os', 'realization_raw' => $os]]]];
    }

    private function snapshotMatches(DashboardSimpananController $controller, array $payload): bool
    {
        return (new ReflectionMethod($controller, 'landingLoanSnapshotMatches'))->invoke($controller, $payload);
    }
}
