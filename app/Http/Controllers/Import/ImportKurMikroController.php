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

    public function preview(Request $request): JsonResponse
    {
        $sourcePath = $this->resolveSourcePath($request);
        if ($sourcePath === null || !file_exists($sourcePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File import KUR Mikro tidak ditemukan. Silakan upload ulang.',
            ], 422);
        }

        try {
            return response()->json([
                'status' => 'success',
                'report' => self::REPORT_LABEL,
                'summary' => $this->inspectWorkbook($sourcePath),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membaca preview KUR Mikro: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function initImport(Request $request): JsonResponse
    {
        return $this->processImport($request);
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

                $send('progress', ['percent' => 10, 'message' => 'Memvalidasi workbook KUR Mikro...']);
                $summary = $this->inspectWorkbook($sourcePath);
                $send('progress', ['percent' => 25, 'message' => 'Workbook valid. Memulai proses import baris data...']);

                $result = $this->importWorkbook($sourcePath, function (string $event, array $payload) use ($send): void {
                    $send($event, $payload);
                });

                $send('complete', [
                    'status' => 'success',
                    'message' => 'Import KUR Mikro selesai.',
                    'summary' => $summary,
                    'result' => $result,
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
            $result = $this->importWorkbook($sourcePath);

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

    private function importWorkbook(string $path, ?callable $send = null): array
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
        $startTime = microtime(true);

        DB::transaction(function () use (
            $worksheet,
            $highestRow,
            &$rowsToInsert,
            &$insertedRows,
            &$skippedRows,
            &$latestPeriod,
            &$processedRows,
            $send,
            $startTime
        ): void {
            for ($rowNumber = self::DATA_START_ROW; $rowNumber <= $highestRow; $rowNumber++) {
                $row = $this->buildRowPayload($worksheet, $rowNumber);
                $processedRows++;

                if ($row === null) {
                    $skippedRows++;
                    continue;
                }

                if (($row['tanggal_bl'] ?? null) !== null) {
                    $latestPeriod = max((string) ($latestPeriod ?? $row['tanggal_bl']), (string) $row['tanggal_bl']);
                }

                $rowsToInsert[] = $row;

                if (count($rowsToInsert) >= self::BATCH_SIZE) {
                    DB::table(self::TABLE_NAME)->insert($rowsToInsert);
                    $insertedRows += count($rowsToInsert);
                    $rowsToInsert = [];
                }

                if ($send !== null && ($processedRows % 25 === 0 || $rowNumber === $highestRow)) {
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) ($processedRows / $elapsed);
                    $percent = $highestRow > 0
                        ? min(95, 20 + (int) (($rowNumber / $highestRow) * 75))
                        : 95;

                    $send('progress', [
                        'percent' => $percent,
                        'message' => "Memproses baris KUR Mikro... ({$speed} baris/detik)",
                        'rows_done' => $processedRows,
                        'inserted_rows' => $insertedRows + count($rowsToInsert),
                        'speed' => $speed,
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
            'inserted_rows' => $insertedRows,
            'skipped_rows' => $skippedRows,
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

        for ($rowNumber = self::DATA_START_ROW; $rowNumber <= min($highestRow, self::DATA_START_ROW + 4); $rowNumber++) {
            $row = $this->buildRowPayload($worksheet, $rowNumber);
            if ($row !== null) {
                $sampleRows[] = $row;
            }
        }

        return [
            'sheet_name' => $context['sheet_name'],
            'highest_row' => $highestRow,
            'headers' => array_values(array_map(
                static fn (array $definition): string => $definition[0],
                self::COLUMN_MAP
            )),
            'sample_rows' => $sampleRows,
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
            return number_format((float) $value, $scale, '.', '');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^0-9,\.\-\+eE]/', '', $value);
        if ($value === '' || $value === '-' || $value === '+') {
            return null;
        }

        if (preg_match('/^[+-]?\d+(?:[.,]\d+)?[eE][+-]?\d+$/', $value) === 1) {
            $value = str_replace(',', '.', $value);
            return number_format((float) $value, $scale, '.', '');
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

        return number_format((float) $value, $scale, '.', '');
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

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:\\\\|\\\\\\\\|\/)/', $path) === 1;
    }
}
