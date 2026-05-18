@php
    $showTargets = $showTargets ?? true;
    $showTargetColumns = $showTargetColumns ?? $showTargets;
    $showAchievementColumns = $showAchievementColumns ?? $showTargets;
    $showLarColumn = $showLarColumn ?? $showTargets;
    if (($selectedSegmen ?? '') === 'CONSUMER') {
        $showLarColumn = false;
    }
    $compact = $compact ?? false;
    $sectionTitle = $sectionTitle ?? 'Performance OS';
    $sectionSubtitle = $sectionSubtitle ?? null;
    $sectionMeta = $sectionMeta ?? null;
    $grandTotalLabel = $grandTotalLabel ?? null;
    $emptyMessage = $emptyMessage ?? 'Silakan pilih parameter filter yang berbeda.';
    $tableClass = 'kinerja-konsumer-table' . ($compact ? ' kinerja-konsumer-table--compact' : '');
    $comparisonColumns = collect($comparisonColumns ?? [
        ['key' => 'ytd', 'label' => $ytdShortLabel ?? '-', 'short_label' => $ytdShortLabel ?? '-'],
        ['key' => 'm4', 'label' => $yoyShortLabel ?? '-', 'short_label' => $yoyShortLabel ?? '-'],
        ['key' => 'm3', 'label' => '-', 'short_label' => '-'],
        ['key' => 'm2', 'label' => '-', 'short_label' => '-'],
        ['key' => 'm1', 'label' => $mtdShortLabel ?? '-', 'short_label' => $mtdShortLabel ?? '-'],
    ])->values();
    $comparisonCount = max(1, $comparisonColumns->count());
    $baseColspan = 5 + $comparisonCount + 1 + $comparisonCount;
    $emptyColspan = $baseColspan
        + ($showTargetColumns ? 2 : 0)
        + ($showAchievementColumns ? 2 : 0)
        + ($showLarColumn ? 1 : 0);
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
    $formatPercent = $formatPercent ?? fn ($value, int $decimals = 1) => number_format((float) $value, $decimals, ',', '.') . '%';
    $quadrantLabel = $quadrantLabel ?? fn ($quadrant) => in_array((int) $quadrant, [1, 2, 3, 4], true) ? 'Kuadran ' . (int) $quadrant : '-';
    $quadrantClass = $quadrantClass ?? fn ($quadrant) => in_array((int) $quadrant, [1, 2, 3, 4], true) ? 'q' . (int) $quadrant : '';
    $achievementHeader = ($selectedSegmen ?? '') === 'CONSUMER' ? 'Plafon Net' : 'Realisasi JG';
    $selectedHeaderLabel = $selectedPeriodShortLabel ?? 'POSISI';
    $valueFor = fn (array $row, string $key): float => (float) data_get($row, "comparison_values.{$key}", data_get($row, $key, 0));
    $deltaFor = fn (array $row, string $key): float => (float) data_get($row, "comparison_deltas.{$key}", (float) ($row['curr'] ?? 0) - $valueFor($row, $key));
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
                    <th rowspan="2" class="sticky-col" style="width: 94px; left: 32px;">Cabang</th>
                    <th rowspan="2" class="sticky-col" style="width: 164px; left: 126px;">RM / Pengelola</th>
                    <th rowspan="2" style="width: 92px;">Produk</th>
                    <th rowspan="2" style="width: 58px;">Kuadran</th>
                    <th colspan="{{ $comparisonCount + 1 }}" class="sub-head">Performance</th>
                    <th colspan="{{ $comparisonCount }}" class="accent-head">Delta</th>
                    @if($showTargetColumns)
                        <th colspan="2" class="sub-head">Target JG</th>
                    @endif
                    @if($showAchievementColumns)
                        <th colspan="2" class="accent-head">{{ $achievementHeader }}</th>
                    @endif
                    @if($showLarColumn)
                        <th rowspan="2" class="accent-head" style="width: 62px;">% LAR</th>
                    @endif
                </tr>
                <tr>
                    @foreach($comparisonColumns as $column)
                        <th class="sub-head kinerja-period-head" style="width: 80px; white-space: nowrap;">
                            <span>{{ $column['short_label'] ?? '-' }}</span>
                        </th>
                    @endforeach
                    <th class="sub-head kinerja-period-head" style="width: 86px; white-space: nowrap;">
                        <span>{{ $selectedHeaderLabel }}</span>
                    </th>

                    @foreach($comparisonColumns as $column)
                        <th class="accent-head" style="width: 68px;">{{ $column['label'] }}</th>
                    @endforeach

                    @if($showTargetColumns)
                        <th class="sub-head" style="width: 44px;">Deb</th>
                        <th class="sub-head" style="width: 70px;">Rp</th>
                    @endif

                    @if($showAchievementColumns)
                        <th class="accent-head" style="width: 50px;">Deb</th>
                        <th class="accent-head" style="width: 76px;">Rp</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @forelse($rows as $branch)
                    <tr class="loan-branch-subtotal">
                        <td rowspan="{{ $branch['branch_rowspan'] }}" class="text-center-important sticky-col" style="font-weight: 800; left: 0; border-right: 1px solid #cbd5e1; background: #d9e1f2 !important; color: #000000 !important;">{{ $no++ }}</td>
                        <td rowspan="{{ $branch['branch_rowspan'] }}" class="merged-branch-cell sticky-col" style="left: 32px;">{{ $branch['cabang'] }}</td>
                        <td colspan="3" class="text-center-important sticky-col" style="letter-spacing: 0.03em; font-weight: 900; background: #d9e1f2 !important; color: #000000 !important; left: 126px; z-index: 20; border-right: 1px solid #cbd5e1;">
                            TOTAL {{ $branch['cabang'] }}
                        </td>
                        @foreach($comparisonColumns as $column)
                            <td>{{ $formatAmount($valueFor($branch['subtotal'], $column['key'])) }}</td>
                        @endforeach
                        <td class="highlight-curr">{{ $formatAmount($branch['subtotal']['curr']) }}</td>
                        @foreach($comparisonColumns as $column)
                            <td>{!! $formatSignedAmount($deltaFor($branch['subtotal'], $column['key'])) !!}</td>
                        @endforeach
                        @if($showTargetColumns)
                            <td class="text-center-important">{{ $branch['subtotal']['target_jg_deb'] ?: '-' }}</td>
                            <td>{{ $branch['subtotal']['target_jg_os'] > 0 ? $formatAmount($branch['subtotal']['target_jg_os']) : '-' }}</td>
                        @endif
                        @if($showAchievementColumns)
                            @php
                                $branchAchDeb = $branch['subtotal']['ach_deb'] ?? null;
                                $branchAchOs = $branch['subtotal']['ach_os'] ?? null;
                                $branchLarPct = $branch['subtotal']['lar_pct'] ?? null;
                            @endphp
                            <td class="{{ is_null($branchAchDeb) ? 'text-center-important kinerja-empty-highlight' : 'text-center-important' }}">{{ is_null($branchAchDeb) ? '' : $formatCount($branchAchDeb) }}</td>
                            <td class="{{ is_null($branchAchOs) ? 'kinerja-empty-highlight' : '' }}">{{ is_null($branchAchOs) ? '' : $formatAmount($branchAchOs) }}</td>
                        @endif
                        @if($showLarColumn)
                            <td class="{{ is_null($branchLarPct) ? 'text-center-important kinerja-empty-highlight' : 'text-center-important' }}">{{ is_null($branchLarPct) ? '' : $formatPercent($branchLarPct) }}</td>
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
                                        style="cursor: pointer; transition: all 0.2s; position: relative; left: 126px;">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-info-circle me-1 text-primary" style="font-size: 0.65rem; opacity: 0.6;"></i>
                                            {{ $rmName }}
                                        </div>
                                    </td>
                                    @php $isFirstRmRow = false; @endphp
                                @endif

                                <td class="text-start-important" style="font-weight: 700; color: var(--loan-muted); padding-left: 0.45rem;">
                                    {{ $item['product'] }}
                                </td>
                                @if($isFirstRmRowForQuad)
                                    <td rowspan="{{ $rmData['rm_rowspan'] }}" class="text-center-important">
                                        @if(($selectedSegmen ?? '') !== 'CONSUMER' && !empty($rmData['quadrant']))
                                            <div class="quadrant-badge {{ $quadrantClass($rmData['quadrant']) }}">
                                                <span class="quadrant-label">{{ $quadrantLabel($rmData['quadrant']) }}</span>
                                            </div>
                                        @elseif(($selectedSegmen ?? '') !== 'CONSUMER')
                                            <span class="text-muted small">-</span>
                                        @else
                                            <span class="text-muted small"></span>
                                        @endif
                                    </td>
                                    @php $isFirstRmRowForQuad = false; @endphp
                                @endif
                                @foreach($comparisonColumns as $column)
                                    <td>{{ $formatAmount($valueFor($item, $column['key'])) }}</td>
                                @endforeach
                                <td class="highlight-curr">{{ $formatAmount($item['curr']) }}</td>
                                @foreach($comparisonColumns as $column)
                                    <td>{!! $formatSignedAmount($deltaFor($item, $column['key'])) !!}</td>
                                @endforeach
                                @if($showTargetColumns)
                                    <td class="text-center-important" style="background: rgba(8, 87, 195, 0.02); font-size: 0.7rem;">{{ $item['target_jg_deb'] ?: '' }}</td>
                                    <td style="background: rgba(8, 87, 195, 0.02);">{{ $item['target_jg_os'] > 0 ? $formatAmount($item['target_jg_os']) : '' }}</td>
                                @endif
                                @if($showAchievementColumns)
                                    @php
                                        $itemAchDeb = $item['ach_deb'] ?? null;
                                        $itemAchOs = $item['ach_os'] ?? null;
                                        $itemLarPct = $item['lar_pct'] ?? null;
                                    @endphp
                                    <td class="{{ is_null($itemAchDeb) ? 'text-center-important kinerja-empty-highlight' : 'text-center-important' }}">{{ is_null($itemAchDeb) ? '' : $formatCount($itemAchDeb) }}</td>
                                    <td class="{{ is_null($itemAchOs) ? 'kinerja-empty-highlight' : '' }}">{{ is_null($itemAchOs) ? '' : $formatAmount($itemAchOs) }}</td>
                                @endif
                                @if($showLarColumn)
                                    <td class="{{ is_null($itemLarPct) ? 'text-center-important kinerja-empty-highlight' : 'text-center-important' }}">{{ is_null($itemLarPct) ? '' : $formatPercent($itemLarPct) }}</td>
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
                        <td colspan="5" class="text-center-important sticky-col" style="font-weight: 900; text-transform: uppercase; background: #b4c6e7 !important; color: #000000 !important; left: 0; z-index: 50; border-top: 1px solid #8faadc !important; border-bottom: 3px double #000000 !important; border-right: 1px solid #cbd5e1;">
                            {{ $grandTotalLabel }}
                        </td>
                        @foreach($comparisonColumns as $column)
                            <td>{{ $formatAmount($valueFor($total, $column['key'])) }}</td>
                        @endforeach
                        <td class="highlight-curr">{{ $formatAmount($total['curr']) }}</td>
                        @foreach($comparisonColumns as $column)
                            <td>{!! $formatSignedAmount($deltaFor($total, $column['key']), false) !!}</td>
                        @endforeach
                        @if($showTargetColumns)
                            <td class="text-center-important">{{ $total['target_jg_deb'] ?: '-' }}</td>
                            <td>{{ $total['target_jg_os'] > 0 ? $formatAmount($total['target_jg_os']) : '-' }}</td>
                        @endif
                        @if($showAchievementColumns)
                            @php
                                $totalAchDeb = $total['ach_deb'] ?? null;
                                $totalAchOs = $total['ach_os'] ?? null;
                                $totalLarPct = $total['lar_pct'] ?? null;
                            @endphp
                            <td class="{{ is_null($totalAchDeb) ? 'text-center-important kinerja-empty-highlight' : 'text-center-important' }}">{{ is_null($totalAchDeb) ? '' : $formatCount($totalAchDeb) }}</td>
                            <td class="{{ is_null($totalAchOs) ? 'kinerja-empty-highlight' : '' }}">{{ is_null($totalAchOs) ? '' : $formatAmount($totalAchOs) }}</td>
                        @endif
                        @if($showLarColumn)
                            <td class="{{ is_null($totalLarPct) ? 'text-center-important kinerja-empty-highlight' : 'text-center-important' }}">{{ is_null($totalLarPct) ? '' : $formatPercent($totalLarPct) }}</td>
                        @endif
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
