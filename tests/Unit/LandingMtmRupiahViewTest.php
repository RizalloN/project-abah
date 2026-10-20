<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use Tests\TestCase;

class LandingMtmRupiahViewTest extends TestCase
{
    public function test_loan_card_color_uses_raw_delta_even_when_percentage_rounds_to_zero(): void
    {
        $source = file_get_contents(resource_path('views/dashboard.blade.php'));
        $start = strpos($source, '<div class="kpi-card simpanan">');
        $end = strpos($source, '{{-- PINJAMAN --}}', $start);
        $fragment = substr($source, $start, $end - $start);
        $html = (string) $this->blade($fragment, ['simpananReport' => [
            'value' => 'Rp1 T', 'trend' => '0,00%', 'trend_raw' => -1000,
            'trend_nominal' => '-Rp1.000', 'trend_period' => '2026-09-17',
        ]]);
        $this->assertStringContainsString('kc-delta neg', $html);
        $this->assertStringContainsString('-Rp1.000 MtM', $html);
        $this->assertStringContainsString('2026-09-17', $html);
        $this->assertStringNotContainsString('0,00%', $html);
    }

    public function test_savings_total_and_product_cards_render_rupiah_with_matching_signs(): void
    {
        $source = file_get_contents(resource_path('views/dashboard/simpanan.blade.php'));
        $start = strpos($source, '<div class="kpi-strip">');
        $end = strpos($source, '{{-- CARD 2:', $start);
        $fragment = substr($source, $start, $end - $start);
        foreach ([5_000_000_000 => '+Rp5,00 M', -5_000_000_000 => '-Rp5,00 M', 0 => 'Rp0'] as $raw => $nominal) {
            $html = (string) $this->blade($fragment, [
                'dashboard' => ['kpi_summary' => ['total_simpanan' => [
                    'trend_mtm_nominal' => $nominal, 'trend_mtm_raw' => $raw,
                    'trend_mtm_period' => '2026-09-17',
                ]]],
                'area6Portfolio' => [],
            ]);
            $this->assertStringContainsString($nominal.' MtM', $html);
            $this->assertStringContainsString('kc-delta '.($raw < 0 ? 'neg' : 'pos'), $html);
        }

        $controller = app(DashboardSimpananController::class);
        $card = (new \ReflectionMethod($controller, 'buildSimpananScopeCard'))->invoke(
            $controller, 'tabungan', 'Tabungan', ['tabungan_ritel'],
            collect([['key' => 'tabungan_ritel', 'values' => ['current' => 20_000_000_000], 'deltas' => ['mtm' => -1_500_000_000]]])->keyBy('key'),
            '17 Okt 2026', 'Oktober 2026', 'blue', 'fas fa-wallet'
        );
        $this->assertSame('-Rp1,50 M', $card['deltas']['mom']['nominal_value']);
        $this->assertSame(-1_500_000_000.0, $card['deltas']['mom']['raw']);
    }
}
