/*
 * Kinerja segments: Simpanan, Pinjaman, Kualitas and Recovery DH, all from the
 * segments endpoint for the chosen scope. The Area breaks down per branch, a
 * branch per outlet; the relief stands each branch or unit as high as its figure.
 */

import { h, percent, deltaChip } from './format.js';
import { donut, timeseries, rods, table, meter } from './charts.js';
import { chapter, block, tile, specs, empty } from './layout.js';

const COLORS = {
    total: '#0058d0',
    giro: '#307fe2', tabungan: '#71c5e8', deposito: '#c6a688',
    ritel: '#0b5cd5', mikro: '#f59e0b', wholesale: '#8b5cf6',
    sme: '#2c6fe0', konsumer: '#14b8a6', kecil: '#2c6fe0',
    sml: '#ff9a2e', npl: '#ff3b30',
};
const RELIEF = { simpanan: 0x2c6fe0, pinjaman: 0x8f8a80, recovery: 0x16a34a };

const PERFORMANCE_PATHS = {
    simpanan: [['composition', 'Komposisi dana', 'Produk dan segmen'], ['trend', 'Timeseries Simpanan', 'Posisi per bulan'], ['entities', 'Pertumbuhan unit', 'YoY, YtD, dan MtD'], ['casa', 'CASA unit', 'Giro dan Tabungan']],
    pinjaman: [['composition', 'Portofolio kredit', 'Segmen dan produk'], ['trend', 'Timeseries Pinjaman', 'Posisi per bulan'], ['entities', 'Kontribusi wilayah', 'Rincian dan pertumbuhan']],
    kualitas: [['composition', 'Posisi risiko', 'Rasio SML dan NPL'], ['products', 'Sumber risiko', 'Segmen dan produk'], ['trend', 'Timeseries Kualitas', 'Posisi per bulan'], ['entities', 'Sebaran risiko', 'Rincian setiap wilayah']],
    recovery: [['composition', 'Hasil pemulihan', 'Komposisi dan peringkat'], ['trend', 'Timeseries Recovery DH', 'Posisi per bulan'], ['entities', 'Kontribusi wilayah', 'Rincian recovery DH']],
};

const mappingCoverage = (ctx) => {
    const data = ctx.segments;
    if (!data?.available || !data.entities?.length || data.scope.level === 'area' || !ctx.index?.ready) return '';
    const mapped = ctx.index.entityCodes(data.entities, false, data.scope.key).size;
    return mapped < data.entities.length ? `<p class="sg-note sg-performance-coverage">${mapped} dari ${data.entities.length} unit kerja memiliki padanan wilayah pada peta. Seluruh unit kerja tetap tercakup dalam tabel dan total.</p>` : '';
};

const performanceChapter = (ctx, options) => {
    const paths = PERFORMANCE_PATHS[options.key];
    const navigation = options.sheet && ctx.segments?.available && !options.emptyState ? `<nav class="sg-performance-path" aria-label="Bagian ${h(options.group)}"><p>Jelajahi kinerja</p><ol>${paths.map(([slug, label, detail], index) => `<li><a href="#performance-${h(options.key)}-${slug}"><small>${String(index + 1).padStart(2, '0')}</small><span><strong>${h(label)}</strong><small>${h(detail)}</small></span><i aria-hidden="true">↘</i></a></li>`).join('')}</ol></nav>` : '';
    return chapter({ ...options, territory: true, sheet: `${navigation}${options.sheet || ''}${mappingCoverage(ctx)}` })
        .replace('class="sg-chapter ', `data-performance-theme="${h(options.key)}" class="sg-chapter sg-performance `);
};

const performanceBlock = (key, slug, options) => `<section class="sg-performance-act sg-performance-act--${slug}" id="performance-${key}-${slug}" aria-label="${h(options.title)}">${block(options).replace('</header>', '<span class="sg-performance-sculpture" aria-hidden="true"><i></i><i></i><i></i></span><span class="sg-performance-signature" aria-hidden="true"><i></i><i></i><i></i></span></header>')}</section>`;

const neutralTerritory = (ctx, key, dark = 0, status = 'Data kinerja belum tersedia') => (studio) => studio.register(key, {
    journey: [{ until: 1, focus: ctx.index.scopeCodes(ctx.scope.key), labels: [], heights: () => ({ lift: .08, color: dark ? 0x56647a : 0xb4c2d2, roughness: .42 }) }],
    dark, fx: { beams: false, flow: 0, speed: 0, color: 0x7893b5 },
    describe: code => { const name = ctx.index.nameOf(code); return `<small>${h(name.regency)}</small><strong>${h(name.district)}</strong><em>${h(status)}</em>`; },
    arrangement: () => ({ explode: 0, spread: 0, levitate: 0, levitateBranch: null, grow: 1, wave: 0 }),
});

