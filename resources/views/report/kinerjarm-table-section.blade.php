@php
    $showTargets = $showTargets ?? true;
    $compact = $compact ?? false;
    $sectionTitle = $sectionTitle ?? 'Performance OS';
    $sectionSubtitle = $sectionSubtitle ?? null;
    $sectionMeta = $sectionMeta ?? null;
    $grandTotalLabel = $grandTotalLabel ?? null;
    $emptyMessage = $emptyMessage ?? 'Silakan pilih parameter filter yang berbeda.';
    $tableClass = 'kinerja-konsumer-table' . ($compact ? ' kinerja-konsumer-table--compact' : '');
    $emptyColspan = $showTargets ? 16 : 12;
    $segmentLabels = [
        'CONSUMER' => 'RM',
        'SMALL' => 'SMALL RM',
        'MICRO' => 'MICRO RM',
    ];
    $segLabel = $segmentLabels[$selectedSegmen] ?? 'RM';
    $grandTotalLabel = $grandTotalLabel ?? ('GRAND TOTAL ' . ($selectedProductLabel === 'Semua Produk' ? $segLabel : strtoupper($selectedProductLabel)));
    $formatAmount = $formatAmount ?? fn ($value, int $decimals = 1) => number_format(((float) $value) / 1000000, $decimals, ',', '.');
    $formatSignedAmount = $formatSignedAmount ?? function ($value, bool $showArrow = true, int $decimals = 1) {
        $amount = ((float) $value) / 1000000;
        $cls = $amount > 0 ? 'pos' : ($amount < 0 ? 'neg' : '');
        $icon = '';

        if ($showArrow) {
            if ($amount > 0) {
                $icon = '<i class="fas fa-caret-up me-1"></i>';
            } elseif ($amount < 0) {
                $icon = '<i class="fas fa-caret-down me-1"></i>';
            }
        }

        $prefix = ($amount > 0 && !$showArrow) ? '+' : '';
        $display = number_format(abs($amount), $decimals, ',', '.');
        if ($amount < 0 && !$showArrow) {
            $display = '-' . $display;
        }

        return "<span class='delta-indicator {$cls}'>{$icon}{$prefix}{$display}</span>";
    };
    $formatCount = $formatCount ?? fn ($value) => number_format((int) round((float) $value), 0, ',', '.');
    $quadrantLabel = $quadrantLabel ?? fn ($quadrant) => in_array((int) $quadrant, [1, 2, 3, 4], true) ? 'Kuadran ' . (int) $quadrant : '-';
    $quadrantClass = $quadrantClass ?? fn ($quadrant) => in_array((int) $quadrant, [1, 2, 3, 4], true) ? 'q' . (int) $quadrant : '';
@endphp

