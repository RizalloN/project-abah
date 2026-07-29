@php
    $sectionTitle = $sectionTitle ?? 'Kualitas RM';
    $sectionSubtitle = $sectionSubtitle ?? null;
    $componentLabel = $componentLabel ?? $sectionTitle;
    $showLoanReference = $showLoanReference ?? true;
    $lowerIsBetter = $lowerIsBetter ?? false;
    $rows = $rows ?? [];
    $total = $total ?? [];
    $comparisonColumns = collect($comparisonColumns ?? [])->values();
    $comparisonCount = max(1, $comparisonColumns->count());
    $selectedHeaderLabel = $selectedPeriodShortLabel ?? 'POSISI';
    $emptyColspan = 3 + ($showLoanReference ? 1 : 0) + $comparisonCount + 1 + $comparisonCount;
    $formatAmount = $formatAmount ?? fn ($value, int $decimals = 1) => number_format(((float) $value) / 1000000, $decimals, ',', '.');
    $valueFor = fn (array $row, string $key): float => (float) data_get($row, "comparison_values.{$key}", 0);
    $deltaFor = fn (array $row, string $key): float => (float) data_get(
        $row,
        "comparison_deltas.{$key}",
        (float) ($row['curr'] ?? 0) - $valueFor($row, $key)
    );
    $deltaClass = function (float $value) use ($lowerIsBetter): string {
        if (abs($value) < 0.0001) {
            return '';
        }

        $isGood = $lowerIsBetter ? $value < 0 : $value > 0;

        return $isGood ? 'cell-pos' : 'cell-neg';
    };
    $formatDelta = function (float $value): string {
        if (abs($value) < 0.0001) {
            return '<span class="delta-indicator">0,0</span>';
        }

        $icon = $value > 0 ? 'fa-caret-up' : 'fa-caret-down';
        $prefix = $value > 0 ? '+' : '-';
        $display = number_format(abs($value) / 1000000, 1, ',', '.');

        return '<span class="delta-indicator"><i class="fas ' . $icon . ' me-1"></i>' . $prefix . $display . '</span>';
    };
@endphp

