<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class WarmReportCacheJob implements ShouldQueue, ShouldBeUnique
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

    public function uniqueId(): string
    {
        return 'all';
    }
}
