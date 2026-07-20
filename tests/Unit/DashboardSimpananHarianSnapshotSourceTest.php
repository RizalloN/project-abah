<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Models\User;
use App\Support\DashboardDanaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
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
            $table->decimal('sme_os', 20, 2)->nullable();
            $table->decimal('consumer_os', 20, 2)->nullable();
            $table->decimal('micro_os', 20, 2)->nullable();
            $table->decimal('total_sml_abs_non_commercial', 20, 2)->nullable();
            $table->decimal('sme_sml', 20, 2)->nullable();
            $table->decimal('consumer_sml', 20, 2)->nullable();
            $table->decimal('micro_sml', 20, 2)->nullable();
            $table->decimal('total_npl_abs_non_commercial', 20, 2)->nullable();
            $table->decimal('sme_npl', 20, 2)->nullable();
            $table->decimal('consumer_npl', 20, 2)->nullable();
            $table->decimal('micro_npl', 20, 2)->nullable();
            $table->decimal('kecil_non_cashcoll_os', 20, 2)->nullable();
            $table->decimal('cashcoll_os', 20, 2)->nullable();
            $table->decimal('kecil_non_cashcoll_sml', 20, 2)->nullable();
            $table->decimal('cashcoll_sml', 20, 2)->nullable();
            $table->decimal('kecil_non_cashcoll_npl', 20, 2)->nullable();
            $table->decimal('cashcoll_npl', 20, 2)->nullable();
            $table->decimal('briguna_konsumer_os', 20, 2)->nullable();
            $table->decimal('kpr_os', 20, 2)->nullable();
            $table->decimal('kkb_os', 20, 2)->nullable();
            $table->decimal('briguna_konsumer_sml', 20, 2)->nullable();
            $table->decimal('kpr_sml', 20, 2)->nullable();
            $table->decimal('kkb_sml', 20, 2)->nullable();
            $table->decimal('briguna_konsumer_npl', 20, 2)->nullable();
            $table->decimal('kpr_npl', 20, 2)->nullable();
            $table->decimal('kkb_npl', 20, 2)->nullable();
            $table->decimal('briguna_mikro_os', 20, 2)->nullable();
            $table->decimal('kupedes_os', 20, 2)->nullable();
            $table->decimal('kur_mikro_os', 20, 2)->nullable();
            $table->decimal('kur_kecil_os', 20, 2)->nullable();
            $table->decimal('kur_kpp_os', 20, 2)->nullable();
            $table->decimal('briguna_mikro_sml', 20, 2)->nullable();
            $table->decimal('kupedes_sml', 20, 2)->nullable();
            $table->decimal('kur_mikro_sml', 20, 2)->nullable();
            $table->decimal('kur_kecil_sml', 20, 2)->nullable();
            $table->decimal('kur_kpp_sml', 20, 2)->nullable();
            $table->decimal('briguna_mikro_npl', 20, 2)->nullable();
            $table->decimal('kupedes_npl', 20, 2)->nullable();
            $table->decimal('kur_mikro_npl', 20, 2)->nullable();
            $table->decimal('kur_kecil_npl', 20, 2)->nullable();
            $table->decimal('kur_kpp_npl', 20, 2)->nullable();
            $table->decimal('total_casa', 20, 2)->nullable();
            $table->decimal('rec_dh_total', 20, 2)->nullable();
            $table->integer('source_row_count')->nullable();
            $table->decimal('rec_dh_small', 20, 2)->nullable();
            $table->decimal('rec_dh_consumer', 20, 2)->nullable();
            $table->decimal('rec_dh_micro', 20, 2)->nullable();
            $table->decimal('simpanan_ritel', 20, 2)->nullable();
            $table->decimal('deposito_ritel', 20, 2)->nullable();
            $table->decimal('simpanan_mikro', 20, 2)->nullable();
            $table->decimal('simpanan_wholesale', 20, 2)->nullable();
            $table->decimal('casa_ritel', 20, 2)->nullable();
            $table->decimal('casa_mikro', 20, 2)->nullable();
            $table->decimal('commercial_os', 20, 2)->nullable();
            $table->decimal('medium_os', 20, 2)->nullable();
            $table->decimal('commercial_sml', 20, 2)->nullable();
            $table->decimal('medium_sml', 20, 2)->nullable();
            $table->decimal('commercial_npl', 20, 2)->nullable();
            $table->decimal('medium_npl', 20, 2)->nullable();
            $table->decimal('ph_tupok', 20, 2)->nullable();
            $table->decimal('ph_lunas', 20, 2)->nullable();
            $table->decimal('deposito_mikro', 20, 2)->nullable();
            $table->decimal('deposito_wholesale', 20, 2)->nullable();
            $table->decimal('kecil_os', 20, 2)->nullable();
            $table->decimal('kecil_sml', 20, 2)->nullable();
            $table->decimal('kecil_npl', 20, 2)->nullable();
            $table->decimal('total_sml_pct_non_commercial', 20, 2)->nullable();
            $table->decimal('total_npl_pct_non_commercial', 20, 2)->nullable();
            $table->integer('source_loan_row_count')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('hourly_dpk');
        Schema::create('hourly_dpk', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('posisi')->nullable();
            $table->string('mbname')->nullable();
            $table->string('brname')->nullable();
            $table->string('segmen')->nullable();
            $table->string('produk')->nullable();
            $table->decimal('saldo', 20, 2)->nullable();
        });

        $this->createSsaPinjamanTable();
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

    public function test_dashboard_dana_branch_scope_groups_rows_by_segment(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->summaryRow('2025-12-31', 'KC Madiun', 0, 0, 9, 0, [
                'giro_ritel' => 10,
                'tabungan_ritel' => 20,
                'deposito_ritel' => 30,
                'simpanan_ritel' => 60,
                'giro_mikro' => 1,
                'tabungan_mikro' => 2,
                'deposito_mikro' => 3,
                'simpanan_mikro' => 6,
                'giro_wholesale' => 4,
                'tabungan_wholesale' => 0,
                'deposito_wholesale' => 5,
                'simpanan_wholesale' => 9,
            ]),
            $this->summaryRow('2026-04-30', 'KC Madiun', 0, 0, 10, 0, [
                'giro_ritel' => 100,
                'tabungan_ritel' => 200,
                'deposito_ritel' => 300,
                'simpanan_ritel' => 600,
                'giro_mikro' => 10,
                'tabungan_mikro' => 20,
                'deposito_mikro' => 30,
                'simpanan_mikro' => 60,
                'giro_wholesale' => 40,
                'tabungan_wholesale' => 0,
                'deposito_wholesale' => 50,
                'simpanan_wholesale' => 90,
            ]),
            $this->summaryRow('2026-05-19', 'KC Madiun', 0, 0, 11, 0, [
                'giro_ritel' => 110,
                'tabungan_ritel' => 220,
                'deposito_ritel' => 330,
                'simpanan_ritel' => 660,
                'giro_mikro' => 11,
                'tabungan_mikro' => 22,
                'deposito_mikro' => 33,
                'simpanan_mikro' => 66,
                'giro_wholesale' => 44,
                'tabungan_wholesale' => 0,
                'deposito_wholesale' => 55,
                'simpanan_wholesale' => 99,
            ]),
        ]);
        DB::table('dashboard_harian_snapshots')->insert($this->summaryRow('2026-05-19', 'KC Magetan', 9_999, 0, 22, 0));

        $service = app(DashboardDanaService::class);
        $payload = $service->getDashboardData('2026-05-19', 'all', null, 'KC Madiun');
        $rows = collect($payload['rows']);

        $this->assertSame('branch', $payload['scope']);
        $this->assertSame('KC MADIUN', $payload['scope_label']);
        $this->assertSame(['RITEL', 'MIKRO', 'WHOLESALE'], $rows->where('is_total', true)->pluck('nama_cabang')->values()->all());
        $this->assertEqualsWithDelta(660, $rows->firstWhere('nama_cabang', 'RITEL')['selected'], 0.01);
        $this->assertEqualsWithDelta(66, $rows->firstWhere('nama_cabang', 'MIKRO')['selected'], 0.01);
        $this->assertEqualsWithDelta(99, $rows->firstWhere('nama_cabang', 'WHOLESALE')['selected'], 0.01);
        $this->assertEqualsWithDelta(825, $payload['total']['selected'], 0.01);
        $this->assertArrayHasKey('KC Madiun', $service->fetchBranches());
    }

    public function test_dashboard_dana_branch_ritel_scope_uses_exact_kc_and_kcp_units_with_their_own_rka(): void
    {
        $summary = $this->summaryRow('2026-05-19', 'KC Ponorogo', 7_260, 0, 3, 0, [
            'giro_ritel' => 1_210,
            'tabungan_ritel' => 2_420,
            'deposito_ritel' => 3_630,
            'simpanan_ritel' => 7_260,
        ]);
        $kc = $this->unitRow('2026-05-19', 'KC Ponorogo', 'KC Ponorogo', 660, 0, [
            'giro_ritel' => 110,
            'tabungan_ritel' => 220,
            'deposito_ritel' => 330,
            'simpanan_ritel' => 660,
        ]);
        $kc['unit_key'] = 'kc-ponorogo-detail';
        $kcp = $this->unitRow('2026-05-19', 'KC Ponorogo', 'KCP Sudirman Ponorogo', 66, 0, [
            'giro_ritel' => 11,
            'tabungan_ritel' => 22,
            'deposito_ritel' => 33,
            'simpanan_ritel' => 66,
        ]);
        $unit = $this->unitRow('2026-05-19', 'KC Ponorogo', 'UNIT Ngrayun Ponorogo', 6_534, 0, [
            'giro_ritel' => 1_089,
            'tabungan_ritel' => 2_178,
            'deposito_ritel' => 3_267,
            'simpanan_ritel' => 6_534,
        ]);

        DB::table('dashboard_harian_snapshots')->insert([$summary, $kc, $kcp, $unit]);
        DB::table('rka')
            ->where('tahun', 2026)
            ->where('kanca', 'KC Ponorogo')
            ->whereIn('mata_anggaran', [
                'Giro Retail Funding Total',
                'Tabungan Retail Funding Total',
                'Deposito Retail Funding Total',
            ])
            ->delete();
        DB::table('rka')->insert([
            $this->rkaRetailRow('rka-dana-ponorogo-kc-giro', '70-KC Ponorogo', 'Giro Retail Funding Total', 100),
            $this->rkaRetailRow('rka-dana-ponorogo-kc-tabungan', '70-KC Ponorogo', 'Tabungan Retail Funding Total', 200),
            $this->rkaRetailRow('rka-dana-ponorogo-kc-deposito', '70-KC Ponorogo', 'Deposito Retail Funding Total', 300),
            $this->rkaRetailRow('rka-dana-ponorogo-kcp-giro', '2204-KCP Sudirman Ponorogo', 'Giro Retail Funding Total', 10),
            $this->rkaRetailRow('rka-dana-ponorogo-kcp-tabungan', '2204-KCP Sudirman Ponorogo', 'Tabungan Retail Funding Total', 20),
            $this->rkaRetailRow('rka-dana-ponorogo-kcp-deposito', '2204-KCP Sudirman Ponorogo', 'Deposito Retail Funding Total', 30),
        ]);

        $payload = app(DashboardDanaService::class)->getDashboardData('2026-05-19', 'Ritel', '2026-05-01', 'KC Ponorogo');
        $rows = collect($payload['rows']);
        $totalRows = $rows->where('is_total', true)->values();
        $kcTotal = $totalRows->firstWhere('nama_cabang', 'KC PONOROGO');
        $kcpTotal = $totalRows->firstWhere('nama_cabang', 'KCP SUDIRMAN PONOROGO');

        $this->assertSame('branch', $payload['scope']);
        $this->assertSame('unit_kerja', $payload['scope_dimension']);
        $this->assertSame(['KC PONOROGO', 'KCP SUDIRMAN PONOROGO'], $totalRows->pluck('nama_cabang')->all());
        $this->assertEqualsWithDelta(660, $kcTotal['selected'], 0.01);
        $this->assertEqualsWithDelta(60, $kcTotal['rka_rp'], 0.01);
        $this->assertEqualsWithDelta(66, $kcpTotal['selected'], 0.01);
        $this->assertEqualsWithDelta(6, $kcpTotal['rka_rp'], 0.01);
        $this->assertEqualsWithDelta(726, $payload['total']['selected'], 0.01);
        $this->assertNull($totalRows->firstWhere('nama_cabang', 'UNIT NGRAYUN PONOROGO'));

        $allSegmentPayload = app(DashboardDanaService::class)->getDashboardData('2026-05-19', 'all', '2026-05-01', 'KC Ponorogo');
        $allSegmentTotalRows = collect($allSegmentPayload['rows'])->where('is_total', true)->values();

        $this->assertSame('unit_kerja_dan_segmen', $allSegmentPayload['scope_dimension']);
        $this->assertSame(
            ['KC PONOROGO', 'KCP SUDIRMAN PONOROGO', 'MIKRO', 'WHOLESALE'],
            $allSegmentTotalRows->pluck('nama_cabang')->all()
        );
        $this->assertNull($allSegmentTotalRows->firstWhere('nama_cabang', 'RITEL'));
        $this->assertEqualsWithDelta(726, $allSegmentPayload['total']['selected'], 0.01);
    }

    public function test_area6_portfolio_exposes_cabang_ritel_and_micro_scopes(): void
    {
        DB::table('dashboard_harian_snapshots')->insert($this->summaryRow('2026-05-19', 'KC Madiun', 1_000_000_000, 2_000_000_000, 10, 20, [
            'total_os_non_commercial' => 1_800_000_000,
            'total_sml_abs_non_commercial' => 45_000_000,
            'total_npl_abs_non_commercial' => 20_000_000,
            'sme_os' => 500_000_000,
            'consumer_os' => 600_000_000,
            'micro_os' => 700_000_000,
            'sme_sml' => 10_000_000,
            'sme_npl' => 5_000_000,
            'consumer_sml' => 15_000_000,
            'consumer_npl' => 5_000_000,
            'micro_sml' => 20_000_000,
            'micro_npl' => 10_000_000,
            'total_casa' => 400_000_000,
            'rec_dh_total' => 11_000_000,
        ]));
        DB::table('dashboard_harian_snapshots')->insert($this->summaryRow('2026-05-19', 'KC Magetan', 2_000_000_000, 3_500_000_000, 20, 35, [
            'total_os_non_commercial' => 3_000_000_000,
            'total_sml_abs_non_commercial' => 90_000_000,
            'total_npl_abs_non_commercial' => 40_000_000,
            'sme_os' => 900_000_000,
            'consumer_os' => 1_000_000_000,
            'micro_os' => 1_200_000_000,
            'sme_sml' => 20_000_000,
            'sme_npl' => 10_000_000,
            'consumer_sml' => 30_000_000,
            'consumer_npl' => 15_000_000,
            'micro_sml' => 40_000_000,
            'micro_npl' => 15_000_000,
            'total_casa' => 800_000_000,
            'rec_dh_total' => 22_000_000,
        ]));
        DB::table('dashboard_harian_snapshots')->insert($this->unitRow('2026-05-19', 'KC Madiun', 'KCP Caruban', 800_000_000, 1_600_000_000, [
            'total_os_non_commercial' => 1_400_000_000,
            'total_sml_abs_non_commercial' => 50_000_000,
            'total_npl_abs_non_commercial' => 20_000_000,
        ]));
        DB::table('dashboard_harian_snapshots')->insert($this->unitRow('2026-05-19', 'KC Madiun', 'UNIT A', 600_000_000, 900_000_000, [
            'total_os_non_commercial' => 800_000_000,
            'total_sml_abs_non_commercial' => 70_000_000,
            'total_npl_abs_non_commercial' => 25_000_000,
        ]));
        DB::table('dashboard_harian_snapshots')->insert($this->unitRow('2026-05-19', 'KC Magetan', 'UNIT B', 700_000_000, 1_300_000_000, [
            'total_os_non_commercial' => 1_100_000_000,
            'total_sml_abs_non_commercial' => 80_000_000,
            'total_npl_abs_non_commercial' => 35_000_000,
        ]));

        $controller = new DashboardSimpananController();

        $payload = $this->invokePrivate($controller, 'buildArea6PortfolioLandingFresh', [null]);

        $this->assertSame('area6', $payload['default_scope']);
        $this->assertArrayHasKey('area6', $payload['ranking_modes']);
        $this->assertArrayHasKey('sme', $payload['ranking_modes']);
        $this->assertArrayHasKey('consumer', $payload['ranking_modes']);
        $this->assertArrayHasKey('micro', $payload['ranking_modes']);
        $this->assertSame('KC Madiun', data_get($payload, 'ranking_modes.sme.branches.0.name'));
        $this->assertSame('Rp500,00 Jt', data_get($payload, 'ranking_modes.sme.branches.0.pinjaman_fmt'));
        $this->assertSame('KC Madiun', data_get($payload, 'ranking_modes.micro.branches.0.name'));
        $this->assertSame('Rp700,00 Jt', data_get($payload, 'ranking_modes.micro.branches.0.pinjaman_fmt'));

        $this->assertSame('4.800', data_get($payload, 'scopes.area6.cards.0.realization_value'));
        $this->assertSame('1.400', data_get($payload, 'scopes.sme.cards.0.realization_value'));
        $this->assertSame('1.900', data_get($payload, 'scopes.micro.cards.0.realization_value'));
    }

    public function test_area6_portfolio_segment_performance(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->summaryRow('2026-05-19', 'KC Madiun', 1_000_000_000, 2_000_000_000, 10, 20, [
                'kecil_non_cashcoll_os' => 12_000_000_000,
                'briguna_konsumer_os' => 18_000_000_000,
                'kupedes_os' => 24_000_000_000,
                'kecil_non_cashcoll_sml' => 1_000_000_000,
                'briguna_konsumer_sml' => 2_000_000_000,
                'kupedes_sml' => 3_000_000_000,
                'kecil_non_cashcoll_npl' => 500_000_000,
                'briguna_konsumer_npl' => 800_000_000,
                'kupedes_npl' => 1_200_000_000,
            ]),
        ]);

        $controller = new DashboardSimpananController();

        $payload = $this->invokePrivate($controller, 'buildArea6PortfolioLandingFresh', [null]);

        $this->assertArrayHasKey('segment_performance', $payload);
        $segPerf = $payload['segment_performance'];
        $this->assertArrayHasKey('segments', $segPerf);
        $this->assertCount(3, $segPerf['segments']);

        // Check SME
        $sme = collect($segPerf['segments'])->firstWhere('label', 'OS SME');
        $this->assertNotNull($sme);
        $this->assertSame('fas fa-briefcase', $sme['icon']);
        $this->assertSame('12.000', $sme['os']['realization_fmt']);
        $this->assertSame('1.000', $sme['sml']['realization_fmt']);
        $this->assertSame('500', $sme['npl']['realization_fmt']);

        // Check KONSUMER
        $consumer = collect($segPerf['segments'])->firstWhere('label', 'OS KONSUMER');
        $this->assertNotNull($consumer);
        $this->assertSame('fas fa-users', $consumer['icon']);
        $this->assertSame('18.000', $consumer['os']['realization_fmt']);
        $this->assertSame('2.000', $consumer['sml']['realization_fmt']);
        $this->assertSame('800', $consumer['npl']['realization_fmt']);

        // Check MIKRO
        $micro = collect($segPerf['segments'])->firstWhere('label', 'OS MIKRO');
        $this->assertNotNull($micro);
        $this->assertSame('fas fa-store', $micro['icon']);
        $this->assertSame('24.000', $micro['os']['realization_fmt']);
        $this->assertSame('3.000', $micro['sml']['realization_fmt']);
        $this->assertSame('1.200', $micro['npl']['realization_fmt']);

        // Check totals
        $this->assertSame('54.000', data_get($segPerf, 'total.os.realization_fmt'));
        $this->assertSame('6.000', data_get($segPerf, 'total.sml.realization_fmt'));
        $this->assertSame('2.500', data_get($segPerf, 'total.npl.realization_fmt'));
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

    public function test_daily_loan_period_is_resolved_inside_the_signed_in_branch(): void
    {
        $this->createDailyLoanTable();
        $this->actingAs(new User(['pn' => '0049']));

        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-05-16', 'cabang1' => 'KC Magetan', 'unit1' => 'UNIT MAGETAN'],
            ['periode' => '2026-05-17', 'cabang1' => 'KC Madiun', 'unit1' => 'UNIT MADIUN'],
            ['periode' => '2026-05-20', 'cabang1' => 'KC Ngawi', 'unit1' => 'UNIT NGAWI'],
        ]);

        $controller = new DashboardSimpananController();

        $resolved = $this->invokePrivate($controller, 'resolveArea6DailyLoanPeriod', ['2026-05-19']);

        $this->assertSame('2026-05-16', $resolved);
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

    public function test_kinerja_area6_cards_include_mom_delta(): void
    {
        Cache::flush();
        $service = app(\App\Support\DashboardHarianSnapshotService::class);
        foreach (['canUseSnapshotMetricsCache', 'sharedPeriodsRequestCache'] as $prop) {
            $ref = new \ReflectionProperty(\App\Support\DashboardHarianSnapshotService::class, $prop);
            $ref->setAccessible(true);
            $ref->setValue($service, null);
        }

        DB::table('dashboard_harian_snapshots')->insert([
            $this->summaryRow('2026-04-24', 'KC Madiun', 1_000_000_000, 2_000_000_000, 10, 20, [
                'total_os_non_commercial' => 1_500_000_000,
                'total_sml_abs_non_commercial' => 45_000_000,
                'total_npl_abs_non_commercial' => 20_000_000,
                'kecil_non_cashcoll_os' => 1_500_000_000,
                'kecil_non_cashcoll_sml' => 45_000_000,
                'kecil_non_cashcoll_npl' => 20_000_000,
            ]),
            $this->summaryRow('2026-05-24', 'KC Madiun', 1_000_000_000, 2_000_000_000, 10, 20, [
                'total_os_non_commercial' => 1_800_000_000,
                'total_sml_abs_non_commercial' => 50_000_000,
                'total_npl_abs_non_commercial' => 30_000_000,
                'kecil_non_cashcoll_os' => 1_800_000_000,
                'kecil_non_cashcoll_sml' => 50_000_000,
                'kecil_non_cashcoll_npl' => 30_000_000,
            ]),
        ]);

        $controller = new DashboardSimpananController();
        $payload = $this->invokePrivate($controller, 'buildArea6PortfolioLandingFresh', ['2026-05-24']);

        $osCard = collect($payload['cards'])->firstWhere('key', 'os');
        $smlCard = collect($payload['cards'])->firstWhere('key', 'sml');
        $nplCard = collect($payload['cards'])->firstWhere('key', 'npl');



        $this->assertNotNull($osCard);
        $this->assertArrayHasKey('mom', $osCard['deltas']);
        $this->assertSame('+300', $osCard['deltas']['mom']['value']);
        $this->assertSame('up', $osCard['deltas']['mom']['type']);
        $this->assertSame('green', $osCard['deltas']['mom']['color']);

        $this->assertNotNull($smlCard);
        $this->assertArrayHasKey('mom', $smlCard['deltas']);
        $this->assertSame('+5', $smlCard['deltas']['mom']['value']);
        
        $this->assertNotNull($nplCard);
        $this->assertArrayHasKey('mom', $nplCard['deltas']);
        $this->assertSame('+10', $nplCard['deltas']['mom']['value']);
    }

    public function test_presentation_payload_uses_dashboard_harian_snapshot_values(): void
    {
        $snapshotRows = [
            $this->summaryRow('2026-04-30', 'KC Madiun', 900_000_000, 1_800_000_000, 9, 18, [
                'total_sml_abs_non_commercial' => 40_000_000,
                'total_npl_abs_non_commercial' => 15_000_000,
            ]),
            $this->summaryRow('2026-04-30', 'KC Magetan', 1_800_000_000, 2_700_000_000, 18, 27, [
                'total_sml_abs_non_commercial' => 70_000_000,
                'total_npl_abs_non_commercial' => 35_000_000,
            ]),
            $this->summaryRow('2026-05-19', 'KC Madiun', 1_100_000_000, 2_100_000_000, 11, 21, [
                'total_sml_abs_non_commercial' => 50_000_000,
                'total_npl_abs_non_commercial' => 20_000_000,
                'kecil_non_cashcoll_os' => 600_000_000,
                'briguna_konsumer_os' => 700_000_000,
                'kupedes_os' => 800_000_000,
                'kecil_non_cashcoll_sml' => 10_000_000,
                'briguna_konsumer_sml' => 15_000_000,
                'kupedes_sml' => 25_000_000,
                'kecil_non_cashcoll_npl' => 5_000_000,
                'briguna_konsumer_npl' => 6_000_000,
                'kupedes_npl' => 9_000_000,
            ]),
            $this->summaryRow('2026-05-19', 'KC Magetan', 2_200_000_000, 3_300_000_000, 22, 33, [
                'total_sml_abs_non_commercial' => 100_000_000,
                'total_npl_abs_non_commercial' => 50_000_000,
                'kecil_non_cashcoll_os' => 900_000_000,
                'briguna_konsumer_os' => 1_100_000_000,
                'kupedes_os' => 1_300_000_000,
                'kecil_non_cashcoll_sml' => 20_000_000,
                'briguna_konsumer_sml' => 30_000_000,
                'kupedes_sml' => 50_000_000,
                'kecil_non_cashcoll_npl' => 10_000_000,
                'briguna_konsumer_npl' => 15_000_000,
                'kupedes_npl' => 25_000_000,
            ]),
        ];

        foreach ($snapshotRows as $row) {
            DB::table('dashboard_harian_snapshots')->insert($row);
        }

        $controller = new DashboardSimpananController();

        $payload = $this->invokePrivate($controller, 'buildPresentationPayload', ['2026-05-19']);
        $cards = collect($payload['summary']['cards'])->keyBy('key');
        $series = collect($payload['timeseries']['series'])->keyBy('key');

        $this->assertSame([
            'meta',
            'assets',
            'summary',
            'performance_overview',
            'timeseries',
            'cover_card_timeseries',
            'savings_breakdown',
            'loan_products',
            'financial_highlights',
            'executive_summary',
            'micro',
            'quality',
            'kts',
            'digital_strategy',
        ], array_keys($payload));
        $this->assertSame('Area 6 - Region Malang', $payload['meta']['title']);
        $this->assertSame('2026-05-19', $payload['meta']['period']);
        $this->assertTrue($cards->get('simpanan')['available']);
        $this->assertEqualsWithDelta(3_300_000_000, $cards->get('simpanan')['value_raw'], 0.01);
        $this->assertEqualsWithDelta(5_400_000_000, $cards->get('os')['value_raw'], 0.01);
        $this->assertEqualsWithDelta(150_000_000, $cards->get('sml')['value_raw'], 0.01);
        $this->assertEqualsWithDelta(70_000_000, $cards->get('npl')['value_raw'], 0.01);
        $this->assertTrue($payload['timeseries']['available']);
        $this->assertTrue($series->has('sml_nominal'));
        $this->assertContains(150, array_map('intval', $series->get('sml_nominal')['values']));
        $this->assertCount(8, $payload['digital_strategy']['cards']);
        $this->assertSame([], $payload['kts']['ritel']);
        $this->assertSame([], $payload['kts']['micro']);
    }

    public function test_presentation_payload_marks_empty_sources_without_dummy_numbers(): void
    {
        $controller = new DashboardSimpananController();

        $payload = $this->invokePrivate($controller, 'buildPresentationPayload', [null]);
        $cards = collect($payload['summary']['cards'])->keyBy('key');

        $this->assertFalse($cards->get('simpanan')['available']);
        $this->assertSame('Data belum tersedia', $cards->get('simpanan')['value']);
        $this->assertNull($cards->get('os')['value_raw']);
        $this->assertSame('Data belum tersedia', $cards->get('sml')['ratio']);
        $this->assertFalse($payload['timeseries']['available']);
        $this->assertSame([], $payload['kts']['ritel']);
        $this->assertSame([], $payload['kts']['micro']);
        $this->assertCount(8, $payload['digital_strategy']['cards']);
    }

    public function test_presentation_data_fresh_request_rebuilds_cached_payload(): void
    {
        DB::table('dashboard_harian_snapshots')->insert($this->summaryRow(
            '2026-05-19',
            'KC Madiun',
            1_000_000_000,
            2_000_000_000,
            10,
            20
        ));

        $controller = new DashboardSimpananController();
        $cachedResponse = $controller->presentationData(Request::create('/dashboard/presentation-data', 'GET', [
            'periode' => '2026-05-19',
        ]));
        $cachedPayload = $cachedResponse->getData(true);
        $cachedCards = collect($cachedPayload['summary']['cards'])->keyBy('key');

        $this->assertEqualsWithDelta(1_000_000_000, $cachedCards->get('simpanan')['value_raw'], 0.01);

        DB::table('dashboard_harian_snapshots')
            ->where('snapshot_period', '2026-05-19')
            ->update([
                'total_simpanan' => 1_750_000_000,
                'tabungan_ritel' => 1_750_000_000,
            ]);

        $freshResponse = $controller->presentationData(Request::create('/dashboard/presentation-data', 'GET', [
            'periode' => '2026-05-19',
            'fresh' => '1',
            '_ts' => 'test',
        ]));
        $freshPayload = $freshResponse->getData(true);
        $freshCards = collect($freshPayload['summary']['cards'])->keyBy('key');

        $this->assertEqualsWithDelta(1_750_000_000, $freshCards->get('simpanan')['value_raw'], 0.01);

        $refreshedResponse = $controller->presentationData(Request::create('/dashboard/presentation-data', 'GET', [
            'periode' => '2026-05-19',
        ]));
        $refreshedPayload = $refreshedResponse->getData(true);
        $refreshedCards = collect($refreshedPayload['summary']['cards'])->keyBy('key');

        $this->assertEqualsWithDelta(1_750_000_000, $refreshedCards->get('simpanan')['value_raw'], 0.01);
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

    private function rkaRetailRow(string $id, string $unit, string $mataAnggaran, int $may): array
    {
        return [
            'uniqueid_namareport' => $id,
            'tahun' => 2026,
            'kanca' => 'KC Ponorogo',
            'desc_uker' => $unit,
            'mata_anggaran' => $mataAnggaran,
            'may' => $may,
            'created_at' => now(),
            'updated_at' => now(),
        ];
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

    private function createSsaPinjamanTable(): void
    {
        Schema::dropIfExists('ssa_pinjaman');
        Schema::create('ssa_pinjaman', function (Blueprint $table): void {
            $table->id();
            $table->date('month_day_year_of_periode')->nullable();
            $table->string('nama_cabang')->nullable();
            $table->string('nama_uker')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
            $table->string('produk')->nullable();
            $table->string('segmen_lama')->nullable();
            $table->string('segmen_2025')->nullable();
            $table->decimal('baki_debet', 20, 2)->nullable();
            $table->unsignedTinyInteger('kolektabilitas_one_obligor')->nullable();
        });
    }

    private function invokePrivate(object $object, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }
}
