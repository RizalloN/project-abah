<div class="rm-mikro-table-wrap">
    <table class="rm-mikro-table" style="min-width: 3000px;">
        <thead>
            <tr>
                <th rowspan="3">No</th><th rowspan="3">BC</th><th rowspan="3">Cabang</th><th rowspan="3">BOH</th><th rowspan="3">Jumlah Unit</th><th rowspan="3">Jumlah Mantri</th>
                <th colspan="3" class="group-head">KAUNIT Sesuai PDWK</th>
                <th colspan="7" class="group-head">MBM</th>
                <th colspan="7" class="group-head">PINCA</th>
                <th colspan="3" class="group-head">RMBH Override</th>
                <th colspan="4" class="group-head">TOTAL s.d {{ $selectedPeriodShortLabel }}</th>
            </tr>
            <tr>
                <th colspan="2">Realisasi</th><th rowspan="2">% Rasio</th>
                <th colspan="2">Sesuai PDWK</th><th colspan="2">Override</th><th colspan="2">Total</th><th rowspan="2">% Rasio</th>
                <th colspan="2">Sesuai PDWK</th><th colspan="2">Override</th><th colspan="2">Total</th><th rowspan="2">% Rasio</th>
                <th colspan="2">Override</th><th rowspan="2">% Rasio</th>
                <th rowspan="2">Deb</th><th rowspan="2">Rp.Juta</th><th rowspan="2">Tiket Size</th><th rowspan="2">Ratas Mantri / HK</th>
            </tr>
            <tr>
                @for ($i = 0; $i < 9; $i++)
                    <th>Deb</th><th>Rp.Juta</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td><td>{{ $row['bc'] }}</td><td class="strong">{{ $row['cabang'] }}</td><td>{{ $row['boh'] }}</td><td class="text-right">{{ $formatAmount($row['jumlah_unit'] ?? 0) }}</td><td class="text-right">{{ $formatAmount($row['jumlah_mantri'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatAmount($row['kaunit_pdwk_deb'] ?? 0) }}</td><td class="text-right">{{ $formatJuta($row['kaunit_pdwk_os'] ?? 0) }}</td><td class="text-right {{ $gradientClass($row['kaunit_pdwk_ratio'] ?? 0, 0, $payload['max_values']['ratio'], false) }}">{{ $formatPercent($row['kaunit_pdwk_ratio'] ?? 0) }}</td>
                    @foreach (['mbm_pdwk', 'mbm_override', 'mbm_total'] as $prefix)
                        <td class="text-right">{{ $formatAmount($row[$prefix . '_deb'] ?? 0) }}</td><td class="text-right">{{ $formatJuta($row[$prefix . '_os'] ?? 0) }}</td>
                    @endforeach
                    <td class="text-right {{ $gradientClass($row['mbm_total_ratio'] ?? 0, 0, $payload['max_values']['ratio'], true) }}">{{ $formatPercent($row['mbm_total_ratio'] ?? 0) }}</td>
                    @foreach (['pinca_pdwk', 'pinca_override', 'pinca_total'] as $prefix)
                        <td class="text-right">{{ $formatAmount($row[$prefix . '_deb'] ?? 0) }}</td><td class="text-right">{{ $formatJuta($row[$prefix . '_os'] ?? 0) }}</td>
                    @endforeach
                    <td class="text-right {{ $gradientClass($row['pinca_total_ratio'] ?? 0, 0, $payload['max_values']['ratio'], true) }}">{{ $formatPercent($row['pinca_total_ratio'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatAmount($row['rmbh_override_deb'] ?? 0) }}</td><td class="text-right">{{ $formatJuta($row['rmbh_override_os'] ?? 0) }}</td><td class="text-right">{{ $formatPercent($row['rmbh_override_ratio'] ?? 0) }}</td>
                    <td class="text-right strong">{{ $formatAmount($row['total_realisasi_deb'] ?? 0) }}</td><td class="text-right strong">{{ $formatJuta($row['total_realisasi_os'] ?? 0) }}</td>
                    <td class="text-right {{ $gradientClass($row['tiket_size'] ?? 0, 0, $payload['max_values']['tiket_size'], true) }}">{{ $formatAmount($row['tiket_size'] ?? 0, 1) }}</td>
                    <td class="text-right {{ $gradientClass($row['ratas_mantri_hk'] ?? 0, 0, $payload['max_values']['ratas_mantri_hk'], true) }}">{{ $formatAmount($row['ratas_mantri_hk'] ?? 0, 1) }}</td>
                </tr>
            @empty
                <tr><td colspan="31" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
