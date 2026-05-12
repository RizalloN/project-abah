<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized duplicate prevention for all import jobs.
 *
 * DESIGN PRINCIPLES:
 * ─────────────────────────────────────────────────────────────────
 * 1. FILE IDENTITY  — SHA256(file_content) is immutable regardless of filename/path.
 *    The same bytes uploaded twice = same file = duplicate.
 *
 * 2. SLOT IDENTITY  — A "slot" is the smallest unit of data ownership per report.
 *    Defined via config: which DB columns form a unique slot key.
 *    e.g. simpanan_multipn: (posisi, kantor_cabang) — allows 4+ imports per period,
 *    one per branch. daily_loan: (periode) — one import per period.
 *
 * 3. TWO LAYERS OF PROTECTION:
 *    Layer A — Global file fingerprint: same SHA256 must not appear in ANY report.
 *    Layer B — Slot availability: the data slot for this (report, dimensions) must be empty.
 *
 * 4. ADVISORY LOCK — MySQL GET_LOCK() prevents race conditions across concurrent uploads.
 *    Cache::lock() is process-local only; GET_LOCK() survives concurrent HTTP processes.
 * ─────────────────────────────────────────────────────────────────
 */
class ImportDuplicateGuardService
{
    /**
     * Per-report policy. Defines which DB columns form a unique "slot" key.
     * Kelonggaran: simpanan_multipn boleh many imports per posisi karena
     * slot key-nya (posisi + kantor_cabang), bukan hanya posisi.
     */
    private const REPORT_POLICY = [
        // [table_name => policy]
        'simpanan_multipn' => [
            'slot_table'       => 'simpanan_multipn',
            'slot_columns'     => ['posisi', 'kantor_cabang'],  // unique per tanggal+cabang
            'slot_label'       => 'posisi/kantor',
        ],
        'daily_loan_dinamis' => [
            'slot_table'       => 'daily_loan_dinamis',
            'slot_columns'     => ['periode'],
            'slot_label'       => 'periode',
        ],
        'brihc' => [
            'slot_table'       => 'brihc',
            'slot_columns'     => ['periode'],
            'slot_label'       => 'periode',
        ],
        'jumlah_merchant_detail' => [
            'slot_table'       => 'jumlah_merchant_detail',
            'slot_columns'     => ['periode'],
            'slot_label'       => 'periode',
        ],
        'casa_brilink_web' => [
            'slot_table'       => 'casa_brilink_web',
            'slot_columns'     => ['periode'],
            'slot_label'       => 'periode',
        ],
        'ssa_simpanan' => [
            'slot_table'       => 'ssa_simpanan',
            'slot_columns'     => ['posisi'],
            'slot_label'       => 'posisi',
        ],
        'hourly_dpk' => [
            'slot_table'       => 'hourly_dpk',
            'slot_columns'     => ['posisi'],
            'slot_label'       => 'posisi',
        ],
        'ssa_pinjaman' => [
            'slot_table'       => 'ssa_pinjaman',
            'slot_columns'     => ['posisi'],
            'slot_label'       => 'posisi',
        ],
    ];

    // Advisory lock timeout in seconds (MySQL GET_LOCK)
    private const ADVISORY_LOCK_TIMEOUT = 30;

    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Compute SHA256 of entire file content.
     * Memory-efficient: reads in 64KB chunks.
     */
    public function fingerprint(string $absolutePath): string
    {
        if (!file_exists($absolutePath)) {
            throw new \RuntimeException("File tidak ditemukan untuk fingerprinting: {$absolutePath}");
        }

        $hash = @hash_file('sha256', $absolutePath);

        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException("Gagal menghitung SHA256 file: {$absolutePath}");
        }

