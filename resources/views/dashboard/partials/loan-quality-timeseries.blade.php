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
        ['key' => 'lar', 'label' => 'LAR', 'color' => '#0754bd', 'dash' => []],
        ['key' => 'lr', 'label' => 'LR', 'color' => '#7c3aed', 'dash' => []],
        ['key' => 'sml', 'label' => 'SML', 'color' => '#0284c7', 'dash' => []],
        ['key' => 'sml1', 'label' => 'SML 1', 'color' => '#0857c3', 'dash' => []],
        ['key' => 'sml2', 'label' => 'SML 2', 'color' => '#009ac8', 'dash' => []],
        ['key' => 'sml3', 'label' => 'SML 3', 'color' => '#d88900', 'dash' => []],
        ['key' => 'kl', 'label' => 'KL', 'color' => '#e66a00', 'dash' => []],
        ['key' => 'd', 'label' => 'D', 'color' => '#dc3545', 'dash' => []],
        ['key' => 'm', 'label' => 'M', 'color' => '#9f1239', 'dash' => []],
        ['key' => 'npl', 'label' => 'NPL', 'color' => '#ef4444', 'dash' => []],
    ];
    $qualityDetailMetrics = array_column($qualitySeries, 'key');
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
                    aria-label="Grafik indikator kualitas portofolio {{ $qualityScopeLabel }} selama {{ data_get($quality, 'year') }}"></canvas>
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
            <small>Rp Juta &middot; pilih indikator</small>
        </aside>
    </div>

    <details class="loan-analytics-details">
        <summary><i class="fas fa-table"></i>Lihat angka per posisi</summary>
        <div class="loan-analytics-table-wrap">
            <table class="loan-analytics-table">
                <thead>
                    <tr>
                        <th>Posisi</th>
                        @foreach($qualityDetailMetrics as $metric)
                            <th>{{ strtoupper($metric) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach((array) data_get($quality, 'points', []) as $point)
                        @continue(data_get($point, 'lar') === null)
                        <tr>
                            <th scope="row">{{ data_get($point, 'period_label', '-') }}</th>
                            @foreach($qualityDetailMetrics as $metric)
                                <td>{{ data_get($point, $metric) !== null ? number_format((float) data_get($point, $metric, 0), 0, ',', '.') : '-' }}</td>
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
