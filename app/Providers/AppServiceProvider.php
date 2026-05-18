<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        $this->registerSecurityRateLimiters();
        $this->registerQueueWorkerAutoEnsure();

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
            $activeImportJobCount = 0;

            try {
                if (Schema::hasTable('import_jobs')) {
                    $activeImportJobCount = (int) DB::table('import_jobs')
                        ->whereIn('status', ['queued', 'processing'])
                        ->count();
                }
            } catch (\Throwable) {
                $activeImportJobCount = 0;
            }

            $view->with('activeImportJobCount', $activeImportJobCount);
        });
    }

    private function registerSecurityRateLimiters(): void
    {
        RateLimiter::for('admin-sensitive', function (Request $request): Limit {
            return Limit::perMinute((int) env('SECURITY_ADMIN_SENSITIVE_LIMIT_PER_MINUTE', 30))
                ->by($this->securityRateLimitKey($request));
        });

        RateLimiter::for('auth-sensitive', function (Request $request): Limit {
            return Limit::perMinute((int) env('SECURITY_AUTH_SENSITIVE_LIMIT_PER_MINUTE', 12))
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
            $monitoredQueues = array_values(array_filter(array_map(
                static fn (string $name): string => trim($name),
                explode(',', (string) config('queue.worker_queues', 'imports-high,imports-daily-loan,snapshots-parallel,default,reports-low,shadow-backfill'))
            )));

            if (!in_array($queue, $monitoredQueues, true)) {
                return;
            }

            if (!Cache::add('queue_worker_auto_ensure:throttle', true, now()->addSeconds(10))) {
                return;
            }

            try {
                Artisan::call('queue:ensure-running', [
                    '--once' => true,
                    '--queues' => implode(',', $monitoredQueues),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        });
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
