<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ChunkReadFilter implements IReadFilter
{
    private int $startRow  = 0;
    private int $endRow    = 0;
    private int $headerRow = 1; // default: Excel row 1 (1-based)

    /**
     * Set the header row (1-based Excel row number).
     * This row is ALWAYS read regardless of the chunk window.
     */
    public function setHeaderRow(int $headerRow): void
    {
        $this->headerRow = $headerRow;
    }

    /**
     * Set the data chunk window.
     * @param int $startRow  1-based Excel row number of first data row in chunk
     * @param int $chunkSize number of rows to read
     */
    public function setRows(int $startRow, int $chunkSize): void
    {
        $this->startRow = $startRow;
        $this->endRow   = $startRow + $chunkSize;
    }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        if ($row == $this->headerRow) return true;
        if ($row >= $this->startRow && $row < $this->endRow) return true;
        return false;
    }
}

class ImportExcelController extends Controller
{
    private const DAILY_LOAN_REPORT_ID = 8;
    private const DAILY_LOAN_SOURCE_HEADERS = [
        'PERIODE','KODE_KANWIL1','KANWIL1','KODE_CABANG1','CABANG1','BRANCH1','UNIT1','CURTYP','AO_NAME','CIFNO',
        'NOMOR_REKENING1','STATUS_REKENING1','LN_TYPE','NAMA_DEBITUR1','RATE','JANGKA_WAKTU1','PLAFON','BAKI_DEBET1',
        'CKPN','NILAI_TERCATAT1','KOL_ADK1','KOLEK_DETAIL','KOLEK','KOLEKTABILITAS_LANCAR','KOLEKTABILITAS_DPK',
        'KOLEKTABILITAS_KURANGLANCAR','KOLEKTABILITAS_DIRAGUKAN','KOLEKTABILITAS_MACET','Textbox20','TUNGGAKAN_POKOK',
        'TUNGGAKAN_BUNGA','TUNGGAKAN_PENALTI','UMUR_TUNGGAKAN','TGL_REALISASI','TGL_JATUH_TEMPO','TANGGAL_MENUNGGAK',
        'TGL_BAYAR_TERAKHIR','TGL_TERMINATE','LAST_DATE_MAINTENANCE_BILLING','NEXT_PMT_DATE','NEXT_PMT_INT_DATE',
        'ADVANCE_PAYMENT','BAP','PAYMENT_AMOUNT','FINAL_PAYMENT_AMOUNT','NPB_POKOK_LA','NPB_POKOK_LF','NPB_BUNGA_LA',
        'NPB_BUNGA_LF','JML_ANGSURAN1','JUMLAH_BAYAR','DEFFERED_BUNGA','SAI_TUNGGAKAN','SAI_DEFFERED','SAI1',
        'FREQ_PAYMENT','FREQ_INT_PAYMENT','JADWAL_GP_POKOK','PN_PENGELOLA1','PN_NAME1','PN_PEMRAKARSA1','PN_REFERRAL1',
        'PN_RESTRUK1','PN_PENGELOLA2','PN_PEMUTUS1','PN_CRM1','PN_CRR','PN_REFERRAL_NAIK_KELAS1','JUMLAH_PN1',
        'JUMLAH_PN_ALL1','CODE','DESCRIPTION','KECAMATAN_T_TINGGAL','KELURAHAN_T_TINGGAL','KODEPOS_T_TINGGAL',
        'KECAMATAN_T_USAHA','KELURAHAN_T_USAHA','KODEPOS_T_USAHA','SEGMEN_DASHBOARD','PRODUK_DASHBOARD',
        'DIVISI_SEGMEN_DASHBOARD','NPL_METHOD','RESTRUK_KE1','JENIS_RESTRUK1','TGL_AKAD_RESTRUK','FLAG_RESTRUK',
        'FLAG_RESTRUK_COVID1','FLAG_COMMODITY_CHAIN1','FLAG_BRIGUNA_DIGITAL1','FLAG_AGF','FLAG_AFT','PMTAMT',
        'PMTAMT_Base','OFFCR','LBDOTU','KETERANGAN_PN_PENGELOLA','Textbox21','FLAG_KLAIM','OS_SEBELUM_KLAIM',
        'OS_PENUH_BERJALAN','BILPRN','BILINT','BILLC'
    ];

