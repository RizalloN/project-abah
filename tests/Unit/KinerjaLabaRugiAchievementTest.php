<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\AlmafactsDashboardController;
use ReflectionMethod;
use Tests\TestCase;

class KinerjaLabaRugiAchievementTest extends TestCase
{
    public function test_calculate_achievement_handles_standard_values_and_edge_cases(): void
    {
        $controller = new AlmafactsDashboardController();
        $method = new ReflectionMethod(AlmafactsDashboardController::class, 'calculateAchievement');

        // Normal cases: below target (KC Madiun)
        $madiunAchieve = $method->invoke($controller, 141021433712.0, 144599838701.0);
        $this->assertNotNull($madiunAchieve);
        $this->assertEqualsWithDelta(97.5253, $madiunAchieve, 0.001);

        // Above target (KC Magetan)
        $magetanAchieve = $method->invoke($controller, 136726535162.0, 129711738266.0);
        $this->assertNotNull($magetanAchieve);
        $this->assertEqualsWithDelta(105.4080, $magetanAchieve, 0.001);

        // Edge cases: null target, zero target, near-zero target
        $this->assertNull($method->invoke($controller, 1000.0, null));
        $this->assertNull($method->invoke($controller, 1000.0, 0.0));
        $this->assertNull($method->invoke($controller, 1000.0, 0.0000001));
    }

    public function test_summary_calculates_overall_achievement_as_total_ratio(): void
    {
        $controller = new AlmafactsDashboardController();
        $method = new ReflectionMethod(AlmafactsDashboardController::class, 'summary');

        $rows = [
            [
                'values' => ['current' => 141021433712.0, 'yoy' => 0.0, 'ytd' => 0.0, 'm2' => 0.0, 'm1' => 0.0],
                'deltas' => ['yoy' => 0.0, 'ytd' => 0.0, 'm2' => 0.0, 'm1' => 0.0],
                'rka' => [
                    'current' => 144599838701.0,
                    'current_gap' => -3578404989.0,
                    'current_achieve' => 97.5253,
                    'dec' => 223261311640.0,
                    'dec_gap' => -82239877928.0,
                    'dec_achieve' => 63.1643,
                ],
            ],
            [
                'values' => ['current' => 136726535162.0, 'yoy' => 0.0, 'ytd' => 0.0, 'm2' => 0.0, 'm1' => 0.0],
                'deltas' => ['yoy' => 0.0, 'ytd' => 0.0, 'm2' => 0.0, 'm1' => 0.0],
                'rka' => [
                    'current' => 129711738266.0,
                    'current_gap' => 7014796896.0,
                    'current_achieve' => 105.4080,
                    'dec' => 199549224296.0,
                    'dec_gap' => -62822689134.0,
                    'dec_achieve' => 68.5177,
                ],
            ],
        ];

        $summary = $method->invoke($controller, $rows);

        $expectedTotalCurrent = 141021433712.0 + 136726535162.0;
        $expectedTotalRka = 144599838701.0 + 129711738266.0;
        $expectedAchieve = ($expectedTotalCurrent / $expectedTotalRka) * 100;

        $this->assertEqualsWithDelta($expectedAchieve, $summary['rka']['current_achieve'], 0.0001);
        $this->assertEqualsWithDelta($expectedAchieve, $summary['rka_current_achieve'], 0.0001);
        $this->assertNotNull($summary['rka']['dec_achieve']);
        $this->assertNotNull($summary['rka_dec_achieve']);
    }

