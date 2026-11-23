<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardHarianController;
use App\Support\DashboardHarianSnapshotService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ReflectionMethod;
use Tests\TestCase;

class DashboardHarianLdrRkaFormattingTest extends TestCase
{
    public function test_ldr_rka_comparison_treats_lower_than_rka_as_good(): void
    {
        $controller = new DashboardHarianController(new DashboardHarianSnapshotService());
        $method = new ReflectionMethod(DashboardHarianController::class, 'dashboardHarianRkaComparison');
        $method->setAccessible(true);

        $comparison = $method->invoke($controller, [
            'key' => 'ldr_non_commercial',
            'values' => [
                'current' => 109.0,
                'rka' => 112.0,
                'rka_dec' => 109.0,
            ],
        ]);

        $this->assertSame(-3.0, $comparison['rka']['delta']);
        $this->assertEqualsWithDelta(102.752293578, $comparison['rka']['achievement'], 0.000001);
        $this->assertSame(0.0, $comparison['rka_dec']['delta']);
        $this->assertSame(100.0, $comparison['rka_dec']['achievement']);
    }

    public function test_ldr_export_uses_lower_is_better_conditional_rules(): void
    {
        $controller = new DashboardHarianController(new DashboardHarianSnapshotService());
        $method = new ReflectionMethod(DashboardHarianController::class, 'fillDashboardHarianSheet');
        $method->setAccessible(true);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $method->invoke($controller, $sheet, [
            'summary' => [
                'kanca_label' => 'Area 6',
                'unit_label' => 'Semua Unit Kerja',
                'source' => 'test',
            ],
            'selected_period' => '2026-06-18',
            'selected_period_label' => '18 Jun 26',
            'selected_rka_period' => '2026-06-01',
            'comparison_periods' => [
                'yoy' => ['period' => '2025-06-18'],
                'ytd' => ['period' => '2025-12-31'],
                'm2' => ['period' => '2026-04-30'],
                'mtm' => ['period' => '2026-05-31'],
                'mtd' => ['period' => '2026-06-01'],
                'rka' => ['period' => '2026-06-01'],
                'rka_dec' => ['period' => '2026-12-01'],
            ],
            'rows' => [
                [
                    'key' => 'ldr_non_commercial',
                    'label' => '6. LDR Non Commercial',
                    'type' => 'percent',
                    'values' => [
                        'yoy' => 0,
                        'ytd' => 0,
                        'm2' => 0,
                        'mtm' => 0,
                        'mtd' => 0,
                        'current' => 109.0,
                        'rka' => 112.0,
                        'rka_dec' => 109.0,
                    ],
                    'deltas' => [
                        'yoy' => 0,
                        'ytd' => 0,
                        'mtm' => 0,
                        'mtd' => 0,
                        'dtd' => 0,
                    ],
                ],
            ],
        ]);

        $rkaDeltaRules = $sheet->getStyle('N8')->getConditionalStyles();
        $rkaAchievementRules = $sheet->getStyle('O8')->getConditionalStyles();
        $rkaDecAchievementRules = $sheet->getStyle('R8')->getConditionalStyles();

        $this->assertConditionalRule($rkaDeltaRules[0], Conditional::OPERATOR_GREATERTHAN, '0');
        $this->assertConditionalRule($rkaDeltaRules[1], Conditional::OPERATOR_EQUAL, '0');
        $this->assertConditionalRule($rkaDeltaRules[2], Conditional::OPERATOR_LESSTHAN, '0');
        $this->assertConditionalRule($rkaAchievementRules[0], Conditional::OPERATOR_LESSTHAN, '100');
        $this->assertConditionalRule($rkaAchievementRules[1], Conditional::OPERATOR_EQUAL, '100');
        $this->assertConditionalRule($rkaAchievementRules[2], Conditional::OPERATOR_GREATERTHAN, '100');
        $this->assertConditionalRule($rkaDecAchievementRules[1], Conditional::OPERATOR_EQUAL, '100');
        $this->assertSame('KETERANGAN', $sheet->getCell('A6')->getValue());
        $this->assertSame('POSISI', $sheet->getCell('B6')->getValue());
        $this->assertSame('DELTA (SELISIH DIBANDING POSISI TERPILIH)', $sheet->getCell('H6')->getValue());
        $this->assertSame('RKA TERPILIH', $sheet->getCell('M6')->getValue());
        $this->assertSame('RKA DES TAHUN BERJALAN', $sheet->getCell('P6')->getValue());
        $this->assertSame('18 Jun 26 (Posisi)', $sheet->getCell('G7')->getValue());
        $this->assertSame('Penc RKA (%)', $sheet->getCell('O7')->getValue());
        $this->assertSame('#,##0.00"%"', $sheet->getStyle('O8')->getNumberFormat()->getFormatCode());
        $this->assertContains('A6:A7', $sheet->getMergeCells());
        $this->assertContains('B6:G6', $sheet->getMergeCells());

        $spreadsheet->disconnectWorksheets();
    }

