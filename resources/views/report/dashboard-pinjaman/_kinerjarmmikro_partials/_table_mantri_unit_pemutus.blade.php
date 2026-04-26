<div class="rm-mikro-table-wrap">
    <table class="rm-mikro-table" style="min-width: 2100px;">
        <thead>
            <tr>
                <th rowspan="3">No</th>
                <th rowspan="3">BC</th>
                <th rowspan="3">Nama Uker</th>
                <th rowspan="3">Cabang</th>
                <th rowspan="3">MBM</th>
                <th colspan="10" class="group-head">Tanggal {{ $selectedPeriodShortLabel }}</th>
                <th colspan="10" class="group-head">Akumulasi s.d {{ $selectedPeriodShortLabel }}</th>
            </tr>
            <tr>
                @foreach (['KAUNIT', 'MBM', 'PINCA', 'RMBH', 'TOTAL'] as $head)
                    <th colspan="2">{{ $head }}</th>
                @endforeach
                @foreach (['KAUNIT', 'MBM', 'PINCA', 'RMBH', 'TOTAL'] as $head)
                    <th colspan="2">{{ $head }}</th>
                @endforeach
            </tr>
            <tr>
                @for ($i = 0; $i < 10; $i++)
                    <th>Deb</th><th>Rp.Juta</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="strong">{{ $row['bc'] }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td class="strong">{{ $row['cabang'] }}</td>
                    <td>{{ $row['mbm_name'] }}</td>
                    @foreach (['kaunit', 'mbm', 'pinca', 'rmbh'] as $role)
                        <td class="text-right">{{ $formatAmount($row[$role . '_period_deb'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatJuta($row[$role . '_period_os'] ?? 0) }}</td>
                    @endforeach
                    <td class="text-right strong">{{ $formatAmount($row['period_total_deb'] ?? 0) }}</td>
                    <td class="text-right strong">{{ $formatJuta($row['period_total_os'] ?? 0) }}</td>
                    @foreach (['kaunit', 'mbm', 'pinca', 'rmbh'] as $role)
                        <td class="text-right">{{ $formatAmount($row[$role . '_mtd_deb'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatJuta($row[$role . '_mtd_os'] ?? 0) }}</td>
                    @endforeach
                    <td class="text-right strong">{{ $formatAmount($row['mtd_total_deb'] ?? 0) }}</td>
                    <td class="text-right strong">{{ $formatJuta($row['mtd_total_os'] ?? 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="25" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
