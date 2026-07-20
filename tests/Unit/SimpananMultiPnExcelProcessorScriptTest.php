<?php

namespace Tests\Unit;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SimpananMultiPnExcelProcessorScriptTest extends TestCase
{
    public function test_stage_streams_excel_without_changing_source_text_or_column_positions(): void
    {
        $python = $this->resolvePythonBinary();
        if ($python === null) {
            $this->markTestSkipped('Python tidak tersedia untuk menjalankan prosesor Simpanan MultiPN.');
        }

        $this->assertPythonModuleAvailable($python, 'openpyxl');

        $dir = storage_path('framework/testing/simpanan_excel_stage_' . uniqid());
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $xlsxPath = $dir . DIRECTORY_SEPARATOR . 'simpanan-source.xlsx';
        $csvPath = $dir . DIRECTORY_SEPARATOR . 'stage.csv';
        $configPath = $dir . DIRECTORY_SEPARATOR . 'config.json';
        $sourceRegional = "R, MALANG; \"AREA\" | SATU\tDUA\nTIGA";

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Kelolaan Rekening Simpanan Multi PN'],
            [],
            [],
            ['Kriteria Report', null, ': Rekening Aktif'],
            ['Date Printed', null, ': 18-07-2026 09:01:11 PM'],
            ['No', 'Posisi', '', 'Regional Office', 'Kantor Cabang', '', 'Unit Kerja', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
            [1, '30-06-2026', '', $sourceRegional, '00049 -- KC Magetan(Konsolidasi-MB)', '', '00049 -- KC Magetan', '00CIF001', '0004901009801538', '01', 'TABUNGAN', '1.234,50'],
        ]);
        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        $headers = ['COL_0', 'Posisi', 'COL_2', 'Regional Office', 'Kantor Cabang', 'COL_5', 'Unit Kerja', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'];
        file_put_contents($configPath, json_encode([
            'file_path' => $xlsxPath,
            'header_index' => 5,
            'normalized_headers' => $headers,
            'output_csv_path' => $csvPath,
            'table_name' => 'simpanan_multipn',
            'full_vectorization' => true,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $command = escapeshellarg($python)
            . ' ' . escapeshellarg(base_path('scripts/simpanan_multipn_polars_processor.py'))
            . ' --config ' . escapeshellarg($configPath)
            . ' --mode stage';
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
        $this->assertFileExists($csvPath);

        $handle = fopen($csvPath, 'rb');
        $this->assertNotFalse($handle);
        $stagedHeaders = fgetcsv($handle);
        $row = fgetcsv($handle);
        fclose($handle);

        $this->assertSame($headers, $stagedHeaders);
        $this->assertCount(12, $row);
        $this->assertSame('1', $row[0]);
        $this->assertSame('30-06-2026', $row[1]);
        $this->assertSame('', $row[2]);
        $this->assertSame($sourceRegional, $row[3]);
        $this->assertSame('00CIF001', $row[7]);
        $this->assertSame('0004901009801538', $row[8]);
        $this->assertSame('01', $row[9]);
        $this->assertSame('1.234,50', $row[11]);
        $this->assertStringContainsString('"full_vectorization": false', implode("\n", $output));
    }

    public function test_excel_processor_preserves_blank_column_positions_for_simpanan_multipn(): void
    {
        $python = $this->resolvePythonBinary();
        if ($python === null) {
            $this->markTestSkipped('Python tidak tersedia untuk menjalankan excel_gpu_processor.py.');
        }

        $this->assertPythonModuleAvailable($python, 'openpyxl');

        $dir = storage_path('framework/testing/simpanan_excel_processor_' . uniqid());
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $xlsxPath = $dir . DIRECTORY_SEPARATOR . 'simpanan.xlsx';
        $csvPath = $dir . DIRECTORY_SEPARATOR . 'out.csv';
        $configPath = $dir . DIRECTORY_SEPARATOR . 'config.json';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Kelolaan Rekening Simpanan Multi PN'],
            [],
            [],
            ['Kriteria Report', null, ': Rekening Aktif'],
            ['Date Printed', null, ': 22-05-2026 03:15:43 PM'],
            ['No', 'Posisi', '', 'Regional Office', 'Kantor Cabang', '', 'Unit Kerja', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
            [1, '20-05-2026', '', 'R -- KANWIL MALANG', '00045 -- KC Madiun(Konsolidasi-MB)', '', '03887 -- UNIT SARADAN MADIUN', 'DEN7844', '388701018481536', '1', 'TABUNGAN', '19401577.00'],
        ]);
        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        file_put_contents($configPath, json_encode([
            'file_path' => $xlsxPath,
            'header_index' => 5,
            'table_name' => 'simpanan_multipn',
            'active_filters' => [],
            'normalized_headers' => ['COL_0', 'Posisi', 'COL_2', 'Regional Office', 'Kantor Cabang', 'COL_5', 'Unit Kerja', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
            'table_columns' => ['uniqueid_SMPN', 'posisi', 'regional_office', 'kantor_cabang', 'unit_kerja', 'CIFNO', 'no_rekening', 'jenis_simpanan', 'status', 'saldo_idr', 'created_at', 'updated_at'],
            'load_columns' => ['uniqueid_SMPN', 'posisi', 'regional_office', 'kantor_cabang', 'unit_kerja', 'CIFNO', 'no_rekening', 'jenis_simpanan', 'status', 'saldo_idr', 'created_at', 'updated_at'],
            'output_csv_path' => $csvPath,
            'unique_id_col' => 'uniqueid_SMPN',
            'unique_id_prefix' => 'test_smpn',
            'unique_id_suffix' => '_SMPN',
            'manual_values' => [],
            'preserve_column_positions' => true,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $command = escapeshellarg($python)
            . ' ' . escapeshellarg(base_path('scripts/excel_gpu_processor.py'))
            . ' --config ' . escapeshellarg($configPath);
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
        $this->assertFileExists($csvPath);

        $handle = fopen($csvPath, 'r');
        $this->assertNotFalse($handle);
        $row = fgetcsv($handle);
        fclose($handle);

        $this->assertIsArray($row);
        $this->assertSame('2026-05-20', $row[1] ?? null);
        $this->assertSame('R -- KANWIL MALANG', $row[2] ?? null);
        $this->assertSame('00045 -- KC Madiun(Konsolidasi-MB)', $row[3] ?? null);
        $this->assertSame('03887 -- UNIT SARADAN MADIUN', $row[4] ?? null);
        $this->assertSame('DEN7844', $row[5] ?? null);
        $this->assertSame('388701018481536', $row[6] ?? null);
        $this->assertSame('TABUNGAN', $row[7] ?? null);
        $this->assertSame('1', $row[8] ?? null);
        $this->assertSame('19401577.00', $row[9] ?? null);
    }

    private function resolvePythonBinary(): ?string
    {
        exec('python --version', $output, $exitCode);

        return $exitCode === 0 ? 'python' : null;
    }

    private function assertPythonModuleAvailable(string $python, string $module): void
    {
        exec(escapeshellarg($python) . ' -c ' . escapeshellarg("import {$module}"), $output, $exitCode);

        if ($exitCode !== 0) {
            $this->markTestSkipped("Python module {$module} tidak tersedia.");
        }
    }
}