    public function test_export_excel_highlights_metric_blocks_and_segments(): void
    {
        $controller = new DashboardHarianController(new DashboardHarianSnapshotService());
        $method = new ReflectionMethod(DashboardHarianController::class, 'fillDashboardHarianSheet');
        $method->setAccessible(true);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $baseRow = [
            'key' => 'simpanan_total',
            'type' => 'currency',
            'values' => [
                'yoy' => 0,
                'ytd' => 0,
                'm2' => 0,
                'mtm' => 0,
                'mtd' => 0,
                'current' => 100_000_000,
                'rka' => 100_000_000,
                'rka_dec' => 100_000_000,
            ],
            'deltas' => [
                'yoy' => 0,
                'ytd' => 0,
                'mtm' => 0,
                'mtd' => 0,
                'dtd' => 0,
            ],
        ];

        $method->invoke($controller, $sheet, [
            'summary' => [
                'kanca_label' => 'Area 6',
                'unit_label' => 'Semua Unit Kerja',
                'source' => 'test',
            ],
            'selected_period' => '2026-06-18',
            'selected_period_label' => '18 Jun 26',
            'selected_rka_period' => '2026-06-01',
            'comparison_periods' => [
                'yoy' => ['period' => '2025-06-18'],
                'ytd' => ['period' => '2025-12-31'],
                'm2' => ['period' => '2026-04-30'],
                'mtm' => ['period' => '2026-05-31'],
                'mtd' => ['period' => '2026-06-01'],
                'rka' => ['period' => '2026-06-01'],
                'rka_dec' => ['period' => '2026-12-01'],
            ],
            'rows' => [
                array_merge($baseRow, ['label' => '1. Simpanan']),
                array_merge($baseRow, ['label' => 'A. RITEL', 'accent' => 'section', 'depth' => 1]),
                array_merge($baseRow, ['label' => 'Total OS Non Commercial', 'accent' => 'section', 'depth' => 1]),
                array_merge($baseRow, ['label' => 'Kecil Non Cashcoll', 'accent' => 'muted', 'depth' => 3]),
                array_merge($baseRow, ['label' => 'KPR', 'accent' => 'default', 'depth' => 2]),
            ],
        ]);

        $this->assertSame('BRI | DASHBOARD KERAGAAN HARIAN', $sheet->getCell('A1')->getValue());
        $this->assertSame('FF00529C', $sheet->getStyle('A2')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FF0070C0', $sheet->getStyle('D2')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FF0F4C97', $sheet->getStyle('A8')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FFDDEBFF', $sheet->getStyle('A9')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FFDDEBFF', $sheet->getStyle('A10')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FFF1F5F9', $sheet->getStyle('A11')->getFill()->getStartColor()->getARGB());
        $this->assertSame(2, $sheet->getStyle('A12')->getAlignment()->getIndent());
        $this->assertTrue($sheet->getStyle('A8')->getFont()->getBold());
        $this->assertTrue($sheet->getStyle('A9')->getFont()->getBold());
        $defaultDeltaRules = $sheet->getStyle('H12')->getConditionalStyles();
        $this->assertCount(3, $defaultDeltaRules);
        $this->assertConditionalRule($defaultDeltaRules[1], Conditional::OPERATOR_EQUAL, '0');

        $temporaryPath = tempnam(sys_get_temp_dir(), 'dashboard-harian-');
        $this->assertNotFalse($temporaryPath);
        try {
            (new Xlsx($spreadsheet))->save($temporaryPath);
            $reloaded = IOFactory::load($temporaryPath);
            $reloadedSheet = $reloaded->getActiveSheet();

            $this->assertSame('BRI | DASHBOARD KERAGAAN HARIAN', $reloadedSheet->getCell('A1')->getValue());
            $this->assertSame('FFDDEBFF', $reloadedSheet->getStyle('A10')->getFill()->getStartColor()->getARGB());
            $this->assertCount(3, $reloadedSheet->getStyle('H12')->getConditionalStyles());

            $reloaded->disconnectWorksheets();
        } finally {
            @unlink($temporaryPath);
        }

        $spreadsheet->disconnectWorksheets();
    }

    public function test_custom_mtm_override_stays_hidden_until_easter_egg_toggle(): void
    {
        $view = file_get_contents(resource_path('views/report/dashboard-harian.blade.php'));

        $this->assertStringContainsString('let mtmOverrideVisible = false;', $view);
        $this->assertStringContainsString('node.addEventListener(\'dblclick\'', $view);
        $this->assertStringContainsString('showMtmOverridePanel(event);', $view);
        $this->assertStringContainsString('if (mtmOverrideVisible) {', $view);
        $this->assertStringContainsString("params.set('mtm_period', state.mtm_period);", $view);
        $this->assertStringContainsString('return value !== defaultText || (normalizedText && value === normalizedText);', $view);
        $this->assertStringNotContainsString('let mtmOverrideVisible = Boolean(initialSelected.mtm_period);', $view);
        $this->assertStringNotContainsString('if (normalized || mtmOverrideVisible)', $view);
    }

    public function test_index_ignores_custom_mtm_query_after_refresh(): void
    {
        $service = new class extends DashboardHarianSnapshotService {
            public array $buildArgs = [];

            public function resolveEffectivePeriod(?string $requestedPeriod): ?string
            {
                return match ($requestedPeriod) {
                    '2026-06-20' => '2026-06-20',
                    '2026-05-15' => '2026-05-15',
                    default => $requestedPeriod,
                };
            }

            public function resolveEffectiveRkaPeriod(?string $requestedMonth, ?string $fallbackPeriod = null): ?string
            {
                return '2026-06-01';
            }

            public function fetchFilterOptions(?string $period = null, array|string|null $selectedKanca = null, array|string|null $selectedUnit = null): array
            {
                return [
                    'kanca' => [['value' => 'all', 'label' => 'AREA 6']],
                    'unit_kerja' => [['value' => 'all', 'label' => 'Semua Unit Kerja']],
                    'posisi_terakhir' => [
                        ['value' => '2026-06-20', 'label' => '20 Jun 26'],
                        ['value' => '2026-05-15', 'label' => '15 Mei 26'],
                    ],
                    'posisi_rka' => [['value' => '2026-06', 'label' => 'Jun 2026']],
                ];
            }

            public function buildDashboardPayload(?string $selectedPeriod, ?string $rkaPeriod = null, array|string|null $kancaKey = null, array|string|null $unitKey = null, ?string $mtmPeriod = null): array
            {
                $this->buildArgs = [$selectedPeriod, $rkaPeriod, $kancaKey, $unitKey, $mtmPeriod];

                return [
                    'summary' => [
                        'scope_label' => 'Area 6',
                        'kanca_label' => 'Area 6',
                        'unit_label' => 'Semua Unit Kerja',
                        'source' => 'test',
                    ],
                    'selected_period' => $selectedPeriod,
                    'selected_period_label' => '20 Jun 26',
                    'selected_rka_period' => $rkaPeriod,
                    'comparison_periods' => [
                        'mtm' => ['period' => '2026-05-31', 'label' => '31 Mei 26'],
                    ],
                    'rows' => [],
                ];
            }
        };
        $controller = new DashboardHarianController($service);
        $request = Request::create('/dashboard-harian', 'GET', [
            'posisi_terakhir' => '2026-06-20',
            'mtm_period' => '2026-05-15',
        ]);

        $view = $controller->index($request);
        $page = $view->getData()['dashboardPage'];

        $this->assertNull($page['selected']['mtm_period']);
        $this->assertSame([
            '2026-06-20',
            '2026-06-01',
            ['KC Madiun', 'KC Magetan', 'KC Ponorogo', 'KC Ngawi'],
            null,
            null,
        ], $service->buildArgs);
    }

    public function test_export_excel_uses_custom_mtm_override_period(): void
    {
        $service = new class extends DashboardHarianSnapshotService {
            public array $buildArgs = [];

            public function resolveEffectivePeriod(?string $requestedPeriod): ?string
            {
                return match ($requestedPeriod) {
                    '2026-06-20' => '2026-06-20',
                    '2026-05-15' => '2026-05-15',
                    default => $requestedPeriod,
                };
            }

            public function resolveEffectiveRkaPeriod(?string $requestedMonth, ?string $fallbackPeriod = null): ?string
            {
                return '2026-06-01';
            }

            public function buildDashboardPayload(?string $selectedPeriod, ?string $rkaPeriod = null, array|string|null $kancaKey = null, array|string|null $unitKey = null, ?string $mtmPeriod = null): array
            {
                $this->buildArgs = [$selectedPeriod, $rkaPeriod, $kancaKey, $unitKey, $mtmPeriod];

                return [
                    'summary' => [
                        'scope_label' => 'Area 6',
                        'kanca_label' => 'Area 6',
                        'unit_label' => 'Semua Unit Kerja',
                        'source' => 'test',
                    ],
                    'selected_period' => $selectedPeriod,
                    'selected_period_label' => '20 Jun 26',
                    'selected_rka_period' => $rkaPeriod,
                    'comparison_periods' => [],
                    'rows' => [],
                ];
            }
        };
        $controller = new DashboardHarianController($service);
        $request = Request::create('/dashboard-harian/export', 'GET', [
            'posisi_terakhir' => '2026-06-20',
            'mtm_period' => '2026-05-15',
        ]);

        $response = $controller->exportExcel($request);

        $this->assertSame([
            '2026-06-20',
            '2026-06-01',
            ['KC Madiun', 'KC Magetan', 'KC Ponorogo', 'KC Ngawi'],
            null,
            '2026-05-15',
        ], $service->buildArgs);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_fill_dashboard_harian_sheet_hides_specified_columns_and_rows(): void
    {
        $controller = new DashboardHarianController(new DashboardHarianSnapshotService());
        $method = new ReflectionMethod(DashboardHarianController::class, 'fillDashboardHarianSheet');
        $method->setAccessible(true);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $method->invoke($controller, $sheet, [
            'summary' => [
                'kanca_label' => 'Area 6',
                'unit_label' => 'Semua Unit Kerja',
                'source' => 'test',
            ],
            'selected_period' => '2026-06-18',
            'selected_period_label' => '18 Jun 26',
            'selected_rka_period' => '2026-06-01',
            'comparison_periods' => [
                'yoy' => ['period' => '2025-06-18'],
                'ytd' => ['period' => '2025-12-31'],
                'm2' => ['period' => '2026-04-30'],
                'mtm' => ['period' => '2026-05-31'],
                'mtd' => ['period' => '2026-06-01'],
                'h1' => ['period' => '2026-06-17'],
                'rka' => ['period' => '2026-06-01'],
                'rka_dec' => ['period' => '2026-12-01'],
            ],
            'rows' => [
                [
                    'key' => 'simpanan_total',
                    'label' => '1. Total Simpanan',
                    'type' => 'currency',
                    'values' => ['current' => 100_000_000],
                    'deltas' => [],
                ],
                [
                    'key' => 'simpanan_ritel',
                    'label' => 'A. Simpanan Ritel',
                    'type' => 'currency',
                    'values' => ['current' => 50_000_000],
                    'deltas' => [],
                ],
                [
                    'key' => 'simpanan_mikro',
                    'label' => 'B. Simpanan Mikro',
                    'type' => 'currency',
                    'values' => ['current' => 50_000_000],
                    'deltas' => [],
                ],
            ],
        ], ['pos_mtm', 'delta_mtm'], ['simpanan_ritel']);

        // Data rows start at row 8
        // Row 8: simpanan_total (visible)
        // Row 9: simpanan_ritel (hidden)
        // Row 10: simpanan_mikro (visible)
        $this->assertTrue($sheet->getRowDimension(8)->getVisible());
        $this->assertFalse($sheet->getRowDimension(9)->getVisible());
        $this->assertTrue($sheet->getRowDimension(10)->getVisible());

        // Columns:
        // A: keterangan (visible)
        // B: pos_yoy (visible)
        // E: pos_mtm (hidden)
        // K: delta_mtm (hidden - index 11 because hasH1 is true)
        // H: pos_current (visible)
        $this->assertTrue($sheet->getColumnDimension('A')->getVisible());
        $this->assertTrue($sheet->getColumnDimension('B')->getVisible());
        $this->assertFalse($sheet->getColumnDimension('E')->getVisible());
        $this->assertFalse($sheet->getColumnDimension('K')->getVisible());
        $this->assertTrue($sheet->getColumnDimension('H')->getVisible());

        $spreadsheet->disconnectWorksheets();
    }

    public function test_sml_and_npl_rka_comparison_uses_current_minus_target_delta(): void
    {
        $controller = new DashboardHarianController(new DashboardHarianSnapshotService());
        $method = new ReflectionMethod(DashboardHarianController::class, 'dashboardHarianRkaComparison');
        $method->setAccessible(true);

        $betterSml = $method->invoke($controller, [
            'key' => 'total_sml_pct_non_commercial',
            'values' => [
                'current' => 4.25,
                'rka' => 5.92,
                'rka_dec' => 5.84,
            ],
        ]);
        $this->assertEqualsWithDelta(-1.67, $betterSml['rka']['delta'], 0.000001);
        $this->assertGreaterThan(100.0, $betterSml['rka']['achievement']);
        $this->assertEqualsWithDelta(-1.59, $betterSml['rka_dec']['delta'], 0.000001);
        $this->assertGreaterThan(100.0, $betterSml['rka_dec']['achievement']);

        $worseNpl = $method->invoke($controller, [
            'key' => 'kupedes_npl',
            'values' => [
                'current' => 716_593_030_000.0,
                'rka' => 557_917_300_000.0,
                'rka_dec' => 551_320_700_000.0,
            ],
        ]);
        $this->assertGreaterThan(0.0, $worseNpl['rka']['delta']);
        $this->assertLessThan(100.0, $worseNpl['rka']['achievement']);
        $this->assertGreaterThan(0.0, $worseNpl['rka_dec']['delta']);
        $this->assertLessThan(100.0, $worseNpl['rka_dec']['achievement']);

        $zeroCashcoll = $method->invoke($controller, [
            'key' => 'cashcoll_sml',
            'values' => [
                'current' => 0.0,
                'rka' => 516_810_000.0,
                'rka_dec' => 516_810_000.0,
            ],
        ]);
        $this->assertSame(-516_810_000.0, $zeroCashcoll['rka']['delta']);
        $this->assertSame(100.0, $zeroCashcoll['rka']['achievement']);
    }

    public function test_export_excel_prepends_tabungan_deposito_giro_and_aligns_sml_npl_conditional_formatting(): void
    {
        $controller = new DashboardHarianController(new DashboardHarianSnapshotService());
        $method = new ReflectionMethod(DashboardHarianController::class, 'fillDashboardHarianSheet');
        $method->setAccessible(true);

        $makeRow = static function (
            string $key,
            string $label,
            string $type,
            float $current,
            float $rka,
            float $rkaDec,
            float $deltaYoy = 0.0,
            float $deltaYtd = 0.0,
            float $deltaMtm = 0.0,
            float $deltaMtd = 0.0,
            float $deltaDtd = 0.0,
            int $depth = 2,
            string $accent = 'default'
        ): array {
            return [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'depth' => $depth,
                'accent' => $accent,
                'values' => [
                    'yoy' => $current - $deltaYoy,
                    'ytd' => $current - $deltaYtd,
                    'm2' => $current,
                    'mtm' => $current - $deltaMtm,
                    'mtd' => $current - $deltaMtd,
                    'h1' => $current - $deltaDtd,
                    'current' => $current,
                    'rka' => $rka,
                    'rka_dec' => $rkaDec,
                ],
                'deltas' => [
                    'yoy' => $deltaYoy,
                    'ytd' => $deltaYtd,
                    'mtm' => $deltaMtm,
                    'mtd' => $deltaMtd,
                    'dtd' => $deltaDtd,
                ],
            ];
        };

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $method->invoke($controller, $sheet, [
            'summary' => [
                'kanca_label' => 'Area 6',
                'unit_label' => 'Semua Unit Kerja',
                'source' => 'test',
            ],
            'selected_period' => '2026-11-21',
            'selected_period_label' => '21 Nov 26',
            'selected_rka_period' => '2026-11-01',
            'comparison_periods' => [
                'yoy' => ['period' => '2025-11-21'],
                'ytd' => ['period' => '2025-12-31'],
                'm2' => ['period' => '2026-09-30'],
                'mtm' => ['period' => '2026-10-21'],
                'mtd' => ['period' => '2026-10-31'],
                'h1' => ['period' => '2026-11-20'],
                'rka' => ['period' => '2026-11-01'],
                'rka_dec' => ['period' => '2026-12-01'],
            ],
            'rows' => [
                $makeRow('total_simpanan', '1. Simpanan', 'currency', 1_100_000_000, 1_000_000_000, 1_050_000_000, 100_000_000, 80_000_000, 30_000_000, 20_000_000, 10_000_000, 0, 'strong'),
                $makeRow('simpanan_ritel', 'A. Ritel', 'currency', 600_000_000, 550_000_000, 580_000_000, 60_000_000, 45_000_000, 15_000_000, 10_000_000, 5_000_000, 1, 'section'),
                $makeRow('giro_ritel', 'Giro', 'currency', 100_000_000, 90_000_000, 95_000_000, 10_000_000, 5_000_000, 2_000_000, 1_000_000, 500_000),
                $makeRow('tabungan_ritel', 'Tabungan', 'currency', 300_000_000, 280_000_000, 290_000_000, 30_000_000, 25_000_000, 8_000_000, 6_000_000, 3_000_000),
                $makeRow('deposito_ritel', 'Deposito', 'currency', 200_000_000, 180_000_000, 195_000_000, 20_000_000, 15_000_000, 5_000_000, 3_000_000, 1_500_000),
                $makeRow('simpanan_mikro', 'B. Mikro', 'currency', 350_000_000, 320_000_000, 335_000_000, 30_000_000, 25_000_000, 10_000_000, 7_000_000, 4_000_000, 1, 'section'),
                $makeRow('giro_mikro', 'Giro', 'currency', 50_000_000, 40_000_000, 45_000_000, 5_000_000, 4_000_000, 1_000_000, 1_000_000, 500_000),
                $makeRow('tabungan_mikro', 'Tabungan', 'currency', 200_000_000, 190_000_000, 195_000_000, 15_000_000, 12_000_000, 6_000_000, 4_000_000, 2_500_000),
                $makeRow('deposito_mikro', 'Deposito', 'currency', 100_000_000, 90_000_000, 95_000_000, 10_000_000, 9_000_000, 3_000_000, 2_000_000, 1_000_000),
                $makeRow('simpanan_wholesale', 'C. Wholesale', 'currency', 150_000_000, 130_000_000, 135_000_000, 10_000_000, 10_000_000, 5_000_000, 3_000_000, 1_000_000, 1, 'section'),
                $makeRow('giro_wholesale', 'Giro', 'currency', 50_000_000, 45_000_000, 45_000_000, 4_000_000, 3_000_000, 2_000_000, 1_000_000, 500_000),
                $makeRow('tabungan_wholesale', 'Tabungan', 'currency', 40_000_000, 35_000_000, 38_000_000, 3_000_000, 4_000_000, 1_500_000, 1_000_000, 250_000),
                $makeRow('deposito_wholesale', 'Deposito', 'currency', 60_000_000, 50_000_000, 52_000_000, 3_000_000, 3_000_000, 1_500_000, 1_000_000, 250_000),
                $makeRow('total_os', '2. OS Total', 'currency', 900_000_000, 880_000_000, 890_000_000, 0, 0, 0, 0, 0, 0, 'strong'),
                $makeRow('total_sml_pct_non_commercial', '3. Total SML (%) Non Commercial', 'percent', 4.50, 5.92, 5.84, -0.50, -0.30, -0.20, -0.10, -0.05, 0, 'strong'),
                $makeRow('cashcoll_sml', 'Cashcoll', 'currency', 0.0, 516_810_000, 516_810_000, -10_000_000, -5_000_000, 0, 0, 0, 3, 'muted'),
                $makeRow('kpr_sml', 'KPR', 'currency', 120_000_000, 80_000_000, 85_000_000, 15_000_000, 10_000_000, 5_000_000, 2_000_000, 1_000_000),
                $makeRow('total_npl_pct_non_commercial', '4. Total NPL (%) Non Commercial', 'percent', 4.56, 3.53, 3.50, 1.45, 1.25, -0.04, 0.43, 0.10, 0, 'strong'),
                $makeRow('kupedes_npl', 'Kupedes', 'currency', 150_000_000, 200_000_000, 190_000_000, -25_000_000, -15_000_000, -5_000_000, -2_000_000, -1_000_000),
            ],
        ]);

        // 3 summary rows prepended at rows 8, 9, 10 before 1. Simpanan at row 11
        $this->assertSame('Tabungan', $sheet->getCell('A8')->getValue());
        $this->assertSame('Deposito', $sheet->getCell('A9')->getValue());
        $this->assertSame('Giro', $sheet->getCell('A10')->getValue());
        $this->assertSame('1. Simpanan', $sheet->getCell('A11')->getValue());

        // Tabungan: current 540, RKA 505, Gap +35, Delta YoY +48
        $this->assertEqualsWithDelta(540.0, $sheet->getCell('H8')->getValue(), 0.001);
        $this->assertEqualsWithDelta(48.0, $sheet->getCell('I8')->getValue(), 0.001);
        $this->assertEqualsWithDelta(505.0, $sheet->getCell('N8')->getValue(), 0.001);
        $this->assertEqualsWithDelta(35.0, $sheet->getCell('O8')->getValue(), 0.001);
        $this->assertEqualsWithDelta((540.0 / 505.0) * 100, $sheet->getCell('P8')->getValue(), 0.001);
        $this->assertEqualsWithDelta(523.0, $sheet->getCell('Q8')->getValue(), 0.001);
        $this->assertEqualsWithDelta(17.0, $sheet->getCell('R8')->getValue(), 0.001);

        // Deposito: current 360, RKA 320, Gap +40
        $this->assertEqualsWithDelta(360.0, $sheet->getCell('H9')->getValue(), 0.001);
        $this->assertEqualsWithDelta(320.0, $sheet->getCell('N9')->getValue(), 0.001);
        $this->assertEqualsWithDelta(40.0, $sheet->getCell('O9')->getValue(), 0.001);

        // Giro: current 200, RKA 175, Gap +25
        $this->assertEqualsWithDelta(200.0, $sheet->getCell('H10')->getValue(), 0.001);
        $this->assertEqualsWithDelta(175.0, $sheet->getCell('N10')->getValue(), 0.001);
        $this->assertEqualsWithDelta(25.0, $sheet->getCell('O10')->getValue(), 0.001);

        // Tabungan + Deposito + Giro == 1. Simpanan (row 11)
        $this->assertEqualsWithDelta(
            $sheet->getCell('H11')->getValue(),
            $sheet->getCell('H8')->getValue() + $sheet->getCell('H9')->getValue() + $sheet->getCell('H10')->getValue(),
            0.001
        );

        // Savings summary rows keep normal CF (< 0 red, > 0 green)
        $tabunganGapRules = $sheet->getStyle('O8')->getConditionalStyles();
        $this->assertCount(2, $tabunganGapRules);
        $this->assertConditionalRuleColor($tabunganGapRules[0], Conditional::OPERATOR_LESSTHAN, '0', 'FFFEE2E2');
        $this->assertConditionalRuleColor($tabunganGapRules[1], Conditional::OPERATOR_GREATERTHAN, '0', 'FFDCFCE7');

        // Shifted rows: 3. Total SML (%) at row 25, Cashcoll at row 26, KPR at row 27, 4. Total NPL (%) at row 28, Kupedes NPL at row 29
        $this->assertSame('3. Total SML (%) Non Commercial', $sheet->getCell('A25')->getValue());
        $this->assertSame('Cashcoll', $sheet->getCell('A26')->getValue());
        $this->assertSame('KPR', $sheet->getCell('A27')->getValue());
        $this->assertSame('4. Total NPL (%) Non Commercial', $sheet->getCell('A28')->getValue());
        $this->assertSame('Kupedes', $sheet->getCell('A29')->getValue());

        // SML good (4.50 < 5.92) -> Gap RKA negative (-1.42) -> GREEN (< 0)
        $this->assertEqualsWithDelta(-1.42, $sheet->getCell('O25')->getValue(), 0.001);
        // Cashcoll SML (0 < 516.81) -> Gap RKA negative (-516.81), Penc RKA 100%
        $this->assertEqualsWithDelta(-516.81, $sheet->getCell('O26')->getValue(), 0.001);
        $this->assertEqualsWithDelta(100.0, $sheet->getCell('P26')->getValue(), 0.001);
        // KPR SML bad (120 > 80) -> Gap RKA positive (+40) -> RED (> 0)
        $this->assertEqualsWithDelta(40.0, $sheet->getCell('O27')->getValue(), 0.001);
        // NPL bad (4.56 > 3.53) -> Gap RKA positive (+1.03) -> RED (> 0)
        $this->assertEqualsWithDelta(1.03, $sheet->getCell('O28')->getValue(), 0.001);
        // Kupedes NPL good (150 < 200) -> Gap RKA negative (-50) -> GREEN (< 0)
        $this->assertEqualsWithDelta(-50.0, $sheet->getCell('O29')->getValue(), 0.001);

        foreach ([25, 26, 27, 28, 29] as $qualityRow) {
            foreach (['I', 'O', 'R'] as $col) {
                $rules = $sheet->getStyle("{$col}{$qualityRow}")->getConditionalStyles();
                $this->assertCount(3, $rules, "Expected 3 rules at {$col}{$qualityRow}");
                $this->assertConditionalRuleColor($rules[0], Conditional::OPERATOR_GREATERTHAN, '0', 'FFFEE2E2');
                $this->assertConditionalRuleColor($rules[1], Conditional::OPERATOR_EQUAL, '0', 'FFFEF3C7');
                $this->assertConditionalRuleColor($rules[2], Conditional::OPERATOR_LESSTHAN, '0', 'FFDCFCE7');
                $this->assertTrue($rules[0]->getStopIfTrue());
                $this->assertTrue($rules[1]->getStopIfTrue());
                $this->assertTrue($rules[2]->getStopIfTrue());
            }

            foreach (['P', 'S'] as $achCol) {
                $achRules = $sheet->getStyle("{$achCol}{$qualityRow}")->getConditionalStyles();
                $this->assertCount(2, $achRules, "Expected 2 achievement rules at {$achCol}{$qualityRow}");
                $this->assertConditionalRuleColor($achRules[0], Conditional::OPERATOR_LESSTHAN, '100', 'FFFEE2E2');
                $this->assertConditionalRuleColor($achRules[1], Conditional::OPERATOR_GREATERTHANOREQUAL, '100', 'FFDCFCE7');
            }
        }

        // Verify round-trip XLSX preserves aligned conditional formatting and values
        $temporaryPath = tempnam(sys_get_temp_dir(), 'dashboard-harian-sml-npl-');
        $this->assertNotFalse($temporaryPath);
        try {
            (new Xlsx($spreadsheet))->save($temporaryPath);
            $reloaded = IOFactory::load($temporaryPath);
            $reloadedSheet = $reloaded->getActiveSheet();

            $this->assertSame('Tabungan', $reloadedSheet->getCell('A8')->getValue());
            $this->assertSame('1. Simpanan', $reloadedSheet->getCell('A11')->getValue());
            $this->assertSame('3. Total SML (%) Non Commercial', $reloadedSheet->getCell('A25')->getValue());
            $this->assertEqualsWithDelta(-1.42, $reloadedSheet->getCell('O25')->getValue(), 0.001);
            $this->assertEqualsWithDelta(1.03, $reloadedSheet->getCell('O28')->getValue(), 0.001);

            $reloadedSmlRules = $reloadedSheet->getStyle('O25')->getConditionalStyles();
            $this->assertCount(3, $reloadedSmlRules);
            $this->assertConditionalRule($reloadedSmlRules[0], Conditional::OPERATOR_GREATERTHAN, '0');
            $this->assertConditionalRule($reloadedSmlRules[2], Conditional::OPERATOR_LESSTHAN, '0');

            $reloaded->disconnectWorksheets();
        } finally {
            @unlink($temporaryPath);
        }

        $spreadsheet->disconnectWorksheets();
    }

    public function test_export_excel_aggregates_tabungan_deposito_giro_from_office_breakdown_rows(): void
    {
        $controller = new DashboardHarianController(new DashboardHarianSnapshotService());
        $method = new ReflectionMethod(DashboardHarianController::class, 'fillDashboardHarianSheet');
        $method->setAccessible(true);

        $makeRow = static fn (string $key, string $label, float $current, float $rka, ?string $sourceMetricKey = null, array $extra = []): array => array_merge([
            'key' => $key,
            'label' => $label,
            'type' => 'currency',
            'depth' => 2,
            'accent' => 'default',
            'values' => [
                'yoy' => $current - 5_000_000,
                'ytd' => $current - 4_000_000,
                'm2' => $current,
                'mtm' => $current - 2_000_000,
                'mtd' => $current - 1_000_000,
                'current' => $current,
                'rka' => $rka,
                'rka_dec' => $rka,
            ],
            'deltas' => [
                'yoy' => 5_000_000,
                'ytd' => 4_000_000,
                'mtm' => 2_000_000,
                'mtd' => 1_000_000,
                'dtd' => 0,
            ],
        ], $sourceMetricKey !== null ? ['source_metric_key' => $sourceMetricKey] : [], $extra);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $method->invoke($controller, $sheet, [
            'summary' => [
                'kanca_label' => 'KC Madiun',
                'unit_label' => 'Semua Unit Kerja',
                'source' => 'test',
            ],
            'selected_period' => '2026-11-21',
            'selected_period_label' => '21 Nov 26',
            'selected_rka_period' => '2026-11-01',
            'comparison_periods' => [
                'yoy' => ['period' => '2025-11-21'],
                'ytd' => ['period' => '2025-12-31'],
                'm2' => ['period' => '2026-09-30'],
                'mtm' => ['period' => '2026-10-21'],
                'mtd' => ['period' => '2026-10-31'],
                'rka' => ['period' => '2026-11-01'],
                'rka_dec' => ['period' => '2026-12-01'],
            ],
            'rows' => [
                $makeRow('total_simpanan', '1. Simpanan', 850_000_000, 800_000_000, null, ['depth' => 0, 'accent' => 'strong']),
                $makeRow('simpanan_ritel', 'A. Ritel', 500_000_000, 470_000_000, null, ['depth' => 1, 'accent' => 'section']),
                $makeRow('simpanan_ritel__office__kc-madiun', 'A.1 KC Madiun', 350_000_000, 330_000_000, 'simpanan_ritel', ['office_breakdown' => true]),
                $makeRow('giro_ritel__office_detail__kc-madiun', 'Giro', 80_000_000, 75_000_000, 'giro_ritel', ['office_breakdown_child' => true]),
                $makeRow('tabungan_ritel__office_detail__kc-madiun', 'Tabungan', 170_000_000, 160_000_000, 'tabungan_ritel', ['office_breakdown_child' => true]),
                $makeRow('deposito_ritel__office_detail__kc-madiun', 'Deposito', 100_000_000, 95_000_000, 'deposito_ritel', ['office_breakdown_child' => true]),
                $makeRow('simpanan_ritel__office__kcp-caruban', 'A.2 KCP Caruban', 150_000_000, 140_000_000, 'simpanan_ritel', ['office_breakdown' => true]),
                $makeRow('giro_ritel__office_detail__kcp-caruban', 'Giro', 20_000_000, 18_000_000, 'giro_ritel', ['office_breakdown_child' => true]),
                $makeRow('tabungan_ritel__office_detail__kcp-caruban', 'Tabungan', 80_000_000, 72_000_000, 'tabungan_ritel', ['office_breakdown_child' => true]),
                $makeRow('deposito_ritel__office_detail__kcp-caruban', 'Deposito', 50_000_000, 50_000_000, 'deposito_ritel', ['office_breakdown_child' => true]),
                $makeRow('simpanan_mikro', 'B. Mikro', 250_000_000, 240_000_000, null, ['depth' => 1, 'accent' => 'section']),
                $makeRow('giro_mikro', 'Giro', 30_000_000, 28_000_000),
                $makeRow('tabungan_mikro', 'Tabungan', 160_000_000, 155_000_000),
                $makeRow('deposito_mikro', 'Deposito', 60_000_000, 57_000_000),
                $makeRow('simpanan_wholesale', 'C. Wholesale', 100_000_000, 90_000_000, null, ['depth' => 1, 'accent' => 'section']),
                $makeRow('giro_wholesale', 'Giro', 60_000_000, 55_000_000),
                $makeRow('tabungan_wholesale', 'Tabungan', 10_000_000, 10_000_000),
                $makeRow('deposito_wholesale', 'Deposito', 30_000_000, 25_000_000),
            ],
        ]);

        $this->assertSame('Tabungan', $sheet->getCell('A8')->getValue());
        $this->assertSame('Deposito', $sheet->getCell('A9')->getValue());
        $this->assertSame('Giro', $sheet->getCell('A10')->getValue());
        $this->assertSame('1. Simpanan', $sheet->getCell('A11')->getValue());

        // Tabungan = 170 + 80 + 160 + 10 = 420
        $this->assertEqualsWithDelta(420.0, $sheet->getCell('G8')->getValue(), 0.001);
        $this->assertEqualsWithDelta(397.0, $sheet->getCell('M8')->getValue(), 0.001);
        // Deposito = 100 + 50 + 60 + 30 = 240
        $this->assertEqualsWithDelta(240.0, $sheet->getCell('G9')->getValue(), 0.001);
        $this->assertEqualsWithDelta(227.0, $sheet->getCell('M9')->getValue(), 0.001);
        // Giro = 80 + 20 + 30 + 60 = 190
        $this->assertEqualsWithDelta(190.0, $sheet->getCell('G10')->getValue(), 0.001);
        $this->assertEqualsWithDelta(176.0, $sheet->getCell('M10')->getValue(), 0.001);

        $this->assertEqualsWithDelta(
            $sheet->getCell('G11')->getValue(),
            $sheet->getCell('G8')->getValue() + $sheet->getCell('G9')->getValue() + $sheet->getCell('G10')->getValue(),
            0.001
        );

        $spreadsheet->disconnectWorksheets();
    }

    private function assertConditionalRule(Conditional $rule, string $operator, string $condition): void
    {
        $this->assertSame($operator, $rule->getOperatorType());
        $this->assertSame([$condition], $rule->getConditions());
    }

    private function assertConditionalRuleColor(Conditional $rule, string $operator, string $condition, string $expectedFillArgb): void
    {
        $this->assertConditionalRule($rule, $operator, $condition);
        $this->assertSame($expectedFillArgb, $rule->getStyle()->getFill()->getStartColor()->getARGB());
    }
}
