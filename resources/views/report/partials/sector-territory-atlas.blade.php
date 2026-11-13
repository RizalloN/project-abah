@php
    $sectorSource = $payload['customer_penetration'] ?? [];
    $sectorTotals = $sectorSource['totals']['total'] ?? [];
    $sectorGroups = array_values(array_filter($sectorSource['comparison_groups'] ?? [], static fn ($group) => ($group['key'] ?? '') !== 'total'));
    $sectorCount = static fn ($value) => is_numeric($value) ? number_format((float) $value, 0, ',', '.') : '—';
    $sectorRatio = static fn ($value) => is_numeric($value) ? number_format((float) $value, 2, ',', '.') . '%' : '—';
    $branchColors = ['madiun' => '#1678c8', 'magetan' => '#008b87', 'ngawi' => '#6467c4', 'ponorogo' => '#c0792c'];
    $sectorBranches = collect($sectorSource['branches'] ?? [])->keyBy('key');
    $districtFilterReady = $selectedScope !== 'area6' && isset($scopes[$selectedScope]);
    $sectorDistricts = $districtFilterReady ? collect($sectorSource['districts'] ?? [])->unique('code')->sortBy('name')->values() : collect();
    $compactEntity = static fn ($row) => array_intersect_key($row, array_flip(['key', 'label', 'code', 'name', 'regency', 'branch', 'allocation_estimate', 'values', 'village_count', 'potential_partial']));
    $atlasPayload = [
        'ready' => (bool) ($sectorSource['ready'] ?? false),
        'scope' => $selectedScope,
        'scopeLabel' => $payload['selected_scope_label'] ?? 'Area 6',
        'geoUrl' => asset(config('marketshare-geography.source.geojson_path', 'data/marketshare-area6-kecamatan.geojson')),
        'regions' => config('marketshare-geography.branches', []),
        'colors' => $branchColors,
        'groups' => $sectorSource['comparison_groups'] ?? [],
        'totals' => $sectorSource['totals'] ?? [],
        'districts' => array_map($compactEntity, $sectorSource['districts'] ?? []),
        'branches' => array_map($compactEntity, $sectorSource['branches'] ?? []),
    ];
@endphp

