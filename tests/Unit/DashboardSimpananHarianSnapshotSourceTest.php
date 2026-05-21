<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Support\DashboardDanaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DashboardSimpananHarianSnapshotSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Schema::dropIfExists('dashboard_harian_snapshots');
        Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->date('snapshot_period')->nullable();
            $table->string('kanca_key')->nullable();
            $table->string('unit_key')->nullable();
            $table->string('kanca_label')->nullable();
            $table->string('unit_label')->nullable();
            $table->decimal('total_simpanan', 20, 2)->nullable();
            $table->decimal('tabungan_ritel', 20, 2)->nullable();
            $table->decimal('tabungan_mikro', 20, 2)->nullable();
            $table->decimal('tabungan_wholesale', 20, 2)->nullable();
            $table->decimal('giro_ritel', 20, 2)->nullable();
            $table->decimal('giro_mikro', 20, 2)->nullable();
            $table->decimal('giro_wholesale', 20, 2)->nullable();
            $table->integer('source_savings_row_count')->nullable();
            $table->decimal('total_os', 20, 2)->nullable();
            $table->decimal('total_os_non_commercial', 20, 2)->nullable();
            $table->decimal('total_sml_abs_non_commercial', 20, 2)->nullable();
            $table->decimal('total_npl_abs_non_commercial', 20, 2)->nullable();
            $table->decimal('total_casa', 20, 2)->nullable();
            $table->decimal('rec_dh_total', 20, 2)->nullable();
            $table->integer('source_loan_row_count')->nullable();
            $table->timestamps();
        });
    }

    public function test_landing_simpanan_and_pinjaman_use_harian_summary_rows(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->summaryRow('2026-04-30', 'KC Madiun', 1_000_000_000, 2_000_000_000, 10, 20),
            $this->summaryRow('2026-05-19', 'KC Madiun', 1_100_000_000, 2_100_000_000, 11, 21),
            $this->summaryRow('2026-05-19', 'KC Magetan', 2_200_000_000, 3_300_000_000, 22, 33),
            $this->unitRow('2026-05-19', 'KC Madiun', 'UNIT A', 99_000_000_000, 88_000_000_000),
        ]);

        $controller = new DashboardSimpananController();

        $periods = $this->invokePrivate($controller, 'resolveDashboardPeriods');
        $loanPeriods = $this->invokePrivate($controller, 'resolveLoanDashboardPeriods');
        $simpanan = $this->invokePrivate($controller, 'querySimpananSummaryFromHarianSnapshot', ['2026-05-19']);
        $pinjaman = $this->invokePrivate($controller, 'queryLoanSummaryFromHarianSnapshot', ['2026-05-19']);

        $this->assertSame(['2026-05-19', '2026-04-30', null], $periods);
        $this->assertSame(['2026-05-19', '2026-04-30', null], $loanPeriods);
        $this->assertSame('dashboard_harian_snapshots', $simpanan['source_table']);
        $this->assertEqualsWithDelta(3_300_000_000, $simpanan['total_balance'], 0.01);
        $this->assertSame(33, $simpanan['account_count']);
        $this->assertSame('dashboard_harian_snapshots', $pinjaman['source_table']);
        $this->assertEqualsWithDelta(5_400_000_000, $pinjaman['total_balance'], 0.01);
        $this->assertSame(54, $pinjaman['account_count']);
    }

    public function test_dashboard_dana_uses_dashboard_harian_summary_rows(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->summaryRow('2025-12-31', 'KC Madiun', 900_000_000, 0, 9, 0),
            $this->summaryRow('2025-12-31', 'KC Magetan', 1_900_000_000, 0, 19, 0),
            $this->summaryRow('2026-04-30', 'KC Madiun', 1_000_000_000, 0, 10, 0),
            $this->summaryRow('2026-04-30', 'KC Magetan', 2_000_000_000, 0, 20, 0),
            $this->summaryRow('2026-05-19', 'KC Madiun', 1_100_000_000, 0, 11, 0),
            $this->summaryRow('2026-05-19', 'KC Magetan', 2_200_000_000, 0, 22, 0),
            $this->unitRow('2026-05-19', 'KC Madiun', 'UNIT A', 99_000_000_000, 0),
        ]);

        $service = app(DashboardDanaService::class);
        $payload = $service->getDashboardData('2026-05-19', 'all');
        $madiunTotal = collect($payload['rows'])->first(
            fn (array $row): bool => ($row['nama_cabang'] ?? '') === 'KC MADIUN' && ($row['kategori'] ?? '') === 'TOTAL CABANG'
        );

        $this->assertSame('dashboard_harian_snapshots', $payload['source_table']);
        $this->assertEqualsWithDelta(3_300_000_000, $payload['total']['selected'], 0.01);
        $this->assertEqualsWithDelta(3_000_000_000, $payload['total']['mtd'], 0.01);
        $this->assertEqualsWithDelta(2_800_000_000, $payload['total']['ytd'], 0.01);
        $this->assertEqualsWithDelta(1_100_000_000, $madiunTotal['selected'], 0.01);
        $this->assertSame('2026-05-19', $service->fetchPeriods()->first());
        $this->assertSame(['Ritel', 'Mikro', 'Wholesale'], $service->fetchCategories());
    }

    public function test_area6_portfolio_exposes_retail_and_micro_ranking_modes(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->summaryRow('2026-05-19', 'KC Madiun', 1_000_000_000, 2_000_000_000, 10, 20, [
                'total_os_non_commercial' => 1_800_000_000,
                'total_sml_abs_non_commercial' => 45_000_000,
                'total_npl_abs_non_commercial' => 20_000_000,
                'total_casa' => 400_000_000,
                'rec_dh_total' => 11_000_000,
            ]),
            $this->summaryRow('2026-05-19', 'KC Magetan', 2_000_000_000, 3_500_000_000, 20, 35, [
                'total_os_non_commercial' => 3_000_000_000,
                'total_sml_abs_non_commercial' => 90_000_000,
                'total_npl_abs_non_commercial' => 40_000_000,
                'total_casa' => 800_000_000,
                'rec_dh_total' => 22_000_000,
            ]),
            $this->unitRow('2026-05-19', 'KC Madiun', 'KCP Caruban', 800_000_000, 1_600_000_000, [
                'total_os_non_commercial' => 1_400_000_000,
                'total_sml_abs_non_commercial' => 50_000_000,
                'total_npl_abs_non_commercial' => 20_000_000,
            ]),
            $this->unitRow('2026-05-19', 'KC Madiun', 'UNIT A', 600_000_000, 900_000_000, [
                'total_os_non_commercial' => 800_000_000,
                'total_sml_abs_non_commercial' => 70_000_000,
                'total_npl_abs_non_commercial' => 25_000_000,
            ]),
            $this->unitRow('2026-05-19', 'KC Magetan', 'UNIT B', 700_000_000, 1_300_000_000, [
                'total_os_non_commercial' => 1_100_000_000,
                'total_sml_abs_non_commercial' => 80_000_000,
                'total_npl_abs_non_commercial' => 35_000_000,
            ]),
        ]);

        $controller = new DashboardSimpananController();

        $payload = $this->invokePrivate($controller, 'buildArea6PortfolioLandingFresh', [null]);

        $this->assertSame('ritel', $payload['default_scope']);
        $this->assertArrayHasKey('ritel', $payload['ranking_modes']);
        $this->assertArrayHasKey('mikro', $payload['ranking_modes']);
        $this->assertSame('KCP Caruban', data_get($payload, 'ranking_modes.ritel.rankings.0.rows.0.label'));
        $this->assertSame('KC Madiun', data_get($payload, 'ranking_modes.ritel.rankings.0.rows.0.meta'));
        $this->assertSame('UNIT B', data_get($payload, 'ranking_modes.mikro.rankings.0.rows.0.label'));
        $this->assertSame('KC Magetan', data_get($payload, 'ranking_modes.mikro.rankings.0.rows.0.meta'));
        $this->assertSame($payload['ranking_modes']['ritel']['rankings'], $payload['rankings']);
    }

    public function test_area6_daily_loan_period_uses_latest_available_source_period(): void
    {
        $this->createDailyLoanTable();

        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-05-15', 'cabang1' => 'KC Madiun', 'unit1' => 'UNIT A'],
            ['periode' => '2026-05-17', 'cabang1' => 'KC Madiun', 'unit1' => 'UNIT A'],
            ['periode' => '2026-05-20', 'cabang1' => 'KC Surabaya', 'unit1' => 'UNIT X'],
        ]);

        $controller = new DashboardSimpananController();

        $resolved = $this->invokePrivate($controller, 'resolveArea6DailyLoanPeriod', ['2026-05-19']);

        $this->assertSame('2026-05-17', $resolved);
    }

    public function test_digital_landing_cards_prefer_available_snapshot_tables(): void
    {
        $this->createDigitalSnapshotTables();

        DB::table('rasio_casa_debitur_snapshots')->insert([
            [
                'uniqueid_rcds' => 'casa-1',
                'loan_period' => '2026-05-19',
                'casa_period' => '2026-05-19',
                'branch_label' => 'KC Madiun',
                'os_amount' => 1000,
                'casa_amount' => 250,
                'source_row_count' => 5,
            ],
        ]);
        DB::table('rekening_dormant_snapshots')->insert([
            [
                'uniqueid_rds' => 'dormant-1',
                'posisi' => '2026-05-19',
                'branch_label' => 'KC Madiun',
                'dormant_count' => 12,
            ],
        ]);
        DB::table('performance_new_payroll_snapshots')->insert([
            [
                'uniqueid_pnps' => 'payroll-1',
                'snapshot_posisi' => '2026-05-19',
                'branch' => 'KC Madiun',
                'rekening_curr' => 7,
                'rekening_prev' => 5,
                'saldo_curr' => 1000000,
                'saldo_prev' => 800000,
            ],
        ]);

        $controller = new DashboardSimpananController();

        $casa = $this->invokePrivate($controller, 'buildCasaDebiturKpiCardFromSnapshot');
        $dormant = $this->invokePrivate($controller, 'buildRekeningDormantKpiCardFromSnapshot');
        $payroll = $this->invokePrivate($controller, 'buildPayrollPerformanceCardFromSnapshot');

        $this->assertSame('rasio_casa_debitur_snapshots', $casa['detail_payload']['source_table']);
        $this->assertSame('25,0%', $casa['current_value']);
        $this->assertSame('rekening_dormant_snapshots', $dormant['detail_payload']['source_table']);
        $this->assertSame('12', $dormant['current_value']);
        $this->assertSame('performance_new_payroll_snapshots', $payroll['detail_payload']['source_table']);
        $this->assertSame('7', $payroll['current_value']);
    }

    private function summaryRow(string $period, string $branch, int $simpanan, int $pinjaman, int $savingsRows, int $loanRows, array $extra = []): array
    {
        $key = strtolower(str_replace(' ', '-', $branch));

        return array_merge([
            'snapshot_period' => $period,
            'kanca_key' => $key,
            'unit_key' => $key,
            'kanca_label' => $branch,
            'unit_label' => $branch,
            'total_simpanan' => $simpanan,
            'tabungan_ritel' => $simpanan,
            'source_savings_row_count' => $savingsRows,
            'total_os' => $pinjaman,
            'total_os_non_commercial' => $pinjaman,
            'total_sml_abs_non_commercial' => 0,
            'total_npl_abs_non_commercial' => 0,
            'total_casa' => 0,
            'rec_dh_total' => 0,
            'source_loan_row_count' => $loanRows,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra);
    }

    private function unitRow(string $period, string $branch, string $unit, int $simpanan, int $pinjaman, array $extra = []): array
    {
        $row = $this->summaryRow($period, $branch, $simpanan, $pinjaman, 999, 999, $extra);
        $row['unit_key'] = strtolower(str_replace(' ', '-', $unit));
        $row['unit_label'] = $unit;

        return $row;
    }

    private function createDigitalSnapshotTables(): void
    {
        Schema::dropIfExists('rasio_casa_debitur_snapshots');
        Schema::create('rasio_casa_debitur_snapshots', function (Blueprint $table): void {
            $table->string('uniqueid_rcds')->primary();
            $table->date('loan_period')->nullable();
            $table->date('casa_period')->nullable();
            $table->string('branch_label')->nullable();
            $table->decimal('os_amount', 20, 2)->default(0);
            $table->decimal('casa_amount', 20, 2)->default(0);
            $table->integer('source_row_count')->default(0);
        });

        Schema::dropIfExists('rekening_dormant_snapshots');
        Schema::create('rekening_dormant_snapshots', function (Blueprint $table): void {
            $table->string('uniqueid_rds')->primary();
            $table->date('posisi')->nullable();
            $table->string('branch_label')->nullable();
            $table->integer('dormant_count')->default(0);
        });

        Schema::dropIfExists('performance_new_payroll_snapshots');
        Schema::create('performance_new_payroll_snapshots', function (Blueprint $table): void {
            $table->string('uniqueid_pnps')->primary();
            $table->date('snapshot_posisi')->nullable();
            $table->string('branch')->nullable();
            $table->integer('rekening_curr')->default(0);
            $table->integer('rekening_prev')->default(0);
            $table->decimal('saldo_curr', 20, 2)->default(0);
            $table->decimal('saldo_prev', 20, 2)->default(0);
        });
    }

    private function createDailyLoanTable(): void
    {
        Schema::dropIfExists('daily_loan_dinamis');
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->date('periode')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
        });
    }

    private function invokePrivate(object $object, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }
}
