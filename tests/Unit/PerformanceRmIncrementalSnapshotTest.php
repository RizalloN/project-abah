<?php

namespace Tests\Unit;

use App\Support\ConsumerRmRealizationCalculator;
use App\Support\ConsumerRmPositionHistoryStore;
use App\Support\DashboardHarianSnapshotService;
use App\Support\ReportSnapshotBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceRmIncrementalSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::connection()->getPdo()->sqliteCreateFunction(
            'REGEXP_REPLACE',
            static fn ($value, $pattern, $replacement): string => preg_replace('/[^0-9]/', (string) $replacement, (string) $value) ?? '',
            3
        );

        Schema::dropAllTables();
        $this->createTables();
    }

    public function test_performance_rm_incremental_rebuild_matches_force_rebuild_and_removes_stale_rows(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('R1', 'BRIGUNAKONSUMER', 1000, 800, '123');
        DB::table('simpanan_multipn')->insert([
            'posisi' => '2026-05-06',
            'CIFNO' => '123',
            'saldo_idr' => 500,
        ]);

        $builder->rebuildPerformanceRm('2026-05-06', true);

        DB::table('performance_rm_snapshots')->insert($this->stalePerformanceRmRow());
        $this->insertDailyLoanRow('R2', 'KPR', 2000, 1500, '456');
        DB::table('daily_loan_dinamis')->where('nomor_rekening1', 'R1')->update(['baki_debet1' => 900]);

        $incrementalResult = $builder->rebuildPerformanceRm('2026-05-06', false);
        $incrementalRows = $this->snapshotRows('performance_rm_snapshots');
        $incrementalCabangRows = $this->snapshotRows('performance_rm_cabang_snapshots');

        DB::table('performance_rm_cabang_snapshots')->where('periode', '2026-05-06')->delete();
        DB::table('performance_rm_snapshots')->where('periode', '2026-05-06')->delete();

        $forceResult = $builder->rebuildPerformanceRm('2026-05-06', true);

        $this->assertSame($forceResult, $incrementalResult);
        $this->assertSame($this->snapshotRows('performance_rm_snapshots'), $incrementalRows);
        $this->assertSame($this->snapshotRows('performance_rm_cabang_snapshots'), $incrementalCabangRows);
        $this->assertDatabaseMissing('performance_rm_snapshots', [
            'periode' => '2026-05-06',
            'produk' => 'STALE',
        ]);
    }

    public function test_consumer_realisasi_uses_current_month_realization_minus_previous_closed_cif_os(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('OLD-CLOSED', 'BRIGUNAKONSUMER', 120000000, 90000000, '123', '2026-04-30');
        $this->insertDailyLoanRow('OLD-STILL-OPEN', 'KPR', 200000000, 150000000, '456', '2026-04-30');
        $this->insertDailyLoanRow('OLD-NO-NEW', 'KPR', 100000000, 80000000, '789', '2026-04-30');

        $this->insertDailyLoanRow('NEW-BRIGUNA', 'BRIGUNAKONSUMER', 400000000, 390000000, '123', '2026-05-15');
        $this->insertDailyLoanRow('OLD-STILL-OPEN', 'KPR', 200000000, 140000000, '456', '2026-05-15', tglRealisasi: '2026-01-10');
        $this->insertDailyLoanRow('NEW-KPR', 'KPR', 300000000, 295000000, '456', '2026-05-15');
        $this->insertDailyLoanRow('OLD-REAL-DATE', 'KPR', 500000000, 490000000, '999', '2026-05-15', tglRealisasi: '2026-03-01');

        $builder->rebuildPerformanceRm('2026-05-15', true);

        $briguna = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-15')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->first();

        $kpr = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-15')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'KPR')
            ->first();

        $this->assertNotNull($briguna);
        $this->assertSame(1, (int) $briguna->realisasi_deb);
        $this->assertSame(310000000.0, (float) $briguna->realisasi_os);
        $this->assertSame(0, (int) $briguna->w1_realisasi_deb);
        $this->assertSame(0.0, (float) $briguna->w1_realisasi_os);

        $this->assertNotNull($kpr);
        $this->assertSame(1, (int) $kpr->realisasi_deb);
        $this->assertSame(300000000.0, (float) $kpr->realisasi_os);
    }

    public function test_consumer_realisasi_applies_the_same_nett_formula_to_briguna_and_kpr(): void
    {
        $this->insertDailyLoanRow('OLD-BRIGUNA', 'BRIGUNAKONSUMER', 150000000, 80000000, 'CIF-BRIGUNA', '2026-04-30');
        $this->insertDailyLoanRow('OLD-KPR', 'KPR', 150000000, 80000000, 'CIF-KPR', '2026-04-30');
        $this->insertDailyLoanRow('NEW-BRIGUNA', 'BRIGUNAKONSUMER', 300000000, 295000000, 'CIF-BRIGUNA', '2026-05-31');
        $this->insertDailyLoanRow('NEW-KPR', 'KPR', 300000000, 295000000, 'CIF-KPR', '2026-05-31');

        $metrics = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-05-31'))
            ->keyBy('produk');

        foreach (['BRIGUNA-KONSUMER', 'KPR'] as $product) {
            $this->assertArrayHasKey($product, $metrics);
            $this->assertSame(0, (int) $metrics[$product]['realisasi_baru_deb']);
            $this->assertSame(0.0, (float) $metrics[$product]['realisasi_baru_os']);
            $this->assertSame(1, (int) $metrics[$product]['suplesi_deb']);
            $this->assertSame(220000000.0, (float) $metrics[$product]['suplesi_os']);
            $this->assertSame(220000000.0, (float) $metrics[$product]['realisasi_os']);
        }
    }

    public function test_consumer_realisasi_returns_empty_metrics_when_month_has_no_realizations(): void
    {
        $this->insertDailyLoanRow(
            'PREVIOUS-DUMMY',
            'BRIGUNAKONSUMER',
            100000000,
            80000000,
            'CIF-PREVIOUS',
            '2026-04-30'
        );

        $this->assertSame(
            [],
            app(ConsumerRmRealizationCalculator::class)->calculate('2026-05-31')
        );
    }

    public function test_consumer_realisasi_uses_full_plafon_when_cif_has_no_closed_previous_account(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('R1', 'BRIGUNAKONSUMER', 300000000, 250000000, '123', '2026-04-30');

        $this->insertDailyLoanRow('R3', 'KPR', 125000000, 100000000, '999', '2026-05-15');
        $this->insertDailyLoanRow('R4', 'BRIGUNAKONSUMER', 275000000, 225000000, '789', '2026-05-15');

        $builder->rebuildPerformanceRm('2026-05-15', true);

        $briguna = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-15')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->first();

        $kpr = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-15')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'KPR')
            ->first();

        $this->assertNotNull($briguna);
        $this->assertSame(1, (int) $briguna->realisasi_deb);
        $this->assertSame(275000000.0, (float) $briguna->realisasi_os);

        $this->assertNotNull($kpr);
        $this->assertSame(1, (int) $kpr->realisasi_deb);
        $this->assertSame(125000000.0, (float) $kpr->realisasi_os);
    }

    public function test_consumer_realisasi_uses_first_previous_cif_row_like_excel_xlookup(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('AAA-CLOSED-HIGH', 'BRIGUNAKONSUMER', 120000000, 90000000, '123', '2026-04-30', uniqueId: 'row-001');
        $this->insertDailyLoanRow('ZZZ-CLOSED-LOW', 'BRIGUNAKONSUMER', 100000000, 75000000, '123', '2026-04-30', uniqueId: 'row-002');

        $this->insertDailyLoanRow('NEW-BRIGUNA', 'BRIGUNAKONSUMER', 400000000, 390000000, '123', '2026-05-15');

        $builder->rebuildPerformanceRm('2026-05-15', true);

        $briguna = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-15')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->first();

        $this->assertNotNull($briguna);
        $this->assertSame(1, (int) $briguna->realisasi_deb);
        $this->assertSame(310000000.0, (float) $briguna->realisasi_os);
    }

    public function test_consumer_realisasi_uses_previous_month_end_before_supplement_account_appears(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('OLD-CLOSED', 'BRIGUNAKONSUMER', 120000000, 90000000, 'CIF-DAILY', '2026-04-30', tglRealisasi: '2025-01-10');
        $this->insertDailyLoanRow('OLD-CLOSED', 'BRIGUNAKONSUMER', 120000000, 70000000, 'CIF-DAILY', '2026-05-09', tglRealisasi: '2025-01-10');
        $this->insertDailyLoanRow('NEW-SUPPLEMENT', 'BRIGUNAKONSUMER', 300000000, 295000000, 'CIF-DAILY', '2026-05-10', tglRealisasi: '2026-05-10');
        $this->insertDailyLoanRow('NEW-SUPPLEMENT', 'BRIGUNAKONSUMER', 300000000, 290000000, 'CIF-DAILY', '2026-05-31', tglRealisasi: '2026-05-10');

        $builder->rebuildPerformanceRm('2026-05-31', true);

        $snapshot = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-31')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->where('rm', 'RM A')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(1, (int) $snapshot->realisasi_deb);
        $this->assertSame(210000000.0, (float) $snapshot->realisasi_os);
    }

    public function test_consumer_realisasi_ignores_another_products_residual_when_selecting_replaced_account(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('TINY-RESIDUAL', 'KPR', 10000000, 5000000, 'CIF-MULTI', '2026-04-30');
        $this->insertDailyLoanRow('MATERIAL-CLOSED', 'BRIGUNAKONSUMER', 100000000, 80000000, 'CIF-MULTI', '2026-04-30');
        $this->insertDailyLoanRow('NEW-SUPPLEMENT', 'BRIGUNAKONSUMER', 100000000, 99000000, 'CIF-MULTI', '2026-05-31');

        $builder->rebuildPerformanceRm('2026-05-31', true);

        $snapshot = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-31')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->where('rm', 'RM A')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(1, (int) $snapshot->realisasi_deb);
        $this->assertSame(20000000.0, (float) $snapshot->realisasi_os);
    }

    public function test_consumer_realisasi_does_not_turn_briguna_into_supplement_from_a_previous_kpr_cif(): void
    {
        $this->insertDailyLoanRow('OLD-KPR', 'KPR', 100000000, 80000000, 'CIF-SHARED', '2026-04-30');
        $this->insertDailyLoanRow('NEW-BRIGUNA', 'BRIGUNAKONSUMER', 150000000, 145000000, 'CIF-SHARED', '2026-05-31');

        $metrics = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-05-31'))
            ->firstWhere('produk', 'BRIGUNA-KONSUMER');

        $this->assertNotNull($metrics);
        $this->assertSame(1, (int) $metrics['realisasi_baru_deb']);
        $this->assertSame(150000000.0, (float) $metrics['realisasi_baru_os']);
        $this->assertSame(0, (int) $metrics['suplesi_deb']);
        $this->assertSame(0.0, (float) $metrics['suplesi_os']);
    }

    public function test_consumer_snapshot_updater_subtracts_previous_closed_os_once_for_multiple_accounts_in_one_cif(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('OLD-CLOSED', 'BRIGUNAKONSUMER', 120000000, 90000000, 'CIF-A1', '2026-04-30');
        $this->insertDailyLoanRow('NEW-ACCOUNT-1', 'BRIGUNAKONSUMER', 400000000, 390000000, 'CIF-A1', '2026-05-31');
        $this->insertDailyLoanRow('NEW-ACCOUNT-2', 'BRIGUNAKONSUMER', 300000000, 295000000, 'CIF-A1', '2026-05-31');

        DB::table('performance_rm_snapshots')->insert([
            'periode' => '2026-05-31',
            'cabang' => 'KC MADIUN',
            'unit' => 'UNIT A',
            'branch_code' => '123',
            'rm' => 'RM A',
            'segmen' => 'CONSUMER',
            'produk' => 'BRIGUNA-KONSUMER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $method = (new \ReflectionClass($builder))->getMethod('updateConsumerPerformanceRmSurplusMetrics');
        $method->setAccessible(true);
        $method->invoke(
            $builder,
            '2026-05-31',
            'performance_rm_snapshots',
            array_flip(Schema::getColumnListing('performance_rm_snapshots'))
        );

        $snapshot = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-31')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(2, (int) $snapshot->realisasi_deb);
        $this->assertSame(610000000.0, (float) $snapshot->realisasi_os);
    }

    public function test_consumer_realisasi_counts_all_realized_accounts_and_never_turns_negative(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('EXISTING-ACCOUNT', 'BRIGUNAKONSUMER', 20000000, 5000000, 'CIF-EXISTING', '2026-04-30');
        $this->insertDailyLoanRow('EXISTING-ACCOUNT', 'BRIGUNAKONSUMER', 25000000, 24000000, 'CIF-EXISTING', '2026-05-31');
        $this->insertDailyLoanRow('EXISTING-ACCOUNT', 'BRIGUNAKONSUMER', 25000000, 24000000, 'CIF-EXISTING', '2026-05-31', uniqueId: 'duplicate-current-row');

        $this->insertDailyLoanRow('CLOSED-HIGH-OS', 'BRIGUNAKONSUMER', 120000000, 100000000, 'CIF-NEGATIVE', '2026-04-30');
        $this->insertDailyLoanRow('NEW-LOW-PLAFOND', 'BRIGUNAKONSUMER', 50000000, 49000000, 'CIF-NEGATIVE', '2026-05-31');

        $builder->rebuildPerformanceRm('2026-05-31', true);

        $snapshot = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-31')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(2, (int) $snapshot->realisasi_deb);
        $this->assertSame(20000000.0, (float) $snapshot->realisasi_os);
    }

    public function test_consumer_realisasi_normalizes_leading_zero_account_after_lw321_source_switch(): void
    {
        $this->insertDailyLoanRow(
            '4501075942104',
            'BRIGUNAKONSUMER',
            100000000,
            80000000,
            'CIF-LW-SWITCH',
            '2026-08-31',
            tglRealisasi: '2025-01-10'
        );
        $this->insertDailyLoanRow(
            '4501075942104',
            'BRIGUNAKONSUMER',
            120000000,
            120000000,
            'CIF-LW-SWITCH',
            '2026-09-01',
            tglRealisasi: '2026-09-01'
        );
        $this->insertDailyLoanRow(
            '004501075942104',
            'BRIGUNAKONSUMER',
            120000000,
            120000000,
            'CIF-LW-SWITCH',
            '2026-09-07',
            tglRealisasi: '2026-09-01'
        );

        $metric = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-09-07'))
            ->firstWhere('produk', 'BRIGUNA-KONSUMER');

        $this->assertNotNull($metric);
        $this->assertSame(1, $metric['realisasi_deb']);
        $this->assertSame(1, $metric['suplesi_deb']);
        $this->assertSame(40000000.0, $metric['realisasi_os']);
        $this->assertSame(40000000.0, $metric['suplesi_os']);
    }

    public function test_consumer_realisasi_freezes_first_monthly_assignment_for_a_repeated_account(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('PREVIOUS-DUMMY', 'BRIGUNAKONSUMER', 100000000, 90000000, 'CIF-OLD', '2026-04-30');
        $this->insertDailyLoanRow('MOVED-ACCOUNT', 'BRIGUNAKONSUMER', 250000000, 250000000, 'CIF-NEW', '2026-05-10', tglRealisasi: '2026-05-10');
        $this->insertDailyLoanRow('MOVED-ACCOUNT', 'BRIGUNAKONSUMER', 250000000, 250000000, 'CIF-NEW', '2026-05-31', tglRealisasi: '2026-05-10');
        DB::table('daily_loan_dinamis')
            ->where('nomor_rekening1', 'MOVED-ACCOUNT')
            ->where('periode', '2026-05-10')
            ->update(['rm_normalized' => 'RM LAMA', 'pn_pengelola1' => 'RM LAMA']);
        DB::table('daily_loan_dinamis')
            ->where('nomor_rekening1', 'MOVED-ACCOUNT')
            ->where('periode', '2026-05-31')
            ->update(['rm_normalized' => 'RM TERKINI', 'pn_pengelola1' => 'RM TERKINI']);

        $builder->rebuildPerformanceRm('2026-05-31', true);

        $originator = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-31')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->where('rm', 'RM LAMA')
            ->first();

        $this->assertNotNull($originator);
        $this->assertSame(1, (int) $originator->realisasi_deb);
        $this->assertSame(250000000.0, (float) $originator->realisasi_os);
    }

    public function test_consumer_realisasi_keeps_accounts_seen_earlier_in_selected_month(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('OLD-CLOSED', 'BRIGUNAKONSUMER', 120000000, 90000000, 'CIF-SUPP', '2026-04-30');
        $this->insertDailyLoanRow('EARLY-REALIZATION', 'BRIGUNAKONSUMER', 300000000, 295000000, 'CIF-SUPP', '2026-05-10');
        $this->insertDailyLoanRow('MONTH-END-REALIZATION', 'BRIGUNAKONSUMER', 100000000, 99000000, 'CIF-NEW', '2026-05-31');

        $builder->rebuildPerformanceRm('2026-05-31', true);

        $snapshot = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-31')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->where('rm', 'RM A')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(2, (int) $snapshot->realisasi_deb);
        $this->assertSame(310000000.0, (float) $snapshot->realisasi_os);
    }

    public function test_consumer_realisasi_remains_identical_after_raw_daily_positions_are_pruned(): void
    {
        $this->insertDailyLoanRow('PREVIOUS-DUMMY', 'BRIGUNAKONSUMER', 100000000, 90000000, 'CIF-OLD', '2026-04-30');
        $this->insertDailyLoanRow('EARLY-REALIZATION', 'BRIGUNAKONSUMER', 300000000, 295000000, 'CIF-EARLY', '2026-05-10');
        $this->insertDailyLoanRow('MONTH-END-REALIZATION', 'BRIGUNAKONSUMER', 100000000, 99000000, 'CIF-END', '2026-05-31');

        $beforePrune = app(ConsumerRmRealizationCalculator::class)->calculate('2026-05-31');

        $migration = require database_path('migrations/2026_09_09_010000_create_consumer_rm_position_history_tables.php');
        $migration->up();
        $archive = app(ConsumerRmPositionHistoryStore::class);
        foreach (['2026-04-30', '2026-05-10', '2026-05-31'] as $sourcePeriod) {
            $capture = $archive->capturePeriod($sourcePeriod, true);
            $this->assertTrue($capture['verified']);
        }

        DB::table('daily_loan_dinamis')->where('periode', '2026-05-10')->delete();

        $afterPrune = app(ConsumerRmRealizationCalculator::class)->calculate('2026-05-31');

        $this->assertSame($beforePrune, $afterPrune);
        $this->assertSame(2, array_sum(array_column($afterPrune, 'realisasi_deb')));
        $this->assertSame(400000000.0, array_sum(array_column($afterPrune, 'realisasi_os')));
    }

    public function test_consumer_realisasi_prefers_active_brihc_initiator_and_preserves_realization_only_row(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        DB::table('brihc_pemasar')->insert([
            'pernr' => '00000002',
            'completename' => 'RM ORIGINATOR',
            'positiondesc' => 'RM BISNIS KONSUMER - BRIGUNA',
        ]);
        $this->insertDailyLoanRow('PREVIOUS-DUMMY', 'BRIGUNAKONSUMER', 100000000, 90000000, 'CIF-OLD', '2026-04-30');
        $this->insertDailyLoanRow('NEW-BY-ORIGINATOR', 'BRIGUNAKONSUMER', 250000000, 245000000, 'CIF-NEW', '2026-05-31');
        DB::table('daily_loan_dinamis')
            ->where('nomor_rekening1', 'NEW-BY-ORIGINATOR')
            ->update(['pn_pemrakarsa1' => '00000002 - RM ORIGINATOR']);

        $builder->rebuildPerformanceRm('2026-05-31', true);

        $originator = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-31')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->where('rm', '00000002 - RM ORIGINATOR')
            ->first();

        $this->assertNotNull($originator);
        $this->assertSame(0, (int) $originator->total_deb);
        $this->assertSame(1, (int) $originator->realisasi_deb);
        $this->assertSame(250000000.0, (float) $originator->realisasi_os);
    }

    public function test_consumer_realisasi_merges_brihc_and_daily_loan_name_casing_before_snapshot_update(): void
    {
        DB::table('brihc_pemasar')->insert([
            'pernr' => '00000002',
            'completename' => 'RM Referensi',
            'positiondesc' => 'RM BISNIS KONSUMER - BRIGUNA',
        ]);
        $this->insertDailyLoanRow('PREVIOUS-DUMMY', 'BRIGUNAKONSUMER', 100000000, 90000000, 'CIF-OLD', '2026-04-30');
        $this->insertDailyLoanRow('NEW-MANAGER', 'BRIGUNAKONSUMER', 100000000, 100000000, 'CIF-NEW-1', '2026-05-31');
        $this->insertDailyLoanRow('NEW-INITIATOR', 'BRIGUNAKONSUMER', 200000000, 200000000, 'CIF-NEW-2', '2026-05-31');
        DB::table('daily_loan_dinamis')
            ->whereIn('nomor_rekening1', ['NEW-MANAGER', 'NEW-INITIATOR'])
            ->update(['rm_normalized' => '00000002 - RM REFERENSI', 'pn_pengelola1' => '00000002 - RM REFERENSI']);
        DB::table('daily_loan_dinamis')
            ->where('nomor_rekening1', 'NEW-INITIATOR')
            ->update(['pn_pemrakarsa1' => '00000002 - RM Referensi']);

        $metrics = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-05-31'))
            ->filter(fn (array $row): bool => str_contains(strtoupper((string) $row['rm']), 'RM REFERENSI'))
            ->values();

        $this->assertCount(1, $metrics);
        $this->assertSame('00000002 - RM Referensi', $metrics->first()['rm']);
        $this->assertSame(2, (int) $metrics->first()['realisasi_deb']);
        $this->assertSame(300000000.0, (float) $metrics->first()['realisasi_os']);
    }

    public function test_consumer_snapshot_updater_clears_realisasi_when_previous_month_end_is_missing(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        DB::table('performance_rm_snapshots')->insert([
            'periode' => '2025-06-30',
            'cabang' => 'KC MADIUN',
            'unit' => 'UNIT A',
            'branch_code' => '123',
            'rm' => 'RM A',
            'segmen' => 'CONSUMER',
            'produk' => 'BRIGUNA-KONSUMER',
            'realisasi_deb' => 9,
            'realisasi_os' => 999000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $method = (new \ReflectionClass($builder))->getMethod('updateConsumerPerformanceRmSurplusMetrics');
        $method->setAccessible(true);
        $method->invoke(
            $builder,
            '2025-06-30',
            'performance_rm_snapshots',
            array_flip(Schema::getColumnListing('performance_rm_snapshots'))
        );

        $snapshot = DB::table('performance_rm_snapshots')
            ->where('periode', '2025-06-30')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(0, (int) $snapshot->realisasi_deb);
        $this->assertSame(0.0, (float) $snapshot->realisasi_os);
    }

    public function test_consumer_realisasi_requires_previous_month_end_period(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('OLD-1', 'BRIGUNAKONSUMER', 100000000, 80000000, '123', '2026-04-29');
        $this->insertDailyLoanRow('NEW-1', 'BRIGUNAKONSUMER', 400000000, 400000000, '123', '2026-05-15');

        $builder->rebuildPerformanceRm('2026-05-15', true);

        $briguna = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-15')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->first();

        $this->assertNotNull($briguna);
        $this->assertSame(0, (int) $briguna->realisasi_deb);
        $this->assertSame(0.0, (float) $briguna->realisasi_os);
    }

    public function test_performance_rm_quality_uses_kolek_instead_of_kol_adk1(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow(
            'SMALL-1',
            'COMMERCIAL',
            100000000,
            90000000,
            'S001',
            '2026-05-06',
            'SMALL',
            1,
            4,
            'Y'
        );

        $builder->rebuildPerformanceRm('2026-05-06', true);

        $snapshot = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-06')
            ->where('segmen', 'SMALL')
            ->where('produk', 'SMALL')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(0.0, (float) $snapshot->lancar_os);
        $this->assertSame(0.0, (float) $snapshot->restruk_os);
        $this->assertSame(0.0, (float) $snapshot->sml_os);
        $this->assertSame(90000000.0, (float) $snapshot->npl_os);
        $this->assertSame(0, (int) $snapshot->lancar_deb);
        $this->assertSame(1, (int) $snapshot->npl_deb);
    }

    public function test_small_realisasi_uses_stored_realisasi_month_without_swapping_ambiguous_dates(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow(
            'SMALL-CURRENT',
            'COMMERCIAL',
            1000000000,
            900000000,
            'S001',
            '2026-01-31',
            'SMALL',
            1,
            1,
            '',
            '2026-01-12'
        );
        $this->insertDailyLoanRow(
            'SMALL-SWAPPED',
            'COMMERCIAL',
            500000000,
            450000000,
            'S002',
            '2026-01-31',
            'SMALL',
            1,
            1,
            '',
            '2026-12-01'
        );
        $this->insertDailyLoanRow(
            'SMALL-OLD',
            'COMMERCIAL',
            500000000,
            450000000,
            'S003',
            '2026-01-31',
            'SMALL',
            1,
            1,
            '',
            '2025-12-01'
        );

        $builder->rebuildPerformanceRm('2026-01-31', true);

        $snapshot = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-01-31')
            ->where('segmen', 'SMALL')
            ->where('produk', 'SMALL')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(1, (int) $snapshot->realisasi_deb);
        $this->assertSame(1000000000.0, (float) $snapshot->realisasi_os);
        $this->assertSame(0, (int) $snapshot->w1_realisasi_deb);
        $this->assertSame(1, (int) $snapshot->w2_realisasi_deb);
        $this->assertSame(1000000000.0, (float) $snapshot->w2_realisasi_os);
    }

    private function createTables(): void
    {
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->string('uniqueid_namareport')->nullable();
            $table->date('periode');
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->string('description')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('unit_normalized')->nullable();
            $table->string('branch_normalized')->nullable();
            $table->string('rm_normalized')->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->integer('kol_adk1')->nullable();
            $table->integer('kolek')->nullable();
            $table->string('flag_restruk')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->string('pn_pengelola1')->nullable();
            $table->string('pn_pemrakarsa1')->nullable();
            $table->string('cifno')->nullable();
            $table->string('cifno_clean')->nullable();
            $table->date('tgl_realisasi')->nullable();
        });

        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            $table->id();
            $table->string('pernr')->nullable();
            $table->string('completename')->nullable();
            $table->string('positiondesc')->nullable();
        });

        Schema::create('simpanan_multipn', function (Blueprint $table): void {
            $table->id();
            $table->date('posisi')->nullable();
            $table->string('CIFNO')->nullable();
            $table->decimal('saldo_idr', 20, 2)->nullable();
        });

        Schema::create('performance_rm_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('periode');
            $table->string('cabang', 100);
            $table->string('unit', 100);
            $table->string('branch_code', 100)->nullable();
            $table->string('rm', 255);
            $table->string('segmen', 50);
            $table->string('produk', 100);
            $table->decimal('plafon', 20, 2)->default(0);
            $table->decimal('loan_os', 20, 2)->default(0);
            $table->decimal('lancar_os', 20, 2)->default(0);
            $table->integer('lancar_deb')->default(0);
            $table->decimal('sml_os', 20, 2)->default(0);
            $table->integer('sml_deb')->default(0);
            $table->decimal('npl_os', 20, 2)->default(0);
            $table->integer('npl_deb')->default(0);
            $table->decimal('restruk_os', 20, 2)->default(0);
            $table->integer('total_deb')->default(0);
            $table->integer('realisasi_deb')->default(0);
            $table->decimal('realisasi_os', 20, 2)->default(0);
            foreach (['w1', 'w2', 'w3', 'w4'] as $week) {
                $table->integer($week.'_realisasi_deb')->default(0);
                $table->decimal($week.'_realisasi_os', 20, 2)->default(0);
            }
            $table->integer('lt_250_realisasi_deb')->default(0);
            $table->decimal('lt_250_realisasi_os', 20, 2)->default(0);
            $table->integer('gt_250_realisasi_deb')->default(0);
            $table->decimal('gt_250_realisasi_os', 20, 2)->default(0);
            $table->decimal('total_deposit', 20, 2)->default(0);
            $table->tinyInteger('quadrant')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_rm_cabang_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('periode');
            $table->string('cabang', 100);
            $table->string('segmen', 50);
            $table->string('produk', 100);
            $table->decimal('loan_os', 20, 2)->default(0);
            $table->decimal('lancar_os', 20, 2)->default(0);
            $table->decimal('sml_os', 20, 2)->default(0);
            $table->decimal('npl_os', 20, 2)->default(0);
            $table->integer('total_deb')->default(0);
            $table->integer('lancar_deb')->default(0);
            $table->integer('sml_deb')->default(0);
            $table->integer('npl_deb')->default(0);
            $table->decimal('restruk_os', 20, 2)->default(0);
            $table->integer('realisasi_deb')->default(0);
            $table->decimal('realisasi_os', 20, 2)->default(0);
            $table->decimal('total_deposit', 20, 2)->default(0);
            $table->decimal('plafon', 20, 2)->default(0);
            $table->timestamps();
        });
    }

    private function insertDailyLoanRow(
        string $account,
        string $product,
        int $plafon,
        int $bakiDebet,
        string $cif,
        string $period = '2026-05-06',
        string $segment = 'CONSUMER',
        int $kolAdk = 1,
        int $kolek = 1,
        string $flagRestruk = '',
        ?string $tglRealisasi = null,
        ?string $uniqueId = null
    ): void {
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => $uniqueId ?? 'row-'.$account.'-'.$period,
            'periode' => $period,
            'segmen_kinerja' => $segment,
            'produk_kinerja' => $product,
            'description' => '',
            'cabang_normalized' => 'KC MADIUN',
            'unit_normalized' => 'UNIT A',
            'branch_normalized' => '123',
            'rm_normalized' => 'RM A',
            'plafon' => $plafon,
            'baki_debet1' => $bakiDebet,
            'kol_adk1' => $kolAdk,
            'kolek' => $kolek,
            'flag_restruk' => $flagRestruk,
            'nomor_rekening1' => $account,
            'pn_pengelola1' => 'RM A',
            'cifno' => $cif,
            'cifno_clean' => $cif,
            'tgl_realisasi' => $tglRealisasi ?? $period,
        ]);
    }

    private function stalePerformanceRmRow(): array
    {
        return [
            'periode' => '2026-05-06',
            'cabang' => 'KC MADIUN',
            'unit' => 'UNIT A',
            'branch_code' => '123',
            'rm' => 'RM A',
            'segmen' => 'CONSUMER',
            'produk' => 'STALE',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function snapshotRows(string $table): array
    {
        $query = DB::table($table)->where('periode', '2026-05-06');
        foreach (['cabang', 'unit', 'rm', 'segmen', 'produk'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                $query->orderBy($column);
            }
        }

        return $query->get()
            ->map(function ($row): array {
                $data = (array) $row;
                unset($data['id'], $data['created_at'], $data['updated_at']);

                return array_map(static fn ($value) => is_numeric($value) ? (string) $value : $value, $data);
            })
            ->all();
    }
}
