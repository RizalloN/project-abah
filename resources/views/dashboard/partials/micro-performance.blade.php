@php
    $meta = (array) data_get($microPerformance, 'meta', []);
    $realization = (array) data_get($microPerformance, 'realization', []);
    $netDisbursement = (array) data_get($microPerformance, 'net_disbursement', []);
    $pdwkLimits = (array) data_get($microPerformance, 'pdwk_limits', []);
    $decisionRanking = (array) data_get($microPerformance, 'decision_ranking', []);
    $realizationNeed = (array) data_get($microPerformance, 'realization_need', []);
    $realizationNeedOptions = collect([
        array_merge($realizationNeed, ['key' => 'all', 'label' => 'Semua Produk']),
        ...(array) data_get($realizationNeed, 'products', []),
    ])->values()->all();
    $mantriPerformance = (array) data_get($microPerformance, 'mantri_performance', []);
    $burden = (array) data_get($microPerformance, 'burden', []);
    $formatInteger = static fn ($value): string => number_format((int) $value, 0, ',', '.');
    $formatAmount = static function ($value): string {
        $millions = (float) $value / 1_000_000;
        $decimals = abs($millions) >= 100 ? 0 : (abs($millions) >= 10 ? 1 : 2);

        return 'Rp '.number_format($millions, $decimals, ',', '.').' jt';
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
                <div class="micro-realization-stack">
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
                </div>

                <div class="micro-realization-products">
                    <div class="micro-ops-subhead"><h4>Realisasi per Produk</h4></div>
                    <div class="micro-ops-table-wrap">
                        <table class="micro-ops-table">
                    <caption class="sr-only">Realisasi plafon baru Mikro per produk</caption>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th class="num">Rekening</th>
                            <th class="num">Plafon Baru</th>
                            <th class="num">Kontribusi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse((array) data_get($realization, 'products', []) as $product)
                            <tr>
                                <th scope="row">{{ data_get($product, 'label', '-') }}</th>
                                <td class="num">{{ $formatInteger(data_get($product, 'deb', 0)) }}</td>
                                <td class="num strong">{{ $formatAmount(data_get($product, 'amount', 0)) }}</td>
                                <td class="num"><span class="micro-share-badge">{{ $formatPercent(data_get($product, 'share', 0)) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">-</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">Total</th>
                            <td class="num">{{ $formatInteger(data_get($realization, 'total.deb', 0)) }}</td>
                            <td class="num strong">{{ $formatAmount(data_get($realization, 'total.amount', 0)) }}</td>
                            <td class="num">100,0%</td>
                        </tr>
                    </tfoot>
                        </table>
                    </div>
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
                    <div class="micro-pdwk-limit__toolbar">
                        <div>
                            <strong>Status Limit Pemutus Bertransaksi</strong>
                            <small>Limit dari workbook; jumlah pemutus, putusan, dan plafon berasal dari realisasi bulan berjalan.</small>
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
                                <article class="micro-pdwk-status tone-{{ data_get($status, 'tone', 'green') }}" style="--pdwk-share: {{ $share }}%">
                                    <header>
                                        <strong>{{ data_get($status, 'label', '-') }}</strong>
                                        <small>{{ data_get($status, 'limit', 0) > 0 ? 'Limit putusan '.data_get($status, 'limit').'% dari PDWK' : 'Tidak diberikan limit putusan' }}</small>
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
                                                    <div class="micro-frequency-item">
                                                        <b>{{ data_get($frequency, 'label', '-') }}</b>
                                                        <span>{{ $formatInteger(data_get($frequency, 'customers', 0)) }} nasabah</span>
                                                        <strong>{{ $formatAmount(data_get($frequency, 'os', 0)) }} OS</strong>
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
                    @foreach(['pt' => 'Nett Disbursement Mantri PT Only', 'contract' => 'Nett Disbursement Mantri Kontrak'] as $tierKey => $tierTitle)
                        @php $tierBlueprint = (array) data_get($mantriPerformance, 'total.tiers.'.$tierKey.'.buckets', []); @endphp
                        <article class="micro-tier-panel micro-tier-panel--{{ $tierKey }}">
                            <header><i class="fas {{ $tierKey === 'pt' ? 'fa-user-check' : 'fa-id-badge' }}" aria-hidden="true"></i><h4>{{ $tierTitle }}</h4></header>
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
                                            <tr>
                                                <th scope="row">{{ data_get($row, 'branch', '-') }}</th>
                                                @foreach((array) data_get($row, 'tiers.'.$tierKey.'.buckets', []) as $bucket)
                                                    <td class="tone-{{ data_get($bucket, 'key') }}">{{ $formatInteger(data_get($bucket, 'mantri', 0)) }}</td>
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
    @endif

</div>
