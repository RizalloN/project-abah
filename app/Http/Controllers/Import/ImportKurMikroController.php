<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Support\ReportDataSyncService;
use App\Support\StrictDateParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportKurMikroController extends Controller
{
    private const TABLE_NAME = 'performance_kurkecil_mikro';
    private const REPORT_LABEL = 'Kinerja per RM Kur Mikro';
    private const STORAGE_DIR = 'performance_kurmikro_imports';
    private const BATCH_SIZE = 1000;
    private const PREVIEW_ROW_LIMIT = 100;
    private const MAX_UNIQUE_FILTER_VALUES = 5000;
    private const HEADER_ROW = 3;
    private const SUBHEADER_ROW = 4;
    private const DATA_START_ROW = 7;

    private const HEADER_CHECKS = [
        'A3' => 'NO',
        'B3' => 'KANCA',
        'C3' => 'PN',
        'D3' => 'NAMA',
        'E3' => 'BC UKER',
        'F3' => 'UKER',
        'G3' => 'TANGGAL BL',
        'H3' => 'KET',
        'I3' => '<250 Juta',
        'I4' => 'Deb',
        'J4' => '%',
        'K4' => 'Rp.Juta',
        'L3' => '>250 Juta',
        'L4' => 'Deb',
        'M4' => '%',
        'N4' => 'Rp.Juta',
        'O3' => 'TOTAL',
        'O4' => 'Deb',
        'P4' => 'Rp.Juta',
    ];

    private const COLUMN_MAP = [
        'B' => ['kanca', 'text'],
        'C' => ['pn', 'text'],
        'D' => ['nama', 'text'],
        'E' => ['bc_uker', 'text'],
        'F' => ['uker', 'text'],
        'G' => ['tanggal_bl', 'date'],
        'H' => ['ket', 'text'],
        'I' => ['lt_250_juta_deb', 'int'],
        'J' => ['lt_250_juta_pct', 'decimal16'],
        'K' => ['lt_250_juta_rp_juta', 'decimal2'],
        'L' => ['gt_250_juta_deb', 'int'],
        'M' => ['gt_250_juta_pct', 'decimal16'],
        'N' => ['gt_250_juta_rp_juta', 'decimal2'],
        'O' => ['total_deb', 'int'],
        'P' => ['total_rp_juta', 'decimal2'],
    ];

    public function upload(Request $request)
    {
        $request->validate([
            'id_report' => 'required',
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            $file = $request->file('file');
            $path = $file->store(self::STORAGE_DIR);

            session([
                'active_id_report' => $request->input('id_report'),
                'import_type' => 'kurmikro',
                'kurmikro_file' => $path,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'path' => $path,
                    'redirect' => route('import.kurmikro.prepare-preview'),
                ]);
            }

            return redirect()->route('import.kurmikro.prepare-preview');
        } catch (\Throwable $e) {
            Log::error('KUR Mikro upload error: ' . $e->getMessage(), [
                'file_name' => $request->file('file')?->getClientOriginalName(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Upload KUR Mikro gagal: ' . $e->getMessage(),
                ], 422);
            }

            return back()->with('error', 'Upload KUR Mikro gagal: ' . $e->getMessage());
        }
    }

    public function preparePreviewStream(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $sourcePath = $this->resolveSourcePath($request);
        request()->session()->save();

        return response()->stream(function () use ($sourcePath) {
            $send = function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                if ($sourcePath === null) {
                    $send('error_msg', ['message' => 'Sesi upload KUR Mikro tidak ditemukan. Silakan upload ulang.']);
                    return;
                }

                if (!file_exists($sourcePath)) {
                    $send('error_msg', ['message' => 'File import KUR Mikro tidak ditemukan di server.']);
                    return;
                }

                $send('progress', ['percent' => 15, 'message' => 'Membaca struktur workbook KUR Mikro...']);
                $summary = $this->inspectWorkbook($sourcePath);
                $send('progress', ['percent' => 85, 'message' => 'Workbook tervalidasi. Menyiapkan halaman preview...']);
                $send('ready', [
                    'redirect' => route('import.kurmikro.preview', [
                        'file_path' => $sourcePath,
                    ]),
                    'summary' => $summary,
                ]);
            } catch (\Throwable $e) {
                Log::error('KUR Mikro prepare preview error: ' . $e->getMessage(), [
                    'file' => $sourcePath,
                ]);
                $send('error_msg', ['message' => 'Gagal menyiapkan preview KUR Mikro: ' . $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function preview(Request $request)
    {
        $sourcePath = $this->resolveSourcePath($request);
        if ($sourcePath === null || !file_exists($sourcePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File import KUR Mikro tidak ditemukan. Silakan upload ulang.',
            ], 422);
        }

        try {
            $summary = $this->inspectWorkbook($sourcePath);
            $headers = (array) ($summary['headers'] ?? []);
            $previewRows = (array) ($summary['sample_rows'] ?? []);
            $formattedUniqueValues = (array) ($summary['formatted_unique_values'] ?? []);
            $previewStateKey = 'kurmikro_preview_' . md5($sourcePath . '|' . (string) microtime(true));

            session([
                'kurmikro_preview_state_key' => $previewStateKey,
                'kurmikro_preview_summary' => $summary,
            ]);

            return view('import.preview_excel', [
                'pageTitle' => 'Preview Kinerja RM Kur Mikro',
                'previewBannerTitle' => 'Preview Kinerja RM Kur Mikro',
                'headers' => $headers,
                'preview' => $previewRows,
                'formattedUniqueValues' => $formattedUniqueValues,
                'path' => $sourcePath,
                'currentDelimiter' => 'excel',
                'initRoute' => route('import.kurmikro.init'),
                'streamRoute' => route('import.kurmikro.stream'),
                'processRoute' => route('import.kurmikro.process'),
                'previewStateKey' => $previewStateKey,
                'filterOptionsRoute' => route('import.preview.filter-options'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('import.index')->with('error', 'Gagal membaca preview KUR Mikro: ' . $e->getMessage());
        }
    }

    public function initImport(Request $request): JsonResponse
    {
        $sourcePath = $this->resolveSourcePath($request);
        if ($sourcePath === null || !file_exists($sourcePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File KUR Mikro tidak ditemukan. Silakan upload ulang.',
            ], 422);
        }

        try {
            $activeFilters = $this->parseActiveFilters($request);
            $totalRows = $this->countMatchingRows($sourcePath, $activeFilters);
            $jobId = 'kurmikro_' . md5($sourcePath . '|' . json_encode($activeFilters) . '|' . microtime(true));

            session([
                'kurmikro_active_filters' => $activeFilters,
                'kurmikro_active_filters_json' => json_encode($activeFilters),
                'kurmikro_import_job_id' => $jobId,
                'kurmikro_import_total_rows' => $totalRows,
            ]);

            return response()->json([
                'status' => 'success',
                'job_id' => $jobId,
                'total_rows' => $totalRows,
            ]);
        } catch (\Throwable $e) {
            Log::error('KUR Mikro init import error: ' . $e->getMessage(), [
                'file' => $sourcePath,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyiapkan import KUR Mikro: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function processImportStream(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $sourcePath = $this->resolveSourcePath($request);
        request()->session()->save();

        return response()->stream(function () use ($sourcePath) {
            $send = function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                if ($sourcePath === null) {
                    $send('error_msg', ['message' => 'Sesi file KUR Mikro tidak ditemukan.']);
                    return;
                }

                if (!file_exists($sourcePath)) {
                    $send('error_msg', ['message' => 'File KUR Mikro tidak ditemukan di server.']);
                    return;
                }

                $activeFilters = (array) session('kurmikro_active_filters', []);
                $totalRows = (int) session('kurmikro_import_total_rows', 0);

                $send('progress', ['percent' => 10, 'message' => 'Memvalidasi workbook KUR Mikro...']);
                $summary = $this->inspectWorkbook($sourcePath);
                $send('progress', ['percent' => 25, 'message' => 'Workbook valid. Memulai proses import baris data...']);

                $result = $this->importWorkbook($sourcePath, $activeFilters, function (string $event, array $payload) use ($send): void {
                    $send($event, $payload);
                }, $totalRows > 0 ? $totalRows : null);

                $send('complete', [
                    'status' => 'success',
                    'message' => 'Import KUR Mikro selesai.',
                    'summary' => $summary,
                    'result' => $result,
                    'total_rows' => (int) ($result['total_rows'] ?? $result['source_rows'] ?? 0),
                    'total_success' => (int) ($result['total_success'] ?? $result['inserted_rows'] ?? 0),
                    'total_failed' => (int) ($result['total_failed'] ?? $result['skipped_rows'] ?? 0),
                    'skipped_count' => (int) ($result['skipped_count'] ?? $result['skipped_rows'] ?? 0),
                    'skipped_rows' => (array) ($result['skipped_rows_list'] ?? []),
                ]);
            } catch (\Throwable $e) {
                Log::error('KUR Mikro import stream error: ' . $e->getMessage(), [
                    'file' => $sourcePath,
                ]);
                $send('error_msg', ['message' => 'Gagal import KUR Mikro: ' . $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function processImport(Request $request): JsonResponse
    {
        $sourcePath = $this->resolveSourcePath($request);
        if ($sourcePath === null || !file_exists($sourcePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File KUR Mikro tidak ditemukan. Silakan upload ulang.',
            ], 422);
        }

        try {
            $activeFilters = $this->parseActiveFilters($request);
            $result = $this->importWorkbook($sourcePath, $activeFilters);

            return response()->json([
                'status' => 'success',
                'message' => 'Import KUR Mikro selesai.',
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('KUR Mikro import error: ' . $e->getMessage(), [
                'file' => $sourcePath,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal import KUR Mikro: ' . $e->getMessage(),
            ], 422);
        }
    }

    private function resolveSourcePath(Request $request): ?string
    {
        $candidate = trim((string) ($request->input('file_path') ?? session('kurmikro_file', '')));
        if ($candidate === '') {
            return null;
        }

        if ($this->isAbsolutePath($candidate) && file_exists($candidate)) {
            return $candidate;
        }

        if (file_exists($candidate)) {
            return $candidate;
        }

        $resolved = Storage::path($candidate);
        return file_exists($resolved) ? $resolved : null;
    }

    private function importWorkbook(string $path, array $activeFilters = [], ?callable $send = null, ?int $totalRowsHint = null): array
    {
        $context = $this->openWorkbookWorksheet($path);
        $worksheet = $context['worksheet'];
        $highestRow = $context['highest_row'];
        $this->validateWorksheetStructure($worksheet);

        $rowsToInsert = [];
        $insertedRows = 0;
        $skippedRows = 0;
        $latestPeriod = null;
        $processedRows = 0;
        $matchedRows = 0;
        $startTime = microtime(true);

        DB::transaction(function () use (
            $worksheet,
            $highestRow,
            $activeFilters,
            &$rowsToInsert,
            &$insertedRows,
            &$skippedRows,
            &$latestPeriod,
            &$processedRows,
            &$matchedRows,
            $send,
            $startTime,
            $totalRowsHint
        ): void {
            for ($rowNumber = self::DATA_START_ROW; $rowNumber <= $highestRow; $rowNumber++) {
                $row = $this->buildRowPayload($worksheet, $rowNumber);
                $processedRows++;

                if ($row === null) {
                    $skippedRows++;
                    continue;
                }

                if (!$this->rowMatchesActiveFilters($row, $activeFilters)) {
                    continue;
                }

                $matchedRows++;

                if (($row['tanggal_bl'] ?? null) !== null) {
                    $latestPeriod = max((string) ($latestPeriod ?? $row['tanggal_bl']), (string) $row['tanggal_bl']);
                }

                $rowsToInsert[] = $row;

                if (count($rowsToInsert) >= self::BATCH_SIZE) {
                    DB::table(self::TABLE_NAME)->insert($rowsToInsert);
                    $insertedRows += count($rowsToInsert);
                    $rowsToInsert = [];
                }

                if ($send !== null && ($matchedRows % 25 === 0 || $rowNumber === $highestRow)) {
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) ($matchedRows / $elapsed);
                    $totalBase = max(1, (int) ($totalRowsHint ?? $matchedRows));
                    $percent = min(95, 20 + (int) (($matchedRows / $totalBase) * 75));

                    $send('progress', [
                        'percent' => $percent,
                        'message' => "Memproses baris KUR Mikro sesuai filter... ({$speed} baris/detik)",
                        'rows_done' => $matchedRows,
                        'inserted_rows' => $insertedRows + count($rowsToInsert),
                        'speed' => $speed,
                        'total' => $totalRowsHint ?? $matchedRows,
                    ]);
                }
            }

            if ($rowsToInsert !== []) {
                DB::table(self::TABLE_NAME)->insert($rowsToInsert);
                $insertedRows += count($rowsToInsert);
            }
        });

        $this->refreshPostImportStatistics($latestPeriod);

        return [
            'report' => self::REPORT_LABEL,
            'table' => self::TABLE_NAME,
            'sheet' => $context['sheet_name'],
            'source_rows' => $processedRows,
            'total_rows' => $matchedRows,
            'inserted_rows' => $insertedRows,
            'skipped_rows' => $skippedRows,
            'total_success' => $insertedRows,
            'total_failed' => $skippedRows,
            'skipped_count' => $skippedRows,
            'skipped_rows_list' => [],
            'latest_period' => $latestPeriod,
        ];
    }

    private function refreshPostImportStatistics(?string $periodHint): void
    {
        if ($periodHint === null || $periodHint === '') {
            return;
        }

        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        try {
            app(ReportDataSyncService::class)->syncImportedTable(
                self::TABLE_NAME,
                $periodHint,
                null,
                static::class
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal sinkronisasi statistik KUR Mikro setelah import: ' . $e->getMessage(), [
                'table' => self::TABLE_NAME,
                'period_hint' => $periodHint,
            ]);
        }
    }

    private function inspectWorkbook(string $path): array
    {
        $context = $this->openWorkbookWorksheet($path);
        $worksheet = $context['worksheet'];
        $this->validateWorksheetStructure($worksheet);

        $highestRow = $context['highest_row'];
        $sampleRows = [];
        $formattedUniqueValues = [];

        for ($rowNumber = self::DATA_START_ROW; $rowNumber <= $highestRow; $rowNumber++) {
            $row = $this->buildRowPayload($worksheet, $rowNumber);
            if ($row !== null) {
                if (count($sampleRows) < self::PREVIEW_ROW_LIMIT) {
                    $sampleRows[] = $this->mapPayloadToPreviewRow($row);
                }

                foreach (array_values(self::COLUMN_MAP) as $index => [$columnName]) {
                    $formattedUniqueValues[$index] ??= [];
                    $filterValue = $this->formatPreviewFilterValue($row[$columnName] ?? null);
                    if (count($formattedUniqueValues[$index]) < self::MAX_UNIQUE_FILTER_VALUES || isset($formattedUniqueValues[$index][$filterValue])) {
                        $formattedUniqueValues[$index][$filterValue] = true;
                    }
                }
            }
        }

        foreach ($formattedUniqueValues as $index => $valuesMap) {
            $formattedUniqueValues[$index] = array_keys($valuesMap);
        }

        return [
            'sheet_name' => $context['sheet_name'],
            'highest_row' => $highestRow,
            'headers' => array_values(array_map(
                static fn (array $definition): string => $definition[0],
                self::COLUMN_MAP
            )),
            'sample_rows' => $sampleRows,
            'formatted_unique_values' => $formattedUniqueValues,
        ];
    }

    private function openWorkbookWorksheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getSheet(0);

        return [
            'spreadsheet' => $spreadsheet,
            'worksheet' => $worksheet,
            'sheet_name' => $worksheet->getTitle(),
            'highest_row' => max((int) $worksheet->getHighestDataRow(), self::DATA_START_ROW - 1),
        ];
    }

    private function validateWorksheetStructure(Worksheet $worksheet): void
    {
        foreach (self::HEADER_CHECKS as $coordinate => $expected) {
            $actual = $this->normalizeHeaderText($worksheet->getCell($coordinate)->getValue());
            if ($actual !== $this->normalizeHeaderText($expected)) {
                throw new \RuntimeException(sprintf(
                    'Struktur workbook tidak sesuai. Sel %s seharusnya "%s" tetapi ditemukan "%s".',
                    $coordinate,
                    $expected,
                    $actual
                ));
            }
        }
    }

    private function buildRowPayload(Worksheet $worksheet, int $rowNumber): ?array
    {
        $rowLabel = $this->normalizeTextValue($worksheet->getCell('A' . $rowNumber)->getValue());
        if ($rowLabel === null) {
            return null;
        }

        if (strtoupper(trim($rowLabel)) === 'TOTAL') {
            return null;
        }

        $payload = [
            'uniqueid_namareport' => 'uuid_pkm_' . (string) Str::uuid(),
        ];

        foreach (self::COLUMN_MAP as $columnLetter => [$columnName, $type]) {
            $value = $worksheet->getCell($columnLetter . $rowNumber)->getValue();
            $payload[$columnName] = match ($type) {
                'text' => $this->normalizeTextValue($value),
                'date' => $this->normalizeDateValue($value),
                'int' => $this->normalizeIntegerValue($value),
                'decimal16' => $this->normalizeDecimalValue($value, 16),
                'decimal2' => $this->normalizeDecimalValue($value, 2),
                default => $this->normalizeTextValue($value),
            };
        }

        if (!$this->hasMeaningfulImportData($payload, ['uniqueid_namareport'])) {
            return null;
        }

        return $payload;
    }

    private function hasMeaningfulImportData(array $row, array $ignoredKeys = []): bool
    {
        $ignored = array_fill_keys(array_map('strtolower', $ignoredKeys), true);

        foreach ($row as $key => $value) {
            if (isset($ignored[strtolower((string) $key)])) {
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

    private function normalizeTextValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_int($value) || is_float($value)) {
            return (string) (int) round((float) $value);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^[+-]?\d+(?:[.,]\d+)?[eE][+-]?\d+$/', $text) === 1) {
            $expanded = (string) ((float) str_replace(',', '.', $text));
            return strpos($expanded, '.') !== false
                ? rtrim(rtrim($expanded, '0'), '.')
                : $expanded;
        }

        return $text;
    }

    private function normalizeIntegerValue(mixed $value): ?int
    {
        $decimal = $this->normalizeDecimalValue($value, 0);
        return $decimal === null ? null : (int) $decimal;
    }

    private function normalizeDecimalValue(mixed $value, int $scale = 2): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $this->normalizeNumericString(sprintf('%.17g', (float) $value), $scale);
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return $this->normalizeNumericString($value, $scale);
    }

    private function normalizeNumericString(string $value, int $scale = 2): ?string
    {
        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^0-9,\.\-\+eE]/', '', $value);
        if ($value === '' || $value === '-' || $value === '+') {
            return null;
        }

        if (preg_match('/^[+-]?\d+(?:[.,]\d+)?[eE][+-]?\d+$/', $value) === 1) {
            $value = str_replace(',', '.', $value);
            return $this->trimNumericString(sprintf('%.17g', (float) $value), $scale);
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

        return $this->trimNumericString(sprintf('%.17g', (float) $value), $scale);
    }

    private function trimNumericString(string $value, int $scale = 2): string
    {
        if (!str_contains($value, '.')) {
            return $scale === 0 ? $value : $value . '.' . str_repeat('0', $scale);
        }

        [$integerPart, $decimalPart] = explode('.', $value, 2);
        $decimalPart = rtrim($decimalPart, '0');

        if ($scale === 0) {
            return $integerPart;
        }

        if ($decimalPart === '') {
            return $integerPart . '.' . str_repeat('0', $scale);
        }

        return $integerPart . '.' . $decimalPart;
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $normalized = StrictDateParser::normalize((string) $value, ['d/m/Y H:i', 'd-m-Y H:i', 'd/m/Y H:i:s', 'd-m-Y H:i:s']);
        return $normalized;
    }

    private function normalizeHeaderText(mixed $value): string
    {
        $text = trim((string) $value);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return strtoupper($text);
    }

    private function mapPayloadToPreviewRow(array $payload): array
    {
        $previewRow = [];

        foreach (array_values(self::COLUMN_MAP) as [$columnName]) {
            $previewRow[] = $payload[$columnName] ?? null;
        }

        return $previewRow;
    }

    private function formatPreviewFilterValue(mixed $value): string
    {
        if ($value === null) {
            return '(Blank)';
        }

        $text = trim((string) $value);
        return $text === '' ? '(Blank)' : $text;
    }

    private function parseActiveFilters(Request $request): array
    {
        $raw = trim((string) ($request->input('active_filters_json') ?? session('kurmikro_active_filters_json', '')));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $index => $values) {
            if (!is_array($values) || $values === []) {
                continue;
            }

            $normalized[(string) $index] = array_values(array_filter(array_map(
                static fn ($value) => trim((string) $value),
                $values
            ), static fn (string $value): bool => $value !== ''));
        }

        return $normalized;
    }

    private function rowMatchesActiveFilters(array $row, array $activeFilters): bool
    {
        if ($activeFilters === []) {
            return true;
        }

        foreach ($activeFilters as $columnIndex => $allowedValues) {
            if (!is_array($allowedValues) || $allowedValues === []) {
                continue;
            }

            $columnMap = array_values(self::COLUMN_MAP);
            if (!isset($columnMap[(int) $columnIndex][0])) {
                continue;
            }

            $columnName = $columnMap[(int) $columnIndex][0];
            $value = $this->formatPreviewFilterValue($row[$columnName] ?? null);
            if (!in_array($value, $allowedValues, true)) {
                return false;
            }
        }

        return true;
    }

    private function countMatchingRows(string $path, array $activeFilters): int
    {
        $context = $this->openWorkbookWorksheet($path);
        $worksheet = $context['worksheet'];
        $this->validateWorksheetStructure($worksheet);

        $matches = 0;
        $highestRow = $context['highest_row'];

        for ($rowNumber = self::DATA_START_ROW; $rowNumber <= $highestRow; $rowNumber++) {
            $row = $this->buildRowPayload($worksheet, $rowNumber);
            if ($row === null) {
                continue;
            }

            if ($this->rowMatchesActiveFilters($row, $activeFilters)) {
                $matches++;
            }
        }

        return $matches;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:\\\\|\\\\\\\\|\/)/', $path) === 1;
    }
}