<div class="kinerja-report-card">
    <div class="kinerja-report-card__header">
        <div class="kinerja-report-card__title-wrap">
            <h2 class="kinerja-report-card__title">
                <i class="fas fa-table" style="font-size: 0.78rem; color: var(--loan-blue);"></i>
                {{ $sectionTitle }}
            </h2>
            @if(!empty($sectionSubtitle))
                <p class="kinerja-report-card__subtitle">{{ $sectionSubtitle }}</p>
            @endif
        </div>
        @if(!empty($sectionMeta))
            <div class="kinerja-report-card__meta">
                <i class="fas fa-info-circle" style="color: var(--loan-blue);"></i>
                <span>{{ $sectionMeta }}</span>
            </div>
        @endif
    </div>

    <div class="kinerja-table-container shadow-sm">
        <table class="{{ $tableClass }}">
            <thead>
                <tr>
                    <th rowspan="2" class="sticky-col" style="width: 32px; left: 0;">No.</th>
                    <th rowspan="2" class="sticky-col" style="width: 100px; left: 32px;">Kantor Cabang</th>
                    <th rowspan="2" class="sticky-col" style="width: 150px; left: 132px;">Nama RM / Pengelola</th>
                    <th rowspan="2" style="width: 100px;">Produk</th>
                    <th rowspan="2" style="width: 60px;">Kuadran</th>
                    <th colspan="4" class="sub-head">PERFORMANCE PER RM</th>
                    <th colspan="3" class="accent-head">DELTA PERIODE</th>
                    @if($showTargets)
                        <th colspan="2" class="sub-head">TARGET REALISASI JG</th>
                        <th colspan="2" class="accent-head">PENCAPAIAN REALISASI JG</th>
                    @endif
                </tr>
                <tr>
                    <th class="sub-head" style="width: 75px;">YoY</th>
                    <th class="sub-head" style="width: 75px;">YtD</th>
                    <th class="sub-head" style="width: 75px;">MtD</th>
                    <th class="sub-head" style="width: 80px;">POSISI</th>

                    <th class="accent-head" style="width: 70px;">YoY</th>
                    <th class="accent-head" style="width: 70px;">YtD</th>
                    <th class="accent-head" style="width: 70px;">MtD</th>

                    @if($showTargets)
                        <th class="sub-head" style="width: 50px;">Deb</th>
                        <th class="sub-head" style="width: 80px;">Rp</th>

                        <th class="accent-head" style="width: 60px;">Deb</th>
                        <th class="accent-head" style="width: 85px;">Rp</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @forelse($rows as $branch)
                    <tr class="loan-branch-subtotal">
                        <td rowspan="{{ $branch['branch_rowspan'] }}" class="text-center-important sticky-col" style="font-weight: 800; left: 0; border-right: 1px solid var(--loan-border-strong); background: #ffffff !important; color: var(--loan-blue-ink) !important;">{{ $no++ }}</td>
                        <td rowspan="{{ $branch['branch_rowspan'] }}" class="merged-branch-cell" style="left: 32px;">{{ $branch['cabang'] }}</td>
                        <td colspan="3" class="text-center-important" style="letter-spacing: 0.05em; font-weight: 900; color: var(--loan-cyan);">
                            TOTAL {{ $branch['cabang'] }}
                        </td>
                        <td>{{ $formatAmount($branch['subtotal']['yoy']) }}</td>
                        <td>{{ $formatAmount($branch['subtotal']['ytd']) }}</td>
                        <td>{{ $formatAmount($branch['subtotal']['mtd']) }}</td>
                        <td class="highlight-curr">{{ $formatAmount($branch['subtotal']['curr']) }}</td>
                        <td>{!! $formatSignedAmount($branch['subtotal']['delta_yoy']) !!}</td>
                        <td>{!! $formatSignedAmount($branch['subtotal']['delta_ytd']) !!}</td>
                        <td>{!! $formatSignedAmount($branch['subtotal']['delta_mtd']) !!}</td>
                        @if($showTargets)
                            <td class="text-center-important">{{ $branch['subtotal']['target_jg_deb'] ?: '-' }}</td>
                            <td>{{ $branch['subtotal']['target_jg_os'] > 0 ? $formatAmount($branch['subtotal']['target_jg_os']) : '-' }}</td>
                            <td class="text-center-important">{{ $formatCount($branch['subtotal']['ach_deb'] ?? 0) }}</td>
                            <td>{{ $formatAmount($branch['subtotal']['ach_os'] ?? 0) }}</td>
                        @endif
                    </tr>

                    @foreach($branch['rms'] as $rmName => $rmData)
                        @php
                            if (trim((string) $rmName) === '00385844 -') {
                                $rmName = '00385844 - Glagah Mahestya Yahya';
                            }
                            $isFirstRmRow = true;
                            $isFirstRmRowForQuad = true;
                        @endphp
                        @foreach($rmData['items'] as $item)
                            <tr>
                                @if($isFirstRmRow)
                                    <td rowspan="{{ $rmData['rm_rowspan'] }}" 
                                        class="merged-rm-cell clickable-rm-row" 
                                        data-rm-name="{{ $rmName }}" 
                                        data-segment="{{ $selectedSegmen }}" 
                                        data-period="{{ $selectedPeriod }}"
                                        title="Klik untuk detail rincian"
                                        style="cursor: pointer; transition: all 0.2s; position: relative; left: 132px;">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-info-circle me-1 text-primary" style="font-size: 0.65rem; opacity: 0.6;"></i>
                                            {{ $rmName }}
                                        </div>
                                    </td>
                                    @php $isFirstRmRow = false; @endphp
                                @endif

                                <td class="text-start-important" style="font-weight: 700; color: var(--loan-muted); padding-left: 0.5rem;">
                                    {{ $item['product'] }}
                                </td>
                                @if($isFirstRmRowForQuad)
                                    <td rowspan="{{ $rmData['rm_rowspan'] }}" class="text-center-important">
                                        @if(!empty($rmData['quadrant']))
                                            <span class="quadrant-badge {{ $quadrantClass($rmData['quadrant']) }}">{{ $quadrantLabel($rmData['quadrant']) }}</span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    @php $isFirstRmRowForQuad = false; @endphp
                                @endif
                                <td>{{ $formatAmount($item['yoy']) }}</td>
                                <td>{{ $formatAmount($item['ytd']) }}</td>
                                <td>{{ $formatAmount($item['mtd']) }}</td>
                                <td class="highlight-curr">{{ $formatAmount($item['curr']) }}</td>
                                <td>{!! $formatSignedAmount($item['delta_yoy']) !!}</td>
                                <td>{!! $formatSignedAmount($item['delta_ytd']) !!}</td>
                                <td>{!! $formatSignedAmount($item['delta_mtd']) !!}</td>
                                @if($showTargets)
                                    <td class="text-center-important" style="background: rgba(8, 87, 195, 0.02); font-size: 0.7rem;">{{ $item['target_jg_deb'] ?: '' }}</td>
                                    <td style="background: rgba(8, 87, 195, 0.02);">{{ $item['target_jg_os'] > 0 ? $formatAmount($item['target_jg_os']) : '' }}</td>
                                    <td class="text-center-important">{{ $formatCount($item['ach_deb'] ?? 0) }}</td>
                                    <td>{{ $formatAmount($item['ach_os'] ?? 0) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    @endforeach
                @empty
                    <tr>
                        <td colspan="{{ $emptyColspan }}" class="py-5 text-center text-muted">
                            <i class="fas fa-folder-open fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                            <span style="font-weight: 700; font-size: 0.95rem;">DATA TIDAK DITEMUKAN</span>
                            <p class="small mt-1">{{ $emptyMessage }}</p>
                        </td>
                    </tr>
                @endforelse

                @if(!empty($rows))
                    <tr class="row-grand-total">
                        <td colspan="5" class="text-center-important" style="font-weight: 900; text-transform: uppercase;">
                            {{ $grandTotalLabel }}
                        </td>
                        <td>{{ $formatAmount($total['yoy']) }}</td>
                        <td>{{ $formatAmount($total['ytd']) }}</td>
                        <td>{{ $formatAmount($total['mtd']) }}</td>
                        <td class="highlight-curr">{{ $formatAmount($total['curr']) }}</td>
                        <td>{!! $formatSignedAmount($total['delta_yoy'], false) !!}</td>
                        <td>{!! $formatSignedAmount($total['delta_ytd'], false) !!}</td>
                        <td>{!! $formatSignedAmount($total['delta_mtd'], false) !!}</td>
                        @if($showTargets)
                            <td class="text-center-important">{{ $total['target_jg_deb'] ?: '-' }}</td>
                            <td>{{ $total['target_jg_os'] > 0 ? $formatAmount($total['target_jg_os']) : '-' }}</td>
                            <td class="text-center-important">{{ $formatCount($total['ach_deb'] ?? 0) }}</td>
                            <td>{{ $formatAmount($total['ach_os'] ?? 0) }}</td>
                        @endif
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
