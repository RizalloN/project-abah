<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
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
