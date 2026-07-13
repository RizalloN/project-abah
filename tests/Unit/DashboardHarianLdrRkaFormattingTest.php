<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardHarianController;
use App\Support\DashboardHarianSnapshotService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
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
                array_merge($baseRow, ['label' => 'A. RITEL']),
            ],
        ]);

        $this->assertSame('FF0F4C97', $sheet->getStyle('A8')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FFDDEBFF', $sheet->getStyle('A9')->getFill()->getStartColor()->getARGB());
        $this->assertTrue($sheet->getStyle('A8')->getFont()->getBold());
        $this->assertTrue($sheet->getStyle('A9')->getFont()->getBold());

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

    private function assertConditionalRule(Conditional $rule, string $operator, string $condition): void
    {
        $this->assertSame($operator, $rule->getOperatorType());
        $this->assertSame([$condition], $rule->getConditions());
    }
}
