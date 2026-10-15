<?php

namespace App\Http\Controllers;

use App\Support\EkosistemGeography;
use App\Support\EkosistemMonitoring;
use App\Support\UserBranchScope;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Peta Prospek Ekosistem (read-only).
 *
 * Sumber data adalah spreadsheet milik webapp Apps Script "Peta Prospek Ekosistem | BRI Area 6".
 * Penulisan (update status, tambah prospek) tetap dilakukan lewat webapp/spreadsheet tersebut.
 * Nominal di sumber disimpan dalam JUTA rupiah.
 */
class EkosistemController extends Controller
{
    private const SPREADSHEET_ID = '1bssXQGe2PVUQYuNQVfiXuo5TDLuidO6TeU1cYR9iHZI';

    private const SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/'.self::SPREADSHEET_ID.'/edit?usp=sharing';

    private const SHEETS = ['ekosistem' => 'Ekosistem', 'prospek' => 'Prospek', 'log' => 'Log'];

    private const KC_OPTIONS = [
        'madiun' => 'Madiun',
        'magetan' => 'Magetan',
        'ngawi' => 'Ngawi',
        'ponorogo' => 'Ponorogo',
    ];

    public const JALUR_RM = ['RMFT', 'RM SME', 'RM Konsumer', 'KUR Mikro & Mantri'];

    public const STATUS = ['Belum mulai', 'Berjalan', 'Tertunda', 'Selesai', 'Batal'];

    public const TAHAP = ['Pendataan', 'Penawaran', 'Sosialisasi', 'Closing', 'Maintenance'];

    public const RING = [
        'L1' => 'Inti: lembaga, pimpinan, dan personel',
        'L2' => 'Pemasok dan unit kelolaan langsung',
        'L3' => 'Turunan: usaha, komunitas, pengguna layanan, dan fasilitas sekitar',
    ];

    public const ARAH = [
        'Sudah BRI' => 'Pendalaman',
        'Sebagian BRI' => 'Akuisisi',
        'Belum BRI' => 'Akuisisi',
        'Belum diketahui' => 'Pendataan',
    ];

    private const NUMERIC_COLUMNS = [
        'Est_Min_Juta', 'Est_Max_Juta', 'Est_Nasabah', 'Est_Durasi_Hari',
        'Realisasi_Nominal_Juta', 'Realisasi_Nasabah',
    ];

    private const DATE_COLUMNS = ['Tgl_Mulai', 'Target_Selesai', 'Tgl_Selesai'];

    private const NOMINAL_COLUMNS = [
        'Est_Min_Juta' => 'Estimasi minimal',
        'Est_Max_Juta' => 'Estimasi maksimal',
        'Realisasi_Nominal_Juta' => 'Realisasi',
    ];

    /**
     * Nilai >= Rp10 T (dalam juta) per prospek hampir pasti diisi dalam rupiah, bukan juta.
     * Nilainya dikeluarkan dari total dan ditandai agar diperbaiki di spreadsheet.
     */
    private const NOMINAL_SANITY_LIMIT_JUTA = 10_000_000;

