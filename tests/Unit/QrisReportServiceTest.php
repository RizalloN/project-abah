<?php

namespace Tests\Unit;

use App\Services\Reports\QrisReportService;
use App\Support\RkaLookupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class QrisReportServiceTest extends TestCase
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
        Cache::flush();

        Schema::create('jumlah_merchant_qris_detail', function (Blueprint $table): void {
            $table->date('POSISI')->index();
            $table->string('MBDESC')->nullable()->index();
            $table->string('BRDESC')->nullable()->index();
            $table->string('STOREID')->nullable();
            $table->string('AKUMULASI_SV_TOTAL')->nullable();
        });
    }

    public function test_qris_payload_cache_refreshes_after_report_cache_version_bump(): void
    {
        DB::table('jumlah_merchant_qris_detail')->insert([
            'POSISI' => '2026-05-15',
            'MBDESC' => 'KC MADIUN',
            'BRDESC' => 'UNIT A',
            'STOREID' => 'STORE-001',
            'AKUMULASI_SV_TOTAL' => '100000',
        ]);

        Cache::put('report_cache_version:global', 1);

        $service = new QrisReportService($this->mockRkaLookup());
        $request = Request::create('/report/data', 'POST', [
            'tab' => 'qris',
            'posisi' => '2026-05-15',
        ]);

        $firstPayload = $service->handle($request)->getData(true);
        $this->assertSame(1, (int) data_get($firstPayload, 'total.jml.curr'));

        DB::table('jumlah_merchant_qris_detail')->insert([
            'POSISI' => '2026-05-15',
            'MBDESC' => 'KC MAGETAN',
            'BRDESC' => 'UNIT B',
            'STOREID' => 'STORE-002',
            'AKUMULASI_SV_TOTAL' => '200000',
        ]);

        $cachedPayload = $service->handle($request)->getData(true);
        $this->assertSame(1, (int) data_get($cachedPayload, 'total.jml.curr'));

        Cache::put('report_cache_version:global', 2);

        $freshPayload = $service->handle($request)->getData(true);
        $this->assertSame(2, (int) data_get($freshPayload, 'total.jml.curr'));
    }

    private function mockRkaLookup(): RkaLookupService
    {
        $mock = Mockery::mock(RkaLookupService::class);
        $emptyGroups = static fn (array $definitions): array => array_fill_keys(array_keys($definitions), []);

        $mock->shouldReceive('resolveMonthColumn')->andReturn('may');
        $mock->shouldReceive('resolveMonthLabel')->andReturn('MAY');
        $mock->shouldReceive('aggregateByGroup')->andReturnUsing($emptyGroups);
        $mock->shouldReceive('aggregateByGroupWithRegionalFilter')->andReturnUsing($emptyGroups);
        $mock->shouldReceive('aggregateByKancaWithSummaryFallback')->andReturnUsing($emptyGroups);

        return $mock;
    }
}
