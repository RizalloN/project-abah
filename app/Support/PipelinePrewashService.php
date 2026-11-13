<?php

namespace App\Support;

use App\Services\Import\MySqlBulkLoadService;
use App\Services\Import\PipelinePrewashDecimal as Money;
use App\Services\Import\PipelinePrewashSourceService as Source;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PipelinePrewashService
{
    private array $columns = [];

    public function payload(?string $requestedPeriod = null, ?array $branchScope = null, bool $forceRefresh = false): array
    {
        $year = $this->year($requestedPeriod);
        $meta = $this->refresh($year, $forceRefresh);
        $signature = Cache::get('pipeline-prewash:derived:v1:'.$year)['signature'] ?? null;
        $key = 'pipeline-prewash:groups:v3:'.hash('sha256', json_encode([$year, $meta, $signature, $branchScope, ReportCacheVersion::get()]));
        if ($forceRefresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 120, fn (): array => $this->aggregate($year, $branchScope, $meta));
    }

    private function aggregate(int $year, ?array $branchScope, array $meta): array
    {
        $result = [...$meta, 'year' => $year, 'groups' => ['bo' => [], 'mbm' => [], 'unit' => []], 'summary' => $this->emptyGroup()];
        if (! $meta['available']) {
            return $result;
        }
        $query = DB::table(Source::TABLE)->where('tahun', $year);
        if ($branchScope !== null) {
            $wanted = $this->branch((string) ($branchScope['label'] ?? $branchScope['plain_label'] ?? $branchScope['key'] ?? ''));
            $query->whereRaw('UPPER(TRIM(nama_kanca)) IN (?, ?)', [$wanted, 'KC '.$wanted]);
        }
        $territories = $this->territories();
        $reference = LandingMicroPerformanceService::mbmReference();
        $groups = ['bo' => [], 'mbm' => [], 'unit' => []];
        $quality = ['missing_cif' => 0, 'unmapped_mbm' => 0, 'branch_pipeline' => 0, 'unverified_realization' => 0];
        foreach ($query->select(['cif', 'branch', 'nama_unit', 'nama_kanca', 'plafond', 'jumlah_kunjungan', 'kunjungan', 'plafond_real', 'status'])->cursor() as $record) {
            $row = (array) $record;
            $branch = $this->branch((string) $row['nama_kanca']);
            $code = $this->code((string) $row['branch']);
            $unit = $this->name((string) $row['nama_unit']);
            $mbm = $territories[$branch.'|'.$code] ?? $territories[$branch.'|'.$unit] ?? null;
            $person = $mbm === null ? null : $this->mbmIdentity($branch, $mbm, $reference);
            // A source record at the BO itself has no unit territory assignment.
            // Keep it distinct from an unmapped unit; a branch has several MBMs.
            $branchPipeline = $mbm === null && $code === $this->branchCode($branch)
                && $this->branch((string) $row['nama_unit']) === $branch;
            $identity = [
                'branch_name' => 'KC '.ucwords(strtolower($branch)), 'branch_code' => $this->branchCode($branch),
                'unit_name' => (string) $row['nama_unit'], 'unit_code' => (string) $row['branch'], 'mbm_pn' => $person['pn'] ?? null,
                'mbm_name' => $person['name'] ?? $mbm ?? ($branchPipeline ? 'Pipeline BO — PIC MBM belum tersedia' : 'Belum dipetakan'),
            ];
            $quality['missing_cif'] += trim((string) $row['cif']) === '' ? 1 : 0;
            $quality['unmapped_mbm'] += $mbm === null ? 1 : 0;
            $quality['branch_pipeline'] += $branchPipeline ? 1 : 0;
            $quality['unverified_realization'] += $row['status'] === 'Perlu verifikasi' ? 1 : 0;
            $keys = ['bo' => $branch, 'mbm' => $branch.'|'.($person['pn'] ?? $mbm ?? ($branchPipeline ? 'branch_unassigned' : 'unknown')), 'unit' => $branch.'|'.$code.'|'.$unit];
            foreach ($keys as $level => $key) {
                $groups[$level][$key] ??= [...$this->emptyGroup(), ...$identity];
                $this->addRow($groups[$level][$key], $row);
            }
            $this->addRow($result['summary'], $row);
        }
        foreach ($groups as $level => $rows) {
            foreach ($rows as &$row) {
                $this->shares($row);
            }
            unset($row);
            uasort($rows, static fn ($a, $b): int => [$a['branch_name'], $a['mbm_name'], $a['unit_name']] <=> [$b['branch_name'], $b['mbm_name'], $b['unit_name']]);
            $result['groups'][$level] = array_values($rows);
        }
        $this->shares($result['summary']);
        $result['quality'] = $quality;

        return $result;
    }

    public function nominativeRows(?string $period, ?array $branchScope): array
    {
        $payload = $this->payload($period, $branchScope);
        if (! $payload['available']) {
            return ['meta' => $payload, 'rows' => []];
        }
        $units = [];
        foreach ($payload['groups']['unit'] as $unit) {
            $key = $this->branch($unit['branch_name']).'|'.$this->code((string) $unit['unit_code']).'|'.$this->name($unit['unit_name']);
            $units[$key] = array_intersect_key($unit, array_flip(['branch_code', 'branch_name', 'unit_code', 'unit_name', 'mbm_pn', 'mbm_name']));
        }
        $query = DB::table(Source::TABLE)->where('tahun', $payload['year']);
        if ($branchScope !== null) {
            $wanted = $this->branch((string) ($branchScope['label'] ?? $branchScope['plain_label'] ?? $branchScope['key'] ?? ''));
            $query->whereRaw('UPPER(TRIM(nama_kanca)) IN (?, ?)', [$wanted, 'KC '.$wanted]);
        }
        $rows = (function () use ($query, $units) {
            foreach ($query->orderBy('uniqueid_namareport')->cursor() as $record) {
                $row = (array) $record;
                $key = $this->branch((string) $row['nama_kanca']).'|'.$this->code((string) $row['branch']).'|'.$this->name((string) $row['nama_unit']);
                yield [...$row, ...($units[$key] ?? [])];
            }
        })();

        return ['meta' => $payload, 'rows' => $rows];
    }

    public function refresh(int $year, bool $force = false): array
    {
        if (! Schema::hasTable(Source::TABLE) || ! DB::table(Source::TABLE)->where('tahun', $year)->exists()) {
            return ['available' => false, 'message' => 'Pipeline Prewash tahun '.$year.' belum diimport.'];
        }
        $loan = $this->resolveLoanSource($year);
        if ($loan === null) {
            return ['available' => false, 'message' => 'Sumber realisasi pinjaman tahun '.$year.' belum tersedia.'];
        }
        $visitTable = 'evaluasi_aktivitas_kunjungan_pemasar';
        if (! Schema::hasTable($visitTable) || ! DB::table($visitTable)->exists()) {
            return ['available' => false, 'message' => 'Report Evaluasi Aktivitas Kunjungan Pemasar belum diimport.'];
        }
        $visit = DB::table($visitTable)->where('tgl_kunjungan', '>=', $year.'-01-01')->where('tgl_kunjungan', '<', ($year + 1).'-01-01')
            ->selectRaw('COUNT(*) AS rows_count, MAX(periode) AS latest_period');
        if (in_array('updated_at', $this->columns($visitTable), true)) {
            $visit->selectRaw('MAX(updated_at) AS latest_update');
        }
        $visit = (array) $visit->first();
        if ((int) $visit['rows_count'] === 0) {
            $printedPeriod = DB::table($visitTable)->max('periode');
            if (! $printedPeriod || $printedPeriod < $year.'-01-01') {
                return ['available' => false, 'message' => 'Report kunjungan untuk tahun '.$year.' belum tersedia.'];
            }
            $visit['latest_period'] = $printedPeriod;
        }
        $pipeline = DB::table(Source::TABLE)->where('tahun', $year)->selectRaw('COUNT(*) AS rows_count, MAX(uniqueid_namareport) AS latest_id');
        if (in_array('created_at', $this->columns(Source::TABLE), true)) {
            $pipeline->selectRaw('MAX(created_at) AS latest_import');
        }
        $signature = hash('sha256', json_encode([$year, $loan, $visit, (array) $pipeline->first(), ReportCacheVersion::get('pinjaman')]));
        $key = 'pipeline-prewash:derived:v1:'.$year;
        $cached = Cache::get($key);
        if (! $force && is_array($cached) && ($cached['signature'] ?? null) === $signature) {
            return $cached['meta'];
        }

        return Cache::lock('pipeline-prewash:refresh:'.$year, 300)->block(30, function () use ($year, $loan, $visit, $signature, $key): array {
            return app(MySqlBulkLoadService::class)->withTableWriteLock(Source::TABLE, function () use ($year, $loan, $visit, $signature, $key): array {
                $months = $this->visitMonths($year);
                [$realization, $unverified] = $this->realizations($year, $loan);
                $updates = (function () use ($year, $months, $realization, $unverified) {
                    foreach (DB::table(Source::TABLE)->where('tahun', $year)->select('uniqueid_namareport', 'cif')->cursor() as $row) {
                        $cif = strtoupper(trim((string) $row->cif));
                        $month = $months[$cif] ?? array_fill(0, 12, 0);
                        $done = count(array_filter($month, static fn ($state): bool => $state === 2));
                        $values = [];
                        foreach (Source::MONTH_COLUMNS as $index => $column) {
                            $values[$column] = match ($month[$index]) {
                                2 => 'DONE', 1 => 'PLAN', default => '0'
                            };
                        }
                        $missing = $cif === '';
                        $uncertain = isset($unverified[$cif]);
                        yield [
                            'uniqueid_namareport' => (string) $row->uniqueid_namareport, ...$values,
                            'jumlah_kunjungan' => $done, 'kunjungan' => $done > 0 ? 'DONE' : 'NOT',
                            'plafond_real' => $missing || $uncertain ? null : ($realization[$cif] ?? '0.00'),
                            'status' => $missing ? 'CIF kosong' : ($uncertain ? 'Perlu verifikasi' : (array_key_exists($cif, $realization) ? 'Sudah' : 'Belum')),
                        ];
                    }
                })();
                $this->persist($year, $updates);
                $meta = [
                    'available' => true, 'year' => $year, 'loan_source' => $loan['table'], 'loan_period' => $loan['period'],
                    'visit_period' => $visit['latest_period'] ?? null, 'message' => null,
                ];
                Cache::put($key, ['signature' => $signature, 'meta' => $meta], now()->addDays(7));

                return $meta;
            });
        });
    }

    public function resolveLoanSource(int $year): ?array
    {
        $daily = null;
        if (Schema::hasTable('daily_loan_dinamis')) {
            $periods = DB::table('daily_loan_dinamis')->where('periode', '<=', $year.'-12-31')->select('periode')->distinct()->orderByDesc('periode')->pluck('periode');
            foreach ($periods as $period) {
                if ($this->nativeDaily(DB::table('daily_loan_dinamis')->where('periode', $period))->exists()) {
                    $daily = (string) $period;
                    break;
                }
            }
        }
        $lw = Schema::hasTable('lw321pn') ? DB::table('lw321pn')->where('periode', '<=', $year.'-12-31')->max('periode') : null;
        if ($daily === null && $lw === null) {
            return null;
        }
        $source = $lw !== null && ($daily === null || $lw > $daily)
            ? ['table' => 'lw321pn', 'period' => (string) $lw, 'kind' => 'lw']
            : ['table' => 'daily_loan_dinamis', 'period' => $daily, 'kind' => 'daily'];

        return (int) substr($source['period'], 0, 4) < $year ? null : $source;
    }

    private function nativeDaily(Builder $query): Builder
    {
        if (! in_array('uniqueid_namareport', $this->columns('daily_loan_dinamis'), true)) {
            return $query;
        }

        return $query->where(static fn ($q) => $q->whereNull('uniqueid_namareport')->orWhere('uniqueid_namareport', 'not like', 'LW321PN:%'));
    }

    private function cifs(int $year): Builder
    {
        return DB::table(Source::TABLE)->where('tahun', $year)->whereNotNull('cif')->whereRaw("TRIM(cif) <> ''")
            ->selectRaw('UPPER(TRIM(cif)) AS lookup_cif')->distinct();
    }

    private function visitMonths(int $year): array
    {
        $wanted = array_fill_keys($this->cifs($year)->pluck('lookup_cif')->all(), true);
        $query = DB::table('evaluasi_aktivitas_kunjungan_pemasar AS visits')
            ->where('visits.tgl_kunjungan', '>=', $year.'-01-01')->where('visits.tgl_kunjungan', '<', ($year + 1).'-01-01')
            ->selectRaw('UPPER(TRIM(visits.cifno)) AS lookup_cif')->groupByRaw('UPPER(TRIM(visits.cifno))');
        foreach (Source::MONTH_COLUMNS as $index => $column) {
            $start = sprintf('%04d-%02d-01', $year, $index + 1);
            $end = $index === 11 ? ($year + 1).'-01-01' : sprintf('%04d-%02d-01', $year, $index + 2);
            $query->selectRaw("MAX(CASE WHEN visits.tgl_kunjungan >= ? AND visits.tgl_kunjungan < ? THEN CASE UPPER(TRIM(visits.status_kunjungan)) WHEN 'DONE' THEN 2 WHEN 'PLAN' THEN 1 ELSE 0 END ELSE 0 END) AS `{$column}`", [$start, $end]);
        }
        $result = [];
        foreach ($query->cursor() as $row) {
            if (! isset($wanted[$row->lookup_cif])) {
                continue;
            }
            $result[$row->lookup_cif] = array_map(static fn ($column): int => (int) $row->$column, Source::MONTH_COLUMNS);
        }

        return $result;
    }

    private function realizations(int $year, array $source): array
    {
        $wanted = array_fill_keys($this->cifs($year)->pluck('lookup_cif')->all(), true);
        $table = $source['table'];
        $account = $source['kind'] === 'daily' ? 'nomor_rekening1' : 'no_rekening';
        $amount = $source['kind'] === 'daily' ? 'plafon' : 'plafon_dalam_idr';
        $query = DB::table($table)
            ->where($table.'.periode', $source['period'])->where($table.'.tgl_realisasi', '>=', $year.'-01-01')->where($table.'.tgl_realisasi', '<', ($year + 1).'-01-01')
            ->selectRaw('UPPER(TRIM(`'.$table.'`.cifno)) AS lookup_cif, `'.$table.'`.`'.$account.'` AS account, `'.$table.'`.`'.$amount.'` AS amount');
        if ($source['kind'] === 'daily') {
            $query = $this->nativeDaily($query);
        }
        $accounts = $uncertain = [];
        foreach ($query->cursor() as $row) {
            if (! isset($wanted[$row->lookup_cif])) {
                continue;
            }
            $key = trim((string) $row->account);
            if (ctype_digit($key)) {
                $key = ltrim($key, '0');
            }
            try {
                $value = Money::normalize($row->amount === null ? null : (string) $row->amount);
            } catch (\Throwable) {
                $value = null;
            }
            if ($key === '' || $value === null) {
                $uncertain[$row->lookup_cif] = true;

                continue;
            }
            if (isset($accounts[$row->lookup_cif][$key]) && $accounts[$row->lookup_cif][$key] !== $value) {
                $uncertain[$row->lookup_cif] = true;
            }
            $accounts[$row->lookup_cif][$key] = $value;
        }
        $result = [];
        foreach ($accounts as $cif => $rows) {
            if (isset($uncertain[$cif])) {
                continue;
            }
            $sum = '0.00';
            foreach ($rows as $value) {
                $sum = Money::add($sum, $value);
            }
            $result[$cif] = $sum;
        }

        return [$result, $uncertain];
    }

    private function persist(int $year, iterable $updates): void
    {
        $fields = [...Source::MONTH_COLUMNS, 'jumlah_kunjungan', 'kunjungan', 'plafond_real', 'status'];
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::transaction(function () use ($updates): void {
                foreach ($updates as $row) {
                    $id = $row['uniqueid_namareport'];
                    unset($row['uniqueid_namareport']);
                    DB::table(Source::TABLE)->where('uniqueid_namareport', $id)->update($row);
                }
            });

            return;
        }
        $loader = app(MySqlBulkLoadService::class);
        $pdo = $loader->createPersistentPdo();
        $temporary = 'prewash_calc_'.bin2hex(random_bytes(6));
        $path = tempnam(storage_path('app'), 'prewash_calc_');
        $out = fopen($path, 'wb');
        $count = 0;
        try {
            foreach ($updates as $row) {
                $encoded = array_map(static function ($value): string {
                    if ($value === null) {
                        return '\\N';
                    }

                    return '"'.strtr((string) $value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r']).'"';
                }, array_values($row));
                $line = implode(',', $encoded)."\n";
                if (fwrite($out, $line) !== strlen($line)) {
                    throw new RuntimeException('Gagal menulis perhitungan Pipeline Prewash.');
                }
                $count++;
            }
            fclose($out);
            $out = null;
            $definitions = ['uniqueid_namareport VARCHAR(128) PRIMARY KEY'];
            foreach ($fields as $field) {
                $definitions[] = '`'.$field.'` '.match ($field) {
                    'plafond_real' => 'DECIMAL(30,2)', 'jumlah_kunjungan' => 'SMALLINT', default => 'VARCHAR(20)'
                }.' NULL';
            }
            $pdo->exec('CREATE TEMPORARY TABLE `'.$temporary.'` ('.implode(',', $definitions).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $pdo->beginTransaction();
            $loaded = $pdo->exec('LOAD DATA LOCAL INFILE '.$pdo->quote(str_replace('\\', '/', $path)).' INTO TABLE `'.$temporary.'` CHARACTER SET utf8mb4 '."FIELDS TERMINATED BY ',' ENCLOSED BY '\"' ESCAPED BY '\\\\' LINES TERMINATED BY '\\n' (`uniqueid_namareport`,".implode(',', array_map(static fn ($field) => '`'.$field.'`', $fields)).')');
            $warnings = (int) $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn();
            if ($loaded !== $count || $warnings > 0) {
                throw new RuntimeException('Perhitungan Pipeline Prewash tidak lengkap; transaksi dibatalkan.');
            }
            $check = $pdo->prepare('SELECT COUNT(*) FROM `'.Source::TABLE.'` p JOIN `'.$temporary.'` c ON p.uniqueid_namareport=c.uniqueid_namareport WHERE p.tahun=?');
            $check->execute([$year]);
            if ((int) $check->fetchColumn() !== $count) {
                throw new RuntimeException('Dataset Pipeline Prewash berubah selama perhitungan.');
            }
            $set = implode(',', array_map(static fn ($field) => 'p.`'.$field.'`=c.`'.$field.'`', $fields));
            $statement = $pdo->prepare('UPDATE `'.Source::TABLE.'` p JOIN `'.$temporary.'` c ON p.uniqueid_namareport=c.uniqueid_namareport SET '.$set.' WHERE p.tahun=?');
            $statement->execute([$year]);
            if ((int) $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn() > 0) {
                throw new RuntimeException('Warning saat menyimpan perhitungan Pipeline Prewash.');
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

    private function mbmIdentity(string $branch, string $name, array $reference): ?array
    {
        // These variants are present in Wilayah MBM and in the supplied MBM example.
        $aliases = ['MADIUN|HENDRINURWAHYUDI' => '64850', 'MAGETAN|RITAAULIASARI' => '61165'];
        $plain = strtoupper(preg_replace('/[^a-z0-9]/i', '', $name));
        foreach ($reference as $pn => $person) {
            if ($this->branch($person['branch']) === $branch
                && (strtoupper(preg_replace('/[^a-z0-9]/i', '', $person['name'])) === $plain || ($aliases[$branch.'|'.$plain] ?? null) === (string) $pn)) {
                return ['pn' => (string) $pn, 'name' => $person['name']];
            }
        }

        return null;
    }

    private function emptyGroup(): array
    {
        return ['total_count' => 0, 'total_amount' => '0.00', 'visited_count' => 0, 'visited_share' => null, 'unvisited_count' => 0, 'unvisited_share' => null, 'realized_count' => 0, 'realized_share' => null, 'realized_amount' => '0.00'];
    }

    private function addRow(array &$group, array $row): void
    {
        $group['total_count']++;
        $group['total_amount'] = $group['total_amount'] === null || $row['plafond'] === null ? null : Money::add($group['total_amount'], (string) $row['plafond']);
        $group['visited_count'] += $row['kunjungan'] === 'DONE' ? 1 : 0;
        $group['unvisited_count'] += $row['kunjungan'] === 'DONE' ? 0 : 1;
        if ($row['status'] === 'Sudah') {
            $group['realized_count']++;
            $group['realized_amount'] = Money::add($group['realized_amount'], (string) $row['plafond_real']);
        }
    }

    private function shares(array &$row): void
    {
        $row['visited_share'] = $row['total_count'] ? round(100 * $row['visited_count'] / $row['total_count'], 2) : null;
        $row['unvisited_share'] = $row['total_count'] ? round(100 * $row['unvisited_count'] / $row['total_count'], 2) : null;
        $row['realized_share'] = $row['visited_count'] ? round(100 * $row['realized_count'] / $row['visited_count'], 2) : null;
    }

    private function columns(string $table): array
    {
        return $this->columns[$table] ??= Schema::getColumnListing($table);
    }

    private function year(?string $period): int
    {
        $period = $period === null ? null : trim($period);
        if ($period === null || trim($period) === '') {
            return (int) now()->format('Y');
        }
        if (ctype_digit($period) && preg_match('/^(19|20|21)\d{2}$/D', $period) !== 1) {
            throw new \InvalidArgumentException('Tahun Pipeline Prewash wajib dalam format YYYY (1900-2199).');
        }
        $date = preg_match('/^(19|20|21)\d{2}$/D', $period) ? $period.'-01-01' : StrictDateParser::normalize($period);
        if ($date === null) {
            throw new \InvalidArgumentException('Tahun Pipeline Prewash tidak valid.');
        }

        return (int) substr($date, 0, 4);
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

    private function branchCode(string $branch): ?string
    {
        foreach (UserBranchScope::branchScopes() as $scope) {
            if ($this->branch($scope['label']) === $branch) {
                return ltrim($scope['code4'], '0');
            }
        }

        return null;
    }
}
