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
        // Always read the designated header row
        if ($row == $this->headerRow) return true;
        // Read rows within the chunk window
        if ($row >= $this->startRow && $row < $this->endRow) return true;
        return false;
    }
}

class ImportExcelController extends Controller
{
    // =========================================================================
    // 🔥 PEMBERSIH & FORMATTER (MANUAL & STABIL)
    // =========================================================================
    
    private function normalizeExcelValue(string $headerName, $value)
    {
        $header = strtoupper(trim($headerName));
        $value = ($value === null) ? '' : trim((string) $value);

        if ($value === '') return null;

        // Kolom Tanggal (Wajib Date Y-m-d)
        $dateColumns = ['PERIODE', 'POSISI', 'TGL_REALISASI', 'TGL_JATUH_TEMPO', 'TANGGAL'];
        if (in_array($header, $dateColumns)) {
            try {
                if (is_numeric($value)) {
                    return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('Y-m-d');
                }
                return Carbon::parse(str_replace('/', '-', $value))->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        // Kolom Numerik (Pembersihan desimal dan string)
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

    // =========================================================================
    // 🔥 UPLOAD & PREVIEW ENGINE
    // =========================================================================

    public function uploadExcel(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);
        $file = $request->file('file');
        if (!$file->isValid()) return back()->with('error', 'Upload gagal, file tidak valid.');
        
        if (!file_exists(Storage::path('excel_imports'))) {
            Storage::makeDirectory('excel_imports');
        }

        $path = $file->store('excel_imports');
        session([
            'excel_path'       => $path,
            'active_id_report' => $request->id_report, // simpan id_report agar initExcelImport tahu tabel tujuan
        ]);
        return redirect()->route('import.excel.preview');
    }

    public function previewExcel(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

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

        // Detect header row: look for PERIODE (Daily Loan) or POSISI (Simpanan MultiPN)
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

        // Preview Display (Max 100 Row)
        $chunkFilter->setRows($headerIndex + 1, 100); 
        $reader->setReadFilter($chunkFilter);
        $spreadsheetPreview = $reader->load($path);
        $sheetPreview = $spreadsheetPreview->getActiveSheet()->toArray(null, true, true, false);

        $cleanPreview = [];
        $headerCount = max(array_keys($normalizedHeaders)) + 1;

        foreach ($sheetPreview as $rowIndex => $row) {
            if ($rowIndex <= $headerIndex) continue;
            if (empty(array_filter($row, fn($val) => trim((string)$val) !== ''))) continue;

            $row = $this->padRow($row, $headerCount);
            $cleanRow = [];
            foreach ($validIndexes as $i) {
                $cleanRow[$normalizedHeaders[$i]] = $this->normalizeExcelValue($normalizedHeaders[$i], $row[$i] ?? '');
            }
            $cleanPreview[] = $cleanRow;
        }
        $spreadsheetPreview->disconnectWorksheets();
        unset($spreadsheetPreview);

        // Build Filter Dropdown (Max 5000 Row)
        $uniqueValues = [];
        foreach ($validIndexes as $i) $uniqueValues[$i] = [];

        $chunkFilter->setRows($headerIndex + 1, 5000); 
        $reader->setReadFilter($chunkFilter);
        $spreadsheetFull = $reader->load($path);
        $sheetFull = $spreadsheetFull->getActiveSheet()->toArray(null, true, true, false);

        foreach ($sheetFull as $rowIndex => $row) {
            if ($rowIndex <= $headerIndex) continue;
            if (empty(array_filter($row, fn($val) => trim((string)$val) !== ''))) continue;
            $row = $this->padRow($row, $headerCount);

            foreach ($validIndexes as $i) {
                $val = $this->normalizeExcelValue($normalizedHeaders[$i], $row[$i] ?? '');
                if ($val === null) $val = '(Blank)';
                $uniqueValues[$i][$val] = true;
            }
        }
        $spreadsheetFull->disconnectWorksheets();
        unset($spreadsheetFull);

        $formattedUniqueValues = [];
        $filterIndex = 0;
        foreach ($validIndexes as $i) {
            $keys = array_keys($uniqueValues[$i]);
            usort($keys, function ($a, $b) { return strnatcmp($a, $b); });
            $formattedUniqueValues[$filterIndex] = $keys;
            $filterIndex++;
        }

        return view('import.preview_excel', [
            'headers' => $finalHeaders,
            'preview' => $cleanPreview,
            'formattedUniqueValues' => $formattedUniqueValues,
            'path' => $relativePath
        ]);
    }

    // =========================================================================
    // 🔥 QUEUE ENGINE (MAPPING MANUAL & EKSPLISIT)
    // =========================================================================

    public function initExcelImport(Request $request)
    {
        $sessionPath = session('excel_path', $request->path);
        if (!$sessionPath) return response()->json(['status' => 'error', 'text' => 'Sesi berakhir.']);
        
        $relativePath = urldecode($sessionPath);
        $path = Storage::path($relativePath);
        if (!file_exists($path)) return response()->json(['status' => 'error', 'text' => 'File tidak ditemukan.']);

        // Ambil id_report dari session (disimpan saat uploadExcel).
        // Default null agar tidak salah mapping ke id_report=1 (jumlah_merchant_detail).
        $idReport  = session('active_id_report');
        $tableName = 'daily_loan_dinamis'; // default fallback
        if ($idReport) {
            $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
            if ($reportData && !empty($reportData->table_name)) {
                $tableName = $reportData->table_name;
            }
        }

        // Gunakan setReadEmptyCells(false) agar konsisten dengan previewExcel()
        // sehingga header yang disimpan ke session sama persis dengan yang ditampilkan di preview.
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $chunkFilter = new ChunkReadFilter();
        $chunkFilter->setRows(1, 200);
        $reader->setReadFilter($chunkFilter);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        // Detect header row: PERIODE (Daily Loan Dinamis) or POSISI (Simpanan MultiPN)
        $headerIndex = null;
        foreach ($sheet as $i => $row) {
            $rowUpper = array_map(fn($v) => strtoupper(trim((string) $v)), $row);
            if (in_array('PERIODE', $rowUpper) || in_array('POSISI', $rowUpper)) {
                $headerIndex = $i;
                break;
            }
        }
        if ($headerIndex === null) return response()->json(['status' => 'error', 'text' => 'Header tidak ditemukan (PERIODE / POSISI).']);

        $worksheetInfo = $reader->listWorksheetInfo($path);
        $totalRows = $worksheetInfo[0]['totalRows'];
        $dataRowsCount = $totalRows - ($headerIndex + 1);

        $jobId = DB::table('import_jobs')->insertGetId([
            'id_report'   => $idReport,
            'file_name'   => basename($path),
            'folder_path' => dirname($path),
            'status'      => 'processing',
            'total_files' => $dataRowsCount,
            'total_success' => 0,
            'total_failed'  => 0,
            'created_by'  => auth()->id() ?? 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Ambil header dari spreadsheet dan simpan ke session agar chunk tidak perlu re-read header
        $rawHeaders = $sheet[$headerIndex];
        $normalizedHeadersForSession = [];
        foreach ($rawHeaders as $i => $h) {
            $normalizedHeadersForSession[$i] = !empty(trim((string)$h)) ? trim((string)$h) : 'COL_' . $i;
        }
        session(['excel_headers' => $normalizedHeadersForSession]);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->json([
            'status' => 'success',
            'job_id' => $jobId,
            'total_rows' => $totalRows,
            'header_index' => $headerIndex,
            'table_name' => $tableName,
            'file_path' => $relativePath
        ]);
    }

    public function processExcelChunk(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        try {
            $jobId       = (int) $request->job_id;
            $headerIndex = (int) $request->header_index; // 0-based
            $tableName   = $request->table_name;
            $startRow    = max((int) $request->start_row, $headerIndex + 1); // 0-based data start
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

            $validIndexes = [];
            foreach ($normalizedHeaders as $i => $h) {
                if (!str_starts_with($h, 'COL_')) $validIndexes[] = $i;
            }
            $headerCount = max(array_keys($normalizedHeaders)) + 1;

            $tableColumnsRaw = Schema::getColumnListing($tableName);
            $tableColumns    = array_map('strtolower', $tableColumnsRaw);

            $uniqueIdCol = str_contains($tableName, 'simpanan') ? 'uniqueid_SimoPN' : 'uniqueid_namareport';
            $suffix      = str_contains($tableName, 'simpanan') ? '_SimoPN' : '_DLD';
            $skipCols    = ['id', strtolower($uniqueIdCol)];

            // 0-based requested window
            $endExclusive = $startRow + $chunkSize;

            // Convert to 1-based excel rows for read filter
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

            foreach ($sheet as $rowIndex => $row) {
                // rowIndex from toArray is 0-based
                if ($rowIndex < $startRow || $rowIndex >= $endExclusive) continue;
                if ($rowIndex <= $headerIndex) continue;
                if (empty(array_filter((array) $row, fn($v) => trim((string) $v) !== ''))) continue;

                $row = $this->padRow($row, $headerCount);
                $mappedExcelData = [];
                $passFilter = true;
                $debugRowsRead++;

                foreach ($validIndexes as $filterIdx => $originalIndex) {
                    $hName = $normalizedHeaders[$originalIndex];
                    $val   = $this->normalizeExcelValue($hName, $row[$originalIndex] ?? '');

                    if (!empty($activeFilters) && isset($activeFilters[$filterIdx])) {
                        $fVal = ($val === null) ? '(Blank)' : (string) $val;
                        if (!in_array($fVal, (array) $activeFilters[$filterIdx])) {
                            $passFilter = false;
                            break;
                        }
                    }

                    $mappedExcelData[strtoupper(str_replace(' ', '_', $hName))] = $val;
                }

                if (!$passFilter) continue;
                $debugPassed++;

                $finalRow = [$uniqueIdCol => uniqid('', true) . $suffix];

                foreach ($mappedExcelData as $excelKey => $val) {
                    $dbCol = strtolower($excelKey);
                    if (in_array($dbCol, $skipCols)) continue;
                    if (!in_array($dbCol, $tableColumns)) continue;
                    $finalRow[$dbCol] = $val;
                }

                $finalRow['created_at'] = now();
                $finalRow['updated_at'] = now();

                if ($sampleMapped === null) $sampleMapped = $finalRow;

                if (count($finalRow) > 3) $dataToInsert[] = $finalRow;
            }

            // Insert optimized per-request chunk (batches of 1000)
            foreach (array_chunk($dataToInsert, 1000) as $batch) {
                try {
                    DB::table($tableName)->insert($batch);
                    $chunkInserted += count($batch);
                } catch (\Exception $e) {
                    foreach ($batch as $single) {
                        try {
                            DB::table($tableName)->insert($single);
                            $chunkInserted++;
                        } catch (\Exception $e2) {
                            $chunkFailed++;
                        }
                    }
                }
            }

            // Incremental progress update to import_jobs for interactive progress
            DB::table('import_jobs')->where('id', $jobId)->update([
                'total_success' => DB::raw('total_success + ' . (int) $chunkInserted),
                'total_failed'  => DB::raw('total_failed + ' . (int) $chunkFailed),
                'updated_at'    => now(),
            ]);

            return response()->json([
                'status'          => 'success',
                'inserted'        => $chunkInserted,
                'failed'          => $chunkFailed,
                'processed_until' => $endExclusive, // 0-based exclusive
                'chunk_start'     => $startRow,
                'chunk_size'      => $chunkSize,
                'debug'           => [
                    'table'          => $tableName,
                    'table_columns'  => $tableColumnsRaw,
                    'excel_headers'  => array_values($normalizedHeaders),
                    'header_index'   => $headerIndex,
                    'rows_read'      => $debugRowsRead,
                    'rows_passed'    => $debugPassed,
                    'rows_inserted'  => $chunkInserted,
                    'active_filters' => $activeFilters,
                    'sample_row'     => $sampleMapped,
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('EXCEL CHUNK ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'status' => 'error',
                'text'   => 'Fatal Error: ' . $e->getMessage() . ' (line ' . $e->getLine() . ')',
            ], 500);
        }
    }

    public function finishExcelImport(Request $request)
    {
        $jobId = $request->job_id;
        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        $finalStatus = ($job->total_failed ?? 0) > 0 ? (($job->total_success ?? 0) > 0 ? 'failed_partial' : 'failed') : 'completed';
        
        DB::table('import_jobs')->where('id', $jobId)->update(['status' => $finalStatus, 'updated_at' => now()]);

        if ($request->file_path) {
            @unlink(Storage::path(urldecode($request->file_path)));
            session()->forget('excel_path');
            session()->forget('excel_headers');
        }

        return response()->json([
            'status' => 'success',
            'total_success' => $job->total_success ?? 0,
            'total_failed' => $job->total_failed ?? 0
        ]);
    }
}