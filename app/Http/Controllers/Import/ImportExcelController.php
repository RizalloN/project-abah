<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Import\Concerns\AllocatesGapIds;
use App\Support\ReportDataSyncService;
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
    use AllocatesGapIds;

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

    private function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
    {
        $reportData = $this->resolveActiveReport();

        return ($reportData && !empty($reportData->table_name))
            ? (string) $reportData->table_name
            : $default;
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
        $normalizedHeader = trim((string) $normalizedHeader, '_');

        $aliases = [
            'TEXTBOX20' => 'total_kewajiban',
            'TOTAL_KEWAJIBAN' => 'total_kewajiban',
            'TEXTBOX21' => 'os_idr',
            'OS_IDR' => 'os_idr',
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

        return $this->isValidSimpananMultiPnRowValues($valuesByHeader);
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

        try {
            $date = Carbon::parse($value);
            $year = (int) $date->format('Y');

            return $year >= 2000 && $year <= 2100;
        } catch (\Throwable) {
            return false;
        }
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
        $dbColumns = Schema::hasTable($tableName)
            ? Schema::getColumnListing($tableName)
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
        return $this->processExcelStream($this->useDailyLoanReport($request));
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

        return $this->uploadExcel($this->useSimpananMultiPnReport($request), ['xlsx', 'xls']);
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
        if ($jobId > 0) {
            try {
                app(ReportDataSyncService::class)->syncImportedJob($jobId, source: static::class);
            } catch (\Throwable $e) {
                Log::warning('Failed to sync report snapshots after import: ' . $e->getMessage(), [
                    'job_id' => $jobId,
                ]);
            }
        }

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

        $uniqueIdCol = null;
        $suffix = '_DLD';

        if (str_contains($tableName, 'simpanan')) {
            $simpananUniqueCandidates = ['uniqueid_SMPN', 'uniqueid_SimoPN'];
            foreach ($simpananUniqueCandidates as $candidate) {
                $lower = strtolower($candidate);
                if (isset($tableColumnsLookup[$lower])) {
                    $uniqueIdCol = $tableColumnsByLower[$lower] ?? $candidate;
                    break;
                }
            }

            $suffix = '_SMPN';
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
            'table_name' => $tableName,
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

            $headerName = (string) ($rule['header_name'] ?? '');
            foreach ($this->getHeaderDatabaseCandidates($headerName) as $candidateColumn) {
                $mappedExcelData[$candidateColumn] = $value;
            }
        }

        $context['row_sequence']++;
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
            $finalRow[$context['table_columns_by_lower'][$dbCol] ?? $dbCol] = $value;
        }

        $minimumColumns = !empty($context['unique_id_col']) ? 3 : 2;

        if (($context['table_name'] ?? '') === 'simpanan_multipn' && !$this->hasRequiredSimpananMultiPnImportData($finalRow)) {
            return null;
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

    private function getExcelDateColumnsLookup(): array
    {
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
            'SALDO_IDR',
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

        $activeIdReport = (int) $request->id_report;
        $redirect = $activeIdReport === self::DAILY_LOAN_REPORT_ID
            ? route('import.dailyloan.preview', ['ck' => $cacheKey])
            : ($activeIdReport === self::SIMPANAN_MULTIPN_REPORT_ID
                ? route('import.simpanan.preview', ['ck' => $cacheKey])
                : route('import.excel.preview', ['ck' => $cacheKey]));

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status'    => 'success',
                'cache_key' => $cacheKey,
                'redirect'  => $redirect,
            ]);
        }

        return redirect($redirect);
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
                    $send('error_msg', ['message' => 'File tidak ditemukan di server. Silakan upload ulang.']);
                    return;
                }

                $useCacheKey = $cacheKey ?: ('excel_preview_' . md5(urldecode($sessionPath) . '|' . microtime(true)));
                $redirect = $activeIdReport === self::DAILY_LOAN_REPORT_ID
                    ? route('import.dailyloan.preview', ['ck' => $useCacheKey])
                    : ($activeIdReport === self::SIMPANAN_MULTIPN_REPORT_ID
                        ? route('import.simpanan.preview', ['ck' => $useCacheKey])
                        : route('import.excel.preview', ['ck' => $useCacheKey]));

                $send('progress', ['percent' => 35, 'message' => 'File ditemukan. Menyiapkan preview...', 'step' => 1]);
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
            Schema::getColumnListing($tableName)
        );

        $previewStateKey = 'excel_preview_' . md5($relativePath . '|fallback|' . microtime(true));
        $this->putExcelPreviewState($previewStateKey, [
            'displayFilterMap' => $reorderedPayload['displayFilterMap'] ?? [],
            'previewMeta' => [
                'path' => $relativePath,
                'staged_csv_path' => null,
                'header_index' => $headerIndex,
                'normalized_headers' => $reorderedPayload['headers'],
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

                if ($jobId > 0 && $totalInserted > 0) {
                    try {
                        app(ReportDataSyncService::class)->syncImportedJob($jobId, source: static::class);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to sync report snapshots after import stream completion: ' . $e->getMessage(), [
                            'job_id' => $jobId,
                            'status' => $finalStatus,
                        ]);
                    }
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

                if ($this->isCsvFile($path)) {
                    if ($this->supportsNativeBulkLoad()) {
                        $send('progress', [
                            'percent'   => 8,
                            'message'   => 'Mode cepat aktif: menyiapkan CSV staging untuk bulk load MySQL...',
                            'rows_done' => 0,
                            'total'     => 0,
                            'speed'     => 0,
                        ]);

                        $bulkHandled = $this->processStagedCsvStream(
                            $send,
                            $path,
                            $tableName,
                            $activeFilters,
                            $normalizedHeaders,
                            $jobId
                        );

                        if ($bulkHandled) {
                            if ($jobId > 0) {
                                $job = DB::table('import_jobs')->where('id', $jobId)->first();
                                if ($job && $job->status === 'completed') {
                                    try {
                                        app(ReportDataSyncService::class)->syncImportedJob($jobId, source: static::class);
                                    } catch (\Throwable $e) {
                                        Log::warning('Failed to sync report snapshots after staged CSV bulk load: ' . $e->getMessage(), [
                                            'job_id' => $jobId,
                                            'status' => $job->status,
                                        ]);
                                    }

                                    $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $path, $stagedCsvPath !== '' ? [$stagedCsvPath] : []);
                                }
                            }

                            return;
                        }

                        $send('progress', [
                            'percent'   => 9,
                            'message'   => 'Bulk load native tidak tersedia untuk file ini. Fallback ke insert bertahap...',
                            'rows_done' => 0,
                            'total'     => 0,
                            'speed'     => 0,
                        ]);
                    }

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
                            $this->insertBatchWithFallback($batch, $tableName, $totalInserted, $totalFailed);
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

                if ($jobId > 0 && $totalInserted > 0) {
                    try {
                        app(ReportDataSyncService::class)->syncImportedJob($jobId, source: static::class);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to sync report snapshots after native CSV import stream: ' . $e->getMessage(), [
                            'job_id' => $jobId,
                            'status' => $finalStatus,
                        ]);
                    }
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

                if ($jobId > 0 && $totalInserted > 0 && $finalStatus !== 'completed') {
                    try {
                        app(ReportDataSyncService::class)->syncImportedJob($jobId, source: static::class);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to sync report snapshots after chunk import stream: ' . $e->getMessage(), [
                            'job_id' => $jobId,
                            'status' => $finalStatus,
                        ]);
                    }
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
