<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Notice kualitas data realisasi untuk laporan produktivitas.
 *
 * Posisi produktivitas dapat berasal dari Daily Loan Dinamis (H-2) atau LW321PN
 * (H-1) yang dimaterialisasi ke tabel yang sama. Pada tanggal realisasi terbaru
 * sumber kadang belum lengkap: PN pengelola/pemrakarsa kosong (produktivitas
 * belum teratribusi) atau kolektibilitas rekening baru tidak lancar padahal belum
 * menunggak. Kelas ini hanya membaca dan melaporkan; angka laporan tidak diubah.
 */
final class ProductivitySourceQuality
{
    private const SOURCE_TABLE = 'daily_loan_dinamis';

    /** Versi data pinjaman ikut di cache key, jadi TTL panjang tetap aman. */
    private const CACHE_SECONDS = 21600;

    private const SAMPLE_LIMIT = 10;

    /** Realisasi H-0/H-1 posisi: paling sering belum lengkap dari sumber. */
    private const LATEST_WINDOW_DAYS = 1;

    /**
     * @param  array<int,string>  $products  produk_kinerja (kosong = semua produk segmen)
     * @param  array<int,string>  $branches  cabang_normalized (kosong = semua cabang)
     * @return array<string,mixed>|null
     */
    public function summarize(?string $period, string $segment, array $products = [], array $branches = []): ?array
    {
        $period = StrictDateParser::normalize(trim((string) $period));
        if ($period === null || ! Schema::hasTable(self::SOURCE_TABLE)) {
            return null;
        }

        $branches = $this->scopedBranches($branches);
        if ($branches === []) {
            return null;
        }

        try {
            $dataset = $this->periodDataset($period);
        } catch (Throwable $e) {
            // Notice bersifat informatif; kegagalannya tidak boleh menggagalkan laporan.
            Log::warning('Gagal menghitung kualitas sumber produktivitas: '.$e->getMessage(), ['period' => $period]);

            return null;
        }

        $segment = strtoupper(trim($segment));
        $products = array_fill_keys(array_filter(array_map(
            static fn ($product): string => Lw321DailyLoanMapper::normalizeToken((string) $product),
            $products
        )), true);
        $branchSet = $branches === null ? null : array_fill_keys($branches, true);
        $matches = static fn (array $row): bool => $row['segment'] === $segment
            && ($products === [] || isset($products[$row['product']]))
            && ($branchSet === null || isset($branchSet[$row['branch']]));

        $summary = [
            'realized_accounts' => 0,
            'realized_plafon' => 0.0,
            'blank_pn_accounts' => 0,
            'blank_pn_plafon' => 0.0,
            'blank_pn_latest_accounts' => 0,
            'kolek_issue_accounts' => 0,
            'kolek_issue_plafon' => 0.0,
            'kolek_age_mismatch_accounts' => 0,
            'latest_realization' => null,
        ];
        foreach (array_filter($dataset['groups'], $matches) as $group) {
            foreach ($summary as $key => $value) {
                $summary[$key] = $key === 'latest_realization'
                    ? max((string) $value, (string) $group[$key]) ?: null
                    : $value + $group[$key];
            }
        }

        $samples = array_values(array_slice(array_filter($dataset['issues'], $matches), 0, self::SAMPLE_LIMIT));

        return [
            'period' => $period,
            'month_start' => $dataset['month_start'],
            'source' => $dataset['from_lw321'] ? 'LW321PN' : 'Daily Loan Dinamis',
            'from_lw321' => $dataset['from_lw321'],
            'reference_period' => $dataset['reference_period'],
            ...$summary,
            'has_issues' => $summary['blank_pn_accounts'] > 0 || $summary['kolek_issue_accounts'] > 0,
            'samples' => $samples,
        ];
    }

    /**
     * Satu scan realisasi bulan berjalan untuk seluruh segmen posisi ini.
     *
     * @return array{month_start:string,from_lw321:bool,reference_period:?string,groups:array<int,array<string,mixed>>,issues:array<int,array<string,mixed>>}
     */
    private function periodDataset(string $period): array
    {
        $cacheKey = 'productivity_source_quality:v1:'.ReportCacheVersion::get('pinjaman').':'.$period;

        return Cache::remember($cacheKey, self::CACHE_SECONDS, fn (): array => $this->buildPeriodDataset($period));
    }

