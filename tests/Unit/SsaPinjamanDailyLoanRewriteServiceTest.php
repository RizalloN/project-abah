<?php

namespace Tests\Unit;

use App\Services\SsaPinjamanDailyLoanRewriteService;
use App\Support\DashboardHarianSnapshotService;
use ReflectionMethod;
use Tests\TestCase;

class SsaPinjamanDailyLoanRewriteServiceTest extends TestCase
{
    public function test_rewrite_preserves_kur_ritel_as_small_kur_small(): void
    {
        $service = app(SsaPinjamanDailyLoanRewriteService::class);
        $method = new ReflectionMethod($service, 'normalizedDailyLoanQuery');
        $sql = $method->invoke($service, SsaPinjamanDailyLoanRewriteService::TARGET_PERIOD)->toSql();

        $this->assertStringContainsString("= 'KUR-SMALL' THEN 'KUR-Small'", $sql);
        $this->assertStringContainsString('source_description', $sql);
        $this->assertStringContainsString("source_description LIKE '%KUR RITEL 2015 NEW%' THEN 'KUR Kecil'", $sql);
        $this->assertStringContainsString("= 'KUR-KECIL' THEN 'KUR-Mikro'", $sql);
    }

    public function test_snapshot_counts_small_kur_small_as_kur_kecil(): void
    {
        $service = app(DashboardHarianSnapshotService::class);
        $method = new ReflectionMethod($service, 'loanMetricDefinitions');
        $definitions = $method->invoke($service, 'SEGMENT', 'PRODUCT_DASHBOARD', 'PRODUCT', 'SEGMENT_2025');

        $this->assertIsArray($definitions['kur_kecil']);
        $this->assertContains(
            "SEGMENT = 'SMALL' AND PRODUCT_DASHBOARD = 'KUR-SMALL' AND PRODUCT = 'KUR KECIL'",
            $definitions['kur_kecil']
        );
        $this->assertStringContainsString("SEGMENT IN ('MICRO', 'MIKRO')", $definitions['kur_kecil'][0]);
    }
}
