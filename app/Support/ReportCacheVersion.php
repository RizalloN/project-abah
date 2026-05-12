<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ReportCacheVersion
{
    public static function get(string $scope = 'global'): int
    {
        return (int) Cache::get(self::key($scope), 1);
    }

    /**
     * @param array<int, string> $scopes
     */
    public static function composite(array $scopes): int
    {
        $scopes = array_values(array_unique(array_filter(
            array_map([self::class, 'normalizeScope'], $scopes),
            static fn (string $scope): bool => $scope !== ''
        )));

        if ($scopes === []) {
            return self::get();
        }

        return array_sum(array_map([self::class, 'get'], $scopes));
    }

    public static function bump(string $scope = 'global'): int
    {
        $key = self::key($scope);

        Cache::add($key, 1, now()->addDays(30));

        return (int) Cache::increment($key);
    }

    public static function key(string $scope = 'global'): string
    {
        return 'report_cache_version:' . self::normalizeScope($scope);
    }

    private static function normalizeScope(string $scope): string
    {
        $scope = strtolower(trim($scope));

        return $scope !== '' ? $scope : 'global';
    }
}
