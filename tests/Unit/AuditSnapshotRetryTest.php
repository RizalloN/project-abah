<?php

namespace Tests\Unit;

use App\Jobs\AuditAndHealSnapshotsJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\Worker;
use Mockery;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class AuditSnapshotRetryTest extends TestCase
{
    public function test_lock_deferrals_do_not_exhaust_three_attempts_before_deadline(): void
    {
        $audit = new AuditAndHealSnapshotsJob();
        $this->assertSame(3, $audit->maxExceptions);
        $this->assertGreaterThan(now()->addHours(3)->timestamp, $audit->retryUntil()->getTimestamp());
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('maxTries')->andReturn($audit->tries);
        $job->shouldReceive('retryUntil')->andReturn($audit->retryUntil()->getTimestamp());
        $job->shouldReceive('attempts')->andReturn(25);
        $job->shouldNotReceive('fail');
        $worker = (new ReflectionClass(Worker::class))->newInstanceWithoutConstructor();

        (new ReflectionMethod(Worker::class, 'markJobAsFailedIfAlreadyExceedsMaxAttempts'))
            ->invoke($worker, 'database', $job, 3);
    }

    public function test_expired_retry_window_still_fails_instead_of_looping_forever(): void
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('maxTries')->andReturn(3);
        $job->shouldReceive('retryUntil')->andReturn(now()->subMinute()->timestamp);
        $job->shouldReceive('resolveName')->andReturn(AuditAndHealSnapshotsJob::class);
        $job->shouldReceive('fail')->once()->with(Mockery::type(MaxAttemptsExceededException::class));
        $worker = (new ReflectionClass(Worker::class))->newInstanceWithoutConstructor();
        $this->expectException(MaxAttemptsExceededException::class);

        (new ReflectionMethod(Worker::class, 'markJobAsFailedIfAlreadyExceedsMaxAttempts'))
            ->invoke($worker, 'database', $job, 3);
    }
}
