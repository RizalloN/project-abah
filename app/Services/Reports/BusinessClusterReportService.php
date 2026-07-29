<?php

namespace App\Services\Reports;

use App\Jobs\RefreshRemoteDashboardSourcesJob;
use App\Rules\TrustedSpreadsheetUrl;
use App\Support\UserBranchScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BusinessClusterReportService
{
    private const TABLE = 'business_cluster';
    private const DEFAULT_SHEET_NAME = '3. BUSINESS CLUSTER';
    private const AREA_ALL_LABEL = 'Area 6 - All';
    private const KANCA_ORDER = [
        'KC Madiun' => 1,
        'KC Magetan' => 2,
        'KC Ngawi' => 3,
        'KC Ponorogo' => 4,
    ];

    public function buildReport(array|string|null $branchOffices = null): array
    {
        $branchOptions = collect(array_keys(self::KANCA_ORDER));
        $scope = UserBranchScope::current();
        if ($scope !== null) {
            $branchOptions = $branchOptions
                ->filter(fn (string $branch): bool => $branch === $scope['label'])
                ->values();
            $branchOffices = [$scope['label']];
        }

        $selectedBranches = $this->normalizeSelectedBranches($branchOffices);
        $scopeLabel = $selectedBranches->isEmpty()
            ? self::AREA_ALL_LABEL
            : $selectedBranches->join(', ');

        if (!Schema::hasTable(self::TABLE)) {
            return [
                'rows' => collect(),
                'sources' => collect(),
                'errors' => collect(['Tabel business_cluster belum tersedia. Jalankan migration terlebih dahulu.']),
                'totalJumlah' => 0,
                'totalSudahBri' => 0,
                'totalBelumBri' => 0,
                'lastFetchedAt' => now(),
                'branchOptions' => $branchOptions,
                'selectedBranchOffices' => $selectedBranches,
                'branchScopeLabel' => $scopeLabel,
                'latestPosition' => now()->format('d/m/Y'),
            ];
        }

        $sources = DB::table(self::TABLE)
            ->select('uniqueid_namareport', 'nama_kanca', 'link_url')
            ->get()
            ->when($selectedBranches->isNotEmpty(), fn ($items) => $items
                ->filter(fn ($source) => $selectedBranches->contains($source->nama_kanca)))
            ->sortBy(fn ($source) => self::KANCA_ORDER[$source->nama_kanca] ?? 99)
            ->values();

        if ($sources->isEmpty()) {
            return [
                'rows' => collect(),
                'sources' => $sources,
                'errors' => collect([$selectedBranches->isEmpty()
                    ? 'Belum ada link spreadsheet Business Cluster yang disimpan.'
                    : 'Belum ada link spreadsheet Business Cluster untuk cabang yang dipilih.']),
                'totalJumlah' => 0,
                'totalSudahBri' => 0,
                'totalBelumBri' => 0,
                'lastFetchedAt' => now(),
                'branchOptions' => $branchOptions,
                'selectedBranchOffices' => $selectedBranches,
                'branchScopeLabel' => $scopeLabel,
                'latestPosition' => now()->format('d/m/Y'),
            ];
        }

        $rows = collect();
        $errors = collect();

        foreach ($sources as $source) {
            $result = $this->readSpreadsheet((string) $source->nama_kanca, (string) $source->link_url);

            $rows = $rows->merge($result['rows']);
            $errors = $errors->merge($result['errors']);
        }

        $rows = $this->aggregateKategoriRows($rows, $scopeLabel);

        return [
            'rows' => $rows,
            'sources' => $sources,
            'errors' => $errors->values(),
            'totalJumlah' => $rows->sum('jumlah'),
            'totalSudahBri' => $rows->sum('sudah_bri'),
            'totalBelumBri' => $rows->sum('belum_bri'),
            'lastFetchedAt' => now(),
            'branchOptions' => $branchOptions,
            'selectedBranchOffices' => $selectedBranches,
            'branchScopeLabel' => $scopeLabel,
            'latestPosition' => now()->format('d/m/Y'),
        ];
    }

    private function normalizeSelectedBranches(array|string|null $branchOffices)
    {
        $values = is_array($branchOffices) ? $branchOffices : [$branchOffices];
        $validBranches = collect(array_keys(self::KANCA_ORDER));

        return collect($values)
            ->flatten()
            ->map(fn ($branch) => trim((string) $branch))
            ->filter(fn ($branch) => $validBranches->contains($branch))
            ->unique()
            ->sortBy(fn ($branch) => self::KANCA_ORDER[$branch] ?? 99)
            ->values();
    }

    private function aggregateKategoriRows($rows, string $scopeLabel)
    {
        return $rows
            ->groupBy(fn ($row) => Str::lower(Str::of($row['kategori'])->squish()->toString()))
            ->map(function ($group) use ($scopeLabel) {
                $first = $group->first();

                return [
                    'branch_office' => $scopeLabel,
                    'kategori' => $first['kategori'],
                    'jumlah' => (int) $group->sum('jumlah'),
                    'sudah_bri' => (int) $group->sum('sudah_bri'),
                    'belum_bri' => (int) $group->sum('belum_bri'),
                    'detail_key' => $this->detailKey($first['kategori']),
                    'details' => $group
                        ->flatMap(fn ($row) => $row['details'] ?? [])
                        ->sortBy(fn ($detail) => Str::lower($detail['nama_usaha'] ?? ''))
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy(fn ($row) => Str::lower($row['kategori']))
            ->values();
    }

    private function readSpreadsheet(string $namaKanca, string $linkUrl): array
    {
        $cacheKey = 'report:business_cluster:v2:' . md5($namaKanca . '|' . $linkUrl);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            try {
                $fetchedAt = isset($cached['fetched_at']) ? Carbon::parse((string) $cached['fetched_at']) : null;
                if ($fetchedAt === null || $fetchedAt->lt(now()->subMinutes(10))) {
                    $this->queueSourceRefresh();
                }
            } catch (\Throwable) {
                $this->queueSourceRefresh();
            }

            return $cached;
        }

        $this->queueSourceRefresh();

        return [
            'rows' => collect(),
            'errors' => collect([$namaKanca . ': data sedang disinkronkan di background.']),
        ];
    }

    public function refreshSourceCaches(): array
    {
        if (!Schema::hasTable(self::TABLE)) {
            return ['success' => false, 'refreshed' => 0, 'error' => 'Tabel business_cluster belum tersedia.'];
        }

        $sources = DB::table(self::TABLE)
            ->select('nama_kanca', 'link_url')
            ->whereNotNull('link_url')
            ->get();
        $refreshed = 0;
        $errors = [];

        foreach ($sources as $source) {
            $namaKanca = (string) $source->nama_kanca;
            $linkUrl = (string) $source->link_url;
            $result = $this->fetchSpreadsheet($namaKanca, $linkUrl);
            if ($result['errors']->isEmpty()) {
                $result['fetched_at'] = now()->toDateTimeString();
                Cache::forever('report:business_cluster:v2:' . md5($namaKanca . '|' . $linkUrl), $result);
                $refreshed++;
            } else {
                $errors = array_merge($errors, $result['errors']->all());
            }
        }

        Cache::forget('dashboard_sources:refresh:business-cluster:pending');

        return ['success' => $errors === [], 'refreshed' => $refreshed, 'errors' => $errors];
    }

    private function queueSourceRefresh(): void
    {
        $key = 'dashboard_sources:refresh:business-cluster:pending';
        if (Cache::add($key, now()->toIso8601String(), now()->addMinutes(10))) {
            RefreshRemoteDashboardSourcesJob::dispatch(['business-cluster']);
        }
    }

    private function fetchSpreadsheet(string $namaKanca, string $linkUrl): array
    {
        try {
            $csvUrl = $this->toCsvUrl($linkUrl);
            $response = Http::timeout(20)
                ->retry(1, 500)
                ->accept('text/csv')
                ->get($csvUrl);

            if (!$response->successful()) {
                return [
                    'rows' => collect(),
                    'errors' => collect([$namaKanca . ': spreadsheet tidak bisa diakses. Pastikan link sudah public/shareable.']),
                ];
            }

            $body = $response->body();
            if (str_contains(Str::lower(substr($body, 0, 500)), '<html')) {
                return [
                    'rows' => collect(),
                    'errors' => collect([$namaKanca . ': respon spreadsheet bukan CSV. Pastikan link Google Sheets sudah public/shareable.']),
                ];
            }

            return $this->countKategoriFromCsv($namaKanca, $body);
        } catch (\Throwable $exception) {
            return [
                'rows' => collect(),
                'errors' => collect([$namaKanca . ': gagal membaca spreadsheet (' . $exception->getMessage() . ').']),
            ];
        }
    }

    private function toCsvUrl(string $linkUrl): string
    {
        if (!TrustedSpreadsheetUrl::isTrusted($linkUrl)) {
            throw new \RuntimeException('Sumber spreadsheet tidak diizinkan.');
        }

        if (!preg_match('~docs\.google\.com/spreadsheets/d/([^/]+)~', $linkUrl, $matches)) {
            throw new \RuntimeException('ID Google Sheets tidak ditemukan.');
        }

        $spreadsheetId = $matches[1];
        $gid = $this->extractGid($linkUrl);

        if ($gid !== null) {
            return 'https://docs.google.com/spreadsheets/d/' . $spreadsheetId . '/export?format=csv&gid=' . $gid;
        }

        return 'https://docs.google.com/spreadsheets/d/' . $spreadsheetId
            . '/gviz/tq?tqx=out:csv&sheet=' . rawurlencode(self::DEFAULT_SHEET_NAME);
    }

    private function extractGid(string $linkUrl): ?string
    {
        $parts = parse_url($linkUrl);
        foreach (['query', 'fragment'] as $partName) {
            $part = $parts[$partName] ?? '';
            if ($part === '') {
                continue;
            }

            parse_str($part, $params);
            if (isset($params['gid']) && preg_match('/^\d+$/', (string) $params['gid'])) {
                return (string) $params['gid'];
            }
        }

        return null;
    }

    private function countKategoriFromCsv(string $namaKanca, string $csv): array
    {
        $records = $this->parseCsv($csv);
        $header = $this->findHeader($records);

        if ($header === null) {
            return [
                'rows' => collect(),
                'errors' => collect([$namaKanca . ': kolom Kategori tidak ditemukan pada spreadsheet.']),
            ];
        }

        [$headerIndex, $kategoriIndex] = $header;
        $headerRow = $records[$headerIndex] ?? [];
        $namaUsahaIndex = $this->findColumnIndex($headerRow, ['namausaha']);
        $alamatLengkapIndex = $this->findColumnIndex($headerRow, ['alamatlengkap', 'alamat']);
        $kotaKabupatenIndex = $this->findColumnIndex($headerRow, ['kotakabupaten', 'kotakab', 'kabupatenkota', 'kota']);
        $statusBriIndex = $this->findColumnIndex($headerRow, ['sudahblmbri', 'sudahbelumbri', 'statusbri']);
        $groups = [];

        foreach (array_slice($records, $headerIndex + 1) as $record) {
            $kategori = Str::of((string) ($record[$kategoriIndex] ?? ''))->squish()->toString();
            $normalized = Str::lower($kategori);

            if ($kategori === '' || in_array($normalized, ['kategori', 'total', 'grand total'], true)) {
                continue;
            }

            if (!isset($groups[$normalized])) {
                $groups[$normalized] = [
                    'kategori' => $kategori,
                    'jumlah' => 0,
                    'sudah_bri' => 0,
                    'belum_bri' => 0,
                    'details' => [],
                ];
            }

            $statusBri = $this->normalizeBriStatus($this->readCell($record, $statusBriIndex));
            $groups[$normalized]['jumlah']++;
            if ($statusBri['key'] === 'sudah') {
                $groups[$normalized]['sudah_bri']++;
            } elseif ($statusBri['key'] === 'belum') {
                $groups[$normalized]['belum_bri']++;
            }

            $groups[$normalized]['details'][] = [
                'nama_usaha' => $this->readCell($record, $namaUsahaIndex),
                'alamat_lengkap' => $this->readCell($record, $alamatLengkapIndex),
                'kota_kabupaten' => $this->readCell($record, $kotaKabupatenIndex),
                'status_bri' => $statusBri['label'],
                'status_bri_key' => $statusBri['key'],
            ];
        }

        $rows = collect($groups)
            ->map(fn ($group) => [
                'branch_office' => $namaKanca,
                'kategori' => $group['kategori'],
                'jumlah' => (int) $group['jumlah'],
                'sudah_bri' => (int) $group['sudah_bri'],
                'belum_bri' => (int) $group['belum_bri'],
                'detail_key' => $this->detailKey($group['kategori']),
                'details' => $group['details'],
            ])
            ->values();

        return [
            'rows' => $rows,
            'errors' => collect(),
        ];
    }

    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);

        $records = [];
        while (($record = fgetcsv($stream)) !== false) {
            if (count($record) === 1 && trim((string) $record[0]) === '') {
                continue;
            }

            $records[] = $record;
        }

        fclose($stream);

        return $records;
    }

    private function findHeader(array $records): ?array
    {
        foreach (array_slice($records, 0, 10, true) as $rowIndex => $record) {
            foreach ($record as $columnIndex => $value) {
                if ($this->normalizeHeader($value) === 'kategori') {
                    return [$rowIndex, $columnIndex];
                }
            }
        }

        return null;
    }

    private function findColumnIndex(array $headerRow, array $candidates): ?int
    {
        foreach ($headerRow as $columnIndex => $value) {
            if (in_array($this->normalizeHeader($value), $candidates, true)) {
                return $columnIndex;
            }
        }

        return null;
    }

    private function readCell(array $record, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return Str::of((string) ($record[$index] ?? ''))->squish()->toString();
    }

    private function detailKey(string $kategori): string
    {
        return md5(Str::lower(Str::of($kategori)->squish()->toString()));
    }

    private function normalizeBriStatus(string $status): array
    {
        $normalized = Str::of($status)->lower()->squish()->toString();

        if ($normalized === '') {
            return ['key' => '', 'label' => '-'];
        }

        if (str_contains($normalized, 'sudah')) {
            return ['key' => 'sudah', 'label' => 'Sudah di BRI'];
        }

        if (str_contains($normalized, 'belum') || preg_match('/\bblm\b/', $normalized)) {
            return ['key' => 'belum', 'label' => 'Belum di BRI'];
        }

        return ['key' => '', 'label' => Str::of($status)->squish()->toString()];
    }

    private function normalizeHeader(mixed $value): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $value));

        return Str::of($text)
            ->lower()
            ->replace([' ', '_', '-', '.', '/', "\r", "\n", "\t"], '')
            ->toString();
    }
}
