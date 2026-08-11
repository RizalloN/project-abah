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
            $table->decimal('jun', 20, 2)->nullable();
            $table->decimal('jul', 20, 2)->nullable();
            $table->decimal('aug', 20, 2)->nullable();
            $table->decimal('sep', 20, 2)->nullable();
            $table->decimal('oct', 20, 2)->nullable();
            $table->decimal('nov', 20, 2)->nullable();
            $table->decimal('dec', 20, 2)->nullable();
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

    public function test_dashboard_harian_simpanan_rka_stays_scoped_for_branch_all_units_and_unit_selector(): void
    {
        DB::table('rka')->insert([
            [
                'uniqueid_namareport' => 'rka-scope-madiun-kc-a1',
                'kanca' => 'KC Madiun',
                'desc_uker' => '45-KC Madiun',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'may' => 1000,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
            [
                'uniqueid_namareport' => 'rka-scope-madiun-kcp-a1',
                'kanca' => 'KC Madiun',
                'desc_uker' => '2109-KCP DOLOPO',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'may' => 200,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
            [
                'uniqueid_namareport' => 'rka-scope-madiun-unit-a1',
                'kanca' => 'KC Madiun',
                'desc_uker' => '3212-UNIT DOLOPO MADIUN',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'may' => 300,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
            [
                'uniqueid_namareport' => 'rka-scope-madiun-giro-korp',
                'kanca' => 'KC Madiun',
                'desc_uker' => '45-KC Madiun',
                'mata_anggaran' => 'A.2.a. Giro Korporasi',
                'may' => 40,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
            [
                'uniqueid_namareport' => 'rka-scope-madiun-dep-korp',
                'kanca' => 'KC Madiun',
                'desc_uker' => '45-KC Madiun',
                'mata_anggaran' => 'A.2.b. Deposito Korporasi',
                'may' => 5,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
            [
                'uniqueid_namareport' => 'rka-scope-ngawi-a1',
                'kanca' => 'KC Ngawi',
                'desc_uker' => 'KC Ngawi',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'may' => 999,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
        ]);

        $definitions = [
            'total_simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total']],
            'simpanan_ritel' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            'simpanan_mikro' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
            'simpanan_wholesale' => ['mata_anggaran' => ['A.2.a. Giro Korporasi']],
            'deposito_wholesale' => ['mata_anggaran' => []],
        ];

        $service = new RkaLookupService();
        $branchAllUnits = $service->aggregateForScope($definitions, 'may', 'KC Madiun', null, 2026);
        $kcpDolopo = $service->aggregateByGroup($definitions, 'may', ['KC Madiun'], ['kcp-dolopo'], 'uker', 2026);
        $unitDolopo = $service->aggregateByGroup($definitions, 'may', ['KC Madiun'], ['unit-dolopo-madiun'], 'uker', 2026);

        $this->assertSame(1500.0, $branchAllUnits['total_simpanan']);
        $this->assertSame(1200.0, $branchAllUnits['simpanan_ritel']);
        $this->assertSame(300.0, $branchAllUnits['simpanan_mikro']);
        $this->assertSame(40.0, $branchAllUnits['simpanan_wholesale']);
        $this->assertSame(0.0, $branchAllUnits['deposito_wholesale']);
        $this->assertSame(['KCP DOLOPO' => 200.0], $kcpDolopo['total_simpanan']);
        $this->assertSame(['UNIT DOLOPO MADIUN' => 300.0], $unitDolopo['total_simpanan']);
        $this->assertSame([], $unitDolopo['simpanan_ritel']);
        $this->assertSame(['UNIT DOLOPO MADIUN' => 300.0], $unitDolopo['simpanan_mikro']);
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

    public function test_grouped_rka_matches_kanca_detail_unit_key_to_kanca_summary_row(): void
    {
        DB::table('rka')->insert([
            [
                'uniqueid_namareport' => 'rka-kanca-detail-1',
                'kanca' => 'KC Madiun',
                'desc_uker' => '45-KC Madiun',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'may' => 1000,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
        ]);

        $service = new RkaLookupService();
        $result = $service->aggregateByGroup(
            ['total_simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total']]],
            'may',
            ['KC Madiun'],
            ['kc-madiun-detail'],
            'uker',
            2026
        );

        $this->assertSame(['KC MADIUN' => 1000.0], $result['total_simpanan']);
    }

    public function test_unit_scope_does_not_match_different_kc_kcp_kind_with_same_region_keyword(): void
    {
        DB::table('rka')->insert([
            [
                'uniqueid_namareport' => 'rka-ponorogo-kc',
                'kanca' => 'KC Ponorogo',
                'desc_uker' => '45-KC Ponorogo',
                'mata_anggaran' => 'B.5.a. Briguna',
                'may' => 1000,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
            [
                'uniqueid_namareport' => 'rka-ponorogo-kcp',
                'kanca' => 'KC Ponorogo',
                'desc_uker' => '2167-KCP Sudirman Ponorogo',
                'mata_anggaran' => 'B.5.a. Briguna',
                'may' => 200,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
            [
                'uniqueid_namareport' => 'rka-ponorogo-kcp-prefixed',
                'kanca' => 'KC Ponorogo',
                'desc_uker' => 'KC Ponorogo - KCP Sudirman Ponorogo',
                'mata_anggaran' => 'B.5.a. Briguna',
                'may' => 300,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
        ]);

        $service = new RkaLookupService();
        $definitions = [
            'briguna' => [
                'mata_anggaran' => ['B.5.a. Briguna'],
                'uker_contains_any' => ['KC', 'KCP'],
                'include_kanca_summary' => true,
            ],
        ];

        $kc = $service->aggregateForScope($definitions, 'may', 'KC Ponorogo', 'KC Ponorogo', 2026);
        $kcp = $service->aggregateForScope($definitions, 'may', 'KC Ponorogo', 'KCP Sudirman Ponorogo', 2026);

        $this->assertSame(1000.0, $kc['briguna']);
        $this->assertSame(500.0, $kcp['briguna']);
    }

    public function test_kanca_summary_fallback_only_fills_zero_branch_aggregate(): void
    {
        DB::table('rka')->insert([
            [
                'uniqueid_namareport' => 'rka-kanca-fallback-madiun-summary',
                'kanca' => 'KC Madiun',
                'desc_uker' => '45-KC Madiun',
                'mata_anggaran' => 'Sample RKA Target',
                'may' => 1000,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
            [
                'uniqueid_namareport' => 'rka-kanca-fallback-madiun-detail',
                'kanca' => 'KC Madiun',
                'desc_uker' => '2109-KCP DOLOPO',
                'mata_anggaran' => 'Sample RKA Target',
                'may' => 250,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
            [
                'uniqueid_namareport' => 'rka-kanca-fallback-magetan-summary',
                'kanca' => 'KC Magetan',
                'desc_uker' => 'KC Magetan',
                'mata_anggaran' => 'Sample RKA Target',
                'may' => 300,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
        ]);

        $service = new RkaLookupService();
        $result = $service->aggregateByKancaWithSummaryFallback(
            ['sample' => ['mata_anggaran' => ['Sample RKA Target'], 'uker_contains_any' => ['KC', 'KCP']]],
            'may',
            ['KC Madiun', 'KC Magetan'],
            2026
        );

        $this->assertSame(250.0, $result['sample']['KC MADIUN']);
        $this->assertSame(300.0, $result['sample']['KC MAGETAN']);
    }

    public function test_grouped_rka_matches_unit_key_when_source_contains_punctuation(): void
    {
        DB::table('rka')->insert([
            [
                'uniqueid_namareport' => 'rka-a-yani-1',
                'kanca' => 'KC Magetan',
                'desc_uker' => '6363-UNIT A. YANI MAGETAN',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'may' => 500,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
        ]);

        $service = new RkaLookupService();
        $result = $service->aggregateByGroup(
            ['total_simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total']]],
            'may',
            ['KC Magetan'],
            ['unit-a-yani-magetan'],
            'uker',
            2026
        );

        $this->assertSame(['UNIT A YANI MAGETAN' => 500.0], $result['total_simpanan']);
    }

    public function test_grouped_rka_matches_truncated_source_unit_to_full_dashboard_unit_key(): void
    {
        DB::table('rka')->insert([
            [
                'uniqueid_namareport' => 'rka-perintis-1',
                'kanca' => 'KC Madiun',
                'desc_uker' => '3885-UNIT PERINTIS KEMERDEKAAN MADI',
                'mata_anggaran' => 'A.1. DPK Retail Funding Total',
                'may' => 750,
                'created_at' => '2026-05-04 07:55:29',
                'updated_at' => '2026-05-04 07:55:29',
            ],
        ]);

        $service = new RkaLookupService();
        $result = $service->aggregateByGroup(
            ['total_simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total']]],
            'may',
            ['KC Madiun'],
            ['unit-perintis-kemerdekaan-madiun'],
            'uker',
            2026
        );

        $this->assertSame(['UNIT PERINTIS KEMERDEKAAN MADI' => 750.0], $result['total_simpanan']);
    }

    public function test_similar_unit_names_do_not_cross_count_rka_targets(): void
    {
        DB::table('rka')->insert([
            ['uniqueid_namareport' => 'rka-kota-i', 'kanca' => 'KC Ponorogo', 'desc_uker' => '6502-UNIT KOTA I PONOROGO', 'mata_anggaran' => 'B. KREDIT TOTAL', 'may' => 100, 'created_at' => '2026-05-01 00:00:00'],
            ['uniqueid_namareport' => 'rka-kota-ii', 'kanca' => 'KC Ponorogo', 'desc_uker' => '3844-UNIT KOTA II PONOROGO', 'mata_anggaran' => 'B. KREDIT TOTAL', 'may' => 200, 'created_at' => '2026-05-01 00:00:00'],
            ['uniqueid_namareport' => 'rka-kota-iii', 'kanca' => 'KC Ponorogo', 'desc_uker' => '6492-UNIT KOTA III PONOROGO', 'mata_anggaran' => 'B. KREDIT TOTAL', 'may' => 300, 'created_at' => '2026-05-01 00:00:00'],
            ['uniqueid_namareport' => 'rka-pasar-pon', 'kanca' => 'KC Ponorogo', 'desc_uker' => '6503-UNIT PASAR PON PONOROGO', 'mata_anggaran' => 'B. KREDIT TOTAL', 'may' => 400, 'created_at' => '2026-05-01 00:00:00'],
            ['uniqueid_namareport' => 'rka-pasar-bajang', 'kanca' => 'KC Ponorogo', 'desc_uker' => '8113-UNIT PASAR BAJANG PONOROGO', 'mata_anggaran' => 'B. KREDIT TOTAL', 'may' => 500, 'created_at' => '2026-05-01 00:00:00'],
        ]);

        $definitions = ['total_os' => ['mata_anggaran' => ['B. KREDIT TOTAL']]];
        $service = new RkaLookupService();

        $kotaI = $service->aggregateForScope($definitions, 'may', 'KC Ponorogo', 'UNIT Kota I Ponorogo', 2026);
        $kotaII = $service->aggregateForScope($definitions, 'may', 'KC Ponorogo', 'UNIT Kota II Ponorogo', 2026);
        $kotaIII = $service->aggregateForScope($definitions, 'may', 'KC Ponorogo', 'UNIT Kota III Ponorogo', 2026);
        $pasarPon = $service->aggregateForScope($definitions, 'may', 'KC Ponorogo', 'UNIT Pasar Pon Ponorogo', 2026);
        $groupedKotaI = $service->aggregateByGroup(
            $definitions,
            'may',
            ['kc-ponorogo'],
            ['unit-kota-i-ponorogo'],
            'uker',
            2026
        );

        $this->assertSame(100.0, $kotaI['total_os']);
        $this->assertSame(200.0, $kotaII['total_os']);
        $this->assertSame(300.0, $kotaIII['total_os']);
        $this->assertSame(400.0, $pasarPon['total_os']);
        $this->assertSame(['UNIT KOTA I PONOROGO' => 100.0], $groupedKotaI['total_os']);
    }

    public function test_rka_year_filter_uses_business_year_instead_of_upload_timestamp(): void
    {
        Schema::table('rka', function (Blueprint $table): void {
            $table->unsignedInteger('tahun')->nullable();
        });

        DB::table('rka')->insert([
            'uniqueid_namareport' => 'rka-business-year-2025',
            'tahun' => 2025,
            'kanca' => 'KC Madiun',
            'desc_uker' => '45-KC Madiun',
            'mata_anggaran' => 'A.1. DPK Retail Funding Total',
            'dec' => 2025,
            'created_at' => '2026-07-31 10:00:00',
            'updated_at' => '2026-07-31 10:00:00',
        ]);

        $service = new RkaLookupService();
        $result = $service->aggregateForScope(
            ['total_simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total']]],
            'dec',
            'KC Madiun',
            'KC Madiun',
            2025
        );

        $this->assertSame(2025.0, $result['total_simpanan']);
        $this->assertSame([2025], $service->availableYears());
    }

    public function test_latest_breakdown_budget_label_matches_legacy_dashboard_definition(): void
    {
        DB::table('rka')->insert([
            'uniqueid_namareport' => 'rka-latest-funding-label',
            'kanca' => 'KC Madiun',
            'desc_uker' => '45-KC Madiun',
            'mata_anggaran' => 'A. DPK Retail Funding Total',
            'jul' => 987654,
            'created_at' => '2026-07-31 10:00:00',
            'updated_at' => '2026-07-31 10:00:00',
        ]);

        $service = new RkaLookupService();
        $result = $service->aggregateForScope(
            ['total_simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total']]],
            'jul',
            'KC Madiun',
            'KC Madiun',
            2026
        );

        $this->assertSame(987654.0, $result['total_simpanan']);
    }
}
