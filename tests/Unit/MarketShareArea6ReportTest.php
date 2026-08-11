<?php

namespace Tests\Unit;

use App\Support\MarketShareArea6Report;
use Tests\TestCase;

class MarketShareArea6ReportTest extends TestCase
{
    public function test_payload_uses_the_may_2026_area_6_reference(): void
    {
        $payload = MarketShareArea6Report::payload('dpk');

        $this->assertSame('Mei 2026', $payload['period']);
        $this->assertSame('Report Market Share Umum RO Malang Mei 2026.pdf', $payload['source']);
        $this->assertSame(
            ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo', 'Area 6'],
            array_column($payload['rows'], 'branch')
        );
        $this->assertSame(
            ['4,039', '3,975', '4,205', '5.8%', '13,586', '14,098', '14,362', '1.9%', '9,547', '10,123', '10,156', '0.3%', '29.7%', '28.2%', '29.3%', '-0.44%', '1.09%'],
            $payload['rows'][0]['values']
        );
        $this->assertSame(
            ['13,502', '13,646', '14,140', '3.6%', '34,423', '35,427', '36,461', '2.9%', '20,921', '21,781', '22,321', '2.5%', '39.2%', '38.5%', '38.8%', '-0.44%', '0.26%'],
            $payload['rows'][4]['values']
        );
    }

    public function test_all_segments_keep_their_expected_branches_and_columns(): void
    {
        $index = MarketShareArea6Report::payload();
        $expectedBranches = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo', 'Area 6'];

        foreach ($index['segments'] as $segment) {
            $payload = MarketShareArea6Report::payload($segment['key']);

            $this->assertSame($expectedBranches, array_column($payload['rows'], 'branch'));
            $this->assertCount(5, $payload['rows']);

            foreach ($payload['rows'] as $row) {
                $this->assertCount(count($payload['headers']['columns']), $row['values']);
            }
        }

        $this->assertSame(
            ['841', '5.46%', '643', '4.18%', '-0.53%', '1.42%', '-0.02%', '0.83%', '1,767', '3.91%', '1,196', '2.65%', '0.58%', '0.69%', '0.21%', '0.49%', '47.56%', '53.76%', '-12.63%', '6.57%', '-1.89%', '1.95%'],
            MarketShareArea6Report::payload('quality_total')['rows'][4]['values']
        );
    }
}
