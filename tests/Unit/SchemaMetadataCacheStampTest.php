<?php

namespace Tests\Unit;

use App\Support\SchemaMetadataCache;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

/**
 * A long-running queue worker keeps its schema inventory in memory. After an
 * accepted schema drift adds a column in another process, the worker must see
 * it without a restart.
 */
class SchemaMetadataCacheStampTest extends TestCase
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

    public function test_worker_inventory_refreshes_after_another_process_invalidates_the_schema(): void
    {
        $before = [(object) ['table_name' => 'ssa_simpanan', 'column_name' => 'saldo', 'table_type' => 'BASE TABLE']];
        $after = [
            (object) ['table_name' => 'ssa_simpanan', 'column_name' => 'saldo', 'table_type' => 'BASE TABLE'],
            (object) ['table_name' => 'ssa_simpanan', 'column_name' => 'jumlah_nasabah_baru', 'table_type' => 'BASE TABLE'],
        ];

        $workerConnection = $this->connectionMock();
        $workerConnection->shouldReceive('select')->twice()->andReturn($before, $after);
        $worker = new SchemaMetadataCache;

        $this->assertSame(['saldo'], $worker->getColumnListing($workerConnection, 'ssa_simpanan'));

        // Another process (the web request that accepted the drift) alters the
        // table and invalidates the shared cache.
        $webConnection = $this->connectionMock();
        (new SchemaMetadataCache)->invalidate($webConnection);

        // Within the throttle window the worker keeps its fast in-memory copy.
        $this->assertSame(['saldo'], $worker->getColumnListing($workerConnection, 'ssa_simpanan'));

        $this->expireStampThrottle($worker);
        $this->assertSame(['saldo', 'jumlah_nasabah_baru'], $worker->getColumnListing($workerConnection, 'ssa_simpanan'));
    }

    public function test_unchanged_schema_never_reloads_the_inventory(): void
    {
        $connection = $this->connectionMock();
        $connection->shouldReceive('select')->once()->andReturn([
            (object) ['table_name' => 'lw325_ph', 'column_name' => 'periode', 'table_type' => 'BASE TABLE'],
        ]);
        $cache = new SchemaMetadataCache;

        $this->assertTrue($cache->hasColumn($connection, 'lw325_ph', 'periode'));
        $this->expireStampThrottle($cache);
        $this->assertTrue($cache->hasColumn($connection, 'lw325_ph', 'periode'));
    }

    private function expireStampThrottle(SchemaMetadataCache $cache): void
    {
        $property = new \ReflectionProperty(SchemaMetadataCache::class, 'stampCheckedAt');
        $property->setValue($cache, 0.0);
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
