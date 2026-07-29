<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportFileBrimoController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    private function invokeMethod(object $target, string $method, array $args = [])
    {
        $reflection = new \ReflectionClass($target);
        $methodRef = $reflection->getMethod($method);
        $methodRef->setAccessible(true);

        return $methodRef->invokeArgs($target, $args);
    }
}
