<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Import\Concerns\SmartCsvImportSupport;
use App\Http\Controllers\Import\Concerns\AllocatesGapIds;
use App\Services\Import\CsvAutoRepairService;
use App\Services\Import\ExcelImportJobService;
use App\Services\Import\ExcelQueuedImportService;
use App\Services\Import\ExcelStagingService;
use App\Services\Import\ImportCleanupService;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportPipelineService;
use App\Services\Import\ImportProgressService;
use App\Services\Import\ImportStrategyFactory;
use App\Services\Import\MySqlBulkLoadService;
use App\Services\Import\SchemaIntrospectionService;
use App\Support\StrictDateParser;
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
    use SmartCsvImportSupport;

    use AllocatesGapIds;

    private array $importContextCache = [];
    private array $dailyLoanCsvAnalysisCache = [];
    private ?array $excelDateColumnsLookupCache = null;
    private ?array $excelDecimalColumnsLookupCache = null;
    private ?array $excelIntegerColumnsLookupCache = null;

    private array $lastDailyLoanCsvParseMeta = [
        'status' => 'normal',
        'reason' => null,
        'expected_columns' => null,
        'actual_columns' => null,
        'repaired' => false,
    ];

    private const DAILY_LOAN_REPORT_ID = 8;
    private const SIMPANAN_MULTIPN_REPORT_ID = 9;
    private const CHUNK_UPLOAD_TEMP_DIR = 'app/chunk_uploads';
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

    protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
    {
        $reportData = $this->resolveActiveReport();

        return ($reportData && !empty($reportData->table_name))
            ? (string) $reportData->table_name
            : $default;
    }

    private function resolvePreviewReportLabel(): string
    {
        $tableName = strtolower(trim($this->resolveActiveTableName()));

        return match ($tableName) {
            'daily_loan_dinamis' => 'Daily Loan Dinamis',
            'simpanan_multipn' => 'Simpanan MultiPN',
            'gi405_rec_dh' => 'GI405 - Rec. DH',
            default => $this->resolveActiveReport()?->nama_report ?? 'Preview Data',
        };
    }

    private function resolvePreviewPageTitle(): string
    {
        return 'Preview & Filter Data - ' . $this->resolvePreviewReportLabel();
    }

    private function resolvePreviewBannerTitle(): string
    {
        return 'Preview Data Import (' . $this->resolvePreviewReportLabel() . ')';
    }

    protected function resolveExcelTableName(): string
    {
        return $this->resolveActiveTableName();
    }

    private function detectHeaderIndex(array $rows, ?string $tableName = null): ?int
    {
        $bestIndex = null;
        $bestScore = 0;
        $dbColumnsLookup = [];

        if ($tableName && $this->cachedSchemaHasTable($tableName)) {
            $dbColumnsLookup = array_fill_keys(
                array_map('strtolower', $this->cachedSchemaColumnListing($tableName)),
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

    private const DB_INSERT_BATCH_SIZE = 2000;
    private const STREAM_PROGRESS_EVERY = 1000;
    private const FALLBACK_SPLIT_THRESHOLD = 25;
    private const INSERT_BUFFER_FLUSH_SIZE = 2000;
    private const BULK_LOAD_TEMP_DIR = 'app/import_bulk';
    private const STAGED_CSV_TEMP_DIR = 'app/excel_stage';
    private const DAILY_LOAN_IMPORT_LOCK_NAME = 'project_abah:table_write:daily_loan_dinamis';

    private function schemaService(): SchemaIntrospectionService
    {
        return app(SchemaIntrospectionService::class);
    }

    private function bulkLoadService(): MySqlBulkLoadService
    {
        return app(MySqlBulkLoadService::class);
    }

    private function csvAutoRepairService(): CsvAutoRepairService
    {
        return app(CsvAutoRepairService::class);
    }

    private function excelStagingService(): ExcelStagingService
    {
        return app(ExcelStagingService::class);
    }

    private function progressService(): ImportProgressService
    {
        return app(ImportProgressService::class);
    }

    private function excelImportJobService(): ExcelImportJobService
    {
        return app(ExcelImportJobService::class);
    }

    private function excelQueuedImportService(): ExcelQueuedImportService
    {
        return app(ExcelQueuedImportService::class);
    }

    private function executionService(): ImportExecutionService
    {
        return app(ImportExecutionService::class);
    }

    private function cleanupService(): ImportCleanupService
    {
        return app(ImportCleanupService::class);
    }

    private function pipelineService(): ImportPipelineService
    {
        return app(ImportPipelineService::class);
    }

    private function strategyFactory(): ImportStrategyFactory
    {
        return app(ImportStrategyFactory::class);
    }

    private function resolveImportStrategy(?string $tableName = null): object
    {
        return $this->strategyFactory()->resolve($this->resolveActiveReport(), $tableName ?? $this->resolveActiveTableName());
    }

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

    private function cachedTableColumnMetadata(string $tableName): array
    {
        return $this->schemaService()->getColumnMetadata($tableName);
    }

    private function parseMysqlColumnTypeMetadata(string $type): array
    {
        return $this->schemaService()->parseMysqlColumnTypeMetadata($type);
    }

    private function applySqlColumnConstraints(string $expression, string $dbColumn, array $context): string
    {
        $columnMeta = $context['table_column_meta_by_lower'][strtolower($dbColumn)] ?? null;
        if (!$columnMeta) {
            return $expression;
        }

        $maxLength = (int) ($columnMeta['max_length'] ?? 0);
        if (!empty($columnMeta['is_textual']) && $maxLength > 0) {
            return "CASE WHEN ({$expression}) IS NULL THEN NULL ELSE LEFT(CAST(({$expression}) AS CHAR), {$maxLength}) END";
        }

        return $expression;
    }

    private function normalizeValueForDatabaseColumn(string $dbColumn, $value, array $context)
    {
        if ($value === null) {
            return null;
        }

        $columnMeta = $context['table_column_meta_by_lower'][strtolower($dbColumn)] ?? null;
        if (!$columnMeta || empty($columnMeta['is_textual'])) {
            return $value;
        }

        if (!is_scalar($value)) {
            return $value;
        }

        $normalizedValue = (string) $value;
        $maxLength = (int) ($columnMeta['max_length'] ?? 0);
        if ($maxLength <= 0) {
            return $normalizedValue;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($normalizedValue, 0, $maxLength);
        }

        return substr($normalizedValue, 0, $maxLength);
    }

    private function relaxMysqlSqlModeForImport(\PDO $pdo): ?string
    {
        return $this->bulkLoadService()->relaxMysqlSqlModeForImport($pdo);
    }

    private function isDailyLoanTable(?string $tableName = null): bool
    {
        return ($tableName ?? $this->resolveExcelTableName()) === 'daily_loan_dinamis';
    }

    private function isSimpananMultiPnTable(?string $tableName = null): bool
    {
        return ($tableName ?? $this->resolveExcelTableName()) === 'simpanan_multipn';
    }

    private function isSsaSimpananTable(?string $tableName = null): bool
    {
        return ($tableName ?? $this->resolveExcelTableName()) === 'ssa_simpanan';
    }

    private function isSsaPinjamanTable(?string $tableName = null): bool
    {
        return ($tableName ?? $this->resolveExcelTableName()) === 'ssa_pinjaman';
    }

    private function isGi405RecDhTable(?string $tableName = null): bool
    {
        return ($tableName ?? $this->resolveExcelTableName()) === 'gi405_rec_dh';
    }

    private function isLw325PhTable(?string $tableName = null): bool
    {
        return ($tableName ?? $this->resolveExcelTableName()) === 'lw325_ph';
    }

    protected function normalizeGi405RecDhKodeValue($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $value = (string) (int) round((float) $value);
        }

        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === null || $digits === '') {
            return $value;
        }

        if (strlen($digits) <= 5) {
            return str_pad($digits, 5, '0', STR_PAD_LEFT);
        }

        return $digits;
    }

    private function hasRequiredGi405RecDhImportData(array $row): bool
    {
        $kode = trim((string) ($row['kode'] ?? ''));
        $tanggal = trim((string) ($row['tanggal'] ?? ''));
        $uniqueId = trim((string) ($row['uniqueid_namareport'] ?? ''));

        return $kode !== '' && $tanggal !== '' && $uniqueId !== '';
    }

    private function assertGi405RecDhNumericMapping(array $row, array $normalizedHeaders, array $finalRow): void
    {
        $checks = [
            'Pendapatan Koreksi PPAP-dr Angsuran PH' => 'pendapatan_koreksi_ppap_dr_angsuran_ph',
            'Recovery Non Klaim' => 'recovery_non_klaim',
        ];

        foreach ($checks as $headerLabel => $dbColumn) {
            $headerIndex = $this->findNormalizedHeaderIndex($normalizedHeaders, $headerLabel);
            if ($headerIndex === null) {
                continue;
            }

            $rawValue = trim((string) ($row[$headerIndex] ?? ''));
            if ($rawValue === '') {
                continue;
            }

            if (($finalRow[$dbColumn] ?? null) !== null) {
                continue;
            }

            throw new \RuntimeException(
                "Nilai numerik GI405 tidak berhasil dipetakan untuk kolom `{$dbColumn}`. "
                . "Header sumber: `{$headerLabel}`, contoh nilai: `{$rawValue}`."
            );
        }
    }

    private function findNormalizedHeaderIndex(array $headers, string $targetHeader): ?int
    {
        $target = $this->normalizeImportColumnName($targetHeader);

        foreach ($headers as $index => $header) {
            if ($this->normalizeImportColumnName((string) $header) === $target) {
                return (int) $index;
            }
        }

        return null;
    }

    private function detectCsvDelimiter(string $path): string
    {
        return $this->smartDetectCsvDelimiter($path);
    }

    private function normalizeImportColumnName(string $headerName): string
    {
        $normalizedHeader = preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($headerName)));
        $normalizedHeader = trim((string) $normalizedHeader, '_');

        $aliases = [
            'TEXTBOX20' => 'total_kewajiban',
            'TOTAL_KEWAJIBAN' => 'total_kewajiban',
            'TEXTBOX21' => 'os_idr',
            'OS_IDR' => 'os_idr',
            'MONTH_DAY_YEAR_OF_POSISI' => 'month_day_year_of_posisi',
            'MONTH_DAY_YEAR_OF_PERIODE' => 'month_day_year_of_periode',
            'NOREKENING' => 'no_rekening',
            'NOMORREKENING' => 'no_rekening',
            'NOMOR_REKENING' => 'no_rekening',
            'NO_REKENING' => 'no_rekening',
            'CIF_NO' => 'cifno',
            'CIF_NUMBER' => 'cifno',
        ];

        if (isset($aliases[$normalizedHeader])) {
            return $aliases[$normalizedHeader];
        }

        return strtolower($normalizedHeader);
    }

    /**
     * @return array<string, string>
     */
    private function dailyLoanSourceHeaderAliasMap(): array
    {
        return [
            'PERIODE' => 'PERIODE',
            'KODE_KANWIL' => 'KODE_KANWIL1',
            'KODE_KANWIL1' => 'KODE_KANWIL1',
            'KANWIL' => 'KANWIL1',
            'KANWIL1' => 'KANWIL1',
            'KODE_CABANG' => 'KODE_CABANG1',
            'KODE_CABANG1' => 'KODE_CABANG1',
            'CABANG' => 'CABANG1',
            'CABANG1' => 'CABANG1',
            'BRANCH' => 'BRANCH1',
            'BRANCH1' => 'BRANCH1',
            'UNIT' => 'UNIT1',
            'UNIT1' => 'UNIT1',
            'CURTYP' => 'CURTYP',
            'AO_NAME' => 'AO_NAME',
            'AO' => 'AO_NAME',
            'CIFNO' => 'CIFNO',
            'NOMOR_REKENING' => 'NOMOR_REKENING1',
            'NOMOR_REKENING1' => 'NOMOR_REKENING1',
            'STATUS_REKENING' => 'STATUS_REKENING1',
            'STATUS_REKENING1' => 'STATUS_REKENING1',
            'LN_TYPE' => 'LN_TYPE',
            'NAMA_DEBITUR' => 'NAMA_DEBITUR1',
            'NAMA_DEBITUR1' => 'NAMA_DEBITUR1',
            'RATE' => 'RATE',
            'JANGKA_WAKTU' => 'JANGKA_WAKTU1',
            'JANGKA_WAKTU1' => 'JANGKA_WAKTU1',
            'PLAFON' => 'PLAFON',
            'BAKI_DEBET' => 'BAKI_DEBET1',
            'BAKI_DEBET1' => 'BAKI_DEBET1',
            'CKPN' => 'CKPN',
            'NILAI_TERCATAT' => 'NILAI_TERCATAT1',
            'NILAI_TERCATAT1' => 'NILAI_TERCATAT1',
            'KOL_ADK' => 'KOL_ADK1',
            'KOL_ADK1' => 'KOL_ADK1',
            'JML_ANGSURAN' => 'JML_ANGSURAN1',
            'JML_ANGSURAN1' => 'JML_ANGSURAN1',
            'PN_NAME' => 'PN_NAME1',
            'PN_NAME1' => 'PN_NAME1',
            'PN_PEMRAKARSA' => 'PN_PEMRAKARSA1',
            'PN_PEMRAKARSA1' => 'PN_PEMRAKARSA1',
            'PN_REFERRAL' => 'PN_REFERRAL1',
            'PN_REFERRAL1' => 'PN_REFERRAL1',
            'PN_RESTRUK' => 'PN_RESTRUK1',
            'PN_RESTRUK1' => 'PN_RESTRUK1',
            'PN_PEMUTUS' => 'PN_PEMUTUS1',
            'PN_PEMUTUS1' => 'PN_PEMUTUS1',
            'PN_CRM' => 'PN_CRM1',
            'PN_CRM1' => 'PN_CRM1',
            'PN_REFERRAL_NAIK_KELAS' => 'PN_REFERRAL_NAIK_KELAS1',
            'PN_REFERRAL_NAIK_KELAS1' => 'PN_REFERRAL_NAIK_KELAS1',
            'JUMLAH_PN' => 'JUMLAH_PN1',
            'JUMLAH_PN1' => 'JUMLAH_PN1',
            'JUMLAH_PN_ALL' => 'JUMLAH_PN_ALL1',
            'JUMLAH_PN_ALL1' => 'JUMLAH_PN_ALL1',
            'RESTRUK_KE' => 'RESTRUK_KE1',
            'RESTRUK_KE1' => 'RESTRUK_KE1',
            'JENIS_RESTRUK' => 'JENIS_RESTRUK1',
            'JENIS_RESTRUK1' => 'JENIS_RESTRUK1',
            'FLAG_RESTRUK_COVID' => 'FLAG_RESTRUK_COVID1',
            'FLAG_RESTRUK_COVID1' => 'FLAG_RESTRUK_COVID1',
            'FLAG_COMMODITY_CHAIN' => 'FLAG_COMMODITY_CHAIN1',
            'FLAG_COMMODITY_CHAIN1' => 'FLAG_COMMODITY_CHAIN1',
            'FLAG_BRIGUNA_DIGITAL' => 'FLAG_BRIGUNA_DIGITAL1',
            'FLAG_BRIGUNA_DIGITAL1' => 'FLAG_BRIGUNA_DIGITAL1',
            'PMTAMT_BASE' => 'PMTAMT_Base',
            'TEXTBOX20' => 'Textbox20',
            'TEXTBOX21' => 'Textbox21',
            'TAGIHAN_POKOK' => 'BILPRN',
            'TAGIHAN_BUNGA' => 'BILINT',
            'TAGIHAN_DENDA' => 'BILLC',
            'TOTAL_DEFERRED_INTEREST_DITUNDA_DAN_BELUM_DIJADWALKAN' => 'LBDOTU',
        ];
    }

    private function canonicalizeDailyLoanSourceHeaders(array $headers): array
    {
        if (!$this->isDailyLoanTable()) {
            return array_values($headers);
        }

        $aliasMap = $this->dailyLoanSourceHeaderAliasMap();
        $canonicalHeaders = [];
        $matched = 0;

        foreach (array_values($headers) as $index => $header) {
            $header = trim((string) $header);
            if ($header === '') {
                $canonicalHeaders[$index] = 'COL_' . $index;
                continue;
            }

            $normalizedHeader = preg_replace('/[^A-Z0-9]+/', '_', strtoupper($header));
            $normalizedHeader = trim((string) $normalizedHeader, '_');

            if (isset($aliasMap[$normalizedHeader])) {
                $canonicalHeaders[$index] = $aliasMap[$normalizedHeader];
                $matched++;
                continue;
            }

            $canonicalHeaders[$index] = $header;
        }

        $hasCoreSignature = isset($canonicalHeaders[0], $canonicalHeaders[1], $canonicalHeaders[9], $canonicalHeaders[17])
            && $canonicalHeaders[0] === 'PERIODE'
            && $canonicalHeaders[1] === 'KODE_KANWIL1'
            && $canonicalHeaders[9] === 'CIFNO'
            && $canonicalHeaders[17] === 'BAKI_DEBET1';

        if ($matched >= 12 || $hasCoreSignature) {
            return $canonicalHeaders;
        }

        return array_values($headers);
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

        return $this->normalizeCsvRow($row, $delimiter);
    }

    private function getDailyLoanExpectedCsvColumns(?int $expectedColumns = null): ?int
    {
        if ($expectedColumns !== null && $expectedColumns > 0) {
            return $expectedColumns;
        }

        return $this->isDailyLoanTable() ? count(self::DAILY_LOAN_SOURCE_HEADERS) : null;
    }

    private function resetDailyLoanCsvParseMeta(?int $expectedColumns = null): void
    {
        $this->lastDailyLoanCsvParseMeta = [
            'status' => 'normal',
            'reason' => null,
            'expected_columns' => $expectedColumns,
            'actual_columns' => null,
            'repaired' => false,
        ];
    }

    private function parseDailyLoanCsvRow(array $row, string $delimiter, ?int $expectedColumns = null): array
    {
        $expectedColumns = $this->getDailyLoanExpectedCsvColumns($expectedColumns);
        $this->resetDailyLoanCsvParseMeta($expectedColumns);

        if (!$this->isDailyLoanTable()) {
            return $row;
        }

        $parsed = $this->csvAutoRepairService()->parseDailyLoanCsvRow($row, $delimiter, $expectedColumns);
        $this->lastDailyLoanCsvParseMeta = $this->csvAutoRepairService()->getLastParseMeta();

        return $parsed;
    }

    private function buildDailyLoanCsvParseCandidates(array $row, string $delimiter): array
    {
        $candidates = [];
        $seen = [];

        $pushCandidate = function (array $candidateRow, bool $repaired = false) use (&$candidates, &$seen): void {
            $key = md5(json_encode(array_values($candidateRow), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $candidates[] = [
                'row' => array_values($candidateRow),
                'repaired' => $repaired,
            ];
        };

        $pushCandidate($row, false);

        $serializedPayload = $this->extractSerializedDailyLoanPayload($row, $delimiter);
        if ($serializedPayload !== null) {
            foreach ($this->buildDailyLoanSerializedPayloadVariants($serializedPayload) as $variant) {
                $parsed = str_getcsv($variant, $delimiter, '"', '\\');
                if (count($parsed) > 1) {
                    $pushCandidate($parsed, true);
                }
            }
        }

        if (count($row) > 1) {
            $joinedRow = implode($delimiter, array_map(static fn ($value): string => (string) $value, $row));
            foreach ($this->buildDailyLoanSerializedPayloadVariants($joinedRow) as $variant) {
                $parsed = str_getcsv($variant, $delimiter, '"', '\\');
                if (count($parsed) > 1) {
                    $pushCandidate($parsed, true);
                }
            }

            if (isset($row[0]) && is_string($row[0])) {
                $firstCellParsed = str_getcsv((string) $row[0], $delimiter, '"', '\\');
                if (count($firstCellParsed) > 1) {
                    $flattened = array_merge($firstCellParsed, array_slice($row, 1));
                    $pushCandidate($flattened, true);
                }
            }
        }

        return $candidates;
    }

    private function extractSerializedDailyLoanPayload(array $row, string $delimiter): ?string
    {
        if (!$this->isDailyLoanTable() || count($row) !== 1 || !isset($row[0]) || !is_string($row[0])) {
            return null;
        }

        $rawValue = trim($row[0]);
        if ($rawValue === '' || !str_contains($rawValue, $delimiter)) {
            return null;
        }

        return $rawValue;
    }

    private function buildDailyLoanSerializedPayloadVariants(string $payload): array
    {
        $variants = [];
        $seen = [];

        $pushVariant = static function (string $value) use (&$variants, &$seen): void {
            $normalized = trim($value);
            if ($normalized === '' || isset($seen[$normalized])) {
                return;
            }

            $seen[$normalized] = true;
            $variants[] = $normalized;
        };

        $pushVariant($payload);

        $normalizedSerializedPayload = $this->normalizeSerializedDailyLoanPayload($payload);
        if ($normalizedSerializedPayload !== null) {
            $pushVariant($normalizedSerializedPayload);
        }

        $pushVariant(str_replace('\,', ',', $payload));
        $pushVariant(str_replace(';"', '', $payload));
        $pushVariant(str_replace(['\,' , ';"'], [',', ''], $payload));

        return $variants;
    }

    private function normalizeSerializedDailyLoanPayload(string $payload): ?string
    {
        $payload = trim($payload);
        if ($payload === '' || !str_starts_with($payload, '"')) {
            return null;
        }

        if (preg_match('/^"(.*)"(?:;*)$/s', $payload, $matches) !== 1) {
            return null;
        }

        $normalized = str_replace('""', '"', (string) ($matches[1] ?? ''));
        $normalized = trim($normalized);

        return $normalized === '' ? null : $normalized;
    }

    private function repairDailyLoanParsedFields(array $fields, int $expectedColumns): array
    {
        $current = array_values($fields);
        $repaired = false;

        foreach ($current as $index => $value) {
            if (!is_string($value)) {
                continue;
            }

            $normalizedValue = preg_replace('/^;"?/', '', $value);
            if ($normalizedValue !== $value) {
                $repaired = true;
                $current[$index] = $normalizedValue;
            }
        }

        if (count($current) < $expectedColumns) {
            $expanded = $this->expandDailyLoanMergedZipCodeFields($current, $expectedColumns);
            $repaired = $repaired || $expanded !== $current;
            $current = $expanded;
        }

        if (count($current) > $expectedColumns) {
            $merged = $this->mergeDailyLoanNumericFragments($current, $expectedColumns);
            $repaired = $repaired || $merged !== $current;
            $current = $merged;
        }

        if (count($current) < $expectedColumns) {
            return [
                'row' => $current,
                'repaired' => $repaired,
                'reason' => 'repair_failed_underflow',
            ];
        }

        if (count($current) > $expectedColumns) {
            return [
                'row' => $current,
                'repaired' => $repaired,
                'reason' => 'repair_failed_overflow',
            ];
        }

        return [
            'row' => $current,
            'repaired' => $repaired,
            'reason' => $repaired ? 'auto_repaired' : null,
        ];
    }

    private function expandDailyLoanMergedZipCodeFields(array $fields, int $expectedColumns): array
    {
        $current = array_values($fields);

        while (count($current) < $expectedColumns) {
            $changed = false;

            foreach ($current as $index => $value) {
                if (!is_string($value) || !preg_match('/^(.*?),(\\d{4,6})$/', trim($value), $matches)) {
                    continue;
                }

                $left = trim((string) $matches[1]);
                $right = trim((string) $matches[2]);
                if ($left === '' || $right === '') {
                    continue;
                }

                array_splice($current, $index, 1, [$left, $right]);
                $changed = true;
                break;
            }

            if (!$changed) {
                break;
            }
        }

        return $current;
    }

    private function mergeDailyLoanNumericFragments(array $fields, int $expectedColumns): array
    {
        $current = array_values($fields);

        while (count($current) > $expectedColumns) {
            $changed = false;
            $merged = [];

            for ($index = 0; $index < count($current); $index++) {
                if (
                    count($merged) + (count($current) - $index) <= $expectedColumns
                ) {
                    $merged = array_merge($merged, array_slice($current, $index));
                    break;
                }

                $partA = (string) $current[$index];
                $partB = (string) ($current[$index + 1] ?? '');
                $partC = (string) ($current[$index + 2] ?? '');

                if (
                    $index + 2 < count($current)
                    && preg_match('/^-?\d{1,3}$/', trim($partA))
                    && preg_match('/^\d{3}$/', trim($partB))
                    && preg_match('/^\d{1,3}(?:\.\d+)?(?:""|")?;?$/', trim($partC))
                ) {
                    $merged[] = trim($partA) . ',' . trim($partB) . ',' . trim($partC);
                    $index += 2;
                    $changed = true;
                    continue;
                }

                if (
                    $index + 1 < count($current)
                    && preg_match('/^-?\d{1,3}$/', trim($partA))
                    && preg_match('/^\d{1,3}(?:\.\d+)?(?:""|")?;?$/', trim($partB))
                ) {
                    $merged[] = trim($partA) . ',' . trim($partB);
                    $index += 1;
                    $changed = true;
                    continue;
                }

                $merged[] = $current[$index];
            }

            if (!$changed) {
                break;
            }

            $current = $merged;
        }

        return $current;
    }

    private function reparseSerializedDailyLoanCsvRow(array $row, string $delimiter, ?int $expectedColumns = null): array
    {
        return $this->parseDailyLoanCsvRow($row, $delimiter, $expectedColumns);
    }

    private function normalizeQuotedCsvCellValue($value): string
    {
        return $this->smartNormalizeQuotedCsvCellValue($value);
    }

    private function resolveCsvRowValueByHeader(array $headers, array $row, string $targetHeader): ?string
    {
        $target = $this->normalizeImportColumnName($targetHeader);

        foreach ($headers as $index => $header) {
            if ($this->normalizeImportColumnName((string) $header) !== $target) {
                continue;
            }

            $value = $row[$index] ?? null;
            if ($value === null) {
                return null;
            }

            $normalized = trim((string) $value);

            return $normalized === '' ? null : $normalized;
        }

        return null;
    }

    private function buildCsvRowPreview(array $row, string $delimiter): string
    {
        return $this->csvAutoRepairService()->buildCsvRowPreview($row, $delimiter);
    }

    private function hasDailyLoanFieldCountMismatch(
        array $headers,
        array $row,
        int $lineNumber,
        string $source,
        string $delimiter = ','
    ): bool {
        if (!$this->isDailyLoanTable()) {
            return false;
        }

        $expectedColumns = count($headers);
        if ($expectedColumns === 0 || count($row) === $expectedColumns) {
            return false;
        }

        Log::warning('Daily Loan CSV field count mismatch; row skipped.', [
            'table_name' => 'daily_loan_dinamis',
            'source' => $source,
            'line_number' => $lineNumber,
            'expected_columns' => $expectedColumns,
            'actual_columns' => count($row),
            'parse_status' => $this->lastDailyLoanCsvParseMeta['status'] ?? 'irrecoverable',
            'parse_reason' => $this->lastDailyLoanCsvParseMeta['reason'] ?? 'field_count_mismatch',
            'was_repaired' => (bool) ($this->lastDailyLoanCsvParseMeta['repaired'] ?? false),
            'periode' => $this->resolveCsvRowValueByHeader($headers, $row, 'periode'),
            'kode_kanwil1' => $this->resolveCsvRowValueByHeader($headers, $row, 'kode_kanwil1'),
            'nomor_rekening1' => $this->resolveCsvRowValueByHeader($headers, $row, 'nomor_rekening1'),
            'nama_debitur1' => $this->resolveCsvRowValueByHeader($headers, $row, 'nama_debitur1'),
            'row_preview' => $this->buildCsvRowPreview($row, $delimiter),
        ]);

        return true;
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

        return $this->isValidSimpananMultiPnRowValues($valuesByHeader);
    }

    protected function isCompleteDailyLoanSourceRow(array $headers, array $row): bool
    {
        if (!$this->isDailyLoanTable()) {
            return true;
        }

        $row = $this->padRow($row, count($headers));
        $valuesByHeader = [];

        foreach ($headers as $index => $header) {
            $normalizedHeader = $this->normalizeImportColumnName((string) $header);
            if ($normalizedHeader === '') {
                continue;
            }

            $valuesByHeader[$normalizedHeader] = $row[$index] ?? null;
        }

        return $this->isValidDailyLoanRowValues($valuesByHeader);
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

        $normalized = [];
        foreach ($valuesByLowerKey as $key => $value) {
            $normalized[strtolower(trim((string) $key))] = trim((string) ($value ?? ''));
        }

        return $this->isValidSimpananMultiPnRowValues($normalized);
    }

    private function hasRequiredDailyLoanImportData(array $row): bool
    {
        if (!$this->isDailyLoanTable()) {
            return true;
        }

        $valuesByLowerKey = [];
        foreach ($row as $key => $value) {
            $valuesByLowerKey[strtolower((string) $key)] = $value;
        }

        return $this->isValidDailyLoanRowValues($valuesByLowerKey);
    }

    private function isValidSimpananMultiPnRowValues(array $valuesByHeader): bool
    {
        $posisi = trim((string) ($valuesByHeader['posisi'] ?? ''));
        $cifno = trim((string) ($valuesByHeader['cifno'] ?? ''));
        $noRekening = trim((string) ($valuesByHeader['no_rekening'] ?? ''));
        $jenis = strtoupper(trim((string) ($valuesByHeader['jenis_simpanan'] ?? '')));
        $saldo = trim((string) ($valuesByHeader['saldo_idr'] ?? ''));

        if ($posisi === '' || $cifno === '' || $noRekening === '' || $jenis === '' || $saldo === '') {
            return false;
        }

        if (!$this->isValidSimpananPosisi($posisi)) {
            return false;
        }

        if (!preg_match('/^[A-Z0-9.,+_\\/\'-]+$/i', $noRekening) || strlen($noRekening) < 6) {
            return false;
        }

        if (
            !str_starts_with($jenis, 'TABUNGAN')
            && !str_starts_with($jenis, 'GIRO')
            && !str_starts_with($jenis, 'DEPOSITO')
        ) {
            return false;
        }

        return $this->normalizeDecimalValue($saldo) !== null;
    }

    private function isValidDailyLoanRowValues(array $valuesByHeader): bool
    {
        $periode = $this->normalizeExcelValue('PERIODE', $valuesByHeader['periode'] ?? null);
        $kodeKanwil = trim((string) ($valuesByHeader['kode_kanwil1'] ?? ''));
        $nomorRekening = trim((string) ($valuesByHeader['nomor_rekening1'] ?? ''));
        $bakiDebet = $this->normalizeDecimalValue($valuesByHeader['baki_debet1'] ?? null);

        if ($periode === null || $nomorRekening === '' || $bakiDebet === null) {
            return false;
        }

        if ($this->isDailyLoanDateLikeCodeValue($kodeKanwil)) {
            return false;
        }

        return true;
    }

    private function isDailyLoanDateLikeCodeValue(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }

        return preg_match('/^\d{8}$/', $value) === 1
            || preg_match('/^\d{4}[-\/]\d{2}[-\/]\d{2}$/', $value) === 1
            || preg_match('/^\d{2}[-\/]\d{2}[-\/]\d{4}$/', $value) === 1
            || preg_match('/^\d{2}[-\/]\d{2}[-\/]\d{2}$/', $value) === 1
            || preg_match('/^\d{4}[-\/]\d{2}[-\/]\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $value) === 1
            || preg_match('/^\d{2}[-\/]\d{2}[-\/]\d{4}\s+\d{2}:\d{2}(:\d{2})?$/', $value) === 1;
    }

    private function isValidSimpananPosisi(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        // Angka pendek biasanya nomor urut baris yang geser ke kolom posisi.
        if (preg_match('/^\d{1,5}$/', $value) === 1) {
            return false;
        }

        if (preg_match('/^\d{8}$/', $value) === 1) {
            foreach (['Ymd', 'dmY', 'mdY'] as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $value);
                    if ($date !== false) {
                        $year = (int) $date->format('Y');
                        if ($year >= 2000 && $year <= 2100) {
                            return true;
                        }
                    }
                } catch (\Throwable) {
                    // lanjut cek format berikutnya
                }
            }
        }

        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial < 20000 || $serial > 80000) {
                return false;
            }

            try {
                $date = Carbon::instance(ExcelDate::excelToDateTimeObject($serial));
                $year = (int) $date->format('Y');

                return $year >= 2000 && $year <= 2100;
            } catch (\Throwable) {
                return false;
            }
        }

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    $year = (int) $date->format('Y');
                    if ($year >= 2000 && $year <= 2100) {
                        return true;
                    }
                }
            } catch (\Throwable) {
                // lanjut cek format berikutnya
            }
        }

        $normalized = StrictDateParser::normalize($value);
        if ($normalized === null) {
            return false;
        }

        $year = (int) substr($normalized, 0, 4);

        return $year >= 2000 && $year <= 2100;
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

    private function resolveSourceHeadersForImport(string $path, int $headerIndex, array $fallbackHeaders): array
    {
        if ($headerIndex < 0 || !file_exists($path)) {
            return array_values($fallbackHeaders);
        }

        try {
            if ($this->isCsvFile($path)) {
                $delimiter = $this->detectCsvDelimiter($path);
                $handle = @fopen($path, 'r');
                if ($handle === false) {
                    return array_values($fallbackHeaders);
                }

                $currentIndex = 0;
                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    if ($currentIndex === $headerIndex) {
                        fclose($handle);
                        $row = $this->normalizeCsvRow($row, $delimiter);
                        $row = $this->canonicalizeDailyLoanSourceHeaders($row);

                        return array_values(array_map(function ($headerValue, $index) {
                            $label = trim((string) $headerValue);
                            return $label !== '' ? $label : ('COL_' . $index);
                        }, $row, array_keys($row)));
                    }

                    $currentIndex++;
                }

                fclose($handle);
                return array_values($fallbackHeaders);
            }

            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $chunkFilter = new ChunkReadFilter();
            $chunkFilter->setRows($headerIndex + 1, 1);
            $reader->setReadFilter($chunkFilter);

            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $headerRow = (array) ($sheet[$headerIndex] ?? []);
            if ($headerRow === []) {
                return array_values($fallbackHeaders);
            }

            $headers = [];
            foreach ($headerRow as $index => $headerValue) {
                $label = trim((string) $headerValue);
                $headers[] = $label !== '' ? $label : ('COL_' . $index);
            }

            return array_values($headers);
        } catch (\Throwable) {
            return array_values($fallbackHeaders);
        }
    }

    protected function stripIgnoredPreviewColumns(array $headers, array $rows, array $formattedUniqueValues, string $tableName): array
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

    protected function orderPreviewColumns(array $headers, array $formattedUniqueValues, array $preview, string $tableName): array
    {
        $dbColumns = $this->cachedSchemaHasTable($tableName)
            ? $this->cachedSchemaColumnListing($tableName)
            : [];

        $reorderedPayload = $this->reorderPreviewPayload($headers, $formattedUniqueValues, $preview, $dbColumns);
        $displayFilterMap = [];
        $usedSourceIndexes = [];

        foreach ($reorderedPayload['headers'] as $displayIndex => $header) {
            foreach ($headers as $sourceIndex => $sourceHeader) {
                if (isset($usedSourceIndexes[$sourceIndex])) {
                    continue;
                }

                if ($this->normalizeHeaderForDatabase((string) $sourceHeader) === $this->normalizeHeaderForDatabase((string) $header)) {
                    $displayFilterMap[$displayIndex] = $sourceIndex;
                    $usedSourceIndexes[$sourceIndex] = true;
                    break;
                }
            }

            if (!array_key_exists($displayIndex, $displayFilterMap)) {
                $displayFilterMap[$displayIndex] = $displayIndex;
            }
        }

        return [
            'headers' => $reorderedPayload['headers'],
            'formatted_unique_values' => $reorderedPayload['formattedUniqueValues'],
            'preview' => $reorderedPayload['preview'],
            'display_filter_map' => $displayFilterMap,
        ];
    }

    protected function hasMeaningfulImportData(array $row, array $ignoredKeys = []): bool
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

            $previewSettings = $this->getCsvPreviewLimits();
            $previewLimit = (int) ($previewSettings['preview_limit'] ?? 100);
            $uniqueLimit = (int) ($previewSettings['unique_scan_limit'] ?? 5000);
            $maxUniqueValuesPerColumn = (int) ($previewSettings['max_unique_values_per_column'] ?? 300);
            $cleanPreview = [];
            $formattedUniqueValues = [];
            $rowsProcessedForUniques = 0;
            $totalAvailableRows = 0;

            foreach (array_keys($headers) as $index) {
                $formattedUniqueValues[$index] = [];
            }

            $lineNumber = 1;
            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                $lineNumber++;
                if (empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                    continue;
                }

                if ($this->hasDailyLoanFieldCountMismatch($headers, $row, $lineNumber, 'build_preview_payload_from_csv', $delimiter)) {
                    continue;
                }

                $row = $this->padRow($row, count($headers));
                if ($this->isSimpananMultiPnTable() && !$this->isCompleteSimpananMultiPnSourceRow($headers, $row)) {
                    continue;
                }

                if (!$this->isCompleteDailyLoanSourceRow($headers, $row)) {
                    continue;
                }

                $totalAvailableRows++;
                $displayRow = [];

                foreach ($headers as $index => $header) {
                    $value = $this->normalizeExcelValue($header, $row[$index] ?? null);
                    $displayRow[$header] = $value;

                    if ($rowsProcessedForUniques < $uniqueLimit) {
                        $formattedValue = $value === null || $value === '' ? '(Blank)' : (string) $value;
                        if (
                            isset($formattedUniqueValues[$index][$formattedValue]) ||
                            count($formattedUniqueValues[$index]) < $maxUniqueValuesPerColumn
                        ) {
                            $formattedUniqueValues[$index][$formattedValue] = true;
                        }
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

                if (count($cleanPreview) >= $previewLimit && $rowsProcessedForUniques >= $uniqueLimit) {
                    break;
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

    private function clearDailyLoanImportSessionState(): void
    {
        if ((int) session('active_id_report') !== self::DAILY_LOAN_REPORT_ID) {
            return;
        }

        session()->forget([
            'active_id_report',
            'excel_path',
            'excel_preview_key',
            'excel_headers',
            'excel_display_filter_map',
            'excel_preview_meta',
            'excel_import_params',
            'excel_import_source',
        ]);
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
        return $this->uploadExcel($this->useDailyLoanReport($request), ['csv', 'txt']);
    }

    public function initDailyLoanChunkUpload(Request $request)
    {
        $request->validate([
            'original_name' => 'required|string',
            'total_size' => 'nullable|integer|min:1',
        ]);

        $originalName = trim((string) $request->input('original_name'));
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Format file Daily Loan harus CSV atau TXT.',
            ], 422);
        }

        $uploadId = 'dailyloan_' . Str::uuid()->toString();
        $directory = $this->ensureChunkUploadDirectory($uploadId);

        file_put_contents($directory . DIRECTORY_SEPARATOR . 'meta.json', json_encode([
            'original_name' => $originalName,
            'total_size' => (int) ($request->input('total_size') ?? 0),
            'created_at' => now()->toIso8601String(),
            'user_id' => auth()->id(),
        ], JSON_PRETTY_PRINT));

        return response()->json([
            'status' => 'success',
            'upload_id' => $uploadId,
        ]);
    }

    public function uploadDailyLoanChunk(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|string',
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
            'file' => 'required|file',
        ]);

        $uploadId = trim((string) $request->input('upload_id'));
        $directory = $this->chunkUploadDirectory($uploadId);

        if (!is_dir($directory)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi upload chunk tidak ditemukan. Silakan upload ulang.',
            ], 404);
        }

        $chunkFile = $request->file('file');
        if (!$chunkFile || !$chunkFile->isValid()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chunk upload tidak valid.',
            ], 422);
        }

        $chunkIndex = (int) $request->input('chunk_index');
        $targetPath = $directory . DIRECTORY_SEPARATOR . sprintf('part_%06d.bin', $chunkIndex);
        $chunkFile->move($directory, basename($targetPath));

        return response()->json([
            'status' => 'success',
            'chunk_index' => $chunkIndex,
        ]);
    }

    public function finalizeDailyLoanChunkUpload(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|string',
            'total_chunks' => 'required|integer|min:1',
            'original_name' => 'required|string',
        ]);

        $uploadId = trim((string) $request->input('upload_id'));
        $totalChunks = (int) $request->input('total_chunks');
        $originalName = trim((string) $request->input('original_name'));
        $directory = $this->chunkUploadDirectory($uploadId);

        if (!is_dir($directory)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Folder upload chunk tidak ditemukan.',
            ], 404);
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Format file Daily Loan harus CSV atau TXT.',
            ], 422);
        }

        if (!file_exists(Storage::path('excel_imports'))) {
            Storage::makeDirectory('excel_imports');
        }

        $safeOriginalName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $relativePath = 'excel_imports/' . date('Ymd_His') . '_' . Str::random(6) . '_' . ($safeOriginalName ?: 'daily-loan') . '.' . $extension;
        $absolutePath = Storage::path($relativePath);

        $outputHandle = fopen($absolutePath, 'wb');
        if ($outputHandle === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyiapkan file upload final.',
            ], 500);
        }

        try {
            for ($index = 0; $index < $totalChunks; $index++) {
                $partPath = $directory . DIRECTORY_SEPARATOR . sprintf('part_%06d.bin', $index);
                if (!file_exists($partPath)) {
                    throw new \RuntimeException('Chunk ke-' . ($index + 1) . ' belum lengkap.');
                }

                $inputHandle = fopen($partPath, 'rb');
                if ($inputHandle === false) {
                    throw new \RuntimeException('Gagal membaca chunk ke-' . ($index + 1) . '.');
                }

                while (!feof($inputHandle)) {
                    $buffer = fread($inputHandle, 1024 * 1024);
                    if ($buffer === false) {
                        fclose($inputHandle);
                        throw new \RuntimeException('Gagal membaca isi chunk ke-' . ($index + 1) . '.');
                    }
                    fwrite($outputHandle, $buffer);
                }

                fclose($inputHandle);
            }
        } catch (\Throwable $e) {
            fclose($outputHandle);
            @unlink($absolutePath);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        fclose($outputHandle);
        $this->cleanupChunkUploadDirectory($directory);

        $cacheKey = 'excel_preview_' . md5($relativePath . '|' . (auth()->id() ?? 'guest') . '|' . microtime(true));
        session([
            'excel_path' => $relativePath,
            'active_id_report' => self::DAILY_LOAN_REPORT_ID,
            'excel_preview_key' => $cacheKey,
        ]);

        return response()->json([
            'status' => 'success',
            'cache_key' => $cacheKey,
            'redirect' => route('import.dailyloan.preview', ['ck' => $cacheKey]),
        ]);
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
        return $this->processDailyLoanImportStream($this->useDailyLoanReport($request));
    }

    public function chunkDailyLoanImport(Request $request)
    {
        return $this->processExcelChunk($this->useDailyLoanReport($request));
    }

    public function uploadSimpananMultiPnExcel(Request $request)
    {
        $file = $request->file('file');
        $originalExtension = $file ? strtolower((string) $file->getClientOriginalExtension()) : '';

        if (in_array($originalExtension, ['csv', 'txt'], true)) {
            return app(ImportSimpananMultiPnCsvController::class)->upload($request);
        }

        return $this->uploadExcel($this->useSimpananMultiPnReport($request), ['xlsx', 'xls', 'csv', 'txt']);
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

    private function processDailyLoanImportStream(Request $request)
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $sessionParams = session('excel_import_params', []);
        $jobId = (int) ($sessionParams['job_id'] ?? $request->query('job_id', 0));
        $jobState = $this->excelImportJobService()->getImportJobState($jobId);
        $params = !empty($jobState['params']) ? (array) $jobState['params'] : $sessionParams;
        $normalizedHeaders = !empty($jobState['headers']) ? (array) $jobState['headers'] : session('excel_headers', []);
        $eligibility = $this->resolveDirectCsvFastPathEligibility('daily_loan', $params, $normalizedHeaders);

        if (!($eligibility['eligible'] ?? false)) {
            $request->attributes->set('queue_message', (string) ($eligibility['reason'] ?? 'Fast import tidak tersedia. Menggunakan safe path queue.'));
            return $this->processExcelStream($request);
        }

        $relativePath = (string) ($eligibility['relative_path'] ?? '');
        $absolutePath = (string) ($eligibility['absolute_path'] ?? '');
        $totalRows = (int) ($eligibility['total_rows'] ?? 0);

        request()->session()->save();

        return response()->stream(function () use ($jobId, $relativePath, $absolutePath, $totalRows, $normalizedHeaders) {
            $streamLock = null;
            $send = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                if ($jobId > 0) {
                    $streamLock = Cache::lock('import_excel_stream_job_' . $jobId, 7200);

                    if (!$streamLock->get()) {
                        $job = DB::table('import_jobs')->where('id', $jobId)->first();

                        if ($job && in_array($job->status, ['completed', 'failed', 'failed_partial'], true)) {
                            $payload = [
                                'status' => (string) $job->status,
                                'total_success' => (int) ($job->total_success ?? 0),
                                'total_failed' => (int) ($job->total_failed ?? 0),
                                'total_rows' => (int) ($job->total_files ?? 0),
                            ];
                            $send($job->status === 'completed' ? 'complete' : 'error', $payload);
                            $this->clearDailyLoanImportSessionState();
                        } else {
                            $send('error', ['message' => 'Job import ini sudah sedang diproses pada koneksi lain.']);
                        }
                        return;
                    }
                }

                if (!file_exists($absolutePath)) {
                    if ($jobId > 0) {
                        $this->progressService()->markFailed(
                            $jobId,
                            'File sumber import Daily Loan Dinamis tidak ditemukan atau sudah dihapus. Silakan upload ulang.',
                            0,
                            0,
                            'failed'
                        );
                        $this->progressService()->cleanupQueuedImportJobRowsForJob($jobId);
                    }

                    $this->clearDailyLoanImportSessionState();
                    $send('error', ['message' => 'File CSV Daily Loan tidak ditemukan di server.']);
                    return;
                }

                if ($jobId > 0) {
                    $this->progressService()->markProcessing($jobId, [
                        'status' => 'processing',
                        'phase' => 'validating',
                        'percent' => 3,
                        'message' => 'Validasi fast import Daily Loan dimulai.',
                        'processed_rows' => 0,
                        'total_rows' => $totalRows,
                    ]);
                }

                $send('progress', [
                    'status' => 'processing',
                    'phase' => 'validating',
                    'percent' => 3,
                    'message' => 'Validasi file fast import Daily Loan...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $handled = $this->processDailyLoanDirectCsvStream(
                    $send,
                    $absolutePath,
                    'daily_loan_dinamis',
                    $normalizedHeaders,
                    $jobId,
                    $totalRows,
                    null,
                    false
                );

                if (!$handled) {
                    throw new \RuntimeException('Fast import Daily Loan tidak dapat dijalankan.');
                }

                $job = $jobId > 0 ? $this->progressService()->findJob($jobId) : null;
                $status = (string) ($job->status ?? 'completed');
                $totalSuccess = (int) ($job->total_success ?? 0);
                $totalFailed = (int) ($job->total_failed ?? 0);
                $finalTotalRows = (int) ($job->total_files ?? max($totalRows, $totalSuccess + $totalFailed));

                if (in_array($status, ['completed', 'failed_partial'], true)) {
                    $syncPayload = [
                        'status' => 'processing',
                        'phase' => 'syncing_report',
                        'percent' => 99,
                        'message' => 'Sinkronisasi report hasil import Daily Loan...',
                        'processed_rows' => $totalSuccess + $totalFailed,
                        'total_rows' => $finalTotalRows,
                        'total_success' => $totalSuccess,
                        'total_failed' => $totalFailed,
                    ];
                    $this->cacheFastImportProgress($jobId, $syncPayload);
                    $send('progress', array_merge($syncPayload, [
                        'rows_done' => $totalSuccess + $totalFailed,
                        'total' => $finalTotalRows,
                        'speed' => 0,
                    ]));

                    $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $absolutePath);
                }

                $terminalPayload = [
                    'status' => $status,
                    'total_success' => $totalSuccess,
                    'total_failed' => $totalFailed,
                    'total_rows' => $finalTotalRows,
                ];

                if ($status === 'completed') {
                    $this->clearDailyLoanImportSessionState();
                    $send('complete', $terminalPayload);
                    return;
                }

                $this->clearDailyLoanImportSessionState();
                $send('error', array_merge($terminalPayload, [
                    'message' => $status === 'failed_partial'
                        ? 'Fast import Daily Loan selesai dengan kegagalan parsial.'
                        : 'Fast import Daily Loan gagal diproses.',
                ]));
            } catch (\Throwable $e) {
                Log::warning('Daily Loan direct path failed, trying staged fallback: ' . $e->getMessage(), [
                    'job_id' => $jobId,
                    'absolute_path' => $absolutePath,
                ]);

                try {
                    $send('progress', [
                        'status' => 'processing',
                        'phase' => 'preparing_load_plan',
                        'percent' => 10,
                        'message' => 'Direct path gagal. Mengalihkan ke staged bulk import Daily Loan...',
                        'rows_done' => 0,
                        'total' => $totalRows,
                        'speed' => 0,
                    ]);

                    $handled = $this->processStagedCsvStream(
                        $send,
                        $absolutePath,
                        'daily_loan_dinamis',
                        [],
                        $normalizedHeaders,
                        $jobId,
                        $totalRows
                    );

                    if ($handled) {
                        $job = $jobId > 0 ? $this->progressService()->findJob($jobId) : null;
                        if ($job && $job->status === 'completed') {
                            $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $absolutePath);
                        }
                        return;
                    }
                } catch (\Throwable $fallbackException) {
                    Log::error('DAILY LOAN STAGED FALLBACK ERROR: ' . $fallbackException->getMessage() . ' | ' . $fallbackException->getFile() . ':' . $fallbackException->getLine());
                }

                Log::error('DAILY LOAN DIRECT CSV LOAD ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

                if ($jobId > 0) {
                    $job = $this->progressService()->findJob($jobId);
                    $this->progressService()->markFailed(
                        $jobId,
                        'Fast import Daily Loan gagal: ' . $e->getMessage(),
                        (int) ($job->total_success ?? 0),
                        (int) ($job->total_failed ?? 0)
                    );
                }

                $this->clearDailyLoanImportSessionState();
                $send('error', ['message' => 'Fast import Daily Loan gagal: ' . $e->getMessage()]);
            } finally {
                if ($streamLock) {
                    try {
                        $streamLock->release();
                    } catch (\Throwable $e) {
                        Log::warning('Failed to release Daily Loan direct import lock for job ' . $jobId . ': ' . $e->getMessage());
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

    private function countCsvDataRows(string $csvPath): int
    {
        if ($this->isDailyLoanTable()) {
            return (int) ($this->analyzeDailyLoanCsvImportSource($csvPath)['valid_rows'] ?? 0);
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return 0;
        }

        $delimiter = $this->detectCsvDelimiter($csvPath);
        $rows = 0;

        try {
            $headerRead = false;
            $headers = [];
            $lineNumber = 0;

            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                $lineNumber++;
                if (!$headerRead) {
                    $headers = (array) $row;
                    $headerRead = true;
                    continue;
                }

                if (empty(array_filter((array) $row, fn ($val) => trim((string) $val) !== ''))) {
                    continue;
                }

                if ($this->hasDailyLoanFieldCountMismatch($headers, (array) $row, $lineNumber, 'count_csv_data_rows', $delimiter)) {
                    continue;
                }

                if (!$this->isCompleteSimpananMultiPnSourceRow($headers, (array) $row)) {
                    continue;
                }

                if (!$this->isCompleteDailyLoanSourceRow($headers, (array) $row)) {
                    continue;
                }

                $rows++;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    private function estimateCsvImportTotalRows(string $csvPath, int $headerIndex): int
    {
        $dataRows = $this->countCsvDataRows($csvPath);

        return max(0, $dataRows + max(0, $headerIndex + 1));
    }

    private function countDailyLoanValidCsvRows(string $csvPath, ?string $delimiter = null): int
    {
        return (int) ($this->analyzeDailyLoanCsvImportSource($csvPath, $delimiter)['valid_rows'] ?? 0);
    }

    private function analyzeDailyLoanCsvImportSource(string $csvPath, ?string $delimiter = null): array
    {
        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);
        $cacheKey = sha1(implode('|', [
            $csvPath,
            $delimiter,
            (string) (@filemtime($csvPath) ?: 0),
            (string) (@filesize($csvPath) ?: 0),
        ]));

        if (isset($this->dailyLoanCsvAnalysisCache[$cacheKey])) {
            return $this->dailyLoanCsvAnalysisCache[$cacheKey];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return $this->dailyLoanCsvAnalysisCache[$cacheKey] = [
                'delimiter' => $delimiter,
                'expected_columns' => 0,
                'physical_rows' => 0,
                'valid_rows' => 0,
                'needs_normalization' => false,
            ];
        }

        try {
            $header = fgetcsv($handle, 0, $delimiter);
            if ($header === false || empty($header)) {
                return $this->dailyLoanCsvAnalysisCache[$cacheKey] = [
                    'delimiter' => $delimiter,
                    'expected_columns' => 0,
                    'physical_rows' => 0,
                    'valid_rows' => 0,
                    'needs_normalization' => false,
                ];
            }

            $header = $this->canonicalizeDailyLoanSourceHeaders((array) $header);

            if (!empty($header) && isset($header[0]) && is_string($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }

            $normalizedHeaders = array_map(
                fn ($value) => $this->normalizeImportColumnName((string) $value),
                $header
            );
            $expectedColumns = count($normalizedHeaders);
            $physicalRows = 0;
            $validRows = 0;
            $requiresNormalization = false;

            while (($rawRow = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (empty(array_filter((array) $rawRow, static fn ($value): bool => trim((string) $value) !== ''))) {
                    continue;
                }

                $physicalRows++;
                $normalizedRow = $this->normalizeCsvRow((array) $rawRow, $delimiter, $expectedColumns);
                $rawColumnCount = count((array) $rawRow);
                $normalizedColumnCount = count($normalizedRow);

                if (
                    $rawColumnCount !== $expectedColumns
                    || $normalizedColumnCount !== $rawColumnCount
                    || $normalizedColumnCount !== $expectedColumns
                ) {
                    $requiresNormalization = true;
                }

                $normalizedRow = $this->padRow($normalizedRow, $expectedColumns);
                if (count($normalizedRow) > $expectedColumns) {
                    $normalizedRow = array_slice($normalizedRow, 0, $expectedColumns);
                }

                $valuesByHeader = [];
                foreach ($normalizedHeaders as $index => $normalizedHeader) {
                    if ($normalizedHeader === '' || str_starts_with($normalizedHeader, 'col_')) {
                        continue;
                    }

                    $valuesByHeader[$normalizedHeader] = $normalizedRow[$index] ?? null;
                }

                if (!$this->isValidDailyLoanRowValues($valuesByHeader)) {
                    $requiresNormalization = true;
                    continue;
                }

                $validRows++;
            }
        } finally {
            fclose($handle);
        }

        return $this->dailyLoanCsvAnalysisCache[$cacheKey] = [
            'delimiter' => $delimiter,
            'expected_columns' => $expectedColumns ?? 0,
            'physical_rows' => $physicalRows ?? 0,
            'valid_rows' => $validRows ?? 0,
            'needs_normalization' => $requiresNormalization || (($validRows ?? 0) !== ($physicalRows ?? 0)),
        ];
    }

    private function chunkUploadDirectory(string $uploadId): string
    {
        return storage_path(self::CHUNK_UPLOAD_TEMP_DIR . DIRECTORY_SEPARATOR . $uploadId);
    }

    private function ensureChunkUploadDirectory(string $uploadId): string
    {
        $directory = $this->chunkUploadDirectory($uploadId);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        return $directory;
    }

    private function cleanupChunkUploadDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($directory);
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
        } finally {
            try {
                $this->cleanupService()->dispatchImportedJobSync($jobId, source: static::class);
            } catch (\Throwable $e) {
                Log::warning('Failed to sync report snapshots after import: ' . $e->getMessage(), [
                    'job_id' => $jobId,
                ]);
            }
        }
    }

    private function buildImportContext(string $tableName, array $normalizedHeaders, array $activeFilters = [], array $importOptions = []): array
    {
        if (strtolower(trim($tableName)) === 'daily_loan_dinamis') {
            $normalizedHeaders = $this->canonicalizeDailyLoanSourceHeaders($normalizedHeaders);
        }

        $cacheKey = $tableName . '|' . sha1(json_encode([
            'headers' => array_values($normalizedHeaders),
            'filters' => $activeFilters,
            'import_options' => [
                'manual_kanca' => $importOptions['manual_kanca'] ?? null,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (isset($this->importContextCache[$cacheKey])) {
            return $this->importContextCache[$cacheKey];
        }

        $validIndexes = [];
        foreach ($normalizedHeaders as $i => $h) {
            if (!str_starts_with($h, 'COL_')) {
                $validIndexes[] = $i;
            }
        }

        $headerCount = empty($normalizedHeaders) ? 0 : (max(array_keys($normalizedHeaders)) + 1);
        $tableColumns = $this->schemaColumnsForBulkImport($tableName);
        $tableColumnMetaByLower = $this->tableColumnMetadataForBulkImport($tableName);
        $tableColumnsLookup = [];
        $tableColumnsByLower = [];
        foreach ($tableColumns as $columnName) {
            $lowerColumnName = strtolower($columnName);
            $tableColumnsLookup[$lowerColumnName] = true;
            $tableColumnsByLower[$lowerColumnName] = $columnName;
        }

        $uniqueIdCol = null;
        $suffix = '_DLD';

        if ($tableName === 'simpanan_multipn') {
            $simpananUniqueCandidates = ['uniqueid_SMPN', 'uniqueid_SimoPN'];
            foreach ($simpananUniqueCandidates as $candidate) {
                $lower = strtolower($candidate);
                if (isset($tableColumnsLookup[$lower])) {
                    $uniqueIdCol = $tableColumnsByLower[$lower] ?? $candidate;
                    break;
                }
            }

            $suffix = '_SMPN';
        } elseif ($tableName === 'gi405_rec_dh' && isset($tableColumnsLookup['uniqueid_namareport'])) {
            $uniqueIdCol = $tableColumnsByLower['uniqueid_namareport'] ?? 'uniqueid_namareport';
            $suffix = '';
        } elseif (isset($tableColumnsLookup['uniqueid_namareport'])) {
            $uniqueIdCol = $tableColumnsByLower['uniqueid_namareport'] ?? 'uniqueid_namareport';
        }
        $skipColumns = ['id'];
        if ($uniqueIdCol) {
            $skipColumns[] = strtolower($uniqueIdCol);
        }
        $skipColumnsLookup = array_fill_keys($skipColumns, true);
        $dateColumnsLookup = $this->getExcelDateColumnsLookup();
        $decimalColumnsLookup = $this->getExcelDecimalColumnsLookup();

        $filterLookups = [];
        foreach ($activeFilters as $filterIdx => $values) {
            $filterLookups[(int) $filterIdx] = array_fill_keys(
                array_map(fn ($v) => (string) $v, (array) $values),
                true
            );
        }

        $headerRules = [];
        $filterIndexes = [];
        $importIndexes = [];
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

            $dbCandidates = $this->getHeaderDatabaseCandidates($headerName);
            $mappedDbCandidates = [];
            foreach ($dbCandidates as $candidateColumn) {
                $lowerCandidate = strtolower((string) $candidateColumn);
                if (isset($tableColumnsLookup[$lowerCandidate]) && !isset($skipColumnsLookup[$lowerCandidate])) {
                    $mappedDbCandidates[] = $tableColumnsByLower[$lowerCandidate] ?? $candidateColumn;
                }
            }
            $mappedDbCandidates = array_values(array_unique($mappedDbCandidates));

            $filterLookup = $filterLookups[$filterIdx] ?? null;

            if (!empty($filterLookup)) {
                $filterIndexes[] = $originalIndex;
            }

            if (!empty($mappedDbCandidates)) {
                $importIndexes[] = $originalIndex;
            }

            $headerRules[$originalIndex] = [
                'header_name' => $headerName,
                'db_column' => $dbColumn,
                'is_date' => isset($dateColumnsLookup[$normalizedHeader]),
                'is_decimal' => isset($decimalColumnsLookup[$normalizedHeader]),
                'filter_lookup' => $filterLookup,
                'db_candidates' => $mappedDbCandidates,
            ];
        }

        $hasFilters = !empty($filterIndexes);
        $processingIndexes = $hasFilters
            ? array_values(array_unique(array_merge($filterIndexes, $importIndexes)))
            : array_values(array_unique($importIndexes));

        $context = [
            'table_name' => $tableName,
            'valid_indexes' => $validIndexes,
            'processing_indexes' => $processingIndexes,
            'filter_indexes' => array_values(array_unique($filterIndexes)),
            'import_indexes' => array_values(array_unique($importIndexes)),
            'header_count' => $headerCount,
            'table_columns_lookup' => $tableColumnsLookup,
            'table_columns_by_lower' => $tableColumnsByLower,
            'table_column_meta_by_lower' => $tableColumnMetaByLower,
            'unique_id_col' => $uniqueIdCol,
            'suffix' => $suffix,
            'skip_columns_lookup' => $skipColumnsLookup,
            'filter_lookups' => $filterLookups,
            'header_rules' => $headerRules,
            'has_filters' => $hasFilters,
            'unique_id_prefix' => str_replace('.', '', uniqid('imp', true)),
            'row_sequence' => 0,
            'manual_column_values' => $this->resolveManualImportColumnValues($tableName, $tableColumnsLookup, $tableColumnsByLower, $importOptions),
        ];

        if ($tableName === 'gi405_rec_dh') {
            $context['unique_id_prefix'] = 'uuid_405RDH';
        }

        $context = $this->resolveImportStrategy($tableName)->prepareContext($context);

        return $this->importContextCache[$cacheKey] = $context;
    }

    private function resolveManualImportColumnValues(string $tableName, array $tableColumnsLookup, array $tableColumnsByLower, array $importOptions = []): array
    {
        $manualValues = [];

        if ($tableName === 'rka') {
            $manualKanca = trim((string) ($importOptions['manual_kanca'] ?? session('excel_manual_kanca', '')));
            if ($manualKanca !== '' && isset($tableColumnsLookup['kanca'])) {
                $resolvedColumn = $tableColumnsByLower['kanca'] ?? 'kanca';
                $manualValues[$resolvedColumn] = $manualKanca;
            }
        }

        return $manualValues;
    }

    protected function fallbackBulkLoadChunkLines(): int
    {
        return max(2000, (int) config('import.direct_load.fallback_chunk_lines', 20000));
    }

    protected function fallbackInsertBatchSize(): int
    {
        return max(500, (int) config('import.direct_load.fallback_insert_batch_size', self::DB_INSERT_BATCH_SIZE));
    }

    protected function directLoadMaxRows(string $reportKey, int $default = 0): int
    {
        return max(0, (int) config("import.direct_load.{$reportKey}.max_rows", $default));
    }

    protected function directLoadEnabled(string $reportKey): bool
    {
        return (bool) config("import.direct_load.{$reportKey}.enabled", true);
    }

    protected function resolveDirectCsvFastPathEligibility(
        string $reportKey,
        array $params,
        array $normalizedHeaders,
        int $defaultMaxRows = 0
    ): array {
        if (!$this->directLoadEnabled($reportKey)) {
            return ['eligible' => false, 'reason' => 'Fast import dinonaktifkan pada konfigurasi aplikasi. Menggunakan safe path queue.'];
        }

        if (!empty($params['active_filters'] ?? [])) {
            return ['eligible' => false, 'reason' => 'Filtered import menggunakan safe path queue.'];
        }

        if ($normalizedHeaders === []) {
            return ['eligible' => false, 'reason' => 'Header import tidak tersedia. Menggunakan safe path queue.'];
        }

        if (!$this->supportsNativeBulkLoad()) {
            return ['eligible' => false, 'reason' => 'LOCAL INFILE tidak aktif di MySQL/PDO. Menggunakan safe path queue.'];
        }

        $candidatePaths = array_values(array_filter([
            (string) ($params['staged_csv_path'] ?? ''),
            (string) ($params['file_path'] ?? ''),
        ], static fn ($path): bool => is_string($path) && $path !== ''));

        foreach ($candidatePaths as $candidatePath) {
            $absolutePath = $candidatePath;
            if (!file_exists($absolutePath)) {
                $absolutePath = Storage::path($candidatePath);
            }

            if (!file_exists($absolutePath) || !$this->isCsvFile($absolutePath)) {
                continue;
            }

            $totalRows = max(0, (int) ($params['total_rows'] ?? 0));
            $maxRows = $this->directLoadMaxRows($reportKey, $defaultMaxRows);
            if ($maxRows > 0 && $totalRows > $maxRows) {
                return [
                    'eligible' => false,
                    'reason' => "Jumlah baris {$totalRows} melebihi batas fast import {$maxRows}. Menggunakan safe path queue.",
                ];
            }

            return [
                'eligible' => true,
                'relative_path' => file_exists($candidatePath) ? '' : $candidatePath,
                'absolute_path' => $absolutePath,
                'total_rows' => $totalRows,
            ];
        }

        return ['eligible' => false, 'reason' => 'Sumber file CSV belum siap untuk fast import. Menggunakan safe path queue.'];
    }

    protected function cacheFastImportProgress(int $jobId, array $payload): void
    {
        if ($jobId <= 0) {
            return;
        }

        $this->progressService()->cacheProgress($jobId, $payload);
    }

    protected function collectCsvNormalizedValuesForHeaders(string $csvPath, array $candidateHeaders, ?string $delimiter = null): array
    {
        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);
        $handle = @fopen($csvPath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka CSV untuk membaca nilai snapshot.');
        }

        try {
            $headers = $this->readCsvRecord($handle, $delimiter);
            if ($headers === false || empty($headers)) {
                return [];
            }

            $candidateLookup = [];
            foreach ($candidateHeaders as $candidateHeader) {
                $candidateLookup[$this->normalizeImportColumnName((string) $candidateHeader)] = true;
            }

            $valueIndexes = [];
            foreach ($headers as $index => $header) {
                $normalizedHeader = $this->normalizeImportColumnName((string) $header);
                if (isset($candidateLookup[$normalizedHeader])) {
                    $valueIndexes[] = (int) $index;
                }
            }

            if ($valueIndexes === []) {
                return [];
            }

            $values = [];
            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                if (empty(array_filter((array) $row, static fn ($value) => trim((string) $value) !== ''))) {
                    continue;
                }

                foreach ($valueIndexes as $index) {
                    $value = trim((string) ($row[$index] ?? ''));
                    if ($value === '') {
                        continue;
                    }

                    try {
                        $normalized = StrictDateParser::normalize($value);
                    } catch (\Throwable) {
                        $normalized = null;
                    }

                    $values[$normalized ?: $value] = true;
                }
            }

            return array_values(array_keys($values));
        } finally {
            fclose($handle);
        }
    }

    protected function deleteRowsByColumnValues(string $tableName, string $columnName, array $values): int
    {
        $normalizedValues = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $values
        ), static fn (string $value): bool => $value !== ''));

        if ($tableName === '' || $columnName === '' || $normalizedValues === []) {
            return 0;
        }

        return (int) DB::table($tableName)
            ->whereIn($columnName, $normalizedValues)
            ->delete();
    }

    private function acquireMysqlAdvisoryLockOnDb(string $lockName, int $timeoutSeconds = 5): bool
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return true;
        }

        $row = DB::selectOne('SELECT GET_LOCK(?, ?) AS lock_result', [$lockName, $timeoutSeconds]);

        return (int) ($row->lock_result ?? 0) === 1;
    }

    private function releaseMysqlAdvisoryLockOnDb(string $lockName): void
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        try {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS release_result', [$lockName]);
        } catch (\Throwable) {
            // Ignore release failures. The connection will drop the lock anyway.
        }
    }

    private function acquireMysqlAdvisoryLockOnPdo(\PDO $pdo, string $lockName, int $timeoutSeconds = 5): bool
    {
        $statement = $pdo->prepare('SELECT GET_LOCK(?, ?) AS lock_result');
        $statement->execute([$lockName, $timeoutSeconds]);

        return (int) ($statement->fetchColumn() ?: 0) === 1;
    }

    private function releaseMysqlAdvisoryLockOnPdo(\PDO $pdo, string $lockName): void
    {
        try {
            $statement = $pdo->prepare('SELECT RELEASE_LOCK(?) AS release_result');
            $statement->execute([$lockName]);
        } catch (\Throwable) {
            // Ignore release failures. The connection owns the lock lifecycle.
        }
    }

    private function resolveCsvDataRowEstimate(?int $totalRows, int $headerIndex): int
    {
        return max(0, max(0, (int) $totalRows) - ($headerIndex + 1));
    }

    private function supportsNativeBulkLoad(): bool
    {
        return $this->bulkLoadService()->supportsNativeBulkLoad();
    }

    private function buildBulkLoadColumns(string $tableName, array $normalizedHeaders, array $activeFilters = []): array
    {
        $context = $this->buildImportContext($tableName, $normalizedHeaders, $activeFilters);
        $columns = [];

        if (!empty($context['unique_id_col'])) {
            $columns[] = $context['unique_id_col'];
        }

        $columns[] = 'created_at';
        $columns[] = 'updated_at';

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

        foreach ((array) ($context['manual_column_values'] ?? []) as $manualColumn => $manualValue) {
            $manualColumnLower = strtolower((string) $manualColumn);
            if ($manualColumnLower === '' || isset($context['skip_columns_lookup'][$manualColumnLower])) {
                continue;
            }

            if (!isset($context['table_columns_lookup'][$manualColumnLower])) {
                continue;
            }

            $columns[] = $context['table_columns_by_lower'][$manualColumnLower] ?? $manualColumnLower;
        }

        return array_values(array_unique($columns));
    }

    /**
     * @return array<int, string>
     */
    protected function schemaColumnsForBulkImport(string $tableName): array
    {
        return $this->cachedSchemaColumnListing($tableName);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function tableColumnMetadataForBulkImport(string $tableName): array
    {
        return $this->cachedTableColumnMetadata($tableName);
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

    private function loadCsvIntoMysql(
        string $csvPath,
        string $tableName,
        array $columns,
        ?callable $beforeLoad = null
    ): int
    {
        return $this->bulkLoadService()->loadCsvIntoMysql($csvPath, $tableName, $columns, false, $beforeLoad);
    }

    private function isTransientMysqlLoadError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, "error while reading query's response packet")
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'lost connection')
            || str_contains($message, 'error writing communication packets')
            || str_contains($message, 'packets out of order');
    }

    private function countFileLines(string $path): int
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        $lines = 0;
        try {
            while (!feof($handle)) {
                $line = fgets($handle);
                if ($line !== false) {
                    $lines++;
                }
            }
        } finally {
            fclose($handle);
        }

        return $lines;
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

    private function loadCsvIntoMysqlDirect(
        string $csvPath,
        string $tableName,
        array $columns,
        ?callable $beforeLoad = null
    ): int {
        $bulkLoadService = $this->bulkLoadService();

        if (!$bulkLoadService->supportsNativeBulkLoad()) {
            throw new \RuntimeException('LOCAL INFILE tidak aktif di MySQL/PDO. Direct load tidak tersedia.');
        }

        return $bulkLoadService->loadCsvIntoMysql($csvPath, $tableName, $columns, false, $beforeLoad);
    }

    private function applyManualColumnValuesAfterLoad(string $tableName, array $context, int $insertedRows): int
    {
        if ($insertedRows <= 0) {
            return 0;
        }

        $manualValues = (array) ($context['manual_column_values'] ?? []);
        $manualValues = array_filter($manualValues, static fn ($value): bool => trim((string) $value) !== '');
        if ($manualValues === [] || empty($context['unique_id_col'])) {
            return 0;
        }

        $uniqueIdPrefix = trim((string) ($context['unique_id_prefix'] ?? ''));
        if ($uniqueIdPrefix === '') {
            return 0;
        }

        $uniqueIdColumn = (string) $context['unique_id_col'];
        $updates = [];
        foreach ($manualValues as $column => $value) {
            $resolvedColumn = (string) ($context['table_columns_by_lower'][strtolower((string) $column)] ?? $column);
            if ($resolvedColumn === '') {
                continue;
            }

            $updates[$resolvedColumn] = $this->normalizeValueForDatabaseColumn($resolvedColumn, $value, $context);
        }

        if ($updates === []) {
            return 0;
        }

        try {
            return (int) DB::table($tableName)
                ->where($uniqueIdColumn, 'like', $uniqueIdPrefix . '%')
                ->update($updates);
        } catch (\Throwable $e) {
            Log::warning('Failed to apply manual column values after import load: ' . $e->getMessage(), [
                'table' => $tableName,
                'unique_id_column' => $uniqueIdColumn,
                'unique_id_prefix' => $uniqueIdPrefix,
            ]);
            return 0;
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

        try {
            $pdo->beginTransaction();
            $affected = $pdo->exec($sql);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (\Throwable) {
                }
            }

            throw $e;
        } finally {
            $pdo = null;
        }

        if ($affected === false) {
            throw new \RuntimeException('LOAD DATA LOCAL INFILE ke staging table gagal.');
        }

        return (int) $affected;
    }

    private function processCsvFromStagingTableStream(
        callable $send,
        string $csvPath,
        string $tableName,
        array $activeFilters,
        array $normalizedHeaders,
        int $jobId
    ): bool {
        if ($csvPath === '' || !file_exists($csvPath) || empty($normalizedHeaders)) {
            return false;
        }

        $delimiter = $this->detectCsvDelimiter($csvPath);
        $headerCount = max(1, count($normalizedHeaders));
        $estimatedTotalRows = $this->countCsvDataRows($csvPath);
        $bulkLoadColumns = $this->buildBulkLoadColumns($tableName, $normalizedHeaders, $activeFilters);
        if (empty($bulkLoadColumns)) {
            return false;
        }

        $stagingTable = null;
        $outputCsvPath = $this->createBulkLoadTempCsvPath($tableName, $jobId);
        $outputHandle = null;

        try {
            $stagingTable = $this->createCsvStagingTable('tmp_excel_csv_stage', $jobId, $headerCount);
            $this->loadCsvIntoStagingTable($csvPath, $stagingTable, $headerCount, $delimiter, 1);

            $outputHandle = fopen($outputCsvPath, 'w');
            if ($outputHandle === false) {
                return false;
            }

            $context = $this->buildImportContext($tableName, $normalizedHeaders, $activeFilters);
            $rowsDone = 0;
            $lastProgressAt = 0;
            $startTime = microtime(true);
            $lastId = 0;
            $chunkSize = 8000;
            $timestamp = now()->toDateTimeString();
            $requiredIndexes = array_values(array_unique(array_map('intval', $context['valid_indexes'] ?? [])));
            if (empty($requiredIndexes)) {
                $requiredIndexes = range(0, max(0, $headerCount - 1));
            }
            $stagingSelectColumns = array_merge(
                ['id'],
                array_map(static fn (int $idx): string => 'c' . $idx, $requiredIndexes)
            );
            $sqlSafeFilterRules = [];
            foreach ($requiredIndexes as $originalIndex) {
                $rule = $context['header_rules'][$originalIndex] ?? null;
                if (!$rule || empty($rule['filter_lookup'])) {
                    continue;
                }

                if (!empty($rule['is_date']) || !empty($rule['is_decimal'])) {
                    continue;
                }

                $filterValues = array_map(static fn ($v) => (string) $v, array_keys((array) $rule['filter_lookup']));
                $includeBlank = in_array('(Blank)', $filterValues, true);
                $filterValues = array_values(array_filter($filterValues, static fn (string $v): bool => $v !== '(Blank)'));

                $sqlSafeFilterRules[] = [
                    'column' => 'c' . $originalIndex,
                    'values' => $filterValues,
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

                foreach ($chunk as $record) {
                    $lastId = (int) $record->id;
                    $row = [];
                    for ($i = 0; $i < $headerCount; $i++) {
                        $value = $record->{'c' . $i} ?? null;
                        $row[] = is_string($value) ? rtrim($value, "\r") : $value;
                    }

                    if (empty(array_filter((array) $row, fn($v) => trim((string) $v) !== ''))) {
                        continue;
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
                }

                if ($rowsDone - $lastProgressAt >= self::STREAM_PROGRESS_EVERY) {
                    $lastProgressAt = $rowsDone;
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) ($rowsDone / $elapsed);
                    $percent = $estimatedTotalRows > 0
                        ? min(95, 10 + (int) (($rowsDone / $estimatedTotalRows) * 80))
                        : 90;

                    $send('progress', [
                        'percent' => $percent,
                        'message' => 'Memfilter data dari staging table... (' . $speed . ' baris/detik)',
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
                'message' => 'Staging selesai. Memuat data ke MySQL...',
                'rows_done' => $rowsDone,
                'total' => $estimatedTotalRows > 0 ? $estimatedTotalRows : $rowsDone,
                'speed' => 0,
            ]);

            $inserted = $this->loadCsvIntoMysqlChunked(
                $outputCsvPath,
                $tableName,
                $bulkLoadColumns,
                function (int $processedLines, int $totalLines) use ($send, $rowsDone, $estimatedTotalRows): void {
                    $ratio = $totalLines > 0 ? min(1, $processedLines / $totalLines) : 1;
                    $percent = 96 + (int) floor($ratio * 3);
                    $send('progress', [
                        'percent' => min(99, $percent),
                        'message' => 'Memuat data ke MySQL (chunked)...',
                        'rows_done' => $rowsDone,
                        'total' => $estimatedTotalRows > 0 ? $estimatedTotalRows : $rowsDone,
                        'speed' => 0,
                    ]);
                }
                ,
                8000,
                $estimatedTotalRows
            );
            $baseTotal = $estimatedTotalRows > 0 ? $estimatedTotalRows : $rowsDone;
            $failed = max(0, $baseTotal - $inserted);
            $status = $failed > 0
                ? ($inserted > 0 ? 'failed_partial' : 'failed')
                : 'completed';

            if ($jobId > 0) {
                $this->progressService()->updateTotals($jobId, $inserted, $failed, $baseTotal, $status);
            }

            $send('complete', [
                'total_success' => $inserted,
                'total_failed' => $failed,
                'total_rows' => $baseTotal,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('CSV staging-table fast path failed; fallback to legacy path. Error: ' . $e->getMessage());
            return false;
        } finally {
            if (is_resource($outputHandle)) {
                fclose($outputHandle);
            }
            if (file_exists($outputCsvPath)) {
                @unlink($outputCsvPath);
            }
            $this->dropCsvStagingTable($stagingTable);
        }
    }

    private function quoteSqlIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function quoteSqlStringLiteral(string $value): string
    {
        try {
            return DB::connection()->getPdo()->quote($value);
        } catch (\Throwable) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
    }

    private function supportsDirectDailyLoanCsvBulkLoad(): bool
    {
        return $this->supportsNativeBulkLoad();
    }

    private function stageDailyLoanCsvWithPolars(?callable $send, string $csvPath, ?string $delimiter = null): ?array
    {
        $pythonExe = $this->findPython();
        $scriptPath = base_path('scripts/daily_loan_polars_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);

        $tempDirectory = storage_path('app/temp');
        if (!is_dir($tempDirectory)) {
            @mkdir($tempDirectory, 0777, true);
        }

        $outputCsvPath = $tempDirectory . DIRECTORY_SEPARATOR . 'daily_loan_polars_' . Str::uuid()->toString() . '.csv';
        $configFile = storage_path('app/daily_loan_polars_config_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode([
            'file_path' => $csvPath,
            'delimiter' => $delimiter,
            'output_csv_path' => $outputCsvPath,
            'required_headers' => ['PERIODE', 'NOMOR_REKENING1', 'BAKI_DEBET1'],
            'strict_non_date_headers' => ['KODE_KANWIL1'],
        ], JSON_UNESCAPED_UNICODE));

        $cmd = escapeshellarg($pythonExe)
            . ' ' . escapeshellarg($scriptPath)
            . ' --config ' . escapeshellarg($configFile)
            . ' --mode stage';

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
            @unlink($outputCsvPath);
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $donePayload = null;
        $pythonError = null;
        $pythonProducedOutput = false;
        $lastKeepAlive = time();
        $keepAliveEvery = 15;

        $processLine = static function (string $line) use ($send, &$donePayload, &$pythonError, &$lastKeepAlive): void {
            $line = trim($line);
            if ($line === '') {
                return;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                return;
            }

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'progress') {
                if ($send) {
                    $send('progress', $data);
                }
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'done') {
                $donePayload = $data;
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'error') {
                $pythonError = $data['message'] ?? 'Python Daily Loan stage error tidak diketahui';
                $lastKeepAlive = time();
            }
        };

        try {
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

                if ((time() - $lastKeepAlive) >= $keepAliveEvery && $send) {
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
        } finally {
            if (isset($pipes[1]) && is_resource($pipes[1])) {
                fclose($pipes[1]);
            }
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                fclose($pipes[2]);
            }

            if (is_resource($process)) {
                proc_close($process);
            }

            @unlink($configFile);
        }

        if (!$pythonProducedOutput || $pythonError !== null || !$donePayload || !file_exists($outputCsvPath)) {
            @unlink($outputCsvPath);
            return null;
        }

        return [
            'path' => $outputCsvPath,
            'cleanup' => true,
            'normalized' => true,
            'backend' => 'polars',
            'skipped_rows' => array_values(array_map('intval', (array) ($donePayload['skipped_rows'] ?? []))),
            'skipped_count' => (int) ($donePayload['skipped_count'] ?? 0),
            'written_rows' => (int) ($donePayload['written_rows'] ?? 0),
            'total_rows' => (int) ($donePayload['total_rows'] ?? 0),
        ];
    }

    protected function stageSimpananMultiPnCsvWithPolars(?callable $send, string $csvPath, ?string $delimiter = null): ?array
    {
        $pythonExe = $this->findPython();
        $scriptPath = base_path('scripts/simpanan_multipn_polars_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);

        $tempDirectory = storage_path('app/temp');
        if (!is_dir($tempDirectory)) {
            @mkdir($tempDirectory, 0777, true);
        }

        $outputCsvPath = $tempDirectory . DIRECTORY_SEPARATOR . 'simpanan_multipn_polars_' . Str::uuid()->toString() . '.csv';
        $configFile = storage_path('app/simpanan_multipn_polars_config_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode([
            'file_path' => $csvPath,
            'delimiter' => $delimiter,
            'output_csv_path' => $outputCsvPath,
            'required_headers' => ['POSISI', 'CIFNO', 'NO_REKENING', 'JENIS_SIMPANAN', 'SALDO_IDR'],
            'duplicate_key_headers' => ['POSISI', 'NO_REKENING'],
        ], JSON_UNESCAPED_UNICODE));

        $cmd = escapeshellarg($pythonExe)
            . ' ' . escapeshellarg($scriptPath)
            . ' --config ' . escapeshellarg($configFile)
            . ' --mode stage';

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
            @unlink($outputCsvPath);
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $donePayload = null;
        $pythonError = null;
        $pythonProducedOutput = false;
        $lastKeepAlive = time();
        $keepAliveEvery = 15;

        $processLine = static function (string $line) use ($send, &$donePayload, &$pythonError, &$lastKeepAlive): void {
            $line = trim($line);
            if ($line === '') {
                return;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                return;
            }

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'progress') {
                if ($send) {
                    $send('progress', $data);
                }
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'done') {
                $donePayload = $data;
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'error') {
                $pythonError = $data['message'] ?? 'Python Simpanan MultiPN stage error tidak diketahui';
                $lastKeepAlive = time();
            }
        };

        try {
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

                if ((time() - $lastKeepAlive) >= $keepAliveEvery && $send) {
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
        } finally {
            if (isset($pipes[1]) && is_resource($pipes[1])) {
                fclose($pipes[1]);
            }
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                fclose($pipes[2]);
            }

            if (is_resource($process)) {
                proc_close($process);
            }

            @unlink($configFile);
        }

        if (!$pythonProducedOutput || $pythonError !== null || !$donePayload || !file_exists($outputCsvPath)) {
            @unlink($outputCsvPath);
            return null;
        }

        return [
            'path' => $outputCsvPath,
            'cleanup' => true,
            'normalized' => true,
            'backend' => 'polars',
            'skipped_rows' => array_values(array_map('intval', (array) ($donePayload['skipped_rows'] ?? []))),
            'skipped_count' => (int) ($donePayload['skipped_count'] ?? 0),
            'duplicate_count' => (int) ($donePayload['duplicate_count'] ?? 0),
            'written_rows' => (int) ($donePayload['written_rows'] ?? 0),
            'total_rows' => (int) ($donePayload['total_rows'] ?? 0),
        ];
    }

    protected function createNormalizedSimpananMultiPnDirectLoadCsv(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
    {
        $polarsResult = $this->stageSimpananMultiPnCsvWithPolars($send, $csvPath, $delimiter);
        if ($polarsResult !== null) {
            return $polarsResult;
        }

        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);
        $tempDirectory = storage_path('app/temp');
        if (!is_dir($tempDirectory)) {
            @mkdir($tempDirectory, 0777, true);
        }

        $tempPath = $tempDirectory . DIRECTORY_SEPARATOR . 'simpanan_multipn_direct_' . Str::uuid()->toString() . '.csv';
        $inputHandle = @fopen($csvPath, 'rb');
        if ($inputHandle === false) {
            throw new \RuntimeException('Gagal membuka file sumber Simpanan MultiPN untuk normalisasi direct load.');
        }

        $outputHandle = @fopen($tempPath, 'wb');
        if ($outputHandle === false) {
            fclose($inputHandle);
            throw new \RuntimeException('Gagal membuat file sementara Simpanan MultiPN untuk direct load.');
        }

        try {
            $header = fgetcsv($inputHandle, 0, $delimiter);
            if ($header === false || empty($header)) {
                throw new \RuntimeException('Header CSV Simpanan MultiPN tidak ditemukan saat normalisasi direct load.');
            }

            if (!empty($header) && isset($header[0]) && is_string($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }

            $expectedColumns = count($header);
            $lineNumber = 1;
            $skippedRows = [];
            $skippedCount = 0;
            $writtenRows = 0;
            $normalizationChanged = false;
            $normalizedHeaders = array_map(
                fn ($value) => $this->normalizeImportColumnName((string) $value),
                $header
            );

            fputcsv($outputHandle, $header, $delimiter, '"', '\\');

            while (($rawRow = fgetcsv($inputHandle, 0, $delimiter)) !== false) {
                $lineNumber++;
                $row = $this->normalizeCsvRow((array) $rawRow, $delimiter, $expectedColumns);

                if (empty(array_filter((array) $row, fn ($value) => trim((string) $value) !== ''))) {
                    continue;
                }

                if (count($row) !== $expectedColumns) {
                    $skippedRows[] = $lineNumber;
                    $skippedCount++;
                    $normalizationChanged = true;
                    continue;
                }

                $valuesByHeader = [];
                foreach ($normalizedHeaders as $index => $normalizedHeader) {
                    if ($normalizedHeader === '' || str_starts_with($normalizedHeader, 'col_')) {
                        continue;
                    }

                    $valuesByHeader[$normalizedHeader] = $row[$index] ?? null;
                }

                if (!$this->hasRequiredSimpananMultiPnImportData($valuesByHeader)) {
                    $skippedRows[] = $lineNumber;
                    $skippedCount++;
                    continue;
                }

                fputcsv($outputHandle, $row, $delimiter, '"', '\\');
                $writtenRows++;
            }
        } catch (\Throwable $e) {
            fclose($inputHandle);
            fclose($outputHandle);
            @unlink($tempPath);
            throw $e;
        }

        fclose($inputHandle);
        fclose($outputHandle);

        if ($writtenRows === 0) {
            @unlink($tempPath);
            throw new \RuntimeException('Polars tidak menemukan baris data Simpanan MultiPN yang valid.');
        }

        return [
            'path' => $tempPath,
            'cleanup' => true,
            'normalized' => true,
            'backend' => 'php',
            'skipped_rows' => $skippedRows,
            'skipped_count' => $skippedCount,
            'duplicate_count' => 0,
            'written_rows' => $writtenRows,
            'rewritten' => $normalizationChanged || $skippedCount > 0,
            'total_rows' => $skippedCount + $writtenRows,
        ];
    }

    protected function prepareSimpananMultiPnDirectLoadSource(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
    {
        $normalized = $this->createNormalizedSimpananMultiPnDirectLoadCsv($csvPath, $delimiter, $send);

        return [
            'path' => $normalized['path'],
            'cleanup' => (bool) ($normalized['cleanup'] ?? true),
            'normalized' => (bool) ($normalized['normalized'] ?? true),
            'backend' => (string) ($normalized['backend'] ?? 'php'),
            'skipped_rows' => $normalized['skipped_rows'] ?? [],
            'skipped_count' => (int) ($normalized['skipped_count'] ?? 0),
            'duplicate_count' => (int) ($normalized['duplicate_count'] ?? 0),
            'written_rows' => (int) ($normalized['written_rows'] ?? 0),
        ];
    }

    private function stageSsaSimpananCsvWithPolars(?callable $send, string $csvPath, ?string $delimiter = null): ?array
    {
        $pythonExe = $this->findPython();
        $scriptPath = base_path('scripts/ssa_simpanan_polars_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);

        $tempDirectory = storage_path('app/temp');
        if (!is_dir($tempDirectory)) {
            @mkdir($tempDirectory, 0777, true);
        }

        $outputCsvPath = $tempDirectory . DIRECTORY_SEPARATOR . 'ssa_simpanan_polars_' . Str::uuid()->toString() . '.csv';
        $configFile = storage_path('app/ssa_simpanan_polars_config_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode([
            'file_path' => $csvPath,
            'delimiter' => $delimiter,
            'output_csv_path' => $outputCsvPath,
        ], JSON_UNESCAPED_UNICODE));

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
            @unlink($outputCsvPath);
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $donePayload = null;
        $pythonError = null;
        $pythonProducedOutput = false;
        $lastKeepAlive = time();
        $keepAliveEvery = 15;

        $processLine = static function (string $line) use ($send, &$donePayload, &$pythonError, &$lastKeepAlive): void {
            $line = trim($line);
            if ($line === '') {
                return;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                return;
            }

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'progress') {
                if ($send) {
                    $send('progress', $data);
                }
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'done') {
                $donePayload = $data;
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'error') {
                $pythonError = $data['message'] ?? 'Python SSA Simpanan stage error tidak diketahui';
                $lastKeepAlive = time();
            }
        };

        try {
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

                if ((time() - $lastKeepAlive) >= $keepAliveEvery && $send) {
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
        } finally {
            if (isset($pipes[1]) && is_resource($pipes[1])) {
                fclose($pipes[1]);
            }
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                fclose($pipes[2]);
            }

            if (is_resource($process)) {
                proc_close($process);
            }

            @unlink($configFile);
        }

        if (!$pythonProducedOutput || $pythonError !== null || !$donePayload || !file_exists($outputCsvPath)) {
            @unlink($outputCsvPath);
            return null;
        }

        return [
            'path' => $outputCsvPath,
            'cleanup' => true,
            'normalized' => true,
            'backend' => 'polars',
            'skipped_rows' => array_values(array_map('intval', (array) ($donePayload['skipped_rows'] ?? []))),
            'skipped_count' => (int) ($donePayload['skipped_count'] ?? 0),
            'duplicate_count' => (int) ($donePayload['duplicate_count'] ?? 0),
            'written_rows' => (int) ($donePayload['written_rows'] ?? 0),
            'total_rows' => (int) ($donePayload['total_rows'] ?? 0),
        ];
    }

    protected function createNormalizedSsaSimpananDirectLoadCsv(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
    {
        $polarsResult = $this->stageSsaSimpananCsvWithPolars($send, $csvPath, $delimiter);
        if ($polarsResult !== null) {
            return $polarsResult;
        }

        return [
            'path' => $csvPath,
            'cleanup' => false,
            'normalized' => false,
            'backend' => 'csv_stage',
            'skipped_rows' => [],
            'skipped_count' => 0,
            'duplicate_count' => 0,
            'written_rows' => 0,
            'total_rows' => 0,
        ];
    }

    protected function prepareSsaSimpananDirectLoadSource(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
    {
        $normalized = $this->createNormalizedSsaSimpananDirectLoadCsv($csvPath, $delimiter, $send);

        return [
            'path' => $normalized['path'],
            'cleanup' => (bool) ($normalized['cleanup'] ?? false),
            'normalized' => (bool) ($normalized['normalized'] ?? false),
            'backend' => (string) ($normalized['backend'] ?? 'csv_stage'),
            'skipped_rows' => $normalized['skipped_rows'] ?? [],
            'skipped_count' => (int) ($normalized['skipped_count'] ?? 0),
            'duplicate_count' => (int) ($normalized['duplicate_count'] ?? 0),
            'written_rows' => (int) ($normalized['written_rows'] ?? 0),
            'periods' => $normalized['periods'] ?? [],
        ];
    }

    private function stageSsaPinjamanCsvWithPolars(?callable $send, string $csvPath, ?string $delimiter = null): ?array
    {
        $pythonExe = $this->findPython();
        $scriptPath = base_path('scripts/ssa_pinjaman_polars_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);

        $tempDirectory = storage_path('app/temp');
        if (!is_dir($tempDirectory)) {
            @mkdir($tempDirectory, 0777, true);
        }

        $outputCsvPath = $tempDirectory . DIRECTORY_SEPARATOR . 'ssa_pinjaman_polars_' . Str::uuid()->toString() . '.csv';
        $configFile = storage_path('app/ssa_pinjaman_polars_config_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode([
            'file_path' => $csvPath,
            'delimiter' => $delimiter,
            'output_csv_path' => $outputCsvPath,
        ], JSON_UNESCAPED_UNICODE));

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
            @unlink($outputCsvPath);
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $donePayload = null;
        $pythonError = null;
        $pythonProducedOutput = false;
        $lastKeepAlive = time();
        $keepAliveEvery = 15;

        $processLine = static function (string $line) use ($send, &$donePayload, &$pythonError, &$lastKeepAlive): void {
            $line = trim($line);
            if ($line === '') {
                return;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                return;
            }

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'progress') {
                if ($send) {
                    $send('progress', $data);
                }
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'done') {
                $donePayload = $data;
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'error') {
                $pythonError = $data['message'] ?? 'Python SSA Pinjaman stage error tidak diketahui';
                $lastKeepAlive = time();
            }
        };

        try {
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

                if ((time() - $lastKeepAlive) >= $keepAliveEvery && $send) {
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
        } finally {
            if (isset($pipes[1]) && is_resource($pipes[1])) {
                fclose($pipes[1]);
            }
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                fclose($pipes[2]);
            }

            if (is_resource($process)) {
                proc_close($process);
            }

            @unlink($configFile);
        }

        if (!$pythonProducedOutput || $pythonError !== null || !$donePayload || !file_exists($outputCsvPath)) {
            @unlink($outputCsvPath);
            return null;
        }

        return [
            'path' => $outputCsvPath,
            'cleanup' => true,
            'normalized' => true,
            'backend' => 'polars',
            'skipped_rows' => array_values(array_map('intval', (array) ($donePayload['skipped_rows'] ?? []))),
            'skipped_count' => (int) ($donePayload['skipped_count'] ?? 0),
            'duplicate_count' => (int) ($donePayload['duplicate_count'] ?? 0),
            'written_rows' => (int) ($donePayload['written_rows'] ?? 0),
            'total_rows' => (int) ($donePayload['total_rows'] ?? 0),
        ];
    }

    protected function createNormalizedSsaPinjamanDirectLoadCsv(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
    {
        $polarsResult = $this->stageSsaPinjamanCsvWithPolars($send, $csvPath, $delimiter);
        if ($polarsResult !== null) {
            return $polarsResult;
        }

        return [
            'path' => $csvPath,
            'cleanup' => false,
            'normalized' => false,
            'backend' => 'csv_stage',
            'skipped_rows' => [],
            'skipped_count' => 0,
            'duplicate_count' => 0,
            'written_rows' => 0,
            'total_rows' => 0,
        ];
    }

    protected function prepareSsaPinjamanDirectLoadSource(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
    {
        $normalized = $this->createNormalizedSsaPinjamanDirectLoadCsv($csvPath, $delimiter, $send);

        return [
            'path' => $normalized['path'],
            'cleanup' => (bool) ($normalized['cleanup'] ?? false),
            'normalized' => (bool) ($normalized['normalized'] ?? false),
            'backend' => (string) ($normalized['backend'] ?? 'csv_stage'),
            'skipped_rows' => $normalized['skipped_rows'] ?? [],
            'skipped_count' => (int) ($normalized['skipped_count'] ?? 0),
            'duplicate_count' => (int) ($normalized['duplicate_count'] ?? 0),
            'written_rows' => (int) ($normalized['written_rows'] ?? 0),
            'periods' => $normalized['periods'] ?? [],
        ];
    }

    private function stageGi405RecDhCsvWithPolars(?callable $send, string $csvPath, ?string $delimiter = null): ?array
    {
        $pythonExe = $this->findPython();
        $scriptPath = base_path('scripts/gi405_rec_dh_polars_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);

        $tempDirectory = storage_path('app/temp');
        if (!is_dir($tempDirectory)) {
            @mkdir($tempDirectory, 0777, true);
        }

        $outputCsvPath = $tempDirectory . DIRECTORY_SEPARATOR . 'gi405_rec_dh_polars_' . Str::uuid()->toString() . '.csv';
        $configFile = storage_path('app/gi405_rec_dh_polars_config_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode([
            'file_path' => $csvPath,
            'delimiter' => $delimiter,
            'output_csv_path' => $outputCsvPath,
        ], JSON_UNESCAPED_UNICODE));

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
            @unlink($outputCsvPath);
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $donePayload = null;
        $pythonError = null;
        $pythonProducedOutput = false;
        $lastKeepAlive = time();
        $keepAliveEvery = 15;

        $processLine = static function (string $line) use ($send, &$donePayload, &$pythonError, &$lastKeepAlive): void {
            $line = trim($line);
            if ($line === '') {
                return;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                return;
            }

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'progress') {
                if ($send) {
                    $send('progress', $data);
                }
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'done') {
                $donePayload = $data;
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'error') {
                $pythonError = $data['message'] ?? 'Python GI405 - Rec. DH stage error tidak diketahui';
                $lastKeepAlive = time();
            }
        };

        try {
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

                if ((time() - $lastKeepAlive) >= $keepAliveEvery && $send) {
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
        } finally {
            if (isset($pipes[1]) && is_resource($pipes[1])) {
                fclose($pipes[1]);
            }
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                fclose($pipes[2]);
            }

            if (is_resource($process)) {
                proc_close($process);
            }

            @unlink($configFile);
        }

        if (!$pythonProducedOutput || $pythonError !== null || !$donePayload || !file_exists($outputCsvPath)) {
            @unlink($outputCsvPath);
            return null;
        }

        return [
            'path' => $outputCsvPath,
            'cleanup' => true,
            'normalized' => true,
            'backend' => 'polars',
            'skipped_rows' => array_values(array_map('intval', (array) ($donePayload['skipped_rows'] ?? []))),
            'skipped_count' => (int) ($donePayload['skipped_count'] ?? 0),
            'duplicate_count' => (int) ($donePayload['duplicate_count'] ?? 0),
            'written_rows' => (int) ($donePayload['written_rows'] ?? 0),
            'total_rows' => (int) ($donePayload['total_rows'] ?? 0),
            'periods' => array_values((array) ($donePayload['dates'] ?? [])),
        ];
    }

    private function stageLw325PhCsvWithPolars(?callable $send, string $csvPath, ?string $delimiter = null): ?array
    {
        $pythonExe = $this->findPython();
        $scriptPath = base_path('scripts/lw325_ph_polars_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);

        $tempDirectory = storage_path('app/temp');
        if (!is_dir($tempDirectory)) {
            @mkdir($tempDirectory, 0777, true);
        }

        $outputCsvPath = $tempDirectory . DIRECTORY_SEPARATOR . 'lw325_ph_polars_' . Str::uuid()->toString() . '.csv';
        $configFile = storage_path('app/lw325_ph_polars_config_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode([
            'file_path' => $csvPath,
            'delimiter' => $delimiter,
            'output_csv_path' => $outputCsvPath,
        ], JSON_UNESCAPED_UNICODE));

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
            @unlink($outputCsvPath);
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $donePayload = null;
        $pythonError = null;
        $pythonProducedOutput = false;
        $lastKeepAlive = time();
        $keepAliveEvery = 15;

        $processLine = static function (string $line) use ($send, &$donePayload, &$pythonError, &$lastKeepAlive): void {
            $line = trim($line);
            if ($line === '') {
                return;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                return;
            }

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'progress') {
                if ($send) {
                    $send('progress', $data);
                }
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'done') {
                $donePayload = $data;
                $lastKeepAlive = time();
                return;
            }

            if ($type === 'error') {
                $pythonError = $data['message'] ?? 'Python LW325_PH stage error tidak diketahui';
                $lastKeepAlive = time();
            }
        };

        try {
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

                if ((time() - $lastKeepAlive) >= $keepAliveEvery && $send) {
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
        } finally {
            if (isset($pipes[1]) && is_resource($pipes[1])) {
                fclose($pipes[1]);
            }
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                fclose($pipes[2]);
            }

            if (is_resource($process)) {
                proc_close($process);
            }

            @unlink($configFile);
        }

        if (!$pythonProducedOutput || $pythonError !== null || !$donePayload || !file_exists($outputCsvPath)) {
            @unlink($outputCsvPath);
            return null;
        }

        return [
            'path' => $outputCsvPath,
            'cleanup' => true,
            'normalized' => true,
            'backend' => 'polars',
            'skipped_rows' => array_values(array_map('intval', (array) ($donePayload['skipped_rows'] ?? []))),
            'skipped_count' => (int) ($donePayload['skipped_count'] ?? 0),
            'duplicate_count' => (int) ($donePayload['duplicate_count'] ?? 0),
            'written_rows' => (int) ($donePayload['written_rows'] ?? 0),
            'total_rows' => (int) ($donePayload['total_rows'] ?? 0),
            'periods' => array_values((array) ($donePayload['dates'] ?? [])),
        ];
    }

    protected function createNormalizedLw325PhDirectLoadCsv(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
    {
        $polarsResult = $this->stageLw325PhCsvWithPolars($send, $csvPath, $delimiter);
        if ($polarsResult !== null) {
            return $polarsResult;
        }

        return [
            'path' => $csvPath,
            'cleanup' => false,
            'normalized' => false,
            'backend' => 'csv_stage',
            'skipped_rows' => [],
            'skipped_count' => 0,
            'duplicate_count' => 0,
            'written_rows' => 0,
            'total_rows' => 0,
            'periods' => [],
        ];
    }

    protected function prepareLw325PhDirectLoadSource(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
    {
        $normalized = $this->createNormalizedLw325PhDirectLoadCsv($csvPath, $delimiter, $send);

        return [
            'path' => $normalized['path'],
            'cleanup' => (bool) ($normalized['cleanup'] ?? false),
            'normalized' => (bool) ($normalized['normalized'] ?? false),
            'backend' => (string) ($normalized['backend'] ?? 'csv_stage'),
            'skipped_rows' => $normalized['skipped_rows'] ?? [],
            'skipped_count' => (int) ($normalized['skipped_count'] ?? 0),
            'duplicate_count' => (int) ($normalized['duplicate_count'] ?? 0),
            'written_rows' => (int) ($normalized['written_rows'] ?? 0),
            'periods' => $normalized['periods'] ?? [],
        ];
    }


    protected function createNormalizedGi405RecDhDirectLoadCsv(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
    {
        $polarsResult = $this->stageGi405RecDhCsvWithPolars($send, $csvPath, $delimiter);
        if ($polarsResult !== null) {
            return $polarsResult;
        }

        return [
            'path' => $csvPath,
            'cleanup' => false,
            'normalized' => false,
            'backend' => 'csv_stage',
            'skipped_rows' => [],
            'skipped_count' => 0,
            'duplicate_count' => 0,
            'written_rows' => 0,
            'total_rows' => 0,
            'periods' => [],
        ];
    }

    protected function prepareGi405RecDhDirectLoadSource(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
    {
        $normalized = $this->createNormalizedGi405RecDhDirectLoadCsv($csvPath, $delimiter, $send);

        return [
            'path' => $normalized['path'],
            'cleanup' => (bool) ($normalized['cleanup'] ?? false),
            'normalized' => (bool) ($normalized['normalized'] ?? false),
            'backend' => (string) ($normalized['backend'] ?? 'csv_stage'),
            'skipped_rows' => $normalized['skipped_rows'] ?? [],
            'skipped_count' => (int) ($normalized['skipped_count'] ?? 0),
            'duplicate_count' => (int) ($normalized['duplicate_count'] ?? 0),
            'written_rows' => (int) ($normalized['written_rows'] ?? 0),
            'periods' => $normalized['periods'] ?? [],
        ];
    }

    private function createNormalizedDailyLoanDirectLoadCsv(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
    {
        $polarsResult = $this->stageDailyLoanCsvWithPolars($send, $csvPath, $delimiter);
        if ($polarsResult !== null) {
            return $polarsResult;
        }

        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);
        $tempDirectory = storage_path('app/temp');
        if (!is_dir($tempDirectory)) {
            @mkdir($tempDirectory, 0777, true);
        }

        $tempPath = $tempDirectory . DIRECTORY_SEPARATOR . 'daily_loan_direct_' . Str::uuid()->toString() . '.csv';
        $inputHandle = @fopen($csvPath, 'rb');
        if ($inputHandle === false) {
            throw new \RuntimeException('Gagal membuka file sumber Daily Loan untuk normalisasi direct load.');
        }

        $outputHandle = @fopen($tempPath, 'wb');
        if ($outputHandle === false) {
            fclose($inputHandle);
            throw new \RuntimeException('Gagal membuat file sementara Daily Loan untuk direct load.');
        }

        try {
            $header = fgetcsv($inputHandle, 0, $delimiter);
            if ($header === false || empty($header)) {
                throw new \RuntimeException('Header CSV Daily Loan tidak ditemukan saat normalisasi direct load.');
            }

            $header = $this->canonicalizeDailyLoanSourceHeaders((array) $header);

            if (!empty($header) && isset($header[0]) && is_string($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }

            $expectedColumns = count($header);
            $lineNumber = 1;
            $skippedRows = [];
            $skippedCount = 0;
            $writtenRows = 0;
            $normalizedHeaders = array_map(
                fn ($value) => $this->normalizeImportColumnName((string) $value),
                $header
            );
            fputcsv($outputHandle, $header, $delimiter, '"', '\\');

            while (($rawRow = fgetcsv($inputHandle, 0, $delimiter)) !== false) {
                $lineNumber++;
                $row = $this->normalizeCsvRow((array) $rawRow, $delimiter);

                if (empty(array_filter((array) $row, fn ($value) => trim((string) $value) !== ''))) {
                    continue;
                }

                if ($this->hasDailyLoanFieldCountMismatch($header, $row, $lineNumber, 'create_normalized_daily_loan_direct_load_csv', $delimiter)) {
                    $skippedRows[] = $lineNumber;
                    $skippedCount++;
                    continue;
                }

                $row = $this->padRow($row, $expectedColumns);
                if (count($row) > $expectedColumns) {
                    $row = array_slice($row, 0, $expectedColumns);
                }

                $valuesByHeader = [];
                foreach ($normalizedHeaders as $index => $normalizedHeader) {
                    if ($normalizedHeader === '' || str_starts_with($normalizedHeader, 'col_')) {
                        continue;
                    }

                    $valuesByHeader[$normalizedHeader] = $row[$index] ?? null;
                }

                if (!$this->isValidDailyLoanRowValues($valuesByHeader)) {
                    $skippedRows[] = $lineNumber;
                    $skippedCount++;
                    continue;
                }

                fputcsv($outputHandle, $row, $delimiter, '"', '\\');
                $writtenRows++;
            }
        } catch (\Throwable $e) {
            fclose($inputHandle);
            fclose($outputHandle);
            @unlink($tempPath);
            throw $e;
        }

        fclose($inputHandle);
        fclose($outputHandle);

        return [
            'path' => $tempPath,
            'backend' => 'php',
            'skipped_rows' => $skippedRows,
            'skipped_count' => $skippedCount,
            'written_rows' => $writtenRows,
        ];
    }

    private function prepareDailyLoanDirectLoadSource(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
    {
        $normalized = $this->createNormalizedDailyLoanDirectLoadCsv($csvPath, $delimiter, $send);
        $path = (string) ($normalized['path'] ?? '');
        $resolvedDelimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : ($path !== '' ? $this->detectCsvDelimiter($path) : ',');

        if ($path !== '' && file_exists($path)) {
            $rewrittenHeaderPath = $this->rewriteDailyLoanCsvHeadersToCanonical($path, $resolvedDelimiter);
            if ($rewrittenHeaderPath !== null && $rewrittenHeaderPath !== $path) {
                if (!empty($normalized['cleanup']) && file_exists($path)) {
                    @unlink($path);
                }

                $normalized['path'] = $rewrittenHeaderPath;
                $normalized['cleanup'] = true;
            }
        }

        return [
            'path' => $normalized['path'],
            'cleanup' => (bool) ($normalized['cleanup'] ?? true),
            'normalized' => (bool) ($normalized['normalized'] ?? true),
            'backend' => (string) ($normalized['backend'] ?? 'php'),
            'skipped_rows' => $normalized['skipped_rows'] ?? [],
            'skipped_count' => (int) ($normalized['skipped_count'] ?? 0),
            'written_rows' => (int) ($normalized['written_rows'] ?? 0),
        ];
    }

    private function rewriteDailyLoanCsvHeadersToCanonical(string $csvPath, string $delimiter): ?string
    {
        $inputHandle = @fopen($csvPath, 'rb');
        if ($inputHandle === false) {
            return null;
        }

        $header = fgetcsv($inputHandle, 0, $delimiter);
        if ($header === false || empty($header)) {
            fclose($inputHandle);
            return null;
        }

        $canonicalHeader = $this->canonicalizeDailyLoanSourceHeaders((array) $header);
        if (array_values($header) === array_values($canonicalHeader)) {
            fclose($inputHandle);
            return $csvPath;
        }

        $tempDirectory = storage_path('app/temp');
        if (!is_dir($tempDirectory)) {
            @mkdir($tempDirectory, 0777, true);
        }

        $tempPath = $tempDirectory . DIRECTORY_SEPARATOR . 'daily_loan_header_rewrite_' . Str::uuid()->toString() . '.csv';
        $outputHandle = @fopen($tempPath, 'wb');
        if ($outputHandle === false) {
            fclose($inputHandle);
            return null;
        }

        try {
            fputcsv($outputHandle, $canonicalHeader, $delimiter, '"', '\\');

            while (($row = fgetcsv($inputHandle, 0, $delimiter)) !== false) {
                fputcsv($outputHandle, $row, $delimiter, '"', '\\');
            }
        } catch (\Throwable) {
            fclose($inputHandle);
            fclose($outputHandle);
            @unlink($tempPath);
            return null;
        }

        fclose($inputHandle);
        fclose($outputHandle);

        return $tempPath;
    }

    private function buildDirectLoadTextExpression(string $columnExpression): string
    {
        $trimmed = "TRIM(COALESCE({$columnExpression}, ''))";

        return "NULLIF(NULLIF({$trimmed}, ''), '\\\\N')";
    }

    protected function buildDirectLoadDecimalExpression(string $columnExpression): string
    {
        $textExpression = $this->buildDirectLoadTextExpression($columnExpression);
        $compacted = "REPLACE({$textExpression}, ' ', '')";
        $signed = "CASE "
            . "WHEN {$compacted} IS NULL THEN NULL "
            . "WHEN LEFT({$compacted}, 1) = '(' AND RIGHT({$compacted}, 1) = ')' THEN CONCAT('-', SUBSTRING({$compacted}, 2, CHAR_LENGTH({$compacted}) - 2)) "
            . "WHEN RIGHT({$compacted}, 1) = '-' THEN CONCAT('-', LEFT({$compacted}, CHAR_LENGTH({$compacted}) - 1)) "
            . "ELSE {$compacted} END";

        return "CASE "
            . "WHEN {$signed} IS NULL THEN NULL "
            . "WHEN {$signed} REGEXP '^-?[0-9]+(\\\\.[0-9]+)?$' THEN CAST({$signed} AS DECIMAL(24,2)) "
            . "WHEN {$signed} REGEXP '^-?[0-9]+(,[0-9]+)?$' THEN CAST(REPLACE({$signed}, ',', '.') AS DECIMAL(24,2)) "
            . "WHEN {$signed} REGEXP '^-?[0-9]{1,3}(,[0-9]{3})+(\\\\.[0-9]+)?$' THEN CAST(REPLACE({$signed}, ',', '') AS DECIMAL(24,2)) "
            . "WHEN {$signed} REGEXP '^-?[0-9]{1,3}(\\\\.[0-9]{3})+(,[0-9]+)?$' THEN CAST(REPLACE(REPLACE({$signed}, '.', ''), ',', '.') AS DECIMAL(24,2)) "
            . "ELSE NULL END";
    }

    private function buildDirectLoadIntegerExpression(string $columnExpression): string
    {
        $decimalExpression = $this->buildDirectLoadDecimalExpression($columnExpression);

        return "CASE "
            . "WHEN ({$decimalExpression}) IS NULL THEN NULL "
            . "ELSE CAST(ROUND({$decimalExpression}, 0) AS SIGNED) END";
    }

    private function buildDirectLoadDateExpression(string $columnExpression): string
    {
        $textExpression = $this->buildDirectLoadTextExpression($columnExpression);

        return StrictDateParser::buildMySqlCaseExpression($textExpression);
    }

    private function buildDirectLoadSqlExpression(array $rule, string $columnExpression): string
    {
        if (!empty($rule['is_date'])) {
            return $this->buildDirectLoadDateExpression($columnExpression);
        }

        if (!empty($rule['is_decimal'])) {
            return $this->buildDirectLoadDecimalExpression($columnExpression);
        }

        $normalizedHeader = preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim((string) ($rule['header_name'] ?? ''))));
        if (in_array($normalizedHeader, [
            'TGL',
            'TAHUN',
            'JANGKA_WAKTU1',
            'UMUR_TUNGGAKAN',
            'FREQ_PAYMENT',
            'FREQ_INT_PAYMENT',
            'JUMLAH_PN1',
            'JUMLAH_PN_ALL1',
            'RESTRUK_KE1',
        ], true)) {
            return $this->buildDirectLoadIntegerExpression($columnExpression);
        }

        return $this->buildDirectLoadTextExpression($columnExpression);
    }

    private function resolveDailyLoanRequiredSourceIndexes(array $normalizedHeaders): array
    {
        $required = [];

        foreach ($normalizedHeaders as $index => $header) {
            $normalized = $this->normalizeImportColumnName((string) $header);
            if (in_array($normalized, ['periode', 'nomor_rekening1', 'baki_debet1'], true)) {
                $required[$normalized] = (int) $index;
            }
        }

        return $required;
    }

    private function hasMinimumDailyLoanSourceValues(array $row, array $requiredIndexes): bool
    {
        foreach (['periode', 'nomor_rekening1', 'baki_debet1'] as $requiredKey) {
            $index = $requiredIndexes[$requiredKey] ?? null;
            if ($index === null) {
                return false;
            }

            $value = trim((string) ($row[$index] ?? ''));
            if ($value === '') {
                return false;
            }
        }

        return true;
    }

    private function buildDirectDailyLoanCsvLoadPlan(string $absolutePath, array $normalizedHeaders): array
    {
        $delimiter = $this->detectCsvDelimiter($absolutePath);
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV Daily Loan.');
        }

        try {
            $sourceHeaders = $this->readCsvRecord($handle, $delimiter);
        } finally {
            fclose($handle);
        }

        if ($sourceHeaders === false || empty($sourceHeaders)) {
            throw new \RuntimeException('Header CSV Daily Loan tidak ditemukan.');
        }

        $sourceHeaders = $this->canonicalizeDailyLoanSourceHeaders((array) $sourceHeaders);
        $normalizedHeaders = $this->canonicalizeDailyLoanSourceHeaders($normalizedHeaders);

        $context = $this->buildImportContext('daily_loan_dinamis', $normalizedHeaders, []);
        $fieldVariables = [];
        $setClauses = [
            "`created_at` = NOW()",
            "`updated_at` = NOW()",
        ];

        if (!empty($context['unique_id_col'])) {
            $uniquePadLength = max(12, strlen((string) max(1, count($normalizedHeaders))) + 8);
            $setClauses[] = "`{$context['unique_id_col']}` = CONCAT(@daily_loan_unique_prefix, LPAD((@daily_loan_rownum := @daily_loan_rownum + 1), {$uniquePadLength}, '0'), '{$context['suffix']}')";
        }

        foreach ($normalizedHeaders as $index => $header) {
            $variable = '@csv_col_' . $index;
            $fieldVariables[] = $variable;

            $rule = $context['header_rules'][$index] ?? null;
            if (!$rule) {
                continue;
            }

            $dbColumn = '';
            foreach ((array) ($rule['db_candidates'] ?? []) as $candidateColumn) {
                $candidateLower = strtolower((string) $candidateColumn);
                if (!isset($context['skip_columns_lookup'][$candidateLower])) {
                    $dbColumn = (string) $candidateColumn;
                    break;
                }
            }

            if ($dbColumn === '') {
                continue;
            }

            if (
                in_array(strtolower($dbColumn), ['id', 'created_at', 'updated_at'], true)
                || (!empty($context['unique_id_col']) && strcasecmp($dbColumn, (string) $context['unique_id_col']) === 0)
            ) {
                continue;
            }

            $expression = $this->buildDirectLoadSqlExpression($rule, $variable);
            $expression = $this->applySqlColumnConstraints($expression, $dbColumn, $context);
            $setClauses[] = $this->quoteSqlIdentifier($dbColumn) . " = {$expression}";
        }

        if (count($setClauses) <= (!empty($context['unique_id_col']) ? 3 : 2)) {
            throw new \RuntimeException('Tidak ada mapping kolom Daily Loan yang bisa dipakai untuk direct import.');
        }

        return [
            'delimiter' => $delimiter,
            'field_variables' => $fieldVariables,
            'set_clauses' => $setClauses,
            'unique_id_prefix' => (string) ($context['unique_id_prefix'] ?? 'imp'),
        ];
    }

    private function buildDirectGenericCsvLoadPlan(string $tableName, string $absolutePath, array $normalizedHeaders): array
    {
        $delimiter = $this->detectCsvDelimiter($absolutePath);
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Gagal membuka file CSV {$tableName}.");
        }

        try {
            $sourceHeaders = $this->readCsvRecord($handle, $delimiter);
        } finally {
            fclose($handle);
        }

        if ($sourceHeaders === false || empty($sourceHeaders)) {
            throw new \RuntimeException("Header CSV {$tableName} tidak ditemukan.");
        }

        $context = $this->buildImportContext($tableName, $normalizedHeaders, []);
        $fieldVariables = [];
        $setClauses = [
            "`created_at` = NOW()",
            "`updated_at` = NOW()",
        ];

        if (!empty($context['unique_id_col'])) {
            $uniquePadLength = max(12, strlen((string) max(1, count($normalizedHeaders))) + 8);
            $setClauses[] = "`{$context['unique_id_col']}` = CONCAT(@generic_import_unique_prefix, LPAD((@generic_import_rownum := @generic_import_rownum + 1), {$uniquePadLength}, '0'), '{$context['suffix']}')";
        }

        foreach ($normalizedHeaders as $index => $header) {
            $variable = '@csv_col_' . $index;
            $fieldVariables[] = $variable;

            $rule = $context['header_rules'][$index] ?? null;
            if (!$rule) {
                continue;
            }

            $dbColumn = '';
            foreach ((array) ($rule['db_candidates'] ?? []) as $candidateColumn) {
                $candidateLower = strtolower((string) $candidateColumn);
                if (!isset($context['skip_columns_lookup'][$candidateLower])) {
                    $dbColumn = (string) $candidateColumn;
                    break;
                }
            }

            if ($dbColumn === '') {
                continue;
            }

            if (
                in_array(strtolower($dbColumn), ['id', 'created_at', 'updated_at'], true)
                || (!empty($context['unique_id_col']) && strcasecmp($dbColumn, (string) $context['unique_id_col']) === 0)
            ) {
                continue;
            }

            $expression = $this->buildDirectLoadSqlExpression($rule, $variable);
            $expression = $this->applySqlColumnConstraints($expression, $dbColumn, $context);
            $setClauses[] = $this->quoteSqlIdentifier($dbColumn) . " = {$expression}";
        }

        foreach ((array) ($context['manual_column_values'] ?? []) as $manualColumn => $manualValue) {
            $manualColumn = (string) $manualColumn;
            if ($manualColumn === '') {
                continue;
            }

            $manualColumnLower = strtolower($manualColumn);
            if (
                isset($context['skip_columns_lookup'][$manualColumnLower])
                || in_array($manualColumnLower, ['created_at', 'updated_at'], true)
            ) {
                continue;
            }

            $manualValue = $this->normalizeValueForDatabaseColumn($manualColumn, $manualValue, $context);
            $setClauses[] = $this->quoteSqlIdentifier($manualColumn) . ' = ' . $this->quoteSqlStringLiteral($manualValue);
        }

        if (count($setClauses) <= (!empty($context['unique_id_col']) ? 3 : 2)) {
            throw new \RuntimeException("Tidak ada mapping kolom {$tableName} yang bisa dipakai untuk direct import.");
        }

        return [
            'delimiter' => $delimiter,
            'field_variables' => $fieldVariables,
            'set_clauses' => $setClauses,
            'unique_id_prefix' => (string) ($context['unique_id_prefix'] ?? 'imp'),
        ];
    }

    private function executeDirectDailyLoanCsvLoad(string $absolutePath, array $loadPlan): int
    {
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
            \PDO::ATTR_TIMEOUT => 120,
        ]);

        $normalizedPath = str_replace('\\', '/', realpath($absolutePath) ?: $absolutePath);
        $quotedPath = $pdo->quote($normalizedPath);
        $quotedFields = implode(', ', $loadPlan['field_variables']);
        $quotedDelimiter = addslashes($loadPlan['delimiter']);
        $setClause = implode(",\n", $loadPlan['set_clauses']);
        $uniquePrefix = (string) ($loadPlan['unique_id_prefix'] ?? 'imp');
        $originalSqlMode = null;
        $lockAcquired = false;
        $affected = false;

        try {
            $lockAcquired = $this->acquireMysqlAdvisoryLockOnPdo($pdo, self::DAILY_LOAN_IMPORT_LOCK_NAME, 5);
            if (!$lockAcquired) {
                throw new \RuntimeException('Import Daily Loan sedang berjalan. Tunggu proses sebelumnya selesai terlebih dahulu.');
            }

            $pdo->beginTransaction();
            $originalSqlMode = $this->relaxMysqlSqlModeForImport($pdo);
            $pdo->exec('SET @skip_snapshot_invalidation = 1');
            $pdo->exec('SET @daily_loan_rownum = 0');
            $pdo->exec('SET @daily_loan_unique_prefix = ' . $pdo->quote($uniquePrefix . '_'));

            $sql = "LOAD DATA LOCAL INFILE {$quotedPath} INTO TABLE `daily_loan_dinamis` "
                . "CHARACTER SET utf8mb4 "
                . "FIELDS TERMINATED BY '{$quotedDelimiter}' OPTIONALLY ENCLOSED BY '\"' "
                . "LINES TERMINATED BY '\\n' "
                . "IGNORE 1 LINES "
                . "({$quotedFields}) "
                . "SET {$setClause}";

            $affected = $pdo->exec($sql);
            $pdo->commit();
        } finally {
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (\Throwable) {
                    // Ignore rollback failures; the connection is already unusable for this import.
                }
            }

            try {
                $pdo->exec('SET @skip_snapshot_invalidation = NULL');
            } catch (\Throwable) {
                // abaikan reset session variable jika koneksi sudah bermasalah
            }

            try {
                $pdo->exec('SET @daily_loan_rownum = NULL');
                $pdo->exec('SET @daily_loan_unique_prefix = NULL');
            } catch (\Throwable) {
                // abaikan reset session variable jika koneksi sudah bermasalah
            }

            if ($lockAcquired) {
                $this->releaseMysqlAdvisoryLockOnPdo($pdo, self::DAILY_LOAN_IMPORT_LOCK_NAME);
            }

            if ($originalSqlMode !== null) {
                try {
                    $pdo->exec('SET SESSION sql_mode = ' . $pdo->quote($originalSqlMode));
                } catch (\Throwable) {
                    // abaikan reset sql_mode jika koneksi sudah bermasalah
                }
            }

            $pdo = null;
        }

        if ($affected === false) {
            throw new \RuntimeException('LOAD DATA LOCAL INFILE gagal dieksekusi untuk Daily Loan.');
        }

        return (int) $affected;
    }

    private function executeDirectGenericCsvLoad(string $tableName, string $absolutePath, array $loadPlan): int
    {
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
            \PDO::ATTR_TIMEOUT => 120,
        ]);

        $normalizedPath = str_replace('\\', '/', realpath($absolutePath) ?: $absolutePath);
        $quotedPath = $pdo->quote($normalizedPath);
        $quotedFields = implode(', ', $loadPlan['field_variables']);
        $quotedDelimiter = addslashes($loadPlan['delimiter']);
        $setClause = implode(",\n", $loadPlan['set_clauses']);
        $uniquePrefix = (string) ($loadPlan['unique_id_prefix'] ?? 'imp');
        $originalSqlMode = null;
        $lockAcquired = false;
        $affected = false;
        $lockName = 'project_abah:table_write:' . strtolower(trim($tableName));

        try {
            $lockAcquired = $this->acquireMysqlAdvisoryLockOnPdo($pdo, $lockName, 10);
            if (!$lockAcquired) {
                throw new \RuntimeException("Import {$tableName} sedang berjalan. Tunggu proses sebelumnya selesai terlebih dahulu.");
            }

            $pdo->beginTransaction();
            $originalSqlMode = $this->relaxMysqlSqlModeForImport($pdo);
            $pdo->exec('SET @skip_snapshot_invalidation = 1');
            $pdo->exec('SET @generic_import_rownum = 0');
            $pdo->exec('SET @generic_import_unique_prefix = ' . $pdo->quote($uniquePrefix . '_'));

            $sql = "LOAD DATA LOCAL INFILE {$quotedPath} INTO TABLE `{$tableName}` "
                . "CHARACTER SET utf8mb4 "
                . "FIELDS TERMINATED BY '{$quotedDelimiter}' OPTIONALLY ENCLOSED BY '\"' "
                . "LINES TERMINATED BY '\\n' "
                . "IGNORE 1 LINES "
                . "({$quotedFields}) "
                . "SET {$setClause}";

            $affected = $pdo->exec($sql);
            $pdo->commit();
        } finally {
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (\Throwable) {
                }
            }

            try {
                $pdo->exec('SET @skip_snapshot_invalidation = NULL');
                $pdo->exec('SET @generic_import_rownum = NULL');
                $pdo->exec('SET @generic_import_unique_prefix = NULL');
            } catch (\Throwable) {
            }

            if ($lockAcquired) {
                $this->releaseMysqlAdvisoryLockOnPdo($pdo, $lockName);
            }

            if ($originalSqlMode !== null) {
                try {
                    $pdo->exec('SET SESSION sql_mode = ' . $pdo->quote($originalSqlMode));
                } catch (\Throwable) {
                }
            }

            $pdo = null;
        }

        if ($affected === false) {
            throw new \RuntimeException("LOAD DATA LOCAL INFILE gagal dieksekusi untuk {$tableName}.");
        }

        return (int) $affected;
    }

    private function buildDailyLoanBulkImportSqlParts(array $context, string $stagingTable): array
    {
        $insertColumns = [];
        $selectClauses = [];
        $selectedColumnsLookup = [];
        $filterAliases = [];

        if (!empty($context['unique_id_col'])) {
            $insertColumns[] = $context['unique_id_col'];
            $uniquePrefix = addslashes((string) ($context['unique_id_prefix'] ?? 'imp'));
            $selectClauses[] = "CONCAT('{$uniquePrefix}_', CAST(`id` AS CHAR), '{$context['suffix']}') AS " . $this->quoteSqlIdentifier($context['unique_id_col']);
            $selectedColumnsLookup[strtolower($context['unique_id_col'])] = true;
        }

        $insertColumns[] = 'created_at';
        $selectClauses[] = 'NOW() AS `created_at`';
        $selectedColumnsLookup['created_at'] = true;

        $insertColumns[] = 'updated_at';
        $selectClauses[] = 'NOW() AS `updated_at`';
        $selectedColumnsLookup['updated_at'] = true;

        foreach ($context['valid_indexes'] as $originalIndex) {
            $rule = $context['header_rules'][$originalIndex] ?? null;
            if (!$rule) {
                continue;
            }

            $expression = $this->buildDirectLoadSqlExpression($rule, $this->quoteSqlIdentifier('c' . $originalIndex));

            if (!empty($rule['filter_lookup'])) {
                $filterAlias = '__flt_' . $originalIndex;
                $selectClauses[] = "{$expression} AS " . $this->quoteSqlIdentifier($filterAlias);
                $filterAliases[$originalIndex] = $filterAlias;
            }

            $dbColumn = '';
            foreach ((array) ($rule['db_candidates'] ?? []) as $candidateColumn) {
                $candidateLower = strtolower((string) $candidateColumn);
                if (!isset($context['skip_columns_lookup'][$candidateLower])) {
                    $dbColumn = (string) $candidateColumn;
                    break;
                }
            }

            if ($dbColumn === '') {
                continue;
            }

            $dbColumnLower = strtolower($dbColumn);
            if (isset($selectedColumnsLookup[$dbColumnLower])) {
                continue;
            }

            $expression = $this->applySqlColumnConstraints($expression, $dbColumn, $context);
            $insertColumns[] = $dbColumn;
            $selectClauses[] = "{$expression} AS " . $this->quoteSqlIdentifier($dbColumn);
            $selectedColumnsLookup[$dbColumnLower] = true;
        }

        return [
            'insert_columns' => array_values(array_unique($insertColumns)),
            'select_clauses' => $selectClauses,
            'filter_aliases' => $filterAliases,
        ];
    }

    private function buildDailyLoanBulkWhereClauses(array $context, array $filterAliases): array
    {
        $whereClauses = [
            'src.`periode` IS NOT NULL',
            'src.`baki_debet1` IS NOT NULL',
            'src.`nomor_rekening1` IS NOT NULL',
        ];

        foreach ($filterAliases as $originalIndex => $filterAlias) {
            $rule = $context['header_rules'][$originalIndex] ?? null;
            if (!$rule || empty($rule['filter_lookup'])) {
                continue;
            }

            $filterValues = array_map(static fn ($v) => (string) $v, array_keys((array) $rule['filter_lookup']));
            $includeBlank = in_array('(Blank)', $filterValues, true);
            $filterValues = array_values(array_filter($filterValues, static fn (string $v): bool => $v !== '(Blank)'));

            $conditions = [];
            if (!empty($filterValues)) {
                $quotedValues = implode(', ', array_map(fn (string $value): string => $this->quoteSqlStringLiteral($value), $filterValues));
                $conditions[] = 'src.' . $this->quoteSqlIdentifier($filterAlias) . " IN ({$quotedValues})";
            }

            if ($includeBlank) {
                $conditions[] = 'src.' . $this->quoteSqlIdentifier($filterAlias) . ' IS NULL';
            }

            if (!empty($conditions)) {
                $whereClauses[] = '(' . implode(' OR ', $conditions) . ')';
            }
        }

        return $whereClauses;
    }

    private function processDailyLoanBulkCsvStream(
        callable $send,
        string $csvPath,
        string $tableName,
        array $normalizedHeaders,
        array $activeFilters,
        int $jobId,
        int $estimatedTotalRows,
        ?string $delimiter = null
    ): bool {
        if ($csvPath === '' || !file_exists($csvPath) || !$this->isDailyLoanTable($tableName)) {
            return false;
        }

        if (!$this->supportsNativeBulkLoad()) {
            return false;
        }

        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);
        $loadSource = null;
        $sourcePath = $csvPath;

        $context = $this->buildImportContext($tableName, $normalizedHeaders, $activeFilters);
        $headerCount = max(1, count($normalizedHeaders));
        $stagingTable = null;

        try {
            $send('progress', [
                'percent' => 18,
                'message' => empty($activeFilters)
                    ? 'Fast-path Daily Loan aktif. Memuat CSV ke staging table...'
                    : 'Fast-path Daily Loan + filter aktif. Memuat CSV ke staging table...',
                'rows_done' => 0,
                'total' => $estimatedTotalRows,
                'speed' => 0,
            ]);

            $loadSource = $this->prepareDailyLoanDirectLoadSource($csvPath, $delimiter, $send);
            $sourcePath = (string) ($loadSource['path'] ?? $csvPath);

            $stagingTable = $this->createCsvStagingTable('tmp_daily_loan_csv_stage', $jobId, $headerCount);
            $loadedRows = $this->loadCsvIntoStagingTable($sourcePath, $stagingTable, $headerCount, $delimiter, 1);

            $sqlParts = $this->buildDailyLoanBulkImportSqlParts($context, $stagingTable);
            $insertColumns = $sqlParts['insert_columns'];
            $selectClauses = $sqlParts['select_clauses'];
            $filterAliases = $sqlParts['filter_aliases'];

            if (count($insertColumns) <= (!empty($context['unique_id_col']) ? 3 : 2)) {
                throw new \RuntimeException('Mapping kolom Daily Loan untuk fast import tidak valid.');
            }

            $quotedInsertColumns = implode(', ', array_map(fn (string $column): string => $this->quoteSqlIdentifier($column), $insertColumns));
            $outerSelectColumns = implode(', ', array_map(fn (string $column): string => 'src.' . $this->quoteSqlIdentifier($column), $insertColumns));
            $innerSelectSql = implode(",\n", $selectClauses);
            $whereClauses = $this->buildDailyLoanBulkWhereClauses($context, $filterAliases);

            $baseTotal = $loadedRows > 0 ? $loadedRows : $estimatedTotalRows;

            if ($jobId > 0) {
                $job = $this->progressService()->findJob($jobId);
                $this->progressService()->updateJob($jobId, [
                    'total_files' => $baseTotal,
                    'total_success' => (int) ($job->total_success ?? 0),
                    'total_failed' => (int) ($job->total_failed ?? 0),
                ]);
            }

            $send('progress', [
                'percent' => 56,
                'message' => empty($activeFilters)
                    ? 'Staging selesai. Menjalankan INSERT SELECT langsung ke Daily Loan...'
                    : 'Staging selesai. Menjalankan INSERT SELECT terfilter langsung ke Daily Loan...',
                'rows_done' => 0,
                'total' => $baseTotal,
                'speed' => 0,
            ]);

            $sql = "INSERT INTO " . $this->quoteSqlIdentifier($tableName) . " ({$quotedInsertColumns}) "
                . "SELECT {$outerSelectColumns} FROM ("
                . "SELECT {$innerSelectSql} FROM " . $this->quoteSqlIdentifier($stagingTable)
                . ") AS src "
                . 'WHERE ' . implode(' AND ', $whereClauses);
            $eligibleCountSql = "SELECT COUNT(*) AS aggregate_count FROM ("
                . "SELECT {$innerSelectSql} FROM " . $this->quoteSqlIdentifier($stagingTable)
                . ") AS src "
                . 'WHERE ' . implode(' AND ', $whereClauses);

            $sessionSqlMode = null;
            $eligibleRows = null;
            $aggregateRow = DB::selectOne($eligibleCountSql);
            if ($aggregateRow) {
                $eligibleRows = (int) ($aggregateRow->aggregate_count ?? $aggregateRow->AGGREGATE_COUNT ?? 0);
            }

            if ($eligibleRows !== null && $eligibleRows >= 0) {
                $baseTotal = $eligibleRows;

                if ($jobId > 0) {
                    $job = $this->progressService()->findJob($jobId);
                    $this->progressService()->updateJob($jobId, [
                        'total_files' => $baseTotal,
                        'total_success' => (int) ($job->total_success ?? 0),
                        'total_failed' => (int) ($job->total_failed ?? 0),
                    ]);
                }
            }

            if (!$this->acquireMysqlAdvisoryLockOnDb(self::DAILY_LOAN_IMPORT_LOCK_NAME, 5)) {
                throw new \RuntimeException('Import Daily Loan sedang berjalan. Tunggu proses sebelumnya selesai terlebih dahulu.');
            }

            $inserted = 0;
            $sessionSqlMode = null;

            try {
                DB::beginTransaction();

                try {
                    DB::statement('SET @skip_snapshot_invalidation = 1');
                    $sqlModeRow = DB::selectOne('SELECT @@SESSION.sql_mode AS session_sql_mode');
                    $sessionSqlMode = (string) ($sqlModeRow->session_sql_mode ?? '');
                    $relaxedModes = array_values(array_filter(array_map('trim', explode(',', $sessionSqlMode))));
                    $relaxedModes = array_values(array_filter($relaxedModes, static function (string $mode): bool {
                        return !in_array(strtoupper($mode), ['STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES'], true);
                    }));
                    DB::statement('SET SESSION sql_mode = ?', [implode(',', $relaxedModes)]);

                    $inserted = DB::affectingStatement($sql);
                    DB::commit();
                } catch (\Throwable $e) {
                    if (DB::connection()->transactionLevel() > 0) {
                        DB::rollBack();
                    }
                    throw $e;
                } finally {
                    if ($sessionSqlMode !== null) {
                        try {
                            DB::statement('SET SESSION sql_mode = ?', [$sessionSqlMode]);
                        } catch (\Throwable) {
                        }
                    }

                    try {
                        DB::statement('SET @skip_snapshot_invalidation = NULL');
                    } catch (\Throwable) {
                    }
                }
            } finally {
                $this->releaseMysqlAdvisoryLockOnDb(self::DAILY_LOAN_IMPORT_LOCK_NAME);
            }

            $failed = max(0, $baseTotal - $inserted);
            $status = $failed > 0
                ? ($inserted > 0 ? 'failed_partial' : 'failed')
                : 'completed';

            if ($jobId > 0) {
                $this->progressService()->updateTotals($jobId, $inserted, $failed, $baseTotal, $status);
            }

            $send('progress', [
                'percent' => 98,
                'message' => empty($activeFilters)
                    ? 'Fast import Daily Loan selesai diproses.'
                    : 'Fast import Daily Loan terfilter selesai diproses.',
                'rows_done' => $inserted,
                'total' => $baseTotal,
                'speed' => 0,
            ]);

            $send('complete', [
                'total_success' => $inserted,
                'total_failed' => $failed,
                'total_rows' => $baseTotal,
            ]);

            return true;
        } finally {
            $this->dropCsvStagingTable($stagingTable);
            if (!empty($loadSource['cleanup']) && !empty($loadSource['path']) && file_exists((string) $loadSource['path'])) {
                @unlink((string) $loadSource['path']);
            }
        }
    }

    private function processDailyLoanDirectCsvStream(
        callable $send,
        string $csvPath,
        string $tableName,
        array $normalizedHeaders,
        int $jobId,
        int $estimatedTotalRows,
        ?string $delimiter = null,
        bool $emitComplete = true
    ): bool {
        if ($csvPath === '' || !file_exists($csvPath)) {
            return false;
        }

        $isDailyLoanTable = $this->isDailyLoanTable($tableName);
        $isSsaSimpananTable = $this->isSsaSimpananTable($tableName);
        $isSsaPinjamanTable = $this->isSsaPinjamanTable($tableName);
        $isGi405RecDhTable = $this->isGi405RecDhTable($tableName);
        $isLw325PhTable = $this->isLw325PhTable($tableName);
        $isSsaTable = $isSsaSimpananTable || $isSsaPinjamanTable;
        $isDirectPolarsTable = $isSsaTable || $isGi405RecDhTable || $isLw325PhTable;

        if (!$isDailyLoanTable && !$isDirectPolarsTable) {
            return false;
        }

        if (!$this->supportsDirectDailyLoanCsvBulkLoad()) {
            return false;
        }

        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);
        $loadSource = null;

        try {
            $send('progress', [
                'status' => 'processing',
                'phase' => 'preparing_load_plan',
                'percent' => 18,
                'message' => $isDailyLoanTable
                    ? 'Menyiapkan direct LOAD DATA untuk Daily Loan...'
                    : 'Menyiapkan direct LOAD DATA untuk ' . ($isGi405RecDhTable ? 'GI405 - Rec. DH' : ($isSsaPinjamanTable ? 'SSA Pinjaman' : 'SSA Simpanan')) . '...',
                'rows_done' => 0,
                'total' => $estimatedTotalRows,
                'speed' => 0,
            ]);

            $loadSource = $isDailyLoanTable
                ? $this->prepareDailyLoanDirectLoadSource($csvPath, $delimiter, $send)
                : ($isGi405RecDhTable
                    ? $this->prepareGi405RecDhDirectLoadSource($csvPath, $delimiter, $send)
                    : ($isSsaPinjamanTable
                    ? $this->prepareSsaPinjamanDirectLoadSource($csvPath, $delimiter, $send)
                    : ($isLw325PhTable
                        ? $this->prepareLw325PhDirectLoadSource($csvPath, $delimiter, $send)
                        : $this->prepareSsaSimpananDirectLoadSource($csvPath, $delimiter, $send))));

            if (($isSsaTable || $isLw325PhTable) && !empty($loadSource['periods'])) {
                $periodColumn = 'periode';
                if ($isSsaSimpananTable) {
                    $periodColumn = 'Month_Day_Year_of_Posisi';
                } elseif ($isSsaPinjamanTable) {
                    $periodColumn = 'Month_Day_Year_of_Periode';
                }
                
                $periods = (array) $loadSource['periods'];

                if ($send) {
                    $send('progress', [
                        'status' => 'processing',
                        'phase' => 'cleaning_old_data',
                        'percent' => 38,
                        'message' => 'Membersihkan data lama untuk periode: ' . implode(', ', $periods) . '...',
                        'rows_done' => 0,
                        'total' => $estimatedTotalRows,
                        'speed' => 0,
                    ]);
                }

                DB::table($tableName)->whereIn($periodColumn, $periods)->delete();
            }
            $sourcePath = (string) ($loadSource['path'] ?? $csvPath);
            $sourceWasNormalized = !empty($loadSource['normalized']);
            $loadBackend = (string) ($loadSource['backend'] ?? 'php');
            $skippedRows = array_values(array_unique(array_map('intval', (array) ($loadSource['skipped_rows'] ?? []))));
            $skippedCount = (int) ($loadSource['skipped_count'] ?? count($skippedRows));

            $loadPlan = $isDailyLoanTable
                ? $this->buildDirectDailyLoanCsvLoadPlan($sourcePath, $normalizedHeaders)
                : $this->buildDirectGenericCsvLoadPlan($tableName, $sourcePath, $normalizedHeaders);
            $baseTotal = !empty($loadSource['written_rows'])
                ? max(0, (int) $loadSource['written_rows'])
                : max(0, $estimatedTotalRows - $skippedCount);

            if ($jobId > 0) {
                $job = $this->progressService()->findJob($jobId);
                $this->progressService()->updateJob($jobId, [
                    'total_files' => $baseTotal,
                    'total_success' => (int) ($job->total_success ?? 0),
                    'total_failed' => (int) ($job->total_failed ?? 0),
                ], [
                    'status' => 'processing',
                    'phase' => 'preparing_load_plan',
                    'percent' => 32,
                    'message' => $isDailyLoanTable
                        ? 'Load plan Daily Loan siap dijalankan.'
                        : 'Load plan ' . ($isGi405RecDhTable ? 'GI405 - Rec. DH' : ($isSsaPinjamanTable ? 'SSA Pinjaman' : 'SSA Simpanan')) . ' siap dijalankan.',
                    'processed_rows' => 0,
                    'total_rows' => $baseTotal,
                    'total_success' => (int) ($job->total_success ?? 0),
                    'total_failed' => (int) ($job->total_failed ?? 0),
                ]);
            }

            $send('progress', [
                'status' => 'processing',
                'phase' => 'loading',
                'percent' => 56,
                'message' => $isDailyLoanTable
                    ? ($sourceWasNormalized
                    ? (
                        $skippedCount > 0
                            ? 'CSV Daily Loan diproses dengan ' . $loadBackend . '. ' . $skippedCount . ' baris rusak di-skip, lalu direct LOAD DATA dijalankan...'
                            : 'CSV Daily Loan diproses dengan ' . $loadBackend . '. Menjalankan direct LOAD DATA ke tabel final...'
                    )
                    : 'Direct LOAD DATA aktif. Memuat CSV langsung ke Daily Loan...')
                    : ($sourceWasNormalized
                    ? (
                        $skippedCount > 0
                            ? 'CSV ' . ($isGi405RecDhTable ? 'GI405 - Rec. DH' : ($isSsaPinjamanTable ? 'SSA Pinjaman' : 'SSA Simpanan')) . ' diproses dengan ' . $loadBackend . '. ' . $skippedCount . ' baris tidak valid di-skip, lalu direct LOAD DATA dijalankan...'
                            : 'CSV ' . ($isGi405RecDhTable ? 'GI405 - Rec. DH' : ($isSsaPinjamanTable ? 'SSA Pinjaman' : 'SSA Simpanan')) . ' diproses dengan ' . $loadBackend . '. Menjalankan direct LOAD DATA ke tabel final...'
                    )
                    : 'Direct LOAD DATA aktif. Memuat CSV stage langsung ke tabel ' . ($isGi405RecDhTable ? 'GI405 - Rec. DH' : ($isSsaPinjamanTable ? 'SSA Pinjaman' : 'SSA Simpanan')) . '...'),
                'rows_done' => 0,
                'total' => $baseTotal,
                'speed' => 0,
            ]);

            $inserted = $isDailyLoanTable
                ? $this->executeDirectDailyLoanCsvLoad($sourcePath, $loadPlan)
                : $this->executeDirectGenericCsvLoad($tableName, $sourcePath, $loadPlan);

            $failed = max(0, $baseTotal - $inserted);
            $status = $failed > 0
                ? ($inserted > 0 ? 'failed_partial' : 'failed')
                : 'completed';

            if ($jobId > 0) {
                $this->progressService()->updateTotals($jobId, $inserted, $failed, $baseTotal, $status, [
                    'status' => $status,
                    'phase' => 'loading',
                    'percent' => $status === 'completed' ? 98 : 96,
                    'message' => $status === 'completed'
                        ? ($isDailyLoanTable ? 'Direct LOAD DATA Daily Loan selesai diproses.' : 'Direct LOAD DATA ' . ($isGi405RecDhTable ? 'GI405 - Rec. DH' : ($isSsaPinjamanTable ? 'SSA Pinjaman' : 'SSA Simpanan')) . ' selesai diproses.')
                        : ($isDailyLoanTable ? 'Direct LOAD DATA Daily Loan selesai dengan kegagalan parsial.' : 'Direct LOAD DATA ' . ($isGi405RecDhTable ? 'GI405 - Rec. DH' : ($isSsaPinjamanTable ? 'SSA Pinjaman' : 'SSA Simpanan')) . ' selesai dengan kegagalan parsial.'),
                    'processed_rows' => $inserted + $failed,
                    'total_rows' => $baseTotal,
                    'total_success' => $inserted,
                    'total_failed' => $failed,
                ]);
            }

            $send('progress', [
                'status' => 'processing',
                'phase' => 'loading',
                'percent' => 98,
                'message' => $isDailyLoanTable
                    ? 'Direct LOAD DATA Daily Loan selesai diproses.'
                    : 'Direct LOAD DATA ' . ($isGi405RecDhTable ? 'GI405 - Rec. DH' : ($isSsaPinjamanTable ? 'SSA Pinjaman' : 'SSA Simpanan')) . ' selesai diproses.',
                'rows_done' => $inserted,
                'total' => $baseTotal,
                'speed' => 0,
            ]);

            if ($emitComplete) {
                $send('complete', [
                    'total_success' => $inserted,
                    'total_failed' => $failed,
                    'total_rows' => $baseTotal,
                    'skipped_rows' => $skippedRows,
                    'skipped_count' => $skippedCount,
                ]);
            }

            return true;
        } finally {
            if (!empty($loadSource['cleanup']) && !empty($loadSource['path']) && file_exists((string) $loadSource['path'])) {
                @unlink((string) $loadSource['path']);
            }
        }
    }

    private function normalizeCsvRow(array $row, string $delimiter, ?int $expectedColumns = null): array
    {
        if (!$this->isDailyLoanTable()) {
            $this->resetDailyLoanCsvParseMeta($expectedColumns);
        }

        $row = $this->reparseSerializedDailyLoanCsvRow($row, $delimiter, $expectedColumns);

        if (!$this->isDailyLoanTable() && count($row) === 1 && isset($row[0]) && is_string($row[0])) {
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

        foreach ($row as $index => $value) {
            if (!is_string($value)) {
                continue;
            }

            $row[$index] = $this->normalizeQuotedCsvCellValue($value);
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

        if ($this->resolveActiveTableName() === 'jumlah_merchant_qris_detail') {
            return [
                'preview_limit' => 100,
                'unique_scan_limit' => 150,
                'max_unique_values_per_column' => 80,
            ];
        }

        return [
            'preview_limit' => 100,
            'unique_scan_limit' => 180,
            'max_unique_values_per_column' => 100,
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

            if ($this->hasDailyLoanFieldCountMismatch($normalizedHeaders, $row, $lineNumber, 'preview_scan_csv', $delimiter)) {
                continue;
            }

            $row = $this->normalizeCsvRow($row, $delimiter, $headerCount);

            if (!$this->isCompleteDailyLoanSourceRow($normalizedHeaders, $row)) {
                continue;
            }

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
            'total_rows' => null,
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
        $exact = strtolower(trim($header));
        $raw = strtolower(trim($header));
        $raw = preg_replace('/[^a-z0-9]+/', '_', $raw);
        $raw = trim((string) $raw, '_');

        $normalized = $this->normalizeHeaderForDatabase($header);
        $candidates = [];

        if ($exact !== '') {
            $candidates[] = $exact;
        }

        if ($raw !== '') {
            $candidates[] = $raw;
        }

        if ($normalized !== '' && $normalized !== $raw) {
            $candidates[] = $normalized;
        }

        $aliasMap = [
            'textbox20' => 'total_kewajiban',
            'textbox21' => 'os_idr',
            'month_day_year_of_posisi' => 'month_day_year_of_posisi',
            'periode' => 'periode',
            'segmen_dashboard' => 'segmen_dashboard',
            'produk_dashboard' => 'produk_dashboard',
        ];

        if (isset($aliasMap[$raw])) {
            $candidates[] = $aliasMap[$raw];
        }

        return array_values(array_unique($candidates));
    }

    protected function reorderPreviewPayload(array $headers, array $formattedUniqueValues, array $preview, array $dbColumns): array
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

                if ($this->normalizeHeaderForDatabase($header) === $this->normalizeHeaderForDatabase($dbCol)) {
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

    /**
     * @param array{headers: array, formattedUniqueValues: array, preview: array} $payload
     * @return array{headers: array, formattedUniqueValues: array, preview: array, sourceHeaders: array}
     */
    protected function applyManualPreviewColumns(string $tableName, array $payload, ?array $sourceHeaders = null): array
    {
        $headers = array_values((array) ($payload['headers'] ?? []));
        $formattedUniqueValues = (array) ($payload['formattedUniqueValues'] ?? []);
        $preview = array_values((array) ($payload['preview'] ?? []));
        $resolvedSourceHeaders = array_values($sourceHeaders ?? $headers);

        if ($tableName !== 'rka') {
            return [
                'headers' => $headers,
                'formattedUniqueValues' => $formattedUniqueValues,
                'preview' => $preview,
                'sourceHeaders' => $resolvedSourceHeaders,
            ];
        }

        $manualKanca = trim((string) session('excel_manual_kanca', ''));
        if ($manualKanca === '') {
            return [
                'headers' => $headers,
                'formattedUniqueValues' => $formattedUniqueValues,
                'preview' => $preview,
                'sourceHeaders' => $resolvedSourceHeaders,
            ];
        }

        $headerLookup = array_map(
            fn ($header) => strtolower(trim((string) $header)),
            $headers
        );
        $kancaIndex = array_search('kanca', $headerLookup, true);

        if ($kancaIndex === false) {
            $headers[] = 'kanca';
            $formattedUniqueValues[] = [];
        } else {
            $formattedUniqueValues[$kancaIndex] = [];
        }

        foreach ($preview as &$row) {
            $rowData = is_array($row) ? $row : (array) $row;
            $rowData['kanca'] = $manualKanca;
            $row = $rowData;
        }
        unset($row);

        if (!in_array('kanca', array_map(
            fn ($header) => strtolower(trim((string) $header)),
            $resolvedSourceHeaders
        ), true)) {
            $resolvedSourceHeaders[] = 'kanca';
        }

        $reordered = $this->reorderPreviewPayload(
            $headers,
            $formattedUniqueValues,
            $preview,
            $this->schemaColumnsForBulkImport($tableName)
        );

        if (!in_array('kanca', array_map(
            fn ($header) => strtolower(trim((string) $header)),
            $reordered['headers']
        ), true)) {
            return [
                'headers' => $headers,
                'formattedUniqueValues' => $formattedUniqueValues,
                'preview' => $this->rebuildPreviewRowsForHeaders($headers, $preview),
                'sourceHeaders' => $resolvedSourceHeaders,
            ];
        }

        return [
            'headers' => $reordered['headers'],
            'formattedUniqueValues' => $reordered['formattedUniqueValues'],
            'preview' => $reordered['preview'],
            'sourceHeaders' => $reordered['headers'],
        ];
    }

    protected function rebuildPreviewRowsForHeaders(array $headers, array $preview): array
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
        $row = $this->alignImportedRowWithNormalizedHeaders($row, $normalizedHeaders);
        $row = $this->padRow($row, $context['header_count']);
        $mappedExcelData = [];

        $indexesToProcess = $context['processing_indexes'] ?? ($context['valid_indexes'] ?? []);
        foreach ($indexesToProcess as $originalIndex) {
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

            foreach ((array) ($rule['db_candidates'] ?? []) as $candidateColumn) {
                $mappedExcelData[(string) $candidateColumn] = $value;
            }
        }

        $context['row_sequence']++;
        $finalRow = [
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        if (!empty($context['unique_id_col'])) {
            $uniquePrefix = trim((string) ($context['unique_id_prefix'] ?? 'imp'));
            if ($uniquePrefix === '') {
                $uniquePrefix = 'imp';
            }

            $finalRow[$context['unique_id_col']] = $uniquePrefix . '_' . uniqid('', true) . $context['suffix'];
        }

        foreach ($mappedExcelData as $dbCol => $value) {
            if (isset($context['skip_columns_lookup'][$dbCol])) {
                continue;
            }
            if (!isset($context['table_columns_lookup'][$dbCol])) {
                continue;
            }
            $resolvedColumn = $context['table_columns_by_lower'][$dbCol] ?? $dbCol;
            if (($context['table_name'] ?? '') === 'gi405_rec_dh' && strtolower($resolvedColumn) === 'kode') {
                $value = $this->normalizeGi405RecDhKodeValue($value);
            }
            $finalRow[$resolvedColumn] = $this->normalizeValueForDatabaseColumn($resolvedColumn, $value, $context);
        }

        foreach ((array) ($context['manual_column_values'] ?? []) as $manualColumn => $manualValue) {
            $manualColumnLower = strtolower((string) $manualColumn);
            if (isset($context['skip_columns_lookup'][$manualColumnLower])) {
                continue;
            }
            if (!isset($context['table_columns_lookup'][$manualColumnLower])) {
                continue;
            }

            $resolvedColumn = $context['table_columns_by_lower'][$manualColumnLower] ?? $manualColumn;
            $finalRow[$resolvedColumn] = $this->normalizeValueForDatabaseColumn($resolvedColumn, $manualValue, $context);
        }

        $minimumColumns = !empty($context['unique_id_col']) ? 3 : 2;

        if (($context['table_name'] ?? '') === 'simpanan_multipn' && !$this->hasRequiredSimpananMultiPnImportData($finalRow)) {
            return null;
        }

        if (($context['table_name'] ?? '') === 'daily_loan_dinamis' && !$this->hasRequiredDailyLoanImportData($finalRow)) {
            return null;
        }

        if (($context['table_name'] ?? '') === 'gi405_rec_dh' && !$this->hasRequiredGi405RecDhImportData($finalRow)) {
            return null;
        }

        if (($context['table_name'] ?? '') === 'gi405_rec_dh') {
            $this->assertGi405RecDhNumericMapping($row, $normalizedHeaders, $finalRow);
        }

        return count($finalRow) > $minimumColumns ? $finalRow : null;
    }

    private function normalizeExcelValueByRule(array $rule, $value)
    {
        $headerName = (string) ($rule['header_name'] ?? '');
        $normalized = $this->normalizeExcelValue($headerName, $value);

        if (!empty($rule['is_decimal'])) {
            return $this->normalizeDecimalValue($value);
        }

        return $normalized;
    }

    private function validateRkaDuplicateGuardOrResponse(string $tableName)
    {
        try {
            $this->assertDuplicateGuard($tableName);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'text' => $e->getMessage(),
                'duplicate_detected' => true,
                'redirect_url' => route('import.index'),
            ], 422);
        }

        return null;
    }

    private function assertDuplicateGuard(string $tableName): void
    {
        if ($tableName !== 'rka') {
            return;
        }

        $selectedKanca = trim((string) session('excel_manual_kanca', ''));
        if ($selectedKanca === '') {
            return;
        }

        $normalizedKanca = function_exists('mb_strtolower')
            ? mb_strtolower($selectedKanca, 'UTF-8')
            : strtolower($selectedKanca);

        $alreadyExists = DB::table('rka')
            ->whereRaw('LOWER(TRIM(`kanca`)) = ?', [$normalizedKanca])
            ->exists();

        if (!$alreadyExists) {
            return;
        }

        throw new \RuntimeException(
            "Data RKA untuk kanca <b>{$selectedKanca}</b> sudah ada di database.<br><br>"
            . 'Import dibatalkan agar data duplikat tidak masuk ke tabel <b class="text-uppercase">rka</b>.'
        );
    }

    private function flushInsertBuffer(array &$rows, string $tableName, int &$totalInserted, int &$totalFailed, ?callable $afterBatch = null): void
    {
        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, $this->fallbackInsertBatchSize()) as $batch) {
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

        $batch = $this->allocateGapIdsForRows($tableName, $batch);

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

    protected function normalizeDecimalValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        $value = trim($this->normalizeQuotedCsvCellValue($value));
        if ($value === '') {
            return null;
        }

        $isNegative = false;

        if (preg_match('/^\((.*)\)$/', $value, $matches) === 1) {
            $value = trim((string) ($matches[1] ?? ''));
            $isNegative = true;
        }

        if (str_ends_with($value, '-')) {
            $value = rtrim(substr($value, 0, -1));
            $isNegative = true;
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

        if ($isNegative && (float) $value > 0) {
            $value = '-' . ltrim((string) $value, '+');
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
        if ($this->excelDateColumnsLookupCache === null) {
            $this->excelDateColumnsLookupCache = $this->getExcelDateColumnsLookup();
        }

        if ($this->excelDecimalColumnsLookupCache === null) {
            $this->excelDecimalColumnsLookupCache = $this->getExcelDecimalColumnsLookup();
        }

        if ($this->excelIntegerColumnsLookupCache === null) {
            $this->excelIntegerColumnsLookupCache = array_fill_keys([
                'TGL',
                'TAHUN',
                'JANGKA_WAKTU1',
                'UMUR_TUNGGAKAN',
                'FREQ_PAYMENT',
                'FREQ_INT_PAYMENT',
                'JUMLAH_PN1',
                'JUMLAH_PN_ALL1',
                'RESTRUK_KE1',
                'JUMLAH_DEBITUR_AKTIF',
                'JUMLAH_REKENING_AKTIF',
            ], true);
        }

        $header = strtoupper(trim($headerName));
        $normalizedHeader = preg_replace('/[^A-Z0-9]+/', '_', $header);
        $value = ($value === null) ? '' : trim($this->normalizeQuotedCsvCellValue($value));

        if ($value === '') return null;

        if (isset($this->excelDateColumnsLookupCache[$normalizedHeader])) {
            try {
                if (preg_match('/^\d{8}$/', $value)) {
                    foreach (['Ymd', 'dmY', 'mdY'] as $format) {
                        try {
                            $date = Carbon::createFromFormat($format, $value);
                            if ($date !== false) {
                                $year = (int) $date->format('Y');
                                if ($year >= 2000 && $year <= 2100) {
                                    return $date->format('Y-m-d');
                                }
                            }
                        } catch (\Throwable) {
                            // lanjut cek format berikutnya
                        }
                    }
                }

                return StrictDateParser::normalize($value);
            } catch (\Throwable $e) {
                return null;
            }
        }

        if (isset($this->excelDecimalColumnsLookupCache[$normalizedHeader])) {
            return $this->normalizeDecimalValue($value);
        }

        if (isset($this->excelIntegerColumnsLookupCache[$normalizedHeader])) {
            return $this->normalizeIntegerValue($value);
        }

        if (is_numeric($value)) {
            $formatted = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
            return $formatted === '' ? '0' : $formatted;
        }

        return $value;
    }

    private function getExcelDateColumnsLookup(): array
    {
        $dateColumns = [
            'PERIODE',
            'POSISI',
            'MONTH_DAY_YEAR_OF_POSISI',
            'MONTH_DAY_YEAR_OF_PERIODE',
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

        return array_fill_keys($dateColumns, true);
    }

    private function getExcelDecimalColumnsLookup(): array
    {
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
            'TEXTBOX20',
            'TEXTBOX21',
            'OS_SEBELUM_KLAIM',
            'OS_PENUH_BERJALAN',
            'BILPRN',
            'BILINT',
            'BILLC',
            'SALDO',
            'SALDO_IDR',
            'PENDAPATAN_KOREKSI_PPAP_DR_ANGSURAN_PH',
            'RECOVERY_NON_KLAIM',
        ];

        return array_fill_keys($decimalColumns, true);
    }

    private function padRow(array $row, int $targetCount): array
    {
        $normalized = [];
        for ($i = 0; $i < $targetCount; $i++) {
            $normalized[$i] = $row[$i] ?? null;
        }
        return $normalized;
    }

    public function uploadExcel(Request $request, array $allowedExtensions = ['xlsx', 'xls', 'csv', 'txt'])
    {
        $request->validate(['file' => 'required|file|mimes:' . implode(',', $allowedExtensions)]);
        $file = $request->file('file');

        $reportId = (int) $request->input('id_report');
        $tableName = strtolower(trim((string) DB::table('nama_report')->where('id_report', $reportId)->value('table_name')));
        $manualKanca = trim((string) $request->input('kanca_manual', ''));

        if ($tableName === 'rka' && $manualKanca === '') {
            $message = 'Kanca wajib dipilih untuk import RKA.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

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
            'excel_manual_kanca' => $tableName === 'rka' ? $manualKanca : null,
        ]);

        $activeIdReport = (int) $request->id_report;
        $previewRedirect = $activeIdReport === self::DAILY_LOAN_REPORT_ID
            ? route('import.dailyloan.preview', ['ck' => $cacheKey])
            : ($activeIdReport === self::SIMPANAN_MULTIPN_REPORT_ID
                ? route('import.simpanan.preview', ['ck' => $cacheKey])
                : route('import.excel.preview', ['ck' => $cacheKey]));
        $prepareRedirect = $activeIdReport === self::DAILY_LOAN_REPORT_ID
            ? route('import.dailyloan.prepare-preview')
            : ($activeIdReport === self::SIMPANAN_MULTIPN_REPORT_ID
                ? route('import.simpanan.prepare-preview')
                : route('import.excel.prepare-preview'));

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status'    => 'success',
                'cache_key' => $cacheKey,
                'redirect'  => $prepareRedirect,
                'preview_redirect' => $previewRedirect,
            ]);
        }

        return redirect($previewRedirect);
    }

    public function preparePreviewStream(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $sessionPath = session('excel_path');
        $cacheKey = session('excel_preview_key');
        $activeIdReport = (int) session('active_id_report');
        request()->session()->save();

        return response()->stream(function () use ($sessionPath, $cacheKey, $activeIdReport) {
            $send = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                if (!$sessionPath) {
                    $send('error_msg', ['message' => 'Sesi upload tidak ditemukan. Silakan upload ulang.']);
                    return;
                }

                $path = Storage::path(urldecode($sessionPath));
                if (!file_exists($path)) {
                    $this->clearDailyLoanImportSessionState();
                    $send('error_msg', ['message' => 'File tidak ditemukan di server. Silakan upload ulang.']);
                    return;
                }

                $useCacheKey = $cacheKey ?: ('excel_preview_' . md5(urldecode($sessionPath) . '|' . microtime(true)));
                $redirect = $activeIdReport === self::DAILY_LOAN_REPORT_ID
                    ? route('import.dailyloan.preview', ['ck' => $useCacheKey])
                    : ($activeIdReport === self::SIMPANAN_MULTIPN_REPORT_ID
                        ? route('import.simpanan.preview', ['ck' => $useCacheKey])
                        : route('import.excel.preview', ['ck' => $useCacheKey]));

                $send('progress', ['percent' => 20, 'message' => 'File ditemukan. Menyiapkan preview...', 'step' => 1]);
                $this->primeExcelPreviewCache($relativePath = urldecode($sessionPath), $path, $useCacheKey, $send);
                $send('progress', ['percent' => 85, 'message' => 'Mengalihkan ke halaman preview...', 'step' => 2]);
                $send('ready', ['redirect' => $redirect]);
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

    protected function primeExcelPreviewCache(string $relativePath, string $path, string $cacheKey, ?callable $send = null): void
    {
        if ($cacheKey === '') {
            return;
        }

        if (Cache::get($cacheKey)) {
            return;
        }

        $tableName = $this->resolveActiveTableName();

        if ($this->isCsvFile($path)) {
            $csvPayload = $this->prepareCsvPreviewPayload($path);
            $reorderedPayload = $this->reorderPreviewPayload(
                $csvPayload['headers'],
                $csvPayload['formattedUniqueValues'],
                $csvPayload['preview'],
                $this->cachedSchemaColumnListing($tableName)
            );
            $reorderedPayload = $this->applyManualPreviewColumns($tableName, $reorderedPayload, array_values($csvPayload['headers']));

            Cache::put($cacheKey, [
                'headers' => $reorderedPayload['headers'],
                'preview' => $reorderedPayload['preview'],
                'formattedUniqueValues' => $reorderedPayload['formattedUniqueValues'],
                'displayFilterMap' => $reorderedPayload['display_filter_map'] ?? [],
                'path' => $relativePath,
                'stagedCsvPath' => $path,
                'headerIndex' => (int) ($csvPayload['header_index'] ?? 0),
                'normalizedHeaders' => $reorderedPayload['headers'],
                'sourceHeaders' => $reorderedPayload['sourceHeaders'],
                'total_rows' => isset($csvPayload['total_rows']) ? (int) $csvPayload['total_rows'] : null,
                'delimiter' => isset($csvPayload['delimiter']) ? (string) $csvPayload['delimiter'] : null,
            ], now()->addHour());

            return;
        }

        $send && $send('progress', ['percent' => 48, 'message' => 'Membaca header dan sampel baris Excel...', 'step' => 1]);

        $nativePreview = $this->excelStagingService()->extractPreviewViaNativeXlsx($path, 100);
        if ($nativePreview === null) {
            return;
        }

        $headers = [];
        foreach ((array) ($nativePreview['headers'] ?? []) as $index => $headerLabel) {
            $headers[$index] = trim((string) $headerLabel) !== '' ? trim((string) $headerLabel) : ('COL_' . $index);
        }

        $previewRows = [];
        foreach ((array) ($nativePreview['preview_rows'] ?? []) as $row) {
            $mapped = [];
            foreach ($headers as $headerLabel) {
                $mapped[$headerLabel] = $this->normalizeExcelValue($headerLabel, $row[$headerLabel] ?? null);
            }
            if ($this->hasMeaningfulImportData($mapped)) {
                $previewRows[] = $mapped;
            }
        }

        $formattedUniqueValues = [];
        foreach ($headers as $headerLabel) {
            $distinct = [];
            foreach ($previewRows as $row) {
                $raw = $row[$headerLabel] ?? null;
                $key = ($raw === null || trim((string) $raw) === '') ? '(Blank)' : (string) $raw;
                $distinct[$key] = true;
            }
            $formattedUniqueValues[$headerLabel] = array_keys($distinct);
        }

        $reorderedPayload = $this->reorderPreviewPayload(
            array_values($headers),
            $formattedUniqueValues,
            $previewRows,
            $this->cachedSchemaColumnListing($tableName)
        );
        $reorderedPayload = $this->applyManualPreviewColumns($tableName, $reorderedPayload, array_values($headers));

        Cache::put($cacheKey, [
            'headers' => $reorderedPayload['headers'],
            'preview' => $reorderedPayload['preview'],
            'formattedUniqueValues' => $reorderedPayload['formattedUniqueValues'],
            'displayFilterMap' => $reorderedPayload['displayFilterMap'] ?? [],
            'path' => $relativePath,
            'stagedCsvPath' => null,
            'headerIndex' => (int) ($nativePreview['header_index'] ?? 0),
            'normalizedHeaders' => $reorderedPayload['headers'],
            'sourceHeaders' => $reorderedPayload['sourceHeaders'],
            'total_rows' => isset($nativePreview['total_rows']) ? (int) $nativePreview['total_rows'] : null,
            'delimiter' => null,
        ], now()->addHour());

        $send && $send('progress', ['percent' => 72, 'message' => 'Preview cepat siap. Menyusun filter kolom...', 'step' => 1]);
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
                    'source_headers' => (array) ($cached['sourceHeaders'] ?? ($cached['normalizedHeaders'] ?? [])),
                    'total_rows' => isset($cached['total_rows']) ? (int) $cached['total_rows'] : null,
                    'delimiter' => isset($cached['delimiter']) ? (string) $cached['delimiter'] : null,
                ];
                $previewStateKey = (string) $ck;
                $this->excelImportJobService()->putPreviewState($previewStateKey, [
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
                        'pageTitle' => $this->resolvePreviewPageTitle(),
                        'previewBannerTitle' => $this->resolvePreviewBannerTitle(),
                    ]);
                }

                if (!$this->isDailyLoanTable()) {
                    $cached['initRoute'] = $initRoute;
                    $cached['streamRoute'] = $streamRoute;
                    $cached['previewStateKey'] = $previewStateKey;
                    $cached['pageTitle'] = $this->resolvePreviewPageTitle();
                    $cached['previewBannerTitle'] = $this->resolvePreviewBannerTitle();
                    return view('import.preview_excel', $cached);
                }
            }
        }

        $sessionPath = session('excel_path', $request->path);
        if (!$sessionPath) return redirect()->route('import.index')->with('sweet_warning', ['title' => 'Sesi Berakhir', 'text' => 'Silakan upload ulang.']);

        $relativePath = urldecode($sessionPath);
        $path = Storage::path($relativePath);
        if (!file_exists($path)) {
            if ((int) session('active_id_report') === self::DAILY_LOAN_REPORT_ID) {
                $this->clearDailyLoanImportSessionState();
            }

            return redirect()->route('import.index')->with('sweet_warning', ['title' => 'File Tidak Ditemukan', 'text' => 'File mungkin sudah terhapus.']);
        }

        if ($this->isCsvFile($path)) {
            $csvPayload = $this->prepareCsvPreviewPayload($path);

            $tableName = $this->resolveActiveTableName();

            $reorderedPayload = $this->reorderPreviewPayload(
                $csvPayload['headers'],
                $csvPayload['formattedUniqueValues'],
                $csvPayload['preview'],
                $this->cachedSchemaColumnListing($tableName)
            );
            $reorderedPayload = $this->applyManualPreviewColumns($tableName, $reorderedPayload, array_values($csvPayload['headers']));

            $previewStateKey = 'excel_preview_' . md5($relativePath . '|csv_direct|' . microtime(true));
            $previewMeta = [
                'path' => $relativePath,
                'staged_csv_path' => $path,
                'header_index' => isset($csvPayload['header_index']) ? (int) $csvPayload['header_index'] : 0,
                'normalized_headers' => $reorderedPayload['headers'],
                'source_headers' => $reorderedPayload['sourceHeaders'],
                'total_rows' => isset($csvPayload['total_rows']) ? (int) $csvPayload['total_rows'] : null,
                'delimiter' => isset($csvPayload['delimiter']) ? (string) $csvPayload['delimiter'] : null,
            ];

            $this->excelImportJobService()->putPreviewState($previewStateKey, [
                'displayFilterMap' => $reorderedPayload['display_filter_map'] ?? [],
                'previewMeta' => $previewMeta,
            ]);

            session([
                'excel_display_filter_map' => $reorderedPayload['display_filter_map'] ?? [],
                'excel_preview_meta' => $previewMeta,
            ]);

            return view('import.preview_excel', [
                'headers' => $reorderedPayload['headers'],
                'preview' => $reorderedPayload['preview'],
                'formattedUniqueValues' => $reorderedPayload['formattedUniqueValues'],
                'path' => $relativePath,
                'initRoute' => $initRoute,
                'streamRoute' => $streamRoute,
                'previewStateKey' => $previewStateKey,
                'pageTitle' => $this->resolvePreviewPageTitle(),
                'previewBannerTitle' => $this->resolvePreviewBannerTitle(),
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

        $headerRow = (array) ($sheet[$headerIndex] ?? []);
        $headers = [];
        foreach ($headerRow as $index => $headerValue) {
            $label = trim((string) $headerValue);
            $headers[$index] = $label !== '' ? $label : ('COL_' . $index);
        }

        $previewRows = [];
        $maxPreviewRows = 100;
        foreach ($sheet as $rowIndex => $rowValues) {
            if ($rowIndex <= $headerIndex) {
                continue;
            }

            $rowValues = (array) $rowValues;
            if (empty(array_filter($rowValues, fn ($value) => trim((string) $value) !== ''))) {
                continue;
            }

            $mapped = [];
            foreach ($headers as $index => $headerLabel) {
                $mapped[$headerLabel] = $rowValues[$index] ?? null;
            }
            $previewRows[] = $mapped;

            if (count($previewRows) >= $maxPreviewRows) {
                break;
            }
        }

        $formattedUniqueValues = [];
        foreach ($headers as $headerLabel) {
            $distinct = [];
            foreach ($previewRows as $row) {
                $raw = $row[$headerLabel] ?? null;
                $key = ($raw === null || trim((string) $raw) === '') ? '(Blank)' : (string) $raw;
                $distinct[$key] = true;
            }
            $formattedUniqueValues[$headerLabel] = array_keys($distinct);
        }

        $reorderedPayload = $this->reorderPreviewPayload(
            array_values($headers),
            $formattedUniqueValues,
            $previewRows,
            $this->cachedSchemaColumnListing($tableName)
        );
        $reorderedPayload = $this->applyManualPreviewColumns($tableName, $reorderedPayload, array_values($headers));

        $previewStateKey = 'excel_preview_' . md5($relativePath . '|fallback|' . microtime(true));
        $this->excelImportJobService()->putPreviewState($previewStateKey, [
            'displayFilterMap' => $reorderedPayload['displayFilterMap'] ?? [],
            'previewMeta' => [
                'path' => $relativePath,
                'staged_csv_path' => null,
                'header_index' => $headerIndex,
                'normalized_headers' => $reorderedPayload['headers'],
                'source_headers' => $reorderedPayload['sourceHeaders'],
                'total_rows' => $csvPayload['total_rows'] ?? null,
                'delimiter' => $csvPayload['delimiter'] ?? null,
            ],
        ]);

        $payload = [
            'headers' => $reorderedPayload['headers'],
            'preview' => $reorderedPayload['preview'],
            'formattedUniqueValues' => $reorderedPayload['formattedUniqueValues'],
        ];

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
                'pageTitle' => $this->resolvePreviewPageTitle(),
                'previewBannerTitle' => $this->resolvePreviewBannerTitle(),
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
            'pageTitle' => $this->resolvePreviewPageTitle(),
            'previewBannerTitle' => $this->resolvePreviewBannerTitle(),
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

        $result = $this->excelStagingService()->detectExcelHeaderViaPython($path, null, 'excel_init_');
        if ($result === null) {
            return null;
        }

        return $result;
    }

    private function validateImportSchemaOrResponse(string $tableName)
    {
        $strategy = $this->resolveImportStrategy($tableName);
        $validation = $strategy->validateSchema($this->cachedSchemaColumnListing($tableName));

        if (($validation['ok'] ?? true) === true) {
            return null;
        }

        return response()->json([
            'status' => 'error',
            'text' => (string) ($validation['message'] ?? 'Schema import tidak valid.'),
        ], 422);
    }

    public function initExcelImport(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $sessionPath = session('excel_path', $request->path);
        if (!$sessionPath) return response()->json(['status' => 'error', 'text' => 'Sesi berakhir.']);

        $relativePath = urldecode($sessionPath);
        $path = Storage::path($relativePath);
        if (!file_exists($path)) return response()->json(['status' => 'error', 'text' => 'File tidak ditemukan.']);

        $idReport = session('active_id_report');
        $tableName = $this->resolveActiveTableName();

        if ($tableName === 'rka' && trim((string) session('excel_manual_kanca', '')) === '') {
            return response()->json(['status' => 'error', 'text' => 'Kanca wajib dipilih untuk import RKA.'], 422);
        }

        $duplicateGuardResponse = $this->validateRkaDuplicateGuardOrResponse($tableName);
        if ($duplicateGuardResponse !== null) {
            return $duplicateGuardResponse;
        }

        $schemaValidationResponse = $this->validateImportSchemaOrResponse($tableName);
        if ($schemaValidationResponse !== null) {
            return $schemaValidationResponse;
        }

        $previewState = $this->excelImportJobService()->getPreviewState($request->input('preview_state_key'));
        $previewMeta = !empty($previewState['previewMeta'])
            ? (array) $previewState['previewMeta']
            : session('excel_preview_meta', []);
        $previewPath = urldecode((string) ($previewMeta['path'] ?? ''));
        $stagedCsvPath = (string) ($previewMeta['staged_csv_path'] ?? '');
        $previewHeaders = (array) ($previewMeta['normalized_headers'] ?? []);
        $sourceHeaders = (array) ($previewMeta['source_headers'] ?? $previewHeaders);
        $previewTotalRows = isset($previewMeta['total_rows']) ? (int) $previewMeta['total_rows'] : null;
        $previewDelimiter = isset($previewMeta['delimiter']) ? (string) $previewMeta['delimiter'] : null;

        if ($previewPath === $relativePath && !empty($previewHeaders) && array_key_exists('header_index', $previewMeta)) {
            $headerIndex = (int) $previewMeta['header_index'];
            $sourceHeaders = $this->resolveSourceHeadersForImport($path, $headerIndex, $sourceHeaders);
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

            if (($this->isSsaSimpananTable($tableName) || $this->isSsaPinjamanTable($tableName)) && !$this->isCsvFile($path)) {
                if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
                    @unlink($stagedCsvPath);
                }

                $stageResult = $this->stageExcelToCsv(
                    static function (string $event, array $data): void {
                        // preview-init phase does not stream progress
                    },
                    $path,
                    $headerIndex,
                    $sourceHeaders,
                    $tableName
                );

                if (!empty($stageResult['staged_csv_path']) && file_exists((string) $stageResult['staged_csv_path'])) {
                    $stagedCsvPath = (string) $stageResult['staged_csv_path'];
                    $previewTotalRows = max(1, ((int) ($stageResult['total_rows'] ?? 0)) + 1);
                    $previewDelimiter = ',';
                }
            }

            session([
                'excel_headers'        => $sourceHeaders,
                'excel_preview_meta'   => [
                    'path' => $relativePath,
                    'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                    'header_index' => $headerIndex,
                    'normalized_headers' => $previewHeaders,
                    'source_headers' => $sourceHeaders,
                    'total_rows' => $previewTotalRows,
                    'delimiter' => $previewDelimiter,
                ],
                'excel_import_params'  => [
                    'header_index'   => $headerIndex,
                    'table_name'     => $tableName,
                    'file_path'      => $relativePath,
                    'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                    'active_filters' => $normalizedActiveFilters,
                    'total_rows'     => $previewTotalRows,
                    'delimiter'      => $previewDelimiter,
                    'manual_kanca'   => $tableName === 'rka' ? trim((string) session('excel_manual_kanca', '')) : null,
                ],
            ]);

            $jobId = $this->excelImportJobService()->createImportJobRecord((int) $idReport, $path, 0, [
                'controller' => static::class,
                'mode' => 'preview_init',
                'table_name' => $tableName,
                'header_index' => $headerIndex,
                'selected_columns' => $normalizedActiveFilters,
                'file_path' => $relativePath,
            ]);

            session([
                'excel_import_params' => array_merge(session('excel_import_params', []), [
                    'job_id' => $jobId,
                ]),
            ]);

            $this->excelImportJobService()->putImportJobState($jobId, [
                'params' => [
                    'header_index'   => $headerIndex,
                    'table_name'     => $tableName,
                    'file_path'      => $relativePath,
                    'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                    'active_filters' => $normalizedActiveFilters,
                    'total_rows'     => $previewTotalRows,
                    'delimiter'      => $previewDelimiter,
                    'manual_kanca'   => $tableName === 'rka' ? trim((string) session('excel_manual_kanca', '')) : null,
                    'job_id'         => $jobId,
                ],
                'headers' => $sourceHeaders,
            ]);
            $this->progressService()->markQueued($jobId, [
                'status' => 'queued',
                'phase' => 'polars',
                'mode' => 'polars',
                'percent' => 0,
                'message' => 'Fase Polars siap diproses.',
                'total_rows' => (int) ($previewTotalRows ?? 0),
                'processed_rows' => 0,
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
        $delimiter   = null;

        if ($this->isCsvFile($path)) {
            $delimiter = $previewDelimiter !== null && $previewDelimiter !== ''
                ? $previewDelimiter
                : $this->detectCsvDelimiter($path);
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

        if ($this->isCsvFile($path)) {
            $totalRows = $this->estimateCsvImportTotalRows($path, (int) $headerIndex);
        }

        $dataRowsCount = max(0, $totalRows - ($headerIndex + 1));

        // Ambil nama kolom dari baris header
        $rawHeaders = $sheet[$headerIndex] ?? [];
        $normalizedHeadersForSession = [];
        foreach ($rawHeaders as $i => $h) {
            $normalizedHeadersForSession[$i] = !empty(trim((string)$h)) ? trim((string)$h) : 'COL_' . $i;
        }
        $normalizedHeadersForSession = array_values($this->resolveImportStrategy($tableName)->transformHeaders($normalizedHeadersForSession));

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

        $mustRefreshStagedCsv = $this->isSsaSimpananTable($tableName) || $this->isSsaPinjamanTable($tableName);

        if (!$this->isCsvFile($path) && ($mustRefreshStagedCsv || $stagedCsvPath === '' || !file_exists($stagedCsvPath))) {
            if ($mustRefreshStagedCsv && $stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
                @unlink($stagedCsvPath);
                $stagedCsvPath = '';
            }

            $stageResult = $this->stageExcelToCsv(
                static function (string $event, array $data): void {
                    // init phase does not stream progress
                },
                $path,
                $headerIndex,
                $normalizedHeadersForSession,
                $tableName
            );

            if (!empty($stageResult['staged_csv_path']) && file_exists((string) $stageResult['staged_csv_path'])) {
                $stagedCsvPath = (string) $stageResult['staged_csv_path'];
                $totalRows = max(1, ((int) ($stageResult['total_rows'] ?? 0)) + 1);
                $delimiter = ',';
            }
        }

        $jobId = $this->excelImportJobService()->createImportJobRecord((int) $idReport, $path, $dataRowsCount, [
            'controller' => static::class,
            'mode' => 'previews',
            'table_name' => $tableName,
            'file_path' => $relativePath,
            'header_index' => $headerIndex,
            'active_filters_hash' => sha1(json_encode($normalizedActiveFilters)),
            'normalized_headers_hash' => sha1(json_encode($normalizedHeadersForSession)),
        ]);

        session([
            'excel_import_params' => array_merge(session('excel_import_params', []), [
                'job_id' => $jobId,
            ]),
        ]);

        session([
            'excel_headers'        => $normalizedHeadersForSession,
            'excel_preview_meta'   => [
                'path' => $relativePath,
                'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                'header_index' => $headerIndex,
                'normalized_headers' => $normalizedHeadersForSession,
                'source_headers' => $normalizedHeadersForSession,
                'total_rows' => $totalRows,
                'delimiter' => $delimiter ?? null,
            ],
            'excel_import_params'  => [
                'header_index'   => $headerIndex,
                'table_name'     => $tableName,
                'file_path'      => $relativePath,
                'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                'active_filters' => $normalizedActiveFilters,
                'total_rows'     => $totalRows,
                'delimiter'      => $delimiter ?? null,
                'manual_kanca'   => $tableName === 'rka' ? trim((string) session('excel_manual_kanca', '')) : null,
                'job_id'         => $jobId,
            ],
        ]);
        $this->excelImportJobService()->putImportJobState($jobId, [
            'params' => [
                'header_index'   => $headerIndex,
                'table_name'     => $tableName,
                'file_path'      => $relativePath,
                'staged_csv_path' => $stagedCsvPath !== '' && file_exists($stagedCsvPath) ? $stagedCsvPath : null,
                'active_filters' => $normalizedActiveFilters,
                'total_rows'     => $totalRows,
                'delimiter'      => $delimiter ?? null,
                'manual_kanca'   => $tableName === 'rka' ? trim((string) session('excel_manual_kanca', '')) : null,
                'job_id'         => $jobId,
            ],
            'headers' => $normalizedHeadersForSession,
        ]);
        $this->progressService()->markQueued($jobId, [
            'status' => 'queued',
            'phase' => 'polars',
            'mode' => 'polars',
            'percent' => 0,
            'message' => 'Fase Polars siap diproses.',
            'total_rows' => (int) $totalRows,
            'processed_rows' => 0,
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
        return $this->excelStagingService()->findPython();
    }

    private function stageExcelToCsv(
        callable $send,
        string $sourcePath,
        int $headerIndex,
        array $normalizedHeaders,
        string $tableName
    ): ?array {
        $stagedCsvPath = $this->createStagedCsvPath($tableName);
        return $this->excelStagingService()->stageExcelToCsv(
            $send,
            $sourcePath,
            $headerIndex,
            $normalizedHeaders,
            $stagedCsvPath,
            null,
            'excel_stage_config_'
        );
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

        $importContext = $this->buildImportContext($tableName, $normalizedHeaders, $activeFilters);
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
            'unique_id_prefix' => $importContext['unique_id_prefix'] ?? null,
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

            $inserted = $this->loadCsvIntoMysqlChunked(
                $csvReadyPath,
                $tableName,
                $bulkLoadColumns,
                function (int $processedLines, int $totalLines) use ($send, $csvRowsPrepared): void {
                    $ratio = $totalLines > 0 ? min(1, $processedLines / $totalLines) : 1;
                    $percent = 96 + (int) floor($ratio * 3);
                    $send('progress', [
                        'percent' => min(99, $percent),
                        'message' => 'Memuat data ke MySQL (chunked)...',
                        'rows_done' => $csvRowsPrepared,
                        'total' => $csvRowsPrepared,
                        'speed' => 0,
                    ]);
                }
                ,
                8000,
                $csvRowsPrepared
            );
            $failed = max(0, $csvRowsPrepared - $inserted);
            $this->applyManualColumnValuesAfterLoad($tableName, $importContext, $inserted);

            if ($jobId > 0) {
                $this->progressService()->updateTotals(
                    $jobId,
                    $inserted,
                    $failed,
                    $csvRowsPrepared,
                    ($inserted > 0 || $csvRowsPrepared === 0) ? 'completed' : 'failed'
                );
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

    protected function processStagedCsvStream(
        callable $send,
        string $csvPath,
        string $tableName,
        array $activeFilters,
        array $normalizedHeaders,
        int $jobId,
        ?int $estimatedTotalRows = null,
        ?string $delimiter = null,
        bool $forceDirectLoad = false,
        ?callable $beforeDirectLoad = null
    ): bool {
        if ($csvPath === '' || !file_exists($csvPath)) {
            return false;
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return false;
        }
        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectCsvDelimiter($csvPath);
        $estimatedTotalRows = $estimatedTotalRows !== null
            ? max(0, $estimatedTotalRows)
            : $this->countCsvDataRows($csvPath);

        $bulkLoadColumns = $this->buildBulkLoadColumns($tableName, $normalizedHeaders, $activeFilters);
        $outputCsvPath = $this->createBulkLoadTempCsvPath($tableName, $jobId);
        $outputHandle = fopen($outputCsvPath, 'w');
        $cleanupPaths = [$outputCsvPath];

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
            $send('progress', [
                'percent' => 18,
                'message' => $forceDirectLoad
                    ? 'CSV staging siap. Menyiapkan LOAD DATA LOCAL INFILE ke MySQL...'
                    : 'CSV staging siap. Menyiapkan batch awal ke MySQL...',
                'rows_done' => 0,
                'total' => $estimatedTotalRows,
                'speed' => 0,
                'total_rows' => $estimatedTotalRows,
                'processed_rows' => 0,
                'mode' => $forceDirectLoad ? 'direct_load' : 'staged_load',
            ]);
            $rowsDone = 0;
            $startTime = microtime(true);
            $lastProgressAt = 0;
            $lastProgressWallClock = $startTime;
            $stagedProgressEveryRows = max(250, min(self::STREAM_PROGRESS_EVERY, 500));
            $timestamp = now()->toDateTimeString();
            $lineNumber = 1;

            if ($jobId > 0) {
                $job = $this->progressService()->findJob($jobId);
                $this->progressService()->updateJob($jobId, [
                    'total_files' => $estimatedTotalRows,
                    'total_success' => (int) ($job->total_success ?? 0),
                    'total_failed' => (int) ($job->total_failed ?? 0),
                ]);
            }

            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                $lineNumber++;

                if ($this->hasDailyLoanFieldCountMismatch($normalizedHeaders, $row, $lineNumber, 'stream_staged_csv_export', $delimiter)) {
                    continue;
                }

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

                $now = microtime(true);
                $shouldSendProgress = $rowsDone - $lastProgressAt >= $stagedProgressEveryRows
                    || ($rowsDone > 0 && ($now - $lastProgressWallClock) >= 2.0);

                if ($shouldSendProgress) {
                    $lastProgressAt = $rowsDone;
                    $lastProgressWallClock = $now;
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) ($rowsDone / $elapsed);
                    $percent = $estimatedTotalRows > 0
                        ? min(95, 18 + (int) (($rowsDone / $estimatedTotalRows) * 72))
                        : min(42, 18 + (int) floor($elapsed / 2));
                    $send('progress', [
                        'percent' => $percent,
                        'message' => $rowsDone > 0
                            ? 'Memfilter data dari CSV stage... (' . $speed . ' baris/detik)'
                            : 'Menyiapkan staging CSV Simpanan MultiPN...',
                        'rows_done' => $rowsDone,
                        'total' => $estimatedTotalRows,
                        'speed' => $speed,
                        'mode' => $forceDirectLoad ? 'direct_load' : 'staged_load',
                    ]);
                }
            }

            fclose($outputHandle);
            $outputHandle = null;

            if ($forceDirectLoad) {
                $directLoadSourcePath = $outputCsvPath;
                if ($tableName === 'simpanan_multipn') {
                    $loadSource = $this->prepareSimpananMultiPnDirectLoadSource($outputCsvPath, $delimiter, $send);
                    $directLoadSourcePath = (string) ($loadSource['path'] ?? $outputCsvPath);
                    if (!empty($loadSource['cleanup']) && $directLoadSourcePath !== '') {
                        $cleanupPaths[] = $directLoadSourcePath;
                    }
                }

                $send('progress', [
                    'percent' => 96,
                    'message' => 'CSV hasil filter siap. Memuat data ke MySQL via LOAD DATA LOCAL INFILE...',
                    'rows_done' => $rowsDone,
                    'total' => $estimatedTotalRows > 0 ? $estimatedTotalRows : $rowsDone,
                    'speed' => 0,
                    'mode' => $forceDirectLoad ? 'direct_load' : 'staged_load',
                ]);

                $inserted = $this->loadCsvIntoMysqlDirect(
                    $directLoadSourcePath,
                    $tableName,
                    $bulkLoadColumns,
                    $beforeDirectLoad
                );
            } else {
                $inserted = $this->loadCsvIntoMysqlChunked(
                    $outputCsvPath,
                    $tableName,
                    $bulkLoadColumns,
                    function (int $processedLines, int $totalLines) use ($send, $rowsDone, $estimatedTotalRows): void {
                        $ratio = $totalLines > 0 ? min(1, $processedLines / $totalLines) : 1;
                        $percent = 96 + (int) floor($ratio * 3);
                        $send('progress', [
                            'percent' => min(99, $percent),
                            'message' => 'Memuat data ke MySQL (chunked)...',
                            'rows_done' => $rowsDone,
                            'total' => $estimatedTotalRows > 0 ? $estimatedTotalRows : $rowsDone,
                            'speed' => 0,
                            'mode' => $forceDirectLoad ? 'direct_load' : 'staged_load',
                        ]);
                    },
                    $this->fallbackBulkLoadChunkLines(),
                    $estimatedTotalRows
                );
            }
            $failed = max(0, $rowsDone - $inserted);
            $this->applyManualColumnValuesAfterLoad($tableName, $context, $inserted);

            if ($jobId > 0) {
                $this->progressService()->updateTotals(
                    $jobId,
                    $inserted,
                    $failed,
                    $rowsDone,
                    ($inserted > 0 || $rowsDone === 0) ? 'completed' : 'failed'
                );
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
            foreach (array_unique(array_filter($cleanupPaths)) as $cleanupPath) {
                if (is_string($cleanupPath) && $cleanupPath !== '' && file_exists($cleanupPath)) {
                    @unlink($cleanupPath);
                }
            }
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
            'unique_id_prefix'   => $importContext['unique_id_prefix'] ?? null,
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
                    $uniquePrefix = trim((string) ($importContext['unique_id_prefix'] ?? 'imp'));
                    if ($uniquePrefix === '') {
                        $uniquePrefix = 'imp';
                    }

                    $clean[$importContext['unique_id_col']] = $uniquePrefix . '_' . uniqid('', true) . $importContext['suffix'];
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
            $send, $insertBatch, $tableName, $jobId, $importContext,
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

                $this->applyManualColumnValuesAfterLoad($tableName, $importContext, $totalInserted);

                if ($jobId > 0) {
                    $this->progressService()->updateTotals($jobId, $totalInserted, $totalFailed, null, $finalStatus);
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
        $sessionParams = session('excel_import_params', []);
        $jobId         = (int) ($sessionParams['job_id'] ?? $request->job_id ?? 0);
        if ($jobId <= 0) {
            return response()->stream(function () {
                echo "event: error\n";
                echo 'data: ' . json_encode(['message' => 'Job import tidak ditemukan.']) . "\n\n";
            }, 422, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-store',
                'X-Accel-Buffering' => 'no',
                'Connection' => 'keep-alive',
            ]);
        }

        request()->session()->save();
        $queueMessage = $request->attributes->get('queue_message');
        $this->executionService()->dispatch($jobId, is_string($queueMessage) ? $queueMessage : null);

        return $this->executionService()->streamStatus($request, $jobId);
    }

    public function executeQueuedImport(array $state, ?callable $send = null): array
    {
        return $this->excelQueuedImportService()->execute($state, [
            'resolve_import_strategy' => fn(string $tableName) => $this->resolveImportStrategy($tableName),
            'mark_failed' => fn(int $jobId, string $message, int $success = 0, int $failed = 0) => $this->progressService()->markFailed($jobId, $message, $success, $failed),
            'find_job' => fn(int $jobId) => $this->progressService()->findJob($jobId),
            'update_job' => fn(int $jobId, array $attributes, ?array $progressPayload = null) => $this->progressService()->updateJob($jobId, $attributes, $progressPayload),
            'assert_transactional_table' => fn(string $tableName, string $context) => $this->bulkLoadService()->assertTransactionalTable($tableName, $context),
            'is_csv_file' => fn(string $path) => $this->isCsvFile($path),
            'detect_csv_delimiter' => fn(string $path) => $this->detectCsvDelimiter($path),
            'count_csv_data_rows' => fn(string $path) => $this->countCsvDataRows($path),
            'resolve_csv_data_row_estimate' => fn(?int $totalRows, int $headerIndex) => $this->resolveCsvDataRowEstimate($totalRows, $headerIndex),
            'run_csv_pipeline' => fn(array $payload) => $this->pipelineService()->runCsvPipeline($payload),
            'process_daily_loan_direct_csv_stream' => fn($send, string $workingPath, string $tableName, array $normalizedHeaders, int $jobId, int $totalDataRows, ?string $delimiter) => $this->processDailyLoanDirectCsvStream($send, $workingPath, $tableName, $normalizedHeaders, $jobId, $totalDataRows, $delimiter),
            'process_daily_loan_bulk_csv_stream' => fn($send, string $workingPath, string $tableName, array $normalizedHeaders, array $activeFilters, int $jobId, int $totalDataRows, ?string $delimiter) => $this->processDailyLoanBulkCsvStream($send, $workingPath, $tableName, $normalizedHeaders, $activeFilters, $jobId, $totalDataRows, $delimiter),
            'process_staged_csv_stream' => fn($send, string $workingPath, string $tableName, array $activeFilters, array $normalizedHeaders, int $jobId, ?int $estimatedTotalRows = null, ?string $delimiter = null, bool $forceDirectLoad = false) => $this->processStagedCsvStream($send, $workingPath, $tableName, $activeFilters, $normalizedHeaders, $jobId, $estimatedTotalRows, $delimiter, $forceDirectLoad),
            'try_python_bulk_load' => fn($send, string $path, int $headerIndex, string $tableName, array $activeFilters, array $normalizedHeaders, int $jobId) => $this->tryPythonBulkLoad($send, $path, $headerIndex, $tableName, $activeFilters, $normalizedHeaders, $jobId),
            'try_python_gpu' => fn($send, string $path, int $headerIndex, string $tableName, array $activeFilters, array $normalizedHeaders, int $jobId) => $this->tryPythonGPU($send, $path, $headerIndex, $tableName, $activeFilters, $normalizedHeaders, $jobId),
            'assert_duplicate_guard' => fn(string $tableName) => $this->assertDuplicateGuard($tableName),
            'build_import_context' => fn(string $tableName, array $normalizedHeaders, array $activeFilters = [], array $importOptions = []) => $this->buildImportContext($tableName, $normalizedHeaders, $activeFilters, $importOptions),
            'map_excel_row_for_insert' => fn(array $row, array $normalizedHeaders, array $context, string $timestamp) => $this->mapExcelRowForInsert($row, $normalizedHeaders, $context, $timestamp),
            'fallback_insert_batch_size' => fn(): int => $this->fallbackInsertBatchSize(),
            'insert_batch_with_fallback' => function (array $batch, string $tableName, int &$totalInserted, int &$totalFailed): void {
                $this->insertBatchWithFallback($batch, $tableName, $totalInserted, $totalFailed);
            },
            'cleanup_successful_import_artifacts' => fn(int $jobId, string $relativePath, string $path, array $extraPaths = []) => $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $path, $extraPaths),
            'cleanup_service_dispatch_imported_job_sync' => fn(int $jobId, string $status) => $this->cleanupService()->dispatchImportedJobSync($jobId, source: static::class),
        ], $send);
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
            $endExclusive = $startRow + $chunkSize;
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
            $this->releaseSessionLockIfNeeded();

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

                    $row = $this->normalizeCsvRow($row, $delimiter);
                    if (empty(array_filter((array) $row, fn($v) => trim((string) $v) !== ''))) continue;

                    if ($this->hasDailyLoanFieldCountMismatch($normalizedHeaders, $row, $rowIndex + 1, 'process_excel_chunk_csv', $delimiter)) {
                        continue;
                    }

                    $row = $this->normalizeCsvRow($row, $delimiter, count($normalizedHeaders));

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
                    $job = $this->progressService()->findJob($jobId);
                    $this->progressService()->updateJob($jobId, [
                        'total_success' => (int) ($job->total_success ?? 0) + $chunkInserted,
                        'total_failed'  => (int) ($job->total_failed ?? 0) + $chunkFailed,
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
                $job = $this->progressService()->findJob($jobId);
                $this->progressService()->updateJob($jobId, [
                    'total_success' => (int) ($job->total_success ?? 0) + $chunkInserted,
                    'total_failed'  => (int) ($job->total_failed ?? 0) + $chunkFailed,
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
