<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
