<?php

namespace App\Services\Reports;

use App\Rules\TrustedSpreadsheetUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use XMLReader;
use ZipArchive;

final class MicroPipelineSyncService
{
    private const BATCH_SIZE = 300;

    private const DATASETS = [
        'prewash' => [
            'id' => 1,
            'label' => 'Prewash',
            'sheet' => 'Nominatif',
            'config_url' => 'services.micro_pipeline.prewash_source_url',
        ],
        'slik_hijau' => [
            'id' => 2,
            'label' => 'SLIK Hijau',
            'sheet' => 'Berminat 1',
            'config_url' => 'services.micro_pipeline.slik_hijau_source_url',
        ],
    ];

    private const MONTHS = [
        'JAN' => 'Jan', 'FEB' => 'Feb', 'MAR' => 'Mar', 'APR' => 'Apr',
        'MEI' => 'Mei', 'JUN' => 'Jun', 'JUL' => 'Jul', 'AGT' => 'Agu',
        'SEP' => 'Sep', 'OKT' => 'Okt', 'NOV' => 'Nov', 'DES' => 'Des',
    ];

    /** @var array<string, string> */
    private const AREA_BRANCHES = [
        'madiun' => 'KC Madiun',
        'magetan' => 'KC Magetan',
        'ngawi' => 'KC Ngawi',
        'ponorogo' => 'KC Ponorogo',
    ];

