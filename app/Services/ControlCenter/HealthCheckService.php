<?php

namespace App\Services\ControlCenter;

use App\Http\Controllers\Report\DataPhReportController;
use App\Services\Import\QueueWorkerMonitorService;
use App\Services\Import\SafeWorkerRestartService;
use App\Services\Import\SchemaDrift\SchemaDriftRegistry;
use App\Services\ServerRuntimeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One health verdict for the whole import platform. Each check is isolated:
 * a failing probe becomes an "unknown" result instead of breaking the panel,
 * and every problem carries the action an operator should take.
 */
class HealthCheckService
{
    private const CACHE_KEY = 'control-center:health:v1';
    private const CACHE_SECONDS = 20;

    public const STATUS_OK = 'ok';
    public const STATUS_WARN = 'warn';
    public const STATUS_FAIL = 'fail';
    public const STATUS_INFO = 'info';
    public const STATUS_UNKNOWN = 'unknown';

    private const PENALTY = [self::STATUS_FAIL => 18, self::STATUS_WARN => 6, self::STATUS_UNKNOWN => 3];

    public function __construct(
        private readonly ServerRuntimeService $runtime,
        private readonly QueueWorkerMonitorService $workers,
        private readonly ReportDataFreshnessService $freshness,
        private readonly DatabaseInsightService $database,
        private readonly ShadowColumnHealthService $shadow,
    ) {
    }

