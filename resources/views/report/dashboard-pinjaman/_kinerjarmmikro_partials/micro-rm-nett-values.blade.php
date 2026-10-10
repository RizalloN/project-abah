<td>{{ $formatInteger($rmMetrics['rm_count'] ?? 0) }}</td>
<td>{{ $formatInteger($rmMetrics['realisasi_deb'] ?? 0) }}</td><td>{{ $formatJuta($rmMetrics['realisasi_os'] ?? 0) }}</td>
@foreach($rmTiers as $key => $tier)
    <td>{{ $formatInteger(data_get($rmMetrics, 'counts.'.$key, 0)) }}</td><td>{{ number_format(data_get($rmMetrics, 'percentages.'.$key, 0), 1, ',', '.') }}%</td>
    @if($key === 'low')<td class="micro-nett-acceleration">{{ number_format($rmMetrics['need_acceleration_percentage'] ?? 0, 1, ',', '.') }}%</td>@endif
@endforeach
