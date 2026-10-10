@foreach(['pt_non_briguna', 'contract', 'briguna'] as $headcountKey)
    <td>{{ $formatInteger(data_get($nettRow, 'headcount.'.$headcountKey, 0)) }}</td>
@endforeach
@if($hasUnknownStatus)<td>{{ $formatInteger(data_get($nettRow, 'headcount.unknown', 0)) }}</td>@endif
@foreach($hasGross ? ['realization', 'net_disbursement'] : ['net_disbursement'] as $metric)
    <td class="micro-nett-amount">{{ $formatJuta(data_get($nettRow, $metric.'.amount', 0)) }}</td>
    <td>{{ $formatJuta(data_get($nettRow, $metric.'.'.($metric === 'net_disbursement' && $hasPersonAverage ? 'average_per_mantri_hke' : 'average_per_hke'), 0)) }}</td>
@endforeach
@foreach(['pt', 'contract'] as $tierKey)
    @foreach(data_get($nettRow, 'tiers.'.$tierKey.'.buckets', []) as $bucket)
        <td class="tone-{{ $bucket['key'] }}">{{ $formatInteger($bucket['mantri']) }}</td>
        <td class="tone-{{ $bucket['key'] }}">{{ $formatPercent($bucket['share']) }}</td>
    @endforeach
@endforeach
