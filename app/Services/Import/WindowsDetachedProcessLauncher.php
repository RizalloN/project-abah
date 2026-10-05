<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Log;

class WindowsDetachedProcessLauncher
{
    /** @param array<int, string> $command */
    public function launch(
        array $command,
        string $workingDirectory,
        string $outputLog,
        string $errorLog,
        string $launcherErrorLog
    ): ?int {
        if (PHP_OS_FAMILY !== 'Windows' || $command === []) {
            return null;
        }

        $launcherDirectory = $this->launcherDirectory();
        if (!is_dir($launcherDirectory) && !@mkdir($launcherDirectory, 0775, true) && !is_dir($launcherDirectory)) {
            $this->recordFailure($launcherErrorLog, 'Direktori launcher tidak dapat dibuat.');

            return null;
        }

        $taskName = $this->taskName();
        $batchPath = $launcherDirectory . DIRECTORY_SEPARATOR . 'queue-monitor-' . $this->projectKey() . '.cmd';
        $ackPath = $batchPath . '.started';
        @unlink($ackPath);

        $childCommand = implode(' ', array_map([$this, 'windowsCommandLineQuote'], $command));
        $batch = '@echo off' . "\r\n"
            . '>' . $this->windowsCommandLineQuote($ackPath) . ' echo TASK_STARTED' . "\r\n"
            . 'cd /D ' . $this->windowsCommandLineQuote($workingDirectory) . "\r\n"
            // A crashed monitor can leave its redirected handles inherited by a
            // child worker. Reopening the same file then fails before PHP starts.
            // Laravel still records failures in laravel.log; discard console IO.
            . $childCommand . ' >NUL 2>&1' . "\r\n";

        if (@file_put_contents($batchPath, $batch, LOCK_EX) === false) {
            $this->recordFailure($launcherErrorLog, 'File launcher tidak dapat dibuat.');

            return null;
        }

        // /IT tasks run in the desktop session. A console action would flash on
        // every scheduled trigger even when singleton leadership exits at once.
        $hiddenPath = $batchPath . '.vbs';
        $batchCommand = 'cmd.exe /D /C call ' . $this->windowsCommandLineQuote($batchPath);
        $hiddenScript = 'Option Explicit' . "\r\n"
            . 'Dim shell, result' . "\r\n"
            . 'Set shell = CreateObject("WScript.Shell")' . "\r\n"
            . 'result = shell.Run("' . str_replace('"', '""', $batchCommand) . '", 0, True)' . "\r\n"
            . 'WScript.Quit result' . "\r\n";
        if (@file_put_contents($hiddenPath, $hiddenScript, LOCK_EX) === false) {
            $this->recordFailure($launcherErrorLog, 'File launcher tersembunyi tidak dapat dibuat.');

            return null;
        }
        $taskCommand = 'wscript.exe //B //NoLogo ' . $this->windowsCommandLineQuote($hiddenPath);
        $interactiveUser = $this->interactiveRunAsUser();
        $createCommand = [
            'schtasks.exe', '/Create', '/TN', $taskName, '/TR', $taskCommand,
            '/SC', 'MINUTE', '/MO', '1',
        ];
        if ($interactiveUser !== null) {
            array_push($createCommand, '/RU', $interactiveUser, '/RL', 'HIGHEST', '/IT');
        } else {
            array_push($createCommand, '/RU', 'SYSTEM', '/RL', 'HIGHEST');
        }
        $createCommand[] = '/F';
        [$createExitCode, $createStdout, $createStderr] = $this->executeWindowsCommand($createCommand);

        if ($createExitCode !== 0) {
            $message = $this->commandFailureMessage('Pembuatan supervisor task gagal', $createStdout, $createStderr);
            $this->recordFailure($launcherErrorLog, $message, $createExitCode);

            return null;
        }

        $this->setTaskEnabled(true);
        [$runExitCode, $runStdout, $runStderr] = $this->executeWindowsCommand([
            'schtasks.exe', '/Run', '/TN', $taskName,
        ]);

        if ($runExitCode !== 0) {
            $message = $this->commandFailureMessage('Supervisor task tidak dapat dijalankan', $runStdout, $runStderr);
            $this->recordFailure($launcherErrorLog, $message, $runExitCode);

            return null;
        }

        if (!$this->waitForAcknowledgement($ackPath)) {
            [$queryExitCode, $queryStdout, $queryStderr] = $this->executeWindowsCommand([
                'schtasks.exe', '/Query', '/TN', $taskName, '/V', '/FO', 'LIST',
            ]);
            $taskDiagnostic = $this->commandFailureMessage(
                'Status supervisor task tidak tersedia',
                $queryStdout,
                $queryStderr
            );
            if ($interactiveUser !== null
                && $this->createInteractiveSupervisorTask($taskName, $taskCommand, $interactiveUser)
                && $this->runSupervisorTask($taskName)
                && $this->waitForAcknowledgement($ackPath)) {
                @unlink($ackPath);
                @file_put_contents($launcherErrorLog, '');

                Log::warning('Queue supervisor recovered with interactive Windows task fallback.', [
                    'task_name' => $taskName,
                    'run_as_user' => $interactiveUser,
                    'system_task_diagnostic' => $taskDiagnostic,
                ]);

                return 0;
            }

            $this->recordFailure(
                $launcherErrorLog,
                'Supervisor task diterima tetapi tidak memberikan acknowledgement proses. '
                    . "QueryExitCode={$queryExitCode}. {$taskDiagnostic}"
            );

            return null;
        }

        @unlink($ackPath);
        @file_put_contents($launcherErrorLog, '');

        // Task Scheduler tidak mengekspos PID proses anak. Nilai 0 berarti
        // supervisor benar-benar mulai dan akan mengulang otomatis bila mati.
        return 0;
    }

