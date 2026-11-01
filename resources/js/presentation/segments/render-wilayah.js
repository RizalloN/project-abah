/*
 * Wilayah segments: Marketshare, Mapping Wilayah and Penetrasi Sektoral.
 *
 * Each returns { html, territory(studio), bind(root) }. Polygon selection is
 * coordinated by the presentation entrypoint; these factories render its exact
 * branch, unit, or service-area selection from the existing source payloads.
 *
 * Every sheet reads in one order: the whole (one visual answers the chapter's
 * question), the comparison (branches or units side by side), the detail, and
 * the exact table folded away beneath it. The three chapters share one visual
 * language from atlas.js: a glass vessel is a whole, its liquid is our part.
 */

import { h, percent, count } from './format.js';
import { renderSector } from './render-sector.js';
import { table, meter } from './charts.js';
import { chapter, block, tile, empty } from './layout.js';
import { vessels, slope, coverage, opportunity, skyline, ledger, disclosure, bindAtlas } from './atlas.js';
import { BRANCH_LABELS, penetrationColor, penetrationCss, mix } from './territory-data.js';
import { subsectorPanel, bindSubsectors } from './cras-subsectors.js';

const BRI = '#0058d0';
const LOAN = '#3b82f6';
const SECTOR_COLORS = ['#2c6fe0', '#14b8a6', '#f59e0b', '#8b5cf6', '#ef4444', '#0ea5e9', '#22c55e', '#e11d48', '#a16207', '#64748b'];

const branchKeys = Object.keys(BRANCH_LABELS);

const selectedScope = (ctx) => {
    const key = ctx.scope.key === 'area6' && branchKeys.includes(ctx.selection?.branchKey)
        ? ctx.selection.branchKey : ctx.scope.key;
    return { key, label: BRANCH_LABELS[key] || ctx.scope.label };
};

const sourceUnits = (ctx) => (ctx.wilayah?.mapping?.units || []).filter((unit) =>
    ctx.scope.key === 'area6' || unit.branch === ctx.scope.key);

const selectedUnits = (ctx) => {
    const scope = selectedScope(ctx);
    let units = sourceUnits(ctx).filter((unit) => scope.key === 'area6' || unit.branch === scope.key);
    if (ctx.selection && ctx.selection.level !== 'branch') {
        const selected = ctx.selection.unitCodes?.length ? ctx.selection.unitCodes : (ctx.selection.unitCode ? [ctx.selection.unitCode] : []);
        const codes = new Set(selected.map(String));
        units = units.filter((unit) => codes.has(String(unit.code)));
    }
    return units;
};

const selectionLabel = (ctx) => (ctx.selection && ctx.selection.level !== 'branch' ? `${BRANCH_LABELS[ctx.selection.branchKey]} / ${ctx.selection.label}` : ctx.selection?.label) || (ctx.selection?.level === 'district'
    ? `Wilayah layanan ${ctx.index.nameOf(ctx.selection.districtCode).district}`
    : selectedScope(ctx).label);

const mapped = (units) => units.filter((unit) => unit.district_codes?.length);
const allocationNote = (ctx, units) => `${ctx.selection?.level === 'district' ? 'Angka wilayah layanan menjumlahkan nilai utuh unit yang melayani kecamatan ini; bukan angka eksklusif kecamatan. ' : ''}${ctx.wilayah?.mapping?.territory_source ? 'Kecamatan layanan mengikuti desa kelolaan UKER pada acuan mapping desa. ' : ''}Nilai unit yang melayani beberapa kecamatan dibagi rata pada peta; tabel memakai nilai unit utuh.${mapped(units).length < units.length ? ` ${mapped(units).length} dari ${units.length} unit memiliki polygon layanan; semua unit tetap tercakup dalam tabel dan total.` : ''}`;

/** "1.09%" from the RO report is a change in percentage points. */
const ppLabel = (value, label) => `${Number(value) > 0 ? '+' : ''}${String(label || '').replace('%', '')} pp`;

