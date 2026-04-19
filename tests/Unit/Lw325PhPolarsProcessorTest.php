<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class Lw325PhPolarsProcessorTest extends TestCase
{
    public function test_polars_preview_mode_normalizes_dates_and_decimals_for_lw325_sample_shape(): void
    {
        $inputPath = storage_path('app/testing_lw325_polars_input.csv');
        $outputPath = storage_path('app/testing_lw325_polars_preview_output.csv');
        $configPath = storage_path('app/testing_lw325_polars_preview_config.json');

        File::ensureDirectoryExists(dirname($inputPath));

        File::put($inputPath, implode("\n", [
            'Textbox3,PERIODE,ACCTNO,KANWIL,KANCA,UNIT,NAMA_DEBITUR,CIF1,FKSEGMEN,SEGMEN_DASHBOARD,DESCRIPTION,PRODUK_DASHBOARD,TGL_PH,TGL_REALISASI,CURTYP,SALDO_PERTAMA_PH_POKOK,SALDO_PERTAMA_PH_BUNGA,BESAR_REALISASI,PLAFON,JW,AT,CIF,POKOK,BUNGA,ANGPOK,ANGBUNG,SISAPOK,SISABUN,CLMAMT1,CLMAPR1,OS_PENUH_BERJALAN1,KECAMATAN_T_TINGGAL,KELURAHAN_T_TINGGAL,KODEPOS_T_TINGGAL,KECAMATAN_T_USAHA,KELURAHAN_T_USAHA,KODEPOS_T_USAHA,PN_PENGELOLA,PN_PEMRAKARSA,PN_REFERRAL,PN_RESTRUK,PN_PENGELOLA2,PN_PEMUTUS,PN_CRM,PN_CRR1,PN_REFERRAL_NAIK_KELAS,JUMLAH_PN,JUMLAH_PN_ALL,SALDO_PERTAMA_KALI_CHARGE_OFF,DEFFERED_BUNGA,SAI_DEFFERED,SAI_TUNGGAKAN,DEFFERED_BUNGA_PH,SAI_TUNGGAKAN_PH,SAI_DEFFERED_PH,WCBAL,WACCINT,WADVPMT,WPENINT,WMISC,WOTHCHG,WPMTAMT,WPSTDT,WPSTDT6,WAMOUNT,FLAG_KLAIM,CLMAMT,CLMAPR',
            '"1,4/4/2026 12:00:00 AM,814601007586100,KANWIL MALANG,KC Ponorogo,UNIT PASAR CONDONG PONOROGO,SUMIHAR PANJAITAN,SIWZ507,11100,Micro,Kupedes,Kupedes,16/10/2025,11/09/2024,IDR ,""7,712,540.00"",""1,979,902.25"",""7,000,000.00"",7000000.00,6,6,SIWZ507,""219,000.00"",""1,267,362.25"",""7,493,540.00"",""712,540.00"",""219,000.00"",""1,267,362.25"",,0.00,219000.00,JAMBON PONOROGO,Rt.001/001,63456,JAMBON PONOROGO,Rt.002/001,63456,00335794 - Indah Wahyu Wulandari,00335794 - Indah Wahyu Wulandari,,,,00023275 - Moch.Thoriq Aziz Putera,,,,1,3,""15,206,080"",0.00,0.00,0.00,0.00,,0.00,,,,,,,,,,,N,,0.00"',
            '"2,4/4/2026 12:00:00 AM,321401029603104,KANWIL MALANG,KC Ponorogo,UNIT PASAR CONDONG PONOROGO,KURNI JEMIRIN,KCR5885,11400,Micro,KUR Mikro Baru,KUR-Mikro,29/11/2025,19/10/2023,IDR ,""53,160,098.00"",""1,574,011.71"",""50,000,000.00"",50000000.00,36,1,KCR5885,""9,101,794.00"",""-1,582,100.29"",""44,058,304.00"",""3,156,112.00"",""9,101,794.00"",""-1,582,100.29"",21237519.00,21237519.00,30339313.00,KAUMAN PONOROGO KAB.,AUMAN,63451,KAUMAN PONOROGO,AUMAN,63451,00245631 - Siti Nurahfia,00120288 - Setiyo Heriyanto,,,00117677 - Yulian Kurniawan,00022655 - Anang Cahyo Basuki,,,,2,4,""97,218,402"",0.00,0.00,0.00,0.00,,0.00,,,,,,,,,,,Y,21237519.00,21237519.00"',
        ]));

        File::put($configPath, json_encode([
            'file_path' => $inputPath,
            'delimiter' => ',',
            'output_csv_path' => $outputPath,
            'output_mode' => 'preview',
            'active_filters' => [],
        ], JSON_UNESCAPED_UNICODE));

        exec('python ' . escapeshellarg(base_path('scripts/lw325_ph_polars_processor.py')) . ' --config ' . escapeshellarg($configPath) . ' --mode stage', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
        $this->assertFileExists($outputPath);

        $csv = File::get($outputPath);

        // Tanggal harus ternormalisasi ke YYYY-MM-DD (bukan raw format)
        $this->assertStringContainsString('2026-04-04', $csv);   // periode
        $this->assertStringContainsString('2025-10-16', $csv);   // tgl_ph 16/10/2025
        $this->assertStringNotContainsString('4/4/2026 12:00:00 AM', $csv);
        $this->assertStringNotContainsString('16/10/2025', $csv);

        // Desimal harus ternormalisasi (tanpa koma ribuan, tanpa kutip)
        $this->assertStringContainsString('1979902.25', $csv);
        $this->assertStringContainsString('-1582100.29', $csv);
        $this->assertStringContainsString('21237519.00', $csv);
        $this->assertStringNotContainsString('"1,979,902.25"', $csv);

        // Output harus punya header
        $this->assertStringContainsString('uniqueid_namareport', $csv);
        $this->assertStringContainsString('periode', $csv);

        // uniqueid_namareport harus berisi nilai (bukan null/kosong)
        $this->assertMatchesRegularExpression('/2026-04-04_\d+_\d+_RPH/', $csv);

        @unlink($inputPath);
        @unlink($outputPath);
        @unlink($configPath);
    }

    public function test_polars_bulk_load_mode_normalizes_values_for_database_without_changing_numeric_meaning(): void
    {
        $inputPath = storage_path('app/testing_lw325_polars_bulk_input.csv');
        $outputPath = storage_path('app/testing_lw325_polars_bulk_output.csv');
        $configPath = storage_path('app/testing_lw325_polars_bulk_config.json');

        File::ensureDirectoryExists(dirname($inputPath));

        File::put($inputPath, implode("\n", [
            'Textbox3,PERIODE,ACCTNO,KANWIL,KANCA,UNIT,NAMA_DEBITUR,CIF1,FKSEGMEN,SEGMEN_DASHBOARD,DESCRIPTION,PRODUK_DASHBOARD,TGL_PH,TGL_REALISASI,CURTYP,SALDO_PERTAMA_PH_POKOK,SALDO_PERTAMA_PH_BUNGA,BESAR_REALISASI,PLAFON,JW,AT,CIF,POKOK,BUNGA',
            '"1,4/4/2026 12:00:00 AM,814601007586100,KANWIL MALANG,KC Ponorogo,UNIT PASAR CONDONG PONOROGO,SUMIHAR PANJAITAN,SIWZ507,11100,Micro,Kupedes,Kupedes,16/10/2025,11/09/2024,IDR ,""7,712,540.00"",""1,979,902.25"",""7,000,000.00"",7000000.00,6,6,SIWZ507,""219,000.00"",""1,267,362.25"""',
        ]));

        File::put($configPath, json_encode([
            'file_path' => $inputPath,
            'delimiter' => ',',
            'output_csv_path' => $outputPath,
            'output_mode' => 'bulk_load',
            'active_filters' => [],
            'timestamp' => '2026-04-19 10:00:00',
            'load_columns' => [
                'uniqueid_namareport',
                'created_at',
                'updated_at',
                'periode',
                'acctno',
                'tgl_ph',
                'tgl_realisasi',
                'saldo_pertama_ph_pokok',
                'saldo_pertama_ph_bunga',
                'pokok',
                'bunga',
            ],
        ], JSON_UNESCAPED_UNICODE));

        exec('python ' . escapeshellarg(base_path('scripts/lw325_ph_polars_processor.py')) . ' --config ' . escapeshellarg($configPath) . ' --mode stage', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
        $this->assertFileExists($outputPath);

        $csv = File::get($outputPath);
        $this->assertStringContainsString('2026-04-04', $csv);
        $this->assertStringContainsString('2025-10-16', $csv);
        $this->assertStringContainsString('2024-09-11', $csv);
        $this->assertStringContainsString('7712540.00', $csv);
        $this->assertStringContainsString('1979902.25', $csv);
        $this->assertStringContainsString('219000.00', $csv);
        $this->assertStringContainsString('1267362.25', $csv);

        @unlink($inputPath);
        @unlink($outputPath);
        @unlink($configPath);
    }
}
