<?php

namespace App\Services;

use App\Services\Import\QueueSupervisorProcessRunner;
use App\Support\PhpCliBinary;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class SchedulerControlService
{
    public function __construct(private readonly QueueSupervisorProcessRunner $runner) {}

    public function inspect(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget($this->cacheKey());
        }

        return Cache::remember($this->cacheKey(), 10, function (): array {
            try {
                return $this->invoke('Status');
            } catch (Throwable $e) {
                return ['scheduler' => ['available' => false, 'message' => $e->getMessage()], 'infrastructure' => []];
            }
        });
    }

    public function control(string $action, ?string $service = null): array
    {
        $command = $service === 'backup' ? 'RunBackup' : ($service !== null ? 'StartService' : match ($action) {
            'start' => 'StartScheduler',
            'stop' => 'StopScheduler',
            'restart' => 'RestartScheduler',
            'run' => 'RunScheduler',
            default => throw new \InvalidArgumentException('Aksi scheduler tidak valid.'),
        });
        if ($service === 'backup' && $action !== 'run') {
            throw new \InvalidArgumentException('Backup hanya dapat dijalankan dari panel.');
        }
        if ($service !== null && $service !== 'backup' && ($action !== 'start' || ! in_array($service, ['apache', 'mysql', 'tunnel'], true))) {
            throw new \InvalidArgumentException('Aksi layanan tidak valid.');
        }

        $lock = Cache::lock($this->cacheKey().':control', 60);
        if (! $lock->get()) {
            return ['success' => false, 'message' => 'Perubahan layanan lain masih diproses. Tunggu lalu periksa status.'];
        }

        try {
            $result = $this->invoke($command, $service === 'backup' ? null : $service);
            if (($result['success'] ?? false) && $service === null && $action !== 'run') {
                $this->writeJson($this->path('control.json'), ['enabled' => $action !== 'stop', 'requested_at' => time()]);
            }

            return $result;
        } finally {
            Cache::forget($this->cacheKey());
            $lock->release();
        }
    }

    public function status(?array $inspection = null): array
    {
        $inspection ??= $this->inspect();
        $native = $inspection['scheduler'] ?? [];
        $control = $this->readJson($this->path('control.json'));
        $run = $this->readJson($this->path('run.json'));
        $heartbeat = $this->readJson(storage_path('app/health/scheduler-heartbeat.json'));
        $boot = (int) ($inspection['boot_timestamp'] ?? 0);
        $timestamps = array_filter([
            (int) Cache::get('system:scheduler:last_heartbeat', 0),
            (int) ($heartbeat['timestamp'] ?? 0),
        ], fn (int $value): bool => $value > 0 && $value >= $boot && $value <= time() + 5);
        $timestamp = $timestamps === [] ? 0 : max($timestamps);
        $age = $timestamp > 0 && $timestamp <= time() + 5 ? max(0, time() - $timestamp) : null;
        $available = (bool) ($native['available'] ?? false);
        $registered = (bool) ($native['registered'] ?? false);
        $taskEnabled = (bool) ($native['enabled'] ?? false);
        $legacyCount = (int) ($native['legacy_count'] ?? 0);
        $enabled = $taskEnabled || $legacyCount > 0;
        $fresh = $age !== null && $age <= 120;
        $starting = $enabled && ! $fresh && time() - (int) ($control['requested_at'] ?? 0) <= 120;
        $stopping = ! $enabled && ($native['state'] ?? '') === 'running';
        $failed = $taskEnabled && isset($native['last_result'])
            && ! in_array((int) $native['last_result'], [0, 267009, 267011], true)
            && ($native['state'] ?? '') !== 'running';
        $state = ! $available || ($native['conflict'] ?? false) ? 'error'
            : ($failed ? 'failed' : ($stopping ? 'stopping' : (! $enabled ? 'stopped' : ($fresh ? ($legacyCount > 0 ? 'external' : 'active') : ($starting ? 'starting' : 'inactive')))));

        return [
            'state' => $state,
            'state_label' => match ($state) {
                'active' => 'Scheduler Berjalan',
                'external' => 'Scheduler Existing Berjalan',
                'starting' => 'Menunggu Eksekusi Pertama',
                'stopping' => 'Scheduler Menyelesaikan Eksekusi',
                'stopped' => 'Scheduler Tidak Aktif',
                'error' => 'Status Scheduler Belum Terverifikasi',
                'failed' => 'Eksekusi Scheduler Gagal',
                default => 'Heartbeat Scheduler Terlambat',
            },
            'tone' => match ($state) {
                'active', 'external' => 'success',
                'starting' => 'info',
                'stopped' => 'secondary',
                default => 'warning',
            },
            'message' => match ($state) {
                'active' => 'Jadwal otomatis berjalan setiap menit. Tugas pemeliharaan mengikuti jadwal masing-masing.',
                'external' => 'Scheduler proyek yang sudah berjalan digunakan; proses kedua tidak dibuat.',
                'starting' => 'Aktivasi diterima. Tunggu heartbeat pertama, maksimal sekitar dua menit.',
                'stopped' => 'Aktifkan scheduler untuk menjalankan jadwal otomatis dan pemeriksaan worker.',
                'stopping' => 'Pemicu berikutnya sudah dinonaktifkan. Eksekusi yang sedang berjalan dibiarkan selesai.',
                'inactive' => 'Task aktif tetapi heartbeat belum segar. Periksa hasil eksekusi dan log scheduler.',
                'failed' => 'Eksekusi terakhir gagal dengan kode '.($native['last_result'] ?? '-').'. Periksa log atau mulai ulang scheduler.',
                default => (string) ($native['message'] ?? 'Status Windows tidak dapat dibaca. Periksa izin akun layanan web.'),
            },
            'enabled' => $enabled,
            'available' => $available,
            'heartbeat_at' => $age !== null ? now()->setTimestamp($timestamp)->toIso8601String() : null,
            'heartbeat_age_seconds' => $age,
            'task_registered' => $available ? $registered : null,
            'task_enabled' => $available ? $taskEnabled : null,
            'startup_mode' => $native['startup_mode'] ?? null,
            'has_boot_trigger' => (bool) ($native['has_boot_trigger'] ?? false),
            'has_repeating_trigger' => (bool) ($native['has_repeating_trigger'] ?? false),
            'execution_time_limit' => $native['execution_time_limit'] ?? null,
            'needs_upgrade' => (bool) ($native['needs_upgrade'] ?? false),
            'legacy_count' => $legacyCount,
            'last_run_at' => $native['last_run_at'] ?? null,
            'next_run_at' => $native['next_run_at'] ?? null,
            'last_result' => $native['last_result'] ?? null,
            'last_execution' => $run,
            'log_tail' => $this->logTail(),
        ];
    }

    /** Shared by the Windows task and the existing batch launcher. */
    public function runDue(): array
    {
        File::ensureDirectoryExists(dirname($this->path('run.lock')));
        $handle = fopen($this->path('run.lock'), 'c+');
        if ($handle === false) {
            throw new RuntimeException('Lock scheduler tidak dapat dibuka.');
        }
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return ['outcome' => 'busy', 'exit_code' => 0];
        }

        try {
            if (($this->readJson($this->path('control.json'))['enabled'] ?? true) === false) {
                return ['outcome' => 'disabled', 'exit_code' => 0];
            }
            $minute = intdiv(time(), 60);
            if ((int) ($this->readJson($this->path('run.json'))['minute'] ?? 0) >= $minute) {
                return ['outcome' => 'already_ran', 'exit_code' => 0];
            }
            $state = ['minute' => $minute, 'pid' => getmypid(), 'started_at' => now()->toIso8601String(), 'completed_at' => null, 'exit_code' => null];
            $this->writeJson($this->path('run.json'), $state);
            try {
                $exitCode = Artisan::call('schedule:run', ['--no-interaction' => true]);
                $output = Artisan::output();
            } catch (Throwable $e) {
                $exitCode = 1;
                $output = $e->getMessage();
            }
            $state['completed_at'] = now()->toIso8601String();
            $state['exit_code'] = $exitCode;
            $this->writeJson($this->path('run.json'), $state);
            $log = $this->path('scheduler.log');
            File::append($log, '['.now()->toIso8601String().'] exit='.$exitCode.PHP_EOL.substr($output, -16384).PHP_EOL);
            if (filesize($log) > 1048576) {
                $stream = fopen($log, 'rb');
                fseek($stream, -524288, SEEK_END);
                $tail = stream_get_contents($stream);
                fclose($stream);
                File::replace($log, $tail);
            }

            return ['outcome' => 'completed', 'exit_code' => $exitCode];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    protected function path(string $name): string
    {
        $directory = app()->runningUnitTests() ? 'framework/testing/runtime-'.getmypid() : 'framework/server-runtime';

        return storage_path($directory.'/'.$name);
    }

    protected function invoke(string $action, ?string $service = null): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException('Kontrol layanan ini tersedia pada server Windows.');
        }
        // The helper validates an absolute php.exe; a bare PATH "php" is not accepted.
        $php = PhpCliBinary::resolve();
        if ($php === null || ! is_file($php)) {
            throw new RuntimeException('PHP CLI tidak ditemukan pada server.');
        }
        $command = ['powershell.exe', '-NoProfile', '-NonInteractive', '-NoLogo', '-ExecutionPolicy', 'Bypass', '-File', base_path('scripts/manage-project-runtime.ps1'), '-Action', $action, '-ProjectDirectory', base_path(), '-PhpBinary', $php];
        if ($service !== null) {
            array_push($command, '-ServiceKey', $service);
        }
        [$exitCode, $stdout, $stderr] = $this->runner->run($command, $action === 'Status' ? 8.0 : 20.0);
        $result = json_decode($stdout, true);
        if (! is_array($result)) {
            throw new RuntimeException('Windows tidak memberikan status layanan yang valid. '.mb_substr($stderr, 0, 600));
        }
        if ($action !== 'Status') {
            $result['success'] = $exitCode === 0 && ($result['success'] ?? false);
        }

        return $result;
    }

    private function cacheKey(): string
    {
        return 'server-runtime:'.sha1(strtolower(str_replace('\\', '/', base_path())));
    }

    private function readJson(string $path): array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }

    private function writeJson(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::replace($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function logTail(): string
    {
        $path = $this->path('scheduler.log');
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            return '';
        }
        fseek($stream, max(0, filesize($path) - 8192));
        $tail = stream_get_contents($stream);
        fclose($stream);

        return mb_convert_encoding($tail, 'UTF-8', 'UTF-8');
    }
}
