import { h, count, percent } from './format.js';
import { table, rods } from './charts.js';
import { chapter, block, tile, empty } from './layout.js';
import { skyline, ledger, disclosure, bindAtlas } from './atlas.js';
import { sectorView, aggregateSectorValues } from './sector-model.js';
import { penetrationColor, mix } from './territory-data.js';
import { subsectorPanel, bindSubsectors } from './cras-subsectors.js';

const COLORS = ['#2c6fe0', '#14b8a6', '#f59e0b', '#8b5cf6', '#ef4444', '#0ea5e9', '#22c55e', '#e11d48', '#a16207', '#64748b'];

/** Source population by KBLI, compared only at the grain actually supported by SSA. */
export const renderSector = (ctx) => {
    const view = sectorView(ctx);
    const { source, label, groups } = view;
    if (!source.ready || !groups.length) return { html: chapter({ key: 'sektoral', group: 'Wilayah', title: 'Penetrasi sektoral.',
        lede: source.message || 'Sumber potensi sektoral atau SSA CRAS September 2026 belum tersedia.', territory: false, span: 120,
        sheet: empty('Angka REKAP lama tidak digunakan sebagai pengganti sumber sektoral baru.') }) };
    ctx.presentationState ||= {};
    const state = ctx.presentationState.sektoral ||= {};
    if (!['total', ...groups.map((group) => group.key)].includes(state.sector)) state.sector = 'total';
    const sectors = groups.map((group, index) => ({ ...group, color: COLORS[index % COLORS.length], ...view.totals(group.key) }))
        .sort((a, b) => (b.potential ?? b.existing ?? 0) - (a.potential ?? a.existing ?? 0));
    const noPotential = view.totals().potential === null;
    const selected = (key = state.sector) => key === 'total'
        ? { key: 'total', label: 'Seluruh sektor usaha', color: '#4c9dff', ...view.totals() }
        : sectors.find((sector) => sector.key === key);
    const metric = (figures) => noPotential ? `${count(figures.existing)} debitur` : percent(figures.penetration, 1);
    const figureLabel = (sector) => noPotential
        ? `Debitur BRI ${sector.label}, ${label}. SSA CRAS ${source.period_label}`
        : `${view.districtLevel ? 'Estimasi penetrasi' : 'Penetrasi debitur aktif + PH'} ${sector.label}, ${label}. Normalisasi maksimum 100%. SSA CRAS ${source.period_label}`;
    const notes = `${source.note || ''} ${source.district_note || ''}`;
    const entityTable = (key) => table({ caption: `${selected(key).label} per ${view.headLabel.toLowerCase()}, ${label}`, columns: [
        { label: view.headLabel, cell: (row) => h(row.label) },
        { label: 'Potensi desa kelolaan', align: 'end', cell: (row) => `${count(row.potential)}${row.potential_partial ? '<small>Data desa belum lengkap</small>' : ''}` },
        { label: 'Intra · Aktif', align: 'end', cell: (row) => count(row.intra) },
        { label: 'Ekstra · PH', align: 'end', cell: (row) => count(row.extra) },
        { label: 'Total debitur BRI', align: 'end', cell: (row) => count(row.existing) },
        { label: 'Penetrasi normalisasi', align: 'end', cell: (row) => `<span title="Rasio sumber ${h(percent(row.penetration_raw, 2))}">${percent(row.penetration, 1)}</span>${row.normalization_applied ? '<small>Dibatasi pada 100%</small>' : ''}` },
    ], rows: view.rowValues(key) });
    const ledgerHtml = (key) => {
        if (noPotential) return table({ caption: `Debitur per kelompok usaha, ${label}`, columns: [
            { label: 'Kelompok usaha', cell: (row) => h(row.label) },
            { label: 'Debitur BRI', align: 'end', cell: (row) => count(row.existing) },
            { label: 'Potensi unit', align: 'end', cell: () => 'Belum tersedia' },
        ], rows: sectors });
        return ledger({ rows: view.rows.map((row) => ({ label: row.label || row.name,
            cells: Object.fromEntries(sectors.map((sector) => [sector.key, aggregateSectorValues([row], sector.key)])) })),
            sectors, selected: key, bounded: true, caption: `Penetrasi normalisasi menurut kelompok usaha dan ${view.headLabel.toLowerCase()}, ${label}`, headLabel: view.headLabel });
    };
    const originalTable = () => table({ caption: `17 kategori KBLI asli, ${label}`, columns: [
        { label: 'Kategori KBLI', cell: (row) => `${h(row.key)}. ${h(row.label)}` },
        { label: 'Potensi pelaku usaha', align: 'end', cell: (row) => count(row.potential) },
    ], rows: (source.source_sectors || []).map((sector) => {
        const values = view.selectedRows.map((row) => row.source_values?.[sector.key]);
        return { ...sector, potential: !values.length || values.some((value) => value === null || value === undefined || !Number.isFinite(Number(value)))
            ? null : values.reduce((sum, value) => sum + Number(value), 0) };
    }) });
    const period = `Debitur BRI: SSA CRAS ${source.period_label}. Potensi: ${source.source?.label || 'Spreadsheet pelaku usaha'}, kolom F. Kelolaan desa: ${source.ownership_source?.label || 'Mapping Wilayah Desa Area 6'}.`;
    const coverageLabel = view.branch ? view.branches[0]?.label || ctx.scope.label : ctx.scope.label;
    const unowned = (source.unmapped_units || []).filter((unit) => !view.branch || unit.branch === view.branch);
    const unownedTotals = aggregateSectorValues(unowned);
    const partialUnits = (source.units || []).filter((unit) => (!view.branch || unit.branch === view.branch) && unit.potential_partial);
    const coverageNote = `${coverageLabel}: total mengikuti UKER yang memiliki desa kelolaan. ${unowned.length ? `${count(unowned.length)} UKER belum memiliki acuan desa (${count(unownedTotals.intra)} aktif dan ${count(unownedTotals.extra)} PH), disajikan terpisah dari penetrasi. ` : ''}${partialUnits.map((unit) => `${unit.name}: ${count(unit.matched_village_count)} dari ${count(unit.owned_village_count)} desa memiliki data potensi${unit.missing_population_villages?.length ? `; belum tersedia: ${unit.missing_population_villages.map((village) => `${village.name}, ${village.district}`).join(', ')}` : ''}. `).join('')}Peta debitur merupakan estimasi alokasi; tabel memakai jumlah UKER utuh.`;
    const unownedTable = unowned.length ? table({ caption: 'Debitur UKER tanpa acuan desa — di luar rasio penetrasi', columns: [
        { label: 'UKER', cell: (row) => h(row.name) },
        { label: 'Intra · Aktif', align: 'end', cell: (row) => count(row.values?.total?.intra) },
        { label: 'Ekstra · PH', align: 'end', cell: (row) => count(row.values?.total?.extra) },
        { label: 'Total debitur BRI', align: 'end', cell: (row) => count(row.values?.total?.existing) },
    ], rows: unowned }) : '';
    const unclassified = view.totals('unclassified');
    const unclassifiedNote = `OTHER / tanpa sektor: ${count(unclassified.intra)} debitur aktif + ${count(unclassified.extra)} debitur PH = ${count(unclassified.existing)}. Tetap termasuk total debitur BRI; rasio khusus OTHER tidak dihitung karena tidak memiliki kategori potensi sepadan.`;
    const first = selected();
    const total = view.totals();
    const comparison = noPotential
        ? tile({ title: 'Jumlah debitur BRI per kelompok usaha', meta: 'Jumlah, bukan rasio penetrasi', body: rods(sectors.map((sector) => ({ label: sector.label, value: sector.existing, formatted: count(sector.existing), color: sector.color }))) })
        : tile({ className: 'sg-tile--atlas', body: skyline({ sectors, selected: state.sector, caption: 'Kelompok usaha sepadan KBLI dan SSA CRAS' }) });

    const register = (studio) => {
        const sector = selected();
        const byDistrict = view.byDistrict(state.sector);
        const max = Math.max(1, ...[...byDistrict.values()].map((row) => row.existing || 0));
        const labelsFor = (codes) => [...byDistrict].filter(([code]) => !codes || codes.has(code)).sort((a, b) => (b[1].potential ?? b[1].existing ?? 0) - (a[1].potential ?? a[1].existing ?? 0)).slice(0, 4)
            .map(([code, row]) => ({ id: `sector-district-${code}`, codes: new Set([code]), title: ctx.index.nameOf(code).district,
                value: noPotential ? `${count(row.existing)} estimasi debitur` : `${percent(row.penetration, 1)} estimasi` }));
        studio.register('sektoral', { version: `population-${state.sector}`, dark: 1,
            journey: [{ until: 1, focus: ctx.selection?.codes || ctx.index.scopeCodes(ctx.scope.key), labels: labelsFor(null),
                heights: (code) => {
                    const row = byDistrict.get(code);
                    if (!row || (!noPotential && row.penetration === null)) return { lift: 0.02, color: 0x6b6e76 };
                    const level = noPotential ? row.existing / max : Math.min(1, row.penetration / 100);
                    return { lift: 0.08 + level * 0.92, color: noPotential ? mix(0xa5bfeb, 0x0058d0, level) : penetrationColor(row.penetration), roughness: 0.22 };
                }, caption: `<strong>${h(sector.label)}</strong><span>${h(metric(sector))} · ${h(source.period_label)}</span>` }],
            fx: { beams: false, flow: 0, speed: 0.9, color: 0x4c9dff },
            describe: (code) => {
                const row = byDistrict.get(code);
                return row ? `<strong>${h(ctx.index.nameOf(code).district)}</strong><em>${h(sector.label)}</em><span>Potensi ${count(row.potential)}, estimasi debitur BRI ${count(row.existing)}</span><small>Debitur dialokasikan dari unit layanan.</small>` : '';
            }, pick: (code) => new Set([code]), labelsFor,
            captionFor: () => `<strong>${h(label)}</strong><span>${h(sector.label)} · ${h(metric(sector))}</span>`,
            arrangement: () => ({ explode: 0, spread: 0, levitate: 0, levitateBranch: null, grow: 1, wave: 0 }),
        });
    };
    return { html: chapter({ key: 'sektoral', group: 'Wilayah · Penetrasi sektoral', title: 'Peluang pada setiap sektor.',
        figure: { value: noPotential ? count(first.existing) : percent(first.penetration, 1), label: figureLabel(first), count: false },
        chips: `<span class="sg-delta"><small>Potensi desa kelolaan</small>${count(total.potential)}</span><span class="sg-delta"><small>Intra · Aktif</small>${count(total.intra)}</span><span class="sg-delta"><small>Ekstra · PH</small>${count(total.extra)}</span><span class="sg-delta"><small>Total aktif + PH</small>${count(total.existing)}</span>`,
        lede: view.unitLevel ? `${label}. Potensi berasal dari desa yang dikelola UKER ini. Intra adalah debitur aktif, ekstra adalah debitur PH. Consumer/Konsumer dikeluarkan dari perbandingan usaha.` : `${label}. Potensi dihitung per desa kelolaan UKER. 17 kategori KBLI dipadankan menjadi 14 kelompok usaha; total aktif + PH tetap mencakup OTHER / tanpa sektor.`,
        aside: `<div class="sg-sector-context"><p data-sector-current>${h(first.label)}</p><button type="button" class="kn-button" data-sector="total">Seluruh sektor</button><a class="kn-link" href="#sg-sector-comparison">Pilih kelompok usaha</a></div>`,
        ground: 'dark', anchor: 'lead', pose: 'map', span: 240,
        sheet: `${block({ title: 'Potensi dan nasabah, berdampingan.', lede: noPotential ? 'Panjang batang menunjukkan jumlah debitur. Potensi wilayah layanan belum dapat dicocokkan seluruhnya.' : `${period} Tinggi prisma adalah potensi pelaku usaha; isinya adalah penetrasi yang dinormalisasi maksimum 100%. Angka sumber tetap tersimpan.`, body: `<div id="sg-sector-comparison" data-sg-sector-comparison>${comparison}</div>` })}
            ${block({ title: view.unitLevel ? 'Peluang pada unit terpilih.' : `Peluang menurut ${view.headLabel.toLowerCase()} dan sektor.`, lede: 'Potensi mengikuti desa kelolaan. Tabel memakai debitur UKER utuh; peta memakai estimasi alokasi kecamatan. Klik judul kolom untuk menyorot sektor.', body: `<div data-sg-ledger>${tile({ className: 'sg-tile--atlas sg-tile--ledger', body: ledgerHtml(state.sector) })}</div><p class="sg-note" data-sector-unclassified>${h(unclassifiedNote)}</p>` })}
            ${disclosure('Lihat angka dan sumber sektoral', `<div data-sg-sector-table>${entityTable(state.sector)}</div><p class="sg-note">${h(period)} ${h(notes)}</p><p class="sg-note" data-sector-coverage>${h(coverageNote)}</p>${originalTable()}${unownedTable}<p class="sg-note"><a href="${h(source.source?.url || '')}" target="_blank" rel="noopener">Sumber potensi usaha</a> · <a href="${h(source.ownership_source?.url || '')}" target="_blank" rel="noopener">Acuan desa kelolaan UKER</a>. ${count(source.source?.village_count)} desa populasi, ${count(source.ownership_source?.unique_village_count)} desa kelolaan unik.</p>`)}
            ${subsectorPanel()}` }), territory: register,
        bind(root, studio) {
            const host = root.querySelector('#sg-sektoral') || root;
            bindAtlas(host);
            const choose = (key) => {
                if (!['total', ...groups.map((group) => group.key)].includes(key)) return;
                state.sector = key;
                const sector = selected();
                host.querySelector('.sg-figure__value').textContent = noPotential ? count(sector.existing) : percent(sector.penetration, 1);
                host.querySelector('.sg-figure__label').textContent = figureLabel(sector);
                host.querySelector('[data-sector-current]').textContent = sector.label;
                host.querySelectorAll('[role="tab"][data-sector]').forEach((button) => button.setAttribute('aria-selected', String(button.dataset.sector === key)));
                const ledgerHost = host.querySelector('[data-sg-ledger]');
                ledgerHost.innerHTML = tile({ className: 'sg-tile--atlas sg-tile--ledger is-in is-settled', body: ledgerHtml(key) });
                host.querySelector('[data-sg-sector-table]').innerHTML = entityTable(key);
                register(studio);
                ctx.rescan(host);
            };
            const onClick = (event) => { const button = event.target.closest('[data-sector]'); if (button && host.contains(button)) choose(button.dataset.sector); };
            host.addEventListener('click', onClick);
            const dispose = bindSubsectors(root, ctx, studio);
            return () => { host.removeEventListener('click', onClick); dispose?.(); };
        } };
};
