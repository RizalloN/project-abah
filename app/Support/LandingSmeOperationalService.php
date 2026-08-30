<?php

namespace App\Support;

use App\Http\Controllers\Report\KinerjaRmReportController;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;

final class LandingSmeOperationalService
{
    private const CACHE_KEY = 'landing:sme:external:v4';

    private const ALLOWED_UNITS = [
        '45' => 'KC MADIUN',
        '49' => 'KC MAGETAN',
        '57' => 'KC NGAWI',
        '70' => 'KC PONOROGO',
        '552' => 'KC MADIUN',
        '2109' => 'KC MADIUN',
        '2167' => 'KC MADIUN',
        '2204' => 'KC PONOROGO',
    ];

    private const SOURCES = [
        'hot_prospects' => [
            'spreadsheet_id' => '125eoTd_H49Rh-vNeNGkizESwhA_UnSA5fJDJOuH7cMw',
            'sheet' => 'Rekap Progress',
            'label' => 'Monitoring Hot Prospek',
        ],
        'rtl_pipeline' => [
            'spreadsheet_id' => '1wSLhDFlgx3eSgYLCRp_0cpPymmsnCxeRHV_saCuWYzw',
            'sheet' => 'REKAP',
            'label' => 'RTL Pipeline',
        ],
        'extension' => [
            'spreadsheet_id' => '1jRQfImezje9Zzn6PuS4UKQVcphwsddiuPCzaFuywBcM',
            'sheet' => 'REKAP',
            'label' => 'Perpanjangan',
        ],
        'restructuring' => [
            'spreadsheet_id' => '1y1nCOWaDmiwCHSEPHc6eQUfAnYsTVv4J1uU6jqlFEso',
            'sheet' => 'PENGERJAAN PAKET RESTRUK',
            'label' => 'Pipeline Restruk',
        ],
        'kanwil_decisions' => [
            'spreadsheet_id' => '1mjragUhRtsBXwIQTMg7m2BSooPTqDl9OXqhvTR8IjCE',
            'sheet' => 'Sheet1',
            'label' => 'Putusan Pipeline Restruk Kanwil',
        ],
    ];

    private const HOT_STATUSES = [
        'belum_ots' => 'Belum OTS Pemutus',
        'analisa_rm' => 'Analisa RM (MAK)',
        'verifikasi_adk' => 'Verifikasi ADK',
        'menunggu_putusan' => 'Menunggu Putusan',
        'sudah_diputus' => 'Sudah Diputus',
        'realisasi' => 'Realisasi',
        'batal' => 'Batal',
    ];

    private const EXTENSION_STATUSES = [
        'analisa_rm' => 'Analisa RM',
        'menunggu_putusan' => 'Menunggu Putusan',
        'sudah_diputus' => 'Sudah Diputus',
        'sudah_diperpanjang' => 'Sudah Diperpanjang',
        'lunas' => 'Lunas',
        'belum_tl' => 'Belum TL',
        'tidak_diperpanjang' => 'Tidak Diperpanjang',
    ];

    private const RESTRUCTURING_STATUSES = [
        'analisa_rm' => 'Analisa RM (MAK)',
        'verifikasi_adk' => 'Verifikasi ADK',
        'menunggu_putusan' => 'Menunggu Putusan',
        'sudah_diputus' => 'Sudah Diputus',
    ];

    private const VENDORS = [
        ['key' => 'petrokimia', 'label' => 'Vendor Petrokimia', 'sheet' => 'VENDOR PETROKIMIA', 'icon' => 'fas fa-flask', 'include' => true],
        ['key' => 'developer', 'label' => 'Developer', 'sheet' => 'DEVELOPER', 'icon' => 'fas fa-building', 'include' => true],
        ['key' => 'ahm', 'label' => 'AHM', 'sheet' => 'AHM', 'icon' => 'fas fa-motorcycle', 'include' => true],
        ['key' => 'charoen_pokphand', 'label' => 'PT Charoen Pokphand', 'sheet' => 'PIPELINE PT CHAROEN POKPHAND', 'icon' => 'fas fa-industry', 'include' => true],
        ['key' => 'mitra_bpp', 'label' => 'Mitra BPP', 'sheet' => 'mitra bpp', 'icon' => 'fas fa-handshake', 'include' => true],
        ['key' => 'hipmi', 'label' => 'HIPMI', 'sheet' => 'HIPMI', 'icon' => 'fas fa-users', 'include' => true],
        ['key' => 'mbg_va_bri', 'label' => 'MBG VA BRI', 'sheet' => 'MBG VA BRI', 'icon' => 'fas fa-utensils', 'include' => true],
        ['key' => 'value_chain_kanpus', 'label' => 'Value Chain Kanpus', 'sheet' => 'VALUE CHAIN KANPUS', 'icon' => 'fas fa-link', 'include' => true],
        ['key' => 'lunas_putus', 'label' => 'Lunas Putus', 'sheet' => 'LUNAS PUTUS', 'icon' => 'fas fa-check-circle', 'include' => true],
        ['key' => 'eksportir_dhe', 'label' => 'Eksportir DHE', 'sheet' => 'EKSPORTIR DHE', 'icon' => 'fas fa-globe-asia', 'include' => true],
        ['key' => 'potensi_kipk', 'label' => 'Potensi KIPK', 'sheet' => 'KIPK', 'icon' => 'fas fa-id-card', 'include' => true],
        ['key' => 'downline_medium', 'label' => 'Downline Nasabah Medium', 'sheet' => 'DOWNLINE MEDIUM', 'icon' => 'fas fa-sitemap', 'include' => true],
        ['key' => 'downline_commercial', 'label' => 'Downline Nasabah Commercial', 'sheet' => 'DOWNLINE NASABAH COMERCIAL', 'icon' => 'fas fa-project-diagram', 'include' => true],
        ['key' => 'pupuk_indonesia', 'label' => 'Pupuk Indonesia', 'sheet' => 'PUPUK INDONESIA', 'icon' => 'fas fa-seedling', 'include' => true],
        ['key' => 'kios_pupuk_lengkap', 'label' => 'Kios Pupuk Lengkap', 'sheet' => 'KIOS PUPUK LENGKAP', 'icon' => 'fas fa-store', 'include' => false],
    ];

    public function __construct(
        private readonly KinerjaRmReportController $kinerjaRmReportController
    ) {
    }

    /** @param array<string, mixed>|null $branchScope */
    public function payload(?array $branchScope = null, bool $forceRefresh = false): array
    {
        $external = $this->externalPayload($forceRefresh);
        $quadrants = $this->kinerjaRmReportController->landingSmallQuadrantSummary();

        return $this->buildScopedPayload($external, $quadrants, $branchScope);
    }

