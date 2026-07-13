@php
    $pemutusRoles = ['kaunit', 'mbm', 'pinca', 'rmbh'];
    $periodOsMax = collect($pemutusRoles)->mapWithKeys(fn ($role) => [
        $role => max(1, (float) $rows->max($role . '_period_os')),
    ])->all();
    $mtdOsMax = collect($pemutusRoles)->mapWithKeys(fn ($role) => [
        $role => max(1, (float) $rows->max($role . '_mtd_os')),
    ])->all();
    $periodTotalOsMax = max(1, (float) $rows->max('period_total_os'));
    $mtdTotalOsMax = max(1, (float) $rows->max('mtd_total_os'));
@endphp

<div class="rm-mikro-table-wrap table-container">
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
                    @foreach ($pemutusRoles as $role)
                        <td class="text-right">{{ $formatAmount($row[$role . '_period_deb'] ?? 0) }}</td>
                        <td class="text-right {{ $gradientClass($row[$role . '_period_os'] ?? 0, 0, $periodOsMax[$role] ?? 1, true) }}">{{ $formatJuta($row[$role . '_period_os'] ?? 0) }}</td>
                    @endforeach
                    <td class="text-right strong">{{ $formatAmount($row['period_total_deb'] ?? 0) }}</td>
                    <td class="text-right strong {{ $gradientClass($row['period_total_os'] ?? 0, 0, $periodTotalOsMax, true) }}">{{ $formatJuta($row['period_total_os'] ?? 0) }}</td>
                    @foreach ($pemutusRoles as $role)
                        <td class="text-right">{{ $formatAmount($row[$role . '_mtd_deb'] ?? 0) }}</td>
                        <td class="text-right {{ $gradientClass($row[$role . '_mtd_os'] ?? 0, 0, $mtdOsMax[$role] ?? 1, true) }}">{{ $formatJuta($row[$role . '_mtd_os'] ?? 0) }}</td>
                    @endforeach
                    <td class="text-right strong">{{ $formatAmount($row['mtd_total_deb'] ?? 0) }}</td>
                    <td class="text-right strong {{ $gradientClass($row['mtd_total_os'] ?? 0, 0, $mtdTotalOsMax, true) }}">{{ $formatJuta($row['mtd_total_os'] ?? 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="25" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
