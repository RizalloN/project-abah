<?php

namespace Tests\Unit;

use App\Support\SpreadsheetFileFormatDetector;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Tests\TestCase;
use ZipArchive;

class SpreadsheetFileFormatDetectorTest extends TestCase
{
    public function test_it_detects_a_valid_xlsx_even_when_the_filename_ends_in_xls(): void
    {
        $path = $this->temporaryPath('.xls');
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path, ZipArchive::CREATE) === true);
        $archive->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $archive->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>');
        $archive->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>');
        $archive->close();

        try {
            $this->assertSame('xlsx', SpreadsheetFileFormatDetector::detect($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_it_detects_legacy_xls_signature(): void
    {
        $path = $this->temporaryPath('.xls');
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->setCellValue('A1', 'Workbook XLS valid');

        try {
            (new Xls($workbook))->save($path);
            $this->assertSame('xls', SpreadsheetFileFormatDetector::detect($path));
        } finally {
            $workbook->disconnectWorksheets();
            @unlink($path);
        }
    }

    public function test_it_rejects_incomplete_or_unknown_excel_content(): void
    {
        $path = $this->temporaryPath('.xls');
        file_put_contents($path, 'not a spreadsheet');

        try {
            $this->assertNull(SpreadsheetFileFormatDetector::detect($path));
        } finally {
            @unlink($path);
        }
    }

    private function temporaryPath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'spreadsheet_format_');
        $target = $path.$extension;
        rename($path, $target);

        return $target;
    }
}
