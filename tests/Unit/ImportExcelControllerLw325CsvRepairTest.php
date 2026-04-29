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
            protected function resolveExcelTableName(): string
            {
                return 'lw325_ph';
            }

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
1,4/4/2026 12:00:00 AM,814601007586100,KANWIL MALANG                           ,KC Ponorogo                   ,UNIT PASAR CONDONG PONOROGO   ,SUMIHAR PANJAITAN   ,SIWZ507,11100,Micro,Kupedes,Kupedes,16/10/2025,11/09/2024,IDR ,"6,240,821.00","153,265.85","6,000,000.00",6000000.00,24,1,SDWZX24,"4,089,173.00","-86,856.15","2,151,648.00","240,122.00","4,089,173.00","-86,856.15",,,,WUNGU MADIUN,KEC WUNGU KAB MADIUN,63181,WUNGU MADIUN,KEC WUNGU KAB MADIUN,63181,00240149 - Reza Aditya Irdiansyah,00052524 - Cahyo Yudhi Kardono,,,,00022009 - Satun,,,,1,3,"8,392,469",0.00,0.00,0.00,,,,,,,,,,,,,,,,
CSV;

        $headers = str_getcsv($headerLine);

        $normalizeMethod = new ReflectionMethod(ImportExcelController::class, 'normalizeCsvRow');
        $normalizeMethod->setAccessible(true);
        $parsedRow = $normalizeMethod->invoke($controller, str_getcsv($rawLine), ',', count($headers));

        $this->assertCount(count($headers), $parsedRow);

        $headerMap = array_flip($headers);

        $this->assertSame('4/4/2026 12:00:00 AM', $parsedRow[$headerMap['PERIODE']]);
        $this->assertSame('814601007586100', $parsedRow[$headerMap['ACCTNO']]);
        $this->assertSame('6,240,821.00', $parsedRow[$headerMap['SALDO_PERTAMA_PH_POKOK']]);
        $this->assertSame('153,265.85', $parsedRow[$headerMap['SALDO_PERTAMA_PH_BUNGA']]);
        $this->assertSame('6,000,000.00', $parsedRow[$headerMap['BESAR_REALISASI']]);
        $this->assertSame('4,089,173.00', $parsedRow[$headerMap['POKOK']]);
        $this->assertSame('-86,856.15', $parsedRow[$headerMap['BUNGA']]);
        $this->assertSame('1', $parsedRow[$headerMap['JUMLAH_PN']]);
        $this->assertSame('3', $parsedRow[$headerMap['JUMLAH_PN_ALL']]);
    }

    public function test_prepare_csv_preview_payload_counts_serialized_lw325_rows_from_raw_file(): void
    {
        $controller = new class extends ImportExcelController {
            protected function resolveExcelTableName(): string
            {
                return 'lw325_ph';
            }

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
            $this->assertSame('4/30/2025 12:00:00 AM', $payload['preview'][0][1] ?? null);
            $this->assertSame('388901017942105', $payload['preview'][0][2] ?? null);
            $this->assertNotEmpty($payload['preview'][1] ?? []);
        } finally {
            @unlink($tempPath);
        }
    }

    public function test_lw325_preview_skips_report_metadata_before_header(): void
    {
        $controller = new class extends ImportExcelController {
            protected function resolveExcelTableName(): string
            {
                return 'lw325_ph';
            }

            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return 'lw325_ph';
            }
        };

        $headerLine = <<<'CSV'
Textbox3,PERIODE,ACCTNO,KANWIL,KANCA,UNIT,NAMA_DEBITUR,CIF1,FKSEGMEN,SEGMEN_DASHBOARD,DESCRIPTION,PRODUK_DASHBOARD,TGL_PH,TGL_REALISASI,CURTYP,SALDO_PERTAMA_PH_POKOK,SALDO_PERTAMA_PH_BUNGA,BESAR_REALISASI,PLAFON,JW,AT,CIF,POKOK,BUNGA,ANGPOK,ANGBUNG,SISAPOK,SISABUN,CLMAMT1,CLMAPR1,OS_PENUH_BERJALAN1,KECAMATAN_T_TINGGAL,KELURAHAN_T_TINGGAL,KODEPOS_T_TINGGAL,KECAMATAN_T_USAHA,KELURAHAN_T_USAHA,KODEPOS_T_USAHA,PN_PENGELOLA,PN_PEMRAKARSA,PN_REFERRAL,PN_RESTRUK,PN_PENGELOLA2,PN_PEMUTUS,PN_CRM,PN_CRR1,PN_REFERRAL_NAIK_KELAS,JUMLAH_PN,JUMLAH_PN_ALL,SALDO_PERTAMA_KALI_CHARGE_OFF,DEFFERED_BUNGA,SAI_DEFFERED,SAI_TUNGGAKAN,DEFFERED_BUNGA_PH,SAI_TUNGGAKAN_PH,SAI_DEFFERED_PH,WCBAL,WACCINT,WADVPMT,WPENINT,WMISC,WOTHCHG,WPMTAMT,WPSTDT,WPSTDT6,WAMOUNT,FLAG_KLAIM,CLMAMT,CLMAPR
CSV;

        $rawLine = <<<'CSV'
