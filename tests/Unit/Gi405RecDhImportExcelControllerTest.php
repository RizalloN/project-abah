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

        Schema::create('gi405_singlerow', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->string('branch', 20)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('posting_control', 30)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('c_c', 20)->nullable();
            $table->string('p_c', 20)->nullable();
            $table->string('f_c', 20)->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('begining_balance', 24, 2)->nullable();
            $table->decimal('equivalents_idr', 24, 2)->nullable();
            $table->decimal('equivalents_usd', 24, 2)->nullable();
            $table->decimal('today_debit', 24, 2)->nullable();
            $table->decimal('today_credit', 24, 2)->nullable();
            $table->decimal('ending_balance', 24, 2)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_collect_business_keys_reports_duplicate_single_row_samples(): void
    {
        $csvPath = tempnam(sys_get_temp_dir(), 'gi405_dup_');
        file_put_contents($csvPath, implode("\n", [
            'PERIODE,BRANCH,CURRENCY,POSTING CONTROL,ACCOUNT NUMBER,C/C,P/C,F/C,DESCRIPTION,BEGINING BALANCE,EQUIVALENTS IDR,EQUIVALENTS USD,TODAY DEBIT,TODAY CREDIT,ENDING BALANCE',
            '01/05/2026,45,AED,*POST,100010992000,,,AED,Kas - Money Changer,35.00,164937.50,9.52,0.00,0.00,35.00',
            '01/05/2026,45,AED,*POST,100010992000,,,AED,Kas - Money Changer,35.00,164937.50,9.52,0.00,0.00,35.00',
            '01/05/2026,49,AED,*POST,100010992000,,,AED,Kas - Money Changer,35.00,164937.50,9.52,0.00,0.00,35.00',
        ]));

        $controller = new Gi405RecDhImportExcelController();
        $method = new ReflectionMethod(Gi405RecDhImportExcelController::class, 'extractGi405BusinessKeysFromCsv');
        $method->setAccessible(true);
        $result = $method->invoke($controller, $csvPath);

        @unlink($csvPath);

        $this->assertSame(['2026-05-01 / 45 / *POST / 100010992000'], $result['duplicates_in_file']);
        $this->assertNotEmpty($result['duplicate_row_samples']);
        $this->assertStringContainsString('baris 2 & 3', $result['duplicate_row_samples'][0]);
    }

    public function test_gi405_excel_staging_uses_single_row_format_and_skips_blank_rows(): void
    {
        $xlsxPath = tempnam(sys_get_temp_dir(), 'gi405_sheet_') . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('GI405Singlerow');
        $sheet->fromArray([
            ['PERIODE', 'BRANCH', 'CURRENCY', 'POSTING CONTROL', 'ACCOUNT NUMBER', 'C/C', 'P/C', 'F/C', 'DESCRIPTION', 'BEGINING BALANCE', 'EQUIVALENTS IDR', 'EQUIVALENTS USD', 'TODAY DEBIT', 'TODAY CREDIT', 'ENDING BALANCE'],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['01/05/2026', 45, 'AED', '*POST', '100010992000', '', '', 'AED', 'Kas - Money Changer', '35.00', '164,937.50', '9.52', '0.00', '0.00', '35.00'],
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

        $this->assertSame(['PERIODE', 'BRANCH', 'CURRENCY', 'POSTING CONTROL', 'ACCOUNT NUMBER', 'C/C', 'P/C', 'F/C', 'DESCRIPTION', 'BEGINING BALANCE', 'EQUIVALENTS IDR', 'EQUIVALENTS USD', 'TODAY DEBIT', 'TODAY CREDIT', 'ENDING BALANCE'], $headers);
        $this->assertSame('01/05/2026', (string) $row[0]);
        $this->assertSame('45', (string) $row[1]);
        $this->assertSame('*POST', (string) $row[3]);
        $this->assertSame('100010992000', (string) $row[4]);
        $this->assertFalse($end);
    }
}
