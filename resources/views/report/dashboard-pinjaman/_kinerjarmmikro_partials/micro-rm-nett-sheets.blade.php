@php
    $rmTiers = (array) data_get($rmKurProductivity, 'distribution.tiers', []);
    $rmSections = (array) data_get($rmKurProductivity, 'sections', []);
    $rmDistributionReady = count($rmSections) > 0 && count($rmTiers) > 0;
    $rmPeople = collect(data_get($rmKurProductivity, 'rows', []));
    $rmOfficeKey = static fn ($value): string => strtoupper(trim((string) $value));
    $nettDrill = (object) ['people' => [], 'keys' => [], 'lists' => []];
    $nettDrillColumns = [
        ['key' => 'pn', 'label' => 'PN'],
        ['key' => 'nama', 'label' => 'Nama RM KUR Kecil Mikro'],
        ['key' => 'cabang', 'label' => 'Branch Office'],
        ['key' => 'unit', 'label' => 'Unit Kerja'],
        ['key' => 'kategori', 'label' => 'Kategori Nett'],
        ['key' => 'rekening', 'label' => 'Rekening', 'align' => 'right', 'format' => 'integer'],
        ['key' => 'nett', 'label' => 'Nett MTD (Rp Juta)', 'align' => 'right', 'format' => 'juta'],
    ];
    // Registers one RM list for the drilldown modal and returns the cell attributes that point to it.
    $nettDrillAttr = function (iterable $people, string $title, string $scope, bool $ascending = false) use ($nettDrill, $rmTiers): string {
        $ids = collect($people)
            ->map(fn ($person) => [
                'pn' => (string) data_get($person, 'pn', '-'),
                'nama' => (string) data_get($person, 'nama', '-'),
                'cabang' => (string) data_get($person, 'cabang', '-'),
                'unit' => (string) data_get($person, 'unit', '-'),
                'kategori' => (string) data_get($rmTiers, data_get($person, 'tier').'.label', '-'),
                'rekening' => (int) data_get($person, 'realisasi_deb', 0),
                'nett' => round((float) data_get($person, 'realisasi_os', 0)),
            ])
            ->sortBy([['nett', $ascending ? 'asc' : 'desc'], ['nama', 'asc']])
            ->map(function (array $row) use ($nettDrill): int {
                $key = json_encode($row);
                if (! isset($nettDrill->keys[$key])) {
                    $nettDrill->keys[$key] = count($nettDrill->people);
                    $nettDrill->people[] = $row;
                }

                return $nettDrill->keys[$key];
            })
            ->values()
            ->all();
        $nettDrill->lists[] = ['title' => $title, 'scope' => $scope, 'ids' => $ids];

        return 'data-nett-drill="'.(count($nettDrill->lists) - 1).'" tabindex="0" title="Klik 2x untuk melihat daftar RM"';
    };
