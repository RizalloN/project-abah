@php
    $meta = (array) data_get($microPerformance, 'meta', []);
    $realization = (array) data_get($microPerformance, 'realization', []);
    $netDisbursement = (array) data_get($microPerformance, 'net_disbursement', []);
    $pdwkLimits = (array) data_get($microPerformance, 'pdwk_limits', []);
    $decisionRanking = (array) data_get($microPerformance, 'decision_ranking', []);
    $realizationNeed = (array) data_get($microPerformance, 'realization_need', []);
    $ph = (array) data_get($microPerformance, 'ph', []);
    $realizationNeedOptions = collect([
        array_merge($realizationNeed, ['key' => 'all', 'label' => 'Semua Produk']),
        ...(array) data_get($realizationNeed, 'products', []),
    ])->values()->all();
    $mantriPerformance = (array) data_get($microPerformance, 'mantri_performance', []);
    $rmKurProductivity = (array) data_get($microPerformance, 'rm_kur_productivity', []);
    $burden = (array) data_get($microPerformance, 'burden', []);
    $unproductiveMantri = (array) data_get($microPerformance, 'unproductive_mantri', []);
    $pipeline = (array) data_get($microPerformance, 'pipeline', []);
    $pipelineSummary = (array) data_get($pipeline, 'summary', []);
    $slikHijau = (array) data_get($pipeline, 'slik_hijau', []);
    $slikSummary = (array) data_get($slikHijau, 'summary', []);
    $pipelineInitial = (array) data_get($pipeline, 'initial_records', []);
    $pipelineInitialRows = (array) data_get($pipelineInitial, 'data', []);
    $billing = (array) data_get($microPerformance, 'billing', []);
    $billingM0 = (array) data_get($billing, 'm0', []);
    $billingM1 = (array) data_get($billing, 'm1', []);
    $billingCards = (array) data_get($billing, 'cards', []);
    $formatInteger = static fn ($value): string => number_format((int) $value, 0, ',', '.');
    $formatAmount = static function ($value): string {
        $amount = (float) $value;
        $millions = abs($amount) / 1_000_000;
        $decimals = abs($millions) >= 100 ? 0 : (abs($millions) >= 10 ? 1 : 2);

        return ($amount < 0 ? '-' : '').'Rp '.number_format($millions, $decimals, ',', '.').' jt';
    };
    $formatJuta = static fn ($value): string => number_format((float) $value / 1_000_000, 0, ',', '.');
    $formatPercent = static fn ($value): string => number_format((float) $value, 1, ',', '.').'%';
    $formatDelta = static function ($value) use ($formatAmount): string {
        $number = (float) $value;

        return ($number > 0 ? '+' : ($number < 0 ? '-' : '')).$formatAmount(abs($number));
    };
    $deltaClass = static function ($value, string $metric): string {
        $number = (float) $value;
        if ($number === 0.0) {
            return 'neutral';
        }

        $isFavourable = $metric === 'os' ? $number > 0.0 : $number < 0.0;

        return $isFavourable ? 'positive' : 'negative';
    };
    $kupedesPending = (array) data_get($realization, 'kupedes_not_realized', []);
    $kupedesPendingByBranch = collect($kupedesPending)
        ->groupBy(static function (array $person): string {
            $branch = trim((string) data_get($person, 'branch', ''));

            return $branch !== '' ? $branch : 'TANPA CABANG';
        })
        ->map(static function ($people, string $branch): array {
            $items = collect($people)
                ->sortBy(static fn (array $person): string => strtoupper(
                    trim((string) data_get($person, 'unit', '')).'|'.trim((string) data_get($person, 'name', '')).'|'.trim((string) data_get($person, 'pn', ''))
                ))
                ->values();

            return [
                'branch' => $branch,
                'people' => $items->all(),
                'mantri_count' => $items->count(),
                'unit_count' => $items->pluck('unit')->filter()->unique()->count(),
            ];
        })
        ->sortBy(static fn (array $branch): string => strtoupper($branch['branch']))
        ->values();
    $kupedesPendingTotal = count($kupedesPending);
    $mantriRosterSummary = (array) data_get($realization, 'mantri_roster', []);
@endphp

