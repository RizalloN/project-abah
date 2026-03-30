<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon; 
use App\Models\NamaReport;

class ImportFileBrimoController extends Controller
{
    // Variabel hardcode dihapus agar bisa dinamis sesuai report yang dipilih

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
        session([
            'import_files'      => $files,
            'active_id_report'  => $request->input('id_report'),
            'import_type'       => 'brimo',
        ]);
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
        
        session(['final_import_path' => $filePath]);

        $processRoute = route('import.brimo.process');

        return view('import.preview', compact(
            'headers',
            'previewData',
            'filePath',
            'formattedUniqueValues',
            'currentDelimiter',
            'processRoute'
        ));
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
        
        // 1. DETEKSI NAMA REPORT DARI DATABASE
        $idReport = session('active_id_report', 1);
        $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();

        // 2. PENENTUAN TABEL & SUFFIX SECARA DINAMIS
        // Selalu gunakan user_brimo_rpt_v2 atau user_brimo_fin.
        // JANGAN gunakan $reportData->table_name karena bisa salah di DB.
        $tableName    = 'user_brimo_rpt_v2'; // Default fallback
        $uniqueSuffix = '_UBv2';             // Default fallback

        if ($reportData) {
            $namaReport = $reportData->nama_report ?? '';

            // Cek apakah laporan ini adalah User Brimo FIN
            if (stripos($namaReport, 'fin') !== false) {
                $tableName    = 'user_brimo_fin';
                $uniqueSuffix = '_UBFin';
            } else {
                // Semua laporan Brimo lainnya (RPT V2, dll) → user_brimo_rpt_v2
                $tableName    = 'user_brimo_rpt_v2';
                $uniqueSuffix = '_UBv2';
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
                    
                    foreach ($csvHeaders as $idx => $hdr) {
                        if (stripos($hdr, 'posisi') !== false) { $posisiIndex = $idx; }
                        if (stripos($hdr, 'tahun') !== false) { $tahunIndex = $idx; }
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
                    
                    $colName = str_replace(' ', '_', strtolower($csvHeaders[$index]));

                    if (strtolower($colName) === 'id' || strtolower($colName) === 'uniqueid_namareport') {
                        continue;
                    }

                    $cellValue = isset($data[$index]) ? trim($data[$index]) : '';

                    // Membersihkan format angka seperti pada kolom jumlah/nominal/volume/fee
                    $numericColumns = ['jumlah', 'nominal', 'fee', 'saldo', 'volume', 'transaksi'];
                    $isNumericColumn = false;
                    foreach ($numericColumns as $numCol) {
                        if (stripos($colName, $numCol) !== false) {
                            $isNumericColumn = true;
                            break;
                        }
                    }

                    if ($isNumericColumn) {
                        $clean = preg_replace('/[^0-9.-]/', '', $cellValue);
                        if (!is_numeric($clean)) {
                            $clean = null;
                        }
                        $cellValue = $clean;
                    }

                    $rowData[$colName] = ($cellValue === '') ? null : $cellValue;
                }
                
                $dataToInsert[] = $rowData;
                $rowCounter++;
            }
            fclose($handle);
        }

        // PENGECEKAN DUPLIKAT DATA
        $samplePeriode = $dataToInsert[0]['periode'] ?? null;
        $samplePosisi = $dataToInsert[0]['posisi'] ?? null;

        $isDuplicate = false;
        $duplicateText = '';

        if ($samplePeriode) {
            $isDuplicate = DB::table($tableName)->where('periode', $samplePeriode)->exists();
            if ($isDuplicate) {
                $duplicateText = "Data untuk PERIODE <b>$samplePeriode</b> sudah pernah diunggah sebelumnya ke tabel <b class='text-uppercase'>$tableName</b>.<br><br>Sistem membatalkan proses ini.";
            }
        } elseif ($samplePosisi) {
            $isDuplicate = DB::table($tableName)->whereDate('posisi', $samplePosisi)->exists();
            if ($isDuplicate) {
                $duplicateText = "Data untuk tanggal POSISI <b>$samplePosisi</b> sudah pernah diunggah sebelumnya ke tabel <b class='text-uppercase'>$tableName</b>.<br><br>Sistem membatalkan proses ini.";
            }
        }

        if ($isDuplicate) {
            $importDir = dirname(dirname($filePath));
            if (strpos($importDir, 'imports') !== false && File::exists($importDir)) {
                File::deleteDirectory($importDir);
            }
            
            $response = [
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => $duplicateText
            ];
            
            return $request->expectsJson()
                ? response()->json($response)
                : redirect()->route('import.index')->with('sweet_warning', $response);
        }

        // CATAT KE IMPORT JOBS
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
                
                // Menyimpan kegagalan import seperti pada ImportFileController
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

        $finalStatus = $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed';
        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => $finalStatus,
            'updated_at' => now(),
        ]);

        $importDir = dirname(dirname($filePath));
        if (strpos($importDir, 'imports') !== false && File::exists($importDir)) {
            File::deleteDirectory($importDir);
        }

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