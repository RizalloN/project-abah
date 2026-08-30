<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use ReflectionMethod;
use Tests\TestCase;

class LandingPageRedesignContractTest extends TestCase
{
    public function test_landing_renders_recovery_as_the_fourth_area_metric_card(): void
    {
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/DashboardSimpananController.php'));

        $this->assertStringContainsString("['os', 'sml', 'npl', 'recovery']", $view);
        $this->assertStringContainsString('grid-template-columns: repeat(4, minmax(0, 1fr)) !important;', $view);
        $this->assertStringContainsString('.ap-header.bg-recovery,', $view);
        $this->assertStringContainsString("'key' => 'recovery'", $controller);
        $this->assertStringContainsString("'header_title' => 'RECOVERY DH'", $controller);
        $this->assertStringContainsString("firstWhere('key', 'rec_dh_total')", $controller);
        $this->assertStringContainsString('LandingPrognosaCardService::class', $controller);
        $this->assertStringContainsString('class="ap-prognosa-strip"', $view);
        $this->assertStringContainsString('data-prognosa-week-select', $view);
        $this->assertStringContainsString('data-prognosa-week-panel', $view);
        $this->assertStringContainsString('selectLandingPrognosaWeek', $view);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $view);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $view);
    }

    public function test_weekly_recovery_uses_the_metric_for_each_selected_segment(): void
    {
        $method = new ReflectionMethod(DashboardSimpananController::class, 'area6PortfolioMetricKeys');
        $controller = app(DashboardSimpananController::class);

        $this->assertSame('rec_dh_total', $method->invoke($controller, 'area6')['recovery_metric']);
        $this->assertSame('rec_dh_small', $method->invoke($controller, 'sme')['recovery_metric']);
        $this->assertSame('rec_dh_consumer', $method->invoke($controller, 'consumer')['recovery_metric']);
        $this->assertSame('rec_dh_micro', $method->invoke($controller, 'micro')['recovery_metric']);
    }

    public function test_area_scope_and_operational_insights_use_the_new_pgs_backed_contract(): void
    {
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/DashboardSimpananController.php'));

        $this->assertStringContainsString('area6-scope-btn__icon', $view);
        $this->assertStringContainsString('data-scope-title=', $view);
        $this->assertStringContainsString('landing-insights__grid', $view);
        $this->assertStringContainsString('Putusan BOH &amp; PDWK', $view);
        $this->assertStringContainsString('Realisasi Seluruh Segmen', $view);
        $this->assertStringContainsString('satu rekening dihitung satu kali', $view);
        $this->assertStringContainsString('decisionSummary(', $controller);
        $this->assertStringContainsString("'pdwk_rule' => 'Rp100 jt - Rp250 jt'", $controller);
        $this->assertStringContainsString("'override_rule' => '< Rp100 jt'", $controller);
        $this->assertStringContainsString("'pdwk_rule' => 's.d. Rp100 jt'", $controller);
        $this->assertStringContainsString('MAX(COALESCE(d.plafon, 0)) as amount', $controller);
    }

    public function test_landing_navigation_is_grouped_under_dashboard_pinjaman(): void
    {
        $sidebar = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));
        $simpananStart = strpos($sidebar, 'sidebar-dashboard-simpanan');
        $pinjamanStart = strpos($sidebar, 'sidebar-dashboard-pinjaman');
        $almafactsStart = strpos($sidebar, 'sidebar-dashboard-almafacts');

        $this->assertNotFalse($simpananStart);
        $this->assertNotFalse($pinjamanStart);
        $this->assertNotFalse($almafactsStart);

        $simpananBlock = substr($sidebar, $simpananStart, $pinjamanStart - $simpananStart);
        $pinjamanBlock = substr($sidebar, $pinjamanStart, $almafactsStart - $pinjamanStart);

        $this->assertStringNotContainsString('<p>Landing Page</p>', $simpananBlock);
        $this->assertStringContainsString('<p>Landing Page</p>', $pinjamanBlock);
        $this->assertStringContainsString("request()->routeIs('dashboard', 'report.dashboard-pinjaman*", $pinjamanBlock);
    }

    public function test_sme_scope_preserves_core_landing_and_adds_lazy_operating_desk(): void
    {
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));
        $partial = file_get_contents(resource_path('views/dashboard/partials/sme-operations.blade.php'));
        $coreLandingPosition = strpos($view, 'data-area6-content-scope');
        $operationsPosition = strpos($view, 'id="sme-operations-dashboard"');

        $this->assertStringContainsString('id="sme-operations-dashboard"', $view);
        $this->assertStringContainsString("route('dashboard.sme-operations',", $view);
        $this->assertStringContainsString("scope === 'sme'", $view);
        $this->assertStringNotContainsString('.db-shell.sme-operations-active .area6-scope-content,', $view);
        $this->assertStringContainsString('.db-shell.sme-operations-active .landing-summary', $view);
        $this->assertStringContainsString('.db-shell.sme-operations-active .main-grid', $view);
        $this->assertIsInt($coreLandingPosition);
        $this->assertIsInt($operationsPosition);
        $this->assertTrue($coreLandingPosition < $operationsPosition);
        $this->assertStringContainsString('data-sme-operations-refresh', $view);
        $this->assertStringContainsString('data-sme-operations-ready="1"', $partial);
        $this->assertStringContainsString('Kuadran RM', $partial);
        $this->assertStringContainsString('Kuadran {{ $quadrant }}', $partial);
        $this->assertStringNotContainsString('>Q{{', $partial);
        $this->assertStringContainsString('Monitoring Hot Prospek', $partial);
        $this->assertStringContainsString('RTL Pipeline', $partial);
        $this->assertStringContainsString('sme-ops-vendor-item__identity', $partial);
        $this->assertStringContainsString('Total Pipeline', $partial);
        $this->assertStringContainsString('Sudah OTS', $partial);
        $this->assertStringContainsString('Berminat', $partial);
        $this->assertStringContainsString('Perpanjangan', $partial);
        $this->assertStringContainsString('Pipeline Restruk', $partial);
        $this->assertStringNotContainsString('sme-ops-restruct-groups', $partial);
        $this->assertStringContainsString('Putusan Pipeline Restruk Kanwil', $partial);
        $this->assertStringContainsString('sme-ops-kanwil-table', $partial);
        $this->assertStringContainsString('Frekuensi Restrukturisasi Debitur', $partial);
        $this->assertStringContainsString("data_get(\$smeOperations, 'restructuring_frequency'", $partial);
        $this->assertStringContainsString('sme-ops-frequency-grid', $partial);
        $this->assertStringContainsString("'periode' => \$selectedPeriod", $view);
        $this->assertStringNotContainsString('Nominal mengacu pada Daily Loan Dinamis', $partial);
        $this->assertStringContainsString("asset('images/sme-rm-team.webp')", $partial);
        $this->assertFileExists(public_path('images/sme-rm-team.webp'));
    }

    public function test_sme_operating_endpoint_stays_inside_authenticated_branch_scope(): void
    {
        $route = app('router')->getRoutes()->getByName('dashboard.sme-operations');

        $this->assertNotNull($route);
        $this->assertSame('dashboard/sme-operations', $route->uri());
        $this->assertStringContainsString('@smeOperations', $route->getActionName());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('user.branch.scope', $route->gatherMiddleware());
    }

    public function test_landing_has_branch_scope_control_and_lazy_micro_performance_desk(): void
    {
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));
        $partial = file_get_contents(resource_path('views/dashboard/partials/micro-performance.blade.php'));
        $service = file_get_contents(app_path('Support/LandingMicroPerformanceService.php'));
        $route = app('router')->getRoutes()->getByName('dashboard.micro-performance');

        $this->assertStringContainsString('id="landing-branch-selector"', $view);
        $this->assertStringContainsString('Cabang dikunci sesuai wilayah user', $view);
        $this->assertStringContainsString('id="micro-performance-dashboard"', $view);
        $this->assertStringContainsString("route('dashboard.micro-performance'", $view);
        $this->assertStringContainsString("scope === 'micro'", $view);
        $this->assertStringContainsString('data-micro-performance-ready="1"', $partial);
        $this->assertStringNotContainsString('Outstanding Mikro per Produk', $partial);
        $this->assertStringNotContainsString('micro-ops-command-ribbon', $partial);
        $this->assertStringContainsString('Plafon (Realisasi Baru)', $partial);
        $this->assertStringContainsString('Nett Disbursement', $partial);
        $this->assertStringContainsString("data_get(\$realization, 'products'", $partial);
        $this->assertStringContainsString('<th class="num">Rekening</th>', $partial);
        $realizationSection = substr(
            $partial,
            (int) strpos($partial, 'micro-ops-section--realization'),
            (int) strpos($partial, 'micro-ops-section--ranking') - (int) strpos($partial, 'micro-ops-section--realization')
        );
        $this->assertStringNotContainsString('<th>CIF</th>', $realizationSection);
        $this->assertStringNotContainsString('<th>CIF</th>', $partial);
        $this->assertStringContainsString('Realisasi per Produk', $partial);
        $this->assertStringContainsString('Pemutus PDWK', $partial);
        $this->assertStringContainsString('Pola Angsuran', $partial);
        $this->assertStringContainsString('data-micro-need-filter', $partial);
        $this->assertStringNotContainsString('data-micro-one-time-detail', $partial);
        $this->assertStringNotContainsString('data-micro-nominative-modal', $partial);
        $this->assertStringNotContainsString('data-micro-one-time-nominatives-url', $view);
        $this->assertStringContainsString('micro-realization-layout', $partial);
        $this->assertStringContainsString('micro-realization-stack', $partial);
        $this->assertStringContainsString('data-micro-pdwk-role', $partial);
        $this->assertStringContainsString('data-micro-pdwk-panel', $partial);
        $this->assertStringContainsString('100% x PDWK', $service);
        $this->assertStringContainsString('75% x PDWK', $service);
        $this->assertStringContainsString('40% x PDWK', $service);
        $this->assertStringContainsString('Produktivitas Mantri', $partial);
        $this->assertStringContainsString('micro-mantri-table--summary', $partial);
        $this->assertStringContainsString('Nett Disbursement Mantri PT Only', $partial);
        $this->assertStringContainsString('Nett Disbursement Mantri Kontrak', $partial);
        $this->assertStringContainsString('Uker Pemberat', $partial);
        $this->assertStringNotContainsString('Branch Office Pemberat', $partial);
        $overrideThPos = strpos($partial, "data_get(\$decision, 'override_label')");
        $primaryThPos = strpos($partial, "data_get(\$decision, 'primary_label')");
        $this->assertNotFalse($overrideThPos);
        $this->assertNotFalse($primaryThPos);
        $this->assertTrue($overrideThPos < $primaryThPos, 'Kolom threshold lebih kecil (override) harus berada di kiri sebelum primary');
        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('user.branch.scope', $route->gatherMiddleware());
    }

    public function test_micro_scope_keeps_core_portfolio_and_uses_the_modern_visual_language(): void
    {
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));
        $partial = file_get_contents(resource_path('views/dashboard/partials/micro-performance.blade.php'));
        $service = file_get_contents(app_path('Support/LandingMicroPerformanceService.php'));
        $coreLandingPosition = strpos($view, 'data-area6-content-scope');
        $microDeskPosition = strpos($view, 'id="micro-performance-dashboard"');

        $this->assertStringNotContainsString('.db-shell.micro-performance-active .area6-scope-content,', $view);
        $this->assertStringContainsString("count(\$contentCards) === 3 ? 'area6-card-grid--three'", $view);
        $this->assertStringContainsString('.db-shell .area6-panel .area6-card-grid.area6-card-grid--three', $view);
        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr)) !important;', $view);
        $this->assertStringContainsString('justify-content: center;', $view);
        $this->assertStringContainsString('class="landing-scope-visual" aria-hidden="true"', $view);
        $this->assertStringNotContainsString('PORTFOLIO INTELLIGENCE', $view);
        $this->assertIsInt($coreLandingPosition);
        $this->assertIsInt($microDeskPosition);
        $this->assertTrue($coreLandingPosition < $microDeskPosition);

        $coreLanding = substr($view, $coreLandingPosition, $microDeskPosition - $coreLandingPosition);
        $this->assertStringContainsString('area6-card-grid', $coreLanding);
        $this->assertStringContainsString('KINERJA PRODUK MIKRO', $coreLanding);
        $this->assertStringContainsString('KOMPOSISI TOTAL', $coreLanding);
        $this->assertStringContainsString('total-composition-card--micro', $coreLanding);
        $this->assertStringContainsString('tcc-horizontal-chart', $coreLanding);
        $this->assertStringContainsString('tcc-horizontal-bar', $coreLanding);
        $this->assertStringContainsString('TREND POSISI', $coreLanding);
        $this->assertStringContainsString('Performance Vs RKA', $coreLanding);

        $this->assertStringContainsString('<span class="micro-ops-eyebrow">REALISASI BULAN BERJALAN</span>', $partial);
        $this->assertStringNotContainsString('micro-ops-hero__visual', $partial);
        $this->assertStringContainsString('micro-mantri-stage__visual', $partial);
        $this->assertStringNotContainsString('micro-ops-command-ribbon', $partial);
        $this->assertStringNotContainsString('<svg viewBox="0 0 360 220"', $partial);
        $this->assertStringContainsString('focusable="false"', $partial);
        $this->assertStringContainsString('micro-pattern-card__breakdown', $partial);
        $this->assertStringContainsString("'label' => '1x Angsuran'", $service);
        $this->assertStringContainsString("'label' => 'Periodik'", $service);
        $this->assertStringContainsString('--micro-nusantara: #0754bd;', $view);
        $this->assertStringContainsString('--micro-cakrawala: #13a7e2;', $view);
        $this->assertStringContainsString('.micro-mantri-table thead tr:first-child th', $view);
        $this->assertStringContainsString('position: sticky;', $view);
        $this->assertStringNotContainsString('micro-ops-section--portfolio', $partial);
    }

    public function test_micro_segment_performance_breaks_down_each_micro_product_and_keeps_total_reconciled(): void
    {
        $controller = app(DashboardSimpananController::class);
        $method = new ReflectionMethod(DashboardSimpananController::class, 'buildArea6ScopeSegmentPerformance');
        $row = static fn (string $key, float $rka): array => [
            'key' => $key,
            'values' => ['current' => 0.0, 'rka' => $rka],
        ];
        $rows = collect([
            $row('briguna_mikro_os', 10_000_000), $row('briguna_mikro_sml', 1_000_000), $row('briguna_mikro_npl', 500_000),
            $row('kupedes_os', 20_000_000), $row('kupedes_sml', 2_000_000), $row('kupedes_npl', 1_000_000),
            $row('kur_mikro_os', 30_000_000), $row('kur_mikro_sml', 3_000_000), $row('kur_mikro_npl', 1_500_000),
            $row('kur_kecil_os', 40_000_000), $row('kur_kecil_sml', 4_000_000), $row('kur_kecil_npl', 2_000_000),
            $row('kur_kpp_os', 50_000_000), $row('kur_kpp_sml', 5_000_000), $row('kur_kpp_npl', 2_500_000),
        ]);
        $snapshotMetrics = (object) [
            'briguna_mikro_os' => 10_000_000, 'briguna_mikro_sml' => 1_000_000, 'briguna_mikro_npl' => 500_000,
            'kupedes_os' => 20_000_000, 'kupedes_sml' => 2_000_000, 'kupedes_npl' => 1_000_000,
            'kur_mikro_os' => 30_000_000, 'kur_mikro_sml' => 3_000_000, 'kur_mikro_npl' => 1_500_000,
            'kur_kecil_os' => 40_000_000, 'kur_kecil_sml' => 4_000_000, 'kur_kecil_npl' => 2_000_000,
            'kur_kpp_os' => 50_000_000, 'kur_kpp_sml' => 5_000_000, 'kur_kpp_npl' => 2_500_000,
        ];

        $payload = $method->invoke($controller, 'micro', $rows, $snapshotMetrics, 'Agustus 26', '22 Agu 2026');

        $this->assertSame(
            ['KUR Mikro', 'Kupedes', 'KUR Kecil', 'KUR KPP', 'Briguna Mikro'],
            array_column($payload['segments'], 'label')
        );
        $this->assertSame('150', data_get($payload, 'total.os.realization_fmt'));
        $this->assertSame('15', data_get($payload, 'total.sml.realization_fmt'));
        $this->assertSame('8', data_get($payload, 'total.npl.realization_fmt'));
        $this->assertSame('30', data_get($payload, 'segments.0.os.realization_fmt'));
        $this->assertSame('20', data_get($payload, 'segments.1.os.realization_fmt'));
    }

    public function test_landing_responsive_layout_follows_the_available_shell_width(): void
    {
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));

        $this->assertStringContainsString('Landing responsive cohesion: follow the actual content width after the sidebar.', $view);
        $this->assertStringContainsString('.db-shell.landing-mobile .ap-week-toggle', $view);
        $this->assertStringContainsString('.db-shell.landing-narrow .sme-ops-feature--quadrant', $view);
        $this->assertStringContainsString('.db-shell.landing-narrow .micro-realization-layout', $view);
        $this->assertStringContainsString('.db-shell.landing-narrow .micro-mantri-table--summary', $view);
        $this->assertStringContainsString('.db-shell.landing-mobile .loan-analytics-head', $view);
        $this->assertStringContainsString("document.addEventListener('landing:scopechange', scheduleSync);", $view);
        $this->assertStringContainsString('chart?.canvas?.offsetParent', $view);
    }
}
