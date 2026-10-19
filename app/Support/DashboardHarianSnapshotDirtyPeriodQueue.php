<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardHarianSnapshotDirtyPeriodQueue
{
    private const DIRTY_PERIODS_KEY = 'snapshot:dashboard_harian:dirty_periods';
    private const PENDING_DISPATCH_KEY = 'snapshot:dashboard_harian:dirty_periods:pending';
    private const INFLIGHT_KEY = 'snapshot:dashboard_harian:dirty_periods:inflight';
    private const LOCK_KEY = 'snapshot:dashboard_harian:dirty_periods:lock';
    private const ALL_PERIODS_TOKEN = '__all__';
    private const CACHE_TTL_SECONDS = 21600;
    private const PENDING_DISPATCH_TTL_SECONDS = 120;
    private const DEBOUNCE_SECONDS = 20;

    /**
     * @param array<int, string|null> $periods
     */
    public function register(array $periods): bool
    {
        return $this->withLock(function () use ($periods): bool {
            $dirty = Cache::get(self::DIRTY_PERIODS_KEY, []);
            $dirty = is_array($dirty) ? $dirty : [];

            foreach ($periods as $period) {
                $normalized = trim((string) $period);
                $dirty[$normalized !== '' ? $normalized : self::ALL_PERIODS_TOKEN] = true;
            }

            Cache::put(
                self::DIRTY_PERIODS_KEY,
                $dirty,
                now()->addSeconds(self::CACHE_TTL_SECONDS)
            );

            $pending = Cache::get(self::PENDING_DISPATCH_KEY);
            $pendingAt = is_string($pending) ? strtotime($pending) : false;
            if ($pendingAt !== false && $pendingAt <= now()->getTimestamp() - self::PENDING_DISPATCH_TTL_SECONDS) {
                Cache::forget(self::PENDING_DISPATCH_KEY);
            }

            return Cache::add(
                self::PENDING_DISPATCH_KEY,
                now()->toIso8601String(),
                now()->addSeconds(self::PENDING_DISPATCH_TTL_SECONDS)
            );
        });
    }

    /**
     * @return array<int, string>|null Null means check automatic due periods.
     */
    public function consume(): ?array
    {
        return $this->withLock(function (): ?array {
            $dirty = Cache::pull(self::DIRTY_PERIODS_KEY, []);
            Cache::forget(self::PENDING_DISPATCH_KEY);

            if (!is_array($dirty) || $dirty === []) {
                return [];
            }

            if (array_key_exists(self::ALL_PERIODS_TOKEN, $dirty)) {
                return null;
            }

            $periods = array_values(array_filter(array_keys($dirty), static fn (string $period) => trim($period) !== ''));
            rsort($periods);

            return $periods;
        });
    }

    public function debounceSeconds(): int
    {
        return self::DEBOUNCE_SECONDS;
    }

    public function restore(?array $periods): void
    {
        if ($periods === []) {
            return;
        }

        $this->register($periods ?? [null]);
    }

    /** @return array{token: ?string, periods: ?array} */
    public function claim(): array
    {
        return $this->withLock(function (): array {
            $inflight = Cache::get(self::INFLIGHT_KEY, []);
            $dirty = Cache::get(self::DIRTY_PERIODS_KEY, []);
            $dirty = array_replace(
                is_array($inflight['dirty'] ?? null) ? $inflight['dirty'] : [],
                is_array($dirty) ? $dirty : []
            );
            if ($dirty === []) {
                Cache::forget(self::PENDING_DISPATCH_KEY);

                return ['token' => null, 'periods' => []];
            }

            $token = bin2hex(random_bytes(16));
            // Persist the claim before removing pending work, so an interrupted
            // worker leaves its targets available to the next queue attempt.
            Cache::put(self::INFLIGHT_KEY, ['token' => $token, 'dirty' => $dirty], now()->addSeconds(self::CACHE_TTL_SECONDS));
            Cache::forget(self::DIRTY_PERIODS_KEY);
            Cache::forget(self::PENDING_DISPATCH_KEY);
            $periods = array_keys($dirty);
            rsort($periods);

            return ['token' => $token, 'periods' => isset($dirty[self::ALL_PERIODS_TOKEN]) ? null : $periods];
        });
    }

    public function acknowledge(?string $token): void
    {
        if ($token === null) {
            return;
        }

        $this->withLock(function () use ($token): void {
            $inflight = Cache::get(self::INFLIGHT_KEY, []);
            if (($inflight['token'] ?? null) === $token) {
                Cache::forget(self::INFLIGHT_KEY);
            }
        });
    }

    private function withLock(callable $callback): mixed
    {
        $lock = Cache::lock(self::LOCK_KEY, 5);

        try {
            return $lock->block(3, $callback);
        } catch (\Throwable $e) {
            Log::debug('Dashboard Harian dirty period lock unavailable.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $callback();
        } finally {
            try {
                optional($lock)->release();
            } catch (\Throwable) {
            }
        }
    }
}
