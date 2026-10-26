<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;

final class MicroCifNettRule
{
    public static function amount(float $gross, float $previous): float
    {
        return self::money(self::netCents(self::cents($gross), self::cents($previous)));
    }

    /**
     * Allocate one CIF result proportionally while preserving booking keys.
     * Largest remainders receive any residual cents, with stable key ties.
     *
     * @param  array<int|string, int|float>  $amounts
     * @return array<int|string, float>
     */
    public static function allocate(array $amounts, float $previous): array
    {
        $gross = BigInteger::zero();
        $bookings = [];
        foreach ($amounts as $key => $amount) {
            $bookings[$key] = self::cents((float) $amount);
            $gross = $gross->plus($bookings[$key]);
        }

        $net = self::netCents($gross, self::cents($previous));
        if ($net->isZero()) {
            return array_fill_keys(array_keys($amounts), 0.0);
        }

        $shares = [];
        $remainders = [];
        $allocated = BigInteger::zero();
        foreach ($bookings as $key => $amount) {
            [$share, $remainder] = $net->multipliedBy($amount)->quotientAndRemainder($gross);
            $shares[$key] = $share;
            $allocated = $allocated->plus($share);
            $remainders[] = ['key' => $key, 'remainder' => $remainder];
        }

        usort($remainders, static fn (array $left, array $right): int => $right['remainder']->compareTo($left['remainder'])
            ?: strcmp((string) $left['key'], (string) $right['key']));
        $remaining = $net->minus($allocated)->toInt();
        for ($index = 0; $index < $remaining; $index++) {
            $key = $remainders[$index]['key'];
            $shares[$key] = $shares[$key]->plus(1);
        }

        return array_map(self::money(...), $shares);
    }

    private static function netCents(BigInteger $gross, BigInteger $previous): BigInteger
    {
        // Deflation does not offset a fresh booking; equal refinancing adds zero.
        return $gross->compareTo($previous) < 0 ? $gross : $gross->minus($previous);
    }

    private static function cents(float $amount): BigInteger
    {
        if ($amount <= 0.0) {
            return BigInteger::zero();
        }

        return BigDecimal::of(json_encode($amount, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION))
            ->toScale(2, RoundingMode::HALF_UP)->getUnscaledValue();
    }

    private static function money(BigInteger $cents): float
    {
        return $cents->toBigDecimal()->withPointMovedLeft(2)->toFloat();
    }
}
