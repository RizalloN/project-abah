<?php

namespace App\Services\Import;

use App\Support\PhpCliBinary;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ImportBackgroundLauncher
{
    public function launch(int $jobId): bool
    {
        $phpBinary = PhpCliBinary::resolve();
        if ($jobId <= 0 || $phpBinary === null) {
            return false;
        }

        $logPrefix = storage_path('logs/import-force-start-'.$jobId.'-'.Str::uuid());
        $arguments = '"'.base_path('artisan').'" import:run-job '.$jobId;

        if (DIRECTORY_SEPARATOR === '\\') {
            $quote = static fn (string $value): string => "'".str_replace("'", "''", $value)."'";
            $script = '$ErrorActionPreference = "Stop"; '
                .'$child = Start-Process -FilePath '.$quote($phpBinary)
                .' -ArgumentList '.$quote($arguments)
                .' -WorkingDirectory '.$quote(base_path())
                .' -WindowStyle Hidden -PassThru'
                .' -RedirectStandardOutput '.$quote($logPrefix.'.out.log')
                .' -RedirectStandardError '.$quote($logPrefix.'.err.log').'; '
                .'$child.Id';
            $command = ['powershell.exe', '-NoProfile', '-NonInteractive', '-NoLogo', '-EncodedCommand',
                base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'))];
        } else {
            $command = ['sh', '-c', 'nohup '.escapeshellarg($phpBinary).' '.escapeshellarg(base_path('artisan'))
                .' import:run-job '.$jobId.' < /dev/null > '.escapeshellarg($logPrefix.'.out.log')
                .' 2> '.escapeshellarg($logPrefix.'.err.log').' & echo $!'];
        }

        try {
            $result = Process::path(base_path())->timeout(15)->run($command);
            $pid = trim($result->output());
            if (! $result->successful() || ! ctype_digit($pid) || (int) $pid <= 0) {
                Log::warning('Import background launcher did not return a process ID.', [
                    'job_id' => $jobId, 'exit_code' => $result->exitCode(), 'error' => $result->errorOutput(),
                ]);

                return false;
            }

            Log::info('Import background process started.', ['job_id' => $jobId, 'pid' => (int) $pid, 'log_prefix' => $logPrefix]);

            return true;
        } catch (ProcessTimedOutException $e) {
            // A child may already exist. Never trigger an inline replay when
            // the launch outcome is ambiguous.
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Import background launch failed.', ['job_id' => $jobId, 'message' => $e->getMessage()]);

            return false;
        }
    }
}
