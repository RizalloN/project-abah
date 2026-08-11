<?php

namespace Tests\Feature;

use App\Services\Rka\BreakdownRkaSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncRkaBreakdownCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();
        Schema::dropAllTables();

        Schema::create('rka', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->unsignedInteger('tahun')->nullable();
            $table->string('kanca')->nullable();
            $table->string('desc_uker')->nullable();
            $table->string('mata_anggaran')->nullable();
            foreach (['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'] as $month) {
                $table->decimal($month, 20, 2)->default(0);
            }
            $table->timestamps();
        });

        $this->directory = storage_path('framework/testing/rka-sync-'.uniqid());
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_sync_replaces_only_validated_year_and_four_branch_scope(): void
    {
        $files = [];
        foreach (['Madiun', 'Magetan', 'Ngawi', 'Ponorogo'] as $branch) {
            $files[] = $this->makeWorkbook($branch);
        }

        DB::table('rka')->insert($this->existingRow('old-madiun', 2026, 'KC Madiun'));
        DB::table('rka')->insert($this->existingRow('keep-2025', 2025, 'KC Madiun'));

        $service = app(BreakdownRkaSyncService::class);
        $dryRun = $service->sync($files, 2026, false);

        $this->assertFalse($dryRun['applied']);
        $this->assertSame(8, $dryRun['source_rows']);
        $this->assertSame(2, DB::table('rka')->count());

        $oldRkaVersion = (int) Cache::get('rka_data_version', 0);
        $result = $service->sync($files, 2026, true);

        $this->assertTrue($result['applied']);
        $this->assertSame(8, $result['source_rows']);
        $this->assertSame(1, $result['replaced_rows']);
        $this->assertSame($result['source_hash'], $result['database_hash']);
        $this->assertSame(8, DB::table('rka')->where('tahun', 2026)->count());
        $this->assertSame(1, DB::table('rka')->where('tahun', 2025)->count());
        $this->assertSame(4, DB::table('rka')->where('tahun', 2026)->distinct()->count('kanca'));
        $this->assertSame('Target 1', DB::table('rka')->where('tahun', 2026)->orderBy('uniqueid_namareport')->value('mata_anggaran'));
        $this->assertGreaterThan($oldRkaVersion, (int) Cache::get('rka_data_version', 0));

        $versionAfterFirstApply = (int) Cache::get('rka_data_version', 0);
        $noChange = $service->sync($files, 2026, true);
        $this->assertFalse($noChange['changes_detected']);
        $this->assertFalse($noChange['applied']);
        $this->assertSame(0, $noChange['replaced_rows']);
        $this->assertSame($versionAfterFirstApply, (int) Cache::get('rka_data_version', 0));

        $changedWorkbook = IOFactory::load($files[0]);
        $changedWorkbook->getActiveSheet()->setCellValue('G2', 999);
        (new Xlsx($changedWorkbook))->save($files[0]);
        $changedWorkbook->disconnectWorksheets();

        $changedDryRun = $service->sync($files, 2026, false);
        $this->assertTrue($changedDryRun['changes_detected']);
        $this->assertFalse($changedDryRun['applied']);
        $this->assertEquals(1.0, (float) DB::table('rka')
            ->where('tahun', 2026)
            ->where('kanca', 'KC Madiun')
            ->where('mata_anggaran', 'Target 1')
            ->value('jan'));

        $changedResult = $service->sync($files, 2026, true);
        $this->assertTrue($changedResult['changes_detected']);
        $this->assertTrue($changedResult['applied']);
        $this->assertSame($changedResult['source_hash'], $changedResult['database_hash']);
        $this->assertEquals(999.0, (float) DB::table('rka')
            ->where('tahun', 2026)
            ->where('kanca', 'KC Madiun')
            ->where('mata_anggaran', 'Target 1')
            ->value('jan'));
        $this->assertGreaterThan($versionAfterFirstApply, (int) Cache::get('rka_data_version', 0));
    }

    private function makeWorkbook(string $branch): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            [
                'DESC KANWIL', 'DESC UKER', 'NO URUT', 'RKA KEY', 'MATA ANGGARAN',
                'PROGNOSA / REALISASI', 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN',
                'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC',
            ],
            ['R-KANWIL MALANG', "45-KC {$branch}", 1, 'RK-1', 'Target 1', 'RKA', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
            ['R-KANWIL MALANG', "100-UNIT {$branch}", 2, 'RK-2', 'Posisi CASA Brilink', 'RKA', 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120],
        ]);

        $path = $this->directory.DIRECTORY_SEPARATOR."BREAKDOWN_KC_{$branch}_2026.xlsx";
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /** @return array<string, mixed> */
    private function existingRow(string $id, int $year, string $branch): array
    {
        $row = [
            'uniqueid_namareport' => $id,
            'tahun' => $year,
            'kanca' => $branch,
            'desc_uker' => $branch,
            'mata_anggaran' => 'Old target',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        foreach (['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'] as $month) {
            $row[$month] = 0;
        }

        return $row;
    }
}
