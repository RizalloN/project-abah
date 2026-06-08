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
        $this->assertStringContainsString('1.600,0', $html);
        $this->assertStringContainsString('12,35%', $html);
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

    private function invokePrivateMethod(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $reflectedMethod = $reflection->getMethod($method);
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invokeArgs($object, $arguments);
    }
}
