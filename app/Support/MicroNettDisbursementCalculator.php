<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MicroNettDisbursementCalculator
{
    private const SOURCE_TABLE = 'daily_loan_dinamis';

    private const KUR_RITEL_DESCRIPTION = 'Kredit Mikro - KUR Ritel 2015';

    private const UNASSIGNED_RM = 'RM LAIN';

    private const AREA_6_BRANCHES = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

    private const READ_CACHE_VERSION = 'v1';

    private const MANTRI_READ_CACHE_VERSION = 'v3-mantri-account-basis';

    private const READ_CACHE_SECONDS = 1800;

    /**
     * Report reads share one month scan of the nominative per data version;
     * snapshot builders keep calling kurRm() directly for a fresh read.
     *
     * @return array<int, array<string, int|float|string>>
     */
    public function cachedKurRm(string $period): array
    {
        return $this->rememberNonEmpty('kur-rm', $period, fn (): array => $this->kurRm($period));
    }

    /** @return array<int, array<string, int|float|string>> */
    public function cachedMantri(string $period): array
    {
        return $this->aggregateMantriAccounts($this->cachedMantriAccounts($period));
    }

    /** @return array<int, array<string, int|float|string>> */
    public function cachedMantriAccounts(string $period): array
    {
        return $this->rememberNonEmpty('mantri-accounts', $period, fn (): array => $this->mantriAccounts($period));
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    public function kurRm(string $period): array
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return [];
        }

        $periodDate = Carbon::parse($period);
        $periodStart = $periodDate->copy()->startOfMonth()->toDateString();
        $rows = $this->kurRitelBaseQuery()
            ->whereBetween('periode', [$periodStart, $period])
            ->whereBetween('tgl_realisasi', [$periodStart, $period])
            ->get([
                'periode', 'cabang_normalized', 'cabang1', 'unit_normalized', 'unit1',
                'branch_normalized', 'branch1', 'pn_pemrakarsa1', 'nomor_rekening1',
                'cifno', 'plafon', 'tgl_realisasi',
            ]);
        $rows = $this->deduplicateCurrentAccounts($rows);

        if ($rows->isEmpty()) {
            return [];
        }

        $previousOs = $this->previousKurRitelOsByCif($periodDate, $rows->pluck('cifno')->all());
        $metrics = [];

        foreach ($rows as $row) {
            $cabang = $this->normalizedValue($row->cabang_normalized ?? null, $row->cabang1 ?? null);
            $unit = $this->normalizedValue($row->unit_normalized ?? null, $row->unit1 ?? null);
            $branchCode = $this->normalizedValue($row->branch_normalized ?? null, $row->branch1 ?? null);
            $rm = strtoupper(trim((string) ($row->pn_pemrakarsa1 ?? '')));
            if ($rm === '') {
                $rm = self::UNASSIGNED_RM;
            }

            $key = implode('|', [$cabang, $unit, $branchCode, $rm]);
            $metrics[$key] ??= $this->blankKurMetric($period, $cabang, $unit, $branchCode, $rm);

            $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
            $cif = strtoupper(trim((string) ($row->cifno ?? '')));
            $plafond = (float) ($row->plafon ?? 0);
            // The prior closed exposure belongs to this distinct booking
            // event. Daily-position duplicates have already been collapsed,
            // so apply it once per canonical account, not once per CIF/month.
            $nett = $plafond - (float) ($previousOs[$cif] ?? 0);
            $date = Carbon::parse((string) $row->tgl_realisasi);
            $week = min(4, intdiv(max(0, $date->day - 1), 7) + 1);

            $metrics[$key]['accounts'][$account !== '' ? $account : $cif] = true;
            $metrics[$key]['realisasi_os'] += $nett;
            $metrics[$key]["w{$week}_accounts"][$account !== '' ? $account : $cif] = true;
            $metrics[$key]["w{$week}_realisasi_os"] += $nett;

            $tier = $plafond < 250000000 ? 'lt_250' : ($plafond > 250000000 ? 'gt_250' : null);
            if ($tier !== null) {
                $metrics[$key]["{$tier}_accounts"][$account !== '' ? $account : $cif] = true;
                $metrics[$key]["{$tier}_realisasi_os"] += $nett;
            }
        }

        return array_values(array_map(function (array $metric): array {
            $metric['realisasi_deb'] = count($metric['accounts']);
            unset($metric['accounts']);

            foreach (['w1', 'w2', 'w3', 'w4', 'lt_250', 'gt_250'] as $prefix) {
                $metric["{$prefix}_realisasi_deb"] = count($metric["{$prefix}_accounts"]);
                unset($metric["{$prefix}_accounts"]);
            }

            return $metric;
        }, $metrics));
    }

    /**
     * Nett Mantri Mikro non-Briguna mengikuti workbook Kanwil:
     * pemilik dari PN pengelola, dan pengurang berupa OS CIF akhir bulan
     * sebelumnya untuk rekening yang sudah tidak ada pada posisi berjalan.
     * Nilai negatif dibatasi menjadi nol.
     *
     * @return array<int, array<string, int|float|string>>
     */
    public function mantri(string $period): array
    {
        return $this->aggregateMantriAccounts($this->mantriAccounts($period));
    }

    /**
     * Canonical Mantri bookings shared by landing and KPI, before roster scope.
     *
     * @return array<int, array<string, int|float|string>>
     */
    public function mantriAccounts(string $period): array
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return [];
        }

        $periodDate = Carbon::parse($period);
        $periodStart = $periodDate->copy()->startOfMonth()->toDateString();
        $ownerColumn = Schema::hasColumn(self::SOURCE_TABLE, 'pn_pengelola1')
            ? 'pn_pengelola1' : 'pn_pemrakarsa1';
        $rows = $this->sourceQueryForPeriodRange()
            ->whereBetween('periode', [$periodStart, $period])
            ->whereBetween('tgl_realisasi', [$periodStart, $period])
            ->where('segmen_kinerja', 'MICRO')
            ->whereNotIn('produk_kinerja', ['BRIGUNAMIKRO', 'BRIGUNAKONSUMER'])
            ->get([
                'periode', 'cabang_normalized', 'cabang1', 'unit_normalized', 'unit1',
                'branch_normalized', 'branch1', $ownerColumn, 'nomor_rekening1',
                'cifno', 'plafon', 'tgl_realisasi',
            ]);
        $rows = $this->deduplicateCurrentAccounts($rows, $ownerColumn, true)
            ->sortBy(fn (object $row): string => (string) $row->tgl_realisasi.'|'.$this->canonicalAccount($row->nomor_rekening1 ?? null))
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        $previousOs = $this->previousCompleteOsByCif($periodDate, $rows->pluck('cifno')->all());
        $remainingPreviousOs = $previousOs;
        $bookings = [];

        foreach ($rows as $row) {
            $cabang = $this->normalizedValue($row->cabang_normalized ?? null, $row->cabang1 ?? null);
            $unit = $this->normalizedValue($row->unit_normalized ?? null, $row->unit1 ?? null);
            $branchCode = $this->normalizedValue($row->branch_normalized ?? null, $row->branch1 ?? null);
            $owner = strtoupper(trim((string) ($row->{$ownerColumn} ?? '')));
            if ($owner === '') {
                $owner = self::UNASSIGNED_RM;
            }

            $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
            $cif = strtoupper(trim((string) ($row->cifno ?? '')));
            $plafond = (float) ($row->plafon ?? 0);
            // A CIF may produce several account rows for the same monthly
            // event. Consume its closed prior exposure once across
            // those rows, after daily-position duplicates were collapsed.
            $availablePreviousOs = (float) ($remainingPreviousOs[$cif] ?? 0);
            $nett = max(0.0, $plafond - $availablePreviousOs);
            $remainingPreviousOs[$cif] = max(0.0, $availablePreviousOs - $plafond);
            $bookings[] = [
                'periode' => $period, 'cabang' => $cabang, 'unit' => $unit,
                'branch_code' => $branchCode, 'owner' => $owner,
                'account_key' => $account !== '' ? $account : ($cif !== '' ? 'CIF:'.$cif : 'ROW:'.count($bookings)),
                'cif_key' => $cif, 'plafon' => $plafond,
                'previous_os' => $plafond - $nett, 'net_amount' => $nett,
                'realization_date' => (string) $row->tgl_realisasi,
            ];
        }

        return $bookings;
    }

    /** @param array<int, array<string, int|float|string>> $bookings */
    private function aggregateMantriAccounts(array $bookings): array
    {
        $metrics = [];
        foreach ($bookings as $booking) {
            $key = implode('|', [$booking['cabang'], $booking['unit'], $booking['branch_code'], $booking['owner']]);
            $metrics[$key] ??= array_intersect_key($booking, array_flip(['periode', 'cabang', 'unit', 'branch_code', 'owner']))
                + ['accounts' => [], 'realisasi_os' => 0.0];
            $metrics[$key]['accounts'][$booking['account_key']] = true;
            $metrics[$key]['realisasi_os'] += $booking['net_amount'];
        }

        return array_values(array_map(function (array $metric): array {
            $metric['realisasi_deb'] = count($metric['accounts']);
            unset($metric['accounts']);

            return $metric;
        }, $metrics));
    }

    private function kurRitelQuery(string $period): Builder
    {
        return $this->kurRitelBaseQuery()
            ->where('periode', $period);
    }

    private function kurRitelBaseQuery(): Builder
    {
        return $this->sourceQueryForPeriodRange()
            ->where('segmen_kinerja', 'MICRO')
            ->whereIn('produk_kinerja', ['KURMIKRO', 'KURKECIL'])
            ->whereRaw($this->normalizedDescriptionSql('description').' = ?', [$this->normalizeToken(self::KUR_RITEL_DESCRIPTION)]);
    }

    /**
     * @param  array<int, mixed>  $cifs
     * @return array<string, float>
     */
    private function previousKurRitelOsByCif(Carbon $periodDate, array $cifs): array
    {
        $previousPeriod = DB::table(self::SOURCE_TABLE)
            ->where('periode', '<=', $periodDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString())
            ->where('segmen_kinerja', 'MICRO')
            ->whereNotIn('produk_kinerja', ['BRIGUNAMIKRO', 'BRIGUNAKONSUMER'])
            ->orderByDesc('periode')->value('periode');
        $cifs = array_values(array_unique(array_filter(array_map(
            static fn ($cif): string => strtoupper(trim((string) $cif)),
            $cifs
        ))));

        if ($previousPeriod === null || $cifs === []) {
            return [];
        }

        $query = $this->kurRitelQuery((string) $previousPeriod)
            ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), self::AREA_6_BRANCHES)
            ->whereIn(DB::raw('UPPER(TRIM(cifno))'), $cifs);

        return $this->accountOsByCif($query);
    }

    /** @param array<int, mixed> $cifs */
    private function previousCompleteOsByCif(Carbon $periodDate, array $cifs): array
    {
        $previousPeriod = DB::table(self::SOURCE_TABLE)
            ->where('periode', '<=', $periodDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString())
            ->orderByDesc('periode')->value('periode');
        $cifs = array_values(array_unique(array_filter(array_map(
            static fn ($cif): string => strtoupper(trim((string) $cif)),
            $cifs
        ))));

        if ($previousPeriod === null || $cifs === []) {
            return [];
        }

        $query = DB::table(self::SOURCE_TABLE)
            ->where('periode', $previousPeriod)
            ->whereIn(DB::raw('UPPER(TRIM(cifno))'), $cifs);

        $previousRows = $query->get(['cifno', 'nomor_rekening1', 'baki_debet1']);
        $accounts = $previousRows->map(fn (object $row): string => $this->canonicalAccount($row->nomor_rekening1 ?? null))
            ->filter(fn (string $account): bool => $account !== '')->unique()->values();
        $currentAccounts = [];
        if ($accounts->isNotEmpty()) {
            $accountSql = DB::getDriverName() === 'mysql'
                ? "TRIM(LEADING '0' FROM UPPER(TRIM(nomor_rekening1)))"
                : "LTRIM(UPPER(TRIM(nomor_rekening1)), '0')";
            $currentAccounts = DB::table(self::SOURCE_TABLE)
                ->where('periode', $periodDate->toDateString())
                // Presence is checked across all CIFs/branches/products: a
                // transferred or relabelled live account is still open.
                ->whereIn(DB::raw("COALESCE(NULLIF({$accountSql}, ''), '0')"), $accounts->all())
                ->pluck('nomor_rekening1')
                ->mapWithKeys(fn ($account): array => [$this->canonicalAccount($account) => true])->all();
        }

        return $this->accountRowsOsByCif($previousRows->reject(function (object $row) use ($currentAccounts): bool {
            $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);

            return $account !== '' && isset($currentAccounts[$account]);
        }));
    }

    /** @return array<string, float> */
    private function accountOsByCif(Builder $query): array
    {
        return $this->accountRowsOsByCif($query->get(['cifno', 'nomor_rekening1', 'baki_debet1']));
    }

    /** @param Collection<int, object> $rows @return array<string, float> */
    private function accountRowsOsByCif(Collection $rows): array
    {
        $accounts = [];
        $missingAccountSequence = 0;

        foreach ($rows as $row) {
            $cif = strtoupper(trim((string) ($row->cifno ?? '')));
            if ($cif === '') {
                continue;
            }

            $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
            // A missing account number cannot establish that two rows are duplicates.
            $key = $account !== '' ? $cif.'|'.$account : $cif.'|ROW:'.(++$missingAccountSequence);
            $amount = (float) ($row->baki_debet1 ?? 0);
            $accounts[$key] = isset($accounts[$key]) ? max($accounts[$key], $amount) : $amount;
        }

        $totals = [];
        foreach ($accounts as $key => $amount) {
            $cif = strstr($key, '|', true);
            $totals[$cif] = ($totals[$cif] ?? 0.0) + $amount;
        }

        return $totals;
    }

    /** @param Collection<int, object> $rows @return Collection<int, object> */
    private function deduplicateCurrentAccounts(Collection $rows, string $ownerColumn = 'pn_pemrakarsa1', bool $latestOwner = false): Collection
    {
        $accounts = [];
        $orderedRows = $rows->values()->sortBy(static fn (object $row, int $index): string => implode('|', [
            (string) ($row->periode ?? ''),
            (string) ($row->tgl_realisasi ?? ''),
            str_pad((string) $index, 12, '0', STR_PAD_LEFT),
        ]));

        foreach ($orderedRows as $index => $row) {
            $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
            $key = $account !== '' ? 'ACCOUNT:'.$account : 'ROW:'.$index;

            if (! isset($accounts[$key])) {
                $accounts[$key] = clone $row;
                continue;
            }

            $current = $accounts[$key];
            if ((float) ($row->plafon ?? 0) > (float) ($current->plafon ?? 0)) {
                $current->plafon = $row->plafon;
            }
            if (($latestOwner || trim((string) ($current->{$ownerColumn} ?? '')) === '') && trim((string) ($row->{$ownerColumn} ?? '')) !== '') {
                $current->{$ownerColumn} = $row->{$ownerColumn};
                if ($latestOwner) {
                    // Mantri is attributed to the latest observed manager and
                    // that position's territory; KUR keeps booking ownership.
                    foreach (['cabang_normalized', 'cabang1', 'unit_normalized', 'unit1', 'branch_normalized', 'branch1'] as $column) {
                        $current->{$column} = $row->{$column} ?? null;
                    }
                }
            }
        }

        return collect(array_values($accounts));
    }

    private function canonicalAccount(mixed $account): string
    {
        $account = strtoupper(trim((string) $account));
        if ($account === '') {
            return '';
        }

        $canonical = ltrim($account, '0');

        return $canonical !== '' ? $canonical : '0';
    }

    private function blankKurMetric(string $period, string $cabang, string $unit, string $branchCode, string $rm): array
    {
        $metric = [
            'periode' => $period,
            'cabang' => $cabang,
            'unit' => $unit,
            'branch_code' => $branchCode,
            'rm' => $rm,
            'accounts' => [],
            'realisasi_os' => 0.0,
        ];

        foreach (['w1', 'w2', 'w3', 'w4', 'lt_250', 'gt_250'] as $prefix) {
            $metric["{$prefix}_accounts"] = [];
            $metric["{$prefix}_realisasi_os"] = 0.0;
        }

        return $metric;
    }

    private function normalizedValue(mixed $normalized, mixed $fallback): string
    {
        $value = trim((string) $normalized);

        return strtoupper($value !== '' ? $value : trim((string) $fallback));
    }

    /** @param callable(): array $compute */
    private function rememberNonEmpty(string $kind, string $period, callable $compute): array
    {
        $key = implode(':', [
            'micro-nett', str_starts_with($kind, 'mantri') ? self::MANTRI_READ_CACHE_VERSION : self::READ_CACHE_VERSION, $kind,
            ReportCacheVersion::get('pinjaman'), Carbon::parse($period)->toDateString(),
        ]);
        $cached = Cache::get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $build = function () use ($key, $compute): array {
            $cached = Cache::get($key);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }

            $result = $compute();
            // An empty month may still be loading; never pin "no data".
            if ($result !== []) {
                Cache::put($key, $result, now()->addSeconds(self::READ_CACHE_SECONDS));
            }

            return $result;
        };

        // Concurrent report tabs wait for one scan instead of each running it.
        try {
            return Cache::lock($key.':build', 300)->block(120, $build);
        } catch (LockTimeoutException) {
            return $build();
        }
    }

    private function sourceQueryForPeriodRange(): Builder
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::query()->fromRaw(self::SOURCE_TABLE.' FORCE INDEX (idx_snapshot_filter_optimized)');
        }

        return DB::table(self::SOURCE_TABLE);
    }

    private function normalizedDescriptionSql(string $column): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE({$column}, '')), ' ', ''), '-', ''), '_', ''), CHAR(160), ''))";
    }

    private function normalizeToken(string $value): string
    {
        return strtoupper((string) preg_replace('/[\s\-_\x{00A0}]+/u', '', trim($value)));
    }
}
