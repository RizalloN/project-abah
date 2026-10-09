<?php

namespace App\Jobs;

use App\Support\LandingSmeOperationalService;
use App\Support\ReportCacheVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class WarmLandingSmeQuadrantsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 20;

    public int $timeout = 900;

    public int $uniqueFor = 1800;

    public int $backoff = 60;

    private readonly int $cacheVersion;

    public function __construct(public readonly ?string $period)
    {
        $this->cacheVersion = ReportCacheVersion::composite(['pinjaman', 'harian']);
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return ($this->period ?: 'latest').':'.$this->cacheVersion;
    }

    public function handle(LandingSmeOperationalService $service): void
    {
        $version = ReportCacheVersion::composite(['pinjaman', 'harian']);
        $key = 'landing:sme:quadrants:v2:'.$version.':'.($this->period ?: 'latest');
        $lock = Cache::lock($key.':lock', $this->timeout + 30);
        if (!$lock->get()) {
            $this->release($this->backoff);

            return;
        }
        try {
            if (!$service->warmQuadrants($this->period)) {
                throw new \RuntimeException('Snapshot SME belum menghasilkan payload valid untuk '.($this->period ?: 'latest').'; pengisian cache akan dicoba ulang.');
            }
        } finally {
            $lock->release();
        }
    }
}
