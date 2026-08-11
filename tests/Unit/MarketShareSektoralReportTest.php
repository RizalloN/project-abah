<?php

namespace Tests\Unit;

use App\Support\MarketShareSektoralReport;
use Tests\TestCase;

class MarketShareSektoralReportTest extends TestCase
{
    public function test_area_6_payload_matches_the_march_workbook_totals(): void
    {
        $payload = MarketShareSektoralReport::payload('area6');

        $this->assertSame('Maret 2026', $payload['period']);
        $this->assertSame('Sektoral per segmen Area 6 Maret.xlsx', $payload['source']);
        $this->assertCount(19, $payload['rows']);
        $this->assertEqualsWithDelta(13367.4046398994, $payload['total']['bri_os'], 0.000001);
        $this->assertEqualsWithDelta(43894.440654738, $payload['total']['industry_os'], 0.000001);
        $this->assertEqualsWithDelta(30527.0360148386, $payload['total']['potential_os'], 0.000001);
        $this->assertEqualsWithDelta(0.3045352541, $payload['total']['market_share_os'], 0.0000001);
        $this->assertEqualsWithDelta(0.0648782838, $payload['total']['bri_sml_ratio'], 0.0000001);
        $this->assertEqualsWithDelta(0.0451110607, $payload['total']['bri_npl_ratio'], 0.0000001);
        $this->assertSame('Perdagangan Besar Dan Eceran, Reparasi Mobil Dan Motor', $payload['rows'][0]['sector']);
        $this->assertArrayHasKey('comparison', $payload['charts']);
        $this->assertArrayNotHasKey('composition', $payload['charts']);
        $this->assertTrue(collect($payload['rows'])->every(
            static fn (array $row): bool => (float) $row['potential_os'] >= 0
        ));
    }

    public function test_branch_payloads_keep_their_own_scope_and_recalculate_ratios(): void
    {
        $expectedTotals = [
            'madiun' => 3764.3231921138,
            'magetan' => 2716.7741825332,
            'ngawi' => 2774.6323986575,
            'ponorogo' => 4111.6748665949,
        ];

        foreach ($expectedTotals as $scope => $expectedTotal) {
            $payload = MarketShareSektoralReport::payload($scope);

            $this->assertSame($scope, $payload['selected_scope']);
            $this->assertCount(19, $payload['rows']);
            $this->assertEqualsWithDelta($expectedTotal, $payload['total']['bri_os'], 0.000001);
            $this->assertCount(8, $payload['charts']['top_sectors']);
        }
    }

    public function test_unknown_scope_falls_back_to_area_6(): void
    {
        $payload = MarketShareSektoralReport::payload('tidak-valid');

        $this->assertSame('area6', $payload['selected_scope']);
        $this->assertSame('Area 6 (Semua Cabang)', $payload['selected_scope_label']);
    }
}
