<?php

namespace Tests\Unit;

use App\Support\SchemaMetadataCache;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class SchemaMetadataCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_repeated_schema_checks_share_one_inventory_query_and_persistent_cache(): void
    {
        $rows = [
            (object) ['table_name' => 'daily_loan_dinamis', 'column_name' => 'periode', 'table_type' => 'BASE TABLE'],
            (object) ['table_name' => 'daily_loan_dinamis', 'column_name' => 'cabang1', 'table_type' => 'BASE TABLE'],
            (object) ['table_name' => 'dashboard_harian_snapshots', 'column_name' => 'snapshot_period', 'table_type' => 'BASE TABLE'],
            (object) ['table_name' => 'daily_loan_view', 'column_name' => 'periode', 'table_type' => 'VIEW'],
        ];
        $firstConnection = $this->connectionMock();
        $firstConnection->shouldReceive('select')->once()->andReturn($rows);

        $first = new SchemaMetadataCache;
        $this->assertTrue($first->hasTable($firstConnection, 'daily_loan_dinamis'));
        $this->assertTrue($first->hasColumn($firstConnection, 'daily_loan_dinamis', 'PERIODE'));
        $this->assertTrue($first->hasColumns($firstConnection, 'daily_loan_dinamis', ['periode', 'cabang1']));
        $this->assertFalse($first->hasColumn($firstConnection, 'daily_loan_dinamis', 'missing'));
        $this->assertFalse($first->hasTable($firstConnection, 'daily_loan_view'));
        $this->assertSame(['periode'], $first->getColumnListing($firstConnection, 'daily_loan_view'));

        $secondConnection = $this->connectionMock();
        $secondConnection->shouldNotReceive('select');
        $second = new SchemaMetadataCache;
        $this->assertTrue($second->hasColumn($secondConnection, 'daily_loan_dinamis', 'cabang1'));

        $second->invalidate($secondConnection);

        $thirdConnection = $this->connectionMock();
        $thirdConnection->shouldReceive('select')->once()->andReturn($rows);
        $third = new SchemaMetadataCache;
        $this->assertTrue($third->hasTable($thirdConnection, 'dashboard_harian_snapshots'));
    }

    private function connectionMock(): Connection
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabaseName')->andReturn('project_abah');
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        $connection->shouldReceive('getName')->andReturn('mysql');
        $connection->shouldReceive('getTablePrefix')->andReturn('');

        return $connection;
    }
}
