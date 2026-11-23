<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportCleanupController;
use App\Http\Controllers\Import\ImportPerformancePisPerProdukController;
use App\Models\User;
use App\Services\Import\ImportCleanupService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use ReflectionClass;
use Tests\TestCase;
use ZipArchive;

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

    public function test_preview_enables_portaled_filter_dropdowns_for_wide_table(): void
    {
        $controller = new ImportPerformancePisPerProdukController();
        $relativePath = 'performance_pis_imports/pnps_portal_preview_test.csv';
        $csvPath = Storage::path($relativePath);
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        $headers = $this->performancePisHeaders();
        file_put_contents($csvPath, implode("\n", [
            'posisi',
            '30 June 2026',
            '',
            implode(',', $headers),
            $this->buildPerformancePisRow([
                'no' => '1',
                'kode_kanca' => '45',
                'kanca' => 'KC Madiun',
            ]),
        ]) . "\n");

        session([
            'performance_pis_file' => $relativePath,
            'performance_pis_periode' => '2026-06-30',
        ]);

        try {
            $response = $controller->preview(Request::create('/import/performance-pis/preview', 'GET', [
                'file_path' => $relativePath,
            ]));

            $this->assertInstanceOf(\Illuminate\View\View::class, $response);
            $this->assertTrue((bool) ($response->getData()['portalFilterDropdowns'] ?? false));
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_build_csv_context_skips_posisi_metadata_before_header(): void
    {
        $controller = new ImportPerformancePisPerProdukController();
        $csvPath = tempnam(sys_get_temp_dir(), 'pnps_posisi_meta_') . '.csv';
        file_put_contents($csvPath, implode("\n", [
            'posisi',
            '15 May 2026',
            '',
            'no,kode_kanwil,kanwil,kode_kanca,kanca,kode_uker,uker,corporate_code,nama_perusahaan,jenis_mitra,jenis_perusahaan,tipe_produk,nomor_rekening,nama_rekening,saldo_britama_kerjasama,tanggal_pembuatan_rekening,pn_rm_dana_brinets,pn_rm_dana_pis2,nomor_hp,email,flag_briguna,flag_cc',
            '1,R,KANWIL MALANG,7,KC Banyuwangi,7,KC Banyuwangi,0000039,TNI AD - Pusat Pendidikan Polisi Militer,,,TN,701104113505,ZANU AGUS LESTARI,"6,321,767.00",9/7/2021 12:00:00 AM,,,081000000000,,Y,N',
        ]) . "\n");

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [
                $csvPath,
                '2026-05-15',
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

            $this->assertSame(4, $context['header_line']);
            $this->assertSame(4, $context['source_indexes']['kanca']);
            $this->assertCount(1, $rows);
            $this->assertSame('2026-05-15', $rows[0][0]);
            $this->assertSame('KC Banyuwangi', $rows[0][4]);
            $this->assertSame('TNI AD - Pusat Pendidikan Polisi Militer', $rows[0][8]);
            $this->assertSame('6321767.00', $rows[0][14]);
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

    public function test_iterate_csv_data_rows_expands_quoted_comma_rows_with_trailing_semicolon(): void
    {
        $controller = new ImportPerformancePisPerProdukController();
        $csvPath = tempnam(sys_get_temp_dir(), 'pnps_wrapped_csv_') . '.csv';
        file_put_contents($csvPath, implode("\r\n", [
            'no,kode_kanwil,kanwil,kode_kanca,kanca,kode_uker,uker,corporate_code,nama_perusahaan,jenis_mitra,jenis_perusahaan,tipe_produk,nomor_rekening,nama_rekening,saldo_britama_kerjasama,tanggal_pembuatan_rekening,pn_rm_dana_brinets,pn_rm_dana_pis2,nomor_hp,email,flag_briguna,flag_cc;',
            '"1,R,KANWIL MALANG,7,KC Banyuwangi,7,KC Banyuwangi,0000039,TNI AD - Pusat Pendidikan Polisi Militer,,,TN,701104113505,ZANU AGUS LESTARI,""6,321,767.00"",9/7/2021 12:00:00 AM,,,081000000000,,Y,N";',
        ]) . "\r\n");

        try {
            $context = $this->invokeMethod($controller, 'buildCsvContext', [
                $csvPath,
                '2026-05-17',
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
            $this->assertSame('2026-05-17', $rows[0][0]);
            $this->assertSame('KC Banyuwangi', $rows[0][4]);
            $this->assertSame('TNI AD - Pusat Pendidikan Polisi Militer', $rows[0][8]);
            $this->assertSame('701104113505', $rows[0][12]);
            $this->assertSame('6321767.00', $rows[0][14]);
            $this->assertSame('081000000000', $rows[0][18]);
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
        session([
            'performance_pis_file' => $relativePath,
            'performance_pis_periode' => '2026-03-31',
        ]);

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
            session()->forget(['performance_pis_file', 'performance_pis_periode']);
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
        session([
            'performance_pis_file' => $relativePath,
            'performance_pis_periode' => '2026-03-31',
        ]);

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
            session()->forget(['performance_pis_file', 'performance_pis_periode']);
        }
    }

    public function test_chunked_upload_init_chunk_and_finalize_stores_file_and_sets_session(): void
    {
        $user = new User();
        $user->id = 999001;
        $this->be($user);

        $controller = new ImportPerformancePisPerProdukController();
        $chunkSizeBytes = (new ReflectionClass(ImportPerformancePisPerProdukController::class))
            ->getConstant('CHUNK_SIZE_BYTES');

        $part1Content = str_repeat("A", $chunkSizeBytes);
        $part2Content = "no,kode_kanwil,nomor_rekening\n1,001,123456\n";
        $totalSize = strlen($part1Content) + strlen($part2Content);

        $initRequest = Request::create('/import/performance-pis/upload-chunk/init', 'POST', [
            'id_report' => '25',
            'periode' => '2026-11-15',
            'original_name' => '2_PERFORMANCE_PIS_PER_PRODUCT_DETAIL-145.xlsx',
            'total_size' => $totalSize,
            'total_chunks' => 2,
        ]);

        $initResponse = $controller->initChunkUpload($initRequest);
        $initPayload = json_decode((string) $initResponse->getContent(), true);

        $this->assertSame(200, $initResponse->getStatusCode());
        $this->assertSame('success', $initPayload['status'] ?? null);
        $uploadId = (string) ($initPayload['upload_id'] ?? '');
        $this->assertMatchesRegularExpression('/^pis_[0-9a-f-]{36}$/', $uploadId);

        $tmpPart1 = tempnam(sys_get_temp_dir(), 'pis_p1_');
        $tmpPart2 = tempnam(sys_get_temp_dir(), 'pis_p2_');
        file_put_contents($tmpPart1, $part1Content);
        file_put_contents($tmpPart2, $part2Content);

        $assembledRelativePath = null;

        try {
            $chunk1Resp = $controller->uploadChunk(Request::create(
                '/import/performance-pis/upload-chunk',
                'POST',
                ['upload_id' => $uploadId, 'chunk_index' => 0, 'total_chunks' => 2],
                [],
                ['file' => new UploadedFile($tmpPart1, 'part0', 'application/octet-stream', null, true)]
            ));
            $this->assertSame(200, $chunk1Resp->getStatusCode());

            $chunk2Resp = $controller->uploadChunk(Request::create(
                '/import/performance-pis/upload-chunk',
                'POST',
                ['upload_id' => $uploadId, 'chunk_index' => 1, 'total_chunks' => 2],
                [],
                ['file' => new UploadedFile($tmpPart2, 'part1', 'application/octet-stream', null, true)]
            ));
            $this->assertSame(200, $chunk2Resp->getStatusCode());

            $finalizeResp = $controller->finalizeChunkUpload(Request::create(
                '/import/performance-pis/upload-chunk/finalize',
                'POST',
                [
                    'upload_id' => $uploadId,
                    'total_chunks' => 2,
                    'original_name' => '2_PERFORMANCE_PIS_PER_PRODUCT_DETAIL-145.xlsx',
                    'id_report' => '25',
                    'periode' => '2026-11-15',
                ]
            ));
            $finalizePayload = json_decode((string) $finalizeResp->getContent(), true);

            $this->assertSame(200, $finalizeResp->getStatusCode());
            $this->assertSame('success', $finalizePayload['status'] ?? null);
            $this->assertSame(route('import.performancepis.prepare-preview'), $finalizePayload['redirect'] ?? null);

            $assembledRelativePath = (string) session('performance_pis_file');
            $this->assertStringStartsWith('performance_pis_imports/', $assembledRelativePath);
            $this->assertStringEndsWith('.xlsx', $assembledRelativePath);
            $this->assertSame('2026-11-15', session('performance_pis_periode'));
            $this->assertSame('performance_pis', session('import_type'));

            $assembledAbsPath = Storage::path($assembledRelativePath);
            $this->assertFileExists($assembledAbsPath);
            $this->assertSame($totalSize, filesize($assembledAbsPath));
        } finally {
            @unlink($tmpPart1);
            @unlink($tmpPart2);
            if ($assembledRelativePath) {
                @unlink(Storage::path($assembledRelativePath));
            }
            $chunkDir = $this->invokeMethod($controller, 'chunkDirectory', [$uploadId]);
            if (is_dir($chunkDir)) {
                File::deleteDirectory($chunkDir);
            }
            session()->forget(['performance_pis_file', 'performance_pis_periode', 'active_id_report', 'import_type']);
        }
    }

    public function test_build_excel_context_reads_shared_strings_headers_from_xlsx(): void
    {
        $controller = new ImportPerformancePisPerProdukController();
        $xlsxPath = tempnam(sys_get_temp_dir(), 'pis_ss_') . '.xlsx';

        $headers = ['NO', 'KODE_KANWIL', 'KANWIL', 'KODE_KANCA', 'KANCA', 'KODE_UKER', 'UKER', 'NOMOR_REKENING'];
        $sharedXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($headers) . '" uniqueCount="' . count($headers) . '">';
        foreach ($headers as $h) {
            $sharedXml .= '<si><t>' . htmlspecialchars($h, ENT_XML1) . '</t></si>';
        }
        $sharedXml .= '</sst>';

        $sheetCells = '';
        foreach ($headers as $idx => $h) {
            $col = chr(65 + $idx);
            $sheetCells .= "<c r=\"{$col}1\" t=\"s\"><v>{$idx}</v></c>";
        }
        // Notice: intentionally omit <dimension> tag to also verify bounded dimension fallback scan
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData><row r="1">' . $sheetCells . '</row></sheetData></worksheet>';

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('xl/sharedStrings.xml', $sharedXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        try {
            $context = $this->invokeMethod($controller, 'buildExcelContext', [$xlsxPath, '2026-11-15']);
            $this->assertSame(1, $context['header_line']);
            $this->assertSame($headers, $context['source_headers']);
            $this->assertSame('2026-11-15', $context['posisi']);
        } finally {
            @unlink($xlsxPath);
        }
    }

    public function test_import_view_enables_chunked_upload_for_performance_pis(): void
    {
        $blade = file_get_contents(resource_path('views/import/index.blade.php'));
        $this->assertStringContainsString("import.performancepis.upload-chunk.init", (string) $blade);
        $this->assertStringContainsString("import.performancepis.upload-chunk'", (string) $blade);
        $this->assertStringContainsString("import.performancepis.upload-chunk.finalize", (string) $blade);
    }

    public function test_excel_staging_column_cache_is_bounded_to_column_letters_and_reads_inline_str(): void
    {
        $service = new \App\Services\Import\ExcelStagingService();
        $ref = new ReflectionClass($service);
        $getColIdx = $ref->getMethod('getColumnReferenceIndex');
        $getColIdx->setAccessible(true);

        for ($row = 1; $row <= 5000; $row++) {
            $this->assertSame(0, $getColIdx->invoke($service, "A{$row}"));
            $this->assertSame(27, $getColIdx->invoke($service, "AB{$row}"));
        }

        $cacheProp = $ref->getProperty('columnRefIndexCache');
        $cacheProp->setAccessible(true);
        $cache = $cacheProp->getValue($service);

        $this->assertCount(2, $cache);
        $this->assertSame(['A' => 0, 'AB' => 27], $cache);

        // Also test inlineStr rich-text concatenation via stageXlsxSheetWithHeadersToCsv
        $xlsxPath = tempnam(sys_get_temp_dir(), 'pis_inline_') . '.xlsx';
        $csvPath = tempnam(sys_get_temp_dir(), 'pis_inline_out_') . '.csv';
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="inlineStr"><is><t>No</t></is></c><c r="C1" t="inlineStr"><is><r><t>PN RM </t></r><r><t>Dana BRINETS</t></r></is></c></row>'
            . '<row r="2"><c r="A2"><v>1</v></c><c r="C2" t="inlineStr"><is><t>00123456</t></is></c></row>'
            . '</sheetData></worksheet>';

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        try {
            $result = $service->stageXlsxSheetWithHeadersToCsv($xlsxPath, ['No', 'PN RM Dana BRINETS'], $csvPath);
            $this->assertIsArray($result);
            $this->assertSame(1, $result['total_rows']);
            $content = file_get_contents($csvPath);
            $this->assertStringContainsString('00123456', (string) $content);
        } finally {
            @unlink($xlsxPath);
            @unlink($csvPath);
        }
    }

    public function test_performance_pis_strategy_and_drift_analyzer_resolve_header_aliases(): void
    {
        $strategy = new \App\Services\Import\Strategies\PerformancePisImportStrategy();
        $sourceHeaders = [
            'Product Type',
            'Alamat Email',
            "Relasi Briguna\n(Yes/No)",
            "Relasi Kartu Kredit\n(Yes/No)",
            'Nomor Rekening',
        ];

        $this->assertSame(
            ['tipe_produk', 'email', 'flag_briguna', 'flag_cc', 'Nomor Rekening'],
            $strategy->transformHeaders($sourceHeaders)
        );

        $canonicalizer = app(\App\Services\Import\SchemaDrift\SchemaDriftAnalyzer::class)
            ->canonicalizer('performance_pis_per_produk');
        $this->assertSame(
            ['tipe_produk', 'email', 'flag_briguna', 'flag_cc', 'nomor_rekening'],
            $canonicalizer($sourceHeaders)
        );
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
