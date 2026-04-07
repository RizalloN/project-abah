<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportCasaBrilinkController extends Controller
{
    private const HEADER_MAP = [
        'row_num',
        'region',
        'rgdesc',
        'mainbr',
        'mbdesc',
        'branch',
        'brdesc',
        'kode_agen',
        'mid_code',
        'account',
        'keterangan',
        'sumber',
        'jml_nominal_casa',
        'textbox9',
        'cifno',
    ];

    public function upload(Request $request)
    {
        $request->validate([
            'id_report' => 'required',
            'file' => 'required|file|mimes:csv,txt',
            'periode' => 'required|date_format:Y-m',
        ]);

        $file = $request->file('file');
        $path = $file->store('casa_brilink_imports');

        session([
            'active_id_report' => $request->input('id_report'),
            'import_type' => 'casa_brilink',
            'casa_brilink_file' => $path,
            'casa_brilink_periode' => $request->input('periode'),
        ]);

        return redirect()->route('import.casabrilink.preview');
    }

    public function preview(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $relativePath = session('casa_brilink_file', $request->input('file_path'));
        $periodeInput = $request->input('periode', session('casa_brilink_periode'));

        if (!$relativePath || !$periodeInput) {
            return redirect()->route('import.index')->with('error', 'File atau periode CASA BRILINK tidak ditemukan. Silakan upload ulang.');
        }

        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return redirect()->route('import.index')->with('error', 'File CSV CASA BRILINK tidak ditemukan di server.');
        }

        $currentDelimiter = $request->input('delimiter', 'auto');

        try {
            $context = $this->buildCsvContext($absolutePath, $periodeInput, $currentDelimiter);
        } catch (\Throwable $e) {
            return redirect()->route('import.index')->with('error', 'Struktur CSV CASA BRILINK tidak dikenali: ' . $e->getMessage());
        }

        $previewData = [];
        $uniqueValues = [];
        foreach ($context['headers'] as $index => $header) {
            $uniqueValues[$index] = [];
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            return redirect()->route('import.index')->with('error', 'Gagal membuka file CSV CASA BRILINK.');
        }

        $lineNumber = 0;
        try {
            while (($data = fgetcsv($handle, 0, $context['delimiter'])) !== false) {
                $lineNumber++;

                if ($lineNumber === 1) {
                    continue;
                }

                $row = $this->mapCsvRow($context, $data);
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

        session(['casa_brilink_periode' => $periodeInput]);

        return view('import.preview', [
            'headers' => $context['headers'],
            'previewData' => $previewData,
            'filePath' => $relativePath,
            'formattedUniqueValues' => $formattedUniqueValues,
            'currentDelimiter' => $currentDelimiter,
            'processRoute' => route('import.casabrilink.process'),
            'previewRoute' => route('import.casabrilink.preview.refresh'),
            'initRoute' => route('import.casabrilink.init'),
            'streamRoute' => route('import.casabrilink.stream'),
            'backRoute' => route('import.index'),
            'manualPeriode' => $periodeInput,
            'manualPeriodeLabel' => Carbon::createFromFormat('Y-m', $periodeInput)->translatedFormat('F Y'),
            'manualPeriodeInputType' => 'month',
            'forceAllFiltersCheckedOnLoad' => true,
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
            'periode' => 'required|date_format:Y-m',
        ]);

        $relativePath = $request->input('file_path');
        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'File CSV CASA BRILINK tidak ditemukan di server.',
            ], 422);
        }

        $reportData = DB::table('nama_report')->where('id_report', session('active_id_report'))->first();
        $tableName = $reportData->table_name ?? '';
        $uniqueSuffix = $tableName === 'casa_brilink_edc' ? '_CBE' : '_CBW';

        if (!in_array($tableName, ['casa_brilink_web', 'casa_brilink_edc'], true)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Table tujuan CASA BRILINK tidak valid.',
            ], 422);
        }

        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json', '{}'), true) ?: [];

        try {
            $context = $this->buildCsvContext($absolutePath, $request->input('periode'), $request->input('delimiter', 'auto'));
            $totalRows = $this->countFilteredRows($absolutePath, $context, $activeFilters, $selectedColumns, $uniqueSuffix);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Struktur CSV CASA BRILINK tidak dikenali: ' . $e->getMessage(),
            ], 422);
        }

        if (DB::table($tableName)->whereDate('periode', $context['periode'])->exists()) {
            $this->cleanupUploadedFile($relativePath);

            return response()->json([
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => 'Data untuk periode <b>' . Carbon::parse($context['periode'])->translatedFormat('F Y') . '</b> sudah ada di tabel <b class="text-uppercase">' . $tableName . '</b>.',
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

        session([
            'casa_import_params' => [
                'job_id' => $jobId,
                'file_path' => $relativePath,
                'delimiter' => $request->input('delimiter', 'auto'),
                'selected_columns' => $selectedColumns,
                'active_filters' => $activeFilters,
                'table_name' => $tableName,
                'unique_suffix' => $uniqueSuffix,
                'periode' => $request->input('periode'),
                'total_rows' => $totalRows,
            ],
        ]);

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

        $params = session('casa_import_params', []);
        $jobId = (int) ($params['job_id'] ?? $request->query('job_id', 0));

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
                    $streamLock = Cache::lock('import_casa_stream_job_' . $jobId, 7200);

                    if (!$streamLock->get()) {
                        $job = DB::table('import_jobs')->where('id', $jobId)->first();

                        if ($job && in_array($job->status, ['completed', 'failed', 'failed_partial'], true)) {
                            $send('complete', [
                                'total_success' => (int) ($job->total_success ?? 0),
                                'total_failed' => (int) ($job->total_failed ?? 0),
                                'total_rows' => (int) ($job->total_files ?? 0),
                            ]);
                        } else {
                            $send('error', [
                                'message' => 'Job import CASA ini sudah sedang diproses pada koneksi lain.',
                            ]);
                        }
                        return;
                    }
                }

                $relativePath = $params['file_path'] ?? '';
                $absolutePath = Storage::path($relativePath);
                $selectedColumns = array_map('intval', $params['selected_columns'] ?? []);
                $activeFilters = $params['active_filters'] ?? [];
                $tableName = $params['table_name'] ?? '';
                $uniqueSuffix = $params['unique_suffix'] ?? '_CBW';
                $periode = $params['periode'] ?? '';
                $requestedDelimiter = $params['delimiter'] ?? 'auto';
                $totalRows = (int) ($params['total_rows'] ?? 0);

                if ($relativePath === '' || !file_exists($absolutePath)) {
                    $send('error', ['message' => 'File CSV CASA BRILINK tidak ditemukan di server.']);
                    return;
                }

                $context = $this->buildCsvContext($absolutePath, $periode, $requestedDelimiter);
                $handle = fopen($absolutePath, 'r');
                if ($handle === false) {
                    $send('error', ['message' => 'Gagal membuka file CSV CASA BRILINK.']);
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
                    'message' => 'Menyiapkan stream import CASA BRILINK...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                try {
                    while (($data = fgetcsv($handle, 0, $context['delimiter'])) !== false) {
                        $lineNumber++;

                        if ($lineNumber === 1) {
                            continue;
                        }

                        $row = $this->mapCsvRow($context, $data);
                        if ($row === null || !$this->passesFilters($row, $activeFilters)) {
                            continue;
                        }

                        $insertRow = $this->buildInsertRow($context['headers'], $row, $selectedColumns, $uniqueSuffix);
                        if ($insertRow === null) {
                            continue;
                        }

                        $rows[] = $insertRow;
                        $rowsDone++;

                        if (count($rows) >= 500) {
                            $this->insertBatch($tableName, $rows, $totalSuccess, $totalFailed, $lastErrorMsg);
                            $rows = [];
                        }

                        if ($rowsDone - $lastProgressAt >= 250) {
                            $lastProgressAt = $rowsDone;
                            $elapsed = max(microtime(true) - $startTime, 0.001);
                            $speed = (int) ($rowsDone / $elapsed);
                            $percent = $totalRows > 0
                                ? min(95, 12 + (int) (($rowsDone / $totalRows) * 83))
                                : 80;

                            $send('progress', [
                                'percent' => $percent,
                                'message' => "Menyimpan data CASA BRILINK... ({$speed} baris/detik)",
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
                    $this->insertBatch($tableName, $rows, $totalSuccess, $totalFailed, $lastErrorMsg);
                }

                DB::table('import_jobs')->where('id', $jobId)->update([
                    'status' => $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed',
                    'total_files' => $rowsDone,
                    'total_success' => $totalSuccess,
                    'total_failed' => $totalFailed,
                    'updated_at' => now(),
                ]);

                $this->cleanupUploadedFile($relativePath);

                $send('progress', [
                    'percent' => 98,
                    'message' => 'Finalisasi status import CASA BRILINK...',
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
                Log::error('CASA BRILINK STREAM ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
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
                        Log::warning('Failed to release CASA import stream lock for job ' . $jobId . ': ' . $e->getMessage());
                    }
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
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
            'periode' => 'required|date_format:Y-m',
        ]);

        $relativePath = $request->input('file_path');
        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'File CSV CASA BRILINK tidak ditemukan di server.',
            ], 422);
        }

        $reportData = DB::table('nama_report')->where('id_report', session('active_id_report'))->first();
        $tableName = $reportData->table_name ?? '';
        $uniqueSuffix = $tableName === 'casa_brilink_edc' ? '_CBE' : '_CBW';

        if (!in_array($tableName, ['casa_brilink_web', 'casa_brilink_edc'], true)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Table tujuan CASA BRILINK tidak valid.',
            ], 422);
        }

        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json', '{}'), true) ?: [];

        try {
            $context = $this->buildCsvContext($absolutePath, $request->input('periode'), $request->input('delimiter', 'auto'));
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Struktur CSV CASA BRILINK tidak dikenali: ' . $e->getMessage(),
            ], 422);
        }

        if (DB::table($tableName)->whereDate('periode', $context['periode'])->exists()) {
            $this->cleanupUploadedFile($relativePath);

            return response()->json([
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => 'Data untuk periode <b>' . Carbon::parse($context['periode'])->translatedFormat('F Y') . '</b> sudah ada di tabel <b class="text-uppercase">' . $tableName . '</b>.',
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
                'text' => 'Gagal membuka file CSV CASA BRILINK.',
            ], 422);
        }

        $lineNumber = 0;
        try {
            while (($data = fgetcsv($handle, 0, $context['delimiter'])) !== false) {
                $lineNumber++;

                if ($lineNumber === 1) {
                    continue;
                }

                $row = $this->mapCsvRow($context, $data);
                if ($row === null || !$this->passesFilters($row, $activeFilters)) {
                    continue;
                }

                $insertRow = $this->buildInsertRow($context['headers'], $row, $selectedColumns, $uniqueSuffix);
                if ($insertRow === null) {
                    continue;
                }

                $rows[] = $insertRow;
                $totalRows++;

                if (count($rows) >= 500) {
                    $this->insertBatch($tableName, $rows, $totalSuccess, $totalFailed, $lastErrorMsg);
                    $rows = [];
                }
            }
        } finally {
            fclose($handle);
        }

        if (!empty($rows)) {
            $this->insertBatch($tableName, $rows, $totalSuccess, $totalFailed, $lastErrorMsg);
        }

        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed',
            'total_files' => $totalRows,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'updated_at' => now(),
        ]);

        $this->cleanupUploadedFile($relativePath);

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
            'text' => "Sebanyak {$totalSuccess} baris data telah sukses masuk ke tabel <b class='text-uppercase'>{$tableName}</b>.",
        ]);
    }

    private function buildCsvContext(string $path, string $periodeInput, string $requestedDelimiter = 'auto'): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV.');
        }

        try {
            $headerLine = fgets($handle);
        } finally {
            fclose($handle);
        }

        if ($headerLine === false) {
            throw new \RuntimeException('Baris header CSV tidak ditemukan.');
        }

        $headerLine = trim(preg_replace('/^\xEF\xBB\xBF/', '', $headerLine));
        if ($headerLine === '') {
            throw new \RuntimeException('Baris header CSV kosong.');
        }

        $delimiter = $requestedDelimiter === 'auto'
            ? $this->detectDelimiter($headerLine)
            : $requestedDelimiter;

        $headers = str_getcsv($headerLine, $delimiter);
        $normalizedHeaders = array_map(fn ($header) => $this->normalizeHeader($header), $headers);

        if ($normalizedHeaders !== self::HEADER_MAP) {
            throw new \RuntimeException('Header CSV tidak sesuai format CASA BRILINK yang diharapkan.');
        }

        return [
            'delimiter' => $delimiter,
            'headers' => array_merge(['periode'], array_values(array_filter(
                $normalizedHeaders,
                fn ($header) => $header !== 'row_num'
            ))),
            'source_headers' => $normalizedHeaders,
            'periode' => Carbon::createFromFormat('Y-m', $periodeInput)->endOfMonth()->toDateString(),
        ];
    }

    private function detectDelimiter(string $line): string
    {
        $delimiters = [',', ';', '|', "\t"];
        $bestDelimiter = ',';
        $bestCount = -1;

        foreach ($delimiters as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
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

        $expectedCount = count($context['source_headers']);
        if (count($data) < $expectedCount) {
            $data = array_pad($data, $expectedCount, null);
        } elseif (count($data) > $expectedCount) {
            $data = array_slice($data, 0, $expectedCount);
        }

        $row = [$context['periode']];
        foreach ($context['source_headers'] as $index => $column) {
            if ($column === 'row_num') {
                continue;
            }

            $row[] = $this->normalizeCellValue($column, $data[$index] ?? null);
        }

        return $row;
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

        if (in_array($column, ['jml_nominal_casa', 'textbox9'], true)) {
            return $this->normalizeDecimalValue($value);
        }

        return $value;
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

    private function buildInsertRow(array $headers, array $row, array $selectedColumns, string $uniqueSuffix): ?array
    {
        $insertRow = [
            'uniqueid_namareport' => uniqid('', true) . $uniqueSuffix,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($selectedColumns as $index) {
            if (!isset($headers[$index])) {
                continue;
            }

            $column = $headers[$index];
            if (in_array($column, ['id', 'uniqueid_namareport', 'row_num'], true)) {
                continue;
            }

            $insertRow[$column] = $row[$index] ?? null;
        }

        return $this->hasMeaningfulImportData($insertRow, ['uniqueid_namareport', 'created_at', 'updated_at', 'periode', 'posisi'])
            ? $insertRow
            : null;
    }

    private function insertBatch(string $tableName, array $rows, int &$totalSuccess, int &$totalFailed, string &$lastErrorMsg): void
    {
        foreach (array_chunk($rows, 100) as $batch) {
            try {
                DB::table($tableName)->insert($batch);
                $totalSuccess += count($batch);
            } catch (\Throwable $e) {
                $lastErrorMsg = Str::limit($e->getMessage(), 800, '...');
                Log::warning('Import CASA BRILINK batch insert failed: ' . $e->getMessage());

                foreach ($batch as $single) {
                    try {
                        DB::table($tableName)->insert($single);
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
            Log::warning('Gagal menghapus file CASA BRILINK sementara: ' . $e->getMessage());
        }
    }

    private function countFilteredRows(string $absolutePath, array $context, array $activeFilters, array $selectedColumns, string $uniqueSuffix): int
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV CASA BRILINK.');
        }

        $totalRows = 0;
        $lineNumber = 0;

        try {
            while (($data = fgetcsv($handle, 0, $context['delimiter'])) !== false) {
                $lineNumber++;

                if ($lineNumber === 1) {
                    continue;
                }

                $row = $this->mapCsvRow($context, $data);
                if ($row === null || !$this->passesFilters($row, $activeFilters)) {
                    continue;
                }

                $insertRow = $this->buildInsertRow($context['headers'], $row, $selectedColumns, $uniqueSuffix);
                if ($insertRow === null) {
                    continue;
                }

                $totalRows++;
            }
        } finally {
            fclose($handle);
        }

        return $totalRows;
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
