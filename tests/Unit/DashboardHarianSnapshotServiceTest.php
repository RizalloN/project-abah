<?php

namespace Tests\Unit;

use App\Support\DashboardHarianSnapshotService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class DashboardHarianSnapshotServiceTest extends TestCase
{
    private string $originalDefaultConnection;
    private ?string $originalSqliteDatabase;
    private string $originalCacheDefault;
    private string $tempDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = (string) Config::get('database.default');
        $this->originalSqliteDatabase = Config::get('database.connections.sqlite.database');
        $this->originalCacheDefault = (string) Config::get('cache.default');
        $this->tempDatabase = tempnam(sys_get_temp_dir(), 'abah_snapshot_test_');

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->tempDatabase);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Config::set('cache.default', 'array');
        Cache::setDefaultDriver('array');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        Config::set('database.default', $this->originalDefaultConnection);
        Config::set('database.connections.sqlite.database', $this->originalSqliteDatabase);
        DB::purge('sqlite');
        Config::set('cache.default', $this->originalCacheDefault);
        Cache::setDefaultDriver($this->originalCacheDefault);

        if (isset($this->tempDatabase) && is_file($this->tempDatabase)) {
            unlink($this->tempDatabase);
        }

        parent::tearDown();
    }

    public function test_npl_rka_metric_definitions_follow_latest_mapping(): void
    {
        $service = new DashboardHarianSnapshotService();

        $reflection = new \ReflectionMethod($service, 'dashboardRkaMetricDefinitions');
        $reflection->setAccessible(true);

        $definitions = $reflection->invoke($service);

        $this->assertSame([], $definitions['total_sml_pct_non_commercial']['mata_anggaran']);
        $this->assertSame([], $definitions['total_npl_pct_non_commercial']['mata_anggaran']);
        $this->assertSame(['A.1. DPK Retail Funding Total'], $definitions['total_simpanan']['mata_anggaran']);
        $this->assertSame(['KC', 'KCP'], $definitions['simpanan_ritel']['uker_contains_any']);
        $this->assertTrue($definitions['simpanan_ritel']['include_kanca_summary']);
        $this->assertTrue($definitions['giro_ritel']['include_kanca_summary']);
        $this->assertSame(['UNIT'], $definitions['simpanan_mikro']['uker_contains_any']);
        $this->assertSame(['A.2. DPK Korporasi'], $definitions['simpanan_wholesale']['mata_anggaran']);
        $this->assertSame(['A.2.b. Deposito Korporasi'], $definitions['deposito_wholesale']['mata_anggaran']);
        $this->assertSame(['KC', 'KCP'], $definitions['kecil_non_cashcoll_os']['uker_contains_any']);
        $this->assertTrue($definitions['kecil_non_cashcoll_os']['include_kanca_summary']);
        $this->assertSame(['KC', 'KCP'], $definitions['briguna_konsumer_os']['uker_contains_any']);
        $this->assertTrue($definitions['briguna_konsumer_os']['include_kanca_summary']);
        $this->assertSame(['KC', 'KCP'], $definitions['kpr_os']['uker_contains_any']);
        $this->assertTrue($definitions['kpr_os']['include_kanca_summary']);
        $this->assertSame(['KC', 'KCP'], $definitions['kkb_os']['uker_contains_any']);
        $this->assertTrue($definitions['kkb_os']['include_kanca_summary']);
        $this->assertArrayNotHasKey('uker_contains_any', $definitions['micro_os']);
        $this->assertArrayNotHasKey('uker_contains_any', $definitions['kur_kecil_os']);
        $this->assertSame(['NPL Rp Kecil Non Cash Collateral'], $definitions['kecil_non_cashcoll_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Kecil Cash Collateral'], $definitions['cashcoll_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Medium'], $definitions['medium_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Briguna'], $definitions['briguna_konsumer_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KPR'], $definitions['kpr_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KKB'], $definitions['kkb_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Mikro'], $definitions['micro_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Briguna Mikro'], $definitions['briguna_mikro_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Kupedes Komersial'], $definitions['kupedes_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KUR Mikro'], $definitions['kur_mikro_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KUR Kecil'], $definitions['kur_kecil_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KPP'], $definitions['kur_kpp_npl']['mata_anggaran']);
    }

    public function test_finalize_rka_metrics_keeps_raw_total_simpanan_and_total_os_values(): void
    {
        $service = new DashboardHarianSnapshotService();

        $reflection = new \ReflectionMethod($service, 'finalizeRkaMetrics');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($service, [
            'total_simpanan' => 2000.0,
            'total_os' => 12345.0,
            'total_sml_pct_non_commercial' => 12.5,
            'total_npl_pct_non_commercial' => 3.5,
            'kecil_non_cashcoll_os' => 100.0,
            'cashcoll_os' => 50.0,
            'medium_os' => 25.0,
            'briguna_konsumer_os' => 10.0,
            'kpr_os' => 5.0,
            'kkb_os' => 5.0,
            'micro_os' => 20.0,
            'kecil_non_cashcoll_sml' => 8.0,
            'cashcoll_sml' => 2.0,
            'medium_sml' => 99.0,
            'briguna_konsumer_sml' => 3.0,
            'kpr_sml' => 4.0,
            'kkb_sml' => 5.0,
            'micro_sml' => 6.0,
            'giro_ritel' => 300.0,
            'tabungan_ritel' => 200.0,
            'giro_mikro' => 180.0,
            'tabungan_mikro' => 120.0,
            'giro_wholesale' => 40.0,
            'tabungan_wholesale' => 15.0,
            'deposito_wholesale' => 10.0,
            'kecil_non_cashcoll_npl' => 11.0,
            'cashcoll_npl' => 4.0,
            'medium_npl' => 100.0,
            'briguna_konsumer_npl' => 7.0,
            'kpr_npl' => 3.0,
            'kkb_npl' => 2.0,
            'micro_npl' => 20.0,
            'rec_dh_total' => 100.0,
            'rec_dh_small' => 40.0,
            'rec_dh_consumer' => 20.0,
            'rec_dh_micro' => 40.0,
        ]);

        $this->assertSame(12345.0, $result['total_os']);
        $this->assertSame(190.0, $result['total_os_non_commercial']);
        $this->assertEqualsWithDelta(14.7368421053, $result['total_sml_pct_non_commercial'], 0.0001);
        $this->assertSame(28.0, $result['total_sml_abs_non_commercial']);
        $this->assertEqualsWithDelta(24.7368421053, $result['total_npl_pct_non_commercial'], 0.0001);
        $this->assertSame(15.0, $result['sme_npl']);
        $this->assertSame(12.0, $result['consumer_npl']);
        $this->assertSame(47.0, $result['total_npl_abs_non_commercial']);
        $this->assertSame(2000.0, $result['total_simpanan']);
        $this->assertSame(500.0, $result['casa_ritel']);
        $this->assertSame(300.0, $result['casa_mikro']);
        $this->assertSame(800.0, $result['casa_non_wholesale']);
        $this->assertSame(55.0, $result['casa_wholesale']);
        $this->assertSame(855.0, $result['total_casa']);
        $this->assertSame(42.75, $result['casa_pct']);
        $this->assertSame(9.5, $result['ldr_non_commercial']);
        $this->assertSame(34.0, $result['ldr_ritel_non_commercial']);
        $this->assertEqualsWithDelta(6.6666666667, $result['ldr_mikro_non_commercial'], 0.0001);
        $this->assertSame(100.0, $result['rec_dh_total']);
        $this->assertSame(60.0, $result['rec_dh_small']);
        $this->assertSame(40.0, $result['rec_dh_micro']);
    }

    public function test_recovery_dh_ritel_display_includes_consumer_without_double_counting_snapshot_rows(): void
    {
        $service = new DashboardHarianSnapshotService();
        $reflection = new \ReflectionMethod($service, 'finalizeMetrics');
        $reflection->setAccessible(true);

        $rawResult = $reflection->invoke($service, [
            'rec_dh_small' => 40.0,
            'rec_dh_consumer' => 20.0,
            'rec_dh_micro' => 40.0,
        ]);

        $this->assertSame(100.0, $rawResult['rec_dh_total']);
        $this->assertSame(60.0, $rawResult['rec_dh_small']);
        $this->assertSame(20.0, $rawResult['rec_dh_consumer']);
        $this->assertSame(40.0, $rawResult['rec_dh_micro']);

        $reloadedSnapshotResult = $reflection->invoke($service, [
            'rec_dh_total' => 100.0,
            'rec_dh_small' => 60.0,
            'rec_dh_consumer' => 20.0,
            'rec_dh_micro' => 40.0,
        ]);

        $this->assertSame(100.0, $reloadedSnapshotResult['rec_dh_total']);
        $this->assertSame(60.0, $reloadedSnapshotResult['rec_dh_small']);
        $this->assertSame(20.0, $reloadedSnapshotResult['rec_dh_consumer']);
        $this->assertSame(40.0, $reloadedSnapshotResult['rec_dh_micro']);
    }

    public function test_finalize_rka_metrics_adds_wholesale_to_total_simpanan_when_retail_total_excludes_it(): void
    {
        $service = new DashboardHarianSnapshotService();

        $reflection = new \ReflectionMethod($service, 'finalizeRkaMetrics');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($service, [
            'total_simpanan' => 1_000.0,
            'giro_ritel' => 200.0,
            'deposito_ritel' => 300.0,
            'tabungan_ritel' => 500.0,
            'giro_wholesale' => 40.0,
            'tabungan_wholesale' => 20.0,
            'deposito_wholesale' => 10.0,
        ]);

        $this->assertSame(1_070.0, $result['total_simpanan']);
        $this->assertSame(70.0, $result['simpanan_wholesale']);
        $this->assertSame(700.0, $result['casa_non_wholesale']);
        $this->assertSame(60.0, $result['casa_wholesale']);
        $this->assertSame(760.0, $result['total_casa']);
    }

    public function test_unit_normalization_preserves_kc_and_kcp_detail_labels(): void
    {
        $service = new DashboardHarianSnapshotService();
        $reflection = new \ReflectionMethod($service, 'normalizeUnitLabel');
        $reflection->setAccessible(true);

        $this->assertSame(
            'KCP Sudirman Madiun',
            $reflection->invoke($service, '00912 -- KCP SUDIRMAN MADIUN', 'KC Madiun')
        );
        $this->assertSame(
            'KC Madiun',
            $reflection->invoke($service, '00070 -- KC MADIUN', 'KC Madiun')
        );
        $this->assertSame(
            'UNIT Sudirman Madiun',
            $reflection->invoke($service, 'UNIT SUDIRMAN MADIUN', 'KC Madiun')
        );
        $this->assertSame(
            'UNIT Kota III',
            $reflection->invoke($service, 'UNIT KOTA III', 'KC Madiun')
        );
    }

    public function test_slug_filter_conditions_match_all_scope_parts(): void
    {
        $service = new DashboardHarianSnapshotService();
        $reflection = new \ReflectionMethod($service, 'buildFilterCondition');
        $reflection->setAccessible(true);

        $this->assertSame(
            "(UPPER(ss.nama_uker) LIKE '%KCP%' AND UPPER(ss.nama_uker) LIKE '%SUDIRMAN%' AND UPPER(ss.nama_uker) LIKE '%MADIUN%')",
            $reflection->invoke($service, 'ss.nama_uker', 'kcp-sudirman-madiun')
        );
        $this->assertSame(
            "(UPPER(ss.nama_uker) LIKE '%KC%' AND UPPER(ss.nama_uker) LIKE '%MADIUN%')",
            $reflection->invoke($service, 'ss.nama_uker', 'kc-madiun-detail')
        );
    }

    public function test_keragaan_uker_unit_filter_accepts_punctuation_in_source_unit_name(): void
    {
        $service = new DashboardHarianSnapshotService();
        $reflection = new \ReflectionMethod($service, 'buildKeragaanUkerUnitFilterCondition');
        $reflection->setAccessible(true);

        $condition = $reflection->invoke($service, 'ss.nama_uker', 'unit-a-yani-magetan');

        $this->assertStringContainsString("UPPER(ss.nama_uker) LIKE '%UNIT%'", $condition);
        $this->assertStringContainsString("UPPER(ss.nama_uker) LIKE '%A%'", $condition);
        $this->assertStringContainsString("UPPER(ss.nama_uker) LIKE '%YANI%'", $condition);
        $this->assertStringContainsString("UPPER(ss.nama_uker) LIKE '%MAGETAN%'", $condition);
    }

    public function test_filter_options_treat_all_kancas_as_area6_and_hide_units_until_scoped(): void
    {
        $this->createSourceMetadataTables();

        DB::table('dashboard_harian_snapshots')->insert([
            [
                'uniqueid_dhs' => 'madiun-summary',
                'snapshot_period' => '2026-05-06',
                'kanca_key' => 'kc-madiun',
                'kanca_label' => 'KC Madiun',
                'unit_key' => 'kc-madiun',
                'unit_label' => 'KC Madiun',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uniqueid_dhs' => 'madiun-unit-a',
                'snapshot_period' => '2026-05-06',
                'kanca_key' => 'kc-madiun',
                'kanca_label' => 'KC Madiun',
                'unit_key' => 'unit-a',
                'unit_label' => 'UNIT A Madiun',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uniqueid_dhs' => 'ngawi-summary',
                'snapshot_period' => '2026-05-06',
                'kanca_key' => 'kc-ngawi',
                'kanca_label' => 'KC Ngawi',
                'unit_key' => 'kc-ngawi',
                'unit_label' => 'KC Ngawi',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = new DashboardHarianSnapshotService();
        $area6Filters = $service->fetchFilterOptions('2026-05-06', ['KC Madiun', 'KC Ngawi'], null);
        $madiunFilters = $service->fetchFilterOptions('2026-05-06', 'KC Madiun', null);

        $this->assertSame('Area 6', $area6Filters['kanca'][0]['label']);
        $this->assertSame([['value' => 'all', 'label' => 'Semua Unit Kerja']], $area6Filters['unit_kerja']);
        $this->assertContains('unit-a', array_column($madiunFilters['unit_kerja'], 'value'));
        $this->assertNotContains('kc-ngawi', array_column($madiunFilters['unit_kerja'], 'value'));
    }

    public function test_fetch_periods_keeps_available_source_dates_when_historical_snapshots_are_incomplete(): void
    {
        $this->createSourceMetadataTables();

        foreach (['2026-05-01', '2026-05-02'] as $period) {
            DB::table('ssa_pinjaman')->insert([
                'month_day_year_of_periode' => $period,
                'nama_cabang' => 'KC Madiun',
                'nama_uker' => '00045 -- KC Madiun',
                'baki_debet' => 1_000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ssa_simpanan')->insert([
                'Month_Day_Year_of_Posisi' => $period,
                'nama_cabang' => 'KC Madiun',
                'nama_uker' => '00045 -- KC Madiun',
                'saldo' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('dashboard_harian_snapshots')->insert([
            'uniqueid_dhs' => 'madiun-summary-2026-05-02',
            'snapshot_period' => '2026-05-02',
            'kanca_key' => 'kc-madiun',
            'kanca_label' => 'KC Madiun',
            'unit_key' => 'kc-madiun',
            'unit_label' => 'KC Madiun',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $periods = (new DashboardHarianSnapshotService())->fetchPeriods()->all();

        $this->assertSame(['2026-05-02', '2026-05-01'], $periods);
    }

    public function test_timeseries_sml_uses_percentage_metric_without_currency_scaling(): void
    {
        $this->createSourceMetadataTables();

        Schema::table('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->decimal('total_sml_pct_non_commercial', 12, 4)->default(0);
            $table->decimal('total_sml_abs_non_commercial', 20, 2)->default(0);
            $table->decimal('total_os_non_commercial', 20, 2)->default(0);
        });

        DB::table('dashboard_harian_snapshots')->insert([
            [
                'uniqueid_dhs' => 'madiun-summary-2026-05-01',
                'snapshot_period' => '2026-05-01',
                'kanca_key' => 'kc-madiun',
                'kanca_label' => 'KC Madiun',
                'unit_key' => 'kc-madiun',
                'unit_label' => 'KC Madiun',
                'source_row_count' => 1,
                'total_sml_pct_non_commercial' => 7.25,
                'total_sml_abs_non_commercial' => 725,
                'total_os_non_commercial' => 10000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uniqueid_dhs' => 'ngawi-summary-2026-05-01',
                'snapshot_period' => '2026-05-01',
                'kanca_key' => 'kc-ngawi',
                'kanca_label' => 'KC Ngawi',
                'unit_key' => 'kc-ngawi',
                'unit_label' => 'KC Ngawi',
                'source_row_count' => 1,
                'total_sml_pct_non_commercial' => 3.0,
                'total_sml_abs_non_commercial' => 300,
                'total_os_non_commercial' => 10000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = new DashboardHarianSnapshotService();
        $canUseSnapshotMetrics = new \ReflectionProperty($service, 'canUseSnapshotMetricsCache');
        $canUseSnapshotMetrics->setAccessible(true);
        $canUseSnapshotMetrics->setValue($service, true);

        $payload = $service->fetchTimeseriesTrend(['2026-05'], 'sml', ['KC Madiun', 'KC Ngawi'], null);

        $this->assertSame('percent', $payload['value_type']);
        $this->assertSame(5.125, $payload['area_total']['2026-05'][0]);
        $this->assertSame(7.25, $payload['series']['KC Madiun']['2026-05'][0]);
        $this->assertSame(3.0, $payload['series']['KC Ngawi']['2026-05'][0]);
    }

    public function test_recovery_timeseries_uses_gi405_daily_delta_for_ritel_and_mikro(): void
    {
        Schema::create('referensi_uker', function (Blueprint $table): void {
            $table->string('kode_uker')->primary();
            $table->string('nama_uker');
            $table->string('nama_cabang');
        });

        Schema::create('gi405_recovery', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->index();
            $table->string('kode_uker')->index();
            $table->string('nama_uker')->nullable();
            $table->decimal('pendapatan_koreksi_ppap_dr_angsuran_ph', 24, 2)->default(0);
        });

        DB::table('referensi_uker')->insert([
            [
                'kode_uker' => '00045',
                'nama_uker' => '00045 -- KC Madiun',
                'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            ],
            [
                'kode_uker' => '00552',
                'nama_uker' => '00552 -- KCP Caruban',
                'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            ],
            [
                'kode_uker' => '06343',
                'nama_uker' => '06343 -- UNIT MUNENG MADIUN',
                'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            ],
            [
                'kode_uker' => '00049',
                'nama_uker' => '00049 -- KC Magetan',
                'nama_cabang' => '00049 -- KC Magetan (Konsolidasi-MB)',
            ],
            [
                'kode_uker' => '00999',
                'nama_uker' => '00999 -- KCP BARU MADIUN',
                'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            ],
        ]);

        $cumulative = [
            '00045' => [-1_000_000_000, -1_200_000_000, -1_500_000_000, -1_900_000_000],
            '00552' => [-500_000_000, -550_000_000, -650_000_000, -800_000_000],
            '06343' => [-2_000_000_000, -2_300_000_000, -2_700_000_000, -3_200_000_000],
            '00049' => [-4_000_000_000, -4_500_000_000, -5_000_000_000, -5_500_000_000],
        ];
        $rows = [];
        foreach ($cumulative as $kodeUker => $values) {
            foreach ($values as $offset => $value) {
                $rows[] = [
                    'periode' => '2026-06-' . (20 + $offset),
                    'kode_uker' => $kodeUker,
                    'nama_uker' => null,
                    'pendapatan_koreksi_ppap_dr_angsuran_ph' => $value,
                ];
            }
        }
        $rows[] = [
            'periode' => '2026-06-23',
            'kode_uker' => '00999',
            'nama_uker' => null,
            'pendapatan_koreksi_ppap_dr_angsuran_ph' => -10_000_000_000,
        ];
        DB::table('gi405_recovery')->insert($rows);

        $service = new DashboardHarianSnapshotService();
        $ritel = $service->fetchTimeseriesTrend(
            ['2026-06'],
            'recovery',
            ['KC Madiun'],
            null,
            'ritel'
        );
        $mikro = $service->fetchTimeseriesTrend(
            ['2026-06'],
            'recovery',
            ['KC Madiun'],
            null,
            'micro'
        );
        $fallback = $service->fetchTimeseriesTrend(
            ['2026-06'],
            'recovery',
            ['KC Madiun'],
            null,
            'total'
        );
        $dimensions = $service->fetchRecoveryDimensionOptions();

        $this->assertSame('currency_million', $ritel['value_type']);
        $this->assertNull($ritel['series']['KC Madiun']['2026-06'][19]);
        $this->assertEqualsWithDelta(250.0, $ritel['series']['KC Madiun']['2026-06'][20], 0.000001);
        $this->assertEqualsWithDelta(400.0, $ritel['series']['KC Madiun']['2026-06'][21], 0.000001);
        $this->assertEqualsWithDelta(550.0, $ritel['series']['KC Madiun']['2026-06'][22], 0.000001);
        $this->assertEqualsWithDelta(300.0, $mikro['series']['KC Madiun']['2026-06'][20], 0.000001);
        $this->assertEqualsWithDelta(400.0, $mikro['series']['KC Madiun']['2026-06'][21], 0.000001);
        $this->assertEqualsWithDelta(500.0, $mikro['series']['KC Madiun']['2026-06'][22], 0.000001);
        $this->assertSame($ritel['series'], $fallback['series']);
        $this->assertSame(['Ritel', 'Mikro'], $dimensions['segments']);
        $this->assertSame([], $dimensions['products']);
    }

    public function test_source_metadata_signature_changes_when_source_values_change(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-04-20',
            'baki_debet' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-04-20',
            'saldo' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cognos_recovery')->insert([
            'periode' => '2026-04-20',
            'total_recovery' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $reflection = new \ReflectionMethod($service, 'buildSourceMetadata');
        $reflection->setAccessible(true);

        $before = $reflection->invoke($service, '2026-04-20');

        DB::table('ssa_pinjaman')
            ->where('month_day_year_of_periode', '2026-04-20')
            ->update(['baki_debet' => 1200]);

        $after = $reflection->invoke($service, '2026-04-20');

        $this->assertNotSame($before['source_signature'], $after['source_signature']);
        $this->assertSame(1, $after['source_loan_row_count']);
        $this->assertSame(1, $after['source_savings_row_count']);
        $this->assertSame(1, $after['source_recovery_row_count']);
        $this->assertSame('2026-04-20', $after['source_recovery_period']);
    }

    public function test_rebuild_does_not_cut_timeseries_when_gi405_recovery_is_empty(): void
    {
        $this->createSourceMetadataTables();

        Schema::create('gi405_recovery', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
        });

        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-05-01',
            'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            'nama_uker' => '00045 -- KC Madiun',
            'segmen_2025' => 'Consumer',
            'kolektabilitas_one_obligor' => '1',
            'baki_debet' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-05-01',
            'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            'nama_uker' => '00045 -- KC Madiun',
            'segmentasi' => 'Ritel',
            'produk' => 'Tabungan',
            'saldo' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = (new DashboardHarianSnapshotService())->rebuild(null, true);

        $this->assertArrayHasKey('2026-05-01', $result);
        $this->assertGreaterThan(0, $result['2026-05-01']);
        $this->assertTrue(
            DB::table('dashboard_harian_snapshots')
                ->where('snapshot_period', '2026-05-01')
                ->exists()
        );
    }

    public function test_consumer_briguna_includes_consumer_rows_marked_briguna_mikro(): void
    {
        $this->createSourceMetadataTables();

        $baseRow = [
            'month_day_year_of_periode' => '2026-05-31',
            'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            'nama_uker' => '00045 -- KC Madiun',
            'segmen_2025' => 'Consumer',
            'kolektabilitas_one_obligor' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('ssa_pinjaman')->insert([
            $baseRow + [
                'segmen_dashboard' => 'Consumer',
                'produk_dashboard' => 'Briguna-Konsumer',
                'produk' => 'Briguna Ritel',
                'baki_debet' => 100,
            ],
            $baseRow + [
                'segmen_dashboard' => 'Consumer',
                'produk_dashboard' => 'Briguna-Mikro',
                'produk' => 'Briguna Ritel',
                'baki_debet' => 25,
            ],
            $baseRow + [
                'segmen_dashboard' => 'Consumer',
                'produk_dashboard' => 'KPR',
                'produk' => 'KPR',
                'baki_debet' => 50,
            ],
            $baseRow + [
                'segmen_dashboard' => 'Micro',
                'produk_dashboard' => 'Briguna-Mikro',
                'produk' => 'Briguna Mikro',
                'segmen_2025' => 'Micro',
                'baki_debet' => 300,
            ],
        ]);

        $service = new DashboardHarianSnapshotService();
        $aggregateBuilder = new \ReflectionMethod($service, 'fetchLoanAggregates');
        $aggregateBuilder->setAccessible(true);

        $row = $aggregateBuilder->invoke($service, '2026-05-31')->first();

        $this->assertNotNull($row);
        $this->assertSame(125.0, (float) $row->briguna_konsumer_os);
        $this->assertSame(50.0, (float) $row->kpr_os);
        $this->assertSame(300.0, (float) $row->briguna_mikro_os);
    }

    public function test_ssa_pinjaman_kolektabilitas_zero_is_excluded_from_loan_snapshot_metrics(): void
    {
        $this->createSourceMetadataTables();

        Schema::table('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->decimal('kpr_os', 20, 2)->default(0);
            $table->decimal('kpr_sml', 20, 2)->default(0);
            $table->decimal('kpr_npl', 20, 2)->default(0);
            $table->decimal('total_os', 20, 2)->default(0);
            $table->decimal('total_os_non_commercial', 20, 2)->default(0);
            $table->decimal('total_sml_abs_non_commercial', 20, 2)->default(0);
            $table->decimal('total_npl_abs_non_commercial', 20, 2)->default(0);
        });

        $baseLoanRow = [
            'month_day_year_of_periode' => '2026-05-31',
            'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            'nama_uker' => '00045 -- KC Madiun',
            'segmen_dashboard' => 'Consumer',
            'produk_dashboard' => 'KPR',
            'produk' => 'KPR',
            'segmen_2025' => 'Consumer',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('ssa_pinjaman')->insert([
            $baseLoanRow + ['kolektabilitas_one_obligor' => '0', 'baki_debet' => 900],
            $baseLoanRow + ['kolektabilitas_one_obligor' => '1', 'baki_debet' => 100],
            $baseLoanRow + ['kolektabilitas_one_obligor' => '2', 'baki_debet' => 200],
            $baseLoanRow + ['kolektabilitas_one_obligor' => '3', 'baki_debet' => 300],
            $baseLoanRow + ['kolektabilitas_one_obligor' => '5', 'baki_debet' => 500],
            $baseLoanRow + ['kolektabilitas_one_obligor' => '6', 'baki_debet' => 600],
        ]);

        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-05-31',
            'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            'nama_uker' => '00045 -- KC Madiun',
            'segmentasi' => 'Ritel',
            'produk' => 'Tabungan',
            'saldo' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();

        $this->assertSame(2, $service->buildPeriodSnapshot('2026-05-31', true));

        $summary = DB::table('dashboard_harian_snapshots')
            ->where('snapshot_period', '2026-05-31')
            ->where('kanca_key', 'kc-madiun')
            ->where('unit_key', 'kc-madiun')
            ->first();

        $this->assertNotNull($summary);
        $this->assertSame(1100.0, (float) $summary->kpr_os);
        $this->assertSame(200.0, (float) $summary->kpr_sml);
        $this->assertSame(800.0, (float) $summary->kpr_npl);
        $this->assertSame(1100.0, (float) $summary->total_os);
        $this->assertSame(1100.0, (float) $summary->total_os_non_commercial);
        $this->assertSame(200.0, (float) $summary->total_sml_abs_non_commercial);
        $this->assertSame(800.0, (float) $summary->total_npl_abs_non_commercial);
    }

    public function test_snapshot_freshness_rebuilds_legacy_rows_and_rejects_changed_signature(): void
    {
        $this->createSourceMetadataTables();

        DB::table('dashboard_harian_snapshots')->insert([
            'uniqueid_dhs' => 'legacy',
            'snapshot_period' => '2026-04-20',
            'kanca_key' => 'legacy',
            'unit_key' => 'legacy',
            'source_signature' => null,
        ]);

        $service = new DashboardHarianSnapshotService();
        $reflection = new \ReflectionMethod($service, 'snapshotSourceIsFresh');
        $reflection->setAccessible(true);

        $this->assertFalse($reflection->invoke($service, '2026-04-20', ['source_signature' => 'new']));

        DB::table('dashboard_harian_snapshots')
            ->where('uniqueid_dhs', 'legacy')
            ->update(['source_signature' => 'old']);

        $this->assertFalse($reflection->invoke($service, '2026-04-20', ['source_signature' => 'new']));
        $this->assertTrue($reflection->invoke($service, '2026-04-20', ['source_signature' => 'old']));

        DB::table('dashboard_harian_snapshots')
            ->where('uniqueid_dhs', 'legacy')
            ->update([
                'source_loan_row_count' => 999,
                'source_savings_row_count' => 1,
                'source_recovery_row_count' => 0,
                'source_recovery_period' => null,
            ]);

        $this->assertFalse($reflection->invoke($service, '2026-04-20', [
            'source_signature' => 'old',
            'source_loan_row_count' => 1,
            'source_savings_row_count' => 1,
            'source_recovery_row_count' => 0,
            'source_recovery_period' => null,
        ]));

        DB::table('dashboard_harian_snapshots')
            ->where('uniqueid_dhs', 'legacy')
            ->update(['source_loan_row_count' => 1]);

        $this->assertTrue($reflection->invoke($service, '2026-04-20', [
            'source_signature' => 'old',
            'source_loan_row_count' => 1,
            'source_savings_row_count' => 1,
            'source_recovery_row_count' => 0,
            'source_recovery_period' => null,
        ]));
    }

    public function test_sync_due_periods_rebuilds_existing_snapshot_when_lw325_changes_recovery_source(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-04-21',
            'baki_debet' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-04-21',
            'saldo' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('dashboard_harian_snapshots')->insert([
            'uniqueid_dhs' => 'existing-2026-04-21',
            'snapshot_period' => '2026-04-21',
            'kanca_key' => 'kc',
            'unit_key' => 'kc',
            'source_signature' => 'old-signature-before-ph-import',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-04-20',
            'pokok' => 250,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new class extends DashboardHarianSnapshotService {
            public array $builtPeriods = [];

            public function buildPeriodSnapshot(string $period, bool $force = false): int
            {
                $this->builtPeriods[] = [$period, $force];

                return 109;
            }
        };

        $result = $service->syncDuePeriods(['2026-04-21']);

        $this->assertSame(1, $result['built']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(['2026-04-21'], $result['stale']);
        $this->assertSame([['2026-04-21', false]], $service->builtPeriods);
    }

    public function test_rebuild_affected_by_ph_period_force_rebuilds_current_and_next_shared_period(): void
    {
        $this->createSourceMetadataTables();

        foreach (['2026-04-20', '2026-04-21'] as $period) {
            DB::table('ssa_pinjaman')->insert([
                'month_day_year_of_periode' => $period,
                'baki_debet' => 1000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ssa_simpanan')->insert([
                'Month_Day_Year_of_Posisi' => $period,
                'saldo' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $service = new class extends DashboardHarianSnapshotService {
            public array $builtPeriods = [];

            public function buildPeriodSnapshot(string $period, bool $force = false): int
            {
                $this->builtPeriods[] = [$period, $force];

                return 109;
            }
        };

        $result = $service->rebuildAffectedByPhPeriod('2026-04-20', true);

        $this->assertSame([
            '2026-04-20' => 109,
            '2026-04-21' => 109,
        ], $result);
        $this->assertSame([
            ['2026-04-20', true],
            ['2026-04-21', true],
        ], $service->builtPeriods);
    }

    public function test_yoy_period_falls_back_to_ssa_source_when_snapshot_is_missing(): void
    {
        $this->createSourceMetadataTables();

        foreach (['2025-04-30', '2026-04-27'] as $period) {
            DB::table('ssa_pinjaman')->insert([
                'month_day_year_of_periode' => $period,
                'baki_debet' => 1000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ssa_simpanan')->insert([
                'Month_Day_Year_of_Posisi' => $period,
                'saldo' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('dashboard_harian_snapshots')->insert([
            'uniqueid_dhs' => 'current-only',
            'snapshot_period' => '2026-04-27',
            'kanca_key' => 'kc',
            'unit_key' => 'kc',
            'source_signature' => 'current',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();

        $this->assertSame('2025-04-30', $service->resolveEffectivePeriod('2025-04-30'));
        $this->assertSame('2025-04-30', $service->resolveComparisonPeriods('2026-04-27')['yoy']);
    }

    public function test_effective_period_builds_latest_shared_source_when_snapshot_is_missing(): void
    {
        $this->createSourceMetadataTables();

        foreach (['2026-04-27', '2026-04-28'] as $period) {
            DB::table('ssa_pinjaman')->insert([
                'month_day_year_of_periode' => $period,
                'baki_debet' => 1000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ssa_simpanan')->insert([
                'Month_Day_Year_of_Posisi' => $period,
                'saldo' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('dashboard_harian_snapshots')->insert([
            'uniqueid_dhs' => 'previous',
            'snapshot_period' => '2026-04-27',
            'kanca_key' => 'kc',
            'unit_key' => 'kc',
            'source_signature' => 'previous',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new class extends DashboardHarianSnapshotService {
            public function buildPeriodSnapshot(string $period, bool $force = false): int
            {
                DB::table(self::SNAPSHOT_TABLE)->insert([
                    'uniqueid_dhs' => 'built-' . $period,
                    'snapshot_period' => $period,
                    'kanca_key' => 'kc',
                    'unit_key' => 'kc',
                    'source_signature' => 'built',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }
        };

        $this->assertSame('2026-04-28', $service->resolveEffectivePeriod(null));
        $this->assertSame(1, DB::table('dashboard_harian_snapshots')->where('snapshot_period', '2026-04-28')->count());
    }

    public function test_explicit_sync_candidate_builds_missing_period_even_when_shared_period_cache_is_stale(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-04-28',
            'baki_debet' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-04-28',
            'saldo' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new class extends DashboardHarianSnapshotService {
            public array $builtPeriods = [];

            public function buildPeriodSnapshot(string $period, bool $force = false): int
            {
                $this->builtPeriods[] = [$period, $force];

                DB::table(self::SNAPSHOT_TABLE)->insert([
                    'uniqueid_dhs' => 'built-' . $period,
                    'snapshot_period' => $period,
                    'kanca_key' => 'kc',
                    'unit_key' => 'kc',
                    'source_signature' => 'built',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }
        };

        $sharedPeriods = new \ReflectionProperty(DashboardHarianSnapshotService::class, 'sharedPeriodsRequestCache');
        $sharedPeriods->setAccessible(true);
        $sharedPeriods->setValue($service, ['2026-04-27']);

        $result = $service->syncDuePeriods(['2026-04-28']);

        $this->assertSame(1, $result['built']);
        $this->assertSame(['2026-04-28'], $result['missing']);
        $this->assertSame([['2026-04-28', false]], $service->builtPeriods);
        $this->assertSame(1, DB::table('dashboard_harian_snapshots')->where('snapshot_period', '2026-04-28')->count());
    }

    public function test_sync_due_periods_rebuilds_duplicate_snapshot_keys_even_when_signature_is_fresh(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-04-29',
            'baki_debet' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-04-29',
            'saldo' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new class extends DashboardHarianSnapshotService {
            public array $builtPeriods = [];

            public function buildPeriodSnapshot(string $period, bool $force = false): int
            {
                $this->builtPeriods[] = [$period, $force];

                return 109;
            }
        };

        $metadataBuilder = new \ReflectionMethod($service, 'buildSourceMetadata');
        $metadataBuilder->setAccessible(true);
        $metadata = $metadataBuilder->invoke($service, '2026-04-29');

        foreach (['duplicate-a', 'duplicate-b'] as $uniqueId) {
            DB::table('dashboard_harian_snapshots')->insert([
                'uniqueid_dhs' => $uniqueId,
                'snapshot_period' => '2026-04-29',
                'kanca_key' => 'kc-madiun',
                'unit_key' => 'kc-madiun',
                'source_signature' => $metadata['source_signature'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $result = $service->syncDuePeriods(['2026-04-29']);

        $this->assertSame(1, $result['built']);
        $this->assertSame(['2026-04-29'], $result['stale']);
        $this->assertSame([['2026-04-29', false]], $service->builtPeriods);
    }

    public function test_l1133_import_affects_shared_periods_until_next_l1133_period(): void
    {
        $this->createSourceMetadataTables();

        foreach (['2026-05-12', '2026-05-13', '2026-05-16', '2026-05-18'] as $period) {
            DB::table('ssa_simpanan')->insert([
                'Month_Day_Year_of_Posisi' => $period,
                'saldo' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('dly_kap_resegmentasi')->insert([
                'periode' => $period,
                'tl_rp' => 1000,
                'dpk_rp' => 100,
                'npl_rp' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['2026-05-12', '2026-05-18'] as $period) {
            DB::table('l1133')->insert([
                'periode' => $period,
                'kode_kanca' => '00045',
                'nama_kanca' => 'KC Madiun',
                'kode_uker' => '00045',
                'nama_uker' => 'KC Madiun',
                'jenis' => 'KUPEDES KOMERSIAL',
                'outstanding' => 5000,
                'dpk' => 50,
                'npl' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $service = new DashboardHarianSnapshotService();

        $this->assertSame(
            ['2026-05-12', '2026-05-13', '2026-05-16'],
            $service->resolveAffectedSnapshotPeriodsForLoanFallback('l1133', '2026-05-12')
        );
    }

    public function test_dly_kap_import_affects_only_the_exact_shared_period(): void
    {
        $this->createSourceMetadataTables();

        foreach (['2026-05-15', '2026-05-16'] as $period) {
            DB::table('ssa_simpanan')->insert([
                'Month_Day_Year_of_Posisi' => $period,
                'saldo' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('dly_kap_resegmentasi')->insert([
                'periode' => $period,
                'tl_rp' => 1000,
                'dpk_rp' => 100,
                'npl_rp' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('l1133')->insert([
                'periode' => $period,
                'kode_kanca' => '00045',
                'nama_kanca' => 'KC Madiun',
                'kode_uker' => '00045',
                'nama_uker' => 'KC Madiun',
                'jenis' => 'KUPEDES KOMERSIAL',
                'outstanding' => 5000,
                'dpk' => 50,
                'npl' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $service = new DashboardHarianSnapshotService();

        $this->assertSame(
            ['2026-05-16'],
            $service->resolveAffectedSnapshotPeriodsForLoanFallback('dly_kap_resegmentasi', '2026-05-16')
        );
    }

    public function test_shared_period_can_use_dly_kap_and_l1133_when_ssa_pinjaman_is_not_available_yet(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-05-03',
            'saldo' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('dly_kap_resegmentasi')->insert([
            'periode' => '2026-05-03',
            'tl_rp' => 1000,
            'dpk_rp' => 100,
            'npl_rp' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('l1133')->insert([
            'periode' => '2026-05-03',
            'kode_kanca' => '00045',
            'nama_kanca' => 'KC Madiun',
            'kode_uker' => '00045',
            'nama_uker' => 'KC Madiun',
            'jenis' => 'KUPEDES KOMERSIAL',
            'outstanding' => 5000,
            'dpk' => 50,
            'npl' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $reflection = new \ReflectionMethod($service, 'resolveSharedPeriods');
        $reflection->setAccessible(true);

        $this->assertContains('2026-05-03', $reflection->invoke($service));
    }

    public function test_hourly_dpk_cannot_replace_ssa_simpanan_for_landing_source_options(): void
    {
        $this->createSourceMetadataTables();

        DB::table('hourly_dpk')->insert([
            'uniqueid_namareport' => 'HDPK-1',
            'posisi' => '2026-05-10',
            'mbname' => '00045 -- KC Madiun(Konsolidasi-MB)',
            'brname' => '00045 -- KC Madiun',
            'segmen' => 'KORPORASI',
            'produk' => 'GIRO',
            'saldo' => 1000,
        ]);
        DB::table('l1133')->insert([
            'periode' => '2026-05-10',
            'kode_kanca' => '00045',
            'nama_kanca' => 'KC Madiun',
            'kode_uker' => '00045',
            'nama_uker' => 'KC Madiun',
            'jenis' => 'KUPEDES KOMERSIAL',
            'outstanding' => 5000,
            'dpk' => 50,
            'npl' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('dly_kap_resegmentasi')->insert([
            'periode' => '2026-05-10',
            'tl_rp' => 1000,
            'dpk_rp' => 100,
            'npl_rp' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $sharedPeriods = new \ReflectionMethod($service, 'resolveSharedPeriods');
        $sharedPeriods->setAccessible(true);

        $this->assertNotContains('2026-05-10', $sharedPeriods->invoke($service));
    }

    public function test_ssa_simpanan_stays_primary_when_hourly_dpk_is_also_available(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-05-10',
            'nama_cabang' => '00045 -- KC Madiun(Konsolidasi-MB)',
            'nama_uker' => '00045 -- KC Madiun',
            'segmentasi' => 'RITEL',
            'produk' => 'GIRO',
            'saldo' => 2000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('hourly_dpk')->insert([
            'uniqueid_namareport' => 'HDPK-1',
            'posisi' => '2026-05-10',
            'mbname' => '00045 -- KC Madiun(Konsolidasi-MB)',
            'brname' => '00045 -- KC Madiun',
            'segmen' => 'KORPORASI',
            'produk' => 'GIRO',
            'saldo' => 1000,
        ]);
        DB::table('l1133')->insert([
            'periode' => '2026-05-10',
            'kode_kanca' => '00045',
            'nama_kanca' => 'KC Madiun',
            'kode_uker' => '00045',
            'nama_uker' => 'KC Madiun',
            'jenis' => 'KUPEDES KOMERSIAL',
            'outstanding' => 5000,
            'dpk' => 50,
            'npl' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $aggregateBuilder = new \ReflectionMethod($service, 'fetchSavingsAggregates');
        $aggregateBuilder->setAccessible(true);

        $payload = $aggregateBuilder->invoke($service, '2026-05-10', null, null);
        $areaRow = $payload->first();

        $this->assertNotNull($areaRow);
        $this->assertSame(2000.0, (float) $areaRow->giro_ritel);
        $this->assertSame(0.0, (float) $areaRow->giro_wholesale);
        $this->assertSame(2000.0, (float) $areaRow->total_simpanan);
    }

    public function test_hourly_dpk_without_fallback_loan_is_not_a_shared_period(): void
    {
        $this->createSourceMetadataTables();

        DB::table('hourly_dpk')->insert([
            'uniqueid_namareport' => 'HDPK-1',
            'posisi' => '2026-05-10',
            'mbname' => '00045 -- KC Madiun(Konsolidasi-MB)',
            'brname' => '00045 -- KC Madiun',
            'segmen' => 'RITEL',
            'produk' => 'GIRO',
            'saldo' => 1000,
        ]);
        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-05-10',
            'baki_debet' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $sharedPeriods = new \ReflectionMethod($service, 'resolveSharedPeriods');
        $sharedPeriods->setAccessible(true);

        $this->assertNotContains('2026-05-10', $sharedPeriods->invoke($service));
    }

    public function test_l1133_micro_aggregates_include_fully_cash_collateral_in_kupedes(): void
    {
        $this->createSourceMetadataTables();

        $baseRow = [
            'periode' => '2026-05-03',
            'kode_kanca' => '001',
            'nama_kanca' => 'KC Madiun',
            'kode_uker' => '00070',
            'nama_uker' => '00070 -- UNIT A',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('l1133')->insert([
            $baseRow + ['jenis' => 'KUPEDES KOMERSIAL', 'outstanding' => 100, 'dpk' => 10, 'npl' => 1],
            $baseRow + ['jenis' => 'KUPEDES RAKYAT', 'outstanding' => 200, 'dpk' => 20, 'npl' => 2],
            $baseRow + ['jenis' => 'RITEL KOMERSIAL FULLY CASH COLLATERAL', 'outstanding' => 300, 'dpk' => 30, 'npl' => 3],
            $baseRow + ['jenis' => 'KUPEDES GBT', 'outstanding' => 400, 'dpk' => 40, 'npl' => 4],
            $baseRow + ['jenis' => 'KUR MIKRO BARU', 'outstanding' => 500, 'dpk' => 50, 'npl' => 5],
            $baseRow + ['jenis' => 'KPR', 'outstanding' => 600, 'dpk' => 60, 'npl' => 6],
            array_merge($baseRow, [
                'kode_uker' => '00045',
                'nama_uker' => '00045 -- KC Madiun',
                'jenis' => 'KPR',
                'outstanding' => 900,
                'dpk' => 90,
                'npl' => 9,
            ]),
            array_merge($baseRow, [
                'kode_uker' => '00046',
                'nama_uker' => '00046 -- KCP Madiun',
                'jenis' => 'KPR',
                'outstanding' => 700,
                'dpk' => 70,
                'npl' => 7,
            ]),
        ]);

        $service = new DashboardHarianSnapshotService();
        $scopeCache = new \ReflectionProperty($service, 'unitScopeMapCache');
        $scopeCache->setAccessible(true);
        $scopeCache->setValue($service, [
            '2026-05-03' => collect([
                '70' => [
                    'raw_cabang' => 'KC Madiun',
                    'raw_unit' => '00070 -- UNIT A',
                    'kanca_label' => 'KC Madiun',
                    'unit_key' => 'unit-a',
                ],
                '45' => [
                    'raw_cabang' => 'KC Madiun',
                    'raw_unit' => '00045 -- KC Madiun',
                    'kanca_label' => 'KC Madiun',
                    'unit_key' => 'kc-madiun-detail',
                ],
                '46' => [
                    'raw_cabang' => 'KC Madiun',
                    'raw_unit' => '00046 -- KCP Madiun',
                    'kanca_label' => 'KC Madiun',
                    'unit_key' => 'kcp-madiun',
                ],
            ]),
            '2026-05-04' => collect([
                '70' => [
                    'raw_cabang' => 'KC Madiun',
                    'raw_unit' => '00070 -- UNIT A',
                    'kanca_label' => 'KC Madiun',
                    'unit_key' => 'unit-a',
                ],
            ]),
        ]);

        $reflection = new \ReflectionMethod($service, 'fetchL1133MicroLoanAggregates');
        $reflection->setAccessible(true);

        $rows = $reflection->invoke($service, '2026-05-03');
        $row = $rows->first();

        $this->assertCount(1, $rows);
        $this->assertSame(600.0, (float) $row->kupedes_os);
        $this->assertSame(60.0, (float) $row->kupedes_sml);
        $this->assertSame(6.0, (float) $row->kupedes_npl);
        $this->assertSame(400.0, (float) $row->briguna_mikro_os);
        $this->assertSame(500.0, (float) $row->kur_mikro_os);
        $this->assertSame(600.0, (float) $row->kur_kpp_os);

        $nextDayRows = $reflection->invoke($service, '2026-05-04');
        $nextDayRow = $nextDayRows->first();

        $this->assertCount(1, $nextDayRows);
        $this->assertSame(600.0, (float) $nextDayRow->kupedes_os);
        $this->assertSame(400.0, (float) $nextDayRow->briguna_mikro_os);
    }

    public function test_ssa_pinjaman_stays_primary_when_l1133_fallback_is_available(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-05-20',
            'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            'nama_uker' => '06340 -- UNIT DAGANGAN MADIUN',
            'segmen_dashboard' => 'MIKRO',
            'produk_dashboard' => 'KUR-MIKRO',
            'produk' => 'KUR MIKRO',
            'kolektabilitas_one_obligor' => '1',
            'baki_debet' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('l1133')->insert([
            'periode' => '2026-05-19',
            'kode_kanca' => '00045',
            'nama_kanca' => 'KC Madiun',
            'kode_uker' => '06340',
            'nama_uker' => '06340 -- UNIT DAGANGAN MADIUN',
            'jenis' => 'KUR MIKRO BARU',
            'outstanding' => 9000,
            'dpk' => 900,
            'npl' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $scopeCache = new \ReflectionProperty($service, 'unitScopeMapCache');
        $scopeCache->setAccessible(true);
        $scopeCache->setValue($service, [
            '2026-05-20' => collect([
                '6340' => [
                    'raw_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
                    'raw_unit' => '06340 -- UNIT DAGANGAN MADIUN',
                    'kanca_label' => 'KC Madiun',
                    'unit_key' => 'unit-dagangan-madiun',
                ],
            ]),
        ]);

        $aggregateBuilder = new \ReflectionMethod($service, 'fetchLoanAggregates');
        $aggregateBuilder->setAccessible(true);
        $rows = $aggregateBuilder->invoke($service, '2026-05-20');

        $metadataBuilder = new \ReflectionMethod($service, 'buildSourceMetadata');
        $metadataBuilder->setAccessible(true);
        $metadata = $metadataBuilder->invoke($service, '2026-05-20');

        $this->assertCount(1, $rows);
        $this->assertSame(1000.0, (float) $rows->first()->kur_mikro_os);
        $this->assertSame(0.0, (float) $rows->first()->kur_mikro_sml);
        $this->assertSame(0.0, (float) $rows->first()->kur_mikro_npl);
        $this->assertSame(1, $metadata['source_loan_row_count']);
    }

    public function test_lw325_recovery_source_uses_exact_snapshot_ph_and_previous_month_end_comparison(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-05-07',
            'baki_debet' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-05-07',
            'saldo' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-04-30',
            'pokok' => 250,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-04-29',
            'pokok' => 777,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-05-06',
            'pokok' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-05-07',
            'pokok' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $reflection = new \ReflectionMethod($service, 'buildSourceMetadata');
        $reflection->setAccessible(true);

        $metadata = $reflection->invoke($service, '2026-05-07');

        $this->assertSame('2026-04-30', $metadata['source_recovery_period']);
        $this->assertSame(1, $metadata['source_recovery_row_count']);
    }

    public function test_ph_recovery_uses_previous_month_balance_delta_for_tupok_and_lunas(): void
    {
        $this->createSourceMetadataTables();

        DB::table('lw325_ph')->insert([
            'periode' => '2026-04-30',
            'acctno' => 'A1',
            'kanca' => 'KC Madiun',
            'unit' => 'Unit A',
            'segmen_dashboard' => 'SMALL',
            'pokok' => 100000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-05-06',
            'acctno' => 'A1',
            'kanca' => 'KC Madiun',
            'unit' => 'Unit A',
            'segmen_dashboard' => 'SMALL',
            'pokok' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-05-07',
            'acctno' => 'A1',
            'kanca' => 'KC Madiun',
            'unit' => 'Unit A',
            'segmen_dashboard' => null,
            'pokok' => 99000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-04-30',
            'acctno' => 'A2',
            'kanca' => 'KC Madiun',
            'unit' => 'Unit A',
            'segmen_dashboard' => 'MICRO',
            'pokok' => 50000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $reflection = new \ReflectionMethod($service, 'fetchPhAggregates');
        $reflection->setAccessible(true);

        $rows = $reflection->invoke($service, '2026-05-07');
        $row = $rows->first();

        $this->assertNotNull($row);
        $this->assertSame(1000000.0, (float) $row->ph_tupok);
        $this->assertSame(50000000.0, (float) $row->ph_lunas);
        $this->assertSame(1000000.0, (float) $row->rec_dh_small);
        $this->assertSame(50000000.0, (float) $row->rec_dh_micro);
        $this->assertSame(51000000.0, (float) $row->rec_dh_total);
    }

    public function test_recovery_aggregates_overlay_lw325_ph_when_primary_recovery_exists(): void
    {
        Schema::create('cognos_recovery', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->index();
            $table->string('cabang')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->string('segmen_bisnis_2025')->nullable();
            $table->decimal('total_recovery', 20, 2)->nullable();
        });

        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->string('acctno')->nullable();
            $table->string('kanca')->nullable();
            $table->string('unit')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->decimal('pokok', 20, 2)->nullable();
            $table->timestamps();
        });

        DB::table('cognos_recovery')->insert([
            'periode' => '2026-07-11',
            'cabang' => 'KC Madiun',
            'unit_kerja' => 'KC Madiun',
            'segmen_bisnis_2025' => 'SMALL',
            'total_recovery' => 700,
        ]);

        DB::table('lw325_ph')->insert([
            [
                'periode' => '2026-06-30',
                'acctno' => '0001',
                'kanca' => 'KC Madiun',
                'unit' => 'KC Madiun',
                'segmen_dashboard' => 'SMALL',
                'pokok' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'periode' => '2026-07-11',
                'acctno' => '1',
                'kanca' => 'KC Madiun',
                'unit' => 'KC Madiun',
                'segmen_dashboard' => 'SMALL',
                'pokok' => 400,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'periode' => '2026-06-30',
                'acctno' => '0002',
                'kanca' => 'KC Madiun',
                'unit' => 'KC Madiun',
                'segmen_dashboard' => 'MICRO',
                'pokok' => 300,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = new DashboardHarianSnapshotService();
        $reflection = new \ReflectionMethod($service, 'fetchRecoveryAggregates');
        $reflection->setAccessible(true);

        $row = $reflection->invoke($service, '2026-07-11')->first();

        $this->assertNotNull($row);
        $this->assertSame(100.0, (float) $row->ph_tupok);
        $this->assertSame(300.0, (float) $row->ph_lunas);
        $this->assertSame(700.0, (float) $row->rec_dh_total);
    }

    public function test_ph_recovery_does_not_fallback_to_same_month_previous_period(): void
    {
        $this->createSourceMetadataTables();

        DB::table('lw325_ph')->insert([
            'periode' => '2026-05-06',
            'acctno' => 'A1',
            'kanca' => 'KC Madiun',
            'unit' => 'Unit A',
            'segmen_dashboard' => 'SMALL',
            'pokok' => 100000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-05-07',
            'acctno' => 'A1',
            'kanca' => 'KC Madiun',
            'unit' => 'Unit A',
            'segmen_dashboard' => 'SMALL',
            'pokok' => 99000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();

        $aggregates = new \ReflectionMethod($service, 'fetchPhAggregates');
        $aggregates->setAccessible(true);
        $rows = $aggregates->invoke($service, '2026-05-07');

        $metadataBuilder = new \ReflectionMethod($service, 'buildSourceMetadata');
        $metadataBuilder->setAccessible(true);
        $metadata = $metadataBuilder->invoke($service, '2026-05-07');

        $this->assertCount(0, $rows);
        $this->assertNull($metadata['source_recovery_period']);
        $this->assertSame(0, $metadata['source_recovery_row_count']);
    }

    public function test_ph_recovery_does_not_carry_latest_available_period_forward(): void
    {
        $this->createSourceMetadataTables();

        DB::table('lw325_ph')->insert([
            'periode' => '2026-04-30',
            'acctno' => 'A1',
            'kanca' => 'KC Madiun',
            'unit' => 'Unit A',
            'segmen_dashboard' => 'SMALL',
            'pokok' => 100000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-05-07',
            'acctno' => 'A1',
            'kanca' => 'KC Madiun',
            'unit' => 'Unit A',
            'segmen_dashboard' => 'SMALL',
            'pokok' => 99000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();

        $aggregates = new \ReflectionMethod($service, 'fetchPhAggregates');
        $aggregates->setAccessible(true);
        $rows = $aggregates->invoke($service, '2026-05-11');

        $metadataBuilder = new \ReflectionMethod($service, 'buildSourceMetadata');
        $metadataBuilder->setAccessible(true);
        $metadata = $metadataBuilder->invoke($service, '2026-05-11');

        $this->assertCount(0, $rows);
        $this->assertNull($metadata['source_recovery_period']);
        $this->assertSame(0, $metadata['source_recovery_row_count']);
    }

    public function test_ph_recovery_does_not_fallback_to_non_month_end_previous_month_period(): void
    {
        $this->createSourceMetadataTables();

        DB::table('lw325_ph')->insert([
            'periode' => '2026-04-29',
            'acctno' => 'A1',
            'kanca' => 'KC Madiun',
            'unit' => 'Unit A',
            'segmen_dashboard' => 'SMALL',
            'pokok' => 100000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-05-09',
            'acctno' => 'A1',
            'kanca' => 'KC Madiun',
            'unit' => 'Unit A',
            'segmen_dashboard' => 'SMALL',
            'pokok' => 99000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();

        $aggregates = new \ReflectionMethod($service, 'fetchPhAggregates');
        $aggregates->setAccessible(true);
        $rows = $aggregates->invoke($service, '2026-05-09');

        $metadataBuilder = new \ReflectionMethod($service, 'buildSourceMetadata');
        $metadataBuilder->setAccessible(true);
        $metadata = $metadataBuilder->invoke($service, '2026-05-09');

        $this->assertCount(0, $rows);
        $this->assertNull($metadata['source_recovery_period']);
        $this->assertSame(0, $metadata['source_recovery_row_count']);
    }

    public function test_dashboard_harian_snapshot_guard_blocks_kur_kpp_that_absorbs_consumer_kpr(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_pinjaman')->insert([
            [
                'month_day_year_of_periode' => '2026-05-15',
                'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
                'nama_uker' => '00045 -- KC Madiun',
                'segmen_dashboard' => 'Micro',
                'produk_dashboard' => 'KPR',
                'produk' => 'KREDIT MIKRO - KPP',
                'segmen_2025' => 'Micro',
                'kolektabilitas_one_obligor' => '1',
                'baki_debet' => 7_668_000_000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'month_day_year_of_periode' => '2026-05-15',
                'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
                'nama_uker' => '00045 -- KC Madiun',
                'segmen_dashboard' => 'Consumer',
                'produk_dashboard' => 'KPR',
                'produk' => 'KPR',
                'segmen_2025' => 'Consumer',
                'kolektabilitas_one_obligor' => '1',
                'baki_debet' => 272_130_000_000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = new DashboardHarianSnapshotService();
        $guard = new \ReflectionMethod($service, 'guardKurKppSnapshotAgainstSsaSource');
        $guard->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked anomalous kur_kpp_os');

        $guard->invoke($service, '2026-05-15', [[
            'snapshot_period' => '2026-05-15',
            'kanca_key' => 'kc-madiun',
            'kanca_label' => 'KC Madiun',
            'unit_key' => 'kc-madiun',
            'unit_label' => 'KC Madiun',
            'kur_kpp_os' => 279_657_000_000,
        ]]);
    }

    public function test_dashboard_harian_snapshot_guard_blocks_micro_that_does_not_match_ssa_source(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-05-20',
            'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            'nama_uker' => '06340 -- UNIT DAGANGAN MADIUN',
            'segmen_dashboard' => 'Micro',
            'produk_dashboard' => 'KUR-Mikro',
            'produk' => 'KUR Mikro',
            'segmen_2025' => 'Micro',
            'kolektabilitas_one_obligor' => '1',
            'baki_debet' => 1_000_000_000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $guard = new \ReflectionMethod($service, 'guardLoanSnapshotAgainstSsaSource');
        $guard->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked SSA loan mismatch');

        $guard->invoke($service, '2026-05-20', [[
            'snapshot_period' => '2026-05-20',
            'kanca_key' => 'kc-madiun',
            'kanca_label' => 'KC Madiun',
            'unit_key' => 'kc-madiun',
            'unit_label' => 'KC Madiun',
            'kur_mikro_os' => 9_000_000_000,
            'micro_os' => 9_000_000_000,
            'total_os_non_commercial' => 9_000_000_000,
            'total_os' => 9_000_000_000,
        ]]);
    }

    public function test_dashboard_harian_snapshot_guard_blocks_unexpected_loan_branch_not_in_ssa_source(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-05-20',
            'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            'nama_uker' => '06340 -- UNIT DAGANGAN MADIUN',
            'segmen_dashboard' => 'Micro',
            'produk_dashboard' => 'KUR-Mikro',
            'produk' => 'KUR Mikro',
            'segmen_2025' => 'Micro',
            'kolektabilitas_one_obligor' => '1',
            'baki_debet' => 1_000_000_000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $guard = new \ReflectionMethod($service, 'guardLoanSnapshotAgainstSsaSource');
        $guard->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked unexpected SSA loan value');

        $guard->invoke($service, '2026-05-20', [[
            'snapshot_period' => '2026-05-20',
            'kanca_key' => 'kc-magetan',
            'kanca_label' => 'KC Magetan',
            'unit_key' => 'kc-magetan',
            'unit_label' => 'KC Magetan',
            'kur_mikro_os' => 1_000_000_000,
            'micro_os' => 1_000_000_000,
        ]]);
    }

    public function test_dashboard_harian_snapshot_guard_blocks_missing_loan_summary_row_from_ssa_source(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-05-20',
            'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            'nama_uker' => '06340 -- UNIT DAGANGAN MADIUN',
            'segmen_dashboard' => 'Micro',
            'produk_dashboard' => 'KUR-Mikro',
            'produk' => 'KUR Mikro',
            'segmen_2025' => 'Micro',
            'kolektabilitas_one_obligor' => '1',
            'baki_debet' => 1_000_000_000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $guard = new \ReflectionMethod($service, 'guardLoanSnapshotAgainstSsaSource');
        $guard->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked missing SSA loan summary row');

        $guard->invoke($service, '2026-05-20', []);
    }

    public function test_dashboard_harian_snapshot_guard_blocks_savings_that_do_not_match_source(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-05-19',
            'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            'nama_uker' => '00045 -- KC Madiun',
            'segmentasi' => 'Wholesale',
            'produk' => 'Giro',
            'saldo' => 18_360_000_000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $guard = new \ReflectionMethod($service, 'guardSavingsSnapshotAgainstSource');
        $guard->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked savings source mismatch');

        $guard->invoke($service, '2026-05-19', [[
            'snapshot_period' => '2026-05-19',
            'kanca_key' => 'kc-madiun',
            'kanca_label' => 'KC Madiun',
            'unit_key' => 'kc-madiun',
            'unit_label' => 'KC Madiun',
            'giro_wholesale' => 247_792_000_000,
            'simpanan_wholesale' => 247_792_000_000,
            'total_simpanan' => 247_792_000_000,
        ]]);
    }

    public function test_dashboard_harian_snapshot_guard_blocks_unexpected_savings_branch_not_in_source(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-05-19',
            'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            'nama_uker' => '00045 -- KC Madiun',
            'segmentasi' => 'Wholesale',
            'produk' => 'Giro',
            'saldo' => 18_360_000_000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $guard = new \ReflectionMethod($service, 'guardSavingsSnapshotAgainstSource');
        $guard->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked unexpected savings value');

        $guard->invoke($service, '2026-05-19', [[
            'snapshot_period' => '2026-05-19',
            'kanca_key' => 'kc-magetan',
            'kanca_label' => 'KC Magetan',
            'unit_key' => 'kc-magetan',
            'unit_label' => 'KC Magetan',
            'giro_wholesale' => 18_360_000_000,
            'simpanan_wholesale' => 18_360_000_000,
            'total_simpanan' => 18_360_000_000,
        ]]);
    }

    public function test_dashboard_harian_snapshot_guard_blocks_missing_savings_summary_row_from_source(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-05-19',
            'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
            'nama_uker' => '00045 -- KC Madiun',
            'segmentasi' => 'Wholesale',
            'produk' => 'Giro',
            'saldo' => 18_360_000_000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $guard = new \ReflectionMethod($service, 'guardSavingsSnapshotAgainstSource');
        $guard->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked missing savings summary row');

        $guard->invoke($service, '2026-05-19', []);
    }

    public function test_dashboard_harian_snapshot_guard_exception_is_not_swallowed_by_rebuild_wrapper(): void
    {
        $this->createSourceMetadataTables();
        $cacheManager = app('cache');

        $lock = new class {
            public function block(int $seconds, callable $callback): int
            {
                throw new \RuntimeException('Dashboard Harian snapshot guard blocked simulated corrupt payload.');
            }
        };

        Cache::shouldReceive('lock')
            ->once()
            ->with('snapshot:dashboard_harian:build:2026-05-20', 600)
            ->andReturn($lock);

        try {
            (new DashboardHarianSnapshotService())->buildPeriodSnapshot('2026-05-20', true);
            $this->fail('Snapshot guard exception was swallowed by the rebuild wrapper.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('snapshot guard blocked', $e->getMessage());
        } finally {
            app()->instance('cache', $cacheManager);
            \Illuminate\Support\Facades\Facade::clearResolvedInstance('cache');
            Cache::swap($cacheManager);
        }
    }

    public function test_automatic_stale_candidates_include_recent_gi405_recovery_periods(): void
    {
        $this->createSourceMetadataTables();

        Schema::dropIfExists('gi405_recovery');
        Schema::create('gi405_recovery', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->decimal('pendapatan_koreksi_ppap_dr_angsuran_ph', 24, 2)->nullable();
            $table->timestamps();
        });

        DB::table('gi405_recovery')->insert([
            'periode' => '2026-07-03',
            'pendapatan_koreksi_ppap_dr_angsuran_ph' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $method = new \ReflectionMethod($service, 'resolveAutomaticStaleCandidatePeriods');
        $method->setAccessible(true);

        $candidates = $method->invoke($service, ['2026-07-04', '2026-07-03', '2026-07-02']);

        $this->assertContains('2026-07-03', $candidates);
    }

    private function createSourceMetadataTables(): void
    {
        foreach (['dashboard_harian_snapshots', 'ssa_pinjaman', 'ssa_simpanan', 'hourly_dpk', 'dly_kap_resegmentasi', 'l1133', 'cognos_recovery', 'lw325_ph'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->string('uniqueid_dhs')->primary();
            $table->date('snapshot_period')->nullable();
            $table->string('kanca_key')->default('');
            $table->string('kanca_label')->nullable();
            $table->string('unit_key')->default('');
            $table->string('unit_label')->nullable();
            $table->integer('source_row_count')->default(0);
            $table->string('source_signature', 64)->nullable();
            $table->unsignedBigInteger('source_loan_row_count')->nullable();
            $table->unsignedBigInteger('source_savings_row_count')->nullable();
            $table->unsignedBigInteger('source_recovery_row_count')->nullable();
            $table->date('source_recovery_period')->nullable();
            $table->timestamps();
        });

        Schema::create('ssa_pinjaman', function (Blueprint $table): void {
            $table->id();
            $table->date('month_day_year_of_periode')->nullable();
            $table->string('nama_cabang')->nullable();
            $table->string('nama_uker')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
            $table->string('produk')->nullable();
            $table->string('segmen_2025')->nullable();
            $table->string('kolektabilitas_one_obligor')->nullable();
            $table->decimal('baki_debet', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('ssa_simpanan', function (Blueprint $table): void {
            $table->id();
            $table->date('Month_Day_Year_of_Posisi')->nullable();
            $table->string('nama_cabang')->nullable();
            $table->string('nama_uker')->nullable();
            $table->string('segmentasi')->nullable();
            $table->string('produk')->nullable();
            $table->decimal('saldo', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('hourly_dpk', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('posisi')->nullable();
            $table->string('mbname')->nullable();
            $table->string('brname')->nullable();
            $table->string('segmen')->nullable();
            $table->string('produk')->nullable();
            $table->decimal('saldo', 20, 2)->nullable();
        });

        Schema::create('dly_kap_resegmentasi', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->decimal('tl_rp', 20, 2)->nullable();
            $table->decimal('dpk_rp', 20, 2)->nullable();
            $table->decimal('npl_rp', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('l1133', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->string('kode_kanca')->nullable();
            $table->string('nama_kanca')->nullable();
            $table->string('kode_uker')->nullable();
            $table->string('nama_uker')->nullable();
            $table->string('jenis')->nullable();
            $table->decimal('outstanding', 20, 2)->nullable();
            $table->decimal('dpk', 20, 2)->nullable();
            $table->decimal('npl', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('cognos_recovery', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->decimal('total_recovery', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->string('acctno')->nullable();
            $table->string('kanca')->nullable();
            $table->string('unit')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->decimal('pokok', 20, 2)->nullable();
            $table->timestamps();
        });
    }
}
