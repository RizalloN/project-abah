@php
    $lt250OsMax = max(1, (float) $rows->max('lt_250_realisasi_os'));
    $gt250OsMax = max(1, (float) $rows->max('gt_250_realisasi_os'));
    $totalOsMax = max(1, (float) $rows->max('total_os'));
@endphp

<div class="rm-mikro-table-wrap table-container">
    <table class="rm-mikro-table" style="min-width: 1500px;">
        <thead>
            <tr><th rowspan="2">No</th><th rowspan="2">Kanca</th><th rowspan="2">PN</th><th rowspan="2">Nama</th><th rowspan="2">BC UKER</th><th rowspan="2">UKER</th><th rowspan="2">KET</th><th colspan="3" class="group-head">&lt;250 Juta</th><th colspan="3" class="group-head">&gt;250 Juta</th><th colspan="2" class="group-head">Total</th></tr>
            <tr><th>Deb</th><th>%</th><th>Plafon Juta</th><th>Deb</th><th>%</th><th>Plafon Juta</th><th>Deb</th><th>Plafon Juta</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td><td>{{ $row['cabang'] }}</td><td>{{ $row['pn'] }}</td><td class="strong">{{ $row['nama'] }}</td><td>{{ $row['branch_code'] }}</td><td>{{ $row['unit'] }}</td><td>{{ $row['ket'] }}</td>
                    <td class="text-right">{{ $formatAmount($row['lt_250_realisasi_deb']) }}</td><td class="text-right">{{ $formatPercent($row['lt_250_pct']) }}</td><td class="text-right {{ $gradientClass($row['lt_250_realisasi_os'], 0, $lt250OsMax, true) }}">{{ $formatJuta($row['lt_250_realisasi_os']) }}</td>
                    <td class="text-right">{{ $formatAmount($row['gt_250_realisasi_deb']) }}</td><td class="text-right">{{ $formatPercent($row['gt_250_pct']) }}</td><td class="text-right {{ $gradientClass($row['gt_250_realisasi_os'], 0, $gt250OsMax, true) }}">{{ $formatJuta($row['gt_250_realisasi_os']) }}</td>
                    <td class="text-right strong">{{ $formatAmount($row['total_deb']) }}</td><td class="text-right strong {{ $gradientClass($row['total_os'], 0, $totalOsMax, true) }}">{{ $formatJuta($row['total_os']) }}</td>
                </tr>
            @empty
                <tr><td colspan="15" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
            @if ($rows->isNotEmpty())
                @php
                    $lt250Os = (float) $rows->sum('lt_250_realisasi_os');
                    $gt250Os = (float) $rows->sum('gt_250_realisasi_os');
                    $totalOs = $lt250Os + $gt250Os;
                    $lt250Pct = $totalOs > 0 ? ($lt250Os / $totalOs) * 100 : 0;
                    $gt250Pct = $totalOs > 0 ? ($gt250Os / $totalOs) * 100 : 0;
                @endphp
                <tr class="rm-mikro-total">
                    <td colspan="7" class="strong">GRAND TOTAL</td>
                    <td class="text-right">{{ $formatAmount($rows->sum('lt_250_realisasi_deb')) }}</td><td class="text-right">{{ $formatPercent($lt250Pct) }}</td><td class="text-right">{{ $formatJuta($lt250Os) }}</td>
                    <td class="text-right">{{ $formatAmount($rows->sum('gt_250_realisasi_deb')) }}</td><td class="text-right">{{ $formatPercent($gt250Pct) }}</td><td class="text-right">{{ $formatJuta($gt250Os) }}</td>
                    <td class="text-right strong">{{ $formatAmount($rows->sum('total_deb')) }}</td><td class="text-right strong">{{ $formatJuta($totalOs) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
