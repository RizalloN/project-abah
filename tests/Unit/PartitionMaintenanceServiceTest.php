<?php

namespace Tests\Unit;

use App\Support\PartitionMaintenanceService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PartitionMaintenanceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    public function test_partition_fast_path_is_disabled_on_sqlite(): void
    {
        $service = app(PartitionMaintenanceService::class);

        $this->assertFalse($service->supportsPartitionDdl('sqlite'));
        $this->assertNull($service->resolveSinglePartitionForValue('daily_loan_dinamis', 'periode', '2026-04-04'));
    }
}
