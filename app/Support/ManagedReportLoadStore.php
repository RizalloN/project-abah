<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ManagedReportLoadStore
{
    private const ACTIVE_IDS_KEY = 'report_management:load:active_ids';
    private const STATE_PREFIX = 'report_management:load:state:';
    private const TTL_MINUTES = 90;

    public static function ttl()
    {
        return now()->addMinutes(self::TTL_MINUTES);
    }

    public static function stateKey(string $loadId): string
    {
        return self::STATE_PREFIX . trim($loadId);
    }

    public static function createInitialState(string $loadId, int $reportId, array $options, ?string $source = null): array
    {
        $timestamp = now()->toIso8601String();

        return [
            'load_id' => $loadId,
            'report_id' => $reportId,
            'options' => self::normalizeOptions($options),
            'source' => $source,
            'status' => 'queued',
            'stage' => 'queued',
            'queued' => true,
            'message' => 'Load data report management masuk antrean dan progress akan tampil realtime.',
            'progress_percent' => 0,
            'completed_units' => 0,
            'total_units' => 4,
            'result' => null,
            'error' => null,
            'started_at' => null,
            'finished_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    public static function putState(array $state): array
    {
        $normalized = self::normalizeState($state, true);
        Cache::put(self::stateKey((string) $normalized['load_id']), $normalized, self::ttl());
        self::syncActiveRegistry($normalized);

        return $normalized;
    }

    public static function getState(string $loadId): ?array
    {
        $state = Cache::get(self::stateKey($loadId));

        return is_array($state) ? self::normalizeState($state, false) : null;
    }

    public static function forgetState(string $loadId): void
    {
        Cache::forget(self::stateKey($loadId));
        self::removeActiveId($loadId);
    }

    public static function activeIds(): array
    {
        $value = Cache::get(self::ACTIVE_IDS_KEY);
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($id): string => trim((string) $id),
            $value
        ), static fn (string $id): bool => $id !== ''));
    }

    public static function normalizeState(array $state, bool $touchUpdatedAt = false): array
    {
        $timestamp = now()->toIso8601String();

        $state['load_id'] = trim((string) ($state['load_id'] ?? ''));
        $state['report_id'] = max(0, (int) ($state['report_id'] ?? 0));
        $state['options'] = self::normalizeOptions((array) ($state['options'] ?? []));
        $state['source'] = $state['source'] ?? null;
        $state['status'] = (string) ($state['status'] ?? 'queued');
        $state['stage'] = (string) ($state['stage'] ?? 'queued');
        $state['queued'] = (bool) ($state['queued'] ?? false);
        $state['message'] = (string) ($state['message'] ?? '');
        $state['progress_percent'] = max(0, min(100, (int) round((float) ($state['progress_percent'] ?? 0))));
        $state['completed_units'] = max(0, (int) ($state['completed_units'] ?? 0));
        $state['total_units'] = max(1, (int) ($state['total_units'] ?? 4));
        $state['result'] = is_array($state['result'] ?? null) ? $state['result'] : null;
        $state['error'] = $state['error'] ? (string) $state['error'] : null;
        $state['started_at'] = $state['started_at'] ?? null;
        $state['finished_at'] = $state['finished_at'] ?? null;
        $state['created_at'] = $state['created_at'] ?? $timestamp;
        $state['updated_at'] = $touchUpdatedAt
            ? $timestamp
            : ($state['updated_at'] ?? $timestamp);

        return $state;
    }

    private static function normalizeOptions(array $options): array
    {
        return [
            'max_rows' => max(100, min(20000, (int) ($options['max_rows'] ?? 5000))),
            'page' => max(1, (int) ($options['page'] ?? 1)),
            'per_page' => max(1, min(24, (int) ($options['per_page'] ?? 8))),
        ];
    }

    private static function syncActiveRegistry(array $state): void
    {
        $loadId = trim((string) ($state['load_id'] ?? ''));
        if ($loadId === '') {
            return;
        }

        $terminal = in_array(strtolower((string) ($state['status'] ?? '')), ['completed', 'failed'], true);
        if ($terminal) {
            self::removeActiveId($loadId);
            return;
        }

        $ids = self::activeIds();
        if (!in_array($loadId, $ids, true)) {
            $ids[] = $loadId;
        }

        Cache::put(self::ACTIVE_IDS_KEY, array_values($ids), self::ttl());
    }

    private static function removeActiveId(string $loadId): void
    {
        $normalizedId = trim($loadId);
        if ($normalizedId === '') {
            return;
        }

        $ids = array_values(array_filter(self::activeIds(), static fn (string $id): bool => $id !== $normalizedId));
        if ($ids === []) {
            Cache::forget(self::ACTIVE_IDS_KEY);
            return;
        }

        Cache::put(self::ACTIVE_IDS_KEY, $ids, self::ttl());
    }
}
