<?php

namespace App\Services\Import;

use Symfony\Component\Process\Process;

/** Runs short supervisor helpers, never the long-running workers themselves. */
class QueueSupervisorProcessRunner
{
    /** @return array{0: int, 1: string, 2: string} */
    public function run(array $command, float $timeout = 5.0): array
    {
        if ($timeout <= 0) {
            throw new \InvalidArgumentException('Supervisor helper timeout must be positive.');
        }
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->runWindowsHelper($command, $timeout);
        }

        $process = new Process($command, base_path(), null, null, $timeout);
        // Process drains stdout and stderr concurrently and enforces a deadline.
        // Let callers distinguish failed observation from an empty process list.
        $exitCode = $process->run();

        return [$exitCode, trim($process->getOutput()), trim($process->getErrorOutput())];
    }

    /** @return array{0: int, 1: string, 2: string} */
    private function runWindowsHelper(array $command, float $timeout): array
    {
        $command = $this->hiddenWindowsCommand($command);
        // Launch the helper directly: a shell wrapper requires process-tree killing
        // on timeout, which can itself block on Windows. Never terminate workers.
        $stdout = tmpfile();
        $stderr = tmpfile();
        $process = null;
        try {
            if ($stdout === false || $stderr === false) {
                throw new \RuntimeException('Cannot allocate supervisor output files.');
            }
            $process = proc_open($command, [
                0 => ['file', 'NUL', 'r'],
                1 => $stdout,
                2 => $stderr,
            ], $pipes, base_path(), null, ['bypass_shell' => true]);
            if (!is_resource($process)) {
                throw new \RuntimeException('Cannot launch supervisor helper.');
            }
            $deadline = microtime(true) + $timeout;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    rewind($stdout);
                    rewind($stderr);

                    return [(int) $status['exitcode'], trim(stream_get_contents($stdout)), trim(stream_get_contents($stderr))];
                }
                if (microtime(true) >= $deadline) {
                    proc_terminate($process);
                    throw new \RuntimeException('Supervisor helper timed out after ' . $timeout . ' seconds.');
                }
                usleep(20_000);
            } while (true);
        } finally {
            if (is_resource($process)) {
                proc_close($process);
            }
            foreach ([$stdout, $stderr] as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
    }

    private function hiddenWindowsCommand(array $command): array
    {
        $executable = strtolower(basename(str_replace('\\', '/', (string) ($command[0] ?? ''))));
        if (in_array($executable, ['powershell', 'powershell.exe', 'pwsh', 'pwsh.exe'], true)) {
            // Put host options before -Command/-EncodedCommand, never inside the script.
            array_splice($command, 1, 0, ['-WindowStyle', 'Hidden']);
        }

        return $command;
    }
}
