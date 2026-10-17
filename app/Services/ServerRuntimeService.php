<?php

namespace App\Services;

use App\Services\Import\QueueWorkerControlService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ServerRuntimeService
{
    public function __construct(private readonly SchedulerControlService $scheduler, private readonly QueueWorkerControlService $workers) {}

    public function snapshot(bool $fresh = false): array
    {
        $inspection = $this->scheduler->inspect($fresh);
        $scheduler = $this->scheduler->status($inspection);
        $worker = $this->workers->status();
        $infrastructure = $inspection['infrastructure'] ?? [];
        $backup = $this->backup($inspection['backup'] ?? null);
        $readiness = $this->readiness($scheduler, $worker, $infrastructure, $backup);

        return [
            'generated_at' => now()->toIso8601String(),
            'scheduler' => $scheduler,
            'worker' => $worker,
            'infrastructure' => $infrastructure,
            'backup' => $backup,
            'readiness' => $readiness,
            'restart_ready' => collect($readiness)->every(fn (array $item): bool => $item['ok'] !== false),
            'schedules' => $this->schedules(),
            'healthy' => in_array($scheduler['state'], ['active', 'external'], true)
                && ($worker['monitor_active'] ?? false)
                && count($infrastructure) === 3
                && collect($infrastructure)->every(fn (array $service): bool => $service['state'] === 'running'),
        ];
    }

    public function control(string $service, string $action, ?string $actor): array
    {
        if (! in_array($service, ['all', 'scheduler', 'apache', 'mysql', 'tunnel', 'backup'], true)
            || ! in_array($action, ['start', 'stop', 'restart', 'run'], true)
            || ($service === 'backup' && $action !== 'run')
            || (! in_array($service, ['scheduler', 'backup'], true) && $action !== 'start')) {
            throw new \InvalidArgumentException('Aksi layanan tidak valid.');
        }

        $lock = Cache::lock('server-runtime:operation:'.sha1(base_path()), 150);
        if (! $lock->get()) {
            return ['status' => 'warning', 'message' => 'Perubahan layanan masih diproses. Tunggu lalu periksa status.', 'runtime' => $this->snapshot(), 'results' => []];
        }
        $results = [];
        try {
            if ($service === 'all') {
                foreach ($this->scheduler->inspect(true)['infrastructure'] ?? [] as $item) {
                    // Starting also restores automatic startup, so a running
                    // service set to manual is still brought back to readiness.
                    if ($item['state'] === 'running' && ($item['automatic'] ?? null) !== false) {
                        continue;
                    }
                    $results[] = $this->attempt($item['key'], fn () => $this->scheduler->control('start', $item['key']));
                }
                $results[] = $this->attempt('scheduler', fn () => $this->scheduler->control('start'));
                $results[] = $this->attempt('worker', function (): array {
                    $state = $this->workers->enable();

                    return ['success' => (bool) ($state['started'] ?? $state['monitor_active'] ?? false), 'message' => $state['message'] ?? 'Permintaan aktivasi worker diproses.'];
                });
            } else {
                $results[] = $this->attempt($service, fn () => $this->scheduler->control($action, $service === 'scheduler' ? null : $service));
            }
            $runtime = $this->snapshot(true);
            $failed = count(array_filter($results, fn (array $result): bool => ! $result['success']));
            $status = $failed === count($results) ? 'error' : ($failed > 0 ? 'warning' : 'success');
            $message = $failed > 0
                ? 'Sebagian layanan belum berhasil dikontrol. '.implode(' ', array_column(array_filter($results, fn (array $result): bool => ! $result['success']), 'message'))
                : ($service === 'all' ? 'Aktivasi pendukung diterima. Status aktif ditentukan dari heartbeat dan pemeriksaan layanan.' : 'Permintaan layanan selesai diproses. Periksa status terbaru pada panel.');
            Log::notice('Server runtime controlled from Job Management.', ['actor' => $actor, 'service' => $service, 'action' => $action, 'results' => $results]);

            return compact('status', 'message', 'runtime', 'results');
        } finally {
            $lock->release();
        }
    }

    private function backup(?array $native): array
    {
        $native ??= ['available' => false, 'message' => 'Status task backup belum tersedia.'];
        $state = ! ($native['available'] ?? false) ? 'unknown' : (string) ($native['state'] ?? 'unknown');
        $lastResult = isset($native['last_result']) ? (int) $native['last_result'] : null;
        // 267011 = never ran, 267009 = running, 267014 = stopped by user.
        $failed = $lastResult !== null && ! in_array($lastResult, [0, 267009, 267011, 267014], true) && $state !== 'running';

        return [
            'available' => (bool) ($native['available'] ?? false),
            'registered' => (bool) ($native['registered'] ?? false),
            'enabled' => (bool) ($native['enabled'] ?? false),
            'owned' => (bool) ($native['owned'] ?? false),
            'state' => $state,
            'startup_mode' => $native['startup_mode'] ?? null,
            'last_run_at' => $native['last_run_at'] ?? null,
            'next_run_at' => $native['next_run_at'] ?? null,
            'last_result' => $lastResult,
            'failed' => $failed,
            'runnable' => ($native['owned'] ?? false) && ($native['enabled'] ?? false) && $state !== 'running',
            'message' => (string) ($native['message'] ?? ''),
        ];
    }

    /**
     * What must hold for the application to come back by itself after the
     * server restarts. ok=null means the server account cannot verify it.
     */
    private function readiness(array $scheduler, array $worker, array $infrastructure, array $backup): array
    {
        $items = [];
        foreach ($infrastructure as $service) {
            $running = ($service['state'] ?? '') === 'running';
            $automatic = $service['automatic'] ?? null;
            $items[] = [
                'key' => 'service-'.($service['key'] ?? 'unknown'),
                'label' => ($service['label'] ?? 'Service').' berjalan dan otomatis menyala',
                'ok' => $running && $automatic === true ? true : ($running && $automatic === null ? null : false),
                'hint' => ! $running
                    ? 'Klik Aktifkan pada baris '.($service['label'] ?? 'service').'.'
                    : ($automatic === false ? 'Klik Aktifkan untuk mengubah startup menjadi otomatis.' : ($automatic === null ? 'Startup otomatis tidak dapat dibaca akun server.' : '')),
            ];
        }
        $taskReadable = $scheduler['available'] ?? false;
        // A boot trigger or an indefinitely repeating trigger both resume a SYSTEM task after a reboot.
        $taskReady = ($scheduler['task_enabled'] ?? false) && ($scheduler['startup_mode'] ?? null) === 'system'
            && (($scheduler['has_boot_trigger'] ?? false) || ($scheduler['has_repeating_trigger'] ?? false))
            && ! ($scheduler['needs_upgrade'] ?? false);
        $items[] = [
            'key' => 'scheduler-task',
            'label' => 'Task scheduler SYSTEM aktif dan menyala otomatis setelah boot',
            'ok' => $taskReady ? true : (! $taskReadable ? null : (($scheduler['legacy_count'] ?? 0) > 0 ? null : false)),
            'hint' => $taskReady ? '' : (! $taskReadable
                ? 'Status task tidak dapat dibaca; pastikan heartbeat scheduler segar.'
                : (($scheduler['legacy_count'] ?? 0) > 0
                    ? 'Scheduler persistent lama berjalan; tidak menyala sendiri setelah restart. Hentikan lalu Aktifkan scheduler.'
                    : 'Klik Aktifkan Semua untuk mendaftarkan atau memperbarui task scheduler.')),
        ];
        $items[] = [
            'key' => 'scheduler-heartbeat',
            'label' => 'Scheduler mengirim heartbeat setiap menit',
            'ok' => in_array($scheduler['state'] ?? '', ['active', 'external'], true) ? true : (($scheduler['state'] ?? '') === 'starting' ? null : false),
            'hint' => in_array($scheduler['state'] ?? '', ['active', 'external'], true) ? '' : (string) ($scheduler['message'] ?? ''),
        ];
        $items[] = [
            'key' => 'worker',
            'label' => 'Worker antrean aktif dan dipulihkan watchdog',
            'ok' => ($worker['enabled'] ?? false) && ($worker['monitor_active'] ?? false) ? true : (($worker['enabled'] ?? false) ? null : false),
            'hint' => ! ($worker['enabled'] ?? false)
                ? 'Worker dimatikan; watchdog tidak akan menyalakannya setelah restart. Klik Aktifkan Semua.'
                : (($worker['monitor_active'] ?? false) ? '' : 'Watchdog scheduler akan menyalakan worker pada menit berikutnya.'),
        ];
        $items[] = [
            'key' => 'backup',
            'label' => 'Backup database harian terjadwal',
            'ok' => ($backup['registered'] && $backup['enabled'] && ! $backup['failed']) || (! $backup['registered'] && $backup['available'])
                ? true
                : (! $backup['available'] ? null : false),
            'hint' => ! $backup['available']
                ? 'Task backup tidak dapat dibaca; backup tetap dijadwalkan scheduler Laravel.'
                : ($backup['failed'] ? 'Backup terakhir gagal (kode '.$backup['last_result'].'). Klik Jalankan backup.' : ($backup['registered'] && ! $backup['enabled'] ? 'Task backup Windows dinonaktifkan.' : '')),
        ];

        return $items;
    }

    private function attempt(string $service, callable $operation): array
    {
        try {
            $result = $operation();

            return ['service' => $service, 'success' => (bool) ($result['success'] ?? false), 'message' => (string) ($result['message'] ?? 'Status operasi tidak tersedia.')];
        } catch (Throwable $e) {
            return ['service' => $service, 'success' => false, 'message' => $e->getMessage()];
        }
    }

    private function schedules(): array
    {
        $labels = [
            'scheduler:heartbeat' => 'Heartbeat scheduler',
            'queue:health-sweep' => 'Pemeriksaan kesehatan antrean',
            'logs:maintenance' => 'Pemeliharaan log',
            'cache:maintenance' => 'Pemeliharaan cache',
            'micro-pipeline:sync' => 'Sinkronisasi pipeline Mikro',
            'dashboard-sources:refresh' => 'Penyegaran sumber dashboard',
            'database:backup-daily' => 'Backup database harian',
            'database:backup-daily --if-missing' => 'Catch-up backup bila server mati tengah malam',
            'database:performance-tune' => 'Penyetelan performa database',
            'optimize' => 'Optimasi cache aplikasi',
            'queue:watchdog' => 'Pengawasan dan pemulihan worker',
            'import:health-check' => 'Pemeriksaan kesehatan impor',
            'reports:resume-snapshot-queues-if-idle' => 'Pemulihan antrean snapshot',
            'snapshot:validate-integrity' => 'Validasi integritas snapshot',
            'reports:ensure-fresh-snapshots' => 'Pemeriksaan kesegaran snapshot',
            'reports:snapshot:drain-dirty' => 'Pemrosesan snapshot yang berubah',
            'reports:dashboard-harian-sync-missing' => 'Sinkronisasi Dashboard Harian',
            'shadow:auto-backfill-scheduler' => 'Penjadwalan backfill data',
        ];

        return collect(app(Schedule::class)->events())->map(function ($event) use ($labels): array {
            $command = preg_replace('/^.*?artisan[\'"]?\s+/', '', (string) ($event->command ?? $event->description ?? 'Tugas terjadwal'));
            $name = strtok($command, ' ');

            return [
                'label' => $labels[$command] ?? $labels[$name] ?? $command,
                'command' => $command,
                'expression' => $event->expression,
                'timezone' => $event->timezone ?? config('app.timezone'),
                'next_run_at' => $event->nextRunDate()->toIso8601String(),
            ];
        })->all();
    }
}
