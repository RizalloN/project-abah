<div class="rm-mikro-table-wrap">
    <table class="rm-mikro-table" style="min-width: 1200px;">
        <thead>
            <tr><th rowspan="2">No</th><th rowspan="2">BC</th><th rowspan="2">Cabang</th><th rowspan="2">Pembina</th><th colspan="3" class="group-head">Jumlah RM Mikro</th><th colspan="4" class="group-head">Produktivitas {{ $selectedMonthLabel }}</th></tr>
            <tr><th>Total RM</th><th>Sudah Real</th><th>BLM Real</th><th>Deb</th><th>Rp.Juta</th><th>Target Rp.Juta</th><th>%</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td><td>{{ $row['bc'] }}</td><td class="strong">{{ $row['cabang'] }}</td><td>{{ $row['pembina'] }}</td>
                    <td class="text-right">{{ $formatAmount($row['total_rm']) }}</td><td class="text-right">{{ $formatAmount($row['sudah_real']) }}</td><td class="text-right">{{ $formatAmount($row['belum_real']) }}</td>
                    <td class="text-right">{{ $formatAmount($row['realisasi_deb']) }}</td><td class="text-right">{{ $formatJuta($row['realisasi_os']) }}</td><td class="text-right">{{ $formatJuta($row['target_os']) }}</td><td class="{{ $achievementClass($row['realisasi_os'], $row['target_os']) }} text-right">{{ $formatPercent($row['pct_target']) }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
