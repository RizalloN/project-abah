<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Support\ReportDataSyncService;
use App\Support\StrictDateParser;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportPerformanceMantriController extends Controller
{
    private const TABLE_NAME = 'performance_mantri';
    private const REPORT_LABEL = 'Kinerja Mantri';
    private const STORAGE_DIR = 'performance_mantri_imports';
    private const BATCH_SIZE = 1000;
    private const PREVIEW_ROW_LIMIT = 100;
    private const DATA_START_ROW = 6;

    private const COLUMN_MAP = [
        'A' => ['no', 'int'],
        'B' => ['pn', 'text'],
        'C' => ['nama', 'text'],
        'D' => ['bc', 'text'],
        'E' => ['unit', 'text'],
        'F' => ['cabang', 'text'],
        'G' => ['ket', 'text'],
        'H' => ['tmt_jabatan', 'date'],
        'I' => ['ket_kehadiran_mantri', 'text'],
        'J' => ['tanggal_mulai_bl', 'date'],
        'K' => ['disbursement_deb', 'int'],
        'L' => ['disbursement_rp_juta', 'decimal2'],
        'M' => ['ket_realisasi', 'text'],
        'N' => ['kategori_realisasi', 'text'],
        'O' => ['tiket_size', 'decimal16'],
        'P' => ['ratas_hk', 'decimal16'],
        'Q' => ['keterangan', 'text'],
    ];

    private const HEADER_ALIASES = [
        'NO' => ['NO'],
        'PN' => ['PN'],
        'NAMA' => ['NAMA MANTRI', 'NAMA'],
        'BC' => ['BC'],
        'UNIT' => ['UNIT'],
        'CABANG' => ['CABANG'],
        'KET' => ['KET'],
        'TMT JABATAN' => ['TMT JABATAN'],
        'KET KEHADIRAN MANTRI' => ['KET KEHADIRAN MANTRI'],
        'TANGGAL MULAI BL' => ['TANGGAL MULAI BL'],
        'DISBRUSMENT' => ['DISBRUSMENT SD'],
        'DEB' => ['DEB'],
        'RP. JUTA' => ['RP. JUTA', 'RP.JUTA'],
        'KATEGORI REALISASI' => ['KATEGORI REALISASI'],
        'TIKET SIZE' => ['TIKET SIZE'],
        'RATAS/HK' => ['RATAS/HK'],
        'KETERANGAN' => ['KETERANGAN'],
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
                'import_type' => 'performance_mantri',
                'performance_mantri_file' => $path,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'path' => $path,
                    'redirect' => route('import.mantri.prepare-preview'),
                ]);
            }

            return redirect()->route('import.mantri.prepare-preview');
        } catch (\Throwable $e) {
            Log::error('Performance Mantri upload error: ' . $e->getMessage(), [
                'file_name' => $request->file('file')?->getClientOriginalName(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Upload Kinerja Mantri gagal: ' . $e->getMessage(),
                ], 422);
            }

            return back()->with('error', 'Upload Kinerja Mantri gagal: ' . $e->getMessage());
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
                    $send('error_msg', ['message' => 'Sesi upload Kinerja Mantri tidak ditemukan. Silakan upload ulang.']);
                    return;
                }

                if (!file_exists($sourcePath)) {
                    $send('error_msg', ['message' => 'File Kinerja Mantri tidak ditemukan di server.']);
                    return;
                }

                $send('progress', ['percent' => 20, 'message' => 'Membaca struktur workbook Kinerja Mantri...']);
                $summary = $this->inspectWorkbook($sourcePath);
                session([
                    'mantri_preview_summary' => array_merge($summary, [
                        'source_path' => $sourcePath,
                    ]),
                ]);
                $send('progress', ['percent' => 85, 'message' => 'Workbook tervalidasi. Menyiapkan halaman preview...']);
                $send('ready', [
                    'redirect' => route('import.mantri.preview', [
                        'file_path' => $sourcePath,
                    ]),
                    'summary' => $summary,
                ]);
            } catch (\Throwable $e) {
                Log::error('Performance Mantri prepare preview error: ' . $e->getMessage(), [
                    'file' => $sourcePath,
                ]);
                $send('error_msg', ['message' => 'Gagal menyiapkan preview Kinerja Mantri: ' . $e->getMessage()]);
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
                'message' => 'File Kinerja Mantri tidak ditemukan. Silakan upload ulang.',
            ], 422);
        }

        try {
            $summary = (array) session('mantri_preview_summary', []);
            if ($summary === [] || (string) ($summary['source_path'] ?? '') !== $sourcePath) {
                $summary = $this->inspectWorkbook($sourcePath);
                $summary['source_path'] = $sourcePath;
            }
            $headers = (array) ($summary['headers'] ?? []);
            $previewRows = (array) ($summary['sample_rows'] ?? []);
            $previewStateKey = 'mantri_preview_' . md5($sourcePath . '|' . (string) microtime(true));

            session([
                'mantri_preview_state_key' => $previewStateKey,
                'mantri_preview_summary' => array_merge($summary, [
                    'source_path' => $sourcePath,
                ]),
            ]);

            return view('import.preview_excel', [
                'pageTitle' => 'Preview Kinerja Mantri',
                'previewBannerTitle' => 'Preview Kinerja Mantri',
                'headers' => $headers,
                'preview' => $previewRows,
                'formattedUniqueValues' => [],
                'path' => $sourcePath,
                'currentDelimiter' => 'excel',
                'initRoute' => route('import.mantri.init'),
                'streamRoute' => route('import.mantri.stream'),
                'processRoute' => route('import.mantri.process'),
                'previewStateKey' => $previewStateKey,
                'filterOptionsRoute' => route('import.preview.filter-options'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('import.index')->with('error', 'Gagal membaca preview Kinerja Mantri: ' . $e->getMessage());
        }
    }

    public function initImport(Request $request): JsonResponse
    {
        $sourcePath = $this->resolveSourcePath($request);
        if ($sourcePath === null || !file_exists($sourcePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File Kinerja Mantri tidak ditemukan. Silakan upload ulang.',
            ], 422);
        }

        try {
            $summary = (array) session('mantri_preview_summary', []);
            $totalRows = max(0, (int) ($summary['highest_row'] ?? 0) - self::DATA_START_ROW + 1);
            if ($totalRows <= 0) {
                $context = $this->openWorkbookWorksheet($sourcePath);
                $totalRows = max(0, (int) $context['highest_row'] - self::DATA_START_ROW + 1);
            }
            $jobId = 'mantri_' . md5($sourcePath . '|' . microtime(true));

            session([
                'mantri_import_job_id' => $jobId,
                'mantri_import_total_rows' => $totalRows,
            ]);

            return response()->json([
                'status' => 'success',
                'job_id' => $jobId,
                'total_rows' => $totalRows,
            ]);
        } catch (\Throwable $e) {
            Log::error('Performance Mantri init import error: ' . $e->getMessage(), [
                'file' => $sourcePath,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyiapkan import Kinerja Mantri: ' . $e->getMessage(),
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
                    $send('error_msg', ['message' => 'Sesi file Kinerja Mantri tidak ditemukan.']);
                    return;
                }

                if (!file_exists($sourcePath)) {
                    $send('error_msg', ['message' => 'File Kinerja Mantri tidak ditemukan di server.']);
                    return;
                }

                $summary = (array) session('mantri_preview_summary', []);
                if ($summary === [] || (string) ($summary['source_path'] ?? '') !== $sourcePath) {
                    $send('progress', ['percent' => 10, 'message' => 'Memvalidasi workbook Kinerja Mantri...']);
                    $summary = $this->inspectWorkbook($sourcePath);
                } else {
                    $send('progress', ['percent' => 10, 'message' => 'Menggunakan preview yang sudah divalidasi...']);
                }
                $send('progress', ['percent' => 25, 'message' => 'Workbook valid. Memulai import baris data...']);

                $result = $this->importWorkbook($sourcePath, function (string $event, array $payload) use ($send): void {
                    $send($event, $payload);
                });

                $send('complete', [
                    'status' => 'success',
                    'message' => 'Import Kinerja Mantri selesai.',
                    'summary' => $summary,
                    'result' => $result,
                    'total_rows' => (int) ($result['total_rows'] ?? 0),
                    'total_success' => (int) ($result['total_success'] ?? 0),
                    'total_failed' => (int) ($result['total_failed'] ?? 0),
                    'skipped_count' => (int) ($result['skipped_count'] ?? 0),
                    'skipped_rows' => (array) ($result['skipped_rows_list'] ?? []),
                ]);
            } catch (\Throwable $e) {
                Log::error('Performance Mantri import stream error: ' . $e->getMessage(), [
                    'file' => $sourcePath,
                ]);
                $send('error_msg', ['message' => 'Gagal import Kinerja Mantri: ' . $e->getMessage()]);
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
                'message' => 'File Kinerja Mantri tidak ditemukan. Silakan upload ulang.',
            ], 422);
        }

        try {
            $result = $this->importWorkbook($sourcePath);

            return response()->json([
                'status' => 'success',
                'message' => 'Import Kinerja Mantri selesai.',
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Performance Mantri import error: ' . $e->getMessage(), [
                'file' => $sourcePath,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal import Kinerja Mantri: ' . $e->getMessage(),
            ], 422);
        }
    }

    private function resolveSourcePath(Request $request): ?string
    {
        $candidate = trim((string) ($request->input('file_path') ?? session('performance_mantri_file', '')));
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
        $columns = $this->resolveWorksheetColumns($worksheet);

        $rowsToInsert = [];
        $insertedRows = 0;
        $skippedRows = 0;
        $processedRows = 0;
        $snapshotPeriod = $this->extractSnapshotPeriod($worksheet, $columns);
        $startTime = microtime(true);

        for ($rowNumber = self::DATA_START_ROW; $rowNumber <= $highestRow; $rowNumber++) {
            $row = $this->buildRowPayload($worksheet, $rowNumber, $columns);
            $processedRows++;

            if ($row === null) {
                $skippedRows++;
                continue;
            }

            if ($snapshotPeriod !== null) {
                $row['snapshot_period'] = $snapshotPeriod;
            }

            // Kolom `no` hanya ada di workbook, bukan di tabel performance_mantri.
            unset($row['no']);

            $rowsToInsert[] = $row;

            if ($send !== null && ($processedRows % 100 === 0 || $rowNumber === $highestRow)) {
                $elapsed = max(microtime(true) - $startTime, 0.001);
                $speed = (int) ($processedRows / $elapsed);
                $send('progress', [
                    'percent' => min(95, 20 + (int) (($processedRows / max(1, $highestRow - self::DATA_START_ROW + 1)) * 75)),
                    'message' => "Memproses baris Kinerja Mantri... ({$speed} baris/detik)",
                    'rows_done' => $processedRows,
                    'inserted_rows' => $insertedRows + count($rowsToInsert),
                    'speed' => $speed,
                    'total' => $highestRow - self::DATA_START_ROW + 1,
                ]);
            }
        }

        DB::transaction(function () use (&$insertedRows, &$rowsToInsert, $snapshotPeriod): void {
            if ($snapshotPeriod !== null) {
                DB::table(self::TABLE_NAME)->where('snapshot_period', $snapshotPeriod)->delete();
            }

            if ($rowsToInsert !== []) {
                foreach (array_chunk($rowsToInsert, self::BATCH_SIZE) as $batch) {
                    DB::table(self::TABLE_NAME)->insert($batch);
                    $insertedRows += count($batch);
                }
            }
        });

        $this->refreshPostImportStatistics($snapshotPeriod);

        return [
            'report' => self::REPORT_LABEL,
            'table' => self::TABLE_NAME,
            'sheet' => $context['sheet_name'],
            'source_rows' => $processedRows,
            'total_rows' => $processedRows - $skippedRows,
            'inserted_rows' => $insertedRows,
            'skipped_rows' => $skippedRows,
            'total_success' => $insertedRows,
            'total_failed' => $skippedRows,
            'skipped_count' => $skippedRows,
            'skipped_rows_list' => [],
            'latest_period' => $snapshotPeriod,
        ];
    }

    private function refreshPostImportStatistics(?string $periodHint): void
    {
        try {
            app(ReportDataSyncService::class)->syncImportedTable(
                self::TABLE_NAME,
                $periodHint,
                null,
                static::class
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal sinkronisasi statistik Kinerja Mantri setelah import: ' . $e->getMessage(), [
                'table' => self::TABLE_NAME,
                'period_hint' => $periodHint,
            ]);
        }
    }

    private function inspectWorkbook(string $path): array
    {
        $context = $this->openWorkbookWorksheet($path);
        $worksheet = $context['worksheet'];
        $columns = $this->resolveWorksheetColumns($worksheet);

        $highestRow = $context['highest_row'];
        $sampleRows = [];
        $snapshotPeriod = $this->extractSnapshotPeriod($worksheet, $columns);

        for ($rowNumber = self::DATA_START_ROW; $rowNumber <= $highestRow; $rowNumber++) {
            $row = $this->buildRowPayload($worksheet, $rowNumber, $columns);
            if ($row !== null && count($sampleRows) < self::PREVIEW_ROW_LIMIT) {
                $sampleRows[] = $this->mapPayloadToPreviewRow($row);

                if (count($sampleRows) >= self::PREVIEW_ROW_LIMIT) {
                    break;
                }
            }
        }

        return [
            'sheet_name' => $context['sheet_name'],
            'highest_row' => $highestRow,
            'snapshot_period' => $snapshotPeriod,
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

    private function resolveWorksheetColumns(Worksheet $worksheet): array
    {
        $headerMap = [];
        $candidateRows = [3, 4, 5, 2, 6];

        // Workbook lama dan variasi lain kadang memakai header RW di kolom pertama
        // atau memindahkan header utama ke baris lain. Kita scan beberapa baris agar
        // preview tidak gagal hanya karena layout bergeser sedikit.
        $headerMap['no'] = $this->findHeaderColumnAnywhere($worksheet, ['NO', 'RW'], $candidateRows);
        $headerMap['pn'] = $this->findHeaderColumnAnywhere($worksheet, ['PN'], $candidateRows);
        $headerMap['nama'] = $this->findHeaderColumnAnywhere($worksheet, ['NAMA MANTRI', 'NAMA'], $candidateRows);
        $headerMap['bc'] = $this->findHeaderColumnAnywhere($worksheet, ['BC'], $candidateRows);
        $headerMap['unit'] = $this->findHeaderColumnAnywhere($worksheet, ['UNIT'], $candidateRows);
        $headerMap['cabang'] = $this->findHeaderColumnAnywhere($worksheet, ['CABANG'], $candidateRows);
        $headerMap['ket'] = $this->findHeaderColumnAnywhere($worksheet, ['KET'], $candidateRows);
        $headerMap['tmt_jabatan'] = $this->findHeaderColumnAnywhere($worksheet, ['TMT JABATAN'], $candidateRows);
        $headerMap['ket_kehadiran_mantri'] = $this->findHeaderColumnAnywhere($worksheet, ['KET KEHADIRAN MANTRI'], $candidateRows);
        $headerMap['tanggal_mulai_bl'] = $this->findHeaderColumnAnywhere($worksheet, ['TANGGAL MULAI BL'], $candidateRows);
        $headerMap['disbursement_header'] = $this->findHeaderColumnAnywhere($worksheet, ['DISBRUSMENT SD', 'DISBRUSMENT'], $candidateRows);

        // Subheader disbursement biasanya ada di baris yang lebih rendah,
        // tapi kita tetap scan beberapa baris kalau workbook bergeser.
        $headerMap['disbursement_deb'] = $this->findHeaderColumnAnywhere($worksheet, ['DEB'], [5, 4, 6, 3]);
        $headerMap['disbursement_rp_juta'] = $this->findHeaderColumnAnywhere($worksheet, ['RP. JUTA', 'RP.JUTA'], [5, 4, 6, 3]);

        $headerMap['ket_realisasi'] = $this->findHeaderColumnAnywhere($worksheet, ['KET'], $candidateRows, ($headerMap['disbursement_rp_juta'] ?? 0) + 1);
        $headerMap['kategori_realisasi'] = $this->findHeaderColumnAnywhere($worksheet, ['KATEGORI REALISASI'], $candidateRows);
        $headerMap['tiket_size'] = $this->findHeaderColumnAnywhere($worksheet, ['TIKET SIZE'], $candidateRows);
        $headerMap['ratas_hk'] = $this->findHeaderColumnAnywhere($worksheet, ['RATAS/HK'], $candidateRows);
        $headerMap['keterangan'] = $this->findHeaderColumnAnywhere($worksheet, ['KETERANGAN'], $candidateRows);

        foreach ($headerMap as $key => $columnIndex) {
            if (!is_int($columnIndex) || $columnIndex <= 0) {
                throw new \RuntimeException('Struktur workbook tidak sesuai. Kolom ' . $key . ' tidak ditemukan.');
            }
        }

        return $headerMap;
    }

    private function buildRowPayload(Worksheet $worksheet, int $rowNumber, array $columns): ?array
    {
        $rowLabel = $this->normalizeTextValue($worksheet->getCell($this->columnLetter($columns['no']) . $rowNumber)->getValue());
        if ($rowLabel === null) {
            return null;
        }

        if (strtoupper(trim($rowLabel)) === 'TOTAL') {
            return null;
        }

        $payload = [
            'uniqueid_namareport' => 'uuid_pm_' . (string) Str::uuid(),
        ];

        $fieldColumns = [
            'no' => $columns['no'],
            'pn' => $columns['pn'],
            'nama' => $columns['nama'],
            'bc' => $columns['bc'],
            'unit' => $columns['unit'],
            'cabang' => $columns['cabang'],
            'ket' => $columns['ket'],
            'tmt_jabatan' => $columns['tmt_jabatan'],
            'ket_kehadiran_mantri' => $columns['ket_kehadiran_mantri'],
            'tanggal_mulai_bl' => $columns['tanggal_mulai_bl'],
            'disbursement_deb' => $columns['disbursement_deb'],
            'disbursement_rp_juta' => $columns['disbursement_rp_juta'],
            'ket_realisasi' => $columns['ket_realisasi'],
            'kategori_realisasi' => $columns['kategori_realisasi'],
            'tiket_size' => $columns['tiket_size'],
            'ratas_hk' => $columns['ratas_hk'],
            'keterangan' => $columns['keterangan'],
        ];

        foreach (self::COLUMN_MAP as $columnLetter => [$columnName, $type]) {
            $actualColumnIndex = $fieldColumns[$columnName] ?? null;
            if (!is_int($actualColumnIndex) || $actualColumnIndex <= 0) {
                continue;
            }

            $cell = $this->columnLetter($actualColumnIndex) . $rowNumber;
            $value = $worksheet->getCell($cell)->getValue();
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

    private function extractSnapshotPeriod(Worksheet $worksheet, array $columns = []): ?string
    {
        $headerColumn = (int) ($columns['disbursement_header'] ?? 0);
        if ($headerColumn <= 0) {
            return null;
        }

        $header = $this->normalizeTextValue($worksheet->getCell($this->columnLetter($headerColumn) . '3')->getValue());
        if ($header === null) {
            return null;
        }

        if (preg_match('/sd\s+(?<date>.+?)(?:\*|$)/i', $header, $matches) === 1) {
            $normalized = $this->normalizeDateText($matches['date']);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $this->normalizeDateText($header);
    }

    private function normalizeDateText(string $value): ?string
    {
        $value = trim(str_replace(['Disbrusment', 'sd'], '', $value));
        $value = trim($value, " *\t\n\r\0\x0B");
        if ($value === '') {
            return null;
        }

        $strict = StrictDateParser::normalize($value);
        if ($strict !== null) {
            return $strict;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
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
            return trim((string) $value);
        }

        $text = trim((string) $value);
        return $text === '' ? null : $text;
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

        if (is_int($value) || is_float($value)) {
            try {
                return SpreadsheetDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
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

    private function assertHeaderEquals(Worksheet $worksheet, string $coordinate, string $expected): void
    {
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

    private function assertHeaderContains(Worksheet $worksheet, string $coordinate, string $expectedPart): void
    {
        $actual = $this->normalizeHeaderText($worksheet->getCell($coordinate)->getValue());
        if (!str_contains($actual, $this->normalizeHeaderText($expectedPart))) {
            throw new \RuntimeException(sprintf(
                'Struktur workbook tidak sesuai. Sel %s seharusnya mengandung "%s" tetapi ditemukan "%s".',
                $coordinate,
                $expectedPart,
                $actual
            ));
        }
    }

    private function mapPayloadToPreviewRow(array $payload): array
    {
        $previewRow = [];

        foreach (array_values(self::COLUMN_MAP) as [$columnName]) {
            $previewRow[] = $payload[$columnName] ?? null;
        }

        return $previewRow;
    }

    private function readRowHeaders(Worksheet $worksheet, int $row, int $startColumn, int $endColumn): array
    {
        $headers = [];
        for ($columnIndex = $startColumn; $columnIndex <= $endColumn; $columnIndex++) {
            $headers[$columnIndex] = $this->normalizeHeaderText($worksheet->getCell($this->columnLetter($columnIndex) . $row)->getValue());
        }

        return $headers;
    }

    private function findHeaderColumnAnywhere(Worksheet $worksheet, array $candidates, array $rows, ?int $afterColumn = null): ?int
    {
        foreach ($rows as $row) {
            $headers = $this->readRowHeaders($worksheet, (int) $row, 1, 40);
            $columnIndex = $this->findHeaderColumn($headers, $candidates, $afterColumn);

            if (is_int($columnIndex) && $columnIndex > 0) {
                return $columnIndex;
            }
        }

        return null;
    }

    private function findHeaderColumn(array $headers, array $candidates, ?int $afterColumn = null): ?int
    {
        foreach ($headers as $columnIndex => $header) {
            if ($afterColumn !== null && $columnIndex < $afterColumn) {
                continue;
            }

            foreach ($candidates as $candidate) {
                $normalizedCandidate = $this->normalizeHeaderText($candidate);
                if ($header === $normalizedCandidate || str_contains($header, $normalizedCandidate)) {
                    return (int) $columnIndex;
                }
            }
        }

        return null;
    }

    private function columnLetter(int $index): string
    {
        $index = max(1, $index);
        $letters = '';

        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)) . $letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '\\\\') || str_starts_with($path, '/');
    }
}
