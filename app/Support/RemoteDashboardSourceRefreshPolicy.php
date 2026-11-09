<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class RemoteDashboardSourceRefreshPolicy
{
    public static function retryDelay(string $source): int
    {
        $status = Cache::get('dashboard_sources:last_refresh:'.$source);
        if (! is_array($status) || ! empty($status['success']) || empty($status['next_retry_at'])) {
            return 0;
        }

        try {
            return max(0, CarbonImmutable::parse($status['next_retry_at'])->getTimestamp() - now()->getTimestamp());
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function failed(string $source, string $error, mixed $retryAfter = null): void
    {
        $key = 'dashboard_sources:last_refresh:'.$source;
        $previous = Cache::get($key);
        $previous = is_array($previous) ? $previous : [];
        $failures = ! empty($previous['success']) ? 1 : max(0, (int) ($previous['consecutive_failures'] ?? 0)) + 1;
        $delay = min(21600, 900 * (2 ** min(5, $failures - 1)));
        if (is_numeric($retryAfter)) {
            $delay = max($delay, max(0, (int) $retryAfter));
        } elseif (is_string($retryAfter) && trim($retryAfter) !== '') {
            try {
                $delay = max($delay, CarbonImmutable::parse($retryAfter)->getTimestamp() - now()->getTimestamp());
            } catch (\Throwable) {
                // An invalid provider header must not disable our own retry bound.
            }
        }
        Cache::forever($key, [
            'success' => false,
            'refreshed_at' => now()->toIso8601String(),
            'last_successful_at' => ! empty($previous['success']) ? ($previous['refreshed_at'] ?? null) : ($previous['last_successful_at'] ?? null),
            'error' => mb_substr($error, 0, 500),
            'consecutive_failures' => $failures,
            'next_retry_at' => now()->addSeconds($delay)->toIso8601String(),
        ]);
    }

    public static function succeeded(string $source): void
    {
        Cache::forever('dashboard_sources:last_refresh:'.$source, [
            'success' => true,
            'refreshed_at' => now()->toIso8601String(),
            'last_successful_at' => now()->toIso8601String(),
            'error' => null,
            'consecutive_failures' => 0,
            'next_retry_at' => null,
        ]);
    }
}
