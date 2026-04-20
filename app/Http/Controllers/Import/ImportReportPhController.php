<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Import\Concerns\AllocatesGapIds;
use App\Http\Controllers\Import\Concerns\SmartCsvImportSupport;
use App\Services\Import\ExcelImportJobService;
use App\Services\Import\ExcelStagingService;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Services\Import\MySqlBulkLoadService;
use App\Support\ReportDataSyncService;
use App\Support\StrictDateParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportReportPhController extends Controller
{
    use AllocatesGapIds;
    use SmartCsvImportSupport;

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
    private const EXCEL_HEADER_ALIASES = [
        'no' => 'textbox3',
        'nomor_rekening' => 'acctno',
        'nomor_rekening_1' => 'acctno',
        'nomor_rekening1' => 'acctno',
        'segmen' => 'segmen_dashboard',
        'deskripsi_segmen' => 'description',
        'produk' => 'produk_dashboard',
        'currency' => 'curtyp',
        'sisa_awal_ph_pokok' => 'saldo_pertama_ph_pokok',
        'sisa_awal_ph_bunga' => 'saldo_pertama_ph_bunga',
        'sisa_akhir_ph_pokok' => 'pokok',
        'sisa_akhir_ph_bunga' => 'bunga',
        'kumulatif_angsuran_pokok' => 'angpok',
        'kumulatif_angsuran_bunga' => 'angbung',
        'sisa_pokok' => 'sisapok',
        'sisa_bunga' => 'sisabun',
        'alih_tagih_asuransi' => 'clmamt1',
        'saldo_tagihan_alih_tagih_asuransi' => 'clmapr1',
        'total_kewajiban' => 'os_penuh_berjalan1',
        'kecamatan_tempat_tinggal' => 'kecamatan_t_tinggal',
        'kelurahan_tempat_tinggal' => 'kelurahan_t_tinggal',
        'kodepos_tempat_tinggal' => 'kodepos_t_tinggal',
        'kecamatan_tempat_usaha' => 'kecamatan_t_usaha',
        'kelurahan_tempat_usaha' => 'kelurahan_t_usaha',
        'kodepos_tempat_usaha' => 'kodepos_t_usaha',
        'pn_pengelola_2' => 'pn_pengelola2',
        'pn_crr' => 'pn_crr1',
        'pn_jumlah' => 'jumlah_pn',
        'deffered_bunga_cutoff_ph' => 'deffered_bunga_ph',
        'sai_tunggakan_cutoff_ph' => 'sai_tunggakan_ph',
        'sai_deffered_cutoff_ph' => 'sai_deffered_ph',
    ];
    private const STAGED_CSV_TEMP_DIR = 'app/report_ph_stage';
    private const FILTERED_CSV_TEMP_DIR = 'app/report_ph_filtered';
    private const BULK_LOAD_TEMP_DIR = 'app/report_ph_bulk_stage';
    private const BULK_STAGE_DELIMITER = ',';
    private const INSERT_BATCH_SIZE = 1000;

    public function upload(Request $request)
    {
        $request->validate([
            'id_report' => 'required',
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $path = $request->file('file')->store('report_ph_imports');

        session([
            'active_id_report' => $request->input('id_report'),
            'import_type' => 'report_ph',
            'report_ph_file' => $path,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'path' => $path,
            ]);
        }

        return redirect()->route('import.reportph.preview');
    }

    private function isExcelFile(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true);
    }

    private function findPython(): ?string
    {
        foreach (['python', 'python3', 'py'] as $cmd) {
            $output = @shell_exec(escapeshellcmd($cmd) . ' --version 2>&1');
            if ($output && str_contains($output, 'Python 3')) {
                return $cmd;
            }
        }

        return null;
    }

    private function reportPhStageCacheKey(string $relativePath): string
    {
        return 'report_ph_excel_stage_' . md5($relativePath);
    }

    private function getStagedExcelState(string $relativePath): array
    {
        $cached = Cache::get($this->reportPhStageCacheKey($relativePath));
        return is_array($cached) ? $cached : [];
    }

    private function putStagedExcelState(string $relativePath, array $payload): void
    {
        Cache::put($this->reportPhStageCacheKey($relativePath), $payload, now()->addHours(4));
    }

    private function clearStagedExcelState(string $relativePath): void
    {
        Cache::forget($this->reportPhStageCacheKey($relativePath));
    }

    private function createStagedCsvPath(): string
    {
        $directory = storage_path(self::STAGED_CSV_TEMP_DIR);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        return $directory . DIRECTORY_SEPARATOR . 'lw325_ph_' . Str::random(12) . '.csv';
    }

    private function createFilteredCsvPath(): string
    {
        $directory = storage_path(self::FILTERED_CSV_TEMP_DIR);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        return $directory . DIRECTORY_SEPARATOR . 'lw325_ph_filtered_' . Str::random(12) . '.csv';
    }

    private function normalizeActiveFiltersForPolars(array $activeFilters): array
    {
        $normalizedFilters = [];

        foreach ($activeFilters as $columnIndex => $allowedValues) {
            $column = self::TARGET_COLUMNS[(int) $columnIndex] ?? null;
            if ($column === null) {
                continue;
            }

            $values = [];
            foreach ((array) $allowedValues as $value) {
                $normalizedValue = $this->normalizeCellValue($column, $value);
                if ($normalizedValue === null) {
                    $normalizedValue = trim((string) $value);
                }

                $normalizedValue = trim((string) $normalizedValue);
                if ($normalizedValue === '') {
                    continue;
                }

                $values[$normalizedValue] = true;
            }

            if ($values !== []) {
                $normalizedFilters[$column] = array_keys($values);
            }
        }

        return $normalizedFilters;
    }

    private function stageFilteredCsvWithPolars(?callable $send, string $sourcePath, array $activeFilters, ?string $delimiter = null): ?array
    {
        return $this->runPolarsProcessor(
            $send,
            $sourcePath,
            $activeFilters,
            [
                'output_mode' => 'preview',
                'output_csv_path' => $this->createFilteredCsvPath(),
            ],
            $delimiter
        );
    }

    private function stageDirectLoadCsvWithPolars(?callable $send, string $sourcePath, array $activeFilters, array $selectedColumns, ?string $delimiter = null): ?array
    {
        $loadColumns = $this->buildPolarsLoadColumns($selectedColumns);
        $result = $this->runPolarsProcessor(
            $send,
            $sourcePath,
            $activeFilters,
            [
                'output_mode' => 'bulk_load',
                'output_csv_path' => $this->createBulkLoadTempCsvPath((int) (microtime(true) * 1000)),
                'load_columns' => $loadColumns,
                'timestamp' => now()->toDateTimeString(),
                'unique_suffix' => self::UNIQUE_SUFFIX,
            ],
            $delimiter
        );

        if ($result === null) {
            return null;
        }

        $outputPath = (string) ($result['path'] ?? '');
        if ($outputPath === '' || !is_file($outputPath) || !$this->validatePolarsBulkLoadCsv($outputPath)) {
            if ($outputPath !== '' && is_file($outputPath)) {
                @unlink($outputPath);
            }

            return null;
        }

        return $result;
    }

    private function buildPolarsLoadColumns(array $selectedColumns): array
    {
        $loadColumns = ['uniqueid_namareport', 'created_at', 'updated_at'];

        foreach ($selectedColumns as $index) {
            $column = self::TARGET_COLUMNS[(int) $index] ?? null;
            if ($column === null || in_array($column, ['id', 'uniqueid_namareport'], true)) {
                continue;
            }

            $loadColumns[] = $column;
        }

        return array_values(array_unique($loadColumns));
    }

    private function estimateImportRows(string $path, int $headerLine = 1): int
    {
        if ($path === '' || !file_exists($path)) {
            return 0;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        try {
            $recordNumber = 0;
            $dataRows = 0;

            while (($row = $this->readCsvRecord($handle, self::COLUMN_DELIMITER)) !== false) {
                $recordNumber++;
                if ($recordNumber <= max(1, $headerLine)) {
                    continue;
                }

                if ($this->isEmptyCsvRow($row)) {
                    continue;
                }

                $dataRows++;
            }

            return $dataRows;
        } finally {
            fclose($handle);
        }
    }

    private function validatePolarsBulkLoadCsv(string $csvPath): bool
    {
        $handle = @fopen($csvPath, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            $checked = 0;

            while (($row = fgetcsv($handle, 0, self::BULK_STAGE_DELIMITER, '"', '\\')) !== false) {
                if (!is_array($row) || empty(array_filter($row, static fn ($value): bool => trim((string) $value) !== ''))) {
                    continue;
                }

                $checked++;
                $uniqueId = trim((string) ($row[0] ?? ''));
                $periode = trim((string) ($row[3] ?? ''));
                $acctno = trim((string) ($row[4] ?? ''));

                if (
                    $uniqueId === ''
                    || str_starts_with(strtolower($uniqueId), 'unknown_')
                    || $periode === ''
                    || StrictDateParser::normalize($periode) === null
                    || $acctno === ''
                    || str_starts_with(strtolower($acctno), 'kelurahan ')
                    || str_starts_with(strtolower($acctno), 'kecamatan ')
                ) {
                    return false;
                }

                if ($checked >= 5) {
                    return true;
                }
            }
        } finally {
            fclose($handle);
        }

        return false;
    }

    private function runPolarsProcessor(
        ?callable $send,
        string $sourcePath,
        array $activeFilters,
        array $extraConfig,
        ?string $delimiter = null
    ): ?array {
        $pythonExe = $this->findPython();
        // OPTIMIZATION: Use optimized v3 script if available, fallback to v2
        $scriptPath = base_path('scripts/lw325_ph_polars_processor_v3.py');
        if (!file_exists($scriptPath)) {
            $scriptPath = base_path('scripts/lw325_ph_polars_processor.py');
        }

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $outputCsvPath = (string) ($extraConfig['output_csv_path'] ?? '');
        if ($outputCsvPath === '') {
            return null;
        }

        $configFile = storage_path('app/report_ph_polars_filter_' . uniqid() . '.json');
        $delimiter = $delimiter !== null && $delimiter !== '' ? $delimiter : $this->smartDetectCsvDelimiter($sourcePath);

        file_put_contents($configFile, json_encode(array_merge([
            'file_path' => $sourcePath,
            'delimiter' => $delimiter,
            'output_csv_path' => $outputCsvPath,
            'active_filters' => $this->normalizeActiveFiltersForPolars($activeFilters),
        ], $extraConfig), JSON_UNESCAPED_UNICODE));

        $cmd = escapeshellarg($pythonExe)
            . ' ' . escapeshellarg($scriptPath)
            . ' --config ' . escapeshellarg($configFile)
            . ' --mode stage';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            @unlink($configFile);
            @unlink($outputCsvPath);
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $donePayload = null;
        $pythonError = null;
        $pythonProducedOutput = false;
        // OPTIMIZATION: Track last update time to throttle progress updates
        $lastProgressUpdate = microtime(true);
        $progressThrottle = 0.1; // seconds

        $processLine = static function (string $line) use ($send, &$donePayload, &$pythonError, &$lastProgressUpdate, $progressThrottle): void {
            $line = trim($line);
            if ($line === '') {
                return;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                return;
            }

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'progress') {
                if ($send !== null) {
                    // OPTIMIZATION: Throttle progress updates
                    $now = microtime(true);
                    if ($now - $lastProgressUpdate >= $progressThrottle) {
                        $send('progress', $data);
                        $lastProgressUpdate = $now;
                    }
                }
                return;
            }

            if ($type === 'done') {
                $donePayload = $data;
                return;
            }

            if ($type === 'error') {
                $pythonError = $data['message'] ?? 'Polars filter LW325_PH gagal.';
            }
        };

        try {
            // OPTIMIZATION: Reduced sleep interval for faster response
            $sleepInterval = 25000; // microseconds (reduced from 50000)
            
            while (true) {
                $status = proc_get_status($process);
                $chunk = fread($pipes[1], 131072); // Increased from 65536

                if ($chunk !== false && $chunk !== '') {
                    $pythonProducedOutput = true;
                    $buffer .= $chunk;
                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);
                        $processLine($line);
                    }
                }

                if (!$status['running']) {
                    break;
                }

                usleep($sleepInterval);
            }

            $remaining = stream_get_contents($pipes[1]);
            if ($remaining) {
                $pythonProducedOutput = true;
                $buffer .= $remaining;
                foreach (explode("\n", $buffer) as $line) {
                    $processLine($line);
                }
            }
        } finally {
            if (isset($pipes[1]) && is_resource($pipes[1])) {
                fclose($pipes[1]);
            }
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                fclose($pipes[2]);
            }
            if (is_resource($process)) {
                proc_close($process);
            }

            @unlink($configFile);
        }

        if (!$pythonProducedOutput || $pythonError !== null || !$donePayload || !file_exists($outputCsvPath)) {
            @unlink($outputCsvPath);
            return null;
        }

        return [
            'path' => $outputCsvPath,
            'cleanup' => true,
            'written_rows' => (int) ($donePayload['written_rows'] ?? $donePayload['total_rows'] ?? 0),
            'periods' => array_values((array) ($donePayload['dates'] ?? [])),
            'load_columns' => array_values((array) ($extraConfig['load_columns'] ?? [])),
            'backend' => 'polars',
        ];
    }

    private function detectExcelHeaderViaPython(string $path): ?array
    {
        $pythonExe = $this->findPython();
        $scriptPath = base_path('scripts/excel_gpu_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return $this->detectExcelHeaderViaPhpSpreadsheet($path);
        }

        $configFile = storage_path('app/report_ph_excel_init_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode(['file_path' => $path], JSON_UNESCAPED_UNICODE));

        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $cmd = escapeshellarg($pythonExe)
            . ' ' . escapeshellarg($scriptPath)
            . ' --config ' . escapeshellarg($configFile)
            . ' --mode init'
            . ' 2>' . $nullDevice;

        $output = @shell_exec($cmd);
        @unlink($configFile);

        if (!$output) {
            return null;
        }

        $result = json_decode(trim($output), true);
        if (!$result || ($result['status'] ?? '') !== 'ok') {
            return $this->detectExcelHeaderViaPhpSpreadsheet($path);
        }

        $payload = [
            'header_index' => (int) ($result['header_index'] ?? 0),
            'total_rows' => (int) ($result['total_rows'] ?? 0),
            'header_values' => (array) ($result['header_values'] ?? []),
        ];

        return $this->isLikelyExcelHeaderRow((array) $payload['header_values'])
            ? $payload
            : $this->detectExcelHeaderViaPhpSpreadsheet($path);
    }

    private function detectExcelHeaderViaPhpSpreadsheet(string $path): ?array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $highestColumn = $sheet->getHighestDataColumn();
            $highestDataRow = (int) $sheet->getHighestDataRow();

            $bestCandidate = null;

            // OPTIMIZED: Reduce header scan from 200 to 20 rows (saves 4-8 seconds)
            // Header almost always in first 20 rows, scanning to 200 is wasteful for large files
            $maxHeaderScanRows = min($highestDataRow, 20);
            for ($rowNumber = 1; $rowNumber <= $maxHeaderScanRows; $rowNumber++) {
                $rowValues = $sheet->rangeToArray(
                    'A' . $rowNumber . ':' . $highestColumn . $rowNumber,
                    null,
                    true,
                    false
                )[0] ?? [];

                $rowValues = $this->trimTrailingEmptyExcelCells($rowValues);
                if ($rowValues === []) {
                    continue;
                }

                $score = $this->scoreExcelHeaderCandidate($rowValues);
                if ($score <= 0) {
                    continue;
                }

                if ($bestCandidate === null || $score > $bestCandidate['score']) {
                    $bestCandidate = [
                        'header_index' => $rowNumber - 1,
                        'total_rows' => $highestDataRow,
                        'header_values' => $rowValues,
                        'score' => $score,
                    ];
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if ($bestCandidate === null) {
                return null;
            }

            unset($bestCandidate['score']);

            return $bestCandidate;
        } catch (\Throwable $e) {
            Log::warning(self::REPORT_LABEL . ' fallback header detection gagal: ' . $e->getMessage());

            return null;
        }
    }

    private function trimTrailingEmptyExcelCells(array $rowValues): array
    {
        while ($rowValues !== []) {
            $lastValue = end($rowValues);
            if (trim((string) $lastValue) !== '') {
                break;
            }

            array_pop($rowValues);
        }

        return array_values($rowValues);
    }

    private function scoreExcelHeaderCandidate(array $rowValues): int
    {
        $normalizedHeaders = $this->normalizeExcelHeaders($rowValues);
        $normalizedHeaders = array_values(array_filter(
            array_map(fn ($header) => $this->normalizeHeader($header), $normalizedHeaders),
            fn ($header) => $header !== '' && !str_starts_with($header, 'col_')
        ));

        if (count($normalizedHeaders) < 4) {
            return 0;
        }

        return $this->scoreHeaderCandidate($normalizedHeaders);
    }

    private function isLikelyExcelHeaderRow(array $headerValues): bool
    {
        return $this->scoreExcelHeaderCandidate($headerValues) > 0;
    }

    private function stageExcelToCsv(callable $send, string $sourcePath, int $headerIndex, array $normalizedHeaders): ?array
    {
        $pythonExe = $this->findPython();
        $scriptPath = base_path('scripts/excel_gpu_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $stagedCsvPath = $this->createStagedCsvPath();
        $configFile = storage_path('app/report_ph_excel_stage_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode([
            'file_path' => $sourcePath,
            'header_index' => $headerIndex,
            'normalized_headers' => $normalizedHeaders,
            'output_csv_path' => $stagedCsvPath,
        ], JSON_UNESCAPED_UNICODE));

        $cmd = escapeshellarg($pythonExe)
            . ' ' . escapeshellarg($scriptPath)
            . ' --config ' . escapeshellarg($configFile)
            . ' --mode stage';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            @unlink($configFile);
            @unlink($stagedCsvPath);
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $donePayload = null;
        $pythonError = null;

        $processLine = function (string $line) use ($send, &$donePayload, &$pythonError): void {
            $line = trim($line);
            if ($line === '') {
                return;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                return;
            }

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'progress') {
                $send('progress', $data);
                return;
            }

            if ($type === 'done') {
                $donePayload = $data;
                return;
            }

            if ($type === 'error') {
                $pythonError = $data['message'] ?? 'Python staging error tidak diketahui';
            }
        };

        while (true) {
            $status = proc_get_status($process);
            $chunk = fread($pipes[1], 65536);
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $processLine($line);
                }
            }

            if (!$status['running']) {
                break;
            }

            // OPTIMIZED: Add keepalive heartbeat to prevent SSE timeout (keep browser connection alive)
            $send('heartbeat', ['timestamp' => time()]);
            usleep(50000);
        }

        $remaining = stream_get_contents($pipes[1]);
        if ($remaining) {
            $buffer .= $remaining;
            foreach (explode("\n", $buffer) as $line) {
                $processLine($line);
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        @unlink($configFile);

        if ($pythonError !== null || !$donePayload || !file_exists($stagedCsvPath)) {
            @unlink($stagedCsvPath);
            return null;
        }

        return [
            'staged_csv_path' => $stagedCsvPath,
            'total_rows' => (int) ($donePayload['total_rows'] ?? 0),
            'header_index' => 0,
            'headers' => array_values($normalizedHeaders),
        ];
    }

    private function normalizeExcelHeaders(array $headerValues): array
    {
        $headers = [];
        $occurrences = [];

        foreach ($headerValues as $index => $value) {
            $label = trim((string) $value);

            if ($label === '') {
                $headers[$index] = 'COL_' . $index;
                continue;
            }

            $normalized = $this->normalizeHeader($label);
            if ($normalized === '') {
                $headers[$index] = $label;
                continue;
            }

            $occurrences[$normalized] = ($occurrences[$normalized] ?? 0) + 1;
            $occurrence = $occurrences[$normalized];

            $headers[$index] = self::EXCEL_HEADER_OCCURRENCE_ALIASES[$normalized][$occurrence]
                ?? self::EXCEL_HEADER_ALIASES[$normalized]
                ?? $label;
        }

        return $headers;
    }

    private function resolveWorkingImportPath(string $relativePath): string
    {
        $absolutePath = Storage::path($relativePath);
        if (!$this->isExcelFile($absolutePath)) {
            return $absolutePath;
        }

        $stageState = $this->getStagedExcelState($relativePath);
        $stagedCsvPath = (string) ($stageState['staged_csv_path'] ?? '');

        return ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) ? $stagedCsvPath : $absolutePath;
    }

    private function createPreviewStateKey(string $relativePath): string
    {
        return 'report_ph_preview_' . md5($relativePath . '|' . microtime(true) . '|' . Str::random(6));
    }

    private function buildFastExcelPreviewState(string $relativePath, string $absolutePath): ?array
    {
        $previewPayload = $this->excelStagingService()->extractIndexedPreviewViaNativeXlsx($absolutePath, 2500);
        if ($previewPayload === null) {
            return null;
        }

        $rawHeaders = array_values((array) ($previewPayload['headers'] ?? []));
        $normalizedSourceHeaders = $this->normalizeExcelHeaders($rawHeaders);
        $sourceIndexes = $this->buildSourceIndexes($normalizedSourceHeaders);
        $previewRows = [];
        $uniqueValues = [];

        foreach (self::TARGET_COLUMNS as $index => $column) {
            $uniqueValues[$index] = [];
        }

        $detectedPeriode = null;
        foreach ((array) ($previewPayload['preview_rows_indexed'] ?? []) as $rowValues) {
            $previewRow = [];

            foreach (self::TARGET_COLUMNS as $displayIndex => $column) {
                $sourceIndex = $sourceIndexes[$column] ?? null;
                $value = $sourceIndex !== null ? ($rowValues[$sourceIndex] ?? null) : null;
                $normalizedValue = $this->normalizeCellValue($column, $value);
                $previewRow[$displayIndex] = $normalizedValue;

                $formattedValue = trim((string) ($normalizedValue ?? ''));
                if ($formattedValue !== '' && count($uniqueValues[$displayIndex]) < 100) {
                    $uniqueValues[$displayIndex][$formattedValue] = true;
                }

                if ($detectedPeriode === null && $column === 'periode' && $normalizedValue !== null) {
                    $detectedPeriode = (string) $normalizedValue;
                }
            }

            $previewRows[] = $previewRow;
        }

        $formattedUniqueValues = [];
        foreach ($uniqueValues as $displayIndex => $valuesMap) {
            $values = array_keys($valuesMap);
            usort($values, 'strnatcmp');
            $formattedUniqueValues[$displayIndex] = $values;
        }

        $resolvedTotalRows = max(0, (int) ($previewPayload['total_rows'] ?? 0));
        $minimumObservedRows = count($previewRows) + ((int) ($previewPayload['header_index'] ?? 0)) + 1;
        if ($resolvedTotalRows > 0 && $resolvedTotalRows < $minimumObservedRows) {
            $resolvedTotalRows = 0;
        }

        $displayFilterMap = [];
        foreach (self::TARGET_COLUMNS as $displayIndex => $column) {
            if (array_key_exists($column, $sourceIndexes)) {
                $displayFilterMap[$displayIndex] = (int) $sourceIndexes[$column];
            }
        }

        $previewMeta = [
            'path' => $relativePath,
            'staged_csv_path' => null,
            'header_index' => (int) ($previewPayload['header_index'] ?? 0),
            'normalized_headers' => self::TARGET_COLUMNS,
            'source_headers' => $normalizedSourceHeaders,
            'total_rows' => $resolvedTotalRows,
            'delimiter' => null,
            'detected_periode' => $detectedPeriode,
        ];

        return [
            'previewData' => $previewRows,
            'formattedUniqueValues' => $formattedUniqueValues,
            'displayFilterMap' => $displayFilterMap,
            'previewMeta' => $previewMeta,
        ];
    }

    public function preparePreviewStream(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $relativePath = session('report_ph_file');
        request()->session()->save();

        return response()->stream(function () use ($relativePath) {
            $send = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                if (!$relativePath) {
                    $send('error_msg', ['message' => 'Sesi upload ' . self::REPORT_LABEL . ' tidak ditemukan.']);
                    return;
                }

                $absolutePath = Storage::path($relativePath);
                if (!file_exists($absolutePath)) {
                    $send('error_msg', ['message' => 'File CSV ' . self::REPORT_LABEL . ' tidak ditemukan di server.']);
                    return;
                }

                $workingPath = $absolutePath;

                if ($this->isExcelFile($absolutePath)) {
                    $stageState = $this->getStagedExcelState($relativePath);
                    $stagedCsvPath = (string) ($stageState['staged_csv_path'] ?? '');

                    if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
                        $workingPath = $stagedCsvPath;
                    } else {
                        $send('progress', ['percent' => 20, 'message' => 'Membaca header Excel ' . self::REPORT_LABEL . '...']);

                        $fastPreviewState = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) === 'xlsx'
                            ? $this->buildFastExcelPreviewState($relativePath, $absolutePath)
                            : null;

                        if ($fastPreviewState !== null) {
                            $previewStateKey = $this->createPreviewStateKey($relativePath);
                            $this->excelImportJobService()->putPreviewState($previewStateKey, $fastPreviewState);

                            $previewMeta = (array) ($fastPreviewState['previewMeta'] ?? []);
                            session([
                                'report_ph_preview_meta' => $previewMeta,
                                'excel_preview_meta' => $previewMeta,
                                'import_display_to_source_map' => (array) ($fastPreviewState['displayFilterMap'] ?? []),
                            ]);

                            $send('progress', ['percent' => 72, 'message' => 'Preview cepat siap. Mengalihkan ke halaman preview...']);
                            $send('ready', [
                                'redirect' => route('import.reportph.preview', [
                                    'file_path' => $relativePath,
                                    'preview_state_key' => $previewStateKey,
                                ]),
                                'detected_periode' => $previewMeta['detected_periode'] ?? null,
                            ]);
                            return;
                        }

                        $send('progress', ['percent' => 30, 'message' => 'Preview cepat tidak tersedia. Menyiapkan staging Excel penuh...']);
                        $excelMeta = $this->detectExcelHeaderViaPython($absolutePath);
                        if ($excelMeta === null) {
                            $send('error_msg', ['message' => 'Excel ' . self::REPORT_LABEL . ' membutuhkan Python staging agar bisa dipreview.']);
                            return;
                        }

                        $send('progress', ['percent' => 45, 'message' => 'Mengonversi Excel ke CSV staging ' . self::REPORT_LABEL . '...']);
                        $stageResult = $this->stageExcelToCsv(
                            $send,
                            $absolutePath,
                            (int) $excelMeta['header_index'],
                            $this->normalizeExcelHeaders((array) $excelMeta['header_values'])
                        );

                        if ($stageResult === null) {
                            $send('error_msg', ['message' => 'Gagal membuat CSV staging dari Excel ' . self::REPORT_LABEL . '.']);
                            return;
                        }

                        $this->putStagedExcelState($relativePath, $stageResult);
                        $workingPath = (string) $stageResult['staged_csv_path'];
                    }
                }

                $send('progress', ['percent' => 70, 'message' => 'Memvalidasi struktur CSV ' . self::REPORT_LABEL . '...']);
                $context = $this->buildCsvContext($workingPath);

                $send('progress', ['percent' => 75, 'message' => 'Struktur valid. Menyiapkan halaman preview...']);
                $send('ready', [
                    'redirect' => route('import.reportph.preview', [
                        'file_path' => $relativePath,
                    ]),
                    'detected_periode' => $context['periode'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::error('REPORT PH PREPARE PREVIEW ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
                $send('error_msg', ['message' => 'Gagal menyiapkan preview ' . self::REPORT_LABEL . ': ' . $e->getMessage()]);
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

        $previewStateKey = trim((string) $request->input('preview_state_key', ''));
        $previewState = $this->excelImportJobService()->getPreviewState($previewStateKey);
        $previewMeta = (array) ($previewState['previewMeta'] ?? []);
        $previewPath = (string) ($previewMeta['path'] ?? '');
        $previewStageCsv = (string) ($previewMeta['staged_csv_path'] ?? '');

        if (
            $previewStateKey !== ''
            && $previewPath === $relativePath
            && !empty($previewState['previewData'])
            && ($previewStageCsv === '' || !file_exists($previewStageCsv))
        ) {
            session([
                'report_ph_preview_meta' => $previewMeta,
                'excel_preview_meta' => $previewMeta,
                'import_display_to_source_map' => (array) ($previewState['displayFilterMap'] ?? []),
            ]);

            return view('import.preview', [
                'headers' => self::TARGET_COLUMNS,
                'previewData' => (array) ($previewState['previewData'] ?? []),
                'filePath' => $relativePath,
                'formattedUniqueValues' => (array) ($previewState['formattedUniqueValues'] ?? []),
                'currentDelimiter' => self::COLUMN_DELIMITER,
                'processRoute' => route('import.reportph.process'),
                'previewRoute' => route('import.reportph.preview.refresh'),
                'initRoute' => route('import.reportph.init'),
                'streamRoute' => route('import.reportph.stream'),
                'backRoute' => route('import.index'),
                'previewStateKey' => $previewStateKey,
                'filterOptionsRoute' => route('import.preview.filter-options'),
                'lockDelimiterSelector' => true,
                'fixedDelimiterLabel' => 'Koma ( , )',
                'hideDelimiterCard' => true,
                'disableArea6AutoFilter' => true,
            ]);
        }

        $workingPath = $this->resolveWorkingImportPath($relativePath);
        if (!file_exists($workingPath)) {
            return redirect()->route('import.index')->with('error', 'File staging ' . self::REPORT_LABEL . ' tidak ditemukan. Silakan upload ulang.');
        }

        try {
            $context = $this->buildCsvContext($workingPath);
        } catch (\Throwable $e) {
            return redirect()->route('import.index')->with('error', 'Struktur CSV ' . self::REPORT_LABEL . ' tidak dikenali: ' . $e->getMessage());
        }

        $previewData = [];
        $uniqueValues = [];
        foreach ($context['headers'] as $index => $header) {
            $uniqueValues[$index] = [];
        }

        $handle = fopen($workingPath, 'r');
        if ($handle === false) {
            return redirect()->route('import.index')->with('error', 'Gagal membuka file CSV ' . self::REPORT_LABEL . '.');
        }

        $lineNumber = 0;
        $rowsProcessed = 0;
        $previewLimit = 2500;
        $uniquesProcessLimit = 3000;  // OPTIMIZED: Only collect uniques from first 3000 rows (saves 10-15 sec)
        $fullColumns = [];  // Track columns that reached limit
        
        try {
            while (($row = $this->readCsvRecord($handle, $context['delimiter'])) !== false) {
                $lineNumber++;
                if ($lineNumber <= $context['header_line']) {
                    continue;
                }

                $row = $this->mapCsvRow($context, $row);
                if ($row === null) {
                    continue;
                }

                if (count($previewData) < $previewLimit) {
                    $previewData[] = $row;
                }

                // OPTIMIZED: Only collect unique values from first N rows (saves 5-10 sec)
                $rowsProcessed++;
                if ($rowsProcessed <= $uniquesProcessLimit) {
                    foreach ($row as $colIndex => $value) {
                        // Skip if column not being tracked or already full
                        if (!isset($uniqueValues[$colIndex]) || isset($fullColumns[$colIndex])) {
                            continue;
                        }

                        $uniqueValues[$colIndex][trim((string) ($value ?? ''))] = true;

                        // OPTIMIZED: Mark column as full when it reaches 100 uniques
                        if (count($uniqueValues[$colIndex]) >= 100) {
                            $fullColumns[$colIndex] = true;
                        }
                    }
                } else {
                    // OPTIMIZED: Stop processing once preview data is complete
                    if (count($previewData) >= $previewLimit) {
                        break;
                    }
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
            'processRoute' => route('import.reportph.process'),
            'previewRoute' => route('import.reportph.preview.refresh'),
            'initRoute' => route('import.reportph.init'),
            'streamRoute' => route('import.reportph.stream'),
            'backRoute' => route('import.index'),
            'lockDelimiterSelector' => true,
            'fixedDelimiterLabel' => 'Delimiter terdeteksi otomatis',
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

        $workingPath = $this->resolveWorkingImportPath($relativePath);
        if (!file_exists($workingPath)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'File staging Excel ' . self::REPORT_LABEL . ' tidak ditemukan.',
            ], 422);
        }

        if (!$this->isValidReportSelection()) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Report yang dipilih tidak mengarah ke tabel ' . self::TABLE_NAME . '.',
            ], 422);
        }

        $this->releaseSessionLockIfNeeded();

        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json', '{}'), true) ?: [];
        $previewState = $this->excelImportJobService()->getPreviewState($request->input('preview_state_key'));
        $previewMeta = !empty($previewState['previewMeta']) && is_array($previewState['previewMeta'])
            ? (array) $previewState['previewMeta']
            : (array) session('report_ph_preview_meta', []);

        $detectedPeriode = (string) ($previewMeta['detected_periode'] ?? '');
        $headerIndex = max(0, (int) ($previewMeta['header_index'] ?? 0));
        $queueHeaders = array_values((array) ($previewMeta['source_headers'] ?? []));
        $stagedCsvPath = (string) ($previewMeta['staged_csv_path'] ?? '');
        $isFastExcelPreview = $previewMeta !== []
            && (string) ($previewMeta['path'] ?? '') === $relativePath
            && $this->isExcelFile($absolutePath)
            && ($stagedCsvPath === '' || !file_exists($stagedCsvPath))
            && $queueHeaders !== [];

        $context = null;
        $workingPath = $this->resolveWorkingImportPath($relativePath);

        if ($isFastExcelPreview) {
            $periode = $detectedPeriode !== '' ? $detectedPeriode : null;
            $filterBackend = 'pending_polars';
            $bulkLoadColumns = $this->buildPolarsLoadColumns($selectedColumns);
            $previewTotalRows = max(0, (int) ($previewMeta['total_rows'] ?? 0));
            $sourceTotalRows = $previewTotalRows > 0 ? $previewTotalRows : 0;
        } else {
            if (!file_exists($workingPath)) {
                return response()->json([
                    'status' => 'error',
                    'title' => 'Gagal!',
                    'text' => 'File staging Excel ' . self::REPORT_LABEL . ' tidak ditemukan.',
                ], 422);
            }

            try {
                $context = $this->buildCsvContext($workingPath);
            } catch (\Throwable $e) {
                return response()->json([
                    'status' => 'error',
                    'title' => 'Gagal!',
                    'text' => 'Struktur CSV ' . self::REPORT_LABEL . ' tidak dikenali: ' . $e->getMessage(),
                ], 422);
            }

            $periode = $context['periode'] ?? null;
            $filterBackend = 'pending_polars';
            $bulkLoadColumns = $this->buildPolarsLoadColumns($selectedColumns);
            $totalRows = $this->estimateImportRows($workingPath, (int) ($context['header_line'] ?? 1));
            $headerIndex = max(0, ((int) ($context['header_line'] ?? 1)) - 1);
            $queueHeaders = array_values((array) ($context['headers'] ?? self::TARGET_COLUMNS));
            $sourceTotalRows = $totalRows + $headerIndex + 1;

            if ($totalRows === 0) {
                return response()->json([
                    'status' => 'warning',
                    'title' => 'Tidak Ada Data',
                    'text' => 'Tidak ada baris data untuk diproses.',
                ], 422);
            }
        }

        if (!$periode) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Periode pada file ' . self::REPORT_LABEL . ' tidak valid atau kosong.',
            ], 422);
        }

        if (DB::table(self::TABLE_NAME)->whereDate('periode', $periode)->exists()) {
            $this->cleanupUploadedFile($relativePath);

            return response()->json([
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => 'Data untuk periode <b>' . Carbon::parse($periode)->translatedFormat('d F Y') . '</b> sudah ada di tabel <b class="text-uppercase">' . self::TABLE_NAME . '</b>.',
            ], 422);
        }

        $filteredCsvPath = null;

        $jobId = $this->excelImportJobService()->createImportJobRecord(
            (int) session('active_id_report'),
            $absolutePath,
            $sourceTotalRows,
            [
                'controller' => static::class,
                'mode' => 'report_ph_import',
                'table_name' => self::TABLE_NAME,
                'file_path' => $relativePath,
                'header_index' => $headerIndex,
                'active_filters_hash' => sha1(json_encode($activeFilters)),
                'normalized_headers_hash' => sha1(json_encode($queueHeaders)),
            ],
            auth()->id() ?? 1
        );

        $importParams = [
            'job_id' => $jobId,
            'file_path' => $relativePath,
            'periode' => $periode,
            'selected_columns' => $selectedColumns,
            'active_filters' => $activeFilters,
            'total_rows' => $sourceTotalRows,
            'header_index' => $headerIndex,
            'delimiter' => $isFastExcelPreview ? self::COLUMN_DELIMITER : (string) (($context['delimiter'] ?? self::COLUMN_DELIMITER)),
            'table_name' => self::TABLE_NAME,
            'staged_csv_path' => (!$isFastExcelPreview && $workingPath !== $absolutePath) ? $workingPath : null,
            'filtered_csv_path' => $filteredCsvPath,
            'bulk_load_columns' => $bulkLoadColumns,
            'filter_backend' => $filterBackend,
        ];
        session(['report_ph_import_params' => $importParams]);
        Cache::put('report_ph_import_params_' . $jobId, $importParams, now()->addHours(4));
        $this->excelImportJobService()->putImportJobState($jobId, [
            'params' => $importParams,
            'headers' => $queueHeaders,
        ]);
        $this->progressService()->markQueued($jobId, [
            'status' => 'queued',
            'phase' => 'polars',
            'mode' => 'polars',
            'percent' => 0,
            'message' => 'Fase Polars LW325 - PH siap diproses lewat worker imports-high.',
            'total_rows' => $sourceTotalRows,
            'processed_rows' => 0,
            'queue' => 'imports-high',
        ]);

        return response()->json([
            'status' => 'success',
            'job_id' => $jobId,
            'total_rows' => $sourceTotalRows,
        ]);
    }

    public function processImportStream(Request $request)
    {
        $sessionParams = session('report_ph_import_params', []);
        $jobId = (int) ($sessionParams['job_id'] ?? $request->query('job_id', 0));
        if ($jobId <= 0) {
            return response()->stream(function (): void {
                echo "event: error\n";
                echo 'data: ' . json_encode(['message' => 'Job import tidak ditemukan.']) . "\n\n";
            }, 422, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-store',
                'X-Accel-Buffering' => 'no',
                'Connection' => 'keep-alive',
            ]);
        }

        request()->session()->save();
        $this->executionService()->dispatch($jobId, 'Fase Polars LW325 - PH dimulai. Menyiapkan import fresh.');

        return $this->executionService()->streamStatus($request, $jobId, false);
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

        $workingPath = $this->resolveWorkingImportPath($relativePath);
        if (!file_exists($workingPath)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'File staging Excel ' . self::REPORT_LABEL . ' tidak ditemukan.',
            ], 422);
        }

        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json', '{}'), true) ?: [];

        try {
            $context = $this->buildCsvContext($workingPath);
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

        $this->releaseSessionLockIfNeeded();

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

        $filteredCsvPath = null;
        $bulkLoadColumns = [];
        $polarsResult = $this->stageDirectLoadCsvWithPolars(null, $workingPath, $activeFilters, $selectedColumns, $context['delimiter'] ?? null);
        if ($polarsResult !== null) {
            $filteredCsvPath = (string) ($polarsResult['path'] ?? '');
            $bulkLoadColumns = array_values((array) ($polarsResult['load_columns'] ?? []));
        }

        if ($filteredCsvPath !== null && file_exists($filteredCsvPath)) {
            $stagingPath = $filteredCsvPath;
            $loadColumns = $bulkLoadColumns !== [] ? $bulkLoadColumns : $this->buildPolarsLoadColumns($selectedColumns);
            $preparedRows = (int) ($polarsResult['written_rows'] ?? 0);

            if ($preparedRows === 0) {
                $extraPaths = [$filteredCsvPath];
                $this->cleanupUploadedFile($relativePath, $extraPaths);

                return response()->json([
                    'status' => 'warning',
                    'title' => 'Tidak Ada Data',
                    'text' => 'Tidak ada baris data yang lolos filter untuk diimport.',
                ], 422);
            }

            $totalSuccess = 0;
            $totalFailed = 0;
            $lastErrorMsg = '';

            try {
                if ($this->supportsNativeBulkLoad()) {
                    $totalSuccess = $this->loadCsvIntoMysqlChunked(
                        $stagingPath,
                        self::TABLE_NAME,
                        $loadColumns,
                        function (int $processed, int $total, int $inserted) use ($jobId) {
                            $this->progressService()->updateImportProgress($jobId, [
                                'processed_rows' => $processed,
                                'total_rows' => $total,
                                'percent' => (int) (($processed / max(1, $total)) * 100),
                                'message' => "Menulis ke DB: {$inserted} baris masuk...",
                            ]);
                        },
                        8000,
                        $preparedRows
                    );
                    $totalFailed = max(0, $preparedRows - $totalSuccess);
                } else {
                    throw new \RuntimeException('LOAD DATA LOCAL INFILE tidak tersedia pada koneksi aktif.');
                }
            } catch (\Throwable $e) {
                $lastErrorMsg = Str::limit($e->getMessage(), 800, '...');
                Log::warning(self::REPORT_LABEL . ' bulk load fallback: ' . $e->getMessage());

                $fallback = $this->insertStagedCsvInBatches($stagingPath, $loadColumns);
                $totalSuccess = $fallback['total_success'];
                $totalFailed = $fallback['total_failed'];
                if ($fallback['last_error'] !== '') {
                    $lastErrorMsg = $fallback['last_error'];
                }
            }

            if ($totalFailed === 0) {
                $extraPaths = [$filteredCsvPath];
                $this->cleanupUploadedFile($relativePath, $extraPaths);
            }

            return response()->json([
                'status' => $totalFailed > 0 ? 'warning' : 'success',
                'title' => $totalFailed > 0 ? 'Import Memiliki Kendala!' : 'Berhasil!',
                'text' => $totalFailed > 0
                    ? "Berhasil: {$totalSuccess} baris.<br>Gagal: {$totalFailed} baris." . ($lastErrorMsg !== '' ? "<br><br><b>Info MySQL:</b><br><small class='text-danger'>" . htmlspecialchars($lastErrorMsg, ENT_QUOTES) . '</small>' : '')
                    : "Sebanyak {$totalSuccess} baris data telah sukses masuk ke tabel <b class='text-uppercase'>" . self::TABLE_NAME . '</b>.',
            ]);
        }

        $handle = fopen($workingPath, 'r');
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
            while (($row = $this->readCsvRecord($handle, $context['delimiter'])) !== false) {
                $lineNumber++;
                if ($lineNumber <= $context['header_line']) {
                    continue;
                }

                $row = $this->mapCsvRow($context, $row);
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
            $extraPaths = $filteredCsvPath !== null && file_exists($filteredCsvPath) ? [$filteredCsvPath] : [];
            $this->cleanupUploadedFile($relativePath, $extraPaths);
        }

        return response()->json([
            'status' => $totalFailed > 0 ? 'warning' : 'success',
            'title' => $totalFailed > 0 ? 'Import Memiliki Kendala!' : 'Berhasil!',
            'text' => $totalFailed > 0
                ? "Berhasil: {$totalSuccess} baris.<br>Gagal: {$totalFailed} baris." . ($lastErrorMsg !== '' ? "<br><br><b>Info MySQL:</b><br><small class='text-danger'>" . htmlspecialchars($lastErrorMsg, ENT_QUOTES) . '</small>' : '')
                : "Sebanyak {$totalSuccess} baris data telah sukses masuk ke tabel <b class='text-uppercase'>" . self::TABLE_NAME . '</b>.',
        ]);
    }

    public function executeQueuedImport(array $state, ?callable $send = null): array
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        DB::disableQueryLog();

        $send ??= static function (string $event, array $payload): void {
        };

        $params = (array) ($state['params'] ?? []);
        $jobId = (int) ($state['job_id'] ?? ($params['job_id'] ?? 0));
        $relativePath = (string) ($params['file_path'] ?? '');
        $absolutePath = $relativePath !== '' ? Storage::path($relativePath) : '';

        if ($absolutePath === '' || !file_exists($absolutePath)) {
            return [
                'status' => 'failed',
                'message' => 'File sumber LW325 - PH tidak ditemukan di server.',
                'total_success' => 0,
                'total_failed' => 0,
                'total_rows' => 0,
            ];
        }

        $selectedColumns = array_map('intval', (array) ($params['selected_columns'] ?? []));
        $activeFilters = (array) ($params['active_filters'] ?? []);
        $delimiter = (string) ($params['delimiter'] ?? self::COLUMN_DELIMITER);
        $sourcePath = $absolutePath;
        $cleanupPaths = [];
        $stagedCsvPath = (string) ($params['staged_csv_path'] ?? '');
        if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
            $sourcePath = $stagedCsvPath;
            $cleanupPaths[] = $stagedCsvPath;
        }

        $send('progress', [
            'percent' => 12,
            'message' => 'LW325 - PH direct Polars dimulai dari file sumber...',
            'rows_done' => 0,
            'total' => (int) ($params['total_rows'] ?? 0),
            'speed' => 0,
        ]);

        $polarsResult = $this->stageDirectLoadCsvWithPolars($send, $sourcePath, $activeFilters, $selectedColumns, $delimiter);

        if ($polarsResult === null && $this->isExcelFile($sourcePath)) {
            $send('progress', [
                'percent' => 18,
                'message' => 'Direct Polars dari Excel gagal. Menyiapkan staging CSV fallback...',
                'rows_done' => 0,
                'total' => (int) ($params['total_rows'] ?? 0),
                'speed' => 0,
            ]);

            $excelMeta = $this->detectExcelHeaderViaPython($sourcePath);
            if ($excelMeta !== null) {
                $stageResult = $this->stageExcelToCsv(
                    $send,
                    $sourcePath,
                    (int) ($excelMeta['header_index'] ?? 0),
                    $this->normalizeExcelHeaders((array) ($excelMeta['header_values'] ?? []))
                );

                if ($stageResult !== null && !empty($stageResult['staged_csv_path']) && file_exists((string) $stageResult['staged_csv_path'])) {
                    $sourcePath = (string) $stageResult['staged_csv_path'];
                    $cleanupPaths[] = $sourcePath;
                    $polarsResult = $this->stageDirectLoadCsvWithPolars($send, $sourcePath, $activeFilters, $selectedColumns, self::COLUMN_DELIMITER);
                }
            }
        }

        if ($polarsResult === null) {
            return app(ImportExcelController::class)->executeQueuedImport($state, $send);
        }

        $filteredCsvPath = (string) ($polarsResult['path'] ?? '');
        if ($filteredCsvPath === '' || !file_exists($filteredCsvPath)) {
            return app(ImportExcelController::class)->executeQueuedImport($state, $send);
        }

        $cleanupPaths[] = $filteredCsvPath;
        $loadColumns = array_values((array) ($polarsResult['load_columns'] ?? []));
        if ($loadColumns === []) {
            $loadColumns = $this->buildPolarsLoadColumns($selectedColumns);
        }

        $preparedRows = (int) ($polarsResult['written_rows'] ?? 0);
        if ($preparedRows <= 0) {
            foreach (array_unique($cleanupPaths) as $cleanupPath) {
                if (is_string($cleanupPath) && $cleanupPath !== '' && file_exists($cleanupPath)) {
                    @unlink($cleanupPath);
                }
            }

            return [
                'status' => 'failed',
                'message' => 'Tidak ada baris data LW325 - PH yang siap diimport.',
                'total_success' => 0,
                'total_failed' => 0,
                'total_rows' => 0,
            ];
        }

        $totalSuccess = 0;
        $totalFailed = 0;
        $lastErrorMsg = '';

        try {
            if ($this->supportsNativeBulkLoad()) {
                $totalSuccess = $this->loadCsvIntoMysqlChunked(
                    $filteredCsvPath,
                    self::TABLE_NAME,
                    $loadColumns,
                    function (int $processed, int $total, int $inserted) use ($send, $preparedRows): void {
                        $ratio = $total > 0 ? min(1, $processed / max(1, $total)) : 1;
                        $percent = 96 + (int) floor($ratio * 3);
                        $send('progress', [
                            'percent' => min(99, $percent),
                            'message' => "Memuat LW325 - PH ke MySQL... ({$inserted} baris masuk)",
                            'rows_done' => $processed,
                            'total' => $preparedRows,
                            'speed' => 0,
                        ]);
                    },
                    8000,
                    $preparedRows
                );
                $totalFailed = max(0, $preparedRows - $totalSuccess);
            } else {
                throw new \RuntimeException('LOAD DATA LOCAL INFILE tidak tersedia pada koneksi aktif.');
            }
        } catch (\Throwable $e) {
            $lastErrorMsg = Str::limit($e->getMessage(), 800, '...');
            Log::warning(self::REPORT_LABEL . ' queued bulk load fallback: ' . $e->getMessage());

            $fallback = $this->insertStagedCsvInBatchesWithProgress($filteredCsvPath, $loadColumns, $preparedRows, $send);
            $totalSuccess = (int) ($fallback['total_success'] ?? 0);
            $totalFailed = (int) ($fallback['total_failed'] ?? 0);
            if (($fallback['last_error'] ?? '') !== '') {
                $lastErrorMsg = (string) $fallback['last_error'];
            }
        } finally {
            foreach (array_unique($cleanupPaths) as $cleanupPath) {
                if (is_string($cleanupPath) && $cleanupPath !== '' && file_exists($cleanupPath)) {
                    @unlink($cleanupPath);
                }
            }
        }

        if ($jobId > 0) {
            $this->progressService()->updateTotals(
                $jobId,
                $totalSuccess,
                $totalFailed,
                $preparedRows,
                $totalFailed === 0 ? 'completed' : ($totalSuccess > 0 ? 'failed_partial' : 'failed')
            );
        }

        if ($totalSuccess > 0) {
            $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, (string) ($params['periode'] ?? null));
        }

        if ($totalFailed > 0) {
            return [
                'status' => $totalSuccess > 0 ? 'failed_partial' : 'failed',
                'message' => $lastErrorMsg !== '' ? $lastErrorMsg : 'Import LW325 - PH selesai dengan kegagalan parsial.',
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'total_rows' => $preparedRows,
            ];
        }

        $send('complete', [
            'total_success' => $totalSuccess,
            'total_failed' => 0,
            'total_rows' => $preparedRows,
        ]);

        return [
            'status' => 'completed',
            'total_success' => $totalSuccess,
            'total_failed' => 0,
            'total_rows' => $preparedRows,
        ];
    }

    private function buildCsvContext(string $path): array
    {
        $profile = $this->smartProfileCsvSource($path, ['periode', 'acctno', 'tgl_ph']);
        $delimiter = (string) ($profile['delimiter'] ?? self::COLUMN_DELIMITER);
        $sampleRows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV.');
        }

        try {
            $recordNumber = 0;
            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false && $recordNumber < 20) {
                $recordNumber++;
                if ($this->isEmptyCsvRow($row)) {
                    continue;
                }

                $sampleRows[] = [
                    'record' => $row,
                    'record_number' => $recordNumber,
                ];
            }
        } finally {
            fclose($handle);
        }

        if (empty($sampleRows)) {
            throw new \RuntimeException('Isi file CSV kosong.');
        }

        $structure = $this->detectCsvStructure($sampleRows, $delimiter);
        if (!$structure) {
            throw new \RuntimeException('Baris header CSV ' . self::REPORT_LABEL . ' tidak ditemukan.');
        }

        $periode = $this->findPeriodeValue($path, $structure['header_row']['record_number'], $structure['parsed_headers'], $delimiter)
            ?? $this->findPeriodeValueFromMetadata($sampleRows, $structure['header_row']['record_number']);

        return [
            'delimiter' => $delimiter,
            'header_line' => $structure['header_row']['record_number'],
            'source_headers' => $structure['parsed_headers'],
            'source_indexes' => $this->buildSourceIndexes($structure['parsed_headers']),
            'headers' => self::TARGET_COLUMNS,
            'periode' => $periode,
            'detected_profile_summary' => [
                'delimiter' => $delimiter,
                'serialized_rows' => (bool) ($profile['serialized_rows'] ?? false),
                'field_count_consistent' => (bool) ($profile['field_count_consistent'] ?? true),
                'sample_bad_rows' => array_values((array) ($profile['sample_bad_rows'] ?? [])),
            ],
        ];
    }

    private function detectCsvStructure(array $sampleRows, string $delimiter): ?array
    {
        $bestCandidate = null;

        foreach ($sampleRows as $row) {
            $sourceHeaders = array_values((array) ($row['record'] ?? []));
            $parsedHeaders = array_values(array_filter(
                $this->normalizeHeadersWithAliases($sourceHeaders),
                fn ($value) => $value !== ''
            ));
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

    private function findPeriodeValue(string $path, int $headerLineNumber, array $sourceHeaders, string $delimiter): ?string
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
            $recordNumber = 0;
            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                $recordNumber++;
                if ($recordNumber <= $headerLineNumber) {
                    continue;
                }

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

    private function findPeriodeValueFromMetadata(array $sampleRows, int $headerLineNumber): ?string
    {
        foreach ($sampleRows as $row) {
            if (($row['record_number'] ?? 0) >= $headerLineNumber) {
                continue;
            }

            $record = array_values((array) ($row['record'] ?? []));
            if ($record === []) {
                continue;
            }

            $line = implode(',', array_map(static fn ($value): string => trim((string) $value), $record));
            if (preg_match('/periode\s*data\s*:\s*([^,]+)/i', $line, $matches) !== 1) {
                continue;
            }

            return $this->normalizeDateValue($matches[1] ?? null);
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
            while (($row = $this->readCsvRecord($handle, $context['delimiter'])) !== false) {
                $lineNumber++;
                if ($lineNumber <= $context['header_line']) {
                    continue;
                }

                $row = $this->mapCsvRow($context, $row);
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

    private function parseCsvLine(string $line, ?string $delimiter = null): array
    {
        return $this->smartParseCsvLine($line, $delimiter ?? self::COLUMN_DELIMITER, true);
    }

    private function trimTrailingEmptyCells(array $cells): array
    {
        return $this->smartTrimTrailingEmptyCsvCells($cells);
    }

    private function normalizeHeader(?string $header): string
    {
        $header = trim((string) $header);
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = strtolower($header);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        return trim($header, '_');
    }

    /**
     * Normalize a CSV header row into the canonical lw325_ph shape.
     */
    private function normalizeHeadersWithAliases(array $headers): array
    {
        $normalizedHeaders = [];
        $cifOccurrence = 0;

        foreach (array_values($headers) as $index => $header) {
            $label = trim((string) $header);
            if ($label === '') {
                $normalizedHeaders[] = 'col_' . $index;
                continue;
            }

            $normalized = $this->normalizeHeader($label);

            if (in_array($normalized, ['cif', 'cif_1', 'cif1'], true)) {
                $cifOccurrence++;
                $normalizedHeaders[] = $cifOccurrence === 1 ? 'cif1' : 'cif';
                continue;
            }

            $normalizedHeaders[] = self::EXCEL_HEADER_ALIASES[$normalized] ?? $normalized;
        }

        return $normalizedHeaders;
    }

    private function readCsvRecord($handle, string $delimiter): array|false
    {
        $row = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if ($row === false) {
            return false;
        }

        // Beberapa export PH menyimpan satu record data sebagai satu field utuh
        // sehingga `fgetcsv()` hanya melihat 1 kolom walaupun isi record masih
        // berisi delimiter asli. Saat itu, fallback ke parser string yang lebih
        // toleran agar preview/import tetap bisa membaca baris data.
        if (count($row) === 1) {
            $singleField = (string) ($row[0] ?? '');
            if ($singleField !== '' && substr_count($singleField, $delimiter) >= 1) {
                $fallbackRow = $this->smartParseCsvLine($singleField, $delimiter, true);
                if (count($fallbackRow) > 1) {
                    $row = $fallbackRow;
                }
            }
        }

        $normalizedRow = [];
        foreach ($row as $value) {
            $normalizedRow[] = $this->smartNormalizeQuotedCsvCellValue($value);
        }

        return $this->trimTrailingEmptyCells($normalizedRow);
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
        $headerCount = count($sourceHeaders);
        $dataCount = count($data);

        if ($dataCount < $headerCount) {
            // CSV export PH sering menghilangkan kolom kosong di ujung baris, mis. `FLAG_KLAIM,CLMAMT,CLMAPR` => `Y,,`.
            // Padding menjaga preview/import tetap membaca row valid dengan trailing empty cells.
            $data = array_pad($data, $headerCount, '');
            $dataCount = count($data);
        }

        if ($dataCount !== $headerCount) {
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
        $value = trim($this->smartNormalizeQuotedCsvCellValue($value));
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
        $value = trim($this->smartNormalizeQuotedCsvCellValue($value));
        if ($value === '') {
            return null;
        }

        return StrictDateParser::normalize($value, [
            'n/j/Y g:i:s A',
            'm/d/Y g:i:s A',
        ]);
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

        if (is_int($value)) {
            return $value . '.00';
        }

        if (is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        $value = trim($this->smartNormalizeQuotedCsvCellValue($value));
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^0-9,\.\-]/', '', $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        $negative = str_starts_with($value, '-');
        $unsignedValue = ltrim($value, '-');
        if ($unsignedValue === '') {
            return null;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');
        $decimalSeparator = null;

        if ($hasComma && $hasDot) {
            $decimalSeparator = strrpos($unsignedValue, ',') > strrpos($unsignedValue, '.') ? ',' : '.';
        } elseif ($hasComma) {
            $parts = explode(',', $unsignedValue);
            $lastPart = (string) end($parts);

            if (count($parts) === 2 && strlen($lastPart) > 0 && strlen($lastPart) <= 2) {
                $decimalSeparator = ',';
            }
        } elseif ($hasDot) {
            $parts = explode('.', $unsignedValue);
            $lastPart = (string) end($parts);

            if (count($parts) === 2 && strlen($lastPart) > 0 && strlen($lastPart) <= 2) {
                $decimalSeparator = '.';
            }
        }

        if ($decimalSeparator !== null) {
            [$intPart, $decimalPart] = explode($decimalSeparator, $unsignedValue, 2);
            $intPart = preg_replace('/[,.]/', '', $intPart);
            $decimalPart = preg_replace('/[,.]/', '', $decimalPart);
        } else {
            $intPart = preg_replace('/[,.]/', '', $unsignedValue);
            $decimalPart = '';
        }

        $intPart = preg_replace('/\D/', '', (string) $intPart);
        $decimalPart = preg_replace('/\D/', '', (string) $decimalPart);

        if ($intPart === '' && $decimalPart === '') {
            return null;
        }

        if ($intPart === '') {
            $intPart = '0';
        }

        if ($decimalPart === '') {
            $decimalPart = '00';
        } elseif (strlen($decimalPart) === 1) {
            $decimalPart .= '0';
        } elseif (strlen($decimalPart) > 2) {
            return number_format((float) (($negative ? '-' : '') . $intPart . '.' . $decimalPart), 2, '.', '');
        }

        $normalizedIntPart = ltrim($intPart, '0');
        if ($normalizedIntPart === '') {
            $normalizedIntPart = '0';
        }

        return ($negative ? '-' : '') . $normalizedIntPart . '.' . $decimalPart;
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

    private function buildInsertRowFromPolarsRecord(array $rowByColumn, array $selectedColumns): ?array
    {
        $insertRow = [
            'uniqueid_namareport' => (string) Str::uuid() . self::UNIQUE_SUFFIX,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($selectedColumns as $index) {
            $column = self::TARGET_COLUMNS[$index] ?? null;
            if ($column === null || in_array($column, ['id', 'uniqueid_namareport'], true)) {
                continue;
            }

            $value = $rowByColumn[$column] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                }
            }

            $insertRow[$column] = $value;
        }

        return $this->hasMeaningfulImportData($insertRow, ['uniqueid_namareport', 'created_at', 'updated_at', 'periode'])
            ? $insertRow
            : null;
    }

    private function bulkLoadService(): MySqlBulkLoadService
    {
        return app(MySqlBulkLoadService::class);
    }

    private function excelImportJobService(): ExcelImportJobService
    {
        return app(ExcelImportJobService::class);
    }

    private function excelStagingService(): ExcelStagingService
    {
        return app(ExcelStagingService::class);
    }

    private function progressService(): ImportProgressService
    {
        return app(ImportProgressService::class);
    }

    private function executionService(): ImportExecutionService
    {
        return app(ImportExecutionService::class);
    }

    private function supportsNativeBulkLoad(): bool
    {
        return $this->bulkLoadService()->supportsNativeBulkLoad();
    }


    private function loadCsvIntoMysqlChunked(
        string $csvPath,
        string $tableName,
        array $columns,
        ?callable $onProgress = null,
        int $chunkLines = 8000,
        ?int $totalLines = null
    ): int {
        return $this->bulkLoadService()->loadCsvIntoMysqlChunked(
            $csvPath,
            $tableName,
            $columns,
            $onProgress,
            $chunkLines,
            $totalLines
        );
    }

    private function createBulkLoadTempCsvPath(int $jobId): string
    {
        return $this->bulkLoadService()->createBulkLoadTempCsvPath(
            storage_path(self::BULK_LOAD_TEMP_DIR),
            self::TABLE_NAME,
            $jobId
        );
    }

    private function createFilteredPolarsCsvStage(
        string $filteredCsvPath,
        array $selectedColumns,
        ?int $totalRows = null,
        ?callable $send = null
    ): array {
        $stagingPath = $this->createBulkLoadTempCsvPath((int) (microtime(true) * 1000));
        $outputHandle = fopen($stagingPath, 'w');
        if ($outputHandle === false) {
            throw new \RuntimeException('Gagal membuat file staging ' . self::REPORT_LABEL . '.');
        }

        $handle = fopen($filteredCsvPath, 'r');
        if ($handle === false) {
            fclose($outputHandle);
            throw new \RuntimeException('Gagal membuka file hasil filter Polars ' . self::REPORT_LABEL . '.');
        }

        $headerRow = fgetcsv($handle, 0, self::COLUMN_DELIMITER);
        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), (array) $headerRow);
        $expectedColumns = count($headers);

        $loadColumns = ['uniqueid_namareport', 'created_at', 'updated_at'];
        foreach ($selectedColumns as $index) {
            $column = self::TARGET_COLUMNS[$index] ?? null;
            if ($column === null || in_array($column, ['id', 'uniqueid_namareport'], true)) {
                continue;
            }
            $loadColumns[] = $column;
        }
        $loadColumns = array_values(array_unique($loadColumns));

        $rowsDone = 0;
        $sourceRowsScanned = 0;
        $lastProgressAt = 0;
        $startTime = microtime(true);
        $timestamp = now()->toDateTimeString();

        try {
            while (($row = fgetcsv($handle, 0, self::COLUMN_DELIMITER)) !== false) {
                if (empty(array_filter((array) $row, fn ($value) => trim((string) $value) !== ''))) {
                    continue;
                }

                if (count($row) < $expectedColumns) {
                    $row = array_pad($row, $expectedColumns, '');
                } elseif (count($row) > $expectedColumns) {
                    $row = array_slice($row, 0, $expectedColumns);
                }

                $sourceRowsScanned++;
                $rowByColumn = [];
                foreach ($headers as $index => $column) {
                    $rowByColumn[$column] = $row[$index] ?? null;
                }

                $insertRow = $this->buildInsertRowFromPolarsRecord($rowByColumn, $selectedColumns);
                if ($insertRow === null) {
                    continue;
                }

                $insertRow['created_at'] = $timestamp;
                $insertRow['updated_at'] = $timestamp;

                $stagedRow = [];
                foreach ($loadColumns as $column) {
                    $value = $insertRow[$column] ?? null;
                    $stagedRow[] = $this->encodeBulkStageValue($value);
                }
                fputcsv($outputHandle, $stagedRow, self::BULK_STAGE_DELIMITER, '"', '\\');
                $rowsDone++;

                if ($send !== null && ($sourceRowsScanned - $lastProgressAt) >= 500) {
                    $lastProgressAt = $sourceRowsScanned;
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) ($sourceRowsScanned / $elapsed);
                    $percent = $totalRows !== null && $totalRows > 0
                        ? min(92, 10 + (int) (($sourceRowsScanned / max($totalRows, 1)) * 82))
                        : 92;

                    $send('progress', [
                        'percent' => $percent,
                        'message' => "Menyusun CSV staging " . self::REPORT_LABEL . " dari hasil filter Polars... ({$speed} baris sumber/detik)",
                        'rows_done' => $sourceRowsScanned,
                        'total' => $totalRows ?? $sourceRowsScanned,
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
        ];
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
            while (($data = fgetcsv($handle, 0, self::BULK_STAGE_DELIMITER)) !== false) {
                if (empty($data)) {
                    continue;
                }

                $row = [];
                foreach ($columns as $index => $column) {
                    $value = $data[$index] ?? null;
                    $row[$column] = $this->decodeBulkStageValue($value);
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

    private function insertStagedCsvInBatchesWithProgress(string $csvPath, array $columns, int $totalRows, callable $send): array
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file staging CSV untuk fallback insert.');
        }

        $rows = [];
        $totalSuccess = 0;
        $totalFailed = 0;
        $lastError = '';
        $processedRows = 0;
        $startTime = microtime(true);

        try {
            while (($data = fgetcsv($handle, 0, self::BULK_STAGE_DELIMITER)) !== false) {
                if (empty($data)) {
                    continue;
                }

                $row = [];
                foreach ($columns as $index => $column) {
                    $value = $data[$index] ?? null;
                    $row[$column] = $this->decodeBulkStageValue($value);
                }

                $rows[] = $row;
                if (count($rows) >= self::INSERT_BATCH_SIZE) {
                    $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastError);
                    $rows = [];
                    $processedRows = $totalSuccess + $totalFailed;
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) round($processedRows / $elapsed);
                    $percent = $totalRows > 0 ? min(99, 97 + (int) floor(($processedRows / max($totalRows, 1)) * 2)) : 99;
                    $send('progress', [
                        'percent' => $percent,
                        'message' => "Fallback insert " . self::REPORT_LABEL . "... ({$speed} baris/detik)",
                        'rows_done' => $processedRows,
                        'total' => $totalRows,
                        'speed' => $speed,
                    ]);
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

    private function encodeBulkStageValue(mixed $value): string
    {
        if ($value === null) {
            return '\N';
        }

        return str_replace('\\', '\\\\', (string) $value);
    }

    private function decodeBulkStageValue(mixed $value): mixed
    {
        if ($value === '\N') {
            return null;
        }

        if (!is_string($value)) {
            return $value;
        }

        return str_replace('\\\\', '\\', $value);
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

    private function cleanupUploadedFile(string $relativePath, array $extraPaths = []): void
    {
        try {
            Storage::delete($relativePath);
            $stageState = $this->getStagedExcelState($relativePath);
            $stagedCsvPath = (string) ($stageState['staged_csv_path'] ?? '');
            if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
                @unlink($stagedCsvPath);
            }
            foreach ($extraPaths as $extraPath) {
                $extraPath = (string) $extraPath;
                if ($extraPath !== '' && file_exists($extraPath)) {
                    @unlink($extraPath);
                }
            }
            $this->clearStagedExcelState($relativePath);
        } catch (\Throwable $e) {
            Log::warning('Gagal menghapus file sementara ' . self::REPORT_LABEL . ': ' . $e->getMessage());
        }
    }

    private function cleanupSuccessfulImportArtifacts(int $jobId, string $relativePath, ?string $periodHint = null, array $extraPaths = []): void
    {
        try {
            app(\App\Services\Import\ImportCleanupService::class)->dispatchImportedJobSync($jobId, self::TABLE_NAME, $periodHint, static::class);
            app(ImportCleanupController::class)->cleanupSuccessfulJobArtifacts(
                $jobId,
                array_values(array_filter(array_merge([$relativePath], $extraPaths)))
            );
            $this->clearStagedExcelState($relativePath);
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
