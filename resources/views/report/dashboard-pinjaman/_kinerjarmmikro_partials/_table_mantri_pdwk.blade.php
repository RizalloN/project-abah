@php
    $pdwkPrefixes = [
        'kaunit_pdwk', 'kaunit_override',
        'mbm_pdwk', 'mbm_override', 'mbm_total',
        'pinca_pdwk', 'pinca_override', 'pinca_total',
        'rmbh_override', 'total_realisasi',
    ];
    $pdwkOsMax = collect($pdwkPrefixes)->mapWithKeys(fn ($prefix) => [
        $prefix => max(1, (float) $rows->max($prefix . '_os')),
    ])->all();
@endphp

<div class="rm-mikro-table-wrap table-container">
    <table class="rm-mikro-table" style="min-width: 2400px;">
        <thead>
            <tr>
                <th rowspan="3">No</th><th rowspan="3">BC</th><th rowspan="3">Nama Uker</th><th rowspan="3">Cabang</th><th rowspan="3">MBM</th>
                <th colspan="4" class="group-head">KAUNIT s.d {{ $selectedPeriodShortLabel }}</th>
                <th colspan="6" class="group-head">MBM</th>
                <th colspan="6" class="group-head">PINCA</th>
                <th colspan="2" rowspan="2" class="group-head">RMBH Override</th>
                <th colspan="2" rowspan="2" class="group-head">Total Realisasi</th>
            </tr>
            <tr>
                <th colspan="2">Sesuai PDWK</th><th colspan="2">Override</th>
                <th colspan="2">Sesuai PDWK</th><th colspan="2">Override</th><th colspan="2">Total</th>
                <th colspan="2">Sesuai PDWK</th><th colspan="2">Override</th><th colspan="2">Total</th>
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
                    <td class="text-center">{{ $index + 1 }}</td><td class="strong">{{ $row['bc'] }}</td><td>{{ $row['unit'] }}</td><td class="strong">{{ $row['cabang'] }}</td><td>{{ $row['mbm_name'] }}</td>
                    @foreach ([
                        'kaunit_pdwk', 'kaunit_override',
                        'mbm_pdwk', 'mbm_override', 'mbm_total',
                        'pinca_pdwk', 'pinca_override', 'pinca_total',
                        'rmbh_override', 'total_realisasi',
                    ] as $prefix)
                        <td class="text-right">{{ $formatAmount($row[$prefix . '_deb'] ?? 0) }}</td>
                        <td class="text-right {{ $gradientClass($row[$prefix . '_os'] ?? 0, 0, $pdwkOsMax[$prefix] ?? 1, true) }}">{{ $formatJuta($row[$prefix . '_os'] ?? 0) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="25" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
