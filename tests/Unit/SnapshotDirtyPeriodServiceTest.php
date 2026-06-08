<?php

namespace Tests\Unit;

use App\Support\SnapshotDirtyPeriodService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SnapshotDirtyPeriodServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('failed_snapshot_dirty_periods');
        Schema::dropIfExists('snapshot_dirty_periods');
        Schema::dropIfExists('report_sync_audits');

        Schema::create('snapshot_dirty_periods', function (Blueprint $table): void {
            $table->string('source_table', 64);
            $table->string('period_key', 40);
            $table->string('shard_type', 32)->default('period');
            $table->string('shard_key', 100)->default('*');
            $table->dateTime('dirty_since', 6);
            $table->unsignedBigInteger('dirty_row_count')->default(0);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('last_attempted_at', 6)->nullable();
            $table->dateTime('claimed_at', 6)->nullable();
            $table->dateTime('dirty_since_at_claim', 6)->nullable();
            $table->string('claim_token', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps(6);
            $table->primary(['source_table', 'period_key', 'shard_type', 'shard_key'], 'pk_sdp_test');
        });

        Schema::create('failed_snapshot_dirty_periods', function (Blueprint $table): void {
            $table->string('source_table', 64);
            $table->string('period_key', 40);
            $table->string('shard_type', 32)->default('period');
            $table->string('shard_key', 100)->default('*');
            $table->dateTime('dirty_since', 6)->nullable();
            $table->unsignedBigInteger('dirty_row_count')->default(0);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('failed_at', 6);
            $table->timestamps(6);
            $table->primary(['source_table', 'period_key', 'shard_type', 'shard_key'], 'pk_failed_sdp_test');
        });

        Schema::create('report_sync_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('import_job_id')->nullable();
            $table->string('source')->nullable();
            $table->string('table_name');
            $table->date('period_hint')->nullable();
            $table->string('action');
            $table->string('status');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('affected_rows')->nullable();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function test_mark_coalesces_dirty_period_and_claim_clear_is_token_scoped(): void
    {
        $service = new SnapshotDirtyPeriodService;

        $service->mark('daily_loan_dinamis', '2026-05-12');
        $service->mark('daily_loan_dinamis', '2026-05-12');

        $claims = $service->claimDue(10);

        $this->assertCount(1, $claims);
        $this->assertSame('daily_loan_dinamis', $claims[0]['source_table']);
        $this->assertSame('2026-05-12', $claims[0]['period_key']);
        $this->assertTrue($service->clearClaim($claims[0]));
        $this->assertSame(0, $service->pendingCount());
        $this->assertDatabaseHas('report_sync_audits', [
            'table_name' => 'daily_loan_dinamis',
            'period_hint' => '2026-05-12',
            'action' => 'snapshot_dirty_clear',
            'status' => 'success',
        ]);
    }

    public function test_release_claim_moves_to_failed_after_max_attempts(): void
    {
        $service = new SnapshotDirtyPeriodService;
        $service->mark('hourly_dpk', '2026-05-12');

        for ($i = 0; $i < SnapshotDirtyPeriodService::MAX_ATTEMPTS; $i++) {
            $claims = $service->claimDue(1);
            $this->assertCount(1, $claims);
            $service->releaseClaim($claims[0], 'boom');
        }

        $this->assertSame(0, $service->pendingCount());
        $this->assertDatabaseHas('failed_snapshot_dirty_periods', [
            'source_table' => 'hourly_dpk',
            'period_key' => '2026-05-12',
        ]);
        $this->assertDatabaseHas('report_sync_audits', [
            'table_name' => 'hourly_dpk',
            'period_hint' => '2026-05-12',
            'action' => 'snapshot_dirty_release',
            'status' => 'retry',
        ]);
        $this->assertDatabaseHas('report_sync_audits', [
            'table_name' => 'hourly_dpk',
            'period_hint' => '2026-05-12',
            'action' => 'snapshot_dirty_dead_letter',
            'status' => 'failed',
        ]);
    }

    public function test_claimable_count_excludes_exhausted_dirty_rows(): void
    {
        $service = new SnapshotDirtyPeriodService;
        $service->mark('simpanan_multipn', '2026-05-16');

        \DB::table('snapshot_dirty_periods')
            ->where('source_table', 'simpanan_multipn')
            ->where('period_key', '2026-05-16')
            ->update(['attempts' => SnapshotDirtyPeriodService::MAX_ATTEMPTS]);

        $this->assertSame(1, $service->pendingCount('simpanan_multipn'));
        $this->assertSame(0, $service->claimableCount('simpanan_multipn', '2026-05-16'));
    }

    public function test_claim_due_recovers_stale_claim_when_worker_disappears(): void
    {
        $service = new SnapshotDirtyPeriodService;
        $service->mark('lw325_ph', '2026-05-13');

        $claims = $service->claimDue(1);
        $this->assertCount(1, $claims);

        \DB::table('snapshot_dirty_periods')
            ->where('source_table', 'lw325_ph')
            ->where('period_key', '2026-05-13')
            ->update([
                'claimed_at' => now()->subMinutes(30),
                'claim_token' => 'lost-worker',
                'dirty_since_at_claim' => now()->subMinutes(30),
            ]);

        $reclaimed = $service->claimDue(1, 'lw325_ph', '2026-05-13');

        $this->assertCount(1, $reclaimed);
        $this->assertSame('lw325_ph', $reclaimed[0]['source_table']);
        $this->assertSame('2026-05-13', $reclaimed[0]['period_key']);
        $this->assertNotSame('lost-worker', $reclaimed[0]['claim_token']);
    }

    public function test_mark_resets_attempts_and_clears_failed_marker_for_fresh_source_change(): void
    {
        $service = new SnapshotDirtyPeriodService;
        $service->mark('ssa_pinjaman', '2026-05-13');

        $claims = $service->claimDue(1);
        $this->assertCount(1, $claims);
        $service->releaseClaim($claims[0], 'temporary failure');

        \DB::table('failed_snapshot_dirty_periods')->insert([
            'source_table' => 'ssa_pinjaman',
            'period_key' => '2026-05-13',
            'shard_type' => 'period',
            'shard_key' => '*',
            'dirty_since' => now(),
            'dirty_row_count' => 1,
            'attempts' => SnapshotDirtyPeriodService::MAX_ATTEMPTS,
            'last_error' => 'old failure',
            'failed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service->mark('ssa_pinjaman', '2026-05-13');
        $reclaimed = $service->claimDue(1, 'ssa_pinjaman', '2026-05-13');

        $this->assertCount(1, $reclaimed);
        $this->assertSame(1, $reclaimed[0]['attempts']);
        $this->assertDatabaseMissing('failed_snapshot_dirty_periods', [
            'source_table' => 'ssa_pinjaman',
            'period_key' => '2026-05-13',
        ]);
    }
}
