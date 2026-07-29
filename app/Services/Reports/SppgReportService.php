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

class SppgReportService
{
    private const LINK_TABLE = 'external_report_links';
    private const GROUP_KEY = 'kolaborasi_report';
    private const LINK_KEY = 'sppg';
    private const DEFAULT_SHEET_NAME = 'Area 6';

    public function buildReport(): array
    {
        $link = $this->linkConfig();

        if (!Schema::hasTable(self::LINK_TABLE)) {
            return $this->emptyReport(collect(['Tabel external_report_links belum tersedia. Jalankan migration terlebih dahulu.']), $link);
        }

        if (trim((string) ($link['link_url'] ?? '')) === '') {
            return $this->emptyReport(collect(['Link spreadsheet SPPG belum diisi di Link Management.']), $link);
        }

        return $this->scopeReport($this->readSpreadsheet($link));
    }

    private function linkConfig(): array
    {
        $default = [
            'label' => 'SPPG',
            'sheet_name' => self::DEFAULT_SHEET_NAME,
            'spreadsheet_id' => '',
            'link_url' => '',
        ];

        if (!Schema::hasTable(self::LINK_TABLE)) {
            return $default;
        }

        $row = DB::table(self::LINK_TABLE)
            ->where('group_key', self::GROUP_KEY)
            ->where('link_key', self::LINK_KEY)
            ->where('is_active', true)
            ->first();

        if (!$row) {
            return $default;
        }

        return [
            'label' => $row->label ?: $default['label'],
            'sheet_name' => $row->sheet_name ?: $default['sheet_name'],
            'spreadsheet_id' => $row->spreadsheet_id ?: '',
            'link_url' => $row->link_url ?: '',
        ];
    }

    private function emptyReport($errors, array $link): array
    {
        return [
            'rows' => collect(),
            'errors' => $errors,
            'link' => $link,
            'totalRows' => 0,
            'branchOptions' => collect(),
            'lastFetchedAt' => now(),
        ];
    }

    private function readSpreadsheet(array $link): array
    {
        $cacheKey = 'report:sppg:v2:'
            . md5(($link['link_url'] ?? '') . '|' . ($link['sheet_name'] ?? self::DEFAULT_SHEET_NAME));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            try {
                $fetchedAt = isset($cached['lastFetchedAt']) ? Carbon::parse($cached['lastFetchedAt']) : null;
                if ($fetchedAt === null || $fetchedAt->lt(now()->subMinutes(10))) {
                    $this->queueSourceRefresh();
                }
            } catch (\Throwable) {
                $this->queueSourceRefresh();
            }

            return $cached;
        }

        $this->queueSourceRefresh();

