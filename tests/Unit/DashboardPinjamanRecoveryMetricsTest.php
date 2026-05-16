<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardPinjamanReportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DashboardPinjamanRecoveryMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        Cache::flush();

        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->date('periode')->index();
            $table->string('acctno')->nullable();
            $table->decimal('pokok', 20, 2)->nullable();
        });

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->date('periode')->index();
        });
    }

    public function test_recovery_metrics_rejects_collapsed_scientific_lw325_accounts(): void
    {
        DB::table('lw325_ph')->insert([
            ['periode' => '2025-04-30', 'acctno' => '6,49201E+14', 'pokok' => 1000000],
            ['periode' => '2025-04-30', 'acctno' => '6,49201E+14', 'pokok' => 2000000],
            ['periode' => '2025-04-30', 'acctno' => '6,50501E+14', 'pokok' => 3000000],
        ]);

        $this->assertFalse($this->invokeShouldUseLw325RecoveryMetrics('2025-04-30'));
    }

    public function test_recovery_metrics_accepts_clean_lw325_accounts(): void
    {
        DB::table('lw325_ph')->insert([
            ['periode' => '2026-04-30', 'acctno' => '649201027193100', 'pokok' => 1000000],
            ['periode' => '2026-04-30', 'acctno' => '650501027193101', 'pokok' => 2000000],
            ['periode' => '2026-04-30', 'acctno' => '650501027193102', 'pokok' => 3000000],
        ]);

        $this->assertTrue($this->invokeShouldUseLw325RecoveryMetrics('2026-04-30'));
    }

    public function test_recovery_previous_ph_period_must_be_previous_month_end(): void
    {
        DB::table('lw325_ph')->insert([
            ['periode' => '2026-03-30', 'acctno' => '649201027193100', 'pokok' => 1000000],
            ['periode' => '2026-04-30', 'acctno' => '649201027193100', 'pokok' => 900000],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'resolvePreviousMonthPhPeriod');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller, '2026-04-30'));

        DB::table('lw325_ph')->insert([
            ['periode' => '2026-03-31', 'acctno' => '649201027193100', 'pokok' => 1000000],
        ]);

        $this->assertSame('2026-03-31', $method->invoke($controller, '2026-04-30'));
    }

    public function test_recovery_comparison_period_uses_exact_previous_month_end(): void
    {
        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'resolveComparisonPeriod');
        $method->setAccessible(true);

        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-02-27'],
            ['periode' => '2026-02-28'],
            ['periode' => '2026-03-31'],
        ]);

        $this->assertSame('2026-02-28', $method->invoke($controller, '2026-03-31'));

        DB::table('daily_loan_dinamis')->delete();

        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-03-30'],
            ['periode' => '2026-03-31'],
            ['periode' => '2026-04-30'],
        ]);

        $this->assertSame('2026-03-31', $method->invoke($controller, '2026-04-30'));

        DB::table('daily_loan_dinamis')->where('periode', '2026-03-31')->delete();

        $this->assertNull($method->invoke($controller, '2026-04-30'));
    }

    public function test_recovery_period_options_include_daily_loan_dates_with_comparison_period(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-03-31'],
            ['periode' => '2026-04-15'],
            ['periode' => '2026-04-30'],
            ['periode' => '2026-05-10'],
            ['periode' => '2026-05-11'],
            ['periode' => '2026-06-01'],
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'fetchRecoveryReportPeriods');
        $method->setAccessible(true);

        $periods = $method->invoke($controller)->all();

        $this->assertContains('2026-05-11', $periods);
        $this->assertContains('2026-05-10', $periods);
        $this->assertContains('2026-04-15', $periods);
        $this->assertNotContains('2026-06-01', $periods);
    }

    private function invokeShouldUseLw325RecoveryMetrics(string $period): bool
    {
        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod(DashboardPinjamanReportController::class, 'shouldUseLw325RecoveryMetrics');
        $method->setAccessible(true);

        return (bool) $method->invoke($controller, $period);
    }
}
