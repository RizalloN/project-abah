<?php

namespace App\Console\Commands;

use App\Services\Reports\MicroPipelineSyncService;
use Illuminate\Console\Command;

final class SyncMicroPipelineCommand extends Command
{
    protected $signature = 'micro-pipeline:sync {--dataset=all : all, prewash, atau slik_hijau} {--source=} {--path=} {--force}';

    protected $description = 'Sinkronkan workbook Pipeline Mikro ke database landing page';

    public function handle(MicroPipelineSyncService $service): int
    {
        $dataset = strtolower(trim((string) $this->option('dataset'))) ?: 'all';
        $path = trim((string) $this->option('path'));
        if ($path !== '' && ! str_starts_with($path, DIRECTORY_SEPARATOR) && preg_match('/^[A-Za-z]:[\\\\\/]/', $path) !== 1) {
            $path = base_path($path);
        }

        $source = trim((string) $this->option('source')) ?: null;
        if ($dataset === 'all' && ($source !== null || $path !== '')) {
            $this->error('Opsi --source atau --path harus disertai --dataset=prewash atau --dataset=slik_hijau.');

            return self::FAILURE;
        }

        if ($dataset === 'all') {
            $results = $service->syncAll((bool) $this->option('force'));
            foreach ($results as $result) {
                $this->renderResult($result);
            }

            return self::SUCCESS;
        }

        try {
            $result = $service->sync($source, $path !== '' ? $path : null, (bool) $this->option('force'), $dataset);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->renderResult($result);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $result */
    private function renderResult(array $result): void
    {

        $this->info((string) ($result['message'] ?? 'Sinkronisasi selesai.'));
        $this->table(['Metrik', 'Nilai'], [
            ['Baris sumber', number_format((int) ($result['source_rows'] ?? 0), 0, ',', '.')],
            ['Masuk Area 6', number_format((int) ($result['imported_rows'] ?? 0), 0, ',', '.')],
            ['Di luar Area 6', number_format((int) ($result['outside_scope_rows'] ?? 0), 0, ',', '.')],
            ['Sudah kunjungan', number_format((int) data_get($result, 'statuses.done', 0), 0, ',', '.')],
            ['Terjadwal', number_format((int) data_get($result, 'statuses.scheduled', 0), 0, ',', '.')],
            ['Belum kunjungan', number_format((int) data_get($result, 'statuses.pending', 0), 0, ',', '.')],
        ]);

    }
}
