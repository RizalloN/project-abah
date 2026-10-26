{{--
    Normalisasi crosstab Excel: merangkum apa yang diubah sebelum preview
    (header yang ditemukan, merge yang di-unmerge, sel kosong yang diisi dari atas,
    kolom tanpa judul yang dinamai, baris Total yang dilewati).
--}}
@php
    $crosstabNormalization = \App\Services\Import\Crosstab\CrosstabNormalizationNotice::forPath($filePath ?? $path ?? null);
@endphp
@if(!empty($crosstabNormalization['notices']))
<section class="ctn" aria-labelledby="ctnTitle">
    <span class="ctn__glyph" aria-hidden="true"><i class="fas fa-magic"></i></span>
    <div class="ctn__body">
        <h3 class="ctn__title" id="ctnTitle">
            Format crosstab Excel dinormalisasi otomatis
            <span class="ctn__mode">{{ ($crosstabNormalization['mode'] ?? '') === 'merge' ? 'Unmerge sel' : 'Isi sel kosong dari atas' }}</span>
        </h3>
        <ul class="ctn__list">
            @foreach($crosstabNormalization['notices'] as $notice)
                <li>{!! preg_replace('/`([^`]+)`/', '<code>$1</code>', e($notice)) !!}</li>
            @endforeach
        </ul>
        <p class="ctn__foot">Nilai measure (saldo, baki debet, jumlah) tidak pernah diambil dari baris lain. File asli tidak diubah; yang diimport adalah hasil normalisasi di atas.</p>
    </div>
</section>
<style>
    .ctn { display: flex; gap: 0.75rem; align-items: flex-start; margin: 0 0 1rem; padding: 0.95rem 1.1rem; border-radius: 10px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #14532d; }
    .ctn__glyph { flex: none; display: inline-grid; place-items: center; width: 2rem; height: 2rem; border-radius: 8px; background: #dcfce7; color: #15803d; }
    .ctn__body { min-width: 0; }
    .ctn__title { margin: 0 0 0.35rem; font-size: 0.95rem; font-weight: 700; display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
    .ctn__mode { font-size: 0.72rem; font-weight: 600; padding: 0.1rem 0.55rem; border-radius: 999px; background: #ffffff; border: 1px solid #86efac; color: #166534; }
    .ctn__list { margin: 0; padding-left: 1.1rem; font-size: 0.86rem; line-height: 1.6; overflow-wrap: anywhere; }
    .ctn__foot { margin: 0.4rem 0 0; font-size: 0.78rem; color: #3f6212; }
    [data-theme="dark"] .ctn { background: #052e16; border-color: #166534; color: #dcfce7; }
    [data-theme="dark"] .ctn__glyph { background: #14532d; color: #86efac; }
    [data-theme="dark"] .ctn__mode { background: #14532d; border-color: #166534; color: #bbf7d0; }
    [data-theme="dark"] .ctn__foot { color: #bef264; }
</style>
@endif
