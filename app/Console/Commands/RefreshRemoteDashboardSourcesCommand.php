<?php

namespace App\Console\Commands;

use App\Http\Controllers\DashboardSimpananController;
use App\Http\Controllers\PublicWorkbookController;
use App\Http\Controllers\Report\AlmafactsDashboardController;
use App\Jobs\RefreshRemoteDashboardSourcesJob;
use App\Services\Reports\BusinessClusterReportService;
use App\Services\Reports\SppgReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class RefreshRemoteDashboardSourcesCommand extends Command
{
    protected $signature = 'dashboard-sources:refresh
        {--source=all : all, market-share, market-share-mapping, market-share-instansi, kpi, business-cluster, or sppg}
        {--kpi= : Optional comma-separated KPI sheet keys}
        {--kpi-period=2026-07 : KPI source period (2026-06 or 2026-07)}
        {--queue : Dispatch isolated refresh jobs instead of waiting for remote sources}
        {--only-stale : With --queue, skip sources whose last successful refresh is still fresh}';

    protected $description = 'Refresh remote dashboard sources into atomic last-good local caches';

    public function handle(
        DashboardSimpananController $dashboardSimpanan,
        PublicWorkbookController $publicWorkbook,
        AlmafactsDashboardController $almafacts,
        BusinessClusterReportService $businessCluster,
        SppgReportService $sppg
    ): int {
        $source = strtolower(trim((string) $this->option('source')));
        if (! in_array($source, ['all', 'market-share', 'market-share-mapping', 'market-share-instansi', 'kpi', 'business-cluster', 'sppg'], true)) {
            $this->error('Source tidak didukung.');

            return self::INVALID;
        }

        $keys = array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) $this->option('kpi'))
        )));
        $kpiPeriod = trim((string) $this->option('kpi-period'));
        if (!in_array($kpiPeriod, ['2026-06', '2026-07'], true)) {
            $this->error('Periode KPI tidak didukung. Gunakan 2026-06 atau 2026-07.');

            return self::INVALID;
        }

        if ((bool) $this->option('queue')) {
            $sources = $source === 'all'
                ? ['market-share', 'market-share-mapping', 'market-share-instansi', 'kpi', 'business-cluster', 'sppg']
                : [$source];
            if ((bool) $this->option('only-stale')) {
                $sources = array_values(array_filter(
                    $sources,
                    fn (string $sourceName): bool => $this->sourceNeedsRefresh($sourceName)
                ));
            }

            foreach ($sources as $sourceName) {
                RefreshRemoteDashboardSourcesJob::dispatch(
                    [$sourceName],
                    $sourceName === 'kpi' ? $keys : [],
                    $kpiPeriod
                );
            }

            $this->info($sources === []
                ? 'Semua remote source masih fresh; tidak ada job yang dijadwalkan.'
                : 'Remote source refresh jobs dispatched: '.implode(', ', $sources));

            return self::SUCCESS;
        }

        $results = [];

        if (in_array($source, ['all', 'market-share'], true)) {
            $results['market-share'] = $publicWorkbook->refreshMarketShareSource();
        }

        if (in_array($source, ['all', 'market-share-mapping'], true)) {
            $results['market-share-mapping'] = $dashboardSimpanan->refreshMarketShareMappingSource();
        }

        if (in_array($source, ['all', 'market-share-instansi'], true)) {
            $results['market-share-instansi'] = $dashboardSimpanan->refreshMarketShareInstansiSources();
        }

        if (in_array($source, ['all', 'kpi'], true)) {
            $results['kpi'] = $almafacts->refreshKpiSourceCaches($keys, $kpiPeriod);
        }

        if (in_array($source, ['all', 'business-cluster'], true)) {
            $results['business-cluster'] = $businessCluster->refreshSourceCaches();
        }

        if (in_array($source, ['all', 'sppg'], true)) {
            $results['sppg'] = $sppg->refreshSourceCache();
        }

        $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $failed = false;
        foreach (['market-share', 'market-share-mapping', 'market-share-instansi', 'business-cluster', 'sppg'] as $key) {
            if (isset($results[$key]) && ($results[$key]['success'] ?? false) === false) {
                $failed = true;
            }
        }

        foreach ((array) ($results['kpi'] ?? []) as $result) {
            if (($result['success'] ?? false) === false) {
                $failed = true;
                break;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function sourceNeedsRefresh(string $source): bool
    {
        $status = Cache::get('dashboard_sources:last_refresh:'.$source);
        if (! is_array($status) || ! (bool) ($status['success'] ?? false)) {
            return ! $this->localSourceFileIsFresh($source);
        }

        try {
            $refreshedAt = Carbon::parse((string) ($status['refreshed_at'] ?? ''));
        } catch (\Throwable) {
            return true;
        }

        return $refreshedAt->lt(now()->subMinutes($this->freshnessMinutes($source)));
    }

    private function freshnessMinutes(string $source): int
    {
        return match ($source) {
            'kpi' => 5,
            'market-share' => max(5, (int) config('services.market_share.cache_minutes', 15)),
            'market-share-mapping' => max(5, (int) config('services.market_share_mapping.cache_minutes', 15)),
            default => 10,
        };
    }

    private function localSourceFileIsFresh(string $source): bool
    {
        $path = match ($source) {
            'market-share' => storage_path(trim((string) config('services.market_share.cache_path'), '/\\')),
            'market-share-mapping' => storage_path(trim((string) config('services.market_share_mapping.cache_path'), '/\\')),
            default => null,
        };
        if ($path === null || ! File::isFile($path)) {
            return false;
        }

        return File::lastModified($path) >= now()
            ->subMinutes($this->freshnessMinutes($source))
            ->getTimestamp();
    }
}
