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
        //
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
}
