<?php

namespace App\Services\Import\SchemaDrift;

use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Encrypted, expiring reference to an uploaded import source. The browser only
 * ever sees this opaque token, never a filesystem path.
 */
final class SchemaDriftSourceToken
{
    private const TTL_SECONDS = 6 * 3600;

    public static function issue(string $table, string $absolutePath, ?string $sourceName = null): string
    {
        return Crypt::encryptString(json_encode([
            'table' => strtolower(trim($table)),
            'path' => $absolutePath,
            'name' => $sourceName ?? basename($absolutePath),
            'exp' => time() + self::TTL_SECONDS,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{table: string, path: string, name: string}|null
     */
    public static function read(?string $token): ?array
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 4, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (!is_array($payload) || (int) ($payload['exp'] ?? 0) < time()
            || !is_string($payload['table'] ?? null) || !is_string($payload['path'] ?? null)) {
            return null;
        }

        return [
            'table' => $payload['table'],
            'path' => $payload['path'],
            'name' => (string) ($payload['name'] ?? basename($payload['path'])),
        ];
    }
}
