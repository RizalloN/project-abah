<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportReportPhController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportReportPhIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Test ini hanya boleh berjalan di SQLite. Bukan di MySQL production. Periksa phpunit.xml.');
        }

        Schema::dropIfExists('lw325_ph');
        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->nullable();
            $table->date('periode')->nullable();
            $table->string('acctno')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('lw325_ph');

        parent::tearDown();
    }

    public function test_report_ph_integrity_validation_accepts_complete_period_rows(): void
    {
        $this->insertRows([
            ['uniqueid_namareport' => '2026-04-20_111_0_RPH', 'periode' => '2026-04-20'],
            ['uniqueid_namareport' => '2026-04-20_222_1_RPH', 'periode' => '2026-04-20'],
        ]);

        $this->assertReportPhIntegrity('2026-04-20', 2);

        $this->assertTrue(true);
    }

    public function test_report_ph_integrity_validation_rejects_missing_rows(): void
    {
        $this->insertRows([
            ['uniqueid_namareport' => '2026-04-20_111_0_RPH', 'periode' => '2026-04-20'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected=2, actual=1');

        $this->assertReportPhIntegrity('2026-04-20', 2);
    }

    public function test_report_ph_integrity_validation_rejects_malformed_unique_id(): void
    {
        $this->insertRows([
            ['uniqueid_namareport' => '2026-04-20_111_0_RPH', 'periode' => '2026-04-20'],
            ['uniqueid_namareport' => '-04-20_222_1_RPH', 'periode' => '2026-04-20'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('malformed_uniqueid=1');

        $this->assertReportPhIntegrity('2026-04-20', 2);
    }

    public function test_integrity_validation_rejects_an_empty_import_even_when_database_is_empty(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak ada baris data yang diimpor');

        $this->assertReportPhIntegrity('2026-10-18', 0);
    }

    public function test_csv_fallback_imports_meridiem_period_rows_into_database(): void
    {
        $result = $this->runCsvFallback([]);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(2, $result['total_success']);
        $this->assertSame(0, $result['total_failed']);
        $this->assertSame(2, \DB::table('lw325_ph')->where('periode', '2026-10-18')->count());
        $this->assertSame(['004501001234567', '004501001234568'], \DB::table('lw325_ph')->orderBy('acctno')->pluck('acctno')->all());
    }

    public function test_csv_fallback_with_no_matching_rows_returns_failure(): void
    {
        $result = $this->runCsvFallback([3 => ['KC Tidak Ada']]);

        $this->assertSame('failed', $result['status']);
        $this->assertSame(0, $result['total_success']);
        $this->assertStringContainsString('Tidak ada data yang diimpor', $result['message']);
        $this->assertSame(0, \DB::table('lw325_ph')->count());
    }

    public function test_rejected_bulk_stage_preserves_original_source_for_csv_fallback(): void
    {
        Storage::fake('local');
        $relative = 'report_ph_imports/preserve_source.csv';
        $headers = array_merge(['Textbox3'], (new \ReflectionClass(ImportReportPhController::class))->getConstant('TARGET_COLUMNS'));
        $data = ['Textbox3' => '1', 'periode' => '10/18/2026 12:00:00 AM', 'acctno' => '004501001234567', 'kanwil' => 'KANWIL MALANG', 'kanca' => 'KC Madiun', 'unit' => 'KC Madiun', 'nama_debitur' => 'DEBITUR UJI', 'cif1' => 'CIF1', 'tgl_ph' => '18/10/2025', 'pokok' => '100.00'];
        Storage::put($relative, '');
        $sourcePath = Storage::path($relative);
        $handle = fopen($sourcePath, 'w');
        fputcsv($handle, $headers);
        fputcsv($handle, array_map(static fn ($header) => $data[$header] ?? '', $headers));
        fclose($handle);

        $result = app(ImportReportPhController::class)->executeQueuedImport([
            'params' => [
                'file_path' => $relative,
                'staged_csv_path' => $sourcePath,
                'periode' => '2026-10-17',
                'selected_columns' => [0, 1],
            ],
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('periode file tidak konsisten', $result['message']);
        $this->assertFileExists($sourcePath);
        $this->assertSame(0, \DB::table('lw325_ph')->count());
    }

    private function runCsvFallback(array $filters): array
    {
        $path = tempnam(storage_path('framework/testing'), 'ph_fallback_');
        file_put_contents($path, implode("\n", [
            'Textbox3,PERIODE,ACCTNO,KANWIL,KANCA,UNIT,NAMA_DEBITUR,CIF1,TGL_PH',
            '1,10/18/2026 12:00:00 AM,004501001234567,KANWIL MALANG,KC Madiun,KC Madiun,DEBITUR UJI,CIF1,18/10/2025',
            '2,10/18/2026 12:00:00 AM,004501001234568,KANWIL MALANG,KC Madiun,KC Madiun,DEBITUR UJI,CIF2,18/10/2025',
        ]));

        try {
            $controller = app(ImportReportPhController::class);

            return (new \ReflectionMethod($controller, 'executeQueuedCsvFallback'))->invoke(
                $controller,
                $path,
                [0, 1],
                $filters,
                ['periode' => '2026-10-18', 'total_rows' => 2],
                static function (string $event, array $payload): void {}
            );
        } finally {
            @unlink($path);
        }
    }

    private function insertRows(array $rows): void
    {
        foreach ($rows as $row) {
            \DB::table('lw325_ph')->insert($row);
        }
    }

    private function assertReportPhIntegrity(string $periode, int $expectedRows): void
    {
        $controller = app(ImportReportPhController::class);
        $method = new \ReflectionMethod($controller, 'assertReportPhImportIntegrity');
        $method->setAccessible(true);
        $method->invoke($controller, $periode, $expectedRows);
    }
}