    /**
     * Memuat worksheet nominatif hanya saat pengguna membuka detail vendor.
     * Workbook sumber adalah file XLSX di Google Drive; parameter `sheet` pada
     * ekspor CSV-nya selalu kembali ke REKAP, sehingga worksheet dibaca langsung.
     *
     * @param array<string, mixed>|null $branchScope
     * @return array<string, mixed>
     */
    public function vendorNominatives(string $vendorKey, ?array $branchScope = null, bool $forceRefresh = false): array
    {
        $vendor = collect(self::VENDORS)->first(
            static fn (array $definition): bool => $definition['include'] && $definition['key'] === $vendorKey
        );
        if (! is_array($vendor)) {
            throw new \InvalidArgumentException('Vendor RTL tidak dikenali.');
        }

        $cacheKey = self::CACHE_KEY.':nominative:v1:'.$vendorKey;
        $stableKey = $cacheKey.':stable';
        $parsed = ! $forceRefresh ? Cache::get($cacheKey) : null;
        $stale = false;
        $error = '';

        if (! is_array($parsed)) {
            try {
                $parsed = $this->loadRtlVendorWorksheet((string) $vendor['sheet']);
                Cache::put($cacheKey, $parsed, now()->addMinutes(15));
                Cache::put($stableKey, $parsed, now()->addDays(2));
            } catch (Throwable $exception) {
                $parsed = Cache::get($stableKey);
                $stale = is_array($parsed);
                $error = $exception->getMessage();
                if (! is_array($parsed)) {
                    throw $exception;
                }
            }
        }

        $branch = strtoupper(trim((string) ($branchScope['upper_label'] ?? '')));
        $branchOrder = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $rows = collect((array) ($parsed['rows'] ?? []))
            ->when($branch !== '', static fn ($items) => $items->where('branch', $branch))
            ->sort(function (array $left, array $right) use ($branchOrder): int {
                $leftBranch = array_search($left['branch'] ?? '', $branchOrder, true);
                $rightBranch = array_search($right['branch'] ?? '', $branchOrder, true);
                $branchComparison = ($leftBranch === false ? 99 : $leftBranch) <=> ($rightBranch === false ? 99 : $rightBranch);

                return $branchComparison !== 0
                    ? $branchComparison
                    : strnatcasecmp((string) ($left['sort_value'] ?? ''), (string) ($right['sort_value'] ?? ''));
            })
            ->values();

        return [
            'available' => $rows->isNotEmpty(),
            'vendor' => [
                'key' => $vendor['key'],
                'label' => $vendor['label'],
                'sheet' => $vendor['sheet'],
                'source_url' => $this->rtlVendorSheetUrl((string) $vendor['sheet']),
            ],
            'scope_label' => $branch !== '' ? $branch : 'AREA 6',
            'columns' => (array) ($parsed['columns'] ?? []),
            'rows' => $rows->map(static fn (array $row): array => [
                'branch' => $row['branch'],
                'values' => $row['values'],
            ])->all(),
            'row_count' => $rows->count(),
            'stale' => $stale,
            'error' => $error,
        ];
    }

