<?php

namespace App\Support;

use App\Services\Import\PipelinePrewashDecimal as Money;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class MicroReportNominativeService
{
    public function page(array $filters, ?array $authorizedScope): array
    {
        $report = $filters['report'];
        $level = $filters['level'] ?? 'bo';
        $metric = $filters['metric'] ?? 'all';
        if (! in_array($report, ['prewash', 'boh', 'mbm', 'ksbk'], true) || ! in_array($level, ['bo', 'unit', 'mbm'], true)) {
            throw ValidationException::withMessages(['report' => 'Laporan atau kelompok nominatif tidak valid.']);
        }
        $scope = $this->scope($filters['branch'] ?? '', $authorizedScope);
        if ($level === 'unit' && trim((string) ($filters['unit'] ?? '')) === '') {
            throw ValidationException::withMessages(['unit' => 'Pilih unit kerja untuk membuka nominatif.']);
        }
        if ($level === 'mbm' && ($report === 'ksbk' || trim((string) ($filters['mbm'] ?? '')) === '')) {
            throw ValidationException::withMessages(['mbm' => 'Kelompok MBM tidak valid.']);
        }
        $allowed = $report === 'ksbk'
            ? ['all', 'realized', 'unrealized', 'ksbk', 'non_ksbk', 'small', 'large']
            : ['all', 'visited', 'unvisited', 'realized'];
        if (! in_array($metric, $allowed, true)) {
            throw ValidationException::withMessages(['metric' => 'Kategori nominatif tidak sesuai laporan.']);
        }
        [$meta, $records] = $this->source($report, $filters['periode'], $scope);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 50;
        $context = [
            'report' => $report, 'level' => $level, 'branch' => $scope['label'] ?? 'Area 6',
            'unit' => $filters['unit'] ?? null, 'metric' => $metric, 'period' => $filters['periode'],
            ...Arr::only($meta, ['year', 'loan_period', 'loan_source', 'visit_period']),
        ];
        if (empty($meta['available'])) {
            return ['available' => false, 'data' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'last_page' => 1, 'context' => $context, 'message' => $meta['message'] ?? 'Sumber laporan belum tersedia.'];
        }
        $data = $seen = $realAmountSeen = [];
        $total = $sourceRows = $unverified = 0;
        $amount = $realAmount = '0.00';
        $invalidMetric = false;
        foreach ($records as $record) {
            $row = (array) $record;
            $branch = $this->rowBranch($row);
            if ($branch === null || ($scope !== null && $branch['key'] !== $scope['key'])) {
                continue;
            }
            $unit = (string) ($row['unit_code'] ?? $row['branch'] ?? '');
            if ($level === 'unit' && $this->code($unit) !== $this->code((string) $filters['unit'])) {
                continue;
            }
            if ($level === 'mbm' && ! $this->mbmMatches($row, (string) $filters['mbm'])) {
                continue;
            }
            $sourceRows++;
            $unknown = $report === 'ksbk'
                ? ($row['cek_real'] === null || ($row['cek_real'] === 'Sudah Real' && ! in_array($row['ksbk_non'], ['KSBK', 'Non KSBK'], true)))
                : $row['plafond_real'] === null;
            $unverified += $unknown ? 1 : 0;
            if ($report !== 'prewash' && in_array($metric, ['realized', 'unrealized', 'ksbk', 'non_ksbk'], true) && $unknown) {
                $invalidMetric = true;
            }
            if (in_array($report, ['boh', 'mbm'], true) && in_array($metric, ['visited', 'unvisited'], true) && $row['jumlah_kunjungan'] === null) {
                $invalidMetric = true;
            }
            if ($report === 'ksbk' && in_array($metric, ['small', 'large'], true) && ($row['product_bucket'] ?? null) === null) {
                $invalidMetric = true;
            }
            if (! $this->matchesMetric($row, $report, $metric)) {
                continue;
            }
            if ($metric === 'realized' && in_array($report, ['boh', 'mbm'], true)) {
                $key = $report === 'boh' ? (string) $row['cif'] : $branch['code4'].'|'.strtoupper(trim((string) $row['cif']));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            }
            $total++;
            $baseAmount = $row[$report === 'ksbk' ? 'plafond_pengajuan' : 'plafond'] ?? null;
            $amount = $amount === null || $baseAmount === null ? null : Money::add($amount, (string) $baseAmount);
            $amountKey = match ($report) {
                'boh' => (string) $row['cif'],
                'mbm' => $branch['code4'].'|'.strtoupper(trim((string) $row['cif'])),
                default => (string) $row['uniqueid_namareport'],
            };
            if (! isset($realAmountSeen[$amountKey])) {
                $realAmount = $realAmount === null || $row[$report === 'ksbk' ? 'plafond' : 'plafond_real'] === null
                    ? null : Money::add($realAmount, (string) $row[$report === 'ksbk' ? 'plafond' : 'plafond_real']);
                $realAmountSeen[$amountKey] = true;
            }
            if ($total > ($page - 1) * $perPage && count($data) < $perPage) {
                $data[] = $this->present($row, $branch, $report);
            }
        }
        if ($invalidMetric) {
            throw ValidationException::withMessages(['metric' => 'Kategori ini belum dapat diverifikasi seluruhnya. Buka seluruh nominatif untuk melihat data yang belum lengkap.']);
        }

        return [
            'available' => true, 'data' => $data, 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage)), 'context' => $context,
            'summary' => ['count' => $total, 'source_rows' => $sourceRows, 'unverified_count' => $unverified, 'plafond' => $amount, 'plafond_real' => $realAmount],
        ];
    }

    private function source(string $report, string $period, ?array $scope): array
    {
        if ($report === 'prewash') {
            $source = app(PipelinePrewashService::class)->nominativeRows($period, $scope);

            return [$source['meta'], $source['rows']];
        }
        if ($report === 'ksbk') {
            $service = app(LandingMicroKsbkService::class);
            $meta = $service->payload($scope);

            return [$meta, empty($meta['available']) ? [] : $service->records($scope)];
        }
        $meta = app($report === 'boh' ? LandingMicroPrewashBohService::class : LandingMicroPrewashMbmService::class)->payload($scope);

        return [$meta, $meta['records'] ?? []];
    }

    private function scope(string $requested, ?array $authorized): ?array
    {
        if (trim($requested) === '') {
            return $authorized;
        }
        foreach (UserBranchScope::branchScopes() as $candidate) {
            if (UserBranchScope::matches($requested, $candidate)) {
                abort_if($authorized !== null && $candidate['key'] !== $authorized['key'], 403, 'Cabang berada di luar filter atau akses pengguna.');

                return $candidate;
            }
        }
        throw ValidationException::withMessages(['branch' => 'Branch Office tidak valid.']);
    }

    private function rowBranch(array $row): ?array
    {
        $value = $row['nama_kanca'] ?? $row['branch_name'] ?? $row['bo'] ?? '';
        foreach (UserBranchScope::branchScopes() as $branch) {
            if (UserBranchScope::matches($value, $branch)) {
                return $branch;
            }
        }

        return null;
    }

    private function code(string $value): string
    {
        return ltrim(trim($value), '0');
    }

    private function mbmMatches(array $row, string $value): bool
    {
        return ! empty($row['mbm_pn'])
            ? $this->code((string) $row['mbm_pn']) === $this->code($value)
            : trim((string) ($row['mbm_name'] ?? '')) === trim($value);
    }

    private function matchesMetric(array $row, string $report, string $metric): bool
    {
        if ($report === 'ksbk') {
            return match ($metric) {
                'realized' => $row['cek_real'] === 'Sudah Real',
                'unrealized' => $row['cek_real'] === 'Belum Real',
                'ksbk' => $row['cek_real'] === 'Sudah Real' && $row['ksbk_non'] === 'KSBK',
                'non_ksbk' => $row['cek_real'] === 'Sudah Real' && $row['ksbk_non'] === 'Non KSBK',
                'small' => ($row['product_bucket'] ?? null) === 'small_count',
                'large' => ($row['product_bucket'] ?? null) === 'large_count',
                default => true,
            };
        }

        return match ($metric) {
            'visited' => $row['kunjungan'] === 'DONE',
            'unvisited' => $row['kunjungan'] !== 'DONE',
            'realized' => $row['status'] === ($report === 'prewash' ? 'Sudah' : 'Real'),
            default => true,
        };
    }

    private function present(array $row, array $branch, string $report): array
    {
        $ksbk = $report === 'ksbk';

        return [
            'id' => (string) $row['uniqueid_namareport'], 'nama' => $row[$ksbk ? 'nama' : 'nama_debt'] ?? null,
            'cif' => $row[$ksbk ? 'cifno' : 'cif'] ?? null, 'norek' => $row['norek_pinjaman'] ?? null,
            'cabang' => $branch['label'], 'branch_code' => $branch['code4'],
            'unit' => $row['unit_name'] ?? $row['nama_unit'] ?? null, 'unit_code' => $row['unit_code'] ?? $row['branch'] ?? null,
            'pn_pemasar' => $row[$ksbk ? 'pernr' : 'pn_mantri'] ?? null, 'nama_pemasar' => $row['nama_mantri'] ?? null,
            'mbm_pn' => $row['mbm_pn'] ?? null, 'mbm_name' => $row['mbm_name'] ?? null,
            'plafond' => $row[$ksbk ? 'plafond_pengajuan' : 'plafond'] ?? null, 'os' => $row['os'] ?? null,
            'plafond_real' => $row[$ksbk ? 'plafond' : 'plafond_real'] ?? null,
            'kunjungan' => $row['kunjungan'] ?? null, 'jumlah_kunjungan' => $row['jumlah_kunjungan'] ?? null,
            'status' => $row[$ksbk ? 'cek_real' : 'status'] ?? null, 'produk' => $row[$ksbk ? 'produk_sbk' : 'product_rekomendasi'] ?? null,
            'ln_type' => $row['ln_type'] ?? null,
        ];
    }
}
