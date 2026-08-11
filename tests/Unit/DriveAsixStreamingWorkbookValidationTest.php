<?php

namespace Tests\Unit;

use App\Exceptions\DriveAsixWorkbookException;
use App\Services\DriveAsix\SpreadsheetWorkbookService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use ZipArchive;

class DriveAsixStreamingWorkbookValidationTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_streaming_validation_accepts_a_valid_xlsx_without_changing_default_validation(): void
    {
        $path = $this->makeWorkbook('=SUM(A1:A2)');
        $service = new SpreadsheetWorkbookService;

        $this->assertSame('xlsx', $service->validateUploadedWorkbook($path, true));
        $this->assertSame('xlsx', $service->validateUploadedWorkbook($path));
    }

    public function test_streaming_validation_rejects_an_external_formula(): void
    {
        $path = $this->makeWorkbook('=WEBSERVICE("https://example.invalid/data")');

        $this->expectException(DriveAsixWorkbookException::class);
        $this->expectExceptionMessage(
            'Workbook memuat formula jaringan, hyperlink formula, atau referensi eksternal.'
        );

        (new SpreadsheetWorkbookService)->validateUploadedWorkbook($path, true);
    }

    public function test_storage_only_streaming_validation_accepts_an_external_formula(): void
    {
        $path = $this->makeWorkbook('=WEBSERVICE("https://example.invalid/data")');

        $this->assertSame(
            'xlsx',
            (new SpreadsheetWorkbookService)->validateUploadedWorkbook($path, true, false)
        );
    }

    public function test_streaming_validation_rejects_a_macro_archive_entry(): void
    {
        $path = $this->makeWorkbook('=SUM(A1:A2)');
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path) === true);
        $contentTypes = $archive->getFromName('[Content_Types].xml');
        $relationships = $archive->getFromName('xl/_rels/workbook.xml.rels');
        $this->assertIsString($contentTypes);
        $this->assertIsString($relationships);
        $contentTypes = str_replace(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
            'application/vnd.ms-excel.sheet.macroEnabled.main+xml',
            $contentTypes
        );
        $contentTypes = str_replace(
            '</Types>',
            '<Override PartName="/xl/vbaProject.bin" '
                .'ContentType="application/vnd.ms-office.vbaProject"/></Types>',
            $contentTypes
        );
        $relationships = str_replace(
            '</Relationships>',
            '<Relationship Id="rIdVba" '
                .'Type="http://schemas.microsoft.com/office/2006/relationships/vbaProject" '
                .'Target="vbaProject.bin"/></Relationships>',
            $relationships
        );
        $this->assertTrue($archive->addFromString('[Content_Types].xml', $contentTypes));
        $this->assertTrue($archive->addFromString(
            'xl/_rels/workbook.xml.rels',
            $relationships
        ));
        $this->assertTrue($archive->addFromString('xl/vbaProject.bin', 'macro payload'));
        $archive->close();

        $this->expectException(DriveAsixWorkbookException::class);
        $this->expectExceptionMessage('Workbook dengan macro/VBA tidak diizinkan');

        (new SpreadsheetWorkbookService)->validateUploadedWorkbook($path, true, false);
    }

    public function test_streaming_validation_rejects_malformed_worksheet_xml_and_restores_libxml_state(): void
    {
        $path = $this->makeWorkbook('=SUM(A1:A2)');
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path) === true);
        $this->assertTrue($archive->deleteName('xl/worksheets/sheet1.xml'));
        $this->assertTrue($archive->addFromString(
            'xl/worksheets/sheet1.xml',
            '<?xml version="1.0"?><worksheet><sheetData>'
        ));
        $archive->close();

        $previousState = libxml_use_internal_errors(false);

        try {
            (new SpreadsheetWorkbookService)->validateUploadedWorkbook($path, true, false);
            $this->fail('Worksheet XML rusak seharusnya ditolak.');
        } catch (DriveAsixWorkbookException $exception) {
            $this->assertSame('XML worksheet XLSX rusak.', $exception->getMessage());
            $this->assertFalse(libxml_use_internal_errors());
            $this->assertSame([], libxml_get_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);
        }
    }

    public function test_streaming_validation_rejects_a_truncated_zip_package(): void
    {
        $path = $this->makeWorkbook('=SUM(A1:A2)');
        $contents = file_get_contents($path);
        $this->assertIsString($contents);
        $this->assertGreaterThan(64, strlen($contents));
        $this->assertNotFalse(file_put_contents($path, substr($contents, 0, -64)));

        $this->expectException(DriveAsixWorkbookException::class);
        $this->expectExceptionMessage('Isi file bukan workbook XLSX/XLS yang valid.');

        (new SpreadsheetWorkbookService)->validateUploadedWorkbook($path, true, false);
    }

    private function makeWorkbook(string $formula): string
    {
        $path = tempnam(sys_get_temp_dir(), 'drive-asix-streaming-');
        $this->assertNotFalse($path);
        $this->temporaryFiles[] = $path;

        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Ringkasan');
        $worksheet->setCellValue('A1', 10);
        $worksheet->setCellValue('A2', 20);
        $worksheet->setCellValue('B1', $formula);

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save($path);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return $path;
    }
}
