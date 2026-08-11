<?php

namespace App\Console\Commands;

use App\Services\Rka\BreakdownRkaSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncRkaBreakdownCommand extends Command
{
    protected $signature = 'rka:sync-breakdown
        {files* : Empat path workbook RKA Madiun, Magetan, Ngawi, dan Ponorogo}
        {--year=2026 : Tahun RKA}
        {--apply : Terapkan penggantian data setelah seluruh validasi lolos}';

    protected $description = 'Validasi dan sinkronkan paket workbook breakdown RKA secara transaksional';

    public function handle(BreakdownRkaSyncService $service): int
    {
        try {
            $result = $service->sync(
                (array) $this->argument('files'),
                (int) $this->option('year'),
                (bool) $this->option('apply')
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Cabang', 'Baris', 'Unit', 'Mata Anggaran', 'Baris Semua Bulan Nol', 'SHA-256'],
            collect($result['branches'])->map(function (array $branch, string $name): array {
                return [
                    $name,
                    number_format((int) $branch['rows'], 0, ',', '.'),
                    number_format((int) $branch['distinct_units'], 0, ',', '.'),
                    number_format((int) $branch['distinct_mata_anggaran'], 0, ',', '.'),
                    number_format((int) $branch['all_months_zero_rows'], 0, ',', '.'),
                    $branch['sha256'],
                ];
            })->values()->all()
        );

        $this->line('Total sumber: '.number_format((int) $result['source_rows'], 0, ',', '.').' baris');
        $this->line('Data lama dalam scope: '.number_format((int) $result['existing_rows'], 0, ',', '.').' baris');
        $this->line('Hash sumber: '.$result['source_hash']);
        $this->line('Hash database saat ini: '.$result['database_hash_before']);

        if ($result['applied']) {
            $this->info('Sinkronisasi diterapkan dan tervalidasi.');
            $this->line('Hash database: '.$result['database_hash']);
            $this->line('Baris lama diganti: '.number_format((int) $result['replaced_rows'], 0, ',', '.'));
        } elseif (! $result['changes_detected']) {
            $this->info('Tidak ada perubahan data. Database dan cache tidak ditulis ulang.');
        } else {
            $this->warn('Perubahan terdeteksi. Database belum diubah; gunakan --apply setelah hasil diperiksa.');
        }

        if (! empty($result['audit_path'])) {
            $this->line('Audit: '.$result['audit_path']);
        }

        return self::SUCCESS;
    }
}
