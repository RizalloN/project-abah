<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\NamaReport;

class ImportFileController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'id_report' => 'required',
            'file' => 'required|file|mimes:rar',
        ]);

        $folderName = 'import_' . date('Ymd_His') . '_' . Str::random(5);
        $storagePath = storage_path('app/imports/' . $folderName);

        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0777, true);
        }

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $file->move($storagePath, $fileName);
        $fullPath = $storagePath . '/' . $fileName;

        $extractPath = $storagePath . '/extracted';

        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0777, true);
        }

        $command = '"C:\Program Files\7-Zip\7z.exe" x "' . $fullPath . '" -o"' . $extractPath . '" -y';
        exec($command);

        $files = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractPath));

        foreach ($rii as $fileItem) {
            if ($fileItem->isDir()) continue;

            $files[] = [
                'name' => $fileItem->getFilename(),
                'path' => $fileItem->getPathname(),
            ];
        }

        session([
            'import_files' => $files,
            'active_id_report' => $request->input('id_report')
        ]);

        return redirect()->route('import.select');
    }

    public function preview(Request $request)
    {
        // Set ini agar PHP bisa mendeteksi baris (Enter) dari berbagai format OS
        ini_set('auto_detect_line_endings', true);

        $request->validate([
            'file_path' => 'required|string',
            'delimiter' => 'nullable|string'
        ]);

        $filePath = $request->input('file_path');
        $currentDelimiter = $request->input('delimiter', 'auto');

        if (!file_exists($filePath)) {
            return back()->with('error', 'File tidak ditemukan di server.');
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $headers = [];
        $previewData = [];
        $uniqueValues = []; 

        if (in_array($extension, ['csv', 'txt'])) {
            if (($handle = fopen($filePath, "r")) !== FALSE) {
                
                $firstLine = fgets($handle);
                
                if ($currentDelimiter === 'auto') {
                    $delimiters = [',' => 0, ';' => 0, '|' => 0, "\t" => 0, '.' => 0];
                    foreach ($delimiters as $delim => &$count) {
                        $count = substr_count($firstLine, $delim);
                    }
                    arsort($delimiters);
                    $delimiter = key($delimiters); 
                } else {
                    $delimiter = $currentDelimiter;
                }
                
                rewind($handle); 

                $rowCounter = 0;
                while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                    if (empty($data) || implode('', $data) === '') continue; // Skip baris kosong

                    if ($rowCounter == 0) {
                        // Bersihkan Header dari BOM/Karakter Gaib sejak awal
                        $headers = array_map(function($val) {
                            return trim($val, " \t\n\r\0\x0B\xEF\xBB\xBF\"");
                        }, $data);

                        foreach ($headers as $i => $h) {
                            $uniqueValues[$i] = [];
                        }
                    } else {
                        if ($rowCounter <= 10) {
                            $previewData[] = $data;
                        }
                        
                        foreach ($data as $i => $val) {
                            if (isset($uniqueValues[$i])) {
                                $cleanVal = trim($val);
                                $uniqueValues[$i][$cleanVal] = true; 
                                if (count($uniqueValues[$i]) > 300) {
                                    unset($uniqueValues[$i]); 
                                }
                            }
                        }
                    }
                    $rowCounter++;
                }
                fclose($handle);
            }
        } else {
            return back()->with('error', 'Format file tidak didukung.');
        }

        $formattedUniqueValues = [];
        foreach ($uniqueValues as $index => $valuesMap) {
            $keys = array_keys($valuesMap);
            sort($keys);
            $formattedUniqueValues[$index] = $keys;
        }

        session(['final_import_path' => $filePath]);

        return view('import.preview', compact('headers', 'previewData', 'filePath', 'formattedUniqueValues', 'currentDelimiter'));
    }

    public function processImport(Request $request)
    {
        ini_set('auto_detect_line_endings', true);
        ini_set('max_execution_time', 300); // Beri waktu ekstra untuk proses DB

        $request->validate([
            'file_path' => 'required|string',
            'selected_columns' => 'required|array|min:1',
            'has_filter' => 'nullable|array',
            'filters' => 'nullable|array',
            'delimiter' => 'required|string'
        ]);

        $filePath = $request->input('file_path');
        $selectedColumns = $request->input('selected_columns');
        
        $hasFilters = $request->input('has_filter', []); // Array penanda kolom mana saja yg pnya UI filter
        $rawFilters = $request->input('filters', []);
        
        $currentDelimiter = $request->input('delimiter', 'auto');
        $idReport = session('active_id_report', 1);

        if (!file_exists($filePath)) {
            return redirect()->route('import.select')->with('error', 'File tidak ditemukan.');
        }

        // 🔥 TANGKAP FILTER AKTIF
        $activeFilters = [];
        foreach ($hasFilters as $colIdx) {
            // Jika kolom punya UI filter, tangkap apa yg dicentang. Jika tidak ada yg dicentang, jadikan array kosong.
            $activeFilters[$colIdx] = isset($rawFilters[$colIdx]) ? $rawFilters[$colIdx] : [];
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
                        return trim($val, " \t\n\r\0\x0B\xEF\xBB\xBF\"");
                    }, $data);
                    $rowCounter++;
                    continue; 
                }

                $passFilter = true;
                foreach ($activeFilters as $colIdx => $allowedValues) {
                    $cellValue = isset($data[$colIdx]) ? trim($data[$colIdx]) : '';
                    // Jika nilai cell tidak ada di dalam checkbox yang dicentang user, skip baris ini
                    if (!in_array($cellValue, $allowedValues)) {
                        $passFilter = false;
                        break;
                    }
                }

                if (!$passFilter) {
                    $rowCounter++;
                    continue;
                }

                // 🔥 SUSUN DATA UNTUK INSERT
                $rowData = [];
                // 1. Tambahkan Format Unique ID
                $rowData['uniqueid_namareport'] = uniqid() . '_MDT';

                // 2. Map setiap kolom terpilih
                foreach ($selectedColumns as $index) {
                    $colName = $csvHeaders[$index];
                    
                    // Kita tidak memasukkan `id` karena MySQL akan memberikan auto increment
                    if (strtolower($colName) === 'id' || strtolower($colName) === 'uniqueid_namareport') {
                        continue;
                    }

                    $rowData[$colName] = isset($data[$index]) ? trim($data[$index]) : null;
                }
                
                $dataToInsert[] = $rowData;
                $rowCounter++;
            }
            fclose($handle);
        }

        // 🔥 INSERT DATABASE BERLAPIS 🔥
        $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
        $tableName = $reportData ? strtolower(str_replace(' ', '_', $reportData->nama_report)) : 'jumlah_merchant_detail';
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            $tableName = 'jumlah_merchant_detail'; // Fallback
        }

        // 1. Buat Job
        $jobId = DB::table('import_jobs')->insertGetId([
            'id_report' => $idReport,
            'file_name' => basename($filePath),
            'folder_path' => dirname($filePath),
            'status' => 'processing',
            'total_files' => count($dataToInsert), // Akan mencatat total baris ter-filter
            'created_by' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Buat Mapping
        foreach ($selectedColumns as $index) {
            DB::table('import_mappings')->insert([
                'import_job_id' => $jobId,
                'source_column' => $csvHeaders[$index],
                'target_column' => $csvHeaders[$index],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Eksekusi Insert Table Utama
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
                $lastErrorMsg = $e->getMessage();
                
                DB::table('failed_jobs')->insert([
                    'uuid' => (string) Str::uuid(),
                    'connection' => 'database',
                    'queue' => 'import_' . $tableName,
                    'payload' => json_encode($chunk),
                    'exception' => $lastErrorMsg,
                    'failed_at' => now(),
                ]);
            }
        }

        $finalStatus = $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed';
        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => $finalStatus,
            'updated_at' => now(),
        ]);

        // 🔥 KEMBALI DENGAN PESAN SWEETALERT
        if ($totalFailed > 0) {
            return redirect()->route('import.index')->with('sweet_warning', [
                'title' => 'Import Memiliki Kendala!',
                'text' => "Berhasil: $totalSuccess baris.<br>Gagal: $totalFailed baris.<br><br><b>Penyebab:</b><br><small class='text-danger'>" . substr($lastErrorMsg, 0, 150) . "...</small>"
            ]);
        }

        return redirect()->route('import.index')->with('sweet_success', [
            'title' => 'Berhasil!',
            'text' => "Sebanyak $totalSuccess baris data telah sukses masuk ke tabel <b>$tableName</b>."
        ]);
    }
}