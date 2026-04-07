<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Import\Concerns\AllocatesGapIds;
use App\Support\ReportDataSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportReportPhController extends Controller
{
    use AllocatesGapIds;

    private const TABLE_NAME = 'lw325_ph';
    private const UNIQUE_SUFFIX = '_RPH';
    private const REPORT_LABEL = 'Report Nominatif Rekening Pinjaman PH';
    private const COLUMN_DELIMITER = ',';
    private const ROW_NUMBER_HEADERS = ['textbox3', 'no', 'row_num', 'nomor_baris', 'rownumber'];
    private const EXPECTED_HEADERS = [
        'textbox3', 'periode', 'acctno', 'kanwil', 'kanca', 'unit', 'nama_debitur', 'cif1',
        'fksegmen', 'segmen_dashboard', 'description', 'produk_dashboard', 'tgl_ph',
        'tgl_realisasi', 'curtyp', 'saldo_pertama_ph_pokok', 'saldo_pertama_ph_bunga',
        'besar_realisasi', 'plafon', 'jw', 'at', 'cif', 'pokok', 'bunga', 'angpok',
        'angbung', 'sisapok', 'sisabun', 'clmamt1', 'clmapr1', 'os_penuh_berjalan1',
        'kecamatan_t_tinggal', 'kelurahan_t_tinggal', 'kodepos_t_tinggal', 'kecamatan_t_usaha',
        'kelurahan_t_usaha', 'kodepos_t_usaha', 'pn_pengelola', 'pn_pemrakarsa',
        'pn_referral', 'pn_restruk', 'pn_pengelola2', 'pn_pemutus', 'pn_crm', 'pn_crr1',
        'pn_referral_naik_kelas', 'jumlah_pn', 'jumlah_pn_all', 'saldo_pertama_kali_charge_off',
        'deffered_bunga', 'sai_deffered', 'sai_tunggakan', 'deffered_bunga_ph',
        'sai_tunggakan_ph', 'sai_deffered_ph', 'wcbal', 'waccint', 'wadvpmt', 'wpenint',
        'wmisc', 'wothchg', 'wpmtamt', 'wpstdt', 'wpstdt6', 'wamount', 'flag_klaim',
        'clmamt', 'clmapr',
    ];
    private const TARGET_COLUMNS = [
        'periode', 'acctno', 'kanwil', 'kanca', 'unit', 'nama_debitur', 'cif1', 'fksegmen',
        'segmen_dashboard', 'description', 'produk_dashboard', 'tgl_ph', 'tgl_realisasi',
        'curtyp', 'saldo_pertama_ph_pokok', 'saldo_pertama_ph_bunga', 'besar_realisasi',
        'plafon', 'jw', 'at', 'cif', 'pokok', 'bunga', 'angpok', 'angbung', 'sisapok',
        'sisabun', 'clmamt1', 'clmapr1', 'os_penuh_berjalan1', 'kecamatan_t_tinggal',
        'kelurahan_t_tinggal', 'kodepos_t_tinggal', 'kecamatan_t_usaha', 'kelurahan_t_usaha',
        'kodepos_t_usaha', 'pn_pengelola', 'pn_pemrakarsa', 'pn_referral', 'pn_restruk',
        'pn_pengelola2', 'pn_pemutus', 'pn_crm', 'pn_crr1', 'pn_referral_naik_kelas',
        'jumlah_pn', 'jumlah_pn_all', 'saldo_pertama_kali_charge_off', 'deffered_bunga',
        'sai_deffered', 'sai_tunggakan', 'deffered_bunga_ph', 'sai_tunggakan_ph',
        'sai_deffered_ph', 'wcbal', 'waccint', 'wadvpmt', 'wpenint', 'wmisc', 'wothchg',
        'wpmtamt', 'wpstdt', 'wpstdt6', 'wamount', 'flag_klaim', 'clmamt', 'clmapr',
    ];
    private const DATE_COLUMNS = ['periode', 'tgl_ph', 'tgl_realisasi', 'wpstdt', 'wpstdt6'];
    private const INTEGER_COLUMNS = ['jw', 'at', 'jumlah_pn', 'jumlah_pn_all'];
    private const DECIMAL_COLUMNS = [
        'saldo_pertama_ph_pokok', 'saldo_pertama_ph_bunga', 'besar_realisasi', 'plafon',
        'pokok', 'bunga', 'angpok', 'angbung', 'sisapok', 'sisabun', 'clmamt1', 'clmapr1',
        'os_penuh_berjalan1', 'saldo_pertama_kali_charge_off', 'deffered_bunga',
        'sai_deffered', 'sai_tunggakan', 'deffered_bunga_ph', 'sai_tunggakan_ph',
        'sai_deffered_ph', 'wcbal', 'waccint', 'wadvpmt', 'wpenint', 'wmisc', 'wothchg',
        'wpmtamt', 'wamount', 'clmamt', 'clmapr',
    ];

    public function upload(Request $request)
    {
        $request->validate([
            'id_report' => 'required',
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('file')->store('report_ph_imports');

        session([
            'active_id_report' => $request->input('id_report'),
            'import_type' => 'report_ph',
            'report_ph_file' => $path,
        ]);

        return redirect()->route('import.reportph.preview');
    }

    public function preview(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $relativePath = session('report_ph_file', $request->input('file_path'));
        if (!$relativePath) {
            return redirect()->route('import.index')->with('error', 'File import ' . self::REPORT_LABEL . ' tidak ditemukan. Silakan upload ulang.');
        }

        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return redirect()->route('import.index')->with('error', 'File CSV ' . self::REPORT_LABEL . ' tidak ditemukan di server.');
        }

        try {
            $context = $this->buildCsvContext($absolutePath);
        } catch (\Throwable $e) {
            return redirect()->route('import.index')->with('error', 'Struktur CSV ' . self::REPORT_LABEL . ' tidak dikenali: ' . $e->getMessage());
        }

        $previewData = [];
        $uniqueValues = [];
        foreach ($context['headers'] as $index => $header) {
            $uniqueValues[$index] = [];
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            return redirect()->route('import.index')->with('error', 'Gagal membuka file CSV ' . self::REPORT_LABEL . '.');
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

                if (count($previewData) < 2500) {
                    $previewData[] = $row;
                }

                foreach ($row as $colIndex => $value) {
                    if (!isset($uniqueValues[$colIndex]) || count($uniqueValues[$colIndex]) > 5000) {
                        continue;
                    }

                    $uniqueValues[$colIndex][trim((string) ($value ?? ''))] = true;
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
            'currentDelimiter' => self::COLUMN_DELIMITER,
            'processRoute' => route('import.reportph.process'),
            'previewRoute' => route('import.reportph.preview.refresh'),
            'initRoute' => route('import.reportph.init'),
            'streamRoute' => route('import.reportph.stream'),
            'backRoute' => route('import.index'),
            'lockDelimiterSelector' => true,
            'fixedDelimiterLabel' => 'Koma ( , )',
            'disableArea6AutoFilter' => true,
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
        ]);

        $relativePath = $request->input('file_path');
        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'File CSV ' . self::REPORT_LABEL . ' tidak ditemukan di server.',
            ], 422);
        }

        if (!$this->isValidReportSelection()) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Report yang dipilih tidak mengarah ke tabel ' . self::TABLE_NAME . '.',
            ], 422);
        }

        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json', '{}'), true) ?: [];

        try {
            $context = $this->buildCsvContext($absolutePath);
            $totalRows = $this->countFilteredRows($absolutePath, $context, $activeFilters, $selectedColumns);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Struktur CSV ' . self::REPORT_LABEL . ' tidak dikenali: ' . $e->getMessage(),
            ], 422);
        }

        if (!$context['periode']) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Kolom PERIODE pada CSV tidak valid atau kosong.',
            ], 422);
        }

        if ($totalRows === 0) {
            return response()->json([
                'status' => 'warning',
                'title' => 'Tidak Ada Data',
                'text' => 'Tidak ada baris yang lolos filter untuk diimport.',
            ], 422);
        }

        if (DB::table(self::TABLE_NAME)->whereDate('periode', $context['periode'])->exists()) {
            $this->cleanupUploadedFile($relativePath);

            return response()->json([
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => 'Data untuk periode <b>' . Carbon::parse($context['periode'])->translatedFormat('d F Y') . '</b> sudah ada di tabel <b class="text-uppercase">' . self::TABLE_NAME . '</b>.',
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
            'total_rows' => $totalRows,
        ];
        session(['report_ph_import_params' => $importParams]);
        Cache::put('report_ph_import_params_' . $jobId, $importParams, now()->addHours(4));

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

        $sessionParams = session('report_ph_import_params', []);
        $jobId = (int) ($sessionParams['job_id'] ?? $request->query('job_id', 0));
        $params = Cache::get('report_ph_import_params_' . $jobId, $sessionParams);

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
                    $streamLock = Cache::lock('import_report_ph_stream_job_' . $jobId, 7200);

                    if (!$streamLock->get()) {
                        $job = DB::table('import_jobs')->where('id', $jobId)->first();

                        if ($job && in_array($job->status, ['completed', 'failed', 'failed_partial'], true)) {
                            $send('complete', [
                                'total_success' => (int) ($job->total_success ?? 0),
                                'total_failed' => (int) ($job->total_failed ?? 0),
                                'total_rows' => (int) ($job->total_files ?? 0),
                            ]);
                        } else {
                            $send('error', ['message' => 'Job import ' . self::REPORT_LABEL . ' ini sudah sedang diproses pada koneksi lain.']);
                        }
                        return;
                    }
                }

                $relativePath = $params['file_path'] ?? '';
                $absolutePath = Storage::path($relativePath);
                $selectedColumns = array_map('intval', $params['selected_columns'] ?? []);
                $activeFilters = $params['active_filters'] ?? [];
                $totalRows = (int) ($params['total_rows'] ?? 0);

                if ($relativePath === '' || !file_exists($absolutePath)) {
                    $send('error', ['message' => 'File CSV ' . self::REPORT_LABEL . ' tidak ditemukan di server.']);
                    return;
                }

                $context = $this->buildCsvContext($absolutePath);
                $handle = fopen($absolutePath, 'r');
                if ($handle === false) {
                    $send('error', ['message' => 'Gagal membuka file CSV ' . self::REPORT_LABEL . '.']);
                    return;
                }

                $rows = [];
                $rowsDone = 0;
                $totalSuccess = 0;
                $totalFailed = 0;
                $lastErrorMsg = '';
                $lineNumber = 0;
                $startTime = microtime(true);
                $lastProgressAt = 0;

                $send('progress', [
                    'percent' => 5,
                    'message' => 'Menyiapkan stream import ' . self::REPORT_LABEL . '...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

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

                        $insertRow = $this->buildInsertRow($context['headers'], $row, $selectedColumns);
                        if ($insertRow === null) {
                            continue;
                        }

                        $rows[] = $insertRow;
                        $rowsDone++;

                        if (count($rows) >= 1000) {
                            $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastErrorMsg);
                            $rows = [];
                        }

                        if ($rowsDone - $lastProgressAt >= 500) {
                            $lastProgressAt = $rowsDone;
                            $elapsed = max(microtime(true) - $startTime, 0.001);
                            $speed = (int) ($rowsDone / $elapsed);
                            $percent = $totalRows > 0 ? min(95, 12 + (int) (($rowsDone / $totalRows) * 83)) : 80;

                            $send('progress', [
                                'percent' => $percent,
                                'message' => "Menyimpan data " . self::REPORT_LABEL . "... ({$speed} baris/detik)",
                                'rows_done' => $rowsDone,
                                'total' => $totalRows,
                                'speed' => $speed,
                            ]);
                        }
                    }
                } finally {
                    fclose($handle);
                }

                if (!empty($rows)) {
                    $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastErrorMsg);
                }

                DB::table('import_jobs')->where('id', $jobId)->update([
                    'status' => $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed',
                    'total_files' => $rowsDone,
                    'total_success' => $totalSuccess,
                    'total_failed' => $totalFailed,
                    'updated_at' => now(),
                ]);

                $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath);

                $send('progress', [
                    'percent' => 98,
                    'message' => 'Finalisasi status import ' . self::REPORT_LABEL . '...',
                    'rows_done' => $rowsDone,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $send('complete', [
                    'total_success' => $totalSuccess,
                    'total_failed' => $totalFailed,
                    'total_rows' => $rowsDone,
                    'error_message' => $lastErrorMsg,
                ]);
            } catch (\Throwable $e) {
                Log::error(self::REPORT_LABEL . ' STREAM ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);
                }
                $send('error', ['message' => 'Fatal Error: ' . $e->getMessage()]);
            } finally {
                if ($streamLock) {
                    try {
                        $streamLock->release();
                    } catch (\Throwable $e) {
                        Log::warning('Failed to release ' . self::REPORT_LABEL . ' import lock for job ' . $jobId . ': ' . $e->getMessage());
                    }
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function processImport(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        DB::disableQueryLog();

        $request->validate([
            'file_path' => 'required|string',
            'selected_columns' => 'required|array|min:1',
            'active_filters_json' => 'nullable|string',
            'delimiter' => 'required|string',
        ]);

        $relativePath = $request->input('file_path');
        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'File CSV ' . self::REPORT_LABEL . ' tidak ditemukan di server.',
            ], 422);
        }

        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json', '{}'), true) ?: [];

        try {
            $context = $this->buildCsvContext($absolutePath);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Struktur CSV ' . self::REPORT_LABEL . ' tidak dikenali: ' . $e->getMessage(),
            ], 422);
        }

        if (!$this->isValidReportSelection()) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Report yang dipilih tidak mengarah ke tabel ' . self::TABLE_NAME . '.',
            ], 422);
        }

        if (!$context['periode']) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Kolom PERIODE pada CSV tidak valid atau kosong.',
            ], 422);
        }

        if (DB::table(self::TABLE_NAME)->whereDate('periode', $context['periode'])->exists()) {
            $this->cleanupUploadedFile($relativePath);

            return response()->json([
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => 'Data untuk periode <b>' . Carbon::parse($context['periode'])->translatedFormat('d F Y') . '</b> sudah ada di tabel <b class="text-uppercase">' . self::TABLE_NAME . '</b>.',
            ], 422);
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Gagal membuka file CSV ' . self::REPORT_LABEL . '.',
            ], 422);
        }

        $rows = [];
        $totalSuccess = 0;
        $totalFailed = 0;
        $lastErrorMsg = '';
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

                $insertRow = $this->buildInsertRow($context['headers'], $row, $selectedColumns);
                if ($insertRow === null) {
                    continue;
                }

                $rows[] = $insertRow;

                if (count($rows) >= 1000) {
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

        if ($totalFailed === 0) {
            $this->cleanupUploadedFile($relativePath);
        }

        return response()->json([
            'status' => $totalFailed > 0 ? 'warning' : 'success',
            'title' => $totalFailed > 0 ? 'Import Memiliki Kendala!' : 'Berhasil!',
            'text' => $totalFailed > 0
                ? "Berhasil: {$totalSuccess} baris.<br>Gagal: {$totalFailed} baris." . ($lastErrorMsg !== '' ? "<br><br><b>Info MySQL:</b><br><small class='text-danger'>" . htmlspecialchars($lastErrorMsg, ENT_QUOTES) . '</small>' : '')
                : "Sebanyak {$totalSuccess} baris data telah sukses masuk ke tabel <b class='text-uppercase'>" . self::TABLE_NAME . '</b>.',
        ]);
    }

    private function buildCsvContext(string $path): array
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
            throw new \RuntimeException('Baris header CSV ' . self::REPORT_LABEL . ' tidak ditemukan.');
        }

        $periode = $this->findPeriodeValue($path, $structure['header_row']['line_number'], $structure['parsed_headers']);

        return [
            'delimiter' => self::COLUMN_DELIMITER,
            'header_line' => $structure['header_row']['line_number'],
            'source_headers' => $structure['parsed_headers'],
            'source_indexes' => $this->buildSourceIndexes($structure['parsed_headers']),
            'headers' => self::TARGET_COLUMNS,
            'periode' => $periode,
        ];
    }

    private function detectCsvStructure(array $sampleRows): ?array
    {
        $bestCandidate = null;

        foreach ($sampleRows as $row) {
            $parsedHeaders = array_map(
                fn ($value) => $this->normalizeHeader($value),
                $this->parseCsvLine($row['line'])
            );

            $parsedHeaders = array_values(array_filter($parsedHeaders, fn ($value) => $value !== ''));
            if (empty($parsedHeaders)) {
                continue;
            }

            $score = $this->scoreHeaderCandidate($parsedHeaders);
            if ($score <= 0) {
                continue;
            }

            $candidate = [
                'header_row' => $row,
                'parsed_headers' => $parsedHeaders,
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

        if (in_array('periode', $headers, true) && in_array('acctno', $headers, true) && in_array('tgl_ph', $headers, true)) {
            $score += 50;
        }

        return $score;
    }

    private function buildSourceIndexes(array $headers): array
    {
        $indexes = [];

        foreach ($headers as $index => $header) {
            if ($header === '' || in_array($header, self::ROW_NUMBER_HEADERS, true) || array_key_exists($header, $indexes)) {
                continue;
            }

            $indexes[$header] = $index;
        }

        return $indexes;
    }

    private function findPeriodeValue(string $path, int $headerLineNumber, array $sourceHeaders): ?string
    {
        $periodeIndex = array_search('periode', $sourceHeaders, true);
        if ($periodeIndex === false) {
            return null;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return null;
        }

        try {
            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                if ($lineNumber <= $headerLineNumber) {
                    continue;
                }

                $row = $this->parseCsvLine($line);
                if ($this->isEmptyCsvRow($row)) {
                    continue;
                }

                $row = $this->alignDataRowWithHeaders($sourceHeaders, $row);
                if ($row === null) {
                    continue;
                }

                return $this->normalizeDateValue($row[$periodeIndex] ?? null);
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    private function countFilteredRows(string $path, array $context, array $activeFilters, array $selectedColumns): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV.');
        }

        $count = 0;
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

                if ($this->buildInsertRow($context['headers'], $row, $selectedColumns) !== null) {
                    $count++;
                }
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    private function parseCsvLine(string $line): array
    {
        $line = preg_replace('/^\xEF\xBB\xBF/', '', rtrim($line, "\r\n"));

        if ($line === null || trim($line) === '') {
            return [];
        }

        $parsed = $this->trimTrailingEmptyCells(str_getcsv($line, self::COLUMN_DELIMITER));

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
                $innerParsed = $this->trimTrailingEmptyCells(str_getcsv($single, self::COLUMN_DELIMITER));

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
            $sourceIndex = $context['source_indexes'][$column] ?? null;
            $row[] = $this->normalizeCellValue($column, $sourceIndex !== null ? ($data[$sourceIndex] ?? null) : null);
        }

        return $row;
    }

    private function alignDataRowWithHeaders(array $sourceHeaders, array $data): ?array
    {
        if (count($data) !== count($sourceHeaders)) {
            return null;
        }

        $rowNumberIndex = null;
        foreach ($sourceHeaders as $index => $header) {
            if (in_array($header, self::ROW_NUMBER_HEADERS, true)) {
                $rowNumberIndex = $index;
                break;
            }
        }

        if ($rowNumberIndex !== null) {
            $rowNumber = trim((string) ($data[$rowNumberIndex] ?? ''));
            if ($rowNumber === '' || preg_match('/^\d+$/', $rowNumber) !== 1) {
                return null;
            }
        }

        return $data;
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

        if (in_array($column, self::DATE_COLUMNS, true)) {
            return $this->normalizeDateValue($value);
        }

        if (in_array($column, self::INTEGER_COLUMNS, true)) {
            return $this->normalizeIntegerValue($value);
        }

        if (in_array($column, self::DECIMAL_COLUMNS, true)) {
            return $this->normalizeDecimalValue($value);
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

        foreach (['n/j/Y g:i:s A', 'm/d/Y g:i:s A', 'd/m/Y H:i:s', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeIntegerValue($value): ?int
    {
        $normalized = $this->normalizeDecimalValue($value);
        if ($normalized === null) {
            return null;
        }

        return (int) round((float) $normalized);
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

    private function buildInsertRow(array $headers, array $row, array $selectedColumns): ?array
    {
        $insertRow = [
            'uniqueid_namareport' => (string) Str::uuid() . self::UNIQUE_SUFFIX,
            'created_at' => now(),
            'updated_at' => now(),
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

        return $this->hasMeaningfulImportData($insertRow, ['uniqueid_namareport', 'created_at', 'updated_at', 'periode'])
            ? $insertRow
            : null;
    }

    private function insertBatch(array $rows, int &$totalSuccess, int &$totalFailed, string &$lastErrorMsg): void
    {
        foreach (array_chunk($rows, 500) as $batch) {
            $batch = $this->allocateGapIdsForRows(self::TABLE_NAME, $batch);

            try {
                DB::table(self::TABLE_NAME)->insert($batch);
                $totalSuccess += count($batch);
            } catch (\Throwable $e) {
                $lastErrorMsg = Str::limit($e->getMessage(), 800, '...');
                Log::warning('Import ' . self::REPORT_LABEL . ' batch insert failed: ' . $e->getMessage());

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
            Log::warning('Gagal menghapus file sementara ' . self::REPORT_LABEL . ': ' . $e->getMessage());
        }
    }

    private function cleanupSuccessfulImportArtifacts(int $jobId, string $relativePath): void
    {
        try {
            app(ReportDataSyncService::class)->syncImportedTable(self::TABLE_NAME, jobId: $jobId, source: static::class);
            app(ImportCleanupController::class)->cleanupSuccessfulJobArtifacts($jobId, [$relativePath]);
        } catch (\Throwable $e) {
            Log::warning('Gagal menjalankan cleanup terpusat ' . self::REPORT_LABEL . ': ' . $e->getMessage(), [
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

    private function isValidReportSelection(): bool
    {
        $tableName = DB::table('nama_report')
            ->where('id_report', session('active_id_report'))
            ->value('table_name');

        return $tableName === self::TABLE_NAME;
    }
}
