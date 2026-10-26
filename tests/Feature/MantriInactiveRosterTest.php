<?php

namespace Tests\Feature;

use App\Console\Commands\SyncMantriSpreadsheetCommand;
use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use App\Services\Reports\MantriIncentiveService;
use App\Support\LandingMicroPerformanceService;
use App\Support\MantriPerformanceBasis;
use App\Support\MantriProductivityReference;
use App\Support\ReportCacheVersion;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class MantriInactiveRosterTest extends TestCase
{
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$values) => implode('', $values), -1);
        Cache::flush();
        Http::preventStrayRequests();

        Schema::create('brihc', function (Blueprint $table): void {
            $table->string('uniqueid_brihc')->primary();
            foreach (['pn', 'nama', 'jabatan'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            foreach (['completename', 'nip', 'pernr', 'sex', 'age', 'esgdesc', 'padesc', 'psadesc', 'orgdesc', 'positiondesc', 'mkj', 'descprogrammasuk', 'jobgrade', 'bc', 'pn_mantri', 'status', 'jg', 'pg', 'tmt_masuk', 'tmt_jabatan', 'keterangan'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->string('uniqueid_namareport')->nullable();
            $table->date('periode');
            foreach (['segmen_kinerja', 'produk_kinerja', 'description', 'branch_normalized', 'branch1', 'unit_normalized', 'unit1', 'cabang_normalized', 'cabang1', 'rm_normalized', 'pn_pengelola1', 'pn_pemrakarsa1', 'nomor_rekening1', 'cifno', 'pn_pemutus_normalized', 'nama_debitur1'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->integer('kolek')->nullable();
            $table->integer('kol_adk1')->nullable();
            $table->date('tgl_realisasi')->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    #[DataProvider('productivityNotes')]
    public function test_inactive_notes_match_whole_words_without_excluding_other_notes(mixed $note, bool $expected): void
    {
        $this->assertSame($expected, app(MantriPerformanceBasis::class)->isInactiveForProductivity($note));
    }

    public static function productivityNotes(): array
    {
        return [
            'null' => [null, false],
            'empty' => ['', false],
            'whitespace' => ['  ', false],
            'other note' => ['Penugasan sementara', false],
            'mixed case illness' => [' sAkIt ', true],
            'illness explanation' => ['SAKIT (surat dokter)', true],
            'maternity leave' => ['Cuti Melahirkan', true],
            'annual leave' => ['cuti tahunan', true],
            'uppercase leave' => ['CUTI', true],
            'punctuation' => ['SAKIT/CUTI', true],
            'illness substring' => ['kesakitan', false],
            'leave substring' => ['cutinya', false],
        ];
    }

    public function test_keterangan_migration_is_idempotent_and_preserves_existing_personnel(): void
    {
        $this->insertPerson('1', 'Mantri Existing', null);
        Schema::table('brihc_pemasar', function (Blueprint $table): void {
            $table->dropColumn('keterangan');
        });
        $before = DB::table('brihc_pemasar')->where('pernr', '1')->first();
        $migration = require database_path('migrations/2026_10_26_000001_add_mantri_keterangan_to_brihc_pemasar.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('brihc_pemasar', 'keterangan'));
        $after = (array) DB::table('brihc_pemasar')->where('pernr', '1')->first();
        $this->assertNull($after['keterangan']);
        unset($after['keterangan']);
        $this->assertSame((array) $before, $after);
    }

    public function test_inactive_people_are_excluded_from_roster_landing_and_productivity_without_changing_source_data(): void
    {
        foreach ([
            ['1', 'Mantri Sakit', 'sAkIt', 'KC MADIUN', true],
            ['2', 'Mantri Cuti', 'Cuti Melahirkan', 'KC MADIUN', true],
            ['3', 'Mantri Catatan Lain', 'Penugasan sementara', 'KC MADIUN', true],
            ['4', 'Mantri Aktif Ngawi', null, 'KC NGAWI', true],
            ['5', 'Mantri Nol Aktif', '', 'KC MADIUN', false],
            ['6', 'Mantri Legacy Sakit', 'SAKIT', 'KC MADIUN', true],
        ] as [$pn, $name, $note, $branch, $hasAccount]) {
            $this->insertPerson($pn, $name, $note, $branch, $pn !== '6');
            if ($hasAccount) {
                $this->insertLoan($pn, $name, $branch);
            }
        }
        $before = $this->sourceRows();
        $basis = app(MantriPerformanceBasis::class)->rosterState('2026-05-31');
        $this->assertTrue($basis['available']);
        $this->assertEqualsCanonicalizing(['3', '4', '5'], $basis['keys']);

        $landing = app(LandingMicroPerformanceService::class);
        $landingRoster = $this->invoke($landing, 'mantriRoster', null, '2026-05-31');
        $this->assertEqualsCanonicalizing(['3', '4', '5'], $landingRoster->pluck('pn')->all());
        $madiun = $this->invoke($landing, 'mantriRoster', UserBranchScope::forKey('madiun'), '2026-05-31');
        $this->assertEqualsCanonicalizing(['3', '5'], $madiun->pluck('pn')->all());

        $controller = new KinerjaRmMikroReportController;
        $source = $this->invoke($controller, 'mantriSourceQuery', '2026-05-31')->get();
        $this->assertEqualsCanonicalizing(['3', '4'], $source->pluck('owner_pn')->all());
        $productivity = $this->invoke($controller, 'mantriProductivityPayload', '2026-05-31');
        $this->assertSame(3, $productivity['total']['jumlah_mantri']);
        $this->assertSame(2, $productivity['total']['realisasi_deb']);
        $this->assertSame(100000000.0, (float) $productivity['total']['realisasi_os']);
        $this->assertEqualsCanonicalizing(['Mantri Catatan Lain', 'Mantri Aktif Ngawi', 'Mantri Nol Aktif'], collect($productivity['rows'])->pluck('nama_mantri')->all());

        $extreme = $this->invoke($controller, 'mantriExtremeLowPayload', '2026-05-31', 'per_cabang');
        $this->assertSame(3, $extreme['total']['total_mantri']);
        $this->assertSame(1, $extreme['total']['buckets']['none']['deb']);
        $this->assertSame(2, $extreme['total']['extreme_low']['deb']);
        $this->assertEqualsCanonicalizing(['Mantri Catatan Lain', 'Mantri Aktif Ngawi', 'Mantri Nol Aktif'], collect($extreme['details'])->pluck('nama')->all());
        $this->assertSame($before, $this->sourceRows());
        $this->assertSame(6, DB::table('brihc_pemasar')->count());
    }

    public function test_all_inactive_authoritative_people_do_not_restore_old_workbook_or_daily_fallback_roster(): void
    {
        $this->insertPerson('1', 'Mantri Sakit', 'SAKIT');
        $this->insertPerson('2', 'Mantri Cuti', 'CUTI');
        $this->insertLoan('1', 'Mantri Sakit');
        $this->insertLoan('999', 'Mantri Workbook Lama');
        $path = $this->temporaryPath();
        file_put_contents($path, json_encode([
            'version' => 1, 'month_start' => '2026-05-01', 'as_of' => '2026-05-31', 'baseline_period' => '2026-04-30',
            'sources' => ['old-workbook.xlsx'], 'accounts' => [], 'baseline_accounts' => [], 'current_accounts' => [],
            'people' => [['pn' => '999', 'name' => 'Mantri Workbook Lama', 'branch' => 'KC MADIUN', 'unit' => 'UNIT LAMA', 'bc' => '45', 'category' => 'pt']],
        ], JSON_THROW_ON_ERROR));
        $this->app->instance(MantriProductivityReference::class, new MantriProductivityReference($path));
        Cache::forget(SyncMantriSpreadsheetCommand::CACHE_KEY);
        $before = $this->sourceRows();

        $state = app(MantriPerformanceBasis::class)->rosterState('2026-05-31');
        $this->assertTrue($state['available']);
        $this->assertSame([], $state['keys']);
        $this->assertSame([], $state['people']);
        $this->assertCount(0, $this->invoke(app(LandingMicroPerformanceService::class), 'mantriRoster', null, '2026-05-31'));
        $controller = new KinerjaRmMikroReportController;
        $this->assertCount(0, $this->invoke($controller, 'mantriSourceQuery', '2026-05-31')->get());
        $this->assertSame(0, $this->invoke($controller, 'mantriProductivityPayload', '2026-05-31')['total']['jumlah_mantri']);
        $this->assertSame(0, $this->invoke($controller, 'mantriExtremeLowPayload', '2026-05-31')['total']['total_mantri']);
        $this->assertSame($before, $this->sourceRows());
    }

    public function test_spreadsheet_note_only_changes_refresh_marker_and_cache_and_clearing_the_note_reactivates_the_person(): void
    {
        $this->insertLoan('200', 'Mantri Tumbuh Sumber');
        $source = $this->sourceRows();
        $this->fakeWorkbook('');
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $firstMarker = Cache::get(SyncMantriSpreadsheetCommand::CACHE_KEY);
        $firstVersions = $this->versions();
        $this->assertSame(['200'], app(MantriPerformanceBasis::class)->rosterState('2026-05-31')['keys']);
        $this->assertDatabaseHas('brihc_pemasar', ['pernr' => '200', 'keterangan' => null]);
        $controller = new KinerjaRmMikroReportController;
        $this->assertSame(1, $this->invoke($controller, 'mantriExtremeLowPayload', '2026-05-31')['total']['total_mantri']);
        $incentive = app(MantriIncentiveService::class);
        $this->assertSame(['200'], array_column($incentive->build('2026-05-31')['rows'], 'pn'));

        $this->fakeWorkbook('Cuti Melahirkan');
        $this->artisan('reference:sync-mantri-spreadsheet', ['--dry-run' => true])->assertExitCode(0);
        $this->assertDatabaseHas('brihc_pemasar', ['pernr' => '200', 'keterangan' => null]);
        $this->assertSame($firstMarker, Cache::get(SyncMantriSpreadsheetCommand::CACHE_KEY));
        $this->assertSame($firstVersions, $this->versions());
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $inactiveMarker = Cache::get(SyncMantriSpreadsheetCommand::CACHE_KEY);
        $inactiveVersions = $this->versions();
        $this->assertDatabaseHas('brihc_pemasar', ['pernr' => '200', 'positiondesc' => 'MANTRI', 'keterangan' => 'Cuti Melahirkan']);
        $this->assertDatabaseHas('brihc', ['pn' => '200', 'jabatan' => 'MANTRI']);
        $this->assertNotSame($firstMarker['source_hash'], $inactiveMarker['source_hash']);
        $this->assertNotSame($firstMarker['database_hash'], $inactiveMarker['database_hash']);
        $this->assertSame(array_map(fn (int $value): int => $value + 1, $firstVersions), $inactiveVersions);
        $this->assertSame([], app(MantriPerformanceBasis::class)->rosterState('2026-05-31')['keys']);
        $this->assertSame(0, $this->invoke($controller, 'mantriExtremeLowPayload', '2026-05-31')['total']['total_mantri']);
        $inactiveIncentive = $incentive->build('2026-05-31');
        $this->assertSame([], $inactiveIncentive['rows']);
        $this->assertSame(0.0, $inactiveIncentive['outside_roster']['nd']);
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $this->assertSame($inactiveMarker, Cache::get(SyncMantriSpreadsheetCommand::CACHE_KEY));
        $this->assertSame($inactiveVersions, $this->versions());

        $this->fakeWorkbook('');
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $this->assertDatabaseHas('brihc_pemasar', ['pernr' => '200', 'keterangan' => null]);
        $this->assertSame(['200'], app(MantriPerformanceBasis::class)->rosterState('2026-05-31')['keys']);
        $this->assertSame(array_map(fn (int $value): int => $value + 1, $inactiveVersions), $this->versions());
        $this->assertSame($firstMarker['source_hash'], Cache::get(SyncMantriSpreadsheetCommand::CACHE_KEY)['source_hash']);
        $this->assertSame(1, $this->invoke($controller, 'mantriExtremeLowPayload', '2026-05-31')['total']['total_mantri']);
        $reactivated = $incentive->build('2026-05-31');
        $this->assertSame(['200'], array_column($reactivated['rows'], 'pn'));
        $this->assertSame(50000000.0, $reactivated['rows'][0]['nd_total']);
        $this->assertSame($source, $this->sourceRows());
    }

    public function test_legacy_inactive_note_also_blocks_that_person_from_the_workbook_fallback(): void
    {
        $this->insertPerson('1', 'Mantri Legacy Sakit', 'SAKIT', authoritative: false);
        $path = $this->temporaryPath();
        file_put_contents($path, json_encode([
            'version' => 1, 'month_start' => '2026-05-01', 'as_of' => '2026-05-31', 'baseline_period' => '2026-04-30',
            'sources' => ['old-workbook.xlsx'], 'accounts' => [], 'baseline_accounts' => [], 'current_accounts' => [],
            'people' => [
                ['pn' => '1', 'name' => 'Mantri Legacy Sakit', 'branch' => 'KC MADIUN', 'unit' => 'UNIT LAMA', 'bc' => '45', 'category' => 'pt'],
                ['pn' => '3', 'name' => 'Mantri Workbook Aktif', 'branch' => 'KC MADIUN', 'unit' => 'UNIT LAMA', 'bc' => '45', 'category' => 'pt'],
            ],
        ], JSON_THROW_ON_ERROR));
        $this->app->instance(MantriProductivityReference::class, new MantriProductivityReference($path));

        $state = app(MantriPerformanceBasis::class)->rosterState('2026-05-31');
        $this->assertSame(['3'], $state['keys']);
        $this->assertSame('workbook_reference', $state['people']['3']['roster_source']);
        $this->assertSame(['3'], $this->invoke(app(LandingMicroPerformanceService::class), 'mantriRoster', null, '2026-05-31')->pluck('pn')->all());
        $extreme = $this->invoke(new KinerjaRmMikroReportController, 'mantriExtremeLowPayload', '2026-05-31');
        $this->assertSame(1, $extreme['total']['total_mantri']);
        $this->assertSame(['Mantri Workbook Aktif'], collect($extreme['details'])->pluck('nama')->all());
    }

    public function test_legacy_matching_marker_cannot_skip_the_first_sync_that_persists_keterangan(): void
    {
        $this->fakeWorkbook('');
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $this->fakeWorkbook('SAKIT');
        $command = app(SyncMantriSpreadsheetCommand::class);
        $sourcePath = $this->temporaryFiles[array_key_last($this->temporaryFiles)];
        $legacyKey = 'reference:mantri-spreadsheet:'.SyncMantriSpreadsheetCommand::SPREADSHEET_ID.':v1';
        $legacyMarker = [
            'source_hash' => $this->invoke($command, 'sourceHash', $sourcePath),
            'database_hash' => $this->invoke($command, 'databaseHash'),
        ];
        Cache::forget(SyncMantriSpreadsheetCommand::CACHE_KEY);
        Cache::forever($legacyKey, $legacyMarker);
        $versions = $this->versions();
        $this->assertNotSame($legacyKey, SyncMantriSpreadsheetCommand::CACHE_KEY);
        $this->assertDatabaseHas('brihc_pemasar', ['pernr' => '200', 'keterangan' => null]);

        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);

        $this->assertDatabaseHas('brihc_pemasar', ['pernr' => '200', 'keterangan' => 'SAKIT']);
        $this->assertSame([], app(MantriPerformanceBasis::class)->rosterState('2026-05-31')['keys']);
        $this->assertSame(array_map(fn (int $version): int => $version + 1, $versions), $this->versions());
        $marker = Cache::get(SyncMantriSpreadsheetCommand::CACHE_KEY);
        $this->assertSame($legacyMarker['source_hash'], $marker['source_hash']);
        $this->assertNotSame($legacyMarker['database_hash'], $marker['database_hash']);
    }

    public function test_legacy_worker_lock_prevents_new_version_from_downloading_or_syncing_concurrently(): void
    {
        $legacyLockKey = 'reference:mantri-spreadsheet:'.SyncMantriSpreadsheetCommand::SPREADSHEET_ID.':v1:lock';
        $this->assertSame($legacyLockKey, SyncMantriSpreadsheetCommand::LOCK_KEY);
        $this->fakeWorkbook('SAKIT');
        $lock = Cache::lock($legacyLockKey, 300);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('reference:sync-mantri-spreadsheet')
                ->expectsOutputToContain('Sinkronisasi spreadsheet Mantri sedang berjalan; dilewati.')
                ->assertExitCode(0);

            Http::assertNothingSent();
            $this->assertSame(0, DB::table('brihc_pemasar')->count());
            $this->assertFalse(Cache::has(SyncMantriSpreadsheetCommand::CACHE_KEY));
        } finally {
            $lock->release();
        }
    }

    public function test_incentive_assembly_excludes_known_inactive_bookings_without_hiding_unknown_pn_diagnostics(): void
    {
        $this->insertPerson('1', 'Mantri Sakit', 'sAkIt');
        $this->insertPerson('2', 'Mantri Cuti', 'Cuti Melahirkan');
        $this->insertPerson('3', 'Mantri Aktif', 'Penugasan sementara');
        $this->insertPerson('4', 'Mantri Nol Aktif', null);
        foreach (['1', '2', '3', '999'] as $pn) {
            $this->insertLoan($pn, 'Mantri '.$pn);
        }
        $before = $this->sourceRows();
        $service = app(MantriIncentiveService::class);
        $this->assertEqualsCanonicalizing(['3', '4'], array_map('strval', array_keys($service->roster('2026-12-31'))));
        $window = $service->window('2026-12-31', ['2026-12-31']);
        $monthly = ['2026-12' => [
            ['pn' => '00000001', 'net' => 1000000000, 'cabang' => 'KC MADIUN'],
            ['pn' => '2', 'net' => 2000000000, 'cabang' => 'KC MADIUN'],
            ['pn' => '3', 'net' => 900000000, 'cabang' => 'KC MADIUN'],
            ['pn' => '999', 'net' => 77000000, 'cabang' => 'KC MADIUN'],
            ['pn' => '', 'net' => 11000000, 'cabang' => 'KC MADIUN'],
        ]];
        $mob = ['2026-01' => [
            ['pn' => '1', 'plafon' => 1000000000, 'pemburukan' => 1000000000],
            ['pn' => '2', 'plafon' => 2000000000, 'pemburukan' => 2000000000],
            ['pn' => '3', 'plafon' => 100000000, 'pemburukan' => 0],
        ]];

        $payload = $this->invoke($service, 'assemble', '2026-12-31', $window, $monthly, $mob);
        $this->assertEqualsCanonicalizing(['3', '4'], array_column($payload['rows'], 'pn'));
        $active = collect($payload['rows'])->firstWhere('pn', '3');
        $this->assertSame(900000000.0, $active['nd_total']);
        $this->assertSame(300000000.0, $active['nd_avg']);
        $this->assertSame(100000000.0, $active['mob_plafon']);
        $this->assertSame(0.0, $active['mob_pemburukan']);
        $this->assertSame([
            'nd' => 77000000.0, 'deb' => 1, 'pn_count' => 1,
            'no_pn_nd' => 11000000.0, 'no_pn_deb' => 1,
        ], $payload['outside_roster']);
        $this->assertSame($before, $this->sourceRows());
    }

    public function test_incentive_nd_and_mob_nominatives_exclude_sick_and_leave_accounts_while_preserving_unknown_accounts(): void
    {
        $this->insertPerson('1', 'Mantri Sakit', 'SAKIT');
        $this->insertPerson('2', 'Mantri Cuti', 'CUTI');
        $this->insertPerson('3', 'Mantri Aktif', null);
        foreach (['1', '2', '3', '999'] as $pn) {
            foreach ([['2026-01-31', 1], ['2026-07-31', 2]] as [$position, $kolek]) {
                $this->insertLoan($pn, 'Mantri '.$pn, overrides: [
                    'periode' => $position, 'tgl_realisasi' => '2026-01-15',
                    'nomor_rekening1' => 'VINTAGE-'.$pn, 'cifno' => 'VINTAGE-CIF-'.$pn, 'kolek' => $kolek,
                ]);
            }
            $this->insertLoan($pn, 'Mantri '.$pn, overrides: [
                'periode' => '2026-12-31', 'tgl_realisasi' => '2026-12-05',
                'nomor_rekening1' => 'NEW-'.$pn, 'cifno' => 'NEW-CIF-'.$pn,
            ]);
        }
        $before = $this->sourceRows();
        $service = app(MantriIncentiveService::class);

        $nd = $service->nominatif('2026-12-31', 'nd');
        $this->assertEqualsCanonicalizing(['3', '999'], array_column($nd, 'pn'));
        $this->assertEqualsCanonicalizing(['NEW-3', 'NEW-999'], array_column($nd, 'account'));
        $this->assertSame(100000000.0, array_sum(array_column($nd, 'net')));

        $mob = $service->nominatif('2026-12-31', 'mob6');
        $this->assertEqualsCanonicalizing(['3', '999'], array_column($mob, 'pn'));
        $this->assertEqualsCanonicalizing(['VINTAGE-3', 'VINTAGE-999'], array_column($mob, 'account'));
        $this->assertSame(100000000.0, array_sum(array_column($mob, 'plafon')));
        $this->assertSame(100000000.0, array_sum(array_column($mob, 'pemburukan')));
        $this->assertSame($before, $this->sourceRows());
    }

    private function insertPerson(string $pn, string $name, ?string $note, string $branch = 'KC MADIUN', bool $authoritative = true): void
    {
        DB::table('brihc_pemasar')->insert([
            'uniqueid_namareport' => ($authoritative ? 'reference_brihc_hc_' : 'legacy_').$pn,
            'pernr' => $pn, 'pn_mantri' => $pn, 'completename' => $name, 'positiondesc' => 'MANTRI',
            'psadesc' => $branch, 'orgdesc' => 'UNIT '.$branch, 'esgdesc' => 'PT', 'bc' => '45',
            'keterangan' => $note,
        ]);
    }

    private function insertLoan(string $pn, string $name, string $branch = 'KC MADIUN', array $overrides = []): void
    {
        $owner = str_pad($pn, 8, '0', STR_PAD_LEFT).' - '.$name;
        DB::table('daily_loan_dinamis')->insert(array_replace([
            'uniqueid_namareport' => 'INACTIVE-TEST-'.DB::table('daily_loan_dinamis')->count(),
            'periode' => '2026-05-31', 'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KUPEDES', 'description' => 'Kupedes',
            'branch_normalized' => '45', 'branch1' => '45', 'unit_normalized' => 'UNIT '.$branch, 'unit1' => 'UNIT '.$branch,
            'cabang_normalized' => $branch, 'cabang1' => $branch, 'rm_normalized' => $owner, 'pn_pengelola1' => $owner,
            'pn_pemrakarsa1' => $owner, 'nomor_rekening1' => 'ACCOUNT-'.$pn, 'cifno' => 'CIF-'.$pn,
            'plafon' => 50000000, 'baki_debet1' => 40000000, 'kolek' => 1, 'kol_adk1' => 1, 'tgl_realisasi' => '2026-05-15',
        ], $overrides));
    }

    private function fakeWorkbook(?string $note): void
    {
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->setTitle('Rekap');
        $headers = ['PSADESC', 'NIP', 'PERNR', 'COMPLETENAME', 'ESGDESC', 'JG', 'PG', 'JOBDESC', 'BC', 'UKER', 'KETERANGAN'];
        $rows = [
            'Mantri Tumbuh' => ['KC Madiun', 'NIP-200', '000200', 'Mantri Tumbuh Sumber', 'PT', 'JG05', 'PG06', 'MANTRI', '003200', 'UNIT MADIUN', $note],
            'Mantri Briguna' => ['KC Magetan', 'NIP-300', '000300', 'Mantri Briguna Sumber', 'PT', 'JG06', 'PG07', 'MANTRI BRIGUNA', '000049', 'KC MAGETAN', null],
            'Mantri Recovery' => ['KC Ponorogo', 'NIP-400', '000400', 'Mantri Recovery Sumber', 'PT', 'JG05', 'PG06', 'MANTRI RECOVERY', '003202', 'UNIT PONOROGO', null],
        ];
        foreach ($rows as $title => $row) {
            $workbook->createSheet()->setTitle($title)->fromArray([$headers, $row]);
        }
        $path = $this->temporaryPath();
        (new Xlsx($workbook))->save($path);
        $workbook->disconnectWorksheets();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([SyncMantriSpreadsheetCommand::SOURCE_URL => Http::response(file_get_contents($path), 200)]);
    }

    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mantri_inactive_');
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function sourceRows(): array
    {
        return DB::table('daily_loan_dinamis')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
    }

    private function versions(): array
    {
        return array_map(fn (string $scope): int => ReportCacheVersion::get($scope), ['pinjaman', 'consumer', 'brihc']);
    }

    private function invoke(object $target, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($target, $method))->invoke($target, ...$arguments);
    }
}
