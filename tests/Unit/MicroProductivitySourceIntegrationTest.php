<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use App\Models\User;
use App\Support\MantriPerformanceBasis;
use App\Support\MantriProductivityReference;
use App\Support\MicroNettDisbursementCalculator;
use App\Support\MicroRmProductivityReference;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class MicroProductivitySourceIntegrationTest extends TestCase
{
    private array $temporaryPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$values) => implode('', $values), -1);
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            foreach ([
                'periode', 'tgl_realisasi', 'cabang_normalized', 'cabang1', 'unit_normalized', 'unit1',
                'branch_normalized', 'branch1', 'pn_pemrakarsa1', 'pn_pengelola1', 'nomor_rekening1',
                'cifno', 'segmen_kinerja', 'produk_kinerja', 'description', 'rm_normalized',
            ] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->unsignedTinyInteger('kolek')->nullable();
        });
        Schema::create('performance_rm_snapshots', function (Blueprint $table): void {
            $table->id();
            foreach (['periode', 'segmen', 'produk', 'cabang'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            foreach (['pernr', 'pn_mantri', 'completename', 'positiondesc', 'psadesc', 'orgdesc', 'bc', 'esgdesc'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Cache::flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            unlink($path);
        }
        parent::tearDown();
    }

    public function test_rm_reference_is_explicit_in_tests_and_only_applies_at_its_exact_position(): void
    {
        $this->assertNull((new MicroRmProductivityReference)->asOf());
        $this->assertNull((new MicroRmProductivityReference)->dataForPeriod('2026-10-17'));
        $path = $this->jsonPath($this->rmFixture());
        $reference = new MicroRmProductivityReference($path);
        $this->assertSame('2026-10-17', $reference->asOf());
        $this->assertNotNull($reference->dataForPeriod('2026-10-17'));
        $this->assertNull($reference->dataForPeriod('2026-10-16'));
        $this->assertNull($reference->loanRows('2026-10-18'));
        $this->assertSame(hash_file('sha256', $path), $reference->fingerprint());
    }

    public function test_rm_benchmark_cannot_supply_live_loans_or_override_database_gross_realization(): void
    {
        $this->bindRmReference();
        DB::table('daily_loan_dinamis')->insert($this->baseline('OLD', 'CIF-A', 400_000_000));
        DB::table('daily_loan_dinamis')->insert($this->dbRow('2026-10-16', 'LIVE', 125_000_000));

        $calculator = app(MicroNettDisbursementCalculator::class);
        $this->assertSame([], $calculator->kurRm('2026-10-17'));
        $live = $calculator->kurRm('2026-10-16');
        $this->assertCount(1, $live);
        $this->assertSame(1, $live[0]['realisasi_deb']);
        $this->assertSame(125_000_000.0, $live[0]['realisasi_os']);
        $this->assertSame('00360186 - LIVE RM', $live[0]['rm']);
    }

    public function test_database_position_and_gross_remain_scoped_when_switching_branch_users(): void
    {
        $this->bindRmReference();
        DB::table('daily_loan_dinamis')->insert([
            $this->dbRow('2026-10-17', 'MADIUN', 125_000_000, ['baki_debet1' => 80_000_000]),
            $this->dbRow('2026-10-17', 'NGAWI', 55_000_000, [
                'cabang1' => 'KC NGAWI', 'cabang_normalized' => 'KC NGAWI',
                'unit1' => 'KC NGAWI', 'unit_normalized' => 'KC NGAWI',
                'baki_debet1' => 25_000_000,
            ]),
        ]);
        $controller = new KinerjaRmMikroReportController;
        foreach (['madiun' => 125_000_000.0, 'ngawi' => 55_000_000.0] as $branch => $gross) {
            $rows = UserBranchScope::runAs(UserBranchScope::forKey($branch), fn () => $this->invoke($controller, 'snapshotAggregates', '2026-10-17', ['rm']));
            $this->assertCount(1, $rows);
            $row = $rows->first();
            $this->assertSame('KC '.strtoupper($branch), $row['cabang']);
            $this->assertSame($gross, $row['realisasi_os']);
            $this->assertSame(1, (int) $row['total_deb']);
            $this->assertSame($branch === 'madiun' ? 80_000_000.0 : 25_000_000.0, (float) $row['total_os']);
        }
    }

    public function test_period_resolution_ignores_benchmark_date_and_includes_real_source_without_snapshots(): void
    {
        $this->bindRmReference();
        DB::table('performance_rm_snapshots')->insert(['periode' => '2026-10-16', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO', 'cabang' => 'KC MADIUN']);
        $controller = new KinerjaRmMikroReportController;
        $this->assertSame('2026-10-16', $this->invoke($controller, 'resolveEmbeddedReadyPeriod', '2026-10-19', false));
        $this->assertSame('2026-10-16', $this->invoke($controller, 'resolveEmbeddedReadyPeriod', '2026-10-16', false));
        DB::table('daily_loan_dinamis')->insert($this->dbRow('2026-10-18', 'NEW-POSITION', 100_000_000));
        Cache::flush();
        $this->assertSame('2026-10-18', $this->invoke($controller, 'resolveEmbeddedReadyPeriod', '2026-10-19', false));
        $this->assertSame(['2026-10-18', '2026-10-16'], $this->invoke($controller, 'fetchAvailablePeriods')->all());
    }

    public function test_unknown_database_owner_remains_in_amounts_without_becoming_a_roster_person(): void
    {
        $this->bindRmReference(true);
        DB::table('daily_loan_dinamis')->insert([
            $this->dbRow('2026-10-17', 'KNOWN', 125_000_000),
            $this->dbRow('2026-10-17', 'UNKNOWN', 10_000_000, [
                'pn_pengelola1' => '00999999 - UNKNOWN', 'rm_normalized' => '00999999 - UNKNOWN',
            ]),
        ]);
        $controller = new KinerjaRmMikroReportController;
        UserBranchScope::runAs(UserBranchScope::forKey('madiun'), function () use ($controller): void {
            $byRm = $this->invoke($controller, 'snapshotAggregates', '2026-10-17', ['rm']);
            $this->assertCount(2, $byRm);
            $this->assertSame(2, (int) $byRm->sum('total_deb'));
            $this->assertSame(135_000_000.0, (float) $byRm->sum('total_os'));
            $unassigned = $byRm->first(fn (array $row): bool => $row['rm'] === '00999999 - UNKNOWN');
            $this->assertSame(1, (int) $unassigned['total_deb']);
            $this->assertSame(10_000_000.0, (float) $unassigned['total_os']);
            $this->assertSame(10_000_000.0, $unassigned['realisasi_os']);
            $byUnit = $this->invoke($controller, 'snapshotAggregates', '2026-10-17', ['unit']);
            $this->assertCount(1, $byUnit);
            $this->assertSame(2, (int) $byUnit->first()['total_deb']);
            $this->assertSame(135_000_000.0, (float) $byUnit->first()['total_os']);

            $payload = $this->invoke($controller, 'nettWorkbookPayload', '2026-10-17', false);
            $workbook = $payload['nett_workbook']['rmKurProductivity'];
            $this->assertSame(135_000_000.0, $workbook['total']['realisasi_os']);
            $this->assertSame(10_000_000.0, $workbook['unassigned_amount']);
            $this->assertFalse(collect($workbook['rows'])->contains('pn', '00999999'));
            $this->assertTrue(collect($workbook['rows'])->contains('pn', '00360186'));
            $this->assertSame(count($workbook['rows']), $workbook['distribution']['total']['rm_count']);
        });
        UserBranchScope::runAs(UserBranchScope::forKey('ngawi'), function () use ($controller): void {
            $rows = $this->invoke($controller, 'snapshotAggregates', '2026-10-17', ['rm']);
            $this->assertCount(0, $rows);
        });
    }

    public function test_rm_controller_renders_database_position_with_personnel_only_reference_metadata(): void
    {
        $this->bindRmReference(true);
        DB::table('daily_loan_dinamis')->insert($this->dbRow('2026-10-16', 'LIVE', 125_000_000));
        DB::table('performance_rm_snapshots')->insert(['periode' => '2026-10-16', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO', 'cabang' => 'KC MADIUN']);
        $this->actingAs(new User(['pn' => '0045', 'name' => 'Branch Madiun', 'role' => 'user']));
        $view = (new KinerjaRmMikroReportController)->index(Request::create('/report/kinerja-rm-mikro', 'GET', [
            'periode' => '2026-10-17', 'kategori_rm' => 'rm_mikro_kur', 'kategori_report' => 'nett_disbursement',
        ]));
        $source = $view->getData()['payload']['nett_workbook']['rmKurProductivity']['reference_sources'][0];
        $this->assertSame('KC MADIUN', $source['branch']);
        $this->assertSame('2026-10-16', $view->getData()['selectedPeriod']);
        $this->assertNotSame('fixture.xlsx', $source['file']);
        $html = $view->render();
        $this->assertStringContainsString('Nett Disbursement', $html);
        $this->assertStringNotContainsString('fixture.xlsx', $html);
        $this->assertSame(125_000_000.0, $view->getData()['payload']['nett_workbook']['rmKurProductivity']['total']['realisasi_os']);
        $this->assertStringContainsString('Muh Ashar Fatoni', $html);
        $this->assertStringNotContainsString('Ngawi RM', $html);
    }

    public function test_authoritative_hr_tumbuh_roster_replaces_workbook_roster_and_excludes_briguna_and_recovery(): void
    {
        $this->bindMantriReference();
        foreach (['MANTRI' => '101', 'MANTRI BRIGUNA' => '102', 'MANTRI RECOVERY' => '103'] as $position => $pn) {
            DB::table('brihc_pemasar')->insert($this->hrPerson('reference_brihc_hc_'.$pn, $pn, $position));
        }
        $state = app(MantriPerformanceBasis::class)->rosterState('2026-10-16');
        $this->assertTrue($state['available']);
        $this->assertSame(['101'], $state['keys']);
        $this->assertArrayNotHasKey('999', $state['people']);
        $this->assertSame('HR 101', $state['people']['101']['name']);
    }

    public function test_workbook_roster_remains_fallback_without_an_authoritative_area6_mantri_source(): void
    {
        $this->bindMantriReference();
        DB::table('brihc_pemasar')->insert([
            $this->hrPerson('legacy_101', '101', 'MANTRI'),
            array_replace($this->hrPerson('reference_brihc_hc_104', '104', 'MANTRI'), ['psadesc' => 'KC SURABAYA']),
        ]);
        $state = app(MantriPerformanceBasis::class)->rosterState('2026-10-16');
        $this->assertSame(['999'], $state['keys']);
        $this->assertSame('workbook_reference', $state['people']['999']['roster_source']);
    }

    private function bindRmReference(bool $withUnassigned = false): void
    {
        $this->app->instance(MicroRmProductivityReference::class, new MicroRmProductivityReference($this->jsonPath($this->rmFixture($withUnassigned))));
    }

    private function bindMantriReference(): void
    {
        $data = [
            'version' => 1, 'month_start' => '2026-10-01', 'as_of' => '2026-10-16', 'baseline_period' => '2026-09-30',
            'sources' => ['fixture.xlsx'], 'accounts' => [], 'baseline_accounts' => [], 'current_accounts' => [],
            'people' => [['pn' => '999', 'name' => 'Old Workbook Person', 'branch' => 'KC MADIUN', 'unit' => 'UNIT A', 'bc' => '45', 'category' => 'pt']],
        ];
        $this->app->instance(MantriProductivityReference::class, new MantriProductivityReference($this->jsonPath($data)));
    }

    private function jsonPath(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'micro_productivity_');
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        $this->temporaryPaths[] = $path;

        return $path;
    }

    private function rmFixture(bool $withUnassigned = false): array
    {
        $accounts = [
            $this->account('000101', 'CIF-A', 300_000_000, '2026-10-05'),
            $this->account('101', 'CIF-A', 280_000_000, '2026-10-05'),
            $this->account('102', 'CIF-B', 100_000_000, '2026-10-08'),
            $this->account('103', 'CIF-C', 250_000_000, '2026-10-17'),
            $this->account('FUTURE', 'CIF-D', 900_000_000, '2026-10-18'),
            $this->account('HISTORIC', 'CIF-E', 800_000_000, '2026-09-30'),
            array_replace($this->account('201', 'CIF-N', 50_000_000, '2026-10-05'), ['pn' => '00202', 'name' => 'Ngawi RM', 'branch' => 'KC NGAWI', 'unit' => 'UNIT NGAWI', 'bc' => '57']),
        ];
        $rows = [];
        foreach (['MADIUN' => '00101', 'NGAWI' => '00202'] as $branch => $pn) {
            $rows[] = [
                'pn' => $pn, 'nama' => $branch === 'MADIUN' ? 'Madiun RM' : 'Ngawi RM', 'cabang' => 'KC '.$branch,
                'unit' => 'UNIT '.$branch, 'branch_code' => $branch === 'MADIUN' ? '45' : '57',
                'office_type' => 'kc', 'source_row' => 1, 'lancar_deb' => 1, 'lancar_os' => 100.0,
                'sml_deb' => 0, 'sml_os' => 0.0, 'npl_deb' => 0, 'npl_os' => 0.0,
                'total_deb' => $branch === 'MADIUN' ? 10 : 20, 'total_os' => 100.0,
                'realisasi_deb' => 999, 'realisasi_os' => 999.0, 'target_os' => 4_000_000_000.0,
            ];
        }

        $unassigned = $withUnassigned ? [array_replace($rows[0], [
            'pn' => '999', 'nama' => 'Unknown Roster Person', 'total_deb' => 1,
            'total_os' => 10_000_000.0, 'lancar_os' => 10_000_000.0,
            'realisasi_deb' => 0, 'realisasi_os' => 0.0,
        ])] : [];

        return [
            'version' => 1, 'as_of' => '2026-10-17', 'source' => ['file' => 'fixture.xlsx'],
            'rows' => $rows, 'accounts' => $accounts, 'unassigned_rows' => $unassigned,
        ];
    }

    private function account(string $account, string $cif, float $plafon, string $date): array
    {
        return [
            'account' => $account, 'cif' => $cif, 'pn' => '00101', 'name' => 'Madiun RM',
            'branch' => 'KC MADIUN', 'unit' => 'UNIT MADIUN', 'bc' => '45',
            'plafon' => $plafon, 'balance' => 100.0, 'realization_date' => $date,
        ];
    }

    private function baseline(string $account, string $cif, float $balance): array
    {
        return [
            'periode' => '2026-09-30', 'nomor_rekening1' => $account, 'cifno' => $cif,
            'cabang1' => 'KC MADIUN',
            'plafon' => $balance, 'baki_debet1' => $balance, 'segmen_kinerja' => 'MICRO',
            'produk_kinerja' => 'KURMIKRO', 'description' => 'Kredit Mikro - KUR Ritel 2015',
        ];
    }

    private function dbRow(string $period, string $account, float $plafon, array $overrides = []): array
    {
        return array_replace([
            'periode' => $period, 'tgl_realisasi' => substr($period, 0, 7).'-05',
            'nomor_rekening1' => $account, 'cifno' => 'CIF-A',
            'cabang1' => 'KC MADIUN', 'cabang_normalized' => 'KC MADIUN',
            'unit1' => 'KC MADIUN', 'unit_normalized' => 'KC MADIUN',
            'branch1' => '45', 'branch_normalized' => '45',
            'pn_pengelola1' => '00360186 - LIVE RM', 'rm_normalized' => '00360186 - LIVE RM',
            'pn_pemrakarsa1' => '00011111 - OTHER INITIATOR',
            'plafon' => $plafon, 'baki_debet1' => $plafon,
            'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURKECIL',
            'description' => 'Kredit Mikro - KUR Ritel 2015', 'kolek' => 1,
        ], $overrides);
    }

    private function hrPerson(string $id, string $pn, string $position): array
    {
        return [
            'uniqueid_namareport' => $id, 'pernr' => $pn, 'completename' => 'HR '.$pn,
            'positiondesc' => $position, 'psadesc' => 'KC MADIUN', 'orgdesc' => 'UNIT A', 'esgdesc' => 'PT',
        ];
    }

    private function invoke(object $object, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($object, $method))->invoke($object, ...$arguments);
    }
}
