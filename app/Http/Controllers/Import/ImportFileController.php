<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportFileController extends Controller
{
    public function upload(Request $request)
    {
        // 🔥 VALIDASI
        $request->validate([
            'id_report' => 'required',
            'file' => 'required|file|mimes:rar',
        ]);

        // 🔥 GENERATE FOLDER
        $folderName = 'import_' . date('Ymd_His') . '_' . Str::random(5);
        $storagePath = storage_path('app/imports/' . $folderName);

        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0777, true);
        }

        // 🔥 SIMPAN FILE
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $file->move($storagePath, $fileName);
        $fullPath = $storagePath . '/' . $fileName;

        // 🔥 EXTRACT RAR (PAKAI 7ZIP)
        $extractPath = $storagePath . '/extracted';

        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0777, true);
        }

        // ⚠️ Pastikan 7zip sudah terinstall di Windows
        $command = '"C:\Program Files\7-Zip\7z.exe" x "' . $fullPath . '" -o"' . $extractPath . '" -y';
        exec($command);

        // 🔥 AMBIL LIST FILE HASIL EXTRACT
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
            'import_files' => $files
        ]);

        return redirect()->route('import.select');
    }

    // 🔥 METHOD BARU UNTUK PREVIEW FILE
    public function preview(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string'
        ]);

        $filePath = $request->input('file_path');

        if (!file_exists($filePath)) {
            return back()->with('error', 'File tidak ditemukan di server.');
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $headers = [];
        $previewData = [];

        // Logika untuk membaca file CSV atau TXT (Native PHP)
        if (in_array($extension, ['csv', 'txt'])) {
            if (($handle = fopen($filePath, "r")) !== FALSE) {
                
                // Deteksi otomatis pemisah (delimiter) koma atau titik koma
                $firstLine = fgets($handle);
                $delimiter = strpos($firstLine, ';') !== false ? ';' : ',';
                rewind($handle); // Kembalikan pointer ke awal file

                $rowCounter = 0;
                while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                    if ($rowCounter == 0) {
                        $headers = $data; // Baris pertama jadikan header tabel
                    } else {
                        $previewData[] = $data; // Baris sisanya jadi data
                    }
                    $rowCounter++;
                    
                    // Batasi preview hanya 10 baris pertama agar tidak berat
                    if ($rowCounter > 10) break; 
                }
                fclose($handle);
            }
        } else {
            // Jika filenya .xlsx atau .xls
            // Catatan: Jika ingin membaca Excel native, kamu butuh package Laravel Excel (maatwebsite/excel)
            // Contoh jika pakai Laravel Excel: $previewData = Excel::toArray(new ImportClass, $filePath)[0];
            return back()->with('error', 'Format file .' . $extension . ' memerlukan library tambahan (seperti Laravel Excel) untuk di-preview. Silakan gunakan format .csv untuk saat ini.');
        }

        // Simpan path file yang sedang diproses ke session untuk proses import final (jika ada)
        session(['final_import_path' => $filePath]);

        return view('import.preview', compact('headers', 'previewData', 'filePath'));
    }
    
}

