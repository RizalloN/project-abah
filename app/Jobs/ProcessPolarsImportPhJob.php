<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ProcessPolarsImportPhJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const CACHE_KEY_PREFIX = 'polars_ph_processing_';
    private const PYTHON_TIMEOUT = 0; // 0 = unlimited
    private const BULK_STAGE_DELIMITER = "\x1C";

    public int $timeout = 0;
    public int $tries = 1;

    public function __construct(
        public int $jobId,
        public string $sourcePath,
        public array $activeFilters = [],
        public array $selectedColumns = [],
        public string $delimiter = ',',
        public array $extraConfig = [],
    ) {
        $this->queue = 'imports-high';
    }

    public function handle(): void
    {
        Log::info('ProcessPolarsImportPhJob started', [
            'job_id' => $this->jobId,
            'source' => basename($this->sourcePath),
        ]);

        try {
            $cacheKey = $this->getCacheKey();
            
            // Check if already processed
            if (Cache::has($cacheKey)) {
                Log::info('Polars processing result cached', ['job_id' => $this->jobId]);
                return;
            }

            // Validate source
            if (!file_exists($this->sourcePath)) {
                throw new \RuntimeException('Source file not found: ' . $this->sourcePath);
            }

            // Run Polars processing
            $result = $this->runPolarsProcessor();

            if ($result === null) {
                throw new \RuntimeException('Polars processing returned null result');
            }

            // Cache result for 24 hours
            Cache::put($cacheKey, $result, Carbon::now()->addHours(24));

            Log::info('ProcessPolarsImportPhJob completed', [
                'job_id' => $this->jobId,
                'written_rows' => $result['written_rows'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessPolarsImportPhJob failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Run Polars processor with optimized settings
     */
    private function runPolarsProcessor(): ?array
    {
        $pythonExe = $this->findPython();
        // OPTIMIZATION: Use optimized v3 script if available, fallback to v2
        $scriptPath = base_path('scripts/lw325_ph_polars_processor_v3.py');
        if (!file_exists($scriptPath)) {
            $scriptPath = base_path('scripts/lw325_ph_polars_processor.py');
        }

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $outputCsvPath = (string) ($this->extraConfig['output_csv_path'] ?? '');
        if ($outputCsvPath === '') {
            $outputCsvPath = $this->createBulkLoadTempCsvPath();
        }

        $configFile = storage_path('app/report_ph_polars_' . Str::random(16) . '.json');
        
        $config = array_merge([
            'file_path' => $this->sourcePath,
            'delimiter' => $this->delimiter ?: $this->smartDetectCsvDelimiter($this->sourcePath),
            'output_csv_path' => $outputCsvPath,
            'active_filters' => $this->normalizeActiveFiltersForPolars($this->activeFilters),
        ], $this->extraConfig);

        file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE));

        $cmd = escapeshellarg($pythonExe)
            . ' ' . escapeshellarg($scriptPath)
            . ' --config ' . escapeshellarg($configFile)
            . ' --mode ' . escapeshellcmd($this->extraConfig['output_mode'] ?? 'stage');

        try {
            $process = Process::fromShellCommandline($cmd);
            $process->setTimeout(self::PYTHON_TIMEOUT);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('Polars processor error', [
                    'stderr' => $process->getErrorOutput(),
                    'stdout' => $process->getOutput(),
                ]);
                return null;
            }

            $output = $process->getOutput();
            $lines = explode("\n", trim($output));
            $donePayload = null;

            foreach (array_reverse($lines) as $line) {
                if ($line === '') {
                    continue;
                }
                $data = json_decode($line, true);
                if (is_array($data) && ($data['type'] ?? '') === 'done') {
                    $donePayload = $data;
                    break;
                }
            }

            if (!$donePayload || !file_exists($outputCsvPath)) {
                @unlink($outputCsvPath);
                return null;
            }

            return [
                'path' => $outputCsvPath,
                'cleanup' => true,
                'written_rows' => (int) ($donePayload['written_rows'] ?? 0),
                'periods' => array_values((array) ($donePayload['dates'] ?? [])),
                'load_columns' => array_values((array) ($this->extraConfig['load_columns'] ?? [])),
                'backend' => 'polars',
            ];
        } finally {
            @unlink($configFile);
        }
    }

    private function findPython(): ?string
    {
        $candidates = [
            'python3',
            'python',
            'C:\\Python311\\python.exe',
            'C:\\Python310\\python.exe',
        ];

        foreach ($candidates as $cmd) {
            $process = Process::fromShellCommandline(escapeshellarg($cmd) . ' --version');
            $process->setTimeout(5);
            try {
                $process->run();
                if ($process->isSuccessful()) {
                    return $cmd;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    private function smartDetectCsvDelimiter(string $path): string
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx') {
            return ',';
        }

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return ',';
        }

        try {
            $samples = [];
            for ($i = 0; $i < 10; $i++) {
                $line = fgets($handle, 4096);
                if ($line === false) {
                    break;
                }
                $samples[] = $line;
            }

            if (empty($samples)) {
                return ',';
            }

            $delimiters = [',', ';', "\t", '|'];
            $scores = [];

            foreach ($delimiters as $delim) {
                $counts = [];
                foreach ($samples as $sample) {
                    $count = count(str_getcsv($sample, $delim));
                    $counts[] = $count;
                }
                $variance = max($counts) - min($counts);
                $scores[$delim] = max($counts) * 100 - $variance;
            }

            return array_key_first(array_slice($scores, 0, 1, true)) ?? ',';
        } finally {
            fclose($handle);
        }
    }

    private function normalizeActiveFiltersForPolars(array $filters): array
    {
        if (empty($filters)) {
            return [];
        }

        // Map column indexes to actual column names (same as ImportReportPhController)
        $targetColumns = [
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

        $normalized = [];
        foreach ($filters as $columnIndex => $allowedValues) {
            // Support both numeric index and direct column name
            if (is_numeric($columnIndex)) {
                $column = $targetColumns[(int) $columnIndex] ?? null;
            } else {
                $column = $columnIndex;
            }

            if ($column === null) {
                continue;
            }

            if (!is_array($allowedValues)) {
                continue;
            }

            $values = [];
            foreach ($allowedValues as $value) {
                $normalizedValue = trim((string) $value);
                if ($normalizedValue === '') {
                    continue;
                }
                $values[$normalizedValue] = true;
            }

            if (!empty($values)) {
                $normalized[$column] = array_keys($values);
            }
        }

        return $normalized;
    }

    private function createBulkLoadTempCsvPath(): string
    {
        return storage_path('app/polars_bulk_' . Str::random(12) . '.csv');
    }

    private function getCacheKey(): string
    {
        $fileHash = hash_file('md5', $this->sourcePath);
        $filterHash = md5(json_encode($this->activeFilters));
        return self::CACHE_KEY_PREFIX . $fileHash . '_' . $filterHash;
    }
}
