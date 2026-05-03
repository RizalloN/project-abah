<?php

namespace Tests\Unit;

use App\Services\Import\ReferensiUkerImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ReferensiUkerImporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        Schema::create('referensi_uker', function (Blueprint $table) {
            $table->id();
            $table->string('kode_uker', 5)->unique();
            $table->string('nama_uker', 180);
            $table->string('keterangan', 50)->nullable();
            $table->string('kode_cabang', 5)->nullable();
            $table->string('nama_cabang', 180)->nullable();
            $table->string('nama_uker_sumber', 180)->nullable();
            $table->string('kode_uker_sumber', 20)->nullable();
            $table->string('sheet_name', 100)->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_parses_and_imports_referensi_uker_workbook(): void
    {
        $path = $this->createWorkbook();

        try {
            $importer = new ReferensiUkerImporter();
            $parsed = $importer->parse($path);

            $this->assertSame(2, $parsed['metadata']['data_rows']);
            $this->assertSame('00045', $parsed['rows'][0]['kode_uker']);
            $this->assertSame('00552 -- KCP Caruban', $parsed['rows'][1]['nama_uker']);
            $this->assertSame('00045', $parsed['rows'][1]['kode_cabang']);

            $summary = $importer->importRows($parsed['rows']);

            $this->assertSame(2, $summary['inserted']);
            $this->assertSame(2, DB::table('referensi_uker')->count());
            $this->assertSame('Ritel', DB::table('referensi_uker')->where('kode_uker', '00552')->value('keterangan'));
        } finally {
            @unlink($path);
        }
    }

    public function test_it_rejects_duplicate_uker_codes(): void
    {
        $path = $this->createWorkbook(duplicateCode: true);

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Duplikasi kode uker 00045');

            (new ReferensiUkerImporter())->parse($path);
        } finally {
            @unlink($path);
        }
    }

    private function createWorkbook(bool $duplicateCode = false): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('REFF');

        $sheet->fromArray([
            ['Nama Uker', 'Keterangan', 'KODE UKER', 'Nama Cabang', 'Nama Uker'],
            ['00045 -- KC Madiun', 'Ritel', '45', '00045 -- KC Madiun (Konsolidasi-MB)', '00045 -- KC Madiun'],
            [
                $duplicateCode ? '00045 -- KC Madiun' : '00552 -- KCP Caruban',
                'Ritel',
                $duplicateCode ? '45' : '552',
                '00045 -- KC Madiun (Konsolidasi-MB)',
                $duplicateCode ? '00045 -- KC Madiun' : '00552 -- KCP Caruban',
            ],
        ]);

        $sheet->setCellValueExplicit('C2', '45', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C3', $duplicateCode ? '45' : '552', DataType::TYPE_STRING);

        $path = tempnam(sys_get_temp_dir(), 'referensi_uker_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
