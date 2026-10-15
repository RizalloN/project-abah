<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Reports\MantriIncentiveService;
use App\Support\ReportCacheVersion;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InsentifMantriPerformanceTest extends TestCase
{
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-31 10:00:00');
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode');
            foreach (['segmen_kinerja', 'produk_kinerja', 'description', 'cabang1', 'unit1', 'pn_pengelola1', 'pn_pemrakarsa1', 'nomor_rekening1', 'cifno', 'nama_debitur1', 'kolek'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->date('tgl_realisasi')->nullable();
        });
        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            foreach (['completename', 'pernr', 'pn_mantri', 'esgdesc', 'psadesc', 'orgdesc', 'positiondesc', 'bc', 'tmt_jabatan'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Cache::flush();
    }

    protected function tearDown(): void
    {
        DB::disableQueryLog();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_warm_scoped_build_keeps_exact_payload_without_reading_loan_or_roster_again(): void
    {
        $this->seedQuarter();
        $this->login('area6');
        $cold = $this->service()->build('2026-12-31');
        $this->assertSame(1_010_000_000.0, (float) $cold['rows'][0]['nd_avg']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $warm = $this->service()->build('2026-12-31');
        $queries = DB::getQueryLog();

        $this->assertSame($cold, $warm);
        $this->assertSame([], $this->sourceQueries($queries), 'A warm request must reuse the assembled result rather than fetch loan/roster rows again.');
    }

    public function test_import_version_refreshes_late_baseline_current_booking_and_mob_measurement(): void
    {
        $this->seedQuarter();
        $this->login('area6');
        $before = $this->service()->build('2026-12-31');
        $this->assertTrue($before['rows'][0]['eligible']);

        DB::table('daily_loan_dinamis')->where('nomor_rekening1', 'OLD')->update(['baki_debet1' => 20_000_000]);
        DB::table('daily_loan_dinamis')->where('nomor_rekening1', 'DEC')->update(['plafon' => 970_000_000]);
        DB::table('daily_loan_dinamis')->where('nomor_rekening1', 'VINTAGE')->where('periode', '2026-07-31')->update(['kolek' => '3']);
        ReportCacheVersion::bump('pinjaman');

        $after = $this->service()->build('2026-12-31');
        $row = $after['rows'][0];
        $this->assertSame(980_000_000.0, (float) $row['nd']['2026-10']);
        $this->assertSame(970_000_000.0, (float) $row['nd']['2026-12']);
        $this->assertSame(100.0, (float) $row['mob_ratio']);
        $this->assertFalse($row['eligible']);
        $this->assertSame(0.0, (float) $row['nominal']);
        $export = collect($this->service()->nominatif('2026-12-31', 'nd'))->keyBy('account');
        $this->assertSame(980_000_000.0, (float) $export['OCT']['net']);
        $this->assertSame(970_000_000.0, (float) $export['DEC']['net']);
    }

    public function test_same_service_and_shared_cache_keep_area_and_branch_results_isolated(): void
    {
        $this->person('111111', 'KC Madiun');
        $this->person('222222', 'KC Ngawi');
        $this->loan('2026-12-31', 'M1', 'M1', 900_000_000, '2026-12-01');
        $this->loan('2026-12-31', 'N1', 'N1', 800_000_000, '2026-12-01', ['pn_pemrakarsa1' => '222222', 'cabang1' => 'KC Ngawi']);
        $this->loan('2026-12-31', 'OUTM', 'OUTM', 77_000_000, '2026-12-01', ['pn_pemrakarsa1' => '999999']);
        $this->loan('2026-12-31', 'OUTN', 'OUTN', 88_000_000, '2026-12-01', ['pn_pemrakarsa1' => '888888', 'cabang1' => 'KC Ngawi']);
        $this->loan('2026-12-31', 'BLANKM', 'BLANKM', 11_000_000, '2026-12-01', ['pn_pemrakarsa1' => '', 'pn_pengelola1' => '']);
        $this->loan('2026-12-31', 'BLANKN', 'BLANKN', 22_000_000, '2026-12-01', ['pn_pemrakarsa1' => '', 'pn_pengelola1' => '', 'cabang1' => 'KC Ngawi']);
        $service = $this->service();

        $this->login('area6');
        $area = $service->build('2026-12-31');
        $this->login('madiun');
        $madiun = $service->build('2026-12-31');
        $this->login('ngawi');
        $ngawi = $service->build('2026-12-31');
        $this->login('area6');
        $areaAgain = $service->build('2026-12-31');

        $this->assertSame($area, $areaAgain);
        $this->assertSame(['111111', '222222'], array_column($area['rows'], 'pn'));
        $this->assertSame(['111111'], array_column($madiun['rows'], 'pn'));
        $this->assertSame(['222222'], array_column($ngawi['rows'], 'pn'));
        $this->assertSame(77_000_000.0, (float) $madiun['outside_roster']['nd']);
        $this->assertSame(11_000_000.0, (float) $madiun['outside_roster']['no_pn_nd']);
        $this->assertSame(88_000_000.0, (float) $ngawi['outside_roster']['nd']);
        $this->assertSame(22_000_000.0, (float) $ngawi['outside_roster']['no_pn_nd']);
    }

    public function test_mob_union_preserves_booking_only_measure_only_account_variants_and_ownership(): void
    {
        $this->loan('2026-01-31', 'BOOKING', 'C1', 100_000_000, '2026-01-10');
        $this->loan('2026-07-31', 'MEASURE', 'C2', 200_000_000, '2026-01-11', ['pn_pemrakarsa1' => '222222', 'kolek' => '3']);
        $this->loan('2026-01-31', '0000123', 'C3', 80_000_000, '2026-01-12');
        $this->loan('2026-07-31', '123', 'C3', 90_000_000, '2026-01-12', ['pn_pemrakarsa1' => '222222', 'kolek' => '2']);
        $this->loan('2026-07-31', '000123', 'C3', 85_000_000, '2026-01-12', ['pn_pemrakarsa1' => '', 'pn_pengelola1' => '333333', 'kolek' => '4']);
        $this->loan('2026-07-31', 'EXCLUDED', 'C4', 999_000_000, '2026-01-12', ['produk_kinerja' => 'KURMIKRO', 'description' => 'Kredit Mikro KUR Ritel 2015']);

        $rows = collect($this->service()->mobBookings($this->mobMonth()))->keyBy('account');

        $this->assertSame(['BOOKING', '123', 'MEASURE'], $rows->keys()->map(fn ($account): string => (string) $account)->all());
        $this->assertNull($rows['BOOKING']['kolek_mob']);
        $this->assertSame(0.0, (float) $rows['BOOKING']['pemburukan']);
        $this->assertSame('111111', $rows['BOOKING']['pn']);
        $this->assertSame('222222', $rows['MEASURE']['pn']);
        $this->assertSame(200_000_000.0, (float) $rows['MEASURE']['pemburukan']);
        $this->assertSame('222222', $rows['123']['pn']);
        $this->assertSame(90_000_000.0, (float) $rows['123']['plafon']);
        $this->assertSame(4, $rows['123']['kolek_mob']);
        $this->assertSame(90_000_000.0, (float) $rows['123']['pemburukan']);
    }

    public function test_mob_retains_measure_only_cohort_when_booking_position_is_unavailable(): void
    {
        $this->loan('2026-07-31', 'LATE', 'C1', 125_000_000, '2026-01-10', ['kolek' => '2']);
        $month = $this->mobMonth();
        $month['booking_position'] = null;
        $rows = $this->service()->mobBookings($month);

        $this->assertCount(1, $rows);
        $this->assertSame('LATE', $rows[0]['account']);
        $this->assertSame(125_000_000.0, (float) $rows[0]['pemburukan']);
        $month['start'] = '2026-02-01';
        $month['end'] = '2026-02-28';
        $month['key'] = '2026-02';
        $this->assertSame([], $this->service()->mobBookings($month));
    }

    public function test_shared_mob_source_position_is_fetched_once_for_all_booking_cohorts(): void
    {
        $this->person('111111', 'KC Madiun');
        $this->loan('2026-07-31', 'JAN', 'C1', 100_000_000, '2026-01-10');
        $this->loan('2026-07-31', 'FEB', 'C2', 200_000_000, '2026-02-10', ['kolek' => '2']);
        $this->loan('2026-07-31', 'MAR', 'C3', 300_000_000, '2026-03-10');
        $this->login('area6');

        DB::enableQueryLog();
        $payload = $this->service()->build('2026-07-31');
        $cohortQueries = array_values(array_filter($this->sourceQueries(DB::getQueryLog()), fn (array $query): bool => str_contains($query['query'], 'kolek')));

        $this->assertCount(1, $cohortQueries, 'MOB cohorts sharing a position should read its source rows once.');
        $this->assertSame(600_000_000.0, (float) $payload['rows'][0]['mob_plafon']);
        $this->assertSame(200_000_000.0, (float) $payload['rows'][0]['mob_pemburukan']);
        $this->assertSame(3, count($this->service()->nominatif('2026-07-31', 'mob6')));
    }

    public function test_import_during_mob_read_does_not_cache_old_rows_under_the_new_source_version(): void
    {
        $this->loan('2026-07-31', 'CORRECTED', 'C1', 100_000_000, '2026-01-10');
        $month = $this->mobMonth();
        $month['booking_position'] = null;
        $corrected = false;
        DB::listen(function (QueryExecuted $query) use (&$corrected): void {
            if ($corrected || ! str_contains($query->sql, 'kolek') || preg_match('/\bfrom\s+["`]?daily_loan_dinamis\b/i', $query->sql) !== 1) {
                return;
            }
            // QueryExecuted fires after the SELECT has fetched its rows, before MOB caches them.
            $corrected = true;
            DB::table('daily_loan_dinamis')->where('nomor_rekening1', 'CORRECTED')->update(['plafon' => 200_000_000, 'kolek' => '3']);
            ReportCacheVersion::bump('pinjaman');
        });

        DB::enableQueryLog();
        $first = $this->service()->mobBookings($month);
        $second = $this->service()->mobBookings($month);
        $cohortQueries = array_values(array_filter($this->sourceQueries(DB::getQueryLog()), fn (array $query): bool => str_contains($query['query'], 'kolek')));

        $this->assertTrue($corrected);
        $this->assertSame(100_000_000.0, (float) $first[0]['plafon']);
        $this->assertSame(0.0, (float) $first[0]['pemburukan']);
        $this->assertSame(200_000_000.0, (float) $second[0]['plafon']);
        $this->assertSame(200_000_000.0, (float) $second[0]['pemburukan']);
        $this->assertCount(2, $cohortQueries, 'The next request must read corrected source rows after the import version changes.');
    }

    public function test_quarter_navigation_limits_position_options_and_resolves_latest_available_position(): void
    {
        $this->person('111111', 'KC Madiun');
        $this->loan('2026-03-15', 'MARCH1', 'C1', 100_000_000, '2026-03-01');
        $this->loan('2026-03-31', 'MARCH2', 'C2', 200_000_000, '2026-03-20');
        $this->loan('2026-12-31', 'DEC', 'C3', 900_000_000, '2026-12-01');
        $this->login('area6');

        $response = $this->get(route('report.kpi.insentif-mantri', ['kuartal' => '2026-Q1', 'periode' => '2026-12-31']));
        $response->assertOk();
        $response->assertViewHas('selectedQuarter', '2026-Q1');
        $response->assertViewHas('selectedPeriod', '2026-03-31');
        $response->assertViewHas('quarterPeriods', ['2026-03-31', '2026-03-15']);
        $response->assertViewHas('window', fn (array $window): bool => $window['quarter'] === 1
            && $window['months'][0]['position'] === null
            && $window['months'][1]['position'] === null);
        $response->assertSee('Data belum tersedia');
        $response->assertSee('Riwayat MOB 6 belum lengkap');

        $earlier = $this->get(route('report.kpi.insentif-mantri', ['kuartal' => '2026-Q1', 'periode' => '2026-03-15']));
        $earlier->assertOk();
        $earlier->assertViewHas('selectedPeriod', '2026-03-15');
        $fallback = $this->get(route('report.kpi.insentif-mantri', ['kuartal' => '2026-Q2', 'periode' => '2026-12-31']));
        $fallback->assertOk();
        $fallback->assertViewHas('selectedPeriod', '2026-12-31');

        $export = $this->get(route('report.kpi.insentif-mantri.export', ['kuartal' => '2026-Q1']));
        $export->assertOk();
        $this->assertStringContainsString('MARCH2', $export->streamedContent());
        $this->assertStringNotContainsString("'DEC", $export->streamedContent());
    }

    public function test_q2_and_q3_monitoring_and_exports_use_only_the_selected_quarter(): void
    {
        $this->person('111111', 'KC Madiun');
        foreach ([4, 5, 6, 7, 8, 9, 10] as $month) {
            $date = Carbon::create(2026, $month, 1);
            $this->loan($date->copy()->endOfMonth()->toDateString(), 'BOOK'.$month, 'CIF'.$month, $month * 100_000_000, $date->toDateString());
        }
        $this->login('area6');

        foreach (['2026-Q2' => ['2026-06-30', ['2026-04', '2026-05', '2026-06'], 1_500_000_000], '2026-Q3' => ['2026-09-30', ['2026-07', '2026-08', '2026-09'], 2_400_000_000]] as $quarter => [$position, $months, $net]) {
            $response = $this->get(route('report.kpi.insentif-mantri', ['kuartal' => $quarter, 'periode' => '2026-10-31']));
            $response->assertOk();
            $response->assertViewHas('selectedQuarter', $quarter);
            $response->assertViewHas('selectedPeriod', $position);
            $response->assertViewHas('window', fn (array $window): bool => array_column($window['months'], 'key') === $months);
            $response->assertViewHas('rows', fn ($rows): bool => (float) $rows->first()['nd_total'] === (float) $net);

            $export = $this->get(route('report.kpi.insentif-mantri.export', ['kuartal' => $quarter, 'periode' => '2026-10-31']));
            $export->assertOk();
            $this->assertStringContainsString("'BOOK".(int) substr($position, 5, 2), $export->streamedContent());
            $this->assertStringNotContainsString("'BOOK10", $export->streamedContent());
        }
    }

    private function seedQuarter(): void
    {
        $this->person('111111', 'KC Madiun');
        $this->loan('2026-09-30', 'OLD', 'C1', 50_000_000, '2025-01-01', ['baki_debet1' => 10_000_000]);
        $this->loan('2026-10-31', 'OCT', 'C1', 1_000_000_000, '2026-10-10');
        $this->loan('2026-11-30', 'NOV', 'C2', 1_100_000_000, '2026-11-10');
        $this->loan('2026-12-31', 'DEC', 'C3', 940_000_000, '2026-12-10');
        $this->loan('2026-01-31', 'VINTAGE', 'C4', 100_000_000, '2026-01-15');
        $this->loan('2026-07-31', 'VINTAGE', 'C4', 100_000_000, '2026-01-15');
    }

    private function person(string $pn, string $branch): void
    {
        DB::table('brihc_pemasar')->insert([
            'uniqueid_namareport' => 'M'.$pn, 'completename' => 'Mantri '.$pn,
            'pernr' => $pn, 'pn_mantri' => $pn, 'esgdesc' => 'PT (peserta pens.)',
            'psadesc' => $branch, 'orgdesc' => 'UNIT TEST', 'positiondesc' => 'MANTRI',
        ]);
    }

    private function loan(string $period, string $account, string $cif, float $plafon, string $bookingDate, array $overrides = []): void
    {
        DB::table('daily_loan_dinamis')->insert(array_replace([
            'uniqueid_namareport' => 'R'.(++$this->sequence), 'periode' => $period,
            'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KUPEDES', 'description' => 'Kredit Mikro - Kupedes',
            'cabang1' => 'KC Madiun', 'unit1' => 'UNIT TEST', 'pn_pengelola1' => '111111', 'pn_pemrakarsa1' => '111111',
            'nomor_rekening1' => $account, 'cifno' => $cif, 'nama_debitur1' => 'Debitur '.$cif,
            'plafon' => $plafon, 'baki_debet1' => $plafon, 'kolek' => '1', 'tgl_realisasi' => $bookingDate,
        ], $overrides));
    }

    private function login(string $scope): void
    {
        $user = new User;
        $user->forceFill(['id' => 1, 'pn' => '0045', 'name' => 'Tester', 'role' => 'admin', 'branch_scope' => $scope]);
        $this->actingAs($user);
    }

    private function service(): MantriIncentiveService
    {
        return app(MantriIncentiveService::class);
    }

    private function mobMonth(): array
    {
        return ['key' => '2026-01', 'start' => '2026-01-01', 'end' => '2026-01-31', 'booking_position' => '2026-01-31', 'measure_position' => '2026-07-31'];
    }

    private function sourceQueries(array $queries): array
    {
        return array_values(array_filter($queries, fn (array $query): bool => preg_match('/\bfrom\s+["`]?daily_loan_dinamis\b|\bfrom\s+["`]?brihc_pemasar\b/i', $query['query']) === 1));
    }
}
