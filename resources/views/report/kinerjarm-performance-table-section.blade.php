@php
    $performanceMonths = collect($performanceMonths ?? [])->values();
    $performanceRows = $performanceRows ?? [];
    $performanceTotal = $performanceTotal ?? [
        'target' => ['deb' => 0, 'rp' => 0],
        'months' => [],
        'delta' => ['ytd' => 0, 'mtd' => 0, 'mom' => null, 'dtd' => null],
        'accumulated' => ['deb' => 0, 'rp' => 0],
    ];
    $performanceMeta = $performanceMeta ?? [];
    $isSmallPerformance = strtoupper((string) ($selectedSegmen ?? '')) === 'SMALL';
    $formatPlainAmount = $formatPlainAmount ?? fn ($value) => is_null($value) ? '-' : (string) (int) round((float) $value / 1000000);
    $formatPlainCount = $formatPlainCount ?? fn ($value) => is_null($value) ? '-' : (string) (int) round((float) $value);
    $formatPlainPercent = fn ($value) => is_null($value) ? '-' : number_format((float) $value, 2, ',', '.') . '%';
    $formatPlainDelta = $formatPlainDelta ?? function ($value) {
        if (is_null($value)) return '-';
        $number = (int) round((float) $value / 1000000);
        return $number > 0 ? '+' . $number : (string) $number;
    };
    $quadrantLabel = $quadrantLabel ?? fn ($quadrant) => in_array((int) $quadrant, [1, 2, 3, 4], true) ? 'Kuadran ' . (int) $quadrant : '-';
    $quadrantClass = $quadrantClass ?? fn ($quadrant) => in_array((int) $quadrant, [1, 2, 3, 4], true) ? 'q' . (int) $quadrant : '';
    $tableMinWidth = ($isSmallPerformance ? 660 : 960) + ($performanceMonths->count() * ($isSmallPerformance ? 112 : 104));
    $emptyColspan = $isSmallPerformance
        ? 3 + $performanceMonths->count() + 2 + 1
        : 3 + 2 + $performanceMonths->count() + 4 + 2 + 1;
    $deltaClass = fn ($value) => is_null($value) ? '' : ((float) $value > 0 ? 'is-positive' : ((float) $value < 0 ? 'is-negative' : ''));
@endphp

