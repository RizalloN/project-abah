<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\Gi405RecDhImportExcelController;
use App\Http\Controllers\Import\ImportExcelController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerGi405RecDhTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('gi405_recovery');
        Schema::dropIfExists('referensi_uker');

        parent::tearDown();
    }

    public function test_gi405_recovery_rows_are_normalized_before_insert(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'periode',
                    'kode_uker',
                    'pendapatan_koreksi_ppap_dr_angsuran_ph',
                    'nama_uker',
                    'created_at',
                    'updated_at',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $headers = [
            'Periode',
            'KODE',
            'Pendapatan Koreksi PPAP-dr Angsuran PH',
        ];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'gi405_recovery', $headers);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke($controller, [
            '01/05/2026',
            45,
            '-164,937.50',
        ], $headers, $context, '2026-05-01 07:00:00');

        $this->assertIsArray($row);
        $this->assertSame('2026-05-01', $row['periode']);
        $this->assertSame('00045', $row['kode_uker']);
        $this->assertStringStartsWith('uuid_gi405_recovery_', $row['uniqueid_namareport']);
        $this->assertSame('-164937.50', $row['pendapatan_koreksi_ppap_dr_angsuran_ph']);
    }

    public function test_gi405_recovery_import_uses_referensi_uker_for_nama_uker(): void
    {
        $this->createGi405RecoveryTestTables();

        DB::table('referensi_uker')->insert([
            ['kode_uker' => '00045', 'nama_uker' => '00045 -- KC Madiun'],
            ['kode_uker' => '00552', 'nama_uker' => '00552 -- KCP Caruban'],
        ]);

        $csvPath = tempnam(sys_get_temp_dir(), 'gi405_recovery_');
        $handle = fopen($csvPath, 'wb');
        fputcsv($handle, ['Periode', 'KODE', 'Pendapatan Koreksi PPAP-dr Angsuran PH']);
        fputcsv($handle, ['19/01/2026', '45', '-61806903']);
        fputcsv($handle, ['2026-01-19', '552', '-150608259.25']);
        fclose($handle);

        $controller = new class extends Gi405RecDhImportExcelController {
            public function importForTest(string $csvPath): bool
            {
                return $this->processStagedCsvStream(
                    static function (): void {
                    },
                    $csvPath,
                    'gi405_recovery',
                    [],
                    ['Periode', 'KODE', 'Pendapatan Koreksi PPAP-dr Angsuran PH'],
                    0,
                    2,
                    ','
                );
            }
        };

        try {
            $this->assertTrue($controller->importForTest($csvPath));
        } finally {
            @unlink($csvPath);
        }

        $this->assertSame(2, DB::table('gi405_recovery')->count());

        $first = DB::table('gi405_recovery')->where('kode_uker', '00045')->first();
        $this->assertSame('2026-01-19', $first->periode);
        $this->assertSame('00045 -- KC Madiun', $first->nama_uker);
        $this->assertSame('-61806903.00', number_format((float) $first->pendapatan_koreksi_ppap_dr_angsuran_ph, 2, '.', ''));

        $second = DB::table('gi405_recovery')->where('kode_uker', '00552')->first();
        $this->assertSame('00552 -- KCP Caruban', $second->nama_uker);
        $this->assertSame('-150608259.25', number_format((float) $second->pendapatan_koreksi_ppap_dr_angsuran_ph, 2, '.', ''));
    }

    private function createGi405RecoveryTestTables(): void
    {
        Schema::create('gi405_recovery', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->string('kode_uker', 20)->nullable();
            $table->decimal('pendapatan_koreksi_ppap_dr_angsuran_ph', 24, 2)->default(0);
            $table->string('nama_uker', 180)->nullable();
            $table->timestamps();
        });

        Schema::create('referensi_uker', function (Blueprint $table): void {
            $table->string('kode_uker', 5)->primary();
            $table->string('nama_uker', 180);
        });
    }
}
