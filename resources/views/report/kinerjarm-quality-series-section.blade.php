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
    $emptyColspan = 5 + ($showLoanReference ? 1 : 0) + $comparisonCount + 1 + $comparisonCount;
    $formatAmount = $formatAmount ?? fn ($value, int $decimals = 0) => number_format(((float) $value) / 1000000, 0, ',', '.');
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
            return '<span class="delta-indicator">0</span>';
        }

        $prefix = $value > 0 ? '+' : '-';
        $display = number_format(abs($value) / 1000000, 0, ',', '.');

        return '<span class="delta-indicator">' . $prefix . $display . '</span>';
    };
@endphp

<div class="kinerja-report-card kinerja-quality-series-card">
    <div class="kinerja-report-card__header">
        <div class="kinerja-report-card__title-wrap">
            <h2 class="kinerja-report-card__title">
                <i class="fas fa-chart-line" aria-hidden="true"></i>
                {{ $sectionTitle }}
            </h2>
            @if(!empty($sectionSubtitle))
                <p class="kinerja-report-card__subtitle">{{ $sectionSubtitle }}</p>
            @endif
        </div>
    </div>

    <div class="kinerja-table-container kinerja-quality-table-container shadow-sm">
        <table class="kinerja-konsumer-table kinerja-konsumer-table--compact kinerja-quality-series-table" style="--kinerja-no-column-width: 48px; --kinerja-branch-column-width: 112px; --kinerja-unit-code-width: 92px; --kinerja-rm-column-width: 190px;">
            <thead>
                <tr>
                    <th rowspan="2" class="sticky-col quality-sticky-no" style="width: var(--kinerja-no-column-width); left: 0;">No</th>
                    <th rowspan="2" class="sticky-col quality-sticky-branch" style="width: var(--kinerja-branch-column-width); left: var(--kinerja-no-column-width);">Cabang</th>
                    <th rowspan="2" class="sticky-col quality-sticky-unit-code" style="width: var(--kinerja-unit-code-width); left: calc(var(--kinerja-no-column-width) + var(--kinerja-branch-column-width));">Kode Uker</th>
                    <th rowspan="2" class="sticky-col quality-sticky-rm" style="width: var(--kinerja-rm-column-width); left: calc(var(--kinerja-no-column-width) + var(--kinerja-branch-column-width) + var(--kinerja-unit-code-width));">Nama RM</th>
                    <th rowspan="2" style="width: 108px;">Produk</th>
                    @if($showLoanReference)
                        <th rowspan="2" class="sub-head" style="width: 92px;">OS Pinjaman<br>{{ $selectedHeaderLabel }}</th>
                    @endif
                    <th colspan="{{ $comparisonCount + 1 }}" class="sub-head quality-series-head">Series {{ $componentLabel }}</th>
                    <th colspan="{{ $comparisonCount }}" class="accent-head quality-delta-head">Delta Terhadap Posisi</th>
                </tr>
                <tr>
                    @foreach($comparisonColumns as $column)
                        <th class="sub-head kinerja-period-head" style="width: 88px; white-space: nowrap;">
                            <span>{{ $column['short_label'] ?? '-' }}</span>
                        </th>
                    @endforeach
                    <th class="sub-head kinerja-period-head quality-current-head" style="width: 92px; white-space: nowrap;">
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
                @php $rowNumber = 0; @endphp
                @forelse($rows as $branch)
                    <tr class="loan-branch-subtotal">
                        <td class="quality-subtotal-no sticky-col" style="left: 0;">-</td>
                        <td class="sticky-col quality-sticky-branch" style="left: var(--kinerja-no-column-width);">{{ $branch['cabang'] }}</td>
                        <td colspan="3" class="text-center-important sticky-col quality-subtotal-label" style="left: calc(var(--kinerja-no-column-width) + var(--kinerja-branch-column-width)); z-index: 20; border-right: 1px solid #cbd5e1;">
                            TOTAL {{ $branch['cabang'] }}
                        </td>
                        @if($showLoanReference)
                            <td>{{ $formatAmount($branch['subtotal']['loan_os_reference'] ?? 0, 0) }}</td>
                        @endif
                        @foreach($comparisonColumns as $column)
                            <td>{{ $formatAmount($valueFor($branch['subtotal'], $column['key']), 0) }}</td>
                        @endforeach
                        <td class="highlight-curr quality-current-cell">{{ $formatAmount($branch['subtotal']['curr'] ?? 0, 0) }}</td>
                        @foreach($comparisonColumns as $column)
                            @php $delta = $deltaFor($branch['subtotal'], $column['key']); @endphp
                            <td class="quality-delta-cell {{ $deltaClass($delta) }}">{!! $formatDelta($delta) !!}</td>
                        @endforeach
                    </tr>

                    @foreach($branch['rms'] as $rmKey => $rmData)
                        @php
                            $rmName = (string) ($rmData['rm'] ?? $rmKey);
                            $rmUnitCode = trim((string) ($rmData['rm_unit_code'] ?? ''));
                            $firstRmRow = true;
                            $rowNumber++;
                        @endphp
                        @foreach($rmData['items'] as $item)
                            <tr>
                                @if($firstRmRow)
                                    <td rowspan="{{ $rmData['rm_rowspan'] }}" class="sticky-col quality-sticky-no quality-row-number" style="left: 0;">{{ $rowNumber }}</td>
                                @endif
                                <td class="sticky-col quality-sticky-branch" style="left: var(--kinerja-no-column-width);">{{ $branch['cabang'] }}</td>
                                @if($firstRmRow)
                                    <td rowspan="{{ $rmData['rm_rowspan'] }}" class="sticky-col quality-sticky-unit-code quality-unit-code" style="left: calc(var(--kinerja-no-column-width) + var(--kinerja-branch-column-width));">{{ $rmUnitCode !== '' ? $rmUnitCode : '-' }}</td>
                                    <td rowspan="{{ $rmData['rm_rowspan'] }}" class="merged-rm-cell sticky-col quality-sticky-rm" style="left: calc(var(--kinerja-no-column-width) + var(--kinerja-branch-column-width) + var(--kinerja-unit-code-width));">{{ $rmName }}</td>
                                    @php $firstRmRow = false; @endphp
                                @endif
                                <td class="text-start-important">{{ $item['product'] }}</td>
                                @if($showLoanReference)
                                    <td>{{ $formatAmount($item['loan_os_reference'] ?? 0, 0) }}</td>
                                @endif
                                @foreach($comparisonColumns as $column)
                                    <td>{{ $formatAmount($valueFor($item, $column['key']), 0) }}</td>
                                @endforeach
                                <td class="highlight-curr quality-current-cell">{{ $formatAmount($item['curr'] ?? 0, 0) }}</td>
                                @foreach($comparisonColumns as $column)
                                    @php $delta = $deltaFor($item, $column['key']); @endphp
                                    <td class="quality-delta-cell {{ $deltaClass($delta) }}">{!! $formatDelta($delta) !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                @empty
                    <tr>
                        <td colspan="{{ $emptyColspan }}" class="quality-empty-row">
                            <i class="fas fa-folder-open fa-2x mb-2 d-block" style="opacity: 0.3;"></i>
                            Data {{ $sectionTitle }} tidak ditemukan untuk filter ini.
                        </td>
                    </tr>
                @endforelse

                @if(!empty($rows))
                    <tr class="row-grand-total">
                        <td colspan="5" class="text-center-important sticky-col quality-grand-total-label" style="left: 0; z-index: 50; border-right: 1px solid #cbd5e1;">
                            GRAND TOTAL {{ strtoupper($componentLabel) }}
                        </td>
                        @if($showLoanReference)
                            <td>{{ $formatAmount($total['loan_os_reference'] ?? 0, 0) }}</td>
                        @endif
                        @foreach($comparisonColumns as $column)
                            <td>{{ $formatAmount($valueFor($total, $column['key']), 0) }}</td>
                        @endforeach
                        <td class="highlight-curr quality-current-cell">{{ $formatAmount($total['curr'] ?? 0, 0) }}</td>
                        @foreach($comparisonColumns as $column)
                            @php $delta = $deltaFor($total, $column['key']); @endphp
                            <td class="quality-delta-cell {{ $deltaClass($delta) }}">{!! $formatDelta($delta) !!}</td>
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
