<?php

namespace Tests\Feature;

use App\Console\Commands\SyncMantriSpreadsheetCommand;
use App\Support\ReportCacheVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MicroBrihcAreaScopeTest extends TestCase
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
            foreach (['completename', 'nip', 'pernr', 'esgdesc', 'padesc', 'psadesc', 'orgdesc', 'positiondesc', 'jobgrade', 'bc', 'pn_mantri', 'jg', 'pg', 'tmt_masuk', 'tmt_jabatan'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
        Http::preventStrayRequests();
    }

    public function test_a_valid_spreadsheet_removes_only_outside_area_six_mantri_roles_from_both_tables(): void
    {
        foreach (['MANTRI', 'MANTRI BRIGUNA', 'MANTRI RECOVERY'] as $index => $role) {
            $this->insertPerson('outside-'.($index + 1), (string) (900 + $index), $role, ['KC Jember', 'KC Situbondo', 'KC Banyuwangi'][$index]);
        }
        $this->insertPerson('outside-unrelated-role', '950', 'RM BISNIS KECIL', 'KC Jember');
        $this->insertPerson('outside-similar-role', '951', 'MANTRI COLLECTION', 'KC Jember');
        $this->insertPerson('area-unrelated-role', '952', 'RM MIKRO', 'KC Madiun');
        $this->fakeWorkbook();

        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);

        foreach (['outside-1', 'outside-2', 'outside-3'] as $id) {
            $this->assertDatabaseMissing('brihc', ['uniqueid_brihc' => $id]);
            $this->assertDatabaseMissing('brihc_pemasar', ['uniqueid_namareport' => $id]);
        }
        foreach (['outside-unrelated-role', 'outside-similar-role', 'area-unrelated-role'] as $id) {
            $this->assertDatabaseHas('brihc', ['uniqueid_brihc' => $id]);
            $this->assertDatabaseHas('brihc_pemasar', ['uniqueid_namareport' => $id]);
        }
        foreach (['200' => ['MANTRI', 'KC Madiun'], '201' => ['MANTRI', 'KC Ngawi'], '300' => ['MANTRI BRIGUNA', 'KC Magetan'], '400' => ['MANTRI RECOVERY', 'KC Ponorogo']] as $pn => [$role, $branch]) {
            $this->assertDatabaseHas('brihc', ['pn' => (string) $pn, 'jabatan' => $role]);
            $this->assertDatabaseHas('brihc_pemasar', ['pernr' => (string) $pn, 'positiondesc' => $role, 'psadesc' => $branch]);
        }
    }

    public function test_unchanged_source_repairs_outside_rows_inserted_after_a_successful_poll(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $beforeVersions = $this->versions();
        foreach (['MANTRI', 'MANTRI BRIGUNA', 'MANTRI RECOVERY'] as $index => $role) {
            $this->insertPerson('late-outside-'.($index + 1), (string) (900 + $index), $role, 'KC Jember');
        }

        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);

        foreach (['late-outside-1', 'late-outside-2', 'late-outside-3'] as $id) {
            $this->assertDatabaseMissing('brihc', ['uniqueid_brihc' => $id]);
            $this->assertDatabaseMissing('brihc_pemasar', ['uniqueid_namareport' => $id]);
        }
        $this->assertSame(array_map(static fn (int $version): int => $version + 1, $beforeVersions), $this->versions());
        $cleanState = $this->state();

        $this->artisan('reference:sync-mantri-spreadsheet')
            ->expectsOutputToContain('tidak berubah')->assertExitCode(0);

        $this->assertSame($cleanState, $this->state());
    }

    public function test_an_invalid_spreadsheet_preserves_references_and_outside_rows_until_a_valid_poll(): void
    {
        $this->fakeWorkbook();
        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(0);
        $this->insertPerson('outside-before-invalid', '900', 'MANTRI', 'KC Jember');
        $before = $this->state();
        $this->fakeWorkbook(invalidBranch: true);

        $this->artisan('reference:sync-mantri-spreadsheet')->assertExitCode(1);

        $this->assertSame($before, $this->state());
        $this->assertDatabaseHas('brihc', ['uniqueid_brihc' => 'outside-before-invalid']);
        $this->assertDatabaseHas('brihc_pemasar', ['uniqueid_namareport' => 'outside-before-invalid']);
    }

    public function test_dry_run_does_not_purge_outside_rows_or_change_reference_versions(): void
    {
        $this->insertPerson('outside-dry-run', '900', 'MANTRI RECOVERY', 'KC Jember');
        $this->fakeWorkbook();
        $before = $this->state();

        $this->artisan('reference:sync-mantri-spreadsheet', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame($before, $this->state());
    }

    private function insertPerson(string $id, string $pn, string $role, string $branch): void
    {
        DB::table('brihc')->insert(['uniqueid_brihc' => $id, 'pn' => $pn, 'nama' => 'Person '.$pn, 'jabatan' => $role]);
        DB::table('brihc_pemasar')->insert([
            'uniqueid_namareport' => $id, 'pernr' => $pn, 'completename' => 'Person '.$pn,
            'positiondesc' => $role, 'psadesc' => $branch, 'orgdesc' => 'UNIT SUMBER', 'bc' => '3200',
        ]);
    }

    private function fakeWorkbook(bool $invalidBranch = false): void
    {
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->setTitle('Rekap');
        $header = ['PSADESC', 'NIP', 'PERNR', 'COMPLETENAME', 'ESGDESC', 'JG', 'PG', 'JOBDESC', 'BC', 'UKER'];
        $sources = [
            'Mantri Tumbuh' => [
                [$invalidBranch ? 'KC Jember' : 'KC Madiun', 'NIP-200', '000200', 'Tumbuh Sumber', 'PT', 'JG05', 'PG06', 'MANTRI', '003200', 'UNIT MADIUN'],
                ['KC Ngawi', 'NIP-201', '000201', 'Tumbuh Ngawi Sumber', 'KONTRAK', 'JG05', 'PG06', 'JUNIOR ASSOCIATE MANTRI', '003201', 'UNIT NGAWI'],
            ],
            'Mantri Briguna' => [['KC Magetan', 'NIP-300', '000300', 'Briguna Sumber', 'PT', 'JG06', 'PG07', 'MANTRI BRIGUNA', '000049', 'KC MAGETAN']],
            'Mantri Recovery' => [['KC Ponorogo', 'NIP-400', '000400', 'Recovery Sumber', 'PT', 'JG05', 'PG06', 'MANTRI RECOVERY', '003202', 'UNIT PONOROGO']],
        ];
        foreach ($sources as $title => $rows) {
            $workbook->createSheet()->setTitle($title)->fromArray(array_merge([$header], $rows));
        }
        $path = tempnam(sys_get_temp_dir(), 'micro_area_scope_');
        try {
            (new Xlsx($workbook))->save($path);
            $body = file_get_contents($path);
        } finally {
            unlink($path);
            $workbook->disconnectWorksheets();
        }
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([SyncMantriSpreadsheetCommand::SOURCE_URL => Http::response($body)]);
    }

    private function versions(): array
    {
        return array_map(static fn (string $scope): int => ReportCacheVersion::get($scope), ['pinjaman', 'consumer', 'brihc']);
    }

    private function state(): array
    {
        return [
            DB::table('brihc')->orderBy('uniqueid_brihc')->get()->map(static fn ($row): array => (array) $row)->all(),
            DB::table('brihc_pemasar')->orderBy('uniqueid_namareport')->get()->map(static fn ($row): array => (array) $row)->all(),
            $this->versions(), Cache::get(SyncMantriSpreadsheetCommand::CACHE_KEY),
        ];
    }
}
