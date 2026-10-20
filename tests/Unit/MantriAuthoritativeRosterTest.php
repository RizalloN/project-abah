<?php

namespace Tests\Unit;

use App\Console\Commands\SyncMantriSpreadsheetCommand;
use App\Support\MantriPerformanceBasis;
use App\Support\MantriProductivityReference;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MantriAuthoritativeRosterTest extends TestCase
{
    private string $referencePath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Cache::forget(SyncMantriSpreadsheetCommand::CACHE_KEY);
        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            foreach (['pernr', 'completename', 'positiondesc', 'psadesc', 'orgdesc', 'esgdesc', 'bc'] as $column) {
                $table->string($column)->nullable();
            }
        });

        $this->referencePath = tempnam(sys_get_temp_dir(), 'mantri_authority_');
        file_put_contents($this->referencePath, json_encode([
            'version' => 1, 'month_start' => '2026-10-01', 'as_of' => '2026-10-16', 'baseline_period' => '2026-09-30',
            'sources' => ['old-workbook.xlsx'], 'accounts' => [], 'baseline_accounts' => [], 'current_accounts' => [],
            'people' => [['pn' => '999', 'name' => 'Old Workbook Person', 'branch' => 'KC MADIUN', 'unit' => 'UNIT A', 'bc' => '45', 'category' => 'pt']],
        ], JSON_THROW_ON_ERROR));
        $this->app->instance(MantriProductivityReference::class, new MantriProductivityReference($this->referencePath));
    }

    protected function tearDown(): void
    {
        unlink($this->referencePath);
        parent::tearDown();
    }

    #[DataProvider('remainingAuthoritativeRoles')]
    public function test_remaining_spreadsheet_role_prevents_old_tumbuh_roster_from_returning_after_cache_loss(string $role): void
    {
        $this->insertPerson($role);

        $state = app(MantriPerformanceBasis::class)->rosterState('2026-10-16');

        $this->assertTrue($state['available']);
        $this->assertSame([], $state['keys']);
        $this->assertSame([], $state['people']);
        $this->assertFalse(Cache::has(SyncMantriSpreadsheetCommand::CACHE_KEY));
    }

    public static function remainingAuthoritativeRoles(): array
    {
        return [['MANTRI BRIGUNA'], ['MANTRI RECOVERY']];
    }

    #[DataProvider('unrelatedPersonnel')]
    public function test_unrelated_personnel_do_not_claim_authority_for_the_area_six_mantri_roster(string $role, string $branch): void
    {
        $this->insertPerson($role, $branch);

        $state = app(MantriPerformanceBasis::class)->rosterState('2026-10-16');

        $this->assertSame(['999'], $state['keys']);
        $this->assertSame('workbook_reference', $state['people']['999']['roster_source']);
    }

    public static function unrelatedPersonnel(): array
    {
        return [['MANTRI BRIGUNA', 'KC SURABAYA'], ['RM MIKRO', 'KC MADIUN']];
    }

    public function test_existing_successful_sync_marker_prevents_workbook_fallback_when_the_roster_is_empty(): void
    {
        Cache::forever(SyncMantriSpreadsheetCommand::CACHE_KEY, ['source_hash' => 'source', 'database_hash' => 'database']);

        $state = app(MantriPerformanceBasis::class)->rosterState('2026-10-16');

        $this->assertSame([], $state['keys']);
        $this->assertSame([], $state['people']);
    }

    #[DataProvider('employmentCategories')]
    public function test_employment_category_follows_manual_probation_rule_without_including_other_roles_or_branches(string $status, string $expectedCategory): void
    {
        $this->insertPerson('MANTRI', 'KC MADIUN', $status);
        foreach ([
            ['102', 'MANTRI BRIGUNA', 'KC MADIUN'],
            ['103', 'MANTRI RECOVERY', 'KC MADIUN'],
            ['104', 'MANTRI', 'KC SURABAYA'],
        ] as [$pn, $role, $branch]) {
            DB::table('brihc_pemasar')->insert([
                'uniqueid_namareport' => 'reference_brihc_hc_'.$pn, 'pernr' => $pn,
                'completename' => 'Excluded Spreadsheet Person', 'positiondesc' => $role,
                'psadesc' => $branch, 'orgdesc' => 'UNIT A', 'esgdesc' => 'Probation', 'bc' => '45',
            ]);
        }

        $state = app(MantriPerformanceBasis::class)->rosterState('2026-10-16');

        $this->assertTrue($state['available']);
        $this->assertSame(['101'], $state['keys']);
        $this->assertCount(1, $state['people']);
        $this->assertSame($expectedCategory, $state['people']['101']['category']);
        $this->assertSame('KC MADIUN', $state['people']['101']['branch']);
    }

    public static function employmentCategories(): array
    {
        return [
            'mixed case probation' => [' ProBaTiOn ', 'contract'],
            'probation takes precedence over PT token' => ['PT / Probation', 'contract'],
            'existing contract' => ['Pekerja Kontrak', 'contract'],
            'existing permanent worker' => ['PT (peserta pens.)', 'pt'],
        ];
    }

    private function insertPerson(string $role, string $branch = 'KC MADIUN', string $status = 'PT'): void
    {
        DB::table('brihc_pemasar')->insert([
            'uniqueid_namareport' => 'reference_brihc_hc_101', 'pernr' => '101',
            'completename' => 'Current Spreadsheet Person', 'positiondesc' => $role,
            'psadesc' => $branch, 'orgdesc' => 'UNIT A', 'esgdesc' => $status, 'bc' => '45',
        ]);
    }
}
