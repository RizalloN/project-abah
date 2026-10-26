@php
    $customerSource = $marketShareSektoral['customer_penetration'] ?? [];
    $customerTotals = $customerSource['totals']['total'] ?? [];
    $customerGroups = array_values(array_filter($customerSource['comparison_groups'] ?? [], static fn ($group) => ($group['key'] ?? '') !== 'total'));
    $customerCount = static fn ($value) => is_numeric($value) ? number_format((float) $value, 0, ',', '.') : '-';
    $customerRatio = static fn ($value) => is_numeric($value) ? number_format((float) $value, 2, ',', '.') . '%' : '-';
@endphp
<section class="mss-panel" aria-labelledby="mss-customer-title">
    <header class="mss-panel-head">
        <div><span class="mss-kicker">Potensi pelaku usaha</span><h2 id="mss-customer-title">Penetrasi nasabah BRI per kelompok usaha</h2>
            <p>Potensi desa kelolaan UKER: {{ $customerSource['source']['label'] ?? 'Spreadsheet pelaku usaha' }}. Debitur: SSA CRAS {{ $customerSource['period_label'] ?? 'September 2026' }}.</p></div>
    </header>
    @if(!empty($customerSource['ready']))
        <div class="mss-kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr))">
            <article class="mss-kpi-card is-industry"><div><small>Potensi pelaku usaha</small><strong>{{ $customerCount($customerTotals['potential'] ?? null) }}</strong><em>Jumlah dari spreadsheet sumber</em></div></article>
            <article class="mss-kpi-card"><div><small>Intra · Debitur aktif</small><strong>{{ $customerCount($customerTotals['intra'] ?? null) }}</strong><em>Status rekening AKTIF</em></div></article>
            <article class="mss-kpi-card"><div><small>Ekstra · Debitur PH</small><strong>{{ $customerCount($customerTotals['extra'] ?? null) }}</strong><em>Status rekening PH</em></div></article>
            <article class="mss-kpi-card"><div><small>Total debitur BRI</small><strong>{{ $customerCount($customerTotals['existing'] ?? null) }}</strong><em>Aktif + PH, termasuk OTHER / tanpa sektor</em></div></article>
            <article class="mss-kpi-card is-share"><div><small>Penetrasi aktif + PH</small><strong>{{ $customerRatio($customerTotals['penetration'] ?? null) }}</strong><em>Total debitur dibanding potensi desa kelolaan</em></div></article>
        </div>
        <div class="mss-table-wrap" tabindex="0" role="region" aria-label="Tabel potensi pelaku usaha dan debitur BRI" data-abah-no-table-guard>
            <table class="mss-table" data-abah-no-table-guard>
                <caption class="sr-only">Potensi dan debitur {{ $marketShareSektoral['selected_scope_label'] ?? '' }}</caption>
                <thead><tr><th scope="col">Kelompok usaha</th><th scope="col">Kategori KBLI sumber</th><th scope="col">Potensi pelaku usaha</th><th scope="col">Intra · Aktif</th><th scope="col">Ekstra · PH</th><th scope="col">Total debitur BRI</th><th scope="col">Penetrasi normalisasi</th></tr></thead>
                <tbody>
                    @foreach($customerGroups as $customerGroup)
                        @php($customerFigures = $customerSource['totals'][$customerGroup['key']] ?? [])
                        <tr><th scope="row" class="sector-cell">{{ $customerGroup['label'] }}</th><td>{{ implode(', ', $customerGroup['categories'] ?? []) ?: 'Tidak tersedia' }}</td><td>{{ $customerGroup['key'] === 'unclassified' ? '-' : $customerCount($customerFigures['potential'] ?? null) }}</td><td>{{ $customerCount($customerFigures['intra'] ?? null) }}</td><td>{{ $customerCount($customerFigures['extra'] ?? null) }}</td><td>{{ $customerCount($customerFigures['existing'] ?? null) }}</td><td title="Rasio sumber: {{ $customerRatio($customerFigures['penetration_raw'] ?? null) }}">{{ $customerRatio($customerFigures['penetration'] ?? null) }} @if(!empty($customerFigures['normalization_applied']))<small>Dinormalisasi</small>@endif</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr><th scope="row" colspan="2">Total termasuk OTHER</th><td>{{ $customerCount($customerTotals['potential'] ?? null) }}</td><td>{{ $customerCount($customerTotals['intra'] ?? null) }}</td><td>{{ $customerCount($customerTotals['extra'] ?? null) }}</td><td>{{ $customerCount($customerTotals['existing'] ?? null) }}</td><td>{{ $customerRatio($customerTotals['penetration'] ?? null) }}</td></tr></tfoot>
            </table>
        </div>
        <p>{{ $customerSource['unit_potential_note'] ?? '' }} OTHER / tanpa sektor tetap masuk total, tanpa rasio tersendiri.</p>
        @foreach($customerSource['units'] ?? [] as $customerUnit)
            @if(!empty($customerUnit['potential_partial']))
                <p role="note">{{ $customerUnit['name'] }}: {{ $customerCount($customerUnit['matched_village_count'] ?? null) }} dari {{ $customerCount($customerUnit['owned_village_count'] ?? null) }} desa memiliki data potensi. Rasio memakai desa yang sudah tersedia.
                    @foreach($customerUnit['missing_population_villages'] ?? [] as $missingVillage)
                        Belum tersedia: {{ $missingVillage['name'] }}, {{ $missingVillage['district'] }}.
                    @endforeach
                </p>
            @endif
        @endforeach
        @if(!empty($customerSource['unmapped_units']))
            <p role="note">{{ count($customerSource['unmapped_units']) }} UKER belum memiliki acuan desa dan disajikan terpisah dari penetrasi:
                @foreach($customerSource['unmapped_units'] as $customerUnit)
                    {{ $customerUnit['name'] }} ({{ $customerCount($customerUnit['values']['total']['intra'] ?? null) }} aktif, {{ $customerCount($customerUnit['values']['total']['extra'] ?? null) }} PH){{ $loop->last ? '.' : ';' }}
                @endforeach
            </p>
        @endif
        <footer class="mss-chart-footer"><span>{{ $customerSource['note'] ?? '' }} Potensi subsektor rinci belum tersedia; rinciannya memakai acuan kategori sektor induk.</span>
            <a href="{{ $customerSource['source']['url'] ?? '#' }}" target="_blank" rel="noopener">Potensi usaha</a>
            <a href="{{ $customerSource['ownership_source']['url'] ?? '#' }}" target="_blank" rel="noopener">Desa kelolaan UKER</a></footer>
    @else
        <p class="alert alert-info" role="status">{{ $customerSource['message'] ?? 'Data potensi pelaku usaha dan SSA CRAS September 2026 belum tersedia.' }}</p>
    @endif
</section>
