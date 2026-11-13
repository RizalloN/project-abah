<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Http\Controllers\Report\SuspectReaktivasiReportController;
use App\Models\User;
use App\Services\Import\ImportDuplicateGuardService;
use App\Services\Import\SuspectReaktivasiSourceService;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class SuspectReaktivasiReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Storage::fake('local');
        Storage::makeDirectory('excel_imports');
        Schema::create('nama_report', function (Blueprint $table): void {
            $table->integer('id_report')->primary();
            $table->string('nama_report');
            $table->string('table_name');
            $table->boolean('active');
            $table->string('import_controller');
            $table->boolean('requires_manual_periode');
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_09_000001_create_suspect_reaktivasi_pinjaman_report.php'))->up();
        $user = new User;
        $user->id = 1;
        $user->role = 'admin';
        $user->pn = '90179583';
        $this->actingAs($user);
    }

    public function test_original_workbook_filters_area6_and_preserves_accounts_dates_and_amounts(): void
    {
        $stats = app(SuspectReaktivasiSourceService::class)->stage($this->workbook(), Storage::path('excel_imports/staged.csv'));
        $this->assertSame(5, $stats['source_rows']);
        $this->assertSame(4, $stats['imported_rows']);
        $this->assertSame(1, $stats['excluded_rows']);
        $this->assertSame('2026-10-31', $stats['periode']);
        $records = $this->importStaged();
        $this->assertCount(4, $records);
        $this->assertSame('000000000000001', $records[0]['norek']);
        $this->assertSame('1250.55', $records[0]['outstanding']);
        $this->assertSame('2026-11-30', $records[0]['bulan_laporan']);
        $this->assertSame('1x Reaktivasi', $records[0]['keterangan']);
        $this->assertSame(['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'], array_column($records, 'cabang'));
        $this->assertSame(4, DB::table(SuspectReaktivasiSourceService::TABLE)->whereNotNull('uniqueid_namareport')->count());
    }

    public function test_upload_uses_normalization_before_the_standard_preview_and_queue(): void
    {
        $source = $this->workbook();
        $request = Request::create('/import-excel/upload', 'POST', ['id_report' => 1], [], [
            'file' => new UploadedFile($source, 'LAPORAN_SUSPECT_REAKTIVASI_PINJAMAN.xlsx', null, null, true),
        ]);
        app(ImportExcelController::class)->uploadExcel($request);
        $path = session('excel_path');
        $this->assertStringEndsWith('.csv', $path);
        $preview = (new ReflectionMethod(ImportExcelController::class, 'prepareCsvPreviewPayload'))->invoke(new ImportExcelController, Storage::path($path));
        $this->assertSame(SuspectReaktivasiSourceService::columns(), $preview['headers']);
        $this->assertSame(4, $preview['total_rows']);
        $this->assertCount(4, $preview['preview']);
    }

    public function test_branch_and_unit_filters_apply_to_rows_totals_and_distributions(): void
    {
        $this->seedSource();
        $data = app(SuspectReaktivasiReportController::class)->index(Request::create('/', 'GET', ['cabang' => 'KC Madiun', 'nama_uker' => 'UNIT SAMPLE Madiun']))->getData();
        $this->assertSame(1, $data['summary']->accounts);
        $this->assertSame(1250.55, (float) $data['summary']->outstanding);
        $this->assertSame(['KC Madiun'], $data['distribution']->pluck('cabang')->all());
        $this->assertSame(1, $data['rows']->total());
        $this->assertSame(['UNIT SAMPLE Madiun'], $data['units']->all());
    }

    public function test_chunk_upload_also_normalizes_the_original_workbook(): void
    {
        $source = file_get_contents($this->workbook());
        $controller = new ImportExcelController;
        $init = $controller->initExcelChunkUpload(Request::create('/', 'POST', [
            'original_name' => 'suspect.xlsx', 'total_size' => strlen($source), 'total_chunks' => 1, 'id_report' => 1,
        ]))->getData(true);
        $controller->uploadExcelChunk(Request::create('/', 'POST', [
            'upload_id' => $init['upload_id'], 'chunk_index' => 0, 'total_chunks' => 1,
        ], [], ['file' => UploadedFile::fake()->createWithContent('part.bin', $source)]));
        $result = $controller->finalizeExcelChunkUpload(Request::create('/', 'POST', [
            'upload_id' => $init['upload_id'], 'total_chunks' => 1, 'original_name' => 'suspect.xlsx', 'id_report' => 1,
        ]));
        $this->assertSame(200, $result->getStatusCode());
        $this->assertStringEndsWith('.csv', session('excel_path'));
        $preview = (new ReflectionMethod($controller, 'prepareCsvPreviewPayload'))->invoke($controller, Storage::path(session('excel_path')));
        $this->assertSame(4, $preview['total_rows']);
    }

    public function test_branch_scope_cannot_be_widened_by_request_filter(): void
    {
        $this->seedSource();
        $data = UserBranchScope::runAs(UserBranchScope::forKey('madiun'), fn () => app(SuspectReaktivasiReportController::class)->index(Request::create('/', 'GET', ['cabang' => 'KC Magetan']))->getData());
        $this->assertSame(1, $data['summary']->accounts);
        $this->assertSame('KC Madiun', $data['branch']);
        $this->assertSame(['KC Madiun'], $data['rows']->pluck('cabang')->unique()->all());
    }

    public function test_frequency_is_exact_and_filters_all_report_values(): void
    {
        $this->seedSource();
        DB::table(SuspectReaktivasiSourceService::TABLE)->where('cabang', 'KC Magetan')->update(['keterangan' => '10x Reaktivasi', 'flag_restruk' => 'Restruk']);
        DB::table(SuspectReaktivasiSourceService::TABLE)->where('cabang', 'KC Ngawi')->update(['keterangan' => '2x Reaktivasi']);
        $data = app(SuspectReaktivasiReportController::class)->index(Request::create('/', 'GET', ['reaktivasi' => '1']))->getData();
        $this->assertSame(2, $data['summary']->accounts);
        $this->assertSame(2501.10, (float) $data['summary']->outstanding);
        $this->assertSame(0, (int) $data['summary']->restruk);
        $this->assertSame(['KC Madiun', 'KC Ponorogo'], $data['distribution']->pluck('cabang')->all());
        $this->assertSame(['1x Reaktivasi'], $data['categories']->pluck('keterangan')->all());
        $this->assertSame(2, $data['rows']->total());
        $this->assertSame([1, 2, 10], $data['frequencies']->pluck('value')->all());
        $this->assertSame([2, 1, 1], $data['frequencies']->pluck('count')->all());
    }

    public function test_frequency_combines_with_unit_branch_scope_and_json_results(): void
    {
        $this->seedSource();
        DB::table(SuspectReaktivasiSourceService::TABLE)->where('cabang', 'KC Madiun')->update(['keterangan' => '2x Reaktivasi', 'flag_restruk' => 'Restruk']);
        $request = Request::create('/', 'GET', ['cabang' => 'KC Magetan', 'nama_uker' => 'UNIT SAMPLE Madiun', 'reaktivasi' => '2'], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $response = UserBranchScope::runAs(UserBranchScope::forKey('madiun'), fn () => app(SuspectReaktivasiReportController::class)->index($request));
        $data = $response->getData(true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $data['accounts']);
        $this->assertSame('KC Madiun', $data['filters']['cabang']);
        $this->assertSame(2, $data['filters']['reaktivasi']);
        $this->assertSame(['UNIT SAMPLE Madiun'], $data['units']);
        $this->assertSame([['value' => 2, 'label' => '2 kali reaktivasi', 'count' => 1]], $data['frequencies']);
        $this->assertStringContainsString('1.250,55', $data['html']);
        $this->assertStringContainsString('2 kali reaktivasi', $data['html']);
        $this->assertStringNotContainsString('KC Magetan', $data['html']);
        $this->assertStringNotContainsString('<html', $data['html']);
    }

    public function test_missing_frequency_preserves_selection_and_returns_empty_results(): void
    {
        $this->seedSource();
        $data = app(SuspectReaktivasiReportController::class)->index(Request::create('/', 'GET', ['reaktivasi' => '5']))->getData();
        $this->assertSame(0, $data['summary']->accounts);
        $this->assertSame(0.0, (float) $data['summary']->outstanding);
        $this->assertSame(0, $data['rows']->total());
        $this->assertTrue($data['distribution']->isEmpty());
        $this->assertSame(5, $data['frequency']);
        $this->assertSame(0, $data['frequencies']->last()['count']);
    }

    public function test_invalid_frequency_is_rejected_instead_of_widening_results(): void
    {
        $this->expectException(ValidationException::class);
        app(SuspectReaktivasiReportController::class)->index(Request::create('/', 'GET', ['reaktivasi' => '2x']));
    }

    public function test_duplicate_accounts_fail_before_any_staging_is_accepted(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rekening ganda');
        app(SuspectReaktivasiSourceService::class)->stage($this->workbook(true), Storage::path('excel_imports/staged.csv'));
    }

    public function test_new_table_has_duplicate_import_slot_protection(): void
    {
        $policy = app(ImportDuplicateGuardService::class)->getPolicyForTable(SuspectReaktivasiSourceService::TABLE);
        $this->assertSame(['periode'], $policy['slot_columns']);
    }

    public function test_empty_report_renders_an_actionable_empty_state(): void
    {
        $view = app(SuspectReaktivasiReportController::class)->index(Request::create('/'));
        $this->assertSame(0, $view->getData()['summary']->accounts);
        $this->assertStringContainsString('Belum ada rekening untuk ditampilkan', $view->render());
    }

    private function seedSource(): void
    {
        app(SuspectReaktivasiSourceService::class)->stage($this->workbook(), Storage::path('excel_imports/staged.csv'));
        $this->importStaged();
    }

    private function importStaged(): array
    {
        $controller = new ImportExcelController;
        $handle = fopen(Storage::path('excel_imports/staged.csv'), 'rb');
        $headers = fgetcsv($handle);
        $context = (new ReflectionMethod($controller, 'buildImportContext'))->invoke($controller, SuspectReaktivasiSourceService::TABLE, $headers);
        $mapper = new ReflectionMethod($controller, 'mapExcelRowForInsert');
        $records = [];
        while (($row = fgetcsv($handle)) !== false) {
            $record = $mapper->invoke($controller, $row, $headers, $context, '2026-11-09 12:00:00');
            $this->assertIsArray($record);
            DB::table(SuspectReaktivasiSourceService::TABLE)->insert($record);
            $records[] = $record;
        }
        fclose($handle);

        return $records;
    }

    private function workbook(bool $duplicate = false): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('A1', 'LAPORAN SUSPECT REAKTIVASI PINJAMAN');
        $sheet->setCellValue('A2', 'POSISI');
        $sheet->setCellValue('D2', '31 Oct 2026');
        foreach ([0 => 'NO', 1 => 'KANWIL', 5 => 'KANCA', 7 => 'UNIT KERJA', 9 => 'NOREK', 10 => 'CIFNO', 11 => 'SNAME', 15 => 'OUTSTANDING', 29 => 'KETERANGAN', 32 => 'AO PENGELOLA'] as $index => $header) {
            $sheet->setCellValue([$index + 1, 4], $header);
        }
        foreach ([['45', 'Madiun'], ['49', 'Magetan'], ['57', 'Ngawi'], ['70', 'Ponorogo'], ['7', 'Banyuwangi']] as $index => [$code, $city]) {
            $row = array_fill(0, 33, null);
            foreach ([1 => 'R', 4 => 'KANWIL MALANG', 5 => $code, 6 => 'KC '.$city, 7 => $code, 8 => 'UNIT SAMPLE '.$city, 9 => str_pad((string) ($duplicate ? 1 : $index + 1), 15, '0', STR_PAD_LEFT), 10 => 'CIF'.$index, 11 => 'Debitur '.$index, 12 => '42209', 13 => 'RI', 14 => 'IDR', 15 => 1250.55, 29 => '1x Reaktivasi', 30 => 'Tidak Restruk', 32 => '00012345 - AO Sample'] as $key => $value) {
                $row[$key] = $value;
            }
            foreach ([16, 17, 18, 19, 20, 21, 22, 23, 25, 26, 27, 28] as $dateIndex) {
                $row[$dateIndex] = '2026-11-30';
            }
            $sheet->fromArray([$row], null, 'A'.($index + 5));
        }
        $path = Storage::path('excel_imports/source.xlsx');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
