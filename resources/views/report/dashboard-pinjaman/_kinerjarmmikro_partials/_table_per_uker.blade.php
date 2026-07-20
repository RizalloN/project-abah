@php
    $realisasiDebMax = max(1, (float) $rows->max('realisasi_deb'));
    $realisasiOsMax = max(1, (float) $rows->max('realisasi_os'));
@endphp

<div class="rm-mikro-table-wrap table-container">
    <table class="rm-mikro-table">
        <thead>
            <tr>
                <th rowspan="3">No</th>
                <th rowspan="3">BC</th>
                <th rowspan="3">Nama Uker</th>
                <th rowspan="3">Cabang</th>
                <th rowspan="3">PIC MBM</th>
                <th colspan="8" class="group-head">Kelolaan Posisi {{ $selectedPeriodLabel }}</th>
                <th colspan="2" rowspan="2" class="group-head">Realisasi s.d {{ $selectedPeriodShortLabel }}</th>
            </tr>
            <tr>
                <th colspan="2">Lancar</th>
                <th colspan="2">SML</th>
                <th colspan="2">NPL</th>
                <th colspan="2">Total</th>
            </tr>
            <tr>
                <th>Deb</th><th>OS Lancar</th><th>Deb</th><th>OS SML</th><th>Deb</th><th>OS NPL</th><th>Deb</th><th>OS Total</th><th>Deb</th><th>Plafon Juta</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td><td class="strong">{{ $row['bc'] }}</td><td>{{ $row['unit'] }}</td><td class="strong">{{ $row['cabang'] }}</td><td>{{ $row['pic_mbm'] }}</td>
                    <td class="text-right">{{ $formatAmount($row['lancar_deb']) }}</td><td class="text-right">{{ $formatJuta($row['lancar_os']) }}</td>
                    <td class="text-right">{{ $formatAmount($row['sml_deb']) }}</td><td class="text-right">{{ $formatJuta($row['sml_os']) }}</td>
                    <td class="text-right">{{ $formatAmount($row['npl_deb']) }}</td><td class="text-right">{{ $formatJuta($row['npl_os']) }}</td>
                    <td class="text-right strong">{{ $formatAmount($row['total_deb']) }}</td><td class="text-right strong">{{ $formatJuta($row['total_os']) }}</td>
                    <td class="text-right {{ $gradientClass($row['realisasi_deb'], 0, $realisasiDebMax, true) }}">{{ $formatAmount($row['realisasi_deb']) }}</td><td class="text-right {{ $gradientClass($row['realisasi_os'], 0, $realisasiOsMax, true) }}">{{ $formatJuta($row['realisasi_os']) }}</td>
                </tr>
            @empty
                <tr><td colspan="15" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
            @if ($rows->isNotEmpty() && $total)
                <tr class="rm-mikro-total">
                    <td class="text-center">{{ $rows->count() + 1 }}</td><td>{{ $userBranchScope['upper_label'] ?? 'AREA 6' }}</td><td>GRAND TOTAL</td><td>{{ $userBranchScope['upper_label'] ?? 'AREA 6' }}</td><td>-</td>
                    <td class="text-right">{{ $formatAmount($total['lancar_deb'] ?? 0) }}</td><td class="text-right">{{ $formatJuta($total['lancar_os'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatAmount($total['sml_deb'] ?? 0) }}</td><td class="text-right">{{ $formatJuta($total['sml_os'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatAmount($total['npl_deb'] ?? 0) }}</td><td class="text-right">{{ $formatJuta($total['npl_os'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatAmount($total['total_deb'] ?? 0) }}</td><td class="text-right">{{ $formatJuta($total['total_os'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatAmount($total['realisasi_deb'] ?? 0) }}</td><td class="text-right">{{ $formatJuta($total['realisasi_os'] ?? 0) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
