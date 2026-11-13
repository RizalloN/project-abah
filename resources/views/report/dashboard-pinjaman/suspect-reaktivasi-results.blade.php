@php
    $dateLabel = fn ($value) => $value ? \Carbon\Carbon::parse($value)->translatedFormat('d M Y') : 'Belum tersedia';
    $amount = fn ($value) => number_format((float) $value, 2, ',', '.');
    $countLabel = fn ($value) => number_format((int) $value, 0, ',', '.');
    $maxBranch = max(1, (int) $distribution->max('accounts'));
@endphp
<div class="sr-result-context"><span><i class="fas fa-sliders-h" aria-hidden="true"></i> Hasil monitoring</span><div><span>{{ $dateLabel($period) }}</span><span>{{ $branch ?: 'Semua cabang Area 6' }}</span>@if($unit)<span>{{ $unit }}</span>@endif<span>{{ $frequency !== null ? $frequency.' kali reaktivasi' : 'Semua frekuensi' }}</span></div></div>
    <section class="sr-metrics" aria-label="Ringkasan sesuai filter">
        <div class="sr-metric"><span>Rekening suspect</span><strong>{{ $countLabel($summary->accounts) }}</strong><small>Rekening pada posisi terpilih</small></div>
        <div class="sr-metric"><span>Total outstanding</span><strong><small>Rp</small> {{ $amount($summary->outstanding) }}</strong><small>Nominal sesuai laporan sumber</small></div>
        <div class="sr-metric"><span>Rekening restruk</span><strong>{{ $countLabel($summary->restruk) }}</strong><small>Berdasarkan flag restruk sumber</small></div>
    </section>
    @if($summary->accounts > 0)
    <section class="sr-analysis" aria-label="Distribusi rekening suspect">
        <div class="sr-panel"><h2>Sebaran rekening</h2><p class="sr-note">Jumlah rekening per cabang sesuai filter aktif</p>@foreach($distribution as $item)<div class="sr-branch"><span>{{ $item->cabang }}</span><div class="sr-track" aria-hidden="true"><div class="sr-fill" style="width:{{ round($item->accounts / $maxBranch * 100, 2) }}%"></div></div><strong>{{ $countLabel($item->accounts) }}</strong></div>@endforeach</div>
        <div class="sr-panel"><h2>Frekuensi reaktivasi</h2><p class="sr-note">Kategori keterangan dari dokumen sumber</p>@foreach($categories as $item)<div class="sr-category"><span>{{ $item->keterangan }}</span><strong>{{ $countLabel($item->accounts) }}</strong></div>@endforeach</div>
    </section>
    @endif
    <section class="sr-table-card" aria-labelledby="sr-table-title">
        <header class="sr-table-heading"><div><h2 id="sr-table-title">Nominatif rekening</h2><p>{{ $unit ?: ($branch ?: 'Seluruh cabang Area 6') }}</p></div><span class="sr-badge">{{ $countLabel($summary->accounts) }} rekening</span></header>
        @if($rows && $rows->count())
        <div class="sr-table-wrap" tabindex="0" role="region" aria-label="Nominatif suspect reaktivasi, geser untuk melihat seluruh kolom">
            <table class="sr-table"><thead><tr><th scope="col">No.</th><th scope="col">Cabang / uker</th><th scope="col">Debitur / rekening</th><th scope="col" class="sr-num">Outstanding (Rp)</th><th scope="col">Keterangan</th><th scope="col">Restruk</th><th scope="col">AO pengelola</th><th scope="col">Riwayat sumber</th></tr></thead><tbody>
            @foreach($rows as $row)
            @php
                $history = [];
                for ($offset = 11; $offset >= 0; $offset--) {
                    $key = $offset ? 'bulan_laporan_'.$offset : 'bulan_laporan';
                    $history[] = ['label' => $offset ? 'Bulan laporan -'.$offset : 'Bulan laporan', 'date' => $row->{$key} ? $dateLabel($row->{$key}) : 'Tidak tersedia'];
                }
            @endphp
            <tr><td>{{ $rows->firstItem() + $loop->index }}</td><td><strong>{{ $row->nama_uker }}</strong><span class="sr-sub">{{ $row->cabang }} · {{ $row->kode_uker }}</span></td><td><strong>{{ $row->nama_debitur }}</strong><span class="sr-sub">{{ $row->norek }}<br>CIF {{ $row->cifno }} · {{ $row->deskripsi }}</span></td><td class="sr-num">{{ $amount($row->outstanding) }}</td><td><span class="sr-badge">{{ $row->keterangan }}</span></td><td><span class="sr-badge {{ $row->flag_restruk === 'Restruk' ? 'sr-restruk' : 'sr-plain' }}">{{ $row->flag_restruk }}</span></td><td><span class="sr-sub">{{ $row->ao_pengelola ?: 'Tidak tersedia' }}</span></td><td><button type="button" class="sr-history" data-history="{{ json_encode($history) }}" data-name="{{ $row->nama_debitur }}" data-account="{{ $row->norek }}" aria-haspopup="dialog">Lihat 12 bulan</button></td></tr>
            @endforeach
            </tbody></table>
        </div>
        <footer class="sr-footer"><span>Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $countLabel($rows->total()) }} rekening</span>
            @if($rows->hasPages())
            <nav aria-label="Halaman nominatif" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                @if($rows->previousPageUrl())<a class="sr-history" href="{{ $rows->previousPageUrl() }}" aria-label="Halaman sebelumnya">‹</a>@endif
                @foreach($rows->getUrlRange(max(1, $rows->currentPage()-2), min($rows->lastPage(), $rows->currentPage()+2)) as $pageNumber => $pageUrl)
                    @if($pageNumber === $rows->currentPage())<span class="sr-badge" aria-current="page">{{ $pageNumber }}</span>@else<a class="sr-history" href="{{ $pageUrl }}" aria-label="Halaman {{ $pageNumber }}">{{ $pageNumber }}</a>@endif
                @endforeach
                @if($rows->nextPageUrl())<a class="sr-history" href="{{ $rows->nextPageUrl() }}" aria-label="Halaman berikutnya">›</a>@endif
            </nav>
            @endif
        </footer>
        @else
        <div class="sr-empty"><i class="fas fa-search-dollar" aria-hidden="true"></i><h2>Belum ada rekening untuk ditampilkan</h2><p>{{ $periods->isEmpty() ? 'Data Suspect Reaktivasi belum tersedia. Import workbook laporan untuk menampilkan rekening Area 6 di halaman ini.' : 'Tidak ada rekening yang sesuai dengan filter aktif. Ubah cabang, uker, atau jumlah reaktivasi untuk melihat rekening lainnya.' }}</p></div>
        @endif
    </section>