1,4/27/2026 12:00:00 AM,814601005128100,KANWIL MALANG,KC Ponorogo,UNIT PASAR CONDONG PONOROGO,AGUNG SISWANTO,ACKX989,11400,Micro,KUR Mikro Baru,KUR-Mikro,23/12/2025,11/11/2022,IDR ,"102,218,225.00","4,126,020.24","75,000,000.00",51290989.00,59,1,ACKX989,"51,090,489.00","-2,691,224.76","51,127,736.00","6,817,245.00","51,090,489.00","-2,691,224.76",,0.00,51090489.00,JAMBON PONOROGO,KEC JAMBON KAB PONOROGO,63456,KELAPA GADING JAKARTA UTARA,JAKARTA UTARA,14250,00245631 - Siti Nurahfia,00152264 - Yuanita Purbasari,,,,00023211 - Adin Darmawan,,,,1,3,"153,345,961",0.00,0.00,0.00,0.00,0.00,0.00,,,,,,,,,,,N,,0.00
CSV;

        $tempPath = storage_path('app/testing/lw325_metadata_preview_' . uniqid() . '.csv');
        if (!is_dir(dirname($tempPath))) {
            @mkdir(dirname($tempPath), 0777, true);
        }

        file_put_contents($tempPath, implode("\n", [
            'textbox1,Textbox38,Textbox45',
            'Laporan Nominatif PH,Periode Data : 27/04/2026,Date Printed : 29 Apr 2026 11:56:17 AM',
            '',
            $headerLine,
            $rawLine,
        ]) . "\n");

        try {
            $previewMethod = new ReflectionMethod(ImportExcelController::class, 'prepareCsvPreviewPayload');
            $previewMethod->setAccessible(true);
            $payload = $previewMethod->invoke($controller, $tempPath);

            $this->assertSame(3, $payload['header_index']);
            $this->assertSame(1, $payload['total_rows']);
            $this->assertSame('Textbox3', $payload['headers'][0] ?? null);
            $this->assertSame('1', $payload['preview'][0][0] ?? null);
            $this->assertSame('4/27/2026 12:00:00 AM', $payload['preview'][0][1] ?? null);
            $this->assertSame('814601005128100', $payload['preview'][0][2] ?? null);
        } finally {
            @unlink($tempPath);
        }
    }

    public function test_prepare_lw325_ph_direct_load_source_exposes_prepared_metadata(): void
    {
        $controller = new class extends ImportExcelController {
            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return 'lw325_ph';
            }

            protected function createNormalizedLw325PhDirectLoadCsv(string $csvPath, ?string $delimiter = null, ?callable $send = null): array
            {
                return [
                    'path' => $csvPath,
                    'cleanup' => false,
                    'normalized' => true,
                    'backend' => 'polars',
                    'skipped_rows' => [],
                    'skipped_count' => 0,
                    'duplicate_count' => 0,
                    'written_rows' => 1,
                    'total_rows' => 1,
                    'periods' => ['2026-04-04'],
                    'period_hints' => ['2026-04-04'],
                    'headers' => ['uniqueid_namareport', 'periode', 'acctno'],
                ];
            }
        };

        $tempPath = storage_path('app/testing/lw325_prepared_source_stub.csv');
        if (!is_dir(dirname($tempPath))) {
            @mkdir(dirname($tempPath), 0777, true);
        }

        file_put_contents($tempPath, "stub\n");

        try {
            $prepareMethod = new ReflectionMethod(ImportExcelController::class, 'prepareLw325PhDirectLoadSource');
            $prepareMethod->setAccessible(true);
            $result = $prepareMethod->invoke($controller, $tempPath, ',');

            $this->assertSame('polars', $result['backend']);
            $this->assertSame(1, $result['written_rows']);
            $this->assertSame(['2026-04-04'], $result['period_hints']);
            $this->assertSame(['uniqueid_namareport', 'periode', 'acctno'], $result['headers']);
        } finally {
            @unlink($tempPath);
        }
    }
}