        return $this->emptyReport(collect(['Data SPPG sedang disinkronkan di background.']), $link);
    }

    public function refreshSourceCache(): array
    {
        $link = $this->linkConfig();
        if (trim((string) ($link['link_url'] ?? '')) === '') {
            return ['success' => false, 'error' => 'Link spreadsheet SPPG belum tersedia.'];
        }

        $payload = $this->fetchSpreadsheet($link);
        $success = $payload['errors']->isEmpty();
        if ($success) {
            $cacheKey = 'report:sppg:v2:'
                . md5(($link['link_url'] ?? '') . '|' . ($link['sheet_name'] ?? self::DEFAULT_SHEET_NAME));
            Cache::forever($cacheKey, $payload);
        }

        Cache::forget('dashboard_sources:refresh:sppg:pending');

        return [
            'success' => $success,
            'row_count' => (int) ($payload['totalRows'] ?? 0),
            'errors' => $payload['errors']->all(),
        ];
    }

    private function queueSourceRefresh(): void
    {
        $key = 'dashboard_sources:refresh:sppg:pending';
        if (Cache::add($key, now()->toIso8601String(), now()->addMinutes(10))) {
            RefreshRemoteDashboardSourcesJob::dispatch(['sppg']);
        }
    }

    private function fetchSpreadsheet(array $link): array
    {
        try {
            $csvUrl = $this->toCsvUrl((string) $link['link_url'], (string) ($link['sheet_name'] ?: self::DEFAULT_SHEET_NAME));
            $response = Http::timeout(20)
                ->retry(1, 500)
                ->accept('text/csv')
                ->get($csvUrl);

            if (!$response->successful()) {
                return $this->emptyReport(collect(['Spreadsheet SPPG tidak bisa diakses. Pastikan link sudah shareable untuk aplikasi.']), $link);
            }

            $body = $response->body();
            if (str_contains(Str::lower(substr($body, 0, 500)), '<html')) {
                return $this->emptyReport(collect(['Respon spreadsheet SPPG bukan CSV. Pastikan link Google Sheets sudah public/shareable atau gunakan link dengan gid sheet Area 6.']), $link);
            }

            return $this->rowsFromCsv($body, $link);
        } catch (\Throwable $exception) {
            return $this->emptyReport(collect(['Gagal membaca spreadsheet SPPG (' . $exception->getMessage() . ').']), $link);
        }
    }

    private function rowsFromCsv(string $csv, array $link): array
    {
        $records = $this->parseCsv($csv);
        $header = $this->findHeader($records);

        if ($header === null) {
            return $this->emptyReport(collect(['Header SPPG tidak ditemukan. Pastikan sheet Area 6 memiliki kolom BO, Nama Yayasan, Nama Kepala SPPG, dan Nama Tenaga Pemasar.']), $link);
        }

        [$headerIndex, $columns] = $header;
        $rows = collect();

        foreach (array_slice($records, $headerIndex + 1) as $record) {
            $branchOffice = $this->readCell($record, $columns['branch_office']);
            $namaYayasan = $this->readCell($record, $columns['nama_yayasan']);
            $namaKepala = $this->readCell($record, $columns['nama_kepala_sppg']);
            $namaPic = $this->readCell($record, $columns['nama_pic_sppg']);

            if ($branchOffice === '' && $namaYayasan === '' && $namaKepala === '' && $namaPic === '') {
                continue;
            }

            if (in_array(Str::lower($branchOffice), ['bo', 'branch office', 'total', 'grand total'], true)) {
                continue;
            }

            $rows->push([
                'branch_office' => $branchOffice,
                'nama_yayasan' => $namaYayasan,
                'nama_kepala_sppg' => $namaKepala,
                'nama_pic_sppg' => $namaPic,
            ]);
        }

        $rows = $rows->sortBy([
                fn ($row) => Str::lower($row['branch_office'] ?? ''),
                fn ($row) => Str::lower($row['nama_yayasan'] ?? ''),
            ])
            ->values();

        return [
            'rows' => $rows,
            'errors' => collect(),
            'link' => $link,
            'totalRows' => $rows->count(),
            'branchOptions' => $rows->pluck('branch_office')->filter()->unique()->sort()->values(),
            'lastFetchedAt' => now(),
        ];
    }

    private function scopeReport(array $report): array
    {
        $scope = UserBranchScope::current();
        if ($scope === null) {
            return $report;
        }

        $branch = strtoupper((string) $scope['label']);
        $plainBranch = strtoupper((string) $scope['plain_label']);
        $rows = collect($report['rows'] ?? [])->filter(function (array $row) use ($branch, $plainBranch): bool {
            $value = strtoupper((string) ($row['branch_office'] ?? ''));

            return str_contains($value, $branch) || str_contains($value, $plainBranch);
        })->values();

        $report['rows'] = $rows;
        $report['totalRows'] = $rows->count();
        $report['branchOptions'] = $rows->pluck('branch_office')->filter()->unique()->sort()->values();

        return $report;
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
        foreach (array_slice($records, 0, 20, true) as $rowIndex => $record) {
            $previous = $records[$rowIndex - 1] ?? [];
            $columns = [
                'branch_office' => $this->findColumnIndex($record, $previous, ['bo', 'branchoffice']),
                'nama_yayasan' => $this->findColumnIndex($record, $previous, ['namayayasan']),
                'nama_kepala_sppg' => $this->findColumnIndex($record, $previous, ['namakepalasppg', 'datakepalasppgnamakepalasppg']),
                'nama_pic_sppg' => $this->findColumnIndex($record, $previous, ['namatenagapemasar', 'pictenagapemasarnamatenagapemasar', 'namapicsppg']),
            ];

            if (!in_array(null, $columns, true)) {
                return [$rowIndex, $columns];
            }
        }

        return null;
    }

    private function findColumnIndex(array $headerRow, array $previousRow, array $candidates): ?int
    {
        foreach ($headerRow as $columnIndex => $value) {
            $normalized = $this->normalizeHeader($value);
            $combined = $this->normalizeHeader(($previousRow[$columnIndex] ?? '') . ' ' . $value);

            if (in_array($normalized, $candidates, true) || in_array($combined, $candidates, true)) {
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

    private function toCsvUrl(string $linkUrl, string $sheetName): string
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
            . '/gviz/tq?tqx=out:csv&sheet=' . rawurlencode($sheetName ?: self::DEFAULT_SHEET_NAME);
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

    private function normalizeHeader(mixed $value): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $value));

        return Str::of($text)
            ->lower()
            ->replace([' ', '_', '-', '.', '/', "\\", '>', ':', "\r", "\n", "\t"], '')
            ->toString();
    }
}
