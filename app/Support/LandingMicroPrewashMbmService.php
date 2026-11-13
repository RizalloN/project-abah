<?php

namespace App\Support;

use App\Services\Import\PipelinePrewashDecimal;
use App\Services\Import\MySqlBulkLoadService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class LandingMicroPrewashMbmService
{
    private const TABLE = 'monitoring_tl_pipeline_mbm';
    private const VISITS = 'evaluasi_aktivitas_kunjungan_pemasar';
    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agt', 'sep', 'okt', 'nov', 'des'];

    public function payload(?array $branchScope): array
    {
        return app(MySqlBulkLoadService::class)->withTableWriteLock(self::TABLE,
            fn (): array => $this->buildPayload($branchScope)
        );
    }

    private function buildPayload(?array $branchScope): array
    {
        $empty = ['available' => false, 'groups' => ['bo' => [], 'mbm' => [], 'unit' => []], 'summary' => [], 'records' => []];
        if (! Schema::hasTable(self::TABLE) || ! DB::table(self::TABLE)->exists()) {
            return $empty + [
                'empty_reason' => 'not_imported',
                'empty_title' => 'TL Pipeline Prewash PIC MBM belum diimport',
                'import_report' => 'Monitoring TL Pipeline Prewash PIC MBM',
                'message' => 'Impor laporan Monitoring TL Pipeline Prewash PIC MBM melalui menu Import. Tab ini memakai dataset PIC MBM tersendiri.',
            ];
        }
        $visitPeriod = Schema::hasTable(self::VISITS) ? DB::table(self::VISITS)->max('periode') : null;
        $year = $visitPeriod ? (int) substr($visitPeriod, 0, 4) : (int) DB::table(self::TABLE)->max('tahun');
        $base = $this->pipelineQuery($year, $branchScope);
        $records = $base->orderBy('uniqueid_namareport')->get()->map(fn (object $row): array => (array) $row)->all();
        if ($records === []) {
            return $empty + ['year' => $year ?: null, 'visit_period' => $visitPeriod, 'empty_reason' => 'no_matching_scope', 'message' => 'Belum ada Pipeline Prewash PIC MBM untuk tahun laporan kunjungan dan cabang yang dipilih.'];
        }

        [$loanSource, $loanPeriod] = $this->loanSource();
        $cifs = array_values(array_unique(array_filter(array_map(fn (array $row): string => trim((string) $row['cif']), $records), fn (string $cif): bool => $cif !== '')));
        $visits = $visitPeriod ? $this->visits($visitPeriod, $year, $branchScope) : [];
        // Without a visit report, the realization year cannot be reconciled.
        $loansAvailable = $visitPeriod !== null && $loanSource !== null;
        $loans = $loansAvailable ? $this->loans($cifs, $loanSource, $loanPeriod, $year, $branchScope) : [];
        $updates = [];
        $territories = $this->territories();
        $quality = ['missing_cif' => 0, 'unmapped_mbm' => 0, 'unverified_realization' => 0];
        foreach ($records as &$row) {
            $before = array_intersect_key($row, array_flip([...self::MONTHS, 'jumlah_kunjungan', 'kunjungan', 'plafond_real', 'status']));
            $cif = strtoupper(trim((string) $row['cif']));
            $lookup = $this->recordKey($row);
            foreach (self::MONTHS as $month) {
                $row[$month] = $visits[$lookup][$month]['status'] ?? null;
            }
            $row['jumlah_kunjungan'] = $visitPeriod && $cif !== '' ? count(array_filter(array_intersect_key($row, array_flip(self::MONTHS)), fn ($status): bool => $status === 'DONE')) : null;
            $row['kunjungan'] = $row['jumlah_kunjungan'] === null ? null : ($row['jumlah_kunjungan'] > 0 ? 'DONE' : 'NOT');
            $row['plafond_real'] = $loansAvailable && $cif !== '' ? (array_key_exists($lookup, $loans) ? $loans[$lookup] : '0.00') : null;
            $row['status'] = $row['plafond_real'] === null ? null : ((float) $row['plafond_real'] > 0 ? 'Real' : 'Belum');
            $identity = $this->mbmIdentity($row, $territories);
            $row = [...$row, ...$identity];
            $quality['missing_cif'] += $cif === '' ? 1 : 0;
            $quality['unmapped_mbm'] += $identity['mbm_name'] === 'Belum dipetakan' ? 1 : 0;
            $quality['unverified_realization'] += $loansAvailable && $cif !== '' && $row['plafond_real'] === null ? 1 : 0;
            $derived = array_intersect_key($row, array_flip([...self::MONTHS, 'jumlah_kunjungan', 'kunjungan', 'plafond_real', 'status']));
            $changed = false;
            foreach ($derived as $column => $value) {
                $previous = $before[$column] ?? null;
                if (($previous === null) !== ($value === null) || (string) $previous !== (string) $value) {
                    $changed = true;
                    break;
                }
            }
            if ($changed) {
                $updates[] = ['uniqueid_namareport' => $row['uniqueid_namareport'], ...$derived];
            }
        }
        unset($row);
        if ($updates !== []) {
            $this->persist($year, $updates);
        }

        $groups = ['bo' => [], 'mbm' => [], 'unit' => []];
        foreach (['bo', 'mbm', 'unit'] as $dimension) {
            $buckets = [];
            foreach ($records as $row) {
                $key = $row['nama_kanca'].match ($dimension) {
                    'mbm' => '|'.($row['mbm_pn'] ?? $row['mbm_name']),
                    'unit' => '|'.$row['branch'],
                    default => '',
                };
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
                    'mbm_pn' => $rows[0]['mbm_pn'],
                    'mbm_name' => $rows[0]['mbm_name'],
                ] + $this->aggregate($rows, $visitPeriod !== null, $loansAvailable);
            }
        }

        return [
            'available' => true, 'year' => $year, 'visit_period' => $visitPeriod,
            'loan_source' => $loanSource, 'loan_period' => $loanPeriod,
            'message' => ! $visitPeriod ? 'Laporan kunjungan belum tersedia; evaluasi kunjungan dan realisasi belum dapat dihitung.'
                : (! $loanSource ? 'Laporan pinjaman belum tersedia; realisasi belum dapat dihitung.' : null),
            'quality' => $quality, 'groups' => $groups, 'summary' => $this->aggregate($records, $visitPeriod !== null, $loansAvailable), 'records' => $records,
        ];
    }

    private function loanSource(): array
    {
        $daily = null;
        if (Schema::hasTable('daily_loan_dinamis')) {
            // Mirrored LW321PN rows do not constitute a newer Daily Loan report.
            $periods = DB::table('daily_loan_dinamis')->select('periode')->distinct()->orderByDesc('periode')->pluck('periode');
            foreach ($periods as $period) {
                if ($this->nativeDaily(DB::table('daily_loan_dinamis')->where('periode', $period))->exists()) {
                    $daily = (string) $period;
                    break;
                }
            }
        }
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
        foreach ($query->select(['cifno', 'kode_main_branch', 'tgl_kunjungan', 'status_kunjungan', 'id_aktivitas'])->get() as $visit) {
            $cif = $this->code((string) $visit->kode_main_branch).'|'.strtoupper(trim((string) $visit->cifno));
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
        $branchColumn = $daily ? 'kode_cabang1' : 'kode_kanca';
        $result = [];
        foreach (array_chunk($cifs, 500) as $chunk) {
            $query = DB::table($table)->where('periode', $period)->whereIn('cifno', $chunk)
                ->where('tgl_realisasi', '>=', $year.'-01-01')->where('tgl_realisasi', '<', ($year + 1).'-01-01');
            if ($daily) {
                $this->nativeDaily($query);
            }
            $this->scopeCodes($query, $branchColumn, $scope);
            $accounts = $query->select('cifno', $account, $branchColumn)->selectRaw("MIN({$amount}) as min_amount, MAX({$amount}) as amount, COUNT(*) as row_count, COUNT({$amount}) as amount_count")
                ->groupBy('cifno', $account, $branchColumn)->get();
            $unknown = [];
            foreach ($accounts as $loan) {
                $cif = $this->code((string) $loan->{$branchColumn}).'|'.strtoupper(trim((string) $loan->cifno));
                if (trim((string) $loan->{$account}) === '' || $loan->amount === null || $loan->amount_count != $loan->row_count || (string) $loan->min_amount !== (string) $loan->amount) {
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
        $visitsAvailable = $visitsAvailable && ! collect($rows)->contains(fn (array $row): bool => $row['jumlah_kunjungan'] === null);
        $visited = $visitsAvailable ? count(array_filter($rows, fn (array $row): bool => $row['kunjungan'] === 'DONE')) : null;
        $realized = $loansAvailable ? count(array_unique(array_map(fn (array $row): string => $this->recordKey($row), array_filter($rows, fn (array $row): bool => $row['status'] === 'Real')))) : null;
        $sum = function (string $column) use ($rows): ?string {
            $amount = '0.00';
            $seen = [];
            foreach ($rows as $row) {
                $key = $this->recordKey($row);
                if ($column === 'plafond_real' && isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
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

    private function persist(int $year, iterable $updates): void
    {
        $fields = [...self::MONTHS, 'jumlah_kunjungan', 'kunjungan', 'plafond_real', 'status'];
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::transaction(function () use ($year, $updates): void {
                foreach ($updates as $row) {
                    $id = $row['uniqueid_namareport'];
                    unset($row['uniqueid_namareport']);
                    DB::table(self::TABLE)->where('tahun', $year)->where('uniqueid_namareport', $id)->update($row);
                }
            });

            return;
        }
        $loader = app(MySqlBulkLoadService::class);
        $pdo = $loader->createPersistentPdo();
        $temporary = 'prewash_mbm_calc_'.bin2hex(random_bytes(6));
        $path = tempnam(storage_path('app'), 'prewash_mbm_calc_');
        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat staging perhitungan PIC MBM.');
        }
        $out = fopen($path, 'wb');
        if ($out === false) {
            @unlink($path);
            throw new RuntimeException('Tidak dapat menulis staging perhitungan PIC MBM.');
        }
        $count = 0;
        try {
            foreach ($updates as $row) {
                $encoded = array_map(static function ($value): string {
                    if ($value === null) {
                        return '\\N';
                    }

                    return '"'.strtr((string) $value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r']).'"';
                }, array_map(static fn (string $column) => $row[$column], ['uniqueid_namareport', ...$fields]));
                $line = implode(',', $encoded)."\n";
                if (fwrite($out, $line) !== strlen($line)) {
                    throw new RuntimeException('Gagal menulis perhitungan Pipeline Prewash PIC MBM.');
                }
                $count++;
            }
            fclose($out);
            $out = null;
            $definitions = ['uniqueid_namareport VARCHAR(80) PRIMARY KEY'];
            foreach ($fields as $field) {
                $definitions[] = '`'.$field.'` '.match ($field) {
                    'plafond_real' => 'DECIMAL(30,2)', 'jumlah_kunjungan' => 'SMALLINT', default => 'VARCHAR(64)'
                }.' NULL';
            }
            $pdo->exec('CREATE TEMPORARY TABLE `'.$temporary.'` ('.implode(',', $definitions).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $pdo->beginTransaction();
            $loaded = $pdo->exec('LOAD DATA LOCAL INFILE '.$pdo->quote(str_replace('\\', '/', $path)).' INTO TABLE `'.$temporary.'` CHARACTER SET utf8mb4 '."FIELDS TERMINATED BY ',' ENCLOSED BY '\"' ESCAPED BY '\\\\' LINES TERMINATED BY '\\n' (`uniqueid_namareport`,".implode(',', array_map(static fn ($field) => '`'.$field.'`', $fields)).')');
            $warnings = (int) $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn();
            if ($loaded !== $count || $warnings > 0) {
                throw new RuntimeException('Perhitungan Pipeline Prewash PIC MBM tidak lengkap; transaksi dibatalkan.');
            }
            $check = $pdo->prepare('SELECT COUNT(*) FROM `'.self::TABLE.'` p JOIN `'.$temporary.'` c ON p.uniqueid_namareport=c.uniqueid_namareport WHERE p.tahun=?');
            $check->execute([$year]);
            if ((int) $check->fetchColumn() !== $count) {
                throw new RuntimeException('Dataset Pipeline Prewash PIC MBM berubah selama perhitungan.');
            }
            $set = implode(',', array_map(static fn ($field) => 'p.`'.$field.'`=c.`'.$field.'`', $fields));
            $statement = $pdo->prepare('UPDATE `'.self::TABLE.'` p JOIN `'.$temporary.'` c ON p.uniqueid_namareport=c.uniqueid_namareport SET '.$set.' WHERE p.tahun=?');
            $statement->execute([$year]);
            if ((int) $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn() > 0) {
                throw new RuntimeException('Warning saat menyimpan perhitungan Pipeline Prewash PIC MBM.');
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        } finally {
            if (is_resource($out)) {
                fclose($out);
            }
            $pdo->exec('DROP TEMPORARY TABLE IF EXISTS `'.$temporary.'`');
            @unlink($path);
        }
    }

    private function territories(): array
    {
        if (! Schema::hasTable('wilayah_mbm')) {
            return [];
        }
        $result = [];
        foreach (DB::table('wilayah_mbm')->select('bc', 'nama_uker', 'cabang', 'nama_mbm')->get() as $row) {
            foreach ([$this->code((string) $row->bc), $this->name((string) $row->nama_uker)] as $unit) {
                $key = $this->branch((string) $row->cabang).'|'.$unit;
                $result[$key][] = trim((string) $row->nama_mbm);
            }
        }
        foreach ($result as $key => $names) {
            $names = array_values(array_unique(array_filter($names)));
            $result[$key] = count($names) === 1 ? $names[0] : null;
        }

        return $result;
    }

    private function mbmIdentity(array $row, array $territories): array
    {
        $branch = $this->branch((string) $row['nama_kanca']);
        $codeKey = $branch.'|'.$this->code((string) $row['branch']);
        $nameKey = $branch.'|'.$this->name((string) $row['nama_unit']);
        // An ambiguous BC must remain unmapped, even if its name happens to match.
        $mbm = array_key_exists($codeKey, $territories) ? $territories[$codeKey] : ($territories[$nameKey] ?? null);
        $aliases = ['MADIUN|HENDRINURWAHYUDI' => '64850', 'MAGETAN|RITAAULIASARI' => '61165'];
        $plain = strtoupper(preg_replace('/[^a-z0-9]/i', '', (string) $mbm));
        foreach (LandingMicroPerformanceService::mbmReference() as $pn => $person) {
            if ($mbm !== null && $this->branch($person['branch']) === $branch
                && (strtoupper(preg_replace('/[^a-z0-9]/i', '', $person['name'])) === $plain
                    || ($aliases[$branch.'|'.$plain] ?? null) === (string) $pn)) {
                return ['mbm_pn' => (string) $pn, 'mbm_name' => $person['name']];
            }
        }

        return ['mbm_pn' => null, 'mbm_name' => $mbm ?? 'Belum dipetakan'];
    }

    private function nativeDaily(Builder $query): Builder
    {
        if (Schema::hasColumn('daily_loan_dinamis', 'uniqueid_namareport')) {
            $query->where(fn (Builder $q) => $q->whereNull('uniqueid_namareport')->orWhere('uniqueid_namareport', 'not like', 'LW321PN:%'));
        }

        return $query;
    }

    private function recordKey(array $row): string
    {
        foreach (UserBranchScope::branchScopes() as $branch) {
            if (UserBranchScope::matches($row['nama_kanca'], $branch)) {
                return $this->code($branch['code4']).'|'.strtoupper(trim((string) $row['cif']));
            }
        }

        return '|'.strtoupper(trim((string) $row['cif']));
    }

    private function name(string $value): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim($value)));
    }

    private function code(string $value): string
    {
        return ltrim(trim($value), '0');
    }

    private function branch(string $value): string
    {
        return preg_replace('/^KC\s+/', '', $this->name($value));
    }

}
