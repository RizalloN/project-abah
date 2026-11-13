<?php

namespace App\Support;

use App\Services\Import\MonitoringKsbkSourceService as Source;
use App\Services\Import\MySqlBulkLoadService;
use App\Services\Import\PipelinePrewashDecimal as Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class LandingMicroKsbkService
{
    public function payload(?array $branchScope): array
    {
        if (! Schema::hasTable(Source::TABLE) || ! DB::table(Source::TABLE)->exists()) {
            return $this->empty('Monitoring Realisasi KSBK belum diimport. Impor laporan untuk menampilkan pipeline dan realisasi.', $branchScope);
        }

        return app(MySqlBulkLoadService::class)->withTableWriteLock(Source::TABLE,
            fn (): array => $this->buildPayload($branchScope)
        );
    }

    private function buildPayload(?array $scope): array
    {
        $prepared = $this->prepareRecords($scope, true);
        $rows = $prepared['rows'];
        if ($rows === []) {
            return $this->empty('Belum ada Monitoring Realisasi KSBK untuk cabang yang dipilih.', $scope);
        }
        $groups = $units = [];
        foreach ($rows as $row) {
            $groups[$row['regional_office'].'|'.$row['branch_code']][] = $row;
            $units[$row['branch_code'].'|'.$row['unit_code']][] = $row;
        }
        $result = $unitGroups = [];
        foreach ($groups as $group) {
            $result[] = array_intersect_key($group[0], array_flip(['regional_office', 'branch_code', 'branch_name'])) + $this->aggregate($group);
        }
        foreach ($units as $group) {
            $unitGroups[] = array_intersect_key($group[0], array_flip(['regional_office', 'branch_code', 'branch_name', 'unit_code', 'unit_name'])) + $this->aggregate($group);
        }
        usort($result, fn (array $a, array $b): int => [$a['regional_office'], $a['branch_name']] <=> [$b['regional_office'], $b['branch_name']]);
        usort($unitGroups, fn (array $a, array $b): int => [$a['branch_name'], $a['unit_name'], $a['unit_code']] <=> [$b['branch_name'], $b['unit_name'], $b['unit_code']]);
        $quality = $prepared['quality'];
        $table = $prepared['loan_source'];

        $targetGroups = ($scope !== null && ($scope['key'] ?? '') !== 'area6') ? $unitGroups : $result;

        return [
            'available' => true, 'loan_source' => $table, 'loan_period' => $prepared['loan_period'], 'scope_key' => $scope['key'] ?? 'area6',
            'message' => $table === null ? 'Laporan pinjaman belum tersedia; realisasi belum dapat diverifikasi.'
                : ($quality['unverified_realization'] > 0 ? $quality['unverified_realization'].' usulan belum dapat diverifikasi karena CIF kosong, beberapa rekening, atau nilai pinjaman tidak lengkap.'
                    : ($quality['unclassified_product'] > 0 ? 'Ada produk SBK yang belum dapat dikelompokkan; periksa sumber.' : null)),
            'quality' => $quality, 'groups' => $result, 'unit_groups' => $unitGroups, 'summary' => $this->summarizeGroups($targetGroups, $this->aggregate($rows)),
        ];
    }

    public function records(?array $branchScope): array
    {
        if (! Schema::hasTable(Source::TABLE)) {
            return [];
        }

        return $this->prepareRecords($branchScope, false)['rows'];
    }

    private function prepareRecords(?array $scope, bool $persist): array
    {
        $scopes = $scope ? [$scope] : UserBranchScope::branchScopes();
        $branches = [];
        foreach ($scopes as $branch) {
            $branches[$this->code($branch['code4'])] = $branch;
        }
        $query = DB::table(Source::TABLE);
        $this->scopeCodes($query, 'bo', $scopes);
        $rows = $query->orderBy('uniqueid_namareport')->get()->map(fn (object $row): array => (array) $row)->all();
        if ($rows === []) {
            return ['rows' => [], 'quality' => [], 'loan_source' => null, 'loan_period' => null];
        }
        [$table, $period] = $this->loanSource();
        $cifs = array_values(array_unique(array_filter(array_map(fn (array $row): string => trim((string) $row['cifno']), $rows), fn (string $cif): bool => $cif !== '')));
        $loans = $table ? $this->loans($table, $period, $cifs, $scopes) : [];
        $units = $this->unitReferences($scopes);
        $quality = ['unverified_realization' => 0, 'unclassified_product' => 0, 'unmapped_unit' => 0];
        $updates = [];
        foreach ($rows as &$row) {
            $key = strtoupper(trim((string) $row['cifno']));
            $derived = $this->derive($row, $table !== null, $loans[$key] ?? []);
            if ($derived['cek_real'] === null || ($derived['cek_real'] === 'Sudah Real' && ! in_array($derived['ksbk_non'], ['KSBK', 'Non KSBK'], true))) {
                $quality['unverified_realization']++;
            }
            $before = array_intersect_key($row, $derived);
            if ($this->changed($before, $derived)) {
                $updates[] = ['uniqueid_namareport' => $row['uniqueid_namareport'], ...$derived];
            }
            $row = [...$row, ...$derived];
            $code = $this->code((string) $row['bo']);
            $row['regional_office'] = in_array(strtoupper(trim((string) $row['ro'])), ['R', '13', '013'], true)
                ? 'Region 13 / Malang' : (string) $row['ro'];
            $row['branch_code'] = $code;
            $row['branch_name'] = $branches[$code]['label'];
            $row['unit_code'] = trim((string) ($row['branch'] ?? '')) === '' ? '' : $this->code((string) $row['branch']);
            $reference = $units[$code.'|'.$row['unit_code']] ?? null;
            $row['unit_name'] = $reference ?? ($row['unit_code'] === '' ? 'Unit belum terpetakan' : 'Unit '.$row['unit_code']);
            $row['product_bucket'] = $this->productBucket((string) $row['produk_sbk']);
            $quality['unmapped_unit'] += $reference === null ? 1 : 0;
            $quality['unclassified_product'] += $row['product_bucket'] === null ? 1 : 0;
        }
        unset($row);
        if ($persist && $updates !== []) {
            DB::transaction(function () use ($updates): void {
                foreach ($updates as $update) {
                    $id = $update['uniqueid_namareport'];
                    unset($update['uniqueid_namareport']);
                    DB::table(Source::TABLE)->where('uniqueid_namareport', $id)->update($update);
                }
            });
        }

        return ['rows' => $rows, 'quality' => $quality, 'loan_source' => $table, 'loan_period' => $period];
    }

    private function unitReferences(array $scopes): array
    {
        if (! Schema::hasTable('referensi_uker')) {
            return [];
        }
        $query = DB::table('referensi_uker');
        $this->scopeCodes($query, 'kode_cabang', $scopes);
        $units = [];
        foreach ($query->get(['kode_cabang', 'kode_uker', 'nama_uker']) as $reference) {
            $name = trim(preg_replace('/^\d+\s*--\s*/', '', (string) $reference->nama_uker));
            if ($name !== '') {
                $units[$this->code((string) $reference->kode_cabang).'|'.$this->code((string) $reference->kode_uker)] = $name;
            }
        }

        return $units;
    }

    private function loanSource(): array
    {
        $daily = null;
        if (Schema::hasTable('daily_loan_dinamis')) {
            // LW321PN mirrors are not evidence of a native Daily Loan report.
            foreach (DB::table('daily_loan_dinamis')->select('periode')->distinct()->orderByDesc('periode')->pluck('periode') as $period) {
                if ($this->nativeDaily(DB::table('daily_loan_dinamis')->where('periode', $period))->exists()) {
                    $daily = (string) $period;
                    break;
                }
            }
        }
        $lw = Schema::hasTable('lw321pn') ? DB::table('lw321pn')->max('periode') : null;

        return $daily && (! $lw || $daily >= $lw) ? ['daily_loan_dinamis', $daily] : ($lw ? ['lw321pn', (string) $lw] : [null, null]);
    }

    private function loans(string $table, string $period, array $cifs, array $scopes): array
    {
        $daily = $table === 'daily_loan_dinamis';
        $branch = $daily ? 'kode_cabang1' : 'kode_kanca';
        $account = $daily ? 'nomor_rekening1' : 'no_rekening';
        $amount = $daily ? 'plafon' : 'plafon_dalam_idr';
        $result = [];
        foreach (array_chunk($cifs, 500) as $chunk) {
            $query = DB::table($table)->where('periode', $period)->whereIn('cifno', $chunk);
            $this->scopeCodes($query, $branch, $scopes);
            if ($daily) {
                $this->nativeDaily($query);
            }
            foreach ($query->select(['cifno', $branch, $account, $amount, 'ln_type'])->cursor() as $loan) {
                $key = strtoupper(trim((string) $loan->cifno));
                $result[$key][] = ['account' => trim((string) $loan->{$account}), 'amount' => $loan->{$amount}, 'type' => $loan->ln_type];
            }
        }

        return $result;
    }

    private function derive(array $row, bool $available, array $loans): array
    {
        $unknown = ['plafond' => null, 'ln_type' => null, 'cek_real' => null, 'ksbk_non' => null];
        if (! $available || trim((string) $row['cifno']) === '') {
            return $unknown;
        }
        if ($loans === []) {
            return ['plafond' => '0.00', 'ln_type' => '0', 'cek_real' => 'Belum Real', 'ksbk_non' => 'Belum Real'];
        }
        $candidates = [];
        foreach ($loans as $loan) {
            try {
                $amount = Money::normalize($loan['amount'] === null ? null : (string) $loan['amount']);
            } catch (Throwable) {
                return $unknown;
            }
            $type = strtoupper(trim((string) $loan['type']));
            if ($loan['account'] === '' || $amount === null || str_starts_with($amount, '-')) {
                return $unknown;
            }
            // Equal duplicate source rows are harmless; conflicting rows are not.
            $candidates[$loan['account'].'|'.$amount.'|'.$type] = [$amount, $type];
        }
        if (count($candidates) !== 1) {
            return $unknown;
        }
        [$amount, $type] = reset($candidates);
        $positive = $amount !== '0.00';
        $classification = $type === '' ? null : ($type === '0' ? 'Belum Real' : (in_array($type, ['8E', '8F', '7E', '7F'], true) ? 'KSBK' : 'Non KSBK'));

        return ['plafond' => $amount, 'ln_type' => $type === '' ? null : $type, 'cek_real' => $positive ? 'Sudah Real' : 'Belum Real', 'ksbk_non' => $classification];
    }

    private function aggregate(array $rows): array
    {
        $out = ['small_count' => 0, 'large_count' => 0, 'total_count' => count($rows), 'ksbk_count' => 0, 'ksbk_amount' => '0.00', 'non_ksbk_count' => 0, 'non_ksbk_amount' => '0.00', 'realized_count' => 0, 'realized_amount' => '0.00', 'unrealized_count' => 0, 'unrealized_share' => null];
        $complete = true;
        $productsComplete = true;
        foreach ($rows as $row) {
            $bucket = $this->productBucket((string) $row['produk_sbk']);
            if ($bucket !== null) {
                $out[$bucket]++;
            } else {
                $productsComplete = false;
            }
            if ($row['cek_real'] === null || ($row['cek_real'] === 'Sudah Real' && ! in_array($row['ksbk_non'], ['KSBK', 'Non KSBK'], true))) {
                $complete = false;

                continue;
            }
            if ($row['cek_real'] === 'Belum Real') {
                $out['unrealized_count']++;
            } else {
                $prefix = $row['ksbk_non'] === 'KSBK' ? 'ksbk' : 'non_ksbk';
                $out[$prefix.'_count']++;
                $out[$prefix.'_amount'] = Money::add($out[$prefix.'_amount'], $row['plafond']);
                $out['realized_count']++;
                $out['realized_amount'] = Money::add($out['realized_amount'], $row['plafond']);
            }
        }
        if (! $productsComplete) {
            $out['small_count'] = $out['large_count'] = null;
        }
        if (! $complete) {
            foreach (['ksbk_count', 'ksbk_amount', 'non_ksbk_count', 'non_ksbk_amount', 'realized_count', 'realized_amount', 'unrealized_count'] as $column) {
                $out[$column] = null;
            }
        } elseif ($out['total_count'] > 0) {
            $out['unrealized_share'] = 100 * $out['unrealized_count'] / $out['total_count'];
        }

        return $out;
    }

    private function productBucket(string $value): ?string
    {
        $value = strtolower(preg_replace('/\s+/', '', str_replace('≤', '<=', $value)));

        return str_contains($value, '<=150juta') ? 'small_count' : (str_contains($value, '>150juta') ? 'large_count' : null);
    }

    private function nativeDaily(Builder $query): Builder
    {
        if (Schema::hasColumn('daily_loan_dinamis', 'uniqueid_namareport')) {
            $query->where(fn (Builder $q) => $q->whereNull('uniqueid_namareport')->orWhere('uniqueid_namareport', 'not like', 'LW321PN:%'));
        }

        return $query;
    }

    private function scopeCodes(Builder $query, string $column, array $scopes): void
    {
        $codes = [];
        foreach ($scopes as $scope) {
            $codes = [...$codes, $scope['code4'], $scope['code5'], $this->code($scope['code4'])];
        }
        $query->whereIn($column, array_unique($codes));
    }

    private function code(string $value): string
    {
        return ltrim(trim($value), '0') ?: '0';
    }

    private function changed(array $before, array $after): bool
    {
        foreach ($after as $column => $value) {
            if ((($before[$column] ?? null) === null) !== ($value === null) || (string) ($before[$column] ?? '') !== (string) $value) {
                return true;
            }
        }

        return false;
    }

    private function summarizeGroups(array $groups, array $fallback): array
    {
        if ($fallback['realized_count'] !== null) {
            return $fallback;
        }

        $out = [
            'small_count' => 0, 'large_count' => 0, 'total_count' => 0,
            'ksbk_count' => 0, 'ksbk_amount' => '0.00',
            'non_ksbk_count' => 0, 'non_ksbk_amount' => '0.00',
            'realized_count' => 0, 'realized_amount' => '0.00',
            'unrealized_count' => 0, 'unrealized_share' => null,
        ];
        $hasAnyRealized = false;
        $hasSmall = true;
        $hasLarge = true;

        foreach ($groups as $group) {
            if (isset($group['small_count']) && is_numeric($group['small_count'])) {
                $out['small_count'] += (int) $group['small_count'];
            } else {
                $hasSmall = false;
            }
            if (isset($group['large_count']) && is_numeric($group['large_count'])) {
                $out['large_count'] += (int) $group['large_count'];
            } else {
                $hasLarge = false;
            }
            $out['total_count'] += (int) ($group['total_count'] ?? 0);

            if (isset($group['ksbk_count']) && is_numeric($group['ksbk_count'])) {
                $out['ksbk_count'] += (int) $group['ksbk_count'];
            }
            if (isset($group['ksbk_amount']) && is_numeric($group['ksbk_amount'])) {
                $out['ksbk_amount'] = Money::add($out['ksbk_amount'], (string) $group['ksbk_amount']);
            }
            if (isset($group['non_ksbk_count']) && is_numeric($group['non_ksbk_count'])) {
                $out['non_ksbk_count'] += (int) $group['non_ksbk_count'];
            }
            if (isset($group['non_ksbk_amount']) && is_numeric($group['non_ksbk_amount'])) {
                $out['non_ksbk_amount'] = Money::add($out['non_ksbk_amount'], (string) $group['non_ksbk_amount']);
            }
            if (isset($group['realized_count']) && is_numeric($group['realized_count'])) {
                $out['realized_count'] += (int) $group['realized_count'];
                $hasAnyRealized = true;
            }
            if (isset($group['realized_amount']) && is_numeric($group['realized_amount'])) {
                $out['realized_amount'] = Money::add($out['realized_amount'], (string) $group['realized_amount']);
                $hasAnyRealized = true;
            }
        }

        if (! $hasSmall || ($out['small_count'] === 0 && ($fallback['small_count'] ?? null) === null)) {
            $out['small_count'] = $fallback['small_count'] ?? null;
        }
        if (! $hasLarge || ($out['large_count'] === 0 && ($fallback['large_count'] ?? null) === null)) {
            $out['large_count'] = $fallback['large_count'] ?? null;
        }

        if (! $hasAnyRealized) {
            return $fallback;
        }

        $out['unrealized_count'] = max(0, $out['total_count'] - $out['realized_count']);
        if ($out['total_count'] > 0) {
            $out['unrealized_share'] = 100 * $out['unrealized_count'] / $out['total_count'];
        }

        return $out;
    }

    private function empty(string $message, ?array $scope = null): array
    {
        return ['available' => false, 'groups' => [], 'unit_groups' => [], 'summary' => [], 'message' => $message, 'loan_source' => null, 'loan_period' => null, 'scope_key' => $scope['key'] ?? 'area6'];
    }
}
