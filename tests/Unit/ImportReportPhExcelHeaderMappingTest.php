<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportReportPhController;
use ReflectionMethod;
use Tests\TestCase;

class ImportReportPhExcelHeaderMappingTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(storage_path('app/testing/report_ph_alias_stage.csv'));
        @rmdir(storage_path('app/testing'));

        parent::tearDown();
    }

    public function test_normalize_excel_headers_maps_lw325_template_aliases(): void
    {
        $controller = app(ImportReportPhController::class);
        $normalizeHeaders = new ReflectionMethod($controller, 'normalizeExcelHeaders');
        $normalizeHeaders->setAccessible(true);

        $headers = $normalizeHeaders->invoke($controller, [
            'NO',
            'PERIODE',
            'NOMOR_REKENING',
            null,
            'KANWIL',
            'KANCA',
            'UNIT',
            'NAMA DEBITUR',
            'CIF',
            'FKSEGMEN',
            'SEGMEN',
            'DESKRIPSI SEGMEN',
            'PRODUK',
            'TGL PH',
            'TGL REALISASI',
            'CURRENCY',
            'SISA AWAL PH POKOK',
            'SISA AWAL PH BUNGA',
            'BESAR REALISASI',
            'PLAFON',
            'JW',
            'AT',
            'CIF',
            'SISA AKHIR PH POKOK',
            'SISA AKHIR PH BUNGA',
            'KUMULATIF ANGSURAN POKOK',
            'KUMULATIF ANGSURAN BUNGA',
            'SISA POKOK',
            'SISA BUNGA',
            'ALIH TAGIH ASURANSI',
            'SALDO/TAGIHAN ALIH TAGIH ASURANSI',
            "TOTAL KEWAJIBAN\n\n",
            'PN PENGELOLA 2',
            'PN CRR',
            'PN JUMLAH',
            ' DEFFERED BUNGA CUTOFF PH',
            'SAI TUNGGAKAN CUTOFF  PH',
            'SAI DEFFERED CUTOFF  PH',
            'FLAG_KLAIM',
            'CLMAMT',
            'CLMAPR',
        ]);

        $this->assertSame('textbox3', $headers[0]);
        $this->assertSame('acctno', $headers[2]);
        $this->assertSame('COL_3', $headers[3]);
        $this->assertSame('cif1', $headers[8]);
        $this->assertSame('cif', $headers[22]);
        $this->assertSame('clmamt1', $headers[29]);
        $this->assertSame('clmapr1', $headers[30]);
        $this->assertSame('os_penuh_berjalan1', $headers[31]);
        $this->assertSame('pn_pengelola2', $headers[32]);
        $this->assertSame('pn_crr1', $headers[33]);
        $this->assertSame('jumlah_pn', $headers[34]);
        $this->assertSame('deffered_bunga_ph', $headers[35]);
        $this->assertSame('sai_tunggakan_ph', $headers[36]);
        $this->assertSame('sai_deffered_ph', $headers[37]);
    }

    public function test_build_csv_context_accepts_staged_csv_with_mapped_excel_headers(): void
    {
        $controller = app(ImportReportPhController::class);
        $normalizeHeaders = new ReflectionMethod($controller, 'normalizeExcelHeaders');
        $normalizeHeaders->setAccessible(true);

        $headers = $normalizeHeaders->invoke($controller, [
            'NO',
            'PERIODE',
            'NOMOR_REKENING',
            null,
            'KANWIL',
            'KANCA',
            'UNIT',
            'NAMA DEBITUR',
            'CIF',
            'FKSEGMEN',
            'SEGMEN',
            'DESKRIPSI SEGMEN',
            'PRODUK',
            'TGL PH',
            'TGL REALISASI',
            'CURRENCY',
            'SISA AWAL PH POKOK',
            'SISA AWAL PH BUNGA',
            'BESAR REALISASI',
            'PLAFON',
            'JW',
            'AT',
            'CIF',
            'SISA AKHIR PH POKOK',
            'SISA AKHIR PH BUNGA',
            'KUMULATIF ANGSURAN POKOK',
            'KUMULATIF ANGSURAN BUNGA',
            'SISA POKOK',
            'SISA BUNGA',
            'ALIH TAGIH ASURANSI',
            'SALDO/TAGIHAN ALIH TAGIH ASURANSI',
            'TOTAL KEWAJIBAN',
            'FLAG_KLAIM',
            'CLMAMT',
            'CLMAPR',
        ]);

        $row = array_fill(0, count($headers), '');
        $row[0] = '1';
        $row[1] = '2025-04-30';
        $row[2] = '634601017396107';
        $row[4] = 'KANWIL MALANG';
        $row[5] = 'KC Madiun';
        $row[6] = 'UNIT BALEREJO';
        $row[7] = 'MARIYAM';
        $row[8] = 'MXH5316';
        $row[9] = '11100';
        $row[10] = 'Micro';
        $row[11] = 'Kupedes';
        $row[12] = 'Kupedes';
        $row[13] = '2023-06-30';
        $row[14] = '2022-04-25';
        $row[15] = 'IDR';
        $row[16] = '57,819,365.00';
        $row[17] = '7,360,459.37';
        $row[18] = '50,000,000.00';
        $row[19] = '50,000,000.00';
        $row[20] = '36';
        $row[21] = '1';
        $row[22] = 'CIF-SECOND';
        $row[23] = '10,000.00';
        $row[24] = '500.00';
        $row[25] = '2,000.00';
        $row[26] = '100.00';
        $row[27] = '8,000.00';
        $row[28] = '400.00';
        $row[29] = '0.00';
        $row[30] = '0.00';
        $row[31] = '8,400.00';
        $row[32] = 'Y';
        $row[33] = '100.00';
        $row[34] = '80.00';

        $absolutePath = storage_path('app/testing/report_ph_alias_stage.csv');
        if (!is_dir(dirname($absolutePath))) {
            @mkdir(dirname($absolutePath), 0777, true);
        }

        $lines = [
            'Periode Data : 30/04/2025',
            implode(',', $headers),
            implode(',', $row),
        ];
        file_put_contents($absolutePath, implode("\n", $lines) . "\n");

        $buildCsvContext = new ReflectionMethod($controller, 'buildCsvContext');
        $buildCsvContext->setAccessible(true);
        $context = $buildCsvContext->invoke($controller, $absolutePath);

        $this->assertSame('2025-04-30', $context['periode']);
        $this->assertSame(2, $context['source_indexes']['acctno']);
        $this->assertSame(29, $context['source_indexes']['clmamt1']);
        $this->assertSame(31, $context['source_indexes']['os_penuh_berjalan1']);

        $mapCsvRow = new ReflectionMethod($controller, 'mapCsvRow');
        $mapCsvRow->setAccessible(true);
        $mappedRow = $mapCsvRow->invoke($controller, $context, $row);

        $this->assertSame('2025-04-30', $mappedRow[0]);
        $this->assertSame('634601017396107', $mappedRow[1]);
        $this->assertSame('KC Madiun', $mappedRow[3]);
        $this->assertSame('MXH5316', $mappedRow[6]);
        $this->assertSame('10000.00', $mappedRow[21]);
        $this->assertSame('Y', $mappedRow[64]);
        $this->assertSame('100.00', $mappedRow[65]);
        $this->assertSame('80.00', $mappedRow[66]);
    }

    public function test_preview_state_rejects_sparse_lw325_rows(): void
    {
        $controller = app(ImportReportPhController::class);
        $usablePreview = new ReflectionMethod($controller, 'hasUsablePreviewStateRows');
        $usablePreview->setAccessible(true);

        $this->assertFalse($usablePreview->invoke($controller, [[
            '2026-05-07',
            '4501052349107',
            null,
            null,
            null,
            null,
            null,
        ]]));

        $this->assertTrue($usablePreview->invoke($controller, [[
            '2026-05-07',
            '004501049347108',
            'KANWIL MALANG',
            'KC Madiun',
            'KC Madiun',
            'POKDAKAN MAKMUR',
            'PO76102',
        ]]));
    }
}
