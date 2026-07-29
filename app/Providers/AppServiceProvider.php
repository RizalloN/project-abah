<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rules\Password;
use App\Services\Import\ActiveImportJobCounter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerCustomQueueExtensions();

        // Bind optimized services for Phase 2
        $this->app->bind(
            \App\Support\DashboardDanaService::class,
            \App\Support\OptimizedDashboardDanaService::class
        );

        $this->app->bind(
            \App\Support\RkaLookupService::class,
            \App\Support\OptimizedRkaLookupService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(static fn (): Password => Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers());

        $this->registerSecurityRateLimiters();
        $this->registerQueueWorkerAutoEnsure();
        $this->registerQueueWorkerHeartbeats();

        // Record user logins to login_histories table
        Event::listen(\Illuminate\Auth\Events\Login::class, function (\Illuminate\Auth\Events\Login $event): void {
            try {
                DB::table('login_histories')->insert([
                    'user_id'    => $event->user->getKey(),
                    'login_at'   => now(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to log login history: ' . $e->getMessage());
            }
        });

        // 🔥 PROTECT FROM ACCIDENTAL DATA LOSS
        // Mencegah perintah destruktif yang dapat menghapus seluruh database secara tidak sengaja.
        if (!app()->runningUnitTests()) {
            Event::listen(CommandStarting::class, function (CommandStarting $event): void {
                $destructiveCommands = ['migrate:fresh', 'migrate:refresh', 'db:wipe'];
                
                if (in_array($event->command, $destructiveCommands, true)) {
                    // Cek apakah ada flag --force (biasanya digunakan di production)
                    // Atau jika kita ingin melarang sama sekali di environment tertentu.
                    $input = $event->input;
                    $isForce = $input->hasParameterOption('--force') || $input->hasParameterOption('-f');
                    
                    // Kita berikan perlindungan ekstra: harus ada konfirmasi manual atau flag khusus.
                    if (app()->environment('local') && !$isForce) {
                        // Di local, kita tetap izinkan TAPI dengan peringatan keras (atau bisa kita blok jika user minta).
                        // Karena user meminta "guard agar tidak bisa terjadi lagi", kita blok saja jika tanpa --force.
                        throw new \RuntimeException(
                            "BAHAYA: Perintah '{$event->command}' diblokir untuk mencegah kehilangan data. " .
                            "Jika Anda BENAR-BENAR ingin mereset database, gunakan flag --force."
                        );
                    }
                }
            });
        }

        View::composer('layouts.sidebar', function ($view): void {
            try {
                $activeImportJobCount = app(ActiveImportJobCounter::class)->count();
            } catch (\Throwable) {
                $activeImportJobCount = 0;
            }

            $view->with('activeImportJobCount', $activeImportJobCount);
        });
    }

    private function registerSecurityRateLimiters(): void
    {
        RateLimiter::for('admin-sensitive', function (Request $request): Limit {
            return Limit::perMinute((int) config('app.security_rate_limits.admin_sensitive_per_minute', 30))
                ->by($this->securityRateLimitKey($request));
        });

        RateLimiter::for('auth-sensitive', function (Request $request): Limit {
            return Limit::perMinute((int) config('app.security_rate_limits.auth_sensitive_per_minute', 12))
                ->by($this->securityRateLimitKey($request));
        });
    }

    private function securityRateLimitKey(Request $request): string
    {
        $user = $request->user();
        $identity = $user ? 'user:' . $user->getAuthIdentifier() : 'guest';
        $route = $request->route()?->getName() ?? $request->path();

        return sha1($identity . '|' . $request->ip() . '|' . $route);
    }

    private function registerQueueWorkerAutoEnsure(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Event::listen(JobQueued::class, function (JobQueued $event): void {
            if ((string) $event->connectionName !== 'database') {
                return;
            }

            $queue = trim((string) ($event->queue ?: 'default'));
            $workerPool = $this->queueWorkerPoolFor($queue);
            if ($workerPool === null) {
                return;
            }

            if (!Cache::add('queue_worker_auto_ensure:throttle:' . sha1($workerPool['name']), true, now()->addSeconds(10))) {
                return;
            }

            try {
                Artisan::call('queue:ensure-running', [
                    '--once' => true,
                    '--queues' => $workerPool['queues'],
                    '--workers' => $workerPool['workers'],
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    private function registerQueueWorkerHeartbeats(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Event::listen(Looping::class, function (Looping $event): void {
            if ((string) $event->connectionName !== 'database') {
                return;
            }

            $workerPool = $this->queueWorkerPoolFor((string) $event->queue);
            if ($workerPool === null) {
                return;
            }

            static $lastHeartbeatAt = [];

            $now = time();
            $processKey = $workerPool['name'] . ':' . getmypid();
            if (($lastHeartbeatAt[$processKey] ?? 0) >= ($now - 5)) {
                return;
            }
            $lastHeartbeatAt[$processKey] = $now;

            $key = 'queue:worker-pool:heartbeats:' . sha1($workerPool['name']);
            $lock = Cache::lock($key . ':lock', 3);
            if (!$lock->get()) {
                return;
            }

            try {
                $heartbeats = Cache::get($key, []);
                $heartbeats = is_array($heartbeats) ? $heartbeats : [];
                $heartbeats = array_filter(
                    $heartbeats,
                    static fn ($timestamp): bool => is_numeric($timestamp) && ($now - (int) $timestamp) <= 30
                );
                $heartbeats[(string) getmypid()] = $now;

                Cache::put($key, $heartbeats, now()->addMinutes(2));

                $pidKey = 'queue:worker-pool:pids:' . sha1($workerPool['name']);
                $pids = Cache::get($pidKey, []);
                $pids = is_array($pids) ? $pids : [];
                $pids[(string) getmypid()] = $now;
                Cache::put($pidKey, $pids, now()->addHours(8));
            } finally {
                $lock->release();
            }
        });
    }

    /** @return array{name: string, queues: string, workers: int}|null */
    private function queueWorkerPoolFor(string $queue): ?array
    {
        $requestedQueues = $this->normalizeQueueNames($queue);

        foreach ((array) config('queue.worker_pools', []) as $poolName => $pool) {
            $queues = $this->normalizeQueueNames((string) ($pool['queues'] ?? ''));

            if ($requestedQueues === $queues
                || (count($requestedQueues) === 1 && in_array($requestedQueues[0], $queues, true))) {
                return [
                    'name' => (string) $poolName,
                    'queues' => implode(',', $queues),
                    'workers' => max(1, (int) ($pool['workers'] ?? 1)),
                ];
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function normalizeQueueNames(string $queues): array
    {
        $names = array_values(array_unique(array_filter(array_map(
            static fn (string $name): string => trim($name),
            explode(',', $queues)
        ))));
        sort($names);

        return $names;
    }

    private function registerCustomQueueExtensions(): void
    {
        // 1. Extend Database Queue Connection to support ID REUSE
        // This must happen after the queue service is registered, so boot() or booted() is appropriate.
        $this->app->booted(function () {
            if ($this->app->bound('queue')) {
                $queue = $this->app['queue'];
                $queue->addConnector('database', function () {
                    return new \App\Queue\Connectors\CustomDatabaseConnector($this->app['db']);
                });
            }
        });

        // 2. Override Failed Job Provider to support ID REUSE
        $this->app->extend('queue.failer', function ($failer, $app) {
            $config = $app['config']['queue.failed'];
            if (!isset($config['database'], $config['table'])) {
                return $failer;
            }

            return new \App\Queue\CustomFailedJobProvider(
                $app['db'], $config['database'], $config['table']
            );
        });

        // 3. Override Batch Repository to support ID REUSE (Numeric IDs)
        $batchRepoResolver = function ($repo, $app) {
            return new \App\Queue\CustomBatchRepository(
                $app->make(\Illuminate\Bus\BatchFactory::class),
                $app->make(\Illuminate\Database\ConnectionInterface::class),
                $app['config']['queue.batching.table'] ?? 'job_batches'
            );
        };

        $this->app->extend(\Illuminate\Bus\BatchRepository::class, $batchRepoResolver);
        $this->app->extend(\Illuminate\Bus\DatabaseBatchRepository::class, $batchRepoResolver);
    }
}
