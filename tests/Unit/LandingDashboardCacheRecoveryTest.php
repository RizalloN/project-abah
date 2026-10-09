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
}
