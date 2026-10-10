<?php

namespace App\Jobs;

use App\Http\Controllers\Report\KinerjaRmReportController;
use App\Support\ReportCacheVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Builds the default Kinerja RM Ritel payloads after an import so the first
 * visitor reads a warm cache instead of paying the cold build.
 */
class WarmKinerjaRmRitelCacheJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const SEGMENTS = ['CONSUMER', 'SMALL'];

    public int $tries = 2;

    public int $timeout = 900;

    public int $uniqueFor = 1800;

    private readonly int $cacheVersion;

    public function __construct()
    {
        $this->cacheVersion = ReportCacheVersion::composite(['pinjaman', 'simpanan']);
        $this->onQueue('reports-low');
    }

    public function uniqueId(): string
    {
        return 'report-v'.$this->cacheVersion;
    }

    public function handle(): void
    {
        foreach (self::SEGMENTS as $segment) {
            // The page itself resolves the latest period, so warming through
            // index() fills exactly the keys a default visit will read.
            app(KinerjaRmReportController::class)->index(
                Request::create('/report/dashboard-pinjaman/kinerjarm', 'GET', ['segmen' => $segment])
            );
        }
    }
}