const emptyPerformance = (ctx, key, title, lede, message = 'Snapshot belum tersedia untuk cakupan dan posisi ini.') => ({
    html: performanceChapter(ctx, { key, group: 'Kinerja', title, lede, emptyState: true, span: 120, ground: key === 'kualitas' ? 'dark' : 'light', sheet: empty(message) }),
    territory: neutralTerritory(ctx, key, key === 'kualitas' ? 1 : 0, lede),
});

const deltaChips = (deltas, compare, options = {}) => ['dtd', 'mtd', 'ytd', 'yoy']
    .map((key) => deltaChip(compare?.[key]?.title || key.toUpperCase(), deltas?.[key], options))
    .join('');

const entityWord = (data) => (data.scope.entity_level === 'branch' ? 'cabang' : 'unit');

/** The gateway shows exact branch totals; outlet values stay in the report. */
const moneyTerritory = (ctx, key, valueOf, formattedOf, { color, dark = 0, fx = {} } = {}) => (studio) => {
    const data = ctx.overviewSegments || ctx.segments;
    if (!data?.available || !data[key]) return neutralTerritory(ctx, key, dark)(studio);
    if (ctx.overviewSegments) {
        const overview = rowsByKey(data[key].rows);
        valueOf = entityKey => rowValue(overview.get(entityKey), 'total');
        formattedOf = entityKey => rowText(overview.get(entityKey), 'total');
    }
    const isArea = data.scope.level === 'area';
    const entities = isArea ? data.entities : [{ key: `kc-${data.scope.key}`, label: data.scope.label }];
    if (!isArea) {
        valueOf = () => data[key].total.value;
        formattedOf = () => data[key].total.formatted;
    }
    const codes = isArea ? ctx.index.entityCodes(entities, true, data.scope.key) : new Map([[entities[0].key, ctx.index.scopeCodes(data.scope.key)]]);
    const byDistrict = new Map();
    codes.forEach((districts, entityKey) => {
        const value = valueOf(entityKey);
        if (value !== null && value !== undefined && Number.isFinite(Number(value))) districts.forEach(code => byDistrict.set(code, Number(value)));
    });
    const max = Math.max(1e-9, ...byDistrict.values());
    const heights = (code) => {
        const value = byDistrict.get(code);
        return value === undefined ? { lift: 0.03, color: dark ? 0x3a3c42 : 0xc3c6cc, roughness: 0.4 } : { lift: 0.1 + (value / max) * 0.9, color, roughness: 0.28 };
    };
    const ranked = entities.filter((entity) => codes.has(entity.key)).sort((a, b) => valueOf(b.key) - valueOf(a.key)).slice(0, isArea ? 4 : 5);
    const labels = ranked.map((entity) => ({ id: `${key}-${entity.key}`, codes: codes.get(entity.key), title: entity.label.replace(' (kantor cabang)', ''), value: formattedOf(entity.key) }));
    const focus = ctx.index.scopeCodes(data.scope.key);
    const entityOfCode = (code) => entities.find((entity) => codes.get(entity.key)?.has(code));
    studio.register(key, {
        journey: [{ until: 1, focus, labels, heights }],
        dark,
        fx: { beams: true, flow: 0, speed: 1, color, ...fx },
        describe: (code) => {
            const name = ctx.index.nameOf(code);
            const entity = entityOfCode(code);
            return `<small>${h(name.regency)}</small><strong>${h(name.district)}</strong>${entity ? `<em>${h(entity.label)}: ${h(formattedOf(entity.key))}</em>` : ''}`;
        },
        pick: (code) => {
            const entity = entityOfCode(code);
            return entity ? codes.get(entity.key) : null;
        },
        captionFor: (focusCodes) => {
            const entity = entities.find((item) => codes.get(item.key) === focusCodes);
            return entity ? `<strong>${h(entity.label)}</strong><span>${h(formattedOf(entity.key))}</span>` : '';
        },
        labelsFor: (focusCodes) => labels.filter((label) => [...label.codes].some((code) => focusCodes.has(code))),
        arrangement: (p, still) => ({
            explode: 0,
            spread: still || !isArea ? 0 : Math.min(1, Math.max(0, (p - 0.3) / 0.3)),
            levitate: 0,
            levitateBranch: null,
            grow: still ? 1 : 0.06 + 0.94 * Math.min(1, Math.max(0, (p - 0.02) / 0.3)),
            wave: 0,
        }),
    });
};

const rowValue = (row, key) => row?.values?.[key]?.value ?? null;
const rowText = (row, key) => row?.values?.[key]?.formatted ?? '-';
const rowsByKey = (rows) => new Map(rows.map((row) => [row.key, row]));

