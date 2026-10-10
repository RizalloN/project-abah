<div class="micro-nett-sheets micro-nett-mantri">
    @php
        $hasGross = data_get($mantriPerformance, 'total.realization.amount') !== null;
        $hasPersonAverage = data_get($mantriPerformance, 'total.net_disbursement.average_per_mantri_hke') !== null;
        $hasUnknownStatus = data_get($mantriPerformance, 'total.headcount.unknown', 0) > 0;
        $hasBoCode = collect(data_get($mantriPerformance, 'sheet_rows.bo', data_get($mantriPerformance, 'rows', [])))
            ->contains(fn ($row) => !in_array(data_get($row, 'branch_code'), [null, '', '-'], true));
    @endphp
    <fieldset class="micro-nett-sheet-nav">
        <legend>Pilih sheet Mantri</legend>
        @foreach(['bo' => 'BO', 'mbm' => 'MBM', 'unit' => 'UNIT'] as $sheet => $label)
            <label><input type="radio" name="micro-mantri-sheet" value="{{ $sheet }}" aria-controls="mantri-nett-{{ $sheet }}" @checked($sheet === 'bo')><span>{{ $label }}</span></label>
        @endforeach
    </fieldset>
    <div class="micro-nett-sheet-guide">
        <div class="micro-nett-sheet-guide__info">
            <span class="micro-guide-desc micro-nett-guide-bo">Ringkasan per Branch Office.</span>
            <span class="micro-guide-desc micro-nett-guide-mbm">Rincian penempatan Mantri per MBM.</span>
            <span class="micro-guide-desc micro-nett-guide-unit">Rincian seluruh unit kerja.</span>
            <span class="micro-nett-guide-hint"><i class="fas fa-arrows-alt-h mr-1 text-primary"></i>Geser tabel horizontal untuk melihat seluruh kategori</span>
        </div>
        <div class="micro-nett-scroll-actions" role="group" aria-label="Navigasi geser tabel">
            <button type="button" class="micro-nett-scroll-btn" data-scroll-dir="left" title="Geser tabel ke kiri" aria-label="Geser ke kiri"><i class="fas fa-chevron-left"></i></button>
            <button type="button" class="micro-nett-scroll-btn" data-scroll-dir="right" title="Geser tabel ke kanan" aria-label="Geser ke kanan"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
    @foreach(['bo' => 'BO', 'mbm' => 'MBM', 'unit' => 'UNIT'] as $sheet => $label)
        @php
            $sheetRows = data_get($mantriPerformance, 'sheet_rows.'.$sheet, $sheet === 'bo' ? data_get($mantriPerformance, 'rows', []) : []);
            $identityColumns = $sheet === 'unit' ? 5 : ($sheet === 'bo' && !$hasBoCode ? 2 : 3);
        @endphp
        <section id="mantri-nett-{{ $sheet }}" class="micro-nett-sheet-panel" data-sheet="{{ $sheet }}" aria-label="Sheet Mantri {{ $label }}">
            @if(count($sheetRows))
                <div class="micro-nett-wrap" tabindex="0" role="region" aria-label="Nett disbursement Mantri {{ $label }}, tabel dapat digeser">
                    <table class="micro-nett-table micro-mantri-table--summary {{ $sheet === 'bo' && $hasBoCode ? 'has-bo-code' : '' }}">
                        <caption>Monitoring Nett Disbursement Mantri — {{ $label }}</caption>
                        <thead>
                            <tr>
                                <th rowspan="3" scope="col" class="col-freeze-1">No.</th>
                                @if($sheet === 'bo')
                                    @if($hasBoCode)<th rowspan="3" scope="col" class="col-freeze-2">Kode BO</th>@endif
                                    <th rowspan="3" scope="col" class="{{ $hasBoCode ? 'col-freeze-3' : 'col-freeze-2' }} col-freeze-last">Branch Office</th>
                                @else
                                    <th rowspan="3" scope="col" class="col-freeze-2">Branch Office</th>
                                    @if($sheet === 'unit')
                                        <th rowspan="3" scope="col" class="col-freeze-3">Kode Unit</th>
                                        <th rowspan="3" scope="col" class="col-freeze-4">Unit Kerja</th>
                                        <th rowspan="3" scope="col" class="col-freeze-5 col-freeze-last">MBM</th>
                                    @else
                                        <th rowspan="3" scope="col" class="col-freeze-3 col-freeze-last">MBM</th>
                                    @endif
                                @endif
                                <th rowspan="3" scope="col">Mantri PT<br>Non Briguna</th><th rowspan="3" scope="col">Mantri<br>Kontrak</th><th rowspan="3" scope="col">Mantri<br>Briguna</th>
                                @if($hasUnknownStatus)<th rowspan="3" scope="col">Status belum<br>terpetakan</th>@endif
                                @if($hasGross)<th colspan="2" rowspan="2" scope="colgroup">Realisasi bulan berjalan</th>@endif
                                <th colspan="2" rowspan="2" scope="colgroup">Nett Disbursement</th>
                                @foreach(['pt' => 'Mantri PT Only', 'contract' => 'Mantri Kontrak'] as $tierKey => $tierTitle)
                                    <th colspan="{{ count(data_get($mantriPerformance, 'total.tiers.'.$tierKey.'.buckets', [])) * 2 }}" scope="colgroup">Nett Disbursement — {{ $tierTitle }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach(['pt', 'contract'] as $tierKey)
                                    @foreach(data_get($mantriPerformance, 'total.tiers.'.$tierKey.'.buckets', []) as $bucket)
                                        <th colspan="2" scope="colgroup" class="tone-{{ $bucket['key'] }}">{{ $bucket['label'] }}@if(!empty($bucket['range']))<span class="micro-rm-kur-range">{{ $bucket['range'] }}</span>@endif</th>
                                    @endforeach
                                @endforeach
                            </tr>
                            <tr>
                                @if($hasGross)<th scope="col">Rp Juta</th><th scope="col">Ratas / HKE</th>@endif
                                <th scope="col">Rp Juta</th><th scope="col">{{ $hasPersonAverage ? 'Ratas / Mantri / HKE' : 'Ratas / HKE' }}</th>
                                @foreach(['pt', 'contract'] as $tierKey)
                                    @foreach(data_get($mantriPerformance, 'total.tiers.'.$tierKey.'.buckets', []) as $bucket)
                                        <th scope="col" class="tone-{{ $bucket['key'] }}">Mantri</th><th scope="col" class="tone-{{ $bucket['key'] }}">%</th>
                                    @endforeach
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sheetRows as $row)
                                <tr>
                                    <td class="col-freeze-1">{{ $loop->iteration }}</td>
                                    @if($sheet === 'bo')
                                        @if($hasBoCode)<td class="col-freeze-2">{{ $row['branch_code'] }}</td>@endif
                                        <th scope="row" class="{{ $hasBoCode ? 'col-freeze-3' : 'col-freeze-2' }} col-freeze-last">{{ $row['branch'] }}</th>
                                    @else
                                        <td class="col-freeze-2">{{ $row['branch'] }}</td>
                                        @if($sheet === 'unit')
                                            <td class="col-freeze-3">{{ $row['branch_code'] }}</td>
                                            <th scope="row" class="col-freeze-4">{{ $row['unit'] }}</th>
                                            <td class="col-freeze-5 col-freeze-last">{{ $row['mbm'] }}</td>
                                        @else
                                            <th scope="row" class="col-freeze-3 col-freeze-last">{{ $row['mbm'] }}</th>
                                        @endif
                                    @endif
                                    @include('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-mantri-nett-values', ['nettRow' => $row])
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th colspan="{{ $identityColumns }}" scope="row" class="col-freeze-total col-freeze-last">Total {{ data_get($mantriPerformance, 'total.branch', 'Area 6') }}</th>@include('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-mantri-nett-values', ['nettRow' => $mantriPerformance['total']])</tr></tfoot>
                    </table>
                </div>
            @else
                <p role="status">Rincian {{ $label }} belum tersedia untuk posisi ini. Ringkasan BO tetap dapat dibuka.</p>
            @endif
        </section>
    @endforeach
    <p class="micro-nett-help">Nett mengikuti perhitungan KPI dan penempatan roster aktif BRIHC. Ratas / HKE adalah nett dibagi hari kerja efektif. Unit tanpa pemetaan MBM ditandai “Belum dipetakan”.</p>
</div>
