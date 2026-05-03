<?php

namespace App\Console\Commands;

use App\Services\Import\ReferensiUkerImporter;
use Illuminate\Console\Command;

class ImportReferensiUkerCommand extends Command
{
    protected $signature = 'import:referensi-uker
        {path : Path file Excel referensi uker}
        {--commit : Simpan hasil import ke database}
        {--no-replace : Jangan hapus data lama sebelum import}';

    protected $description = 'Parse dan import file REFERENSI.xlsx ke tabel referensi_uker.';

    public function handle(ReferensiUkerImporter $importer): int
    {
        $result = $importer->parse((string) $this->argument('path'));
        $rows = $result['rows'];
        $metadata = $result['metadata'];

        $this->components->info('File referensi uker terbaca.');
        $this->table(
            ['sheet', 'baris file', 'baris valid'],
            [[
                $metadata['sheet'] ?? '-',
                $metadata['highest_row'] ?? '-',
                count($rows),
            ]]
        );

        if (($result['warnings'] ?? []) !== []) {
            $this->components->warn('Peringatan parsing:');
            foreach ($result['warnings'] as $warning) {
                $this->line('- ' . $warning);
            }
        }

        $sample = array_slice($rows, 0, 5);
        if ($sample !== []) {
            $this->components->info('Sample mapping:');
            $this->table(
                ['kode_uker', 'nama_uker', 'keterangan', 'kode_cabang', 'nama_cabang'],
                array_map(static fn (array $row): array => [
                    $row['kode_uker'],
                    $row['nama_uker'],
                    $row['keterangan'],
                    $row['kode_cabang'],
                    $row['nama_cabang'],
                ], $sample)
            );
        }

        if (! $this->option('commit')) {
            $this->components->info('Simulasi selesai. Tambahkan --commit untuk menyimpan ke database.');

            return self::SUCCESS;
        }

        $summary = $importer->importRows($rows, ! $this->option('no-replace'));
        $mode = $summary['replaced'] ? 'replace penuh' : 'upsert tanpa delete';
        $this->components->info("Import selesai ({$mode}). Rows diproses: {$summary['inserted']}.");

        return self::SUCCESS;
    }
}