        return $hash;
    }

    /**
     * Layer A — Global file check.
     *
     * Ensures the exact same file has not been successfully imported into ANY report.
     * Throws if a completed job with this SHA256 exists anywhere.
     *
     * @param string $contentHash  SHA256 of the file
     * @param int    $excludeJobId Exclude a specific job (e.g., current job being updated)
     */
    public function assertFileNotImportedAnywhere(string $contentHash, int $excludeJobId = 0): void
    {
        if (!$this->isContentHashColumnAvailable()) {
            // Graceful degradation: virtual column not yet migrated
            Log::debug('ImportDuplicateGuard: job_content_hash column unavailable, skipping global check');
            return;
        }

        $query = DB::table('import_jobs')
            ->where('job_content_hash', $contentHash)
            ->whereIn('status', ['completed', 'processing'])
            ->orderByDesc('id')
            ->limit(1);

        if ($excludeJobId > 0) {
            $query->where('id', '!=', $excludeJobId);
        }

        $existing = $query->first(['id', 'id_report', 'created_at', 'job_context']);

        if ($existing === null) {
            return;
        }

        $reportName = $this->resolveReportName((int) $existing->id_report);
        $importedAt = $existing->created_at ?? '—';

        throw new \RuntimeException(sprintf(
            'File ini sudah pernah diimport (job #%d · %s · %s). '
            . 'Upload file yang sama ke report manapun tidak diizinkan.',
            $existing->id,
            $reportName,
            $importedAt
        ));
    }

    /**
     * Layer B — Slot availability check.
     *
     * For a given table and set of (column → value) slot dimensions,
     * checks whether the data slot is already occupied in the database.
     *
     * simpanan_multipn allows multiple imports per posisi as long as
     * (posisi, kantor_cabang) is unique — each branch is its own slot.
     *
     * @param string               $tableName  Target table (e.g. 'simpanan_multipn')
     * @param array<string,string> $slotValues e.g. ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Madiun']
     */
    public function assertSlotEmpty(string $tableName, array $slotValues): void
    {
        if (!Schema::hasTable($tableName) || empty($slotValues)) {
            return;
        }

        $query = DB::table($tableName);
        foreach ($slotValues as $column => $value) {
            $query->where($column, $value);
        }

        $exists = $query->exists();

        if (!$exists) {
            return;
        }

        $policy = self::REPORT_POLICY[$tableName] ?? null;
        $label  = $policy['slot_label'] ?? implode('+', array_keys($slotValues));
        $desc   = implode(', ', array_map(
            static fn ($k, $v) => "{$k}={$v}",
            array_keys($slotValues),
            array_values($slotValues)
        ));

        throw new \RuntimeException(
            "Data untuk slot {$label} ({$desc}) sudah ada di tabel {$tableName}. "
            . "Hapus data periode/kanca terkait terlebih dahulu sebelum import ulang."
        );
    }

    /**
     * Build slot dimensions from detected periods and branches
     * using the policy defined for the given table.
     *
     * Returns an array of [column => value] maps, one per unique slot.
     * e.g. [['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Madiun'], ...]
     *
     * @param string   $tableName
     * @param string[] $periods   Detected periods from file
     * @param string[] $branches  Detected branches from file (empty = no branch dimension)
     * @return array<array<string,string>>
     */
    public function buildSlotValues(string $tableName, array $periods, array $branches = []): array
    {
        $policy = self::REPORT_POLICY[$tableName] ?? null;

        if ($policy === null) {
            // Fallback: treat first period column as sole slot key
            return array_map(
                static fn ($p) => ['periode' => $p],
                array_values(array_unique(array_filter($periods)))
            );
        }

        $columns = $policy['slot_columns'];
        $slots   = [];

        // If only one slot dimension (e.g. ['periode']), expand per period
        if (count($columns) === 1) {
            $col = $columns[0];
            foreach (array_unique(array_filter($periods)) as $period) {
                $slots[] = [$col => $period];
            }
            return $slots;
        }

        // Two-dimensional slot: expand as cartesian product of period × branch
        foreach (array_unique(array_filter($periods)) as $period) {
            if (empty($branches)) {
                // No branch hints — cannot build full slot key; skip branch check
                $slots[] = [$columns[0] => $period];
                continue;
            }

            foreach (array_unique(array_filter($branches)) as $branch) {
                $slot = [];
                foreach ($columns as $col) {
                    // Map generic 'posisi'/'periode' to period, others to branch
                    $slot[$col] = in_array($col, ['posisi', 'periode'], true)
                        ? $period
                        : $branch;
                }
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    /**
     * Acquire a MySQL advisory lock for the given slot to prevent concurrent imports.
     * Returns the lock name so the caller can release it.
     *
     * Uses MySQL GET_LOCK() which is connection-scoped and process-safe,
     * unlike Cache::lock() which can be bypassed in multi-process environments.
     *
     * Returns null if locking is not available (non-MySQL driver).
     */
    public function acquireAdvisoryLock(string $tableName, array $slotValues): ?string
    {
        $lockName = $this->buildLockName($tableName, $slotValues);

        try {
            $result = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [
                $lockName,
                self::ADVISORY_LOCK_TIMEOUT,
            ]);

            if ((int) ($result->acquired ?? 0) !== 1) {
                throw new \RuntimeException(
                    "Import untuk slot ini ({$lockName}) sedang diproses oleh job lain. "
                    . "Mohon tunggu beberapa saat lalu coba lagi."
                );
            }

            return $lockName;
        } catch (\Illuminate\Database\QueryException $e) {
            // Non-MySQL driver or GET_LOCK unsupported — fallback gracefully
            Log::warning('ImportDuplicateGuard: GET_LOCK tidak tersedia, menggunakan fallback cache lock', [
                'error' => $e->getMessage(),
            ]);

            return $this->acquireCacheFallbackLock($lockName);
        }
    }

    /**
     * Release a previously acquired advisory lock.
     */
    public function releaseAdvisoryLock(?string $lockName): void
    {
        if ($lockName === null) {
            return;
        }

        // Release MySQL advisory lock
        try {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        } catch (\Throwable) {
            // Nothing — lock expires on connection close anyway
        }

        // Also release cache fallback lock if it was used
        Cache::forget("import_advisory_lock:{$lockName}");
    }

    /**
     * Convenience: run a callback within an advisory lock for the given slot.
     * Automatically acquires and releases the lock.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function withAdvisoryLock(string $tableName, array $slotValues, callable $callback): mixed
    {
        $lockName = $this->acquireAdvisoryLock($tableName, $slotValues);

        try {
            return $callback();
        } finally {
            $this->releaseAdvisoryLock($lockName);
        }
    }

    /**
     * Get the canonical policy for a given table.
     * Returns null if no specific policy is configured (falls back to caller logic).
     */
    public function getPolicyForTable(string $tableName): ?array
    {
        return self::REPORT_POLICY[$tableName] ?? null;
    }

    public function isContentHashColumnAvailable(): bool
    {
        try {
            return Schema::hasColumn('import_jobs', 'job_content_hash');
        } catch (\Throwable) {
            return false;
        }
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function buildLockName(string $tableName, array $slotValues): string
    {
        // Format: "import:simpanan_multipn:2026-04-30:KC Madiun"
        $parts = [$tableName];
        foreach ($slotValues as $value) {
            $parts[] = (string) $value;
        }

        $raw = 'import:' . implode(':', $parts);

        // MySQL GET_LOCK max 64 chars — hash if too long
        return strlen($raw) <= 64
            ? $raw
            : 'import:' . substr($tableName, 0, 12) . ':' . md5(implode('|', $parts));
    }

    private function acquireCacheFallbackLock(string $lockName): ?string
    {
        $cacheKey = "import_advisory_lock:{$lockName}";
        $lock = Cache::lock($cacheKey, 1800);

        if (!$lock->get()) {
            throw new \RuntimeException(
                "Import untuk slot ini sedang diproses. Mohon tunggu beberapa saat lalu coba lagi."
            );
        }

        return $lockName;
    }

    private function resolveReportName(int $reportId): string
    {
        $names = [
            1  => 'Jumlah Merchant',
            2  => 'SV Merchant',
            3  => 'QRIS',
            7  => 'Daily Loan Dinamis',
            9  => 'Simpanan MultiPN',
            12 => 'CASA Brilink Web',
            13 => 'CASA Brilink EDC',
        ];

        return $names[$reportId] ?? "Report #{$reportId}";
    }
}