    /**
     * @return array<string, mixed>
     */
    public function sync(?string $sourceUrl = null, ?string $localPath = null, bool $force = false, string $dataset = 'prewash'): array
    {
        if (! Schema::hasTable('micro_pipeline_records') || ! Schema::hasTable('micro_pipeline_syncs')) {
            throw new \RuntimeException('Tabel Pipeline Mikro belum tersedia. Jalankan migrasi database.');
        }

        $dataset = $this->normalizeDataset($dataset);
        $definition = self::DATASETS[$dataset];
        $definition['sheet'] = $this->configuredSheetName($dataset);
        $lock = Cache::lock('micro-pipeline:sync:'.$dataset, 1800);
        if (! $lock->get()) {
            throw new \RuntimeException('Sinkronisasi Pipeline Mikro sedang berjalan.');
        }

        $temporaryPath = null;
        try {
            $sourceUrl = trim((string) ($sourceUrl ?: $this->configuredSourceUrl($dataset)));
            $path = $localPath !== null ? realpath($localPath) : false;
            if (! is_string($path) || $path === '') {
                if ($sourceUrl === '') {
                    throw new \RuntimeException('URL sumber Pipeline Mikro belum dikonfigurasi.');
                }
                $temporaryPath = $this->downloadSource($sourceUrl);
                $path = $temporaryPath;
            }

            $this->assertWorkbook($path);
            $sourceHash = hash_file('sha256', $path);
            $previousSync = DB::table('micro_pipeline_syncs')->where('source_key', $dataset)->first();
            $previousHash = $previousSync?->source_hash;
            if (! $force && is_string($previousHash) && hash_equals($previousHash, $sourceHash)) {
                $stats = json_decode((string) ($previousSync->stats ?? ''), true);
                return [
                    'changed' => false,
                    'source_hash' => $sourceHash,
                    'dataset' => $dataset,
                    'source_rows' => (int) ($previousSync->source_rows ?? 0),
                    'imported_rows' => (int) ($previousSync->imported_rows ?? DB::table('micro_pipeline_records')->where('source_key', $dataset)->count()),
                    'outside_scope_rows' => (int) ($previousSync->outside_scope_rows ?? 0),
                    'statuses' => is_array($stats) ? (array) ($stats['statuses'] ?? []) : [],
                    'branches' => is_array($stats) ? (array) ($stats['branches'] ?? []) : [],
                    'sheet' => (string) ($previousSync->source_sheet ?? $definition['sheet']),
                    'message' => 'Workbook tidak berubah; data aktif dipertahankan.',
                ];
            }

            $sharedStrings = $this->readSharedStrings($path);
            $sheets = collect($this->worksheetEntries($path))->where('hidden', false)->values();
            $sheet = $sheets->first(fn (array $item): bool => $this->normalize($item['name']) === $this->normalize($definition['sheet']));
            if ($dataset === 'prewash') {
                $sheet ??= $sheets->first(fn (array $item): bool => str_contains(Str::lower($item['name']), 'pipeline'));
                $sheet ??= $sheets->first();
            }
            if (! is_array($sheet)) {
                throw new \RuntimeException("Sheet {$definition['sheet']} tidak ditemukan pada workbook {$definition['label']}.");
            }

            $now = now()->toDateTimeString();
            $sourceRows = 0;
            $importedRows = 0;
            $outsideScopeRows = 0;
            $statusTotals = ['done' => 0, 'scheduled' => 0, 'pending' => 0];
            $branchTotals = array_fill_keys(array_keys(self::AREA_BRANCHES), 0);

            DB::transaction(function () use (
                $path,
                $sheet,
                $sharedStrings,
                $dataset,
                $definition,
                $sourceUrl,
                $sourceHash,
                $now,
                &$sourceRows,
                &$importedRows,
                &$outsideScopeRows,
                &$statusTotals,
                &$branchTotals
            ): void {
                DB::table('micro_pipeline_records')->where('source_key', $dataset)->delete();

                $headers = null;
                $batch = [];
                foreach ($this->xlsxRows($path, $sheet['entry'], $sharedStrings) as $rowNumber => $row) {
                    if ($headers === null) {
                        $headers = $this->headerMap($row);
                        $this->assertHeaders($headers, $dataset);

                        continue;
                    }

                    $sourceRows++;
                    $record = $this->mapRecord($dataset, $rowNumber, $row, $headers, $sheet['name'], $now);
                    if ($record === null) {
                        continue;
                    }
                    if (! isset(self::AREA_BRANCHES[$record['branch_key']])) {
                        $outsideScopeRows++;

                        continue;
                    }

                    $batch[] = $record;
                    $importedRows++;
                    $statusTotals[$record['visit_status']]++;
                    $branchTotals[$record['branch_key']]++;
                    if (count($batch) >= self::BATCH_SIZE) {
                        DB::table('micro_pipeline_records')->insert($batch);
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    DB::table('micro_pipeline_records')->insert($batch);
                }

                DB::table('micro_pipeline_syncs')->updateOrInsert(
                    ['id' => $definition['id']],
                    [
                        'source_key' => $dataset,
                        'source_url' => $sourceUrl,
                        'source_file' => basename($path),
                        'source_sheet' => $sheet['name'],
                        'source_hash' => $sourceHash,
                        'source_rows' => $sourceRows,
                        'imported_rows' => $importedRows,
                        'outside_scope_rows' => $outsideScopeRows,
                        'synced_at' => $now,
                        'stats' => json_encode([
                            'statuses' => $statusTotals,
                            'branches' => $branchTotals,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }, 3);

            return [
                'changed' => true,
                'dataset' => $dataset,
                'source_hash' => $sourceHash,
                'source_rows' => $sourceRows,
                'imported_rows' => $importedRows,
                'outside_scope_rows' => $outsideScopeRows,
                'statuses' => $statusTotals,
                'branches' => $branchTotals,
                'sheet' => $sheet['name'],
                'message' => "Pipeline Mikro {$definition['label']} berhasil disinkronkan.",
            ];
        } finally {
            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
            $lock->release();
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function syncAll(bool $force = false): array
    {
        $results = [];
        foreach (array_keys(self::DATASETS) as $dataset) {
            $results[$dataset] = $this->sync(null, null, $force, $dataset);
        }

        return $results;
    }

    private function normalizeDataset(string $dataset): string
    {
        $dataset = Str::lower(trim($dataset));
        if (! isset(self::DATASETS[$dataset])) {
            throw new \InvalidArgumentException('Dataset Pipeline Mikro tidak dikenal: '.$dataset);
        }

        return $dataset;
    }

    private function configuredSourceUrl(string $dataset): string
    {
        if (Schema::hasTable('external_report_links')) {
            $managed = DB::table('external_report_links')
                ->where('group_key', 'micro_pipeline')
                ->where('link_key', $dataset)
                ->where('is_active', true)
                ->value('link_url');
            if (is_string($managed) && trim($managed) !== '') {
                return trim($managed);
            }
        }

        return (string) config(self::DATASETS[$dataset]['config_url'], '');
    }

    private function configuredSheetName(string $dataset): string
    {
        if (Schema::hasTable('external_report_links')) {
            $managed = DB::table('external_report_links')
                ->where('group_key', 'micro_pipeline')
                ->where('link_key', $dataset)
                ->where('is_active', true)
                ->value('sheet_name');
            if (is_string($managed) && trim($managed) !== '') {
                return trim($managed);
            }
        }

        return self::DATASETS[$dataset]['sheet'];
    }

    private function downloadSource(string $sourceUrl): string
    {
        if (! TrustedSpreadsheetUrl::isTrusted($sourceUrl)) {
            throw new \RuntimeException('URL sumber Pipeline Mikro tidak diizinkan.');
        }
        if (preg_match('~docs\.google\.com/spreadsheets/d/([A-Za-z0-9_-]+)~', $sourceUrl, $matches) !== 1) {
            throw new \RuntimeException('ID workbook Pipeline Mikro tidak ditemukan.');
        }

        $directory = storage_path('app/cache');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Direktori cache Pipeline Mikro tidak dapat dibuat.');
        }
        $path = tempnam($directory, 'micro-pipeline-');
        if (! is_string($path)) {
            throw new \RuntimeException('File sementara Pipeline Mikro tidak dapat dibuat.');
        }

        $downloadUrl = 'https://drive.usercontent.google.com/download?id='.$matches[1].'&export=download&confirm=t';
        $response = Http::timeout((int) config('services.micro_pipeline.timeout_seconds', 240))
            ->retry(2, 1000)
            ->withOptions(['sink' => $path])
            ->get($downloadUrl);
        if (! $response->successful()) {
            @unlink($path);
            throw new \RuntimeException('Workbook Pipeline Mikro gagal diunduh (HTTP '.$response->status().').');
        }

        return $path;
    }

    private function assertWorkbook(string $path): void
    {
        if (! is_file($path) || filesize($path) < 1000) {
            throw new \RuntimeException('Workbook Pipeline Mikro kosong atau tidak ditemukan.');
        }
        if (filesize($path) > (int) config('services.micro_pipeline.max_download_bytes', 104_857_600)) {
            throw new \RuntimeException('Ukuran workbook Pipeline Mikro melebihi batas aman.');
        }
        $archive = new ZipArchive;
        if ($archive->open($path) !== true || $archive->locateName('xl/workbook.xml') === false) {
            $archive->close();
            throw new \RuntimeException('Sumber Pipeline Mikro bukan workbook XLSX yang valid.');
        }
        $archive->close();
    }

    /** @return array<string, array<int, int>> */
    private function headerMap(array $row): array
    {
        $headers = [];
        foreach ($row as $column => $value) {
            $key = $this->normalize($value);
            if ($key !== '') {
                $headers[$key][] = (int) $column;
            }
        }

        return $headers;
    }

    private function assertHeaders(array $headers, string $dataset): void
    {
        if ($dataset === 'slik_hijau') {
            foreach (['nama debitur', 'nama uker', 'mainbr desc', 'perkiraan plafond', 'status kunjungan', 'hasil kunjungan'] as $required) {
                if (! isset($headers[$required])) {
                    throw new \RuntimeException("Kolom wajib '{$required}' tidak ditemukan pada workbook SLIK Hijau.");
                }
            }

            return;
        }

        foreach (['nama debt', 'nama unit', 'nama kanca', 'sumberpipeline', 'keterangan', 'status kunjungan'] as $required) {
            if (! isset($headers[$required])) {
                throw new \RuntimeException("Kolom wajib '{$required}' tidak ditemukan pada workbook Pipeline Mikro.");
            }
        }
        foreach (array_keys(self::MONTHS) as $month) {
            if (! isset($headers[$this->normalize($month)])) {
                throw new \RuntimeException("Kolom kunjungan {$month} tidak ditemukan pada workbook Pipeline Mikro.");
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function mapRecord(string $dataset, int $rowNumber, array $row, array $headers, string $sheetName, string $now): ?array
    {
        if ($dataset === 'slik_hijau') {
            return $this->mapSlikHijauRecord($rowNumber, $row, $headers, $sheetName, $now);
        }

        $debtor = $this->value($row, $headers, 'nama debt');
        $cif = $this->value($row, $headers, 'cif');
        $legacyId = $this->value($row, $headers, 'idpipeline lama');
        if ($debtor === '' && $cif === '' && $legacyId === '') {
            return null;
        }

        $branchKey = $this->branchKey($this->value($row, $headers, 'nama kanca'));
        $visitMonths = [];
        $plannedMonths = [];
        foreach (self::MONTHS as $sourceMonth => $label) {
            $status = $this->normalize($this->value($row, $headers, $sourceMonth));
            if ($status === 'done') {
                $visitMonths[] = $label;
            } elseif ($status === 'plan') {
                $plannedMonths[] = $label;
            }
        }
        $visitStatus = $visitMonths !== [] ? 'done' : ($plannedMonths !== [] ? 'scheduled' : 'pending');

        $sourceColumns = $headers['sumberpipeline'] ?? [];
        $source = $this->lastPopulatedValue($row, $sourceColumns);
        $legacySource = $this->value($row, $headers, 'sumberpipeline lama');

        return [
            'source_key' => 'prewash',
            'source_row' => $rowNumber,
            'debtor_name' => $this->nullable($debtor),
            'address' => $this->nullable($this->value($row, $headers, 'alamat')),
            'phone' => $this->nullable($this->value($row, $headers, 'nomorhandphone')),
            'cif' => $this->nullable($cif),
            'loan_account' => $this->nullable($this->value($row, $headers, 'norek pinjaman')),
            'branch_key' => $branchKey ?? 'outside',
            'branch_name' => self::AREA_BRANCHES[$branchKey] ?? $this->value($row, $headers, 'nama kanca'),
            'unit_code' => $this->nullable($this->value($row, $headers, 'branch')),
            'unit_name' => $this->nullable($this->value($row, $headers, 'nama unit')),
            'plafond' => $this->number($this->value($row, $headers, 'plafond')),
            'outstanding' => $this->number($this->value($row, $headers, 'os')),
            'source_pipeline' => $this->nullable($source !== '' ? $source : $legacySource),
            'legacy_source_pipeline' => $this->nullable($legacySource),
            'mantri_pn' => $this->nullable($this->value($row, $headers, 'pn mantri')),
            'mantri_name' => $this->nullable($this->value($row, $headers, 'nama mantri')),
            'recommended_product' => $this->nullable($this->value($row, $headers, 'product rekomendasi')),
            'score' => $this->nullableNumber($this->value($row, $headers, 'score')),
            'legacy_pipeline_id' => $this->nullable($legacyId),
            'description' => $this->nullable($this->value($row, $headers, 'keterangan')),
            'real_plafond' => $this->number($this->value($row, $headers, 'plafond real')),
            'visit_status' => $visitStatus,
            'reported_visit_count' => max(0, (int) $this->number($this->value($row, $headers, 'status kunjungan'))),
            'visit_count' => count($visitMonths),
            'planned_count' => count($plannedMonths),
            'last_visit_month' => $visitMonths !== [] ? end($visitMonths) : null,
            'visit_months' => json_encode($visitMonths, JSON_UNESCAPED_UNICODE),
            'planned_months' => json_encode($plannedMonths, JSON_UNESCAPED_UNICODE),
            'source_sheet' => $sheetName,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** @return array<string, mixed>|null */
    private function mapSlikHijauRecord(int $rowNumber, array $row, array $headers, string $sheetName, string $now): ?array
    {
        $debtor = $this->value($row, $headers, 'nama debitur');
        $cif = $this->value($row, $headers, 'cifno');
        $pipelineId = $this->value($row, $headers, 'id');
        if ($debtor === '' && $cif === '' && $pipelineId === '') {
            return null;
        }

        $status = $this->normalize($this->value($row, $headers, 'status kunjungan'));
        $result = $this->normalize($this->value($row, $headers, 'hasil kunjungan'));
        if ($status !== 'done' || ! str_contains($result, 'berminat')) {
            return null;
        }

        $branchName = $this->value($row, $headers, 'mainbr desc');
        $branchKey = $this->branchKey($branchName);
        $visitMonth = $this->excelMonth($this->value($row, $headers, 'tgl kunjungan'));
        $description = collect([
            $this->value($row, $headers, 'detail hasil kunjungan'),
            $this->value($row, $headers, 'tl kunjungan'),
            $this->value($row, $headers, 'keterangan'),
            $this->value($row, $headers, 'feedback masalah'),
        ])->map(fn (string $value): string => trim($value))->filter()->unique()->implode(' | ');

        return [
            'source_key' => 'slik_hijau',
            'source_row' => $rowNumber,
            'debtor_name' => $this->nullable($debtor),
            'address' => $this->nullable($this->value($row, $headers, 'alamat kunjungan')),
            'phone' => null,
            'cif' => $this->nullable($cif),
            'loan_account' => null,
            'branch_key' => $branchKey ?? 'outside',
            'branch_name' => self::AREA_BRANCHES[$branchKey] ?? $branchName,
            'unit_code' => $this->nullable($this->value($row, $headers, 'branch')),
            'unit_name' => $this->nullable($this->value($row, $headers, 'nama uker')),
            'plafond' => $this->number($this->value($row, $headers, 'perkiraan plafond')),
            'outstanding' => 0,
            'source_pipeline' => 'SLIK Hijau',
            'legacy_source_pipeline' => null,
            'mantri_pn' => $this->nullable($this->value($row, $headers, 'pn')),
            'mantri_name' => $this->nullable($this->value($row, $headers, 'nama pegawai')),
            'recommended_product' => $this->nullable($this->value($row, $headers, 'pipeline')),
            'score' => null,
            'legacy_pipeline_id' => $this->nullable($pipelineId),
            'description' => $this->nullable($description),
            'real_plafond' => 0,
            'visit_status' => 'done',
            'reported_visit_count' => 1,
            'visit_count' => 1,
            'planned_count' => 0,
            'last_visit_month' => $visitMonth,
            'visit_months' => json_encode($visitMonth !== null ? [$visitMonth] : [], JSON_UNESCAPED_UNICODE),
            'planned_months' => json_encode([], JSON_UNESCAPED_UNICODE),
            'source_sheet' => $sheetName,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function excelMonth(string $value): ?string
    {
        if ($this->isNullLike($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $month = (int) ExcelDate::excelToDateTimeObject((float) $value)->format('n');
            } else {
                $month = (int) (new \DateTimeImmutable($value))->format('n');
            }

            return array_values(self::MONTHS)[$month - 1] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function branchKey(string $value): ?string
    {
        $normalized = $this->normalize($value);
        foreach (array_keys(self::AREA_BRANCHES) as $branch) {
            if (str_contains($normalized, $branch)) {
                return $branch;
            }
        }

        return null;
    }

    private function value(array $row, array $headers, string $header): string
    {
        foreach ($headers[$this->normalize($header)] ?? [] as $column) {
            if (array_key_exists($column, $row)) {
                return trim((string) $row[$column]);
            }
        }

        return '';
    }

    private function lastPopulatedValue(array $row, array $columns): string
    {
        foreach (array_reverse($columns) as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            if (! $this->isNullLike($value)) {
                return $value;
            }
        }

        return '';
    }

    private function normalize(mixed $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(Str::lower(trim((string) $value)))) ?? '');
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return ! $this->isNullLike($value) ? $value : null;
    }

    private function isNullLike(string $value): bool
    {
        return in_array($this->normalize($value), ['', 'null', 'n a', 'nan'], true);
    }

    private function number(string $value): float
    {
        $normalized = preg_replace('/[^0-9eE+\-.]/', '', $value) ?? '';

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function nullableNumber(string $value): ?float
    {
        return $this->isNullLike($value) ? null : $this->number($value);
    }

    /** @return array<int, string> */
    private function readSharedStrings(string $path): array
    {
        $archive = new ZipArchive;
        $archive->open($path);
        $exists = $archive->locateName('xl/sharedStrings.xml') !== false;
        $archive->close();
        if (! $exists) {
            return [];
        }

        $reader = $this->openArchiveXml($path, 'xl/sharedStrings.xml');
        $strings = [];
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                    continue;
                }
                $document = new \DOMDocument;
                if (! $document->loadXML($reader->readOuterXml(), LIBXML_NONET | LIBXML_COMPACT)) {
                    $strings[] = '';

                    continue;
                }
                $parts = [];
                foreach ($document->getElementsByTagName('t') as $text) {
                    $parts[] = $text->textContent;
                }
                $strings[] = implode('', $parts);
            }
        } finally {
            $reader->close();
        }

        return $strings;
    }

    /** @return array<int, array{name: string, entry: string, hidden: bool}> */
    private function worksheetEntries(string $path): array
    {
        $relationships = [];
        $reader = $this->openArchiveXml($path, 'xl/_rels/workbook.xml.rels');
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'Relationship') {
                    continue;
                }
                $target = str_replace('\\', '/', (string) $reader->getAttribute('Target'));
                if ($target !== '' && ! str_contains($target, '..')) {
                    $relationships[(string) $reader->getAttribute('Id')] = str_starts_with($target, '/')
                        ? ltrim($target, '/')
                        : 'xl/'.ltrim($target, '/');
                }
            }
        } finally {
            $reader->close();
        }

        $sheets = [];
        $reader = $this->openArchiveXml($path, 'xl/workbook.xml');
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'sheet') {
                    continue;
                }
                $relationshipId = (string) $reader->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                if (! isset($relationships[$relationshipId])) {
                    continue;
                }
                $sheets[] = [
                    'name' => (string) $reader->getAttribute('name'),
                    'entry' => $relationships[$relationshipId],
                    'hidden' => in_array((string) $reader->getAttribute('state'), ['hidden', 'veryHidden'], true),
                ];
            }
        } finally {
            $reader->close();
        }

        return $sheets;
    }

    /** @return \Generator<int, array<int, mixed>> */
    private function xlsxRows(string $path, string $entry, array $sharedStrings): \Generator
    {
        $reader = $this->openArchiveXml($path, $entry);
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }
                $rowNumber = max(1, (int) $reader->getAttribute('r'));
                $document = new \DOMDocument;
                if (! $document->loadXML($reader->readOuterXml(), LIBXML_NONET | LIBXML_COMPACT)) {
                    continue;
                }
                $values = [];
                foreach ($document->getElementsByTagName('c') as $cell) {
                    if (preg_match('/^([A-Z]+)\d+$/i', (string) $cell->getAttribute('r'), $matches) !== 1) {
                        continue;
                    }
                    $column = Coordinate::columnIndexFromString(strtoupper($matches[1]));
                    if ($column > 36) {
                        continue;
                    }
                    $value = $this->xlsxCellValue($cell, $sharedStrings);
                    if ($value !== null && trim((string) $value) !== '') {
                        $values[$column] = $value;
                    }
                }
                yield $rowNumber => $values;
            }
        } finally {
            $reader->close();
        }
    }

    private function xlsxCellValue(\DOMElement $cell, array $sharedStrings): mixed
    {
        if ($cell->getAttribute('t') === 'inlineStr') {
            $parts = [];
            foreach ($cell->getElementsByTagName('t') as $text) {
                $parts[] = $text->textContent;
            }

            return implode('', $parts);
        }
        $values = $cell->getElementsByTagName('v');
        if ($values->length === 0) {
            return null;
        }
        $value = $values->item(0)?->textContent;

        return $cell->getAttribute('t') === 's' ? ($sharedStrings[(int) $value] ?? null) : $value;
    }

    private function openArchiveXml(string $path, string $entry): XMLReader
    {
        $realPath = realpath($path);
        if (! is_string($realPath) || $realPath === '' || str_contains($realPath, '#')) {
            throw new \RuntimeException('Lokasi workbook Pipeline Mikro tidak valid.');
        }
        $reader = new XMLReader;
        if (! $reader->open('zip://'.str_replace('\\', '/', $realPath).'#'.$entry, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new \RuntimeException('XML workbook Pipeline Mikro tidak dapat dibuka.');
        }

        return $reader;
    }
}
