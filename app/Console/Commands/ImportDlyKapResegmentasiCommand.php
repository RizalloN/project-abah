<?php

namespace App\Console\Commands;

use App\Services\Import\DlyKapResegmentasiCsvImporter;
use Illuminate\Console\Command;

class ImportDlyKapResegmentasiCommand extends Command
{
    protected $signature = 'import:dly-kap-resegmentasi
        {path : Path file CSV DLY KAP RESEGMENTASI}
        {--commit : Simpan hasil import ke database}
        {--no-replace : Jangan hapus data lama pada periode/kanwil/cabang/unit yang sama sebelum import}';

    protected $description = 'Parse dan import CSV DLY KAP RESEGMENTASI 2025 ke tabel migrasi.';

    public function handle(DlyKapResegmentasiCsvImporter $importer): int
    {
        $path = (string) $this->argument('path');
        $result = $importer->parse($path);
        $rows = $result['rows'];
        $metadata = $result['metadata'];

        $this->components->info('DLY KAP RESEGMENTASI CSV terbaca.');
        $this->table(
            ['periode', 'kanwil', 'kode_cabang', 'kode_unit', 'rows'],
            [[
                $metadata['periode'] ?? '-',
                $metadata['kanwil'] ?? '-',
                $metadata['kode_cabang'] ?? '-',
                $metadata['kode_unit'] ?? '-',
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
                ['segmen', 'keterangan', 'L_rp', 'L_deb', 'DPK_rp', 'DPK_deb', 'TL_rp', 'TL_deb'],
                array_map(static fn (array $row): array => [
                    $row['segmen'],
                    $row['keterangan'],
                    $row['l_rp'],
                    $row['l_deb'],
                    $row['dpk_rp'],
                    $row['dpk_deb'],
                    $row['tl_rp'],
                    $row['tl_deb'],
                ], $sample)
            );
        }

        if (!$this->option('commit')) {
            $this->components->info('Simulasi selesai. Tambahkan --commit untuk menyimpan ke database.');

            return self::SUCCESS;
        }

        $summary = $importer->import($rows, ! $this->option('no-replace'));
        $this->components->info("Import selesai. Insert/upsert: {$summary['inserted']}, deleted scope lama: {$summary['deleted']}.");

        return self::SUCCESS;
    }
}
