{{--
    Pemindai Struktur Sumber: membandingkan dokumen sumber dengan tabel tujuan
    sebelum import. Kolom baru menunggu keputusan Terima/Tolak; perbedaan format
    yang kompatibel (misalnya tanggal yang kini memuat jam) disesuaikan otomatis.
--}}
@if(!empty($schemaDriftSource) && \Illuminate\Support\Facades\Route::has('import.schema-drift.analyze'))
<section class="sdp" id="schemaDriftPanel" data-state="loading" aria-labelledby="sdpTitle"
    data-analyze-url="{{ route('import.schema-drift.analyze') }}"
    data-accept-url="{{ route('import.schema-drift.accept', ['event' => '__ID__']) }}"
    data-reject-url="{{ route('import.schema-drift.reject', ['event' => '__ID__']) }}"
    data-source="{{ $schemaDriftSource }}"
    data-mode="{{ ($schemaDriftMode ?? 'evolve') === 'monitor' ? 'monitor' : 'evolve' }}"
    data-reload-on-accept="{{ !empty($schemaDriftReloadOnAccept) ? '1' : '0' }}"
    data-csrf="{{ csrf_token() }}">
    <header class="sdp__head">
        <div class="sdp__identity">
            <span class="sdp__glyph" aria-hidden="true"><i class="fas fa-shield-alt"></i></span>
            <div class="sdp__titles">
                <h3 class="sdp__title" id="sdpTitle">Pemindai Struktur Sumber</h3>
                <p class="sdp__headline" data-sdp-headline aria-live="polite">Membaca header dan sampel data dokumen sumber…</p>
            </div>
        </div>
        <div class="sdp__head-actions">
            <span class="sdp__pill" data-sdp-pill>Memindai</span>
            <button type="button" class="sdp__ghost" data-sdp-rescan aria-label="Pindai ulang struktur sumber">
                <i class="fas fa-redo-alt" aria-hidden="true"></i><span class="sdp__ghost-label">Pindai ulang</span>
            </button>
        </div>
    </header>

    <div class="sdp__skeleton" data-sdp-skeleton aria-hidden="true">
        <span></span><span></span><span></span><span></span>
    </div>

    <div class="sdp__body" data-sdp-body hidden>
        <dl class="sdp__stats" data-sdp-stats></dl>
        <p class="sdp__source" data-sdp-source></p>

        <div class="sdp__block sdp__block--new" data-sdp-new hidden>
            <div class="sdp__block-head">
                <h4 class="sdp__block-title"><i class="fas fa-plus-square" aria-hidden="true"></i> Kolom baru di dokumen sumber</h4>
                <p class="sdp__block-note" data-sdp-new-note>Terima untuk menambah kolom ke tabel tujuan (NULLABLE, di akhir tabel; data lama tidak berubah) — atau Tolak agar import tetap berjalan tanpa kolom ini.</p>
            </div>
            <div class="sdp__table-wrap">
                <table class="sdp__table">
                    <thead>
                        <tr>
                            <th scope="col" class="sdp__col-check">Tambah</th>
                            <th scope="col">Kolom sumber &amp; contoh isi</th>
                            <th scope="col">Nama kolom database</th>
                            <th scope="col">Tipe data</th>
                            <th scope="col">Alasan deteksi</th>
                        </tr>
                    </thead>
                    <tbody data-sdp-new-rows></tbody>
                </table>
            </div>
            <div class="sdp__decision">
                <button type="button" class="sdp__btn sdp__btn--accept" data-sdp-accept>
                    <i class="fas fa-check" aria-hidden="true"></i> <span data-sdp-accept-label>Terima &amp; tambah kolom</span>
                </button>
                <button type="button" class="sdp__btn sdp__btn--reject" data-sdp-reject>
                    <i class="fas fa-times" aria-hidden="true"></i> <span data-sdp-reject-label>Tolak, import tanpa kolom ini</span>
                </button>
                <span class="sdp__decision-status" data-sdp-decision-status role="status" aria-live="polite"></span>
            </div>
        </div>

        <div class="sdp__block" data-sdp-adapt hidden>
            <h4 class="sdp__block-title"><i class="fas fa-magic" aria-hidden="true"></i> Penyesuaian format otomatis</h4>
            <ul class="sdp__findings" data-sdp-findings></ul>
        </div>

        <details class="sdp__block sdp__details" data-sdp-unmapped hidden>
            <summary><i class="fas fa-eye-slash" aria-hidden="true"></i> Kolom sumber yang tidak disimpan <span data-sdp-unmapped-count></span></summary>
            <p class="sdp__block-note">Kolom ini memang selalu ada di dokumen sumber tetapi tidak menjadi bagian tabel tujuan. Hanya informasi — import tidak terpengaruh.</p>
            <div class="sdp__chips" data-sdp-unmapped-list></div>
        </details>

        <div class="sdp__block" data-sdp-missing hidden>
            <h4 class="sdp__block-title"><i class="fas fa-unlink" aria-hidden="true"></i> Kolom tabel yang tidak ada di sumber</h4>
            <p class="sdp__block-note">Kolom berikut akan bernilai kosong untuk import ini.</p>
            <div class="sdp__chips" data-sdp-missing-list></div>
        </div>

        <details class="sdp__details">
            <summary>Peta kolom lengkap <span data-sdp-map-count></span></summary>
            <div class="sdp__table-wrap">
                <table class="sdp__table sdp__table--map">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Kolom sumber</th>
                            <th scope="col">Kolom tujuan</th>
                            <th scope="col">Format terdeteksi</th>
                            <th scope="col">Contoh</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody data-sdp-map-rows></tbody>
                </table>
            </div>
        </details>
    </div>

    <p class="sdp__error" data-sdp-error hidden role="alert"></p>
