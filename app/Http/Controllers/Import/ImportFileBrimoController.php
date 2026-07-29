<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Import\Concerns\AllocatesGapIds;
use App\Http\Controllers\Import\Concerns\AuthorizesImportSourceFiles;
use App\Http\Controllers\Import\Concerns\SmartCsvImportSupport;
use App\Services\Import\MySqlBulkLoadService;
use App\Support\ReportDataSyncService;
use App\Support\SargableDateFilter;
use App\Support\StrictDateParser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon; 

class ImportFileBrimoController extends Controller
{
    use AllocatesGapIds;
    use AuthorizesImportSourceFiles;
    use SmartCsvImportSupport;

    private const BULK_LOAD_TEMP_DIR = 'app/import_bulk';

    private function bulkLoadService(): MySqlBulkLoadService
    {
        return app(MySqlBulkLoadService::class);
    }

    private function normalizeBrimoPeriodValue($value, $fallbackPosisi = null, $fallbackTahun = null): ?string
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
                    return Carbon::createFromFormat($format, $normalized)->startOfMonth()->format('Y-m');
                } catch (\Throwable $e) {
                }
            }

            try {
                return Carbon::parse($normalized)->startOfMonth()->format('Y-m');
            } catch (\Throwable $e) {
            }
        }

        $fallbackPosisi = trim((string) $fallbackPosisi);
        if ($fallbackPosisi !== '') {
            try {
                return Carbon::parse($fallbackPosisi)->startOfMonth()->format('Y-m');
            } catch (\Throwable $e) {
            }
        }

        $fallbackTahun = trim((string) $fallbackTahun);
        if (preg_match('/^\d{4}$/', $fallbackTahun) === 1) {
            return $fallbackTahun . '-01';
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

    private function resolveTableName($reportData): string
    {
        $tableName = 'user_brimo_rpt_v2';
        $hasExplicitTableName = false;

        if ($reportData) {
            if (!empty($reportData->table_name)) {
                $tableName = $reportData->table_name;
                $hasExplicitTableName = true;
            } elseif (!empty($reportData->nama_report) && stripos($reportData->nama_report, 'fin') !== false) {
                $tableName = 'user_brimo_fin';
            }
        }

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            if ($reportData || $hasExplicitTableName) {
                throw new \RuntimeException("Tabel tujuan import Brimo `{$tableName}` tidak ditemukan. Periksa konfigurasi nama_report.table_name.");
            }

            return 'user_brimo_rpt_v2';
        }

        return $tableName;
    }

    private function resolveUniqueSuffix(string $tableName): string
    {
        return match ($tableName) {
            'user_brimo_fin' => '_UBFin',
            'brimo_fin_all' => '_BFA',
            default => '_UBv2',
        };
    }

    private function normalizeBrimoHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = preg_replace('/[^A-Za-z0-9]+/', '_', trim((string) $header));

        return strtolower(trim((string) $header, '_'));
    }

    private function normalizeBrimoDecimalValue($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(["\xc2\xa0", ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');
            $value = $lastComma > $lastDot
                ? str_replace('.', '', str_replace(',', '.', $value))
                : str_replace(',', '', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        $clean = preg_replace('/[^0-9.\-]/', '', $value);
        if ($clean === '' || $clean === '-' || $clean === '.' || !is_numeric($clean)) {
            return null;
        }

        return number_format((float) $clean, 2, '.', '');
    }

    private function isBrimoNumericColumn(string $column): bool
    {
        foreach (['jumlah', 'nominal', 'fee', 'saldo', 'volume', 'transaksi', 'sales'] as $needle) {
            if (str_contains($column, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function readCsvRecord($handle, string $delimiter)
    {
        $line = fgets($handle);
        if ($line === false) {
            return false;
        }

        $row = $this->smartParseCsvLine((string) $line, $delimiter, false);

        return $row !== [] ? $row : false;
    }

    private function writableColumnsForTable(string $tableName): array
    {
        return array_fill_keys(DB::getSchemaBuilder()->getColumnListing($tableName), true);
    }

    private function writeBulkTempCsv(array $rows, array $columns, string $tableName, int $jobId): string
    {
        $directory = storage_path(self::BULK_LOAD_TEMP_DIR);
        $path = $this->bulkLoadService()->createBulkLoadTempCsvPath($directory, $tableName, $jobId);
        $handle = @fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuat CSV sementara untuk bulk import Brimo.');
        }

        try {
            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $column) {
                    $value = $row[$column] ?? null;
                    $values[] = $value === null ? '\N' : $value;
                }
                fputcsv($handle, $values);
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }

    public function upload(Request $request)
    {
        $request->validate([
            'id_report' => ['required'],
            'file' => ['required', 'file', 'mimes:rar', 'max:' . $this->configuredImportUploadMaxKilobytes()],
        ]);

        $file = $request->file('file');
        $storagePath = $this->createSecureImportDirectory();
        $storedUpload = $this->storeImportUpload($file, $storagePath);
        $fullPath = $storedUpload['path'];

        try {
            $files = $this->extractImportArchive($fullPath, $storagePath);
        } catch (\Throwable $e) {
            $this->cleanupAuthorizedImportDirectory($fullPath);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        session([
            'import_files'      => $files,
            'active_id_report'  => $request->input('id_report'),
            'import_type'       => 'brimo',
        ]);

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
        ini_set('memory_limit', '-1');
        ini_set('auto_detect_line_endings', true);
        ini_set('max_execution_time', 0); 

        $request->validate(['file_path' => 'required|string', 'delimiter' => 'nullable|string']);
        $filePath = $this->authorizeImportSourceFile((string) $request->input('file_path'));
        $currentDelimiter = $request->input('delimiter', 'auto');
        if (!file_exists($filePath)) { return back()->with('error', 'File tidak ditemukan di server.'); }
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $headers = []; $previewData = []; $uniqueValues = []; 
        $posisiIndex = -1; 
        $tahunIndex = -1;
        $periodeIndex = -1;

        if (in_array($extension, ['csv', 'txt'])) {
            if (($handle = fopen($filePath, 'r')) !== FALSE) {
                $firstLine = fgets($handle);
                if ($currentDelimiter === 'auto') {
                    $delimiters = [',' => 0, ';' => 0, '|' => 0, "\t" => 0, '.' => 0];
                    foreach ($delimiters as $delim => &$count) { $count = substr_count($firstLine, $delim); }
                    arsort($delimiters); $delimiter = key($delimiters); 
                } else { $delimiter = $currentDelimiter; }
                rewind($handle); 
                
                $rowCounter = 0; $savedRows = 0;
                while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                    if (empty($data) || implode('', $data) === '') continue;
                    
                    if ($rowCounter == 0) {
                        $headers = array_map(function($val) {
                            $clean = trim(preg_replace('/[\xef\xbb\xbf]/', '', $val));
                            return str_replace(' ', '_', $clean);
                        }, $data);
                        
                        foreach ($headers as $i => $h) { 
                            if (stripos($h, 'POSISI') !== false) { $posisiIndex = $i; }
                            if (stripos($h, 'TAHUN') !== false) { $tahunIndex = $i; }
                            if (stripos($h, 'PERIODE') !== false) { $periodeIndex = $i; }
                        }

                        foreach ($headers as $i => $h) { 
                            $uniqueValues[$i] = []; 
                        }

                    } else {
                        if (trim($data[0]) === 'TAHUN' || stripos(trim($data[0]), 'textbox') !== false) continue;

                        if (count($data) < count($headers)) {
                            $data = array_pad($data, count($headers), null);
                        }
                        if (count($data) > count($headers)) continue; 

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

                        if ($savedRows < 2500) { 
                            $previewData[] = $data; 
                            $savedRows++; 
                        }
                        
                        foreach ($data as $i => $val) {
                            if (isset($uniqueValues[$i])) {
                                $cleanVal = trim($val);
                                $uniqueValues[$i][$cleanVal] = true; 
                                if (count($uniqueValues[$i]) > 5000) { unset($uniqueValues[$i]); }
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

        $area6ColumnHints = ['MBDESC'];
        $initialArea6Selections = $this->buildInitialArea6Selections($headers, $formattedUniqueValues, $area6ColumnHints);
        
        session(['final_import_path' => $filePath]);

        $processRoute = route('import.brimo.process');

        return view('import.preview', compact(
            'headers',
            'previewData',
            'filePath',
            'formattedUniqueValues',
            'currentDelimiter',
            'processRoute'
        ))->with([
            'area6ColumnHints' => $area6ColumnHints,
            'initialArea6Selections' => $initialArea6Selections,
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

        $filePath = $this->authorizeImportSourceFile((string) $request->input('file_path'));
        $selectedColumns = $request->input('selected_columns');
        $activeFilters = json_decode($request->input('active_filters_json'), true) ?: [];
        $currentDelimiter = $request->input('delimiter', 'auto');
        
        // 1. DETEKSI NAMA REPORT DARI DATABASE
        $idReport = session('active_id_report', 1);
        $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
        $this->releaseSessionLockIfNeeded();

        // 2. PENENTUAN TABEL & SUFFIX SECARA DINAMIS
        // Prioritaskan nama_report.table_name agar target import mengikuti report yang dipilih.
        $tableName = $this->resolveTableName($reportData);
        $uniqueSuffix = $this->resolveUniqueSuffix($tableName);

        if ($reportData) {
            $namaReport = $reportData->nama_report ?? '';

            // Cek apakah laporan ini adalah User Brimo FIN
            if (stripos($namaReport, 'fin') !== false) {
                $tableName = $this->resolveTableName($reportData);
                $uniqueSuffix = $this->resolveUniqueSuffix($tableName);
            } else {
                // Semua laporan Brimo lainnya (RPT V2, dll) → user_brimo_rpt_v2
                $tableName = $this->resolveTableName($reportData);
                $uniqueSuffix = $this->resolveUniqueSuffix($tableName);
            }
        }

        // Validasi keberadaan tabel di database untuk mencegah error query
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            Log::error("Tabel tujuan import Brimo tidak ditemukan: " . $tableName);
            return $request->expectsJson()
                ? response()->json(['status' => 'error', 'title' => 'Gagal!', 'text' => "Tabel $tableName tidak ditemukan di database."])
                : redirect()->route('import.index')->with('error', "Tabel $tableName tidak ditemukan di database.");
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

        $dataToInsert = [];
        $csvHeaders = [];
        $posisiIndex = -1;
        $tahunIndex = -1;
        $periodeIndex = -1;
        $writableColumns = $this->writableColumnsForTable($tableName);

        if (($handle = fopen($filePath, "r")) !== FALSE) {
            if ($currentDelimiter === 'auto') {
                $firstLine = fgets($handle);
                $delimiters = [',' => 0, ';' => 0, '|' => 0, "\t" => 0];
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

                if ($rowCounter == 0) {
                    $csvHeaders = array_map(function($val) {
                        return trim(preg_replace('/[\xef\xbb\xbf]/', '', $val));
                    }, $data);
                    
                    foreach ($csvHeaders as $idx => $hdr) {
                        if (stripos($hdr, 'posisi') !== false) { $posisiIndex = $idx; }
                        if (stripos($hdr, 'tahun') !== false) { $tahunIndex = $idx; }
                        if (stripos($hdr, 'periode') !== false) { $periodeIndex = $idx; }
                    }
                    
                    $rowCounter++;
                    continue; 
                }

                if (trim($data[0]) === 'TAHUN' || stripos(trim($data[0]), 'textbox') !== false) continue;

                if (count($data) < count($csvHeaders)) {
                    $data = array_pad($data, count($csvHeaders), null);
                }

                if (count($data) > count($csvHeaders)) {
                    Log::warning('Kolom tidak sesuai pada Import Brimo', [
                        'expected' => count($csvHeaders),
                        'actual' => count($data),
                        'row' => $data
                    ]);
                    continue; 
                }

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

                // FILTER AKTIF
                $passFilter = true;
                foreach ($activeFilters as $colIdx => $allowedValues) {
                    $cellValue = isset($data[$colIdx]) ? trim($data[$colIdx]) : '';
                    if (!in_array($cellValue, $allowedValues)) {
                        $passFilter = false;
                        break;
                    }
                }

                if (!$passFilter) {
                    $rowCounter++;
                    continue;
                }

                // DYNAMIC MAPPING
                $rowData = [];
                $rowData['uniqueid_namareport'] = uniqid() . $uniqueSuffix;

                foreach ($selectedColumns as $index) {
                    if (!isset($csvHeaders[$index])) continue;
                    
                    $colName = $this->normalizeBrimoHeader((string) $csvHeaders[$index]);

                    if ($colName === 'id' || $colName === 'uniqueid_namareport' || !isset($writableColumns[$colName])) {
                        continue;
                    }

                    $cellValue = isset($data[$index]) ? trim($data[$index]) : '';

                    if ($this->isBrimoNumericColumn($colName)) {
                        $cellValue = $this->normalizeBrimoDecimalValue($cellValue);
                    }

                    if ($colName === 'periode') {
                        $cellValue = $this->normalizeBrimoPeriodValue(
                            $cellValue,
                            $posisiIndex !== -1 ? ($data[$posisiIndex] ?? null) : null,
                            $tahunIndex !== -1 ? ($data[$tahunIndex] ?? null) : null
                        );
                    }

                    $rowData[$colName] = ($cellValue === '') ? null : $cellValue;
                }

                if ((!array_key_exists('periode', $rowData) || $rowData['periode'] === null || $rowData['periode'] === '') && $periodeIndex !== -1) {
                    $rowData['periode'] = $this->normalizeBrimoPeriodValue(
                        $data[$periodeIndex] ?? null,
                        $posisiIndex !== -1 ? ($data[$posisiIndex] ?? null) : null,
                        $tahunIndex !== -1 ? ($data[$tahunIndex] ?? null) : null
                    );
                }
                
                if ($this->hasMeaningfulImportData($rowData, ['uniqueid_namareport', 'periode', 'posisi'])) {
                    $dataToInsert[] = $rowData;
                }
                $rowCounter++;
            }
            fclose($handle);
        }

        // PENGECEKAN DUPLIKAT DATA
        $samplePeriode = $dataToInsert[0]['periode'] ?? null;
        $samplePosisi = $dataToInsert[0]['posisi'] ?? null;

        $isDuplicate = false;
        $duplicateText = '';

        if (in_array($tableName, ['user_brimo_rpt_v2', 'user_brimo_fin'])) {
            if ($samplePosisi) {
                $isDuplicate = SargableDateFilter::apply(DB::table($tableName), 'posisi', '=', $samplePosisi)->exists();
                if ($isDuplicate) {
                    $duplicateText = "Data untuk tanggal POSISI <b>$samplePosisi</b> sudah pernah diunggah sebelumnya ke tabel <b class='text-uppercase'>$tableName</b>.<br><br>Sistem membatalkan proses ini.";
                }
            } elseif ($samplePeriode) {
                $isDuplicate = DB::table($tableName)->where('periode', $samplePeriode)->exists();
                if ($isDuplicate) {
                    $duplicateText = "Data untuk PERIODE <b>$samplePeriode</b> sudah pernah diunggah sebelumnya ke tabel <b class='text-uppercase'>$tableName</b>.<br><br>Sistem membatalkan proses ini.";
                }
            }
        } else {
            if ($samplePeriode) {
                $isDuplicate = DB::table($tableName)->where('periode', $samplePeriode)->exists();
                if ($isDuplicate) {
                    $duplicateText = "Data untuk PERIODE <b>$samplePeriode</b> sudah pernah diunggah sebelumnya ke tabel <b class='text-uppercase'>$tableName</b>.<br><br>Sistem membatalkan proses ini.";
                }
            } elseif ($samplePosisi) {
                $isDuplicate = SargableDateFilter::apply(DB::table($tableName), 'posisi', '=', $samplePosisi)->exists();
                if ($isDuplicate) {
                    $duplicateText = "Data untuk tanggal POSISI <b>$samplePosisi</b> sudah pernah diunggah sebelumnya ke tabel <b class='text-uppercase'>$tableName</b>.<br><br>Sistem membatalkan proses ini.";
                }
            }
        }

        if ($isDuplicate) {
            $this->cleanupAuthorizedImportDirectory($filePath);
            
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

        // CATAT KE IMPORT JOBS
        $jobId = app(\App\Services\Import\ImportProgressService::class)->createJob([
            'id_report' => $idReport,
            'file_name' => basename($filePath),
            'folder_path' => dirname($filePath),
            'status' => 'processing',
            'total_files' => count($dataToInsert),
            'created_by' => auth()->id() ?? 1,
            'job_context' => [
                'controller' => static::class,
                'mode' => 'brimo_import',
                'table_name' => $tableName,
                'file_hash' => sha1($filePath),
                'data_rows_count' => count($dataToInsert),
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\Schema::dropIfExists('import_mappings');

        $chunks = array_chunk($dataToInsert, 500);
        $totalSuccess = 0;
        $totalFailed = 0;
        $lastErrorMsg = '';
        $bulkLoadHandled = false;

        if (!empty($dataToInsert)) {
            $bulkColumns = array_values(array_keys($dataToInsert[0]));
            $bulkCsvPath = null;

            try {
                $bulkCsvPath = $this->writeBulkTempCsv($dataToInsert, $bulkColumns, $tableName, $jobId);
                $totalSuccess = $this->bulkLoadService()->loadCsvIntoMysqlChunked(
                    $bulkCsvPath,
                    $tableName,
                    $bulkColumns,
                    null,
                    8000,
                    count($dataToInsert),
                    true
                );
                $totalFailed = max(0, count($dataToInsert) - $totalSuccess);
                $bulkLoadHandled = true;
            } catch (\Throwable $e) {
                $lastErrorMsg = substr($e->getMessage(), 0, 800) . '...';
                Log::warning('Bulk import Brimo gagal, fallback ke insert batch: ' . $e->getMessage(), [
                    'table' => $tableName,
                    'job_id' => $jobId,
                ]);
            } finally {
                if ($bulkCsvPath !== null && file_exists($bulkCsvPath)) {
                    @unlink($bulkCsvPath);
                }
            }
        }

        if (!$bulkLoadHandled) {
            foreach ($chunks as $chunk) {
                $chunk = $this->allocateGapIdsForRows($tableName, $chunk);

                try {
                    DB::table($tableName)->insert($chunk);
                    $totalSuccess += count($chunk);
                } catch (\Exception $e) {
                    $totalFailed += count($chunk);
                    $lastErrorMsg = substr($e->getMessage(), 0, 800) . '...';

                    DB::table('failed_jobs')->insert([
                        'uuid' => (string) Str::uuid(),
                        'connection' => 'database',
                        'queue' => 'import_' . $tableName,
                        'payload' => json_encode(['error' => 'Batch failed. Showing 1 sample:', 'sample' => $chunk[0] ?? []]),
                        'exception' => $lastErrorMsg,
                        'failed_at' => now(),
                    ]);
                }
            }
        }

        $finalStatus = $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed';
        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => $finalStatus,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'updated_at' => now(),
        ]);

        if ($totalSuccess > 0) {
            try {
                $periodHint = $samplePeriode ?: $samplePosisi;
                app(\App\Services\Import\ImportCleanupService::class)->dispatchImportedJobSync($jobId, $tableName, $periodHint, static::class);
            } catch (\Throwable $e) {
                Log::warning('Gagal sinkronisasi data report setelah import Brimo: ' . $e->getMessage(), [
                    'job_id' => $jobId,
                    'table' => $tableName,
                ]);
            }
        }

        $this->cleanupAuthorizedImportDirectory($filePath);

        if ($totalFailed > 0) {
            $response = [
                'status' => 'warning',
                'title' => 'Import Memiliki Kendala!',
                'text' => "Berhasil: $totalSuccess baris.<br>Gagal: $totalFailed baris.<br><br><b>Info MySQL:</b><br><small class='text-danger'>" . htmlspecialchars($lastErrorMsg, ENT_QUOTES) . "</small>"
            ];

            return $request->expectsJson()
                ? response()->json($response)
                : redirect()->route('import.index')->with('sweet_warning', $response);
        }

        $response = [
            'status' => 'success',
            'title' => 'Berhasil!',
            'text' => "Sebanyak $totalSuccess baris data telah sukses masuk ke tabel <b class='text-uppercase'>$tableName</b>."
        ];

        return $request->expectsJson()
            ? response()->json($response)
            : redirect()->route('import.index')->with('sweet_success', $response);
    }
}
