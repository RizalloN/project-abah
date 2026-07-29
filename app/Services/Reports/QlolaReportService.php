<?php

namespace App\Services\Reports;

use App\Support\SargableDateFilter;

use App\Support\UserBranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QlolaReportService
{
    private const DEFAULT_BRANCHES = [
        'KC MADIUN',
        'KC MAGETAN',
        'KC NGAWI',
        'KC PONOROGO',
    ];

    public function buildFilterOptions(): array
    {
        $options = Cache::remember('report_filter:qlola_options', now()->addHours(6), function () {
            $rows = collect([
                DB::table('ibbisniz_corp')
                    ->selectRaw('TRIM(cabang) as branch_name')
                    ->selectRaw('TRIM(uker) as uker_name')
                    ->whereNotNull('cabang')
                    ->whereNotNull('uker')
                    ->whereRaw("TRIM(cabang) <> ''")
                    ->whereRaw("TRIM(uker) <> ''")
                    ->get(),
                DB::table('usak_ibbiz_uker')
                    ->selectRaw('TRIM(kanca) as branch_name')
                    ->selectRaw('TRIM(uker) as uker_name')
                    ->whereNotNull('kanca')
                    ->whereNotNull('uker')
                    ->whereRaw("TRIM(kanca) <> ''")
                    ->whereRaw("TRIM(uker) <> ''")
                    ->get(),
            ])->flatten(1)
                ->map(function ($row) {
                    $row->branch_name = $this->normalizeOfficeName($row->branch_name ?? '');
                    $row->uker_name = $this->normalizeOfficeName($row->uker_name ?? '');
                    return $row;
                })
                ->filter(fn ($row) => $row->branch_name !== '' && $row->uker_name !== '')
                ->unique(fn ($row) => $row->branch_name . '|' . $row->uker_name)
                ->sortBy([
                    ['branch_name', 'asc'],
                    ['uker_name', 'asc'],
                ])
                ->values();

            return [
                'branchOptions' => $rows->pluck('branch_name')->unique()->values(),
                'branchUkerMap' => $rows->groupBy('branch_name')
                    ->map(fn ($items) => $items->pluck('uker_name')->unique()->values()->all()),
            ];
        });

        $scope = UserBranchScope::current();
        if ($scope === null) {
            return $options;
        }

        return [
            'branchOptions' => collect($options['branchOptions'] ?? [])
                ->filter(fn ($branch): bool => strtoupper(trim((string) $branch)) === $scope['upper_label'])
                ->values(),
            'branchUkerMap' => collect($options['branchUkerMap'] ?? [])
                ->filter(fn ($ukers, $branch): bool => strtoupper(trim((string) $branch)) === $scope['upper_label']),
        ];
    }

    public function handle(Request $request): JsonResponse
    {
        $selectedBranches = collect((array) $request->input('branch_office', []))
            ->map(fn ($branch) => $this->normalizeOfficeName($branch))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $selectedUkers = collect((array) $request->input('nama_uker', []))
            ->map(fn ($uker) => $this->normalizeOfficeName($uker))
            ->filter()
            ->reject(fn ($uker) => $uker === 'ALL UKER')
            ->unique()
            ->values()
            ->all();

        $isBranchFiltered = !empty($selectedBranches);
        $branches = $isBranchFiltered ? $selectedBranches : self::DEFAULT_BRANCHES;
        $groupLabel = $isBranchFiltered ? 'UNIT KERJA' : 'BRANCH OFFICE';
        $totalLabel = $isBranchFiltered
            ? 'TOTAL ' . implode(', ', $branches)
            : 'TOTAL AREA 6';

        $displayItems = !empty($selectedUkers)
            ? $selectedUkers
            : ($isBranchFiltered ? $this->getUkersForBranches($branches) : $branches);

        $corpGroupExpression = $this->normalizeSqlExpression($isBranchFiltered ? 'uker' : 'cabang');
        $usakGroupExpression = $this->normalizeSqlExpression($isBranchFiltered ? 'uker' : 'kanca');
        $corpBranchExpression = $this->normalizeSqlExpression('cabang');
        $usakBranchExpression = $this->normalizeSqlExpression('kanca');
        $corpPeriod = DB::table('ibbisniz_corp')->max('periode');
        $usakPeriod = DB::table('usak_ibbiz_uker')->max('periode');

        $transactionRows = DB::table('ibbisniz_corp')
            ->selectRaw("{$corpGroupExpression} as branch")
            ->selectRaw('COALESCE(SUM(COALESCE(jml_trx_sukses, 0)), 0) as jumlah_transaksi')
            ->selectRaw('COALESCE(SUM(COALESCE(nominal, 0)), 0) as nominal_transaksi')
            ->whereIn(DB::raw($corpBranchExpression), $branches)
            ->when(!empty($selectedUkers), fn ($query) => $query->whereIn(DB::raw($this->normalizeSqlExpression('uker')), $selectedUkers))
            ->when($corpPeriod, fn ($query) => SargableDateFilter::apply($query, 'periode', '=', $corpPeriod))
            ->whereNotNull($isBranchFiltered ? 'uker' : 'cabang')
            ->groupBy(DB::raw($corpGroupExpression))
            ->get()
            ->keyBy('branch');

        $userRows = DB::table('usak_ibbiz_uker')
            ->selectRaw("{$usakGroupExpression} as branch")
            ->selectRaw('COUNT(*) as jumlah_user_aktif')
            ->whereIn(DB::raw($usakBranchExpression), $branches)
            ->when(!empty($selectedUkers), fn ($query) => $query->whereIn(DB::raw($this->normalizeSqlExpression('uker')), $selectedUkers))
            ->when($usakPeriod, fn ($query) => SargableDateFilter::apply($query, 'periode', '=', $usakPeriod))
            ->whereIn(DB::raw('UPPER(TRIM(deskripsi))'), ['ACTIVE', 'ACTIVATED'])
            ->whereNotNull($isBranchFiltered ? 'uker' : 'kanca')
            ->groupBy(DB::raw($usakGroupExpression))
            ->get()
            ->keyBy('branch');

        $data = [];
        $totalUserAktif = 0;
        $totalTransaksi = 0.0;
        $totalNominal = 0.0;

        foreach ($displayItems as $displayItem) {
            $key = $this->normalizeOfficeName($displayItem);
            $transactionRow = $transactionRows->get($key);
            $userRow = $userRows->get($key);

            $jumlahUserAktif = (int) ($userRow->jumlah_user_aktif ?? 0);
            $jumlahTransaksi = (float) ($transactionRow->jumlah_transaksi ?? 0);
            $nominalTransaksi = (float) ($transactionRow->nominal_transaksi ?? 0);

            $data[] = [
                'branch' => $key,
                'jumlah_user_aktif' => $jumlahUserAktif,
                'jumlah_transaksi' => $jumlahTransaksi,
                'nominal_transaksi' => $nominalTransaksi,
            ];

            $totalUserAktif += $jumlahUserAktif;
            $totalTransaksi += $jumlahTransaksi;
            $totalNominal += $nominalTransaksi;
        }

        return response()->json([
            'status' => 'success',
            'group_label' => $groupLabel,
            'labels' => [
                'corp_period' => $corpPeriod,
                'user_period' => $usakPeriod,
            ],
            'data' => $data,
            'total' => [
                'branch' => $totalLabel,
                'jumlah_user_aktif' => $totalUserAktif,
                'jumlah_transaksi' => $totalTransaksi,
                'nominal_transaksi' => $totalNominal,
            ],
        ]);
    }

    private function getUkersForBranches(array $branches): array
    {
        $filterOptions = $this->buildFilterOptions();
        $branchUkerMap = $filterOptions['branchUkerMap'] ?? collect();

        return collect($branches)
            ->flatMap(fn ($branch) => $branchUkerMap[$branch] ?? [])
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalizeOfficeName(mixed $value): string
    {
        $name = strtoupper(trim((string) $value));
        $name = preg_replace('/^\d+\s*-\s*/', '', $name) ?? $name;
        return preg_replace('/\s+/', ' ', trim($name)) ?? trim($name);
    }

    private function normalizeSqlExpression(string $column): string
    {
        return "UPPER(TRIM(CASE WHEN LOCATE(' - ', {$column}) > 0 THEN SUBSTRING({$column}, LOCATE(' - ', {$column}) + 3) ELSE {$column} END))";
    }
}
