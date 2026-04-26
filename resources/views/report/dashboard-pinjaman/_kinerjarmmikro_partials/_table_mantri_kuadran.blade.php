<div class="rm-mikro-table-wrap">
    <table class="rm-mikro-table" style="min-width: 1500px;">
        <thead>
            <tr>
                <th rowspan="2">No</th><th rowspan="2">BC</th><th rowspan="2">Nama Uker</th><th rowspan="2">Cabang</th><th rowspan="2">Jumlah Mantri</th>
                <th colspan="5" class="group-head">Disbursment s.d {{ $selectedPeriodShortLabel }} | HK: {{ $payload['working_days'] ?? 0 }}</th>
            </tr>
            <tr><th>Deb</th><th>Rp.Juta</th><th>Tiket Size</th><th>Ratas Mantri / HK</th><th>Ket</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td><td class="strong">{{ $row['bc'] }}</td><td>{{ $row['unit'] }}</td><td class="strong">{{ $row['cabang'] }}</td><td class="text-right">{{ $formatAmount($row['jumlah_mantri'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatAmount($row['realisasi_deb'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatJuta($row['realisasi_os'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatAmount($row['tiket_size'] ?? 0, 1) }}</td>
                    <td class="text-right {{ $gradientClass($row['ratas_mantri_hk'] ?? 0, 0, $payload['max_values']['ratas_mantri_hk'], true) }}">{{ $formatAmount($row['ratas_mantri_hk'] ?? 0, 1) }}</td>
                    <td class="strong">{{ $row['ket'] }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
