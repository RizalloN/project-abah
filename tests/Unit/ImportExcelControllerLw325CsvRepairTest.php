<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerLw325CsvRepairTest extends TestCase
{
    public function test_lw325_serialized_csv_rows_keep_grouped_numeric_values_intact(): void
    {
        $controller = new class extends ImportExcelController {
            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return 'lw325_ph';
            }

            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'periode',
                    'acctno',
                    'saldo_pertama_ph_pokok',
                    'saldo_pertama_ph_bunga',
                    'besar_realisasi',
                    'plafon',
                    'pokok',
                    'bunga',
                    'angpok',
                    'angbung',
                    'sisapok',
                    'sisabun',
                    'saldo_pertama_kali_charge_off',
                    'clmamt1',
                    'clmapr1',
                    'os_penuh_berjalan1',
                    'jumlah_pn',
                    'jumlah_pn_all',
                    'created_at',
                    'updated_at',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $headerLine = <<<'CSV'
Textbox3,PERIODE,ACCTNO,KANWIL,KANCA,UNIT,NAMA_DEBITUR,CIF1,FKSEGMEN,SEGMEN_DASHBOARD,DESCRIPTION,PRODUK_DASHBOARD,TGL_PH,TGL_REALISASI,CURTYP,SALDO_PERTAMA_PH_POKOK,SALDO_PERTAMA_PH_BUNGA,BESAR_REALISASI,PLAFON,JW,AT,CIF,POKOK,BUNGA,ANGPOK,ANGBUNG,SISAPOK,SISABUN,CLMAMT1,CLMAPR1,OS_PENUH_BERJALAN1,KECAMATAN_T_TINGGAL,KELURAHAN_T_TINGGAL,KODEPOS_T_TINGGAL,KECAMATAN_T_USAHA,KELURAHAN_T_USAHA,KODEPOS_T_USAHA,PN_PENGELOLA,PN_PEMRAKARSA,PN_REFERRAL,PN_RESTRUK,PN_PENGELOLA2,PN_PEMUTUS,PN_CRM,PN_CRR1,PN_REFERRAL_NAIK_KELAS,JUMLAH_PN,JUMLAH_PN_ALL,SALDO_PERTAMA_KALI_CHARGE_OFF,DEFFERED_BUNGA,SAI_DEFFERED,SAI_TUNGGAKAN,DEFFERED_BUNGA_PH,SAI_TUNGGAKAN_PH,SAI_DEFFERED_PH,WCBAL,WACCINT,WADVPMT,WPENINT,WMISC,WOTHCHG,WPMTAMT,WPSTDT,WPSTDT6,WAMOUNT,FLAG_KLAIM,CLMAMT,CLMAPR
CSV;

        $rawLine = <<<'CSV'
"1,4/4/2026 12:00:00 AM,814601007586100,KANWIL MALANG                           ,KC Ponorogo                   ,UNIT PASAR CONDONG PONOROGO   ,SUMIHAR PANJAITAN   ,SIWZ507,11100,Micro,Kupedes,Kupedes,16/10/2025,11/09/2024,IDR ,""7,712,540.00"",""1,979,902.25"",""7,000,000.00"",7000000.00,6,6,SIWZ507,""219,000.00"",""1,267,362.25"",""7,493,540.00"",""712,540.00"",""219,000.00"",""1,267,362.25"",,0.00,219000.00,JAMBON PONOROGO,Rt.001/001,63456,JAMBON PONOROGO,Rt.002/001,63456,00335794 - Indah Wahyu Wulandari,00335794 - Indah Wahyu Wulandari,,,,00023275 - Moch.Thoriq Aziz Putera,,,,1,3,""15,206,080"",0.00,0.00,0.00,0.00,,0.00,,,,,,,,,,,N,,0.00"
CSV;

        $headers = str_getcsv($headerLine);

        $normalizeMethod = new ReflectionMethod(ImportExcelController::class, 'normalizeCsvRow');
        $normalizeMethod->setAccessible(true);
        $parsedRow = $normalizeMethod->invoke($controller, [$rawLine], ',', count($headers));

        $this->assertCount(count($headers), $parsedRow);

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'lw325_ph', $headers);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $mappedRow = $mapMethod->invoke($controller, $parsedRow, $headers, $context, '2026-04-19 08:00:00');

        $this->assertIsArray($mappedRow);
        $this->assertSame('2026-04-04', $mappedRow['periode']);
        $this->assertSame('814601007586100', $mappedRow['acctno']);
        $this->assertSame('7712540.00', $mappedRow['saldo_pertama_ph_pokok']);
        $this->assertSame('1979902.25', $mappedRow['saldo_pertama_ph_bunga']);
        $this->assertSame('7000000.00', $mappedRow['besar_realisasi']);
        $this->assertSame('7000000.00', $mappedRow['plafon']);
        $this->assertSame('219000.00', $mappedRow['pokok']);
        $this->assertSame('1267362.25', $mappedRow['bunga']);
        $this->assertSame('7493540.00', $mappedRow['angpok']);
        $this->assertSame('712540.00', $mappedRow['angbung']);
        $this->assertSame('219000.00', $mappedRow['sisapok']);
        $this->assertSame('1267362.25', $mappedRow['sisabun']);
        $this->assertSame('15206080.00', $mappedRow['saldo_pertama_kali_charge_off']);
        $this->assertSame('1', $mappedRow['jumlah_pn']);
        $this->assertSame('3', $mappedRow['jumlah_pn_all']);
    }

    public function test_prepare_csv_preview_payload_counts_serialized_lw325_rows_from_raw_file(): void
    {
        $controller = new class extends ImportExcelController {
            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return 'lw325_ph';
            }
        };

        $headerLine = <<<'CSV'
