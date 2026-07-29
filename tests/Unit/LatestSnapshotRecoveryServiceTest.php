<?php

namespace Tests\Unit;

use App\Jobs\EnsureImportedSnapshotsFreshJob;
use App\Support\LatestSnapshotRecoveryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LatestSnapshotRecoveryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'daily_loan_dinamis',
            'simpanan_multipn',
            'ssa_simpanan',
            'ssa_pinjaman',
            'hourly_dpk',
            'lw325_ph',
            'gi405_recovery',
            'dly_kap_resegmentasi',
            'l1133',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
        });

        Schema::create('simpanan_multipn', function (Blueprint $table): void {
            $table->id();
            $table->date('posisi')->nullable();
        });
    }

    public function test_it_queues_latest_period_per_available_snapshot_source(): void
    {
        \DB::table('daily_loan_dinamis')->insert(['periode' => '2026-07-18']);
        \DB::table('simpanan_multipn')->insert(['posisi' => '2026-07-19']);
        Queue::fake();

        $result = app(LatestSnapshotRecoveryService::class)->queueLatestChecks('test:manual-snapshot-check');

        $this->assertFalse($result['duplicate_request']);
        $this->assertSame([
            ['table' => 'daily_loan_dinamis', 'period' => '2026-07-18'],
            ['table' => 'simpanan_multipn', 'period' => '2026-07-19'],
        ], $result['queued']);
        Queue::assertPushed(EnsureImportedSnapshotsFreshJob::class, 2);
    }
}
