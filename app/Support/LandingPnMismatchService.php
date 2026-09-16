<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LandingPnMismatchService
{
    private const SEGMENTS = ['sme' => 'SMALL', 'consumer' => 'CONSUMER', 'micro' => 'MICRO'];

    /** @param array<string, mixed>|null $branchScope */
    public function summary(string $segment, ?string $period, ?array $branchScope): array
    {
        $branch = strtoupper(trim((string) ($branchScope['upper_label'] ?? '')));
        $empty = ['available' => false, 'period' => $period, 'total' => 0, 'branches' => []];
        if (! isset(self::SEGMENTS[$segment]) || ! $period
            || ! Schema::hasTable('daily_loan_dinamis') || ! Schema::hasTable('brihc_pemasar')
            || ! Schema::hasColumns('daily_loan_dinamis', ['periode', 'segmen_dashboard', 'cabang1', 'pn_pengelola1', 'pn_name1'])
            || ! Schema::hasColumns('brihc_pemasar', ['pernr', 'completename'])) {
            return $empty;
        }

        $version = ReportCacheVersion::get('pinjaman').':'.ReportCacheVersion::get('brihc');
        return Cache::remember('landing:pn-mismatch:v2:'.md5("{$segment}|{$period}|{$branch}|{$version}"), 600,
            function () use ($segment, $period, $branch): array {
                $references = [];
                foreach (DB::table('brihc_pemasar')->whereNotNull('pernr')->get(['pernr', 'completename']) as $person) {
                    $pn = $this->pn((string) $person->pernr);
                    if ($pn !== '') {
                        $references[$pn][] = trim((string) $person->completename);
                    }
                }
                if ($references === []) {
                    return ['available' => false, 'period' => $period, 'total' => 0, 'branches' => []];
                }

                $rows = $this->source($segment, $period, $branch)
                    ->whereNotNull('pn_pengelola1')->where('pn_pengelola1', '<>', '')
                    ->select('cabang1', 'pn_pengelola1', 'pn_name1')
                    ->selectRaw('COUNT(*) as account_count')
                    ->groupBy('cabang1', 'pn_pengelola1', 'pn_name1')->get();
                $branches = [];
                $total = 0;
                foreach ($rows as $row) {
                    $raw = trim((string) $row->pn_pengelola1);
                    $pn = $this->pn($raw);
                    $sourceName = trim((string) ($row->pn_name1 ?: preg_replace('/^\s*\d+\s*-\s*/', '', $raw)));
                    $names = $references[$pn] ?? [];
                    if ($pn !== '' && $names !== [] && ($sourceName === '' || collect($names)->contains(
                        fn (string $name): bool => $this->nameKey($name) === $this->nameKey($sourceName)
                    ))) {
                        continue;
                    }
                    $label = strtoupper(trim((string) $row->cabang1));
                    $count = (int) $row->account_count;
                    $total += $count;
                    $branches[$label]['branch'] = $label;
                    $branches[$label]['count'] = ($branches[$label]['count'] ?? 0) + $count;
                    $branches[$label]['managers'][] = [
                        'raw' => $raw,
                        'source_name' => $sourceName,
                        'pn' => $pn,
                        'brihc_name' => implode(' / ', array_unique($names)),
                    ];
                }

                return ['available' => true, 'period' => $period, 'total' => $total,
                    'branches' => array_values($branches)];
            });
    }

    /** @param array<string, mixed>|null $branchScope */
    public function nominatives(string $segment, ?string $period, ?array $branchScope, string $branch, int $page): array
    {
        $summary = $this->summary($segment, $period, $branchScope);
        $selected = collect($summary['branches'])->firstWhere('branch', strtoupper(trim($branch)));
        if (! $summary['available'] || ! $selected) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'last_page' => 1];
        }
        $managers = collect($selected['managers'])->keyBy('raw');
        $result = $this->source($segment, $period, strtoupper(trim($branch)))
            ->whereIn('pn_pengelola1', $managers->keys()->all())
            ->select('nomor_rekening1', 'nama_debitur1', 'pn_pengelola1', 'unit1', 'produk_dashboard')
            ->orderBy('nomor_rekening1')->paginate(25, ['*'], 'page', $page);

        return ['data' => collect($result->items())->map(function ($row) use ($managers): array {
            $manager = $managers->get(trim((string) $row->pn_pengelola1), []);
            return ['rekening' => (string) $row->nomor_rekening1,
                'debitur' => (string) $row->nama_debitur1,
                'unit' => (string) $row->unit1,
                'produk' => (string) $row->produk_dashboard,
                'pn' => $manager['pn'] ?? '',
                'nama_sumber' => $manager['source_name'] ?? '',
                'nama_brihc' => ($manager['brihc_name'] ?? '') ?: 'Tidak ditemukan di BRIHC'];
        })->all(), 'total' => $result->total(), 'page' => $result->currentPage(),
            'last_page' => $result->lastPage()];
    }

    private function source(string $segment, string $period, string $branch): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('daily_loan_dinamis')->where('periode', $period)
            ->whereIn('segmen_dashboard', match ($segment) {
                'sme' => ['Small', 'SMALL'],
                'consumer' => ['Consumer', 'CONSUMER'],
                default => ['Micro', 'MICRO', 'Mikro', 'MIKRO'],
            });
        if ($branch !== '') {
            $query->whereRaw('UPPER(TRIM(cabang1)) = ?', [$branch]);
        } else {
            $query->whereIn('cabang1', ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO',
                'KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo']);
        }
        return $query;
    }

    private function pn(string $value): string
    {
        return preg_match('/^\s*0*(\d{5,})\s*(?:-|$)/', $value, $match) === 1
            ? ltrim($match[1], '0') : '';
    }

    private function nameKey(string $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper($value)) ?? '';
    }
}
