<?php

namespace App\Http\Controllers\Import;

use App\Support\StrictDateParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ReflectionMethod;

class Gi405RecDhImportExcelController extends ImportExcelController
{
    private const TABLE_NAME = 'gi405_rec_dh';
    private const MAX_ERROR_SAMPLES = 8;

    public function uploadExcel(Request $request, array $allowedExtensions = ['xlsx', 'xls'])
    {
        $response = parent::uploadExcel($request, $allowedExtensions);

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
            $payload['redirect'] = route('import.gi405.prepare-preview');
            $payload['preview_redirect'] = route('import.gi405.preview', ['ck' => $payload['cache_key'] ?? session('excel_preview_key')]);

            return response()->json($payload, $response->getStatusCode(), $response->headers->all());
        }

        if ($response instanceof RedirectResponse) {
            return redirect()->route('import.gi405.preview', ['ck' => session('excel_preview_key')]);
        }

        return $response;
    }

    public function preparePreviewStream(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $sessionPath = session('excel_path');
        $cacheKey = session('excel_preview_key');
        request()->session()->save();

        return response()->stream(function () use ($sessionPath, $cacheKey) {
            $send = function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                if (!$sessionPath) {
                    $send('error_msg', ['message' => 'Sesi upload tidak ditemukan. Silakan upload ulang.']);
                    return;
                }

                $path = Storage::path(urldecode($sessionPath));
                if (!file_exists($path)) {
                    $send('error_msg', ['message' => 'File tidak ditemukan di server. Silakan upload ulang.']);
                    return;
                }

                $useCacheKey = $cacheKey ?: ('excel_preview_' . md5(urldecode($sessionPath) . '|' . microtime(true)));
                $redirect = route('import.gi405.preview', ['ck' => $useCacheKey]);

                $send('progress', ['percent' => 20, 'message' => 'File ditemukan. Menyiapkan preview...', 'step' => 1]);
                $this->primeExcelPreviewCache(urldecode($sessionPath), $path, $useCacheKey, $send);
                $send('progress', ['percent' => 85, 'message' => 'Mengalihkan ke halaman preview...', 'step' => 2]);
                $send('ready', ['redirect' => $redirect]);
            } catch (\Throwable $e) {
                Log::error('GI405 PREPARE PREVIEW SSE ERROR: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $send('error_msg', ['message' => 'Gagal menyiapkan preview: ' . $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function previewExcel(Request $request)
    {
        $response = parent::previewExcel($request);

        if ($response instanceof View) {
            return $response
                ->with('initRoute', route('import.gi405.init'))
                ->with('streamRoute', route('import.gi405.stream'));
        }

        return $response;
    }

    public function initExcelImport(Request $request)
    {
        if ($this->resolveActiveTableName() === self::TABLE_NAME) {
            $validationError = $this->validateGi405DuplicateGuard();
            if ($validationError !== null) {
                return response()->json([
                    'status' => 'error',
                    'text' => $validationError,
                    'duplicate_detected' => true,
                    'redirect_url' => route('import.index'),
                ], 422);
            }
        }

        return parent::initExcelImport($request);
    }

    private function validateGi405DuplicateGuard(): ?string
    {
        $path = Storage::path((string) session('excel_path', ''));
        if ($path === '' || !file_exists($path)) {
            return null;
        }

        $pairs = $this->extractGi405BusinessKeys($path);
        if (($pairs['count'] ?? 0) === 0) {
            return null;
        }

        $fileDuplicates = (array) ($pairs['duplicates_in_file'] ?? []);
        if ($fileDuplicates !== []) {
            $samples = (array) ($pairs['duplicate_row_samples'] ?? []);
            $sample = implode(', ', array_slice($samples !== [] ? $samples : $fileDuplicates, 0, 5));

            return 'File GI405 - Rec. DH mengandung duplikat pasangan tanggal + kode unit pada file yang sama.<br><br>'
                . 'Contoh: <b>' . e($sample) . '</b><br>'
                . 'Perbaiki file terlebih dahulu sebelum import.';
        }

        $grouped = (array) ($pairs['grouped'] ?? []);
        $existing = [];

        foreach ($grouped as $date => $codes) {
            $rows = DB::table(self::TABLE_NAME)
                ->whereDate('tanggal', $date)
                ->whereIn('kode', array_values($codes))
                ->limit(5)
                ->get(['tanggal', 'kode']);

            foreach ($rows as $row) {
                $existing[] = trim((string) $row->tanggal) . ' / ' . trim((string) $row->kode);
            }
        }

        if ($existing === []) {
            return null;
        }

        return 'Data GI405 - Rec. DH untuk kombinasi periode/tanggal dan kode unit sudah ada di database.<br><br>'
            . 'Contoh yang bentrok: <b>' . e(implode(', ', array_slice($existing, 0, 5))) . '</b><br>'
            . 'Import dibatalkan agar tidak ada data periode yang sama dengan kode unit yang sama.';
    }

    protected function processStagedCsvStream(
        callable $send,
        string $csvPath,
        string $tableName,
        array $activeFilters,
        array $normalizedHeaders,
        int $jobId,
        ?int $estimatedTotalRows = null,
        ?string $delimiter = null,
        bool $forceDirectLoad = false,
        ?callable $beforeDirectLoad = null,
        array $importOptions = []
    ): bool {
        if ($tableName !== self::TABLE_NAME) {
            return parent::processStagedCsvStream(
                $send,
                $csvPath,
                $tableName,
                $activeFilters,
                $normalizedHeaders,
                $jobId,
                $estimatedTotalRows,
                $delimiter,
                $forceDirectLoad,
                $beforeDirectLoad,
                $importOptions
            );
        }

        if ($csvPath === '' || !file_exists($csvPath)) {
            return false;
        }

        $handle = @fopen($csvPath, 'r');
        if ($handle === false) {
            return false;
        }

        $delimiter = ($delimiter !== null && $delimiter !== '')
            ? $delimiter
            : $this->detectGi405CsvDelimiter($csvPath);

        $estimatedTotalRows = $estimatedTotalRows !== null
            ? max(0, $estimatedTotalRows)
            : max(0, $this->countGi405CsvDataRows($csvPath));

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!is_array($headers)) {
            fclose($handle);
            return false;
        }

        $timestamp = now()->toDateTimeString();
        $context = $this->invokeParentPrivate('buildImportContext', $tableName, $normalizedHeaders, $activeFilters, []);
        $pendapatanIndex = $this->findHeaderIndex($normalizedHeaders, 'Pendapatan Koreksi PPAP-dr Angsuran PH');
        $recoveryIndex = $this->findHeaderIndex($normalizedHeaders, 'Recovery Non Klaim');
        $kodeIndex = $this->findHeaderIndex($normalizedHeaders, 'KODE');
        $tanggalIndex = $this->findHeaderIndex($normalizedHeaders, 'Tanggal');

        $rowsToInsert = [];
        $sourcePendapatanTotal = 0.0;
        $sourceRecoveryTotal = 0.0;
        $parsedPendapatanTotal = 0.0;
        $parsedRecoveryTotal = 0.0;
        $sourceRowCount = 0;
        $sourceRowsWithNumericContent = 0;
        $duplicatePairs = [];
        $seenPairs = [];
        $errorCounts = [];
        $errorSamples = [];
        $invalidRowCount = 0;
        $rowsDone = 0;
        $lineNumber = 1;
        $lastProgressAt = 0;
        $startTime = microtime(true);

        $send('progress', [
            'percent' => 18,
            'message' => 'CSV GI405 siap. Memvalidasi dokumen sumber dan menyiapkan data import...',
            'rows_done' => 0,
            'total' => $estimatedTotalRows,
            'speed' => 0,
            'total_rows' => $estimatedTotalRows,
            'processed_rows' => 0,
            'mode' => 'gi405_hardened',
        ]);

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;
            $row = $this->normalizeGi405CsvRow($row, count($normalizedHeaders));

            if ($this->gi405RowIsEmpty($row)) {
                continue;
            }

            $sourceRowCount++;
            $rowsDone++;

            $rawPendapatan = $pendapatanIndex !== null ? trim((string) ($row[$pendapatanIndex] ?? '')) : '';
            $rawRecovery = $recoveryIndex !== null ? trim((string) ($row[$recoveryIndex] ?? '')) : '';
            $rawKode = $kodeIndex !== null ? ($row[$kodeIndex] ?? '') : '';
            $rawTanggal = $tanggalIndex !== null ? trim((string) ($row[$tanggalIndex] ?? '')) : '';

            $normalizedPendapatan = $rawPendapatan !== '' ? $this->normalizeDecimalValue($rawPendapatan) : null;
            $normalizedRecovery = $rawRecovery !== '' ? $this->normalizeDecimalValue($rawRecovery) : null;

            $rowHasNumericContent = $rawPendapatan !== '' || $rawRecovery !== '';
            if ($rowHasNumericContent) {
                $sourceRowsWithNumericContent++;
            }

            $rowErrors = [];

            if (trim((string) $this->normalizeGi405RecDhKodeValue($rawKode)) === '') {
                $rowErrors[] = 'Kode unit kosong/tidak valid.';
            }

            if ($rawTanggal === '') {
                $rowErrors[] = 'Tanggal kosong.';
            } else {
                try {
                    StrictDateParser::normalize($rawTanggal);
                } catch (\Throwable) {
                    $rowErrors[] = 'Tanggal tidak valid: `' . $rawTanggal . '`.';
                }
            }

            if ($rawPendapatan !== '' && $normalizedPendapatan === null) {
                $rowErrors[] = 'Pendapatan Koreksi PPAP-dr Angsuran PH tidak bisa diparse: `' . $rawPendapatan . '`.';
            }

            if ($rawRecovery !== '' && $normalizedRecovery === null) {
                $rowErrors[] = 'Recovery Non Klaim tidak bisa diparse: `' . $rawRecovery . '`.';
            }

            try {
                $finalRow = $this->invokeParentPrivate('mapExcelRowForInsert', $row, $normalizedHeaders, $context, $timestamp);
            } catch (\Throwable $e) {
                $finalRow = null;
                $rowErrors[] = $e->getMessage();
            }

            if ($finalRow === null && $rowErrors === [] && $activeFilters === []) {
                $rowErrors[] = 'Baris tidak lolos mapping GI405.';
            }

            if ($finalRow !== null) {
                $pair = trim((string) ($finalRow['tanggal'] ?? '')) . ' / ' . trim((string) ($finalRow['kode'] ?? ''));
                if (isset($seenPairs[$pair])) {
                    $duplicatePairs[$pair] = [
                        'pair' => $pair,
                        'first_line' => $seenPairs[$pair],
                        'line' => $lineNumber,
                    ];
                    $rowErrors[] = 'Duplikat pasangan tanggal + kode dengan baris ' . $seenPairs[$pair] . '.';
                } else {
                    $seenPairs[$pair] = $lineNumber;
                }
            }

            if ($rowErrors !== []) {
                $invalidRowCount++;
                foreach ($rowErrors as $message) {
                    $this->addGi405ImportErrorSample($errorCounts, $errorSamples, [
                        'line' => $lineNumber,
                        'kode' => $this->normalizeGi405RecDhKodeValue($rawKode),
                        'tanggal' => $rawTanggal,
                        'raw_pendapatan' => $rawPendapatan,
                        'raw_recovery' => $rawRecovery,
                        'reason' => $message,
                    ]);
                }
            } else {
                if ($normalizedPendapatan !== null) {
                    $sourcePendapatanTotal += (float) $normalizedPendapatan;
                }
                if ($normalizedRecovery !== null) {
                    $sourceRecoveryTotal += (float) $normalizedRecovery;
                }

                $parsedPendapatan = (float) ($finalRow['pendapatan_koreksi_ppap_dr_angsuran_ph'] ?? 0);
                $parsedRecovery = (float) ($finalRow['recovery_non_klaim'] ?? 0);

                $parsedPendapatanTotal += $parsedPendapatan;
                $parsedRecoveryTotal += $parsedRecovery;
                $rowsToInsert[] = $finalRow;
            }

            if (($rowsDone - $lastProgressAt) >= 500) {
                $lastProgressAt = $rowsDone;
                $elapsed = max(microtime(true) - $startTime, 0.001);
                $speed = (int) ($rowsDone / $elapsed);
                $percent = $estimatedTotalRows > 0
                    ? min(92, 18 + (int) (($rowsDone / $estimatedTotalRows) * 70))
                    : 50;

                $send('progress', [
                    'percent' => $percent,
                    'message' => 'Memvalidasi dan memetakan data GI405... (' . $speed . ' baris/detik)',
                    'rows_done' => $rowsDone,
                    'total' => $estimatedTotalRows,
                    'speed' => $speed,
                    'processed_rows' => $rowsDone,
                    'total_rows' => $estimatedTotalRows,
                    'total_success' => count($rowsToInsert),
                    'total_failed' => $invalidRowCount,
                    'mode' => 'gi405_hardened',
                ]);
            }
        }

        fclose($handle);

        if ($duplicatePairs !== []) {
            foreach ($duplicatePairs as $duplicate) {
                $this->addGi405ImportErrorSample($errorCounts, $errorSamples, [
                    'line' => (int) ($duplicate['line'] ?? 0),
                    'kode' => $this->extractKodeFromPair((string) ($duplicate['pair'] ?? '')),
                    'tanggal' => $this->extractTanggalFromPair((string) ($duplicate['pair'] ?? '')),
                    'raw_pendapatan' => '',
                    'raw_recovery' => '',
                    'reason' => 'Duplikat pasangan tanggal + kode juga ditemukan pada baris ' . ($duplicate['first_line'] ?? '?') . '.',
                ], 'duplicate_pair');
            }
        }

        $sourcePendapatanTotal = round($sourcePendapatanTotal, 2);
        $sourceRecoveryTotal = round($sourceRecoveryTotal, 2);
        $parsedPendapatanTotal = round($parsedPendapatanTotal, 2);
        $parsedRecoveryTotal = round($parsedRecoveryTotal, 2);

        if ($sourceRowCount !== count($rowsToInsert) + $invalidRowCount) {
            $this->addGi405ImportErrorSample($errorCounts, $errorSamples, [
                'line' => 0,
                'kode' => null,
                'tanggal' => null,
                'raw_pendapatan' => null,
                'raw_recovery' => null,
                'reason' => 'Jumlah baris sumber tidak seimbang. Sumber: ' . $sourceRowCount . ', valid: ' . count($rowsToInsert) . ', invalid: ' . $invalidRowCount . '.',
            ], 'row_count_mismatch');
        }

        if (abs($sourcePendapatanTotal - $parsedPendapatanTotal) > 0.009) {
            $this->addGi405ImportErrorSample($errorCounts, $errorSamples, [
                'line' => 0,
                'kode' => null,
                'tanggal' => null,
                'raw_pendapatan' => number_format($sourcePendapatanTotal, 2, '.', ''),
                'raw_recovery' => number_format($parsedPendapatanTotal, 2, '.', ''),
                'reason' => 'Total pendapatan hasil parse tidak sama dengan dokumen sumber.',
            ], 'aggregate_pendapatan_mismatch');
        }

        if (abs($sourceRecoveryTotal - $parsedRecoveryTotal) > 0.009) {
            $this->addGi405ImportErrorSample($errorCounts, $errorSamples, [
                'line' => 0,
                'kode' => null,
                'tanggal' => null,
                'raw_pendapatan' => number_format($sourceRecoveryTotal, 2, '.', ''),
                'raw_recovery' => number_format($parsedRecoveryTotal, 2, '.', ''),
                'reason' => 'Total recovery hasil parse tidak sama dengan dokumen sumber.',
            ], 'aggregate_recovery_mismatch');
        }

        if (abs($sourcePendapatanTotal + $sourceRecoveryTotal) > 0.009) {
            $this->addGi405ImportErrorSample($errorCounts, $errorSamples, [
                'line' => 0,
                'kode' => null,
                'tanggal' => null,
                'raw_pendapatan' => number_format($sourcePendapatanTotal, 2, '.', ''),
                'raw_recovery' => number_format($sourceRecoveryTotal, 2, '.', ''),
                'reason' => 'Total pendapatan + recovery dari dokumen sumber tidak balance menjadi 0.',
            ], 'aggregate_source_not_balanced');
        }

        if ($invalidRowCount > 0 || array_sum($errorCounts) > 0) {
            $message = $this->buildGi405ValidationFailureMessage(
                $sourceRowCount,
                $sourceRowsWithNumericContent,
                $invalidRowCount,
                $errorCounts,
                $errorSamples,
                $sourcePendapatanTotal,
                $parsedPendapatanTotal,
                $sourceRecoveryTotal,
                $parsedRecoveryTotal
            );

            if ($jobId > 0) {
                $this->progressService()->markFailed($jobId, $message, 0, max($invalidRowCount, 1), 'failed');
            }

            $send('error', [
                'message' => $message,
                'total_success' => 0,
                'total_failed' => max($invalidRowCount, 1),
                'total_rows' => $sourceRowCount,
            ]);

            return true;
        }

        $inserted = 0;
        DB::transaction(function () use (&$inserted, $rowsToInsert): void {
            foreach (array_chunk($rowsToInsert, 1000) as $batch) {
                DB::table(self::TABLE_NAME)->insert($batch);
                $inserted += count($batch);
            }
        });

        $failed = max(0, count($rowsToInsert) - $inserted);

        if ($jobId > 0) {
            $this->progressService()->updateTotals($jobId, $inserted, $failed, $sourceRowCount, $failed > 0 ? 'failed_partial' : 'completed');
        }

        $send($failed > 0 ? 'error' : 'complete', [
            'total_success' => $inserted,
            'total_failed' => $failed,
            'total_rows' => $sourceRowCount,
        ]);

        return true;
    }

    private function extractGi405BusinessKeys(string $path): array
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['csv', 'txt'], true)
            ? $this->extractGi405BusinessKeysFromCsv($path)
            : $this->extractGi405BusinessKeysFromExcel($path);
    }

    private function extractGi405BusinessKeysFromCsv(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return ['count' => 0, 'duplicates_in_file' => [], 'grouped' => []];
        }

        try {
            $headers = fgetcsv($handle);
            if (!is_array($headers)) {
                return ['count' => 0, 'duplicates_in_file' => [], 'grouped' => []];
            }

            return $this->collectGi405BusinessKeys($headers, function () use ($handle) {
                $row = fgetcsv($handle);
                return $row === false ? null : $row;
            });
        } finally {
            fclose($handle);
        }
    }

    private function extractGi405BusinessKeysFromExcel(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($path);
        try {
            $sheet = $spreadsheet->getActiveSheet();
            $headerIndex = 1;
            $headers = [];

            foreach ($sheet->getRowIterator(1, min(20, $sheet->getHighestRow())) as $row) {
                $cells = [];
                foreach ($row->getCellIterator() as $cell) {
                    $cells[] = trim((string) $cell->getFormattedValue());
                }

                $normalized = array_map(static fn ($value): string => strtoupper(trim((string) $value)), $cells);
                if (in_array('KODE', $normalized, true) && in_array('TANGGAL', $normalized, true)) {
                    $headerIndex = $row->getRowIndex();
                    $headers = $cells;
                    break;
                }
            }

            if ($headers === []) {
                return ['count' => 0, 'duplicates_in_file' => [], 'grouped' => []];
            }

            $rowIterator = $sheet->getRowIterator($headerIndex + 1);

            return $this->collectGi405BusinessKeys($headers, function () use ($rowIterator) {
                if (!$rowIterator->valid()) {
                    return null;
                }

                $row = $rowIterator->current();
                $rowIterator->next();
                $values = [];
                foreach ($row->getCellIterator() as $cell) {
                    $values[] = $cell->getFormattedValue();
                }

                return $values;
            });
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function collectGi405BusinessKeys(array $headers, callable $nextRow): array
    {
        $kodeIndex = null;
        $tanggalIndex = null;

        foreach ($headers as $index => $header) {
            $normalized = strtoupper(trim((string) $header));
            if ($normalized === 'KODE') {
                $kodeIndex = (int) $index;
            } elseif ($normalized === 'TANGGAL') {
                $tanggalIndex = (int) $index;
            }
        }

        if ($kodeIndex === null || $tanggalIndex === null) {
            return ['count' => 0, 'duplicates_in_file' => [], 'grouped' => []];
        }

        $seen = [];
        $duplicates = [];
        $duplicateRowSamples = [];
        $grouped = [];
        $rowNumber = 1;

        while (($row = $nextRow()) !== null) {
            $rowNumber++;
            $kode = $this->normalizeGi405RecDhKodeValue($row[$kodeIndex] ?? '');
            $tanggalRaw = trim((string) ($row[$tanggalIndex] ?? ''));

            if ($kode === null || $kode === '' || $tanggalRaw === '') {
                continue;
            }

            try {
                $tanggal = StrictDateParser::normalize($tanggalRaw);
            } catch (\Throwable) {
                continue;
            }

            $pair = $tanggal . ' / ' . $kode;
            if (isset($seen[$pair])) {
                $duplicates[$pair] = true;
                if (count($duplicateRowSamples) < 5) {
                    $duplicateRowSamples[] = $pair . ' (baris ' . $seen[$pair] . ' & ' . $rowNumber . ')';
                }
                continue;
            }

            $seen[$pair] = $rowNumber;
            $grouped[$tanggal][$kode] = $kode;
        }

        return [
            'count' => count($seen),
            'duplicates_in_file' => array_keys($duplicates),
            'duplicate_row_samples' => $duplicateRowSamples,
            'grouped' => $grouped,
        ];
    }

    private function invokeParentPrivate(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod(ImportExcelController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this, ...$arguments);
    }

    private function detectGi405CsvDelimiter(string $path): string
    {
        $sample = (string) @file_get_contents($path, false, null, 0, 4096);
        $delimiters = [',', ';', "\t", '|'];
        $best = ',';
        $bestCount = -1;

        foreach ($delimiters as $candidate) {
            $count = substr_count($sample, $candidate);
            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function countGi405CsvDataRows(string $path): int
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        $rows = 0;
        while (fgetcsv($handle) !== false) {
            $rows++;
        }

        fclose($handle);

        return max(0, $rows - 1);
    }

    private function normalizeGi405CsvRow(array $row, int $expectedColumns): array
    {
        $row = array_values($row);
        $count = count($row);
        if ($count < $expectedColumns) {
            return array_pad($row, $expectedColumns, null);
        }

        if ($count > $expectedColumns) {
            return array_slice($row, 0, $expectedColumns);
        }

        return $row;
    }

    private function gi405RowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) ($value ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function findHeaderIndex(array $headers, string $target): ?int
    {
        foreach ($headers as $index => $header) {
            if (strcasecmp(trim((string) $header), trim($target)) === 0) {
                return (int) $index;
            }
        }

        return null;
    }

    private function addGi405ImportErrorSample(array &$errorCounts, array &$errorSamples, array $payload, ?string $key = null): void
    {
        $resolvedKey = $key ?: Str::slug((string) ($payload['reason'] ?? 'invalid'), '_');
        $errorCounts[$resolvedKey] = (int) ($errorCounts[$resolvedKey] ?? 0) + 1;

        if (count($errorSamples) < self::MAX_ERROR_SAMPLES) {
            $errorSamples[] = $payload;
        }
    }

    private function buildGi405ValidationFailureMessage(
        int $sourceRowCount,
        int $rowsWithNumericContent,
        int $invalidRowCount,
        array $errorCounts,
        array $errorSamples,
        float $sourcePendapatanTotal,
        float $parsedPendapatanTotal,
        float $sourceRecoveryTotal,
        float $parsedRecoveryTotal
    ): string {
        $summary = [];
        foreach ($errorCounts as $key => $count) {
            $summary[] = str_replace('_', ' ', $key) . ': ' . number_format((int) $count, 0, ',', '.');
        }

        $samples = [];
        foreach ($errorSamples as $sample) {
            $parts = [];
            if (!empty($sample['line'])) {
                $parts[] = 'baris ' . $sample['line'];
            }
            if (!empty($sample['kode'])) {
                $parts[] = 'kode ' . $sample['kode'];
            }
            if (!empty($sample['tanggal'])) {
                $parts[] = 'tanggal ' . $sample['tanggal'];
            }
            if (($sample['raw_pendapatan'] ?? null) !== null && $sample['raw_pendapatan'] !== '') {
                $parts[] = 'pendapatan `' . e((string) $sample['raw_pendapatan']) . '`';
            }
            if (($sample['raw_recovery'] ?? null) !== null && $sample['raw_recovery'] !== '') {
                $parts[] = 'recovery `' . e((string) $sample['raw_recovery']) . '`';
            }

            $samples[] = implode(' | ', $parts) . ' -> ' . e((string) ($sample['reason'] ?? 'invalid'));
        }

        return 'Validasi dokumen sumber GI405 - Rec. DH gagal.<br><br>'
            . 'Total baris sumber: <b>' . number_format($sourceRowCount, 0, ',', '.') . '</b><br>'
            . 'Baris dengan konten numerik: <b>' . number_format($rowsWithNumericContent, 0, ',', '.') . '</b><br>'
            . 'Total baris invalid: <b>' . number_format($invalidRowCount, 0, ',', '.') . '</b><br>'
            . 'Ringkasan error: <b>' . e(implode(' | ', $summary)) . '</b><br><br>'
            . 'Total sumber pendapatan: <b>' . number_format($sourcePendapatanTotal, 2, '.', ',') . '</b><br>'
            . 'Total hasil parse pendapatan: <b>' . number_format($parsedPendapatanTotal, 2, '.', ',') . '</b><br>'
            . 'Total sumber recovery: <b>' . number_format($sourceRecoveryTotal, 2, '.', ',') . '</b><br>'
            . 'Total hasil parse recovery: <b>' . number_format($parsedRecoveryTotal, 2, '.', ',') . '</b><br><br>'
            . 'Contoh baris error:<br><b>' . implode('<br>', $samples) . '</b>';
    }

    private function extractTanggalFromPair(string $pair): ?string
    {
        $parts = explode(' / ', $pair, 2);
        return $parts[0] ?? null;
    }

    private function extractKodeFromPair(string $pair): ?string
    {
        $parts = explode(' / ', $pair, 2);
        return $parts[1] ?? null;
    }
}
