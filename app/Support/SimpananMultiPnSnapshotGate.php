<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SimpananMultiPnSnapshotGate
{
    private const SOURCE_TABLE = 'simpanan_multipn';

    /**
     * Area 6 cabang yang wajib lengkap sebelum snapshot simpanan dijalankan.
     *
     * Disimpan dalam bentuk key normalisasi agar variasi penulisan
     * seperti "KC Madiun" atau "kc madiun (konsolidasi-mb)" tetap terbaca sama.
     *
     * @var array<int, string>
     */
    private const REQUIRED_BRANCH_KEYS = [
        'MADIUN',
        'MAGETAN',
        'NGAWI',
        'PONOROGO',
    ];

    /**
     * Menentukan apakah snapshot simpanan MultiPN sudah boleh dimulai untuk periode tertentu.
     */
    public function isReady(?string $period): bool
    {
        return $this->resolveStatus($period)['is_ready'];
    }

    /**
     * Force-invalidate the branch coverage cache for a specific period.
     * Call this immediately after a simpanan_multipn import so the gate
     * re-reads from DB rather than serving stale cache data.
     */
    public function invalidatePeriodCache(?string $period): void
    {
        $normalizedPeriod = $this->normalizePeriod($period);
        if ($normalizedPeriod === null) {
            return;
        }

        $version = (int) Cache::get('report_cache_version:global', 1);
        Cache::forget('snapshot:simpanan_multipn:coverage:v' . $version . ':' . $normalizedPeriod);
    }

    /**
     * Mengembalikan branch Area 6 yang belum tersedia pada periode tertentu.
     *
     * @return array<int, string>
     */
    public function getMissingBranches(?string $period): array
    {
        return $this->resolveStatus($period)['missing_branches'];
    }

    /**
     * Mengembalikan branch Area 6 yang sudah terdeteksi pada periode tertentu.
     *
     * @return array<int, string>
     */
    public function getAvailableBranches(?string $period): array
    {
        return $this->resolveStatus($period)['available_branches'];
    }

    /**
     * @return array{is_ready: bool, available_branches: array<int, string>, missing_branches: array<int, string>}
     */
    private function resolveStatus(?string $period): array
    {
        $normalizedPeriod = $this->normalizePeriod($period);
        if ($normalizedPeriod === null || !Schema::hasTable(self::SOURCE_TABLE)) {
            return [
                'is_ready' => false,
                'available_branches' => [],
                'missing_branches' => self::REQUIRED_BRANCH_KEYS,
            ];
        }

        $cacheKey = 'snapshot:simpanan_multipn:coverage:v'
            . (int) Cache::get('report_cache_version:global', 1)
            . ':'
            . $normalizedPeriod;

        $status = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($normalizedPeriod): array {
            $availableBranches = $this->collectAvailableBranchKeys($normalizedPeriod);
            $missingBranches = array_values(array_diff(self::REQUIRED_BRANCH_KEYS, $availableBranches));

            return [
                'is_ready' => $availableBranches !== [],
                'available_branches' => $availableBranches,
                'missing_branches' => $missingBranches,
            ];
        });

        if (!is_array($status)) {
            return [
                'is_ready' => false,
                'available_branches' => [],
                'missing_branches' => self::REQUIRED_BRANCH_KEYS,
            ];
        }

        return [
            'is_ready' => (bool) ($status['is_ready'] ?? false),
            'available_branches' => array_values(array_filter(
                (array) ($status['available_branches'] ?? []),
                static fn ($value): bool => is_string($value) && $value !== ''
            )),
            'missing_branches' => array_values(array_filter(
                (array) ($status['missing_branches'] ?? []),
                static fn ($value): bool => is_string($value) && $value !== ''
            )),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function collectAvailableBranchKeys(string $period): array
    {
        try {
            $branches = DB::table(self::SOURCE_TABLE)
                ->where('posisi', $period)
                ->whereNotNull('kantor_cabang')
                ->whereRaw("TRIM(kantor_cabang) <> ''")
                ->distinct()
                ->pluck('kantor_cabang');

            $available = [];
            foreach ($branches as $branch) {
                $normalized = $this->normalizeBranchKey((string) $branch);
                if ($normalized !== '') {
                    $available[$normalized] = true;
                }
            }

            return array_values(array_keys($available));
        } catch (Throwable) {
            return [];
        }
    }

    private function normalizePeriod(?string $period): ?string
    {
        $trimmed = trim((string) $period);
        if ($trimmed === '') {
            return null;
        }

        $strictNormalized = StrictDateParser::normalize($trimmed);
        if ($strictNormalized !== null) {
            return $strictNormalized;
        }

        try {
            return Carbon::parse($trimmed)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeBranchKey(?string $branch): string
    {
        $value = strtoupper(trim((string) $branch));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/^KC[\.\s-]*/', '', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        foreach (self::REQUIRED_BRANCH_KEYS as $branchKey) {
            if (str_contains($value, $branchKey)) {
                return $branchKey;
            }
        }

        return '';
    }
}
