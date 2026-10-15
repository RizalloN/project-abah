<?php

namespace Tests\Unit;

use App\Support\EkosistemGeography;
use App\Support\EkosistemMonitoring;
use Tests\TestCase;

class EkosistemMonitoringTest extends TestCase
{
    public function test_financial_categories_are_never_combined_with_annual_cash_flow_or_payroll(): void
    {
        $monitor = new EkosistemMonitoring;
        $rows = [
            $this->row(['Jenis_Potensi' => 'Simpanan', 'Est_Min_Juta' => 1000, 'Est_Max_Juta' => 2000, 'Nama_PIC' => ' A ']),
            $this->row(['Jenis_Potensi' => 'Kredit', 'Est_Min_Juta' => 500, 'Est_Max_Juta' => null, 'Realisasi_Nominal_Juta' => 100]),
            $this->row(['Jenis_Potensi' => 'Arus dana', 'Est_Min_Juta' => 90000, 'Est_Max_Juta' => 95000]),
            $this->row(['Jenis_Potensi' => 'Payroll', 'Est_Nasabah' => 60, 'Est_Max_Juta' => 7000]),
            $this->row(['Jenis_Potensi' => 'Merchant', 'Est_Nasabah' => 20, 'Est_Max_Juta' => 5000]),
        ];
        $s = $monitor->stats($monitor->decorate($rows, '2026-10-15'));
        $this->assertSame(['min' => 1000.0, 'max' => 2000.0], $s['categories']['Simpanan']);
        $this->assertSame(['min' => 500.0, 'max' => 500.0], $s['categories']['Kredit']);
        $this->assertSame(['min' => 90000.0, 'max' => 95000.0], $s['categories']['Arus dana']);
        $this->assertSame(60, $s['payroll']);
        $this->assertSame(100.0, $s['realization']);
        $this->assertSame(20, $s['coverage']);
        $this->assertSame(4, $s['unassigned']);
        $this->assertSame(5, array_sum($s['types']));
        $this->assertSame(5, array_sum($s['statuses']));
    }

    public function test_condition_order_matches_the_supplied_appscript_including_unstarted_overdue_and_dependencies(): void
    {
        $monitor = new EkosistemMonitoring;
        $cases = [
            ['Status' => 'Selesai', 'Target_Selesai' => '2026-01-01', 'expected' => 'done'],
            ['Status' => 'Batal', 'Target_Selesai' => '2026-01-01', 'expected' => 'cancel'],
            ['Status' => 'Belum mulai', 'Target_Selesai' => '2026-10-14', 'expected' => 'late'],
            ['Status' => 'Tertunda', 'expected' => 'hold'],
            ['Status' => 'Berjalan', 'Tgl_Mulai' => '2026-09-30', 'Target_Selesai' => '2026-10-16', 'expected' => 'stale'],
            ['Status' => 'Berjalan', 'Tgl_Mulai' => '2026-09-30', 'Diubah_Pada' => '2026-10-14', 'Target_Selesai' => '2026-10-22', 'expected' => 'due'],
            ['Status' => 'Berjalan', 'Tgl_Mulai' => '2026-10-01', 'Target_Selesai' => '2026-10-23', 'expected' => 'ok'],
            ['Prasyarat_ID' => 'PRIOR', 'expected' => 'blocked'],
            ['expected' => 'nopic'],
            ['Nama_PIC' => 'Fitri', 'expected' => 'ready'],
        ];
        foreach ($cases as $case) {
            $health = $monitor->health($this->row($case), ['PRIOR' => ['Status' => 'Berjalan']], '2026-10-15');
            $this->assertSame($case['expected'], $health['key']);
        }
        $this->assertSame('nopic', $monitor->health($this->row(['Prasyarat_ID' => 'PRIOR']), ['PRIOR' => ['Status' => 'Selesai']], '2026-10-15')['key']);
    }

    public function test_one_unmapped_record_remains_in_totals_and_is_not_counted_as_a_registered_ecosystem(): void
    {
        $monitor = new EkosistemMonitoring;
        $rows = $monitor->decorate([$this->row(), $this->row(['ID_Prospek' => 'P2', 'ID_Ekosistem' => '', 'Ekosistem' => 'Lainnya', 'Ekosistem_Terdaftar' => false])], '2026-10-15');
        $stats = $monitor->stats($rows);
        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['ecosystems']);
        $this->assertSame(2, $stats['ecosystem_groups']);
        $this->assertSame(1, $stats['unmapped_ecosystem']);
        $this->assertSame(['P2'], array_column($monitor->filter($rows, ['condition' => 'unmapped']), 'ID_Prospek'));
    }

    public function test_quality_and_empty_filters_return_the_exact_records_and_keep_zero_amounts(): void
    {
        $monitor = new EkosistemMonitoring;
        $rows = $monitor->decorate([
            $this->row(['ID_Prospek' => 'P1', 'Nama_PIC' => 'A', 'Realisasi_Nominal_Juta' => 0, 'Cek_Satuan' => [['value' => 10000000]]]),
            $this->row(['ID_Prospek' => 'P2', 'Ring' => '', 'Peran_PIC' => '', 'Nama_PIC' => '  ']),
        ], '2026-10-15');
        $this->assertSame(['P1'], array_column($monitor->filter($rows, ['pic' => '__assigned']), 'ID_Prospek'));
        $this->assertSame(['P2'], array_column($monitor->filter($rows, ['pic' => '__unassigned', 'ring' => '__empty', 'peran' => '__empty']), 'ID_Prospek'));
        $this->assertSame(['P1'], array_column($monitor->filter($rows, ['condition' => 'unit']), 'ID_Prospek'));
        $this->assertSame(1, $monitor->stats($rows)['conditions']['unit']);
        $this->assertSame(0.0, $monitor->stats($rows)['realization']);
    }

    public function test_polygon_geometry_uses_the_existing_administrative_features_and_respects_allowed_branches(): void
    {
        $geo = new EkosistemGeography;
        $all = $geo->build(['madiun', 'magetan', 'ngawi', 'ponorogo']);
        $this->assertTrue($all['ready']);
        $this->assertCount(4, $all['regions']);
        $this->assertCount(22, $all['regions']['madiun']['paths']);
        $this->assertSame(['magetan'], array_keys($geo->build(['magetan'])['regions']));
        $this->assertFalse($geo->build([])['ready']);
        foreach ($all['regions'] as $region) {
            $this->assertNotEmpty($region['paths']);
            $this->assertMatchesRegularExpression('/^M[0-9.,LZM-]+$/', $region['paths'][0]);
        }
    }

    private function row(array $patch = []): array
    {
        return array_replace(['ID_Prospek' => 'P1', 'ID_Ekosistem' => 'RSUD', 'Ekosistem' => 'RSUD', 'Ekosistem_Terdaftar' => true,
            'KC' => 'Madiun', 'Jalur_RM' => 'RMFT', 'Status' => 'Belum mulai', 'Target_Selesai' => '', 'Nama_PIC' => '', 'Jenis_Potensi' => 'Simpanan',
            'Ring' => 'L1', 'Cek_Satuan' => []], $patch);
    }
}