<div class="micro-ops" data-micro-performance-ready="1">
    @if(empty($meta['available']))
        <div class="micro-ops-empty" role="status">
            <i class="fas fa-database" aria-hidden="true"></i>
            <strong>Data Mikro belum tersedia</strong>
        </div>
    @else
        <section class="micro-ops-section micro-ops-section--realization" aria-labelledby="micro-realization-title">
            <div class="micro-ops-section__head">
                <div>
                    <span class="micro-ops-section__number">01</span>
                    <span class="micro-ops-eyebrow">REALISASI BULAN BERJALAN</span>
                    <h3 id="micro-realization-title">Plafon &amp; Nett Disbursement</h3>
                </div>
                <div class="micro-ops-context">
                    <span><i class="far fa-calendar-alt" aria-hidden="true"></i>{{ data_get($meta, 'period_label', '-') }}</span>
                    <button type="button" class="micro-ops-refresh" data-micro-performance-refresh aria-label="Perbarui data Mikro">
                        <i class="fas fa-sync-alt" aria-hidden="true"></i><span>Perbarui</span>
                    </button>
                </div>
            </div>

            <div class="micro-realization-layout">
                <div class="micro-realization-summary-grid">
                    <article class="micro-realization-type-card type-plafond">
                        <span class="micro-realization-type-card__icon"><i class="fas fa-file-signature" aria-hidden="true"></i></span>
                        <div>
                            <span>Plafon (Realisasi Baru)</span>
                            <strong>{{ $formatAmount(data_get($realization, 'total.amount', 0)) }}</strong>
                            <small>{{ $formatInteger(data_get($realization, 'total.deb', 0)) }} rekening</small>
                        </div>
                    </article>
                    <article class="micro-realization-type-card type-nett">
                        <span class="micro-realization-type-card__icon"><i class="fas fa-chart-line" aria-hidden="true"></i></span>
                        <div>
                            <span>Nett Disbursement</span>
                            <strong>{{ $formatAmount(data_get($netDisbursement, 'total.amount', 0)) }}</strong>
                            <small>{{ $formatInteger(data_get($netDisbursement, 'total.deb', 0)) }} rekening</small>
                        </div>
                        <div class="micro-nett-type-list" aria-label="Komposisi nett disbursement">
                            @foreach((array) data_get($netDisbursement, 'types', []) as $type)
                                <span><b>{{ data_get($type, 'key') === 'baru' ? 'Baru' : data_get($type, 'label', '-') }}</b>{{ $formatAmount(data_get($type, 'amount', 0)) }}</span>
                            @endforeach
                        </div>
                    </article>
                    <article class="micro-realization-type-card type-runoff">
                        <span class="micro-realization-type-card__icon"><i class="fas fa-level-down-alt" aria-hidden="true"></i></span>
                        <div>
                            <span>Run Off Mikro</span>
                            <strong>{{ $formatAmount(data_get($realizationNeed, 'run_off', 0)) }}</strong>
                            <small>{{ data_get($realizationNeed, 'period_label', data_get($meta, 'period_label', '-')) }}</small>
                        </div>
                    </article>
                    <article class="micro-realization-type-card type-ph">
                        <span class="micro-realization-type-card__icon"><i class="fas fa-receipt" aria-hidden="true"></i></span>
                        <div class="micro-ph-card__head">
                            <span>PH Mikro</span>
                            <small>{{ data_get($ph, 'comparison_period_label', '-') }} ke {{ data_get($ph, 'period_label', '-') }}</small>
                        </div>
                        <div class="micro-ph-card__metrics">
                            <span><b>Lunas</b><strong>{{ $formatAmount(data_get($ph, 'lunas.amount', 0)) }}</strong><small>{{ $formatInteger(data_get($ph, 'lunas.deb', 0)) }} debitur</small></span>
                            <span><b>Turun Pokok</b><strong>{{ $formatAmount(data_get($ph, 'turun_pokok.amount', 0)) }}</strong><small>{{ $formatInteger(data_get($ph, 'turun_pokok.deb', 0)) }} debitur</small></span>
                        </div>
                    </article>
                </div>

                <div class="micro-realization-products">
                    <div class="micro-ops-subhead"><h4>Realisasi per Produk</h4></div>
                    <div class="micro-mantri-roster-strip" aria-label="Komposisi roster Mantri aktif">
                        <span><small>Mantri Aktif</small><strong>{{ $formatInteger(data_get($mantriRosterSummary, 'total_active', 0)) }}</strong></span>
                        <span><small>PT Non Briguna</small><strong>{{ $formatInteger(data_get($mantriRosterSummary, 'pt', 0)) }}</strong></span>
                        <span><small>Kontrak</small><strong>{{ $formatInteger(data_get($mantriRosterSummary, 'contract', 0)) }}</strong></span>
                        <span><small>Briguna</small><strong>{{ $formatInteger(data_get($mantriRosterSummary, 'briguna', 0)) }}</strong></span>
                    </div>
                    <div class="micro-ops-table-wrap">
                        <table class="micro-ops-table">
                    <caption class="sr-only">Realisasi plafon baru Mikro per produk</caption>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th class="num">Rekening</th>
                            <th class="num">Plafon Baru</th>
                            <th class="num">Mantri Sudah Real</th>
                            <th class="num">Mantri Belum Real</th>
                            <th class="num">Kontribusi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse((array) data_get($realization, 'products', []) as $product)
                            <tr>
                                <th scope="row">{{ data_get($product, 'label', '-') }}</th>
                                <td class="num">{{ $formatInteger(data_get($product, 'deb', 0)) }}</td>
                                <td class="num strong">{{ $formatAmount(data_get($product, 'amount', 0)) }}</td>
                                <td class="num"><span class="micro-mantri-count is-realized">{{ $formatInteger(data_get($product, 'mantri_realized', 0)) }}</span></td>
                                <td class="num"><span class="micro-mantri-count is-pending">{{ $formatInteger(data_get($product, 'mantri_not_realized', 0)) }}</span></td>
                                <td class="num"><span class="micro-share-badge">{{ $formatPercent(data_get($product, 'share', 0)) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">-</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">Total</th>
                            <td class="num">{{ $formatInteger(data_get($realization, 'total.deb', 0)) }}</td>
                            <td class="num strong">{{ $formatAmount(data_get($realization, 'total.amount', 0)) }}</td>
                            <td class="num"><span class="micro-mantri-count is-realized">{{ $formatInteger(data_get($realization, 'mantri.realized', 0)) }}</span></td>
                            <td class="num"><span class="micro-mantri-count is-pending">{{ $formatInteger(data_get($realization, 'mantri.not_realized', 0)) }}</span></td>
                            <td class="num">100,0%</td>
                        </tr>
                    </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="micro-kupedes-pending" aria-labelledby="micro-kupedes-pending-title">
                <div class="micro-kupedes-pending__head">
                    <div>
                        <span class="micro-kupedes-pending__icon"><i class="fas fa-user-clock" aria-hidden="true"></i></span>
                        <div>
                            <h4 id="micro-kupedes-pending-title">Mantri Belum Realisasi Kupedes</h4>
                            <p>{{ $formatInteger(data_get($mantriRosterSummary, 'kupedes_realized', 0)) }} dari {{ $formatInteger(data_get($mantriRosterSummary, 'kupedes_eligible', 0)) }} Mantri eligible sudah realisasi</p>
                        </div>
                    </div>
                    <div class="micro-kupedes-pending__headline-stats" aria-label="Ringkasan Mantri belum realisasi Kupedes">
                        <strong><b>{{ $formatInteger($kupedesPendingTotal) }}</b> Belum realisasi</strong>
                        <span>Basis {{ $formatInteger(data_get($mantriRosterSummary, 'kupedes_eligible', 0)) }} Mantri</span>
                    </div>
                </div>
                @if($kupedesPendingByBranch->isNotEmpty())
                    <div class="micro-kupedes-pending__summary" aria-label="Ringkasan Mantri belum realisasi per cabang">
                        @foreach($kupedesPendingByBranch as $branchSummary)
                            @php
                                $branchCount = (int) data_get($branchSummary, 'mantri_count', 0);
                                $branchShare = $kupedesPendingTotal > 0 ? ($branchCount / $kupedesPendingTotal) * 100 : 0;
                            @endphp
                            <article class="micro-kupedes-branch">
                                <div class="micro-kupedes-branch__head">
                                    <span class="micro-kupedes-branch__icon"><i class="fas fa-landmark" aria-hidden="true"></i></span>
                                    <div>
                                        <span>Cabang</span>
                                        <strong>{{ data_get($branchSummary, 'branch', '-') }}</strong>
                                    </div>
                                    <b>{{ $formatInteger($branchCount) }}</b>
                                </div>
                                <div class="micro-kupedes-branch__metrics">
                                    <div><span>Mantri</span><strong>{{ $formatInteger($branchCount) }}</strong></div>
                                    <div><span>Unit terdampak</span><strong>{{ $formatInteger(data_get($branchSummary, 'unit_count', 0)) }}</strong></div>
                                    <div><span>Porsi Area</span><strong>{{ $formatPercent($branchShare) }}</strong></div>
                                </div>
                                <details class="micro-kupedes-branch__details">
                                    <summary>
                                        <span>Lihat daftar Mantri</span>
                                        <span>{{ $formatInteger($branchCount) }} orang <i class="fas fa-chevron-down" aria-hidden="true"></i></span>
                                    </summary>
                                    <ol class="micro-kupedes-branch__people">
                                        @foreach((array) data_get($branchSummary, 'people', []) as $person)
                                            <li>
                                                <div>
                                                    <strong>{{ data_get($person, 'name') ?: data_get($person, 'pn', '-') }}</strong>
                                                    <span>{{ data_get($person, 'unit', '-') }}</span>
                                                </div>
                                                <b>PN {{ data_get($person, 'pn', '-') }}</b>
                                            </li>
                                        @endforeach
                                    </ol>
                                </details>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="micro-kupedes-pending__empty">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        <strong>Seluruh Mantri telah realisasi Kupedes.</strong>
                    </div>
                @endif
                </div>
            </div>
        </section>

        <section class="micro-ops-section micro-ops-section--ranking" aria-labelledby="micro-mbm-ranking-title" data-micro-ranking>
            <div class="micro-ops-section__head micro-ranking-head">
                <div>
                    <span class="micro-ops-section__number">02</span>
                    <span class="micro-ops-eyebrow">RANKING PEMUTUS</span>
                    <h3 id="micro-mbm-ranking-title">Kinerja MBM</h3>
                </div>
                <div class="micro-ranking-toggle" role="group" aria-label="Pilih dasar ranking MBM">
                    @foreach((array) data_get($decisionRanking, 'metrics', []) as $rankingMetric)
                        @php $isActiveRankingMetric = data_get($rankingMetric, 'key') === data_get($decisionRanking, 'default_metric', 'plafond'); @endphp
                        <button type="button"
                                class="{{ $isActiveRankingMetric ? 'active' : '' }}"
                                data-micro-ranking-metric="{{ data_get($rankingMetric, 'key') }}"
                                aria-pressed="{{ $isActiveRankingMetric ? 'true' : 'false' }}">
                            {{ data_get($rankingMetric, 'label', '-') }}
                        </button>
                    @endforeach
                </div>
            </div>

            @if(!empty($decisionRanking['available']))
                @foreach((array) data_get($decisionRanking, 'metrics', []) as $rankingMetric)
                    @php $isActiveRankingPanel = data_get($rankingMetric, 'key') === data_get($decisionRanking, 'default_metric', 'plafond'); @endphp
                    <div class="micro-ranking-panel"
                         data-micro-ranking-panel="{{ data_get($rankingMetric, 'key') }}"
                         {{ !$isActiveRankingPanel ? 'hidden' : '' }}>
                        <div class="micro-ranking-intro">
                            <span class="micro-ranking-intro__icon"><i class="fas fa-user-tie" aria-hidden="true"></i></span>
                            <div>
                                <strong>MBM berdasarkan {{ data_get($rankingMetric, 'label', '-') }}</strong>
                                <small>Akumulasi bulan berjalan sampai {{ data_get($meta, 'period_label', '-') }}.</small>
                            </div>
                            <div class="micro-ranking-podium" aria-hidden="true"><i></i><i></i><i></i></div>
                        </div>
                        <div class="micro-ranking-lists">
                            @foreach(['top' => ['3 Teratas', 'fa-chart-line'], 'bottom' => ['3 Terbawah', 'fa-level-down-alt']] as $rankingType => $rankingMeta)
                                <div class="micro-ranking-column is-{{ $rankingType }}">
                                    <header>
                                        <span><i class="fas {{ $rankingMeta[1] }}" aria-hidden="true"></i></span>
                                        <div><strong>{{ $rankingMeta[0] }}</strong><small>{{ data_get($rankingMetric, 'label', '-') }}</small></div>
                                    </header>
                                    <ol>
                                        @forelse((array) data_get($rankingMetric, $rankingType, []) as $rankedMbm)
                                            <li>
                                                <span class="micro-ranking-position">{{ $loop->iteration }}</span>
                                                <span class="micro-ranking-avatar"><i class="fas fa-user-tie" aria-hidden="true"></i></span>
                                                <div class="micro-ranking-person">
                                                    <strong>{{ data_get($rankedMbm, 'name', '-') }}</strong>
                                                    <small>PN {{ data_get($rankedMbm, 'pn', '-') }} &middot; {{ data_get($rankedMbm, 'branch', '-') }} &middot; {{ $formatInteger(data_get($rankedMbm, 'deb', 0)) }} rekening</small>
                                                </div>
                                                <b>{{ $formatAmount(data_get($rankedMbm, 'amount', 0)) }}</b>
                                            </li>
                                        @empty
                                            <li class="empty">Belum ada transaksi MBM.</li>
                                        @endforelse
                                    </ol>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @else
                <div class="micro-ops-empty micro-ops-empty--compact" role="status"><strong>Data pemutus MBM belum tersedia</strong></div>
            @endif
        </section>

        <section class="micro-ops-section micro-ops-section--decision" aria-labelledby="micro-pdwk-title">
            <div class="micro-ops-section__head">
                <div>
                    <span class="micro-ops-section__number">03</span>
                    <span class="micro-ops-eyebrow">PDWK</span>
                    <h3 id="micro-pdwk-title">Realisasi per Pemutus PDWK</h3>
                </div>
            </div>
            @if(!empty($pdwkLimits['available']))
                <div class="micro-pdwk-limit" data-micro-pdwk-limit>
                    <script type="application/json" data-micro-pdwk-details>@json(data_get($pdwkLimits, 'roles', []))</script>
                    <div class="micro-pdwk-limit__toolbar">
                        <div>
                            <strong>Status Limit Pemutus Bertransaksi</strong>
                            <small>Nama dan limit hanya mengikuti workbook per 31 Juli 2026; putusan dan plafon berasal dari realisasi Mikro bulan berjalan.</small>
                        </div>
                        <div class="micro-pdwk-role-toggle" role="group" aria-label="Pilih jabatan pemutus PDWK">
                            @foreach((array) data_get($pdwkLimits, 'roles', []) as $role)
                                @php $isActiveRole = data_get($role, 'key') === data_get($pdwkLimits, 'default_role', 'mbm'); @endphp
                                <button type="button"
                                        class="{{ $isActiveRole ? 'is-active' : '' }}"
                                        data-micro-pdwk-role="{{ data_get($role, 'key') }}"
                                        aria-pressed="{{ $isActiveRole ? 'true' : 'false' }}">
                                    {{ data_get($role, 'label', '-') }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @foreach((array) data_get($pdwkLimits, 'roles', []) as $role)
                        @php $isActiveRolePanel = data_get($role, 'key') === data_get($pdwkLimits, 'default_role', 'mbm'); @endphp
                        <div class="micro-pdwk-status-grid"
                             data-micro-pdwk-panel="{{ data_get($role, 'key') }}"
                             {{ !$isActiveRolePanel ? 'hidden' : '' }}>
                            @foreach((array) data_get($role, 'statuses', []) as $status)
                                @php $share = max(0, min(100, (float) data_get($status, 'pemutus_share', 0))); @endphp
                                <article class="micro-pdwk-status tone-{{ data_get($status, 'tone', 'green') }}"
                                         data-micro-pdwk-status
                                         data-micro-pdwk-role-key="{{ data_get($role, 'key') }}"
                                         data-micro-pdwk-status-key="{{ data_get($status, 'key') }}"
                                         style="--pdwk-share: {{ $share }}%">
                                    <header>
                                        <strong>{{ data_get($status, 'label', '-') }}</strong>
                                        <small>{{ data_get($status, 'limit', 0) > 0 ? 'Limit putusan '.data_get($status, 'limit').'% dari PDWK' : 'Tidak diberikan limit putusan' }}</small>
                                        <button type="button"
                                                class="micro-pdwk-status__detail"
                                                data-micro-pdwk-open-detail
                                                aria-label="Lihat nama {{ data_get($role, 'label', 'pemutus') }} pada status {{ data_get($status, 'label', '-') }}"
                                                title="Lihat nama pemutus">
                                            <i class="fas fa-users" aria-hidden="true"></i>
                                        </button>
                                    </header>
                                    <div class="micro-pdwk-status__body">
                                        <div class="micro-pdwk-donut" aria-label="{{ $formatPercent($share) }} dari pemutus aktif">
                                            <div><strong>{{ $formatInteger(data_get($status, 'pemutus', 0)) }}</strong><span>Pemutus</span></div>
                                        </div>
                                        <div class="micro-pdwk-status__metrics">
                                            <div><span>Jumlah Putusan</span><strong>{{ $formatInteger(data_get($status, 'putus_deb', 0)) }}</strong><small>{{ $formatPercent(data_get($status, 'putus_share', 0)) }}</small></div>
                                            <div><span>Plafon</span><strong>{{ $formatAmount(data_get($status, 'amount', 0)) }}</strong><small>{{ $formatPercent(data_get($status, 'amount_share', 0)) }}</small></div>
                                        </div>
                                    </div>
                                    <span class="micro-pdwk-status__hint"><i class="fas fa-mouse-pointer" aria-hidden="true"></i> 2x klik untuk nama pemutus</span>
                                </article>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="micro-decision-grid">
                @foreach((array) data_get($realization, 'decisions', []) as $decision)
                    @php $hasBreakdown = in_array(data_get($decision, 'key'), ['boh', 'mbm'], true); @endphp
                    <article class="micro-decision-card role-{{ data_get($decision, 'key') }}">
                        <div class="micro-decision-card__head">
                            <div><span>Pemutus</span><h4>{{ data_get($decision, 'label', '-') }}</h4></div>
                            <div>
                                <strong>{{ $formatAmount(data_get($decision, 'amount', 0)) }}</strong>
                                <span>{{ $formatInteger(data_get($decision, 'deb', 0)) }} rekening &middot; {{ $formatPercent(data_get($decision, 'share', 0)) }}</span>
                            </div>
                        </div>
                        <div class="micro-ops-table-wrap">
                            <table class="micro-ops-table micro-ops-table--decision">
                                <caption class="sr-only">Realisasi pemutus {{ data_get($decision, 'label', '-') }} per cabang</caption>
                                <thead>
                                    <tr>
                                        <th>Cabang</th>
                                        <th class="num">Rekening</th>
                                        <th class="num">Plafon</th>
                                        <th class="num">% Realisasi Cabang</th>
                                        @if($hasBreakdown)
                                            <th class="num">{{ data_get($decision, 'override_label') }}</th>
                                            <th class="num">{{ data_get($decision, 'primary_label') }}</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach((array) data_get($decision, 'branches', []) as $branch)
                                        <tr>
                                            <th scope="row">{{ data_get($branch, 'branch', '-') }}</th>
                                            <td class="num">{{ $formatInteger(data_get($branch, 'deb', 0)) }}</td>
                                            <td class="num strong">{{ $formatAmount(data_get($branch, 'amount', 0)) }}</td>
                                            <td class="num">{{ $formatPercent(data_get($branch, 'share', 0)) }}</td>
                                            @if($hasBreakdown)
                                                <td class="num compact override">{{ $formatInteger(data_get($branch, 'override_deb', 0)) }} / {{ $formatAmount(data_get($branch, 'override_amount', 0)) }}</td>
                                                <td class="num compact">{{ $formatInteger(data_get($branch, 'primary_deb', 0)) }} / {{ $formatAmount(data_get($branch, 'primary_amount', 0)) }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="micro-ops-section micro-ops-section--need" aria-labelledby="micro-realization-need-title">
            <div class="micro-ops-section__head">
                <div>
                    <span class="micro-ops-section__number">04</span>
                    <span class="micro-ops-eyebrow">KEBUTUHAN REALISASI</span>
                    <h3 id="micro-realization-need-title">Target Kerja per Mantri</h3>
                </div>
                <div class="micro-need-filter" role="group" aria-label="Filter target kerja berdasarkan produk">
                    @foreach($realizationNeedOptions as $needOption)
                        <button type="button"
                                class="{{ data_get($needOption, 'key') === 'all' ? 'is-active' : '' }}"
                                data-micro-need-filter="{{ data_get($needOption, 'key') }}"
                                aria-pressed="{{ data_get($needOption, 'key') === 'all' ? 'true' : 'false' }}">
                            {{ data_get($needOption, 'label', '-') }}
                        </button>
                    @endforeach
                </div>
            </div>

            @foreach($realizationNeedOptions as $needOption)
                @php
                    $needKey = (string) data_get($needOption, 'key', 'all');
                @endphp
                <div data-micro-need-panel="{{ $needKey }}" @if($needKey !== 'all') hidden @endif>
                    @if(!empty($needOption['available']))
                        <div class="micro-need-stage">
                            <div class="micro-need-stage__copy">
                                <span class="micro-need-stage__icon"><i class="fas fa-bullseye" aria-hidden="true"></i></span>
                                <div>
                                    <span>Kebutuhan per Mantri · {{ data_get($needOption, 'label', 'Semua Produk') }}</span>
                                    <strong>{{ $formatAmount(data_get($needOption, 'need_per_mantri', 0)) }}</strong>
                                    <small>Gap RKA + sisa Run Off dibagi {{ $formatInteger(data_get($needOption, 'total_mantri', 0)) }} Mantri.</small>
                                </div>
                            </div>
                            <div class="micro-need-stage__daily">
                                <span>Target per Mantri / HKE</span>
                                <strong>{{ $formatAmount(data_get($needOption, 'need_per_mantri_per_hke', 0)) }}</strong>
                                <small>{{ $formatInteger(data_get($realizationNeed, 'remaining_working_days', 0)) }} HKE tersisa dari {{ $formatInteger(data_get($realizationNeed, 'total_working_days', 0)) }} HKE bulan berjalan</small>
                            </div>
                            <div class="micro-need-visual" aria-hidden="true">
                                <span><i class="fas fa-user-tie"></i></span>
                                <i></i><i></i><i></i>
                            </div>
                        </div>
                        <div class="micro-need-equation" aria-label="Komponen perhitungan kebutuhan realisasi {{ data_get($needOption, 'label', 'semua produk') }}">
                            <div class="tone-gap">
                                <span>Gap RKA {{ data_get($realizationNeed, 'rka_period_label', '-') }}</span>
                                <strong>{{ $formatAmount(data_get($needOption, 'rka_gap', 0)) }}</strong>
                                <small>RKA {{ $formatAmount(data_get($needOption, 'rka', 0)) }} dikurangi OS {{ $formatAmount(data_get($needOption, 'current_os', 0)) }}</small>
                            </div>
                            <span class="micro-need-operator" aria-hidden="true">+</span>
                            <div class="tone-runoff">
                                <span>Sisa Run Off</span>
                                <strong>{{ $formatAmount(data_get($needOption, 'run_off', 0)) }}</strong>
                                <small>Posisi {{ data_get($realizationNeed, 'run_off_period_label', '-') }}</small>
                            </div>
                            <span class="micro-need-operator" aria-hidden="true">=</span>
                            <div class="tone-total">
                                <span>Total Kebutuhan</span>
                                <strong>{{ $formatAmount(data_get($needOption, 'total_need', 0)) }}</strong>
                                <small>Basis pembagian target sales force</small>
                            </div>
                        </div>
                    @else
                        <div class="micro-ops-empty micro-ops-empty--compact" role="status">
                            <strong>Kebutuhan {{ data_get($needOption, 'label', 'produk') }} belum dapat dihitung</strong>
                            <small>{{ data_get($realizationNeed, 'error', 'Sumber RKA, Run Off, atau roster Mantri belum lengkap.') }}</small>
                        </div>
                    @endif
                </div>
            @endforeach
        </section>

        <section class="micro-ops-section micro-ops-section--pattern" aria-labelledby="micro-pattern-title">
            <div class="micro-ops-section__head">
                <div>
                    <span class="micro-ops-section__number">05</span>
                    <span class="micro-ops-eyebrow">POLA ANGSURAN</span>
                    <h3 id="micro-pattern-title">Pola Angsuran</h3>
                </div>
            </div>
            <div class="micro-pattern-grid">
                @foreach((array) data_get($realization, 'patterns', []) as $pattern)
                    @php
                        $isMusimanPattern = data_get($pattern, 'key') === 'musiman';
                        $patternDetails = (array) data_get($pattern, 'details', []);
                    @endphp
                    <article class="micro-pattern-card pattern-{{ data_get($pattern, 'key') }} {{ $isMusimanPattern && !empty($patternDetails) ? 'has-breakdown' : '' }}">
                        <div class="micro-pattern-card__visual"><i class="fas {{ data_get($pattern, 'key') === 'musiman' ? 'fa-leaf' : 'fa-calendar-check' }}" aria-hidden="true"></i></div>
                        <div class="micro-pattern-card__copy">
                            <span>{{ data_get($pattern, 'label', '-') }}</span>
                            <strong>{{ $formatAmount(data_get($pattern, 'amount', 0)) }}</strong>
                            <small>{{ $formatInteger(data_get($pattern, 'deb', 0)) }} rekening</small>
                        </div>
                        <b>{{ $formatPercent(data_get($pattern, 'share', 0)) }}</b>
                        @if($isMusimanPattern && !empty($patternDetails))
                            <div class="micro-pattern-card__breakdown" aria-label="Rincian realisasi musiman">
                                @foreach($patternDetails as $detail)
                                    @continue((int) data_get($detail, 'deb', 0) <= 0)
                                    <div class="micro-pattern-card__detail {{ data_get($detail, 'key') === 'periodik' ? 'is-periodic' : '' }} {{ data_get($detail, 'key') === 'satu_kali' ? 'is-one-time' : '' }}">
                                        <span>{{ data_get($detail, 'label', '-') }}</span>
                                        <strong>{{ $formatAmount(data_get($detail, 'amount', 0)) }}</strong>
                                        <small>{{ $formatInteger(data_get($detail, 'deb', 0)) }} rekening</small>
                                        @if(data_get($detail, 'key') === 'periodik' && !empty($detail['frequency_details']))
                                            <div class="micro-frequency-grid" aria-label="Rincian periodik berdasarkan frekuensi pembayaran">
                                                @foreach((array) data_get($detail, 'frequency_details', []) as $frequency)
                                                    @continue((int) data_get($frequency, 'customers', 0) <= 0)
                                                    @php $hasTerms = !empty($frequency['term_details']); @endphp
                                                    <div class="micro-frequency-item {{ $hasTerms ? 'has-terms' : '' }}">
                                                        @if($hasTerms)
                                                            <button type="button" class="micro-freq-header" data-freq-toggle aria-expanded="false" aria-label="Buka rincian jangka waktu {{ data_get($frequency, 'label', '') }}">
                                                                <span class="micro-freq-main">
                                                                    <b>{{ data_get($frequency, 'label', '-') }}</b>
                                                                    <span>{{ $formatInteger(data_get($frequency, 'customers', 0)) }} nasabah</span>
                                                                    <strong>{{ $formatAmount(data_get($frequency, 'os', 0)) }} OS</strong>
                                                                </span>
                                                                <i class="fas fa-chevron-down micro-freq-chevron" aria-hidden="true"></i>
                                                            </button>
                                                            <div class="micro-freq-term-drawer" hidden>
                                                                @foreach((array) $frequency['term_details'] as $freqTerm)
                                                                    @continue((int) data_get($freqTerm, 'customers', 0) <= 0)
                                                                    <div class="micro-freq-term-item">
                                                                        <b>{{ data_get($freqTerm, 'label', '-') }}</b>
                                                                        <span>{{ $formatInteger(data_get($freqTerm, 'customers', 0)) }} nsb &middot; {{ $formatInteger(data_get($freqTerm, 'deb', 0)) }} rek</span>
                                                                        <strong>{{ $formatAmount(data_get($freqTerm, 'os', 0)) }} OS</strong>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @else
                                                            <b>{{ data_get($frequency, 'label', '-') }}</b>
                                                            <span>{{ $formatInteger(data_get($frequency, 'customers', 0)) }} nasabah</span>
                                                            <strong>{{ $formatAmount(data_get($frequency, 'os', 0)) }} OS</strong>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if(data_get($detail, 'key') === 'satu_kali' && !empty($detail['term_details']))
                                            <div class="micro-term-grid" aria-label="Rincian 1x angsuran berdasarkan jangka waktu">
                                                @foreach((array) data_get($detail, 'term_details', []) as $term)
                                                    @continue((int) data_get($term, 'customers', 0) <= 0)
                                                    <article class="micro-term-item">
                                                        <div>
                                                            <b>{{ data_get($term, 'label', '-') }}</b>
                                                            <span>{{ $formatInteger(data_get($term, 'customers', 0)) }} nasabah · {{ $formatInteger(data_get($term, 'deb', 0)) }} rekening</span>
                                                            <strong>{{ $formatAmount(data_get($term, 'os', 0)) }} OS</strong>
                                                        </div>
                                                    </article>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="micro-ops-section micro-ops-section--mantri" aria-labelledby="micro-mantri-title">
            <div class="micro-mantri-stage">
                <div class="micro-mantri-stage__copy">
                    <span class="micro-ops-section__number">06</span>
                    <span class="micro-ops-eyebrow">SALES FORCE</span>
                    <h3 id="micro-mantri-title">Produktivitas Mantri</h3>
                    <div class="micro-mantri-stage__chips">
                        <span><i class="fas fa-user-tie" aria-hidden="true"></i>{{ $formatInteger(data_get($mantriPerformance, 'total.tiers.pt.total', 0) + data_get($mantriPerformance, 'total.tiers.contract.total', 0) + data_get($mantriPerformance, 'total.headcount.briguna', 0)) }} Mantri</span>
                        <span title="Rekening tanpa pengelola Mantri aktif tidak masuk tabel produktivitas"><i class="fas fa-shield-alt" aria-hidden="true"></i>Roster aktif BRIHC</span>
                        <span><i class="fas fa-business-time" aria-hidden="true"></i>{{ $formatInteger(data_get($mantriPerformance, 'working_days', 0)) }} HKE</span>
                        <span><i class="fas fa-calendar-day" aria-hidden="true"></i>Harian {{ data_get($mantriPerformance, 'daily_period_label', '-') }}</span>
                        <span><i class="fas fa-layer-group" aria-hidden="true"></i>Akumulatif {{ data_get($mantriPerformance, 'accumulation_label', '-') }}</span>
                    </div>
                </div>
                <div class="micro-mantri-stage__visual" aria-hidden="true">
                    <svg viewBox="0 0 250 170" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                        <path d="M13 143C49 125 74 132 102 103C131 73 162 101 187 66C202 45 220 40 239 28" stroke="#65D7FF" stroke-width="7" stroke-linecap="round"/>
                        <path d="M211 28H239V56" stroke="#FFF" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="91" cy="55" r="23" fill="#BEEFFF"/>
                        <path d="M63 125C63 93 74 76 91 76C109 76 120 93 120 125" fill="#FFF"/>
                        <path d="M78 78L91 96L104 78" fill="#0D79D5"/>
                        <path d="M89 95H94L99 124H84L89 95Z" fill="#052D68"/>
                        <rect x="119" y="82" width="74" height="51" rx="8" fill="#073E8E" stroke="#8EE7FF" stroke-width="3"/>
                        <path d="M135 117L148 103L157 109L177 91" stroke="#7BE3FF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="177" cy="91" r="4" fill="#FFF"/>
                    </svg>
                </div>
            </div>

            @if(!empty($mantriPerformance['available']))
                <div class="micro-mantri-table-wrap micro-mantri-table-wrap--summary">
                    <table class="micro-mantri-table micro-mantri-table--summary">
                        <caption class="sr-only">Ringkasan produktivitas Mantri per Branch Office</caption>
                        <thead>
                            <tr>
                                <th rowspan="2">No.</th>
                                <th rowspan="2">Kode BO</th>
                                <th rowspan="2">Branch Office</th>
                                <th rowspan="2">Mantri PT<br>Non Briguna</th>
                                <th rowspan="2">Mantri<br>Kontrak</th>
                                <th rowspan="2">Mantri<br>Briguna</th>
                                <th colspan="2" class="scope-daily">Realisasi Harian {{ data_get($mantriPerformance, 'daily_period_label', '-') }}</th>
                                <th colspan="4" class="scope-cumulative">Akumulatif s.d. {{ data_get($meta, 'period_label', '-') }}</th>
                            </tr>
                            <tr>
                                <th class="scope-daily">Plafon</th><th class="scope-daily">Nett Disb.</th>
                                <th class="scope-cumulative">Plafon</th><th class="scope-cumulative">Ratas / HKE</th>
                                <th class="scope-cumulative">Nett Disb.</th><th class="scope-cumulative">Ratas / HKE</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach((array) data_get($mantriPerformance, 'rows', []) as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ data_get($row, 'branch_code', '-') }}</td>
                                    <th scope="row">{{ data_get($row, 'branch', '-') }}</th>
                                    <td>{{ $formatInteger(data_get($row, 'headcount.pt_non_briguna', 0)) }}</td>
                                    <td>{{ $formatInteger(data_get($row, 'headcount.contract', 0)) }}</td>
                                    <td>{{ $formatInteger(data_get($row, 'headcount.briguna', 0)) }}</td>
                                    <td class="strong micro-cell-daily" title="{{ $formatInteger(data_get($row, 'daily_realization.deb', 0)) }} rekening">{{ $formatJuta(data_get($row, 'daily_realization.amount', 0)) }}</td>
                                    <td class="strong micro-cell-daily" title="{{ $formatInteger(data_get($row, 'daily_net_disbursement.deb', 0)) }} rekening">{{ $formatJuta(data_get($row, 'daily_net_disbursement.amount', 0)) }}</td>
                                    <td class="strong">{{ $formatJuta(data_get($row, 'realization.amount', 0)) }}</td>
                                    <td>{{ $formatJuta(data_get($row, 'realization.average_per_hke', 0)) }}</td>
                                    <td class="strong">{{ $formatJuta(data_get($row, 'net_disbursement.amount', 0)) }}</td>
                                    <td>{{ $formatJuta(data_get($row, 'net_disbursement.average_per_hke', 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" scope="row">Total {{ data_get($mantriPerformance, 'total.branch', 'Area 6') }}</th>
                                <td>{{ $formatInteger(data_get($mantriPerformance, 'total.headcount.pt_non_briguna', 0)) }}</td>
                                <td>{{ $formatInteger(data_get($mantriPerformance, 'total.headcount.contract', 0)) }}</td>
                                <td>{{ $formatInteger(data_get($mantriPerformance, 'total.headcount.briguna', 0)) }}</td>
                                <td>{{ $formatJuta(data_get($mantriPerformance, 'total.daily_realization.amount', 0)) }}</td>
                                <td>{{ $formatJuta(data_get($mantriPerformance, 'total.daily_net_disbursement.amount', 0)) }}</td>
                                <td>{{ $formatJuta(data_get($mantriPerformance, 'total.realization.amount', 0)) }}</td>
                                <td>{{ $formatJuta(data_get($mantriPerformance, 'total.realization.average_per_hke', 0)) }}</td>
                                <td>{{ $formatJuta(data_get($mantriPerformance, 'total.net_disbursement.amount', 0)) }}</td>
                                <td>{{ $formatJuta(data_get($mantriPerformance, 'total.net_disbursement.average_per_hke', 0)) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="micro-tier-panels">
                    <script type="application/json" data-micro-mantri-tier-details>{!! json_encode(data_get($mantriPerformance, 'rows', []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
                    @foreach(['pt' => 'Nett Disbursement Mantri PT Only', 'contract' => 'Nett Disbursement Mantri Kontrak'] as $tierKey => $tierTitle)
                        @php $tierBlueprint = (array) data_get($mantriPerformance, 'total.tiers.'.$tierKey.'.buckets', []); @endphp
                        <article class="micro-tier-panel micro-tier-panel--{{ $tierKey }}">
                            <header>
                                <i class="fas {{ $tierKey === 'pt' ? 'fa-user-check' : 'fa-id-badge' }}" aria-hidden="true"></i>
                                <div class="micro-tier-panel__heading">
                                    <h4>{{ $tierTitle }}</h4>
                                </div>
                            </header>
                            <div class="micro-mantri-table-wrap">
                                <table class="micro-mantri-table micro-mantri-table--tiers">
                                    <caption class="sr-only">{{ $tierTitle }} per Branch Office</caption>
                                    <thead>
                                        <tr>
                                            <th rowspan="2">Branch Office</th>
                                            @foreach($tierBlueprint as $bucket)
                                                <th colspan="2" class="tone-{{ data_get($bucket, 'key') }}">{{ data_get($bucket, 'label', '-') }}</th>
                                            @endforeach
                                        </tr>
                                        <tr>
                                            @foreach($tierBlueprint as $bucket)
                                                <th class="tone-{{ data_get($bucket, 'key') }}">Mantri</th>
                                                <th class="tone-{{ data_get($bucket, 'key') }}">%</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach((array) data_get($mantriPerformance, 'rows', []) as $row)
                                            <tr data-micro-mantri-tier-row data-micro-mantri-tier="{{ $tierKey }}" data-micro-mantri-branch-code="{{ data_get($row, 'branch_code', '-') }}">
                                                <th scope="row">{{ data_get($row, 'branch', '-') }}</th>
                                                @foreach((array) data_get($row, 'tiers.'.$tierKey.'.buckets', []) as $bucket)
                                                    <td class="tone-{{ data_get($bucket, 'key') }}" data-micro-mantri-tier-count data-micro-mantri-bucket="{{ data_get($bucket, 'key') }}" role="button" tabindex="0" aria-label="Lihat nominatif {{ data_get($bucket, 'label', 'kategori') }} {{ $tierTitle }} {{ data_get($row, 'branch', '-') }}" title="Klik dua kali untuk melihat nominatif">{{ $formatInteger(data_get($bucket, 'mantri', 0)) }}</td>
                                                    <td class="tone-{{ data_get($bucket, 'key') }}">{{ $formatPercent(data_get($bucket, 'share', 0)) }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th scope="row">Total {{ data_get($mantriPerformance, 'total.branch', 'Area 6') }}</th>
                                            @foreach($tierBlueprint as $bucket)
                                                <td>{{ $formatInteger(data_get($bucket, 'mantri', 0)) }}</td>
                                                <td>{{ $formatPercent(data_get($bucket, 'share', 0)) }}</td>
                                            @endforeach
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="micro-ops-empty micro-ops-empty--compact" role="status"><strong>Roster Mantri belum tersedia</strong></div>
            @endif

            <article class="micro-rm-kur-productivity" data-micro-rm-kur-productivity aria-labelledby="micro-rm-kur-title">
                <header class="micro-rm-kur-head">
                    <span class="micro-rm-kur-head__icon" aria-hidden="true"><i class="fas fa-user-tie"></i></span>
                    <div class="micro-rm-kur-head__copy">
                        <span class="micro-ops-eyebrow">RM KUR KECIL MIKRO</span>
                        <h4 id="micro-rm-kur-title">Produktivitas RM KUR Kecil Mikro</h4>
                        <p>Realisasi MTD produk KUR Ritel 2015 per RM. Nilai menggunakan snapshot Daily Loan dan identitas personel mengutamakan roster BRIHC.</p>
                    </div>
                    <div class="micro-rm-kur-head__meta" aria-label="Konteks data produktivitas RM KUR Kecil Mikro">
                        <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i>{{ data_get($rmKurProductivity, 'scope_label', data_get($meta, 'scope_label', 'Area 6')) }}</span>
                        <span><i class="far fa-calendar-alt" aria-hidden="true"></i>{{ data_get($rmKurProductivity, 'period_label', '-') }}</span>
                        @if(!empty($rmKurProductivity['refresh_pending']))
                            <span title="Snapshot periode yang diminta sedang diperbarui"><i class="fas fa-sync-alt" aria-hidden="true"></i>Snapshot diperbarui</span>
                        @endif
                    </div>
                </header>

                <div class="micro-rm-kur-summary" aria-label="Ringkasan produktivitas RM KUR Kecil Mikro">
                    <article>
                        <span>RM Produktif</span>
                        <strong>{{ $formatInteger(data_get($rmKurProductivity, 'total.rm_count', 0)) }}</strong>
                        <small>RM dengan realisasi MTD</small>
                    </article>
                    <article>
                        <span>Debitur Realisasi</span>
                        <strong>{{ $formatInteger(data_get($rmKurProductivity, 'total.realisasi_deb', 0)) }}</strong>
                        <small>Rekening baru bulan berjalan</small>
                    </article>
                    <article>
                        <span>Plafon Realisasi</span>
                        <strong>{{ $formatAmount(data_get($rmKurProductivity, 'total.realisasi_os', 0)) }}</strong>
                        <small>Akumulasi MTD</small>
                    </article>
                    <article>
                        <span>Rata-rata / RM</span>
                        <strong>{{ $formatAmount(data_get($rmKurProductivity, 'total.average_per_rm', 0)) }}</strong>
                        <small>Plafon produktif per RM</small>
                    </article>
                </div>

                @if(!empty($rmKurProductivity['available']))
                    <div class="micro-mantri-table-wrap micro-rm-kur-table-wrap">
                        <table class="micro-mantri-table micro-rm-kur-table">
                            <caption class="sr-only">Produktivitas realisasi MTD RM KUR Kecil Mikro</caption>
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Kode BO</th>
                                    <th>Branch Office</th>
                                    <th>RM KUR Kecil Mikro</th>
                                    <th>Unit Kerja</th>
                                    <th>Debitur</th>
                                    <th>Plafon (Rp Juta)</th>
                                    <th>Rata-rata / Debitur (Rp Juta)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach((array) data_get($rmKurProductivity, 'rows', []) as $row)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ data_get($row, 'branch_code', '-') }}</td>
                                        <td>{{ data_get($row, 'cabang', '-') }}</td>
                                        <th scope="row" class="micro-rm-kur-person">
                                            <strong>{{ data_get($row, 'nama', '-') }}</strong>
                                            <small>PN {{ data_get($row, 'pn', '-') }}</small>
                                        </th>
                                        <td>{{ data_get($row, 'unit', '-') }}</td>
                                        <td class="strong">{{ $formatInteger(data_get($row, 'realisasi_deb', 0)) }}</td>
                                        <td class="strong">{{ $formatJuta(data_get($row, 'realisasi_os', 0)) }}</td>
                                        <td>{{ $formatJuta(data_get($row, 'average_per_debtor', 0)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5" scope="row">Total {{ data_get($rmKurProductivity, 'scope_label', 'Area 6') }}</th>
                                    <td>{{ $formatInteger(data_get($rmKurProductivity, 'total.realisasi_deb', 0)) }}</td>
                                    <td>{{ $formatJuta(data_get($rmKurProductivity, 'total.realisasi_os', 0)) }}</td>
                                    <td>{{ $formatJuta(data_get($rmKurProductivity, 'total.average_per_debtor', 0)) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="micro-rm-kur-empty" role="status">
                        <i class="fas fa-user-clock" aria-hidden="true"></i>
                        <div>
                            <strong>Belum ada realisasi RM KUR Kecil Mikro</strong>
                            <span>Tidak ditemukan realisasi MTD KUR Ritel 2015 pada periode dan cabang yang dipilih.</span>
                        </div>
                    </div>
                @endif
            </article>
        </section>

        <section class="micro-ops-section micro-ops-section--burden" aria-labelledby="micro-unit-burden-title">
            <div class="micro-ops-section__head">
                <div>
                    <span class="micro-ops-section__number">07</span>
                    <span class="micro-ops-eyebrow">PRIORITAS</span>
                    <h3 id="micro-unit-burden-title">Uker Pemberat</h3>
                </div>
            </div>
            <div class="micro-burden-grid">
                @foreach(['os' => ['OS', 'MTD'], 'sml' => ['SML', 'MTD'], 'npl' => ['NPL', 'MTD']] as $metricKey => $metricMeta)
                    <article class="micro-burden-card metric-{{ $metricKey }}">
                        <div class="micro-burden-card__head"><span>{{ $metricMeta[0] }}</span><strong>5 Uker Pemberat</strong><small>Selisih {{ $metricMeta[1] }}</small></div>
                        <ol class="micro-burden-list">
                            @forelse((array) data_get($burden, 'units.'.$metricKey, []) as $item)
                                @php $delta = (float) data_get($item, $metricKey.'_delta', 0); @endphp
                                <li>
                                    <span class="micro-burden-list__rank">{{ $loop->iteration }}</span>
                                    <div><strong>{{ data_get($item, 'label', '-') }}</strong><small>{{ data_get($item, 'branch', '-') }}</small></div>
                                    <span class="micro-delta {{ $deltaClass($delta, $metricKey) }}">{{ $formatDelta($delta) }}</span>
                                </li>
                            @empty
                                <li class="empty">-</li>
                            @endforelse
                        </ol>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="micro-ops-section micro-ops-section--inactive" aria-labelledby="micro-inactive-title">
            <div class="micro-ops-section__head">
                <div>
                    <span class="micro-ops-section__number">08</span>
                    <span class="micro-ops-eyebrow">MONITORING SALES FORCE</span>
                    <h3 id="micro-inactive-title">Mantri Tidak Produktif</h3>
                </div>
                <div class="micro-inactive-head-meta">
                    <strong>Basis {{ $formatInteger(data_get($unproductiveMantri, 'total_mantri', 0)) }} Mantri aktif</strong>
                    <span>Closing {{ data_get($unproductiveMantri, 'period_label', '-') }}</span>
                </div>
            </div>

            @if(!empty($unproductiveMantri['available']))
                <div class="micro-inactive-metrics" aria-label="Ringkasan Mantri tidak produktif">
                    @foreach(['month_1' => 'clock', 'month_3' => 'calendar-alt', 'month_6' => 'exclamation-triangle'] as $metricKey => $metricIcon)
                        @php $inactiveMetric = (array) data_get($unproductiveMantri, 'totals.'.$metricKey, []); @endphp
                        <article class="micro-inactive-metric tone-{{ $metricKey }} {{ empty($inactiveMetric['enabled']) ? 'is-disabled' : '' }}">
                            <span class="micro-inactive-metric__icon"><i class="fas fa-{{ $metricIcon }}" aria-hidden="true"></i></span>
                            <div>
                                <span>Tidak produktif</span>
                                <strong>{{ data_get($inactiveMetric, 'label', '-') }}</strong>
                            </div>
                            <b>{{ !empty($inactiveMetric['enabled']) ? $formatInteger(data_get($inactiveMetric, 'count', 0)) : '-' }}</b>
                            <small>{{ !empty($inactiveMetric['enabled']) ? $formatPercent(data_get($inactiveMetric, 'percentage', 0)).' · '.$formatInteger(data_get($inactiveMetric, 'monitored', 0)).' terpantau' : 'Closing belum lengkap' }}</small>
                        </article>
                    @endforeach
                </div>

                <div class="micro-inactive-table-wrap">
                    <table class="micro-inactive-table">
                        <caption class="sr-only">Jumlah Mantri tidak produktif per Branch Office</caption>
                        <thead>
                            <tr>
                                <th>Branch Office</th>
                                <th>Roster Aktif</th>
                                <th>1 Bulan</th>
                                <th>3 Bulan</th>
                                <th>6 Bulan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach((array) data_get($unproductiveMantri, 'branches', []) as $branch)
                                <tr>
                                    <th scope="row">{{ data_get($branch, 'branch', '-') }}</th>
                                    <td>{{ $formatInteger(data_get($branch, 'total_mantri', 0)) }}</td>
                                    @foreach(['month_1', 'month_3', 'month_6'] as $metricKey)
                                        @php $branchMetric = (array) data_get($branch, 'metrics.'.$metricKey, []); @endphp
                                        <td>
                                            <strong>{{ !empty($branchMetric['enabled']) ? $formatInteger(data_get($branchMetric, 'count', 0)) : '-' }}</strong>
                                            <small>{{ !empty($branchMetric['enabled']) ? $formatPercent(data_get($branchMetric, 'percentage', 0)).' · basis '.$formatInteger(data_get($branchMetric, 'monitored', 0)) : '-' }}</small>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="micro-inactive-details">
                    @foreach(['month_1', 'month_3', 'month_6'] as $metricKey)
                        @php $inactiveMetric = (array) data_get($unproductiveMantri, 'totals.'.$metricKey, []); @endphp
                        <details class="micro-inactive-detail tone-{{ $metricKey }} {{ empty($inactiveMetric['enabled']) ? 'is-disabled' : '' }}">
                            <summary>
                                <span>{{ data_get($inactiveMetric, 'label', '-') }}</span>
                                <strong>{{ !empty($inactiveMetric['enabled']) ? $formatInteger(data_get($inactiveMetric, 'count', 0)).' Mantri' : 'Belum tersedia' }}</strong>
                                <i class="fas fa-chevron-down" aria-hidden="true"></i>
                            </summary>
                            <ol>
                                @forelse((array) data_get($inactiveMetric, 'mantri', []) as $person)
                                    <li>
                                        <span class="micro-inactive-avatar"><i class="fas fa-user-tie" aria-hidden="true"></i></span>
                                        <div>
                                            <strong>{{ data_get($person, 'name') ?: 'PN '.data_get($person, 'pn', '-') }}</strong>
                                            <small>{{ data_get($person, 'branch', '-') }} &middot; {{ data_get($person, 'unit', '-') }}</small>
                                        </div>
                                        <span class="micro-inactive-category">{{ data_get($person, 'category') === 'contract' ? 'Kontrak' : (data_get($person, 'category') === 'briguna' ? 'Briguna' : 'PT') }}</span>
                                    </li>
                                @empty
                                    <li class="is-empty">Tidak ada Mantri pada kategori ini.</li>
                                @endforelse
                            </ol>
                        </details>
                    @endforeach
                </div>
            @else
                <div class="micro-ops-empty micro-ops-empty--compact" role="status">
                    <strong>Data closing atau roster Mantri belum lengkap</strong>
                </div>
            @endif
        </section>

        <section class="micro-ops-section micro-ops-section--pipeline" aria-labelledby="micro-pipeline-title">
            <div class="micro-ops-section__head">
                <div>
                    <span class="micro-ops-section__number">09</span>
                    <span class="micro-ops-eyebrow">PIPELINE MIKRO</span>
                    <h3 id="micro-pipeline-title">Prewash &amp; SLIK Hijau &middot; {{ data_get($pipeline, 'scope_label', 'Area 6') }}</h3>
                </div>
                <div class="micro-pipeline-head-actions">
                    @if(data_get($pipeline, 'sync.synced_at'))
                        <span class="micro-pipeline-sync"><i class="fas fa-sync-alt" aria-hidden="true"></i>Diperbarui {{ \Carbon\Carbon::parse(data_get($pipeline, 'sync.synced_at'))->translatedFormat('d M Y H:i') }}</span>
                    @endif
                    @if(!empty($pipeline['available']))
                        <button type="button" class="micro-pipeline-open" data-micro-pipeline-open="prewash">
                            <i class="fas fa-table" aria-hidden="true"></i><span>Lihat Nominatif</span>
                        </button>
                    @endif
                </div>
            </div>

            @if(!empty($pipeline['available']))
                <div class="micro-pipeline-dataset-title"><span>PREWASH</span><strong>Nominatif &amp; progres kunjungan</strong></div>
                <div class="micro-pipeline-kpis" aria-label="Ringkasan Pipeline Mikro Prewash">
                    <article class="tone-total"><span>Total Pipeline</span><strong>{{ $formatInteger(data_get($pipelineSummary, 'total', 0)) }}</strong><small>{{ $formatAmount(data_get($pipelineSummary, 'potential_plafond', 0)) }} potensi plafon</small></article>
                    <article class="tone-done"><span>Sudah Dikunjungi</span><strong>{{ $formatInteger(data_get($pipelineSummary, 'done', 0)) }}</strong><small>{{ $formatPercent(data_get($pipelineSummary, 'visit_rate', 0)) }} dari pipeline</small></article>
                    <article class="tone-plan"><span>Terjadwal</span><strong>{{ $formatInteger(data_get($pipelineSummary, 'scheduled', 0)) }}</strong><small>Rencana kunjungan</small></article>
                    <article class="tone-pending"><span>Belum Dikunjungi</span><strong>{{ $formatInteger(data_get($pipelineSummary, 'pending', 0)) }}</strong><small>Pipeline terbuka</small></article>
                    <article class="tone-real"><span>Plafon Real</span><strong>{{ $formatAmount(data_get($pipelineSummary, 'realized_plafond', 0)) }}</strong><small>Realisasi pada sumber pipeline</small></article>
                </div>

                @if(!empty($slikHijau['available']))
                    @php $slikMax = max(1, (float) collect((array) data_get($slikHijau, 'groups', []))->max('potential_plafond')); @endphp
                    <article class="micro-slik-card" aria-label="Ringkasan Pipeline Mikro SLIK Hijau">
                        <div class="micro-slik-card__identity">
                            <span class="micro-slik-card__icon"><i class="fas fa-leaf" aria-hidden="true"></i></span>
                            <div><span>SLIK HIJAU</span><h4>Nasabah Berminat</h4></div>
                        </div>
                        <div class="micro-slik-card__metrics">
                            <div><span>Nominatif</span><strong>{{ $formatInteger(data_get($slikSummary, 'total', 0)) }}</strong></div>
                            <div><span>Potensi</span><strong>{{ $formatAmount(data_get($slikSummary, 'potential_plafond', 0)) }}</strong></div>
                            <div><span>Mantri</span><strong>{{ $formatInteger(data_get($slikSummary, 'mantri', 0)) }}</strong></div>
                            <div><span>Unit</span><strong>{{ $formatInteger(data_get($slikSummary, 'units', 0)) }}</strong></div>
                        </div>
                        <div class="micro-slik-card__branches">
                            @foreach((array) data_get($slikHijau, 'groups', []) as $group)
                                @php $slikWidth = min(100, ((float) data_get($group, 'potential_plafond', 0) / $slikMax) * 100); @endphp
                                <div class="micro-slik-branch">
                                    <span>{{ data_get($group, 'label', '-') }}</span>
                                    <div><i style="width: {{ $slikWidth }}%"></i></div>
                                    <strong>{{ $formatInteger(data_get($group, 'total', 0)) }}</strong>
                                    <small>{{ $formatAmount(data_get($group, 'potential_plafond', 0)) }}</small>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="micro-slik-card__open" data-micro-pipeline-open="slik_hijau"><i class="fas fa-arrow-right" aria-hidden="true"></i><span>Nominatif</span></button>
                    </article>
                @endif

                <div class="micro-pipeline-overview">
                    <article class="micro-pipeline-panel">
                        <div class="micro-pipeline-panel__head">
                            <div><span>CAKUPAN WILAYAH</span><h4>Progress per {{ data_get($pipeline, 'group_label', 'Cabang') }}</h4></div>
                            <small>Prewash</small>
                        </div>
                        <div class="micro-pipeline-group-list">
                            @foreach((array) data_get($pipeline, 'groups', []) as $group)
                                @php
                                    $grpTotal = max(1, (int) data_get($group, 'total', 0));
                                    $grpDoneRate = ((int) data_get($group, 'done', 0) / $grpTotal) * 100;
                                    $grpPlanRate = ((int) data_get($group, 'scheduled', 0) / $grpTotal) * 100;
                                    $grpPending = (int) data_get($group, 'pending', 0);
                                @endphp
                                <div class="micro-pipeline-group-row">
                                    <div class="micro-pipeline-group-row__identity">
                                        <strong>{{ data_get($group, 'label', '-') }}</strong>
                                        <small>{{ $formatInteger(data_get($group, 'total', 0)) }} pipeline &middot; {{ $formatAmount(data_get($group, 'potential_plafond', 0)) }}</small>
                                    </div>
                                    <div class="micro-pipeline-segmented-bar" title="{{ $formatInteger(data_get($group, 'done', 0)) }} selesai, {{ $formatInteger(data_get($group, 'scheduled', 0)) }} terjadwal, {{ $formatInteger($grpPending) }} belum">
                                        <span class="is-done" style="width: {{ $grpDoneRate }}%"></span>
                                        <span class="is-plan" style="width: {{ $grpPlanRate }}%"></span>
                                    </div>
                                    <div class="micro-pipeline-group-row__status">
                                        <b>{{ $formatPercent(data_get($group, 'visit_rate', 0)) }}</b>
                                        <span>{{ $formatInteger(data_get($group, 'done', 0)) }} selesai &middot; {{ $formatInteger($grpPending) }} terbuka</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>

                    <article class="micro-pipeline-panel micro-pipeline-panel--sources">
                        <div class="micro-pipeline-panel__head">
                            <div><span>SUMBER PIPELINE</span><h4>Rincian &amp; Kontributor Terbesar</h4></div>
                            <small>Buka ringkasan atau progres per cabang</small>
                        </div>
                        <div class="micro-pipeline-source-accordion">
                            @foreach((array) data_get($pipeline, 'sources', []) as $source)
                                @php
                                    $srcTotal = max(1, (int) data_get($source, 'total', 0));
                                    $srcDone = (int) data_get($source, 'done', 0);
                                    $srcPlan = (int) data_get($source, 'scheduled', 0);
                                    $srcPending = (int) data_get($source, 'pending', 0);
                                    $srcDoneRate = ($srcDone / $srcTotal) * 100;
                                    $srcPlanRate = ($srcPlan / $srcTotal) * 100;
                                    $hasDetails = !empty($source['groups']) || !empty($source['products']);
                                    $sourceDetail = [
                                        'label' => (string) data_get($source, 'label', 'Tidak Teridentifikasi'),
                                        'group_label' => (string) data_get($pipeline, 'group_label', 'Cabang'),
                                        'total' => $srcTotal,
                                        'done' => $srcDone,
                                        'scheduled' => $srcPlan,
                                        'pending' => $srcPending,
                                        'visit_rate' => (float) data_get($source, 'visit_rate', 0),
                                        'potential_plafond' => (float) data_get($source, 'potential_plafond', 0),
                                        'groups' => array_values((array) data_get($source, 'groups', [])),
                                    ];
                                @endphp
                                <div class="micro-pipeline-source-card {{ $hasDetails ? 'has-drawer' : '' }}">
                                    <div class="micro-pipeline-source-card__actions">
                                        <button type="button" class="micro-pipeline-source-btn" data-source-toggle aria-expanded="false" aria-label="Buka ringkasan {{ data_get($source, 'label', '-') }}">
                                            <span class="micro-pipeline-source-card__rank">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                            <div class="micro-pipeline-source-card__main">
                                                <div class="micro-pipeline-source-card__title">
                                                    <strong>{{ data_get($source, 'label', '-') }}</strong>
                                                    <small>{{ $formatInteger($srcTotal) }} nominatif &middot; {{ $formatAmount(data_get($source, 'potential_plafond', 0)) }}</small>
                                                </div>
                                                <div class="micro-pipeline-segmented-bar" title="{{ $formatInteger($srcDone) }} selesai, {{ $formatInteger($srcPlan) }} terjadwal, {{ $formatInteger($srcPending) }} belum">
                                                    <span class="is-done" style="width: {{ $srcDoneRate }}%"></span>
                                                    <span class="is-plan" style="width: {{ $srcPlanRate }}%"></span>
                                                </div>
                                                <div class="micro-pipeline-source-card__stats">
                                                    <span>{{ $formatInteger($srcDone) }} selesai (<b>{{ $formatPercent(data_get($source, 'visit_rate', 0)) }}</b>) &middot; {{ $formatInteger($srcPlan + $srcPending) }} terbuka</span>
                                                </div>
                                            </div>
                                            <i class="fas fa-chevron-down micro-source-chevron" aria-hidden="true"></i>
                                        </button>
                                        <button type="button"
                                                class="micro-pipeline-source-detail"
                                                data-micro-pipeline-source-detail='@json($sourceDetail)'
                                                aria-label="Lihat progres {{ data_get($pipeline, 'group_label', 'Cabang') }} untuk sumber {{ data_get($source, 'label', '-') }}">
                                            <i class="fas fa-chart-bar" aria-hidden="true"></i><span>Progres {{ data_get($pipeline, 'group_label', 'Cabang') }}</span>
                                        </button>
                                    </div>

                                    <div class="micro-pipeline-source-drawer" hidden>
                                        <div class="micro-source-drawer__kpis">
                                            <div class="micro-source-drawer__kpi is-done">
                                                <span>Selesai Dikunjungi</span>
                                                <strong>{{ $formatInteger($srcDone) }}</strong>
                                                <small>{{ $formatPercent(data_get($source, 'visit_rate', 0)) }}</small>
                                            </div>
                                            <div class="micro-source-drawer__kpi is-plan">
                                                <span>Kunjungan Terjadwal</span>
                                                <strong>{{ $formatInteger($srcPlan) }}</strong>
                                                <small>{{ $formatPercent(data_get($source, 'scheduled_rate', 0)) }}</small>
                                            </div>
                                            <div class="micro-source-drawer__kpi is-pending">
                                                <span>Belum Dikunjungi</span>
                                                <strong>{{ $formatInteger($srcPending) }}</strong>
                                                <small>{{ $formatPercent(data_get($source, 'pending_rate', 0)) }}</small>
                                            </div>
                                            <div class="micro-source-drawer__kpi is-potensi">
                                                <span>Potensi Plafon</span>
                                                <strong>{{ $formatAmount(data_get($source, 'potential_plafond', 0)) }}</strong>
                                                <small>{{ $formatInteger($srcTotal) }} total</small>
                                            </div>
                                        </div>

                                        @if(!empty($source['groups']))
                                            <div class="micro-source-drawer__section">
                                                <div class="micro-source-drawer__section-head">
                                                    <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Distribusi {{ data_get($pipeline, 'group_label', 'Cabang') }}</span>
                                                </div>
                                                <div class="micro-source-subgrid">
                                                    @foreach((array) $source['groups'] as $grp)
                                                        @php
                                                            $gTotal = max(1, (int) data_get($grp, 'total', 0));
                                                            $gDone = (int) data_get($grp, 'done', 0);
                                                            $gDoneRate = ($gDone / $gTotal) * 100;
                                                        @endphp
                                                        <div class="micro-source-subitem">
                                                            <div class="micro-source-subitem__title">
                                                                <b>{{ data_get($grp, 'label', '-') }}</b>
                                                                <span>{{ $formatInteger($gTotal) }} pipeline &middot; {{ $formatAmount(data_get($grp, 'potential_plafond', 0)) }}</span>
                                                            </div>
                                                            <div class="micro-source-subitem__progress">
                                                                <div class="micro-pipeline-segmented-bar"><span class="is-done" style="width: {{ $gDoneRate }}%"></span></div>
                                                                <strong>{{ $formatPercent(data_get($grp, 'visit_rate', 0)) }}</strong>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        @if(!empty($source['products']))
                                            <div class="micro-source-drawer__section">
                                                <div class="micro-source-drawer__section-head">
                                                    <span><i class="fas fa-box-open" aria-hidden="true"></i> Produk Rekomendasi</span>
                                                </div>
                                                <div class="micro-source-subgrid micro-source-subgrid--products">
                                                    @foreach((array) $source['products'] as $prod)
                                                        @php
                                                            $pTotal = max(1, (int) data_get($prod, 'total', 0));
                                                            $pDone = (int) data_get($prod, 'done', 0);
                                                            $pDoneRate = ($pDone / $pTotal) * 100;
                                                        @endphp
                                                        <div class="micro-source-subitem">
                                                            <div class="micro-source-subitem__title">
                                                                <b>{{ data_get($prod, 'label', '-') }}</b>
                                                                <span>{{ $formatInteger($pTotal) }} pipeline &middot; {{ $formatAmount(data_get($prod, 'potential_plafond', 0)) }}</span>
                                                            </div>
                                                            <div class="micro-source-subitem__progress">
                                                                <div class="micro-pipeline-segmented-bar"><span class="is-done" style="width: {{ $pDoneRate }}%"></span></div>
                                                                <strong>{{ $formatPercent(data_get($prod, 'visit_rate', 0)) }}</strong>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                </div>

                <script type="application/json" data-micro-pipeline-initial>@json($pipelineInitial)</script>
                <div class="micro-pipeline-modal"
                     data-micro-pipeline-modal
                     data-url="{{ route('dashboard.micro-pipeline', ['cabang' => data_get($pipeline, 'query_scope', 'area6')]) }}"
                     data-prewash-sheet="{{ data_get($pipeline, 'sync.source_sheet', 'Nominatif') }}"
                     data-prewash-url="{{ data_get($pipeline, 'sync.source_url', '') }}"
                     data-slik-hijau-sheet="{{ data_get($slikHijau, 'sync.source_sheet', 'Berminat 1') }}"
                     data-slik-hijau-url="{{ data_get($slikHijau, 'sync.source_url', '') }}"
                     hidden>
                    <div class="micro-pipeline-modal__backdrop" data-micro-pipeline-close></div>
                    <section class="micro-pipeline-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="micro-pipeline-modal-title">
                        <header>
                            <div><span>NOMINATIF PIPELINE MIKRO</span><h3 id="micro-pipeline-modal-title">{{ data_get($pipeline, 'scope_label', 'Area 6') }}</h3></div>
                            <div><a href="{{ data_get($pipeline, 'sync.source_url', '#') }}" data-micro-pipeline-source-link target="_blank" rel="noopener noreferrer" title="Buka sumber spreadsheet"><i class="fas fa-external-link-alt" aria-hidden="true"></i><span class="sr-only">Buka sumber spreadsheet</span></a><button type="button" data-micro-pipeline-close aria-label="Tutup nominatif"><i class="fas fa-times" aria-hidden="true"></i></button></div>
                        </header>
                        <form class="micro-pipeline-filters" data-micro-pipeline-filters>
                            <label><span>Dataset</span><select name="dataset">@foreach((array) data_get($pipeline, 'dataset_options', []) as $dataset)<option value="{{ data_get($dataset, 'key') }}">{{ data_get($dataset, 'label') }}</option>@endforeach</select></label>
                            <label><span>Status</span><select name="status"><option value="open">Belum selesai</option><option value="all">Semua status</option><option value="done">Sudah dikunjungi</option><option value="scheduled">Terjadwal</option><option value="pending">Belum dikunjungi</option></select></label>
                            <label><span>Sumber Pipeline</span><select name="source"><option value="">Semua sumber</option>@foreach((array) data_get($pipeline, 'source_options', []) as $source)<option value="{{ $source }}">{{ $source }}</option>@endforeach</select></label>
                            <label><span>Produk / Arah Pipeline</span><select name="product"><option value="">Semua produk</option>@foreach((array) data_get($pipeline, 'product_options', []) as $product)<option value="{{ $product }}">{{ $product }}</option>@endforeach</select></label>
                            <label class="micro-pipeline-filters__search"><span>Cari Debitur / CIF / Unit / Mantri / Keterangan</span><input type="search" name="search" maxlength="100" placeholder="Ketik kata kunci"></label>
                            <button type="submit"><i class="fas fa-search" aria-hidden="true"></i><span>Terapkan</span></button>
                        </form>
                        <div class="micro-pipeline-modal__meta"><span data-micro-pipeline-result-count>{{ $formatInteger(data_get($pipelineInitial, 'meta.total', 0)) }} nominatif</span><span data-micro-pipeline-sheet>Sheet: {{ data_get($pipeline, 'sync.source_sheet', '-') }}</span></div>
                        <div class="micro-pipeline-modal__table-wrap">
                            <table class="micro-pipeline-modal__table"><caption class="sr-only">Nominatif Pipeline Mikro</caption><thead><tr><th>Debitur</th><th>Unit / Mantri</th><th>Sumber</th><th>Produk</th><th>Potensi</th><th>Keterangan</th><th>Kunjungan</th></tr></thead><tbody data-micro-pipeline-body></tbody></table>
                        </div>
                        <footer><span data-micro-pipeline-page-info>Halaman 1</span><div><button type="button" data-micro-pipeline-page="prev" aria-label="Halaman sebelumnya"><i class="fas fa-chevron-left" aria-hidden="true"></i></button><button type="button" data-micro-pipeline-page="next" aria-label="Halaman berikutnya"><i class="fas fa-chevron-right" aria-hidden="true"></i></button></div></footer>
                    </section>
                </div>
                <div class="micro-pipeline-source-modal" data-micro-pipeline-source-modal hidden>
                    <div class="micro-pipeline-source-modal__backdrop" data-micro-pipeline-source-close></div>
                    <section class="micro-pipeline-source-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="micro-pipeline-source-modal-title">
                        <header>
                            <div><span>SUMBER PIPELINE</span><h3 id="micro-pipeline-source-modal-title" data-micro-pipeline-source-title>Progress Penyelesaian</h3></div>
                            <button type="button" data-micro-pipeline-source-close aria-label="Tutup progres sumber pipeline"><i class="fas fa-times" aria-hidden="true"></i></button>
                        </header>
                        <div class="micro-pipeline-source-modal__summary" data-micro-pipeline-source-summary></div>
                        <div class="micro-pipeline-source-modal__table-wrap">
                            <table class="micro-pipeline-source-modal__table">
                                <caption class="sr-only">Progress penyelesaian pipeline per cabang</caption>
                                <thead><tr><th>No.</th><th data-micro-pipeline-source-group-heading>Cabang</th><th>Total</th><th>Selesai</th><th>Terjadwal</th><th>Belum</th><th>Progres</th><th>Potensi Plafon</th></tr></thead>
                                <tbody data-micro-pipeline-source-body></tbody>
                            </table>
                        </div>
                        <footer>
                            <span data-micro-pipeline-source-meta>-</span>
                            <button type="button" data-micro-pipeline-source-open-nominatives><i class="fas fa-list" aria-hidden="true"></i><span>Lihat Nominatif Sumber</span></button>
                        </footer>
                    </section>
                </div>
            @else
                <div class="micro-pipeline-empty" role="status"><i class="fas fa-project-diagram" aria-hidden="true"></i><div><strong>Pipeline Mikro belum siap ditampilkan</strong><span>{{ data_get($pipeline, 'error', 'Jalankan sinkronisasi sumber Pipeline Mikro.') }}</span></div></div>
            @endif
        </section>

        <section class="micro-ops-section micro-ops-section--billing" aria-labelledby="micro-billing-title" data-micro-billing-section>
            <div class="micro-ops-section__head micro-billing-head">
                <div>
                    <span class="micro-ops-section__number">04</span>
                    <span class="micro-ops-eyebrow">MONITORING BILLING &amp; NEXT PAYMENT DATE (NPD)</span>
                    <h3 id="micro-billing-title">Jadwal &amp; Realisasi Billing Mikro</h3>
                    <p class="micro-billing-desc">
                        Monitoring jadwal jatuh tempo angsuran harian (1 s.d. akhir bulan), pelacakan pembayaran, dan benchmark komparasi realisasi M-1.
                    </p>
                </div>
                <div class="micro-billing-toolbar">
                    <div class="micro-billing-toggle-group" role="group" aria-label="Pilihan Periode Tampilan">
                        <button type="button" class="micro-billing-btn is-active" data-billing-view="m0" aria-pressed="true">
                            <i class="far fa-calendar-alt" aria-hidden="true"></i>
                            <span>Bulan Berjalan ({{ data_get($billingM0, 'month_label', 'M0') }})</span>
                        </button>
                        <button type="button" class="micro-billing-btn" data-billing-view="m1" aria-pressed="false">
                            <i class="fas fa-history" aria-hidden="true"></i>
                            <span>Bulan Lalu (M-1 / {{ data_get($billingM1, 'month_label', 'M-1') }})</span>
                        </button>
                        <button type="button" class="micro-billing-btn" data-billing-view="compare" aria-pressed="false">
                            <i class="fas fa-columns" aria-hidden="true"></i>
                            <span>Komparasi</span>
                        </button>
                    </div>

                    <div class="micro-billing-toggle-group" role="group" aria-label="Pilihan Satuan Metrik">
                        <button type="button" class="micro-billing-btn is-active" data-billing-metric="os" aria-pressed="true">
                            <i class="fas fa-money-bill-wave" aria-hidden="true"></i>
                            <span>Baki Debet (OS)</span>
                        </button>
                        <button type="button" class="micro-billing-btn" data-billing-metric="deb" aria-pressed="false">
                            <i class="fas fa-users" aria-hidden="true"></i>
                            <span>Debitur</span>
                        </button>
                    </div>
                </div>
            </div>

            @if(!empty($billing['available']))
                <!-- 4 Executive Summary KPI Cards -->
                <div class="micro-billing-kpis">
                    <article class="micro-billing-kpi-card tone-primary">
                        <div class="micro-billing-kpi-card__head">
                            <span><i class="far fa-calendar-check" aria-hidden="true"></i> Total Billing Bulan Ini</span>
                            <span class="micro-billing-kpi-badge">{{ data_get($billingM0, 'month_label', '-') }}</span>
                        </div>
                        <strong class="micro-billing-kpi-val" data-metric-display="os">{{ $formatAmount(data_get($billingM0, 'total_billing_os', 0)) }}</strong>
                        <strong class="micro-billing-kpi-val d-none" data-metric-display="deb">{{ $formatInteger(data_get($billingM0, 'total_billing_debitur', 0)) }} deb</strong>
                        <div class="micro-billing-kpi-sub">
                            <span data-metric-display="os">Total OS seluruh jadwal billing bulan berjalan</span>
                            <span data-metric-display="deb" class="d-none">Total debitur seluruh jadwal billing bulan berjalan</span>
                            <small>Snapshot baseline D-1: {{ data_get($billingM0, 'baseline_period', '-') }}</small>
                        </div>
                    </article>

                    <article class="micro-billing-kpi-card tone-due">
                        <div class="micro-billing-kpi-card__head">
                            <span><i class="far fa-clock" aria-hidden="true"></i> Jatuh Tempo s.d. Hari Ini</span>
                            <span class="micro-billing-kpi-badge">Tgl 1 - {{ data_get($billing, 'current_day', 0) }}</span>
                        </div>
                        <strong class="micro-billing-kpi-val" data-metric-display="os">{{ $formatAmount(data_get($billingM0, 'due_so_far_billing_os', 0)) }}</strong>
                        <strong class="micro-billing-kpi-val d-none" data-metric-display="deb">{{ $formatInteger(data_get($billingM0, 'due_so_far_billing_debitur', 0)) }} deb</strong>
                        <div class="micro-billing-kpi-sub">
                            <span data-metric-display="os">Total OS yang sudah jatuh tempo</span>
                            <span data-metric-display="deb" class="d-none">Total debitur yang sudah jatuh tempo</span>
                            <small>Akumulasi s.d. posisi data</small>
                        </div>
                    </article>

                    <article class="micro-billing-kpi-card tone-realized">
                        <div class="micro-billing-kpi-card__head">
                            <span><i class="fas fa-check-circle" aria-hidden="true"></i> Billing Terbayar s.d. Hari Ini</span>
                            <span class="micro-billing-kpi-badge highlight" data-metric-display="os">{{ $formatPercent(data_get($billingM0, 'collection_rate_os', 0)) }}</span>
                            <span class="micro-billing-kpi-badge highlight d-none" data-metric-display="deb">{{ $formatPercent(data_get($billingM0, 'collection_rate_deb', 0)) }}</span>
                        </div>
                        <strong class="micro-billing-kpi-val" data-metric-display="os">{{ $formatAmount(data_get($billingM0, 'paid_os', 0)) }}</strong>
                        <strong class="micro-billing-kpi-val d-none" data-metric-display="deb">{{ $formatInteger(data_get($billingM0, 'paid_debitur', 0)) }} deb</strong>
                        <div class="micro-billing-kpi-sub">
                            <div class="micro-billing-meter">
                                <span class="bar is-good" style="width: {{ min(100, max(0, (float) data_get($billingM0, 'collection_rate_os', 0))) }}%"></span>
                            </div>
                            <span data-metric-display="os">{{ $formatPercent(data_get($billingM0, 'collection_rate_os', 0)) }} dari total OS jatuh tempo</span>
                            <span data-metric-display="deb" class="d-none">{{ $formatPercent(data_get($billingM0, 'collection_rate_deb', 0)) }} dari total debitur jatuh tempo</span>
                            <small>Pelunasan &amp; pergeseran NPD</small>
                        </div>
                    </article>

                    <article class="micro-billing-kpi-card tone-benchmark">
                        <div class="micro-billing-kpi-card__head">
                            <span><i class="fas fa-tachometer-alt" aria-hidden="true"></i> Perbandingan Tanggal Sama M-1</span>
                            <span class="micro-billing-kpi-badge">s.d. {{ data_get($billingM1, 'same_day_cutoff_label', '-') }}</span>
                        </div>
                        @php
                            $rateM0Os = (float) data_get($billingM0, 'collection_rate_os', 0);
                            $rateM1Os = (float) data_get($billingM1, 'same_day_collection_rate_os', 0);
                            $diffOs = round($rateM0Os - $rateM1Os, 1);

                            $rateM0Deb = (float) data_get($billingM0, 'collection_rate_deb', 0);
                            $rateM1Deb = (float) data_get($billingM1, 'same_day_collection_rate_deb', 0);
                            $diffDeb = round($rateM0Deb - $rateM1Deb, 1);
                        @endphp
                        <div class="micro-billing-kpi-bench" data-metric-display="os">
                            <strong class="micro-billing-kpi-val">{{ $formatPercent($rateM1Os) }}</strong>
                            <span class="micro-billing-delta-tag {{ $diffOs >= 0 ? 'is-positive' : 'is-negative' }}">
                                {{ $diffOs > 0 ? 'Naik ' : ($diffOs < 0 ? 'Turun ' : 'Tetap ') }}{{ number_format(abs($diffOs), 1, ',', '.') }} pp
                            </span>
                        </div>
                        <div class="micro-billing-kpi-bench d-none" data-metric-display="deb">
                            <strong class="micro-billing-kpi-val">{{ $formatPercent($rateM1Deb) }}</strong>
                            <span class="micro-billing-delta-tag {{ $diffDeb >= 0 ? 'is-positive' : 'is-negative' }}">
                                {{ $diffDeb > 0 ? 'Naik ' : ($diffDeb < 0 ? 'Turun ' : 'Tetap ') }}{{ number_format(abs($diffDeb), 1, ',', '.') }} pp
                            </span>
                        </div>
                        <div class="micro-billing-kpi-sub">
                            <span data-metric-display="os">Closing akhir bulan M-1: {{ $formatPercent(data_get($billingM1, 'collection_rate_os', 0)) }} ({{ $formatAmount(data_get($billingM1, 'paid_os', 0)) }})</span>
                            <span data-metric-display="deb" class="d-none">Closing akhir bulan M-1: {{ $formatPercent(data_get($billingM1, 'collection_rate_deb', 0)) }} ({{ $formatInteger(data_get($billingM1, 'paid_debitur', 0)) }} deb)</span>
                            <small>M0 tgl 1-{{ data_get($billing, 'current_day', 0) }} dibanding M-1 tgl 1-{{ data_get($billingM1, 'same_day_cutoff', 0) }}</small>
                        </div>
                    </article>
                </div>

                <!-- Daily Calendar Grid (Day 1 - 30/31) -->
                <div class="micro-billing-grid-wrapper">
                    <div class="micro-billing-calendar-grid view-mode-m0">
                        @foreach($billingCards as $card)
                            @php
                                $cDay = (int) $card['day'];
                                $cStatus = (string) $card['status'];
                                $cIsDue = (bool) $card['is_due'];
                                $cIsToday = (bool) $card['is_today'];
                                $cIsWeekend = (bool) $card['is_weekend'];
                                $cPctOs = (float) $card['pct_os'];
                                $cPctDeb = (float) $card['pct_debitur'];
                                $cM1PctOs = (float) $card['m1_pct_os'];
                                $cM1PctDeb = (float) $card['m1_pct_debitur'];
                                $cDeltaOs = (float) $card['delta_pct_os'];
                                $cDeltaDeb = (float) $card['delta_pct_deb'];
                                $cComparisonLabel = (string) data_get($card, 'comparison_date_label', 'M-1');
                                $cComparisonAdjusted = (bool) data_get($card, 'comparison_date_adjusted', false);

                                $colorLevel = $cPctOs >= 85.0 ? 'is-good' : ($cPctOs >= 60.0 ? 'is-mid' : 'is-low');
                                $debColorLevel = $cPctDeb >= 85.0 ? 'is-good' : ($cPctDeb >= 60.0 ? 'is-mid' : 'is-low');
                                $m1ColorLevel = $cM1PctOs >= 85.0 ? 'is-good' : ($cM1PctOs >= 60.0 ? 'is-mid' : 'is-low');
                                $m1DebColorLevel = $cM1PctDeb >= 85.0 ? 'is-good' : ($cM1PctDeb >= 60.0 ? 'is-mid' : 'is-low');
                            @endphp

                            <div class="micro-billing-card status-{{ $cStatus }} {{ $cIsWeekend ? 'is-weekend' : '' }} {{ $cIsToday ? 'is-today' : '' }}"
                                 data-day="{{ $cDay }}"
                                 data-status="{{ $cStatus }}">

                                <div class="micro-billing-card__top">
                                    <div class="micro-billing-card__date">
                                        <span class="day-num">{{ sprintf('%02d', $cDay) }}</span>
                                        <span class="day-name">{{ $card['day_name'] }}</span>
                                    </div>

                                    <div class="micro-billing-card__badge-wrap">
                                        @if($cIsToday)
                                            <span class="micro-card-badge is-today"><i class="fas fa-circle-dot fa-beat" aria-hidden="true"></i> Hari Ini</span>
                                        @elseif($cStatus === 'past')
                                            <span class="micro-card-badge is-past">Selesai</span>
                                        @else
                                            <span class="micro-card-badge is-upcoming">Belum Tempo</span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Progress Bar (for past & today) -->
                                <div class="micro-billing-card__meter">
                                    @if($cIsDue)
                                        <div class="meter-bar {{ $colorLevel }}" data-metric-display="os" style="width: {{ min(100, max(4, $cPctOs)) }}%" title="OS terbayar: {{ $formatPercent($cPctOs) }}"></div>
                                        <div class="meter-bar {{ $debColorLevel }} d-none" data-metric-display="deb" style="width: {{ min(100, max(4, $cPctDeb)) }}%" title="Debitur terbayar: {{ $formatPercent($cPctDeb) }}"></div>
                                    @else
                                        <div class="meter-bar is-upcoming-track" style="width: 100%" title="Belum Jatuh Tempo"></div>
                                    @endif
                                </div>

                                <!-- Body Data: M0 View -->
                                <div class="micro-billing-card__data card-view-content view-m0">
                                    <div class="metric-block" data-metric-display="os">
                                        <div class="billing-value-row billing-value-row--total">
                                            <span>Billing</span>
                                            <strong>{{ $formatAmount($card['billing_os']) }}</strong>
                                        </div>
                                        <div class="billing-value-row billing-value-row--paid">
                                            <span>Bayar</span>
                                            @if($cIsDue)
                                                <strong>{{ $formatAmount($card['paid_os']) }}</strong>
                                                <small class="{{ $colorLevel }}">{{ $formatPercent($cPctOs) }}</small>
                                            @else
                                                <strong class="is-pending">Belum tempo</strong>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="metric-block d-none" data-metric-display="deb">
                                        <div class="billing-value-row billing-value-row--total">
                                            <span>Billing</span>
                                            <strong>{{ $formatInteger($card['billing_debitur']) }} deb</strong>
                                        </div>
                                        <div class="billing-value-row billing-value-row--paid">
                                            <span>Bayar</span>
                                            @if($cIsDue)
                                                <strong>{{ $formatInteger($card['paid_debitur']) }} deb</strong>
                                                <small class="{{ $debColorLevel }}">{{ $formatPercent($cPctDeb) }}</small>
                                            @else
                                                <strong class="is-pending">Belum tempo</strong>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Body Data: M-1 View (When toggled to M-1) -->
                                <div class="micro-billing-card__data card-view-content view-m1 d-none">
                                    <div class="metric-block" data-metric-display="os">
                                        <div class="billing-reference-date">Acuan {{ $cComparisonLabel }}{{ $cComparisonAdjusted ? ' (akhir bulan)' : '' }}</div>
                                        <div class="billing-value-row billing-value-row--total">
                                            <span>Billing</span>
                                            <strong>{{ $formatAmount($card['m1_billing_os']) }}</strong>
                                        </div>
                                        <div class="billing-value-row billing-value-row--paid">
                                            <span>Bayar</span>
                                            <strong>{{ $formatAmount($card['m1_paid_os']) }}</strong>
                                            <small class="{{ $m1ColorLevel }}">{{ $formatPercent($cM1PctOs) }}</small>
                                        </div>
                                    </div>
                                    <div class="metric-block d-none" data-metric-display="deb">
                                        <div class="billing-reference-date">Acuan {{ $cComparisonLabel }}{{ $cComparisonAdjusted ? ' (akhir bulan)' : '' }}</div>
                                        <div class="billing-value-row billing-value-row--total">
                                            <span>Billing</span>
                                            <strong>{{ $formatInteger($card['m1_billing_debitur']) }} deb</strong>
                                        </div>
                                        <div class="billing-value-row billing-value-row--paid">
                                            <span>Bayar</span>
                                            <strong>{{ $formatInteger($card['m1_paid_debitur']) }} deb</strong>
                                            <small class="{{ $m1DebColorLevel }}">{{ $formatPercent($cM1PctDeb) }}</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Body Data: Compare View (When toggled to Side-by-Side) -->
                                <div class="micro-billing-card__data card-view-content view-compare d-none">
                                    <div class="compare-item m0-item">
                                        <span class="item-tag">M0 / Tgl {{ sprintf('%02d', $cDay) }}</span>
                                        <span data-metric-display="os">Bayar <b>{{ $cIsDue ? $formatAmount($card['paid_os']) : 'Belum tempo' }}</b> / {{ $formatAmount($card['billing_os']) }}</span>
                                        <span data-metric-display="deb" class="d-none">Bayar <b>{{ $cIsDue ? $formatInteger($card['paid_debitur']).' deb' : 'Belum tempo' }}</b> / {{ $formatInteger($card['billing_debitur']) }} deb</span>
                                    </div>
                                    <div class="compare-item m1-item">
                                        <span class="item-tag">M-1 / {{ $cComparisonLabel }}</span>
                                        <span data-metric-display="os">Bayar <b>{{ $formatAmount($card['m1_paid_os']) }}</b> / {{ $formatAmount($card['m1_billing_os']) }}</span>
                                        <span data-metric-display="deb" class="d-none">Bayar <b>{{ $formatInteger($card['m1_paid_debitur']) }} deb</b> / {{ $formatInteger($card['m1_billing_debitur']) }} deb</span>
                                    </div>
                                </div>

                                <!-- Footer Benchmark Chip (M-1 Benchmark Pill) -->
                                <div class="micro-billing-card__foot">
                                    <div class="m1-benchmark-pill" data-metric-display="os" title="Collection OS M-1 pada {{ $cComparisonLabel }}: {{ $formatPercent($cM1PctOs) }}">
                                        <span class="pill-label">vs {{ $cComparisonLabel }}: {{ $formatPercent($cM1PctOs) }}</span>
                                        @if($cIsDue)
                                            <span class="pill-delta {{ $cDeltaOs > 0 ? 'is-up' : ($cDeltaOs < 0 ? 'is-down' : 'is-flat') }}">
                                                {{ ($cDeltaOs > 0 ? 'Naik ' : ($cDeltaOs < 0 ? 'Turun ' : 'Tetap ')).number_format(abs($cDeltaOs), 1, ',', '.').' pp' }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="m1-benchmark-pill d-none" data-metric-display="deb" title="Collection debitur M-1 pada {{ $cComparisonLabel }}: {{ $formatPercent($cM1PctDeb) }}">
                                        <span class="pill-label">vs {{ $cComparisonLabel }}: {{ $formatPercent($cM1PctDeb) }}</span>
                                        @if($cIsDue)
                                            <span class="pill-delta {{ $cDeltaDeb > 0 ? 'is-up' : ($cDeltaDeb < 0 ? 'is-down' : 'is-flat') }}">
                                                {{ ($cDeltaDeb > 0 ? 'Naik ' : ($cDeltaDeb < 0 ? 'Turun ' : 'Tetap ')).number_format(abs($cDeltaDeb), 1, ',', '.').' pp' }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Footer Legend -->
                <div class="micro-billing-legend">
                    <div class="legend-item"><span class="legend-badge is-past"></span><span>Selesai / Terlewat</span></div>
                    <div class="legend-item"><span class="legend-badge is-today"></span><span>Hari Ini (Active)</span></div>
                    <div class="legend-item"><span class="legend-badge is-upcoming"></span><span>Belum Jatuh Tempo</span></div>
                    <div class="legend-item"><span class="legend-badge is-weekend"></span><span>Sabtu / Minggu</span></div>
                    <div class="legend-note">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <span>Baseline dihitung dari posisi nominatif akhir bulan sebelumnya ({{ data_get($billingM0, 'baseline_period', '-') }}). Status bayar dihitung jika rekening telah lunas atau tanggal Next Payment Date (NPD) telah bergeser maju. Perbandingan memakai tanggal kalender yang sama di M-1; jika tanggal itu tidak tersedia, acuannya adalah hari terakhir M-1.</span>
                    </div>
                </div>
            @else
                <div class="micro-pipeline-empty" role="status">
                    <i class="far fa-calendar-times" aria-hidden="true"></i>
                    <div>
                        <strong>Jadwal Billing Mikro belum siap ditampilkan</strong>
                        <span>{{ data_get($billing, 'reason', 'Data snapshot Daily Loan Dinamis atau kolom next_pmt_date belum tersedia.') }}</span>
                    </div>
                </div>
            @endif
        </section>

        <style>
        .micro-rm-kur-productivity {
            min-width: 0;
            margin: 1rem;
            overflow: hidden;
            background: #fff;
            border: 1px solid #9fc4e8;
            border-radius: 6px 20px 6px 20px;
            box-shadow: 0 18px 34px -30px rgba(3, 40, 90, 0.9);
        }
        .micro-rm-kur-head {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 0.8rem;
            padding: 0.9rem 1rem;
            color: #fff;
            background: linear-gradient(118deg, #052d68 0%, var(--micro-nusantara, #0754bd) 62%, #087fc8 100%);
            border-bottom: 2px solid var(--micro-cakrawala, #13a7e2);
        }
        .micro-rm-kur-head__icon {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            color: #063476;
            background: #bfeeff;
            border-radius: 5px 13px 5px 13px;
            font-size: 1rem;
        }
        .micro-rm-kur-head__copy { min-width: 0; }
        .micro-rm-kur-head__copy .micro-ops-eyebrow { color: #8fe5ff; }
        .micro-rm-kur-head__copy h4 { margin: 0.08rem 0 0; color: #fff; font-size: clamp(0.92rem, 1.5vw, 1.12rem); }
        .micro-rm-kur-head__copy p { max-width: 760px; margin: 0.24rem 0 0; color: rgba(255, 255, 255, 0.84); font-size: 0.7rem; line-height: 1.45; }
        .micro-rm-kur-head__meta { display: flex; max-width: 320px; flex-wrap: wrap; justify-content: flex-end; gap: 0.42rem; }
        .micro-rm-kur-head__meta span {
            display: inline-flex;
            min-height: 30px;
            align-items: center;
            gap: 0.35rem;
            padding: 0.36rem 0.56rem;
            color: #fff;
            background: rgba(255, 255, 255, 0.13);
            border: 1px solid rgba(191, 238, 255, 0.34);
            border-radius: 999px;
            font-size: 0.64rem;
            font-weight: 800;
        }
        .micro-rm-kur-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.7rem;
            padding: 0.85rem;
            background: #edf5ff;
            border-bottom: 1px solid #b8cee5;
        }
        .micro-rm-kur-summary article {
            min-width: 0;
            padding: 0.72rem 0.78rem;
            background: #fff;
            border: 1px solid #bfd4e8;
            border-left: 4px solid var(--micro-nusantara, #0754bd);
            border-radius: 4px 12px 4px 12px;
        }
        .micro-rm-kur-summary span,
        .micro-rm-kur-summary small { display: block; color: #59708b; font-size: 0.62rem; }
        .micro-rm-kur-summary span { font-weight: 850; letter-spacing: 0.025em; text-transform: uppercase; }
        .micro-rm-kur-summary strong { display: block; margin: 0.18rem 0 0.08rem; color: #052d68; font-size: clamp(0.92rem, 1.5vw, 1.18rem); font-variant-numeric: tabular-nums; }
        .micro-rm-kur-table-wrap {
            max-width: 100%;
            max-height: none;
            overflow-x: auto;
            overflow-y: visible;
            border: 0;
            scrollbar-gutter: auto;
            -webkit-overflow-scrolling: touch;
        }
        .micro-rm-kur-table { min-width: 1040px; }
        .micro-rm-kur-table thead tr:first-child th,
        .micro-rm-kur-table tfoot th,
        .micro-rm-kur-table tfoot td { position: static; }
        .micro-rm-kur-table tbody td:nth-child(3),
        .micro-rm-kur-table tbody td:nth-child(5) { text-align: left; }
        .micro-rm-kur-person { min-width: 190px; white-space: normal !important; }
        .micro-rm-kur-person strong,
        .micro-rm-kur-person small { display: block; }
        .micro-rm-kur-person small { margin-top: 0.16rem; color: #687f98; font-size: 0.62rem; font-weight: 700; }
        .micro-rm-kur-empty {
            display: flex;
            min-height: 96px;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            padding: 1rem;
            color: #526c86;
            text-align: left;
        }
        .micro-rm-kur-empty i { color: #087fc8; font-size: 1.15rem; }
        .micro-rm-kur-empty strong,
        .micro-rm-kur-empty span { display: block; }
        .micro-rm-kur-empty span { margin-top: 0.15rem; font-size: 0.7rem; }
        .micro-ops-section--billing {
            --billing-nusantara: var(--micro-nusantara, #0754bd);
            --billing-nusantara-dark: #043f8c;
            --billing-cakrawala: var(--micro-cakrawala, #13a7e2);
            --billing-surface: #ffffff;
            --billing-surface-blue: #edf5ff;
            margin-top: 1rem;
            border: 2px solid var(--billing-nusantara);
            border-radius: 10px;
            background: var(--billing-surface);
            box-shadow: 0 8px 22px rgba(7, 84, 189, 0.14);
            overflow: hidden;
        }
        .micro-billing-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.8rem 1rem;
            background: linear-gradient(118deg, var(--billing-nusantara-dark) 0%, var(--billing-nusantara) 58%, #0879cf 100%);
            border-bottom: 2px solid var(--billing-cakrawala);
            color: #fff;
        }
        .micro-billing-head .micro-ops-section__number {
            background: #fff;
            color: var(--billing-nusantara-dark);
            border-color: rgba(255, 255, 255, 0.88);
        }
        .micro-billing-head .micro-ops-eyebrow,
        .micro-billing-head h3 {
            color: #fff;
        }
        .micro-billing-desc {
            margin: 0.15rem 0 0;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.75rem;
            line-height: 1.35;
        }
        .micro-billing-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }
        .micro-billing-toggle-group {
            display: inline-flex;
            padding: 3px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.92);
            border-radius: 7px;
            gap: 2px;
            box-shadow: 0 4px 12px rgba(2, 36, 79, 0.22);
        }
        .micro-billing-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            min-height: 36px;
            padding: 0.3rem 0.58rem;
            background: transparent;
            border: none;
            border-radius: 5px;
            color: var(--billing-nusantara-dark);
            font-size: 0.7rem;
            font-weight: 800;
            cursor: pointer;
            touch-action: manipulation;
            transition: background-color 0.16s ease, color 0.16s ease, box-shadow 0.16s ease;
        }
        .micro-billing-btn:hover {
            color: var(--billing-nusantara-dark);
            background: #dcecff;
        }
        .micro-billing-btn.is-active {
            background: var(--billing-nusantara);
            color: #fff;
            box-shadow: 0 2px 7px rgba(7, 84, 189, 0.34);
        }
        .micro-billing-btn:focus-visible {
            outline: 3px solid #7dd3fc;
            outline-offset: 2px;
        }
        .micro-billing-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.6rem;
            padding: 0.7rem 1rem;
            background: var(--billing-surface-blue);
            border-bottom: 1px solid #bfd8f5;
        }
        @media (max-width: 992px) {
            .micro-billing-kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 576px) {
            .micro-billing-kpis {
                grid-template-columns: 1fr;
            }
        }
        .micro-billing-kpi-card {
            min-height: 108px;
            padding: 0.62rem 0.72rem;
            background: #fff;
            border: 1.5px solid #8ebbea;
            border-radius: 7px;
            box-shadow: 0 3px 9px rgba(7, 84, 189, 0.09);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .micro-billing-kpi-card.tone-primary,
        .micro-billing-kpi-card.tone-benchmark {
            border-color: var(--billing-nusantara);
            background: linear-gradient(145deg, var(--billing-nusantara-dark), var(--billing-nusantara));
        }
        .micro-billing-kpi-card.tone-due { border-top: 4px solid var(--billing-cakrawala); }
        .micro-billing-kpi-card.tone-realized { border-top: 4px solid var(--billing-nusantara); }
        .micro-billing-kpi-card.tone-primary .micro-billing-kpi-card__head,
        .micro-billing-kpi-card.tone-primary .micro-billing-kpi-val,
        .micro-billing-kpi-card.tone-primary .micro-billing-kpi-sub,
        .micro-billing-kpi-card.tone-primary .micro-billing-kpi-sub small,
        .micro-billing-kpi-card.tone-benchmark .micro-billing-kpi-card__head,
        .micro-billing-kpi-card.tone-benchmark .micro-billing-kpi-val,
        .micro-billing-kpi-card.tone-benchmark .micro-billing-kpi-sub,
        .micro-billing-kpi-card.tone-benchmark .micro-billing-kpi-sub small {
            color: #fff;
        }
        .micro-billing-kpi-card.tone-primary .micro-billing-kpi-badge,
        .micro-billing-kpi-card.tone-benchmark .micro-billing-kpi-badge {
            background: #fff;
            color: var(--billing-nusantara-dark);
        }
        .micro-billing-kpi-card__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.72rem;
            font-weight: 800;
            color: #4a627a;
            margin-bottom: 0.22rem;
        }
        .micro-billing-kpi-badge {
            font-size: 0.62rem;
            font-weight: 800;
            padding: 0.15rem 0.4rem;
            border-radius: 5px;
            background: #eef5fc;
            color: #0c569f;
        }
        .micro-billing-kpi-badge.highlight {
            background: #e6f7f2;
            color: #007a65;
        }
        .micro-billing-kpi-val {
            display: block;
            font-size: clamp(1.05rem, 1.25vw, 1.24rem);
            font-weight: 900;
            color: #123354;
            line-height: 1.15;
            font-variant-numeric: tabular-nums;
        }
        .micro-billing-kpi-bench {
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
        }
        .micro-billing-delta-tag {
            font-size: 0.7rem;
            font-weight: 850;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
        }
        .micro-billing-delta-tag.is-positive {
            background: #e6f7f2;
            color: #008a72;
        }
        .micro-billing-delta-tag.is-negative {
            background: #fde8eb;
            color: #c92f47;
        }
        .micro-billing-kpi-sub {
            margin-top: 0.3rem;
            font-size: 0.68rem;
            color: #5c748c;
            font-weight: 650;
        }
        .micro-billing-kpi-sub small {
            display: block;
            color: #8da2b6;
            font-size: 0.62rem;
            margin-top: 0.15rem;
        }
        .micro-billing-meter {
            height: 5px;
            background: #e6edf5;
            border-radius: 99px;
            overflow: hidden;
            margin: 0.25rem 0;
        }
        .micro-billing-meter .bar {
            height: 100%;
            border-radius: 99px;
            transition: width 0.3s ease;
        }
        .micro-billing-meter .bar.is-good { background: linear-gradient(90deg, #008a72, #00b493); }
        .micro-billing-meter .bar.is-mid { background: linear-gradient(90deg, #d97706, #f59e0b); }
        .micro-billing-meter .bar.is-low { background: linear-gradient(90deg, #c92f47, #f43f5e); }

        /* Calendar Grid */
        .micro-billing-grid-wrapper {
            padding: 0.72rem 1rem;
            background: #fff;
            overflow-x: hidden;
        }
        .micro-billing-calendar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(126px, 100%), 1fr));
            gap: 0.5rem;
        }
        @media (min-width: 1400px) {
            .micro-billing-calendar-grid {
                grid-template-columns: repeat(8, minmax(0, 1fr));
            }
        }
        .micro-billing-card {
            background: #fff;
            border: 1.5px solid #84b4e7;
            border-radius: 7px;
            padding: 0.42rem 0.46rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 116px;
            box-shadow: 0 2px 7px rgba(7, 84, 189, 0.08);
            transition: border-color 0.16s ease, box-shadow 0.16s ease;
            position: relative;
        }
        .micro-billing-card:hover {
            box-shadow: 0 5px 14px rgba(7, 84, 189, 0.18);
            border-color: var(--billing-nusantara);
        }
        .micro-billing-card.is-weekend {
            background: #f4f8ff;
            border-style: dashed;
        }
        .micro-billing-card.status-past {
            border-left: 4px solid var(--billing-nusantara);
        }
        .micro-billing-card.status-today {
            border: 2px solid var(--billing-cakrawala);
            border-left: 4px solid #fff;
            background: linear-gradient(145deg, var(--billing-nusantara-dark), var(--billing-nusantara));
            box-shadow: 0 0 0 3px rgba(19, 167, 226, 0.2), 0 6px 16px rgba(7, 84, 189, 0.24);
        }
        .micro-billing-card.status-upcoming {
            border-left: 4px solid #9cc8f3;
            background: #fff;
        }
        .micro-billing-card__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.28rem;
        }
        .micro-billing-card__date {
            display: flex;
            align-items: baseline;
            gap: 0.35rem;
        }
        .micro-billing-card__date .day-num {
            font-size: 1.05rem;
            font-weight: 900;
            color: var(--billing-nusantara-dark);
            font-variant-numeric: tabular-nums;
        }
        .micro-billing-card__date .day-name {
            font-size: 0.64rem;
            font-weight: 800;
            color: #6a829a;
            text-transform: uppercase;
        }
        .micro-card-badge {
            font-size: 0.58rem;
            font-weight: 800;
            padding: 0.12rem 0.38rem;
            border-radius: 4px;
        }
        .micro-card-badge.is-today {
            background: #fff;
            color: var(--billing-nusantara-dark);
        }
        .micro-card-badge.is-past {
            background: var(--billing-nusantara);
            color: #fff;
        }
        .micro-card-badge.is-upcoming {
            background: #e6f1ff;
            color: var(--billing-nusantara-dark);
        }
        .micro-billing-card__meter {
            height: 4.5px;
            background: #e7eff6;
            border-radius: 99px;
            overflow: hidden;
            margin-bottom: 0.34rem;
        }
        .meter-bar {
            height: 100%;
            border-radius: 99px;
        }
        .meter-bar.is-good { background: linear-gradient(90deg, var(--billing-nusantara), var(--billing-cakrawala)); }
        .meter-bar.is-mid { background: linear-gradient(90deg, #0879cf, #49b6e9); }
        .meter-bar.is-low { background: linear-gradient(90deg, #c92f47, #f43f5e); }
        .meter-bar.is-upcoming-track { background: repeating-linear-gradient(45deg, #e2e8f0, #e2e8f0 4px, #edf2f7 4px, #edf2f7 8px); }

        .micro-billing-card__data {
            margin-bottom: 0.3rem;
        }
        .billing-reference-date {
            margin-bottom: 0.16rem;
            color: #526b82;
            font-size: 0.54rem;
            font-weight: 850;
            line-height: 1.2;
        }
        .billing-value-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: baseline;
            gap: 0.08rem 0.18rem;
            min-width: 0;
            padding: 0.13rem 0.2rem;
            border-radius: 4px;
        }
        .billing-value-row + .billing-value-row {
            margin-top: 0.14rem;
        }
        .billing-value-row--total {
            background: #eaf4ff;
            border-left: 3px solid var(--billing-nusantara);
        }
        .billing-value-row--paid {
            background: #fff;
            border: 1px solid #d7e7f7;
        }
        .billing-value-row > span {
            min-width: 0;
            color: #5b7289;
            font-size: 0.52rem;
            font-weight: 850;
            line-height: 1.15;
            white-space: nowrap;
        }
        .billing-value-row > strong {
            color: #173451;
            font-size: 0.68rem;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            line-height: 1.15;
            text-align: right;
            white-space: nowrap;
            justify-self: end;
        }
        .billing-value-row--paid > strong {
            color: var(--billing-nusantara);
        }
        .billing-value-row > small {
            grid-column: 2;
            justify-self: end;
            font-size: 0.56rem;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .billing-value-row > small.is-good { color: #008a72; }
        .billing-value-row > small.is-mid { color: #b86100; }
        .billing-value-row > small.is-low { color: #c92f47; }
        .billing-value-row > strong.is-pending {
            grid-column: 2;
            color: #64748b;
            font-size: 0.57rem;
        }
        .stat-row-primary {
            font-size: 0.74rem;
            font-weight: 850;
            line-height: 1.25;
            font-variant-numeric: tabular-nums;
            color: #173451;
        }
        .stat-row-primary .paid-val { color: var(--billing-nusantara); font-weight: 900; }
        .stat-row-primary .sep { color: #8ea1b4; margin: 0 0.1rem; }
        .stat-row-primary .bill-val { color: #173451; }
        .stat-row-primary--upcoming .bill-val-sole {
            font-size: 0.8rem;
            font-weight: 900;
            color: #1e3a5a;
        }
        .stat-row-secondary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0.18rem;
            font-size: 0.66rem;
            color: #627991;
        }
        .stat-row-secondary .pct.is-good { color: #008a72; font-weight: 900; }
        .stat-row-secondary .pct.is-mid { color: #d97706; font-weight: 900; }
        .stat-row-secondary .pct.is-low { color: #c92f47; font-weight: 900; }
        .upcoming-tag {
            font-size: 0.58rem;
            font-weight: 750;
            color: #64748b;
            background: #f1f5f9;
            padding: 0.08rem 0.3rem;
            border-radius: 3px;
        }
        .compare-dual-row {
            display: flex;
            flex-direction: column;
            gap: 0.22rem;
            font-size: 0.62rem;
        }
        .compare-item {
            display: grid;
            gap: 0.08rem;
            padding: 0.15rem 0.3rem;
            border-radius: 4px;
        }
        .compare-item.m0-item { background: #f0f7ff; color: #0754bd; }
        .compare-item.m1-item { background: #f7f6fe; color: #534eb5; }
        .compare-item .item-tag { font-weight: 850; }
        .compare-item > span:not(.item-tag) { font-size: 0.56rem; line-height: 1.25; }
        .compare-item b { font-variant-numeric: tabular-nums; }
        .micro-billing-card__foot {
            border-top: 1px dashed #d8e5ef;
            padding-top: 0.24rem;
            margin-top: auto;
        }
        .m1-benchmark-pill {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.08rem 0.3rem;
            font-size: 0.58rem;
            color: #556c82;
            background: #edf4fa;
            padding: 0.12rem 0.3rem;
            border-radius: 4px;
        }
        .m1-benchmark-pill .pill-label { font-weight: 800; color: #61778d; }
        .m1-benchmark-pill .pill-val { font-weight: 850; font-variant-numeric: tabular-nums; }
        .m1-benchmark-pill .pill-delta { font-weight: 850; font-variant-numeric: tabular-nums; }
        .m1-benchmark-pill .pill-delta.is-up { color: #008a72; }
        .m1-benchmark-pill .pill-delta.is-down { color: #c92f47; }
        .m1-benchmark-pill .pill-delta.is-flat { color: #526b82; }

        /* Legend */
        .micro-billing-legend {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            padding: 0.58rem 1rem;
            background: var(--billing-nusantara-dark);
            border-top: 2px solid var(--billing-cakrawala);
            font-size: 0.68rem;
            color: #fff;
        }
        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 750;
        }
        .legend-badge {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
        .legend-badge.is-past { background: var(--billing-cakrawala); }
        .legend-badge.is-today { background: #fff; border: 1px solid var(--billing-cakrawala); }
        .legend-badge.is-upcoming { background: #b8d8f7; }
        .legend-badge.is-weekend { background: transparent; border: 1px dashed #fff; }
        .legend-note {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.65rem;
        }
        .micro-billing-card.status-today .day-num,
        .micro-billing-card.status-today .day-name,
        .micro-billing-card.status-today .billing-reference-date,
        .micro-billing-card.status-today .stat-row-primary,
        .micro-billing-card.status-today .stat-row-primary .paid-val,
        .micro-billing-card.status-today .stat-row-primary .bill-val,
        .micro-billing-card.status-today .stat-row-primary--upcoming .bill-val-sole,
        .micro-billing-card.status-today .stat-row-secondary {
            color: #fff;
        }
        .micro-billing-card.status-today .billing-value-row--total,
        .micro-billing-card.status-today .billing-value-row--paid {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.64);
        }
        .micro-billing-card.status-today .billing-value-row > span,
        .micro-billing-card.status-today .billing-value-row > strong,
        .micro-billing-card.status-today .billing-value-row > strong.is-pending {
            color: #fff;
        }
        .micro-billing-card.status-today .billing-value-row > small.is-good { color: #baf7e9; }
        .micro-billing-card.status-today .billing-value-row > small.is-mid { color: #fff0a8; }
        .micro-billing-card.status-today .billing-value-row > small.is-low { color: #ffd0d7; }
        .micro-billing-card.status-today .micro-billing-card__meter {
            background: rgba(255, 255, 255, 0.28);
        }
        .micro-billing-card.status-today .m1-benchmark-pill,
        .micro-billing-card.status-today .compare-item {
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
        }
        .micro-billing-card.status-today .m1-benchmark-pill .pill-label,
        .micro-billing-card.status-today .m1-benchmark-pill .pill-delta,
        .micro-billing-card.status-today .compare-item {
            color: #fff;
        }
        @media (max-width: 900px) {
            .micro-rm-kur-head { grid-template-columns: auto minmax(0, 1fr); }
            .micro-rm-kur-head__meta { grid-column: 1 / -1; max-width: none; justify-content: flex-start; }
            .micro-rm-kur-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .legend-note { margin-left: 0; width: 100%; }
        }
        @media (max-width: 768px) {
            .micro-rm-kur-productivity { margin: 0.75rem; }
            .micro-rm-kur-head { padding: 0.78rem; }
            .micro-billing-head { padding: 0.72rem; }
            .micro-billing-toolbar,
            .micro-billing-toggle-group { width: 100%; }
            .micro-billing-toggle-group { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; }
            .micro-billing-btn { min-height: 44px; justify-content: center; padding-inline: 0.4rem; }
            .micro-billing-kpis,
            .micro-billing-grid-wrapper { padding: 0.6rem; }
        }
        @media (max-width: 520px) {
            .micro-rm-kur-head { grid-template-columns: 1fr; }
            .micro-rm-kur-head__icon { width: 38px; height: 38px; }
            .micro-rm-kur-summary { grid-template-columns: minmax(0, 1fr); }
        }
        @media (prefers-reduced-motion: reduce) {
            .micro-billing-btn,
            .micro-billing-card,
            .micro-billing-meter .bar { transition: none; }
        }
        </style>
    @endif

    @include('dashboard.partials.pn-mismatch', ['pnMismatch' => data_get($microPerformance, 'pn_mismatch', []), 'pnSegment' => 'micro'])
</div>
