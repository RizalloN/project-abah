<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerRkaManualKancaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('rka', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('desc_kanwil')->nullable();
            $table->string('desc_uker')->nullable();
            $table->unsignedInteger('tahun')->nullable();
            $table->string('kanca')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_bulk_load_columns_include_manual_rka_kanca_value(): void
    {
        session(['excel_manual_kanca' => 'KC Ponorogo']);

        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return match ($tableName) {
                    'rka' => [
                        'uniqueid_namareport',
                        'desc_kanwil',
                        'desc_uker',
                        'kanca',
                        'created_at',
                        'updated_at',
                    ],
                    default => [],
                };
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $method = new ReflectionMethod(ImportExcelController::class, 'buildBulkLoadColumns');
        $method->setAccessible(true);
        $columns = $method->invoke($controller, 'rka', ['DESC_KANWIL', 'DESC_UKER'], []);

        $this->assertContains('kanca', array_map('strtolower', $columns));
        $this->assertSame([
            'uniqueid_namareport',
            'created_at',
            'updated_at',
            'desc_kanwil',
            'desc_uker',
            'kanca',
        ], array_map('strtolower', $columns));
    }

    public function test_build_import_context_uses_manual_kanca_from_queue_state_when_session_is_unavailable(): void
    {
        session()->forget('excel_manual_kanca');
        session()->forget('excel_manual_periode');

        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return ['uniqueid_namareport', 'desc_kanwil', 'desc_uker', 'tahun', 'kanca', 'created_at', 'updated_at'];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $method = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $method->setAccessible(true);
        $context = $method->invoke($controller, 'rka', ['DESC_KANWIL', 'DESC_UKER'], [], [
            'manual_kanca' => 'KC Madiun',
            'manual_periode' => '2026',
        ]);

        $this->assertSame(['kanca' => 'KC Madiun', 'tahun' => 2026], $context['manual_column_values']);
    }

    public function test_build_import_context_uses_derived_rka_values_when_manual_values_missing(): void
    {
        session()->forget('excel_manual_kanca');
        session()->forget('excel_manual_periode');

        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return ['uniqueid_namareport', 'desc_kanwil', 'desc_uker', 'tahun', 'kanca', 'created_at', 'updated_at'];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $method = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $method->setAccessible(true);
        $context = $method->invoke($controller, 'rka', ['DESC_KANWIL', 'DESC_UKER'], [], [
            'derived_kanca' => 'KC Madiun',
            'derived_tahun' => 2026,
        ]);

        $this->assertSame(['kanca' => 'KC Madiun', 'tahun' => 2026], $context['manual_column_values']);
    }

    public function test_manual_kanca_is_injected_into_rka_preview_payload_and_source_headers(): void
    {
        session(['excel_manual_kanca' => 'KC Ponorogo']);
        session(['excel_manual_periode' => '2026']);

        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'desc_kanwil',
                    'tahun',
                    'kanca',
                    'desc_uker',
                    'rka_key',
                    'mata_anggaran',
                    'jan',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $method = new ReflectionMethod(ImportExcelController::class, 'applyManualPreviewColumns');
        $method->setAccessible(true);

        $payload = $method->invoke($controller, 'rka', [
            'headers' => ['desc_kanwil', 'desc_uker'],
            'formattedUniqueValues' => [[], []],
            'preview' => [[
                'desc_kanwil' => 'R-KANWIL MALANG',
                'desc_uker' => '3888-UNIT SLEKO MADIUN',
            ]],
        ], ['desc_kanwil', 'desc_uker']);

        $this->assertSame(['desc_kanwil', 'tahun', 'kanca', 'desc_uker'], array_map('strtolower', $payload['headers']));
        $this->assertSame('KC Ponorogo', $payload['preview'][0]['kanca']);
        $this->assertSame(2026, $payload['preview'][0]['tahun']);
        $this->assertSame(['desc_kanwil', 'tahun', 'kanca', 'desc_uker'], array_map('strtolower', $payload['sourceHeaders']));
    }

    public function test_rka_manual_kanca_can_be_applied_after_load_using_batch_prefix(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return ['uniqueid_namareport', 'desc_kanwil', 'tahun', 'kanca', 'desc_uker', 'created_at', 'updated_at'];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        DB::table('rka')->insert([
            [
                'uniqueid_namareport' => 'imp_testprefix_111_DLD',
                'desc_kanwil' => 'R-KANWIL MALANG',
                'kanca' => null,
                'desc_uker' => '3888-UNIT SLEKO MADIUN',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uniqueid_namareport' => 'imp_testprefix_222_DLD',
                'desc_kanwil' => 'R-KANWIL MALANG',
                'kanca' => null,
                'desc_uker' => '6339-UNIT ALOON-ALOON MADIUN',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uniqueid_namareport' => 'imp_otherprefix_999_DLD',
                'desc_kanwil' => 'R-KANWIL MALANG',
                'kanca' => null,
                'desc_uker' => 'OTHER ROW',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $method = new ReflectionMethod(ImportExcelController::class, 'applyManualColumnValuesAfterLoad');
        $method->setAccessible(true);
        $affected = $method->invoke($controller, 'rka', [
            'manual_column_values' => ['kanca' => 'KC Ponorogo', 'tahun' => 2026],
            'unique_id_col' => 'uniqueid_namareport',
            'unique_id_prefix' => 'imp_testprefix_',
            'table_columns_by_lower' => ['kanca' => 'kanca', 'tahun' => 'tahun'],
        ], 2);

        $this->assertSame(2, $affected);
        $this->assertSame('KC Ponorogo', DB::table('rka')->where('uniqueid_namareport', 'imp_testprefix_111_DLD')->value('kanca'));
        $this->assertSame('KC Ponorogo', DB::table('rka')->where('uniqueid_namareport', 'imp_testprefix_222_DLD')->value('kanca'));
        $this->assertSame(2026, DB::table('rka')->where('uniqueid_namareport', 'imp_testprefix_111_DLD')->value('tahun'));
        $this->assertSame(2026, DB::table('rka')->where('uniqueid_namareport', 'imp_testprefix_222_DLD')->value('tahun'));
        $this->assertNull(DB::table('rka')->where('uniqueid_namareport', 'imp_otherprefix_999_DLD')->value('kanca'));
        $this->assertNull(DB::table('rka')->where('uniqueid_namareport', 'imp_otherprefix_999_DLD')->value('tahun'));
    }

    public function test_rka_manual_kanca_apply_invalidates_optimized_lookup_cache(): void
    {
        Cache::forever('rka_data_version', 123);

        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return ['uniqueid_namareport', 'desc_kanwil', 'tahun', 'kanca', 'desc_uker', 'created_at', 'updated_at'];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        DB::table('rka')->insert([
            'uniqueid_namareport' => 'imp_cacheprefix_111_DLD',
            'desc_kanwil' => 'R-KANWIL MALANG',
            'kanca' => null,
            'desc_uker' => '3888-UNIT SLEKO MADIUN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $method = new ReflectionMethod(ImportExcelController::class, 'applyManualColumnValuesAfterLoad');
        $method->setAccessible(true);
        $method->invoke($controller, 'rka', [
            'manual_column_values' => ['kanca' => 'KC Madiun', 'tahun' => 2026],
            'unique_id_col' => 'uniqueid_namareport',
            'unique_id_prefix' => 'imp_cacheprefix_',
            'table_columns_by_lower' => ['kanca' => 'kanca', 'tahun' => 'tahun'],
        ], 1);

        $this->assertNotSame(123, Cache::get('rka_data_version'));
        $this->assertIsInt(Cache::get('rka_data_version'));
    }

    public function test_rka_manual_verification_fails_fast_and_removes_incomplete_batch(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return ['uniqueid_namareport', 'desc_kanwil', 'tahun', 'kanca', 'desc_uker', 'created_at', 'updated_at'];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        DB::table('rka')->insert([
            [
                'uniqueid_namareport' => 'imp_partialprefix_111_DLD',
                'desc_kanwil' => 'R-KANWIL MALANG',
                'kanca' => null,
                'desc_uker' => '3888-UNIT SLEKO MADIUN',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uniqueid_namareport' => 'imp_safeother_999_DLD',
                'desc_kanwil' => 'R-KANWIL MALANG',
                'kanca' => null,
                'desc_uker' => 'OTHER ROW',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $method = new ReflectionMethod(ImportExcelController::class, 'applyManualColumnValuesAfterLoad');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Import RKA dibatalkan');

        try {
            $method->invoke($controller, 'rka', [
                'manual_column_values' => ['kanca' => 'KC Madiun', 'tahun' => 2026],
                'unique_id_col' => 'uniqueid_namareport',
                'unique_id_prefix' => 'imp_partialprefix_',
                'table_columns_by_lower' => ['kanca' => 'kanca', 'tahun' => 'tahun'],
            ], 2);
        } finally {
            $this->assertSame(0, DB::table('rka')->where('uniqueid_namareport', 'like', 'imp_partialprefix_%')->count());
            $this->assertSame(1, DB::table('rka')->where('uniqueid_namareport', 'imp_safeother_999_DLD')->count());
        }
    }

    public function test_rka_insert_rows_use_batch_prefix_on_uniqueid(): void
    {
        session(['excel_manual_kanca' => 'KC Ponorogo']);

        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return ['uniqueid_namareport', 'desc_kanwil', 'tahun', 'kanca', 'desc_uker', 'created_at', 'updated_at'];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'rka', ['DESC_KANWIL', 'DESC_UKER'], [], [
            'manual_kanca' => 'KC Ponorogo',
            'manual_periode' => '2026',
        ]);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke($controller, [
            'R-KANWIL MALANG',
            '3888-UNIT SLEKO MADIUN',
        ], ['DESC_KANWIL', 'DESC_UKER'], $context, '2026-04-15 10:00:00');

        $this->assertIsArray($row);
        $this->assertSame('KC Ponorogo', $row['kanca']);
        $this->assertSame(2026, $row['tahun']);
        $this->assertNotEmpty($row['uniqueid_namareport']);
        $this->assertStringStartsWith('uuid_rka_', $row['uniqueid_namareport']);
        $this->assertStringNotContainsString('_DLD', $row['uniqueid_namareport']);
    }

    public function test_rka_row_derived_kanca_uses_desc_uker_when_manual_kanca_is_missing(): void
    {
        session()->forget('excel_manual_kanca');
        session(['excel_manual_periode' => '2026']);

        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return ['uniqueid_namareport', 'desc_kanwil', 'tahun', 'kanca', 'desc_uker', 'created_at', 'updated_at'];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'rka', ['DESC_KANWIL', 'DESC_UKER'], [], []);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke($controller, [
            'R-KANWIL MALANG',
            '45-KC Madiun',
        ], ['DESC_KANWIL', 'DESC_UKER'], $context, '2026-04-15 10:00:00');

        $this->assertSame('KC Madiun', $row['kanca']);
        $this->assertSame(2026, $row['tahun']);
    }

    public function test_rka_header_detection_does_not_treat_posisi_budget_row_as_header(): void
    {
        $controller = new ImportExcelController();
        $method = new ReflectionMethod(ImportExcelController::class, 'detectHeaderIndex');
        $method->setAccessible(true);

        $rows = [
            [
                'DESC KANWIL', 'DESC UKER', 'NO URUT', 'RKA KEY', 'MATA ANGGARAN',
                'PROGNOSA / REALISASI', 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN',
                'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC',
            ],
            [
                'R-KANWIL MALANG', '45-KC Madiun', '183', '7002', 'Posisi CASA Brilink',
                'Realisasi', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12,
            ],
        ];

        $this->assertSame(0, $method->invoke($controller, $rows, 'rka'));
    }

    public function test_rka_python_header_candidate_must_contain_complete_rka_columns(): void
    {
        $controller = new ImportExcelController();
        $method = new ReflectionMethod(ImportExcelController::class, 'isDetectedHeaderValidForTable');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, [
            'R-KANWIL MALANG', '45-KC Madiun', '183', '7002', 'Posisi CASA Brilink',
        ], 'rka'));
        $this->assertTrue($method->invoke($controller, [
            'DESC KANWIL', 'DESC UKER', 'NO URUT', 'RKA KEY', 'MATA ANGGARAN',
            'PROGNOSA / REALISASI', 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN',
            'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC',
        ], 'rka'));
    }

    public function test_rka_duplicate_guard_is_scoped_by_business_year(): void
    {
        DB::table('rka')->insert([
            'uniqueid_namareport' => 'rka-madiun-2025',
            'tahun' => 2025,
            'kanca' => 'KC Madiun',
        ]);
        session([
            'excel_manual_kanca' => 'KC Madiun',
            'excel_manual_periode' => '2026',
        ]);

        $controller = new ImportExcelController();
        $method = new ReflectionMethod(ImportExcelController::class, 'assertDuplicateGuard');
        $method->setAccessible(true);
        $method->invoke($controller, 'rka');

        DB::table('rka')->insert([
            'uniqueid_namareport' => 'rka-madiun-2026',
            'tahun' => 2026,
            'kanca' => 'KC Madiun',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tahun <b>2026</b>');
        $method->invoke($controller, 'rka');
    }
}
