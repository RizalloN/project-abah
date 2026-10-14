@php
    $nettDrillPeople = collect($nettDrillPeople ?? []);
    $nettDrillScope = $nettDrillScope ?? '';
    $rmAllDrill = $nettDrillAttr($nettDrillPeople, 'Nett Disbursement seluruh RM', $nettDrillScope);
@endphp
<td {!! $rmAllDrill !!}>{{ $formatInteger($rmMetrics['rm_count'] ?? 0) }}</td>
<td {!! $rmAllDrill !!}>{{ $formatInteger($rmMetrics['realisasi_deb'] ?? 0) }}</td><td {!! $rmAllDrill !!}>{{ $formatJuta($rmMetrics['realisasi_os'] ?? 0) }}</td>
@foreach($rmTiers as $key => $tier)
    @php
        $rmTierDrill = $nettDrillAttr(
            $nettDrillPeople->where('tier', $key),
            'RM · '.$tier['label'].(!empty($tier['range']) ? ' ('.$tier['range'].')' : ''),
            $nettDrillScope,
            true
        );
    @endphp
    <td {!! $rmTierDrill !!}>{{ $formatInteger(data_get($rmMetrics, 'counts.'.$key, 0)) }}</td><td {!! $rmTierDrill !!}>{{ number_format(data_get($rmMetrics, 'percentages.'.$key, 0), 1, ',', '.') }}%</td>
    @if($key === 'low')<td class="micro-nett-acceleration" {!! $nettDrillAttr($nettDrillPeople->whereIn('tier', ['zero', 'extreme_low', 'low']), 'RM Need Acceleration', $nettDrillScope, true) !!}>{{ number_format($rmMetrics['need_acceleration_percentage'] ?? 0, 1, ',', '.') }}%</td>@endif
@endforeach
