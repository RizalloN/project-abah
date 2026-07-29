<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class SchedulerHeartbeatCommand extends Command
{
    private const CACHE_KEY = 'system:scheduler:last_heartbeat';

    protected $signature = 'scheduler:heartbeat
        {--check : Only check whether the scheduler heartbeat is fresh}
        {--max-age=120 : Maximum heartbeat age in seconds for --check}';

    protected $description = 'Write or verify the scheduler heartbeat';

    public function handle(): int
    {
        if ((bool) $this->option('check')) {
            $heartbeat = $this->readHeartbeat();
            $maxAge = max(30, (int) $this->option('max-age'));
            $age = $heartbeat > 0 ? time() - $heartbeat : null;

            if ($age === null || $age > $maxAge) {
                $this->error($age === null
                    ? 'Scheduler heartbeat belum tersedia.'
                    : "Scheduler heartbeat stale ({$age} detik)."
                );

                return self::FAILURE;
            }

            $this->info("Scheduler heartbeat sehat ({$age} detik lalu).");

            return self::SUCCESS;
        }

        $now = time();
        Cache::put(self::CACHE_KEY, $now, now()->addMinutes(10));

        $path = storage_path('app/health/scheduler-heartbeat.json');
        File::ensureDirectoryExists(dirname($path));
        File::replace($path, json_encode([
            'timestamp' => $now,
            'written_at' => now()->toIso8601String(),
            'pid' => getmypid(),
        ], JSON_UNESCAPED_SLASHES));

        $this->line(now()->toIso8601String());

        return self::SUCCESS;
    }

    private function readHeartbeat(): int
    {
        $cached = (int) Cache::get(self::CACHE_KEY, 0);
        if ($cached > 0) {
            return $cached;
        }

        $path = storage_path('app/health/scheduler-heartbeat.json');
        if (! File::isFile($path)) {
            return 0;
        }

        $payload = json_decode((string) File::get($path), true);

        return is_array($payload) ? (int) ($payload['timestamp'] ?? 0) : 0;
    }
}
