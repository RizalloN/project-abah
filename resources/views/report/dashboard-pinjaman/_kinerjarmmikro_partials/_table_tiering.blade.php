<div class="rm-mikro-table-wrap">
    <table class="rm-mikro-table" style="min-width: 1500px;">
        <thead>
            <tr><th rowspan="2">No</th><th rowspan="2">Kanca</th><th rowspan="2">PN</th><th rowspan="2">Nama</th><th rowspan="2">BC UKER</th><th rowspan="2">UKER</th><th rowspan="2">KET</th><th colspan="3" class="group-head">&lt;250 Juta</th><th colspan="3" class="group-head">&gt;250 Juta</th><th colspan="2" class="group-head">Total</th></tr>
            <tr><th>Deb</th><th>%</th><th>Rp.Juta</th><th>Deb</th><th>%</th><th>Rp.Juta</th><th>Deb</th><th>Rp.Juta</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td><td>{{ $row['cabang'] }}</td><td>{{ $row['pn'] }}</td><td class="strong">{{ $row['nama'] }}</td><td>{{ $row['branch_code'] }}</td><td>{{ $row['unit'] }}</td><td>{{ $row['ket'] }}</td>
                    <td class="text-right">{{ $formatAmount($row['lt_250_realisasi_deb']) }}</td><td class="text-right">{{ $formatPercent($row['lt_250_pct']) }}</td><td class="text-right">{{ $formatJuta($row['lt_250_realisasi_os']) }}</td>
                    <td class="text-right">{{ $formatAmount($row['gt_250_realisasi_deb']) }}</td><td class="text-right">{{ $formatPercent($row['gt_250_pct']) }}</td><td class="text-right">{{ $formatJuta($row['gt_250_realisasi_os']) }}</td>
                    <td class="text-right strong">{{ $formatAmount($row['total_deb']) }}</td><td class="text-right strong">{{ $formatJuta($row['total_os']) }}</td>
                </tr>
            @empty
                <tr><td colspan="15" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
