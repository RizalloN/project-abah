<?php

namespace App\Services\Reports;

use App\Support\SargableDateFilter;

use App\Support\UserBranchScope;
use App\Support\RkaLookupService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk laporan Kinerja New Payroll.
 * Menangani query dan helper data untuk halaman Kinerja New Payroll.
 */
class NewPayrollReportService
{
    private const QUALITY_PAYROLL_MINIMUM_BALANCE = 5000000;

    public function __construct(
        private readonly RkaLookupService $rkaLookup
    ) {}

    /**
     * Bangun opsi filter branch/uker untuk halaman New Payroll.
     */
    public function buildFilterOptions(): array
    {
        $branchUkerRows = DB::table('performance_pis_per_produk')
            ->selectRaw('TRIM(kanca) as branch_name')
            ->selectRaw('TRIM(uker) as uker_name')
            ->whereNotNull('kanca')
            ->whereNotNull('uker')
            ->whereRaw("TRIM(kanca) <> ''")
            ->whereRaw("TRIM(uker) <> ''")
            ->distinct()
            ->orderBy('branch_name')
            ->orderBy('uker_name')
            ->get();

        $scope = UserBranchScope::current();
        if ($scope !== null) {
            $branchUkerRows = $branchUkerRows
                ->filter(fn ($row): bool => strtoupper(trim((string) ($row->branch_name ?? ''))) === $scope['upper_label'])
                ->values();
        }

        return [
            'branchOptions' => $branchUkerRows
                ->pluck('branch_name')
                ->filter()
                ->unique()
                ->values(),
            'branchUkerMap' => $branchUkerRows
                ->groupBy('branch_name')
                ->map(fn ($rows) => $rows->pluck('uker_name')->filter()->unique()->values()->all()),
        ];
    }

