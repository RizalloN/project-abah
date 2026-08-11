<?php

namespace Tests\Unit;

use App\Support\DashboardPinjamanKreditService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardPinjamanKreditServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::dropAllTables();

        Schema::create('rka', function (Blueprint $table): void {
            $table->string('kanca')->nullable();
            $table->string('desc_uker')->nullable();
            $table->string('mata_anggaran')->nullable();
            $table->decimal('apr', 22, 2)->default(0);
            $table->decimal('may', 22, 2)->default(0);
            $table->decimal('dec', 22, 2)->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->date('snapshot_period')->nullable();
            $table->string('kanca_key')->nullable();
            $table->string('unit_key')->nullable();
            $table->string('kanca_label')->nullable();
            $table->string('unit_label')->nullable();

            foreach ($this->snapshotMetricColumns() as $column) {
                $table->decimal($column, 22, 2)->default(0);
            }
        });
    }

    public function test_sme_rka_uses_detail_rows_before_kanca_summary_fallback(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->snapshotRow('2026-05-15', 'KC Madiun', 500_000_000),
            $this->snapshotRow('2026-05-15', 'KC Magetan', 350_000_000),
        ]);

        DB::table('rka')->insert([
            [
                'kanca' => 'KC Madiun',
                'desc_uker' => '45-KC Madiun',
                'mata_anggaran' => 'B.2.a. Kredit Kecil Non Cash Collateral',
                'apr' => 900_000_000,
                'may' => 900_000_000,
                'dec' => 1_200_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'kanca' => 'KC Madiun',
                'desc_uker' => '552-KCP Caruban',
                'mata_anggaran' => 'B.2.a. Kredit Kecil Non Cash Collateral',
                'apr' => 100_000_000,
                'may' => 110_000_000,
                'dec' => 120_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'kanca' => 'KC Magetan',
                'desc_uker' => '49-KC Magetan',
                'mata_anggaran' => 'B.2.a. Kredit Kecil Non Cash Collateral',
                'apr' => 300_000_000,
                'may' => 310_000_000,
                'dec' => 330_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
        ]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-15', 'SME');
        $osRows = collect($payload['os']);

        $madiun = $osRows->first(fn (array $row): bool => ($row['branch'] ?? '') === 'KC Madiun' && ($row['category'] ?? '') === 'Kecil non Cashcoll');
        $magetan = $osRows->first(fn (array $row): bool => ($row['branch'] ?? '') === 'KC Magetan' && ($row['category'] ?? '') === 'Kecil non Cashcoll');

        $this->assertNotNull($madiun);
        $this->assertNotNull($magetan);
        $this->assertEqualsWithDelta(120_000_000, $madiun['rka_m1'], 0.01);
        $this->assertEqualsWithDelta(110_000_000, $madiun['rka_current'], 0.01);
        $this->assertEqualsWithDelta(330_000_000, $magetan['rka_m1'], 0.01);
        $this->assertEqualsWithDelta(310_000_000, $magetan['rka_current'], 0.01);
    }

    public function test_sme_sml_and_npl_rka_do_not_borrow_kanca_total_when_detail_exists(): void
    {
        $snapshot = $this->snapshotRow('2026-05-15', 'KC Madiun', 150_000_000);
        $snapshot['kecil_non_cashcoll_sml'] = 120_000_000;
        $snapshot['kecil_non_cashcoll_npl'] = 80_000_000;

        DB::table('dashboard_harian_snapshots')->insert([$snapshot]);

        DB::table('rka')->insert([
            [
                'kanca' => 'KC Madiun',
                'desc_uker' => '45-KC Madiun',
                'mata_anggaran' => 'DPK Rp Kecil Non Cash Collateral',
                'may' => 900_000_000,
                'dec' => 1_000_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'kanca' => 'KC Madiun',
                'desc_uker' => '552-KCP Caruban',
                'mata_anggaran' => 'DPK Rp Kecil Non Cash Collateral',
                'may' => 100_000_000,
                'dec' => 110_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'kanca' => 'KC Madiun',
                'desc_uker' => '45-KC Madiun',
                'mata_anggaran' => 'NPL Rp Kecil Non Cash Collateral',
                'may' => 800_000_000,
                'dec' => 900_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'kanca' => 'KC Madiun',
                'desc_uker' => '552-KCP Caruban',
                'mata_anggaran' => 'NPL Rp Kecil Non Cash Collateral',
                'may' => 70_000_000,
                'dec' => 75_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
        ]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-15', 'SME');
        $sml = collect($payload['sml'])->first(fn (array $row): bool => ($row['branch'] ?? '') === 'KC Madiun' && ($row['category'] ?? '') === 'Kecil non Cashcoll');
        $npl = collect($payload['npl'])->first(fn (array $row): bool => ($row['branch'] ?? '') === 'KC Madiun' && ($row['category'] ?? '') === 'Kecil non Cashcoll');

        $this->assertNotNull($sml);
        $this->assertNotNull($npl);
        $this->assertEqualsWithDelta(110_000_000, $sml['rka_m1'], 0.01);
        $this->assertEqualsWithDelta(100_000_000, $sml['rka_current'], 0.01);
        $this->assertEqualsWithDelta(75_000_000, $npl['rka_m1'], 0.01);
        $this->assertEqualsWithDelta(70_000_000, $npl['rka_current'], 0.01);
    }

    public function test_rka_labels_use_december_and_current_month(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->snapshotRow('2026-05-15', 'KC Madiun', 500_000_000),
        ]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-15', 'SME', 'KC Madiun');

        $this->assertSame('Des-26', $payload['rka_labels']['m1']);
        $this->assertSame('Mei-26', $payload['rka_labels']['current']);
    }

    public function test_sml_and_npl_rka_achievement_use_quality_metric_direction(): void
    {
        $snapshot = $this->snapshotRow('2026-05-15', 'KC Madiun', 150_000_000);
        $snapshot['kecil_non_cashcoll_sml'] = 120_000_000;
        $snapshot['kecil_non_cashcoll_npl'] = 80_000_000;

        DB::table('dashboard_harian_snapshots')->insert([$snapshot]);

        DB::table('rka')->insert([
            [
                'kanca' => 'KC Madiun',
                'desc_uker' => '552-KCP Caruban',
                'mata_anggaran' => 'B.2.a. Kredit Kecil Non Cash Collateral',
                'apr' => 90_000_000,
                'may' => 100_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'kanca' => 'KC Madiun',
                'desc_uker' => '552-KCP Caruban',
                'mata_anggaran' => 'DPK Rp Kecil Non Cash Collateral',
                'apr' => 90_000_000,
                'may' => 100_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'kanca' => 'KC Madiun',
                'desc_uker' => '552-KCP Caruban',
                'mata_anggaran' => 'NPL Rp Kecil Non Cash Collateral',
                'apr' => 90_000_000,
                'may' => 100_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
        ]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-15', 'SME', 'KC Madiun');

        $os = collect($payload['os'])->first(fn (array $row): bool => ($row['category'] ?? '') === 'Kecil non Cashcoll');
        $sml = collect($payload['sml'])->first(fn (array $row): bool => ($row['category'] ?? '') === 'Kecil non Cashcoll');
        $npl = collect($payload['npl'])->first(fn (array $row): bool => ($row['category'] ?? '') === 'Kecil non Cashcoll');

        $this->assertNotNull($os);
        $this->assertNotNull($sml);
        $this->assertNotNull($npl);

        $this->assertEqualsWithDelta(50_000_000, $os['penc_cur_rp'], 0.01);
        $this->assertEqualsWithDelta(150, $os['penc_cur_pct'], 0.01);

        $this->assertEqualsWithDelta(20_000_000, $sml['penc_cur_rp'], 0.01);
        $this->assertEqualsWithDelta(83.333333, $sml['penc_cur_pct'], 0.01);

        $this->assertEqualsWithDelta(-20_000_000, $npl['penc_cur_rp'], 0.01);
        $this->assertEqualsWithDelta(125, $npl['penc_cur_pct'], 0.01);
    }

    public function test_consumer_breakdown_includes_kkb_like_dashboard_harian(): void
    {
        $row = $this->snapshotRow('2026-05-15', 'KC Madiun', 0);
        $row['briguna_konsumer_os'] = 100_000_000;
        $row['kpr_os'] = 50_000_000;
        $row['kkb_os'] = 25_000_000;

        DB::table('dashboard_harian_snapshots')->insert([$row]);

        DB::table('rka')->insert([
            [
                'kanca' => 'KC Madiun',
                'desc_uker' => '552-KCP Caruban',
                'mata_anggaran' => 'B.5.c. KKB',
                'apr' => 20_000_000,
                'may' => 22_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
        ]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-15', 'Consumer', 'KC Madiun');
        $osRows = collect($payload['os']);
        $kkb = $osRows->first(fn (array $item): bool => ($item['category'] ?? '') === 'KKB');
        $total = $osRows->firstWhere('is_total', true);

        $this->assertNotNull($kkb);
        $this->assertEqualsWithDelta(25_000_000, $kkb['selected'], 0.01);
        $this->assertEqualsWithDelta(22_000_000, $kkb['rka_current'], 0.01);
        $this->assertEqualsWithDelta(175_000_000, $total['selected'], 0.01);
    }

    public function test_branch_scope_limits_kredit_totals_to_selected_kanca(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->snapshotRow('2026-05-15', 'KC Madiun', 500_000_000, 25_000_000),
            $this->snapshotRow('2026-05-15', 'KC Magetan', 350_000_000, 15_000_000),
        ]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-15', 'SME', 'KC Madiun');
        $osRows = collect($payload['os']);
        $total = $osRows->firstWhere('is_total', true);

        $this->assertCount(3, $payload['os']);
        $this->assertSame(['KC Madiun'], $osRows->where('is_total', null)->pluck('branch')->unique()->values()->all());
        $this->assertEqualsWithDelta(525_000_000, $total['selected'], 0.01);
    }

    public function test_selected_kanca_breaks_kredit_segment_down_to_kc_and_kcp_rows(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->snapshotRow('2026-05-15', 'KC Ponorogo', 1_500_000_000, 75_000_000),
            $this->snapshotRow('2026-05-15', 'KC Ponorogo', 500_000_000, 25_000_000, 'kc-ponorogo-detail', 'KC Ponorogo'),
            $this->snapshotRow('2026-05-15', 'KC Ponorogo', 1_000_000_000, 50_000_000, 'kcp-sudirman-ponorogo', 'KCP Sudirman Ponorogo'),
            $this->snapshotRow('2026-05-15', 'KC Ponorogo', 0, 0, 'unit-zero-ponorogo', 'UNIT Zero Ponorogo'),
            $this->snapshotRow('2026-05-15', 'KC Magetan', 350_000_000, 15_000_000),
        ]);
        DB::table('rka')->insert([
            [
                'kanca' => 'KC Ponorogo',
                'desc_uker' => '45-KC Ponorogo',
                'mata_anggaran' => 'B.2.a. Kredit Kecil Non Cash Collateral',
                'may' => 480_021_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'kanca' => 'KC Ponorogo',
                'desc_uker' => 'KC Ponorogo - KCP Sudirman Ponorogo',
                'mata_anggaran' => 'B.2.a. Kredit Kecil Non Cash Collateral',
                'may' => 98_344_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
        ]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-15', 'SME', 'KC Ponorogo');
        $osRows = collect($payload['os']);
        $branches = $osRows->where('is_total', null)->pluck('branch')->unique()->values()->all();
        $total = $osRows->firstWhere('is_total', true);
        $kcKecil = $osRows->first(fn (array $row): bool => ($row['branch'] ?? '') === 'KC Ponorogo' && ($row['category'] ?? '') === 'Kecil non Cashcoll');
        $kcpKecil = $osRows->first(fn (array $row): bool => ($row['branch'] ?? '') === 'KCP Sudirman Ponorogo' && ($row['category'] ?? '') === 'Kecil non Cashcoll');

        $this->assertSame(['KC Ponorogo', 'KCP Sudirman Ponorogo'], $branches);
        $this->assertEqualsWithDelta(1_575_000_000, $total['selected'], 0.01);
        $this->assertTrue($osRows->where('branch', 'KC Ponorogo')->every(fn (array $row): bool => ($row['scope_level'] ?? null) === 'unit'));
        $this->assertTrue($osRows->where('branch', 'KCP Sudirman Ponorogo')->every(fn (array $row): bool => ($row['scope_level'] ?? null) === 'unit'));
        $this->assertEqualsWithDelta(480_021_000, $kcKecil['rka_current'], 0.01);
        $this->assertEqualsWithDelta(98_344_000, $kcpKecil['rka_current'], 0.01);
        $this->assertNull($osRows->first(fn (array $row): bool => ($row['branch'] ?? '') === 'UNIT Zero Madiun'));
    }

    public function test_kredit_uses_kc_detail_scope_when_unit_label_matches_kanca_label(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->snapshotRow('2026-05-15', 'KC Madiun', 700_000_000, 20_000_000),
            $this->snapshotRow('2026-05-15', 'KC Madiun', 400_000_000, 10_000_000, 'kc-madiun-detail'),
        ]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-15', 'SME', 'KC Madiun');
        $branches = collect($payload['os'])->where('is_total', null)->pluck('branch')->unique()->values()->all();
        $total = collect($payload['os'])->firstWhere('is_total', true);

        $this->assertSame(['KC Madiun'], $branches);
        $this->assertEqualsWithDelta(410_000_000, $total['selected'], 0.01);
    }

    public function test_selected_kanca_breaks_consumer_segment_down_to_kc_and_kcp_rows(): void
    {
        $summary = $this->snapshotRow('2026-05-15', 'KC Ponorogo', 0);
        $summary['briguna_konsumer_os'] = 150_000_000;
        $summary['kpr_os'] = 50_000_000;
        $summary['kkb_os'] = 25_000_000;

        $kc = $this->snapshotRow('2026-05-15', 'KC Ponorogo', 0, 0, 'kc-ponorogo-detail', 'KC Ponorogo');
        $kc['briguna_konsumer_os'] = 100_000_000;
        $kc['kpr_os'] = 40_000_000;

        $kcp = $this->snapshotRow('2026-05-15', 'KC Ponorogo', 0, 0, 'kcp-sudirman-ponorogo', 'KCP Sudirman Ponorogo');
        $kcp['briguna_konsumer_os'] = 50_000_000;
        $kcp['kkb_os'] = 25_000_000;

        DB::table('dashboard_harian_snapshots')->insert([$summary, $kc, $kcp]);
        DB::table('rka')->insert([
            [
                'kanca' => 'KC Ponorogo',
                'desc_uker' => '45-KC Ponorogo',
                'mata_anggaran' => 'B.5.a. Briguna',
                'may' => 100_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'kanca' => 'KC Ponorogo',
                'desc_uker' => 'KC Ponorogo - KCP Sudirman Ponorogo',
                'mata_anggaran' => 'B.5.a. Briguna',
                'may' => 50_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
        ]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-15', 'Consumer', 'KC Ponorogo');
        $osRows = collect($payload['os']);
        $branches = $osRows->where('is_total', null)->pluck('branch')->unique()->values()->all();
        $total = $osRows->firstWhere('is_total', true);
        $kcBriguna = $osRows->first(fn (array $row): bool => ($row['branch'] ?? '') === 'KC Ponorogo' && ($row['category'] ?? '') === 'Briguna Konsumer');
        $kcpBriguna = $osRows->first(fn (array $row): bool => ($row['branch'] ?? '') === 'KCP Sudirman Ponorogo' && ($row['category'] ?? '') === 'Briguna Konsumer');

        $this->assertSame(['KC Ponorogo', 'KCP Sudirman Ponorogo'], $branches);
        $this->assertEqualsWithDelta(215_000_000, $total['selected'], 0.01);
        $this->assertEqualsWithDelta(100_000_000, $kcBriguna['rka_current'], 0.01);
        $this->assertEqualsWithDelta(50_000_000, $kcpBriguna['rka_current'], 0.01);
    }

    public function test_period_references_include_mom_and_previous_month_end_mtd(): void
    {
        $periods = app(DashboardPinjamanKreditService::class)->calculatePeriodReferences('2026-05-18');

        $this->assertSame('2025-12-31', $periods['ytd']);
        $this->assertSame('2026-03-31', $periods['m2']);
        $this->assertSame('2026-04-18', $periods['mtm']);
        $this->assertSame('2026-04-30', $periods['mtd']);
        $this->assertSame('2026-05-18', $periods['selected']);
    }

    public function test_month_end_hides_mom_and_uses_previous_month_end_as_its_baseline(): void
    {
        $service = app(DashboardPinjamanKreditService::class);
        $periods = $service->calculatePeriodReferences('2026-07-31');
        $payload = $service->getUnifiedSegmentData('2026-07-31', 'SME');

        $this->assertSame('2026-06-30', $periods['mtm']);
        $this->assertSame($periods['mtd'], $periods['mtm']);
        $this->assertFalse($payload['display_options']['show_mom']);
    }

    public function test_kredit_payload_uses_mtd_for_previous_month_end_delta(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->snapshotRow('2025-12-31', 'KC Madiun', 80_000_000),
            $this->snapshotRow('2026-03-31', 'KC Madiun', 60_000_000),
            $this->snapshotRow('2026-04-18', 'KC Madiun', 70_000_000),
            $this->snapshotRow('2026-04-30', 'KC Madiun', 90_000_000),
            $this->snapshotRow('2026-05-18', 'KC Madiun', 100_000_000),
        ]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-18', 'SME', 'KC Madiun');
        $row = collect($payload['os'])->first(fn (array $item): bool => ($item['category'] ?? '') === 'Kecil non Cashcoll');

        $this->assertEqualsWithDelta(70_000_000, $row['mtm'], 0.01);
        $this->assertEqualsWithDelta(90_000_000, $row['mtd'], 0.01);
        $this->assertEqualsWithDelta(30_000_000, $row['delta_mom'], 0.01);
        $this->assertEqualsWithDelta(10_000_000, $row['delta_mtd'], 0.01);
    }

    public function test_mikro_total_does_not_double_count_micro_parent_row(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->microSnapshotRow('2026-05-15', 'KC Madiun'),
        ]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-15', 'Mikro', 'KC Madiun');
        $total = collect($payload['os'])->firstWhere('is_total', true);

        $this->assertEqualsWithDelta(100_000_000, $total['selected'], 0.01);
    }

    public function test_selected_kanca_keeps_mikro_at_full_branch_summary_scope(): void
    {
        $summary = $this->microSnapshotRow('2026-05-15', 'KC Madiun');
        $unit = $this->microSnapshotRow('2026-05-15', 'KC Madiun', 'unit-balerejo', 'UNIT Balerejo');
        $unit['briguna_mikro_os'] = 1_000_000;
        $unit['kupedes_os'] = 2_000_000;
        $unit['kur_mikro_os'] = 3_000_000;
        $unit['kur_kecil_os'] = 4_000_000;
        $unit['kur_kpp_os'] = 5_000_000;

        DB::table('dashboard_harian_snapshots')->insert([$summary, $unit]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-15', 'Mikro', 'KC Madiun');
        $osRows = collect($payload['os']);
        $branches = $osRows->where('is_total', null)->pluck('branch')->unique()->values()->all();
        $micro = $osRows->first(fn (array $row): bool => ($row['category'] ?? '') === 'Micro');
        $total = $osRows->firstWhere('is_total', true);

        $this->assertSame(['KC Madiun'], $branches);
        $this->assertTrue($osRows->where('is_total', null)->every(fn (array $row): bool => ($row['scope_level'] ?? null) === 'kanca'));
        $this->assertEqualsWithDelta(100_000_000, $micro['selected'], 0.01);
        $this->assertEqualsWithDelta(100_000_000, $total['selected'], 0.01);
    }

    private function snapshotRow(string $period, string $branch, int $os, int $cashcollOs = 0, ?string $unitKey = null, ?string $unitLabel = null): array
    {
        $kancaKey = strtolower(str_replace(' ', '-', $branch));

        $row = [
            'snapshot_period' => $period,
            'kanca_key' => $kancaKey,
            'unit_key' => $unitKey ?? $kancaKey,
            'kanca_label' => $branch,
            'unit_label' => $unitLabel ?? $branch,
        ];

        foreach ($this->snapshotMetricColumns() as $column) {
            $row[$column] = 0;
        }

        $row['kecil_non_cashcoll_os'] = $os;
        $row['cashcoll_os'] = $cashcollOs;

        return $row;
    }

    private function microSnapshotRow(string $period, string $branch, ?string $unitKey = null, ?string $unitLabel = null): array
    {
        $row = $this->snapshotRow($period, $branch, 0, 0, $unitKey, $unitLabel);

        $row['micro_os'] = 100_000_000;
        $row['briguna_mikro_os'] = 10_000_000;
        $row['kupedes_os'] = 20_000_000;
        $row['kur_mikro_os'] = 30_000_000;
        $row['kur_kecil_os'] = 25_000_000;
        $row['kur_kpp_os'] = 15_000_000;

        return $row;
    }

    /**
     * @return array<int, string>
     */
    private function snapshotMetricColumns(): array
    {
        return [
            'kecil_non_cashcoll_os',
            'cashcoll_os',
            'kecil_non_cashcoll_sml',
            'cashcoll_sml',
            'kecil_non_cashcoll_npl',
            'cashcoll_npl',
            'briguna_konsumer_os',
            'briguna_konsumer_sml',
            'briguna_konsumer_npl',
            'kpr_os',
            'kpr_sml',
            'kpr_npl',
            'kkb_os',
            'kkb_sml',
            'kkb_npl',
            'micro_os',
            'briguna_mikro_os',
            'kupedes_os',
            'kur_mikro_os',
            'kur_kecil_os',
            'kur_kpp_os',
            'micro_sml',
            'briguna_mikro_sml',
            'kupedes_sml',
            'kur_mikro_sml',
            'kur_kecil_sml',
            'kur_kpp_sml',
            'micro_npl',
            'briguna_mikro_npl',
            'kupedes_npl',
            'kur_mikro_npl',
            'kur_kecil_npl',
            'kur_kpp_npl',
        ];
    }
}
