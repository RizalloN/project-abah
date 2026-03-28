<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon; // 🔥 Wajib ditambahkan untuk parsing tanggal
use App\Models\NamaReport;

class ImportFileController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate(['id_report' => 'required', 'file' => 'required|file|mimes:rar']);
        $folderName = 'import_' . date('Ymd_His') . '_' . Str::random(5);
        $storagePath = storage_path('app/imports/' . $folderName);
        if (!file_exists($storagePath)) { mkdir($storagePath, 0777, true); }
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $file->move($storagePath, $fileName);
        $fullPath = $storagePath . '/' . $fileName;
        $extractPath = $storagePath . '/extracted';
        if (!file_exists($extractPath)) { mkdir($extractPath, 0777, true); }
        $command = '"C:\Program Files\7-Zip\7z.exe" x "' . $fullPath . '" -o"' . $extractPath . '" -y';
        exec($command);
        $files = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractPath));
        foreach ($rii as $fileItem) {
            if ($fileItem->isDir()) continue;
            $files[] = ['name' => $fileItem->getFilename(), 'path' => $fileItem->getPathname()];
        }
        session(['import_files' => $files, 'active_id_report' => $request->input('id_report')]);
        return redirect()->route('import.select');
    }

    public function preview(Request $request)
    {
        ini_set('auto_detect_line_endings', true);
        $request->validate(['file_path' => 'required|string', 'delimiter' => 'nullable|string']);
        $filePath = $request->input('file_path');
        $currentDelimiter = $request->input('delimiter', 'auto');
        if (!file_exists($filePath)) { return back()->with('error', 'File tidak ditemukan di server.'); }
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $headers = []; $previewData = []; $uniqueValues = []; 
        if (in_array($extension, ['csv', 'txt'])) {
            if (($handle = fopen($filePath, "r")) !== FALSE) {
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
                        foreach ($headers as $i => $h) { $uniqueValues[$i] = []; }
                    } else {
                        // Antisipasi jika baris kedua berisi header tambahan (Skip baris tersebut)
                        if (trim($data[0]) === 'TAHUN') continue;

                        if ($savedRows <= 2500) { $previewData[] = $data; $savedRows++; }
                        foreach ($data as $i => $val) {
                            if (isset($uniqueValues[$i])) {
                                $cleanVal = trim($val);
                                $uniqueValues[$i][$cleanVal] = true; 
                                if (count($uniqueValues[$i]) > 300) { unset($uniqueValues[$i]); }
                            }
                        }
                    }
                    $rowCounter++;
                    if ($rowCounter > 5000) break; 
                }
                fclose($handle);
            }
        } else { return back()->with('error', 'Format file tidak didukung.'); }
        $formattedUniqueValues = [];
        foreach ($uniqueValues as $index => $valuesMap) {
            $keys = array_keys($valuesMap); sort($keys); $formattedUniqueValues[$index] = $keys;
        }
        session(['final_import_path' => $filePath]);
        return view('import.preview', compact('headers', 'previewData', 'filePath', 'formattedUniqueValues', 'currentDelimiter'));
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
    $idReport = session('active_id_report', 1);

    if (!file_exists($filePath)) {
        return $request->expectsJson()
            ? response()->json(['status' => 'error', 'title' => 'Error', 'text' => 'File tidak ditemukan.'])
            : redirect()->route('import.select')->with('error', 'File tidak ditemukan.');
    }

    $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
    $tableName = $reportData ? strtolower(str_replace(' ', '_', $reportData->nama_report)) : 'jumlah_merchant_detail';
    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        $tableName = 'jumlah_merchant_detail'; 
    }

    $uniqueSuffix = '_MDT'; 
    if ($tableName === 'sv_merchant') {
        $uniqueSuffix = '_SVMer';
    } elseif ($tableName === 'merchant_qris') {
        $uniqueSuffix = '_MQ';
    }

    $dataToInsert = [];
    $csvHeaders = [];

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

            if ($rowCounter == 0) {
                $csvHeaders = array_map(function($val) {
                    return trim(preg_replace('/[\xef\xbb\xbf]/', '', $val));
                }, $data);
                $rowCounter++;
                continue; 
            }

            if (trim($data[0]) === 'TAHUN') continue;

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

            $rowData = [];
            $rowData['uniqueid_namareport'] = uniqid() . $uniqueSuffix;

            foreach ($selectedColumns as $index) {
                if (!isset($csvHeaders[$index])) continue;
                
                $colName = str_replace(' ', '_', $csvHeaders[$index]);

                if (strtolower($colName) === 'id' || strtolower($colName) === 'uniqueid_namareport') {
                    continue;
                }

                $cellValue = isset($data[$index]) ? trim($data[$index]) : '';

                if (strtolower($colName) === 'posisi' && $cellValue !== '') {
                    try {
                        $cellValue = Carbon::parse($cellValue)->format('Y-m-d');
                    } catch (\Exception $e) {}
                }

                $rowData[$colName] = ($cellValue === '') ? null : $cellValue;
            }
            
            $dataToInsert[] = $rowData;
            $rowCounter++;
        }
        fclose($handle);
    }

    $samplePosisi = !empty($dataToInsert) ? ($dataToInsert[0]['POSISI'] ?? null) : null;

    if ($samplePosisi) {
        $isDuplicate = DB::table($tableName)->whereDate('POSISI', $samplePosisi)->exists();
        if ($isDuplicate) {
            $importDir = dirname(dirname($filePath));
            if (strpos($importDir, 'imports') !== false && File::exists($importDir)) {
                File::deleteDirectory($importDir);
            }

            $response = [
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => "Data tanggal $samplePosisi sudah ada."
            ];

            return $request->expectsJson()
                ? response()->json($response)
                : redirect()->route('import.index')->with('sweet_warning', $response);
        }
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

    $chunks = array_chunk($dataToInsert, 500);
    $totalSuccess = 0;
    $totalFailed = 0;
    $lastErrorMsg = '';

    foreach ($chunks as $chunk) {
        try {
            DB::table($tableName)->insert($chunk);
            $totalSuccess += count($chunk);
        } catch (\Exception $e) {
            $totalFailed += count($chunk);
            $lastErrorMsg = substr($e->getMessage(), 0, 800) . '...';
        }
    }

    $finalStatus = $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed';

    DB::table('import_jobs')->where('id', $jobId)->update([
        'status' => $finalStatus,
        'updated_at' => now(),
    ]);

    $importDir = dirname(dirname($filePath));
    if (strpos($importDir, 'imports') !== false && File::exists($importDir)) {
        File::deleteDirectory($importDir);
    }

    $response = $totalFailed > 0
        ? [
            'status' => 'warning',
            'title' => 'Import Parsial',
            'text' => "Berhasil: $totalSuccess, Gagal: $totalFailed"
        ]
        : [
            'status' => 'success',
            'title' => 'Berhasil!',
            'text' => "Sebanyak $totalSuccess baris data berhasil diimport ke $tableName"
        ];

    return $request->expectsJson()
        ? response()->json($response)
        : redirect()->route('import.index')->with(
            $totalFailed > 0 ? 'sweet_warning' : 'sweet_success',
            $response
        );
}
}