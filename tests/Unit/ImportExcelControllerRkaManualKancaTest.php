<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use Illuminate\Database\Schema\Blueprint;
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

        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return ['uniqueid_namareport', 'desc_kanwil', 'desc_uker', 'kanca', 'created_at', 'updated_at'];
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
        ]);

        $this->assertSame(['kanca' => 'KC Madiun'], $context['manual_column_values']);
    }

    public function test_manual_kanca_is_injected_into_rka_preview_payload_and_source_headers(): void
    {
        session(['excel_manual_kanca' => 'KC Ponorogo']);

        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'desc_kanwil',
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

        $this->assertSame(['desc_kanwil', 'kanca', 'desc_uker'], array_map('strtolower', $payload['headers']));
        $this->assertSame('KC Ponorogo', $payload['preview'][0]['kanca']);
        $this->assertSame(['desc_kanwil', 'kanca', 'desc_uker'], array_map('strtolower', $payload['sourceHeaders']));
    }

    public function test_rka_manual_kanca_can_be_applied_after_load_using_batch_prefix(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return ['uniqueid_namareport', 'desc_kanwil', 'kanca', 'desc_uker', 'created_at', 'updated_at'];
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
            'manual_column_values' => ['kanca' => 'KC Ponorogo'],
            'unique_id_col' => 'uniqueid_namareport',
            'unique_id_prefix' => 'imp_testprefix_',
            'table_columns_by_lower' => ['kanca' => 'kanca'],
        ], 2);

        $this->assertSame(2, $affected);
        $this->assertSame('KC Ponorogo', DB::table('rka')->where('uniqueid_namareport', 'imp_testprefix_111_DLD')->value('kanca'));
        $this->assertSame('KC Ponorogo', DB::table('rka')->where('uniqueid_namareport', 'imp_testprefix_222_DLD')->value('kanca'));
        $this->assertNull(DB::table('rka')->where('uniqueid_namareport', 'imp_otherprefix_999_DLD')->value('kanca'));
    }

    public function test_rka_insert_rows_use_batch_prefix_on_uniqueid(): void
    {
        session(['excel_manual_kanca' => 'KC Ponorogo']);

        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return ['uniqueid_namareport', 'desc_kanwil', 'kanca', 'desc_uker', 'created_at', 'updated_at'];
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
        ]);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke($controller, [
            'R-KANWIL MALANG',
            '3888-UNIT SLEKO MADIUN',
        ], ['DESC_KANWIL', 'DESC_UKER'], $context, '2026-04-15 10:00:00');

        $this->assertIsArray($row);
        $this->assertSame('KC Ponorogo', $row['kanca']);
        $this->assertNotEmpty($row['uniqueid_namareport']);
        $this->assertStringStartsWith($context['unique_id_prefix'] . '_', $row['uniqueid_namareport']);
        $this->assertStringEndsWith('_DLD', $row['uniqueid_namareport']);
    }
}
