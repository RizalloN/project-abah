<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Http\Controllers\Report\AlmafactsDashboardController;
use App\Jobs\RefreshRemoteDashboardSourcesJob;
use App\Services\Reports\BusinessClusterReportService;
use App\Services\Reports\SppgReportService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RemoteDashboardSourceTest extends TestCase
{
    private string $mappingPath;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Cache::flush();
        Queue::fake();
        $this->mappingPath = storage_path('app/testing/market-share-mapping.xlsx');
        File::delete($this->mappingPath);
        Config::set('services.market_share_mapping.cache_path', 'app/testing/market-share-mapping.xlsx');
        Config::set('services.market_share_mapping.fallback_cache_path', 'app/testing/missing-fallback.xlsx');
        Config::set('services.market_share_mapping.source_url', 'https://docs.google.com/spreadsheets/d/source-id/export?format=xlsx');
    }

    protected function tearDown(): void
    {
        File::delete($this->mappingPath);
        parent::tearDown();
    }

    public function test_mapping_request_queues_refresh_without_calling_google(): void
    {
        Http::preventStrayRequests();
        $controller = new DashboardSimpananController;
        $method = new \ReflectionMethod($controller, 'freshMarketShareMappingWorkbookPath');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller));
        Queue::assertPushed(RefreshRemoteDashboardSourcesJob::class, fn ($job): bool => $job->sources === ['market-share-mapping']);
        Http::assertNothingSent();
    }

    public function test_mapping_refresh_atomically_replaces_cache_only_with_valid_workbook(): void
    {
        $oldWorkbook = "PK\x03\x04".str_repeat('a', 2048);
        File::ensureDirectoryExists(dirname($this->mappingPath));
        File::put($this->mappingPath, $oldWorkbook);

        Http::fake(['docs.google.com/*' => Http::response('invalid response', 200)]);
        $failed = (new DashboardSimpananController)->refreshMarketShareMappingSource();
        $this->assertFalse($failed['success']);
        $this->assertSame($oldWorkbook, File::get($this->mappingPath));

        $newWorkbook = "PK\x03\x04".str_repeat('b', 4096);
        $successHttp = new Factory;
        $successHttp->fake(['docs.google.com/*' => Http::response($newWorkbook, 200)]);
        Http::swap($successHttp);
        $success = (new DashboardSimpananController)->refreshMarketShareMappingSource();

        $this->assertTrue($success['success']);
        $this->assertSame($newWorkbook, File::get($this->mappingPath));
    }

    public function test_failed_kpi_refresh_keeps_last_good_payload(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response("\"KEY PERFORMING INDICATOR\",\"MBM\",\"SCORE\"\n\"MADIUN\",\"NUR\",\"98%\"", 200),
        ]);
        $controller = new AlmafactsDashboardController;
        $controller->refreshKpiSourceCaches(['mbm']);

        $failedHttp = new Factory;
        $failedHttp->fake(['docs.google.com/*' => Http::response('unavailable', 503)]);
        Http::swap($failedHttp);
        $result = $controller->refreshKpiSourceCaches(['mbm']);
        $view = $controller->kpi(Request::create('/report/dashboard-almafacts/kpi/mbm', 'GET'), 'mbm');

        $this->assertFalse($result['mbm']['success']);
        $this->assertSame(['BO', 'MBM', 'Score'], $view->getData()['header']);
        $this->assertSame('NUR', $view->getData()['rows'][0][1]);
    }

    public function test_stale_report_caches_are_served_while_background_refresh_is_queued(): void
    {
        Http::preventStrayRequests();

        $businessKey = 'report:business_cluster:v2:'.md5('KC Madiun|https://example.test/business');
        Cache::forever($businessKey, [
            'rows' => collect([['kategori' => 'Pasar']]),
            'errors' => collect(),
            'fetched_at' => now()->subMinutes(11)->toDateTimeString(),
        ]);
        $businessMethod = new \ReflectionMethod(BusinessClusterReportService::class, 'readSpreadsheet');
        $businessResult = $businessMethod->invoke(
            app(BusinessClusterReportService::class),
            'KC Madiun',
            'https://example.test/business'
        );

        $sppgLink = [
            'label' => 'SPPG',
            'sheet_name' => 'Area 6',
            'spreadsheet_id' => 'source-id',
            'link_url' => 'https://example.test/sppg',
        ];
        $sppgKey = 'report:sppg:v2:'.md5('https://example.test/sppg|Area 6');
        Cache::forever($sppgKey, [
            'rows' => collect([['branch_office' => 'KC Madiun']]),
            'errors' => collect(),
            'link' => $sppgLink,
            'totalRows' => 1,
            'branchOptions' => collect(['KC Madiun']),
            'lastFetchedAt' => now()->subMinutes(11),
        ]);
        $sppgMethod = new \ReflectionMethod(SppgReportService::class, 'readSpreadsheet');
        $sppgResult = $sppgMethod->invoke(app(SppgReportService::class), $sppgLink);

        $this->assertCount(1, $businessResult['rows']);
        $this->assertCount(1, $sppgResult['rows']);
        Queue::assertPushed(RefreshRemoteDashboardSourcesJob::class, fn ($job): bool => $job->sources === ['business-cluster']);
        Queue::assertPushed(RefreshRemoteDashboardSourcesJob::class, fn ($job): bool => $job->sources === ['sppg']);
        Http::assertNothingSent();
    }

    public function test_scheduled_refresh_skips_fresh_sources_and_queues_failed_source(): void
    {
        $sources = ['market-share', 'market-share-mapping', 'market-share-instansi', 'kpi', 'business-cluster', 'sppg'];
        foreach ($sources as $source) {
            Cache::forever('dashboard_sources:last_refresh:'.$source, [
                'success' => true,
                'refreshed_at' => now()->toIso8601String(),
                'error' => null,
            ]);
        }

        $this->artisan('dashboard-sources:refresh', ['--queue' => true, '--only-stale' => true])
            ->expectsOutput('Semua remote source masih fresh; tidak ada job yang dijadwalkan.')
            ->assertSuccessful();
        Queue::assertNothingPushed();

        Cache::forever('dashboard_sources:last_refresh:kpi', [
            'success' => false,
            'refreshed_at' => now()->toIso8601String(),
            'error' => 'temporary failure',
        ]);
        $this->artisan('dashboard-sources:refresh', ['--queue' => true, '--only-stale' => true])
            ->expectsOutput('Remote source refresh jobs dispatched: kpi')
            ->assertSuccessful();
        Queue::assertPushed(RefreshRemoteDashboardSourcesJob::class, 1);
        Queue::assertPushed(RefreshRemoteDashboardSourcesJob::class, fn ($job): bool => $job->sources === ['kpi']);
    }
}
