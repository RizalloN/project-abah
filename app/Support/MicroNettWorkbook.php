<?php

namespace App\Support;

class MicroNettWorkbook
{
    public function mantri(array $rows, array $roster, array $territories, string $period, int $workingDays): array
    {
        $normal = static fn ($value): string => preg_replace('/\s+/', ' ', preg_replace('/^\d+\s*[-\x{2013}\x{2014}]+\s*/u', '', strtoupper(trim((string) $value))));
        $pnKey = static fn ($value): string => ltrim(explode(' - ', trim((string) $value))[0], '0');
        $mapping = collect($territories)->groupBy(fn ($r) => $normal($r['cabang']).'|'.$normal($r['nama_uker']));
        $byCode = collect($territories)->groupBy(fn ($r) => preg_replace('/^KC\s+/', '', $normal($r['cabang'])).'|'.ltrim((string) $r['bc'], '0'));
        $people = [];
        foreach ($roster as $person) {
            $pn = $pnKey($person['pn']);
            $people[$pn] = array_merge($person, ['net_amount' => 0.0, 'net_deb' => 0, 'counted' => true]);
        }
        foreach ($rows as $row) {
            $pn = $pnKey($row['pn_mantri'] ?? $row['pn_pengelola'] ?? '');
            if (! ctype_digit($pn) || ! isset($people[$pn])) {
                $pn = $normal($row['cabang']).'|'.$normal($row['unit']).'|'.$pn;
            }
            $people[$pn] ??= ['pn' => $pn, 'name' => $row['nama_mantri'] ?? '-', 'branch' => $row['cabang'], 'unit' => $row['unit'], 'bc' => $row['bc'] ?? '-', 'category' => 'unknown', 'net_amount' => 0.0, 'net_deb' => 0, 'counted' => false];
            $people[$pn]['net_amount'] += (float) ($row['realisasi_os'] ?? 0);
            $people[$pn]['net_deb'] += (int) ($row['realisasi_deb'] ?? 0);
        }
        $groups = ['bo' => [], 'mbm' => [], 'unit' => []];
        foreach ($people as $person) {
            $branch = $normal($person['branch']);
            $branchKey = preg_replace('/^KC\s+/', '', $branch);
            $matches = $mapping->get($branch.'|'.$normal($person['unit']), $mapping->get($branchKey.'|'.$normal($person['unit']), collect()));
            $coded = $byCode->get($branchKey.'|'.ltrim((string) ($person['bc'] ?? ''), '0'), collect());
            $territory = $coded->count() === 1 ? $coded->first() : ($matches->count() === 1 ? $matches->first() : null);
            $person['mbm'] = trim((string) ($territory['nama_mbm'] ?? '')) ?: 'Belum dipetakan';
            $person['unit'] = $normal($territory['nama_uker'] ?? $person['unit']);
            $person['bc'] = (string) ($territory['bc'] ?? $person['bc'] ?? '-');
            $person['nett_tier'] = $person['counted'] && in_array($person['category'], ['pt', 'contract'], true)
                ? $this->nettTier($person['category'], (float) $person['net_amount'], ($person['roster_source'] ?? '') === 'workbook_reference')
                : null;
            foreach (['bo' => $branch, 'mbm' => $person['mbm'], 'unit' => $person['unit']] as $sheet => $label) {
                $groups[$sheet][$branch.'|'.$label] ??= ['group_label' => $label, 'branch' => $branch, 'branch_code' => $sheet === 'unit' ? $person['bc'] : '-', 'unit' => $sheet === 'unit' ? $person['unit'] : '', 'mbm' => $sheet === 'bo' ? '' : $person['mbm'], 'people' => []];
                $groups[$sheet][$branch.'|'.$label]['people'][] = $person;
            }
        }
        $summarize = function (array $group) use ($workingDays): array {
            $people = collect($group['people']);
            unset($group['people']);
            $group['members'] = $people->values()->all();
            $headcount = ['pt_non_briguna' => 0, 'contract' => 0, 'briguna' => 0, 'unknown' => 0];
            foreach ($people->where('counted', true) as $person) {
                $key = $person['category'] === 'pt' ? 'pt_non_briguna' : $person['category'];
                $headcount[$key]++;
            }
            $amount = (float) $people->sum('net_amount');
            $tiers = [];
            foreach (['pt', 'contract'] as $category) {
                $eligible = $people->where('counted', true)->where('category', $category);
                $hasSourceDefinedTier = $eligible->contains(fn (array $person): bool => ($person['roster_source'] ?? '') === 'workbook_reference');
                $buckets = [];
                foreach (['none' => 'Belum Realisasi', 'extreme_low' => 'Extreme Low', 'low' => 'Low', 'mid' => 'Mid', 'high' => 'High'] as $key => $label) {
                    $range = match ($key) { 'none' => 'Nett <= Rp0', 'extreme_low' => $category === 'contract' ? '>0 - <175 jt' : '>0 - <400 jt', 'low' => $category === 'contract' ? '175 - 350 jt' : '400 - 800 jt', 'mid' => $category === 'contract' ? '>350 - 450 jt' : '>800 jt - 1 M', default => $category === 'contract' ? '>450 jt' : '>1 M' };
                    if ($key === 'extreme_low' && $hasSourceDefinedTier) {
                        $range = $category === 'contract' ? '<175 jt' : '<400 jt';
                    }
                    $buckets[$key] = ['key' => $key, 'label' => $label, 'range' => $range, 'mantri' => 0, 'share' => 0.0, 'people' => []];
                }
                foreach ($eligible as $person) {
                    $value = $person['net_amount'];
                    $key = $this->nettTier($category, (float) $value, ($person['roster_source'] ?? '') === 'workbook_reference');
                    $buckets[$key]['mantri']++;
                    $buckets[$key]['people'][] = $person;
                }
                foreach ($buckets as &$bucket) {
                    $bucket['share'] = $eligible->count() ? 100 * $bucket['mantri'] / $eligible->count() : 0.0;
                }
                unset($bucket);
                $tiers[$category] = ['total' => $eligible->count(), 'buckets' => array_values($buckets)];
            }

            return array_merge($group, ['headcount' => $headcount, 'net_disbursement' => ['amount' => $amount, 'deb' => (int) $people->sum('net_deb'), 'average_per_hke' => $workingDays > 0 ? $amount / $workingDays : 0.0], 'tiers' => $tiers]);
        };
        $sheets = [];
        foreach ($groups as $sheet => $items) {
            ksort($items);
            $sheets[$sheet] = array_map($summarize, array_values($items));
        }

        return ['available' => $people !== [], 'working_days' => $workingDays, 'daily_period' => $period, 'accumulation_label' => substr($period, 0, 7), 'sheet_rows' => $sheets, 'rows' => $sheets['bo'],
            'total' => $summarize(['group_label' => 'TOTAL', 'branch' => 'TOTAL', 'branch_code' => '-', 'people' => array_values($people)]),
            'sheet_basis' => 'Nett mengikuti kalkulator KPI Kinerja RM Mikro dan penempatan roster aktif BRIHC; status kepegawaian tidak lengkap dipisahkan. Realisasi bruto dan nett harian tidak tersedia pada sumber agregat ini.'];
    }

    // The workbook formula classifies zero and negative nett as Extreme Low;
    // legacy BRIHC rosters retain the separate Belum Realisasi bucket.
    private function nettTier(string $category, float $value, bool $sourceDefined = false): string
    {
        [$low, $mid, $high] = $category === 'contract' ? [175000000, 350000000, 450000000] : [400000000, 800000000, 1000000000];

        return match (true) { ! $sourceDefined && $value <= 0 => 'none', $value < $low => 'extreme_low', $value <= $mid => 'low', $value <= $high => 'mid', default => 'high' };
    }
}