const growthRods = (rows, key, limit = 12) => rods(
    [...rows].filter((row) => row.values?.[key]?.mtd?.value != null).sort((a, b) => b.values[key].mtd.value - a.values[key].mtd.value).slice(0, limit).map((row) => ({
        label: row.label.replace(' (kantor cabang)', ''),
        value: row.values[key].mtd.value,
        formatted: row.values[key].mtd.formatted,
        tone: row.values[key].mtd.value >= 0 ? 'up' : 'down',
    })),
    { signed: true },
);

const mtdCell = (cell) => (cell?.mtd ? `<span class="sg-pp is-${cell.mtd.value >= 0 ? 'up' : 'down'}">${h(cell.mtd.formatted)}</span>` : '-');
const growthColumns = (metric = 'total') => ['yoy', 'ytd', 'mtd'].map(key => ({
    label: { yoy: 'YoY', ytd: 'YtD', mtd: 'MtD' }[key], align: 'end',
    cell: row => {
        const value = row.values?.[metric];
        const delta = value?.deltas?.[key] ?? (key === 'mtd' ? value?.mtd : null);
        return delta ? `<span class="sg-pp is-${delta.value >= 0 ? 'up' : 'down'}">${h(delta.formatted)}</span>` : '-';
    },
}));
const comparisonNote = data => ['yoy', 'ytd', 'mtd'].map(key => `${data.compare[key]?.title || key.toUpperCase()}: ${data.compare[key]?.label || 'pembanding belum tersedia'}`).join(' · ');

/* ------------------------------------------------------------------ simpanan -- */

export const simpanan = (ctx) => {
    const data = ctx.segments;
    const segment = data?.simpanan;
    if (!data?.available || !segment) return emptyPerformance(ctx, 'simpanan', 'Kinerja simpanan.', 'Snapshot belum tersedia.');
    const rows = segment.rows;
    const lookup = rowsByKey(rows);
    const word = entityWord(data);
    const casaRatio = segment.casa?.share;

    return {
        html: performanceChapter(ctx, {
            key: 'simpanan',
            group: 'Kinerja · Simpanan',
            title: 'Dana yang kita himpun.',
            figure: { value: segment.total.formatted, label: `Total simpanan ${data.scope.label}, ${data.period_label}` },
            chips: deltaChips(segment.total.deltas, data.compare),
            lede: `Per produk dan per segmen, lalu setiap ${word} dan perkembangannya dari waktu ke waktu.`,
            note: 'Tinggi setiap wilayah mengikuti total simpanan kantor cabangnya.',
            ground: 'light',
            anchor: 'trail',
            pose: 'trail',
            sheet: `
                ${specs([
                    { label: 'Total simpanan', value: segment.total.formatted },
                    { label: 'CASA', value: segment.casa.formatted, note: casaRatio ? `${casaRatio.formatted} dari simpanan` : '' },
                    { label: `MtD (${data.compare.mtd.label})`, value: segment.total.deltas.mtd?.formatted || '-', tone: (segment.total.deltas.mtd?.value || 0) >= 0 ? 'up' : 'down' },
                    { label: `YtD (${data.compare.ytd.label})`, value: segment.total.deltas.ytd?.formatted || '-', tone: (segment.total.deltas.ytd?.value || 0) >= 0 ? 'up' : 'down' },
                ])}
                ${performanceBlock('simpanan', 'composition', { title: 'Komposisi', body: `<div class="sg-grid sg-grid--2">
                    ${tile({ title: 'Per produk', meta: data.period_label, body: donut({ caption: 'Simpanan per produk', center: { value: segment.total.formatted, label: 'total simpanan' }, items: segment.products.map((item) => ({ key: item.key, label: item.label, value: item.value, formatted: item.formatted, color: COLORS[item.key], note: item.deltas.mtd ? `MtD ${item.deltas.mtd.formatted}` : '' })) }) })}
                    ${tile({ title: 'Per segmen', meta: 'Ritel, Mikro, Wholesale', body: donut({ caption: 'Simpanan per segmen', center: { value: segment.segments.find((item) => item.key === 'mikro')?.share?.formatted || '-', label: 'porsi mikro' }, items: segment.segments.map((item) => ({ key: item.key, label: item.label, value: item.value, formatted: item.formatted, color: COLORS[item.key], note: item.deltas.mtd ? `MtD ${item.deltas.mtd.formatted}` : '' })) }) })}
                </div>` })}
                ${performanceBlock('simpanan', 'trend', { title: 'Timeseries Simpanan', body: timeseries({ scrollable: true, zeroBaseline: true, title: 'Simpanan per bulan', labels: data.timeseries_labels, series: segment.timeseries.series.map((item) => ({ ...item, color: COLORS[item.key] })), shown: ['total'], note: 'Sumbu nilai dimulai dari nol. Posisi akhir setiap bulan; bulan berjalan memakai posisi terakhir. Pilih seri lain di legenda.' }) })}
                ${performanceBlock('simpanan', 'entities', { title: `Pertumbuhan setiap ${word}`, lede: `${rows.length} ${word}, diurutkan dari simpanan terbesar. ${comparisonNote(data)}.`, body: `<div class="sg-grid sg-grid--1">${tile({ wide: true, body: table({
                    caption: `Simpanan per ${word}`,
                    columns: [
                        { label: word === 'cabang' ? 'Cabang' : 'Unit kerja', cell: (row) => h(row.label) },
                        { label: 'Total', align: 'end', cell: (row) => `<strong>${h(rowText(row, 'total'))}</strong>` },
                        { label: 'Giro', align: 'end', cell: (row) => h(rowText(row, 'giro')) },
                        { label: 'Tabungan', align: 'end', cell: (row) => h(rowText(row, 'tabungan')) },
                        { label: 'Deposito', align: 'end', cell: (row) => h(rowText(row, 'deposito')) },
                        { label: 'Ritel', align: 'end', cell: (row) => h(rowText(row, 'ritel')) },
                        { label: 'Mikro', align: 'end', cell: (row) => h(rowText(row, 'mikro')) },
                        { label: 'Porsi', align: 'end', cell: (row) => `${meter(row.values.total.share)}<small>${percent(row.values.total.share)}</small>` },
                        ...growthColumns(),
                    ],
                    rows: [...rows].sort((a, b) => rowValue(b, 'total') - rowValue(a, 'total')),
                }) })}</div>` })}
                ${performanceBlock('simpanan', 'casa', { title: `CASA setiap ${word}`, lede: `Dana murah berasal dari Giro dan Tabungan. Porsi CASA dibandingkan total simpanan masing-masing unit kerja. YoY, YtD, dan MtD menunjukkan perubahan nominal CASA. ${comparisonNote(data)}.`, body: tile({ wide: true, body: table({
                    caption: `CASA per ${word}, ${data.period_label}`, columns: [
                        { label: word === 'cabang' ? 'Cabang' : 'Unit kerja', cell: row => h(row.label) },
                        { label: 'Giro', align: 'end', cell: row => h(rowText(row, 'giro')) },
                        { label: 'Tabungan', align: 'end', cell: row => h(rowText(row, 'tabungan')) },
                        { label: 'CASA', align: 'end', cell: row => `<strong>${h(rowText(row, 'casa'))}</strong>` },
                        { label: 'Porsi CASA', align: 'end', cell: row => percent(rowValue(row, 'total') > 0 && rowValue(row, 'casa') != null ? rowValue(row, 'casa') / rowValue(row, 'total') * 100 : null) },
                        ...growthColumns('casa'),
                    ], rows: [...rows].sort((a, b) => rowValue(b, 'casa') - rowValue(a, 'casa')),
                }) }) })}
            `,
        }),
        territory: moneyTerritory(ctx, 'simpanan', (key) => rowValue(lookup.get(key), 'total'), (key) => rowText(lookup.get(key), 'total'), { color: RELIEF.simpanan, fx: { flow: -1 } }),
    };
};

