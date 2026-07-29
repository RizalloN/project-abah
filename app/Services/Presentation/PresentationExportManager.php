<?php

namespace App\Services\Presentation;

use App\Jobs\GeneratePresentationPowerPointJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class PresentationExportManager
{
    private const CACHE_PREFIX = 'presentation:export:';
    private const RETENTION_HOURS = 24;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function queue(array $payload, array $options, int $userId): array
    {
        $this->cleanupExpired();
        $token = (string) Str::uuid();
        $input = [
            'payload' => $payload,
            'options' => $options,
            'user_id' => $userId,
        ];

        File::ensureDirectoryExists($this->directory());
        File::put(
            $this->inputPath($token),
            json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        $status = [
            'token' => $token,
            'user_id' => $userId,
            'status' => 'queued',
            'progress' => 5,
            'message' => 'Ekspor masuk antrean dan menunggu worker.',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'expires_at' => now()->addHours(self::RETENTION_HOURS)->toIso8601String(),
        ];
        $this->writeStatus($token, $status);

        GeneratePresentationPowerPointJob::dispatch($token);

        return $this->publicStatus($status);
    }

    /** @return array<string, mixed> */
    public function input(string $token): array
    {
        $this->assertToken($token);
        $path = $this->inputPath($token);
        if (!is_file($path)) {
            throw new RuntimeException('Data sumber ekspor tidak ditemukan atau sudah kedaluwarsa.');
        }

        $input = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($input)) {
            throw new RuntimeException('Data sumber ekspor tidak valid.');
        }

        return $input;
    }

    /** @return array<string, mixed>|null */
    public function statusForUser(string $token, int $userId): ?array
    {
        $status = $this->readStatus($token);
        if (!$status || (int) ($status['user_id'] ?? 0) !== $userId) {
            return null;
        }

        if (isset($status['expires_at']) && now()->greaterThan((string) $status['expires_at'])) {
            $this->deleteExport($token, $status);

            return null;
        }

        return $this->publicStatus($status);
    }

    /** @return array<string, mixed>|null */
    public function downloadForUser(string $token, int $userId): ?array
    {
        $status = $this->readStatus($token);
        if (
            !$status
            || (int) ($status['user_id'] ?? 0) !== $userId
            || ($status['status'] ?? '') !== 'completed'
            || !is_file((string) ($status['path'] ?? ''))
        ) {
            return null;
        }

        return [
            'path' => (string) $status['path'],
            'filename' => (string) ($status['filename'] ?? 'Performance-Review.pptx'),
            'slide_count' => (int) ($status['slide_count'] ?? 0),
            'renderer' => (string) ($status['renderer'] ?? 'native-openxml'),
        ];
    }

    public function markProcessing(string $token): void
    {
        $this->updateStatus($token, [
            'status' => 'processing',
            'progress' => 30,
            'message' => 'Menyusun data, grafik, narasi, dan layout slide.',
            'started_at' => now()->toIso8601String(),
        ]);
    }

    /** @param array<string, mixed> $result */
    public function markCompleted(string $token, array $result): void
    {
        $this->updateStatus($token, [
            'status' => 'completed',
            'progress' => 100,
            'message' => 'PowerPoint siap diunduh.',
            'completed_at' => now()->toIso8601String(),
            'path' => (string) ($result['path'] ?? ''),
            'filename' => (string) ($result['filename'] ?? 'Performance-Review.pptx'),
            'slide_count' => (int) ($result['slide_count'] ?? 0),
            'renderer' => (string) ($result['renderer'] ?? 'native-openxml'),
        ]);
        File::delete($this->inputPath($token));
    }

    public function markFailed(string $token, string $message): void
    {
        $this->updateStatus($token, [
            'status' => 'failed',
            'progress' => 100,
            'message' => $message,
            'failed_at' => now()->toIso8601String(),
        ]);
        File::delete($this->inputPath($token));
    }

    /** @param array<string, mixed> $changes */
    private function updateStatus(string $token, array $changes): void
    {
        $status = $this->readStatus($token);
        if (!$status) {
            throw new RuntimeException('Status ekspor tidak ditemukan.');
        }

        $this->writeStatus($token, array_merge($status, $changes, [
            'updated_at' => now()->toIso8601String(),
        ]));
    }

    /** @param array<string, mixed> $status */
    private function writeStatus(string $token, array $status): void
    {
        $this->assertToken($token);
        $encoded = json_encode($status, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        File::ensureDirectoryExists($this->directory());
        File::put($this->statusPath($token), $encoded);
        Cache::put(self::CACHE_PREFIX . $token, $status, now()->addHours(self::RETENTION_HOURS));
    }

    /** @return array<string, mixed>|null */
    private function readStatus(string $token): ?array
    {
        $this->assertToken($token);
        $cached = Cache::get(self::CACHE_PREFIX . $token);
        if (is_array($cached)) {
            return $cached;
        }

        $path = $this->statusPath($token);
        if (!is_file($path)) {
            return null;
        }

        try {
            $status = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($status)) {
            return null;
        }

        Cache::put(self::CACHE_PREFIX . $token, $status, now()->addHours(self::RETENTION_HOURS));

        return $status;
    }

    /** @param array<string, mixed> $status */
    private function publicStatus(array $status): array
    {
        return [
            'token' => (string) ($status['token'] ?? ''),
            'status' => (string) ($status['status'] ?? 'queued'),
            'progress' => (int) ($status['progress'] ?? 0),
            'message' => (string) ($status['message'] ?? ''),
            'filename' => isset($status['filename']) ? (string) $status['filename'] : null,
            'slide_count' => isset($status['slide_count']) ? (int) $status['slide_count'] : null,
            'renderer' => isset($status['renderer']) ? (string) $status['renderer'] : null,
            'updated_at' => (string) ($status['updated_at'] ?? ''),
        ];
    }

    private function cleanupExpired(): void
    {
        if (!is_dir($this->directory())) {
            return;
        }

        $cutoff = now()->subHours(self::RETENTION_HOURS)->getTimestamp();
        foreach (File::glob($this->directory() . DIRECTORY_SEPARATOR . '*.status.json') ?: [] as $path) {
            if ((int) @filemtime($path) >= $cutoff) {
                continue;
            }
            $token = str_replace('.status.json', '', basename($path));
            $status = $this->readStatus($token) ?? [];
            $this->deleteExport($token, $status);
        }
    }

    /** @param array<string, mixed> $status */
    private function deleteExport(string $token, array $status): void
    {
        File::delete([
            $this->inputPath($token),
            $this->statusPath($token),
            (string) ($status['path'] ?? ''),
        ]);
        Cache::forget(self::CACHE_PREFIX . $token);
    }

    private function assertToken(string $token): void
    {
        if (!Str::isUuid($token)) {
            throw new RuntimeException('Token ekspor tidak valid.');
        }
    }

    private function directory(): string
    {
        return storage_path('app/presentation-exports/jobs');
    }

    private function inputPath(string $token): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . $token . '.input.json';
    }

    private function statusPath(string $token): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . $token . '.status.json';
    }
}
