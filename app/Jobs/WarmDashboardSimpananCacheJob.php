<?php

namespace App\Jobs;

use App\Http\Controllers\DashboardSimpananController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WarmDashboardSimpananCacheJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 0;

    public int $timeout = 900;

    public int $uniqueFor = 86400;

    /** @param array<string, mixed> $context */
    public function __construct(public string $type, public array $context)
    {
        $this->onQueue('reports-low');
        if (in_array($type, ['dashboard-payload', 'micro-readiness', 'area6-portfolio', 'sme-quadrants'], true)) {
            $this->tries = 20;
            $this->backoff = 60;
        }
    }

    public function uniqueId(): string
    {
        return sha1($this->type.'|'.serialize($this->context));
    }

    public function handle(DashboardSimpananController $controller): void
    {
        $ready = $controller->warmDashboardSimpananCache($this->type, $this->context);
        if (!$ready && in_array($this->type, ['dashboard-payload', 'micro-readiness', 'area6-portfolio', 'sme-quadrants'], true)) {
            // A source that never becomes ready (e.g. an old period without
            // Mikro data) is not a failure: releasing on the last attempt made
            // the worker throw MaxAttemptsExceededException into failed_jobs.
            if ($this->attempts() >= $this->tries) {
                Log::warning('Warm cache landing dihentikan: sumber belum siap setelah batas percobaan.', [
                    'type' => $this->type,
                    'period' => $this->context['period'] ?? $this->context['selectedPeriod'] ?? $this->context['loanPeriod'] ?? null,
                    'attempts' => $this->attempts(),
                ]);

                return;
            }

            $this->release($this->backoff);
        }
    }
}