<div class="kinerja-report-card kinerja-quality-series-card">
    <div class="kinerja-report-card__header">
        <div class="kinerja-report-card__title-wrap">
            <h2 class="kinerja-report-card__title">
                <i class="fas fa-chart-bar" style="font-size: 0.78rem; color: var(--loan-blue);"></i>
                {{ $sectionTitle }}
            </h2>
            @if(!empty($sectionSubtitle))
                <p class="kinerja-report-card__subtitle">{{ $sectionSubtitle }}</p>
            @endif
        </div>
        <div class="kinerja-report-card__meta">
            <span>Rp Juta</span>
        </div>
    </div>

    <div class="kinerja-table-container">
        <table class="kinerja-konsumer-table kinerja-konsumer-table--compact kinerja-quality-series-table" style="--kinerja-branch-column-width: 112px;">
            <thead>
                <tr>
                    <th rowspan="2" class="sticky-col" style="width: var(--kinerja-branch-column-width); left: 0;">Cabang</th>
                    <th rowspan="2" class="sticky-col" style="width: 190px; left: var(--kinerja-branch-column-width);">Nama RM</th>
                    <th rowspan="2" style="width: 108px;">Produk</th>
                    @if($showLoanReference)
                        <th rowspan="2" class="sub-head" style="width: 92px;">OS Pinjaman<br>{{ $selectedHeaderLabel }}</th>
                    @endif
                    <th colspan="{{ $comparisonCount + 1 }}" class="sub-head">Series {{ $componentLabel }}</th>
                    <th colspan="{{ $comparisonCount }}" class="accent-head">Delta Terhadap Posisi</th>
                </tr>
                <tr>
                    @foreach($comparisonColumns as $column)
                        <th class="sub-head kinerja-period-head" style="width: 88px; white-space: nowrap;">
                            <span>{{ $column['short_label'] ?? '-' }}</span>
                        </th>
                    @endforeach
                    <th class="sub-head kinerja-period-head" style="width: 92px; white-space: nowrap;">
                        <span>{{ $selectedHeaderLabel }}</span>
                    </th>
                    @foreach($comparisonColumns as $column)
                        <th class="accent-head kinerja-period-head" style="width: 88px; white-space: nowrap;">
                            <span>{{ $column['short_label'] ?? '-' }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $branch)
                    <tr class="loan-branch-subtotal">
                        <td rowspan="{{ $branch['branch_rowspan'] }}" class="merged-branch-cell sticky-col" style="left: 0;">{{ $branch['cabang'] }}</td>
                        <td colspan="2" class="text-center-important sticky-col" style="left: var(--kinerja-branch-column-width); z-index: 20; border-right: 1px solid #cbd5e1;">
                            TOTAL {{ $branch['cabang'] }}
                        </td>
                        @if($showLoanReference)
                            <td>{{ $formatAmount($branch['subtotal']['loan_os_reference'] ?? 0) }}</td>
                        @endif
                        @foreach($comparisonColumns as $column)
                            <td>{{ $formatAmount($valueFor($branch['subtotal'], $column['key'])) }}</td>
                        @endforeach
                        <td class="highlight-curr">{{ $formatAmount($branch['subtotal']['curr'] ?? 0) }}</td>
                        @foreach($comparisonColumns as $column)
                            @php $delta = $deltaFor($branch['subtotal'], $column['key']); @endphp
                            <td class="{{ $deltaClass($delta) }}">{!! $formatDelta($delta) !!}</td>
                        @endforeach
                    </tr>

                    @foreach($branch['rms'] as $rmKey => $rmData)
                        @php
                            $rmName = (string) ($rmData['rm'] ?? $rmKey);
                            $rmCategory = $rmData['rm_category'] ?? null;
                            $rmUnit = $rmData['rm_unit'] ?? null;
                            $firstRmRow = true;
                        @endphp
                        @foreach($rmData['items'] as $item)
                            <tr>
                                @if($firstRmRow)
                                    <td rowspan="{{ $rmData['rm_rowspan'] }}" class="merged-rm-cell sticky-col" style="left: var(--kinerja-branch-column-width);">
                                        <div class="d-flex align-items-start flex-column">
                                            <span>{{ $rmName }}</span>
                                            @if($rmCategory !== null)
                                                <span class="kinerja-rm-scope-badge {{ $rmCategory === 'KCP' ? 'is-kcp' : 'is-kc' }}">{{ $rmUnit ?: 'RM ' . $rmCategory }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    @php $firstRmRow = false; @endphp
                                @endif
                                <td class="text-start-important">{{ $item['product'] }}</td>
                                @if($showLoanReference)
                                    <td>{{ $formatAmount($item['loan_os_reference'] ?? 0) }}</td>
                                @endif
                                @foreach($comparisonColumns as $column)
                                    <td>{{ $formatAmount($valueFor($item, $column['key'])) }}</td>
                                @endforeach
                                <td class="highlight-curr">{{ $formatAmount($item['curr'] ?? 0) }}</td>
                                @foreach($comparisonColumns as $column)
                                    @php $delta = $deltaFor($item, $column['key']); @endphp
                                    <td class="{{ $deltaClass($delta) }}">{!! $formatDelta($delta) !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                @empty
                    <tr>
                        <td colspan="{{ $emptyColspan }}" class="py-5 text-center text-muted">
                            <i class="fas fa-folder-open fa-2x mb-2 d-block" style="opacity: 0.3;"></i>
                            Data {{ $sectionTitle }} tidak ditemukan untuk filter ini.
                        </td>
                    </tr>
                @endforelse

                @if(!empty($rows))
                    <tr class="row-grand-total">
                        <td colspan="3" class="text-center-important sticky-col" style="left: 0; z-index: 50; border-right: 1px solid #cbd5e1;">
                            GRAND TOTAL {{ strtoupper($componentLabel) }}
                        </td>
                        @if($showLoanReference)
                            <td>{{ $formatAmount($total['loan_os_reference'] ?? 0) }}</td>
                        @endif
                        @foreach($comparisonColumns as $column)
                            <td>{{ $formatAmount($valueFor($total, $column['key'])) }}</td>
                        @endforeach
                        <td class="highlight-curr">{{ $formatAmount($total['curr'] ?? 0) }}</td>
                        @foreach($comparisonColumns as $column)
                            @php $delta = $deltaFor($total, $column['key']); @endphp
                            <td class="{{ $deltaClass($delta) }}">{!! $formatDelta($delta) !!}</td>
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
