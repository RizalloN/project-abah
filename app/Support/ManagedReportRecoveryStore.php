<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ManagedReportRecoveryStore
{
    private const ACTIVE_IDS_KEY = 'report_management:recovery:active_ids';
    private const STATE_PREFIX = 'report_management:recovery:state:';
    private const TTL_MINUTES = 360;

    public static function ttl()
    {
        return now()->addMinutes(self::TTL_MINUTES);
    }

    public static function stateKey(string $recoveryId): string
    {
        return self::STATE_PREFIX . trim($recoveryId);
    }

    public static function createInitialState(string $recoveryId, int $reportId, string $backupPath, ?string $source = null): array
    {
        $timestamp = now()->toIso8601String();

        return [
            'recovery_id' => $recoveryId,
            'report_id' => $reportId,
            'backup_path' => trim($backupPath),
            'source' => $source,
            'status' => 'queued',
            'stage' => 'queued',
            'queued' => true,
            'message' => 'Recovery backup report masuk antrean dan progress akan tampil realtime.',
            'progress_percent' => 0,
            'completed_units' => 0,
            'total_units' => 6,
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
        Cache::put(self::stateKey((string) $normalized['recovery_id']), $normalized, self::ttl());
        self::syncActiveRegistry($normalized);

        return $normalized;
    }

    public static function getState(string $recoveryId): ?array
    {
        $state = Cache::get(self::stateKey($recoveryId));

        return is_array($state) ? self::normalizeState($state, false) : null;
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

        $state['recovery_id'] = trim((string) ($state['recovery_id'] ?? ''));
        $state['report_id'] = max(0, (int) ($state['report_id'] ?? 0));
        $state['backup_path'] = trim((string) ($state['backup_path'] ?? ''));
        $state['source'] = $state['source'] ?? null;
        $state['status'] = (string) ($state['status'] ?? 'queued');
        $state['stage'] = (string) ($state['stage'] ?? 'queued');
        $state['queued'] = (bool) ($state['queued'] ?? false);
        $state['message'] = (string) ($state['message'] ?? '');
        $state['progress_percent'] = max(0, min(100, (int) round((float) ($state['progress_percent'] ?? 0))));
        $state['completed_units'] = max(0, (int) ($state['completed_units'] ?? 0));
        $state['total_units'] = max(1, (int) ($state['total_units'] ?? 6));
        $state['result'] = is_array($state['result'] ?? null) ? $state['result'] : null;
        $state['error'] = $state['error'] ? (string) $state['error'] : null;
        $state['started_at'] = $state['started_at'] ?? null;
        $state['finished_at'] = $state['finished_at'] ?? null;
        $state['created_at'] = $state['created_at'] ?? $timestamp;
        $state['updated_at'] = $touchUpdatedAt ? $timestamp : ($state['updated_at'] ?? $timestamp);

        return $state;
    }

    private static function syncActiveRegistry(array $state): void
    {
        $recoveryId = trim((string) ($state['recovery_id'] ?? ''));
        if ($recoveryId === '') {
            return;
        }

        $terminal = in_array(strtolower((string) ($state['status'] ?? '')), ['completed', 'failed'], true);
        if ($terminal) {
            self::removeActiveId($recoveryId);
            return;
        }

        $ids = self::activeIds();
        if (!in_array($recoveryId, $ids, true)) {
            $ids[] = $recoveryId;
        }

        Cache::put(self::ACTIVE_IDS_KEY, array_values($ids), self::ttl());
    }

    private static function removeActiveId(string $recoveryId): void
    {
        $normalizedId = trim($recoveryId);
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
