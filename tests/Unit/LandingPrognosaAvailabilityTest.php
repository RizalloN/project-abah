<?php

namespace Tests\Unit;

use App\Services\Presentation\PresentationPrognosaWeeklyService;
use App\Support\LandingPrognosaCardService;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class LandingPrognosaAvailabilityTest extends TestCase
{
    public function test_previous_spreadsheet_cache_does_not_hide_the_new_source(): void
    {
        Cache::put('landing:prognosa:v1', ['meta' => ['available' => false]], now()->addDays(7));
        Cache::put('landing:prognosa:v1:last-valid', ['meta' => ['available' => false]], now()->addDays(7));
        $source = Mockery::mock(PresentationPrognosaWeeklyService::class);
        $source->shouldReceive('payload')->once()->with(false)->andReturn($this->payload());

        $this->assertSame('125', $this->forecast(new LandingPrognosaCardService($source))['value']);
    }

    public function test_expired_forecast_is_returned_without_waiting_for_remote_refresh(): void
    {
        $source = Mockery::mock(PresentationPrognosaWeeklyService::class);
        $source->shouldReceive('payload')->once()->with(false)->andReturn($this->payload());
        $service = new LandingPrognosaCardService($source);
        $this->assertSame('125', $this->forecast($service)['value']);
        $this->travel(11)->minutes();

        $forecast = $this->forecast($service);
        $this->assertSame('125', $forecast['value']);
        $this->assertTrue($forecast['stale']);
        $this->assertTrue(Cache::has('landing:prognosa:v2:1DQSxk8-9U2pXwOQGiLQK4PW2Ao3ROhCYtvTy7yo12DM:refresh-pending'));
        $this->assertSame('125', $this->forecast($service)['value']);
    }

    public function test_failed_background_refresh_preserves_last_valid_forecast_and_throttles_retries(): void
    {
        $source = Mockery::mock(PresentationPrognosaWeeklyService::class);
        $source->shouldReceive('payload')->once()->with(false)->andReturn($this->payload());
        $source->shouldReceive('payload')->once()->with(true)->andThrow(new \RuntimeException('offline'));
        $service = new LandingPrognosaCardService($source);
        $this->forecast($service);
        $this->travel(11)->minutes();
        $this->forecast($service);
        app(DeferredCallbackCollection::class)->invoke();

        $this->assertSame('125', $this->forecast($service)['value']);
        $this->assertTrue($this->forecast($service)['stale']);
        $this->assertFalse(data_get(Cache::get('landing:prognosa:v2:1DQSxk8-9U2pXwOQGiLQK4PW2Ao3ROhCYtvTy7yo12DM:last-valid'), 'meta.stale', false));
        $this->assertFalse(Cache::has('landing:prognosa:v2:1DQSxk8-9U2pXwOQGiLQK4PW2Ao3ROhCYtvTy7yo12DM:refresh-pending'));
    }

    public function test_unavailable_cold_source_is_short_cached(): void
    {
        $source = Mockery::mock(PresentationPrognosaWeeklyService::class);
        $source->shouldReceive('payload')->once()->with(false)->andThrow(new \RuntimeException('offline'));
        $service = new LandingPrognosaCardService($source);
        $dashboard = ['area6_portfolio' => ['scopes' => []]];
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $result = $service->decorateDashboard($dashboard, 'area6');
            $this->assertFalse(data_get($result, 'area6_portfolio.prognosa.available'));
        }
        $this->assertFalse(Cache::has('landing:prognosa:v2:1DQSxk8-9U2pXwOQGiLQK4PW2Ao3ROhCYtvTy7yo12DM:last-valid'));
    }

    public function test_successful_background_refresh_replaces_the_previous_forecast(): void
    {
        $updated = $this->payload();
        $updated['scopes']['area6']['metrics']['os']['value'] = 150_000_000;
        $source = Mockery::mock(PresentationPrognosaWeeklyService::class);
        $source->shouldReceive('payload')->once()->with(false)->andReturn($this->payload());
        $source->shouldReceive('payload')->once()->with(true)->andReturn($updated);
        $service = new LandingPrognosaCardService($source);
        $this->forecast($service);
        $this->travel(11)->minutes();
        $this->assertSame('125', $this->forecast($service)['value']);
        app(DeferredCallbackCollection::class)->invoke();

        $this->assertSame('150', $this->forecast($service)['value']);
        $this->assertFalse($this->forecast($service)['stale']);
    }

    private function forecast(LandingPrognosaCardService $service): array
    {
        $result = $service->decorateDashboard([
            'area6_portfolio' => [
                'default_scope' => 'area6',
                'scopes' => ['area6' => ['cards' => [['key' => 'os', 'realization_raw' => 100_000_000]]]],
            ],
        ], 'area6');

        return data_get($result, 'area6_portfolio.cards.0.prognosa');
    }

    private function payload(): array
    {
        return [
            'meta' => ['available' => true, 'week_number' => 1, 'week_label' => 'W1'],
            'scopes' => ['area6' => ['metrics' => ['os' => ['available' => true, 'value' => 125_000_000]]]],
        ];
    }
}
