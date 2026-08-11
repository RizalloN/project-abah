<?php

namespace Tests\Unit;

use Tests\TestCase;

class SsaPolarsSourceContractTest extends TestCase
{
    public function test_ssa_simpanan_processor_writes_current_columns_in_target_order(): void
    {
        $headers = [
            'Saldo', 'Segmen Kategorisasi Bisnis', 'Nama Uker', 'Produk',
            'Month, Day, Year of Posisi', 'Segmentasi', 'Nama Cabang',
        ];
        $row = [
            '1250000.25', 'Consumer', '00045 -- KC Madiun', 'Tabungan',
            '10 Agustus 2026', 'Ritel', '00045 -- KC Madiun(Konsolidasi-MB)',
        ];

        $result = $this->runProcessor('ssa_simpanan_polars_processor.py', $headers, $row);

        $this->assertSame(0, $result['exit_code'], implode(PHP_EOL, $result['events']));
        $this->assertSame([
            'month_day_year_of_posisi', 'nama_cabang', 'nama_uker', 'produk',
            'segmentasi', 'segmen_kategorisasi_bisnis', 'saldo',
        ], $result['csv'][0]);
        $this->assertSame('Consumer', $result['csv'][1][5]);
    }

    public function test_ssa_simpanan_processor_rejects_source_without_business_category(): void
    {
        $result = $this->runProcessor('ssa_simpanan_polars_processor.py', [
            'Month, Day, Year of Posisi', 'Nama Cabang', 'Nama Uker',
            'Produk', 'Segmentasi', 'Saldo',
        ], [
            '10 Agustus 2026', 'KC Madiun', 'KC Madiun', 'Tabungan', 'Ritel', '1000',
        ]);

        $this->assertNotSame(0, $result['exit_code']);
        $this->assertStringContainsString('segmen_kategorisasi_bisnis', implode(PHP_EOL, $result['events']));
    }

    public function test_ssa_pinjaman_processor_writes_all_current_columns_in_target_order(): void
    {
        $headers = [
            'Baki Debet', 'Nama Uker', 'Produk_Dashboard', 'Segmen Lama',
            'Jumlah Rekening Aktif', 'Month, Day, Year of Periode', 'Produk',
            'Nama Cabang', 'Segmen', 'SEGMEN_2025', 'Segmen_Dashboard',
            'Kolektabilitas One Obligor', 'Flag Restruk', 'Jumlah Debitur Aktif',
        ];
        $row = [
            '2500000', '00045 -- KC Madiun', 'Kupedes', 'Mikro', '1',
            '10 Agustus 2026', 'KUPEDES RAKYAT', 'KC Madiun', 'Mikro',
            'Mikro', 'Mikro', '1', 'N', '1',
        ];

        $result = $this->runProcessor('ssa_pinjaman_polars_processor.py', $headers, $row);

        $this->assertSame(0, $result['exit_code'], implode(PHP_EOL, $result['events']));
        $this->assertSame([
            'month_day_year_of_periode', 'nama_cabang', 'nama_uker', 'produk',
            'produk_dashboard', 'segmen', 'segmen_lama', 'segmen_2025',
            'segmen_dashboard', 'kolektabilitas_one_obligor', 'flag_restruk',
            'baki_debet', 'jumlah_debitur_aktif', 'jumlah_rekening_aktif',
        ], $result['csv'][0]);
        $this->assertSame('2500000.00', $result['csv'][1][11]);
    }

    /**
     * @return array{exit_code: int, events: array<int, string>, csv: array<int, array<int, string|null>>}
     */
    private function runProcessor(string $script, array $headers, array $row): array
    {
        $source = tempnam(sys_get_temp_dir(), 'ssa_source_');
        $output = tempnam(sys_get_temp_dir(), 'ssa_output_');
        $config = tempnam(sys_get_temp_dir(), 'ssa_config_');
        $handle = fopen($source, 'wb');
        fputcsv($handle, $headers);
        fputcsv($handle, $row);
        fclose($handle);
        file_put_contents($config, json_encode([
            'file_path' => $source,
            'delimiter' => ',',
            'output_csv_path' => $output,
        ], JSON_THROW_ON_ERROR));

        $events = [];
        $exitCode = 0;
        exec(
            'python '.escapeshellarg(base_path('scripts/'.$script))
            .' --config '.escapeshellarg($config)
            .' --mode stage 2>&1',
            $events,
            $exitCode
        );

        $csv = [];
        if (is_file($output) && filesize($output) > 0) {
            $outputHandle = fopen($output, 'rb');
            while (($values = fgetcsv($outputHandle)) !== false) {
                $csv[] = $values;
            }
            fclose($outputHandle);
        }

        @unlink($source);
        @unlink($output);
        @unlink($config);

        return ['exit_code' => $exitCode, 'events' => $events, 'csv' => $csv];
    }
}