    public function index(Request $request): View
    {
        [$kcOptions, $selectedKc, $isLocked] = $this->resolveKcSelection($request->input('kc'));

        if ($request->boolean('refresh')) {
            Cache::forget($this->cacheKey());
        }

        $source = Cache::get($this->cacheKey());
        if (! is_array($source)) {
            $source = $this->fetchSource();
            if (empty($source['error'])) {
                Cache::put($this->cacheKey(), $source, now()->addMinutes(10));
            }
        }

        $today = Carbon::today()->toDateString();
        $scopeLabel = $isLocked ? self::KC_OPTIONS[$selectedKc] : null;
        $accessibleEcosystems = array_values(array_filter(
            $source['ekosistem'] ?? [],
            static fn (array $row): bool => $scopeLabel === null || strcasecmp($row['KC'] ?? '', $scopeLabel) === 0
        ));
        $accessibleProspects = array_values(array_filter(
            $source['prospek'] ?? [],
            static fn (array $row): bool => $scopeLabel === null || strcasecmp($row['KC'] ?? '', $scopeLabel) === 0
        ));
        $monitor = new EkosistemMonitoring;
        $masterIds = array_flip(array_column($accessibleEcosystems, 'ID_Ekosistem'));
        $accessibleProspects = $monitor->decorate(array_map(function (array $row) use ($today, $masterIds): array {
            $row['Ekosistem_Terdaftar'] = isset($masterIds[$row['ID_Ekosistem'] ?? '']);
            foreach (array_merge(self::DATE_COLUMNS, ['Diubah_Pada']) as $column) {
                $row[$column] = $this->parseDate((string) ($row[$column] ?? ''));
            }

            return $this->decorateProspect($row, $today);
        }, $accessibleProspects), $today);

        $filterFields = ['jalur' => 'Jalur_RM', 'status' => 'Status', 'jenis' => 'Jenis_Potensi',
            'dasar' => 'Dasar_Angka', 'ring' => 'Ring', 'pic' => 'Nama_PIC', 'arah' => 'Arah',
            'eco' => 'ID_Ekosistem', 'peran' => 'Peran_PIC'];
        $choices = [];
        $filters = ['kc' => $selectedKc];
        foreach ($filterFields as $key => $field) {
            $values = array_map(static fn (array $row): string => trim((string) ($row[$field] ?? '')), $accessibleProspects);
            $choices[$key] = array_values(array_unique(array_filter($values, static fn (string $value): bool => $value !== '')));
            sort($choices[$key], SORT_NATURAL | SORT_FLAG_CASE);
            $value = $request->query($key, '');
            $filters[$key] = is_string($value) && (in_array($value, $choices[$key], true) || ($key === 'pic' && in_array($value, ['__unassigned', '__assigned'], true)) || (in_array($key, ['peran', 'ring', 'jenis'], true) && $value === '__empty')) ? $value : '';
        }
        $condition = $request->query('condition', '');
        $filters['condition'] = is_string($condition) && isset(EkosistemMonitoring::CONDITIONS[$condition]) ? $condition : '';
        $search = $request->query('q', '');
        $filters['q'] = is_string($search) ? mb_substr(trim($search), 0, 200) : '';
        $prospects = $monitor->filter($accessibleProspects, $filters);
        $contextRows = $monitor->filter($accessibleProspects, $filters, true);
        $ecosystems = array_values(array_filter($accessibleEcosystems, static fn (array $row): bool => $selectedKc === 'all' || mb_strtolower($row['KC'] ?? '') === $selectedKc));
        if (count(array_filter(array_diff_key($filters, ['kc' => true]))) > 0) {
            $matchedIds = array_flip(array_column($prospects, 'ID_Ekosistem'));
            $ecosystems = array_values(array_filter($ecosystems, static fn (array $row): bool => isset($matchedIds[$row['ID_Ekosistem']])));
        }
        $branches = [];
        $matrix = [];
        foreach ($kcOptions as $key => $label) {
            if ($key === 'all') {
                continue;
            }
            $rows = array_values(array_filter($contextRows, static fn (array $row): bool => mb_strtolower($row['KC'] ?? '') === $key));
            $branches[$key] = ['label' => $label, 'stats' => $monitor->stats($rows)];
            foreach (self::JALUR_RM as $jalur) {
                $matrix[$key][$jalur] = $monitor->stats(array_values(array_filter($rows, static fn (array $row): bool => ($row['Jalur_RM'] ?? '') === $jalur)));
            }
        }
        $view = $request->query('view', 'overview');
        $activeView = in_array($view, ['overview', 'monitor', 'prospects', 'ecosystems'], true) ? $view : 'overview';
        $page = max(1, (int) $request->integer('page', 1));
        $page = min($page, max(1, (int) ceil(count($prospects) / 20)));
        $paginator = new LengthAwarePaginator(array_slice($prospects, ($page - 1) * 20, 20), count($prospects), 20, $page, ['path' => route('ekosistem.index'), 'query' => array_merge($filters, ['view' => 'prospects'])]);
        $picGroups = $monitor->picGroups($prospects);
        $picPage = min(max(1, $request->integer('pic_page', 1)), max(1, (int) ceil(count($picGroups) / 20)));
        $picPaginator = new LengthAwarePaginator(array_slice($picGroups, ($picPage - 1) * 20, 20), count($picGroups), 20, $picPage,
            ['path' => route('ekosistem.index'), 'query' => array_merge($filters, ['view' => 'monitor']), 'pageName' => 'pic_page']);

        $prospectIds = array_flip(array_column($prospects, 'ID_Prospek'));
        $activity = array_values(array_filter(
            $source['log'] ?? [],
            static fn (array $row): bool => isset($prospectIds[$row['ID_Prospek'] ?? ''])
        ));

        return view('report.ekosistem', [
            'kcOptions' => $kcOptions,
            'selectedKc' => $selectedKc,
            'isLocked' => $isLocked,
            'spreadsheetUrl' => self::SPREADSHEET_URL,
            'summary' => $this->summarize($ecosystems, $prospects),
            'ecosystems' => $this->summarizeEcosystems($ecosystems, $prospects),
            'prospects' => $prospects,
            'activity' => array_slice($activity, 0, 20),
            'jalurOptions' => self::JALUR_RM,
            'statusOptions' => self::STATUS,
            'ringOptions' => self::RING,
            'fetchedAt' => $source['fetched_at'] ?? null,
            'error' => $source['error'] ?? null,
            'filters' => $filters,
            'choices' => $choices,
            'activeView' => $activeView,
            'monitorSummary' => $monitor->stats($prospects),
            'branches' => $branches,
            'matrix' => $matrix,
            'picGroups' => $picGroups,
            'picPaginator' => $picPaginator,
            'priority' => $monitor->priority($prospects),
            'paginator' => $paginator,
            'geography' => (new EkosistemGeography)->build(array_keys($branches)),
            'allEcosystems' => $accessibleEcosystems,
            'conditions' => EkosistemMonitoring::CONDITIONS,
            'statusCounts' => $monitor->stats($prospects)['statuses'],
        ]);
    }

