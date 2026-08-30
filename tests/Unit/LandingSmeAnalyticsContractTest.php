<?php

namespace Tests\Unit;

use Tests\TestCase;

class LandingSmeAnalyticsContractTest extends TestCase
{
    public function test_analytics_partials_compile_and_emit_valid_chart_json(): void
    {
        $contentPortfolio = [
            'quality_timeseries' => [
                'available' => true,
                'year' => 2026,
                'labels' => ['Jan'],
                'period_labels' => ['31 Jan 2026'],
                'series' => [
                    'lr' => [10.0],
                    'sml' => [20.0],
                    'npl' => [30.0],
                    'lar' => [60.0],
                ],
                'points' => [[
                    'period_label' => '31 Jan 2026',
                    'lr' => 10.0,
                    'sml' => 20.0,
                    'npl' => 30.0,
                    'lar' => 60.0,
                ]],
            ],
            'tariff_relief' => [
                'available' => true,
                'labels' => ['Jan'],
                'series' => ['previous' => [2100.0], 'closing' => [2050.0], 'daily_realization' => [25.0], 'adjusted_delta' => [-75.0]],
                'latest' => ['delta_os' => -50.0, 'daily_realization' => 25.0, 'daily_realization_available' => true, 'adjusted_delta_os' => -75.0, 'closing_period_label' => '31 Jan 2026'],
                'points' => [[
                    'label' => 'Jan',
                    'previous_period_label' => '30 Jan 2026',
                    'previous_os' => 2100.0,
                    'closing_period_label' => '31 Jan 2026',
                    'closing_os' => 2050.0,
                    'delta_os' => -50.0,
                    'daily_realization' => 25.0,
                    'daily_realization_available' => true,
                    'adjusted_delta_os' => -75.0,
                ]],
            ],
        ];

        $qualityHtml = view('dashboard.partials.loan-quality-timeseries', [
            'contentPortfolio' => $contentPortfolio,
            'contentScopeKey' => 'sme',
        ])->render();
        $tariffHtml = view('dashboard.partials.tariff-relief', [
            'contentPortfolio' => $contentPortfolio,
            'contentScopeKey' => 'sme',
        ])->render();

        $this->assertMatchesRegularExpression('/data-loan-quality-config>\s*\{.+\}\s*<\/script>/s', $qualityHtml);
        $this->assertMatchesRegularExpression('/data-tariff-relief-config>\s*\{.+\}\s*<\/script>/s', $tariffHtml);
        preg_match('/data-loan-quality-config>\s*(\{.+\})\s*<\/script>/s', $qualityHtml, $qualityConfigMatch);
        $qualityConfig = json_decode((string) ($qualityConfigMatch[1] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($qualityConfig['datasets']);
        $this->assertSame(
            [[], [], [], []],
            array_column($qualityConfig['datasets'], 'borderDash'),
            'Semua garis timeseries harus solid, bukan putus-putus.'
        );
        $this->assertStringContainsString('data-loan-quality-toggle', $qualityHtml);
        $this->assertStringContainsString('tariff-series-chart-wrap', $tariffHtml);
        $this->assertStringContainsString('Kelonggaran Tarik', $tariffHtml);
    }

    public function test_segment_scopes_render_eom_quality_and_sme_tariff_cards(): void
    {
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));
        $quality = file_get_contents(resource_path('views/dashboard/partials/loan-quality-timeseries.blade.php'));
        $tariff = file_get_contents(resource_path('views/dashboard/partials/tariff-relief.blade.php'));

        $this->assertStringContainsString("in_array(\$contentScopeKey, ['sme', 'consumer', 'micro'], true)", $view);
        $this->assertStringContainsString('data-loan-analytics-slot="quality"', $view);
        $this->assertStringContainsString('data-loan-analytics-slot="tariff"', $view);
        $this->assertStringContainsString("route('dashboard.loan-analytics'", $view);
        $this->assertStringContainsString('data-loan-quality-config', $quality);
        $this->assertStringContainsString('Timeseries Akhir Bulan', $quality);
        $this->assertStringContainsString('LR', $quality);
        $this->assertStringContainsString('LAR', $quality);
        $this->assertStringContainsString('data-loan-quality-metric', $quality);
        $this->assertStringContainsString('Kelonggaran Tarik H-1 vs Akhir Bulan', $tariff);
        $this->assertStringContainsString('data-tariff-relief-config', $tariff);
        $this->assertStringContainsString('tariff-series-chart-wrap', $tariff);
        $this->assertStringContainsString('landingAdaptiveScaleBounds', $view);
        $this->assertStringContainsString('% Pencapaian RKA', $view);
        $this->assertStringContainsString("data_get(\$segmentPerf, 'previous_rka_month_year', 'M-1')", $view);
        $this->assertStringContainsString('initializeLandingAnalyticsCharts', $view);
        $this->assertStringContainsString('loadLoanAnalytics', $view);
        $this->assertStringContainsString("\$lrComp = data_get(\$composition, 'restruk', [])", $view);
        $this->assertStringContainsString('tcc-legend-dot bg-lr', $view);

        $route = app('router')->getRoutes()->getByName('dashboard.loan-analytics');
        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('user.branch.scope', $route->gatherMiddleware());
    }

