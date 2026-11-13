(function () {
    'use strict';

    if (window.MicroReportNominatives) return;

    let dialog, activeRequest, requestNumber = 0, activeContext, opener, page = 1, lastPage = 1;
    const reports = { prewash: 'Pipeline Prewash', boh: 'TL Pipeline Prewash BOH', mbm: 'TL Pipeline Prewash PIC MBM', ksbk: 'Realisasi KSBK' };
    const metrics = { all: 'Semua debitur', visited: 'Sudah dikunjungi', unvisited: 'Belum dikunjungi', realized: 'Sudah realisasi', ksbk: 'Real KSBK', non_ksbk: 'Real non KSBK', small: 'SBK ≤ Rp150 juta', large: 'SBK > Rp150 juta', unrealized: 'Belum realisasi' };
    const columns = [
        ['nama', 'Nama debitur'], ['cif', 'CIF'], ['norek', 'Nomor rekening'],
        ['cabang', 'Branch Office'], ['unit', 'Unit Kerja'], ['pn_pemasar', 'PN pemasar'],
        ['nama_pemasar', 'Nama pemasar'], ['produk', 'Produk'], ['plafond', 'Plafond (Rp)'],
        ['plafond_real', 'Plafond real (Rp)'], ['kunjungan', 'Kunjungan'], ['status', 'Status'],
    ];
    const count = value => new Intl.NumberFormat('id-ID').format(Number(value) || 0);
    const display = value => value === null || value === undefined || value === '' ? '—' : String(value);
    // Group decimal strings directly: account identifiers and monetary digits never pass through Number.
    function money(value) {
        const raw = display(value);
        const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(raw);
        if (!match) return raw;
        return match[1] + match[2].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (match[3] ? ',' + match[3] : '');
    }

    function createDialog() {
        if (dialog) return;
        const style = document.createElement('style');
        style.textContent = `
            .micro-nominatives{width:min(1480px,94vw);max-width:94vw;max-height:90dvh;padding:0;border:1px solid #b8cfe8;border-radius:16px;background:#fff;color:#173f66;box-shadow:0 24px 80px #10355b40;font:inherit}
            .micro-nominatives::backdrop{background:#122f4b80}
            .micro-nominatives__layout{display:flex;flex-direction:column;max-height:88dvh}
            .micro-nominatives__head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:20px 24px;border-bottom:1px solid #d9e5f0}
            .micro-nominatives__head h2{margin:0;color:#0754bd;font-size:1.1rem;line-height:1.4}
            .micro-nominatives__head p{margin:5px 0 0;font-size:.8rem;line-height:1.5;color:#526c84}
            .micro-nominatives button{font:inherit;cursor:pointer;touch-action:manipulation}
            .micro-nominatives button:focus-visible,.micro-nominatives__scroll:focus-visible{outline:3px solid #d97706;outline-offset:2px}
            .micro-nominatives__close{flex:none;width:44px;height:44px;border:1px solid #c7d9e9;border-radius:8px;background:#fff;color:#174c78;font-size:1.4rem!important}
            .micro-nominatives__status{margin:0;padding:14px 24px;font-size:.8rem;line-height:1.5;color:#40566c}
            .micro-nominatives__scroll{min-height:0;overflow:auto;overscroll-behavior:contain;margin:0 24px;border:1px solid #d9e5f0}
            .micro-nominatives__table{width:100%;min-width:1480px;border-collapse:collapse;font-size:.75rem;font-variant-numeric:tabular-nums}
            .micro-nominatives__table th{position:sticky;top:0;z-index:1;background:#0754bd;color:white;text-align:left;padding:12px 10px;white-space:nowrap}
            .micro-nominatives__table td{padding:11px 10px;border-bottom:1px solid #e1ebf3;vertical-align:top;max-width:240px;overflow-wrap:anywhere}
            .micro-nominatives__table tbody tr:nth-child(even){background:#f4f8fd}
            .micro-nominatives__table [data-money]{text-align:right;white-space:nowrap}
            .micro-nominatives__footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:16px 24px}
            .micro-nominatives__pages{display:flex;align-items:center;gap:10px;font-size:.8rem}
            .micro-nominatives__action{min-height:44px;padding:8px 14px;border:1px solid #aac8ed;border-radius:7px;background:#eef5ff;color:#0754bd;font-weight:700!important}
            .micro-nominatives__action:disabled{opacity:.45;cursor:default}
            .micro-nominatives [hidden]{display:none!important}
            @media(max-width:640px){.micro-nominatives{width:96vw;max-width:96vw}.micro-nominatives__head{padding:16px}.micro-nominatives__scroll{margin:0 12px}.micro-nominatives__status,.micro-nominatives__footer{padding:12px 16px}.micro-nominatives__head h2{font-size:1rem}}
            @media(prefers-reduced-motion:reduce){.micro-nominatives,.micro-nominatives *{scroll-behavior:auto!important;animation:none!important;transition:none!important}}
        `;
        document.head.appendChild(style);
        dialog = document.createElement('dialog');
        dialog.className = 'micro-nominatives';
        dialog.id = 'micro-report-nominatives';
        dialog.setAttribute('aria-labelledby', 'micro-nominatives-title');
        dialog.setAttribute('aria-describedby', 'micro-nominatives-context');
        dialog.innerHTML = `<div class="micro-nominatives__layout">
            <header class="micro-nominatives__head"><div><h2 id="micro-nominatives-title">Nominatif</h2><p id="micro-nominatives-context"></p></div><button type="button" class="micro-nominatives__close" data-close aria-label="Tutup nominatif">×</button></header>
            <p class="micro-nominatives__status" role="status" aria-live="polite" data-status></p>
            <div class="micro-nominatives__scroll" tabindex="0" role="region" aria-label="Daftar nominatif debitur" hidden><table class="micro-nominatives__table"><thead><tr></tr></thead><tbody></tbody></table></div>
            <footer class="micro-nominatives__footer"><button type="button" class="micro-nominatives__action" data-retry hidden>Coba lagi</button><span data-total></span><div class="micro-nominatives__pages" hidden><button type="button" class="micro-nominatives__action" data-previous>Sebelumnya</button><span data-page></span><button type="button" class="micro-nominatives__action" data-next>Berikutnya</button></div></footer>
        </div>`;
        document.body.appendChild(dialog);
        [['number', 'No.'], ...columns].forEach(([, label]) => {
            const th = document.createElement('th');
            th.scope = 'col';
            th.textContent = label;
            dialog.querySelector('thead tr').appendChild(th);
        });
        dialog.querySelector('[data-close]').addEventListener('click', close);
        dialog.querySelector('[data-retry]').addEventListener('click', () => load(page));
        dialog.querySelector('[data-previous]').addEventListener('click', () => load(page - 1));
        dialog.querySelector('[data-next]').addEventListener('click', () => load(page + 1));
        dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const rect = dialog.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close();
        });
        dialog.addEventListener('keydown', event => {
            if (event.key !== 'Tab') return;
            const focusable = [...dialog.querySelectorAll('button:not(:disabled), [tabindex="0"]')].filter(element => !element.closest('[hidden]'));
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
    }

    function close() {
        requestNumber++;
        if (activeRequest) activeRequest.abort();
        activeRequest = null;
        if (dialog && dialog.open) dialog.close();
        if (opener && opener.isConnected) opener.focus({ preventScroll: true });
        opener = null;
    }

    async function load(requestedPage) {
        if (!activeContext || requestedPage < 1) return;
        if (activeRequest) activeRequest.abort();
        const current = ++requestNumber;
        activeRequest = new AbortController();
        const controller = activeRequest;
        const deadline = window.setTimeout(() => controller.abort('timeout'), 45000);
        page = requestedPage;
        dialog.querySelector('[data-status]').textContent = 'Memuat nominatif…';
        dialog.querySelector('.micro-nominatives__scroll').hidden = true;
        dialog.querySelector('[data-retry]').hidden = true;
        dialog.querySelector('.micro-nominatives__pages').hidden = true;
        dialog.querySelector('[data-total]').textContent = '';
        dialog.setAttribute('aria-busy', 'true');
        try {
            const url = new URL(activeContext.url, location.href);
            ['report', 'level', 'branch', 'unit', 'mbm', 'metric', 'periode', 'cabang'].forEach(key => {
                if (activeContext[key] !== undefined && activeContext[key] !== '') url.searchParams.set(key, activeContext[key]);
            });
            url.searchParams.set('page', String(page));
            const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!response.ok) {
                const failure = await response.json().catch(() => ({}));
                const validation = failure.errors ? Object.values(failure.errors).flat()[0] : null;
                throw new Error(response.status === 401 ? 'Sesi login berakhir. Muat ulang halaman dan masuk kembali.' : (response.status === 403 ? 'Anda tidak memiliki akses ke nominatif ini.' : (validation || failure.message || 'Nominatif gagal dimuat. Silakan coba lagi.')));
            }
            const payload = await response.json().catch(() => { throw new Error('Respons nominatif tidak lengkap. Muat ulang halaman atau coba lagi.'); });
            if (current !== requestNumber || !dialog.open) return;
            if (!Array.isArray(payload.data)) throw new Error('Respons nominatif tidak lengkap. Silakan coba lagi.');
            const total = Number(payload.total) || 0;
            page = Math.max(1, Number(payload.page) || requestedPage);
            lastPage = Math.max(1, Number(payload.last_page) || 1);
            const perPage = Math.max(1, Number(payload.per_page) || 50);
            const body = dialog.querySelector('tbody');
            body.replaceChildren();
            payload.data.forEach((record, index) => {
                const row = document.createElement('tr');
                const number = document.createElement('td');
                number.textContent = count((page - 1) * perPage + index + 1);
                row.appendChild(number);
                columns.forEach(([key]) => {
                    const cell = document.createElement('td');
                    if (key === 'plafond' || key === 'plafond_real') {
                        cell.dataset.money = '';
                        cell.textContent = money(record[key]);
                    } else cell.textContent = display(record[key]);
                    row.appendChild(cell);
                });
                body.appendChild(row);
            });
            dialog.querySelector('[data-status]').textContent = payload.available === false
                ? (payload.message || 'Sumber nominatif belum tersedia.')
                : (total ? `${count(total)} debitur sesuai filter.` : 'Tidak ada debitur yang sesuai dengan filter ini.');
            dialog.querySelector('.micro-nominatives__scroll').hidden = payload.data.length === 0;
            dialog.querySelector('[data-total]').textContent = total ? `Menampilkan ${count((page - 1) * perPage + 1)}–${count((page - 1) * perPage + payload.data.length)} dari ${count(total)}` : '';
            dialog.querySelector('[data-page]').textContent = `Halaman ${count(page)} / ${count(lastPage)}`;
            dialog.querySelector('[data-previous]').disabled = page <= 1;
            dialog.querySelector('[data-next]').disabled = page >= lastPage;
            dialog.querySelector('.micro-nominatives__pages').hidden = lastPage <= 1;
            dialog.querySelector('.micro-nominatives__scroll').scrollTop = 0;
        } catch (error) {
            if (current !== requestNumber || (error.name === 'AbortError' && controller.signal.reason !== 'timeout')) return;
            dialog.querySelector('[data-status]').textContent = controller.signal.reason === 'timeout' ? 'Waktu tunggu nominatif habis. Silakan coba lagi.' : (error instanceof TypeError ? 'Koneksi terputus. Periksa koneksi lalu coba lagi.' : (error.message || 'Nominatif gagal dimuat. Silakan coba lagi.'));
            dialog.querySelector('[data-retry]').hidden = false;
        } finally {
            window.clearTimeout(deadline);
            if (current === requestNumber) dialog.removeAttribute('aria-busy');
        }
    }

    function open(row, metric, trigger) {
        createDialog();
        activeContext = { ...row.dataset, url: row.dataset.nominativeUrl, metric: metric || 'all' };
        opener = trigger;
        dialog.querySelector('#micro-nominatives-title').textContent = `Nominatif ${reports[activeContext.report] || 'Mikro'}`;
        dialog.querySelector('#micro-nominatives-context').textContent = [activeContext.groupLabel, metrics[activeContext.metric], activeContext.periode ? `Posisi ${activeContext.periode}` : ''].filter(Boolean).join(' · ');
        if (!dialog.open) dialog.showModal();
        dialog.querySelector('[data-close]').focus({ preventScroll: true });
        load(1);
    }

    function init() {
        document.addEventListener('click', event => {
            const button = event.target.closest('[data-micro-nominative-open]');
            if (!button) return;
            const row = button.closest('[data-micro-report-row]');
            if (row) open(row, button.dataset.metric, button);
        });
        document.addEventListener('dblclick', event => {
            if (event.target.closest('button,a,input,select')) return;
            const row = event.target.closest('[data-micro-report-row]');
            if (row) open(row, 'all', row.querySelector('[data-micro-nominative-open]'));
        });
        document.addEventListener('change', event => {
            if (event.target.matches('[name="cabang"], [name="periode"], #cabang, #periode, #periode-selector')) close();
        });
    }

    let initialized = false;
    window.MicroReportNominatives = { close, init() { if (!initialized) { initialized = true; init(); } } };
    window.MicroReportNominatives.init();
})();
