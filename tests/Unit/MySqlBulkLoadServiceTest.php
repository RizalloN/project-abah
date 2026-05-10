<?php

namespace Tests\Unit;

use App\Services\Import\MySqlBulkLoadService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class MySqlBulkLoadServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Test ini hanya boleh berjalan di SQLite. Bukan di MySQL production. Periksa phpunit.xml.');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
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

    public function test_assert_transactional_table_allows_innodb_tables(): void
    {
        DB::shouldReceive('connection->getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('connection->getSchemaBuilder->hasTable')->once()->with('daily_loan_dinamis')->andReturn(true);
        DB::shouldReceive('connection->getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('selectOne')
            ->once()
            ->with(
                'SELECT ENGINE FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                ['daily_loan_dinamis']
            )
            ->andReturn((object) ['ENGINE' => 'InnoDB']);

        $service = new MySqlBulkLoadService();

        $service->assertTransactionalTable('daily_loan_dinamis', 'import test');
        $this->assertTrue(true);
    }

    public function test_assert_transactional_table_blocks_non_innodb_tables(): void
    {
        DB::shouldReceive('connection->getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('connection->getSchemaBuilder->hasTable')->once()->with('daily_loan_dinamis')->andReturn(true);
        DB::shouldReceive('connection->getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('selectOne')
            ->once()
            ->with(
                'SELECT ENGINE FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                ['daily_loan_dinamis']
            )
            ->andReturn((object) ['ENGINE' => 'MyISAM']);

        $service = new MySqlBulkLoadService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('daily_loan_dinamis');

        $service->assertTransactionalTable('daily_loan_dinamis', 'import test');
    }

    public function test_load_csv_into_mysql_chunked_falls_back_to_php_batch_insert_when_native_bulk_is_unavailable(): void
    {
        Schema::create('bulk_load_php_fallback_test', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('code')->nullable();
            $table->string('amount')->nullable();
        });

        $csvPath = storage_path('framework/testing/bulk_load_php_fallback_test.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, "A,100\nB,200\n");

        try {
            $service = new MySqlBulkLoadService();

            $this->assertFalse($service->supportsNativeBulkLoad());

            $inserted = $service->loadCsvIntoMysqlChunked(
                $csvPath,
                'bulk_load_php_fallback_test',
                ['code', 'amount'],
                null,
                1000
            );

            $this->assertSame(2, $inserted);
            $this->assertDatabaseHas('bulk_load_php_fallback_test', [
                'code' => 'A',
                'amount' => '100',
            ]);
            $this->assertDatabaseHas('bulk_load_php_fallback_test', [
                'code' => 'B',
                'amount' => '200',
            ]);
        } finally {
            @unlink($csvPath);
            Schema::dropIfExists('bulk_load_php_fallback_test');
        }
    }
}