    public function test_kinerja_laba_rugi_view_renders_achievement_columns_and_proper_colspans(): void
    {
        $this->actingAs(new \App\Models\User(['id' => 1, 'pn' => '12345678', 'name' => 'Admin Test', 'role' => 'admin']));

        $view = view('report.almafacts.kinerja-laba-rugi', [
            'periodOptions' => ['2026-08-31'],
            'selectedPeriod' => '2026-08-31',
            'selectedPeriodLabel' => 'August 26',
            'branchOptions' => ['area6' => 'Area 6'],
            'selectedBranch' => 'area6',
            'selectedBranchLabel' => 'Area 6',
            'rkaPeriodOptions' => ['2026-08-31'],
            'selectedRkaPeriod' => '2026-08-31',
            'selectedRkaLabel' => 'August 26',
            'rkaDecLabel' => 'December 26',
            'comparisonPeriods' => [
                'yoy' => '2025-08-31',
                'ytd' => '2025-12-31',
                'm2' => '2026-06-30',
                'm1' => '2026-07-31',
                'current' => '2026-08-31',
            ],
            'comparisonLabels' => [
                'yoy' => 'August 25',
                'ytd' => 'December 25',
                'm2' => 'June 26',
                'm1' => 'July 26',
                'current' => 'August 26',
            ],
            'rows' => [
                [
                    'key' => 'KC Madiun',
                    'branch' => 'KC Madiun',
                    'unit_code' => null,
                    'unit_name' => null,
                    'unit_type' => null,
                    'values' => [
                        'yoy' => 1000000,
                        'ytd' => 2000000,
                        'm2' => 3000000,
                        'm1' => 4000000,
                        'current' => 141021433712,
                    ],
                    'deltas' => [
                        'yoy' => 10000,
                        'ytd' => 20000,
                        'm2' => 30000,
                        'm1' => 40000,
                    ],
                    'rka' => [
                        'current' => 144599838701,
                        'current_gap' => -3578404989,
                        'current_achieve' => 97.5253,
                        'dec' => 223261311640,
                        'dec_gap' => -82239877928,
                        'dec_achieve' => 63.1643,
                    ],
                ],
            ],
            'showUnitColumn' => false,
            'summary' => [
                'row_count' => 1,
                'current' => 141021433712,
                'rka_current' => 144599838701,
                'rka_current_gap' => -3578404989,
                'rka_current_achieve' => 97.5253,
                'rka_dec' => 223261311640,
                'rka_dec_gap' => -82239877928,
                'rka_dec_achieve' => 63.1643,
                'values' => [
                    'yoy' => 1000000,
                    'ytd' => 2000000,
                    'm2' => 3000000,
                    'm1' => 4000000,
                    'current' => 141021433712,
                ],
                'deltas' => [
                    'yoy' => 10000,
                    'ytd' => 20000,
                    'm2' => 30000,
                    'm1' => 40000,
                ],
                'rka' => [
                    'current' => 144599838701,
                    'current_gap' => -3578404989,
                    'current_achieve' => 97.5253,
                    'dec' => 223261311640,
                    'dec_gap' => -82239877928,
                    'dec_achieve' => 63.1643,
                ],
            ],
        ])->render();

        // Check group header has colspan="6"
        $this->assertStringContainsString('<th colspan="6">RKA</th>', $view);

        // Check column headers
        $this->assertStringContainsString('<th>% Penc August 26</th>', $view);
        $this->assertStringContainsString('<th>% Penc December 26</th>', $view);

        // Check formatted percent values in row
        $this->assertStringContainsString('97,53%', $view);
        $this->assertStringContainsString('63,16%', $view);

        // Test empty state colspans
        $emptyViewNoUnit = view('report.almafacts.kinerja-laba-rugi', [
            'periodOptions' => ['2026-08-31'],
            'selectedPeriod' => '2026-08-31',
            'selectedPeriodLabel' => 'August 26',
            'branchOptions' => ['area6' => 'Area 6'],
            'selectedBranch' => 'area6',
            'selectedBranchLabel' => 'Area 6',
            'rkaPeriodOptions' => ['2026-08-31'],
            'selectedRkaPeriod' => '2026-08-31',
            'selectedRkaLabel' => 'August 26',
            'rkaDecLabel' => 'December 26',
            'comparisonPeriods' => [],
            'comparisonLabels' => [
                'yoy' => 'August 25',
                'ytd' => 'December 25',
                'm2' => 'June 26',
                'm1' => 'July 26',
                'current' => 'August 26',
            ],
            'rows' => [],
            'showUnitColumn' => false,
            'summary' => ['row_count' => 0],
        ])->render();
        $this->assertStringContainsString('colspan="17"', $emptyViewNoUnit);

        $emptyViewWithUnit = view('report.almafacts.kinerja-laba-rugi', [
            'periodOptions' => ['2026-08-31'],
            'selectedPeriod' => '2026-08-31',
            'selectedPeriodLabel' => 'August 26',
            'branchOptions' => ['area6' => 'Area 6'],
            'selectedBranch' => 'KC Madiun',
            'selectedBranchLabel' => 'KC Madiun',
            'rkaPeriodOptions' => ['2026-08-31'],
            'selectedRkaPeriod' => '2026-08-31',
            'selectedRkaLabel' => 'August 26',
            'rkaDecLabel' => 'December 26',
            'comparisonPeriods' => [],
            'comparisonLabels' => [
                'yoy' => 'August 25',
                'ytd' => 'December 25',
                'm2' => 'June 26',
                'm1' => 'July 26',
                'current' => 'August 26',
            ],
            'rows' => [],
            'showUnitColumn' => true,
            'summary' => ['row_count' => 0],
        ])->render();
        $this->assertStringContainsString('colspan="18"', $emptyViewWithUnit);
    }
}