/* ------------------------------------------------------------------ pinjaman -- */

export const pinjaman = (ctx) => {
    const data = ctx.segments;
    const segment = data?.pinjaman;
    if (!data?.available || !segment) return emptyPerformance(ctx, 'pinjaman', 'Kinerja pinjaman.', 'Snapshot belum tersedia.');
    const rows = segment.rows;
    const lookup = rowsByKey(rows);
    const word = entityWord(data);

    const productTiles = segment.products.filter((group) => group.products.length).map((group) => tile({
        title: `${group.label} per produk`,
        meta: segment.segments.find((item) => item.key === group.key)?.formatted || '',
        body: rods(group.products.map((product) => ({ label: product.label, value: product.value, formatted: product.formatted, note: `${product.share.formatted} dari pinjaman${product.deltas.mtd ? ` · MtD ${product.deltas.mtd.formatted}` : ''}`, color: COLORS[group.key] }))),
    })).join('');

    return {
        html: performanceChapter(ctx, {
            key: 'pinjaman',
            group: 'Kinerja · Pinjaman',
            title: 'Kredit yang kita salurkan.',
            figure: { value: segment.total.formatted, label: `Outstanding ${data.scope.label}, ${data.period_label}` },
            chips: deltaChips(segment.total.deltas, data.compare),
            lede: `SME, Konsumer, dan Mikro beserta setiap produknya, lalu setiap ${word}.`,
            note: 'Tinggi setiap wilayah mengikuti outstanding kantor cabangnya.',
            ground: 'light',
            anchor: 'lead',
            pose: 'lead',
            sheet: `
                ${specs([
                    { label: 'Outstanding', value: segment.total.formatted },
                    ...segment.segments.map((item) => ({ label: item.label, value: item.formatted, note: `${item.share.formatted} dari OS` })),
                ])}
                ${performanceBlock('pinjaman', 'composition', { title: 'Per segmen dan produk', body: `<div class="sg-grid sg-grid--2">
                    ${tile({ title: 'Per segmen', meta: data.period_label, body: donut({ caption: 'Pinjaman per segmen', center: { value: segment.total.formatted, label: 'outstanding' }, items: segment.segments.map((item) => ({ key: item.key, label: item.label, value: item.value, formatted: item.formatted, color: COLORS[item.key], note: item.deltas.mtd ? `MtD ${item.deltas.mtd.formatted}` : '' })) }) })}
                    ${productTiles}
                </div>` })}
                ${performanceBlock('pinjaman', 'trend', { title: 'Timeseries Pinjaman', body: timeseries({ scrollable: true, zeroBaseline: true, title: 'Outstanding per bulan', labels: data.timeseries_labels, series: segment.timeseries.series.map((item) => ({ ...item, color: COLORS[item.key] })), shown: ['total'], note: 'Sumbu nilai dimulai dari nol. Posisi akhir setiap bulan, non-komersial. Pilih segmen di legenda.' }) })}
                ${performanceBlock('pinjaman', 'entities', { title: `Pertumbuhan setiap ${word}`, lede: `${rows.length} ${word}, diurutkan dari outstanding terbesar. ${comparisonNote(data)}.`, body: `<div class="sg-grid sg-grid--1">${tile({ wide: true, body: table({
                    caption: `Pinjaman per ${word}`,
                    columns: [
                        { label: word === 'cabang' ? 'Cabang' : 'Unit kerja', cell: (row) => h(row.label) },
                        { label: 'Outstanding', align: 'end', cell: (row) => `<strong>${h(rowText(row, 'total'))}</strong>` },
                        { label: 'SME', align: 'end', cell: (row) => h(rowText(row, 'sme')) },
                        { label: 'Konsumer', align: 'end', cell: (row) => h(rowText(row, 'konsumer')) },
                        { label: 'Mikro', align: 'end', cell: (row) => h(rowText(row, 'mikro')) },
                        { label: 'Porsi', align: 'end', cell: (row) => `${meter(row.values.total.share)}<small>${percent(row.values.total.share)}</small>` },
                        ...growthColumns(),
                    ],
                    rows: [...rows].sort((a, b) => rowValue(b, 'total') - rowValue(a, 'total')),
                }) })}</div>` })}
            `,
        }),
        territory: moneyTerritory(ctx, 'pinjaman', (key) => rowValue(lookup.get(key), 'total'), (key) => rowText(lookup.get(key), 'total'), { color: RELIEF.pinjaman, fx: { flow: 1, color: 0x4a78e8 } }),
    };
};

