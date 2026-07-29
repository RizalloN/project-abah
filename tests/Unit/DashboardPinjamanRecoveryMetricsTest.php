<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardPinjamanReportController;
use App\Jobs\EnsureDashboardSnapshotJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DashboardPinjamanRecoveryMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        Cache::flush();

        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->date('periode')->index();
            $table->string('acctno')->nullable();
            $table->string('kanca')->nullable();
            $table->string('unit')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->decimal('pokok', 20, 2)->nullable();
        });

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->date('periode')->index();
            $table->string('nomor_rekening1')->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->string('kolek_detail')->nullable();
            $table->integer('umur_tunggakan')->nullable();
            $table->string('flag_restruk')->nullable();
            $table->string('kolek')->nullable();
            $table->string('status_rekening1')->nullable();
            $table->string('ln_type')->nullable();
            $table->date('tgl_jatuh_tempo')->nullable();
            $table->date('next_pmt_date')->nullable();
            $table->date('next_pmt_int_date')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
            $table->string('nama_debitur1')->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('tunggakan_pokok', 20, 2)->nullable();
            $table->decimal('tunggakan_bunga', 20, 2)->nullable();
            $table->decimal('tunggakan_penalti', 20, 2)->nullable();
            $table->decimal('npb_pokok_la', 20, 2)->nullable();
            $table->decimal('npb_bunga_la', 20, 2)->nullable();
            $table->decimal('payment_amount', 20, 2)->nullable();
            $table->decimal('pmtamt', 20, 2)->nullable();
            $table->integer('freq_payment')->nullable();
            $table->integer('freq_int_payment')->nullable();
        });

        Schema::create('dashboard_pinjaman_snapshots', function (Blueprint $table): void {
            $table->date('periode')->index();
            $table->string('account_number')->nullable();
            $table->decimal('loan_balance', 20, 2)->nullable();
            $table->string('quality_bucket')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
        });
    }

    public function test_recovery_metrics_rejects_collapsed_scientific_lw325_accounts(): void
    {
        DB::table('lw325_ph')->insert([
            ['periode' => '2025-04-30', 'acctno' => '6,49201E+14', 'pokok' => 1000000],
            ['periode' => '2025-04-30', 'acctno' => '6,49201E+14', 'pokok' => 2000000],
            ['periode' => '2025-04-30', 'acctno' => '6,50501E+14', 'pokok' => 3000000],
        ]);

        $this->assertFalse($this->invokeShouldUseLw325RecoveryMetrics('2025-04-30'));
    }

    public function test_recovery_metrics_accepts_clean_lw325_accounts(): void
    {
        DB::table('lw325_ph')->insert([
            ['periode' => '2026-04-30', 'acctno' => '649201027193100', 'pokok' => 1000000],
            ['periode' => '2026-04-30', 'acctno' => '650501027193101', 'pokok' => 2000000],
            ['periode' => '2026-04-30', 'acctno' => '650501027193102', 'pokok' => 3000000],
        ]);

        $this->assertTrue($this->invokeShouldUseLw325RecoveryMetrics('2026-04-30'));
    }

    public function test_matrix_returns_promptly_while_missing_snapshots_are_queued(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-06-30', 'nomor_rekening1' => '001', 'baki_debet1' => 100],
            ['periode' => '2026-07-10', 'nomor_rekening1' => '001', 'baki_debet1' => 100],
        ]);
        Queue::fake();

        $startedAt = microtime(true);
        $response = (new DashboardPinjamanReportController())->data(Request::create('/report/dashboard-pinjaman/data', 'GET', [
            'periode' => '2026-07-10',
        ]));
        $elapsed = microtime(true) - $startedAt;

        $payload = $response->getData(true);

        $this->assertSame('warming', $payload['status']);
        $this->assertSame(['2026-07-10', '2026-06-30'], $payload['warming_periods']);
        $this->assertLessThan(1.0, $elapsed);
        Queue::assertPushed(EnsureDashboardSnapshotJob::class, 2);
    }

    public function test_recovery_metrics_accepts_clean_duplicate_lw325_accounts(): void
    {
        DB::table('lw325_ph')->insert([
            ['periode' => '2026-04-30', 'acctno' => '649201027193100', 'pokok' => 1000000],
            ['periode' => '2026-04-30', 'acctno' => '649201027193100', 'pokok' => 500000],
            ['periode' => '2026-04-30', 'acctno' => '650501027193101', 'pokok' => 2000000],
        ]);

        $this->assertTrue($this->invokeShouldUseLw325RecoveryMetrics('2026-04-30'));
    }

    public function test_recovery_previous_ph_period_must_be_previous_month_end(): void
    {
        DB::table('lw325_ph')->insert([
            ['periode' => '2026-03-30', 'acctno' => '649201027193100', 'pokok' => 1000000],
            ['periode' => '2026-04-30', 'acctno' => '649201027193100', 'pokok' => 900000],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'resolvePreviousMonthPhPeriod');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller, '2026-04-30'));

        DB::table('lw325_ph')->insert([
            ['periode' => '2026-03-31', 'acctno' => '649201027193100', 'pokok' => 1000000],
        ]);

        $this->assertSame('2026-03-31', $method->invoke($controller, '2026-04-30'));
    }

    public function test_recovery_comparison_period_uses_exact_previous_month_end(): void
    {
        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'resolveComparisonPeriod');
        $method->setAccessible(true);

        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-02-27'],
            ['periode' => '2026-02-28'],
            ['periode' => '2026-03-31'],
        ]);

        $this->assertSame('2026-02-28', $method->invoke($controller, '2026-03-31'));

        DB::table('daily_loan_dinamis')->delete();

        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-03-30'],
            ['periode' => '2026-03-31'],
            ['periode' => '2026-04-30'],
        ]);

        $this->assertSame('2026-03-31', $method->invoke($controller, '2026-04-30'));

        DB::table('daily_loan_dinamis')->where('periode', '2026-03-31')->delete();

        $this->assertNull($method->invoke($controller, '2026-04-30'));
    }

    public function test_recovery_period_options_include_daily_loan_dates_with_comparison_period(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-03-31'],
            ['periode' => '2026-04-15'],
            ['periode' => '2026-04-30'],
            ['periode' => '2026-05-10'],
            ['periode' => '2026-05-11'],
            ['periode' => '2026-06-01'],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'fetchRecoveryReportPeriods');
        $method->setAccessible(true);

        $periods = $method->invoke($controller)->all();

        $this->assertContains('2026-05-11', $periods);
        $this->assertContains('2026-05-10', $periods);
        $this->assertContains('2026-04-15', $periods);
        $this->assertNotContains('2026-06-01', $periods);
    }

    public function test_matrix_filter_options_are_derived_from_covering_dimension_pairs(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-06-20', 'segmen_dashboard' => 'MICRO', 'produk_dashboard' => 'KUR MIKRO', 'cabang1' => 'KC Madiun', 'unit1' => 'Unit A'],
            ['periode' => '2026-06-20', 'segmen_dashboard' => 'MICRO', 'produk_dashboard' => 'KUPEDES', 'cabang1' => 'KC Magetan', 'unit1' => 'Unit B'],
            ['periode' => '2026-06-20', 'segmen_dashboard' => 'CONSUMER', 'produk_dashboard' => 'BRIGUNA', 'cabang1' => 'KC Madiun', 'unit1' => 'Unit C'],
            ['periode' => '2026-06-20', 'segmen_dashboard' => 'SMALL', 'produk_dashboard' => 'KMK', 'cabang1' => 'KC Ngawi', 'unit1' => 'Unit D'],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildPeriodFilterOptions');
        $method->setAccessible(true);

        $options = $method->invoke($controller, '2026-06-20', [
            'segmen' => ['micro'],
            'produk' => ['kur mikro'],
            'cabang' => ['kc madiun'],
            'unit' => [],
        ], true);

        $this->assertSame(['CONSUMER', 'MICRO', 'SMALL'], $options['segments']->all());
        $this->assertSame(['KUPEDES', 'KUR MIKRO'], $options['products']->all());
        $this->assertSame(['KC Madiun', 'KC Magetan', 'KC Ngawi'], $options['branches']->all());
        $this->assertSame(['Unit A', 'Unit C'], $options['units']->all());
    }

    public function test_matrix_ph_uses_current_daily_loan_status_five_balance(): void
    {
        DB::table('dashboard_pinjaman_snapshots')->insert([
            [
                'periode' => '2026-05-31',
                'account_number' => 'A1',
                'loan_balance' => 181492538,
                'quality_bucket' => 'D1',
            ],
            [
                'periode' => '2026-05-31',
                'account_number' => 'A2',
                'loan_balance' => 3993097,
                'quality_bucket' => 'M',
            ],
            [
                'periode' => '2026-05-31',
                'account_number' => 'A4',
                'loan_balance' => 0,
                'quality_bucket' => 'L',
            ],
        ]);

        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-20',
                'nomor_rekening1' => 'A1',
                'baki_debet1' => 181492538,
                'status_rekening1' => '5',
            ],
            [
                'periode' => '2026-06-20',
                'nomor_rekening1' => 'A2',
                'baki_debet1' => 3993097,
                'status_rekening1' => '5',
            ],
            [
                'periode' => '2026-06-20',
                'nomor_rekening1' => 'A3',
                'baki_debet1' => 999999999,
                'status_rekening1' => '1',
            ],
            [
                'periode' => '2026-06-20',
                'nomor_rekening1' => 'A4',
                'baki_debet1' => 0,
                'status_rekening1' => '5',
            ],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildDailyLoanPhMetricQuery');
        $method->setAccessible(true);

        $rows = $method->invoke($controller, '2026-06-20', '2026-05-31', [
            'segmen' => [],
            'produk' => [],
            'cabang' => [],
            'unit' => [],
        ], true)->get()->keyBy('before_bucket');

        $this->assertSame(18149253800, (int) $rows->get('D1')->amount_cents);
        $this->assertSame(399309700, (int) $rows->get('M')->amount_cents);
        $this->assertSame(0, (int) $rows->get('L')->amount_cents);
        $this->assertCount(3, $rows);
    }

    public function test_matrix_ph_deduplicates_repeated_daily_loan_account_rows(): void
    {
        DB::table('dashboard_pinjaman_snapshots')->insert([
            [
                'periode' => '2026-05-31',
                'account_number' => '649201027193100',
                'loan_balance' => 200,
                'quality_bucket' => 'D1',
            ],
        ]);

        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-20',
                'nomor_rekening1' => '649201027193100',
                'baki_debet1' => 125,
                'status_rekening1' => '5',
            ],
            [
                'periode' => '2026-06-20',
                'nomor_rekening1' => '649201027193100',
                'baki_debet1' => 125,
                'status_rekening1' => '5',
            ],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildDailyLoanPhMetricQuery');
        $method->setAccessible(true);

        $rows = $method->invoke($controller, '2026-06-20', '2026-05-31', [
            'segmen' => [],
            'produk' => [],
            'cabang' => [],
            'unit' => [],
        ], true)->get();

        $this->assertSame(12500, (int) $rows->sum('amount_cents'));
    }

    public function test_matrix_exit_metrics_use_current_ph_pokok_for_previous_daily_loan_accounts(): void
    {
        DB::table('dashboard_pinjaman_snapshots')->insert([
            [
                'periode' => '2026-04-30',
                'account_number' => 'A1',
                'loan_balance' => 500,
                'quality_bucket' => 'L',
            ],
            [
                'periode' => '2026-04-30',
                'account_number' => '000649201027193100',
                'loan_balance' => 250,
                'quality_bucket' => 'D1',
            ],
            [
                'periode' => '2026-04-30',
                'account_number' => 'A3',
                'loan_balance' => 125,
                'quality_bucket' => 'L',
            ],
            [
                'periode' => '2026-05-31',
                'account_number' => 'A1',
                'loan_balance' => 450,
                'quality_bucket' => 'L',
            ],
        ]);

        DB::table('lw325_ph')->insert([
            [
                'periode' => '2026-05-31',
                'acctno' => '649201027193100',
                'kanca' => 'KC Old',
                'unit' => 'Unit Old',
                'segmen_dashboard' => 'Micro',
                'pokok' => 175,
            ],
        ]);

        $controller = new DashboardPinjamanReportController();
        $exitMethod = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildDailyLoanExitMetricQuery');
        $exitMethod->setAccessible(true);

        $rows = $exitMethod->invoke($controller, '2026-05-31', '2026-04-30', [
            'segmen' => [],
            'produk' => [],
            'cabang' => [],
            'unit' => [],
        ], true, true)->get();

        $totalExitCents = (int) $rows->sum('amount_cents');
        $rows = $rows->mapWithKeys(fn ($row) => [$row->before_bucket . ':' . $row->metric_type => $row]);

        $this->assertSame(12500, (int) $rows->get('L:lunas')->amount_cents);
        $this->assertSame(17500, (int) $rows->get('D1:ph')->amount_cents);
        $this->assertSame(30000, $totalExitCents);
    }

    public function test_matrix_exit_ph_uses_latest_same_month_ph_period_when_daily_loan_is_month_end(): void
    {
        DB::table('dashboard_pinjaman_snapshots')->insert([
            [
                'periode' => '2026-05-31',
                'account_number' => '649201027193100',
                'loan_balance' => 250,
                'quality_bucket' => 'D1',
            ],
        ]);

        DB::table('lw325_ph')->insert([
            [
                'periode' => '2026-05-31',
                'acctno' => '649201027193100',
                'kanca' => 'KC Old',
                'unit' => 'Unit Old',
                'segmen_dashboard' => 'Micro',
                'pokok' => 250,
            ],
            [
                'periode' => '2026-06-29',
                'acctno' => '649201027193100',
                'kanca' => 'KC Old',
                'unit' => 'Unit Old',
                'segmen_dashboard' => 'Micro',
                'pokok' => 175,
            ],
        ]);

        $controller = new DashboardPinjamanReportController();
        $exitMethod = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildDailyLoanExitMetricQuery');
        $exitMethod->setAccessible(true);

        $rows = $exitMethod->invoke($controller, '2026-06-30', '2026-05-31', [
            'segmen' => [],
            'produk' => [],
            'cabang' => [],
            'unit' => [],
        ], true, true)->get()->keyBy(fn ($row) => $row->before_bucket . ':' . $row->metric_type);

        $this->assertSame(17500, (int) $rows->get('D1:ph')->amount_cents);
    }

    public function test_matrix_ph_total_combines_active_daily_loan_ph_and_lw325_exit_without_double_counting(): void
    {
        DB::table('dashboard_pinjaman_snapshots')->insert([
            [
                'periode' => '2026-05-31',
                'account_number' => '649201027193100',
                'loan_balance' => 200,
                'quality_bucket' => 'D1',
            ],
            [
                'periode' => '2026-05-31',
                'account_number' => '000649201027193101',
                'loan_balance' => 250,
                'quality_bucket' => 'M',
            ],
            [
                'periode' => '2026-06-20',
                'account_number' => '649201027193100',
                'loan_balance' => 125,
                'quality_bucket' => 'M',
            ],
        ]);

        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-20',
                'nomor_rekening1' => '649201027193100',
                'baki_debet1' => 125,
                'status_rekening1' => '5',
            ],
        ]);

        DB::table('lw325_ph')->insert([
            [
                'periode' => '2026-05-31',
                'acctno' => '649201027193102',
                'pokok' => 300,
            ],
            [
                'periode' => '2026-06-20',
                'acctno' => '649201027193102',
                'pokok' => 275,
            ],
            [
                'periode' => '2026-06-20',
                'acctno' => '649201027193101',
                'pokok' => 175,
            ],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildLw325MatrixMetricAggregateQuery');
        $method->setAccessible(true);

        $rows = $method->invoke($controller, '2026-06-20', '2026-05-31', [
            'segmen' => [],
            'produk' => [],
            'cabang' => [],
            'unit' => [],
        ], true, true)->get();

        $phRows = $rows->where('metric_type', 'ph');

        $this->assertSame(30000, (int) $phRows->sum('amount_cents'));
        $this->assertSame(['D1', 'M'], $phRows->pluck('before_bucket')->sort()->values()->all());
    }

    public function test_lw325_recovery_metrics_lookup_current_period_by_account_only(): void
    {
        DB::table('dashboard_pinjaman_snapshots')->insert([
            [
                'periode' => '2026-05-31',
                'account_number' => 'A1',
                'loan_balance' => 1000,
                'quality_bucket' => 'L',
                'cabang1' => 'KC Old',
                'unit1' => 'Unit Old',
            ],
            [
                'periode' => '2026-05-31',
                'account_number' => 'A2',
                'loan_balance' => 500,
                'quality_bucket' => 'L',
                'cabang1' => 'KC Old',
                'unit1' => 'Unit Old',
            ],
        ]);

        DB::table('lw325_ph')->insert([
            [
                'periode' => '2026-05-31',
                'acctno' => 'A1',
                'kanca' => 'KC Old',
                'unit' => 'Unit Old',
                'segmen_dashboard' => 'Micro',
                'pokok' => 1000000.75,
            ],
            [
                'periode' => '2026-06-10',
                'acctno' => '000A1',
                'kanca' => 'KC New',
                'unit' => 'Unit New',
                'segmen_dashboard' => 'Micro',
                'pokok' => 999701.25,
            ],
            [
                'periode' => '2026-05-31',
                'acctno' => 'A2',
                'kanca' => 'KC Old',
                'unit' => 'Unit Old',
                'segmen_dashboard' => 'Micro',
                'pokok' => 1500000.90,
            ],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildLw325RecoveryMetricQuery');
        $method->setAccessible(true);

        $query = $method->invoke($controller, '2026-06-10', '2026-05-31', [
            'segmen' => [],
            'produk' => [],
            'cabang' => ['KC Old'],
            'unit' => [],
        ], true);

        $rows = collect($query->get())->keyBy('metric_type');

        $this->assertSame(29900, (int) $rows->get('principal_reduction')->amount_cents);
        $this->assertFalse($rows->has('lunas'));
    }

    public function test_lw325_principal_reduction_aggregates_duplicate_accounts_before_comparison(): void
    {
        DB::table('dashboard_pinjaman_snapshots')->insert([
            [
                'periode' => '2026-05-31',
                'account_number' => '649201027193100',
                'loan_balance' => 1000,
                'quality_bucket' => 'L',
            ],
        ]);

        DB::table('lw325_ph')->insert([
            ['periode' => '2026-05-31', 'acctno' => '649201027193100', 'pokok' => 600],
            ['periode' => '2026-05-31', 'acctno' => '649201027193100', 'pokok' => 400],
            ['periode' => '2026-06-20', 'acctno' => '649201027193100', 'pokok' => 300],
            ['periode' => '2026-06-20', 'acctno' => '649201027193100', 'pokok' => 400],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildLw325RecoveryMetricQuery');
        $method->setAccessible(true);

        $rows = $method->invoke($controller, '2026-06-20', '2026-05-31', [
            'segmen' => [],
            'produk' => [],
            'cabang' => [],
            'unit' => [],
        ], true)->get();

        $this->assertSame(30000, (int) $rows->sum('amount_cents'));
    }

    public function test_ug_npl_rows_use_revised_arrears_rules_for_due_periodic_general_and_dl_loans(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-28',
                'nomor_rekening1' => 'DUE-01',
                'nama_debitur1' => 'Debitur Jatuh Tempo',
                'ln_type' => 'A4',
                'tgl_jatuh_tempo' => '2026-06-27',
                'kolek' => '3',
                'flag_restruk' => null,
                'umur_tunggakan' => 120,
                'plafon' => 100000000,
                'baki_debet1' => 90000000,
                'tunggakan_pokok' => 3000000,
                'tunggakan_bunga' => 500000,
                'tunggakan_penalti' => 100000,
                'freq_payment' => 3,
                'freq_int_payment' => 3,
            ],
            [
                'periode' => '2026-06-28',
                'nomor_rekening1' => 'PER-01',
                'nama_debitur1' => 'Debitur Periodik',
                'ln_type' => 'A4',
                'tgl_jatuh_tempo' => '2027-06-28',
                'kolek' => '3',
                'flag_restruk' => null,
                'umur_tunggakan' => 120,
                'plafon' => 100000000,
                'baki_debet1' => 80000000,
                'tunggakan_pokok' => 3000000,
                'tunggakan_bunga' => 500000,
                'tunggakan_penalti' => 100000,
                'freq_payment' => 3,
                'freq_int_payment' => 1,
            ],
            [
                'periode' => '2026-06-28',
                'nomor_rekening1' => 'GEN-01',
                'nama_debitur1' => 'Debitur Umum',
                'ln_type' => 'A4',
                'tgl_jatuh_tempo' => '2027-06-28',
                'kolek' => '5',
                'flag_restruk' => null,
                'umur_tunggakan' => 360,
                'plafon' => 100000000,
                'baki_debet1' => 70000000,
                'tunggakan_pokok' => 50000000,
                'tunggakan_bunga' => 20000000,
                'tunggakan_penalti' => 5000000,
                'freq_payment' => 12,
                'freq_int_payment' => 12,
            ],
            [
                'periode' => '2026-06-28',
                'nomor_rekening1' => 'DL-01',
                'nama_debitur1' => 'Debitur DL',
                'ln_type' => 'DL',
                'tgl_jatuh_tempo' => '2027-06-28',
                'kolek' => '3',
                'flag_restruk' => null,
                'umur_tunggakan' => 270,
                'plafon' => 100000000,
                'baki_debet1' => 60000000,
                'tunggakan_pokok' => 50000000,
                'tunggakan_bunga' => 20000000,
                'tunggakan_penalti' => 5000000,
                'freq_payment' => 3,
                'freq_int_payment' => 1,
            ],
            [
                'periode' => '2026-06-28',
                'nomor_rekening1' => 'DL-SML1-01',
                'nama_debitur1' => 'Debitur DL SML 1',
                'ln_type' => 'DL',
                'tgl_jatuh_tempo' => '2026-06-27',
                'kolek' => '2',
                'flag_restruk' => null,
                'umur_tunggakan' => 20,
                'plafon' => 100000000,
                'baki_debet1' => 55000000,
                'tunggakan_pokok' => 3000000,
                'tunggakan_bunga' => 500000,
                'tunggakan_penalti' => 100000,
                'freq_payment' => 1,
                'freq_int_payment' => 1,
            ],
        ]);

        $controller = new DashboardPinjamanReportController();
        $fetchMethod = new ReflectionMethod(DashboardPinjamanReportController::class, 'fetchUgNplRows');
        $fetchMethod->setAccessible(true);
        $mapMethod = new ReflectionMethod(DashboardPinjamanReportController::class, 'mapUgNplRow');
        $mapMethod->setAccessible(true);

        $rows = collect(iterator_to_array($fetchMethod->invoke($controller, '2026-06-28', [], [])))
            ->map(fn ($row): array => $mapMethod->invoke($controller, $row, 0))
            ->keyBy('nomor_rekening1');

        $this->assertSame('due_lancar', $rows['DUE-01']['action_key']);
        $this->assertSame(3600000.0, $rows['DUE-01']['estimated_payment']);
        $this->assertSame(3000000.0, $rows['DUE-01']['estimated_principal']);

        $this->assertSame('periodic_lancar', $rows['PER-01']['action_key']);
        $this->assertSame(3600000.0, $rows['PER-01']['estimated_payment']);
        $this->assertSame('Lancar', $rows['PER-01']['target_bucket']);

        $this->assertSame('general_sml3', $rows['GEN-01']['action_key']);
        $this->assertSame(12, $rows['GEN-01']['effective_months']);
        $this->assertSame(10, $rows['GEN-01']['cycles']);
        $this->assertEqualsWithDelta(63333333.33, $rows['GEN-01']['estimated_payment'], 0.01);
        $this->assertSame('SML 3', $rows['GEN-01']['target_bucket']);

        $this->assertSame('dl_sml3', $rows['DL-01']['action_key']);
        $this->assertSame(9, $rows['DL-01']['effective_months']);
        $this->assertSame(7, $rows['DL-01']['cycles']);
        $this->assertSame(0.0, $rows['DL-01']['estimated_principal']);
        $this->assertEqualsWithDelta(20555555.56, $rows['DL-01']['estimated_payment'], 0.01);

        $this->assertSame('SML 1', $rows['DL-SML1-01']['current_bucket']);
        $this->assertSame('Lancar', $rows['DL-SML1-01']['target_bucket']);
        $this->assertTrue($rows['DL-SML1-01']['is_past_due']);
        $this->assertSame(0.0, $rows['DL-SML1-01']['estimated_principal']);
        $this->assertSame(600000.0, $rows['DL-SML1-01']['estimated_payment']);
        $this->assertSame('DL SML 1 -> Lancar: bunga + penalti (pokok 0)', $rows['DL-SML1-01']['payment_rule']);
    }

    public function test_ug_npl_rows_can_be_filtered_by_segment(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-28',
                'nomor_rekening1' => 'UG-MICRO-01',
                'segmen_dashboard' => 'MICRO',
                'kolek' => '2',
                'umur_tunggakan' => 20,
                'tunggakan_bunga' => 500000,
                'cabang1' => 'KC Madiun',
                'unit1' => 'Unit A',
            ],
            [
                'periode' => '2026-06-28',
                'nomor_rekening1' => 'UG-SMALL-01',
                'segmen_dashboard' => 'SMALL',
                'kolek' => '2',
                'umur_tunggakan' => 20,
                'tunggakan_bunga' => 750000,
                'cabang1' => 'KC Madiun',
                'unit1' => 'Unit B',
            ],
        ]);

        $controller = new DashboardPinjamanReportController();
        $fetchMethod = new ReflectionMethod(DashboardPinjamanReportController::class, 'fetchUgNplRows');
        $fetchMethod->setAccessible(true);
        $rows = collect(iterator_to_array($fetchMethod->invoke(
            $controller,
            '2026-06-28',
            ['KC Madiun'],
            [],
            ['MICRO']
        )));

        $this->assertSame(['UG-MICRO-01'], $rows->pluck('nomor_rekening1')->all());
        $this->assertSame(['MICRO'], $rows->pluck('segmen_dashboard')->all());

        $view = file_get_contents(resource_path('views/report/dashboard-pinjaman/analisa-ug-npl.blade.php'));
        $this->assertStringContainsString('name="segmen_dashboard"', $view);
        $this->assertStringContainsString('row.segmen_dashboard', $view);
    }

    private function invokeShouldUseLw325RecoveryMetrics(string $period): bool
    {
        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'shouldUseLw325RecoveryMetrics');
        $method->setAccessible(true);

        return (bool) $method->invoke($controller, $period);
    }
}
