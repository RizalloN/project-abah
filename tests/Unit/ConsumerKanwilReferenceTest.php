<?php

namespace Tests\Unit;

use App\Support\ConsumerKanwilReference;
use PHPUnit\Framework\TestCase;

class ConsumerKanwilReferenceTest extends TestCase
{
    public function test_find_rm_by_pn(): void
    {
        $rm = ConsumerKanwilReference::findRm('00369254');
        $this->assertNotNull($rm);
        $this->assertSame('Mochammad Samsul Arifin', $rm['nama']);
        $this->assertSame('JG05', $rm['jg']);
        $this->assertSame(19, $rm['target_deb']);
        $this->assertEquals(3700000000.0, $rm['target_os']);
    }

    public function test_find_rm_by_hyphenated_display_name(): void
    {
        $rm = ConsumerKanwilReference::findRm('00021951 - TITIN OKTAVIA');
        $this->assertNotNull($rm);
        $this->assertSame('Titin Oktavia', $rm['nama']);
        $this->assertSame('JG07', $rm['jg']);
        $this->assertSame(20, $rm['target_deb']);
        $this->assertEquals(3850000000.0, $rm['target_os']);
    }

    public function test_find_rm_by_name_only(): void
    {
        $rm = ConsumerKanwilReference::findRm('RONA ROHANA TALIBATA');
        $this->assertNotNull($rm);
        $this->assertSame('Rona Rohana Talibata', $rm['nama']);
        $this->assertSame('JG06', $rm['jg']);
        $this->assertSame(20, $rm['target_deb']);
        $this->assertEquals(3750000000.0, $rm['target_os']);
    }

    public function test_find_rm_returns_null_for_unknown(): void
    {
        $this->assertNull(ConsumerKanwilReference::findRm('UNKNOWN RM NAME XYZ'));
        $this->assertNull(ConsumerKanwilReference::findRm(''));
    }

    public function test_resolve_target_with_fallback(): void
    {
        // Known RM
        $targetTitin = ConsumerKanwilReference::resolveTarget('TITIN OKTAVIA');
        $this->assertSame(20, $targetTitin['target_deb']);
        $this->assertEquals(3850000000.0, $targetTitin['target_os']);

        // Unknown RM with JG
        $targetJg07 = ConsumerKanwilReference::resolveTarget('SOME NEW RM', 'JG07');
        $this->assertSame(20, $targetJg07['target_deb']);
        $this->assertEquals(3850000000.0, $targetJg07['target_os']);

        // Unknown RM without JG defaults to JG05
        $targetDefault = ConsumerKanwilReference::resolveTarget('SOME NEW RM');
        $this->assertSame(19, $targetDefault['target_deb']);
        $this->assertEquals(3700000000.0, $targetDefault['target_os']);
    }

    public function test_calculate_quadrant_thresholds(): void
    {
        $target = 3700000000.0;

        // >= 105% => Q1
        $this->assertSame(1, ConsumerKanwilReference::calculateQuadrant($target * 1.05, $target));
        $this->assertSame(1, ConsumerKanwilReference::calculateQuadrant($target * 1.50, $target));

        // 100% to 104.99% => Q2
        $this->assertSame(2, ConsumerKanwilReference::calculateQuadrant($target * 1.00, $target));
        $this->assertSame(2, ConsumerKanwilReference::calculateQuadrant($target * 1.049, $target));

        // 50% to 99.99% => Q3
        $this->assertSame(3, ConsumerKanwilReference::calculateQuadrant($target * 0.50, $target));
        $this->assertSame(3, ConsumerKanwilReference::calculateQuadrant($target * 0.999, $target));

        // < 50% => Q4
        $this->assertSame(4, ConsumerKanwilReference::calculateQuadrant($target * 0.499, $target));
        $this->assertSame(4, ConsumerKanwilReference::calculateQuadrant(0, $target));

        // Invalid
        $this->assertNull(ConsumerKanwilReference::calculateQuadrant(null, $target));
        $this->assertNull(ConsumerKanwilReference::calculateQuadrant(100, 0));
    }

    public function test_all_kanwil_roster_historical_months_match_formula(): void
    {
        $totalChecked = 0;
        $mismatches = [];

        foreach (ConsumerKanwilReference::ROSTER as $pn => $rm) {
            $target = $rm['target_os'];
            if ($target <= 0) {
                continue;
            }

            foreach ($rm['months'] as $mNum => $m) {
                if ($m['rp'] === null || $m['kuadran'] === null) {
                    continue;
                }

                $calculated = ConsumerKanwilReference::calculateQuadrant($m['rp'], $target);
                $expected = (int) filter_var($m['kuadran'], FILTER_SANITIZE_NUMBER_INT);

                if ($calculated !== $expected) {
                    $mismatches[] = "RM {$rm['nama']} Month {$mNum}: expected {$expected}, calculated {$calculated} (Rp: {$m['rp']}, Target: {$target})";
                }
                $totalChecked++;
            }
        }

        $this->assertGreaterThan(400, $totalChecked, 'Should check all historical data points');
        $this->assertEmpty($mismatches, 'There should be 0 formula mismatches against official Kanwil table: ' . implode('; ', $mismatches));
    }
}
