<div class="rm-mikro-table-wrap">
    <table class="rm-mikro-table">
        <thead>
            <tr>
                <th rowspan="2">No</th><th rowspan="2">Kanca</th><th rowspan="2">PN</th><th rowspan="2">Nama</th><th rowspan="2">BC UKER</th><th rowspan="2">UKER</th>
                <th colspan="8" class="group-head">Kelola Posisi {{ $selectedPeriodLabel }}</th><th colspan="2" class="group-head">Realisasi s.d {{ $selectedPeriodShortLabel }}</th>
            </tr>
            <tr>
                <th>Lancar Deb</th><th>OS Juta</th><th>SML Deb</th><th>OS Juta</th><th>NPL Deb</th><th>OS Juta</th><th>Total Deb</th><th>OS Juta</th><th>Deb</th><th>OS Juta</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td><td class="strong">{{ $row['cabang'] }}</td><td>{{ $row['pn'] }}</td><td class="strong">{{ $row['nama'] }}</td><td>{{ $row['branch_code'] }}</td><td>{{ $row['unit'] }}</td>
                    <td class="text-right">{{ $formatAmount($row['lancar_deb']) }}</td><td class="text-right">{{ $formatJuta($row['lancar_os']) }}</td>
                    <td class="text-right">{{ $formatAmount($row['sml_deb']) }}</td><td class="text-right">{{ $formatJuta($row['sml_os']) }}</td>
                    <td class="text-right">{{ $formatAmount($row['npl_deb']) }}</td><td class="text-right">{{ $formatJuta($row['npl_os']) }}</td>
                    <td class="text-right strong">{{ $formatAmount($row['total_deb']) }}</td><td class="text-right strong">{{ $formatJuta($row['total_os']) }}</td>
                    <td class="text-right">{{ $formatAmount($row['realisasi_deb']) }}</td><td class="text-right">{{ $formatJuta($row['realisasi_os']) }}</td>
                </tr>
            @empty
                <tr><td colspan="16" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
