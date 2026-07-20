<?php

namespace Tests\Unit;

use App\Services\Import\CrasSourceService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class CrasSourceServiceTest extends TestCase
{
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_utf16_tsv_is_read_without_changing_source_values(): void
    {
        $row = $this->validRow();
        $row[1] = '  KC Test  ';
        $row[8] = '\\N';
        $row[9] = 'value,with,commas';
        $row[10] = 'say "yes"';
        $row[11] = 'C:\\source\\folder';
        $row[12] = "line one\nline two";
        $row[13] = '';
        $source = $this->createUtf16Tsv([CrasSourceService::SOURCE_HEADERS, $row]);
        $service = new CrasSourceService();

        $state = $service->inspect($source);

        $this->assertSame(1, $state['total_rows']);
        $this->assertSame('2026-04-30', $state['period']);
        $this->assertSame(['  KC Test  '], $state['branch_values']);
        $this->assertSame($row, $state['preview_rows'][0]);
        $this->assertSame('', $state['preview_rows'][0][13]);
    }

    public function test_staging_escapes_mysql_control_sequences_without_normalizing_values(): void
    {
        $selected = $this->validRow();
        $selected[1] = ' KC Selected ';
        $selected[8] = '\\N';
        $selected[9] = 'a,b';
        $selected[10] = 'quote " value';
        $selected[11] = "line one\nline two";
        $other = $this->validRow();
        $other[1] = 'KC Other';
        $source = $this->createUtf16Tsv([
            CrasSourceService::SOURCE_HEADERS,
            $selected,
            $other,
        ]);
        $staged = $this->temporaryPath('cras_stage_');
        $service = new CrasSourceService();

        $result = $service->stageForImport(
            $source,
            $staged,
            [' KC Selected '],
            '2026-04-30'
        );
        $contents = (string) file_get_contents($staged);

        $this->assertSame(2, $result['scanned_rows']);
        $this->assertSame(1, $result['imported_rows']);
        $this->assertSame(1, substr_count($contents, "\n"));
        $this->assertStringContainsString('" KC Selected "', $contents);
        $this->assertStringContainsString('"\\\\N"', $contents);
        $this->assertStringContainsString('"a,b"', $contents);
        $this->assertStringContainsString('"quote \\" value"', $contents);
        $this->assertStringContainsString('"line one\\nline two"', $contents);
        $this->assertStringNotContainsString('KC Other', $contents);
    }

    public function test_malformed_non_empty_row_is_rejected_instead_of_padded_or_truncated(): void
    {
        $badRow = array_slice($this->validRow(), 0, 32);
        $source = $this->createUtf16Tsv([CrasSourceService::SOURCE_HEADERS, $badRow]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('memiliki 32 kolom; seharusnya 33 kolom');

        (new CrasSourceService())->inspect($source);
    }

    public function test_header_is_not_silently_normalized(): void
    {
        $headers = CrasSourceService::SOURCE_HEADERS;
        $headers[1] = 'ket kanca';
        $source = $this->createUtf16Tsv([$headers, $this->validRow()]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Header CRAS kolom ke-2 harus `Ket Kanca`');

        (new CrasSourceService())->inspect($source);
    }

    public function test_xlsx_is_streamed_without_formatting_or_normalizing_cell_values(): void
    {
        $row = $this->validRow();
        $row[0] = 'May 31, 2026';
        $row[1] = '  KC XLSX  ';
        $row[2] = '0045';
        $row[8] = '\\N';
        $row[9] = ' value with spaces ';
        $row[13] = '';
        $row[18] = '58782230.5';
        $row[19] = '-5246507';

        $source = $this->createXlsx([$row], [18, 19]);
        $staged = $this->temporaryPath('cras_xlsx_stage_');
        $service = new CrasSourceService();

        $state = $service->inspect($source);
        $stage = $service->stageForImport($source, $staged, ['  KC XLSX  '], '2026-05-31');
        $contents = (string) file_get_contents($staged);

        $this->assertSame(1, $state['total_rows']);
        $this->assertSame('2026-05-31', $state['period']);
        $this->assertSame($row, $state['preview_rows'][0]);
        $this->assertSame('0045', $state['preview_rows'][0][2]);
        $this->assertSame('58782230.5', $state['preview_rows'][0][18]);
        $this->assertSame('-5246507', $state['preview_rows'][0][19]);
        $this->assertSame('', $state['preview_rows'][0][13]);
        $this->assertSame(1, $stage['imported_rows']);
        $this->assertStringContainsString('"  KC XLSX  "', $contents);
        $this->assertStringContainsString('"0045"', $contents);
        $this->assertStringContainsString('"58782230.5"', $contents);
    }

    public function test_xlsx_rejects_non_empty_data_after_column_33(): void
    {
        $source = $this->createXlsx([$this->validRow()], [], 'unexpected column');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('memiliki data setelah kolom ke-33');

        (new CrasSourceService())->inspect($source);
    }

    private function validRow(): array
    {
        $row = array_fill(0, count(CrasSourceService::SOURCE_HEADERS), '');
        $row[0] = 'April 30, 2026';
        $row[1] = 'KC Test';
        $row[2] = '7';
        $row[3] = 'UNIT TEST';
        $row[19] = '25,000,000,000';

        return $row;
    }

    private function createUtf16Tsv(array $rows): string
    {
        $memory = fopen('php://temp', 'w+b');
        foreach ($rows as $row) {
            fputcsv($memory, $row, "\t", '"', '', "\r\n");
        }
        rewind($memory);
        $utf8 = (string) stream_get_contents($memory);
        fclose($memory);

        $path = $this->temporaryPath('cras_source_');
        file_put_contents($path, "\xFF\xFE" . mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8'));

        return $path;
    }

    private function createXlsx(array $rows, array $numericColumns = [], ?string $extraValue = null): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach (CrasSourceService::SOURCE_HEADERS as $index => $header) {
            $coordinate = Coordinate::stringFromColumnIndex($index + 1) . '1';
            $sheet->setCellValueExplicit($coordinate, $header, DataType::TYPE_STRING);
        }

        foreach ($rows as $rowOffset => $row) {
            $excelRow = $rowOffset + 2;
            foreach ($row as $index => $value) {
                if ($value === '') {
                    continue;
                }
                $coordinate = Coordinate::stringFromColumnIndex($index + 1) . $excelRow;
                if (in_array($index, $numericColumns, true)) {
                    $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_NUMERIC);
                    $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('#,##0');
                    continue;
                }
                $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_STRING);
            }

            if ($extraValue !== null) {
                $sheet->setCellValueExplicit('AH' . $excelRow, $extraValue, DataType::TYPE_STRING);
            }
        }

        $path = $this->temporaryXlsxPath();
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function temporaryXlsxPath(): string
    {
        $base = $this->temporaryPath('cras_xlsx_');
        $path = $base . '.xlsx';
        if (!rename($base, $path)) {
            $this->fail('Temporary XLSX path could not be created.');
        }
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function temporaryPath(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) {
            $this->fail('Temporary file could not be created.');
        }
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
