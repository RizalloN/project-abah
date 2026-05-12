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
        $service = new SnapshotDirtyPeriodService();

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
        $service = new SnapshotDirtyPeriodService();
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
}
