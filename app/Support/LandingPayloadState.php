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

    /**
     * A full-page reread only helps a page rendered without data. Stale but valid
     * figures stay on screen while workers refresh the caches in the background.
     */
    public static function needsPageRecovery(array $payload): bool
    {
        return empty($payload['period']) && self::pending($payload);
    }
}
