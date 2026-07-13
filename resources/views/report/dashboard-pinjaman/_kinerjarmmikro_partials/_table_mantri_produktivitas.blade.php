@php
    $realisasiDebMax = max(1, (float) $rows->max('realisasi_deb'));
    $realisasiOsMax = max(1, (float) $rows->max('realisasi_os'));
@endphp

<div class="rm-mikro-pagination" data-mantri-pagination>
    <div class="rm-mikro-pagination__summary" aria-live="polite">
        <strong data-mantri-range>0 baris</strong>
        <span>dari {{ number_format($rows->count(), 0, ',', '.') }} Mantri</span>
    </div>
    <div class="rm-mikro-pagination__controls">
        <label for="mantri-page-size">Tampilkan</label>
        <select id="mantri-page-size" class="rm-mikro-pagination__select" data-mantri-page-size>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="all" selected>Semua</option>
        </select>
        <button type="button" class="rm-mikro-pagination__button" data-mantri-prev aria-label="Halaman sebelumnya" title="Halaman sebelumnya">
            <i class="fas fa-chevron-left" aria-hidden="true"></i>
        </button>
        <span class="rm-mikro-pagination__page" data-mantri-page>1 / 1</span>
        <button type="button" class="rm-mikro-pagination__button" data-mantri-next aria-label="Halaman berikutnya" title="Halaman berikutnya">
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </button>
    </div>
</div>

<div class="rm-mikro-table-wrap rm-mikro-table-wrap--mantri-productivity table-container">
    <table class="rm-mikro-table" style="min-width: 1500px;">
        <thead>
            <tr>
                <th rowspan="2">No</th><th rowspan="2">BC</th><th rowspan="2">Nama Uker</th><th rowspan="2">Nama Mantri</th><th rowspan="2">PN</th>
                <th colspan="5" class="group-head">Disbursment s.d {{ $selectedPeriodShortLabel }} | HK: {{ $payload['working_days'] ?? 0 }}</th>
            </tr>
            <tr><th>Deb</th><th>Rp.Juta</th><th>Tiket Size</th><th>Ratas Mantri / HK</th><th>Ket</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr data-mantri-row data-row-index="{{ $index }}">
                    <td class="text-center">{{ $index + 1 }}</td><td class="strong">{{ $row['bc'] }}</td><td>{{ $row['unit'] }}</td><td class="strong">{{ $row['nama_mantri'] ?? $row['pn_pengelola'] ?? '-' }}</td><td class="text-center strong">{{ $row['pn_mantri'] ?? '-' }}</td>
                    <td class="text-right {{ $gradientClass($row['realisasi_deb'] ?? 0, 0, $realisasiDebMax, true) }}">{{ $formatAmount($row['realisasi_deb'] ?? 0) }}</td>
                    <td class="text-right {{ $gradientClass($row['realisasi_os'] ?? 0, 0, $realisasiOsMax, true) }}">{{ $formatJuta($row['realisasi_os'] ?? 0) }}</td>
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
