<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportKurMikroController;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportKurMikroControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('performance_kurkecil_mikro', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('kanca', 150)->nullable();
            $table->string('pn', 50)->nullable();
            $table->string('nama', 255)->nullable();
            $table->string('bc_uker', 150)->nullable();
            $table->string('uker', 255)->nullable();
            $table->date('tanggal_bl')->nullable();
            $table->string('ket', 100)->nullable();
            $table->unsignedInteger('lt_250_juta_deb')->nullable();
            $table->decimal('lt_250_juta_pct', 20, 16)->nullable();
            $table->decimal('lt_250_juta_rp_juta', 20, 2)->nullable();
            $table->unsignedInteger('gt_250_juta_deb')->nullable();
            $table->decimal('gt_250_juta_pct', 20, 16)->nullable();
            $table->decimal('gt_250_juta_rp_juta', 20, 2)->nullable();
            $table->unsignedInteger('total_deb')->nullable();
            $table->decimal('total_rp_juta', 20, 2)->nullable();
            $table->timestamps();
        });
    }

    public function test_it_imports_kur_mikro_workbook_and_preserves_source_values(): void
    {
        $path = $this->createWorkbook([
            [
                'row' => 7,
                'no' => 1,
                'kanca' => 'BANYUWANGI',
                'pn' => '00123',
                'nama' => 'Bagus Suhendra',
                'bc_uker' => '007',
                'uker' => 'KC Banyuwangi',
                'tanggal_bl' => Carbon::parse('2026-04-06'),
                'ket' => 'KC',
                'lt_250_juta_deb' => 0,
                'lt_250_juta_pct' => 0.3983050847457627,
                'lt_250_juta_rp_juta' => 15725,
                'gt_250_juta_deb' => 3,
                'gt_250_juta_pct' => 0.6016949152542372,
                'gt_250_juta_rp_juta' => 49870,
                'total_deb' => 236,
                'total_rp_juta' => 65595,
            ],
            [
                'row' => 8,
                'no' => 2,
                'kanca' => 'BATU',
                'pn' => '00045',
                'nama' => 'Mazuin Kusuma Ningtyas',
                'bc_uker' => '551',
                'uker' => 'KC BATU',
                'tanggal_bl' => Carbon::parse('2026-05-11'),
                'ket' => 'KC',
                'lt_250_juta_deb' => 0,
                'lt_250_juta_pct' => 0.0,
                'lt_250_juta_rp_juta' => 0,
                'gt_250_juta_deb' => 2,
                'gt_250_juta_pct' => 1.0,
                'gt_250_juta_rp_juta' => 700,
                'total_deb' => 2,
                'total_rp_juta' => 700,
            ],
        ]);

        try {
            $controller = new ImportKurMikroController();
            $request = Request::create('/import/kurmikro/process', 'POST', [
                'file_path' => $path,
            ]);

            $response = $controller->processImport($request);
            $payload = $response->getData(true);

            $this->assertSame('success', $payload['status']);
            $this->assertSame(2, DB::table('performance_kurkecil_mikro')->count());
            $this->assertSame('uuid_pkm_', substr((string) DB::table('performance_kurkecil_mikro')->value('uniqueid_namareport'), 0, 9));
            $this->assertSame('00123', DB::table('performance_kurkecil_mikro')->where('pn', '00123')->value('pn'));
            $this->assertSame('007', DB::table('performance_kurkecil_mikro')->where('pn', '00123')->value('bc_uker'));
            $this->assertSame('2026-04-06', DB::table('performance_kurkecil_mikro')->where('pn', '00123')->value('tanggal_bl'));
            $this->assertStringStartsWith('0.39830508474576', (string) DB::table('performance_kurkecil_mikro')->where('pn', '00123')->value('lt_250_juta_pct'));
            $this->assertStringStartsWith('0.60169491525424', (string) DB::table('performance_kurkecil_mikro')->where('pn', '00123')->value('gt_250_juta_pct'));
            $this->assertSame(2, DB::table('performance_kurkecil_mikro')->where('pn', '00045')->value('total_deb'));
        } finally {
            @unlink($path);
        }
    }

    public function test_it_rejects_invalid_workbook_headers_before_inserting_rows(): void
    {
        $path = $this->createWorkbook([
            [
                'row' => 7,
                'no' => 1,
                'kanca' => 'BANYUWANGI',
                'pn' => '00123',
                'nama' => 'Bagus Suhendra',
                'bc_uker' => '007',
                'uker' => 'KC Banyuwangi',
                'tanggal_bl' => Carbon::parse('2026-04-06'),
                'ket' => 'KC',
                'lt_250_juta_deb' => 0,
                'lt_250_juta_pct' => 0.3983050847457627,
                'lt_250_juta_rp_juta' => 15725,
                'gt_250_juta_deb' => 3,
                'gt_250_juta_pct' => 0.6016949152542372,
                'gt_250_juta_rp_juta' => 49870,
                'total_deb' => 236,
                'total_rp_juta' => 65595,
            ],
        ], false);

        try {
            $controller = new ImportKurMikroController();
            $request = Request::create('/import/kurmikro/process', 'POST', [
                'file_path' => $path,
            ]);

            $response = $controller->processImport($request);
            $payload = $response->getData(true);

            $this->assertSame('error', $payload['status']);
            $this->assertSame(0, DB::table('performance_kurkecil_mikro')->count());
        } finally {
            @unlink($path);
        }
    }

    private function createWorkbook(array $rows, bool $validHeaders = true): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PER TIERING');

        $sheet->setCellValue('A1', 'MONITORING PRODUKTIVITAS RM MIKRO PER TIERING PLAFOND');
        $sheet->setCellValue('A3', $validHeaders ? 'NO' : 'BAD');
        $sheet->setCellValue('B3', 'KANCA');
        $sheet->setCellValue('C3', 'PN');
        $sheet->setCellValue('D3', 'NAMA');
        $sheet->setCellValue('E3', 'BC UKER');
        $sheet->setCellValue('F3', 'UKER');
        $sheet->setCellValue('G3', 'TANGGAL BL');
        $sheet->setCellValue('H3', 'KET');
        $sheet->setCellValue('I3', '<250 Juta');
        $sheet->setCellValue('I4', 'Deb');
        $sheet->setCellValue('J4', '%');
        $sheet->setCellValue('K4', 'Rp.Juta');
        $sheet->setCellValue('L3', '>250 Juta');
        $sheet->setCellValue('L4', 'Deb');
        $sheet->setCellValue('M4', '%');
        $sheet->setCellValue('N4', 'Rp.Juta');
        $sheet->setCellValue('O3', 'TOTAL');
        $sheet->setCellValue('O4', 'Deb');
        $sheet->setCellValue('P4', 'Rp.Juta');
        $sheet->setCellValue('A5', 'TOTAL');
        $sheet->setCellValue('I5', 94);
        $sheet->setCellValue('J5', 0.3983050847457627);
        $sheet->setCellValue('K5', 15725);
        $sheet->setCellValue('L5', 142);
        $sheet->setCellValue('M5', 0.6016949152542372);
        $sheet->setCellValue('N5', 49870);
        $sheet->setCellValue('O5', 236);
        $sheet->setCellValue('P5', 65595);

        foreach ($rows as $row) {
            $rowNumber = $row['row'];
            $sheet->setCellValue('A' . $rowNumber, $row['no']);
            $sheet->setCellValue('B' . $rowNumber, $row['kanca']);
            $sheet->setCellValueExplicit('C' . $rowNumber, $row['pn'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('D' . $rowNumber, $row['nama']);
            $sheet->setCellValueExplicit('E' . $rowNumber, $row['bc_uker'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('F' . $rowNumber, $row['uker']);
            $sheet->setCellValue('G' . $rowNumber, ExcelDate::PHPToExcel($row['tanggal_bl']));
            $sheet->setCellValue('H' . $rowNumber, $row['ket']);
            $sheet->setCellValue('I' . $rowNumber, $row['lt_250_juta_deb']);
            $sheet->setCellValue('J' . $rowNumber, $row['lt_250_juta_pct']);
            $sheet->setCellValue('K' . $rowNumber, $row['lt_250_juta_rp_juta']);
            $sheet->setCellValue('L' . $rowNumber, $row['gt_250_juta_deb']);
            $sheet->setCellValue('M' . $rowNumber, $row['gt_250_juta_pct']);
            $sheet->setCellValue('N' . $rowNumber, $row['gt_250_juta_rp_juta']);
            $sheet->setCellValue('O' . $rowNumber, $row['total_deb']);
            $sheet->setCellValue('P' . $rowNumber, $row['total_rp_juta']);
        }

        $path = tempnam(sys_get_temp_dir(), 'kurmikro_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }
}
