<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportExcelChunkUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        \Illuminate\Support\Facades\Schema::dropIfExists('nama_report');
        \Illuminate\Support\Facades\Schema::create('nama_report', function ($table) {
            $table->increments('id_report');
            $table->string('nama_report');
            $table->string('table_name');
            $table->timestamps();
        });

        DB::table('nama_report')->insert([
            'id_report' => 28,
            'nama_report' => 'LW321PN - Kolektibilitas dan Tunggakan Per AO',
            'table_name' => 'lw321pn',
        ]);

        $user = new User();
        $user->id = 1;
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $this->actingAs($user);
    }

    public function test_chunk_upload_lifecycle_for_lw321pn_csv(): void
    {
        $controller = app(ImportExcelController::class);

        $reportId = DB::table('nama_report')->where('table_name', 'lw321pn')->value('id_report') ?? 28;

        $contentChunk1 = "PERIODE,KODE_KANWIL,KANWIL,KODE_KANCA,KANCA\n23/08/2026,R,KANWIL MALANG,45,KC Madiun\n";
        $totalContent = $contentChunk1;
        $totalSize = strlen($totalContent);
        $originalName = 'test_lw321pn.csv';

        // 1. Init
        $initRequest = Request::create('/import-excel/upload-chunk/init', 'POST', [
            'original_name' => $originalName,
            'total_size' => $totalSize,
            'total_chunks' => 1,
            'id_report' => $reportId,
        ]);
        $initResponse = $controller->initExcelChunkUpload($initRequest);
        $this->assertSame(200, $initResponse->getStatusCode());
        $initData = json_decode($initResponse->getContent(), true);
        $this->assertSame('success', $initData['status']);
        $this->assertStringStartsWith('excel_', $initData['upload_id']);

        $uploadId = $initData['upload_id'];

        // 2. Upload Chunk 0
        $file0 = UploadedFile::fake()->createWithContent('part_0.bin', $contentChunk1);
        $chunk0Request = Request::create('/import-excel/upload-chunk', 'POST', [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'total_chunks' => 1,
        ], [], ['file' => $file0]);
        $chunk0Response = $controller->uploadExcelChunk($chunk0Request);
        $this->assertSame(200, $chunk0Response->getStatusCode());

        // 3. Finalize
        $finalizeRequest = Request::create('/import-excel/upload-chunk/finalize', 'POST', [
            'upload_id' => $uploadId,
            'total_chunks' => 1,
            'original_name' => $originalName,
            'id_report' => $reportId,
        ]);
        $finalizeResponse = $controller->finalizeExcelChunkUpload($finalizeRequest);
        $this->assertSame(200, $finalizeResponse->getStatusCode());
        $finalizeData = json_decode($finalizeResponse->getContent(), true);
        $this->assertSame('success', $finalizeData['status']);
        $this->assertNotEmpty($finalizeData['cache_key']);
        $this->assertStringContainsString('prepare-preview', $finalizeData['redirect']);

        // Check session
        $this->assertSame($reportId, (int) session('active_id_report'));
        $this->assertNotEmpty(session('excel_path'));

        // Check assembled file
        $assembledPath = Storage::path(session('excel_path'));
        $this->assertFileExists($assembledPath);
        $this->assertSame($totalContent, file_get_contents($assembledPath));

        // Cleanup
        if (file_exists($assembledPath)) {
            @unlink($assembledPath);
        }
    }

    public function test_multi_chunk_upload_assembly(): void
    {
        $controller = app(ImportExcelController::class);
        $reportId = 28;

        $chunk0Content = str_repeat('X', 8 * 1024 * 1024);
        $chunk1Content = "Y_END";
        $totalContent = $chunk0Content . $chunk1Content;
        $totalSize = strlen($totalContent);
        $originalName = 'large_lw321pn.csv';

        // 1. Init
        $initRequest = Request::create('/import-excel/upload-chunk/init', 'POST', [
            'original_name' => $originalName,
            'total_size' => $totalSize,
            'total_chunks' => 2,
            'id_report' => $reportId,
        ]);
        $initResponse = $controller->initExcelChunkUpload($initRequest);
        $this->assertSame(200, $initResponse->getStatusCode());
        $uploadId = json_decode($initResponse->getContent(), true)['upload_id'];

        // 2. Chunk 0
        $file0 = UploadedFile::fake()->createWithContent('part_0.bin', $chunk0Content);
        $chunk0Response = $controller->uploadExcelChunk(Request::create('/import-excel/upload-chunk', 'POST', [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'total_chunks' => 2,
        ], [], ['file' => $file0]));
        $this->assertSame(200, $chunk0Response->getStatusCode());

        // 3. Chunk 1
        $file1 = UploadedFile::fake()->createWithContent('part_1.bin', $chunk1Content);
        $chunk1Response = $controller->uploadExcelChunk(Request::create('/import-excel/upload-chunk', 'POST', [
            'upload_id' => $uploadId,
            'chunk_index' => 1,
            'total_chunks' => 2,
        ], [], ['file' => $file1]));
        $this->assertSame(200, $chunk1Response->getStatusCode());

        // 4. Finalize
        $finalizeResponse = $controller->finalizeExcelChunkUpload(Request::create('/import-excel/upload-chunk/finalize', 'POST', [
            'upload_id' => $uploadId,
            'total_chunks' => 2,
            'original_name' => $originalName,
            'id_report' => $reportId,
        ]));
        $this->assertSame(200, $finalizeResponse->getStatusCode());

        $assembledPath = Storage::path(session('excel_path'));
        $this->assertFileExists($assembledPath);
        $this->assertSame($totalSize, filesize($assembledPath));

        if (file_exists($assembledPath)) {
            @unlink($assembledPath);
        }
    }
}
