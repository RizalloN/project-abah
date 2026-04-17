<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportCognosPhController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class ImportCognosPhControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('cognos_ph')) {
            Schema::create('cognos_ph', function (Blueprint $table) {
                $table->string('uniqueid_namareport')->primary();
                $table->date('periode')->nullable();
                $table->string('kanwil')->nullable();
                $table->string('region')->nullable();
                $table->string('ro_fix')->nullable();
                $table->string('bc')->nullable();
                $table->string('sub_bc')->nullable();
                $table->string('kanca')->nullable();
                $table->string('unit_kerja')->nullable();
                $table->string('acctno')->nullable();
                $table->string('cifno')->nullable();
                $table->string('sname')->nullable();
                $table->string('segmen')->nullable();
                $table->string('segmen_bisnis_2025')->nullable();
                $table->string('produk')->nullable();
                $table->string('segmen_kur')->nullable();
                $table->string('segmen_repeat')->nullable();
                $table->string('segmen_2')->nullable();
                $table->string('compliance')->nullable();
                $table->decimal('saldo_ph', 20, 2)->nullable();
                $table->timestamps();
            });
        } else {
            DB::table('cognos_ph')->delete();
        }
    }

    public function test_build_csv_context_detects_semicolon_header_and_end_of_month_period(): void
    {
        $controller = new ImportCognosPhController();
        $csvPath = $this->createFixtureCsv($this->subsetFixtureLines());

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [$csvPath]);

            $this->assertSame(';', $context['delimiter']);
            $this->assertSame(1, $context['header_line']);
            $this->assertSame('2026-01-31', $context['periode']);
            $this->assertSame('acctno', $context['headers'][8]);
            $this->assertSame('saldo_ph', $context['headers'][18]);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_map_csv_row_keeps_acctno_and_duplicate_segmen_for_preview(): void
    {
        $controller = new ImportCognosPhController();
        $csvPath = $this->createFixtureCsv($this->subsetFixtureLines());

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [$csvPath]);
            $line = $this->subsetFixtureLines()[1];
            $mapped = $this->invokeMethod($controller, 'mapCsvRow', [
                $context,
                $this->invokeMethod($controller, 'parseCsvLine', [$line, ';']),
                2,
            ]);

            $this->assertSame('1,00015E+13', $mapped['row'][8]);
            $this->assertSame('2026-01-31', $mapped['row'][0]);
            $this->assertSame('Ritel', $mapped['row'][11]);
            $this->assertSame('Kecil', $mapped['row'][15]);
            $this->assertSame('2.500.000.000', trim((string) $mapped['row'][18]));
            $this->assertSame('2500000000.00', $mapped['normalized_row'][18]);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_normalize_decimal_value_handles_source_formats_without_corrupting_values(): void
    {
        $controller = new ImportCognosPhController();

        $this->assertSame('2500000000.00', $this->invokeMethod($controller, 'normalizeDecimalValue', ['2.500.000.000']));
        $this->assertSame('139433808.00', $this->invokeMethod($controller, 'normalizeDecimalValue', ['139.433.808']));
        $this->assertNull($this->invokeMethod($controller, 'normalizeDecimalValue', ['']));
        $this->assertNull($this->invokeMethod($controller, 'normalizeDecimalValue', ['-']));
    }

    public function test_footer_row_is_detected_when_only_nominal_is_present(): void
    {
        $controller = new ImportCognosPhController();
        $footer = ';;;;;;;;;;;;;;;;;; 99.999 ';
        $parsed = $this->invokeMethod($controller, 'parseCsvLine', [$footer, ';']);

        $this->assertTrue($this->invokeMethod($controller, 'isFooterRow', [$parsed]));
    }

    public function test_collect_preview_unique_values_scans_beyond_first_rows(): void
    {
        $controller = new ImportCognosPhController();
        $lines = $this->subsetFixtureLines();
        $header = array_shift($lines);
        $body = [];

        for ($i = 0; $i < 20; $i++) {
            $body[] = $lines[0];
        }

        $body[] = '202601;Z -- Test;Region X;Test Fix;999;9999;09999 -- KC Unique;09999 -- UNIT UNIQUE;9,99999E+13;ZZ99999;NAMA UJI;Mikro;Micro;Produk Uji;Segmen Kur Uji;Segmen Repeat Uji;Segmen Dua Uji;Compliance Uji; 10.000 ';
        $csvPath = $this->createFixtureCsv(array_merge([$header], $body));

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [$csvPath]);
            $values = $this->invokeMethod($controller, 'collectPreviewUniqueValues', [$csvPath, $context]);
            $kancaIndex = array_search('kanca', $context['headers'], true);

            $this->assertContains('09999 -- KC Unique', $values[$kancaIndex]);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_subset_import_inserts_20_rows_and_preserves_control_sums(): void
    {
        $controller = new ImportCognosPhController();
        $csvPath = $this->createFixtureCsv($this->subsetFixtureLines());

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [$csvPath]);
            $headers = $context['headers'];
            $selectedColumns = range(0, count($headers) - 1);
            $handle = fopen($csvPath, 'r');
            $lineNumber = 0;
            $rows = [];
            $success = 0;
            $failed = 0;
            $lastError = '';

            try {
                while (($line = fgets($handle)) !== false) {
                    $lineNumber++;
                    if ($lineNumber <= $context['header_line']) {
                        continue;
                    }

                    $mapped = $this->invokeMethod($controller, 'mapCsvRow', [
                        $context,
                        $this->invokeMethod($controller, 'parseCsvLine', [$line, ';']),
                        $lineNumber,
                    ]);
                    if ($mapped === null) {
                        continue;
                    }

                    $insertRow = $this->invokeMethod($controller, 'buildInsertRow', [
                        $headers,
                        $mapped['normalized_row'],
                        $selectedColumns,
                    ]);
                    if ($insertRow === null) {
                        continue;
                    }

                    $rows[] = $insertRow;
                }
            } finally {
                fclose($handle);
            }

            $this->invokeMethod($controller, 'insertBatch', [$rows, &$success, &$failed, &$lastError]);

            $this->assertSame(20, DB::table('cognos_ph')->count());
            $this->assertSame('2026-01-31', DB::table('cognos_ph')->value('periode'));

            $firstTenNames = [
                'IHSAN B NADIRIN SE',
                'NASIATUL KHABIBAH',
                'NINA SUSANTI',
                'SRIYADI HIDAYAT',
                'INEU',
                'KUKUH IMAN PRASETYO',
                'ASEP SYAEFUL AMIR',
                'ADI WINATA',
                'PIRMANSAH',
                'BERSIH TARIGAN',
            ];
            $lastTenNames = [
                'RATNASARI DEWI',
                'NURDIN',
                'NOVEAN RAHMADI MUIZ',
                'RUDINI',
                'ANTI RUMIYANTI',
                'AGUSTIA',
                'EDI SURYANTO',
                'JURESNITA',
                'AMIR BIN JASIH',
                'KUSWAN',
            ];

            $firstTen = DB::table('cognos_ph')->whereIn('sname', $firstTenNames);
            $lastTen = DB::table('cognos_ph')->whereIn('sname', $lastTenNames);

            $this->assertSame('8536416369.00', $this->formatDbDecimal($firstTen->sum('saldo_ph')));
            $this->assertSame('732112557.00', $this->formatDbDecimal($lastTen->sum('saldo_ph')));
        } finally {
            @unlink($csvPath);
        }
    }

    private function createFixtureCsv(array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cognos_ph_') . '.csv';
        file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);

        return $path;
    }

    private function formatDbDecimal($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function invokeMethod(object $target, string $method, array $arguments)
    {
        $reflection = new ReflectionClass($target);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invokeArgs($target, $arguments);
    }

    private function subsetFixtureLines(): array
    {
        return [
            'PERIODE_DATA;KANWIL;REGION;RO FIX;BC;SUB BC;KANCA;UNIT_KERJA;ACCTNO;CIFNO;SNAME;SEGMEN;SEGMEN_BISNIS_2025;PRODUK;SEGMEN KUR;SEGMEN;SEGMEN 2;Compliance; SALDO_PH ',
            '202601;F -- Bandung                                      ;Region 9;Bandung;100;100;00100 -- KC Tasikmalaya (Konsolidasi-MB)                                        ;00100 -- KC Tasikmalaya                           ;1,00015E+13;IKK3023;IHSAN B NADIRIN SE  ;Ritel;Small;Ritel Komersial;Kecil;Kecil;Small;Ritel Komersial >Rp 1M s.d Rp25 M; 2.500.000.000 ',
            '202601;G -- Semarang                                     ;Region 10;Semarang;435;1007;00435 -- KC Brigjen Sudiarto (Konsolidasi-MB)                                   ;01007 -- KCP KEDUNGMUNDU                          ;1,00701E+14;NIOO674;NASIATUL KHABIBAH   ;Kecil;Small;KUR Kecil 2015 New;Program KUR;Program;Micro;KUR Ritel; 139.433.808 ',
            '202601;G -- Semarang                                     ;Region 10;Semarang;435;1007;00435 -- KC Brigjen Sudiarto (Konsolidasi-MB)                                   ;01007 -- KCP KEDUNGMUNDU                          ;1,00701E+14;NGKB175;NINA SUSANTI        ;Ritel;Small;Ritel Komersial;Kecil;Kecil;Small;Ritel Komersial >Rp 1M s.d Rp25 M; 1.999.500.000 ',
            '202601;H -- Yogyakarta                                   ;Region 11;Yogyakarta;410;1008;00410 -- KC Yogyakarta Adisucipto (Konsolidasi-MB)                              ;01008 -- KCP GEDONG KUNING                        ;1,00801E+14;SEQP151;SRIYADI HIDAYAT     ;Kecil;Small;Kecil Komersial;Kecil;Kecil;Small;Ritel Komersial >Rp 1M s.d Rp25 M; 299.700.000 ',
            '202601;F -- Bandung                                      ;Region 9;Bandung;104;104;00104 -- KC Ciamis (Konsolidasi-MB)                                             ;00104 -- KC Ciamis                                ;1,04015E+13;IBQ4857;INEU                ;Kecil;Small;Kecil Komersial;Kecil;Kecil;Small;Ritel Komersial >Rp 1M s.d Rp25 M; 598.663.000 ',
            '202601;H -- Yogyakarta                                   ;Region 11;Yogyakarta;106;106;00106 -- KC Cilacap (Konsolidasi-MB)                                            ;00106 -- KC Cilacap                               ;1,06015E+13;KLR7360;KUKUH IMAN PRASETYO ;Ritel;Small;Ritel Komersial;Kecil;Kecil;Small;Ritel Komersial >Rp 1M s.d Rp25 M; 2.300.000.000 ',
            '202601;F -- Bandung                                      ;Region 9;Bandung;107;107;00107 -- KC Cirebon Kartini (Konsolidasi-MB)                                    ;00107 -- KC Cirebon Kartini                       ;1,07011E+13;AJE8158;ASEP SYAEFUL AMIR   ;Kecil;Small;KUR Kecil 2015 New;Program KUR;Program;Micro;KUR Ritel; 193.125.958 ',
            '202601;J -- KANWIL BANDAR LAMPUNG                        ;Region 5;Bandar Lampung;108;108;00108 -- KC Curup (Konsolidasi-MB)                                              ;00108 -- KC Curup                                 ;1,0801E+13;AVVX996;ADI WINATA          ;Ritel;Consumer;BRIGuna-Kretap;Briguna;Briguna;Consumer;KREDIT PEGAWAI; 5.294.037 ',
            '202601;J -- KANWIL BANDAR LAMPUNG                        ;Region 5;Bandar Lampung;108;108;00108 -- KC Curup (Konsolidasi-MB)                                              ;00108 -- KC Curup                                 ;1,0801E+13;PEA4276;PIRMANSAH           ;Ritel;Consumer;BRIGuna-Kretap;Briguna;Briguna;Consumer;KREDIT PEGAWAI; 699.566 ',
            '202601;B -- Medan                                        ;Region 1;Medan;53;1083;00053 -- KC Medan Putri Hijau (Konsolidasi-MB)                                  ;01083 -- KCP KESAWAN                              ;1,08301E+14;BH37492;BERSIH TARIGAN      ;Kecil;Small;Kecil Komersial;Kecil;Kecil;Small;Ritel Komersial >Rp 1M s.d Rp25 M; 500.000.000 ',
            '202601;F -- Bandung                                      ;Region 9;Bandung;405;782;00405 -- KC Bandung Dago (Konsolidasi-MB)                                       ;00782 -- UNIT SUKAMAJU BANDUNG                    ;9,8901E+13;RQP4251;RATNASARI DEWI      ;Mikro;Micro;KUR Kupedes Baru;Mikro KUR;Mikro;Micro;KUR Mikro 0 s.d 50 juta; 24.441.483 ',
            '202601;I -- DKI2                                         ;Region 7;Jakarta II;421;993;00421 -- KC Cibinong (Konsolidasi-MB)                                           ;00993 -- UNIT CIBINONG PABUARAN                   ;9,9301E+13;NZE9808;NURDIN              ;Mikro;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 134.795.160 ',
            '202601;I -- DKI2                                         ;Region 7;Jakarta II;421;993;00421 -- KC Cibinong (Konsolidasi-MB)                                           ;00993 -- UNIT CIBINONG PABUARAN                   ;9,9301E+13;NBWH798;NOVEAN RAHMADI MUIZ ;Mikro;Micro;KUR Kupedes Baru;Mikro KUR;Mikro;Micro;KUR Mikro 0 s.d 50 juta; 59.719.729 ',
            '202601;Q -- KANWIL JAKARTA 3                             ;Region 8;Jakarta III;382;994;00382 -- KC Ciputat (Konsolidasi-MB)                                            ;00994 -- UNIT CIPUTAT JAKARTA                     ;9,9401E+13;RKWN776;RUDINI              ;Mikro;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 72.242.845 ',
            '202601;Q -- KANWIL JAKARTA 3                             ;Region 8;Jakarta III;382;994;00382 -- KC Ciputat (Konsolidasi-MB)                                            ;00994 -- UNIT CIPUTAT JAKARTA                     ;9,9401E+13;ABLI855;ANTI RUMIYANTI      ;Mikro;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 48.577.962 ',
            '202601;I -- DKI2                                         ;Region 7;Jakarta II;385;995;00385 -- KC Pondok Gede (Konsolidasi-MB)                                        ;00995 -- UNIT JATI MAKMUR PONDOK GEDE             ;9,9501E+13;AYGD337;AGUSTIA             ;Mikro;Micro;KUR Kupedes Baru;Mikro KUR;Mikro;Micro;KUR Mikro 0 s.d 50 juta; 52.844.254 ',
            '202601;E -- DKI                                          ;Region 6;Jakarta I;419;997;00419 -- KC JAKARTA KALIMALANG (Konsolidasi-MB)                                 ;00997 -- UNIT MALAKA                              ;9,9701E+13;EFW9796;EDI SURYANTO        ;Mikro;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 72.863.712 ',
            '202601;E -- DKI                                          ;Region 6;Jakarta I;419;3305;00419 -- KC JAKARTA KALIMALANG (Konsolidasi-MB)                                 ;03305 -- UNIT PULO JAHE                           ;9,9701E+13;JF54581;JURESNITA           ;Mikro;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 132.413.873 ',
            '202601;E -- DKI                                          ;Region 6;Jakarta I;419;997;00419 -- KC JAKARTA KALIMALANG (Konsolidasi-MB)                                 ;00997 -- UNIT MALAKA                              ;9,9701E+13;ASJR067;AMIR BIN JASIH      ;Mikro;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 40.975.245 ',
            '202601;Q -- KANWIL JAKARTA 3                             ;Region 8;Jakarta III;1127;998;01127 -- KC Pamulang (Konsolidasi-MB)                                           ;00998 -- UNIT PONDOK CABE                         ;9,9801E+13;KV62666;KUSWAN              ;Mikro;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 93.238.294 ',
        ];
    }
}
