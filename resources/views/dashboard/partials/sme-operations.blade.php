@php
    $meta = (array) data_get($smeOperations, 'meta', []);
    $quadrants = (array) data_get($smeOperations, 'quadrants', []);
    $realizationTiers = (array) data_get($smeOperations, 'realization_tiers', []);
    $unproductive = (array) data_get($smeOperations, 'unproductive', []);
    $calendarWeek = (array) data_get($meta, 'calendar_week', []);
    $hotProspects = (array) data_get($smeOperations, 'hot_prospects', []);
    $rtlPipeline = (array) data_get($smeOperations, 'rtl_pipeline', []);
    $extension = (array) data_get($smeOperations, 'extension', []);
    $restructuring = (array) data_get($smeOperations, 'restructuring', []);
    $restructuringFrequency = (array) data_get($smeOperations, 'restructuring_frequency', []);
    $restructuringFrequencyBuckets = array_values((array) data_get($restructuringFrequency, 'buckets', []));
    $restructuringFrequencyBucketCount = count($restructuringFrequencyBuckets);
    $restructuringFrequencyColumns = $restructuringFrequencyBucketCount <= 4
        ? max(1, $restructuringFrequencyBucketCount)
        : ($restructuringFrequencyBucketCount === 5
            ? 5
            : ($restructuringFrequencyBucketCount % 3 === 0 ? 3 : 4));
    $kanwilDecisions = (array) data_get($smeOperations, 'kanwil_decisions', []);
    $formatInteger = static fn ($value): string => number_format((int) round((float) $value), 0, ',', '.');
    $formatAmount = static fn ($value): string => number_format((float) $value, 0, ',', '.');
    $formatPercent = static fn ($value): string => number_format((float) $value, 2, ',', '.') . '%';
    $sourceState = static function (array $module): array {
        if (!empty($module['stale'])) {
            return ['label' => 'Cache terakhir', 'class' => 'is-stale', 'icon' => 'fas fa-history'];
        }

        if (empty($module['available'])) {
            return ['label' => 'Belum tersedia', 'class' => 'is-unavailable', 'icon' => 'fas fa-exclamation-circle'];
        }

        return ['label' => 'Google Sheets live', 'class' => 'is-live', 'icon' => 'fas fa-circle'];
    };
    $quadrantIcons = [
        1 => 'fas fa-trophy',
        2 => 'fas fa-chart-line',
        3 => 'fas fa-compass',
        4 => 'fas fa-seedling',
    ];
    $hotIcons = [
        'belum_ots' => 'fas fa-user-clock',
        'analisa_rm' => 'fas fa-search-dollar',
        'verifikasi_adk' => 'fas fa-clipboard-check',
        'menunggu_putusan' => 'fas fa-hourglass-half',
        'sudah_diputus' => 'fas fa-gavel',
        'realisasi' => 'fas fa-check-circle',
        'batal' => 'fas fa-times-circle',
    ];
    $extensionIcons = [
        'analisa_rm' => 'fas fa-search-dollar',
        'menunggu_putusan' => 'fas fa-hourglass-half',
        'sudah_diputus' => 'fas fa-gavel',
        'sudah_diperpanjang' => 'fas fa-check-double',
        'lunas' => 'fas fa-flag-checkered',
        'belum_tl' => 'fas fa-clock',
        'tidak_diperpanjang' => 'fas fa-times-circle',
    ];
    $hotState = $sourceState($hotProspects);
    $rtlState = $sourceState($rtlPipeline);
    $extensionState = $sourceState($extension);
    $restructuringState = $sourceState($restructuring);
    $kanwilDecisionState = $sourceState($kanwilDecisions);
@endphp

