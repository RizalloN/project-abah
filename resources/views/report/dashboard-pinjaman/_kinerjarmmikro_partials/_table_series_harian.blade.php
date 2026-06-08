<div class="rm-mikro-table-wrap">
    <table class="rm-mikro-table" style="min-width: 1720px;">
        <thead>
            <tr>
                <th rowspan="2">No</th><th rowspan="2">Kanca</th><th rowspan="2">PN</th><th rowspan="2">Nama</th><th rowspan="2">BC UKER</th><th rowspan="2">UKER</th>
                <th colspan="2" class="group-head">W1</th><th colspan="2" class="group-head">W2</th><th colspan="2" class="group-head">W3</th><th colspan="2" class="group-head">W4</th>
                <th colspan="2" class="group-head">Total</th><th rowspan="2">Target s.d Akhir Bulan</th><th rowspan="2">Penc. Target</th>
            </tr>
            <tr><th>Deb</th><th>Plafon Juta</th><th>Deb</th><th>Plafon Juta</th><th>Deb</th><th>Plafon Juta</th><th>Deb</th><th>Plafon Juta</th><th>Deb</th><th>Plafon Juta</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td><td>{{ $row['cabang'] }}</td><td>{{ $row['pn'] }}</td><td class="strong">{{ $row['nama'] }}</td><td>{{ $row['branch_code'] }}</td><td>{{ $row['unit'] }}</td>
                    @foreach (['w1', 'w2', 'w3', 'w4'] as $week)
                        <td class="text-right">{{ $formatAmount($row[$week . '_deb'] ?? 0) }}</td>
                        <td class="text-right {{ $achievementClass(($row[$week . '_os'] ?? 0) / 1000000, $weeklyTargetJuta) }}">{{ $formatJuta($row[$week . '_os'] ?? 0) }}</td>
                    @endforeach
                    <td class="text-right strong">{{ $formatAmount($row['total_deb']) }}</td><td class="text-right strong">{{ $formatJuta($row['total_os']) }}</td><td class="text-right">{{ $formatAmount($targetMonthlyJuta) }}</td>
                    <td>
                        <div class="target-bar"><div class="target-bar__track"><div class="target-bar__fill" style="width: {{ min(100, max(0, (float) $row['pct_target'])) }}%;"></div></div><div class="text-center strong mt-1">{{ $formatPercent($row['pct_target']) }}</div></div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="18" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
            @if ($rows->isNotEmpty())
                @php
                    $totalOs = (float) $rows->sum('total_os');
                    $totalTargetJuta = $rows->count() * (float) $targetMonthlyJuta;
                    $totalPct = $totalTargetJuta > 0 ? (($totalOs / 1000000) / $totalTargetJuta) * 100 : 0;
                @endphp
                <tr class="rm-mikro-total">
                    <td colspan="6" class="strong">GRAND TOTAL</td>
                    @foreach (['w1', 'w2', 'w3', 'w4'] as $week)
                        <td class="text-right">{{ $formatAmount($rows->sum($week . '_deb')) }}</td>
                        <td class="text-right">{{ $formatJuta($rows->sum($week . '_os')) }}</td>
                    @endforeach
                    <td class="text-right strong">{{ $formatAmount($rows->sum('total_deb')) }}</td><td class="text-right strong">{{ $formatJuta($totalOs) }}</td><td class="text-right">{{ $formatAmount($totalTargetJuta) }}</td>
                    <td class="text-center strong">{{ $formatPercent($totalPct) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