    public function snapshot(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->build($fresh));
    }

    private function build(bool $fresh): array
    {
        $startedAt = microtime(true);
        $checks = [];
        foreach ([
            'runtime' => fn (): array => $this->runtimeChecks($fresh),
            'queue' => fn (): array => $this->queueChecks(),
            'imports' => fn (): array => $this->importChecks(),
            'data' => fn (): array => $this->dataChecks($fresh),
            'snapshots' => fn (): array => $this->snapshotChecks(),
            'shadow' => fn (): array => $this->shadowChecks($fresh),
            'database' => fn (): array => $this->databaseChecks($fresh),
            'schema' => fn (): array => $this->schemaDriftChecks(),
        ] as $group => $probe) {
            try {
                foreach ($probe() as $check) {
                    $checks[] = $check + ['group' => $group];
                }
            } catch (Throwable $e) {
                $checks[] = $this->check($group . '.probe', $group, 'Pemeriksaan ' . $group, self::STATUS_UNKNOWN, 'Gagal diperiksa', mb_strimwidth($e->getMessage(), 0, 180, '…'), 'Periksa log aplikasi; panel tetap berjalan.') + ['group' => $group];
            }
        }

        $score = 100;
        foreach ($checks as $check) {
            $score -= self::PENALTY[$check['status']] ?? 0;
        }
        $score = max(0, $score);
        $counts = array_count_values(array_column($checks, 'status'));
        $overall = match (true) {
            ($counts[self::STATUS_FAIL] ?? 0) > 0 => self::STATUS_FAIL,
            ($counts[self::STATUS_WARN] ?? 0) > 0 => self::STATUS_WARN,
            default => self::STATUS_OK,
        };

        usort($checks, static function (array $a, array $b): int {
            $rank = [self::STATUS_FAIL => 0, self::STATUS_WARN => 1, self::STATUS_UNKNOWN => 2, self::STATUS_INFO => 3, self::STATUS_OK => 4];

            return ($rank[$a['status']] ?? 5) <=> ($rank[$b['status']] ?? 5);
        });

        return [
            'generated_at' => now()->toIso8601String(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'score' => $score,
            'overall' => $overall,
            'headline' => match ($overall) {
                self::STATUS_FAIL => ($counts[self::STATUS_FAIL] ?? 0) . ' masalah kritis perlu ditangani.',
                self::STATUS_WARN => ($counts[self::STATUS_WARN] ?? 0) . ' hal perlu diperhatikan; layanan inti berjalan.',
                default => 'Semua layanan, antrean, dan data dalam kondisi sehat.',
            },
            'counts' => [
                'ok' => $counts[self::STATUS_OK] ?? 0,
                'warn' => $counts[self::STATUS_WARN] ?? 0,
                'fail' => $counts[self::STATUS_FAIL] ?? 0,
                'info' => ($counts[self::STATUS_INFO] ?? 0) + ($counts[self::STATUS_UNKNOWN] ?? 0),
            ],
            'checks' => $checks,
        ];
    }

    private function runtimeChecks(bool $fresh): array
    {
        $runtime = $this->runtime->snapshot($fresh);
        $scheduler = (array) ($runtime['scheduler'] ?? []);
        $worker = (array) ($runtime['worker'] ?? []);
        $checks = [];

        // The heartbeat proves the scheduler runs; the Windows task registration
        // may simply be unreadable for the web server account.
        $schedulerState = (string) ($scheduler['state'] ?? 'unknown');
        $heartbeatAge = $scheduler['heartbeat_age_seconds'] ?? null;
        $lastExitCode = $scheduler['last_execution']['exit_code'] ?? null;
        $beating = $heartbeatAge !== null && (int) $heartbeatAge <= 180;
        // A heartbeat that is late but recent usually means one tick is still
        // held open by a long child process; a long silence means it stopped.
        $lateMinutes = $heartbeatAge !== null && !$beating ? (int) ceil((int) $heartbeatAge / 60) : null;
        $schedulerStatus = match (true) {
            in_array($schedulerState, ['active', 'external'], true) => self::STATUS_OK,
            $beating && ($lastExitCode === null || (int) $lastExitCode === 0) => self::STATUS_OK,
            $beating => self::STATUS_WARN,
            $lateMinutes !== null && $lateMinutes <= 15 => self::STATUS_WARN,
            default => self::STATUS_FAIL,
        };
        $checks[] = $this->check('runtime.scheduler', 'runtime', 'Scheduler Laravel', $schedulerStatus,
            $beating
                ? 'Berjalan · heartbeat ' . (int) $heartbeatAge . ' dtk lalu'
                : ($lateMinutes !== null ? 'Heartbeat terlambat ' . $lateMinutes . ' menit' : (string) ($scheduler['state_label'] ?? $schedulerState)),
            match (true) {
                $beating && !in_array($schedulerState, ['active', 'external'], true) => 'Eksekusi terjadwal aktif. Registrasi Windows Task Scheduler tidak dapat dibaca akun server: ' . mb_strimwidth((string) ($scheduler['message'] ?? ''), 0, 120, '…'),
                $lateMinutes !== null => 'Tick terakhir ' . ($scheduler['heartbeat_at'] ?? '-') . '. Watchdog worker dan drain snapshot ikut tertunda selama scheduler diam.',
                default => 'Scheduler menjalankan watchdog worker, sinkronisasi snapshot, dan backup terjadwal.',
            },
            match (true) {
                $schedulerStatus === self::STATUS_OK => 'Tidak perlu tindakan.',
                $lateMinutes !== null && $lateMinutes <= 15 => 'Biasanya pulih sendiri; bila berlanjut, jalankan ulang scheduler dari panel Layanan Server.',
                default => 'Aktifkan scheduler pada panel Layanan Server.',
            });

        $monitorActive = (bool) ($worker['monitor_active'] ?? false);
        $checks[] = $this->check('runtime.monitor', 'runtime', 'Monitor worker antrean',
            $monitorActive ? self::STATUS_OK : (($worker['enabled'] ?? true) ? self::STATUS_FAIL : self::STATUS_WARN),
            $monitorActive ? 'Aktif' : (($worker['enabled'] ?? true) ? 'Tidak aktif' : 'Dinonaktifkan'),
            'Monitor meluncurkan dan mengatur jumlah worker sesuai antrean.',
            'Klik "Mulai Worker" pada tab Operasional.');

        foreach ((array) ($runtime['infrastructure'] ?? []) as $service) {
            $state = (string) ($service['state'] ?? 'unknown');
            $checks[] = $this->check('runtime.' . ($service['key'] ?? 'service'), 'runtime', (string) ($service['label'] ?? 'Layanan'),
                $state === 'running' ? self::STATUS_OK : self::STATUS_FAIL,
                (string) ($service['state_label'] ?? $state),
                (string) ($service['detail'] ?? ''),
                'Jalankan layanan dari panel Layanan Server.');
        }

        $backup = (array) ($runtime['backup'] ?? []);
        if ($backup !== []) {
            $backupOk = (bool) ($backup['healthy'] ?? ($backup['ok'] ?? true));
            $checks[] = $this->check('runtime.backup', 'runtime', 'Backup database', $backupOk ? self::STATUS_OK : self::STATUS_WARN,
                (string) ($backup['label'] ?? ($backupOk ? 'Tersedia' : 'Perlu dicek')),
                (string) ($backup['message'] ?? ($backup['detail'] ?? '')),
                'Jalankan backup manual dari panel Layanan Server.');
        }

        return $checks;
    }

    private function queueChecks(): array
    {
        $snapshot = $this->workers->snapshot();
        $totals = (array) ($snapshot['totals'] ?? []);
        $checks = [];

        $stalled = array_values(array_filter((array) ($snapshot['pools'] ?? []), static fn (array $pool): bool => ($pool['state'] ?? '') === 'stalled'));
        $backoff = array_values(array_filter((array) ($snapshot['pools'] ?? []), static fn (array $pool): bool => ($pool['state'] ?? '') === 'backoff'));
        $staleReserved = array_sum(array_map(static fn (array $pool): int => (int) ($pool['stale_reserved_jobs'] ?? 0), (array) ($snapshot['pools'] ?? [])));

        $checks[] = $this->check('queue.pools', 'queue', 'Pool worker',
            $stalled !== [] ? self::STATUS_FAIL : ($backoff !== [] ? self::STATUS_WARN : self::STATUS_OK),
            ($totals['live_workers'] ?? 0) . ' worker hidup · ' . ($totals['ready_jobs'] ?? 0) . ' job siap',
            $stalled !== []
                ? 'Antrean macet tanpa worker: ' . implode(', ', array_column($stalled, 'name')) . '.'
                : ($backoff !== [] ? 'Cooldown peluncuran setelah gagal: ' . implode(', ', array_column($backoff, 'name')) . '.' : 'Setiap antrean dilayani worker sesuai kebutuhan.'),
            $stalled !== [] ? 'Restart worker dari tab Operasional; periksa log queue-worker.' : 'Tidak perlu tindakan.');

        if ($staleReserved > 0) {
            $checks[] = $this->check('queue.stale', 'queue', 'Job tertahan', self::STATUS_WARN, $staleReserved . ' job',
                'Ada job yang direservasi melewati batas retry; worker-nya kemungkinan berhenti di tengah jalan.',
                'Job akan dirilis otomatis; bila berulang, hapus atau jalankan paksa dari tab Operasional.');
        }

        $outdated = (int) ($totals['outdated_workers'] ?? 0);
        if ($outdated > 0) {
            $checks[] = $this->check('queue.outdated', 'queue', 'Versi kode worker', self::STATUS_INFO, $outdated . ' worker versi lama',
                'Worker lama menyelesaikan job aktif lalu digantikan otomatis oleh restart aman.', 'Tidak perlu tindakan.');
        }

        $failed = (array) ($snapshot['failed_jobs'] ?? []);
        $lastDay = (int) ($failed['last_24h'] ?? 0);
        // Failures in light queues (remote refresh, cache warm) never touch
        // imported data; they are worth a warning, not a critical alarm.
        $byQueue = $this->failedJobsByQueue();
        $critical = 0;
        foreach ($byQueue as $queue => $count) {
            if (!in_array($queue, SafeWorkerRestartService::LIGHT_QUEUES, true)) {
                $critical += $count;
            }
        }
        $topGroup = $failed['groups'][0] ?? null;
        $checks[] = $this->check('queue.failed', 'queue', 'Job gagal',
            $critical >= 5 ? self::STATUS_FAIL : ($lastDay > 0 ? self::STATUS_WARN : self::STATUS_OK),
            "{$lastDay} dalam 24 jam · {$critical} di antrean inti",
            $topGroup !== null
                ? 'Terbanyak: ' . $topGroup['job'] . ' (' . $topGroup['queue'] . ', ' . $topGroup['count'] . '×) — ' . ($topGroup['error'] ?? '')
                : 'Tidak ada job gagal dalam 24 jam.',
            $critical > 0 ? 'Tinjau job gagal di antrean inti pada tab Operasional.' : ($lastDay > 0 ? 'Kegagalan hanya pada antrean ringan; periksa koneksi sumber eksternal.' : 'Tidak perlu tindakan.'));

        try {
            $restart = app(SafeWorkerRestartService::class)->summary();
            if (!empty($restart['pending'])) {
                $checks[] = $this->check('queue.restart', 'queue', 'Restart aman tertunda', self::STATUS_INFO,
                    'Menunggu sistem diam',
                    'Worker akan memakai kode terbaru setelah import/snapshot yang berjalan selesai.',
                    'Tidak perlu tindakan.');
            }
        } catch (Throwable) {
            // Optional signal.
        }

        return $checks;
    }

    private function importChecks(): array
    {
        if (!Schema::hasTable('import_jobs')) {
            return [];
        }

        $since = now()->subDays(7);
        $rows = DB::table('import_jobs')->where('created_at', '>=', $since)->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->all();
        $finished = array_sum(array_intersect_key($rows, array_flip(['completed', 'failed', 'failed_partial', 'terminated'])));
        $completed = (int) ($rows['completed'] ?? 0);
        $rate = $finished > 0 ? round(100 * $completed / $finished, 1) : null;

        $stuck = DB::table('import_jobs')
            ->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(30))
            ->count();
        $waiting = DB::table('import_jobs')
            ->where('status', 'queued')
            ->where('created_at', '<', now()->subMinutes(15))
            ->count();

        return [
            $this->check('imports.success', 'imports', 'Keberhasilan import 7 hari',
                $rate === null ? self::STATUS_INFO : ($rate >= 95 ? self::STATUS_OK : ($rate >= 80 ? self::STATUS_WARN : self::STATUS_FAIL)),
                $rate === null ? 'Belum ada import' : "{$rate}% ({$completed}/{$finished})",
                'Gagal: ' . (int) ($rows['failed'] ?? 0) . ' · Sebagian: ' . (int) ($rows['failed_partial'] ?? 0) . ' · Dihentikan: ' . (int) ($rows['terminated'] ?? 0),
                $rate !== null && $rate < 95 ? 'Buka Riwayat Eksekusi Job dan saring status Failed.' : 'Tidak perlu tindakan.'),
            $this->check('imports.stuck', 'imports', 'Import tanpa progres',
                $stuck > 0 ? self::STATUS_WARN : self::STATUS_OK,
                $stuck > 0 ? "{$stuck} job" : 'Tidak ada',
                'Job berstatus processing yang tidak bergerak lebih dari 30 menit.',
                $stuck > 0 ? 'Monitor akan merekonsiliasi otomatis; bila tetap, hentikan job dari tab Operasional.' : 'Tidak perlu tindakan.'),
            $this->check('imports.waiting', 'imports', 'Import menunggu antrean',
                $waiting > 0 ? self::STATUS_WARN : self::STATUS_OK,
                $waiting > 0 ? "{$waiting} job > 15 menit" : 'Tidak ada',
                'Import yang lama berada di antrean biasanya menandakan worker import penuh atau macet.',
                $waiting > 0 ? 'Periksa pool imports-high pada panel Worker.' : 'Tidak perlu tindakan.'),
        ];
    }

    private function dataChecks(bool $fresh): array
    {
        $snapshot = $this->freshness->snapshot($fresh);
        $late = array_values(array_filter($snapshot['reports'], static fn (array $report): bool => in_array($report['status'], ['warn', 'fail'], true)));
        $failing = array_values(array_filter($late, static fn (array $report): bool => $report['status'] === 'fail'));
        usort($late, static fn (array $a, array $b): int => ($b['status'] === 'fail') <=> ($a['status'] === 'fail'));

        return [
            $this->check('data.freshness', 'data', 'Kesegaran data report',
                $failing !== [] ? self::STATUS_FAIL : ($late !== [] ? self::STATUS_WARN : self::STATUS_OK),
                (int) ($snapshot['summary']['ok'] ?? 0) . ' segar · ' . count($late) . ' tertinggal',
                $late === []
                    ? 'Semua report aktif terbaru sesuai ritme update masing-masing.'
                    : implode('; ', array_map(static fn (array $report): string => $report['report'] . ' (' . $report['message'] . ')', array_slice($late, 0, 4))),
                $late === [] ? 'Tidak perlu tindakan.' : 'Unggah data terbaru untuk report tersebut atau cek import terakhirnya.'),
            $this->dataPhGi405Check($fresh),
        ];
    }

    /**
     * Data PH shows recovery from LW325 only; this proves it in the backend
     * against GI405 income for the same month (see DataPhReportController).
     */
    private function dataPhGi405Check(bool $fresh): array
    {
        $cacheKey = 'control-center:data-ph-gi405:v1';
        if ($fresh) {
            Cache::forget($cacheKey);
        }
        try {
            $result = Cache::remember($cacheKey, 900, fn (): array => app(DataPhReportController::class)->gi405Reconciliation());
        } catch (Throwable $e) {
            // Keep the freshness check of the same group visible.
            return $this->check('data.ph_gi405', 'data', 'Rekonsiliasi Data PH ↔ GI405', self::STATUS_UNKNOWN, 'Gagal diperiksa',
                mb_strimwidth($e->getMessage(), 0, 180, '…'), 'Periksa log aplikasi.');
        }

        $percent = static fn (?float $value): string => $value === null ? 'n/d' : sprintf('%+.1f%%', $value);
        $total = (array) ($result['total'] ?? []);
        $byStatus = array_count_values(array_column((array) $result['branches'], 'status'));
        $detail = implode('; ', array_map(
            static fn (string $branch, array $row): string => $branch . ' ' . $percent($row['diff_pct']),
            array_keys((array) $result['branches']),
            (array) $result['branches']
        ));

        return $this->check('data.ph_gi405', 'data', 'Rekonsiliasi Data PH ↔ GI405',
            match ($result['status']) {
                'ok' => self::STATUS_OK,
                'warn' => self::STATUS_WARN,
                'fail' => self::STATUS_FAIL,
                default => self::STATUS_INFO,
            },
            $result['status'] === 'unknown'
                ? 'GI405 belum tersedia untuk ' . ($result['period'] ?? '-')
                : 'Selisih Area 6 ' . $percent($total['diff_pct'] ?? null) . ' · ' . ($byStatus['ok'] ?? 0) . '/' . count((array) $result['branches']) . ' KC sesuai',
            'Periode ' . ($result['period'] ?? '-') . ' vs GI405 sejak ' . ($result['gi405_m1_period'] ?? '-') . ': ' . $detail
                . '. Toleransi ±' . DataPhReportController::GI405_RECONCILE_OK_PCT . '% (perhatian sampai ±' . DataPhReportController::GI405_RECONCILE_WARN_PCT . '%).',
            match ($result['status']) {
                'ok' => 'Tidak perlu tindakan.',
                'unknown' => 'Import GI405 Single Row untuk tanggal yang sama dengan LW325 PH terakhir.',
                default => 'Periksa import LW325 PH/GI405 periode tersebut dan data klaim KC yang selisihnya besar.',
            });
    }

    private function snapshotChecks(): array
    {
        if (!Schema::hasTable('snapshot_dirty_periods')) {
            return [];
        }

        $staleClaimCutoff = now()->subMinutes(90);
        $row = DB::table('snapshot_dirty_periods')
            ->selectRaw('COUNT(*) AS n')
            ->selectRaw('SUM(claimed_at IS NOT NULL) AS claimed')
            ->selectRaw('MIN(CASE WHEN claimed_at IS NULL THEN dirty_since END) AS oldest_waiting')
            ->selectRaw('SUM(claimed_at IS NOT NULL AND claimed_at < ?) AS stale_claims', [$staleClaimCutoff])
            ->selectRaw('SUM(last_error IS NOT NULL) AS errors')
            ->selectRaw('MAX(attempts) AS attempts')
            ->first();
        $pending = (int) ($row->n ?? 0);
        $claimed = (int) ($row->claimed ?? 0);
        $waitingMinutes = $row->oldest_waiting !== null ? (int) round((time() - strtotime((string) $row->oldest_waiting)) / 60) : 0;
        $staleClaims = (int) ($row->stale_claims ?? 0);
        $errors = (int) ($row->errors ?? 0);
        $status = match (true) {
            $errors > 0 && (int) ($row->attempts ?? 0) >= 3, $waitingMinutes > 180 => self::STATUS_FAIL,
            $errors > 0, $staleClaims > 0, $waitingMinutes > 45 => self::STATUS_WARN,
            $pending > 0 => self::STATUS_INFO,
            default => self::STATUS_OK,
        };

        return [
            $this->check('snapshots.backlog', 'snapshots', 'Antrean refresh snapshot', $status,
                $pending === 0 ? 'Bersih' : "{$pending} periode · {$claimed} sedang diproses",
                match (true) {
                    $errors > 0 => "{$errors} periode gagal diproses (percobaan maks " . (int) ($row->attempts ?? 0) . ').',
                    $staleClaims > 0 => "{$staleClaims} periode diklaim lebih dari 90 menit tanpa selesai.",
                    $waitingMinutes > 0 => "Periode tertua menunggu {$waitingMinutes} menit untuk diproses.",
                    $pending > 0 => 'Snapshot periode yang berubah sedang dibangun ulang.',
                    default => 'Tidak ada periode yang menunggu refresh snapshot.',
                },
                in_array($status, [self::STATUS_FAIL, self::STATUS_WARN], true) ? 'Buka tab Snapshot untuk sinkronisasi periode atau rebuild.' : 'Tidak perlu tindakan.'),
        ];
    }

    private function shadowChecks(bool $fresh): array
    {
        $snapshot = $this->shadow->snapshot($fresh);
        if (!($snapshot['available'] ?? false) || ($snapshot['periods'] ?? []) === []) {
            return [];
        }

        $latest = $snapshot['periods'][0];
        $drift = array_sum((array) $latest['mismatches']) + (int) $latest['unbuilt_rows'] + array_sum((array) $latest['empty_derived']);

        return [
            $this->check('shadow.latest', 'shadow', 'Shadow column Daily Loan',
                $latest['status'] === 'ok' ? self::STATUS_OK : ($latest['status'] === 'warn' ? self::STATUS_WARN : self::STATUS_FAIL),
                $latest['parity_percent'] . '% paritas · periode ' . $latest['period'],
                $drift === 0 ? 'Kolom normalisasi yang dibaca dashboard identik dengan nilai acuan database.' : "{$drift} sel belum sinkron (belum dibangun/berbeda dari acuan).",
                $drift === 0 ? 'Tidak perlu tindakan.' : 'Jalankan perbaikan periode dari tab Shadow Column.'),
        ];
    }

    private function databaseChecks(bool $fresh): array
    {
        $db = $this->database->snapshot($fresh);
        if (!($db['available'] ?? false)) {
            return [];
        }

        $usage = (float) $db['connections']['usage_ratio'];
        $long = count($db['long_queries']);
        $checks = [
            $this->check('database.connections', 'database', 'Koneksi database',
                $usage >= 0.85 ? self::STATUS_FAIL : ($usage >= 0.6 ? self::STATUS_WARN : self::STATUS_OK),
                $db['connections']['connected'] . ' / ' . $db['connections']['max'],
                'Puncak ' . $db['connections']['peak'] . ' koneksi · ' . $db['connections']['running'] . ' sedang mengeksekusi query.',
                $usage >= 0.6 ? 'Kurangi worker paralel atau naikkan max_connections.' : 'Tidak perlu tindakan.'),
            $this->check('database.long_queries', 'database', 'Query berjalan lama',
                $long > 2 ? self::STATUS_WARN : ($long > 0 ? self::STATUS_INFO : self::STATUS_OK),
                $long . ' query ≥ ' . (int) (DatabaseInsightService::LONG_QUERY_SECONDS / 60) . ' menit',
                $long > 0 ? 'Terlama ' . $db['long_queries'][0]['seconds'] . ' detik: ' . $db['long_queries'][0]['sql'] : 'Tidak ada query yang tertahan lama.',
                $long > 0 ? 'Lihat detail di tab Database; import besar memang bisa berjalan lama.' : 'Tidak perlu tindakan.'),
        ];

        foreach ((array) $db['storage']['disks'] as $disk) {
            if (($disk['free_bytes'] ?? null) === null || ($disk['total_bytes'] ?? 0) <= 0) {
                continue;
            }
            $freeRatio = $disk['free_bytes'] / $disk['total_bytes'];
            $freeGb = round($disk['free_bytes'] / 1073741824, 1);
            $checks[] = $this->check('database.disk.' . md5($disk['path']), 'database', 'Ruang disk ' . rtrim($disk['path'], '\\/'),
                $freeGb < 15 || $freeRatio < 0.05 ? self::STATUS_FAIL : ($freeGb < 40 || $freeRatio < 0.12 ? self::STATUS_WARN : self::STATUS_OK),
                $freeGb . ' GB kosong',
                $disk['label'] . ' · ' . round(100 * $freeRatio, 1) . '% tersisa. Import besar membutuhkan ruang staging.',
                $freeGb < 40 ? 'Bersihkan file staging/backup lama atau perluas disk.' : 'Tidak perlu tindakan.');
        }

        return $checks;
    }

    private function schemaDriftChecks(): array
    {
        $feed = app(SchemaDriftRegistry::class)->notifications(1);
        $pending = (int) ($feed['pending'] ?? 0);
        if ($pending === 0 && (int) ($feed['count'] ?? 0) === 0) {
            return [$this->check('schema.drift', 'schema', 'Struktur dokumen sumber', self::STATUS_OK, 'Sesuai', 'Tidak ada perubahan struktur yang menunggu.', 'Tidak perlu tindakan.')];
        }

        return [$this->check('schema.drift', 'schema', 'Struktur dokumen sumber',
            $pending > 0 ? self::STATUS_WARN : self::STATUS_INFO,
            $pending > 0 ? "{$pending} menunggu keputusan" : (int) $feed['count'] . ' notifikasi belum dibaca',
            (string) ($feed['items'][0]['summary'] ?? ''),
            'Putuskan dari ikon lonceng di navbar atau halaman preview import.')];
    }

    /**
     * Failures of the last 24 hours per queue, read from the newest rows by
     * primary key (failed_jobs has no failed_at index).
     *
     * @return array<string, int>
     */
    private function failedJobsByQueue(): array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return [];
        }

        $maxId = (int) DB::table('failed_jobs')->max('id');
        if ($maxId === 0) {
            return [];
        }

        return array_map('intval', DB::table('failed_jobs')
            ->where('id', '>', max(0, $maxId - 5000))
            ->where('failed_at', '>=', now()->subDay())
            ->selectRaw('queue, COUNT(*) AS n')
            ->groupBy('queue')
            ->pluck('n', 'queue')
            ->all());
    }

    private function check(string $key, string $category, string $label, string $status, string $value, string $detail, string $action): array
    {
        return compact('key', 'category', 'label', 'status', 'value', 'detail', 'action');
    }
}
