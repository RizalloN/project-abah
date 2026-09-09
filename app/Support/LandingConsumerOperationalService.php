<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class LandingConsumerOperationalService
{
    private const CACHE_PREFIX = 'landing:consumer:institution-pipeline:v2';

    private const BRANCHES = [
        'madiun' => 'KC MADIUN',
        'magetan' => 'KC MAGETAN',
        'ngawi' => 'KC NGAWI',
        'ponorogo' => 'KC PONOROGO',
    ];

    private const PRODUCTS = [
        'briguna' => ['label' => 'RM Briguna', 'snapshot' => 'BRIGUNA-KONSUMER', 'target' => 'BRIGUNA-KONSUMER'],
        'kpr' => ['label' => 'RM KPR', 'snapshot' => 'KPR', 'target' => 'KPR'],
    ];

    private const SOURCES = [
        'madiun' => [
            'branch_key' => 'madiun',
            'branch' => 'KC Madiun',
            'label' => 'KC Madiun',
            'spreadsheet_id' => '1_HRbgKXKy6Rv9gi56x0rpKaJEQgXbOlkeupqAa_XACo',
            'sheet' => 'DATA INTANSI',
        ],
        'caruban' => [
            'branch_key' => 'madiun',
            'branch' => 'KC Madiun',
            'label' => 'KCP Caruban',
            'spreadsheet_id' => '1_HRbgKXKy6Rv9gi56x0rpKaJEQgXbOlkeupqAa_XACo',
            'sheet' => 'KCP Caruban',
        ],
        'magetan' => [
            'branch_key' => 'magetan',
            'branch' => 'KC Magetan',
            'label' => 'KC Magetan',
            'spreadsheet_id' => '1uTvCIxznFkqbzgfJdLbCUUtUOTrURkqaiCxiJWFLbQ8',
            'sheet' => 'DATA INTANSI',
        ],
        'ngawi' => [
            'branch_key' => 'ngawi',
            'branch' => 'KC Ngawi',
            'label' => 'KC Ngawi',
            'spreadsheet_id' => '1Xdq0tjkUuKkD5rC4Zo0RJDeHy33bCOPDp0-98489v28',
            'sheet' => 'DATA INTANSI',
        ],
        'ponorogo' => [
            'branch_key' => 'ponorogo',
            'branch' => 'KC Ponorogo',
            'label' => 'KC Ponorogo',
            'spreadsheet_id' => '16bJoKksVdWUloplXOk07LDnZGPzh5inRKuSOdYTbEQA',
            'sheet' => 'DATA INTANSI',
        ],
    ];

    private const KPR_PIPELINE_SOURCE = [
        'key' => 'kpr-madiun',
        'branch_key' => 'madiun',
        'branch' => 'KC Madiun',
        'label' => 'KC Madiun',
        'spreadsheet_id' => '1Uh1ns63umrkTfrm6EqxGZrxfWrxcyHYjClYkTtrJQW0',
        'sheet' => 'Madiun',
    ];

    private const BRIGUNA_TARGET_FALLBACK = [
        'ARISSULISTYAWAN' => 3700000000.0,
        'ZULFAENDYCRISMANA' => 3700000000.0,
        'RATNADWISISWIYANTORO' => 3700000000.0,
        'RIDHOARDIANTO' => 3700000000.0,
        'DIMASPERDANAHADIWIJAYA' => 3700000000.0,
        'RONSROHANATALIBATA' => 3750000000.0,
        'RONAROHANATALIBATA' => 3750000000.0,
        'ARDINI' => 3850000000.0,
        'NAVANYOGAPRATAMA' => 3550000000.0,
        'NOVANYOGAPRATAMA' => 3550000000.0,
        'MUHAMADSYAMSUDINHIMAWIJAYA' => 3700000000.0,
        'BAGUSPRASETYO' => 3750000000.0,
        'ARIANISETYOPALUPI' => 3750000000.0,
        'TITINOKTAVIA' => 3850000000.0,
        'FARIDRAMOLDONI' => 3700000000.0,
        'FARIDROMADLONI' => 3700000000.0,
    ];

    /** @param array<string, mixed>|null $branchScope */
    public function payload(?string $requestedPeriod, ?array $branchScope = null, bool $forceRefresh = false): array
    {
        $pipeline = $this->pipelinePayload($branchScope, $forceRefresh);
        $kprPipeline = $this->kprPipelinePayload($branchScope, $forceRefresh);
        $quadrants = $this->quadrantPayload($requestedPeriod, $branchScope);

        return [
            'meta' => [
                'scope' => (string) ($branchScope['key'] ?? UserBranchScope::AREA_SCOPE),
                'scope_label' => (string) ($branchScope['label'] ?? 'Area 6'),
                'period' => $quadrants['period'] ?? null,
                'period_label' => $quadrants['period_label'] ?? 'Belum ada data',
                'generated_at' => now()->toIso8601String(),
            ],
            'pipeline' => $pipeline,
            'kpr_pipeline' => $kprPipeline,
            'quadrants' => $quadrants,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function parseInstitutionCsv(string $csv, array $source): array
    {
        $matrix = $this->csvMatrix($csv);
        $headerIndex = collect($matrix)->search(function (array $row): bool {
            $labels = array_map(fn ($value): string => $this->normaliseLabel((string) $value), $row);

            return in_array('NAMA INSTANSI', $labels, true)
                && collect($labels)->contains(fn (string $label): bool => str_starts_with($label, 'POTENSI BRIG'));
        });

        if ($headerIndex === false) {
            throw new \RuntimeException('Header Nama Instansi dan Potensi Briguna tidak ditemukan.');
        }

        $header = $matrix[(int) $headerIndex];
        $columns = [
            'institution' => $this->findColumn($header, ['NAMA INSTANSI']),
            'leader' => $this->findColumn($header, ['KEPALA INSTANSI']),
            'rm_1' => $this->findColumn($header, ['RM PIC 1']),
            'rm_2' => $this->findColumn($header, ['RM PIC 2']),
            'employees' => $this->findColumn($header, ['TOTAL PEGAWAI']),
            'potential' => $this->findColumnStartingWith($header, 'POTENSI BRIG'),
            'served' => $this->findColumn($header, ['SUDAH TERLAYANI']),
            'payroll' => $this->findColumn($header, ['PAYROLL']),
            'salary' => $this->findColumn($header, ['GAJI INSTANSI']),
            'mtd_debtors' => $this->findColumn($header, ['MTD DEB']),
        ];
        $latestOutstanding = $this->lastColumnBefore($header, ['OUTSTANDING', 'OS'], $columns['potential']);

        $rows = collect(array_slice($matrix, (int) $headerIndex + 1))
            ->map(function (array $row) use ($columns, $latestOutstanding): array {
                $institution = $this->clean($this->cell($row, $columns['institution']));
                $institutionToken = $this->normaliseLabel($institution);
                $rmNames = collect([$columns['rm_1'], $columns['rm_2']])
                    ->filter(fn (int $column): bool => $column >= 0)
                    ->map(fn (int $column): string => $this->clean($this->cell($row, $column)))
                    ->filter(fn (string $name): bool => $name !== '' && $name !== '-')
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'institution' => $institution,
                    'institution_token' => $institutionToken,
                    'leader' => $this->clean($this->cell($row, $columns['leader'])),
                    'rm_names' => $rmNames,
                    'employees' => (int) round($this->number($this->cell($row, $columns['employees'])) ?? 0),
                    'potential' => (int) round($this->number($this->cell($row, $columns['potential'])) ?? 0),
                    'served' => $this->clean($this->cell($row, $columns['served'])),
                    'payroll' => $this->clean($this->cell($row, $columns['payroll'])),
                    'salary' => $this->clean($this->cell($row, $columns['salary'])),
                    'mtd_debtors' => (int) round($this->number($this->cell($row, $columns['mtd_debtors'])) ?? 0),
                    'latest_outstanding' => $this->number($this->cell($row, $latestOutstanding)) ?? 0.0,
                ];
            })
            ->filter(fn (array $row): bool => $row['institution'] !== ''
                && ! in_array($row['institution_token'], ['TOTAL', 'JUMLAH', 'GRAND TOTAL'], true))
            ->sort(function (array $left, array $right): int {
                $potential = $right['potential'] <=> $left['potential'];
                if ($potential !== 0) {
                    return $potential;
                }

                $employees = $right['employees'] <=> $left['employees'];

                return $employees !== 0
                    ? $employees
                    : strnatcasecmp($left['institution'], $right['institution']);
            })
            ->take(10)
            ->values()
            ->map(function (array $row, int $index): array {
                unset($row['institution_token']);
                $row['rank'] = $index + 1;

                return $row;
            });

        return [
            'key' => (string) ($source['key'] ?? ''),
            'branch_key' => (string) ($source['branch_key'] ?? ''),
            'branch' => (string) ($source['branch'] ?? ''),
            'label' => (string) ($source['label'] ?? ''),
            'sheet' => (string) ($source['sheet'] ?? ''),
            'source_url' => $this->sourceUrl($source),
            'available' => $rows->isNotEmpty(),
            'stale' => false,
            'error' => '',
            'row_count' => $rows->count(),
            'total_potential' => (int) $rows->sum('potential'),
            'rows' => $rows->all(),
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function parseKprPipelineCsv(string $csv, array $source): array
    {
        $matrix = $this->csvMatrix($csv);
        $headerIndex = collect($matrix)->search(function (array $row): bool {
            $labels = array_map(fn ($value): string => $this->normaliseLabel((string) $value), $row);

            return in_array('NAMA RM', $labels, true)
                && collect($labels)->contains(fn (string $label): bool => str_starts_with($label, 'DIINPUT RM NAMA DEBITUR'))
                && collect($labels)->contains(fn (string $label): bool => str_starts_with($label, 'PLAFOND DALAM JUTAAN'));
        });

        if ($headerIndex === false) {
            throw new \RuntimeException('Header pipeline KPR tidak ditemukan.');
        }

        $header = $matrix[(int) $headerIndex];
        $columns = [
            'source_number' => $this->findColumn($header, ['NO']),
            'branch' => $this->findColumn($header, ['BRANCH OFFICE']),
            'rm' => $this->findColumn($header, ['NAMA RM']),
            'debtor' => $this->findColumnStartingWith($header, 'DIINPUT RM NAMA DEBITUR'),
            'facility' => $this->findColumn($header, ['FASILITAS']),
            'income_type' => $this->findColumn($header, ['JENIS INCOME']),
            'developer' => $this->findColumn($header, ['NAMA DEVELOPER']),
            'planned_realisation' => $this->findColumn($header, ['TGL RENCANA REAL']),
            'plafond_juta' => $this->findColumnStartingWith($header, 'PLAFOND DALAM JUTAAN'),
            'process' => $this->findColumnStartingWith($header, 'PLAFON DALAM JUTAAN KETERANGAN PROSES'),
        ];

        $rows = collect(array_slice($matrix, (int) $headerIndex + 1))
            ->map(function (array $row) use ($columns): array {
                return [
                    'source_number' => $this->clean($this->cell($row, $columns['source_number'])),
                    'branch' => $this->clean($this->cell($row, $columns['branch'])),
                    'rm' => $this->clean($this->cell($row, $columns['rm'])),
                    'debtor' => $this->clean($this->cell($row, $columns['debtor'])),
                    'facility' => $this->clean($this->cell($row, $columns['facility'])),
                    'income_type' => $this->clean($this->cell($row, $columns['income_type'])),
                    'developer' => $this->clean($this->cell($row, $columns['developer'])),
                    'planned_realisation' => $this->clean($this->cell($row, $columns['planned_realisation'])),
                    'plafond_juta' => $this->number($this->cell($row, $columns['plafond_juta'])) ?? 0.0,
                    'process' => $this->clean($this->cell($row, $columns['process'])),
                ];
            })
            ->filter(fn (array $row): bool => $row['debtor'] !== '')
            ->values();

        return [
            'visible' => true,
            'key' => (string) ($source['key'] ?? ''),
            'branch_key' => (string) ($source['branch_key'] ?? ''),
            'branch' => (string) ($source['branch'] ?? ''),
            'label' => (string) ($source['label'] ?? ''),
            'sheet' => (string) ($source['sheet'] ?? ''),
            'source_url' => $this->sourceUrl($source),
            'available' => $rows->isNotEmpty(),
            'stale' => false,
            'error' => '',
            'row_count' => $rows->count(),
            'rm_count' => $rows->pluck('rm')->filter()->unique()->count(),
            'total_plafond_juta' => (float) $rows->sum('plafond_juta'),
            'rows' => $rows->all(),
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed>|null $branchScope */
    private function pipelinePayload(?array $branchScope, bool $forceRefresh): array
    {
        $scopeKey = (string) ($branchScope['key'] ?? '');
        $definitions = collect(self::SOURCES)
            ->map(fn (array $source, string $key): array => array_merge($source, ['key' => $key]))
            ->when($scopeKey !== '', fn (Collection $sources) => $sources->where('branch_key', $scopeKey));
        $payloads = collect();
        $pending = collect();

        foreach ($definitions as $key => $source) {
            $cached = ! $forceRefresh ? Cache::get($this->cacheKey((string) $key)) : null;
            if (is_array($cached)) {
                $payloads->put($key, $cached);
            } else {
                $pending->put($key, $source);
            }
        }

        $responses = [];
        $poolError = '';
        if ($pending->isNotEmpty()) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($pending): array {
                    return $pending->map(function (array $source, string $key) use ($pool) {
                        return $pool->as($key)
                            ->connectTimeout(5)
                            ->timeout(20)
                            ->get($this->csvUrl($source));
                    })->values()->all();
                });
            } catch (Throwable $exception) {
                $poolError = $exception->getMessage();
            }
        }

        foreach ($pending as $key => $source) {
            try {
                $response = $responses[$key] ?? null;
                if (! $response instanceof Response || ! $response->successful() || trim($response->body()) === '') {
                    throw new \RuntimeException('Google Sheet tidak merespons dengan CSV yang valid.');
                }

                $parsed = $this->parseInstitutionCsv($response->body(), $source);
                Cache::put($this->cacheKey((string) $key), $parsed, now()->addMinutes(10));
                Cache::put($this->stableCacheKey((string) $key), $parsed, now()->addDays(2));
                $payloads->put($key, $parsed);
            } catch (Throwable $exception) {
                $stable = Cache::get($this->stableCacheKey((string) $key));
                if (is_array($stable)) {
                    $stable['stale'] = true;
                    $stable['error'] = $exception->getMessage();
                    $payloads->put($key, $stable);
                } else {
                    $payloads->put($key, [
                        'key' => $key,
                        'branch_key' => $source['branch_key'],
                        'branch' => $source['branch'],
                        'label' => $source['label'],
                        'sheet' => $source['sheet'],
                        'source_url' => $this->sourceUrl($source),
                        'available' => false,
                        'stale' => false,
                        'error' => $exception->getMessage() ?: $poolError,
                        'row_count' => 0,
                        'total_potential' => 0,
                        'rows' => [],
                        'fetched_at' => null,
                    ]);
                }
            }
        }

        $ordered = $definitions->keys()
            ->map(fn (string $key): array => (array) $payloads->get($key, []))
            ->filter()
            ->values();

        return [
            'available' => $ordered->contains(fn (array $source): bool => (bool) ($source['available'] ?? false)),
            'sources' => $ordered->all(),
            'source_count' => $ordered->count(),
            'institution_count' => (int) $ordered->sum('row_count'),
            'total_potential' => (int) $ordered->sum('total_potential'),
        ];
    }

    /** @param array<string, mixed>|null $branchScope */
    private function kprPipelinePayload(?array $branchScope, bool $forceRefresh): array
    {
        $source = self::KPR_PIPELINE_SOURCE;
        $scopeKey = (string) ($branchScope['key'] ?? '');
        if ($scopeKey !== '' && $scopeKey !== $source['branch_key']) {
            return [
                'visible' => false,
                'available' => false,
                'rows' => [],
            ];
        }

        $cacheKey = $this->cacheKey((string) $source['key']);
        $cached = ! $forceRefresh ? Cache::get($cacheKey) : null;
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(20)
                ->get($this->csvUrl($source));
            if (! $response->successful() || trim($response->body()) === '') {
                throw new \RuntimeException('Google Sheet pipeline KPR tidak merespons dengan CSV yang valid.');
            }

            $parsed = $this->parseKprPipelineCsv($response->body(), $source);
            Cache::put($cacheKey, $parsed, now()->addMinutes(10));
            Cache::put($this->stableCacheKey((string) $source['key']), $parsed, now()->addDays(2));

            return $parsed;
        } catch (Throwable $exception) {
            $stable = Cache::get($this->stableCacheKey((string) $source['key']));
            if (is_array($stable)) {
                $stable['stale'] = true;
                $stable['error'] = $exception->getMessage();

                return $stable;
            }

            return [
                'visible' => true,
                'key' => $source['key'],
                'branch_key' => $source['branch_key'],
                'branch' => $source['branch'],
                'label' => $source['label'],
                'sheet' => $source['sheet'],
                'source_url' => $this->sourceUrl($source),
                'available' => false,
                'stale' => false,
                'error' => $exception->getMessage(),
                'row_count' => 0,
                'rm_count' => 0,
                'total_plafond_juta' => 0.0,
                'rows' => [],
                'fetched_at' => null,
            ];
        }
    }

    /** @param array<string, mixed>|null $branchScope */
    private function quadrantPayload(?string $requestedPeriod, ?array $branchScope): array
    {
        $empty = [
            'available' => false,
            'period' => null,
            'period_label' => 'Belum ada data',
            'branches' => [],
        ];

        if (! Schema::hasTable('performance_rm_snapshots')) {
            return $empty;
        }

        $requiredColumns = ['periode', 'cabang', 'segmen', 'produk', 'rm', 'realisasi_os'];
        foreach ($requiredColumns as $column) {
            if (! Schema::hasColumn('performance_rm_snapshots', $column)) {
                return $empty;
            }
        }

        $scopeKey = (string) ($branchScope['key'] ?? '');
        $areaBranches = collect(self::BRANCHES);
        $branches = $areaBranches
            ->when($scopeKey !== '', fn (Collection $items) => $items->only([$scopeKey]));
        if ($branches->isEmpty()) {
            return $empty;
        }

        $periodQuery = DB::table('performance_rm_snapshots')
            ->where('segmen', 'CONSUMER')
            ->whereIn(DB::raw('UPPER(TRIM(cabang))'), $areaBranches->values()->all());
        if ($requestedPeriod) {
            $requestedDate = Carbon::parse($requestedPeriod);
            $period = (clone $periodQuery)
                ->whereBetween('periode', [
                    $requestedDate->copy()->startOfMonth()->toDateString(),
                    $requestedDate->copy()->endOfMonth()->toDateString(),
                ])
                ->max('periode');
            if (! $period) {
                $period = $periodQuery
                    ->where('periode', '<=', $requestedDate->toDateString())
                    ->max('periode');
            }
        } else {
            $period = $periodQuery->max('periode');
        }
        if (! $period) {
            return $empty;
        }

        $end = Carbon::parse((string) $period)->toDateString();
        $start = Carbon::parse($end)->startOfYear()->toDateString();
        $availablePeriods = DB::table('performance_rm_snapshots')
            ->whereBetween('periode', [$start, $end])
            ->where('segmen', 'CONSUMER')
            ->whereIn(DB::raw('UPPER(TRIM(cabang))'), $areaBranches->values()->all())
            ->whereIn('produk', array_column(self::PRODUCTS, 'snapshot'))
            ->select('periode')
            ->distinct()
            ->orderBy('periode')
            ->pluck('periode')
            ->map(fn ($value): string => Carbon::parse($value)->toDateString())
            ->groupBy(fn (string $date): string => Carbon::parse($date)->format('Y-m'))
            ->map(fn (Collection $dates): string => (string) $dates->last())
            ->values();

        if ($availablePeriods->isEmpty()) {
            return $empty;
        }

        $snapshotRows = DB::table('performance_rm_snapshots')
            ->whereIn('periode', $availablePeriods->all())
            ->where('segmen', 'CONSUMER')
            ->whereIn(DB::raw('UPPER(TRIM(cabang))'), $areaBranches->values()->all())
            ->whereIn('produk', array_column(self::PRODUCTS, 'snapshot'))
            ->whereNotNull('rm')
            ->whereRaw("TRIM(rm) <> ''")
            ->select('periode', 'cabang', 'produk', 'rm', 'realisasi_os')
            ->get();
        $snapshotRows = $this->applyLatestConsumerSnapshotAssignments(
            $this->applyBrihcPrimaryConsumerAssignments($snapshotRows, $end)
        );
        $targetMaps = $this->productTargetMaps();

        $buildBranchPayload = function (string $branchKey, string $branchLabel, Collection $branchRows) use (
            $availablePeriods,
            $targetMaps
        ): array {
            $products = [];

            foreach (self::PRODUCTS as $productKey => $definition) {
                $productRows = $branchRows->where('produk', $definition['snapshot']);
                $monthlyRows = $availablePeriods->map(function (string $date) use (
                    $productRows,
                    $targetMaps,
                    $productKey
                ): array {
                    $monthCarbon = Carbon::parse($date);

                    $throughPeriod = $productRows->filter(
                        fn ($row): bool => Carbon::parse($row->periode)->toDateString() <= $date
                    );
                    $grouped = $throughPeriod->groupBy(
                        fn ($row): string => $this->rmTargetKey((string) $row->rm)
                    )->filter(fn (Collection $rows, string $key): bool => $key !== '');
                    $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
                    $rmDetails = [1 => [], 2 => [], 3 => [], 4 => []];

                    foreach ($grouped as $targetKey => $rmRows) {
                        $target = (float) ($targetMaps[$productKey][$targetKey] ?? 0.0);
                        if ($target <= 0.0) {
                            continue;
                        }

                        $monthRows = $rmRows->filter(
                            fn ($row): bool => Carbon::parse($row->periode)->toDateString() === $date
                        );
                        // A single RM can have several snapshot fragments
                        // (unit/account attribution) in one period. The Kanwil
                        // workbook classifies the RM total, not the first row.
                        $monthRealisation = (float) $monthRows->sum('realisasi_os');
                        $quadrant = ConsumerKanwilReference::calculateQuadrant($monthRealisation, $target) ?? 4;

                        $counts[$quadrant]++;
                        $latestRmRow = $rmRows->sortBy('periode')->last();
                        $rmDetails[$quadrant][] = [
                            'name' => $this->rmDisplayName((string) ($latestRmRow->rm ?? $targetKey)),
                            'branch' => $this->branchDisplayName((string) ($latestRmRow->cabang ?? '')),
                        ];
                    }

                    foreach ($rmDetails as $quadrant => $details) {
                        $rmDetails[$quadrant] = collect($details)
                            ->sortBy(fn (array $detail): string => $detail['name'].'|'.$detail['branch'])
                            ->values()
                            ->all();
                    }

                    return [
                        'period' => $date,
                        'label' => Carbon::parse($date)->translatedFormat('M y'),
                        'q1' => $counts[1],
                        'q2' => $counts[2],
                        'q3' => $counts[3],
                        'q4' => $counts[4],
                        'total' => array_sum($counts),
                        'source_total' => $grouped->count(),
                        'rm_details' => [
                            'q1' => $rmDetails[1],
                            'q2' => $rmDetails[2],
                            'q3' => $rmDetails[3],
                            'q4' => $rmDetails[4],
                        ],
                    ];
                })->values();
                $latest = (array) ($monthlyRows->last() ?? []);

                $products[$productKey] = [
                    'key' => $productKey,
                    'label' => $definition['label'],
                    'available' => $monthlyRows->contains(fn (array $row): bool => $row['source_total'] > 0),
                    'rows' => $monthlyRows->all(),
                    'coverage' => [
                        'classified' => (int) ($latest['total'] ?? 0),
                        'source_total' => (int) ($latest['source_total'] ?? 0),
                        'unclassified' => max(0, (int) ($latest['source_total'] ?? 0) - (int) ($latest['total'] ?? 0)),
                    ],
                ];
            }

            return [
                'key' => $branchKey,
                'label' => $branchKey === UserBranchScope::AREA_SCOPE
                    ? 'Area 6 (4 KC)'
                    : $this->branchDisplayName($branchLabel),
                'products' => $products,
            ];
        };

        $branchPayloads = $branches->map(function (string $branchLabel, string $branchKey) use (
            $snapshotRows,
            $buildBranchPayload
        ): array {
            $branchRows = $snapshotRows->filter(
                fn ($row): bool => strtoupper(trim((string) $row->cabang)) === $branchLabel
            );

            return $buildBranchPayload($branchKey, $branchLabel, $branchRows);
        })->values();

        if ($scopeKey === '') {
            $branchPayloads->prepend(
                $buildBranchPayload(UserBranchScope::AREA_SCOPE, 'AREA 6', $snapshotRows)
            );
        }

        return [
            'available' => $branchPayloads->contains(function (array $branch): bool {
                return collect($branch['products'])->contains(fn (array $product): bool => $product['available']);
            }),
            'period' => $end,
            'period_label' => Carbon::parse($end)->translatedFormat('d M Y'),
            'branches' => $branchPayloads->all(),
        ];
    }

    /**
     * BRIHC menjadi roster identitas RM Konsumer, sedangkan cabang historis
     * tetap mengikuti snapshot Daily Loan pada periode yang diminta.
     *
     * @param  Collection<int, object>  $snapshotRows
     * @return Collection<int, object>
     */
    public function applyBrihcPrimaryConsumerAssignments(Collection $snapshotRows, string $period): Collection
    {
        $productsInScope = $snapshotRows
            ->pluck('produk')
            ->map(static fn ($product): string => strtoupper(trim((string) $product)))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $roster = $this->consumerBrihcRoster($snapshotRows);
        if ($roster->isEmpty()) {
            return $snapshotRows;
        }

        $rosterByIdentity = $roster->groupBy('identity');
        $matchedRoster = collect();
        $mappedRows = $snapshotRows
            ->map(function (object $row) use ($rosterByIdentity, $matchedRoster): ?object {
                $candidates = collect($this->consumerRmIdentityKeys((string) ($row->rm ?? '')))
                    ->flatMap(fn (string $key): Collection => $rosterByIdentity->get($key, collect()))
                    ->unique(fn (array $reference): string => $reference['identity'].'|'.$reference['product'])
                    ->values();
                if ($candidates->isEmpty()) {
                    if (abs((float) ($row->realisasi_os ?? 0.0)) <= 0.0001) {
                        return null;
                    }
                    $row->roster_source = 'daily_loan_backup';

                    return $row;
                }

                $reference = $candidates->first(
                    fn (array $candidate): bool => $candidate['active']
                        && $candidate['product'] === (string) ($row->produk ?? '')
                );
                if (! is_array($reference)) {
                    // Perubahan jabatan/produk saat ini tidak boleh menghapus
                    // realisasi historis yang valid pada snapshot Daily Loan.
                    if (abs((float) ($row->realisasi_os ?? 0.0)) <= 0.0001) {
                        return null;
                    }
                    $row->roster_source = 'daily_loan_historical';

                    return $row;
                }

                $matchedRoster->put($reference['product'].'|'.$reference['rm'], true);
                $row->rm = $reference['rm'];
                $row->roster_source = 'brihc_primary';

                return $row;
            })
            ->filter()
            ->values();

        // RM aktif BRIHC tanpa snapshot nominatif tetap masuk sebagai realisasi nol
        // pada periode berjalan, sehingga kuadrannya tidak hilang dari Landing.
        $rosterOnlyRows = $roster
            ->filter(static fn (array $reference): bool => $reference['active']
                && in_array($reference['product'], $productsInScope, true))
            ->groupBy(fn (array $reference): string => $reference['product'].'|'.$reference['rm'])
            ->map(static fn (Collection $references): array => (array) $references->first())
            ->reject(fn (array $reference): bool => $matchedRoster->has($reference['product'].'|'.$reference['rm']))
            ->map(static function (array $reference) use ($period): object {
                return (object) [
                    'periode' => $period,
                    'cabang' => $reference['branch'],
                    'produk' => $reference['product'],
                    'rm' => $reference['rm'],
                    'realisasi_os' => 0.0,
                    'roster_source' => 'brihc_primary_roster_only',
                ];
            });

        return $mappedRows->concat($rosterOnlyRows)->values();
    }

    /**
     * Consolidate an RM's year-to-date realization under the latest branch
     * assignment that exists in the requested Daily Loan snapshot range.
     * This preserves the historical placement for a past cutoff while avoiding
     * duplicate RM counts after a transfer between Area 6 branches.
     *
     * @param  Collection<int, object>  $snapshotRows
     * @return Collection<int, object>
     */
    public function applyLatestConsumerSnapshotAssignments(Collection $snapshotRows): Collection
    {
        $assignmentByRm = $snapshotRows
            ->filter(fn (object $row): bool => $this->rmTargetKey((string) ($row->rm ?? '')) !== '')
            ->groupBy(fn (object $row): string => strtoupper(trim((string) ($row->produk ?? '')))
                .'|'.$this->rmTargetKey((string) ($row->rm ?? '')))
            ->map(function (Collection $rows): object {
                return $rows->sort(function (object $left, object $right): int {
                    $periodOrder = strcmp(
                        Carbon::parse((string) ($left->periode ?? '1900-01-01'))->toDateString(),
                        Carbon::parse((string) ($right->periode ?? '1900-01-01'))->toDateString()
                    );
                    if ($periodOrder !== 0) {
                        return $periodOrder;
                    }

                    return abs((float) ($left->realisasi_os ?? 0.0))
                        <=> abs((float) ($right->realisasi_os ?? 0.0));
                })->last();
            });

        return $snapshotRows->map(function (object $row) use ($assignmentByRm): object {
            $identity = $this->rmTargetKey((string) ($row->rm ?? ''));
            if ($identity === '') {
                return $row;
            }

            $key = strtoupper(trim((string) ($row->produk ?? ''))).'|'.$identity;
            $assignment = $assignmentByRm->get($key);
            if (! is_object($assignment)) {
                return $row;
            }

            foreach (['cabang', 'unit', 'branch_code'] as $column) {
                if (property_exists($row, $column) && property_exists($assignment, $column)) {
                    $row->{$column} = $assignment->{$column};
                }
            }

            return $row;
        })->values();
    }

    /**
     * @param  Collection<int, object>  $snapshotRows
     * @return Collection<int, array{identity:string,branch:string,product:string,rm:string,active:bool}>
     */
    private function consumerBrihcRoster(Collection $snapshotRows): Collection
    {
        if (! Schema::hasTable('brihc_pemasar')) {
            return collect();
        }

        $requiredColumns = ['pernr', 'completename', 'positiondesc', 'psadesc'];
        if (collect($requiredColumns)->contains(
            static fn (string $column): bool => ! Schema::hasColumn('brihc_pemasar', $column)
        )) {
            return collect();
        }

        $candidatePns = $snapshotRows
            ->flatMap(fn (object $row): array => $this->consumerRmIdentityKeys((string) ($row->rm ?? '')))
            ->filter(static fn (string $key): bool => str_starts_with($key, 'PN:'))
            ->map(static fn (string $key): string => substr($key, 3))
            ->flatMap(static fn (string $pn): array => [$pn, str_pad($pn, 8, '0', STR_PAD_LEFT)])
            ->unique()
            ->values()
            ->all();
        $candidateNames = $snapshotRows
            ->flatMap(fn (object $row): array => $this->consumerRmIdentityKeys((string) ($row->rm ?? '')))
            ->filter(static fn (string $key): bool => str_starts_with($key, 'NAME:'))
            ->map(static fn (string $key): string => substr($key, 5))
            ->values()
            ->all();
        $productByRole = [
            'RM BISNIS KONSUMER - BRIGUNA' => 'BRIGUNA-KONSUMER',
            'RM BISNIS KONSUMER - KPR' => 'KPR',
        ];
        $branches = array_values(self::BRANCHES);

        $records = DB::table('brihc_pemasar')
            ->select('pernr', 'completename', 'positiondesc', 'psadesc')
            ->where(function ($query) use ($productByRole, $branches, $candidatePns, $candidateNames): void {
                $query->where(function ($activeQuery) use ($productByRole, $branches): void {
                    $activeQuery
                        ->whereIn(DB::raw("UPPER(TRIM(COALESCE(positiondesc, '')) )"), array_keys($productByRole))
                        ->whereIn(DB::raw("UPPER(TRIM(COALESCE(psadesc, '')) )"), $branches);
                });
                if ($candidatePns !== []) {
                    $query->orWhereIn('pernr', $candidatePns);
                }
                if ($candidateNames !== []) {
                    $query->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(completename, ''))), ' ', ''), '-', ''), '.', '') IN ("
                        .implode(',', array_fill(0, count($candidateNames), '?')).')',
                        $candidateNames
                    );
                }
            })
            ->get()
            ->flatMap(function (object $record) use ($productByRole, $branches): Collection {
                $role = strtoupper(trim((string) ($record->positiondesc ?? '')));
                $branch = strtoupper(trim((string) ($record->psadesc ?? '')));
                $product = $productByRole[$role] ?? null;
                $name = trim((string) ($record->completename ?? ''));
                $pn = ltrim(preg_replace('/\D+/', '', (string) ($record->pernr ?? '')) ?? '', '0');
                $identityKeys = $this->consumerRmIdentityKeys(
                    ($pn !== '' ? $pn.' - ' : '').$name
                );
                if ($identityKeys === [] || $name === '') {
                    return collect();
                }

                $rm = ($pn !== '' ? str_pad($pn, 8, '0', STR_PAD_LEFT).' - ' : '').$name;

                return collect($identityKeys)->map(static fn (string $identity): array => [
                    'identity' => $identity,
                    'branch' => $branch,
                    'product' => $product ?? '',
                    'rm' => $rm,
                    'active' => $product !== null && in_array($branch, $branches, true),
                ]);
            })
            ->groupBy(fn (array $reference): string => $reference['identity'].'|'.$reference['product'])
            ->map(static fn (Collection $references): array => $references
                ->sortByDesc(static fn (array $reference): bool => $reference['active'])
                ->first())
            ->values();

        return $records;
    }

    /** @return array<int, string> */
    private function consumerRmIdentityKeys(string $rm): array
    {
        $raw = trim($rm);
        if ($raw === '') {
            return [];
        }

        $keys = [];
        $prefix = trim(explode('-', $raw, 2)[0]);
        $pn = ltrim(preg_replace('/\D+/', '', $prefix) ?? '', '0');
        if ($pn !== '') {
            $keys[] = 'PN:'.$pn;
        }

        $name = trim(explode('-', $raw, 2)[1] ?? $raw);
        $nameKey = preg_replace('/[^A-Z0-9]+/', '', strtoupper($name)) ?? '';
        if ($nameKey !== '') {
            $keys[] = 'NAME:'.$nameKey;
        }

        return array_values(array_unique($keys));
    }

    /** @return array<string, array<string, float>> */
    private function productTargetMaps(): array
    {
        $maps = [
            'briguna' => self::BRIGUNA_TARGET_FALLBACK,
            'kpr' => [],
        ];
        if (! Schema::hasTable('performance_targets')) {
            return $maps;
        }
        foreach (['category', 'rm_name', 'target_os'] as $column) {
            if (! Schema::hasColumn('performance_targets', $column)) {
                return $maps;
            }
        }

        $categoryToProduct = collect(self::PRODUCTS)
            ->mapWithKeys(fn (array $definition, string $key): array => [$definition['target'] => $key]);
        DB::table('performance_targets')
            ->whereIn('category', $categoryToProduct->keys()->all())
            ->select('category', 'rm_name', 'target_os')
            ->get()
            ->groupBy(fn ($row): string => strtoupper(trim((string) $row->category)))
            ->each(function (Collection $categoryRows, string $category) use (&$maps, $categoryToProduct): void {
                $productKey = $categoryToProduct->get($category);
                if (! is_string($productKey)) {
                    return;
                }

                $categoryRows->groupBy(fn ($row): string => $this->rmTargetKey((string) $row->rm_name))
                    ->each(function (Collection $rows, string $targetKey) use (&$maps, $productKey): void {
                        $target = (float) $rows->sum('target_os');
                        if ($targetKey !== '' && $target > 0.0) {
                            $maps[$productKey][$targetKey] = $target;
                        }
                    });
            });

        // Target Kanwil pada workbook kuadran menjadi otoritas untuk roster
        // Briguna yang sudah diaudit, termasuk koreksi target Novan/NAVAN.
        $kanwilBrigunaTargets = [];
        foreach (ConsumerKanwilReference::ROSTER as $rm) {
            $kanwilBrigunaTargets[$rm['name_key']] = (float) $rm['target_os'];
            $kanwilBrigunaTargets['PN:'.ltrim($rm['pn'], '0')] = (float) $rm['target_os'];
        }
        $maps['briguna'] = array_replace($maps['briguna'], self::BRIGUNA_TARGET_FALLBACK, $kanwilBrigunaTargets);

        return $maps;
    }

    /** @return array<int, array<int, string>> */
    private function csvMatrix(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Buffer CSV tidak dapat dibuat.');
        }

        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = array_map(fn ($value): string => $this->clean((string) $value), $row);
        }
        fclose($stream);

        return $rows;
    }

    /** @param array<int, string> $row */
    private function cell(array $row, int $column): string
    {
        return $column >= 0 ? (string) ($row[$column] ?? '') : '';
    }

    /** @param array<int, string> $header */
    private function findColumn(array $header, array $aliases): int
    {
        $normalisedAliases = array_map(fn (string $alias): string => $this->normaliseLabel($alias), $aliases);
        foreach ($header as $index => $value) {
            if (in_array($this->normaliseLabel((string) $value), $normalisedAliases, true)) {
                return (int) $index;
            }
        }

        return -1;
    }

    /** @param array<int, string> $header */
    private function findColumnStartingWith(array $header, string $prefix): int
    {
        $normalisedPrefix = $this->normaliseLabel($prefix);
        foreach ($header as $index => $value) {
            if (str_starts_with($this->normaliseLabel((string) $value), $normalisedPrefix)) {
                return (int) $index;
            }
        }

        return -1;
    }

    /** @param array<int, string> $header */
    private function lastColumnBefore(array $header, array $aliases, int $before): int
    {
        $normalisedAliases = array_map(fn (string $alias): string => $this->normaliseLabel($alias), $aliases);
        $match = -1;
        foreach ($header as $index => $value) {
            if (($before < 0 || $index < $before)
                && in_array($this->normaliseLabel((string) $value), $normalisedAliases, true)) {
                $match = (int) $index;
            }
        }

        return $match;
    }

    private function normaliseLabel(string $value): string
    {
        $value = strtoupper($this->clean($value));

        return trim(preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? '');
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $value)) ?? $value);
    }

    private function number(string $value): ?float
    {
        $value = $this->clean($value);
        if ($value === '' || $value === '-' || str_starts_with($value, '#')) {
            return null;
        }

        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $numeric = preg_replace('/[^0-9,.-]/', '', $value) ?? '';
        if ($numeric === '' || $numeric === '-') {
            return null;
        }

        if (str_contains($numeric, ',') && str_contains($numeric, '.')) {
            $numeric = strrpos($numeric, ',') > strrpos($numeric, '.')
                ? str_replace(',', '.', str_replace('.', '', $numeric))
                : str_replace(',', '', $numeric);
        } elseif (str_contains($numeric, ',')) {
            $numeric = preg_match('/,\d{1,2}$/', $numeric) === 1
                ? str_replace(',', '.', str_replace('.', '', $numeric))
                : str_replace(',', '', $numeric);
        } elseif (substr_count($numeric, '.') > 1 || preg_match('/\.\d{3}$/', $numeric) === 1) {
            $numeric = str_replace('.', '', $numeric);
        }

        if (! is_numeric($numeric)) {
            return null;
        }

        $number = (float) $numeric;

        return $negative ? -abs($number) : $number;
    }

    /** @param array<string, mixed> $source */
    private function csvUrl(array $source): string
    {
        return 'https://docs.google.com/spreadsheets/d/'
            .$source['spreadsheet_id']
            .'/gviz/tq?tqx=out:csv&sheet='
            .rawurlencode((string) $source['sheet']);
    }

    /** @param array<string, mixed> $source */
    private function sourceUrl(array $source): string
    {
        return 'https://docs.google.com/spreadsheets/d/'.$source['spreadsheet_id'].'/edit';
    }

    private function cacheKey(string $key): string
    {
        return self::CACHE_PREFIX.':'.$key;
    }

    private function stableCacheKey(string $key): string
    {
        return $this->cacheKey($key).':stable';
    }

    private function rmTargetKey(string $rm): string
    {
        $name = trim(explode('-', $rm, 2)[1] ?? $rm);

        return preg_replace('/[^A-Z0-9]/', '', strtoupper($name)) ?? '';
    }

    private function rmDisplayName(string $rm): string
    {
        $name = trim(explode('-', $rm, 2)[1] ?? $rm);

        return $name !== '' ? $name : '-';
    }

    private function branchDisplayName(string $branch): string
    {
        $label = preg_replace('/^Kc /', 'KC ', ucwords(strtolower(trim($branch))));

        return $label !== null && $label !== '' ? $label : '-';
    }
}