const penetrationCell = (value) => `<span class="sg-heat" style="--c:${penetrationCss(value)}">${meter(Math.min(100, value || 0), { color: penetrationCss(value) })}<strong>${percent(value, 1)}</strong></span>`;

/* --------------------------------------------------------------- marketshare -- */

const scopeRow = (segment, scope) => (scope.key === 'area6'
    ? segment.rows.find((row) => row.total)
    : segment.rows.find((row) => row.key === scope.key)) || null;

export const marketshare = (ctx) => {
    const market = ctx.wilayah?.marketshare;
    const scope = selectedScope(ctx);
    const deposit = market?.groups?.simpanan || [];
    const loan = market?.groups?.pinjaman || [];
    if (!market?.available || (!deposit.length && !loan.length)) {
        return { html: chapter({ key: 'marketshare', group: 'Wilayah', title: 'Pangsa pasar.', lede: 'Data market share belum tersedia.', territory: false, span: 120 }) };
    }
    const dpk = deposit.find((segment) => segment.key === 'dpk');
    const credit = loan.find((segment) => segment.key === 'pinjaman_total');
    const categories = [
        { key: 'simpanan', label: 'Simpanan', group: deposit, total: dpk, keys: ['tabungan', 'giro', 'deposito', 'casa'], color: BRI, note: 'CASA mencakup Giro dan Tabungan; angka produk disajikan masing-masing dan tidak dijumlahkan kembali ke Total DPK.' },
        { key: 'pinjaman', label: 'Pinjaman', group: loan, total: credit, keys: ['umkm', 'konsumer', 'briguna', 'kpr'], color: LOAN, note: 'UMKM, Konsumer, BRIGUNA, dan KPR mengikuti klasifikasi laporan sumber; angka produk tidak dijumlahkan untuk membentuk Total Pinjaman.' },
    ];
    const isArea = ctx.scope.key === 'area6';
    const period = dpk?.period_now || credit?.period_now || market.period;
    const previous = dpk?.period_prev || credit?.period_prev || '';

    // The whole: every product as a vessel, as tall as its industry, filled to BRI's share.
    const productVessels = (category) => {
        const items = category.keys.map((key) => {
            const segment = category.group.find((item) => item.key === key);
            if (!segment) return null;
            const row = scopeRow(segment, scope);
            if (!row?.labels) return null;
            return {
                key: segment.key,
                label: segment.label.replace(/^(Total |Pinjaman )/, (match) => (match === 'Total ' ? 'Total ' : '')),
                sub: `BRI ${row.labels.bri}`,
                total: row.industry,
                part: row.bri,
                ratio: row.share,
                ratioLabel: row.labels.share,
                detail: `BRI ${row.labels.bri} dari industri ${row.labels.industry} (${market.unit}). ${previous} ${row.labels.share_prev}, perubahan ${ppLabel(row.share_ytd, row.labels.share_ytd)}.`,
                color: category.color,
            };
        }).filter(Boolean);
        return items.length ? tile({
            title: `${category.label} per produk`,
            meta: `${period}, ${market.unit}`,
            className: 'sg-tile--atlas sg-market-products',
            body: `${vessels({ items, caption: `${category.label}: pangsa BRI per produk`, ratioName: 'pangsa BRI' })}<p class="sg-note">${h(category.note)}</p>`,
        }) : empty(`Rincian produk ${category.label.toLowerCase()} belum tersedia pada sumber.`);
    };

    // The movement: share in December against share now, one line per product.
    const shareShift = (group, label, color) => tile({
        title: label,
        meta: `${previous} ke ${period}`,
        className: 'sg-tile--atlas',
        body: slope({
            from: previous,
            to: period,
            caption: `Pergeseran pangsa ${label}`,
            items: group.map((segment) => {
                const row = scopeRow(segment, scope);
                return row?.labels ? { key: segment.key, label: segment.label.replace(/^Pinjaman /, ''), prev: row.share_prev, now: row.share, prevLabel: row.labels.share_prev, nowLabel: row.labels.share, deltaLabel: ppLabel(row.share_ytd, row.labels.share_ytd), color, total: ['dpk', 'pinjaman_total'].includes(segment.key) } : null;
            }).filter(Boolean),
        }),
    });

    // The comparison follows the selected category's source total, without another product selector.
    const branchVessels = (segment) => vessels({
        caption: `${segment.label} per cabang`,
        ratioName: `pangsa ${segment.label}`,
        items: segment.rows.filter((row) => !row.total && row.labels).map((row) => ({
            key: row.key,
            label: row.label,
            sub: `BRI ${row.labels.bri} dari ${row.labels.industry}`,
            total: row.industry,
            part: row.bri,
            ratio: row.share,
            ratioLabel: row.labels.share,
            detail: `BRI ${row.labels.bri} dari industri ${row.labels.industry} (${market.unit}). ${segment.period_prev} ${row.labels.share_prev}, perubahan ${ppLabel(row.share_ytd, row.labels.share_ytd)}.`,
            color: segment.kind === 'deposit' ? BRI : LOAN,
            selected: row.key === ctx.selection?.branchKey,
            dim: Boolean(ctx.selection?.branchKey) && row.key !== ctx.selection.branchKey,
        })),
    });
    const productTable = (group, label) => table({
        caption: `${label} per produk`,
        columns: [
            { label: 'Produk', cell: (row) => h(row.segment) },
            { label: 'BRI', align: 'end', cell: (row) => h(row.labels.bri) },
            { label: 'Industri', align: 'end', cell: (row) => h(row.labels.industry) },
            { label: 'Pangsa BRI', align: 'end', cell: (row) => `${meter(row.share)}<strong>${h(row.labels.share)}</strong>` },
            { label: previous || 'Sebelumnya', align: 'end', cell: (row) => h(row.labels.share_prev) },
            { label: 'YtD', align: 'end', cell: (row) => `<span class="sg-pp is-${(row.share_ytd || 0) >= 0 ? 'up' : 'down'}">${h(ppLabel(row.share_ytd, row.labels.share_ytd))}</span>` },
        ],
        rows: group.map((segment) => ({ segment: segment.label, ...scopeRow(segment, scope) })).filter((row) => row.labels),
    });

    const categoryTotal = (category) => {
        const row = category.total ? scopeRow(category.total, scope) : null;
        if (!row?.labels) return empty(`Total ${category.label.toLowerCase()} belum tersedia pada sumber; tidak dihitung dari penjumlahan produk.`);
        return `<div class="sg-market-total" data-market-total="${category.key}">
            <div class="sg-market-total__share"><p>${h(category.total.label)} · ${h(scope.label)}</p><strong>${h(row.labels.share)}</strong><span>Pangsa BRI terhadap industri</span></div>
            <dl><div><dt>BRI</dt><dd>${h(row.labels.bri)}</dd></div><div><dt>Industri</dt><dd>${h(row.labels.industry)}</dd></div><div><dt>${h(category.total.period_prev || previous)}</dt><dd>${h(row.labels.share_prev)}</dd></div><div><dt>Perubahan YtD</dt><dd>${h(ppLabel(row.share_ytd, row.labels.share_ytd))}</dd></div></dl>
            <p class="sg-market-total__source">${h(category.total.period_now || period)} · ${h(market.unit)}. ${h(market.source)}.</p>
        </div>`;
    };
    const categoryInsight = (category) => {
        const products = category.keys.map(key => category.group.find(item => item.key === key)).filter(Boolean)
            .map(segment => ({ segment, row: scopeRow(segment, scope) })).filter(item => item.row?.share != null && Number.isFinite(Number(item.row.share)));
        const strongest = [...products].sort((a, b) => b.row.share - a.row.share)[0];
        const moving = products.filter(item => item.row.share_ytd != null && Number.isFinite(Number(item.row.share_ytd)));
        const leader = [...moving].sort((a, b) => b.row.share_ytd - a.row.share_ytd)[0];
        const label = item => h(item.segment.label.replace(/^Pinjaman /, ''));
        return `<aside class="sg-market-insight" aria-label="Sorotan ${category.label}"><span>Sorotan ${category.label.toLowerCase()}</span>
            ${strongest ? `<p><strong>${label(strongest)}</strong> memimpin pangsa produk: <b>${h(strongest.row.labels.share)}</b>.</p>` : '<p>Pangsa produk belum tersedia.</p>'}
            ${leader ? `<p>Perubahan YtD tertinggi: <strong>${label(leader)}</strong> <b>${h(ppLabel(leader.row.share_ytd, leader.row.labels.share_ytd))}</b>.</p>` : ''}
        </aside>`;
    };
    const categoryPanels = `<div class="sg-market-split" data-market-comparison>
        ${categories.map(category => `<section class="sg-market-column" data-market-kind="${category.key}" aria-labelledby="sg-market-${category.key}-title">
            <header class="sg-market-column__heading"><span>${category.key === 'simpanan' ? '01 / Dana' : '02 / Kredit'}</span><h3 id="sg-market-${category.key}-title">${category.label}</h3><p>BRI dibanding industri · ${h(period)}</p></header>
            ${categoryTotal(category)}${productVessels(category)}${categoryInsight(category)}
        </section>`).join('')}
    </div>
    ${previous ? block({ title: 'Arah pergerakan pangsa', lede: `${previous} ke ${period}, dalam poin persentase.`, body: `<div class="sg-grid sg-grid--2 sg-market-support">${categories.filter(c => c.group.length).map(c => shareShift(c.group, c.label, c.color)).join('')}</div>` }) : ''}
    ${isArea ? block({ title: 'Posisi setiap cabang', lede: 'Simpanan dan pinjaman pada cakupan cabang yang sama.', body: `<div class="sg-grid sg-grid--2 sg-market-support">${categories.filter(c => c.total).map(c => tile({ title: c.label, meta: period, className: 'sg-tile--atlas', body: branchVessels(c.total) })).join('')}</div>` }) : ''}
    <div class="sg-grid sg-grid--2 sg-market-ledgers">${categories.filter(c => c.group.length).map(c => disclosure(`Tabel ${c.label.toLowerCase()} per produk`, `${productTable(c.group, c.label)}<p class="sg-note">${h(c.note)} ${h(market.unit)}.</p>`)).join('')}</div>`;

    const heightsFor = (segment) => {
        const values = Object.fromEntries(segment.rows.filter((row) => row.key).map((row) => [row.key, row.share]));
        const max = Math.max(1, ...Object.values(values));
        return (code) => {
            const value = values[ctx.index.branchOf(code)];
            if (value === undefined || value === null) return { lift: 0.02, color: 0x8e9096 };
            const t = value / max;
            return { lift: 0.12 + t * 0.88, color: mix(0xc9d6ea, 0x1d4fb0, t * t * (3 - 2 * t)), roughness: 0.26 };
        };
    };
    const primary = dpk || credit || deposit[0] || loan[0];
    const primaryRow = primary ? scopeRow(primary, scope) : null;
    const labelsFor = (segment) => segment.rows.filter((row) => row.key && (scope.key === 'area6' || row.key === scope.key)).map((row) => ({
        id: `ms-${row.key}`, codes: ctx.index.codesOfBranch(row.key), title: row.label, value: `${row.labels.share} ${segment.label}`,
    }));

    return {
        html: chapter({
            key: 'marketshare',
            group: 'Wilayah · Marketshare',
            title: 'Posisi kita di pasar.',
            figure: { value: primaryRow?.labels.share || '-', label: primaryRow ? `Pangsa ${primary.label} BRI ${scope.label} dibanding seluruh industri, ${primary.period_now}` : 'Total belum tersedia pada sumber.' },
            chips: primaryRow ? `<span class="sg-delta is-${(primaryRow.share_ytd || 0) >= 0 ? 'up' : 'down'}"><small>${h(primary.label)} YtD</small>${h(ppLabel(primaryRow.share_ytd, primaryRow.labels.share_ytd))}</span>` : '<span class="sg-delta">YtD belum tersedia</span>',
            lede: scope.key === 'area6' ? 'Pilih polygon cabang untuk membuka pangsa simpanan dan pinjaman terhadap industri.' : `Pangsa BRI terhadap industri di ${scope.label}, untuk simpanan dan pinjaman.`,
            note: 'Data industri tersedia per cabang. Klik polygon cabang untuk memilih; tinggi wilayah mengikuti pangsa cabangnya.',
            ground: 'light',
            anchor: 'lead',
            pose: 'lead',
            span: isArea ? 300 : 220,
            sheet: categoryPanels,
        }),
        territory(studio) {
            const heights = heightsFor(primary);
            studio.register('marketshare', {
                journey: [{ until: 1, focus: ctx.index.scopeCodes(ctx.scope.key), labels: labelsFor(primary), heights }],
                fx: { beams: true, flow: -1, speed: 1, color: 0x2c6fe0 },
                describe: (code) => {
                    const name = ctx.index.nameOf(code);
                    const row = primary.rows.find((item) => item.key === name.branch);
                    return `<small>${h(name.regency)}</small><strong>${h(name.district)}</strong>${row ? `<em>${h(row.label)}: ${h(primary.label)} ${h(row.labels.share)}</em>` : ''}`;
                },
                pick: (code) => ctx.index.codesOfBranch(ctx.index.branchOf(code)),
                captionFor: (codes) => {
                    const branch = branchKeys.find((key) => ctx.index.codesOfBranch(key) === codes || [...codes].every((code) => ctx.index.branchOf(code) === key));
                    const row = primary.rows.find((item) => item.key === branch);
                    return row ? `<strong>${h(row.label)}</strong><span>${h(primary.label)} ${h(row.labels.share)}</span>` : '';
                },
                arrangement: () => ({ explode: 0, spread: 0, levitate: 0, levitateBranch: null, grow: 1, wave: 0 }),
            });
        },
        bind(root, studio) {
            const chapterRoot = root.querySelector('#sg-marketshare') || root;
            bindAtlas(chapterRoot);

        },
    };
};

