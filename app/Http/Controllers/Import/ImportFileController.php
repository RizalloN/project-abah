<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Import\Concerns\AllocatesGapIds;
use App\Http\Controllers\Import\Concerns\SmartCsvImportSupport;
use App\Services\Import\MySqlBulkLoadService;
use App\Services\Import\SchemaIntrospectionService;
use App\Support\ReportDataSyncService;
use App\Support\StrictDateParser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon; 

class ImportFileController extends Controller
{
    use AllocatesGapIds;
    use SmartCsvImportSupport;

    private ?array $dailyLoanColumnsCache = null;
    private array $timestampSupportCache = [];
    
    // OPTIMIZATION: Cache for expensive operations across import session
    private array $decimalNormalizationCache = [];
    private array $delimiterDetectionCache = [];
    private array $headerIndexCache = [];
    private ?bool $bomAlreadyStripped = null;

    private function schemaService(): SchemaIntrospectionService
    {
        return app(SchemaIntrospectionService::class);
    }

    private function bulkLoadService(): MySqlBulkLoadService
    {
        return app(MySqlBulkLoadService::class);
    }

    private const SAFE_MEMORY_LIMIT = '512M';
    private const PREVIEW_SAMPLE_LIMIT = 1200;
    private const PREVIEW_UNIQUE_SCAN_LIMIT = 4000;
    private const PREVIEW_UNIQUE_LIMIT_PER_COLUMN = 400;
    private const LARGE_FILE_THRESHOLD_BYTES = 150 * 1024 * 1024;
    private const LARGE_FILE_PREVIEW_SAMPLE_LIMIT = 100;
    private const LARGE_FILE_PREVIEW_UNIQUE_SCAN_LIMIT = 100;
    private const LARGE_FILE_PREVIEW_UNIQUE_LIMIT_PER_COLUMN = 80;
    private const DAILY_LOAN_PREVIEW_SAMPLE_LIMIT = 150;
    private const DAILY_LOAN_PREVIEW_UNIQUE_SCAN_LIMIT = 150;
    private const DAILY_LOAN_PREVIEW_UNIQUE_LIMIT_PER_COLUMN = 40;
    private const IMPORT_BATCH_SIZE = 1000;
    private const DAILY_LOAN_IMPORT_BATCH_SIZE = 250;
    private const BULK_LOAD_TEMP_DIR = 'app/import_bulk';

    private function shouldUseDbStagingFastPath(): bool
    {
        return (bool) config('import.use_db_staging_fast_path', false);
    }

    private function cachedSchemaHasTable(string $tableName): bool
    {
        return $this->schemaService()->hasTable($tableName);
    }

    private function cachedSchemaColumnListing(string $tableName): array
    {
        return $this->schemaService()->getColumnListing($tableName);
    }

    private function cachedSchemaHasColumn(string $tableName, string $columnName): bool
    {
        return $this->schemaService()->hasColumn($tableName, $columnName);
    }

    private function parseIniSizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $bytes = (int) $value;

