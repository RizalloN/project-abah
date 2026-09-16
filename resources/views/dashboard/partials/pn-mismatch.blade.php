@php
    $mismatch = (array) ($pnMismatch ?? []);
    $segment = (string) ($pnSegment ?? '');
    $branches = (array) data_get($mismatch, 'branches', []);
@endphp
<section class="pn-mismatch" data-pn-mismatch-section data-segment="{{ $segment }}" data-period="{{ data_get($mismatch, 'period', '') }}" data-url="{{ url('/dashboard/pn-mismatch-nominatives') }}" aria-labelledby="pn-mismatch-{{ $segment }}-title">
    <header class="pn-mismatch__head">
        <div><span class="pn-mismatch__eyebrow">KUALITAS DATA PERSONEL &middot; {{ data_get($mismatch, 'period', '-') }}</span>
            <h3 id="pn-mismatch-{{ $segment }}-title">PN Tidak Sesuai BRIHC</h3>
            <p>Nama pengelola atau PN pada nominatif berbeda dari roster BRIHC saat ini. Klik dua kali atau ketuk jumlah rekening untuk melihat nominatifnya.</p>
        </div>
        <strong>{{ number_format((int) data_get($mismatch, 'total', 0), 0, ',', '.') }} <small>rekening</small></strong>
    </header>
    @if(!data_get($mismatch, 'available', false))
        <p class="pn-mismatch__empty">Perbandingan PN belum tersedia untuk posisi ini atau referensi BRIHC belum tersedia.</p>
    @else
        <div class="pn-mismatch__scroll" tabindex="0" aria-label="Tabel PN tidak sesuai BRIHC">
            <table><thead><tr><th scope="col">Branch Office</th><th scope="col">Keterangan</th><th scope="col">Nominatif</th></tr></thead>
            <tbody>
                @forelse($branches as $branch)
                    <tr><th scope="row">{{ data_get($branch, 'branch', '-') }}</th><td>PN tidak sesuai</td>
                        <td><button type="button" data-pn-mismatch-open data-branch="{{ data_get($branch, 'branch', '') }}" title="Klik dua kali atau tekan Enter untuk melihat nominatif">{{ number_format((int) data_get($branch, 'count', 0), 0, ',', '.') }} rekening <span aria-hidden="true">↗</span></button></td></tr>
                @empty
                    <tr><td colspan="3">Semua nama dan PN yang terisi sesuai dengan BRIHC.</td></tr>
                @endforelse
            </tbody></table>
        </div>
    @endif
</section>
<style>
.pn-mismatch { margin:1.25rem 0; border:1px solid #d8e4f1; border-radius:16px; background:#fff; box-shadow:0 6px 22px rgba(19,52,89,.06); overflow:hidden; }
.pn-mismatch__head { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1.1rem 1.25rem; background:#f5f9ff; }
.pn-mismatch__head h3 { margin:.25rem 0; color:#18385d; font-size:1.08rem; }
.pn-mismatch__head p { margin:0; color:#536a82; font-size:.81rem; line-height:1.5; }
.pn-mismatch__head > strong { flex:none; color:#97461a; font-size:1.3rem; }
.pn-mismatch__head > strong small { font-size:.72rem; }
.pn-mismatch__eyebrow { color:#235b9c; font-size:.69rem; font-weight:800; letter-spacing:.06em; }
.pn-mismatch__scroll { overflow:auto; }
.pn-mismatch table { width:100%; min-width:520px; border-collapse:collapse; text-align:left; }
.pn-mismatch th,.pn-mismatch td { padding:.68rem 1.15rem; border-top:1px solid #e5edf5; font-size:.82rem; }
.pn-mismatch thead th { color:#405a76; background:#fafcff; }
.pn-mismatch button { min-height:40px; padding:.3rem .75rem; border:1px solid #b9d6f4; border-radius:8px; background:#eaf4ff; color:#0757a4; font-weight:750; cursor:pointer; }
.pn-mismatch button:focus-visible { outline:3px solid #307fe2; outline-offset:2px; }
.pn-mismatch__empty { padding:1rem 1.25rem; color:#526b87; }
.pn-mismatch-modal { position:fixed; inset:0; z-index:11000; display:grid; place-items:center; padding:1rem; background:rgba(10,31,55,.68); }
.pn-mismatch-modal[hidden] { display:none; }
.pn-mismatch-modal__dialog { width:min(100%,1000px); max-height:90vh; display:flex; flex-direction:column; border-radius:16px; background:white; overflow:hidden; }
.pn-mismatch-modal__head,.pn-mismatch-modal__foot { display:flex; align-items:center; justify-content:space-between; gap:.65rem; padding:1rem 1.2rem; }
.pn-mismatch-modal__head h3 { margin:0; font-size:1.05rem; }
.pn-mismatch-modal__head button,.pn-mismatch-modal__foot button { min-height:40px; padding:.35rem .8rem; border:1px solid #b9d6f4; border-radius:8px; background:#eaf4ff; color:#0757a4; cursor:pointer; }
.pn-mismatch-modal__scroll { overflow:auto; flex:1; }
.pn-mismatch-modal__dialog table { min-width:870px; }
.pn-mismatch-modal__foot { border-top:1px solid #e5edf5; }
@media(max-width:600px) { .pn-mismatch__head { align-items:flex-start; flex-direction:column; } }
</style>
<div class="pn-mismatch-modal" data-pn-mismatch-modal data-pn-modal-segment="{{ $segment }}" hidden>
    <div class="pn-mismatch-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pn-mismatch-modal-title-{{ $segment }}">
        <header class="pn-mismatch-modal__head"><h3 id="pn-mismatch-modal-title-{{ $segment }}" data-pn-mismatch-title>Nominatif PN Tidak Sesuai</h3><button type="button" data-pn-mismatch-close aria-label="Tutup nominatif">Tutup</button></header>
        <div class="pn-mismatch-modal__scroll pn-mismatch" style="margin:0;border:0;border-radius:0;box-shadow:none" tabindex="0"><table><thead><tr><th>Rekening</th><th>Debitur</th><th>Unit</th><th>Produk</th><th>PN</th><th>Nama di nominatif</th><th>Nama BRIHC</th></tr></thead><tbody data-pn-mismatch-rows></tbody></table></div>
        <footer class="pn-mismatch-modal__foot"><span data-pn-mismatch-count></span><div><button type="button" data-pn-mismatch-page="prev">Sebelumnya</button> <button type="button" data-pn-mismatch-page="next">Berikutnya</button></div></footer>
    </div>
</div>
