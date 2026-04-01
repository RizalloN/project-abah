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

        $uniqueIdCol = str_contains($tableName, 'simpanan') ? 'uniqueid_SimoPN' : 'uniqueid_namareport';
        $suffix = str_contains($tableName, 'simpanan') ? '_SimoPN' : '_DLD';
        $skipColumnsLookup = array_fill_keys(['id', strtolower($uniqueIdCol)], true);

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

            $mappedExcelData[strtolower(str_replace(' ', '_', $headerName))] = $value;
        }

        $finalRow = [
            $context['unique_id_col'] => uniqid('', true) . $context['suffix'],
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
            $finalRow[$dbCol] = $value;
        }

        return count($finalRow) > 3 ? $finalRow : null;
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

    private function normalizeExcelValue(string $headerName, $value)
    {
        $header = strtoupper(trim($headerName));
        $normalizedHeader = preg_replace('/[^A-Z0-9]+/', '_', $header);
        $value = ($value === null) ? '' : trim((string) $value);

        if ($value === '') return null;

        $dateColumns = ['PERIODE', 'POSISI', 'TGL_REALISASI', 'TGL_JATUH_TEMPO', 'TANGGAL'];
        if (in_array($normalizedHeader, $dateColumns, true)) {
            try {
                if (is_numeric($value)) {
                    return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('Y-m-d');
                }
                return Carbon::parse(str_replace('/', '-', $value))->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        $decimalColumns = ['BAKI_DEBET'];
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

    public function uploadExcel(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);
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
        request()->session()->save();

        return response()->stream(function () use ($sessionPath, $cacheKey) {
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

                $send('progress', ['percent' => 5, 'message' => 'Membaca file Excel (single-pass)...', 'step' => 1]);

                $reader = IOFactory::createReaderForFile($path);
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);

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
                        foreach ($sheet as $i => $row) {
                            $rowUpper = array_map(fn($v) => strtoupper(trim((string) $v)), $row);
                            if (in_array('PERIODE', $rowUpper) || in_array('POSISI', $rowUpper)) {
                                $headerIndex = $i;
                                $rawHeaders = $sheet[$headerIndex];

                                foreach ($rawHeaders as $colIdx => $h) {
                                    $normalizedHeaders[$colIdx] = !empty(trim((string)$h)) ? trim((string)$h) : 'COL_' . $colIdx;
                                }
                                foreach ($normalizedHeaders as $colIdx => $h) {
                                    if (!str_starts_with($h, 'COL_')) $validIndexes[] = $colIdx;
                                }
                                foreach ($validIndexes as $colIdx) $finalHeaders[] = $normalizedHeaders[$colIdx];
                                
                                $headerCount = max(array_keys($normalizedHeaders)) + 1;
                                foreach ($validIndexes as $colIdx) $uniqueValues[$colIdx] = [];

                                $send('progress', ['percent' => 35, 'message' => 'Header ditemukan di baris ' . ($headerIndex + 1) . '. Memproses data...', 'step' => 3]);
                                break;
                            }
                        }
                        if ($headerIndex === null && $startRow + $chunkSize > 200) {
                            $send('error_msg', ['message' => 'Header utama (PERIODE / POSISI) tidak ditemukan dalam 200 baris pertama.']);
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
                    $send('error_msg', ['message' => 'Header utama (PERIODE / POSISI) tidak bisa ditemukan di file.']);
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

                $tableName = 'daily_loan_dinamis';
                $idReport = session('active_id_report');
                if ($idReport && ($reportData = DB::table('nama_report')->where('id_report', $idReport)->first())) {
                    if(!empty($reportData->table_name)) $tableName = $reportData->table_name;
                }
                $dbColumns = Schema::getColumnListing($tableName);

                $headerMap = [];
                foreach ($finalHeaders as $h) {
                    $normalized = strtolower(str_replace(' ', '_', $h));
                    if (in_array($normalized, $dbColumns)) $headerMap[$h] = $normalized;
                }

                $orderedHeaders = [];
                $orderedUniqueValues = [];
                foreach ($dbColumns as $dbCol) {
                    $excelHeader = array_search($dbCol, $headerMap);
                    if ($excelHeader !== false) {
                        $orderedHeaders[] = $excelHeader;
                        $originalIndex = array_search($excelHeader, $finalHeaders);
                        if(isset($formattedUniqueValues[$originalIndex])) {
                            $orderedUniqueValues[] = $formattedUniqueValues[$originalIndex];
                        }
                        unset($headerMap[$excelHeader]);
                    }
                }
                foreach ($headerMap as $excelH => $mapCol) {
                    $orderedHeaders[] = $excelH;
                    $originalIndex = array_search($excelH, $finalHeaders);
                    if(isset($formattedUniqueValues[$originalIndex])) {
                        $orderedUniqueValues[] = $formattedUniqueValues[$originalIndex];
                    }
                }
                $finalHeaders = $orderedHeaders;
                $formattedUniqueValues = $orderedUniqueValues;

                foreach ($cleanPreview as &$row) {
                    $newRow = [];
                    foreach ($finalHeaders as $h) $newRow[$h] = $row[$h] ?? null;
                    $row = $newRow;
                }
                unset($row);

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
                    'redirect' => route('import.excel.preview', ['ck' => $useCacheKey]),
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

        $ck = $request->query('ck');
        if ($ck) {
            $cached = Cache::get($ck);
            if ($cached && is_array($cached)) {
                Cache::forget($ck);
                return view('import.preview_excel', $cached);
            }
        }

        $sessionPath = session('excel_path', $request->path);
        if (!$sessionPath) return redirect()->route('import.index')->with('sweet_warning', ['title' => 'Sesi Berakhir', 'text' => 'Silakan upload ulang.']);

        $relativePath = urldecode($sessionPath);
        $path = Storage::path($relativePath);
        if (!file_exists($path)) return redirect()->route('import.index')->with('sweet_warning', ['title' => 'File Tidak Ditemukan', 'text' => 'File mungkin sudah terhapus.']);

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $chunkFilter = new ChunkReadFilter();
        $chunkFilter->setRows(1, 200);
        $reader->setReadFilter($chunkFilter);

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $headerIndex = null;
        foreach ($sheet as $i => $row) {
            $rowUpper = array_map(fn($v) => strtoupper(trim((string) $v)), $row);
            if (in_array('PERIODE', $rowUpper) || in_array('POSISI', $rowUpper)) {
                $headerIndex = $i;
                break;
            }
        }
        if ($headerIndex === null) return back()->with('error', 'Header utama (PERIODE / POSISI) tidak ditemukan dalam 200 baris pertama.');

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
            'path' => $relativePath
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
        $tableName = 'daily_loan_dinamis';
        if ($idReport) {
            $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
            if ($reportData && !empty($reportData->table_name)) {
                $tableName = $reportData->table_name;
            }
        }

        // Pastikan schema minimum tersedia agar import tidak terlihat "sukses"
        // padahal kolom penting untuk report belum ada di database.
        if ($tableName === 'daily_loan_dinamis' && !Schema::hasColumn($tableName, 'baki_debet')) {
            return response()->json([
                'status' => 'error',
                'text' => 'Kolom wajib `baki_debet` belum tersedia di tabel daily_loan_dinamis. Jalankan migration terlebih dahulu lalu upload ulang file Excel.',
            ], 422);
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
