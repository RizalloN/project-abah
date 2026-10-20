<?php

namespace Tests\Feature;

use App\Console\Commands\SyncMantriSpreadsheetCommand as SyncCommand;
use App\Services\Reports\KpiPersonnelReferenceSyncService;
use App\Support\MantriPerformanceBasis;
use App\Support\MantriProductivityReference;
use App\Support\ReportCacheVersion;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncMantriSpreadsheetCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('brihc', function (Blueprint $table): void {
            $table->string('uniqueid_brihc')->primary();
            foreach (['pn', 'nama', 'jabatan'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            foreach (['completename', 'nip', 'pernr', 'sex', 'age', 'esgdesc', 'padesc', 'psadesc', 'orgdesc', 'positiondesc', 'mkj', 'descprogrammasuk', 'jobgrade', 'bc', 'pn_mantri', 'status', 'jg', 'pg', 'tmt_masuk', 'tmt_jabatan'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
        Http::preventStrayRequests();
    }

    public function test_it_syncs_all_three_roles_and_preserves_unrelated_references_and_tenure(): void
    {
        DB::table('brihc')->insert(['uniqueid_brihc' => 'keep-small', 'pn' => '900', 'nama' => 'RM Small Tetap', 'jabatan' => 'RM BISNIS KECIL']);
        DB::table('brihc_pemasar')->insert([
            ['uniqueid_namareport' => 'keep-small', 'pernr' => '900', 'positiondesc' => 'RM BISNIS KECIL', 'psadesc' => 'KC Madiun', 'tmt_masuk' => null],
            ['uniqueid_namareport' => 'keep-outside', 'pernr' => '901', 'positiondesc' => 'MANTRI', 'psadesc' => 'KC Situbondo', 'tmt_masuk' => null],
            ['uniqueid_namareport' => 'old-mantri', 'pernr' => '200', 'positiondesc' => 'MANTRI', 'psadesc' => 'KC Madiun', 'tmt_masuk' => '2010-01-01'],
        ]);
        $this->fakeWorkbook();
        $versions = $this->versions();

        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);

        foreach (['200' => 'MANTRI', '300' => 'MANTRI BRIGUNA', '400' => 'MANTRI RECOVERY'] as $pn => $role) {
            $this->assertDatabaseHas('brihc', ['pn' => (string) $pn, 'jabatan' => $role]);
            $this->assertDatabaseHas('brihc_pemasar', ['pernr' => (string) $pn, 'positiondesc' => $role]);
        }
        $this->assertDatabaseHas('brihc_pemasar', ['pernr' => '200', 'tmt_masuk' => '2010-01-01']);
        $this->assertDatabaseHas('brihc', ['uniqueid_brihc' => 'keep-small']);
        $this->assertDatabaseHas('brihc_pemasar', ['uniqueid_namareport' => 'keep-small']);
        $this->assertDatabaseMissing('brihc_pemasar', ['uniqueid_namareport' => 'keep-outside']);
        $this->assertSame(array_map(fn (int $version): int => $version + 1, $versions), $this->versions());
        $this->assertIsArray(Cache::get(SyncCommand::CACHE_KEY));
        Http::assertSent(fn ($request): bool => $request->url() === SyncCommand::SOURCE_URL);
    }

    public function test_dry_run_validates_without_mutating_rows_or_cache(): void
    {
        $this->fakeWorkbook();
        $before = $this->state();
        $this->artisan('reference:sync-mantri-spreadsheet', ['--dry-run' => true])
            ->expectsOutputToContain('"dry_run": true')->assertExitCode(0);
        $this->assertSame($before, $this->state());
    }

    public function test_download_failure_preserves_last_valid_references_and_marker(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $before = $this->state();
        $this->fakeBody('Unavailable', 503);

        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(1);

        $this->assertSame($before, $this->state());
    }

    public function test_malformed_download_and_missing_required_sheet_preserve_last_valid_state(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $before = $this->state();
        foreach (['<html>Login required</html>', $this->workbookBody(missingSheet: true)] as $body) {
            $this->fakeBody($body);
            $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(1);
            $this->assertSame($before, $this->state());
        }
    }

    public function test_missing_header_and_conflicting_duplicate_pn_preserve_last_valid_state(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $before = $this->state();
        foreach ([$this->workbookBody(missingHeader: true), $this->workbookBody(conflict: true)] as $body) {
            $this->fakeBody($body);
            $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(1);
            $this->assertSame($before, $this->state());
        }
    }

    public function test_unchanged_cells_skip_writes_and_cache_bumps_even_when_xlsx_metadata_changes(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $before = $this->state();
        $this->fakeWorkbook(metadata: 'different exporter metadata');

        $this->artisan('reference:sync-mantri-spreadsheet')
            ->expectsOutputToContain('tidak berubah')->assertExitCode(0);

        $this->assertSame($before, $this->state());
    }

    public function test_changed_source_updates_reference_and_bumps_versions(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $versions = $this->versions();
        $this->fakeWorkbook(name: 'Nama Sumber Diperbarui');

        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);

        $this->assertDatabaseHas('brihc', ['pn' => '200', 'nama' => 'Nama Sumber Diperbarui']);
        $this->assertSame(array_map(fn (int $version): int => $version + 1, $versions), $this->versions());
    }

    public function test_timestamp_and_unrelated_roles_inside_or_outside_area_do_not_trigger_a_sync(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        DB::table('brihc')->update(['updated_at' => '2020-01-01 00:00:00']);
        DB::table('brihc_pemasar')->update(['updated_at' => '2020-01-01 00:00:00']);
        DB::table('brihc_pemasar')->insert([
            ['uniqueid_namareport' => 'outside-role', 'pernr' => '900', 'positiondesc' => 'RM MICRO', 'psadesc' => 'KC Madiun'],
            ['uniqueid_namareport' => 'outside-branch', 'pernr' => '901', 'positiondesc' => 'RM MIKRO', 'psadesc' => 'KC Situbondo'],
        ]);
        $before = $this->state();

        $this->artisan('reference:sync-mantri-spreadsheet')
            ->expectsOutputToContain('tidak berubah')->assertExitCode(0);

        $this->assertSame($before, $this->state());
    }

    public function test_manual_database_drift_is_restored_from_the_unchanged_source(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $versions = $this->versions();
        DB::table('brihc_pemasar')->where('pernr', '200')->update(['completename' => 'Perubahan Manual']);
        DB::table('brihc')->where('pn', '300')->update(['nama' => 'Perubahan Manual']);

        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);

        $this->assertDatabaseHas('brihc_pemasar', ['pernr' => '200', 'completename' => 'Mantri Tumbuh Sumber']);
        $this->assertDatabaseHas('brihc', ['pn' => '300', 'nama' => 'Mantri Briguna Sumber']);
        $this->assertSame(array_map(fn (int $version): int => $version + 1, $versions), $this->versions());
    }

    public function test_an_existing_lock_prevents_overlapping_downloads(): void
    {
        $lock = Cache::lock(SyncCommand::LOCK_KEY, 300);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('reference:sync-mantri-spreadsheet')->expectsOutputToContain('sedang berjalan')->assertExitCode(0);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_subsequent_sync_follows_assignment_changes_and_removes_people_no_longer_in_the_source(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $rows = $this->sourceRows();
        $rows['Mantri Tumbuh'][0] = ['KC Ngawi', 'NIP-200', '000200', 'Nama Terbaru', 'KONTRAK', 'JG07', 'PG08', 'MANTRI', '006348', 'UNIT NGAWI BARU'];
        $this->fakeBody($this->workbookBody(rows: $rows));

        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);

        $this->assertDatabaseHas('brihc', ['pn' => '200', 'nama' => 'Nama Terbaru', 'jabatan' => 'MANTRI']);
        $this->assertDatabaseHas('brihc_pemasar', [
            'pernr' => '200', 'completename' => 'Nama Terbaru', 'psadesc' => 'KC Ngawi',
            'orgdesc' => 'UNIT NGAWI BARU', 'bc' => '6348', 'esgdesc' => 'KONTRAK', 'jg' => 'JG07', 'pg' => 'PG08',
        ]);

        $tumbuh = $rows['Mantri Tumbuh'][0];
        $tumbuh[7] = 'MANTRI BRIGUNA';
        $briguna = $rows['Mantri Briguna'][0];
        $briguna[7] = 'MANTRI RECOVERY';
        $rows['Mantri Tumbuh'] = [];
        $rows['Mantri Briguna'] = [$tumbuh];
        $rows['Mantri Recovery'] = [$briguna];
        $this->fakeBody($this->workbookBody(rows: $rows));

        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);

        foreach (['200' => 'MANTRI BRIGUNA', '300' => 'MANTRI RECOVERY'] as $pn => $role) {
            $this->assertDatabaseHas('brihc', ['pn' => (string) $pn, 'jabatan' => $role]);
            $this->assertDatabaseHas('brihc_pemasar', ['pernr' => (string) $pn, 'positiondesc' => $role]);
            $this->assertSame(1, DB::table('brihc')->where('pn', $pn)->count());
            $this->assertSame(1, DB::table('brihc_pemasar')->where('pernr', $pn)->count());
        }
        $this->assertDatabaseMissing('brihc', ['pn' => '400']);
        $this->assertDatabaseMissing('brihc_pemasar', ['pernr' => '400']);
        $this->assertDatabaseMissing('brihc', ['pn' => '200', 'jabatan' => 'MANTRI']);
        $this->assertDatabaseMissing('brihc_pemasar', ['pernr' => '300', 'positiondesc' => 'MANTRI BRIGUNA']);
    }

    public function test_a_branch_missing_from_the_new_source_clears_only_its_three_mantri_roles(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        foreach (['MANTRI' => '710', 'MANTRI BRIGUNA' => '720', 'MANTRI RECOVERY' => '730', 'RM MIKRO' => '740'] as $role => $pn) {
            DB::table('brihc')->insert(['uniqueid_brihc' => 'stale-'.$pn, 'pn' => $pn, 'nama' => $role, 'jabatan' => $role]);
            DB::table('brihc_pemasar')->insert(['uniqueid_namareport' => 'stale-'.$pn, 'pernr' => $pn, 'positiondesc' => $role, 'psadesc' => 'KC Ngawi']);
        }
        DB::table('brihc')->insert(['uniqueid_brihc' => 'outside-750', 'pn' => '750', 'nama' => 'Di Luar Area', 'jabatan' => 'MANTRI']);
        DB::table('brihc_pemasar')->insert(['uniqueid_namareport' => 'outside-750', 'pernr' => '750', 'positiondesc' => 'MANTRI', 'psadesc' => 'KC Situbondo']);
        $rows = $this->sourceRows();
        $rows['Mantri Recovery'] = [];
        $this->fakeBody($this->workbookBody(rows: $rows));

        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);

        foreach (['400', '710', '720', '730', '750'] as $pn) {
            $this->assertDatabaseMissing('brihc', ['pn' => $pn]);
            $this->assertDatabaseMissing('brihc_pemasar', ['pernr' => $pn]);
        }
        foreach (['740'] as $pn) {
            $this->assertDatabaseHas('brihc', ['pn' => $pn]);
            $this->assertDatabaseHas('brihc_pemasar', ['pernr' => $pn]);
        }
    }

    public function test_an_empty_tumbuh_tab_does_not_resurrect_people_from_the_productivity_workbook(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mantri_reference_');
        file_put_contents($path, json_encode([
            'version' => 1, 'month_start' => '2026-10-01', 'as_of' => '2026-10-16', 'baseline_period' => '2026-09-30',
            'people' => [['pn' => '200', 'name' => 'Mantri Workbook Lama']],
            'accounts' => [], 'baseline_accounts' => [], 'current_accounts' => [], 'sources' => [],
        ], JSON_THROW_ON_ERROR));
        $this->app->instance(MantriProductivityReference::class, new MantriProductivityReference($path));
        try {
            $this->assertSame(['200'], app(MantriPerformanceBasis::class)->rosterState('2026-10-16')['keys']);
            $this->fakeWorkbook();
            $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
            $rows = $this->sourceRows();
            $rows['Mantri Tumbuh'] = [];
            $this->fakeBody($this->workbookBody(rows: $rows));

            $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);

            $this->assertIsArray(Cache::get(SyncCommand::CACHE_KEY));
            $this->assertSame([], app(MantriPerformanceBasis::class)->rosterState('2026-10-16')['keys']);
            $this->assertDatabaseMissing('brihc', ['pn' => '200']);
            $this->assertDatabaseMissing('brihc_pemasar', ['pernr' => '200']);
        } finally {
            unlink($path);
        }
    }

    public function test_the_successful_spreadsheet_marker_blocks_legacy_mantri_reintroduction_even_without_hc_rows(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        // Isolate the authority marker from the separate HC-row authority checks.
        DB::table('brihc')->delete();
        DB::table('brihc_pemasar')->delete();
        $before = $this->state();

        $result = app(KpiPersonnelReferenceSyncService::class)->sync('mantri', [
            'header' => ['PN', 'NAMA', 'BO', 'UKER', 'BC'],
            'rows' => [['200', 'Mantri Workbook Lama', 'KC Madiun', 'UNIT LAMA', '3200']],
        ]);

        $this->assertTrue($result['skipped']);
        $this->assertSame(1, $result['source_records']);
        $this->assertSame($before, $this->state());
    }

    public function test_the_spreadsheet_roster_is_scheduled_for_every_minute(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) ($event->command ?? ''), 'reference:sync-mantri-spreadsheet'));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->first()->expression);
    }

    public function test_orphan_hc_rows_are_removed_when_the_source_itself_has_not_changed(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        DB::table('brihc')->insert([
            ['uniqueid_brihc' => 'reference_brihc_hc_mantri_999_BRIHC', 'pn' => '999', 'nama' => 'HC Yatim', 'jabatan' => 'MANTRI'],
            ['uniqueid_brihc' => 'unrelated-reference', 'pn' => '998', 'nama' => 'RM Tetap', 'jabatan' => 'RM MIKRO'],
        ]);

        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);

        $this->assertDatabaseMissing('brihc', ['pn' => '999']);
        $this->assertDatabaseHas('brihc', ['pn' => '998', 'jabatan' => 'RM MIKRO']);
        $this->assertDatabaseHas('brihc', ['pn' => '200', 'jabatan' => 'MANTRI']);
    }

    public function test_successful_poll_updates_health_without_invalidating_unchanged_data_and_failed_poll_keeps_it(): void
    {
        $this->fakeWorkbook();
        $this->freezeTime();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $first = Cache::get(SyncCommand::CACHE_KEY.':last_success');
        $versions = $this->versions();
        $this->travel(1)->minutes();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $second = Cache::get(SyncCommand::CACHE_KEY.':last_success');
        $this->assertNotSame($first, $second);
        $this->assertSame($this->versions(), $versions);
        $this->travel(1)->minutes();
        $this->fakeBody('Unavailable', 503);
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(1);
        $this->assertSame($second, Cache::get(SyncCommand::CACHE_KEY.':last_success'));
    }

    private function fakeWorkbook(string $name = 'Mantri Tumbuh Sumber', string $metadata = ''): void
    {
        $this->fakeBody($this->workbookBody(name: $name, metadata: $metadata));
    }

    private function fakeBody(string $body, int $status = 200): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([SyncCommand::SOURCE_URL => Http::response($body, $status)]);
    }

    private function workbookBody(string $name = 'Mantri Tumbuh Sumber', string $metadata = '', bool $missingSheet = false, bool $missingHeader = false, bool $conflict = false, ?array $rows = null): string
    {
        $workbook = new Spreadsheet;
        $workbook->getProperties()->setDescription($metadata);
        $workbook->getActiveSheet()->setTitle('Rekap');
        $headers = ['PSADESC', 'NIP', 'PERNR', 'COMPLETENAME', 'ESGDESC', 'JG', 'PG', 'JOBDESC', 'BC', 'UKER'];
        $rows ??= $this->sourceRows($name);
        if ($missingHeader) {
            $headers[8] = 'INVALID HEADER';
        }
        foreach ($rows as $title => $sheetRows) {
            if ($missingSheet && $title === 'Mantri Recovery') {
                continue;
            }
            $sheet = $workbook->createSheet()->setTitle($title);
            $sheet->fromArray(array_merge([$headers], $sheetRows));
            if ($conflict && $title === 'Mantri Tumbuh') {
                $row = $sheetRows[0];
                $row[3] = 'Nama Bertentangan';
                $sheet->fromArray([$row], null, 'A3');
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'mantri_fixture_');
        try {
            (new Xlsx($workbook))->save($path);

            return file_get_contents($path);
        } finally {
            unlink($path);
            $workbook->disconnectWorksheets();
        }
    }

    private function sourceRows(string $name = 'Mantri Tumbuh Sumber'): array
    {
        return [
            'Mantri Tumbuh' => [['KC Madiun', 'NIP-200', '000200', $name, 'PT', 'JG05', 'PG06', 'MANTRI', '003200', 'UNIT MADIUN']],
            'Mantri Briguna' => [['KC Magetan', 'NIP-300', '000300', 'Mantri Briguna Sumber', 'PT', 'JG06', 'PG07', 'MANTRI BRIGUNA', '000049', 'KC MAGETAN']],
            'Mantri Recovery' => [['KC Ponorogo', 'NIP-400', '000400', 'Mantri Recovery Sumber', 'PT', 'JG05', 'PG06', 'MANTRI RECOVERY', '003202', 'UNIT PONOROGO']],
        ];
    }

    private function versions(): array
    {
        return array_map(fn (string $scope): int => ReportCacheVersion::get($scope), ['pinjaman', 'consumer', 'brihc']);
    }

    private function state(): array
    {
        return [
            DB::table('brihc')->orderBy('uniqueid_brihc')->get()->map(fn ($row): array => (array) $row)->all(),
            DB::table('brihc_pemasar')->orderBy('uniqueid_namareport')->get()->map(fn ($row): array => (array) $row)->all(),
            $this->versions(),
            Cache::get(SyncCommand::CACHE_KEY),
        ];
    }
}
