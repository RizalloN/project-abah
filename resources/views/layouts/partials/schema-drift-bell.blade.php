{{-- Notifikasi perubahan struktur dokumen sumber import (khusus admin). --}}
<li class="nav-item d-flex align-items-center mr-2 sdb" id="schemaDriftBell"
    data-feed-url="{{ route('import.schema-drift.notifications') }}"
    data-accept-url="{{ route('import.schema-drift.accept', ['event' => '__ID__']) }}"
    data-reject-url="{{ route('import.schema-drift.reject', ['event' => '__ID__']) }}"
    data-ack-url="{{ route('import.schema-drift.acknowledge', ['event' => '__ID__']) }}"
    data-csrf="{{ csrf_token() }}">
    <button type="button" class="sdb__trigger" aria-haspopup="true" aria-expanded="false" aria-controls="schemaDriftBellMenu" aria-label="Notifikasi perubahan struktur import" title="Perubahan struktur import">
        <i class="fas fa-bell" aria-hidden="true"></i>
        <span class="sdb__badge" data-sdb-badge hidden>0</span>
    </button>
    <div class="sdb__menu" id="schemaDriftBellMenu" role="dialog" aria-label="Perubahan struktur import" hidden>
        <div class="sdb__menu-head">
            <strong>Perubahan Struktur Sumber</strong>
            <span class="sdb__menu-sub" data-sdb-sub>Memuat…</span>
        </div>
        <ul class="sdb__list" data-sdb-list></ul>
    </div>
</li>