</section>

<style>
    .sdp {
        --sdp-accent: var(--bri-nusantara, #0857c3);
        --sdp-accent-soft: var(--app-info-soft, #edf6ff);
        position: relative;
        margin-bottom: 1rem;
        padding: 1.1rem 1.25rem 1.15rem;
        border: 1px solid var(--app-line, #d8e5f7);
        border-radius: var(--app-radius, 14px);
        background: var(--app-surface, #fff);
        box-shadow: var(--app-shadow, 0 18px 42px -30px rgba(4, 42, 95, 0.34));
        color: var(--app-ink, #0b1f3a);
        overflow: hidden;
    }
    .sdp::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: var(--sdp-accent);
        transition: background-color .25s ease;
    }
    .sdp[data-state="match"] { --sdp-accent: var(--app-success, #176b4d); --sdp-accent-soft: var(--app-success-soft, #edf8f3); }
    .sdp[data-state="adapted"] { --sdp-accent: var(--bri-nusantara, #0857c3); --sdp-accent-soft: var(--app-info-soft, #edf6ff); }
    .sdp[data-state="attention"] { --sdp-accent: var(--app-warning, #8a5200); --sdp-accent-soft: var(--app-warning-soft, #fff8e8); }
    .sdp[data-state="drift"] { --sdp-accent: #7c3aed; --sdp-accent-soft: rgba(124, 58, 237, .1); }
    .sdp[data-state="error"] { --sdp-accent: var(--app-danger, #b4232d); --sdp-accent-soft: var(--app-danger-soft, #fff1f2); }

    .sdp__head { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem 1rem; flex-wrap: wrap; }
    .sdp__identity { display: flex; gap: .85rem; align-items: flex-start; min-width: 0; flex: 1 1 22rem; }
    .sdp__glyph {
        flex: none; display: inline-flex; align-items: center; justify-content: center;
        width: 42px; height: 42px; border-radius: 12px;
        background: var(--sdp-accent-soft); color: var(--sdp-accent); font-size: 1.05rem;
    }
    .sdp__titles { min-width: 0; }
    .sdp__title { margin: 0; font-size: 1rem; font-weight: 800; letter-spacing: -.01em; }
    .sdp__headline { margin: .2rem 0 0; color: var(--app-muted, #526987); font-size: .9rem; line-height: 1.45; text-wrap: pretty; }
    .sdp__head-actions { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
    .sdp__pill {
        display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .7rem;
        border-radius: 999px; font-size: .75rem; font-weight: 800; letter-spacing: .02em; text-transform: uppercase;
        background: var(--sdp-accent-soft); color: var(--sdp-accent); white-space: nowrap;
    }
    .sdp__ghost {
        display: inline-flex; align-items: center; gap: .4rem; min-height: 36px; padding: 0 .75rem;
        border: 1px solid var(--app-line, #d8e5f7); border-radius: 10px; background: transparent;
        color: var(--app-ink, #0b1f3a); font-size: .82rem; font-weight: 700; cursor: pointer;
    }
    .sdp__ghost:hover { border-color: var(--app-line-strong, #b9cfee); background: var(--theme-hover, rgba(8, 87, 195, .06)); }
    .sdp button:focus-visible, .sdp input:focus-visible, .sdp select:focus-visible, .sdp summary:focus-visible {
        outline: 3px solid var(--app-focus-ring, rgba(48, 127, 226, .34)); outline-offset: 2px;
    }

    .sdp__skeleton { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .65rem; margin-top: 1rem; }
    .sdp__skeleton span {
        height: 64px; border-radius: 12px;
        background: linear-gradient(90deg, var(--app-surface-soft, #f7faff) 0%, var(--app-line, #e6eefa) 50%, var(--app-surface-soft, #f7faff) 100%);
        background-size: 200% 100%; animation: sdpShimmer 1.4s ease-in-out infinite;
    }
    @keyframes sdpShimmer { from { background-position: 100% 0; } to { background-position: -100% 0; } }

    .sdp__stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .65rem; margin: 1rem 0 .5rem; }
    .sdp__stat {
        margin: 0; padding: .7rem .85rem; border-radius: 12px;
        border: 1px solid var(--app-line, #d8e5f7); background: var(--app-surface-soft, #f7faff);
    }
    .sdp__stat dt { font-size: .72rem; font-weight: 700; color: var(--app-muted, #526987); text-transform: uppercase; letter-spacing: .04em; }
    .sdp__stat dd { margin: .15rem 0 0; font-size: 1.35rem; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1.15; }
    .sdp__stat dd small { font-size: .78rem; font-weight: 600; color: var(--app-muted, #526987); }
    .sdp__stat--hot { border-color: var(--sdp-accent); background: var(--sdp-accent-soft); }
    .sdp__stat--hot dd { color: var(--sdp-accent); }
    .sdp__source { margin: 0 0 .25rem; font-size: .8rem; color: var(--app-muted, #526987); overflow-wrap: anywhere; }

    .sdp__block { margin-top: 1rem; padding-top: .9rem; border-top: 1px dashed var(--app-line, #d8e5f7); }
    .sdp__block-title { margin: 0 0 .25rem; font-size: .9rem; font-weight: 800; display: flex; align-items: center; gap: .45rem; }
    .sdp__block-title i { color: var(--sdp-accent); }
    .sdp__block-note { margin: 0 0 .6rem; font-size: .82rem; color: var(--app-muted, #526987); line-height: 1.45; }

    .sdp__table-wrap { overflow-x: auto; border: 1px solid var(--app-line, #d8e5f7); border-radius: 12px; -webkit-overflow-scrolling: touch; }
    .sdp__table { width: 100%; border-collapse: collapse; font-size: .84rem; }
    .sdp__table th {
        position: sticky; top: 0; padding: .55rem .7rem; text-align: left; white-space: nowrap;
        background: var(--app-surface-soft, #f7faff); color: var(--app-muted, #526987);
        font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em;
        border-bottom: 1px solid var(--app-line, #d8e5f7);
    }
    .sdp__table td { padding: .6rem .7rem; vertical-align: top; border-bottom: 1px solid var(--app-line, #e6eefa); }
    .sdp__table tbody tr:last-child td { border-bottom: 0; }
    .sdp__col-check { width: 1%; }
    .sdp__table input[type="text"], .sdp__table select {
        width: 100%; min-width: 9.5rem; min-height: 36px; padding: .3rem .55rem;
        border: 1px solid var(--app-line-strong, #b9cfee); border-radius: 9px;
        background: var(--app-surface, #fff); color: var(--app-ink, #0b1f3a); font-size: .84rem;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }
    .sdp__table input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--sdp-accent); cursor: pointer; }
    .sdp__table tr[data-excluded="1"] td:not(:first-child) { opacity: .45; }
    .sdp__header-name { font-weight: 800; overflow-wrap: anywhere; }
    .sdp__kind { display: inline-block; margin-top: .2rem; font-size: .72rem; font-weight: 700; color: var(--app-muted, #526987); }
    .sdp__reason { min-width: 14rem; color: var(--app-muted, #526987); font-size: .8rem; line-height: 1.4; }

    .sdp__chips { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .3rem; }
    .sdp__chip {
        display: inline-flex; align-items: center; max-width: 100%; padding: .12rem .5rem; border-radius: 7px;
        background: var(--app-surface-soft, #f7faff); border: 1px solid var(--app-line, #d8e5f7);
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .74rem;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    .sdp__decision { display: flex; align-items: center; gap: .55rem; flex-wrap: wrap; margin-top: .75rem; }
    .sdp__btn {
        display: inline-flex; align-items: center; gap: .45rem; min-height: 40px; padding: 0 1rem;
        border-radius: 10px; font-weight: 800; font-size: .86rem; cursor: pointer; border: 1px solid transparent;
        transition: transform .12s ease, box-shadow .2s ease, background-color .2s ease;
    }
    .sdp__btn:active { transform: translateY(1px); }
    .sdp__btn[disabled] { opacity: .6; cursor: progress; }
    .sdp__btn--accept { background: #7c3aed; color: #fff; box-shadow: 0 10px 22px -14px rgba(124, 58, 237, .9); }
    .sdp__btn--accept:hover { background: #6d28d9; }
    .sdp__btn--reject { background: transparent; color: var(--app-ink, #0b1f3a); border-color: var(--app-line-strong, #b9cfee); }
    .sdp__btn--reject:hover { background: var(--theme-hover, rgba(8, 87, 195, .06)); }
    .sdp__decision-status { font-size: .82rem; font-weight: 700; color: var(--app-muted, #526987); }

    .sdp__findings { list-style: none; margin: .4rem 0 0; padding: 0; display: grid; gap: .45rem; }
    .sdp__finding {
        display: grid; grid-template-columns: auto 1fr; gap: .2rem .65rem; padding: .6rem .75rem;
        border-radius: 11px; border: 1px solid var(--app-line, #d8e5f7); background: var(--app-surface-soft, #f7faff);
    }
    .sdp__finding-icon { grid-row: span 2; width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: .78rem; }
    .sdp__finding--info .sdp__finding-icon { background: var(--app-info-soft, #edf6ff); color: var(--bri-nusantara, #0857c3); }
    .sdp__finding--warning .sdp__finding-icon { background: var(--app-warning-soft, #fff8e8); color: var(--app-warning, #8a5200); }
    .sdp__finding--danger .sdp__finding-icon { background: var(--app-danger-soft, #fff1f2); color: var(--app-danger, #b4232d); }
    .sdp__finding--danger { border-color: var(--app-danger, #b4232d); }
    .sdp__finding-title { font-weight: 800; font-size: .84rem; }
    .sdp__finding-title code { font-size: .78rem; margin-right: .3rem; }
    .sdp__finding-detail { font-size: .8rem; color: var(--app-muted, #526987); line-height: 1.45; overflow-wrap: anywhere; }

    .sdp__details { margin-top: 1rem; }
    .sdp__details summary { cursor: pointer; font-weight: 800; font-size: .85rem; padding: .35rem 0; }
    .sdp__details[open] summary { margin-bottom: .5rem; }
    .sdp__state { display: inline-block; padding: .1rem .45rem; border-radius: 6px; font-size: .7rem; font-weight: 800; white-space: nowrap; }
    .sdp__state--matched { background: var(--app-success-soft, #edf8f3); color: var(--app-success, #176b4d); }
    .sdp__state--new { background: rgba(124, 58, 237, .12); color: #7c3aed; }
    .sdp__state--rejected, .sdp__state--ignored, .sdp__state--duplicate, .sdp__state--unmapped { background: var(--app-surface-soft, #f7faff); color: var(--app-muted, #526987); }
    .sdp__state--adapted { background: var(--app-info-soft, #edf6ff); color: var(--bri-nusantara, #0857c3); }
    .sdp__state--issue { background: var(--app-warning-soft, #fff8e8); color: var(--app-warning, #8a5200); }

    .sdp__error { margin: .85rem 0 0; padding: .6rem .8rem; border-radius: 10px; background: var(--app-danger-soft, #fff1f2); color: var(--app-danger, #b4232d); font-weight: 700; font-size: .85rem; }
    .sdp-flash { animation: sdpFlash 1.2s ease 2; }
    @keyframes sdpFlash { 50% { box-shadow: 0 0 0 4px rgba(124, 58, 237, .35); } }

    :root[data-theme="dark"] .sdp[data-state="drift"] { --sdp-accent: #b794f6; --sdp-accent-soft: rgba(183, 148, 246, .14); }
    :root[data-theme="dark"] .sdp__btn--accept { background: #8b5cf6; }
    :root[data-theme="dark"] .sdp__state--new { color: #c4b5fd; background: rgba(183, 148, 246, .16); }

    @media (max-width: 767.98px) {
        .sdp { padding: .95rem .9rem 1rem; }
        .sdp__stats, .sdp__skeleton { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .sdp__ghost-label { display: none; }
        .sdp__btn { flex: 1 1 100%; justify-content: center; }
    }
    @media (prefers-reduced-motion: reduce) {
        .sdp__skeleton span, .sdp-flash { animation: none; }
        .sdp__btn, .sdp::before { transition: none; }
    }
</style>

<script>
(function () {
    'use strict';
    var panel = document.getElementById('schemaDriftPanel');
    if (!panel) { return; }

    var state = { report: null, busy: false, confirmedWithoutDecision: false };
    // Monitor mode: the report has a fixed import layout, so drift is shown and
    // can be acknowledged, but no column is offered and the import is not held.
    var monitorMode = panel.dataset.mode === 'monitor';
    var reloadOnAccept = panel.dataset.reloadOnAccept === '1';
    window.SchemaDriftPanel = { hasPendingDecision: function () { return !monitorMode && !!(state.report && state.report.has_pending_decision && pendingEvent()); } };

    var STATUS = {
        match: { pill: 'Identik', icon: 'fa-check-circle' },
        adapted: { pill: 'Disesuaikan otomatis', icon: 'fa-magic' },
        attention: { pill: 'Perlu perhatian', icon: 'fa-exclamation-triangle' },
        drift: { pill: 'Kolom baru', icon: 'fa-code-branch' },
        error: { pill: 'Gagal memindai', icon: 'fa-times-circle' }
    };
    var SEVERITY_ICON = { info: 'fa-magic', warning: 'fa-exclamation', danger: 'fa-ban' };

    function $(selector) { return panel.querySelector(selector); }
    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined && text !== null) { node.textContent = String(text); }
        return node;
    }
    function icon(name) { var i = el('i', 'fas ' + name); i.setAttribute('aria-hidden', 'true'); return i; }
    function pendingEvent() { var e = state.report && state.report.event; return e && e.status === 'pending' ? e : null; }

    function request(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': panel.dataset.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body || {}),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok || data.status !== 'ok') { throw new Error(data.message || ('Permintaan gagal (' + response.status + ').')); }
                return data;
            });
        });
    }

    function setState(name, headline) {
        panel.dataset.state = name;
        var meta = STATUS[name] || { pill: 'Memindai', icon: 'fa-circle-notch' };
        var pill = $('[data-sdp-pill]');
        pill.textContent = '';
        pill.appendChild(icon(meta.icon));
        pill.appendChild(document.createTextNode(meta.pill));
        if (headline) { $('[data-sdp-headline]').textContent = headline; }
    }

    function analyze() {
        if (state.busy) { return; }
        state.busy = true;
        $('[data-sdp-error]').hidden = true;
        $('[data-sdp-skeleton]').hidden = false;
        $('[data-sdp-body]').hidden = true;
        panel.dataset.state = 'loading';
        $('[data-sdp-headline]').textContent = 'Membaca header dan sampel data dokumen sumber…';
        $('[data-sdp-pill]').textContent = 'Memindai';

        request(panel.dataset.analyzeUrl, { source: panel.dataset.source })
            .then(function (data) { render(data.report); })
            .catch(function (error) {
                setState('error', 'Pemindaian tidak dapat diselesaikan. Import tetap dapat dijalankan seperti biasa.');
                var box = $('[data-sdp-error]');
                box.textContent = error.message;
                box.hidden = false;
                $('[data-sdp-skeleton]').hidden = true;
            })
            .finally(function () { state.busy = false; });
    }

    function render(report) {
        state.report = report;
        $('[data-sdp-skeleton]').hidden = true;
        $('[data-sdp-body]').hidden = false;
        setState(report.status, report.headline);
        renderStats(report);
        renderSource(report);
        renderNewColumns(report);
        renderFindings(report);
        renderUnmapped(report);
        renderMissing(report);
        renderMap(report);
        document.dispatchEvent(new CustomEvent('schema-drift:analyzed', { detail: report }));
    }

    function renderStats(report) {
        var stats = report.stats || {};
        var host = $('[data-sdp-stats]');
        host.textContent = '';
        [
            { label: 'Kolom cocok', value: stats.matched, total: stats.source_columns },
            { label: 'Kolom baru', value: stats.new, hot: stats.new > 0 },
            { label: 'Format disesuaikan', value: stats.adaptations, hot: stats.adaptations > 0 && report.status !== 'drift' },
            { label: 'Tidak ada di sumber', value: stats.missing }
        ].forEach(function (item) {
            var wrap = el('div', 'sdp__stat' + (item.hot ? ' sdp__stat--hot' : ''));
            wrap.appendChild(el('dt', null, item.label));
            var dd = el('dd', null, item.value || 0);
            if (item.total !== undefined) { dd.appendChild(el('small', null, ' / ' + item.total)); }
            wrap.appendChild(dd);
            host.appendChild(wrap);
        });
    }

    function renderSource(report) {
        var source = report.source || {};
        var delimiter = { ',': 'koma', ';': 'titik koma', '\t': 'tab', '|': 'pipe' }[source.delimiter] || (source.format === 'spreadsheet' ? 'Excel' : '-');
        var parts = [
            source.name,
            'header di baris ' + source.header_row,
            (report.stats.sample_rows || 0).toLocaleString('id-ID') + ' baris sampel',
            'pemisah ' + delimiter,
            'dipindai ' + report.analyzed_in_ms + ' ms'
        ];
        var periodMeta = (source.metadata || []).filter(function (line) { return /periode|tanggal|posisi/i.test(line); })[0];
        if (periodMeta) { parts.push(periodMeta.replace(/\s+/g, ' ')); }
        $('[data-sdp-source]').textContent = parts.filter(Boolean).join(' · ');
    }

    function renderNewColumns(report) {
        var block = $('[data-sdp-new]');
        var rows = $('[data-sdp-new-rows]');
        var pending = (report.new_columns || []).filter(function (column) { return column.decision === 'pending'; });
        var event = pendingEvent();
        rows.textContent = '';
        block.hidden = pending.length === 0;
        if (!pending.length) { return; }

        if (monitorMode) {
            renderMonitorColumns(pending, rows, event);
            return;
        }

        $('[data-sdp-accept]').hidden = false;
        pending.forEach(function (column) {
            var tr = el('tr');
            tr.dataset.proposed = column.proposed_column;

            var checkCell = el('td');
            var check = el('input');
            check.type = 'checkbox';
            check.checked = true;
            check.setAttribute('aria-label', 'Tambahkan kolom ' + column.header);
            check.dataset.role = 'include';
            check.addEventListener('change', function () { tr.dataset.excluded = check.checked ? '0' : '1'; updateAcceptLabel(); });
            checkCell.appendChild(check);
            tr.appendChild(checkCell);

            var sourceCell = el('td');
            sourceCell.appendChild(el('div', 'sdp__header-name', column.header));
            sourceCell.appendChild(el('span', 'sdp__kind', column.label + ' · ' + (column.non_empty || 0) + ' nilai terisi'));
            var chips = el('div', 'sdp__chips');
            (column.samples || []).slice(0, 3).forEach(function (sample) { chips.appendChild(el('span', 'sdp__chip', sample)); });
            sourceCell.appendChild(chips);
            tr.appendChild(sourceCell);

            var nameCell = el('td');
            var name = el('input');
            name.type = 'text';
            name.value = column.proposed_column;
            name.maxLength = 64;
            name.pattern = '[a-z][a-z0-9_]{0,63}';
            name.spellcheck = false;
            name.autocomplete = 'off';
            name.dataset.role = 'name';
            name.setAttribute('aria-label', 'Nama kolom database untuk ' + column.header);
            name.addEventListener('input', function () { name.value = name.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'); });
            nameCell.appendChild(name);
            tr.appendChild(nameCell);

            var typeCell = el('td');
            var select = el('select');
            select.dataset.role = 'type';
            select.setAttribute('aria-label', 'Tipe data untuk ' + column.header);
            (column.type_options || [column.proposed_type]).forEach(function (type) {
                var option = el('option', null, type);
                option.value = type;
                option.selected = type === column.proposed_type;
                select.appendChild(option);
            });
            typeCell.appendChild(select);
            tr.appendChild(typeCell);

            tr.appendChild(el('td', 'sdp__reason', column.reason));
            rows.appendChild(tr);
        });

        $('[data-sdp-accept]').disabled = !event;
        $('[data-sdp-reject]').disabled = !event;
        $('[data-sdp-decision-status]').textContent = event ? '' : 'Notifikasi belum tercatat; muat ulang untuk memutuskan.';
        updateAcceptLabel();
    }

    function renderMonitorColumns(pending, rows, event) {
        $('[data-sdp-new-note]').textContent = 'Report ini memakai layout import kolom tetap, jadi kolom baru dicatat sebagai notifikasi tanpa ditambahkan ke tabel. Data tetap masuk seperti biasa; tandai sebagai ditinjau setelah Anda memeriksanya.';
        $('[data-sdp-accept]').hidden = true;
        $('[data-sdp-reject-label]').textContent = 'Tandai sudah ditinjau';
        pending.forEach(function (column) {
            var tr = el('tr');
            tr.dataset.proposed = column.proposed_column;
            tr.appendChild(el('td', 'sdp__col-check', '—'));
            var sourceCell = el('td');
            sourceCell.appendChild(el('div', 'sdp__header-name', column.header));
            sourceCell.appendChild(el('span', 'sdp__kind', column.label + ' · ' + (column.non_empty || 0) + ' nilai terisi'));
            var chips = el('div', 'sdp__chips');
            (column.samples || []).slice(0, 3).forEach(function (sample) { chips.appendChild(el('span', 'sdp__chip', sample)); });
            sourceCell.appendChild(chips);
            tr.appendChild(sourceCell);
            tr.appendChild(el('td', null, column.proposed_column));
            tr.appendChild(el('td', null, column.proposed_type));
            tr.appendChild(el('td', 'sdp__reason', column.reason));
            rows.appendChild(tr);
        });
        $('[data-sdp-reject]').disabled = !event;
        $('[data-sdp-decision-status]').textContent = event ? '' : 'Notifikasi belum tercatat; muat ulang untuk menandai.';
    }

    function renderUnmapped(report) {
        var block = $('[data-sdp-unmapped]');
        var list = $('[data-sdp-unmapped-list]');
        var unmapped = report.unmapped_columns || [];
        list.textContent = '';
        block.hidden = unmapped.length === 0;
        $('[data-sdp-unmapped-count]').textContent = '(' + unmapped.length + ')';
        unmapped.forEach(function (column) {
            var chip = el('span', 'sdp__chip', column.header + ((column.samples || [])[0] ? ' · ' + column.samples[0] : ''));
            chip.title = (column.label || '') + (column.proposed_type ? ' → ' + column.proposed_type : '');
            list.appendChild(chip);
        });
    }

    function updateAcceptLabel() {
        var included = panel.querySelectorAll('[data-sdp-new-rows] input[data-role="include"]:checked').length;
        $('[data-sdp-accept-label]').textContent = included > 0 ? 'Terima & tambah ' + included + ' kolom' : 'Terima tanpa menambah kolom';
    }

    function renderFindings(report) {
        var block = $('[data-sdp-adapt]');
        var list = $('[data-sdp-findings]');
        list.textContent = '';
        var findings = report.adaptations || [];
        block.hidden = findings.length === 0;
        var order = { danger: 0, warning: 1, info: 2 };
        findings.slice().sort(function (a, b) { return (order[a.severity] || 3) - (order[b.severity] || 3); }).forEach(function (finding) {
            var item = el('li', 'sdp__finding sdp__finding--' + finding.severity);
            var badge = el('span', 'sdp__finding-icon');
            badge.appendChild(icon(SEVERITY_ICON[finding.severity] || 'fa-info'));
            item.appendChild(badge);
            var title = el('div', 'sdp__finding-title');
            title.appendChild(el('code', null, String(finding.header || finding.column || '').toUpperCase()));
            title.appendChild(document.createTextNode(finding.title));
            item.appendChild(title);
            item.appendChild(el('div', 'sdp__finding-detail', finding.detail));
            list.appendChild(item);
        });
    }

    function renderMissing(report) {
        var missing = report.missing_columns || [];
        var block = $('[data-sdp-missing]');
        var list = $('[data-sdp-missing-list]');
        list.textContent = '';
        block.hidden = missing.length === 0;
        missing.forEach(function (column) { list.appendChild(el('span', 'sdp__chip', column)); });
    }

    function renderMap(report) {
        var rows = $('[data-sdp-map-rows]');
        rows.textContent = '';
        var columns = report.columns || [];
        $('[data-sdp-map-count]').textContent = '(' + columns.length + ')';
        var labels = { matched: 'Cocok', new: 'Baru', rejected: 'Ditolak', ignored: 'Diabaikan', duplicate: 'Ganda', unmapped: 'Tidak disimpan' };
        columns.forEach(function (column) {
            var tr = el('tr');
            tr.appendChild(el('td', null, column.index + 1));
            tr.appendChild(el('td', 'sdp__header-name', column.header || '(kosong)'));
            tr.appendChild(el('td', null, column.db_column || '—'));
            tr.appendChild(el('td', null, column.label));
            var sampleCell = el('td');
            (column.samples || []).slice(0, 1).forEach(function (sample) { sampleCell.appendChild(el('span', 'sdp__chip', sample)); });
            tr.appendChild(sampleCell);
            var findings = column.findings || [];
            var hasIssue = findings.some(function (f) { return f.severity !== 'info'; });
            var stateKey = column.state === 'matched' && findings.length ? (hasIssue ? 'issue' : 'adapted') : column.state;
            var stateLabel = stateKey === 'adapted' ? 'Disesuaikan' : (stateKey === 'issue' ? 'Perhatian' : (labels[column.state] || column.state));
            var stateCell = el('td');
            stateCell.appendChild(el('span', 'sdp__state sdp__state--' + stateKey, stateLabel));
            tr.appendChild(stateCell);
            rows.appendChild(tr);
        });
    }

    function collectSelections() {
        return Array.prototype.map.call(panel.querySelectorAll('[data-sdp-new-rows] tr'), function (tr) {
            return {
                proposed_column: tr.dataset.proposed,
                include: tr.querySelector('[data-role="include"]').checked,
                name: tr.querySelector('[data-role="name"]').value.trim(),
                type: tr.querySelector('[data-role="type"]').value
            };
        });
    }

    function decide(kind) {
        var event = pendingEvent();
        if (!event || state.busy) { return; }
        var selections = collectSelections();
        var invalid = selections.filter(function (s) { return s.include && !/^[a-z][a-z0-9_]{0,63}$/.test(s.name); })[0];
        if (kind === 'accept' && invalid) {
            $('[data-sdp-decision-status]').textContent = 'Nama kolom "' + invalid.name + '" tidak valid: awali dengan huruf, gunakan huruf kecil/angka/garis bawah.';
            return;
        }

        state.busy = true;
        var accept = $('[data-sdp-accept]');
        var reject = $('[data-sdp-reject]');
        accept.disabled = true;
        reject.disabled = true;
        $('[data-sdp-decision-status]').textContent = kind === 'accept' ? 'Menambahkan kolom ke tabel tujuan…' : 'Menyimpan keputusan…';

        var url = (kind === 'accept' ? panel.dataset.acceptUrl : panel.dataset.rejectUrl).replace('__ID__', encodeURIComponent(event.id));
        request(url, kind === 'accept' ? { columns: selections } : {})
            .then(function (data) {
                document.dispatchEvent(new CustomEvent('schema-drift:decided', { detail: data }));
                state.busy = false;
                var appliedCount = ((data.event && data.event.applied_columns) || []).length;
                if (kind === 'accept' && reloadOnAccept && appliedCount > 0) {
                    // The column list of this preview is rendered server side;
                    // reload so the new column is part of this very import.
                    $('[data-sdp-decision-status]').textContent = 'Kolom ditambahkan. Memuat ulang preview agar kolom baru ikut diimport…';
                    notify('success', 'Struktur diperbarui', data.message + ' Preview dimuat ulang…');
                    window.setTimeout(function () { window.location.reload(); }, 1400);
                    return;
                }
                notify('success', kind === 'accept' ? 'Struktur diperbarui' : (monitorMode ? 'Ditandai sudah ditinjau' : 'Kolom baru ditolak'), data.message);
                analyze();
            })
            .catch(function (error) {
                state.busy = false;
                accept.disabled = false;
                reject.disabled = false;
                $('[data-sdp-decision-status]').textContent = error.message;
                notify('error', 'Keputusan gagal', error.message);
            });
    }

    function notify(icon, title, text) {
        var swal = window.ImportSwal && typeof window.ImportSwal.fire === 'function' ? window.ImportSwal : window.Swal;
        if (swal && typeof swal.fire === 'function') {
            swal.fire({ icon: icon, title: title, text: text, timer: icon === 'success' ? 3200 : undefined, showConfirmButton: icon !== 'success' });
        }
    }

    // Import must not silently drop new columns: ask once when a decision is still open.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.id !== 'importForm' || state.confirmedWithoutDecision || !window.SchemaDriftPanel.hasPendingDecision()) { return; }
        e.preventDefault();
        e.stopImmediatePropagation();

        var pending = (state.report.new_columns || []).filter(function (c) { return c.decision === 'pending'; }).map(function (c) { return c.header; });
        var message = pending.length + ' kolom baru (' + pending.slice(0, 4).join(', ') + (pending.length > 4 ? ', …' : '') + ') belum diputuskan. Jika dilanjutkan, data tetap masuk tetapi kolom tersebut tidak ikut tersimpan.';
        var proceed = function () { state.confirmedWithoutDecision = true; if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); } };
        var review = function () { panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); panel.classList.remove('sdp-flash'); void panel.offsetWidth; panel.classList.add('sdp-flash'); };
        var swal = window.ImportSwal && typeof window.ImportSwal.fire === 'function' ? window.ImportSwal : window.Swal;
        if (swal && typeof swal.fire === 'function') {
            swal.fire({ icon: 'question', title: 'Ada kolom baru di dokumen sumber', text: message, showCancelButton: true, confirmButtonText: 'Tinjau kolom baru', cancelButtonText: 'Import tanpa kolom baru', reverseButtons: true })
                .then(function (result) {
                    if (result.isConfirmed) { review(); }
                    else if (result.dismiss === 'cancel') { proceed(); }
                });
        } else if (window.confirm(message + '\n\nOK = import tanpa kolom baru, Batal = tinjau dulu.')) {
            proceed();
        } else {
            review();
        }
    }, true);

    $('[data-sdp-rescan]').addEventListener('click', analyze);
    $('[data-sdp-accept]').addEventListener('click', function () { decide('accept'); });
    $('[data-sdp-reject]').addEventListener('click', function () { decide('reject'); });
    document.addEventListener('schema-drift:feed-decided', function () { analyze(); });

    analyze();
}());
</script>
@endif
