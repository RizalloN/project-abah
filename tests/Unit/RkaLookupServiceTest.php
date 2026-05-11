<?php

namespace Tests\Unit;

use App\Support\RkaLookupService;
use App\Support\OptimizedRkaLookupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RkaLookupServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::dropAllTables();

        Schema::create('rka', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('desc_kanwil')->nullable();
            $table->string('kanca')->nullable();
            $table->string('desc_uker')->nullable();
            $table->string('rka_key')->nullable();
            $table->string('mata_anggaran')->nullable();
            $table->decimal('jan', 20, 2)->nullable();
            $table->decimal('feb', 20, 2)->nullable();
            $table->decimal('mar', 20, 2)->nullable();
            $table->decimal('apr', 20, 2)->nullable();
            $table->decimal('may', 20, 2)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_it_maps_april_datepicker_to_apr_column_and_matches_normalized_branch_and_unit_names(): void
    {
        DB::table('rka')->insert([
            [
                'uniqueid_namareport' => 'rka-1',
                'desc_kanwil' => 'KANWIL JATIM',
                'kanca' => '49-KC Madiun',
                'desc_uker' => '00045 -- KC Madiun (Konsolidasi-MB)',
                'rka_key' => 'RK-1',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'apr' => 1250000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uniqueid_namareport' => 'rka-2',
                'desc_kanwil' => 'KANWIL JATIM',
                'kanca' => 'KC Ponorogo',
                'desc_uker' => 'UNIT Lain',
                'rka_key' => 'RK-2',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'apr' => 9999999,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = new RkaLookupService();

        $this->assertSame('apr', $service->resolveMonthColumn('2026-04-15'));
        $this->assertSame('APR', $service->resolveMonthLabel('2026-04-15'));

        $result = $service->aggregateForScope(
            ['total_simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total']]],
            'apr',
            '00045 -- KC Madiun (Konsolidasi-MB)',
            '49-KC Madiun',
            2026
        );

        $this->assertSame(1250000.0, $result['total_simpanan']);
    }

    public function test_regional_rka_can_be_grouped_and_filtered_by_selected_uker(): void
    {
        DB::table('rka')->insert([
            [
                'uniqueid_namareport' => 'rka-regional-1',
                'kanca' => 'KC Ponorogo',
                'desc_uker' => '6388-UNIT SLEKO MADIUN',
                'mata_anggaran' => 'Jumlah Merchant (EDC) yang Produktif',
                'apr' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uniqueid_namareport' => 'rka-regional-2',
                'kanca' => 'KC Ponorogo',
                'desc_uker' => '6339-UNIT ALOON ALOON MADIUN',
                'mata_anggaran' => 'Jumlah Merchant (EDC) yang Produktif',
                'apr' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uniqueid_namareport' => 'rka-regional-3',
                'kanca' => 'KC Ponorogo',
                'desc_uker' => '7000-UNIT MAGETAN',
                'mata_anggaran' => 'Jumlah Merchant (EDC) yang Produktif',
                'apr' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = new RkaLookupService();
        $result = $service->aggregateByGroupWithRegionalFilter(
            ['prod' => ['mata_anggaran' => ['Jumlah Merchant (EDC) yang Produktif']]],
            'apr',
            ['MADIUN'],
            2026,
            ['UNIT SLEKO MADIUN'],
            'uker'
        );

        $this->assertSame(['UNIT SLEKO MADIUN' => 10.0], $result['prod']);
    }

    public function test_grouped_rka_matches_slugged_dashboard_unit_key_to_coded_uker_name(): void
    {
        DB::table('rka')->insert([
            [
                'uniqueid_namareport' => 'rka-caruban-1',
                'kanca' => 'KC Madiun',
                'desc_uker' => '552-KCP Caruban',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'may' => 194912333366.57,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
            [
                'uniqueid_namareport' => 'rka-caruban-2',
                'kanca' => 'KC Madiun',
                'desc_uker' => '3883-UNIT CARUBAN MADIUN',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'may' => 52550560092.64,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
        ]);

        $service = new RkaLookupService();
        $result = $service->aggregateByGroup(
            ['total_simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total']]],
            'may',
            ['KC Madiun'],
            ['kcp-caruban'],
            'uker',
            2026
        );

        $this->assertSame(['KCP CARUBAN' => 194912333366.57], $result['total_simpanan']);
    }

    public function test_optimized_grouped_rka_cache_is_scoped_by_selected_branch_and_unit(): void
    {
        DB::table('rka')->insert([
            [
                'uniqueid_namareport' => 'rka-cache-1',
                'kanca' => 'KC Madiun',
                'desc_uker' => '552-KCP Caruban',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'may' => 100,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
            [
                'uniqueid_namareport' => 'rka-cache-2',
                'kanca' => 'KC Madiun',
                'desc_uker' => '2167-KCP Sudirman Madiun',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'may' => 200,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
        ]);

        $service = new OptimizedRkaLookupService();
        $definitions = ['total_simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total']]];

        $caruban = $service->aggregateByGroup($definitions, 'may', ['KC Madiun'], ['kcp-caruban'], 'uker', 2026);
        $sudirman = $service->aggregateByGroup($definitions, 'may', ['KC Madiun'], ['kcp-sudirman-madiun'], 'uker', 2026);

        $this->assertSame(['KCP CARUBAN' => 100.0], $caruban['total_simpanan']);
        $this->assertSame(['KCP SUDIRMAN MADIUN' => 200.0], $sudirman['total_simpanan']);
    }
}