<style>
    .sdb { position: relative; }
    .sdb__trigger {
        position: relative; display: inline-flex; align-items: center; justify-content: center;
        width: 42px; height: 42px; padding: 0; border-radius: 12px; cursor: pointer;
        border: 1px solid #e2e8f0; background: #f8fafc; color: #334155;
        transition: border-color .18s ease, color .18s ease;
    }
    .sdb__trigger:hover { border-color: #b9cfee; color: #0857c3; }
    .sdb__trigger:focus-visible, .sdb__menu button:focus-visible { outline: 3px solid var(--app-focus-ring, rgba(48, 127, 226, .34)); outline-offset: 2px; }
    .sdb__badge {
        position: absolute; top: -6px; right: -6px; min-width: 20px; height: 20px; padding: 0 5px;
        border-radius: 999px; background: #7c3aed; color: #fff; font-size: .7rem; font-weight: 800;
        display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 0 0 2px var(--app-surface, #fff);
        font-variant-numeric: tabular-nums;
    }
    .sdb__badge--info { background: var(--bri-nusantara, #0857c3); }
    .sdb__trigger[data-has-pending="1"] i { animation: sdbRing 2.6s ease-in-out infinite; transform-origin: 50% 0; }
    @keyframes sdbRing { 0%, 86%, 100% { transform: rotate(0); } 89% { transform: rotate(14deg); } 93% { transform: rotate(-12deg); } 97% { transform: rotate(6deg); } }

    .sdb__menu {
        position: absolute; top: calc(100% + 10px); right: 0; z-index: 1060;
        width: min(420px, calc(100vw - 24px)); max-height: min(70vh, 560px); overflow-y: auto;
        border: 1px solid var(--app-line, #d8e5f7); border-radius: 14px;
        background: var(--app-surface, #fff); color: var(--app-ink, #0b1f3a);
        box-shadow: var(--app-shadow-raised, 0 22px 52px -32px rgba(4, 42, 95, .42));
    }
    .sdb__menu-head { position: sticky; top: 0; display: flex; flex-direction: column; gap: .1rem; padding: .8rem 1rem; background: var(--app-surface, #fff); border-bottom: 1px solid var(--app-line, #d8e5f7); }
    .sdb__menu-sub { font-size: .78rem; color: var(--app-muted, #526987); }
    .sdb__list { list-style: none; margin: 0; padding: .35rem; }
    .sdb__item { padding: .7rem .75rem; border-radius: 11px; }
    .sdb__item + .sdb__item { border-top: 1px solid var(--app-line, #eef3fb); }
    .sdb__item-top { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; margin-bottom: .2rem; }
    .sdb__tag { padding: .08rem .45rem; border-radius: 6px; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; }
    .sdb__tag--pending { background: rgba(124, 58, 237, .12); color: #7c3aed; }
    .sdb__tag--auto_adapted { background: var(--app-info-soft, #edf6ff); color: var(--bri-nusantara, #0857c3); }
    .sdb__tag--attention, .sdb__tag--failed { background: var(--app-danger-soft, #fff1f2); color: var(--app-danger, #b4232d); }
    .sdb__report { font-weight: 800; font-size: .84rem; overflow-wrap: anywhere; }
    .sdb__summary { margin: 0; font-size: .8rem; color: var(--app-muted, #526987); line-height: 1.45; overflow-wrap: anywhere; }
    .sdb__meta { font-size: .72rem; color: var(--app-muted, #526987); margin-top: .25rem; }
    .sdb__cols { display: flex; flex-wrap: wrap; gap: .3rem; margin: .45rem 0 0; }
    .sdb__col { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .72rem; padding: .1rem .4rem; border-radius: 6px; background: var(--app-surface-soft, #f7faff); border: 1px solid var(--app-line, #d8e5f7); }
    .sdb__actions { display: flex; gap: .4rem; flex-wrap: wrap; margin-top: .55rem; }
    .sdb__btn { min-height: 32px; padding: 0 .7rem; border-radius: 8px; font-size: .78rem; font-weight: 800; cursor: pointer; border: 1px solid var(--app-line-strong, #b9cfee); background: transparent; color: var(--app-ink, #0b1f3a); }
    .sdb__btn--primary { background: #7c3aed; border-color: #7c3aed; color: #fff; }
    .sdb__btn[disabled] { opacity: .6; cursor: progress; }
    .sdb__empty { padding: 1.2rem 1rem; text-align: center; color: var(--app-muted, #526987); font-size: .84rem; }
    .sdb__empty i { display: block; font-size: 1.4rem; margin-bottom: .35rem; color: var(--app-success, #176b4d); }
    :root[data-theme="dark"] .sdb__trigger { border-color: var(--app-line, #2d3c53); background: var(--theme-raised, #1c2c43); color: var(--app-ink, #e2ecf7); }
    :root[data-theme="dark"] .sdb__tag--pending { color: #c4b5fd; background: rgba(183, 148, 246, .16); }
    @media (prefers-reduced-motion: reduce) { .sdb__trigger[data-has-pending="1"] i { animation: none; } }
</style>

<script>
(function () {
    'use strict';
    var root = document.getElementById('schemaDriftBell');
    if (!root || !window.fetch) { return; }
    var trigger = root.querySelector('.sdb__trigger');
    var menu = root.querySelector('.sdb__menu');
    var badge = root.querySelector('[data-sdb-badge]');
    var list = root.querySelector('[data-sdb-list]');
    var sub = root.querySelector('[data-sdb-sub]');
    var TAG = { pending: 'Perlu keputusan', auto_adapted: 'Disesuaikan otomatis', attention: 'Perlu perhatian', failed: 'Gagal diterapkan' };

    function el(tag, className, text) { var n = document.createElement(tag); if (className) { n.className = className; } if (text != null) { n.textContent = String(text); } return n; }
    function url(key, id) { return root.dataset[key].replace('__ID__', encodeURIComponent(id)); }

    function post(target) {
        return fetch(target, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf, 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { if (!r.ok || d.status !== 'ok') { throw new Error(d.message || 'Permintaan gagal.'); } return d; }); });
    }

    function render(feed) {
        var count = feed.count || 0;
        badge.hidden = count === 0;
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.classList.toggle('sdb__badge--info', (feed.pending || 0) === 0);
        trigger.dataset.hasPending = (feed.pending || 0) > 0 ? '1' : '0';
        trigger.setAttribute('aria-label', count ? count + ' notifikasi perubahan struktur import' : 'Tidak ada notifikasi perubahan struktur import');
        sub.textContent = count ? (feed.pending ? feed.pending + ' perlu keputusan' : 'Semua sudah disesuaikan otomatis') : 'Semua dokumen sumber sesuai struktur tabel';

        list.textContent = '';
        if (!feed.items || !feed.items.length) {
            var empty = el('li', 'sdb__empty');
            var i = el('i', 'fas fa-check-circle'); i.setAttribute('aria-hidden', 'true');
            empty.appendChild(i);
            empty.appendChild(document.createTextNode('Tidak ada perubahan struktur yang menunggu.'));
            list.appendChild(empty);
            return;
        }

        feed.items.forEach(function (item) {
            var li = el('li', 'sdb__item');
            var top = el('div', 'sdb__item-top');
            top.appendChild(el('span', 'sdb__tag sdb__tag--' + item.status, TAG[item.status] || item.status));
            top.appendChild(el('span', 'sdb__report', item.report_label));
            li.appendChild(top);
            li.appendChild(el('p', 'sdb__summary', item.error || item.summary));

            var pendingColumns = (item.new_columns || []).filter(function (c) { return c.decision === 'pending'; });
            if (item.status === 'pending' && pendingColumns.length) {
                var cols = el('div', 'sdb__cols');
                pendingColumns.slice(0, 8).forEach(function (c) { cols.appendChild(el('span', 'sdb__col', c.proposed_column + ' · ' + c.proposed_type)); });
                li.appendChild(cols);
            }

            var seen = item.occurrences > 1 ? ' · terdeteksi ' + item.occurrences + '×' : '';
            li.appendChild(el('div', 'sdb__meta', 'Tabel ' + item.table + seen));

            var actions = el('div', 'sdb__actions');
            if (item.status === 'pending' && item.evolution_mode === 'monitor') {
                li.appendChild(el('div', 'sdb__meta', 'Layout import tetap — kolom dicatat, tidak ditambahkan ke tabel.'));
                actions.appendChild(button('Tandai sudah ditinjau', 'sdb__btn', function (b) { act(b, url('rejectUrl', item.id), 'Ditandai sudah ditinjau.'); }));
            } else if (item.status === 'pending') {
                actions.appendChild(button('Terima semua', 'sdb__btn sdb__btn--primary', function (b) { act(b, url('acceptUrl', item.id), 'Kolom berhasil ditambahkan.'); }));
                actions.appendChild(button('Tolak', 'sdb__btn', function (b) { act(b, url('rejectUrl', item.id), 'Kolom baru ditolak.'); }));
            } else {
                actions.appendChild(button('Tandai dibaca', 'sdb__btn', function (b) { act(b, url('ackUrl', item.id)); }));
            }
            li.appendChild(actions);
            list.appendChild(li);
        });
    }

    function button(label, className, onClick) {
        var b = el('button', className, label);
        b.type = 'button';
        b.addEventListener('click', function () { onClick(b); });
        return b;
    }

    function act(btn, target, successText) {
        btn.disabled = true;
        post(target).then(function (data) {
            render(data.feed || {});
            document.dispatchEvent(new CustomEvent('schema-drift:feed-decided', { detail: data }));
            if (successText && window.Swal) { window.Swal.fire({ icon: 'success', title: successText, text: data.message, timer: 2600, showConfirmButton: false }); }
        }).catch(function (error) {
            btn.disabled = false;
            if (window.Swal) { window.Swal.fire({ icon: 'error', title: 'Gagal', text: error.message }); } else { window.alert(error.message); }
        });
    }

    function load() {
        fetch(root.dataset.feedUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (feed) { if (feed) { render(feed); } })
            .catch(function () { /* silent: notification feed is non-critical */ });
    }

    function setOpen(open) {
        menu.hidden = !open;
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) { load(); }
    }

    trigger.addEventListener('click', function (e) { e.stopPropagation(); setOpen(menu.hidden); });
    document.addEventListener('click', function (e) { if (!menu.hidden && !root.contains(e.target)) { setOpen(false); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { setOpen(false); trigger.focus(); } });
    document.addEventListener('schema-drift:analyzed', load);
    document.addEventListener('schema-drift:decided', load);

    load();
    window.setInterval(function () { if (!document.hidden) { load(); } }, 90000);
}());
</script>
