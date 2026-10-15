<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Services\DailyDatabaseBackupService;
use App\Services\ManagedReportBackupRecoveryService;
use App\Support\DashboardHarianSnapshotService;
use App\Support\ManagedReportManagementService;
use App\Support\PeriodSnapshotSyncService;
use App\Support\ReportCacheVersion;
use App\Support\ReportSnapshotBuilder;
use App\Support\SsaSimpananSnapshotBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class PeriodSnapshotSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();
        Cache::flush();
    }

    public function test_period_accepts_display_format_and_rejects_impossible_dates(): void
    {
        $this->assertSame('2026-10-14', PeriodSnapshotSyncService::normalizePeriod('14-10-2026'));
        $this->assertSame('2026-10-14', PeriodSnapshotSyncService::normalizePeriod('2026-10-14'));
        $this->assertSame('2026-10-14', PeriodSnapshotSyncService::normalizePeriod('14/10/2026'));
        $this->assertNull(PeriodSnapshotSyncService::normalizePeriod('31-02-2026'));
        $this->assertNull(PeriodSnapshotSyncService::normalizePeriod('1-10-2026'));
        $this->assertNull(PeriodSnapshotSyncService::normalizePeriod('20261014'));
    }

    public function test_sync_rebuilds_only_snapshots_whose_source_has_the_exact_period(): void
    {
        foreach (['daily_loan_dinamis' => 'periode', 'simpanan_multipn' => 'posisi', 'performance_pis_per_produk' => 'posisi', 'ssa_simpanan' => 'Month_Day_Year_of_Posisi', 'jumlah_merchant_qris_detail' => 'POSISI'] as $table => $column) {
            Schema::create($table, fn (Blueprint $t) => $t->date($column)->nullable());
        }
        Schema::create('nama_report', function (Blueprint $t) {
            $t->integer('id_report');
            $t->string('nama_report');
            $t->string('table_name');
            $t->boolean('active');
        });
        DB::table('daily_loan_dinamis')->insert(['periode' => '2026-10-14']);
        DB::table('ssa_simpanan')->insert(['Month_Day_Year_of_Posisi' => '2026-10-14']);
        // Older data only: must be skipped, never treated as a deleted source.
        DB::table('simpanan_multipn')->insert(['posisi' => '2026-10-08']);
        DB::table('jumlah_merchant_qris_detail')->insert(['POSISI' => '2026-10-14']);
        DB::table('nama_report')->insert([
            ['id_report' => 8, 'nama_report' => 'Daily Loan', 'table_name' => 'daily_loan_dinamis', 'active' => 1],
            ['id_report' => 9, 'nama_report' => 'Simpanan', 'table_name' => 'simpanan_multipn', 'active' => 1],
            ['id_report' => 34, 'nama_report' => 'QRIS', 'table_name' => 'jumlah_merchant_qris_detail', 'active' => 1],
        ]);

        $builder = $this->createMock(ReportSnapshotBuilder::class);
        $builder->expects($this->once())->method('rebuildDashboard')->with('2026-10-14', true)->willReturn(['2026-10-14' => 10]);
        $builder->expects($this->once())->method('rebuildChartPeriodik')->with('2026-10-14', true)->willReturn(['2026-10-14' => 2]);
        $builder->expects($this->once())->method('rebuildPerformanceRm')->with('2026-10-14', true)->willReturn(['2026-10-14' => 3]);
        $builder->expects($this->once())->method('rebuildRasioCasa')->with('2026-10-14', true)->willReturn(['2026-10-14' => 4]);
        $builder->expects($this->never())->method('rebuildDashboardSimpanan');
        $builder->expects($this->never())->method('rebuildRekeningDormant');
        $builder->expects($this->never())->method('rebuildPerformanceNewPayroll');
        $harian = $this->createMock(DashboardHarianSnapshotService::class);
        $harian->method('describeRebuildPlan')->with('2026-10-14')->willReturn(['periods' => ['2026-10-14']]);
        $harian->expects($this->once())->method('rebuild')->with('2026-10-14', true)->willReturn(['2026-10-14' => 115]);
        $ssa = $this->createMock(SsaSimpananSnapshotBuilder::class);
        $ssa->expects($this->once())->method('rebuild')->with('2026-10-14', true)->willReturn(['success' => true, 'records_inserted' => 7]);
        $management = $this->createMock(ManagedReportManagementService::class);
        $management->method('resolveManagementScopeColumns')->willReturnCallback(fn (string $table): array => [match ($table) {
            'daily_loan_dinamis' => 'periode',
            'simpanan_multipn' => 'posisi',
            default => 'POSISI',
        }, null]);
        $versionBefore = ReportCacheVersion::get('global');

        $summary = (new PeriodSnapshotSyncService($builder, $harian, $ssa, $management))->run('2026-10-14');

        $this->assertSame([], $summary['errors']);
        $this->assertSame(6, $summary['rebuilt']);
        $this->assertSame(115, $summary['results']['dashboard_harian']);
        $this->assertSame(['daily_loan_dinamis', 'jumlah_merchant_qris_detail'], array_column($summary['plan']['reports'], 'table'));
        $this->assertGreaterThan($versionBefore, ReportCacheVersion::get('global'));
    }

    public function test_composite_harian_is_skipped_when_it_would_fall_back_to_another_date(): void
    {
        $harian = $this->createMock(DashboardHarianSnapshotService::class);
        $harian->method('describeRebuildPlan')->willReturn(['periods' => ['2026-10-13']]);
        $harian->expects($this->never())->method('rebuild');

        $service = new PeriodSnapshotSyncService(
            $this->createMock(ReportSnapshotBuilder::class),
            $harian,
            $this->createMock(SsaSimpananSnapshotBuilder::class),
            $this->createMock(ManagedReportManagementService::class)
        );
        $summary = $service->run('2026-10-14');

        $this->assertSame(0, $summary['rebuilt']);
        $this->assertFalse(collect($summary['plan']['steps'])->contains('available', true));
    }

    public function test_controller_queues_display_date_and_rejects_invalid_one(): void
    {
        Queue::fake();
        $controller = app(ImportIndexController::class);

        $response = $controller->startForceSyncSnapshots(Request::create('/', 'POST', ['period' => '14-10-2026']));
        $payload = $response->getData(true);
        $this->assertSame('queued', $payload['status']);
        $this->assertSame('2026-10-14', $payload['period']);
        $this->assertSame('14-10-2026', Cache::get(PeriodSnapshotSyncService::STATE_PREFIX . $payload['sync_id'])['period_label']);

        $invalid = $controller->startForceSyncSnapshots(Request::create('/', 'POST', ['period' => '2026-14-10']));
        $this->assertSame(422, $invalid->getStatusCode());
    }

    public function test_recovery_assembles_table_dump_from_verified_daily_backup(): void
    {
        Schema::create('ssa_pinjaman', function (Blueprint $t) {
            $t->bigInteger('id');
            $t->string('nama_cabang')->nullable();
        });
        $directory = storage_path('framework/testing/daily-backup-' . uniqid());
        File::ensureDirectoryExists($directory . '/data');
        file_put_contents($directory . '/schema.sql.gz', gzencode(
            "CREATE TABLE `other` (\n  `x` int\n);\nCREATE TABLE `ssa_pinjaman` (\n  `id` bigint(20) unsigned NOT NULL,\n  `nama_cabang` varchar(150) DEFAULT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n"
        ));
        file_put_contents($directory . '/data/ssa_pinjaman.sql.gz', gzencode("INSERT INTO `ssa_pinjaman` VALUES (1,'KC Madiun');\n"));
        $backup = ['directory' => $directory, 'manifest' => ['parts' => [
            ['kind' => 'schema', 'file' => 'schema.sql.gz', 'table' => null],
            ['kind' => 'data', 'file' => 'data/ssa_pinjaman.sql.gz', 'table' => 'ssa_pinjaman'],
        ]]];
        $daily = $this->createMock(DailyDatabaseBackupService::class);
        $daily->method('resolveChunkedBackup')->with('backup project-abah 15102026')->willReturn($backup);
        $daily->expects($this->exactly(2))->method('verifyChunkedParts')->with($backup, ['ssa_pinjaman']);
        $this->app->instance(DailyDatabaseBackupService::class, $daily);

        $service = app(ManagedReportBackupRecoveryService::class);
        $assemble = new ReflectionMethod($service, 'assembleDailyBackupTableDump');
        try {
            [$path, $name] = $assemble->invoke($service, 'backup project-abah 15102026', 'ssa_pinjaman', null);
            $reader = gzopen($path, 'rb');
            $sql = '';
            while (($line = gzgets($reader)) !== false) {
                $sql .= $line;
            }
            gzclose($reader);

            $this->assertSame(basename($directory) . '/ssa_pinjaman', $name);
            $this->assertStringContainsString("DROP TABLE IF EXISTS `ssa_pinjaman`;\nCREATE TABLE `ssa_pinjaman` (", $sql);
            $this->assertStringContainsString("INSERT INTO `ssa_pinjaman` VALUES (1,'KC Madiun');", $sql);
            $this->assertStringNotContainsString('`other`', $sql);
            @unlink($path);

            Schema::table('ssa_pinjaman', fn (Blueprint $t) => $t->string('segmen_baru')->nullable());
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('segmen_baru');
            $assemble->invoke($service, 'backup project-abah 15102026', 'ssa_pinjaman', null);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
