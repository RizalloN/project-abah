<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImportExcelSsaUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Schema::dropIfExists('nama_report');
        Schema::create('nama_report', function ($table) {
            $table->increments('id_report');
            $table->string('nama_report');
            $table->string('table_name');
        });
        DB::table('nama_report')->insert([
            ['id_report' => 101, 'nama_report' => 'SSA Simpanan', 'table_name' => 'ssa_simpanan'],
            ['id_report' => 102, 'nama_report' => 'SSA Pinjaman', 'table_name' => 'ssa_pinjaman'],
        ]);
        $user = new User;
        $user->id = 1;
        $this->actingAs($user);
    }

    public static function uploadCases(): array
    {
        return [
            'simpanan normal' => [101, false],
            'simpanan chunk' => [101, true],
            'pinjaman normal' => [102, false],
            'pinjaman chunk' => [102, true],
        ];
    }

    #[DataProvider('uploadCases')]
    public function test_original_excel_is_normalized_before_preview_queue_and_chunk_state(int $reportId, bool $chunked): void
    {
        $source = $this->originalWorkbook($reportId);
        $controller = app(ImportExcelController::class);
        $filename = 'original.xlsx';
        if ($chunked) {
            $init = $controller->initExcelChunkUpload(Request::create('/', 'POST', [
                'original_name' => $filename, 'total_size' => strlen($source), 'total_chunks' => 1, 'id_report' => $reportId,
            ]));
            $this->assertSame(200, $init->getStatusCode());
            $uploadId = $init->getData(true)['upload_id'];
            foreach ([$source] as $index => $part) {
                $response = $controller->uploadExcelChunk(Request::create('/', 'POST', [
                    'upload_id' => $uploadId, 'chunk_index' => $index, 'total_chunks' => 1,
                ], [], ['file' => UploadedFile::fake()->createWithContent('part.bin', $part)]));
                $this->assertSame(200, $response->getStatusCode(), $response->getContent());
            }
            $response = $controller->finalizeExcelChunkUpload(Request::create('/', 'POST', [
                'upload_id' => $uploadId, 'total_chunks' => 1, 'original_name' => $filename, 'id_report' => $reportId,
            ], [], [], ['HTTP_ACCEPT' => 'application/json']));
        } else {
            $response = $controller->uploadExcel(Request::create('/', 'POST', ['id_report' => $reportId], [], [
                'file' => UploadedFile::fake()->createWithContent($filename, $source),
            ], ['HTTP_ACCEPT' => 'application/json']));
        }

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertSame('success', $response->getData(true)['status']);
        $path = session('excel_path');
        $this->assertStringEndsWith('.csv', $path);
        $this->assertSame($reportId, (int) session('active_id_report'));
        $handle = fopen(Storage::path($path), 'rb');
        $headers = fgetcsv($handle);
        $first = fgetcsv($handle);
        $second = fgetcsv($handle);
        $this->assertFalse(fgetcsv($handle));
        fclose($handle);
        $this->assertSame('2026-10-16', $second[0]);
        $this->assertSame('KC A', $second[1]);
        $this->assertSame('Unit A', $second[2]);
        $this->assertNotContains('', $second);
        if ($reportId === 101) {
            $this->assertSame('saldo', $headers[6]);
            $this->assertSame('1234567890.12', $first[6]);
            $this->assertSame(['Micro', '0.00'], array_slice($second, 5));
        } else {
            $this->assertSame(['1', 'Y', '0.00', '0', '0'], array_slice($second, 9));
        }
        $this->assertCount(1, Storage::files('excel_imports'));
    }

    private function originalWorkbook(int $reportId): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        if ($reportId === 101) {
            $sheet->fromArray([
                ['Month, Day, Year of Posisi', 'Nama Cabang', 'Nama Uker', 'Produk', 'Segmentasi', 'Segmen Kategorisasi Bisnis', null],
                ['16 Oktober 2026', 'KC A', 'Unit A', 'Giro', 'Ritel', 'Consumer', 1234567890.12],
                [null, null, null, null, null, 'Micro', 0],
            ], null, 'A1', true);
            $dimensions = range('A', 'E');
        } else {
            $sheet->fromArray([
                ['Month, Day, Year of Periode', 'Nama Cabang', 'Nama Uker', 'Produk', 'Produk_Dashboard', 'Segmen', 'Segmen Lama', 'SEGMEN_2025', 'Segmen_Dashboard', 'Kolektabilitas One Obligor', 'Flag Restruk', 'Baki Debet', 'Jumlah Debitur Aktif', 'Jumlah Rekening Aktif'],
                ['16 Oktober 2026', 'KC A', 'Unit A', 'Kupedes', 'Kupedes', 'Micro', 'Mikro', 'Micro', 'Micro', 1, 'N', 1234567890.12, 2, 3],
                [null, null, null, null, null, null, null, null, null, null, 'Y', 0, 0, 0],
            ], null, 'A1', true);
            $dimensions = range('A', 'J');
        }
        foreach ($dimensions as $column) {
            $sheet->mergeCells($column.'2:'.$column.'3');
        }
        $path = tempnam(sys_get_temp_dir(), 'ssa_upload_');
        try {
            (new Xlsx($book))->save($path);

            return file_get_contents($path);
        } finally {
            $book->disconnectWorksheets();
            @unlink($path);
        }
    }
}
