<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon; 

class ImportFileController extends Controller
{
    private const PREVIEW_SAMPLE_LIMIT = 1200;
    private const PREVIEW_UNIQUE_SCAN_LIMIT = 4000;
    private const PREVIEW_UNIQUE_LIMIT_PER_COLUMN = 400;
    private const IMPORT_BATCH_SIZE = 1000;

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

        $firstLine = fgets($handle);
        $delimiters = [',' => 0, ';' => 0, '|' => 0, "\t" => 0, '.' => 0];

        foreach ($delimiters as $delim => &$count) {
            $count = substr_count((string) $firstLine, $delim);
        }

        arsort($delimiters);

        return key($delimiters);
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
                    $data[$posisiIndex] = Carbon::parse(str_replace('/', '-', $rawPosisi))->format('Y-m-d');
                } elseif ($tahunIndex !== -1 && isset($data[$tahunIndex]) && trim((string) $data[$tahunIndex]) !== '') {
                    $rawTahun = trim((string) $data[$tahunIndex]);
                    if (preg_match('/^([a-zA-Z]+\s+\d+)/', $rawPosisi, $matches)) {
                        $data[$posisiIndex] = Carbon::parse($matches[1] . ' ' . $rawTahun)->format('Y-m-d');
                    } else {
                        $data[$posisiIndex] = Carbon::parse($rawPosisi)->format('Y-m-d');
                    }
                } else {
                    $data[$posisiIndex] = Carbon::parse($rawPosisi)->format('Y-m-d');
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

    private function logFailedImportRow(string $tableName, array $row, string $errorMessage): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'import_' . $tableName,
            'payload' => json_encode(['error' => 'Single row failed.', 'sample' => $row]),
            'exception' => $errorMessage,
            'failed_at' => now(),
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

        try {
            DB::table($tableName)->insert($chunk);
            $totalSuccess += count($chunk);
            return;
        } catch (\Throwable $e) {
            $lastErrorMsg = substr($e->getMessage(), 0, 800) . '...';
        }

        if (count($chunk) === 1) {
            $totalFailed++;
            $this->logFailedImportRow($tableName, $chunk[0], $lastErrorMsg);
            return;
        }

        $midpoint = (int) ceil(count($chunk) / 2);
        $this->insertBufferedChunk(array_slice($chunk, 0, $midpoint), $tableName, $totalSuccess, $totalFailed, $lastErrorMsg);
        $this->insertBufferedChunk(array_slice($chunk, $midpoint), $tableName, $totalSuccess, $totalFailed, $lastErrorMsg);
    }

    private function flushInsertBuffer(
        array &$buffer,
        string $tableName,
        int &$totalSuccess,
        int &$totalFailed,
        string &$lastErrorMsg,
        ?callable $beforeInsert = null
    ): void {
        if (empty($buffer)) {
            return;
        }

        foreach (array_chunk($buffer, self::IMPORT_BATCH_SIZE) as $chunk) {
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
        string $uniqueSuffix
    ): ?array {
        if ($isBrilinkSummary) {
            $rawPeriode = $sourceData[0] ?? '';
            $periode = strpos((string) $rawPeriode, ':') !== false
                ? trim(explode(':', (string) $rawPeriode, 2)[1] ?? '')
                : trim((string) $rawPeriode);

            $brilinkOffset = $this->hasManualNumberingColumn($sourceData) ? 1 : 0;

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

        foreach ($selectedColumns as $index) {
            if (!isset($csvHeaders[$index])) {
                continue;
            }

            $colName = str_replace(' ', '_', $csvHeaders[$index]);

            if (strtolower($colName) === 'id' || strtolower($colName) === 'uniqueid_namareport') {
                continue;
            }

            $cellValue = isset($sourceData[$index]) ? trim((string) $sourceData[$index]) : '';
            if (in_array(strtoupper($colName), self::NUMERIC_COLUMNS, true)) {
                $cellValue = $this->normalizeDecimalValue($cellValue);
            }

            $rowData[$colName] = ($cellValue === '') ? null : $cellValue;
        }

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
            while (($data = fgetcsv($handle, 10000, $delimiter)) !== false) {
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

                if ($samplePeriode === null && isset($parsedRow[0])) {
                    $samplePeriode = trim((string) $parsedRow[0]) ?: null;
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

    public function upload(Request $request)
    {
        $request->validate(['id_report' => 'required', 'file' => 'required|file|mimes:rar,csv']);
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
            return redirect()->route('import.preview.direct', [
                'file_path' => $files[0]['path'],
            ]);
        }

        return redirect()->route('import.select');
    }

    public function preview(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('auto_detect_line_endings', true);
        ini_set('max_execution_time', 0); 

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

        if ($reportData && (stripos($reportData->nama_report, 'BRILINK Web - Laporan Summary Transaksi') !== false || stripos($reportData->nama_report, 'brilink_web') !== false)) {
            $isBrilinkSummary = true;
        }

        if ($extension === 'csv') {
            if (($handle = fopen($filePath, "r")) !== FALSE) {
                $firstLine = fgets($handle);
                if ($currentDelimiter === 'auto') {
                    $delimiters = [',' => 0, ';' => 0, '|' => 0, "\t" => 0, '.' => 0];
                    foreach ($delimiters as $delim => &$count) { $count = substr_count($firstLine, $delim); }
                    arsort($delimiters); $delimiter = key($delimiters); 
                } else { $delimiter = $currentDelimiter; }
                rewind($handle); 
                
                $rowCounter = 0;
                $savedRows = 0;
                $scannedRows = 0;
                $collectUniqueValues = true;
                while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                    if (empty($data) || implode('', $data) === '') continue;
                    
                    if ($rowCounter == 0) {
                        $headers = array_map(function ($header) {
                            return str_replace(' ', '_', $header);
                        }, $this->formatCsvHeaders($data, $isBrilinkSummary));

                        if (!$isBrilinkSummary) {
                            foreach ($headers as $i => $h) { 
                                if (stripos($h, 'POSISI') !== false) { $posisiIndex = $i; }
                                if (stripos($h, 'TAHUN') !== false) { $tahunIndex = $i; }
                            }
                        }

                        foreach ($headers as $i => $h) {
                            $uniqueValues[$i] = [];
                        }

                    } else {
                        if (trim($data[0]) === 'TAHUN' || stripos(trim($data[0]), 'textbox') !== false) continue;

                        if ($isBrilinkSummary) {
                            $data = $this->transformBrilinkSummaryRow($data);
                        } 
                        else {
                            if (count($data) < count($headers)) {
                                $data = array_pad($data, count($headers), null);
                            }
                            if (count($data) > count($headers)) continue; 

                            if ($posisiIndex !== -1 && isset($data[$posisiIndex]) && trim($data[$posisiIndex]) !== '') {
                                $rawPosisi = trim($data[$posisiIndex]);
                                try {
                                    if (strpos($rawPosisi, '/') !== false) {
                                        $data[$posisiIndex] = Carbon::parse(str_replace('/', '-', $rawPosisi))->format('Y-m-d');
                                    } else {
                                        if ($tahunIndex !== -1 && isset($data[$tahunIndex]) && trim($data[$tahunIndex]) !== '') {
                                            $rawTahun = trim($data[$tahunIndex]);
                                            if (preg_match('/^([a-zA-Z]+\s+\d+)/', $rawPosisi, $matches)) {
                                                $fixedDateStr = $matches[1] . ' ' . $rawTahun; 
                                                $data[$posisiIndex] = Carbon::parse($fixedDateStr)->format('Y-m-d');
                                            } else {
                                                $data[$posisiIndex] = Carbon::parse($rawPosisi)->format('Y-m-d');
                                            }
                                        } else {
                                            $data[$posisiIndex] = Carbon::parse($rawPosisi)->format('Y-m-d');
                                        }
                                    }
                                } catch (\Exception $e) {}
                            }
                        }

                        $scannedRows++;

                        if ($savedRows < self::PREVIEW_SAMPLE_LIMIT) {
                            $previewData[] = $data;
                            $savedRows++;
                        }

                        if ($collectUniqueValues) {
                            foreach ($data as $i => $val) {
                                if (!isset($uniqueValues[$i])) {
                                    continue;
                                }

                                $cleanVal = trim((string) $val);
                                if (count($uniqueValues[$i]) < self::PREVIEW_UNIQUE_LIMIT_PER_COLUMN || isset($uniqueValues[$i][$cleanVal])) {
                                    $uniqueValues[$i][$cleanVal] = true;
                                }
                            }

                            $allColumnsFilled = !empty($uniqueValues);
                            foreach ($uniqueValues as $valuesMap) {
                                if (count($valuesMap) < self::PREVIEW_UNIQUE_LIMIT_PER_COLUMN) {
                                    $allColumnsFilled = false;
                                    break;
                                }
                            }

                            if ($scannedRows >= self::PREVIEW_UNIQUE_SCAN_LIMIT || $allColumnsFilled) {
                                $collectUniqueValues = false;
                            }
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
        
        session(['final_import_path' => $filePath]);

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
        ));
    }

    public function initImport(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('auto_detect_line_endings', true);
        ini_set('max_execution_time', 0);

        $request->validate([
            'file_path' => 'required|string',
            'selected_columns' => 'required|array|min:1',
            'active_filters_json' => 'nullable|string',
            'delimiter' => 'required|string',
        ]);

        $filePath = $request->input('file_path');
        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json'), true) ?: [];
        $normalizedFilters = [];
        foreach ($activeFilters as $columnIndex => $allowedValues) {
            $normalizedFilters[(int) $columnIndex] = array_fill_keys(array_map(function ($value) {
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
            $isDuplicate = DB::table($tableName)->where('periode', $meta['sample_periode'])->exists();
            if ($isDuplicate) {
                $duplicateText = "Data untuk PERIODE <b>{$meta['sample_periode']}</b> sudah pernah diunggah sebelumnya ke tabel <b class='text-uppercase'>{$tableName}</b>.<br><br>Sistem membatalkan proses ini.";
            }
        } elseif ($meta['sample_posisi']) {
            $isDuplicate = DB::table($tableName)->whereDate('POSISI', $meta['sample_posisi'])->exists();
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
            ], 422);
        }

        $jobId = DB::table('import_jobs')->insertGetId([
            'id_report' => $idReport,
            'file_name' => basename($filePath),
            'folder_path' => dirname($filePath),
            'status' => 'processing',
            'total_files' => $meta['total_rows'],
            'total_success' => 0,
            'total_failed' => 0,
            'created_by' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session([
            'csv_import_params' => [
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
                'duplicate_lookup' => $duplicateLookup,
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'job_id' => $jobId,
            'total_rows' => $meta['total_rows'],
        ]);
    }

    public function processImportStream(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('auto_detect_line_endings', true);
        ini_set('max_execution_time', 0);
        DB::disableQueryLog();

        $params = session('csv_import_params', []);
        $jobId = (int) ($params['job_id'] ?? $request->query('job_id', 0));

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
                $uniqueSuffix = $params['unique_suffix'] ?? '_MDT';
                $isBrilinkSummary = (bool) ($params['is_brilink_summary'] ?? false);
                $csvHeaders = $params['headers'] ?? [];
                $posisiIndex = (int) ($params['posisi_index'] ?? -1);
                $tahunIndex = (int) ($params['tahun_index'] ?? -1);
                $totalRows = (int) ($params['total_rows'] ?? 0);
                $duplicateLookup = $params['duplicate_lookup'] ?? [];

                if ($filePath === '' || !file_exists($filePath)) {
                    $send('error', ['message' => 'File tidak ditemukan di server. Silakan upload ulang.']);
                    return;
                }

                $handle = fopen($filePath, 'r');
                if ($handle === false) {
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

                $flushBuffer = function () use (&$buffer, &$totalSuccess, &$totalFailed, &$lastErrorMsg, $tableName, $shouldInsertRow) {
                    $this->flushInsertBuffer($buffer, $tableName, $totalSuccess, $totalFailed, $lastErrorMsg, $shouldInsertRow);
                };

                while (($data = fgetcsv($handle, 10000, $resolvedDelimiter)) !== false) {
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
                        $isBrilinkSummary ? $data : $parsedRow,
                        $selectedColumns,
                        $csvHeaders,
                        $isBrilinkSummary,
                        $uniqueSuffix
                    );
                    if ($mappedRow === null) {
                        $rowCounter++;
                        continue;
                    }

                    $buffer[] = $mappedRow;
                    $rowsDone++;
                    $rowCounter++;

                    if (count($buffer) >= self::IMPORT_BATCH_SIZE) {
                        $flushBuffer();
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
        ini_set('memory_limit', '-1');
        ini_set('auto_detect_line_endings', true);
        ini_set('max_execution_time', 0); 

        $request->validate([
            'file_path' => 'required|string',
            'selected_columns' => 'required|array|min:1',
            'active_filters_json' => 'nullable|string',
            'delimiter' => 'required|string'
        ]);

        $filePath = $request->input('file_path');
        $selectedColumns = $request->input('selected_columns');
        $activeFilters = json_decode($request->input('active_filters_json'), true) ?: [];
        $currentDelimiter = $request->input('delimiter', 'auto');
        
        // 🔥 1. DETEKSI REPORT (WAJIB SAMA DENGAN PREVIEW)
        $idReport = session('active_id_report', 1);
        $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
        $isBrilinkSummary = false;

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

        // 🔥 PERBAIKAN FINAL: PRIORITAS TABLE_NAME DARI DB
        $tableName = 'jumlah_merchant_detail'; // default fallback

        if ($reportData) {
            if (!empty($reportData->table_name)) {
                $tableName = $reportData->table_name;
            } else {
                // fallback lama (JANGAN DIHAPUS)
                $tableName = strtolower(str_replace(' ', '_', $reportData->nama_report));
            }
        }

        // 🔥 VALIDASI FINAL
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            $tableName = 'jumlah_merchant_detail';
        }

        $uniqueSuffix = '_MDT'; 
        if ($tableName === 'sv_merchant') {
            $uniqueSuffix = '_SVMer';
        } elseif ($tableName === 'merchant_qris') {
            $uniqueSuffix = '_MQ';
        } elseif ($tableName === 'merchant_qris_volume') {
            $uniqueSuffix = '_MQV'; 
        } elseif ($tableName === 'brilink_web_laporan_summary_transaksi_brilink_web') {
            $uniqueSuffix = '_BST';
        }

        $dataToInsert = [];
        $csvHeaders = [];
        $duplicateLookup = [];

        $posisiIndex = -1;
        $tahunIndex = -1;

        if (($handle = fopen($filePath, "r")) !== FALSE) {
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
            while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                if (empty($data) || implode('', $data) === '') continue;

                // 🔥 2. SKIP HEADER DEFAULT (INI KRITIS)
                if ($rowCounter == 0) {
                    $csvHeaders = $isBrilinkSummary ? [] : $this->formatCsvHeaders($data, false);
                    if (!$isBrilinkSummary) {
                        foreach ($csvHeaders as $idx => $hdr) {
                            if (stripos($hdr, 'posisi') !== false) { $posisiIndex = $idx; }
                            if (stripos($hdr, 'tahun') !== false) { $tahunIndex = $idx; }
                        }
                    }
                    
                    $rowCounter++;
                    continue; 
                }

                if (trim($data[0]) === 'TAHUN' || stripos(trim($data[0]), 'textbox') !== false) continue;

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
                                $data[$posisiIndex] = Carbon::parse(str_replace('/', '-', $rawPosisi))->format('Y-m-d');
                            } else {
                                if ($tahunIndex !== -1 && isset($data[$tahunIndex]) && trim($data[$tahunIndex]) !== '') {
                                    $rawTahun = trim($data[$tahunIndex]);
                                    if (preg_match('/^([a-zA-Z]+\s+\d+)/', $rawPosisi, $matches)) {
                                        $fixedDateStr = $matches[1] . ' ' . $rawTahun;
                                        $data[$posisiIndex] = Carbon::parse($fixedDateStr)->format('Y-m-d');
                                    } else {
                                        $data[$posisiIndex] = Carbon::parse($rawPosisi)->format('Y-m-d');
                                    }
                                } else {
                                    $data[$posisiIndex] = Carbon::parse($rawPosisi)->format('Y-m-d');
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
                foreach ($activeFilters as $colIdx => $allowedValues) {
                    $cellValue = isset($filterData[$colIdx]) ? trim((string) $filterData[$colIdx]) : '';
                    if (!in_array($cellValue, $allowedValues)) {
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

                    foreach ($selectedColumns as $index) {
                        if (!isset($csvHeaders[$index])) continue;
                        
                        $colName = str_replace(' ', '_', $csvHeaders[$index]);

                        if (strtolower($colName) === 'id' || strtolower($colName) === 'uniqueid_namareport') {
                            continue;
                        }

                        $cellValue = isset($data[$index]) ? trim($data[$index]) : '';
                        
                        if (in_array(strtoupper($colName), self::NUMERIC_COLUMNS, true)) {
                            $cellValue = $this->normalizeDecimalValue($cellValue);
                        }

                        $rowData[$colName] = ($cellValue === '') ? null : $cellValue;
                    }
                }
                
                $dataToInsert[] = $rowData;
                $rowCounter++;
            }
            fclose($handle);
        }

        $samplePosisi = null;
        $samplePeriode = null;

        if (!empty($dataToInsert)) {
            $samplePosisi = $dataToInsert[0]['POSISI'] ?? null;
            $samplePeriode = $dataToInsert[0]['periode'] ?? null;
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
                'text' => $duplicateText
            ];
            
            return $request->expectsJson()
                ? response()->json($response)
                : redirect()->route('import.index')->with('sweet_warning', $response);
        }

        $jobId = DB::table('import_jobs')->insertGetId([
            'id_report' => $idReport,
            'file_name' => basename($filePath),
            'folder_path' => dirname($filePath),
            'status' => 'processing',
            'total_files' => count($dataToInsert),
            'created_by' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\Schema::dropIfExists('import_mappings');

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

        $this->flushInsertBuffer($dataToInsert, $tableName, $totalSuccess, $totalFailed, $lastErrorMsg, $shouldInsertRow);

        $finalStatus = $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed';
        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => $finalStatus,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed + $duplicateSkipped,
            'updated_at' => now(),
        ]);

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
}
