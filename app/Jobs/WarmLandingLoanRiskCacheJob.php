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

    public int $uniqueFor = 900;

    public function __construct(public readonly string $period)
    {
        $this->onQueue('reports-low');
    }

    public function uniqueId(): string
    {
        return $this->period.':report-v'.ReportCacheVersion::get('pinjaman');
    }

    public function handle(LandingLoanRiskCacheService $service): void
    {
        $service->rebuild($this->period);
    }
}
