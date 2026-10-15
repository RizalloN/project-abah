<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use App\Support\LandingMicroPerformanceService;
use App\Support\MantriProductivityReference;
use App\Support\MicroNettDisbursementCalculator;
use App\Support\ReportCacheVersion;
use App\Support\UserBranchScope;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class MantriNettParityTest extends TestCase
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

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->string('uniqueid_namareport')->unique();
            foreach ([
                'periode', 'tgl_realisasi', 'cifno', 'cifno_clean', 'nomor_rekening1',
                'cabang1', 'kode_cabang1', 'cabang_normalized', 'unit1', 'unit_normalized',
                'branch1', 'branch_normalized', 'segmen_kinerja', 'produk_dashboard',
                'produk_kinerja', 'description', 'ln_type', 'nama_debitur1', 'jangka_waktu1',
                'pn_pemutus1', 'pn_pemutus_normalized', 'pn_pengelola1', 'pn_pemrakarsa1',
                'pn_name1', 'rm_normalized', 'kolek_detail', 'flag_restruk',
                'next_pmt_date', 'next_pmt_int_date',
            ] as $column) {
                $table->string($column)->nullable();
            }
            foreach (['freq_payment', 'umur_tunggakan', 'kolek'] as $column) {
                $table->integer($column)->nullable();
            }
            $table->decimal('plafon', 20, 2)->default(0);
            $table->decimal('baki_debet1', 20, 2)->default(0);
        });
        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            foreach ([
                'uniqueid_namareport', 'pernr', 'pn_mantri', 'nip', 'completename',
                'esgdesc', 'positiondesc', 'orgdesc', 'psadesc', 'bc', 'status',
                'tmt_masuk', 'tmt_jabatan',
            ] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('brihc', function (Blueprint $table): void {
            $table->string('pn')->nullable();
            $table->string('nama')->nullable();
            $table->string('jabatan')->nullable();
        });
        Schema::create('loan_type', function (Blueprint $table): void {
            $table->string('loan_type');
            $table->string('pola_pembayaran')->nullable();
        });

        $this->seedMantri();
        $this->seedBookings();
    }

    public function test_landing_and_kpi_reconcile_per_mantri_and_branch_including_zero_net_and_missing_bookings(): void
    {
        $landing = $this->landing();
        $kpi = $this->kpi();

        $this->assertTrue(data_get($landing, 'meta.available'), (string) data_get($landing, 'meta.error'));
        $this->assertSame(215_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(5, data_get($landing, 'mantri_performance.total.net_disbursement.deb'));
        $this->assertSame(375_000_000.0, (float) data_get($landing, 'mantri_performance.total.realization.amount'));
        $this->assertSame(100_000_000.0, (float) data_get($landing, 'mantri_performance.total.daily_net_disbursement.amount'));
        $this->assertSame(3, data_get($landing, 'mantri_performance.total.daily_net_disbursement.deb'));
        $this->assertSame(230_000_000.0, (float) data_get($landing, 'mantri_performance.total.daily_realization.amount'));
        $this->assertSame(230_000_000.0, (float) data_get($landing, 'mantri_performance.excluded_non_roster.net_disbursement.amount'));

        $this->assertMantriParity($landing, $kpi, ['111' => [125_000_000.0, 4], '222' => [90_000_000.0, 1]]);
        foreach (['KC MADIUN' => 125_000_000.0, 'KC NGAWI' => 90_000_000.0] as $branch => $amount) {
            $landingRow = collect(data_get($landing, 'mantri_performance.rows'))->firstWhere('branch', $branch);
            $this->assertSame($amount, (float) data_get($landingRow, 'net_disbursement.amount'));
            $this->assertSame($amount, (float) collect($kpi['rows'])->where('cabang', $branch)->sum('realisasi_os'));
        }
    }

    public function test_branch_scope_follows_active_mantri_placement_after_loan_branch_transfer(): void
    {
        auth()->setUser(new GenericUser(['id' => '0045']));
        $landing = $this->landing(UserBranchScope::forKey('madiun'));
        $kpi = $this->kpi();

        $this->assertSame(['KC MADIUN'], array_column(data_get($landing, 'mantri_performance.rows'), 'branch'));
        $this->assertSame(125_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(125_000_000.0, (float) $kpi['total']['realisasi_os']);
        $this->assertMantriParity($landing, $kpi, ['111' => [125_000_000.0, 4]]);
    }

    public function test_subsequent_import_refreshes_both_consumers_without_changing_kur_rm(): void
    {
        $calculator = app(MicroNettDisbursementCalculator::class);
        $first = $this->landing();
        $firstKpi = $this->kpi();
        $kurBefore = $calculator->kurRm('2026-10-12');
        $this->assertSame(200_000_000.0, (float) $kurBefore[0]['realisasi_os']);
        $this->assertSame(215_000_000.0, (float) $firstKpi['total']['realisasi_os']);

        DB::table('daily_loan_dinamis')->where('uniqueid_namareport', 'a-current')->update(['plafon' => 200_000_000]);
        ReportCacheVersion::bump('pinjaman');
        $second = $this->landing();

        // Durable last-valid data may be shown while the import-version refresh runs.
        if (data_get($second, 'meta.refresh_pending')) {
            $this->assertSame(data_get($first, 'mantri_performance'), data_get($second, 'mantri_performance'));
            app(DeferredCallbackCollection::class)->invoke();
            $second = $this->landing();
        }

        $this->assertMantriParity($second, $this->kpi(), ['111' => [125_000_000.0, 4], '222' => [170_000_000.0, 1]]);
        $this->assertSame($kurBefore, $calculator->kurRm('2026-10-12'));
        $this->assertSame(295_000_000.0, (float) data_get($second, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(455_000_000.0, (float) data_get($second, 'mantri_performance.total.realization.amount'));
    }

    public function test_baseline_reimport_is_applied_to_landing_and_kpi_using_the_same_version(): void
    {
        $this->landing();
        $this->kpi();

        DB::table('daily_loan_dinamis')->where('uniqueid_namareport', 'closed')->update(['baki_debet1' => 50_000_000]);
        ReportCacheVersion::bump('pinjaman');
        $landing = $this->landing();
        if (data_get($landing, 'meta.refresh_pending')) {
            app(DeferredCallbackCollection::class)->invoke();
            $landing = $this->landing();
        }

        $this->assertMantriParity($landing, $this->kpi(), ['111' => [105_000_000.0, 4], '222' => [70_000_000.0, 1]]);
        $this->assertSame(175_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
    }

    public function test_next_month_uses_its_own_baseline_without_carrying_prior_month_bookings(): void
    {
        $this->landing();
        $this->kpi();

        DB::table('daily_loan_dinamis')->insert([
            $this->loan('nov-baseline', 'CIF-NOV', 'NOV-OLD', 80_000_000, ['periode' => '2026-10-31', 'tgl_realisasi' => null]),
            $this->loan('nov-current', 'CIF-NOV', 'NOV-NEW', 100_000_000, ['periode' => '2026-11-03', 'tgl_realisasi' => '2026-11-03']),
        ]);
        ReportCacheVersion::bump('pinjaman');
        $landing = app(LandingMicroPerformanceService::class)->payload('2026-11-03');
        $kpi = (new ReflectionMethod(KinerjaRmMikroReportController::class, 'mantriProductivityPayload'))
            ->invoke(new KinerjaRmMikroReportController, '2026-11-03');

        $this->assertSame('2026-11-03', data_get($landing, 'meta.period'));
        $this->assertMantriParity($landing, $kpi, ['111' => [20_000_000.0, 1]]);
        $this->assertSame(100_000_000.0, (float) data_get($landing, 'mantri_performance.total.realization.amount'));
        $this->assertSame(20_000_000.0, (float) data_get($landing, 'mantri_performance.total.daily_net_disbursement.amount'));
    }

    public function test_mantri_working_days_and_average_match_across_existing_national_holidays(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->loan('aug-baseline', 'CIF-AUG', 'AUG-OLD', 10_000_000, ['periode' => '2026-07-31', 'tgl_realisasi' => null]),
            $this->loan('aug-current', 'CIF-AUG', 'AUG-NEW', 150_000_000, ['periode' => '2026-08-25', 'tgl_realisasi' => '2026-08-25']),
        ]);
        $landing = app(LandingMicroPerformanceService::class)->payload('2026-08-25');
        $kpi = (new ReflectionMethod(KinerjaRmMikroReportController::class, 'mantriProductivityPayload'))
            ->invoke(new KinerjaRmMikroReportController, '2026-08-25');

        // The existing landing calendar already excludes 17 and 25 August 2026.
        $this->assertSame(15, data_get($landing, 'mantri_performance.working_days'));
        $this->assertSame(15, $kpi['working_days']);
        $this->assertMantriParity($landing, $kpi, ['111' => [140_000_000.0, 1]]);
        $this->assertEqualsWithDelta(
            (float) $kpi['rows'][0]['ratas_mantri_hk'] * 1_000_000,
            (float) data_get($landing, 'mantri_performance.total.net_disbursement.average_per_hke'),
            0.01
        );
    }

    public function test_active_mantri_with_unknown_employment_category_remains_in_nett_totals(): void
    {
        DB::table('brihc_pemasar')->insert([
            'uniqueid_namareport' => 'roster-444', 'pernr' => '000444',
            'completename' => 'Mantri Kategori Belum Terpetakan',
            'positiondesc' => 'MANTRI', 'esgdesc' => 'BELUM TERPETAKAN',
            'orgdesc' => 'UNIT 444', 'psadesc' => 'KC MADIUN',
        ]);
        DB::table('daily_loan_dinamis')->insert($this->loan('unknown-category', 'CIF-UNKNOWN', 'UNKNOWN', 40_000_000, ['pn_pengelola1' => '000444']));
        $landing = $this->landing();
        $kpi = $this->kpi();

        $this->assertSame(255_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(255_000_000.0, (float) $kpi['total']['realisasi_os']);
        $this->assertSame(6, data_get($landing, 'mantri_performance.total.net_disbursement.deb'));
        $this->assertSame(6, (int) $kpi['total']['realisasi_deb']);
        $this->assertSame(40_000_000.0, (float) collect($kpi['rows'])->firstWhere('pn_mantri', '00000444')['realisasi_os']);
    }

    public function test_full_closed_cif_deduction_is_independent_of_booking_and_observation_order(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->loan('reverse-baseline', 'CIF-REVERSE', 'REVERSE-OLD', 70_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null]),
            $this->loan('reverse-b', 'CIF-REVERSE', 'REVERSE-B', 60_000_000, ['periode' => '2026-10-03', 'tgl_realisasi' => '2026-10-03', 'pn_pengelola1' => '000222']),
            $this->loan('reverse-a', 'CIF-REVERSE', 'REVERSE-A', 50_000_000, ['tgl_realisasi' => '2026-10-02']),
            $this->loan('reverse-b-current', 'CIF-REVERSE', 'REVERSE-B', 60_000_000, ['tgl_realisasi' => '2026-10-03', 'pn_pengelola1' => '000222']),
        ]);
        $calculator = app(MicroNettDisbursementCalculator::class);
        $firstAccounts = $calculator->cachedMantriAccounts('2026-10-12');
        $firstMetrics = $calculator->cachedMantri('2026-10-12');
        $kurBefore = $calculator->cachedKurRm('2026-10-12');
        $reverseAccounts = collect($firstAccounts)->where('cif_key', 'CIF-REVERSE')->keyBy('account_key');

        $this->assertSame(-20_000_000.0, $reverseAccounts['REVERSE-A']['net_amount']);
        $this->assertSame(70_000_000.0, $reverseAccounts['REVERSE-A']['previous_os']);
        $this->assertSame(-10_000_000.0, $reverseAccounts['REVERSE-B']['net_amount']);
        $this->assertSame(70_000_000.0, $reverseAccounts['REVERSE-B']['previous_os']);
        foreach ($firstMetrics as $metric) {
            $accounts = collect($firstAccounts)->where('owner', $metric['owner'])
                ->where('cabang', $metric['cabang'])->where('unit', $metric['unit'])->where('branch_code', $metric['branch_code']);
            $this->assertSame((float) $metric['realisasi_os'], (float) $accounts->sum('net_amount'));
            $this->assertSame($metric['realisasi_deb'], $accounts->pluck('account_key')->unique()->count());
        }

        DB::table('daily_loan_dinamis')->where('uniqueid_namareport', 'reverse-a')->update(['periode' => '2026-10-02']);
        DB::table('daily_loan_dinamis')->where('uniqueid_namareport', 'reverse-b')->update(['periode' => '2026-10-12']);
        ReportCacheVersion::bump('pinjaman');

        $this->assertSame($firstAccounts, $calculator->cachedMantriAccounts('2026-10-12'));
        $this->assertSame($firstMetrics, $calculator->cachedMantri('2026-10-12'));
        $this->assertSame($kurBefore, $calculator->cachedKurRm('2026-10-12'));
    }

    public function test_signed_negative_per_account_nett_remains_identical_in_landing_kpi_and_all_workbook_sheets(): void
    {
        DB::table('daily_loan_dinamis')->delete();
        DB::table('daily_loan_dinamis')->insert([
            $this->loan('negative-baseline', 'CIF-NEGATIVE', 'CLOSED-NEGATIVE', 200_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null]),
            $this->loan('negative-pt', 'CIF-NEGATIVE', 'NEW-PT', 100_000_000),
            $this->loan('negative-contract', 'CIF-NEGATIVE', 'NEW-CONTRACT', 50_000_000, ['pn_pengelola1' => '000222', 'cabang1' => 'KC NGAWI', 'cabang_normalized' => 'KC NGAWI']),
        ]);
        $landing = $this->landing();
        $kpi = $this->kpi();
        $this->assertMantriParity($landing, $kpi, ['111' => [-100_000_000.0, 1], '222' => [-150_000_000.0, 1]]);
        $this->assertSame(-250_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(-250_000_000.0, (float) data_get($landing, 'mantri_performance.total.daily_net_disbursement.amount'));
        $this->assertSame(150_000_000.0, (float) data_get($landing, 'mantri_performance.total.realization.amount'));
        $this->assertSame(2, data_get($landing, 'mantri_performance.total.net_disbursement.deb'));
        $this->assertLessThan(0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.average_per_hke'));
        foreach (['pt', 'contract'] as $category) {
            $this->assertSame(1, collect(data_get($landing, 'mantri_performance.total.tiers.'.$category.'.buckets'))->firstWhere('key', 'none')['mantri']);
        }
        $workbookPayload = (new ReflectionMethod(KinerjaRmMikroReportController::class, 'nettWorkbookPayload'))
            ->invoke(new KinerjaRmMikroReportController, '2026-10-12', true);
        $workbook = $workbookPayload['nett_workbook']['mantriPerformance'];
        foreach ($workbook['sheet_rows'] as $sheet => $groups) {
            $this->assertSame(-250_000_000.0, (float) collect($groups)->sum('net_disbursement.amount'), $sheet);
            $this->assertSame(2, (int) collect($groups)->sum('net_disbursement.deb'), $sheet);
            $people = collect($groups)->flatMap(fn (array $group): array => $group['members'])->keyBy('pn');
            $this->assertSame(-100_000_000.0, $people->get('111')['net_amount'], $sheet);
            $this->assertSame(-150_000_000.0, $people->get('222')['net_amount'], $sheet);
            $this->assertSame('none', $people->get('111')['nett_tier'], $sheet);
            $this->assertSame('none', $people->get('222')['nett_tier'], $sheet);
        }
    }

    public function test_period_source_people_and_exact_account_lineage_override_stale_database_without_mutating_raw_rows(): void
    {
        DB::table('daily_loan_dinamis')->delete();
        DB::table('daily_loan_dinamis')->insert([
            $this->loan('source-old', 'CIF-SOURCE', 'SOURCE-DB-OLD', 5_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null]),
            $this->loan('source-current', 'CIF-SOURCE', '0001001', 100_000_000, ['periode' => '2026-10-05', 'tgl_realisasi' => '2026-10-05']),
            $this->loan('source-current-asof', 'CIF-SOURCE', '1001', 100_000_000, ['tgl_realisasi' => '2026-10-05']),
            $this->loan('other-old', 'CIF-OTHER', 'OTHER-OLD', 10_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null]),
            $this->loan('other-current', 'CIF-OTHER', 'OTHER-NEW', 50_000_000),
        ]);
        $data = [
            'version' => 1, 'month_start' => '2026-10-01', 'as_of' => '2026-10-12', 'baseline_period' => '2026-09-30', 'sources' => ['test-workbook.xlsx'],
            'people' => [
                ['pn' => '555', 'name' => 'Mantri Sumber PT', 'branch' => 'KC NGAWI', 'unit' => 'UNIT NGAWI SUMBER', 'bc' => '3201', 'category' => 'pt'],
                ['pn' => '556', 'name' => 'Mantri Sumber Nol', 'branch' => 'KC NGAWI', 'unit' => 'UNIT NGAWI SUMBER', 'bc' => '3201', 'category' => 'contract'],
            ],
            'accounts' => [
                ['account' => '1001', 'cif' => 'CIF-SOURCE', 'realization_date' => '2026-10-05', 'pn' => '555', 'name' => 'Mantri Sumber PT', 'branch' => 'KC NGAWI', 'unit' => 'UNIT NGAWI SUMBER', 'bc' => '3201'],
            ],
            'baseline_accounts' => [['account' => 'SOURCE-CLOSED', 'cif' => 'CIF-SOURCE', 'balance' => 120_000_000]],
            'current_accounts' => ['1001'],
        ];
        $path = tempnam(sys_get_temp_dir(), 'mantri_source_parity_');
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        $this->app->instance(MantriProductivityReference::class, new MantriProductivityReference($path));
        $rawLoans = DB::table('daily_loan_dinamis')->orderBy('uniqueid_namareport')->get()->all();
        $rawRoster = DB::table('brihc_pemasar')->orderBy('uniqueid_namareport')->get()->all();
        try {
            $calculator = app(MicroNettDisbursementCalculator::class);
            $accounts = collect($calculator->mantriAccounts('2026-10-12'))->keyBy('account_key');
            $this->assertSame('00000555 - MANTRI SUMBER PT', $accounts['1001']['owner']);
            $this->assertSame(120_000_000.0, $accounts['1001']['previous_os']);
            $this->assertSame(-20_000_000.0, $accounts['1001']['net_amount']);
            $this->assertSame('mantri_productivity_reference', $accounts['1001']['identity_source']);
            $this->assertSame(10_000_000.0, $accounts['OTHER-NEW']['previous_os']);
            $this->assertSame(40_000_000.0, $accounts['OTHER-NEW']['net_amount']);
            $this->assertSame('daily_loan_dinamis', $accounts['OTHER-NEW']['baseline_source']);
            $earlier = collect($calculator->mantriAccounts('2026-10-11'))->keyBy('account_key');
            $this->assertSame('00000555 - MANTRI SUMBER PT', $earlier['1001']['owner']);
            $this->assertSame(5_000_000.0, $earlier['1001']['previous_os']);
            $this->assertSame(95_000_000.0, $earlier['1001']['net_amount']);
            $this->assertSame('daily_loan_dinamis', $earlier['1001']['baseline_source']);
            $landing = $this->landing();
            $kpi = $this->kpi();
            $this->assertMantriParity($landing, $kpi, ['555' => [-20_000_000.0, 1], '556' => [0.0, 0]]);
            $this->assertSame(2, array_sum(data_get($landing, 'mantri_performance.total.headcount')));
            $this->assertSame(2, (int) $kpi['total']['jumlah_mantri']);
            $this->assertSame(2, data_get($landing, 'realization.mantri_roster.kupedes_eligible'));
            $this->assertSame(1, data_get($landing, 'realization.mantri_roster.kupedes_realized'));
            $this->assertSame(1, data_get($landing, 'realization.mantri_roster.kupedes_not_realized'));
            $this->assertSame(['556'], array_column(data_get($landing, 'realization.kupedes_not_realized'), 'pn'));
            $this->assertSame(40_000_000.0, (float) data_get($landing, 'mantri_performance.excluded_non_roster.net_disbursement.amount'));
            $this->assertSame(['KC NGAWI'], collect($kpi['rows'])->pluck('cabang')->unique()->values()->all());
            $contractBuckets = collect(data_get($landing, 'mantri_performance.total.tiers.contract.buckets'))->keyBy('key');
            $this->assertSame(1, $contractBuckets->get('extreme_low')['mantri']);
            $this->assertSame(0, $contractBuckets->get('none')['mantri']);
            $this->assertSame('556', $contractBuckets->get('extreme_low')['people'][0]['pn']);
            $workbook = (new ReflectionMethod(KinerjaRmMikroReportController::class, 'nettWorkbookPayload'))
                ->invoke(new KinerjaRmMikroReportController, '2026-10-12', true);
            $sourceWorkbook = $workbook['nett_workbook']['mantriPerformance'];
            foreach ($sourceWorkbook['sheet_rows'] as $sheet => $groups) {
                $sourcePeople = collect($groups)->flatMap(fn (array $group): array => $group['members'])->keyBy('pn');
                $this->assertSame(0.0, $sourcePeople->get('556')['net_amount'], $sheet);
                $this->assertSame('extreme_low', $sourcePeople->get('556')['nett_tier'], $sheet);
                $this->assertSame('extreme_low', $sourcePeople->get('555')['nett_tier'], $sheet);
            }

            // The next position falls outside this source's as-of date and uses the raw DB references.
            $outside = collect($calculator->mantriAccounts('2026-10-13'))->keyBy('account_key');
            $this->assertSame('000111', $outside['1001']['owner']);
            $this->assertSame(5_000_000.0, $outside['1001']['previous_os']);
            $this->assertSame(95_000_000.0, $outside['1001']['net_amount']);
            $outsideKpi = (new ReflectionMethod(KinerjaRmMikroReportController::class, 'mantriProductivityPayload'))
                ->invoke(new KinerjaRmMikroReportController, '2026-10-13');
            $this->assertNotContains('Mantri Sumber PT', array_column($outsideKpi['rows'], 'nama_mantri'));
            $this->assertSame(135_000_000.0, (float) $outsideKpi['total']['realisasi_os']);
            $versionBefore = ReportCacheVersion::get('pinjaman');
            $calculator->cachedMantriAccounts('2026-10-12');
            $data['baseline_accounts'][0]['balance'] = 150_000_000;
            file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
            $this->app->instance(MantriProductivityReference::class, new MantriProductivityReference($path));
            $refreshed = collect($calculator->cachedMantriAccounts('2026-10-12'))->keyBy('account_key');
            $this->assertSame(150_000_000.0, $refreshed['1001']['previous_os']);
            $this->assertSame(-50_000_000.0, $refreshed['1001']['net_amount']);
            $this->assertMantriParity($this->landing(), $this->kpi(), ['555' => [-50_000_000.0, 1], '556' => [0.0, 0]]);
            $this->assertSame($versionBefore, ReportCacheVersion::get('pinjaman'));
            $this->assertEquals($rawLoans, DB::table('daily_loan_dinamis')->orderBy('uniqueid_namareport')->get()->all());
            $this->assertEquals($rawRoster, DB::table('brihc_pemasar')->orderBy('uniqueid_namareport')->get()->all());
        } finally {
            @unlink($path);
        }
    }

    private function assertMantriParity(array $landing, array $kpi, array $expected): void
    {
        $people = collect(data_get($landing, 'mantri_performance.rows', []))->flatMap(function (array $branch) {
            return collect(['pt', 'contract'])->flatMap(fn (string $category) => collect(data_get($branch, 'tiers.'.$category.'.buckets', []))->flatMap(fn (array $bucket) => $bucket['people']));
        })->keyBy('pn');
        $kpiPeople = collect($kpi['rows'])->keyBy(fn (array $row): string => ltrim($row['pn_mantri'], '0'));

        foreach ($expected as $pn => [$amount, $accounts]) {
            $this->assertSame($amount, (float) data_get($people, $pn.'.net_amount'));
            $this->assertSame($accounts, data_get($people, $pn.'.net_deb'));
            $this->assertSame($amount, (float) data_get($kpiPeople, $pn.'.realisasi_os'));
            $this->assertSame($accounts, (int) data_get($kpiPeople, $pn.'.realisasi_deb'));
        }
        $this->assertSame((float) $kpi['total']['realisasi_os'], (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame((int) $kpi['total']['realisasi_deb'], data_get($landing, 'mantri_performance.total.net_disbursement.deb'));
    }

    private function landing(?array $scope = null): array
    {
        return app(LandingMicroPerformanceService::class)->payload('2026-10-12', $scope);
    }

    private function kpi(): array
    {
        return (new ReflectionMethod(KinerjaRmMikroReportController::class, 'mantriProductivityPayload'))
            ->invoke(new KinerjaRmMikroReportController, '2026-10-12');
    }

    private function seedMantri(): void
    {
        foreach ([['111', 'KC MADIUN', 'PT', 'MANTRI'], ['222', 'KC NGAWI', 'KONTRAK', 'MANTRI'], ['333', 'KC MADIUN', 'PT', 'MANTRI COLLECTION']] as [$pn, $branch, $category, $role]) {
            DB::table('brihc_pemasar')->insert([
                'uniqueid_namareport' => 'roster-'.$pn,
                'pernr' => str_pad($pn, 8, '0', STR_PAD_LEFT),
                'pn_mantri' => str_pad($pn, 8, '0', STR_PAD_LEFT),
                'completename' => 'Mantri '.$pn,
                'esgdesc' => $category,
                'positiondesc' => $role,
                'orgdesc' => 'UNIT '.$pn,
                'psadesc' => $branch,
                'bc' => $branch === 'KC MADIUN' ? '45' : '57',
                'tmt_jabatan' => '2020-01-01',
            ]);
        }
    }

    private function seedBookings(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->loan('closed', 'CIF-X', 'CLOSED', 30_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null, 'segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'KOMERSIAL']),
            $this->loan('open-baseline', 'CIF-X', '000OPEN', 40_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null]),
            // A transferred live baseline account must not become a closed-CIF deduction.
            $this->loan('open-current', 'CIF-TRANSFERRED', 'OPEN', 35_000_000, ['tgl_realisasi' => null, 'segmen_kinerja' => 'CONSUMER', 'cabang1' => 'KC NGAWI']),
            $this->loan('zero-baseline', 'CIF-ZERO', 'ZERO-OLD', 100_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null, 'produk_kinerja' => 'BRIGUNAMIKRO']),
            $this->loan('same-baseline', 'CIF-SAME', 'SAME', 20_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null]),
            $this->loan('a-first', 'CIF-X', '0001001', 100_000_000, ['periode' => '2026-10-05', 'tgl_realisasi' => '2026-10-05']),
            $this->loan('a-current', 'CIF-X', '1001', 120_000_000, ['tgl_realisasi' => '2026-10-05', 'pn_pengelola1' => '000222', 'cabang1' => 'KC NGAWI', 'cabang_normalized' => 'KC NGAWI']),
            // Loan territory differs from the active Mantri placement.
            $this->loan('b-current', 'CIF-X', '1002', 80_000_000, ['cabang1' => 'KC NGAWI', 'cabang_normalized' => 'KC NGAWI']),
            $this->loan('zero-current', 'CIF-ZERO', 'ZERO-NEW', 80_000_000),
            $this->loan('same-current', 'CIF-SAME', 'SAME', 70_000_000),
            // A booking that disappeared from the selected daily position remains MTD.
            $this->loan('missing-current', 'CIF-MISSING', 'MISSING', 25_000_000, ['periode' => '2026-10-04', 'tgl_realisasi' => '2026-10-04']),
            $this->loan('unmatched', 'CIF-UNMATCHED', 'UNMATCHED', 20_000_000, ['pn_pengelola1' => '000999']),
            $this->loan('collection', 'CIF-COLLECTION', 'COLLECTION', 10_000_000, ['pn_pengelola1' => '000333']),
            $this->loan('kur-control', 'CIF-KUR', 'KUR-CONTROL', 200_000_000, ['pn_pengelola1' => '000333', 'produk_kinerja' => 'KURKECIL', 'produk_dashboard' => 'KUR Kecil', 'description' => 'Kredit Mikro - KUR Ritel 2015']),
            $this->loan('new-briguna', 'CIF-BRIGUNA', 'BRIGUNA', 900_000_000, ['produk_kinerja' => 'BRIGUNAMIKRO', 'produk_dashboard' => 'Briguna Mikro']),
        ]);
    }

    private function loan(string $id, string $cif, string $account, float $amount, array $overrides = []): array
    {
        return array_merge([
            'uniqueid_namareport' => $id,
            'periode' => '2026-10-12',
            'tgl_realisasi' => '2026-10-12',
            'cifno' => $cif,
            'cifno_clean' => $cif,
            'nomor_rekening1' => $account,
            'plafon' => $amount,
            'baki_debet1' => $amount,
            'cabang1' => 'KC MADIUN',
            'cabang_normalized' => 'KC MADIUN',
            'kode_cabang1' => '45',
            'unit1' => 'UNIT 111',
            'unit_normalized' => 'UNIT 111',
            'branch1' => '45',
            'branch_normalized' => '45',
            'segmen_kinerja' => 'MICRO',
            'produk_kinerja' => 'KUPEDES',
            'produk_dashboard' => 'Kupedes',
            'description' => 'Kupedes',
            'ln_type' => 'BULANAN',
            'freq_payment' => 1,
            'pn_pengelola1' => '000111',
            'pn_pemrakarsa1' => '000888',
            'pn_pemutus1' => '',
            'pn_pemutus_normalized' => '',
            'kolek' => 1,
        ], $overrides);
    }
}
