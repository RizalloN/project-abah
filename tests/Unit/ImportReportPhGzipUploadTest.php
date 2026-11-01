<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportReportPhController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class ImportReportPhGzipUploadTest extends TestCase
{
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_gzip_encoded_upload_is_stored_as_the_original_plain_file(): void
    {
        $csv = $this->sampleCsv();

        $response = app(ImportReportPhController::class)->upload($this->gzipUploadRequest($csv, strlen($csv)));

        $path = (string) $response->getData(true)['path'];
        $this->assertStringStartsWith('report_ph_imports/', $path);
        $this->assertStringEndsWith('.txt', $path);
        $this->assertSame($csv, Storage::disk('local')->get($path));
        $this->assertSame($path, session('report_ph_file'));
    }

    public function test_gzip_encoded_upload_rejects_size_mismatch(): void
    {
        $csv = $this->sampleCsv();

        try {
            app(ImportReportPhController::class)->upload($this->gzipUploadRequest($csv, strlen($csv) + 10));
            $this->fail('Upload dengan ukuran asli yang tidak cocok harus ditolak.');
        } catch (ValidationException) {
            $this->assertSame([], Storage::disk('local')->files('report_ph_imports'));
        }
    }

    public function test_gzip_encoded_upload_rejects_plain_payload(): void
    {
        $csv = $this->sampleCsv();
        $plainPath = $this->tempFile($csv);
        $request = $this->uploadRequest(new UploadedFile($plainPath, 'lw325.txt.gz', 'application/gzip', null, true), strlen($csv));

        $this->expectException(ValidationException::class);

        app(ImportReportPhController::class)->upload($request);
    }

    public function test_column_scan_matches_full_scan_for_filter_columns(): void
    {
        $path = $this->tempFile($this->sampleCsv());
        $controller = app(ImportReportPhController::class);
        $context = $this->invoke($controller, 'buildCsvContext', $path);
        $kancaIndex = array_search('kanca', $context['headers'], true);
        $periodeIndex = array_search('periode', $context['headers'], true);

        $fullRows = [];
        $this->invoke($controller, 'iterateMappedPreviewRows', $path, $context, function (array $row) use (&$fullRows): bool {
            $fullRows[] = $row;

            return true;
        });

        $partialRows = [];
        $this->invoke($controller, 'iterateMappedPreviewColumns', $path, $context, [$kancaIndex, $periodeIndex], function (array $row) use (&$partialRows): bool {
            $partialRows[] = $row;

            return true;
        });

        $this->assertCount(2, $fullRows);
        $this->assertCount(count($fullRows), $partialRows);
        foreach ($fullRows as $rowIndex => $fullRow) {
            $this->assertSame($fullRow[$kancaIndex], $partialRows[$rowIndex][$kancaIndex]);
            $this->assertSame($fullRow[$periodeIndex], $partialRows[$rowIndex][$periodeIndex]);
            $this->assertCount(count($fullRow), $partialRows[$rowIndex]);
        }
        $this->assertNull($partialRows[0][array_search('pokok', $context['headers'], true)]);
    }

    private function sampleCsv(): string
    {
        return implode("\n", [
            'textbox1,Textbox38,Textbox45',
            'Laporan Nominatif PH,Periode Data : 26/10/2026,Date Printed : 28 Oct 2026 08:45:16 AM',
            '',
            'Textbox3,PERIODE,ACCTNO,KANWIL,KANCA,UNIT,NAMA_DEBITUR,CIF1,FKSEGMEN,SEGMEN_DASHBOARD,DESCRIPTION,PRODUK_DASHBOARD,TGL_PH,TGL_REALISASI,CURTYP,POKOK,BUNGA',
            '1,10/26/2026 12:00:00 AM,814601006413100,KANWIL MALANG,KC Ponorogo,UNIT PASAR CONDONG PONOROGO,AMI WASTUTI,AQW7956,11400,Micro,KUR Mikro Baru,KUR-Mikro,29/07/2026,30/09/2023,IDR,"2,316,974.00","-1,624,856.97"',
            '2,10/26/2026 12:00:00 AM,814601001310103,KANWIL MALANG,KC Madiun,UNIT PASAR BESAR MADIUN,EDI MULYONO,ECT3553,11100,Micro,Kupedes,Kupedes,29/07/2019,09/03/2018,IDR,"124,394,781.00","6,266,940.00"',
        ]) . "\n";
    }

    private function gzipUploadRequest(string $content, int $originalSize): Request
    {
        $gzipPath = $this->tempFile(gzencode($content, 6));

        return $this->uploadRequest(new UploadedFile($gzipPath, 'lw325.txt.gz', 'application/gzip', null, true), $originalSize);
    }

    private function uploadRequest(UploadedFile $file, int $originalSize): Request
    {
        $request = Request::create('/import/report-ph/upload', 'POST', [
            'id_report' => 15,
            'file_encoding' => 'gzip',
            'original_name' => 'lw325.txt',
            'original_size' => $originalSize,
        ], [], ['file' => $file], ['HTTP_ACCEPT' => 'application/json']);
        $request->setLaravelSession($this->app['session.store']);

        return $request;
    }

    private function tempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rph');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function invoke(object $target, string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($target, ...$arguments);
    }
}