    public function setTaskEnabled(bool $enabled): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        [$exitCode] = $this->executeWindowsCommand([
            'schtasks.exe', '/Change', '/TN', $this->taskName(), $enabled ? '/ENABLE' : '/DISABLE',
        ]);

        return $exitCode === 0;
    }

    /**
     * @param array<int, string> $command
     * @return array{0: int, 1: string, 2: string}
     */
    protected function executeWindowsCommand(array $command): array
    {
        try {
            return app(QueueSupervisorProcessRunner::class)->run($command);
        } catch (\Throwable $e) {
            return [1, '', 'Proses supervisor gagal: ' . $e->getMessage()];
        }
    }

    protected function waitForAcknowledgement(string $ackPath): bool
    {
        $deadline = microtime(true) + 4;
        do {
            clearstatcache(true, $ackPath);
            if (is_file($ackPath)) {
                return true;
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        return false;
    }

    private function createInteractiveSupervisorTask(string $taskName, string $taskCommand, string $user): bool
    {
        [$exitCode] = $this->executeWindowsCommand([
            'schtasks.exe', '/Create', '/TN', $taskName, '/TR', $taskCommand,
            '/SC', 'MINUTE', '/MO', '1', '/RU', $user, '/RL', 'HIGHEST', '/IT', '/F',
        ]);

        return $exitCode === 0;
    }

    private function runSupervisorTask(string $taskName): bool
    {
        $this->setTaskEnabled(true);
        [$exitCode] = $this->executeWindowsCommand(['schtasks.exe', '/Run', '/TN', $taskName]);

        return $exitCode === 0;
    }

    private function interactiveRunAsUser(): ?string
    {
        $configured = trim((string) config('queue.supervisor_run_as_user', ''));
        if ($configured !== '') {
            return $configured;
        }

        [$exitCode, $stdout] = $this->executeWindowsCommand(['quser.exe']);
        if ($exitCode === 0) {
            foreach (preg_split('/\R+/', trim($stdout)) ?: [] as $line) {
                if (preg_match('/^>?\s*([^\s]+)\s+\S*\s+\d+\s+Active\b/i', trim($line), $matches) === 1) {
                    return trim($matches[1]);
                }
            }
        }

        $candidate = trim((string) get_current_user());
        if ($candidate === '' || in_array(strtolower($candidate), ['system', 'localsystem'], true)) {
            return null;
        }

        return $candidate;
    }

    private function projectKey(): string
    {
        return substr(sha1(strtolower(str_replace('\\', '/', realpath(base_path()) ?: base_path()))), 0, 12);
    }

    protected function launcherDirectory(): string
    {
        return storage_path('framework/queue-launchers');
    }

    private function taskName(): string
    {
        return 'ProjectABAH-QueueSupervisor-' . $this->projectKey();
    }

    private function commandFailureMessage(string $prefix, string $stdout, string $stderr): string
    {
        $detail = trim($stderr !== '' ? $stderr : $stdout);

        return $detail === '' ? $prefix . '.' : $prefix . ': ' . $detail;
    }

    private function recordFailure(string $launcherErrorLog, string $message, ?int $exitCode = null): void
    {
        @file_put_contents(
            $launcherErrorLog,
            '[' . now()->toDateTimeString() . "] {$message}" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
        Log::error('Windows detached process launcher failed.', [
            'exit_code' => $exitCode,
            'message' => $message,
            'launcher_error_log' => $launcherErrorLog,
        ]);
    }

    private function windowsCommandLineQuote(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
