<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportExcelControllerSnapshotReplaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->string('cabang1')->nullable();
        });
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('framework/testing/daily_loan_snapshot_replace_test.csv'));
        parent::tearDown();
    }

    public function test_fast_path_replaces_existing_daily_loan_snapshot_rows_before_load(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'A-1',
            'periode' => '2026-04-04',
            'cabang1' => 'KC A',
        ]);
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'A-2',
            'periode' => '2026-04-04',
            'cabang1' => 'KC B',
        ]);
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'B-1',
            'periode' => '2026-04-03',
            'cabang1' => 'KC C',
        ]);

        $controller = new class extends ImportExcelController {
            public function collectValues(string $path): array
            {
                return $this->collectCsvNormalizedValuesForHeaders($path, ['PERIODE']);
            }

            public function deleteValues(array $values): int
            {
                return $this->deleteRowsByColumnValues('daily_loan_dinamis', 'periode', $values);
            }
        };

        $csvPath = storage_path('framework/testing/daily_loan_snapshot_replace_test.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'PERIODE,KODE_KANWIL1,KANWIL1',
            '04-04-2026,R,KANWIL MALANG',
            '04-04-2026,R,KANWIL MALANG',
        ]));

        $values = $controller->collectValues($csvPath);
        $deleted = $controller->deleteValues($values);

        $this->assertSame(['2026-04-04'], $values);
        $this->assertSame(2, $deleted);
        $this->assertSame(1, DB::table('daily_loan_dinamis')->count());
        $this->assertSame('2026-04-03', DB::table('daily_loan_dinamis')->value('periode'));
    }
}
