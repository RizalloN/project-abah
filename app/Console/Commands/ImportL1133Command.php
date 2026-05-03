<?php

namespace App\Console\Commands;

use App\Services\Import\L1133CsvImporter;
use Illuminate\Console\Command;

class ImportL1133Command extends Command
{
    protected $signature = 'import:l1133
        {path : Path file CSV L1133}
        {--commit : Simpan hasil import ke database}
        {--no-replace : Jangan hapus data lama pada periode yang sama sebelum import}';

    protected $description = 'Parse dan import report L1133 Laporan Harian Pinjaman Kanwil.';

    public function handle(L1133CsvImporter $importer): int
    {
        $path = (string) $this->argument('path');
        $result = $importer->parse($path);
        $rows = $result['rows'];
        $metadata = $result['metadata'];

        $this->components->info('CSV L1133 terbaca.');
        $this->table(
            ['periode', 'kode_kanwil', 'nama_kanwil', 'rows'],
            [[
                $metadata['periode'] ?? '-',
                $metadata['kode_kanwil'] ?? '-',
                $metadata['nama_kanwil'] ?? '-',
                count($rows),
            ]]
        );

        if (!empty($result['warnings'])) {
            $this->components->warn('Peringatan parsing:');
            foreach ($result['warnings'] as $warning) {
                $this->line('- ' . $warning);
            }
        }

        $sample = array_slice($rows, 0, 5);
        if ($sample !== []) {
            $this->components->info('Sample hasil mapping:');
            $this->table(
                ['kanca', 'uker', 'jenis', 'jumlah_debitur', 'jumlah_rekening', 'outstanding', 'debitur_npl', 'npl', 'debitur_dpk', 'dpk'],
                array_map(static fn (array $row): array => [
                    trim(($row['kode_kanca'] ?? '') . ' ' . ($row['nama_kanca'] ?? '')),
                    trim(($row['kode_uker'] ?? '') . ' ' . ($row['nama_uker'] ?? '')),
                    $row['jenis'],
                    $row['jumlah_debitur'],
                    $row['jumlah_rekening'],
                    $row['outstanding'],
                    $row['jumlah_debitur_npl'],
                    $row['npl'],
                    $row['jumlah_debitur_dpk'],
                    $row['dpk'],
                ], $sample)
            );
        }

        if (!$this->option('commit')) {
            $this->components->info('Simulasi selesai. Tambahkan --commit untuk menyimpan ke database.');

            return self::SUCCESS;
        }

        $summary = $importer->importRows($rows, ! $this->option('no-replace'));
        $this->components->info("Import selesai. Insert/upsert: {$summary['inserted']}, deleted periode lama: {$summary['deleted']}.");

        return self::SUCCESS;
    }
}
