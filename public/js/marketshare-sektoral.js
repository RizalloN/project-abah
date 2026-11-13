const numeric = value => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)) ? Number(value) : null;
const countFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const ratioFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const count = value => numeric(value) === null ? '—' : countFormat.format(Number(value));
const percent = value => numeric(value) === null ? '—' : `${ratioFormat.format(Number(value))}%`;

export function canSelectDistrict(payload) {
    return Boolean(payload.scope && payload.scope !== 'area6' && payload.regions?.[payload.scope]);
}

let openPicker = null;
function enhanceSelect(select, colors) {
    const wrapper = select.closest('.mss-custom-select');
    if (!wrapper) return;
    const label = document.querySelector(`label[for="${select.id}"]`);
    const kind = select.dataset.mssCustomSelect;
    const trigger = document.createElement('button'); trigger.type = 'button'; trigger.className = 'mss-picker-trigger'; trigger.id = `${select.id}-control`;
    const current = document.createElement('span'); current.className = 'mss-picker-value'; current.id = `${select.id}-value`;
    const arrow = document.createElement('span'); arrow.className = 'mss-picker-arrow'; arrow.setAttribute('aria-hidden', 'true');
    trigger.append(current, arrow);
    const panel = document.createElement('div'); panel.className = 'mss-picker-panel'; panel.hidden = true;
    const search = document.createElement('input'); search.type = 'search'; search.autocomplete = 'off'; search.spellcheck = false; search.placeholder = `Cari ${kind}…`; search.setAttribute('aria-label', `Cari ${kind}`);
    const list = document.createElement('div'); list.className = 'mss-picker-options'; list.id = `${select.id}-options`; list.setAttribute('role', 'listbox'); list.setAttribute('aria-label', `Pilihan ${kind}`);
    const empty = document.createElement('p'); empty.className = 'mss-picker-empty'; empty.textContent = `${kind === 'KC' ? 'KC' : kind.charAt(0).toUpperCase() + kind.slice(1)} tidak ditemukan.`; empty.setAttribute('role', 'status'); empty.hidden = true;
    panel.append(search, list, empty); wrapper.append(trigger, panel);
    trigger.setAttribute('aria-haspopup', 'listbox'); trigger.setAttribute('aria-expanded', 'false'); trigger.setAttribute('aria-controls', list.id);
    search.setAttribute('aria-controls', list.id);
    if (label) { label.id ||= `${select.id}-label`; label.htmlFor = trigger.id; trigger.setAttribute('aria-labelledby', `${label.id} ${current.id}`); }
    select.setAttribute('aria-hidden', 'true'); select.tabIndex = -1; wrapper.classList.add('is-enhanced');
    const buttons = [...select.options].map(option => {
        const button = document.createElement('button'); button.type = 'button'; button.className = 'mss-picker-option'; button.setAttribute('role', 'option'); button.dataset.value = option.value; button.disabled = option.disabled;
        const text = document.createElement('span'); text.textContent = option.textContent; button.append(text);
        const check = document.createElement('span'); check.className = 'mss-picker-check'; check.setAttribute('aria-hidden', 'true'); check.textContent = '✓'; button.append(check);
        if (kind === 'KC') { button.classList.add('is-branch'); button.style.setProperty('--option-accent', colors[option.value] || '#48718e'); }
        button.addEventListener('click', () => choose(button)); list.append(button); return button;
    });
    const visible = () => buttons.filter(button => !button.hidden && !button.disabled);
    function sync() {
        current.textContent = select.selectedOptions[0]?.textContent || `Pilih ${kind}`;
        trigger.disabled = select.disabled;
        buttons.forEach(button => { const selected = button.dataset.value === select.value; button.setAttribute('aria-selected', String(selected)); button.tabIndex = selected ? 0 : -1; });
    }
    function close(restoreFocus = false) {
        panel.hidden = true; wrapper.classList.remove('is-open'); trigger.setAttribute('aria-expanded', 'false');
        if (openPicker === api) openPicker = null;
        if (restoreFocus && !trigger.disabled) trigger.focus();
    }
    function position() {
        if (panel.hidden) return;
        const rect = trigger.getBoundingClientRect();
        const header = document.querySelector('.main-header')?.getBoundingClientRect().bottom || 0;
        const below = (window.visualViewport?.height || innerHeight) - rect.bottom - 12;
        const above = rect.top - Math.max(12, header + 8);
        const flip = below < 270 && above > below;
        wrapper.classList.toggle('opens-above', flip);
        list.style.maxHeight = `${Math.max(90, Math.min(245, (flip ? above : below) - 70))}px`;
    }
    function filter() {
        const query = search.value.trim().toLocaleLowerCase('id-ID');
        buttons.forEach(button => { button.hidden = !button.textContent.toLocaleLowerCase('id-ID').includes(query); });
        const shown = visible(); empty.hidden = shown.length > 0;
        shown.forEach((button, index) => { button.tabIndex = index === 0 ? 0 : -1; });
        position();
    }
    function open(keyboard = false) {
        if (trigger.disabled) return;
        openPicker?.close(); openPicker = api; search.value = ''; panel.hidden = false; wrapper.classList.add('is-open'); trigger.setAttribute('aria-expanded', 'true'); filter();
        const chosen = buttons.find(button => button.dataset.value === select.value && !button.hidden) || visible()[0];
        chosen?.scrollIntoView({ block: 'nearest' });
        if (keyboard || window.matchMedia('(pointer: fine)').matches) search.focus();
    }
    function choose(button) {
        const changed = select.value !== button.dataset.value; select.value = button.dataset.value; sync(); close(true);
        if (changed) select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    const api = { close, sync, trigger };
    trigger.addEventListener('click', () => panel.hidden ? open() : close());
    trigger.addEventListener('keydown', event => { if (['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); open(true); } });
    panel.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); close(true); return; }
        const shown = visible(), active = shown.indexOf(document.activeElement); if (!shown.length) return;
        let next = null;
        if (event.key === 'ArrowDown') next = shown[(active + 1) % shown.length];
        if (event.key === 'ArrowUp') next = shown[active < 0 ? shown.length - 1 : (active + shown.length - 1) % shown.length];
        if (event.key === 'Home' && document.activeElement !== search) next = shown[0];
        if (event.key === 'End' && document.activeElement !== search) next = shown.at(-1);
        if (next) { event.preventDefault(); next.focus(); next.scrollIntoView({ block: 'nearest' }); }
        if (event.key === 'Enter' && document.activeElement === search) { event.preventDefault(); choose(shown[0]); }
    });
    search.addEventListener('input', filter);
    wrapper.addEventListener('focusout', event => { if (!wrapper.contains(event.relatedTarget)) close(); });
    document.addEventListener('pointerdown', event => { if (!wrapper.contains(event.target)) close(); });
    window.addEventListener('resize', position); window.visualViewport?.addEventListener('resize', position);
    select.addEventListener('change', sync); select.addEventListener('mss:sync', sync);
    sync(); return api;
}

function bindBranchFilter(payload, pickers) {
    const select = document.getElementById('mss-cabang'); if (!select || select.disabled) return;
    const form = select.form, page = form.closest('.mss-page'), hint = form.querySelector('[data-mss-branch-hint]');
    const originalHint = hint.textContent; let pending = false;
    // The native GET form remains the authority for branch scoping and source data.
    select.onchange = null;
    select.addEventListener('change', () => {
        if (pending) return; pending = true;
        page.classList.add('is-filtering-branch'); form.setAttribute('aria-busy', 'true');
        hint.textContent = select.value === 'area6' ? 'Memuat ringkasan Area 6…' : 'Memuat pilihan kecamatan…';
        pickers.forEach(picker => { picker.close(); picker.trigger.disabled = true; });
        requestAnimationFrame(() => form.requestSubmit());
    });
    const restoreBranch = () => {
        pending = false; select.value = payload.scope; page.classList.remove('is-filtering-branch'); form.removeAttribute('aria-busy'); hint.textContent = originalHint;
        pickers.forEach(picker => picker.sync());
    };
    // History traversal can restore native form values after the new document initializes.
    window.addEventListener('pageshow', () => requestAnimationFrame(restoreBranch));
    window.addEventListener('popstate', restoreBranch);
}

function chooseBranch(key) {
    const select = document.getElementById('mss-cabang');
    if (!select || select.disabled || ![...select.options].some(option => option.value === key)) return;
    select.value = key; select.dispatchEvent(new Event('change', { bubbles: true }));
}

// Sum the supplied geographic allocation; an unavailable field remains unavailable.
export function aggregateDistricts(records, key = 'total') {
    const sum = field => {
        const values = records.map(row => numeric(row.values?.[key]?.[field]));
        return values.length && values.every(value => value !== null) ? values.reduce((a, b) => a + b, 0) : null;
    };
    const result = Object.fromEntries(['potential', 'existing', 'existing_source', 'intra', 'extra'].map(field => [field, sum(field)]));
    result.penetration = result.potential > 0 && result.existing !== null ? Math.min(100, Math.max(0, result.existing / result.potential * 100)) : null;
    result.penetration_raw = result.potential > 0 && result.existing_source !== null ? result.existing_source / result.potential * 100 : null;
    return result;
}

export function districtIndex(payload) {
    const index = new Map();
    for (const row of payload.districts || []) {
        const code = String(row.code || '');
        if (!code) continue;
        if (!index.has(code)) index.set(code, { code, name: row.name, regency: row.regency, records: [], estimated: false });
        const entry = index.get(code);
        entry.records.push(row);
        entry.estimated ||= Boolean(row.allocation_estimate);
    }
    return index;
}

export function visibleFeatures(geo, payload) {
    const codes = new Set((payload.districts || []).map(row => String(row.code)));
    const regencies = payload.regions?.[payload.scope]?.regency_codes || [];
    return (geo.features || []).filter(feature => feature.geometry && (payload.scope === 'area6'
        || codes.has(String(feature.properties?.KDCPUM)) || regencies.includes(String(feature.properties?.KDPKAB))));
}

export function penetrationColor(value) {
    const number = numeric(value);
    if (number === null) return '#d4dde6';
    return ['#d9edf9', '#9ccbea', '#5aa6d8', '#247db8', '#08558d'][Math.min(4, Math.max(0, Math.floor(number / 20)))];
}

const ringsOf = geometry => geometry.type === 'Polygon' ? geometry.coordinates : geometry.type === 'MultiPolygon' ? geometry.coordinates.flat() : [];
export function mapGeometry(features) {
    const valid = features.filter(feature => ['Polygon', 'MultiPolygon'].includes(feature.geometry?.type));
    const points = valid.flatMap(feature => ringsOf(feature.geometry).flat()).filter(point => point?.length >= 2 && point.slice(0, 2).every(Number.isFinite));
    if (!points.length) throw new Error('Batas kecamatan tidak tersedia.');
    const cosine = Math.cos(points.reduce((sum, point) => sum + point[1], 0) / points.length * Math.PI / 180);
    let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
    for (const [lon, lat] of points) { minX = Math.min(minX, lon * cosine); maxX = Math.max(maxX, lon * cosine); minY = Math.min(minY, -lat); maxY = Math.max(maxY, -lat); }
    const scale = 1000 / Math.max(maxX - minX, 0.00001);
    const project = point => [(point[0] * cosine - minX) * scale + 36, (-point[1] - minY) * scale + 36];
    const entries = valid.map(feature => {
        const rings = ringsOf(feature.geometry).map(ring => ring.map(project));
        const all = rings.flat();
        const bounds = [Math.min(...all.map(p => p[0])), Math.min(...all.map(p => p[1])), Math.max(...all.map(p => p[0])), Math.max(...all.map(p => p[1]))];
        // Use the largest ring's area centroid, retaining holes in the rendered path.
        let largest = null, largestArea = 0;
        for (const ring of rings) {
            let area = 0, x = 0, y = 0;
            for (let i = 0; i < ring.length; i++) { const a = ring[i], b = ring[(i + 1) % ring.length], cross = a[0] * b[1] - b[0] * a[1]; area += cross; x += (a[0] + b[0]) * cross; y += (a[1] + b[1]) * cross; }
            if (Math.abs(area) > largestArea) { largestArea = Math.abs(area); largest = [x / (3 * area), y / (3 * area)]; }
        }
        return { code: String(feature.properties.KDCPUM), name: feature.properties.WADMKC, regency: feature.properties.WADMKK, regencyCode: String(feature.properties.KDPKAB), bounds,
            center: largest || [(bounds[0] + bounds[2]) / 2, (bounds[1] + bounds[3]) / 2],
            path: rings.map(ring => `M${ring.map(p => `${p[0].toFixed(2)},${p[1].toFixed(2)}`).join('L')}Z`).join('') };
    });
    return { entries, box: [0, 0, 1072, (maxY - minY) * scale + 72] };
}

function initAtlas(root, payload) {
    if (!payload.ready) return;
    const districtReady = canSelectDistrict(payload);
    const index = districtIndex(payload);
    const groups = payload.groups || [];
    const selectable = groups.filter(group => group.key !== 'unclassified');
    const sectorSelect = root.querySelector('#mss-atlas-sector');
    const districtSelect = root.querySelector('#mss-atlas-district');
    const svg = root.querySelector('#mss-territory-map');
    const stage = root.querySelector('#mss-map-stage');
    const tooltip = root.querySelector('#mss-map-tooltip');
    const legend = root.querySelector('[data-mss-legend]');
    const paths = new Map();
    const state = { district: '', sector: 'total', mode: 'branch' };
    let geometry = null, zoom = 1, center = null, drag = null, preventPickUntil = 0;
    const nameOf = key => groups.find(group => group.key === key)?.label || 'Seluruh sektor usaha';
    const physicalBranch = entry => Object.entries(payload.regions || {}).find(([, region]) => region.regency_codes.includes(entry.regencyCode))?.[0];
    const figuresFor = (key, code = state.district) => code && index.has(code) ? aggregateDistricts(index.get(code).records, key) : payload.totals?.[key] || {};
    const labelOf = () => state.district ? index.get(state.district)?.name || payload.scopeLabel : payload.scopeLabel;
    const estimated = () => Boolean(state.district && index.get(state.district)?.estimated);
    const display = (value, field, approximate = estimated()) => field === 'penetration' ? percent(value) : `${approximate && ['intra', 'extra', 'existing'].includes(field) && numeric(value) !== null ? '≈ ' : ''}${count(value)}`;
    const text = (selector, value) => { const element = root.querySelector(selector); if (element) element.textContent = value; };
    const make = (tag, className, value) => { const element = document.createElement(tag); if (className) element.className = className; if (value !== undefined) element.textContent = value; return element; };

    function readUrl() {
        const params = new URL(window.location.href).searchParams;
        state.district = districtReady && index.has(params.get('kecamatan')) ? params.get('kecamatan') : '';
        state.sector = selectable.some(group => group.key === params.get('sektor')) ? params.get('sektor') : 'total';
        state.mode = params.get('warna') === 'penetration' ? 'penetration' : 'branch';
    }
    function writeUrl(replace = false) {
        const url = new URL(window.location.href);
        for (const [key, value, fallback] of [['kecamatan', state.district, ''], ['sektor', state.sector, 'total'], ['warna', state.mode, 'branch']]) {
            if (value === fallback) url.searchParams.delete(key); else url.searchParams.set(key, value);
        }
        if (url.href !== window.location.href) window.history[replace ? 'replaceState' : 'pushState']({}, '', url);
    }
    function select(district = state.district, sector = state.sector) {
        state.district = districtReady && index.has(district) ? district : '';
        state.sector = selectable.some(group => group.key === sector) ? sector : 'total';
        writeUrl(); render();
    }
    function renderLegend() {
        legend.replaceChildren();
        const entries = state.mode === 'penetration' ? [['#d9edf9', '<20%'], ['#9ccbea', '20–<40%'], ['#5aa6d8', '40–<60%'], ['#247db8', '60–<80%'], ['#08558d', '≥80%'], ['#d4dde6', 'Belum tersedia']]
            : Object.entries(payload.regions || {}).filter(([key]) => !geometry || geometry.entries.some(entry => physicalBranch(entry) === key)).map(([key, region]) => [payload.colors[key], region.label]);
        for (const [color, label] of entries) { const item = make('span', '', label); const dot = make('i', 'mss-dot'); dot.style.backgroundColor = color; dot.setAttribute('aria-hidden', 'true'); item.prepend(dot); legend.append(item); }
    }
    function renderMap() {
        svg.dataset.mode = state.mode;
        for (const [code, { element, entry }] of paths) {
            const metrics = index.has(code) ? aggregateDistricts(index.get(code).records, state.sector) : {};
            element.style.fill = state.mode === 'penetration' ? penetrationColor(metrics.penetration) : index.has(code) ? payload.colors[physicalBranch(entry)] || '#d4dde6' : '#d4dde6';
            element.classList.toggle('is-selected', code === state.district);
            element.classList.toggle('is-muted', Boolean(state.mode === 'branch' && state.district && code !== state.district));
            if (index.has(code)) element.setAttribute('aria-pressed', String(code === state.district));
            const nextStep = districtReady ? '' : ` Pilih KC ${payload.regions?.[physicalBranch(entry)]?.label || ''} terlebih dahulu untuk membuka filter kecamatan.`;
            element.setAttribute('aria-label', `${entry.name}, ${entry.regency}. ${nameOf(state.sector)}. Potensi ${count(metrics.potential)}, penetrasi ${percent(metrics.penetration)}${index.get(code)?.estimated ? ', estimasi alokasi debitur' : ''}.${nextStep}`);
        }
        root.querySelectorAll('[data-mss-mode]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.mssMode === state.mode)));
        renderLegend();
    }
    function render() {
        sectorSelect.value = state.sector; districtSelect.value = state.district;
        sectorSelect.dispatchEvent(new Event('mss:sync')); districtSelect.dispatchEvent(new Event('mss:sync'));
        const district = index.get(state.district), metrics = figuresFor(state.sector);
        text('#mss-selection-title', labelOf());
        text('[data-mss-selection-level]', district ? 'Kecamatan terpilih' : 'Wilayah analisis');
        text('[data-mss-selection-context]', district ? district.regency : 'Seluruh kecamatan pada wilayah terpilih');
        root.querySelector('[data-mss-estimate]').hidden = !estimated();
        text('[data-mss-sector-label]', nameOf(state.sector));
        text('[data-mss-ledger-context]', `${district ? `${district.name}, ${district.regency}` : payload.scopeLabel}${estimated() ? ' · Estimasi alokasi debitur' : ' · Seluruh kecamatan'}`);
        root.querySelectorAll('[data-mss-value]').forEach(element => { const field = element.dataset.mssValue; element.textContent = display(metrics[field], field); });
        const fraction = field => metrics.potential > 0 && numeric(metrics[field]) !== null ? Math.min(100, Math.max(0, metrics[field] / metrics.potential * 100)) : 0;
        const intra = fraction('intra'), extra = Math.min(100 - intra, fraction('extra'));
        root.querySelector('[data-mss-progress="intra"]').style.width = `${intra}%`;
        root.querySelector('[data-mss-progress="extra"]').style.width = `${extra}%`;
        text('[data-mss-selection-note]', estimated() ? 'Potensi berasal dari desa kelolaan. Debitur kecamatan adalah estimasi alokasi UKER menurut porsi potensi; angka jumlah dibulatkan.' : 'Jumlah debitur mengikuti penjumlahan SSA CRAS pada UKER pemilik desa. Aktif + PH termasuk OTHER; bukan CIF unik lintas kelompok.');
        root.querySelectorAll('[data-mss-group]').forEach(row => {
            const key = row.dataset.mssGroup, value = figuresFor(key);
            row.classList.toggle('is-active', key === state.sector);
            row.querySelector('[data-mss-sector]')?.setAttribute('aria-pressed', String(key === state.sector));
            row.querySelectorAll('[data-mss-field]').forEach(element => {
                const field = element.dataset.mssField;
                element.textContent = key === 'unclassified' && ['potential', 'penetration'].includes(field) ? '—' : display(value[field], field);
            });
            const raw = `Rasio sesuai kode SSA: ${percent(value.penetration_raw)}`;
            row.querySelector('[data-mss-field="penetration"]').title = raw;
            row.querySelector('[data-mss-raw-ratio]').textContent = raw;
            row.querySelector('[data-mss-row-progress]').style.width = `${key !== 'unclassified' && numeric(value.penetration) !== null ? Math.min(100, Math.max(0, value.penetration)) : 0}%`;
        });
        const total = figuresFor('total');
        root.querySelectorAll('[data-mss-total]').forEach(element => { const field = element.dataset.mssTotal; element.textContent = display(total[field], field); });
        const top = root.querySelector('[data-mss-top-sectors]'); top.replaceChildren();
        const ranked = selectable.filter(group => group.key !== 'total').map(group => ({ ...group, value: numeric(figuresFor(group.key).potential) })).filter(group => group.value !== null).sort((a, b) => b.value - a.value).slice(0, 3);
        for (const group of ranked) { const button = make('button', 'mss-top-sector'); button.type = 'button'; button.dataset.mssSector = group.key; button.setAttribute('aria-pressed', String(group.key === state.sector)); button.append(make('span', '', group.label), make('strong', '', count(group.value))); top.append(button); }
        if (!ranked.length) top.append(make('p', '', 'Potensi sektor belum tersedia.'));
        renderMap();
        text('[data-mss-announcement]', `${labelOf()}. ${nameOf(state.sector)}. Potensi ${count(metrics.potential)}. Total debitur BRI ${display(metrics.existing, 'existing')}. Penetrasi ${percent(metrics.penetration)}.`);
    }

    function applyView() {
        if (!geometry) return;
        const [, , width, height] = geometry.box, w = width / zoom, h = height / zoom;
        center[0] = Math.min(width - w / 2, Math.max(w / 2, center[0])); center[1] = Math.min(height - h / 2, Math.max(h / 2, center[1]));
        svg.setAttribute('viewBox', `${center[0] - w / 2} ${center[1] - h / 2} ${w} ${h}`);
        stage.classList.toggle('is-zoomed', zoom > 1);
        root.querySelector('[data-mss-zoom="out"]').disabled = zoom <= 1;
        root.querySelector('[data-mss-zoom="in"]').disabled = zoom >= 6;
    }
    function changeZoom(direction) {
        if (!geometry) return;
        if (direction === 'fit') { zoom = 1; center = [geometry.box[2] / 2, geometry.box[3] / 2]; }
        else zoom = Math.min(6, Math.max(1, zoom * (direction === 'in' ? 1.5 : 1 / 1.5)));
        tooltip.hidden = true; applyView();
    }
    function showTooltip(code, event) {
        if (drag?.moved) return;
        const item = index.get(code), entry = paths.get(code)?.entry; if (!entry) return;
        const metrics = item ? aggregateDistricts(item.records, state.sector) : {};
        tooltip.replaceChildren(make('strong', '', entry.name), make('small', '', entry.regency), make('span', '', `Potensi ${count(metrics.potential)}`), make('span', '', districtReady ? `${item?.estimated ? 'Estimasi penetrasi' : 'Penetrasi'} ${percent(metrics.penetration)}` : `Pilih KC ${payload.regions?.[physicalBranch(entry)]?.label || ''} untuk melanjutkan`));
        tooltip.hidden = false;
        const box = stage.getBoundingClientRect(), target = paths.get(code).element.getBoundingClientRect();
        const x = event?.clientX ?? target.x + target.width / 2, y = event?.clientY ?? target.y + target.height / 2;
        tooltip.style.left = `${Math.max(12, Math.min(box.width - tooltip.offsetWidth - 12, x - box.x + 14))}px`;
        tooltip.style.top = `${Math.max(12, Math.min(box.height - tooltip.offsetHeight - 12, y - box.y + 14))}px`;
    }
    async function loadMap() {
        const status = root.querySelector('[data-mss-map-state]'), retry = root.querySelector('[data-mss-retry]');
        status.hidden = false; status.removeAttribute('role'); status.setAttribute('role', 'status'); retry.hidden = true;
        status.querySelector('strong').textContent = 'Memuat polygon wilayah…'; status.querySelector('small').textContent = 'Menyiapkan batas kecamatan Area 6';
        status.classList.remove('is-error'); stage.setAttribute('aria-busy', 'true');
        const abort = new AbortController(), timer = setTimeout(() => abort.abort(), 15000);
        try {
            const response = await fetch(payload.geoUrl, { signal: abort.signal, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Geometri belum dapat diunduh.');
            geometry = mapGeometry(visibleFeatures(await response.json(), payload));
            svg.replaceChildren(); paths.clear();
            const ns = 'http://www.w3.org/2000/svg';
            for (const entry of geometry.entries) {
                const path = document.createElementNS(ns, 'path'); path.setAttribute('d', entry.path); path.setAttribute('fill-rule', 'evenodd'); path.setAttribute('class', 'mss-district-polygon');
                path.dataset.code = entry.code;
                const available = index.has(entry.code);
                path.setAttribute('role', available ? 'button' : 'img'); path.setAttribute('tabindex', available ? '0' : '-1');
                path.classList.toggle('is-unavailable', !available);
                if (!available) path.setAttribute('aria-disabled', 'true');
                const pick = () => {
                    if (!index.has(entry.code)) return;
                    if (!districtReady) { chooseBranch(physicalBranch(entry)); return; }
                    select(state.district === entry.code ? '' : entry.code);
                };
                path.addEventListener('click', () => { if (Date.now() >= preventPickUntil) pick(); });
                path.addEventListener('keydown', event => {
                    if (['Enter', ' '].includes(event.key)) { event.preventDefault(); pick(); }
                    if (['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp'].includes(event.key)) { event.preventDefault(); const list = [...paths.values()].filter(item => index.has(item.entry.code)), current = list.findIndex(item => item.element === path); list[(current + (['ArrowRight', 'ArrowDown'].includes(event.key) ? 1 : list.length - 1)) % list.length].element.focus(); }
                });
                path.addEventListener('pointermove', event => showTooltip(entry.code, event)); path.addEventListener('pointerleave', () => { tooltip.hidden = true; });
                path.addEventListener('focus', () => {
                    if (zoom > 1 && path.matches(':focus-visible')) { center = [...entry.center]; applyView(); }
                    showTooltip(entry.code);
                });
                path.addEventListener('blur', () => { tooltip.hidden = true; });
                paths.set(entry.code, { element: path, entry }); svg.append(path);
            }
            // Four branch names give orientation without crowding 76 small polygons.
            for (const [key, region] of Object.entries(payload.regions || {})) {
                const entries = geometry.entries.filter(entry => physicalBranch(entry) === key); if (!entries.length) continue;
                const x = entries.reduce((sum, entry) => sum + entry.center[0], 0) / entries.length, y = entries.reduce((sum, entry) => sum + entry.center[1], 0) / entries.length;
                const label = document.createElementNS(ns, 'text'); label.textContent = region.label; label.setAttribute('x', x); label.setAttribute('y', y); label.setAttribute('class', 'mss-map-label'); label.setAttribute('aria-hidden', 'true'); svg.append(label);
            }
            svg.hidden = false; svg.removeAttribute('hidden'); status.hidden = true; text('[data-mss-polygon-count]', count(geometry.entries.length));
            root.querySelector('[data-mss-zoom="fit"]').disabled = false; changeZoom('fit'); renderMap();
        } catch (error) {
            svg.setAttribute('hidden', ''); status.classList.add('is-error'); status.setAttribute('role', 'alert'); status.querySelector('strong').textContent = 'Peta wilayah belum dapat dimuat';
            status.querySelector('small').textContent = 'Coba muat ulang polygon. Filter kecamatan dan tabel tetap dapat digunakan.'; retry.hidden = false;
            root.querySelectorAll('[data-mss-zoom]').forEach(button => { button.disabled = true; });
        } finally { clearTimeout(timer); stage.setAttribute('aria-busy', 'false'); }
    }

    sectorSelect.addEventListener('change', () => select(state.district, sectorSelect.value));
    districtSelect.addEventListener('change', () => select(districtSelect.value));
    root.addEventListener('click', event => {
        const sector = event.target.closest('[data-mss-sector]'); if (sector) select(state.district, state.sector === sector.dataset.mssSector ? 'total' : sector.dataset.mssSector);
        const mode = event.target.closest('[data-mss-mode]'); if (mode) { state.mode = mode.dataset.mssMode; writeUrl(); renderMap(); }
        const control = event.target.closest('[data-mss-zoom]'); if (control) changeZoom(control.dataset.mssZoom);
    });
    root.querySelector('[data-mss-reset]').addEventListener('click', () => { state.mode = 'branch'; root.querySelector('#mss-atlas-search').value = ''; filterRows(); select('', 'total'); changeZoom('fit'); });
    root.querySelector('[data-mss-retry]').addEventListener('click', loadMap);
    function filterRows() {
        const query = root.querySelector('#mss-atlas-search').value.trim().toLocaleLowerCase('id-ID'); let visible = 0;
        root.querySelectorAll('[data-mss-search]').forEach(row => { row.hidden = !row.dataset.mssSearch.includes(query); if (!row.hidden) visible++; });
        root.querySelector('[data-mss-empty]').hidden = visible > 0;
    }
    root.querySelector('#mss-atlas-search').addEventListener('input', filterRows);
    window.addEventListener('popstate', () => { readUrl(); render(); });
    window.addEventListener('pageshow', () => requestAnimationFrame(() => { readUrl(); render(); }));
    svg.addEventListener('pointerdown', event => { if (!geometry || zoom <= 1 || event.button !== 0 || drag) return; drag = { id: event.pointerId, x: event.clientX, y: event.clientY, center: [...center], moved: false }; });
    svg.addEventListener('pointermove', event => {
        if (!drag || !geometry || drag.id !== event.pointerId) return; const dx = event.clientX - drag.x, dy = event.clientY - drag.y;
        if (!drag.moved && Math.hypot(dx, dy) > 5) { drag.moved = true; svg.setPointerCapture(event.pointerId); }
        if (!drag.moved) return; tooltip.hidden = true;
        const box = svg.getBoundingClientRect(), scale = Math.min(box.width / (geometry.box[2] / zoom), box.height / (geometry.box[3] / zoom));
        center = [drag.center[0] - dx / scale, drag.center[1] - dy / scale]; applyView();
    });
    const stopDrag = () => { if (drag?.moved) preventPickUntil = Date.now() + 150; drag = null; };
    svg.addEventListener('pointerup', stopDrag); svg.addEventListener('pointercancel', stopDrag);
    window.addEventListener('pointerup', stopDrag);
    svg.addEventListener('wheel', event => { if (!event.ctrlKey || !geometry) return; event.preventDefault(); changeZoom(event.deltaY < 0 ? 'in' : 'out'); }, { passive: false });
    readUrl(); writeUrl(true); render(); loadMap();
}

if (typeof document !== 'undefined') {
    const root = document.getElementById('mss-atlas'), data = document.getElementById('mss-atlas-data');
    if (root && data) {
        const payload = JSON.parse(data.textContent);
        const pickers = [...document.querySelectorAll('[data-mss-custom-select]')].map(select => enhanceSelect(select, payload.colors || {})).filter(Boolean);
        bindBranchFilter(payload, pickers);
        initAtlas(root, payload);
    }
}
