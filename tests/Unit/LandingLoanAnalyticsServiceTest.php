<?php

namespace Tests\Unit;

use App\Support\LandingLoanAnalyticsService;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LandingLoanAnalyticsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Schema::dropIfExists('daily_loan_dinamis');
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->date('periode');
            $table->decimal('baki_debet1', 20, 2)->default(0);
            $table->string('kolek')->nullable();
            $table->string('flag_restruk')->nullable();
            $table->string('segmen_kinerja')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->decimal('rate', 12, 8)->nullable();
            $table->unsignedInteger('restruk_ke1')->nullable();
            $table->string('cifno_clean')->nullable();
            $table->string('cifno')->nullable();
            $table->string('nomor_rekening1')->nullable();
        });

        Schema::dropIfExists('ssa_pinjaman');
        Schema::create('ssa_pinjaman', function (Blueprint $table): void {
            $table->id();
            $table->date('month_day_year_of_periode');
            $table->string('nama_cabang')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->decimal('baki_debet', 20, 2)->default(0);
            $table->unsignedTinyInteger('kolektabilitas_one_obligor')->nullable();
        });

        Schema::dropIfExists('performance_rm_cabang_snapshots');
        Schema::create('performance_rm_cabang_snapshots', function (Blueprint $table): void {
            $table->date('periode');
            $table->string('cabang');
            $table->string('segmen');
            $table->decimal('restruk_os', 20, 2)->default(0);
            $table->decimal('sml_os', 20, 2)->default(0);
            $table->decimal('npl_os', 20, 2)->default(0);
            $table->decimal('realisasi_os', 20, 2)->default(0);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('performance_rm_cabang_snapshots');
        Schema::dropIfExists('ssa_pinjaman');
        Schema::dropIfExists('daily_loan_dinamis');

        parent::tearDown();
    }

    public function test_quality_uses_exact_month_end_and_respects_locked_branch(): void
    {
        $this->insert('2026-01-30', 100_000_000, 1, 'Y', 'SMALL', 'KC MADIUN', 0.10);
        $this->insert('2026-01-31', 100_000_000, 1, 'Y', 'SMALL', 'KC MADIUN', 0.11);
        $this->insert('2026-01-31', 200_000_000, 2, 'N', 'SMALL', 'KC MADIUN', 0.12);
        $this->insert('2026-01-31', 300_000_000, 3, 'N', 'SMALL', 'KC MADIUN', 0.13);
        $this->insert('2026-01-31', 900_000_000, 2, 'N', 'SMALL', 'KC NGAWI', 0.14);
        $this->insert('2026-01-31', 50_000_000, 1, 'Y', 'CONSUMER', 'KC MADIUN', 0.10);
        $this->insert('2026-01-31', 75_000_000, 2, 'N', 'MICRO', 'KC MADIUN', 0.10);
        $this->insert('2026-02-28', 125_000_000, 1, 'Y', 'SMALL', 'KC MADIUN', 0.12);
        $this->insert('2026-08-25', 999_000_000, 3, 'N', 'SMALL', 'KC MADIUN', 0.15);

        $payload = app(LandingLoanAnalyticsService::class)->payload(
            '2026-08-28',
            UserBranchScope::forKey('madiun'),
            true
        );

        $this->assertTrue(data_get($payload, 'meta.available'));
        $this->assertSame('2026-08-25', data_get($payload, 'meta.period'));
        $this->assertSame(100.0, data_get($payload, 'quality.sme.series.lr.0'));
        $this->assertSame(200.0, data_get($payload, 'quality.sme.series.sml.0'));
        $this->assertSame(300.0, data_get($payload, 'quality.sme.series.npl.0'));
        $this->assertSame(600.0, data_get($payload, 'quality.sme.series.lar.0'));
        $this->assertSame(125.0, data_get($payload, 'quality.sme.series.lr.1'));
        $this->assertNull(data_get($payload, 'quality.sme.series.lar.7'));
        $this->assertSame(50.0, data_get($payload, 'quality.consumer.series.lr.0'));
        $this->assertSame(75.0, data_get($payload, 'quality.micro.series.sml.0'));
        $this->assertCount(12, data_get($payload, 'quality.sme.labels'));
    }

    public function test_tariff_compares_exact_h_minus_one_to_closed_month_end_only(): void
    {
        $this->insert('2026-08-25', 200_000_000, 1, 'N', 'SMALL', 'KC MADIUN', 0.15);
        foreach (range(1, 7) as $month) {
            $closing = now()->setDate(2026, $month, 1)->endOfMonth();
            $this->insertSsa($closing->copy()->subDay()->toDateString(), $month * 100_000_000, 'SMALL', '00045 -- KC Madiun (Konsolidasi-MB)');
            $this->insertSsa($closing->toDateString(), ($month * 100_000_000) + 25_000_000, 'SMALL', '00045 -- KC Madiun (Konsolidasi-MB)');
            $this->insertSsa($closing->toDateString(), 9_000_000_000, 'SMALL', '00057 -- KC Ngawi (Konsolidasi-MB)');
            $this->insertBranchRealization($closing->copy()->subDay()->toDateString(), $month * 10_000_000);
            $this->insertBranchRealization($closing->toDateString(), ($month * 10_000_000) + 5_000_000);
        }
        $this->insertSsa('2026-08-26', 900_000_000, 'SMALL', '00045 -- KC Madiun (Konsolidasi-MB)');
        $this->insertSsa('2026-07-31', 9_000_000_000, 'MICRO', '00045 -- KC Madiun (Konsolidasi-MB)');
        $this->insertSsa('2026-07-31', 9_000_000_000, 'SMALL', '00045 -- KC Madiun (Konsolidasi-MB)', 0);

        $payload = app(LandingLoanAnalyticsService::class)->payload(
            '2026-08-28',
            UserBranchScope::forKey('madiun'),
            true
        );

        $this->assertTrue(data_get($payload, 'tariff_relief.available'));
        $this->assertCount(7, data_get($payload, 'tariff_relief.points'));
        $this->assertSame('2026-01-30', data_get($payload, 'tariff_relief.points.0.previous_period'));
        $this->assertSame('2026-01-31', data_get($payload, 'tariff_relief.points.0.closing_period'));
        $this->assertEqualsWithDelta(100.0, data_get($payload, 'tariff_relief.points.0.previous_os'), 0.0001);
        $this->assertEqualsWithDelta(125.0, data_get($payload, 'tariff_relief.points.0.closing_os'), 0.0001);
        $this->assertEqualsWithDelta(25.0, data_get($payload, 'tariff_relief.points.0.delta_os'), 0.0001);
        $this->assertEqualsWithDelta(5.0, data_get($payload, 'tariff_relief.points.0.daily_realization'), 0.0001);
        $this->assertEqualsWithDelta(20.0, data_get($payload, 'tariff_relief.points.0.adjusted_delta_os'), 0.0001);
        $this->assertTrue(data_get($payload, 'tariff_relief.points.0.daily_realization_available'));
        $this->assertSame('2026-07-31', data_get($payload, 'tariff_relief.points.6.closing_period'));
        $this->assertSame('SSA Pinjaman', data_get($payload, 'tariff_relief.source'));
    }

    public function test_restructuring_frequency_uses_small_debtors_current_period_and_locked_branch(): void
    {
        $this->insertRestructuring('2026-08-27', 100_000_000, 1, 'CIF-A', 'ACC-01', 'N', 'SMALL', 'KC MADIUN');
        $this->insertRestructuring('2026-08-27', 50_000_000, 1, 'CIF-A', 'ACC-02', 'Y', 'SMALL', 'KC MADIUN');
        $this->insertRestructuring('2026-08-27', 200_000_000, 2, 'CIF-B', 'ACC-03', 'Y', 'SMALL', 'KC MADIUN');
        $this->insertRestructuring('2026-08-27', 25_000_000, 2, '', 'ACC-04', 'N', 'SMALL', 'KC MADIUN');
        $this->insertRestructuring('2026-08-27', 900_000_000, 1, 'CIF-C', 'ACC-05', 'Y', 'SMALL', 'KC NGAWI');
        $this->insertRestructuring('2026-08-27', 700_000_000, 1, 'CIF-D', 'ACC-06', 'Y', 'CONSUMER', 'KC MADIUN');
        $this->insertRestructuring('2026-08-26', 500_000_000, 3, 'CIF-E', 'ACC-07', 'Y', 'SMALL', 'KC MADIUN');

        $service = app(LandingLoanAnalyticsService::class);
        $branchPayload = $service->restructuringFrequency(
            '2026-08-28',
            UserBranchScope::forKey('madiun'),
            true
        );

        $this->assertTrue(data_get($branchPayload, 'available'));
        $this->assertSame('2026-08-27', data_get($branchPayload, 'period'));
        $this->assertSame(3, data_get($branchPayload, 'total_debtors'));
        $this->assertSame(4, data_get($branchPayload, 'total_accounts'));
        $this->assertEqualsWithDelta(375.0, data_get($branchPayload, 'total_os_juta'), 0.0001);
        $this->assertSame(1, data_get($branchPayload, 'buckets.0.debtors'));
        $this->assertSame(2, data_get($branchPayload, 'buckets.0.accounts'));
        $this->assertEqualsWithDelta(150.0, data_get($branchPayload, 'buckets.0.os_juta'), 0.0001);
        $this->assertSame(2, data_get($branchPayload, 'buckets.1.debtors'));
        $this->assertEqualsWithDelta(225.0, data_get($branchPayload, 'buckets.1.os_juta'), 0.0001);

        $areaPayload = $service->restructuringFrequency('2026-08-28', null, true);
        $this->assertSame(4, data_get($areaPayload, 'total_debtors'));
        $this->assertEqualsWithDelta(1_275.0, data_get($areaPayload, 'total_os_juta'), 0.0001);
    }

    public function test_decorator_attaches_segment_quality_and_sme_tariff_without_replacing_other_payload(): void
    {
        $this->insert('2026-01-30', 100_000_000, 1, 'N', 'SMALL', 'KC MADIUN', 0.10);
        $this->insert('2026-01-31', 100_000_000, 1, 'Y', 'SMALL', 'KC MADIUN', 0.11);
        $this->insertSsa('2026-01-30', 100_000_000, 'SMALL', '00045 -- KC Madiun (Konsolidasi-MB)');
        $this->insertSsa('2026-01-31', 110_000_000, 'SMALL', '00045 -- KC Madiun (Konsolidasi-MB)');

        $dashboard = [
            'area6_portfolio' => [
                'scopes' => [
                    'sme' => ['cards' => ['preserved']],
                    'consumer' => [],
                    'micro' => [],
                ],
            ],
        ];

        $decorated = app(LandingLoanAnalyticsService::class)->decorateDashboard(
            $dashboard,
            '2026-01-31',
            UserBranchScope::forKey('madiun')
        );

        $this->assertSame(['preserved'], data_get($decorated, 'area6_portfolio.scopes.sme.cards'));
        $this->assertSame(100.0, data_get($decorated, 'area6_portfolio.scopes.sme.quality_timeseries.series.lr.0'));
        $this->assertTrue(data_get($decorated, 'area6_portfolio.scopes.sme.tariff_relief.available'));
    }

    public function test_micro_timeseries_uses_micro_snapshot_and_respects_locked_branch(): void
    {
        $this->insert('2026-01-31', 999_000_000, 3, 'N', 'MICRO', 'KC MADIUN', 0.10);
        $this->insert('2026-08-22', 1_000_000, 1, 'N', 'MICRO', 'KC MADIUN', 0.10);
        $this->insert('2026-08-25', 2_000_000, 1, 'N', 'CONSUMER', 'KC MADIUN', 0.10);
        $this->insert('2026-08-26', 3_000_000, 1, 'N', 'MICRO', 'KC NGAWI', 0.10);

        DB::table('performance_rm_cabang_snapshots')->insert([
            [
                'periode' => '2026-01-31', 'cabang' => 'KC MADIUN', 'segmen' => 'MICRO',
                'restruk_os' => 100_000_000, 'sml_os' => 200_000_000, 'npl_os' => 300_000_000,
            ],
            [
                'periode' => '2026-01-31', 'cabang' => 'KC MADIUN', 'segmen' => 'SMALL',
                'restruk_os' => 9_000_000_000, 'sml_os' => 9_000_000_000, 'npl_os' => 9_000_000_000,
            ],
            [
                'periode' => '2026-01-31', 'cabang' => 'KC NGAWI', 'segmen' => 'MICRO',
                'restruk_os' => 8_000_000_000, 'sml_os' => 8_000_000_000, 'npl_os' => 8_000_000_000,
            ],
        ]);

        $payload = app(LandingLoanAnalyticsService::class)->payload(
            '2026-08-28',
            UserBranchScope::forKey('madiun'),
            true,
            'micro'
        );

        $this->assertSame('2026-08-22', data_get($payload, 'meta.period'));
        $this->assertSame(100.0, data_get($payload, 'quality.micro.series.lr.0'));
        $this->assertSame(200.0, data_get($payload, 'quality.micro.series.sml.0'));
        $this->assertSame(300.0, data_get($payload, 'quality.micro.series.npl.0'));
        $this->assertSame(600.0, data_get($payload, 'quality.micro.series.lar.0'));
    }

    private function insert(
        string $period,
        float $balance,
        int $collectibility,
        string $restructureFlag,
        string $segment,
        string $branch,
        float $rate
    ): void {
        DB::table('daily_loan_dinamis')->insert([
            'periode' => $period,
            'baki_debet1' => $balance,
            'kolek' => (string) $collectibility,
            'flag_restruk' => $restructureFlag,
            'segmen_kinerja' => $segment,
            'cabang_normalized' => $branch,
            'rate' => $rate,
        ]);
    }

    private function insertSsa(
        string $period,
        float $balance,
        string $segment,
        string $branch,
        int $collectibility = 1
    ): void {
        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => $period,
            'nama_cabang' => $branch,
            'segmen_dashboard' => $segment,
            'baki_debet' => $balance,
            'kolektabilitas_one_obligor' => $collectibility,
        ]);
    }

    private function insertRestructuring(
        string $period,
        float $balance,
        int $frequency,
        string $cif,
        string $account,
        string $restructureFlag,
        string $segment,
        string $branch
    ): void {
        DB::table('daily_loan_dinamis')->insert([
            'periode' => $period,
            'baki_debet1' => $balance,
            'kolek' => '1',
            'flag_restruk' => $restructureFlag,
            'segmen_kinerja' => $segment,
            'cabang_normalized' => $branch,
            'rate' => 0.1,
            'restruk_ke1' => $frequency,
            'cifno_clean' => $cif,
            'cifno' => $cif,
            'nomor_rekening1' => $account,
        ]);
    }

    private function insertBranchRealization(string $period, float $realization): void
    {
        DB::table('performance_rm_cabang_snapshots')->insert([
            'periode' => $period,
            'cabang' => 'KC MADIUN',
            'segmen' => 'SMALL',
            'restruk_os' => 0,
            'sml_os' => 0,
            'npl_os' => 0,
            'realisasi_os' => $realization,
        ]);
    }
}
