<?php

namespace Tests\Unit;

use App\Support\MicroNettWorkbook;
use PHPUnit\Framework\TestCase;

class MicroNettWorkbookTest extends TestCase
{
    public function test_pt_and_contract_exact_source_boundaries_and_signed_nett_reconcile_in_every_sheet(): void
    {
        $cases = [
            'pt' => [
                [-50_000_000, 'extreme_low'], [0, 'extreme_low'], [1, 'extreme_low'],
                [399_999_999, 'extreme_low'], [400_000_000, 'low'], [800_000_000, 'low'],
                [800_000_001, 'mid'], [1_000_000_000, 'mid'], [1_000_000_001, 'high'],
            ],
            'contract' => [
                [-20_000_000, 'extreme_low'], [0, 'extreme_low'], [1, 'extreme_low'],
                [174_999_999, 'extreme_low'], [175_000_000, 'low'], [350_000_000, 'low'],
                [350_000_001, 'mid'], [450_000_000, 'mid'], [450_000_001, 'high'],
            ],
        ];
        $roster = [];
        $rows = [];
        $expected = [];
        foreach ($cases as $category => $values) {
            foreach ($values as [$amount, $tier]) {
                $pn = (string) (count($roster) + 1);
                $roster[] = ['pn' => $pn, 'name' => 'Mantri '.$pn, 'branch' => 'KC MADIUN', 'unit' => 'UNIT A', 'bc' => '45', 'category' => $category];
                $rows[] = ['pn_mantri' => $pn, 'nama_mantri' => 'Mantri '.$pn, 'cabang' => 'KC MADIUN', 'unit' => 'UNIT A', 'realisasi_os' => $amount, 'realisasi_deb' => 1];
                $expected[$pn] = [$amount, $category, $tier];
            }
        }
        $result = (new MicroNettWorkbook)->mantri($rows, $roster, [], '2026-10-06', 4);
        foreach ($result['sheet_rows'] as $sheet => $groups) {
            $this->assertSame((float) array_sum(array_column($rows, 'realisasi_os')), (float) collect($groups)->sum('net_disbursement.amount'), $sheet);
            $this->assertSame(18, (int) collect($groups)->sum('net_disbursement.deb'), $sheet);
            $people = collect($groups)->flatMap(fn (array $group): array => $group['members'])->keyBy('pn');
            foreach ($expected as $pn => [$amount, $category, $tier]) {
                $this->assertSame((float) $amount, $people->get($pn)['net_amount'], $sheet.' '.$pn);
                $this->assertSame($category, $people->get($pn)['category'], $sheet.' '.$pn);
                $this->assertSame($tier, $people->get($pn)['nett_tier'], $sheet.' '.$pn);
            }
        }
        foreach (['pt', 'contract'] as $category) {
            $buckets = collect($result['total']['tiers'][$category]['buckets'])->keyBy('key');
            $this->assertSame(['none' => 0, 'extreme_low' => 4, 'low' => 2, 'mid' => 2, 'high' => 1], $buckets->map(fn (array $bucket): int => $bucket['mantri'])->all());
            $this->assertEqualsWithDelta(100, $buckets->sum('share'), 0.00001);
        }
    }

    public function test_all_sheets_reconcile_nett_and_include_zero_roster_without_fabricating_gross(): void
    {
        $roster = [
            ['pn' => '1', 'name' => 'A', 'branch' => 'KC MADIUN', 'unit' => 'UNIT A', 'bc' => '45', 'category' => 'pt'],
            ['pn' => '2', 'name' => 'B', 'branch' => 'KC MADIUN', 'unit' => 'UNIT B', 'bc' => '45', 'category' => 'contract'],
            ['pn' => '3', 'name' => 'C', 'branch' => 'KC MADIUN', 'unit' => 'UNIT B', 'bc' => '45', 'category' => 'unknown'],
        ];
        $rows = [
            ['pn_mantri' => '0001', 'nama_mantri' => 'A', 'cabang' => 'KC MADIUN', 'unit' => 'UNIT A', 'realisasi_os' => 500000000, 'realisasi_deb' => 3],
        ];
        $territories = [
            ['bc' => '1001', 'nama_uker' => '01001--UNIT A', 'cabang' => 'MADIUN', 'nama_mbm' => 'MBM A'],
            ['bc' => '1002', 'nama_uker' => 'UNIT B', 'cabang' => 'MADIUN', 'nama_mbm' => 'MBM B'],
        ];
        $result = (new MicroNettWorkbook())->mantri($rows, $roster, $territories, '2026-10-06', 4);
        foreach ($result['sheet_rows'] as $sheet) {
            $this->assertEquals(500000000, collect($sheet)->sum('net_disbursement.amount'));
            $this->assertEquals(3, collect($sheet)->sum('net_disbursement.deb'));
            $this->assertEquals(3, collect($sheet)->sum(fn ($row) => array_sum($row['headcount'])));
            $this->assertEquals(1, collect($sheet)->sum('tiers.contract.total'));
            $this->assertArrayNotHasKey('realization', $sheet[0]);
            $this->assertArrayNotHasKey('daily_net_disbursement', $sheet[0]);
        }
        $this->assertCount(2, $result['sheet_rows']['unit']);
        $unitB = collect($result['sheet_rows']['unit'])->firstWhere('unit', 'UNIT B');
        $this->assertSame('1002', $unitB['branch_code']);
        $this->assertSame('MBM B', $unitB['mbm']);
        $this->assertSame(1, $unitB['headcount']['unknown']);
        $this->assertSame(1, collect($unitB['tiers']['contract']['buckets'])->firstWhere('key', 'extreme_low')['mantri']);
        $this->assertSame(0.0, $unitB['net_disbursement']['amount']);
    }
}
