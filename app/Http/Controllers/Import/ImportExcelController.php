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
        session(['excel_path' => $path]);
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

        $headerIndex = null;
        foreach ($sheet as $i => $row) {
            $rowUpper = array_map(function($v) { return strtoupper(trim((string)$v)); }, $row);
            if (in_array('PERIODE', $rowUpper)) {
                $headerIndex = $i;
                break;
            }
        }
        if ($headerIndex === null) return back()->with('error', 'Header utama (PERIODE) tidak ditemukan.');

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

        $idReport = session('active_id_report', 1);
        $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
        
        // Pilih tabel berdasarkan kategori report
        $tableName = 'daily_loan_dinamis'; 
        if ($reportData && !empty($reportData->table_name)) {
            $tableName = $reportData->table_name;
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
        foreach ($sheet as $i => $row) {
            if (in_array('PERIODE', array_map(function($v){return strtoupper(trim((string)$v));}, $row))) {
                $headerIndex = $i; break;
            }
        }
        if ($headerIndex === null) return response()->json(['status'=>'error', 'text'=>'Header tidak ditemukan']);

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
            $jobId = $request->job_id;
            $startIndex = (int) $request->start_row; 
            $chunkSize = (int) $request->chunk_size;
            $headerIndex = (int) $request->header_index;
            $tableName = $request->table_name;
            $activeFilters = json_decode($request->active_filters_json, true) ?: [];
            $relativePath = urldecode($request->file_path);
            $path = Storage::path($relativePath);

            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false); 

            // Ambil Header
            $chunkFilter = new ChunkReadFilter();
            $chunkFilter->setRows($headerIndex + 1, 1); 
            $reader->setReadFilter($chunkFilter);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            
            $rawHeaders = $sheet[$headerIndex];
            $normalizedHeaders = [];
            foreach ($rawHeaders as $i => $h) {
                $normalizedHeaders[$i] = !empty(trim((string)$h)) ? trim((string)$h) : 'COL_' . $i;
            }
            $validIndexes = [];
            foreach ($normalizedHeaders as $i => $h) {
                if (!str_starts_with($h, 'COL_')) $validIndexes[] = $i;
            }
            $headerCount = max(array_keys($normalizedHeaders)) + 1;
            $spreadsheet->disconnectWorksheets(); unset($spreadsheet);

            // Baca Data Chunk
            $chunkFilter->setRows($startIndex + 1, $chunkSize);
            $reader->setReadFilter($chunkFilter);
            $spreadsheet = $reader->load($path);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

            $chunkInsert = [];
            $totalSuccess = 0; $totalFailed = 0;

            foreach ($sheetData as $rowIndex => $row) {
                if ($rowIndex < $startIndex || $rowIndex >= $startIndex + $chunkSize) continue;
                if (empty(array_filter($row, fn($val) => trim((string)$val) !== ''))) continue;

                $row = $this->padRow($row, $headerCount);
                $mappedExcelData = [];
                $passFilter = true;

                // Loop index Excel
                foreach ($validIndexes as $filterIdx => $originalIndex) {
                    $hName = $normalizedHeaders[$originalIndex];
                    $val = $this->normalizeExcelValue($hName, $row[$originalIndex] ?? '');

                    // Proteksi jika baris header terbaca lagi
                    if (strtoupper((string)$val) === strtoupper($hName)) { $passFilter = false; break; }

                    // Cek Filter UI
                    if (!empty($activeFilters) && isset($activeFilters[$filterIdx])) {
                        $fVal = ($val === null) ? '(Blank)' : $val;
                        if (!in_array($fVal, $activeFilters[$filterIdx])) { $passFilter = false; break; }
                    }
                    $mappedExcelData[strtoupper(str_replace(' ', '_', $hName))] = $val;
                }

                if (!$passFilter) continue;

                // =========================================================
                // 🔥 MAPPING MANUAL EKSPLISIT (Sesuai Struktur Tabel Anda)
                // =========================================================
                $finalRow = [];
                
                if ($tableName === 'daily_loan_dinamis') {
                    $finalRow = [
                        'uniqueid_namareport' => uniqid('', true) . '_DLD',
                        'periode'             => $mappedExcelData['PERIODE'] ?? null,
                        'kode_kanwil'         => $mappedExcelData['KODE_KANWIL'] ?? null,
                        'kanwil'              => $mappedExcelData['KANWIL'] ?? null,
                        'kode_cabang'         => $mappedExcelData['KODE_CABANG'] ?? null,
                        'cabang'              => $mappedExcelData['CABANG'] ?? null,
                        'branch'              => $mappedExcelData['BRANCH'] ?? null,
                        'unit'                => $mappedExcelData['UNIT'] ?? null,
                        'ao_name'             => $mappedExcelData['AO_NAME'] ?? null,
                        'cifno'               => $mappedExcelData['CIFNO'] ?? $mappedExcelData['CIF'] ?? null,
                        'nomor_rekening'      => preg_replace('/[^0-9]/', '', $mappedExcelData['NOMOR_REKENING'] ?? $mappedExcelData['REKENING'] ?? ''),
                        'segmen_dashboard'    => $mappedExcelData['SEGMEN_DASHBOARD'] ?? null,
                        'produk_dashboard'    => $mappedExcelData['PRODUK_DASHBOARD'] ?? null,
                        'created_at'          => now(),
                        'updated_at'          => now()
                    ];
                } 
                elseif ($tableName === 'simpanan_multipn') {
                    $finalRow = [
                        'uniqueid_namareport' => uniqid('', true) . '_SimoPN',
                        'posisi'              => $mappedExcelData['POSISI'] ?? null,
                        'regional_office'     => $mappedExcelData['REGIONAL_OFFICE'] ?? null,
                        'kantor_cabang'       => $mappedExcelData['KANTOR_CABANG'] ?? null,
                        'unit_kerja'          => $mappedExcelData['UNIT_KERJA'] ?? null,
                        'CIFNO'               => $mappedExcelData['CIFNO'] ?? $mappedExcelData['CIF'] ?? null,
                        'no_rekening'         => preg_replace('/[^0-9]/', '', $mappedExcelData['NO_REKENING'] ?? $mappedExcelData['REKENING'] ?? ''),
                        'jenis_simpanan'      => $mappedExcelData['JENIS_SIMPANAN'] ?? null,
                        'saldo_idr'           => $mappedExcelData['SALDO_IDR'] ?? $mappedExcelData['SALDO'] ?? 0,
                        'created_at'          => now(),
                        'updated_at'          => now()
                    ];
                }

                if (!empty($finalRow)) $chunkInsert[] = $finalRow;
            }

            // Safe Batch Insert
            if (!empty($chunkInsert)) {
                try {
                    DB::table($tableName)->insert($chunkInsert);
                    $totalSuccess += count($chunkInsert);
                } catch (\Exception $e) {
                    // Fallback per baris jika batch gagal (misal karena constraint mysql)
                    foreach ($chunkInsert as $single) {
                        try { DB::table($tableName)->insert($single); $totalSuccess++; }
                        catch (\Exception $e2) { $totalFailed++; }
                    }
                }
            }

            DB::table('import_jobs')->where('id', $jobId)->update([
                'total_success' => DB::raw("total_success + $totalSuccess"),
                'total_failed'  => DB::raw("total_failed + $totalFailed"),
                'updated_at'    => now()
            ]);

            $spreadsheet->disconnectWorksheets(); unset($spreadsheet);
            return response()->json(['status' => 'success', 'inserted' => $totalSuccess, 'failed' => $totalFailed]);

        } catch (\Throwable $e) {
            Log::error('FATAL CHUNK ERROR: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'text' => 'Fatal Error System: ' . $e->getMessage()], 500);
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
        }

        return response()->json([
            'status' => 'success',
            'total_success' => $job->total_success ?? 0,
            'total_failed' => $job->total_failed ?? 0
        ]);
    }
}