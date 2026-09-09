<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TuneDatabasePerformanceCommandTest extends TestCase
{
    public function test_runtime_tuning_never_shrinks_a_larger_buffer_pool(): void
    {
        config()->set('performance.database.runtime_tuning_enabled', true);
        config()->set('performance.database.buffer_pool_mb', 12288);

        DB::shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('select')->once()->andReturn($this->globalVariables(16 * 1024 * 1024 * 1024));
        DB::shouldReceive('statement')
            ->with('SET GLOBAL innodb_buffer_pool_size = 12884901888')
            ->never();
        DB::shouldReceive('statement')->with('SET GLOBAL long_query_time = 2')->once()->andReturnTrue();
        DB::shouldReceive('statement')->with('SET GLOBAL min_examined_row_limit = 1000')->once()->andReturnTrue();
        DB::shouldReceive('statement')->with("SET GLOBAL slow_query_log = 'ON'")->once()->andReturnTrue();
        DB::shouldReceive('select')->once()->andReturn($this->globalVariables(16 * 1024 * 1024 * 1024));

        $this->artisan('database:performance-tune')
            ->assertSuccessful();
    }

    public function test_runtime_tuning_grows_a_smaller_buffer_pool_to_the_configured_floor(): void
    {
        config()->set('performance.database.runtime_tuning_enabled', true);
        config()->set('performance.database.buffer_pool_mb', 12288);

        DB::shouldReceive('getDriverName')->once()->andReturn('mariadb');
        DB::shouldReceive('select')->once()->andReturn($this->globalVariables(4 * 1024 * 1024 * 1024));
        DB::shouldReceive('statement')
            ->with('SET GLOBAL innodb_buffer_pool_size = 12884901888')
            ->once()
            ->andReturnTrue();
        DB::shouldReceive('statement')->with('SET GLOBAL long_query_time = 2')->once()->andReturnTrue();
        DB::shouldReceive('statement')->with('SET GLOBAL min_examined_row_limit = 1000')->once()->andReturnTrue();
        DB::shouldReceive('statement')->with("SET GLOBAL slow_query_log = 'ON'")->once()->andReturnTrue();
        DB::shouldReceive('select')->once()->andReturn($this->globalVariables(12 * 1024 * 1024 * 1024));

        $this->artisan('database:performance-tune')
            ->assertSuccessful();
    }

    /** @return array<int, object> */
    private function globalVariables(int $bufferPoolBytes): array
    {
        return [
            (object) ['Variable_name' => 'innodb_buffer_pool_size', 'Value' => (string) $bufferPoolBytes],
            (object) ['Variable_name' => 'long_query_time', 'Value' => '2.000000'],
            (object) ['Variable_name' => 'min_examined_row_limit', 'Value' => '1000'],
            (object) ['Variable_name' => 'slow_query_log', 'Value' => 'ON'],
            (object) ['Variable_name' => 'slow_query_log_file', 'Value' => 'mysql-slow.log'],
        ];
    }
}
