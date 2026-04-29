<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportFileBrimoController;
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

    private function invokeMethod(object $target, string $method, array $args = [])
    {
        $reflection = new \ReflectionClass($target);
        $methodRef = $reflection->getMethod($method);
        $methodRef->setAccessible(true);

        return $methodRef->invokeArgs($target, $args);
    }
}
