import test from 'node:test';
import assert from 'node:assert/strict';
import { TerritoryIndex, withVillageTerritory } from '../../resources/js/presentation/segments/territory-data.js';
import { resolvePolygonSelection } from '../../resources/js/presentation/segments/polygon-selection.js';

const index = new TerritoryIndex([
    { properties: { KDCPUM: 'A', WADMKK: 'NGAWI', WADMKC: 'Kecamatan bersama' } },
    { properties: { KDCPUM: 'B', WADMKK: 'NGAWI', WADMKC: 'Kecamatan tunggal' } },
    { properties: { KDCPUM: 'C', WADMKK: 'MADIUN', WADMKC: 'Cabang lain' } },
], { ready: true, units: [
    { code: '001', name: 'Satu', branch: 'ngawi', district_codes: ['A'], values: {} },
    { code: '002', name: 'Dua', branch: 'ngawi', district_codes: ['A'], values: {} },
    { code: '003', name: 'Tiga', branch: 'ngawi', district_codes: ['B'], values: {} },
    { code: '004', name: 'Empat', branch: 'madiun', district_codes: ['C'], values: {} },
] });
const area = { key: 'area6', label: 'Area 6' };
const branch = { key: 'ngawi', label: 'KC Ngawi' };

test('new village ownership changes polygon choices while preserving REKAP figures and identities', () => {
    const original = { ready: true, units: [
        { code: '001', name: 'Satu', branch: 'ngawi', district_codes: ['A'], values: { total: { potential: 900, existing: 400 } } },
        { code: '002', name: 'Dua', branch: 'madiun', district_codes: ['C'], values: {} },
    ] };
    const reference = { ready: true, ownership_source: { spreadsheet_id: 'ownership' }, units: [
        { code: '001', branch: 'ngawi', district_codes: ['B', 'B'], owned_village_count: 2 },
    ] };
    const result = withVillageTerritory(original, reference);
    const nextIndex = new TerritoryIndex(index.features, result);
    assert.deepEqual(nextIndex.unitsOf('A'), []);
    assert.deepEqual(nextIndex.unitsOf('B').map((unit) => unit.code), ['001']);
    assert.deepEqual(result.units[1].district_codes, []);
    assert.deepEqual(result.units[0].values, original.units[0].values);
    assert.deepEqual(original.units[0].district_codes, ['A']);
    assert.equal(result.units[0].name, 'Satu');
    assert.equal(result.territory_source.spreadsheet_id, 'ownership');
});

test('marketshare polygon always selects the source branch, including repeated clicks', () => {
    const first = resolvePolygonSelection({ key: 'marketshare', code: 'A', index, scope: area });
    const next = resolvePolygonSelection({ key: 'marketshare', code: 'B', current: first, index, scope: area });
    assert.equal(first.level, 'branch');
    assert.equal(next.level, 'branch');
    assert.equal(next.branchKey, 'ngawi');
    assert.deepEqual([...next.codes], ['A', 'B']);
    assert.equal(resolvePolygonSelection({ key: 'marketshare', unitCode: '001', current: first, index, scope: area }), null);
});

test('mapping enters a branch first and groups all actual units of a shared district', () => {
    const first = resolvePolygonSelection({ key: 'mapping', code: 'A', index, scope: area });
    const district = resolvePolygonSelection({ key: 'mapping', code: 'A', current: first, index, scope: area });
    assert.equal(first.level, 'branch');
    assert.equal(district.level, 'district');
    assert.deepEqual(district.unitCodes, ['001', '002']);
    assert.equal(district.unitCode, null);
    assert.deepEqual([...district.codes], ['A']);
});

test('explicit unit identity disambiguates units sharing the same polygon', () => {
    const first = resolvePolygonSelection({ key: 'sektoral', code: 'A', index, scope: branch });
    const current = resolvePolygonSelection({ key: 'sektoral', code: 'A', current: first, index, scope: branch });
    const one = resolvePolygonSelection({ key: 'sektoral', unitCode: '001', current, index, scope: branch });
    const two = resolvePolygonSelection({ key: 'sektoral', unitCode: '002', current, index, scope: branch });
    assert.equal(one.unitCode, '001');
    assert.equal(two.unitCode, '002');
    assert.deepEqual([...one.codes], [...two.codes]);
    assert.notEqual(one.label, two.label);
});

test('a sole serving unit is source-backed and selections cannot cross authorized scope', () => {
    const first = resolvePolygonSelection({ key: 'mapping', code: 'B', index, scope: branch });
    const selected = resolvePolygonSelection({ key: 'mapping', code: 'B', current: first, index, scope: branch });
    assert.equal(selected.unitCode, '003');
    assert.equal(resolvePolygonSelection({ key: 'mapping', code: 'C', index, scope: branch }), null);
    assert.equal(resolvePolygonSelection({ key: 'mapping', code: 'missing', index, scope: area }), null);
    assert.equal(resolvePolygonSelection({ key: 'mapping', unitCode: '004', current: selected, index, scope: branch }), null);
    assert.equal(resolvePolygonSelection({ key: 'pinjaman', code: 'A', index, scope: area }), null);
});

test('a branch-scoped presentation always names the KC before revealing a district or unit', () => {
    for (const key of ['mapping', 'sektoral']) {
        for (const code of ['A', 'B']) {
            const first = resolvePolygonSelection({ key, code, index, scope: branch });
            assert.equal(first.level, 'branch');
            assert.equal(first.label, 'KC Ngawi');
            assert.equal(first.branchKey, 'ngawi');
            assert.deepEqual(first.unitCodes, []);
            assert.deepEqual([...first.codes], ['A', 'B']);
        }
    }
});
