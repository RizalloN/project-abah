<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Support\ReportCacheVersion;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KinerjaNonPtpReportController extends Controller
{
    private const DAILY_LOAN_TABLE = 'daily_loan_dinamis';

    private const NOMINATIVE_PER_PAGE = 25;

    private const AREA_6_BRANCHES = [
        'KC Madiun',
        'KC Magetan',
        'KC Ngawi',
        'KC Ponorogo',
    ];

    private const AREA_6_BRANCH_KEYS = [
        'KC MADIUN',
        'KC MAGETAN',
        'KC NGAWI',
        'KC PONOROGO',
    ];

    private const SEGMENTS = [
        'ALL' => 'Semua Segmen',
        'SMALL' => 'Small',
        'CONSUMER' => 'Consumer',
        'MICRO' => 'Micro',
    ];

    private const VIEWS = [
        'rekap' => 'Rekap',
        'history' => 'History',
        'nominatif' => 'Nominatif',
    ];

    public function index(Request $request): View
    {
        $availablePeriods = $this->availablePeriods();
        $selectedPeriod = $this->resolveSelectedPeriod($availablePeriods, $request->input('periode'));
        $comparisonPeriod = $selectedPeriod ? $this->resolveComparisonPeriod($selectedPeriod) : null;
        $previousComparisonPeriod = $comparisonPeriod ? $this->resolveComparisonPeriod($comparisonPeriod) : null;
        $selectedBranch = $this->resolveBranch($request->input('cabang'));
        $selectedSegment = $this->resolveSegment($request->input('segmen'));
        $selectedView = $this->resolveView($request->input('view'));
        $perPage = self::NOMINATIVE_PER_PAGE;

        $monthlyRecap = collect();
        $summary = collect();
        $totals = $this->emptyTotals();
        $rows = $this->emptyPaginator($perPage);

        if ($selectedPeriod && $comparisonPeriod && $this->hasRequiredColumns()) {
            $monthlyRecap = $this->cachedMonthlyRecapRows($selectedPeriod, $selectedBranch, $selectedSegment);
            $summary = $this->cachedSummaryRows($selectedPeriod, $comparisonPeriod, $previousComparisonPeriod, $selectedBranch, $selectedSegment);
            $totals = $this->totalsFromSummary($summary);

            if ($selectedView === 'nominatif') {
                $rows = $this->nominativeRows($selectedPeriod, $comparisonPeriod, $previousComparisonPeriod, $selectedBranch, $selectedSegment, $perPage);
            }
        }

        return view('report.dashboard-pinjaman.kinerja-non-ptp', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodLabel' => $this->formatDateLong($selectedPeriod),
            'comparisonPeriod' => $comparisonPeriod,
            'comparisonPeriodLabel' => $this->formatDateLong($comparisonPeriod),
            'previousComparisonPeriod' => $previousComparisonPeriod,
            'previousComparisonPeriodLabel' => $this->formatDateLong($previousComparisonPeriod),
            'selectedBranch' => $selectedBranch,
            'selectedBranchLabel' => $selectedBranch === 'all' ? 'Area 6' : $selectedBranch,
            'branchOptions' => $this->branchOptions(),
            'segments' => self::SEGMENTS,
            'selectedSegment' => $selectedSegment,
            'selectedSegmentLabel' => self::SEGMENTS[$selectedSegment],
            'viewOptions' => self::VIEWS,
            'selectedView' => $selectedView,
            'selectedViewLabel' => self::VIEWS[$selectedView],
            'perPage' => $perPage,
            'monthlyRecap' => $monthlyRecap,
            'summary' => $summary,
            'summaryDimensionLabel' => $selectedBranch === 'all' ? 'Kantor Cabang' : 'Unit Kerja',
            'totals' => $totals,
            'rows' => $rows,
            'isReady' => $selectedPeriod !== null && $comparisonPeriod !== null && $this->hasRequiredColumns(),
        ]);
    }

    private function hasRequiredColumns(): bool
    {
        if (!Schema::hasTable(self::DAILY_LOAN_TABLE)) {
            return false;
        }

        $required = [
            'periode',
            'cabang1',
            'unit1',
            'nomor_rekening1',
            'nama_debitur1',
            'baki_debet1',
            'kolek',
            'tgl_bayar_terakhir',
            'next_pmt_date',
            'next_pmt_int_date',
            'tgl_realisasi',
            'segmen_kinerja',
            'freq_payment',
            'freq_int_payment',
            'jangka_waktu1',
        ];

        foreach ($required as $column) {
            if (!Schema::hasColumn(self::DAILY_LOAN_TABLE, $column)) {
                return false;
            }
        }

        return true;
    }

    private function availablePeriods(): Collection
    {
        if (!Schema::hasTable(self::DAILY_LOAN_TABLE) || !Schema::hasColumn(self::DAILY_LOAN_TABLE, 'periode')) {
            return collect();
        }

        return DB::table(self::DAILY_LOAN_TABLE)
            ->whereNotNull('periode')
            ->select('periode')
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode')
            ->map(fn ($period): string => Carbon::parse($period)->toDateString())
            ->values();
    }

    private function resolveSelectedPeriod(Collection $periods, mixed $requested): ?string
    {
        if ($periods->isEmpty()) {
            return null;
        }

        try {
            $requestedDate = $requested ? Carbon::parse((string) $requested)->toDateString() : null;
        } catch (\Throwable) {
            $requestedDate = null;
        }

        if ($requestedDate !== null) {
            return $periods->first(fn (string $period): bool => $period <= $requestedDate) ?? $periods->first();
        }

        return $periods->first();
    }

    private function resolveComparisonPeriod(string $selectedPeriod): ?string
    {
        $selected = Carbon::parse($selectedPeriod);
        $previousMonthStart = $selected->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $previousMonthEnd = $selected->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $period = DB::table(self::DAILY_LOAN_TABLE)
            ->whereBetween('periode', [$previousMonthStart, $previousMonthEnd])
            ->orderByDesc('periode')
            ->value('periode');

        return $period ? Carbon::parse($period)->toDateString() : null;
    }

    private function resolveBranch(mixed $branch): string
    {
        $branch = trim((string) $branch);

        return in_array($branch, self::AREA_6_BRANCHES, true) ? $branch : 'all';
    }

    private function resolveSegment(mixed $segment): string
    {
        $segment = strtoupper(trim((string) $segment));

        return array_key_exists($segment, self::SEGMENTS) ? $segment : 'ALL';
    }

    private function resolveView(mixed $view): string
    {
        $view = strtolower(trim((string) $view));

        return array_key_exists($view, self::VIEWS) ? $view : 'rekap';
    }

    private function branchOptions(): array
    {
        return ['all' => 'Area 6'] + array_combine(self::AREA_6_BRANCHES, self::AREA_6_BRANCHES);
    }

    private function summaryRows(string $period, string $comparisonPeriod, ?string $previousComparisonPeriod, string $branch, string $segment): Collection
    {
        $dimensionSql = $branch === 'all'
            ? "COALESCE(NULLIF(TRIM(d.cabang1), ''), '-')"
            : "COALESCE(NULLIF(TRIM(d.unit1), ''), '-')";

        $currentPositions = $this->positionRows($period, $branch, $segment, $dimensionSql)->keyBy('dimension_label');
        $previousPositions = $this->positionRows($comparisonPeriod, $branch, $segment, $dimensionSql)->keyBy('dimension_label');
        $transitions = $this->transitionRows($period, $comparisonPeriod, $previousComparisonPeriod, $branch, $segment, $dimensionSql)
            ->keyBy('dimension_label');

        return $currentPositions
            ->keys()
            ->merge($previousPositions->keys())
            ->merge($transitions->keys())
            ->unique()
            ->map(function (string $label) use ($currentPositions, $previousPositions, $transitions): object {
                $current = $currentPositions->get($label);
                $previous = $previousPositions->get($label);
                $transition = $transitions->get($label);

                return (object) [
                    'dimension_label' => $label,
                    'rekening_count' => (int) ($current->rekening_count ?? 0),
                    'baki_debet_total' => (float) ($current->baki_debet_total ?? 0),
                    'current_ptp_count' => (int) ($current->current_ptp_count ?? 0),
                    'current_ptp_baki' => (float) ($current->current_ptp_baki ?? 0),
                    'current_non_ptp_count' => (int) ($current->current_non_ptp_count ?? 0),
                    'current_non_ptp_baki' => (float) ($current->current_non_ptp_baki ?? 0),
                    'previous_ptp_count' => (int) ($previous->current_ptp_count ?? 0),
                    'previous_ptp_baki' => (float) ($previous->current_ptp_baki ?? 0),
                    'ptp_to_non_count' => (int) ($transition->ptp_to_non_count ?? 0),
                    'ptp_to_non_baki' => (float) ($transition->ptp_to_non_baki ?? 0),
                    'non_to_ptp_count' => (int) ($transition->non_to_ptp_count ?? 0),
                    'non_to_ptp_baki' => (float) ($transition->non_to_ptp_baki ?? 0),
                ];
            })
            ->sortBy([
                ['baki_debet_total', 'desc'],
                ['dimension_label', 'asc'],
            ])
            ->values();
    }

    private function positionRows(string $period, string $branch, string $segment, string $dimensionSql): Collection
    {
        $query = DB::table(self::DAILY_LOAN_TABLE . ' as d')
            ->where('d.periode', $period)
            ->whereRaw($this->activePtpScopeSql('d', $period));

        if ($segment !== 'ALL') {
            $query->where('d.segmen_kinerja', $segment);
        }

        $this->applyAreaBranchFilter($query, 'd');

        if ($branch !== 'all') {
            $this->applySelectedBranchFilter($query, 'd', $branch);
        }

        return $query
            ->selectRaw("{$dimensionSql} as dimension_label")
            ->selectRaw('COUNT(DISTINCT d.nomor_rekening1) as rekening_count')
            ->selectRaw('COALESCE(SUM(COALESCE(d.baki_debet1, 0)), 0) as baki_debet_total')
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$this->ptpStatusSql('d', $period)} = 'PTP' THEN d.nomor_rekening1 END) as current_ptp_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$this->ptpStatusSql('d', $period)} = 'PTP' THEN COALESCE(d.baki_debet1, 0) ELSE 0 END), 0) as current_ptp_baki")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$this->ptpStatusSql('d', $period)} = 'NON PTP' THEN d.nomor_rekening1 END) as current_non_ptp_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$this->ptpStatusSql('d', $period)} = 'NON PTP' THEN COALESCE(d.baki_debet1, 0) ELSE 0 END), 0) as current_non_ptp_baki")
            ->groupBy('dimension_label')
            ->get();
    }

    private function transitionRows(string $period, string $comparisonPeriod, ?string $previousComparisonPeriod, string $branch, string $segment, string $dimensionSql): Collection
    {
        $history = $this->historyStatusQuery($period, $comparisonPeriod, $previousComparisonPeriod, $branch, $segment)
            ->selectRaw("{$dimensionSql} as dimension_label");

        return DB::query()
            ->fromSub($history, 'h')
            ->selectRaw('h.dimension_label')
            ->selectRaw("COUNT(DISTINCT CASE WHEN h.status_bulan_sebelumnya = 'PTP' AND h.status_periode_ini = 'NON PTP' THEN h.nomor_rekening END) as ptp_to_non_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN h.status_bulan_sebelumnya = 'PTP' AND h.status_periode_ini = 'NON PTP' THEN COALESCE(h.baki_debet1, 0) ELSE 0 END), 0) as ptp_to_non_baki")
            ->selectRaw("COUNT(DISTINCT CASE WHEN h.status_bulan_sebelumnya = 'NON PTP' AND h.status_periode_ini = 'PTP' THEN h.nomor_rekening END) as non_to_ptp_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN h.status_bulan_sebelumnya = 'NON PTP' AND h.status_periode_ini = 'PTP' THEN COALESCE(h.baki_debet1, 0) ELSE 0 END), 0) as non_to_ptp_baki")
            ->groupBy('dimension_label')
            ->get();
    }

    private function cachedSummaryRows(string $period, string $comparisonPeriod, ?string $previousComparisonPeriod, string $branch, string $segment): Collection
    {
        $cacheKey = 'histori_ptp_deb:summary:v6-excel-ptp-logic:' . md5(json_encode([
            $period,
            $comparisonPeriod,
            $previousComparisonPeriod,
            $branch,
            $segment,
            ReportCacheVersion::composite(['pinjaman']),
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(10), fn (): Collection => $this->summaryRows(
            $period,
            $comparisonPeriod,
            $previousComparisonPeriod,
            $branch,
            $segment
        ));
    }

    private function monthlyRecapRows(string $selectedPeriod, string $branch, string $segment): Collection
    {
        $selected = Carbon::parse($selectedPeriod);
        $yearStart = $selected->copy()->startOfYear();
        $monthCursor = $yearStart->copy();
        $rows = collect();

        while ($monthCursor->lte($selected)) {
            $period = $this->latestPeriodInMonth($monthCursor, $selected);
            $comparisonPeriod = $period ? $this->resolveComparisonPeriod($period) : null;
            $previousComparisonPeriod = $comparisonPeriod ? $this->resolveComparisonPeriod($comparisonPeriod) : null;

            if ($period && $comparisonPeriod) {
                $periodRows = $this->monthlyRecapForPeriod($period, $comparisonPeriod, $previousComparisonPeriod, $branch, $segment);
                $rows = $rows->merge($periodRows);
            }

            $monthCursor->addMonthNoOverflow();
        }

        return $rows
            ->sortBy([
                ['branch_label', 'asc'],
                ['period', 'asc'],
            ])
            ->values();
    }

    private function cachedMonthlyRecapRows(string $selectedPeriod, string $branch, string $segment): Collection
    {
        $periodPlan = $this->monthlyPeriodPlan($selectedPeriod);
        $sourcePeriods = collect($periodPlan)
            ->flatMap(fn (array $plan): array => array_filter([
                $plan['period'] ?? null,
                $plan['comparison_period'] ?? null,
                $plan['previous_comparison_period'] ?? null,
            ]))
            ->unique()
            ->values()
            ->all();
        $cacheKey = 'histori_ptp_deb:monthly:v6-excel-ptp-logic:' . md5(json_encode([
            $selectedPeriod,
            $branch,
            $segment,
            $periodPlan,
            $sourcePeriods,
            ReportCacheVersion::composite(['pinjaman']),
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($periodPlan, $branch, $segment): Collection {
            $rows = collect();

            foreach ($periodPlan as $plan) {
                $rows = $rows->merge($this->monthlyRecapForPeriod(
                    $plan['period'],
                    $plan['comparison_period'],
                    $plan['previous_comparison_period'],
                    $branch,
                    $segment
                ));
            }

            return $rows
                ->sortBy([
                    ['branch_label', 'asc'],
                    ['period', 'asc'],
                ])
                ->values();
        });
    }

    private function monthlyPeriodPlan(string $selectedPeriod): array
    {
        $selected = Carbon::parse($selectedPeriod);
        $monthCursor = $selected->copy()->startOfYear();
        $plan = [];

        while ($monthCursor->lte($selected)) {
            $period = $this->latestPeriodInMonth($monthCursor, $selected);
            $comparisonPeriod = $period ? $this->resolveComparisonPeriod($period) : null;
            $previousComparisonPeriod = $comparisonPeriod ? $this->resolveComparisonPeriod($comparisonPeriod) : null;

            if ($period && $comparisonPeriod) {
                $plan[] = [
                    'period' => $period,
                    'comparison_period' => $comparisonPeriod,
                    'previous_comparison_period' => $previousComparisonPeriod,
                ];
            }

            $monthCursor->addMonthNoOverflow();
        }

        return $plan;
    }

    private function latestPeriodInMonth(Carbon $month, Carbon $selectedPeriod): ?string
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = min($month->copy()->endOfMonth()->toDateString(), $selectedPeriod->toDateString());

        $period = DB::table(self::DAILY_LOAN_TABLE)
            ->whereBetween('periode', [$start, $end])
            ->orderByDesc('periode')
            ->value('periode');

        return $period ? Carbon::parse($period)->toDateString() : null;
    }

    private function monthlyRecapForPeriod(string $period, string $comparisonPeriod, ?string $previousComparisonPeriod, string $branch, string $segment): Collection
    {
        if ($branch === 'all') {
            return collect(self::AREA_6_BRANCHES)
                ->flatMap(fn (string $scopeBranch): Collection => $this->monthlyRecapForPeriod(
                    $period,
                    $comparisonPeriod,
                    $previousComparisonPeriod,
                    $scopeBranch,
                    $segment
                ))
                ->sortBy('branch_label')
                ->values();
        }

        $dimensionSql = "COALESCE(NULLIF(TRIM(d.cabang1), ''), '-')";
        $current = $this->positionRows($period, $branch, $segment, $dimensionSql)->first();
        $previous = $this->positionRows($comparisonPeriod, $branch, $segment, $dimensionSql)->first();
        $transition = $this->transitionRows($period, $comparisonPeriod, $previousComparisonPeriod, $branch, $segment, $dimensionSql)->first();
        $branchLabel = $current->dimension_label ?? $previous->dimension_label ?? $transition->dimension_label ?? $branch;

        return collect([(object) [
            'branch_label' => $branchLabel,
            'period' => $period,
            'comparison_period' => $comparisonPeriod,
            'previous_comparison_period' => $previousComparisonPeriod,
            'previous_ptp_count' => (int) ($previous->current_ptp_count ?? 0),
            'previous_ptp_baki' => (float) ($previous->current_ptp_baki ?? 0),
            'current_ptp_count' => (int) ($current->current_ptp_count ?? 0),
            'current_ptp_baki' => (float) ($current->current_ptp_baki ?? 0),
            'ptp_to_non_count' => (int) ($transition->ptp_to_non_count ?? 0),
            'ptp_to_non_baki' => (float) ($transition->ptp_to_non_baki ?? 0),
            'non_to_ptp_count' => (int) ($transition->non_to_ptp_count ?? 0),
            'non_to_ptp_baki' => (float) ($transition->non_to_ptp_baki ?? 0),
        ]]);
    }

    private function totalsFromSummary(Collection $summary): array
    {
        return [
            'rekening_count' => (int) $summary->sum(fn ($row): int => (int) ($row->rekening_count ?? 0)),
            'baki_debet_total' => (float) $summary->sum(fn ($row): float => (float) ($row->baki_debet_total ?? 0)),
            'current_ptp_count' => (int) $summary->sum(fn ($row): int => (int) ($row->current_ptp_count ?? 0)),
            'current_ptp_baki' => (float) $summary->sum(fn ($row): float => (float) ($row->current_ptp_baki ?? 0)),
            'current_non_ptp_count' => (int) $summary->sum(fn ($row): int => (int) ($row->current_non_ptp_count ?? 0)),
            'current_non_ptp_baki' => (float) $summary->sum(fn ($row): float => (float) ($row->current_non_ptp_baki ?? 0)),
            'previous_ptp_count' => (int) $summary->sum(fn ($row): int => (int) ($row->previous_ptp_count ?? 0)),
            'previous_ptp_baki' => (float) $summary->sum(fn ($row): float => (float) ($row->previous_ptp_baki ?? 0)),
            'ptp_to_non_count' => (int) $summary->sum(fn ($row): int => (int) ($row->ptp_to_non_count ?? 0)),
            'ptp_to_non_baki' => (float) $summary->sum(fn ($row): float => (float) ($row->ptp_to_non_baki ?? 0)),
            'non_to_ptp_count' => (int) $summary->sum(fn ($row): int => (int) ($row->non_to_ptp_count ?? 0)),
            'non_to_ptp_baki' => (float) $summary->sum(fn ($row): float => (float) ($row->non_to_ptp_baki ?? 0)),
        ];
    }

    private function nominativeRows(string $period, string $comparisonPeriod, ?string $previousComparisonPeriod, string $branch, string $segment, int $perPage): Paginator
    {
        $earliestDueSql = $this->earliestDueSql('d', $period);
        $lastPaymentSql = $this->validDateSql('d.tgl_bayar_terakhir');
        $previousDueSql = $this->earliestDueSql('p', $comparisonPeriod);
        $previousLastPaymentSql = $this->validDateSql('p.tgl_bayar_terakhir');
        $repaymentPatternSql = $this->repaymentPatternSql('d');
        $history = $this->historyStatusQuery($period, $comparisonPeriod, $previousComparisonPeriod, $branch, $segment)
            ->selectRaw('d.periode')
            ->selectRaw("COALESCE(NULLIF(TRIM(d.cabang1), ''), '-') as cabang1")
            ->selectRaw("COALESCE(NULLIF(TRIM(d.unit1), ''), '-') as unit1")
            ->selectRaw("COALESCE(NULLIF(TRIM(d.nama_debitur1), ''), '-') as nama_debitur1")
            ->selectRaw("COALESCE(NULLIF(TRIM(d.kolek), ''), '-') as kolek")
            ->selectRaw('d.tgl_bayar_terakhir')
            ->selectRaw("{$earliestDueSql} as npd_npid")
            ->selectRaw("{$earliestDueSql} as tanggal_bayar_seharusnya")
            ->selectRaw("{$repaymentPatternSql} as pola_angsuran")
            ->selectRaw("{$this->excelStatusSql('d', $period)} as keterangan_excel")
            ->selectRaw('p.periode as periode_sebelumnya')
            ->selectRaw('p.tgl_bayar_terakhir as tgl_bayar_terakhir_sebelumnya')
            ->selectRaw("{$previousDueSql} as npd_npid_sebelumnya")
            ->selectRaw("{$previousDueSql} as tanggal_bayar_seharusnya_sebelumnya")
            ->selectRaw("CASE
                WHEN {$lastPaymentSql} IS NULL THEN 'Belum ada tanggal bayar valid'
                WHEN DAY({$lastPaymentSql}) > DAY({$earliestDueSql}) THEN 'Telat membayar'
                ELSE 'Tepat / tidak telat bayar'
            END as detail_periode_ini")
            ->selectRaw("CASE
                WHEN {$previousLastPaymentSql} IS NULL THEN 'Belum ada tanggal bayar valid'
                WHEN DAY({$previousLastPaymentSql}) > DAY({$previousDueSql}) THEN 'Telat membayar'
                ELSE 'Tepat / tidak telat bayar'
            END as detail_bulan_sebelumnya");

        return DB::query()
            ->fromSub($history, 'h')
            ->selectRaw('h.*')
            ->selectRaw("CASE
                WHEN h.status_bulan_sebelumnya = 'PTP' AND h.status_periode_ini = 'NON PTP' THEN 'PTP -> NON PTP'
                WHEN h.status_bulan_sebelumnya = 'NON PTP' AND h.status_periode_ini = 'PTP' THEN 'NON PTP -> PTP'
                WHEN h.status_bulan_sebelumnya = 'PTP' AND h.status_periode_ini = 'PTP' THEN 'Tetap PTP'
                WHEN h.status_bulan_sebelumnya = 'NON PTP' AND h.status_periode_ini = 'NON PTP' THEN 'Tetap NON PTP'
                ELSE 'Belum ada histori lengkap'
            END as keterangan")
            ->orderBy('h.cabang1')
            ->orderBy('h.unit1')
            ->orderByRaw("CASE
                WHEN h.status_bulan_sebelumnya = 'PTP' AND h.status_periode_ini = 'NON PTP' THEN 1
                WHEN h.status_bulan_sebelumnya = 'NON PTP' AND h.status_periode_ini = 'PTP' THEN 2
                WHEN h.status_periode_ini = 'NON PTP' THEN 3
                ELSE 4
            END")
            ->orderByDesc('h.baki_debet1')
            ->simplePaginate($perPage)
            ->withQueryString();
    }

    private function historyStatusQuery(string $period, string $comparisonPeriod, ?string $previousComparisonPeriod, string $branch, string $segment)
    {
        return $this->baseHistoryQuery($period, $comparisonPeriod, $previousComparisonPeriod, $branch, $segment)
            ->selectRaw("COALESCE(NULLIF(TRIM(d.nomor_rekening1), ''), '-') as nomor_rekening")
            ->selectRaw('COALESCE(d.baki_debet1, 0) as baki_debet1')
            ->selectRaw("{$this->ptpStatusSql('p', $comparisonPeriod)} as status_bulan_sebelumnya")
            ->selectRaw("{$this->ptpStatusSql('d', $period)} as status_periode_ini");
    }

    private function baseHistoryQuery(string $period, string $comparisonPeriod, ?string $previousComparisonPeriod, string $branch, string $segment)
    {
        $previousComparisonPeriod ??= '1900-01-01';

        $query = DB::table(DB::raw(self::DAILY_LOAN_TABLE . ' as d FORCE INDEX (idx_dld_nonptp_monthly_lookup)'))
            ->join(DB::raw(self::DAILY_LOAN_TABLE . ' as p FORCE INDEX (idx_loan_periode_rek)'), function ($join) use ($comparisonPeriod): void {
                $join
                    ->on('p.nomor_rekening1', '=', 'd.nomor_rekening1')
            ->where('p.periode', '=', $comparisonPeriod);
            })
            ->where('d.periode', $period)
            ->whereRaw("{$this->repaymentPatternSql('p')} = 'BULANAN'")
            ->whereRaw($this->activePtpScopeSql('d', $period));

        if ($segment !== 'ALL') {
            $query
                ->where('d.segmen_kinerja', $segment)
                ->where('p.segmen_kinerja', $segment);
        }

        $this->applyAreaBranchFilter($query, 'd');
        $this->applyAreaBranchFilter($query, 'p');

        if ($branch !== 'all') {
            $this->applySelectedBranchFilter($query, 'd', $branch);
        }

        return $query;
    }

    private function applyAreaBranchFilter($query, string $alias): void
    {
        if (Schema::hasColumn(self::DAILY_LOAN_TABLE, 'cabang_normalized')) {
            $query->whereIn("{$alias}.cabang_normalized", self::AREA_6_BRANCH_KEYS);

            return;
        }

        $query->whereIn("{$alias}.cabang1", self::AREA_6_BRANCHES);
    }

    private function applySelectedBranchFilter($query, string $alias, string $branch): void
    {
        if (Schema::hasColumn(self::DAILY_LOAN_TABLE, 'cabang_normalized')) {
            $query->where("{$alias}.cabang_normalized", strtoupper($branch));

            return;
        }

        $query->where("{$alias}.cabang1", $branch);
    }

    private function ptpStatusSql(string $alias, string $period, ?string $comparisonAlias = null): string
    {
        $lastPaymentSql = $this->validDateSql("{$alias}.tgl_bayar_terakhir");
        $dueSql = $this->earliestDueSql($alias, $period);
        $scopeSql = $this->activePtpScopeSql($alias, $period);

        if ($comparisonAlias !== null) {
            $scopeSql .= " AND {$comparisonAlias}.nomor_rekening1 IS NOT NULL";
        }

        return "CASE
            WHEN {$scopeSql} THEN
                CASE WHEN DAY({$lastPaymentSql}) > DAY({$dueSql})
                    THEN 'NON PTP'
                    ELSE 'PTP'
                END
            ELSE 'TIDAK TERHITUNG'
        END";
    }

    private function excelStatusSql(string $alias, string $period): string
    {
        $lastPaymentSql = $this->validDateSql("{$alias}.tgl_bayar_terakhir");
        $dueSql = $this->earliestDueSql($alias, $period);
        $monthStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $monthEnd = Carbon::parse($period)->endOfMonth()->toDateString();

        return "CASE
            WHEN {$alias}.tgl_realisasi IS NOT NULL
                AND DATE({$alias}.tgl_realisasi) BETWEEN '{$monthStart}' AND '{$monthEnd}'
                THEN 'REALISASI BULAN INI'
            WHEN CAST(TRIM(COALESCE({$alias}.kolek, '')) AS UNSIGNED) <> 1 THEN 'NON LANCAR'
            WHEN {$this->repaymentPatternSql($alias)} <> 'BULANAN' THEN 'NON BULANAN'
            WHEN DAY({$lastPaymentSql}) > DAY({$dueSql}) THEN 'NON PTP'
            ELSE 'PTP'
        END";
    }

    private function activePtpScopeSql(string $alias, string $period): string
    {
        $monthStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $monthEnd = Carbon::parse($period)->endOfMonth()->toDateString();

        return "CAST(TRIM(COALESCE({$alias}.kolek, '')) AS UNSIGNED) = 1
            AND {$alias}.nomor_rekening1 IS NOT NULL
            AND TRIM(COALESCE({$alias}.nomor_rekening1, '')) <> ''
            AND COALESCE({$alias}.baki_debet1, 0) > 0
            AND {$this->repaymentPatternSql($alias)} = 'BULANAN'
            AND ({$alias}.tgl_realisasi IS NULL OR DATE({$alias}.tgl_realisasi) NOT BETWEEN '{$monthStart}' AND '{$monthEnd}')";
    }

    private function validDateSql(string $column): string
    {
        return "CASE WHEN {$column} IS NULL OR {$column} <= '1991-01-01' THEN NULL ELSE {$column} END";
    }

    private function earliestDueSql(string $alias, string $period): string
    {
        $nextPrincipal = $this->validDateSql("{$alias}.next_pmt_date");
        $nextInterest = $this->validDateSql("{$alias}.next_pmt_int_date");

        return "CASE
            WHEN {$nextPrincipal} IS NULL THEN {$nextInterest}
            WHEN {$nextInterest} IS NULL THEN {$nextPrincipal}
            WHEN {$nextPrincipal} <= {$nextInterest} THEN {$nextPrincipal}
            ELSE {$nextInterest}
        END";
    }

    private function repaymentPatternSql(string $alias): string
    {
        return "CASE
            WHEN CAST(COALESCE({$alias}.freq_payment, 0) AS UNSIGNED) > 0
                AND CAST(COALESCE({$alias}.freq_payment, 0) AS UNSIGNED) = CAST(COALESCE({$alias}.freq_int_payment, 0) AS UNSIGNED) THEN 'BULANAN'
            WHEN CAST(COALESCE({$alias}.freq_payment, 0) AS UNSIGNED) = CAST(NULLIF(REGEXP_REPLACE(COALESCE({$alias}.jangka_waktu1, ''), '[^0-9]', ''), '') AS UNSIGNED) THEN '1 X ANGSURAN'
            ELSE 'PERIODIK'
        END";
    }

    private function emptyTotals(): array
    {
        return [
            'rekening_count' => 0,
            'baki_debet_total' => 0.0,
            'current_ptp_count' => 0,
            'current_ptp_baki' => 0.0,
            'current_non_ptp_count' => 0,
            'current_non_ptp_baki' => 0.0,
            'previous_ptp_count' => 0,
            'previous_ptp_baki' => 0.0,
            'ptp_to_non_count' => 0,
            'ptp_to_non_baki' => 0.0,
            'non_to_ptp_count' => 0,
            'non_to_ptp_baki' => 0.0,
        ];
    }

    private function emptyPaginator(int $perPage): Paginator
    {
        return new \Illuminate\Pagination\Paginator([], $perPage);
    }

    private function formatDateLong(?string $date): string
    {
        return $date ? Carbon::parse($date)->locale('id')->translatedFormat('d F Y') : '-';
    }
}
