<?php

namespace Tests\Unit;

use App\Jobs\EnsureImportedSnapshotsFreshJob;
use App\Support\DashboardHarianSnapshotService;
use App\Support\ReportSnapshotBuilder;
use App\Support\SnapshotSourceSignatureService;
use App\Support\SsaSimpananSnapshotBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class EnsureImportedSnapshotsFreshJobTest extends TestCase
{
    private SnapshotSourceSignatureService $sourceSignatures;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        Config::set('import.snapshot.enable_analyze_table', false);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        $this->createTables();
        $this->sourceSignatures = new SnapshotSourceSignatureService();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_daily_loan_freshness_rebuilds_legacy_performance_rm_snapshot(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'periode' => '2026-05-06',
            'baki_debet1' => 1000,
            'created_at' => '2026-05-08 10:00:00',
            'updated_at' => '2026-05-08 10:00:00',
        ]);

        DB::table('dashboard_pinjaman_snapshots')->insert($this->snapshotRow('periode', '2026-05-06', '2026-05-08 11:00:00'));
        DB::table('dashboard_pinjaman_chart_periodik_snapshots')->insert($this->snapshotRow('periode', '2026-05-06', '2026-05-08 11:00:00'));
        DB::table('performance_rm_snapshots')->insert($this->snapshotRow('periode', '2026-05-06', '2026-05-08 09:00:00'));
        DB::table('rasio_casa_debitur_snapshots')->insert($this->snapshotRow('loan_period', '2026-05-06', '2026-05-08 11:00:00'));

        $metadata = $this->sourceSignatures->capture('daily_loan_dinamis', 'periode', '2026-05-06');
        $this->markFresh('daily_loan_dinamis', 'dashboard_pinjaman_snapshots', '2026-05-06', $metadata);
        $this->markFresh('daily_loan_dinamis', 'dashboard_pinjaman_chart_periodik_snapshots', '2026-05-06', $metadata);
        $this->markFresh('daily_loan_dinamis', 'rasio_casa_debitur_snapshots', '2026-05-06', $metadata);
        Cache::put('report_cache_version:pinjaman', 3, now()->addHours(24));

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $builder->shouldNotReceive('rebuildDashboard');
        $builder->shouldNotReceive('rebuildChartPeriodik');
        $builder->shouldReceive('rebuildPerformanceRm')
            ->once()
            ->with('2026-05-06', false)
            ->andReturn(['2026-05-06' => 10]);
        $builder->shouldNotReceive('rebuildRasioCasa');

        $dashboardHarian = Mockery::mock(DashboardHarianSnapshotService::class);
        $dashboardHarian->shouldNotReceive('rebuild');

        (new EnsureImportedSnapshotsFreshJob('daily_loan_dinamis', '2026-05-06', 'unit-test'))
            ->handle($builder, $dashboardHarian, $this->sourceSignatures);

        $this->assertSame(4, (int) Cache::get('report_cache_version:pinjaman'));
        $this->assertTrue($this->sourceSignatures->isFresh(
            'daily_loan_dinamis',
            'performance_rm_snapshots',
            '2026-05-06',
            $metadata
        ));
    }

    public function test_daily_loan_freshness_rebuilds_performance_rm_when_formula_version_changes(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'periode' => '2026-05-06',
            'baki_debet1' => 1000,
            'created_at' => '2026-05-08 10:00:00',
            'updated_at' => '2026-05-08 10:00:00',
        ]);

        foreach ([
            'dashboard_pinjaman_snapshots' => 'periode',
            'dashboard_pinjaman_chart_periodik_snapshots' => 'periode',
            'performance_rm_snapshots' => 'periode',
            'rasio_casa_debitur_snapshots' => 'loan_period',
        ] as $snapshotTable => $periodColumn) {
            DB::table($snapshotTable)->insert($this->snapshotRow($periodColumn, '2026-05-06', '2026-05-08 11:00:00'));
        }

        $metadata = $this->sourceSignatures->capture('daily_loan_dinamis', 'periode', '2026-05-06');
        $this->markFresh('daily_loan_dinamis', 'dashboard_pinjaman_snapshots', '2026-05-06', $metadata);
        $this->markFresh('daily_loan_dinamis', 'dashboard_pinjaman_chart_periodik_snapshots', '2026-05-06', $metadata);
        $this->markFresh('daily_loan_dinamis', 'rasio_casa_debitur_snapshots', '2026-05-06', $metadata);

        DB::table('snapshot_source_signatures')->insert([
            'source_table' => 'daily_loan_dinamis',
            'snapshot_table' => 'performance_rm_snapshots',
            'period_key' => '2026-05-06',
            'source_signature' => $metadata['source_signature'],
            'source_row_count' => $metadata['source_row_count'],
            'source_max_updated_at' => $metadata['source_max_updated_at'],
            'built_at' => now(),
            'context' => json_encode(['snapshot_formula_version' => 'performance-rm-v1-realisasi-date']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse($this->sourceSignatures->isFresh(
            'daily_loan_dinamis',
            'performance_rm_snapshots',
            '2026-05-06',
            $metadata
        ));

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $builder->shouldNotReceive('rebuildDashboard');
        $builder->shouldNotReceive('rebuildChartPeriodik');
        $builder->shouldReceive('rebuildPerformanceRm')
            ->once()
            ->with('2026-05-06', false)
            ->andReturn(['2026-05-06' => 10]);
        $builder->shouldNotReceive('rebuildRasioCasa');

        $dashboardHarian = Mockery::mock(DashboardHarianSnapshotService::class);
        $dashboardHarian->shouldNotReceive('rebuild');

        (new EnsureImportedSnapshotsFreshJob('daily_loan_dinamis', '2026-05-06', 'unit-test'))
            ->handle($builder, $dashboardHarian, $this->sourceSignatures);

        $this->assertTrue($this->sourceSignatures->isFresh(
            'daily_loan_dinamis',
            'performance_rm_snapshots',
            '2026-05-06',
            $metadata
        ));
    }

    public function test_daily_loan_freshness_rebuilds_fresh_snapshot_when_duplicate_identity_exists(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'periode' => '2026-05-06',
            'baki_debet1' => 1000,
            'created_at' => '2026-05-08 10:00:00',
            'updated_at' => '2026-05-08 10:00:00',
        ]);

        DB::table('dashboard_pinjaman_snapshots')->insert([
            $this->snapshotRow('periode', '2026-05-06', '2026-05-08 11:00:00') + [
                'account_number' => 'LOAN-1',
                'segmen_dashboard' => 'SMALL',
                'produk_dashboard' => 'KUPEDES',
                'cabang1' => 'KC PONOROGO',
                'unit1' => 'UNIT 1',
            ],
            $this->snapshotRow('periode', '2026-05-06', '2026-05-08 11:00:00') + [
                'account_number' => 'LOAN-1',
                'segmen_dashboard' => 'SMALL',
                'produk_dashboard' => 'KUPEDES',
                'cabang1' => 'KC PONOROGO',
                'unit1' => 'UNIT 1',
            ],
        ]);
        DB::table('dashboard_pinjaman_chart_periodik_snapshots')->insert($this->snapshotRow('periode', '2026-05-06', '2026-05-08 11:00:00'));
        DB::table('performance_rm_snapshots')->insert($this->snapshotRow('periode', '2026-05-06', '2026-05-08 11:00:00'));
        DB::table('rasio_casa_debitur_snapshots')->insert($this->snapshotRow('loan_period', '2026-05-06', '2026-05-08 11:00:00'));

        $metadata = $this->sourceSignatures->capture('daily_loan_dinamis', 'periode', '2026-05-06');
        $this->markFresh('daily_loan_dinamis', 'dashboard_pinjaman_snapshots', '2026-05-06', $metadata);
        $this->markFresh('daily_loan_dinamis', 'dashboard_pinjaman_chart_periodik_snapshots', '2026-05-06', $metadata);
        $this->markFresh('daily_loan_dinamis', 'performance_rm_snapshots', '2026-05-06', $metadata);
        $this->markFresh('daily_loan_dinamis', 'rasio_casa_debitur_snapshots', '2026-05-06', $metadata);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $builder->shouldReceive('rebuildDashboard')
            ->once()
            ->with('2026-05-06', false)
            ->andReturn(['2026-05-06' => 2]);
        $builder->shouldNotReceive('rebuildChartPeriodik');
        $builder->shouldNotReceive('rebuildPerformanceRm');
        $builder->shouldNotReceive('rebuildRasioCasa');

        $dashboardHarian = Mockery::mock(DashboardHarianSnapshotService::class);
        $dashboardHarian->shouldNotReceive('rebuild');

        (new EnsureImportedSnapshotsFreshJob('daily_loan_dinamis', '2026-05-06', 'unit-test'))
            ->handle($builder, $dashboardHarian, $this->sourceSignatures);

        $this->assertTrue($this->sourceSignatures->isFresh(
            'daily_loan_dinamis',
            'dashboard_pinjaman_snapshots',
            '2026-05-06',
            $metadata
        ));
    }

    public function test_simpanan_existing_snapshots_rebuild_when_source_signature_changes(): void
    {
        $this->insertReadySimpananRows('2026-05-06', 1000);
        $oldMetadata = $this->sourceSignatures->capture('simpanan_multipn', 'posisi', '2026-05-06');

        foreach ([
            'dashboard_simpanan_snapshots' => 'snapshot_period',
            'rekening_dormant_snapshots' => 'posisi',
            'performance_rm_snapshots' => 'periode',
            'rasio_casa_debitur_snapshots' => 'casa_period',
        ] as $snapshotTable => $periodColumn) {
            DB::table($snapshotTable)->insert($this->snapshotRow($periodColumn, '2026-05-06', '2026-05-08 09:00:00'));
            $this->markFresh('simpanan_multipn', $snapshotTable, '2026-05-06', $oldMetadata);
        }

        DB::table('simpanan_multipn')
            ->where('kantor_cabang', 'KC Madiun')
            ->update(['saldo_idr' => 2500]);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $builder->shouldReceive('rebuildDashboardSimpanan')->once()->with('2026-05-06', false)->andReturn(['2026-05-06' => 4]);
        $builder->shouldReceive('rebuildRekeningDormant')->once()->with('2026-05-06', false)->andReturn(['2026-05-06' => 4]);
        $builder->shouldReceive('rebuildPerformanceRm')->once()->with('2026-05-06', false)->andReturn(['2026-05-06' => 4]);
        $builder->shouldReceive('rebuildRasioCasa')->once()->with('2026-05-06', false)->andReturn(['2026-05-06' => 4]);

        $dashboardHarian = Mockery::mock(DashboardHarianSnapshotService::class);
        $dashboardHarian->shouldNotReceive('rebuild');

        (new EnsureImportedSnapshotsFreshJob('simpanan_multipn', '2026-05-06', 'unit-test'))
            ->handle($builder, $dashboardHarian, $this->sourceSignatures);

        $newMetadata = $this->sourceSignatures->capture('simpanan_multipn', 'posisi', '2026-05-06');
        $this->assertTrue($this->sourceSignatures->isFresh(
            'simpanan_multipn',
            'dashboard_simpanan_snapshots',
            '2026-05-06',
            $newMetadata
        ));
    }

    public function test_ssa_pinjaman_existing_dashboard_harian_snapshot_rebuilds_when_signature_changes(): void
    {
        DB::table('ssa_simpanan')->insert($this->ssaSimpananRow('2026-05-06', 1000));
        DB::table('ssa_pinjaman')->insert($this->ssaPinjamanRow('2026-05-06', 1500));
        DB::table('dashboard_harian_snapshots')->insert($this->snapshotRow('snapshot_period', '2026-05-06', '2026-05-08 09:00:00'));

        $oldMetadata = $this->sourceSignatures->capture('ssa_pinjaman', 'month_day_year_of_periode', '2026-05-06');
        $this->markFresh('ssa_pinjaman', 'dashboard_harian_snapshots', '2026-05-06', $oldMetadata);

        DB::table('ssa_pinjaman')->where('month_day_year_of_periode', '2026-05-06')->update(['baki_debet' => 1900]);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarian = Mockery::mock(DashboardHarianSnapshotService::class);
        $dashboardHarian->shouldReceive('rebuild')
            ->once()
            ->with('2026-05-06', false)
            ->andReturn(['2026-05-06' => 1]);

        (new EnsureImportedSnapshotsFreshJob('ssa_pinjaman', '2026-05-06', 'unit-test'))
            ->handle($builder, $dashboardHarian, $this->sourceSignatures);

        $newMetadata = $this->sourceSignatures->capture('ssa_pinjaman', 'month_day_year_of_periode', '2026-05-06');
        $this->assertTrue($this->sourceSignatures->isFresh(
            'ssa_pinjaman',
            'dashboard_harian_snapshots',
            '2026-05-06',
            $newMetadata
        ));
    }

    public function test_ssa_simpanan_existing_snapshots_rebuild_when_signature_changes(): void
    {
        DB::table('ssa_simpanan')->insert($this->ssaSimpananRow('2026-05-06', 1000));
        DB::table('ssa_pinjaman')->insert($this->ssaPinjamanRow('2026-05-06', 1500));
        DB::table('ssa_simpanan_snapshots')->insert($this->snapshotRow('periode', '2026-05-06', '2026-05-08 09:00:00'));
        DB::table('dashboard_harian_snapshots')->insert($this->snapshotRow('snapshot_period', '2026-05-06', '2026-05-08 09:00:00'));

        $oldMetadata = $this->sourceSignatures->capture('ssa_simpanan', 'Month_Day_Year_of_Posisi', '2026-05-06');
        $this->markFresh('ssa_simpanan', 'ssa_simpanan_snapshots', '2026-05-06', $oldMetadata);
        $this->markFresh('ssa_simpanan', 'dashboard_harian_snapshots', '2026-05-06', $oldMetadata);

        DB::table('ssa_simpanan')->where('Month_Day_Year_of_Posisi', '2026-05-06')->update(['saldo' => 2500]);

        $ssaBuilder = Mockery::mock(SsaSimpananSnapshotBuilder::class);
        $ssaBuilder->shouldReceive('rebuild')->once()->with('2026-05-06', false)->andReturn(['2026-05-06' => 1]);
        $this->app->instance(SsaSimpananSnapshotBuilder::class, $ssaBuilder);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarian = Mockery::mock(DashboardHarianSnapshotService::class);
        $dashboardHarian->shouldReceive('rebuild')
            ->once()
            ->with('2026-05-06', false)
            ->andReturn(['2026-05-06' => 1]);

        (new EnsureImportedSnapshotsFreshJob('ssa_simpanan', '2026-05-06', 'unit-test'))
            ->handle($builder, $dashboardHarian, $this->sourceSignatures);

        $newMetadata = $this->sourceSignatures->capture('ssa_simpanan', 'Month_Day_Year_of_Posisi', '2026-05-06');
        $this->assertTrue($this->sourceSignatures->isFresh(
            'ssa_simpanan',
            'dashboard_harian_snapshots',
            '2026-05-06',
            $newMetadata
        ));
    }

    public function test_lw325_ph_existing_affected_dashboard_harian_snapshot_rebuilds_when_signature_changes(): void
    {
        DB::table('lw325_ph')->insert([
            'periode' => '2026-05-05',
            'pokok' => 1000,
            'created_at' => '2026-05-08 10:00:00',
            'updated_at' => '2026-05-08 10:00:00',
        ]);
        DB::table('dashboard_harian_snapshots')->insert($this->snapshotRow('snapshot_period', '2026-05-06', '2026-05-08 09:00:00'));

        $oldMetadata = $this->sourceSignatures->capture('lw325_ph', 'periode', '2026-05-05');
        $this->markFresh('lw325_ph', 'dashboard_harian_snapshots', '2026-05-06', $oldMetadata);

        DB::table('lw325_ph')->where('periode', '2026-05-05')->update(['pokok' => 1500]);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarian = Mockery::mock(DashboardHarianSnapshotService::class);
        $dashboardHarian->shouldReceive('resolveAffectedSnapshotPeriodsForPh')
            ->once()
            ->with('2026-05-05')
            ->andReturn(['2026-05-06']);
        $dashboardHarian->shouldReceive('rebuild')
            ->once()
            ->with('2026-05-06', false)
            ->andReturn(['2026-05-06' => 1]);

        (new EnsureImportedSnapshotsFreshJob('lw325_ph', '2026-05-05', 'unit-test'))
            ->handle($builder, $dashboardHarian, $this->sourceSignatures);

        $newMetadata = $this->sourceSignatures->capture('lw325_ph', 'periode', '2026-05-05');
        $this->assertTrue($this->sourceSignatures->isFresh(
            'lw325_ph',
            'dashboard_harian_snapshots',
            '2026-05-06',
            $newMetadata
        ));
    }

    public function test_l1133_import_checks_affected_dashboard_harian_periods(): void
    {
        DB::table('l1133')->insert([
            'periode' => '2026-05-12',
            'outstanding' => 1000,
            'created_at' => '2026-05-12 10:00:00',
            'updated_at' => '2026-05-12 10:00:00',
        ]);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarian = Mockery::mock(DashboardHarianSnapshotService::class);
        $dashboardHarian->shouldReceive('resolveAffectedSnapshotPeriodsForLoanFallback')
            ->once()
            ->with('l1133', '2026-05-12')
            ->andReturn(['2026-05-12', '2026-05-13', '2026-05-16']);
        $dashboardHarian->shouldReceive('syncDuePeriods')
            ->once()
            ->with(['2026-05-12', '2026-05-13', '2026-05-16'])
            ->andReturn(['built' => 2, 'failed' => 0, 'missing' => [], 'stale' => ['2026-05-13'], 'checked' => 3]);

        Cache::put('report_cache_version:pinjaman', 3, now()->addHours(24));

        (new EnsureImportedSnapshotsFreshJob('l1133', '2026-05-12', 'unit-test'))
            ->handle($builder, $dashboardHarian, $this->sourceSignatures);

        $this->assertSame(4, (int) Cache::get('report_cache_version:pinjaman'));
    }

    public function test_dly_kap_import_checks_exact_dashboard_harian_period(): void
    {
        DB::table('dly_kap_resegmentasi')->insert([
            'periode' => '2026-05-16',
            'tl_rp' => 1000,
            'created_at' => '2026-05-16 10:00:00',
            'updated_at' => '2026-05-16 10:00:00',
        ]);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarian = Mockery::mock(DashboardHarianSnapshotService::class);
        $dashboardHarian->shouldReceive('resolveAffectedSnapshotPeriodsForLoanFallback')
            ->once()
            ->with('dly_kap_resegmentasi', '2026-05-16')
            ->andReturn(['2026-05-16']);
        $dashboardHarian->shouldReceive('syncDuePeriods')
            ->once()
            ->with(['2026-05-16'])
            ->andReturn(['built' => 1, 'failed' => 0, 'missing' => [], 'stale' => ['2026-05-16'], 'checked' => 1]);

        Cache::put('report_cache_version:pinjaman', 7, now()->addHours(24));

        (new EnsureImportedSnapshotsFreshJob('dly_kap_resegmentasi', '2026-05-16', 'unit-test'))
            ->handle($builder, $dashboardHarian, $this->sourceSignatures);

        $this->assertSame(8, (int) Cache::get('report_cache_version:pinjaman'));
    }

    private function createTables(): void
    {
        Schema::create('snapshot_source_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 100);
            $table->string('snapshot_table', 100);
            $table->string('period_key', 40);
            $table->string('source_signature', 64);
            $table->unsignedBigInteger('source_row_count')->default(0);
            $table->timestamp('source_max_updated_at')->nullable();
            $table->timestamp('built_at')->nullable();
            $table->text('context')->nullable();
            $table->timestamps();
            $table->unique(['source_table', 'snapshot_table', 'period_key'], 'uq_snapshot_source_signature_scope');
        });

        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('simpanan_multipn', function (Blueprint $table) {
            $table->id();
            $table->date('posisi');
            $table->string('kantor_cabang')->nullable();
            $table->decimal('saldo_idr', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('ssa_simpanan', function (Blueprint $table) {
            $table->id();
            $table->date('Month_Day_Year_of_Posisi');
            $table->decimal('saldo', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('ssa_pinjaman', function (Blueprint $table) {
            $table->id();
            $table->date('month_day_year_of_periode');
            $table->decimal('baki_debet', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('lw325_ph', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->decimal('pokok', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('dly_kap_resegmentasi', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->decimal('tl_rp', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('l1133', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->decimal('outstanding', 20, 2)->nullable();
            $table->timestamps();
        });

        foreach ([
            'dashboard_pinjaman_snapshots' => 'periode',
            'dashboard_pinjaman_chart_periodik_snapshots' => 'periode',
            'performance_rm_snapshots' => 'periode',
            'rasio_casa_debitur_snapshots' => 'loan_period',
            'dashboard_simpanan_snapshots' => 'snapshot_period',
            'rekening_dormant_snapshots' => 'posisi',
            'dashboard_harian_snapshots' => 'snapshot_period',
            'ssa_simpanan_snapshots' => 'periode',
        ] as $tableName => $periodColumn) {
            Schema::create($tableName, function (Blueprint $table) use ($periodColumn) {
                $table->id();
                $table->date($periodColumn)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('rasio_casa_debitur_snapshots', 'casa_period')) {
            Schema::table('rasio_casa_debitur_snapshots', function (Blueprint $table) {
                $table->date('casa_period')->nullable();
            });
        }

        Schema::table('dashboard_pinjaman_snapshots', function (Blueprint $table) {
            $table->string('account_number')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
        });
    }

    private function insertReadySimpananRows(string $period, int $saldo): void
    {
        foreach (['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'] as $branch) {
            DB::table('simpanan_multipn')->insert([
                'posisi' => $period,
                'kantor_cabang' => $branch,
                'saldo_idr' => $saldo,
                'created_at' => '2026-05-08 10:00:00',
                'updated_at' => '2026-05-08 10:00:00',
            ]);
        }
    }

    private function ssaSimpananRow(string $period, int $saldo): array
    {
        return [
            'Month_Day_Year_of_Posisi' => $period,
            'saldo' => $saldo,
            'created_at' => '2026-05-08 10:00:00',
            'updated_at' => '2026-05-08 10:00:00',
        ];
    }

    private function ssaPinjamanRow(string $period, int $bakiDebet): array
    {
        return [
            'month_day_year_of_periode' => $period,
            'baki_debet' => $bakiDebet,
            'created_at' => '2026-05-08 10:00:00',
            'updated_at' => '2026-05-08 10:00:00',
        ];
    }

    private function markFresh(string $sourceTable, string $snapshotTable, string $periodKey, ?array $metadata): void
    {
        $this->assertNotNull($metadata);

        $this->sourceSignatures->markBuilt($sourceTable, $snapshotTable, $periodKey, $metadata);
    }

    private function snapshotRow(string $periodColumn, string $period, string $updatedAt): array
    {
        return [
            $periodColumn => $period,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ];
    }
}
