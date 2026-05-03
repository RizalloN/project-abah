<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class DatabaseBackupStatusStore
{
    private const STATUS_DIRECTORY = 'framework/backup-status';
    private const RUNNING_STATUSES = ['starting', 'processing', 'stalled'];

    /**
     * @param array<string, mixed> $payload
     */
    public static function put(string $backupId, array $payload): void
    {
        $payload['backup_id'] = $backupId;
        $payload['updated_at'] = $payload['updated_at'] ?? now()->timestamp;

        Cache::put(self::cacheKey($backupId), $payload, now()->addHours(6));

        $directory = self::directory();
        if (!is_dir($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = self::path($backupId);
        $temporaryPath = $path . '.tmp';
        File::put($temporaryPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), true);
        @rename($temporaryPath, $path);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $backupId): ?array
    {
        $cached = Cache::get(self::cacheKey($backupId));
        if (is_array($cached)) {
            return $cached;
        }

        $path = self::path($backupId);
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function path(string $backupId): string
    {
        $safeBackupId = preg_replace('/[^A-Za-z0-9_-]+/', '_', $backupId) ?: 'backup';

        return self::directory() . DIRECTORY_SEPARATOR . $safeBackupId . '.json';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function latestRunning(int $freshSeconds = 900): ?array
    {
        $directory = self::directory();
        if (!is_dir($directory)) {
            return null;
        }

        $latest = null;
        foreach (File::files($directory) as $file) {
            $decoded = json_decode((string) file_get_contents($file->getPathname()), true);
            if (!is_array($decoded) || !in_array($decoded['status'] ?? null, self::RUNNING_STATUSES, true)) {
                continue;
            }

            $updatedAt = (int) ($decoded['updated_at'] ?? 0);
            $backupFile = is_string($decoded['backup_file'] ?? null) ? $decoded['backup_file'] : null;
            $fileModifiedAt = $backupFile && is_file($backupFile) ? (int) filemtime($backupFile) : 0;
            $freshAt = max($updatedAt, $fileModifiedAt);

            if ($freshAt <= 0 || now()->timestamp - $freshAt > $freshSeconds) {
                continue;
            }

            if ($latest === null || $freshAt > (int) ($latest['_fresh_at'] ?? 0)) {
                $decoded['_fresh_at'] = $freshAt;
                $latest = $decoded;
            }
        }

        if ($latest !== null) {
            unset($latest['_fresh_at']);
        }

        return $latest;
    }

    private static function directory(): string
    {
        return storage_path(self::STATUS_DIRECTORY);
    }

    private static function cacheKey(string $backupId): string
    {
        return "backup_progress:{$backupId}";
    }
}
