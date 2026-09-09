<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Support\LandingMicroPerformanceService;
use App\Support\ReportCacheVersion;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class LandingMicroPerformanceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Cache::flush();

        Schema::dropIfExists('dashboard_harian_snapshots');
        Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->date('snapshot_period');
            $table->string('kanca_key');
            $table->string('unit_key');
            $table->string('kanca_label');
            $table->string('unit_label');
            $table->decimal('micro_os', 20, 2)->default(0);
            $table->decimal('micro_sml', 20, 2)->default(0);
            $table->decimal('micro_npl', 20, 2)->default(0);
            $table->decimal('briguna_mikro_os', 20, 2)->default(0);
            $table->decimal('kupedes_os', 20, 2)->default(0);
            $table->decimal('kur_mikro_os', 20, 2)->default(0);
            $table->decimal('kur_kecil_os', 20, 2)->default(0);
            $table->decimal('kur_kpp_os', 20, 2)->default(0);
        });

        Schema::dropIfExists('daily_loan_dinamis');
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode');
            $table->date('tgl_realisasi')->nullable();
            $table->string('cifno')->nullable();
            $table->string('cifno_clean')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->decimal('baki_debet1', 20, 2)->default(0);
            $table->decimal('plafon', 20, 2)->default(0);
            $table->string('cabang1')->nullable();
            $table->string('kode_cabang1')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('unit1')->nullable();
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_dashboard')->nullable();
            $table->string('ln_type')->nullable();
            $table->string('nama_debitur1')->nullable();
            $table->string('jangka_waktu1')->nullable();
            $table->integer('freq_payment')->nullable();
            $table->string('pn_pemutus1')->nullable();
            $table->string('pn_pemutus_normalized')->nullable();
            $table->string('pn_pengelola1')->nullable();
            $table->string('pn_name1')->nullable();
            $table->string('kolek_detail')->nullable();
            $table->integer('umur_tunggakan')->nullable();
            $table->string('flag_restruk')->nullable();
            $table->integer('kolek')->nullable();
            $table->date('next_pmt_date')->nullable();
            $table->date('next_pmt_int_date')->nullable();
        });

        Schema::dropIfExists('performance_rm_snapshots');
        Schema::create('performance_rm_snapshots', function (Blueprint $table): void {
            $table->date('periode');
            $table->string('cabang');
            $table->string('unit')->nullable();
            $table->string('rm');
            $table->string('segmen');
            $table->decimal('realisasi_os', 20, 2)->default(0);
        });

        Schema::dropIfExists('ssa_pinjaman');
        Schema::create('ssa_pinjaman', function (Blueprint $table): void {
            $table->date('month_day_year_of_periode');
            $table->string('nama_cabang')->nullable();
            $table->string('nama_uker')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->integer('kolektabilitas_one_obligor')->nullable();
            $table->decimal('baki_debet', 20, 2)->default(0);
        });

        Schema::dropIfExists('loan_type');
        Schema::create('loan_type', function (Blueprint $table): void {
            $table->string('loan_type');
            $table->string('pola_pembayaran')->nullable();
        });

        Schema::dropIfExists('brihc');
        Schema::create('brihc', function (Blueprint $table): void {
            $table->string('pn')->nullable();
            $table->string('nama')->nullable();
            $table->string('jabatan')->nullable();
        });

        Schema::dropIfExists('brihc_pemasar');
        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            $table->string('pernr')->nullable();
            $table->string('pn_mantri')->nullable();
            $table->string('completename')->nullable();
            $table->string('esgdesc')->nullable();
            $table->string('positiondesc')->nullable();
            $table->string('orgdesc')->nullable();
            $table->string('psadesc')->nullable();
            $table->string('tmt_masuk')->nullable();
            $table->string('tmt_jabatan')->nullable();
        });

        Schema::dropIfExists('lw325_ph');
        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode');
            $table->string('acctno')->nullable();
            $table->string('kanca')->nullable();
            $table->string('unit')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->decimal('pokok', 20, 2)->default(0);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('lw325_ph');
        Schema::dropIfExists('brihc_pemasar');
        Schema::dropIfExists('brihc');
        Schema::dropIfExists('loan_type');
        Schema::dropIfExists('performance_rm_snapshots');
        Schema::dropIfExists('daily_loan_dinamis');
        Schema::dropIfExists('ssa_pinjaman');
        Schema::dropIfExists('dashboard_harian_snapshots');

        parent::tearDown();
    }

    public function test_net_realization_aggregates_explicit_account_rows_and_keeps_totals_reconciled(): void
    {
        $service = app(LandingMicroPerformanceService::class);
        $payload = $service->aggregateNetRealization([
            (object) [
                'cif_key' => 'CIF-NEW',
                'current_os' => 80_000_000,
                'previous_os' => 0,
                'branch' => 'KC Madiun',
                'product' => 'Kupedes',
                'payment_pattern' => 'BULANAN',
                'payment_frequency' => 1,
                'decision_role' => 'KA UNIT',
                'plafon' => 80_000_000,
            ],
            (object) [
                'cif_key' => 'CIF-SUPLESI-SAME-ACCOUNT',
                'current_os' => 50_000_000,
                'previous_os' => 20_000_000,
                'branch' => 'KC Madiun',
                'product' => 'KUR Mikro',
                'payment_pattern' => 'PERIODIK',
                'payment_frequency' => 3,
                'decision_role' => 'MBM',
                'plafon' => 90_000_000,
            ],
            (object) [
                'account_key' => 'REK-ONE-TIME',
                'cif_key' => 'CIF-SUPLESI-NEW-ACCOUNT',
                'current_os' => 330_000_000,
                'previous_os' => 250_000_000,
                'branch' => 'KC Ngawi',
                'product' => 'KUR Kecil',
                'payment_pattern' => 'MUSIMAN 1 X LUNAS',
                'payment_frequency' => 6,
                'loan_term' => '12M',
                'decision_role' => 'BOH',
                'plafon' => 300_000_000,
            ],
            (object) [
                'cif_key' => 'CIF-NO-GROWTH',
                'current_os' => 15_000_000,
                'previous_os' => 20_000_000,
                'branch' => 'KC Magetan',
                'product' => 'Kupedes',
                'payment_pattern' => 'BULANAN',
                'payment_frequency' => 1,
                'decision_role' => 'KA UNIT',
                'plafon' => 20_000_000,
            ],
            (object) [
                'cif_key' => 'CIF-KPP',
                'current_os' => 25_000_000,
                'previous_os' => 0,
                'branch' => 'KC Madiun',
                'product' => 'KPR',
                'payment_pattern' => 'BULANAN',
                'payment_frequency' => 1,
                'decision_role' => 'KA UNIT',
                'plafon' => 25_000_000,
            ],
        ]);

        $this->assertSame(4, data_get($payload, 'total.deb'));
        $this->assertSame(215_000_000.0, data_get($payload, 'total.amount'));
        $this->assertSame(2, data_get($payload, 'types.0.deb'));
        $this->assertSame(105_000_000.0, data_get($payload, 'types.0.amount'));
        $this->assertSame(2, data_get($payload, 'types.1.deb'));
        $this->assertSame(110_000_000.0, data_get($payload, 'types.1.amount'));
        $this->assertSame(
            215_000_000.0,
            collect(data_get($payload, 'products'))->sum('amount')
        );
        $this->assertSame(105_000_000.0, data_get($payload, 'patterns.1.amount'));
        $this->assertSame(110_000_000.0, data_get($payload, 'patterns.0.amount'));
        $musiman = collect(data_get($payload, 'patterns'))->firstWhere('key', 'musiman');
        $this->assertSame(80_000_000.0, data_get($musiman, 'details.satu_kali.amount'));
        $this->assertSame('12 Bulan', data_get($musiman, 'details.satu_kali.term_details.0.label'));
        $this->assertSame(1, data_get($musiman, 'details.satu_kali.term_details.0.customers'));
        $this->assertSame(330_000_000.0, data_get($musiman, 'details.satu_kali.term_details.0.os'));
        $this->assertSame(30_000_000.0, data_get($musiman, 'details.periodik.amount'));
        $this->assertSame(1, data_get($musiman, 'details.periodik.frequency_details.0.customers'));
        $this->assertSame(50_000_000.0, data_get($musiman, 'details.periodik.frequency_details.0.os'));
        $this->assertSame(0, data_get($musiman, 'details.lainnya.deb'));
        $this->assertSame(
            25_000_000.0,
            collect(data_get($payload, 'products'))->firstWhere('label', 'KUR KPP')['amount']
        );
        $this->assertNull(collect(data_get($payload, 'products'))->firstWhere('label', 'KPR'));
    }

    public function test_payment_pattern_fallback_recognizes_unmapped_frequency_as_periodic_and_monthly_gp_as_non_musiman(): void
    {
        $row = static fn (string $account, float $amount, string $pattern, int $frequency): object => (object) [
            'account_key' => $account,
            'current_os' => $amount,
            'previous_os' => 0.0,
            'branch' => 'KC Madiun',
            'product' => 'KUR Kecil',
            'payment_pattern' => $pattern,
            'payment_frequency' => $frequency,
            'decision_role' => 'KA UNIT',
            'plafon' => $amount,
        ];

        $payload = app(LandingMicroPerformanceService::class)->aggregateNetRealization([
            $row('RQ-PERIODIK', 150_000_000, '', 6),
            $row('RP-PERIODIK', 125_000_000, 'TIDAK TERPETAKAN', 4),
            $row('RO-PERIODIK', 150_000_000, '', 3),
            $row('GP-NON-MUSIMAN', 90_000_000, 'BULANAN DENGAN GP', 1),
        ]);

        $patterns = collect(data_get($payload, 'patterns'));
        $musiman = $patterns->firstWhere('key', 'musiman');
        $nonMusiman = $patterns->firstWhere('key', 'non_musiman');

        $this->assertSame(3, data_get($musiman, 'deb'));
        $this->assertSame(425_000_000.0, data_get($musiman, 'amount'));
        $this->assertSame(3, data_get($musiman, 'details.periodik.deb'));
        $this->assertSame(425_000_000.0, data_get($musiman, 'details.periodik.amount'));
        $this->assertSame(0, data_get($musiman, 'details.lainnya.deb'));
        $this->assertSame(1, data_get($nonMusiman, 'deb'));
        $this->assertSame(90_000_000.0, data_get($nonMusiman, 'amount'));
        $this->assertSame(515_000_000.0, (float) $patterns->sum('amount'));
    }

    public function test_periodic_frequency_breakdown_counts_unique_customers_and_uses_current_os(): void
    {
        $payload = app(LandingMicroPerformanceService::class)->aggregatePlafondRealization([
            (object) [
                'account_key' => 'REK-1', 'cif_key' => 'CIF-1', 'amount' => 50_000_000,
                'current_os' => 40_000_000, 'payment_pattern' => 'PERIODIK', 'payment_frequency' => 3,
                'branch' => 'KC Madiun', 'product' => 'Kupedes', 'decision_role' => 'MBM',
            ],
            (object) [
                'account_key' => 'REK-2', 'cif_key' => 'CIF-1', 'amount' => 35_000_000,
                'current_os' => 30_000_000, 'payment_pattern' => 'PERIODIK', 'payment_frequency' => 3,
                'branch' => 'KC Madiun', 'product' => 'Kupedes', 'decision_role' => 'MBM',
            ],
            (object) [
                'account_key' => 'REK-3', 'cif_key' => 'CIF-2', 'amount' => 25_000_000,
                'current_os' => 20_000_000, 'payment_pattern' => 'PERIODIK', 'payment_frequency' => 4,
                'branch' => 'KC Madiun', 'product' => 'Kupedes', 'decision_role' => 'MBM',
            ],
        ]);
        $frequencies = collect(data_get(
            collect(data_get($payload, 'patterns'))->firstWhere('key', 'musiman'),
            'details.periodik.frequency_details'
        ))->keyBy('frequency');

        $this->assertSame(1, data_get($frequencies, '3.customers'));
        $this->assertSame(2, data_get($frequencies, '3.deb'));
        $this->assertSame(70_000_000.0, data_get($frequencies, '3.os'));
        $this->assertSame(1, data_get($frequencies, '4.customers'));
        $this->assertSame(20_000_000.0, data_get($frequencies, '4.os'));
        $this->assertCount(2, $frequencies);
        $this->assertFalse($frequencies->has(12));
    }

    public function test_period_resolution_is_limited_to_micro_rows_in_the_active_branch(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->dailyLoanRow('micro-madiun', '2026-08-22', 'CIF-MICRO-MADIUN', 'REK-MICRO-MADIUN', 10, 'MICRO', 'Kupedes'),
            $this->dailyLoanRow('consumer-madiun', '2026-08-25', 'CIF-CONSUMER-MADIUN', 'REK-CONSUMER-MADIUN', 20, 'CONSUMER', 'KPR'),
            $this->dailyLoanRow('micro-ngawi', '2026-08-24', 'CIF-MICRO-NGAWI', 'REK-MICRO-NGAWI', 30, 'MICRO', 'Kupedes', null, [
                'cabang1' => 'KC Ngawi',
                'kode_cabang1' => '57',
                'cabang_normalized' => 'KC NGAWI',
                'unit1' => 'UNIT Ngawi',
            ]),
        ]);

        $service = app(LandingMicroPerformanceService::class);

        $this->assertSame(
            '2026-08-22',
            $this->invokePrivate($service, 'resolvePeriod', [null, UserBranchScope::forKey('madiun')])
        );
        $this->assertSame(
            '2026-08-24',
            $this->invokePrivate($service, 'resolvePeriod', [null, UserBranchScope::forKey('ngawi')])
        );
        $this->assertSame('2026-08-24', $this->invokePrivate($service, 'resolvePeriod', [null, null]));
    }

    public function test_pdwk_rows_keep_all_area_branches_and_split_requested_override_buckets(): void
    {
        $service = app(LandingMicroPerformanceService::class);
        $payload = $service->aggregateNetRealization([
            (object) [
                'current_os' => 220_000_000,
                'previous_os' => 100_000_000,
                'branch' => 'Madiun',
                'product' => 'Kupedes',
                'payment_pattern' => 'BULANAN',
                'decision_role' => 'BOH',
                'plafon' => 200_000_000,
            ],
            (object) [
                'current_os' => 190_000_000,
                'previous_os' => 100_000_000,
                'branch' => 'Madiun',
                'product' => 'KUR Mikro',
                'payment_pattern' => 'BULANAN',
                'decision_role' => 'MBM',
                'plafon' => 90_000_000,
            ],
        ]);

        $boh = collect(data_get($payload, 'decisions'))->firstWhere('key', 'boh');
        $mbm = collect(data_get($payload, 'decisions'))->firstWhere('key', 'mbm');

        $this->assertCount(4, $boh['branches']);
        $this->assertSame(
            ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'],
            array_column($boh['branches'], 'branch')
        );
        $this->assertSame(1, $boh['branches'][0]['override_deb']);
        $this->assertSame(120_000_000.0, $boh['branches'][0]['override_amount']);
        $this->assertSame(1, $mbm['branches'][0]['override_deb']);
        $this->assertSame(90_000_000.0, $mbm['branches'][0]['override_amount']);

        $branchPayload = $service->aggregateNetRealization([], UserBranchScope::forKey('ngawi'));
        $this->assertCount(1, data_get($branchPayload, 'decisions.0.branches'));
        $this->assertSame('KC NGAWI', data_get($branchPayload, 'decisions.0.branches.0.branch'));
    }

    public function test_landing_decision_summary_reuses_pgs_resolver_and_marks_every_out_of_band_authority_as_override(): void
    {
        DB::table('brihc')->insert([
            ['pn' => '90001', 'nama' => 'BOH Uji', 'jabatan' => 'BOH'],
            ['pn' => '90002', 'nama' => 'MBM Uji', 'jabatan' => 'MBM'],
            ['pn' => '90003', 'nama' => 'Ka Unit Uji', 'jabatan' => 'KAUNIT'],
        ]);
        DB::table('daily_loan_dinamis')->insert([
            $this->dailyLoanRow('boh-pdwk', '2026-08-25', 'C1', 'R1', 300_000_000, 'MICRO', 'KUR Mikro', '2026-08-10', ['pn_pemutus1' => '90001 - BOH Uji', 'pn_pemutus_normalized' => '90001']),
            $this->dailyLoanRow('boh-override', '2026-08-25', 'C2', 'R2', 200_000_000, 'MICRO', 'KUR Kecil', '2026-08-11', ['pn_pemutus1' => '90001 - BOH Uji', 'pn_pemutus_normalized' => '90001']),
            $this->dailyLoanRow('mbm-pdwk', '2026-08-25', 'C3', 'R3', 150_000_000, 'MICRO', 'KUR Kecil', '2026-08-12', ['pn_pemutus1' => '90002 - MBM Uji', 'pn_pemutus_normalized' => '90002']),
            $this->dailyLoanRow('mbm-override', '2026-08-25', 'C4', 'R4', 80_000_000, 'MICRO', 'Kupedes', '2026-08-13', ['pn_pemutus1' => '90002 - MBM Uji', 'pn_pemutus_normalized' => '90002']),
            $this->dailyLoanRow('ka-pdwk', '2026-08-25', 'C5', 'R5', 80_000_000, 'MICRO', 'Kupedes', '2026-08-14', ['pn_pemutus1' => '90003 - Ka Unit Uji', 'pn_pemutus_normalized' => '90003']),
            $this->dailyLoanRow('ka-override', '2026-08-25', 'C6', 'R6', 150_000_000, 'MICRO', 'KUR Kecil', '2026-08-15', ['pn_pemutus1' => '90003 - Ka Unit Uji', 'pn_pemutus_normalized' => '90003']),
        ]);

        $payload = app(LandingMicroPerformanceService::class)->decisionSummary('2026-08-25', null, true);
        $decisions = collect(data_get($payload, 'decisions'))->keyBy('key');

        $this->assertTrue(data_get($payload, 'available'));
        $this->assertSame(6, data_get($payload, 'total.deb'));
        $this->assertSame(960_000_000.0, data_get($payload, 'total.amount'));
        $this->assertSame(1, data_get($decisions, 'boh.branches.0.primary_deb'));
        $this->assertSame(1, data_get($decisions, 'boh.branches.0.override_deb'));
        $this->assertSame(1, data_get($decisions, 'mbm.branches.0.primary_deb'));
        $this->assertSame(1, data_get($decisions, 'mbm.branches.0.override_deb'));
        $this->assertSame(1, data_get($decisions, 'ka_unit.branches.0.primary_deb'));
        $this->assertSame(1, data_get($decisions, 'ka_unit.branches.0.override_deb'));
    }

    public function test_area_segment_realization_uses_mtd_plafond_deduplicated_by_account_for_all_segments(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->dailyLoanRow('small-main', '2026-08-25', 'C1', 'R-SMALL', 150_000_000, 'SMALL', 'Kredit Small', '2026-08-10'),
            $this->dailyLoanRow('small-duplicate', '2026-08-25', 'C1', 'R-SMALL', 140_000_000, 'SMALL', 'Kredit Small', '2026-08-10'),
            $this->dailyLoanRow('consumer-main', '2026-08-25', 'C2', 'R-CONSUMER', 200_000_000, 'CONSUMER', 'KPR', '2026-08-11'),
            $this->dailyLoanRow('micro-main', '2026-08-25', 'C3', 'R-MICRO', 100_000_000, 'MICRO', 'Kupedes', '2026-08-12'),
            $this->dailyLoanRow('outside-month', '2026-08-25', 'C4', 'R-OLD', 500_000_000, 'MICRO', 'Kupedes', '2026-07-31'),
        ]);

        $controller = app(DashboardSimpananController::class);
        $payload = $this->invokePrivate($controller, 'buildLandingSegmentRealizationSummary', ['2026-08-25']);
        $segments = collect(data_get($payload, 'scopes.area6.segments'))->keyBy('key');

        $this->assertTrue(data_get($payload, 'available'));
        $this->assertSame(3, data_get($payload, 'total_deb'));
        $this->assertSame(450_000_000.0, data_get($payload, 'total_nominal'));
        $this->assertSame(150_000_000.0, data_get($segments, 'sme.nominal'));
        $this->assertSame(200_000_000.0, data_get($segments, 'consumer.nominal'));
        $this->assertSame(100_000_000.0, data_get($segments, 'micro.nominal'));
        $this->assertSame(1, data_get($segments, 'sme.deb'));
        $this->assertSame('Daily Loan Dinamis - plafon realisasi MTD terdeduplikasi per rekening', data_get($payload, 'source'));
        $this->assertArrayNotHasKey('target', $segments['sme']);
    }

    public function test_daily_query_uses_canonical_micro_segment_and_normalises_kpp_without_leaking_consumer_kpr(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->dailyLoanRow('prev-kpp', '2026-07-31', 'CIF-KPP', 'REK-KPP-LAMA', 10_000_000, 'MICRO', 'KPR'),
            $this->dailyLoanRow('curr-kpp', '2026-08-22', 'CIF-KPP', 'REK-KPP-BARU', 40_000_000, 'MICRO', 'KPR', '2026-08-10'),
            $this->dailyLoanRow('curr-kpp-duplicate', '2026-08-22', 'CIF-KPP', 'REK-KPP-BARU', 35_000_000, 'MICRO', 'KPR', '2026-08-10'),
            $this->dailyLoanRow('curr-cashcoll', '2026-08-22', 'CIF-CASH', 'REK-CASH', 12_000_000, 'MICRO', 'Cash Collateral', '2026-08-11'),
            $this->dailyLoanRow('curr-consumer-kpr', '2026-08-22', 'CIF-CONSUMER', 'REK-CONSUMER', 100_000_000, 'CONSUMER', 'KPR', '2026-08-12'),
        ]);

        $service = app(LandingMicroPerformanceService::class);
        $rows = $this->invokePrivate($service, 'fetchNetRealizationRows', ['2026-08-22', '2026-07-31', null]);
        $payload = $service->aggregateNetRealization($rows);

        $this->assertSame(2, data_get($payload, 'total.deb'));
        $this->assertSame(42_000_000.0, data_get($payload, 'total.amount'));
        $this->assertSame(
            30_000_000.0,
            collect(data_get($payload, 'products'))->firstWhere('label', 'KUR KPP')['amount']
        );
        $this->assertSame(
            12_000_000.0,
            collect(data_get($payload, 'products'))->firstWhere('label', 'Cash Collateral')['amount']
        );
        $this->assertNull(collect(data_get($payload, 'products'))->firstWhere('label', 'KPR'));
    }

    public function test_plafond_realization_counts_accounts_and_resolves_pdwk_from_override_then_brihc(): void
    {
        DB::table('loan_type')->insert([
            ['loan_type' => 'LT-BULANAN', 'pola_pembayaran' => 'BULANAN'],
            ['loan_type' => 'LT-MUSIMAN', 'pola_pembayaran' => 'MUSIMAN'],
        ]);
        DB::table('brihc')->insert([
            ['pn' => '00076935', 'jabatan' => 'MBM'],
            ['pn' => '00088888', 'jabatan' => 'KEPALA UNIT'],
        ]);
        DB::table('daily_loan_dinamis')->insert([
            $this->dailyLoanRow('boh', '2026-08-25', 'CIF-SAMA', 'REK-BOH', 300_000_000, 'MICRO', 'KUR Mikro', '2026-08-10', [
                'ln_type' => 'LT-MUSIMAN',
                'pn_pemutus1' => '00066855 - Zamrud Nusa Lazuardi',
            ]),
            $this->dailyLoanRow('sboh', '2026-08-25', 'CIF-SAMA', 'REK-SBOH', 80_000_000, 'MICRO', 'Kupedes', '2026-08-11', [
                'ln_type' => 'LT-BULANAN',
                'pn_pemutus1' => '00076935 - Yudhi Romansyah',
            ]),
            $this->dailyLoanRow('ka-unit', '2026-08-25', 'CIF-LAIN', 'REK-KA', 150_000_000, 'MICRO', 'KUR Kecil', '2026-08-12', [
                'ln_type' => 'LT-BULANAN',
                'pn_pemutus1' => '00088888 - Kepala Unit',
            ]),
        ]);

        $service = app(LandingMicroPerformanceService::class);
        $rows = $this->invokePrivate($service, 'fetchPlafondRealizationRows', ['2026-08-25', null]);
        $payload = $service->aggregatePlafondRealization($rows);
        $decisions = collect(data_get($payload, 'decisions'));

        $this->assertCount(3, $rows);
        $this->assertSame(3, data_get($payload, 'total.deb'));
        $this->assertSame(530_000_000.0, data_get($payload, 'total.amount'));
        $this->assertSame(1, $decisions->firstWhere('key', 'boh')['deb']);
        $this->assertSame(300_000_000.0, $decisions->firstWhere('key', 'boh')['amount']);
        $this->assertSame(1, $decisions->firstWhere('key', 'sboh')['deb']);
        $this->assertSame(80_000_000.0, $decisions->firstWhere('key', 'sboh')['amount']);
        $this->assertSame(1, $decisions->firstWhere('key', 'ka_unit')['deb']);
        $this->assertSame(150_000_000.0, $decisions->firstWhere('key', 'ka_unit')['amount']);
        $this->assertSame(3, $decisions->sum('deb'));
        $this->assertSame(530_000_000.0, (float) $decisions->sum('amount'));
        $this->assertSame(530_000_000.0, (float) collect(data_get($payload, 'products'))->sum('amount'));
        $this->assertSame(530_000_000.0, (float) collect(data_get($payload, 'patterns'))->sum('amount'));
    }

    public function test_manual_pdwk_account_overrides_remain_authoritative_after_source_import_changes(): void
    {
        DB::table('brihc')->insert([
            ['pn' => '00088888', 'jabatan' => 'KEPALA UNIT'],
        ]);

        $expectedRoles = [
            '634101020247101' => 'BOH',
            '388701046142106' => 'BOH',
            '388701046143102' => 'BOH',
            '635201038330107' => 'BOH',
            '55201008397109' => 'SBOH',
            '55201008400106' => 'SBOH',
            '55201008410101' => 'SBOH',
            '55201008414105' => 'SBOH',
            '210901000316104' => 'SBOH',
            '388501022076105' => 'KA UNIT',
            '364101032802102' => 'BOH',
            '375801028495108' => 'BOH',
            '220401000458108' => 'SBOH',
        ];
        $plafonds = [
            '634101020247101' => 100_000_000,
            '388701046142106' => 150_000_000,
            '388701046143102' => 160_000_000,
            '635201038330107' => 500_000_000,
            '55201008397109' => 250_000_000,
            '55201008400106' => 200_000_000,
            '55201008410101' => 150_000_000,
            '55201008414105' => 150_000_000,
            '210901000316104' => 350_000_000,
            '388501022076105' => 80_000_000,
            '364101032802102' => 250_000_000,
            '375801028495108' => 250_000_000,
            '220401000458108' => 400_000_000,
        ];
        $rows = [];
        foreach ($expectedRoles as $account => $role) {
            $rows[] = $this->dailyLoanRow(
                'manual-pdwk-'.$account,
                '2026-08-25',
                'CIF-'.$account,
                $account,
                $plafonds[$account],
                'MICRO',
                'Kupedes',
                '2026-08-15',
                ['pn_pemutus1' => '00088888 - Data sumber berubah']
            );
        }
        DB::table('daily_loan_dinamis')->insert($rows);

        $service = app(LandingMicroPerformanceService::class);
        $realizations = $this->invokePrivate($service, 'fetchPlafondRealizationRows', ['2026-08-25', null]);
        $byAccount = collect($realizations)->keyBy('account_key');
        foreach ($expectedRoles as $account => $role) {
            $this->assertSame($role, data_get($byAccount, $account.'.decision_role'));
            $this->assertSame('manual_account_override', data_get($byAccount, $account.'.decision_role_source'));
            $this->assertNotSame('', data_get($byAccount, $account.'.decision_override_note'));
        }
        $this->assertSame('MBM', data_get($byAccount, '634101020247101.decision_expected_role'));
        $this->assertTrue(data_get($byAccount, '634101020247101.decision_is_override'));
        $this->assertFalse(data_get($byAccount, '635201038330107.decision_is_override'));
        $this->assertTrue(data_get($byAccount, '388501022076105.decision_is_override'));
        $this->assertTrue(data_get($byAccount, '364101032802102.decision_is_override'));

        $decisions = collect(data_get($service->aggregatePlafondRealization($realizations), 'decisions'));
        $this->assertSame(6, $decisions->firstWhere('key', 'boh')['deb']);
        $this->assertSame(1_410_000_000.0, $decisions->firstWhere('key', 'boh')['amount']);
        $this->assertSame(6, $decisions->firstWhere('key', 'sboh')['deb']);
        $this->assertSame(1_500_000_000.0, $decisions->firstWhere('key', 'sboh')['amount']);
        $this->assertSame(1, $decisions->firstWhere('key', 'ka_unit')['deb']);
        $this->assertSame(80_000_000.0, $decisions->firstWhere('key', 'ka_unit')['amount']);
        $this->assertSame(1, collect($decisions->firstWhere('key', 'ka_unit')['branches'])->sum('override_deb'));
    }

    public function test_smart_pdwk_detection_uses_cross_unit_authority_pattern_with_strict_guards(): void
    {
        DB::table('brihc')->insert([
            ['pn' => '33333', 'jabatan' => 'MBM'],
            ['pn' => '44444', 'jabatan' => 'MBM'],
            ['pn' => '55555', 'jabatan' => 'MBM'],
            ['pn' => '66666', 'jabatan' => 'MBM'],
            ['pn' => '77777', 'jabatan' => 'RSBH'],
            ['pn' => '88888', 'jabatan' => 'MBM'],
        ]);

        DB::table('daily_loan_dinamis')->insert([
            $this->dailyLoanRow('single-kcp-low', '2026-08-25', 'CIF-KCP-LOW', 'SINGLE-KCP-LOW', 150_000_000, 'MICRO', 'Kupedes', '2026-08-10', [
                'unit1' => 'KCP SATU',
                'pn_pemutus1' => '00033333 - MBM Satu Transaksi KCP',
                'pn_pemutus_normalized' => '33333',
            ]),
            $this->dailyLoanRow('kcp-head-high', '2026-08-25', 'CIF-KCP-HEAD', 'KCP-HEAD-HIGH', 350_000_000, 'MICRO', 'KUR Kecil', '2026-08-10', [
                'unit1' => 'KCP DUA',
                'pn_pemutus1' => '00044444 - Kepala KCP',
                'pn_pemutus_normalized' => '44444',
            ]),
            $this->dailyLoanRow('smart-100', '2026-08-25', 'CIF-SMART-100', 'SMART-100', 100_000_000, 'MICRO', 'Kupedes', '2026-08-11', [
                'unit1' => 'UNIT A MADIUN',
                'pn_pemutus1' => '00055555 - Pejabat Pelaksana',
                'pn_pemutus_normalized' => '55555',
            ]),
            $this->dailyLoanRow('smart-150', '2026-08-25', 'CIF-SMART-150', 'SMART-150', 150_000_000, 'MICRO', 'Kupedes', '2026-08-11', [
                'unit1' => 'UNIT B MADIUN',
                'pn_pemutus1' => '00055555 - Pejabat Pelaksana',
                'pn_pemutus_normalized' => '55555',
            ]),
            $this->dailyLoanRow('smart-500', '2026-08-25', 'CIF-SMART-500', 'SMART-500', 500_000_000, 'MICRO', 'Kupedes', '2026-08-11', [
                'unit1' => 'UNIT C MADIUN',
                'pn_pemutus1' => '00055555 - Pejabat Pelaksana',
                'pn_pemutus_normalized' => '55555',
            ]),
            $this->dailyLoanRow('smart-80', '2026-08-25', 'CIF-SMART-80', 'SMART-80', 80_000_000, 'MICRO', 'KUR Mikro', '2026-08-14', [
                'unit1' => 'UNIT D MADIUN',
                'pn_pemutus1' => '00055555 - Pejabat Pelaksana',
                'pn_pemutus_normalized' => '55555',
            ]),
            $this->dailyLoanRow('sparse-mbm', '2026-08-25', 'CIF-SPARSE', 'SPARSE-MBM', 350_000_000, 'MICRO', 'KUR Kecil', '2026-08-14', [
                'unit1' => 'UNIT TUNGGAL',
                'pn_pemutus1' => '00066666 - MBM Tunggal',
                'pn_pemutus_normalized' => '66666',
            ]),
            $this->dailyLoanRow('regional-head', '2026-08-25', 'CIF-RSBH', 'REGIONAL-HEAD', 250_000_000, 'MICRO', 'Kupedes', '2026-08-14', [
                'unit1' => 'UNIT REGIONAL',
                'pn_pemutus1' => '00077777 - Regional Head',
                'pn_pemutus_normalized' => '77777',
            ]),
            $this->dailyLoanRow('cross-branch-a', '2026-08-25', 'CIF-CROSS-A', 'CROSS-A', 150_000_000, 'MICRO', 'Kupedes', '2026-08-12', [
                'unit1' => 'UNIT A MADIUN',
                'pn_pemutus1' => '00088888 - MBM Lintas Cabang',
                'pn_pemutus_normalized' => '88888',
            ]),
            $this->dailyLoanRow('cross-branch-b', '2026-08-25', 'CIF-CROSS-B', 'CROSS-B', 120_000_000, 'MICRO', 'Kupedes', '2026-08-12', [
                'cabang1' => 'KC Ngawi',
                'cabang_normalized' => 'KC NGAWI',
                'unit1' => 'UNIT B NGAWI',
                'pn_pemutus1' => '00088888 - MBM Lintas Cabang',
                'pn_pemutus_normalized' => '88888',
            ]),
            $this->dailyLoanRow('cross-branch-c', '2026-08-25', 'CIF-CROSS-C', 'CROSS-C', 500_000_000, 'MICRO', 'Kupedes', '2026-08-12', [
                'cabang1' => 'KC Ngawi',
                'cabang_normalized' => 'KC NGAWI',
                'unit1' => 'UNIT C NGAWI',
                'pn_pemutus1' => '00088888 - MBM Lintas Cabang',
                'pn_pemutus_normalized' => '88888',
            ]),
            $this->dailyLoanRow('fallback-100', '2026-08-25', 'CIF-FALLBACK', 'FALLBACK-100', 100_000_000, 'MICRO', 'Kupedes', '2026-08-15', [
                'pn_pemutus1' => '00099999 - Tidak Ada di BRIHC',
                'pn_pemutus_normalized' => '99999',
            ]),
        ]);

        $rows = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'fetchPlafondRealizationRows',
            ['2026-08-25', null]
        );
        $byAccount = collect($rows)->keyBy('account_key');

        $this->assertSame('BOH', data_get($byAccount, 'SMART-100.decision_role'));
        $this->assertSame('MBM', data_get($byAccount, 'SMART-100.decision_expected_role'));
        $this->assertSame('inferred_acting_boh', data_get($byAccount, 'SMART-100.decision_role_source'));
        $this->assertSame('high', data_get($byAccount, 'SMART-100.decision_role_confidence'));
        $this->assertTrue(data_get($byAccount, 'SMART-100.decision_is_override'));
        $this->assertSame('BOH', data_get($byAccount, 'SMART-500.decision_role'));
        $this->assertFalse(data_get($byAccount, 'SMART-500.decision_is_override'));
        $this->assertSame('KA UNIT', data_get($byAccount, 'SMART-80.decision_role'));
        $this->assertSame('inferred_acting_boh_nominal_guard', data_get($byAccount, 'SMART-80.decision_role_source'));
        $this->assertTrue(data_get($byAccount, 'SMART-80.decision_is_override'));
        $this->assertSame('MBM', data_get($byAccount, 'SPARSE-MBM.decision_role'));
        $this->assertSame('brihc', data_get($byAccount, 'SPARSE-MBM.decision_role_source'));
        $this->assertSame('BOH', data_get($byAccount, 'REGIONAL-HEAD.decision_role'));
        $this->assertTrue(data_get($byAccount, 'REGIONAL-HEAD.decision_is_override'));
        $this->assertSame('MBM', data_get($byAccount, 'CROSS-C.decision_role'));
        $this->assertSame('brihc', data_get($byAccount, 'CROSS-C.decision_role_source'));
        $this->assertSame('SBOH', data_get($byAccount, 'KCP-HEAD-HIGH.decision_role'));
        $this->assertSame('inferred_kcp_sboh', data_get($byAccount, 'KCP-HEAD-HIGH.decision_role_source'));
        $this->assertSame('MBM', data_get($byAccount, 'SINGLE-KCP-LOW.decision_role'));
        $this->assertSame('brihc', data_get($byAccount, 'SINGLE-KCP-LOW.decision_role_source'));
        $this->assertSame('MBM', data_get($byAccount, 'FALLBACK-100.decision_role'));
        $this->assertSame('nominal_fallback', data_get($byAccount, 'FALLBACK-100.decision_role_source'));
    }

    public function test_nett_disbursement_handles_same_and_replacement_accounts_without_double_reducing_cif(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->dailyLoanRow('prev-same', '2026-07-31', 'CIF-SAME', 'REK-SAME', 20_000_000, 'MICRO', 'Kupedes'),
            $this->dailyLoanRow('curr-same', '2026-08-25', 'CIF-SAME', 'REK-SAME', 70_000_000, 'MICRO', 'Kupedes', '2026-08-05'),
            $this->dailyLoanRow('prev-replace', '2026-07-31', 'CIF-REPLACE', 'REK-OLD', 20_000_000, 'MICRO', 'Kupedes'),
            $this->dailyLoanRow('curr-replace', '2026-08-25', 'CIF-REPLACE', 'REK-NEW', 70_000_000, 'MICRO', 'KUR Mikro', '2026-08-06'),
            $this->dailyLoanRow('prev-split', '2026-07-31', 'CIF-SPLIT', 'REK-SPLIT-OLD', 20_000_000, 'MICRO', 'Kupedes'),
            $this->dailyLoanRow('curr-split-a', '2026-08-25', 'CIF-SPLIT', 'REK-SPLIT-A', 40_000_000, 'MICRO', 'KUR Mikro', '2026-08-07'),
            $this->dailyLoanRow('curr-split-b', '2026-08-25', 'CIF-SPLIT', 'REK-SPLIT-B', 30_000_000, 'MICRO', 'KUR Kecil', '2026-08-07'),
        ]);

        $service = app(LandingMicroPerformanceService::class);
        $rows = $this->invokePrivate($service, 'fetchNetRealizationRows', ['2026-08-25', '2026-07-31', null]);
        $payload = $service->aggregateNetRealization($rows);

        $this->assertCount(4, $rows);
        $this->assertSame(4, data_get($payload, 'total.deb'));
        $this->assertSame(150_000_000.0, data_get($payload, 'total.amount'));
        $this->assertSame(50_000_000.0, (float) collect($rows)->firstWhere('account_key', 'REK-SAME')->net_amount);
        $this->assertSame(50_000_000.0, (float) collect($rows)->firstWhere('account_key', 'REK-NEW')->net_amount);
        $this->assertSame(
            50_000_000.0,
            (float) collect($rows)->whereIn('account_key', ['REK-SPLIT-A', 'REK-SPLIT-B'])->sum('net_amount')
        );
        $this->assertSame(150_000_000.0, (float) collect(data_get($payload, 'types'))->sum('amount'));
        $this->assertSame(150_000_000.0, (float) collect(data_get($payload, 'products'))->sum('amount'));
    }

    public function test_mantri_performance_uses_roster_categories_account_counts_tiers_and_national_hke(): void
    {
        DB::table('brihc_pemasar')->insert([
            ['pernr' => '', 'pn_mantri' => '000111', 'completename' => 'PT Aktif', 'esgdesc' => 'PT', 'positiondesc' => 'MANTRI', 'orgdesc' => 'UNIT A', 'psadesc' => 'KC Madiun'],
            ['pernr' => '000112', 'pn_mantri' => null, 'completename' => 'PT Belum', 'esgdesc' => 'PT', 'positiondesc' => 'MANTRI', 'orgdesc' => 'UNIT B', 'psadesc' => 'KC Madiun'],
            ['pernr' => '000222', 'pn_mantri' => null, 'completename' => 'Kontrak', 'esgdesc' => 'KONTRAK', 'positiondesc' => 'MANTRI', 'orgdesc' => 'UNIT C', 'psadesc' => 'KC Madiun'],
            ['pernr' => '000333', 'pn_mantri' => null, 'completename' => 'Briguna', 'esgdesc' => 'PT', 'positiondesc' => 'MANTRI BRIGUNA', 'orgdesc' => 'KC MADIUN', 'psadesc' => 'KC Madiun'],
        ]);
        DB::table('daily_loan_dinamis')->insert(
            $this->dailyLoanRow('branch-code', '2026-08-25', 'CIF-X', 'REK-X', 1, 'MICRO', 'Kupedes')
        );
        $realizationRows = [
            (object) ['branch' => 'KC Madiun', 'manager_pn' => '111', 'account_key' => 'REK-PT', 'amount' => 700_000_000, 'realization_date' => '2026-08-25'],
            (object) ['branch' => 'KC Madiun', 'manager_pn' => '222', 'account_key' => 'REK-KONTRAK', 'amount' => 350_000_000, 'realization_date' => '2026-08-24'],
            (object) ['branch' => 'KC Madiun', 'manager_pn' => '999', 'account_key' => 'REK-BELUM-TERPETAKAN', 'amount' => 50_000_000, 'realization_date' => '2026-08-25'],
        ];
        $netRows = [
            (object) ['branch' => 'KC Madiun', 'manager_pn' => '111', 'account_key' => 'REK-PT', 'net_amount' => 500_000_000, 'realization_date' => '2026-08-25'],
            (object) ['branch' => 'KC Madiun', 'manager_pn' => '222', 'account_key' => 'REK-KONTRAK', 'net_amount' => 350_000_000, 'realization_date' => '2026-08-24'],
            (object) ['branch' => 'KC Madiun', 'manager_pn' => '999', 'account_key' => 'REK-BELUM-TERPETAKAN', 'net_amount' => 30_000_000, 'realization_date' => '2026-08-25'],
        ];

        $payload = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'buildMantriPerformance',
            ['2026-08-25', $realizationRows, $netRows, null]
        );
        $madiun = collect($payload['rows'])->firstWhere('branch', 'KC MADIUN');
        $ptBuckets = collect(data_get($madiun, 'tiers.pt.buckets'));
        $contractBuckets = collect(data_get($madiun, 'tiers.contract.buckets'));

        $this->assertTrue($payload['available']);
        $this->assertSame(15, $payload['working_days']);
        $this->assertSame('45', data_get($madiun, 'branch_code'));
        $this->assertSame(2, data_get($madiun, 'headcount.pt_non_briguna'));
        $this->assertSame(1, data_get($madiun, 'headcount.contract'));
        $this->assertSame(1, data_get($madiun, 'headcount.briguna'));
        $this->assertSame(2, data_get($madiun, 'realization.deb'));
        $this->assertSame(1_050_000_000.0, data_get($madiun, 'realization.amount'));
        $this->assertSame(1, data_get($madiun, 'daily_realization.deb'));
        $this->assertSame(700_000_000.0, data_get($madiun, 'daily_realization.amount'));
        $this->assertSame(500_000_000.0, data_get($madiun, 'daily_net_disbursement.amount'));
        $this->assertEqualsWithDelta(70_000_000.0, data_get($madiun, 'realization.average_per_hke'), 0.01);
        $this->assertSame(850_000_000.0, data_get($madiun, 'net_disbursement.amount'));
        $this->assertSame(1_050_000_000.0, data_get($payload, 'total.realization.amount'));
        $this->assertSame(1, data_get($payload, 'excluded_non_roster.realization.deb'));
        $this->assertSame(50_000_000.0, data_get($payload, 'excluded_non_roster.realization.amount'));
        $this->assertSame(30_000_000.0, data_get($payload, 'excluded_non_roster.net_disbursement.amount'));
        $this->assertSame(1, $ptBuckets->firstWhere('key', 'none')['mantri']);
        $this->assertSame(1, $ptBuckets->firstWhere('key', 'low')['mantri']);
        $this->assertSame(1, $contractBuckets->firstWhere('key', 'low')['mantri']);
        $this->assertSame('PT Belum', data_get($ptBuckets->firstWhere('key', 'none'), 'people.0.name'));
        $this->assertSame(0.0, data_get($ptBuckets->firstWhere('key', 'none'), 'people.0.net_amount'));
        $this->assertSame('PT Aktif', data_get($ptBuckets->firstWhere('key', 'low'), 'people.0.name'));
        $this->assertSame(500_000_000.0, data_get($ptBuckets->firstWhere('key', 'low'), 'people.0.net_amount'));
        $this->assertSame(1, data_get($contractBuckets->firstWhere('key', 'low'), 'people.0.net_deb'));
        $this->assertSame(100.0, (float) $ptBuckets->sum('share'));
        $this->assertSame(100.0, (float) $contractBuckets->sum('share'));
    }

    public function test_unproductive_mantri_uses_consecutive_closed_micro_months_and_current_branch_roster(): void
    {
        DB::table('brihc_pemasar')->insert([
            ['pernr' => '000111', 'completename' => 'Mantri Aktif Juli', 'esgdesc' => 'PT', 'positiondesc' => 'MANTRI', 'orgdesc' => 'UNIT A', 'psadesc' => 'KC Madiun'],
            ['pernr' => '000112', 'completename' => 'Mantri Terakhir Juni', 'esgdesc' => 'PT', 'positiondesc' => 'MANTRI', 'orgdesc' => 'UNIT B', 'psadesc' => 'KC Madiun'],
            ['pernr' => '000113', 'completename' => 'Mantri Terakhir April', 'esgdesc' => 'KONTRAK', 'positiondesc' => 'MANTRI', 'orgdesc' => 'UNIT C', 'psadesc' => 'KC Madiun'],
            ['pernr' => '000114', 'completename' => 'Mantri Nihil Enam Bulan', 'esgdesc' => 'PT', 'positiondesc' => 'MANTRI BRIGUNA', 'orgdesc' => 'KC MADIUN', 'psadesc' => 'KC Madiun'],
            ['pernr' => '000221', 'completename' => 'Mantri Ngawi Nihil', 'esgdesc' => 'PT', 'positiondesc' => 'MANTRI', 'orgdesc' => 'UNIT NGAWI', 'psadesc' => 'KC Ngawi'],
        ]);

        foreach (['2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31', '2026-06-30', '2026-07-31'] as $closedPeriod) {
            DB::table('daily_loan_dinamis')->insert($this->dailyLoanRow(
                'closing-'.str_replace('-', '', $closedPeriod),
                $closedPeriod,
                'CIF-'.$closedPeriod,
                'REK-'.$closedPeriod,
                1,
                'MICRO',
                'Kupedes'
            ));
            DB::table('performance_rm_snapshots')->insert([
                [
                    'periode' => $closedPeriod,
                    'cabang' => 'KC MADIUN',
                    'unit' => 'KC MADIUN',
                    'rm' => '000114 - MANTRI NIHIL ENAM BULAN',
                    'segmen' => 'MICRO',
                    'realisasi_os' => 0,
                ],
                [
                    'periode' => $closedPeriod,
                    'cabang' => 'KC NGAWI',
                    'unit' => 'UNIT NGAWI',
                    'rm' => '000221 - MANTRI NGAWI NIHIL',
                    'segmen' => 'MICRO',
                    'realisasi_os' => 0,
                ],
            ]);
        }

        DB::table('performance_rm_snapshots')->insert([
            ['periode' => '2026-07-31', 'cabang' => 'KC MADIUN', 'unit' => 'UNIT A', 'rm' => '000111 - MANTRI AKTIF JULI', 'segmen' => 'MICRO', 'realisasi_os' => 100_000_000],
            ['periode' => '2026-06-30', 'cabang' => 'KC MADIUN', 'unit' => 'UNIT B', 'rm' => '000112 - MANTRI TERAKHIR JUNI', 'segmen' => 'MICRO', 'realisasi_os' => 80_000_000],
            ['periode' => '2026-04-30', 'cabang' => 'KC MADIUN', 'unit' => 'UNIT C', 'rm' => '000113 - MANTRI TERAKHIR APRIL', 'segmen' => 'MICRO', 'realisasi_os' => 60_000_000],
            ['periode' => '2026-07-31', 'cabang' => 'KC MADIUN', 'unit' => 'KC MADIUN', 'rm' => '000114 - MANTRI NIHIL ENAM BULAN', 'segmen' => 'SMALL', 'realisasi_os' => 900_000_000],
            ['periode' => '2026-07-31', 'cabang' => 'KC MADIUN', 'unit' => 'UNIT LAMA', 'rm' => '000999 - PEGAWAI LAMA', 'segmen' => 'MICRO', 'realisasi_os' => 500_000_000],
        ]);

        $service = app(LandingMicroPerformanceService::class);
        $area = $this->invokePrivate($service, 'buildUnproductiveMantri', [
            '2026-08-25',
            null,
            $this->invokePrivate($service, 'mantriRoster', [null]),
        ]);
        $madiunScope = UserBranchScope::forKey('madiun');
        $madiun = $this->invokePrivate($service, 'buildUnproductiveMantri', [
            '2026-08-25',
            $madiunScope,
            $this->invokePrivate($service, 'mantriRoster', [$madiunScope]),
        ]);

        $this->assertTrue($area['available']);
        $this->assertSame(5, $area['total_mantri']);
        $this->assertSame(4, data_get($area, 'totals.month_1.count'));
        $this->assertSame(3, data_get($area, 'totals.month_3.count'));
        $this->assertSame(2, data_get($area, 'totals.month_6.count'));
        $this->assertSame(40.0, data_get($area, 'totals.month_6.percentage'));
        $this->assertSame('Mantri Nihil Enam Bulan', data_get($area, 'totals.month_6.mantri.0.name'));
        $this->assertSame(
            'UNIT C',
            data_get(collect(data_get($area, 'totals.month_3.mantri', []))->firstWhere('pn', '113'), 'unit')
        );
        $this->assertSame(6, count($area['closed_periods']));

        $this->assertSame(4, $madiun['total_mantri']);
        $this->assertSame(3, data_get($madiun, 'totals.month_1.count'));
        $this->assertSame(2, data_get($madiun, 'totals.month_3.count'));
        $this->assertSame(1, data_get($madiun, 'totals.month_6.count'));
        $this->assertSame(['KC MADIUN'], array_column($madiun['branches'], 'branch'));
        $this->assertSame(1, data_get($madiun, 'branches.0.metrics.month_6.count'));
    }

    public function test_unproductive_mantri_uses_daily_loan_assignment_and_does_not_backdate_new_brihc_mantri(): void
    {
        DB::table('brihc_pemasar')->insert([
            [
                'pernr' => '000111',
                'completename' => 'Nama Referensi Lama',
                'esgdesc' => 'PT',
                'positiondesc' => 'MANTRI',
                'orgdesc' => 'UNIT A',
                'psadesc' => 'KC Madiun',
                'tmt_jabatan' => '2024-01-01',
            ],
            [
                'pernr' => '000999',
                'completename' => 'Angga Triawan',
                'esgdesc' => 'PT',
                'positiondesc' => 'MANTRI',
                'orgdesc' => 'UNIT B',
                'psadesc' => 'KC Madiun',
                'tmt_jabatan' => '2026-08-10',
            ],
        ]);

        foreach (['2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31', '2026-06-30', '2026-07-31'] as $closedPeriod) {
            DB::table('daily_loan_dinamis')->insert($this->dailyLoanRow(
                'guard-closing-'.str_replace('-', '', $closedPeriod),
                $closedPeriod,
                'CIF-'.$closedPeriod,
                'REK-'.$closedPeriod,
                1,
                'MICRO',
                'Kupedes'
            ));
            DB::table('performance_rm_snapshots')->insert([
                'periode' => $closedPeriod,
                'cabang' => 'KC MADIUN',
                'unit' => 'UNIT A',
                'rm' => '000111 - NAMA DARI DAILY LOAN',
                'segmen' => 'MICRO',
                'realisasi_os' => 0,
            ]);
        }

        DB::table('daily_loan_dinamis')->insert($this->dailyLoanRow(
            'guard-current',
            '2026-08-25',
            'CIF-GUARD',
            'REK-GUARD',
            1,
            'MICRO',
            'Kupedes',
            null,
            [
                'unit1' => 'UNIT A',
                'pn_pengelola1' => '000111',
                'pn_name1' => '000111 - Nama Dari Daily Loan',
            ]
        ));
        DB::table('daily_loan_dinamis')->insert($this->dailyLoanRow(
            'roster-daily-only',
            '2026-08-25',
            'CIF-DAILY-ONLY',
            'REK-DAILY-ONLY',
            1,
            'MICRO',
            'Kupedes',
            '2026-08-20',
            [
                'unit1' => 'UNIT A',
                'pn_pengelola1' => '000999',
                'pn_name1' => '000999 - Pemegang Lama Bukan Mantri Aktif',
            ]
        ));

        $service = app(LandingMicroPerformanceService::class);
        $roster = $this->invokePrivate($service, 'mantriRoster', [null, '2026-08-25']);
        $payload = $this->invokePrivate($service, 'buildUnproductiveMantri', ['2026-08-25', null, $roster]);

        $this->assertSame('Nama Dari Daily Loan', $roster->firstWhere('pn', '111')['name']);
        $this->assertSame(2, $payload['total_mantri']);
        $this->assertSame(1, data_get($payload, 'totals.month_1.monitored'));
        $this->assertSame(1, data_get($payload, 'totals.month_6.count'));
        $this->assertNull(collect(data_get($payload, 'totals.month_1.mantri', []))->firstWhere('pn', '999'));
    }

    public function test_mantri_roster_matches_brihc_category_by_pn_before_unit(): void
    {
        DB::table('brihc_pemasar')->insert([
            ['pernr' => '000111', 'completename' => 'Mantri PT', 'esgdesc' => 'PT', 'positiondesc' => 'MANTRI', 'orgdesc' => 'UNIT A', 'psadesc' => 'KC Madiun'],
            ['pernr' => '000112', 'completename' => 'Mantri Kontrak', 'esgdesc' => 'KONTRAK', 'positiondesc' => 'MANTRI', 'orgdesc' => 'UNIT A', 'psadesc' => 'KC Madiun'],
        ]);
        DB::table('daily_loan_dinamis')->insert($this->dailyLoanRow(
            'roster-current',
            '2026-08-25',
            'CIF-ROSTER',
            'REK-ROSTER',
            1,
            'MICRO',
            'Kupedes',
            null,
            [
                'unit1' => 'UNIT A',
                'pn_pengelola1' => '000111',
                'pn_name1' => '000111 - Nama Daily Loan',
            ]
        ));
        DB::table('daily_loan_dinamis')->insert($this->dailyLoanRow(
            'roster-current-daily-only',
            '2026-08-25',
            'CIF-ROSTER-DAILY-ONLY',
            'REK-ROSTER-DAILY-ONLY',
            1,
            'MICRO',
            'Kupedes',
            '2026-08-20',
            [
                'unit1' => 'UNIT A',
                'pn_pengelola1' => '000999',
                'pn_name1' => '000999 - Pemegang Lama Bukan Mantri Aktif',
            ]
        ));

        $roster = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'mantriRoster',
            [null, '2026-08-25']
        );

        $this->assertSame('pt', $roster->firstWhere('pn', '111')['category']);
        $this->assertSame('Nama Daily Loan', $roster->firstWhere('pn', '111')['name']);
        $this->assertSame('contract', $roster->firstWhere('pn', '112')['category']);
        $this->assertCount(2, $roster);
        $this->assertNull($roster->firstWhere('pn', '999'));
    }

    public function test_micro_payload_stable_cache_is_invalidated_when_brihc_version_changes(): void
    {
        DB::table('brihc_pemasar')->insert([
            'pernr' => '000111',
            'completename' => 'Mantri Pertama',
            'esgdesc' => 'PT',
            'positiondesc' => 'MANTRI',
            'orgdesc' => 'UNIT A',
            'psadesc' => 'KC Madiun',
        ]);
        DB::table('daily_loan_dinamis')->insert($this->dailyLoanRow(
            'cache-current',
            '2026-08-25',
            'CIF-CACHE',
            'REK-CACHE',
            10_000_000,
            'MICRO',
            'Kupedes',
            '2026-08-25',
            ['pn_pengelola1' => '000111', 'pn_name1' => '000111 - Mantri Pertama']
        ));

        $service = app(LandingMicroPerformanceService::class);
        $first = $service->payload('2026-08-25');
        $this->assertSame(1, data_get($first, 'realization.mantri_roster.total_active'));

        DB::table('brihc_pemasar')->insert([
            'pernr' => '000112',
            'completename' => 'Mantri Kedua',
            'esgdesc' => 'PT',
            'positiondesc' => 'MANTRI',
            'orgdesc' => 'UNIT B',
            'psadesc' => 'KC Madiun',
        ]);
        ReportCacheVersion::bump('pinjaman');

        $second = $service->payload('2026-08-25');

        $this->assertSame(2, data_get($second, 'realization.mantri_roster.total_active'));
        $this->assertNotSame(
            data_get($first, 'realization.mantri_roster.total_active'),
            data_get($second, 'realization.mantri_roster.total_active')
        );
    }

    public function test_realization_products_expose_realized_and_pending_mantri_without_double_counting_total(): void
    {
        DB::table('brihc_pemasar')->insert([
            ['pernr' => '000111', 'completename' => 'Mantri Kupedes', 'esgdesc' => 'PT', 'positiondesc' => 'MANTRI', 'orgdesc' => 'UNIT A', 'psadesc' => 'KC Madiun'],
            ['pernr' => '000112', 'completename' => 'Mantri Belum', 'esgdesc' => 'PT', 'positiondesc' => 'MANTRI', 'orgdesc' => 'UNIT B', 'psadesc' => 'KC Madiun'],
            ['pernr' => '000333', 'completename' => 'Mantri Briguna', 'esgdesc' => 'PT', 'positiondesc' => 'MANTRI BRIGUNA', 'orgdesc' => 'KC MADIUN', 'psadesc' => 'KC Madiun'],
        ]);
        $rows = [
            (object) ['product' => 'Kupedes', 'manager_pn' => '111', 'manager_raw' => '111', 'amount' => 10_000_000, 'current_os' => 10_000_000, 'account_key' => 'R1', 'branch' => 'KC Madiun'],
            (object) ['product' => 'Briguna Mikro', 'manager_pn' => '333', 'manager_raw' => '333', 'amount' => 5_000_000, 'current_os' => 5_000_000, 'account_key' => 'R2', 'branch' => 'KC Madiun'],
        ];
        $service = app(LandingMicroPerformanceService::class);
        $payload = $this->invokePrivate($service, 'decorateRealizationWithMantri', [
            $service->aggregatePlafondRealization($rows),
            $rows,
            $this->invokePrivate($service, 'mantriRoster', [null]),
        ]);
        $products = collect($payload['products'])->keyBy('label');

        $this->assertSame(1, data_get($products, 'Kupedes.mantri_realized'));
        $this->assertSame(1, data_get($products, 'Kupedes.mantri_not_realized'));
        $this->assertSame(1, data_get($products, 'Briguna Mikro.mantri_realized'));
        $this->assertSame(0, data_get($products, 'Briguna Mikro.mantri_not_realized'));
        $this->assertSame(['eligible' => 3, 'realized' => 2, 'not_realized' => 1], $payload['mantri']);
        $this->assertSame(3, data_get($payload, 'mantri_roster.total_active'));
        $this->assertSame(2, data_get($payload, 'mantri_roster.kupedes_eligible'));
        $this->assertSame(1, data_get($payload, 'mantri_roster.kupedes_realized'));
        $this->assertSame(1, data_get($payload, 'mantri_roster.kupedes_not_realized'));
        $this->assertSame([
            'branch' => 'KC MADIUN',
            'total_active' => 3,
            'pt' => 2,
            'contract' => 0,
            'briguna' => 1,
        ], data_get($payload, 'mantri_roster.branches.0'));
        $this->assertSame([
            [
                'pn' => '112',
                'name' => 'Mantri Belum',
                'branch' => 'KC MADIUN',
                'unit' => 'UNIT B',
                'category' => 'pt',
            ],
        ], $payload['kupedes_not_realized']);
    }

    public function test_micro_ph_summary_uses_previous_month_end_for_lunas_and_turun_pokok(): void
    {
        DB::table('lw325_ph')->insert([
            ['uniqueid_namareport' => 'prev-drop', 'periode' => '2026-07-31', 'acctno' => '0001', 'kanca' => 'KC Madiun', 'unit' => 'UNIT A', 'segmen_dashboard' => 'Micro', 'pokok' => 100_000_000],
            ['uniqueid_namareport' => 'curr-drop', 'periode' => '2026-08-25', 'acctno' => '0001', 'kanca' => 'KC Madiun', 'unit' => 'UNIT A', 'segmen_dashboard' => 'Micro', 'pokok' => 70_000_000],
            ['uniqueid_namareport' => 'prev-paid', 'periode' => '2026-07-31', 'acctno' => '0002', 'kanca' => 'KC Madiun', 'unit' => 'UNIT A', 'segmen_dashboard' => 'Micro', 'pokok' => 40_000_000],
            ['uniqueid_namareport' => 'prev-small', 'periode' => '2026-07-31', 'acctno' => '0003', 'kanca' => 'KC Madiun', 'unit' => 'UNIT A', 'segmen_dashboard' => 'Small', 'pokok' => 90_000_000],
        ]);

        $payload = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'buildMicroPhSummary',
            ['2026-08-25', null]
        );

        $this->assertTrue($payload['available']);
        $this->assertSame(30_000_000.0, data_get($payload, 'turun_pokok.amount'));
        $this->assertSame(1, data_get($payload, 'turun_pokok.deb'));
        $this->assertSame(40_000_000.0, data_get($payload, 'lunas.amount'));
        $this->assertSame(1, data_get($payload, 'lunas.deb'));
    }

    public function test_micro_quality_composition_splits_sml_from_daily_loan_and_npl_from_ssa(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->dailyLoanRow('lr-prev', '2026-07-31', 'C0', 'R0', 50_000_000, 'MICRO', 'Kupedes', null, ['kolek_detail' => 'LR', 'kolek' => 1, 'flag_restruk' => 'Y']),
            $this->dailyLoanRow('lr-now', '2026-08-25', 'C0', 'R0', 70_000_000, 'MICRO', 'Kupedes', null, ['kolek_detail' => 'LR', 'kolek' => 1, 'flag_restruk' => 'Y']),
            $this->dailyLoanRow('sml1-prev', '2026-07-31', 'C1', 'R1', 80_000_000, 'MICRO', 'Kupedes', null, ['kolek_detail' => 'SML 1', 'kolek' => 2]),
            $this->dailyLoanRow('sml1-now', '2026-08-25', 'C2', 'R2', 100_000_000, 'MICRO', 'Kupedes', null, ['kolek_detail' => 'SML 1', 'kolek' => 2]),
            $this->dailyLoanRow('sml2-now', '2026-08-25', 'C3', 'R3', 200_000_000, 'MICRO', 'Kupedes', null, ['kolek_detail' => 'SML 2', 'kolek' => 2]),
            $this->dailyLoanRow('outside-micro', '2026-08-25', 'C4', 'R4', 900_000_000, 'SMALL', 'Small', null, ['kolek_detail' => 'SML 1', 'kolek' => 2]),
            $this->dailyLoanRow('outside-area', '2026-08-25', 'C5', 'R5', 8_000_000_000, 'MICRO', 'Kupedes', null, ['cabang1' => 'KC Banyuwangi', 'kolek_detail' => 'SML 1', 'kolek' => 2]),
        ]);
        DB::table('ssa_pinjaman')->insert([
            ['month_day_year_of_periode' => '2026-07-31', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)', 'nama_uker' => 'UNIT A', 'segmen_dashboard' => 'Micro', 'kolektabilitas_one_obligor' => 3, 'baki_debet' => 250_000_000],
            ['month_day_year_of_periode' => '2026-08-20', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)', 'nama_uker' => 'UNIT A', 'segmen_dashboard' => 'Micro', 'kolektabilitas_one_obligor' => 3, 'baki_debet' => 300_000_000],
            ['month_day_year_of_periode' => '2026-08-20', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)', 'nama_uker' => 'UNIT A', 'segmen_dashboard' => 'Micro', 'kolektabilitas_one_obligor' => 4, 'baki_debet' => 400_000_000],
            ['month_day_year_of_periode' => '2026-08-20', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)', 'nama_uker' => 'UNIT A', 'segmen_dashboard' => 'Micro', 'kolektabilitas_one_obligor' => 5, 'baki_debet' => 500_000_000],
            ['month_day_year_of_periode' => '2026-08-20', 'nama_cabang' => '00003 -- KC Banyuwangi (Konsolidasi-MB)', 'nama_uker' => 'UNIT X', 'segmen_dashboard' => 'Micro', 'kolektabilitas_one_obligor' => 3, 'baki_debet' => 9_000_000_000],
        ]);

        $payload = $this->invokePrivate(
            app(DashboardSimpananController::class),
            'buildMicroQualityComposition',
            ['2026-08-25', '2026-08-25', null]
        );
        $items = collect($payload['items'])->keyBy('key');

        $this->assertSame(70_000_000.0, data_get($items, 'lr.position'));
        $this->assertSame(20_000_000.0, data_get($items, 'lr.mtd'));
        $this->assertSame(100_000_000.0, data_get($items, 'sml1.position'));
        $this->assertSame(20_000_000.0, data_get($items, 'sml1.mtd'));
        $this->assertSame(200_000_000.0, data_get($items, 'sml2.position'));
        $this->assertSame(300_000_000.0, data_get($items, 'kl.position'));
        $this->assertSame(400_000_000.0, data_get($items, 'd.position'));
        $this->assertSame(500_000_000.0, data_get($items, 'm.position'));
    }

    public function test_product_position_uses_branch_summary_rows_and_exposes_every_micro_product(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->snapshotRow('2026-08-22', 'KC Madiun', 'KC Madiun', 150, 5, 2, [
                'briguna_mikro_os' => 10,
                'kupedes_os' => 20,
                'kur_mikro_os' => 30,
                'kur_kecil_os' => 40,
                'kur_kpp_os' => 50,
            ]),
            $this->snapshotRow('2026-08-22', 'KC Ngawi', 'KC Ngawi', 50, 2, 1, [
                'briguna_mikro_os' => 5,
                'kupedes_os' => 10,
                'kur_mikro_os' => 15,
                'kur_kecil_os' => 10,
                'kur_kpp_os' => 10,
            ]),
            $this->snapshotRow('2026-08-22', 'KC Madiun', 'UNIT A Madiun', 999, 99, 99, [
                'kupedes_os' => 999,
            ]),
        ]);

        $service = app(LandingMicroPerformanceService::class);
        $area = $this->invokePrivate($service, 'productPositions', ['2026-08-22', null]);
        $madiun = $this->invokePrivate($service, 'productPositions', ['2026-08-22', UserBranchScope::forKey('madiun')]);

        $this->assertTrue($area['available']);
        $this->assertCount(5, $area['items']);
        $this->assertSame(200.0, $area['total']);
        $this->assertSame(150.0, $madiun['total']);
        $this->assertSame(
            ['KUR KPP', 'KUR Kecil', 'KUR Mikro', 'Kupedes', 'Briguna Mikro'],
            array_column($madiun['items'], 'label')
        );
    }

    public function test_mbm_ranking_supports_plafond_and_net_without_including_sboh(): void
    {
        $plafondRows = collect([
            ['pn' => '24600', 'name' => 'Trimo Agung Yunianto', 'amount' => 500_000_000],
            ['pn' => '20458', 'name' => 'Nur Elfiana', 'amount' => 400_000_000],
            ['pn' => '64850', 'name' => 'Hendry Nurwahyudi', 'amount' => 300_000_000],
            ['pn' => '22008', 'name' => 'Rudhi Nur Subijanto', 'amount' => 200_000_000],
            ['pn' => '22263', 'name' => 'Muko Hendrasworo', 'amount' => 100_000_000],
        ])->map(fn (array $row): object => (object) [
            'account_key' => 'REK-'.$row['pn'],
            'decision_pn' => $row['pn'],
            'decision_name' => $row['name'],
            'decision_reference_role' => 'MBM',
            'decision_role' => 'MBM',
            'amount' => $row['amount'],
        ])->push((object) [
            'account_key' => 'REK-SBOH',
            'decision_pn' => '999',
            'decision_name' => 'SBOH Tidak Ikut',
            'decision_reference_role' => 'MBM',
            'decision_role' => 'SBOH',
            'amount' => 900_000_000,
        ])->push((object) [
            'account_key' => 'REK-MBM-LUAR-ROSTER',
            'decision_pn' => '888',
            'decision_name' => 'MBM Luar Roster',
            'decision_reference_role' => 'MBM',
            'decision_role' => 'MBM',
            'amount' => 800_000_000,
        ])->all();
        $netRows = collect($plafondRows)
            ->take(4)
            ->map(function (object $row, int $index): object {
                return (object) array_merge((array) $row, [
                    'net_amount' => [50_000_000, 80_000_000, 30_000_000, 20_000_000][$index],
                ]);
            })
            ->all();

        $payload = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'buildMbmDecisionRanking',
            [$plafondRows, $netRows]
        );

        $this->assertTrue($payload['available']);
        $this->assertSame('Trimo Agung Yunianto', data_get($payload, 'metrics.plafond.top.0.name'));
        $this->assertSame('Muko Hendrasworo', data_get($payload, 'metrics.plafond.bottom.0.name'));
        $this->assertSame('Nur Elfiana', data_get($payload, 'metrics.net.top.0.name'));
        $this->assertSame('Muko Hendrasworo', data_get($payload, 'metrics.net.bottom.0.name'));
        $this->assertSame(0.0, data_get($payload, 'metrics.net.bottom.0.amount'));
        $this->assertNotContains(
            'SBOH Tidak Ikut',
            collect(data_get($payload, 'metrics.plafond.top'))->pluck('name')->all()
        );
        $this->assertNotContains(
            'MBM Luar Roster',
            collect(data_get($payload, 'metrics.plafond.top'))->pluck('name')->all()
        );
    }

    public function test_mbm_ranking_uses_current_area_reference_name_and_branch(): void
    {
        $row = (object) [
            'account_key' => 'REK-61165',
            'decision_pn' => '61165',
            'decision_name' => 'Rita Auliasari',
            'decision_reference_role' => 'MBM',
            'decision_role' => 'MBM',
            'branch' => 'KC Madiun',
            'amount' => 125_000_000,
            'net_amount' => 100_000_000,
        ];

        $payload = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'buildMbmDecisionRanking',
            [[$row], [$row]]
        );

        $this->assertSame('Rita Awaliasari', data_get($payload, 'metrics.plafond.top.0.name'));
        $this->assertSame('KC MAGETAN', data_get($payload, 'metrics.plafond.top.0.branch'));
    }

    public function test_pdwk_limit_summary_uses_only_july_workbook_reference(): void
    {
        $rows = [
            (object) [
                'account_key' => 'REK-40',
                'decision_pn' => '24600',
                'decision_name' => 'Nama BRIHC Berbeda',
                'decision_reference_role' => 'SBOH',
                'decision_role' => 'SBOH',
                'amount' => 40_000_000,
            ],
            (object) [
                'account_key' => 'REK-75',
                'decision_pn' => '20458',
                'decision_name' => 'Nur Elfiana',
                'decision_reference_role' => 'MBM',
                'decision_role' => 'MBM',
                'amount' => 75_000_000,
            ],
            (object) [
                'account_key' => 'REK-100',
                'decision_pn' => '22781',
                'decision_name' => 'Suprijono Edi Widodo',
                'decision_reference_role' => 'MBM',
                'decision_role' => 'MBM',
                'amount' => 100_000_000,
            ],
            (object) [
                'account_key' => 'REK-KAUNIT-75',
                'decision_pn' => '199564',
                'decision_name' => 'Hana Binti Muyasaroh',
                'decision_reference_role' => 'KA UNIT',
                'decision_role' => 'KA UNIT',
                'amount' => 50_000_000,
            ],
            (object) [
                'account_key' => 'REK-DI-LUAR-WORKBOOK',
                'decision_pn' => '999999',
                'decision_name' => 'Pemutus Di Luar Referensi',
                'decision_reference_role' => 'MBM',
                'decision_role' => 'MBM',
                'amount' => 10_000_000,
            ],
        ];

        $payload = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'buildPdwkLimitSummary',
            [$rows]
        );
        $mbm = collect(data_get($payload, 'roles'))->firstWhere('key', 'mbm');
        $kaUnit = collect(data_get($payload, 'roles'))->firstWhere('key', 'ka_unit');
        $mbmStatuses = collect(data_get($mbm, 'statuses'))->keyBy('key');
        $kaUnitStatuses = collect(data_get($kaUnit, 'statuses'))->keyBy('key');

        $this->assertTrue($payload['available']);
        $this->assertSame('PDWK MBM & KEPALA UNIT_2026 07 31.xlsx', $payload['source']);
        $this->assertSame(1, data_get($mbmStatuses, 'full.pemutus'));
        $this->assertSame(1, data_get($mbmStatuses, 'three_quarter.pemutus'));
        $this->assertSame(1, data_get($mbmStatuses, 'limited.pemutus'));
        $this->assertSame(0, data_get($mbmStatuses, 'stop.pemutus'));
        $this->assertSame(0.0, data_get($mbmStatuses, 'stop.amount'));
        $this->assertSame(100_000_000.0, data_get($mbmStatuses, 'full.amount'));
        $this->assertSame('Trimo Agung Yunianto', data_get($mbmStatuses, 'limited.people.0.name'));
        $this->assertSame('Hendri Windianarko', data_get($mbmStatuses, 'three_quarter.reference_people.0.name'));
        $this->assertSame(1, data_get($kaUnitStatuses, 'three_quarter.pemutus'));
        $this->assertSame(50_000_000.0, data_get($kaUnitStatuses, 'three_quarter.amount'));

        $madiunPayload = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'buildPdwkLimitSummary',
            [$rows, ['label' => 'KC Madiun']]
        );
        $madiunMbm = collect(data_get($madiunPayload, 'roles'))->firstWhere('key', 'mbm');
        $madiunStatuses = collect(data_get($madiunMbm, 'statuses'))->keyBy('key');

        $this->assertCount(1, data_get($madiunStatuses, 'limited.reference_people'));
        $this->assertCount(2, data_get($madiunStatuses, 'three_quarter.reference_people'));
        $this->assertCount(0, data_get($madiunStatuses, 'full.reference_people'));
    }

    public function test_realization_need_uses_rka_gap_runoff_headcount_and_remaining_hke(): void
    {
        $payload = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'calculateRealizationNeed',
            [900_000_000.0, 1_000_000_000.0, 200_000_000.0, 3, 5]
        );

        $this->assertSame(100_000_000.0, data_get($payload, 'rka_gap'));
        $this->assertSame(300_000_000.0, data_get($payload, 'total_need'));
        $this->assertSame(100_000_000.0, data_get($payload, 'need_per_mantri'));
        $this->assertSame(20_000_000.0, data_get($payload, 'need_per_mantri_per_hke'));
    }

    public function test_realization_need_treats_missing_rka_as_zero_gap(): void
    {
        $payload = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'calculateRealizationNeed',
            [900_000_000.0, 0.0, 100_000_000.0, 2, 5]
        );

        $this->assertSame(0.0, data_get($payload, 'signed_rka_gap'));
        $this->assertSame(0.0, data_get($payload, 'rka_gap'));
        $this->assertSame(100_000_000.0, data_get($payload, 'total_need'));
        $this->assertSame(50_000_000.0, data_get($payload, 'need_per_mantri'));
        $this->assertSame(10_000_000.0, data_get($payload, 'need_per_mantri_per_hke'));
    }

    public function test_realization_need_preserves_overachievement_as_negative_gap(): void
    {
        $payload = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'calculateRealizationNeed',
            [1_200_000_000.0, 1_000_000_000.0, 100_000_000.0, 2, 5]
        );

        $this->assertSame(-200_000_000.0, data_get($payload, 'signed_rka_gap'));
        $this->assertSame(-200_000_000.0, data_get($payload, 'rka_gap'));
        $this->assertSame(-100_000_000.0, data_get($payload, 'total_need'));
        $this->assertSame(-50_000_000.0, data_get($payload, 'need_per_mantri'));
        $this->assertSame(-10_000_000.0, data_get($payload, 'need_per_mantri_per_hke'));
    }

    public function test_micro_need_filter_exposes_all_official_micro_products(): void
    {
        $definitions = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'microNeedProductDefinitions',
            []
        );

        $this->assertSame(
            ['KUR Kecil', 'Kupedes', 'KUR Mikro', 'Briguna Mikro', 'KUR KPP'],
            array_column($definitions, 'label')
        );
    }

    public function test_one_time_nominatives_are_filtered_by_term_and_use_current_os(): void
    {
        DB::table('loan_type')->insert([
            'loan_type' => 'LT-ONE-TIME',
            'pola_pembayaran' => 'MUSIMAN 1 X LUNAS',
        ]);
        DB::table('daily_loan_dinamis')->insert([
            $this->dailyLoanRow('one-time-12', '2026-08-25', 'CIF-12', 'REK-12', 80_000_000, 'MICRO', 'KUR Kecil', '2026-08-12', [
                'ln_type' => 'LT-ONE-TIME',
                'nama_debitur1' => 'Nasabah Dua Belas',
                'jangka_waktu1' => '12M',
            ]),
            $this->dailyLoanRow('one-time-24', '2026-08-25', 'CIF-24', 'REK-24', 120_000_000, 'MICRO', 'KUR Kecil', '2026-08-13', [
                'ln_type' => 'LT-ONE-TIME',
                'nama_debitur1' => 'Nasabah Dua Empat',
                'jangka_waktu1' => '24',
            ]),
        ]);

        $payload = app(LandingMicroPerformanceService::class)->oneTimePaymentNominatives(
            '2026-08-25',
            UserBranchScope::forKey('madiun'),
            'all',
            'm12'
        );

        $this->assertSame('12 Bulan', $payload['term_label']);
        $this->assertSame(1, $payload['customers']);
        $this->assertSame(1, $payload['accounts']);
        $this->assertSame(80_000_000.0, $payload['total_os']);
        $this->assertSame('Nasabah Dua Belas', data_get($payload, 'rows.0.customer_name'));
        $this->assertSame('KUR Kecil', data_get($payload, 'rows.0.product'));
    }

    public function test_burden_ranking_uses_mtd_for_all_metrics_and_excludes_kc_kcp_rows(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->snapshotRow('2025-12-31', 'KC Madiun', 'KC Madiun', 100, 0, 0),
            $this->snapshotRow('2025-12-31', 'KC Ngawi', 'KC Ngawi', 110, 0, 0),
            $this->snapshotRow('2025-12-31', 'KC Madiun', 'UNIT A Madiun', 100, 0, 0),
            $this->snapshotRow('2025-12-31', 'KC Ngawi', 'UNIT B Ngawi', 110, 0, 0),
            $this->snapshotRow('2026-07-31', 'KC Madiun', 'KC Madiun', 75, 10, 6),
            $this->snapshotRow('2026-07-31', 'KC Ngawi', 'KC Ngawi', 100, 12, 1),
            $this->snapshotRow('2026-07-31', 'KC Madiun', 'UNIT A Madiun', 75, 10, 6),
            $this->snapshotRow('2026-07-31', 'KC Ngawi', 'UNIT B Ngawi', 100, 12, 1),
            $this->snapshotRow('2026-07-31', 'KC Madiun', 'KCP Caruban', 1, 1, 1),
            $this->snapshotRow('2026-08-22', 'KC Madiun', 'KC Madiun', 80, 15, 8),
            $this->snapshotRow('2026-08-22', 'KC Ngawi', 'KC Ngawi', 120, 20, 5),
            $this->snapshotRow('2026-08-22', 'KC Madiun', 'UNIT A Madiun', 80, 15, 8),
            $this->snapshotRow('2026-08-22', 'KC Ngawi', 'UNIT B Ngawi', 120, 20, 5),
            $this->snapshotRow('2026-08-22', 'KC Madiun', 'KCP Caruban', 1, 999, 999),
        ]);

        $payload = $this->invokePrivate(
            app(LandingMicroPerformanceService::class),
            'buildBurdenPayload',
            ['2026-08-22', '2026-07-31', '2025-12-31', null]
        );

        $this->assertSame('UNIT A Madiun', data_get($payload, 'units.os.0.label'));
        $this->assertSame(5.0, data_get($payload, 'units.os.0.os_delta'));
        $this->assertSame('UNIT B Ngawi', data_get($payload, 'units.sml.0.label'));
        $this->assertSame(8.0, data_get($payload, 'units.sml.0.sml_delta'));
        $this->assertSame('UNIT B Ngawi', data_get($payload, 'units.npl.0.label'));
        $this->assertSame(4.0, data_get($payload, 'units.npl.0.npl_delta'));
        $this->assertSame('mtd', data_get($payload, 'comparison'));
        $this->assertNull(data_get($payload, 'branches'));
        $this->assertNotContains('KCP Caruban', collect(data_get($payload, 'units.sml'))->pluck('label')->all());
    }

    /** @param array<string, int|float> $metrics */
    private function snapshotRow(
        string $period,
        string $branch,
        string $unit,
        int|float $os,
        int|float $sml,
        int|float $npl,
        array $metrics = []
    ): array {
        $slug = static fn (string $value): string => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '', '-'));

        return array_merge([
            'snapshot_period' => $period,
            'kanca_key' => $slug($branch),
            'unit_key' => $branch === $unit ? $slug($branch) : $slug($unit),
            'kanca_label' => $branch,
            'unit_label' => $unit,
            'micro_os' => $os,
            'micro_sml' => $sml,
            'micro_npl' => $npl,
            'briguna_mikro_os' => 0,
            'kupedes_os' => 0,
            'kur_mikro_os' => 0,
            'kur_kecil_os' => 0,
            'kur_kpp_os' => 0,
        ], $metrics);
    }

    private function dailyLoanRow(
        string $id,
        string $period,
        string $cif,
        string $account,
        int|float $os,
        string $segment,
        string $product,
        ?string $realizationDate = null,
        array $overrides = []
    ): array {
        return array_merge([
            'uniqueid_namareport' => $id,
            'periode' => $period,
            'tgl_realisasi' => $realizationDate,
            'cifno' => $cif,
            'cifno_clean' => $cif,
            'nomor_rekening1' => $account,
            'baki_debet1' => $os,
            'plafon' => $os,
            'cabang1' => 'KC Madiun',
            'kode_cabang1' => '45',
            'cabang_normalized' => 'KC MADIUN',
            'unit1' => 'UNIT Madiun',
            'segmen_kinerja' => $segment,
            'produk_dashboard' => $product,
            'ln_type' => 'BULANAN',
            'nama_debitur1' => null,
            'jangka_waktu1' => null,
            'freq_payment' => 1,
            'pn_pemutus1' => '',
            'pn_pemutus_normalized' => '',
            'pn_pengelola1' => '',
            'kolek_detail' => null,
            'umur_tunggakan' => null,
            'flag_restruk' => null,
            'kolek' => null,
            'next_pmt_date' => null,
            'next_pmt_int_date' => null,
        ], $overrides);
    }

    public function test_billing_schedule_generates_dynamic_calendar_days_for_month(): void
    {
        // Baseline: 2026-08-31, Current: 2026-09-06
        DB::table('daily_loan_dinamis')->insert([
            $this->dailyLoanRow('L1', '2026-08-31', 'C1', 'R1', 10_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-09-02',
            ]),
            $this->dailyLoanRow('L2', '2026-09-06', 'C1', 'R1', 9_500_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-10-02', // rolled forward = paid!
            ]),
        ]);

        $service = app(LandingMicroPerformanceService::class);
        $res = $service->billingSchedule(null, '2026-09-06');

        $this->assertTrue($res['available']);
        $this->assertSame('2026-09-06', $res['period']);
        $this->assertSame(6, $res['current_day']);
        $this->assertSame(30, $res['days_in_month']);
        $this->assertCount(30, $res['cards']);

        // Day 2 should be marked past, with 1 debtor paid
        $day2 = collect($res['cards'])->firstWhere('day', 2);
        $this->assertNotNull($day2);
        $this->assertSame('past', $day2['status']);
        $this->assertSame(1, $day2['billing_debitur']);
        $this->assertSame(1, $day2['paid_debitur']);
        $this->assertEquals(100.0, $day2['pct_debitur']);
        $this->assertEquals(10_000_000.0, $day2['billing_os']);
        $this->assertEquals(10_000_000.0, $day2['paid_os']);

        // Day 7 should be marked upcoming
        $day7 = collect($res['cards'])->firstWhere('day', 7);
        $this->assertNotNull($day7);
        $this->assertSame('upcoming', $day7['status']);
        $this->assertFalse($day7['is_due']);
    }

    public function test_billing_schedule_uses_exact_calendar_length_for_28_29_and_31_day_months(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->dailyLoanRow('FEB28_BASE', '2027-01-31', 'C28', 'R28', 1_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2027-02-28',
            ]),
            $this->dailyLoanRow('FEB28_CURR', '2027-02-10', 'C28', 'R28', 900_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2027-03-28',
            ]),
            $this->dailyLoanRow('FEB29_BASE', '2028-01-31', 'C29', 'R29', 2_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2028-02-29',
            ]),
            $this->dailyLoanRow('FEB29_CURR', '2028-02-10', 'C29', 'R29', 1_900_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2028-03-29',
            ]),
            $this->dailyLoanRow('OCT31_BASE', '2028-09-30', 'C31', 'R31', 3_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2028-10-31',
            ]),
            $this->dailyLoanRow('OCT31_CURR', '2028-10-10', 'C31', 'R31', 2_900_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2028-11-30',
            ]),
        ]);

        $service = app(LandingMicroPerformanceService::class);

        foreach ([
            ['period' => '2027-02-10', 'days' => 28, 'last_date' => '2027-02-28'],
            ['period' => '2028-02-10', 'days' => 29, 'last_date' => '2028-02-29'],
            ['period' => '2028-10-10', 'days' => 31, 'last_date' => '2028-10-31'],
        ] as $case) {
            $result = $service->billingSchedule(null, $case['period']);

            $this->assertSame($case['days'], $result['days_in_month']);
            $this->assertCount($case['days'], $result['cards']);
            $this->assertSame($case['last_date'], $result['cards'][$case['days'] - 1]['date']);
        }
    }

    public function test_billing_schedule_maps_day_31_to_last_available_day_in_m1(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->dailyLoanRow('APR30_BASE', '2026-03-31', 'C-M1', 'R-M1', 20_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-04-30',
            ]),
            $this->dailyLoanRow('APR30_PAID', '2026-04-30', 'C-M1', 'R-M1', 19_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-05-30',
            ]),
            $this->dailyLoanRow('MAY31_BASE', '2026-04-30', 'C-M0', 'R-M0', 10_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-05-31',
            ]),
            $this->dailyLoanRow('MAY31_PAID', '2026-05-31', 'C-M0', 'R-M0', 9_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-06-30',
            ]),
        ]);

        $result = app(LandingMicroPerformanceService::class)->billingSchedule(null, '2026-05-31');
        $day31 = collect($result['cards'])->firstWhere('day', 31);

        $this->assertNotNull($day31);
        $this->assertSame(30, $day31['comparison_day']);
        $this->assertSame('2026-04-30', $day31['comparison_date']);
        $this->assertSame('30 Apr', $day31['comparison_date_label']);
        $this->assertTrue($day31['comparison_date_adjusted']);
        $this->assertSame(1, $day31['m1_billing_debitur']);
        $this->assertSame(1, $day31['m1_paid_debitur']);
        $this->assertEquals(20_000_000.0, $day31['m1_billing_os']);
        $this->assertEquals(20_000_000.0, $day31['m1_paid_os']);
        $this->assertEquals(0.0, $day31['delta_pct_os']);
        $this->assertSame(30, data_get($result, 'm1.same_day_cutoff'));
        $this->assertSame('30 Apr 2026', data_get($result, 'm1.same_day_cutoff_label'));
    }

    public function test_billing_schedule_tracks_paid_accounts_and_upcoming_dates(): void
    {
        // Seed baseline accounts for September on 2026-08-31
        DB::table('daily_loan_dinamis')->insert([
            // Account 1: due Sep 3 -> rolled forward to Oct 3 (Paid regular installment)
            $this->dailyLoanRow('L1', '2026-08-31', 'C1', 'R1', 5_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-09-03',
            ]),
            // Account 2: due Sep 3 -> settled/closed in full (not in 09-06, Paid pelunasan)
            $this->dailyLoanRow('L2', '2026-08-31', 'C2', 'R2', 15_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-09-03',
            ]),
            // Account 3: due Sep 3 -> stuck on Sep 3 with remaining OS (Unpaid)
            $this->dailyLoanRow('L3', '2026-08-31', 'C3', 'R3', 20_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-09-03',
            ]),
            // Account 4: due Sep 15 (Upcoming)
            $this->dailyLoanRow('L4', '2026-08-31', 'C4', 'R4', 30_000_000, 'MICRO', 'KUR Mikro', null, [
                'next_pmt_date' => '2026-09-15',
            ]),

            // Position on 2026-09-06:
            $this->dailyLoanRow('L1_curr', '2026-09-06', 'C1', 'R1', 4_500_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-10-03',
            ]),
            // R2 drops out (pelunasan)
            $this->dailyLoanRow('L3_curr', '2026-09-06', 'C3', 'R3', 20_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-09-03',
            ]),
            $this->dailyLoanRow('L4_curr', '2026-09-06', 'C4', 'R4', 30_000_000, 'MICRO', 'KUR Mikro', null, [
                'next_pmt_date' => '2026-09-15',
            ]),
        ]);

        $service = app(LandingMicroPerformanceService::class);
        $res = $service->billingSchedule(null, '2026-09-06');

        $this->assertTrue($res['available']);

        // Day 3: 3 debtors billed (5M + 15M + 20M = 40M), 2 paid (R1 + R2 = 20M)
        $day3 = collect($res['cards'])->firstWhere('day', 3);
        $this->assertNotNull($day3);
        $this->assertSame(3, $day3['billing_debitur']);
        $this->assertSame(2, $day3['paid_debitur']);
        $this->assertEquals(40_000_000.0, $day3['billing_os']);
        $this->assertEquals(20_000_000.0, $day3['paid_os']);
        $this->assertEquals(66.7, $day3['pct_debitur']);
        $this->assertEquals(50.0, $day3['pct_os']);

        // Day 15: 1 debtor billed (30M), status upcoming
        $day15 = collect($res['cards'])->firstWhere('day', 15);
        $this->assertNotNull($day15);
        $this->assertSame('upcoming', $day15['status']);
        $this->assertSame(1, $day15['billing_debitur']);
        $this->assertEquals(30_000_000.0, $day15['billing_os']);
    }

    public function test_billing_schedule_includes_m1_comparison_benchmark(): void
    {
        // M-1 Baseline: 2026-07-31
        // M-1 Settled: 2026-08-31
        // M0 Baseline: 2026-08-31
        // M0 Current: 2026-09-06
        DB::table('daily_loan_dinamis')->insert([
            // July 31 for August billing
            $this->dailyLoanRow('M1_L1', '2026-07-31', 'CM1', 'RM1', 10_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-08-05',
            ]),
            // Hari ke-31 harus tetap masuk closing M-1 meskipun M0 (September) hanya 30 hari.
            $this->dailyLoanRow('M1_L31', '2026-07-31', 'CM31', 'RM31', 7_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-08-31',
            ]),
            // August 31 (end of M-1): paid!
            $this->dailyLoanRow('M1_L1_done', '2026-08-31', 'CM1', 'RM1', 9_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-09-05',
            ]),
            $this->dailyLoanRow('M1_L31_done', '2026-08-31', 'CM31', 'RM31', 6_500_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-09-30',
            ]),
            // Also August 31 acts as M0 baseline for Sept 5
            $this->dailyLoanRow('M0_L1', '2026-08-31', 'CM0', 'RM0', 20_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-09-05',
            ]),
            // Sept 6 (M0 current): paid!
            $this->dailyLoanRow('M0_L1_curr', '2026-09-06', 'CM0', 'RM0', 19_000_000, 'MICRO', 'Kupedes', null, [
                'next_pmt_date' => '2026-10-05',
            ]),
        ]);

        $service = app(LandingMicroPerformanceService::class);
        $res = $service->billingSchedule(null, '2026-09-06');

        $this->assertTrue($res['available']);
        $this->assertNotEmpty($res['m1']);
        $this->assertEquals(17_000_000.0, $res['m1']['total_billing_os']);
        $this->assertEquals(17_000_000.0, $res['m1']['paid_os']);
        $this->assertSame(2, $res['m1']['total_billing_debitur']);
        $this->assertSame(2, $res['m1']['paid_debitur']);
        $this->assertEquals(100.0, $res['m1']['collection_rate_os']);
        $this->assertEquals(10_000_000.0, $res['m1']['due_so_far_paid_os']);

        $day5 = collect($res['cards'])->firstWhere('day', 5);
        $this->assertNotNull($day5);
        $this->assertEquals(100.0, $day5['m1_pct_os']);
        $this->assertEquals(100.0, $day5['pct_os']);
        $this->assertEquals(0.0, $day5['delta_pct_os']);
    }

    public function test_billing_schedule_respects_branch_filter(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            // Madiun account
            $this->dailyLoanRow('L_MDN', '2026-08-31', 'C_MDN', 'R_MDN', 10_000_000, 'MICRO', 'Kupedes', null, [
                'cabang1' => 'KC Madiun',
                'cabang_normalized' => 'KC MADIUN',
                'next_pmt_date' => '2026-09-04',
            ]),
            // Magetan account
            $this->dailyLoanRow('L_MGT', '2026-08-31', 'C_MGT', 'R_MGT', 50_000_000, 'MICRO', 'Kupedes', null, [
                'cabang1' => 'KC Magetan',
                'cabang_normalized' => 'KC MAGETAN',
                'next_pmt_date' => '2026-09-04',
            ]),
            // Position 09-06
            $this->dailyLoanRow('L_MDN_curr', '2026-09-06', 'C_MDN', 'R_MDN', 10_000_000, 'MICRO', 'Kupedes', null, [
                'cabang1' => 'KC Madiun',
                'cabang_normalized' => 'KC MADIUN',
                'next_pmt_date' => '2026-10-04',
            ]),
            $this->dailyLoanRow('L_MGT_curr', '2026-09-06', 'C_MGT', 'R_MGT', 50_000_000, 'MICRO', 'Kupedes', null, [
                'cabang1' => 'KC Magetan',
                'cabang_normalized' => 'KC MAGETAN',
                'next_pmt_date' => '2026-10-04',
            ]),
        ]);

        $service = app(LandingMicroPerformanceService::class);

        // Madiun scope
        $scopeMadiun = [
            'key' => 'madiun',
            'label' => 'KC Madiun',
            'upper_label' => 'KC MADIUN',
            'plain_label' => 'Madiun',
        ];
        $resMadiun = $service->billingSchedule($scopeMadiun, '2026-09-06');
        $this->assertTrue($resMadiun['available']);
        $this->assertEquals(10_000_000.0, $resMadiun['m0']['total_billing_os']);
        $this->assertEquals(1, $resMadiun['m0']['total_billing_debitur']);

        // Magetan scope
        $scopeMagetan = [
            'key' => 'magetan',
            'label' => 'KC Magetan',
            'upper_label' => 'KC MAGETAN',
            'plain_label' => 'Magetan',
        ];
        $resMagetan = $service->billingSchedule($scopeMagetan, '2026-09-06');
        $this->assertTrue($resMagetan['available']);
        $this->assertEquals(50_000_000.0, $resMagetan['m0']['total_billing_os']);
        $this->assertEquals(1, $resMagetan['m0']['total_billing_debitur']);
    }

    private function invokePrivate(object $target, string $method, array $arguments): mixed
    {
        return (new ReflectionMethod($target, $method))->invokeArgs($target, $arguments);
    }
}
