<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class ImportExcelControllerDailyLoanDuplicateGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->string('uniqueid_namareport')->nullable();
            $table->date('periode')->nullable();
        });
        Schema::create('lw321pn', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
        });
    }

    public function test_daily_loan_duplicate_guard_rejects_existing_period(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'periode' => '2026-05-24',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('periode=2026-05-24');

        $this->invokeControllerMethod('assertDailyLoanImportPeriodsEmptyOrFail', [
            ['24052026'],
        ]);
    }

    public function test_daily_loan_duplicate_guard_allows_empty_period(): void
    {
        $this->invokeControllerMethod('assertDailyLoanImportPeriodsEmptyOrFail', [
            ['2026-05-25'],
        ]);

        $this->assertTrue(true);
    }

    public function test_daily_loan_duplicate_guard_allows_raw_lw321_for_the_same_period(): void
    {
        DB::table('lw321pn')->insert([
            'periode' => '2026-05-25',
        ]);

        $this->invokeControllerMethod('assertDailyLoanImportPeriodsEmptyOrFail', [
            ['2026-05-25'],
        ]);

        $this->assertSame(1, DB::table('lw321pn')->where('periode', '2026-05-25')->count());
    }

    public function test_daily_loan_duplicate_guard_allows_a_materialized_lw321_target_period(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'LW321PN:materialized-row',
            'periode' => '2026-05-25',
        ]);

        $this->invokeControllerMethod('assertDailyLoanImportPeriodsEmptyOrFail', [
            ['2026-05-25'],
        ]);

        $this->assertSame(1, DB::table('daily_loan_dinamis')->where('periode', '2026-05-25')->count());
    }

    public function test_fast_path_takeover_deletes_only_materialized_lw321_rows(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            [
                'uniqueid_namareport' => 'LW321PN:materialized-row',
                'periode' => '2026-05-25',
            ],
            [
                'uniqueid_namareport' => 'DAILY:authoritative-row',
                'periode' => '2026-05-25',
            ],
        ]);

        $deleted = $this->invokeControllerMethod('deleteLw321MaterializedRowsForPeriods', [
            ['2026-05-25'],
        ]);

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('daily_loan_dinamis', [
            'uniqueid_namareport' => 'LW321PN:materialized-row',
        ]);
        $this->assertDatabaseHas('daily_loan_dinamis', [
            'uniqueid_namareport' => 'DAILY:authoritative-row',
        ]);
    }

    public function test_legacy_direct_takeover_callback_composes_existing_callback_and_rolls_back_with_load_transaction(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'LW321PN:materialized-row',
            'periode' => '2026-05-25',
        ]);

        $beforeLoadCalled = false;
        $callback = $this->invokeControllerMethod('buildDailyLoanTakeoverBeforeLoadCallback', [
            ['2026-05-25'],
            function (\PDO $pdo) use (&$beforeLoadCalled): void {
                $beforeLoadCalled = $pdo->inTransaction();
            },
        ]);

        DB::beginTransaction();
        try {
            $callback(DB::connection()->getPdo());
            $this->assertTrue($beforeLoadCalled);
            $this->assertDatabaseMissing('daily_loan_dinamis', [
                'uniqueid_namareport' => 'LW321PN:materialized-row',
            ]);
        } finally {
            DB::rollBack();
        }

        $this->assertDatabaseHas('daily_loan_dinamis', [
            'uniqueid_namareport' => 'LW321PN:materialized-row',
        ]);
    }

    private function invokeControllerMethod(string $method, array $arguments): mixed
    {
        $controller = new ImportExcelController();
        $reflection = new ReflectionClass($controller);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invokeArgs($controller, $arguments);
    }
}
