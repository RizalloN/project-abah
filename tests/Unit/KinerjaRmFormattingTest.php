<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmReportController;
use App\Support\RkaLookupService;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class KinerjaRmFormattingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_controller_formatters_normalize_amounts_and_quadrants(): void
    {
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $this->assertSame('1.600,0', $this->invokePrivateMethod($controller, 'formatAmountInJuta', [1600000000]));
        $this->assertStringContainsString('+1,0', $this->invokePrivateMethod($controller, 'formatSignedAmountInJuta', [1000000, false]));
        $this->assertSame('Kuadran 2', $this->invokePrivateMethod($controller, 'formatQuadrantLabel', [2]));
        $this->assertSame('-', $this->invokePrivateMethod($controller, 'formatQuadrantLabel', [7]));
        $this->assertSame('q3', $this->invokePrivateMethod($controller, 'formatQuadrantClass', [3]));
        $this->assertSame('', $this->invokePrivateMethod($controller, 'formatQuadrantClass', [null]));
    }

    public function test_controller_product_mapping_supports_small_and_micro_rules(): void
    {
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $this->assertSame('CONSUMER', $this->invokePrivateMethod($controller, 'normalizeProductLabel', ['Briguna Konsumer', 'CONSUMER']));
        $this->assertSame('CONSUMER', $this->invokePrivateMethod($controller, 'normalizeProductLabel', ['kpr', 'CONSUMER']));

        $this->assertSame('CASHCALL', $this->invokePrivateMethod($controller, 'normalizeProductLabel', ['cashcall', 'SMALL']));
        $this->assertNull($this->invokePrivateMethod($controller, 'normalizeProductLabel', ['cashcoll', 'SMALL']));

        $this->assertSame('CASHCOLLATERAL', $this->invokePrivateMethod($controller, 'normalizeProductLabel', ['Cash Collateral', 'MICRO']));
        $this->assertSame('KUR-SMALL', $this->invokePrivateMethod($controller, 'normalizeProductLabel', ['kur-small', 'MICRO']));
        $this->assertSame('KPR', $this->invokePrivateMethod($controller, 'normalizeProductLabel', ['kpr', 'MICRO']));
    }

    public function test_controller_uses_consumer_monthly_target_table(): void
    {
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $target = $this->invokePrivateMethod($controller, 'resolveManualTargetForProduct', [
            collect(),
            'CONSUMER',
            'Aris Sulistyawan',
        ]);

        $this->assertSame(19, $target['target_jg_deb']);
        $this->assertSame(3700000000.0, $target['target_jg_os']);

        $ronaTarget = $this->invokePrivateMethod($controller, 'resolveManualTargetForProduct', [
            collect(),
            'CONSUMER',
            'Rona Rohana Talibata',
        ]);

        $this->assertSame(20, $ronaTarget['target_jg_deb']);
        $this->assertSame(3750000000.0, $ronaTarget['target_jg_os']);
    }

    public function test_controller_calculates_consumer_quadrant_from_target_achievement(): void
    {
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $this->assertSame(1, $this->invokePrivateMethod($controller, 'calculateConsumerQuadrant', [3937500000, 3750000000.0]));
        $this->assertSame(2, $this->invokePrivateMethod($controller, 'calculateConsumerQuadrant', [3750000000, 3750000000.0]));
        $this->assertSame(3, $this->invokePrivateMethod($controller, 'calculateConsumerQuadrant', [1875000000, 3750000000.0]));
        $this->assertSame(4, $this->invokePrivateMethod($controller, 'calculateConsumerQuadrant', [1874999999, 3750000000.0]));
        $this->assertNull($this->invokePrivateMethod($controller, 'calculateConsumerQuadrant', [1000000, 0.0]));
    }

    public function test_kinerjarm_table_section_renders_amounts_in_juta_and_quadrant_badge(): void
    {
        $html = view('report.kinerjarm-table-section', [
            'selectedSegmen' => 'CONSUMER',
            'selectedProductLabel' => 'Semua Produk',
            'selectedPeriod' => '2026-04-23',
            'selectedPeriodShortLabel' => '23 Apr 26',
            'selectedPeriodLabel' => '23 Apr 2026',
            'yoyPeriod' => '2026-03-31',
            'yoyShortLabel' => '31 Mar 26',
            'ytdPeriod' => '2025-12-31',
            'ytdShortLabel' => '31 Des 25',
            'mtdPeriod' => '2026-03-31',
            'mtdShortLabel' => '31 Mar 26',
            'showTargets' => true,
            'compact' => false,
            'rows' => [
                [
                    'cabang' => 'KC TEST',
                    'branch_rowspan' => 2,
                    'subtotal' => [
                        'yoy' => 1500000000,
                        'ytd' => 1400000000,
                        'mtd' => 1300000000,
                        'curr' => 1600000000,
                        'delta_yoy' => 100000000,
                        'delta_ytd' => 200000000,
                        'delta_mtd' => 300000000,
                        'target_jg_deb' => 20,
                        'target_jg_os' => 3750000000,
                        'ach_deb' => 21,
                        'ach_os' => 4100000000,
                    ],
                    'rms' => [
                        'RM TEST' => [
                            'rm_rowspan' => 1,
                            'quadrant' => 2,
                            'items' => [
                                [
                                    'product' => 'BRIGUNA-KONSUMER',
                                    'yoy' => 1500000000,
                                    'ytd' => 1400000000,
                                    'mtd' => 1300000000,
                                    'curr' => 1600000000,
                                    'delta_yoy' => 100000000,
                                    'delta_ytd' => 200000000,
                                    'delta_mtd' => 300000000,
                                    'target_jg_deb' => 20,
                                    'target_jg_os' => 3750000000,
                                    'ach_deb' => 21,
                                    'ach_os' => 4100000000,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'total' => [
                'yoy' => 1500000000,
                'ytd' => 1400000000,
                'mtd' => 1300000000,
                'curr' => 1600000000,
                'delta_yoy' => 100000000,
                'delta_ytd' => 200000000,
                'delta_mtd' => 300000000,
                'target_jg_deb' => 20,
                'target_jg_os' => 3750000000,
                'ach_deb' => 21,
                'ach_os' => 4100000000,
            ],
        ])->render();

        $this->assertStringContainsString('1.600,0', $html);
        $this->assertStringContainsString('Delta OS', $html);
        $this->assertStringContainsString('Kuadran 2', $html);
        $this->assertStringContainsString('cell-quadrant q2', $html);
        $this->assertStringNotContainsString('% LAR</th>', $html);
        $this->assertStringContainsString('31 Mar 26', $html);
        $this->assertStringContainsString('31 Des 25', $html);
        $this->assertStringContainsString('23 Apr 26', $html);
        $this->assertStringContainsString('Delta', $html);
        $this->assertStringNotContainsString('Gap vs Posisi', $html);
    }

    public function test_kinerjarm_quality_series_renders_actual_period_labels_and_loan_reference(): void
    {
        $comparisonColumns = [
            ['key' => 'yoy', 'short_label' => '20 Jun 25'],
            ['key' => 'ytd', 'short_label' => '31 Des 25'],
            ['key' => 'm2', 'short_label' => '30 Apr 26'],
            ['key' => 'm1', 'short_label' => '31 Mei 26'],
        ];
        $item = [
            'product' => 'CONSUMER',
            'loan_os_reference' => 1000000000,
            'comparison_values' => [
                'yoy' => 300000000,
                'ytd' => 290000000,
                'm2' => 280000000,
                'm1' => 320000000,
            ],
            'comparison_deltas' => [
                'yoy' => 10000000,
                'ytd' => 20000000,
                'm2' => 30000000,
                'm1' => -10000000,
            ],
            'curr' => 310000000,
        ];
        $subtotal = array_merge($item, ['loan_os_reference' => 1000000000]);

        $html = view('report.kinerjarm-quality-series-section', [
            'selectedPeriodShortLabel' => '20 Jun 26',
            'sectionTitle' => 'LAR',
            'componentLabel' => 'LAR',
            'showLoanReference' => true,
            'lowerIsBetter' => true,
            'comparisonColumns' => $comparisonColumns,
            'rows' => [[
                'cabang' => 'KC TEST',
                'branch_rowspan' => 2,
                'subtotal' => $subtotal,
                'rms' => [
                    'RM TEST' => [
                        'rm' => 'RM TEST',
                        'rm_category' => 'KCP',
                        'rm_unit' => 'KCP CARUBAN',
                        'rm_unit_code' => '552',
                        'rm_rowspan' => 1,
                        'items' => [$item],
                    ],
                ],
            ]],
            'total' => $subtotal,
        ])->render();

        $this->assertStringContainsString('>No<', $html);
        $this->assertStringContainsString('Kode Uker', $html);
        $this->assertStringContainsString('Nama RM', $html);
        $this->assertStringContainsString('>552<', $html);
        $this->assertStringContainsString('quality-row-number', $html);
        $this->assertStringContainsString('OS Pinjaman', $html);
        $this->assertStringContainsString('Series LAR', $html);
        $this->assertStringContainsString('quality-series-head', $html);
        $this->assertStringContainsString('quality-delta-head', $html);
        $this->assertStringContainsString('quality-current-head', $html);
        $this->assertStringContainsString('quality-current-cell', $html);
        $this->assertStringContainsString('quality-delta-cell', $html);
        $this->assertStringContainsString('--kinerja-no-column-width: 48px;', $html);
        $this->assertStringContainsString('--kinerja-branch-column-width: 112px;', $html);
        $this->assertStringContainsString('quality-sticky-rm', $html);
        $this->assertStringContainsString('KC TEST</td>', $html);
        $this->assertStringNotContainsString('rowspan="2" class="merged-branch-cell', $html);
        $this->assertMatchesRegularExpression(
            '/quality-row-number[^>]*>1<\/td>\s*<td[^>]*quality-sticky-branch[^>]*>KC TEST<\/td>/s',
            $html
        );
        $this->assertStringContainsString('20 Jun 25', $html);
        $this->assertStringContainsString('31 Des 25', $html);
        $this->assertStringContainsString('30 Apr 26', $html);
        $this->assertStringContainsString('31 Mei 26', $html);
        $this->assertStringContainsString('20 Jun 26', $html);
        $this->assertStringNotContainsString('>YoY<', $html);
        $this->assertStringNotContainsString('>YTD<', $html);
        $this->assertStringNotContainsString('>M-1<', $html);
        $this->assertStringContainsString('cell-pos', $html);
        $this->assertStringContainsString('cell-neg', $html);
    }

    public function test_quality_series_rows_sort_by_unit_code_when_requested(): void
    {
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));
        $branches = [
            'KC B' => [
                'cabang' => 'KC B',
                'rms' => [
                    'RM 552' => ['rm' => 'RM 552', 'rm_unit_code' => '552', 'items' => [['product' => 'LAR']]],
                    'RM 45' => ['rm' => 'RM 45', 'rm_unit_code' => '45', 'items' => [['product' => 'LAR']]],
                ],
            ],
            'KC A' => [
                'cabang' => 'KC A',
                'rms' => [
                    'RM 7' => ['rm' => 'RM 7', 'rm_unit_code' => '7', 'items' => [['product' => 'LAR']]],
                ],
            ],
        ];

        $sorted = $this->invokePrivateMethod($controller, 'sortKinerjaRmBranches', [$branches, 'CONSUMER', true]);

        $this->assertSame(['KC A', 'KC B'], array_keys($sorted));
        $this->assertSame(['RM 45', 'RM 552'], array_keys($sorted['KC B']['rms']));
        $this->assertSame(3, $sorted['KC B']['branch_rowspan']);
    }

    public function test_kinerjarm_history_modal_renders_million_format_for_realisasi_os(): void
    {
        $html = view('report.kinerjarm-detail-modal', [
            'rm' => 'RM TEST',
            'details' => [
                [
                    'periode' => 'Apr 2026',
                    'cabang' => 'KC TEST',
                    'realisasi_os' => 1600000000,
                    'penc_realisasi' => 'A',
                    'pct_lar' => 12.3456,
                    'penc_lar' => 'A',
                ],
            ],
            'formatAmount' => fn ($value, int $decimals = 1) => number_format(((float) $value) / 1000000, $decimals, ',', '.'),
            'formatPercent' => fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.'),
        ])->render();

        $this->assertStringContainsString('Realisasi OS (Rp Juta)', $html);
        $this->assertStringContainsString('1.600', $html);
        $this->assertStringContainsString('12,35%', $html);
    }

    public function test_kinerjarm_small_history_modal_uses_closed_month_summary(): void
    {
        $details = collect([
            ['periode' => 'Jan 2026', 'periode_raw' => '2026-01-31', 'year' => 2026, 'cabang' => 'KC PONOROGO', 'loan_os' => 1000000000, 'lar_value' => 160000000, 'realisasi_os' => 2750000000, 'penc_realisasi' => 'A', 'pct_lar' => 16.0, 'penc_lar' => 'A'],
            ['periode' => 'Feb 2026', 'periode_raw' => '2026-02-28', 'year' => 2026, 'cabang' => 'KC PONOROGO', 'loan_os' => 1000000000, 'lar_value' => 190000000, 'realisasi_os' => 2500000000, 'penc_realisasi' => 'A', 'pct_lar' => 19.0, 'penc_lar' => 'B'],
            ['periode' => 'Mar 2026', 'periode_raw' => '2026-03-31', 'year' => 2026, 'cabang' => 'KC PONOROGO', 'loan_os' => 1000000000, 'lar_value' => 140000000, 'realisasi_os' => 5150000000, 'penc_realisasi' => 'A', 'pct_lar' => 14.0, 'penc_lar' => 'A'],
            ['periode' => 'Apr 2026', 'periode_raw' => '2026-04-30', 'year' => 2026, 'cabang' => 'KC PONOROGO', 'loan_os' => 1000000000, 'lar_value' => 120000000, 'realisasi_os' => 10900000000, 'penc_realisasi' => 'A', 'pct_lar' => 12.0, 'penc_lar' => 'A'],
            ['periode' => 'Mei 2026', 'periode_raw' => '2026-05-31', 'year' => 2026, 'cabang' => 'KC PONOROGO', 'loan_os' => 1000000000, 'lar_value' => 130700000, 'realisasi_os' => 2350000000, 'penc_realisasi' => 'A', 'pct_lar' => 13.07, 'penc_lar' => 'A'],
            ['periode' => 'Jun 2026', 'periode_raw' => '2026-06-20', 'year' => 2026, 'cabang' => 'KC PONOROGO', 'loan_os' => 1000000000, 'lar_value' => 300000000, 'realisasi_os' => 2500000000, 'penc_realisasi' => 'A', 'pct_lar' => 30.0, 'penc_lar' => 'B'],
        ]);
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));
        $summaries = $this->invokePrivateMethod($controller, 'buildSmallHistorySummaries', [$details, '2026-06-20']);

        $html = view('report.kinerjarm-detail-modal', [
            'rm' => '00063020 - ANTON PURWANTO',
            'segmen' => 'SMALL',
            'details' => $details,
            'smallSummariesByYear' => $summaries,
            'selectedHistoryYear' => 2026,
            'formatAmount' => fn ($value, int $decimals = 0) => number_format(((float) $value) / 1000000, $decimals, ',', '.'),
            'formatPercent' => fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.'),
        ])->render();

        $this->assertSame(4730000000.0, $summaries['2026']['realisasi_os']);
        $this->assertEqualsWithDelta(13.07, $summaries['2026']['pct_lar'], 0.0001);
        $this->assertStringContainsString('Ratas Realisasi OS 2026', $html);
        $this->assertStringContainsString('4.730', $html);
        $this->assertStringContainsString('% LAR May 2026', $html);
        $this->assertStringContainsString('13,07%', $html);
        $this->assertStringContainsString('RATAS 2026', $html);
    }

    public function test_kinerjarm_consumer_history_modal_renders_target_and_pg(): void
    {
        $html = view('report.kinerjarm-detail-modal', [
            'rm' => '00187063 - RONA ROHANA TALIBATA',
            'segmen' => 'CONSUMER',
            'detailMode' => 'consumer_surplus',
            'historyRangeLabel' => 'Jan 2026 - Mei 2026',
            'selectedHistoryYear' => 2026,
            'details' => [
                [
                    'periode' => '31 Mei 2026',
                    'periode_raw' => '2026-05-31',
                    'year' => 2026,
                    'previous_period' => '30 Apr 2026',
                    'current_debitur' => 128,
                    'previous_debitur' => 126,
                    'debitur' => 2,
                    'current_os' => 230451000000,
                    'previous_os' => 228090000000,
                    'delta_os' => 2361000000,
                    'surplus_plafon' => 2361000000,
                    'target_jg_deb' => 20,
                    'target_jg_os' => 3750000000,
                ],
            ],
            'formatAmount' => fn ($value, int $decimals = 0) => number_format(((float) $value) / 1000000, $decimals, ',', '.'),
            'formatPercent' => fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.'),
        ])->render();

        $this->assertStringContainsString('Target JG 2026', $html);
        $this->assertStringContainsString('Target Deb', $html);
        $this->assertStringContainsString('Target Rp', $html);
        $this->assertStringContainsString('OS Bulan Ini 2026', $html);
        $this->assertStringContainsString('OS Bulan Lalu 2026', $html);
        $this->assertStringContainsString('Delta OS 2026', $html);
        $this->assertStringContainsString('% PG', $html);
        $this->assertStringContainsString('3.750', $html);
        $this->assertStringContainsString('2.361', $html);
        $this->assertStringContainsString('62,96%', $html);
    }

    public function test_kinerjarm_table_section_renders_zero_realisasi_values(): void
    {
        $html = view('report.kinerjarm-table-section', [
            'selectedSegmen' => 'SMALL',
            'selectedProductLabel' => 'Semua Produk',
            'selectedPeriod' => '2026-04-20',
            'selectedPeriodShortLabel' => '20 Apr 26',
            'selectedPeriodLabel' => '20 Apr 2026',
            'yoyPeriod' => '2025-03-31',
            'ytdPeriod' => '2025-12-31',
            'mtdPeriod' => '2026-03-31',
            'showTargets' => true,
            'compact' => false,
            'rows' => [
                [
                    'cabang' => 'KC TEST',
                    'branch_rowspan' => 2,
                    'subtotal' => [
                        'yoy' => 100,
                        'ytd' => 100,
                        'mtd' => 100,
                        'curr' => 100,
                        'delta_yoy' => 0,
                        'delta_ytd' => 0,
                        'delta_mtd' => 0,
                        'target_jg_deb' => 0,
                        'target_jg_os' => 0,
                        'ach_deb' => 0,
                        'ach_os' => 0,
                    ],
                    'rms' => [
                        'RM TEST' => [
                            'rm_rowspan' => 1,
                            'quadrant' => null,
                            'items' => [
                                [
                                    'product' => 'COMMERCIAL',
                                    'yoy' => 100,
                                    'ytd' => 100,
                                    'mtd' => 100,
                                    'curr' => 100,
                                    'delta_yoy' => 0,
                                    'delta_ytd' => 0,
                                    'delta_mtd' => 0,
                                    'target_jg_deb' => 0,
                                    'target_jg_os' => 0,
                                    'ach_deb' => 0,
                                    'ach_os' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'total' => [
                'yoy' => 100,
                'ytd' => 100,
                'mtd' => 100,
                'curr' => 100,
                'delta_yoy' => 0,
                'delta_ytd' => 0,
                'delta_mtd' => 0,
                'target_jg_deb' => 0,
                'target_jg_os' => 0,
                'ach_deb' => 0,
                'ach_os' => 0,
            ],
            'formatAmount' => fn ($value, int $decimals = 1) => number_format(((float) $value) / 1000000, $decimals, ',', '.'),
            'formatCount' => fn ($value) => number_format((int) round((float) $value), 0, ',', '.'),
        ])->render();

        $this->assertStringContainsString('<td class="text-center-important">0</td>', $html);
        $this->assertStringContainsString('<td>0,0</td>', $html);
    }

    public function test_kinerjarm_view_uses_kinerja_rm_ritel_label(): void
    {
        $view = file_get_contents(resource_path('views/report/kinerjarm.blade.php'));
        $table = file_get_contents(resource_path('views/report/kinerjarm-table.blade.php'));
        $sidebar = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));

        $this->assertStringContainsString("@section('title', 'Kinerja RM Ritel')", $view);
        $this->assertStringContainsString('Kinerja RM Ritel', $view);
        $this->assertStringContainsString('Kinerja-RM-Ritel', $view);
        $this->assertStringContainsString('.rm-ritel-page .kinerja-table-container::-webkit-scrollbar-thumb', $view);
        $this->assertStringContainsString('scrollbar-color: #cbd5e1 #ffffff', $view);
        $this->assertStringContainsString('--kinerja-branch-column-width: 94px;', $view);
        $this->assertStringContainsString('left: var(--kinerja-branch-column-width) !important;', $view);
        $this->assertStringContainsString('scrollbar-gutter: stable;', $view);
        $this->assertStringNotContainsString('scrollbar-gutter: stable both-edges;', $view);
        $this->assertStringNotContainsString('scrollbar-color: #1d4ed8 #dbeafe', $view);
        $this->assertStringContainsString('Performance & Kualitas', $table);
        $this->assertStringContainsString("@include('report.kinerjarm-performance-table-section')", $table);
        $this->assertStringContainsString('Navigasi Kinerja RM Ritel', $table);
        $qualityOrder = [
            "'os' => [",
            "'lancar' => [",
            "'lr' => [",
            "'lnr' => [",
            "'account_restruk' => [",
            "'sml_1' => [",
            "'sml_2' => [",
            "'sml_3' => [",
            "'kl' => [",
            "'d1' => [",
            "'d2' => [",
            "'m' => [",
        ];
        $previousPosition = -1;
        foreach ($qualityOrder as $definition) {
            $position = strpos($table, $definition);
            $this->assertNotFalse($position, "Definisi kualitas {$definition} tidak ditemukan.");
            $this->assertGreaterThan($previousPosition, $position, "Urutan kualitas {$definition} tidak sesuai.");
            $previousPosition = $position;
        }
        $this->assertStringNotContainsString("'lar' => [", $table);
        $this->assertStringContainsString('<p>Kinerja RM Ritel</p>', $sidebar);
        $this->assertStringNotContainsString('Dashboard RM Ritel', $view);
        $this->assertStringNotContainsString('Kinerja RM Performance Report', $view);
        $this->assertStringNotContainsString('Report RM Performance', $view);
    }

    public function test_small_performance_table_uses_monthly_rp_lar_and_closed_month_accumulation(): void
    {
        $months = collect([
            ['key' => '2026-07', 'short_label' => 'Jul 26', 'period' => '2026-07-31', 'period_label' => '31 Jul 2026', 'is_closed' => true],
            ['key' => '2026-08', 'short_label' => 'Agu 26', 'period' => '2026-08-09', 'period_label' => '09 Agu 2026', 'is_closed' => false],
        ]);
        $row = [
            'unit_code' => '45',
            'unit' => 'KC MADIUN',
            'cabang' => 'KC MADIUN',
            'rm' => '0001 - RM SMALL',
            'rm_display' => 'RM SMALL',
            'months' => [
                '2026-07' => ['rp' => 3000000000, 'lar_pct' => 15.0, 'has_data' => true],
                '2026-08' => ['rp' => 10000000000, 'lar_pct' => 50.0, 'has_data' => true],
            ],
            'accumulated' => ['ratas_rp' => 3000000000, 'lar_pct' => 15.0],
            'quadrant' => 1,
        ];
        $total = [
            'months' => $row['months'],
            'accumulated' => $row['accumulated'],
        ];

        $html = view('report.kinerjarm-performance-table-section', [
            'selectedSegmen' => 'SMALL',
            'selectedPeriod' => '2026-08-09',
            'performanceMonths' => $months,
            'performanceRows' => [$row],
            'performanceTotal' => $total,
            'performanceMeta' => [
                'latest_period_label' => '09 Agu 2026',
                'closed_range_label' => 'Jan 26 - Jul 26',
                'closed_through_period_label' => '31 Jul 2026',
                'hidden_inactive_count' => 17,
            ],
        ])->render();

        $this->assertStringContainsString('Realisasi &amp; LAR per RM', $html);
        $this->assertStringContainsString('Rp / % LAR', $html);
        $this->assertStringContainsString('15,00%', $html);
        $this->assertStringContainsString('Kuadran 1', $html);
        $this->assertStringContainsString('- berjalan', $html);
        $this->assertStringNotContainsString('Target JG / Bulan', $html);
        $this->assertStringNotContainsString('performance-group-head--delta', $html);
        $this->assertStringNotContainsString('Jml Deb', $html);
        $this->assertStringNotContainsString('Terakhir 09 Agu 2026', $html);
        $this->assertStringNotContainsString('Ratas Jan 26 - Jul 26', $html);
        $this->assertStringNotContainsString('LAR bulan tutup 31 Jul 2026', $html);
        $this->assertStringNotContainsString('17 RM tanpa realisasi 2 bulan disaring', $html);
    }

    private function invokePrivateMethod(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $reflectedMethod = $reflection->getMethod($method);
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invokeArgs($object, $arguments);
    }
}
