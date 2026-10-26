<?php

namespace App\Services\Import\Crosstab;

use Illuminate\Support\Facades\Cache;

/**
 * Keeps what the crosstab normalizer changed, keyed by the staged file, so the
 * preview can tell the user. Keyed by path rather than session because several
 * uploads may be previewed at once.
 */
class CrosstabNormalizationNotice
{
    public static function remember(string $relativePath, ?array $summary): void
    {
        if (empty($summary['notices'])) {
            return;
        }

        Cache::put(self::key($relativePath), $summary, now()->addMinutes((int) config('import.preview_state_ttl_minutes', 360)));
    }

    public static function forPath(?string $relativePath): ?array
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return null;
        }

        $summary = Cache::get(self::key($relativePath));

        return is_array($summary) ? $summary : null;
    }

    private static function key(string $relativePath): string
    {
        return 'crosstab_normalization:'.md5(str_replace('\\', '/', trim($relativePath)));
    }
}
