<?php

namespace App\Services\Import;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DlyKapResegmentasiCsvImporter
{
    public const TABLE = 'dly_kap_resegmentasi';

    private const NORMALIZATION_VERSION = '2026-05-05-segmen-medium';

    public const NORMALIZED_HEADERS = [
        'uniqueid_dly_kap',
        'periode',
        'kanwil',
        'kode_cabang',
        'kode_unit',
        'segmen_kategori',
        'segmen',
        'keterangan',
        'l_rp',
        'l_deb',
        'dpk_rp',
        'dpk_deb',
        'kl_rp',
        'kl_deb',
        'd_rp',
        'd_deb',
        'm_rp',
        'm_deb',
        'npl_rp',
        'npl_deb',
        'tl_rp',
        'tl_deb',
    ];

    private const METRIC_COLUMNS = [
        'l_rp' => 2,
        'l_deb' => 3,
        'dpk_rp' => 4,
        'dpk_deb' => 5,
        'kl_rp' => 6,
        'kl_deb' => 7,
        'd_rp' => 8,
        'd_deb' => 9,
        'm_rp' => 10,
        'm_deb' => 11,
        'npl_rp' => 12,
        'npl_deb' => 13,
        'tl_rp' => 14,
        'tl_deb' => 15,
    ];

    private const SEGMEN_KATEGORI_BY_HEADER = [
        'TEXTBOX171' => 'SEGMEN MICRO',
        'TEXTBOX161' => 'SEGMEN CONSUMER',
        'TEXTBOX226' => 'SEGMEN SMALL',
        'TEXTBOX254' => 'SEGMEN MEDIUM',
        'TEXTBOX282' => 'SEGMEN COMMERCIAL',
        'TEXTBOX310' => 'SEGMEN CORPORATE',
    ];

