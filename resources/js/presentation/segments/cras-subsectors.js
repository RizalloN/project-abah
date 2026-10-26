import { h, count, money, percent } from './format.js';
import { table } from './charts.js';
import { BRANCH_LABELS, mix } from './territory-data.js';
import { choicePanel, bindChoicePanel } from './choice-panel.js';
import { subsectorReference } from './subsector-coverage.js';
import { sectorMetrics } from './sector-model.js';

export const subsectorPanel = () => `
    <section class="sg-cras" data-sg-cras aria-labelledby="sg-cras-title">
        <header class="sg-block__head"><h3 class="sg-block__title" id="sg-cras-title">Rincian subsektor CRAS</h3>
            <p class="sg-block__lede">Pilih sektor dan subsektor untuk melihat debitur, rekening, dan baki debet pada wilayah layanan unit.</p></header>
        <form class="sg-cras__filters" aria-label="Filter subsektor CRAS">
            ${choicePanel({ name: 'sektor', label: 'Sektor' })}
            ${choicePanel({ name: 'sub_sektor', label: 'Subsektor' })}
            <button type="submit" class="kn-button" data-cras-retry>Muat rincian</button>
        </form>
        <p class="sg-note" role="status" data-cras-status>Rincian akan dimuat saat bagian ini dibuka.</p>
        <div class="sg-grid sg-grid--1" data-cras-results></div>
        <p class="sg-note" data-cras-reference>Rasio subsektor memakai potensi kategori sektor induk pada desa kelolaan UKER, dari kolom F spreadsheet. Intra adalah debitur aktif dan ekstra adalah debitur PH. Tampilan dinormalisasi maksimum 100%; jumlah sumber dan rasio aslinya tetap tersimpan.</p>
    </section>`;

