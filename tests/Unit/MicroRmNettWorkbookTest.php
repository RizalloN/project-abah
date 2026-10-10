<?php

namespace Tests\Unit;

use App\Support\MicroRmNettWorkbook;
use PHPUnit\Framework\TestCase;

class MicroRmNettWorkbookTest extends TestCase
{
    public function test_identity_aggregation_preserves_signed_source_values_and_zero_roster(): void
    {
        $roster = [
            ['pn' => '001', 'name' => 'A', 'branch' => 'KC MADIUN', 'unit' => 'KC MADIUN', 'office_type' => 'kc'],
            ['pn' => '002', 'name' => 'B', 'branch' => 'KC MADIUN', 'unit' => 'KCP DOLOPO', 'office_type' => 'kcp'],
            ['pn' => '003', 'name' => 'C', 'branch' => 'KC NGAWI', 'unit' => 'KC NGAWI', 'office_type' => 'kc'],
        ];
        $raw = [
            ['rm' => '0001 - WRONG NAME', 'realisasi_os' => 1000, 'realisasi_deb' => 2, 'unit' => 'OLD UNIT'],
            ['pn' => '1', 'nama' => 'A', 'realisasi_os' => -100, 'realisasi_deb' => 1, 'unit' => 'NEW UNIT'],
            ['pn' => '999', 'nama' => 'B', 'realisasi_os' => 500, 'realisasi_deb' => 1],
            ['pn' => null, 'nama' => 'RM LAIN', 'realisasi_os' => 600, 'realisasi_deb' => 1],
            ['pn' => '', 'nama' => 'B', 'realisasi_os' => -50, 'realisasi_deb' => 1],
        ];
        $result = (new MicroRmNettWorkbook())->build($raw, $roster, 'Area 6', '2026-10-07');
        $this->assertCount(3, $result['rows']);
        $byPn = array_column($result['rows'], null, 'pn');
        $this->assertSame(['kc', 'kc', 'kcp'], array_column($result['rows'], 'office_type'));
        $this->assertSame(100.0, $result['assigned_total']['need_acceleration_percentage']);
        $this->assertSame(900.0, $byPn['001']['realisasi_os']);
        $this->assertSame(3, $byPn['001']['realisasi_deb']);
        $this->assertSame(-50.0, $byPn['002']['realisasi_os']);
        $this->assertSame(0.0, $byPn['003']['realisasi_os']);
        $this->assertSame(1950.0, $result['total']['realisasi_os']);
        $this->assertSame(850.0, $result['assigned_total']['realisasi_os']);
        $this->assertSame(1100.0, $result['unassigned_amount']);
        $this->assertSame(2, $result['unassigned_deb']);
        $this->assertCount(2, $result['unassigned_rows']);
        $this->assertSame('999', $result['unassigned_rows'][0]['source_pn']);
        $this->assertCount(2, $result['sections']['kc']['rows']);
        $this->assertCount(1, $result['sections']['kcp']['rows']);
        $this->assertSame(50.0, $result['sections']['kc']['total']['percentages']['zero']);
        $this->assertSame(100.0, $result['sections']['kcp']['total']['percentages']['zero']);
        $this->assertEqualsWithDelta(200 / 3, $result['distribution']['total']['percentages']['zero'], 0.00001);
    }

    public function test_unknown_numeric_and_ambiguous_name_never_override_identity(): void
    {
        $roster = [
            ['pn' => '1', 'name' => 'SAME', 'branch' => 'KC MADIUN', 'unit' => 'KC MADIUN'],
            ['pn' => '2', 'name' => 'SAME', 'branch' => 'KC MADIUN', 'unit' => 'KC MADIUN'],
        ];
        $rows = [
            ['pn' => '', 'nama' => 'SAME', 'realisasi_os' => 10],
            ['pn' => '999BAD', 'nama' => 'SAME', 'realisasi_os' => 20],
            ['pn' => 'RM LAIN', 'nama' => 'SAME', 'realisasi_os' => 30],
        ];
        $result = (new MicroRmNettWorkbook())->build($rows, $roster, 'KC Madiun', '2026-10-07');
        $this->assertSame(60.0, $result['unassigned_amount']);
        $this->assertSame(0.0, $result['assigned_total']['realisasi_os']);
        $this->assertCount(1, $result['sections']['kc']['rows']);
        $this->assertSame([], $result['sections']['kcp']['rows']);
    }
}
