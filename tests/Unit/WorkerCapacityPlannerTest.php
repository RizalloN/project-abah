<?php

use App\Services\Import\QueueSupervisorProcessRunner;
use App\Services\Import\WorkerCapacityPlanner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

function plannerPool(int $capacity, int $priority, int $ready = 0, int $reserved = 0, int $active = 0, bool $critical = false): array
{
    return compact('capacity', 'priority', 'ready', 'reserved', 'active', 'critical');
}

beforeEach(function () {
    $this->planner = new WorkerCapacityPlanner(Mockery::mock(QueueSupervisorProcessRunner::class));
});

it('serves every waiting pool once before giving any pool a second worker', function () {
    $targets = $this->planner->allocate([
        'imports-high' => plannerPool(3, 10, ready: 5, critical: true),
        'snapshots' => plannerPool(3, 40, ready: 40),
        'reports-low' => plannerPool(1, 70, ready: 2),
    ], 4);

    expect($targets)->toBe(['imports-high' => 2, 'snapshots' => 1, 'reports-low' => 1]);
});

it('fills spare budget by priority up to each pool capacity', function () {
    $targets = $this->planner->allocate([
        'imports-high' => plannerPool(3, 10, ready: 5, critical: true),
        'snapshots' => plannerPool(3, 40, ready: 40),
    ], 12);

    expect($targets)->toBe(['imports-high' => 3, 'snapshots' => 3]);
});

it('always gives a critical pool its first worker even when the budget is exhausted', function () {
    $targets = $this->planner->allocate([
        'imports-high' => plannerPool(3, 10, ready: 1, critical: true),
        'snapshots' => plannerPool(3, 40, ready: 10, reserved: 3, active: 3),
        'shadow-backfill' => plannerPool(2, 50, ready: 4),
    ], 3);

    expect($targets['imports-high'])->toBe(1)
        ->and($targets['snapshots'])->toBe(3)
        ->and($targets['shadow-backfill'])->toBe(0);
});

it('never plans below the workers that are already running', function () {
    $targets = $this->planner->allocate([
        'snapshots' => plannerPool(3, 40, ready: 0, reserved: 3, active: 3),
        'background' => plannerPool(2, 30, ready: 2, critical: true),
    ], 2);

    expect($targets['snapshots'])->toBe(3)
        ->and($targets['background'])->toBe(1);
});

it('leaves idle pools at their configured capacity because they launch nothing', function () {
    $targets = $this->planner->allocate([
        'imports-high' => plannerPool(3, 10),
        'reports-low' => plannerPool(1, 70),
    ], 0);

    expect($targets)->toBe(['imports-high' => 3, 'reports-low' => 1]);
});

it('falls back to static capacity when disabled', function () {
    config()->set('queue.worker_planner.enabled', false);

    expect($this->planner->targets([
        'imports-high' => ['queues' => 'imports-high', 'workers' => 3],
        'snapshots' => ['queues' => 'snapshots-parallel', 'workers' => 2],
    ]))->toBe(['imports-high' => 3, 'snapshots' => 2]);
});

it('shrinks the global cap when free memory could not absorb another worker', function () {
    config()->set('cache.default', 'array');
    config()->set('queue.worker_planner.enabled', true);
    config()->set('queue.worker_planner.max_total_workers', 12);
    config()->set('queue.worker_planner.memory_reserve_mb', 2048);
    config()->set('queue.worker_planner.worker_memory_estimate_mb', 512);
    config()->set('queue.worker_pools', [
        'imports-high' => ['queues' => 'imports-high', 'priority' => 10, 'critical' => true, 'workers' => 3],
        'snapshots' => ['queues' => 'snapshots-parallel', 'priority' => 40, 'workers' => 3],
    ]);
    Schema::dropIfExists('jobs');
    Schema::create('jobs', function ($table) {
        $table->id();
        $table->string('queue');
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts')->default(0);
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    foreach ([['imports-high', 3], ['snapshots-parallel', 9]] as [$queue, $count]) {
        for ($i = 0; $i < $count; $i++) {
            DB::table('jobs')->insert(['queue' => $queue, 'payload' => '{}', 'available_at' => time() - 5, 'created_at' => time() - 5]);
        }
    }
    // Free RAM leaves room for exactly two more workers above the reserve.
    Cache::put('queue:worker-planner:free-memory-mb', 2048 + 512 * 2, 60);

    $plan = $this->planner->plan([
        'imports-high' => ['queues' => 'imports-high', 'workers' => 3],
        'snapshots' => ['queues' => 'snapshots-parallel', 'workers' => 3],
    ]);

    expect($plan['global_cap'])->toBe(2)
        ->and($plan['targets'])->toBe(['imports-high' => 1, 'snapshots' => 1])
        ->and($plan['decisions']['snapshots']['throttled'])->toBeTrue()
        ->and($plan['decisions']['snapshots']['limited_by'])->toBe('memory');

    Schema::dropIfExists('jobs');
});
