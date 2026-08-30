<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Presentation\PresentationPrognosaWeeklyService;
use App\Support\LandingPrognosaCardService;
use Mockery;
use Tests\TestCase;

class LandingPrognosaCardServiceTest extends TestCase
{
    public function test_it_decorates_each_landing_scope_with_weekly_prognosa(): void
    {
        $service = new LandingPrognosaCardService(
            app(PresentationPrognosaWeeklyService::class)
        );

        $portfolio = [
            'default_scope' => 'area6',
            'cards' => [],
            'scopes' => [
                'area6' => ['cards' => [
                    $this->card('os', 120_000_000),
                    $this->card('sml', 80_000_000),
                    $this->card('npl', 40_000_000),
                    $this->card('recovery', 60_000_000),
                ]],
                'sme' => ['cards' => [
                    $this->card('os', 75_000_000),
                    $this->card('sml', 20_000_000),
                    $this->card('npl', 10_000_000),
                ]],
            ],
        ];
        $payload = [
            'meta' => [
                'available' => true,
                'week_number' => 4,
                'week_label' => 'W4',
                'label' => 'Prognosa W4 Agustus 2026',
            ],
            'scopes' => [
                'area6' => ['metrics' => [
                    'os' => $this->metric(100_000_000),
                    'sml' => $this->metric(100_000_000),
                    'npl' => $this->metric(50_000_000),
                    'recovery' => $this->metric(50_000_000),
                    'sme_os' => $this->metric(75_000_000),
                    'sme_sml' => $this->metric(25_000_000),
                    'sme_npl' => $this->metric(12_500_000),
                ]],
            ],
        ];

        $decorated = $service->decoratePortfolio($portfolio, $payload);

        $this->assertSame('W4', data_get($decorated, 'prognosa.week_label'));
        $this->assertSame('100', data_get($decorated, 'scopes.area6.cards.0.prognosa.value'));
        $this->assertSame('120,00%', data_get($decorated, 'scopes.area6.cards.0.prognosa.achievement'));
        $this->assertSame('125,00%', data_get($decorated, 'scopes.area6.cards.1.prognosa.achievement'));
        $this->assertSame('125,00%', data_get($decorated, 'scopes.area6.cards.2.prognosa.achievement'));
        $this->assertSame('120,00%', data_get($decorated, 'scopes.area6.cards.3.prognosa.achievement'));
        $this->assertSame('75', data_get($decorated, 'scopes.sme.cards.0.prognosa.value'));
        $this->assertSame(
            data_get($decorated, 'scopes.area6.cards'),
            data_get($decorated, 'cards')
        );
    }

    public function test_it_uses_the_authenticated_branch_forecast_scope(): void
    {
        $service = new LandingPrognosaCardService(
            app(PresentationPrognosaWeeklyService::class)
        );
        $portfolio = [
            'default_scope' => 'area6',
            'scopes' => [
                'area6' => ['cards' => [$this->card('os', 150_000_000)]],
            ],
        ];
        $payload = [
            'meta' => ['available' => true, 'week_number' => 4, 'week_label' => 'W4'],
            'scopes' => [
                'area6' => ['metrics' => ['os' => $this->metric(100_000_000)]],
                'KC MADIUN' => ['metrics' => ['os' => $this->metric(125_000_000)]],
            ],
        ];

        $decorated = $service->decoratePortfolio($portfolio, $payload, 'KC MADIUN');

        $this->assertSame('KC MADIUN', data_get($decorated, 'prognosa.scope'));
        $this->assertSame('125', data_get($decorated, 'cards.0.prognosa.value'));
        $this->assertSame('120,00%', data_get($decorated, 'cards.0.prognosa.achievement'));
    }

