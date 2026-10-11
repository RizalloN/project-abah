<?php

namespace App\Services\DatabaseBackup;

use RuntimeException;

/**
 * Runs artisan worker commands with a bounded pool. Each job owns a spec file
 * and reports through "<spec>.result.json"; output streams go to a log file,
 * so a chatty child can never block on a full pipe.
 */
class ParallelPartRunner
{
    private const POLL_MICROSECONDS = 200000;

    /**
     * @param  array<string, string>  $specPaths  job key => spec path (result at spec.result.json)
     * @param  callable(string, array<string, mixed>): void|null  $onFinished
     * @return array<string, array<string, mixed>> job key => decoded result
     */
    public function run(string $command, array $specPaths, int $workers, ?callable $onFinished = null): array
    {
        $workers = max(1, $workers);
        $queue = $specPaths;
        $running = [];
        $results = [];
        $failure = null;

        try {
            while ($queue !== [] || $running !== []) {
                while ($failure === null && $queue !== [] && count($running) < $workers) {
                    $key = array_key_first($queue);
                    $specPath = $queue[$key];
                    unset($queue[$key]);
                    $running[$key] = $this->start($command, $specPath);
                }

                foreach ($running as $key => $job) {
                    $status = proc_get_status($job['process']);
                    if ($status['running'] ?? false) {
                        continue;
                    }

                    proc_close($job['process']);
                    unset($running[$key]);
                    $result = json_decode((string) @file_get_contents($job['spec'].'.result.json'), true);
                    if (! is_array($result) || ($result['status'] ?? null) !== 'ok') {
                        $failure ??= sprintf(
                            'Worker %s gagal: %s %s',
                            $key,
                            is_array($result) ? (string) ($result['error'] ?? 'tanpa pesan') : 'hasil tidak tersedia',
                            is_array($result) ? (string) ($result['stderr'] ?? '') : $this->tail($job['log'])
                        );

                        continue;
                    }

                    @unlink($job['log']);
                    $results[$key] = $result;
                    if ($onFinished !== null) {
                        $onFinished($key, $result);
                    }
                }

                if ($failure !== null && $running === []) {
                    break;
                }
                if ($running !== []) {
                    usleep(self::POLL_MICROSECONDS);
                }
            }
        } finally {
            foreach ($running as $job) {
                @proc_terminate($job['process']);
                @proc_close($job['process']);
            }
        }

        if ($failure !== null) {
            throw new RuntimeException(trim($failure));
        }

        return $results;
    }

    /** @return array{process: resource, spec: string, log: string} */
    private function start(string $command, string $specPath): array
    {
        $log = $specPath.'.log';
        $process = @proc_open(
            [PHP_BINARY, base_path('artisan'), $command, $specPath],
            [
                0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
                1 => ['file', $log, 'ab'],
                2 => ['file', $log, 'ab'],
            ],
            $pipes,
            base_path(),
            null,
            ['bypass_shell' => true]
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Gagal memulai worker '.$command.'.');
        }

        return ['process' => $process, 'spec' => $specPath, 'log' => $log];
    }

    private function tail(string $path): string
    {
        if (! is_file($path)) {
            return '';
        }

        $content = (string) file_get_contents($path, false, null, max(0, (int) filesize($path) - 2000));

        return substr(trim(preg_replace('/\s+/', ' ', $content) ?? $content), 0, 600);
    }
}
