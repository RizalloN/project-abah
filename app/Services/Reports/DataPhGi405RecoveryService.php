<?php

namespace App\Services\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** GI405 is a signed YTD income position, not a movement in PH principal. */
class DataPhGi405RecoveryService
{
    private const ACCOUNTS = ['418020000010', '418020000013'];

    private const BRANCHES = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

    private array $periodRows = [];

    public function availablePeriods(): Collection
    {
        $periods = collect();
        if (Schema::hasTable('gi405_recovery')) {
            $periods = $periods->merge(DB::table('gi405_recovery')->distinct()->pluck('periode'));
        }
        if (Schema::hasTable('gi405_singlerow')) {
            $periods = $periods->merge($this->ledgerQuery()->distinct()->pluck('periode'));
        }

        return $periods->filter()->map(fn ($period): string => substr((string) $period, 0, 10))->unique()->sortDesc()->values();
    }

    public function unitRows(string $period, array $branches = [], string $unit = 'all'): array
    {
        $rows = $this->periodRows[$period] ??= $this->loadPeriod($period);
        $branches = $branches === [] ? self::BRANCHES : $branches;
        $unitKey = $this->unitKey($unit);

        return array_values(array_filter($rows, fn (array $row): bool => in_array($row['branch'], $branches, true)
            && (strtolower(trim($unit)) === 'all' || $unit === '' || $this->unitKey($row['unit']) === $unitKey)));
    }

    private function loadPeriod(string $period): array
    {
        $references = [];
        if (Schema::hasTable('referensi_uker')) {
            foreach (DB::table('referensi_uker')->get(['kode_uker', 'nama_uker', 'nama_cabang']) as $reference) {
                $references[$this->code($reference->kode_uker)] = $reference;
            }
        }

        $official = [];
        if (Schema::hasTable('gi405_recovery')) {
            foreach (DB::table('gi405_recovery')->where('periode', $period)
                ->get(['kode_uker', 'nama_uker', 'pendapatan_koreksi_ppap_dr_angsuran_ph']) as $row) {
                $code = $this->code($row->kode_uker);
                $reference = $references[$code] ?? null;
                $branch = $this->branch($reference->nama_cabang ?? '');
                if ($branch === null || ! is_numeric($row->pendapatan_koreksi_ppap_dr_angsuran_ph)) {
                    continue;
                }
                $official[$code] ??= $this->emptyRow($branch, $reference->nama_uker ?? $row->nama_uker, $code, 'gi405_recovery');
                // Preserve debit corrections: ABS() per row would inflate revenue.
                $official[$code]['total'] -= (float) $row->pendapatan_koreksi_ppap_dr_angsuran_ph;
            }
        }

        $ledger = [];
        $hasClaimAccount = false;
        if (Schema::hasTable('gi405_singlerow')) {
            foreach ($this->ledgerQuery()->where('periode', $period)
                ->get(['branch', 'nama_cabang', 'nama_uker', 'account_number', 'ending_balance']) as $row) {
                $code = $this->code($row->branch);
                $reference = $references[$code] ?? null;
                $branch = $this->branch($reference->nama_cabang ?? $row->nama_cabang);
                if ($branch === null || ! is_numeric($row->ending_balance)) {
                    continue;
                }
                $ledger[$code] ??= $this->emptyRow($branch, $reference->nama_uker ?? $row->nama_uker, $code, 'gi405_singlerow');
                $ledger[$code]['non_klaim'] ??= 0.0;
                $ledger[$code]['klaim'] ??= 0.0;
                $component = trim($row->account_number) === self::ACCOUNTS[1] ? 'klaim' : 'non_klaim';
                $hasClaimAccount = $hasClaimAccount || $component === 'klaim';
                $ledger[$code][$component] -= (float) $row->ending_balance;
                $ledger[$code]['total'] -= (float) $row->ending_balance;
            }
        }

        foreach ($ledger as $code => $row) {
            $official[$code] ??= $this->emptyRow($row['branch'], $row['unit'], (string) $code, 'gi405_singlerow');
            if ($official[$code]['source'] === 'gi405_singlerow') {
                $official[$code]['total'] = $row['total'];
            }
            // Some years omit/reclassify the claim GL. Absence is not proof of zero claims.
            if ($hasClaimAccount && (int) round($row['total'] * 100) === (int) round($official[$code]['total'] * 100)) {
                $official[$code]['non_klaim'] = round($row['non_klaim'], 2);
                $official[$code]['klaim'] = round($row['klaim'], 2);
                $official[$code]['split_available'] = true;
            }
        }
        foreach ($official as &$row) {
            $row['total'] = round($row['total'], 2);
        }

        return array_values($official);
    }

    private function ledgerQuery()
    {
        return DB::table('gi405_singlerow')
            ->whereIn(DB::raw('TRIM(account_number)'), self::ACCOUNTS)
            ->where(fn ($query) => $query->where('periode', '<', '2026-01-01')
                ->orWhereRaw('TRIM(account_number) = ?', [self::ACCOUNTS[0]]))
            ->whereRaw("UPPER(TRIM(currency)) = 'IDR'")
            ->whereRaw("UPPER(TRIM(posting_control)) = '*POST'");
    }

    private function emptyRow(string $branch, ?string $unit, string $code, string $source): array
    {
        return [
            'branch' => $branch,
            'unit' => $this->unitKey((string) $unit),
            'unit_code' => $code,
            'total' => 0.0,
            'non_klaim' => null,
            'klaim' => null,
            'split_available' => false,
            'source' => $source,
        ];
    }

    private function code(mixed $value): string
    {
        return ltrim(trim((string) $value), '0') ?: '0';
    }

    private function branch(?string $value): ?string
    {
        foreach (self::BRANCHES as $branch) {
            if (str_contains(strtoupper((string) $value), strtoupper(substr($branch, 3)))) {
                return $branch;
            }
        }

        return null;
    }

    private function unitKey(string $value): string
    {
        return trim(strtoupper(preg_replace('/^\s*\d+\s*--\s*/', '', $value) ?? $value));
    }
}