Textbox3,PERIODE,ACCTNO,KANWIL,KANCA,UNIT,NAMA_DEBITUR,CIF1,FKSEGMEN,SEGMEN_DASHBOARD,DESCRIPTION,PRODUK_DASHBOARD,TGL_PH,TGL_REALISASI,CURTYP,SALDO_PERTAMA_PH_POKOK,SALDO_PERTAMA_PH_BUNGA,BESAR_REALISASI,PLAFON,JW,AT,CIF,POKOK,BUNGA,ANGPOK,ANGBUNG,SISAPOK,SISABUN,CLMAMT1,CLMAPR1,OS_PENUH_BERJALAN1,KECAMATAN_T_TINGGAL,KELURAHAN_T_TINGGAL,KODEPOS_T_TINGGAL,KECAMATAN_T_USAHA,KELURAHAN_T_USAHA,KODEPOS_T_USAHA,PN_PENGELOLA,PN_PEMRAKARSA,PN_REFERRAL,PN_RESTRUK,PN_PENGELOLA2,PN_PEMUTUS,PN_CRM,PN_CRR1,PN_REFERRAL_NAIK_KELAS,JUMLAH_PN,JUMLAH_PN_ALL,SALDO_PERTAMA_KALI_CHARGE_OFF,DEFFERED_BUNGA,SAI_DEFFERED,SAI_TUNGGAKAN,DEFFERED_BUNGA_PH,SAI_TUNGGAKAN_PH,SAI_DEFFERED_PH,WCBAL,WACCINT,WADVPMT,WPENINT,WMISC,WOTHCHG,WPMTAMT,WPSTDT,WPSTDT6,WAMOUNT,FLAG_KLAIM,CLMAMT,CLMAPR
CSV;

        $rawLineOne = <<<'CSV'
"1,4/30/2025 12:00:00 AM,388901017942105,KANWIL MALANG                           ,KC Madiun                               ,UNIT WUNGU MADIUN                       ,SUBANI              ,SDWZX24,11400,Micro,KUR Mikro Baru,KUR-Mikro,31/05/2022,21/01/2021,IDR ,""6,240,821.00"",""153,265.85"",""6,000,000.00"",6000000.00,24,1,SDWZX24,""4,089,173.00"",""-86,856.15"",""2,151,648.00"",""240,122.00"",""4,089,173.00"",""-86,856.15"",,,,WUNGU MADIUN,KEC WUNGU KAB MADIUN,63181,WUNGU MADIUN,KEC WUNGU KAB MADIUN,63181,00240149 - Reza Aditya Irdiansyah,00052524 - Cahyo Yudhi Kardono,,,,00022009 - Satun,,,,1,3,""8,392,469"",0.00,0.00,0.00,,,,,,,,,,,,,,,,";
CSV;

        $rawLineTwo = <<<'CSV'
"2601,4/30/2025 12:00:00 AM,811401005715104,KANWIL MALANG                           ,KC Ponorogo                             ,UNIT PASAR NGUMPUL PONOROGO             ,BUDI SANTOSO        ,BN22649,11100,Micro,Kupedes,Kupedes,31/01/2025,21/02/2023,IDR ,""63,115,198.00"",""6,479,486.12"",""50,000,000.00"",50000000.00,60,1,BN22649,""36,804,198.00"",""-6,629,711.88"",""26,311,000.00"",""13,109,198.00"",""36,804,198.00"",""-6,629,711.88"",,,,BALONG PONOROGO,KARANGPATIHAN BALO,63461,BALONG PONOROGO,KARANGPATIHAN BAL";"ONG,63461,00057187 - Wakhid Akhmadin Ariza,00235731 - Ekky Lukmana Putri,,,00235731 - Ekky Lukmana Putri,00022655 - Anang Cahyo Basuki,,,,2,4,""89,426,198"",0.00,0.00,0.00,,,,,,,,,,,,,,,,"
CSV;

        $tempPath = storage_path('app/testing/lw325_preview_payload_' . uniqid() . '.csv');
        if (!is_dir(dirname($tempPath))) {
            @mkdir(dirname($tempPath), 0777, true);
        }

        file_put_contents($tempPath, implode("\n", [
            $headerLine,
            $rawLineOne,
            $rawLineTwo,
        ]) . "\n");

        try {
            $previewMethod = new ReflectionMethod(ImportExcelController::class, 'prepareCsvPreviewPayload');
            $previewMethod->setAccessible(true);
            $payload = $previewMethod->invoke($controller, $tempPath);

            $this->assertSame(2, $payload['total_rows']);
            $this->assertSame(0, $payload['header_index']);
            $this->assertCount(2, $payload['preview']);
            $this->assertSame('2025-04-30', $payload['preview'][0]['PERIODE']);
            $this->assertSame('2025-04-30', $payload['preview'][1]['PERIODE']);
            $this->assertSame('388901017942105', $payload['preview'][0]['ACCTNO']);
            $this->assertSame('811401005715104', $payload['preview'][1]['ACCTNO']);
        } finally {
            @unlink($tempPath);
        }
    }
}
