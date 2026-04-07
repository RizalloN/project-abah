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
    private const SIMPANAN_MULTIPN_REPORT_ID = 9;
    private const DB_INSERT_BATCH_SIZE = 500;
    private const STREAM_PROGRESS_EVERY = 2000;
    private const FALLBACK_SPLIT_THRESHOLD = 25;
    private const INSERT_BUFFER_FLUSH_SIZE = 1000;
    private const BULK_LOAD_TEMP_DIR = 'app/import_bulk';
    private const STAGED_CSV_TEMP_DIR = 'app/excel_stage';

    private function isDailyLoanTable(?string $tableName = null): bool
    {
        return ($tableName ?? $this->resolveExcelTableName()) === 'daily_loan_dinamis';
    }

    private function isSimpananMultiPnTable(?string $tableName = null): bool
    {
        return ($tableName ?? $this->resolveExcelTableName()) === 'simpanan_multipn';
    }

    private function detectCsvDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ',';
        }

        try {
            $sampleLine = fgets($handle);
            if ($sampleLine === false) {
                return ',';
            }

            $sampleLine = preg_replace('/^\xEF\xBB\xBF/', '', $sampleLine);
            $delimiters = [',', ';', "\t", '|'];
            $bestDelimiter = ',';
            $bestCount = -1;

            foreach ($delimiters as $delimiter) {
                $count = count(str_getcsv($sampleLine, $delimiter));
                if ($count > $bestCount) {
                    $bestCount = $count;
                    $bestDelimiter = $delimiter;
                }
            }

            return $bestDelimiter;
        } finally {
            fclose($handle);
        }
    }

    private function normalizeImportColumnName(string $headerName): string
    {
        $normalizedHeader = preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($headerName)));

        $aliases = [
            'TEXTBOX20' => 'total_kewajiban',
            'TOTAL_KEWAJIBAN' => 'total_kewajiban',
            'TEXTBOX21' => 'os_idr',
            'OS_IDR' => 'os_idr',
        ];

        if (isset($aliases[$normalizedHeader])) {
            return $aliases[$normalizedHeader];
        }

        return strtolower(str_replace(' ', '_', trim($headerName)));
    }

    private function resolvePreviewHeaderLabel(string $headerName, string $tableName): string
    {
        if ($tableName !== 'daily_loan_dinamis') {
            return $headerName;
        }

        return match ($this->normalizeImportColumnName($headerName)) {
            'total_kewajiban' => 'Total Kewajiban',
            'os_idr' => 'OS IDR',
            default => $headerName,
        };
    }

    private function getDailyLoanPreviewOrder(): array
    {
        return [
            'periode',
            'kode_kanwil1',
            'kanwil1',
            'kode_cabang1',
            'cabang1',
            'branch1',
            'unit1',
            'curtyp',
            'ao_name',
            'cifno',
            'nomor_rekening1',
            'status_rekening1',
            'ln_type',
            'nama_debitur1',
            'rate',
            'jangka_waktu1',
            'plafon',
            'baki_debet1',
            'ckpn',
            'nilai_tercatat1',
            'kol_adk1',
            'kolek_detail',
            'kolek',
            'kolektabilitas_lancar',
            'kolektabilitas_dpk',
            'kolektabilitas_kuranglancar',
            'kolektabilitas_diragukan',
            'kolektabilitas_macet',
            'total_kewajiban',
            'tunggakan_pokok',
            'tunggakan_bunga',
            'tunggakan_penalti',
            'umur_tunggakan',
            'tgl_realisasi',
            'tgl_jatuh_tempo',
            'tanggal_menunggak',
            'tgl_bayar_terakhir',
            'tgl_terminate',
            'last_date_maintenance_billing',
            'next_pmt_date',
            'next_pmt_int_date',
            'advance_payment',
            'bap',
            'payment_amount',
            'final_payment_amount',
            'npb_pokok_la',
            'npb_pokok_lf',
            'npb_bunga_la',
            'npb_bunga_lf',
            'jml_angsuran1',
            'jumlah_bayar',
            'deffered_bunga',
            'sai_tunggakan',
            'sai_deffered',
            'sai1',
            'freq_payment',
            'freq_int_payment',
            'jadwal_gp_pokok',
            'pn_pengelola1',
            'pn_name1',
            'pn_pemrakarsa1',
            'pn_referral1',
            'pn_restruk1',
            'pn_pengelola2',
            'pn_pemutus1',
            'pn_crm1',
            'pn_crr',
            'pn_referral_naik_kelas1',
            'jumlah_pn1',
            'jumlah_pn_all1',
            'code',
            'description',
            'kecamatan_t_tinggal',
            'kelurahan_t_tinggal',
            'kodepos_t_tinggal',
            'kecamatan_t_usaha',
            'kelurahan_t_usaha',
            'kodepos_t_usaha',
            'segmen_dashboard',
            'produk_dashboard',
            'divisi_segmen_dashboard',
            'npl_method',
            'restruk_ke1',
            'jenis_restruk1',
            'tgl_akad_restruk',
            'flag_restruk',
            'flag_restruk_covid1',
            'flag_commodity_chain1',
            'flag_briguna_digital1',
            'flag_agf',
            'flag_aft',
            'pmtamt',
            'pmtamt_base',
            'offcr',
            'lbdotu',
            'keterangan_pn_pengelola',
            'os_idr',
            'flag_klaim',
            'os_sebelum_klaim',
            'os_penuh_berjalan',
            'bilprn',
            'bilint',
            'billc',
        ];
    }

    private function buildLegacyPreviewRows(array $headers, array $previewRows): array
    {
        $indexedRows = [];

        foreach ($previewRows as $row) {
            $indexedRow = [];
            foreach ($headers as $header) {
                $value = $row[$header] ?? null;
                $indexedRow[] = $value === null ? '' : (string) $value;
            }
            $indexedRows[] = $indexedRow;
        }

        return $indexedRows;
    }

    private function readCsvRecord($handle, string $delimiter = ',')
    {
        $row = fgetcsv($handle, 0, $delimiter);
        if ($row === false) {
            return false;
        }

        if (count($row) === 1) {
            $single = (string) ($row[0] ?? '');
            if ($single !== '' && str_contains($single, $delimiter)) {
                $reparsed = str_getcsv($single, $delimiter);
                if (count($reparsed) > 1) {
                    $row = $reparsed;
                }
            }
        }

        if (!empty($row)) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($row[0] ?? ''));
        }

        return $row;
    }

    private function isRowNumberLikeHeader(string $headerName): bool
    {
        return in_array($this->normalizeImportColumnName($headerName), [
            'no',
            'row_num',
            'rownumber',
            'nomor_baris',
            'urutan',
        ], true);
    }

    protected function isCompleteSimpananMultiPnSourceRow(array $headers, array $row): bool
    {
        if (!$this->isSimpananMultiPnTable()) {
            return true;
        }

        $row = $this->padRow($row, count($headers));
        $valuesByHeader = [];

        foreach ($headers as $index => $header) {
            $normalizedHeader = $this->normalizeImportColumnName((string) $header);
            if ($normalizedHeader === '') {
                continue;
            }

            $valuesByHeader[$normalizedHeader] = trim((string) ($row[$index] ?? ''));
        }

        foreach (['posisi', 'cifno', 'no_rekening', 'jenis_simpanan', 'saldo_idr'] as $requiredHeader) {
            if (($valuesByHeader[$requiredHeader] ?? '') === '') {
                return false;
            }
        }

        return true;
    }

    private function hasRequiredSimpananMultiPnImportData(array $row): bool
    {
        if (!$this->isSimpananMultiPnTable()) {
            return true;
        }

        $valuesByLowerKey = [];
        foreach ($row as $key => $value) {
            $valuesByLowerKey[strtolower((string) $key)] = $value;
        }

        foreach (['posisi', 'cifno', 'no_rekening', 'jenis_simpanan', 'saldo_idr'] as $requiredColumn) {
            $value = $valuesByLowerKey[$requiredColumn] ?? null;
            if ($value === null) {
                return false;
            }

            if (is_string($value) && trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    private function alignImportedRowWithNormalizedHeaders(array $row, array $normalizedHeaders): array
    {
        if (!$this->isSimpananMultiPnTable()) {
            return $row;
        }

        $headerCount = count($normalizedHeaders);
        if ($headerCount === 0) {
            return $row;
        }

        if (count($row) === $headerCount + 1) {
            $firstHeader = $this->normalizeImportColumnName((string) ($normalizedHeaders[0] ?? ''));
            $firstValue = trim((string) ($row[0] ?? ''));

            if ($firstHeader !== 'no' && preg_match('/^\d+$/', $firstValue) === 1) {
                array_shift($row);
            }
        }

        return $row;
    }

    private function stripIgnoredPreviewColumns(array $headers, array $rows, array $formattedUniqueValues, string $tableName): array
    {
        if (!$this->isSimpananMultiPnTable($tableName)) {
            return [
                'headers' => $headers,
                'rows' => $rows,
                'formatted_unique_values' => $formattedUniqueValues,
            ];
        }

        $keptIndexes = [];
        $filteredHeaders = [];

        foreach ($headers as $index => $header) {
            if ($this->isRowNumberLikeHeader((string) $header)) {
                continue;
            }

            $keptIndexes[] = $index;
            $filteredHeaders[] = $header;
        }

        if (count($keptIndexes) === count($headers)) {
            return [
                'headers' => $headers,
                'rows' => $rows,
                'formatted_unique_values' => $formattedUniqueValues,
            ];
        }

        $filteredRows = [];
        foreach ($rows as $row) {
            $filteredRow = [];
            foreach ($keptIndexes as $index) {
                $header = $headers[$index];
                $filteredRow[$header] = $row[$header] ?? null;
            }
            $filteredRows[] = $filteredRow;
        }

        $filteredUniqueValues = [];
        foreach ($keptIndexes as $newIndex => $oldIndex) {
            $filteredUniqueValues[$newIndex] = $formattedUniqueValues[$oldIndex] ?? [];
        }

        return [
            'headers' => array_values($filteredHeaders),
            'rows' => $filteredRows,
            'formatted_unique_values' => $filteredUniqueValues,
        ];
    }

    protected function buildPreviewPayloadFromCsvFile(string $csvPath): array
    {
        if (!file_exists($csvPath)) {
            throw new \RuntimeException('File CSV tidak ditemukan.');
        }

        $delimiter = $this->detectCsvDelimiter($csvPath);
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV.');
        }

        try {
            $headers = $this->readCsvRecord($handle, $delimiter);
            if ($headers === false || empty($headers)) {
                throw new \RuntimeException('Header CSV tidak ditemukan.');
            }

            foreach ($headers as $index => $header) {
                $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
                $headers[$index] = trim($header) !== '' ? trim($header) : 'COL_' . $index;
            }

            $previewLimit = 100;
            $uniqueLimit = 5000;
            $cleanPreview = [];
            $formattedUniqueValues = [];
            $rowsProcessedForUniques = 0;
            $totalAvailableRows = 0;

            foreach (array_keys($headers) as $index) {
                $formattedUniqueValues[$index] = [];
            }

            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                if (empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                    continue;
                }

                $row = $this->padRow($row, count($headers));
                if (!$this->isCompleteSimpananMultiPnSourceRow($headers, $row)) {
                    continue;
                }

                $totalAvailableRows++;
                $displayRow = [];

                foreach ($headers as $index => $header) {
                    $value = $this->normalizeExcelValue($header, $row[$index] ?? null);
                    $displayRow[$header] = $value;

                    if ($rowsProcessedForUniques < $uniqueLimit) {
                        $formattedUniqueValues[$index][$value === null || $value === '' ? '(Blank)' : (string) $value] = true;
                    }
                }

                if (!$this->hasMeaningfulImportData($displayRow)) {
                    continue;
                }

                if (count($cleanPreview) < $previewLimit) {
                    $cleanPreview[] = $displayRow;
                }

                if ($rowsProcessedForUniques < $uniqueLimit) {
                    $rowsProcessedForUniques++;
                }
            }

            foreach ($formattedUniqueValues as $index => $valuesMap) {
                $keys = array_keys($valuesMap);
                usort($keys, 'strnatcmp');
                $formattedUniqueValues[$index] = $keys;
            }

            $filteredPreview = $this->stripIgnoredPreviewColumns(
                $headers,
                $cleanPreview,
                $formattedUniqueValues,
                $this->resolveExcelTableName()
            );

            $orderedPreview = $this->orderPreviewColumns(
                $filteredPreview['headers'],
                $filteredPreview['formatted_unique_values'],
                $filteredPreview['rows'],
                $this->resolveExcelTableName()
            );

            return [
                'headers' => $orderedPreview['headers'],
                'preview' => $orderedPreview['preview'],
                'formattedUniqueValues' => $orderedPreview['formatted_unique_values'],
                'displayFilterMap' => $orderedPreview['display_filter_map'],
                'header_index' => 0,
                'header_row' => 1,
                'normalized_headers' => $filteredPreview['headers'],
                'rows_scanned' => min($rowsProcessedForUniques, $totalAvailableRows),
                'total_sample_rows' => $totalAvailableRows,
                'delimiter' => $delimiter,
            ];
        } finally {
            fclose($handle);
        }
    }

    private function useDailyLoanReport(Request $request): Request
    {
        $request->merge(['id_report' => self::DAILY_LOAN_REPORT_ID]);
        session(['active_id_report' => self::DAILY_LOAN_REPORT_ID]);

        return $request;
    }

    private function useSimpananMultiPnReport(Request $request): Request
    {
        $request->merge(['id_report' => self::SIMPANAN_MULTIPN_REPORT_ID]);
        session([
            'active_id_report' => self::SIMPANAN_MULTIPN_REPORT_ID,
            'excel_import_source' => 'simpanan_excel',
        ]);

        return $request;
    }

    public function uploadDailyLoanExcel(Request $request)
    {
        return $this->uploadExcel($this->useDailyLoanReport($request));
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

    public function uploadSimpananMultiPnExcel(Request $request)
    {
        return $this->uploadExcel($this->useSimpananMultiPnReport($request));
    }

    public function previewSimpananMultiPnExcel(Request $request)
    {
        return $this->previewExcel($this->useSimpananMultiPnReport($request));
    }

    public function prepareSimpananMultiPnPreview(Request $request)
    {
        return $this->preparePreviewStream($this->useSimpananMultiPnReport($request));
    }

    public function initSimpananMultiPnImport(Request $request)
    {
        return $this->initExcelImport($this->useSimpananMultiPnReport($request));
    }

    public function streamSimpananMultiPnImport(Request $request)
    {
        return $this->processExcelStream($this->useSimpananMultiPnReport($request));
    }

    public function chunkSimpananMultiPnImport(Request $request)
    {
        return $this->processExcelChunk($this->useSimpananMultiPnReport($request));
    }

    private function countCsvDataRows(string $csvPath): int
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return 0;
        }

        $delimiter = $this->detectCsvDelimiter($csvPath);
        $rows = 0;

        try {
            $headerRead = false;
            $headers = [];

            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                if (!$headerRead) {
                    $headers = (array) $row;
                    $headerRead = true;
                    continue;
                }

                if (empty(array_filter((array) $row, fn ($val) => trim((string) $val) !== ''))) {
                    continue;
                }

                if (!$this->isCompleteSimpananMultiPnSourceRow($headers, (array) $row)) {
                    continue;
                }

                $rows++;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
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

    private function cleanupSuccessfulImportArtifacts(int $jobId = 0, string $relativePath = '', ?string $absolutePath = null, array $extraPaths = []): void
    {
        if ($jobId <= 0) {
            $this->cleanupImportedFile($relativePath, $absolutePath);

            foreach ($extraPaths as $extraPath) {
                if (is_string($extraPath) && $extraPath !== '' && file_exists($extraPath)) {
                    @unlink($extraPath);
                }
            }

            return;
        }

        try {
            app(ImportCleanupController::class)->cleanupSuccessfulJobArtifacts(
                $jobId,
                array_values(array_filter(array_merge([$relativePath, $absolutePath], $extraPaths)))
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to trigger centralized import cleanup: ' . $e->getMessage(), [
                'job_id' => $jobId,
                'relative_path' => $relativePath,
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
        $tableColumns = Schema::getColumnListing($tableName);
        $tableColumnsLookup = [];
        $tableColumnsByLower = [];
        foreach ($tableColumns as $columnName) {
            $lowerColumnName = strtolower($columnName);
            $tableColumnsLookup[$lowerColumnName] = true;
            $tableColumnsByLower[$lowerColumnName] = $columnName;
        }

        $defaultUniqueIdCol = str_contains($tableName, 'simpanan') ? 'uniqueid_SMPN' : 'uniqueid_namareport';
        $uniqueIdCol = $tableColumnsByLower[strtolower($defaultUniqueIdCol)] ?? $defaultUniqueIdCol;
        $suffix = str_contains($tableName, 'simpanan') ? '_SMPN' : '_DLD';
        $skipColumnsLookup = array_fill_keys(['id', strtolower($uniqueIdCol)], true);
        $dateColumnsLookup = array_fill_keys([
            'PERIODE',
            'POSISI',
            'TANGGAL',
            'TGL_REALISASI',
            'TGL_JATUH_TEMPO',
            'TANGGAL_MENUNGGAK',
            'TGL_BAYAR_TERAKHIR',
            'TGL_TERMINATE',
            'LAST_DATE_MAINTENANCE_BILLING',
            'NEXT_PMT_DATE',
            'NEXT_PMT_INT_DATE',
            'TGL_AKAD_RESTRUK',
        ], true);
        $decimalColumnsLookup = array_fill_keys([
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
            'TEXTBOX20',
            'TOTAL_KEWAJIBAN',
            'TUNGGAKAN_POKOK',
            'TUNGGAKAN_BUNGA',
            'TUNGGAKAN_PENALTI',
            'UMUR_TUNGGAKAN',
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
            'FREQ_PAYMENT',
            'FREQ_INT_PAYMENT',
            'JUMLAH_PN1',
            'JUMLAH_PN_ALL1',
            'RESTRUK_KE1',
            'PMTAMT',
            'PMTAMT_BASE',
            'TEXTBOX21',
            'OS_IDR',
            'OS_SEBELUM_KLAIM',
            'OS_PENUH_BERJALAN',
            'BILPRN',
            'BILINT',
            'BILLC',
        ], true);

        $filterLookups = [];
        foreach ($activeFilters as $filterIdx => $values) {
            $filterLookups[(int) $filterIdx] = array_fill_keys(
                array_map(fn ($v) => (string) $v, (array) $values),
                true
            );
        }

        $headerRules = [];
        foreach ($validIndexes as $filterIdx => $originalIndex) {
            $headerName = $normalizedHeaders[$originalIndex];
            $normalizedHeader = preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim((string) $headerName)));
            $dbColumn = $this->normalizeImportColumnName($headerName);

            if (!isset($tableColumnsLookup[$dbColumn])) {
                if ($dbColumn === 'total_kewajiban' && isset($tableColumnsLookup['textbox20'])) {
                    $dbColumn = 'textbox20';
                } elseif ($dbColumn === 'os_idr' && isset($tableColumnsLookup['textbox21'])) {
                    $dbColumn = 'textbox21';
                }
            }

            $headerRules[$originalIndex] = [
                'header_name' => $headerName,
                'db_column' => $dbColumn,
                'is_date' => isset($dateColumnsLookup[$normalizedHeader]),
                'is_decimal' => isset($decimalColumnsLookup[$normalizedHeader]),
                'filter_lookup' => $filterLookups[$filterIdx] ?? null,
            ];
        }

        return [
            'valid_indexes' => $validIndexes,
            'header_count' => $headerCount,
            'table_columns_lookup' => $tableColumnsLookup,
            'table_columns_by_lower' => $tableColumnsByLower,
            'unique_id_col' => $uniqueIdCol,
            'suffix' => $suffix,
            'skip_columns_lookup' => $skipColumnsLookup,
            'filter_lookups' => $filterLookups,
            'header_rules' => $headerRules,
            'unique_id_prefix' => str_replace('.', '', uniqid('imp', true)),
            'row_sequence' => 0,
        ];
    }

    private function supportsNativeBulkLoad(): bool
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        try {
            $row = DB::selectOne("SHOW VARIABLES LIKE 'local_infile'");
            return strtoupper((string) ($row->Value ?? $row->value ?? 'OFF')) === 'ON';
        } catch (\Throwable $e) {
            Log::warning('Unable to verify local_infile support: ' . $e->getMessage());
            return false;
        }
    }

    private function buildBulkLoadColumns(string $tableName, array $normalizedHeaders, array $activeFilters = []): array
    {
        $context = $this->buildImportContext($tableName, $normalizedHeaders, $activeFilters);
        $columns = [$context['unique_id_col'], 'created_at', 'updated_at'];

        foreach ($context['valid_indexes'] as $originalIndex) {
            $rule = $context['header_rules'][$originalIndex] ?? null;
            if (!$rule) {
                continue;
            }

            $dbColumn = strtolower((string) ($rule['db_column'] ?? ''));
            if ($dbColumn === '' || isset($context['skip_columns_lookup'][$dbColumn])) {
                continue;
            }
            if (!isset($context['table_columns_lookup'][$dbColumn])) {
                continue;
            }

            $columns[] = $context['table_columns_by_lower'][$dbColumn] ?? $dbColumn;
        }

        return array_values(array_unique($columns));
    }

    private function createBulkLoadTempCsvPath(string $tableName, int $jobId): string
    {
        $directory = storage_path(self::BULK_LOAD_TEMP_DIR);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        return $directory . DIRECTORY_SEPARATOR . $tableName . '_' . $jobId . '_' . Str::random(8) . '.csv';
    }

    private function createStagedCsvPath(string $tableName): string
    {
        $directory = storage_path(self::STAGED_CSV_TEMP_DIR);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        return $directory . DIRECTORY_SEPARATOR . $tableName . '_stage_' . Str::random(10) . '.csv';
    }

    protected function putExcelPreviewState(string $key, array $payload): void
    {
        Cache::put('excel_preview_state_' . $key, $payload, now()->addMinutes(30));
    }

    protected function getExcelPreviewState(?string $key): array
    {
        if (!$key) {
            return [];
        }

        $cached = Cache::get('excel_preview_state_' . $key);
        return is_array($cached) ? $cached : [];
    }

    protected function putExcelImportJobState(int $jobId, array $payload): void
    {
        if ($jobId <= 0) {
            return;
        }

        Cache::put('excel_import_job_' . $jobId, $payload, now()->addHours(4));
    }

    protected function getExcelImportJobState(int $jobId): array
    {
        if ($jobId <= 0) {
            return [];
        }

        $cached = Cache::get('excel_import_job_' . $jobId);
        return is_array($cached) ? $cached : [];
    }

    private function loadCsvIntoMysql(string $csvPath, string $tableName, array $columns): int
    {
        if (!file_exists($csvPath)) {
            throw new \RuntimeException('File CSV sementara tidak ditemukan untuk bulk load.');
        }

        if (empty($columns)) {
            throw new \RuntimeException('Kolom bulk load kosong.');
        }

        $connection = config('database.default', 'mysql');
        $dbConfig = config("database.connections.{$connection}", []);
        $charset = $dbConfig['charset'] ?? 'utf8mb4';
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'] ?? '';
        $username = $dbConfig['username'] ?? '';
        $password = $dbConfig['password'] ?? '';
        $unixSocket = $dbConfig['unix_socket'] ?? '';

        $dsn = $unixSocket !== ''
            ? "mysql:unix_socket={$unixSocket};dbname={$database};charset={$charset}"
            : "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
        ]);

        $normalizedPath = str_replace('\\', '/', realpath($csvPath) ?: $csvPath);
        $quotedPath = $pdo->quote($normalizedPath);
        $quotedColumns = implode(', ', array_map(function (string $column) {
            return '`' . str_replace('`', '``', $column) . '`';
        }, $columns));

        $sql = "LOAD DATA LOCAL INFILE {$quotedPath} INTO TABLE `{$tableName}` "
            . "CHARACTER SET utf8mb4 "
            . "FIELDS TERMINATED BY ',' ENCLOSED BY '\"' "
            . "LINES TERMINATED BY '\\n' "
            . "({$quotedColumns})";

        $affected = $pdo->exec($sql);
        $pdo = null;
        if ($affected === false) {
            throw new \RuntimeException('LOAD DATA LOCAL INFILE gagal dieksekusi.');
        }

        return (int) $affected;
    }

    private function normalizeExcelValueByRule(array $rule, $value)
    {
        $value = ($value === null) ? '' : trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (!empty($rule['is_date'])) {
            return $this->normalizeDateValue($value);
        }

        if (!empty($rule['is_decimal'])) {
            return $this->normalizeDecimalValue($value);
        }

        if (is_numeric($value)) {
            $formatted = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
            return $formatted === '' ? '0' : $formatted;
        }

        return $value;
    }

    private function applyDailyLoanCompatibilityColumns(array $row, array $context): array
    {
        if (!$this->isDailyLoanTable()) {
            return $row;
        }

        $compatibilityMap = [
            'kode_kanwil1' => 'kode_kanwil',
            'kanwil1' => 'kanwil',
            'kode_cabang1' => 'kode_cabang',
            'cabang1' => 'cabang',
            'branch1' => 'branch',
            'unit1' => 'unit',
            'nomor_rekening1' => 'nomor_rekening',
            'baki_debet1' => 'baki_debet',
            'total_kewajiban' => 'textbox20',
            'os_idr' => 'textbox21',
        ];

        foreach ($compatibilityMap as $sourceColumn => $targetColumn) {
            $sourceKey = $context['table_columns_by_lower'][$sourceColumn] ?? $sourceColumn;
            $targetKey = $context['table_columns_by_lower'][$targetColumn] ?? $targetColumn;

            if (!array_key_exists($sourceKey, $row)) {
                continue;
            }

            if (!isset($context['table_columns_lookup'][$targetColumn])) {
                continue;
            }

            $targetHasValue = array_key_exists($targetKey, $row)
                && $row[$targetKey] !== null
                && trim((string) $row[$targetKey]) !== '';

            if ($targetHasValue) {
                continue;
            }

            $row[$targetKey] = $row[$sourceKey];
        }

        return $row;
    }

    private function mapExcelRowForInsert(array $row, array $normalizedHeaders, array &$context, string $timestamp): ?array
    {
        $row = $this->alignImportedRowWithNormalizedHeaders($row, $normalizedHeaders);
        $row = $this->padRow($row, $context['header_count']);
        $mappedExcelData = [];

        foreach ($context['valid_indexes'] as $filterIdx => $originalIndex) {
            $rule = $context['header_rules'][$originalIndex] ?? null;
            if (!$rule) {
                continue;
            }

            $value = $this->normalizeExcelValueByRule($rule, $row[$originalIndex] ?? '');

            if (!empty($rule['filter_lookup'])) {
                $filterValue = ($value === null) ? '(Blank)' : (string) $value;
                if (!isset($rule['filter_lookup'][$filterValue])) {
                    return null;
                }
            }

            $mappedExcelData[$rule['db_column']] = $value;
        }

        $context['row_sequence']++;
        $finalRow = [
            $context['unique_id_col'] => $context['unique_id_prefix'] . '_' . $context['row_sequence'] . $context['suffix'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        foreach ($mappedExcelData as $dbCol => $value) {
            if (isset($context['skip_columns_lookup'][$dbCol])) {
                continue;
            }
            if (!isset($context['table_columns_lookup'][$dbCol])) {
                continue;
            }
            $finalRow[$context['table_columns_by_lower'][$dbCol] ?? $dbCol] = $value;
        }

        $finalRow = $this->applyDailyLoanCompatibilityColumns($finalRow, $context);

        if (!$this->hasRequiredSimpananMultiPnImportData($finalRow)) {
            return null;
        }

        return $this->hasMeaningfulImportData($finalRow, [
            $context['unique_id_col'],
            'created_at',
            'updated_at',
            'periode',
            'posisi',
        ]) ? $finalRow : null;
    }

    private function hasMeaningfulImportData(array $row, array $ignoredKeys = []): bool
    {
        $ignoredLookup = array_fill_keys(array_map('strtolower', $ignoredKeys), true);

        foreach ($row as $key => $value) {
            if (isset($ignoredLookup[strtolower((string) $key)])) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return true;
        }

        return false;
    }

    private function flushInsertBuffer(array &$rows, string $tableName, int &$totalInserted, int &$totalFailed, ?callable $afterBatch = null): void
    {
        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, self::DB_INSERT_BATCH_SIZE) as $batch) {
            $this->insertBatchWithFallback($batch, $tableName, $totalInserted, $totalFailed);

            if ($afterBatch) {
                $afterBatch();
            }
        }

        $rows = [];
    }

    private function insertBatchWithFallback(array $batch, string $tableName, int &$totalInserted, int &$totalFailed): void
    {
        if (empty($batch)) {
            return;
        }

        try {
            DB::table($tableName)->insert($batch);
            $totalInserted += count($batch);
            return;
        } catch (\Exception $e) {
            if (count($batch) <= self::FALLBACK_SPLIT_THRESHOLD) {
                foreach ($batch as $single) {
                    try {
                        DB::table($tableName)->insert($single);
                        $totalInserted++;
                    } catch (\Exception $rowException) {
                        $totalFailed++;
                    }
                }
                return;
            }
        }

        $midpoint = (int) ceil(count($batch) / 2);
        $leftBatch = array_slice($batch, 0, $midpoint);
        $rightBatch = array_slice($batch, $midpoint);

        $this->insertBatchWithFallback($leftBatch, $tableName, $totalInserted, $totalFailed);
        $this->insertBatchWithFallback($rightBatch, $tableName, $totalInserted, $totalFailed);
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

    private function normalizeDateValue($value): ?string
    {
        $value = ($value === null) ? '' : trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('Y-m-d');
            }

            $normalizedValue = str_replace('/', '-', $value);
            foreach (['d-m-Y', 'Y-m-d', 'd-m-y', 'Ymd'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $normalizedValue)->format('Y-m-d');
                } catch (\Throwable $e) {
                    // try next format
                }
            }

            return Carbon::parse($normalizedValue)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
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
            'TANGGAL',
            'TGL_REALISASI',
            'TGL_JATUH_TEMPO',
            'TANGGAL_MENUNGGAK',
            'TGL_BAYAR_TERAKHIR',
            'TGL_TERMINATE',
            'LAST_DATE_MAINTENANCE_BILLING',
            'NEXT_PMT_DATE',
            'NEXT_PMT_INT_DATE',
            'TGL_AKAD_RESTRUK',
        ];
        if (in_array($normalizedHeader, $dateColumns, true)) {
            return $this->normalizeDateValue($value);
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
            'TEXTBOX20',
            'TOTAL_KEWAJIBAN',
            'TUNGGAKAN_POKOK',
            'TUNGGAKAN_BUNGA',
            'TUNGGAKAN_PENALTI',
            'UMUR_TUNGGAKAN',
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
            'FREQ_PAYMENT',
            'FREQ_INT_PAYMENT',
            'JUMLAH_PN1',
            'JUMLAH_PN_ALL1',
            'RESTRUK_KE1',
            'PMTAMT',
            'PMTAMT_BASE',
            'TEXTBOX21',
            'OS_IDR',
            'OS_SEBELUM_KLAIM',
            'OS_PENUH_BERJALAN',
            'BILPRN',
            'BILINT',
            'BILLC',
        ];
        if (in_array($normalizedHeader, $decimalColumns, true)) {
            return $this->normalizeDecimalValue($value);
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

    private function resolveExcelTableName(): string
    {
        $tableName = 'daily_loan_dinamis';
        $idReport = session('active_id_report');

        if ($idReport) {
            $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
            if ($reportData && !empty($reportData->table_name)) {
                $tableName = $reportData->table_name;
            }
        }

        return $tableName;
    }

    private function orderPreviewColumns(array $headers, array $formattedUniqueValues, array $cleanPreview, string $tableName): array
    {
        $dbColumns = $tableName === 'daily_loan_dinamis'
            ? $this->getDailyLoanPreviewOrder()
            : Schema::getColumnListing($tableName);
        $matchedColumns = [];
        $unmatchedColumns = [];

        foreach ($headers as $filterIndex => $header) {
            $normalized = $this->normalizeImportColumnName($header);
            $columnMeta = [
                'display_header' => $this->resolvePreviewHeaderLabel($header, $tableName),
                'source_header' => $header,
                'filter_index' => $filterIndex,
                'db_column' => $normalized,
            ];

            if (in_array($normalized, $dbColumns, true)) {
                $matchedColumns[] = $columnMeta;
            } else {
                $unmatchedColumns[] = $columnMeta;
            }
        }

        usort($matchedColumns, function (array $left, array $right) use ($dbColumns) {
            return array_search($left['db_column'], $dbColumns, true) <=> array_search($right['db_column'], $dbColumns, true);
        });

        $orderedColumns = array_merge($matchedColumns, $unmatchedColumns);
        $orderedHeaders = [];
        $orderedUniqueValues = [];
        $displayFilterMap = [];

        foreach ($orderedColumns as $displayIndex => $columnMeta) {
            $orderedHeaders[] = $columnMeta['display_header'];
            $orderedUniqueValues[] = $formattedUniqueValues[$columnMeta['filter_index']] ?? [];
            $displayFilterMap[$displayIndex] = $columnMeta['filter_index'];
        }

        foreach ($cleanPreview as &$row) {
            $newRow = [];
            foreach ($orderedColumns as $columnMeta) {
                $displayHeader = $columnMeta['display_header'];
                $sourceHeader = $columnMeta['source_header'];
                $newRow[$displayHeader] = $row[$sourceHeader] ?? null;
            }
            $row = $newRow;
        }
        unset($row);

        return [
            'headers' => $orderedHeaders,
            'formatted_unique_values' => $orderedUniqueValues,
            'preview' => $cleanPreview,
            'display_filter_map' => $displayFilterMap,
        ];
    }

    private function buildExcelPreviewPayload(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $headerSearchLimit = 200;
        $previewLimit = 100;
        $uniqueLimit = 5000;
        $rowsToRead = $headerSearchLimit + $uniqueLimit + 100;

        $chunkFilter = new ChunkReadFilter();
        $chunkFilter->setRows(1, $rowsToRead);
        $reader->setReadFilter($chunkFilter);

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (empty($sheet)) {
            throw new \RuntimeException('Worksheet Excel kosong atau tidak dapat dibaca.');
        }

        $headerIndex = null;
        foreach ($sheet as $i => $row) {
            if ($i >= $headerSearchLimit) {
                break;
            }

            $rowUpper = array_map(fn ($v) => strtoupper(trim((string) $v)), $row);
            if (in_array('PERIODE', $rowUpper, true) || in_array('POSISI', $rowUpper, true)) {
                $headerIndex = $i;
                break;
            }
        }

        if ($headerIndex === null) {
            throw new \RuntimeException('Header utama (PERIODE / POSISI) tidak ditemukan dalam 200 baris pertama.');
        }

        $rawHeaders = $sheet[$headerIndex];
        $normalizedHeaders = [];
        foreach ($rawHeaders as $i => $header) {
            $normalizedHeaders[$i] = !empty(trim((string) $header)) ? trim((string) $header) : 'COL_' . $i;
        }

        $validIndexes = [];
        foreach ($normalizedHeaders as $i => $header) {
            if (!str_starts_with($header, 'COL_')) {
                $validIndexes[] = $i;
            }
        }

        $finalHeaders = [];
        foreach ($validIndexes as $i) {
            $finalHeaders[] = $normalizedHeaders[$i];
        }

        $headerCount = empty($normalizedHeaders) ? 0 : (max(array_keys($normalizedHeaders)) + 1);
        $cleanPreview = [];
        $uniqueValues = [];
        foreach ($validIndexes as $i) {
            $uniqueValues[$i] = [];
        }

        $rowsProcessedForUniques = 0;
        $totalAvailableRows = max(0, count($sheet) - ($headerIndex + 1));

        foreach ($sheet as $rowIndex => $row) {
            if ($rowIndex <= $headerIndex) {
                continue;
            }

            if (empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                continue;
            }

            $row = $this->padRow($row, $headerCount);
            $normalizedRow = [];

            foreach ($validIndexes as $i) {
                $normalizedRow[$i] = $this->normalizeExcelValue($normalizedHeaders[$i], $row[$i] ?? '');
            }

            if (count($cleanPreview) < $previewLimit) {
                $cleanRow = [];
                foreach ($validIndexes as $i) {
                    $cleanRow[$normalizedHeaders[$i]] = $normalizedRow[$i];
                }
                $cleanPreview[] = $cleanRow;
            }

            if ($rowsProcessedForUniques < $uniqueLimit) {
                foreach ($validIndexes as $i) {
                    $val = $normalizedRow[$i];
                    $uniqueValues[$i][$val === null ? '(Blank)' : $val] = true;
                }
                $rowsProcessedForUniques++;
            }

            if (count($cleanPreview) >= $previewLimit && $rowsProcessedForUniques >= $uniqueLimit) {
                break;
            }
        }

        $formattedUniqueValues = [];
        $filterIndex = 0;
        foreach ($validIndexes as $i) {
            $keys = array_keys($uniqueValues[$i] ?? []);
            usort($keys, 'strnatcmp');
            $formattedUniqueValues[$filterIndex] = $keys;
            $filterIndex++;
        }

        $filteredPreview = $this->stripIgnoredPreviewColumns(
            $finalHeaders,
            $cleanPreview,
            $formattedUniqueValues,
            $this->resolveExcelTableName()
        );

        $orderedPreview = $this->orderPreviewColumns(
            $filteredPreview['headers'],
            $filteredPreview['formatted_unique_values'],
            $filteredPreview['rows'],
            $this->resolveExcelTableName()
        );

        return [
            'headers' => $orderedPreview['headers'],
            'preview' => $orderedPreview['preview'],
            'formattedUniqueValues' => $orderedPreview['formatted_unique_values'],
            'displayFilterMap' => $orderedPreview['display_filter_map'],
            'header_index' => $headerIndex,
            'header_row' => $headerIndex + 1,
            'normalized_headers' => $filteredPreview['headers'],
            'rows_scanned' => min($rowsProcessedForUniques, $totalAvailableRows),
            'total_sample_rows' => $totalAvailableRows,
        ];
    }

    private function buildPreviewPayloadFromStagedCsv(string $csvPath): array
    {
        if (!file_exists($csvPath)) {
            throw new \RuntimeException('File stage CSV tidak ditemukan.');
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file stage CSV.');
        }

        try {
            $headers = $this->readCsvRecord($handle);
            if ($headers === false || empty($headers)) {
                throw new \RuntimeException('Header stage CSV tidak ditemukan.');
            }

            $previewLimit = 100;
            $uniqueLimit = 5000;
            $cleanPreview = [];
            $formattedUniqueValues = [];
            $rowsProcessedForUniques = 0;
            $totalAvailableRows = 0;

            foreach (array_keys($headers) as $index) {
                $formattedUniqueValues[$index] = [];
            }

            while (($row = $this->readCsvRecord($handle)) !== false) {
                if (empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                    continue;
                }

                $totalAvailableRows++;
                $row = $this->padRow($row, count($headers));
                $displayRow = [];

                foreach ($headers as $index => $header) {
                    $value = $row[$index] ?? null;
                    if ($value === '\N') {
                        $value = null;
                    }

                    $displayRow[$header] = $value;

                    if ($rowsProcessedForUniques < $uniqueLimit) {
                        $formattedUniqueValues[$index][$value === null || $value === '' ? '(Blank)' : (string) $value] = true;
                    }
                }

                if (count($cleanPreview) < $previewLimit) {
                    $cleanPreview[] = $displayRow;
                }

                if ($rowsProcessedForUniques < $uniqueLimit) {
                    $rowsProcessedForUniques++;
                }
            }

            foreach ($formattedUniqueValues as $index => $valuesMap) {
                $keys = array_keys($valuesMap);
                usort($keys, 'strnatcmp');
                $formattedUniqueValues[$index] = $keys;
            }

            $filteredPreview = $this->stripIgnoredPreviewColumns(
                $headers,
                $cleanPreview,
                $formattedUniqueValues,
                $this->resolveExcelTableName()
            );

            $orderedPreview = $this->orderPreviewColumns(
                $filteredPreview['headers'],
                $filteredPreview['formatted_unique_values'],
                $filteredPreview['rows'],
                $this->resolveExcelTableName()
            );

            return [
                'headers' => $orderedPreview['headers'],
                'preview' => $orderedPreview['preview'],
                'formattedUniqueValues' => $orderedPreview['formatted_unique_values'],
                'displayFilterMap' => $orderedPreview['display_filter_map'],
                'header_index' => 0,
                'header_row' => 1,
                'normalized_headers' => $filteredPreview['headers'],
                'rows_scanned' => min($rowsProcessedForUniques, $totalAvailableRows),
                'total_sample_rows' => $totalAvailableRows,
            ];
        } finally {
            fclose($handle);
        }
    }

    public function uploadExcel(Request $request)
    {
        $tableName = $this->resolveExcelTableName();
        $isDailyLoanCsv = $this->isDailyLoanTable($tableName);

        $request->validate([
            'file' => $isDailyLoanCsv
                ? 'required|file|mimes:csv,txt'
                : 'required|file|mimes:xlsx,xls',
        ]);
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

                $payload = null;
                $stagedCsvPath = null;
                $tableName = $this->resolveExcelTableName();

                if ($this->isDailyLoanTable($tableName)) {
                    $send('progress', ['percent' => 5, 'message' => 'Membaca header CSV Daily Loan Dinamis...', 'step' => 1]);
                    $payload = $this->buildPreviewPayloadFromCsvFile($path);
                    $stagedCsvPath = $path;
                    $send('progress', ['percent' => 70, 'message' => 'Header CSV ditemukan. Menyusun preview...', 'step' => 3]);
                } else {
                    $send('progress', ['percent' => 5, 'message' => 'Mendeteksi header Excel...', 'step' => 1]);
                    $pythonResult = $this->detectHeaderViaPython($path);

                    if ($pythonResult !== null) {
                        $normalizedHeaders = [];
                        foreach ((array) ($pythonResult['header_values'] ?? []) as $i => $h) {
                            $normalizedHeaders[$i] = !empty(trim((string) $h)) ? trim((string) $h) : 'COL_' . $i;
                        }

                        $send('progress', ['percent' => 20, 'message' => 'Mengonversi Excel ke CSV stage untuk preview/filter...', 'step' => 2]);
                        $stageResult = $this->stageExcelToCsv(
                            $send,
                            $path,
                            (int) $pythonResult['header_index'],
                            $normalizedHeaders,
                            $tableName
                        );

                        if ($stageResult !== null) {
                            $stagedCsvPath = $stageResult['staged_csv_path'];
                            $send('progress', ['percent' => 75, 'message' => 'CSV stage siap. Menyusun preview dari file perantara...', 'step' => 3]);
                            $payload = $this->buildPreviewPayloadFromStagedCsv($stagedCsvPath);
                        }
                    }

                    if ($payload === null) {
                        $send('progress', ['percent' => 25, 'message' => 'Fallback: membangun preview langsung dari Excel...', 'step' => 2]);
                        $payload = $this->buildExcelPreviewPayload($path);
                        $send('progress', ['percent' => 70, 'message' => 'Header ditemukan di baris ' . $payload['header_row'] . '. Menyusun preview...', 'step' => 3]);
                    }
                }

                $send('progress', ['percent' => 95, 'message' => 'Finalisasi preview...', 'step' => 5]);

                $cachePayload = [
                    'headers' => $payload['headers'],
                    'preview' => $payload['preview'],
                    'formattedUniqueValues' => $payload['formattedUniqueValues'],
                    'displayFilterMap' => $payload['displayFilterMap'],
                    'headerIndex' => $payload['header_index'] ?? null,
                    'normalizedHeaders' => $payload['normalized_headers'] ?? [],
                    'path' => urldecode($sessionPath),
                    'stagedCsvPath' => $stagedCsvPath,
                ];

                $useCacheKey = $cacheKey ?: ('excel_preview_' . md5(urldecode($sessionPath) . '|' . microtime(true)));
                Cache::put($useCacheKey, $cachePayload, now()->addMinutes(10));

                $send('ready', [
                    'redirect' => $activeIdReport === self::DAILY_LOAN_REPORT_ID
                        ? route('import.dailyloan.preview', ['ck' => $useCacheKey])
                        : ($activeIdReport === self::SIMPANAN_MULTIPN_REPORT_ID
                            ? route('import.simpanan.preview', ['ck' => $useCacheKey])
                            : route('import.excel.preview', ['ck' => $useCacheKey])),
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
        $importSource = (string) session('excel_import_source', '');
        $initRoute = $activeIdReport === self::DAILY_LOAN_REPORT_ID
            ? route('import.dailyloan.init')
            : ($activeIdReport === self::SIMPANAN_MULTIPN_REPORT_ID
                ? ($importSource === 'simpanan_csv' ? route('import.simpanan.csv.init') : route('import.simpanan.init'))
                : route('import.excel.init'));
        $streamRoute = $activeIdReport === self::DAILY_LOAN_REPORT_ID
            ? route('import.dailyloan.stream')
            : ($activeIdReport === self::SIMPANAN_MULTIPN_REPORT_ID
                ? ($importSource === 'simpanan_csv' ? route('import.simpanan.csv.stream') : route('import.simpanan.stream'))
                : route('import.excel.stream'));

        $ck = $request->query('ck');
        if ($ck) {
            $cached = Cache::get($ck);
            if ($cached && is_array($cached)) {
                $previewMeta = [
                    'path' => $cached['path'] ?? null,
                    'staged_csv_path' => $cached['stagedCsvPath'] ?? null,
                    'header_index' => isset($cached['headerIndex']) ? (int) $cached['headerIndex'] : null,
                    'normalized_headers' => (array) ($cached['normalizedHeaders'] ?? []),
                ];
                $previewStateKey = (string) $ck;
                $this->putExcelPreviewState($previewStateKey, [
                    'displayFilterMap' => $cached['displayFilterMap'] ?? [],
                    'previewMeta' => $previewMeta,
                ]);

                session([
                    'excel_display_filter_map' => $cached['displayFilterMap'] ?? [],
                    'excel_preview_meta' => $previewMeta,
                ]);

                if ($this->isDailyLoanTable()) {
                    return view('import.preview', [
                        'headers' => $cached['headers'] ?? [],
                        'previewData' => $this->buildLegacyPreviewRows($cached['headers'] ?? [], $cached['preview'] ?? []),
                        'formattedUniqueValues' => $cached['formattedUniqueValues'] ?? [],
                        'filePath' => $cached['path'] ?? null,
                        'currentDelimiter' => ',',
                        'lockDelimiterSelector' => true,
                        'fixedDelimiterLabel' => 'Koma ( , )',
                        'hideDelimiterCard' => true,
                        'processRoute' => '',
                        'backRoute' => route('import.index'),
                        'initRoute' => $initRoute,
                        'streamRoute' => $streamRoute,
                        'previewStateKey' => $previewStateKey,
                        'disableArea6AutoFilter' => true,
                        'forceAllFiltersCheckedOnLoad' => true,
                    ]);
                }

                if (!$this->isDailyLoanTable()) {
                    $cached['initRoute'] = $initRoute;
                    $cached['streamRoute'] = $streamRoute;
                    $cached['previewStateKey'] = $previewStateKey;
                    return view('import.preview_excel', $cached);
                }
            }
        }

        $sessionPath = session('excel_path', $request->path);
        if (!$sessionPath) return redirect()->route('import.index')->with('sweet_warning', ['title' => 'Sesi Berakhir', 'text' => 'Silakan upload ulang.']);

        $relativePath = urldecode($sessionPath);
        $path = Storage::path($relativePath);
        if (!file_exists($path)) return redirect()->route('import.index')->with('sweet_warning', ['title' => 'File Tidak Ditemukan', 'text' => 'File mungkin sudah terhapus.']);

        $previewMeta = session('excel_preview_meta', []);
        $stagedCsvPath = (string) ($previewMeta['staged_csv_path'] ?? '');
        if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
            $payload = $this->isDailyLoanTable()
                ? $this->buildPreviewPayloadFromCsvFile($stagedCsvPath)
                : $this->buildPreviewPayloadFromStagedCsv($stagedCsvPath);
        } else {
            $payload = $this->isDailyLoanTable()
                ? $this->buildPreviewPayloadFromCsvFile($path)
                : $this->buildExcelPreviewPayload($path);
        }
        session([
            'excel_display_filter_map' => $payload['displayFilterMap'],
            'excel_preview_meta' => [
                'path' => $relativePath,
                'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                'header_index' => $payload['header_index'] ?? null,
                'normalized_headers' => (array) ($payload['normalized_headers'] ?? []),
            ],
        ]);
        $previewStateKey = Str::random(32);
        $this->putExcelPreviewState($previewStateKey, [
            'displayFilterMap' => $payload['displayFilterMap'],
            'previewMeta' => [
                'path' => $relativePath,
                'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                'header_index' => $payload['header_index'] ?? null,
                'normalized_headers' => (array) ($payload['normalized_headers'] ?? []),
            ],
        ]);

        if ($this->isDailyLoanTable()) {
            return view('import.preview', [
                'headers' => $payload['headers'],
                'previewData' => $this->buildLegacyPreviewRows($payload['headers'], $payload['preview']),
                'formattedUniqueValues' => $payload['formattedUniqueValues'],
                'filePath' => $relativePath,
                'currentDelimiter' => ',',
                'lockDelimiterSelector' => true,
                'fixedDelimiterLabel' => 'Koma ( , )',
                'hideDelimiterCard' => true,
                'processRoute' => '',
                'backRoute' => route('import.index'),
                'initRoute' => $initRoute,
                'streamRoute' => $streamRoute,
                'previewStateKey' => $previewStateKey,
                'disableArea6AutoFilter' => true,
                'forceAllFiltersCheckedOnLoad' => true,
            ]);
        }

        return view('import.preview_excel', [
            'headers' => $payload['headers'],
            'preview' => $payload['preview'],
            'formattedUniqueValues' => $payload['formattedUniqueValues'],
            'path' => $relativePath,
            'initRoute' => $initRoute,
            'streamRoute' => $streamRoute,
            'previewStateKey' => $previewStateKey,
        ]);
    }

    /**
     * Deteksi header Excel menggunakan Python (openpyxl read-only, cepat).
     * Return array dengan header_index, total_rows, dan header_values (nama kolom),
     * atau null jika Python tidak tersedia / gagal.
     */
    private function detectHeaderViaPython(string $path): ?array
    {
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

        $idReport  = session('active_id_report');
        $tableName = $this->resolveExcelTableName();

        // Pastikan schema minimum tersedia agar import tidak terlihat "sukses"
        // padahal kolom penting untuk report belum ada di database.
        if ($tableName === 'daily_loan_dinamis' && !Schema::hasColumn($tableName, 'baki_debet1')) {
            return response()->json([
                'status' => 'error',
                'text' => 'Kolom wajib `baki_debet1` belum tersedia di tabel daily_loan_dinamis. Jalankan migration terlebih dahulu lalu upload ulang file CSV.',
            ], 422);
        }

        $previewState = $this->getExcelPreviewState($request->input('preview_state_key'));
        $previewMeta = !empty($previewState['previewMeta'])
            ? (array) $previewState['previewMeta']
            : session('excel_preview_meta', []);
        $previewPath = urldecode((string) ($previewMeta['path'] ?? ''));
        $stagedCsvPath = (string) ($previewMeta['staged_csv_path'] ?? '');
        $previewHeaders = (array) ($previewMeta['normalized_headers'] ?? []);

        if ($previewPath === $relativePath && !empty($previewHeaders) && array_key_exists('header_index', $previewMeta)) {
            $headerIndex = (int) $previewMeta['header_index'];
            $activeFilters = json_decode($request->active_filters_json ?? '{}', true) ?: [];
            $displayFilterMap = !empty($previewState['displayFilterMap'])
                ? (array) $previewState['displayFilterMap']
                : session('excel_display_filter_map', []);
            $normalizedActiveFilters = [];

            foreach ($activeFilters as $displayIndex => $values) {
                $mappedIndex = $displayFilterMap[$displayIndex] ?? $displayIndex;
                $normalizedActiveFilters[(int) $mappedIndex] = array_values((array) $values);
            }

            ksort($normalizedActiveFilters);

            $jobId = DB::table('import_jobs')->insertGetId([
                'id_report'     => $idReport,
                'file_name'     => basename($path),
                'folder_path'   => dirname($path),
                'status'        => 'processing',
                'total_files'   => 0,
                'total_success' => 0,
                'total_failed'  => 0,
                'created_by'    => auth()->id() ?? 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            session([
                'excel_headers'        => $previewHeaders,
                'excel_preview_meta'   => [
                    'path' => $relativePath,
                    'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                    'header_index' => $headerIndex,
                    'normalized_headers' => $previewHeaders,
                ],
                'excel_import_params'  => [
                    'header_index'   => $headerIndex,
                    'table_name'     => $tableName,
                    'file_path'      => $relativePath,
                    'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                    'active_filters' => $normalizedActiveFilters,
                    'job_id'         => $jobId,
                ],
            ]);
            $this->putExcelImportJobState($jobId, [
                'params' => [
                    'header_index'   => $headerIndex,
                    'table_name'     => $tableName,
                    'file_path'      => $relativePath,
                    'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                    'active_filters' => $normalizedActiveFilters,
                    'job_id'         => $jobId,
                ],
                'headers' => $previewHeaders,
            ]);

            return response()->json([
                'status'       => 'success',
                'job_id'       => $jobId,
                'total_rows'   => 0,
                'header_index' => $headerIndex,
                'table_name'   => $tableName,
                'file_path'    => $relativePath,
            ]);
        }

        // ── Coba Python dulu (openpyxl read-only, jauh lebih cepat) ──────────
        $headerIndex = null;
        $totalRows   = 0;
        $sheet       = null;

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

            foreach ($sheet as $i => $row) {
                $rowUpper = array_map(fn($v) => strtoupper(trim((string) $v)), $row);
                if (in_array('PERIODE', $rowUpper) || in_array('POSISI', $rowUpper)) {
                    $headerIndex = $i;
                    break;
                }
            }

            if ($headerIndex === null) {
                return response()->json(['status' => 'error', 'text' => 'Header tidak ditemukan (PERIODE / POSISI).']);
            }

            $worksheetInfo = $reader->listWorksheetInfo($path);
            $totalRows     = $worksheetInfo[0]['totalRows'];
        }

        if ($headerIndex === null) {
            return response()->json(['status' => 'error', 'text' => 'Header tidak ditemukan (PERIODE / POSISI).']);
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
        $displayFilterMap = !empty($previewState['displayFilterMap'])
            ? (array) $previewState['displayFilterMap']
            : session('excel_display_filter_map', []);
        $normalizedActiveFilters = [];

        foreach ($activeFilters as $displayIndex => $values) {
            $mappedIndex = $displayFilterMap[$displayIndex] ?? $displayIndex;
            $normalizedActiveFilters[(int) $mappedIndex] = array_values((array) $values);
        }

        ksort($normalizedActiveFilters);
        session([
            'excel_headers'        => $normalizedHeadersForSession,
            'excel_preview_meta'   => [
                'path' => $relativePath,
                'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                'header_index' => $headerIndex,
                'normalized_headers' => $normalizedHeadersForSession,
            ],
            'excel_import_params'  => [
                'header_index'   => $headerIndex,
                'table_name'     => $tableName,
                'file_path'      => $relativePath,
                'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                'active_filters' => $normalizedActiveFilters,
                'job_id'         => $jobId,
            ],
        ]);
        $this->putExcelImportJobState($jobId, [
            'params' => [
                'header_index'   => $headerIndex,
                'table_name'     => $tableName,
                'file_path'      => $relativePath,
                'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                'active_filters' => $normalizedActiveFilters,
                'job_id'         => $jobId,
            ],
            'headers' => $normalizedHeadersForSession,
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

    private function stageExcelToCsv(
        callable $send,
        string $sourcePath,
        int $headerIndex,
        array $normalizedHeaders,
        string $tableName
    ): ?array {
        $pythonExe = $this->findPython();
        $scriptPath = base_path('scripts/excel_gpu_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $stagedCsvPath = $this->createStagedCsvPath($tableName);
        $configData = [
            'file_path' => $sourcePath,
            'header_index' => $headerIndex,
            'normalized_headers' => $normalizedHeaders,
            'output_csv_path' => $stagedCsvPath,
        ];

        $configFile = storage_path('app/excel_stage_config_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode($configData, JSON_UNESCAPED_UNICODE));

        $cmd = escapeshellarg($pythonExe)
            . ' ' . escapeshellarg($scriptPath)
            . ' --config ' . escapeshellarg($configFile)
            . ' --mode stage';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            @unlink($configFile);
            @unlink($stagedCsvPath);
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $lastKeepAlive = time();
        $keepAliveEvery = 15;
        $pythonProducedOutput = false;
        $donePayload = null;
        $pythonError = null;

        $processLine = function (string $line) use ($send, &$lastKeepAlive, &$donePayload, &$pythonError) {
            $line = trim($line);
            if ($line === '') {
                return;
            }

            $data = json_decode($line, true);
            if (!$data) {
                return;
            }

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'progress') {
                $send('progress', $data);
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'done') {
                $donePayload = $data;
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'error') {
                $pythonError = $data['message'] ?? 'Python staging error tidak diketahui';
                $lastKeepAlive = time();
            }
        };

        while (true) {
            $status = proc_get_status($process);
            $chunk = fread($pipes[1], 65536);

            if ($chunk !== false && $chunk !== '') {
                $pythonProducedOutput = true;
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $processLine($line);
                }
            }

            if ((time() - $lastKeepAlive) >= $keepAliveEvery) {
                echo ": keepalive\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                $lastKeepAlive = time();
            }

            if (!$status['running']) {
                break;
            }

            usleep(50000);
        }

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

        if (!$pythonProducedOutput || $pythonError !== null || !$donePayload || !file_exists($stagedCsvPath)) {
            @unlink($stagedCsvPath);
            return null;
        }

        return [
            'staged_csv_path' => $stagedCsvPath,
            'total_rows' => (int) ($donePayload['total_rows'] ?? 0),
        ];
    }

    private function tryPythonBulkLoad(
        callable $send,
        string $path,
        int $headerIndex,
        string $tableName,
        array $activeFilters,
        array $normalizedHeaders,
        int $jobId
    ): bool {
        $pythonExe = $this->findPython();
        $scriptPath = base_path('scripts/excel_gpu_processor.py');

        if (!$pythonExe || !file_exists($scriptPath) || !$this->supportsNativeBulkLoad()) {
            return false;
        }

        $bulkLoadColumns = $this->buildBulkLoadColumns($tableName, $normalizedHeaders, $activeFilters);
        $csvTempPath = $this->createBulkLoadTempCsvPath($tableName, $jobId);

        $configData = [
            'file_path' => $path,
            'header_index' => $headerIndex,
            'table_name' => $tableName,
            'active_filters' => $activeFilters,
            'normalized_headers' => $normalizedHeaders,
            'table_columns' => $bulkLoadColumns,
            'output_csv_path' => $csvTempPath,
            'load_columns' => $bulkLoadColumns,
        ];

        $configFile = storage_path('app/excel_gpu_config_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode($configData, JSON_UNESCAPED_UNICODE));

        $cmd = escapeshellarg($pythonExe)
            . ' ' . escapeshellarg($scriptPath)
            . ' --config ' . escapeshellarg($configFile);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $gpuEnv = [
            'CUDA_VISIBLE_DEVICES' => '',
            'ROCR_VISIBLE_DEVICES' => '',
            'MLU_VISIBLE_DEVICES' => '',
            'ASCEND_VISIBLE_DEVICES' => '',
            'HIP_VISIBLE_DEVICES' => '',
        ];
        $procEnv = array_merge((getenv() ?: $_ENV ?: []), $gpuEnv);

        $process = proc_open($cmd, $descriptors, $pipes, null, $procEnv);
        if (!is_resource($process)) {
            @unlink($configFile);
            @unlink($csvTempPath);
            return false;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $lastKeepAlive = time();
        $keepAliveEvery = 15;
        $pythonProducedOutput = false;
        $doneSent = false;
        $pythonError = null;
        $csvRowsPrepared = 0;
        $csvReadyPath = null;

        $processLine = function (string $line) use (
            $send, &$lastKeepAlive, &$doneSent, &$pythonError, &$csvRowsPrepared, &$csvReadyPath
        ) {
            $line = trim($line);
            if ($line === '') {
                return;
            }

            $data = json_decode($line, true);
            if (!$data) {
                return;
            }

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'done') {
                $doneSent = true;
                $csvRowsPrepared = (int) ($data['total_rows'] ?? 0);
                $csvReadyPath = $data['csv_path'] ?? null;
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'progress') {
                $send('progress', $data);
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'error') {
                $pythonError = $data['message'] ?? 'Python error tidak diketahui';
                $lastKeepAlive = time();
            }
        };

        while (true) {
            $status = proc_get_status($process);
            $chunk = fread($pipes[1], 65536);

            if ($chunk !== false && $chunk !== '') {
                $pythonProducedOutput = true;
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $processLine($line);
                }
            }

            if ((time() - $lastKeepAlive) >= $keepAliveEvery) {
                echo ": keepalive\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                $lastKeepAlive = time();
            }

            if (!$status['running']) {
                break;
            }

            usleep(50000);
        }

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

        if (!$pythonProducedOutput) {
            @unlink($csvTempPath);
            Log::warning('Python CSV export produced no output; fallback to legacy import path.');
            return false;
        }

        if ($pythonError !== null) {
            @unlink($csvReadyPath ?: $csvTempPath);
            Log::warning('Python CSV export failed; fallback to legacy import path. Error: ' . $pythonError);
            return false;
        }

        if (!$doneSent || !$csvReadyPath || !file_exists($csvReadyPath)) {
            @unlink($csvTempPath);
            Log::warning('Python CSV export finished without valid CSV file; fallback to legacy import path.');
            return false;
        }

        try {
            $send('progress', [
                'percent' => 96,
                'message' => 'CSV sementara siap. Memuat data ke MySQL dengan LOAD DATA LOCAL INFILE...',
                'rows_done' => $csvRowsPrepared,
                'total' => $csvRowsPrepared,
                'speed' => 0,
            ]);

            $inserted = $this->loadCsvIntoMysql($csvReadyPath, $tableName, $bulkLoadColumns);
            $failed = max(0, $csvRowsPrepared - $inserted);

            if ($jobId > 0) {
                DB::table('import_jobs')->where('id', $jobId)->update([
                    'total_success' => $inserted,
                    'total_failed' => $failed,
                    'status' => ($inserted > 0 || $csvRowsPrepared === 0) ? 'completed' : 'failed',
                    'updated_at' => now(),
                ]);
            }

            $send('complete', [
                'total_success' => $inserted,
                'total_failed' => $failed,
                'total_rows' => $csvRowsPrepared,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('LOAD DATA LOCAL INFILE failed; fallback to legacy import path. Error: ' . $e->getMessage());
            return false;
        } finally {
            @unlink($csvReadyPath);
        }
    }

    private function processStagedCsvStream(
        callable $send,
        string $csvPath,
        string $tableName,
        array $activeFilters,
        array $normalizedHeaders,
        int $jobId
    ): bool {
        if ($csvPath === '' || !file_exists($csvPath)) {
            return false;
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return false;
        }
        $delimiter = $this->detectCsvDelimiter($csvPath);
        $estimatedTotalRows = $this->countCsvDataRows($csvPath);

        $bulkLoadColumns = $this->buildBulkLoadColumns($tableName, $normalizedHeaders, $activeFilters);
        $outputCsvPath = $this->createBulkLoadTempCsvPath($tableName, $jobId);
        $outputHandle = fopen($outputCsvPath, 'w');

        if ($outputHandle === false) {
            fclose($handle);
            return false;
        }

        try {
            $headers = $this->readCsvRecord($handle, $delimiter);
            if ($headers === false) {
                return false;
            }

            $context = $this->buildImportContext($tableName, $normalizedHeaders, $activeFilters);
            $rowsDone = 0;
            $startTime = microtime(true);
            $lastProgressAt = 0;
            $timestamp = now()->toDateTimeString();

            if ($jobId > 0) {
                DB::table('import_jobs')->where('id', $jobId)->update([
                    'total_files' => $estimatedTotalRows,
                    'updated_at' => now(),
                ]);
            }

            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                $row = $this->padRow($row, $context['header_count']);
                foreach ($row as $index => $value) {
                    if ($value === '\N') {
                        $row[$index] = null;
                    }
                }

                $finalRow = $this->mapExcelRowForInsert($row, $normalizedHeaders, $context, $timestamp);
                if ($finalRow === null) {
                    continue;
                }

                fputcsv($outputHandle, array_map(function ($column) use ($finalRow) {
                    $value = $finalRow[$column] ?? null;
                    return $value === null ? '\N' : $value;
                }, $bulkLoadColumns));

                $rowsDone++;

                if ($rowsDone - $lastProgressAt >= self::STREAM_PROGRESS_EVERY) {
                    $lastProgressAt = $rowsDone;
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) ($rowsDone / $elapsed);
                    $percent = $estimatedTotalRows > 0
                        ? min(95, 10 + (int) (($rowsDone / $estimatedTotalRows) * 80))
                        : 90;
                    $send('progress', [
                        'percent' => $percent,
                        'message' => 'Memfilter data dari CSV stage... (' . $speed . ' baris/detik)',
                        'rows_done' => $rowsDone,
                        'total' => $estimatedTotalRows,
                        'speed' => $speed,
                    ]);
                }
            }

            fclose($outputHandle);
            $outputHandle = null;

            $send('progress', [
                'percent' => 96,
                'message' => 'CSV hasil filter siap. Memuat data ke MySQL...',
                'rows_done' => $rowsDone,
                'total' => $estimatedTotalRows > 0 ? $estimatedTotalRows : $rowsDone,
                'speed' => 0,
            ]);

            $inserted = $this->loadCsvIntoMysql($outputCsvPath, $tableName, $bulkLoadColumns);
            $failed = max(0, $rowsDone - $inserted);

            if ($jobId > 0) {
                DB::table('import_jobs')->where('id', $jobId)->update([
                    'total_success' => $inserted,
                    'total_failed' => $failed,
                    'status' => ($inserted > 0 || $rowsDone === 0) ? 'completed' : 'failed',
                    'updated_at' => now(),
                ]);
            }

            $send('complete', [
                'total_success' => $inserted,
                'total_failed' => $failed,
                'total_rows' => $rowsDone,
            ]);

            return true;
        } finally {
            fclose($handle);
            if (is_resource($outputHandle)) {
                fclose($outputHandle);
            }
            @unlink($outputCsvPath);
        }
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
                    if ($colLower === strtolower($importContext['unique_id_col'])) {
                        $clean[$importContext['unique_id_col']] = $val;
                        continue;
                    }
                    if (!isset($importContext['table_columns_lookup'][$colLower])) {
                        continue;
                    }
                    $clean[$colLower] = $val;
                }

                if (!isset($clean[$importContext['unique_id_col']])) {
                    $clean[$importContext['unique_id_col']] = uniqid('', true) . $importContext['suffix'];
                }
                if (!isset($clean['created_at'])) {
                    $clean['created_at'] = $timestamp;
                }
                if (!isset($clean['updated_at'])) {
                    $clean['updated_at'] = $timestamp;
                }
                if (count($clean) > 3) {
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

        $sessionParams = session('excel_import_params', []);
        $jobId         = (int) ($sessionParams['job_id'] ?? $request->job_id ?? 0);
        $jobState      = $this->getExcelImportJobState($jobId);
        $params        = !empty($jobState['params']) ? (array) $jobState['params'] : $sessionParams;
        $normalizedHeaders = !empty($jobState['headers']) ? (array) $jobState['headers'] : session('excel_headers', []);

        $headerIndex   = (int) ($params['header_index'] ?? 0);
        $tableName     = $params['table_name']     ?? 'daily_loan_dinamis';
        $activeFilters = $params['active_filters'] ?? [];
        $relativePath  = $params['file_path']      ?? '';
        $stagedCsvPath = $params['staged_csv_path'] ?? '';

        request()->session()->save();

        return response()->stream(function () use (
            $jobId, $headerIndex, $tableName, $activeFilters, $relativePath, $normalizedHeaders, $stagedCsvPath
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

                if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
                    $send('progress', [
                        'percent'   => 3,
                        'message'   => 'Menggunakan CSV stage sebagai sumber preview/filter/import...',
                        'rows_done' => 0,
                        'total'     => 0,
                        'speed'     => 0,
                    ]);

                    if ($this->processStagedCsvStream($send, $stagedCsvPath, $tableName, $activeFilters, $normalizedHeaders, $jobId)) {
                        if ($jobId > 0) {
                            $job = DB::table('import_jobs')->where('id', $jobId)->first();
                            if ($job && $job->status === 'completed') {
                                $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $path, [$stagedCsvPath]);
                            }
                        }
                        return;
                    }
                }

                // ── Coba Python CPU Processor terlebih dahulu ─────────────────
                $send('progress', [
                    'percent'   => 3,
                    'message'   => 'Menyiapkan pandas -> CSV temp -> LOAD DATA LOCAL INFILE...',
                    'rows_done' => 0,
                    'total'     => 0,
                    'speed'     => 0,
                ]);

                $pythonHandled = $this->tryPythonBulkLoad(
                    $send, $path, $headerIndex, $tableName,
                    $activeFilters, $normalizedHeaders, $jobId
                );

                if ($pythonHandled) {
                    if ($jobId > 0) {
                        $job = DB::table('import_jobs')->where('id', $jobId)->first();
                        if ($job && $job->status === 'completed') {
                            $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $path, $stagedCsvPath !== '' ? [$stagedCsvPath] : []);
                            if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
                                @unlink($stagedCsvPath);
                            }
                        }
                    }
                    return;
                }

                $send('progress', [
                    'percent'   => 5,
                    'message'   => 'Bulk load native tidak dipakai. Fallback ke import Python/PHP lama...',
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
                            $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $path);
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

                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'total_files' => $totalDataRows,
                        'updated_at' => now(),
                    ]);
                }

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
                $progressEvery  = self::STREAM_PROGRESS_EVERY;
                $startTime      = microtime(true);
                $lastProgressAt = 0;

                $flushBatch = function () use (
                    &$dataToInsert, &$totalInserted, &$totalFailed, $tableName, $ping
                ) {
                    if (empty($dataToInsert)) return;
                    foreach (array_chunk($dataToInsert, self::DB_INSERT_BATCH_SIZE) as $batch) {
                        $this->insertBatchWithFallback($batch, $tableName, $totalInserted, $totalFailed);
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

                        if (count($dataToInsert) >= self::INSERT_BUFFER_FLUSH_SIZE) {
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
                    $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $path, $stagedCsvPath !== '' ? [$stagedCsvPath] : []);
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
