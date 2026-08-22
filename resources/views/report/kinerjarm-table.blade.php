@php
    $normalize = fn($v) => (float) $v / 1000000;
    $formatAmount = $formatAmount ?? fn ($value, int $decimals = 1) => number_format($normalize($value), $decimals, ',', '.');
    $formatSignedAmount = $formatSignedAmount ?? function ($value, bool $showArrow = true, int $decimals = 1) use ($normalize) {
        $val = $normalize($value);
        $cls = $val > 0 ? 'pos' : ($val < 0 ? 'neg' : '');
        $icon = '';

        if ($showArrow) {
            if ($val > 0) {
                $icon = '<i class="fas fa-caret-up me-1"></i>';
            } elseif ($val < 0) {
                $icon = '<i class="fas fa-caret-down me-1"></i>';
            }
        }

        $prefix = ($val > 0 && !$showArrow) ? '+' : '';
        $display = number_format(abs($val), $decimals, ',', '.');
        if ($val < 0 && !$showArrow) {
            $display = '-' . $display;
        }

        return "<span class='delta-indicator {$cls}'>{$icon}{$prefix}{$display}</span>";
    };
    $formatCount = $formatCount ?? fn ($value) => number_format((int) round((float) $value), 0, ',', '.');

    $segmentLabels = [
        'CONSUMER' => 'Consumer',
        'SMALL' => 'Small Business',
        'MICRO' => 'Micro Business',
    ];
    $segmentLabel = $segmentLabels[$selectedSegmen] ?? $selectedSegmen;
    $qualityDefinitions = [
        'os' => [
            'title' => 'OS',
            'subtitle' => null,
            'component' => 'OS',
            'show_loan_reference' => false,
            'lower_is_better' => false,
        ],
        'lancar' => [
            'title' => 'Lancar',
            'subtitle' => 'Kolektabilitas 1',
            'component' => 'Lancar',
            'show_loan_reference' => true,
            'lower_is_better' => false,
        ],
        'lr' => [
            'title' => 'LR',
            'subtitle' => 'Kolektabilitas 1 · Flag Restruk Y',
            'component' => 'LR',
            'show_loan_reference' => true,
            'lower_is_better' => true,
        ],
        'lnr' => [
            'title' => 'LNR',
            'subtitle' => 'Kolektabilitas 1 · Flag Restruk N',
            'component' => 'LNR',
            'show_loan_reference' => true,
            'lower_is_better' => false,
        ],
        'account_restruk' => [
            'title' => 'Account Restruk',
            'subtitle' => 'Flag Restruk Y · Semua Kolektabilitas',
            'component' => 'Account Restruk',
            'show_loan_reference' => true,
            'lower_is_better' => true,
        ],
        'sml_1' => [
            'title' => 'SML 1',
            'subtitle' => null,
            'component' => 'SML 1',
            'show_loan_reference' => true,
            'lower_is_better' => true,
        ],
        'sml_2' => [
            'title' => 'SML 2',
            'subtitle' => null,
            'component' => 'SML 2',
            'show_loan_reference' => true,
            'lower_is_better' => true,
        ],
        'sml_3' => [
            'title' => 'SML 3',
            'subtitle' => null,
            'component' => 'SML 3',
            'show_loan_reference' => true,
            'lower_is_better' => true,
        ],
        'kl' => [
            'title' => 'KL',
            'subtitle' => null,
            'component' => 'KL',
            'show_loan_reference' => true,
            'lower_is_better' => true,
        ],
        'd1' => [
            'title' => 'D1',
            'subtitle' => null,
            'component' => 'D1',
            'show_loan_reference' => true,
            'lower_is_better' => true,
        ],
        'd2' => [
            'title' => 'D2',
            'subtitle' => null,
            'component' => 'D2',
            'show_loan_reference' => true,
            'lower_is_better' => true,
        ],
        'm' => [
            'title' => 'M',
            'subtitle' => null,
            'component' => 'M',
            'show_loan_reference' => true,
            'lower_is_better' => true,
        ],
    ];
@endphp

<div id="kinerjaContentArea" class="animate-reveal">
    <div class="px-4 pb-4">
        <div class="kinerja-tabs-shell">
            <div class="kinerja-tabs-header">
                <div class="kinerja-tabs-heading">
                    <p class="kinerja-tabs-kicker">{{ $segmentLabel }}</p>
                    <h2 class="kinerja-tabs-title">Performance & Kualitas</h2>
                </div>

                <div class="kinerja-tabs-nav" role="tablist" aria-label="Navigasi Kinerja RM Ritel">
                    <button type="button" id="kinerja-tab-os" class="kinerja-tab-btn active" data-kinerja-tab="os" role="tab" aria-controls="kinerja-panel-os" aria-selected="true">
                        <span class="kinerja-tab-btn__label">Performance</span>
                    </button>
                    <button type="button" id="kinerja-tab-kualitas" class="kinerja-tab-btn" data-kinerja-tab="kualitas" role="tab" aria-controls="kinerja-panel-kualitas" aria-selected="false">
                        <span class="kinerja-tab-btn__label">Kualitas</span>
                    </button>
                </div>
            </div>

            <div class="kinerja-tabs-body">
                <section id="kinerja-panel-os" class="kinerja-tab-panel is-active" data-kinerja-panel="os" role="tabpanel" aria-labelledby="kinerja-tab-os">
                    @include('report.kinerjarm-performance-table-section')
                </section>

                <section id="kinerja-panel-kualitas" class="kinerja-tab-panel" data-kinerja-panel="kualitas" role="tabpanel" aria-labelledby="kinerja-tab-kualitas">
                    <div class="kinerja-quality-intro">
                        <div>
                            <p class="kinerja-quality-intro__title">Kualitas Kredit</p>
                        </div>
                        <div class="kinerja-quality-intro__chips">
                            <span class="kinerja-report-chip">Lancar = kolek 1</span>
                            <span class="kinerja-report-chip">SML 1-3 = kolek 2</span>
                            <span class="kinerja-report-chip">LR / LNR = kolek 1 · flag Y / N</span>
                            <span class="kinerja-report-chip">Account Restruk = seluruh flag Y</span>
                            <span class="kinerja-report-chip">KL = kolek 3</span>
                            <span class="kinerja-report-chip">D1-2 = kolek 4</span>
                            <span class="kinerja-report-chip">M = kolek 5</span>
                        </div>
                    </div>

                    <div class="kinerja-quality-stack">
                        @foreach($qualityDefinitions as $qualityKey => $definition)
                            @php $series = $qualitySeries[$qualityKey] ?? ['rows' => [], 'total' => []]; @endphp
                            @include('report.kinerjarm-quality-series-section', [
                                'sectionTitle' => $definition['title'],
                                'sectionSubtitle' => $definition['subtitle'],
                                'componentLabel' => $definition['component'],
                                'showLoanReference' => $definition['show_loan_reference'],
                                'lowerIsBetter' => $definition['lower_is_better'],
                                'comparisonColumns' => $comparisonColumns,
                                'rows' => $series['rows'],
                                'total' => $series['total'],
                            ])
                        @endforeach
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
