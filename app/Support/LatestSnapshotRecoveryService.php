<?php

namespace App\Support;

use App\Jobs\EnsureImportedSnapshotsFreshJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LatestSnapshotRecoveryService
{
    /**
     * This list intentionally mirrors EnsureImportedSnapshotsFreshJob. Each
     * item is checked against its own latest source period, so a monthly
     * source cannot make a daily source appear stale (or vice versa).
     */
    private const SOURCE_PERIOD_COLUMNS = [
        'daily_loan_dinamis' => 'periode',
        'simpanan_multipn' => 'posisi',
        'ssa_simpanan' => 'Month_Day_Year_of_Posisi',
        'ssa_pinjaman' => 'month_day_year_of_periode',
        'hourly_dpk' => 'posisi',
        'lw325_ph' => 'periode',
        'gi405_recovery' => 'periode',
        'dly_kap_resegmentasi' => 'periode',
        'l1133' => 'periode',
    ];

    /**
     * Queue freshness checks for the latest available period of each source.
     *
     * @return array{queued: array<int, array{table: string, period: string}>, skipped: array<int, array{table: string, reason: string}>, duplicate_request: bool}
     */
    public function queueLatestChecks(string $source = 'file-management:latest-snapshot-check'): array
    {
        $requestLock = Cache::lock('snapshot:manual-latest-check:dispatch', 15);
        if (! $requestLock->get()) {
            return [
                'queued' => [],
                'skipped' => [],
                'duplicate_request' => true,
            ];
        }

        try {
            $queued = [];
            $skipped = [];

            foreach (self::SOURCE_PERIOD_COLUMNS as $table => $periodColumn) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $periodColumn)) {
                    $skipped[] = [
                        'table' => $table,
                        'reason' => 'Sumber tidak tersedia.',
                    ];

                    continue;
                }

                try {
                    $period = StrictDateParser::normalize((string) DB::table($table)->max($periodColumn));
                } catch (\Throwable $e) {
                    Log::warning('Manual latest snapshot check could not read source period.', [
                        'table' => $table,
                        'period_column' => $periodColumn,
                        'exception_class' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                    $skipped[] = [
                        'table' => $table,
                        'reason' => 'Periode sumber tidak dapat dibaca.',
                    ];

                    continue;
                }

                if ($period === null) {
                    $skipped[] = [
                        'table' => $table,
                        'reason' => 'Belum memiliki periode yang dapat diperiksa.',
                    ];

                    continue;
                }

                try {
                    EnsureImportedSnapshotsFreshJob::dispatch($table, $period, $source)
                        ->onQueue('snapshots-priority');
                } catch (\Throwable $e) {
                    Log::warning('Manual latest snapshot check could not be queued.', [
                        'table' => $table,
                        'period' => $period,
                        'exception_class' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                    $skipped[] = [
                        'table' => $table,
                        'reason' => 'Pengecekan snapshot gagal dijadwalkan.',
                    ];

                    continue;
                }

                $queued[] = [
                    'table' => $table,
                    'period' => $period,
                ];
            }

            Log::info('Manual latest snapshot freshness checks queued.', [
                'source' => $source,
                'queued' => $queued,
                'skipped_count' => count($skipped),
            ]);

            return [
                'queued' => $queued,
                'skipped' => $skipped,
                'duplicate_request' => false,
            ];
        } finally {
            $requestLock->release();
        }
    }
}
