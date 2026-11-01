import test from 'node:test';
import assert from 'node:assert/strict';
import { simpanan } from '../../resources/js/presentation/segments/render-kinerja.js';

const money = (value, formatted = `Rp${value}`) => ({ value, formatted });
const delta = (value, formatted) => ({ value, formatted });
const context = entityLevel => {
    const rows = [
        { key: 'a', label: 'Kantor A', values: { casa: { ...money(300), deltas: { yoy: delta(25, '+Rp25'), ytd: delta(-17, '-Rp17'), mtd: delta(0, 'Rp0') } } } },
        { key: 'b', label: 'Kantor B', values: { casa: { ...money(200), deltas: { yoy: null, ytd: null, mtd: null } } } },
        { key: 'c', label: 'Kantor C', values: { casa: { ...money(100), mtd: delta(11, '+Rp11') } } },
    ];
    for (const row of rows) {
        row.values.total = { ...money(500), share: 100 / 3, deltas: { yoy: delta(999, '+Rp999'), ytd: delta(888, '+Rp888'), mtd: delta(777, '+Rp777') } };
        row.values.giro = money(50);
        row.values.tabungan = money(row.values.casa.value - 50);
    }
    const total = { ...money(1500), deltas: {} };
    const scope = { key: entityLevel === 'branch' ? 'area6' : 'madiun', label: entityLevel === 'branch' ? 'Area 6 Konsol' : 'KC Madiun', entity_level: entityLevel, level: entityLevel === 'branch' ? 'area' : 'branch' };
    return { scope, segments: { available: true, scope, period_label: '26 Okt 2026', entities: [],
        compare: { mtd: { title: 'MtD', label: '30 Sep 2026' }, ytd: { title: 'YtD', label: '31 Des 2025' }, yoy: { title: 'YoY', label: '26 Okt 2025' } },
        timeseries_labels: ['Sep 26', 'Okt 26'],
        simpanan: { total, casa: money(600), products: [], segments: [], rows, timeseries: { series: [{ key: 'total', label: 'Total Simpanan', values: [1400, 1500] }] } },
    } };
};
const cells = row => [...row.matchAll(/<t[hd]\b[^>]*>([\s\S]*?)<\/t[hd]>/g)].map(match => match[1].replace(/<[^>]+>/g, '').trim());

for (const entityLevel of ['branch', 'unit']) test(`CASA ${entityLevel} growth preserves its own signed, zero and unavailable comparisons`, () => {
    const ctx = context(entityLevel);
    const before = structuredClone(ctx);
    const html = simpanan(ctx).html;
    const section = html.match(/id="performance-simpanan-casa"[\s\S]*?<\/section>/)?.[0];
    assert.ok(section);
    const rows = [...section.matchAll(/<tr\b[^>]*>([\s\S]*?)<\/tr>/g)].map(match => cells(match[1]));
    assert.deepEqual(rows[0], [entityLevel === 'branch' ? 'Cabang' : 'Unit kerja', 'Giro', 'Tabungan', 'CASA', 'Porsi CASA', 'YoY', 'YtD', 'MtD']);
    assert.deepEqual(rows.slice(1).map(row => [row[0], ...row.slice(5)]), [
        ['Kantor A', '+Rp25', '-Rp17', 'Rp0'],
        ['Kantor B', '-', '-', '-'],
        ['Kantor C', '-', '-', '+Rp11'],
    ]);
    assert.ok(section.includes('is-up">+Rp25'));
    assert.ok(section.includes('is-down">-Rp17'));
    for (const period of Object.values(ctx.segments.compare)) assert.ok(section.includes(`${period.title}: ${period.label}`));
    assert.ok(!/Rp999|Rp888|Rp777/.test(section), 'CASA must not use total-savings growth');
    assert.deepEqual(ctx, before, 'Rendering preserves the source payload');
});
