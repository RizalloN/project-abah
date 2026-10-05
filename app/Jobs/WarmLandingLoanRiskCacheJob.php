<?php

namespace App\Jobs;

use App\Support\LandingLoanRiskCacheService;
use App\Support\ReportCacheVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WarmLandingLoanRiskCacheJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 1200;

    public int $uniqueFor = 86400;

    // Default preserves deserialization of jobs queued before this field existed.
    private ?int $reportVersion = null;

    public function __construct(public readonly string $period)
    {
        $this->reportVersion = ReportCacheVersion::get('pinjaman');
        $this->onQueue('reports-low');
    }

    public function uniqueId(): string
    {
        // Never recompute the version while Laravel releases the unique lock.
        // Legacy payloads have no recoverable dispatch-time version; keep them
        // in a separate namespace so they cannot release a newer job's lock.
        return $this->period.':report-v'.($this->reportVersion ?? 'legacy');
    }

    public function handle(LandingLoanRiskCacheService $service): void
    {
        $service->warm($this->period);
    }
}
