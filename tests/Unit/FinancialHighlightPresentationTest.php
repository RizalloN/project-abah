<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\AlmafactsDashboardController;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class FinancialHighlightPresentationTest extends TestCase
{
    public function test_financial_ratio_formula_uses_aggregated_nominal_values(): void
    {
        $current = [
            'interest_income' => 100.0,
            'assets_spread' => 60.0,
            'interest_expense' => -10.0,
            'ftp_income' => 30.0,
            'liabilities_spread' => 20.0,
            'contribution_margin' => 80.0,
            'fee_income' => 20.0,
            'overhead_cost' => -9.0,
            'ckpn_expense' => -8.0,
            'profit_before_tax' => 10.0,
            'profit_after_tax' => 8.0,
            'operating_income' => 100.0,
            'operating_expense' => -70.0,
            'average_loans' => 200.0,
            'average_savings' => 100.0,
            'loans' => 220.0,
            'savings' => 200.0,
            'giro' => 80.0,
            'tabungan' => 100.0,
        ];
        $method = new ReflectionMethod(AlmafactsDashboardController::class, 'calculateFinancialRatios');
        $ratios = $method->invoke(
            new AlmafactsDashboardController(),
            $current,
            [
                'dpk_weighted_numerator' => 10.0,
                'dpk_weight' => 200.0,
                'npl_nominal' => 11.0,
                'lar_nominal' => 22.0,
            ],
            2.0
        );

        $this->assertEqualsWithDelta(1.0, $ratios['profitability']['yield'], 0.000001);
        $this->assertEqualsWithDelta(0.2, $ratios['profitability']['cof'], 0.000001);
        $this->assertEqualsWithDelta(0.8, $ratios['profitability']['nim'], 0.000001);
        $this->assertEqualsWithDelta(0.09, $ratios['profitability']['ohc'], 0.000001);
        $this->assertEqualsWithDelta(0.08, $ratios['profitability']['credit_cost'], 0.000001);
        $this->assertEqualsWithDelta(0.1, $ratios['profitability']['roa_before_tax'], 0.000001);
        $this->assertEqualsWithDelta(0.08, $ratios['profitability']['roa_after_tax'], 0.000001);
        $this->assertEqualsWithDelta(0.7, $ratios['profitability']['bopo'], 0.000001);
        $this->assertEqualsWithDelta(0.09, $ratios['profitability']['cer'], 0.000001);
        $this->assertEqualsWithDelta(0.1333333333, $ratios['profitability']['fee_to_income'], 0.000001);
        $this->assertEqualsWithDelta(1.1, $ratios['liquidity']['ldr'], 0.000001);
        $this->assertEqualsWithDelta(0.9, $ratios['liquidity']['casa'], 0.000001);
        $this->assertEqualsWithDelta(0.05, $ratios['asset_quality']['dpk'], 0.000001);
        $this->assertEqualsWithDelta(0.05, $ratios['asset_quality']['npl'], 0.000001);
        $this->assertEqualsWithDelta(0.1, $ratios['asset_quality']['lar'], 0.000001);
    }

    public function test_financial_highlight_marks_requested_cost_and_ratio_deltas_as_inverse(): void
    {
        $snapshots = [
            'current' => [
                'pnl' => [
                    'ftp_expense' => -1,
                    'interest_expense' => -1,
                    'overhead_cost' => -1,
                    'ckpn_expense' => -1,
                ],
                'profitability' => [
                    'cof' => 0.01,
                    'ohc' => -0.01,
                    'bopo' => 0.8,
                ],
                'asset_quality' => [
                    'dpk' => 0.02,
                    'npl' => 0.03,
                ],
            ],
        ];

        $method = new ReflectionMethod(AlmafactsDashboardController::class, 'financialHighlightSections');
        $sections = $method->invoke(new AlmafactsDashboardController(), $snapshots);
        $rows = collect($sections)->pluck('rows')->flatten(1)->keyBy('label');

        foreach ([
            'Beban FTP',
            'Beban Bunga',
            'Overhead Cost',
            'Biaya CKPN',
            'COF',
            'OHC',
            'BOPO',
            'DPK',
            'NPL',
        ] as $label) {
            $this->assertTrue($rows[$label]['is_quality_metric']);
        }
    }

    public function test_financial_highlight_does_not_apply_table_row_hover_highlights(): void
    {
        $view = file_get_contents(resource_path('views/report/almafacts/financial-highlight.blade.php'));

        $this->assertStringNotContainsString('.fh-table tr:hover td', $view);
        $this->assertStringNotContainsString('.fh-table tr.summary-row:hover td', $view);
        $this->assertStringContainsString('<th class="grey-header">MTD</th>', $view);
        $this->assertStringNotContainsString('<th class="grey-header">MOM</th>', $view);
    }

    public function test_financial_ratios_do_not_divide_by_zero_source_bases(): void
    {
        $method = new ReflectionMethod(AlmafactsDashboardController::class, 'calculateFinancialRatios');
        $ratios = $method->invoke(
            new AlmafactsDashboardController(),
            [
                'interest_income' => 100.0,
                'interest_expense' => -10.0,
                'assets_spread' => 60.0,
                'liabilities_spread' => 20.0,
                'overhead_cost' => -9.0,
                'ckpn_expense' => -8.0,
                'profit_before_tax' => 10.0,
                'profit_after_tax' => 8.0,
                'operating_income' => 0.0,
                'operating_expense' => -70.0,
                'contribution_margin' => 0.0,
                'fee_income' => 0.0,
                'ftp_income' => 0.0,
                'average_loans' => 0.0,
                'average_savings' => 0.0,
                'loans' => 0.0,
                'savings' => 0.0,
                'giro' => 0.0,
                'tabungan' => 0.0,
            ],
            [],
            2.0
        );

        $this->assertNull($ratios['profitability']['yield']);
        $this->assertNull($ratios['profitability']['cof']);
        $this->assertNull($ratios['profitability']['bopo']);
        $this->assertNull($ratios['liquidity']['ldr']);
        $this->assertNull($ratios['asset_quality']['npl']);
    }

    public function test_selected_financial_unit_filter_uses_the_unit_code(): void
    {
        $query = DB::table('ssa_almafacts');
        $method = new ReflectionMethod(AlmafactsDashboardController::class, 'applyFinancialFilters');

        $method->invoke(
            new AlmafactsDashboardController(),
            $query,
            'kanca_konsolidasi',
            'kode_unit_kerja',
            'unit_kerja',
            ['KC Madiun'],
            [
                'type' => 'UNIT',
                'selected' => [[
                    'branch' => 'KC Madiun',
                    'code' => '6340',
                    'name' => 'UNIT DAGANGAN MADIUN',
                ]],
            ]
        );

        $this->assertStringContainsString('TRIM(`kode_unit_kerja`) = ?', $query->toSql());
        $this->assertContains('6340', $query->getBindings());
    }
}
