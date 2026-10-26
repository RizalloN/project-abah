<?php

namespace Tests\Unit;

use App\Support\LandingMicroPerformanceService;
use App\Support\MantriPerformanceBasis;
use App\Support\MicroNettWorkbook;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class MantriKanwilHkeTest extends TestCase
{
    public function test_nett_per_mantri_hke_uses_pt_and_contract_headcount_and_reconciles_groups_and_totals_across_periods(): void
    {
        $roster = [
            $this->person('1', 'pt'), $this->person('2', 'contract'),
            $this->person('3', 'briguna'), $this->person('4', 'unknown'),
            $this->person('5', 'pt', 'KC MAGETAN'), $this->person('6', 'contract', 'KC MAGETAN'),
        ];
        $rows = [
            $this->row('1', 180_000_000), $this->row('2', 60_000_000),
            $this->row('5', 180_000_000, 'KC MAGETAN'), $this->row('6', 180_000_000, 'KC MAGETAN'),
            $this->row('999', 0),
        ];
        $service = app(LandingMicroPerformanceService::class);
        foreach (['2026-10-16' => 12, '2026-11-16' => 11] as $period => $expectedHke) {
            $hke = app(MantriPerformanceBasis::class)->workingDays(substr($period, 0, 7).'-01', $period);
            $this->assertSame($expectedHke, $hke);
            $workbook = (new MicroNettWorkbook)->mantri($rows, $roster, [], $period, $hke);
            foreach ($workbook['sheet_rows'] as $groups) {
                foreach ($groups as $group) {
                    $headcount = $group['headcount']['pt_non_briguna'] + $group['headcount']['contract'];
                    $this->assertEqualsWithDelta($group['net_disbursement']['amount'] / $headcount / $hke, $group['net_disbursement']['average_per_mantri_hke'], 0.00001);
                }
            }
            foreach (['KC MADIUN' => 240_000_000, 'KC MAGETAN' => 360_000_000] as $branch => $net) {
                $landingRow = $this->landingRow($service, $branch, collect($roster)->where('branch', $branch)->values(), $net, $hke);
                $workbookRow = collect($workbook['sheet_rows']['bo'])->firstWhere('branch', $branch);
                $this->assertEqualsWithDelta($net / 2 / $hke, $workbookRow['net_disbursement']['average_per_mantri_hke'], 0.00001);
                $this->assertSame($workbookRow['net_disbursement']['average_per_mantri_hke'], $landingRow['net_disbursement']['average_per_mantri_hke']);
                $this->assertSame($workbookRow['net_disbursement']['average_per_hke'], $landingRow['net_disbursement']['average_per_hke']);
                $this->assertEqualsWithDelta($net * 2 / $hke, $landingRow['realization']['average_per_hke'], 0.00001);
            }
            $landingTotal = $this->landingRow($service, 'AREA 6', collect($roster), 600_000_000, $hke);
            $this->assertEqualsWithDelta(600_000_000 / 4 / $hke, $workbook['total']['net_disbursement']['average_per_mantri_hke'], 0.00001);
            $this->assertSame($workbook['total']['net_disbursement']['average_per_mantri_hke'], $landingTotal['net_disbursement']['average_per_mantri_hke']);
            $this->assertSame(1, $workbook['total']['headcount']['briguna']);
            $this->assertSame(1, $workbook['total']['headcount']['unknown']);
            $this->assertFalse(collect($workbook['total']['members'])->firstWhere('pn', 'KC MADIUN|UNIT A|999')['counted']);
        }
    }

    public function test_no_eligible_mantri_or_no_hke_returns_zero_without_changing_nett_amount(): void
    {
        $service = app(LandingMicroPerformanceService::class);
        foreach ([[$this->person('1', 'briguna'), $this->person('2', 'unknown')], [$this->person('1', 'pt')]] as $roster) {
            foreach ([0, 12] as $hke) {
                $rows = [$this->row('999', 30_000_000)];
                $workbook = (new MicroNettWorkbook)->mantri($rows, $roster, [], '2026-10-16', $hke);
                $landingRow = $this->landingRow($service, 'KC MADIUN', collect($roster), 30_000_000, $hke);
                $hasEligible = collect($roster)->contains('category', 'pt');
                $expected = $hke > 0 && $hasEligible ? 30_000_000.0 / $hke : 0.0;
                $this->assertSame(30_000_000.0, $workbook['total']['net_disbursement']['amount']);
                $this->assertSame($expected, $workbook['total']['net_disbursement']['average_per_mantri_hke']);
                $this->assertSame($expected, $landingRow['net_disbursement']['average_per_mantri_hke']);
            }
        }
    }

    public function test_rendered_landing_and_nett_sheet_show_the_mantri_average_in_the_nett_column(): void
    {
        $roster = [$this->person('1', 'pt'), $this->person('2', 'contract')];
        $workbook = (new MicroNettWorkbook)->mantri([$this->row('1', 180_000_000), $this->row('2', 60_000_000)], $roster, [], '2026-10-16', 12);
        $landingRow = $this->landingRow(app(LandingMicroPerformanceService::class), 'KC MADIUN', collect($roster), 240_000_000, 12);
        $landing = view('dashboard.partials.micro-performance', ['microPerformance' => [
            'meta' => ['available' => true],
            'mantri_performance' => ['available' => true, 'working_days' => 12, 'rows' => [$landingRow], 'total' => $landingRow],
        ]])->render();
        $report = view('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-mantri-nett-sheets', [
            'mantriPerformance' => $workbook,
            'formatInteger' => static fn ($value): string => number_format((int) $value),
            'formatJuta' => static fn ($value): string => number_format((float) $value / 1_000_000),
            'formatPercent' => static fn ($value): string => $value.'%',
        ])->render();

        $this->assertStringContainsString('Ratas / Mantri / HKE', $landing);
        $this->assertStringContainsString('Ratas / Mantri / HKE', $report);
        $landingXpath = $this->xpath($landing);
        $table = '//table[caption[contains(., "Ringkasan produktivitas Mantri per Branch Office")]]';
        $this->assertSame('10', trim($landingXpath->query($table.'/tbody/tr[1]/td[last()]')->item(0)->textContent));
        $this->assertSame('10', trim($landingXpath->query($table.'/tfoot/tr/td[last()]')->item(0)->textContent));
        $this->assertSame('10', trim($this->xpath($report)->query('//section[@data-sheet="bo"]//tbody/tr[1]/td[contains(@class, "micro-nett-amount")]/following-sibling::td[1]')->item(0)->textContent));
    }

    public function test_current_hr_and_workbook_rosters_separate_zero_productivity_without_changing_signed_nett(): void
    {
        $roster = [];
        $rows = [];
        $netByManager = [];
        foreach (['pt', 'contract'] as $category) {
            foreach (['brihc_active_reference', 'workbook_reference'] as $source) {
                foreach ([-1_000_000, 0] as $amount) {
                    $pn = (string) (count($roster) + 1);
                    $roster[] = array_merge($this->person($pn, $category), ['roster_source' => $source]);
                    $rows[] = $this->row($pn, $amount);
                    $netByManager[$pn] = ['amount' => (float) $amount, 'accounts' => ['ACCOUNT-'.$pn => true]];
                }
            }
        }
        $workbook = (new MicroNettWorkbook)->mantri($rows, $roster, [], '2026-10-16', 12);
        $method = new ReflectionMethod(LandingMicroPerformanceService::class, 'mantriTierPayload');
        foreach (['pt', 'contract'] as $category) {
            $landingTier = $method->invoke(app(LandingMicroPerformanceService::class), collect($roster)->where('category', $category)->values(), $netByManager, $category);
            $workbookBuckets = collect($workbook['total']['tiers'][$category]['buckets'])->keyBy('key');
            $landingBuckets = collect($landingTier['buckets'])->keyBy('key');
            $this->assertSame(2, $workbookBuckets['none']['mantri']);
            $this->assertSame(2, $workbookBuckets['extreme_low']['mantri']);
            $this->assertSame(50.0, (float) $workbookBuckets['extreme_low']['share']);
            $this->assertSame(2, $landingBuckets['none']['mantri']);
            $this->assertSame(2, $landingBuckets['extreme_low']['mantri']);
            $this->assertSame(50.0, (float) $landingBuckets['extreme_low']['share']);
        }
        $this->assertSame(['extreme_low', 'none'], collect($workbook['sheet_rows']['bo'])->flatMap(fn (array $row): array => $row['members'])->pluck('nett_tier')->unique()->values()->all());
    }

    private function landingRow(LandingMicroPerformanceService $service, string $branch, Collection $people, float $net, int $hke): array
    {
        $empty = ['amount' => 0.0, 'accounts' => []];
        $gross = ['amount' => $net * 2, 'accounts' => ['A' => true, 'B' => true]];
        $nett = ['amount' => $net, 'accounts' => ['A' => true, 'B' => true]];

        return (new ReflectionMethod($service, 'mantriPerformanceRow'))
            ->invoke($service, $branch, '-', $people, $empty, $empty, $gross, $nett, [], $hke);
    }

    private function person(string $pn, string $category, string $branch = 'KC MADIUN'): array
    {
        return ['pn' => $pn, 'name' => 'Mantri '.$pn, 'branch' => $branch, 'unit' => 'UNIT A', 'bc' => '3200', 'category' => $category];
    }

    private function row(string $pn, float $amount, string $branch = 'KC MADIUN'): array
    {
        return ['pn_mantri' => $pn, 'nama_mantri' => 'Mantri '.$pn, 'cabang' => $branch, 'unit' => 'UNIT A', 'realisasi_os' => $amount, 'realisasi_deb' => 1];
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($dom);
    }
}
