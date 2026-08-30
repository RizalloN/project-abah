<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Support\LandingMicroPerformanceService;
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
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('brihc_pemasar');
        Schema::dropIfExists('brihc');
        Schema::dropIfExists('loan_type');
        Schema::dropIfExists('daily_loan_dinamis');
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
        $this->assertSame(3, data_get($madiun, 'realization.deb'));
        $this->assertSame(1_100_000_000.0, data_get($madiun, 'realization.amount'));
        $this->assertSame(2, data_get($madiun, 'daily_realization.deb'));
        $this->assertSame(750_000_000.0, data_get($madiun, 'daily_realization.amount'));
        $this->assertSame(530_000_000.0, data_get($madiun, 'daily_net_disbursement.amount'));
        $this->assertEqualsWithDelta(73_333_333.33, data_get($madiun, 'realization.average_per_hke'), 0.01);
        $this->assertSame(880_000_000.0, data_get($madiun, 'net_disbursement.amount'));
        $this->assertSame(1_100_000_000.0, data_get($payload, 'total.realization.amount'));
        $this->assertSame(1, $ptBuckets->firstWhere('key', 'none')['mantri']);
        $this->assertSame(1, $ptBuckets->firstWhere('key', 'low')['mantri']);
        $this->assertSame(1, $contractBuckets->firstWhere('key', 'low')['mantri']);
        $this->assertSame(100.0, (float) $ptBuckets->sum('share'));
        $this->assertSame(100.0, (float) $contractBuckets->sum('share'));
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

    public function test_pdwk_limit_summary_uses_uploaded_overrides_and_defaults_unlisted_mbm_to_full_limit(): void
    {
        $rows = [
            (object) [
                'account_key' => 'REK-40',
                'decision_pn' => '24600',
                'decision_name' => 'Trimo Agung Yunianto',
                'decision_reference_role' => 'MBM',
                'decision_role' => 'MBM',
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
                'decision_pn' => '64850',
                'decision_name' => 'Hendry Nurwahyudi',
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
        $this->assertSame('PDWK MBM dan Kaunit.xlsx', $payload['source']);
        $this->assertSame(1, data_get($mbmStatuses, 'full.pemutus'));
        $this->assertSame(1, data_get($mbmStatuses, 'three_quarter.pemutus'));
        $this->assertSame(1, data_get($mbmStatuses, 'limited.pemutus'));
        $this->assertSame(100_000_000.0, data_get($mbmStatuses, 'full.amount'));
        $this->assertSame('Trimo Agung Yunianto', data_get($mbmStatuses, 'limited.people.0.name'));
        $this->assertSame(1, data_get($kaUnitStatuses, 'three_quarter.pemutus'));
        $this->assertSame(50_000_000.0, data_get($kaUnitStatuses, 'three_quarter.amount'));
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
        ], $overrides);
    }

    private function invokePrivate(object $target, string $method, array $arguments): mixed
    {
        return (new ReflectionMethod($target, $method))->invokeArgs($target, $arguments);
    }
}
