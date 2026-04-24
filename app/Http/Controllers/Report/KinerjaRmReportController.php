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

class KinerjaRmReportController extends Controller
{
    private const DEFAULT_TITLE = 'Performance Per RM';
    private const SEGMENT_LABEL = 'KPR';
    
    // Mapping segmen ke product options
    private const SEGMENT_PRODUCT_MAP = [
        'CONSUMER' => ['BRIGUNA-KONSUMER', 'KPR'],
        'SMALL' => ['COMMERCIAL', 'CASHCALL'],
        'MICRO' => ['BRIGUNA-MIKRO', 'KUPEDES', 'KUR-MIKRO', 'CASHCOLLATERAL', 'KPR', 'KUR-SMALL'],
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
        
        // LIMITATION FOR MIKRO SEGMENT
        $availableCabangs = $this->fetchAvailableCabangsBySegmen($selectedSegmen);
        
        $selectedPeriod = $this->resolveSelectedPeriod($availablePeriods, $request->input('periode'))
            ?? $availablePeriods->first()
            ?? Carbon::now()->toDateString();
        $selectedCabang = $this->resolveSelectedCabang($availableCabangs, $request->input('cabang1'));
        
        // Force selection for MICRO if not selected
        if ($selectedSegmen === 'MICRO' && $selectedCabang === null) {
             $selectedCabang = $availableCabangs->first();
        }

        $selectedProduct = $this->resolveSelectedProduct($request->input('produk'), $selectedSegmen);

        $currentDate = Carbon::parse($selectedPeriod);
        
        $yoyPeriod = $this->resolveClosestPeriod(
            $availablePeriods,
            $currentDate->copy()->subYear()
        ) ?? $selectedPeriod;
        
        $ytdPeriod = $this->resolveClosestPeriod(
            $availablePeriods,
            $currentDate->copy()->subYear()->endOfYear()
        ) ?? $selectedPeriod;
        
        $mtdPeriod = $this->resolveClosestPeriod(
            $availablePeriods,
            $currentDate->copy()->subMonthNoOverflow()->endOfMonth()
        ) ?? $selectedPeriod;

        $osRows = $this->fetchBranchRows($selectedSegmen, $selectedPeriod, $yoyPeriod, $ytdPeriod, $mtdPeriod, $selectedCabang, $selectedProduct);
        $smlRows = $this->fetchBranchRows($selectedSegmen, $selectedPeriod, $yoyPeriod, $ytdPeriod, $mtdPeriod, $selectedCabang, $selectedProduct, 'sml');
        $nplRows = $this->fetchBranchRows($selectedSegmen, $selectedPeriod, $yoyPeriod, $ytdPeriod, $mtdPeriod, $selectedCabang, $selectedProduct, 'npl');
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
            'yoyPeriod' => $yoyPeriod,
            'yoyLabel' => Carbon::parse($yoyPeriod)->translatedFormat('d M Y'),
            'ytdPeriod' => $ytdPeriod,
            'ytdLabel' => Carbon::parse($ytdPeriod)->translatedFormat('d M Y'),
            'mtdPeriod' => $mtdPeriod,
            'mtdLabel' => Carbon::parse($mtdPeriod)->translatedFormat('d M Y'),
            'currentMonthLabel' => $currentDate->format('M-y'),
            'nextMonthLabel' => $nextMonth->format('M-y'),
            'rows' => $osRows['rows'],
            'total' => $osRows['total'],
            'qualityRowsSml' => $smlRows['rows'],
            'qualityTotalSml' => $smlRows['total'],
            'qualityRowsNpl' => $nplRows['rows'],
            'qualityTotalNpl' => $nplRows['total'],
            'formatAmount' => fn ($value, int $decimals = 1) => $this->formatAmountInJuta($value, $decimals),
            'formatSignedAmount' => fn ($value, bool $showArrow = true, int $decimals = 1) => $this->formatSignedAmountInJuta($value, $showArrow, $decimals),
            'formatCount' => fn ($value) => $this->formatCount($value),
            'formatPercent' => fn ($value, int $decimals = 1) => $this->formatPercent($value, $decimals),
            'quadrantLabel' => fn ($quadrant) => $this->formatQuadrantLabel($quadrant),
            'quadrantClass' => fn ($quadrant) => $this->formatQuadrantClass($quadrant),
        ];

