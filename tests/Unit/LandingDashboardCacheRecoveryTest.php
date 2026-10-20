<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

class LandingDashboardCacheRecoveryTest extends TestCase
{
    public function test_old_empty_cache_is_rejected_but_zero_balance_with_period_is_valid(): void
    {
        config(['cache.default' => 'array']);
        $controller = app(DashboardSimpananController::class);
        $read = new ReflectionMethod($controller, 'readLandingPayloadCache');
        Cache::put('landing-empty', ['period' => null], 3600);
        $this->assertNull($read->invoke($controller, 'landing-empty'));
        $this->assertFalse(Cache::has('landing-empty'));
        $valid = ['period' => '2026-10-07', 'balance' => 0];
        Cache::put('landing-valid', $valid, 3600);
        $this->assertSame($valid, $read->invoke($controller, 'landing-valid', true));
    }

    public function test_landing_cards_from_the_old_mtm_basis_are_rejected_in_primary_and_durable_cache(): void
    {
        config(['cache.default' => 'array']);
        $controller = app(DashboardSimpananController::class);
        $read = new ReflectionMethod($controller, 'readLandingPayloadCache');
        foreach (['live_reports' => [['key' => 'simpanan']], 'kpi_summary' => ['total_simpanan' => []]] as $type => $cards) {
            foreach ([false, true] as $durable) {
                Cache::put('old-mtm', ['period' => '2026-10-17', $type => $cards], 3600);
                $this->assertNull($read->invoke($controller, 'old-mtm', $durable));
                $this->assertFalse(Cache::has('old-mtm'));
            }
        }
    }

    public function test_pending_payload_is_bounded_and_cannot_be_used_as_durable(): void
    {
        config(['cache.default' => 'array']);
        $controller = app(DashboardSimpananController::class);
        $read = new ReflectionMethod($controller, 'readLandingPayloadCache');
        $pending = ['period' => '2026-10-07', 'meta' => [
            'refresh_pending' => true, 'retry_after' => now()->addSeconds(30)->timestamp,
        ]];
        Cache::put('landing-pending', $pending, 3600);
        $this->assertSame($pending, $read->invoke($controller, 'landing-pending'));
        $this->travel(31)->seconds();
        $this->assertNull($read->invoke($controller, 'landing-pending'));
        Cache::put('landing-pending', $pending, 3600);
        $this->assertNull($read->invoke($controller, 'landing-pending', true));
        $this->travelBack();
    }

    public function test_mtm_migration_preserves_retry_backoff_for_an_empty_pending_landing(): void
    {
        config(['cache.default' => 'array']);
        $controller = app(DashboardSimpananController::class);
        $payload = ['period' => null, 'live_reports' => [], 'meta' => [
            'refresh_pending' => true, 'retry_after' => now()->addSeconds(30)->timestamp,
        ]];
        Cache::put('empty-pending-mtm', $payload, 30);
        $this->assertSame($payload, (new ReflectionMethod($controller, 'readLandingPayloadCache'))->invoke($controller, 'empty-pending-mtm'));
    }
}
