<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportFileBrimoController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportFileBrimoControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
    }

    public function test_brimo_import_does_not_fallback_when_configured_table_is_missing(): void
    {
        $controller = new ImportFileBrimoController();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing_brimo_table');

        $this->invokeMethod($controller, 'resolveTableName', [
            (object) [
                'nama_report' => 'User Brimo FIN',
                'table_name' => 'missing_brimo_table',
            ],
        ]);
    }

    public function test_brimo_upload_returns_json_redirect_for_ajax_uploader(): void
    {
        $controller = new class extends ImportFileBrimoController {
            protected function createSecureImportDirectory(): string
            {
                return sys_get_temp_dir();
            }

            protected function storeImportUpload(UploadedFile $file, string $directory): array
            {
                return [
                    'name' => 'source.rar',
                    'path' => __FILE__,
                    'extension' => 'rar',
                ];
            }

            protected function extractImportArchive(string $archivePath, string $directory): array
            {
                return [[
                    'name' => 'source.csv',
                    'path' => __FILE__,
                ]];
            }
        };

        $request = Request::create('/import/brimo/upload', 'POST', [
            'id_report' => 99,
        ], [], [
            'file' => UploadedFile::fake()->create('source.rar', 1, 'application/vnd.rar'),
        ], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $response = $controller->upload($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'status' => 'success',
            'redirect' => route('import.select'),
        ], $response->getData(true));
    }

    public function test_brimo_auto_filter_prioritizes_nama_kci_alias(): void
    {
        $controller = new ImportFileBrimoController();
        $headers = ['PERIODE', 'NAMA_KCI', 'MBDESC', 'BRDESC'];
        $uniqueValues = [
            1 => ['KC BLITAR', '0045 - KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'],
            2 => ['KC MADIUN', 'UNIT MAGETAN'],
            3 => ['UNIT A', 'UNIT B'],
        ];

        $selections = $this->invokeMethod($controller, 'buildInitialArea6Selections', [
            $headers,
            $uniqueValues,
            $this->invokeMethod($controller, 'defaultArea6PreviewColumnHints'),
        ]);

        $this->assertSame([
            '1' => ['0045 - KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'],
        ], $selections);
    }

    public function test_brimo_auto_filter_uses_the_single_column_with_best_area_coverage(): void
    {
        $controller = new ImportFileBrimoController();
        $headers = ['NAMA_KCI', 'MBDESC'];
        $uniqueValues = [
            0 => ['KC MADIUN'],
            1 => ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'],
        ];

        $selections = $this->invokeMethod($controller, 'buildInitialArea6Selections', [
            $headers,
            $uniqueValues,
            $this->invokeMethod($controller, 'defaultArea6PreviewColumnHints'),
        ]);

        $this->assertSame([
            '1' => ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'],
        ], $selections);
    }

    public function test_brimo_preview_is_bounded_and_keeps_nama_kci_auto_filter_complete(): void
    {
        Schema::create('nama_report', function ($table): void {
            $table->integer('id_report')->primary();
            $table->string('nama_report');
            $table->string('table_name');
        });
        Schema::create('user_brimo_rpt_v2', function ($table): void {
            $table->string('uniqueid_namareport')->primary();
        });
        DB::table('nama_report')->insert([
            'id_report' => 5,
            'nama_report' => 'User Brimo RPT v2',
            'table_name' => 'user_brimo_rpt_v2',
        ]);

        $directory = storage_path('app/imports/brimo_preview_' . Str::random(8));
        File::ensureDirectoryExists($directory, 0750, true);
        $path = $directory . DIRECTORY_SEPARATOR . 'brimo-rpt-v2.csv';
        $rows = ['TAHUN|PERIODE|POSISI|NAMA KCI|MBDESC|BRDESC|JUMLAH'];
        for ($i = 0; $i < 4001; $i++) {
            $rows[] = "2026|2026-08|2026-08-17|KC BLITAR|KC BLITAR|UNIT {$i}|1";
        }
        foreach (['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'] as $branch) {
            $rows[] = "2026|2026-08|2026-08-17|{$branch}|{$branch}|UNIT TEST|1";
        }
        File::put($path, implode(PHP_EOL, $rows) . PHP_EOL);

        try {
            $session = app('session.store');
            $session->start();
            $session->put('active_id_report', 5);
            $session->put('import_type', 'brimo');
            $session->put('import_files', [['name' => basename($path), 'path' => $path]]);

            $request = Request::create('/import/brimo/preview', 'POST', [
                'file_path' => $path,
                'delimiter' => '|',
            ]);
            $request->setLaravelSession($session);

            $response = app(ImportFileBrimoController::class)->preview($request);
            $data = $response->getData();

            $this->assertCount(100, $data['previewData']);
            $this->assertSame([
                '3' => ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'],
            ], $data['initialArea6Selections']);
            $this->assertTrue($data['warmPreviewIndexOnLoad']);
            $this->assertFalse($data['initialFilterOptionsAreComplete']);
            $this->assertTrue($data['hidePreviewRowsUntilJs']);
            $this->assertSame(range(0, 6), $session->get('import_display_to_source_map'));
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function invokeMethod(object $target, string $method, array $args = [])
    {
        $reflection = new \ReflectionClass($target);
        $methodRef = $reflection->getMethod($method);
        $methodRef->setAccessible(true);

        return $methodRef->invokeArgs($target, $args);
    }
}
