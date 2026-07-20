<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Import\Concerns\AllocatesGapIds;
use App\Http\Controllers\Import\Concerns\SmartCsvImportSupport;
use App\Jobs\PrepareCsvStagingJob;
use App\Services\Import\ImportProgressService;
use App\Services\Import\ImportDuplicateGuardService;
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
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

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
    private const PREVIEW_INDEX_WARM_LOCK_SECONDS = 1800;
    private const IMPORT_BATCH_SIZE = 1000;
    private const DAILY_LOAN_IMPORT_BATCH_SIZE = 250;
    private const BULK_LOAD_TEMP_DIR = 'app/import_bulk';

    private function shouldUseDbStagingFastPath(): bool
    {
        return (bool) config('import.use_db_staging_fast_path', false);
    }

    private function shouldSkipRawLoadDataFastPath(string $tableName, string $filePath, string $delimiter): bool
    {

        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            return false;
        }

        try {
            $sampled = 0;
            while (($line = fgets($handle)) !== false && $sampled < 250) {
                $sampled++;
                if (!is_string($line) || strpos($line, '"') === false) {
                    continue;
                }

                // Raw LOAD DATA on vendor-exported files with free quotes inside unquoted fields
                // can shift columns. These files are safer through PHP parse -> temp CSV.
                return true;
            }
        } finally {
            fclose($handle);
        }

        return false;
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

    private function previewFilterCacheKey(string $filePath, string $delimiter, int $columnIndex, string $tableName, string $contextSignature = ''): string
    {
        return 'preview_filter_options:v4:' . md5($filePath . '|' . $delimiter . '|' . $columnIndex . '|' . $tableName . '|' . $contextSignature);
    }

    private function normalizeDisplayFilterMap(array $displayFilterMap, int $maxValidIndex = -1): array
    {
        $normalized = [];
        foreach ($displayFilterMap as $displayIdx => $sourceIdx) {
            $displayIdxInt = (int) $displayIdx;
            $sourceIdxInt = (int) $sourceIdx;
            if ($maxValidIndex === -1 || ($sourceIdxInt >= 0 && $sourceIdxInt <= $maxValidIndex)) {
                $normalized[$displayIdxInt] = $sourceIdxInt;
            }
        }
        return $normalized;
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

    private function isJumlahMerchantQrisDetailReport($reportData): bool
    {
        if (!$reportData) {
            return false;
        }

        return strtolower((string) ($reportData->table_name ?? '')) === 'jumlah_merchant_qris_detail';
    }

    private function isIbbizImportTable(string $tableName): bool
    {
        return in_array(strtolower(trim($tableName)), ['ibbisniz_corp', 'usak_ibbiz_uker'], true);
    }

    private function requiresManualIbbizPeriode(string $tableName): bool
    {
        return in_array(strtolower(trim($tableName)), ['ibbisniz_corp', 'usak_ibbiz_uker'], true);
    }

    private function normalizeManualImportPeriode(string $tableName, $value): ?string
    {
        if (!$this->isIbbizImportTable($tableName)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            if (!$this->requiresManualIbbizPeriode($tableName)) {
                return null;
            }

            throw new \InvalidArgumentException('Periode wajib diisi untuk import IB Biz.');
        }

        try {
            $normalized = StrictDateParser::normalize($value);
        } catch (\Throwable $e) {
            $normalized = null;
        }

        if ($normalized === null) {
            throw new \InvalidArgumentException('Format periode IB Biz tidak valid. Gunakan tanggal YYYY-MM-DD.');
        }

        return $normalized;
    }

    private function resolveManualImportPeriodeFromRequest(Request $request, string $tableName): ?string
    {
        if (!$this->isIbbizImportTable($tableName)) {
            return null;
        }

        return $this->normalizeManualImportPeriode(
            $tableName,
            $request->input('periode', session('generic_import_manual_periode'))
        );
    }

    private function applyManualImportPeriode(array $rowData, string $tableName, ?string $manualPeriode): array
    {
        if ($this->isIbbizImportTable($tableName) && $manualPeriode !== null) {
            $rowData['periode'] = $manualPeriode;
        }

        return $rowData;
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

    private function buildColumnImportBlueprint(array $selectedColumns, array $csvHeaders, string $tableName = ''): array
    {
        $blueprint = [];

        foreach ($selectedColumns as $index) {
            if (!isset($csvHeaders[$index])) {
                continue;
            }

            $colName = str_replace(' ', '_', $csvHeaders[$index]);
            $normalizedColName = $this->resolveMappedImportColumnName($tableName, (int) $index, $colName);

            if ($normalizedColName === null) {
                continue;
            }

            if ($normalizedColName === 'id' || $normalizedColName === 'uniqueid_namareport') {
                continue;
            }

            $type = 'string';
            if ($normalizedColName === 'periode') {
                $type = 'period';
            } elseif ($this->isDailyLoanDateColumn($normalizedColName)) {
                $type = 'date';
            } elseif (
                $this->isDailyLoanNumericColumn($normalizedColName)
                || in_array($normalizedColName, ['jml_trx_sukses', 'nominal', 'fee_transaksi'], true)
                || in_array(strtoupper($colName), self::NUMERIC_COLUMNS, true)
            ) {
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

    private function resolveMappedImportColumnName(string $tableName, int $index, string $header): ?string
    {
        $tableName = strtolower(trim($tableName));
        $normalizedHeader = $this->normalizeImportHeaderName($header);

        if ($tableName === 'ibbisniz_corp') {
            return match ($normalizedHeader) {
                'TEXTBOX10' => 'wilayah',
                'TEXTBOX11' => 'cabang',
                'TEXTBOX12' => 'uker',
                'TEXTBOX7' => 'corporate_id',
                'TEXTBOX13' => 'nama_perusahaan',
                'JUMLAHTRANSAKSI', 'JUMLAH_TRANSAKSI' => 'jml_trx_sukses',
                'NOMINAL' => 'nominal',
                'FEE' => 'fee_transaksi',
                default => $this->normalizeDailyLoanHeader($header),
            };
        }

        if ($tableName === 'usak_ibbiz_uker') {
            return match ($index) {
                0, 1 => null,
                2 => 'kanwil',
                3 => 'kanca',
                4 => 'uker',
                5 => 'corporate_id',
                6 => 'nama_perusahaan',
                7 => 'status',
                8 => 'deskripsi',
                9 => 'referral',
                default => $this->normalizeDailyLoanHeader($header),
            };
        }

        return $this->normalizeDailyLoanHeader($header);
    }

    private function sortFilterValues(array &$values): void
    {
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);
    }

    private function getPrettyIbbizHeaders(string $tableName, array $headers): array
    {
        $tableName = strtolower(trim($tableName));

        if ($tableName === 'ibbisniz_corp') {
            $mappedHeaders = [];
            foreach ($headers as $index => $h) {
                $norm = strtoupper(trim((string) $h));
                $mappedHeaders[] = match ($norm) {
                    'TEXTBOX10' => 'Wilayah',
                    'TEXTBOX11' => 'Cabang',
                    'TEXTBOX12' => 'Uker',
                    'TEXTBOX7' => 'Corporate ID',
                    'TEXTBOX13' => 'Nama Perusahaan',
                    'JUMLAHTRANSAKSI', 'JUMLAH_TRANSAKSI' => 'Jml Trx Sukses',
                    'NOMINAL' => 'Nominal',
                    'FEE' => 'Fee Transaksi',
                    default => ucwords(strtolower(str_replace('_', ' ', $h))),
                };
            }
            return $mappedHeaders;
        }

        if ($tableName === 'usak_ibbiz_uker') {
            $mappedHeaders = [];
            foreach ($headers as $index => $h) {
                $mappedHeaders[] = match ($index) {
                    0 => 'Periode',
                    1 => 'No',
                    2 => 'Kanwil',
                    3 => 'Kanca',
                    4 => 'Uker',
                    5 => 'Corporate ID',
                    6 => 'Nama Perusahaan',
                    7 => 'Status',
                    8 => 'Deskripsi',
                    9 => 'Referral',
                    default => ucwords(strtolower(str_replace('_', ' ', $h))),
                };
            }
            return $mappedHeaders;
        }

        return $headers;
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
        $hasExplicitTableName = false;

        if ($reportData) {
            if (!empty($reportData->table_name)) {
                $tableName = $reportData->table_name;
                $hasExplicitTableName = true;
            } else {
                $tableName = strtolower(str_replace(' ', '_', $reportData->nama_report));
            }
        }

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            if ($reportData || $hasExplicitTableName) {
                throw new \RuntimeException("Tabel tujuan import `{$tableName}` tidak ditemukan. Periksa konfigurasi nama_report.table_name.");
            }

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

        if ($tableName === 'brilink_web_laporan_summary_transaksi_brilink_web') {
            return '_BST';
        }

        if ($tableName === 'ibbisniz_corp') {
            return '_IBBC';
        }

        if ($tableName === 'usak_ibbiz_uker') {
            return '_UIBU';
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

    private function normalizeImportHeaderName(string $header): string
    {
        $normalized = preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($header)));

        return trim((string) $normalized, '_');
    }

    private function detectImportDateHeaderIndexes(array $headers): array
    {
        $posisiIndex = -1;
        $tahunIndex = -1;

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeImportHeaderName((string) $header);

            if ($posisiIndex === -1 && in_array($normalized, ['POSISI', 'TGL_POSISI', 'TANGGAL_POSISI'], true)) {
                $posisiIndex = (int) $index;
            }

            if ($tahunIndex === -1 && $normalized === 'TAHUN') {
                $tahunIndex = (int) $index;
            }
        }

        return [$posisiIndex, $tahunIndex];
    }

    private function normalizeImportDateHeaderIndexes(array $headers, int $posisiIndex, int $tahunIndex): array
    {
        [$detectedPosisiIndex, $detectedTahunIndex] = $this->detectImportDateHeaderIndexes($headers);

        return [
            $detectedPosisiIndex !== -1 ? $detectedPosisiIndex : $posisiIndex,
            $detectedTahunIndex !== -1 ? $detectedTahunIndex : $tahunIndex,
        ];
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

    private function resolveIbbizBulkPeriodeValue(string $tableName, array $sourceData, ?string $manualPeriode): ?string
    {
        $tableName = strtolower(trim($tableName));

        if ($tableName === 'ibbisniz_corp') {
            return $manualPeriode;
        }

        if ($tableName === 'usak_ibbiz_uker') {
            if ($manualPeriode !== null && $manualPeriode !== '') {
                return $manualPeriode;
            }

            $rawPeriode = trim((string) ($sourceData[0] ?? ''));
            if ($rawPeriode === '') {
                return $manualPeriode;
            }

            try {
                return StrictDateParser::normalize($rawPeriode);
            } catch (\Throwable) {
                return $manualPeriode;
            }
        }

        return null;
    }

    public function applyIbbizPeriodBulkUpdate(int $jobId, string $tableName, ?string $periodCsvPath = null): int
    {
        $tableName = strtolower(trim($tableName));
        if (!$this->isIbbizImportTable($tableName) || !$this->cachedSchemaHasColumn($tableName, 'periode')) {
            return 0;
        }

        if ($periodCsvPath === null || $periodCsvPath === '') {
            $params = Cache::get("csv_import_params_{$jobId}", []);
            $periodCsvPath = (string) ($params['ibbiz_period_bulk_csv'] ?? Cache::get("ibbiz_period_bulk_csv:{$jobId}", ''));
        }

        if ($periodCsvPath === '' || !is_file($periodCsvPath)) {
            return 0;
        }

        $stagingTable = null;

        try {
            $stagingTable = $this->createCsvStagingTable('tmp_file_csv_stage', $jobId, 2);
            $this->loadCsvIntoStagingTable($periodCsvPath, $stagingTable, 2, ',', 0);

            $quotedTarget = '`' . str_replace('`', '``', $tableName) . '`';
            $quotedStage = '`' . str_replace('`', '``', $stagingTable) . '`';

            return DB::affectingStatement(
                "UPDATE {$quotedTarget} target
                 INNER JOIN {$quotedStage} src
                    ON target.`uniqueid_namareport` = CONVERT(src.`c0` USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 SET target.`periode` = STR_TO_DATE(NULLIF(src.`c1`, ''), '%Y-%m-%d')
                 WHERE src.`c1` IS NOT NULL
                   AND src.`c1` <> ''
                   AND (target.`periode` IS NULL OR target.`periode` <> STR_TO_DATE(src.`c1`, '%Y-%m-%d'))"
            );
        } finally {
            $this->dropCsvStagingTable($stagingTable);
            if (is_file($periodCsvPath)) {
                @unlink($periodCsvPath);
            }
            Cache::forget("ibbiz_period_bulk_csv:{$jobId}");
        }
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

        if (!preg_match('/^tmp_file_csv_stage_\d+_[a-z0-9]+$/', $tableName)) {
            Log::critical('Blocked unsafe CSV staging table drop.', [
                'table' => $tableName,
            ]);

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
        ?string $manualPeriode,
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
            if ($this->shouldSkipDuplicateImportRow($tableName, $row, $duplicateLookup)) {
                $duplicateSkipped++;
                return false;
            }

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
            $cachedBlueprint = !$isBrilinkSummary ? ($columnBlueprint ?? $this->buildColumnImportBlueprint($selectedColumns, $csvHeaders, $tableName)) : null;
            
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

                    $mappedRow = $this->applyManualImportPeriode($mappedRow, $tableName, $manualPeriode);
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
        ?string $manualPeriode,
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
            if ($this->shouldSkipDuplicateImportRow($tableName, $row, $duplicateLookup)) {
                $duplicateSkipped++;
                return false;
            }

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

                if (
                    !$isBrilinkSummary
                    && !empty($activeFilters)
                    && !$this->passesActiveFiltersFast($data, $activeFilters, false, (int) $posisiIndex, (int) $tahunIndex)
                ) {
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

                $mappedRow = $this->applyManualImportPeriode($mappedRow, $tableName, $manualPeriode);
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

    public function prepareCsvStaging(int $jobId, array $params, ImportProgressService $progressService): string
    {
        $filePath = $params['filePath'] ?? $params['file_path'] ?? null;
        $resolvedDelimiter = $params['delimiter'] ?? ',';
        $selectedColumns = $params['selectedColumns'] ?? $params['selected_columns'] ?? [];
        $activeFilters = $params['normalized_filters'] ?? $params['activeFilters'] ?? $params['active_filters'] ?? [];
        $tableName = $params['tableName'] ?? $params['table_name'] ?? '';
        $uniqueSuffix = $params['uniqueSuffix'] ?? $params['unique_suffix'] ?? '';
        $isBrilinkSummary = $params['isBrilinkSummary'] ?? $params['is_brilink_summary'] ?? false;
        $manualPeriode = $this->normalizeManualImportPeriode($tableName, $params['manual_periode'] ?? null);
        $csvHeaders = $params['csvHeaders'] ?? $params['headers'] ?? [];
        $posisiIndex = $params['posisiIndex'] ?? $params['posisi_index'] ?? -1;
        $tahunIndex = $params['tahunIndex'] ?? $params['tahun_index'] ?? -1;
        $totalRows = $params['totalRows'] ?? $params['total_rows'] ?? 0;
        $columnBlueprint = $params['columnBlueprint'] ?? $params['column_blueprint'] ?? [];
        $duplicateLookup = $params['duplicateLookup'] ?? $params['duplicate_lookup'] ?? [];
        if (!is_array($duplicateLookup)) {
            $duplicateLookup = [];
        }

        if (!$isBrilinkSummary) {
            [$posisiIndex, $tahunIndex] = $this->normalizeImportDateHeaderIndexes(
                $csvHeaders,
                (int) $posisiIndex,
                (int) $tahunIndex
            );
        }

        if (!$isBrilinkSummary && empty($columnBlueprint)) {
            $columnBlueprint = $this->buildColumnImportBlueprint($selectedColumns, $csvHeaders, $tableName);
        }

        $bulkColumns = $this->buildBulkLoadColumnsForMappedRows($tableName, $isBrilinkSummary, $columnBlueprint);
        if (empty($bulkColumns)) {
            throw new \Exception('Kolom bulk load kosong untuk tabel tujuan.');
        }

        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception('File CSV tidak dapat dibaca.');
        }

        $bulkCsvPath = $this->createBulkLoadTempCsvPath($tableName, max(0, $jobId));
        $bulkHandle = @fopen($bulkCsvPath, 'w');
        if ($bulkHandle === false) {
            fclose($handle);
            throw new \Exception('Gagal membuat file staging CSV sementara.');
        }

        $periodBulkCsvPath = '';
        $periodBulkHandle = null;
        $periodRows = 0;
        if ($this->isIbbizImportTable($tableName) && $this->cachedSchemaHasColumn($tableName, 'periode')) {
            $periodBulkCsvPath = $this->createBulkLoadTempCsvPath($tableName . '_periode', max(0, $jobId));
            $periodBulkHandle = @fopen($periodBulkCsvPath, 'w');
            if ($periodBulkHandle === false) {
                fclose($handle);
                fclose($bulkHandle);
                throw new \Exception('Gagal membuat file staging periode IB Biz.');
            }
        }

        $rowCounter = 0;
        $rowsDone = 0;
        $lastProgressAt = 0;
        $startTime = microtime(true);
        $progressStep = strtolower($tableName) === 'daily_loan_dinamis' ? 1000 : 2000;

        $csvFormatter = static function ($value): string {
            return $value === null ? '\N' : $value;
        };

        $shouldInsertRow = function (array $row) use ($tableName, &$duplicateLookup) {
            return !$this->shouldSkipDuplicateImportRow($tableName, $row, $duplicateLookup);
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
                fputcsv($bulkHandle, array_map($csvFormatter, $bulkValues));

                if (is_resource($periodBulkHandle)) {
                    $bulkPeriode = $this->resolveIbbizBulkPeriodeValue($tableName, $parsedRow, $manualPeriode);
                    if ($bulkPeriode !== null) {
                        fputcsv($periodBulkHandle, [
                            $mappedRow['uniqueid_namareport'] ?? null,
                            $bulkPeriode,
                        ]);
                        $periodRows++;
                    }
                }

                $rowsDone++;
                $rowCounter++;

                if ($rowsDone - $lastProgressAt >= $progressStep) {
                    $lastProgressAt = $rowsDone;
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) ($rowsDone / $elapsed);
                    $percent = $totalRows > 0
                        ? min(40, 5 + (int) (($rowsDone / $totalRows) * 35))
                        : 30;

                    $progressService->cacheProgress($jobId, [
                        'status' => 'staging',
                        'percent' => $percent,
                        'message' => "Menyiapkan CSV staging... ({$speed} baris/detik)",
                        'rows_done' => $rowsDone,
                        'total' => $totalRows,
                        'speed' => $speed,
                    ]);
                }
            }

            fclose($bulkHandle);
            $bulkHandle = null;
            if (is_resource($periodBulkHandle)) {
                fclose($periodBulkHandle);
                $periodBulkHandle = null;
            }
            fclose($handle);
            $handle = null;

            $progressService->cacheProgress($jobId, [
                'status' => 'staging',
                'percent' => 45,
                'message' => 'CSV staging siap. Menunggu antrian LOAD DATA...',
                'rows_done' => $rowsDone,
                'total' => $totalRows > 0 ? $totalRows : $rowsDone,
            ]);

            $cacheStore = trim((string) config('import.cache_store', 'file'));
            $cache = $cacheStore !== '' ? Cache::store($cacheStore) : Cache::store();
            $preparedParams = array_merge($params, [
                'tableName' => $tableName,
                'bulkColumns' => $bulkColumns,
                'bulk_columns' => $bulkColumns,
                'prepared_rows' => $rowsDone,
                'ibbiz_period_bulk_csv' => $periodRows > 0 ? $periodBulkCsvPath : null,
                'ibbiz_period_rows' => $periodRows,
            ]);
            $cache->put("csv_import_params_{$jobId}", $preparedParams, now()->addHours(2));
            Cache::put("csv_import_params_{$jobId}", $preparedParams, now()->addHours(2));

            if ($periodRows > 0) {
                Cache::put("ibbiz_period_bulk_csv:{$jobId}", $periodBulkCsvPath, now()->addHours(2));
            } elseif ($periodBulkCsvPath !== '' && file_exists($periodBulkCsvPath)) {
                @unlink($periodBulkCsvPath);
            }

            return $bulkCsvPath;

        } catch (\Throwable $e) {
            if (file_exists($bulkCsvPath)) {
                @unlink($bulkCsvPath);
            }
            if ($periodBulkCsvPath !== '' && file_exists($periodBulkCsvPath)) {
                @unlink($periodBulkCsvPath);
            }
            throw $e;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_resource($bulkHandle)) {
                fclose($bulkHandle);
            }
            if (is_resource($periodBulkHandle)) {
                fclose($periodBulkHandle);
            }
        }
    }

    private function isJumlahMerchantDetailTable(string $tableName): bool
    {
        return strtolower($tableName) === 'jumlah_merchant_detail';
    }

    private function isBrilinkSummaryTable(string $tableName): bool
    {
        return strtolower($tableName) === 'brilink_web_laporan_summary_transaksi_brilink_web';
    }

    private function extractJumlahMerchantDuplicateKey(array $rowData): ?array
    {
        $periode = trim((string) (
            $rowData['POSISI']
            ?? $rowData['posisi']
            ?? $rowData['PERIODE']
            ?? $rowData['periode']
            ?? ''
        ));
        $tid = trim((string) ($rowData['TID'] ?? ''));

        if ($periode === '' || $tid === '') {
            return null;
        }

        return [$this->normalizeJumlahMerchantDuplicatePeriod($periode), $tid];
    }

    private function normalizeJumlahMerchantDuplicatePeriod(string $periode): string
    {
        $normalized = StrictDateParser::normalize($periode);

        return $normalized ?? trim($periode);
    }

    private function buildJumlahMerchantDuplicateLookup(array $periodeTidPairs): array
    {
        $normalizedPairs = [];
        $periods = [];
        $periodQueryValues = [];

        foreach ($periodeTidPairs as $pair) {
            $periode = trim((string) ($pair['periode'] ?? ''));
            $tid = trim((string) ($pair['tid'] ?? ''));
            if ($periode === '' || $tid === '') {
                continue;
            }

            $normalizedPeriod = $this->normalizeJumlahMerchantDuplicatePeriod($periode);
            $normalizedPairs[$normalizedPeriod . '|' . $tid] = true;
            $periods[$normalizedPeriod] = true;
            $periodQueryValues[$periode] = true;
            $periodQueryValues[$normalizedPeriod] = true;
            foreach ($this->buildJumlahMerchantDuplicatePeriodVariants($normalizedPeriod) as $periodVariant) {
                $periodQueryValues[$periodVariant] = true;
            }
        }

        if (empty($normalizedPairs) || empty($periods)) {
            return [];
        }

        $selectColumns = ['PERIODE', 'TID'];
        $hasPosisiColumn = $this->cachedSchemaHasColumn('jumlah_merchant_detail', 'POSISI');
        if ($hasPosisiColumn) {
            $selectColumns[] = 'POSISI';
        }

        $lookup = [];
        $existingRows = DB::table('jumlah_merchant_detail')
            ->select($selectColumns)
            ->whereNotNull('TID')
            ->where(function ($query) use ($periodQueryValues, $periods, $hasPosisiColumn) {
                $query->whereIn('PERIODE', array_keys($periodQueryValues));

                if ($hasPosisiColumn) {
                    foreach (array_keys($periods) as $normalizedPeriod) {
                        $query->orWhereDate('POSISI', $normalizedPeriod);
                    }
                }
            })
            ->get();

        foreach ($existingRows as $existingRow) {
            $rawPeriod = $hasPosisiColumn ? trim((string) ($existingRow->POSISI ?? '')) : '';
            $rawPeriod = $rawPeriod !== '' ? $rawPeriod : trim((string) ($existingRow->PERIODE ?? ''));

            $periode = $this->normalizeJumlahMerchantDuplicatePeriod($rawPeriod);
            $tid = trim((string) ($existingRow->TID ?? ''));
            $key = $periode . '|' . $tid;

            if (isset($normalizedPairs[$key])) {
                $lookup[$key] = true;
            }
        }

        return $lookup;
    }

    /**
     * @return array<int, string>
     */
    private function buildJumlahMerchantDuplicatePeriodVariants(string $periode): array
    {
        $normalized = StrictDateParser::normalize($periode);
        if ($normalized === null) {
            return [];
        }

        try {
            $date = Carbon::parse($normalized);
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_unique(array_filter([
            $normalized,
            $date->format('d M y'),
            $date->format('j M y'),
            $date->format('d-M-y'),
            $date->format('j-M-y'),
            $date->format('d M Y'),
            $date->format('j M Y'),
            $date->format('d-M-Y'),
            $date->format('j-M-Y'),
        ], static fn (string $value): bool => trim($value) !== '')));
    }

    private function extractBrilinkSummaryDuplicateKey(array $rowData): ?array
    {
        $periode = trim((string) ($rowData['periode'] ?? $rowData[0] ?? ''));
        $merchantCode = trim((string) ($rowData['merchant_code'] ?? $rowData[8] ?? ''));
        $outletCode = trim((string) ($rowData['outlet_code'] ?? $rowData[10] ?? ''));

        if ($periode === '' || $merchantCode === '' || $outletCode === '') {
            return null;
        }

        return [$periode, $merchantCode, $outletCode];
    }

    private function buildBrilinkSummaryDuplicateLookup(array $summaryKeys): array
    {
        $normalizedKeys = [];
        $periods = [];

        foreach ($summaryKeys as $keyData) {
            $periode = trim((string) ($keyData['periode'] ?? ''));
            $merchantCode = trim((string) ($keyData['merchant_code'] ?? ''));
            $outletCode = trim((string) ($keyData['outlet_code'] ?? ''));

            if ($periode === '' || $merchantCode === '' || $outletCode === '') {
                continue;
            }

            $normalizedKeys[$periode . '|' . $merchantCode . '|' . $outletCode] = true;
            $periods[$periode] = true;
        }

        if ($normalizedKeys === [] || $periods === []) {
            return [];
        }

        $lookup = [];
        $existingRows = DB::table('brilink_web_laporan_summary_transaksi_brilink_web')
            ->select(['periode', 'merchant_code', 'outlet_code'])
            ->whereIn('periode', array_keys($periods))
            ->whereNotNull('periode')
            ->whereNotNull('merchant_code')
            ->whereNotNull('outlet_code')
            ->get();

        foreach ($existingRows as $existingRow) {
            $periode = trim((string) ($existingRow->periode ?? ''));
            $merchantCode = trim((string) ($existingRow->merchant_code ?? ''));
            $outletCode = trim((string) ($existingRow->outlet_code ?? ''));
            $key = $periode . '|' . $merchantCode . '|' . $outletCode;

            if (isset($normalizedKeys[$key])) {
                $lookup[$key] = true;
            }
        }

        return $lookup;
    }

    private function shouldSkipDuplicateImportRow(string $tableName, array $row, array &$duplicateLookup): bool
    {
        $duplicateKey = null;

        if ($this->isJumlahMerchantDetailTable($tableName)) {
            $duplicateKey = $this->extractJumlahMerchantDuplicateKey($row);
        } elseif ($this->isBrilinkSummaryTable($tableName)) {
            $duplicateKey = $this->extractBrilinkSummaryDuplicateKey($row);
        }

        if ($duplicateKey === null) {
            return false;
        }

        $lookupKey = implode('|', $duplicateKey);
        if (isset($duplicateLookup[$lookupKey])) {
            return true;
        }

        $duplicateLookup[$lookupKey] = true;
        return false;
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

    private function collectFilterOptionsFromCsvFast(
        $handle,
        string $delimiter,
        int $sourceColumnIndex,
        array $activeFilters,
        bool $isBrilinkSummary = false
    ): array {
        $valuesMap = [];
        $headers = [];
        $rowCounter = 0;

        while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
            if (empty($row) || implode('', $row) === '') {
                continue;
            }

            if ($rowCounter === 0) {
                $headers = $this->formatCsvHeaders($row, $isBrilinkSummary);
                if (!isset($headers[$sourceColumnIndex])) {
                    return [];
                }

                $rowCounter++;
                continue;
            }

            if (!$isBrilinkSummary) {
                $firstCell = trim((string) ($row[0] ?? ''));
                if ($firstCell === 'TAHUN' || stripos($firstCell, 'textbox') !== false) {
                    continue;
                }
            }

            $row = $isBrilinkSummary ? $this->transformBrilinkSummaryRow($row) : $row;
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), null);
            } elseif (count($row) > count($headers)) {
                continue;
            }

            if (!$this->passesActiveFilters($row, $activeFilters)) {
                continue;
            }

            $valuesMap[trim((string) ($row[$sourceColumnIndex] ?? ''))] = true;
        }

        $values = array_keys($valuesMap);
        $this->sortFilterValues($values);

        return $values;
    }

    private function passesActiveFiltersFast(
        array $row,
        array $activeFilters,
        bool $isBrilinkSummary = false,
        int $posisiIndex = -1,
        int $tahunIndex = -1
    ): bool {
        foreach ($activeFilters as $colIdx => $allowedValues) {
            $cellValue = isset($row[$colIdx]) ? trim((string) $row[$colIdx]) : '';

            if (!$isBrilinkSummary && $colIdx === $posisiIndex && $cellValue !== '') {
                try {
                    if (strpos($cellValue, '/') !== false) {
                        $cellValue = StrictDateParser::normalize($cellValue);
                    } elseif ($tahunIndex !== -1 && isset($row[$tahunIndex]) && trim((string) $row[$tahunIndex]) !== '') {
                        $rawTahun = trim((string) $row[$tahunIndex]);
                        if (preg_match('/^([a-zA-Z]+\s+\d+)/', $cellValue, $matches)) {
                            $cellValue = StrictDateParser::normalize($matches[1] . ' ' . $rawTahun);
                        } else {
                            $cellValue = StrictDateParser::normalize($cellValue);
                        }
                    } else {
                        $cellValue = StrictDateParser::normalize($cellValue);
                    }
                } catch (\Throwable $e) {
                }
            }

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
                $cellValue = $this->normalizeDecimalValue(
                    $cellValue,
                    ($columnMeta['column'] ?? '') === 'rate' ? 6 : 2
                );
            } else {
                $cellValue = $this->normalizeImportedString($cellValue);
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
        $brilinkSummaryKeys = [];
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
                        [$posisiIndex, $tahunIndex] = $this->detectImportDateHeaderIndexes($headers);
                    } else {
                        $posisiIndex = -1;
                        $tahunIndex = -1;
                    }

                    foreach ($headers as $i => $header) {
                        if (strcasecmp(trim((string) $header), 'PERIODE') === 0) {
                            $periodeIndex = $i;
                        }
                        if (strcasecmp(trim((string) $header), 'TID') === 0) {
                            $tidIndex = $i;
                        }
                    }

                    $rowCounter++;
                    continue;
                }

                if (
                    !$isBrilinkSummary
                    && !empty($activeFilters)
                    && !$this->passesActiveFiltersFast($data, $activeFilters, false, $posisiIndex, $tahunIndex)
                ) {
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
                    $periodeCandidate = trim((string) $periodeCandidate);
                    $samplePeriode = $periodeCandidate !== ''
                        ? ($this->normalizeJumlahMerchantDuplicatePeriod($periodeCandidate) ?: $periodeCandidate)
                        : null;
                }

                if ($samplePosisi === null && $posisiIndex !== -1 && isset($parsedRow[$posisiIndex])) {
                    $posisiCandidate = trim((string) $parsedRow[$posisiIndex]);
                    $samplePosisi = $posisiCandidate !== ''
                        ? ($this->normalizeJumlahMerchantDuplicatePeriod($posisiCandidate) ?: $posisiCandidate)
                        : null;
                }

                $duplicatePeriodIndex = $posisiIndex !== -1 ? $posisiIndex : $periodeIndex;
                if ($collectDuplicatePairs && $duplicatePeriodIndex !== -1 && $tidIndex !== -1) {
                    $periodeValue = $this->normalizeJumlahMerchantDuplicatePeriod(trim((string) ($parsedRow[$duplicatePeriodIndex] ?? '')));
                    $tidValue = trim((string) ($parsedRow[$tidIndex] ?? ''));

                    if ($periodeValue !== '' && $tidValue !== '') {
                        $periodeTidPairs[$periodeValue . '|' . $tidValue] = [
                            'periode' => $periodeValue,
                            'tid' => $tidValue,
                        ];
                    }
                }

                if ($isBrilinkSummary) {
                    $summaryKey = $this->extractBrilinkSummaryDuplicateKey($parsedRow);
                    if ($summaryKey !== null) {
                        [$periodeValue, $merchantCode, $outletCode] = $summaryKey;
                        $brilinkSummaryKeys[$periodeValue . '|' . $merchantCode . '|' . $outletCode] = [
                            'periode' => $periodeValue,
                            'merchant_code' => $merchantCode,
                            'outlet_code' => $outletCode,
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
            'brilink_summary_keys' => array_values($brilinkSummaryKeys),
        ];
    }

    private function resolveImportStreamParams(int $jobId, array $params): array
    {
        if ($jobId <= 0) {
            return $params;
        }

        $filePath = trim((string) ($params['file_path'] ?? $params['filePath'] ?? ''));
        if ($filePath !== '' && is_file($filePath)) {
            return $params;
        }

        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        if (!$job) {
            return $params;
        }

        $context = [];
        if (is_string($job->job_context ?? null) && trim((string) $job->job_context) !== '') {
            $decoded = json_decode((string) $job->job_context, true);
            $context = is_array($decoded) ? $decoded : [];
        }

        $contextParams = $context['import_params'] ?? null;
        if (is_array($contextParams) && trim((string) ($contextParams['file_path'] ?? '')) !== '') {
            $resolved = array_merge($contextParams, ['job_id' => $jobId]);
            Cache::put('csv_import_params_' . $jobId, $resolved, now()->addHours(4));

            return $resolved;
        }

        $sourcePath = $this->resolveImportJobSourcePath($job);
        if ($sourcePath === null || !is_file($sourcePath)) {
            return $params;
        }

        $tableName = trim((string) ($context['table_name'] ?? ''));
        if ($tableName === '') {
            $report = DB::table('nama_report')->where('id_report', (int) ($job->id_report ?? 0))->first();
            $tableName = $report ? $this->resolveTableName($report) : 'jumlah_merchant_detail';
        }

        $isBrilinkSummary = $tableName === 'brilink_web_laporan_summary_transaksi_brilink_web';
        $meta = $this->collectImportMeta($sourcePath, [], [], 'auto', $isBrilinkSummary, $this->isJumlahMerchantDetailTable($tableName));
        $normalizedFilters = [];
        $selectedColumns = $this->inferImportSelectedColumns($tableName, $meta['headers'] ?? []);

        $resolved = [
            'job_id' => $jobId,
            'file_path' => $sourcePath,
            'delimiter' => $meta['delimiter'] ?? 'auto',
            'selected_columns' => $selectedColumns,
            'active_filters' => $normalizedFilters,
            'normalized_filters' => $normalizedFilters,
            'table_name' => $tableName,
            'unique_suffix' => $this->resolveUniqueSuffix($tableName),
            'is_brilink_summary' => $isBrilinkSummary,
            'headers' => $meta['headers'] ?? [],
            'posisi_index' => (int) ($meta['posisi_index'] ?? -1),
            'tahun_index' => (int) ($meta['tahun_index'] ?? -1),
            'total_rows' => (int) ($meta['total_rows'] ?? ($job->total_files ?? 0)),
            'sample_posisi' => $meta['sample_posisi'] ?? null,
            'sample_periode' => $meta['sample_periode'] ?? null,
            'duplicate_lookup' => [],
        ];

        Cache::put('csv_import_params_' . $jobId, $resolved, now()->addHours(4));

        return $resolved;
    }

    private function resolveImportJobSourcePath(object $job): ?string
    {
        $folderPath = trim((string) ($job->folder_path ?? ''));
        $fileName = trim((string) ($job->file_name ?? ''));
        if ($folderPath === '' || $fileName === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $folderPath) === 1 || str_starts_with($folderPath, '\\\\')) {
            return rtrim($folderPath, "\\/") . DIRECTORY_SEPARATOR . $fileName;
        }

        return storage_path('app/' . trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $folderPath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName);
    }

    private function inferImportSelectedColumns(string $tableName, array $headers): array
    {
        if ($headers === []) {
            return [];
        }

        if ($tableName === 'brilink_web_laporan_summary_transaksi_brilink_web') {
            return range(0, max(0, count($headers) - 1));
        }

        $tableColumns = array_fill_keys(array_map(
            static fn ($column): string => strtolower((string) $column),
            $this->cachedSchemaColumnListing($tableName)
        ), true);

        $selected = [];
        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeDailyLoanHeader(str_replace(' ', '_', (string) $header));
            if ($normalized === '' || $normalized === 'id' || $normalized === 'uniqueid_namareport') {
                continue;
            }

            if (isset($tableColumns[strtolower($normalized)])) {
                $selected[] = (int) $index;
            }
        }

        return $selected !== [] ? $selected : range(0, max(0, count($headers) - 1));
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
            $this->normalizeImportedString($periode),
            $this->normalizeImportedString($row[$baseIndex] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 1] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 2] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 3] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 4] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 5] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 6] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 7] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 8] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 9] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 10] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 11] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 12] ?? null),
            $this->normalizeImportedString($row[$baseIndex + 13] ?? null),
        ];
    }

    private function normalizeDecimalValue($value, int $scale = 2): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, $scale, '.', '');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // OPTIMIZATION: Cache normalization results for repeated values (common in imports)
        $cacheKey = 'decimal:' . $scale . ':' . $value;
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
            $result = $value . '.' . str_repeat('0', $scale);
            $this->decimalNormalizationCache[$cacheKey] = $result;
            return $result;
        }

        if (preg_match('/^-?\d+\.\d+$/', $value) === 1) {
            if ($isNegative && str_starts_with($value, '-') === false) {
                $value = '-' . $value;
            }
            $result = number_format((float) $value, $scale, '.', '');
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

        $result = number_format((float) $value, $scale, '.', '');
        $this->decimalNormalizationCache[$cacheKey] = $result;
        return $result;
    }

    private function normalizeImportedString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('//u', $value)) {
            foreach (['Windows-1252', 'ISO-8859-1', 'ISO-8859-15'] as $encoding) {
                $converted = @mb_convert_encoding($value, 'UTF-8', $encoding);
                if (is_string($converted) && $converted !== '' && preg_match('//u', $converted)) {
                    $value = $converted;
                    break;
                }
            }

            if (!preg_match('//u', $value)) {
                $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
                if (is_string($converted) && $converted !== '') {
                    $value = $converted;
                } else {
                    $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $value);
                    if (is_string($converted) && $converted !== '') {
                        $value = $converted;
                    }
                }
            }
        }

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return $value === '' ? null : $value;
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
        $requestedReport = DB::table('nama_report')
            ->where('id_report', (int) $request->input('id_report'))
            ->first();
        $requestedTableName = strtolower(trim((string) ($requestedReport->table_name ?? '')));
        $manualImportPeriode = null;

        if ($this->requiresManualIbbizPeriode($requestedTableName)) {
            $rawPeriode = $request->input('periode');
            if (empty($rawPeriode)) {
                $request->validate(['periode' => 'required']);
            }
            try {
                $manualImportPeriode = $this->normalizeManualImportPeriode($requestedTableName, $rawPeriode);
            } catch (\Throwable $e) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ], 422);
                }
                return back()->with('error', $e->getMessage());
            }
        }

        if (in_array($requestedTableName, ['casa_brilink_web', 'casa_brilink_edc'], true)) {
            $message = 'Report CASA BRILINK wajib diproses lewat jalur upload CASA BRILINK. Silakan pilih ulang report lalu upload file CASA.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 422);
            }

            return back()->with('error', $message);
        }

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
            'generic_import_manual_periode' => $manualImportPeriode,
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
        $manualPeriode = null;
        $manualPeriodeLabel = null;
        $manualPeriodeInputType = 'date';

        if ($this->isIbbizImportTable($tableName)) {
            try {
                $manualPeriode = $this->resolveManualImportPeriodeFromRequest($request, $tableName);
            } catch (\InvalidArgumentException $e) {
                return redirect()->route('import.index')->with('error', $e->getMessage());
            }

            session(['generic_import_manual_periode' => $manualPeriode]);
            $manualPeriodeLabel = $manualPeriode !== null
                ? Carbon::parse($manualPeriode)->translatedFormat('d F Y')
                : null;
        }

        $isJumlahMerchantQrisDetail = $this->isJumlahMerchantQrisDetailReport($reportData) || $tableName === 'jumlah_merchant_qris_detail';
        $isJumlahMerchantDetail = $this->isJumlahMerchantDetailTable($tableName);
        $disableArea6AutoFilter = $isDailyLoan || in_array($tableName, [
            'sv_merchant',
        ], true);

        if ($reportData && (stripos($reportData->nama_report, 'BRILINK Web - Laporan Summary Transaksi') !== false || stripos($reportData->nama_report, 'brilink_web') !== false)) {
            $isBrilinkSummary = true;
        }

        $previewSampleLimit = $isDailyLoan ? self::DAILY_LOAN_PREVIEW_SAMPLE_LIMIT : self::PREVIEW_SAMPLE_LIMIT;
        $previewUniqueScanLimit = $isDailyLoan ? self::DAILY_LOAN_PREVIEW_UNIQUE_SCAN_LIMIT : self::PREVIEW_UNIQUE_SCAN_LIMIT;
        $previewUniqueLimitPerColumn = $isDailyLoan ? self::DAILY_LOAN_PREVIEW_UNIQUE_LIMIT_PER_COLUMN : self::PREVIEW_UNIQUE_LIMIT_PER_COLUMN;
        $fileSizeBytes = (int) @filesize($filePath);

        if ($isJumlahMerchantQrisDetail) {
            // QRIS detail files are much wider and bigger than the standard reports.
            // Keep preview snappy by sampling fewer rows while still retaining branch filters.
            $previewSampleLimit = min($previewSampleLimit, 100);
            $previewUniqueScanLimit = min($previewUniqueScanLimit, 250);
            $previewUniqueLimitPerColumn = min($previewUniqueLimitPerColumn, 80);
        }

        if ($isJumlahMerchantDetail) {
            // Merchant detail contains dozens of high-cardinality columns. Only organizational
            // filters are useful in preview; scan the file once so all Area 6 branches are found.
            $previewSampleLimit = min($previewSampleLimit, 100);
            $previewUniqueScanLimit = PHP_INT_MAX;
            $previewUniqueLimitPerColumn = 500;
        }

        if (!$isDailyLoan && !$isJumlahMerchantDetail && $fileSizeBytes > self::LARGE_FILE_THRESHOLD_BYTES) {
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
                $previewFilterableHeaders = $isJumlahMerchantQrisDetail
                    ? array_fill_keys(['MBDESC', 'BRDESC'], true)
                    : ($isJumlahMerchantDetail
                        ? array_fill_keys(['NAMA_KANCA', 'NAMA_UKER'], true)
                        : []);
                
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
                            [$posisiIndex, $tahunIndex] = $this->detectImportDateHeaderIndexes($headers);
                        }

                        foreach ($headers as $i => $h) {
                            if ($isJumlahMerchantQrisDetail || $isJumlahMerchantDetail) {
                                $normalizedHeader = strtoupper(trim((string) $h));
                                if (!isset($previewFilterableHeaders[$normalizedHeader])) {
                                    continue;
                                }
                            }

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
                                    $normalized[$i] = $this->normalizeDecimalValue(
                                        is_string($cellValue) ? trim($cellValue) : $cellValue,
                                        $normalizedColumn === 'rate' ? 6 : 2
                                    );
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
                            foreach ($validIndices as $i) {
                                if (!isset($data[$i])) {
                                    continue;
                                }

                                $val = $data[$i];
                                $cleanVal = is_string($val) ? trim($val) : (string) $val;

                                if ($cleanVal === '' && count($uniqueValues[$i]) > 0) {
                                    continue;
                                }

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
            $keys = array_keys($valuesMap);
            $this->sortFilterValues($keys);
            $formattedUniqueValues[$index] = $keys;
        }

        $filterableColumnIndices = [];
        foreach ($formattedUniqueValues as $index => $values) {
            if (!empty($values)) {
                $filterableColumnIndices[] = (int) $index;
            }
        }

        $shouldWarmPreviewIndexOnLoad = !$isJumlahMerchantQrisDetail;

        if ($shouldWarmPreviewIndexOnLoad && in_array($extension, ['csv', 'txt'], true) && !empty($filterableColumnIndices)) {
            $this->dispatchPreviewIndexWarmup($filePath, $currentDelimiter, $tableName, $isBrilinkSummary, $filterableColumnIndices);
        }

        $headers = $this->getPrettyIbbizHeaders($tableName, $headers);

        $area6ColumnHints = $isDailyLoan
            ? []
            : ['KANCA', 'KCI', 'BRANCH', 'BRDESC', 'MBDESC', 'CABANG'];
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
            'filterableColumnIndices',
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
            'warmIndexRoute' => $shouldWarmPreviewIndexOnLoad ? route('import.preview.warm-index') : '',
            'prefetchFilterOptionsOnLoad' => false,
            'warmPreviewIndexOnLoad' => $shouldWarmPreviewIndexOnLoad,
            'disableFilterOptionsLocalCache' => $isJumlahMerchantQrisDetail,
            'portalFilterDropdowns' => true,
            'manualPeriode' => $manualPeriode,
            'manualPeriodeInputType' => $manualPeriodeInputType,
            'manualPeriodeLabel' => $manualPeriodeLabel,
        ]);
    }

    public function previewWarmIndex(Request $request)
    {
        $this->applySafeRuntimeLimits();

        $request->validate([
            'file_path' => 'required|string',
            'delimiter' => 'nullable|string',
            'filterable_column_indices_json' => 'nullable|string',
            'preview_state_key' => 'nullable|string',
        ]);

        $filePath = (string) $request->input('file_path');
        $currentDelimiter = (string) $request->input('delimiter', 'auto');
        $previewStateKey = trim((string) $request->input('preview_state_key', ''));
        $filterableColumnIndices = json_decode((string) $request->input('filterable_column_indices_json', ''), true);
        if (!is_array($filterableColumnIndices)) {
            $filterableColumnIndices = [];
        }

        $previewState = $previewStateKey !== ''
            ? app(\App\Services\Import\ExcelImportJobService::class)->getPreviewState($previewStateKey)
            : [];
        $previewMeta = !empty($previewState['previewMeta']) && is_array($previewState['previewMeta'])
            ? (array) $previewState['previewMeta']
            : (array) session('excel_preview_meta', []);

        $resolvedFilePath = $this->resolveStagedCsvPath($filePath, $previewMeta, $previewStateKey);

        if (!file_exists($resolvedFilePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File tidak ditemukan di server.',
            ], 404);
        }

        $reportData = $this->getActiveReportData();
        $tableName = $this->resolveTableName($reportData);
        $isBrilinkSummary = $this->isBrilinkSummaryReport($reportData);
        $indexDbPath = $this->previewIndexDbPath($resolvedFilePath, $currentDelimiter, $tableName);

        if (file_exists($indexDbPath) && filesize($indexDbPath) > 0) {
            return response()->json([
                'status' => 'success',
                'message' => 'Index preview sudah siap.',
                'index_ready' => true,
            ]);
        }

        $lockKey = $this->previewIndexWarmLockKey($resolvedFilePath, $currentDelimiter, $tableName);
        if (!Cache::has($lockKey)) {
            $this->dispatchPreviewIndexWarmup($resolvedFilePath, $currentDelimiter, $tableName, $isBrilinkSummary, $filterableColumnIndices);
        }

        return response()->json([
            'status' => 'warming',
            'message' => 'Index preview sedang disiapkan.',
            'index_ready' => false,
        ], 202);
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
            'active_filters_json' => 'nullable|string',
        ]);

        $filePath = (string) $request->input('file_path');
        $currentDelimiter = (string) $request->input('delimiter', 'auto');
        $columnIndex = (int) $request->input('column_index');
        $previewStateKey = trim((string) $request->input('preview_state_key', ''));
        $displayFilterMap = json_decode((string) $request->input('display_filter_map_json', ''), true);
        if (!is_array($displayFilterMap)) {
            $displayFilterMap = [];
        }
        $activeFilters = json_decode((string) $request->input('active_filters_json', ''), true);
        if (!is_array($activeFilters)) {
            $activeFilters = [];
        }

        $displayFilterMap = $this->normalizeDisplayFilterMap($displayFilterMap);
        $sourceColumnIndex = (int) ($displayFilterMap[$columnIndex] ?? $columnIndex);

        $normalizedActiveFilters = [];
        $hasEmptyActiveFilter = false;
        foreach ($activeFilters as $displayIndex => $values) {
            $displayIndexInt = (int) $displayIndex;
            $sourceIndex = (int) ($displayFilterMap[$displayIndexInt] ?? $displayIndexInt);

            if ($sourceIndex === $sourceColumnIndex) {
                continue;
            }

            if (!is_array($values) || empty($values)) {
                $hasEmptyActiveFilter = true;
                continue;
            }

            $normalizedValues = array_values(array_unique(array_map(static function ($value): string {
                return trim((string) $value);
            }, $values)));

            if (count($normalizedValues) > 0) {
                $normalizedActiveFilters[$sourceIndex] = array_fill_keys($normalizedValues, true);
            } else {
                $hasEmptyActiveFilter = true;
            }
        }

        if ($hasEmptyActiveFilter) {
            return response()->json([
                'status' => 'success',
                'values' => [],
                'cached' => false,
            ]);
        }

        ksort($normalizedActiveFilters);
        $contextSignature = sha1(json_encode($normalizedActiveFilters));

        $previewState = $previewStateKey !== ''
            ? app(\App\Services\Import\ExcelImportJobService::class)->getPreviewState($previewStateKey)
            : [];
        $previewMeta = !empty($previewState['previewMeta']) && is_array($previewState['previewMeta'])
            ? (array) $previewState['previewMeta']
            : (array) session('excel_preview_meta', []);

        $metaPath = urldecode((string) ($previewMeta['path'] ?? ''));
        if ($metaPath === '' || !$this->isSameImportPath($filePath, $metaPath)) {
            $previewMeta = [];
        }

        $resolvedFilePath = $this->resolveStagedCsvPath($filePath, $previewMeta, $previewStateKey);
        if (!file_exists($resolvedFilePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File tidak ditemukan di server.',
            ], 404);
        }
        $extension = strtolower(pathinfo($resolvedFilePath, PATHINFO_EXTENSION));

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
        $cacheKey = $this->previewFilterCacheKey($resolvedFilePath, $currentDelimiter, $sourceColumnIndex, $tableName, $contextSignature);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json([
                'status' => 'success',
                'values' => $cached,
                'cached' => true,
            ]);
        }

        $indexDbPath = $this->previewIndexDbPath($resolvedFilePath, $currentDelimiter, $tableName);
        $indexReady = file_exists($indexDbPath) && filesize($indexDbPath) > 0;
        if (!$indexReady && $this->isJumlahMerchantDetailTable($tableName)) {
            $lockKey = $this->previewIndexWarmLockKey($resolvedFilePath, $currentDelimiter, $tableName);
            if (!Cache::has($lockKey)) {
                $this->dispatchPreviewIndexWarmup(
                    $resolvedFilePath,
                    $currentDelimiter,
                    $tableName,
                    $isBrilinkSummary,
                    array_merge([$sourceColumnIndex], array_keys($normalizedActiveFilters))
                );
            }

            $indexReady = $this->waitForPreviewIndex($indexDbPath, 2.0);
        }

        if ($indexReady) {
            try {
                $values = $this->queryPreviewFilterOptionsFromIndex(
                    $indexDbPath,
                    $sourceColumnIndex,
                    $normalizedActiveFilters,
                    $this->isJumlahMerchantDetailTable($tableName)
                );
                Cache::put($cacheKey, $values, now()->addHours(4));

                return response()->json([
                    'status' => 'success',
                    'values' => $values,
                    'cached' => false,
                    'source' => 'sqlite',
                ]);
            } catch (\Throwable $indexError) {
                Log::warning('Preview filter option index query failed: ' . $indexError->getMessage(), [
                    'file' => $resolvedFilePath,
                    'table' => $tableName,
                    'column' => $sourceColumnIndex,
                ]);
            }
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

            if (($tableName === 'jumlah_merchant_qris_detail' || $this->isJumlahMerchantDetailTable($tableName)) && !$isDailyLoan && !$isBrilinkSummary) {
                $values = $this->collectFilterOptionsFromCsvFast(
                    $handle,
                    $resolvedDelimiter,
                    $sourceColumnIndex,
                    $normalizedActiveFilters,
                    $isBrilinkSummary
                );

                Cache::put($cacheKey, $values, now()->addHours(4));

                return response()->json([
                    'status' => 'success',
                    'values' => $values,
                    'cached' => false,
                ]);
            }

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
                        [$posisiIndex, $tahunIndex] = $this->detectImportDateHeaderIndexes($headers);
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
                            $data[$i] = $this->normalizeDecimalValue(
                                $cellValue,
                                $normalizedColumn === 'rate' ? 6 : 2
                            );
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

                if (!isset($data[$sourceColumnIndex])) {
                    continue;
                }

                if (!$this->passesActiveFilters($data, $normalizedActiveFilters)) {
                    continue;
                }

                $value = trim((string) $data[$sourceColumnIndex]);
                if ($value !== '') {
                    $valuesMap[$value] = true;
                }
            }
        } finally {
            fclose($handle);
        }

        $values = array_keys($valuesMap);
        $this->sortFilterValues($values);
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

        $fileSignature = implode('|', [
            (string) realpath($resolvedFilePath),
            (string) @filesize($resolvedFilePath),
            (string) @filemtime($resolvedFilePath),
            (string) $columnIndex,
            (string) $currentDelimiter,
            'v3',
        ]);
        $cacheKey = "import_dynamic_filter:" . md5($fileSignature);
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

        $uniqueValues = [];
        $headers = [];
        $rowCounter = 0;
        
        // OPTIMIZATION 3: Max unique values limit untuk file besar
        $maxUniqueValues = 5000;  // Cap di 5000 unique values
        $uniqueCollected = 0;
        $stopCollecting = false;

        try {
            $delimiter = $this->resolveDelimiter($handle, $currentDelimiter);
            rewind($handle);

            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                // OPTIMIZATION 4: Skip empty rows early
                if (empty($row)) {
                    continue;
                }

                if ($rowCounter === 0) {
                    $headers = $this->formatCsvHeaders($row, false);
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
            $this->sortFilterValues($values);

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

    public function previewFilteredRows(Request $request)
    {
        $this->applySafeRuntimeLimits();

        $request->validate([
            'file_path' => 'required|string',
            'delimiter' => 'nullable|string',
            'display_filter_map_json' => 'nullable|string',
            'active_filters_json' => 'nullable|string',
            'preview_state_key' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $filePath = (string) $request->input('file_path');
        $currentDelimiter = (string) $request->input('delimiter', 'auto');
        $limit = (int) $request->input('limit', 100);
        $previewStateKey = trim((string) $request->input('preview_state_key', ''));

        $displayFilterMap = json_decode((string) $request->input('display_filter_map_json', ''), true);
        if (!is_array($displayFilterMap)) {
            $displayFilterMap = [];
        }

        $activeFilters = json_decode((string) $request->input('active_filters_json', ''), true);
        if (!is_array($activeFilters)) {
            $activeFilters = [];
        }

        $displayFilterMap = $this->normalizeDisplayFilterMap($displayFilterMap);

        $previewState = $previewStateKey !== ''
            ? app(\App\Services\Import\ExcelImportJobService::class)->getPreviewState($previewStateKey)
            : [];
        $previewMeta = !empty($previewState['previewMeta']) && is_array($previewState['previewMeta'])
            ? (array) $previewState['previewMeta']
            : (array) session('excel_preview_meta', []);

        $metaPath = urldecode((string) ($previewMeta['path'] ?? ''));
        if ($metaPath === '' || !$this->isSameImportPath($filePath, $metaPath)) {
            $previewMeta = [];
        }

        $resolvedFilePath = $this->resolveStagedCsvPath($filePath, $previewMeta, $previewStateKey);
        if (!file_exists($resolvedFilePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File tidak ditemukan di server.',
            ], 404);
        }
        $extension = strtolower(pathinfo($resolvedFilePath, PATHINFO_EXTENSION));

        if (!in_array($extension, ['csv', 'txt'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Format file tidak didukung untuk preview hasil filter.',
            ], 422);
        }

        $reportData = $this->getActiveReportData();
        $tableName = $this->resolveTableName($reportData);
        $isBrilinkSummary = $this->isBrilinkSummaryReport($reportData);

        $normalizedFilters = [];
        $hasEmptyActiveFilter = false;
        foreach ($activeFilters as $displayIndex => $values) {
            $displayIndexInt = (int) $displayIndex;
            $sourceIndex = (int) ($displayFilterMap[$displayIndexInt] ?? $displayIndexInt);

            if (!is_array($values) || empty($values)) {
                $hasEmptyActiveFilter = true;
                continue;
            }

            $normalizedValues = array_values(array_unique(array_map(static function ($value): string {
                return trim((string) $value);
            }, $values)));

            if (count($normalizedValues) > 0) {
                $normalizedFilters[$sourceIndex] = array_fill_keys($normalizedValues, true);
            } else {
                $hasEmptyActiveFilter = true;
            }
        }

        if ($hasEmptyActiveFilter) {
            return response()->json([
                'status' => 'success',
                'rows' => [],
                'total_matched' => 0,
                'returned_rows' => 0,
                'truncated' => false,
                'partial' => false,
                'retry_after_ms' => null,
                'source' => 'empty-filter',
            ]);
        }

        $indexDbPath = $this->previewIndexDbPath($resolvedFilePath, $currentDelimiter, $tableName);
        $indexReady = file_exists($indexDbPath) && filesize($indexDbPath) > 0;
        if (!$indexReady) {
            $lockKey = $this->previewIndexWarmLockKey($resolvedFilePath, $currentDelimiter, $tableName);
            if (!Cache::has($lockKey)) {
                $this->dispatchPreviewIndexWarmup($resolvedFilePath, $currentDelimiter, $tableName, $isBrilinkSummary, array_keys($normalizedFilters));
            }

            if ($this->isJumlahMerchantDetailTable($tableName)) {
                $indexReady = $this->waitForPreviewIndex($indexDbPath, 2.0);
            }
        }

        if ($indexReady) {
            try {
                $indexed = $this->queryPreviewRowsFromIndex(
                    $indexDbPath,
                    $normalizedFilters,
                    $limit,
                    $this->isJumlahMerchantDetailTable($tableName)
                );
                return response()->json([
                    'status' => 'success',
                    'rows' => $indexed['rows'],
                    'total_matched' => null,
                    'returned_rows' => count($indexed['rows']),
                    'truncated' => (bool) $indexed['truncated'],
                    'source' => 'sqlite',
                ]);
            } catch (\Throwable $indexError) {
                Log::warning('Preview index query failed: ' . $indexError->getMessage(), [
                    'file' => $resolvedFilePath,
                    'table' => $tableName,
                ]);
            }
        }

        if (!$indexReady) {
            Log::debug('Preview index unavailable, using bounded scan', [
                'file' => $resolvedFilePath,
                'table' => $tableName,
            ]);
        }

        $fileSignature = implode('|', [
            (string) realpath($resolvedFilePath),
            (string) @filesize($resolvedFilePath),
            (string) @filemtime($resolvedFilePath),
            (string) $limit,
            (string) $currentDelimiter,
            (string) $tableName,
            md5((string) json_encode($displayFilterMap)),
            md5((string) json_encode($activeFilters)),
            'v3',
        ]);

        $cacheKey = 'import_preview_filtered_rows:' . md5($fileSignature);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json($cached);
        }

        $handle = fopen($resolvedFilePath, 'r');
        if ($handle === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'File tidak dapat dibaca oleh server.',
            ], 422);
        }

        try {
            $delimiter = $this->resolveDelimiter($handle, $currentDelimiter);
            rewind($handle);

            $headers = [];
            $posisiIndex = -1;
            $tahunIndex = -1;
            $rowCounter = 0;
            $matchedRows = [];
            $matchedCount = 0;
            $truncated = false;

            while (($data = $this->readCsvRecord($handle, $delimiter)) !== false) {
                if ($rowCounter === 0) {
                    $headers = $this->formatCsvHeaders($data, $isBrilinkSummary);
                    if (!$isBrilinkSummary) {
                        [$posisiIndex, $tahunIndex] = $this->detectImportDateHeaderIndexes($headers);
                    }

                    $rowCounter++;
                    continue;
                }

                if ($isBrilinkSummary) {
                    $parsedRow = $this->transformBrilinkSummaryRow($data);
                    if (!$this->passesActiveFilters($parsedRow, $normalizedFilters)) {
                        continue;
                    }

                    $matchedCount++;
                    if (count($matchedRows) < $limit) {
                        $matchedRows[] = array_values($parsedRow);
                        if (count($matchedRows) >= $limit) {
                            $truncated = true;
                            break;
                        }
                    }
                    continue;
                }

                if (!$this->passesActiveFiltersFast($data, $normalizedFilters, false, $posisiIndex, $tahunIndex)) {
                    continue;
                }

                $parsedRow = $this->parseCsvRow($data, $isBrilinkSummary, $headers, $posisiIndex, $tahunIndex);
                if ($parsedRow === null) {
                    continue;
                }

                $matchedCount++;
                if (count($matchedRows) < $limit) {
                    $matchedRows[] = array_values($parsedRow);
                    if (count($matchedRows) >= $limit) {
                        $truncated = true;
                        break;
                    }
                }
            }

            $payload = [
                'status' => 'success',
                'rows' => $matchedRows,
                'total_matched' => $truncated ? null : $matchedCount,
                'returned_rows' => count($matchedRows),
                'truncated' => $truncated,
                'partial' => false,
                'retry_after_ms' => null,
                'source' => 'scan',
            ];

            Cache::put($cacheKey, $payload, now()->addMinutes(30));

            return response()->json($payload);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat preview hasil filter: ' . $e->getMessage(),
            ], 422);
        } finally {
            fclose($handle);
        }
    }

    private function previewIndexDbPath(string $resolvedFilePath, string $currentDelimiter, string $tableName): string
    {
        $baseDir = storage_path('app/import_preview_indexes');
        if (!File::exists($baseDir)) {
            File::makeDirectory($baseDir, 0755, true);
        }

        $signature = implode('|', [
            (string) realpath($resolvedFilePath),
            (string) @filesize($resolvedFilePath),
            (string) @filemtime($resolvedFilePath),
            (string) $currentDelimiter,
            (string) $tableName,
            'v3',
        ]);

        return $baseDir . DIRECTORY_SEPARATOR . md5($signature) . '.sqlite';
    }

    public function warmPreviewIndexDatabase(
        string $resolvedFilePath,
        string $currentDelimiter,
        bool $isBrilinkSummary,
        string $tableName,
        array $warmIndexColumns = []
    ): string
    {
        $dbPath = $this->previewIndexDbPath($resolvedFilePath, $currentDelimiter, $tableName);
        if (file_exists($dbPath) && filesize($dbPath) > 0) {
            return $dbPath;
        }

        $tmpDbPath = $dbPath . '.tmp';
        if (file_exists($tmpDbPath)) {
            @unlink($tmpDbPath);
        }

        $handle = fopen($resolvedFilePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('File preview tidak dapat dibaca untuk membangun index.');
        }

        try {
            $delimiter = $this->resolveDelimiter($handle, $currentDelimiter);
            rewind($handle);

            $headers = [];
            $posisiIndex = -1;
            $tahunIndex = -1;
            $rowCounter = 0;

            $pdo = new \PDO('sqlite:' . $tmpDbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode = OFF');
            $pdo->exec('PRAGMA synchronous = OFF');
            $pdo->exec('PRAGMA temp_store = MEMORY');
            $pdo->exec('DROP TABLE IF EXISTS preview_rows');

            $insert = null;
            $batchSize = 1000;
            $insertCount = 0;

            while (($data = $this->readCsvRecord($handle, $delimiter)) !== false) {
                if ($rowCounter === 0) {
                    $headers = $this->formatCsvHeaders($data, $isBrilinkSummary);
                    if (!$isBrilinkSummary) {
                        [$posisiIndex, $tahunIndex] = $this->detectImportDateHeaderIndexes($headers);
                    }

                    $columnDefs = [];
                    foreach (array_keys($headers) as $index) {
                        $columnDefs[] = 'c' . $index . ' TEXT';
                    }
                    $pdo->exec('CREATE TABLE preview_rows (row_num INTEGER PRIMARY KEY, ' . implode(', ', $columnDefs) . ')');

                    $columnNames = [];
                    $placeholders = [];
                    foreach (array_keys($headers) as $index) {
                        $columnNames[] = 'c' . $index;
                        $placeholders[] = '?';
                    }

                    $insert = $pdo->prepare('INSERT INTO preview_rows (' . implode(', ', $columnNames) . ') VALUES (' . implode(', ', $placeholders) . ')');
                    $pdo->beginTransaction();
                    $rowCounter++;
                    continue;
                }

                $parsedRow = $isBrilinkSummary
                    ? $this->transformBrilinkSummaryRow($data)
                    : $this->parseCsvRow($data, false, $headers, $posisiIndex, $tahunIndex);

                if ($parsedRow === null) {
                    continue;
                }

                $values = array_values($parsedRow);
                $values = array_slice($values, 0, count($headers));
                if (count($values) < count($headers)) {
                    $values = array_pad($values, count($headers), null);
                }

                $insert->execute($values);
                $insertCount++;

                if ($insertCount % $batchSize === 0) {
                    $pdo->commit();
                    $pdo->beginTransaction();
                }
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            $indexColumns = array_values(array_unique(array_filter(array_map('intval', $warmIndexColumns), static fn (int $index): bool => $index >= 0)));
            if (empty($indexColumns)) {
                $indexColumns = array_keys($headers);
            }

            foreach ($indexColumns as $index) {
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_preview_rows_c' . (int) $index . ' ON preview_rows(c' . (int) $index . ')');
            }

            @rename($tmpDbPath, $dbPath);

            if (!file_exists($dbPath) && file_exists($tmpDbPath)) {
                @copy($tmpDbPath, $dbPath);
                @unlink($tmpDbPath);
            }

            return $dbPath;
        } catch (\Throwable $e) {
            if (file_exists($tmpDbPath)) {
                @unlink($tmpDbPath);
            }
            throw $e;
        } finally {
            fclose($handle);
        }
    }

    private function buildPreviewIndexDatabase(
        string $resolvedFilePath,
        string $currentDelimiter,
        bool $isBrilinkSummary,
        string $tableName,
        array $warmIndexColumns = []
    ): string
    {
        return $this->warmPreviewIndexDatabase($resolvedFilePath, $currentDelimiter, $isBrilinkSummary, $tableName, $warmIndexColumns);
    }

    private function previewIndexWarmLockKey(string $resolvedFilePath, string $currentDelimiter, string $tableName): string
    {
        $signature = implode('|', [
            (string) realpath($resolvedFilePath),
            (string) @filesize($resolvedFilePath),
            (string) @filemtime($resolvedFilePath),
            (string) $currentDelimiter,
            (string) $tableName,
            'v3',
        ]);

        return 'import_preview_index_warm:' . md5($signature);
    }

    private function isSameImportPath(string $requestPath, string $metaPath): bool
    {
        $normalize = static fn(string $p): string => str_replace('\\', '/', urldecode(trim($p)));
        return $normalize($requestPath) === $normalize($metaPath);
    }

    private function resolveStagedCsvPath(
        string $filePath,
        array &$previewMeta,
        string $previewStateKey
    ): string {
        $stagedCsvPath = (string) ($previewMeta['staged_csv_path'] ?? '');
        if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
            return $stagedCsvPath;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($extension, ['csv', 'txt'], true)) {
            return $filePath;
        }

        $headerIndex = isset($previewMeta['header_index']) ? (int) $previewMeta['header_index'] : null;
        $sourceHeaders = array_values((array) ($previewMeta['source_headers'] ?? []));
        $previewPath = urldecode((string) ($previewMeta['path'] ?? ''));
        $sourceExcelPath = $previewPath !== '' ? Storage::path($previewPath) : $filePath;

        if (
            $previewStateKey === ''
            || $headerIndex === null
            || empty($sourceHeaders)
            || !is_string($sourceExcelPath)
            || $sourceExcelPath === ''
            || !file_exists($sourceExcelPath)
        ) {
            return $filePath;
        }

        $lockKey = 'excel_staging_lock_' . md5($sourceExcelPath);
        $maxWaitSeconds = 30;
        $sleepMicroseconds = 200000; // 200ms
        $elapsedSeconds = 0;

        // Loop wait if another process is staging
        while (Cache::has($lockKey)) {
            usleep($sleepMicroseconds);
            $elapsedSeconds += 0.2;
            if ($elapsedSeconds >= $maxWaitSeconds) {
                break;
            }

            // Re-read preview state to see if another process finished staging
            $previewState = app(\App\Services\Import\ExcelImportJobService::class)->getPreviewState($previewStateKey);
            $previewMeta = !empty($previewState['previewMeta']) && is_array($previewState['previewMeta'])
                ? (array) $previewState['previewMeta']
                : (array) session('excel_preview_meta', []);

            $stagedCsvPath = (string) ($previewMeta['staged_csv_path'] ?? '');
            if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
                return $stagedCsvPath;
            }
        }

        // Re-check after waiting
        if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
            return $stagedCsvPath;
        }

        // Acquire lock
        if (Cache::add($lockKey, true, now()->addMinutes(5))) {
            try {
                $stagingService = app(\App\Services\Import\ExcelStagingService::class);
                $generatedStagedCsvPath = $stagingService->createStagedCsvPath(storage_path('app/import_preview_filters'), 'filter_preview');
                $stageResult = $stagingService->stageExcelToCsv(
                    static function (string $event, array $payload): void {},
                    $sourceExcelPath,
                    $headerIndex,
                    $sourceHeaders,
                    $generatedStagedCsvPath,
                    null,
                    'excel_filter_preview_'
                );

                $candidateCsvPath = (string) ($stageResult['staged_csv_path'] ?? '');
                if ($candidateCsvPath !== '' && file_exists($candidateCsvPath)) {
                    $stagedCsvPath = $candidateCsvPath;
                    $previewMeta['staged_csv_path'] = $candidateCsvPath;
                    session(['excel_preview_meta' => array_merge((array) session('excel_preview_meta', []), [
                        'staged_csv_path' => $candidateCsvPath,
                    ])]);

                    $previewState = app(\App\Services\Import\ExcelImportJobService::class)->getPreviewState($previewStateKey);
                    app(\App\Services\Import\ExcelImportJobService::class)->putPreviewState(
                        $previewStateKey,
                        array_merge($previewState, ['previewMeta' => $previewMeta])
                    );
                }
            } catch (\Throwable $e) {
                Log::error('Error during excel preview staging: ' . $e->getMessage());
            } finally {
                Cache::forget($lockKey);
            }
        } else {
            // If lock failed, do a final fallback check
            $previewState = app(\App\Services\Import\ExcelImportJobService::class)->getPreviewState($previewStateKey);
            $previewMeta = !empty($previewState['previewMeta']) && is_array($previewState['previewMeta'])
                ? (array) $previewState['previewMeta']
                : (array) session('excel_preview_meta', []);
            $stagedCsvPath = (string) ($previewMeta['staged_csv_path'] ?? '');
        }

        return $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : $filePath;
    }

    private function dispatchPreviewIndexWarmup(
        string $resolvedFilePath,
        string $currentDelimiter,
        string $tableName,
        bool $isBrilinkSummary,
        array $warmIndexColumns = []
    ): bool
    {
        $indexDbPath = $this->previewIndexDbPath($resolvedFilePath, $currentDelimiter, $tableName);
        if (file_exists($indexDbPath) && filesize($indexDbPath) > 0) {
            return true;
        }

        $lockKey = $this->previewIndexWarmLockKey($resolvedFilePath, $currentDelimiter, $tableName);
        if (!Cache::add($lockKey, true, now()->addSeconds(self::PREVIEW_INDEX_WARM_LOCK_SECONDS))) {
            return true;
        }

        try {
            $phpBinary = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY;
            $process = new Process([
                $phpBinary,
                base_path('artisan'),
                'import:warm-preview-index',
                '--file_path=' . $resolvedFilePath,
                '--delimiter=' . $currentDelimiter,
                '--table_name=' . $tableName,
                '--is_brilink_summary=' . ($isBrilinkSummary ? '1' : '0'),
                '--lock_key=' . $lockKey,
                '--warm_index_columns_json=' . rawurlencode(json_encode(array_values(array_map('intval', $warmIndexColumns)))),
            ], base_path());
            $process->disableOutput();
            $process->setTimeout(null);
            $process->start();

            return true;
        } catch (\Throwable $e) {
            Cache::forget($lockKey);
            Log::warning('Failed to dispatch preview index warmup: ' . $e->getMessage(), [
                'file' => $resolvedFilePath,
                'table' => $tableName,
            ]);

            return false;
        }
    }

    private function queryPreviewRowsFromIndex(string $dbPath, array $normalizedFilters, int $limit, bool $trimIndexedValues = false): array
    {
        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout = 3000');

        $this->ensurePreviewQueryIndexes($pdo, array_keys($normalizedFilters));

        $columnsInfo = $pdo->query('PRAGMA table_info(preview_rows)')->fetchAll(\PDO::FETCH_ASSOC);
        $dataColumns = [];
        foreach ($columnsInfo as $info) {
            $name = (string) ($info['name'] ?? '');
            if ($name !== '' && $name !== 'row_num') {
                $dataColumns[] = $name;
            }
        }

        $whereParts = [];
        $params = [];
        foreach ($normalizedFilters as $sourceIndex => $allowedValues) {
            $values = array_keys($allowedValues);
            if (empty($values)) {
                continue;
            }

            $columnExpression = $trimIndexedValues
                ? 'TRIM(c' . (int) $sourceIndex . ')'
                : 'c' . (int) $sourceIndex;
            [$condition, $conditionParams] = $this->buildSqliteInCondition($columnExpression, $values);
            if ($condition !== '') {
                $whereParts[] = $condition;
                array_push($params, ...$conditionParams);
            }
        }

        $sql = 'SELECT ' . implode(', ', $dataColumns) . ' FROM preview_rows';
        if (!empty($whereParts)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }
        $sql .= ' ORDER BY row_num ASC LIMIT ' . ((int) $limit + 1);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
        $truncated = count($rows) > $limit;
        if ($truncated) {
            array_pop($rows);
        }

        return [
            'rows' => $rows,
            'truncated' => $truncated,
        ];
    }

    private function queryPreviewFilterOptionsFromIndex(string $dbPath, int $sourceColumnIndex, array $normalizedFilters, bool $trimIndexedValues = false): array
    {
        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout = 3000');

        $this->ensurePreviewQueryIndexes($pdo, array_merge([$sourceColumnIndex], array_keys($normalizedFilters)));

        $column = 'c' . (int) $sourceColumnIndex;
        $columnExpr = $trimIndexedValues ? 'TRIM(' . $column . ')' : $column;
        $whereParts = [$column . ' IS NOT NULL', $columnExpr . " <> ''"];
        $params = [];
        foreach ($normalizedFilters as $sourceIndex => $allowedValues) {
            $values = array_keys($allowedValues);
            if (empty($values)) {
                continue;
            }

            $filterColumnExpression = $trimIndexedValues
                ? 'TRIM(c' . (int) $sourceIndex . ')'
                : 'c' . (int) $sourceIndex;
            [$condition, $conditionParams] = $this->buildSqliteInCondition($filterColumnExpression, $values);
            if ($condition !== '') {
                $whereParts[] = $condition;
                array_push($params, ...$conditionParams);
            }
        }

        $sql = 'SELECT DISTINCT ' . $columnExpr . ' as value FROM preview_rows';
        if (!empty($whereParts)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $values = [];
        while (($value = $stmt->fetchColumn()) !== false) {
            $cleanValue = trim((string) $value);
            if ($cleanValue !== '') {
                $values[] = $cleanValue;
            }
        }

        $this->sortFilterValues($values);

        return $values;
    }

    private function buildSqliteInCondition(string $column, array $values): array
    {
        $values = array_values(array_unique(array_map(static fn ($value): string => trim((string) $value), $values)));
        if (empty($values)) {
            return ['', []];
        }

        $parts = [];
        $params = [];
        foreach (array_chunk($values, 800) as $chunk) {
            $parts[] = $column . ' IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')';
            array_push($params, ...$chunk);
        }

        return ['(' . implode(' OR ', $parts) . ')', $params];
    }

    private function waitForPreviewIndex(string $indexDbPath, float $maxSeconds): bool
    {
        $deadline = microtime(true) + max(0.0, $maxSeconds);
        do {
            if (file_exists($indexDbPath) && filesize($indexDbPath) > 0) {
                return true;
            }

            usleep(200000);
        } while (microtime(true) < $deadline);

        return file_exists($indexDbPath) && filesize($indexDbPath) > 0;
    }

    private function ensurePreviewQueryIndexes(\PDO $pdo, array $sourceColumns): void
    {
        $sourceColumns = array_values(array_unique(array_filter(array_map('intval', $sourceColumns), static fn (int $index): bool => $index >= 0)));
        if (empty($sourceColumns)) {
            return;
        }

        foreach ($sourceColumns as $index) {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_preview_rows_c' . (int) $index . ' ON preview_rows(c' . (int) $index . ')');
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
        $manualImportPeriode = null;

        try {
            $manualImportPeriode = $this->resolveManualImportPeriodeFromRequest($request, $tableName);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'text' => $e->getMessage(),
            ], 422);
        }

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

        // Layer A: cross-report duplicate file check via SHA256 content fingerprint
        $contentHash = '';
        try {
            $fileGuard = app(ImportDuplicateGuardService::class);
            $contentHash = $fileGuard->fingerprint($filePath);
            if (!$isBrilinkSummary && !$this->isIbbizImportTable($tableName)) {
                $fileGuard->assertFileNotImportedAnywhere($contentHash);
            }
        } catch (\RuntimeException $e) {
            $this->cleanupImportDirectory($filePath);
            return response()->json([
                'status' => 'warning',
                'title' => 'File Sudah Diimport!',
                'text' => $e->getMessage(),
                'duplicate_detected' => true,
                'redirect_url' => route('import.index'),
            ], 422);
        } catch (\Throwable $e) {
            Log::warning('ImportFileController: Fingerprint calculation failed (continuing): ' . $e->getMessage());
        }

        $isDuplicate = false;
        $duplicateText = '';
        $duplicateLookup = [];

        if ($this->isJumlahMerchantDetailTable($tableName)) {
            $duplicateLookup = $this->buildJumlahMerchantDuplicateLookup($meta['periode_tid_pairs'] ?? []);

            if (!empty($meta['periode_tid_pairs']) && count($duplicateLookup) === count($meta['periode_tid_pairs'])) {
                $isDuplicate = true;
                $duplicateText = 'Semua kombinasi <b>PERIODE + TID</b> pada file ini sudah ada di tabel <b class="text-uppercase">' . $tableName . '</b>.<br><br>Sistem membatalkan proses untuk mencegah data dobel.';
            }
        } elseif ($isBrilinkSummary) {
            $duplicateLookup = $this->buildBrilinkSummaryDuplicateLookup($meta['brilink_summary_keys'] ?? []);

            if (!empty($meta['brilink_summary_keys']) && count($duplicateLookup) === count($meta['brilink_summary_keys'])) {
                $isDuplicate = true;
                $duplicateText = 'Semua kombinasi <b>PERIODE + MERCHANT_CODE + OUTLET_CODE</b> pada file ini sudah ada di tabel <b class="text-uppercase">' . $tableName . '</b>.<br><br>Sistem membatalkan proses untuk mencegah data dobel.';
            }
        } elseif ($this->isIbbizImportTable($tableName) && $manualImportPeriode !== null) {
            $isDuplicate = DB::table($tableName)->whereDate('periode', $manualImportPeriode)->exists();
            if ($isDuplicate) {
                $duplicateText = 'Data IB Biz untuk periode <b>' . e($manualImportPeriode) . '</b> sudah ada di tabel <b class="text-uppercase">' . e($tableName) . '</b>.<br><br>Sistem membatalkan proses ini.';
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
                'content_hash' => $contentHash,
                'total_rows' => (int) $meta['total_rows'],
                'import_params' => [
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
                    'manual_periode' => $manualImportPeriode,
                    'sample_posisi' => $meta['sample_posisi'] ?? null,
                    'sample_periode' => $manualImportPeriode ?? ($meta['sample_periode'] ?? null),
                    'brilink_summary_keys' => $meta['brilink_summary_keys'] ?? [],
                    'duplicate_lookup' => $duplicateLookup,
                ],
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
            'manual_periode' => $manualImportPeriode,
            'sample_posisi' => $meta['sample_posisi'] ?? null,
            'sample_periode' => $manualImportPeriode ?? ($meta['sample_periode'] ?? null),
            'brilink_summary_keys' => $meta['brilink_summary_keys'] ?? [],
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
        $jobId = (int) ($request->query('job_id', 0) ?: ($sessionParams['job_id'] ?? 0));
        $params = Cache::get('csv_import_params_' . $jobId, $sessionParams);
        $params = $this->resolveImportStreamParams($jobId, is_array($params) ? $params : []);

        request()->session()->save();

        return response()->stream(function () use ($params, $jobId) {
            $streamLock = null;
            $progressService = app(\App\Services\Import\ImportProgressService::class);

            $send = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $sendWithCacheSync = function (string $event, array $data) use (&$send, $jobId, $progressService): void {
                $send($event, $data);

                if ($jobId > 0 && in_array($event, ['progress', 'complete'], true)) {
                    $cachePayload = [
                        'status' => $event === 'complete' ? 'completed' : 'processing',
                        'percent' => (int) ($data['percent'] ?? 0),
                        'message' => (string) ($data['message'] ?? ''),
                        'processed_rows' => (int) ($data['rows_done'] ?? $data['processed_rows'] ?? 0),
                        'total_rows' => (int) ($data['total'] ?? $data['total_rows'] ?? 0),
                        'total_success' => (int) ($data['total_success'] ?? 0),
                        'total_failed' => (int) ($data['total_failed'] ?? 0),
                    ];

                    if (!empty($data['speed'])) {
                        $cachePayload['speed'] = (int) $data['speed'];
                    }

                    if (!empty($data['speed_label'])) {
                        $cachePayload['speed_label'] = (string) $data['speed_label'];
                    }

                    try {
                        $progressService->cacheProgress($jobId, $cachePayload);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to cache import progress for job ' . $jobId . ': ' . $e->getMessage());
                    }
                }
            };
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
                try {
                    $manualImportPeriode = $this->normalizeManualImportPeriode($tableName, $params['manual_periode'] ?? null);
                } catch (\InvalidArgumentException $e) {
                    $markJobFailed($e->getMessage());
                    $send('error', ['message' => $e->getMessage()]);
                    return;
                }
                $syncPeriod = $manualImportPeriode ?? ($params['sample_posisi'] ?? $params['sample_periode'] ?? null);
                $uniqueSuffix = $params['unique_suffix'] ?? '_MDT';
                $isBrilinkSummary = (bool) ($params['is_brilink_summary'] ?? false);
                $csvHeaders = $params['headers'] ?? [];
                $posisiIndex = (int) ($params['posisi_index'] ?? -1);
                $tahunIndex = (int) ($params['tahun_index'] ?? -1);
                $totalRows = (int) ($params['total_rows'] ?? 0);
                $duplicateLookup = $params['duplicate_lookup'] ?? [];
                if (!is_array($duplicateLookup)) {
                    $duplicateLookup = [];
                }
                if (!$isBrilinkSummary) {
                    [$posisiIndex, $tahunIndex] = $this->normalizeImportDateHeaderIndexes(
                        $csvHeaders,
                        $posisiIndex,
                        $tahunIndex
                    );
                }
                $columnBlueprint = $isBrilinkSummary ? [] : $this->buildColumnImportBlueprint($selectedColumns, $csvHeaders, $tableName);
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

                $sendWithCacheSync('progress', [
                    'percent' => 5,
                    'message' => 'Menyiapkan stream import CSV...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $resolvedDelimiter = $this->resolveDelimiter($handle, $delimiter);
                rewind($handle);

                $sendWithCacheSync('progress', [
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
                    if ($this->shouldSkipDuplicateImportRow($tableName, $row, $duplicateLookup)) {
                        $duplicateSkipped++;
                        return false;
                    }

                    return true;
                };

                $flushBuffer = function () use (&$buffer, &$totalSuccess, &$totalFailed, &$lastErrorMsg, $tableName, $batchSize) {
                    $this->flushInsertBuffer($buffer, $tableName, $totalSuccess, $totalFailed, $lastErrorMsg, null, $batchSize);
                };

                $stagingHandled = false;
                $skipRawLoadDataFastPath = $this->shouldSkipRawLoadDataFastPath($tableName, $filePath, $resolvedDelimiter);
                if ($this->shouldUseDbStagingFastPath() && !$skipRawLoadDataFastPath) {
                    $stagingHandled = $this->processImportStreamViaStagingTable(
                        $sendWithCacheSync,
                        $filePath,
                        $resolvedDelimiter,
                        $selectedColumns,
                        $activeFilters,
                        $tableName,
                        $uniqueSuffix,
                        $isBrilinkSummary,
                        $manualImportPeriode,
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

                if ($skipRawLoadDataFastPath) {
                    Log::info('Skipping raw LOAD DATA staging fast path for safer CSV parsing.', [
                        'table' => $tableName,
                        'file' => basename($filePath),
                        'delimiter' => $resolvedDelimiter,
                    ]);
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
                    $sendWithCacheSync('progress', [
                        'percent' => 14,
                        'message' => 'Menambahkan tahap staging ke antrean prioritas...',
                        'rows_done' => 0,
                        'total' => $totalRows,
                        'speed' => 0,
                    ]);

                    $bulkColumns = $this->buildBulkLoadColumnsForMappedRows($tableName, $isBrilinkSummary, $columnBlueprint);

                    $stagingParams = [
                        'file_path' => $filePath,
                        'delimiter' => $resolvedDelimiter,
                        'selected_columns' => $selectedColumns,
                        'normalized_filters' => $activeFilters,
                        'table_name' => $tableName,
                        'unique_suffix' => $uniqueSuffix,
                        'is_brilink_summary' => $isBrilinkSummary,
                        'headers' => $csvHeaders,
                        'posisi_index' => $posisiIndex,
                        'tahun_index' => $tahunIndex,
                        'total_rows' => $totalRows,
                        'manual_periode' => $manualImportPeriode,
                        'duplicate_lookup' => $duplicateLookup,
                        'column_blueprint' => $columnBlueprint,
                        'bulkColumns' => $bulkColumns,
                        'bulk_columns' => $bulkColumns,
                        'batch_size' => $batchSize,
                        'sync_period' => $syncPeriod,
                    ];

                    $cacheStore = trim((string) config('import.cache_store', 'file'));
                    $cache = $cacheStore !== '' ? Cache::store($cacheStore) : Cache::store();
                    $cache->put("csv_import_params_{$jobId}", $stagingParams, now()->addHours(2));

                    \App\Jobs\PrepareCsvStagingJob::dispatch($jobId)->onQueue('imports-high');
                    $progressService->markQueued($jobId, [
                        'status' => 'queued',
                        'phase' => 'staging_queued',
                        'mode' => 'queue',
                        'percent' => 14,
                        'message' => 'Menunggu worker staging prioritas...',
                        'processed_rows' => 0,
                        'total_rows' => $totalRows,
                    ]);

                    $pollingStart = microtime(true);
                    while (true) {
                        usleep(400_000); // 400ms

                        $cached = $progressService->getCachedProgress($jobId);
                        $status = strtolower((string) ($cached['status'] ?? 'staging'));

                        // A terminal result is authoritative. Sending another progress event
                        // would overwrite the completed/failed database status as processing.
                        if (in_array($status, ['completed', 'failed', 'failed_partial', 'terminated'], true)) {
                            break;
                        }

                        try {
                            $sendWithCacheSync('progress', $cached);
                        } catch (\Throwable $e) {
                            Log::warning('Failed to send progress during staging job polling: ' . $e->getMessage());
                        }

                        if (microtime(true) - $pollingStart > 7200) {
                            $send('error', ['message' => 'Import timeout (2 jam).']);
                            $progressService->markFailed($jobId, 'Import timeout');
                            return;
                        }
                    }

                    $job = DB::table('import_jobs')->where('id', $jobId)->first();
                    if ($job) {
                        $totalSuccess = (int) ($job->total_success ?? 0);
                        $totalFailed = (int) ($job->total_failed ?? 0);
                        $lastErrorMsg = $job->error_message ?? '';
                    }

                    $finalStatus = $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed';

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

                $sendWithCacheSync('progress', [
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

                    if (
                        !$isBrilinkSummary
                        && !empty($activeFilters)
                        && !$this->passesActiveFiltersFast($data, $activeFilters, false, $posisiIndex, $tahunIndex)
                    ) {
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

                    $mappedRow = $this->applyManualImportPeriode($mappedRow, $tableName, $manualImportPeriode);
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

                        $sendWithCacheSync('progress', [
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

                $sendWithCacheSync('progress', [
                    'percent' => 98,
                    'message' => 'Finalisasi status import...',
                    'rows_done' => $rowsDone,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $sendWithCacheSync('complete', [
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
            $manualImportPeriode = $this->resolveManualImportPeriodeFromRequest($request, $tableName);
        } catch (\InvalidArgumentException $e) {
            $response = [
                'status' => 'error',
                'title' => 'Periode Wajib Diisi',
                'text' => $e->getMessage(),
            ];

            return $request->expectsJson()
                ? response()->json($response, 422)
                : redirect()->route('import.index')->with('sweet_warning', $response);
        }

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
                        [$posisiIndex, $tahunIndex] = $this->detectImportDateHeaderIndexes($csvHeaders);

                        $columnBlueprint = $this->buildColumnImportBlueprint($selectedColumns, $csvHeaders, $tableName);
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

                        'kanwil' => $this->normalizeImportedString($data[2 + $brilinkOffset] ?? null),
                        'cabang' => $this->normalizeImportedString($data[4 + $brilinkOffset] ?? null),
                        'uker' => $this->normalizeImportedString($data[6 + $brilinkOffset] ?? null),

                        'merchant_name' => $this->normalizeImportedString($data[7 + $brilinkOffset] ?? null),
                        'merchant_code' => $this->normalizeImportedString($data[8 + $brilinkOffset] ?? null),
                        'outlet_name' => $this->normalizeImportedString($data[9 + $brilinkOffset] ?? null),
                        'outlet_code' => $this->normalizeImportedString($data[10 + $brilinkOffset] ?? null),

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
                        } else {
                            $cellValue = $this->normalizeImportedString($cellValue);
                        }

                        $rowData[$columnMeta['column']] = ($cellValue === '') ? null : $cellValue;
                    }

                    $rowData = $this->applyDailyLoanCompatibilityColumns($rowData);
                }
                
                $rowData = $this->applyManualImportPeriode($rowData, $tableName, $manualImportPeriode);
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
            $rawSamplePosisi = $dataToInsert[0]['POSISI'] ?? ($dataToInsert[0]['posisi'] ?? null);
            $samplePosisi = $rawSamplePosisi !== null
                ? $this->normalizeJumlahMerchantDuplicatePeriod((string) $rawSamplePosisi)
                : null;
            $samplePeriode = $dataToInsert[0]['periode']
                ?? $dataToInsert[0]['PERIODE']
                ?? $samplePosisi;
        }

        // Layer A: cross-report duplicate file check via SHA256 content fingerprint
        $contentHash = '';
        try {
            $fileGuard = app(ImportDuplicateGuardService::class);
            $contentHash = $fileGuard->fingerprint($filePath);
            if (!$isBrilinkSummary && !$this->isIbbizImportTable($tableName)) {
                $fileGuard->assertFileNotImportedAnywhere($contentHash);
            }
        } catch (\RuntimeException $e) {
            $this->cleanupImportDirectory($filePath);
            $response = [
                'status' => 'warning',
                'title' => 'File Sudah Diimport!',
                'text' => $e->getMessage(),
                'duplicate_detected' => true,
                'redirect_url' => route('import.index'),
            ];
            return $request->expectsJson()
                ? response()->json($response, 422)
                : redirect()->route('import.index')->with('sweet_warning', $response);
        } catch (\Throwable $e) {
            Log::warning('ImportFileController: Fingerprint calculation failed (continuing): ' . $e->getMessage());
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
        } elseif ($isBrilinkSummary) {
            $brilinkSummaryKeys = [];
            foreach ($dataToInsert as $rowData) {
                $duplicateKey = $this->extractBrilinkSummaryDuplicateKey($rowData);
                if ($duplicateKey === null) {
                    continue;
                }

                [$periode, $merchantCode, $outletCode] = $duplicateKey;
                $brilinkSummaryKeys[$periode . '|' . $merchantCode . '|' . $outletCode] = [
                    'periode' => $periode,
                    'merchant_code' => $merchantCode,
                    'outlet_code' => $outletCode,
                ];
            }

            $duplicateLookup = $this->buildBrilinkSummaryDuplicateLookup(array_values($brilinkSummaryKeys));
            if (!empty($brilinkSummaryKeys) && count($duplicateLookup) === count($brilinkSummaryKeys)) {
                $isDuplicate = true;
                $duplicateText = "Semua kombinasi <b>PERIODE + MERCHANT_CODE + OUTLET_CODE</b> pada file ini sudah ada di tabel <b class='text-uppercase'>$tableName</b>.<br><br>Sistem membatalkan proses ini.";
            }
        } elseif ($this->isIbbizImportTable($tableName) && $manualImportPeriode !== null) {
            $isDuplicate = DB::table($tableName)->whereDate('periode', $manualImportPeriode)->exists();
            if ($isDuplicate) {
                $duplicateText = "Data IB Biz untuk periode <b>{$manualImportPeriode}</b> sudah pernah diunggah ke tabel <b class='text-uppercase'>{$tableName}</b>.<br><br>Sistem membatalkan proses ini.";
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
                'content_hash' => $contentHash,
                'data_rows_count' => count($dataToInsert),
                'manual_periode' => $manualImportPeriode,
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
            if ($this->shouldSkipDuplicateImportRow($tableName, $row, $duplicateLookup)) {
                $duplicateSkipped++;
                return false;
            }

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

        // Reactively resume paused snapshot queues — don't wait for the 1-minute scheduler
        try {
            app(\App\Services\Import\SnapshotQueuePauseService::class)->resumeWhenNoActiveImports();
        } catch (\Throwable $e) {
            Log::debug('Reactive queue resume skipped: ' . $e->getMessage());
        }
    }
}
