<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MicroNettDisbursementCalculator
{
    private const SOURCE_TABLE = 'daily_loan_dinamis';

    private const KUR_RITEL_DESCRIPTION = 'Kredit Mikro - KUR Ritel 2015';

    private const UNASSIGNED_RM = 'RM LAIN';

    private const AREA_6_BRANCHES = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

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
        $rows = $this->kurRitelQuery($period)
            ->whereBetween('tgl_realisasi', [$periodStart, $period])
            ->get([
                'cabang_normalized', 'cabang1', 'unit_normalized', 'unit1',
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

            $account = strtoupper(trim((string) ($row->nomor_rekening1 ?? '')));
            $cif = strtoupper(trim((string) ($row->cifno ?? '')));
            $plafond = (float) ($row->plafon ?? 0);
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
     * Nett Mantri mengikuti workbook Kanwil: seluruh segmen non-Briguna,
     * pemilik dari PN pemrakarsa, dan pengurang berupa total OS CIF posisi
     * akhir bulan sebelumnya. Nilai negatif dibatasi menjadi nol.
     *
     * @return array<int, array<string, int|float|string>>
     */
    public function mantri(string $period): array
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return [];
        }

        $periodDate = Carbon::parse($period);
        $periodStart = $periodDate->copy()->startOfMonth()->toDateString();
        $rows = DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->whereBetween('tgl_realisasi', [$periodStart, $period])
            ->where('segmen_kinerja', 'MICRO')
            ->whereNotIn('produk_kinerja', ['BRIGUNAMIKRO', 'BRIGUNAKONSUMER'])
            ->get([
                'cabang_normalized', 'cabang1', 'unit_normalized', 'unit1',
                'branch_normalized', 'branch1', 'pn_pemrakarsa1', 'nomor_rekening1',
                'cifno', 'plafon',
            ]);
        $rows = $this->deduplicateCurrentAccounts($rows);

        if ($rows->isEmpty()) {
            return [];
        }

        $previousOs = $this->previousCompleteOsByCif($periodDate, $rows->pluck('cifno')->all());
        $metrics = [];

        foreach ($rows as $row) {
            $cabang = $this->normalizedValue($row->cabang_normalized ?? null, $row->cabang1 ?? null);
            $unit = $this->normalizedValue($row->unit_normalized ?? null, $row->unit1 ?? null);
            $branchCode = $this->normalizedValue($row->branch_normalized ?? null, $row->branch1 ?? null);
            $owner = strtoupper(trim((string) ($row->pn_pemrakarsa1 ?? '')));
            if ($owner === '') {
                $owner = self::UNASSIGNED_RM;
            }

            $key = implode('|', [$cabang, $unit, $branchCode, $owner]);
            $metrics[$key] ??= [
                'periode' => $period,
                'cabang' => $cabang,
                'unit' => $unit,
                'branch_code' => $branchCode,
                'owner' => $owner,
                'accounts' => [],
                'realisasi_os' => 0.0,
            ];

            $account = strtoupper(trim((string) ($row->nomor_rekening1 ?? '')));
            $cif = strtoupper(trim((string) ($row->cifno ?? '')));
            $plafond = (float) ($row->plafon ?? 0);
            $nett = max(0.0, $plafond - (float) ($previousOs[$cif] ?? 0));
            $metrics[$key]['accounts'][$account !== '' ? $account : $cif] = true;
            $metrics[$key]['realisasi_os'] += $nett;
        }

        return array_values(array_map(function (array $metric): array {
            $metric['realisasi_deb'] = count($metric['accounts']);
            unset($metric['accounts']);

            return $metric;
        }, $metrics));
    }

    private function kurRitelQuery(string $period)
    {
        return DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
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
            ->max('periode');
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
            ->max('periode');
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

        return $this->accountOsByCif($query);
    }

    /** @return array<string, float> */
    private function accountOsByCif(Builder $query): array
    {
        $accountSql = "UPPER(TRIM(COALESCE(nomor_rekening1, '')))";
        $accounts = $query
            ->selectRaw('UPPER(TRIM(cifno)) as cif_key')
            // A missing account number cannot establish that two rows are duplicates.
            ->selectRaw("CASE WHEN MIN({$accountSql}) = '' THEN SUM(COALESCE(baki_debet1, 0)) ELSE MAX(COALESCE(baki_debet1, 0)) END as account_os")
            ->groupByRaw('UPPER(TRIM(cifno))')
            ->groupByRaw($accountSql);

        return DB::query()->fromSub($accounts, 'accounts')
            ->selectRaw('cif_key, SUM(account_os) as previous_os')
            ->groupBy('cif_key')
            ->pluck('previous_os', 'cif_key')
            ->map(static fn ($value): float => (float) $value)
            ->all();
    }

    /** @param Collection<int, object> $rows @return Collection<int, object> */
    private function deduplicateCurrentAccounts(Collection $rows): Collection
    {
        $accounts = [];
        foreach ($rows as $index => $row) {
            $account = strtoupper(trim((string) ($row->nomor_rekening1 ?? '')));
            $key = json_encode([
                $this->normalizedValue($row->cabang_normalized ?? null, $row->cabang1 ?? null),
                $this->normalizedValue($row->unit_normalized ?? null, $row->unit1 ?? null),
                $this->normalizedValue($row->branch_normalized ?? null, $row->branch1 ?? null),
                strtoupper(trim((string) ($row->pn_pemrakarsa1 ?? ''))) ?: self::UNASSIGNED_RM,
                $account !== '' ? 'ACCOUNT:'.$account : 'ROW:'.$index,
            ]);
            if (! isset($accounts[$key]) || (float) ($row->plafon ?? 0) > (float) ($accounts[$key]->plafon ?? 0)) {
                $accounts[$key] = $row;
            }
        }

        return collect(array_values($accounts));
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

    private function normalizedDescriptionSql(string $column): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE({$column}, '')), ' ', ''), '-', ''), '_', ''), CHAR(160), ''))";
    }

    private function normalizeToken(string $value): string
    {
        return strtoupper((string) preg_replace('/[\s\-_\x{00A0}]+/u', '', trim($value)));
    }
}
