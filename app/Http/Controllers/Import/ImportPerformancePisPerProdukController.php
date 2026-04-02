<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportPerformancePisPerProdukController extends Controller
{
    private const TABLE_NAME = 'performance_pis_per_produk';
    private const UNIQUE_SUFFIX = '_PISPP';
    private const HEADER_LINE = 4;

    public function upload(Request $request)
    {
        $request->validate([
            'id_report' => 'required',
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $path = $file->store('performance_pis_imports');

        session([
            'active_id_report' => $request->input('id_report'),
            'import_type' => 'performance_pis',
            'performance_pis_file' => $path,
        ]);

        return redirect()->route('import.performancepis.preview');
    }

    public function preview(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $relativePath = session('performance_pis_file', $request->input('file_path'));
        if (!$relativePath) {
            return redirect()->route('import.index')->with('error', 'File import tidak ditemukan. Silakan upload ulang.');
        }

        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return redirect()->route('import.index')->with('error', 'File CSV tidak ditemukan di server.');
        }

        $currentDelimiter = $request->input('delimiter', 'auto');
        try {
            $context = $this->buildCsvContext($absolutePath, $currentDelimiter);
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
            while (($data = fgetcsv($handle, 0, $context['delimiter'])) !== false) {
                $lineNumber++;

                if ($lineNumber <= $context['header_line']) {
                    continue;
                }

                $row = $this->mapCsvRow($context, $data);
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
            'currentDelimiter' => $currentDelimiter,
            'processRoute' => route('import.performancepis.process'),
            'previewRoute' => route('import.performancepis.preview.refresh'),
            'backRoute' => route('import.index'),
            'disableArea6AutoFilter' => true,
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
            $context = $this->buildCsvContext($absolutePath, $request->input('delimiter', 'auto'));
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
            while (($data = fgetcsv($handle, 0, $context['delimiter'])) !== false) {
                $lineNumber++;

                if ($lineNumber <= $context['header_line']) {
                    continue;
                }

                $row = $this->mapCsvRow($context, $data);
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
            'text' => "Sebanyak {$totalSuccess} baris data telah sukses masuk ke tabel <b class='text-uppercase'>" . self::TABLE_NAME . '</b>.',
        ]);
    }

    private function buildCsvContext(string $path, string $requestedDelimiter = 'auto'): array
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

        $delimiter = $requestedDelimiter === 'auto'
            ? $this->detectDelimiterFromSample($sampleRows)
            : $requestedDelimiter;

        $headerRow = collect($sampleRows)->firstWhere('line_number', self::HEADER_LINE);
        if (!$headerRow) {
            throw new \RuntimeException('Baris header ke-' . self::HEADER_LINE . ' tidak ditemukan pada file CSV.');
        }

        if (substr_count($headerRow['line'], $delimiter) <= 0) {
            throw new \RuntimeException('Baris ke-4 tidak terbaca sebagai header CSV yang valid.');
        }

        $rawHeaders = str_getcsv($headerRow['line'], $delimiter);
        $normalizedHeaders = [];
        foreach ($rawHeaders as $header) {
            $normalizedHeaders[] = $this->normalizeHeader($header);
        }

        $posisiRaw = null;
        if (
            count($sampleRows) >= 2 &&
            strtolower(trim($sampleRows[0]['line'])) === 'posisi'
        ) {
            $posisiRaw = $sampleRows[1]['line'];
        }

        return [
            'delimiter' => $delimiter,
            'header_line' => self::HEADER_LINE,
            'source_headers' => $normalizedHeaders,
            'headers' => array_merge(['posisi'], $normalizedHeaders),
            'posisi' => $this->normalizeDateValue($posisiRaw),
        ];
    }

    private function detectDelimiterFromSample(array $sampleRows): string
    {
        $delimiters = [',', ';', '|', "\t"];
        $bestDelimiter = ',';
        $bestCount = -1;

        foreach ($delimiters as $delimiter) {
            $currentMax = 0;
            foreach ($sampleRows as $row) {
                $currentMax = max($currentMax, substr_count($row['line'], $delimiter));
            }

            if ($currentMax > $bestCount) {
                $bestCount = $currentMax;
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

        $row = [$context['posisi']];
        foreach ($context['source_headers'] as $index => $column) {
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

        if ($column === 'saldo_britama_kerjasama') {
            return $this->normalizeDecimalValue($value);
        }

        if (in_array($column, ['posisi', 'tanggal_pembuatan_rekening'], true)) {
            return $this->normalizeDateValue($value);
        }

        if ($column === 'no') {
            return is_numeric($value) ? (int) $value : null;
        }

        return $value;
    }

    private function normalizeDateValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse(str_replace('/', '-', $value))->format('Y-m-d');
        } catch (\Throwable $e) {
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

    private function buildInsertRow(array $headers, array $row, array $selectedColumns): ?array
    {
        $insertRow = [
            'uniqueid_namareport' => uniqid('', true) . self::UNIQUE_SUFFIX,
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

        return count($insertRow) > 3 ? $insertRow : null;
    }

    private function insertBatch(array $rows, int &$totalSuccess, int &$totalFailed, string &$lastErrorMsg): void
    {
        foreach (array_chunk($rows, 100) as $batch) {
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
}
