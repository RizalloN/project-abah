<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Support\RkaLookupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KinerjaKonsumerReportController extends Controller
{
    private const DEFAULT_TITLE = 'OutstandingKonsumer - Briguna & KPR';
    private const SEGMENT_DEFAULT = 'ALL';

    private const AVAILABLE_SEGMENTS = [
        'ALL' => 'Semua Segmen',
        'BRIGUNA-KONSUMER' => 'Briguna-Konsumer',
        'KPR' => 'KPR',
    ];

    public function __construct(
        private readonly RkaLookupService $rkaLookup
    ) {}

    public function index(Request $request): View
    {
        $availablePeriods = $this->fetchAvailablePeriods();
        $availableCabangs = $this->fetchAvailableCabangs();
        $selectedSegmen = $this->resolveSelectedSegmen($request->input('segmen'));
        $selectedPeriod = $this->resolveSelectedPeriod($availablePeriods, $request->input('periode'))
            ?? $availablePeriods->first()
            ?? Carbon::now()->toDateString();
        $selectedCabang = $this->resolveSelectedCabang($availableCabangs, $request->input('cabang1'));

        $currentDate = Carbon::parse($selectedPeriod);
        $previousDayPeriod = $this->resolveClosestPeriod(
            $availablePeriods,
            $currentDate->copy()->subDay()
        ) ?? $selectedPeriod;
        $mtdPeriod = $this->resolveClosestPeriod(
            $availablePeriods,
            $currentDate->copy()->subMonthNoOverflow()->endOfMonth()
        ) ?? $selectedPeriod;
        $ytdPeriod = $this->resolveClosestPeriod(
            $availablePeriods,
            $currentDate->copy()->subYearNoOverflow()->endOfYear()
        ) ?? $selectedPeriod;

        $branchRows = $this->fetchBranchRows($selectedPeriod, $previousDayPeriod, $mtdPeriod, $ytdPeriod, $selectedCabang, $selectedSegmen);
        $nextMonth = $currentDate->copy()->addMonthNoOverflow();

        return view('report.kinerja-konsumer', [
            'title' => self::DEFAULT_TITLE,
            'availableSegments' => self::AVAILABLE_SEGMENTS,
            'availablePeriods' => $availablePeriods,
            'latestPeriodLabel' => $availablePeriods->first()
                ? Carbon::parse($availablePeriods->first())->translatedFormat('d M Y')
                : '-',
            'availableCabangs' => $availableCabangs,
            'selectedSegmen' => $selectedSegmen,
            'selectedSegmenLabel' => self::AVAILABLE_SEGMENTS[$selectedSegmen ?? self::SEGMENT_DEFAULT] ?? self::AVAILABLE_SEGMENTS[self::SEGMENT_DEFAULT],
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodLabel' => $currentDate->translatedFormat('d M Y'),
            'selectedPeriodShortLabel' => $currentDate->translatedFormat('d M y'),
            'selectedCabang' => $selectedCabang,
            'selectedCabangLabel' => $selectedCabang !== null ? $selectedCabang : 'Semua Cabang',
            'previousDayPeriod' => $previousDayPeriod,
            'previousDayLabel' => Carbon::parse($previousDayPeriod)->translatedFormat('d M y'),
            'mtdPeriod' => $mtdPeriod,
            'mtdLabel' => Carbon::parse($mtdPeriod)->translatedFormat('d M Y'),
            'ytdPeriod' => $ytdPeriod,
            'ytdLabel' => Carbon::parse($ytdPeriod)->translatedFormat('d M Y'),
            'currentMonthLabel' => $currentDate->format('M-y'),
            'nextMonthLabel' => $nextMonth->format('M-y'),
            'rows' => $branchRows['rows'],
            'total' => $branchRows['total'],
        ]);
    }

    private function fetchAvailablePeriods(): Collection
    {
        $productColumn = $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['produk_dashboard', 'produk'],
            'produk_dashboard'
        );

        return DB::table('daily_loan_dinamis')
            ->whereRaw("UPPER(TRIM(COALESCE(segmen_dashboard, ''))) = 'CONSUMER'")
            ->whereRaw("REPLACE(UPPER(TRIM(COALESCE({$productColumn}, ''))), '-', ' ') IN ('BRIGUNA KONSUMER', 'KPR')")
            ->select('periode')
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode')
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->values();
    }

    private function fetchAvailableCabangs(): Collection
    {
        $cabangColumn = $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['cabang1', 'cabang'],
            'cabang1'
        );
        $productColumn = $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['produk_dashboard', 'produk'],
            'produk_dashboard'
        );

        return DB::table('daily_loan_dinamis')
            ->whereRaw("UPPER(TRIM(COALESCE(segmen_dashboard, ''))) = 'CONSUMER'")
            ->whereRaw("REPLACE(UPPER(TRIM(COALESCE({$productColumn}, ''))), '-', ' ') IN ('BRIGUNA KONSUMER', 'KPR')")
            ->whereRaw("TRIM(COALESCE({$cabangColumn}, '')) <> ''")
            ->selectRaw("UPPER(TRIM(COALESCE({$cabangColumn}, ''))) as cabang")
            ->distinct()
            ->orderBy('cabang')
            ->pluck('cabang')
            ->filter()
            ->values();
    }

    private function resolveSelectedPeriod(Collection $periods, ?string $requestedPeriod): ?string
    {
        $target = $this->normalizeDate($requestedPeriod);

        if ($target !== null) {
            $match = $this->resolveClosestPeriod($periods, Carbon::parse($target));
            if ($match !== null) {
                return $match;
            }
        }

        return $periods->first();
    }

    private function resolveSelectedCabang(Collection $cabangs, ?string $requestedCabang): ?string
    {
        $value = strtoupper(trim((string) $requestedCabang));

        if ($value === '' || in_array($value, ['SEMUA CABANG', 'ALL', 'ALL CABANG'], true)) {
            return null;
        }

        return $cabangs->contains($value) ? $value : null;
    }

    private function resolveSelectedSegmen(?string $requestedSegmen): string
    {
        $value = strtoupper(trim((string) $requestedSegmen));
        $value = $value !== '' ? $value : self::SEGMENT_DEFAULT;

        return array_key_exists($value, self::AVAILABLE_SEGMENTS) ? $value : self::SEGMENT_DEFAULT;
    }

    private function resolveClosestPeriod(Collection $periods, Carbon $target): ?string
    {
        $targetDate = $target->toDateString();

        return $periods
            ->first(function (string $period) use ($targetDate) {
                return $period <= $targetDate;
            });
    }

    private function fetchBranchRows(string $selectedPeriod, string $previousDayPeriod, string $mtdPeriod, string $ytdPeriod, ?string $selectedCabang = null, string $selectedSegmen = self::SEGMENT_DEFAULT): array
    {
        $periods = array_values(array_unique(array_filter([
            $selectedPeriod,
            $previousDayPeriod,
            $mtdPeriod,
            $ytdPeriod,
        ])));

        $rmColumn = $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['pn_pengelola1', 'pn_pengelola', 'rm'],
            'pn_pengelola1'
        );
        $cabangColumn = $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['cabang1', 'cabang'],
            'cabang1'
        );
        $productColumn = $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['produk_dashboard', 'produk'],
            'produk_dashboard'
        );

        $rows = DB::table('daily_loan_dinamis')
            ->selectRaw("UPPER(TRIM(COALESCE({$rmColumn}, ''))) as rm")
            ->selectRaw("UPPER(TRIM(COALESCE({$cabangColumn}, ''))) as cabang")
            ->selectRaw("UPPER(TRIM(COALESCE({$productColumn}, ''))) as produk_raw")
            ->selectRaw("SUM(CASE WHEN DATE(periode) = ? THEN COALESCE(baki_debet1, 0) ELSE 0 END) as curr", [$selectedPeriod])
            ->selectRaw("SUM(CASE WHEN DATE(periode) = ? THEN COALESCE(baki_debet1, 0) ELSE 0 END) as prev_day", [$previousDayPeriod])
            ->selectRaw("SUM(CASE WHEN DATE(periode) = ? THEN COALESCE(baki_debet1, 0) ELSE 0 END) as mtd", [$mtdPeriod])
            ->selectRaw("SUM(CASE WHEN DATE(periode) = ? THEN COALESCE(baki_debet1, 0) ELSE 0 END) as ytd", [$ytdPeriod])
            ->whereRaw("UPPER(TRIM(COALESCE(segmen_dashboard, ''))) = 'CONSUMER'")
            ->whereRaw("REPLACE(UPPER(TRIM(COALESCE({$productColumn}, ''))), '-', ' ') IN ('BRIGUNA KONSUMER', 'KPR')")
            ->whereRaw("TRIM(COALESCE({$rmColumn}, '')) <> ''")
            ->when($selectedSegmen !== self::SEGMENT_DEFAULT, function ($query) use ($productColumn, $selectedSegmen) {
                if ($selectedSegmen === 'BRIGUNA-KONSUMER') {
                    $query->whereRaw("REPLACE(UPPER(TRIM(COALESCE({$productColumn}, ''))), '-', ' ') = 'BRIGUNA KONSUMER'");
                } elseif ($selectedSegmen === 'KPR') {
                    $query->whereRaw("REPLACE(UPPER(TRIM(COALESCE({$productColumn}, ''))), '-', ' ') = 'KPR'");
                }
            })
            ->when($selectedCabang !== null, function ($query) use ($cabangColumn, $selectedCabang) {
                $query->whereRaw("UPPER(TRIM(COALESCE({$cabangColumn}, ''))) = ?", [$selectedCabang]);
            })
            ->where(function ($query) use ($periods) {
                foreach ($periods as $period) {
                    $query->orWhereDate('periode', $period);
                }
            })
            ->groupByRaw("UPPER(TRIM(COALESCE({$rmColumn}, ''))), UPPER(TRIM(COALESCE({$cabangColumn}, ''))), UPPER(TRIM(COALESCE({$productColumn}, '')))")
            ->orderBy('cabang')
            ->orderBy('rm')
            ->orderBy('produk_raw')
            ->get();

        $currentMonth = Carbon::parse($selectedPeriod);
        $nextMonth = $currentMonth->copy()->addMonthNoOverflow();
        $currentMonthColumn = $this->rkaLookup->resolveMonthColumn($currentMonth);
        $nextMonthColumn = $this->rkaLookup->resolveMonthColumn($nextMonth);
        $currentYear = (int) $currentMonth->format('Y');
        $nextYear = (int) $nextMonth->format('Y');

        $rkaCurrent = $this->rkaLookup->aggregateByGroup(
            [
                'briguna_konsumer' => ['mata_anggaran' => ['B.5.a. Briguna']],
                'kpr' => ['mata_anggaran' => ['B.5.b. KPR']],
            ],
            $currentMonthColumn,
            [],
            [],
            'kanca',
            $currentYear
        );
        $rkaNext = $this->rkaLookup->aggregateByGroup(
            [
                'briguna_konsumer' => ['mata_anggaran' => ['B.5.a. Briguna']],
                'kpr' => ['mata_anggaran' => ['B.5.b. KPR']],
            ],
            $nextMonthColumn,
            [],
            [],
            'kanca',
            $nextYear
        );

        $data = [];
        $totals = [
            'curr' => 0.0,
            'prev_day' => 0.0,
            'mtd' => 0.0,
            'ytd' => 0.0,
        ];

        $groupedRows = [];

        foreach ($rows as $row) {
            $rm = trim((string) ($row->rm ?? ''));
            $cabang = trim((string) ($row->cabang ?? ''));
            $productLabel = $this->normalizeProductLabel($row->produk_raw ?? null);
            if ($rm === '') {
                continue;
            }
            if ($productLabel === null) {
                continue;
            }

            $curr = (float) ($row->curr ?? 0);
            $prevDay = (float) ($row->prev_day ?? 0);
            $mtd = (float) ($row->mtd ?? 0);
            $ytd = (float) ($row->ytd ?? 0);
            $cabangKey = strtoupper($cabang);
            $rkaKey = $this->resolveRkaProductKey($productLabel);
            $rkaCurr = (float) ($rkaCurrent[$rkaKey][$cabangKey] ?? 0);
            $rkaNextVal = (float) ($rkaNext[$rkaKey][$cabangKey] ?? 0);

            $groupKey = $cabangKey . '|' . $rm;
            if (!isset($groupedRows[$groupKey])) {
                $groupedRows[$groupKey] = [
                    'cabang' => $cabang !== '' ? $cabang : '-',
                    'rm' => $rm,
                    'items' => [],
                ];
            }

            $groupedRows[$groupKey]['items'][] = [
                'segmen' => $productLabel,
                'product' => $productLabel,
                'rm' => $rm,
                'curr' => $curr,
                'prev_day' => $prevDay,
                'mtd' => $mtd,
                'ytd' => $ytd,
                'delta_dtd' => round($curr - $prevDay, 0),
                'delta_mtd' => round($curr - $mtd, 0),
                'delta_ytd' => round($curr - $ytd, 0),
                'rka_current' => $rkaCurr,
                'rka_next' => $rkaNextVal,
                'penc_current' => $rkaCurr > 0 ? round(($curr / $rkaCurr) * 100, 2) : 0,
                'penc_next' => $rkaNextVal > 0 ? round(($curr / $rkaNextVal) * 100, 2) : 0,
            ];

            $totals['curr'] += $curr;
            $totals['prev_day'] += $prevDay;
            $totals['mtd'] += $mtd;
            $totals['ytd'] += $ytd;
        }

        $totals['rka_current'] = $this->sumRkaValuesByProducts($rkaCurrent, $selectedCabang);
        $totals['rka_next'] = $this->sumRkaValuesByProducts($rkaNext, $selectedCabang);

        $data = [];
        foreach ($groupedRows as $group) {
            $groupItems = array_values($group['items']);
            $group['rowspan'] = count($groupItems);
            $group['items'] = $groupItems;
            $data[] = $group;
        }

        $total = [
            'segmen' => 'Total',
            'cabang' => $selectedCabang !== null ? $selectedCabang : 'SEMUA CABANG',
            'rm' => 'TOTAL',
            'curr' => round($totals['curr'], 0),
            'prev_day' => round($totals['prev_day'], 0),
            'mtd' => round($totals['mtd'], 0),
            'ytd' => round($totals['ytd'], 0),
            'delta_dtd' => round($totals['curr'] - $totals['prev_day'], 0),
            'delta_mtd' => round($totals['curr'] - $totals['mtd'], 0),
            'delta_ytd' => round($totals['curr'] - $totals['ytd'], 0),
            'rka_current' => round($totals['rka_current'], 0),
            'rka_next' => round($totals['rka_next'], 0),
            'penc_current' => $totals['rka_current'] > 0 ? round(($totals['curr'] / $totals['rka_current']) * 100, 2) : 0,
            'penc_next' => $totals['rka_next'] > 0 ? round(($totals['curr'] / $totals['rka_next']) * 100, 2) : 0,
        ];

        return [
            'rows' => $data,
            'total' => $total,
        ];
    }

    private function resolveExistingColumn(string $table, array $candidates, string $fallback): string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return $fallback;
    }

    private function sumRkaValuesByProducts(array $values, ?string $selectedCabang = null): float
    {
        $productKeys = ['briguna_konsumer', 'kpr'];

        if ($selectedCabang !== null) {
            $cabangKey = strtoupper($selectedCabang);
            $total = 0.0;

            foreach ($productKeys as $productKey) {
                $total += (float) ($values[$productKey][$cabangKey] ?? 0);
            }

            return $total;
        }

        $total = 0.0;
        foreach ($productKeys as $productKey) {
            $total += array_reduce(
                $values[$productKey] ?? [],
                fn (float $carry, $value) => $carry + (float) $value,
                0.0
            );
        }

        return $total;
    }

    private function normalizeProductLabel(?string $value): ?string
    {
        $normalized = strtoupper(trim((string) $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = str_replace(['_', '/'], [' ', ' '], $normalized);
        $normalized = preg_replace('/\s*-\s*/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? $normalized;

        if ($normalized === 'BRIGUNA KONSUMER') {
            return 'BRIGUNA-KONSUMER';
        }

        if ($normalized === 'KPR') {
            return 'KPR';
        }

        return null;
    }

    private function resolveRkaProductKey(string $productLabel): string
    {
        return $productLabel === 'BRIGUNA-KONSUMER' ? 'briguna_konsumer' : 'kpr';
    }

    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
