<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use App\Support\LandingMicroPerformanceService;
use App\Support\MantriSourceInputStore;
use App\Support\MicroNettDisbursementCalculator;
use App\Support\ReportCacheVersion;
use App\Support\UserBranchScope;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class MantriNettParityTest extends TestCase
{
    private ?string $cohortDirectory = null;

    protected function tearDown(): void
    {
        if ($this->cohortDirectory !== null) {
            File::deleteDirectory($this->cohortDirectory);
        }
        parent::tearDown();
    }

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
        $this->registerCohort('2026-10-04');
        $landing = $this->landing();
        $kpi = $this->kpi();

        $this->assertTrue(data_get($landing, 'meta.available'), (string) data_get($landing, 'meta.error'));
        $this->assertSame(295_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(5, data_get($landing, 'mantri_performance.total.net_disbursement.deb'));
        $this->assertSame(375_000_000.0, (float) data_get($landing, 'mantri_performance.total.realization.amount'));
        $this->assertSame(150_000_000.0, (float) data_get($landing, 'mantri_performance.total.daily_net_disbursement.amount'));
        $this->assertSame(3, data_get($landing, 'mantri_performance.total.daily_net_disbursement.deb'));
        $this->assertSame(230_000_000.0, (float) data_get($landing, 'mantri_performance.total.daily_realization.amount'));
        $this->assertSame(30_000_000.0, (float) data_get($landing, 'mantri_performance.excluded_non_roster.net_disbursement.amount'));

        $this->assertMantriParity($landing, $kpi, ['111' => [175_000_000.0, 4], '222' => [120_000_000.0, 1]]);
        foreach (['KC MADIUN' => 175_000_000.0, 'KC NGAWI' => 120_000_000.0] as $branch => $amount) {
            $landingRow = collect(data_get($landing, 'mantri_performance.rows'))->firstWhere('branch', $branch);
            $this->assertSame($amount, (float) data_get($landingRow, 'net_disbursement.amount'));
            $this->assertSame($amount, (float) collect($kpi['rows'])->where('cabang', $branch)->sum('realisasi_os'));
        }
    }

    public function test_branch_scope_follows_active_mantri_placement_after_loan_branch_transfer(): void
    {
        $this->registerCohort('2026-10-04');
        auth()->setUser(new GenericUser(['id' => '0045']));
        $landing = $this->landing(UserBranchScope::forKey('madiun'));
        $kpi = $this->kpi();

        $this->assertSame(['KC MADIUN'], array_column(data_get($landing, 'mantri_performance.rows'), 'branch'));
        $this->assertSame(175_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(175_000_000.0, (float) $kpi['total']['realisasi_os']);
        $this->assertMantriParity($landing, $kpi, ['111' => [175_000_000.0, 4]]);
    }

    public function test_subsequent_import_refreshes_both_consumers_without_changing_kur_rm(): void
    {
        $this->registerCohort('2026-10-04');
        $calculator = app(MicroNettDisbursementCalculator::class);
        $first = $this->landing();
        $firstKpi = $this->kpi();
        $kurBefore = $calculator->kurRm('2026-10-12');
        $this->assertSame(200_000_000.0, (float) $kurBefore[0]['realisasi_os']);
        $this->assertSame(295_000_000.0, (float) $firstKpi['total']['realisasi_os']);

        DB::table('daily_loan_dinamis')->where('uniqueid_namareport', 'a-current')->update(['plafon' => 200_000_000]);
        ReportCacheVersion::bump('pinjaman');
        $second = $this->landing();

        // Durable last-valid data may be shown while the import-version refresh runs.
        if (data_get($second, 'meta.refresh_pending')) {
            $this->assertSame(data_get($first, 'mantri_performance'), data_get($second, 'mantri_performance'));
            app(DeferredCallbackCollection::class)->invoke();
            $second = $this->landing();
        }

        $this->assertMantriParity($second, $this->kpi(), ['111' => [175_000_000.0, 4], '222' => [200_000_000.0, 1]]);
        $this->assertSame($kurBefore, $calculator->kurRm('2026-10-12'));
        $this->assertSame(375_000_000.0, (float) data_get($second, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(455_000_000.0, (float) data_get($second, 'mantri_performance.total.realization.amount'));
    }

    public function test_baseline_reimport_is_applied_to_landing_and_kpi_using_the_same_version(): void
    {
        $this->registerCohort('2026-10-04');
        $this->landing();
        $this->kpi();

        DB::table('daily_loan_dinamis')->where('uniqueid_namareport', 'zero-baseline')->update(['baki_debet1' => 110_000_000]);
        ReportCacheVersion::bump('pinjaman');
        $landing = $this->landing();
        if (data_get($landing, 'meta.refresh_pending')) {
            app(DeferredCallbackCollection::class)->invoke();
            $landing = $this->landing();
        }

        $this->assertMantriParity($landing, $this->kpi(), ['111' => [175_000_000.0, 4], '222' => [120_000_000.0, 1]]);
        $this->assertSame(295_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
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
        $this->registerCohort('2026-10-04');
        DB::table('brihc_pemasar')->insert([
            'uniqueid_namareport' => 'roster-444', 'pernr' => '000444',
            'completename' => 'Mantri Kategori Belum Terpetakan',
            'positiondesc' => 'MANTRI', 'esgdesc' => 'BELUM TERPETAKAN',
            'orgdesc' => 'UNIT 444', 'psadesc' => 'KC MADIUN',
        ]);
        DB::table('daily_loan_dinamis')->insert($this->loan('unknown-category', 'CIF-UNKNOWN', 'UNKNOWN', 40_000_000, ['pn_pengelola1' => '000444']));
        $landing = $this->landing();
        $kpi = $this->kpi();

        $this->assertSame(335_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(335_000_000.0, (float) $kpi['total']['realisasi_os']);
        $this->assertSame(6, data_get($landing, 'mantri_performance.total.net_disbursement.deb'));
        $this->assertSame(6, (int) $kpi['total']['realisasi_deb']);
        $this->assertSame(40_000_000.0, (float) collect($kpi['rows'])->firstWhere('pn_mantri', '00000444')['realisasi_os']);
    }

    public function test_floored_closed_micro_cif_deduction_is_independent_of_current_row_insertion_order(): void
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

        $this->assertSame(0.0, $reverseAccounts['REVERSE-A']['net_amount']);
        $this->assertSame(70_000_000.0, $reverseAccounts['REVERSE-A']['previous_os']);
        $this->assertSame(0.0, $reverseAccounts['REVERSE-B']['net_amount']);
        $this->assertSame(70_000_000.0, $reverseAccounts['REVERSE-B']['previous_os']);
        foreach ($firstMetrics as $metric) {
            $accounts = collect($firstAccounts)->where('owner', $metric['owner'])
                ->where('cabang', $metric['cabang'])->where('unit', $metric['unit'])->where('branch_code', $metric['branch_code']);
            $this->assertSame((float) $metric['realisasi_os'], (float) $accounts->sum('net_amount'));
            $this->assertSame($metric['realisasi_deb'], $accounts->pluck('account_key')->unique()->count());
        }

        $ids = ['reverse-a', 'reverse-b-current'];
        $reinsert = DB::table('daily_loan_dinamis')->whereIn('uniqueid_namareport', $ids)->get()
            ->reverse()->map(fn ($row): array => array_diff_key((array) $row, ['id' => true]))->all();
        DB::table('daily_loan_dinamis')->whereIn('uniqueid_namareport', $ids)->delete();
        DB::table('daily_loan_dinamis')->insert($reinsert);
        ReportCacheVersion::bump('pinjaman');

        $this->assertSame($firstAccounts, $calculator->cachedMantriAccounts('2026-10-12'));
        $this->assertSame($firstMetrics, $calculator->cachedMantri('2026-10-12'));
        $this->assertSame($kurBefore, $calculator->cachedKurRm('2026-10-12'));
    }

    public function test_negative_gross_minus_baseline_becomes_zero_in_landing_kpi_and_all_workbook_sheets(): void
    {
        DB::table('daily_loan_dinamis')->delete();
        DB::table('daily_loan_dinamis')->insert([
            $this->loan('negative-baseline', 'CIF-NEGATIVE', 'CLOSED-NEGATIVE', 200_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null]),
            $this->loan('negative-pt', 'CIF-NEGATIVE', 'NEW-PT', 100_000_000),
            $this->loan('negative-contract', 'CIF-NEGATIVE', 'NEW-CONTRACT', 50_000_000, ['pn_pengelola1' => '000222', 'cabang1' => 'KC NGAWI', 'cabang_normalized' => 'KC NGAWI']),
        ]);
        $landing = $this->landing();
        $kpi = $this->kpi();
        $this->assertMantriParity($landing, $kpi, ['111' => [0.0, 1], '222' => [0.0, 1]]);
        $this->assertSame(0.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(0.0, (float) data_get($landing, 'mantri_performance.total.daily_net_disbursement.amount'));
        $this->assertSame(150_000_000.0, (float) data_get($landing, 'mantri_performance.total.realization.amount'));
        $this->assertSame(2, data_get($landing, 'mantri_performance.total.net_disbursement.deb'));
        $this->assertSame(0.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.average_per_hke'));
        foreach (['pt', 'contract'] as $category) {
            $this->assertSame(1, collect(data_get($landing, 'mantri_performance.total.tiers.'.$category.'.buckets'))->firstWhere('key', 'extreme_low')['mantri']);
        }
        $workbookPayload = (new ReflectionMethod(KinerjaRmMikroReportController::class, 'nettWorkbookPayload'))
            ->invoke(new KinerjaRmMikroReportController, '2026-10-12', true);
        $workbook = $workbookPayload['nett_workbook']['mantriPerformance'];
        foreach ($workbook['sheet_rows'] as $sheet => $groups) {
            $this->assertSame(0.0, (float) collect($groups)->sum('net_disbursement.amount'), $sheet);
            $this->assertSame(2, (int) collect($groups)->sum('net_disbursement.deb'), $sheet);
            $people = collect($groups)->flatMap(fn (array $group): array => $group['members'])->keyBy('pn');
            $this->assertSame(0.0, $people->get('111')['net_amount'], $sheet);
            $this->assertSame(0.0, $people->get('222')['net_amount'], $sheet);
            $this->assertSame('extreme_low', $people->get('111')['nett_tier'], $sheet);
            $this->assertSame('extreme_low', $people->get('222')['nett_tier'], $sheet);
        }
    }

    public function test_same_pn_negative_and_positive_bookings_are_floored_before_pn_branch_and_workbook_aggregation(): void
    {
        DB::table('daily_loan_dinamis')->delete();
        DB::table('daily_loan_dinamis')->insert([
            $this->loan('mixed-baseline', 'CIF-MIXED', 'CLOSED-MIXED', 200_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null]),
            $this->loan('mixed-negative', 'CIF-MIXED', 'MIXED-NEGATIVE', 100_000_000),
            $this->loan('mixed-positive', 'CIF-MIXED', 'MIXED-POSITIVE', 250_000_000),
            $this->loan('other-positive', 'CIF-OTHER', 'OTHER-POSITIVE', 50_000_000, ['pn_pengelola1' => '000222']),
        ]);
        $accounts = collect(app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-10-12'))->keyBy('account_key');
        $this->assertSame(200_000_000.0, $accounts['MIXED-NEGATIVE']['previous_os']);
        $this->assertSame(200_000_000.0, $accounts['MIXED-POSITIVE']['previous_os']);
        $this->assertSame(0.0, $accounts['MIXED-NEGATIVE']['net_amount']);
        $this->assertSame(50_000_000.0, $accounts['MIXED-POSITIVE']['net_amount']);

        $landing = $this->landing();
        $kpi = $this->kpi();
        $this->assertMantriParity($landing, $kpi, ['111' => [50_000_000.0, 2], '222' => [50_000_000.0, 1]]);
        $this->assertSame(100_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(100_000_000.0, (float) data_get($landing, 'mantri_performance.total.daily_net_disbursement.amount'));
        $this->assertSame(400_000_000.0, (float) data_get($landing, 'mantri_performance.total.realization.amount'));
        $this->assertEqualsWithDelta(100_000_000.0 / data_get($landing, 'mantri_performance.working_days'), data_get($landing, 'mantri_performance.total.net_disbursement.average_per_hke'), 0.01);
        $workbook = (new ReflectionMethod(KinerjaRmMikroReportController::class, 'nettWorkbookPayload'))
            ->invoke(new KinerjaRmMikroReportController, '2026-10-12', true);
        foreach ($workbook['nett_workbook']['mantriPerformance']['sheet_rows'] as $sheet => $groups) {
            $this->assertSame(100_000_000.0, (float) collect($groups)->sum('net_disbursement.amount'), $sheet);
            $this->assertSame(3, (int) collect($groups)->sum('net_disbursement.deb'), $sheet);
            $people = collect($groups)->flatMap(fn (array $group): array => $group['members'])->keyBy('pn');
            $this->assertSame(50_000_000.0, $people->get('111')['net_amount'], $sheet);
            $this->assertSame(2, $people->get('111')['net_deb'], $sheet);
        }
    }

    public function test_closed_only_mtd_booking_still_realizes_its_owner_without_inflating_current_source_realization(): void
    {
        DB::table('daily_loan_dinamis')->delete();
        DB::table('daily_loan_dinamis')->insert([
            $this->loan('historical-baseline', 'CIF-HISTORICAL', 'HISTORICAL-OLD', 120_000_000, [
                'periode' => '2026-09-30', 'tgl_realisasi' => null,
            ]),
            $this->loan('historical-booking', 'CIF-HISTORICAL', 'HISTORICAL-NEW', 100_000_000, [
                'periode' => '2026-10-05', 'tgl_realisasi' => '2026-10-05',
            ]),
            $this->loan('current-non-roster', 'CIF-OTHER', 'OTHER-NEW', 50_000_000, ['pn_pengelola1' => '00000999']),
        ]);

        $this->registerCohort('2026-10-05');
        $landing = $this->landing();
        $kpi = $this->kpi();
        $this->assertMantriParity($landing, $kpi, ['111' => [0.0, 1], '222' => [0.0, 0]]);
        $this->assertSame(2, data_get($landing, 'realization.mantri_roster.kupedes_eligible'));
        $this->assertSame(1, data_get($landing, 'realization.mantri_roster.kupedes_realized'));
        $this->assertSame(1, data_get($landing, 'realization.mantri_roster.kupedes_not_realized'));
        $this->assertSame(['222'], array_column(data_get($landing, 'realization.kupedes_not_realized'), 'pn'));
        $this->assertSame(1, data_get($landing, 'realization.mantri.realized'));
        $this->assertSame(1, data_get($landing, 'realization.mantri.not_realized'));
        $this->assertSame(100_000_000.0, data_get($landing, 'mantri_performance.total.realization.amount'));
        $this->assertSame(0.0, data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(50_000_000.0, data_get($landing, 'mantri_performance.excluded_non_roster.net_disbursement.amount'));
        $this->assertSame(50_000_000.0, data_get($landing, 'realization.total.amount'));
        $this->assertSame(1, data_get($landing, 'realization.total.deb'));
        $kupedes = collect(data_get($landing, 'realization.products'))->firstWhere('label', 'Kupedes');
        $this->assertSame(50_000_000.0, $kupedes['amount']);
        $this->assertSame(1, $kupedes['mantri_realized']);
        $this->assertSame(1, $kupedes['mantri_not_realized']);
    }

    public function test_account_level_source_inputs_and_approved_owner_follow_live_roster_without_mutating_raw_rows(): void
    {
        DB::table('daily_loan_dinamis')->delete();
        DB::table('daily_loan_dinamis')->insert([
            $this->loan('source-current', 'CIF-SOURCE', '0001001', 100_000_000, ['periode' => '2026-10-05', 'tgl_realisasi' => '2026-10-05']),
            $this->loan('source-current-asof', 'CIF-SOURCE', '1001', 100_000_000, ['tgl_realisasi' => '2026-10-05']),
            $this->loan('other-old', 'CIF-OTHER', 'OTHER-OLD', 10_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null]),
            $this->loan('other-current', 'CIF-OTHER', 'OTHER-NEW', 50_000_000),
            $this->loan('source-earlier', 'CIF-SOURCE', '1001', 100_000_000, ['periode' => '2026-10-11', 'tgl_realisasi' => '2026-10-05']),
            $this->loan('source-later', 'CIF-SOURCE', '1001', 100_000_000, ['periode' => '2026-10-13', 'tgl_realisasi' => '2026-10-05']),
            $this->loan('other-later', 'CIF-OTHER', 'OTHER-NEW', 50_000_000, ['periode' => '2026-10-13']),
        ]);
        DB::table('brihc_pemasar')->delete();
        foreach ([['555', 'Mantri Sumber PT', 'PT'], ['556', 'Mantri Sumber Nol', 'KONTRAK']] as [$pn, $name, $status]) {
            DB::table('brihc_pemasar')->insert([
                'uniqueid_namareport' => 'reference_brihc_hc_'.$pn, 'pernr' => str_pad($pn, 8, '0', STR_PAD_LEFT),
                'pn_mantri' => str_pad($pn, 8, '0', STR_PAD_LEFT), 'completename' => $name, 'positiondesc' => 'MANTRI',
                'psadesc' => 'KC NGAWI', 'orgdesc' => 'UNIT NGAWI SUMBER', 'bc' => '3201', 'esgdesc' => $status,
            ]);
        }
        $directory = sys_get_temp_dir().'/mantri-source-parity-'.bin2hex(random_bytes(8));
        $store = new MantriSourceInputStore($directory);
        $this->app->instance(MantriSourceInputStore::class, $store);
        $baseline = [
            'source' => ['source_kind' => 'manual_workbook_baseline'], 'complete_presence' => false,
            'presence' => [], 'bookings' => [], 'unclassified_accounts' => [
                $this->loan('source-unknown', 'CIF-SOURCE', 'SOURCE-CLOSED', 120_000_000, [
                    'periode' => '2026-09-30', 'tgl_realisasi' => null,
                    'description' => null, 'segmen_kinerja' => null, 'produk_kinerja' => null,
                ]),
            ],
        ];
        $store->write('2026-09-30', $baseline);
        $store->write('2026-10-12', [
            'source' => ['source_kind' => 'manual_workbook_position'], 'complete_presence' => true,
            'presence' => ['1001', 'OTHER-NEW'], 'bookings' => [], 'unclassified_accounts' => [],
            'owner_evidence' => [
                ['account' => '1001', 'cif' => 'CIF-SOURCE', 'realization_date' => '2026-10-05',
                    'pn' => '555', 'name' => 'Mantri Sumber PT', 'as_of' => '2026-10-12', 'owner_basis' => 'pn_pemrakarsa1'],
            ],
        ]);
        $rawLoans = DB::table('daily_loan_dinamis')->orderBy('uniqueid_namareport')->get()->all();
        $rawRoster = DB::table('brihc_pemasar')->orderBy('uniqueid_namareport')->get()->all();
        try {
            $calculator = app(MicroNettDisbursementCalculator::class);
            $accounts = collect($calculator->mantriAccounts('2026-10-12'))->keyBy('account_key');
            $this->assertSame('00000555 - MANTRI SUMBER PT', $accounts['1001']['owner']);
            $this->assertSame(120_000_000.0, $accounts['1001']['previous_os']);
            $this->assertSame(0.0, $accounts['1001']['net_amount']);
            $this->assertSame('mantri_origination_ledger', $accounts['1001']['identity_source']);
            $this->assertSame('mantri_source_input', $accounts['1001']['baseline_source']);
            $this->assertSame(10_000_000.0, $accounts['OTHER-NEW']['previous_os']);
            $this->assertSame(40_000_000.0, $accounts['OTHER-NEW']['net_amount']);
            $this->assertSame('daily_loan_dinamis', $accounts['OTHER-NEW']['baseline_source']);
            $earlier = collect($calculator->mantriAccounts('2026-10-11'))->keyBy('account_key');
            $this->assertSame('00000111', $earlier['1001']['owner']);
            $this->assertSame(120_000_000.0, $earlier['1001']['previous_os']);
            $this->assertSame(0.0, $earlier['1001']['net_amount']);
            $this->assertSame('daily_loan_dinamis', $earlier['1001']['identity_source']);
            $this->assertSame('mantri_source_input', $earlier['1001']['baseline_source']);
            $landing = $this->landing();
            $kpi = $this->kpi();
            $this->assertMantriParity($landing, $kpi, ['555' => [0.0, 1], '556' => [0.0, 0]]);
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
            foreach ($workbook['nett_workbook']['mantriPerformance']['sheet_rows'] as $sheet => $groups) {
                $people = collect($groups)->flatMap(fn (array $group): array => $group['members'])->keyBy('pn');
                $this->assertSame(0.0, $people->get('555')['net_amount'], $sheet);
                $this->assertSame(0.0, $people->get('556')['net_amount'], $sheet);
                $this->assertSame('extreme_low', $people->get('555')['nett_tier'], $sheet);
                $this->assertSame('extreme_low', $people->get('556')['nett_tier'], $sheet);
            }

            // Approved booking credit persists after later stock ownership changes.
            $later = collect($calculator->mantriAccounts('2026-10-13'))->keyBy('account_key');
            $this->assertSame('00000555 - MANTRI SUMBER PT', $later['1001']['owner']);
            $this->assertSame(120_000_000.0, $later['1001']['previous_os']);
            $this->assertSame(0.0, $later['1001']['net_amount']);
            $this->assertSame('mantri_origination_ledger', $later['1001']['identity_source']);
            $this->assertSame('mantri_source_input', $later['1001']['baseline_source']);

            $versionBefore = ReportCacheVersion::get('pinjaman');
            $calculator->cachedMantriAccounts('2026-10-12');
            $baseline['unclassified_accounts'][0]['baki_debet1'] = 150_000_000;
            $store->write('2026-09-30', $baseline);
            $this->assertNotSame($versionBefore, ReportCacheVersion::get('pinjaman'));
            $refreshed = collect($calculator->cachedMantriAccounts('2026-10-12'))->keyBy('account_key');
            $this->assertSame(150_000_000.0, $refreshed['1001']['previous_os']);
            $this->assertSame(0.0, $refreshed['1001']['net_amount']);
            $freshLanding = $this->landing();
            if (data_get($freshLanding, 'meta.refresh_pending')) {
                app(DeferredCallbackCollection::class)->invoke();
                $freshLanding = $this->landing();
            }
            $this->assertMantriParity($freshLanding, $this->kpi(), ['555' => [0.0, 1], '556' => [0.0, 0]]);
            $versionAfter = ReportCacheVersion::get('pinjaman');
            $store->write('2026-09-30', $baseline);
            $this->assertSame($versionAfter, ReportCacheVersion::get('pinjaman'));
            $this->assertEquals($rawLoans, DB::table('daily_loan_dinamis')->orderBy('uniqueid_namareport')->get()->all());
            $this->assertEquals($rawRoster, DB::table('brihc_pemasar')->orderBy('uniqueid_namareport')->get()->all());
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            unlink($directory.'/.write.lock');
            rmdir($directory);
        }
    }

    private function registerCohort(string $period): void
    {
        $this->cohortDirectory = storage_path('framework/testing/mantri-parity-cohort-'.bin2hex(random_bytes(8)));
        $store = new MantriSourceInputStore($this->cohortDirectory);
        $this->app->instance(MantriSourceInputStore::class, $store);
        $store->captureDailyLoanPeriod($period);
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
            $this->loan('open-baseline', 'CIF-X', '0009001', 40_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null]),
            // A transferred account is still present and must not be treated as closed.
            $this->loan('open-current', 'CIF-TRANSFERRED', '9001', 35_000_000, ['tgl_realisasi' => null, 'segmen_kinerja' => 'CONSUMER', 'cabang1' => 'KC NGAWI']),
            $this->loan('zero-baseline', 'CIF-ZERO', 'ZERO-OLD', 100_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null, 'produk_kinerja' => 'BRIGUNAMIKRO']),
            $this->loan('same-baseline', 'CIF-SAME', 'SAME', 20_000_000, ['periode' => '2026-09-30', 'tgl_realisasi' => null]),
            $this->loan('a-first', 'CIF-X', '0001001', 100_000_000, ['periode' => '2026-10-05', 'tgl_realisasi' => '2026-10-05']),
            $this->loan('a-current', 'CIF-X', '1001', 120_000_000, ['tgl_realisasi' => '2026-10-05', 'pn_pengelola1' => '000222', 'cabang1' => 'KC NGAWI', 'cabang_normalized' => 'KC NGAWI']),
            // Loan territory differs from the active Mantri placement.
            $this->loan('b-current', 'CIF-X', '1002', 80_000_000, ['cabang1' => 'KC NGAWI', 'cabang_normalized' => 'KC NGAWI']),
            $this->loan('zero-current', 'CIF-ZERO', 'ZERO-NEW', 80_000_000),
            $this->loan('same-current', 'CIF-SAME', 'SAME', 70_000_000),
            // MTD keeps a completed booking after its account disappears from the stock position.
            $this->loan('missing-current', 'CIF-MISSING', 'MISSING', 25_000_000, ['periode' => '2026-10-04', 'tgl_realisasi' => '2026-10-04']),
            $this->loan('unmatched', 'CIF-UNMATCHED', 'UNMATCHED', 20_000_000, ['pn_pengelola1' => '000999']),
            $this->loan('collection', 'CIF-COLLECTION', 'COLLECTION', 10_000_000, ['pn_pengelola1' => '000333']),
            $this->loan('kur-control', 'CIF-KUR', 'KUR-CONTROL', 200_000_000, ['pn_pengelola1' => '000333', 'produk_kinerja' => 'KURKECIL', 'produk_dashboard' => 'KUR Kecil', 'description' => 'Kredit Mikro - KUR Ritel 2015']),
            $this->loan('new-briguna', 'CIF-BRIGUNA', 'BRIGUNA', 900_000_000, ['produk_kinerja' => 'BRIGUNAMIKRO', 'produk_dashboard' => 'Briguna Mikro']),
        ]);
    }

    private function loan(string $id, string $cif, string $account, float $amount, array $overrides = []): array
    {
        $overrides += ['pn_pemrakarsa1' => $overrides['pn_pengelola1'] ?? '000111'];

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
            'pn_pemrakarsa1' => '000111',
            'pn_pemutus1' => '',
            'pn_pemutus_normalized' => '',
            'kolek' => 1,
        ], $overrides);
    }
}
