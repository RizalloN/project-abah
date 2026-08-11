<?php

namespace App\Services\DatabaseBackup;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class CompressedDatabaseDumpRunner
{
    private const READ_BUFFER_BYTES = 1048576;

    private const SQL_PREFIX_BYTES = 65536;

    /**
     * Stream a database dump directly into a gzip file.
     *
     * @param  array<int, string>  $command
     * @return array{
     *     uncompressed_bytes: int,
     *     uncompressed_sha256: string,
     *     sql_prefix: string,
     *     exit_code: int
     * }
     */
    public function run(
        array $command,
        string $gzipPath,
        string $stderrPath,
        int $compressionLevel = 4
    ): array {
        $this->validateArguments($command, $gzipPath, $stderrPath, $compressionLevel);

        $gzipHandle = null;
        $process = null;
        $pipes = [];

        try {
            $gzipHandle = @gzopen($gzipPath, 'wb'.$compressionLevel);
            if (! is_resource($gzipHandle)) {
                throw new RuntimeException('Gagal membuka file gzip untuk database dump.');
            }

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['file', $stderrPath, 'wb'],
            ];

            try {
                $process = @proc_open(
                    $command,
                    $descriptors,
                    $pipes,
                    null,
                    null,
                    ['bypass_shell' => true]
                );
            } catch (Throwable) {
                throw new RuntimeException('Gagal memulai proses database dump.');
            }

            if (! is_resource($process)) {
                throw new RuntimeException('Gagal memulai proses database dump.');
            }

            if (isset($pipes[0]) && is_resource($pipes[0])) {
                fclose($pipes[0]);
                unset($pipes[0]);
            }

            if (! isset($pipes[1]) || ! is_resource($pipes[1])) {
                throw new RuntimeException('Output proses database dump tidak tersedia.');
            }

            $hashContext = hash_init('sha256');
            $uncompressedBytes = 0;
            $sqlPrefix = '';

            while (! feof($pipes[1])) {
                $chunk = fread($pipes[1], self::READ_BUFFER_BYTES);
                if ($chunk === false) {
                    throw new RuntimeException('Gagal membaca output proses database dump.');
                }

                if ($chunk === '') {
                    continue;
                }

                $chunkBytes = strlen($chunk);
                $uncompressedBytes += $chunkBytes;
                hash_update($hashContext, $chunk);

                $prefixBytesRemaining = self::SQL_PREFIX_BYTES - strlen($sqlPrefix);
                if ($prefixBytesRemaining > 0) {
                    $sqlPrefix .= substr($chunk, 0, $prefixBytesRemaining);
                }

                $this->writeCompressedChunk($gzipHandle, $chunk);
            }

            fclose($pipes[1]);
            unset($pipes[1]);

            if (! @gzclose($gzipHandle)) {
                $gzipHandle = null;
                throw new RuntimeException('Gagal menyelesaikan file gzip database dump.');
            }
            $gzipHandle = null;

            $exitCode = $this->closeProcess($process);
            $process = null;

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    'Proses database dump gagal dengan exit code '.$exitCode.'. Periksa file stderr.'
                );
            }

            return [
                'uncompressed_bytes' => $uncompressedBytes,
                'uncompressed_sha256' => hash_final($hashContext),
                'sql_prefix' => $sqlPrefix,
                'exit_code' => $exitCode,
            ];
        } catch (Throwable $exception) {
            $this->cleanupFailedRun($process, $pipes, $gzipHandle, $gzipPath);

            if ($exception instanceof RuntimeException || $exception instanceof InvalidArgumentException) {
                throw $exception;
            }

            throw new RuntimeException('Proses database dump gagal.');
        }
    }

    /**
     * @param  array<int, string>  $command
     */
    private function validateArguments(
        array $command,
        string $gzipPath,
        string $stderrPath,
        int $compressionLevel
    ): void {
        if ($command === [] || ! array_is_list($command)) {
            throw new InvalidArgumentException('Command database dump harus berupa daftar argumen yang valid.');
        }

        foreach ($command as $argument) {
            if (! is_string($argument) || $argument === '' || str_contains($argument, "\0")) {
                throw new InvalidArgumentException('Command database dump berisi argumen yang tidak valid.');
            }
        }

        if ($gzipPath === '' || str_contains($gzipPath, "\0")) {
            throw new InvalidArgumentException('Path file gzip tidak valid.');
        }

        if ($stderrPath === '' || str_contains($stderrPath, "\0")) {
            throw new InvalidArgumentException('Path file stderr tidak valid.');
        }

        if ($this->normalizePath($gzipPath) === $this->normalizePath($stderrPath)) {
            throw new InvalidArgumentException('Path file gzip dan stderr harus berbeda.');
        }

        if ($compressionLevel < 0 || $compressionLevel > 9) {
            throw new InvalidArgumentException('Level kompresi gzip harus berada di antara 0 dan 9.');
        }
    }

    /**
     * @param  resource  $gzipHandle
     */
    private function writeCompressedChunk($gzipHandle, string $chunk): void
    {
        $chunkBytes = strlen($chunk);
        $offset = 0;

        while ($offset < $chunkBytes) {
            $written = @gzwrite($gzipHandle, substr($chunk, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Gagal menulis output database dump ke file gzip.');
            }

            $offset += $written;
        }
    }

    /**
     * Capture the status exit code before proc_close because Windows can return
     * -1 from proc_close after proc_get_status has observed process completion.
     *
     * @param  resource  $process
     */
    private function closeProcess($process): int
    {
        $observedExitCode = null;

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $status = @proc_get_status($process);
            if (! is_array($status)) {
                break;
            }

            if (! ($status['running'] ?? false)) {
                $statusExitCode = $status['exitcode'] ?? -1;
                if (is_int($statusExitCode) && $statusExitCode >= 0) {
                    $observedExitCode = $statusExitCode;
                }
                break;
            }

            usleep(10000);
        }

        $closeExitCode = @proc_close($process);

        if ($observedExitCode !== null) {
            return $observedExitCode;
        }

        if (is_int($closeExitCode) && $closeExitCode >= 0) {
            return $closeExitCode;
        }

        return -1;
    }

    /**
     * @param  resource|null  $process
     * @param  array<int, resource>  $pipes
     * @param  resource|null  $gzipHandle
     */
    private function cleanupFailedRun($process, array $pipes, $gzipHandle, string $gzipPath): void
    {
        if (is_resource($process)) {
            $status = @proc_get_status($process);
            if (is_array($status) && ($status['running'] ?? false)) {
                @proc_terminate($process);
            }
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }

        if (is_resource($process)) {
            @proc_close($process);
        }

        if (is_resource($gzipHandle)) {
            @gzclose($gzipHandle);
        }

        if (is_file($gzipPath)) {
            @unlink($gzipPath);
        }
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        return PHP_OS_FAMILY === 'Windows'
            ? strtolower($normalized)
            : $normalized;
    }
}
