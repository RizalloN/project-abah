<?php

namespace Tests\Unit;

use App\Services\Reports\KpiRmSmeDashboardService;
use Tests\TestCase;

class KpiRmSmeDashboardServiceTest extends TestCase
{
    public function test_dashboard_builds_active_area_profiles_and_english_abbreviated_periods(): void
    {
        $dashboard = (new KpiRmSmeDashboardService())->build(
            $this->sources(),
            ['selected' => 'all', 'locked' => false, 'options' => []]
        );

        $this->assertSame(['2025-12-31', '2026-06-30', '2026-07-31'], array_column($dashboard['periods'], 'key'));
        $this->assertSame('2026-07-31', $dashboard['latest_period']);
        $this->assertCount(2, $dashboard['profiles']);
        $this->assertSame(['00045:00123456', '00049:00987654'], array_keys($dashboard['profiles']));
        $this->assertSame(2, $dashboard['stats']['rm_count']);
        $this->assertSame('Area 6', $dashboard['stats']['scope_label']);
        $this->assertCount(2, $dashboard['summary_rows']);

        $madiun = $dashboard['profiles']['00045:00123456'];
        $this->assertSame('RM Nama Sama', $madiun['name']);
        $this->assertSame(0.005, $madiun['history']['2026-07-31']['metrics']['downgrade_kol2']['value']);
        $this->assertSame(0.01, $madiun['history']['2026-07-31']['metrics']['downgrade_kol2']['target']);
        $this->assertNull($madiun['history']['2026-07-31']['metrics']['product_holding']['value']);

        $madiunSummary = collect($dashboard['summary_rows'])->firstWhere('id', '00045:00123456');
        $this->assertTrue($madiunSummary['scores']['downgrade_kol2']['achieved']);
        $this->assertSame(1.1, $madiunSummary['scores']['downgrade_kol2']['achievement']);
        $this->assertSame(22.0, $madiunSummary['scores']['downgrade_kol2']['score']);
    }

    public function test_dashboard_honours_locked_branch_and_keeps_same_names_separate(): void
    {
        $dashboard = (new KpiRmSmeDashboardService())->build(
            $this->sources(),
            [
                'selected' => '00045 -- KC Madiun (Konsolidasi-MB)',
                'locked' => true,
                'options' => [],
            ]
        );

        $this->assertSame(['00045:00123456'], array_keys($dashboard['profiles']));
        $this->assertSame('KC Madiun', $dashboard['stats']['scope_label']);
        $this->assertSame(1, $dashboard['stats']['rm_count']);
        $this->assertSame(['KC Madiun'], array_column($dashboard['branch_analysis'], 'branch'));
    }

    /** @return array<string, array{header: array<int, string>, rows: array<int, array<int, string>>}> */
    private function sources(): array
    {
        $header = [
            'PERIODE', 'NAMA KANCA KONSOL', 'NAMA UKO', 'NAMA MANTRI', 'JG',
            'Avg Balance Small', 'RKA Avg Balance Small', 'OS Small', 'RKA OS Small',
            'Jumlah Debitur Small', 'RKA Jumlah Debitur Small', 'Downgrade to Kol 2 %',
            'RKA Downgrade to Kol 2 %', 'Rasio DPK Debitur Kelolaan to Loan SME',
            'RKA Rasio DPK Debitur Kelolaan to Loan SME', '% Booking Value Chain Cash Loan',
            'RKA % Booking Value Chain Cash Loan', 'Product Holding Nasabah Kelolaan',
            'RKA Product Holding Nasabah Kelolaan', 'POSISI LANCAR', 'POSISI SML', 'POSISI NPL',
        ];
        $mainRows = [];
        foreach (['31 Dec 2025', '30 Jun 2026', '31 Jul 2026'] as $index => $period) {
            $mainRows[] = $this->row($period, '00045 -- KC Madiun (Konsolidasi-MB)', '00045 -- KC Madiun', '00123456 - RM Nama Sama', $index + 1);
            $mainRows[] = $this->row($period, '00049 -- KC Magetan (Konsolidasi-MB)', '00049 -- KC Magetan', '00987654 - RM Nama Sama', $index + 2);
            $mainRows[] = $this->row($period, '00057 -- KC Ngawi (Konsolidasi-MB)', '00057 -- KC Ngawi', '00777777 - RM Tidak Aktif', $index + 3);
        }

        return [
            'main' => ['header' => $header, 'rows' => $mainRows],
            'targets' => [
                'header' => $header,
                'rows' => [
                    $this->row('31 Dec 2026', '00045 -- KC Madiun (Konsolidasi-MB)', '00045 -- KC Madiun', '00123456 - RM Nama Sama', 5),
                    $this->row('31 Dec 2026', '00049 -- KC Magetan (Konsolidasi-MB)', '00049 -- KC Magetan', '00987654 - RM Nama Sama', 5),
                ],
            ],
            'roster' => [
                'header' => ['NAMA KANCA KONSOL', 'NAMA UKO', 'NAMA MANTRI', 'JG'],
                'rows' => [
                    ['00045 -- KC Madiun (Konsolidasi-MB)', '00045 -- KC Madiun', '00123456 - RM Nama Sama', 'JG07'],
                    ['00049 -- KC Magetan (Konsolidasi-MB)', '00049 -- KC Magetan', '00987654 - RM Nama Sama', 'JG07'],
                ],
            ],
        ];
    }

    /** @return array<int, string> */
    private function row(string $period, string $branch, string $unit, string $person, int $seed): array
    {
        return [
            $period, $branch, $unit, $person, 'JG07',
            (string) (100 + $seed), '100', (string) (200 + $seed), '200',
            (string) (10 + $seed), '10', $period === '31 Jul 2026' && str_contains($branch, 'Madiun') ? '0,50%' : '1,20%',
            '1,00%', '80,00%', '75,00%', '5,25', '5,00',
            $period === '31 Jul 2026' && str_contains($branch, 'Madiun') ? '#ERROR!' : '85,00%',
            '80,00%', (string) (900 + $seed), (string) (80 + $seed), (string) (20 + $seed),
        ];
    }
}
