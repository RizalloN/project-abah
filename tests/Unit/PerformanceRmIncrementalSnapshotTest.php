<?php

namespace Tests\Unit;

use App\Support\ConsumerRmPositionHistoryStore;
use App\Support\ConsumerRmRealizationCalculator;
use App\Support\DashboardHarianSnapshotService;
use App\Support\LandingConsumerOperationalService;
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
        $this->assertSame(300000000.0, (float) $briguna->realisasi_os);
        $this->assertSame(0, (int) $briguna->w1_realisasi_deb);
        $this->assertSame(0.0, (float) $briguna->w1_realisasi_os);

        $this->assertNotNull($kpr);
        $this->assertSame(1, (int) $kpr->realisasi_deb);
        $this->assertSame(300000000.0, (float) $kpr->realisasi_os);
    }

    public function test_kpr_uses_booked_plafond_while_briguna_retains_net_disbursement(): void
    {
        $this->insertDailyLoanRow('OLD-BRIGUNA', 'BRIGUNAKONSUMER', 150000000, 80000000, 'CIF-BRIGUNA', '2026-04-30');
        $this->insertDailyLoanRow('OLD-KPR', 'KPR', 150000000, 80000000, 'CIF-KPR', '2026-04-30');
        $this->insertDailyLoanRow('NEW-BRIGUNA', 'BRIGUNAKONSUMER', 300000000, 295000000, 'CIF-BRIGUNA', '2026-05-31');
        $this->insertDailyLoanRow('NEW-KPR', 'KPR', 300000000, 295000000, 'CIF-KPR', '2026-05-31');

        $metrics = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-05-31'))
            ->keyBy('produk');

        foreach (['BRIGUNA-KONSUMER' => 215000000.0, 'KPR' => 300000000.0] as $product => $expected) {
            $this->assertArrayHasKey($product, $metrics);
            $this->assertSame(0, (int) $metrics[$product]['realisasi_baru_deb']);
            $this->assertSame(0.0, (float) $metrics[$product]['realisasi_baru_os']);
            $this->assertSame(1, (int) $metrics[$product]['suplesi_deb']);
            $this->assertSame($expected, (float) $metrics[$product]['suplesi_os']);
            $this->assertSame($expected, (float) $metrics[$product]['realisasi_os']);
        }
    }

    public function test_kpr_transfer_is_resolved_per_account_through_cutoff_without_moving_briguna(): void
    {
        $this->insertDailyLoanRow('BASE', 'KPR', 100, 80, 'BASE', '2026-04-30');
        foreach (['A' => 300000000, 'B' => 200000000] as $account => $amount) {
            $this->insertDailyLoanRow($account, 'KPR', $amount, $amount, 'CIF-'.$account, '2026-05-10');
            $this->insertDailyLoanRow($account, 'KPR', $amount, $amount, 'CIF-'.$account, '2026-05-31', tglRealisasi: '2026-05-10');
            $this->insertDailyLoanRow($account, 'KPR', $amount, $amount, 'CIF-'.$account, '2026-06-30', tglRealisasi: '2026-05-10');
        }
        DB::table('daily_loan_dinamis')->where('periode', '2026-05-31')->where('nomor_rekening1', 'A')->update(['rm_normalized' => 'RM B']);
        DB::table('daily_loan_dinamis')->where('periode', '2026-06-30')->where('nomor_rekening1', 'A')->update(['rm_normalized' => 'RM C']);
        $this->insertDailyLoanRow('BRIGUNA', 'BRIGUNAKONSUMER', 150000000, 150000000, 'BRIGUNA', '2026-05-10');
        $this->insertDailyLoanRow('BRIGUNA', 'BRIGUNAKONSUMER', 150000000, 150000000, 'BRIGUNA', '2026-06-30', tglRealisasi: '2026-05-10');
        DB::table('daily_loan_dinamis')->where('periode', '2026-06-30')->where('nomor_rekening1', 'BRIGUNA')->update(['rm_normalized' => 'RM C']);

        $calculator = app(ConsumerRmRealizationCalculator::class);
        $may = collect($calculator->calculate('2026-05-31'))->where('produk', 'KPR')->keyBy('rm');
        $this->assertSame(300000000.0, $may['RM B']['realisasi_os']);
        $this->assertSame(200000000.0, $may['RM A']['realisasi_os']);
        $this->assertFalse($may->has('RM C'));
        $june = collect($calculator->calculate('2026-05-31', '2026-06-30'));
        $this->assertSame(300000000.0, $june->where('produk', 'KPR')->keyBy('rm')['RM C']['realisasi_os']);
        $this->assertSame('RM A', $june->where('produk', 'BRIGUNA-KONSUMER')->first()['rm']);
        $this->assertSame(650000000.0, $june->sum('realisasi_os'));
        $this->assertSame(500000000.0, collect($calculator->calculate('2026-05-31', '2026-06-30', 'KPR'))->sum('realisasi_os'));
    }

    public function test_kpr_deduplicates_account_aliases_across_cifs_and_preserves_portfolio_on_projection(): void
    {
        $this->insertDailyLoanRow('BASE', 'KPR', 100, 80, 'BASE', '2026-04-30');
        $this->insertDailyLoanRow('0000125', 'KPR', 150000000, 145000000, 'OLD-CIF', '2026-05-10');
        $this->insertDailyLoanRow('125', 'KPR', 200000000, 190000000, 'NEW-CIF', '2026-05-31', tglRealisasi: '2026-05-10');
        DB::table('daily_loan_dinamis')->where('periode', '2026-05-31')->update(['rm_normalized' => 'RM B']);
        $metric = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-05-31'))->first();
        $this->assertSame('RM B', $metric['rm']);
        $this->assertSame(1, $metric['realisasi_deb']);
        $this->assertSame(200000000.0, $metric['realisasi_os']);

        $original = collect([(object) [
            'periode' => '2026-05-31', 'cabang' => 'KC MADIUN', 'produk' => 'KPR',
            'rm' => 'RM A', 'loan_os' => 190000000.0, 'npl_os' => 10000000.0,
            'realisasi_os' => 120000000.0, 'realisasi_deb' => 1,
        ], (object) [
            'periode' => '2026-05-31', 'cabang' => 'KC MADIUN', 'produk' => 'BRIGUNA-KONSUMER',
            'rm' => 'RM A', 'loan_os' => 180000000.0, 'realisasi_os' => 150000000.0,
        ]]);
        $projected = app(LandingConsumerOperationalService::class)
            ->applyKprRealizationAssignments($original, '2026-05-31');
        $this->assertSame(120000000.0, $original->first()->realisasi_os);
        $this->assertSame(0.0, $projected->first()->realisasi_os);
        $this->assertSame(190000000.0, $projected->first()->loan_os);
        $this->assertSame(10000000.0, $projected->first()->npl_os);
        $this->assertSame(200000000.0, $projected->where('rm', 'RM B')->sum('realisasi_os'));
        $this->assertSame(150000000.0, $projected->where('produk', 'BRIGUNA-KONSUMER')->sum('realisasi_os'));
        $this->assertEquals($original[1], $projected[1]);
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

    public function test_consumer_realisasi_subtracts_every_previous_account_in_the_same_cif(): void
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
        $this->assertSame(225000000.0, (float) $briguna->realisasi_os);
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
        $this->assertSame(205000000.0, (float) $snapshot->realisasi_os);
    }

    public function test_briguna_origination_day_matches_kanwil_july_cif_balances(): void
    {
        // Independent RO cached daily figures, 25 July 2026: Zulfa 278.217627m
        // and Dimas 23.729448m. Synthetic identities preserve the source amounts.
        $this->insertDailyLoanRow('Z-OLD', 'BRIGUNAKONSUMER', 300000000, 255851152, 'CIF-Z', '2026-06-30', tglRealisasi: '2024-02-24');
        $this->insertDailyLoanRow('Z-OLD', 'BRIGUNAKONSUMER', 300000000, 254068779, 'CIF-Z', '2026-07-25', tglRealisasi: '2024-02-24');
        $this->insertDailyLoanRow('Z-NEW', 'BRIGUNAKONSUMER', 280000000, 280000000, 'CIF-Z', '2026-07-25', tglRealisasi: '2026-07-25');
        $this->insertDailyLoanRow('Z-NEW', 'BRIGUNAKONSUMER', 280000000, 280000000, 'CIF-Z', '2026-07-31', tglRealisasi: '2026-07-25');
        $this->insertDailyLoanRow('D-OLD-1', 'BRIGUNAKONSUMER', 450000000, 390155278, 'CIF-D', '2026-06-30', tglRealisasi: '2023-07-07');
        $this->insertDailyLoanRow('D-OLD-2', 'BRIGUNAKONSUMER', 100000000, 96115274, 'CIF-D', '2026-06-30', tglRealisasi: '2025-04-29');
        $this->insertDailyLoanRow('D-NEW', 'BRIGUNAKONSUMER', 510000000, 510000000, 'CIF-D', '2026-07-25', tglRealisasi: '2026-07-25');
        DB::table('daily_loan_dinamis')->where('cifno_clean', 'CIF-D')->update(['rm_normalized' => 'RM D']);

        $metrics = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-07-31'))->keyBy('rm');
        $this->assertSame(278217627.0, $metrics['RM A']['realisasi_os']);
        $this->assertSame(23729448.0, $metrics['RM D']['realisasi_os']);
        $this->assertSame(1, $metrics['RM A']['suplesi_deb']);
        $this->assertSame(1, $metrics['RM D']['suplesi_deb']);

        $migration = require database_path('migrations/2026_09_09_010000_create_consumer_rm_position_history_tables.php');
        $migration->up();
        foreach (['2026-06-30', '2026-07-25', '2026-07-31'] as $date) {
            $capture = app(ConsumerRmPositionHistoryStore::class)->capturePeriod($date, true);
            $this->assertTrue($capture['verified']);
        }
        DB::table('daily_loan_dinamis')->where('periode', '2026-07-25')->delete();
        $archived = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-07-31'))->keyBy('rm');
        $this->assertSame($metrics->all(), $archived->all());
    }

    public function test_briguna_cif_movement_includes_earlier_bookings_at_the_event_cutoff(): void
    {
        // Bagus, 31 July: current 227.595240m + 350m + 100m minus 228.203372m.
        $this->insertDailyLoanRow('OLD', 'BRIGUNAKONSUMER', 230000000, 228203372, 'CIF-CUMULATIVE', '2026-06-30', tglRealisasi: '2026-03-26');
        $this->insertDailyLoanRow('OLD', 'BRIGUNAKONSUMER', 230000000, 227595240, 'CIF-CUMULATIVE', '2026-07-31', tglRealisasi: '2026-03-26');
        $this->insertDailyLoanRow('EARLIER', 'BRIGUNAKONSUMER', 350000000, 350000000, 'CIF-CUMULATIVE', '2026-07-02', tglRealisasi: '2026-07-02');
        $this->insertDailyLoanRow('OLD', 'BRIGUNAKONSUMER', 230000000, 228203372, 'CIF-CUMULATIVE', '2026-07-02', tglRealisasi: '2026-03-26');
        $this->insertDailyLoanRow('EARLIER', 'BRIGUNAKONSUMER', 350000000, 350000000, 'CIF-CUMULATIVE', '2026-07-31', tglRealisasi: '2026-07-02');
        $this->insertDailyLoanRow('NEW', 'BRIGUNAKONSUMER', 100000000, 100000000, 'CIF-CUMULATIVE', '2026-07-31', tglRealisasi: '2026-07-31');

        $metric = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-07-31'))->first();
        $this->assertSame(2, $metric['realisasi_deb']);
        $this->assertSame(350000000.0 + 449391868.0, $metric['realisasi_os']);
    }

    public function test_briguna_keeps_facility_estimate_when_origination_day_is_unavailable(): void
    {
        $this->insertDailyLoanRow('OLD', 'BRIGUNAKONSUMER', 300000000, 255851152, 'CIF-MISSING', '2026-06-30', tglRealisasi: '2024-02-24');
        $this->insertDailyLoanRow('NEW', 'BRIGUNAKONSUMER', 280000000, 280000000, 'CIF-MISSING', '2026-07-31', tglRealisasi: '2026-07-25');

        $metric = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-07-31'))->first();
        $this->assertSame(24148848.0, $metric['realisasi_os']);
        $this->assertSame(1, $metric['suplesi_deb']);
    }

    public function test_briguna_same_day_multiple_bookings_do_not_duplicate_the_cif_movement(): void
    {
        $this->insertDailyLoanRow('OLD', 'BRIGUNAKONSUMER', 120000000, 90000000, 'CIF-MULTI-EVENT', '2026-06-30', tglRealisasi: '2025-01-10');
        $this->insertDailyLoanRow('NEW-1', 'BRIGUNAKONSUMER', 400000000, 400000000, 'CIF-MULTI-EVENT', '2026-07-25', tglRealisasi: '2026-07-25');
        $this->insertDailyLoanRow('NEW-2', 'BRIGUNAKONSUMER', 300000000, 300000000, 'CIF-MULTI-EVENT', '2026-07-25', tglRealisasi: '2026-07-25');

        $metric = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-07-25'))->first();
        $this->assertSame(2, $metric['realisasi_deb']);
        $this->assertSame(610000000.0, $metric['realisasi_os']);
    }

    public function test_briguna_nett_disbursement_uses_complete_previous_and_event_cif_exposure(): void
    {
        foreach ([
            'OLD-25' => 25000000,
            'OLD-50' => 50000000,
            'OLD-100' => 100000000,
        ] as $account => $outstanding) {
            $this->insertDailyLoanRow(
                $account,
                'BRIGUNAKONSUMER',
                $outstanding,
                $outstanding,
                'CIF-THREE-FACILITIES',
                '2026-08-31',
                tglRealisasi: '2025-01-10'
            );
        }
        foreach (['OLD-50' => 50000000, 'OLD-100' => 100000000] as $account => $outstanding) {
            $this->insertDailyLoanRow(
                $account,
                'BRIGUNAKONSUMER',
                $outstanding,
                $outstanding,
                'CIF-THREE-FACILITIES',
                '2026-09-12',
                tglRealisasi: '2025-01-10'
            );
        }
        $this->insertDailyLoanRow(
            'NEW-50',
            'BRIGUNAKONSUMER',
            50000000,
            50000000,
            'CIF-THREE-FACILITIES',
            '2026-09-12',
            tglRealisasi: '2026-09-12'
        );

        $metric = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-09-12'))->first();

        $this->assertSame(1, $metric['realisasi_deb']);
        $this->assertSame(25000000.0, $metric['realisasi_os']);
        $this->assertSame(0, $metric['realisasi_baru_deb']);
        $this->assertSame(1, $metric['suplesi_deb']);
        $this->assertSame(25000000.0, $metric['suplesi_os']);
    }

    public function test_consumer_booking_with_blank_pn_is_retained_in_an_explicit_unassigned_bucket(): void
    {
        $this->insertDailyLoanRow(
            'PREVIOUS-DUMMY',
            'BRIGUNAKONSUMER',
            10000000,
            9000000,
            'CIF-PREVIOUS',
            '2026-08-31',
            tglRealisasi: '2025-01-10'
        );
        $this->insertDailyLoanRow(
            'BLANK-PN-BOOKING',
            'BRIGUNAKONSUMER',
            100000000,
            95000000,
            'CIF-BLANK-PN',
            '2026-09-12',
            tglRealisasi: '2026-09-12'
        );
        DB::table('daily_loan_dinamis')
            ->where('nomor_rekening1', 'BLANK-PN-BOOKING')
            ->update([
                'rm_normalized' => '',
                'pn_pengelola1' => '',
                'pn_pemrakarsa1' => '',
            ]);

        $metric = collect(app(ConsumerRmRealizationCalculator::class)->calculate('2026-09-12'))
            ->firstWhere('rm', ConsumerRmRealizationCalculator::UNASSIGNED_RM);

        $this->assertNotNull($metric);
        $this->assertSame(1, $metric['realisasi_deb']);
        $this->assertSame(100000000.0, $metric['realisasi_os']);
        $this->assertSame(1, $metric['realisasi_baru_deb']);
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
        $this->assertSame(19000000.0, (float) $snapshot->realisasi_os);
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
        $this->assertSame(595000000.0, (float) $snapshot->realisasi_os);
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
        $this->assertSame(19000000.0, (float) $snapshot->realisasi_os);
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
        $this->assertSame(305000000.0, (float) $snapshot->realisasi_os);
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

    public function test_small_snapshot_realisasi_combines_bookings_and_same_account_plafond_increases(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('OLD-SAME', 'COMMERCIAL', 300000000, 250000000, 'CIF-SAME', '2026-06-30', 'SMALL', tglRealisasi: '2025-01-01');
        $this->insertDailyLoanRow('OLD-REPLACED', 'COMMERCIAL', 600000000, 500000000, 'CIF-NEW-ACCOUNT', '2026-06-30', 'SMALL', tglRealisasi: '2025-01-01');
        $this->insertDailyLoanRow('SUPPLEMENT', 'COMMERCIAL', 400000000, 350000000, 'CIF-SUPPLEMENT', '2026-06-30', 'SMALL', tglRealisasi: '2025-01-01');
        $this->insertDailyLoanRow('OLD-SAME', 'COMMERCIAL', 350000000, 350000000, 'CIF-SAME', '2026-07-31', 'SMALL', tglRealisasi: '2026-07-10');
        $this->insertDailyLoanRow('NEW-ACCOUNT', 'COMMERCIAL', 800000000, 800000000, 'CIF-NEW-ACCOUNT', '2026-07-31', 'SMALL', tglRealisasi: '2026-07-10');
        $this->insertDailyLoanRow('BRAND-NEW', 'COMMERCIAL', 500000000, 500000000, 'CIF-NEW', '2026-07-31', 'SMALL', tglRealisasi: '2026-07-11');
        $this->insertDailyLoanRow('SUPPLEMENT', 'COMMERCIAL', 500000000, 450000000, 'CIF-SUPPLEMENT', '2026-07-31', 'SMALL', tglRealisasi: '2025-01-01');

        $builder->rebuildPerformanceRm('2026-07-31', true);

        $snapshot = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-07-31')
            ->where('segmen', 'SMALL')
            ->where('produk', 'SMALL')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(4, (int) $snapshot->realisasi_deb);
        $this->assertSame(1750000000.0, (float) $snapshot->realisasi_os);
        $this->assertDatabaseHas('performance_rm_cabang_snapshots', [
            'periode' => '2026-07-31',
            'segmen' => 'SMALL',
            'produk' => 'SMALL',
            'realisasi_deb' => 4,
            'realisasi_os' => 1750000000,
        ]);
    }

    public function test_small_quadrants_match_closed_month_history_despite_rm_case_and_outer_spaces(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));
        $history = [
            ['2026-01-31', 'Rm A', 1600000000, 100000000, 0, 'UNIT A'],
            ['2026-02-28', 'rm a', 1600000000, 100000000, 15000000, 'UNIT A'],
            ['2026-01-31', ' RM B ', 1600000000, 100000000, 0, 'UNIT A'],
            ['2026-02-28', ' rm b ', 1600000000, 100000000, 14999999, 'UNIT A'],
            ['2026-01-31', 'Rm D', 0, 100000000, 0, 'UNIT A'],
            ['2026-02-28', 'RM D', 1600000000, 50000000, 0, 'UNIT A'],
            ['2026-02-28', ' rm d ', 1600000000, 50000000, 0, 'UNIT B'],
        ];
        foreach ($history as [$period, $rm, $realization, $loanOs, $larOs, $unit]) {
            DB::table('performance_rm_snapshots')->insert([
                'periode' => $period, 'cabang' => 'KC MADIUN', 'unit' => $unit,
                'branch_code' => '123', 'rm' => $rm, 'segmen' => 'SMALL', 'produk' => 'SMALL',
                'realisasi_os' => $realization, 'loan_os' => $loanOs, 'sml_os' => $larOs,
            ]);
        }
        foreach (['A', 'B', 'C', 'D'] as $suffix) {
            $this->insertDailyLoanRow('SMALL-'.$suffix, 'COMMERCIAL', 100000000, 90000000, 'CIF-'.$suffix, '2026-03-02', 'SMALL', tglRealisasi: '2025-01-01');
            DB::table('daily_loan_dinamis')->where('nomor_rekening1', 'SMALL-'.$suffix)->update([
                'rm_normalized' => 'RM '.$suffix,
                'pn_pengelola1' => 'RM '.$suffix,
                'pn_pemrakarsa1' => 'RM '.$suffix,
            ]);
        }
        $historyBefore = DB::table('performance_rm_snapshots')->orderBy('id')->get()->all();

        $builder->rebuildPerformanceRm('2026-03-02', true);

        $rows = DB::table('performance_rm_snapshots')->where('periode', '2026-03-02')->get()->keyBy('rm');
        $this->assertCount(4, $rows);
        // Monthly average equals Rp1.6 billion; exactly 15% LAR stays in quadrant 2.
        $this->assertSame(2, $rows['RM A']->quadrant);
        $this->assertSame(1, $rows['RM B']->quadrant);
        $this->assertNull($rows['RM C']->quadrant);
        // Both prior assignments count once toward the same RM's history and LAR.
        $this->assertSame(1, $rows['RM D']->quadrant);
        $this->assertSame(0.0, (float) $rows->sum('realisasi_os'));
        $this->assertSame(0, $rows->sum('realisasi_deb'));
        $this->assertSame(360000000.0, (float) $rows->sum('loan_os'));
        $this->assertEquals($historyBefore, DB::table('performance_rm_snapshots')->where('periode', '<', '2026-03-02')->orderBy('id')->get()->all());

        $this->insertDailyLoanRow('SMALL-MARCH', 'COMMERCIAL', 1600000000, 1600000000, 'CIF-MARCH', '2026-03-31', 'SMALL');
        $builder->rebuildPerformanceRm('2026-03-31', true);
        $monthEnd = DB::table('performance_rm_snapshots')->where('periode', '2026-03-31')->where('rm', 'RM A')->first();
        $this->assertNotNull($monthEnd);
        $this->assertSame(1, $monthEnd->quadrant);
        $this->assertSame(1600000000.0, (float) $monthEnd->realisasi_os);
        $this->assertSame(1, $monthEnd->realisasi_deb);
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
            'pn_pemrakarsa1' => $segment === 'SMALL' ? 'RM A' : null,
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
