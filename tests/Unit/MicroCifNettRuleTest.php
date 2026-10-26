<?php

namespace Tests\Unit;

use App\Support\MicroCifNettRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MicroCifNettRuleTest extends TestCase
{
    #[DataProvider('amountCases')]
    public function test_cif_nett_ignores_deflation_and_deducts_growth_baseline_once(float $gross, float $previous, float $expected): void
    {
        $this->assertSame($expected, MicroCifNettRule::amount($gross, $previous));
        $this->assertSame(['booking' => $expected], MicroCifNettRule::allocate(['booking' => $gross], $previous));
    }

    public static function amountCases(): array
    {
        return [
            'smaller fresh booking after repayment' => [10_000_000.0, 25_000_000.0, 10_000_000.0],
            'larger refinancing only adds the difference' => [50_000_000.0, 30_000_000.0, 20_000_000.0],
            'equal refinancing has no increment' => [30_000_000.0, 30_000_000.0, 0.0],
            'new borrower' => [10_000_000.0, 0.0, 10_000_000.0],
            'negative baseline contributes zero' => [10_000_000.0, -30_000_000.0, 10_000_000.0],
            'no booking' => [0.0, 30_000_000.0, 0.0],
            'negative booking contributes zero' => [-10_000_000.0, 30_000_000.0, 0.0],
            'negative booking and baseline' => [-10_000_000.0, -30_000_000.0, 0.0],
            'decimal growth' => [50.35, 30.12, 20.23],
            'round inputs before comparing' => [10.004, 10.003, 0.0],
            'half cent rounding' => [50.005, 30.004, 20.01],
        ];
    }

    public function test_multiple_bookings_share_one_cif_baseline_and_preserve_pn_booking_keys(): void
    {
        $this->assertSame([
            '00000801|account-a' => 5_000_000.0,
            '00000802|account-b' => 5_000_000.0,
        ], MicroCifNettRule::allocate([
            '00000801|account-a' => 20_000_000.0,
            '00000802|account-b' => 20_000_000.0,
        ], 30_000_000.0));
    }

    public function test_net_is_proportional_to_booking_amounts_without_changing_their_owners(): void
    {
        $this->assertSame(['PN-A' => 8_000_000.0, 'PN-B' => 12_000_000.0],
            MicroCifNettRule::allocate(['PN-A' => 20_000_000.0, 'PN-B' => 30_000_000.0], 30_000_000.0));
    }

    public function test_largest_remainder_keeps_every_cent_and_breaks_equal_ties_by_stable_booking_key(): void
    {
        $amounts = ['account-c' => 1.0, 'account-a' => 1.0, 'account-b' => 1.0];
        $result = MicroCifNettRule::allocate($amounts, 2.98);

        $this->assertSame(['account-c' => 0.0, 'account-a' => 0.01, 'account-b' => 0.01], $result);
        $this->assertSame(2, $this->totalCents($result));
        $this->assertSame(array_keys($amounts), array_keys($result));

        $reversed = MicroCifNettRule::allocate(array_reverse($amounts, true), 2.98);
        ksort($reversed);
        ksort($result);
        $this->assertSame($result, $reversed);
    }

    public function test_largest_remainder_prefers_larger_fraction_before_the_key(): void
    {
        $result = MicroCifNettRule::allocate(['a' => 1.0, 'b' => 2.0, 'c' => 3.0], 5.98);

        $this->assertSame(['a' => 0.0, 'b' => 0.01, 'c' => 0.01], $result);
        $this->assertSame(2, $this->totalCents($result));
    }

    public function test_rounding_each_positive_booking_to_cents_precedes_the_cif_comparison(): void
    {
        $this->assertSame(['a' => 0.0, 'b' => 0.0], MicroCifNettRule::allocate(['a' => 10.004, 'b' => 10.004], 20.0));
        $this->assertSame(['a' => 0.01, 'b' => 0.01], MicroCifNettRule::allocate(['a' => 10.005, 'b' => 10.005], 20.0));
    }

    public function test_below_baseline_no_baseline_and_negative_values_keep_nonnegative_booking_amounts(): void
    {
        $amounts = [9 => 2.35, 'account-b' => 1.15, 4 => 0.0, 'negative' => -4.0];
        $expected = [9 => 2.35, 'account-b' => 1.15, 4 => 0.0, 'negative' => 0.0];

        $this->assertSame($expected, MicroCifNettRule::allocate($amounts, 10.0));
        $this->assertSame($expected, MicroCifNettRule::allocate($amounts, 0.0));
        $this->assertSame($expected, MicroCifNettRule::allocate($amounts, -10.0));
        $this->assertSame([], MicroCifNettRule::allocate([], 10.0));
        $this->assertSame(['zero' => 0.0, 'negative' => 0.0], MicroCifNettRule::allocate(['zero' => 0.0, 'negative' => -2.0], 0.0));
    }

    public function test_allocation_uses_exact_products_larger_than_native_integer_capacity(): void
    {
        $result = MicroCifNettRule::allocate(['a' => 700_000_000_000.01, 'b' => 300_000_000_000.02], 400_000_000_000.01);

        $this->assertSame(60_000_000_000_002, $this->totalCents($result));
        $this->assertSame(['a' => 420_000_000_000.01, 'b' => 180_000_000_000.01], $result);
    }

    private function totalCents(array $amounts): int
    {
        return array_sum(array_map(static fn (float $amount): int => (int) round($amount * 100), $amounts));
    }
}
