<?php

namespace App\Jobs;

use App\Services\Import\ImportProgressService;
use App\Support\LandingMicroPerformanceService;
use App\Support\UserBranchScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds the landing micro panel for every branch scope before users open it.
 * A new period has no cached payload yet, so without this the first visitor
 * per branch waited for the full build (often past Cloudflare's 100 s limit,
 * which shows as a page that never finishes loading). A warm cache makes
 * each call here a cheap lookup.
 */
class WarmLandingMicroPerformanceJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $timeout = 1800;
    public $tries = 1;
    public $uniqueFor = 1800;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'landing-micro-warm';
    }

    public function handle(ImportProgressService $imports): void
    {
        // Warming against a half-imported period would cache partial numbers.
        if ($imports->hasActiveProcessingJobsForTable('daily_loan_dinamis')) {
            return;
        }

        $service = app(LandingMicroPerformanceService::class);

        foreach (array_merge([null], UserBranchScope::branchScopes()) as $scope) {
            try {
                $service->payload(null, $scope);
            } catch (Throwable $e) {
                Log::warning('Pemanasan cache landing mikro gagal.', [
                    'scope' => $scope['key'] ?? UserBranchScope::AREA_SCOPE,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
