@php
    $tariff = (array) data_get($contentPortfolio ?? [], 'tariff_relief', []);
    $tariffPoints = array_values((array) data_get($tariff, 'points', []));
    $tariffLatest = (array) data_get($tariff, 'latest', []);
    $tariffAdjustedDelta = (float) data_get($tariffLatest, 'adjusted_delta_os', data_get($tariffLatest, 'delta_os', 0));
    $tariffChartId = 'sme-tariff-relief-chart';
    $tariffChartConfig = [
        'canvasId' => $tariffChartId,
        'valueType' => 'currency_juta',
        'labels' => data_get($tariff, 'labels', []),
        'periodLabels' => array_column($tariffPoints, 'closing_period_label'),
        'points' => $tariffPoints,
        'datasets' => [
            [
                'metricKey' => 'previous',
                'label' => 'OS H-1',
                'data' => data_get($tariff, 'series.previous', []),
                'borderColor' => '#0754bd',
                'backgroundColor' => '#0754bd',
                'borderDash' => [],
            ],
            [
                'metricKey' => 'closing',
                'label' => 'OS Akhir Bulan',
                'data' => data_get($tariff, 'series.closing', []),
                'borderColor' => '#00a6d6',
                'backgroundColor' => '#00a6d6',
                'borderDash' => [7, 4],
            ],
        ],
    ];
@endphp

@if(!empty($tariff['available']))
<section class="loan-analytics-card tariff-relief-card" data-tariff-relief-card>
    <div class="loan-analytics-shape loan-analytics-shape--one" aria-hidden="true"></div>
    <header class="loan-analytics-head">
        <div class="loan-analytics-title">
            <span class="loan-analytics-icon loan-analytics-icon--cyan"><i class="fas fa-coins"></i></span>
            <div>
                <span class="loan-analytics-kicker">SME OS Closing Monitor</span>
                <h3>Kelonggaran Tarik H-1 vs Akhir Bulan</h3>
            </div>
        </div>
    </header>

    <div class="tariff-series-chart-wrap">
        <canvas id="{{ $tariffChartId }}"
                height="128"
                role="img"
                aria-label="Grafik garis OS H-1 dan OS akhir bulan SME"></canvas>
    </div>
    <script type="application/json" data-tariff-relief-config>
        @json($tariffChartConfig)
    </script>

    <details class="loan-analytics-details">
        <summary><i class="fas fa-calendar-alt"></i>Lihat detail OS, realisasi, dan kelonggaran tarik</summary>
        <div class="loan-analytics-table-wrap">
            <table class="loan-analytics-table tariff-detail-table">
                <thead><tr><th>Bulan</th><th>Posisi H-1</th><th>OS H-1</th><th>Posisi Akhir Bulan</th><th>OS Akhir Bulan</th><th>Delta OS</th><th>Realisasi SME</th><th>Kelonggaran Tarik</th></tr></thead>
                <tbody>
                    @foreach($tariffPoints as $point)
                        @php
                            $adjustedDelta = (float) data_get($point, 'adjusted_delta_os', data_get($point, 'delta_os', 0));
                        @endphp
                        <tr>
                            <th scope="row">{{ data_get($point, 'label', '-') }}</th>
                            <td>{{ data_get($point, 'previous_period_label', '-') }}</td>
                            <td>{{ number_format((float) data_get($point, 'previous_os', 0), 0, ',', '.') }}</td>
                            <td>{{ data_get($point, 'closing_period_label', '-') }}</td>
                            <td>{{ number_format((float) data_get($point, 'closing_os', 0), 0, ',', '.') }}</td>
                            <td>{{ number_format((float) data_get($point, 'delta_os', 0), 0, ',', '.') }}</td>
                            <td>{{ data_get($point, 'daily_realization_available') ? number_format((float) data_get($point, 'daily_realization', 0), 0, ',', '.') : '-' }}</td>
                            <td class="{{ $adjustedDelta <= 0 ? 'text-success' : 'text-warning' }} font-weight-bold">{{ $adjustedDelta > 0 ? '+' : '' }}{{ number_format($adjustedDelta, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</section>
@endif
