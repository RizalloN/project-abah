<?php

namespace Tests\Feature;

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

        foreach (['daily_loan_dinamis', 'lw325_ph', 'simpanan_multipn', 'import_jobs'] as $table) {
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
        Schema::create('simpanan_multipn', function (Blueprint $table): void {
            $table->id();
            $table->date('posisi')->nullable()->index();
        });
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('status');
        });

        $this->seedPeriods('daily_loan_dinamis', 'periode');
        $this->seedPeriods('lw325_ph', 'periode');
        $this->seedPeriods('simpanan_multipn', 'posisi');
        $this->auditFilesBefore = File::glob(storage_path('logs/report-retention-cleanup-*.json'));
    }

    protected function tearDown(): void
    {
        foreach (File::glob(storage_path('logs/report-retention-cleanup-*.json')) as $file) {
            if (! in_array($file, $this->auditFilesBefore, true)) {
                File::delete($file);
            }
        }

        foreach (['daily_loan_dinamis', 'lw325_ph', 'simpanan_multipn', 'import_jobs'] as $table) {
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
        $this->assertSame(8, DB::table('simpanan_multipn')->count());
    }

    public function test_execute_keeps_month_end_and_preserves_protected_months(): void
    {
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
        $this->assertRetainedPeriods('simpanan_multipn', 'posisi');
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
        $this->assertSame(8, DB::table('simpanan_multipn')->count());
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
