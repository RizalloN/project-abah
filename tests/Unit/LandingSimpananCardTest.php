<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Support\LandingPrognosaCardService;
use ReflectionMethod;
use Tests\TestCase;

class LandingSimpananCardTest extends TestCase
{
    public function test_landing_simpanan_route_is_registered_and_points_to_controller(): void
    {
        $route = app('router')->getRoutes()->getByName('dashboard.simpanan');

        $this->assertNotNull($route);
        $this->assertSame('dashboard/simpanan', $route->uri());
        $this->assertStringContainsString('@landingSimpanan', $route->getActionName());
        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_landing_simpanan_view_contract_has_three_cards_tabungan_deposito_giro(): void
    {
        $view = file_get_contents(resource_path('views/dashboard/simpanan.blade.php'));

        $this->assertStringContainsString('simpanan-hero', $view);
        $this->assertStringContainsString('simpanan-hero__svg', $view);
        $this->assertStringNotContainsString('class="kpi-strip"', $view);
        $this->assertStringContainsString('area6-card-grid--three', $view);
        $this->assertStringContainsString('bg-tabungan', $view);
        $this->assertStringContainsString('bg-deposito', $view);
        $this->assertStringContainsString('bg-giro', $view);
        $this->assertStringContainsString('class="ap-prognosa-strip"', $view);
        $this->assertStringContainsString('data-prognosa-week-select', $view);
        $this->assertStringContainsString('selectLandingPrognosaWeek', $view);
        $this->assertStringContainsString('data-area6-scope', $view);
        $this->assertStringContainsString('data-area6-content-scope', $view);
        $this->assertStringContainsString('simpanan-scope-title', $view);
        $this->assertStringContainsString('DtD', $view);
        $this->assertStringContainsString('MtD', $view);
        $this->assertStringContainsString('MtM', $view);
        $this->assertStringContainsString('YtD', $view);
        $this->assertStringContainsString('Keragaan Simpanan per Cabang Konsolidasi', $view);
        $this->assertStringContainsString('simpanan-table', $view);
    }

    public function test_build_simpanan_scope_card_calculates_metrics_correctly(): void
    {
        $controller = app(DashboardSimpananController::class);
        $method = new ReflectionMethod(DashboardSimpananController::class, 'buildSimpananScopeCard');

        $rows = collect([
            'tabungan_ritel' => [
                'values' => ['current' => 60_000_000_000, 'rka' => 50_000_000_000],
                'deltas' => ['dtd' => 1_000_000_000, 'mtd' => 5_000_000_000, 'mtm' => 4_000_000_000, 'ytd' => 10_000_000_000],
            ],
            'tabungan_mikro' => [
                'values' => ['current' => 40_000_000_000, 'rka' => 30_000_000_000],
                'deltas' => ['dtd' => 2_000_000_000, 'mtd' => 3_000_000_000, 'mtm' => 2_000_000_000, 'ytd' => 5_000_000_000],
            ],
        ]);

        $card = $method->invoke(
            $controller,
            'tabungan',
            'Tabungan',
            ['tabungan_ritel', 'tabungan_mikro'],
            $rows,
            '22 Agu 2026',
            'Agustus 2026',
            'blue',
            'fas fa-piggy-bank'
        );

        $this->assertSame('tabungan', $card['key']);
        $this->assertSame('TABUNGAN', $card['header_title']);
        $this->assertSame('100.000', $card['realization_value']);
        $this->assertSame('80.000', $card['target_value']);
        $this->assertSame('+20.000', $card['gap_value']);
        $this->assertSame('green', $card['gap_color']);
        $this->assertSame('blue', $card['tone']);
        $this->assertSame('fas fa-piggy-bank', $card['icon']);

        // Check deltas
        $this->assertArrayHasKey('dtd', $card['deltas']);
        $this->assertArrayHasKey('mtd', $card['deltas']);
        $this->assertArrayHasKey('mom', $card['deltas']);
        $this->assertArrayHasKey('ytd', $card['deltas']);
        $this->assertSame('+3.000', $card['deltas']['dtd']['value']);
        $this->assertSame('+8.000', $card['deltas']['mtd']['value']);
        $this->assertSame('+6.000', $card['deltas']['mom']['value']);
        $this->assertSame('+15.000', $card['deltas']['ytd']['value']);
    }

    public function test_build_simpanan_scope_card_handles_deficit_gap(): void
    {
        $controller = app(DashboardSimpananController::class);
        $method = new ReflectionMethod(DashboardSimpananController::class, 'buildSimpananScopeCard');

        $rows = collect([
            'deposito_total' => [
                'values' => ['current' => 70_000_000_000, 'rka' => 100_000_000_000],
                'deltas' => ['dtd' => -2_000_000_000, 'mtd' => -5_000_000_000, 'mtm' => -3_000_000_000, 'ytd' => -10_000_000_000],
            ],
        ]);

        $card = $method->invoke(
            $controller,
            'deposito',
            'Deposito',
            ['deposito_total'],
            $rows,
            '22 Agu 2026',
            'Agustus 2026',
            'teal',
            'fas fa-vault'
        );

        $this->assertSame('deposito', $card['key']);
        $this->assertSame('70.000', $card['realization_value']);
        $this->assertSame('100.000', $card['target_value']);
        $this->assertSame('(30.000)', $card['gap_value']);
        $this->assertSame('red', $card['gap_color']);
        $this->assertSame('(2.000)', $card['deltas']['dtd']['value']);
    }

    public function test_prognosa_service_is_target_achieved_logic(): void
    {
        $service = app(LandingPrognosaCardService::class);
        $method = new ReflectionMethod(LandingPrognosaCardService::class, 'isTargetAchieved');

        // Untuk produk simpanan (lowerIsBetter = false):
        // Realisasi lebih besar atau sama dengan target adalah tercapai (true)
        $this->assertTrue($method->invoke($service, 120_000_000.0, 100_000_000.0, false));
        $this->assertTrue($method->invoke($service, 100_000_000.0, 100_000_000.0, false));
        $this->assertFalse($method->invoke($service, 80_000_000.0, 100_000_000.0, false));

        // Untuk NPL/SML (lowerIsBetter = true):
        // Realisasi lebih kecil atau sama dengan target adalah tercapai (true)
        $this->assertTrue($method->invoke($service, 80_000_000.0, 100_000_000.0, true));
        $this->assertFalse($method->invoke($service, 120_000_000.0, 100_000_000.0, true));
    }

    public function test_landing_simpanan_view_renders_successfully_with_sample_data(): void
    {
        $this->actingAs(new \App\Models\User(['id' => 1, 'pn' => '12345678', 'name' => 'Admin Test', 'role' => 'admin']));

        $view = view('dashboard.simpanan', [
            'dashboard' => [
                'area6_portfolio' => [
                    'cards' => [],
                    'default_scope' => 'area6',
                    'scopes' => [],
                    'branches' => [],
                    'period_label' => '22 Agu 2026',
                ],
                'kpi_summary' => [
                    'total_simpanan' => [
                        'value' => '100.000',
                        'target' => '90.000',
                        'pct' => '111,11%',
                        'gap' => '+10.000',
                        'gap_positive' => true,
                        'trend_mtm' => '+5.000',
                        'trend_ytd' => '+15.000',
                    ],
                    'casa' => [
                        'value' => '60.000',
                        'ratio' => '60,00%',
                        'tabungan_share' => '50,00%',
                        'giro_share' => '10,00%',
                    ],
                ],
            ],
            'periods' => collect(['2026-08-22']),
            'selectedPeriod' => '2026-08-22',
            'landingBranchOptions' => ['area6' => 'Area 6 (Semua Cabang)'],
            'selectedLandingBranch' => 'area6',
            'landingBranchLocked' => false,
            'landingBranchLabel' => 'Area 6',
        ]);

        $html = $view->render();
        $this->assertStringContainsString('Landing Page Simpanan', $html);
        $this->assertStringContainsString('simpanan-hero', $html);
        $this->assertStringContainsString('simpanan-hero__svg', $html);
        $this->assertStringNotContainsString('kpi-strip', $html);
        $this->assertStringNotContainsString('Pertumbuhan MtM', $html);
        $this->assertStringNotContainsString('Pertumbuhan YtD', $html);
    }
}
