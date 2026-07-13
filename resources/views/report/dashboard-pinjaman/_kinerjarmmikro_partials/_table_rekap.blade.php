@php
    $realisasiDebMax = max(1, (float) $rows->max('realisasi_deb'));
    $realisasiOsMax = max(1, (float) $rows->max('realisasi_os'));
@endphp

<div class="rm-mikro-table-wrap table-container">
    <table class="rm-mikro-table" style="min-width: 1200px;">
        <thead>
            <tr><th rowspan="2">No</th><th rowspan="2">BC</th><th rowspan="2">Cabang</th><th rowspan="2">Pembina</th><th colspan="3" class="group-head">Jumlah RM Mikro</th><th colspan="4" class="group-head">Produktivitas {{ $selectedMonthLabel }}</th></tr>
            <tr><th>Total RM</th><th>Sudah Real</th><th>BLM Real</th><th>Deb</th><th>Plafon Juta</th><th>Target Rp.Juta</th><th>%</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td><td>{{ $row['bc'] }}</td><td class="strong">{{ $row['cabang'] }}</td><td>{{ $row['pembina'] }}</td>
                    <td class="text-right">{{ $formatAmount($row['total_rm']) }}</td><td class="text-right">{{ $formatAmount($row['sudah_real']) }}</td><td class="text-right">{{ $formatAmount($row['belum_real']) }}</td>
                    <td class="text-right {{ $gradientClass($row['realisasi_deb'], 0, $realisasiDebMax, true) }}">{{ $formatAmount($row['realisasi_deb']) }}</td><td class="text-right {{ $gradientClass($row['realisasi_os'], 0, $realisasiOsMax, true) }}">{{ $formatJuta($row['realisasi_os']) }}</td><td class="text-right">{{ $formatJuta($row['target_os']) }}</td><td class="{{ $achievementClass($row['realisasi_os'], $row['target_os']) }} text-right">{{ $formatPercent($row['pct_target']) }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
            @if ($rows->isNotEmpty())
                @php
                    $totalRm = (int) $rows->sum('total_rm');
                    $totalSudahReal = (int) $rows->sum('sudah_real');
                    $totalBelumReal = (int) $rows->sum('belum_real');
                    $totalRealisasiDeb = (int) $rows->sum('realisasi_deb');
                    $totalRealisasiOs = (float) $rows->sum('realisasi_os');
                    $totalTargetOs = (float) $rows->sum('target_os');
                    $totalPct = $totalTargetOs > 0 ? ($totalRealisasiOs / $totalTargetOs) * 100 : 0;
                @endphp
                <tr class="rm-mikro-total">
                    <td colspan="4" class="strong">GRAND TOTAL</td>
                    <td class="text-right">{{ $formatAmount($totalRm) }}</td><td class="text-right">{{ $formatAmount($totalSudahReal) }}</td><td class="text-right">{{ $formatAmount($totalBelumReal) }}</td>
                    <td class="text-right">{{ $formatAmount($totalRealisasiDeb) }}</td><td class="text-right">{{ $formatJuta($totalRealisasiOs) }}</td><td class="text-right">{{ $formatJuta($totalTargetOs) }}</td><td class="{{ $achievementClass($totalRealisasiOs, $totalTargetOs) }} text-right">{{ $formatPercent($totalPct) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
