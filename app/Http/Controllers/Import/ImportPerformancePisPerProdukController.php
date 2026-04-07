<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Import\Concerns\AllocatesGapIds;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportPerformancePisPerProdukController extends Controller
{
    use AllocatesGapIds;

    private const TABLE_NAME = 'performance_pis_per_produk';
    private const UNIQUE_SUFFIX = '_PISPP';
    private const COLUMN_DELIMITER = ',';
    private const STREAM_PROGRESS_EVERY = 500;
    private const INSERT_BATCH_SIZE = 1000;
    private const BULK_LOAD_TEMP_DIR = 'app/import_bulk';
    private const EXPECTED_HEADERS = [
        'no',
        'kode_kanwil',
        'kanwil',
        'kode_kanca',
        'kanca',
        'kode_uker',
        'uker',
        'corporate_code',
        'nama_perusahaan',
        'jenis_mitra',
        'jenis_perusahaan',
        'tipe_produk',
        'nomor_rekening',
        'nama_rekening',
        'saldo_britama_kerjasama',
        'tanggal_pembuatan_rekening',
        'pn_rm_dana_brinets',
        'pn_rm_dana_pis2',
        'nomor_hp',
        'email',
        'flag_briguna',
        'flag_cc',
    ];
    private const TARGET_COLUMNS = [
        'posisi',
        'kode_kanwil',
        'kanwil',
        'kode_kanca',
        'kanca',
        'kode_uker',
        'uker',
        'corporate_code',
        'nama_perusahaan',
        'jenis_mitra',
        'jenis_perusahaan',
        'tipe_produk',
        'nomor_rekening',
        'nama_rekening',
        'saldo_britama_kerjasama',
        'tanggal_pembuatan_rekening',
        'pn_rm_dana_brinets',
        'pn_rm_dana_pis2',
        'nomor_hp',
        'email',
        'flag_briguna',
        'flag_cc',
    ];

    public function upload(Request $request)
    {
        $request->validate([
            'id_report' => 'required',
            'file' => 'required|file|mimes:rar,csv,txt',
            'periode' => 'required|date_format:Y-m-d',
        ]);

        try {
            $file = $request->file('file');
            $path = $this->storePerformancePisUpload($file);

            session([
                'active_id_report' => $request->input('id_report'),
                'import_type' => 'performance_pis',
                'performance_pis_file' => $path,
                'performance_pis_periode' => $request->input('periode'),
            ]);

            return redirect()->route('import.performancepis.preview');
        } catch (\Throwable $e) {
            return redirect()->route('import.index')->with('error', 'Upload Performance PIS gagal: ' . $e->getMessage());
        }
    }

    public function preview(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $relativePath = session('performance_pis_file', $request->input('file_path'));
        $periodeInput = $request->input('periode', session('performance_pis_periode'));
        if (!$relativePath) {
            return redirect()->route('import.index')->with('error', 'File import tidak ditemukan. Silakan upload ulang.');
        }

        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return redirect()->route('import.index')->with('error', 'File CSV tidak ditemukan di server.');
        }
        if (!$periodeInput) {
            return redirect()->route('import.index')->with('error', 'Periode Performance PIS tidak ditemukan. Silakan upload ulang.');
        }

        session(['performance_pis_periode' => $periodeInput]);

        try {
            $context = $this->buildCsvContext($absolutePath, $periodeInput);
        } catch (\Throwable $e) {
            return redirect()->route('import.index')->with('error', 'Struktur CSV tidak dikenali: ' . $e->getMessage());
        }

        $previewData = [];
        $uniqueValues = [];
        foreach ($context['headers'] as $index => $header) {
            $uniqueValues[$index] = [];
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            return redirect()->route('import.index')->with('error', 'Gagal membuka file CSV.');
        }

        $lineNumber = 0;
        $savedRows = 0;
        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;

                if ($lineNumber <= $context['header_line']) {
                    continue;
                }

                $row = $this->mapCsvRow($context, $this->parseCsvLine($line));
                if ($row === null) {
                    continue;
                }

                if ($savedRows < 2500) {
                    $previewData[] = $row;
                    $savedRows++;
                }

                foreach ($row as $colIndex => $value) {
                    if (!isset($uniqueValues[$colIndex]) || count($uniqueValues[$colIndex]) > 5000) {
                        continue;
                    }

                    $key = trim((string) ($value ?? ''));
                    $uniqueValues[$colIndex][$key] = true;
                }
            }
        } finally {
            fclose($handle);
        }

        $formattedUniqueValues = [];
        foreach ($uniqueValues as $index => $valuesMap) {
            $keys = array_keys($valuesMap);
            usort($keys, 'strnatcmp');
            $formattedUniqueValues[$index] = $keys;
        }

        return view('import.preview', [
            'headers' => $context['headers'],
            'previewData' => $previewData,
            'filePath' => $relativePath,
            'formattedUniqueValues' => $formattedUniqueValues,
            'currentDelimiter' => $context['delimiter'],
            'processRoute' => route('import.performancepis.process'),
            'previewRoute' => route('import.performancepis.preview.refresh'),
            'initRoute' => route('import.performancepis.init'),
            'streamRoute' => route('import.performancepis.stream'),
            'backRoute' => route('import.index'),
            'disableArea6AutoFilter' => true,
            'detectedPosisi' => $context['posisi'],
            'manualPeriode' => $periodeInput,
            'manualPeriodeLabel' => Carbon::parse($periodeInput)->translatedFormat('d F Y'),
        ]);
    }

    public function processImport(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $request->validate([
            'file_path' => 'required|string',
            'selected_columns' => 'required|array|min:1',
            'active_filters_json' => 'nullable|string',
            'delimiter' => 'required|string',
            'periode' => 'required|date_format:Y-m-d',
        ]);

        $relativePath = $request->input('file_path');
        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'File CSV tidak ditemukan di server.',
            ], 422);
        }

        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json', '{}'), true) ?: [];

        try {
            $context = $this->buildCsvContext($absolutePath, $request->input('periode'));
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Struktur CSV tidak dikenali: ' . $e->getMessage(),
            ], 422);
        }

        if (!empty($context['posisi']) && DB::table(self::TABLE_NAME)->whereDate('posisi', $context['posisi'])->exists()) {
            $this->cleanupUploadedFile($relativePath);

            return response()->json([
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => "Data untuk tanggal POSISI <b>{$context['posisi']}</b> sudah ada di tabel <b class='text-uppercase'>" . self::TABLE_NAME . '</b>.',
            ]);
        }

        $jobId = DB::table('import_jobs')->insertGetId([
            'id_report' => session('active_id_report'),
            'file_name' => basename($absolutePath),
            'folder_path' => dirname($absolutePath),
            'status' => 'processing',
            'total_files' => 0,
            'total_success' => 0,
            'total_failed' => 0,
            'created_by' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = [];
        $totalRows = 0;
        $totalSuccess = 0;
        $totalFailed = 0;
        $lastErrorMsg = '';

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Gagal membuka file CSV.',
            ], 422);
        }

        $lineNumber = 0;
        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;

                if ($lineNumber <= $context['header_line']) {
                    continue;
                }

                $row = $this->mapCsvRow($context, $this->parseCsvLine($line));
                if ($row === null) {
                    continue;
                }

                if (!$this->passesFilters($row, $activeFilters)) {
                    continue;
                }

                $insertRow = $this->buildInsertRow($context['headers'], $row, $selectedColumns);
                if ($insertRow === null) {
                    continue;
                }

                $rows[] = $insertRow;
                $totalRows++;

                if (count($rows) >= 500) {
                    $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastErrorMsg);
                    $rows = [];
                }
            }
        } finally {
            fclose($handle);
        }

        if (!empty($rows)) {
            $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastErrorMsg);
        }

        $finalStatus = $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed';
        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => $finalStatus,
            'total_files' => $totalRows,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'updated_at' => now(),
        ]);

        if ($finalStatus === 'completed' && $totalSuccess >= $totalRows) {
            $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath);
        }

        if ($totalFailed > 0) {
            return response()->json([
                'status' => 'warning',
                'title' => 'Import Memiliki Kendala!',
                'text' => "Berhasil: {$totalSuccess} baris.<br>Gagal: {$totalFailed} baris." .
                    ($lastErrorMsg !== '' ? "<br><br><b>Info MySQL:</b><br><small class='text-danger'>" . htmlspecialchars($lastErrorMsg, ENT_QUOTES) . '</small>' : ''),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'title' => 'Berhasil!',
            'text' => "Sebanyak {$totalSuccess} baris data telah sukses masuk ke tabel <b class='text-uppercase'>" . self::TABLE_NAME . '</b>.',
        ]);
    }

    public function initImport(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $request->validate([
            'file_path' => 'required|string',
            'selected_columns' => 'required|array|min:1',
            'active_filters_json' => 'nullable|string',
            'delimiter' => 'required|string',
            'periode' => 'required|date_format:Y-m-d',
        ]);

        $relativePath = $request->input('file_path');
        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'File CSV tidak ditemukan di server.',
            ], 422);
        }

        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json', '{}'), true) ?: [];

        try {
            $context = $this->buildCsvContext($absolutePath, $request->input('periode'));
            $totalRows = $this->countFilteredRows($absolutePath, $context, $activeFilters, $selectedColumns);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Struktur CSV tidak dikenali: ' . $e->getMessage(),
            ], 422);
        }

        if (empty($context['posisi'])) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Tanggal posisi Performance PIS tidak berhasil dideteksi.',
            ], 422);
        }

        if ($totalRows === 0) {
            return response()->json([
                'status' => 'warning',
                'title' => 'Tidak Ada Data',
                'text' => 'Tidak ada baris yang lolos filter untuk diimport.',
            ], 422);
        }

        if (DB::table(self::TABLE_NAME)->whereDate('posisi', $context['posisi'])->exists()) {
            $this->cleanupUploadedFile($relativePath);

            return response()->json([
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => "Data untuk tanggal POSISI <b>{$context['posisi']}</b> sudah ada di tabel <b class='text-uppercase'>" . self::TABLE_NAME . '</b>.',
            ], 422);
        }

        $jobId = DB::table('import_jobs')->insertGetId([
            'id_report' => session('active_id_report'),
            'file_name' => basename($absolutePath),
            'folder_path' => dirname($absolutePath),
            'status' => 'processing',
            'total_files' => $totalRows,
            'total_success' => 0,
            'total_failed' => 0,
            'created_by' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $importParams = [
            'job_id' => $jobId,
            'file_path' => $relativePath,
            'selected_columns' => $selectedColumns,
            'active_filters' => $activeFilters,
            'periode' => $request->input('periode'),
            'total_rows' => $totalRows,
        ];
        session(['performance_pis_import_params' => $importParams]);
        Cache::put('performance_pis_import_params_' . $jobId, $importParams, now()->addHours(4));

        return response()->json([
            'status' => 'success',
            'job_id' => $jobId,
            'total_rows' => $totalRows,
        ]);
    }

    public function processImportStream(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        DB::disableQueryLog();

        $sessionParams = session('performance_pis_import_params', []);
        $jobId = (int) ($sessionParams['job_id'] ?? $request->query('job_id', 0));
        $params = Cache::get('performance_pis_import_params_' . $jobId, $sessionParams);

        request()->session()->save();

        return response()->stream(function () use ($params, $jobId) {
            $streamLock = null;
            $send = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                if ($jobId > 0) {
                    $streamLock = Cache::lock('import_performance_pis_stream_job_' . $jobId, 7200);

                    if (!$streamLock->get()) {
                        $job = DB::table('import_jobs')->where('id', $jobId)->first();

                        if ($job && in_array($job->status, ['completed', 'failed', 'failed_partial'], true)) {
                            $send('complete', [
                                'total_success' => (int) ($job->total_success ?? 0),
                                'total_failed' => (int) ($job->total_failed ?? 0),
                                'total_rows' => (int) ($job->total_files ?? 0),
                            ]);
                        } else {
                            $send('error', ['message' => 'Job import Performance PIS ini sudah sedang diproses pada koneksi lain.']);
                        }
                        return;
                    }
                }

                $relativePath = $params['file_path'] ?? '';
                $absolutePath = Storage::path($relativePath);
                $selectedColumns = array_map('intval', $params['selected_columns'] ?? []);
                $activeFilters = $params['active_filters'] ?? [];
                $periode = $params['periode'] ?? null;
                $totalRows = (int) ($params['total_rows'] ?? 0);

                if ($relativePath === '' || !file_exists($absolutePath)) {
                    $send('error', ['message' => 'File CSV Performance PIS tidak ditemukan di server.']);
                    return;
                }

                $context = $this->buildCsvContext($absolutePath, $periode);

                if ($totalRows <= 0) {
                    $totalRows = $this->countFilteredRows($absolutePath, $context, $activeFilters, $selectedColumns);
                }

                $send('progress', [
                    'percent' => 5,
                    'message' => 'Menyiapkan CSV staging Performance PIS...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $stagingResult = $this->createFilteredCsvStage($absolutePath, $context, $activeFilters, $selectedColumns, $totalRows, $send);
                $totalPreparedRows = $stagingResult['rows_done'];
                $stagingPath = $stagingResult['path'];
                $loadColumns = $stagingResult['columns'];

                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'total_files' => $totalPreparedRows,
                        'updated_at' => now(),
                    ]);
                }

                $send('progress', [
                    'percent' => 96,
                    'message' => 'CSV staging siap. Memuat data ke MySQL...',
                    'rows_done' => $totalPreparedRows,
                    'total' => $totalPreparedRows,
                    'speed' => 0,
                ]);

                $totalSuccess = 0;
                $totalFailed = 0;
                $lastErrorMsg = '';

                try {
                    if ($this->supportsNativeBulkLoad()) {
                        $totalSuccess = $this->loadCsvIntoMysql($stagingPath, self::TABLE_NAME, $loadColumns);
                        $totalFailed = max(0, $totalPreparedRows - $totalSuccess);
                    } else {
                        throw new \RuntimeException('LOAD DATA LOCAL INFILE tidak tersedia pada koneksi aktif.');
                    }
                } catch (\Throwable $e) {
                    $lastErrorMsg = Str::limit($e->getMessage(), 800, '...');
                    Log::warning('Performance PIS bulk load fallback: ' . $e->getMessage());

                    $send('progress', [
                        'percent' => 97,
                        'message' => 'Bulk load tidak tersedia. Fallback ke batch insert...',
                        'rows_done' => $totalPreparedRows,
                        'total' => $totalPreparedRows,
                        'speed' => 0,
                    ]);

                    $fallbackResult = $this->insertStagedCsvInBatches($stagingPath, $loadColumns);
                    $totalSuccess = $fallbackResult['total_success'];
                    $totalFailed = $fallbackResult['total_failed'];
                    if ($fallbackResult['last_error'] !== '') {
                        $lastErrorMsg = $fallbackResult['last_error'];
                    }
                } finally {
                    @unlink($stagingPath);
                }

                DB::table('import_jobs')->where('id', $jobId)->update([
                    'status' => $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed',
                    'total_files' => $totalPreparedRows,
                    'total_success' => $totalSuccess,
                    'total_failed' => $totalFailed,
                    'updated_at' => now(),
                ]);

                if ($totalFailed === 0 && $totalSuccess >= $totalPreparedRows) {
                    $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, [$stagingPath]);
                }

                $send('complete', [
                    'total_success' => $totalSuccess,
                    'total_failed' => $totalFailed,
                    'total_rows' => $totalPreparedRows,
                    'error_message' => $lastErrorMsg,
                ]);
            } catch (\Throwable $e) {
                Log::error('PERFORMANCE PIS STREAM ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);
                }
                $send('error', [
                    'message' => 'Fatal Error: ' . $e->getMessage(),
                ]);
            } finally {
                if ($streamLock) {
                    try {
                        $streamLock->release();
                    } catch (\Throwable $e) {
                        Log::warning('Failed to release Performance PIS import stream lock for job ' . $jobId . ': ' . $e->getMessage());
                    }
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    private function buildCsvContext(string $path, ?string $manualPosisi = null): array
    {
        $sampleRows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV.');
        }

        try {
            $lineNumber = 0;
            while (($line = fgets($handle)) !== false && $lineNumber < 20) {
                $lineNumber++;
                $trimmed = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line));
                if ($trimmed === '') {
                    continue;
                }

                $sampleRows[] = [
                    'line' => $trimmed,
                    'line_number' => $lineNumber,
                ];
            }
        } finally {
            fclose($handle);
        }

        if (empty($sampleRows)) {
            throw new \RuntimeException('Isi file CSV kosong.');
        }

        $structure = $this->detectCsvStructure($sampleRows);
        if (!$structure) {
            throw new \RuntimeException('Baris header CSV Performance PIS tidak ditemukan.');
        }

        $posisiRaw = $structure['posisi_raw']
            ?? $this->findPosisiValue($sampleRows, $structure['header_row']['line_number']);

        return [
            'delimiter' => self::COLUMN_DELIMITER,
            'header_line' => $structure['header_row']['line_number'],
            'source_headers' => $structure['parsed_headers'],
            'source_indexes' => $this->buildSourceIndexes($structure['parsed_headers']),
            'headers' => self::TARGET_COLUMNS,
            'posisi' => $this->normalizeDateValue($posisiRaw) ?? $this->normalizeDateValue($manualPosisi),
        ];
    }

    private function storePerformancePisUpload($file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension !== 'rar') {
            return $file->store('performance_pis_imports');
        }

        $folderName = 'performance_pis_' . date('Ymd_His') . '_' . Str::random(5);
        $storagePath = storage_path('app/performance_pis_imports/' . $folderName);
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0777, true);
        }

        $fileName = $file->getClientOriginalName();
        $file->move($storagePath, $fileName);
        $fullPath = $storagePath . DIRECTORY_SEPARATOR . $fileName;

        $extractPath = $storagePath . DIRECTORY_SEPARATOR . 'extracted';
        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0777, true);
        }

        $command = '"C:\Program Files\7-Zip\7z.exe" x "' . $fullPath . '" -o"' . $extractPath . '" -y';
        exec($command);

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractPath));
        foreach ($rii as $fileItem) {
            if ($fileItem->isDir()) {
                continue;
            }

            $candidatePath = $fileItem->getPathname();
            $candidateExtension = strtolower(pathinfo($candidatePath, PATHINFO_EXTENSION));
            if (in_array($candidateExtension, ['csv', 'txt'], true)) {
                return str_replace('\\', '/', str_replace(storage_path('app') . DIRECTORY_SEPARATOR, '', $candidatePath));
            }
        }

        throw new \RuntimeException('File RAR tidak berisi CSV/TXT yang bisa diimport.');
    }

    private function detectCsvStructure(array $sampleRows): ?array
    {
        $bestCandidate = null;

        foreach ($sampleRows as $row) {
            $parsedHeaders = array_map(
                fn ($value) => $this->normalizeHeader($value),
                $this->parseCsvLine($row['line'])
            );

            $parsedHeaders = array_values(array_filter(
                $parsedHeaders,
                fn ($value) => $value !== ''
            ));

            if (empty($parsedHeaders)) {
                continue;
            }

            $score = $this->scoreHeaderCandidate($parsedHeaders);
            if ($score <= 0) {
                continue;
            }

            $posisiRaw = $this->findPosisiValue($sampleRows, $row['line_number']);
            if ($this->normalizeDateValue($posisiRaw) !== null) {
                $score += 200;
            }

            $candidate = [
                'delimiter' => self::COLUMN_DELIMITER,
                'header_row' => $row,
                'parsed_headers' => $parsedHeaders,
                'posisi_raw' => $posisiRaw,
                'score' => $score,
            ];

            if ($bestCandidate === null || $candidate['score'] > $bestCandidate['score']) {
                $bestCandidate = $candidate;
            }
        }

        return $bestCandidate;
    }

    private function scoreHeaderCandidate(array $headers): int
    {
        if ($headers === self::EXPECTED_HEADERS) {
            return 10000;
        }

        $score = 0;
        foreach (self::EXPECTED_HEADERS as $index => $expectedHeader) {
            if (!in_array($expectedHeader, $headers, true)) {
                continue;
            }

            $score += 10;

            if (($headers[$index] ?? null) === $expectedHeader) {
                $score += 5;
            }
        }

        if (in_array('kode_kanwil', $headers, true) && in_array('nomor_rekening', $headers, true)) {
            $score += 50;
        }

        return $score;
    }

    private function buildSourceIndexes(array $headers): array
    {
        $indexes = [];

        foreach ($headers as $index => $header) {
            if ($header === '' || $header === 'no' || array_key_exists($header, $indexes)) {
                continue;
            }

            $indexes[$header] = $index;
        }

        return $indexes;
    }

    private function findPosisiValue(array $sampleRows, int $headerLineNumber): ?string
    {
        $pendingPosisiMarker = false;

        foreach ($sampleRows as $row) {
            if ($row['line_number'] >= $headerLineNumber) {
                break;
            }

            $line = trim($row['line']);
            if ($line === '') {
                continue;
            }

            $cells = array_map(
                fn ($value) => trim((string) $value),
                $this->parseCsvLine($line)
            );

            $normalizedCells = array_map(
                fn ($value) => $this->normalizeHeader($value),
                $cells
            );

            if ($pendingPosisiMarker) {
                $candidate = $this->extractDateCandidateFromCells($cells);
                if ($candidate !== null) {
                    return $candidate;
                }

                if ($this->looksLikePosisiLabel($line, $normalizedCells)) {
                    continue;
                }

                $pendingPosisiMarker = false;
            }

            if ($this->looksLikePosisiLabel($line, $normalizedCells)) {
                $candidate = $this->extractDateCandidateFromCells(array_slice($cells, 1));
                if ($candidate !== null) {
                    return $candidate;
                }

                $inlineCandidate = $this->extractInlinePosisiValue($line);
                if ($inlineCandidate !== null) {
                    return $inlineCandidate;
                }

                $pendingPosisiMarker = true;
                continue;
            }
        }

        return null;
    }

    private function parseCsvLine(string $line): array
    {
        $line = preg_replace('/^\xEF\xBB\xBF/', '', rtrim($line, "\r\n"));

        if ($line === null || trim($line) === '') {
            return [];
        }

        $line = preg_replace('/;\s*$/', '', $line);
        $parsed = str_getcsv($line, self::COLUMN_DELIMITER);
        $parsed = $this->trimTrailingEmptyCells($parsed);

        if (count($parsed) === 1) {
            $single = trim((string) ($parsed[0] ?? ''));

            if (
                strlen($single) >= 2
                && str_starts_with($single, '"')
                && str_ends_with($single, '"')
            ) {
                $single = substr($single, 1, -1);
                $single = str_replace('""', '"', $single);
            }

            if ($single !== '' && substr_count($single, self::COLUMN_DELIMITER) >= 3) {
                $innerParsed = str_getcsv($single, self::COLUMN_DELIMITER);
                $innerParsed = $this->trimTrailingEmptyCells($innerParsed);

                if (count($innerParsed) > 1) {
                    return $innerParsed;
                }
            }
        }

        return $parsed;
    }

    private function trimTrailingEmptyCells(array $cells): array
    {
        while (!empty($cells) && trim((string) end($cells)) === '') {
            array_pop($cells);
        }

        return $cells;
    }

    private function normalizeHeader(?string $header): string
    {
        $header = trim((string) $header);
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = strtolower($header);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        return trim($header, '_');
    }

    private function mapCsvRow(array $context, array $data): ?array
    {
        if ($this->isEmptyCsvRow($data)) {
            return null;
        }

        $data = $this->alignDataRowWithHeaders($context['source_headers'], $data);
        if ($data === null) {
            return null;
        }

        $row = [];

        foreach ($context['headers'] as $column) {
            if ($column === 'posisi') {
                $row[] = $context['posisi'];
                continue;
            }

            $sourceIndex = $context['source_indexes'][$column] ?? null;
            $row[] = $this->normalizeCellValue($column, $sourceIndex !== null ? ($data[$sourceIndex] ?? null) : null);
        }

        return $row;
    }

    private function alignDataRowWithHeaders(array $sourceHeaders, array $data): ?array
    {
        $expectedCount = count($sourceHeaders);
        $actualCount = count($data);

        if ($actualCount === $expectedCount + 1 && isset($data[1])) {
            $marker = trim((string) $data[1]);
            if ($marker !== '' && preg_match('/^[A-Z]$/', $marker) === 1) {
                array_splice($data, 1, 1);
                $actualCount = count($data);
            }
        }

        if ($actualCount !== $expectedCount) {
            return null;
        }

        $rowNumber = trim((string) ($data[0] ?? ''));
        if ($rowNumber === '' || preg_match('/^\d+$/', $rowNumber) !== 1) {
            return null;
        }

        return $data;
    }

    private function looksLikePosisiLabel(string $line, array $normalizedCells): bool
    {
        if ($this->normalizeHeader($line) === 'posisi') {
            return true;
        }

        if (($normalizedCells[0] ?? null) === 'posisi') {
            return true;
        }

        return preg_match('/^\s*posisi\s*[:=|-]?\s*$/i', $line) === 1;
    }

    private function extractDateCandidateFromCells(array $cells): ?string
    {
        foreach ($cells as $cell) {
            $value = trim((string) $cell);
            if ($value === '') {
                continue;
            }

            if ($this->normalizeDateValue($value) !== null) {
                return $value;
            }
        }

        return null;
    }

    private function extractInlinePosisiValue(string $line): ?string
    {
        if (preg_match('/^\s*posisi\s*[:=|-]\s*(.+)$/i', $line, $matches) !== 1) {
            return null;
        }

        $candidate = trim($matches[1] ?? '');
        return $candidate !== '' ? $candidate : null;
    }

    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeCellValue(string $column, $value)
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if ($column === 'saldo_britama_kerjasama') {
            return $this->normalizeDecimalValue($value);
        }

        if (in_array($column, ['posisi', 'tanggal_pembuatan_rekening'], true)) {
            return $this->normalizeDateValue($value);
        }

        return $value;
    }

    private function normalizeDateValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse(str_replace('/', '-', $value))->format('Y-m-d');
        } catch (\Throwable $e) {
            foreach (['d-M-y', 'd-M-Y', 'j-M-y', 'j-M-Y', 'd/m/Y', 'j/n/Y', 'n/j/Y'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $value)->format('Y-m-d');
                } catch (\Throwable $inner) {
                }
            }

            return null;
        }
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

        if ($value === '' || $value === '-') {
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

    private function passesFilters(array $row, array $activeFilters): bool
    {
        foreach ($activeFilters as $colIndex => $allowedValues) {
            $value = trim((string) ($row[(int) $colIndex] ?? ''));
            if (!in_array($value, array_map(fn ($item) => trim((string) $item), (array) $allowedValues), true)) {
                return false;
            }
        }

        return true;
    }

    private function buildInsertRow(array $headers, array $row, array $selectedColumns, ?string $timestamp = null): ?array
    {
        $timestamp = $timestamp ?? now()->toDateTimeString();

        $insertRow = [
            'uniqueid_namareport' => uniqid('', true) . self::UNIQUE_SUFFIX,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        foreach ($selectedColumns as $index) {
            if (!isset($headers[$index])) {
                continue;
            }

            $column = $headers[$index];
            if (in_array($column, ['id', 'uniqueid_namareport'], true)) {
                continue;
            }

            $insertRow[$column] = $row[$index] ?? null;
        }

        return $this->hasMeaningfulImportData($insertRow, ['uniqueid_namareport', 'created_at', 'updated_at', 'periode', 'posisi'])
            ? $insertRow
            : null;
    }

    private function countFilteredRows(string $absolutePath, array $context, array $activeFilters, array $selectedColumns): int
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV.');
        }

        $totalRows = 0;
        $lineNumber = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                if ($lineNumber <= $context['header_line']) {
                    continue;
                }

                $row = $this->mapCsvRow($context, $this->parseCsvLine($line));
                if ($row === null || !$this->passesFilters($row, $activeFilters)) {
                    continue;
                }

                if ($this->buildInsertRow($context['headers'], $row, $selectedColumns) === null) {
                    continue;
                }

                $totalRows++;
            }
        } finally {
            fclose($handle);
        }

        return $totalRows;
    }

    private function createBulkLoadTempCsvPath(int $jobId): string
    {
        $directory = storage_path(self::BULK_LOAD_TEMP_DIR);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        return $directory . DIRECTORY_SEPARATOR . self::TABLE_NAME . '_' . $jobId . '_' . Str::random(8) . '.csv';
    }

    private function createFilteredCsvStage(
        string $absolutePath,
        array $context,
        array $activeFilters,
        array $selectedColumns,
        int $totalRows,
        callable $send
    ): array {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV Performance PIS.');
        }

        $stagingPath = $this->createBulkLoadTempCsvPath((int) (microtime(true) * 1000));
        $outputHandle = fopen($stagingPath, 'w');
        if ($outputHandle === false) {
            fclose($handle);
            throw new \RuntimeException('Gagal membuat file staging Performance PIS.');
        }

        $loadColumns = ['uniqueid_namareport', 'created_at', 'updated_at'];
        foreach ($selectedColumns as $index) {
            $column = $context['headers'][$index] ?? null;
            if (!$column || in_array($column, ['id', 'uniqueid_namareport'], true)) {
                continue;
            }

            $loadColumns[] = $column;
        }
        $loadColumns = array_values(array_unique($loadColumns));

        $rowsDone = 0;
        $lineNumber = 0;
        $lastProgressAt = 0;
        $startTime = microtime(true);
        $timestamp = now()->toDateTimeString();

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                if ($lineNumber <= $context['header_line']) {
                    continue;
                }

                $row = $this->mapCsvRow($context, $this->parseCsvLine($line));
                if ($row === null || !$this->passesFilters($row, $activeFilters)) {
                    continue;
                }

                $insertRow = $this->buildInsertRow($context['headers'], $row, $selectedColumns, $timestamp);
                if ($insertRow === null) {
                    continue;
                }

                $stagedRow = [];
                foreach ($loadColumns as $column) {
                    $value = $insertRow[$column] ?? null;
                    $stagedRow[] = $value === null ? '\N' : (string) $value;
                }

                fputcsv($outputHandle, $stagedRow);
                $rowsDone++;

                if ($rowsDone - $lastProgressAt >= self::STREAM_PROGRESS_EVERY) {
                    $lastProgressAt = $rowsDone;
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) ($rowsDone / $elapsed);
                    $percent = $totalRows > 0
                        ? min(92, 10 + (int) (($rowsDone / $totalRows) * 82))
                        : 80;

                    $send('progress', [
                        'percent' => $percent,
                        'message' => "Menyusun CSV staging Performance PIS... ({$speed} baris/detik)",
                        'rows_done' => $rowsDone,
                        'total' => $totalRows,
                        'speed' => $speed,
                    ]);
                }
            }
        } finally {
            fclose($handle);
            fclose($outputHandle);
        }

        return [
            'path' => $stagingPath,
            'rows_done' => $rowsDone,
            'columns' => $loadColumns,
            'timestamp' => $timestamp,
        ];
    }

    private function supportsNativeBulkLoad(): bool
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        try {
            $row = DB::selectOne("SHOW VARIABLES LIKE 'local_infile'");
            return strtoupper((string) ($row->Value ?? $row->value ?? 'OFF')) === 'ON';
        } catch (\Throwable $e) {
            Log::warning('Unable to verify local_infile support for Performance PIS: ' . $e->getMessage());
            return false;
        }
    }

    private function loadCsvIntoMysql(string $csvPath, string $tableName, array $columns): int
    {
        if (!file_exists($csvPath)) {
            throw new \RuntimeException('File staging CSV tidak ditemukan untuk bulk load.');
        }

        if (empty($columns)) {
            throw new \RuntimeException('Kolom bulk load kosong.');
        }

        $connection = config('database.default', 'mysql');
        $dbConfig = config("database.connections.{$connection}", []);
        $charset = $dbConfig['charset'] ?? 'utf8mb4';
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'] ?? '';
        $username = $dbConfig['username'] ?? '';
        $password = $dbConfig['password'] ?? '';
        $unixSocket = $dbConfig['unix_socket'] ?? '';

        $dsn = $unixSocket !== ''
            ? "mysql:unix_socket={$unixSocket};dbname={$database};charset={$charset}"
            : "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
        ]);

        $normalizedPath = str_replace('\\', '/', realpath($csvPath) ?: $csvPath);
        $quotedPath = $pdo->quote($normalizedPath);
        $quotedColumns = implode(', ', array_map(function (string $column) {
            return '`' . str_replace('`', '``', $column) . '`';
        }, $columns));

        $sql = "LOAD DATA LOCAL INFILE {$quotedPath} INTO TABLE `{$tableName}` "
            . "CHARACTER SET utf8mb4 "
            . "FIELDS TERMINATED BY ',' ENCLOSED BY '\"' "
            . "LINES TERMINATED BY '\\n' "
            . "({$quotedColumns})";

        $affected = $pdo->exec($sql);
        $pdo = null;

        if ($affected === false) {
            throw new \RuntimeException('LOAD DATA LOCAL INFILE gagal dieksekusi.');
        }

        return (int) $affected;
    }

    private function insertStagedCsvInBatches(string $csvPath, array $columns): array
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file staging CSV untuk fallback insert.');
        }

        $rows = [];
        $totalSuccess = 0;
        $totalFailed = 0;
        $lastError = '';

        try {
            while (($data = fgetcsv($handle, 0, self::COLUMN_DELIMITER)) !== false) {
                if (empty($data)) {
                    continue;
                }

                $row = [];
                foreach ($columns as $index => $column) {
                    $value = $data[$index] ?? null;
                    $row[$column] = $value === '\N' ? null : $value;
                }

                $rows[] = $row;

                if (count($rows) >= self::INSERT_BATCH_SIZE) {
                    $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastError);
                    $rows = [];
                }
            }
        } finally {
            fclose($handle);
        }

        if (!empty($rows)) {
            $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastError);
        }

        return [
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'last_error' => $lastError,
        ];
    }

    private function insertBatch(array $rows, int &$totalSuccess, int &$totalFailed, string &$lastErrorMsg): void
    {
        foreach (array_chunk($rows, 100) as $batch) {
            $batch = $this->allocateGapIdsForRows(self::TABLE_NAME, $batch);

            try {
                DB::table(self::TABLE_NAME)->insert($batch);
                $totalSuccess += count($batch);
            } catch (\Throwable $e) {
                $lastErrorMsg = Str::limit($e->getMessage(), 800, '...');
                Log::warning('Import Performance PIS batch insert failed: ' . $e->getMessage());

                foreach ($batch as $single) {
                    try {
                        DB::table(self::TABLE_NAME)->insert($single);
                        $totalSuccess++;
                    } catch (\Throwable $singleError) {
                        $totalFailed++;
                        $lastErrorMsg = Str::limit($singleError->getMessage(), 800, '...');
                    }
                }
            }
        }
    }

    private function cleanupUploadedFile(string $relativePath): void
    {
        try {
            Storage::delete($relativePath);
        } catch (\Throwable $e) {
            Log::warning('Gagal menghapus file Performance PIS sementara: ' . $e->getMessage());
        }
    }

    private function cleanupSuccessfulImportArtifacts(int $jobId, string $relativePath, array $extraPaths = []): void
    {
        try {
            app(ImportCleanupController::class)->cleanupSuccessfulJobArtifacts(
                $jobId,
                array_values(array_filter(array_merge([$relativePath], $extraPaths)))
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal menjalankan cleanup terpusat Performance PIS: ' . $e->getMessage(), [
                'job_id' => $jobId,
                'relative_path' => $relativePath,
            ]);
        }
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
}
