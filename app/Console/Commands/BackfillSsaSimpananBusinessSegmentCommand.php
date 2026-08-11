<?php

namespace App\Console\Commands;

use App\Services\SsaSimpananBusinessSegmentBackfillService;
use Illuminate\Console\Command;

class BackfillSsaSimpananBusinessSegmentCommand extends Command
{
    protected $signature = 'ssa-simpanan:backfill-business-segment
        {--reference=* : Dua atau lebih workbook referensi SSA Simpanan berurutan}
        {--apply : Terapkan hasil pemetaan ke database}';

    protected $description = 'Memetakan Segmen Kategorisasi Bisnis SSA Simpanan secara kronologis';

    public function handle(SsaSimpananBusinessSegmentBackfillService $service): int
    {
        $references = array_values(array_filter(array_map(
            static fn ($path): string => trim((string) $path),
            (array) $this->option('reference')
        )));

        try {
            $result = $service->run($references, (bool) $this->option('apply'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metrik', 'Hasil'],
            collect($result)->map(static function ($value, $key): array {
                return [
                    (string) $key,
                    is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value,
                ];
            })->values()->all()
        );
        $this->info($this->option('apply') ? 'Backfill selesai diterapkan.' : 'Dry-run selesai; database belum diubah.');

        return self::SUCCESS;
    }
}
