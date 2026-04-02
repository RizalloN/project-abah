<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon; 

class ImportFileController extends Controller
{
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
            'import_files'     => $files,
            'active_id_report' => $request->input('id_report'),
            'import_type'      => 'default',
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

        $idReport = session('active_id_report', 1);
        $reportData = DB::table('nama_report')->where('id_report', $idReport)->first();
        $isBrilinkSummary = false;

        if ($reportData && (stripos($reportData->nama_report, 'BRILINK Web - Laporan Summary Transaksi') !== false || stripos($reportData->nama_report, 'brilink_web') !== false)) {
            $isBrilinkSummary = true;
        }

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
                        
                        if ($isBrilinkSummary) {
                            $headers = [
                                'PERIODE', 'NO', 'FLAG', 'KANWIL', 'KODE_KANCA', 'CABANG',
                                'KODE_UKER', 'UKER', 'MERCHANT_NAME', 'MERCHANT_CODE',
                                'OUTLET_NAME', 'OUTLET_CODE', 'TOTAL_TRANSAKSI',
                                'TOTAL_NOMINAL', 'TOTAL_FEE', 'TOTAL_FEE_BRI'
                            ];
                        } else {
                            $headers = array_map(function($val) {
                                $clean = trim(preg_replace('/[\xef\xbb\xbf]/', '', $val));
                                return str_replace(' ', '_', $clean);
                            }, $data);
                            
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
                            $rawPeriode = $data[0] ?? '';
                            $periode = null;

                            if (strpos($rawPeriode, ':') !== false) {
                                $periode = trim(explode(':', $rawPeriode)[1]); 
                            } else {
                                $periode = trim($rawPeriode);
                            }

                            $data = [
                                $periode,
                                $data[1] ?? null,
                                $data[2] ?? null,
                                trim($data[3] ?? null),
                                $data[4] ?? null,
                                trim($data[5] ?? null),
                                $data[6] ?? null,
                                trim($data[7] ?? null),
                                trim($data[8] ?? null),
                                $data[9] ?? null,
                                trim($data[10] ?? null),
                                $data[11] ?? null,
                                $data[12] ?? null,
                                $data[13] ?? null,
                                $data[14] ?? null,
                                $data[15] ?? null,
                            ];
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

        $processRoute = route('import.process');

        return view('import.preview', compact(
            'headers',
            'previewData',
            'filePath',
            'formattedUniqueValues',
            'currentDelimiter',
            'isBrilinkSummary',
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
                    if ($isBrilinkSummary) {
                        // ❌ JANGAN pakai header CSV (textboxXX)
                        $csvHeaders = [];
                    } else {
                        $csvHeaders = array_map(function($val) {
                            return trim(preg_replace('/[\xef\xbb\xbf]/', '', $val));
                        }, $data);
                        
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

                    $rowData = [
                        'uniqueid_namareport' => uniqid() . '_BST',
                        'periode' => $periode,

                        'no' => (int) ($data[1] ?? 0),
                        'kanwil' => trim($data[3] ?? null),
                        'cabang' => trim($data[5] ?? null),
                        'uker' => trim($data[7] ?? null),

                        'merchant_name' => trim($data[8] ?? null),
                        'merchant_code' => trim($data[9] ?? null),
                        'outlet_name' => trim($data[10] ?? null),
                        'outlet_code' => trim($data[11] ?? null),

                        'total_transaksi' => (int) preg_replace('/[^0-9]/', '', $data[12] ?? 0),

                        'total_nominal' => (float) preg_replace('/[^0-9.]/', '', $data[13] ?? 0),
                        'total_fee' => (float) preg_replace('/[^0-9.]/', '', $data[14] ?? 0),
                        'total_fee_bri' => (float) preg_replace('/[^0-9.]/', '', $data[15] ?? 0),
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
                        
                        $numericColumns = [
                            'SALDO_POSISI', 'RATAS_SALDO', 'SALDO_POSISI_BY_CIF', 'RATAS_SALDO_BY_CIF',
                            'SALES_VOLUME', 'AKUMULASI_SALES_VOLUME', 'JML_TRANSAKSI', 'AKUMULASI_TRANSAKSI',
                            'NILAI', 'MERCHANT_QRIS_VOLUME', 'MERCHANT_QRIS', 'BAKI_DEBET'
                        ];

                        if (in_array(strtoupper($colName), $numericColumns)) {
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

        if ($isBrilinkSummary && $samplePeriode) {
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