    /**
     * @return array{month_start:string,from_lw321:bool,reference_period:?string,groups:array<int,array<string,mixed>>,issues:array<int,array<string,mixed>>}
     */
    private function buildPeriodDataset(string $period): array
    {
        $monthStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $latestStart = Carbon::parse($period)->subDays(self::LATEST_WINDOW_DAYS)->toDateString();
        $lwPrefix = Lw321DailyLoanSyncService::TARGET_ID_PREFIX;
        $groups = [];
        $issues = [];
        $fromLw = false;
        $realized = 0;

        $rows = DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->whereBetween('tgl_realisasi', [$monthStart, $period])
            ->select([
                'uniqueid_namareport', 'nomor_rekening1', 'nama_debitur1', 'cabang1', 'unit1', 'cabang_normalized',
                'segmen_kinerja', 'produk_kinerja', 'tgl_realisasi', 'plafon', 'kolek', 'umur_tunggakan',
                'pn_pengelola1', 'pn_pemrakarsa1',
            ])
            ->cursor();

        foreach ($rows as $row) {
            $realized++;
            $fromLw = $fromLw || str_starts_with((string) $row->uniqueid_namareport, $lwPrefix);
            $segment = strtoupper($this->text($row->segmen_kinerja) ?? '');
            $product = Lw321DailyLoanMapper::normalizeToken($row->produk_kinerja);
            $branch = strtoupper($this->text($row->cabang_normalized) ?? $this->text($row->cabang1) ?? '');
            $realizationDate = substr((string) $row->tgl_realisasi, 0, 10);
            $plafon = (float) ($row->plafon ?? 0);
            $kolek = $this->text($row->kolek);
            $age = $row->umur_tunggakan === null ? null : (int) $row->umur_tunggakan;
            $blankPn = $this->text($row->pn_pengelola1) === null || $this->text($row->pn_pemrakarsa1) === null;
            $kolekIssue = $kolek !== '1';
            // Non-lancar padahal belum menunggak: tidak sejalan dengan umur tunggakan.
            $kolekAgeMismatch = $kolekIssue && ($kolek === null || ($age ?? 0) <= 0);

            $key = $segment.'|'.$product.'|'.$branch;
            $groups[$key] ??= [
                'segment' => $segment,
                'product' => $product,
                'branch' => $branch,
                'realized_accounts' => 0,
                'realized_plafon' => 0.0,
                'blank_pn_accounts' => 0,
                'blank_pn_plafon' => 0.0,
                'blank_pn_latest_accounts' => 0,
                'kolek_issue_accounts' => 0,
                'kolek_issue_plafon' => 0.0,
                'kolek_age_mismatch_accounts' => 0,
                'latest_realization' => null,
            ];
            $group = &$groups[$key];
            $group['realized_accounts']++;
            $group['realized_plafon'] += $plafon;
            $group['latest_realization'] = max((string) $group['latest_realization'], $realizationDate) ?: null;
            if ($blankPn) {
                $group['blank_pn_accounts']++;
                $group['blank_pn_plafon'] += $plafon;
                $group['blank_pn_latest_accounts'] += $realizationDate >= $latestStart ? 1 : 0;
            }
            if ($kolekIssue) {
                $group['kolek_issue_accounts']++;
                $group['kolek_issue_plafon'] += $plafon;
                $group['kolek_age_mismatch_accounts'] += $kolekAgeMismatch ? 1 : 0;
            }
            unset($group);

            if ($blankPn || $kolekIssue) {
                $issueLabels = [];
                if ($blankPn) {
                    $issueLabels[] = 'PN kosong';
                }
                if ($kolekIssue) {
                    $issueLabels[] = 'Kolek '.($kolek ?? '-').($kolekAgeMismatch ? ' (belum menunggak)' : '');
                }
                $issues[] = [
                    'segment' => $segment,
                    'product' => $product,
                    'branch' => $branch,
                    'account' => $this->text($row->nomor_rekening1),
                    'debtor' => $this->text($row->nama_debitur1),
                    'unit' => $this->text($row->unit1),
                    'realization_date' => $realizationDate,
                    'plafon' => $plafon,
                    'kolek' => $kolek,
                    'arrears_age' => $age,
                    'pn_manager' => $this->text($row->pn_pengelola1),
                    'pn_initiator' => $this->text($row->pn_pemrakarsa1),
                    'issues' => $issueLabels,
                ];
            }
        }

        if ($realized === 0) {
            $fromLw = DB::table(self::SOURCE_TABLE)
                ->where('periode', $period)
                ->where('uniqueid_namareport', 'like', $lwPrefix.'%')
                ->exists();
        }

        // Terbaru dulu, plafon terbesar dulu: yang paling berdampak tampil di contoh.
        usort($issues, static fn (array $left, array $right): int => [$right['realization_date'], $right['plafon']]
            <=> [$left['realization_date'], $left['plafon']]);
        // Top-N per kelompok sudah memuat top-N gabungan filter apa pun.
        $kept = [];
        $issues = array_values(array_filter($issues, static function (array $issue) use (&$kept): bool {
            $key = $issue['segment'].'|'.$issue['product'].'|'.$issue['branch'];
            $kept[$key] = ($kept[$key] ?? 0) + 1;

            return $kept[$key] <= self::SAMPLE_LIMIT;
        }));

        return [
            'month_start' => $monthStart,
            'from_lw321' => $fromLw,
            'reference_period' => $fromLw ? $this->latestNativeDailyPeriodBefore($period) : null,
            'groups' => array_values($groups),
            'issues' => $issues,
        ];
    }

    private function latestNativeDailyPeriodBefore(string $period): ?string
    {
        $value = DB::table(self::SOURCE_TABLE)
            ->where('periode', '<', $period)
            ->where(function (Builder $query): void {
                $query->whereNull('uniqueid_namareport')
                    ->orWhere('uniqueid_namareport', 'not like', Lw321DailyLoanSyncService::TARGET_ID_PREFIX.'%');
            })
            ->max('periode');

        return StrictDateParser::normalize(trim((string) $value));
    }

    /**
     * Cabang yang diminta dipersempit ke scope akses user. null = tanpa filter cabang.
     *
     * @param  array<int,string>  $branches
     * @return array<int,string>|null
     */
    private function scopedBranches(array $branches): ?array
    {
        $branches = array_values(array_unique(array_filter(array_map(
            static fn ($branch): string => strtoupper(trim((string) $branch)),
            $branches
        ))));

        $scope = UserBranchScope::current();
        if ($scope === null) {
            return $branches === [] ? null : $branches;
        }

        if ($branches === []) {
            return [$scope['upper_label']];
        }

        return array_values(array_filter(
            $branches,
            static fn (string $branch): bool => str_contains($branch, $scope['upper_label']) || str_contains($scope['upper_label'], $branch)
        ));
    }

    private function text(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' || $normalized === '-' ? null : $normalized;
    }
}