    public function test_it_exposes_weekly_forecasts_with_snapshot_actuals_at_each_cutoff(): void
    {
        $service = new LandingPrognosaCardService(
            app(PresentationPrognosaWeeklyService::class)
        );
        $portfolio = [
            'default_scope' => 'area6',
            'scopes' => [
                'area6' => [
                    'cards' => [
                        $this->card('os', 150_000_000),
                        $this->card('sml', 70_000_000),
                    ],
                    'prognosa_actuals' => [
                        'W1' => $this->actuals('2026-08-07', 90_000_000, 80_000_000),
                        'W2' => $this->actuals('2026-08-14', 120_000_000, 75_000_000),
                        'W3' => $this->actuals('2026-08-21', 140_000_000, 72_000_000),
                        'W4' => $this->actuals('2026-08-26', 150_000_000, 70_000_000, false),
                    ],
                ],
            ],
        ];
        $payload = [
            'meta' => [
                'available' => true,
                'week_number' => 4,
                'week_label' => 'W4',
                'available_weeks' => [1, 2, 3, 4],
            ],
            'scopes' => [
                'area6' => [
                    'available' => true,
                    'metrics' => [
                        'os' => $this->metric(130_000_000),
                        'sml' => $this->metric(100_000_000),
                    ],
                    'weeks' => [
                        'W1' => ['available' => true, 'metrics' => ['os' => $this->metric(100_000_000), 'sml' => $this->metric(100_000_000)]],
                        'W2' => ['available' => true, 'metrics' => ['os' => $this->metric(110_000_000), 'sml' => $this->metric(95_000_000)]],
                        'W3' => ['available' => true, 'metrics' => ['os' => $this->metric(120_000_000), 'sml' => $this->metric(90_000_000)]],
                        'W4' => ['available' => true, 'metrics' => ['os' => $this->metric(130_000_000), 'sml' => $this->metric(100_000_000)]],
                    ],
                ],
            ],
        ];

        $decorated = $service->decoratePortfolio($portfolio, $payload);

        $this->assertSame(['W1', 'W2', 'W3', 'W4'], data_get($decorated, 'prognosa.available_weeks'));
        $this->assertSame('W4', data_get($decorated, 'scopes.area6.prognosa.active_week'));
        $this->assertSame('90', data_get($decorated, 'scopes.area6.cards.0.prognosa.weeks.W1.actual_value'));
        $this->assertSame('90,00%', data_get($decorated, 'scopes.area6.cards.0.prognosa.weeks.W1.achievement'));
        $this->assertSame('125,00%', data_get($decorated, 'scopes.area6.cards.1.prognosa.weeks.W1.achievement'));
        $this->assertSame('26 Agt 26', data_get($decorated, 'scopes.area6.cards.0.prognosa.actual_label'));
        $this->assertSame('115,38%', data_get($decorated, 'scopes.area6.cards.0.prognosa.achievement'));
        $this->assertFalse((bool) data_get($decorated, 'scopes.area6.prognosa.weeks.W4.is_elapsed'));
    }

    public function test_dashboard_decoration_resolves_the_logged_in_branch_scope(): void
    {
        $this->actingAs(new User(['pn' => '0045']));

        $prognosa = Mockery::mock(PresentationPrognosaWeeklyService::class);
        $prognosa->shouldReceive('payload')->once()->andReturn([
            'meta' => ['available' => true, 'week_number' => 4, 'week_label' => 'W4'],
            'scopes' => [
                'area6' => ['metrics' => ['os' => $this->metric(100_000_000)]],
                'KC MADIUN' => ['metrics' => ['os' => $this->metric(125_000_000)]],
            ],
        ]);

        $dashboard = (new LandingPrognosaCardService($prognosa))->decorateDashboard([
            'area6_portfolio' => [
                'default_scope' => 'area6',
                'scopes' => [
                    'area6' => ['cards' => [$this->card('os', 150_000_000)]],
                ],
            ],
        ]);

        $this->assertSame('KC MADIUN', data_get($dashboard, 'area6_portfolio.prognosa.scope'));
        $this->assertSame('125', data_get($dashboard, 'area6_portfolio.cards.0.prognosa.value'));
    }

    /** @return array<string, mixed> */
    private function card(string $key, float $realization): array
    {
        return [
            'key' => $key,
            'realization_raw' => $realization,
            'realization_value' => number_format($realization / 1_000_000, 0, ',', '.'),
        ];
    }

    /** @return array{available: true, value: float} */
    private function metric(float $value): array
    {
        return ['available' => true, 'value' => $value];
    }

    /** @return array<string, mixed> */
    private function actuals(string $date, float $os, float $sml, bool $elapsed = true): array
    {
        return [
            'position_label' => \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('d M y'),
            'cutoff_label' => \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('d M y'),
            'state_label' => $elapsed ? 'Tutup' : 'Berjalan',
            'is_elapsed' => $elapsed,
            'metrics' => ['os' => $os, 'sml' => $sml],
        ];
    }
}