    private function isCsvFile(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['csv', 'txt'], true);
    }

    private function isDailyLoanActive(): bool
    {
        return (int) session('active_id_report') === self::DAILY_LOAN_REPORT_ID;
    }

    private function resolveActiveReport(): ?object
    {
        $idReport = session('active_id_report');
        if (!$idReport) {
            return null;
        }

        return DB::table('nama_report')->where('id_report', $idReport)->first();
    }

    private function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
    {
        $reportData = $this->resolveActiveReport();

        return ($reportData && !empty($reportData->table_name))
            ? (string) $reportData->table_name
            : $default;
    }

    private function detectHeaderIndex(array $rows, ?string $tableName = null): ?int
    {
        $bestIndex = null;
        $bestScore = 0;
        $dbColumnsLookup = [];

        if ($tableName && Schema::hasTable($tableName)) {
            $dbColumnsLookup = array_fill_keys(
                array_map('strtolower', Schema::getColumnListing($tableName)),
                true
            );
        }

        foreach ($rows as $i => $row) {
            $rowUpper = array_map(fn($v) => strtoupper(trim((string) $v)), $row);
            if (in_array('PERIODE', $rowUpper, true) || in_array('POSISI', $rowUpper, true)) {
                return $i;
            }

            if (empty($dbColumnsLookup)) {
                continue;
            }

            $score = 0;
            foreach ($row as $cell) {
                $header = trim((string) $cell);
                if ($header === '') {
                    continue;
                }

                foreach ($this->getHeaderDatabaseCandidates($header) as $candidateColumn) {
                    if (isset($dbColumnsLookup[$candidateColumn])) {
                        $score++;
                        break;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $i;
            }
        }

        return $bestScore >= 2 ? $bestIndex : null;
    }

    private function headerNotFoundMessage(?string $tableName = null): string
    {
        if ($tableName) {
            return "Header utama file tidak ditemukan atau tidak cocok dengan kolom tabel `{$tableName}`.";
        }

        return 'Header utama file tidak ditemukan.';
    }

    private function useDailyLoanReport(Request $request): Request
    {
        $request->merge(['id_report' => self::DAILY_LOAN_REPORT_ID]);
        session(['active_id_report' => self::DAILY_LOAN_REPORT_ID]);

        return $request;
    }

    public function uploadDailyLoanExcel(Request $request)
    {
        return $this->uploadExcel($this->useDailyLoanReport($request), ['csv', 'txt']);
    }

    public function previewDailyLoanExcel(Request $request)
    {
        return $this->previewExcel($this->useDailyLoanReport($request));
    }

    public function prepareDailyLoanPreview(Request $request)
    {
        return $this->preparePreviewStream($this->useDailyLoanReport($request));
    }

    public function initDailyLoanImport(Request $request)
    {
        return $this->initExcelImport($this->useDailyLoanReport($request));
    }

    public function streamDailyLoanImport(Request $request)
    {
        return $this->processExcelStream($this->useDailyLoanReport($request));
    }

    public function chunkDailyLoanImport(Request $request)
    {
        return $this->processExcelChunk($this->useDailyLoanReport($request));
    }

    private function cleanupImportedFile(string $relativePath = '', ?string $absolutePath = null): void
    {
        try {
            if ($relativePath !== '' && Storage::exists($relativePath)) {
                Storage::delete($relativePath);
                return;
            }

            if ($absolutePath && file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to cleanup imported file: ' . $e->getMessage(), [
                'relative_path' => $relativePath,
                'absolute_path' => $absolutePath,
            ]);
        }
    }

    private function buildImportContext(string $tableName, array $normalizedHeaders, array $activeFilters = []): array
    {
        $validIndexes = [];
        foreach ($normalizedHeaders as $i => $h) {
            if (!str_starts_with($h, 'COL_')) {
                $validIndexes[] = $i;
            }
        }

        $headerCount = empty($normalizedHeaders) ? 0 : (max(array_keys($normalizedHeaders)) + 1);
        $tableColumns = array_map('strtolower', Schema::getColumnListing($tableName));
        $tableColumnsLookup = array_fill_keys($tableColumns, true);

        $defaultUniqueIdCol = str_contains($tableName, 'simpanan') ? 'uniqueid_SimoPN' : 'uniqueid_namareport';
        $uniqueIdCol = in_array(strtolower($defaultUniqueIdCol), $tableColumns, true)
            ? $defaultUniqueIdCol
            : null;
        $suffix = str_contains($tableName, 'simpanan') ? '_SimoPN' : '_DLD';
        $skipColumns = ['id'];
        if ($uniqueIdCol) {
            $skipColumns[] = strtolower($uniqueIdCol);
        }
        $skipColumnsLookup = array_fill_keys($skipColumns, true);

        $filterLookups = [];
        foreach ($activeFilters as $filterIdx => $values) {
            $filterLookups[(int) $filterIdx] = array_fill_keys(
                array_map(fn ($v) => (string) $v, (array) $values),
                true
            );
        }

        return [
            'valid_indexes' => $validIndexes,
            'header_count' => $headerCount,
            'table_columns_lookup' => $tableColumnsLookup,
            'unique_id_col' => $uniqueIdCol,
            'suffix' => $suffix,
            'skip_columns_lookup' => $skipColumnsLookup,
            'filter_lookups' => $filterLookups,
        ];
    }

    private function detectCsvDelimiter(string $path): string
    {
        $handle = @fopen($path, 'r');
        if (!$handle) {
            return ',';
        }

        $sampleLines = [];
        for ($i = 0; $i < 5 && !feof($handle); $i++) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }
            $sampleLines[] = $line;
        }
        fclose($handle);

        $delimiters = [',', ';', '|', "\t"];
        $scores = [];
        foreach ($delimiters as $delimiter) {
            $scores[$delimiter] = 0;
            foreach ($sampleLines as $line) {
                $scores[$delimiter] += substr_count($line, $delimiter);
            }
        }

        arsort($scores);
        return (string) array_key_first($scores);
    }

    private function normalizeCsvRow(array $row, string $delimiter, ?int $expectedColumns = null): array
    {
        if (count($row) === 1 && isset($row[0]) && is_string($row[0])) {
            $rawValue = trim($row[0]);
            if ($rawValue !== '' && str_contains($rawValue, $delimiter)) {
                $expandedRow = str_getcsv($rawValue, $delimiter, '"', '\\');
                if (count($expandedRow) > 1) {
                    $row = $expandedRow;
                }
            }
        }

        if (!empty($row) && isset($row[0]) && is_string($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
        }

        if ($expectedColumns !== null && count($row) < $expectedColumns) {
            $row = $this->padRow($row, $expectedColumns);
        }

        return $row;
    }

    private function getCsvPreviewLimits(): array
    {
        if ($this->isDailyLoanActive()) {
            return [
                'preview_limit' => 60,
                'unique_scan_limit' => 600,
                'max_unique_values_per_column' => 120,
            ];
        }

        return [
            'preview_limit' => 100,
            'unique_scan_limit' => 5000,
            'max_unique_values_per_column' => 300,
        ];
    }

    private function prepareCsvPreviewPayload(string $path): array
    {
        $delimiter = $this->detectCsvDelimiter($path);
        $handle = @fopen($path, 'r');
        if (!$handle) {
            throw new \RuntimeException('Gagal membuka file CSV.');
        }

        $lineNumber = 0;
        $headerIndex = null;
        $normalizedHeaders = [];
        $validIndexes = [];
        $headerCount = 0;
        $cleanPreview = [];
        $uniqueValues = [];
        $rowsProcessedForUniques = 0;
        $previewSettings = $this->getCsvPreviewLimits();
        $previewLimit = $previewSettings['preview_limit'];
        $uniqueLimit = $previewSettings['unique_scan_limit'];
        $maxUniqueValuesPerColumn = $previewSettings['max_unique_values_per_column'];
        $totalRows = 0;
        $forcedHeaders = $this->isDailyLoanActive() ? self::DAILY_LOAN_SOURCE_HEADERS : null;
        $tableName = $this->resolveActiveTableName();

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row = $this->normalizeCsvRow($row, $delimiter);
            $lineNumber++;
            $totalRows++;

            if ($headerIndex === null) {
                if ($this->detectHeaderIndex([$row], $tableName) === 0) {
                    $headerIndex = $lineNumber - 1;

                    if ($forcedHeaders !== null) {
                        foreach ($forcedHeaders as $colIdx => $header) {
                            $normalizedHeaders[$colIdx] = $header;
                        }
                    } else {
                        foreach ($row as $colIdx => $header) {
                            $normalizedHeaders[$colIdx] = !empty(trim((string) $header)) ? trim((string) $header) : 'COL_' . $colIdx;
                        }
                    }

                    foreach ($normalizedHeaders as $colIdx => $header) {
                        if (!str_starts_with($header, 'COL_')) {
                            $validIndexes[] = $colIdx;
                            $uniqueValues[$colIdx] = [];
                        }
                    }

                    $headerCount = count($normalizedHeaders);
                }
                continue;
            }

            if (empty(array_filter($row, fn ($value) => trim((string) $value) !== ''))) {
                continue;
            }

            $row = $this->normalizeCsvRow($row, $delimiter, $headerCount);

            if (count($cleanPreview) < $previewLimit) {
                $cleanRow = [];
                foreach ($validIndexes as $index) {
                    $headerName = $normalizedHeaders[$index];
                    $cleanRow[$headerName] = $this->normalizeExcelValue($headerName, $row[$index] ?? '');
                }
                $cleanPreview[] = $cleanRow;
            }

            if ($rowsProcessedForUniques < $uniqueLimit) {
                foreach ($validIndexes as $index) {
                    $headerName = $normalizedHeaders[$index];
                    $value = $this->normalizeExcelValue($headerName, $row[$index] ?? '');
                    if ($value === null) {
                        $value = '(Blank)';
                    }
                    if (
                        isset($uniqueValues[$index][$value]) ||
                        count($uniqueValues[$index]) < $maxUniqueValuesPerColumn
                    ) {
                        $uniqueValues[$index][$value] = true;
                    }
                }
                $rowsProcessedForUniques++;
            }

            if (count($cleanPreview) >= $previewLimit && $rowsProcessedForUniques >= $uniqueLimit) {
                break;
            }
        }

        fclose($handle);

        if ($headerIndex === null) {
            throw new \RuntimeException($this->headerNotFoundMessage($tableName));
        }

        $finalHeaders = [];
        foreach ($validIndexes as $index) {
            $finalHeaders[] = $normalizedHeaders[$index];
        }

        $formattedUniqueValues = [];
        $filterIndex = 0;
        foreach ($validIndexes as $index) {
            $keys = array_keys($uniqueValues[$index] ?? []);
            usort($keys, 'strnatcmp');
            $formattedUniqueValues[$filterIndex] = $keys;
            $filterIndex++;
        }

        return [
            'total_rows' => $totalRows,
            'header_index' => $headerIndex,
            'headers' => $finalHeaders,
            'preview' => $cleanPreview,
            'formattedUniqueValues' => $formattedUniqueValues,
        ];
    }

    private function normalizeHeaderForDatabase(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);
        $normalized = trim((string) $normalized, '_');
        $normalized = preg_replace('/\d+$/', '', (string) $normalized);

        return trim((string) $normalized, '_');
    }

    private function getHeaderDatabaseCandidates(string $header): array
    {
        $raw = strtolower(trim($header));
        $raw = preg_replace('/[^a-z0-9]+/', '_', $raw);
        $raw = trim((string) $raw, '_');

        $normalized = $this->normalizeHeaderForDatabase($header);
        $candidates = [];

        if ($raw !== '') {
            $candidates[] = $raw;
        }

        if ($normalized !== '' && $normalized !== $raw) {
            $candidates[] = $normalized;
        }

        $aliasMap = [
            'textbox20' => 'total_kewajiban',
            'textbox21' => 'os_idr',
            'periode' => 'periode',
            'kode_kanwil1' => 'kode_kanwil',
            'kanwil1' => 'kanwil',
            'kode_cabang1' => 'kode_cabang',
            'cabang1' => 'cabang',
            'branch1' => 'branch',
            'unit1' => 'unit',
            'nomor_rekening1' => 'nomor_rekening',
            'baki_debet1' => 'baki_debet',
            'segmen_dashboard' => 'segmen_dashboard',
            'produk_dashboard' => 'produk_dashboard',
        ];

        if (isset($aliasMap[$raw])) {
            $candidates[] = $aliasMap[$raw];
        }

        return array_values(array_unique($candidates));
    }

    private function reorderPreviewPayload(array $headers, array $formattedUniqueValues, array $preview, array $dbColumns): array
    {
        $preview = $this->rebuildPreviewRowsForHeaders($headers, $preview);

        if ($this->isDailyLoanActive()) {
            return [
                'headers' => $headers,
                'formattedUniqueValues' => $formattedUniqueValues,
                'preview' => $preview,
            ];
        }

        $matchedHeaders = [];
        $matchedUniqueValues = [];
        $usedHeaders = [];

        foreach ($dbColumns as $dbCol) {
            foreach ($headers as $index => $header) {
                if (isset($usedHeaders[$header])) {
                    continue;
                }

                if ($this->normalizeHeaderForDatabase($header) === strtolower($dbCol)) {
                    $matchedHeaders[] = $header;
                    $matchedUniqueValues[] = $formattedUniqueValues[$index] ?? [];
                    $usedHeaders[$header] = true;
                    break;
                }
            }
        }

        $remainingHeaders = [];
        $remainingUniqueValues = [];
        foreach ($headers as $index => $header) {
            if (isset($usedHeaders[$header])) {
                continue;
            }
            $remainingHeaders[] = $header;
            $remainingUniqueValues[] = $formattedUniqueValues[$index] ?? [];
        }

        $finalHeaders = array_merge($matchedHeaders, $remainingHeaders);
        $finalUniqueValues = array_merge($matchedUniqueValues, $remainingUniqueValues);

        if (empty($finalHeaders)) {
            $finalHeaders = $headers;
            $finalUniqueValues = $formattedUniqueValues;
        }

        foreach ($preview as &$row) {
            $newRow = [];
            foreach ($finalHeaders as $header) {
                $newRow[$header] = $row[$header] ?? null;
            }
            $row = $newRow;
        }
        unset($row);

        return [
            'headers' => $finalHeaders,
            'formattedUniqueValues' => $finalUniqueValues,
            'preview' => $this->rebuildPreviewRowsForHeaders($finalHeaders, $preview),
        ];
    }

    private function rebuildPreviewRowsForHeaders(array $headers, array $preview): array
    {
        $rebuilt = [];

        foreach ($preview as $row) {
            $rowData = is_array($row) ? $row : (array) $row;
            $rowValues = array_values($rowData);
            $rowLower = [];
            foreach ($rowData as $key => $value) {
                $rowLower[strtolower(trim((string) $key))] = $value;
            }

            $newRow = [];
            foreach ($headers as $index => $header) {
                $headerKey = trim((string) $header);
                $normalizedHeaderKey = $this->normalizeHeaderForDatabase($headerKey);

                $newRow[$headerKey] = $rowData[$headerKey]
                    ?? $rowData[$normalizedHeaderKey]
                    ?? $rowData[strtoupper($headerKey)]
                    ?? $rowData[strtolower($headerKey)]
                    ?? $rowLower[strtolower($headerKey)]
                    ?? $rowLower[$normalizedHeaderKey]
                    ?? ($rowValues[$index] ?? null);
            }

            $rebuilt[] = $newRow;
        }

        return $rebuilt;
    }

    private function mapExcelRowForInsert(array $row, array $normalizedHeaders, array $context, string $timestamp): ?array
    {
        $row = $this->padRow($row, $context['header_count']);
        $mappedExcelData = [];

        foreach ($context['valid_indexes'] as $filterIdx => $originalIndex) {
            $headerName = $normalizedHeaders[$originalIndex];
            $value = $this->normalizeExcelValue($headerName, $row[$originalIndex] ?? '');

            if (!empty($context['filter_lookups']) && isset($context['filter_lookups'][$filterIdx])) {
                $filterValue = ($value === null) ? '(Blank)' : (string) $value;
                if (!isset($context['filter_lookups'][$filterIdx][$filterValue])) {
                    return null;
                }
            }

            foreach ($this->getHeaderDatabaseCandidates($headerName) as $candidateColumn) {
                $mappedExcelData[$candidateColumn] = $value;
            }
        }

        $finalRow = [
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        if (!empty($context['unique_id_col'])) {
            $finalRow[$context['unique_id_col']] = uniqid('', true) . $context['suffix'];
        }

        foreach ($mappedExcelData as $dbCol => $value) {
            if (isset($context['skip_columns_lookup'][$dbCol])) {
                continue;
            }
            if (!isset($context['table_columns_lookup'][$dbCol])) {
                continue;
            }
            $finalRow[$dbCol] = $value;
        }

        $minimumColumns = !empty($context['unique_id_col']) ? 3 : 2;

        return count($finalRow) > $minimumColumns ? $finalRow : null;
    }

    private function flushInsertBuffer(array &$rows, string $tableName, int &$totalInserted, int &$totalFailed, ?callable $afterBatch = null): void
    {
        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, 100) as $batch) {
            try {
                DB::table($tableName)->insert($batch);
                $totalInserted += count($batch);
            } catch (\Exception $e) {
                foreach ($batch as $single) {
                    try {
                        DB::table($tableName)->insert($single);
                        $totalInserted++;
                    } catch (\Exception $e2) {
                        $totalFailed++;
                    }
                }
            }

            if ($afterBatch) {
                $afterBatch();
            }
        }

        $rows = [];
    }

    private function normalizeDecimalValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^0-9,\.\-]/', '', $value);

        if ($value === '' || $value === '-' || $value === null) {
            return null;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($hasComma) {
            $parts = explode(',', $value);
            $lastPart = end($parts);

            if (count($parts) > 2 || strlen((string) $lastPart) === 3) {
                $value = str_replace(',', '', $value);
            } else {
                $value = str_replace(',', '.', $value);
            }
        } elseif ($hasDot) {
            $parts = explode('.', $value);
            $lastPart = end($parts);

            if (count($parts) > 2 || strlen((string) $lastPart) === 3) {
                $value = str_replace('.', '', $value);
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function normalizeIntegerValue($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/-?\d+/', $value, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    private function normalizeExcelValue(string $headerName, $value)
    {
        $header = strtoupper(trim($headerName));
        $normalizedHeader = preg_replace('/[^A-Z0-9]+/', '_', $header);
        $value = ($value === null) ? '' : trim((string) $value);

        if ($value === '') return null;

        $dateColumns = [
            'PERIODE',
            'POSISI',
            'TGL_REALISASI',
            'TGL_JATUH_TEMPO',
            'TANGGAL',
            'TANGGAL_MENUNGGAK',
            'TGL_BAYAR_TERAKHIR',
            'TGL_TERMINATE',
            'LAST_DATE_MAINTENANCE_BILLING',
            'NEXT_PMT_DATE',
            'NEXT_PMT_INT_DATE',
            'TGL_AKAD_RESTRUK',
        ];
        if (in_array($normalizedHeader, $dateColumns, true)) {
            try {
                if (is_numeric($value)) {
                    return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('Y-m-d');
                }
                if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
                    return Carbon::createFromFormat('d-m-Y', $value)->format('Y-m-d');
                }
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                    return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
                }
                return Carbon::parse(str_replace('/', '-', $value))->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        $decimalColumns = [
            'RATE',
            'PLAFON',
            'BAKI_DEBET',
            'BAKI_DEBET1',
            'CKPN',
            'NILAI_TERCATAT1',
            'KOLEKTABILITAS_LANCAR',
            'KOLEKTABILITAS_DPK',
            'KOLEKTABILITAS_KURANGLANCAR',
            'KOLEKTABILITAS_DIRAGUKAN',
            'KOLEKTABILITAS_MACET',
            'TUNGGAKAN_POKOK',
            'TUNGGAKAN_BUNGA',
            'TUNGGAKAN_PENALTI',
            'ADVANCE_PAYMENT',
            'BAP',
            'PAYMENT_AMOUNT',
            'FINAL_PAYMENT_AMOUNT',
            'NPB_POKOK_LA',
            'NPB_POKOK_LF',
            'NPB_BUNGA_LA',
            'NPB_BUNGA_LF',
            'JML_ANGSURAN1',
            'JUMLAH_BAYAR',
            'DEFFERED_BUNGA',
            'SAI_TUNGGAKAN',
            'SAI_DEFFERED',
            'SAI1',
            'PMTAMT',
            'PMTAMT_BASE',
            'OS_SEBELUM_KLAIM',
            'OS_PENUH_BERJALAN',
            'BILPRN',
            'BILINT',
            'BILLC',
        ];
        if (in_array($normalizedHeader, $decimalColumns, true)) {
            return $this->normalizeDecimalValue($value);
        }

        $integerColumns = [
            'JANGKA_WAKTU1',
            'UMUR_TUNGGAKAN',
            'FREQ_PAYMENT',
            'FREQ_INT_PAYMENT',
            'JUMLAH_PN1',
            'JUMLAH_PN_ALL1',
            'RESTRUK_KE1',
        ];
        if (in_array($normalizedHeader, $integerColumns, true)) {
            return $this->normalizeIntegerValue($value);
        }

        if (is_numeric($value)) {
            $formatted = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
            return $formatted === '' ? '0' : $formatted;
        }

        return $value;
    }

    private function padRow(array $row, int $targetCount): array
    {
        $normalized = [];
        for ($i = 0; $i < $targetCount; $i++) {
            $normalized[$i] = $row[$i] ?? null;
        }
        return $normalized;
    }

    public function uploadExcel(Request $request, array $allowedExtensions = ['xlsx', 'xls'])
    {
        $request->validate(['file' => 'required|file|mimes:' . implode(',', $allowedExtensions)]);
        $file = $request->file('file');

        if (!$file || !$file->isValid()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Upload gagal, file tidak valid.'], 422);
            }
            return back()->with('error', 'Upload gagal, file tidak valid.');
        }

        if (!file_exists(Storage::path('excel_imports'))) {
            Storage::makeDirectory('excel_imports');
        }

        $path = $file->store('excel_imports');
        $cacheKey = 'excel_preview_' . md5($path . '|' . (auth()->id() ?? 'guest') . '|' . microtime(true));

        session([
            'excel_path'        => $path,
            'active_id_report'  => $request->id_report,
            'excel_preview_key' => $cacheKey,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status'    => 'success',
                'cache_key' => $cacheKey,
            ]);
        }

        return redirect()->route('import.excel.preview');
    }

    public function preparePreviewStream(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $sessionPath = session('excel_path');
        $cacheKey    = session('excel_preview_key');
        $activeIdReport = (int) session('active_id_report');
        request()->session()->save();

        return response()->stream(function () use ($sessionPath, $cacheKey, $activeIdReport) {
            $send = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
            };

            try {
                if (!$sessionPath) {
                    $send('error_msg', ['message' => 'Sesi upload tidak ditemukan. Silakan upload ulang.']);
                    return;
                }
                $path = Storage::path(urldecode($sessionPath));
                if (!file_exists($path)) {
                    $send('error_msg', ['message' => 'File tidak ditemukan di server. Silakan upload ulang.']);
                    return;
                }

                $fileLabel = $this->isCsvFile($path) ? 'CSV' : 'Excel';
                $send('progress', ['percent' => 5, 'message' => 'Membaca file ' . $fileLabel . ' (single-pass)...', 'step' => 1]);

                if ($this->isCsvFile($path)) {
                    $send('progress', ['percent' => 15, 'message' => 'Menganalisis struktur CSV...', 'step' => 2]);
                    $csvPayload = $this->prepareCsvPreviewPayload($path);
                    $send('progress', ['percent' => 70, 'message' => 'Mengurutkan dan memformat hasil...', 'step' => 4]);

                    $tableName = $this->resolveActiveTableName();

                    $reorderedPayload = $this->reorderPreviewPayload(
                        $csvPayload['headers'],
                        $csvPayload['formattedUniqueValues'],
                        $csvPayload['preview'],
                        Schema::getColumnListing($tableName)
                    );

                    $finalHeaders = $reorderedPayload['headers'];
                    $formattedUniqueValues = $reorderedPayload['formattedUniqueValues'];
                    $cleanPreview = $reorderedPayload['preview'];

                    $send('progress', ['percent' => 95, 'message' => 'Finalisasi preview...', 'step' => 5]);

                    $payload = [
                        'headers' => $finalHeaders,
                        'preview' => $cleanPreview,
                        'formattedUniqueValues' => $formattedUniqueValues,
                        'path' => urldecode($sessionPath),
                    ];

                    $useCacheKey = $cacheKey ?: ('excel_preview_' . md5(urldecode($sessionPath) . '|' . microtime(true)));
                    Cache::put($useCacheKey, $payload, now()->addMinutes(10));

                    $send('ready', [
                        'redirect' => $activeIdReport === self::DAILY_LOAN_REPORT_ID
                            ? route('import.dailyloan.preview', ['ck' => $useCacheKey])
                            : route('import.excel.preview', ['ck' => $useCacheKey]),
                    ]);
                    return;
                }

                $reader = IOFactory::createReaderForFile($path);
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);

                $tableName = $this->resolveActiveTableName();
                $worksheetInfo = $reader->listWorksheetInfo($path);
                $totalRows = $worksheetInfo[0]['totalRows'] ?? 0;
                $send('progress', ['percent' => 10, 'message' => 'File terdeteksi: ' . $totalRows . ' baris.', 'step' => 2]);
                
                $chunkFilter = new ChunkReadFilter();
                $chunkSize = 2000;
                $currentChunk = 1;

                $headerIndex = null;
                $rawHeaders = [];
                $normalizedHeaders = [];
                $validIndexes = [];
                $headerCount = 0;

                $cleanPreview = [];
                $uniqueValues = [];
                $rowsProcessedForUniques = 0;
                $uniqueLimit = 5000;
                $previewLimit = 100;
                
                // Single-pass loop
                while (true) {
                    $startRow = (($currentChunk - 1) * $chunkSize) + 1;
                    if ($startRow > $totalRows) break;

                    $chunkFilter->setRows($startRow, $chunkSize);
                    $reader->setReadFilter($chunkFilter);
                    $spreadsheet = $reader->load($path);
                    $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);

                    if (empty($sheet)) break;

                    // Step 1: Find Header (only once, in first chunks)
                    if ($headerIndex === null) {
                        $detectedHeaderIndex = $this->detectHeaderIndex($sheet, $tableName);
                        if ($detectedHeaderIndex !== null) {
                            $headerIndex = $detectedHeaderIndex;
                            $rawHeaders = $sheet[$headerIndex];

                            foreach ($rawHeaders as $colIdx => $h) {
                                $normalizedHeaders[$colIdx] = !empty(trim((string)$h)) ? trim((string)$h) : 'COL_' . $colIdx;
                            }
                            foreach ($normalizedHeaders as $colIdx => $h) {
                                if (!str_starts_with($h, 'COL_')) $validIndexes[] = $colIdx;
                            }
                            $finalHeaders = [];
                            foreach ($validIndexes as $colIdx) $finalHeaders[] = $normalizedHeaders[$colIdx];

                            $headerCount = max(array_keys($normalizedHeaders)) + 1;
                            foreach ($validIndexes as $colIdx) $uniqueValues[$colIdx] = [];

                            $send('progress', ['percent' => 35, 'message' => 'Header ditemukan di baris ' . ($headerIndex + 1) . '. Memproses data...', 'step' => 3]);
                        }
                        if ($headerIndex === null && $startRow + $chunkSize > 200) {
                            $send('error_msg', ['message' => $this->headerNotFoundMessage($tableName)]);
                            return;
                        }
                    }

                    // Step 2 & 3: Collect Preview and Unique Values
                    if ($headerIndex !== null) {
                        foreach ($sheet as $rowIndex => $row) {
                            if ($rowIndex <= $headerIndex) continue;
                            if (empty(array_filter($row, fn($val) => trim((string) $val) !== ''))) continue;

                            $row = $this->padRow($row, $headerCount);
                            
                            // Collect for preview
                            if (count($cleanPreview) < $previewLimit) {
                                $cleanRow = [];
                                foreach ($validIndexes as $i) {
                                    $cleanRow[$normalizedHeaders[$i]] = $this->normalizeExcelValue($normalizedHeaders[$i], $row[$i] ?? '');
                                }
                                $cleanPreview[] = $cleanRow;
                            }

                            // Collect for unique filters
                            if ($rowsProcessedForUniques < $uniqueLimit) {
                                foreach ($validIndexes as $i) {
                                    $val = $this->normalizeExcelValue($normalizedHeaders[$i], $row[$i] ?? '');
                                    if ($val === null) $val = '(Blank)';
                                    $uniqueValues[$i][$val] = true;
                                }
                                $rowsProcessedForUniques++;
                            }
                        }
                    }

                    if ($headerIndex !== null && (count($cleanPreview) >= $previewLimit && $rowsProcessedForUniques >= $uniqueLimit)) {
                        break; 
                    }

                    $currentChunk++;
                }
                
                if ($headerIndex === null) {
                    $send('error_msg', ['message' => $this->headerNotFoundMessage($tableName)]);
                    return;
                }

                $send('progress', ['percent' => 70, 'message' => 'Mengurutkan dan memformat hasil...', 'step' => 4]);

                $formattedUniqueValues = [];
                $filterIndex = 0;
                foreach ($validIndexes as $i) {
                    $keys = isset($uniqueValues[$i]) ? array_keys($uniqueValues[$i]) : [];
                    usort($keys, 'strnatcmp');
                    $formattedUniqueValues[$filterIndex] = $keys;
                    $filterIndex++;
                }

                $dbColumns = Schema::getColumnListing($tableName);

                $reorderedPayload = $this->reorderPreviewPayload(
                    $finalHeaders,
                    $formattedUniqueValues,
                    $cleanPreview,
                    $dbColumns
                );
                $finalHeaders = $reorderedPayload['headers'];
                $formattedUniqueValues = $reorderedPayload['formattedUniqueValues'];
                $cleanPreview = $reorderedPayload['preview'];

                $send('progress', ['percent' => 95, 'message' => 'Finalisasi preview...', 'step' => 5]);

                $payload = [
                    'headers' => $finalHeaders,
                    'preview' => $cleanPreview,
                    'formattedUniqueValues' => $formattedUniqueValues,
                    'path' => urldecode($sessionPath),
                ];

                $useCacheKey = $cacheKey ?: ('excel_preview_' . md5(urldecode($sessionPath) . '|' . microtime(true)));
                Cache::put($useCacheKey, $payload, now()->addMinutes(10));

                $send('ready', [
                    'redirect' => $activeIdReport === self::DAILY_LOAN_REPORT_ID
                        ? route('import.dailyloan.preview', ['ck' => $useCacheKey])
                        : route('import.excel.preview', ['ck' => $useCacheKey]),
                ]);

            } catch (\Throwable $e) {
                Log::error('PREPARE PREVIEW SSE ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
                $send('error_msg', ['message' => 'Gagal menyiapkan preview: ' . $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function previewExcel(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $activeIdReport = (int) session('active_id_report');
        $initRoute = $activeIdReport === self::DAILY_LOAN_REPORT_ID
            ? route('import.dailyloan.init')
            : route('import.excel.init');
        $streamRoute = $activeIdReport === self::DAILY_LOAN_REPORT_ID
            ? route('import.dailyloan.stream')
            : route('import.excel.stream');

        $ck = $request->query('ck');
        if ($ck) {
            $cached = Cache::get($ck);
            if ($cached && is_array($cached)) {
                Cache::forget($ck);
                $cached['initRoute'] = $initRoute;
                $cached['streamRoute'] = $streamRoute;
                return view('import.preview_excel', $cached);
            }
        }

        $sessionPath = session('excel_path', $request->path);
        if (!$sessionPath) return redirect()->route('import.index')->with('sweet_warning', ['title' => 'Sesi Berakhir', 'text' => 'Silakan upload ulang.']);

        $relativePath = urldecode($sessionPath);
        $path = Storage::path($relativePath);
        if (!file_exists($path)) return redirect()->route('import.index')->with('sweet_warning', ['title' => 'File Tidak Ditemukan', 'text' => 'File mungkin sudah terhapus.']);

        if ($this->isCsvFile($path)) {
            $csvPayload = $this->prepareCsvPreviewPayload($path);

            $tableName = $this->resolveActiveTableName();

            $reorderedPayload = $this->reorderPreviewPayload(
                $csvPayload['headers'],
                $csvPayload['formattedUniqueValues'],
                $csvPayload['preview'],
                Schema::getColumnListing($tableName)
            );

            return view('import.preview_excel', [
                'headers' => $reorderedPayload['headers'],
                'preview' => $reorderedPayload['preview'],
                'formattedUniqueValues' => $reorderedPayload['formattedUniqueValues'],
                'path' => $relativePath,
                'initRoute' => $initRoute,
                'streamRoute' => $streamRoute,
            ]);
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $chunkFilter = new ChunkReadFilter();
        $chunkFilter->setRows(1, 200);
        $reader->setReadFilter($chunkFilter);

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $headerIndex = null;
        $tableName = $this->resolveActiveTableName();
        $headerIndex = $this->detectHeaderIndex($sheet, $tableName);
        if ($headerIndex === null) return back()->with('error', $this->headerNotFoundMessage($tableName));

        $rawHeaders = $sheet[$headerIndex];
        $normalizedHeaders = [];
        foreach ($rawHeaders as $i => $h) {
            $normalizedHeaders[$i] = !empty(trim((string)$h)) ? trim((string)$h) : 'COL_' . $i;
        }

        $validIndexes = [];
        foreach ($normalizedHeaders as $i => $h) {
            if (!str_starts_with($h, 'COL_')) $validIndexes[] = $i;
        }

        $finalHeaders = [];
        foreach ($validIndexes as $i) $finalHeaders[] = $normalizedHeaders[$i];

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $headerCount = max(array_keys($normalizedHeaders)) + 1;

        $chunkFilter->setHeaderRow($headerIndex + 1);
        $chunkFilter->setRows($headerIndex + 2, 100);
        $reader->setReadFilter($chunkFilter);
        $spreadsheetPreview = $reader->load($path);
        $wsPreview   = $spreadsheetPreview->getActiveSheet();
        $highestCol  = $wsPreview->getHighestColumn();
        $lastDataRow = min($wsPreview->getHighestRow(), $headerIndex + 101);

        $previewRange = 'A' . ($headerIndex + 1) . ':' . $highestCol . $lastDataRow;
        $rangePreview = $wsPreview->rangeToArray($previewRange, null, true, true, false);

        $cleanPreview = [];
        foreach ($rangePreview as $relIdx => $row) {
            if ($relIdx === 0) continue;
            if (empty(array_filter($row, fn($val) => trim((string) $val) !== ''))) continue;

            $row      = $this->padRow($row, $headerCount);
            $cleanRow = [];
            foreach ($validIndexes as $i) {
                $cleanRow[$normalizedHeaders[$i]] = $this->normalizeExcelValue($normalizedHeaders[$i], $row[$i] ?? '');
            }
            $cleanPreview[] = $cleanRow;
        }
        $spreadsheetPreview->disconnectWorksheets();
        unset($spreadsheetPreview, $wsPreview, $rangePreview);

        $uniqueValues = [];
        foreach ($validIndexes as $i) $uniqueValues[$i] = [];

        $chunkFilter->setHeaderRow($headerIndex + 1);
        $chunkFilter->setRows($headerIndex + 2, 5000);
        $reader->setReadFilter($chunkFilter);
        $spreadsheetFull = $reader->load($path);
        $wsFull          = $spreadsheetFull->getActiveSheet();
        $highestColFull  = $wsFull->getHighestColumn();
        $lastFullRow     = min($wsFull->getHighestRow(), $headerIndex + 5001);

        $fullRange  = 'A' . ($headerIndex + 2) . ':' . $highestColFull . $lastFullRow;
        $rangeFull  = $wsFull->rangeToArray($fullRange, null, true, true, false);

        foreach ($rangeFull as $row) {
            if (empty(array_filter($row, fn($val) => trim((string) $val) !== ''))) continue;
            $row = $this->padRow($row, $headerCount);

            foreach ($validIndexes as $i) {
                $val = $this->normalizeExcelValue($normalizedHeaders[$i], $row[$i] ?? '');
                if ($val === null) $val = '(Blank)';
                $uniqueValues[$i][$val] = true;
            }
        }
        $spreadsheetFull->disconnectWorksheets();
        unset($spreadsheetFull, $wsFull, $rangeFull);

        $formattedUniqueValues = [];
        $filterIndex = 0;
        foreach ($validIndexes as $i) {
            $keys = array_keys($uniqueValues[$i]);
            usort($keys, function ($a, $b) { return strnatcmp($a, $b); });
            $formattedUniqueValues[$filterIndex] = $keys;
            $filterIndex++;
        }

        // Reorder headers, preview, and unique values to match DB column order
        $tableName = 'daily_loan_dinamis'; // Default
        $idReport = session('active_id_report');
        if ($idReport) {
            $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
            if ($reportData && !empty($reportData->table_name)) {
                $tableName = $reportData->table_name;
            }
        }
        $dbColumns = Schema::getColumnListing($tableName);

        $headerMap = []; // excelHeader => dbCol (lowercase)
        foreach ($finalHeaders as $h) {
            $normalized = strtolower(str_replace(' ', '_', $h));
            if (in_array($normalized, $dbColumns)) {
                $headerMap[$h] = $normalized;
            }
        }

        $orderedHeaders = [];
        $orderedUniqueValues = [];
        foreach ($dbColumns as $dbCol) {
            foreach ($headerMap as $excelH => $mapCol) {
                if ($mapCol === $dbCol) {
                    $orderedHeaders[] = $excelH;
                    $originalIndex = array_search($excelH, $finalHeaders);
                    $orderedUniqueValues[] = $formattedUniqueValues[$originalIndex];
                    unset($headerMap[$excelH]);
                    break;
                }
            }
        }

        // Append any remaining Excel headers not matching DB columns
        foreach ($headerMap as $excelH => $mapCol) {
            $orderedHeaders[] = $excelH;
            $originalIndex = array_search($excelH, $finalHeaders);
            $orderedUniqueValues[] = $formattedUniqueValues[$originalIndex];
        }

        $finalHeaders = $orderedHeaders;
        $formattedUniqueValues = $orderedUniqueValues;

        // Reorder cleanPreview rows
        foreach ($cleanPreview as &$row) {
            $newRow = [];
            foreach ($finalHeaders as $h) {
                $newRow[$h] = $row[$h] ?? null;
            }
            $row = $newRow;
        }
        unset($row);

        return view('import.preview_excel', [
            'headers' => $finalHeaders,
            'preview' => $cleanPreview,
            'formattedUniqueValues' => $formattedUniqueValues,
            'path' => $relativePath,
            'initRoute' => $initRoute,
            'streamRoute' => $streamRoute,
        ]);
    }

    /**
     * Deteksi header Excel menggunakan Python (openpyxl read-only, cepat).
     * Return array dengan header_index, total_rows, dan header_values (nama kolom),
     * atau null jika Python tidak tersedia / gagal.
     */
    private function detectHeaderViaPython(string $path): ?array
    {
        if ($this->isCsvFile($path)) {
            return null;
        }

        $pythonExe  = $this->findPython();
        $scriptPath = base_path('scripts/excel_gpu_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $configData = ['file_path' => $path];
        $configFile = storage_path('app/excel_init_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode($configData, JSON_UNESCAPED_UNICODE));

        // Redirect stderr ke null device (NUL di Windows, /dev/null di Unix)
        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $cmd    = escapeshellarg($pythonExe)
                . ' ' . escapeshellarg($scriptPath)
                . ' --config ' . escapeshellarg($configFile)
                . ' --mode init'
                . ' 2>' . $nullDevice;
        $output = @shell_exec($cmd);
        @unlink($configFile);

        if (!$output) return null;

        $result = json_decode(trim($output), true);
        if (!$result || ($result['status'] ?? '') !== 'ok') {
            Log::warning('Python init failed: ' . ($result['message'] ?? $output));
            return null;
        }

        return [
            'header_index'  => (int)   $result['header_index'],
            'total_rows'    => (int)   $result['total_rows'],
            'header_values' => (array) ($result['header_values'] ?? []),  // nama kolom langsung dari Python
        ];
    }

    public function initExcelImport(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $sessionPath = session('excel_path', $request->path);
        if (!$sessionPath) return response()->json(['status' => 'error', 'text' => 'Sesi berakhir.']);

        $relativePath = urldecode($sessionPath);
        $path = Storage::path($relativePath);
        if (!file_exists($path)) return response()->json(['status' => 'error', 'text' => 'File tidak ditemukan.']);

        $idReport = session('active_id_report');
        $tableName = $this->resolveActiveTableName();

        // Pastikan schema minimum tersedia agar import tidak terlihat "sukses"
        // padahal kolom penting untuk report belum ada di database.
        if ($tableName === 'daily_loan_dinamis') {
            $availableColumns = array_map('strtolower', Schema::getColumnListing($tableName));
            $hasAnyBakiDebetColumn = in_array('baki_debet', $availableColumns, true)
                || in_array('baki_debet1', $availableColumns, true);

            if (!$hasAnyBakiDebetColumn) {
                return response()->json([
                    'status' => 'error',
                    'text' => 'Kolom Daily Loan untuk baki debet belum tersedia di tabel `daily_loan_dinamis`.',
                ], 422);
            }
        }

        // ── Coba Python dulu (openpyxl read-only, jauh lebih cepat) ──────────
        $headerIndex = null;
        $totalRows   = 0;
        $sheet       = null;

        if ($this->isCsvFile($path)) {
            $delimiter = $this->detectCsvDelimiter($path);
            $handle = @fopen($path, 'r');

            if (!$handle) {
                return response()->json(['status' => 'error', 'text' => 'Gagal membuka file CSV.']);
            }

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $row = $this->normalizeCsvRow($row, $delimiter);

                if ($headerIndex === null) {
                    $rowUpper = array_map(fn($v) => strtoupper(trim((string) $v)), $row);
                    if ($this->detectHeaderIndex([$row], $tableName) === 0) {
                        $headerIndex = $totalRows;
                        $sheet = [];
                        $sheet[$headerIndex] = $this->isDailyLoanActive()
                            ? self::DAILY_LOAN_SOURCE_HEADERS
                            : $row;
                    }
                }

                $totalRows++;
            }

            fclose($handle);
        } else {
            $pythonResult = $this->detectHeaderViaPython($path);

            if ($pythonResult !== null) {
                // ── Python berhasil: TIDAK perlu buka file dengan PhpSpreadsheet sama sekali ──
                $headerIndex  = $pythonResult['header_index'];
                $totalRows    = $pythonResult['total_rows'];
                $headerValues = $pythonResult['header_values'];  // array nama kolom dari Python

                // Bangun $sheet[$headerIndex] dari header_values yang dikembalikan Python
                // agar kompatibel dengan kode di bawah yang membaca $sheet[$headerIndex]
                $sheet = [];
                $sheet[$headerIndex] = $headerValues;

            } else {
                // ── Fallback: PhpSpreadsheet (untuk file kecil / Python tidak tersedia) ──
                Log::info('initExcelImport: Python tidak tersedia, fallback ke PhpSpreadsheet.');

                $reader = IOFactory::createReaderForFile($path);
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);

                $chunkFilter = new ChunkReadFilter();
                $chunkFilter->setRows(1, 200);
                $reader->setReadFilter($chunkFilter);

                $spreadsheet = $reader->load($path);
                $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

                $headerIndex = $this->detectHeaderIndex($sheet, $tableName);

                if ($headerIndex === null) {
                    return response()->json(['status' => 'error', 'text' => $this->headerNotFoundMessage($tableName)]);
                }

                $worksheetInfo = $reader->listWorksheetInfo($path);
                $totalRows     = $worksheetInfo[0]['totalRows'];
            }
        }

        if ($headerIndex === null) {
            return response()->json(['status' => 'error', 'text' => $this->headerNotFoundMessage($tableName)]);
        }

        $dataRowsCount = max(0, $totalRows - ($headerIndex + 1));

        $jobId = DB::table('import_jobs')->insertGetId([
            'id_report'     => $idReport,
            'file_name'     => basename($path),
            'folder_path'   => dirname($path),
            'status'        => 'processing',
            'total_files'   => $dataRowsCount,
            'total_success' => 0,
            'total_failed'  => 0,
            'created_by'    => auth()->id() ?? 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Ambil nama kolom dari baris header
        $rawHeaders = $sheet[$headerIndex] ?? [];
        $normalizedHeadersForSession = [];
        foreach ($rawHeaders as $i => $h) {
            $normalizedHeadersForSession[$i] = !empty(trim((string)$h)) ? trim((string)$h) : 'COL_' . $i;
        }

        $activeFilters = json_decode($request->active_filters_json ?? '{}', true) ?: [];
        session([
            'excel_headers'        => $normalizedHeadersForSession,
            'excel_import_params'  => [
                'header_index'   => $headerIndex,
                'table_name'     => $tableName,
                'file_path'      => $relativePath,
                'active_filters' => $activeFilters,
                'job_id'         => $jobId,
            ],
        ]);

        return response()->json([
            'status'       => 'success',
            'job_id'       => $jobId,
            'total_rows'   => $totalRows,
            'header_index' => $headerIndex,
            'table_name'   => $tableName,
            'file_path'    => $relativePath,
        ]);
    }

    /**
     * Cari executable Python yang tersedia di sistem.
     * Return null jika Python tidak ditemukan.
     */
    private function findPython(): ?string
    {
        $candidates = ['python', 'python3', 'py'];
        foreach ($candidates as $cmd) {
            $output = @shell_exec(escapeshellcmd($cmd) . ' --version 2>&1');
            if ($output && str_contains($output, 'Python 3')) {
                return $cmd;
            }
        }
        return null;
    }

    /**
     * Coba jalankan Python GPU processor.
     * Return true jika Python berhasil menangani proses, false jika tidak tersedia.
     */
    private function tryPythonGPU(
        callable $send,
        string   $path,
        int      $headerIndex,
        string   $tableName,
        array    $activeFilters,
        array    $normalizedHeaders,
        int      $jobId
    ): bool {
        if ($this->isCsvFile($path)) {
            return false;
        }

        $pythonExe  = $this->findPython();
        $scriptPath = base_path('scripts/excel_gpu_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return false;
        }

        // ── Siapkan info tabel untuk Python (Python tidak perlu koneksi DB) ──
        $importContext   = $this->buildImportContext($tableName, $normalizedHeaders, $activeFilters);

        // Config untuk Python: tidak ada 'db' — Python hanya baca Excel & output JSON
        $configData = [
            'file_path'          => $path,
            'header_index'       => $headerIndex,
            'table_name'         => $tableName,
            'active_filters'     => $activeFilters,
            'normalized_headers' => $normalizedHeaders,
            'table_columns'      => array_keys($importContext['table_columns_lookup']),  // PHP kirim daftar kolom valid ke Python
        ];

        $configFile = storage_path('app/excel_gpu_config_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode($configData, JSON_UNESCAPED_UNICODE));

        $cmd = escapeshellarg($pythonExe)
             . ' ' . escapeshellarg($scriptPath)
             . ' --config ' . escapeshellarg($configFile);

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout — Python output batch JSON di sini
            2 => ['pipe', 'w'],  // stderr — ditangkap, diabaikan
        ];

        // ── Nonaktifkan semua GPU device sebelum Python start ─────────────
        $gpuEnv = [
            'CUDA_VISIBLE_DEVICES'   => '',
            'ROCR_VISIBLE_DEVICES'   => '',
            'MLU_VISIBLE_DEVICES'    => '',
            'ASCEND_VISIBLE_DEVICES' => '',
            'HIP_VISIBLE_DEVICES'    => '',
        ];
        $procEnv = array_merge((getenv() ?: $_ENV ?: []), $gpuEnv);

        $process = proc_open($cmd, $descriptors, $pipes, null, $procEnv);
        if (!is_resource($process)) {
            @unlink($configFile);
            return false;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer               = '';
        $lastKeepAlive        = time();
        $keepAliveEvery       = 15;
        $pythonProducedOutput = false;
        $doneSent             = false;   // Lacak apakah Python mengirim event 'done'
        $pythonError          = null;    // Pesan error dari Python (jika ada)
        $totalInserted        = 0;
        $totalFailed          = 0;

        // ── Helper: insert satu batch rows ke DB ──────────────────────────
        // Gunakan sub-batch 100 baris agar tidak melebihi max_allowed_packet MySQL
        $insertBatch = function (array $rows) use (
            $tableName, $importContext, &$totalInserted, &$totalFailed
        ) {
            if (empty($rows)) {
                return;
            }

            $cleanRows = [];
            $timestamp = now()->toDateTimeString();

            foreach ($rows as $row) {
                $clean = [];
                foreach ($row as $col => $val) {
                    $colLower = strtolower($col);
                    if (!empty($importContext['unique_id_col']) && $colLower === strtolower($importContext['unique_id_col'])) {
                        $clean[$importContext['unique_id_col']] = $val;
                        continue;
                    }
                    if (!isset($importContext['table_columns_lookup'][$colLower])) {
                        continue;
                    }
                    $clean[$colLower] = $val;
                }

                if (!empty($importContext['unique_id_col']) && !isset($clean[$importContext['unique_id_col']])) {
                    $clean[$importContext['unique_id_col']] = uniqid('', true) . $importContext['suffix'];
                }
                if (!isset($clean['created_at'])) {
                    $clean['created_at'] = $timestamp;
                }
                if (!isset($clean['updated_at'])) {
                    $clean['updated_at'] = $timestamp;
                }
                $minimumColumns = !empty($importContext['unique_id_col']) ? 3 : 2;
                if (count($clean) > $minimumColumns) {
                    $cleanRows[] = $clean;
                }
            }

            $this->flushInsertBuffer($cleanRows, $tableName, $totalInserted, $totalFailed);
        };

        // ── Helper: proses satu baris JSON dari Python ────────────────────
        $processLine = function (string $line) use (
            $send, $insertBatch, $tableName, $jobId,
            &$totalInserted, &$totalFailed, &$lastKeepAlive,
            &$doneSent, &$pythonError
        ) {
            $line = trim($line);
            if ($line === '') return;

            $data = json_decode($line, true);
            if (!$data) return;

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'batch') {
                // ── PHP insert batch ke DB ─────────────────────────────────
                $insertBatch($data['rows'] ?? []);
                // Keepalive setelah setiap batch insert
                echo ": keepalive\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
                $lastKeepAlive = time();

            } elseif ($type === 'done') {
                $doneSent = true; // Tandai bahwa Python selesai dengan sukses
                // ── Python selesai baca file — PHP finalisasi job & kirim complete ──
                $finalStatus = $totalFailed === 0
                    ? 'completed'
                    : ($totalInserted > 0 ? 'failed_partial' : 'failed');

                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'total_success' => $totalInserted,
                        'total_failed'  => $totalFailed,
                        'status'        => $finalStatus,
                        'updated_at'    => now(),
                    ]);
                }

                $send('complete', [
                    'total_success' => $totalInserted,
                    'total_failed'  => $totalFailed,
                    'total_rows'    => $data['total_rows'] ?? 0,
                ]);
                $lastKeepAlive = time();

            } elseif ($type === 'progress') {
                $send('progress', $data);
                $lastKeepAlive = time();

            } elseif ($type === 'error') {
                // Simpan pesan error Python — jangan langsung kirim ke browser
                // PHP akan memutuskan: fallback ke chunked reading (jika belum ada insert)
                // atau kirim error ke browser (jika sudah ada data yang ter-insert)
                $pythonError   = $data['message'] ?? 'Python error tidak diketahui';
                $lastKeepAlive = time();
            }
        };

        // ── Loop baca stdout Python ────────────────────────────────────────
        while (true) {
            $status = proc_get_status($process);

            // Buffer besar (64KB) karena batch JSON bisa besar
            $chunk = fread($pipes[1], 65536);
            if ($chunk !== false && $chunk !== '') {
                $pythonProducedOutput = true;
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line   = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $processLine($line);
                }
            }

            // SSE Keepalive saat Python diam (membaca file, dll.)
            if ((time() - $lastKeepAlive) >= $keepAliveEvery) {
                echo ": keepalive\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
                $lastKeepAlive = time();
            }

            if (!$status['running']) break;
            usleep(50000); // 50ms polling
        }

        // ── Flush sisa buffer setelah Python selesai ──────────────────────
        $remaining = stream_get_contents($pipes[1]);
        if ($remaining) {
            $pythonProducedOutput = true;
            $buffer .= $remaining;
            foreach (explode("\n", $buffer) as $line) {
                $processLine($line);
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        @unlink($configFile);

        // ── Keputusan fallback ─────────────────────────────────────────────
        // Kasus 1: Python tidak menghasilkan output sama sekali (crash diam-diam)
        if (!$pythonProducedOutput) {
            Log::warning('Python: no output (silent crash) — falling back to PHP chunked reading.');
            return false;
        }

        // Kasus 2: Python mengirim error event DAN belum ada data yang ter-insert
        // → aman untuk fallback ke PHP chunked reading (tidak ada duplikasi data)
        if ($pythonError !== null && $totalInserted === 0) {
            Log::warning('Python error (no data inserted yet) — falling back to PHP chunked reading. Error: ' . $pythonError);
            return false;
        }

        // Kasus 3: Python mengirim error event SETELAH sebagian data ter-insert
        // → tidak bisa fallback (akan duplikasi), kirim error ke browser
        if ($pythonError !== null && $totalInserted > 0) {
            Log::error('Python error after partial insert (' . $totalInserted . ' rows) — cannot fallback. Error: ' . $pythonError);
            $send('error', ['message' => 'Import terhenti setelah ' . $totalInserted . ' baris ter-insert. Error: ' . $pythonError]);
            return true;
        }

        // Kasus 4: Python selesai tanpa 'done' event (crash setelah beberapa batch)
        if ($pythonProducedOutput && !$doneSent && $totalInserted === 0) {
            Log::warning('Python exited without done event and no inserts — falling back to PHP chunked reading.');
            return false;
        }

        return true;
    }

    public function processExcelStream(Request $request)
    {
        // Chunked reading: memory jauh lebih rendah (~30MB/chunk vs 400MB+ full-load)
        ini_set('memory_limit', '2048M');
        set_time_limit(0);
        DB::disableQueryLog(); // Cegah memory leak dari query log saat jutaan baris

        $params            = session('excel_import_params', []);
        $normalizedHeaders = session('excel_headers', []);

        $jobId         = (int) ($params['job_id']       ?? $request->job_id ?? 0);
        $headerIndex   = (int) ($params['header_index'] ?? 0);
        $tableName     = $params['table_name']     ?? 'daily_loan_dinamis';
        $activeFilters = $params['active_filters'] ?? [];
        $relativePath  = $params['file_path']      ?? '';

        request()->session()->save();

        return response()->stream(function () use (
            $jobId, $headerIndex, $tableName, $activeFilters, $relativePath, $normalizedHeaders
        ) {
            $streamLock = null;
            $send = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
            };

            // Keepalive ping to prevent SSE idle timeouts (e.g., proxies/Apache/browser)
            $lastKeepAlive  = time();
            $keepAliveEvery = 15; // seconds
            $ping = function () use (&$lastKeepAlive) {
                echo ": keepalive\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
                $lastKeepAlive = time();
            };

            try {
                if ($jobId > 0) {
                    $streamLock = Cache::lock('import_excel_stream_job_' . $jobId, 7200);

                    if (!$streamLock->get()) {
                        $job = DB::table('import_jobs')->where('id', $jobId)->first();

                        if ($job && in_array($job->status, ['completed', 'failed', 'failed_partial'], true)) {
                            $send('complete', [
                                'total_success' => (int) ($job->total_success ?? 0),
                                'total_failed'  => (int) ($job->total_failed ?? 0),
                                'total_rows'    => (int) ($job->total_files ?? 0),
                            ]);
                        } else {
                            $send('error', [
                                'message' => 'Job import ini sudah sedang diproses pada koneksi lain. Proses kedua dibatalkan untuk mencegah data dobel.',
                            ]);
                        }
                        return;
                    }
                }

                $path = Storage::path($relativePath);

                if (!file_exists($path)) {
                    $send('error', ['message' => 'File Excel tidak ditemukan di server. Silakan upload ulang.']);
                    return;
                }
                if (empty($normalizedHeaders)) {
                    $send('error', ['message' => 'Header session hilang. Silakan ulangi import dari awal.']);
                    return;
                }

                if ($this->isCsvFile($path)) {
                    $delimiter = $this->detectCsvDelimiter($path);
                    $countHandle = @fopen($path, 'r');
                    if (!$countHandle) {
                        $send('error', ['message' => 'Gagal membuka file CSV di server.']);
                        return;
                    }

                    $totalRows = 0;
                    while (fgetcsv($countHandle, 0, $delimiter) !== false) {
                        $totalRows++;
                    }
                    fclose($countHandle);

                    $totalDataRows = max(0, $totalRows - ($headerIndex + 1));
                    $send('progress', [
                        'percent'   => 10,
                        'message'   => "File CSV terdeteksi: {$totalDataRows} baris data. Memulai processing...",
                        'rows_done' => 0,
                        'total'     => $totalDataRows,
                        'speed'     => 0,
                    ]);

                    $importContext = $this->buildImportContext($tableName, $normalizedHeaders, $activeFilters);
                    $send('progress', [
                        'percent'   => 15,
                        'message'   => "Mapping kolom selesai. Mulai insert ke tabel `{$tableName}`...",
                        'rows_done' => 0,
                        'total'     => $totalDataRows,
                        'speed'     => 0,
                    ]);

                    $handle = @fopen($path, 'r');
                    if (!$handle) {
                        $send('error', ['message' => 'Gagal membaca ulang file CSV di server.']);
                        return;
                    }

                    $dataToInsert   = [];
                    $totalInserted  = 0;
                    $totalFailed    = 0;
                    $rowsDone       = 0;
                    $progressEvery  = 500;
                    $startTime      = microtime(true);
                    $lastProgressAt = 0;
                    $currentRow     = -1;
                    $timestamp      = now()->toDateTimeString();

                    $flushBatch = function () use (&$dataToInsert, &$totalInserted, &$totalFailed, $tableName, $ping) {
                        if (empty($dataToInsert)) return;
                        foreach (array_chunk($dataToInsert, 100) as $batch) {
                            try {
                                DB::table($tableName)->insert($batch);
                                $totalInserted += count($batch);
                            } catch (\Exception $e) {
                                foreach ($batch as $single) {
                                    try {
                                        DB::table($tableName)->insert($single);
                                        $totalInserted++;
                                    } catch (\Exception $e2) {
                                        $totalFailed++;
                                    }
                                }
                            }
                            $ping();
                        }
                        $dataToInsert = [];
                    };

                    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                        $currentRow++;
                        $row = $this->normalizeCsvRow($row, $delimiter, count($normalizedHeaders));

                        if ($currentRow <= $headerIndex) continue;
                        if (empty(array_filter((array) $row, fn($v) => trim((string) $v) !== ''))) continue;

                        $finalRow = $this->mapExcelRowForInsert($row, $normalizedHeaders, $importContext, $timestamp);
                        if ($finalRow === null) continue;

                        $dataToInsert[] = $finalRow;
                        $rowsDone++;

                        if (count($dataToInsert) >= 500) {
                            $flushBatch();
                        }

                        if ($rowsDone - $lastProgressAt >= $progressEvery) {
                            $lastProgressAt = $rowsDone;
                            $elapsed = max(microtime(true) - $startTime, 0.001);
                            $speed = (int) ($rowsDone / $elapsed);
                            $pct = $totalDataRows > 0
                                ? min(92, 15 + (int) (($rowsDone / $totalDataRows) * 77))
                                : 50;

                            $send('progress', [
                                'percent'   => $pct,
                                'message'   => "Menyimpan data CSV ke database... ({$speed} baris/detik)",
                                'rows_done' => $rowsDone,
                                'total'     => $totalDataRows,
                                'speed'     => $speed,
                            ]);
                        } elseif ((time() - $lastKeepAlive) >= $keepAliveEvery) {
                            $ping();
                        }
                    }

                    fclose($handle);
                    $flushBatch();

                    $send('progress', [
                        'percent'   => 96,
                        'message'   => 'Finalisasi dan menyimpan status import...',
                        'rows_done' => $rowsDone,
                        'total'     => $totalDataRows,
                        'speed'     => 0,
                    ]);

                    $finalStatus = $totalFailed > 0
                        ? ($totalInserted > 0 ? 'failed_partial' : 'failed')
                        : 'completed';

                    if ($jobId > 0) {
                        DB::table('import_jobs')->where('id', $jobId)->update([
                            'total_success' => $totalInserted,
                            'total_failed'  => $totalFailed,
                            'status'        => $finalStatus,
                            'updated_at'    => now(),
                        ]);
                    }

                    if ($finalStatus === 'completed') {
                        $this->cleanupImportedFile($relativePath, $path);
                    }

                    $send('complete', [
                        'total_success' => $totalInserted,
                        'total_failed'  => $totalFailed,
                        'total_rows'    => $totalDataRows,
                    ]);
                    return;
                }

                // ── Coba Python CPU Processor terlebih dahulu ─────────────────
                $send('progress', [
                    'percent'   => 3,
                    'message'   => 'Memproses dengan pandas CPU (satu proses penuh)...',
                    'rows_done' => 0,
                    'total'     => 0,
                    'speed'     => 0,
                ]);

                $pythonHandled = $this->tryPythonGPU(
                    $send, $path, $headerIndex, $tableName,
                    $activeFilters, $normalizedHeaders, $jobId
                );

                if ($pythonHandled) {
                    if ($jobId > 0) {
                        $job = DB::table('import_jobs')->where('id', $jobId)->first();
                        if ($job && $job->status === 'completed') {
                            $this->cleanupImportedFile($relativePath, $path);
                        }
                    }
                    return; // Python GPU sudah menangani semuanya
                }

                // ── Fallback: PHP Chunked Reading ─────────────────────────────
                $send('progress', [
                    'percent'   => 5,
                    'message'   => 'Mode PHP Chunked aktif (1000 baris/chunk, hemat memori)...',
                    'rows_done' => 0,
                    'total'     => 0,
                    'speed'     => 0,
                ]);

                $reader = IOFactory::createReaderForFile($path);
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);

                // Dapatkan total baris TANPA load seluruh file
                $worksheetInfo = $reader->listWorksheetInfo($path);
                $totalRows     = $worksheetInfo[0]['totalRows'] ?? 0;
                $totalDataRows = max(0, $totalRows - ($headerIndex + 1));

                $send('progress', [
                    'percent'   => 10,
                    'message'   => "File terdeteksi: {$totalDataRows} baris data. Memulai chunked processing...",
                    'rows_done' => 0,
                    'total'     => $totalDataRows,
                    'speed'     => 0,
                ]);

                $importContext = $this->buildImportContext($tableName, $normalizedHeaders, $activeFilters);

                $send('progress', [
                    'percent'   => 15,
                    'message'   => "Mapping kolom selesai. Mulai insert ke tabel `{$tableName}`...",
                    'rows_done' => 0,
                    'total'     => $totalDataRows,
                    'speed'     => 0,
                ]);

                // ── Setup ChunkReadFilter ──────────────────────────────────────
                $chunkFilter = new ChunkReadFilter();
                $chunkFilter->setHeaderRow($headerIndex + 1); // 1-based Excel row

                // Hitung chunk size: bagi rata menjadi maksimal 4 chunk
                // Contoh: 100.000 baris → 4 chunk × 25.000 baris
                //         500.000 baris → 4 chunk × 125.000 baris
                //         500 baris     → 4 chunk × 125 baris (min 500 agar tidak terlalu kecil)
                $chunkSize = $totalDataRows > 0
                    ? max(500, (int) ceil($totalDataRows / 4))
                    : 1000;
                // startExcelRow: 1-based, baris data pertama setelah header
                $startExcelRow = $headerIndex + 2;

                $dataToInsert   = [];
                $totalInserted  = 0;
                $totalFailed    = 0;
                $rowsDone       = 0;
                $progressEvery  = 500;
                $startTime      = microtime(true);
                $lastProgressAt = 0;

                $flushBatch = function () use (
                    &$dataToInsert, &$totalInserted, &$totalFailed, $tableName, $ping
                ) {
                    if (empty($dataToInsert)) return;
                    // Sub-batch 100 baris — aman untuk max_allowed_packet MySQL default
                    foreach (array_chunk($dataToInsert, 100) as $batch) {
                        try {
                            DB::table($tableName)->insert($batch);
                            $totalInserted += count($batch);
                        } catch (\Exception $e) {
                            foreach ($batch as $single) {
                                try {
                                    DB::table($tableName)->insert($single);
                                    $totalInserted++;
                                } catch (\Exception $e2) {
                                    $totalFailed++;
                                }
                            }
                        }
                        // keepalive after each DB batch to avoid idle disconnects
                        $ping();
                    }
                    $dataToInsert = [];
                };

                // ── Loop chunk demi chunk ──────────────────────────────────────
                while ($startExcelRow <= $totalRows) {
                    // SSE keepalive to avoid idle disconnects during heavy operations
                    if ((time() - $lastKeepAlive) >= $keepAliveEvery) { $ping(); }

                    $chunkFilter->setRows($startExcelRow, $chunkSize);
                    $reader->setReadFilter($chunkFilter);

                    $spreadsheet = $reader->load($path);
                    $sheet       = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
                    $spreadsheet->disconnectWorksheets();
                    // keepalive after loading/reading chunk
                    $ping();
                    unset($spreadsheet);
                    gc_collect_cycles();

                    // Index array 0-based: Excel row N → array index N-1
                    $startArrayIdx = $startExcelRow - 1;
                    $endArrayIdx   = $startArrayIdx + $chunkSize;
                    $timestamp = now()->toDateTimeString();

                    foreach ($sheet as $rowIndex => $row) {
                        // Lewati baris di luar window chunk ini
                        if ($rowIndex < $startArrayIdx || $rowIndex >= $endArrayIdx) continue;
                        // Lewati baris header dan sebelumnya
                        if ($rowIndex <= $headerIndex) continue;
                        // Lewati baris kosong
                        if (empty(array_filter((array) $row, fn($v) => trim((string) $v) !== ''))) continue;

                        $finalRow = $this->mapExcelRowForInsert($row, $normalizedHeaders, $importContext, $timestamp);
                        if ($finalRow === null) continue;

                        $dataToInsert[] = $finalRow;
                        $rowsDone++;

                        if (count($dataToInsert) >= 500) {
                            $flushBatch();
                        }

                        if ($rowsDone - $lastProgressAt >= $progressEvery) {
                            $lastProgressAt = $rowsDone;
                            $elapsed        = max(microtime(true) - $startTime, 0.001);
                            $speed          = (int) ($rowsDone / $elapsed);
                            $pct            = $totalDataRows > 0
                                ? min(92, 15 + (int) (($rowsDone / $totalDataRows) * 77))
                                : 50;

                            $send('progress', [
                                'percent'   => $pct,
                                'message'   => "Menyimpan data ke database... ({$speed} baris/detik)",
                                'rows_done' => $rowsDone,
                                'total'     => $totalDataRows,
                                'speed'     => $speed,
                            ]);
                        } else {
                            // periodic keepalive if no progress recently
                            if ((time() - $lastKeepAlive) >= $keepAliveEvery) { $ping(); }
                        }
                    }

                    // Flush sisa batch di akhir setiap chunk
                    $flushBatch();
                    $startExcelRow += $chunkSize;
                }

                $send('progress', [
                    'percent'   => 96,
                    'message'   => 'Finalisasi dan menyimpan status import...',
                    'rows_done' => $rowsDone,
                    'total'     => $totalDataRows,
                    'speed'     => 0,
                ]);

                $finalStatus = $totalFailed > 0
                    ? ($totalInserted > 0 ? 'failed_partial' : 'failed')
                    : 'completed';

                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'total_success' => $totalInserted,
                        'total_failed'  => $totalFailed,
                        'status'        => $finalStatus,
                        'updated_at'    => now(),
                    ]);
                }

                if ($finalStatus === 'completed') {
                    $this->cleanupImportedFile($relativePath, $path);
                }

                $send('complete', [
                    'total_success' => $totalInserted,
                    'total_failed'  => $totalFailed,
                    'total_rows'    => $totalDataRows,
                ]);

            } catch (\Throwable $e) {
                Log::error('EXCEL STREAM ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
                $send('error', [
                    'message' => 'Fatal Error: ' . $e->getMessage() . ' (line ' . $e->getLine() . ')',
                ]);
            } finally {
                if ($streamLock) {
                    try {
                        $streamLock->release();
                    } catch (\Throwable $e) {
                        Log::warning('Failed to release import stream lock for job ' . $jobId . ': ' . $e->getMessage());
                    }
                }
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    public function processExcelChunk(Request $request)
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);
        DB::disableQueryLog(); // Cegah memory leak dari query log

        try {
            $jobId       = (int) $request->job_id;
            $headerIndex = (int) $request->header_index;
            $tableName   = $request->table_name;
            $startRow    = max((int) $request->start_row, $headerIndex + 1);
            $chunkSize   = max((int) $request->chunk_size, 1);
            $activeFilters = json_decode($request->active_filters_json, true) ?: [];
            $relativePath  = urldecode($request->file_path);
            $path = Storage::path($relativePath);

            if (!file_exists($path)) {
                return response()->json(['status' => 'error', 'text' => 'File Excel tidak ditemukan di server. Silakan upload ulang.'], 422);
            }

            $normalizedHeaders = session('excel_headers', []);
            if (empty($normalizedHeaders)) {
                return response()->json([
                    'status' => 'error',
                    'text'   => 'Header session hilang. Silakan ulangi import dari awal.',
                ], 422);
            }

            $importContext = $this->buildImportContext($tableName, $normalizedHeaders, $activeFilters);

            if ($this->isCsvFile($path)) {
                $delimiter = $this->detectCsvDelimiter($path);
                $handle = @fopen($path, 'r');
                if (!$handle) {
                    return response()->json(['status' => 'error', 'text' => 'Gagal membuka file CSV.'], 422);
                }

                $dataToInsert  = [];
                $chunkInserted = 0;
                $chunkFailed   = 0;
                $debugRowsRead = 0;
                $debugPassed   = 0;
                $sampleMapped  = null;
                $timestamp     = now()->toDateTimeString();
                $rowIndex      = -1;

                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    $rowIndex++;
                    if ($rowIndex < $startRow || $rowIndex >= $endExclusive) continue;
                    if ($rowIndex <= $headerIndex) continue;

                    $row = $this->normalizeCsvRow($row, $delimiter, count($normalizedHeaders));
                    if (empty(array_filter((array) $row, fn($v) => trim((string) $v) !== ''))) continue;

                    $debugRowsRead++;

                    $finalRow = $this->mapExcelRowForInsert($row, $normalizedHeaders, $importContext, $timestamp);
                    if ($finalRow === null) continue;
                    $debugPassed++;

                    if ($sampleMapped === null) $sampleMapped = $finalRow;
                    if (count($finalRow) > 3) $dataToInsert[] = $finalRow;
                }

                fclose($handle);

                $this->flushInsertBuffer($dataToInsert, $tableName, $chunkInserted, $chunkFailed);

                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'total_success' => DB::raw('total_success + ' . $chunkInserted),
                        'total_failed'  => DB::raw('total_failed + ' . $chunkFailed),
                        'updated_at'    => now(),
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'inserted' => $chunkInserted,
                    'failed' => $chunkFailed,
                    'debug_rows_read' => $debugRowsRead,
                    'debug_passed' => $debugPassed,
                ]);
            }

            $endExclusive = $startRow + $chunkSize;

            $headerExcelRow = $headerIndex + 1;
            $startExcelRow  = $startRow + 1;

            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $chunkFilter = new ChunkReadFilter();
            $chunkFilter->setHeaderRow($headerExcelRow);
            $chunkFilter->setRows($startExcelRow, $chunkSize);
            $reader->setReadFilter($chunkFilter);

            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $dataToInsert  = [];
            $chunkInserted = 0;
            $chunkFailed   = 0;
            $debugRowsRead = 0;
            $debugPassed   = 0;
            $sampleMapped  = null;
            $timestamp     = now()->toDateTimeString();

            foreach ($sheet as $rowIndex => $row) {
                if ($rowIndex < $startRow || $rowIndex >= $endExclusive) continue;
                if ($rowIndex <= $headerIndex) continue;
                if (empty(array_filter((array) $row, fn($v) => trim((string) $v) !== ''))) continue;

                $debugRowsRead++;

                $finalRow = $this->mapExcelRowForInsert($row, $normalizedHeaders, $importContext, $timestamp);
                if ($finalRow === null) continue;
                $debugPassed++;

                if ($sampleMapped === null) $sampleMapped = $finalRow;

                if (count($finalRow) > 3) $dataToInsert[] = $finalRow;
            }

            // Sub-batch 100 baris — aman untuk max_allowed_packet MySQL default
            $this->flushInsertBuffer($dataToInsert, $tableName, $chunkInserted, $chunkFailed);

            if ($jobId > 0) {
                DB::table('import_jobs')->where('id', $jobId)->update([
                    'total_success' => DB::raw('total_success + ' . $chunkInserted),
                    'total_failed'  => DB::raw('total_failed + ' . $chunkFailed),
                    'updated_at'    => now(),
                ]);
            }

            return response()->json([
                'status' => 'success',
                'inserted' => $chunkInserted,
                'failed' => $chunkFailed,
                'debug_rows_read' => $debugRowsRead,
                'debug_passed' => $debugPassed,
            ]);
        } catch (\Exception $e) {
            Log::error('CHUNK PROCESS ERROR: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
