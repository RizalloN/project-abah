<?php

namespace Tests\Unit;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Lw321PnXlsxStagingScriptTest extends TestCase
{
    public function test_streaming_stager_preserves_source_text_and_all_rows(): void
    {
        $python = $this->resolvePythonBinary();
        if ($python === null) {
            $this->markTestSkipped('Python tidak tersedia.');
        }

        $headers = $this->sourceHeaders();
        $workbookPath = tempnam(sys_get_temp_dir(), 'lw321pn_source_');
        $csvPath = tempnam(sys_get_temp_dir(), 'lw321pn_stage_');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Metadata');
        $sheet->fromArray($headers, null, 'A4');

        $row = array_fill(0, count($headers), '');
        $row[1] = '23/08/2026';
        $row[2] = 'R ';
        $row[3] = 'KANWIL MALANG   ';
        $row[4] = '45';
        $row[5] = 'KC Madiun   ';
        $row[6] = '45';
        $row[7] = 'KC Madiun   ';
        $row[8] = 'IDR ';
        $row[9] = 'WL';
        $row[10] = '000501061071105';
        $row[11] = 'SAMINGUN   ';
        $row[12] = '150000000';
        $row[13] = '29/08/2026';
        $row[15] = '8.13';
        $row[17] = '29/12/2022';
        $row[18] = '29/12/2027';
        $row[19] = '60M';
        $row[20] = 'N';
        $row[21] = 'SDZJ380';
        $row[22] = '48824693';
        $row[23] = '0';
        $row[24] = '0';
        $row[25] = '0';
        $row[26] = '0';
        $row[27] = '0';
        $row[28] = '0';
        $row[29] = '0';
        $row[30] = '1';
        $row[31] = '1';
        $row[32] = '41210';
        $row[33] = 'KREDIT RITEL';
        $row[34] = '41000';
        $row[35] = 'RITEL';
        $row[36] = '1';
        $row[37] = '00322928 - Dimas Perdana';
        $row[38] = '00322928 - Dimas Perdana';
        $row[39] = '00059472 - Herwan';
        $row[40] = ' - ';
        $row[41] = ' - ';
        $row[42] = ' - ';
        $row[43] = '00001279 - Trisania';
        $row[44] = ' - ';
        $row[45] = ' - ';
        $row[46] = ' - ';
        $row[47] = '150000000';
        $row[48] = '48824693';
        $sheet->fromArray($row, null, 'A5');
        $sheet->setCellValueExplicit('K5', '000501061071105', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('D5', 'KANWIL MALANG   ', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('L5', 'SAMINGUN   ', DataType::TYPE_STRING);

        (new Xlsx($spreadsheet))->save($workbookPath);
        $spreadsheet->disconnectWorksheets();

        try {
            $command = escapeshellarg($python)
                . ' ' . escapeshellarg(base_path('scripts/lw321pn_xlsx_to_csv.py'))
                . ' --input ' . escapeshellarg($workbookPath)
                . ' --output ' . escapeshellarg($csvPath)
                . ' --progress-every 1';

            $output = [];
            exec($command, $output, $exitCode);

            $this->assertSame(0, $exitCode, implode(PHP_EOL, $output));
            $done = collect($output)
                ->map(static fn (string $line): array => (array) json_decode($line, true))
                ->firstWhere('type', 'done');
            $this->assertSame(1, $done['total_rows'] ?? null);
            $this->assertSame([], $done['preview_rows'] ?? null);
            $this->assertSame([], $done['unique_values'] ?? null);

            $handle = fopen($csvPath, 'rb');
            $csvHeaders = fgetcsv($handle);
            $csvRow = fgetcsv($handle);
            $extraRow = fgetcsv($handle);
            fclose($handle);

            $this->assertCount(49, $csvHeaders);
            $this->assertSame('COL_0', $csvHeaders[0]);
            $this->assertSame('R ', $csvRow[2]);
            $this->assertSame('KANWIL MALANG   ', $csvRow[3]);
            $this->assertSame('000501061071105', $csvRow[10]);
            $this->assertSame('SAMINGUN   ', $csvRow[11]);
            $this->assertFalse($extraRow);
        } finally {
            @unlink($workbookPath);
            @unlink($csvPath);
        }
    }

    public function test_stager_rejects_an_incomplete_source_schema(): void
    {
        $python = $this->resolvePythonBinary();
        if ($python === null) {
            $this->markTestSkipped('Python tidak tersedia.');
        }

        $workbookPath = tempnam(sys_get_temp_dir(), 'lw321pn_invalid_');
        $spreadsheet = new Spreadsheet();
        $headers = array_values(array_filter(
            $this->sourceHeaders(),
            static fn (string $header): bool => $header !== 'BALANCE DALAM IDR'
        ));
        $spreadsheet->getActiveSheet()->fromArray($headers, null, 'A4');
        (new Xlsx($spreadsheet))->save($workbookPath);
        $spreadsheet->disconnectWorksheets();

        try {
            $command = escapeshellarg($python)
                . ' ' . escapeshellarg(base_path('scripts/lw321pn_xlsx_to_csv.py'))
                . ' --input ' . escapeshellarg($workbookPath)
                . ' --preview-only';

            $output = [];
            exec($command, $output, $exitCode);

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('BALANCE_DALAM_IDR', implode(PHP_EOL, $output));
        } finally {
            @unlink($workbookPath);
        }
    }

    private function resolvePythonBinary(): ?string
    {
        $output = [];
        exec('python --version', $output, $exitCode);

        return $exitCode === 0 ? 'python' : null;
    }

    private function sourceHeaders(): array
    {
        return [
            '', 'PERIODE', 'KODE_KANWIL', 'KANWIL', 'KODE_KANCA', 'KANCA', 'KODE_UKER', 'UKER',
            'CURRENCY', 'LN_TYPE', 'NOMOR_REKENING', 'NAMA_DEBITUR', 'PLAFON', 'NEXT_PMT_DATE',
            'NEXT_INT_PMT_DATE', 'RATE', 'TGL_MENUNGGAK', 'TGL_REALISASI', 'TGL JATUH TEMPO',
            'JANGKA WAKTU', 'FLAG RESTRUK', 'CIFNO', 'KOLEKTIBILITAS LANCAR', 'KOLEKTIBILITAS DPK',
            'KOLEKTIBILITAS KURANG LANCAR', 'KOLEKTIBILITAS DIRAGUKAN', 'KOLEKTIBILITAS MACET',
            'TUNGGAKAN POKOK', 'TUNGGAKAN BUNGA', 'TUNGGAKAN PINALTI', 'FREQ PAYMENT',
            'FREQ INT PAYMENT', 'CODE', 'DESCRIPTION', 'SEGMEN LV1', 'DESC SEGMEN LV1', 'KOL_ADK',
            'PN PENGELOLA SINGLEPN', 'PN PENGELOLA 1', 'PN PEMRAKARSA', 'PN REFERRAL', 'PN RESTRUK',
            'PN PENGELOLA 2', 'PN PEMUTUS', 'PN CRM', 'PN RM REFERRAL NAIK SEGMENTASI', 'PN RM CRR',
            'PLAFON DALAM IDR', 'BALANCE DALAM IDR',
        ];
    }
}
