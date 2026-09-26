<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class ImportExcelControllerHourlyDpkTest extends TestCase
{
    public function test_hourly_dpk_raw_workbook_headers_map_posisi_posisi_jam_and_unique_id(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'posisi',
                    'posisi_jam',
                    'mbname',
                    'brname',
                    'segmen',
                    'segmen2',
                    'produk',
                    'saldo',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [
                    'mbname' => ['is_textual' => true, 'max_length' => 150],
                    'brname' => ['is_textual' => true, 'max_length' => 150],
                    'segmen' => ['is_textual' => true, 'max_length' => 50],
                    'segmen2' => ['is_textual' => true, 'max_length' => 50],
                    'produk' => ['is_textual' => true, 'max_length' => 100],
                    'posisi_jam' => [],
                    'saldo' => ['scale' => 2],
                ];
            }
        };

        $headers = [
            'Minute of POSISI',
            'MBNAME',
            'BRNAME',
            'SEGMEN2',
            'PRODUK',
            'Saldo',
        ];
        $normalizedHeaders = app(\App\Services\Import\Strategies\HourlyDpkImportStrategy::class)->transformHeaders($headers);

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'hourly_dpk', $normalizedHeaders);

        $this->assertSame('uniqueid_namareport', $context['unique_id_col']);
        $this->assertSame('', $context['suffix']);
        $this->assertStringStartsWith('uuid_hourly_dpk_', $context['unique_id_prefix']);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke(
            $controller,
            [
                'June 26, 2026 at 6:00 AM',
                '00045 -- KC Madiun(Konsolidasi-MB)',
                '00045 -- KC Madiun',
                'RITEL',
                'GIRO',
                '13833797168.82',
            ],
            $normalizedHeaders,
            $context,
            '2026-05-11 20:45:00'
        );

        $this->assertIsArray($row);
        $this->assertArrayNotHasKey('id_report', $row);
        $this->assertArrayNotHasKey('created_at', $row);
        $this->assertArrayNotHasKey('updated_at', $row);
        $this->assertStringStartsWith('uuid_hourly_dpk_', $row['uniqueid_namareport']);
        $this->assertFalse(str_ends_with($row['uniqueid_namareport'], '_DLD'));
        $this->assertSame('2026-06-26', $row['posisi']);
        $this->assertSame('2026-06-26 06:00:00', $row['posisi_jam']);
        $this->assertSame('00045 -- KC Madiun(Konsolidasi-MB)', $row['mbname']);
        $this->assertSame('00045 -- KC Madiun', $row['brname']);
        $this->assertSame('RITEL', $row['segmen2']);
        $this->assertSame('GIRO', $row['produk']);
        $this->assertSame('13833797168.82', $row['saldo']);

        $bulkLoadMethod = new ReflectionMethod(ImportExcelController::class, 'buildBulkLoadColumns');
        $bulkLoadMethod->setAccessible(true);
        $bulkLoadColumns = $bulkLoadMethod->invoke($controller, 'hourly_dpk', $normalizedHeaders);

        $this->assertContains('posisi_jam', $bulkLoadColumns);
    }

    public function test_indonesian_textual_dates_do_not_apply_to_other_import_tables(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return ['tanggal'];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $headers = ['Tanggal'];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'generic_report', $headers);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke(
            $controller,
            ['12 Mei 2026'],
            $headers,
            $context,
            '2026-05-12 08:00:00'
        );

        $this->assertIsArray($row);
        $this->assertNull($row['tanggal']);
    }

    public function test_hourly_dpk_rejects_import_when_saldo_header_is_missing(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'posisi',
                    'posisi_jam',
                    'mbname',
                    'brname',
                    'segmen2',
                    'produk',
                    'saldo',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [
                    'mbname' => ['is_textual' => true, 'max_length' => 150],
                    'brname' => ['is_textual' => true, 'max_length' => 150],
                    'segmen2' => ['is_textual' => true, 'max_length' => 50],
                    'produk' => ['is_textual' => true, 'max_length' => 100],
                    'posisi_jam' => [],
                    'saldo' => ['scale' => 2],
                ];
            }
        };

        $headers = app(\App\Services\Import\Strategies\HourlyDpkImportStrategy::class)->transformHeaders([
            'Minute of POSISI',
            'MBNAME',
            'BRNAME',
            'SEGMEN2',
            'PRODUK',
            'PRODUK',
        ]);

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kolom wajib yang belum ditemukan: SALDO');
        $this->expectExceptionMessage('Terdeteksi lebih dari satu header PRODUK');

        $contextMethod->invoke($controller, 'hourly_dpk', $headers);
    }

    public function test_hourly_dpk_smart_header_mapping_accepts_bri_aliases_bom_and_minor_typos(): void
    {
        $strategy = app(\App\Services\Import\Strategies\HourlyDpkImportStrategy::class);

        $this->assertSame([
            'posisi',
            'mbname',
            'brname',
            'segmen2',
            'produk',
            'saldo',
        ], $strategy->transformHeaders([
            "\xEF\xBB\xBFminute-of-position",
            'MB Name',
            'BR Nmae',
            'Segment 2',
            'Product',
            'SADLO',
        ]));
    }

    public function test_hourly_dpk_header_error_lists_all_missing_duplicate_and_read_headers(): void
    {
        $controller = new ImportExcelController();
        $method = new ReflectionMethod(ImportExcelController::class, 'assertValidHourlyDpkHeaders');
        $method->setAccessible(true);

        try {
            $method->invoke($controller, 'hourly_dpk', [
                'Minute of POSISI',
                'MBNAME',
                'MB Name',
                'PRODUK',
            ]);
            $this->fail('Header Hourly DPK yang tidak lengkap seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Kolom wajib yang belum ditemukan: BRNAME, SEGMEN2, SALDO', $e->getMessage());
            $this->assertStringContainsString('Kolom terpetakan ganda/ambigu: MBNAME (MBNAME, MB Name)', $e->getMessage());
            $this->assertStringContainsString('Header yang terbaca: Minute of POSISI, MBNAME, MB Name, PRODUK', $e->getMessage());
        }
    }

    public function test_hourly_dpk_promotes_single_bri_segmen_header_but_keeps_distinct_segment_columns(): void
    {
        $strategy = app(\App\Services\Import\Strategies\HourlyDpkImportStrategy::class);

        $this->assertSame(
            ['posisi', 'mbname', 'brname', 'produk', 'segmen2', 'saldo'],
            $strategy->transformHeaders(['POSISI', 'MBNAME', 'BRNAME', 'PRODUK', 'SEGMEN', 'SALDO'])
        );
        $this->assertSame(
            ['posisi', 'mbname', 'brname', 'segmen', 'segmen2', 'produk', 'saldo'],
            $strategy->transformHeaders(['POSISI', 'MBNAME', 'BRNAME', 'SEGMEN', 'SEGMEN2', 'PRODUK', 'SALDO'])
        );
    }

    public function test_hourly_dpk_preview_accepts_minute_of_posisi_display_header(): void
    {
        $controller = new ImportExcelController();

        $method = new ReflectionMethod(ImportExcelController::class, 'assertValidHourlyDpkHeaders');
        $method->setAccessible(true);

        $method->invoke($controller, 'hourly_dpk', [
            'MBNAME',
            'BRNAME',
            'SEGMEN2',
            'PRODUK',
            'Saldo',
            'Minute of POSISI',
        ]);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'buildPreviewDisplayFilterMap');
        $mapMethod->setAccessible(true);

        $this->assertSame([1, 2, 4, 3, 5, 0], $mapMethod->invoke($controller, [
            'MBNAME',
            'BRNAME',
            'SEGMEN2',
            'PRODUK',
            'Saldo',
            'Minute of POSISI',
        ], [
            'Minute of POSISI',
            'MBNAME',
            'BRNAME',
            'PRODUK',
            'SEGMEN2',
            'Saldo',
        ]));
    }

    public function test_hourly_dpk_direct_load_plan_detects_hourly_replace_slot(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'posisi',
                    'posisi_jam',
                    'mbname',
                    'brname',
                    'segmen2',
                    'produk',
                    'saldo',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [
                    'mbname' => ['is_textual' => true, 'max_length' => 150],
                    'brname' => ['is_textual' => true, 'max_length' => 150],
                    'segmen2' => ['is_textual' => true, 'max_length' => 50],
                    'produk' => ['is_textual' => true, 'max_length' => 100],
                    'posisi_jam' => [],
                    'saldo' => ['scale' => 2],
                ];
            }
        };

        $path = tempnam(sys_get_temp_dir(), 'hourly_dpk_');
        $this->assertIsString($path);

        try {
            file_put_contents($path, implode("\n", [
                'posisi,mbname,brname,produk,segmen2,saldo',
                '"June 27, 2026 at 10:00 AM","00045 -- KC Madiun(Konsolidasi-MB)","00045 -- KC Madiun","GIRO","RITEL","1000"',
            ]) . "\n");

            $method = new ReflectionMethod(ImportExcelController::class, 'buildDirectGenericCsvLoadPlan');
            $method->setAccessible(true);
            $plan = $method->invoke($controller, 'hourly_dpk', $path, [
                'posisi',
                'mbname',
                'brname',
                'produk',
                'segmen2',
                'saldo',
            ]);

            $this->assertSame(['2026-06-27'], $plan['hourly_dpk_slots']['dates']);
            $this->assertSame(['2026-06-27 10:00:00'], $plan['hourly_dpk_slots']['datetimes']);
        } finally {
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_hourly_dpk_replace_slots_delete_only_overlapping_hours(): void
    {
        Schema::dropIfExists('hourly_dpk');
        Schema::create('hourly_dpk', function (Blueprint $table): void {
            $table->date('posisi')->nullable();
            $table->dateTime('posisi_jam')->nullable();
            $table->string('mbname')->nullable();
            $table->decimal('saldo', 20, 2)->nullable();
        });

        DB::table('hourly_dpk')->insert([
            ['posisi' => '2026-06-27', 'posisi_jam' => '2026-06-27 08:00:00', 'mbname' => 'old-08', 'saldo' => 8],
            ['posisi' => '2026-06-27', 'posisi_jam' => '2026-06-27 09:00:00', 'mbname' => 'old-09', 'saldo' => 9],
            ['posisi' => '2026-06-27', 'posisi_jam' => '2026-06-27 10:00:00', 'mbname' => 'old-10', 'saldo' => 10],
        ]);

        $controller = new ImportExcelController();
        $slots = ['dates' => [], 'datetimes' => []];
        $appendMethod = new ReflectionMethod(ImportExcelController::class, 'appendHourlyDpkSlotFromMappedRow');
        $appendMethod->setAccessible(true);
        foreach ([
            ['posisi' => '2026-06-27', 'posisi_jam' => '2026-06-27 10:00:00'],
            ['posisi' => '2026-06-27', 'posisi_jam' => '2026-06-27 11:00:00'],
            ['posisi' => '2026-06-27', 'posisi_jam' => '2026-06-27 12:00:00'],
        ] as $row) {
            $appendMethod->invokeArgs($controller, [&$slots, $row]);
        }

        $deleteMethod = new ReflectionMethod(ImportExcelController::class, 'deleteExistingHourlyDpkSlotsBeforeLoad');
        $deleteMethod->setAccessible(true);
        $deleteMethod->invoke($controller, DB::connection()->getPdo(), $slots);

        $this->assertTrue(DB::table('hourly_dpk')->where('posisi_jam', '2026-06-27 08:00:00')->exists());
        $this->assertTrue(DB::table('hourly_dpk')->where('posisi_jam', '2026-06-27 09:00:00')->exists());
        $this->assertFalse(DB::table('hourly_dpk')->where('posisi_jam', '2026-06-27 10:00:00')->exists());
    }
}
