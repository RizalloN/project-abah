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

    // Mapping segmen ke subtitle
    $segmentLabels = [
        'CONSUMER' => 'Consumer',
        'SMALL' => 'Small Business',
        'MICRO' => 'Micro Business',
    ];
    $segmentLabel = $segmentLabels[$selectedSegmen] ?? $selectedSegmen;
@endphp

<div id="kinerjaContentArea" class="animate-reveal">
    <div class="px-4 pb-4">
        <div class="kinerja-tabs-shell">
            <div class="kinerja-tabs-header">
                <div class="kinerja-tabs-heading">
                    <p class="kinerja-tabs-kicker">Rincian RM - {{ $segmentLabel }}</p>
                    <h2 class="kinerja-tabs-title">Performance OS dan Kualitas</h2>
                    <p class="kinerja-tabs-subtitle">Pilih panel untuk berpindah antara performance dan kualitas RM tanpa memecah konteks halaman.</p>
                </div>

                <div class="kinerja-tabs-nav" role="tablist" aria-label="Navigasi Kinerja Konsumer">
                    <button type="button" id="kinerja-tab-os" class="kinerja-tab-btn active" data-kinerja-tab="os" role="tab" aria-controls="kinerja-panel-os" aria-selected="true">
                        <span class="kinerja-tab-btn__label">Kinerja OS</span>
                        <span class="kinerja-tab-btn__meta">Outstanding</span>
                    </button>
                    <button type="button" id="kinerja-tab-kualitas" class="kinerja-tab-btn" data-kinerja-tab="kualitas" role="tab" aria-controls="kinerja-panel-kualitas" aria-selected="false">
                        <span class="kinerja-tab-btn__label">Kinerja Kualitas</span>
                        <span class="kinerja-tab-btn__meta">SML dan NPL</span>
                    </button>
                </div>
            </div>

            <div class="kinerja-tabs-body">
                <section id="kinerja-panel-os" class="kinerja-tab-panel is-active" data-kinerja-panel="os" role="tabpanel" aria-labelledby="kinerja-tab-os">
                    @include('report.kinerjarm-table-section', [
                        'sectionTitle' => 'Performance OS Per RM',
                        'sectionSubtitle' => 'Performance RM per branch dan produk.',
                        'sectionMeta' => 'Satuan Akuntansi: Rp, Juta',
                        'comparisonColumns' => $comparisonColumns,
                        'rows' => $rows,
                        'total' => $total,
                        'showTargets' => true,
                        'showTargetColumns' => $selectedSegmen !== 'SMALL',
                        'showAchievementColumns' => true,
                        'showLarColumn' => $selectedSegmen !== 'CONSUMER',
                        'compact' => false,
                        'grandTotalLabel' => 'GRAND TOTAL ' . ($selectedProductLabel === 'Semua Produk' ? 'RM' : strtoupper($selectedProductLabel)),
                        'emptyMessage' => 'Silakan pilih parameter filter yang berbeda.',
                    ])
                </section>

                <section id="kinerja-panel-kualitas" class="kinerja-tab-panel" data-kinerja-panel="kualitas" role="tabpanel" aria-labelledby="kinerja-tab-kualitas">
                    <div class="kinerja-quality-intro">
                        <div>
                            <p class="kinerja-quality-intro__title">Kinerja Kualitas per RM</p>
                            <p class="kinerja-quality-intro__desc">Dua tabel berikut memisahkan kualitas berdasarkan <code>kol_adk1</code>.</p>
                        </div>
                        <div class="kinerja-quality-intro__chips">
                            <span class="kinerja-report-chip">SML = kol_adk1 = 2</span>
                            <span class="kinerja-report-chip">NPL = kol_adk1 &gt; 2</span>
                        </div>
                    </div>

                    <div class="kinerja-quality-stack">
                        @include('report.kinerjarm-table-section', [
                            'sectionTitle' => 'SML',
                            'sectionSubtitle' => 'Filter kualitas: kol_adk1 = 2.',
                            'sectionMeta' => 'Filter: kol_adk1 = 2',
                        'comparisonColumns' => $comparisonColumns,
                        'rows' => $qualityRowsSml,
                        'total' => $qualityTotalSml,
                        'showTargets' => false,
                        'showTargetColumns' => false,
                        'showAchievementColumns' => false,
                        'showLarColumn' => false,
                        'compact' => true,
                        'grandTotalLabel' => 'GRAND TOTAL SML ' . ($selectedProductLabel === 'Semua Produk' ? 'KONSUMER' : strtoupper($selectedProductLabel)),
                        'emptyMessage' => 'Tidak ada data SML untuk kombinasi filter ini.',
                    ])

                        @include('report.kinerjarm-table-section', [
                            'sectionTitle' => 'NPL',
                            'sectionSubtitle' => 'Filter kualitas: kol_adk1 > 2.',
                            'sectionMeta' => 'Filter: kol_adk1 > 2',
                        'comparisonColumns' => $comparisonColumns,
                        'rows' => $qualityRowsNpl,
                        'total' => $qualityTotalNpl,
                        'showTargets' => false,
                        'showTargetColumns' => false,
                        'showAchievementColumns' => false,
                        'showLarColumn' => false,
                        'compact' => true,
                        'grandTotalLabel' => 'GRAND TOTAL NPL ' . ($selectedProductLabel === 'Semua Produk' ? 'KONSUMER' : strtoupper($selectedProductLabel)),
                        'emptyMessage' => 'Tidak ada data NPL untuk kombinasi filter ini.',
                    ])
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