    /**
     * @return array{0: array<string, string>, 1: string, 2: bool}
     */
    private function resolveKcSelection(mixed $requested): array
    {
        $scope = UserBranchScope::current();
        if ($scope !== null && isset(self::KC_OPTIONS[$scope['key']])) {
            return [[$scope['key'] => 'KC '.self::KC_OPTIONS[$scope['key']]], $scope['key'], true];
        }

        $options = ['all' => 'Area 6 (Semua KC)'];
        foreach (self::KC_OPTIONS as $key => $label) {
            $options[$key] = 'KC '.$label;
        }

        $selected = is_scalar($requested) ? strtolower(trim((string) $requested)) : '';

        return [$options, isset($options[$selected]) ? $selected : 'all', false];
    }

    /**
     * @return array{ekosistem: array<int, array<string, mixed>>, prospek: array<int, array<string, mixed>>, log: array<int, array<string, string>>, fetched_at: string|null, error: string|null}
     */
    private function fetchSource(): array
    {
        try {
            $responses = Http::pool(function (Pool $pool): array {
                $requests = [];
                foreach (self::SHEETS as $key => $sheet) {
                    $requests[] = $pool->as($key)->timeout(20)->get($this->csvUrl($sheet));
                }

                return $requests;
            });

            $tables = [];
            foreach (self::SHEETS as $key => $sheet) {
                $response = $responses[$key] ?? null;
                if (! $response instanceof Response || ! $response->successful()) {
                    $response = Http::timeout(20)->retry(1, 300)->get($this->csvUrl($sheet));
                }
                $tables[$key] = $this->rowsFromResponse($response, $sheet);
            }
        } catch (\Throwable $exception) {
            Log::warning('Ekosistem spreadsheet could not be fetched.', ['message' => $exception->getMessage()]);

            return [
                'ekosistem' => [],
                'prospek' => [],
                'log' => [],
                'fetched_at' => null,
                'error' => 'Data Ekosistem belum dapat disinkronkan dari spreadsheet. Silakan perbarui beberapa saat lagi.',
            ];
        }

        $prospek = array_map(function (array $row): array {
            foreach (self::NUMERIC_COLUMNS as $column) {
                $row[$column] = $this->parseNumber($row[$column] ?? '');
            }
            foreach (self::DATE_COLUMNS as $column) {
                $row[$column] = $this->parseDate($row[$column] ?? '');
            }

            return $row;
        }, array_values(array_filter($tables['prospek'], static fn (array $row): bool => ($row['ID_Prospek'] ?? '') !== '')));

        $log = array_reverse(array_values(array_filter(
            $tables['log'],
            static fn (array $row): bool => ($row['ID_Prospek'] ?? '') !== ''
        )));

        return [
            'ekosistem' => array_values(array_filter($tables['ekosistem'], static fn (array $row): bool => ($row['ID_Ekosistem'] ?? '') !== '')),
            'prospek' => $prospek,
            'log' => $log,
            'fetched_at' => now()->format('d M Y H:i'),
            'error' => null,
        ];
    }

    /** @return array<int, array<string, string>> */
    private function rowsFromResponse(Response $response, string $sheet): array
    {
        if (! $response->successful()) {
            throw new \RuntimeException("Sheet {$sheet} returned status {$response->status()}.");
        }

        $csv = trim($response->body());
        if ($csv === '' || str_contains(strtolower(substr($csv, 0, 300)), '<html')) {
            throw new \RuntimeException("Sheet {$sheet} did not return CSV data.");
        }

        return $this->parseCsv($csv);
    }

