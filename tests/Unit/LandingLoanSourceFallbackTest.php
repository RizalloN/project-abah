<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class LandingLoanSourceFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();
        foreach (['daily_loan_dinamis', 'dashboard_pinjaman_snapshots'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->date('periode');
                $table->string('cabang1');
                $table->string('unit1');
                $table->string($name === 'daily_loan_dinamis' ? 'nomor_rekening1' : 'account_number');
                $table->decimal($name === 'daily_loan_dinamis' ? 'baki_debet1' : 'loan_balance', 20, 2);
                $table->timestamp('updated_at')->nullable();
            });
        }
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-09-30', 'cabang1' => 'KC Madiun', 'unit1' => 'Unit A', 'nomor_rekening1' => '1', 'baki_debet1' => 125],
            ['periode' => '2026-09-30', 'cabang1' => 'KC Ngawi', 'unit1' => 'Unit B', 'nomor_rekening1' => '2', 'baki_debet1' => 900],
        ]);
    }

    public function test_empty_snapshot_falls_back_to_daily_loan_for_selected_branch(): void
    {
        $controller = app(DashboardSimpananController::class);
        $this->invoke($controller, 'configureLandingBranchFromKey', 'madiun');
        $summary = $this->invoke($controller, 'buildLoanSummary', '2026-09-30');
        $branches = $this->invoke($controller, 'fetchLoanTopBranches', '2026-09-30');

        $this->assertSame('daily_loan_dinamis', $summary['source_table']);
        $this->assertSame(125.0, $summary['total_balance']);
        $this->assertSame(1, $summary['account_count']);
        $this->assertCount(1, $branches);
        $this->assertSame('KC Madiun', $branches->first()['full_label']);
        $this->assertSame(125.0, $branches->first()['balance']);

        $other = app(DashboardSimpananController::class);
        $this->invoke($other, 'configureLandingBranchFromKey', 'ngawi');
        $this->assertSame(900.0, $this->invoke($other, 'buildLoanSummary', '2026-09-30')['total_balance']);
        $this->assertSame('KC Ngawi', $this->invoke($other, 'fetchLoanTopBranches', '2026-09-30')->first()['full_label']);
    }

    public function test_snapshot_from_another_period_does_not_block_daily_fallback(): void
    {
        DB::table('dashboard_pinjaman_snapshots')->insert([
            'periode' => '2026-08-31', 'cabang1' => 'KC Madiun', 'unit1' => 'Unit A', 'account_number' => '1', 'loan_balance' => 999,
        ]);
        $controller = app(DashboardSimpananController::class);
        $this->invoke($controller, 'configureLandingBranchFromKey', 'madiun');
        $this->assertSame(125.0, $this->invoke($controller, 'buildLoanSummary', '2026-09-30')['total_balance']);
        $this->assertSame(125.0, $this->invoke($controller, 'fetchLoanTopBranches', '2026-09-30')->first()['balance']);
    }

    public function test_real_snapshot_with_zero_balance_remains_authoritative(): void
    {
        DB::table('dashboard_pinjaman_snapshots')->insert([
            'periode' => '2026-09-30', 'cabang1' => 'KC Madiun', 'unit1' => 'Unit A', 'account_number' => '1', 'loan_balance' => 0,
        ]);
        $controller = app(DashboardSimpananController::class);
        $this->invoke($controller, 'configureLandingBranchFromKey', 'madiun');
        $summary = $this->invoke($controller, 'buildLoanSummary', '2026-09-30');
        $this->assertSame('dashboard_pinjaman_snapshots', $summary['source_table']);
        $this->assertSame(0.0, $summary['total_balance']);
        $this->assertSame(1, $summary['account_count']);
        $this->assertSame(0.0, $this->invoke($controller, 'fetchLoanTopBranches', '2026-09-30')->first()['balance']);
    }

    private function invoke(object $target, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($target, $method))->invoke($target, ...$arguments);
    }
}