        return match ($unit) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => (int) $value,
        };
    }

    private function applySafeRuntimeLimits(bool $streaming = false): void
    {
        ini_set('memory_limit', self::SAFE_MEMORY_LIMIT);
        ini_set('auto_detect_line_endings', '1');
        ini_set('max_execution_time', $streaming ? '0' : '300');
    }

    private function previewFilterCacheKey(string $filePath, string $delimiter, int $columnIndex, string $tableName): string
    {
        return 'preview_filter_options:' . md5($filePath . '|' . $delimiter . '|' . $columnIndex . '|' . $tableName);
    }

    private const BRILINK_SUMMARY_HEADERS = [
        'PERIODE', 'FLAG', 'KANWIL', 'KODE_KANCA', 'CABANG',
        'KODE_UKER', 'UKER', 'MERCHANT_NAME', 'MERCHANT_CODE',
        'OUTLET_NAME', 'OUTLET_CODE', 'TOTAL_TRANSAKSI',
        'TOTAL_NOMINAL', 'TOTAL_FEE', 'TOTAL_FEE_BRI',
    ];

    private const NUMERIC_COLUMNS = [
        'SALDO_POSISI', 'RATAS_SALDO', 'SALDO_POSISI_BY_CIF', 'RATAS_SALDO_BY_CIF',
        'SALES_VOLUME', 'AKUMULASI_SALES_VOLUME', 'JML_TRANSAKSI', 'AKUMULASI_TRANSAKSI',
        'NILAI', 'MERCHANT_QRIS_VOLUME', 'MERCHANT_QRIS', 'BAKI_DEBET',
    ];

    private function isDailyLoanReport($reportData): bool
    {
        if (!$reportData) {
            return false;
        }

        return strtolower((string) ($reportData->table_name ?? '')) === 'daily_loan_dinamis'
            || str_contains(strtolower((string) ($reportData->nama_report ?? '')), 'daily loan');
    }

    private function readCsvRecord($handle, string $delimiter)
    {
        $line = fgets($handle);
        if ($line === false) {
            return false;
        }

        // Use smartParseCsvLine to avoid double-processing quotes (fgetcsv already unquotes)
        $row = $this->smartParseCsvLine((string) $line, $delimiter, false);
        
        // OPTIMIZATION: Strip BOM only once per file on first record (tracked by bomAlreadyStripped)
        if (!empty($row) && $this->bomAlreadyStripped !== true) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($row[0] ?? ''));
            // Mark as processed so we don't check again
            $this->bomAlreadyStripped = true;
        }

        return $row !== [] ? $row : false;
    }

    private function normalizeDailyLoanHeader(string $header): string
    {
        $normalizedHeader = preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($header)));

        return match ($normalizedHeader) {
            'TEXTBOX20', 'TOTAL_KEWAJIBAN' => 'total_kewajiban',
            'TEXTBOX21', 'OS_IDR' => 'os_idr',
            default => strtolower(str_replace(' ', '_', trim($header))),
        };
    }

    private function resolveDailyLoanPreviewLabel(string $header): string
    {
        return match ($this->normalizeDailyLoanHeader($header)) {
            'total_kewajiban' => 'Total Kewajiban',
            'os_idr' => 'OS IDR',
            default => str_replace('_', ' ', trim($header)) === trim($header) ? trim($header) : trim($header),
        };
    }

    private function getDailyLoanPreviewOrder(): array
    {
        return [
            'periode','kode_kanwil1','kanwil1','kode_cabang1','cabang1','branch1','unit1','curtyp','ao_name','cifno',
            'nomor_rekening1','status_rekening1','ln_type','nama_debitur1','rate','jangka_waktu1','plafon','baki_debet1',
            'ckpn','nilai_tercatat1','kol_adk1','kolek_detail','kolek','kolektabilitas_lancar','kolektabilitas_dpk',
            'kolektabilitas_kuranglancar','kolektabilitas_diragukan','kolektabilitas_macet','total_kewajiban',
            'tunggakan_pokok','tunggakan_bunga','tunggakan_penalti','umur_tunggakan','tgl_realisasi','tgl_jatuh_tempo',
            'tanggal_menunggak','tgl_bayar_terakhir','tgl_terminate','last_date_maintenance_billing','next_pmt_date',
            'next_pmt_int_date','advance_payment','bap','payment_amount','final_payment_amount','npb_pokok_la',
            'npb_pokok_lf','npb_bunga_la','npb_bunga_lf','jml_angsuran1','jumlah_bayar','deffered_bunga',
            'sai_tunggakan','sai_deffered','sai1','freq_payment','freq_int_payment','jadwal_gp_pokok','pn_pengelola1',
            'pn_name1','pn_pemrakarsa1','pn_referral1','pn_restruk1','pn_pengelola2','pn_pemutus1','pn_crm1','pn_crr',
            'pn_referral_naik_kelas1','jumlah_pn1','jumlah_pn_all1','code','description','kecamatan_t_tinggal',
            'kelurahan_t_tinggal','kodepos_t_tinggal','kecamatan_t_usaha','kelurahan_t_usaha','kodepos_t_usaha',
            'segmen_dashboard','produk_dashboard','divisi_segmen_dashboard','npl_method','restruk_ke1','jenis_restruk1',
            'tgl_akad_restruk','flag_restruk','flag_restruk_covid1','flag_commodity_chain1','flag_briguna_digital1',
            'flag_agf','flag_aft','pmtamt','pmtamt_base','offcr','lbdotu','keterangan_pn_pengelola','os_idr',
            'flag_klaim','os_sebelum_klaim','os_penuh_berjalan','bilprn','bilint','billc',
        ];
    }

    private function normalizeDailyLoanDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = str_replace('/', '-', $value);

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $matches) === 1) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) === 1) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{2})$/', $value, $matches) === 1) {
            $year = (int) $matches[3];
            $year += $year >= 70 ? 1900 : 2000;

            return $year . '-' . $matches[2] . '-' . $matches[1];
        }

        try {
            foreach (['d-m-Y', 'Y-m-d', 'd-m-y'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $value)->format('Y-m-d');
                } catch (\Throwable $e) {
                }
            }

            return StrictDateParser::normalize($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isDailyLoanDateColumn(string $column): bool
    {
        return in_array($column, [
            'tgl_realisasi','tgl_jatuh_tempo','tanggal_menunggak','tgl_bayar_terakhir',
            'tgl_terminate','last_date_maintenance_billing','next_pmt_date','next_pmt_int_date','tgl_akad_restruk',
        ], true);
    }

    private function normalizeImportPeriodValue($value, $fallbackPosisi = null, $fallbackTahun = null): ?string
    {
        $value = trim((string) $value);
        if ($value !== '') {
            $normalized = str_replace('/', '-', $value);

            if (preg_match('/^\d{4}-\d{2}$/', $normalized) === 1) {
                return $normalized;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) === 1) {
                return substr($normalized, 0, 7);
            }

            foreach (['F Y', 'M Y', 'F-Y', 'M-Y', 'Y-m'] as $format) {
                try {
                    $parsed = Carbon::createFromFormat($format, $normalized)->startOfMonth()->format('Y-m');
                    // FIX #3: Validate output format before returning
                    if (preg_match('/^\d{4}-\d{2}$/', $parsed) === 1) {
                        return $parsed;
                    }
                } catch (\Throwable $e) {
                }
            }

            try {
                $parsed = Carbon::parse($normalized)->startOfMonth()->format('Y-m');
                // Validate output format before returning
                if (preg_match('/^\d{4}-\d{2}$/', $parsed) === 1) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
            }
        }

        $fallbackPosisi = trim((string) $fallbackPosisi);
        if ($fallbackPosisi !== '') {
            try {
                $parsed = Carbon::parse($fallbackPosisi)->startOfMonth()->format('Y-m');
                // Validate output format before returning
                if (preg_match('/^\d{4}-\d{2}$/', $parsed) === 1) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
            }
        }

        $fallbackTahun = trim((string) $fallbackTahun);
        if (preg_match('/^\d{4}$/', $fallbackTahun) === 1) {
            $result = $fallbackTahun . '-01';
            // Validate output format
            if (preg_match('/^\d{4}-\d{2}$/', $result) === 1) {
                return $result;
            }
        }

        return null;
    }

    private function buildInitialArea6Selections(array $headers, array $formattedUniqueValues, array $columnHints): array
    {
        $area6Values = array_fill_keys(array_map('strtoupper', [
            'KC PONOROGO',
            'KC NGAWI',
            'KC MAGETAN',
            'KC MADIUN',
            'PONOROGO',
            'NGAWI',
            'MAGETAN',
            'MADIUN',
        ]), true);

        $normalizedHints = array_values(array_filter(array_map('strtoupper', (array) $columnHints), static fn ($hint): bool => $hint !== ''));
        if (empty($normalizedHints)) {
            return [];
        }

        $selections = [];
        foreach ($headers as $index => $header) {
            $headerText = strtoupper((string) $header);
            if ($headerText === '' || str_contains($headerText, 'KODE')) {
                continue;
            }

            $matchedHint = false;
            foreach ($normalizedHints as $hint) {
                if (str_contains($headerText, $hint)) {
                    $matchedHint = true;
                    break;
                }
            }

            if (!$matchedHint) {
                continue;
            }

            $values = array_map('trim', (array) ($formattedUniqueValues[$index] ?? []));
            $selected = array_values(array_filter($values, static function (string $value) use ($area6Values): bool {
                return $value !== '' && isset($area6Values[strtoupper($value)]);
            }));

            if (!empty($selected)) {
                $selections[(string) $index] = $selected;
            }
        }

        return $selections;
    }

    private function isDailyLoanNumericColumn(string $column): bool
    {
        return in_array($column, [
            'rate','plafon','baki_debet1','ckpn','nilai_tercatat1','kolektabilitas_lancar','kolektabilitas_dpk',
            'kolektabilitas_kuranglancar','kolektabilitas_diragukan','kolektabilitas_macet','total_kewajiban',
            'tunggakan_pokok','tunggakan_bunga','tunggakan_penalti','umur_tunggakan','advance_payment','bap',
            'payment_amount','final_payment_amount','npb_pokok_la','npb_pokok_lf','npb_bunga_la','npb_bunga_lf',
            'jml_angsuran1','jumlah_bayar','deffered_bunga','sai_tunggakan','sai_deffered','sai1','freq_payment',
            'freq_int_payment','jumlah_pn1','jumlah_pn_all1','restruk_ke1','pmtamt','pmtamt_base','os_idr',
            'os_sebelum_klaim','os_penuh_berjalan','bilprn','bilint','billc',
        ], true);
    }

    private function buildColumnImportBlueprint(array $selectedColumns, array $csvHeaders): array
    {
        $blueprint = [];

        foreach ($selectedColumns as $index) {
            if (!isset($csvHeaders[$index])) {
                continue;
            }

            $colName = str_replace(' ', '_', $csvHeaders[$index]);
            $normalizedColName = $this->normalizeDailyLoanHeader($colName);

            if ($normalizedColName === 'id' || $normalizedColName === 'uniqueid_namareport') {
                continue;
            }

            $type = 'string';
            if ($normalizedColName === 'periode') {
                $type = 'period';
            } elseif ($this->isDailyLoanDateColumn($normalizedColName)) {
                $type = 'date';
            } elseif ($this->isDailyLoanNumericColumn($normalizedColName) || in_array(strtoupper($colName), self::NUMERIC_COLUMNS, true)) {
                $type = 'numeric';
            }

            $blueprint[] = [
                'index' => (int) $index,
                'column' => $normalizedColName,
                'type' => $type,
            ];
        }

        return $blueprint;
    }

    private function applyDailyLoanCompatibilityColumns(array $rowData): array
    {
        if ($this->dailyLoanColumnsCache === null) {
            $this->dailyLoanColumnsCache = array_fill_keys($this->cachedSchemaColumnListing('daily_loan_dinamis'), true);
        }

        $compatibilityMap = [
            'total_kewajiban' => 'textbox20',
            'os_idr' => 'textbox21',
        ];

        foreach ($compatibilityMap as $source => $target) {
            if (!isset($this->dailyLoanColumnsCache[$target])) {
                continue;
            }
            if (!array_key_exists($source, $rowData)) {
                continue;
            }
            if (!array_key_exists($target, $rowData) || $rowData[$target] === null || $rowData[$target] === '') {
                $rowData[$target] = $rowData[$source];
            }
        }

        return $rowData;
    }

    private function applyImportTimestamps(array $rowData, string $tableName): array
    {
        if (!isset($this->timestampSupportCache[$tableName])) {
            $this->timestampSupportCache[$tableName] = [
                'created_at' => $this->cachedSchemaHasColumn($tableName, 'created_at'),
                'updated_at' => $this->cachedSchemaHasColumn($tableName, 'updated_at'),
            ];
        }

        $now = now();

        if (
            $this->timestampSupportCache[$tableName]['created_at']
            && (!array_key_exists('created_at', $rowData) || $rowData['created_at'] === null || $rowData['created_at'] === '')
        ) {
            $rowData['created_at'] = $now;
        }

        if (
            $this->timestampSupportCache[$tableName]['updated_at']
            && (!array_key_exists('updated_at', $rowData) || $rowData['updated_at'] === null || $rowData['updated_at'] === '')
        ) {
            $rowData['updated_at'] = $now;
        }

        return $rowData;
    }

    private function prepareDailyLoanPreview(array $headers, array $previewRows, array $uniqueValues): array
    {
        $displayMap = range(0, max(count($headers) - 1, 0));
        $displayHeaders = array_map(function ($header) {
            return $this->resolveDailyLoanPreviewLabel((string) $header);
        }, $headers);

        $orderedUniqueValues = [];
        foreach ($displayMap as $displayIndex => $sourceIndex) {
            $orderedUniqueValues[$displayIndex] = $uniqueValues[$sourceIndex] ?? [];
        }

        return [
            'headers' => $displayHeaders,
            'previewData' => $previewRows,
            'formattedUniqueValues' => $orderedUniqueValues,
            'displayToSourceMap' => $displayMap,
        ];
    }

    private function getActiveReportData()
    {
        $idReport = (int) session('active_id_report', 1);

        return DB::table('nama_report')->where('id_report', $idReport)->first();
    }

    private function isBrilinkSummaryReport($reportData): bool
    {
        if (!$reportData) {
            return false;
        }

        return stripos($reportData->nama_report, 'BRILINK Web - Laporan Summary Transaksi') !== false
            || stripos($reportData->nama_report, 'brilink_web') !== false;
    }

    private function resolveTableName($reportData): string
    {
        $tableName = 'jumlah_merchant_detail';

        if ($reportData) {
            if (!empty($reportData->table_name)) {
                $tableName = $reportData->table_name;
            } else {
                $tableName = strtolower(str_replace(' ', '_', $reportData->nama_report));
            }
        }

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return 'jumlah_merchant_detail';
        }

        return $tableName;
    }

    private function resolveUniqueSuffix(string $tableName): string
    {
        if ($tableName === 'jumlah_merchant_qris_detail') {
            return '_JMQD';
        }

        if ($tableName === 'sv_merchant') {
            return '_SVMer';
        }

        if ($tableName === 'merchant_qris') {
            return '_MQ';
        }

        if ($tableName === 'merchant_qris_volume') {
            return '_MQV';
        }

        if ($tableName === 'brilink_web_laporan_summary_transaksi_brilink_web') {
            return '_BST';
        }

        return '_MDT';
    }

    private function resolveDelimiter($handle, string $currentDelimiter): string
    {
        if ($currentDelimiter !== 'auto') {
            return $currentDelimiter;
        }

        $meta = stream_get_meta_data($handle);
        $path = $meta['uri'] ?? null;
        if (is_string($path) && $path !== '' && is_file($path)) {
            // OPTIMIZATION: Cache delimiter detection by file path to avoid re-scanning
            if (!isset($this->delimiterDetectionCache[$path])) {
                $this->delimiterDetectionCache[$path] = $this->smartDetectCsvDelimiter($path, [',', ';', "\t", '|', '.']);
            }
            return $this->delimiterDetectionCache[$path];
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            return ',';
        }

        $bestDelimiter = ',';
        $bestCount = -1;
        foreach ([',', ';', '|', "\t", '.'] as $delimiter) {
            $count = count($this->smartParseCsvLine((string) $firstLine, $delimiter, true));
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    private function parseCsvRow(
        array $data,
        bool $isBrilinkSummary,
        array $headers,
        int $posisiIndex,
        int $tahunIndex
    ): ?array {
        if (empty($data) || implode('', $data) === '') {
            return null;
        }

        if (trim((string) ($data[0] ?? '')) === 'TAHUN' || stripos(trim((string) ($data[0] ?? '')), 'textbox') !== false) {
            return null;
        }

        if ($isBrilinkSummary) {
            return $this->transformBrilinkSummaryRow($data);
        }

        if (count($data) < count($headers)) {
            $data = array_pad($data, count($headers), null);
        }

        if (count($data) > count($headers)) {
            return null;
        }

        if ($posisiIndex !== -1 && isset($data[$posisiIndex]) && trim((string) $data[$posisiIndex]) !== '') {
            $rawPosisi = trim((string) $data[$posisiIndex]);

            try {
                if (strpos($rawPosisi, '/') !== false) {
                    $data[$posisiIndex] = StrictDateParser::normalize($rawPosisi);
                } elseif ($tahunIndex !== -1 && isset($data[$tahunIndex]) && trim((string) $data[$tahunIndex]) !== '') {
                    $rawTahun = trim((string) $data[$tahunIndex]);
                    if (preg_match('/^([a-zA-Z]+\s+\d+)/', $rawPosisi, $matches)) {
                        $data[$posisiIndex] = StrictDateParser::normalize($matches[1] . ' ' . $rawTahun);
                    } else {
                        $data[$posisiIndex] = StrictDateParser::normalize($rawPosisi);
                    }
                } else {
                    $data[$posisiIndex] = StrictDateParser::normalize($rawPosisi);
                }
            } catch (\Exception $e) {
            }
        }

        return $data;
    }

    private function formatCsvHeaders(array $data, bool $isBrilinkSummary): array
    {
        if ($isBrilinkSummary) {
            return self::BRILINK_SUMMARY_HEADERS;
        }

        return array_map(function ($val) {
            return trim(preg_replace('/[\xef\xbb\xbf]/', '', (string) $val));
        }, $data);
    }

    private function cleanupImportDirectory(string $filePath): void
    {
        $importDir = dirname(dirname($filePath));

        if (strpos($importDir, 'imports') !== false && File::exists($importDir)) {
            File::deleteDirectory($importDir);
        }
    }

    private function resolveImportBatchSize(string $tableName): int
    {
        return strtolower($tableName) === 'daily_loan_dinamis'
            ? self::DAILY_LOAN_IMPORT_BATCH_SIZE
            : self::IMPORT_BATCH_SIZE;
    }

    private function supportsNativeBulkLoad(): bool
    {
        return $this->bulkLoadService()->supportsNativeBulkLoad();
    }

    private function createBulkLoadTempCsvPath(string $tableName, int $jobId): string
    {
        return $this->bulkLoadService()->createBulkLoadTempCsvPath(storage_path(self::BULK_LOAD_TEMP_DIR), $tableName, $jobId);
    }

    private function loadCsvIntoMysql(string $csvPath, string $tableName, array $columns): int
    {
        return $this->bulkLoadService()->loadCsvIntoMysql($csvPath, $tableName, $columns);
    }

    private function isTransientMysqlLoadError(\Throwable $e): bool
    {
        return $this->bulkLoadService()->isTransientMysqlLoadError($e);
    }

    private function countFileLines(string $path): int
    {
        return $this->bulkLoadService()->countFileLines($path);
    }

    private function loadCsvIntoMysqlChunked(
        string $csvPath,
        string $tableName,
        array $columns,
        ?callable $onProgress = null,
        int $chunkLines = 8000,
        ?int $totalLines = null
    ): int {
        return $this->bulkLoadService()->loadCsvIntoMysqlChunked(
            $csvPath,
            $tableName,
            $columns,
            $onProgress,
            $chunkLines,
            $totalLines
        );
    }

    private function buildBulkLoadColumnsForMappedRows(string $tableName, bool $isBrilinkSummary, array $columnBlueprint): array
    {
        if (!$this->cachedSchemaHasTable($tableName)) {
            return [];
        }

        $tableColumns = $this->cachedSchemaColumnListing($tableName);
        $lookupByLower = [];
        foreach ($tableColumns as $column) {
            $lookupByLower[strtolower((string) $column)] = (string) $column;
        }

        $requested = ['uniqueid_namareport'];

        if ($isBrilinkSummary) {
            $requested = array_merge($requested, [
                'periode',
                'kanwil',
                'cabang',
                'uker',
                'merchant_name',
                'merchant_code',
                'outlet_name',
                'outlet_code',
                'total_transaksi',
                'total_nominal',
                'total_fee',
                'total_fee_bri',
            ]);
        } else {
            foreach ($columnBlueprint as $columnMeta) {
                $columnName = strtolower((string) ($columnMeta['column'] ?? ''));
                if ($columnName !== '') {
                    $requested[] = $columnName;
                }
            }
            $requested[] = 'textbox20';
            $requested[] = 'textbox21';
        }

        $requested[] = 'created_at';
        $requested[] = 'updated_at';

        $columns = [];
        foreach (array_values(array_unique($requested)) as $column) {
            $lower = strtolower((string) $column);
            if (isset($lookupByLower[$lower])) {
                $columns[] = $lookupByLower[$lower];
            }
        }

        return $columns;
    }

    private function mapRowValuesForBulkLoad(array $row, array $columns): array
    {
        $lowerRow = [];
        foreach ($row as $key => $value) {
            $lowerRow[strtolower((string) $key)] = $value;
        }

        $result = [];
        foreach ($columns as $column) {
            $result[] = $lowerRow[strtolower((string) $column)] ?? null;
        }

        return $result;
    }

    private function fallbackInsertFromBulkCsv(
        string $csvPath,
        array $columns,
        string $tableName,
        int &$totalSuccess,
        int &$totalFailed,
        string &$lastErrorMsg,
        ?int $batchSize = null
    ): void {
        $handle = @fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka CSV temp untuk fallback insert.');
        }

        $buffer = [];

        try {
            while (($csvRow = fgetcsv($handle, 0, ',')) !== false) {
                $assoc = [];
                foreach ($columns as $index => $columnName) {
                    $value = $csvRow[$index] ?? null;
                    $assoc[$columnName] = ($value === '\\N') ? null : $value;
                }

                $buffer[] = $assoc;
                if ($batchSize !== null && count($buffer) >= $batchSize) {
                    $this->flushInsertBuffer($buffer, $tableName, $totalSuccess, $totalFailed, $lastErrorMsg, null, $batchSize);
                }
            }

            if (!empty($buffer)) {
                $this->flushInsertBuffer($buffer, $tableName, $totalSuccess, $totalFailed, $lastErrorMsg, null, $batchSize);
            }
        } finally {
            fclose($handle);
        }
    }

    private function createCsvStagingTable(string $prefix, int $jobId, int $columnCount): string
    {
        $columnCount = max(1, $columnCount);
        $tableName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $prefix . '_' . $jobId . '_' . Str::random(8)));
        $tableName = substr($tableName, 0, 60);

        $columnsSql = ['`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'];
        for ($i = 0; $i < $columnCount; $i++) {
            $columnsSql[] = "`c{$i}` LONGTEXT NULL";
        }

        DB::statement("CREATE TABLE `{$tableName}` (" . implode(', ', $columnsSql) . ') ENGINE=InnoDB');

        return $tableName;
    }

    private function dropCsvStagingTable(?string $tableName): void
    {
        if (!$tableName) {
            return;
        }

        try {
            DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
        } catch (\Throwable $e) {
            Log::warning('Failed to drop CSV staging table: ' . $e->getMessage(), [
                'table' => $tableName,
            ]);
        }
    }

    private function loadCsvIntoStagingTable(
        string $csvPath,
        string $stagingTable,
        int $columnCount,
        string $delimiter,
        int $ignoreLines = 1
    ): int {
        if (!file_exists($csvPath)) {
            throw new \RuntimeException('File CSV sumber tidak ditemukan untuk staging.');
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
        $quotedDelimiter = $pdo->quote($delimiter);
        $quotedColumns = implode(', ', array_map(static function (int $index): string {
            return "`c{$index}`";
        }, range(0, max(0, $columnCount - 1))));

        $sql = "LOAD DATA LOCAL INFILE {$quotedPath} INTO TABLE `{$stagingTable}` "
            . "CHARACTER SET utf8mb4 "
            . "FIELDS TERMINATED BY {$quotedDelimiter} ENCLOSED BY '\"' "
            . "LINES TERMINATED BY '\\n' "
            . 'IGNORE ' . max(0, $ignoreLines) . " LINES "
            . "({$quotedColumns})";

        $affected = $pdo->exec($sql);
        $pdo = null;

        if ($affected === false) {
            throw new \RuntimeException('LOAD DATA LOCAL INFILE ke staging table gagal.');
        }

        return (int) $affected;
    }

    private function processImportStreamViaStagingTable(
        callable $send,
        string $filePath,
        string $resolvedDelimiter,
        array $selectedColumns,
        array $activeFilters,
        string $tableName,
        string $uniqueSuffix,
        bool $isBrilinkSummary,
        array $csvHeaders,
        int $posisiIndex,
        int $tahunIndex,
        int $totalRows,
        array &$duplicateLookup,
        int &$duplicateSkipped,
        int &$totalSuccess,
        int &$totalFailed,
        string &$lastErrorMsg,
        int $jobId,
        array $columnBlueprint,
        int $batchSize
    ): bool {
        if (!$this->supportsNativeBulkLoad()) {
            return false;
        }

        $headerCount = max(1, count($csvHeaders));
        $bulkColumns = $this->buildBulkLoadColumnsForMappedRows($tableName, $isBrilinkSummary, $columnBlueprint);
        if (empty($bulkColumns)) {
            return false;
        }

        $stagingTable = null;
        $bulkCsvPath = '';
        $bulkHandle = null;
        $rowsDone = 0;
        $lastProgressAt = 0;
        $startTime = microtime(true);

        $shouldInsertRow = function (array $row) use ($tableName, &$duplicateLookup, &$duplicateSkipped) {
            if (!$this->isJumlahMerchantDetailTable($tableName)) {
                return true;
            }

            $duplicateKey = $this->extractJumlahMerchantDuplicateKey($row);
            if ($duplicateKey === null) {
                return true;
            }

            [$periode, $tid] = $duplicateKey;
            $lookupKey = $periode . '|' . $tid;

            if (isset($duplicateLookup[$lookupKey])) {
                $duplicateSkipped++;
                return false;
            }

            $duplicateLookup[$lookupKey] = true;
            return true;
        };

        try {
            $send('progress', [
                'percent' => 14,
                'message' => 'Mode cepat aktif: direct load ke staging table...',
                'rows_done' => 0,
                'total' => $totalRows,
                'speed' => 0,
            ]);

            $stagingTable = $this->createCsvStagingTable('tmp_file_csv_stage', $jobId, $headerCount);
            $this->loadCsvIntoStagingTable($filePath, $stagingTable, $headerCount, $resolvedDelimiter, 1);

            $bulkCsvPath = $this->createBulkLoadTempCsvPath($tableName, max(0, $jobId));
            $bulkHandle = @fopen($bulkCsvPath, 'w');
            if ($bulkHandle === false) {
                return false;
            }

            $lastId = 0;
            $requiredIndexes = [];
            if ($isBrilinkSummary) {
                $requiredIndexes = range(0, 14);
            } else {
                $requiredIndexes = array_values(array_unique(array_map('intval', array_merge(
                    $selectedColumns,
                    array_map('intval', array_keys($activeFilters)),
                    [$posisiIndex, $tahunIndex]
                ))));
                $requiredIndexes = array_values(array_filter($requiredIndexes, static fn (int $idx): bool => $idx >= 0 && $idx < $headerCount));
            }

            if (empty($requiredIndexes)) {
                $requiredIndexes = range(0, max(0, $headerCount - 1));
            }

            // OPTIMIZATION: Calculate chunk size based on column count to respect MySQL placeholder limits
            $numColumns = count($requiredIndexes);
            $maxRowsPerQuery = (int) floor(65000 / max(1, $numColumns));
            $chunkSize = min(10000, $maxRowsPerQuery);  // Use 10k or max safe rows, whichever is smaller

            $stagingSelectColumns = array_merge(
                ['id'],
                array_map(static fn (int $idx): string => 'c' . $idx, $requiredIndexes)
            );

            // Cache blueprint outside loop for performance (avoid rebuild per row)
            $cachedBlueprint = !$isBrilinkSummary ? ($columnBlueprint ?? $this->buildColumnImportBlueprint($selectedColumns, $csvHeaders)) : null;
            
            // Pre-allocate row template to reduce per-row array operations
            $rowTemplate = array_fill(0, $headerCount, null);
            
            // Static CSV formatter for bulk fputcsv operations
            $csvFormatter = static function ($value): string {
                return $value === null ? '\N' : $value;
            };

            // OPTIMIZATION: Cache header lower-case lookups for filter rules (avoid repeated strtolower/trim)
            $cachedLowercaseHeaders = [];
            foreach ($csvHeaders as $idx => $header) {
                $cachedLowercaseHeaders[$idx] = strtolower(trim((string) $header));
            }

            $sqlSafeFilterRules = [];
            foreach ($activeFilters as $filterIdx => $allowedValuesLookup) {
                $filterIndex = (int) $filterIdx;
                if ($filterIndex < 0 || $filterIndex >= $headerCount) {
                    continue;
                }

                // OPTIMIZATION: Use pre-cached lowercase header instead of recalculating
                $header = $cachedLowercaseHeaders[$filterIndex] ?? '';
                if (
                    str_contains($header, 'posisi')
                    || str_contains($header, 'tanggal')
                    || str_contains($header, 'tgl')
                    || str_contains($header, 'date')
                    || str_contains($header, 'periode')
                    || str_contains($header, 'tahun')
                ) {
                    continue;
                }

                $values = array_map(static fn ($v) => trim((string) $v), array_keys((array) $allowedValuesLookup));
                $includeBlank = in_array('(Blank)', $values, true) || in_array('', $values, true);
                $values = array_values(array_filter($values, static function (string $value): bool {
                    if ($value === '' || $value === '(Blank)') {
                        return false;
                    }

                    return preg_match('/^[\pL\pN\s\.\,\-\/_]+$/u', $value) === 1;
                }));

                if (empty($values) && !$includeBlank) {
                    continue;
                }

                $sqlSafeFilterRules[] = [
                    'column' => 'c' . $filterIndex,
                    'values' => $values,
                    'include_blank' => $includeBlank,
                ];
            }

            while (true) {
                $query = DB::table($stagingTable)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->select($stagingSelectColumns);

                foreach ($sqlSafeFilterRules as $filterRule) {
                    $query->where(function ($where) use ($filterRule) {
                        $hasBaseCondition = false;

                        if (!empty($filterRule['values'])) {
                            $where->whereIn($filterRule['column'], $filterRule['values']);
                            $hasBaseCondition = true;
                        }

                        if (!empty($filterRule['include_blank'])) {
                            if ($hasBaseCondition) {
                                $where->orWhereNull($filterRule['column'])
                                    ->orWhere($filterRule['column'], '');
                            } else {
                                $where->whereNull($filterRule['column'])
                                    ->orWhere($filterRule['column'], '');
                            }
                        }
                    });
                }

                $chunk = $query->limit($chunkSize)->get();

                if ($chunk->isEmpty()) {
                    break;
                }

            // OPTIMIZATION: Convert chunk to arrays once to avoid dynamic property access in loop
            // OPTIMIZATION: Pre-compute column keys to avoid string concatenation in inner loop
            $columnKeys = array_map(static fn(int $i): string => 'c' . $i, range(0, $headerCount - 1));
            
            foreach ($chunk as $record) {
                $recordArray = (array) $record;
                $lastId = (int) ($recordArray['id'] ?? 0);
                
                // OPTIMIZATION: Use proper array copy with spread operator (ensures deep copy)
                $row = [...$rowTemplate];
                for ($i = 0; $i < $headerCount; $i++) {
                    // OPTIMIZATION: Direct array access with pre-computed keys (avoids string concatenation per row)
                    $value = $recordArray[$columnKeys[$i]] ?? null;
                    // OPTIMIZATION: Avoid rtrim() overhead - only trim if value ends with \r
                    $row[$i] = (is_string($value) && str_ends_with($value, "\r")) 
                        ? substr($value, 0, -1) 
                        : $value;
                }

                    // OPTIMIZATION: Inline passesActiveFilters check if table has no active filters
                    $parsedRow = $this->parseCsvRow($row, $isBrilinkSummary, $csvHeaders, $posisiIndex, $tahunIndex);
                    if ($parsedRow === null) {
                        continue;
                    }
                    
                    // Skip filter check if no filters are active (common case)
                    if (!empty($activeFilters) && !$this->passesActiveFilters($parsedRow, $activeFilters)) {
                        continue;
                    }

                    // Use cached blueprint for non-Brilink imports
                    $blueprintToUse = !$isBrilinkSummary ? $cachedBlueprint : null;
                    $mappedRow = $this->mapRowForInsert(
                        $parsedRow,
                        $selectedColumns,
                        $csvHeaders,
                        $isBrilinkSummary,
                        $uniqueSuffix,
                        $blueprintToUse,
                        $posisiIndex,
                        $tahunIndex
                    );
                    if ($mappedRow === null) {
                        continue;
                    }

                    $mappedRow = $this->applyImportTimestamps($mappedRow, $tableName);
                    if (!$shouldInsertRow($mappedRow)) {
                        continue;
                    }

                    $bulkValues = $this->mapRowValuesForBulkLoad($mappedRow, $bulkColumns);
                    fputcsv($bulkHandle, array_map($csvFormatter, $bulkValues));

                    $rowsDone++;
                }

                if ($rowsDone - $lastProgressAt >= 500) {
                    $lastProgressAt = $rowsDone;
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) ($rowsDone / $elapsed);
                    $percent = $totalRows > 0
                        ? min(95, 12 + (int) (($rowsDone / $totalRows) * 83))
                        : 80;

                    $send('progress', [
                        'percent' => $percent,
                        'message' => "Memfilter data dari staging table... ({$speed} baris/detik)",
                        'rows_done' => $rowsDone,
                        'total' => $totalRows,
                        'speed' => $speed,
                    ]);
                }
            }

            fclose($bulkHandle);
            $bulkHandle = null;

            $send('progress', [
                'percent' => 96,
                'message' => 'Staging selesai. Memuat data ke MySQL...',
                'rows_done' => $rowsDone,
                'total' => $totalRows > 0 ? $totalRows : $rowsDone,
                'speed' => 0,
            ]);

            try {
                $inserted = $this->loadCsvIntoMysqlChunked(
                    $bulkCsvPath,
                    $tableName,
                    $bulkColumns,
                    function (int $processedLines, int $totalLines) use ($send, $rowsDone, $totalRows): void {
                        $ratio = $totalLines > 0 ? min(1, $processedLines / $totalLines) : 1;
                        $percent = 96 + (int) floor($ratio * 3);
                        $send('progress', [
                            'percent' => min(99, $percent),
                            'message' => 'Memuat data ke MySQL (chunked)...',
                            'rows_done' => $rowsDone,
                            'total' => $totalRows > 0 ? $totalRows : $rowsDone,
                            'speed' => 0,
                        ]);
                    },
                    8000,
                    $totalRows
                );
                $totalSuccess = $inserted;
                $totalFailed = max(0, $rowsDone - $inserted);
            } catch (\Throwable $e) {
                Log::warning('LOAD DATA LOCAL INFILE gagal setelah staging table, fallback ke insert batch: ' . $e->getMessage(), [
                    'table' => $tableName,
                    'job_id' => $jobId,
                ]);
                $lastErrorMsg = mb_substr($e->getMessage(), 0, 800) . '...';
                $this->fallbackInsertFromBulkCsv(
                    $bulkCsvPath,
                    $bulkColumns,
                    $tableName,
                    $totalSuccess,
                    $totalFailed,
                    $lastErrorMsg,
                    $batchSize
                );
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('CSV staging-table fast path failed in ImportFileController; fallback to legacy path. Error: ' . $e->getMessage(), [
                'table' => $tableName,
                'job_id' => $jobId,
            ]);
            return false;
        } finally {
            if (is_resource($bulkHandle)) {
                fclose($bulkHandle);
            }
            if ($bulkCsvPath !== '' && file_exists($bulkCsvPath)) {
                @unlink($bulkCsvPath);
            }
            $this->dropCsvStagingTable($stagingTable);
        }
    }

    private function processImportStreamViaStrictLocalInfile(
        callable $send,
        string $filePath,
        string $resolvedDelimiter,
        array $selectedColumns,
        array $activeFilters,
        string $tableName,
        string $uniqueSuffix,
        bool $isBrilinkSummary,
        array $csvHeaders,
        int $posisiIndex,
        int $tahunIndex,
        int $totalRows,
        array &$duplicateLookup,
        int &$duplicateSkipped,
        int &$totalSuccess,
        int &$totalFailed,
        string &$lastErrorMsg,
        int $jobId,
        array $columnBlueprint
    ): bool {
        $bulkColumns = $this->buildBulkLoadColumnsForMappedRows($tableName, $isBrilinkSummary, $columnBlueprint);
        if (empty($bulkColumns)) {
            $lastErrorMsg = 'Kolom bulk load kosong untuk tabel tujuan.';
            return false;
        }

        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            $lastErrorMsg = 'File CSV tidak dapat dibaca.';
            return false;
        }

        $bulkCsvPath = $this->createBulkLoadTempCsvPath($tableName, max(0, $jobId));
        $bulkHandle = @fopen($bulkCsvPath, 'w');
        if ($bulkHandle === false) {
            fclose($handle);
            $lastErrorMsg = 'Gagal membuat file staging CSV sementara.';
            return false;
        }

        $rowCounter = 0;
        $rowsDone = 0;
        $lastProgressAt = 0;
        $startTime = microtime(true);
        $progressStep = strtolower($tableName) === 'daily_loan_dinamis' ? 1000 : 2000;

        // Cache CSV formatter to avoid closure creation per row
        $csvFormatter = static function ($value): string {
            return $value === null ? '\N' : $value;
        };

        $shouldInsertRow = function (array $row) use ($tableName, &$duplicateLookup, &$duplicateSkipped) {
            if (!$this->isJumlahMerchantDetailTable($tableName)) {
                return true;
            }

            $duplicateKey = $this->extractJumlahMerchantDuplicateKey($row);
            if ($duplicateKey === null) {
                return true;
            }

            [$periode, $tid] = $duplicateKey;
            $lookupKey = $periode . '|' . $tid;

            if (isset($duplicateLookup[$lookupKey])) {
                $duplicateSkipped++;
                return false;
            }

            $duplicateLookup[$lookupKey] = true;
            return true;
        };

        try {
            while (($data = $this->readCsvRecord($handle, $resolvedDelimiter)) !== false) {
                if (empty($data) || implode('', $data) === '') {
                    continue;
                }

                if ($rowCounter === 0) {
                    $rowCounter++;
                    continue;
                }

                $parsedRow = $this->parseCsvRow($data, $isBrilinkSummary, $csvHeaders, $posisiIndex, $tahunIndex);
                if ($parsedRow === null || !$this->passesActiveFilters($parsedRow, $activeFilters)) {
                    $rowCounter++;
                    continue;
                }

                $mappedRow = $this->mapRowForInsert(
                    $parsedRow,
                    $selectedColumns,
                    $csvHeaders,
                    $isBrilinkSummary,
                    $uniqueSuffix,
                    $columnBlueprint,
                    $posisiIndex,
                    $tahunIndex
                );
                if ($mappedRow === null) {
                    $rowCounter++;
                    continue;
                }

                $mappedRow = $this->applyImportTimestamps($mappedRow, $tableName);
                if (!$shouldInsertRow($mappedRow)) {
                    $rowCounter++;
                    continue;
                }

                $bulkValues = $this->mapRowValuesForBulkLoad($mappedRow, $bulkColumns);
                // Use cached formatter (avoid closure recreation per row)
                fputcsv($bulkHandle, array_map($csvFormatter, $bulkValues));

                $rowsDone++;
                $rowCounter++;

                if ($rowsDone - $lastProgressAt >= $progressStep) {
                    $lastProgressAt = $rowsDone;
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) ($rowsDone / $elapsed);
                    $percent = $totalRows > 0
                        ? min(95, 12 + (int) (($rowsDone / $totalRows) * 83))
                        : 80;

                    $send('progress', [
                        'percent' => $percent,
                        'message' => "Menyiapkan CSV untuk LOAD DATA LOCAL INFILE... ({$speed} baris/detik)",
                        'rows_done' => $rowsDone,
                        'total' => $totalRows,
                        'speed' => $speed,
                    ]);
                }
            }

            fclose($bulkHandle);
            $bulkHandle = null;
            fclose($handle);
            $handle = null;

            $send('progress', [
                'percent' => 96,
                'message' => 'CSV hasil filter siap. Memuat data ke MySQL via LOCAL INFILE...',
                'rows_done' => $rowsDone,
                'total' => $totalRows > 0 ? $totalRows : $rowsDone,
                'speed' => 0,
            ]);

            $inserted = $this->loadCsvIntoMysqlChunked(
                $bulkCsvPath,
                $tableName,
                $bulkColumns,
                function (int $processedLines, int $totalLines) use ($send, $rowsDone, $totalRows): void {
                    $ratio = $totalLines > 0 ? min(1, $processedLines / $totalLines) : 1;
                    $percent = 96 + (int) floor($ratio * 3);
                    $send('progress', [
                        'percent' => min(99, $percent),
                        'message' => 'Memuat data ke MySQL (LOCAL INFILE chunked)...',
                        'rows_done' => $rowsDone,
                        'total' => $totalRows > 0 ? $totalRows : $rowsDone,
                        'speed' => 0,
                    ]);
                },
                8000,
                $totalRows
            );

            $totalSuccess = $inserted;
            $totalFailed = max(0, $rowsDone - $inserted);

            return true;
        } catch (\Throwable $e) {
            $lastErrorMsg = mb_substr($e->getMessage(), 0, 800) . '...';
            Log::warning('Strict LOCAL INFILE stream failed: ' . $e->getMessage(), [
                'table' => $tableName,
                'job_id' => $jobId,
            ]);
            return false;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_resource($bulkHandle)) {
                fclose($bulkHandle);
            }
            if (file_exists($bulkCsvPath)) {
                @unlink($bulkCsvPath);
            }
        }
    }

    private function isJumlahMerchantDetailTable(string $tableName): bool
    {
        return strtolower($tableName) === 'jumlah_merchant_detail';
    }

    private function extractJumlahMerchantDuplicateKey(array $rowData): ?array
    {
        $periode = trim((string) ($rowData['PERIODE'] ?? ''));
        $tid = trim((string) ($rowData['TID'] ?? ''));

        if ($periode === '' || $tid === '') {
            return null;
        }

        return [$periode, $tid];
    }

    private function buildJumlahMerchantDuplicateLookup(array $periodeTidPairs): array
    {
        $normalizedPairs = [];
        $periods = [];

        foreach ($periodeTidPairs as $pair) {
            $periode = trim((string) ($pair['periode'] ?? ''));
            $tid = trim((string) ($pair['tid'] ?? ''));
            if ($periode === '' || $tid === '') {
                continue;
            }

            $normalizedPairs[$periode . '|' . $tid] = true;
            $periods[$periode] = true;
        }

        if (empty($normalizedPairs) || empty($periods)) {
            return [];
        }

        $lookup = [];
        $existingRows = DB::table('jumlah_merchant_detail')
            ->select(['PERIODE', 'TID'])
            ->whereIn('PERIODE', array_keys($periods))
            ->whereNotNull('PERIODE')
            ->whereNotNull('TID')
            ->get();

        foreach ($existingRows as $existingRow) {
            $periode = trim((string) ($existingRow->PERIODE ?? ''));
            $tid = trim((string) ($existingRow->TID ?? ''));
            $key = $periode . '|' . $tid;

            if (isset($normalizedPairs[$key])) {
                $lookup[$key] = true;
            }
        }

        return $lookup;
    }

    private function summarizeFailedRow(array $row): array
    {
        $summary = [];
        $count = 0;

        foreach ($row as $key => $value) {
            if ($count >= 12) {
                break;
            }

            $stringValue = is_scalar($value) || $value === null
                ? (string) ($value ?? '')
                : json_encode($value);

            $summary[$key] = mb_substr($stringValue, 0, 120);
            $count++;
        }

        $summary['__column_count'] = count($row);

        return $summary;
    }

    private function logFailedImportRow(string $tableName, array $row, string $errorMessage): void
    {
        // NOTE: intentionally NOT inserting into failed_jobs (Laravel queue system table)
        // to avoid contaminating queue monitoring dashboards.
        Log::warning('Import row failed', [
            'table'   => $tableName,
            'error'   => mb_substr($errorMessage, 0, 600),
            'sample'  => $this->summarizeFailedRow($row),
        ]);
    }

    private function insertBufferedChunk(
        array $chunk,
        string $tableName,
        int &$totalSuccess,
        int &$totalFailed,
        string &$lastErrorMsg
    ): void {
        if (empty($chunk)) {
            return;
        }

        $chunk = $this->allocateGapIdsForRows($tableName, $chunk);

        try {
            DB::table($tableName)->insert($chunk);
            $totalSuccess += count($chunk);
            return;
        } catch (\Throwable $e) {
            $lastErrorMsg = substr($e->getMessage(), 0, 800) . '...';
        }

        // Non-recursive fallback: insert per row to isolate bad records
        // without generating recursive transaction storms.
        foreach ($chunk as $row) {
            try {
                DB::table($tableName)->insert([$row]);
                $totalSuccess++;
            } catch (\Throwable $singleError) {
                $totalFailed++;
                $lastErrorMsg = substr($singleError->getMessage(), 0, 800) . '...';
                $this->logFailedImportRow($tableName, $row, $lastErrorMsg);
            }
        }
    }

    private function flushInsertBuffer(
        array &$buffer,
        string $tableName,
        int &$totalSuccess,
        int &$totalFailed,
        string &$lastErrorMsg,
        ?callable $beforeInsert = null,
        ?int $batchSize = null
    ): void {
        if (empty($buffer)) {
            return;
        }

        $batchSize ??= $this->resolveImportBatchSize($tableName);

        foreach (array_chunk($buffer, $batchSize) as $chunk) {
            if ($beforeInsert) {
                $chunk = array_values(array_filter($chunk, function (array $row) use ($beforeInsert) {
                    return $beforeInsert($row) !== false;
                }));
            }

            if (empty($chunk)) {
                continue;
            }

            $this->insertBufferedChunk($chunk, $tableName, $totalSuccess, $totalFailed, $lastErrorMsg);
        }

        $buffer = [];
    }

    private function passesActiveFilters(array $row, array $activeFilters): bool
    {
        foreach ($activeFilters as $colIdx => $allowedValues) {
            $cellValue = isset($row[$colIdx]) ? trim((string) $row[$colIdx]) : '';
            if (!isset($allowedValues[$cellValue])) {
                return false;
            }
        }

        return true;
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

    private function mapRowForInsert(
        array $sourceData,
        array $selectedColumns,
        array $csvHeaders,
        bool $isBrilinkSummary,
        string $uniqueSuffix,
        ?array $columnBlueprint = null,
        int $posisiIndex = -1,
        int $tahunIndex = -1
    ): ?array {
        if ($isBrilinkSummary) {
            // FIX #2: Add length validation for Brilink indices before access
            if (count($sourceData) < 2) {
                return null;  // Insufficient columns
            }

            $rawPeriode = $sourceData[0] ?? '';
            $periode = strpos((string) $rawPeriode, ':') !== false
                ? trim(explode(':', (string) $rawPeriode, 2)[1] ?? '')
                : trim((string) $rawPeriode);

            $brilinkOffset = $this->hasManualNumberingColumn($sourceData) ? 1 : 0;
            $minRequiredLength = 15 + $brilinkOffset;  // Indices 0-14 needed (or 1-15 with offset)
            
            if (count($sourceData) < $minRequiredLength) {
                return null;  // Row too short for Brilink structure
            }

            $rowData = [
                'uniqueid_namareport' => uniqid() . '_BST',
                'periode' => $periode,
                'kanwil' => trim((string) ($sourceData[2 + $brilinkOffset] ?? '')) ?: null,
                'cabang' => trim((string) ($sourceData[4 + $brilinkOffset] ?? '')) ?: null,
                'uker' => trim((string) ($sourceData[6 + $brilinkOffset] ?? '')) ?: null,
                'merchant_name' => trim((string) ($sourceData[7 + $brilinkOffset] ?? '')) ?: null,
                'merchant_code' => trim((string) ($sourceData[8 + $brilinkOffset] ?? '')) ?: null,
                'outlet_name' => trim((string) ($sourceData[9 + $brilinkOffset] ?? '')) ?: null,
                'outlet_code' => trim((string) ($sourceData[10 + $brilinkOffset] ?? '')) ?: null,
                'total_transaksi' => (int) preg_replace('/[^0-9]/', '', (string) ($sourceData[11 + $brilinkOffset] ?? 0)),
                'total_nominal' => (float) preg_replace('/[^0-9.]/', '', (string) ($sourceData[12 + $brilinkOffset] ?? 0)),
                'total_fee' => (float) preg_replace('/[^0-9.]/', '', (string) ($sourceData[13 + $brilinkOffset] ?? 0)),
                'total_fee_bri' => (float) preg_replace('/[^0-9.]/', '', (string) ($sourceData[14 + $brilinkOffset] ?? 0)),
            ];

            return $this->hasMeaningfulImportData($rowData, ['uniqueid_namareport', 'periode']) ? $rowData : null;
        }

        $rowData = [
            'uniqueid_namareport' => uniqid() . $uniqueSuffix,
        ];

        $columnBlueprint ??= $this->buildColumnImportBlueprint($selectedColumns, $csvHeaders);

        foreach ($columnBlueprint as $columnMeta) {
            $index = $columnMeta['index'];
            $cellValue = isset($sourceData[$index]) ? trim((string) $sourceData[$index]) : '';

            if ($columnMeta['type'] === 'period') {
                $cellValue = $this->normalizeImportPeriodValue(
                    $cellValue,
                    $posisiIndex !== -1 ? ($sourceData[$posisiIndex] ?? null) : null,
                    $tahunIndex !== -1 ? ($sourceData[$tahunIndex] ?? null) : null
                );
            } elseif ($columnMeta['type'] === 'date') {
                $cellValue = $this->normalizeDailyLoanDate($cellValue);
            } elseif ($columnMeta['type'] === 'numeric') {
                $cellValue = $this->normalizeDecimalValue($cellValue);
            }

            $rowData[$columnMeta['column']] = ($cellValue === '') ? null : $cellValue;
        }

        $rowData = $this->applyDailyLoanCompatibilityColumns($rowData);

        return $this->hasMeaningfulImportData($rowData, ['uniqueid_namareport', 'periode', 'posisi']) ? $rowData : null;
    }

    private function collectImportMeta(
        string $filePath,
        array $selectedColumns,
        array $activeFilters,
        string $currentDelimiter,
        bool $isBrilinkSummary,
        bool $collectDuplicatePairs = false
    ): array {
        $headers = [];
        $posisiIndex = -1;
        $tahunIndex = -1;
        $totalRows = 0;
        $samplePosisi = null;
        $samplePeriode = null;
        $periodeTidPairs = [];
        $periodeIndex = -1;
        $tidIndex = -1;

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('File tidak dapat dibaca.');
        }

        try {
            $delimiter = $this->resolveDelimiter($handle, $currentDelimiter);
            rewind($handle);

            $rowCounter = 0;
            while (($data = $this->readCsvRecord($handle, $delimiter)) !== false) {
                if (empty($data) || implode('', $data) === '') {
                    continue;
                }

                if ($rowCounter === 0) {
                    $headers = $this->formatCsvHeaders($data, $isBrilinkSummary);
                    if (!$isBrilinkSummary) {
                        foreach ($headers as $i => $header) {
                            if (stripos($header, 'posisi') !== false) {
                                $posisiIndex = $i;
                            }
                            if (stripos($header, 'tahun') !== false) {
                                $tahunIndex = $i;
                            }
                            if (strcasecmp(trim((string) $header), 'PERIODE') === 0) {
                                $periodeIndex = $i;
                            }
                            if (strcasecmp(trim((string) $header), 'TID') === 0) {
                                $tidIndex = $i;
                            }
                        }
                    }

                    $rowCounter++;
                    continue;
                }

                $parsedRow = $this->parseCsvRow($data, $isBrilinkSummary, $headers, $posisiIndex, $tahunIndex);
                if ($parsedRow === null || !$this->passesActiveFilters($parsedRow, $activeFilters)) {
                    $rowCounter++;
                    continue;
                }

                $totalRows++;

                if ($samplePeriode === null) {
                    // Prefer the detected periodeIndex; fall back to column 0 only if no dedicated period column found
                    $periodeCandidate = $periodeIndex !== -1
                        ? ($parsedRow[$periodeIndex] ?? null)
                        : ($posisiIndex !== -1 ? ($parsedRow[$posisiIndex] ?? null) : ($parsedRow[0] ?? null));
                    $samplePeriode = trim((string) $periodeCandidate) ?: null;
                }

                if ($samplePosisi === null && $posisiIndex !== -1 && isset($parsedRow[$posisiIndex])) {
                    $samplePosisi = trim((string) $parsedRow[$posisiIndex]) ?: null;
                }

                if ($collectDuplicatePairs && $periodeIndex !== -1 && $tidIndex !== -1) {
                    $periodeValue = trim((string) ($parsedRow[$periodeIndex] ?? ''));
                    $tidValue = trim((string) ($parsedRow[$tidIndex] ?? ''));

                    if ($periodeValue !== '' && $tidValue !== '') {
                        $periodeTidPairs[$periodeValue . '|' . $tidValue] = [
                            'periode' => $periodeValue,
                            'tid' => $tidValue,
                        ];
                    }
                }

                $rowCounter++;
            }
        } finally {
            fclose($handle);
        }

        return [
            'delimiter' => $delimiter,
            'headers' => $headers,
            'posisi_index' => $posisiIndex,
            'tahun_index' => $tahunIndex,
            'total_rows' => $totalRows,
            'sample_posisi' => $samplePosisi,
            'sample_periode' => $samplePeriode,
            'periode_tid_pairs' => array_values($periodeTidPairs),
        ];
    }

    private function hasManualNumberingColumn(array $row): bool
    {
        $candidate = trim((string) ($row[1] ?? ''));

        return $candidate !== '' && preg_match('/^\d+$/', $candidate) === 1;
    }

    private function transformBrilinkSummaryRow(array $row): array
    {
        $rawPeriode = $row[0] ?? '';
        $periode = strpos($rawPeriode, ':') !== false
            ? trim(explode(':', $rawPeriode, 2)[1])
            : trim((string) $rawPeriode);

        $offset = $this->hasManualNumberingColumn($row) ? 1 : 0;
        $baseIndex = 1 + $offset;

        return [
            $periode,
            $row[$baseIndex] ?? null,
            trim((string) ($row[$baseIndex + 1] ?? '')) ?: null,
            $row[$baseIndex + 2] ?? null,
            trim((string) ($row[$baseIndex + 3] ?? '')) ?: null,
            $row[$baseIndex + 4] ?? null,
            trim((string) ($row[$baseIndex + 5] ?? '')) ?: null,
            trim((string) ($row[$baseIndex + 6] ?? '')) ?: null,
            $row[$baseIndex + 7] ?? null,
            trim((string) ($row[$baseIndex + 8] ?? '')) ?: null,
            $row[$baseIndex + 9] ?? null,
            $row[$baseIndex + 10] ?? null,
            $row[$baseIndex + 11] ?? null,
            $row[$baseIndex + 12] ?? null,
            $row[$baseIndex + 13] ?? null,
        ];
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

        // OPTIMIZATION: Cache normalization results for repeated values (common in imports)
        $cacheKey = 'decimal:' . $value;
        if (isset($this->decimalNormalizationCache[$cacheKey])) {
            return $this->decimalNormalizationCache[$cacheKey];
        }

        $originalValue = $value;
        $isNegative = false;

        if (preg_match('/^\((.*)\)$/', $value, $matches) === 1) {
            $value = trim((string) ($matches[1] ?? ''));
            $isNegative = true;
        }

        if (str_ends_with($value, '-')) {
            $value = rtrim(substr($value, 0, -1));
            $isNegative = true;
        }

        if (preg_match('/^-?\d+$/', $value) === 1) {
            if ($isNegative && str_starts_with($value, '-') === false) {
                $value = '-' . $value;
            }
            $result = $value . '.00';
            $this->decimalNormalizationCache[$cacheKey] = $result;
            return $result;
        }

        if (preg_match('/^-?\d+\.\d+$/', $value) === 1) {
            if ($isNegative && str_starts_with($value, '-') === false) {
                $value = '-' . $value;
            }
            $result = number_format((float) $value, 2, '.', '');
            $this->decimalNormalizationCache[$cacheKey] = $result;
            return $result;
        }

        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^0-9,\.\-]/', '', $value);

        if ($value === '' || $value === '-' || $value === null) {
            return $this->decimalNormalizationCache[$cacheKey] = null;
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
            return $this->decimalNormalizationCache[$cacheKey] = null;
        }

        if ($isNegative && (float) $value > 0) {
            $value = '-' . ltrim((string) $value, '+');
        }

        $result = number_format((float) $value, 2, '.', '');
        $this->decimalNormalizationCache[$cacheKey] = $result;
        return $result;
    }

    public function upload(Request $request)
    {
        $contentLength = (int) ($request->server('CONTENT_LENGTH') ?? 0);
        $postMaxSize = $this->parseIniSizeToBytes((string) ini_get('post_max_size'));

        if ($contentLength > 0 && $postMaxSize > 0 && $contentLength > $postMaxSize && !$request->hasFile('file')) {
            $message = 'Ukuran upload melebihi batas server (' . ini_get('post_max_size') . ').';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 413);
            }

            return back()->with('error', $message);
        }

        $request->validate(['id_report' => 'required', 'file' => 'required|file|mimes:rar,csv,txt']);
        $folderName = 'import_' . date('Ymd_His') . '_' . Str::random(5);
        $storagePath = storage_path('app/imports/' . $folderName);
        if (!file_exists($storagePath)) { mkdir($storagePath, 0777, true); }
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $file->move($storagePath, $fileName);
        $fullPath = $storagePath . '/' . $fileName;
        $files = [];
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'rar') {
            $extractPath = $storagePath . '/extracted';
            if (!file_exists($extractPath)) { mkdir($extractPath, 0777, true); }
            $command = '"C:\Program Files\7-Zip\7z.exe" x "' . $fullPath . '" -o"' . $extractPath . '" -y';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);

            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractPath));
            foreach ($rii as $fileItem) {
                if ($fileItem->isDir()) continue;
                $files[] = ['name' => $fileItem->getFilename(), 'path' => $fileItem->getPathname()];
            }

            if ($exitCode !== 0 || empty($files)) {
                $this->cleanupImportDirectory($fullPath);
                return back()->with('error', 'Gagal mengekstrak file RAR atau arsip tidak berisi file yang bisa diproses.');
            }
        } else {
            $files[] = ['name' => $fileName, 'path' => $fullPath];
        }
        session([
            'import_files'     => $files,
            'active_id_report' => $request->input('id_report'),
            'import_type'      => 'default',
        ]);

        if ($extension !== 'rar' && count($files) === 1) {
            $redirectUrl = route('import.preview.direct', [
                'file_path' => $files[0]['path'],
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'redirect' => $redirectUrl,
                ]);
            }

            return redirect()->to($redirectUrl);
        }

        $selectUrl = route('import.select');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'redirect' => $selectUrl,
            ]);
        }

        return redirect()->to($selectUrl);
    }

    public function preview(Request $request)
    {
        $this->applySafeRuntimeLimits();

        $request->validate(['file_path' => 'required|string', 'delimiter' => 'nullable|string']);
        $filePath = $request->input('file_path');
        $currentDelimiter = $request->input('delimiter', 'auto');
        if (!file_exists($filePath)) { return back()->with('error', 'File tidak ditemukan di server.'); }
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $headers = []; $previewData = []; $uniqueValues = [];
        $posisiIndex = -1;
        $tahunIndex = -1;

        $idReport = session('active_id_report', 1);
        $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
        $isBrilinkSummary = false;
        $this->releaseSessionLockIfNeeded();
        $isDailyLoan = $this->isDailyLoanReport($reportData);
        $tableName = $this->resolveTableName($reportData);
        $disableArea6AutoFilter = $isDailyLoan || in_array($tableName, [
            'sv_merchant',
            'merchant_qris',
            'merchant_qris_volume',
        ], true);

        if ($reportData && (stripos($reportData->nama_report, 'BRILINK Web - Laporan Summary Transaksi') !== false || stripos($reportData->nama_report, 'brilink_web') !== false)) {
            $isBrilinkSummary = true;
        }

        $previewSampleLimit = $isDailyLoan ? self::DAILY_LOAN_PREVIEW_SAMPLE_LIMIT : self::PREVIEW_SAMPLE_LIMIT;
        $previewUniqueScanLimit = $isDailyLoan ? self::DAILY_LOAN_PREVIEW_UNIQUE_SCAN_LIMIT : self::PREVIEW_UNIQUE_SCAN_LIMIT;
        $previewUniqueLimitPerColumn = $isDailyLoan ? self::DAILY_LOAN_PREVIEW_UNIQUE_LIMIT_PER_COLUMN : self::PREVIEW_UNIQUE_LIMIT_PER_COLUMN;
        $fileSizeBytes = (int) @filesize($filePath);

        if (!$isDailyLoan && $fileSizeBytes > self::LARGE_FILE_THRESHOLD_BYTES) {
            $previewSampleLimit = min($previewSampleLimit, self::LARGE_FILE_PREVIEW_SAMPLE_LIMIT);
            $previewUniqueScanLimit = min($previewUniqueScanLimit, self::LARGE_FILE_PREVIEW_UNIQUE_SCAN_LIMIT);
            $previewUniqueLimitPerColumn = min($previewUniqueLimitPerColumn, self::LARGE_FILE_PREVIEW_UNIQUE_LIMIT_PER_COLUMN);
        }

        if (in_array($extension, ['csv', 'txt'], true)) {
            if (($handle = fopen($filePath, "r")) !== FALSE) {
                // OPTIMIZATION 1: Cache delimiter detection results
                $delimiterCacheKey = "import_csv_delimiter:" . md5($filePath . filesize($filePath));
                $delimiter = Cache::get($delimiterCacheKey);
                
                if ($delimiter === null) {
                    $firstLine = fgets($handle);
                    if ($currentDelimiter === 'auto') {
                        $delimiters = [',' => 0, ';' => 0, '|' => 0, "\t" => 0, '.' => 0];
                        foreach ($delimiters as $delim => &$count) { $count = substr_count($firstLine, $delim); }
                        arsort($delimiters); $delimiter = key($delimiters); 
                    } else { $delimiter = $currentDelimiter; }
                    Cache::put($delimiterCacheKey, $delimiter, now()->addHours(24));
                    rewind($handle);
                } else {
                    // Delimiter dari cache, skip firstLine reading
                    rewind($handle);
                }
                
                // OPTIMIZATION 2: Prepare data for efficient batch processing
                $rowCounter = 0;
                $savedRows = 0;
                $scannedRows = 0;
                $collectUniqueValues = true;
                
                // Pre-allocate for non-daily-loan date parsing
                $posisiCache = [];
                $tahunCache = [];
                
                // OPTIMIZATION 3: Process rows with minimal trim operations
                while (($data = $this->readCsvRecord($handle, $delimiter)) !== FALSE) {
                    // OPTIMIZATION 4: Skip empty rows BEFORE any processing
                    $hasContent = false;
                    foreach ($data as $val) {
                        if ($val !== null && $val !== '' && trim((string) $val) !== '') {
                            $hasContent = true;
                            break;
                        }
                    }
                    if (!$hasContent) continue;
                    
                    if ($rowCounter == 0) {
                        $headers = $this->formatCsvHeaders($data, $isBrilinkSummary);

                        if (!$isBrilinkSummary) {
                            // OPTIMIZATION 5: Find posisi/tahun indices once, cache them
                            foreach ($headers as $i => $h) { 
                                if (stripos($h, 'POSISI') !== false) { $posisiIndex = $i; }
                                if (stripos($h, 'TAHUN') !== false) { $tahunIndex = $i; }
                            }
                        }

                        foreach ($headers as $i => $h) {
                            $uniqueValues[$i] = [];
                        }

                    } else {
                        // OPTIMIZATION 6: Defer expensive operations, only process needed data
                        if (!$isDailyLoan) {
                            $firstCell = trim((string) ($data[0] ?? ''));
                            if ($firstCell === 'TAHUN' || stripos($firstCell, 'textbox') !== false) {
                                continue;
                            }
                        }

                        if ($isBrilinkSummary) {
                            $data = $this->transformBrilinkSummaryRow($data);
                        } elseif ($isDailyLoan) {
                            // OPTIMIZATION 7: Minimal array operations for daily loan
                            $headerCount = count($headers);
                            $dataCount = count($data);
                            
                            if ($dataCount < $headerCount) {
                                // Only pad if necessary
                                for ($j = $dataCount; $j < $headerCount; $j++) {
                                    $data[$j] = null;
                                }
                            } elseif ($dataCount > $headerCount) {
                                continue;
                            }

                            // OPTIMIZATION 8: Batch normalize values in single pass
                            $normalized = [];
                            foreach ($headers as $i => $header) {
                                $normalizedColumn = $this->normalizeDailyLoanHeader($header);
                                $cellValue = $data[$i] ?? '';
                                
                                if ($this->isDailyLoanDateColumn($normalizedColumn)) {
                                    $normalized[$i] = $this->normalizeDailyLoanDate(is_string($cellValue) ? trim($cellValue) : $cellValue);
                                } elseif ($this->isDailyLoanNumericColumn($normalizedColumn)) {
                                    $normalized[$i] = $this->normalizeDecimalValue(is_string($cellValue) ? trim($cellValue) : $cellValue);
                                } else {
                                    $normalized[$i] = is_string($cellValue) && $cellValue !== '' ? trim($cellValue) : null;
                                }
                            }
                            $data = $normalized;
                        } else {
                            // OPTIMIZATION 9: Minimal array operations for other reports
                            $headerCount = count($headers);
                            $dataCount = count($data);
                            
                            if ($dataCount < $headerCount) {
                                for ($j = $dataCount; $j < $headerCount; $j++) {
                                    $data[$j] = null;
                                }
                            } elseif ($dataCount > $headerCount) {
                                continue;
                            }

                            // OPTIMIZATION 10: Defer date parsing - only parse when needed for preview/unique
                            if ($posisiIndex !== -1 && isset($data[$posisiIndex])) {
                                $rawPosisi = is_string($data[$posisiIndex]) ? trim($data[$posisiIndex]) : '';
                                if ($rawPosisi !== '') {
                                    // Cache hasil parsing untuk rows yang sama
                                    $cacheKey = "parsed_date:" . $rawPosisi;
                                    if (!isset($posisiCache[$cacheKey])) {
                                        try {
                                            if (strpos($rawPosisi, '/') !== false) {
                                                $posisiCache[$cacheKey] = StrictDateParser::normalize($rawPosisi);
                                            } else {
                                                if ($tahunIndex !== -1 && isset($data[$tahunIndex])) {
                                                    $rawTahun = is_string($data[$tahunIndex]) ? trim($data[$tahunIndex]) : '';
                                                    if ($rawTahun !== '') {
                                                        if (preg_match('/^([a-zA-Z]+\s+\d+)/', $rawPosisi, $matches)) {
                                                            $fixedDateStr = $matches[1] . ' ' . $rawTahun; 
                                                            $posisiCache[$cacheKey] = StrictDateParser::normalize($fixedDateStr);
                                                        } else {
                                                            $posisiCache[$cacheKey] = StrictDateParser::normalize($rawPosisi);
                                                        }
                                                    } else {
                                                        $posisiCache[$cacheKey] = StrictDateParser::normalize($rawPosisi);
                                                    }
                                                } else {
                                                    $posisiCache[$cacheKey] = StrictDateParser::normalize($rawPosisi);
                                                }
                                            }
                                        } catch (\Exception $e) {
                                            $posisiCache[$cacheKey] = $rawPosisi;
                                        }
                                    }
                                    $data[$posisiIndex] = $posisiCache[$cacheKey];
                                }
                            }
                        }

                        $scannedRows++;

                        if ($savedRows < $previewSampleLimit) {
                            $previewData[] = $data;
                            $savedRows++;
                        }

                        if ($collectUniqueValues) {
                            // OPTIMIZATION 11: Batch unique value collection
                            $validIndices = array_keys($uniqueValues);
                            $validIndicesSet = array_flip($validIndices);  // Use flip for faster lookup
                            
                            foreach ($validIndices as $i) {
                                $val = $data[$i] ?? '';
                                $cleanVal = is_string($val) ? trim($val) : (string) $val;
                                
                                if (count($uniqueValues[$i]) < $previewUniqueLimitPerColumn || isset($uniqueValues[$i][$cleanVal])) {
                                    $uniqueValues[$i][$cleanVal] = true;
                                }
                            }

                            if ($scannedRows >= $previewUniqueScanLimit) {
                                $collectUniqueValues = false;
                            }
                        }

                        // Early exit untuk file besar
                        if (!$collectUniqueValues && count($previewData) >= $previewSampleLimit) {
                            break;
                        }
                    }
                    $rowCounter++;
                }
                fclose($handle);
            }
        } else { return back()->with('error', 'Format file tidak didukung.'); }
        
        $formattedUniqueValues = [];
        foreach ($uniqueValues as $index => $valuesMap) {
            $keys = array_keys($valuesMap); sort($keys); $formattedUniqueValues[$index] = $keys;
        }

        $area6ColumnHints = $isDailyLoan
            ? []
            : ['KANCA', 'KCI', 'BRANCH', 'BRDESC', 'MBDESC'];
        $initialArea6Selections = $this->buildInitialArea6Selections($headers, $formattedUniqueValues, $area6ColumnHints);
        if ($disableArea6AutoFilter) {
            $initialArea6Selections = [];
        }

        $displayToSourceMap = range(0, max(count($headers) - 1, 0));
        if ($isDailyLoan) {
            $preparedPreview = $this->prepareDailyLoanPreview($headers, $previewData, $formattedUniqueValues);
            $headers = $preparedPreview['headers'];
            $previewData = $preparedPreview['previewData'];
            $formattedUniqueValues = $preparedPreview['formattedUniqueValues'];
            $displayToSourceMap = $preparedPreview['displayToSourceMap'];
        }

        session(['final_import_path' => $filePath]);
        session(['import_display_to_source_map' => $displayToSourceMap]);

        $processRoute = route('import.process');
        $initRoute = route('import.init');
        $streamRoute = route('import.stream');

        return view('import.preview', compact(
            'headers',
            'previewData',
            'filePath',
            'formattedUniqueValues',
            'currentDelimiter',
            'isBrilinkSummary',
            'processRoute',
            'initRoute',
            'streamRoute'
        ))->with([
            'disableArea6AutoFilter' => $disableArea6AutoFilter,
            'forceAllFiltersCheckedOnLoad' => $isDailyLoan,
            'lockDelimiterSelector' => $isDailyLoan,
            'fixedDelimiterLabel' => 'Koma ( , )',
            'backRoute' => route('import.index'),
            'area6ColumnHints' => $area6ColumnHints,
            'initialArea6Selections' => $initialArea6Selections,
            'filterOptionsRoute' => route('import.preview.filter-options'),
            'prefetchFilterOptionsOnLoad' => false,
        ]);
    }

    public function previewFilterOptions(Request $request)
    {
        $this->applySafeRuntimeLimits();

        $request->validate([
            'file_path' => 'required|string',
            'delimiter' => 'nullable|string',
            'column_index' => 'required|integer|min:0',
            'display_filter_map_json' => 'nullable|string',
            'preview_state_key' => 'nullable|string',
        ]);

        $filePath = (string) $request->input('file_path');
        $currentDelimiter = (string) $request->input('delimiter', 'auto');
        $columnIndex = (int) $request->input('column_index');
        $previewStateKey = trim((string) $request->input('preview_state_key', ''));
        $displayFilterMap = json_decode((string) $request->input('display_filter_map_json', ''), true);
        if (!is_array($displayFilterMap)) {
            $displayFilterMap = [];
        }
        $sourceColumnIndex = array_key_exists($columnIndex, $displayFilterMap)
            ? (int) $displayFilterMap[$columnIndex]
            : $columnIndex;

        $previewState = $previewStateKey !== ''
            ? app(\App\Services\Import\ExcelImportJobService::class)->getPreviewState($previewStateKey)
            : [];
        $previewMeta = !empty($previewState['previewMeta']) && is_array($previewState['previewMeta'])
            ? (array) $previewState['previewMeta']
            : (array) session('excel_preview_meta', []);

        $resolvedFilePath = $filePath;
        if (!file_exists($resolvedFilePath)) {
            try {
                $storageResolvedPath = Storage::path($filePath);
                if (is_string($storageResolvedPath) && $storageResolvedPath !== '' && file_exists($storageResolvedPath)) {
                    $resolvedFilePath = $storageResolvedPath;
                }
            } catch (\Throwable $e) {
            }
        }

        $stagedCsvPath = (string) ($previewMeta['staged_csv_path'] ?? '');
        if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
            $resolvedFilePath = $stagedCsvPath;
        }

        if (!file_exists($resolvedFilePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File tidak ditemukan di server.',
            ], 404);
        }

        $extension = strtolower(pathinfo($resolvedFilePath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt'], true)) {
            $headerIndex = isset($previewMeta['header_index']) ? (int) $previewMeta['header_index'] : null;
            $sourceHeaders = array_values((array) ($previewMeta['source_headers'] ?? []));
            $previewPath = urldecode((string) ($previewMeta['path'] ?? ''));
            $sourceExcelPath = $previewPath !== '' ? Storage::path($previewPath) : $resolvedFilePath;

            if (
                $previewStateKey !== ''
                && $headerIndex !== null
                && !empty($sourceHeaders)
                && is_string($sourceExcelPath)
                && $sourceExcelPath !== ''
                && file_exists($sourceExcelPath)
            ) {
                try {
                    $stagingService = app(\App\Services\Import\ExcelStagingService::class);
                    $generatedStagedCsvPath = $stagingService->createStagedCsvPath(storage_path('app/import_preview_filters'), 'filter_preview');
                    $stageResult = $stagingService->stageExcelToCsv(
                        static function (string $event, array $payload): void {
                        },
                        $sourceExcelPath,
                        $headerIndex,
                        $sourceHeaders,
                        $generatedStagedCsvPath,
                        null,
                        'excel_filter_preview_'
                    );

                    $candidateCsvPath = (string) ($stageResult['staged_csv_path'] ?? '');
                    if ($candidateCsvPath !== '' && file_exists($candidateCsvPath)) {
                        $resolvedFilePath = $candidateCsvPath;
                        $extension = strtolower(pathinfo($resolvedFilePath, PATHINFO_EXTENSION));

                        $previewMeta['staged_csv_path'] = $candidateCsvPath;
                        session(['excel_preview_meta' => array_merge((array) session('excel_preview_meta', []), [
                            'staged_csv_path' => $candidateCsvPath,
                        ])]);

                        if ($previewStateKey !== '') {
                            app(\App\Services\Import\ExcelImportJobService::class)->putPreviewState(
                                $previewStateKey,
                                array_merge($previewState, ['previewMeta' => $previewMeta])
                            );
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        if (!in_array($extension, ['csv', 'txt'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Format file tidak didukung untuk opsi filter preview.',
            ], 422);
        }

        $reportData = $this->getActiveReportData();
        $tableName = $this->resolveTableName($reportData);
        $isBrilinkSummary = $this->isBrilinkSummaryReport($reportData);
        $isDailyLoan = $this->isDailyLoanReport($reportData);
        $cacheKey = $this->previewFilterCacheKey($resolvedFilePath, $currentDelimiter, $sourceColumnIndex, $tableName);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json([
                'status' => 'success',
                'values' => $cached,
                'cached' => true,
            ]);
        }

        $handle = fopen($resolvedFilePath, 'r');
        if ($handle === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'File tidak dapat dibaca oleh server.',
            ], 422);
        }

        $valuesMap = [];
        $headers = [];
        $resolvedDelimiter = ',';
        $posisiIndex = -1;
        $tahunIndex = -1;

        try {
            $resolvedDelimiter = $this->resolveDelimiter($handle, $currentDelimiter);
            rewind($handle);

            $rowCounter = 0;
            while (($data = $this->readCsvRecord($handle, $resolvedDelimiter)) !== false) {
                if (empty($data) || implode('', $data) === '') {
                    continue;
                }

                if ($rowCounter === 0) {
                    $headers = $this->formatCsvHeaders($data, $isBrilinkSummary);
                    if (!isset($headers[$sourceColumnIndex])) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Kolom filter tidak valid.',
                        ], 422);
                    }

                    if (!$isBrilinkSummary) {
                        foreach ($headers as $i => $header) {
                            if (stripos((string) $header, 'POSISI') !== false) {
                                $posisiIndex = $i;
                            }
                            if (stripos((string) $header, 'TAHUN') !== false) {
                                $tahunIndex = $i;
                            }
                        }
                    }

                    $rowCounter++;
                    continue;
                }

                if (!$isDailyLoan && (trim((string) ($data[0] ?? '')) === 'TAHUN' || stripos(trim((string) ($data[0] ?? '')), 'textbox') !== false)) {
                    continue;
                }

                if ($isBrilinkSummary) {
                    $data = $this->transformBrilinkSummaryRow($data);
                } elseif ($isDailyLoan) {
                    if (count($data) < count($headers)) {
                        $data = array_pad($data, count($headers), null);
                    }

                    if (count($data) > count($headers)) {
                        continue;
                    }

                    foreach ($headers as $i => $header) {
                        $normalizedColumn = $this->normalizeDailyLoanHeader((string) $header);
                        $cellValue = isset($data[$i]) ? trim((string) $data[$i]) : '';

                        if ($this->isDailyLoanDateColumn($normalizedColumn)) {
                            $data[$i] = $this->normalizeDailyLoanDate($cellValue);
                        } elseif ($this->isDailyLoanNumericColumn($normalizedColumn)) {
                            $data[$i] = $this->normalizeDecimalValue($cellValue);
                        } else {
                            $data[$i] = $cellValue === '' ? null : $cellValue;
                        }
                    }
                } else {
                    if (count($data) < count($headers)) {
                        $data = array_pad($data, count($headers), null);
                    }

                    if (count($data) > count($headers)) {
                        continue;
                    }

                    if ($posisiIndex !== -1 && isset($data[$posisiIndex]) && trim((string) $data[$posisiIndex]) !== '') {
                        $rawPosisi = trim((string) $data[$posisiIndex]);
                        try {
                            if (strpos($rawPosisi, '/') !== false) {
                                $data[$posisiIndex] = StrictDateParser::normalize($rawPosisi);
                            } else {
                                if ($tahunIndex !== -1 && isset($data[$tahunIndex]) && trim((string) $data[$tahunIndex]) !== '') {
                                    $rawTahun = trim((string) $data[$tahunIndex]);
                                    if (preg_match('/^([a-zA-Z]+\s+\d+)/', $rawPosisi, $matches)) {
                                        $data[$posisiIndex] = StrictDateParser::normalize($matches[1] . ' ' . $rawTahun);
                                    } else {
                                        $data[$posisiIndex] = StrictDateParser::normalize($rawPosisi);
                                    }
                                } else {
                                    $data[$posisiIndex] = StrictDateParser::normalize($rawPosisi);
                                }
                            }
                        } catch (\Throwable $e) {
                        }
                    }
                }

                $value = trim((string) ($data[$sourceColumnIndex] ?? ''));
                $valuesMap[$value] = true;
            }
        } finally {
            fclose($handle);
        }

        $values = array_keys($valuesMap);
        sort($values);
        Cache::put($cacheKey, $values, now()->addHours(4));

        return response()->json([
            'status' => 'success',
            'values' => $values,
            'cached' => false,
        ]);
    }

    /**
     * Dynamic filter options loading untuk merchant QRIS detail
     * Load complete unique values dari SELURUH file tanpa batasan
     */
    public function previewDynamicFilterOptions(Request $request)
    {
        $this->applySafeRuntimeLimits();

        $request->validate([
            'file_path' => 'required|string',
            'delimiter' => 'nullable|string',
            'column_index' => 'required|integer|min:0',
            'column_name' => 'nullable|string',
        ]);

        $filePath = (string) $request->input('file_path');
        $currentDelimiter = (string) $request->input('delimiter', 'auto');
        $columnIndex = (int) $request->input('column_index');
        $columnName = (string) $request->input('column_name', '');

        $resolvedFilePath = $filePath;
        if (!file_exists($resolvedFilePath)) {
            try {
                $storageResolvedPath = Storage::path($filePath);
                if (is_string($storageResolvedPath) && file_exists($storageResolvedPath)) {
                    $resolvedFilePath = $storageResolvedPath;
                }
            } catch (\Throwable $e) {
            }
        }

        if (!file_exists($resolvedFilePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File tidak ditemukan.',
            ], 422);
        }

        // OPTIMIZATION 1: Cached dynamic options (same cache key strategy)
        $cacheKey = "import_dynamic_filter:".md5($filePath . $columnIndex . $currentDelimiter);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return response()->json([
                'status' => 'success',
                'values' => $cached,
                'total_unique' => count($cached),
                'from_cache' => true,
            ]);
        }

        $handle = fopen($resolvedFilePath, 'r');
        if ($handle === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'File tidak dapat dibaca.',
            ], 422);
        }

        // OPTIMIZATION 2: Cache delimiter detection
        $delimiterCacheKey = "import_csv_delimiter:" . md5($resolvedFilePath . filesize($resolvedFilePath));
        $delimiter = Cache::get($delimiterCacheKey);
        
        if ($delimiter === null) {
            $delimiter = $currentDelimiter === 'auto' ? $this->detectCsvDelimiter($resolvedFilePath) : $currentDelimiter;
            Cache::put($delimiterCacheKey, $delimiter, now()->addHours(24));
        }

        $uniqueValues = [];
        $headers = [];
        $rowCounter = 0;
        
        // OPTIMIZATION 3: Max unique values limit untuk file besar
        $maxUniqueValues = 5000;  // Cap di 5000 unique values
        $uniqueCollected = 0;
        $stopCollecting = false;

        try {
            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                // OPTIMIZATION 4: Skip empty rows early
                if (empty($row)) {
                    continue;
                }

                if ($rowCounter === 0) {
                    $headers = $this->formatCsvHeaders($row);
                    if (!isset($row[$columnIndex])) {
                        fclose($handle);
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Kolom filter tidak valid pada index ' . $columnIndex,
                        ], 422);
                    }
                    $rowCounter++;
                    continue;
                }

                // Collect value dari column
                if (isset($row[$columnIndex]) && !$stopCollecting) {
                    $value = is_string($row[$columnIndex]) ? trim($row[$columnIndex]) : (string) $row[$columnIndex];
                    
                    if ($value !== '' && !isset($uniqueValues[$value])) {
                        $uniqueValues[$value] = true;
                        $uniqueCollected++;
                        
                        // OPTIMIZATION 5: Stop jika sudah reached limit
                        if ($uniqueCollected >= $maxUniqueValues) {
                            $stopCollecting = true;
                        }
                    }
                }

                $rowCounter++;
                
                // OPTIMIZATION 6: For very large files, add safety check every 10000 rows
                if ($rowCounter % 10000 === 0) {
                    if (memory_get_usage(true) > 256 * 1024 * 1024) {  // 256MB limit
                        break;  // Stop if memory usage too high
                    }
                }
                
                // Stop early if both unique collected and some rows scanned
                if ($stopCollecting && $rowCounter > 50000) {
                    break;
                }
            }

            fclose($handle);

            $values = array_keys($uniqueValues);
            sort($values);

            // Cache untuk 8 jam
            Cache::put($cacheKey, $values, now()->addHours(8));

            return response()->json([
                'status' => 'success',
                'values' => $values,
                'total_unique' => count($values),
                'total_rows_scanned' => $rowCounter - 1, // Exclude header
                'from_cache' => false,
                'capped_at_limit' => count($values) >= $maxUniqueValues,
            ]);

        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses file: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function initImport(Request $request)
    {
        $this->applySafeRuntimeLimits();

        $request->validate([
            'file_path' => 'required|string',
            'selected_columns' => 'required|array|min:1',
            'active_filters_json' => 'nullable|string',
            'delimiter' => 'required|string',
        ]);

        $filePath = $request->input('file_path');
        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json'), true) ?: [];
        $displayToSourceMap = session('import_display_to_source_map', []);
        if (!empty($displayToSourceMap)) {
            $selectedColumns = array_values(array_map(function ($displayIndex) use ($displayToSourceMap) {
                return (int) ($displayToSourceMap[$displayIndex] ?? $displayIndex);
            }, $selectedColumns));
        }
        $normalizedFilters = [];
        foreach ($activeFilters as $columnIndex => $allowedValues) {
            $sourceIndex = !empty($displayToSourceMap) ? (int) ($displayToSourceMap[$columnIndex] ?? $columnIndex) : (int) $columnIndex;
            $normalizedFilters[$sourceIndex] = array_fill_keys(array_map(function ($value) {
                return trim((string) $value);
            }, (array) $allowedValues), true);
        }
        $currentDelimiter = $request->input('delimiter', 'auto');

        if (!file_exists($filePath)) {
            return response()->json([
                'status' => 'error',
                'text' => 'File tidak ditemukan di server.',
            ], 422);
        }

        $reportData = $this->getActiveReportData();
        $idReport = (int) session('active_id_report', 1);
        $isBrilinkSummary = $this->isBrilinkSummaryReport($reportData);
        $tableName = $this->resolveTableName($reportData);
        $uniqueSuffix = $this->resolveUniqueSuffix($tableName);
        $duplicateLookup = [];

        try {
            $meta = $this->collectImportMeta(
                $filePath,
                $selectedColumns,
                $normalizedFilters,
                $currentDelimiter,
                $isBrilinkSummary,
                $this->isJumlahMerchantDetailTable($tableName)
            );
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'text' => $e->getMessage(),
            ], 422);
        }

        $isDuplicate = false;
        $duplicateText = '';

        if ($this->isJumlahMerchantDetailTable($tableName)) {
            $duplicateLookup = $this->buildJumlahMerchantDuplicateLookup($meta['periode_tid_pairs'] ?? []);

            if (!empty($meta['periode_tid_pairs']) && count($duplicateLookup) === count($meta['periode_tid_pairs'])) {
                $isDuplicate = true;
                $duplicateText = 'Semua kombinasi <b>PERIODE + TID</b> pada file ini sudah ada di tabel <b class="text-uppercase">' . $tableName . '</b>.<br><br>Sistem membatalkan proses untuk mencegah data dobel.';
            }
        } elseif ($isBrilinkSummary && $meta['sample_periode']) {
            $isDuplicate = $this->cachedSchemaHasColumn($tableName, 'periode')
                && DB::table($tableName)->where('periode', $meta['sample_periode'])->exists();
            if ($isDuplicate) {
                $duplicateText = "Data untuk PERIODE <b>{$meta['sample_periode']}</b> sudah pernah diunggah sebelumnya ke tabel <b class='text-uppercase'>{$tableName}</b>.<br><br>Sistem membatalkan proses ini.";
            }
        } elseif ($meta['sample_posisi']) {
            // BUG-03: Guard against tables that don't have a POSISI column
            $isDuplicate = $this->cachedSchemaHasColumn($tableName, 'POSISI')
                && DB::table($tableName)->whereDate('POSISI', $meta['sample_posisi'])->exists();
            if ($isDuplicate) {
                $duplicateText = "Data untuk tanggal POSISI <b>{$meta['sample_posisi']}</b> sudah pernah diunggah sebelumnya ke tabel <b class='text-uppercase'>{$tableName}</b>.<br><br>Sistem membatalkan proses ini.";
            }
        }

        if ($isDuplicate) {
            $this->cleanupImportDirectory($filePath);

            return response()->json([
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => $duplicateText,
                'duplicate_detected' => true,
                'redirect_url' => route('import.index'),
            ], 422);
        }

        $jobId = app(\App\Services\Import\ImportProgressService::class)->createJob([
            'id_report' => $idReport,
            'file_name' => basename($filePath),
            'folder_path' => dirname($filePath),
            'status' => 'processing',
            'total_files' => $meta['total_rows'],
            'total_success' => 0,
            'total_failed' => 0,
            'created_by' => auth()->id() ?? 1,
            'job_context' => [
                'controller' => static::class,
                'mode' => 'file_import',
                'table_name' => $tableName,
                'file_hash' => sha1($filePath),
                'total_rows' => (int) $meta['total_rows'],
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $importParams = [
            'job_id' => $jobId,
            'file_path' => $filePath,
            'delimiter' => $meta['delimiter'],
            'selected_columns' => $selectedColumns,
            'active_filters' => $activeFilters,
            'normalized_filters' => $normalizedFilters,
            'table_name' => $tableName,
            'unique_suffix' => $uniqueSuffix,
            'is_brilink_summary' => $isBrilinkSummary,
            'headers' => $meta['headers'],
            'posisi_index' => $meta['posisi_index'],
            'tahun_index' => $meta['tahun_index'],
            'total_rows' => $meta['total_rows'],
            'sample_posisi' => $meta['sample_posisi'] ?? null,
            'sample_periode' => $meta['sample_periode'] ?? null,
            'duplicate_lookup' => $duplicateLookup,
        ];
        session(['csv_import_params' => $importParams]);
        Cache::put('csv_import_params_' . $jobId, $importParams, now()->addHours(4));

        return response()->json([
            'status' => 'success',
            'job_id' => $jobId,
            'total_rows' => $meta['total_rows'],
        ]);
    }

    public function processImportStream(Request $request)
    {
        $this->applySafeRuntimeLimits(streaming: true);
        DB::disableQueryLog();

        $sessionParams = session('csv_import_params', []);
        $jobId = (int) ($sessionParams['job_id'] ?? $request->query('job_id', 0));
        $params = Cache::get('csv_import_params_' . $jobId, $sessionParams);

        request()->session()->save();

        return response()->stream(function () use ($params, $jobId) {
            $streamLock = null;
            $send = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };
            $progressService = app(\App\Services\Import\ImportProgressService::class);
            $markJobFailed = function (string $message, int $success = 0, int $failed = 0, ?string $status = null) use ($jobId, $progressService): void {
                if ($jobId <= 0) {
                    return;
                }

                if ($success === 0 && $failed === 0 && str_contains(strtolower($message), 'file tidak ditemukan')) {
                    $progressService->markFailed($jobId, $message, 0, 0, $status ?? 'failed');
                    $progressService->cleanupQueuedImportJobRowsForJob($jobId);
                    return;
                }

                DB::table('import_jobs')->where('id', $jobId)->update([
                    'status' => $status ?? ($success > 0 ? 'failed_partial' : 'failed'),
                    'total_success' => $success,
                    'total_failed' => $failed,
                    'updated_at' => now(),
                ]);
            };

            try {
                if ($jobId > 0) {
                    $streamLock = Cache::lock('import_file_stream_job_' . $jobId, 7200);

                    if (!$streamLock->get()) {
                        $job = DB::table('import_jobs')->where('id', $jobId)->first();

                        if ($job && in_array($job->status, ['completed', 'failed', 'failed_partial'], true)) {
                            $send('complete', [
                                'total_success' => (int) ($job->total_success ?? 0),
                                'total_failed' => (int) ($job->total_failed ?? 0),
                                'total_rows' => (int) ($job->total_files ?? 0),
                                'skipped_count' => 0,
                                'skipped_rows' => [],
                                'skip_reasons_summary' => [],
                            ]);
                        } else {
                            $send('error', [
                                'message' => 'Job import ini sudah sedang diproses pada koneksi lain.',
                            ]);
                        }
                        return;
                    }
                }

                $filePath = $params['file_path'] ?? '';
                $delimiter = $params['delimiter'] ?? 'auto';
                $selectedColumns = array_map('intval', $params['selected_columns'] ?? []);
                $activeFilters = $params['normalized_filters'] ?? [];
                $tableName = $params['table_name'] ?? 'jumlah_merchant_detail';
                $syncPeriod = $params['sample_posisi'] ?? $params['sample_periode'] ?? null;
                $uniqueSuffix = $params['unique_suffix'] ?? '_MDT';
                $isBrilinkSummary = (bool) ($params['is_brilink_summary'] ?? false);
                $csvHeaders = $params['headers'] ?? [];
                $posisiIndex = (int) ($params['posisi_index'] ?? -1);
                $tahunIndex = (int) ($params['tahun_index'] ?? -1);
                $totalRows = (int) ($params['total_rows'] ?? 0);
                $duplicateLookup = $params['duplicate_lookup'] ?? [];
                $columnBlueprint = $isBrilinkSummary ? [] : $this->buildColumnImportBlueprint($selectedColumns, $csvHeaders);
                $batchSize = $this->resolveImportBatchSize($tableName);
                $progressStep = strtolower($tableName) === 'daily_loan_dinamis' ? 200 : 500;

                if ($filePath === '' || !file_exists($filePath)) {
                    $markJobFailed('File tidak ditemukan di server. Silakan upload ulang.');
                    $send('error', ['message' => 'File tidak ditemukan di server. Silakan upload ulang.']);
                    return;
                }

                try {
                    $this->bulkLoadService()->assertTransactionalTable($tableName, 'import CSV');
                } catch (\RuntimeException $e) {
                    $markJobFailed($e->getMessage());
                    $send('error', ['message' => $e->getMessage()]);
                    return;
                }

                $handle = fopen($filePath, 'r');
                if ($handle === false) {
                    $markJobFailed('File tidak dapat dibaca oleh server.');
                    $send('error', ['message' => 'File tidak dapat dibaca oleh server.']);
                    return;
                }

                $send('progress', [
                    'percent' => 5,
                    'message' => 'Menyiapkan stream import CSV...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $resolvedDelimiter = $this->resolveDelimiter($handle, $delimiter);
                rewind($handle);

                $send('progress', [
                    'percent' => 12,
                    'message' => "Delimiter terdeteksi. Memulai insert ke tabel `{$tableName}`...",
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $rowCounter = 0;
                $rowsDone = 0;
                $totalSuccess = 0;
                $totalFailed = 0;
                $lastErrorMsg = '';
                $buffer = [];
                $startTime = microtime(true);
                $lastProgressAt = 0;
                $duplicateSkipped = 0;

                $shouldInsertRow = function (array $row) use ($tableName, &$duplicateLookup, &$duplicateSkipped) {
                    if (!$this->isJumlahMerchantDetailTable($tableName)) {
                        return true;
                    }

                    $duplicateKey = $this->extractJumlahMerchantDuplicateKey($row);
                    if ($duplicateKey === null) {
                        return true;
                    }

                    [$periode, $tid] = $duplicateKey;
                    $lookupKey = $periode . '|' . $tid;

                    if (isset($duplicateLookup[$lookupKey])) {
                        $duplicateSkipped++;
                        return false;
                    }

                    $duplicateLookup[$lookupKey] = true;
                    return true;
                };

                $flushBuffer = function () use (&$buffer, &$totalSuccess, &$totalFailed, &$lastErrorMsg, $tableName, $batchSize) {
                    $this->flushInsertBuffer($buffer, $tableName, $totalSuccess, $totalFailed, $lastErrorMsg, null, $batchSize);
                };

                $stagingHandled = false;
                if ($this->shouldUseDbStagingFastPath()) {
                    $stagingHandled = $this->processImportStreamViaStagingTable(
                        $send,
                        $filePath,
                        $resolvedDelimiter,
                        $selectedColumns,
                        $activeFilters,
                        $tableName,
                        $uniqueSuffix,
                        $isBrilinkSummary,
                        $csvHeaders,
                        $posisiIndex,
                        $tahunIndex,
                        $totalRows,
                        $duplicateLookup,
                        $duplicateSkipped,
                        $totalSuccess,
                        $totalFailed,
                        $lastErrorMsg,
                        $jobId,
                        $columnBlueprint,
                        $batchSize
                    );
                }

                if ($stagingHandled) {
                    $finalStatus = $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed';

                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'status' => $finalStatus,
                        'total_success' => $totalSuccess,
                        'total_failed' => $totalFailed + $duplicateSkipped,
                        'updated_at' => now(),
                    ]);

                    if ($totalSuccess > 0) {
                        $this->syncReportArtifacts($tableName, $jobId, $syncPeriod);
                    }

                    $this->cleanupImportDirectory($filePath);

                    $send('complete', [
                        'total_success' => $totalSuccess,
                        'total_failed' => $totalFailed + $duplicateSkipped,
                        'total_rows' => $totalRows,
                        'error_message' => $lastErrorMsg,
                        'duplicates_skipped' => $duplicateSkipped,
                        'skipped_count' => 0,
                        'skipped_rows' => [],
                        'skip_reasons_summary' => [],
                    ]);
                    return;
                }

                if (is_resource($handle)) {
                    fclose($handle);
                    $handle = null;
                }

                $fallbackReason = '';

                if ($this->supportsNativeBulkLoad()) {
                    $strictHandled = $this->processImportStreamViaStrictLocalInfile(
                        $send,
                        $filePath,
                        $resolvedDelimiter,
                        $selectedColumns,
                        $activeFilters,
                        $tableName,
                        $uniqueSuffix,
                        $isBrilinkSummary,
                        $csvHeaders,
                        $posisiIndex,
                        $tahunIndex,
                        $totalRows,
                        $duplicateLookup,
                        $duplicateSkipped,
                        $totalSuccess,
                        $totalFailed,
                        $lastErrorMsg,
                        $jobId,
                        $columnBlueprint
                    );

                    if ($strictHandled) {
                        $finalStatus = $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed';

                        DB::table('import_jobs')->where('id', $jobId)->update([
                            'status' => $finalStatus,
                            'total_success' => $totalSuccess,
                            'total_failed' => $totalFailed + $duplicateSkipped,
                            'updated_at' => now(),
                        ]);

                        if ($totalSuccess > 0) {
                            $this->syncReportArtifacts($tableName, $jobId, $syncPeriod);
                        }

                        $this->cleanupImportDirectory($filePath);

                        $send('complete', [
                            'total_success' => $totalSuccess,
                            'total_failed' => $totalFailed + $duplicateSkipped,
                            'total_rows' => $totalRows,
                            'error_message' => $lastErrorMsg,
                            'duplicates_skipped' => $duplicateSkipped,
                            'skipped_count' => 0,
                            'skipped_rows' => [],
                            'skip_reasons_summary' => [],
                        ]);
                        return;
                    }

                    $fallbackReason = $lastErrorMsg !== ''
                        ? 'LOAD DATA LOCAL INFILE gagal. Lanjut fallback insert batch...'
                        : 'LOAD DATA LOCAL INFILE gagal. Lanjut fallback insert batch biasa...';
                } else {
                    $fallbackReason = 'LOCAL INFILE tidak aktif di MySQL/PDO. Lanjut fallback insert batch biasa...';
                }

                $handle = fopen($filePath, 'r');
                if ($handle === false) {
                    $markJobFailed('File tidak dapat dibaca ulang untuk fallback insert batch.', $totalSuccess, $totalFailed + $duplicateSkipped);
                    $send('error', [
                        'message' => 'File tidak dapat dibaca ulang untuk fallback insert batch.',
                    ]);
                    return;
                }

                $send('progress', [
                    'percent' => 12,
                    'message' => $fallbackReason,
                    'rows_done' => $rowsDone,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                while (($data = $this->readCsvRecord($handle, $resolvedDelimiter)) !== false) {
                    if (empty($data) || implode('', $data) === '') {
                        continue;
                    }

                    if ($rowCounter === 0) {
                        $rowCounter++;
                        continue;
                    }

                    $parsedRow = $this->parseCsvRow($data, $isBrilinkSummary, $csvHeaders, $posisiIndex, $tahunIndex);
                    if ($parsedRow === null || !$this->passesActiveFilters($parsedRow, $activeFilters)) {
                        $rowCounter++;
                        continue;
                    }

                    $mappedRow = $this->mapRowForInsert(
                        $parsedRow,
                        $selectedColumns,
                        $csvHeaders,
                        $isBrilinkSummary,
                        $uniqueSuffix,
                        $columnBlueprint,
                        $posisiIndex,
                        $tahunIndex
                    );
                    if ($mappedRow === null) {
                        $rowCounter++;
                        continue;
                    }

                    $mappedRow = $this->applyImportTimestamps($mappedRow, $tableName);

                    if (!$shouldInsertRow($mappedRow)) {
                        $rowCounter++;
                        continue;
                    }

                    $buffer[] = $mappedRow;
                    if (count($buffer) >= $batchSize) {
                        $flushBuffer();
                    }

                    $rowsDone++;
                    $rowCounter++;

                    if ($rowsDone - $lastProgressAt >= $progressStep) {
                        $lastProgressAt = $rowsDone;
                        $elapsed = max(microtime(true) - $startTime, 0.001);
                        $speed = (int) ($rowsDone / $elapsed);
                        $percent = $totalRows > 0
                            ? min(95, 12 + (int) (($rowsDone / $totalRows) * 83))
                            : 80;

                        $send('progress', [
                            'percent' => $percent,
                            'message' => "Menyimpan data ke database... ({$speed} baris/detik)",
                            'rows_done' => $rowsDone,
                            'total' => $totalRows,
                            'speed' => $speed,
                        ]);
                    }
                }

                fclose($handle);

                $flushBuffer();

                $finalStatus = $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed';

                DB::table('import_jobs')->where('id', $jobId)->update([
                    'status' => $finalStatus,
                    'total_success' => $totalSuccess,
                    'total_failed' => $totalFailed + $duplicateSkipped,
                    'updated_at' => now(),
                ]);

                if ($totalSuccess > 0) {
                    $this->syncReportArtifacts($tableName, $jobId, $syncPeriod);
                }

                $this->cleanupImportDirectory($filePath);

                $send('progress', [
                    'percent' => 98,
                    'message' => 'Finalisasi status import...',
                    'rows_done' => $rowsDone,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $send('complete', [
                    'total_success' => $totalSuccess,
                    'total_failed' => $totalFailed + $duplicateSkipped,
                    'total_rows' => $totalRows,
                    'error_message' => $lastErrorMsg,
                    'duplicates_skipped' => $duplicateSkipped,
                    'skipped_count' => 0,
                    'skipped_rows' => [],
                    'skip_reasons_summary' => [],
                ]);
            } catch (\Throwable $e) {
                Log::error('CSV STREAM ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);
                }
                $send('error', [
                    'message' => 'Fatal Error: ' . $e->getMessage(),
                ]);
            } finally {
                if ($streamLock) {
                    try {
                        $streamLock->release();
                    } catch (\Throwable $e) {
                        Log::warning('Failed to release CSV import stream lock for job ' . $jobId . ': ' . $e->getMessage());
                    }
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function processImport(Request $request)
    {
        $this->applySafeRuntimeLimits(streaming: true);
        DB::disableQueryLog();

        $request->validate([
            'file_path' => 'required|string',
            'selected_columns' => 'required|array|min:1',
            'active_filters_json' => 'nullable|string',
            'delimiter' => 'required|string'
        ]);

        $filePath = $request->input('file_path');
        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json'), true) ?: [];
        $displayToSourceMap = session('import_display_to_source_map', []);
        if (!empty($displayToSourceMap)) {
            $selectedColumns = array_values(array_map(function ($displayIndex) use ($displayToSourceMap) {
                return (int) ($displayToSourceMap[$displayIndex] ?? $displayIndex);
            }, $selectedColumns));
        }
        $normalizedFilters = [];
        foreach ($activeFilters as $columnIndex => $allowedValues) {
            $sourceIndex = !empty($displayToSourceMap) ? (int) ($displayToSourceMap[$columnIndex] ?? $columnIndex) : (int) $columnIndex;
            $normalizedFilters[$sourceIndex] = array_fill_keys(array_map(function ($value) {
                return trim((string) $value);
            }, (array) $allowedValues), true);
        }
        $currentDelimiter = $request->input('delimiter', 'auto');
        
        // 🔥 1. DETEKSI REPORT (WAJIB SAMA DENGAN PREVIEW)
        $idReport = session('active_id_report', 1);
        $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
        $isBrilinkSummary = false;
        $this->releaseSessionLockIfNeeded();

        if ($reportData && (stripos($reportData->nama_report, 'BRILINK Web - Laporan Summary Transaksi') !== false || stripos($reportData->nama_report, 'brilink_web') !== false)) {
            $isBrilinkSummary = true;
        }

        if (!file_exists($filePath)) {
            $response = [
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'File tidak ditemukan di server.'
            ];
            return $request->expectsJson()
                ? response()->json($response)
                : redirect()->route('import.index')->with('error', 'File tidak ditemukan.');
        }

        $tableName = $this->resolveTableName($reportData);

        try {
            $this->bulkLoadService()->assertTransactionalTable($tableName, 'import CSV');
        } catch (\RuntimeException $e) {
            $response = [
                'status' => 'error',
                'title' => 'Import Diblokir',
                'text' => $e->getMessage(),
            ];

            return $request->expectsJson()
                ? response()->json($response, 422)
                : redirect()->route('import.index')->with('sweet_warning', $response);
        }

        $uniqueSuffix = $this->resolveUniqueSuffix($tableName);

        $dataToInsert = [];
        $csvHeaders = [];
        $duplicateLookup = [];
        $columnBlueprint = [];
        $batchSize = $this->resolveImportBatchSize($tableName);

        $posisiIndex = -1;
        $tahunIndex = -1;

        if (filesize($filePath) > (25 * 1024 * 1024)) {
            $response = [
                'status' => 'warning',
                'title' => 'Gunakan Mode Streaming',
                'text' => 'File terlalu besar untuk diproses lewat fallback import biasa. Silakan jalankan import melalui tombol preview yang memakai progress streaming.',
            ];

            return $request->expectsJson()
                ? response()->json($response, 422)
                : redirect()->route('import.index')->with('sweet_warning', $response);
        }

        if (($handle = fopen($filePath, "r")) !== FALSE) {
            try {
                if ($currentDelimiter === 'auto') {
                $firstLine = fgets($handle);
                $delimiters = [',' => 0, ';' => 0, '|' => 0, "\t" => 0, '.' => 0];
                foreach ($delimiters as $delim => &$count) {
                    $count = substr_count($firstLine, $delim);
                }
                arsort($delimiters);
                $delimiter = key($delimiters);
                rewind($handle);
            } else {
                $delimiter = $currentDelimiter;
            }

            $rowCounter = 0;
            while (($data = $this->readCsvRecord($handle, $delimiter)) !== FALSE) {
                if (empty($data) || implode('', $data) === '') continue;

                // 🔥 2. SKIP HEADER DEFAULT (INI KRITIS)
                if ($rowCounter == 0) {
                    $csvHeaders = $isBrilinkSummary ? [] : $this->formatCsvHeaders($data, false);
                    if (!$isBrilinkSummary) {
                        foreach ($csvHeaders as $idx => $hdr) {
                            if (stripos($hdr, 'posisi') !== false) { $posisiIndex = $idx; }
                            if (stripos($hdr, 'tahun') !== false) { $tahunIndex = $idx; }
                        }

                        $columnBlueprint = $this->buildColumnImportBlueprint($selectedColumns, $csvHeaders);
                    }
                    
                    $rowCounter++;
                    continue; 
                }

                if (!$this->isDailyLoanReport($reportData) && (trim((string) $data[0]) === 'TAHUN' || stripos(trim((string) $data[0]), 'textbox') !== false)) continue;

                // 🔥 4. SKIP VALIDASI KOLOM HEADER SAAT BRILINK
                if (!$isBrilinkSummary) {
                    if (count($data) < count($csvHeaders)) {
                        $data = array_pad($data, count($csvHeaders), null);
                    }

                    if (count($data) > count($csvHeaders)) {
                        Log::warning('Kolom tidak sesuai', [
                            'expected' => count($csvHeaders),
                            'actual' => count($data),
                            'row' => $data
                        ]);
                        continue; 
                    }

                    // DATE RECONSTRUCTOR HANYA JIKA BUKAN BRILINK
                    if ($posisiIndex !== -1 && isset($data[$posisiIndex]) && trim($data[$posisiIndex]) !== '') {
                        $rawPosisi = trim($data[$posisiIndex]);
                        try {
                            if (strpos($rawPosisi, '/') !== false) {
                                $data[$posisiIndex] = StrictDateParser::normalize($rawPosisi);
                            } else {
                                if ($tahunIndex !== -1 && isset($data[$tahunIndex]) && trim($data[$tahunIndex]) !== '') {
                                    $rawTahun = trim($data[$tahunIndex]);
                                    if (preg_match('/^([a-zA-Z]+\s+\d+)/', $rawPosisi, $matches)) {
                                        $fixedDateStr = $matches[1] . ' ' . $rawTahun;
                                        $data[$posisiIndex] = StrictDateParser::normalize($fixedDateStr);
                                    } else {
                                        $data[$posisiIndex] = StrictDateParser::normalize($rawPosisi);
                                    }
                                } else {
                                    $data[$posisiIndex] = StrictDateParser::normalize($rawPosisi);
                                }
                            }
                        } catch (\Exception $e) {}
                    }
                }

                $filterData = $isBrilinkSummary
                    ? $this->transformBrilinkSummaryRow($data)
                    : $data;

                // FILTER AKTIF
                $passFilter = true;
                foreach ($normalizedFilters as $sourceIndex => $allowedValues) {
                    $cellValue = isset($filterData[$sourceIndex]) ? trim((string) $filterData[$sourceIndex]) : '';
                    if (!isset($allowedValues[$cellValue])) {
                        $passFilter = false;
                        break;
                    }
                }

                if (!$passFilter) {
                    $rowCounter++;
                    continue;
                }

                // 🔥 3. OVERRIDE TOTAL MAPPING (INI BAGIAN INTI)
                if ($isBrilinkSummary) {
                    // 🔥 PARSE PERIODE
                    $rawPeriode = $data[0] ?? '';
                    $periode = null;

                    if (strpos($rawPeriode, ':') !== false) {
                        $periode = trim(explode(':', $rawPeriode)[1]); // Output: "March 2026"
                    } else {
                        $periode = trim($rawPeriode);
                    }

                    $brilinkOffset = $this->hasManualNumberingColumn($data) ? 1 : 0;

                    $rowData = [
                        'uniqueid_namareport' => uniqid() . '_BST',
                        'periode' => $periode,

                        'kanwil' => trim($data[2 + $brilinkOffset] ?? null),
                        'cabang' => trim($data[4 + $brilinkOffset] ?? null),
                        'uker' => trim($data[6 + $brilinkOffset] ?? null),

                        'merchant_name' => trim($data[7 + $brilinkOffset] ?? null),
                        'merchant_code' => trim($data[8 + $brilinkOffset] ?? null),
                        'outlet_name' => trim($data[9 + $brilinkOffset] ?? null),
                        'outlet_code' => trim($data[10 + $brilinkOffset] ?? null),

                        'total_transaksi' => (int) preg_replace('/[^0-9]/', '', $data[11 + $brilinkOffset] ?? 0),

                        'total_nominal' => (float) preg_replace('/[^0-9.]/', '', $data[12 + $brilinkOffset] ?? 0),
                        'total_fee' => (float) preg_replace('/[^0-9.]/', '', $data[13 + $brilinkOffset] ?? 0),
                        'total_fee_bri' => (float) preg_replace('/[^0-9.]/', '', $data[14 + $brilinkOffset] ?? 0),
                    ];
                } else {
                    // 🔥 EXISTING LOGIC (JANGAN DIUBAH)
                    $rowData = [];
                    $rowData['uniqueid_namareport'] = uniqid() . $uniqueSuffix;

                    foreach ($columnBlueprint as $columnMeta) {
                        $index = $columnMeta['index'];
                        $cellValue = isset($data[$index]) ? trim((string) $data[$index]) : '';

                        if ($columnMeta['type'] === 'period') {
                            $cellValue = $this->normalizeImportPeriodValue(
                                $cellValue,
                                $posisiIndex !== -1 ? ($data[$posisiIndex] ?? null) : null,
                                $tahunIndex !== -1 ? ($data[$tahunIndex] ?? null) : null
                            );
                        } elseif ($columnMeta['type'] === 'date') {
                            $cellValue = $this->normalizeDailyLoanDate($cellValue);
                        } elseif ($columnMeta['type'] === 'numeric') {
                            $cellValue = $this->normalizeDecimalValue($cellValue);
                        }

                        $rowData[$columnMeta['column']] = ($cellValue === '') ? null : $cellValue;
                    }

                    $rowData = $this->applyDailyLoanCompatibilityColumns($rowData);
                }
                
                $dataToInsert[] = $this->applyImportTimestamps($rowData, $tableName);
                $rowCounter++;
            }
            } finally {
                fclose($handle);
            }
        }

        $samplePosisi = null;
        $samplePeriode = null;

        if (!empty($dataToInsert)) {
            $samplePosisi = $dataToInsert[0]['POSISI'] ?? null;
            $samplePeriode = $dataToInsert[0]['periode'] ?? ($dataToInsert[0]['POSISI'] ?? null);
        }

        $isDuplicate = false;
        $duplicateText = "";

        if ($this->isJumlahMerchantDetailTable($tableName)) {
            $periodeTidPairs = [];
            foreach ($dataToInsert as $rowData) {
                $duplicateKey = $this->extractJumlahMerchantDuplicateKey($rowData);
                if ($duplicateKey === null) {
                    continue;
                }

                [$periode, $tid] = $duplicateKey;
                $periodeTidPairs[$periode . '|' . $tid] = [
                    'periode' => $periode,
                    'tid' => $tid,
                ];
            }

            $duplicateLookup = $this->buildJumlahMerchantDuplicateLookup(array_values($periodeTidPairs));
            if (!empty($periodeTidPairs) && count($duplicateLookup) === count($periodeTidPairs)) {
                $isDuplicate = true;
                $duplicateText = "Semua kombinasi <b>PERIODE + TID</b> pada file ini sudah ada di tabel <b class='text-uppercase'>$tableName</b>.<br><br>Sistem membatalkan proses ini.";
            }
        } elseif ($isBrilinkSummary && $samplePeriode) {
            $isDuplicate = DB::table($tableName)->where('periode', $samplePeriode)->exists();
            if ($isDuplicate) {
                $duplicateText = "Data untuk PERIODE <b>$samplePeriode</b> sudah pernah diunggah sebelumnya ke tabel <b class='text-uppercase'>$tableName</b>.<br><br>Sistem membatalkan proses ini.";
            }
        } elseif ($samplePosisi) {
            $isDuplicate = DB::table($tableName)->whereDate('POSISI', $samplePosisi)->exists();
            if ($isDuplicate) {
                $duplicateText = "Data untuk tanggal POSISI <b>$samplePosisi</b> sudah pernah diunggah sebelumnya ke tabel <b class='text-uppercase'>$tableName</b>.<br><br>Sistem membatalkan proses ini.";
            }
        }

        if ($isDuplicate) {
            $this->cleanupImportDirectory($filePath);
            
            $response = [
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => $duplicateText,
                'duplicate_detected' => true,
                'redirect_url' => route('import.index'),
            ];
            
            return $request->expectsJson()
                ? response()->json($response)
                : redirect()->route('import.index')->with('sweet_warning', $response);
        }

        $jobId = app(\App\Services\Import\ImportProgressService::class)->createJob([
            'id_report' => $idReport,
            'file_name' => basename($filePath),
            'folder_path' => dirname($filePath),
            'status' => 'processing',
            'total_files' => count($dataToInsert),
            'created_by' => auth()->id() ?? 1,
            'job_context' => [
                'controller' => static::class,
                'mode' => 'file_import_preview',
                'table_name' => $tableName,
                'file_hash' => sha1($filePath),
                'data_rows_count' => count($dataToInsert),
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // import_mappings drop removed

        $totalSuccess = 0;
        $totalFailed = 0;
        $lastErrorMsg = '';
        $duplicateSkipped = 0;
        $shouldInsertRow = function (array $row) use ($tableName, &$duplicateLookup, &$duplicateSkipped) {
            if (!$this->isJumlahMerchantDetailTable($tableName)) {
                return true;
            }

            $duplicateKey = $this->extractJumlahMerchantDuplicateKey($row);
            if ($duplicateKey === null) {
                return true;
            }

            [$periode, $tid] = $duplicateKey;
            $lookupKey = $periode . '|' . $tid;

            if (isset($duplicateLookup[$lookupKey])) {
                $duplicateSkipped++;
                return false;
            }

            $duplicateLookup[$lookupKey] = true;
            return true;
        };

        $bulkLoadHandled = false;
        if ($this->supportsNativeBulkLoad()) {
            $filteredRows = array_values(array_filter($dataToInsert, static function (array $row) use ($shouldInsertRow) {
                return $shouldInsertRow($row) !== false;
            }));

            $bulkColumns = $this->buildBulkLoadColumnsForMappedRows($tableName, $isBrilinkSummary, $columnBlueprint);
            if (!empty($bulkColumns)) {
                $bulkCsvPath = $this->createBulkLoadTempCsvPath($tableName, $jobId);
                $bulkHandle = @fopen($bulkCsvPath, 'w');

                if ($bulkHandle !== false) {
                    try {
                        foreach ($filteredRows as $rowData) {
                            $bulkValues = $this->mapRowValuesForBulkLoad($rowData, $bulkColumns);
                            fputcsv($bulkHandle, array_map(static function ($value) {
                                return $value === null ? '\N' : $value;
                            }, $bulkValues));
                        }
                    } finally {
                        fclose($bulkHandle);
                    }

                    try {
                        $inserted = $this->loadCsvIntoMysqlChunked($bulkCsvPath, $tableName, $bulkColumns, null, 8000, count($filteredRows));
                        $totalSuccess = $inserted;
                        $totalFailed = max(0, count($filteredRows) - $inserted);
                        $bulkLoadHandled = true;
                    } catch (\Throwable $e) {
                        $lastErrorMsg = mb_substr($e->getMessage(), 0, 800) . '...';
                        Log::warning('LOAD DATA LOCAL INFILE gagal di processImport, fallback ke insert batch: ' . $e->getMessage(), [
                            'table' => $tableName,
                            'job_id' => $jobId,
                        ]);
                    } finally {
                        if (file_exists($bulkCsvPath)) {
                            @unlink($bulkCsvPath);
                        }
                    }
                }
            }
        }

        if (!$bulkLoadHandled) {
            $this->flushInsertBuffer($dataToInsert, $tableName, $totalSuccess, $totalFailed, $lastErrorMsg, $shouldInsertRow, $batchSize);
        }

        $finalStatus = $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed';
        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => $finalStatus,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed + $duplicateSkipped,
            'updated_at' => now(),
        ]);

        if ($totalSuccess > 0) {
            $this->syncReportArtifacts($tableName, $jobId, $samplePosisi ?: $samplePeriode);
        }

        $this->cleanupImportDirectory($filePath);

        if ($totalFailed > 0) {
            $response = [
                'status' => 'warning',
                'title' => 'Import Memiliki Kendala!',
                'text' => "Berhasil: $totalSuccess baris.<br>Gagal: $totalFailed baris.<br>" . ($duplicateSkipped > 0 ? "Duplikat dilewati: $duplicateSkipped baris.<br><br>" : '<br>') . "<b>Info MySQL:</b><br><small class='text-danger'>" . htmlspecialchars($lastErrorMsg, ENT_QUOTES) . "</small>"
            ];

            return $request->expectsJson()
                ? response()->json($response)
                : redirect()->route('import.index')->with('sweet_warning', $response);
        }

        $response = [
            'status' => 'success',
            'title' => 'Berhasil!',
            'text' => "Sebanyak $totalSuccess baris data telah sukses masuk ke tabel <b class='text-uppercase'>$tableName</b>." . ($duplicateSkipped > 0 ? "<br><small class='text-warning'>$duplicateSkipped baris duplikat PERIODE + TID dilewati.</small>" : '')
        ];

        return $request->expectsJson()
            ? response()->json($response)
            : redirect()->route('import.index')->with('sweet_success', $response);
    }

    private function syncReportArtifacts(string $tableName, int $jobId, ?string $periodHint = null): void
    {
        try {
            app(ReportDataSyncService::class)->syncImportedJob($jobId, $tableName, $periodHint, static::class);
        } catch (\Throwable $e) {
            Log::warning('Gagal sinkronisasi snapshot setelah import CSV: ' . $e->getMessage(), [
                'job_id' => $jobId,
                'table_name' => $tableName,
                'period_hint' => $periodHint,
            ]);
        }
    }
}
