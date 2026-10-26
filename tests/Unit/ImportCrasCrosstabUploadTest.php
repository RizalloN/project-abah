<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportCrasController;
use App\Models\User;
use App\Services\Import\CrasSourceService;
use App\Services\Import\Crosstab\CrosstabNormalizationNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportCrasCrosstabUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $user = new User;
        $user->id = 1;
        $this->actingAs($user);
    }

    public function test_crosstab_workbook_is_normalized_before_the_strict_cras_validation(): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setCellValueExplicit([1, 1], 'SSA CRAS - Area 6', DataType::TYPE_STRING);
        foreach (CrasSourceService::SOURCE_HEADERS as $index => $header) {
            $sheet->setCellValueExplicit([$index + 1, 2], $header, DataType::TYPE_STRING);
        }
        $sheet->setCellValue([1, 3], 46142); // April 30, 2026 as an Excel date
        $sheet->setCellValueExplicit([2, 3], 'KC Test', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit([3, 3], '7', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit([4, 3], 'UNIT A', DataType::TYPE_STRING);
        $sheet->setCellValue([20, 3], 1000);
        $sheet->setCellValue([20, 4], 2500);
        foreach (['A', 'B', 'C', 'D'] as $column) {
            $sheet->mergeCells($column.'3:'.$column.'4');
        }
        Storage::makeDirectory('cras_uploads');
        $relative = 'cras_uploads/source.xlsx';
        (new Xlsx($book))->save(Storage::path($relative));
        $book->disconnectWorksheets();
        session(['cras_file' => $relative, 'active_id_report' => 40]);

        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));
        $this->app->instance('request', $request);
        $response = app(ImportCrasController::class)->preparePreviewStream($request);
        $events = '';
        // The stream flushes after every event, so collect each flushed chunk.
        ob_start(static function (string $chunk) use (&$events): string {
            $events .= $chunk;

            return '';
        });
        $response->sendContent();
        ob_end_clean();

        $this->assertStringContainsString('event: ready', $events, $events);
        $normalized = session('cras_file');
        $this->assertStringEndsWith('_crosstab.txt', $normalized);
        Storage::assertMissing($relative);
        $state = (new CrasSourceService)->inspect(Storage::path($normalized));
        $this->assertSame('2026-04-30', $state['period']);
        $this->assertSame(2, $state['total_rows']);
        $this->assertSame(['April 30, 2026', 'KC Test', '7', 'UNIT A'], array_slice($state['preview_rows'][1], 0, 4));
        $this->assertSame('2500', $state['preview_rows'][1][19]);
        $this->assertSame('merge', CrosstabNormalizationNotice::forPath($normalized)['mode']);
    }
}
