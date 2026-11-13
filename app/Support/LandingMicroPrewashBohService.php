<?php

namespace App\Support;

use App\Services\Import\PipelinePrewashDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LandingMicroPrewashBohService
{
    private const TABLE = 'pipeline_prewash_boh';
    private const VISITS = 'evaluasi_aktivitas_kunjungan_pemasar';
    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agt', 'sep', 'okt', 'nov', 'des'];

    public function payload(?array $branchScope): array
    {
        $empty = ['available' => false, 'groups' => ['bo' => [], 'unit' => []], 'summary' => [], 'records' => []];
        if (! Schema::hasTable(self::TABLE) || ! DB::table(self::TABLE)->exists()) {
            return $empty + [
                'empty_reason' => 'not_imported',
                'empty_title' => 'TL Pipeline Prewash PIC BOH belum diimport',
                'import_report' => 'Pipeline Prewash PIC BOH',
                'message' => 'Impor laporan Pipeline Prewash PIC BOH melalui menu Import. Tab ini memakai dataset PIC BOH tersendiri.',
            ];
        }
        $visitPeriod = Schema::hasTable(self::VISITS) ? DB::table(self::VISITS)->max('periode') : null;
        $year = $visitPeriod ? (int) substr($visitPeriod, 0, 4) : (int) DB::table(self::TABLE)->max('tahun');
        $base = $this->pipelineQuery($year, $branchScope);
        $records = $base->orderBy('uniqueid_namareport')->get()->map(fn (object $row): array => (array) $row)->all();
        if ($records === []) {
            return $empty + ['year' => $year ?: null, 'visit_period' => $visitPeriod, 'empty_reason' => 'no_matching_scope', 'message' => 'Belum ada Pipeline Prewash PIC BOH untuk tahun laporan kunjungan dan cabang yang dipilih.'];
        }

        [$loanSource, $loanPeriod] = $this->loanSource();
        $cifs = array_values(array_unique(array_filter(array_map(fn (array $row): string => trim((string) $row['cif']), $records), fn (string $cif): bool => $cif !== '')));
        $visits = $visitPeriod ? $this->visits($visitPeriod, $year, $branchScope) : [];
        // Without a visit report, the realization year cannot be reconciled.
        $loansAvailable = $visitPeriod !== null && $loanSource !== null;
        $loans = $loansAvailable ? $this->loans($cifs, $loanSource, $loanPeriod, $year, $branchScope) : [];
        foreach ($records as &$row) {
            $cif = trim((string) $row['cif']);
            foreach (self::MONTHS as $month) {
                $row[$month] = $visits[$cif][$month]['status'] ?? null;
            }
            $row['jumlah_kunjungan'] = $visitPeriod ? count(array_filter(array_intersect_key($row, array_flip(self::MONTHS)), fn ($status): bool => $status === 'DONE')) : null;
            $row['kunjungan'] = $row['jumlah_kunjungan'] === null ? null : ($row['jumlah_kunjungan'] > 0 ? 'DONE' : 'NOT');
            $row['plafond_real'] = $loansAvailable ? (array_key_exists($cif, $loans) ? $loans[$cif] : '0.00') : null;
            $row['status'] = $row['plafond_real'] === null ? null : ((float) $row['plafond_real'] > 0 ? 'Real' : 'Belum');
        }
        unset($row);

        $groups = ['bo' => [], 'unit' => []];
        foreach (['bo', 'unit'] as $dimension) {
            $buckets = [];
            foreach ($records as $row) {
                $key = $row['nama_kanca'].($dimension === 'unit' ? '|'.$row['branch'] : '');
                $buckets[$key][] = $row;
            }
            ksort($buckets);
            foreach ($buckets as $rows) {
                $scope = collect(UserBranchScope::branchScopes())->first(fn (array $scope): bool => UserBranchScope::matches($rows[0]['nama_kanca'], $scope));
                $groups[$dimension][] = [
                    'branch_code' => $scope['code4'] ?? null,
                    'branch_name' => $rows[0]['nama_kanca'],
                    'unit_name' => $rows[0]['nama_unit'],
                    'unit_code' => $rows[0]['branch'],
                ] + $this->aggregate($rows, $visitPeriod !== null, $loansAvailable);
            }
        }

        return [
            'available' => true, 'year' => $year, 'visit_period' => $visitPeriod,
            'loan_source' => $loanSource, 'loan_period' => $loanPeriod,
            'message' => ! $visitPeriod ? 'Laporan kunjungan belum tersedia; evaluasi kunjungan dan realisasi belum dapat dihitung.'
                : (! $loanSource ? 'Laporan pinjaman belum tersedia; realisasi belum dapat dihitung.' : null),
            'groups' => $groups, 'summary' => $this->aggregate($records, $visitPeriod !== null, $loansAvailable), 'records' => $records,
        ];
    }

    private function loanSource(): array
    {
        $daily = Schema::hasTable('daily_loan_dinamis') ? DB::table('daily_loan_dinamis')->max('periode') : null;
        $lw = Schema::hasTable('lw321pn') ? DB::table('lw321pn')->max('periode') : null;

        return $daily && (! $lw || $daily >= $lw) ? ['daily_loan_dinamis', $daily] : ($lw ? ['lw321pn', $lw] : [null, null]);
    }

    private function pipelineQuery(int $year, ?array $scope): Builder
    {
        $scopes = $scope ? [$scope] : UserBranchScope::branchScopes();

        return DB::table(self::TABLE)->where('tahun', $year)->whereIn('nama_kanca', collect($scopes)->flatMap(
            fn (array $branch): array => [$branch['label'], $branch['upper_label']]
        )->all());
    }

    private function visits(string $period, int $year, ?array $scope): array
    {
        $result = [];
        // One source scan even for large pipelines; the existing visit index is period/branch.
        $query = DB::table(self::VISITS)->where('periode', $period)
            ->whereIn('cifno', $this->pipelineQuery($year, $scope)->select('cif'))
            ->where('tgl_kunjungan', '>=', $year.'-01-01')->where('tgl_kunjungan', '<', ($year + 1).'-01-01');
        $this->scopeCodes($query, 'kode_main_branch', $scope);
        foreach ($query->select(['cifno', 'tgl_kunjungan', 'status_kunjungan', 'id_aktivitas'])->get() as $visit) {
            $cif = trim((string) $visit->cifno);
            $month = self::MONTHS[(int) substr($visit->tgl_kunjungan, 5, 2) - 1];
            $candidate = ['status' => strtoupper(trim((string) $visit->status_kunjungan)), 'date' => $visit->tgl_kunjungan, 'id' => (string) $visit->id_aktivitas];
            $previous = $result[$cif][$month] ?? null;
            // DONE is sticky for a month; otherwise latest date/activity wins.
            if ($previous === null || ($candidate['status'] === 'DONE' && $previous['status'] !== 'DONE')
                || ($candidate['status'] !== 'DONE' && $previous['status'] !== 'DONE' && $this->later($candidate, $previous))) {
                $result[$cif][$month] = $candidate;
            }
        }

        return $result;
    }

    private function later(array $candidate, array $previous): bool
    {
        return [$candidate['date'], strlen($candidate['id']), $candidate['id'], $candidate['status']]
            > [$previous['date'], strlen($previous['id']), $previous['id'], $previous['status']];
    }

    private function loans(array $cifs, string $table, string $period, int $year, ?array $scope): array
    {
        $daily = $table === 'daily_loan_dinamis';
        $account = $daily ? 'nomor_rekening1' : 'no_rekening';
        $amount = $daily ? 'plafon' : 'plafon_dalam_idr';
        $result = [];
        foreach (array_chunk($cifs, 500) as $chunk) {
            $query = DB::table($table)->where('periode', $period)->whereIn('cifno', $chunk)
                ->where('tgl_realisasi', '>=', $year.'-01-01')->where('tgl_realisasi', '<', ($year + 1).'-01-01')
                ->whereNotNull($account)->where($account, '<>', '');
            $this->scopeCodes($query, $daily ? 'kode_cabang1' : 'kode_kanca', $scope);
            $accounts = $query->select('cifno', $account)->selectRaw("MIN({$amount}) as min_amount, MAX({$amount}) as amount, COUNT(*) as row_count, COUNT({$amount}) as amount_count")
                ->groupBy('cifno', $account)->get();
            $unknown = [];
            foreach ($accounts as $loan) {
                $cif = trim((string) $loan->cifno);
                if ($loan->amount === null || $loan->amount_count != $loan->row_count || (string) $loan->min_amount !== (string) $loan->amount) {
                    $unknown[$cif] = true;
                    continue;
                }
                $result[$cif] = PipelinePrewashDecimal::add($result[$cif] ?? '0.00', (string) $loan->amount);
            }
            foreach ($unknown as $cif => $_) {
                $result[$cif] = null;
            }
        }

        return $result;
    }

    private function scopeCodes(Builder $query, string $column, ?array $scope): void
    {
        $scopes = $scope ? [$scope] : UserBranchScope::branchScopes();
        $codes = [];
        foreach ($scopes as $branch) {
            $codes = [...$codes, $branch['code4'], $branch['code5'], ltrim($branch['code4'], '0')];
        }
        $query->whereIn($column, array_values(array_unique($codes)));
    }

    private function aggregate(array $rows, bool $visitsAvailable, bool $loansAvailable): array
    {
        $total = count($rows);
        $visited = $visitsAvailable ? count(array_filter($rows, fn (array $row): bool => $row['kunjungan'] === 'DONE')) : null;
        $realized = $loansAvailable ? count(array_unique(array_column(array_filter($rows, fn (array $row): bool => $row['status'] === 'Real'), 'cif'))) : null;
        $sum = static function (string $column) use ($rows): ?string {
            $amount = '0.00';
            $seen = [];
            foreach ($rows as $row) {
                if ($column === 'plafond_real' && isset($seen[$row['cif']])) {
                    continue;
                }
                $seen[$row['cif']] = true;
                if (! isset($row[$column]) || $row[$column] === '') {
                    return null;
                }
                $amount = PipelinePrewashDecimal::add($amount, (string) $row[$column]);
            }

            return $amount;
        };
        $realAmount = $loansAvailable ? $sum('plafond_real') : null;
        if ($loansAvailable && $realAmount === null) {
            $realized = null;
        }

        return [
            'total_count' => $total, 'total_amount' => $sum('plafond'),
            'visited_count' => $visited, 'visited_share' => $visited !== null && $total ? 100 * $visited / $total : null,
            'unvisited_count' => $visited === null ? null : $total - $visited,
            'unvisited_share' => $visited !== null && $total ? 100 * ($total - $visited) / $total : null,
            'realized_count' => $realized, 'realized_share' => $realized !== null && $visited ? 100 * $realized / $visited : null,
            'realized_amount' => $realAmount,
        ];
    }
}