<div class="sme-ops" data-sme-operations-ready="1">
    <header class="sme-ops-intro">
        <div class="sme-ops-intro__mark" aria-hidden="true">
            <i class="fas fa-briefcase"></i>
            <span><i class="fas fa-chart-line"></i></span>
        </div>
        <div class="sme-ops-intro__copy">
            <span class="sme-ops-eyebrow">SME ACTION CENTER</span>
            <h2>Pipeline, Kinerja RM, dan Penyelesaian Kredit</h2>
            <p>{{ data_get($meta, 'scope_label', 'AREA 6') }} &middot; data eksternal diperbarui maksimal setiap 5 menit</p>
        </div>
        <div class="sme-ops-intro__actions">
            <span class="sme-ops-week-badge">
                <i class="far fa-calendar-check"></i>
                <span>{{ data_get($calendarWeek, 'label', 'Week -') }}</span>
                <strong>{{ data_get($calendarWeek, 'range_label', '-') }}</strong>
            </span>
            <span class="sme-ops-live-label"><i class="fas fa-circle"></i>Sumber terhubung</span>
            <button type="button" class="sme-ops-refresh" data-sme-operations-refresh>
                <i class="fas fa-sync-alt" aria-hidden="true"></i>
                <span>Perbarui Data</span>
            </button>
        </div>
    </header>

    <section class="sme-ops-feature sme-ops-feature--quadrant" aria-labelledby="sme-quadrant-title">
        <div class="sme-ops-rm-visual">
            <div class="sme-ops-section-heading sme-ops-section-heading--light">
                <span class="sme-ops-section-number">01</span>
                <div>
                    <span class="sme-ops-kicker">Kinerja RM Ritel &middot; Small</span>
                    <h3 id="sme-quadrant-title">Kuadran RM</h3>
                    <p>Pemetaan produktivitas dan kualitas portofolio RM pada posisi aktif.</p>
                </div>
            </div>
            <img src="{{ asset('images/sme-rm-team.webp') }}"
                 class="sme-ops-rm-illustration"
                 width="640"
                 height="714"
                 loading="lazy"
                 decoding="async"
                 alt="Ilustrasi Relationship Manager perbankan memantau kinerja SME">
            <div class="sme-ops-rm-total">
                <span>Total RM Terpetakan</span>
                <strong>{{ $formatInteger(data_get($quadrants, 'total_rm', 0)) }}</strong>
                <small><i class="far fa-calendar-alt"></i>{{ data_get($quadrants, 'period_label', '-') }}</small>
            </div>
        </div>

        <div class="sme-ops-rm-content">
            @if(!empty($quadrants['available']))
                <div class="sme-ops-quadrant-summary" aria-label="Ringkasan kuadran RM">
                    @foreach([1, 2, 3, 4] as $quadrant)
                        <article class="sme-ops-q-summary q{{ $quadrant }}">
                            <span class="sme-ops-q-summary__icon"><i class="{{ data_get($quadrantIcons, $quadrant) }}"></i></span>
                            <div>
                                <span>Kuadran {{ $quadrant }}</span>
                                <strong>{{ $formatInteger(data_get($quadrants, 'totals.' . $quadrant . '.count', 0)) }}</strong>
                                <small>{{ $formatPercent(data_get($quadrants, 'totals.' . $quadrant . '.percentage', 0)) }} dari total RM</small>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if(data_get($quadrants, 'mode') === 'branch')
                    <div class="sme-ops-rm-list" role="list" aria-label="Daftar RM menurut kuadran">
                        @foreach((array) data_get($quadrants, 'rms', []) as $index => $rm)
                            <div class="sme-ops-rm-row" role="listitem">
                                <span class="sme-ops-rm-row__number">{{ $index + 1 }}</span>
                                <div class="sme-ops-rm-row__identity">
                                    <strong>{{ data_get($rm, 'rm', '-') }}</strong>
                                    <span>{{ data_get($rm, 'unit_code', '-') }} &middot; {{ data_get($rm, 'unit', '-') }}</span>
                                </div>
                                <dl class="sme-ops-rm-row__metrics" aria-label="Kinerja bulan berjalan">
                                    <div>
                                        <dt>Deb</dt>
                                        <dd>{{ $formatInteger(data_get($rm, 'realization_deb', 0)) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Real (Rp Jt)</dt>
                                        <dd>{{ $formatAmount(((float) data_get($rm, 'realization_rp', 0)) / 1_000_000) }}</dd>
                                    </div>
                                    <div>
                                        <dt>% LAR</dt>
                                        <dd>{{ $formatPercent(data_get($rm, 'lar_pct', 0)) }}</dd>
                                    </div>
                                </dl>
                                <span class="sme-ops-q-badge q{{ data_get($rm, 'quadrant', 4) }}">Kuadran {{ data_get($rm, 'quadrant', '-') }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="sme-ops-branch-list" role="list" aria-label="Kuadran RM per cabang">
                        @foreach((array) data_get($quadrants, 'branches', []) as $branch)
                            <article class="sme-ops-branch-row" role="listitem">
                                <div class="sme-ops-branch-row__name">
                                    <span class="sme-ops-branch-row__icon"><i class="fas fa-building"></i></span>
                                    <div>
                                        <strong>{{ data_get($branch, 'branch', '-') }}</strong>
                                        <span>{{ $formatInteger(data_get($branch, 'total_rm', 0)) }} RM terpetakan</span>
                                    </div>
                                </div>
                                <div class="sme-ops-branch-row__quadrants">
                                    @foreach([1, 2, 3, 4] as $quadrant)
                                        <div class="sme-ops-branch-q q{{ $quadrant }}">
                                            <span>Kuadran {{ $quadrant }}</span>
                                            <strong>{{ $formatInteger(data_get($branch, 'quadrants.' . $quadrant . '.count', 0)) }}</strong>
                                            <small>{{ $formatPercent(data_get($branch, 'quadrants.' . $quadrant . '.percentage', 0)) }}</small>
                                        </div>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="sme-ops-empty"><i class="fas fa-chart-pie"></i>Data kuadran RM belum tersedia untuk lingkup ini.</div>
            @endif
        </div>
    </section>

    <section class="sme-ops-feature sme-ops-feature--tiers" aria-labelledby="sme-tier-title">
        <div class="sme-ops-feature-head">
            <div class="sme-ops-section-heading">
                <span class="sme-ops-section-number">02</span>
                <span class="sme-ops-feature-icon"><i class="fas fa-layer-group"></i></span>
                <div>
                    <span class="sme-ops-kicker">Realisasi s.d. {{ data_get($realizationTiers, 'period_label', '-') }}</span>
                    <h3 id="sme-tier-title">Sebaran Realisasi RM per Cabang</h3>
                </div>
            </div>
            <div class="sme-ops-tier-total">
                <span>RM Terukur</span>
                <strong>{{ $formatInteger(data_get($realizationTiers, 'total_rm', 0)) }}</strong>
            </div>
        </div>

        @if(!empty($realizationTiers['available']))
            <div class="sme-ops-tier-table-wrap">
                <table class="sme-ops-tier-table">
                    <thead>
                        <tr>
                            <th>Branch Office</th>
                            @foreach(['zero', 'lt_500', '500_1000', '1000_1600', 'gte_1600'] as $tierKey)
                                <th>{{ data_get($realizationTiers, 'totals.' . $tierKey . '.label', '-') }}</th>
                            @endforeach
                            <th>Total RM</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach((array) data_get($realizationTiers, 'branches', []) as $branch)
                            <tr>
                                <th scope="row"><i class="fas fa-building"></i>{{ data_get($branch, 'branch', '-') }}</th>
                                @foreach(['zero', 'lt_500', '500_1000', '1000_1600', 'gte_1600'] as $tierKey)
                                    @php
                                        $tierMetric = (array) data_get($branch, 'tiers.' . $tierKey, []);
                                        $tierDetail = [
                                            'title' => 'Sebaran Realisasi RM',
                                            'scope' => data_get($branch, 'branch', '-'),
                                            'metric' => data_get($tierMetric, 'label', '-'),
                                            'rows' => collect((array) data_get($tierMetric, 'rms', []))
                                                ->map(fn (array $rm): array => array_merge($rm, ['branch' => data_get($branch, 'branch', '-')]))
                                                ->values()
                                                ->all(),
                                        ];
                                    @endphp
                                    <td>
                                        <button type="button"
                                                class="sme-ops-tier-cell"
                                                title="Klik dua kali untuk melihat nama RM"
                                                data-sme-unproductive-detail="{{ json_encode($tierDetail, JSON_UNESCAPED_UNICODE) }}">
                                            <strong>{{ $formatInteger(data_get($tierMetric, 'rm_count', 0)) }} RM</strong>
                                            <span>{{ $formatAmount(((float) data_get($tierMetric, 'amount', 0)) / 1_000_000) }} jt</span>
                                        </button>
                                    </td>
                                @endforeach
                                <td class="is-total">{{ $formatInteger(data_get($branch, 'total_rm', 0)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            @foreach(['zero', 'lt_500', '500_1000', '1000_1600', 'gte_1600'] as $tierKey)
                                <td>
                                    <strong class="text-white">{{ $formatInteger(data_get($realizationTiers, 'totals.' . $tierKey . '.rm_count', 0)) }} RM</strong>
                                    <span class="text-white">{{ $formatPercent(data_get($realizationTiers, 'totals.' . $tierKey . '.percentage', 0)) }}</span>
                                </td>
                            @endforeach
                            <td class="is-total text-white">{{ $formatInteger(data_get($realizationTiers, 'total_rm', 0)) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="sme-ops-empty"><i class="fas fa-layer-group"></i>Data realisasi bulan closing belum tersedia.</div>
        @endif
    </section>

    <section class="sme-ops-feature sme-ops-feature--hot" aria-labelledby="sme-hot-title">
        <div class="sme-ops-feature-head">
            <div class="sme-ops-section-heading">
                <span class="sme-ops-section-number">03</span>
                <span class="sme-ops-feature-icon"><i class="fas fa-fire-alt"></i></span>
                <div>
                    <span class="sme-ops-kicker">{{ $formatInteger(data_get($hotProspects, 'rm_count', 0)) }} RM &middot; {{ $formatInteger(data_get($hotProspects, 'unit_count', 0)) }} unit kerja</span>
                    <h3 id="sme-hot-title">Monitoring Hot Prospek</h3>
                    <p>Posisi calon debitur pada setiap tahap proses kredit.</p>
                </div>
            </div>
            <a class="sme-ops-source {{ $hotState['class'] }}" href="{{ data_get($hotProspects, 'source_url', '#') }}" target="_blank" rel="noopener noreferrer">
                <i class="{{ $hotState['icon'] }}"></i>{{ $hotState['label'] }}<i class="fas fa-external-link-alt"></i>
            </a>
        </div>

        @if(!empty($hotProspects['available']))
            <div class="sme-ops-status-grid">
                @foreach((array) data_get($hotProspects, 'statuses', []) as $status)
                    <article class="sme-ops-status-item" data-status="{{ data_get($status, 'key') }}">
                        <div class="sme-ops-status-item__head">
                            <span class="sme-ops-status-item__icon"><i class="{{ data_get($hotIcons, data_get($status, 'key'), 'fas fa-circle') }}"></i></span>
                            <h4>{{ data_get($status, 'label', '-') }}</h4>
                        </div>
                        <div class="sme-ops-data-pair">
                            <div>
                                <span>Debitur</span>
                                <strong>{{ $formatInteger(data_get($status, 'deb', 0)) }}</strong>
                            </div>
                            <div>
                                <span>Rp Juta</span>
                                <strong>{{ $formatAmount(data_get($status, 'amount_juta', 0)) }}</strong>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="sme-ops-empty"><i class="fas fa-cloud-download-alt"></i>Data Hot Prospek belum tersedia untuk lingkup ini.</div>
        @endif
    </section>

    <section class="sme-ops-feature sme-ops-feature--rtl" aria-labelledby="sme-rtl-title">
        <div class="sme-ops-feature-head">
            <div class="sme-ops-section-heading">
                <span class="sme-ops-section-number">04</span>
                <span class="sme-ops-feature-icon"><i class="fas fa-project-diagram"></i></span>
                <div>
                    <span class="sme-ops-kicker">{{ $formatInteger(data_get($rtlPipeline, 'unit_count', 0)) }} unit kerja terpantau</span>
                    <h3 id="sme-rtl-title">RTL Pipeline per Vendor</h3>
                    <p>Seluruh angka menunjukkan jumlah nasabah: terdata, sudah OTS, dan berminat pembiayaan.</p>
                </div>
            </div>
            <a class="sme-ops-source {{ $rtlState['class'] }}" href="{{ data_get($rtlPipeline, 'source_url', '#') }}" target="_blank" rel="noopener noreferrer">
                <i class="{{ $rtlState['icon'] }}"></i>{{ $rtlState['label'] }}<i class="fas fa-external-link-alt"></i>
            </a>
        </div>

        <div class="sme-ops-rtl-totals" aria-label="Total RTL Pipeline">
            <div><span>Total Pipeline</span><strong>{{ $formatInteger(data_get($rtlPipeline, 'total_pipeline', 0)) }}</strong></div>
            <div><span>Sudah OTS</span><strong>{{ $formatInteger(data_get($rtlPipeline, 'total_ots', 0)) }}</strong></div>
            <div><span>Berminat</span><strong>{{ $formatInteger(data_get($rtlPipeline, 'total_interested', 0)) }}</strong></div>
        </div>

        @if(!empty($rtlPipeline['available']))
            <div class="sme-ops-vendor-grid">
                @foreach((array) data_get($rtlPipeline, 'vendors', []) as $vendor)
                    <article class="sme-ops-vendor-item tone-{{ ($loop->index % 4) + 1 }}"
                             role="button"
                             tabindex="0"
                             data-sme-vendor-detail='@json($vendor)'
                             aria-label="Buka nominatif {{ data_get($vendor, 'label', 'vendor') }}">
                        <div class="sme-ops-vendor-item__identity">
                            <span class="sme-ops-vendor-item__icon"><i class="{{ data_get($vendor, 'icon', 'fas fa-briefcase') }}"></i></span>
                            <h4>{{ data_get($vendor, 'label', '-') }}</h4>
                        </div>
                        <div class="sme-ops-vendor-metrics">
                            <div><span>Pipeline</span><strong>{{ $formatInteger(data_get($vendor, 'pipeline', 0)) }}</strong></div>
                            <div><span>OTS</span><strong>{{ $formatInteger(data_get($vendor, 'ots', 0)) }}</strong></div>
                            <div><span>Minat</span><strong>{{ $formatInteger(data_get($vendor, 'interested', 0)) }}</strong></div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="sme-ops-empty"><i class="fas fa-project-diagram"></i>Data RTL Pipeline belum tersedia untuk lingkup ini.</div>
        @endif
    </section>

    <div class="sme-ops-two-column">
        <section class="sme-ops-feature sme-ops-feature--extension" aria-labelledby="sme-extension-title">
            <div class="sme-ops-feature-head">
                <div class="sme-ops-section-heading">
                    <span class="sme-ops-section-number">05</span>
                    <span class="sme-ops-feature-icon"><i class="fas fa-file-signature"></i></span>
                    <div>
                        <span class="sme-ops-kicker">Monitoring {{ data_get($extension, 'period_label', 'periode terbaru') }}</span>
                        <h3 id="sme-extension-title">Perpanjangan</h3>
                        <p>Kedisiplinan pengisian dan progres penyelesaian nominatif.</p>
                    </div>
                </div>
                <a class="sme-ops-source sme-ops-source--icon {{ $extensionState['class'] }}" href="{{ data_get($extension, 'source_url', '#') }}" target="_blank" rel="noopener noreferrer" aria-label="Buka sumber Perpanjangan di Google Sheets" title="{{ $extensionState['label'] }}">
                    <i class="{{ $extensionState['icon'] }}"></i><i class="fas fa-external-link-alt"></i>
                </a>
            </div>

            @if(!empty($extension['available']))
                @php
                    $attendancePercentage = min(100, max(0, (float) data_get($extension, 'attendance.percentage', 0)));
                @endphp
                <div class="sme-ops-extension-layout">
                    <div class="sme-ops-attendance-ring" style="--attendance: {{ $attendancePercentage }}%;">
                        <div>
                            <strong>{{ $formatPercent($attendancePercentage) }}</strong>
                            <span>Sudah Isi</span>
                        </div>
                    </div>
                    <div class="sme-ops-attendance-copy">
                        <span>Absen Pengisian</span>
                        <strong>{{ $formatInteger(data_get($extension, 'attendance.filled', 0)) }} selesai</strong>
                        <small>{{ $formatInteger(data_get($extension, 'attendance.missing', 0)) }} belum mengisi</small>
                    </div>
                </div>
                <div class="sme-ops-process-list" aria-label="Progress Perpanjangan {{ data_get($extension, 'period_label', 'periode terbaru') }}">
                    @foreach((array) data_get($extension, 'statuses', []) as $status)
                        <div class="sme-ops-process-row">
                            <span class="sme-ops-process-row__icon"><i class="{{ data_get($extensionIcons, data_get($status, 'key'), 'fas fa-circle') }}"></i></span>
                            <span class="sme-ops-process-row__label">{{ data_get($status, 'label', '-') }}</span>
                            <div class="sme-ops-process-row__metrics">
                                <span><strong>{{ $formatInteger(data_get($status, 'deb', 0)) }}</strong> deb</span>
                                <span><strong>{{ $formatAmount(data_get($status, 'amount_juta', 0)) }}</strong> Rp Juta</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="sme-ops-empty"><i class="fas fa-calendar-times"></i>Data perpanjangan belum tersedia untuk lingkup ini.</div>
            @endif
        </section>

        <section class="sme-ops-feature sme-ops-feature--restructuring" aria-labelledby="sme-restructuring-title">
            <div class="sme-ops-feature-head">
                <div class="sme-ops-section-heading">
                    <span class="sme-ops-section-number">06</span>
                    <span class="sme-ops-feature-icon"><i class="fas fa-file-medical-alt"></i></span>
                    <div>
                        <span class="sme-ops-kicker">Paket Restrukturisasi</span>
                        <h3 id="sme-restructuring-title">Pipeline Restruk</h3>
                        <p>Alur pengerjaan paket restrukturisasi sampai tahap putusan.</p>
                    </div>
                </div>
                <a class="sme-ops-source sme-ops-source--icon {{ $restructuringState['class'] }}" href="{{ data_get($restructuring, 'source_url', '#') }}" target="_blank" rel="noopener noreferrer" aria-label="Buka sumber Pipeline Restruk di Google Sheets" title="{{ $restructuringState['label'] }}">
                    <i class="{{ $restructuringState['icon'] }}"></i><i class="fas fa-external-link-alt"></i>
                </a>
            </div>

            @if(!empty($restructuring['available']))
                <div class="sme-ops-restruct-timeline">
                    @foreach((array) data_get($restructuring, 'statuses', []) as $status)
                        @php
                            $deb = (int) data_get($status, 'deb', 0);
                            $resolved = (int) data_get($status, 'resolved_deb', 0);
                            $amountAvailable = (bool) data_get($status, 'amount_available', false);
                        @endphp
                        <article class="sme-ops-restruct-step">
                            <span class="sme-ops-restruct-step__number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="sme-ops-restruct-step__body">
                                <div>
                                    <h4>{{ data_get($status, 'label', '-') }}</h4>
                                    <small>{{ $resolved }} dari {{ $deb }} baris mencantumkan nominal di Sheets</small>
                                </div>
                                <div class="sme-ops-data-pair">
                                    <div><span>Debitur</span><strong>{{ $formatInteger($deb) }}</strong></div>
                                    <div><span>Rp Juta</span><strong>{{ $amountAvailable ? $formatAmount(data_get($status, 'amount_juta', 0)) : '-' }}</strong></div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="sme-ops-empty"><i class="fas fa-file-medical-alt"></i>Data pipeline restruk belum tersedia untuk lingkup ini.</div>
            @endif

            <div class="sme-ops-kanwil-head">
                    <div>
                        <span class="sme-ops-kicker">Putusan Regional Office</span>
                        <h4>Putusan Pipeline Restruk Kanwil</h4>
                    </div>
                    <a class="sme-ops-source sme-ops-source--icon {{ $kanwilDecisionState['class'] }}" href="{{ data_get($kanwilDecisions, 'source_url', '#') }}" target="_blank" rel="noopener noreferrer" aria-label="Buka sumber Putusan Pipeline Restruk Kanwil" title="{{ $kanwilDecisionState['label'] }}">
                        <i class="{{ $kanwilDecisionState['icon'] }}"></i><i class="fas fa-external-link-alt"></i>
                    </a>
            </div>
            @if(!empty($kanwilDecisions['available']))
                    <div class="sme-ops-kanwil-summary">
                        <div><span>Total Pipeline</span><strong>{{ $formatInteger(data_get($kanwilDecisions, 'record_count', 0)) }}</strong></div>
                        <div><span>Terkirim</span><strong>{{ $formatInteger(data_get($kanwilDecisions, 'sent_count', 0)) }}</strong></div>
                        <div><span>Diputus</span><strong>{{ $formatInteger(data_get($kanwilDecisions, 'decided_count', 0)) }}</strong></div>
                        <div><span>DIO Tersedia</span><strong>{{ $formatInteger(data_get($kanwilDecisions, 'dio_count', 0)) }}</strong></div>
                    </div>
                    <div class="sme-ops-kanwil-table-wrap">
                        <table class="sme-ops-kanwil-table">
                            <thead>
                                <tr><th>Cabang</th><th>Total</th><th>Terkirim</th><th>Diputus</th><th>DIO</th><th>Word PTK</th></tr>
                            </thead>
                            <tbody>
                                @foreach((array) data_get($kanwilDecisions, 'branches', []) as $branch)
                                    <tr>
                                        <th scope="row">{{ data_get($branch, 'branch', '-') }}</th>
                                        <td>{{ $formatInteger(data_get($branch, 'total', 0)) }}</td>
                                        <td>{{ $formatInteger(data_get($branch, 'sent', 0)) }}</td>
                                        <td>{{ $formatInteger(data_get($branch, 'decided', 0)) }}</td>
                                        <td>{{ $formatInteger(data_get($branch, 'dio', 0)) }}</td>
                                        <td>{{ $formatInteger(data_get($branch, 'word_ptk', 0)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
            @else
                <div class="sme-ops-empty"><i class="fas fa-gavel"></i>Data putusan restruk Kanwil belum tersedia untuk lingkup ini.</div>
            @endif
        </section>
    </div>

    <section class="sme-ops-feature sme-ops-feature--frequency" aria-labelledby="sme-restructuring-frequency-title">
        <div class="sme-ops-feature-head">
            <div class="sme-ops-section-heading">
                <span class="sme-ops-section-number">07</span>
                <span class="sme-ops-feature-icon"><i class="fas fa-history"></i></span>
                <div>
                    <span class="sme-ops-kicker">Posisi {{ data_get($restructuringFrequency, 'period_label', '-') }} &middot; {{ data_get($restructuringFrequency, 'scope_label', data_get($meta, 'scope_label', 'Area 6')) }}</span>
                    <h3 id="sme-restructuring-frequency-title">Frekuensi Restrukturisasi Debitur</h3>
                    <p>Distribusi debitur dan outstanding berdasarkan jumlah restrukturisasi yang pernah tercatat.</p>
                </div>
            </div>
            <span class="sme-ops-data-source" title="Sumber posisi Daily Loan Dinamis">
                <i class="fas fa-database" aria-hidden="true"></i>
                <span>{{ data_get($restructuringFrequency, 'source', 'Daily Loan Dinamis') }}</span>
            </span>
        </div>

        @if(!empty($restructuringFrequency['available']) && $restructuringFrequencyBucketCount > 0)
            <div class="sme-ops-frequency-summary" aria-label="Ringkasan restrukturisasi SME">
                <div>
                    <span>Total Debitur Unik</span>
                    <strong>{{ $formatInteger(data_get($restructuringFrequency, 'total_debtors', 0)) }} <small>debitur</small></strong>
                </div>
                <div>
                    <span>Total Outstanding</span>
                    <strong>Rp {{ $formatAmount(data_get($restructuringFrequency, 'total_os_juta', 0)) }} <small>juta</small></strong>
                </div>
            </div>

            <div class="sme-ops-frequency-grid" role="list" aria-label="Frekuensi restrukturisasi debitur SME" style="--sme-frequency-columns: {{ $restructuringFrequencyColumns }}">
                @foreach($restructuringFrequencyBuckets as $bucket)
                    @php
                        $frequency = max(1, (int) data_get($bucket, 'frequency', 1));
                    @endphp
                    <article class="sme-ops-frequency-item tone-{{ (($loop->index % 4) + 1) }}" role="listitem" aria-label="Restruk {{ $frequency }} kali, {{ $formatInteger(data_get($bucket, 'debtors', 0)) }} debitur, outstanding Rp {{ $formatAmount(data_get($bucket, 'os_juta', 0)) }} juta">
                        <div class="sme-ops-frequency-identity">
                            <span class="sme-ops-frequency-badge">{{ $frequency }}x</span>
                            <div>
                                <small>Frekuensi</small>
                                <h4>{{ data_get($bucket, 'label', 'Restruk ' . $frequency . ' kali') }}</h4>
                            </div>
                        </div>
                        <div class="sme-ops-frequency-metrics">
                            <div>
                                <span>Debitur</span>
                                <strong>{{ $formatInteger(data_get($bucket, 'debtors', 0)) }}</strong>
                                <small>CIF unik</small>
                            </div>
                            <div>
                                <span>Outstanding</span>
                                <strong>Rp {{ $formatAmount(data_get($bucket, 'os_juta', 0)) }}</strong>
                                <small>Juta</small>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <p class="sme-ops-frequency-note">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                Debitur dihitung unik per frekuensi berdasarkan CIF; outstanding menjumlahkan baki debet rekening pada frekuensi yang sama.
            </p>
        @else
            <div class="sme-ops-empty"><i class="fas fa-history"></i>Data frekuensi restrukturisasi SME belum tersedia pada posisi dan wilayah ini.</div>
        @endif
    </section>

    <section class="sme-ops-feature sme-ops-feature--inactive" aria-labelledby="sme-inactive-title">
        <div class="sme-ops-feature-head">
            <div class="sme-ops-section-heading">
                <span class="sme-ops-section-number">08</span>
                <span class="sme-ops-feature-icon"><i class="fas fa-user-clock"></i></span>
                <div>
                    <span class="sme-ops-kicker">Posisi Closing {{ data_get($unproductive, 'period_label', '-') }}</span>
                    <h3 id="sme-inactive-title">RM Tidak Produktif</h3>
                </div>
            </div>
        </div>

        @if(!empty($unproductive['available']))
            <div class="sme-ops-inactive-grid">
                @foreach(['month_1', 'month_3', 'month_6'] as $metricKey)
                    @php
                        $inactiveMetric = (array) data_get($unproductive, 'totals.' . $metricKey, []);
                        $inactiveDetail = [
                            'scope' => data_get($meta, 'scope_label', 'Area 6'),
                            'metric' => data_get($inactiveMetric, 'label', '-'),
                            'rows' => array_values((array) data_get($inactiveMetric, 'rms', [])),
                        ];
                    @endphp
                    <article class="sme-ops-inactive-card tone-{{ $loop->iteration }}"
                             tabindex="0"
                             title="Klik dua kali untuk melihat daftar RM"
                             data-sme-unproductive-detail="{{ json_encode($inactiveDetail, JSON_UNESCAPED_UNICODE) }}">
                        <span><i class="fas fa-hourglass-half"></i>{{ data_get($inactiveMetric, 'label', '-') }}</span>
                        <strong>{{ $formatInteger(data_get($inactiveMetric, 'count', 0)) }} <small>RM</small></strong>
                        <div class="sme-ops-inactive-meter"><span style="width: {{ min(100, max(0, (float) data_get($inactiveMetric, 'percentage', 0))) }}%"></span></div>
                        <small>{{ $formatPercent(data_get($inactiveMetric, 'percentage', 0)) }} dari RM terpantau</small>
                        <button type="button" class="sme-ops-inactive-open" data-sme-unproductive-open aria-label="Lihat daftar {{ data_get($inactiveMetric, 'label', '-') }}">
                            <i class="fas fa-users" aria-hidden="true"></i><span>Detail RM</span>
                        </button>
                    </article>
                @endforeach
            </div>

            @if(data_get($meta, 'scope') === 'area6')
                <div class="sme-ops-inactive-table-wrap">
                    <table class="sme-ops-inactive-table">
                        <thead><tr><th>Branch Office</th><th>1 Bulan</th><th>3 Bulan</th><th>6 Bulan</th><th>RM Terpantau</th></tr></thead>
                        <tbody>
                            @foreach((array) data_get($unproductive, 'branches', []) as $branch)
                                <tr>
                                    <th scope="row">{{ data_get($branch, 'branch', '-') }}</th>
                                    @foreach(['month_1', 'month_3', 'month_6'] as $metricKey)
                                        @php
                                            $branchMetric = (array) data_get($branch, 'metrics.' . $metricKey, []);
                                            $branchDetail = [
                                                'scope' => data_get($branch, 'branch', '-'),
                                                'metric' => data_get($branchMetric, 'label', '-'),
                                                'rows' => collect((array) data_get($branchMetric, 'rms', []))
                                                    ->map(fn (array $rm): array => array_merge($rm, ['branch' => data_get($branch, 'branch', '-')]))
                                                    ->values()
                                                    ->all(),
                                            ];
                                        @endphp
                                        <td>
                                            <button type="button"
                                                    class="sme-ops-inactive-cell"
                                                    title="Klik dua kali untuk melihat daftar RM"
                                                    data-sme-unproductive-detail="{{ json_encode($branchDetail, JSON_UNESCAPED_UNICODE) }}">
                                                {{ $formatInteger(data_get($branchMetric, 'count', 0)) }}
                                            </button>
                                        </td>
                                    @endforeach
                                    <td>{{ $formatInteger(data_get($branch, 'total_rm', 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            <div class="sme-ops-empty"><i class="fas fa-user-clock"></i>Histori bulan closing belum cukup untuk mengukur produktivitas RM.</div>
        @endif
    </section>

    <div class="sme-vendor-modal" data-sme-vendor-modal hidden>
        <button type="button" class="sme-vendor-modal__backdrop" data-sme-vendor-close tabindex="-1" aria-label="Tutup rincian vendor"></button>
        <section class="sme-vendor-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sme-vendor-modal-title">
            <header>
                <div>
                    <span>RTL Pipeline Area 6</span>
                    <h3 id="sme-vendor-modal-title" data-sme-vendor-modal-title>Detail Vendor</h3>
                </div>
                <button type="button" class="sme-vendor-modal__close" data-sme-vendor-close aria-label="Tutup modal"><i class="fas fa-times"></i></button>
            </header>
            <div class="sme-vendor-modal__summary" data-sme-vendor-modal-summary></div>
            <div class="sme-vendor-modal__toolbar">
                <label>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <span class="sr-only">Cari nominatif vendor</span>
                    <input type="search" data-sme-vendor-search placeholder="Cari nasabah, uker, RM, atau status..." autocomplete="off">
                </label>
                <strong data-sme-vendor-result-count>0 baris</strong>
            </div>
            <div class="sme-vendor-modal__table-wrap">
                <table aria-label="Nominatif RTL Pipeline vendor">
                    <thead><tr data-sme-vendor-modal-head></tr></thead>
                    <tbody data-sme-vendor-modal-body></tbody>
                </table>
            </div>
            <footer>
                <span>Nominatif difilter sesuai wilayah aktif.</span>
                <a href="#" target="_blank" rel="noopener noreferrer" data-sme-vendor-modal-link>Buka sheet vendor<i class="fas fa-external-link-alt"></i></a>
            </footer>
        </section>
    </div>

    <div class="sme-vendor-modal sme-unproductive-modal" data-sme-unproductive-modal hidden>
        <button type="button" class="sme-vendor-modal__backdrop" data-sme-unproductive-close tabindex="-1" aria-label="Tutup detail RM"></button>
        <section class="sme-vendor-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sme-unproductive-modal-title">
            <header>
                <div>
                    <span>Monitoring RM SME</span>
                    <h3 id="sme-unproductive-modal-title" data-sme-unproductive-modal-title>Detail RM</h3>
                </div>
                <button type="button" class="sme-vendor-modal__close" data-sme-unproductive-close aria-label="Tutup modal"><i class="fas fa-times"></i></button>
            </header>
            <div class="sme-vendor-modal__summary" data-sme-unproductive-modal-summary></div>
            <div class="sme-vendor-modal__toolbar">
                <label>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <span class="sr-only">Cari detail RM</span>
                    <input type="search" data-sme-unproductive-search placeholder="Cari cabang, kode uker, unit, atau nama RM..." autocomplete="off">
                </label>
                <strong data-sme-unproductive-result-count>0 RM</strong>
            </div>
            <div class="sme-vendor-modal__table-wrap">
                <table aria-label="Daftar RM SME">
                    <thead><tr><th>No</th><th>Cabang</th><th>Kode Uker</th><th>Unit Kerja</th><th>Nama RM</th><th data-sme-rm-realization-head hidden>Realisasi (Rp Juta)</th></tr></thead>
                    <tbody data-sme-unproductive-modal-body></tbody>
                </table>
            </div>
            <footer>
                <span>Daftar mengikuti periode closing dan wilayah yang sedang aktif.</span>
            </footer>
        </section>
    </div>

    @if(!empty($hotProspects['stale']) || !empty($rtlPipeline['stale']) || !empty($extension['stale']) || !empty($restructuring['stale']) || !empty($kanwilDecisions['stale']))
        <div class="sme-ops-cache-note"><i class="fas fa-info-circle"></i>Sebagian sumber sedang tidak dapat dijangkau. Dashboard menampilkan sinkronisasi terakhir yang berhasil.</div>
    @endif
</div>
