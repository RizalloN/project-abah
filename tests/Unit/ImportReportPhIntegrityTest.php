<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportReportPhController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportReportPhIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('lw325_ph');
        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->nullable();
            $table->date('periode')->nullable();
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
