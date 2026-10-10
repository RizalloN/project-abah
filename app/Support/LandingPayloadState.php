<?php

namespace App\Support;

final class LandingPayloadState
{
    /** A pending dependency must not be published as a completed landing payload. */
    public static function pending(array $payload): bool
    {
        if (!empty($payload['refresh_pending'])) {
            return true;
        }

        foreach ($payload as $value) {
            if (is_array($value) && self::pending($value)) {
                return true;
            }
        }

        return false;
    }
}