    public function test_sme_operating_desk_exposes_tiers_vendor_drilldown_and_inactivity(): void
    {
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));
        $partial = file_get_contents(resource_path('views/dashboard/partials/sme-operations.blade.php'));

        $this->assertStringContainsString('Sebaran Realisasi RM per Cabang', $partial);
        $this->assertStringContainsString('sme-ops-tier-table', $partial);
        $this->assertStringContainsString('data-sme-vendor-detail', $partial);
        $this->assertStringContainsString('data-sme-vendor-modal', $partial);
        $this->assertStringContainsString('data-sme-vendor-modal-head', $partial);
        $this->assertStringContainsString('data-sme-vendor-search', $partial);
        $this->assertStringNotContainsString('data-sme-vendor-open', $partial);
        $this->assertStringContainsString('RM Tidak Produktif', $partial);
        $this->assertStringContainsString('month_6', $partial);
        $this->assertStringContainsString('data-sme-unproductive-detail', $partial);
        $this->assertStringContainsString('data-sme-unproductive-modal', $partial);
        $this->assertStringContainsString('data-sme-unproductive-search', $partial);
        $this->assertStringContainsString('sme-ops-tier-cell', $partial);
        $this->assertStringContainsString('data-sme-rm-realization-head', $partial);
        $this->assertStringContainsString('Frekuensi Restrukturisasi Debitur', $partial);
        $this->assertStringContainsString('Total Debitur Unik', $partial);
        $this->assertStringContainsString('Total Outstanding', $partial);
        $this->assertStringContainsString('sme-ops-feature--frequency', $view);
        $this->assertStringContainsString("addEventListener('dblclick'", $view);
        $this->assertStringContainsString("event.key === 'Enter' || event.key === ' '", $view);
        $this->assertStringContainsString("event.key === 'Escape'", $view);

        $route = app('router')->getRoutes()->getByName('dashboard.sme-vendor-nominatives');
        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('user.branch.scope', $route->gatherMiddleware());
    }

    public function test_sme_restructuring_frequency_card_renders_live_bucket_payload(): void
    {
        $html = view('dashboard.partials.sme-operations', [
            'smeOperations' => [
                'meta' => ['scope_label' => 'Area 6', 'scope' => 'area6'],
                'restructuring_frequency' => [
                    'available' => true,
                    'period_label' => '27 Agu 2026',
                    'scope_label' => 'Area 6',
                    'source' => 'Daily Loan Dinamis',
                    'total_debtors' => 12,
                    'total_os_juta' => 3456,
                    'buckets' => [[
                        'frequency' => 2,
                        'label' => 'Restruk 2 kali',
                        'debtors' => 12,
                        'os_juta' => 3456,
                    ]],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('data-sme-operations-ready="1"', $html);
        $this->assertStringContainsString('Restruk 2 kali', $html);
        $this->assertStringContainsString('12', $html);
        $this->assertStringContainsString('Rp 3.456', $html);
        $this->assertStringNotContainsString('@php', $html);
        $this->assertStringNotContainsString('{{', $html);
    }
}
