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

    public function test_landing_navigation_is_standalone_above_marketshare(): void
    {
        $sidebar = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));
        $landingStart = strpos($sidebar, 'sidebar-dashboard-landing');
        $pinjamanStart = strpos($sidebar, '<li class="nav-item sidebar-dashboard-pinjaman');
        $almafactsStart = strpos($sidebar, '<li class="nav-item sidebar-dashboard-almafacts');

        $this->assertNotFalse($landingStart);
        $this->assertNotFalse($pinjamanStart);
        $this->assertNotFalse($almafactsStart);

        $pinjamanBlock = substr($sidebar, $pinjamanStart, $almafactsStart - $pinjamanStart);

        $this->assertStringContainsString('sidebar-dashboard-landing', $sidebar);
        $this->assertStringNotContainsString('<p>Landing Page</p>', $pinjamanBlock);
        $this->assertStringContainsString("route('dashboard')", $sidebar);
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
        $controller = file_get_contents(app_path('Http/Controllers/DashboardSimpananController.php'));
        $route = app('router')->getRoutes()->getByName('dashboard.micro-performance');
        $pipelineRoute = app('router')->getRoutes()->getByName('dashboard.micro-pipeline');
        $pipelineService = file_get_contents(app_path('Support/LandingMicroPipelineService.php'));

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
        $this->assertStringContainsString('Mantri Belum Realisasi Kupedes', $partial);
        $this->assertStringContainsString("data_get(\$realization, 'kupedes_not_realized'", $partial);
        $this->assertStringContainsString("data_get(\$realization, 'mantri_roster'", $partial);
        $this->assertStringContainsString('micro-mantri-roster-strip', $partial);
        $this->assertStringContainsString('Basis {{ $formatInteger', $partial);
        $this->assertStringContainsString('$kupedesPendingByBranch', $partial);
        $this->assertStringContainsString('micro-kupedes-pending__summary', $partial);
        $this->assertStringContainsString('micro-kupedes-branch__metrics', $partial);
        $this->assertStringContainsString('micro-kupedes-branch__people', $partial);
        $this->assertTrue(
            strpos($realizationSection, 'Realisasi per Produk') < strpos($realizationSection, 'Mantri Belum Realisasi Kupedes'),
            'Daftar Mantri belum realisasi Kupedes harus tampil setelah Realisasi per Produk.'
        );
        $this->assertStringContainsString('Pemutus PDWK', $partial);
        $this->assertStringContainsString('Pola Angsuran', $partial);
        $this->assertStringContainsString('data-micro-need-filter', $partial);
        $this->assertStringNotContainsString('data-micro-one-time-detail', $partial);
        $this->assertStringNotContainsString('data-micro-nominative-modal', $partial);
        $this->assertStringNotContainsString('data-micro-one-time-nominatives-url', $view);
        $this->assertStringContainsString('micro-realization-layout', $partial);
        $this->assertStringContainsString('micro-realization-summary-grid', $partial);
        $this->assertStringContainsString('Run Off Mikro', $partial);
        $this->assertStringContainsString('PH Mikro', $partial);
        $this->assertStringContainsString('Mantri Sudah Real', $partial);
        $this->assertStringContainsString('Mantri Belum Real', $partial);
        $this->assertStringContainsString('data-micro-pdwk-role', $partial);
        $this->assertStringContainsString('data-micro-pdwk-panel', $partial);
        $this->assertStringContainsString('data-micro-pdwk-details', $partial);
        $this->assertStringContainsString('data-micro-pdwk-open-detail', $partial);
        $this->assertStringContainsString('openMicroPdwkPeople', $view);
        $this->assertStringContainsString("addEventListener('dblclick'", $view);
        $this->assertStringContainsString('100% x PDWK', $service);
        $this->assertStringContainsString('75% x PDWK', $service);
        $this->assertStringContainsString('40% x PDWK', $service);
        $this->assertStringContainsString("'label' => 'Low'", $service);
        $this->assertStringContainsString("'label' => 'Moderate'", $service);
        $this->assertStringContainsString("'label' => 'Moderate to High'", $service);
        $this->assertStringContainsString("'source' => 'STOP N GO.xlsx'", $service);
        $this->assertStringContainsString('person?.category', $view);
        $this->assertStringContainsString('person?.limit', $view);
        $this->assertStringContainsString('person?.branch', $view);
        $this->assertStringContainsString('Produktivitas Mantri', $partial);
        $this->assertStringContainsString('Roster aktif BRIHC', $partial);
        $this->assertStringContainsString('micro-mantri-table--summary', $partial);
        $this->assertStringContainsString('Nett Disbursement Mantri PT Only', $partial);
        $this->assertStringContainsString('Nett Disbursement Mantri Kontrak', $partial);
        $this->assertStringContainsString('data-micro-mantri-tier-row', $partial);
        $this->assertStringContainsString('data-micro-mantri-tier-details', $partial);
        $this->assertStringContainsString('data-micro-mantri-tier-count', $partial);
        $this->assertStringContainsString('data-micro-mantri-bucket', $partial);
        $this->assertStringNotContainsString('data-micro-mantri-tier-open', $partial);
        $this->assertStringContainsString('openMicroMantriTierPeople', $view);
        $this->assertStringContainsString("buckets.find(item => String(item?.key || '') === bucketKey)", $view);
        $this->assertStringContainsString('Produktivitas RM KUR Kecil Mikro', $partial);
        $this->assertStringContainsString('data-micro-rm-kur-productivity', $partial);
        $this->assertStringContainsString("'rm_kur_productivity'", $controller);
        $this->assertStringContainsString('buildLandingRmKurProductivity', $controller);
        $mantriPosition = strpos($partial, 'id="micro-mantri-title"');
        $rmKurPosition = strpos($partial, 'id="micro-rm-kur-title"');
        $burdenPosition = strpos($partial, 'micro-ops-section--burden');
        $this->assertNotFalse($mantriPosition);
        $this->assertNotFalse($rmKurPosition);
        $this->assertNotFalse($burdenPosition);
        $this->assertTrue(
            $mantriPosition < $rmKurPosition && $rmKurPosition < $burdenPosition,
            'Produktivitas RM KUR Kecil Mikro harus tampil setelah Mantri dan sebelum Uker Pemberat.'
        );
        $this->assertStringContainsString('Uker Pemberat', $partial);
        $this->assertStringNotContainsString('Branch Office Pemberat', $partial);
        $this->assertStringContainsString('Mantri Tidak Produktif', $partial);
        $this->assertStringContainsString('micro-ops-section--inactive', $partial);
        $this->assertStringContainsString("data_get(\$unproductiveMantri, 'totals.'", $partial);
        $this->assertStringContainsString("'month_1' => 'clock'", $partial);
        $this->assertStringContainsString("'month_3' => 'calendar-alt'", $partial);
        $this->assertStringContainsString("'month_6' => 'exclamation-triangle'", $partial);
        $this->assertTrue(
            strpos($partial, 'micro-ops-section--burden') < strpos($partial, 'micro-ops-section--inactive'),
            'Monitoring Mantri tidak produktif harus menjadi bagian paling bawah setelah Uker Pemberat.'
        );
        $overrideThPos = strpos($partial, "data_get(\$decision, 'override_label')");
        $primaryThPos = strpos($partial, "data_get(\$decision, 'primary_label')");
        $this->assertNotFalse($overrideThPos);
        $this->assertNotFalse($primaryThPos);
        $this->assertTrue($overrideThPos < $primaryThPos, 'Kolom threshold lebih kecil (override) harus berada di kiri sebelum primary');
        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('user.branch.scope', $route->gatherMiddleware());
        $this->assertStringContainsString('Pipeline Mikro', $partial);
        $this->assertStringContainsString('Prewash &amp; SLIK Hijau', $partial);
        $this->assertStringContainsString('data-micro-pipeline-open="slik_hijau"', $partial);
        $this->assertStringContainsString('name="product"', $partial);
        $this->assertStringContainsString('data-micro-pipeline-modal', $partial);
        $this->assertStringContainsString('data-micro-pipeline-source-detail', $partial);
        $this->assertStringContainsString('data-micro-pipeline-source-modal', $partial);
        $this->assertStringContainsString('Progress Penyelesaian', $partial);
        $this->assertStringContainsString('data-micro-pipeline-source-open-nominatives', $partial);
        $this->assertStringContainsString('openMicroPipelineSourceModal', $view);
        $this->assertStringContainsString('renderMicroPipelineSourceProgress', $view);
        $this->assertStringContainsString("['status' => 'open', 'per_page' => 20]", $pipelineService);
        $this->assertNotNull($pipelineRoute);
        $this->assertContains('auth', $pipelineRoute->gatherMiddleware());
        $this->assertContains('user.branch.scope', $pipelineRoute->gatherMiddleware());
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
        $this->assertStringContainsString('tcc-quality-matrix', $coreLanding);
        $this->assertDoesNotMatchRegularExpression(
            '/\.tcc-quality-matrix\s*\{\s*display:\s*grid;\s*\/\*/',
            $view,
            'Selector matriks kualitas harus menutup blok CSS sebelum komentar atau selector berikutnya.'
        );
        $this->assertStringContainsString('Posisi YTD', $coreLanding);
        $this->assertStringContainsString('Posisi MTD', $coreLanding);
        $this->assertStringContainsString("data_get(\$composition, 'micro_quality'", $coreLanding);
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

    public function test_micro_rm_kur_productivity_renders_scoped_realisasi_and_summary(): void
    {
        $html = view('dashboard.partials.micro-performance', [
            'microPerformance' => [
                'meta' => [
                    'available' => true,
                    'period_label' => '24 Jul 2026',
                    'scope_label' => 'KC Madiun',
                ],
                'rm_kur_productivity' => [
                    'available' => true,
                    'period_label' => '24 Jul 2026',
                    'scope_label' => 'KC Madiun',
                    'rows' => [
                        [
                            'pn' => '0001',
                            'nama' => 'RM SATU',
                            'branch_code' => '45',
                            'cabang' => 'KC MADIUN',
                            'unit' => 'FUNGSI BISNIS MIKRO',
                            'realisasi_deb' => 2,
                            'realisasi_os' => 500000000,
                            'average_per_debtor' => 250000000,
                        ],
                        [
                            'pn' => '0002',
                            'nama' => 'RM DUA',
                            'branch_code' => '45',
                            'cabang' => 'KC MADIUN',
                            'unit' => 'KC MADIUN',
                            'realisasi_deb' => 1,
                            'realisasi_os' => 250000000,
                            'average_per_debtor' => 250000000,
                        ],
                    ],
                    'total' => [
                        'rm_count' => 2,
                        'realisasi_deb' => 3,
                        'realisasi_os' => 750000000,
                        'average_per_rm' => 375000000,
                        'average_per_debtor' => 250000000,
                    ],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('data-micro-rm-kur-productivity', $html);
        $this->assertStringContainsString('Produktivitas RM KUR Kecil Mikro', $html);
        $this->assertStringContainsString('RM SATU', $html);
        $this->assertStringContainsString('RM DUA', $html);
        $this->assertStringContainsString('PN 0001', $html);
        $this->assertStringContainsString('KC MADIUN', $html);
        $this->assertStringContainsString('FUNGSI BISNIS MIKRO', $html);
        $this->assertStringContainsString('Rp 750 jt', $html);
        $this->assertStringContainsString('Rp 375 jt', $html);
    }

    public function test_micro_billing_controls_survive_lazy_load_and_use_compact_nusantara_layout(): void
    {
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));
        $partial = file_get_contents(resource_path('views/dashboard/partials/micro-performance.blade.php'));

        $this->assertStringContainsString('const setMicroBillingMetric = (section, metric)', $view);
        $this->assertStringContainsString('const setMicroBillingView = (section, view)', $view);
        $this->assertStringContainsString("event.target.closest('[data-billing-metric]')", $view);
        $this->assertStringContainsString("event.target.closest('[data-billing-view]')", $view);
        $this->assertStringContainsString("viewButton.dataset.billingControlBound = '1';", $view);
        $this->assertStringContainsString('initializeMicroBilling(microPerformanceDashboard);', $view);
        $this->assertStringNotContainsString('function initMicroBillingInteractivity()', $partial);

        $this->assertStringContainsString('--billing-nusantara: var(--micro-nusantara, #0754bd);', $partial);
        $this->assertStringContainsString('grid-template-columns: repeat(auto-fill, minmax(min(126px, 100%), 1fr));', $partial);
        $this->assertStringContainsString('grid-template-columns: repeat(8, minmax(0, 1fr));', $partial);
        $this->assertStringContainsString('min-height: 116px;', $partial);
        $this->assertStringContainsString('<span>Billing</span>', $partial);
        $this->assertStringContainsString('<span>Bayar</span>', $partial);
        $this->assertStringNotContainsString('<span>Total Billing</span>', $partial);
        $this->assertStringNotContainsString('<span>Billing Terbayar</span>', $partial);
        $this->assertStringContainsString('comparison_date_label', $partial);
        $this->assertStringContainsString('jika tanggal itu tidak tersedia, acuannya adalah hari terakhir M-1', $partial);
        $this->assertStringContainsString('.micro-billing-btn:focus-visible', $partial);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $partial);
    }

    public function test_micro_billing_daily_card_renders_labeled_totals_paid_values_and_adjusted_m1_date(): void
    {
        $html = view('dashboard.partials.micro-performance', [
            'microPerformance' => [
                'meta' => ['available' => true, 'period_label' => '31 Mei 2026'],
                'billing' => [
                    'available' => true,
                    'period' => '2026-05-31',
                    'current_day' => 31,
                    'days_in_month' => 31,
                    'm0' => [
                        'month_label' => 'Mei 2026',
                        'baseline_period' => '2026-04-30',
                        'total_billing_debitur' => 54,
                        'total_billing_os' => 155_830_000,
                        'due_so_far_billing_debitur' => 54,
                        'due_so_far_billing_os' => 155_830_000,
                        'paid_debitur' => 24,
                        'paid_os' => 80_198_000,
                        'collection_rate_deb' => 44.4,
                        'collection_rate_os' => 51.5,
                    ],
                    'm1' => [
                        'month_label' => 'April 2026',
                        'total_billing_debitur' => 52,
                        'total_billing_os' => 150_000_000,
                        'paid_debitur' => 26,
                        'paid_os' => 75_000_000,
                        'collection_rate_deb' => 50.0,
                        'collection_rate_os' => 50.0,
                        'same_day_collection_rate_deb' => 50.0,
                        'same_day_collection_rate_os' => 50.0,
                        'same_day_cutoff_label' => '30 Apr 2026',
                    ],
                    'cards' => [[
                        'day' => 31,
                        'day_name' => 'Min',
                        'is_today' => true,
                        'is_weekend' => true,
                        'is_due' => true,
                        'status' => 'today',
                        'billing_debitur' => 54,
                        'billing_os' => 155_830_000,
                        'paid_debitur' => 24,
                        'paid_os' => 80_198_000,
                        'pct_debitur' => 44.4,
                        'pct_os' => 51.5,
                        'm1_billing_debitur' => 52,
                        'm1_billing_os' => 150_000_000,
                        'm1_paid_debitur' => 26,
                        'm1_paid_os' => 75_000_000,
                        'm1_pct_debitur' => 50.0,
                        'm1_pct_os' => 50.0,
                        'delta_pct_deb' => -5.6,
                        'delta_pct_os' => 1.5,
                        'comparison_date_label' => '30 Apr',
                        'comparison_date_adjusted' => true,
                    ]],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('<span>Billing</span>', $html);
        $this->assertStringContainsString('<span>Bayar</span>', $html);
        $this->assertStringNotContainsString('<span>Total Billing</span>', $html);
        $this->assertStringNotContainsString('<span>Billing Terbayar</span>', $html);
        $this->assertStringContainsString('M0 / Tgl 31', $html);
        $this->assertStringContainsString('M-1 / 30 Apr', $html);
        $this->assertStringContainsString('Acuan 30 Apr (akhir bulan)', $html);
        $this->assertStringContainsString('vs 30 Apr: 50,0%', $html);
        $this->assertStringContainsString('Naik 1,5 pp', $html);
        $this->assertStringContainsString('Turun 5,6 pp', $html);
    }

    public function test_kupedes_pending_summary_groups_mantri_by_branch_and_keeps_detail_available(): void
    {
        $html = view('dashboard.partials.micro-performance', [
            'microPerformance' => [
                'meta' => ['available' => true, 'period_label' => '31 Agu 2026'],
                'realization' => [
                    'mantri_roster' => [
                        'total_active' => 11,
                        'pt' => 8,
                        'contract' => 2,
                        'briguna' => 1,
                        'kupedes_eligible' => 10,
                        'kupedes_realized' => 7,
                        'kupedes_not_realized' => 3,
                    ],
                    'kupedes_not_realized' => [
                        ['pn' => '100001', 'name' => 'Mantri Satu', 'branch' => 'KC MADIUN', 'unit' => 'UNIT A'],
                        ['pn' => '100002', 'name' => 'Mantri Dua', 'branch' => 'KC MADIUN', 'unit' => 'UNIT B'],
                        ['pn' => '100003', 'name' => 'Mantri Tiga', 'branch' => 'KC NGAWI', 'unit' => 'UNIT C'],
                    ],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('3</b> Belum realisasi', $html);
        $this->assertStringContainsString('Basis 10 Mantri', $html);
        $this->assertStringContainsString('7 dari 10 Mantri eligible sudah realisasi', $html);
        $this->assertStringContainsString('<small>Mantri Aktif</small><strong>11</strong>', $html);
        $this->assertStringContainsString('KC MADIUN', $html);
        $this->assertStringContainsString('KC NGAWI', $html);
        $this->assertStringContainsString('2 orang', $html);
        $this->assertStringContainsString('Mantri Satu', $html);
        $this->assertStringContainsString('PN 100001', $html);
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
