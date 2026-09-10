<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ConsumerRmRealizationCalculator
{
    /**
     * Calculate monthly Consumer realization from the current nominative and
     * the preceding month-end nominative. Each realized account is counted
     * once. Briguna uses the product's CIF balance movement at origination
     * when that daily position is available, with the existing facility
     * estimate for incomplete history. KPR measures gross booked plafond.
     *
     * @return array<string, array<string, mixed>>
     */
    public function calculate(string $period, ?string $kprAssignmentPeriod = null, ?string $onlyProduct = null): array
    {
        $source = $this->resolveNominativeSource($period);
        if ($source === null) {
            return [];
        }

        $sourceTable = $source['table'];
        $periodColumn = $source['period'];
        $productColumn = $source['product'];
        $productValues = $source['product_values'];
        if ($onlyProduct !== null) {
            $productValues = array_values(array_filter($productValues, fn (string $value): bool => $this->canonicalProduct($value) === $this->canonicalProduct($onlyProduct)
            ));
        }
        $accountColumn = $source['account'];
        $realisasiDateColumn = $source['realization_date'];
        $cifColumn = $source['cif'];
        $plafonColumn = $source['plafon'];
        $outstandingColumn = $source['outstanding'];
        $managerColumn = $source['manager'];
        $initiatorColumn = $source['initiator'];
        $lookupOrderColumn = $source['lookup_order'];
        $hasInitiator = $initiatorColumn !== null;

        $previousPeriod = $this->resolvePreviousMonthEndPeriod($period, $sourceTable, $periodColumn);
        if ($previousPeriod === null) {
            return [];
        }

        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $brihcInitiators = $this->consumerBrihcInitiators();

        $currentRows = DB::table($sourceTable)
            // A monthly realization is cumulative. An account that was present
            // earlier in the month must not disappear merely because it was
            // repaid or transferred before the selected position date.
            ->whereBetween($periodColumn, [$periodStart, $period])
            ->when($source['segment'] !== null, fn ($query) => $query->where($source['segment'], 'CONSUMER'))
            ->whereIn($productColumn, $productValues)
            ->where(function ($query) use ($hasInitiator, $initiatorColumn, $managerColumn): void {
                $query->where(function ($managerQuery) use ($managerColumn): void {
                    $managerQuery
                        ->whereNotNull($managerColumn)
                        ->where($managerColumn, '<>', '');
                });
                if ($hasInitiator) {
                    $query->orWhere(function ($initiatorQuery) use ($initiatorColumn): void {
                        $initiatorQuery
                            ->whereNotNull($initiatorColumn)
                            ->where($initiatorColumn, '<>', '');
                    });
                }
            })
            ->whereNotNull($accountColumn)
            ->where($accountColumn, '<>', '')
            ->whereNotNull($cifColumn)
            ->where($cifColumn, '<>', '')
            ->whereBetween($realisasiDateColumn, [$periodStart, $period])
            ->selectRaw("COALESCE({$source['cabang']}, '') as cabang")
            ->selectRaw("COALESCE({$source['unit']}, '') as unit")
            ->selectRaw("COALESCE({$source['branch_code']}, '') as branch_code")
            ->selectRaw("COALESCE({$source['rm']}, '') as rm")
            ->selectRaw("CASE WHEN {$productColumn} IN ('BRIGUNAKONSUMER', 'BRIGUNA-KONSUMER') THEN 'BRIGUNA-KONSUMER' ELSE {$productColumn} END as produk")
            ->selectRaw("UPPER(TRIM({$cifColumn})) as clean_cif")
            ->selectRaw("UPPER(TRIM({$accountColumn})) as account_key")
            ->selectRaw("MAX(COALESCE({$plafonColumn}, 0)) as current_plafon")
            ->selectRaw("MIN({$periodColumn}) as first_seen_period")
            ->selectRaw("MAX({$periodColumn}) as last_seen_period")
            ->selectRaw("MIN({$realisasiDateColumn}) as realization_date")
            ->selectRaw($hasInitiator ? "COALESCE({$initiatorColumn}, '') as initiator" : "'' as initiator")
            ->groupBy([
                $source['cabang'],
                $source['unit'],
                $source['branch_code'],
                $source['rm'],
                $productColumn,
                $cifColumn,
                $accountColumn,
            ])
            ->when($hasInitiator, fn ($query) => $query->groupBy($initiatorColumn))
            ->orderBy('first_seen_period')
            ->orderBy('account_key')
            ->orderBy('rm')
            ->orderBy('initiator')
            ->get();

        if ($currentRows->isEmpty()) {
            return [];
        }

        $kprAssignments = $this->kprAccountAssignments(
            $currentRows,
            $periodStart,
            $kprAssignmentPeriod ?? $period
        );

        // Deduplicate repeated daily positions. Briguna keeps its first event
        // attribution; KPR ownership is resolved separately at the cutoff.
        $deduplicatedCurrentRows = [];
        foreach ($currentRows as $row) {
            $row->account_key = $this->canonicalAccountKey((string) ($row->account_key ?? ''));
            $candidateKey = (string) ($row->produk ?? '').'|'
                .($row->produk === 'KPR' ? '' : (string) ($row->clean_cif ?? '')).'|'
                .(string) ($row->account_key ?? '');
            if ($candidateKey === '||') {
                continue;
            }
            if (! isset($deduplicatedCurrentRows[$candidateKey])) {
                $deduplicatedCurrentRows[$candidateKey] = $row;

                continue;
            }

            $existing = $deduplicatedCurrentRows[$candidateKey];
            $firstSeen = min(
                (string) ($existing->first_seen_period ?? $period),
                (string) ($row->first_seen_period ?? $period)
            );
            $greatestPlafon = max(
                (float) ($existing->current_plafon ?? 0.0),
                (float) ($row->current_plafon ?? 0.0)
            );
            if ((string) ($row->first_seen_period ?? $period) < (string) ($existing->first_seen_period ?? $period)) {
                $deduplicatedCurrentRows[$candidateKey] = $row;
            }
            $deduplicatedCurrentRows[$candidateKey]->first_seen_period = $firstSeen;
            $deduplicatedCurrentRows[$candidateKey]->current_plafon = $greatestPlafon;
        }

        $currentMetricsByCif = [];
        $bookingsByCifDate = [];
        foreach ($deduplicatedCurrentRows as $row) {
            $cleanCif = (string) ($row->clean_cif ?? '');
            $accountKey = (string) ($row->account_key ?? '');
            if ($cleanCif === '' || $accountKey === '') {
                continue;
            }

            $product = (string) ($row->produk ?? '');
            $bookingKey = $product.'|'.$cleanCif.'|'.(string) ($row->realization_date ?? '');
            $bookingsByCifDate[$bookingKey] = ($bookingsByCifDate[$bookingKey] ?? 0) + 1;
            $group = [
                'cabang' => (string) ($row->cabang ?? ''),
                'unit' => (string) ($row->unit ?? ''),
                'branch_code' => (string) ($row->branch_code ?? ''),
                'rm' => $this->resolveConsumerRm(
                    $product === 'KPR' ? '' : (string) ($row->initiator ?? ''),
                    $product === 'KPR'
                        ? ($kprAssignments[$accountKey] ?? (string) ($row->rm ?? ''))
                        : (string) ($row->rm ?? ''),
                    $product,
                    $brihcInitiators
                ),
                'produk' => $product,
            ];
            if ($group['rm'] === '') {
                continue;
            }
            $groupKey = $this->groupKey($group);
            $metricKey = $groupKey.'|'.$cleanCif;
            $currentMetricsByCif[$metricKey] ??= $group + [
                'group_key' => $groupKey,
                'clean_cif' => $cleanCif,
                'account_plafon' => [],
                'account_first_period' => [],
                'account_realization_date' => [],
            ];

            // The same nominative account can be duplicated by the extract.
            // Count it once and keep its greatest current plafond.
            $currentMetricsByCif[$metricKey]['account_plafon'][$accountKey] = max(
                (float) ($currentMetricsByCif[$metricKey]['account_plafon'][$accountKey] ?? 0.0),
                (float) ($row->current_plafon ?? 0.0)
            );
            $currentMetricsByCif[$metricKey]['account_first_period'][$accountKey] ??=
                (string) ($row->first_seen_period ?? $period);
            $currentMetricsByCif[$metricKey]['account_realization_date'][$accountKey] ??=
                (string) ($row->realization_date ?? '');
        }

        if ($currentMetricsByCif === []) {
            return [];
        }

        $currentCifs = collect($currentMetricsByCif)
            ->pluck('clean_cif')
            ->unique()
            ->values()
            ->all();
        $eventPeriods = collect($currentMetricsByCif)
            ->flatMap(static fn (array $metric): array => array_values($metric['account_first_period']))
            ->map(static fn ($value): string => substr((string) $value, 0, 10))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
        $historyPeriods = collect($eventPeriods)
            ->push($previousPeriod)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $historyByPeriod = [];
        foreach ($historyPeriods as $sourcePeriod) {
            foreach (array_chunk($currentCifs, 1000) as $cifChunk) {
                DB::table($sourceTable)
                    // Exact period equality lets the existing
                    // (periode, cifno_clean) index serve both predicates.
                    ->where($periodColumn, $sourcePeriod)
                    ->whereIn($cifColumn, $cifChunk)
                    ->when($source['segment'] !== null, fn ($query) => $query->where($source['segment'], 'CONSUMER'))
                    ->whereIn($productColumn, $productValues)
                    ->whereNotNull($accountColumn)
                    ->where($accountColumn, '<>', '')
                    ->whereNotNull($cifColumn)
                    ->where($cifColumn, '<>', '')
                    ->selectRaw("CASE WHEN {$productColumn} IN ('BRIGUNAKONSUMER', 'BRIGUNA-KONSUMER') THEN 'BRIGUNA-KONSUMER' ELSE {$productColumn} END as produk")
                    ->selectRaw("UPPER(TRIM({$cifColumn})) as clean_cif")
                    ->selectRaw("UPPER(TRIM({$accountColumn})) as account_key")
                    ->selectRaw("MAX(COALESCE({$outstandingColumn}, 0)) as previous_os")
                    ->selectRaw("MIN(COALESCE(NULLIF({$lookupOrderColumn}, ''), {$accountColumn})) as lookup_order")
                    ->groupBy([$productColumn, $cifColumn, $accountColumn])
                    ->orderBy('produk')
                    ->orderBy('clean_cif')
                    ->orderBy('lookup_order')
                    ->chunk(1000, function ($rows) use (&$historyByPeriod, $sourcePeriod): void {
                        foreach ($rows as $row) {
                            $product = $this->canonicalProduct((string) ($row->produk ?? ''));
                            $cleanCif = (string) ($row->clean_cif ?? '');
                            $accountKey = $this->canonicalAccountKey((string) ($row->account_key ?? ''));
                            if ($product === '' || $cleanCif === '' || $accountKey === '') {
                                continue;
                            }

                            $existing = $historyByPeriod[$sourcePeriod][$product][$cleanCif][$accountKey] ?? null;
                            $historyByPeriod[$sourcePeriod][$product][$cleanCif][$accountKey] = [
                                'os' => (float) ($row->previous_os ?? 0.0),
                                'lookup_order' => (string) ($row->lookup_order ?? $accountKey),
                            ];
                            if (is_array($existing)) {
                                $historyByPeriod[$sourcePeriod][$product][$cleanCif][$accountKey]['os'] = max(
                                    (float) ($existing['os'] ?? 0.0),
                                    (float) ($row->previous_os ?? 0.0)
                                );
                                $historyByPeriod[$sourcePeriod][$product][$cleanCif][$accountKey]['lookup_order'] = min(
                                    (string) ($existing['lookup_order'] ?? $accountKey),
                                    (string) ($row->lookup_order ?? $accountKey)
                                );
                            }
                        }
                    });
            }
        }

        $metricsByGroup = [];
        $usedPreviousAccounts = [];
        foreach ($currentMetricsByCif as $metric) {
            $cleanCif = (string) $metric['clean_cif'];
            $product = $this->canonicalProduct((string) $metric['produk']);
            $netDisbursement = 0.0;
            $realizedAccounts = [];
            $newAccounts = [];
            $newDisbursement = 0.0;
            $supplementAccounts = [];
            $supplementDisbursement = 0.0;

            foreach ($metric['account_plafon'] as $accountKey => $currentPlafon) {
                $firstSeenPeriod = (string) ($metric['account_first_period'][$accountKey] ?? $period);
                $baselineAccounts = $historyByPeriod[$previousPeriod][$product][$cleanCif] ?? [];
                $realizationAccounts = $historyByPeriod[$firstSeenPeriod][$product][$cleanCif] ?? [];
                $previousOs = 0.0;
                $selectedPreviousAccount = null;
                // Classification follows the product-specific customer
                // baseline at the preceding month-end. KPR history must not
                // turn a first Briguna facility into a supplement (and vice
                // versa).
                $isSupplement = $baselineAccounts !== [];

                if (isset($baselineAccounts[$accountKey])) {
                    $previousOs = (float) ($baselineAccounts[$accountKey]['os'] ?? 0.0);
                    $selectedPreviousAccount = $accountKey;
                } else {
                    $replacementCandidates = [];
                    foreach ($baselineAccounts as $previousAccount => $baselineAccount) {
                        $usageKey = $product.'|'.$cleanCif.'|'.$previousAccount;
                        if (
                            isset($realizationAccounts[$previousAccount])
                            || isset($usedPreviousAccounts[$usageKey])
                        ) {
                            continue;
                        }
                        $outstanding = (float) ($baselineAccount['os'] ?? 0.0);
                        if ($outstanding <= 0.0) {
                            continue;
                        }
                        $replacementCandidates[$previousAccount] = [
                            'os' => $outstanding,
                            'lookup_order' => (string) ($baselineAccount['lookup_order'] ?? $previousAccount),
                        ];
                    }
                    if ($replacementCandidates !== []) {
                        // Retain the deterministic facility estimate when the
                        // origination-day position is missing. A later monthly
                        // position cannot reconstruct the event's CIF balance.
                        uasort($replacementCandidates, static function (array $left, array $right): int {
                            return strcmp($left['lookup_order'], $right['lookup_order']);
                        });
                        $selectedPreviousAccount = (string) array_key_first($replacementCandidates);
                        $previousOs = (float) $replacementCandidates[$selectedPreviousAccount]['os'];
                    }
                }

                if ($selectedPreviousAccount !== null) {
                    $usedPreviousAccounts[$product.'|'.$cleanCif.'|'.$selectedPreviousAccount] = true;
                }

                // KPR/KPRS in the Kanwil time series is gross origination.
                // An existing CIF determines its classification, but does not
                // reduce its booked plafond. Keep Briguna's net rule separate.
                $accountNet = max(0.0, (float) $currentPlafon - ($product === 'KPR' ? 0.0 : $previousOs));
                if ($product === 'BRIGUNA-KONSUMER'
                    && $firstSeenPeriod === $metric['account_realization_date'][$accountKey]
                    && ($bookingsByCifDate[$product.'|'.$cleanCif.'|'.$firstSeenPeriod] ?? 0) === 1
                    && isset($realizationAccounts[$accountKey])
                    && abs($realizationAccounts[$accountKey]['os'] - (float) $currentPlafon) < 0.01) {
                    // Kanwil's daily series measures the full product/CIF
                    // movement against the previous month-end on each booking
                    // date. Include retained facilities and earlier bookings;
                    // subtract every prior facility, not just one closed loan.
                    // Only apply the reconciled single-booking case while its
                    // balance still equals booked plafond. Same-day multiple
                    // bookings and already-amortized facilities remain on the
                    // estimate until their event allocation is established.
                    $accountNet = max(0.0,
                        array_sum(array_column($realizationAccounts, 'os'))
                        - array_sum(array_column($baselineAccounts, 'os'))
                    );
                }
                $realizedAccounts[$accountKey] = true;
                $netDisbursement += $accountNet;

                if (! $isSupplement) {
                    $newAccounts[$accountKey] = true;
                    $newDisbursement += $accountNet;
                } else {
                    $supplementAccounts[$accountKey] = true;
                    $supplementDisbursement += $accountNet;
                }
            }

            $groupKey = (string) $metric['group_key'];
            $metricsByGroup[$groupKey] ??= [
                'cabang' => $metric['cabang'],
                'unit' => $metric['unit'],
                'branch_code' => $metric['branch_code'],
                'rm' => $metric['rm'],
                'produk' => $metric['produk'],
                'accounts' => [],
                'realisasi_deb' => 0,
                'realisasi_os' => 0.0,
                'realisasi_baru_deb' => 0,
                'realisasi_baru_os' => 0.0,
                'suplesi_deb' => 0,
                'suplesi_os' => 0.0,
            ];

            if ($realizedAccounts === []) {
                continue;
            }

            foreach (array_keys($realizedAccounts) as $accountKey) {
                $metricsByGroup[$groupKey]['accounts'][$accountKey] = true;
            }
            $accountCount = count($realizedAccounts);
            $metricsByGroup[$groupKey]['realisasi_deb'] += $accountCount;
            $metricsByGroup[$groupKey]['realisasi_os'] += $netDisbursement;

            $metricsByGroup[$groupKey]['realisasi_baru_deb'] += count($newAccounts);
            $metricsByGroup[$groupKey]['realisasi_baru_os'] += $newDisbursement;
            $metricsByGroup[$groupKey]['suplesi_deb'] += count($supplementAccounts);
            $metricsByGroup[$groupKey]['suplesi_os'] += $supplementDisbursement;
        }

        return $metricsByGroup;
    }

    /**
     * Resolve each KPR facility's last observed manager through the reporting
     * cutoff. Transfers can split a portfolio: never alias an entire old PN to
     * another PN, or read ownership from positions later than the cutoff.
     *
     * @param  iterable<object>  $currentRows
     * @return array<string, string>
     */
    private function kprAccountAssignments(iterable $currentRows, string $start, string $cutoff): array
    {
        $accounts = [];
        foreach ($currentRows as $row) {
            if ($row->produk === 'KPR') {
                $accounts[$this->canonicalAccountKey((string) $row->account_key)] = true;
            }
        }
        if ($accounts === []) {
            return [];
        }
        $source = $this->resolveNominativeSource($cutoff);
        if ($source === null) {
            return [];
        }

        $assignments = [];
        $rows = DB::table($source['table'])
            ->whereBetween($source['period'], [$start, $cutoff])
            ->where($source['product'], 'KPR')
            ->when($source['segment'] !== null, fn ($query) => $query->where($source['segment'], 'CONSUMER'))
            ->whereNotNull($source['rm'])
            ->where($source['rm'], '<>', '')
            ->selectRaw("{$source['account']} as account_key, {$source['rm']} as rm, {$source['period']} as position")
            ->orderByDesc($source['period'])
            ->orderBy($source['rm'])
            ->get();
        foreach ($rows as $row) {
            // Source switches may add/remove leading zeroes. Compare canonical
            // keys after the bounded KPR/date query, not raw account strings.
            $key = $this->canonicalAccountKey((string) $row->account_key);
            if (isset($accounts[$key]) && ! isset($assignments[$key])) {
                $assignments[$key] = (string) $row->rm;
            }
        }

        return $assignments;
    }

    /** @param array<string, mixed> $row */
    public function groupKey(array $row): string
    {
        return implode('|', [
            (string) ($row['cabang'] ?? ''),
            (string) ($row['unit'] ?? ''),
            (string) ($row['branch_code'] ?? ''),
            (string) ($row['rm'] ?? ''),
            $this->canonicalProduct((string) ($row['produk'] ?? '')),
        ]);
    }

    /**
     * Prefer the compact immutable archive only when it covers the selected
     * position, its baseline, and every still-present raw position in between.
     * This keeps mixed deployments on the original source until capture is
     * complete, while allowing exact recalculation after daily raw pruning.
     *
     * @return array{
     *     table:string,period:string,segment:?string,product:string,
     *     product_values:array<int,string>,cif:string,account:string,
     *     realization_date:string,plafon:string,outstanding:string,
     *     cabang:string,unit:string,branch_code:string,rm:string,
     *     manager:string,initiator:?string,lookup_order:string
     * }|null
     */
    private function resolveNominativeSource(string $period): ?array
    {
        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $previousEnd = Carbon::parse($period)
            ->subMonthNoOverflow()
            ->endOfMonth()
            ->toDateString();

        try {
            $archive = app(ConsumerRmPositionHistoryStore::class);
            if ($archive->hasCapture($period) && $archive->hasCapture($previousEnd)) {
                $archivePeriods = $archive->availablePeriods($periodStart, $period);
                $rawPeriods = Schema::hasTable('daily_loan_dinamis')
                    ? DB::table('daily_loan_dinamis')
                        ->whereBetween('periode', [$periodStart, $period])
                        ->distinct()
                        ->orderBy('periode')
                        ->pluck('periode')
                        ->map(static fn ($value): string => substr((string) $value, 0, 10))
                        ->all()
                    : [];

                if (array_diff($rawPeriods, $archivePeriods) === []) {
                    return [
                        'table' => ConsumerRmPositionHistoryStore::HISTORY_TABLE,
                        'period' => 'periode',
                        'segment' => null,
                        'product' => 'produk',
                        'product_values' => ['BRIGUNA-KONSUMER', 'KPR'],
                        'cif' => 'cifno_clean',
                        'account' => 'account_key',
                        'realization_date' => 'tgl_realisasi',
                        'plafon' => 'plafon',
                        'outstanding' => 'baki_debet',
                        'cabang' => 'cabang',
                        'unit' => 'unit',
                        'branch_code' => 'branch_code',
                        'rm' => 'rm',
                        'manager' => 'pn_pengelola',
                        'initiator' => 'pn_pemrakarsa',
                        'lookup_order' => 'lookup_order',
                    ];
                }
            }
        } catch (Throwable) {
            // Archive support is additive. An incomplete deployment must keep
            // reading the original nominative instead of breaking dashboards.
        }

        if (! Schema::hasTable('daily_loan_dinamis')) {
            return null;
        }

        $realizationDate = Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi1')
            ? 'tgl_realisasi1'
            : 'tgl_realisasi';
        $cif = Schema::hasColumn('daily_loan_dinamis', 'cifno_clean')
            ? 'cifno_clean'
            : 'cifno';
        $initiator = Schema::hasColumn('daily_loan_dinamis', 'pn_pemrakarsa1')
            ? 'pn_pemrakarsa1'
            : null;
        $lookupOrder = Schema::hasColumn('daily_loan_dinamis', 'uniqueid_namareport')
            ? 'uniqueid_namareport'
            : 'nomor_rekening1';

        return [
            'table' => 'daily_loan_dinamis',
            'period' => 'periode',
            'segment' => 'segmen_kinerja',
            'product' => 'produk_kinerja',
            'product_values' => ['BRIGUNAKONSUMER', 'KPR'],
            'cif' => $cif,
            'account' => 'nomor_rekening1',
            'realization_date' => $realizationDate,
            'plafon' => 'plafon',
            'outstanding' => 'baki_debet1',
            'cabang' => 'cabang_normalized',
            'unit' => 'unit_normalized',
            'branch_code' => 'branch_normalized',
            'rm' => 'rm_normalized',
            'manager' => 'pn_pengelola1',
            'initiator' => $initiator,
            'lookup_order' => $lookupOrder,
        ];
    }

    private function resolvePreviousMonthEndPeriod(
        string $period,
        string $sourceTable,
        string $periodColumn
    ): ?string {
        $previousEnd = Carbon::parse($period)
            ->subMonthNoOverflow()
            ->endOfMonth()
            ->toDateString();

        return DB::table($sourceTable)
            ->where($periodColumn, $previousEnd)
            ->exists()
                ? $previousEnd
                : null;
    }

    private function canonicalProduct(string $product): string
    {
        $token = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($product))) ?? '';

        return $token === 'BRIGUNAKONSUMER' ? 'BRIGUNA-KONSUMER' : $token;
    }

    private function canonicalAccountKey(string $account): string
    {
        $account = strtoupper(trim($account));
        if ($account === '') {
            return '';
        }

        $withoutLeadingZero = ltrim($account, '0');

        return $withoutLeadingZero !== '' ? $withoutLeadingZero : '0';
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function consumerBrihcInitiators(): array
    {
        if (! Schema::hasTable('brihc_pemasar')) {
            return [];
        }

        foreach (['pernr', 'completename', 'positiondesc'] as $column) {
            if (! Schema::hasColumn('brihc_pemasar', $column)) {
                return [];
            }
        }

        $products = [
            'RM BISNIS KONSUMER - BRIGUNA' => 'BRIGUNA-KONSUMER',
            'RM BISNIS KONSUMER - KPR' => 'KPR',
        ];
        $references = [];

        foreach (DB::table('brihc_pemasar')
            ->whereIn(DB::raw("UPPER(TRIM(COALESCE(positiondesc, '')))"), array_keys($products))
            ->get(['pernr', 'completename', 'positiondesc']) as $record) {
            $product = $products[strtoupper(trim((string) $record->positiondesc))] ?? null;
            $name = trim((string) $record->completename);
            $pn = ltrim(preg_replace('/\D+/', '', (string) $record->pernr) ?? '', '0');
            if ($product === null || $name === '' || $pn === '') {
                continue;
            }

            $rm = str_pad($pn, 8, '0', STR_PAD_LEFT).' - '.$name;
            $references[$product]['PN:'.$pn] = $rm;
            $nameKey = $this->consumerNameKey($name);
            if ($nameKey !== '') {
                $references[$product]['NAME:'.$nameKey] = $rm;
            }
        }

        return $references;
    }

    /**
     * @param  array<string, array<string, string>>  $references
     */
    private function resolveConsumerRm(string $initiator, string $manager, string $product, array $references): string
    {
        $product = $this->canonicalProduct($product);
        foreach ([$initiator, $manager] as $candidate) {
            $reference = $this->resolveConsumerRmReference($candidate, $product, $references);
            if ($reference !== null) {
                return $reference;
            }
        }

        return trim($manager);
    }

    /**
     * @param  array<string, array<string, string>>  $references
     */
    private function resolveConsumerRmReference(string $candidate, string $product, array $references): ?string
    {
        preg_match_all('/\d{5,}/', $candidate, $matches);
        foreach ($matches[0] ?? [] as $rawPn) {
            $pn = ltrim($rawPn, '0');
            if (isset($references[$product]['PN:'.$pn])) {
                return $references[$product]['PN:'.$pn];
            }
        }

        $name = preg_replace('/^\s*\d+\s*-\s*/', '', trim($candidate)) ?? '';
        $nameKey = $this->consumerNameKey($name);
        if ($nameKey !== '' && isset($references[$product]['NAME:'.$nameKey])) {
            return $references[$product]['NAME:'.$nameKey];
        }

        return null;
    }

    private function consumerNameKey(string $name): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($name))) ?? '';
    }
}
