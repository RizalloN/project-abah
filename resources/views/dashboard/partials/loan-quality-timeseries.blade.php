@php
    $quality = (array) data_get($contentPortfolio ?? [], 'quality_timeseries', []);
    $qualityScopeLabel = match ($contentScopeKey ?? '') {
        'sme' => 'SME',
        'consumer' => 'Konsumer',
        'micro' => 'Mikro',
        default => strtoupper((string) ($contentScopeKey ?? '')),
    };
    $qualityChartId = 'loan-quality-chart-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($contentScopeKey ?? 'scope'));
    $qualitySeries = [
        ['key' => 'lr', 'label' => 'LR', 'color' => '#7c3aed', 'dash' => []],
        ['key' => 'sml', 'label' => 'SML', 'color' => '#f59e0b', 'dash' => []],
        ['key' => 'npl', 'label' => 'NPL', 'color' => '#ef4444', 'dash' => []],
        ['key' => 'lar', 'label' => 'LAR', 'color' => '#0754bd', 'dash' => []],
    ];
    $qualityLatestPoint = collect((array) data_get($quality, 'points', []))
        ->filter(fn (array $point): bool => data_get($point, 'lar') !== null)
        ->last();
    $qualityChartConfig = [
        'canvasId' => $qualityChartId,
        'labels' => data_get($quality, 'labels', []),
        'periodLabels' => data_get($quality, 'period_labels', []),
        'defaultMetric' => 'lar',
        'datasets' => collect($qualitySeries)->map(fn (array $definition): array => [
            'metricKey' => $definition['key'],
            'label' => $definition['label'],
            'data' => data_get($quality, 'series.' . $definition['key'], []),
            'borderColor' => $definition['color'],
            'backgroundColor' => $definition['color'],
            'borderDash' => $definition['dash'],
            'hidden' => $definition['key'] !== 'lar',
        ])->all(),
    ];
@endphp

@if(!empty($quality['available']))
<section class="loan-analytics-card loan-quality-card" data-loan-quality-card>
    <div class="loan-analytics-shape loan-analytics-shape--one" aria-hidden="true"></div>
    <div class="loan-analytics-shape loan-analytics-shape--two" aria-hidden="true"></div>
    <header class="loan-analytics-head">
        <div class="loan-analytics-title">
            <span class="loan-analytics-icon"><i class="fas fa-wave-square"></i></span>
            <div>
                <span class="loan-analytics-kicker">Kualitas Portofolio {{ $qualityScopeLabel }}</span>
                <h3>Timeseries Akhir Bulan {{ data_get($quality, 'year') }}</h3>
            </div>
        </div>
        @if($qualityLatestPoint)
            <div class="loan-analytics-latest">
                <span>Posisi Terakhir</span>
                <strong>{{ data_get($qualityLatestPoint, 'period_label', '-') }}</strong>
            </div>
        @endif
    </header>

    <div class="loan-quality-layout">
        <div class="loan-quality-chart-wrap">
            <canvas id="{{ $qualityChartId }}"
                    height="118"
                    role="img"
                    aria-label="Grafik LR, SML, NPL, dan LAR {{ $qualityScopeLabel }} selama {{ data_get($quality, 'year') }}"></canvas>
        </div>
        <aside class="loan-quality-legend" aria-label="Pilih indikator kualitas">
            @foreach($qualitySeries as $definition)
                @php
                    $latestValue = $qualityLatestPoint ? data_get($qualityLatestPoint, $definition['key']) : null;
                @endphp
                <button type="button"
                        class="loan-quality-legend__item {{ $definition['key'] === 'lar' ? 'is-active' : '' }}"
                        data-loan-quality-toggle="{{ $qualityChartId }}"
                        data-loan-quality-metric="{{ $definition['key'] }}"
                        aria-pressed="{{ $definition['key'] === 'lar' ? 'true' : 'false' }}"
                        style="--legend-color: {{ $definition['color'] }}">
                    <span class="loan-quality-legend__swatch"></span>
                    <div>
                        <span>{{ $definition['label'] }}</span>
                        <strong>{{ $latestValue !== null ? number_format((float) $latestValue, 0, ',', '.') : '-' }}</strong>
                    </div>
                </button>
            @endforeach
            <small>Rp Juta · pilih indikator</small>
        </aside>
    </div>

    <details class="loan-analytics-details">
        <summary><i class="fas fa-table"></i>Lihat angka per posisi</summary>
        <div class="loan-analytics-table-wrap">
            <table class="loan-analytics-table">
                <thead>
                    <tr>
                        <th>Posisi</th>
                        <th>LR</th>
                        <th>SML</th>
                        <th>NPL</th>
                        <th>LAR</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach((array) data_get($quality, 'points', []) as $point)
                        @continue(data_get($point, 'lar') === null)
                        <tr>
                            <th scope="row">{{ data_get($point, 'period_label', '-') }}</th>
                            @foreach(['lr', 'sml', 'npl', 'lar'] as $metric)
                                <td>{{ number_format((float) data_get($point, $metric, 0), 0, ',', '.') }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    <script type="application/json" data-loan-quality-config>
        @json($qualityChartConfig)
    </script>
</section>
@endif
