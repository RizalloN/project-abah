<?php

namespace Tests\Unit;

use App\Support\MicroNettWorkbook;
use PHPUnit\Framework\TestCase;

class MicroNettWorkbookTest extends TestCase
{
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
        $this->assertSame(1, $unitB['tiers']['contract']['buckets'][0]['mantri']);
        $this->assertSame(0.0, $unitB['net_disbursement']['amount']);
    }
}