    /** @return array<int, array<string, string>> */
    public function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('CSV sementara tidak dapat dibaca.');
        }

        try {
            fwrite($stream, $csv);
            rewind($stream);
            $headers = null;
            $rows = [];
            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $row = array_map(static fn (mixed $value): string => trim((string) $value), $row);
                if ($headers === null) {
                    $headers = $row;

                    continue;
                }
                $record = [];
                foreach ($headers as $index => $header) {
                    if ($header !== '') {
                        $record[$header] = $row[$index] ?? '';
                    }
                }
                $rows[] = $record;
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }

    public function parseNumber(string $value): ?float
    {
        $clean = str_replace([',', ' '], '', trim($value));

        return $clean !== '' && is_numeric($clean) ? (float) $clean : null;
    }

    /** Sumber memakai yyyy-mm-dd atau format tampilan Google (M/d/yyyy [H:mm:ss]). */
    public function parseDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "{$m[1]}-{$m[2]}-{$m[3]}" : '';
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $value, $m)) {
            return checkdate((int) $m[1], (int) $m[2], (int) $m[3])
                ? sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2])
                : '';
        }

        return '';
    }

    /** @param  array<string, mixed>  $row */
    private function decorateProspect(array $row, string $today): array
    {
        $status = (string) ($row['Status'] ?? '') ?: 'Belum mulai';
        $target = (string) ($row['Target_Selesai'] ?? '');
        $row['Status'] = $status;
        $row['Cek_Satuan'] = [];
        foreach (self::NOMINAL_COLUMNS as $column => $label) {
            $value = $row[$column] ?? null;
            if ($value !== null && $value >= self::NOMINAL_SANITY_LIMIT_JUTA) {
                $row['Cek_Satuan'][] = ['label' => $label, 'value' => $value];
                $row[$column] = null;
            }
        }
        $row['Arah'] = self::ARAH[$row['Status_BRI'] ?? ''] ?? 'Pendataan';
        $row['Terlambat'] = $target !== '' && $target < $today && in_array($status, ['Berjalan', 'Tertunda'], true);

        return $row;
    }

    /**
     * @param  array<int, array<string, mixed>>  $ecosystems
     * @param  array<int, array<string, mixed>>  $prospects
     * @return array<string, int|float>
     */
    private function summarize(array $ecosystems, array $prospects): array
    {
        $countBy = static fn (string $status): int => count(array_filter(
            $prospects,
            static fn (array $row): bool => $row['Status'] === $status
        ));

        return [
            'ekosistem' => count($ecosystems),
            'prospek' => count($prospects),
            'berjalan' => $countBy('Berjalan'),
            'selesai' => $countBy('Selesai'),
            'terlambat' => count(array_filter($prospects, static fn (array $row): bool => $row['Terlambat'])),
            'cek_satuan' => count(array_filter($prospects, static fn (array $row): bool => $row['Cek_Satuan'] !== [])),
            'potensi_juta' => array_sum(array_map(static fn (array $row): float => (float) ($row['Est_Max_Juta'] ?? 0), $prospects)),
            'realisasi_juta' => array_sum(array_map(static fn (array $row): float => (float) ($row['Realisasi_Nominal_Juta'] ?? 0), $prospects)),
            'realisasi_nasabah' => (int) array_sum(array_map(static fn (array $row): float => (float) ($row['Realisasi_Nasabah'] ?? 0), $prospects)),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $ecosystems
     * @param  array<int, array<string, mixed>>  $prospects
     * @return array<int, array<string, mixed>>
     */
    private function summarizeEcosystems(array $ecosystems, array $prospects): array
    {
        $grouped = [];
        foreach ($prospects as $row) {
            $grouped[$row['ID_Ekosistem'] ?? ''][] = $row;
        }

        return array_map(static function (array $eco) use ($grouped): array {
            $rows = $grouped[$eco['ID_Ekosistem']] ?? [];
            $status = array_fill_keys(self::STATUS, 0);
            $ring = array_fill_keys(array_keys(self::RING), 0);
            foreach ($rows as $row) {
                $status[$row['Status']] = ($status[$row['Status']] ?? 0) + 1;
                if (isset($ring[$row['Ring'] ?? ''])) {
                    $ring[$row['Ring']]++;
                }
            }
            $active = count($rows) - $status['Batal'];

            return $eco + [
                'total' => count($rows),
                'status' => $status,
                'ring' => $ring,
                'progress' => $active > 0 ? (int) round(($status['Berjalan'] + $status['Selesai']) / $active * 100) : 0,
                'potensi_juta' => array_sum(array_map(static fn (array $row): float => (float) ($row['Est_Max_Juta'] ?? 0), $rows)),
                'realisasi_juta' => array_sum(array_map(static fn (array $row): float => (float) ($row['Realisasi_Nominal_Juta'] ?? 0), $rows)),
            ];
        }, $ecosystems);
    }

    private function csvUrl(string $sheetName): string
    {
        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?%s',
            self::SPREADSHEET_ID,
            http_build_query(['tqx' => 'out:csv', 'sheet' => $sheetName])
        );
    }

    private function cacheKey(): string
    {
        return 'ekosistem:source:v1:'.self::SPREADSHEET_ID;
    }
}
