<?php

namespace Tests\Unit;

use App\Services\Import\MySqlBulkLoadService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlBulkLoadServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('DB_MYSQL_LOCAL_INFILE');
        unset($_ENV['DB_MYSQL_LOCAL_INFILE'], $_SERVER['DB_MYSQL_LOCAL_INFILE']);

        parent::tearDown();
    }

    public function test_supports_native_bulk_load_ignores_runtime_env_when_mysql_variable_is_enabled(): void
    {
        config()->set('import.direct_load.require_local_infile', true);
        putenv('DB_MYSQL_LOCAL_INFILE=false');
        $_ENV['DB_MYSQL_LOCAL_INFILE'] = 'false';
        $_SERVER['DB_MYSQL_LOCAL_INFILE'] = 'false';

        DB::shouldReceive('connection->getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('selectOne')->once()->with("SHOW VARIABLES LIKE 'local_infile'")
            ->andReturn((object) ['Value' => 'ON']);

        $service = new MySqlBulkLoadService();

        $this->assertTrue($service->supportsNativeBulkLoad());
    }

    public function test_supports_native_bulk_load_returns_false_when_mysql_variable_is_disabled(): void
    {
        config()->set('import.direct_load.require_local_infile', true);
        putenv('DB_MYSQL_LOCAL_INFILE=true');
        $_ENV['DB_MYSQL_LOCAL_INFILE'] = 'true';
        $_SERVER['DB_MYSQL_LOCAL_INFILE'] = 'true';

        DB::shouldReceive('connection->getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('selectOne')->once()->with("SHOW VARIABLES LIKE 'local_infile'")
            ->andReturn((object) ['Value' => 'OFF']);

        $service = new MySqlBulkLoadService();

        $this->assertFalse($service->supportsNativeBulkLoad());
    }
}
