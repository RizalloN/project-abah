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

const deltaChips = (deltas, compare, options = {}) => ['dtd', 'mtd', 'ytd', 'yoy']
    .map((key) => deltaChip(compare?.[key]?.title || key.toUpperCase(), deltas?.[key], options))
    .join('');

const entityWord = (data) => (data.scope.entity_level === 'branch' ? 'cabang' : 'unit');

/** Relief state shared by the money segments: height per branch or outlet, labels on the largest. */
const moneyTerritory = (ctx, key, valueOf, formattedOf, { color, dark = 0, fx = {} } = {}) => (studio) => {
    const data = ctx.segments;
    const isArea = data.scope.level === 'area';
    const codes = ctx.index.entityCodes(data.entities, isArea, data.scope.key);
    const byDistrict = ctx.index.entityValuesByDistrict(codes, valueOf);
    const max = Math.max(1e-9, ...byDistrict.values());
    const heights = (code) => {
        const value = byDistrict.get(code);
        return value === undefined ? { lift: 0.03, color: dark ? 0x3a3c42 : 0xc3c6cc, roughness: 0.4 } : { lift: 0.1 + (value / max) * 0.9, color, roughness: 0.28 };
    };
    const ranked = data.entities.filter((entity) => codes.has(entity.key)).sort((a, b) => valueOf(b.key) - valueOf(a.key)).slice(0, isArea ? 4 : 5);
    const labels = ranked.map((entity) => ({ id: `${key}-${entity.key}`, codes: codes.get(entity.key), title: entity.label.replace(' (kantor cabang)', ''), value: formattedOf(entity.key) }));
    const focus = ctx.index.scopeCodes(data.scope.key);
    const entityOfCode = (code) => data.entities.find((entity) => codes.get(entity.key)?.has(code));
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
            const entity = data.entities.find((item) => codes.get(item.key) === focusCodes);
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

/* ------------------------------------------------------------------ simpanan -- */

export const simpanan = (ctx) => {
    const data = ctx.segments;
    const segment = data?.simpanan;
    if (!data?.available || !segment) return { html: chapter({ key: 'simpanan', group: 'Kinerja', title: 'Kinerja simpanan.', lede: 'Snapshot belum tersedia.', territory: false, span: 120 }) };
    const rows = segment.rows;
    const lookup = rowsByKey(rows);
    const word = entityWord(data);
    const casaRatio = segment.casa?.share;

    return {
        html: chapter({
            key: 'simpanan',
            group: 'Kinerja · Simpanan',
            title: 'Dana yang kita himpun.',
            figure: { value: segment.total.formatted, label: `Total simpanan ${data.scope.label}, ${data.period_label}` },
            chips: deltaChips(segment.total.deltas, data.compare),
            lede: `Per produk dan per segmen, lalu setiap ${word} dan arahnya dua belas bulan terakhir.`,
            note: `Tinggi setiap wilayah adalah simpanan ${word}nya.`,
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
                ${block({ title: 'Komposisi', body: `<div class="sg-grid sg-grid--2">
                    ${tile({ title: 'Per produk', meta: data.period_label, body: donut({ caption: 'Simpanan per produk', center: { value: segment.total.formatted, label: 'total simpanan' }, items: segment.products.map((item) => ({ key: item.key, label: item.label, value: item.value, formatted: item.formatted, color: COLORS[item.key], note: item.deltas.mtd ? `MtD ${item.deltas.mtd.formatted}` : '' })) }) })}
                    ${tile({ title: 'Per segmen', meta: 'Ritel, Mikro, Wholesale', body: donut({ caption: 'Simpanan per segmen', center: { value: segment.segments.find((item) => item.key === 'mikro')?.share?.formatted || '-', label: 'porsi mikro' }, items: segment.segments.map((item) => ({ key: item.key, label: item.label, value: item.value, formatted: item.formatted, color: COLORS[item.key], note: item.deltas.mtd ? `MtD ${item.deltas.mtd.formatted}` : '' })) }) })}
                </div>` })}
                ${block({ title: 'Dua belas bulan', body: timeseries({ title: 'Simpanan per bulan', labels: data.timeseries_labels, series: segment.timeseries.series.map((item) => ({ ...item, color: COLORS[item.key] })), shown: ['total'], note: 'Posisi akhir setiap bulan; bulan berjalan memakai posisi terakhir. Pilih seri lain di legenda.' }) })}
                ${block({ title: `Setiap ${word}`, lede: `${rows.length} ${word}, diurutkan dari simpanan terbesar.`, body: `<div class="sg-grid sg-grid--1">${tile({ wide: true, body: table({
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
                        { label: 'MtD', align: 'end', cell: (row) => mtdCell(row.values.total) },
                    ],
                    rows: [...rows].sort((a, b) => rowValue(b, 'total') - rowValue(a, 'total')),
                }) })}${tile({ title: 'Pertumbuhan bulan ini', meta: `MtD terhadap ${data.compare.mtd.label}`, body: growthRods(rows, 'total') })}</div>` })}
            `,
        }),
        territory: moneyTerritory(ctx, 'simpanan', (key) => rowValue(lookup.get(key), 'total'), (key) => rowText(lookup.get(key), 'total'), { color: RELIEF.simpanan, fx: { flow: -1 } }),
    };
};

/* ------------------------------------------------------------------ pinjaman -- */

export const pinjaman = (ctx) => {
    const data = ctx.segments;
    const segment = data?.pinjaman;
    if (!data?.available || !segment) return { html: chapter({ key: 'pinjaman', group: 'Kinerja', title: 'Kinerja pinjaman.', lede: 'Snapshot belum tersedia.', territory: false, span: 120 }) };
    const rows = segment.rows;
    const lookup = rowsByKey(rows);
    const word = entityWord(data);

    const productTiles = segment.products.filter((group) => group.products.length).map((group) => tile({
        title: `${group.label} per produk`,
        meta: segment.segments.find((item) => item.key === group.key)?.formatted || '',
        body: rods(group.products.map((product) => ({ label: product.label, value: product.value, formatted: product.formatted, note: `${product.share.formatted} dari pinjaman${product.deltas.mtd ? ` · MtD ${product.deltas.mtd.formatted}` : ''}`, color: COLORS[group.key] }))),
    })).join('');

    return {
        html: chapter({
            key: 'pinjaman',
            group: 'Kinerja · Pinjaman',
            title: 'Kredit yang kita salurkan.',
            figure: { value: segment.total.formatted, label: `Outstanding ${data.scope.label}, ${data.period_label}` },
            chips: deltaChips(segment.total.deltas, data.compare),
            lede: `SME, Konsumer, dan Mikro beserta setiap produknya, lalu setiap ${word}.`,
            note: `Tinggi setiap wilayah adalah outstanding ${word}nya.`,
            ground: 'light',
            anchor: 'lead',
            pose: 'lead',
            sheet: `
                ${specs([
                    { label: 'Outstanding', value: segment.total.formatted },
                    ...segment.segments.map((item) => ({ label: item.label, value: item.formatted, note: `${item.share.formatted} dari OS` })),
                ])}
                ${block({ title: 'Per segmen dan produk', body: `<div class="sg-grid sg-grid--2">
                    ${tile({ title: 'Per segmen', meta: data.period_label, body: donut({ caption: 'Pinjaman per segmen', center: { value: segment.total.formatted, label: 'outstanding' }, items: segment.segments.map((item) => ({ key: item.key, label: item.label, value: item.value, formatted: item.formatted, color: COLORS[item.key], note: item.deltas.mtd ? `MtD ${item.deltas.mtd.formatted}` : '' })) }) })}
                    ${productTiles}
                </div>` })}
                ${block({ title: 'Dua belas bulan', body: timeseries({ title: 'Outstanding per bulan', labels: data.timeseries_labels, series: segment.timeseries.series.map((item) => ({ ...item, color: COLORS[item.key] })), shown: ['total'], note: 'Posisi akhir setiap bulan, non-komersial. Pilih segmen di legenda.' }) })}
                ${block({ title: `Setiap ${word}`, lede: `${rows.length} ${word}, diurutkan dari outstanding terbesar.`, body: `<div class="sg-grid sg-grid--1">${tile({ wide: true, body: table({
                    caption: `Pinjaman per ${word}`,
                    columns: [
                        { label: word === 'cabang' ? 'Cabang' : 'Unit kerja', cell: (row) => h(row.label) },
                        { label: 'Outstanding', align: 'end', cell: (row) => `<strong>${h(rowText(row, 'total'))}</strong>` },
                        { label: 'SME', align: 'end', cell: (row) => h(rowText(row, 'sme')) },
                        { label: 'Konsumer', align: 'end', cell: (row) => h(rowText(row, 'konsumer')) },
                        { label: 'Mikro', align: 'end', cell: (row) => h(rowText(row, 'mikro')) },
                        { label: 'Porsi', align: 'end', cell: (row) => `${meter(row.values.total.share)}<small>${percent(row.values.total.share)}</small>` },
                        { label: 'MtD', align: 'end', cell: (row) => mtdCell(row.values.total) },
                    ],
                    rows: [...rows].sort((a, b) => rowValue(b, 'total') - rowValue(a, 'total')),
                }) })}${tile({ title: 'Pertumbuhan bulan ini', meta: `MtD terhadap ${data.compare.mtd.label}`, body: growthRods(rows, 'total') })}</div>` })}
            `,
        }),
        territory: moneyTerritory(ctx, 'pinjaman', (key) => rowValue(lookup.get(key), 'total'), (key) => rowText(lookup.get(key), 'total'), { color: RELIEF.pinjaman, fx: { flow: 1, color: 0x4a78e8 } }),
    };
};

/* ------------------------------------------------------------------ kualitas -- */

export const kualitas = (ctx) => {
    const data = ctx.segments;
    const segment = data?.kualitas;
    if (!data?.available || !segment) return { html: chapter({ key: 'kualitas', group: 'Kinerja', title: 'Kualitas kredit.', lede: 'Snapshot belum tersedia.', territory: false, span: 120 }) };
    const word = entityWord(data);
    const total = segment.total;
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
        html: chapter({
            key: 'kualitas',
            group: 'Kinerja · Kualitas',
            title: 'Di mana risiko berkumpul.',
            figure: { value: total.npl_ratio.formatted, label: `Rasio NPL ${data.scope.label}, NPL ${total.npl.formatted}` },
            chips: `<span class="sg-delta is-${total.sml_ratio.value > 5 ? 'down' : 'flat'}"><small>SML</small>${h(total.sml_ratio.formatted)}</span>${deltaChips(total.npl_ratio.deltas, data.compare, { invert: true })}`,
            lede: `SML dan NPL per segmen dan produk, lalu ${word} dengan rasio tertinggi.`,
            note: `Cahaya di dalam setiap wilayah setinggi rasio NPL ${word}nya.`,
            ground: 'dark',
            anchor: 'trail',
            pose: 'trail',
            span: 260,
            sheet: `
                ${block({ title: 'Rasio terhadap outstanding', body: `<div class="sg-grid sg-grid--2">
                    ${tile({ title: 'Special Mention Loan', meta: `MtD ${total.sml_ratio.deltas.mtd?.formatted || '-'} · YtD ${total.sml_ratio.deltas.ytd?.formatted || '-'}`, body: gauge('sml', 'SML') })}
                    ${tile({ title: 'Non-Performing Loan', meta: `MtD ${total.npl_ratio.deltas.mtd?.formatted || '-'} · YtD ${total.npl_ratio.deltas.ytd?.formatted || '-'}`, body: gauge('npl', 'NPL') })}
                </div>` })}
                ${block({ title: 'Per segmen dan produk', body: `<div class="sg-grid sg-grid--1">
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
                ${block({ title: 'Dua belas bulan', body: timeseries({ title: 'Rasio SML dan NPL', labels: data.timeseries_labels, unit: 'percent', series: segment.timeseries.series.map((item) => ({ ...item, color: item.key.startsWith('sml') ? COLORS.sml : COLORS.npl })) }) })}
                ${block({ title: `Setiap ${word}`, lede: 'Diurutkan dari rasio NPL tertinggi.', body: `<div class="sg-grid sg-grid--1">${tile({ wide: true, body: table({ caption: `Kualitas per ${word}`, columns: [
                    { label: word === 'cabang' ? 'Cabang' : 'Unit kerja', cell: (row) => h(row.label) },
                    { label: 'Outstanding', align: 'end', cell: (row) => h(row.os.formatted) },
                    { label: 'SML', align: 'end', cell: (row) => h(row.sml.formatted) },
                    { label: 'Rasio SML', align: 'end', cell: (row) => ratioCell(row.sml_ratio, 'sml_ratio') },
                    { label: 'NPL', align: 'end', cell: (row) => h(row.npl.formatted) },
                    { label: 'Rasio NPL', align: 'end', cell: (row) => ratioCell(row.npl_ratio, 'npl_ratio') },
                ], rows: [...segment.rows].filter((row) => row.os.value > 0).sort((a, b) => b.npl_ratio.value - a.npl_ratio.value) }) })}</div>` })}
            `,
        }),
        territory(studio) {
            const isArea = data.scope.level === 'area';
            const codes = ctx.index.entityCodes(data.entities, isArea, data.scope.key);
            const lookup = new Map(segment.rows.map((row) => [row.key, row]));
            const ratioOf = (key) => lookup.get(key)?.npl_ratio.value ?? null;
            // A ratio is not split over kecamatan the way money is: every kecamatan of the entity carries the whole ratio.
            const byDistrict = ctx.index.entityValuesByDistrict(codes, (key) => (ratioOf(key) ?? Number.NaN) * (codes.get(key)?.size || 1));
            const max = Math.max(1e-9, ...byDistrict.values());
            const heights = (code) => {
                const value = byDistrict.get(code);
                return { lift: 0.62, color: 0xff3b30, roughness: 0.12, core: value === undefined ? 0 : 0.14 + (value / max) * 0.72 };
            };
            const ranked = segment.rows.filter((row) => codes.has(row.key) && row.os.value > 0).sort((a, b) => b.npl_ratio.value - a.npl_ratio.value).slice(0, isArea ? 4 : 5);
            const riskiest = isArea && ranked[0] ? ranked[0].key.replace(/^kc-/, '') : null;
            studio.register('kualitas', {
                journey: [{ until: 1, focus: ctx.index.scopeCodes(data.scope.key), heights, labels: ranked.map((row) => ({ id: `q-${row.key}`, codes: codes.get(row.key), title: row.label.replace(' (kantor cabang)', ''), value: `NPL ${row.npl_ratio.formatted}` })) }],
                glass: 1,
                dark: 1,
                fx: { beams: true, flow: 0, speed: 2.1, color: 0xff3b30 },
                describe: (code) => {
                    const name = ctx.index.nameOf(code);
                    const row = segment.rows.find((item) => codes.get(item.key)?.has(code));
                    return `<small>${h(name.regency)}</small><strong>${h(name.district)}</strong>${row ? `<em>${h(row.label)}: NPL ${h(row.npl_ratio.formatted)}, SML ${h(row.sml_ratio.formatted)}</em>` : ''}`;
                },
                pick: (code) => {
                    const row = segment.rows.find((item) => codes.get(item.key)?.has(code));
                    return row ? codes.get(row.key) : null;
                },
                arrangement: (p, still) => ({ explode: 0, spread: 0, levitate: still ? 0 : Math.min(1, Math.max(0, (p - 0.2) / 0.35)), levitateBranch: riskiest, grow: 1, wave: 0 }),
            });
        },
    };
};

/* ------------------------------------------------------------------ recovery -- */

export const recovery = (ctx) => {
    const data = ctx.segments;
    const segment = data?.recovery;
    if (!data?.available || !segment) return { html: chapter({ key: 'recovery', group: 'Kinerja', title: 'Recovery DH.', lede: 'Snapshot belum tersedia.', territory: false, span: 120 }) };
    const rows = segment.rows;
    const lookup = rowsByKey(rows);
    const word = entityWord(data);
    const segments = segment.segments.filter((item) => item.value !== 0 || segment.segments.length <= 3);
    if (segment.total.value === null || segment.total.value === undefined) {
        return { html: chapter({ key: 'recovery', group: 'Kinerja · Recovery DH', title: 'Recovery daftar hitam.', lede: 'Nilai recovery DH belum tersedia pada sumber untuk posisi ini.', territory: false, span: 140, sheet: empty('Data recovery DH belum tersedia untuk cakupan ini.') }) };
    }
    if (segment.total.value === 0) {
        return { html: chapter({ key: 'recovery', group: 'Kinerja · Recovery DH', title: 'Recovery daftar hitam.', lede: 'Belum ada recovery DH tercatat pada posisi ini.', territory: false, span: 140, sheet: empty('Recovery DH bernilai nol untuk cakupan ini.') }) };
    }

    return {
        html: chapter({
            key: 'recovery',
            group: 'Kinerja · Recovery DH',
            title: 'Yang berhasil kita pulihkan.',
            figure: { value: segment.total.formatted, label: `Recovery DH ${data.scope.label}, ${data.period_label}` },
            chips: deltaChips(segment.total.deltas, data.compare),
            lede: `Recovery daftar hitam per segmen dan setiap ${word}.`,
            note: `Tinggi setiap wilayah adalah recovery ${word}nya.`,
            ground: 'light',
            anchor: 'lead',
            pose: 'lead',
            sheet: `
                ${specs([
                    { label: 'Recovery DH', value: segment.total.formatted },
                    ...segments.map((item) => ({ label: item.label, value: item.formatted, note: item.share ? `${item.share.formatted} dari total` : '' })),
                ])}
                ${block({ title: 'Per segmen', body: `<div class="sg-grid sg-grid--2">
                    ${tile({ title: 'Komposisi', meta: data.period_label, body: donut({ caption: 'Recovery per segmen', center: { value: segment.total.formatted, label: 'recovery DH' }, items: segments.map((item) => ({ key: item.key, label: item.label, value: item.value, formatted: item.formatted, color: COLORS[item.key] || '#16a34a', note: item.deltas.mtd ? `MtD ${item.deltas.mtd.formatted}` : '' })) }) })}
                    ${tile({ title: `Peringkat ${word}`, meta: 'Recovery terbesar', body: rods([...rows].filter((row) => rowValue(row, 'total') > 0).sort((a, b) => rowValue(b, 'total') - rowValue(a, 'total')).slice(0, 10).map((row) => ({ label: row.label.replace(' (kantor cabang)', ''), value: rowValue(row, 'total'), formatted: rowText(row, 'total'), note: row.values.total.mtd ? `MtD ${row.values.total.mtd.formatted}` : '', color: '#16a34a' }))) })}
                </div>` })}
                ${block({ title: 'Dua belas bulan', body: timeseries({ title: 'Recovery DH per bulan', labels: data.timeseries_labels, series: segment.timeseries.series.map((item) => ({ ...item, color: item.key === 'total' ? '#16a34a' : COLORS[item.key] })), shown: ['total'], note: 'Posisi akhir setiap bulan. Pilih segmen di legenda.' }) })}
                ${block({ title: `Setiap ${word}`, body: `<div class="sg-grid sg-grid--1">${tile({ wide: true, body: table({
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

