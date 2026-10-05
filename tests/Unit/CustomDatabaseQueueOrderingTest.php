<?php

namespace Tests\Unit;

use App\Queue\CustomDatabaseQueue;
use Illuminate\Database\SQLiteConnection;
use PDO;
use Tests\TestCase;

class CustomDatabaseQueueOrderingTest extends TestCase
{
    public function test_reused_low_ids_do_not_overtake_older_jobs(): void
    {
        [$queue, $db] = $this->queue();
        $this->insert($db, 90, time() - 3600);
        $this->insert($db, 1, time() - 10);

        $job = $queue->pop('reports-low');

        $this->assertSame(90, $job->getJobId());
        $this->assertSame(1, $job->attempts());
        $this->assertNotNull($db->table('jobs')->where('id', 90)->value('reserved_at'));
        $this->assertNull($db->table('jobs')->where('id', 1)->value('reserved_at'));
    }

    public function test_delayed_reserved_and_other_queue_jobs_are_not_taken(): void
    {
        [$queue, $db] = $this->queue();
        $this->insert($db, 1, time() - 4000, ['available_at' => time() + 600]);
        $this->insert($db, 2, time() - 3000, ['reserved_at' => time()]);
        $this->insert($db, 3, time() - 2000, ['queue' => 'imports-high']);
        $this->insert($db, 99, time() - 1000);

        $this->assertSame(99, $queue->pop('reports-low')->getJobId());
        $this->assertNull($queue->pop('reports-low'));
    }

    public function test_expired_reservation_remains_recoverable_in_arrival_order(): void
    {
        [$queue, $db] = $this->queue();
        $this->insert($db, 90, time() - 4000, ['reserved_at' => time() - 301, 'attempts' => 1]);
        $this->insert($db, 1, time() - 10);

        $job = $queue->pop('reports-low');

        $this->assertSame(90, $job->getJobId());
        $this->assertSame(2, $job->attempts());
    }

    public function test_equal_arrival_times_have_a_stable_id_tiebreaker(): void
    {
        [$queue, $db] = $this->queue();
        $created = time() - 100;
        $this->insert($db, 90, $created);
        $this->insert($db, 3, $created);

        $this->assertSame(3, $queue->pop('reports-low')->getJobId());
        $this->assertSame(90, $queue->pop('reports-low')->getJobId());
    }

    private function queue(): array
    {
        $db = new SQLiteConnection(new PDO('sqlite::memory:'));
        $db->statement('CREATE TABLE jobs (id INTEGER PRIMARY KEY, queue TEXT, payload TEXT, attempts INTEGER, reserved_at INTEGER NULL, available_at INTEGER, created_at INTEGER)');
        $queue = new CustomDatabaseQueue($db, 'jobs', 'reports-low', 300);
        $queue->setContainer($this->app);
        $queue->setConnectionName('ordering-test');

        return [$queue, $db];
    }

    private function insert(SQLiteConnection $db, int $id, int $created, array $extra = []): void
    {
        $db->table('jobs')->insert(array_replace([
            'id' => $id, 'queue' => 'reports-low',
            'payload' => json_encode(['job' => 'ordering-test', 'data' => []]),
            'attempts' => 0, 'reserved_at' => null,
            'available_at' => time() - 1, 'created_at' => $created,
        ], $extra));
    }
}
