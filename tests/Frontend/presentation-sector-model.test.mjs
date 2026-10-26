import test from 'node:test';
import assert from 'node:assert/strict';
import { aggregateSectorValues, sectorView, sectorMetrics } from '../../resources/js/presentation/segments/sector-model.js';

const metric = (potential, existing) => ({ total: { potential, existing }, trade: { potential, existing } });
const payload = {
    ready: true, comparison_groups: [{ key: 'total' }, { key: 'trade', label: 'Perdagangan' }],
    branches: [
        { key: 'ngawi', label: 'KC Ngawi', values: metric(1000, 200) },
        { key: 'madiun', label: 'KC Madiun', values: metric(800, 300) },
    ],
    districts: [
        { code: 'A', branch: 'ngawi', name: 'Kecamatan A', values: metric(400, 75) },
        { code: 'B', branch: 'ngawi', name: 'Kecamatan B', values: metric(600, 125) },
        { code: 'C', branch: 'madiun', name: 'Kecamatan C', values: metric(800, 300) },
    ],
    villages: [
        { key: 'desa-a', district_code: 'A', source_values: { G: 40 } },
        { key: 'desa-b', district_code: 'B', source_values: { G: 60 } },
    ],
    units: [{ code: '01', name: 'Unit satu', branch: 'ngawi', district_codes: ['A', 'B'], village_keys: ['desa-a', 'desa-b'], values: metric(null, 150) }],
};
const ctx = (selection = null, scope = { key: 'area6', label: 'Area 6' }) => ({ wilayah: { sektoral: payload }, scope, selection });

test('branch/area ratios aggregate source amounts, not the average of row ratios', () => {
    const area = sectorView(ctx());
    assert.equal(area.totals().potential, 1800);
    assert.equal(area.totals().existing, 500);
    assert.equal(area.totals().penetration, 500 / 1800 * 100);
    const branch = sectorView(ctx({ level: 'branch', branchKey: 'ngawi', label: 'KC Ngawi' }));
    assert.equal(branch.totals().potential, 1000);
    assert.equal(branch.headLabel, 'UKER');
    assert.deepEqual([...branch.byDistrict('trade').keys()], ['A', 'B']);
});

test('a district uses actual district potential instead of whole serving-unit potential', () => {
    const view = sectorView(ctx({ level: 'district', branchKey: 'ngawi', districtCode: 'A', unitCodes: ['01'], label: 'Wilayah A' }));
    assert.equal(view.totals().potential, 400);
    assert.equal(view.totals().existing, 75);
    assert.equal(view.totals().penetration, 18.75);
    assert.equal(view.rows.length, 1);
});

test('unit map uses owned villages rather than all population in a shared district', () => {
    const data = { ...payload, comparison_groups: [{ key: 'total', categories: ['G'] }, { key: 'trade', categories: ['G'] }] };
    const view = sectorView({ ...ctx({ level: 'unit', branchKey: 'ngawi', unitCode: '01', unitCodes: ['01'], label: 'Unit satu' }), wilayah: { sektoral: data } });
    assert.equal(view.totals().potential, null);
    assert.equal(view.totals().existing, 150);
    assert.equal(view.totals().penetration, null);
    assert.equal(view.byDistrict('trade').get('A').existing, 75);
    assert.equal(view.byDistrict('trade').get('A').potential, 40);
    assert.equal(view.byDistrict('trade').get('A').penetration_raw, 187.5);
    assert.equal(view.byDistrict('trade').get('A').penetration, 100);
});

test('coverage is bounded at 100 while source counts and raw ratio are preserved', () => {
    const values = sectorMetrics(100, 141);
    assert.equal(values.potential, 100);
    assert.equal(values.existing, 141);
    assert.equal(values.penetration, 100);
    assert.equal(values.penetration_raw, 141);
    assert.equal(values.normalization_applied, true);
    assert.equal(values.penetration_excess, 41);
    assert.equal(sectorMetrics(0, 20).penetration, null);
});

test('overlapping service-area unit populations are never summed into the branch population', () => {
    const data = { ...payload, units: [
        { code: '01', name: 'Satu', branch: 'ngawi', district_codes: ['A', 'B'], values: metric(1000, 150) },
        { code: '02', name: 'Dua', branch: 'ngawi', district_codes: ['A', 'B'], values: metric(1000, 50) },
    ] };
    const branch = sectorView({ ...ctx({ level: 'branch', branchKey: 'ngawi' }), wilayah: { sektoral: data } });
    assert.equal(branch.totals().potential, 1000);
    assert.equal(branch.rows.length, 2);
    assert.equal(branch.headLabel, 'UKER');
    assert.equal(branch.rows.reduce((sum, row) => sum + row.values.total.potential, 0), 2000);
    const unit = sectorView({ ...ctx({ level: 'unit', branchKey: 'ngawi', unitCodes: ['01'], label: 'Unit Satu' }), wilayah: { sektoral: data } });
    assert.equal(unit.totals().potential, 1000);
    assert.equal(unit.totals().existing, 150);
    assert.equal(unit.totals().penetration, 15);
});

test('missing values do not become source zero and a narrower scope cannot expose another branch', () => {
    assert.equal(aggregateSectorValues([{ values: metric(0, 0) }]).potential, 0);
    assert.equal(aggregateSectorValues([{ values: metric(10, null) }]).existing, null);
    assert.equal(aggregateSectorValues([]).potential, null);
    const view = sectorView(ctx({ level: 'unit', branchKey: 'ngawi', unitCodes: ['01'], label: 'Unit satu' }, { key: 'madiun', label: 'KC Madiun' }));
    assert.deepEqual(view.units, []);
    assert.equal(view.totals().existing, null);
});

test('active and PH counts remain separate, including OTHER in the supplied total', () => {
    const result = aggregateSectorValues([
        { values: { total: { potential: 100, intra: 20, extra: 8, existing: 28 } } },
        { values: { total: { potential: 60, intra: 10, extra: 2, existing: 12 } } },
    ]);
    assert.equal(result.intra, 30);
    assert.equal(result.extra, 10);
    assert.equal(result.existing, 40);
    assert.equal(result.penetration, 25);
    const view = sectorView({ ...ctx(), wilayah: { sektoral: { ...payload, comparison_groups: [...payload.comparison_groups, { key: 'unclassified' }] } } });
    assert.deepEqual(view.groups.map((group) => group.key), ['trade']);
});