    /**
     * @param array<int, array<int, mixed>> $matrix
     * @return array<string, mixed>
     */
    public function parseRtlVendorMatrix(array $matrix): array
    {
        $matrix = array_map(
            fn (array $row): array => array_map(fn ($value): string => $this->clean((string) ($value ?? '')), $row),
            $matrix
        );
        $headerIndex = null;
        foreach ($matrix as $index => $row) {
            $labels = array_map(fn ($value): string => $this->normaliseLabel((string) $value), $row);
            $hasBranchCode = in_array('KODE KANCA', $labels, true) || in_array('NO KANCA', $labels, true);
            $hasBranch = in_array('KANCA KONSOL', $labels, true) || in_array('KANCA', $labels, true);
            if ($hasBranchCode && $hasBranch) {
                $headerIndex = $index;
                break;
            }
        }
        if ($headerIndex === null) {
            throw new \RuntimeException('Header nominatif vendor tidak ditemukan.');
        }

        $header = $matrix[$headerIndex];
        $subHeader = $matrix[$headerIndex + 1] ?? [];
        $branchCodeColumn = $this->findColumn($header, ['KODE KANCA', 'NO KANCA']);
        $branchColumn = $this->findColumn($header, ['KANCA KONSOL', 'KANCA']);
        $subHeaderIsData = isset($subHeader[$branchCodeColumn])
            && preg_match('/\d/', (string) $subHeader[$branchCodeColumn]) === 1;
        $dataStartIndex = $headerIndex + ($subHeaderIsData ? 1 : 2);
        if ($subHeaderIsData) {
            $subHeader = [];
        }

        $columnCount = max(count($header), count($subHeader));
        $columnLabels = [];
        $lastGroup = '';
        for ($column = 0; $column < $columnCount; $column++) {
            $parent = $this->clean((string) ($header[$column] ?? ''));
            $child = $this->clean((string) ($subHeader[$column] ?? ''));
            if ($parent !== '') {
                $lastGroup = $parent;
            }
            $label = $child !== ''
                ? implode(' - ', array_values(array_unique(array_filter([$parent !== '' ? $parent : $lastGroup, $child]))))
                : $parent;
            $normalised = $this->normaliseLabel($label);
            if ($normalised === '' || $normalised === 'ISI DI SINI' || str_starts_with($normalised, 'ISI DISINI')) {
                continue;
            }
            $columnLabels[$column] = $label;
        }

        $rawRows = [];
        foreach (array_slice($matrix, $dataStartIndex) as $row) {
            $branchCode = $this->normaliseCode($this->cell($row, $branchCodeColumn));
            $branch = self::ALLOWED_UNITS[$branchCode] ?? $this->resolveBranch($this->cell($row, $branchColumn));
            if ($branch === null) {
                continue;
            }
            $rawRows[] = ['branch' => $branch, 'row' => $row];
        }

        $usedColumns = collect(array_keys($columnLabels))->filter(function (int $column) use ($rawRows): bool {
            return collect($rawRows)->contains(
                fn (array $item): bool => $this->clean($this->cell($item['row'], $column)) !== ''
            );
        })->values()->all();
        $columns = collect($usedColumns)->map(fn (int $column): array => [
            'key' => 'c'.$column,
            'label' => $columnLabels[$column],
        ])->all();

        $rows = collect($rawRows)->map(function (array $item) use ($usedColumns): array {
            $values = collect($usedColumns)
                ->map(fn (int $column): string => $this->clean($this->cell($item['row'], $column)))
                ->all();

            return [
                'branch' => $item['branch'],
                'sort_value' => implode('|', array_slice($values, 0, 6)),
                'values' => $values,
            ];
        })->all();

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @param array<string, mixed> $external
     * @param array<string, mixed> $quadrants
     * @param array<string, mixed>|null $branchScope
     * @return array<string, mixed>
     */
    public function buildScopedPayload(array $external, array $quadrants, ?array $branchScope = null): array
    {
        $branch = strtoupper(trim((string) ($branchScope['upper_label'] ?? '')));
        $scopeLabel = $branch !== '' ? $branch : 'AREA 6';
        $calendarWeek = $this->calendarWeek($quadrants['period'] ?? null);

        return [
            'meta' => [
                'scope' => $branch !== '' ? 'branch' : 'area6',
                'scope_label' => $scopeLabel,
                'generated_at' => now()->toDateTimeString(),
                'calendar_week' => $calendarWeek,
            ],
            'quadrants' => $this->scopeQuadrants($quadrants, $branch),
            'realization_tiers' => $this->scopeRealizationTiers((array) ($quadrants['realization_tiers'] ?? []), $branch),
            'unproductive' => $this->scopeUnproductive((array) ($quadrants['unproductive'] ?? []), $branch),
            'hot_prospects' => $this->scopeHotProspects((array) ($external['hot_prospects'] ?? []), $branch),
            'rtl_pipeline' => $this->scopeRtlPipeline((array) ($external['rtl_pipeline'] ?? []), $branch),
            'extension' => $this->scopeExtension((array) ($external['extension'] ?? []), $branch),
            'restructuring' => $this->scopeRestructuring((array) ($external['restructuring'] ?? []), $branch),
            'kanwil_decisions' => $this->scopeKanwilDecisions((array) ($external['kanwil_decisions'] ?? []), $branch),
        ];
    }

    /** @return array<string, mixed> */
    public function calendarWeek(Carbon|string|null $value = null): array
    {
        $date = $value instanceof Carbon
            ? $value->copy()->startOfDay()
            : ($value ? Carbon::parse($value)->startOfDay() : now()->startOfDay());
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();
        $firstSaturday = $monthStart->isSaturday()
            ? $monthStart->copy()
            : $monthStart->copy()->next(Carbon::SATURDAY);

        if ($date->lte($firstSaturday)) {
            $week = 1;
            $start = $monthStart;
            $end = $firstSaturday;
        } else {
            $week = intdiv($firstSaturday->diffInDays($date), 7) + 2;
            $start = $firstSaturday->copy()->addDay()->addWeeks($week - 2);
            $end = $start->copy()->addDays(6)->min($monthEnd);
        }

        return [
            'number' => $week,
            'label' => 'Week '.$week,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'range_label' => $start->translatedFormat('d M').' - '.$end->translatedFormat('d M Y'),
        ];
    }

    /** @return array<string, mixed> */
    private function externalPayload(bool $forceRefresh): array
    {
        if (! $forceRefresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $responses = Http::pool(function (Pool $pool): array {
                $requests = [];
                foreach (self::SOURCES as $key => $source) {
                    $requests[] = $pool->as($key)
                        ->connectTimeout(5)
                        ->timeout(20)
                        ->get($this->csvUrl($source));
                }

                return $requests;
            });
        } catch (Throwable $exception) {
            $responses = [];
            $poolError = $exception->getMessage();
        }

        $payload = [];
        foreach (self::SOURCES as $key => $source) {
            try {
                $response = $responses[$key] ?? null;
                if (! $response instanceof Response || ! $response->successful() || trim($response->body()) === '') {
                    throw new \RuntimeException('Sumber tidak merespons dengan data CSV yang valid.');
                }

                $parsed = match ($key) {
                    'hot_prospects' => $this->parseHotProspectCsv($response->body()),
                    'rtl_pipeline' => $this->parseRtlPipelineCsv($response->body()),
                    'extension' => $this->parseExtensionCsv($response->body()),
                    'restructuring' => $this->parseRestructuringCsv($response->body()),
                    'kanwil_decisions' => $this->parseKanwilDecisionCsv($response->body()),
                };
                $parsed['meta'] = $this->sourceMeta($source, false);
                Cache::put($this->stableSourceKey($key), $parsed, now()->addDays(2));
                $payload[$key] = $parsed;
            } catch (Throwable $exception) {
                $stable = Cache::get($this->stableSourceKey($key));
                if (is_array($stable)) {
                    data_set($stable, 'meta.stale', true);
                    data_set($stable, 'meta.error', $exception->getMessage());
                    $payload[$key] = $stable;
                } else {
                    $payload[$key] = [
                        'records' => [],
                        'meta' => array_merge($this->sourceMeta($source, false), [
                            'available' => false,
                            'error' => $exception->getMessage() ?: ($poolError ?? 'Sumber tidak tersedia.'),
                        ]),
                    ];
                }
            }
        }

        Cache::put(self::CACHE_KEY, $payload, now()->addMinutes(5));

        return $payload;
    }

    /** @return array<string, mixed> */
    public function parseHotProspectCsv(string $csv): array
    {
        $matrix = $this->csvMatrix($csv);
        $headerIndex = $this->findHeaderRow($matrix, ['KODE KANCA', 'KODE UKER', 'NAMA RM']);
        $header = $matrix[$headerIndex] ?? [];
        $column = [
            'branch_code' => $this->findColumn($header, ['KODE KANCA']),
            'unit_code' => $this->findColumn($header, ['KODE UKER']),
            'unit' => $this->findColumn($header, ['UKER']),
            'rm' => $this->findColumn($header, ['NAMA RM']),
        ];

        $statusColumns = [
            'belum_ots' => $this->columnsStartingWith($header, 'BELUM OTS PEMUTUS'),
            'analisa_rm' => [$this->findColumn($header, ['ANALISA RM MAK'])],
            'verifikasi_adk' => [$this->findColumn($header, ['VERIFIKASI ADK', 'REVIEW SBM'])],
            'menunggu_putusan' => [$this->findColumn($header, ['MENUNGGU PUTUSAN'])],
            'sudah_diputus' => [$this->findColumn($header, ['SUDAH DIPUTUS'])],
            'realisasi' => [$this->findColumnStartingWith($header, 'REALISASI')],
            'batal' => [$this->findColumn($header, ['BATAL'])],
        ];

        $records = [];
        foreach (array_slice($matrix, $headerIndex + 1) as $row) {
            $unitCode = $this->normaliseCode($this->cell($row, $column['unit_code']));
            if (! isset(self::ALLOWED_UNITS[$unitCode])) {
                continue;
            }

            $rm = $this->clean($this->cell($row, $column['rm']));
            if ($rm === '') {
                continue;
            }

            $statuses = [];
            foreach ($statusColumns as $statusKey => $indices) {
                $deb = 0;
                $amount = 0.0;
                foreach (array_filter($indices, static fn ($index): bool => is_int($index) && $index >= 0) as $index) {
                    $deb += (int) round($this->number($this->cell($row, $index)) ?? 0);
                    $amount += $this->number($this->cell($row, $index + 1)) ?? 0.0;
                }
                $statuses[$statusKey] = ['deb' => $deb, 'amount_juta' => $amount];
            }

            $records[] = [
                'branch' => self::ALLOWED_UNITS[$unitCode],
                'branch_code' => $this->normaliseCode($this->cell($row, $column['branch_code'])),
                'unit_code' => $unitCode,
                'unit' => $this->clean($this->cell($row, $column['unit'])),
                'rm' => $rm,
                'statuses' => $statuses,
            ];
        }

        return ['records' => $records];
    }

    /** @return array<string, mixed> */
    public function parseRtlPipelineCsv(string $csv): array
    {
        $matrix = $this->csvMatrix($csv);
        $headerIndex = $this->findHeaderRow($matrix, ['KODE KANCA', 'KODE UKER', 'AREA HEAD']);
        $header = $matrix[$headerIndex] ?? [];
        $headerRows = [$header];
        $dataStartIndex = $headerIndex + 1;
        for ($offset = 1; $offset <= 3; $offset++) {
            $candidate = $matrix[$headerIndex + $offset] ?? [];
            if ($this->rtlHeaderSignalScore($candidate) === 0) {
                break;
            }

            $headerRows[] = $candidate;
            $dataStartIndex = $headerIndex + $offset + 1;
        }

        $metricHeader = $this->rtlFlattenedHeader($headerRows);
        $pipelineColumns = array_slice($this->rtlPipelineColumns($metricHeader), 0, count(self::VENDORS));

        $vendorColumns = [];
        foreach (self::VENDORS as $position => $vendor) {
            if (! isset($pipelineColumns[$position])) {
                continue;
            }

            $start = $pipelineColumns[$position];
            $end = ($pipelineColumns[$position + 1] ?? count($metricHeader)) - 1;
            $vendorColumns[$vendor['key']] = [
                'pipeline' => $start,
                'ots' => $this->rtlMetricColumn($metricHeader, $start, $end, 'SUDAH OTS'),
                'interested' => $this->rtlMetricColumn($metricHeader, $start, $end, 'BERMINAT', 'TIDAK BERMINAT'),
            ];
        }

        $unitCodeColumn = $this->findColumn($header, ['KODE UKER']);
        $unitColumn = $this->findColumn($header, ['UKER']);
        $records = [];
        foreach (array_slice($matrix, $dataStartIndex) as $row) {
            $unitCode = $this->normaliseCode($this->cell($row, $unitCodeColumn));
            if (! isset(self::ALLOWED_UNITS[$unitCode])) {
                continue;
            }

            $vendors = [];
            foreach ($vendorColumns as $vendorKey => $columns) {
                $vendors[$vendorKey] = [
                    'pipeline' => (int) round($this->number($this->cell($row, $columns['pipeline'])) ?? 0),
                    'ots' => $columns['ots'] === null
                        ? 0
                        : (int) round($this->number($this->cell($row, $columns['ots'])) ?? 0),
                    'interested' => $columns['interested'] === null
                        ? 0
                        : (int) round($this->number($this->cell($row, $columns['interested'])) ?? 0),
                ];
            }

            $records[] = [
                'branch' => self::ALLOWED_UNITS[$unitCode],
                'unit_code' => $unitCode,
                'unit' => $this->clean($this->cell($row, $unitColumn)),
                'vendors' => $vendors,
            ];
        }

        return ['records' => $records];
    }

    /** @return array<string, mixed> */
    public function parseExtensionCsv(string $csv): array
    {
        $matrix = $this->csvMatrix($csv);
        $headerIndex = $this->findHeaderRow($matrix, ['UKER', 'KODE UKER', 'KANCA INDUK']);
        $header = $matrix[$headerIndex] ?? [];
        $groupHeader = $matrix[$headerIndex + 1] ?? [];
        $unitCodeColumn = $this->findColumn($header, ['KODE UKER']);
        $unitColumn = $this->findColumn($header, ['UKER']);
        $totalColumn = $this->findColumnStartingWith($header, 'TOTAL NOMINATIF');
        $filledColumn = $this->findColumn($groupHeader, ['SUDAH ISI']);
        $missingColumn = $this->findColumn($groupHeader, ['BELUM ISI']);

        $statusColumns = [];
        foreach (self::EXTENSION_STATUSES as $key => $label) {
            $statusColumns[$key] = $this->findColumn($groupHeader, [$label]);
        }

        $records = [];
        foreach (array_slice($matrix, $headerIndex + 1) as $row) {
            $unitCode = $this->normaliseCode($this->cell($row, $unitCodeColumn));
            if (! isset(self::ALLOWED_UNITS[$unitCode])) {
                continue;
            }

            $statuses = [];
            foreach ($statusColumns as $statusKey => $index) {
                $statuses[$statusKey] = [
                    'deb' => (int) round($this->number($this->cell($row, $index)) ?? 0),
                    'amount_juta' => $this->number($this->cell($row, $index + 1)) ?? 0.0,
                ];
            }

            $records[] = [
                'branch' => self::ALLOWED_UNITS[$unitCode],
                'unit_code' => $unitCode,
                'unit' => $this->clean($this->cell($row, $unitColumn)),
                'total' => [
                    'deb' => (int) round($this->number($this->cell($row, $totalColumn)) ?? 0),
                    'amount_juta' => $this->number($this->cell($row, $totalColumn + 1)) ?? 0.0,
                ],
                'attendance' => [
                    'filled' => (int) round($this->number($this->cell($row, $filledColumn)) ?? 0),
                    'missing' => (int) round($this->number($this->cell($row, $missingColumn)) ?? 0),
                ],
                'statuses' => $statuses,
            ];
        }

        return ['records' => $records];
    }

    /** @return array<string, mixed> */
    public function parseRestructuringCsv(string $csv): array
    {
        $matrix = $this->csvMatrix($csv);
        $headerIndex = $this->findHeaderRow($matrix, ['UKER', 'NOREK', 'NASABAH', 'KETERANGAN']);
        $header = $matrix[$headerIndex] ?? [];
        $columns = [
            'branch' => $this->findColumn($header, ['UKER']),
            'rm' => $this->findColumn($header, ['NAMA RM KUALITAS']),
            'account' => $this->findColumn($header, ['NOREK']),
            'customer' => $this->findColumn($header, ['NASABAH']),
            'status' => $this->findColumn($header, ['KETERANGAN']),
        ];

        $records = [];
        foreach (array_slice($matrix, $headerIndex + 1) as $row) {
            $branch = $this->resolveBranch($this->cell($row, $columns['branch']));
            $customer = $this->clean($this->cell($row, $columns['customer']));
            if ($branch === null || $customer === '') {
                continue;
            }

            $rawStatus = $this->clean($this->cell($row, $columns['status']));
            $status = $this->classifyRestructuringStatus($rawStatus);
            if ($status === null) {
                continue;
            }

            $records[] = [
                'branch' => $branch,
                'rm' => $this->clean($this->cell($row, $columns['rm'])),
                'account' => $this->normaliseAccount($this->cell($row, $columns['account'])),
                'customer' => $customer,
                'status' => $status,
                'status_raw' => $rawStatus,
                'source_amount_juta' => $this->inlineRestructuringAmount($rawStatus),
            ];
        }

        return ['records' => $records];
    }

    /** @return array<string, mixed> */
    public function parseKanwilDecisionCsv(string $csv): array
    {
        $matrix = $this->csvMatrix($csv);
        $headerIndex = $this->findHeaderRow($matrix, ['KODE UKER', 'UKER', 'NAMA NASABAH', 'REK', 'PEMUTUS']);
        $header = $matrix[$headerIndex] ?? [];
        $columns = [
            'unit_code' => $this->findColumn($header, ['KODE UKER']),
            'branch' => $this->findColumn($header, ['UKER']),
            'customer' => $this->findColumn($header, ['NAMA NASABAH']),
            'account' => $this->findColumn($header, ['REK']),
            'restructuring_number' => $this->findColumn($header, ['RESTRUK KE']),
            'decision_maker' => $this->findColumn($header, ['PEMUTUS']),
            'status' => $this->findColumn($header, ['STATUS PAKET']),
            'sent_date' => $this->findColumn($header, ['TANGGAL KIRIM KE KANWIL']),
            'sent_via' => $this->findColumn($header, ['DIKIRIM MELALUI']),
            'dio' => $this->findColumn($header, ['DIO DARI ADK KANWIL']),
            'word_ptk' => $this->findColumn($header, ['WORD PTK']),
        ];

        $records = [];
        foreach (array_slice($matrix, $headerIndex + 1) as $row) {
            $branch = $this->resolveBranch($this->cell($row, $columns['branch']));
            $customer = $this->clean($this->cell($row, $columns['customer']));
            $account = $this->normaliseAccount($this->cell($row, $columns['account']));
            if ($branch === null || ($customer === '' && $account === '')) {
                continue;
            }

            $status = $this->clean($this->cell($row, $columns['status']));
            $sentDate = $this->clean($this->cell($row, $columns['sent_date']));
            $sentVia = $this->clean($this->cell($row, $columns['sent_via']));
            $dio = $this->clean($this->cell($row, $columns['dio']));
            $wordPtk = $this->clean($this->cell($row, $columns['word_ptk']));

            $records[] = [
                'branch' => $branch,
                'unit_code' => $this->normaliseCode($this->cell($row, $columns['unit_code'])),
                'customer' => $customer,
                'account' => $account,
                'restructuring_number' => $this->clean($this->cell($row, $columns['restructuring_number'])),
                'decision_maker' => $this->clean($this->cell($row, $columns['decision_maker'])),
                'status' => $status,
                'sent_date' => $sentDate,
                'sent_via' => $sentVia,
                'dio' => $dio,
                'word_ptk' => $wordPtk,
                'is_sent' => $sentDate !== '' || $sentVia !== '',
                'is_decided' => $this->isKanwilDecisionCompleted($status),
                'has_dio' => $dio !== '',
                'has_word_ptk' => $wordPtk !== '',
            ];
        }

        return ['records' => $records];
    }

    /** @param array<string, mixed> $quadrants */
    private function scopeQuadrants(array $quadrants, string $branch): array
    {
        $branches = collect((array) ($quadrants['branches'] ?? []));
        if ($branch !== '') {
            $branches = $branches->filter(
                fn (array $item): bool => strtoupper((string) ($item['branch'] ?? '')) === $branch
            )->values();
        }

        $totals = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $totalRm = 0;
        foreach ($branches as $item) {
            $totalRm += (int) ($item['total_rm'] ?? 0);
            foreach ($totals as $quadrant => $_value) {
                $totals[$quadrant] += (int) data_get($item, 'quadrants.'.$quadrant.'.count', 0);
            }
        }

        return [
            'available' => $branches->isNotEmpty(),
            'mode' => $branch !== '' ? 'branch' : 'area6',
            'period' => $quadrants['period'] ?? null,
            'period_label' => $quadrants['period_label'] ?? '-',
            'total_rm' => $totalRm,
            'totals' => $this->quadrantMetrics($totals, $totalRm),
            'branches' => $branches->all(),
            'rms' => $branch !== '' ? (array) data_get($branches->first(), 'rms', []) : [],
        ];
    }

    /** @param array<string, mixed> $source */
    private function scopeRealizationTiers(array $source, string $branch): array
    {
        $branches = collect((array) ($source['branches'] ?? []));
        if ($branch !== '') {
            $branches = $branches
                ->filter(fn (array $item): bool => strtoupper((string) ($item['branch'] ?? '')) === $branch)
                ->values();
        }

        $tierKeys = ['lt_500', '500_1000', '1000_1600', 'gte_1600'];
        $totals = [];
        $totalRm = (int) $branches->sum(fn (array $item): int => (int) ($item['total_rm'] ?? 0));
        foreach ($tierKeys as $tierKey) {
            $first = collect((array) ($source['totals'] ?? []))->get($tierKey, []);
            $count = (int) $branches->sum(
                fn (array $item): int => (int) data_get($item, 'tiers.'.$tierKey.'.rm_count', 0)
            );
            $totals[$tierKey] = [
                'key' => $tierKey,
                'label' => (string) ($first['label'] ?? $tierKey),
                'rm_count' => $count,
                'amount' => (float) $branches->sum(
                    fn (array $item): float => (float) data_get($item, 'tiers.'.$tierKey.'.amount', 0)
                ),
                'percentage' => $totalRm > 0 ? ($count / $totalRm) * 100 : 0.0,
                'rms' => $branches
                    ->flatMap(function (array $item) use ($tierKey): array {
                        $branchLabel = (string) ($item['branch'] ?? '-');

                        return collect((array) data_get($item, 'tiers.'.$tierKey.'.rms', []))
                            ->map(static fn (array $rm): array => array_merge($rm, ['branch' => $branchLabel]))
                            ->all();
                    })
                    ->unique(fn (array $rm): string => implode('|', [
                        strtoupper((string) ($rm['branch'] ?? '')),
                        strtoupper((string) ($rm['unit_code'] ?? '')),
                        strtoupper((string) ($rm['rm'] ?? '')),
                    ]))
                    ->values()
                    ->all(),
            ];
        }

        return [
            'available' => (bool) ($source['available'] ?? false) && $branches->isNotEmpty(),
            'basis' => (string) ($source['basis'] ?? ''),
            'period_label' => (string) ($source['period_label'] ?? '-'),
            'total_rm' => $totalRm,
            'totals' => $totals,
            'branches' => $branches->all(),
        ];
    }

    /** @param array<string, mixed> $source */
    private function scopeUnproductive(array $source, string $branch): array
    {
        $branches = collect((array) ($source['branches'] ?? []));
        if ($branch !== '') {
            $branches = $branches
                ->filter(fn (array $item): bool => strtoupper((string) ($item['branch'] ?? '')) === $branch)
                ->values();
        }

        $metricKeys = ['month_1', 'month_3', 'month_6'];
        $totalRm = (int) $branches->sum(fn (array $item): int => (int) ($item['total_rm'] ?? 0));
        $totals = [];
        foreach ($metricKeys as $metricKey) {
            $sourceMetric = (array) data_get($source, 'totals.'.$metricKey, []);
            $count = (int) $branches->sum(
                fn (array $item): int => (int) data_get($item, 'metrics.'.$metricKey.'.count', 0)
            );
            $rms = $branches
                ->flatMap(function (array $item) use ($metricKey): array {
                    $branchLabel = (string) ($item['branch'] ?? '-');

                    return collect((array) data_get($item, 'metrics.'.$metricKey.'.rms', []))
                        ->map(static fn (array $rm): array => array_merge($rm, ['branch' => $branchLabel]))
                        ->all();
                })
                ->unique(static fn (array $rm): string => implode('|', [
                    strtoupper((string) ($rm['branch'] ?? '')),
                    strtoupper((string) ($rm['rm'] ?? '')),
                    strtoupper((string) ($rm['unit_code'] ?? '')),
                ]))
                ->sortBy(static fn (array $rm): string => implode('|', [
                    strtoupper((string) ($rm['branch'] ?? '')),
                    str_pad((string) ($rm['unit_code'] ?? ''), 10, '0', STR_PAD_LEFT),
                    strtoupper((string) ($rm['rm'] ?? '')),
                ]))
                ->values()
                ->all();
            $totals[$metricKey] = [
                'key' => $metricKey,
                'label' => (string) ($sourceMetric['label'] ?? $metricKey),
                'count' => $count,
                'percentage' => $totalRm > 0 ? ($count / $totalRm) * 100 : 0.0,
                'rms' => $rms,
            ];
        }

        return [
            'available' => (bool) ($source['available'] ?? false) && $branches->isNotEmpty(),
            'period_label' => (string) ($source['period_label'] ?? '-'),
            'total_rm' => $totalRm,
            'totals' => $totals,
            'branches' => $branches->all(),
        ];
    }

    /** @param array<string, mixed> $source */
    private function scopeHotProspects(array $source, string $branch): array
    {
        $records = $this->scopeRecords((array) ($source['records'] ?? []), $branch);
        $statuses = [];
        foreach (self::HOT_STATUSES as $key => $label) {
            $statuses[] = [
                'key' => $key,
                'label' => $label,
                'deb' => (int) $records->sum(fn (array $row): int => (int) data_get($row, 'statuses.'.$key.'.deb', 0)),
                'amount_juta' => (float) $records->sum(fn (array $row): float => (float) data_get($row, 'statuses.'.$key.'.amount_juta', 0)),
            ];
        }

        return array_merge($this->moduleMeta($source, $records->isNotEmpty()), [
            'rm_count' => $records->pluck('rm')->filter()->unique()->count(),
            'unit_count' => $records->pluck('unit_code')->filter()->unique()->count(),
            'statuses' => $statuses,
        ]);
    }

    /** @param array<string, mixed> $source */
    private function scopeRtlPipeline(array $source, string $branch): array
    {
        $records = $this->scopeRecords((array) ($source['records'] ?? []), $branch);
        $branchOrder = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $displayBranches = $branch !== '' ? [$branch] : $branchOrder;
        $vendors = [];
        foreach (self::VENDORS as $vendor) {
            if (! $vendor['include']) {
                continue;
            }
            $branches = collect($displayBranches)->map(function (string $branchLabel) use ($records, $vendor): array {
                $branchRecords = $records->where('branch', $branchLabel)->values();

                return [
                    'branch' => $branchLabel,
                    'unit_count' => $branchRecords->pluck('unit_code')->filter()->unique()->count(),
                    'pipeline' => (int) $branchRecords->sum(fn (array $row): int => (int) data_get($row, 'vendors.'.$vendor['key'].'.pipeline', 0)),
                    'ots' => (int) $branchRecords->sum(fn (array $row): int => (int) data_get($row, 'vendors.'.$vendor['key'].'.ots', 0)),
                    'interested' => (int) $branchRecords->sum(fn (array $row): int => (int) data_get($row, 'vendors.'.$vendor['key'].'.interested', 0)),
                ];
            })->values();
            $vendors[] = [
                'key' => $vendor['key'],
                'label' => $vendor['label'],
                'icon' => $vendor['icon'],
                'source_sheet' => $vendor['sheet'],
                'source_url' => $this->rtlVendorSheetUrl((string) $vendor['sheet']),
                'pipeline' => (int) $branches->sum('pipeline'),
                'ots' => (int) $branches->sum('ots'),
                'interested' => (int) $branches->sum('interested'),
                'branches' => $branches->all(),
            ];
        }

        return array_merge($this->moduleMeta($source, $records->isNotEmpty()), [
            'unit_count' => $records->pluck('unit_code')->filter()->unique()->count(),
            'total_pipeline' => array_sum(array_column($vendors, 'pipeline')),
            'total_ots' => array_sum(array_column($vendors, 'ots')),
            'total_interested' => array_sum(array_column($vendors, 'interested')),
            'vendors' => $vendors,
        ]);
    }

    private function rtlVendorSheetUrl(string $sheet): string
    {
        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/edit#sheet=%s',
            self::SOURCES['rtl_pipeline']['spreadsheet_id'],
            rawurlencode($sheet)
        );
    }

