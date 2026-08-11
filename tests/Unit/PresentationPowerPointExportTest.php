<?php

namespace Tests\Unit;

use App\Services\Presentation\PresentationDeckDataService;
use App\Services\Presentation\NativeOpenXmlPowerPointRenderer;
use App\Services\Presentation\PresentationScopeDataService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PresentationPowerPointExportTest extends TestCase
{
    public function test_presentation_exposes_a_configurable_powerpoint_export(): void
    {
        $view = $this->presentationSource();

        $this->assertStringContainsString("route('dashboard.presentation.export-pptx')", $view);
        $this->assertStringContainsString('Performance Review - Area 6 Region 13', $view);
        $this->assertStringContainsString('name="global_scope"', $view);
        $this->assertStringContainsString('name="funding_scope"', $view);
        $this->assertStringContainsString('name="funding_product"', $view);
        $this->assertStringContainsString('name="sme_scope"', $view);
        $this->assertStringContainsString('name="sme_product"', $view);
        $this->assertStringContainsString('name="consumer_scope"', $view);
        $this->assertStringContainsString('name="consumer_product"', $view);
        $this->assertStringContainsString('id="pres-prognosa-toggle"', $view);
        $this->assertStringContainsString('name="use_prognosa"', $view);
    }

    public function test_structured_deck_can_toggle_latest_written_weekly_prognosa(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/DashboardSimpananController.php'));
        $scopeService = file_get_contents(app_path('Services/Presentation/PresentationScopeDataService.php'));
        $prognosaService = file_get_contents(app_path('Services/Presentation/PresentationPrognosaWeeklyService.php'));
        $engine = file_get_contents(resource_path('js/presentation/pres-engine.js'));
        $structured = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));
        $styles = file_get_contents(resource_path('css/presentation/pres-structured.css'));

        $this->assertStringContainsString("'comparison_prognosa'", $controller);
        $this->assertStringContainsString("'use_prognosa' => ['nullable', 'boolean']", $controller);
        $this->assertStringContainsString('PresentationPrognosaWeeklyService::class', $scopeService);
        $this->assertStringContainsString("'prognosa_delta_fmt'", $scopeService);
        $this->assertStringContainsString('UPDATE POSISI', $prognosaService);
        $this->assertStringContainsString('weekOfMonth', $prognosaService);
        $this->assertStringContainsString('getUsePrognosa', $structured);
        $this->assertStringContainsString('Delta vs Posisi', $structured);
        $this->assertStringContainsString('has-prognosa', $structured);
        $this->assertStringContainsString('applyPrognosaMode', $engine);
        $this->assertStringContainsString('--pres-slide-width', $engine);
        $this->assertStringContainsString('col.is-prognosa-delta', $styles);
    }

    public function test_powerpoint_export_route_is_registered_as_post(): void
    {
        $this->assertTrue(Route::has('dashboard.presentation.export-pptx'));
        $route = Route::getRoutes()->getByName('dashboard.presentation.export-pptx');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());

        foreach ([
            'dashboard.presentation.export-pptx.start' => 'POST',
            'dashboard.presentation.export-pptx.status' => 'GET',
            'dashboard.presentation.export-pptx.download' => 'GET',
        ] as $name => $method) {
            $this->assertTrue(Route::has($name));
            $asyncRoute = Route::getRoutes()->getByName($name);
            $this->assertNotNull($asyncRoute);
            $this->assertContains($method, $asyncRoute->methods());
        }
    }

    public function test_powerpoint_export_has_native_renderer_async_worker_and_visible_progress(): void
    {
        $service = file_get_contents(app_path('Services/Presentation/PowerPointExportService.php'));
        $native = file_get_contents(app_path('Services/Presentation/NativeOpenXmlPowerPointRenderer.php'));
        $manager = file_get_contents(app_path('Services/Presentation/PresentationExportManager.php'));
        $job = file_get_contents(app_path('Jobs/GeneratePresentationPowerPointJob.php'));
        $source = $this->presentationSource();

        $this->assertStringContainsString('nativeRenderer->render', $service);
        $this->assertStringContainsString("'renderer' => 'native-openxml'", $native);
        $this->assertStringContainsString('rewritePresentationSlideList', $native);
        $this->assertStringContainsString('GeneratePresentationPowerPointJob::dispatch', $manager);
        $this->assertStringContainsString("onQueue('reports-low')", $job);
        $this->assertStringContainsString('id="pres-export-progress"', $source);
        $this->assertStringContainsString('config.exportStartUrl', $source);
        $this->assertStringContainsString('const pollExport = async', $source);
    }

    public function test_powerpoint_deck_places_marketshare_slides_immediately_after_agenda(): void
    {
        $renderer = new NativeOpenXmlPowerPointRenderer();
        $method = new \ReflectionMethod($renderer, 'buildSlides');
        $slides = $method->invoke($renderer, [
            'meta' => [
                'title' => 'Performance Review',
                'scope_label' => 'Area 6 Konsolidasi',
                'period_label' => '31 Juli 2026',
                'source_note' => 'Sumber pengujian',
            ],
            'agenda' => ['Market Share Area 6', 'Mapping Potensi dan Penetrasi'],
            'marketshare' => [
                'area6' => [
                    'available' => true,
                    'scope_label' => 'Area 6 Konsolidasi',
                    'period' => 'Mei 2026',
                    'unit' => 'Rp dalam Miliar',
                    'source' => 'Workbook Market Share Area 6',
                    'segment_key' => 'dpk',
                    'segment_label' => 'Total DPK',
                    'rows' => [
                        [
                            'label' => 'KC Madiun',
                            'branch' => 'KC Madiun',
                            'total' => false,
                            'bri_current' => 100,
                            'industry_current' => 400,
                            'outside_current' => 300,
                            'market_share_current' => 25,
                            'market_share_previous' => 24,
                            'bri_ytd' => 5,
                        ],
                        [
                            'label' => 'AREA 6',
                            'branch' => 'AREA 6',
                            'total' => true,
                            'bri_current' => 100,
                            'industry_current' => 400,
                            'outside_current' => 300,
                            'market_share_current' => 25,
                            'market_share_previous' => 24,
                            'bri_ytd' => 5,
                        ],
                    ],
                    'total' => [
                        'bri_current' => 100,
                        'industry_current' => 400,
                        'outside_current' => 300,
                        'market_share_current' => 25,
                        'market_share_previous' => 24,
                        'bri_ytd' => 5,
                    ],
                ],
                'sektoral' => [
                    'scope_label' => 'Area 6 (Semua Cabang)',
                    'period' => 'Maret 2026',
                    'total' => [
                        'bri_os' => 100,
                        'industry_os' => 400,
                        'potential_os' => 300,
                        'market_share_os' => 0.25,
                    ],
                    'top_sectors' => [[
                        'sector' => 'Perdagangan',
                        'bri_os' => 100,
                        'industry_os' => 400,
                        'market_share_os' => 0.25,
                    ]],
                ],
                'mapping' => [
                    'scope_label' => 'Area 6 Konsolidasi',
                    'updated_at' => '06 Agu 2026 20:44',
                    'totals' => ['potential' => 1000, 'existing' => 250, 'penetration' => 25],
                    'dashboard' => [
                        'ready' => true,
                        'highlights' => ['Penetrasi total Area 6 mencapai 25%.'],
                    ],
                    'coverage' => ['unit_count' => 1, 'mapped_unit_count' => 1],
                    'map_points' => [['label' => '03212 - Dolopo', 'x' => 0.5, 'y' => 0.5, 'potential' => 1000, 'penetration' => 25]],
                    'ranking' => [[
                        'label' => '03212 - Dolopo',
                        'values' => ['total' => ['existing' => 250, 'penetration' => 25]],
                    ]],
                ],
            ],
            'funding' => ['title' => 'Performance Funding', 'overview_rows' => [], 'product_rows' => []],
            'sme' => ['title' => 'Performance SME', 'overview_rows' => [], 'product_rows' => []],
            'consumer' => ['title' => 'Performance Konsumer', 'overview_rows' => [], 'product_rows' => []],
            'structured' => ['funding' => [], 'credit' => []],
            'strategies' => [],
            'funding_strategies' => [],
            'trend_groups' => [],
        ]);

        $this->assertCount(15, $slides);
        $this->assertStringContainsString('Market Share Total DPK - Area 6 Konsolidasi', $slides[2]);
        $this->assertStringNotContainsString('Market Share Sektoral', $slides[2]);
        $this->assertStringContainsString('Mapping Market Share', $slides[3]);
        $this->assertStringContainsString('Update 06 Agu 2026 20:44', $slides[3]);
        $this->assertStringContainsString('Performance Funding', $slides[4]);
        $this->assertStringContainsString('15', $slides[14]);
    }

    public function test_powerpoint_marketshare_payload_respects_selected_branch_scope(): void
    {
        $service = new PresentationDeckDataService();
        $method = new \ReflectionMethod($service, 'buildMarketShare');
        $payload = [
            'area6' => [
                'title' => 'Marketshare - Area 6',
                'period' => 'Mei 2026',
                'unit' => 'Rp dalam Miliar',
                'source' => 'Workbook Market Share Area 6',
                'default_segment' => 'dpk',
                'segments' => [
                    'dpk' => [
                        'key' => 'dpk',
                        'label' => 'Total DPK',
                        'kind' => 'deposit',
                        'headers' => ['Cabang'],
                        'insights' => ['Market share Area 6 menguat.'],
                        'rows' => [
                            ['branch' => 'Madiun', 'total' => false, 'values' => [null, null, 100, 5, null, null, 400, null, null, null, 300, null, null, 20, 25]],
                            ['branch' => 'Ngawi', 'total' => false, 'values' => [null, null, 200, 4, null, null, 500, null, null, null, 300, null, null, 35, 40]],
                            ['branch' => 'AREA 6', 'total' => true, 'values' => [null, null, 300, 4.5, null, null, 900, null, null, null, 600, null, null, 30, 33.33]],
                        ],
                    ],
                ],
            ],
            'sektoral' => [
                'period' => 'Maret 2026',
                'scopes' => [
                    'area6' => ['label' => 'Area 6', 'rows' => [['sector' => 'Area', 'bri_os' => 999]], 'total' => []],
                    'madiun' => ['label' => 'KC Madiun', 'rows' => [['sector' => 'Madiun', 'bri_os' => 100]], 'total' => ['bri_os' => 100]],
                ],
            ],
            'mapping' => [
                'ready' => true,
                'updated_at' => '06 Agu 2026 20:44',
                'area_totals' => ['potential' => 300, 'existing' => 45, 'penetration' => 15],
                'branches' => [
                    ['key' => 'madiun', 'totals' => ['potential' => 100, 'existing' => 25, 'penetration' => 25]],
                    ['key' => 'ngawi', 'totals' => ['potential' => 200, 'existing' => 20, 'penetration' => 10]],
                ],
                'units' => [
                    ['label' => 'Madiun Unit', 'branch' => 'madiun', 'district_codes' => ['3519010'], 'values' => ['total' => ['potential' => 100, 'existing' => 25, 'penetration' => 25]]],
                    ['label' => 'Ngawi Unit', 'branch' => 'ngawi', 'district_codes' => ['3521010'], 'values' => ['total' => ['potential' => 200, 'existing' => 20, 'penetration' => 10]]],
                ],
                'geojson' => ['features' => [
                    ['properties' => ['KDCPUM' => '3519010'], 'geometry' => ['coordinates' => [[[111.1, -7.6], [111.2, -7.5], [111.1, -7.6]]]]],
                    ['properties' => ['KDCPUM' => '3521010'], 'geometry' => ['coordinates' => [[[111.3, -7.4], [111.4, -7.3], [111.3, -7.4]]]]],
                ]],
                'dashboard' => [
                    'ready' => true,
                    'title' => 'Market Share Mapping Area 6',
                    'subtitle' => 'Dashboard potensi wilayah',
                    'selectedSector' => 'TOTAL',
                    'totalMetrics' => [
                        ['label' => 'Total Potensi', 'raw' => 607212, 'value' => '607.212'],
                        ['label' => 'Total Debitur', 'raw' => 346571, 'value' => '346.571'],
                        ['label' => 'Penetrasi', 'raw' => 57.1, 'value' => '57,1%'],
                    ],
                    'headlineMetrics' => [
                        ['label' => 'Orang', 'raw' => 1971514, 'value' => '1.971.514'],
                    ],
                    'highlights' => [
                        ['label' => 'Sinyal', 'value' => 'Penetrasi Area 6 kuat'],
                    ],
                ],
            ],
        ];

        $result = $method->invoke($service, $payload, 'KC Madiun', 'KC Madiun');
        $areaResult = $method->invoke($service, $payload, 'area6', 'Area 6 Konsolidasi');

        $this->assertSame('madiun', $result['scope_key']);
        $this->assertSame('Madiun', data_get($result, 'area6.rows.0.branch'));
        $this->assertSame(100.0, data_get($result, 'area6.total.bri_current'));
        $this->assertSame(25.0, data_get($result, 'area6.total.market_share_current'));
        $this->assertSame('Madiun', data_get($result, 'sektoral.rows.0.sector'));
        $this->assertSame(25, data_get($result, 'mapping.totals.penetration'));
        $this->assertFalse(data_get($result, 'mapping.dashboard.ready'));
        $this->assertSame('Madiun Unit', data_get($result, 'mapping.ranking.0.label'));
        $this->assertCount(1, data_get($result, 'mapping.map_points'));
        $this->assertSame(607212.0, data_get($areaResult, 'mapping.totals.potential'));
        $this->assertSame(346571.0, data_get($areaResult, 'mapping.totals.existing'));
        $this->assertSame(57.1, data_get($areaResult, 'mapping.totals.penetration'));
        $this->assertTrue(data_get($areaResult, 'mapping.dashboard.ready'));
        $this->assertSame('Sinyal: Penetrasi Area 6 kuat', data_get($areaResult, 'mapping.dashboard.highlights.0'));
    }

    public function test_deck_builder_contains_all_required_comparison_points_and_products(): void
    {
        $builder = file_get_contents(app_path('Services/Presentation/PresentationDeckDataService.php'));

        foreach (['yoy', 'ytd', 'mom', 'mtd', 'dtd', 'current'] as $period) {
            $this->assertStringContainsString("'{$period}'", $builder);
        }

        foreach (['non_cashcoll', 'cashcoll', 'briguna', 'kpr', 'kkb'] as $product) {
            $this->assertStringContainsString("'{$product}'", $builder);
        }

        $this->assertStringContainsString('resolveRka', $builder);
        $this->assertStringContainsString("'global_scope'", $builder);
        $this->assertStringContainsString('buildProductivity($payload, $globalScope, $scopeLabel)', $builder);
        $this->assertStringContainsString('buildTrendGroups($payload, $globalScope, $scopeLabel)', $builder);
    }

    public function test_renderer_keeps_template_branding_and_inverse_quality_formatting(): void
    {
        $renderer = file_get_contents(base_path('scripts/export_bri_performance_ppt.ps1'));

        $this->assertStringContainsString('BRI_Presentation Template.pptx', file_get_contents(app_path('Services/Presentation/PowerPointExportService.php')));
        $this->assertStringContainsString('Get-DeltaStyle $row.sml.deltas.mtd $true', $renderer);
        $this->assertStringContainsString('Get-DeltaStyle $row.npl.deltas.mtd $true', $renderer);
        $this->assertStringContainsString('Add-SectionSlides $data.funding $true', $renderer);
        $this->assertStringContainsString('Add-SectionSlides $data.sme $false', $renderer);
        $this->assertStringContainsString('Add-SectionSlides $data.consumer $false', $renderer);
        $this->assertStringContainsString('Add-ProductivitySlides', $renderer);
        $this->assertStringContainsString('Add-IntegratedTrendSlides', $renderer);
    }

    public function test_browser_presentation_fits_one_fixed_canvas_across_viewports(): void
    {
        $view = $this->presentationSource();

        $this->assertStringContainsString('--pres-slide-width: 1440px', $view);
        $this->assertStringContainsString('--pres-slide-height: 810px', $view);
        $this->assertStringContainsString('Math.min(availableWidth / logicalSlideWidth, availableHeight / logicalSlideHeight)', $view);
        $this->assertStringContainsString("window.addEventListener('orientationchange'", $view);
        $this->assertStringContainsString("window.visualViewport?.addEventListener('resize'", $view);
        $this->assertStringContainsString('document.querySelectorAll(\'.apple-slide\').length', $view);
        $this->assertStringContainsString('window.__presentationLayoutAudit = collectPresentationLayoutAudit', $view);
        $this->assertStringContainsString('grid-template-columns: repeat(5, minmax(0, 1fr)) !important', $view);
        $this->assertStringContainsString('horizontalScrollRegions:', $view);
        $this->assertStringContainsString('padding-right: 0 !important', $view);
        $this->assertStringContainsString('white-space: normal !important', $view);
        $this->assertStringNotContainsString('const totalSlides = 16;', $view);
    }

    public function test_browser_presentation_uses_scope_led_business_sequence_and_quality_analysis(): void
    {
        $view = $this->presentationSource();
        $slides = file_get_contents(resource_path('views/presentation/_executive-slides.blade.php'));
        $structured = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));

        foreach ([
            'cover',
            'agenda',
            'funding-summary',
            'funding-product',
            'strategies',
            'loan-summary',
            'loan-sme',
            'loan-consumer',
            'loan-micro',
            'quality-sml',
            'quality-npl',
            'timeseries',
            'closing',
        ] as $storyKey) {
            $this->assertStringContainsString("['key' => '{$storyKey}'", $slides);
        }

        $this->assertStringContainsString('() => this.renderAgendaV2()', $structured);
        $this->assertStringContainsString('() => this.renderFundingSummaryV2()', $structured);
        $this->assertStringContainsString('() => this.renderFundingProductsV2()', $structured);
        $this->assertStringContainsString('() => this.renderStrategiesV2()', $structured);
        $this->assertStringContainsString('() => this.renderLoanSummaryV2()', $structured);
        $this->assertStringContainsString("() => this.renderSegmentPerformanceV2('sme')", $structured);
        $this->assertStringContainsString("() => this.renderSegmentPerformanceV2('consumer')", $structured);
        $this->assertStringContainsString('() => this.renderMicroHighlight()', $structured);
        $this->assertStringContainsString("() => this.renderQualityV2('sml')", $structured);
        $this->assertStringContainsString("() => this.renderQualityV2('npl')", $structured);
        $this->assertStringContainsString('() => this.renderTimeseries()', $structured);
        $this->assertStringContainsString('() => this.renderPrioritiesV2()', $structured);
        $this->assertStringContainsString("const positionKeys = ['yoy', 'ytd', 'm2', 'mom', 'mtd', 'dtd', 'current'];", $structured);
        $this->assertStringContainsString('SML menjadi early warning sebelum memburuk ke NPL.', $structured);
        $this->assertStringContainsString('NPL menjadi fokus recovery dan penyelesaian eksposur.', $structured);
        $this->assertStringContainsString('getScope: () => deckState.scope', $view);
        $this->assertStringNotContainsString('Perbandingan 4 Kantor Cabang', $slides);
    }

    public function test_browser_presentation_keeps_executive_layouts_dense_and_data_adaptive(): void
    {
        $view = $this->presentationSource();
        $structured = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));
        $styles = file_get_contents(resource_path('css/presentation/pres-structured.css'));

        foreach ([
            'psd-total-layout',
            'psd-analysis-column',
            'psd-mini-trend',
            'psd-breakdown-layout',
            'psd-segment-layout',
            'psd-product-stack',
            'psd-product-branches',
            'psd-quality-layout',
            'psd-productivity-layout',
            'psd-mantri-layout',
            'psd-kts-layout',
            'psd-timeseries-layout',
            'psd-closing-layout',
        ] as $idOrClass) {
            $this->assertStringContainsString($idOrClass, $structured);
            $this->assertStringContainsString(".{$idOrClass}", $styles);
        }

        $this->assertStringContainsString('const safeRows = Array.isArray(rows) ? rows.filter(Boolean) : [];', $structured);
        $this->assertStringContainsString('--psd-row-count:${Math.max(1, safeRows.length)}', $structured);
        $this->assertStringContainsString('.filter((row) => asNumber(row.realisasi_os) !== 0 || asNumber(row.realisasi_deb) !== 0)', $structured);
        $this->assertStringContainsString('roles.length ? roles.map', $structured);
        $this->assertStringContainsString('renderCompactTrend(keys, title, meta, inverse = false)', $structured);
        $this->assertStringContainsString("this.renderCompactTrend(['simpanan']", $structured);
        $this->assertStringContainsString("this.renderCompactTrend(['os', 'sml_ratio', 'npl_ratio']", $structured);
        $this->assertStringContainsString('Math.max(0, values.length - 6)', $structured);
        $this->assertStringContainsString('Nominal dan rasio enam bulan terakhir', $structured);
        $this->assertStringContainsString('Data belum tersedia pada scope ini.', $structured);
        $this->assertStringContainsString('.psd-reading {', $styles);
        $this->assertStringContainsString('min-height: 76px;', $styles);
        $this->assertStringContainsString('.psd-reading p {', $styles);
        $this->assertStringContainsString('overflow: visible;', $styles);
        $this->assertStringContainsString('.pres-structured-shell .psd-matrix-row > div', $styles);
        $this->assertStringContainsString('font-size: 14px;', $styles);
        $this->assertStringContainsString('.pres-structured-shell .psd-mini-trend-lane > span strong', $styles);
        $this->assertStringContainsString("ctx.font = '700 14px Inter, sans-serif';", $structured);
        $this->assertStringContainsString("font: { size: 13, weight: '600', lineHeight: 1.05 }", $structured);
        $this->assertStringContainsString("return parts.length > 1 ? [parts[0], parts.slice(1).join(' ')] : label;", $structured);
        $this->assertStringContainsString('overflow: hidden;', $styles);
        $this->assertStringContainsString('minmax(0, 1fr)', $styles);
        $this->assertStringContainsString('window.__presentationLayoutAudit = collectPresentationLayoutAudit', $view);
    }

    public function test_structured_slides_use_the_compact_executive_sheet_system(): void
    {
        $structured = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));
        $engine = file_get_contents(resource_path('js/presentation/pres-engine.js'));
        $styles = file_get_contents(resource_path('css/presentation/pres-structured.css'));

        foreach ([
            'psd-heading-meta',
            "periodLabel = 'Posisi data'",
            '${escapeHtml(periodLabel)}:',
            'psd-header-controls',
            'psd-insight-label',
            'Fokus pembahasan',
            'psd-insight-grid',
            'psd-cover-agenda',
        ] as $marker) {
            $this->assertStringContainsString($marker, $structured);
        }

        $this->assertStringContainsString('Compact executive-sheet system.', $styles);
        $this->assertStringContainsString('grid-template-rows: 106px minmax(0, 1fr) 62px;', $styles);
        $this->assertStringContainsString('grid-template-columns: 190px minmax(0, 1fr);', $styles);
        $this->assertStringContainsString('grid-template-columns: repeat(4, minmax(0, 1fr));', $styles);
        $this->assertStringContainsString('.pres-structured-shell .psd-cover-agenda', $styles);
        $this->assertStringContainsString('Screen geometry guard.', $styles);
        $this->assertStringContainsString('position: absolute !important;', $styles);
        $this->assertStringContainsString('width: var(--pres-slide-width, 1440px) !important;', $styles);
        $this->assertStringContainsString('min-height: 810px !important;', $styles);
        $this->assertStringContainsString('measurementsAreUsable', $engine);
        $this->assertStringContainsString('new ResizeObserver(schedulePresentationFit)', $engine);
    }

    public function test_structured_slide_tables_keep_labels_wrapped_and_numeric_values_clear(): void
    {
        $structured = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));
        $styles = file_get_contents(resource_path('css/presentation/pres-structured.css'));

        $this->assertStringContainsString(
            '#apple-presentation-mode .apple-slide.pres-structured-shell',
            $styles
        );
        $this->assertStringContainsString(
            '.pres-structured-shell .psd-v2-comparison-table td:first-child',
            $styles
        );
        $this->assertStringContainsString('overflow-wrap: anywhere;', $styles);
        $this->assertStringContainsString('font-variant-numeric: tabular-nums;', $styles);
        $this->assertStringContainsString('paint-order: stroke fill;', $styles);
        $this->assertStringContainsString(
            '.psd-v2-data-main.is-dense-table .psd-v2-distribution-list article > div',
            $styles
        );
        $this->assertStringContainsString('grid-template-rows: minmax(12px, 1fr) 4px;', $styles);
        $this->assertStringContainsString('Math.max(13, y(value) - 13)', $structured);
    }

    public function test_micro_slide_uses_dense_executive_highlight_from_available_payloads(): void
    {
        $structured = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));
        $styles = file_get_contents(resource_path('css/presentation/pres-structured.css'));

        foreach ([
            'renderMicroHighlight()',
            "this.summaryMetric('micro_os')",
            'this.data?.micro?.pdwk?.branches',
            'this.data?.micro?.extreme_low_mantri?.rows',
            'this.data?.micro?.rm_kur_tiering?.rows',
            "this.microProductivityView = 'extreme_low'",
            'data-psd-micro-view="extreme_low"',
            'data-psd-micro-view="rm_kur"',
            'Kategori Mantri',
            'Ringkasan Tiga KPI Utama',
            'Kinerja per Produk Kredit',
            'Rekap PDWK per Cabang',
            'Putusan KA Unit',
            'Putusan MBM',
            'Putusan BOH',
            'Total Realisasi',
            'Kategori Mantri per Cabang',
            '<span>Extreme Low</span>',
            '<span>Low</span>',
            'Total &le; 800 Jt',
            '<span>Mid</span>',
            '<span>High</span>',
            'RM Mikro KUR per Tiering dan Cabang',
        ] as $marker) {
            $this->assertStringContainsString($marker, $structured);
        }
        $this->assertStringNotContainsString('Unit Pemutus Perlu Asistensi', $structured);

        foreach ([
            '.pres-structured-shell .psd-micro-highlight-layout',
            '.pres-structured-shell .psd-micro-kpi-grid',
            '.pres-structured-shell .psd-micro-product-table',
            '.pres-structured-shell .psd-micro-support-grid',
            '.pres-structured-shell .psd-micro-branch-table',
            '.pres-structured-shell .psd-micro-value-pair',
            '.pres-structured-shell .psd-micro-branch-table.is-extreme-low',
            '.pres-structured-shell .psd-micro-branch-table.is-tiering',
        ] as $marker) {
            $this->assertStringContainsString($marker, $styles);
        }
    }

    public function test_browser_presentation_explains_every_structured_slide_from_live_data(): void
    {
        $slides = file_get_contents(resource_path('views/presentation/_executive-slides.blade.php'));
        $structured = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));

        preg_match_all("/\\['key' => '([^']+)'/", $slides, $matches);
        $this->assertCount(15, $matches[1] ?? []);

        $this->assertStringContainsString('for (let index = 0; index < 15; index += 1)', $structured);
        $this->assertStringContainsString('const storyHeader = ({', $structured);
        $this->assertStringContainsString("periodLabel = 'Posisi data'", $structured);
        $this->assertStringContainsString('class="psd-reading', $structured);
        $this->assertStringContainsString('Pembacaan data', $structured);
        $this->assertStringContainsString('comparisonTable({', $structured);
        $this->assertStringContainsString('miniTrendChart(', $structured);
        $this->assertStringContainsString('distributionPanel(', $structured);
        $this->assertStringContainsString('quadrantHistoryPanel(', $structured);
        $this->assertStringContainsString('comparisonTable({ periods, rows, inverse: true })', $structured);
    }

    public function test_browser_presentation_finishes_number_animation_and_protects_interactive_controls(): void
    {
        $view = $this->presentationSource();

        $this->assertStringContainsString('const duration = window.matchMedia', $view);
        $this->assertStringContainsString("el.dataset.countupComplete = 'true'", $view);
        $this->assertStringContainsString('window.cancelAnimationFrame(activeFrame)', $view);
        $this->assertStringContainsString('chart.render()', $view);
        $this->assertStringContainsString('animation: false', $view);
        $this->assertStringContainsString("e.target.closest('input, select, textarea, button, [contenteditable=\"true\"], [data-psd-timeseries-expand], [data-psd-marketshare-interactive]')", $view);
        $this->assertStringContainsString("e.key === 'Home'", $view);
        $this->assertStringContainsString("e.key === 'End'", $view);
        $this->assertStringContainsString("e.key.toLowerCase() === 'f'", $view);
        $this->assertStringContainsString('index === 0 || index === 14', $view);
        $this->assertStringContainsString('color: #fff !important', $view);
    }

    public function test_browser_scope_selector_cascades_to_every_data_workspace(): void
    {
        $view = $this->presentationSource();
        $slides = file_get_contents(resource_path('views/presentation/_executive-slides.blade.php'));
        $structured = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));

        $this->assertStringContainsString('id="pres-global-scope-selector"', $view);
        $this->assertStringContainsString('const deckState = { scope:', $view);
        $this->assertStringContainsString('const applyDeckScope = (scope)', $view);
        $this->assertStringContainsString('performanceState.scope = next.key', $view);
        $this->assertStringContainsString('segmentState.scope = next.key', $view);
        $this->assertStringContainsString('riskState.scope = next.key', $view);
        $this->assertStringContainsString("url.searchParams.set('scope', next.key)", $view);
        $this->assertStringContainsString("['key' => 'loan-sme'", $slides);
        $this->assertStringContainsString("['key' => 'loan-consumer'", $slides);
        $this->assertStringContainsString("['key' => 'loan-micro'", $slides);
        $this->assertStringContainsString("() => this.renderSegmentPerformanceV2('sme')", $structured);
        $this->assertStringContainsString("() => this.renderSegmentPerformanceV2('consumer')", $structured);
        $this->assertStringContainsString('() => this.renderMicroHighlight()', $structured);
        $this->assertStringContainsString('findScope(this.data?.productivity?.scopes, this.scopeKey())', $structured);
        $this->assertStringContainsString('findScope(this.data?.timeseries?.scopes, this.scopeKey())', $structured);
    }

    public function test_presentation_uses_daily_loan_period_for_restructured_current_loans(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/DashboardSimpananController.php'));

        $this->assertStringContainsString("->where('periode', \$dailyLoanPeriod)", $controller);
        $this->assertStringContainsString("'restruk' => [", $controller);
        $this->assertStringContainsString("'raw_value' => \$restrukOs", $controller);
        $this->assertStringContainsString("buildPresentationPdwkSummary(\$requestedPeriod)", $controller);
        $this->assertStringContainsString("invokeKinerjaRmMikroPayload('rekap_mantri', \$period, true)", $controller);
        $this->assertStringContainsString("'refresh_pending' => \$refreshPending", $controller);
        $this->assertStringContainsString("'label' => 'K Unit'", $controller);
        $this->assertStringContainsString("'label' => 'MBM'", $controller);
        $this->assertStringContainsString("'boh',", $controller);
        $this->assertStringContainsString("'BOH',", $controller);
    }

    public function test_micro_pdwk_and_nominal_timeseries_overlay_are_available_in_both_outputs(): void
    {
        $structured = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));
        $builder = file_get_contents(app_path('Services/Presentation/PresentationDeckDataService.php'));
        $renderer = file_get_contents(app_path('Services/Presentation/NativeOpenXmlPowerPointRenderer.php'));

        $this->assertStringContainsString('renderMantri()', $structured);
        $this->assertStringContainsString('findScope(this.data?.micro?.pdwk?.scopes, this.scopeKey())', $structured);
        $this->assertStringContainsString('class="psd-role-grid"', $structured);
        $this->assertStringContainsString('PDWK memisahkan kontribusi K Unit, MBM, dan BOH.', $structured);
        $this->assertStringContainsString('displayValues: item.display_values || []', $structured);
        $this->assertStringContainsString('callbacks:', $structured);
        $this->assertStringContainsString('context.dataset.displayValues?.[context.dataIndex]', $structured);
        $this->assertStringContainsString("data_get(\$payload, 'micro.pdwk.scopes.'", $builder);
        $this->assertStringContainsString("'pdwk' => \$key === 'micro' ? \$pdwkScope : []", $builder);
        $this->assertStringContainsString('private function pdwkSlide', $renderer);
        $this->assertStringContainsString('REKAP PDWK PER PEMUTUS', $renderer);
    }

    public function test_performance_trend_keeps_period_nominals_and_dynamic_analysis_visible_in_both_outputs(): void
    {
        $structured = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));
        $styles = file_get_contents(resource_path('css/presentation/pres-structured.css'));
        $loader = file_get_contents(resource_path('js/presentation/pres-data-loader.js'));
        $engine = file_get_contents(resource_path('js/presentation/pres-engine.js'));
        $renderer = file_get_contents(app_path('Services/Presentation/NativeOpenXmlPowerPointRenderer.php'));

        $this->assertStringContainsString('id="psd-timeseries-chart"', $structured);
        $this->assertStringContainsString("id: 'psdValueLabels'", $structured);
        $this->assertStringContainsString('dataset.displayValues?.[pointIndex]', $structured);
        $this->assertStringContainsString('ctx.strokeText', $structured);
        $this->assertStringContainsString('autoSkip: false', $structured);
        $this->assertStringContainsString('data-psd-timeseries-expand', $structured);
        $this->assertStringContainsString('data-psd-timeseries-keys=', $structured);
        $this->assertStringContainsString("seriesKeys: ['simpanan']", $structured);
        $this->assertStringContainsString("seriesKeys: ['os']", $structured);
        $this->assertStringContainsString('seriesKeys: [metricKey]', $structured);
        $this->assertStringContainsString('timeseriesModalData(trigger = null)', $structured);
        $this->assertStringContainsString('Array.from({ length: 31 }', $structured);
        $this->assertStringContainsString('monthKey: month.key', $structured);
        $this->assertStringContainsString('date.getTime() > end.getTime()', $structured);
        $this->assertStringContainsString('timeseriesMetricLatestPeriod(metric, fallback', $structured);
        $this->assertStringContainsString('month.point_count > 0', $structured);
        $this->assertStringContainsString('role="group" aria-label="Pilih indikator timeseries"', $structured);
        $this->assertStringContainsString('data-psd-timeseries-metric=', $structured);
        $this->assertStringContainsString('4 garis bulanan', $structured);
        $this->assertStringContainsString('spanGaps: false', $structured);
        $this->assertStringContainsString('timeseriesMonthLegend(metric, modalData)', $structured);
        $this->assertStringContainsString('timeseries: [4, 5, 7, 8, 9, 11, 12, 13]', $structured);
        $this->assertStringContainsString("this.root.addEventListener('dblclick'", $structured);
        $this->assertStringContainsString('openTimeseriesModal(trigger', $structured);
        $this->assertStringContainsString('scope?.daily', $structured);
        $this->assertStringContainsString('data-psd-timeseries-close', $structured);
        $this->assertStringContainsString("event.key === 'Escape'", $structured);
        $this->assertStringContainsString('this.timeseriesModalChart?.destroy()', $structured);
        $this->assertStringContainsString('.psd-timeseries-modal', $styles);
        $this->assertStringContainsString('.psd-timeseries-dialog-chart', $styles);
        $this->assertStringContainsString('.psd-timeseries-metric-selector', $styles);
        $this->assertStringContainsString('Nominal dan bulan tampil pada grafik', $structured);
        $this->assertStringContainsString('Momentum terkuat', $structured);
        $this->assertStringContainsString('Perlu perhatian', $structured);
        $this->assertStringContainsString('private function trendLabSlide', $renderer);
        $this->assertStringContainsString('Pergerakan indikator utama dengan angka posisi pada setiap periode.', $renderer);
        $this->assertStringContainsString('$this->trendNarrative(', $renderer);
        $this->assertStringContainsString('private function lineChart', $renderer);
        $this->assertStringContainsString("4: ['timeseries']", $loader);
        $this->assertStringContainsString("8: ['productivity', 'timeseries']", $loader);
        $this->assertStringContainsString("12: ['timeseries']", $loader);
        $this->assertStringContainsString('[data-psd-timeseries-expand]', $engine);
    }

    public function test_timeseries_scope_keeps_current_and_previous_three_calendar_months(): void
    {
        $dailyRows = [];
        $date = new \DateTimeImmutable('2026-02-01');
        $end = new \DateTimeImmutable('2026-07-25');
        $index = 1;

        while ($date <= $end) {
            if ($date->format('Y-m-d') !== '2026-05-10') {
                $dailyRows[$date->format('Y-m-d')] = [
                    'simpanan' => $index * 1000000,
                    'casa' => $index * 500000,
                    'os' => $index * 800000,
                    'sml' => $index * 10000,
                    'npl' => $index * 5000,
                ];
            }
            $date = $date->modify('+1 day');
            $index++;
        }

        $service = new PresentationScopeDataService();
        $method = new \ReflectionMethod($service, 'formatTimeseriesScope');
        $scope = $method->invoke($service, $dailyRows, 'area6', 'Area 6 Konsol');

        $this->assertTrue($scope['daily']['available']);
        $this->assertSame('2026-04-01', $scope['daily']['start_period']);
        $this->assertSame('2026-07-25', $scope['daily']['end_period']);
        $this->assertSame('2026-04-01', $scope['daily']['periods'][0]);
        $this->assertSame('2026-07-25', $scope['daily']['periods'][array_key_last($scope['daily']['periods'])]);
        $this->assertCount(116, $scope['daily']['periods']);
        $this->assertCount(116, $scope['daily']['series']['simpanan']['values']);
        $this->assertArrayHasKey('npl_ratio', $scope['daily']['series']);
        $missingIndex = array_search('2026-05-10', $scope['daily']['periods'], true);
        $this->assertIsInt($missingIndex);
        $this->assertNull($scope['daily']['series']['simpanan']['values'][$missingIndex]);
        $this->assertSame('-', $scope['daily']['series']['simpanan']['display_values'][$missingIndex]);
    }

    public function test_structured_deck_maps_kts_details_and_adds_business_decision_context(): void
    {
        $structured = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));
        $styles = file_get_contents(resource_path('css/presentation/pres-structured.css'));

        $this->assertStringContainsString('scopedKtsDetails(payload = this.scopedKts())', $structured);
        $this->assertStringContainsString('branch?.debiturs', $structured);
        $this->assertStringContainsString('row.nomor_rekening', $structured);
        $this->assertStringContainsString('renderBusinessQuadrant()', $structured);
        $this->assertStringContainsString('Garis diagonal = LDR 100%', $structured);
        $this->assertStringContainsString('Pinjaman dibagi Dana / Simpanan', $structured);
        $this->assertStringContainsString('class="psd-closing-kpis"', $structured);
        $this->assertStringContainsString('Array.isArray(card.stats)', $structured);
        $this->assertStringContainsString('.psd-quadrant-panel', $styles);
        $this->assertStringContainsString('.psd-strategy-detail', $styles);
        $this->assertStringContainsString('.psd-closing-kpis', $styles);
    }

    public function test_presentation_progressive_loader_uses_stable_cache_polling_and_slide_priority(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/DashboardSimpananController.php'));
        $snapshotFreshnessJob = file_get_contents(app_path('Jobs/EnsureImportedSnapshotsFreshJob.php'));
        $loader = file_get_contents(resource_path('js/presentation/pres-data-loader.js'));
        $engine = file_get_contents(resource_path('js/presentation/pres-engine.js'));
        $slides = file_get_contents(resource_path('views/presentation/_executive-slides.blade.php'));
        $styles = file_get_contents(resource_path('css/presentation/pres-interactions.css'));
        $serviceWorker = file_get_contents(public_path('presentation-sw.js'));
        $scopeService = file_get_contents(app_path('Services/Presentation/PresentationScopeDataService.php'));

        $this->assertStringContainsString("'pending' => true", $controller);
        $this->assertStringContainsString("'retry_after_ms' => 1500", $controller);
        $this->assertStringContainsString('presentationStablePayloadCacheKey', $controller);
        $this->assertStringContainsString('presentationMicroPayloadForRequest', $controller);
        $this->assertStringContainsString('presentationMicroPayloadCacheKey', $controller);
        $this->assertStringContainsString('buildPresentationMicroForPeriod', $controller);
        $this->assertStringContainsString("if (\$type === 'micro-readiness')", $controller);
        $this->assertStringContainsString("WarmDashboardSimpananCacheJob::dispatch('micro-readiness'", $snapshotFreshnessJob);
        $this->assertStringContainsString('resolvePresentationMicroPeriod', $controller);
        $this->assertStringContainsString("->where('segmen_kinerja', 'MICRO')", $controller);
        $this->assertStringContainsString("'extreme_low_mantri' => \$this->buildPresentationExtremeLowMantri(\$requestedPeriod)", $controller);
        $this->assertStringContainsString("'rm_kur_tiering' => \$this->buildPresentationRmKurTiering(\$requestedPeriod)", $controller);
        $this->assertStringContainsString('->buildEmbeddedPayload($category, $period, $mantri, $extremeLowView)', $controller);
        $this->assertStringContainsString("'per_cabang'", $controller);
        $this->assertStringContainsString(':ppt_deck_v20_marketshare_area6', $controller);
        $this->assertStringContainsString("\$lockSeconds = \$type === 'presentation-payload' ? 300", $controller);
        $this->assertStringContainsString('buildPresentationSummaryPayload', $controller);
        $this->assertStringContainsString("'summary-only'", $controller);
        $this->assertStringContainsString('public function buildSummary', $scopeService);
        $this->assertStringContainsString("'comparison_periods' =>", $scopeService);
        $this->assertStringContainsString("'comparison_scopes' =>", $scopeService);
        $this->assertStringContainsString('private function resolveComparisonPeriods', $scopeService);
        $this->assertStringContainsString('private function buildRmQuadrantHistory', $scopeService);
        $this->assertStringContainsString("'daily' => [", $scopeService);
        $this->assertStringContainsString('subMonthsNoOverflow(3)', $scopeService);
        $this->assertStringContainsString("'start_period' => \$dailyStartDate", $scopeService);
        $this->assertStringContainsString("'end_period' => \$dailyEndDate", $scopeService);

        $this->assertStringContainsString('summaryRefreshTimeout = 120000', $loader);
        $this->assertStringContainsString("if (response?.pending)", $loader);
        $this->assertStringContainsString('async fetchDetail(section, period', $loader);
        $this->assertStringContainsString("section === 'micro' ? this.summaryRefreshTimeout : this.timeout", $loader);
        $this->assertStringContainsString("onStatus('cache-warming')", $loader);
        $this->assertStringContainsString('sectionNamesForSlide(index)', $loader);
        $this->assertStringContainsString("2: ['marketshare']", $loader);
        $this->assertStringContainsString("3: ['marketshare']", $loader);
        $this->assertStringContainsString("4: ['timeseries']", $loader);
        $this->assertStringContainsString("5: ['timeseries']", $loader);
        $this->assertStringContainsString("6: ['digital']", $loader);
        $this->assertStringContainsString("7: ['timeseries']", $loader);
        $this->assertStringContainsString("8: ['productivity', 'timeseries']", $loader);
        $this->assertStringContainsString("9: ['productivity', 'timeseries']", $loader);
        $this->assertStringContainsString("10: ['micro']", $loader);
        $this->assertStringContainsString("11: ['timeseries']", $loader);
        $this->assertStringContainsString("12: ['timeseries']", $loader);
        $this->assertStringContainsString("13: ['timeseries']", $loader);
        $this->assertStringContainsString("14: ['digital']", $loader);
        $this->assertStringContainsString('this.requestedSlideIndex = Number(index) || 0', $loader);
        $this->assertStringContainsString("['micro', 'marketshare']", $loader);
        $this->assertStringContainsString("this.loadSection('micro')", $loader);
        $this->assertStringContainsString('Promise.allSettled', $loader);
        $this->assertStringContainsString("payload?.meta?.cache_state === 'stale-refreshing'", $loader);
        $this->assertStringContainsString('scheduleIdlePreload()', $loader);
        $this->assertStringContainsString("registration.sync.register('presentation-data-refresh')", $loader);
        $this->assertStringContainsString('presentationDataLoader.preloadForSlide(index)', $engine);
        $this->assertStringContainsString('structuredDeck.renderSection(section, data)', $engine);
        $this->assertStringContainsString('void loadPresentation();', $engine);
        $this->assertStringNotContainsString('await loadPresentation();', $engine);

        $this->assertStringContainsString("'progressive' => 'digital'", $slides);
        $this->assertStringContainsString("'progressive' => 'micro'", $slides);
        $this->assertStringContainsString("'progressive' => 'timeseries'", $slides);
        $this->assertStringContainsString("'progressive' => 'marketshare'", $slides);
        $this->assertStringContainsString('data-progressive-section="{{ $story[\'progressive\'] }}"', $slides);
        $this->assertStringContainsString('is-section-loading', $slides);
        $this->assertStringContainsString('@keyframes pres-section-shimmer', $styles);
        $this->assertStringContainsString('@media (max-width: 1100px)', $styles);
        $this->assertStringContainsString("const VERSION = 'presentation-v2'", $serviceWorker);
        $this->assertStringContainsString('refreshCachedPresentationData', $serviceWorker);
        $this->assertStringContainsString('.slice(0, MAX_DATA_PERIODS)', $serviceWorker);
    }

    public function test_browser_presentation_places_area6_and_mapping_slides_after_agenda(): void
    {
        $slides = file_get_contents(resource_path('views/presentation/_executive-slides.blade.php'));
        $structured = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));
        $styles = file_get_contents(resource_path('css/presentation/pres-structured.css'));
        $view = file_get_contents(resource_path('views/presentation.blade.php'));

        $agendaPosition = strpos($slides, "['key' => 'agenda'");
        $area6Position = strpos($slides, "['key' => 'market-share-area6'");
        $mappingPosition = strpos($slides, "['key' => 'market-share-mapping'");
        $fundingPosition = strpos($slides, "['key' => 'funding-summary'");

        $this->assertIsInt($agendaPosition);
        $this->assertIsInt($area6Position);
        $this->assertIsInt($mappingPosition);
        $this->assertIsInt($fundingPosition);
        $this->assertTrue($agendaPosition < $area6Position);
        $this->assertTrue($area6Position < $mappingPosition);
        $this->assertTrue($mappingPosition < $fundingPosition);
        $this->assertStringContainsString("['key' => 'market-share-area6'", $slides);
        $this->assertStringContainsString("['key' => 'market-share-mapping'", $slides);
        $this->assertStringContainsString('renderMarketShareArea6()', $structured);
        $this->assertStringContainsString('renderMarketShareMapping()', $structured);
        $this->assertStringContainsString("'Market Share Area 6'", file_get_contents(resource_path('js/presentation/pres-engine.js')));
        $this->assertStringContainsString('return scopedUnits;', $structured);
        $this->assertStringContainsString('psd-marketshare-area-bars', $structured);
        $this->assertStringContainsString('psd-marketshare-area-table', $structured);
        $this->assertStringContainsString('psd-marketshare-executive-card', $structured);
        $this->assertStringContainsString('data-psd-marketshare-segment', $structured);
        $this->assertStringContainsString("const sector = { key: 'total', label: 'Total seluruh sektor' };", $structured);
        $this->assertStringContainsString("const metric = { key: 'penetration', label: 'Penetrasi' };", $structured);
        $this->assertStringContainsString('const effectivePotential = dashboardPotential', $structured);
        $this->assertStringContainsString('const marketGap = Math.max(0, effectivePotential - effectiveExisting);', $structured);
        $this->assertStringContainsString("mapping?.updated_at ? 'Diperbarui' : 'Posisi data'", $structured);
        $this->assertStringNotContainsString('data-psd-marketshare-sector', $structured);
        $this->assertStringNotContainsString('data-psd-marketshare-metric', $structured);
        $this->assertStringContainsString('<thead>', $structured);
        $this->assertStringContainsString('drawMarketShareMap()', $structured);
        $this->assertStringContainsString('destroyMarketShareMap()', $structured);
        $this->assertStringContainsString('this.marketShareMap.invalidateSize', $structured);
        $this->assertStringContainsString('.psd-marketshare-map-main', $styles);
        $this->assertStringContainsString('.psd-marketshare-area-main', $styles);
        $this->assertStringContainsString('.psd-marketshare-area-table', $styles);
        $this->assertStringContainsString('.psd-marketshare-executive-card', $styles);
        $this->assertStringContainsString("asset('vendor/leaflet-1.9.4/leaflet.js')", $view);
        $this->assertStringContainsString('Slide 1 dari 15', $view);
    }

    public function test_comparison_scope_propagates_rka_to_totals_segments_and_products(): void
    {
        $service = new PresentationScopeDataService();
        $periods = [
            'yoy' => ['date' => '2025-07-23', 'label' => '23 Jul 25'],
            'ytd' => ['date' => '2025-12-31', 'label' => '31 Des 25'],
            'm2' => ['date' => '2026-05-31', 'label' => '31 Mei 26'],
            'mom' => ['date' => '2026-06-23', 'label' => '23 Jun 26'],
            'mtd' => ['date' => '2026-06-30', 'label' => '30 Jun 26'],
            'dtd' => ['date' => '2026-07-22', 'label' => '22 Jul 26'],
            'current' => ['date' => '2026-07-23', 'label' => '23 Jul 26'],
        ];
        $metrics = [
            'simpanan' => 1000.0,
            'funding_retail' => 400.0,
            'funding_wholesale' => 100.0,
            'funding_micro' => 500.0,
            'giro' => 200.0,
            'tabungan' => 650.0,
            'deposito' => 150.0,
            'os' => 800.0,
            'sml' => 80.0,
            'npl' => 40.0,
            'sme_os' => 300.0,
            'sme_sml' => 30.0,
            'sme_npl' => 15.0,
            'consumer_os' => 200.0,
            'consumer_sml' => 20.0,
            'consumer_npl' => 10.0,
            'micro_os' => 300.0,
            'micro_sml' => 30.0,
            'micro_npl' => 15.0,
            'sme_non_cashcoll_os' => 250.0,
            'sme_non_cashcoll_sml' => 24.0,
            'sme_non_cashcoll_npl' => 12.0,
            'sme_cashcoll_os' => 50.0,
            'sme_cashcoll_sml' => 6.0,
            'sme_cashcoll_npl' => 3.0,
            'consumer_briguna_os' => 150.0,
            'consumer_briguna_sml' => 15.0,
            'consumer_briguna_npl' => 8.0,
            'consumer_kpr_os' => 50.0,
            'consumer_kpr_sml' => 5.0,
            'consumer_kpr_npl' => 2.0,
            'micro_kur_kecil_os' => 60.0,
            'micro_kur_kecil_sml' => 8.0,
            'micro_kur_kecil_npl' => 4.0,
        ];
        $snapshotRows = [];
        foreach ($periods as $period) {
            $snapshotRows[$period['date']]['area6'] = $metrics;
        }
        $rka = collect($metrics)->map(fn (float $value): float => $value / 2)->all();
        $rka['sme_cashcoll_npl'] = 0.0;

        $method = new \ReflectionMethod($service, 'buildComparisonScope');
        $scope = $method->invoke(
            $service,
            $snapshotRows,
            $periods,
            'area6',
            'Area 6 Konsol',
            [],
            $rka
        );

        $fundingProducts = collect($scope['funding']['products'])->keyBy('key');
        $creditSegments = collect($scope['credit']['segments'])->keyBy('key');
        $smeProducts = collect($creditSegments['sme']['products'])->keyBy('key');
        $microProducts = collect($creditSegments['micro']['products'])->keyBy('key');

        $this->assertSame(100.0, $fundingProducts['giro']['rka']);
        $this->assertSame(150.0, $creditSegments['sme']['os']['rka']);
        $this->assertSame(12.0, $smeProducts['non_cashcoll']['sml']['rka']);
        $this->assertSame('Rp0', $smeProducts['cashcoll']['npl']['rka_fmt']);
        $this->assertSame(3.0, $smeProducts['cashcoll']['npl']['gap']);
        $this->assertSame(2.0, $microProducts['kur_kecil']['npl']['rka']);
        $this->assertSame(50.0, $scope['credit']['total']['sml']['achievement']);
        $this->assertSame(50.0, $creditSegments['sme']['npl']['achievement']);
    }

    public function test_cashcoll_npl_rka_is_derived_from_small_less_non_cashcoll(): void
    {
        $service = new PresentationScopeDataService();
        $method = new \ReflectionMethod($service, 'finalizeComparisonRkaValues');
        $values = $method->invoke($service, [
            'sme_small_npl_basis' => 150.0,
            'sme_non_cashcoll_npl' => 125.0,
        ]);

        $this->assertSame(25.0, $values['sme_cashcoll_npl']);
        $this->assertArrayNotHasKey('sme_small_npl_basis', $values);
    }

    public function test_native_powerpoint_builder_reads_product_rka_from_comparison_payload(): void
    {
        $service = new PresentationDeckDataService();
        $method = new \ReflectionMethod($service, 'resolveRka');
        $payload = [
            'comparison' => [
                'scopes' => [
                    'area6' => [
                        'funding' => [
                            'total' => ['rka' => 1000.0],
                            'products' => [
                                ['key' => 'giro', 'rka' => 200.0],
                                ['key' => 'deposito', 'rka' => 0.0],
                            ],
                        ],
                        'credit' => [
                            'segments' => [
                                [
                                    'key' => 'sme',
                                    'os' => ['rka' => 300.0],
                                    'products' => [
                                        ['key' => 'cashcoll', 'os' => ['rka' => 25.0]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(
            200.0,
            $method->invoke($service, $payload, 'area6', 'funding', 'giro', 'value')
        );
        $this->assertSame(
            25.0,
            $method->invoke($service, $payload, 'area6', 'sme', 'cashcoll', 'os')
        );
        $this->assertSame(
            0.0,
            $method->invoke($service, $payload, 'area6', 'funding', 'deposito', 'value')
        );
    }

    public function test_funding_strategy_payload_keeps_selected_scope_and_qris_rka(): void
    {
        $service = new PresentationDeckDataService();
        $method = new \ReflectionMethod($service, 'buildFundingStrategies');
        $payload = [
            'period_label' => '23 Jul 26',
            'scopes' => [
                'area6' => [
                    'scope_key' => 'area6',
                    'scope_label' => 'Area 6 Konsol',
                ],
                'KC MADIUN' => [
                    'scope_key' => 'KC MADIUN',
                    'scope_label' => 'KC Madiun',
                    'digital' => [
                        'rows' => [
                            [
                                'key' => 'qris',
                                'label' => 'QRIS',
                                'rka' => ['raw' => 167375000000.0, 'fmt' => 'Rp167,38 M'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $selected = $method->invoke($service, $payload, 'KC Madiun', 'KC Madiun');

        $this->assertSame('KC MADIUN', $selected['scope_key']);
        $this->assertSame('KC Madiun', $selected['scope_label']);
        $this->assertSame(167375000000.0, data_get($selected, 'digital.rows.0.rka.raw'));
        $this->assertSame('Rp167,38 M', data_get($selected, 'digital.rows.0.rka.fmt'));
    }

    public function test_funding_strategy_sources_and_renderers_cover_the_requested_tables(): void
    {
        $strategyService = (string) file_get_contents(
            app_path('Services/Presentation/PresentationFundingStrategyService.php')
        );
        $browserRenderer = (string) file_get_contents(
            resource_path('js/presentation/pres-structured-deck.js')
        );
        $nativeRenderer = (string) file_get_contents(
            app_path('Services/Presentation/NativeOpenXmlPowerPointRenderer.php')
        );

        foreach ([
            "'edc'",
            "'qris'",
            "'casa_merchant'",
            "'brimo'",
            "'brilink'",
            "'qlola'",
            'qrisVolumeRkaByBranch',
            'Sales Volume QRIS',
            'rasio_casa_debitur_snapshots',
            'performance_pis_per_produk',
            'buildBusinessClusterRows',
            'rekening_dormant_snapshots',
            'emptySupportingStrategies',
        ] as $expected) {
            $this->assertStringContainsString($expected, $strategyService);
        }

        foreach ([
            'Optimalisasi Digital Channel',
            'Rekening Transaksi Debitur',
            'Bisnis Cluster | Top 5',
            'Peningkatan Payroll Berkualitas',
            'Rekening Dormant',
            'RKA / Penc.',
            'psd-v2-strategy-orbit',
            'psd-strategy-core-ring',
            'psd-strategy-actionbar',
        ] as $expected) {
            $this->assertStringContainsString($expected, $browserRenderer);
        }

        $this->assertStringContainsString('funding_strategies', $nativeRenderer);
        $this->assertStringContainsString('OPTIMALISASI DIGITAL CHANNEL', $nativeRenderer);
        $this->assertStringContainsString('FUNDING EXECUTION MAP', $nativeRenderer);
        $this->assertStringContainsString("data_get(\$row, 'rka.fmt', '-')", $nativeRenderer);
    }

    private function presentationSource(): string
    {
        $paths = [
            resource_path('views/presentation.blade.php'),
            resource_path('views/presentation/_executive-slides.blade.php'),
            resource_path('css/presentation/pres-core.css'),
            resource_path('css/presentation/pres-theme-bri.css'),
            resource_path('css/presentation/pres-slides.css'),
            resource_path('css/presentation/pres-interactions.css'),
            resource_path('css/presentation/pres-structured.css'),
            resource_path('js/presentation/pres-engine.js'),
            resource_path('js/presentation/pres-charts.js'),
            resource_path('js/presentation/pres-data-loader.js'),
            resource_path('js/presentation/pres-offline-store.js'),
            resource_path('js/presentation/pres-interactions.js'),
            resource_path('js/presentation/pres-structured-deck.js'),
        ];

        return collect($paths)
            ->map(fn (string $path): string => is_file($path) ? (string) file_get_contents($path) : '')
            ->implode(PHP_EOL);
    }
}