/* ------------------------------------------------------------------- mapping -- */

const unitRows = (units) => units.map((unit) => {
    const values = unit.values?.total || {};
    const potential = Number(values.potential) || 0;
    const existing = Number(values.existing) || 0;
    return { unit, key: String(unit.code), name: unit.name, branch: BRANCH_LABELS[unit.branch] || unit.branch, potential, existing, penetration: potential > 0 ? (existing / potential) * 100 : null, gap: Math.max(0, potential - existing) };
});

export const mapping = (ctx) => {
    const map = ctx.wilayah?.mapping;
    const scope = selectedScope(ctx);
    const label = selectionLabel(ctx);
    const isArea = scope.key === 'area6';
    if (!map?.ready || !sourceUnits(ctx).length) {
        return { html: chapter({ key: 'mapping', group: 'Wilayah', title: 'Mapping wilayah.', lede: 'Peta unit kerja belum tersedia: sheet REKAP atau polygon kecamatan belum siap.', territory: false, span: 120 }) };
    }
    const units = selectedUnits(ctx);
    const totals = ctx.index.sectorTotals('total', units);
    const rows = unitRows(units);
    const byDistrict = ctx.index.sectorByDistrict('total', mapped(units));

    const heights = (code) => {
        const entry = byDistrict.get(code);
        if (!entry || entry.penetration === null) return { lift: 0.02, color: 0x8e9096 };
        return { lift: 0.1 + Math.min(1, entry.penetration / 100) * 0.9, color: penetrationColor(entry.penetration), roughness: 0.24 };
    };
    const branchLabel = (branch) => {
        const branchTotals = ctx.index.sectorTotals('total', units.filter((unit) => unit.branch === branch));
        return { id: `map-${branch}`, codes: ctx.index.codesOfBranch(branch), title: BRANCH_LABELS[branch], value: `${percent(branchTotals.penetration, 1)} penetrasi` };
    };
    const unitLabel = (row) => ({ id: `map-u-${row.unit.code}`, codes: ctx.index.codesOfUnit(row.unit), title: row.name, value: percent(row.penetration, 1) });
    const caption = (title, figures) => `<strong>${h(title)}</strong><span>Potensi ${count(figures.potential)} &middot; Nasabah ${count(figures.existing)} &middot; Penetrasi ${percent(figures.penetration, 1)}</span>`;
    const topGaps = [...rows].filter((row) => row.unit.district_codes?.length).sort((a, b) => b.gap - a.gap).slice(0, 4);

    // The four branches always stand together, so a selected KC is read against its peers.
    const allUnits = sourceUnits(ctx);
    const branchVessels = ctx.scope.key === 'area6' ? tile({
        className: 'sg-tile--atlas',
        body: vessels({
            caption: 'Penetrasi per cabang',
            ratioName: 'penetrasi',
            items: branchKeys.map((branch) => {
                const branchTotals = ctx.index.sectorTotals('total', allUnits.filter((unit) => unit.branch === branch));
                return {
                    key: branch,
                    label: BRANCH_LABELS[branch],
                    sub: `${count(branchTotals.existing)} dari ${count(branchTotals.potential)}`,
                    total: branchTotals.potential,
                    part: branchTotals.existing,
                    ratio: branchTotals.penetration,
                    ratioLabel: percent(branchTotals.penetration, 1),
                    detail: `Potensi ${count(branchTotals.potential)}, nasabah BRI ${count(branchTotals.existing)}, belum tergarap ${count(branchTotals.gap)}.`,
                    color: penetrationCss(branchTotals.penetration),
                    selected: branch === ctx.selection?.branchKey,
                    dim: Boolean(ctx.selection?.branchKey) && branch !== ctx.selection.branchKey,
                };
            }),
        }),
    }) : '';

    const branchTable = isArea ? table({
        caption: 'Penetrasi per cabang',
        columns: [
            { label: 'Cabang', cell: (row) => h(row.label) },
            { label: 'Unit', align: 'end', cell: (row) => count(row.units) },
            { label: 'Potensi', align: 'end', cell: (row) => count(row.potential) },
            { label: 'Nasabah BRI', align: 'end', cell: (row) => count(row.existing) },
            { label: 'Penetrasi', align: 'end', cell: (row) => penetrationCell(row.penetration) },
            { label: 'Belum tergarap', align: 'end', cell: (row) => count(row.gap) },
        ],
        rows: branchKeys.map((branch) => {
            const branchUnits = units.filter((unit) => unit.branch === branch);
            return { label: BRANCH_LABELS[branch], units: branchUnits.length, ...ctx.index.sectorTotals('total', branchUnits) };
        }),
        footer: { label: 'Area 6', units: units.length, ...totals },
    }) : '';

    const unitTable = table({
        caption: 'Penetrasi per unit',
        columns: [
            { label: 'Unit', cell: (row) => `${h(row.name)}${isArea ? `<small>${h(row.branch)}</small>` : ''}` },
            { label: 'Potensi', align: 'end', cell: (row) => count(row.potential) },
            { label: 'Nasabah BRI', align: 'end', cell: (row) => count(row.existing) },
            { label: 'Penetrasi', align: 'end', cell: (row) => penetrationCell(row.penetration) },
            { label: 'Belum tergarap', align: 'end', cell: (row) => count(row.gap) },
        ],
        rows: [...rows].sort((a, b) => b.gap - a.gap),
    });

    return {
        html: chapter({
            key: 'mapping',
            group: 'Wilayah · Mapping',
            title: 'Di mana nasabah kita, di mana belum.',
            figure: { value: percent(totals.penetration, 1), label: `Penetrasi nasabah BRI terhadap potensi, ${label}` },
            chips: `<span class="sg-delta"><small>Potensi</small>${count(totals.potential)}</span><span class="sg-delta"><small>Nasabah</small>${count(totals.existing)}</span><span class="sg-delta is-down"><small>Belum tergarap</small>${count(totals.gap)}</span>`,
            lede: ctx.selection
                ? `${label}. Angka di bawah mengikuti wilayah yang dipilih langsung pada peta.`
                : 'Klik polygon cabang untuk memilih wilayah, lalu pilih polygon kecamatan atau penanda unit untuk melihat rinciannya.',
            note: 'Tinggi dan warna setiap kecamatan adalah penetrasinya: merah tipis, biru kuat.',
            aside: `<p class="sg-legend-ramp"><span>0%</span><i aria-hidden="true"></i><span>100%</span></p>`,
            ground: 'dark',
            anchor: 'trail',
            pose: 'map',
            span: isArea ? 380 : 300,
            sheet: `
                ${branchVessels ? block({ title: 'Empat cabang berdampingan.', lede: 'Setiap bejana setinggi potensi cabangnya; isinya nasabah BRI.', body: branchVessels }) : ''}
                ${block({ title: 'Di mana peluang terbesar.', lede: `Setiap bola adalah satu unit: makin ke kiri makin tipis penetrasinya, makin tinggi makin besar potensinya, dan besarnya adalah nasabah yang belum tergarap.`, body: tile({ className: 'sg-tile--atlas', body: opportunity({ rows: rows.map((row) => ({ ...row, sub: row.branch })), average: totals.penetration, caption: `Peta peluang unit, ${label}`, source: `Sheet REKAP, diperbarui ${map.updated_at || '-'}. Angka unit utuh pada wilayah terpilih.` }) }) })}
                ${disclosure(`Lihat tabel ${rows.length} unit`, `<div class="sg-grid sg-grid--1">${branchTable}${unitTable}</div><p class="sg-note">Sheet REKAP, diperbarui ${h(map.updated_at)}. ${h(allocationNote(ctx, units))}</p>`)}
            `,
        }),
        territory(studio) {
            const overviewLabels = isArea ? branchKeys.map(branchLabel) : topGaps.map(unitLabel);
            studio.register('mapping', {
                journey: [{ until: 1, focus: ctx.index.scopeCodes(ctx.scope.key), labels: overviewLabels, heights, caption: caption(label, totals) }],
                dark: 1,
                fx: { beams: false, flow: 0, speed: 0.9, color: 0x4c9dff },
                describe: (code) => {
                    const name = ctx.index.nameOf(code);
                    const entry = byDistrict.get(code);
                    const served = ctx.index.unitsOf(code).filter((unit) => units.includes(unit));
                    return `<small>${h(name.regency)}</small><strong>${h(name.district)}</strong>${entry ? `<em>Penetrasi ${percent(entry.penetration, 1)}</em>` : ''}${served.length ? `<span>${served.map((unit) => h(unit.name)).join(', ')}</span>` : ''}`;
                },
                pick: (code) => new Set([code]),
                labelsFor: (codes) => {
                    const inside = rows.filter((row) => row.unit.district_codes?.some((code) => codes.has(code)));
                    return inside.sort((a, b) => b.potential - a.potential).slice(0, 5).map(unitLabel);
                },
                captionFor: () => caption(label, totals),
                arrangement: () => ({ explode: 0, spread: 0, levitate: 0, levitateBranch: null, grow: 1, wave: 0 }),
            });
        },
        bind(root) {
            bindAtlas(root.querySelector('#sg-mapping') || root);
        },
    };
};

/* ------------------------------------------------------------------ sektoral -- */

export const sektoral = (ctx) => renderSector(ctx);

export const emptySegment = (key, title) => ({ html: chapter({ key, group: 'Segmen', title, lede: 'Data belum tersedia.', territory: false, span: 120, sheet: empty('Data belum tersedia untuk cakupan ini.') }) });
