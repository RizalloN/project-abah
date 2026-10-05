<?php

namespace Tests\Unit;

use App\Support\LandingRmKurDistribution;
use Tests\TestCase;

class LandingRmKurDistributionTest extends TestCase
{
    public function test_landing_filters_roster_and_amounts_to_selected_branch(): void
    {
        $report = $this->createMock(\App\Http\Controllers\Report\KinerjaRmMikroReportController::class);
        $report->method('buildEmbeddedPayload')->willReturn([
            'rows' => [
                ['pn' => '1', 'cabang' => 'KC MADIUN', 'realisasi_os' => 1_000_000_000],
                ['pn' => '2', 'cabang' => 'KC NGAWI', 'realisasi_os' => 3_000_000_000],
            ],
            'meta' => ['data_period' => '2026-09-30', 'requested_period' => '2026-09-30'],
        ]);
        $report->method('landingKurRmRoster')->willReturn([
            ['pn' => '1', 'branch' => 'KC MADIUN', 'branch_code' => '45', 'unit' => 'KC Madiun'],
            ['pn' => '2', 'branch' => 'KC NGAWI', 'branch_code' => '57', 'unit' => 'KC Ngawi'],
            ['pn' => '3', 'branch' => 'KC MADIUN', 'branch_code' => '552', 'unit' => 'KCP Caruban'],
        ]);
        $this->app->instance(\App\Http\Controllers\Report\KinerjaRmMikroReportController::class, $report);
        $controller = app(\App\Http\Controllers\DashboardSimpananController::class);
        $result = (new \ReflectionMethod($controller, 'buildLandingRmKurProductivityFresh'))
            ->invoke($controller, '2026-09-30', ['upper_label' => 'KC MADIUN', 'label' => 'KC Madiun']);
        $this->assertSame('2026-09-30', $result['period']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame(2, $result['distribution']['total']['rm_count']);
        $this->assertSame(1, $result['distribution']['total']['counts']['zero']);
        $this->assertSame(1, $result['distribution']['total']['counts']['low']);
        $this->assertSame(0, $result['distribution']['total']['counts']['high']);
    }

    public function test_guidance_boundaries_zero_roster_and_unassigned_amount(): void
    {
        $amounts = [0, 1, 900_000_000, 900_000_001, 1_800_000_000, 1_800_000_001, 2_500_000_000, 2_500_000_001];
        $roster = [];
        $rows = [];
        foreach ($amounts as $index => $amount) {
            $roster[] = ['pn' => str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                'branch' => 'KC MADIUN', 'branch_code' => '45', 'unit' => 'KC Madiun'];
            if ($amount > 0) {
                $rows[] = ['pn' => (string) ($index + 1), 'realisasi_os' => $amount];
            }
        }
        $roster[] = $roster[0];
        $rows[] = ['pn' => '-', 'realisasi_os' => 123_000_000];
        $result = (new LandingRmKurDistribution())->build($rows, $roster);
        $this->assertSame(8, $result['total']['rm_count']);
        $this->assertSame(['zero' => 1, 'extreme_low' => 2, 'low' => 2, 'mid' => 2, 'high' => 1], $result['total']['counts']);
        $this->assertEquals(100, array_sum($result['total']['percentages']));
        $this->assertSame(123_000_000.0, $result['unassigned_amount']);
        $this->assertCount(1, $result['rows']);
    }

    public function test_groups_are_separate_per_branch_and_kcp_and_percentages_use_group_headcount(): void
    {
        $result = (new LandingRmKurDistribution())->build([['pn' => '1', 'realisasi_os' => 3_000_000_000]], [
            ['pn' => '1', 'branch' => 'KC MADIUN', 'branch_code' => '45', 'unit' => 'KC Madiun'],
            ['pn' => '2', 'branch' => 'KC MADIUN', 'branch_code' => '552', 'unit' => 'KCP Caruban'],
            ['pn' => '3', 'branch' => 'KC NGAWI', 'branch_code' => '57', 'unit' => 'KC Ngawi'],
        ]);
        $this->assertCount(3, $result['rows']);
        $this->assertSame(100.0, $result['rows'][0]['percentages']['high']);
        $this->assertSame(100.0, $result['rows'][1]['percentages']['zero']);
        $this->assertSame(3, $result['total']['rm_count']);
    }

    public function test_no_roster_is_not_reported_as_zero_performance(): void
    {
        $result = (new LandingRmKurDistribution())->build([], []);
        $this->assertSame([], $result['rows']);
        $this->assertSame(0, $result['total']['rm_count']);
    }

    public function test_blade_renders_all_five_categories_and_separate_people_percent_columns(): void
    {
        $distribution = (new LandingRmKurDistribution())->build([], [
            ['pn' => '1', 'branch' => 'KC MADIUN', 'branch_code' => '45', 'unit' => 'KC Madiun'],
        ]);
        $html = view('dashboard.partials.micro-performance', ['microPerformance' => [
            'meta' => ['available' => true, 'period' => '2026-09-30'],
            'rm_kur_productivity' => ['distribution' => $distribution],
        ]])->render();
        foreach (LandingRmKurDistribution::TIERS as $tier) {
            $this->assertStringContainsString(e($tier['label']), $html);
            $this->assertStringContainsString(e($tier['range']), $html);
        }
        $this->assertStringContainsString('micro-rm-kur-distribution-table', $html);
        $this->assertStringContainsString('100,0%', $html);
        $this->assertStringContainsString('Kode BO/KCP', $html);
    }
}