    /**
     * @return array{metadata: array<string, mixed>, rows: array<int, array<string, mixed>>, warnings: array<int, string>}
     */
    public function parse(string $path): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("File DLY KAP tidak ditemukan: {$path}");
        }

        $cleanupPath = null;
        $path = $this->ensureCsvSource($path, $cleanupPath);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("File CSV tidak bisa dibuka: {$path}");
        }

        $warnings = [];
        $metadata = [
            'periode' => null,
            'kanwil' => null,
            'kode_cabang' => null,
            'kode_unit' => null,
        ];
        $rows = [];
        $lineNumber = 0;
        $sectionHeader = null;
        $segmenKategori = null;
        $skipNextFooterTotalRow = false;
        $delimiter = ',';

        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $lineNumber++;
                $row = $this->normalizeRow($row);

                if ($lineNumber === 1) {
                    continue;
                }

                if ($lineNumber === 2) {
                    $metadata = [
                        'periode' => $this->normalizeDate($row[0] ?? null),
                        'kanwil' => $this->blankToNull($row[1] ?? null),
                        'kode_cabang' => $this->blankToNull($row[2] ?? null),
                        'kode_unit' => $this->blankToNull($row[3] ?? null),
                    ];

                    continue;
                }

                if ($this->isBlankRow($row)) {
                    continue;
                }

                $firstCell = strtoupper(trim((string) ($row[0] ?? '')));
                if ($skipNextFooterTotalRow) {
                    $skipNextFooterTotalRow = false;
                    continue;
                }

                if (str_starts_with($firstCell, 'TEXTBOX')) {
                    $skipNextFooterTotalRow = true;
                    continue;
                }

                if (str_starts_with($firstCell, 'SEGMEN')) {
                    $sectionHeader = $firstCell;
                    $segmenKategori = $this->resolveSegmenKategori($row[1] ?? null);
                    continue;
                }

                if ($sectionHeader === null || $segmenKategori === null) {
                    $warnings[] = "Baris {$lineNumber} dilewati karena muncul sebelum header SEGMEN.";
                    continue;
                }

                if (count($row) < 16) {
                    $warnings[] = "Baris {$lineNumber} dilewati karena kolom kurang dari format minimum 16 kolom.";
                    continue;
                }

                $rows[] = $this->makeMetricRecord(
                    $metadata,
                    $sectionHeader,
                    $segmenKategori,
                    $lineNumber,
                    $this->blankToNull($row[0] ?? null),
                    $this->blankToNull($row[1] ?? null),
                    $row,
                    2
                );
            }
        } finally {
            fclose($handle);
            if ($cleanupPath !== null) {
                @unlink($cleanupPath);
            }
        }

        foreach (['periode', 'kanwil', 'kode_cabang', 'kode_unit'] as $key) {
            if ($metadata[$key] === null) {
                $warnings[] = "Metadata {$key} kosong atau tidak terbaca dari baris 2.";
            }
        }

        return [
            'metadata' => $metadata,
            'rows' => $rows,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{absolute_path: string, relative_path: string, total_rows: int, metadata: array<string, mixed>, warnings: array<int, string>}
     */
    public function stageNormalizedCsv(string $sourcePath, ?string $relativeDirectory = null): array
    {
        $relativeDirectory ??= 'excel_imports/dly_kap';
        $relativeDirectory = trim($relativeDirectory, '/\\');
        $absoluteDirectory = Storage::path($relativeDirectory);
        if (!is_dir($absoluteDirectory)) {
            mkdir($absoluteDirectory, 0777, true);
        }

        // Fingerprint-based filename: same source file reuses staged CSV (no re-parsing)
        $fingerprint = md5(self::NORMALIZATION_VERSION . '|' . $sourcePath . '|' . (@filemtime($sourcePath) ?: 0) . '|' . (@filesize($sourcePath) ?: 0));
        $fileName = 'dly_kap_normalized_' . $fingerprint . '.csv';
        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $fileName;
        $relativePath = $relativeDirectory . '/' . $fileName;

        $legacyAbsolutePath = storage_path('app/' . $relativePath);
        if (!file_exists($absolutePath) && file_exists($legacyAbsolutePath)) {
            @copy($legacyAbsolutePath, $absolutePath);
        }

        if (file_exists($absolutePath) && filesize($absolutePath) > 0) {
            if ($this->csvHasNormalizedHeaders($absolutePath)) {
                $totalRows = max(0, (int) substr_count((string) file_get_contents($absolutePath), "\n") - 1);
                return [
                    'absolute_path' => $absolutePath,
                    'relative_path' => $relativePath,
                    'total_rows' => $totalRows,
                    'metadata' => [],
                    'warnings' => [],
                ];
            }

            @unlink($absolutePath);
        }

        $parsed = $this->parse($sourcePath);

        $output = fopen($absolutePath, 'wb');
        if ($output === false) {
            throw new \RuntimeException('Gagal membuat CSV staging DLY KAP Resegmentasi.');
        }

        try {
            fputcsv($output, self::NORMALIZED_HEADERS);
            foreach ($parsed['rows'] as $row) {
                fputcsv($output, array_map(static fn (string $header) => $row[$header] ?? null, self::NORMALIZED_HEADERS));
            }
        } catch (\Throwable $e) {
            fclose($output);
            @unlink($absolutePath);
            throw $e;
        }

        fclose($output);

        return [
            'absolute_path' => $absolutePath,
            'relative_path' => $relativePath,
            'total_rows' => count($parsed['rows']),
            'metadata' => $parsed['metadata'],
            'warnings' => $parsed['warnings'],
        ];
    }

    /**
     * @return array{metadata: array<string, mixed>, rows: array<int, array<string, mixed>>, warnings: array<int, string>}
     */
    public function parseForImport(string $path): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("File DLY KAP tidak ditemukan: {$path}");
        }

        if ($this->csvHasNormalizedHeaders($path)) {
            return $this->parseNormalizedCsv($path);
        }

        return $this->parse($path);
    }

    /**
     * @return array{metadata: array<string, mixed>, rows: array<int, array<string, mixed>>, warnings: array<int, string>}
     */
    private function parseNormalizedCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("File CSV tidak bisa dibuka: {$path}");
        }

        $warnings = [];
        $rows = [];
        $metadata = [
            'periode' => null,
            'kanwil' => null,
            'kode_cabang' => null,
            'kode_unit' => null,
        ];

        try {
            $headers = $this->normalizeRow((array) fgetcsv($handle));
            $headers = array_map(static fn ($header): string => strtolower((string) $header), $headers);

            if ($headers !== self::NORMALIZED_HEADERS) {
                throw new \InvalidArgumentException('Header normalized DLY KAP tidak sesuai.');
            }

            $lineNumber = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                $row = $this->normalizeRow($row);

                if ($this->isBlankRow($row)) {
                    continue;
                }

                $record = array_combine(self::NORMALIZED_HEADERS, array_pad($row, count(self::NORMALIZED_HEADERS), null));
                if ($record === false) {
                    $warnings[] = "Baris {$lineNumber} dilewati karena jumlah kolom normalized tidak sesuai.";
                    continue;
                }

                $record['periode'] = $this->normalizeDate($record['periode'] ?? null);
                foreach (['uniqueid_dly_kap', 'kanwil', 'kode_cabang', 'kode_unit', 'segmen_kategori', 'segmen', 'keterangan'] as $column) {
                    $record[$column] = $this->blankToNull($record[$column] ?? null);
                }
                $record['segmen_kategori'] = $this->normalizeSegmenKategori($record['segmen_kategori'] ?? null);

                foreach (array_keys(self::METRIC_COLUMNS) as $column) {
                    $record[$column] = str_ends_with($column, '_deb')
                        ? $this->normalizeInteger($record[$column] ?? null)
                        : $this->normalizeDecimal($record[$column] ?? null);
                }

                if ($record['uniqueid_dly_kap'] === null) {
                    $warnings[] = "Baris {$lineNumber} dilewati karena uniqueid_dly_kap kosong.";
                    continue;
                }

                if ($rows === []) {
                    $metadata = [
                        'periode' => $record['periode'],
                        'kanwil' => $record['kanwil'],
                        'kode_cabang' => $record['kode_cabang'],
                        'kode_unit' => $record['kode_unit'],
                    ];
                }

                $rows[] = $record;
            }
        } finally {
            fclose($handle);
        }

        foreach (['periode', 'kanwil', 'kode_cabang', 'kode_unit'] as $key) {
            if ($metadata[$key] === null) {
                $warnings[] = "Metadata {$key} kosong atau tidak terbaca dari CSV normalized.";
            }
        }

        return [
            'metadata' => $metadata,
            'rows' => $rows,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{inserted: int, deleted: int}
     */
    public function import(array $rows, bool $replaceScope = true): array
    {
        if ($rows === []) {
            return ['inserted' => 0, 'deleted' => 0];
        }

        return DB::transaction(function () use ($rows, $replaceScope): array {
            $deleted = 0;

            if ($replaceScope) {
                $first = $rows[0];
                $deleted = DB::table(self::TABLE)
                    ->where('periode', $first['periode'])
                    ->where('kanwil', $first['kanwil'])
                    ->where('kode_cabang', $first['kode_cabang'])
                    ->where('kode_unit', $first['kode_unit'])
                    ->delete();
            }

            $now = now();
            $payload = array_map(static function (array $row) use ($now): array {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;

                return $row;
            }, $rows);

            foreach (array_chunk($payload, 1000) as $chunk) {
                DB::table(self::TABLE)->upsert(
                    $chunk,
                    ['uniqueid_dly_kap'],
                    array_values(array_diff(array_keys($chunk[0]), ['uniqueid_dly_kap', 'created_at']))
                );
            }

            $this->fixSegmenKategoriFromSegmen($rows[0] ?? null);

            return ['inserted' => count($rows), 'deleted' => $deleted];
        });
    }

    private function fixSegmenKategoriFromSegmen(?array $firstRow): void
    {
        if ($firstRow === null) {
            return;
        }

        $periode = $firstRow['periode'] ?? null;
        $kanwil = $firstRow['kanwil'] ?? null;
        $kodeCabang = $firstRow['kode_cabang'] ?? null;
        $kodeUnit = $firstRow['kode_unit'] ?? null;

        if (empty($periode) || empty($kanwil) || empty($kodeCabang) || empty($kodeUnit)) {
            return;
        }

        $table = self::TABLE;

        $validSegmen = [
            'SEGMEN MICRO',
            'SEGMEN CONSUMER',
            'SEGMEN SMALL',
            'SEGMEN MEDIUM',
            'SEGMEN COMMERCIAL',
            'SEGMEN CORPORATE',
        ];

        $caseWhen = [];
        foreach ($validSegmen as $seg) {
            $caseWhen[] = "WHEN `segmen` = '$seg' THEN '$seg'";
        }
        $caseClause = implode(' ', $caseWhen);

        DB::affectingStatement("
            UPDATE `{$table}`
            SET `segmen_kategori` = CASE
                {$caseClause}
                ELSE `segmen_kategori`
            END,
            `updated_at` = NOW()
            WHERE (`segmen_kategori` IS NULL OR TRIM(`segmen_kategori`) = '')
              AND `periode` = ?
              AND `kanwil` = ?
              AND `kode_cabang` = ?
              AND `kode_unit` = ?
              AND `segmen` IN ('" . implode("','", $validSegmen) . "')
        ", [$periode, $kanwil, $kodeCabang, $kodeUnit]);
    }

    /**
     * @param array<int, string|null> $row
     * @return array<int, string|null>
     */
    private function normalizeRow(array $row): array
    {
        if (isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
        }

        return array_map(function ($value): ?string {
            if ($value === null) {
                return null;
            }

            $value = str_replace("\xC2\xA0", ' ', (string) $value);
            $value = trim($value);

            return $value === '' ? null : $value;
        }, $row);
    }

    private function ensureCsvSource(string $path, ?string &$cleanupPath): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['csv', 'txt'], true)) {
            return $path;
        }

        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw new \InvalidArgumentException('Format DLY KAP Resegmentasi harus CSV, TXT, XLSX, atau XLS.');
        }

        $directory = Storage::path('import_staging');
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $csvPath = $directory . DIRECTORY_SEPARATOR . 'dly_kap_source_' . Str::uuid()->toString() . '.csv';

        // Native XLSX streaming (10-50x faster than PhpSpreadsheet)
        if ($extension === 'xlsx' && app(ExcelStagingService::class)->dumpFlatXlsxToCsv($path, $csvPath)) {
            $cleanupPath = $csvPath;
            return $csvPath;
        }

        // Fallback: PhpSpreadsheet (for .xls or when native streaming fails)
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $output = fopen($csvPath, 'wb');
        if ($output === false) {
            $spreadsheet->disconnectWorksheets();
            throw new \RuntimeException('Gagal membuat staging CSV dari Excel DLY KAP.');
        }

        try {
            foreach ($sheet->toArray(null, true, true, false) as $row) {
                fputcsv($output, $row);
            }
        } finally {
            fclose($output);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        $cleanupPath = $csvPath;

        return $csvPath;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<int, string|null> $row
     * @return array<string, mixed>
     */
    private function makeMetricRecord(
        array $metadata,
        string $sectionHeader,
        string $segmenKategori,
        int $lineNumber,
        ?string $segmen,
        ?string $keterangan,
        array $row,
        int $metricOffset
    ): array {
        $record = [
            'uniqueid_dly_kap' => $this->makeUniqueId($metadata, $sectionHeader, $lineNumber, [$segmen, $keterangan]),
            'periode' => $metadata['periode'],
            'kanwil' => $metadata['kanwil'],
            'kode_cabang' => $metadata['kode_cabang'],
            'kode_unit' => $metadata['kode_unit'],
            'segmen_kategori' => $segmenKategori,
            'segmen' => $segmen,
            'keterangan' => $keterangan,
        ];

        foreach (array_keys(self::METRIC_COLUMNS) as $position => $column) {
            $index = $metricOffset + $position;
            $record[$column] = str_ends_with($column, '_deb')
                ? $this->normalizeInteger($row[$index] ?? null)
                : $this->normalizeDecimal($row[$index] ?? null);
        }

        return $record;
    }

    /**
     * @param array<int, string|null> $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function csvHasNormalizedHeaders(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            $headers = fgetcsv($handle);
        } finally {
            fclose($handle);
        }

        if (!is_array($headers)) {
            return false;
        }

        $headers = $this->normalizeRow($headers);

        return array_map(static fn ($header): string => strtolower((string) $header), $headers) === self::NORMALIZED_HEADERS;
    }

    private function resolveSegmenKategori(?string $headerMarker): ?string
    {
        $marker = strtoupper(trim((string) ($headerMarker ?? '')));

        return $this->normalizeSegmenKategori(self::SEGMEN_KATEGORI_BY_HEADER[$marker] ?? null);
    }

    private function normalizeSegmenKategori(?string $value): ?string
    {
        $value = $this->blankToNull($value);
        if ($value === null) {
            return null;
        }

        return strtoupper($value) === 'MEDIUM' ? 'SEGMEN MEDIUM' : $value;
    }

    private function blankToNull($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeDate($value): ?string
    {
        $value = $this->blankToNull($value);
        if ($value === null) {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function normalizeDecimal($value): ?string
    {
        $value = $this->blankToNull($value);
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', $value);

        return $normalized === '' || !is_numeric($normalized)
            ? null
            : number_format((float) $normalized, 2, '.', '');
    }

    private function normalizeInteger($value): ?int
    {
        $value = $this->blankToNull($value);
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9\-]/', '', $value);

        return $normalized === '' || !is_numeric($normalized)
            ? null
            : (int) $normalized;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<int, string|null> $row
     */
    private function makeUniqueId(array $metadata, string $sectionHeader, int $lineNumber, array $row): string
    {
        $fingerprint = implode('|', [
            $metadata['periode'] ?? '',
            $metadata['kanwil'] ?? '',
            $metadata['kode_cabang'] ?? '',
            $metadata['kode_unit'] ?? '',
            $sectionHeader,
            $lineNumber,
            $row[0] ?? '',
            $row[1] ?? '',
        ]);

        return 'DLYKAP_' . sha1($fingerprint);
    }
}
