<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\Gi405RecDhImportExcelController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class Gi405RecDhImportExcelControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('gi405_rec_dh', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('kode', 20);
            $table->decimal('pendapatan_koreksi_ppap_dr_angsuran_ph', 20, 2)->nullable();
            $table->decimal('recovery_non_klaim', 20, 2)->nullable();
            $table->string('kc_konsol', 150)->nullable();
            $table->string('nama_uker', 150)->nullable();
            $table->string('segmen', 50)->nullable();
            $table->date('tanggal')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('import_jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('status')->nullable();
            $table->integer('total_success')->default(0);
            $table->integer('total_failed')->default(0);
            $table->integer('total_files')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->text('job_context')->nullable();
            $table->string('job_fingerprint')->nullable();
        });
    }

    public function test_collect_business_keys_reports_duplicate_row_samples(): void
    {
        $csvPath = tempnam(sys_get_temp_dir(), 'gi405_dup_');
        file_put_contents($csvPath, implode("\n", [
            'KODE,Tanggal',
            '45,19 Januari 2026',
            '45,19 Januari 2026',
            '46,20 Januari 2026',
        ]));

        $controller = new Gi405RecDhImportExcelController();
        $method = new ReflectionMethod(Gi405RecDhImportExcelController::class, 'extractGi405BusinessKeysFromCsv');
        $method->setAccessible(true);
        $result = $method->invoke($controller, $csvPath);

        @unlink($csvPath);

        $this->assertSame(['2026-01-19 / 00045'], $result['duplicates_in_file']);
        $this->assertNotEmpty($result['duplicate_row_samples']);
        $this->assertStringContainsString('baris 2 & 3', $result['duplicate_row_samples'][0]);
    }

    private function makeController(): Gi405RecDhImportExcelController
    {
        return new class extends Gi405RecDhImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'kode',
                    'pendapatan_koreksi_ppap_dr_angsuran_ph',
                    'recovery_non_klaim',
                    'kc_konsol',
                    'nama_uker',
                    'segmen',
                    'tanggal',
                    'created_at',
                    'updated_at',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };
    }

    public function test_gi405_staged_import_persists_negative_pendapatan_values(): void
    {
        $csvPath = tempnam(sys_get_temp_dir(), 'gi405_ok_');
        file_put_contents($csvPath, implode("\n", [
            'KODE,"Pendapatan Koreksi PPAP-dr Angsuran PH","Recovery Non Klaim","KC Konsol","Nama Uker","Segmen",Tanggal',
            '45,-61806903,61806903,"00045 -- KC Madiun (Konsolidasi-MB)","00045 -- KC Madiun",Ritel,"19 Januari 2026"',
            '46,-3029000,3029000,"00046 -- KC Madiun (Konsolidasi-MB)","00046 -- KC Madiun",Ritel,"19 Januari 2026"',
        ]));

        $events = [];
        $controller = $this->makeController();
        $method = new ReflectionMethod(Gi405RecDhImportExcelController::class, 'processStagedCsvStream');
        $method->setAccessible(true);

        $result = $method->invoke(
            $controller,
            function (string $event, array $payload) use (&$events): void {
                $events[] = compact('event', 'payload');
            },
            $csvPath,
            'gi405_rec_dh',
            [],
            [
                'KODE',
                'Pendapatan Koreksi PPAP-dr Angsuran PH',
                'Recovery Non Klaim',
                'KC Konsol',
                'Nama Uker',
                'Segmen',
                'Tanggal',
            ],
            0,
            2,
            ',',
            false,
            null
        );

        @unlink($csvPath);

        $this->assertTrue($result);
        $this->assertSame(2, DB::table('gi405_rec_dh')->count());
        $this->assertSame('-61806903', (string) DB::table('gi405_rec_dh')->orderBy('kode')->value('pendapatan_koreksi_ppap_dr_angsuran_ph'));
        $this->assertSame('61806903', (string) DB::table('gi405_rec_dh')->orderBy('kode')->value('recovery_non_klaim'));
        $this->assertStringStartsWith(
            'uuid_405RDH_',
            (string) DB::table('gi405_rec_dh')->orderBy('kode')->value('uniqueid_namareport')
        );
        $this->assertSame('complete', $events[array_key_last($events)]['event']);
    }

    public function test_gi405_staged_import_returns_diagnostic_error_samples_when_source_is_invalid(): void
    {
        $csvPath = tempnam(sys_get_temp_dir(), 'gi405_bad_');
        file_put_contents($csvPath, implode("\n", [
            'KODE,"Pendapatan Koreksi PPAP-dr Angsuran PH","Recovery Non Klaim","KC Konsol","Nama Uker","Segmen",Tanggal',
            '45,abc,61806903,"00045 -- KC Madiun (Konsolidasi-MB)","00045 -- KC Madiun",Ritel,"19 Januari 2026"',
            '45,-100,100,"00045 -- KC Madiun (Konsolidasi-MB)","00045 -- KC Madiun",Ritel,"19 Januari 2026"',
        ]));

        $events = [];
        $controller = $this->makeController();
        $method = new ReflectionMethod(Gi405RecDhImportExcelController::class, 'processStagedCsvStream');
        $method->setAccessible(true);

        $result = $method->invoke(
            $controller,
            function (string $event, array $payload) use (&$events): void {
                $events[] = compact('event', 'payload');
            },
            $csvPath,
            'gi405_rec_dh',
            [],
            [
                'KODE',
                'Pendapatan Koreksi PPAP-dr Angsuran PH',
                'Recovery Non Klaim',
                'KC Konsol',
                'Nama Uker',
                'Segmen',
                'Tanggal',
            ],
            0,
            2,
            ',',
            false,
            null
        );

        @unlink($csvPath);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('gi405_rec_dh')->count());
        $this->assertSame('error', $events[array_key_last($events)]['event']);
        $this->assertStringContainsString('Contoh baris error', $events[array_key_last($events)]['payload']['message']);
        $this->assertStringContainsString('baris 2', $events[array_key_last($events)]['payload']['message']);
        $this->assertStringContainsString('abc', $events[array_key_last($events)]['payload']['message']);
    }
}
