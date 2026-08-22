<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardPinjamanReportController;
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
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('DATEDIFF', static function ($a, $b): ?int {
            if ($a === null || $b === null) {
                return null;
            }

            return (int) floor((strtotime((string) $a) - strtotime((string) $b)) / 86400);
        }, 2);
        $pdo->sqliteCreateFunction('LEAST', static function (...$values) {
            $values = array_values(array_filter($values, static fn ($value): bool => $value !== null));

            return $values === [] ? null : min($values);
        });

        Schema::dropAllTables();
        Cache::flush();

        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->date('periode')->index();
            $table->string('acctno')->nullable();
            $table->string('kanca')->nullable();
            $table->string('unit')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->date('tgl_ph')->nullable();
            $table->decimal('pokok', 20, 2)->nullable();
        });

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->date('periode')->index();
            $table->string('uniqueid_namareport')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->string('cifno')->nullable();
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
            $table->date('tgl_realisasi')->nullable();
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

    public function test_matrix_uses_daily_loan_directly_without_waiting_for_snapshots(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-06-30', 'nomor_rekening1' => '001', 'baki_debet1' => 100, 'kolek' => '1', 'umur_tunggakan' => 0],
            ['periode' => '2026-07-10', 'nomor_rekening1' => '001', 'baki_debet1' => 100, 'kolek' => '1', 'umur_tunggakan' => 0],
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-07-10',
            'acctno' => '999000111222333',
            'pokok' => 0,
        ]);
        Queue::fake();

        $startedAt = microtime(true);
        $response = (new DashboardPinjamanReportController())->data(Request::create('/report/dashboard-pinjaman/data', 'GET', [
            'periode' => '2026-07-10',
        ]));
        $elapsed = microtime(true) - $startedAt;

        $payload = $response->getData(true);

        $this->assertSame('ready', $payload['status']);
        $this->assertEqualsWithDelta(100.0, $payload['grand_total_value'], 0.001);
        $this->assertSame('balanced', $payload['reconciliation']['status']);
        $this->assertLessThan(1.0, $elapsed);
        Queue::assertNothingPushed();
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

    public function test_recovery_period_options_only_include_dates_with_comparison_and_usable_ph(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-03-31'],
            ['periode' => '2026-04-15'],
            ['periode' => '2026-04-30'],
            ['periode' => '2026-05-10'],
            ['periode' => '2026-05-11'],
            ['periode' => '2026-06-01'],
        ]);
        DB::table('lw325_ph')->insert([
            ['periode' => '2026-04-14', 'acctno' => '649201027193100', 'pokok' => 100],
            ['periode' => '2026-05-10', 'acctno' => '649201027193101', 'pokok' => 100],
            ['periode' => '2026-06-01', 'acctno' => '6,49201E+14', 'pokok' => 100],
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

    public function test_requested_recovery_period_must_match_an_exact_processable_option(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-03-31'],
            ['periode' => '2026-04-15'],
            ['periode' => '2026-04-30'],
            ['periode' => '2026-05-10'],
        ]);
        DB::table('lw325_ph')->insert([
            ['periode' => '2026-04-15', 'acctno' => '649201027193100', 'pokok' => 100],
            ['periode' => '2026-05-10', 'acctno' => '649201027193101', 'pokok' => 100],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'resolveRecoveryReportPeriod');
        $method->setAccessible(true);

        $this->assertSame('2026-05-10', $method->invoke($controller, '2026-05-10'));
        $this->assertSame('2026-04-15', $method->invoke($controller, '2026-04-15'));
        $this->assertNull($method->invoke($controller, '2026-05-09'));
        $this->assertNull($method->invoke($controller, 'not-a-date'));
    }

    public function test_later_invalid_ph_does_not_fallback_to_an_earlier_valid_ph_period(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-06-30'],
            ['periode' => '2026-07-10'],
            ['periode' => '2026-07-11'],
        ]);
        DB::table('lw325_ph')->insert([
            ['periode' => '2026-07-10', 'acctno' => '649201027193100', 'pokok' => 100],
            ['periode' => '2026-07-11', 'acctno' => '6,49201E+14', 'pokok' => 100],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'fetchRecoveryReportPeriods');
        $method->setAccessible(true);
        $periods = $method->invoke($controller)->all();

        $this->assertContains('2026-07-10', $periods);
        $this->assertNotContains('2026-07-11', $periods);
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

    public function test_matrix_exit_metrics_use_previous_daily_loan_os_and_ph_as_membership(): void
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
                'tgl_ph' => '2026-05-15',
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
        $this->assertSame(25000, (int) $rows->get('D1:ph')->amount_cents);
        $this->assertSame(37500, $totalExitCents);
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
                'tgl_ph' => '2026-05-15',
                'pokok' => 250,
            ],
            [
                'periode' => '2026-06-29',
                'acctno' => '649201027193100',
                'kanca' => 'KC Old',
                'unit' => 'Unit Old',
                'segmen_dashboard' => 'Micro',
                'tgl_ph' => '2026-06-15',
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

        $this->assertSame(25000, (int) $rows->get('D1:ph')->amount_cents);
    }

    public function test_matrix_exit_ph_excludes_accounts_still_present_in_daily_loan(): void
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
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildMatrixExitMetricAggregateQuery');
        $method->setAccessible(true);

        $rows = $method->invoke($controller, '2026-06-20', '2026-05-31', [
            'segmen' => [],
            'produk' => [],
            'cabang' => [],
            'unit' => [],
        ], true, true)->get();

        $phRows = $rows->where('metric_type', 'ph');

        $this->assertSame(25000, (int) $phRows->sum('amount_cents'));
        $this->assertSame(['M'], $phRows->pluck('before_bucket')->sort()->values()->all());
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

        $this->assertSame(29950, (int) $rows->get('principal_reduction')->amount_cents);
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

    public function test_matrix_reconciles_daily_loan_movement_and_current_ph_membership_exactly(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-30',
                'nomor_rekening1' => 'A',
                'baki_debet1' => 1000.25,
                'kolek' => '1',
                'flag_restruk' => null,
                'umur_tunggakan' => 0,
                'tgl_realisasi' => null,
            ],
            [
                'periode' => '2026-06-30',
                'nomor_rekening1' => 'B',
                'baki_debet1' => 500.10,
                'kolek' => '1',
                'flag_restruk' => 'Y',
                'umur_tunggakan' => 0,
                'tgl_realisasi' => null,
            ],
            [
                'periode' => '2026-06-30',
                'nomor_rekening1' => 'C',
                'baki_debet1' => 250.05,
                'kolek' => '4',
                'flag_restruk' => null,
                'umur_tunggakan' => 120,
                'tgl_realisasi' => null,
            ],
            [
                'periode' => '2026-06-30',
                'nomor_rekening1' => 'D',
                'baki_debet1' => 100.05,
                'kolek' => '5',
                'flag_restruk' => null,
                'umur_tunggakan' => 365,
                'tgl_realisasi' => null,
            ],
            [
                'periode' => '2026-07-10',
                'nomor_rekening1' => 'A',
                'baki_debet1' => 800.10,
                'kolek' => '2',
                'flag_restruk' => null,
                'umur_tunggakan' => 20,
                'tgl_realisasi' => null,
            ],
            [
                'periode' => '2026-07-10',
                'nomor_rekening1' => 'B',
                'baki_debet1' => 700.25,
                'kolek' => '1',
                'flag_restruk' => null,
                'umur_tunggakan' => 0,
                'tgl_realisasi' => null,
            ],
            [
                'periode' => '2026-07-10',
                'nomor_rekening1' => 'E',
                'baki_debet1' => 997800.50,
                'kolek' => '1',
                'flag_restruk' => null,
                'umur_tunggakan' => 0,
                'tgl_realisasi' => '2026-07-05',
            ],
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-07-10',
            'acctno' => '000C',
            'tgl_ph' => '2026-07-05',
            'pokok' => 1,
        ]);

        $response = (new DashboardPinjamanReportController())->data(Request::create(
            '/report/dashboard-pinjaman/data',
            'GET',
            ['periode' => '2026-07-10', 'refresh' => '1']
        ));
        $payload = $response->getData(true);
        $rows = collect($payload['matrix_rows'])->keyBy('label');

        $this->assertSame('ready', $payload['status']);
        $this->assertEqualsWithDelta(999650.95, $payload['grand_total_value'], 0.001);
        $this->assertEqualsWithDelta(1000.25, $rows['L']['values'][2], 0.001);
        $this->assertEqualsWithDelta(500.10, $rows['LR']['values'][0], 0.001);
        $this->assertEqualsWithDelta(997800.50, $rows['New Account']['total'], 0.001);
        $this->assertEqualsWithDelta(250.05, $rows['D1']['total'], 0.001);
        $this->assertEqualsWithDelta(100.05, $rows['M']['total'], 0.001);
        $this->assertEqualsWithDelta(200.15, $rows['L']['metrics']['principal_reduction'], 0.001);
        $this->assertEqualsWithDelta(200.15, $rows['LR']['metrics']['suplesi'], 0.001);
        $this->assertEqualsWithDelta(997800.50, $rows['New Account']['metrics']['suplesi'], 0.001);
        $this->assertEqualsWithDelta(250.05, $rows['D1']['metrics']['ph'], 0.001);
        $this->assertEqualsWithDelta(100.05, $rows['M']['metrics']['lunas'], 0.001);
        $this->assertEqualsWithDelta(998000.65, $payload['grand_totals']['metrics']['suplesi'], 0.001);

        $reconciliation = $payload['reconciliation'];
        $this->assertSame('balanced', $reconciliation['status']);
        $this->assertSame(3, $reconciliation['matrix_accounts']);
        $this->assertSame(2, $reconciliation['matched_accounts']);
        $this->assertSame(1, $reconciliation['new_accounts']);
        $this->assertSame(2, $reconciliation['exit_accounts']);
        $this->assertSame(1, $reconciliation['ph_accounts']);
        $this->assertSame(1, $reconciliation['lunas_accounts']);
        $this->assertSame(1, $rows['D1']['metric_debtors']['ph']);
        $this->assertEqualsWithDelta(1850.45, $reconciliation['previous_position'], 0.001);
        $this->assertEqualsWithDelta(999300.85, $reconciliation['movement_grid_position'], 0.001);
        $this->assertEqualsWithDelta(999650.95, $reconciliation['matrix_position'], 0.001);
        $this->assertEqualsWithDelta(997800.50, $reconciliation['portfolio_inflow_position'], 0.001);
        $this->assertEqualsWithDelta(350.10, $reconciliation['exit_position'], 0.001);
        $this->assertEqualsWithDelta(1850.45, $reconciliation['basis_less_inflow_position'], 0.001);
        $this->assertEqualsWithDelta(
            $reconciliation['previous_position'],
            $payload['grand_total_value'] - $rows['New Account']['total'],
            0.001
        );
        $this->assertEqualsWithDelta(0.0, $reconciliation['basis_to_opening_difference'], 0.001);
        $this->assertEqualsWithDelta(0.0, $reconciliation['opening_partition_difference'], 0.001);
        $this->assertEqualsWithDelta(0.0, $reconciliation['closing_partition_difference'], 0.001);
        $this->assertEqualsWithDelta(0.0, $reconciliation['inflow_composition_difference'], 0.001);
        $this->assertEqualsWithDelta(0.0, $reconciliation['suplesi_composition_difference'], 0.001);
        $this->assertEqualsWithDelta(999300.85, $reconciliation['reconstructed_current_position'], 0.001);
        $this->assertEqualsWithDelta(0.0, $reconciliation['difference'], 0.001);
    }

    public function test_matrix_deduplicates_an_identical_full_period_without_doubling_pivot_or_detail(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-30',
                'nomor_rekening1' => 'DUP-FULL-1',
                'baki_debet1' => 100,
                'kolek' => '1',
                'umur_tunggakan' => 0,
                'cabang1' => 'KC Madiun',
            ],
            [
                'periode' => '2026-07-10',
                'nomor_rekening1' => 'DUP-FULL-1',
                'baki_debet1' => 100,
                'kolek' => '1',
                'umur_tunggakan' => 0,
                'cabang1' => 'KC Madiun',
            ],
            [
                'periode' => '2026-07-10',
                'nomor_rekening1' => 'DUP-FULL-1',
                'baki_debet1' => 100,
                'kolek' => '1',
                'umur_tunggakan' => 0,
                'cabang1' => 'KC Madiun',
            ],
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-07-10',
            'acctno' => '999000111222333',
            'pokok' => 0,
        ]);

        $controller = new DashboardPinjamanReportController();
        $payload = $controller->data(Request::create(
            '/report/dashboard-pinjaman/data',
            'GET',
            ['periode' => '2026-07-10', 'refresh' => '1']
        ))->getData(true);
        $detail = $controller->matrixDetail(Request::create(
            '/report/dashboard-pinjaman/matrix-pergeseran-kolek/detail',
            'GET',
            [
                'periode' => '2026-07-10',
                'before_bucket' => 'L',
                'after_bucket' => 'L',
            ]
        ))->getData(true);

        $this->assertSame('ready', $payload['status']);
        $this->assertEqualsWithDelta(100.0, $payload['grand_total_value'], 0.001);
        $this->assertSame(1, $payload['reconciliation']['matrix_accounts']);
        $this->assertSame(1, $payload['reconciliation']['matched_accounts']);
        $this->assertNull($payload['grand_totals']['metrics']['suplesi']);
        $this->assertCount(1, $detail['rows']);
        $this->assertEqualsWithDelta(100.0, (float) $detail['rows'][0]['baki_debet1'], 0.001);
    }

    public function test_matrix_rejects_missing_or_unusable_current_ph_source(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-30',
                'nomor_rekening1' => 'EXIT-1',
                'baki_debet1' => 100,
                'kolek' => '1',
                'umur_tunggakan' => 0,
            ],
            [
                'periode' => '2026-07-10',
                'nomor_rekening1' => 'ACTIVE-1',
                'baki_debet1' => 50,
                'kolek' => '1',
                'umur_tunggakan' => 0,
            ],
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-07-10',
            'acctno' => '649201027193100',
            'pokok' => 100,
        ]);

        $controller = new DashboardPinjamanReportController();
        $periodsMethod = new ReflectionMethod(DashboardPinjamanReportController::class, 'fetchRecoveryReportPeriods');
        $periodsMethod->setAccessible(true);
        $this->assertContains('2026-07-10', $periodsMethod->invoke($controller)->all());

        DB::table('lw325_ph')->delete();
        DB::table('lw325_ph')->insert([
            'periode' => '2026-07-10',
            'acctno' => '6,49201E+14',
            'pokok' => 100,
        ]);

        $response = $controller->data(Request::create(
            '/report/dashboard-pinjaman/data',
            'GET',
            ['periode' => '2026-07-10', 'refresh' => '1']
        ));
        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('error', $payload['status']);
        $this->assertNull($payload['ph_source']);
        $this->assertStringContainsString('Nominatif PH', $payload['message']);
    }

    public function test_historical_periods_use_their_own_comparison_and_same_month_ph(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2025-12-31', 'nomor_rekening1' => '1001', 'baki_debet1' => 100, 'kolek' => '1', 'umur_tunggakan' => 0],
            ['periode' => '2026-01-31', 'nomor_rekening1' => '1001', 'baki_debet1' => 90, 'kolek' => '1', 'umur_tunggakan' => 0],
            ['periode' => '2026-02-28', 'nomor_rekening1' => '1001', 'baki_debet1' => 80, 'kolek' => '2', 'umur_tunggakan' => 20],
        ]);
        DB::table('lw325_ph')->insert([
            ['periode' => '2026-01-31', 'acctno' => '9001', 'pokok' => 0],
            ['periode' => '2026-02-28', 'acctno' => '9002', 'pokok' => 0],
        ]);

        $controller = new DashboardPinjamanReportController();
        $january = $controller->data(Request::create(
            '/report/dashboard-pinjaman/data',
            'GET',
            ['periode' => '2026-01-31', 'refresh' => '1']
        ))->getData(true);
        $february = $controller->data(Request::create(
            '/report/dashboard-pinjaman/data',
            'GET',
            ['periode' => '2026-02-28', 'refresh' => '1']
        ))->getData(true);

        $this->assertSame(['2026-01-31', '2025-12-31', '2026-01-31'], [
            $january['selected_period'],
            $january['comparison_period'],
            $january['ph_period'],
        ]);
        $this->assertSame(['2026-02-28', '2026-01-31', '2026-02-28'], [
            $february['selected_period'],
            $february['comparison_period'],
            $february['ph_period'],
        ]);
        $this->assertSame('balanced', $january['reconciliation']['status']);
        $this->assertSame('balanced', $february['reconciliation']['status']);
    }

    public function test_matrix_does_not_double_count_status_five_account_still_present_in_daily_loan(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-30',
                'nomor_rekening1' => '123456789',
                'baki_debet1' => 100,
                'kolek' => '5',
                'umur_tunggakan' => 365,
                'status_rekening1' => null,
            ],
            [
                'periode' => '2026-07-10',
                'nomor_rekening1' => '123456789',
                'baki_debet1' => 80,
                'kolek' => '5',
                'umur_tunggakan' => 365,
                'status_rekening1' => '5',
            ],
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-07-10',
            'acctno' => '123456789',
            'pokok' => 80,
        ]);

        $response = (new DashboardPinjamanReportController())->data(Request::create(
            '/report/dashboard-pinjaman/data',
            'GET',
            ['periode' => '2026-07-10', 'refresh' => '1']
        ));
        $payload = $response->getData(true);

        $this->assertSame('ready', $payload['status']);
        $this->assertEqualsWithDelta(100.0, $payload['grand_total_value'], 0.001);
        $this->assertEqualsWithDelta(80.0, $payload['reconciliation']['current_position'], 0.001);
        $this->assertEqualsWithDelta(20.0, $payload['grand_totals']['metrics']['principal_reduction'], 0.001);
        $this->assertNull($payload['grand_totals']['metrics']['ph']);
        $this->assertSame(0, $payload['reconciliation']['ph_accounts']);
        $this->assertSame('balanced', $payload['reconciliation']['status']);
    }

    public function test_matrix_current_scope_still_finds_previous_bucket_after_unit_transfer(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-30',
                'nomor_rekening1' => 'MOVE-1',
                'baki_debet1' => 1000,
                'kolek' => '1',
                'umur_tunggakan' => 0,
                'cabang1' => 'KC Madiun',
                'unit1' => 'Unit Lama',
            ],
            [
                'periode' => '2026-07-10',
                'nomor_rekening1' => 'MOVE-1',
                'baki_debet1' => 900,
                'kolek' => '1',
                'umur_tunggakan' => 0,
                'cabang1' => 'KC Madiun',
                'unit1' => 'Unit Baru',
            ],
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-07-10',
            'acctno' => '999000111222333',
            'pokok' => 0,
        ]);

        $response = (new DashboardPinjamanReportController())->data(Request::create(
            '/report/dashboard-pinjaman/data',
            'GET',
            [
                'periode' => '2026-07-10',
                'cabang1' => ['KC Madiun'],
                'unit1' => ['Unit Baru'],
                'refresh' => '1',
            ]
        ));
        $rows = collect($response->getData(true)['matrix_rows'])->keyBy('label');

        $this->assertEqualsWithDelta(1000.0, $rows['L']['values'][0], 0.001);
        $this->assertNull($rows['New Account']['values'][0]);
        $this->assertEqualsWithDelta(100.0, $rows['L']['metrics']['principal_reduction'], 0.001);
    }

    public function test_matrix_drilldown_uses_same_account_grain_and_worst_bucket_as_pivot(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-30',
                'nomor_rekening1' => 'DUP-1',
                'baki_debet1' => 100,
                'kolek' => '1',
                'umur_tunggakan' => 0,
            ],
            [
                'periode' => '2026-07-10',
                'nomor_rekening1' => 'DUP-1',
                'baki_debet1' => 50,
                'kolek' => '1',
                'umur_tunggakan' => 0,
            ],
            [
                'periode' => '2026-07-10',
                'nomor_rekening1' => 'DUP-1',
                'baki_debet1' => 50,
                'kolek' => '4',
                'umur_tunggakan' => 120,
            ],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildMatrixDrilldownQuery');
        $method->setAccessible(true);
        $rows = $method->invoke(
            $controller,
            '2026-07-10',
            '2026-06-30',
            ['segmen' => [], 'produk' => [], 'cabang' => [], 'unit' => []],
            'L',
            [
                'pivot_before_bucket',
                'pivot_after_bucket',
                'pivot_previous_balance',
                'nomor_rekening1',
                'baki_debet1',
                'kolek',
            ],
            'D1'
        )->get();

        $this->assertCount(1, $rows);
        $this->assertSame('L', $rows[0]->pivot_before_bucket);
        $this->assertSame('D1', $rows[0]->pivot_after_bucket);
        $this->assertEqualsWithDelta(100.0, (float) $rows[0]->pivot_previous_balance, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $rows[0]->baki_debet1, 0.001);
    }

    public function test_matrix_keeps_distinct_anonymous_rows_consistent_between_pivot_and_detail(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-30',
                'uniqueid_namareport' => 'BASELINE-1',
                'nomor_rekening1' => 'BASELINE-1',
                'baki_debet1' => 10,
                'kolek' => '1',
                'umur_tunggakan' => 0,
            ],
            [
                'periode' => '2026-07-10',
                'uniqueid_namareport' => 'ANON-1',
                'nomor_rekening1' => null,
                'baki_debet1' => 100,
                'kolek' => '1',
                'umur_tunggakan' => 0,
            ],
            [
                'periode' => '2026-07-10',
                'uniqueid_namareport' => 'ANON-2',
                'nomor_rekening1' => null,
                'baki_debet1' => 100,
                'kolek' => '1',
                'umur_tunggakan' => 0,
            ],
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-07-10',
            'acctno' => '999000111222333',
            'pokok' => 0,
        ]);

        $controller = new DashboardPinjamanReportController();
        $payload = $controller->data(Request::create(
            '/report/dashboard-pinjaman/data',
            'GET',
            ['periode' => '2026-07-10', 'refresh' => '1']
        ))->getData(true);
        $detail = $controller->matrixDetail(Request::create(
            '/report/dashboard-pinjaman/matrix-pergeseran-kolek/detail',
            'GET',
            [
                'periode' => '2026-07-10',
                'before_bucket' => 'New Account',
                'after_bucket' => 'L',
                'limit' => 25,
            ]
        ))->getData(true);

        $rows = collect($payload['matrix_rows'])->keyBy('label');

        $this->assertSame('ready', $payload['status']);
        $this->assertEqualsWithDelta(200.0, $rows['New Account']['values'][0], 0.001);
        $this->assertSame(0, $payload['reconciliation']['new_accounts']);
        $this->assertSame(2, $payload['reconciliation']['portfolio_inflow_accounts']);
        $this->assertSame(2, $payload['reconciliation']['unverified_inflow_accounts']);
        $this->assertCount(2, $detail['rows']);
        $this->assertEqualsWithDelta(
            200.0,
            collect($detail['rows'])->sum(fn (array $row): float => (float) $row['baki_debet1']),
            0.001
        );
    }

    public function test_matrix_matches_canonical_account_keys_and_clamps_negative_exposure(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-06-30',
                'nomor_rekening1' => '000123',
                'baki_debet1' => 100,
                'kolek' => '1',
                'umur_tunggakan' => 0,
                'tgl_realisasi' => null,
            ],
            [
                'periode' => '2026-06-30',
                'nomor_rekening1' => 'NEG-EXIT',
                'baki_debet1' => -50,
                'kolek' => '1',
                'umur_tunggakan' => 0,
                'tgl_realisasi' => null,
            ],
            [
                'periode' => '2026-07-10',
                'nomor_rekening1' => '123',
                'baki_debet1' => 80,
                'kolek' => '1',
                'umur_tunggakan' => 0,
                'tgl_realisasi' => null,
            ],
            [
                'periode' => '2026-07-10',
                'nomor_rekening1' => 'NEW-NEGATIVE',
                'baki_debet1' => -25,
                'kolek' => '1',
                'umur_tunggakan' => 0,
                'tgl_realisasi' => '2026-07-05',
            ],
        ]);
        DB::table('lw325_ph')->insert([
            'periode' => '2026-07-10',
            'acctno' => 'PH-DUMMY',
            'tgl_ph' => '2026-07-10',
            'pokok' => 0,
        ]);

        $payload = (new DashboardPinjamanReportController())->data(Request::create(
            '/report/dashboard-pinjaman/data',
            'GET',
            ['periode' => '2026-07-10', 'refresh' => '1']
        ))->getData(true);
        $rows = collect($payload['matrix_rows'])->keyBy('label');

        $this->assertSame('ready', $payload['status']);
        $this->assertEqualsWithDelta(100.0, $rows['L']['values'][0], 0.001);
        $this->assertNull($rows['New Account']['values'][0]);
        $this->assertEqualsWithDelta(20.0, $rows['L']['metrics']['principal_reduction'], 0.001);
        $this->assertSame(1, $payload['reconciliation']['matched_accounts']);
        $this->assertSame(1, $payload['reconciliation']['new_accounts']);
        $this->assertSame('balanced', $payload['reconciliation']['status']);
        $this->assertEqualsWithDelta(80.0, $payload['reconciliation']['current_position'], 0.001);
    }

    public function test_matrix_ph_requires_m_bucket_or_current_period_ph_evidence(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-06-30', 'nomor_rekening1' => '100000000000001', 'cifno' => 'CIF-1', 'baki_debet1' => 100, 'kolek' => '4', 'umur_tunggakan' => 160],
            ['periode' => '2026-06-30', 'nomor_rekening1' => '100000000000002', 'cifno' => 'CIF-2', 'baki_debet1' => 200, 'kolek' => '4', 'umur_tunggakan' => 160],
            ['periode' => '2026-06-30', 'nomor_rekening1' => '100000000000003', 'cifno' => 'CIF-3', 'baki_debet1' => 300, 'kolek' => '5', 'umur_tunggakan' => 365],
        ]);
        DB::table('lw325_ph')->insert([
            ['periode' => '2026-06-30', 'acctno' => '100000000000001', 'tgl_ph' => '2026-05-10', 'pokok' => 90],
            ['periode' => '2026-06-30', 'acctno' => '100000000000003', 'tgl_ph' => '2026-05-10', 'pokok' => 250],
            ['periode' => '2026-07-10', 'acctno' => '100000000000001', 'tgl_ph' => '2026-05-10', 'pokok' => 80],
            ['periode' => '2026-07-10', 'acctno' => '100000000000002', 'tgl_ph' => '2026-07-05', 'pokok' => 175],
            ['periode' => '2026-07-10', 'acctno' => '100000000000003', 'tgl_ph' => '2026-05-10', 'pokok' => 225],
        ]);

        $controller = new DashboardPinjamanReportController();
        $resolveCurrentPh = new ReflectionMethod(DashboardPinjamanReportController::class, 'resolveCurrentMonthPhPeriod');
        $resolveCurrentPh->setAccessible(true);
        $hasUsablePh = new ReflectionMethod(DashboardPinjamanReportController::class, 'hasUsableLw325RecoveryPeriod');
        $hasUsablePh->setAccessible(true);
        $this->assertSame('2026-07-10', $resolveCurrentPh->invoke($controller, '2026-07-10'));
        $this->assertTrue($hasUsablePh->invoke($controller, '2026-07-10'));

        $exitRowsMethod = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildDailyLoanExitMetricQuery');
        $exitRowsMethod->setAccessible(true);
        $exitRows = $exitRowsMethod->invoke(
            $controller,
            '2026-07-10',
            '2026-06-30',
            ['segmen' => [], 'produk' => [], 'cabang' => [], 'unit' => []],
            false,
            false
        )->get();
        $this->assertCount(3, $exitRows, json_encode($exitRows->toArray()));
        $this->assertSame(
            ['D2:lunas', 'D2:ph', 'M:ph'],
            $exitRows
                ->map(fn ($row) => $row->before_bucket . ':' . $row->metric_type)
                ->sort()
                ->values()
                ->all(),
            json_encode($exitRows->toArray())
        );

        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildMatrixExitMetricAggregateQuery');
        $method->setAccessible(true);
        $aggregateQuery = $method->invoke(
            $controller,
            '2026-07-10',
            '2026-06-30',
            ['segmen' => [], 'produk' => [], 'cabang' => [], 'unit' => []],
            false,
            false
        );
        $rows = $aggregateQuery->get()->keyBy(fn ($row) => $row->before_bucket . ':' . $row->metric_type);

        $this->assertTrue($rows->has('D2:ph'), json_encode([
            'keys' => $rows->keys()->all(),
            'sql' => $aggregateQuery->toSql(),
            'bindings' => $aggregateQuery->getBindings(),
        ]));
        $this->assertSame(20000, (int) $rows['D2:ph']->amount_cents);
        $this->assertSame(10000, (int) $rows['D2:lunas']->amount_cents);
        $this->assertSame(30000, (int) $rows['M:ph']->amount_cents);
        $this->assertSame(1, (int) $rows['D2:ph']->debtor_count);
        $this->assertSame(1, (int) $rows['M:ph']->debtor_count);
    }

    public function test_matrix_does_not_use_partial_previous_ph_snapshot_as_new_membership_evidence(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'periode' => '2026-06-30',
            'nomor_rekening1' => '200000000000001',
            'cifno' => 'CIF-PARTIAL',
            'baki_debet1' => 100,
            'kolek' => '4',
            'umur_tunggakan' => 160,
        ]);

        $completePreviousDay = [];
        for ($index = 1; $index <= 10; $index++) {
            $completePreviousDay[] = [
                'periode' => '2026-06-29',
                'acctno' => '2999999999999' . $index,
                'tgl_ph' => '2026-05-10',
                'pokok' => 100,
            ];
        }
        DB::table('lw325_ph')->insert(array_merge($completePreviousDay, [
            [
                'periode' => '2026-06-30',
                'acctno' => '200000000000001',
                'tgl_ph' => '2026-05-10',
                'pokok' => 90,
            ],
            [
                'periode' => '2026-07-10',
                'acctno' => '200000000000001',
                'tgl_ph' => '2026-05-10',
                'pokok' => 80,
            ],
        ]));

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'buildMatrixExitMetricAggregateQuery');
        $method->setAccessible(true);
        $rows = $method->invoke(
            $controller,
            '2026-07-10',
            '2026-06-30',
            ['segmen' => [], 'produk' => [], 'cabang' => [], 'unit' => []],
            false,
            false
        )->get()->keyBy(fn ($row) => $row->before_bucket . ':' . $row->metric_type);

        $this->assertFalse($rows->has('D2:ph'));
        $this->assertSame(10000, (int) $rows['D2:lunas']->amount_cents);
    }

    public function test_recovery_period_options_reject_partial_lw325_daily_snapshot(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-06-30'],
            ['periode' => '2026-07-10'],
            ['periode' => '2026-07-11'],
        ]);

        $completeRows = [];
        for ($index = 1; $index <= 10; $index++) {
            $completeRows[] = [
                'periode' => '2026-07-10',
                'acctno' => 'FULL-' . $index,
                'tgl_ph' => '2026-07-10',
                'pokok' => 100,
            ];
        }
        $partialRows = [];
        for ($index = 1; $index <= 5; $index++) {
            $partialRows[] = [
                'periode' => '2026-07-11',
                'acctno' => 'PARTIAL-' . $index,
                'tgl_ph' => '2026-07-11',
                'pokok' => 100,
            ];
        }
        DB::table('lw325_ph')->insert(array_merge($completeRows, $partialRows));

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'fetchRecoveryReportPeriods');
        $method->setAccessible(true);
        $periods = $method->invoke($controller)->all();

        $this->assertContains('2026-07-10', $periods);
        $this->assertNotContains('2026-07-11', $periods);
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
