<?php

namespace Tests\Feature;

use App\Support\ConsumerRmPositionHistoryStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PruneReportDailyHistoryCommandTest extends TestCase
{
    private array $auditFilesBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'daily_loan_dinamis',
            'lw325_ph',
            'lw321pn',
            'simpanan_multipn',
            'hourly_dpk',
            'dly_kap_resegmentasi',
            'dashboard_pinjaman_snapshots',
            'dashboard_pinjaman_chart_periodik_snapshots',
            'ssa_simpanan',
            'ssa_pinjaman',
            'gi405_recovery',
            'import_jobs',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable()->index();
        });
        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable()->index();
        });
        Schema::create('lw321pn', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable()->index();
        });
        Schema::create('simpanan_multipn', function (Blueprint $table): void {
            $table->id();
            $table->date('posisi')->nullable()->index();
        });
        Schema::create('hourly_dpk', function (Blueprint $table): void {
            $table->id();
            $table->date('posisi')->nullable()->index();
        });
        Schema::create('dly_kap_resegmentasi', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable()->index();
        });
        Schema::create('dashboard_pinjaman_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable()->index();
        });
        Schema::create('dashboard_pinjaman_chart_periodik_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable()->index();
        });
        Schema::create('ssa_simpanan', function (Blueprint $table): void {
            $table->id();
            $table->date('Month_Day_Year_of_Posisi')->nullable()->index();
        });
        Schema::create('ssa_pinjaman', function (Blueprint $table): void {
            $table->id();
            $table->date('month_day_year_of_periode')->nullable()->index();
        });
        Schema::create('gi405_recovery', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable()->index();
        });
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('status');
        });

        $this->seedPeriods('daily_loan_dinamis', 'periode');
        $this->seedPeriods('lw325_ph', 'periode');
        $this->seedPeriods('lw321pn', 'periode');
        $this->seedPeriods('simpanan_multipn', 'posisi');
        $this->seedPeriods('hourly_dpk', 'posisi');
        $this->seedPeriods('dly_kap_resegmentasi', 'periode');
        $this->seedPeriods('dashboard_pinjaman_snapshots', 'periode');
        $this->seedPeriods('dashboard_pinjaman_chart_periodik_snapshots', 'periode');
        $this->seedPeriods('ssa_simpanan', 'Month_Day_Year_of_Posisi');
        $this->seedPeriods('ssa_pinjaman', 'month_day_year_of_periode');
        $this->seedPeriods('gi405_recovery', 'periode');
        $this->auditFilesBefore = File::glob(storage_path('logs/report-retention-cleanup-*.json'));
    }

    protected function tearDown(): void
    {
        foreach (File::glob(storage_path('logs/report-retention-cleanup-*.json')) as $file) {
            if (! in_array($file, $this->auditFilesBefore, true)) {
                File::delete($file);
            }
        }

        foreach ([
            'daily_loan_dinamis',
            'lw325_ph',
            'lw321pn',
            'simpanan_multipn',
            'hourly_dpk',
            'dly_kap_resegmentasi',
            'dashboard_pinjaman_snapshots',
            'dashboard_pinjaman_chart_periodik_snapshots',
            'ssa_simpanan',
            'ssa_pinjaman',
            'gi405_recovery',
            'import_jobs',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_dry_run_does_not_delete_rows(): void
    {
        $this->artisan('reports:prune-daily-history', [
            '--keep-full-month' => ['2026-06', '2026-07'],
            '--chunk' => 1_000,
        ])
            ->expectsOutputToContain('DRY-RUN selesai')
            ->assertExitCode(0);

        $this->assertSame(8, DB::table('daily_loan_dinamis')->count());
        $this->assertSame(8, DB::table('lw325_ph')->count());
        $this->assertSame(8, DB::table('lw321pn')->count());
        $this->assertSame(8, DB::table('simpanan_multipn')->count());
        $this->assertSame(8, DB::table('hourly_dpk')->count());
        $this->assertSame(8, DB::table('dly_kap_resegmentasi')->count());
        $this->assertSame(8, DB::table('dashboard_pinjaman_snapshots')->count());
        $this->assertSame(8, DB::table('dashboard_pinjaman_chart_periodik_snapshots')->count());
    }

    public function test_execute_keeps_month_end_and_preserves_protected_months(): void
    {
        $archive = new class {
            /** @var list<string> */
            public array $capturedPeriods = [];

            /** @return array<string, mixed> */
            public function capturePeriod(string $period): array
            {
                $this->capturedPeriods[] = $period;

                return [
                    'source_rows' => 1,
                    'archived_rows' => 1,
                    'verified' => true,
                    'skipped' => false,
                ];
            }
        };
        $this->app->instance(ConsumerRmPositionHistoryStore::class, $archive);

        $this->artisan('reports:prune-daily-history', [
            '--execute' => true,
            '--keep-full-month' => ['2026-06', '2026-07'],
            '--chunk' => 1_000,
            '--sleep-ms' => 0,
        ])
            ->expectsOutputToContain('Pembersihan selesai dan validasi retensi lulus.')
            ->assertExitCode(0);

        $this->assertRetainedPeriods('daily_loan_dinamis', 'periode');
        $this->assertRetainedPeriods('lw325_ph', 'periode');
        $this->assertRetainedPeriods('lw321pn', 'periode');
        $this->assertRetainedPeriods('simpanan_multipn', 'posisi');
        $this->assertRetainedPeriods('hourly_dpk', 'posisi');
        $this->assertRetainedPeriods('dly_kap_resegmentasi', 'periode');
        $this->assertRetainedPeriods('dashboard_pinjaman_snapshots', 'periode');
        $this->assertRetainedPeriods('dashboard_pinjaman_chart_periodik_snapshots', 'periode');
        $this->assertSame(8, DB::table('ssa_simpanan')->count());
        $this->assertSame(8, DB::table('ssa_pinjaman')->count());
        $this->assertSame(8, DB::table('gi405_recovery')->count());
        $this->assertSame(['2026-04-01', '2026-05-01'], $archive->capturedPeriods);
    }

    public function test_execute_stops_before_daily_loan_delete_when_archive_is_not_verified(): void
    {
        $archive = new class {
            /** @return array<string, mixed> */
            public function capturePeriod(string $period): array
            {
                return [
                    'source_rows' => 1,
                    'archived_rows' => 0,
                    'verified' => false,
                    'skipped' => true,
                    'reason' => 'archive_table_unavailable',
                ];
            }
        };
        $this->app->instance(ConsumerRmPositionHistoryStore::class, $archive);

        $this->artisan('reports:prune-daily-history', [
            '--execute' => true,
            '--keep-full-month' => ['2026-06', '2026-07'],
            '--chunk' => 1_000,
            '--sleep-ms' => 0,
        ])
            ->expectsOutputToContain('Arsip posisi RM Konsumer periode 2026-04-01 belum terverifikasi')
            ->assertExitCode(1);

        $this->assertSame(8, DB::table('daily_loan_dinamis')->count());
        $this->assertSame(8, DB::table('lw325_ph')->count());
    }

    public function test_execute_stops_when_an_import_job_is_active(): void
    {
        DB::table('import_jobs')->insert(['status' => 'processing']);

        $this->artisan('reports:prune-daily-history', [
            '--execute' => true,
            '--keep-full-month' => ['2026-06', '2026-07'],
            '--sleep-ms' => 0,
        ])
            ->expectsOutputToContain('Masih ada job import aktif')
            ->assertExitCode(1);

        $this->assertSame(8, DB::table('daily_loan_dinamis')->count());
        $this->assertSame(8, DB::table('lw325_ph')->count());
        $this->assertSame(8, DB::table('lw321pn')->count());
        $this->assertSame(8, DB::table('simpanan_multipn')->count());
        $this->assertSame(8, DB::table('hourly_dpk')->count());
        $this->assertSame(8, DB::table('dly_kap_resegmentasi')->count());
        $this->assertSame(8, DB::table('dashboard_pinjaman_snapshots')->count());
        $this->assertSame(8, DB::table('dashboard_pinjaman_chart_periodik_snapshots')->count());
    }

    private function seedPeriods(string $table, string $periodColumn): void
    {
        DB::table($table)->insert(array_map(
            static fn (string $date): array => [$periodColumn => $date],
            [
                '2026-04-01',
                '2026-04-30',
                '2026-05-01',
                '2026-05-31',
                '2026-06-01',
                '2026-06-30',
                '2026-07-01',
                '2026-07-31',
            ]
        ));
    }

    private function assertRetainedPeriods(string $table, string $periodColumn): void
    {
        $periods = DB::table($table)->orderBy($periodColumn)->pluck($periodColumn)->all();

        $this->assertSame([
            '2026-04-30',
            '2026-05-31',
            '2026-06-01',
            '2026-06-30',
            '2026-07-01',
            '2026-07-31',
        ], $periods);
    }
}
