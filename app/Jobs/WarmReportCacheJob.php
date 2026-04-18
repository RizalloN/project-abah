<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class WarmReportCacheJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    public function handle(): void
    {
        try {
            Artisan::call('report:warm-cache');
        } catch (Throwable $e) {
            Log::warning('Gagal menjalankan queue cache warming report: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Dispatch the job only if and only if there's no identical job pending.
     */
    public static function dispatchUnique(): void
    {
        try {
            $queue = (string) config('queue.report_queue', 'default');
            $jobName = self::class;

            $exists = \Illuminate\Support\Facades\DB::table('jobs')
                ->where('queue', $queue)
                ->where('payload', 'like', '%' . str_replace('\\', '\\\\', $jobName) . '%')
                ->exists();

            if (!$exists) {
                self::dispatch()->onQueue($queue);
            }
        } catch (\Throwable $e) {
            // Fallback to normal dispatch if anything fails
            self::dispatch()->onQueue((string) config('queue.report_queue', 'default'));
        }
    }
}
