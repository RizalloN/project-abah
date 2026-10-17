<?php

namespace App\Support;

use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Locates the PHP CLI executable from any SAPI. Under Apache (mod_php)
 * PHP_BINARY is httpd.exe and PHP_BINDIR is a compile-time path, and the
 * LocalSystem PATH usually has no PHP, so derive it from the running install.
 */
final class PhpCliBinary
{
    /** Absolute path to php(.exe), or null when no CLI binary exists. */
    public static function resolve(): ?string
    {
        return self::firstUsable(self::candidates());
    }

    /** @return array<int, string|false|null> */
    public static function candidates(): array
    {
        $runtimeConfig = trim((string) (getenv('PHPRC') ?: ($_SERVER['PHPRC'] ?? '')));
        $runtimeDirectory = $runtimeConfig !== ''
            ? (is_dir($runtimeConfig) ? $runtimeConfig : dirname($runtimeConfig))
            : '';
        $loadedIni = php_ini_loaded_file();

        return [
            PHP_BINARY,
            $runtimeDirectory !== '' ? $runtimeDirectory.DIRECTORY_SEPARATOR.'php.exe' : null,
            $loadedIni ? dirname($loadedIni).DIRECTORY_SEPARATOR.'php.exe' : null,
            PHP_BINDIR.DIRECTORY_SEPARATOR.'php.exe',
            PHP_BINDIR.DIRECTORY_SEPARATOR.'php',
            // XAMPP layout: <xampp>\apache\bin\httpd.exe and <xampp>\htdocs\<project>.
            dirname((string) PHP_BINARY, 3).DIRECTORY_SEPARATOR.'php'.DIRECTORY_SEPARATOR.'php.exe',
            dirname(base_path(), 2).DIRECTORY_SEPARATOR.'php'.DIRECTORY_SEPARATOR.'php.exe',
            (new PhpExecutableFinder)->find(false),
        ];
    }

    /**
     * First candidate that is a PHP CLI binary (never httpd.exe or php-cgi).
     * A bare "php" is accepted as-is so callers may opt into a PATH lookup.
     *
     * @param  array<int, mixed>  $candidates
     */
    public static function firstUsable(array $candidates): ?string
    {
        foreach (array_unique(array_filter($candidates, static fn ($candidate): bool => is_string($candidate) && trim($candidate) !== '')) as $candidate) {
            $candidate = trim($candidate);
            if (! in_array(strtolower(basename(str_replace('\\', '/', $candidate))), ['php.exe', 'php'], true)) {
                continue;
            }
            if ($candidate === 'php') {
                return $candidate;
            }
            if (is_file($candidate)) {
                // "\xampp\php\php.exe" only resolves on Apache's current drive; a
                // Task Scheduler process may start elsewhere, so return it absolute.
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }
}