        if ($request->ajax()) {
            $this->releaseSessionLockIfNeeded();
            return view('report.kinerjarm-table', $viewData);
        }

        return view('report.kinerjarm', $viewData);
    }

    public function historyDetails(Request $request): View
    {
        $rm = $request->input('rm');
        $segmen = $request->input('segmen');
        $selectedPeriod = $request->input('periode');
        
        $year = Carbon::parse($selectedPeriod)->year;
        
        $history = DB::table('performance_rm_snapshots')
            ->where('rm', $rm)
            ->where('segmen', $segmen)
            ->whereYear('periode', $year)
            ->orderByDesc('periode')
            ->get();
            
        // Group by Month and Branch
        $groups = $history->groupBy(function ($row) {
            return Carbon::parse($row->periode)->format('Y-m') . '|' . $row->cabang;
        });

        $details = $groups->map(function ($group) {
            // Pick the latest date in this month-branch group
            $latestDate = $group->first()->periode;
            $latestDateRows = $group->where('periode', $latestDate);

            $loanOs = $latestDateRows->sum('loan_os');
            $smlOs = $latestDateRows->sum('sml_os');
            $nplOs = $latestDateRows->sum('npl_os');
            $restrukOs = $latestDateRows->sum('restruk_os');
            $realisasiOs = $latestDateRows->sum('realisasi_os');

            $lar = (float)$restrukOs + (float)$smlOs + (float)$nplOs;
            $pctLar = $loanOs > 0 ? ($lar / $loanOs) * 100 : 0;
            
            // Re-calculate A/B (Target 1600M)
            $isRealizA = ($realisasiOs / 1000000) >= 1600;
            $isLarA = $pctLar < 17.5;
            
            return [
                'periode' => Carbon::parse($latestDate)->translatedFormat('M Y'),
                'cabang' => $group->first()->cabang,
                'realisasi_os' => $realisasiOs,
                'penc_realisasi' => $isRealizA ? 'A' : 'B',
                'pct_lar' => $pctLar,
                'penc_lar' => $isLarA ? 'A' : 'B',
                'sort_date' => $latestDate
            ];
        })->sortBy('sort_date')->values();
        
        return view('report.kinerjarm-detail-modal', [
            'rm' => $rm,
            'segmen' => $segmen,
            'details' => $details,
            'formatAmount' => fn ($value, int $decimals = 0) => $this->formatAmountInJuta($value, $decimals),
            'formatPercent' => fn ($value, int $decimals = 2) => $this->formatPercent($value, $decimals),
        ]);
    }

    private function fetchAvailablePeriods(): Collection
    {
        $cacheKey = 'kinerja_rm_periods_v2:' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, 600, function () {
            return DB::table('performance_rm_snapshots')
                ->select('periode')
                ->distinct()
                ->orderByDesc('periode')
                ->pluck('periode')
                ->map(fn ($value) => Carbon::parse($value)->toDateString());
        });
    }

    private function fetchAvailableCabangsBySegmen(string $segmen): Collection
    {
        $cacheKey = 'kinerja_rm_cabangs_v2:' . $this->reportCacheVersion() . ':' . $segmen;
        
        return Cache::remember($cacheKey, 1800, function () use ($segmen) {
            return DB::table('performance_rm_snapshots')
                ->where('segmen', $segmen)
                ->whereNotNull('cabang')
                ->where('cabang', '<>', '')
                ->select('cabang')
                ->distinct()
                ->orderBy('cabang')
                ->pluck('cabang')
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
        string $yoyPeriod,
        string $ytdPeriod,
        string $mtdPeriod,
        ?string $selectedCabang = null,
        ?string $selectedProduct = null,
        ?string $qualityType = null
    ): array
    {
        $cacheKey = 'kinerja_rm_rows_v4:' . $this->reportCacheVersion() . ':' . md5(json_encode([
            'segmen' => $segmen,
            'selected' => $selectedPeriod,
            'yoy' => $yoyPeriod,
            'mtd' => $mtdPeriod,
            'ytd' => $ytdPeriod,
            'cabang' => $selectedCabang,
            'produk' => $selectedProduct,
            'quality' => $qualityType,
        ]));

        return Cache::remember($cacheKey, 300, function () use ($segmen, $selectedPeriod, $yoyPeriod, $mtdPeriod, $ytdPeriod, $selectedCabang, $selectedProduct, $qualityType) {
            $periods = array_values(array_unique(array_filter([
                $selectedPeriod,
                $yoyPeriod,
                $ytdPeriod,
                $mtdPeriod,
            ])));

            $dbRows = DB::table('performance_rm_snapshots')
                ->whereIn('periode', $periods)
                ->where('segmen', $segmen)
                ->when($selectedProduct !== null, function ($query) use ($selectedProduct) {
                    $query->where('produk', $selectedProduct);
                })
                ->when($selectedCabang !== null, function ($query) use ($selectedCabang) {
                    $query->where('cabang', $selectedCabang);
                })
                ->get();

            $manualTargets = $this->getManualJgTargets();
            $branches = [];
            $grandTotals = [
                'curr' => 0.0, 'curr_deb' => 0, 'yoy' => 0.0, 'mtd' => 0.0, 'ytd' => 0.0,
                'target_jg_deb' => 0, 'target_jg_os' => 0.0,
                'ach_deb' => 0, 'ach_os' => 0.0,
            ];

            // Pivot data by RM and Product
            $pivoted = [];
            foreach ($dbRows as $row) {
                $cabKey = $this->normalizeCabangKey($row->cabang);
                $rmKey = trim(strtoupper((string)$row->rm));
                $prodKey = $row->produk;
                $key = "{$cabKey}|{$rmKey}|{$prodKey}";

                $val = (float) match($qualityType) {
                    'sml' => $row->sml_os,
                    'npl' => $row->npl_os,
                    default => $row->loan_os
                };

                $pivoted[$key] ??= [
                    'cabang' => $row->cabang,
                    'rm' => $row->rm,
                    'produk' => $row->produk,
                    'quadrant' => null,
                    'curr' => 0.0, 'curr_deb' => 0, 'yoy' => 0.0, 'mtd' => 0.0, 'ytd' => 0.0,
                    'realisasi_deb' => 0, 'realisasi_os' => 0.0,
                ];

                if ($row->periode === $selectedPeriod) {
                    $pivoted[$key]['curr'] = $val;
                    $pivoted[$key]['curr_deb'] = (int)$row->total_deb;
                    $pivoted[$key]['realisasi_deb'] = (int)($row->realisasi_deb ?? 0);
                    $pivoted[$key]['realisasi_os'] = (float)($row->realisasi_os ?? 0.0);
                    $quadrant = $row->quadrant ?? null;

                    if ($quadrant === null) {
                        $loanOs = (float) ($row->loan_os ?? 0);

                        if ($loanOs > 0) {
                            $isXPositive = (float) ($row->lancar_os ?? 0) >= (float) ($row->npl_os ?? 0);
                            $isYPositive = (float) ($row->total_deposit ?? 0) >= $loanOs;

                            if ($isXPositive && $isYPositive) {
                                $quadrant = 2;
                            } elseif (!$isXPositive && $isYPositive) {
                                $quadrant = 3;
                            } elseif (!$isXPositive && !$isYPositive) {
                                $quadrant = 4;
                            } else {
                                $quadrant = 1;
                            }
                        }
                    }

                    $pivoted[$key]['quadrant'] = $quadrant;
                }
                
                if ($row->periode === $yoyPeriod) {
                    $pivoted[$key]['yoy'] = $val;
                }
                
                if ($row->periode === $ytdPeriod) {
                    $pivoted[$key]['ytd'] = $val;
                }
                
                if ($row->periode === $mtdPeriod) {
                    $pivoted[$key]['mtd'] = $val;
                }
            }

            foreach ($pivoted as $data) {
                $cabangName = $data['cabang'];
                $rmName = $this->mapRmName($data['rm']);
                $productLabel = $this->normalizeProductLabel($data['produk'], $segmen);

                if ($rmName === '' || $productLabel === null) continue;

                $cabangKey = $this->normalizeCabangKey($cabangName);
                if (!isset($branches[$cabangKey])) {
                    $branches[$cabangKey] = [
                        'cabang' => $cabangName,
                        'rms' => [],
                        'subtotal' => [
                            'curr' => 0.0, 'curr_deb' => 0, 'yoy' => 0.0, 'mtd' => 0.0, 'ytd' => 0.0,
                            'target_jg_deb' => 0, 'target_jg_os' => 0.0,
                            'ach_deb' => 0, 'ach_os' => 0.0,
                        ],
                        'branch_rowspan' => 0,
                    ];
                }

                if (!isset($branches[$cabangKey]['rms'][$rmName])) {
                    $branches[$cabangKey]['rms'][$rmName] = [
                        'rm' => $rmName,
                        'items' => [],
                        'rm_rowspan' => 0,
                        'quadrant' => $data['quadrant'],
                    ];
                }

                // Manual Targets
                $nameOnly = strtoupper(trim(explode('-', $rmName)[1] ?? $rmName));
                $target = $manualTargets[$productLabel][$nameOnly] ?? null;
                $tDeb = $target['deb'] ?? 0;
                $tOs = $target['os'] ?? 0.0;

                $item = [
                    'segmen' => $segmen,
                    'product' => $productLabel,
                    'curr' => $data['curr'],
                    'curr_deb' => $data['curr_deb'],
                    'yoy' => $data['yoy'],
                    'ytd' => $data['ytd'],
                    'mtd' => $data['mtd'],
                    'delta_yoy' => $data['curr'] - $data['yoy'],
                    'delta_ytd' => $data['curr'] - $data['ytd'],
                    'delta_mtd' => $data['curr'] - $data['mtd'],
                    'target_jg_deb' => $tDeb,
                    'target_jg_os' => $tOs,
                    'ach_deb' => $data['realisasi_deb'],
                    'ach_os' => $data['realisasi_os'],
                ];

                $branches[$cabangKey]['rms'][$rmName]['items'][] = $item;
                $branches[$cabangKey]['rms'][$rmName]['rm_rowspan']++;
                $branches[$cabangKey]['branch_rowspan']++;

                // Update Branch Subtotal
                $branches[$cabangKey]['subtotal']['curr'] += $data['curr'];
                $branches[$cabangKey]['subtotal']['curr_deb'] += $data['curr_deb'];
                $branches[$cabangKey]['subtotal']['yoy'] += $data['yoy'];
                $branches[$cabangKey]['subtotal']['ytd'] += $data['ytd'];
                $branches[$cabangKey]['subtotal']['mtd'] += $data['mtd'];
                $branches[$cabangKey]['subtotal']['target_jg_deb'] += $tDeb;
                $branches[$cabangKey]['subtotal']['target_jg_os'] += $tOs;
                $branches[$cabangKey]['subtotal']['ach_deb'] = ($branches[$cabangKey]['subtotal']['ach_deb'] ?? 0) + $data['realisasi_deb'];
                $branches[$cabangKey]['subtotal']['ach_os'] = ($branches[$cabangKey]['subtotal']['ach_os'] ?? 0.0) + $data['realisasi_os'];

                // Grand Totals
                $grandTotals['curr'] += $data['curr'];
                $grandTotals['curr_deb'] += $data['curr_deb'];
                $grandTotals['yoy'] += $data['yoy'];
                $grandTotals['ytd'] += $data['ytd'];
                $grandTotals['mtd'] += $data['mtd'];
                $grandTotals['target_jg_deb'] += $tDeb;
                $grandTotals['target_jg_os'] += $tOs;
                $grandTotals['ach_deb'] = ($grandTotals['ach_deb'] ?? 0) + $data['realisasi_deb'];
                $grandTotals['ach_os'] = ($grandTotals['ach_os'] ?? 0.0) + $data['realisasi_os'];
            }

            foreach ($branches as $key => $branch) {
                $branches[$key]['branch_rowspan'] += 1; // For subtotal row
                $b_curr = $branches[$key]['subtotal']['curr'];
                $branches[$key]['subtotal']['delta_yoy'] = $b_curr - $branches[$key]['subtotal']['yoy'];
                $branches[$key]['subtotal']['delta_ytd'] = $b_curr - $branches[$key]['subtotal']['ytd'];
                $branches[$key]['subtotal']['delta_mtd'] = $b_curr - $branches[$key]['subtotal']['mtd'];
            }

            $totalRecord = [
                'segmen' => $segmen,
                'cabang' => $selectedCabang ?? 'SEMUA CABANG',
                'rm' => 'TOTAL',
                'curr' => $grandTotals['curr'],
                'curr_deb' => $grandTotals['curr_deb'],
                'yoy' => $grandTotals['yoy'],
                'ytd' => $grandTotals['ytd'],
                'mtd' => $grandTotals['mtd'],
                'delta_yoy' => $grandTotals['curr'] - $grandTotals['yoy'],
                'delta_ytd' => $grandTotals['curr'] - $grandTotals['ytd'],
                'delta_mtd' => $grandTotals['curr'] - $grandTotals['mtd'],
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

    private function reportCacheVersion(): int
    {
        return (int) Cache::get('report_cache_version:global', 1);
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
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $value))) ?? '';

        // Normalize based on segmen
        $productMap = match($segmen) {
            'CONSUMER' => [
                'BRIGUNAKONSUMER' => 'BRIGUNA-KONSUMER',
                'KPR' => 'KPR',
            ],
            'SMALL' => [
                'COMMERCIAL' => 'COMMERCIAL',
                'CASHCALL' => 'CASHCALL',
            ],
            'MICRO' => [
                'BRIGUNAMIKRO' => 'BRIGUNA-MIKRO',
                'KUPEDES' => 'KUPEDES',
                'KURMIKRO' => 'KUR-MIKRO',
                'CASHCOLLATERAL' => 'CASHCOLLATERAL',
                'CASHCOLL' => 'CASHCOLLATERAL',
                'KPR' => 'KPR',
                'KURSMALL' => 'KUR-SMALL',
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

    private function normalizeNumericValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function formatAmountInJuta(mixed $value, int $decimals = 1): string
    {
        $normalized = $this->normalizeNumericValue($value);

        if ($normalized === null) {
            return '-';
        }

        return number_format($normalized / 1000000, $decimals, ',', '.');
    }

    private function formatSignedAmountInJuta(mixed $value, bool $showArrow = true, int $decimals = 1): string
    {
        $normalized = $this->normalizeNumericValue($value);

        if ($normalized === null) {
            return "<span class='delta-indicator'>-</span>";
        }

        $amount = $normalized / 1000000;
        $cls = $amount > 0 ? 'pos' : ($amount < 0 ? 'neg' : '');
        $icon = '';

        if ($showArrow) {
            if ($amount > 0) {
                $icon = '<i class="fas fa-caret-up me-1"></i>';
            } elseif ($amount < 0) {
                $icon = '<i class="fas fa-caret-down me-1"></i>';
            }
        }

        $prefix = ($amount > 0 && ! $showArrow) ? '+' : '';
        $display = number_format(abs($amount), $decimals, ',', '.');

        if ($amount < 0 && ! $showArrow) {
            $display = '-' . $display;
        }

        return "<span class='delta-indicator {$cls}'>{$icon}{$prefix}{$display}</span>";
    }

    private function formatCount(mixed $value): string
    {
        $normalized = $this->normalizeNumericValue($value);

        if ($normalized === null) {
            return '-';
        }

        return number_format((int) round($normalized), 0, ',', '.');
    }

    private function formatPercent(mixed $value, int $decimals = 1): string
    {
        $normalized = $this->normalizeNumericValue($value);

        if ($normalized === null) {
            return '-';
        }

        return number_format($normalized, $decimals, ',', '.');
    }

    private function normalizeQuadrant(mixed $quadrant): ?int
    {
        $normalized = $this->normalizeNumericValue($quadrant);

        if ($normalized === null) {
            return null;
        }

        $quadrantValue = (int) $normalized;

        return in_array($quadrantValue, [1, 2, 3, 4], true) ? $quadrantValue : null;
    }

    private function formatQuadrantLabel(mixed $quadrant): string
    {
        $normalized = $this->normalizeQuadrant($quadrant);

        return $normalized !== null ? 'Kuadran ' . $normalized : '-';
    }

    private function formatQuadrantClass(mixed $quadrant): string
    {
        $normalized = $this->normalizeQuadrant($quadrant);

        return $normalized !== null ? 'q' . $normalized : '';
    }
}
