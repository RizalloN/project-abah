<?php

namespace App\Jobs;

use App\Http\Controllers\DashboardSimpananController;
use App\Http\Controllers\PublicWorkbookController;
use App\Http\Controllers\Report\AlmafactsDashboardController;
use App\Services\Reports\BusinessClusterReportService;
use App\Services\Reports\SppgReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class RefreshRemoteDashboardSourcesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 480;

    public int $uniqueFor = 900;

    public array $backoff = [30, 120, 300];

    /**
     * @param  array<int, string>  $sources
     * @param  array<int, string>  $kpiSheetKeys
     */
    public function __construct(
        public array $sources = ['market-share', 'market-share-mapping', 'market-share-instansi', 'kpi', 'business-cluster', 'sppg'],
        public array $kpiSheetKeys = [],
        public string $kpiPeriod = '2026-07'
    ) {
        $this->onQueue('remote-sources');
    }

    public function uniqueId(): string
    {
        return sha1(implode(',', $this->sources).'|'.implode(',', $this->kpiSheetKeys).'|'.$this->kpiPeriod);
    }

    public function handle(
        DashboardSimpananController $dashboardSimpanan,
        PublicWorkbookController $publicWorkbook,
        AlmafactsDashboardController $almafacts,
        BusinessClusterReportService $businessCluster,
        SppgReportService $sppg
    ): void {
        try {
            if (in_array('market-share', $this->sources, true)) {
                $this->refreshSource('market-share', fn () => $publicWorkbook->refreshMarketShareSource());
            }

            if (in_array('market-share-mapping', $this->sources, true)) {
                $this->refreshSource('market-share-mapping', fn () => $dashboardSimpanan->refreshMarketShareMappingSource());
            }

            if (in_array('market-share-instansi', $this->sources, true)) {
                $this->refreshSource('market-share-instansi', fn () => $dashboardSimpanan->refreshMarketShareInstansiSources());
            }

            if (in_array('kpi', $this->sources, true)) {
                $this->refreshSource('kpi', fn () => $almafacts->refreshKpiSourceCaches($this->kpiSheetKeys, $this->kpiPeriod));
            }

            if (in_array('business-cluster', $this->sources, true)) {
                $this->refreshSource('business-cluster', fn () => $businessCluster->refreshSourceCaches());
            }

            if (in_array('sppg', $this->sources, true)) {
                $this->refreshSource('sppg', fn () => $sppg->refreshSourceCache());
            }
        } finally {
            if (in_array('market-share', $this->sources, true)) {
                Cache::forget('dashboard_sources:refresh:market-share:pending');
            }

            if (in_array('market-share-mapping', $this->sources, true)) {
                Cache::forget('dashboard_sources:refresh:market-share-mapping:pending');
            }

            foreach ($this->kpiSheetKeys as $sheetKey) {
                Cache::forget('dashboard_sources:refresh:kpi:'.$this->kpiPeriod.':'.$sheetKey.':pending');
            }

            if ($this->kpiSheetKeys === []) {
                Cache::forget('dashboard_sources:refresh:kpi:'.$this->kpiPeriod.':all:pending');
            }

            foreach (['market-share-instansi', 'business-cluster', 'sppg'] as $source) {
                if (in_array($source, $this->sources, true)) {
                    Cache::forget('dashboard_sources:refresh:'.$source.':pending');
                }
            }
        }
    }

    private function refreshSource(string $source, callable $callback): void
    {
        try {
            $result = $callback();
            $success = $this->resultIsSuccessful($result);
            Cache::forever('dashboard_sources:last_refresh:'.$source, [
                'success' => $success,
                'refreshed_at' => now()->toIso8601String(),
                'error' => $success ? null : $this->resultError($result),
            ]);

            if (! $success) {
                throw new RuntimeException('Refresh sumber '.$source.' tidak berhasil; last-good cache tetap digunakan.');
            }
        } catch (Throwable $exception) {
            Cache::forever('dashboard_sources:last_refresh:'.$source, [
                'success' => false,
                'refreshed_at' => now()->toIso8601String(),
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            throw $exception;
        }
    }

    private function resultIsSuccessful(mixed $result): bool
    {
        if (is_bool($result)) {
            return $result;
        }

        if (! is_array($result)) {
            return false;
        }

        if (array_key_exists('success', $result)) {
            return (bool) $result['success'];
        }

        return $result !== [] && collect($result)->every(
            fn ($item): bool => is_array($item) && (bool) ($item['success'] ?? false)
        );
    }

    private function resultError(mixed $result): ?string
    {
        if (! is_array($result)) {
            return null;
        }

        $error = $result['error'] ?? $result['errors'] ?? null;
        if ($error === null) {
            $error = collect($result)->pluck('error')->filter()->first();
        }

        if (is_array($error)) {
            $error = implode('; ', array_map('strval', $error));
        }

        return $error === null ? null : mb_substr((string) $error, 0, 500);
    }
}
