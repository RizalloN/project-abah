<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KejarLabaReportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class KejarLabaReportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->date('periode')->nullable();
            $table->string('acctno')->nullable();
            $table->string('kanwil')->nullable();
            $table->string('kanca')->nullable();
            $table->string('unit')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->decimal('pokok', 20, 2)->nullable();
        });
    }

    public function test_ph_recovery_does_not_use_non_month_end_previous_period(): void
    {
        DB::table('lw325_ph')->insert([
            [
                'periode' => '2026-04-29',
                'acctno' => '123',
                'kanwil' => 'K1',
                'kanca' => 'KC Madiun',
                'unit' => 'Unit A',
                'segmen_dashboard' => 'Small',
                'pokok' => 100_000_000,
            ],
            [
                'periode' => '2026-04-30',
                'acctno' => '123',
                'kanwil' => 'K1',
                'kanca' => 'KC Madiun',
                'unit' => 'Unit A',
                'segmen_dashboard' => 'Small',
                'pokok' => 50_000_000,
            ],
        ]);

        $metrics = $this->invokeBranchPhMetrics('2026-04-30', ['KC Madiun']);

        $this->assertSame(0, $metrics['KC Madiun']['small']);
        $this->assertSame(0, $metrics['KC Madiun']['total']);
    }

    public function test_ph_recovery_uses_exact_previous_month_end_period(): void
    {
        DB::table('lw325_ph')->insert([
            [
                'periode' => '2026-03-31',
                'acctno' => '123',
                'kanwil' => 'K1',
                'kanca' => 'KC Madiun',
                'unit' => 'Unit A',
                'segmen_dashboard' => 'Small',
                'pokok' => 100_000_000,
            ],
            [
                'periode' => '2026-03-31',
                'acctno' => '456',
                'kanwil' => 'K1',
                'kanca' => 'KC Madiun',
                'unit' => 'Unit A',
                'segmen_dashboard' => 'Small',
                'pokok' => 25_000_000,
            ],
            [
                'periode' => '2026-04-30',
                'acctno' => '123',
                'kanwil' => 'K1',
                'kanca' => 'KC Madiun',
                'unit' => 'Unit A',
                'segmen_dashboard' => 'Small',
                'pokok' => 60_000_000,
            ],
        ]);

        $metrics = $this->invokeBranchPhMetrics('2026-04-30', ['KC Madiun']);

        $this->assertSame(65_000_000.0, $metrics['KC Madiun']['small']);
        $this->assertSame(65_000_000.0, $metrics['KC Madiun']['total']);
    }

    private function invokeBranchPhMetrics(string $period, array $branches): array
    {
        $controller = new KejarLabaReportController();
        $method = new ReflectionMethod(KejarLabaReportController::class, 'getBranchOfficeRecoveryMetricsFromPh');
        $method->setAccessible(true);

        return $method->invoke($controller, $period, $branches);
    }
}
