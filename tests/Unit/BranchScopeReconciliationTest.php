<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Jobs\WarmDashboardSimpananCacheJob;
use App\Models\User;
use App\Support\DashboardDanaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class BranchScopeReconciliationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('ssa_simpanan', function (Blueprint $table): void {
            $table->date('Month_Day_Year_of_Posisi');
            $table->string('nama_cabang');
            $table->string('nama_uker');
            $table->string('produk');
            $table->string('segmentasi');
            $table->decimal('saldo', 20, 2);
        });
    }

    public function test_area_dana_fallback_combines_branch_name_variants_into_four_kc_rows(): void
    {
        DB::table('ssa_simpanan')->insert([
            $this->fundingRow('00045 -- KC Madiun (Konsolidasi-MB)', 10),
            $this->fundingRow('00045 -- KC Madiun(Konsolidasi-MB)', 20),
            $this->fundingRow('00049 -- KC Magetan(Konsolidasi-MB)', 40),
            $this->fundingRow('00057 -- KC Ngawi(Konsolidasi-MB)', 60),
            $this->fundingRow('00070 -- KC Ponorogo(Konsolidasi-MB)', 80),
            $this->fundingRow('00099 -- KC Di Luar Area (Konsolidasi-MB)', 999),
        ]);

        $payload = app(DashboardDanaService::class)->getDashboardData('2026-09-12', 'all');
        $totals = collect($payload['rows'])->where('is_total', true)->keyBy('nama_cabang');

        $this->assertSame(
            ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'],
            $totals->keys()->all()
        );
        $this->assertEqualsWithDelta(30, $totals['KC MADIUN']['selected'], 0.01);
        $this->assertEqualsWithDelta(210, $payload['total']['selected'], 0.01);
    }

    public function test_presentation_refresh_job_keeps_the_requesting_branch_scope(): void
    {
        Bus::fake();
        $this->actingAs(new User(['pn' => '0045', 'branch_scope' => 'madiun']));

        (new ReflectionMethod(DashboardSimpananController::class, 'deferPresentationPayloadRefresh'))
            ->invoke(app(DashboardSimpananController::class), '2026-09-12');

        Bus::assertDispatched(WarmDashboardSimpananCacheJob::class, function (WarmDashboardSimpananCacheJob $job): bool {
            return $job->type === 'presentation-payload'
                && ($job->context['landingBranchKey'] ?? null) === 'madiun';
        });
    }

    public function test_branch_dana_raw_fallback_shows_only_its_own_work_units(): void
    {
        DB::table('ssa_simpanan')->insert([
            $this->fundingRow('00045 -- KC Madiun (Konsolidasi-MB)', 10, 'KC Madiun'),
            $this->fundingRow('00045 -- KC Madiun(Konsolidasi-MB)', 20, 'KCP Caruban'),
            $this->fundingRow('00049 -- KC Magetan(Konsolidasi-MB)', 40, 'KC Magetan'),
        ]);

        $payload = app(DashboardDanaService::class)->getDashboardData('2026-09-12', 'Ritel', null, 'KC Madiun');
        $totals = collect($payload['rows'])->where('is_total', true)->keyBy('nama_cabang');

        $this->assertSame(['KC MADIUN', 'KCP CARUBAN'], $totals->keys()->all());
        $this->assertEqualsWithDelta(10, $totals['KC MADIUN']['selected'], 0.01);
        $this->assertEqualsWithDelta(20, $totals['KCP CARUBAN']['selected'], 0.01);
        $this->assertEqualsWithDelta(30, $payload['total']['selected'], 0.01);
    }

    private function fundingRow(string $branch, int $balance, ?string $unit = null): array
    {
        return [
            'Month_Day_Year_of_Posisi' => '2026-09-12',
            'nama_cabang' => $branch,
            'nama_uker' => $unit ?? $branch,
            'produk' => 'Tabungan',
            'segmentasi' => 'Ritel',
            'saldo' => $balance,
        ];
    }
}
