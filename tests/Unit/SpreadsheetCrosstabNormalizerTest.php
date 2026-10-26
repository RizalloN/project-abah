<?php

namespace Tests\Unit;

use App\Services\Import\CrasSourceService;
use App\Services\Import\SsaSpreadsheetStagingService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SpreadsheetCrosstabNormalizerTest extends TestCase
{
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    public function test_almafacts_crosstab_skips_title_rows_names_the_balance_and_unmerges_groups(): void
    {
        [$source, $output] = $this->workbook([
            ['Laporan SSA Almafacts Area 6'],
            ['Filter: Kanca Madiun'],
            [],
            ['Month, Day, Year of 00. Posisi', '03. Kanca Konsolidasi', '05. Kode Unit Kerja', '04. Unit Kerja', 'Keterangan', null],
            [46326, 'KC Madiun', '3212', 'UNIT DOLOPO MADIUN', '01. Pendapatan Bunga', 990745525],
            [null, null, null, null, '02. Beban Bunga', -1250.5],
            [null, null, '3213', 'UNIT MEJAYAN', '01. Pendapatan Bunga', 6710],
            ['Grand Total', null, null, null, null, 990751984.5],
        ], ['A5:A7', 'B5:B7', 'C5:C6', 'D5:D6', 'A8:E8'], dateCells: ['A5']);

        $result = app(SsaSpreadsheetStagingService::class)->stage($source, 'ssa_almafacts', $output);
        $rows = $this->readCsv($output, ',');

        $this->assertSame(3, $result['total_rows']);
        $this->assertSame(['Month, Day, Year of 00. Posisi', '03. Kanca Konsolidasi', '05. Kode Unit Kerja', '04. Unit Kerja', 'Keterangan', 'Saldo'], $rows[0]);
        $this->assertSame(['October 31, 2026', 'KC Madiun', '3212', 'UNIT DOLOPO MADIUN', '01. Pendapatan Bunga', '990745525'], $rows[1]);
        $this->assertSame(['October 31, 2026', 'KC Madiun', '3212', 'UNIT DOLOPO MADIUN', '02. Beban Bunga', '-1250.5'], $rows[2]);
        $this->assertSame(['October 31, 2026', 'KC Madiun', '3213', 'UNIT MEJAYAN', '01. Pendapatan Bunga', '6710'], $rows[3]);

        $normalization = $result['normalization'];
        $this->assertSame([4], $normalization['header_rows']);
        $this->assertSame(1, $normalization['stats']['total_rows_skipped']);
        $this->assertStringContainsString('3 baris judul/filter', implode(' ', $normalization['notices']));
        $this->assertStringContainsString('dinamai `saldo`', implode(' ', $normalization['notices']));
    }

    public function test_cras_crosstab_becomes_the_exact_template_text_the_cras_reader_accepts(): void
    {
        $headers = CrasSourceService::SOURCE_HEADERS;
        $first = $this->crasRow(['Month, Day, Year of Posisi' => 'April 30, 2026', 'Ket Kanca' => '  KC Test  ', 'BR#' => '7', 'Ket Unit Kerja' => 'UNIT A', 'Kualitas' => '1', 'Baki Debet' => '25000000000']);
        $second = $this->crasRow(['Kualitas' => '2', 'Baki Debet' => '1500']);
        $third = $this->crasRow(['BR#' => '8', 'Ket Unit Kerja' => 'UNIT B', 'Kualitas' => '1', 'Baki Debet' => '0']);
        [$source, $output] = $this->workbook(
            [$headers, $first, $second, $third],
            ['A2:A4', 'B2:B4', 'C2:C3', 'D2:D3'],
            extension: 'xlsx'
        );

        $result = app(SsaSpreadsheetStagingService::class)->stage($source, 'cras', $output);
        $state = (new CrasSourceService)->inspect($output);

        $this->assertSame(3, $result['total_rows']);
        $this->assertSame('2026-04-30', $state['period']);
        $this->assertSame(['  KC Test  '], $state['branch_values']);
        $this->assertSame(['7', 'UNIT A', '2', '1500'], [$state['preview_rows'][1][2], $state['preview_rows'][1][3], $state['preview_rows'][1][13], $state['preview_rows'][1][19]]);
        $this->assertSame(['8', 'UNIT B'], [$state['preview_rows'][2][2], $state['preview_rows'][2][3]]);
        $this->assertSame('merge', $result['normalization']['mode']);
    }

    public function test_exact_flat_cras_workbook_keeps_the_streaming_xlsx_path(): void
    {
        [$source, $output] = $this->workbook([
            CrasSourceService::SOURCE_HEADERS,
            $this->crasRow(['Month, Day, Year of Posisi' => 'April 30, 2026', 'Ket Kanca' => 'KC Test', 'BR#' => '7', 'Ket Unit Kerja' => 'UNIT A']),
        ]);

        $this->assertNull(app(SsaSpreadsheetStagingService::class)->stage($source, 'cras', $output));
        $this->assertFileDoesNotExist($output);
    }

    public function test_hand_unmerged_export_fills_blanks_from_above_only_within_the_same_group(): void
    {
        [$source, $output] = $this->workbook([
            ['Month, Day, Year of Posisi', 'Nama Cabang', 'Nama Uker', 'Produk', 'Segmentasi', 'Segmen Kategorisasi Bisnis', null],
            ['16 Oktober 2026', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Consumer', 1000],
            [null, null, null, null, null, 'Micro', 250],
            [null, null, 'Unit B', 'Tabungan', 'Ritel', 'Micro', 75.5],
            [null, 'KC B', 'Unit C', 'Giro', 'Wholesale', 'Wholesale', 10],
        ]);

        $result = app(SsaSpreadsheetStagingService::class)->stage($source, 'ssa_simpanan', $output);
        $rows = $this->readCsv($output, ',');

        $this->assertSame('fill_down', $result['normalization']['mode']);
        $this->assertSame(['2026-10-16', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Micro', '250.00'], $rows[2]);
        $this->assertSame(['2026-10-16', 'KC A', 'Unit B', 'Tabungan', 'Ritel', 'Micro', '75.50'], $rows[3]);
        $this->assertSame(['2026-10-16', 'KC B', 'Unit C', 'Giro', 'Wholesale', 'Wholesale', '10.00'], $rows[4]);
        $this->assertSame(8, $result['normalization']['stats']['blank_filled']);
    }

    public function test_reordered_aliased_and_misspelled_headers_are_recognized(): void
    {
        [$source, $output] = $this->workbook([
            ['Rekap Pinjaman'],
            ['Kantor Cabang', 'Month, Day, Year of Periode', 'Unit Kerja', 'Produk', 'Produk Dashboard', 'Segmen', 'Segmen Lama', 'Segmen 2025', 'Segmen Dashboard', 'Kolektibilitas One Obligor', 'Flag Restruk', 'Baki Debet', 'Jumlah Debitur Aktif', 'Jumlah Rekening Aktif'],
            ['KC A', '16 Oktober 2026', 'Unit A', 'KUR', 'KUR', 'Mikro', 'Ritel', 'Micro', 'Micro', 1, 'N', 1000, 3, 4],
        ]);

        $result = app(SsaSpreadsheetStagingService::class)->stage($source, 'ssa_pinjaman', $output);
        $rows = $this->readCsv($output, ',');

        $this->assertSame(['2026-10-16', 'KC A', 'Unit A', 'KUR', 'KUR', 'Mikro', 'Ritel', 'Micro', 'Micro', '1', 'N', '1000.00', '3', '4'], $rows[1]);
        $this->assertSame([2], $result['normalization']['header_rows']);
    }

    public function test_ctrl_enter_reference_formulas_without_stored_values_are_resolved(): void
    {
        [$source, $output] = $this->workbook([
            ['Month, Day, Year of Posisi', 'Nama Cabang', 'Nama Uker', 'Produk', 'Segmentasi', 'Segmen Kategorisasi Bisnis', 'Saldo'],
            ['16 Oktober 2026', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Consumer', 1000],
            ['=A2', '=B2', '=C2', '=D2', '=E2', 'Micro', 500],
        ], precalculate: false);

        $result = app(SsaSpreadsheetStagingService::class)->stage($source, 'ssa_simpanan', $output);
        $rows = $this->readCsv($output, ',');

        $this->assertSame(['2026-10-16', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Micro', '500.00'], $rows[2]);
        $this->assertSame(5, $result['normalization']['stats']['formulas_resolved']);
    }

    public function test_unknown_column_with_data_in_a_crosstab_is_rejected_instead_of_dropped(): void
    {
        [$source, $output] = $this->workbook([
            ['Month, Day, Year of Posisi', 'Nama Cabang', 'Nama Uker', 'Produk', 'Segmentasi', 'Segmen Kategorisasi Bisnis', 'Saldo', 'Catatan Baru'],
            ['16 Oktober 2026', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Consumer', 1000, 'x'],
            [null, null, null, null, null, 'Micro', 500, null],
        ], ['A2:A3', 'B2:B3', 'C2:C3', 'D2:D3', 'E2:E3']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('`Catatan Baru` berisi data tetapi tidak dikenali');
        app(SsaSpreadsheetStagingService::class)->stage($source, 'ssa_simpanan', $output);
    }

    private function crasRow(array $values): array
    {
        $row = array_fill(0, count(CrasSourceService::SOURCE_HEADERS), null);
        foreach ($values as $header => $value) {
            $row[array_search($header, CrasSourceService::SOURCE_HEADERS, true)] = $value;
        }

        return $row;
    }

    private function workbook(array $rows, array $merges = [], array $dateCells = [], string $extension = 'xlsx', bool $precalculate = true): array
    {
        $source = tempnam(sys_get_temp_dir(), 'crosstab_').'.'.$extension;
        $output = tempnam(sys_get_temp_dir(), 'crosstab_out_');
        $this->paths[] = $source;
        $this->paths[] = $output;
        @unlink($output);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                if ($value === null) {
                    continue;
                }
                $coordinate = [$columnIndex + 1, $rowIndex + 1];
                if (is_string($value) && !str_starts_with($value, '=')) {
                    $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($coordinate, $value);
                }
            }
        }
        foreach ($dateCells as $cell) {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('d mmmm yyyy');
        }
        foreach ($merges as $range) {
            $sheet->mergeCells($range);
        }
        $writer = new Xlsx($book);
        $writer->setPreCalculateFormulas($precalculate);
        $writer->save($source);
        $book->disconnectWorksheets();

        return [$source, $output];
    }

    private function readCsv(string $path, string $delimiter): array
    {
        $handle = fopen($path, 'rb');
        $rows = [];
        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
