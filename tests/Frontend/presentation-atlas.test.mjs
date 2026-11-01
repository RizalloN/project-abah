import test from 'node:test';
import assert from 'node:assert/strict';
import { vessels, slope, coverage, opportunity, opportunityRanks, skyline, ledger } from '../../resources/js/presentation/segments/atlas.js';

const attr = (html, name) => [...html.matchAll(new RegExp(`${name}:([\\d.]+)`, 'g'))].map((match) => Number(match[1]));

test('a vessel is as tall as its whole and filled to the share, capped at full with an overflow mark', () => {
    const html = vessels({ items: [
        { key: 'a', label: 'A', total: 200, part: 50, ratio: 25, ratioLabel: '25%' },
        { key: 'b', label: 'B', total: 100, part: 130, ratio: 130, ratioLabel: '130%' },
    ] });
    assert.deepEqual(attr(html, '--h'), [1, 0.5]);
    assert.deepEqual(attr(html, '--f'), [0.25, 1]);
    assert.match(html, /sg-vessel is-over/);
    assert.match(html, /130%/);
});

test('slope keeps only products with both periods and prints the source labels', () => {
    const html = slope({ from: 'Dec-25', to: 'Mei-26', items: [
        { key: 'a', label: 'Giro', prev: 8.4, now: 12.5, prevLabel: '8.4%', nowLabel: '12.5%', deltaLabel: '+4.02 pp' },
        { key: 'b', label: 'Kosong', prev: null, now: 3, prevLabel: '-', nowLabel: '3%', deltaLabel: '' },
    ] });
    assert.match(html, /Giro/);
    assert.match(html, /\+4\.02 pp/);
    assert.doesNotMatch(html, /Kosong/);
    assert.equal(slope({ items: [], from: 'a', to: 'b' }), '');
});

test('coverage splits potential into customers and the untapped remainder', () => {
    const html = coverage({ potential: 1000, existing: 250 });
    assert.deepEqual(attr(html, '--f'), [0.25]);
    assert.match(html, />750</);
    assert.match(html, />1\.000</);
});

test('opportunity keeps the priority quadrant separate from its source-ranked opposite tails', () => {
    const rows = [
        { key: 'big-thin', name: 'Big thin', potential: 1000, existing: 100, penetration: 10, gap: 900 },
        { key: 'big-thick', name: 'Big thick', potential: 900, existing: 800, penetration: 88.9, gap: 100 },
        { key: 'small-thin', name: 'Small thin', potential: 50, existing: 5, penetration: 10, gap: 45 },
        { key: 'mid-thin', name: 'Mid thin', potential: 600, existing: 120, penetration: 20, gap: 480 },
    ];
    const html = opportunity({ rows, average: 50 });
    // The priority quadrant still marks only Big thin; the lists rank all eligible source units.
    const list = html.slice(html.indexOf('sg-opportunity__rankings'));
    assert.match(list, /Big thin/);
    assert.match(list, /Big thick|Small thin|Mid thin/);
    assert.equal((html.match(/sg-orb is-priority/g) || []).length, 1);
    assert.deepEqual(opportunityRanks(rows).largest.map(row => row.key), ['big-thin', 'mid-thin']);
    assert.deepEqual(opportunityRanks(rows).smallest.map(row => row.key), ['small-thin', 'big-thick']);
    assert.match(html, /tanpa mengulang unit/);
    assert.equal(opportunity({ rows: [], average: 50 }), '');
});

