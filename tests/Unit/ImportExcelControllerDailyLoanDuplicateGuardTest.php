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

    private function invokeControllerMethod(string $method, array $arguments): mixed
    {
        $controller = new ImportExcelController();
        $reflection = new ReflectionClass($controller);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invokeArgs($controller, $arguments);
    }
}
