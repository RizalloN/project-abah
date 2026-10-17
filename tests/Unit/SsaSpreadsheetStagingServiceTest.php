<?php

namespace Tests\Unit;

use App\Services\Import\SsaSpreadsheetStagingService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SsaSpreadsheetStagingServiceTest extends TestCase
{
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    public function test_original_simpanan_expands_only_source_merges_and_preserves_every_balance(): void
    {
        [$source, $output] = $this->workbook([
            $this->simpananHeaders(),
            ['16 Oktober 2026', 'KC A', 'Unit A', "Giro\u{00A0}", 'Ritel', 'Consumer', 5055201999.97],
            [null, null, null, null, null, 'Micro', 0],
            [null, null, 'Unit B', 'Tabungan', 'Wholesale', 'Wholesale', -1250.25],
        ], ['A2:A4', 'B2:B4', 'C2:C3', 'D2:D3', 'E2:E3']);
        $hash = hash_file('sha256', $source);

        $result = app(SsaSpreadsheetStagingService::class)->stage($source, 'ssa_simpanan', $output);
        $rows = $this->readCsv($output);

        $this->assertSame(3, $result['total_rows']);
        $this->assertSame('saldo', $rows[0][6]);
        $this->assertSame(['2026-10-16', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Micro', '0.00'], $rows[2]);
        $this->assertSame(['2026-10-16', 'KC A', 'Unit B', 'Tabungan', 'Wholesale', 'Wholesale', '-1250.25'], $rows[3]);
        $this->assertSame('5055201999.97', $rows[1][6]);
        $this->assertSame($hash, hash_file('sha256', $source));
    }

    public function test_pinjaman_preserves_collectibility_restructuring_and_separate_counts(): void
    {
        [$source, $output] = $this->workbook([
            $this->pinjamanHeaders(),
            ['16 Oktober 2026', 'KC A', 'Unit A', 'Kecil Komersial', 'Commercial', 'SME', 'Ritel', 'Small', 'Small', 1, 'N', 683256128784, 4642, 4933],
            [null, null, null, null, null, null, null, null, null, null, 'Y', 50450749718.84, 347, 360],
            [null, null, null, null, null, null, null, null, null, 2, 'N', 0, 0, 0],
        ], ['A2:A4', 'B2:B4', 'C2:C4', 'D2:D4', 'E2:E4', 'F2:F4', 'G2:G4', 'H2:H4', 'I2:I4', 'J2:J3']);

        $result = app(SsaSpreadsheetStagingService::class)->stage($source, 'ssa_pinjaman', $output);
        $rows = $this->readCsv($output);

        $this->assertSame(3, $result['total_rows']);
        $this->assertSame(['1', 'N', '683256128784.00', '4642', '4933'], array_slice($rows[1], 9));
        $this->assertSame(['1', 'Y', '50450749718.84', '347', '360'], array_slice($rows[2], 9));
        $this->assertSame(['2', 'N', '0.00', '0', '0'], array_slice($rows[3], 9));
    }

    public function test_blank_dimension_outside_merge_is_rejected_without_guessing_previous_group(): void
    {
        [$source, $output] = $this->workbook([
            $this->simpananHeaders(),
            ['16 Oktober 2026', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Consumer', 1000],
            ['16 Oktober 2026', 'KC B', null, 'Tabungan', 'Ritel', 'Micro', 500],
        ]);

        try {
            app(SsaSpreadsheetStagingService::class)->stage($source, 'ssa_simpanan', $output);
            $this->fail('Missing unit must reject the upload.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Baris SSA 3: kolom nama_uker kosong', $e->getMessage());
            $this->assertFileDoesNotExist($output);
        }
    }

    public function test_merged_balance_is_never_filled_from_another_row(): void
    {
        [$source, $output] = $this->workbook([
            $this->simpananHeaders(),
            ['16 Oktober 2026', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Consumer', 1000],
            ['16 Oktober 2026', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Micro', null],
        ], ['G2:G3']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Baris SSA 3: kolom saldo kosong');
        app(SsaSpreadsheetStagingService::class)->stage($source, 'ssa_simpanan', $output);
    }

    public function test_invalid_source_date_rejects_the_entire_upload(): void
    {
        [$source, $output] = $this->workbook([
            $this->simpananHeaders(),
            ['31 Februari 2026', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Micro', 500],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tanggal periode tidak valid');
        app(SsaSpreadsheetStagingService::class)->stage($source, 'ssa_simpanan', $output);
    }

    public function test_flat_export_keeps_existing_normalization_rules(): void
    {
        [$source, $output] = $this->workbook([
            ['Periode A', 'Cabang A', 'Uker A', 'Produk A', 'Segmen A', 'Bisnis A', 'Nilai A'],
            ['16 Oktober 2026', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Micro', '1.234.567,89'],
        ]);

        $this->assertNull(app(SsaSpreadsheetStagingService::class)->stage($source, 'ssa_simpanan', $output));
        $this->assertFileDoesNotExist($output);
    }

    public function test_missing_balance_header_is_recognized_only_after_the_six_known_dimensions(): void
    {
        [$source, $output] = $this->workbook([
            ['A', 'B', 'C', 'D', 'E', 'F', null],
            ['16 Oktober 2026', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Micro', 500],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Header file SSA tidak lengkap');
        app(SsaSpreadsheetStagingService::class)->stage($source, 'ssa_simpanan', $output);
    }

    private function simpananHeaders(): array
    {
        return ['Month, Day, Year of Posisi', 'Nama Cabang', 'Nama Uker', 'Produk', 'Segmentasi', 'Segmen Kategorisasi Bisnis', null];
    }

    private function pinjamanHeaders(): array
    {
        return ['Month, Day, Year of Periode', 'Nama Cabang', 'Nama Uker', 'Produk', 'Produk_Dashboard', 'Segmen', 'Segmen Lama', 'SEGMEN_2025', 'Segmen_Dashboard', 'Kolektabilitas One Obligor', 'Flag Restruk', 'Baki Debet', 'Jumlah Debitur Aktif', 'Jumlah Rekening Aktif'];
    }

    private function workbook(array $rows, array $merges = []): array
    {
        $source = tempnam(sys_get_temp_dir(), 'ssa_workbook_');
        $output = tempnam(sys_get_temp_dir(), 'ssa_csv_');
        $this->paths[] = $source;
        $this->paths[] = $output;
        @unlink($output);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1', true);
        foreach ($merges as $range) {
            $sheet->mergeCells($range);
        }
        (new Xlsx($book))->save($source);
        $book->disconnectWorksheets();

        return [$source, $output];
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
