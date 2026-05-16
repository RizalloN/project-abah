<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportCleanupController;
use App\Http\Controllers\Import\ImportPerformancePisPerProdukController;
use App\Services\Import\ImportCleanupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class ImportPerformancePisPerProdukControllerTest extends TestCase
{
    public function test_cleanup_successful_import_syncs_report_snapshots_before_cleanup(): void
    {
        $controller = new ImportPerformancePisPerProdukController();

        $cleanupService = Mockery::mock(ImportCleanupService::class);
        $cleanupService->shouldReceive('dispatchImportedJobSync')
            ->once()
            ->with(77, 'performance_pis_per_produk', null, ImportPerformancePisPerProdukController::class);
        $this->app->instance(ImportCleanupService::class, $cleanupService);

        $cleanupController = Mockery::mock(ImportCleanupController::class);
        $cleanupController->shouldReceive('cleanupSuccessfulJobArtifacts')
            ->once()
            ->with(77, ['performance/sample.xlsx', 'C:\\temp\\performance_stage.csv'])
            ->andReturn([
                'job_id' => 77,
                'eligible' => true,
                'deleted_files' => [],
                'deleted_directories' => [],
            ]);
        $this->app->instance(ImportCleanupController::class, $cleanupController);

        $this->invokeMethod($controller, 'cleanupSuccessfulImportArtifacts', [
            77,
            'performance/sample.xlsx',
            null,
            ['C:\\temp\\performance_stage.csv'],
        ]);
    }

    public function test_resolve_working_import_path_prefers_staged_csv_when_available(): void
    {
        $controller = new ImportPerformancePisPerProdukController();
        $relativePath = 'performance_pis_imports/sample.xlsx';
        $stagedCsvPath = tempnam(sys_get_temp_dir(), 'pnps_stage_') . '.csv';
        file_put_contents($stagedCsvPath, "header\nvalue\n");

        try {
            $cacheKey = $this->invokeMethod($controller, 'stageCacheKey', [$relativePath]);
            Cache::put(
                $cacheKey,
                [
                    'staged_csv_path' => $stagedCsvPath,
                    'total_rows' => 1,
                ],
                now()->addMinutes(5)
            );

            $resolvedPath = $this->invokeMethod($controller, 'resolveWorkingImportPath', [$relativePath]);

            $this->assertSame($stagedCsvPath, $resolvedPath);
        } finally {
            Cache::forget($this->invokeMethod($controller, 'stageCacheKey', [$relativePath]));
            @unlink($stagedCsvPath);
        }
    }

    public function test_build_csv_context_uses_manual_periode_and_headers(): void
    {
        $controller = new ImportPerformancePisPerProdukController();
        $csvPath = tempnam(sys_get_temp_dir(), 'pnps_csv_') . '.csv';
        file_put_contents($csvPath, implode("\n", [
            'no,kode_kanwil,kanwil,kode_kanca,kanca,kode_uker,uker,corporate_code,nama_perusahaan,jenis_mitra,jenis_perusahaan,tipe_produk,nomor_rekening,nama_rekening,saldo_britama_kerjasama,tanggal_pembuatan_rekening,pn_rm_dana_brinets,pn_rm_dana_pis2,nomor_hp,email,flag_briguna,flag_cc',
            '1,01,Kanwil A,07,KC Banyuwangi,09,UKER,0001,PT A,MITRA,PERUSAHAAN,PRODUK,123,Rek A,1000,2026-03-31,1,2,08123456789,a@example.com,Y,N',
        ]) . "\n");

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [
                $csvPath,
                '2026-03-31',
                1,
            ]);

            $this->assertSame('csv', $context['source_format']);
            $this->assertSame('2026-03-31', $context['posisi']);
            $this->assertSame(1, $context['header_line']);
            $this->assertSame(4, $context['source_indexes']['kanca']);
            $this->assertSame(2, $context['total_rows']);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_map_csv_row_injects_posisi_and_keeps_branch_values(): void
    {
        $controller = new ImportPerformancePisPerProdukController();
        $csvPath = tempnam(sys_get_temp_dir(), 'pnps_map_') . '.csv';
        file_put_contents($csvPath, implode("\n", [
            'no,kode_kanwil,kanwil,kode_kanca,kanca,kode_uker,uker,corporate_code,nama_perusahaan,jenis_mitra,jenis_perusahaan,tipe_produk,nomor_rekening,nama_rekening,saldo_britama_kerjasama,tanggal_pembuatan_rekening,pn_rm_dana_brinets,pn_rm_dana_pis2,nomor_hp,email,flag_briguna,flag_cc',
            '1,01,Kanwil A,07,KC Banyuwangi,09,UKER,0001,PT A,MITRA,PERUSAHAAN,PRODUK,123,Rek A,1000,2026-03-31,1,2,08123456789,a@example.com,Y,N',
        ]) . "\n");

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [
                $csvPath,
                '2026-03-31',
                1,
            ]);

            $dataRow = $this->invokeMethod($controller, 'parseCsvLine', [
                '1,01,Kanwil A,07,KC Banyuwangi,09,UKER,0001,PT A,MITRA,PERUSAHAAN,PRODUK,123,Rek A,1000,2026-03-31,1,2,08123456789,a@example.com,Y,N',
                ',',
            ]);
            $mappedRow = $this->invokeMethod($controller, 'mapCsvRow', [$context, $dataRow]);

            $this->assertSame('2026-03-31', $mappedRow[0]);
            $this->assertSame('KC Banyuwangi', $mappedRow[4]);
            $this->assertSame('PT A', $mappedRow[8]);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_build_csv_context_detects_semicolon_delimiter_with_multiline_header(): void
    {
        $controller = new ImportPerformancePisPerProdukController();
        $csvPath = tempnam(sys_get_temp_dir(), 'pnps_multiline_') . '.csv';
        file_put_contents($csvPath, implode("\r\n", [
            'No;Kode Kanwil;Kanwil;Kode Kanca;Kanca;Kode Uker;Uker;Corporate Code;Nama Perusahaan;Jenis Mitra;Jenis Perusahaan;Product Type;Nomor Rekening;Nama Rekening;Saldo Britama Kerjasama;Tanggal Pembuatan Rekening;"PN RM',
            'Dana BRINETS";"PN RM',
            'Dana PIS2";Nomor HP;Alamat Email;"Relasi Briguna',
            '(Yes/No)";"Relasi Kartu Kredit',
            '(Yes/No)"',
            '1;R;KANWIL MALANG;7;KC Banyuwangi;7;KC Banyuwangi;184;PT ASABRI PERSERO;;;TN;7,01096E+11;DENY PUDJI PRADANA;39177,06;06/11/2020 00:00;;;81259533952;;Y;N',
        ]) . "\r\n");

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [
                $csvPath,
                '2026-03-31',
                1,
            ]);

            $this->assertSame(';', $context['delimiter']);
            $this->assertSame(1, $context['header_line']);
            $this->assertSame(12, $context['source_indexes']['nomor_rekening']);
            $this->assertSame(16, $context['source_indexes']['pn_rm_dana_brinets']);
            $this->assertSame(21, $context['source_indexes']['flag_cc']);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_iterate_csv_data_rows_preserves_row_alignment_and_numeric_values_for_semicolon_csv(): void
    {
        $controller = new ImportPerformancePisPerProdukController();
        $csvPath = tempnam(sys_get_temp_dir(), 'pnps_semicolon_') . '.csv';
        file_put_contents($csvPath, implode("\r\n", [
            'No;Kode Kanwil;Kanwil;Kode Kanca;Kanca;Kode Uker;Uker;Corporate Code;Nama Perusahaan;Jenis Mitra;Jenis Perusahaan;Product Type;Nomor Rekening;Nama Rekening;Saldo Britama Kerjasama;Tanggal Pembuatan Rekening;"PN RM',
            'Dana BRINETS";"PN RM',
            'Dana PIS2";Nomor HP;Alamat Email;"Relasi Briguna',
            '(Yes/No)";"Relasi Kartu Kredit',
            '(Yes/No)"',
            '1;R;KANWIL MALANG;7;KC Banyuwangi;7;KC Banyuwangi;184;PT ASABRI PERSERO;;;TN;7,01096E+11;DENY PUDJI PRADANA;39177,06;06/11/2020 00:00;;;81259533952;;Y;N',
        ]) . "\r\n");

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [
                $csvPath,
                '2026-03-31',
                1,
            ]);

            $rows = [];
            $this->invokeMethod($controller, 'iterateDataRows', [
                $csvPath,
                $context,
                function (array $row) use (&$rows) {
                    $rows[] = $row;
                    return true;
                },
            ]);

            $this->assertCount(1, $rows);
            $this->assertSame('2026-03-31', $rows[0][0]);
            $this->assertSame('7,01096E+11', $rows[0][12]);
            $this->assertSame('39177.06', $rows[0][14]);
            $this->assertSame('2020-11-06', $rows[0][15]);
            $this->assertSame('81259533952', $rows[0][18]);
            $this->assertSame('Y', $rows[0][20]);
            $this->assertSame('N', $rows[0][21]);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_collect_preview_unique_values_scans_entire_file_for_filter_options(): void
    {
        $controller = new ImportPerformancePisPerProdukController();
        $csvPath = tempnam(sys_get_temp_dir(), 'pnps_unique_') . '.csv';
        $headers = $this->performancePisHeaders();

        $rows = [implode(',', $headers)];
        for ($i = 1; $i <= 5000; $i++) {
            $rows[] = $this->buildPerformancePisRow([
                'no' => (string) $i,
                'kode_kanca' => '07',
                'kanca' => 'KC Banyuwangi',
            ]);
        }
        $rows[] = $this->buildPerformancePisRow([
            'no' => '5001',
            'kode_kanca' => '08',
            'kanca' => 'KC Jember',
        ]);

        file_put_contents($csvPath, implode("\n", $rows) . "\n");

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [
                $csvPath,
                '2026-03-31',
                5001,
            ]);

            $uniqueValues = $this->invokeMethod($controller, 'collectPreviewUniqueValues', [
                $csvPath,
                $context,
            ]);

            $kancaIndex = array_search('kanca', $headers, true);
            $kancaValues = array_keys($uniqueValues[$kancaIndex] ?? []);

            $this->assertContains('KC Banyuwangi', $kancaValues);
            $this->assertContains('KC Jember', $kancaValues);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_performance_pis_filter_options_endpoint_scans_beyond_preview_sample(): void
    {
        $controller = new ImportPerformancePisPerProdukController();
        $relativePath = 'performance_pis_imports/pnps_filter_options_test.csv';
        $csvPath = Storage::path($relativePath);
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        $headers = $this->performancePisHeaders();
        $rows = [implode(',', $headers)];
        for ($i = 1; $i <= 250; $i++) {
            $rows[] = $this->buildPerformancePisRow([
                'no' => (string) $i,
                'kode_kanca' => '07',
                'kanca' => 'KC Banyuwangi',
            ]);
        }
        $rows[] = $this->buildPerformancePisRow([
            'no' => '251',
            'kode_kanca' => '08',
            'kanca' => 'KC Jember',
        ]);

        file_put_contents($csvPath, implode("\n", $rows) . "\n");
        session(['performance_pis_periode' => '2026-03-31']);

        try {
            $request = Request::create('/import/performance-pis/preview/filter-options', 'GET', [
                'file_path' => $relativePath,
                'column_index' => array_search('kanca', $headers, true),
                'active_filters_json' => '{}',
            ]);

            $response = $controller->previewFilterOptions($request);
            $payload = json_decode((string) $response->getContent(), true);

            $this->assertSame('success', $payload['status'] ?? null);
            $this->assertContains('KC Banyuwangi', $payload['values'] ?? []);
            $this->assertContains('KC Jember', $payload['values'] ?? []);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_performance_pis_filtered_rows_endpoint_returns_dynamic_rows_beyond_sample(): void
    {
        $controller = new ImportPerformancePisPerProdukController();
        $relativePath = 'performance_pis_imports/pnps_filtered_rows_test.csv';
        $csvPath = Storage::path($relativePath);
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        $headers = $this->performancePisHeaders();
        $kancaIndex = array_search('kanca', $headers, true);
        $rows = [implode(',', $headers)];
        for ($i = 1; $i <= 250; $i++) {
            $rows[] = $this->buildPerformancePisRow([
                'no' => (string) $i,
                'kode_kanca' => '07',
                'kanca' => 'KC Banyuwangi',
                'nama_perusahaan' => 'PT SAMPLE BANYUWANGI',
            ]);
        }
        $rows[] = $this->buildPerformancePisRow([
            'no' => '251',
            'kode_kanca' => '08',
            'kanca' => 'KC Jember',
            'nama_perusahaan' => 'PT SAMPLE JEMBER',
        ]);

        file_put_contents($csvPath, implode("\n", $rows) . "\n");
        session(['performance_pis_periode' => '2026-03-31']);

        try {
            $request = Request::create('/import/performance-pis/preview/filtered-rows', 'GET', [
                'file_path' => $relativePath,
                'active_filters_json' => json_encode([
                    $kancaIndex => ['KC Jember'],
                ]),
                'limit' => 100,
            ]);

            $response = $controller->previewFilteredRows($request);
            $payload = json_decode((string) $response->getContent(), true);

            $this->assertSame('success', $payload['status'] ?? null);
            $this->assertSame(1, $payload['returned_rows'] ?? null);
            $this->assertSame('KC Jember', $payload['rows'][0][$kancaIndex] ?? null);
            $this->assertSame('PT SAMPLE JEMBER', $payload['rows'][0][array_search('nama_perusahaan', $headers, true)] ?? null);
        } finally {
            @unlink($csvPath);
        }
    }

    private function performancePisHeaders(): array
    {
        $reflection = new ReflectionClass(ImportPerformancePisPerProdukController::class);

        return $reflection->getConstant('TARGET_COLUMNS');
    }

    private function buildPerformancePisRow(array $overrides): string
    {
        $headers = $this->performancePisHeaders();
        $row = array_fill(0, count($headers), '');
        $headerMap = array_flip($headers);

        foreach ($overrides as $header => $value) {
            if (array_key_exists($header, $headerMap)) {
                $row[$headerMap[$header]] = $value;
            }
        }

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $row);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return rtrim((string) $csv, "\r\n");
    }

    private function invokeMethod(object $target, string $method, array $arguments)
    {
        $reflection = new ReflectionClass($target);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invokeArgs($target, $arguments);
    }
}
