<?php

namespace App\Jobs;

use App\Http\Controllers\DashboardSimpananController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WarmDashboardSimpananCacheJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public int $uniqueFor = 600;

    /** @param array<string, mixed> $context */
    public function __construct(public string $type, public array $context)
    {
        $this->onQueue('reports-low');
    }

    public function uniqueId(): string
    {
        return sha1($this->type.'|'.serialize($this->context));
    }

    public function handle(DashboardSimpananController $controller): void
    {
        $controller->warmDashboardSimpananCache($this->type, $this->context);
    }
}
