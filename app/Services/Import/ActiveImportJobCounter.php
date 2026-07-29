<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActiveImportJobCounter
{
    private const CACHE_KEY = 'sidebar:active-import-jobs:v1';

    public function count(): int
    {
        return (int) Cache::remember(self::CACHE_KEY, now()->addSeconds(20), function (): int {
            if (! Schema::hasTable('import_jobs')) {
                return 0;
            }

            return (int) DB::table('import_jobs')
                ->whereIn('status', ['queued', 'processing'])
                ->count();
        });
    }

    public function forget(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // Cache invalidation is best effort and must never block terminal job updates.
        }
    }
}