/* ------------------------------------------------------------------ kualitas -- */

export const kualitas = (ctx) => {
    const data = ctx.segments;
    const segment = data?.kualitas;
    if (!data?.available || !segment) return emptyPerformance(ctx, 'kualitas', 'Kualitas kredit.', 'Snapshot belum tersedia.');
    const word = entityWord(data);
    const total = segment.total;
    const qualityMetric = ctx.presentationState?.qualityMetric === 'npl' ? 'npl' : 'sml';
    const qualityName = qualityMetric.toUpperCase();
    const alternateMetric = qualityMetric === 'sml' ? 'npl' : 'sml';
    const ratioMax = (key) => Math.max(1, ...segment.rows.map((row) => row[key].value), ...segment.segments.map((row) => row[key].value));
    const ratioCell = (cell, key) => `<span class="sg-heat">${meter(cell.value, { max: ratioMax(key), color: COLORS[key.startsWith('sml') ? 'sml' : 'npl'] })}<strong>${h(cell.formatted)}</strong></span>`;
    const productRows = segment.products.flatMap((group) => group.products.map((product, index) => ({ ...product, group: index === 0 ? group.label : '', className: index === 0 ? 'is-group-start' : '' })));
    const gauge = (key, label) => donut({
        caption: `${label} terhadap outstanding`,
        center: { value: total[`${key}_ratio`].formatted, label: `rasio ${label}` },
        size: 'sm',
        items: [
            { key, label, value: total[key].value, formatted: total[key].formatted, color: COLORS[key] },
            { key: 'lancar', label: 'Lainnya', value: total.os.value !== null && total[key].value !== null && total.os.value >= total[key].value ? total.os.value - total[key].value : null, formatted: '', color: 'rgba(255,255,255,0.14)' },
        ],
    });

    return {
        html: performanceChapter(ctx, {
            key: 'kualitas',
            group: 'Kinerja · Kualitas',
            title: 'Di mana risiko berkumpul.',
            figure: { value: total[`${qualityMetric}_ratio`].formatted, label: `Rasio ${qualityName} ${data.scope.label}, ${qualityName} ${total[qualityMetric].formatted}` },
            chips: `<span class="sg-delta is-flat"><small>${alternateMetric.toUpperCase()}</small>${h(total[`${alternateMetric}_ratio`].formatted)}</span>${deltaChips(total[`${qualityMetric}_ratio`].deltas, data.compare, { invert: true })}`,
            lede: `SML dan NPL per segmen dan produk, lalu ${word} dengan rasio tertinggi.`,
            note: `Cahaya di dalam wilayah mengikuti rasio ${qualityName} kantor cabangnya. Gulir untuk menelusuri SML dan NPL.`,
            ground: 'dark',
            anchor: 'trail',
            pose: 'trail',
            span: 260,
            sheet: `
                ${performanceBlock('kualitas', 'composition', { title: 'Rasio terhadap outstanding', body: `<div class="sg-grid sg-grid--2">
                    ${tile({ title: 'Special Mention Loan', meta: `MtD ${total.sml_ratio.deltas.mtd?.formatted || '-'} · YtD ${total.sml_ratio.deltas.ytd?.formatted || '-'}`, body: gauge('sml', 'SML') })}
                    ${tile({ title: 'Non-Performing Loan', meta: `MtD ${total.npl_ratio.deltas.mtd?.formatted || '-'} · YtD ${total.npl_ratio.deltas.ytd?.formatted || '-'}`, body: gauge('npl', 'NPL') })}
                </div>` })}
                ${performanceBlock('kualitas', 'products', { title: 'Per segmen dan produk', body: `<div class="sg-grid sg-grid--1">
                    ${tile({ wide: true, title: 'Per segmen', body: table({ caption: 'Kualitas per segmen', columns: [
                        { label: 'Segmen', cell: (row) => h(row.label) },
                        { label: 'Outstanding', align: 'end', cell: (row) => h(row.os.formatted) },
                        { label: 'SML', align: 'end', cell: (row) => h(row.sml.formatted) },
                        { label: 'Rasio SML', align: 'end', cell: (row) => ratioCell(row.sml_ratio, 'sml_ratio') },
                        { label: 'NPL', align: 'end', cell: (row) => h(row.npl.formatted) },
                        { label: 'Rasio NPL', align: 'end', cell: (row) => ratioCell(row.npl_ratio, 'npl_ratio') },
                        { label: 'NPL MtD', align: 'end', cell: (row) => (row.npl_ratio.deltas.mtd ? `<span class="sg-pp is-${row.npl_ratio.deltas.mtd.value > 0 ? 'down' : 'up'}">${h(row.npl_ratio.deltas.mtd.formatted)}</span>` : '-') },
                    ], rows: segment.segments, footer: total }) })}
                    ${tile({ wide: true, title: 'Per produk', body: table({ caption: 'Kualitas per produk', columns: [
                        { label: 'Produk', cell: (row) => `${row.group ? `<small class="sg-group">${h(row.group)}</small>` : ''}${h(row.label)}` },
                        { label: 'Outstanding', align: 'end', cell: (row) => h(row.os.formatted) },
                        { label: 'SML', align: 'end', cell: (row) => h(row.sml.formatted) },
                        { label: 'Rasio SML', align: 'end', cell: (row) => ratioCell(row.sml_ratio, 'sml_ratio') },
                        { label: 'NPL', align: 'end', cell: (row) => h(row.npl.formatted) },
                        { label: 'Rasio NPL', align: 'end', cell: (row) => ratioCell(row.npl_ratio, 'npl_ratio') },
                    ], rows: productRows }) })}
                </div>` })}
                ${performanceBlock('kualitas', 'trend', { title: 'Timeseries Kualitas', body: timeseries({ scrollable: true, title: 'Rasio SML dan NPL', labels: data.timeseries_labels, unit: 'percent', series: segment.timeseries.series.map((item) => ({ ...item, color: item.key.startsWith('sml') ? COLORS.sml : COLORS.npl })) }) })}
                ${performanceBlock('kualitas', 'entities', { title: `Setiap ${word}`, lede: `Diurutkan dari rasio NPL tertinggi. MtD: rasio posisi ${data.compare.mtd.label}; saat ini: ${data.period_label}.`, body: `<div class="sg-grid sg-grid--1">${tile({ wide: true, body: table({ caption: `Kualitas per ${word}`, columns: [
                    { label: word === 'cabang' ? 'Cabang' : 'Unit kerja', cell: (row) => h(row.label) },
                    { label: 'SML', align: 'end', cell: (row) => h(row.sml.formatted) },
                    { label: 'Rasio SML · MtD', align: 'end', cell: row => h(row.sml_ratio.previous?.mtd?.formatted || '-') },
                    { label: 'Rasio SML · Saat ini', align: 'end', cell: (row) => ratioCell(row.sml_ratio, 'sml_ratio') },
                    { label: 'NPL', align: 'end', cell: (row) => h(row.npl.formatted) },
                    { label: 'Rasio NPL · MtD', align: 'end', cell: row => h(row.npl_ratio.previous?.mtd?.formatted || '-') },
                    { label: 'Rasio NPL · Saat ini', align: 'end', cell: (row) => ratioCell(row.npl_ratio, 'npl_ratio') },
                ], rows: [...segment.rows].filter((row) => row.os.value > 0).sort((a, b) => b.npl_ratio.value - a.npl_ratio.value) }) })}</div>` })}
            `,
        }),
        territory(studio) {
            const data = ctx.overviewSegments || ctx.segments;
            const segment = data?.kualitas;
            if (!data?.available || !segment) return neutralTerritory(ctx, 'kualitas', 1)(studio);
            const isArea = data.scope.level === 'area';
            const rows = isArea ? segment.rows : [{ ...segment.total, key: `kc-${data.scope.key}`, label: data.scope.label }];
            const codes = isArea ? ctx.index.entityCodes(data.entities, true, data.scope.key) : new Map([[rows[0].key, ctx.index.scopeCodes(data.scope.key)]]);
            const lookup = new Map(rows.map((row) => [row.key, row]));
            const ratioOf = (key) => lookup.get(key)?.[`${qualityMetric}_ratio`].value ?? null;
            // Each KC is one source ratio. Never add outlet ratios in a shared district.
            const byDistrict = new Map();
            codes.forEach((districts, entityKey) => {
                const value = ratioOf(entityKey);
                if (value !== null && Number.isFinite(Number(value))) districts.forEach(code => byDistrict.set(code, Number(value)));
            });
            const max = Math.max(1e-9, ...byDistrict.values());
            const heights = (code) => {
                const value = byDistrict.get(code);
                return { lift: 0.62, color: qualityMetric === 'sml' ? 0xff9a2e : 0xff3b30, roughness: 0.12, core: value === undefined ? 0 : 0.14 + (value / max) * 0.72 };
            };
            const ranked = rows.filter((row) => codes.has(row.key) && row.os.value > 0).sort((a, b) => ratioOf(b.key) - ratioOf(a.key)).slice(0, isArea ? 4 : 5);
            const riskiest = isArea && ranked[0] ? ranked[0].key.replace(/^kc-/, '') : null;
            studio.register('kualitas', {
                journey: [{ until: 1, focus: ctx.index.scopeCodes(data.scope.key), heights, labels: ranked.map((row) => ({ id: `q-${row.key}`, codes: codes.get(row.key), title: row.label.replace(' (kantor cabang)', ''), value: `${qualityName} ${row[`${qualityMetric}_ratio`].formatted}` })) }],
                glass: 1,
                dark: 1,
                fx: { beams: true, flow: 0, speed: 2.1, color: qualityMetric === 'sml' ? 0xff9a2e : 0xff3b30 },
                describe: (code) => {
                    const name = ctx.index.nameOf(code);
                    const row = rows.find((item) => codes.get(item.key)?.has(code));
                    return `<small>${h(name.regency)}</small><strong>${h(name.district)}</strong>${row ? `<em>${h(row.label)}: NPL ${h(row.npl_ratio.formatted)}, SML ${h(row.sml_ratio.formatted)}</em>` : ''}`;
                },
                pick: (code) => {
                    const row = rows.find((item) => codes.get(item.key)?.has(code));
                    return row ? codes.get(row.key) : null;
                },
                arrangement: (p, still) => ({ explode: 0, spread: 0, levitate: still ? 0 : Math.min(1, Math.max(0, (p - 0.2) / 0.35)), levitateBranch: riskiest, grow: 1, wave: 0 }),
            });
        },
    };
};

