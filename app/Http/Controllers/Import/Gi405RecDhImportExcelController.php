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
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Gi405RecDhImportExcelController extends ImportExcelController
{
    private const TABLE_NAME = 'gi405_rec_dh';

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
            $sample = implode(', ', array_slice($fileDuplicates, 0, 5));

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
        $grouped = [];

        while (($row = $nextRow()) !== null) {
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
                continue;
            }

            $seen[$pair] = true;
            $grouped[$tanggal][$kode] = $kode;
        }

        return [
            'count' => count($seen),
            'duplicates_in_file' => array_keys($duplicates),
            'grouped' => $grouped,
        ];
    }
}
