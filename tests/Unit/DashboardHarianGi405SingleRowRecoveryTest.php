<?php

namespace Tests\Unit;

use App\Support\DashboardHarianSnapshotService;
use App\Support\Gi405SingleRowRecoverySource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardHarianGi405SingleRowRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('referensi_uker', function (Blueprint $table): void {
            $table->string('kode_uker')->primary();
            $table->string('nama_uker');
            $table->string('nama_cabang');
        });
        DB::table('referensi_uker')->insert([
            ['kode_uker' => '00045', 'nama_uker' => '00045 -- KC Madiun', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)'],
            ['kode_uker' => '06343', 'nama_uker' => '06343 -- UNIT MUNENG MADIUN', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)'],
            ['kode_uker' => '00049', 'nama_uker' => '00049 -- KC Magetan', 'nama_cabang' => '00049 -- KC Magetan (Konsolidasi-MB)'],
        ]);
        Schema::create('gi405_singlerow', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode');
            foreach (['branch', 'nama_cabang', 'nama_uker', 'account_number', 'currency', 'posting_control'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('ending_balance', 24, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('gi405_recovery', function (Blueprint $table): void {
            $table->id();
            $table->date('periode');
            $table->string('kode_uker');
            $table->string('nama_uker')->nullable();
            $table->decimal('pendapatan_koreksi_ppap_dr_angsuran_ph', 24, 2);
            $table->timestamps();
        });
    }

    public function test_single_row_has_date_priority_and_preserves_signed_corrections_and_unit_scope(): void
    {
        $this->ledger('2026-10-17', '45', -100.12);
        $this->ledger('2026-10-17', '00045', 25.01);
        $this->ledger('2026-10-17', '06343', -50.25);
        $this->ledger('2026-10-17', '49', -20.45);
        $this->official('2026-10-17', '00045', -999999);
        $this->official('2026-10-17', '06343', -999999);
        $service = new DashboardHarianSnapshotService;
        $rows = $this->invoke($service, 'fetchRecoveryAggregates', '2026-10-17', ['KC Madiun'], null);

        $this->assertCount(2, $rows);
        $this->assertEqualsWithDelta(75.11, $rows->sum('rec_dh_small'), 0.00001);
        $this->assertEqualsWithDelta(50.25, $rows->sum('rec_dh_micro'), 0.00001);
        $this->assertEqualsWithDelta(125.36, $rows->sum('rec_dh_total'), 0.00001);
        $unit = $this->invoke($service, 'fetchRecoveryAggregates', '2026-10-17', ['KC Madiun'], ['UNIT MUNENG MADIUN']);
        $this->assertCount(1, $unit);
        $this->assertEqualsWithDelta(50.25, $unit->sum('rec_dh_total'), 0.00001);
        $this->assertEqualsWithDelta(0, $rows->sum('rec_dh_consumer'), 0.00001);
    }

    public function test_zero_ledger_is_authoritative_and_other_currencies_totals_accounts_and_dates_do_not_pollute_recovery(): void
    {
        $this->official('2026-10-17', '00045', -999);
        $this->ledger('2026-10-17', '00045', 0);
        $this->ledger('2026-10-17', '00045', -1000, ['currency' => 'USD']);
        $this->ledger('2026-10-17', '00045', -2000, ['posting_control' => '*TOTAL']);
        $this->ledger('2026-10-17', '00045', -3000, ['account_number' => '418020000099']);
        $this->ledger('2026-10-17', '00045', -4000, ['account_number' => '418020000013']);
        $this->ledger('2026-10-16', '00045', -5000);

        $rows = $this->invoke(new DashboardHarianSnapshotService, 'fetchRecoveryAggregates', '2026-10-17', null, null);

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(0, $rows->sum('rec_dh_total'), 0.00001);
        $this->assertTrue(app(Gi405SingleRowRecoverySource::class)->availableForPeriod('2026-10-17'));
    }

    public function test_official_report_remains_fallback_if_same_date_has_no_eligible_ledger(): void
    {
        $this->ledger('2026-10-16', '00045', -5000);
        $this->ledger('2026-10-17', '00045', -1000, ['currency' => 'USD']);
        $this->ledger('2026-10-17', '00045', null);
        $this->official('2026-10-17', '00045', -123.45);

        $service = new DashboardHarianSnapshotService;
        $rows = $this->invoke($service, 'fetchRecoveryAggregates', '2026-10-17', null, null);
        $metadata = $this->invoke($service, 'sourceRecoveryState', '2026-10-17');

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(123.45, $rows->sum('rec_dh_total'), 0.00001);
        $this->assertSame('gi405_recovery', $metadata[0]);
        $this->assertFalse(app(Gi405SingleRowRecoverySource::class)->availableForPeriod('2026-10-17'));
    }

    public function test_priority_is_per_report_date_and_does_not_mix_other_units_from_the_processed_report(): void
    {
        $this->ledger('2026-10-17', '00045', -100);
        $this->official('2026-10-17', '00049', -999);
        $service = new DashboardHarianSnapshotService;

        $rows = $this->invoke($service, 'fetchRecoveryAggregates', '2026-10-17', null, null);
        $missingBranch = $this->invoke($service, 'fetchRecoveryAggregates', '2026-10-17', ['KC Magetan'], null);
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(100, $rows->sum('rec_dh_total'), 0.00001);
        $this->assertCount(0, $missingBranch);

        DB::table('gi405_singlerow')->where('periode', '2026-10-17')->delete();
        $fallback = $this->invoke($service, 'fetchRecoveryAggregates', '2026-10-17', ['KC Magetan'], null);
        $this->assertEqualsWithDelta(999, $fallback->sum('rec_dh_total'), 0.00001);
    }

    public function test_month_end_uses_single_row_without_requiring_processed_report_and_old_year_claim_rule_is_preserved(): void
    {
        Schema::drop('gi405_recovery');
        $this->ledger('2025-12-31', '00045', -100.12);
        $this->ledger('2025-12-31', '00045', -25.13, ['account_number' => '418020000013']);
        $this->ledger('2026-09-30', '00045', -100.12);
        $this->ledger('2026-09-30', '00045', -99999, ['account_number' => '418020000013']);

        $service = new DashboardHarianSnapshotService;
        $old = $this->invoke($service, 'fetchRecoveryAggregates', '2025-12-31', null, null);
        $current = $this->invoke($service, 'fetchRecoveryAggregates', '2026-09-30', null, null);
        $metadata = $this->invoke($service, 'sourceRecoveryState', '2026-09-30');

        $this->assertEqualsWithDelta(125.25, $old->sum('rec_dh_total'), 0.00001);
        $this->assertEqualsWithDelta(100.12, $current->sum('rec_dh_total'), 0.00001);
        $this->assertSame(['gi405_singlerow', '2026-09-30'], array_slice($metadata, 0, 2));
        $this->assertSame(1, $metadata[2]['row_count']);
    }

    public function test_reimport_changes_signature_and_later_official_report_cannot_replace_selected_ledger(): void
    {
        $this->ledger('2026-10-17', '00045', -100);
        $service = new DashboardHarianSnapshotService;
        $before = $this->invoke($service, 'sourceRecoveryState', '2026-10-17');
        $this->official('2026-10-17', '00045', -999);
        $afterOfficial = $this->invoke($service, 'sourceRecoveryState', '2026-10-17');
        $this->assertSame($before, $afterOfficial);
        DB::table('gi405_singlerow')->update(['ending_balance' => -200]);
        $afterReimport = $this->invoke($service, 'sourceRecoveryState', '2026-10-17');
        $this->assertNotSame($before, $afterReimport);
        $rows = $this->invoke($service, 'fetchRecoveryAggregates', '2026-10-17', null, null);
        $this->assertEqualsWithDelta(200, $rows->sum('rec_dh_total'), 0.00001);
    }

    public function test_recovery_timeseries_and_keragaan_apply_same_date_priority_including_month_end(): void
    {
        $this->official('2026-09-29', '00045', -1000000);
        $this->official('2026-09-30', '00045', -999000000);
        $this->ledger('2026-09-30', '00045', -1500000);
        $this->ledger('2026-10-01', '00045', -2000000);
        $this->ledger('2026-10-01', '06343', -500000);
        $this->ledger('2026-10-02', '06343', -700000);
        $this->ledger('2026-10-02', '00045', -2500000);
        $service = new DashboardHarianSnapshotService;

        $trend = $service->fetchTimeseriesTrend(['2026-09', '2026-10'], 'recovery', ['KC Madiun'], null, 'ritel');
        $this->assertEqualsWithDelta(0.5, $trend['series']['KC Madiun']['2026-09'][29], 0.000001);
        $this->assertEqualsWithDelta(0.5, $trend['series']['KC Madiun']['2026-10'][0], 0.000001);
        $this->assertEqualsWithDelta(0.5, $trend['series']['KC Madiun']['2026-10'][1], 0.000001);
        $rows = $this->invoke($service, 'fetchKeragaanUkerRecoveryRows', ['2026-09-29', '2026-09-30'], ['KC Madiun'], null);
        $this->assertCount(2, $rows);
        $this->assertEqualsWithDelta(1500000, $rows->firstWhere('period', '2026-09-30')->recovery, 0.001);
    }

    public function test_snapshot_rebuild_replaces_stale_recovery_while_savings_and_loan_values_stay_the_same(): void
    {
        $this->createSnapshotSources();
        $this->official('2026-10-17', '00045', -100);
        $service = new DashboardHarianSnapshotService;
        $service->rebuild('2026-10-17');
        $before = DB::table('dashboard_harian_snapshots')->whereColumn('kanca_key', 'unit_key')->first();
        $this->assertEqualsWithDelta(100, $before->rec_dh_total, 0.00001);
        $this->ledger('2026-10-17', '00045', -250.12);
        $service = new DashboardHarianSnapshotService;
        $result = $service->syncDuePeriods(['2026-10-17']);
        $after = DB::table('dashboard_harian_snapshots')->whereColumn('kanca_key', 'unit_key')->first();

        $this->assertSame(1, $result['built']);
        $this->assertEqualsWithDelta(250.12, $after->rec_dh_total, 0.00001);
        $this->assertNotSame($before->source_signature, $after->source_signature);
        $this->assertSame('2026-10-17', $after->source_recovery_period);
        $this->assertSame(1, $after->source_recovery_row_count);
        $this->assertEquals($before->total_simpanan, $after->total_simpanan);
        $this->assertEquals($before->total_os, $after->total_os);
        $metadata = $this->invoke($service, 'buildSourceMetadata', '2026-10-17');
        $this->assertTrue($this->invoke($service, 'snapshotSourceIsFresh', '2026-10-17', $metadata));
    }

    private function ledger(string $period, string $branch, ?float $balance, array $extra = []): void
    {
        DB::table('gi405_singlerow')->insert(array_merge([
            'uniqueid_namareport' => uniqid(), 'periode' => $period, 'branch' => $branch,
            'nama_cabang' => 'KC Madiun', 'nama_uker' => 'Ignored source label',
            'account_number' => '418020000010', 'currency' => ' IDR ', 'posting_control' => ' *POST ',
            'ending_balance' => $balance, 'created_at' => now(), 'updated_at' => now(),
        ], $extra));
    }

    private function official(string $period, string $branch, float $balance): void
    {
        DB::table('gi405_recovery')->insert([
            'periode' => $period, 'kode_uker' => $branch, 'pendapatan_koreksi_ppap_dr_angsuran_ph' => $balance,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function invoke(object $service, string $method, ...$args): mixed
    {
        return (new \ReflectionMethod($service, $method))->invoke($service, ...$args);
    }

    private function createSnapshotSources(): void
    {
        Schema::create('ssa_simpanan', function (Blueprint $table): void {
            $table->id();
            $table->date('Month_Day_Year_of_Posisi');
            foreach (['nama_cabang', 'nama_uker', 'segmentasi', 'produk'] as $col) {
                $table->string($col);
            }
            $table->decimal('saldo', 24, 2);
            $table->timestamps();
        });
        Schema::create('ssa_pinjaman', function (Blueprint $table): void {
            $table->id();
            $table->date('month_day_year_of_periode');
            foreach (['nama_cabang', 'nama_uker', 'segmen_dashboard', 'produk_dashboard', 'produk', 'segmen_2025', 'kolektabilitas_one_obligor'] as $col) {
                $table->string($col);
            }
            $table->decimal('baki_debet', 24, 2);
            $table->timestamps();
        });
        Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->string('uniqueid_dhs')->primary();
            $table->date('snapshot_period');
            foreach (['kanca_key', 'unit_key', 'kanca_label', 'unit_label', 'source_signature', 'source_recovery_period'] as $col) {
                $table->string($col)->nullable();
            }
            foreach (['source_row_count', 'source_savings_row_count', 'source_loan_row_count', 'source_recovery_row_count'] as $col) {
                $table->integer($col)->nullable();
            }
            foreach ((new \ReflectionClass(DashboardHarianSnapshotService::class))->getConstant('METRIC_COLUMNS') as $col) {
                $table->decimal($col, 24, 4)->default(0);
            }
            $table->timestamps();
        });
        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-10-17', 'nama_cabang' => 'KC Madiun', 'nama_uker' => 'KC Madiun',
            'segmentasi' => 'Ritel', 'produk' => 'Giro', 'saldo' => 500, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-10-17', 'nama_cabang' => 'KC Madiun', 'nama_uker' => 'KC Madiun',
            'segmen_dashboard' => 'Consumer', 'produk_dashboard' => 'KPR', 'produk' => 'KPR', 'segmen_2025' => 'Consumer',
            'kolektabilitas_one_obligor' => '1', 'baki_debet' => 1000, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