<div class="kinerja-report-card kinerja-performance-card">
    <div class="kinerja-report-card__header">
        <div class="kinerja-report-card__title-wrap">
            <h2 class="kinerja-report-card__title">
                <i class="fas fa-chart-line" aria-hidden="true"></i>
                {{ $isSmallPerformance ? 'Realisasi & LAR per RM' : 'Realisasi per RM' }}
            </h2>
        </div>
    </div>

    <div class="kinerja-table-container kinerja-performance-table-container shadow-sm">
        <table class="kinerja-rm-performance-table" style="min-width: {{ $tableMinWidth }}px">
            <thead>
                <tr>
                    <th rowspan="2" class="performance-sticky performance-sticky-no">No</th>
                    <th rowspan="2" class="performance-sticky performance-sticky-unit">Kode (Unit Kerja)</th>
                    <th rowspan="2" class="performance-sticky performance-sticky-rm">Nama RM</th>
                    @if(!$isSmallPerformance)
                        <th colspan="2" class="performance-group-head">Target JG / Bulan</th>
                    @endif
                    <th colspan="{{ max(1, $performanceMonths->count()) }}" class="performance-group-head">Performance</th>
                    @if(!$isSmallPerformance)
                        <th colspan="4" class="performance-group-head performance-group-head--delta">Delta</th>
                    @endif
                    <th colspan="2" class="performance-group-head">
                        {{ $isSmallPerformance ? 'Akumulasi Bulan Tutup' : 'Akumulasi s.d. ' . ($performanceMeta['latest_period_label'] ?? '-') }}
                    </th>
                    <th rowspan="2" class="performance-group-head">Kelompok Kuadran RM</th>
                </tr>
                <tr>
                    @if(!$isSmallPerformance)
                        <th>Jml Deb</th>
                        <th>Rp Juta</th>
                    @endif
                    @forelse($performanceMonths as $month)
                        <th class="performance-month-head {{ $loop->last ? 'is-current' : '' }} {{ $isSmallPerformance && !($month['is_closed'] ?? false) && !empty($month['period']) ? 'is-open' : '' }}" title="Data posisi {{ $month['period_label'] }}">
                            {{ $month['short_label'] }}
                            <small>
                                {{ $isSmallPerformance ? 'Rp / % LAR' : 'Deb / Rp Juta' }}
                                @if($isSmallPerformance && !($month['is_closed'] ?? false) && !empty($month['period']))
                                    - berjalan
                                @endif
                            </small>
                        </th>
                    @empty
                        <th>-</th>
                    @endforelse
                    @if($isSmallPerformance)
                        <th>Ratas Rp<small>Rp Juta</small></th>
                        <th>% LAR<small>{{ $performanceMeta['closed_through_period_label'] ?? 'Bulan tutup terakhir' }}</small></th>
                    @else
                        <th class="performance-delta-head">YTD<small>Rp Juta</small></th>
                        <th class="performance-delta-head">MTD<small>Rp Juta</small></th>
                        <th class="performance-delta-head" title="Month over Month">MoM<small>Rp Juta</small></th>
                        <th class="performance-delta-head" title="Day to Day">DtD<small>Rp Juta</small></th>
                        <th>Jml Deb</th>
                        <th>Rp Juta</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($performanceRows as $row)
                    <tr>
                        <td class="performance-sticky performance-sticky-no performance-cell-center">{{ $loop->iteration }}</td>
                        <td class="performance-sticky performance-sticky-unit performance-unit-cell">
                            <strong>{{ $row['unit_code'] ?: '-' }}</strong>
                            <span>{{ $row['unit'] ?: $row['cabang'] }}</span>
                        </td>
                        <td class="performance-sticky performance-sticky-rm performance-rm-cell clickable-rm-row"
                            data-rm-name="{{ $row['rm'] }}"
                            data-segment="{{ $selectedSegmen }}"
                            data-period="{{ $selectedPeriod }}"
                            title="Klik dua kali untuk detail historis">
                            {{ $row['rm_display'] ?? $row['rm'] }}
                        </td>
                        @if(!$isSmallPerformance)
                            <td class="performance-cell-center">{{ $formatPlainCount($row['target']['deb']) }}</td>
                            <td>{{ $formatPlainAmount($row['target']['rp']) }}</td>
                        @endif
                        @foreach($performanceMonths as $month)
                            @php $metric = $row['months'][$month['key']] ?? ['deb' => 0, 'rp' => 0, 'lar_pct' => null, 'has_data' => false]; @endphp
                            <td class="performance-month-cell {{ $loop->last ? 'is-current' : '' }}">
                                @if($metric['has_data'])
                                    @if(!$isSmallPerformance)
                                        <span><b>D</b> {{ $formatPlainCount($metric['deb']) }}</span>
                                    @endif
                                    <span><b>Rp</b> {{ $formatPlainAmount($metric['rp']) }}</span>
                                    @if($isSmallPerformance)
                                        <span><b>LAR</b> {{ $formatPlainPercent($metric['lar_pct'] ?? null) }}</span>
                                    @endif
                                @else
                                    <span class="performance-empty-value">-</span>
                                @endif
                            </td>
                        @endforeach
                        @if($isSmallPerformance)
                            <td>{{ $formatPlainAmount($row['accumulated']['ratas_rp'] ?? null) }}</td>
                            <td>{{ $formatPlainPercent($row['accumulated']['lar_pct'] ?? null) }}</td>
                        @else
                            @foreach(['ytd', 'mtd', 'mom', 'dtd'] as $deltaKey)
                                @php $deltaValue = $row['delta'][$deltaKey] ?? null; @endphp
                                <td class="performance-delta-cell {{ $deltaClass($deltaValue) }}">{{ $formatPlainDelta($deltaValue) }}</td>
                            @endforeach
                            <td class="performance-cell-center">{{ $formatPlainCount($row['accumulated']['deb']) }}</td>
                            <td>{{ $formatPlainAmount($row['accumulated']['rp']) }}</td>
                        @endif
                        <td class="performance-cell-center cell-quadrant {{ $quadrantClass($row['quadrant']) }}">
                            {{ $quadrantLabel($row['quadrant']) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $emptyColspan }}" class="performance-empty-row">
                            Tidak ada RM dengan realisasi pada dua bulan laporan terakhir.
                        </td>
                    </tr>
                @endforelse

                @if(!empty($performanceRows))
                    <tr class="performance-total-row">
                        <td class="performance-sticky performance-sticky-no"></td>
                        <td class="performance-sticky performance-sticky-unit">TOTAL</td>
                        <td class="performance-sticky performance-sticky-rm">{{ count($performanceRows) }} RM</td>
                        @if(!$isSmallPerformance)
                            <td class="performance-cell-center">{{ $formatPlainCount($performanceTotal['target']['deb']) }}</td>
                            <td>{{ $formatPlainAmount($performanceTotal['target']['rp']) }}</td>
                        @endif
                        @foreach($performanceMonths as $month)
                            @php $metric = $performanceTotal['months'][$month['key']] ?? ['deb' => 0, 'rp' => 0, 'lar_pct' => null, 'has_data' => false]; @endphp
                            <td class="performance-month-cell {{ $loop->last ? 'is-current' : '' }}">
                                @if($metric['has_data'])
                                    @if(!$isSmallPerformance)
                                        <span><b>D</b> {{ $formatPlainCount($metric['deb']) }}</span>
                                    @endif
                                    <span><b>Rp</b> {{ $formatPlainAmount($metric['rp']) }}</span>
                                    @if($isSmallPerformance)
                                        <span><b>LAR</b> {{ $formatPlainPercent($metric['lar_pct'] ?? null) }}</span>
                                    @endif
                                @else
                                    <span class="performance-empty-value">-</span>
                                @endif
                            </td>
                        @endforeach
                        @if($isSmallPerformance)
                            <td>{{ $formatPlainAmount($performanceTotal['accumulated']['ratas_rp'] ?? null) }}</td>
                            <td>{{ $formatPlainPercent($performanceTotal['accumulated']['lar_pct'] ?? null) }}</td>
                        @else
                            @foreach(['ytd', 'mtd', 'mom', 'dtd'] as $deltaKey)
                                @php $deltaValue = $performanceTotal['delta'][$deltaKey] ?? null; @endphp
                                <td class="performance-delta-cell {{ $deltaClass($deltaValue) }}">{{ $formatPlainDelta($deltaValue) }}</td>
                            @endforeach
                            <td class="performance-cell-center">{{ $formatPlainCount($performanceTotal['accumulated']['deb']) }}</td>
                            <td>{{ $formatPlainAmount($performanceTotal['accumulated']['rp']) }}</td>
                        @endif
                        <td class="performance-cell-center">-</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