/** Existing CRAS service owns normalization, dependent options and server-side branch enforcement. */
export const bindSubsectors = (root, ctx, studio) => {
    const host = root.querySelector('[data-sg-cras]');
    if (!host || !ctx.crasUrl) return () => {};
    const form = host.querySelector('form');
    const sector = form.elements.sektor;
    const subsector = form.elements.sub_sektor;
    const status = host.querySelector('[data-cras-status]');
    const results = host.querySelector('[data-cras-results]');
    const button = form.querySelector('[data-cras-retry]');
    const selection = ctx.selection;
    const branchKey = ctx.scope.key === 'area6' ? (selection?.branchKey || 'all') : ctx.scope.key;
    const selectedUnits = new Set((selection?.unitCodes?.length ? selection.unitCodes : (selection?.unitCode ? [selection.unitCode] : [])).map(String));
    const restrictUnits = selection?.level === 'unit' || selection?.level === 'district';
    const areaRows = ctx.scope.key === 'area6' && !selection;
    const geographyLabel = selection && selection.level !== 'branch' ? `${BRANCH_LABELS[selection.branchKey]} / ${selection.label}` : selection?.label || ctx.scope.label;
    const saved = ctx.presentationState?.cras;
    const state = saved?.branchKey === branchKey ? saved : { branchKey, sektor: 'all', sub_sektor: 'all' };
    if (ctx.presentationState) ctx.presentationState.cras = state;
    let request = null;
    let disposed = false;
    let version = 0;
    const sectorChoices = bindChoicePanel(form.querySelector('[data-choice-field="sektor"]'), (value) => {
        state.sektor = value;
        state.sub_sektor = 'all';
        subsectorChoices.setOptions([], 'all');
        load();
    });
    const subsectorChoices = bindChoicePanel(form.querySelector('[data-choice-field="sub_sektor"]'), (value) => {
        state.sub_sektor = value;
        load();
    });
    const render = (payload) => {
        const units = (payload.sector_reference_units || []).filter((unit) => (
            (ctx.scope.key === 'area6' || unit.branch_key === ctx.scope.key)
            && (branchKey === 'all' || unit.branch_key === branchKey)
            && (!restrictUnits || selectedUnits.has(String(unit.code)))
        ));
        const selected = payload.filters?.selected || {};
        state.sektor = selected.sektor || 'all';
        state.sub_sektor = selected.sub_sektor || 'all';
        const title = state.sub_sektor !== 'all' ? state.sub_sektor : state.sektor !== 'all' ? state.sektor : 'Seluruh subsektor';
        sectorChoices.setOptions(payload.filters?.options?.sektor || [], state.sektor);
        subsectorChoices.setOptions(payload.filters?.options?.sub_sektor || [], state.sub_sektor);
        const totals = units.reduce((sum, unit) => {
            for (const key of Object.keys(sum)) sum[key] += Number(unit.values?.[key]) || 0;
            return sum;
        }, { jumlah_debitur: 0, intra: 0, extra: 0, jumlah_rekening: 0, baki_debet: 0, credit_extra: 0 });
        const reference = subsectorReference(payload, units, selection);
        const entities = areaRows
            ? Object.entries(BRANCH_LABELS).map(([key, label]) => {
                const values = units.filter((unit) => unit.branch_key === key).reduce((sum, unit) => {
                for (const metric of Object.keys(sum)) sum[metric] += Number(unit.values?.[metric]) || 0;
                return sum;
                }, { jumlah_debitur: 0, intra: 0, extra: 0, jumlah_rekening: 0, baki_debet: 0, credit_extra: 0 });
                const population = payload.sector_potential?.branches?.find((branch) => branch.key === key)?.values?.[reference.group?.key]?.potential ?? null;
                return { label, ...values, parentCoverage: sectorMetrics(population, values.jumlah_debitur) };
            })
            : units.map((unit) => ({ label: unit.name, ...unit.values, parentCoverage: unit.sector_parent_coverage || sectorMetrics(null, Number(unit.values?.jumlah_debitur) || 0) }));
        const coverageCell = (row) => {
            const coverage = row.parentCoverage;
            return `<span title="Rasio sumber ${h(percent(coverage?.penetration_raw, 2))}">${percent(coverage?.penetration, 2)}</span>${coverage?.normalization_applied ? '<small>Dinormalisasi maksimum 100%</small>' : ''}`;
        };
        results.innerHTML = `<section class="sg-tile sg-tile--wide"><header class="sg-tile__head"><h3>${h(title)}</h3><span>SSA CRAS, ${h(payload.updated_at)}</span></header>
            ${table({ caption: `${title} per ${areaRows ? 'cabang' : 'unit'} — ${geographyLabel}`, columns: [
                { label: areaRows ? 'Cabang' : 'Unit', cell: (row) => h(row.label) },
                { label: 'Intra · Aktif', align: 'end', cell: (row) => count(row.intra) },
                { label: 'Ekstra · PH', align: 'end', cell: (row) => count(row.extra) },
                { label: 'Total debitur BRI', align: 'end', cell: (row) => count(row.jumlah_debitur) },
                { label: 'Rekening', align: 'end', cell: (row) => count(row.jumlah_rekening) },
                { label: 'Kredit intra', align: 'end', cell: (row) => h(money(row.baki_debet)) },
                { label: 'Kredit ekstra · PH', align: 'end', cell: (row) => h(money(row.credit_extra)) },
                { label: 'Potensi sektor induk', align: 'end', cell: (row) => count(row.parentCoverage?.potential) },
                { label: 'Penetrasi terhadap sektor induk', align: 'end', cell: coverageCell },
            ], rows: entities, footer: { label: 'Total cakupan unik', ...totals, parentCoverage: reference.total } })}
            <div class="sg-cras__map-actions"><button type="button" class="kn-button" data-cras-map>Lihat peta subsektor</button></div>
        </section>`;
        host.querySelector('[data-cras-reference]').textContent = reference.group
            ? `Acuan: ${reference.group.label}. Pembagi adalah jumlah pelaku usaha kategori induk pada desa kelolaan UKER. Total cakupan memakai gabungan desa unik. Intra = AKTIF, ekstra = PH; Consumer/Konsumer dikeluarkan. Rasio dinormalisasi maksimum 100%; nilai sumber dipertahankan.${reference.potential_partial ? ` ${count(reference.missing_population_village_count)} desa belum memiliki data potensi; rasio memakai desa yang sudah tersedia.` : ''}`
            : 'Pilih sektor dan subsektor agar kategori sektor induk dapat dicocokkan. Pembagi memakai jumlah pelaku usaha kategori induk pada desa kelolaan UKER; potensi khusus subsektor belum tersedia.';
        const mapped = units.filter((unit) => unit.district_codes?.length);
        status.textContent = `SSA CRAS posisi ${payload.updated_at}, ${geographyLabel}: ${units.length} UKER pada hasil filter, ${mapped.length} memiliki polygon. ${count(totals.intra)} aktif + ${count(totals.extra)} PH = ${count(totals.jumlah_debitur)} debitur BRI. Data kinerja memakai periode tersendiri.`;
        const byDistrict = new Map();
        mapped.forEach((unit) => unit.district_codes.forEach((code) => byDistrict.set(code, (byDistrict.get(code) || 0) + (Number(unit.values?.jumlah_debitur) || 0) / unit.district_codes.length)));
        const maximum = Math.max(1, ...byDistrict.values());
        const labelsFor = (codes) => mapped.filter((unit) => !codes || unit.district_codes.some((code) => codes.has(code))).sort((a, b) => b.values.jumlah_debitur - a.values.jumlah_debitur).slice(0, 5).map((unit) => ({
            id: `cras-${unit.code}`, codes: new Set(unit.district_codes), title: unit.name, value: `${count(unit.values.jumlah_debitur)} debitur`,
        }));
        const register = () => {
            studio.register('sektoral', {
                version: `cras-${++version}`,
                allowedCodes: ctx.index.scopeCodes(ctx.scope.key),
                dark: 1,
                journey: [{ until: 1, focus: selection?.codes || ctx.index.scopeCodes(branchKey === 'all' ? ctx.scope.key : branchKey), labels: labelsFor(null),
                    heights: (code) => {
                        const value = byDistrict.get(code);
                        return value === undefined ? { lift: 0.02, color: 0x6b6e76 } : { lift: 0.08 + 0.92 * value / maximum, color: mix(0xa5bfeb, 0x0058d0, value / maximum), roughness: 0.24 };
                    }, caption: `<strong>${h(title)}</strong><span>${count(totals.jumlah_debitur)} debitur BRI · ${h(payload.updated_at)}</span>` }],
                describe: (code) => byDistrict.has(code) ? `<strong>${h(ctx.index.nameOf(code).district)}</strong><em>${h(title)}</em><span>Alokasi ${byDistrict.get(code).toLocaleString('id-ID', { maximumFractionDigits: 1 })} debitur BRI</span>` : '',
                labelsFor,
                captionFor: (codes) => `<strong>${h(title)}</strong><span>${mapped.filter((unit) => unit.district_codes.some((code) => codes.has(code))).map((unit) => `${h(unit.name)}: ${count(unit.values.jumlah_debitur)} debitur`).join('; ')}</span>`,
            });
            const figure = root.querySelector('#sg-sektoral .sg-figure__value');
            const label = root.querySelector('#sg-sektoral .sg-figure__label');
            if (figure) figure.textContent = count(totals.jumlah_debitur);
            if (label) label.textContent = `Debitur BRI ${title}, ${geographyLabel}, SSA CRAS ${payload.updated_at}`;
            root.querySelectorAll('[data-sector]').forEach((item) => item.setAttribute('aria-selected', 'false'));
        };
        const showMap = () => {
            register();
            root.querySelector('#sg-sektoral')?.dispatchEvent(new CustomEvent('polygonshowmap', { bubbles: true }));
        };
        results.querySelector('[data-cras-map]').addEventListener('click', showMap);
        ctx.rescan(results);
    };
    const load = async () => {
        request?.abort();
        const current = new AbortController();
        request = current;
        const timer = window.setTimeout(() => current.abort(), 45000);
        form.setAttribute('aria-busy', 'true');
        button.disabled = true;
        button.textContent = 'Memuat rincian';
        status.textContent = 'Memuat data subsektor SSA CRAS…';
        const url = new URL(ctx.crasUrl, window.location.origin);
        url.searchParams.set('wilayah', branchKey);
        const crasPeriod = ctx.wilayah?.sektoral?.period || ctx.period;
        if (crasPeriod) url.searchParams.set('periode', crasPeriod);
        url.searchParams.set('sektor', state.sektor);
        url.searchParams.set('sub_sektor', state.sub_sektor);
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: current.signal });
            if (!response.ok) throw new Error(String(response.status));
            const payload = await response.json();
            if (disposed || request !== current) return;
            if (!payload.ready) throw new Error(payload.message || 'Data SSA CRAS belum tersedia.');
            if (!payload.sector_reference_ready) throw new Error('Acuan desa dan periode sektoral belum tersedia.');
            render(payload);
        } catch (error) {
            if (disposed || request !== current) return;
            status.textContent = 'Rincian subsektor belum dapat dimuat. Tekan Muat rincian untuk mencoba kembali.';
        } finally {
            window.clearTimeout(timer);
            if (!disposed && request === current) {
                form.setAttribute('aria-busy', 'false');
                button.disabled = false;
                button.textContent = 'Muat rincian';
            }
        }
    };
    form.addEventListener('submit', (event) => { event.preventDefault(); load(); });
    const observer = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) { observer.disconnect(); load(); }
    }, { rootMargin: '400px' }) : null;
    if (observer) observer.observe(host);
    else load();
    return () => { disposed = true; observer?.disconnect(); request?.abort(); };
};
