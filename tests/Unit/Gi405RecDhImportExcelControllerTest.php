<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\Gi405RecDhImportExcelController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

        Schema::create('gi405_recovery', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->string('kode_uker', 20)->nullable();
            $table->decimal('pendapatan_koreksi_ppap_dr_angsuran_ph', 24, 2)->nullable();
            $table->string('nama_uker', 180)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_collect_business_keys_reports_duplicate_recovery_samples(): void
    {
        $csvPath = tempnam(sys_get_temp_dir(), 'gi405_dup_');
        file_put_contents($csvPath, implode("\n", [
            'Periode,KODE,Pendapatan Koreksi PPAP-dr Angsuran PH',
            '01/05/2026,45,-164937.50',
            '01/05/2026,45,-164937.50',
            '01/05/2026,49,-164937.50',
        ]));

        $controller = new Gi405RecDhImportExcelController();
        $method = new ReflectionMethod(Gi405RecDhImportExcelController::class, 'extractGi405BusinessKeysFromCsv');
        $method->setAccessible(true);
        $result = $method->invoke($controller, $csvPath);

        @unlink($csvPath);

        $this->assertSame(['2026-05-01 / 00045'], $result['duplicates_in_file']);
        $this->assertNotEmpty($result['duplicate_row_samples']);
        $this->assertStringContainsString('baris 2 & 3', $result['duplicate_row_samples'][0]);
    }

    public function test_gi405_excel_staging_uses_recovery_format_and_skips_blank_rows(): void
    {
        $xlsxPath = tempnam(sys_get_temp_dir(), 'gi405_sheet_') . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('RECOVERY DH');
        $sheet->fromArray([
            ['Periode', 'KODE', 'Pendapatan Koreksi PPAP-dr Angsuran PH  '],
            ['', '', ''],
            ['01/05/2026', 45, '-164,937.50'],
        ]);

        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        $controller = new Gi405RecDhImportExcelController();
        $method = new ReflectionMethod(Gi405RecDhImportExcelController::class, 'stageGi405WorkbookSheetToCsv');
        $method->setAccessible(true);
        $stage = $method->invoke($controller, $xlsxPath);

        @unlink($xlsxPath);

        $handle = fopen($stage['absolute_path'], 'r');
        $headers = fgetcsv($handle);
        $row = fgetcsv($handle);
        $end = fgetcsv($handle);
        fclose($handle);

        $this->assertSame(['Periode', 'KODE', 'Pendapatan Koreksi PPAP-dr Angsuran PH'], $headers);
        $this->assertSame('01/05/2026', (string) $row[0]);
        $this->assertSame('45', (string) $row[1]);
        $this->assertSame('-164,937.50', (string) $row[2]);
        $this->assertFalse($end);
    }
}
