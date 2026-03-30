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
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ChunkReadFilter implements IReadFilter
{
    private int $startRow = 0;
    private int $endRow = 0;

    public function setRows(int $startRow, int $chunkSize): void
    {
        $this->startRow = $startRow;
        $this->endRow = $startRow + $chunkSize;
    }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        if (($row == 1) || ($row >= $this->startRow && $row < $this->endRow)) {
            return true;
        }
        return false;
    }
}

class ImportExcelController extends Controller
{
    public function uploadExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $file = $request->file('file');

        if (!$file->isValid()) {
            return back()->with('error', 'Upload gagal, file tidak valid atau corrupt.');
        }

        if (!file_exists(Storage::path('excel_imports'))) {
            Storage::makeDirectory('excel_imports');
        }

        $path = $file->store('excel_imports');
        session(['excel_path' => $path]);

        return redirect()->route('import.excel.preview');
    }

    public function previewExcel(Request $request)
    {
        ini_set('memory_limit', '1024M'); // Limit Memory yang lebih stabil
        set_time_limit(0);

        $sessionPath = session('excel_path', $request->path);

        if (!$sessionPath) {
            return redirect()->route('import.index')->with('sweet_warning', [
                'title' => 'Sesi Berakhir',
                'text' => 'Sesi import telah habis atau file hilang. Silakan upload ulang file Excel.'
            ]);
        }

        $relativePath = urldecode($sessionPath);
        $path = Storage::path($relativePath);

        if (!file_exists($path)) {
            return redirect()->route('import.index')->with('sweet_warning', [
                'title' => 'File Tidak Ditemukan',
                'text' => 'File mungkin sudah terhapus oleh sistem atau belum tersimpan sempurna.'
            ]);
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $chunkFilter = new ChunkReadFilter();
        $chunkFilter->setRows(1, 200); 
        $reader->setReadFilter($chunkFilter);

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $headerIndex = null;
        foreach ($sheet as $i => $row) {
            if (in_array('PERIODE', array_map('strtoupper', $row))) {
                $headerIndex = $i;
                break;
            }
        }

        if ($headerIndex === null) {
            foreach ($sheet as $i => $row) {
                if (count(array_filter($row, fn($val) => trim((string)$val) !== '')) > 10) {
                    $headerIndex = $i;
                    break;
                }
            }
        }

        if ($headerIndex === null) {
            return back()->with('error', 'Header utama laporan tidak ditemukan (Format Excel tidak dikenali).');
        }

        $headers = $sheet[$headerIndex];

        $normalizedHeaders = [];
        foreach ($headers as $i => $h) {
            $normalizedHeaders[$i] = !empty(trim((string)$h)) ? trim((string)$h) : 'COL_' . $i;
        }

        $validIndexes = [];
        foreach ($normalizedHeaders as $i => $h) {
            if (!str_starts_with($h, 'COL_')) {
                $validIndexes[] = $i;
            }
        }

        $finalHeaders = [];
        foreach ($validIndexes as $i) {
            $finalHeaders[] = $normalizedHeaders[$i];
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $chunkFilter->setRows($headerIndex + 1, 2500); 
        $reader->setReadFilter($chunkFilter);
        $spreadsheetPreview = $reader->load($path);
        $sheetPreview = $spreadsheetPreview->getActiveSheet()->toArray(null, true, true, false);

        $cleanPreview = [];
        foreach ($sheetPreview as $rowIndex => $row) {
            if ($rowIndex <= $headerIndex || $rowIndex >= ($headerIndex + 2500)) continue;
            if (empty(array_filter($row, fn($val) => trim((string)$val) !== ''))) continue;

            $cleanRow = [];
            foreach ($validIndexes as $i) {
                $headerName = $normalizedHeaders[$i];
                $value = trim((string)($row[$i] ?? ''));

                if (is_numeric($value)) {
                    $value = rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
                    if ($value === '') $value = '0';
                }

                $cleanRow[$headerName] = $value;
            }
            $cleanPreview[] = $cleanRow;
        }
        $spreadsheetPreview->disconnectWorksheets();
        unset($spreadsheetPreview);

        $uniqueValues = [];
        foreach ($validIndexes as $i) {
            $uniqueValues[$i] = [];
        }

        $worksheetInfo = $reader->listWorksheetInfo($path);
        $totalRows = $worksheetInfo[0]['totalRows'];
        $chunkSizeFilter = 5000;

        for ($startRow = $headerIndex + 1; $startRow <= $totalRows; $startRow += $chunkSizeFilter) {
            $chunkFilter->setRows($startRow, $chunkSizeFilter);
            $reader->setReadFilter($chunkFilter);
            $spreadsheetFull = $reader->load($path);
            $sheetFull = $spreadsheetFull->getActiveSheet()->toArray(null, true, true, false);

            foreach ($sheetFull as $rowIndex => $row) {
                if ($rowIndex < $startRow || $rowIndex >= $startRow + $chunkSizeFilter) continue;
                if (empty(array_filter($row, fn($val) => trim((string)$val) !== ''))) continue;

                foreach ($validIndexes as $i) {
                    $val = trim((string)($row[$i] ?? ''));

                    if (strtoupper($val) === strtoupper($normalizedHeaders[$i])) continue;

                    if (is_numeric($val)) {
                        $val = rtrim(rtrim(number_format((float)$val, 2, '.', ''), '0'), '.');
                        if ($val === '') $val = '0';
                    }

                    if ($val === '') $val = '(Blank)';

                    if (count($uniqueValues[$i]) < 5000) {
                        $uniqueValues[$i][$val] = true;
                    }
                }
            }
            $spreadsheetFull->disconnectWorksheets();
            unset($spreadsheetFull);
        }

        $formattedUniqueValues = [];
        $filterIndex = 0;
        foreach ($validIndexes as $i) {
            $keys = array_keys($uniqueValues[$i]);
            
            usort($keys, function ($a, $b) {
                return strnatcmp($a, $b);
            });
            
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

    public function initExcelImport(Request $request)
    {
        $sessionPath = session('excel_path', $request->path);
        if (!$sessionPath) return response()->json(['status' => 'error', 'text' => 'Sesi berakhir. SIlakan upload ulang.']);
        
        $relativePath = urldecode($sessionPath);
        $path = Storage::path($relativePath);
        if (!file_exists($path)) return response()->json(['status' => 'error', 'text' => 'File tidak ditemukan di server.']);

        $idReport = session('active_id_report', 1);
        $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
        
        $tableName = 'jumlah_merchant_detail'; 
        if ($reportData && !empty($reportData->table_name)) {
            $tableName = $reportData->table_name;
        } elseif ($reportData) {
            $tableName = strtolower(str_replace(' ', '_', $reportData->nama_report));
        }

        if (!Schema::hasTable($tableName)) {
            $tableName = 'jumlah_merchant_detail'; 
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $chunkFilter = new ChunkReadFilter();
        $chunkFilter->setRows(1, 200);
        $reader->setReadFilter($chunkFilter);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $headerIndex = null;
        foreach ($sheet as $i => $row) {
            if (in_array('PERIODE', array_map('strtoupper', $row))) { $headerIndex = $i; break; }
        }
        if ($headerIndex === null) {
            foreach ($sheet as $i => $row) {
                if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) > 10) { $headerIndex = $i; break; }
            }
        }
        if ($headerIndex === null) return response()->json(['status'=>'error', 'text'=>'Header tidak ditemukan']);

        $worksheetInfo = $reader->listWorksheetInfo($path);
        $totalRows = $worksheetInfo[0]['totalRows'];
        $dataRowsCount = $totalRows - $headerIndex;

        $jobId = DB::table('import_jobs')->insertGetId([
            'id_report' => $idReport,
            'file_name' => basename($path),
            'folder_path' => dirname($path),
            'status' => 'processing',
            'total_files' => $dataRowsCount,
            'total_success' => 0,
            'total_failed' => 0,
            'created_by' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'job_id' => $jobId,
            'total_rows' => $totalRows,
            'header_index' => $headerIndex,
            'table_name' => $tableName,
            'file_path' => $relativePath
        ]);
    }

    // =========================================================================
    // 🔥 QUEUE ENGINE STEP 2: PROSES CHUNK DENGAN PROTEKSI FATAL ERROR
    // =========================================================================
    public function processExcelChunk(Request $request)
    {
        // 🔥 FIX 6: Batasi Limit Memori Spesifik & Hindari -1
        ini_set('memory_limit', '1024M');
        set_time_limit(0); 

        try {
            $jobId = $request->job_id;
            $startRow = $request->start_row;
            $chunkSize = $request->chunk_size;
            $headerIndex = $request->header_index;
            $tableName = $request->table_name;
            $activeFilters = json_decode($request->active_filters_json, true) ?: [];

            $relativePath = urldecode($request->file_path);
            $path = Storage::path($relativePath);

            // 🔥 FIX 4 & 6: VALIDASI FILE SAFETY GUARD
            if (!file_exists($path) || filesize($path) == 0) {
                return response()->json([
                    'status' => 'error',
                    'text' => 'File invalid / kosong / terhapus saat proses chunk berlangsung.'
                ], 500);
            }

            // 🔥 FIX 4: LOG TRACKING DI TITIK RAWAN
            Log::info('PROCESS CHUNK INITIATED', [
                'start_row' => $startRow,
                'chunk_size' => $chunkSize,
                'table' => $tableName
            ]);

            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false); // 🔥 FIX 5: OPTIMASI MEMORY UNTUK FILE 30MB+

            // ===================================
            // AMBIL HEADER (TERPROTEKSI TRY-CATCH)
            // ===================================
            $chunkFilter = new ChunkReadFilter();
            $chunkFilter->setRows($headerIndex, 1); 
            $reader->setReadFilter($chunkFilter);

            try {
                $spreadsheet = $reader->load($path);
            } catch (\Throwable $e) {
                Log::error('HEADER LOAD ERROR: ' . $e->getMessage());
                return response()->json(['status' => 'error', 'text' => 'Gagal membaca Header Excel: ' . $e->getMessage()], 500);
            }

            $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

            $normalizedHeaders = [];
            foreach ($sheet[$headerIndex] as $i => $h) {
                $normalizedHeaders[$i] = !empty(trim((string)$h)) ? trim((string)$h) : 'COL_' . $i;
            }

            $validIndexes = [];
            foreach ($normalizedHeaders as $i => $h) {
                if (!str_starts_with($h, 'COL_')) $validIndexes[] = $i;
            }
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            // KAMUS ALIAS AUTO-MAPPING
            $dbColumns = Schema::getColumnListing($tableName);
            $dbColsUpper = array_map('strtoupper', $dbColumns);
            $originalDbCols = array_combine($dbColsUpper, $dbColumns); 

            $aliasDictionary = [
                'NOMOR_REKENING'    => 'NO_REKENING',
                'REKENING'          => 'NO_REKENING',
                'NAMA_DEBITUR'      => 'NAMA_NASABAH',
                'CIF'               => 'CIFNO',
                'SALDO'             => 'SALDO_IDR',
                'OUTLET_CODE'       => 'KODE_UKER',
                'OUTLET_NAME'       => 'NAMA_UKER',
                'MERCHANT_NAME'     => 'NAMA_MERCHANT',
                'NAMA_KCI'          => 'CABANG',
                'NAMA_KANCA'        => 'CABANG'
            ];

            $uniqueSuffix = '_EXCEL';
            if ($tableName === 'daily_loan_dinamis') $uniqueSuffix = '_DLD';
            elseif ($tableName === 'simpanan_multipn') $uniqueSuffix = '_SimoPN';
            elseif ($tableName === 'brilink_web_laporan_summary_transaksi_brilink_web') $uniqueSuffix = '_BST';

            $uniqueCol = null;
            foreach ($dbColsUpper as $col) {
                if (str_starts_with($col, 'UNIQUEID_')) {
                    $uniqueCol = $originalDbCols[$col]; break;
                }
            }

            // ===================================
            // AMBIL DATA CHUNK (TERPROTEKSI TRY-CATCH)
            // ===================================
            $chunkFilter->setRows($startRow, $chunkSize);
            $reader->setReadFilter($chunkFilter);

            try {
                $spreadsheet = $reader->load($path);
            } catch (\Throwable $e) {
                Log::error('DATA CHUNK LOAD ERROR: ' . $e->getMessage());
                return response()->json(['status' => 'error', 'text' => 'Gagal membaca Data Excel Chunk: ' . $e->getMessage()], 500);
            }

            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

            $chunkInsert = [];
            $totalSuccess = 0;
            $totalFailed = 0;

            foreach ($sheetData as $rowIndex => $row) {
                if ($rowIndex < $startRow || $rowIndex >= $startRow + $chunkSize) continue;
                if (empty(array_filter($row, fn($val) => trim((string)$val) !== ''))) continue;

                $mappedRow = [];
                $passFilter = true;
                $isHeaderNyasar = false;

                // Menggunakan array murni $validIndexes tanpa counter increment manual
                foreach ($validIndexes as $filterIdx => $originalIndex) {
                    $headerName = $normalizedHeaders[$originalIndex];
                    $val = trim((string)($row[$originalIndex] ?? ''));

                    if (strtoupper($val) === strtoupper($headerName)) {
                        $isHeaderNyasar = true; break;
                    }

                    if (is_numeric($val)) {
                        $val = rtrim(rtrim(number_format((float)$val, 2, '.', ''), '0'), '.');
                        if ($val === '') $val = '0';
                    }

                    $filterVal = $val === '' ? '(Blank)' : $val;

                    if (isset($activeFilters[$filterIdx]) && !in_array($filterVal, $activeFilters[$filterIdx])) {
                        $passFilter = false; break;
                    }
                    $mappedRow[$headerName] = $val;
                }

                if ($isHeaderNyasar || !$passFilter) continue;

                $rowData = [];
                if ($uniqueCol) {
                    $rowData[$uniqueCol] = uniqid() . $uniqueSuffix;
                }

                foreach ($mappedRow as $key => $val) {
                    $cleanKey = preg_replace('/[^A-Za-z0-9]/', '_', trim((string)$key));
                    $cleanKey = preg_replace('/_+/', '_', $cleanKey);
                    $colNameUpper = strtoupper(trim($cleanKey, '_'));

                    if (isset($aliasDictionary[$colNameUpper])) {
                        $colNameUpper = strtoupper($aliasDictionary[$colNameUpper]);
                    }

                    if (isset($originalDbCols[$colNameUpper])) {
                        $realColName = $originalDbCols[$colNameUpper];

                        if (strtoupper($realColName) === 'ID' || str_starts_with(strtoupper($realColName), 'UNIQUEID_')) continue;

                        if (str_contains(strtoupper($realColName), 'REKENING')) {
                            $val = preg_replace('/[^0-9]/', '', (string)$val);
                        }

                        $rowData[$realColName] = $val === '' ? null : $val;
                    }
                }

                if (isset($originalDbCols['CREATED_AT'])) $rowData[$originalDbCols['CREATED_AT']] = now();
                if (isset($originalDbCols['UPDATED_AT'])) $rowData[$originalDbCols['UPDATED_AT']] = now();

                if (!empty($rowData)) {
                    $chunkInsert[] = $rowData;
                }
            }

            if (!empty($chunkInsert)) {
                try {
                    DB::table($tableName)->insert($chunkInsert);
                    $totalSuccess = count($chunkInsert);
                } catch (\Exception $e) {
                    $totalFailed = count($chunkInsert);
                    Log::error('Excel Import Chunk Insert Error: ' . $e->getMessage());
                    return response()->json(['status' => 'error', 'text' => 'Gagal menyisipkan baris di database: ' . $e->getMessage()], 500);
                }
            }

            DB::table('import_jobs')->where('id', $jobId)->update([
                'total_success' => DB::raw("total_success + $totalSuccess"),
                'total_failed' => DB::raw("total_failed + $totalFailed"),
                'updated_at' => now()
            ]);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return response()->json(['status' => 'success', 'inserted' => $totalSuccess, 'failed' => $totalFailed]);

        // 🔥 FIX 1: FORCE JSON RESPONSE UNTUK FATAL ERROR SERVER SEKALIPUN
        } catch (\Throwable $e) {
            Log::error('FATAL CHUNK ERROR: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'text' => 'Fatal Error System: ' . $e->getMessage()
            ], 500);
        }
    }

    public function finishExcelImport(Request $request)
    {
        $jobId = $request->job_id;
        $relativePath = urldecode($request->file_path);

        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        
        $finalStatus = $job->total_failed > 0 ? ($job->total_success > 0 ? 'failed_partial' : 'failed') : 'completed';
        
        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => $finalStatus,
            'updated_at' => now()
        ]);

        if ($relativePath) {
            @unlink(Storage::path($relativePath));
            session()->forget('excel_path');
        }

        return response()->json([
            'status' => 'success',
            'total_success' => $job->total_success,
            'total_failed' => $job->total_failed
        ]);
    }
}