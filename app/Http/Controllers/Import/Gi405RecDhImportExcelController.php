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
    private const TABLE_NAME = 'gi405_singlerow';
    private const MAX_ERROR_SAMPLES = 8;
    private const REQUIRED_HEADERS = [
        'PERIODE',
        'BRANCH',
        'CURRENCY',
        'POSTING CONTROL',
        'ACCOUNT NUMBER',
        'C/C',
        'P/C',
        'F/C',
        'DESCRIPTION',
        'BEGINING BALANCE',
        'EQUIVALENTS IDR',
        'EQUIVALENTS USD',
        'TODAY DEBIT',
        'TODAY CREDIT',
        'ENDING BALANCE',
    ];

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

                $relativePath = urldecode($sessionPath);
                $path = Storage::path($relativePath);
                if (!file_exists($path)) {
                    $send('error_msg', ['message' => 'File tidak ditemukan di server. Silakan upload ulang.']);
                    return;
                }

                $source = $this->prepareGi405PreviewSource($relativePath, $path, $send);
                $relativePath = $source['relative_path'];
                $path = $source['absolute_path'];
                session(['excel_path' => $relativePath]);
                request()->session()->save();

                $useCacheKey = $cacheKey ?: ('excel_preview_' . md5(urldecode($sessionPath) . '|' . microtime(true)));
                $redirect = route('import.gi405.preview', ['ck' => $useCacheKey]);

                $send('progress', ['percent' => 20, 'message' => 'File ditemukan. Menyiapkan preview...', 'step' => 1]);
                $this->primeExcelPreviewCache($relativePath, $path, $useCacheKey, $send);
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
        $this->ensureGi405SessionUsesPreviewSource();

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
        $this->ensureGi405SessionUsesPreviewSource();

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

    private function ensureGi405SessionUsesPreviewSource(): void
    {
        $relativePath = urldecode((string) session('excel_path', ''));
        if ($relativePath === '') {
            return;
        }

        $path = Storage::path($relativePath);
        if (!file_exists($path)) {
            return;
        }

        try {
            $source = $this->prepareGi405PreviewSource($relativePath, $path);
            if ($source['relative_path'] !== $relativePath) {
                session(['excel_path' => $source['relative_path']]);
            }
        } catch (\Throwable $e) {
            Log::warning('GI405 session source normalization skipped: ' . $e->getMessage(), [
                'path' => $path,
            ]);
        }
    }

    private function prepareGi405PreviewSource(string $relativePath, string $absolutePath, ?callable $send = null): array
    {
        if ($this->isCsvFile($absolutePath)) {
            return [
                'relative_path' => $relativePath,
                'absolute_path' => $absolutePath,
            ];
        }

        $send && $send('progress', [
            'percent' => 35,
            'message' => 'Memilih worksheet GI405 dan menormalisasi sumber preview...',
            'step' => 1,
        ]);

        return $this->stageGi405WorkbookSheetToCsv($absolutePath, $send);
    }

    private function stageGi405WorkbookSheetToCsv(string $path, ?callable $send = null): array
    {
        $fingerprint = md5($path . '|' . ((string) (@filemtime($path) ?: '0')) . '|' . ((string) (@filesize($path) ?: '0')));
        $directory = 'excel_imports/gi405_singlerow';
        $relativeCsvPath = $directory . '/gi405_singlerow_' . $fingerprint . '.csv';
        $absoluteCsvPath = Storage::path($relativeCsvPath);

        if (file_exists($absoluteCsvPath) && filesize($absoluteCsvPath) > 0) {
            return [
                'relative_path' => $relativeCsvPath,
                'absolute_path' => $absoluteCsvPath,
            ];
        }

        Storage::makeDirectory($directory);

        $nativeStage = $this->excelStagingService()->stageXlsxSheetWithHeadersToCsv(
            $path,
            self::REQUIRED_HEADERS,
            $absoluteCsvPath,
            $send
        );

        if ($nativeStage !== null && file_exists($absoluteCsvPath) && filesize($absoluteCsvPath) > 0) {
            return [
                'relative_path' => $relativeCsvPath,
                'absolute_path' => $absoluteCsvPath,
            ];
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($path);
        try {
            [$sheet, $headerRow] = $this->resolveGi405WorksheetAndHeaderRow($spreadsheet);

            $output = @fopen($absoluteCsvPath, 'wb');
            if ($output === false) {
                throw new \RuntimeException('Gagal membuat CSV staging GI405 Single Row.');
            }

            try {
                fputcsv($output, self::REQUIRED_HEADERS);

                $highestRow = (int) $sheet->getHighestRow();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
                $headerMap = $this->buildGi405HeaderColumnMap($sheet, $headerRow, $highestColumnIndex);

                for ($rowIndex = $headerRow + 1; $rowIndex <= $highestRow; $rowIndex++) {
                    $row = [];
                    $hasData = false;

                    foreach (self::REQUIRED_HEADERS as $header) {
                        $columnIndex = $headerMap[$header] ?? null;
                        $value = $columnIndex === null ? '' : $this->readGi405FormattedCell($sheet, $columnIndex, $rowIndex);
                        if (trim((string) $value) !== '') {
                            $hasData = true;
                        }
                        $row[] = $value;
                    }

                    if ($hasData) {
                        fputcsv($output, $row);
                    }
                }
            } finally {
                fclose($output);
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        return [
            'relative_path' => $relativeCsvPath,
            'absolute_path' => $absoluteCsvPath,
        ];
    }

    private function resolveGi405WorksheetAndHeaderRow(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): array
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $headerRow = $this->findGi405HeaderRow($sheet);
            if ($headerRow !== null) {
                return [$sheet, $headerRow];
            }
        }

        throw new \RuntimeException('Worksheet GI405 dengan header wajib tidak ditemukan.');
    }

    private function findGi405HeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): ?int
    {
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $highestRow = min(50, (int) $sheet->getHighestRow());

        for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
            $headers = [];
            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $headers[] = $this->normalizeGi405HeaderLabel(
                    $this->readGi405FormattedCell($sheet, $columnIndex, $rowIndex)
                );
            }

            $matched = 0;
            foreach (self::REQUIRED_HEADERS as $requiredHeader) {
                if (in_array($this->normalizeGi405HeaderLabel($requiredHeader), $headers, true)) {
                    $matched++;
                }
            }

            if ($matched === count(self::REQUIRED_HEADERS)) {
                return $rowIndex;
            }
        }

        return null;
    }

    private function buildGi405HeaderColumnMap(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $headerRow, int $highestColumnIndex): array
    {
        $lookup = [];
        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $lookup[$this->normalizeGi405HeaderLabel($this->readGi405FormattedCell($sheet, $columnIndex, $headerRow))] = $columnIndex;
        }

        $map = [];
        foreach (self::REQUIRED_HEADERS as $header) {
            $normalized = $this->normalizeGi405HeaderLabel($header);
            if (!isset($lookup[$normalized])) {
                throw new \RuntimeException("Kolom wajib GI405 tidak ditemukan: {$header}");
            }
            $map[$header] = $lookup[$normalized];
        }

        return $map;
    }

    private function readGi405FormattedCell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $columnIndex, int $rowIndex): string
    {
        $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex;

        return trim(str_replace(["\r", "\n"], ' ', (string) $sheet->getCell($coordinate)->getFormattedValue()));
    }

    private function normalizeGi405HeaderLabel(string $value): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($value)) ?? '');
    }

    private function validateGi405DuplicateGuard(): ?string
    {
        $path = Storage::path((string) session('excel_path', ''));
        if ($path === '' || !file_exists($path)) {
            return null;
        }

        $periods = $this->extractGi405Periods($path);
        if ($periods === []) {
            return null;
        }

        // GI405 Single Row tidak memakai filter. Cukup cegah import ulang untuk periode yang sudah ada.
        $existingRows = DB::table(self::TABLE_NAME)
            ->whereIn('periode', $periods)
            ->select('periode')
            ->distinct()
            ->limit(10)
            ->pluck('periode')
            ->map(static fn ($value): string => (string) $value)
            ->all();

        if ($existingRows === []) {
            return null;
        }

        return 'Data GI405 Single Row untuk periode yang sama sudah ada di database.<br><br>'
            . 'Periode bentrok: <b>' . e(implode(', ', array_slice($existingRows, 0, 5))) . '</b><br>'
            . 'Import dibatalkan agar file/periode yang sama tidak masuk dua kali.';
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
        array $importOptions = [],
        bool $fullVectorization = false
    ): bool {
        if ($tableName !== 'gi405_rec_dh') {
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
                $importOptions,
                $fullVectorization
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

    private function extractGi405Periods(string $path): array
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['csv', 'txt'], true)
            ? $this->extractGi405PeriodsFromCsv($path)
            : $this->extractGi405PeriodsFromExcel($path);
    }

    private function extractGi405PeriodsFromCsv(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        try {
            $delimiter = $this->detectGi405CsvDelimiter($path);
            $headers = fgetcsv($handle, 0, $delimiter);
            if (!is_array($headers)) {
                return [];
            }

            $periodeIndex = $this->findGi405HeaderIndex($headers, 'periode');
            if ($periodeIndex === null) {
                return [];
            }

            return $this->collectGi405Periods(static function () use ($handle, $delimiter) {
                $row = fgetcsv($handle, 0, $delimiter);
                return $row === false ? null : $row;
            }, $periodeIndex);
        } finally {
            fclose($handle);
        }
    }

    private function extractGi405PeriodsFromExcel(string $path): array
    {
        $staged = $this->stageGi405WorkbookSheetToCsv($path);

        return $this->extractGi405PeriodsFromCsv((string) $staged['absolute_path']);
    }

    private function findGi405HeaderIndex(array $headers, string $expected): ?int
    {
        $expected = $this->normalizeGi405HeaderLabel($expected);
        foreach ($headers as $index => $header) {
            if ($this->normalizeGi405HeaderLabel((string) $header) === $expected) {
                return (int) $index;
            }
        }

        return null;
    }

    private function collectGi405Periods(callable $nextRow, int $periodeIndex): array
    {
        $periods = [];

        while (($row = $nextRow()) !== null) {
            $rawPeriod = trim((string) ($row[$periodeIndex] ?? ''));
            if ($rawPeriod === '') {
                continue;
            }

            try {
                $period = StrictDateParser::normalize($rawPeriod);
            } catch (\Throwable) {
                $period = null;
            }

            if ($period !== null) {
                $periods[$period] = true;
            }
        }

        return array_keys($periods);
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
            [$sheet, $headerIndex] = $this->resolveGi405WorksheetAndHeaderRow($spreadsheet);
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
            $headers = [];
            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $headers[] = $this->readGi405FormattedCell($sheet, $columnIndex, $headerIndex);
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
        $periodeIndex = null;
        $branchIndex = null;
        $postingControlIndex = null;
        $accountNumberIndex = null;

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeGi405HeaderLabel((string) $header);
            if ($normalized === 'periode') {
                $periodeIndex = (int) $index;
            } elseif ($normalized === 'branch') {
                $branchIndex = (int) $index;
            } elseif ($normalized === 'posting control') {
                $postingControlIndex = (int) $index;
            } elseif ($normalized === 'account number') {
                $accountNumberIndex = (int) $index;
            }
        }

        if ($periodeIndex === null || $branchIndex === null || $postingControlIndex === null || $accountNumberIndex === null) {
            return ['count' => 0, 'duplicates_in_file' => [], 'grouped' => []];
        }

        $seen = [];
        $duplicates = [];
        $duplicateRowSamples = [];
        $grouped = [];
        $rowNumber = 1;

        while (($row = $nextRow()) !== null) {
            $rowNumber++;
            $periodeRaw = trim((string) ($row[$periodeIndex] ?? ''));
            $branch = trim((string) ($row[$branchIndex] ?? ''));
            $postingControl = trim((string) ($row[$postingControlIndex] ?? ''));
            $accountNumber = trim((string) ($row[$accountNumberIndex] ?? ''));

            if ($periodeRaw === '' || $branch === '' || $postingControl === '' || $accountNumber === '') {
                continue;
            }

            try {
                $periode = StrictDateParser::normalize($periodeRaw);
            } catch (\Throwable) {
                continue;
            }

            $pair = $periode . ' / ' . $branch . ' / ' . $postingControl . ' / ' . $accountNumber;
            if (isset($seen[$pair])) {
                $duplicates[$pair] = true;
                if (count($duplicateRowSamples) < 5) {
                    $duplicateRowSamples[] = $pair . ' (baris ' . $seen[$pair] . ' & ' . $rowNumber . ')';
                }
                continue;
            }

            $seen[$pair] = $rowNumber;
            $grouped[$periode][$branch][$postingControl][$accountNumber] = $accountNumber;
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
