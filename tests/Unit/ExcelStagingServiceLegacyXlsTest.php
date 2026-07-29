<?php

namespace Tests\Unit;

use App\Services\Import\ExcelStagingService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Tests\TestCase;

class ExcelStagingServiceLegacyXlsTest extends TestCase
{
    public function test_legacy_xls_uses_php_spreadsheet_staging_fallback(): void
    {
        $sourcePath = $this->temporaryPath('.xls');
        $stagedPath = $this->temporaryPath('.csv');
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['KODE', 'NILAI'],
            ['MADIUN', '1000'],
        ]);
        (new Xls($spreadsheet))->save($sourcePath);
        $spreadsheet->disconnectWorksheets();

        try {
            $service = new ExcelStagingService();
            $header = $service->detectExcelHeaderViaPython($sourcePath, __DIR__ . '/missing-reader.py');
            $result = $service->stageExcelToCsv(
                static function (): void {
                },
                $sourcePath,
                0,
                ['kode', 'nilai'],
                $stagedPath,
                __DIR__ . '/missing-reader.py'
            );

            $this->assertSame(['KODE', 'NILAI'], $header['header_values']);
            $this->assertSame(1, $result['total_rows']);
            $this->assertStringContainsString('MADIUN', (string) file_get_contents($stagedPath));
            $this->assertStringContainsString('1000.00', (string) file_get_contents($stagedPath));
        } finally {
            @unlink($sourcePath);
            @unlink($stagedPath);
        }
    }

    private function temporaryPath(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'legacy_xls_');
        $path = $base . $extension;
        rename($base, $path);

        return $path;
    }
}
