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

    public function test_it_rejects_missing_or_empty_metadata(): void
    {
        foreach (['[Content_Types].xml', 'xl/workbook.xml'] as $name) {
            foreach ([null, ''] as $contents) {
                $path = $this->temporaryPath('.xlsx');
                $archive = new ZipArchive;
                $this->assertTrue($archive->open($path, ZipArchive::CREATE) === true);
                $archive->addFromString('[Content_Types].xml', '<Types/>');
                $archive->addFromString('xl/workbook.xml', '<workbook/>');
                $archive->addFromString('xl/worksheets/sheet1.xml', '<worksheet/>');
                if ($contents === null) {
                    $archive->deleteName($name);
                } else {
                    $archive->addFromString($name, $contents);
                }
                $archive->close();

                try {
                    $this->assertNull(SpreadsheetFileFormatDetector::detect($path), $name);
                } finally {
                    @unlink($path);
                }
            }
        }
    }

    public function test_it_detects_large_metadata_without_inflating_it_into_memory(): void
    {
        foreach (['[Content_Types].xml', 'xl/workbook.xml'] as $name) {
            $path = $this->archiveWithLargeEntry($name, 8_388_608);

            try {
                memory_reset_peak_usage();
                $memoryBefore = memory_get_usage();
                $result = SpreadsheetFileFormatDetector::detect($path);
                $peakIncrease = memory_get_peak_usage() - $memoryBefore;

                $this->assertSame('xlsx', $result, $name);
                $this->assertLessThan(2_097_152, $peakIncrease, $name.' was expanded into memory');
            } finally {
                @unlink($path);
            }
        }
    }

    public function test_it_preserves_detection_for_metadata_at_four_megabytes(): void
    {
        foreach (['[Content_Types].xml', 'xl/workbook.xml'] as $name) {
            $path = $this->archiveWithLargeEntry($name, 4_194_304);

            try {
                $this->assertSame('xlsx', SpreadsheetFileFormatDetector::detect($path), $name);
            } finally {
                @unlink($path);
            }
        }
    }

    public function test_it_still_reads_only_one_byte_of_a_large_worksheet(): void
    {
        $path = $this->archiveWithLargeEntry('xl/worksheets/sheet1.xml', 8_388_608);

        try {
            memory_reset_peak_usage();
            $memoryBefore = memory_get_usage();
            $result = SpreadsheetFileFormatDetector::detect($path);
            $peakIncrease = memory_get_peak_usage() - $memoryBefore;

            $this->assertSame('xlsx', $result);
            $this->assertLessThan(2_097_152, $peakIncrease, 'Worksheet was expanded into memory');
        } finally {
            @unlink($path);
        }
    }

    private function archiveWithLargeEntry(string $name, int $size): string
    {
        $path = $this->temporaryPath('.xlsx');
        $source = $this->temporaryPath('.xml');
        $stream = fopen($source, 'wb');
        $chunk = str_repeat(' ', 8192);
        for ($written = 0; $written < $size; $written += strlen($chunk)) {
            fwrite($stream, substr($chunk, 0, min(strlen($chunk), $size - $written)));
        }
        fclose($stream);

        $archive = new ZipArchive;
        try {
            $this->assertTrue($archive->open($path, ZipArchive::CREATE) === true);
            $archive->addFromString('[Content_Types].xml', '<Types/>');
            $archive->addFromString('xl/workbook.xml', '<workbook/>');
            $archive->addFromString('xl/worksheets/sheet1.xml', '<worksheet/>');
            $this->assertTrue($archive->addFile($source, $name));
            $this->assertTrue($archive->close());
        } finally {
            @unlink($source);
        }

        return $path;
    }

    private function temporaryPath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'spreadsheet_format_');
        $target = $path.$extension;
        rename($path, $target);

        return $target;
    }
}
