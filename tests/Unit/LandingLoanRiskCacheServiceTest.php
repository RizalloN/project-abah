<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Jobs\WarmLandingLoanRiskCacheJob;
use App\Support\LandingLoanRiskCacheService;
use App\Support\ReportCacheVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class LandingLoanRiskCacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        Config::set('queue.default', 'sync');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->date('periode');
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
            $table->string('status_rekening1')->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->string('kolek')->nullable();
            $table->integer('umur_tunggakan')->nullable();
            $table->decimal('tunggakan_pokok', 20, 2)->nullable();
            $table->decimal('tunggakan_bunga', 20, 2)->nullable();
            $table->decimal('tunggakan_penalti', 20, 2)->nullable();
            $table->string('nomor_rekening1')->nullable();
        });

        DB::table('daily_loan_dinamis')->insert([
            $this->row('KC Madiun', 'UNIT A MADIUN', 'A-1', 1000, '1', 50, 40000, 10000, 5000),
            $this->row('KC Madiun', 'UNIT A MADIUN', 'A-2', 2000, '2', 50, 50000, 10000, 0),
            $this->row('KC Madiun', 'KC Madiun', 'A-3', 3000, '3', 10, 150000, 0, 0),
            $this->row('KC Magetan', 'UNIT B MAGETAN', 'B-1', 4000, '4', 190, 25000, 0, 0),
            $this->row('KC Banyuwangi', 'UNIT LUAR AREA', 'X-1', 9000, '1', 50, 10000, 0, 0),
        ]);
    }

    public function test_rebuild_materializes_exact_area_branch_and_unit_risk_metrics(): void
    {
        $payload = app(LandingLoanRiskCacheService::class)->rebuild('2026-08-29');

        $this->assertTrue($payload['available']);
        $this->assertSame(3, $payload['area_totals']['kts_count']);
        $this->assertSame(8000.0, $payload['area_totals']['kts_os']);
        $this->assertSame(3, $payload['area_totals']['small_arrears_count']);
        $this->assertSame(140000.0, $payload['area_totals']['small_arrears_amount']);

        $madiun = $payload['branch_totals']['KC MADIUN'];
        $this->assertSame(2, $madiun['kts_count']);
        $this->assertSame(4000.0, $madiun['kts_os']);
        $this->assertSame(2, $madiun['small_arrears_count']);
        $this->assertSame(115000.0, $madiun['small_arrears_amount']);

        $unit = collect($payload['rows'])->firstWhere('unit', 'UNIT A MADIUN');
        $this->assertSame(1, $unit['kts_count']);
        $this->assertSame(2, $unit['small_arrears_count']);
    }

    public function test_http_snapshot_path_never_queries_daily_loan_and_keeps_stable_data_across_version_bump(): void
    {
        $service = app(LandingLoanRiskCacheService::class);
        $service->rebuild('2026-08-29');
        ReportCacheVersion::bump('pinjaman');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $payload = $service->snapshot('2026-08-29');
        $queries = DB::getQueryLog();

        $this->assertSame([], $queries);
        $this->assertTrue($payload['refresh_pending']);
        $this->assertSame(3, $payload['area_totals']['kts_count']);
    }

    public function test_controller_ranking_uses_materialized_cache_and_preserves_scope_rules(): void
    {
        app(LandingLoanRiskCacheService::class)->rebuild('2026-08-29');
        $controller = app(DashboardSimpananController::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $micro = $this->invokeRanking($controller, 'buildArea6KtsRanking', 'unit');
        $retail = $this->invokeRanking($controller, 'buildArea6KtsRanking', 'retail');
        $queries = DB::getQueryLog();

        $this->assertSame([], $queries);
        $this->assertSame(3, $micro['total_count']);
        $this->assertSame(['UNIT B MAGETAN', 'UNIT A MADIUN'], array_column($micro['rows'], 'label'));
        $this->assertSame(['KC Madiun'], array_column($retail['rows'], 'label'));
    }

    public function test_background_job_forces_a_rebuild_on_the_low_priority_report_queue(): void
    {
        $service = new class extends LandingLoanRiskCacheService
        {
            public ?string $rebuiltPeriod = null;

            public function rebuild(string $period): array
            {
                $this->rebuiltPeriod = $period;

                return [];
            }
        };
        $job = new WarmLandingLoanRiskCacheJob('2026-08-29');

        $this->assertSame('reports-low', $job->queue);
        $job->handle($service);
        $this->assertSame('2026-08-29', $service->rebuiltPeriod);
    }

    /** @return array<string, mixed> */
    private function row(
        string $branch,
        string $unit,
        string $account,
        float $balance,
        string $kolek,
        int $age,
        float $principalArrears,
        float $interestArrears,
        float $penaltyArrears
    ): array {
        return [
            'periode' => '2026-08-29',
            'cabang1' => $branch,
            'unit1' => $unit,
            'status_rekening1' => '1',
            'baki_debet1' => $balance,
            'kolek' => $kolek,
            'umur_tunggakan' => $age,
            'tunggakan_pokok' => $principalArrears,
            'tunggakan_bunga' => $interestArrears,
            'tunggakan_penalti' => $penaltyArrears,
            'nomor_rekening1' => $account,
        ];
    }

    /** @return array<string, mixed> */
    private function invokeRanking(
        DashboardSimpananController $controller,
        string $method,
        string $scope
    ): array {
        $reflection = new ReflectionMethod($controller, $method);

        return $reflection->invoke($controller, '2026-08-29', $scope);
    }
}
