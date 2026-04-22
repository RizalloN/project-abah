<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Support\RkaLookupService;
use App\Support\StrictDateParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KinerjaKonsumerReportController extends Controller
{
    private const DEFAULT_TITLE = 'Performance Per RM';
    private const SEGMENT_LABEL = 'KPR';
    
    // Mapping segmen ke product options
    private const SEGMENT_PRODUCT_MAP = [
        'CONSUMER' => ['BRIGUNA-KONSUMER', 'KPR'],
        'SMALL' => ['COMMERCIAL', 'CASHCOLL'],
        'MICRO' => ['BRIGUNA-MIKRO', 'KUPEDES', 'KUR-MIKRO'],
    ];
    
    private const AVAILABLE_SEGMENTS = ['CONSUMER', 'SMALL', 'MICRO'];
    private const DEFAULT_SEGMENT = 'CONSUMER';

    public function __construct(
        private readonly RkaLookupService $rkaLookup
    ) {}

    public function index(Request $request): View
    {
        $availablePeriods = $this->fetchAvailablePeriods();
        $selectedSegmen = $this->resolveSelectedSegmen($request->input('segmen'));
        $availableCabangs = $this->fetchAvailableCabangsBySegmen($selectedSegmen);
        $selectedPeriod = $this->resolveSelectedPeriod($availablePeriods, $request->input('periode'))
            ?? $availablePeriods->first()
            ?? Carbon::now()->toDateString();
        $selectedCabang = $this->resolveSelectedCabang($availableCabangs, $request->input('cabang1'));
        $selectedProduct = $this->resolveSelectedProduct($request->input('produk'), $selectedSegmen);

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

        $osRows = $this->fetchBranchRows($selectedSegmen, $selectedPeriod, $previousDayPeriod, $mtdPeriod, $ytdPeriod, $selectedCabang, $selectedProduct);
        $smlRows = $this->fetchBranchRows($selectedSegmen, $selectedPeriod, $previousDayPeriod, $mtdPeriod, $ytdPeriod, $selectedCabang, $selectedProduct, 'sml');
        $nplRows = $this->fetchBranchRows($selectedSegmen, $selectedPeriod, $previousDayPeriod, $mtdPeriod, $ytdPeriod, $selectedCabang, $selectedProduct, 'npl');
        $nextMonth = $currentDate->copy()->addMonthNoOverflow();

        $productOptions = self::SEGMENT_PRODUCT_MAP[$selectedSegmen] ?? [];

        $viewData = [
            'title' => self::DEFAULT_TITLE,
            'availablePeriods' => $availablePeriods,
            'availableSegmens' => self::AVAILABLE_SEGMENTS,
            'selectedSegmen' => $selectedSegmen,
            'latestPeriodLabel' => $availablePeriods->first()
                ? Carbon::parse($availablePeriods->first())->translatedFormat('d M Y')
                : '-',
            'availableCabangs' => $availableCabangs,
            'availableProducts' => $productOptions,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodLabel' => $currentDate->translatedFormat('d M Y'),
            'selectedPeriodShortLabel' => $currentDate->translatedFormat('d M y'),
            'selectedCabang' => $selectedCabang,
            'selectedCabangLabel' => $selectedCabang !== null ? $selectedCabang : 'Semua Cabang',
            'selectedProduct' => $selectedProduct,
            'selectedProductLabel' => $selectedProduct ?? 'Semua Produk',
            'previousDayPeriod' => $previousDayPeriod,
            'previousDayLabel' => Carbon::parse($previousDayPeriod)->translatedFormat('d M y'),
            'mtdPeriod' => $mtdPeriod,
            'mtdLabel' => Carbon::parse($mtdPeriod)->translatedFormat('d M Y'),
            'ytdPeriod' => $ytdPeriod,
            'ytdLabel' => Carbon::parse($ytdPeriod)->translatedFormat('d M Y'),
            'currentMonthLabel' => $currentDate->format('M-y'),
            'nextMonthLabel' => $nextMonth->format('M-y'),
            'rows' => $osRows['rows'],
            'total' => $osRows['total'],
            'qualityRowsSml' => $smlRows['rows'],
            'qualityTotalSml' => $smlRows['total'],
            'qualityRowsNpl' => $nplRows['rows'],
            'qualityTotalNpl' => $nplRows['total'],
        ];

        if ($request->ajax()) {
            return view('report.kinerja-konsumer-table', $viewData);
        }

        return view('report.kinerja-konsumer', $viewData);
    }

    private function fetchAvailablePeriods(): Collection
    {
        return Cache::remember('kinerja_rm_periods', 600, function () {
            $productColumn = $this->resolveProductColumn();
            
            $periods = collect();
            foreach (self::AVAILABLE_SEGMENTS as $segment) {
                $productOptions = self::SEGMENT_PRODUCT_MAP[$segment] ?? [];
                $periodsColl = DB::table('daily_loan_dinamis')
                    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = ?", [$segment])
                    ->whereIn(DB::raw($this->normalizedColumnExpression($productColumn)), $productOptions)
                    ->select('periode')
                    ->distinct()
                    ->orderByDesc('periode')
                    ->pluck('periode')
                    ->map(fn ($value) => Carbon::parse($value)->toDateString());
                
                $periods = $periods->merge($periodsColl);
            }
            
            return $periods->unique()->sort()->reverse()->values();
        });
    }

    private function fetchAvailableCabangsBySegmen(string $segmen): Collection
    {
        $cacheKey = 'kinerja_rm_cabangs:' . $segmen;
        
        return Cache::remember($cacheKey, 1800, function () use ($segmen) {
            $cabangColumn = $this->resolveCabangColumn();
            $productColumn = $this->resolveProductColumn();
            $productOptions = self::SEGMENT_PRODUCT_MAP[$segmen] ?? [];

            return DB::table('daily_loan_dinamis')
                ->whereRaw("UPPER(TRIM(segmen_dashboard)) = ?", [$segmen])
                ->whereIn(DB::raw($this->normalizedColumnExpression($productColumn)), $productOptions)
                ->whereNotNull($cabangColumn)
                ->where($cabangColumn, '<>', '')
                ->select($cabangColumn . ' as cabang')
                ->orderBy('cabang')
                ->pluck('cabang')
                ->map(fn ($cabang) => $this->sanitizeCabangLabel($cabang))
                ->filter(fn ($cabang) => $cabang !== '')
                ->unique(fn ($cabang) => $this->normalizeCabangKey($cabang))
                ->values();
        });
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

    private function resolveSelectedSegmen(?string $requestedSegmen): string
    {
        $normalized = strtoupper(trim((string) $requestedSegmen));
        
        if (in_array($normalized, self::AVAILABLE_SEGMENTS, true)) {
            return $normalized;
        }
        
        return self::DEFAULT_SEGMENT;
    }

    private function resolveSelectedCabang(Collection $cabangs, ?string $requestedCabang): ?string
    {
        $value = $this->normalizeCabangKey($requestedCabang);

        if ($value === '' || in_array($value, ['SEMUA CABANG', 'ALL', 'ALL CABANG'], true)) {
            return null;
        }

        return $cabangs->first(fn ($cabang) => $this->normalizeCabangKey($cabang) === $value);
    }

    private function resolveSelectedProduct(?string $requestedProduct, string $segmen = 'CONSUMER'): ?string
    {
        $normalized = $this->normalizeProductLabel($requestedProduct);
        $productOptions = self::SEGMENT_PRODUCT_MAP[$segmen] ?? [];
        
        if ($normalized !== null && in_array($normalized, $productOptions, true)) {
            return $normalized;
        }
        
        return null;
    }

    private function resolveClosestPeriod(Collection $periods, Carbon $target): ?string
    {
        $targetDate = $target->toDateString();

        return $periods
            ->first(function (string $period) use ($targetDate) {
                return $period <= $targetDate;
            });
    }

    private function fetchBranchRows(
        string $segmen,
        string $selectedPeriod,
        string $previousDayPeriod,
        string $mtdPeriod,
        string $ytdPeriod,
        ?string $selectedCabang = null,
        ?string $selectedProduct = null,
        ?string $qualityType = null
    ): array
    {
        $cacheKey = 'kinerja_rm_rows:v1:' . md5(json_encode([
            'segmen' => $segmen,
            'selected' => $selectedPeriod,
            'prev_day' => $previousDayPeriod,
            'mtd' => $mtdPeriod,
            'ytd' => $ytdPeriod,
            'cabang' => $selectedCabang,
            'produk' => $selectedProduct,
            'quality' => $qualityType,
        ]));

        return Cache::remember($cacheKey, 300, function () use ($segmen, $selectedPeriod, $previousDayPeriod, $mtdPeriod, $ytdPeriod, $selectedCabang, $selectedProduct, $qualityType) {
        $productOptions = self::SEGMENT_PRODUCT_MAP[$segmen] ?? [];
        
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
        $cabangColumn = $this->resolveCabangColumn();
        $productColumn = $this->resolveProductColumn();
        $debiturColumn = $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['nomor_rekening1', 'nomor_rekening', 'no_rekening', 'rekening', 'account_number', 'cifno', 'nocif'],
            'nomor_rekening1'
        );
        $balanceColumn = $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['baki_debet1', 'baki_debet'],
            'baki_debet1'
        );
        $kolAdkColumn = $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['kol_adk1'],
            'kol_adk1'
        );
        $realisasiColumn = $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['tgl_realisasi'],
            'tgl_realisasi'
        );
        $debiturExpression = "NULLIF(TRIM(CAST({$debiturColumn} AS CHAR)), '')";
        $realisasiDateExpression = StrictDateParser::buildMySqlCaseExpression("NULLIF(TRIM(CAST({$realisasiColumn} AS CHAR)), '')");
        $normalizedProductExpression = $this->normalizedColumnExpression($productColumn);
        $normalizedCabangExpression = $this->normalizedColumnExpression($cabangColumn);
        $monthStart = Carbon::parse($selectedPeriod)->startOfMonth()->toDateString();
        $monthEnd = Carbon::parse($selectedPeriod)->endOfMonth()->toDateString();

        $qualityExpression = "CASE
            WHEN CAST(COALESCE({$kolAdkColumn}, 0) AS DECIMAL(10,2)) = 2 THEN 'sml'
            WHEN CAST(COALESCE({$kolAdkColumn}, 0) AS DECIMAL(10,2)) > 2 THEN 'npl'
            ELSE 'other'
        END";

        $builder = DB::table('daily_loan_dinamis')
            ->selectRaw("{$rmColumn} as rm")
            ->selectRaw("{$cabangColumn} as cabang")
            ->selectRaw("{$productColumn} as produk_raw")
            ->selectRaw("SUM(CASE WHEN periode = ? THEN COALESCE({$balanceColumn}, 0) ELSE 0 END) as curr", [$selectedPeriod])
            ->selectRaw("COUNT(DISTINCT CASE WHEN periode = ? THEN {$debiturExpression} END) as curr_deb", [$selectedPeriod])
            ->selectRaw("SUM(CASE WHEN periode = ? THEN COALESCE({$balanceColumn}, 0) ELSE 0 END) as prev_day", [$previousDayPeriod])
            ->selectRaw("SUM(CASE WHEN periode = ? THEN COALESCE({$balanceColumn}, 0) ELSE 0 END) as mtd", [$mtdPeriod])
            ->selectRaw("SUM(CASE WHEN periode = ? THEN COALESCE({$balanceColumn}, 0) ELSE 0 END) as ytd", [$ytdPeriod])
            ->selectRaw("COUNT(DISTINCT CASE WHEN periode = ? AND {$realisasiDateExpression} BETWEEN ? AND ? THEN {$debiturExpression} END) as realisasi_deb", [$selectedPeriod, $monthStart, $monthEnd])
            ->selectRaw("SUM(CASE WHEN periode = ? AND {$realisasiDateExpression} BETWEEN ? AND ? THEN COALESCE({$balanceColumn}, 0) ELSE 0 END) as realisasi_os", [$selectedPeriod, $monthStart, $monthEnd])
            ->whereRaw("UPPER(TRIM(segmen_dashboard)) = ?", [$segmen])
            ->when($selectedProduct === null, function ($query) use ($normalizedProductExpression, $productOptions) {
                $query->whereIn(DB::raw($normalizedProductExpression), $productOptions);
            })
            ->when($selectedProduct !== null, function ($query) use ($normalizedProductExpression, $selectedProduct) {
                $query->whereRaw($normalizedProductExpression . ' = ?', [$selectedProduct]);
            })
            ->whereNotNull($rmColumn)
            ->where($rmColumn, '<>', '')
            ->when($qualityType !== null, function ($query) use ($qualityExpression, $qualityType) {
                $query->whereRaw('(' . $qualityExpression . ') = ?', [$qualityType]);
            })
            ->when($selectedCabang !== null, function ($query) use ($normalizedCabangExpression, $selectedCabang) {
                $query->whereRaw($normalizedCabangExpression . ' = ?', [$this->normalizeCabangKey($selectedCabang)]);
            })
            ->whereIn('periode', $periods)
            ->groupBy($rmColumn, $cabangColumn, $productColumn)
            ->orderBy($cabangColumn)
            ->orderBy($rmColumn)
            ;

        $dbRows = $builder->get();

        $manualTargets = $this->getManualJgTargets();
        $branches = [];
        $grandTotals = [
            'curr' => 0.0,
            'curr_deb' => 0,
            'prev_day' => 0.0,
            'mtd' => 0.0,
            'ytd' => 0.0,
            'target_jg_deb' => 0,
            'target_jg_os' => 0.0,
        ];

        foreach ($dbRows as $row) {
            $cabangName = trim((string) ($row->cabang ?? ''));
            $rmOriginal = trim((string) ($row->rm ?? ''));
            $rmName = $this->mapRmName($rmOriginal);
            $productLabel = $this->normalizeProductLabel($row->produk_raw ?? null, $segmen);

            if ($rmName === '' || $productLabel === null) {
                continue;
            }

            $cabangKey = $this->normalizeCabangKey($cabangName);
            if (!isset($branches[$cabangKey])) {
                $branches[$cabangKey] = [
                    'cabang' => $this->sanitizeCabangLabel($cabangName) ?: '-',
                    'rms' => [],
                    'subtotal' => [
                        'curr' => 0.0, 'curr_deb' => 0, 'prev_day' => 0.0, 'mtd' => 0.0, 'ytd' => 0.0,
                        'target_jg_deb' => 0, 'target_jg_os' => 0.0,
                    ],
                    'branch_rowspan' => 0,
                ];
            }

            if (!isset($branches[$cabangKey]['rms'][$rmName])) {
                $branches[$cabangKey]['rms'][$rmName] = [
                    'rm' => $rmName,
                    'items' => [],
                    'rm_rowspan' => 0,
                ];
            }

            $curr = (float) ($row->curr ?? 0);
            $currDeb = (int) ($row->curr_deb ?? 0);
            $prevDay = (float) ($row->prev_day ?? 0);
            $mtd = (float) ($row->mtd ?? 0);
            $ytd = (float) ($row->ytd ?? 0);
            $realisasiDeb = (int) ($row->realisasi_deb ?? 0);
            $realisasiOs = (float) ($row->realisasi_os ?? 0);

            // Fetch Manual JG Target
            $nameOnly = strtoupper(trim(explode('-', $rmName)[1] ?? $rmName));
            $target = $manualTargets[$productLabel][$nameOnly] ?? null;
            $tDeb = $target['deb'] ?? 0;
            $tOs = $target['os'] ?? 0.0;

            $item = [
                'segmen' => $segmen,
                'product' => $productLabel,
                'curr' => $curr,
                'curr_deb' => $currDeb,
                'prev_day' => $prevDay,
                'mtd' => $mtd,
                'ytd' => $ytd,
                'delta_dtd' => $curr - $prevDay,
                'delta_mtd' => $curr - $mtd,
                'delta_ytd' => $curr - $ytd,
                'target_jg_deb' => $tDeb,
                'target_jg_os' => $tOs,
                'ach_deb' => $realisasiDeb,
                'ach_os' => $realisasiOs,
            ];

            $branches[$cabangKey]['rms'][$rmName]['items'][] = $item;
            $branches[$cabangKey]['rms'][$rmName]['rm_rowspan']++;
            $branches[$cabangKey]['branch_rowspan']++;

            // Update Branch Subtotal
            $branches[$cabangKey]['subtotal']['curr'] += $curr;
            $branches[$cabangKey]['subtotal']['curr_deb'] += $currDeb;
            $branches[$cabangKey]['subtotal']['prev_day'] += $prevDay;
            $branches[$cabangKey]['subtotal']['mtd'] += $mtd;
            $branches[$cabangKey]['subtotal']['ytd'] += $ytd;
            $branches[$cabangKey]['subtotal']['target_jg_deb'] += $tDeb;
            $branches[$cabangKey]['subtotal']['target_jg_os'] += $tOs;
            $branches[$cabangKey]['subtotal']['ach_deb'] = ($branches[$cabangKey]['subtotal']['ach_deb'] ?? 0) + $realisasiDeb;
            $branches[$cabangKey]['subtotal']['ach_os'] = ($branches[$cabangKey]['subtotal']['ach_os'] ?? 0.0) + $realisasiOs;

            // Update Grand Totals
            $grandTotals['curr'] += $curr;
            $grandTotals['curr_deb'] += $currDeb;
            $grandTotals['prev_day'] += $prevDay;
            $grandTotals['mtd'] += $mtd;
            $grandTotals['ytd'] += $ytd;
            $grandTotals['target_jg_deb'] += $tDeb;
            $grandTotals['target_jg_os'] += $tOs;
            $grandTotals['ach_deb'] = ($grandTotals['ach_deb'] ?? 0) + $realisasiDeb;
            $grandTotals['ach_os'] = ($grandTotals['ach_os'] ?? 0.0) + $realisasiOs;
        }

        // Add 1 to branch_rowspan for the subtotal row
        foreach ($branches as $key => $branch) {
            $branches[$key]['branch_rowspan'] += 1;
            
            $b_curr = $branches[$key]['subtotal']['curr'];
            $branches[$key]['subtotal']['delta_dtd'] = $b_curr - $branches[$key]['subtotal']['prev_day'];
            $branches[$key]['subtotal']['delta_mtd'] = $b_curr - $branches[$key]['subtotal']['mtd'];
            $branches[$key]['subtotal']['delta_ytd'] = $b_curr - $branches[$key]['subtotal']['ytd'];
            
            $branches[$key]['subtotal']['ach_deb'] = $branches[$key]['subtotal']['ach_deb'] ?? 0;
            $branches[$key]['subtotal']['ach_os'] = $branches[$key]['subtotal']['ach_os'] ?? 0.0;
        }

        $totalRecord = [
            'segmen' => $segmen,
            'cabang' => $selectedCabang ?? 'SEMUA CABANG',
            'rm' => 'TOTAL',
            'curr' => $grandTotals['curr'],
            'curr_deb' => $grandTotals['curr_deb'],
            'prev_day' => $grandTotals['prev_day'],
            'mtd' => $grandTotals['mtd'],
            'ytd' => $grandTotals['ytd'],
            'delta_dtd' => $grandTotals['curr'] - $grandTotals['prev_day'],
            'delta_mtd' => $grandTotals['curr'] - $grandTotals['mtd'],
            'delta_ytd' => $grandTotals['curr'] - $grandTotals['ytd'],
            'target_jg_deb' => $grandTotals['target_jg_deb'],
            'target_jg_os' => $grandTotals['target_jg_os'],
            'ach_deb' => $grandTotals['ach_deb'] ?? 0,
            'ach_os' => $grandTotals['ach_os'] ?? 0.0,
        ];

        return [
            'rows' => array_values($branches),
            'total' => $totalRecord,
        ];
        });
    }

    private function getManualJgTargets(): array
    {
        return [
            'BRIGUNA-KONSUMER' => [
                'BAGUS PRASETYO' => ['deb' => 20, 'os' => 3750000000],
                'ARIANI SETYO PALUPI' => ['deb' => 20, 'os' => 3750000000],
                'RONA ROHANA TALIBATA' => ['deb' => 20, 'os' => 3750000000],
                'RATNA DWI SISWIYANTORO' => ['deb' => 19, 'os' => 3700000000],
                'ARIS SULISTYAWAN' => ['deb' => 19, 'os' => 3700000000],
                'TITIN OKTAVIA' => ['deb' => 20, 'os' => 3850000000],
                'FARID ROMADLONI' => ['deb' => 19, 'os' => 3700000000],
                'ZULFA ENDY CRISMANA' => ['deb' => 19, 'os' => 3700000000],
                'ARDINI' => ['deb' => 20, 'os' => 3850000000],
                'NOVAN YOGA PRATAMA' => ['deb' => 16, 'os' => 1900000000],
            ],
            'KPR' => [
                'VIVIN SRIHARDILA TANTIAYUDHA' => ['deb' => 7, 'os' => 3300000000],
                'ABDUL HALIM MUZAKKI' => ['deb' => 7, 'os' => 3500000000],
                'GLAGAH MAHESTYA YAHYA' => ['deb' => 6, 'os' => 2800000000],
            ],
        ];
    }

    private function mapRmName(string $rmName): string
    {
        if (str_contains(strtoupper($rmName), '00385844 -')) {
            return '00385844 - Glagah Mahestya Yahya';
        }
        return $rmName;
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

    private function resolveCabangColumn(): string
    {
        return $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['cabang1', 'cabang'],
            'cabang1'
        );
    }

    private function resolveProductColumn(): string
    {
        return $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['produk_dashboard', 'produk'],
            'produk_dashboard'
        );
    }

    private function sumRkaValuesByProducts(array $values, ?string $selectedCabang = null, ?string $selectedProduct = null): float
    {
        $productKeys = $selectedProduct ? [$this->resolveRkaProductKey($selectedProduct)] : ['briguna_konsumer', 'kpr'];

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

    private function normalizeProductLabel(?string $value, string $segmen = 'CONSUMER'): ?string
    {
        $normalized = strtoupper(trim((string) $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = str_replace(['_', '/'], [' ', ' '], $normalized);
        $normalized = preg_replace('/\s*-\s*/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? $normalized;

        // Normalize based on segmen
        $productMap = match($segmen) {
            'CONSUMER' => [
                'BRIGUNA KONSUMER' => 'BRIGUNA-KONSUMER',
                'KPR' => 'KPR',
            ],
            'SMALL' => [
                'COMMERCIAL' => 'COMMERCIAL',
                'CASHCOLL' => 'CASHCOLL',
            ],
            'MICRO' => [
                'BRIGUNA MIKRO' => 'BRIGUNA-MIKRO',
                'KUPEDES' => 'KUPEDES',
                'KUR MIKRO' => 'KUR-MIKRO',
            ],
            default => []
        };

        return $productMap[$normalized] ?? null;
    }

    private function normalizedColumnExpression(string $column): string
    {
        return "UPPER(TRIM(REPLACE(REPLACE(CAST({$column} AS CHAR), '_', '-'), ' ', '-')))";
    }

    private function normalizeCabangKey(?string $value): string
    {
        $normalized = strtoupper(trim((string) $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized;
    }

    private function sanitizeCabangLabel(?string $value): string
    {
        return trim((string) $value);
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
