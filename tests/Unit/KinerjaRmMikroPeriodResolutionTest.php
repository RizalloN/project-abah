<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class KinerjaRmMikroPeriodResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->nullable();
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
        });
        Schema::create('performance_rm_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->string('segmen');
            $table->string('produk');
        });

        Cache::forget('report_cache_version:global');
    }

    public function test_period_options_include_micro_kur_daily_loan_source_periods(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-05-06', 'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURMIKRO'],
            ['periode' => '2026-05-05', 'segmen_kinerja' => 'CONSUMER', 'produk_kinerja' => 'KPR'],
        ]);
        DB::table('performance_rm_snapshots')->insert([
            ['periode' => '2026-04-30', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO'],
        ]);

        $periods = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'fetchAvailablePeriods');

        $this->assertSame(['2026-05-06', '2026-04-30'], $periods->all());
    }

    private function invokePrivateMethod(object $object, string $method): mixed
    {
        $reflection = new ReflectionClass($object);
        $reflectedMethod = $reflection->getMethod($method);
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invoke($object);
    }
}
