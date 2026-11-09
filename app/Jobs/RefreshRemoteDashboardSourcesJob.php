<?php

namespace App\Jobs;

use App\Http\Controllers\DashboardSimpananController;
use App\Http\Controllers\PublicWorkbookController;
use App\Http\Controllers\Report\AlmafactsDashboardController;
use App\Services\Reports\BusinessClusterReportService;
use App\Services\Reports\MicroPipelineSyncService;
use App\Services\Reports\SppgReportService;
use App\Support\RemoteDashboardSourceRefreshPolicy;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class RefreshRemoteDashboardSourcesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    // Cooldown releases are not execution failures; real failures remain bounded.
    public int $maxExceptions = 3;

    public int $timeout = 480;

    public int $uniqueFor = 86400;

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

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(
        DashboardSimpananController $dashboardSimpanan,
        PublicWorkbookController $publicWorkbook,
        AlmafactsDashboardController $almafacts,
        BusinessClusterReportService $businessCluster,
        SppgReportService $sppg,
        MicroPipelineSyncService $microPipeline
    ): void {
        $errors = [];
        $cooldowns = [];
        $refresh = function (string $source, callable $callback) use (&$errors, &$cooldowns): void {
            $delay = RemoteDashboardSourceRefreshPolicy::retryDelay($source);
            if ($delay > 0) {
                $cooldowns[$source] = $delay;

                return;
            }

            try {
                $this->refreshSource($source, $callback);
            } catch (Throwable $exception) {
                $errors[$source] = $exception;
            }
        };
        try {
            if (in_array('market-share', $this->sources, true)) {
                $refresh('market-share', fn () => $publicWorkbook->refreshMarketShareSource());
            }

            if (in_array('market-share-mapping', $this->sources, true)) {
                $refresh('market-share-mapping', fn () => $dashboardSimpanan->refreshMarketShareMappingSource());
            }

            if (in_array('market-share-instansi', $this->sources, true)) {
                $refresh('market-share-instansi', fn () => $dashboardSimpanan->refreshMarketShareInstansiSources());
            }

            if (in_array('kpi', $this->sources, true)) {
                $refresh('kpi', fn () => $almafacts->refreshKpiSourceCaches($this->kpiSheetKeys, $this->kpiPeriod));
            }

            if (in_array('business-cluster', $this->sources, true)) {
                $refresh('business-cluster', fn () => $businessCluster->refreshSourceCaches());
            }

            if (in_array('sppg', $this->sources, true)) {
                $refresh('sppg', fn () => $sppg->refreshSourceCache());
            }

            if (in_array('micro-pipeline', $this->sources, true)) {
                $refresh('micro-pipeline', fn () => [
                    'success' => collect($microPipeline->syncAll(true))->every(
                        fn (array $result): bool => array_key_exists('changed', $result)
                    ),
                ]);
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

            foreach (['market-share-instansi', 'business-cluster', 'sppg', 'micro-pipeline'] as $source) {
                if (in_array($source, $this->sources, true)) {
                    Cache::forget('dashboard_sources:refresh:'.$source.':pending');
                }
            }
        }

        if ($errors !== []) {
            $messages = array_map(static fn (Throwable $exception): string => $exception->getMessage(), $errors);
            throw new RuntimeException(implode('; ', $messages), 0, reset($errors));
        }
        if ($cooldowns !== []) {
            if ($this->job === null) {
                throw new RuntimeException('Refresh ditunda sampai masa tunggu sumber berakhir: '.implode(', ', array_keys($cooldowns)));
            }
            $this->release(min($cooldowns));
        }
    }

    private function refreshSource(string $source, callable $callback): void
    {
        $retryAfter = null;
        try {
            $result = $callback();
            $success = $this->resultIsSuccessful($result);
            $retryAfter = is_array($result) ? ($result['retry_after'] ?? null) : null;

            if (! $success) {
                $detail = $this->resultError($result);
                throw new RuntimeException('Refresh sumber '.$source.' tidak berhasil; last-good cache tetap digunakan.'.($detail ? ' '.$detail : ''));
            }
            RemoteDashboardSourceRefreshPolicy::succeeded($source);
        } catch (Throwable $exception) {
            if ($exception instanceof RequestException) {
                $retryAfter = $exception->response->header('Retry-After') ?: $retryAfter;
            }
            RemoteDashboardSourceRefreshPolicy::failed($source, $exception->getMessage(), $retryAfter);

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