<section class="mss-atlas" id="mss-atlas" aria-labelledby="mss-atlas-heading">
    @if(!empty($sectorSource['ready']))
        <div class="mss-overview" aria-label="Ringkasan potensi dan debitur wilayah cabang">
            <div class="mss-overview-item"><span>Potensi pelaku usaha</span><strong>{{ $sectorCount($sectorTotals['potential'] ?? null) }}</strong><small>{{ $sectorCount($sectorSource['coverage']['owned_village_count'] ?? null) }} desa kelolaan UKER</small></div>
            <div class="mss-overview-item"><span><i class="mss-dot is-intra" aria-hidden="true"></i>Intra · Aktif</span><strong>{{ $sectorCount($sectorTotals['intra'] ?? null) }}</strong><small>Debitur aktif BRI</small></div>
            <div class="mss-overview-item"><span><i class="mss-dot is-extra" aria-hidden="true"></i>Ekstra · PH</span><strong>{{ $sectorCount($sectorTotals['extra'] ?? null) }}</strong><small>Debitur BRI hapus buku</small></div>
            <div class="mss-overview-item"><span>Total debitur BRI</span><strong>{{ $sectorCount($sectorTotals['existing'] ?? null) }}</strong><small>Aktif + PH, termasuk OTHER</small></div>
            <div class="mss-overview-item is-ratio"><span>Penetrasi wilayah</span><strong>{{ $sectorRatio($sectorTotals['penetration'] ?? null) }}</strong><small>Dari potensi pelaku usaha</small></div>
        </div>

        <div class="mss-atlas-toolbar">
            <div class="mss-atlas-intro"><h2 id="mss-atlas-heading">Jelajahi potensi per wilayah</h2><p>{{ $districtFilterReady ? 'Pilih kecamatan melalui filter atau polygon wilayah.' : 'Pilih KC terlebih dahulu untuk menjelajahi kecamatannya.' }}</p></div>
            <div class="mss-atlas-filters">
                <div class="mss-filter-field"><label for="mss-atlas-sector">Kelompok usaha</label><div class="mss-custom-select"><select id="mss-atlas-sector" data-mss-custom-select="kelompok usaha">
                    @foreach($sectorSource['comparison_groups'] ?? [] as $group)
                        @if(($group['key'] ?? '') !== 'unclassified')<option value="{{ $group['key'] }}">{{ $group['label'] }}</option>@endif
                    @endforeach
                </select></div></div>
                <div class="mss-district-gate {{ $districtFilterReady ? 'is-ready' : 'is-waiting' }}" data-mss-district-ready="{{ $districtFilterReady ? 'true' : 'false' }}">
                    <div class="mss-district-control" @if(!$districtFilterReady) hidden @endif><label for="mss-atlas-district">Kecamatan</label><div class="mss-custom-select"><select id="mss-atlas-district" data-mss-custom-select="kecamatan" @disabled(!$districtFilterReady)><option value="">Seluruh kecamatan</option>
                    @foreach($sectorDistricts as $district)<option value="{{ $district['code'] }}">{{ $district['name'] }} · {{ $district['regency'] }}</option>@endforeach
                    </select></div></div>
                    <div class="mss-district-waiting" @if($districtFilterReady) hidden @endif><span>Kecamatan</span><p><i class="fas fa-lock" aria-hidden="true"></i>Pilih KC terlebih dahulu</p></div>
                </div>
                <button type="button" class="mss-reset-selection" data-mss-reset aria-label="Reset pilihan kecamatan dan sektor"><i class="fas fa-undo-alt" aria-hidden="true"></i><span>Reset</span></button>
            </div>
        </div>

        <div class="mss-atlas-grid">
            <section class="mss-map-card" aria-label="Peta polygon kecamatan">
                <div class="mss-map-top"><span><i class="fas fa-map-marked-alt" aria-hidden="true"></i><strong data-mss-map-title>{{ $payload['selected_scope_label'] ?? 'Area 6' }}</strong></span>
                    <div class="mss-map-modes" role="group" aria-label="Warna polygon"><button type="button" data-mss-mode="branch" aria-pressed="true">Wilayah cabang</button><button type="button" data-mss-mode="penetration" aria-pressed="false">Penetrasi</button></div>
                </div>
                <div class="mss-map-stage" id="mss-map-stage">
                    <svg id="mss-territory-map" xmlns="http://www.w3.org/2000/svg" role="group" aria-label="{{ $districtFilterReady ? 'Polygon kecamatan. Pilih dengan Enter atau gunakan filter kecamatan.' : 'Polygon Area 6. Pilih polygon untuk membuka KC terlebih dahulu.' }}" hidden></svg>
                    <div class="mss-map-state" data-mss-map-state role="status"><span class="mss-map-loader" aria-hidden="true"></span><strong>Memuat polygon wilayah…</strong><small>Menyiapkan batas kecamatan Area 6</small><button type="button" data-mss-retry hidden>Coba lagi</button></div>
                    <div class="mss-map-tooltip" id="mss-map-tooltip" role="tooltip" hidden></div>
                    <div class="mss-map-tools" aria-label="Kontrol peta"><button type="button" data-mss-zoom="in" aria-label="Perbesar peta" disabled>+</button><button type="button" data-mss-zoom="out" aria-label="Perkecil peta" disabled>−</button><button type="button" data-mss-zoom="fit" aria-label="Sesuaikan peta ke seluruh wilayah" disabled><i class="fas fa-expand-arrows-alt" aria-hidden="true"></i></button></div>
                    <span class="mss-map-caption"><span data-mss-polygon-count>{{ collect($sectorSource['districts'] ?? [])->unique('code')->count() }}</span> kecamatan <span aria-hidden="true">·</span> Batas administratif</span>
                </div>
                <div class="mss-map-legend" data-mss-legend aria-label="Legenda warna polygon"></div>
                <p class="mss-map-footnote"><i class="fas fa-info-circle" aria-hidden="true"></i>Polygon mengikuti batas kecamatan. {{ collect($sectorSource['districts'] ?? [])->contains(static fn ($district) => !empty($district['allocation_estimate'])) ? 'Angka debitur kecamatan adalah estimasi alokasi dari UKER, bukan alamat nasabah.' : 'Potensi dan debitur kecamatan dijumlahkan dari desa kelolaan sesuai sumber.' }}</p>
                <noscript><p class="mss-no-script">Aktifkan JavaScript untuk memilih polygon. Ringkasan dan tabel wilayah tetap tersedia di bawah.</p></noscript>
            </section>

            <aside class="mss-territory-inspector" aria-labelledby="mss-selection-title">
                <div class="mss-inspector-location"><i class="fas fa-map-pin" aria-hidden="true"></i><span data-mss-selection-level>Wilayah analisis</span><span class="mss-estimate-badge" data-mss-estimate hidden>Estimasi</span></div>
                <h3 id="mss-selection-title">{{ $payload['selected_scope_label'] ?? 'Area 6' }}</h3>
                <p class="mss-selection-context" data-mss-selection-context>Seluruh kecamatan pada wilayah terpilih</p>
                <div class="mss-inspector-sector" data-mss-sector-label>Seluruh sektor usaha</div>
                <div class="mss-inspector-ratio"><span>Penetrasi aktif + PH</span><strong data-mss-value="penetration">{{ $sectorRatio($sectorTotals['penetration'] ?? null) }}</strong></div>
                <div class="mss-penetration-track" aria-hidden="true"><i class="is-intra" data-mss-progress="intra"></i><i class="is-extra" data-mss-progress="extra"></i></div>
                <div class="mss-inspector-figures">
                    <div><span>Potensi usaha</span><strong data-mss-value="potential">{{ $sectorCount($sectorTotals['potential'] ?? null) }}</strong></div>
                    <div><span>Total debitur BRI</span><strong data-mss-value="existing">{{ $sectorCount($sectorTotals['existing'] ?? null) }}</strong></div>
                    <div><span><i class="mss-dot is-intra" aria-hidden="true"></i>Intra · Aktif</span><strong data-mss-value="intra">{{ $sectorCount($sectorTotals['intra'] ?? null) }}</strong></div>
                    <div><span><i class="mss-dot is-extra" aria-hidden="true"></i>Ekstra · PH</span><strong data-mss-value="extra">{{ $sectorCount($sectorTotals['extra'] ?? null) }}</strong></div>
                </div>
                <div class="mss-inspector-top"><h4>Potensi sektor terbesar</h4><div data-mss-top-sectors></div></div>
                <p class="mss-inspector-note" data-mss-selection-note>Jumlah debitur mengikuti penjumlahan SSA CRAS pada UKER pemilik desa.</p>
            </aside>
        </div>

        <nav class="mss-branch-grid" aria-label="Pilih cakupan kantor cabang">
            @foreach(['madiun', 'magetan', 'ngawi', 'ponorogo'] as $branchKey)
                @if(isset($scopes[$branchKey]) && $sectorBranches->has($branchKey))
                    @php($branchData = $sectorBranches->get($branchKey))
                    <a class="mss-branch-card" style="--branch-color:{{ $branchColors[$branchKey] }}" href="{{ route('report.dashboard-dana.market-share.sektoral', ['cabang' => $branchKey]) }}" @if($selectedScope === $branchKey) aria-current="page" @endif>
                        <span class="mss-branch-name"><i class="mss-dot" aria-hidden="true"></i>{{ $branchData['label'] }}<i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                        <strong>{{ $sectorCount($branchData['values']['total']['potential'] ?? null) }}<small>potensi usaha</small></strong>
                        <span class="mss-branch-meta">Penetrasi <b>{{ $sectorRatio($branchData['values']['total']['penetration'] ?? null) }}</b></span>
                    </a>
                @endif
            @endforeach
        </nav>

        <section class="mss-sector-ledger" aria-labelledby="mss-customer-title">
            <header class="mss-ledger-head"><div><h2 id="mss-customer-title">Komposisi sektor usaha</h2><p data-mss-ledger-context>{{ $payload['selected_scope_label'] ?? 'Area 6' }} · Seluruh kecamatan</p></div><label class="mss-atlas-search" for="mss-atlas-search"><i class="fas fa-search" aria-hidden="true"></i><input id="mss-atlas-search" type="search" placeholder="Cari kelompok usaha…" autocomplete="off"></label></header>
            <p class="mss-ledger-scroll-hint">Geser tabel ke samping untuk melihat seluruh angka.</p>
            <div class="mss-ledger-scroll" tabindex="0" role="region" aria-label="Tabel potensi dan penetrasi per kelompok usaha" data-abah-no-table-guard>
                <table class="mss-sector-ledger-table" data-abah-no-table-guard>
                    <caption class="sr-only">Potensi dan debitur BRI per sektor pada wilayah yang dipilih</caption>
                    <thead><tr><th scope="col">Kelompok usaha <small>Pilih untuk menyorot peta</small></th><th scope="col">KBLI</th><th scope="col">Potensi usaha</th><th scope="col">Intra · Aktif</th><th scope="col">Ekstra · PH</th><th scope="col">Total BRI</th><th scope="col">Penetrasi</th></tr></thead>
                    <tbody>
                        @foreach($sectorGroups as $group)
                            @php($figures = $sectorSource['totals'][$group['key']] ?? [])
                            <tr data-mss-group="{{ $group['key'] }}" data-mss-search="{{ mb_strtolower($group['label'], 'UTF-8') }}">
                                <th scope="row">@if($group['key'] !== 'unclassified')<button type="button" data-mss-sector="{{ $group['key'] }}" aria-pressed="false">{{ $group['label'] }}<i class="fas fa-location-arrow" aria-hidden="true"></i></button>@else<span>{{ $group['label'] }}</span>@endif</th>
                                <td class="mss-kbli">{{ implode(', ', $group['categories'] ?? []) ?: '—' }}</td>
                                @foreach(['potential', 'intra', 'extra', 'existing'] as $field)<td data-mss-field="{{ $field }}">{{ $group['key'] === 'unclassified' && $field === 'potential' ? '—' : $sectorCount($figures[$field] ?? null) }}</td>@endforeach
                                <td class="mss-ledger-ratio"><strong data-mss-field="penetration" tabindex="0" title="Rasio sesuai kode SSA: {{ $sectorRatio($figures['penetration_raw'] ?? null) }}">{{ $group['key'] === 'unclassified' ? '—' : $sectorRatio($figures['penetration'] ?? null) }}</strong><span aria-hidden="true"><i data-mss-row-progress></i></span><span class="sr-only" data-mss-raw-ratio></span></td>
                            </tr>
                        @endforeach
                        <tr class="mss-ledger-empty" data-mss-empty hidden><td colspan="7">Kelompok usaha tidak ditemukan. Coba kata lain.</td></tr>
                    </tbody>
                    <tfoot><tr><th scope="row" colspan="2">Total termasuk OTHER</th>@foreach(['potential', 'intra', 'extra', 'existing', 'penetration'] as $field)<td data-mss-total="{{ $field }}">{{ $field === 'penetration' ? $sectorRatio($sectorTotals[$field] ?? null) : $sectorCount($sectorTotals[$field] ?? null) }}</td>@endforeach</tr></tfoot>
                </table>
            </div>
            <p class="mss-ledger-note">Penetrasi memakai hasil normalisasi sektor. Rasio sesuai kode SSA tersedia saat persentase difokuskan atau disorot. OTHER tetap masuk total tanpa rasio tersendiri.</p>
        </section>

        <details class="mss-source-disclosure"><summary><span><i class="fas fa-layer-group" aria-hidden="true"></i>Sumber, cakupan, dan cara membaca data</span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
            <div><p><strong>Posisi debitur:</strong> SSA CRAS {{ $sectorSource['period_label'] ?? '' }}. {{ $sectorSource['note'] ?? '' }}</p><p>{{ $sectorSource['district_note'] ?? '' }}</p><p>{{ $sectorSource['normalization_note'] ?? '' }} {{ $sectorSource['extra_sector_note'] ?? '' }}</p><p>{{ $sectorSource['unit_potential_note'] ?? '' }}</p>
                @foreach($sectorSource['units'] ?? [] as $unit)@if(!empty($unit['potential_partial']))<p><strong>{{ $unit['name'] }}:</strong> {{ $sectorCount($unit['matched_village_count'] ?? null) }} dari {{ $sectorCount($unit['owned_village_count'] ?? null) }} desa memiliki potensi. @foreach($unit['missing_population_villages'] ?? [] as $village)Belum tersedia: {{ $village['name'] }}, {{ $village['district'] }}. @endforeach</p>@endif @endforeach
                @if(!empty($sectorSource['unmapped_units']))<p><strong>{{ count($sectorSource['unmapped_units']) }} UKER belum memiliki acuan desa</strong> dan berada di luar rasio penetrasi:</p><ul>@foreach($sectorSource['unmapped_units'] as $unit)<li>{{ $unit['name'] }} — {{ $sectorCount($unit['values']['total']['intra'] ?? null) }} aktif, {{ $sectorCount($unit['values']['total']['extra'] ?? null) }} PH.</li>@endforeach</ul>@endif
                <div class="mss-source-links"><a href="{{ $sectorSource['source']['url'] ?? '#' }}" target="_blank" rel="noopener">Sumber potensi usaha <i class="fas fa-external-link-alt" aria-hidden="true"></i></a><a href="{{ $sectorSource['ownership_source']['url'] ?? '#' }}" target="_blank" rel="noopener">Desa kelolaan UKER <i class="fas fa-external-link-alt" aria-hidden="true"></i></a><a href="https://geoservices.big.go.id/rbi/rest/services/BATASWILAYAH/Administrasi_AR_Kecamatan_10K/MapServer/0" target="_blank" rel="noopener">Batas kecamatan BIG <i class="fas fa-external-link-alt" aria-hidden="true"></i></a></div>
            </div>
        </details>
    @else
        <div class="mss-atlas-unavailable" role="status"><i class="fas fa-map-marked-alt" aria-hidden="true"></i><h2 id="mss-atlas-heading">Data wilayah belum tersedia</h2><p>{{ $sectorSource['message'] ?? 'Sumber potensi usaha atau SSA CRAS belum dapat dibaca. Muat ulang halaman untuk mencoba kembali.' }}</p><a href="{{ request()->fullUrl() }}">Muat ulang halaman</a></div>
    @endif
    <p class="sr-only" data-mss-announcement aria-live="polite" aria-atomic="true"></p>
</section>
<script type="application/json" id="mss-atlas-data">@json($atlasPayload)</script>