/* ------------------------------------------------------------------ recovery -- */

export const recovery = (ctx) => {
    const segment = ctx.segments?.recovery;
    const data = ctx.segments && { ...ctx.segments,
        period: segment?.source?.period || ctx.segments.period,
        period_label: segment?.source?.period_label || ctx.segments.period_label,
        compare: segment?.compare || ctx.segments.compare,
        timeseries_labels: segment?.timeseries_labels || ctx.segments.timeseries_labels };
    if (!data?.available || !segment) return emptyPerformance(ctx, 'recovery', 'Recovery DH.', 'Snapshot belum tersedia.');
    const rows = segment.rows;
    const lookup = rowsByKey(rows);
    const word = entityWord(data);
    const segments = segment.segments.filter((item) => item.value !== 0 || segment.segments.length <= 3);
    if (segment.total.value === null || segment.total.value === undefined) {
        return emptyPerformance(ctx, 'recovery', 'Recovery daftar hitam.', 'Nilai recovery DH belum tersedia pada sumber untuk posisi ini.', 'Data recovery DH belum tersedia untuk cakupan ini.');
    }
    if (segment.total.value === 0) {
        return emptyPerformance(ctx, 'recovery', 'Recovery daftar hitam.', 'Belum ada recovery DH tercatat pada posisi ini.', 'Recovery DH bernilai nol untuk cakupan ini.');
    }

    return {
        html: performanceChapter(ctx, {
            key: 'recovery',
            group: 'Kinerja · Recovery DH',
            title: 'Yang berhasil kita pulihkan.',
            figure: { value: segment.total.formatted, label: `Recovery DH ${data.scope.label}, ${data.period_label}` },
            chips: deltaChips(segment.total.deltas, data.compare),
            lede: `Recovery daftar hitam per segmen dan setiap ${word}.`,
            note: `${segment.source?.label || 'Data PH'}, posisi ${data.period_label}. ${segment.source?.note || ''}`,
            ground: 'light',
            anchor: 'lead',
            pose: 'lead',
            sheet: `
                <p class="sg-note sg-recovery-source">Sumber: ${h(segment.source?.label || 'Data PH')}, posisi ${h(data.period_label)}. Pembanding aktual: ${h(comparisonNote(data))}. ${h(segment.source?.note || '')}</p>
                ${specs([
                    { label: 'Recovery DH', value: segment.total.formatted },
                    ...segments.map((item) => ({ label: item.label, value: item.formatted, note: item.share ? `${item.share.formatted} dari total` : '' })),
                ])}
                ${performanceBlock('recovery', 'composition', { title: 'Per segmen', body: `<div class="sg-grid sg-grid--2">
                    ${tile({ title: 'Komposisi', meta: data.period_label, body: donut({ caption: 'Recovery per segmen', center: { value: segment.total.formatted, label: 'recovery DH' }, items: segments.map((item) => ({ key: item.key, label: item.label, value: item.value, formatted: item.formatted, color: COLORS[item.key] || '#16a34a', note: item.deltas.mtd ? `MtD ${item.deltas.mtd.formatted}` : '' })) }) })}
                    ${tile({ title: `Peringkat ${word}`, meta: 'Recovery terbesar', body: rods([...rows].filter((row) => rowValue(row, 'total') > 0).sort((a, b) => rowValue(b, 'total') - rowValue(a, 'total')).slice(0, 10).map((row) => ({ label: row.label.replace(' (kantor cabang)', ''), value: rowValue(row, 'total'), formatted: rowText(row, 'total'), note: row.values.total.mtd ? `MtD ${row.values.total.mtd.formatted}` : '', color: '#16a34a' }))) })}
                </div>` })}
                ${performanceBlock('recovery', 'trend', { title: 'Timeseries Recovery DH', body: timeseries({ scrollable: true, title: 'Recovery DH per bulan', labels: data.timeseries_labels, series: segment.timeseries.series.map((item) => ({ ...item, color: item.key === 'total' ? '#16a34a' : COLORS[item.key] })), shown: ['total'], note: 'Posisi akhir setiap bulan. Pilih segmen di legenda.' }) })}
                ${performanceBlock('recovery', 'entities', { title: `Setiap ${word}`, body: `<div class="sg-grid sg-grid--1">${tile({ wide: true, body: table({
                    caption: `Recovery per ${word}`,
                    columns: [
                        { label: word === 'cabang' ? 'Cabang' : 'Unit kerja', cell: (row) => h(row.label) },
                        { label: 'Recovery', align: 'end', cell: (row) => `<strong>${h(rowText(row, 'total'))}</strong>` },
                        ...segments.map((item) => ({ label: item.label, align: 'end', cell: (row) => h(rowText(row, item.key)) })),
                        { label: 'Porsi', align: 'end', cell: (row) => `${meter(row.values.total.share, { color: '#16a34a' })}<small>${percent(row.values.total.share)}</small>` },
                        { label: 'MtD', align: 'end', cell: (row) => mtdCell(row.values.total) },
                    ],
                    rows: [...rows].sort((a, b) => rowValue(b, 'total') - rowValue(a, 'total')),
                }) })}</div>` })}
            `,
        }),
        territory: moneyTerritory(ctx, 'recovery', (key) => rowValue(lookup.get(key), 'total'), (key) => rowText(lookup.get(key), 'total'), { color: RELIEF.recovery, fx: { color: 0x16a34a, flow: -1 } }),
    };
};