test('opportunity ranks ten actual largest and smallest gaps without repeating small scopes', () => {
    for (const size of [1, 2, 9, 12, 19, 20, 25]) {
        const rows = Array.from({ length: size }, (_, index) => ({ key: `u${index}`, name: `Unit ${index}`, sub: 'KC Madiun', potential: 1000, existing: 1000 - index, penetration: 100 - index / 10, gap: index }));
        const ranking = opportunityRanks(rows);
        const biggest = Math.min(10, Math.ceil(size / 2));
        const smallest = Math.min(10, size - biggest);
        assert.equal(ranking.count, size);
        assert.deepEqual(ranking.largest.map(row => row.gap), Array.from({ length: biggest }, (_, index) => size - 1 - index));
        assert.deepEqual(ranking.smallest.map(row => row.gap), Array.from({ length: smallest }, (_, index) => index));
        assert.equal(new Set([...ranking.largest, ...ranking.smallest].map(row => row.key)).size, biggest + smallest);
        assert.equal(rows[0].gap, 0, 'source order and values are preserved');
        const html = opportunity({ rows, average: 50, source: 'Sheet REKAP, updated 2026-10-25.' });
        assert.equal((html.match(/data-opportunity-ranking=/g) || []).length, 2);
        assert.equal((html.match(/data-orb-link=/g) || []).length, biggest + smallest);
        assert.match(html, /KC Madiun/);
        assert.match(html, /Sheet REKAP, updated 2026-10-25/);
    }
});

test('opportunity rankings preserve a real zero and omit unavailable gaps or potentials', () => {
    const rows = [
        { key: 'zero', name: 'Zero', potential: 0, gap: 0 },
        { key: 'missing', name: 'Missing', potential: 100, gap: null },
        { key: 'unavailable', name: 'Unavailable', potential: null, gap: 50 },
        { key: 'known', name: 'Known', potential: 100, gap: 60 },
    ];
    const ranking = opportunityRanks(rows);
    assert.deepEqual(ranking.largest.map(row => row.key), ['known']);
    assert.deepEqual(ranking.smallest.map(row => row.key), ['zero']);
});

test('opportunity exposes numeric potential ticks, percentage penetration and the bubble-size measure', () => {
    const html = opportunity({ rows: [
        { key: 'u1', name: 'Unit satu', potential: 12345, existing: 2469, penetration: 20, gap: 9876 },
        { key: 'u2', name: 'Unit dua', potential: 4000, existing: 2000, penetration: 50, gap: 2000 },
    ], average: 27.3 });
    assert.match(html, /Sumbu X <strong>Penetrasi nasabah BRI \(%\)/);
    assert.match(html, /Sumbu Y <strong>Potensi nasabah \(jumlah\)/);
    assert.match(html, /Ukuran bola: jumlah nasabah belum tergarap/);
    const axis = html.match(/class="sg-opportunity__y"[\s\S]*?<\/div>/)?.[0] || '';
    assert.match(axis, />0</);
    assert.match(axis, />5\.000</);
    assert.match(axis, />15\.000</);
    assert.match(html, /data-pot="12\.345"/);
    assert.match(html, /data-pen="20,0%"/);
});

test('skyline fills each prism by penetration and marks the chosen sector', () => {
    const html = skyline({ selected: 'b', sectors: [
        { key: 'a', label: 'A', color: '#000', potential: 100, existing: 50, penetration: 50 },
        { key: 'b', label: 'B', color: '#111', potential: 50, existing: 60, penetration: 120 },
    ] });
    assert.deepEqual(attr(html, '--f'), [0.5, 1]);
    assert.match(html, /aria-selected="true" data-sector="b"/);
    assert.match(html, /sg-prism is-over/);
});

test('ledger caps very large ratios on screen, keeps the exact value, and leaves no-potential cells empty', () => {
    const html = ledger({ selected: 'a', sectors: [{ key: 'a', label: 'A', color: '#000' }, { key: 'b', label: 'B', color: '#111' }], rows: [
        { label: 'Unit', cells: { a: { potential: 10, existing: 188, penetration: 1880 }, b: { potential: 0, existing: 0, penetration: null } } },
    ] });
    assert.match(html, /300%\+/);
    assert.match(html, /1\.880,0%/);
    assert.match(html, /class="is-empty"/);
});
