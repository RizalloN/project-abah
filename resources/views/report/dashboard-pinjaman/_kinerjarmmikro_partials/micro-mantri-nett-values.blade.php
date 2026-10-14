@php
    $nettMembers = collect(data_get($nettRow, 'members', []));
    $countedMembers = $nettMembers->where('counted', true);
    $nettDrillScope = $nettDrillScope ?? '';
@endphp
@foreach(['pt_non_briguna' => ['pt', 'Mantri PT Non Briguna'], 'contract' => ['contract', 'Mantri Kontrak'], 'briguna' => ['briguna', 'Mantri Briguna']] as $headcountKey => [$category, $headcountTitle])
    <td {!! $nettDrillAttr($countedMembers->where('category', $category), $headcountTitle, $nettDrillScope) !!}>{{ $formatInteger(data_get($nettRow, 'headcount.'.$headcountKey, 0)) }}</td>
@endforeach
@if($hasUnknownStatus)<td {!! $nettDrillAttr($countedMembers->where('category', 'unknown'), 'Mantri status belum terpetakan', $nettDrillScope) !!}>{{ $formatInteger(data_get($nettRow, 'headcount.unknown', 0)) }}</td>@endif
@php
    $nettAmountDrill = $nettDrillAttr($nettMembers, 'Nett Disbursement seluruh Mantri', $nettDrillScope);
@endphp
@foreach($hasGross ? ['realization', 'net_disbursement'] : ['net_disbursement'] as $metric)
    <td class="micro-nett-amount" {!! $nettAmountDrill !!}>{{ $formatJuta(data_get($nettRow, $metric.'.amount', 0)) }}</td>
    <td {!! $nettAmountDrill !!}>{{ $formatJuta(data_get($nettRow, $metric.'.'.($metric === 'net_disbursement' && $hasPersonAverage ? 'average_per_mantri_hke' : 'average_per_hke'), 0)) }}</td>
@endforeach
@foreach(['pt' => 'Mantri PT', 'contract' => 'Mantri Kontrak'] as $tierKey => $tierTitle)
    @foreach(data_get($nettRow, 'tiers.'.$tierKey.'.buckets', []) as $bucket)
        @php
            $bucketDrill = $nettDrillAttr(
                $bucket['people'] ?? [],
                $tierTitle.' · '.$bucket['label'].(!empty($bucket['range']) ? ' ('.$bucket['range'].')' : ''),
                $nettDrillScope,
                true
            );
        @endphp
        <td class="tone-{{ $bucket['key'] }}" {!! $bucketDrill !!}>{{ $formatInteger($bucket['mantri']) }}</td>
        <td class="tone-{{ $bucket['key'] }}" {!! $bucketDrill !!}>{{ $formatPercent($bucket['share']) }}</td>
    @endforeach
@endforeach
