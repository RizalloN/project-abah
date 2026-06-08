<div class="rm-mikro-table-wrap">
    <table class="rm-mikro-table">
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">BC</th>
                <th rowspan="2">Nama Uker</th>
                <th rowspan="2">Cabang</th>
                <th rowspan="2">PIC MBM</th>
                <th colspan="8" class="group-head">Kelolaan Posisi {{ $selectedPeriodLabel }}</th>
                <th colspan="2" class="group-head">Realisasi s.d {{ $selectedPeriodShortLabel }}</th>
            </tr>
            <tr>
                <th>Lancar Deb</th><th>OS Juta</th><th>SML Deb</th><th>OS Juta</th><th>NPL Deb</th><th>OS Juta</th><th>Total Deb</th><th>OS Juta</th><th>Deb</th><th>Plafon Juta</th>
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
                    <td class="text-right">{{ $formatAmount($row['realisasi_deb']) }}</td><td class="text-right">{{ $formatJuta($row['realisasi_os']) }}</td>
                </tr>
            @empty
                <tr><td colspan="15" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
            @if ($rows->isNotEmpty() && $total)
                <tr class="rm-mikro-total">
                    <td class="text-center">{{ $rows->count() + 1 }}</td><td>AREA 6</td><td>GRAND TOTAL</td><td>AREA 6</td><td>-</td>
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
