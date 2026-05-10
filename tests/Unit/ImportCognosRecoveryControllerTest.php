<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportCognosRecoveryController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class ImportCognosRecoveryControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Test ini hanya boleh berjalan di SQLite. Bukan di MySQL production. Periksa phpunit.xml.');
        }

        Schema::dropIfExists('cognos_recovery');
        Schema::create('cognos_recovery', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('cifno')->nullable();
            $table->string('bc')->nullable();
            $table->string('sub_bc')->nullable();
            $table->string('kanwil')->nullable();
            $table->string('ro_fix')->nullable();
            $table->string('region')->nullable();
            $table->string('cabang')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->string('gl_account')->nullable();
            $table->string('produk_code')->nullable();
            $table->string('segmen_fpsl')->nullable();
            $table->string('rekening')->nullable();
            $table->string('status')->nullable();
            $table->string('stsdt_dt_raw')->nullable();
            $table->string('sname')->nullable();
            $table->string('segmen')->nullable();
            $table->string('segmen_bisnis')->nullable();
            $table->string('segmen_bisnis_2025')->nullable();
            $table->string('produk')->nullable();
            $table->string('segmen_kur')->nullable();
            $table->string('segmen_repeat')->nullable();
            $table->string('segmen_2')->nullable();
            $table->string('compliance')->nullable();
            $table->decimal('recovery', 20, 2)->nullable();
            $table->decimal('recovery_klaim', 20, 2)->nullable();
            $table->decimal('recovery_olsib', 20, 2)->nullable();
            $table->decimal('total_recovery', 20, 2)->nullable();
            $table->decimal('recovery_non_klaim', 20, 2)->nullable();
            $table->timestamps();
        });
    }

    public function test_build_csv_context_detects_semicolon_header_and_end_of_month_period(): void
    {
        $controller = new ImportCognosRecoveryController();
        $csvPath = $this->createFixtureCsv($this->subsetFixtureLines());

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [$csvPath]);

            $this->assertSame(';', $context['delimiter']);
            $this->assertSame(1, $context['header_line']);
            $this->assertSame('2026-01-31', $context['periode']);
            $this->assertSame('rekening', $context['headers'][13]);
            $this->assertSame('recovery_non_klaim', $context['headers'][29]);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_map_csv_row_keeps_rekening_and_duplicate_segmen_for_preview(): void
    {
        $controller = new ImportCognosRecoveryController();
        $csvPath = $this->createFixtureCsv($this->subsetFixtureLines());

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [$csvPath]);
            $line = $this->subsetFixtureLines()[1];
            $mapped = $this->invokeMethod($controller, 'mapCsvRow', [
                $context,
                $this->invokeMethod($controller, 'parseCsvLine', [$line, ';']),
                2,
            ]);

            $this->assertSame('7,40101E+14', $mapped['row'][13]);
            $this->assertSame('2026-01-31', $mapped['row'][0]);
            $this->assertSame('IMRONDI', trim((string) $mapped['row'][16]));
            $this->assertSame('Mikro', $mapped['row'][17]);
            $this->assertSame('Mikro', $mapped['row'][22]);
            $this->assertSame('493.809', trim((string) $mapped['row'][25]));
            $this->assertSame('493809.00', $mapped['normalized_row'][25]);
            $this->assertSame('45996', $mapped['row'][15]);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_normalize_decimal_value_handles_source_formats_without_corrupting_values(): void
    {
        $controller = new ImportCognosRecoveryController();

        $this->assertSame('493809.00', $this->invokeMethod($controller, 'normalizeDecimalValue', ['493.809']));
        $this->assertSame('6413092.00', $this->invokeMethod($controller, 'normalizeDecimalValue', ['6.413.092']));
        $this->assertSame('-3307500.00', $this->invokeMethod($controller, 'normalizeDecimalValue', ['(3.307.500)']));
        $this->assertNull($this->invokeMethod($controller, 'normalizeDecimalValue', ['']));
        $this->assertNull($this->invokeMethod($controller, 'normalizeDecimalValue', ['-']));
    }

    public function test_legacy_short_header_is_mapped_by_name_not_fixed_position(): void
    {
        $controller = new ImportCognosRecoveryController();
        $csvPath = $this->createFixtureCsv([
            'PERIODE_DATA;KANWIL;RO FIX;KANCA;UNIT_KERJA;ACCTNO;CIFNO;SNAME;PRODUK;SEGMEN KUR;SEGMEN;SEGMEN 2;COMPLIANCE; SALDO_PH ; RECOVERY ; RECOVERY_KLAIM_ASURANSI ; RECOVERY_OLSIB ; TOTAL_RECOVERY ; RECOVERY NON KLAIM ',
            '202501;R -- KANWIL MALANG;Malang;00049 -- KC Magetan (Konsolidasi-MB);03504 -- UNIT MAOSPATI MAGETAN;701500314157;SA94997;SUTIYEM;KUR Kupedes Baru;Mikro KUR;Mikro;Mikro;KUR Mikro; - ; 1.600.000 ; - ; - ; 1.600.000 ; 1.600.000 ',
        ]);

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [$csvPath]);
            $line = file($csvPath, FILE_IGNORE_NEW_LINES)[1];
            $mapped = $this->invokeMethod($controller, 'mapCsvRow', [
                $context,
                $this->invokeMethod($controller, 'parseCsvLine', [$line, ';']),
                2,
            ]);

            $headers = $context['headers'];
            $this->assertSame('2025-01-31', $mapped['normalized_row'][array_search('periode', $headers, true)]);
            $this->assertSame('KC Magetan', $mapped['normalized_row'][array_search('cabang', $headers, true)]);
            $this->assertSame('UNIT MAOSPATI MAGETAN', $mapped['normalized_row'][array_search('unit_kerja', $headers, true)]);
            $this->assertSame('701500314157', $mapped['normalized_row'][array_search('rekening', $headers, true)]);
            $this->assertSame('MICRO', $mapped['normalized_row'][array_search('segmen_bisnis_2025', $headers, true)]);
            $this->assertSame('1600000.00', $mapped['normalized_row'][array_search('total_recovery', $headers, true)]);
            $this->assertSame('1600000.00', $mapped['normalized_row'][array_search('recovery_non_klaim', $headers, true)]);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_modern_shuffled_header_aliases_are_importable(): void
    {
        $controller = new ImportCognosRecoveryController();
        $csvPath = $this->createFixtureCsv([
            'PERIODE;KETERANGAN;MB;SUB BC;KANWIL;RO FIX;REGION;CABANG;UNIT_KERJA;GL_ACCOUNT;PRODUK_CODE;SEGMEN_FPSL;REKENING;STATUS;STSDT_DT;CIFNO;SNAME;SEGMEN;SEGMEN_BISNIS;SEGMEN_BISNIS_2025;PRODUK;SEGMEN KUR;SEGMEN;SEGMEN 2;COMPLIANCE; Recovery ; Recovery Klaim ; Recovery Olsib ; Total Recovery ; Recovery Non Klaim ',
            '202510;Klaim Asuransi;65;65;KANWIL MALANG;Malang;Region 13;KC Pasuruan;00065 -- KC Pasuruan;4080005000;RV;Small;6,50105E+12;8;45899;ENW5641;ENDANG;Kecil;KECIL;Small;KUR Kecil;Program KUR;Program;Kecil;KUR Ritel; - ;150.637.413; - ;150.637.413; - ',
        ]);

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [$csvPath]);
            $line = file($csvPath, FILE_IGNORE_NEW_LINES)[1];
            $mapped = $this->invokeMethod($controller, 'mapCsvRow', [
                $context,
                $this->invokeMethod($controller, 'parseCsvLine', [$line, ';']),
                2,
            ]);

            $headers = $context['headers'];
            $this->assertSame('65', $mapped['normalized_row'][array_search('bc', $headers, true)]);
            $this->assertSame('65', $mapped['normalized_row'][array_search('sub_bc', $headers, true)]);
            $this->assertSame('KC Pasuruan', $mapped['normalized_row'][array_search('unit_kerja', $headers, true)]);
            $this->assertSame('SMALL', $mapped['normalized_row'][array_search('segmen_bisnis_2025', $headers, true)]);
            $this->assertSame('150637413.00', $mapped['normalized_row'][array_search('recovery_klaim', $headers, true)]);
            $this->assertSame('150637413.00', $mapped['normalized_row'][array_search('total_recovery', $headers, true)]);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_footer_row_is_detected_and_skipped(): void
    {
        $controller = new ImportCognosRecoveryController();
        $csvPath = $this->createFixtureCsv($this->subsetFixtureLines());

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [$csvPath]);
            $lines = $this->subsetFixtureLines();
            $footerLine = end($lines);
            $parsed = $this->invokeMethod($controller, 'parseCsvLine', [$footerLine, ';']);

            $this->assertTrue($this->invokeMethod($controller, 'isFooterRow', [$parsed]));
            $this->assertNull($this->invokeMethod($controller, 'mapCsvRow', [$context, $parsed, count($lines)]));
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_collect_preview_unique_values_scans_beyond_first_rows(): void
    {
        $controller = new ImportCognosRecoveryController();
        $lines = $this->subsetFixtureLines();
        $header = array_shift($lines);
        $body = [];

        for ($i = 0; $i < 20; $i++) {
            $body[] = $lines[0];
        }

        $body[] = '202601;Brinets;ZZ99999;999;9999;Kanwil Uji;Ro Uji;Region Uji;KC Unique;09999 -- UNIT UNIQUE;4080005000;P2;Micro;9,99999E+14;8;45996;NAMA UJI;Mikro;MIKRO;Micro;Produk Uji;Mikro Uji;Mikro;Micro;Compliance Uji; 10.000 ;;; 10.000 ; 10.000 ';
        $body[] = end($lines);

        $csvPath = $this->createFixtureCsv(array_merge([$header], $body));

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [$csvPath]);
            $values = $this->invokeMethod($controller, 'collectPreviewUniqueValues', [$csvPath, $context]);
            $cabangIndex = array_search('cabang', $context['headers'], true);

            $this->assertContains('KC Unique', $values[$cabangIndex]);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_subset_import_inserts_20_rows_and_preserves_control_sums(): void
    {
        $controller = new ImportCognosRecoveryController();
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

            $this->assertSame(20, DB::table('cognos_recovery')->count());
            $this->assertSame(0, DB::table('cognos_recovery')->whereNull('keterangan')->count());
            $this->assertSame('2026-01-31', DB::table('cognos_recovery')->value('periode'));

            $firstTenNames = [
                'IMRONDI',
                'ISTIGHFAROH',
                'BADRUDIN',
                'KOMARIAH',
                'JONI',
                'NENDIANTO',
                'MARZUKI IBRAHIM',
                'JANUARI',
                'JUMUIYAH',
                'NANI YANTI',
            ];
            $lastTenNames = [
                'NURPARIDAH',
                'NORMAWATI',
                'MUNIKMAH',
                'MISRA JAYA',
                'MISNA',
                'NI NENGAH WARNI',
                'MUHAWI',
                'MUH RAMLI',
                'KARWA',
                'ISRAM L',
            ];
            $firstTen = DB::table('cognos_recovery')->whereIn('sname', $firstTenNames);
            $lastTen = DB::table('cognos_recovery')->whereIn('sname', $lastTenNames);

            $this->assertSame('12268901.00', $this->formatDbDecimal($firstTen->sum('recovery')));
            $this->assertSame('0.00', $this->formatDbDecimal($firstTen->sum('recovery_klaim')));
            $this->assertSame('0.00', $this->formatDbDecimal($firstTen->sum('recovery_olsib')));
            $this->assertSame('12268901.00', $this->formatDbDecimal($firstTen->sum('total_recovery')));
            $this->assertSame('12268901.00', $this->formatDbDecimal($firstTen->sum('recovery_non_klaim')));

            $this->assertSame('20696233.00', $this->formatDbDecimal($lastTen->sum('recovery')));
            $this->assertSame('0.00', $this->formatDbDecimal($lastTen->sum('recovery_klaim')));
            $this->assertSame('0.00', $this->formatDbDecimal($lastTen->sum('recovery_olsib')));
            $this->assertSame('20696233.00', $this->formatDbDecimal($lastTen->sum('total_recovery')));
            $this->assertSame('20696233.00', $this->formatDbDecimal($lastTen->sum('recovery_non_klaim')));
        } finally {
            @unlink($csvPath);
        }
    }

    private function createFixtureCsv(array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cognos_recovery_') . '.csv';
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
            'PERIODE;KETERANGAN;CIFNO;BC;SUB BC;KANWIL;RO FIX;REGION;CABANG;UNIT_KERJA;GL_ACCOUNT;PRODUK_CODE;SEGMEN_FPSL;REKENING;STATUS;STSDT_DT;SNAME;SEGMEN;SEGMEN_BISNIS;SEGMEN_BISNIS_2025;PRODUK;SEGMEN KUR;SEGMEN;SEGMEN 2;COMPLIANCE; RECOVERY ; Recovery Klaim ; Recovery Olsib ; Total Recovery ; Recovery Non Klaim ',
            '202601;Brinets;IV25992;207;7401;Banjarmasin                             ;Banjarmasin;Region 14;KC Mempawah                             ;07401 -- UNIT MANDOR                              ;4080005000;P2;Micro;7,40101E+14;8;45996;IMRONDI             ;Mikro;MIKRO;Micro;KUR Kupedes Baru;Mikro KUR;Mikro;Micro;KUR Mikro 0 s.d 50 juta; 493.809 ;;; 493.809 ; 493.809 ',
            '202601;Brinets;IWG6156;48;6772;Yogyakarta                              ;Yogyakarta;Region 11;KC Magelang                             ;06772 -- UNIT KALIANGRIK MAGELANG                 ;4080005000;1K;Micro;6,77201E+14;8;46014;ISTIGHFAROH         ;Mikro;MIKRO;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 188.000 ;;; 188.000 ; 188.000 ',
            '202601;Brinets;BFT6289;92;7778;Bandung                                 ;Bandung;Region 9;KC Sukabumi                             ;07778 -- UNIT CIEMAS SUKABUMI                     ;4080005000;1K;Micro;7,77801E+14;8;45991;BADRUDIN            ;Mikro;MIKRO;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 500.000 ;;; 500.000 ; 500.000 ',
            '202601;Brinets;KQF5406;133;4284;Bandung                                 ;Bandung;Region 9;KC Kuningan                             ;04284 -- UNIT SELAJAMBE KUNINGAN                  ;4080005000;P2;Micro;4,28401E+14;8;45626;KOMARIAH            ;Mikro;MIKRO;Micro;KUR Kupedes Baru;Mikro KUR;Mikro;Micro;KUR Mikro 0 s.d 50 juta; 30.000 ;;; 30.000 ; 30.000 ',
            '202601;Brinets;JBH1057;357;5598;KANWIL BANDAR LAMPUNG                   ;Bandar Lampung;Region 5;KC Bandar Jaya                          ;05598 -- UNIT RUMBIA BANDAR JAYA                  ;4080005000;SL;Micro;5,59801E+14;8;44438;JONI                ;Mikro;MIKRO;Micro;KUR Kupedes Baru;Mikro KUR;Mikro;Micro;KUR Mikro 0 s.d 50 juta; 144.000 ;;; 144.000 ; 144.000 ',
            '202601;Brinets;NEKS897;74;6810;Yogyakarta                              ;Yogyakarta;Region 11;KC Purbalingga                          ;06810 -- UNIT KARANGREJA PURBALINGGA              ;4080005000;1L;Micro;6,81001E+14;8;45351;NENDIANTO           ;Mikro;MIKRO;Micro;Kupedes Rakyat;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 250.000 ;;; 250.000 ; 250.000 ',
            '202601;Brinets;MGL2299;238;5259;Medan                                   ;Medan;Region 1;KC Binjai                               ;05259 -- UNIT SUDIRMAN BINJAI                     ;4080005000;LI;Micro;5,25901E+14;2;46049;MARZUKI IBRAHIM     ;Mikro;MIKRO;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 6.413.092 ;;; 6.413.092 ; 6.413.092 ',
            '202601;Brinets;JIF5686;40;5675;Palembang                               ;Palembang;Region 4;KC Lahat                                ;05675 -- UNIT PS LEMATANG LAHAT                   ;4080005000;1K;Micro;5,67501E+14;8;45991;JANUARI             ;Mikro;MIKRO;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 1.050.000 ;;; 1.050.000 ; 1.050.000 ',
            '202601;Brinets;JR51354;156;5823;Semarang                                ;Semarang;Region 10;KC Batang                               ;05823 -- UNIT TERSONO  BATANG                     ;4080005000;1L;Micro;5,82301E+14;8;45869;JUMUIYAH            ;Mikro;MIKRO;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 2.200.000 ;;; 2.200.000 ; 2.200.000 ',
            '202601;Brinets;NVN1713;59;5750;Palembang                               ;Palembang;Region 4;KC Palembang A. Rivai                   ;05750 -- UNIT LINGKARAN PALEMBANG A RIV           ;4080005000;SH;Micro;5,75001E+14;8;44440;NANI YANTI          ;Mikro;MIKRO;Micro;KUR Kupedes Baru;Mikro KUR;Mikro;Micro;KUR Mikro 0 s.d 50 juta; 1.000.000 ;;; 1.000.000 ; 1.000.000 ',
            '202601;Brinets;NBOH107;105;4071;Bandung                                 ;Bandung;Region 9;KC Cianjur                              ;04071 -- UNIT KAWUNGLUWUK CIANJUR                 ;4080005000;SX;Micro;4,06401E+14;8;45838;NURPARIDAH          ;Mikro;MIKRO;Micro;KUR Kupedes Baru;Mikro KUR;Mikro;Micro;KUR Mikro 0 s.d 50 juta; 900.000 ;;; 900.000 ; 900.000 ',
            '202601;Brinets;NFUY877;253;4898;Makassar                                ;Makassar;Region 15;KC Bulukumba                            ;04898 -- UNIT BONTOMANAI BULUKUMBA                ;4080005000;1K;Micro;4,89801E+14;2;46028;NORMAWATI           ;Mikro;MIKRO;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 16.091.533 ;;; 16.091.533 ; 16.091.533 ',
            '202601;Brinets;MSF1234;16;3738;Semarang                                ;Semarang;Region 10;KC Demak                                ;03738 -- UNIT GAJAH DEMAK                         ;4080005000;H5;Micro;3,73801E+14;8;41992;MUNIKMAH            ;Mikro;MIKRO;Micro;KUR Kupedes;Mikro KUR;Mikro;Micro;KUR Mikro 0 s.d 50 juta; 75.000 ;;; 75.000 ; 75.000 ',
            '202601;Brinets;MQK9857;275;3389;Palembang                               ;Palembang;Region 4;KC Bangko                               ;03389 -- UNIT MERANGIN BANGKO                     ;4080005000;1H;Micro;3,38901E+14;8;45688;MISRA JAYA          ;Mikro;MIKRO;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 191.000 ;;; 191.000 ; 191.000 ',
            '202601;Brinets;MVK7370;116;3460;DKI2                                    ;Jakarta II;Region 7;KC Karawang                             ;03460 -- UNIT JOHAR KARAWANG                      ;4080005000;1G;Micro;3,46001E+14;8;45777;MISNA               ;Mikro;MIKRO;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 2.000.000 ;;; 2.000.000 ; 2.000.000 ',
            '202601;Brinets;NRX1948;572;985;Denpasar                                ;Denpasar;Region 17;KC Denpasar Gatot Subroto               ;00985 -- UNIT GATOT SUBROTO                       ;4080005000;SH;Micro;9,8501E+13;8;43318;NI NENGAH WARNI     ;Mikro;MIKRO;Micro;KUR Kupedes Baru;Mikro KUR;Mikro;Micro;KUR Mikro 0 s.d 50 juta; 800.000 ;;; 800.000 ; 800.000 ',
            '202601;Brinets;MPI5811;13;6195;KANWIL MALANG                           ;Malang;Region 13;KC Bondowoso                            ;06195 -- UNIT GRUJUGAN BONDOWOSO                  ;4080005000;4I;Micro;6,19501E+14;8;45855;MUHAWI              ;Mikro;MIKRO;Micro;KUR Kupedes Baru;Mikro KUR;Mikro;Micro;KUR Mikro 0 s.d 50 juta; 8.700 ;;; 8.700 ; 8.700 ',
            '202601;Brinets;MRTJ217;157;7598;Denpasar                                ;Denpasar;Region 17;KC Selong                               ;07598 -- UNIT PANCOR SELONG                       ;4080005000;P2;Micro;7,59801E+14;8;44834;MUH RAMLI           ;Mikro;MIKRO;Micro;KUR Kupedes Baru;Mikro KUR;Mikro;Micro;KUR Mikro 0 s.d 50 juta; 30.000 ;;; 30.000 ; 30.000 ',
            '202601;Brinets;KNB6758;161;4360;Bandung                                 ;Bandung;Region 9;KC Singaparna                           ;04360 -- UNIT CIBALONG SINGAPARNA                 ;4080005000;HA;Micro;4,36001E+14;8;45351;KARWA               ;Mikro;MIKRO;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 100.000 ;;; 100.000 ; 100.000 ',
            '202601;Brinets;IN65693;363;5197;Manado                                  ;Manado;Region 16;KC Parigi                               ;05197 -- UNIT TINOMBO PARIGI                      ;4080005000;HW;Micro;5,19701E+14;8;45245;ISRAM L             ;Mikro;MIKRO;Micro;Kupedes;Mikro Non KUR;Mikro;Micro;Kupedes 0 s.d 250 Juta; 500.000 ;;; 500.000 ; 500.000 ',
            ';;;;;;;;;;;;;;;;;;;;;;;;; 8.263.442.421 ; 4.316.154.260 ; -   ; 12.579.596.681 ; 8.263.442.421 ',
        ];
    }
}