@endphp
<article class="micro-rm-kur-productivity micro-nett-sheets micro-nett-rm" data-micro-rm-kur-productivity aria-labelledby="micro-rm-kur-title">
    <h4 id="micro-rm-kur-title" class="micro-nett-visually-hidden">Persebaran dan rincian RM KUR Kecil Mikro</h4>
    <fieldset class="micro-nett-sheet-nav">
        <legend>Pilih sheet RM</legend>
        @foreach(['bo' => 'BO', 'detail' => 'Per RM'] as $sheet => $label)
            <label><input type="radio" name="micro-rm-sheet" value="{{ $sheet }}" aria-controls="rm-nett-{{ $sheet }}" @checked($sheet === 'bo')><span>{{ $label }}</span></label>
        @endforeach
    </fieldset>
    <div class="micro-nett-sheet-guide">
        <div class="micro-nett-sheet-guide__info">
            <span class="micro-guide-desc micro-nett-guide-bo">BO menampilkan KC dan KCP beserta subtotalnya.</span>
            <span class="micro-guide-desc micro-nett-guide-detail">Per RM menampilkan nama dan PN sesuai referensi SDM.</span>
            <span class="micro-nett-guide-hint"><i class="fas fa-arrows-alt-h mr-1 text-primary"></i>Geser tabel horizontal untuk melihat seluruh kolom</span>
        </div>
        <div class="micro-nett-scroll-actions" role="group" aria-label="Navigasi geser tabel">
            <button type="button" class="micro-nett-scroll-btn" data-scroll-dir="left" title="Geser tabel ke kiri" aria-label="Geser ke kiri"><i class="fas fa-chevron-left"></i></button>
            <button type="button" class="micro-nett-scroll-btn" data-scroll-dir="right" title="Geser tabel ke kanan" aria-label="Geser ke kanan"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
    <section id="rm-nett-bo" class="micro-nett-sheet-panel" data-sheet="bo" aria-label="Sheet RM BO">
        @if($rmDistributionReady)
            <div class="micro-nett-wrap" tabindex="0" role="region" aria-label="Persebaran RM BO dan KCP, tabel dapat digeser">
                <table class="micro-nett-table micro-rm-kur-distribution-table">
                    <caption>Monitoring Nett Disbursement RM — BO</caption>
                    <thead>
                        <tr>
                            <th rowspan="3" scope="col" class="col-freeze-1">No.</th>
                            <th rowspan="3" scope="col" class="col-freeze-2">Branch Office</th>
                            <th rowspan="3" scope="col" class="col-freeze-3 col-freeze-last">Sub Branch Office</th>
                            <th rowspan="3" scope="col">Jumlah RM</th>
                            <th rowspan="2" colspan="2" scope="colgroup">Nett Disbursement MTD</th><th colspan="11" scope="colgroup">Sebaran Nett Disbursement</th>
                        </tr>
                        <tr>
                            @foreach($rmTiers as $key => $tier)
                                <th colspan="2" scope="colgroup" class="tone-{{ $key }}">{{ $tier['label'] }}<span class="micro-rm-kur-range">{{ $tier['range'] ?? '' }}</span></th>
                                @if($key === 'low')<th rowspan="2" scope="col" class="tone-acceleration">Need<br>Acceleration<br>(%)</th>@endif
                            @endforeach
                        </tr>
                        <tr><th scope="col">Rekening</th><th scope="col">Rp Juta</th>@foreach($rmTiers as $key => $tier)<th scope="col" class="tone-{{ $key }}">Org</th><th scope="col" class="tone-{{ $key }}">%</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach(['kc' => 'KC', 'kcp' => 'KCP'] as $type => $label)
                            @foreach(data_get($rmSections, $type.'.rows', []) as $group)
                                @php
                                    $nettDrillPeople = $rmPeople->filter(fn ($person) => ($person['office_type'] ?? 'kc') === $type
                                        && $rmOfficeKey($person['cabang'] ?? '') === $rmOfficeKey($group['cabang'] ?? '')
                                        && ($type === 'kc' || $rmOfficeKey($person['unit'] ?? '') === $rmOfficeKey($group['unit'] ?? '')));
                                    $nettDrillScope = $type === 'kcp' ? $group['cabang'].' • '.$group['unit'] : $group['cabang'];
                                @endphp
                                <tr data-office-type="{{ $type }}">
                                    <td class="col-freeze-1">{{ $loop->iteration }}</td>
                                    <th scope="row" class="col-freeze-2">{{ $group['cabang'] }}</th>
                                    <td class="col-freeze-3 col-freeze-last">{{ $type === 'kcp' ? $group['unit'] : '—' }}</td>
                                    @include('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-rm-nett-values', ['rmMetrics' => $group])
                                </tr>
                            @endforeach
                            <tr class="micro-nett-subtotal" data-subtotal="{{ $type }}"><th colspan="3" scope="row" class="col-freeze-total col-freeze-last">Total {{ $label }}</th>@include('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-rm-nett-values', ['rmMetrics' => data_get($rmSections, $type.'.total', []), 'nettDrillPeople' => $rmPeople->where('office_type', $type), 'nettDrillScope' => 'Total '.$label])</tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><th colspan="3" scope="row" class="col-freeze-total col-freeze-last">Total RM {{ data_get($rmKurProductivity, 'scope_label', 'Area 6') }}</th>@include('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-rm-nett-values', ['rmMetrics' => data_get($rmKurProductivity, 'assigned_total', []), 'nettDrillPeople' => $rmPeople, 'nettDrillScope' => 'Total RM '.data_get($rmKurProductivity, 'scope_label', 'Area 6')])</tr></tfoot>
                </table>
            </div>
        @else
            <p role="status">Persebaran belum tersedia untuk posisi ini. Data yang belum tersedia tidak dianggap sebagai belum realisasi.</p>
        @endif
    </section>
    <section id="rm-nett-detail" class="micro-nett-sheet-panel" data-sheet="detail" aria-label="Sheet Per RM">
        @if($rmDistributionReady)
            <div class="micro-nett-wrap" tabindex="0" role="region" aria-label="Rincian RM, tabel dapat digeser">
                <table class="micro-nett-table micro-rm-kur-table">
                    <caption>Monitoring Nett Disbursement — Per RM</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="col-freeze-1">No.</th>
                            <th scope="col" class="col-freeze-2">Branch Office</th>
                            <th scope="col" class="col-freeze-3">Unit Kerja</th>
                            <th scope="col" class="col-freeze-4">PN</th>
                            <th scope="col" class="col-freeze-5 col-freeze-last">Nama RM KUR Kecil Mikro</th>
                            <th scope="col">Rekening</th>
                            <th scope="col">Nett MTD<br>(Rp Juta)</th>
                            <th scope="col">Rata-rata / Rekening<br>(Rp Juta)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(data_get($rmKurProductivity, 'rows', []) as $row)
                            <tr>
                                <td class="col-freeze-1">{{ $loop->iteration }}</td>
                                <td class="col-freeze-2">{{ $row['cabang'] }}</td>
                                <td class="col-freeze-3">{{ $row['unit'] }}</td>
                                <td class="col-freeze-4">{{ $row['pn'] }}</td>
                                <th scope="row" class="micro-rm-kur-person col-freeze-5 col-freeze-last">{{ $row['nama'] }}</th>
                                <td>{{ $formatInteger($row['realisasi_deb']) }}</td>
                                <td>{{ $formatJuta($row['realisasi_os']) }}</td>
                                <td>{{ $formatJuta($row['average_per_debtor']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><th colspan="5" scope="row" class="col-freeze-total col-freeze-last">Total RM teridentifikasi</th><td>{{ $formatInteger(data_get($rmKurProductivity, 'assigned_total.realisasi_deb', 0)) }}</td><td>{{ $formatJuta(data_get($rmKurProductivity, 'assigned_total.realisasi_os', 0)) }}</td><td>{{ $formatJuta(data_get($rmKurProductivity, 'assigned_total.realisasi_deb', 0) > 0 ? data_get($rmKurProductivity, 'assigned_total.realisasi_os', 0) / data_get($rmKurProductivity, 'assigned_total.realisasi_deb') : 0) }}</td></tr></tfoot>
                </table>
            </div>
        @endif
    </section>
    @if(data_get($rmKurProductivity, 'unassigned_deb', 0) || data_get($rmKurProductivity, 'unassigned_amount', 0) != 0)
        <p role="note">{{ $formatInteger(data_get($rmKurProductivity, 'unassigned_deb', 0)) }} rekening dengan nett {{ $formatAmount(data_get($rmKurProductivity, 'unassigned_amount', 0)) }} belum terhubung ke PN RM dalam referensi SDM. Nilainya tetap masuk total sumber, tetapi tidak dihitung sebagai orang atau dibagikan ke RM. Persentase persebaran hanya mencakup RM teridentifikasi.</p>
    @endif
    @if(count(data_get($rmKurProductivity, 'reference_sources', [])))
        <details class="micro-nett-nominative"><summary>Referensi nama dan penempatan RM</summary><ul>@foreach($rmKurProductivity['reference_sources'] as $source)<li>{{ $source['branch'] }} — posisi {{ $source['position_date'] }} · {{ $source['file'] }}</li>@endforeach</ul></details>
    @endif
    @if($rmDistributionReady)
        @include('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-nett-drilldown')
    @endif
</article>