    /**
     * Proses request fetch data dan kembalikan JsonResponse.
     */
    public function fetchData(Request $request): JsonResponse
    {
        $selectedDate     = Carbon::parse($request->input('posisi', date('Y-m-d')));
        $rkaMonthColumn   = $this->rkaLookup->resolveMonthColumn($selectedDate);
        $rkaMonthLabel    = $this->rkaLookup->resolveMonthLabel($selectedDate);
        $defaultBranches  = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

        $selectedBranches = collect((array) $request->input('branch_office', []))
            ->map(fn ($b) => strtoupper(trim((string) $b)))
            ->filter()->values()->all();
        $selectedUkers = collect((array) $request->input('nama_uker', []))
            ->map(fn ($u) => strtoupper(trim((string) $u)))
            ->filter()
            ->reject(fn ($u) => $u === 'ALL UKER')
            ->values()->all();

        $isBranchFiltered = !empty($selectedBranches);
        $branches         = $isBranchFiltered ? $selectedBranches : $defaultBranches;
        $groupExpression  = $isBranchFiltered ? 'UPPER(TRIM(uker))' : 'UPPER(TRIM(kanca))';
        $groupLabel       = $isBranchFiltered ? 'UKER' : 'BRANCH OFFICE';
        $totalLabel       = $isBranchFiltered
            ? 'TOTAL ' . strtoupper(implode(', ', $selectedBranches))
            : 'TOTAL AREA 6';

        $newPayrollRka = $this->buildSplitRkaGroups(
            ['rekening' => ['mata_anggaran' => ['New Rekening Payroll Ritel']]],
            $rkaMonthColumn,
            $branches,
            $selectedUkers,
            $isBranchFiltered
        );

        $effectiveSnapshot = SargableDateFilter::apply(DB::table('performance_pis_per_produk'), 'posisi', '<=', $selectedDate->toDateString())
            ->max('posisi');

        $labels = $this->buildLabels($selectedDate);
        $labels['rka'] = 'RKA ' . $rkaMonthLabel;

        if (!$effectiveSnapshot) {
            return response()->json([
                'status'             => 'success',
                'labels'             => $labels,
                'effective_snapshot' => null,
                'data'               => [],
                'total'              => $this->buildEmptyTotal(),
            ]);
        }

        $currStart   = $selectedDate->copy()->startOfYear()->toDateString();
        $currEnd     = $selectedDate->copy()->endOfMonth()->toDateString();
        $prevEndDate = $selectedDate->copy()->subMonthNoOverflow()->endOfMonth();
        $prevStart   = $prevEndDate->copy()->startOfYear()->toDateString();
        $prevEnd     = $prevEndDate->toDateString();
        $yoyDate     = $selectedDate->copy()->subYearNoOverflow();
        $yoyStart    = $yoyDate->copy()->startOfYear()->toDateString();
        $yoyEnd      = $yoyDate->copy()->endOfMonth()->toDateString();
        $qualityMinimumBalance = self::QUALITY_PAYROLL_MINIMUM_BALANCE;

        $prevSnapshot = SargableDateFilter::apply(DB::table('performance_pis_per_produk'), 'posisi', '<=', $prevEnd)
            ->whereIn(DB::raw('UPPER(TRIM(kanca))'), array_map('strtoupper', $branches))
            ->when(!empty($selectedUkers), fn ($q) => $q->whereIn(DB::raw('UPPER(TRIM(uker))'), $selectedUkers))
            ->max('posisi') ?? $effectiveSnapshot;

        $yoySnapshot = SargableDateFilter::apply(DB::table('performance_pis_per_produk'), 'posisi', '<=', $yoyEnd)
            ->whereIn(DB::raw('UPPER(TRIM(kanca))'), array_map('strtoupper', $branches))
            ->when(!empty($selectedUkers), fn ($q) => $q->whereIn(DB::raw('UPPER(TRIM(uker))'), $selectedUkers))
            ->max('posisi') ?? $effectiveSnapshot;

        $rows = DB::table('performance_pis_per_produk')
            ->selectRaw("{$groupExpression} as branch")
            ->selectRaw('COUNT(CASE WHEN posisi = ? AND tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 END) as rekening_curr', [$effectiveSnapshot, $currStart, $currEnd])
            ->selectRaw('COUNT(CASE WHEN posisi = ? AND tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 END) as rekening_prev', [$prevSnapshot, $prevStart, $prevEnd])
            ->selectRaw('COUNT(CASE WHEN posisi = ? AND tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 END) as rekening_yoy_prev', [$yoySnapshot, $yoyStart, $yoyEnd])
            ->selectRaw('SUM(CASE WHEN posisi = ? AND tanggal_pembuatan_rekening BETWEEN ? AND ? THEN saldo_britama_kerjasama ELSE 0 END) as saldo_curr', [$effectiveSnapshot, $currStart, $currEnd])
            ->selectRaw('SUM(CASE WHEN posisi = ? AND tanggal_pembuatan_rekening BETWEEN ? AND ? THEN saldo_britama_kerjasama ELSE 0 END) as saldo_prev', [$prevSnapshot, $prevStart, $prevEnd])
            ->selectRaw('SUM(CASE WHEN posisi = ? AND tanggal_pembuatan_rekening BETWEEN ? AND ? THEN saldo_britama_kerjasama ELSE 0 END) as saldo_yoy_prev', [$yoySnapshot, $yoyStart, $yoyEnd])
            ->selectRaw('COUNT(CASE WHEN posisi = ? AND tanggal_pembuatan_rekening BETWEEN ? AND ? AND saldo_britama_kerjasama >= ? THEN 1 END) as kualitas_curr', [$effectiveSnapshot, $currStart, $currEnd, $qualityMinimumBalance])
            ->selectRaw('COUNT(CASE WHEN posisi = ? AND tanggal_pembuatan_rekening BETWEEN ? AND ? AND saldo_britama_kerjasama >= ? THEN 1 END) as kualitas_prev', [$prevSnapshot, $prevStart, $prevEnd, $qualityMinimumBalance])
            ->selectRaw('COUNT(CASE WHEN posisi = ? AND tanggal_pembuatan_rekening BETWEEN ? AND ? AND saldo_britama_kerjasama >= ? THEN 1 END) as kualitas_yoy_prev', [$yoySnapshot, $yoyStart, $yoyEnd, $qualityMinimumBalance])
            ->whereIn('posisi', array_unique([$effectiveSnapshot, $prevSnapshot, $yoySnapshot]))
            ->whereIn(DB::raw('UPPER(TRIM(kanca))'), array_map('strtoupper', $branches))
            ->when(!empty($selectedUkers), fn ($q) => $q->whereIn(DB::raw('UPPER(TRIM(uker))'), $selectedUkers))
            ->groupBy(DB::raw($groupExpression))
            ->get()
            ->keyBy('branch');

        $displayKeys = $isBranchFiltered
            ? $rows->keys()->sort()->values()->all()
            : $defaultBranches;

        $data           = [];
        $totalRekCurr   = 0;
        $totalRekPrev   = 0;
        $totalRekYoy    = 0;
        $totalSaldoCurr = 0.0;
        $totalSaldoPrev = 0.0;
        $totalSaldoYoy  = 0.0;
        $totalKualitasCurr = 0;
        $totalKualitasPrev = 0;
        $totalKualitasYoy = 0;
        $totalRekeningRka = 0.0;

        foreach ($displayKeys as $branch) {
            $row      = $rows->get(strtoupper($branch));
            $groupKey = strtoupper(trim((string) $branch));

            $rekeningCurr   = (int) ($row->rekening_curr ?? 0);
            $rekeningPrev   = (int) ($row->rekening_prev ?? 0);
            $rekeningYoyPrev = (int) ($row->rekening_yoy_prev ?? 0);
            $saldoCurr      = (float) ($row->saldo_curr ?? 0);
            $saldoPrev      = (float) ($row->saldo_prev ?? 0);
            $saldoYoyPrev   = (float) ($row->saldo_yoy_prev ?? 0);
            $kualitasCurr   = (int) ($row->kualitas_curr ?? 0);
            $kualitasPrev   = (int) ($row->kualitas_prev ?? 0);
            $kualitasYoyPrev = (int) ($row->kualitas_yoy_prev ?? 0);
            $rekeningRka    = round((float) ($newPayrollRka['rekening'][$groupKey] ?? 0), 2);

            $rekeningMetric = $this->calculateMetrics($rekeningCurr, $rekeningPrev, $rekeningYoyPrev);
            $rekeningMetric['rka'] = $rekeningRka;
            $rekeningMetric['penc_pct'] = $rekeningRka > 0 ? (($rekeningCurr / $rekeningRka) * 100) : null;

            $data[] = [
                'branch'   => strtoupper($branch),
                'rekening' => $rekeningMetric,
                'saldo'    => $this->calculateMetrics($saldoCurr, $saldoPrev, $saldoYoyPrev),
                'kualitas' => $this->calculateMetrics($kualitasCurr, $kualitasPrev, $kualitasYoyPrev),
            ];

            $totalRekCurr   += $rekeningCurr;
            $totalRekPrev   += $rekeningPrev;
            $totalRekYoy    += $rekeningYoyPrev;
            $totalSaldoCurr += $saldoCurr;
            $totalSaldoPrev += $saldoPrev;
            $totalSaldoYoy  += $saldoYoyPrev;
            $totalKualitasCurr += $kualitasCurr;
            $totalKualitasPrev += $kualitasPrev;
            $totalKualitasYoy += $kualitasYoyPrev;
            $totalRekeningRka += $rekeningRka;
        }

        $totalRekening = $this->calculateMetrics($totalRekCurr, $totalRekPrev, $totalRekYoy);
        $totalRekening['rka'] = round($totalRekeningRka, 2);
        $totalRekening['penc_pct'] = $totalRekeningRka > 0 ? (($totalRekCurr / $totalRekeningRka) * 100) : null;

        return response()->json([
            'status'             => 'success',
            'labels'             => $labels,
            'effective_snapshot' => Carbon::parse($effectiveSnapshot)->toDateString(),
            'group_label'        => $groupLabel,
            'data'               => $data,
            'total'              => [
                'branch'   => $totalLabel,
                'rekening' => $totalRekening,
                'saldo'    => $this->calculateMetrics($totalSaldoCurr, $totalSaldoPrev, $totalSaldoYoy),
                'kualitas' => $this->calculateMetrics($totalKualitasCurr, $totalKualitasPrev, $totalKualitasYoy),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    private function buildSplitRkaGroups(
        array $definitions,
        string $monthColumn,
        array $branches,
        array $selectedUkers,
        bool $isBranchFiltered
    ): array {
        $directBranches = [];
        $regionalPatterns = [];

        foreach ($branches as $branch) {
            $branchUpper = strtoupper(trim((string) $branch));
            if ($branchUpper === '') {
                continue;
            }

            if ($branchUpper === 'KC PONOROGO') {
                $directBranches[] = $branchUpper;
                continue;
            }

            $regionalPatterns[] = strtoupper(str_replace('KC ', '', $branchUpper));
        }

        $upperSelectedUkers = array_map('strtoupper', $selectedUkers);
        $groups = [];

        if ($directBranches !== []) {
            $directGroups = $this->rkaLookup->aggregateByGroup(
                $definitions,
                $monthColumn,
                $directBranches,
                $upperSelectedUkers,
                $isBranchFiltered ? 'uker' : 'kanca'
            );

            foreach ($definitions as $defKey => $definition) {
                $groups[$defKey] = $directGroups[$defKey] ?? [];
            }
        }

        foreach ($definitions as $defKey => $definition) {
            $groups[$defKey] ??= [];
        }

        if ($regionalPatterns !== []) {
            $regionalGroups = $this->rkaLookup->aggregateByGroupWithRegionalFilter(
                $definitions,
                $monthColumn,
                $regionalPatterns,
                null,
                $upperSelectedUkers,
                $isBranchFiltered ? 'uker' : 'region'
            );

            foreach ($definitions as $defKey => $definition) {
                foreach (($regionalGroups[$defKey] ?? []) as $groupKey => $value) {
                    $resultKey = $isBranchFiltered ? $groupKey : ('KC ' . $groupKey);
                    $groups[$defKey][$resultKey] = $value;
                }
            }
        }

        if (!$isBranchFiltered) {
            $this->fillMissingBranchRkaFromKancaFallback($groups, $definitions, $monthColumn, $branches);
        }

        return $groups;
    }

    private function fillMissingBranchRkaFromKancaFallback(array &$groups, array $definitions, string $monthColumn, array $branches): void
    {
        $fallbackGroups = $this->rkaLookup->aggregateByKancaWithSummaryFallback($definitions, $monthColumn, $branches);

        foreach (array_keys($definitions) as $defKey) {
            foreach ($branches as $branch) {
                $branchKey = strtoupper(trim((string) $branch));
                if ($branchKey === '') {
                    continue;
                }

                if (abs((float) ($groups[$defKey][$branchKey] ?? 0)) <= 0.0) {
                    $groups[$defKey][$branchKey] = (float) ($fallbackGroups[$defKey][$branchKey] ?? 0);
                }
            }
        }
    }

    private function calculateMetrics($curr, $prev, $yoyPrev): array
    {
        $curr    = (float) ($curr ?? 0);
        $prev    = (float) ($prev ?? 0);
        $yoyPrev = (float) ($yoyPrev ?? 0);
        $yoy     = $curr - $yoyPrev;
        $yoyPct  = $yoyPrev != 0.0 ? ($yoy / $yoyPrev) * 100 : null;

        return [
            'curr'     => $curr,
            'prev'     => $prev,
            'yoy_prev' => $yoyPrev,
            'yoy'      => $yoy,
            'yoy_pct'  => $yoyPct,
            'rka'      => null,
            'penc_pct' => null,
        ];
    }

    private function emptyMetric(): array
    {
        return [
            'curr'     => null,
            'prev'     => null,
            'yoy_prev' => null,
            'yoy'      => null,
            'yoy_pct'  => null,
            'rka'      => null,
            'penc_pct' => null,
        ];
    }

    private function buildEmptyTotal(): array
    {
        return [
            'branch'   => 'TOTAL AREA 6',
            'rekening' => $this->calculateMetrics(0, 0, 0),
            'saldo'    => $this->calculateMetrics(0, 0, 0),
            'kualitas' => $this->emptyMetric(),
        ];
    }

    private function buildLabels(Carbon $selectedDate): array
    {
        $curr = $selectedDate->copy();
        $prev = $selectedDate->copy()->subMonthNoOverflow();
        $yoy  = $selectedDate->copy()->subYearNoOverflow();

        return [
            'curr'     => $this->formatYearToDateLabel($curr),
            'prev'     => $this->formatYearToDateLabel($prev),
            'yoy_prev' => $this->formatYearToDateLabel($yoy),
            'rka'      => 'RKA ' . $curr->format('M') . ' - ' . $curr->format('y'),
        ];
    }

    private function formatYearToDateLabel(Carbon $date): string
    {
        return $date->copy()->startOfYear()->format('M') . '-' . $date->format('M y');
    }
}