    /** @return array<string, mixed> */
    private function loadRtlVendorWorksheet(string $sheetName): array
    {
        $response = Http::connectTimeout(5)
            ->timeout(60)
            ->get(sprintf(
                'https://docs.google.com/spreadsheets/d/%s/export?format=xlsx',
                self::SOURCES['rtl_pipeline']['spreadsheet_id']
            ));
        if (! $response->successful() || strlen($response->body()) < 1024) {
            throw new \RuntimeException('Workbook RTL Pipeline tidak dapat diunduh.');
        }

        $directory = storage_path('framework/cache');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Folder cache workbook tidak tersedia.');
        }
        $temporaryFile = tempnam($directory, 'rtl_vendor_');
        if ($temporaryFile === false) {
            throw new \RuntimeException('File sementara workbook tidak dapat dibuat.');
        }

        try {
            if (file_put_contents($temporaryFile, $response->body(), LOCK_EX) === false) {
                throw new \RuntimeException('Workbook RTL Pipeline tidak dapat disimpan sementara.');
            }

            $reader = new Xlsx();
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([$sheetName]);
            $spreadsheet = $reader->load($temporaryFile);
            $worksheet = $spreadsheet->getSheetByName($sheetName);
            if ($worksheet === null) {
                throw new \RuntimeException('Worksheet vendor '.$sheetName.' tidak ditemukan.');
            }

            $matrix = $worksheet->toArray('', false, false, false);
            $spreadsheet->disconnectWorksheets();

            return $this->parseRtlVendorMatrix($matrix);
        } finally {
            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    /** @param array<string, mixed> $source */
    private function scopeExtension(array $source, string $branch): array
    {
        $records = $this->scopeRecords((array) ($source['records'] ?? []), $branch);
        $totalDeb = (int) $records->sum(fn (array $row): int => (int) data_get($row, 'total.deb', 0));
        $filled = (int) $records->sum(fn (array $row): int => (int) data_get($row, 'attendance.filled', 0));
        $missing = (int) $records->sum(fn (array $row): int => (int) data_get($row, 'attendance.missing', 0));
        $statuses = [];
        foreach (self::EXTENSION_STATUSES as $key => $label) {
            $statuses[] = [
                'key' => $key,
                'label' => $label,
                'deb' => (int) $records->sum(fn (array $row): int => (int) data_get($row, 'statuses.'.$key.'.deb', 0)),
                'amount_juta' => (float) $records->sum(fn (array $row): float => (float) data_get($row, 'statuses.'.$key.'.amount_juta', 0)),
            ];
        }

        return array_merge($this->moduleMeta($source, $records->isNotEmpty()), [
            'total' => [
                'deb' => $totalDeb,
                'amount_juta' => (float) $records->sum(fn (array $row): float => (float) data_get($row, 'total.amount_juta', 0)),
            ],
            'attendance' => [
                'filled' => $filled,
                'missing' => $missing,
                'percentage' => $totalDeb > 0 ? ($filled / $totalDeb) * 100 : 0.0,
            ],
            'statuses' => $statuses,
        ]);
    }

    /** @param array<string, mixed> $source */
    private function scopeRestructuring(array $source, string $branch): array
    {
        $records = $this->scopeRecords((array) ($source['records'] ?? []), $branch);

        return array_merge($this->moduleMeta($source, $records->isNotEmpty()), [
            'record_count' => $records->count(),
            'statuses' => $this->restructuringStatusMetrics($records),
        ]);
    }

    /** @param array<string, mixed> $source */
    private function scopeKanwilDecisions(array $source, string $branch): array
    {
        $records = $this->scopeRecords((array) ($source['records'] ?? []), $branch);
        $branchOrder = [
            'KC MADIUN' => '01',
            'KC MAGETAN' => '02',
            'KC NGAWI' => '03',
            'KC PONOROGO' => '04',
        ];
        $displayBranches = $branch !== '' ? [$branch] : array_keys($branchOrder);
        $groups = collect($displayBranches)
            ->map(function (string $branchLabel) use ($records): array {
                $branchRecords = $records->where('branch', $branchLabel)->values();

                return [
                    'branch' => $branchLabel,
                    'total' => $branchRecords->count(),
                    'sent' => $branchRecords->where('is_sent', true)->count(),
                    'decided' => $branchRecords->where('is_decided', true)->count(),
                    'dio' => $branchRecords->where('has_dio', true)->count(),
                    'word_ptk' => $branchRecords->where('has_word_ptk', true)->count(),
                ];
            })
            ->sortBy(fn (array $item): string => $branchOrder[$item['branch']] ?? '99')
            ->values()
            ->all();

        return array_merge($this->moduleMeta($source, $records->isNotEmpty()), [
            'record_count' => $records->count(),
            'sent_count' => $records->where('is_sent', true)->count(),
            'decided_count' => $records->where('is_decided', true)->count(),
            'dio_count' => $records->where('has_dio', true)->count(),
            'word_ptk_count' => $records->where('has_word_ptk', true)->count(),
            'branches' => $groups,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function restructuringStatusMetrics($records): array
    {
        $records = collect($records)->values();
        $statuses = [];

        foreach (self::RESTRUCTURING_STATUSES as $key => $label) {
            $statusRecords = $records->where('status', $key)->values();
            $sourceAmounts = $statusRecords
                ->pluck('source_amount_juta')
                ->filter(static fn (mixed $amount): bool => is_numeric($amount))
                ->map(static fn (mixed $amount): float => (float) $amount)
                ->values();

            $statuses[] = [
                'key' => $key,
                'label' => $label,
                'deb' => $statusRecords->count(),
                'amount_juta' => (float) $sourceAmounts->sum(),
                'amount_available' => $sourceAmounts->isNotEmpty(),
                'resolved_deb' => $sourceAmounts->count(),
            ];
        }

        return $statuses;
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function scopeRecords(array $records, string $branch)
    {
        return collect($records)
            ->filter(fn (array $row): bool => $branch === '' || strtoupper((string) ($row['branch'] ?? '')) === $branch)
            ->values();
    }

    /** @return array<string, mixed> */
    private function moduleMeta(array $source, bool $hasRows): array
    {
        $meta = (array) ($source['meta'] ?? []);

        return [
            'available' => (bool) ($meta['available'] ?? true) && $hasRows,
            'stale' => (bool) ($meta['stale'] ?? false),
            'source_label' => (string) ($meta['label'] ?? ''),
            'source_url' => (string) ($meta['source_url'] ?? ''),
            'source_sheet' => (string) ($meta['sheet'] ?? ''),
            'fetched_at' => $meta['fetched_at'] ?? null,
            'error' => (string) ($meta['error'] ?? ''),
        ];
    }

    /** @param array<int, int> $counts */
    private function quadrantMetrics(array $counts, int $total): array
    {
        $metrics = [];
        foreach ([1, 2, 3, 4] as $quadrant) {
            $count = (int) ($counts[$quadrant] ?? 0);
            $metrics[$quadrant] = [
                'count' => $count,
                'percentage' => $total > 0 ? ($count / $total) * 100 : 0.0,
            ];
        }

        return $metrics;
    }

    /** @param array<string, mixed> $source */
    private function sourceMeta(array $source, bool $stale): array
    {
        return [
            'available' => ! $stale,
            'stale' => $stale,
            'label' => $source['label'],
            'sheet' => $source['sheet'],
            'source_url' => sprintf(
                'https://docs.google.com/spreadsheets/d/%s/edit',
                $source['spreadsheet_id']
            ),
            'fetched_at' => now()->toDateTimeString(),
        ];
    }

    /** @param array<string, mixed> $source */
    private function csvUrl(array $source): string
    {
        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/export?format=csv&sheet=%s',
            $source['spreadsheet_id'],
            rawurlencode((string) $source['sheet'])
        );
    }

    private function stableSourceKey(string $key): string
    {
        return self::CACHE_KEY.':'.$key.':stable';
    }

    /** @return array<int, array<int, string>> */
    private function csvMatrix(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Stream CSV tidak dapat dibuat.');
        }

        fwrite($stream, $csv);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = array_map(fn ($value): string => $this->clean((string) $value), $row);
        }
        fclose($stream);

        if ($rows !== [] && isset($rows[0][0])) {
            $rows[0][0] = ltrim($rows[0][0], "\xEF\xBB\xBF");
        }

        return $rows;
    }

    /** @param array<int, array<int, string>> $matrix */
    private function findHeaderRow(array $matrix, array $requiredLabels): int
    {
        $required = array_map(fn (string $label): string => $this->normaliseLabel($label), $requiredLabels);
        foreach ($matrix as $index => $row) {
            $labels = array_map(fn ($value): string => $this->normaliseLabel((string) $value), $row);
            if (collect($required)->every(fn (string $label): bool => in_array($label, $labels, true))) {
                return $index;
            }
        }

        throw new \RuntimeException('Header yang dibutuhkan tidak ditemukan pada sumber spreadsheet.');
    }

    /** @param array<int, string> $row */
    private function findColumn(array $row, array $labels): int
    {
        $needles = array_map(fn (string $label): string => $this->normaliseLabel($label), $labels);
        foreach ($row as $index => $value) {
            if (in_array($this->normaliseLabel($value), $needles, true)) {
                return $index;
            }
        }

        return -1;
    }

    /** @param array<int, string> $row */
    private function findColumnStartingWith(array $row, string $label): int
    {
        $needle = $this->normaliseLabel($label);
        foreach ($row as $index => $value) {
            if (str_starts_with($this->normaliseLabel($value), $needle)) {
                return $index;
            }
        }

        return -1;
    }

    /** @param array<int, string> $row @return array<int, int> */
    private function columnsStartingWith(array $row, string $label): array
    {
        $needle = $this->normaliseLabel($label);

        return collect($row)
            ->map(fn ($value, $index): ?int => str_starts_with($this->normaliseLabel((string) $value), $needle) ? (int) $index : null)
            ->filter(static fn ($value): bool => $value !== null)
            ->values()
            ->all();
    }

    /** @param array<int, string> $row */
    private function rtlHeaderSignalScore(array $row): int
    {
        $signals = ['PIPELINE', 'SUDAH TARIK SLIK', 'SUDAH OTS', 'PEMBIAYAAN', 'BERMINAT', 'PROGRES RTL'];

        return collect($row)->sum(function ($value) use ($signals): int {
            $label = $this->normaliseLabel((string) $value);

            return collect($signals)->contains(fn (string $signal): bool => str_contains($label, $signal)) ? 1 : 0;
        });
    }

    /**
     * @param array<int, array<int, string>> $rows
     * @return array<int, string>
     */
    private function rtlFlattenedHeader(array $rows): array
    {
        $columnCount = collect($rows)->map(fn (array $row): int => count($row))->max() ?? 0;
        $header = [];

        for ($column = 0; $column < $columnCount; $column++) {
            $labels = collect($rows)
                ->map(fn (array $row): string => $this->clean((string) ($row[$column] ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $header[$column] = implode(' ', $labels);
        }

        return $header;
    }

    /** @param array<int, string> $row @return array<int, int> */
    private function rtlPipelineColumns(array $row): array
    {
        return collect($row)
            ->map(function ($value, $index): ?int {
                $label = $this->normaliseLabel((string) $value);

                return str_contains($label, 'PIPELINE') && ! str_contains($label, 'PROGRES')
                    ? (int) $index
                    : null;
            })
            ->filter(static fn ($value): bool => $value !== null)
            ->values()
            ->all();
    }

    /** @param array<int, string> $row */
    private function rtlMetricColumn(
        array $row,
        int $start,
        int $end,
        string $needle,
        ?string $excludedNeedle = null
    ): ?int {
        $normalisedNeedle = $this->normaliseLabel($needle);
        $normalisedExcluded = $excludedNeedle === null ? null : $this->normaliseLabel($excludedNeedle);

        for ($index = max(0, $start); $index <= min($end, count($row) - 1); $index++) {
            $label = $this->normaliseLabel((string) ($row[$index] ?? ''));
            if (! str_contains($label, $normalisedNeedle)) {
                continue;
            }
            if ($normalisedExcluded !== null && str_contains($label, $normalisedExcluded)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function classifyRestructuringStatus(string $status): ?string
    {
        $value = $this->normaliseLabel($status);
        if ($value === '') {
            return null;
        }
        if (str_contains($value, 'MENUNGGU PUTUSAN') || str_contains($value, 'KIRIM KE KANWIL')) {
            return 'menunggu_putusan';
        }
        if (str_contains($value, 'SUDAH PUTUSAN') || str_contains($value, 'SUDAH DIPUTUS') || str_contains($value, 'SUDAH AKAD') || str_contains($value, 'MENUNGGU AKAD')) {
            return 'sudah_diputus';
        }
        if (str_contains($value, 'ADK') || str_contains($value, 'VERIF')) {
            return 'verifikasi_adk';
        }
        if (str_contains($value, 'ANALISA') || str_contains($value, 'PROSES PENGERJAAN') || str_contains($value, 'NEGO NASABAH') || str_contains($value, 'MAK')) {
            return 'analisa_rm';
        }

        return null;
    }

    private function isKanwilDecisionCompleted(string $status): bool
    {
        $value = $this->normaliseLabel($status);

        return $value !== '' && (
            str_contains($value, 'PUTUS')
            || str_contains($value, 'SETUJU')
            || str_contains($value, 'SELESAI')
            || str_contains($value, 'AKAD')
        );
    }

    private function inlineRestructuringAmount(string $status): ?float
    {
        if (preg_match('/(?:^|\s)([\d.]+(?:,[\d]+)?)\s*\/\s*SML\b/i', $status, $matches) !== 1) {
            return null;
        }

        return $this->number($matches[1]);
    }

    private function resolveBranch(string $value): ?string
    {
        $normalised = $this->normaliseLabel($value);
        foreach (['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'] as $branch) {
            if ($normalised === $branch || $normalised === 'KC '.$branch || $normalised === 'KANCA '.$branch) {
                return 'KC '.$branch;
            }
        }

        return null;
    }

    private function normaliseCode(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        $digits = ltrim($digits, '0');

        return $digits === '' ? '0' : $digits;
    }

    private function normaliseAccount(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return ltrim($digits, '0');
    }

    private function normaliseLabel(string $value): string
    {
        $value = strtoupper($this->clean($value));
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function clean(string $value): string
    {
        $value = str_replace(["\u{00A0}", "\r", "\n"], [' ', ' ', ' '], $value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /** @param array<int, string> $row */
    private function cell(array $row, int $index): string
    {
        return $index >= 0 ? (string) ($row[$index] ?? '') : '';
    }

    private function number(string $value): ?float
    {
        $value = $this->clean($value);
        if ($value === '' || $value === '-' || str_starts_with($value, '#')) {
            return null;
        }

        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $value = trim($value, "() \t\n\r\0\x0B");
        $value = str_replace(['Rp', 'RP', '%', ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $value) === 1) {
            $value = str_replace('.', '', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $negative ? -abs($number) : $number;
    }
}
