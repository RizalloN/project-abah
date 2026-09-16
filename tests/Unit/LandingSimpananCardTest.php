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
        $this->assertStringContainsString('container: simpanan-landing / inline-size', $view);
        $this->assertStringContainsString('@container simpanan-landing (max-width: 1100px)', $view);
        $this->assertStringContainsString('@container simpanan-landing (max-width: 620px)', $view);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $view);
        $this->assertStringContainsString('aria-pressed="{{ $scopeKey === $area6DefaultScope', $view);
        $this->assertStringContainsString('DtD', $view);
        $this->assertStringContainsString('MtD', $view);
        $this->assertStringContainsString('MtM', $view);
        $this->assertStringContainsString('YtD', $view);
        $this->assertStringNotContainsString('Keragaan Simpanan per Cabang Konsolidasi', $view);
        $this->assertStringNotContainsString('simpanan-table', $view);
        $this->assertStringNotContainsString('simpananDonutChart', $view);
        $this->assertStringContainsString('simpanan-sekat-stack', $view);
        $this->assertStringContainsString('trend-position-card', $view);
        $this->assertStringContainsString('TREND POSISI (Rp Juta)', $view);
        $this->assertStringContainsString('simpanan-trend-svg-tabungan', $view);
        $this->assertStringContainsString('simpanan-trend-svg-deposito', $view);
        $this->assertStringContainsString('simpanan-trend-svg-giro', $view);
        $this->assertStringContainsString('simpanan-sekat-card--monthly', $view);
        $this->assertStringContainsString('simpananMonthlyChart', $view);
        $this->assertStringNotContainsString('scc-monthly-strip', $view);
        $this->assertStringNotContainsString('simpanan-monthly-strip', $view);
        $this->assertStringContainsString('1. OPTIMALISASI DIGITAL CHANNEL', $view);
        $this->assertStringContainsString('digitalChannelTriggers', $view);
        $this->assertStringContainsString('dcDynamicTable', $view);
        $this->assertStringContainsString('2. REKENING TRANSAKSI DEBITUR & REKENING DORMANT', $view);
        $this->assertStringContainsString('sekat-rekening-debitur-dormant', $view);
        $this->assertStringContainsString('casaViewToggle', $view);
        $this->assertStringContainsString('casa-table-cabang', $view);
        $this->assertStringContainsString('casa-table-segmen', $view);
        $this->assertStringContainsString('3. PENINGKATAN PAYROLL BERKUALITAS', $view);
        $this->assertStringContainsString('sekat-peningkatan-payroll', $view);
        $this->assertStringContainsString('simpanan-payroll-card', $view);
        $this->assertStringContainsString('payroll-card-body', $view);
        $this->assertStringContainsString('payrollBranchTable', $view);
        $this->assertStringContainsString('payrollNominatifModal', $view);
        $this->assertStringContainsString('btn-open-nominatif', $view);
        $this->assertStringContainsString('payrollModalTable', $view);
        $this->assertStringContainsString('payrollModalTabs', $view);
        $this->assertStringContainsString('payrollModalSearch', $view);
        $this->assertStringNotContainsString('Spreadsheet Pipeline', $view);
        $this->assertStringNotContainsString('payrollBranchFilter', $view);
        $this->assertStringContainsString('4. ECOSYSTEM & VALUE CHAIN', $view);
        $this->assertStringContainsString('sekat-ecosystem-value-chain', $view);
        $this->assertStringContainsString('simpanan-ecosystem-card', $view);
        $this->assertStringContainsString('ecosystem-card-body', $view);
        $this->assertStringContainsString('ecosystemBranchTable', $view);
        $this->assertStringContainsString('ecosystemNominatifModal', $view);
        $this->assertStringContainsString('btn-open-ecosystem-nominatif', $view);
        $this->assertStringContainsString('ecosystemModalTable', $view);
        $this->assertStringContainsString('ecosystemModalTabs', $view);
        $this->assertStringContainsString('ecosystemModalSearch', $view);
        $this->assertStringContainsString('5. PERUSAHAAN ANAK', $view);
        $this->assertStringContainsString('sekat-perusahaan-anak', $view);
        $this->assertStringContainsString('simpanan-perusahaan-anak-card', $view);
        $this->assertStringContainsString('perusahaan-anak-card-body', $view);
        $this->assertStringContainsString('perusahaanAnakBranchTable', $view);
        $this->assertStringContainsString('perusahaanAnakNominatifModal', $view);
        $this->assertStringContainsString('btn-open-pa-nominatif', $view);
        $this->assertStringContainsString('perusahaanAnakModalTable', $view);
        $this->assertStringContainsString('paModalTabs', $view);
        $this->assertStringContainsString('paModalSearch', $view);
    }

    public function test_build_casa_debitur_strategy_payload_structure(): void
    {
        $controller = app(DashboardSimpananController::class);
        $method = new \ReflectionMethod(DashboardSimpananController::class, 'buildCasaDebiturStrategyPayload');

        $result = $method->invoke($controller, '2026-09-08');

        $this->assertIsArray($result);
        if (!empty($result)) {
            $this->assertArrayHasKey('period_label', $result);
            $this->assertArrayHasKey('total', $result);
            $this->assertArrayHasKey('branches', $result);
            $this->assertArrayHasKey('segments', $result);
            $this->assertCount(4, $result['branches']);
            $this->assertCount(3, $result['segments']);
        }
    }

    public function test_build_dormant_strategy_payload_structure(): void
    {
        $controller = app(DashboardSimpananController::class);
        $method = new \ReflectionMethod(DashboardSimpananController::class, 'buildDormantStrategyPayload');

        $result = $method->invoke($controller, '2026-09-08');

        $this->assertIsArray($result);
        if (!empty($result)) {
            $this->assertArrayHasKey('dates', $result);
            $this->assertArrayHasKey('total', $result);
            $this->assertArrayHasKey('branches', $result);
            $this->assertCount(4, $result['branches']);
            $this->assertArrayHasKey('current', $result['dates']);
            $this->assertArrayHasKey('mtd', $result['dates']);
            $this->assertArrayHasKey('ytd', $result['dates']);
        }
    }

    public function test_build_payroll_quality_strategy_payload_structure(): void
    {
        $controller = app(DashboardSimpananController::class);
        $result = $controller->buildPayrollQualityStrategyPayload('2026-09-08');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('sheet_url', $result);

        $summary = $result['summary'];
        $this->assertArrayHasKey('total_perusahaan', $summary);
        $this->assertArrayHasKey('total_potensi', $summary);
        $this->assertArrayHasKey('total_realisasi', $summary);
        $this->assertArrayHasKey('total_kunjungan', $summary);
        $this->assertArrayHasKey('persen_kunjungan', $summary);
        $this->assertArrayHasKey('branches', $summary);

        $this->assertEquals(29, $summary['total_perusahaan']);
        $this->assertEquals(11663, $summary['total_potensi']);
        $this->assertEquals(1610, $summary['total_realisasi']);
        $this->assertEquals(19, $summary['total_kunjungan']);
        $this->assertCount(29, $result['rows']);
        $this->assertEquals('Yayasan Siti Walidah', $result['rows'][0]['nama']);
        $this->assertEquals(1578, $result['rows'][0]['potensi']);
        $this->assertEquals('ROYAL REGENT M', $result['rows'][1]['nama']);
        $this->assertEquals(1427, $result['rows'][1]['potensi']);
        $this->assertGreaterThanOrEqual($result['rows'][1]['potensi'], $result['rows'][0]['potensi']);
        $this->assertGreaterThanOrEqual($result['rows'][2]['potensi'], $result['rows'][1]['potensi']);
    }

    public function test_build_perusahaan_anak_strategy_payload_structure(): void
    {
        $controller = app(DashboardSimpananController::class);
        $result = $controller->buildPerusahaanAnakStrategyPayload('2026-09-08');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('sheet_url', $result);

        $summary = $result['summary'];
        $this->assertEquals(5, $summary['card_number'] ?? 0);
        $this->assertEquals('5. PERUSAHAAN ANAK', $summary['title'] ?? '');
        $this->assertArrayHasKey('total_pipeline', $summary);
        $this->assertArrayHasKey('total_sudah', $summary);
        $this->assertArrayHasKey('total_belum', $summary);
        $this->assertArrayHasKey('persen_akuisisi', $summary);
        $this->assertArrayHasKey('total_saldo_september', $summary);
        $this->assertArrayHasKey('branches', $summary);
        $this->assertArrayHasKey('entities', $summary);

        $this->assertEquals(6, $summary['total_pipeline']);
        $this->assertEquals(1, $summary['total_sudah']);
        $this->assertEquals(5, $summary['total_belum']);
        $this->assertEquals(16.7, $summary['persen_akuisisi']);
        $this->assertEquals(628507.0, (float) $summary['total_saldo_september']);
        $this->assertCount(6, $result['rows']);

        // Branches structure
        $this->assertArrayHasKey('KC Madiun', $summary['branches']);
        $this->assertArrayHasKey('KC Magetan', $summary['branches']);
        $this->assertArrayHasKey('KC Ngawi', $summary['branches']);
        $this->assertArrayHasKey('KC Ponorogo', $summary['branches']);
        $this->assertEquals(4, $summary['branches']['KC Madiun']['total_pipeline']);
        $this->assertEquals(1, $summary['branches']['KC Madiun']['total_sudah']);
        $this->assertEquals(2, $summary['branches']['KC Ponorogo']['total_pipeline']);

        // First row is the acquired partner with active balance
        $firstRow = $result['rows'][0];
        $this->assertEquals('Surya Indah Madiun', $firstRow['partner']);
        $this->assertEquals('Sudah Terakuisisi', $firstRow['status']);
        $this->assertTrue($firstRow['is_terakuisisi']);
        $this->assertEquals('KC Madiun', $firstRow['kc']);
        $this->assertEquals('MMP3270', $firstRow['cif']);
        $this->assertEquals(628507.0, (float) $firstRow['saldo_september']);
        $this->assertSame('Rp 628.507', $firstRow['saldo_september_fmt']);
    }

    public function test_build_perusahaan_anak_strategy_fallback_to_baseline(): void
    {
        \Illuminate\Support\Facades\Cache::forget('dashboard_simpanan_perusahaan_anak_strategy');
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response('Error', 500),
        ]);

        $controller = app(DashboardSimpananController::class);
        $result = $controller->buildPerusahaanAnakStrategyPayload('2026-09-08');

        $this->assertIsArray($result);
        $this->assertEquals(6, $result['summary']['total_pipeline']);
        $this->assertEquals(1, $result['summary']['total_sudah']);
        $this->assertCount(6, $result['rows']);
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

        $controller = app(DashboardSimpananController::class);
        $payrollPayload = $controller->buildPayrollQualityStrategyPayload('2026-08-22');
        $perusahaanAnakPayload = $controller->buildPerusahaanAnakStrategyPayload('2026-08-22');
        $ecosystemPayload = $controller->buildEcosystemValueChainStrategyPayload('2026-08-22');

        $view = view('dashboard.simpanan', [
            'dashboard' => [
                'payroll_quality_strategy' => $payrollPayload,
                'perusahaan_anak_strategy' => $perusahaanAnakPayload,
                'ecosystem_value_chain_strategy' => $ecosystemPayload,
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
        $this->assertStringContainsString('simpanan-sekat-stack', $html);
        $this->assertStringContainsString('trend-position-card', $html);
        $this->assertStringContainsString('TREND POSISI (Rp Juta)', $html);
        $this->assertStringContainsString('simpananMonthlyChart', $html);
        $this->assertStringContainsString('1. OPTIMALISASI DIGITAL CHANNEL', $html);
        $this->assertStringContainsString('digitalChannelTriggers', $html);
        $this->assertStringContainsString('2. REKENING TRANSAKSI DEBITUR & REKENING DORMANT', $html);
        $this->assertStringContainsString('sekat-rekening-debitur-dormant', $html);
        $this->assertStringContainsString('3. PENINGKATAN PAYROLL BERKUALITAS', $html);
        $this->assertStringContainsString('sekat-peningkatan-payroll', $html);
        $this->assertStringContainsString('Hakim Plastik', $html);
        $this->assertStringContainsString('PT GROW FOREVER GARMENT', $html);
        $this->assertStringContainsString('SPPG KAB PONOROGO', $html);
        $this->assertStringContainsString('1.610', $html);
        $this->assertStringContainsString('11.663', $html);
        $this->assertStringContainsString('4. ECOSYSTEM & VALUE CHAIN', $html);
        $this->assertStringContainsString('sekat-ecosystem-value-chain', $html);
        $this->assertStringContainsString('2.069', $html);
        $this->assertStringContainsString('5. PERUSAHAAN ANAK', $html);
        $this->assertStringContainsString('sekat-perusahaan-anak', $html);
        $this->assertStringContainsString('Surya Indah Madiun', $html);
        $this->assertStringContainsString('BAROKAH MOBIL', $html);
    }

    public function test_build_simpanan_monthly_timeseries_structure(): void
    {
        $controller = app(DashboardSimpananController::class);
        $method = new \ReflectionMethod(DashboardSimpananController::class, 'buildSimpananMonthlyTimeseries');

        $result = $method->invoke($controller, '2026-09-08');

        if (!empty($result)) {
            $this->assertArrayHasKey('area6', $result);
            $this->assertArrayHasKey('ritel', $result);
            $this->assertArrayHasKey('micro', $result);
            $this->assertArrayHasKey('wholesale', $result);
            $this->assertArrayHasKey('labels', $result['area6']);
            $this->assertArrayHasKey('h1_values', $result['area6']);
            $this->assertArrayHasKey('h_values', $result['area6']);
            $this->assertArrayHasKey('deltas', $result['area6']);
            $this->assertArrayHasKey('items', $result['area6']);

            if (!empty($result['area6']['items'])) {
                $firstItem = $result['area6']['items'][0];
                $this->assertArrayHasKey('month', $firstItem);
                $this->assertArrayHasKey('h1_date', $firstItem);
                $this->assertArrayHasKey('h_date', $firstItem);
                $this->assertArrayHasKey('h1_fmt', $firstItem);
                $this->assertArrayHasKey('h_fmt', $firstItem);
                $this->assertArrayHasKey('delta_fmt', $firstItem);
                $this->assertArrayHasKey('delta_pct_fmt', $firstItem);
                $this->assertArrayHasKey('delta_color', $firstItem);
            }
        } else {
            $this->assertIsArray($result);
        }
    }

    public function test_build_digital_channel_strategy_payload_structure(): void
    {
        $controller = app(DashboardSimpananController::class);
        $method = new \ReflectionMethod(DashboardSimpananController::class, 'buildDigitalChannelStrategyPayload');

        $result = $method->invoke($controller, '2026-09-08');

        $this->assertIsArray($result);
        $expectedChannels = ['edc', 'qris', 'casa_merchant', 'brimo', 'brilink', 'qlola'];
        foreach ($expectedChannels as $ch) {
            $this->assertArrayHasKey($ch, $result);
            $this->assertArrayHasKey('key', $result[$ch]);
            $this->assertArrayHasKey('label', $result[$ch]);
            $this->assertArrayHasKey('metric_label', $result[$ch]);
            $this->assertArrayHasKey('dates', $result[$ch]);
            $this->assertArrayHasKey('total', $result[$ch]);
            $this->assertArrayHasKey('branches', $result[$ch]);
            $this->assertCount(4, $result[$ch]['branches']);
        }
    }

    public function test_build_ecosystem_value_chain_strategy_payload_structure(): void
    {
        $controller = app(DashboardSimpananController::class);
        $result = $controller->buildEcosystemValueChainStrategyPayload('2026-09-08');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('records', $result);
        $this->assertArrayHasKey('sheet_url', $result);

        $summary = $result['summary'];
        $this->assertArrayHasKey('totalAccounts', $summary);
        $this->assertArrayHasKey('totalBalance', $summary);
        $this->assertArrayHasKey('ecosystems', $summary);
        $this->assertArrayHasKey('branches', $summary);

        $this->assertEquals(2069, $summary['totalAccounts']);
        $this->assertGreaterThan(80_000_000_000, $summary['totalBalance']);
        $this->assertCount(6, $summary['ecosystems']);
        $this->assertCount(4, $summary['branches']);
        $this->assertCount(2069, $result['records']);

        // Check descending sorting by saldo
        $this->assertGreaterThanOrEqual($result['records'][1]['saldo'], $result['records'][0]['saldo']);
        $this->assertGreaterThanOrEqual($result['records'][2]['saldo'], $result['records'][1]['saldo']);
    }
}
