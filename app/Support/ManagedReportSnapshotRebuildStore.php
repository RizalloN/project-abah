<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ManagedReportSnapshotRebuildStore
{
    public const PENDING_KEY = 'report_management:rebuild_snapshots:pending';
    public const ACTIVE_KEY = 'report_management:rebuild_snapshots:active_id';
    private const STATE_PREFIX = 'report_management:rebuild_snapshots:state:';
    private const TTL_MINUTES = 360;

    public static function ttl()
    {
        return now()->addMinutes(self::TTL_MINUTES);
    }

    public static function stateKey(string $rebuildId): string
    {
        return self::STATE_PREFIX . trim($rebuildId);
    }

    public static function createInitialState(string $rebuildId, bool $forceRebuild, ?string $source = null): array
    {
        $timestamp = now()->toIso8601String();

        return [
            'rebuild_id' => $rebuildId,
            'status' => 'queued',
            'stage' => 'queued',
            'queued' => true,
            'force_rebuild' => $forceRebuild,
            'source' => $source,
            'message' => $forceRebuild
                ? 'Rebuild snapshot seluruh report dari awal sedang menunggu worker.'
                : 'Refresh snapshot seluruh report sedang menunggu worker.',
            'progress_percent' => 0,
            'completed_units' => 0,
            'total_units' => 1,
            'build_units' => 0,
            'current_report_key' => null,
            'current_report_label' => null,
            'current_period' => null,
            'report_completed_units' => 0,
            'report_total_units' => 0,
            'reports' => [],
            'results' => [],
            'started_at' => null,
            'finished_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    public static function putState(array $state): array
    {
        $normalized = self::normalizeState($state, true);
        Cache::put(self::stateKey((string) $normalized['rebuild_id']), $normalized, self::ttl());

        return $normalized;
    }

    public static function getState(string $rebuildId): ?array
    {
        $state = Cache::get(self::stateKey($rebuildId));

        return is_array($state) ? self::normalizeState($state, false) : null;
    }

    public static function forgetState(string $rebuildId): void
    {
        Cache::forget(self::stateKey($rebuildId));
    }

    public static function setActiveRebuildId(string $rebuildId): void
    {
        Cache::put(self::ACTIVE_KEY, $rebuildId, self::ttl());
    }

    public static function getActiveRebuildId(): ?string
    {
        $value = Cache::get(self::ACTIVE_KEY);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public static function forgetActiveRebuildId(): void
    {
        Cache::forget(self::ACTIVE_KEY);
    }

    public static function normalizeState(array $state, bool $touchUpdatedAt = false): array
    {
        $timestamp = now()->toIso8601String();
        $state['rebuild_id'] = (string) ($state['rebuild_id'] ?? '');
        $state['status'] = (string) ($state['status'] ?? 'queued');
        $state['stage'] = (string) ($state['stage'] ?? 'queued');
        $state['queued'] = (bool) ($state['queued'] ?? false);
        $state['force_rebuild'] = (bool) ($state['force_rebuild'] ?? false);
        $state['source'] = $state['source'] ?? null;
        $state['message'] = (string) ($state['message'] ?? '');
        $state['progress_percent'] = max(0, min(100, (int) round((float) ($state['progress_percent'] ?? 0))));
        $state['completed_units'] = max(0, (int) ($state['completed_units'] ?? 0));
        $state['total_units'] = max(1, (int) ($state['total_units'] ?? 1));
        $state['build_units'] = max(0, (int) ($state['build_units'] ?? 0));
        $state['current_report_key'] = $state['current_report_key'] ?: null;
        $state['current_report_label'] = $state['current_report_label'] ?: null;
        $state['current_period'] = $state['current_period'] ?: null;
        $state['report_completed_units'] = max(0, (int) ($state['report_completed_units'] ?? 0));
        $state['report_total_units'] = max(0, (int) ($state['report_total_units'] ?? 0));
        $state['reports'] = is_array($state['reports'] ?? null) ? array_values($state['reports']) : [];
        $state['results'] = is_array($state['results'] ?? null) ? $state['results'] : [];
        $state['started_at'] = $state['started_at'] ?? null;
        $state['finished_at'] = $state['finished_at'] ?? null;
        $state['created_at'] = $state['created_at'] ?? $timestamp;
        $state['updated_at'] = $touchUpdatedAt
            ? $timestamp
            : ($state['updated_at'] ?? $timestamp);

        return $state;
    }
}
