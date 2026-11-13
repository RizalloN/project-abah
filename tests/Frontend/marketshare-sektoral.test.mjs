import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { aggregateDistricts, districtIndex, visibleFeatures, mapGeometry, penetrationColor, canSelectDistrict } from '../../public/js/marketshare-sektoral.js';

const row = (potential, existing, source = existing) => ({ values: { total: { potential, existing, existing_source: source, intra: existing, extra: 0 } } });

test('district selection requires a known KC instead of Area 6 or an arbitrary URL scope', () => {
    const regions = { magetan: { regency_codes: ['35.20'] } };
    assert.equal(canSelectDistrict({ scope: 'area6', regions }), false);
    assert.equal(canSelectDistrict({ scope: 'unknown', regions }), false);
    assert.equal(canSelectDistrict({ regions }), false);
    assert.equal(canSelectDistrict({ scope: 'magetan', regions }), true);
});

test('district combination uses sums and retains the original SSA ratio', () => {
    const result = aggregateDistricts([row(100, 80, 110), row(900, 90, 90)]);
    assert.equal(result.potential, 1000);
    assert.equal(result.existing, 170);
    assert.equal(result.penetration, 17);
    assert.equal(result.penetration_raw, 20);
    assert.equal(result.extra, 0);
});

test('missing geography figures remain missing instead of valid-looking zeroes', () => {
    assert.equal(aggregateDistricts([]).potential, null);
    assert.equal(aggregateDistricts([row(100, null)]).existing, null);
    assert.equal(aggregateDistricts([row(100, 20), row(null, 10)]).potential, null);
    assert.equal(aggregateDistricts([row(0, 0)]).penetration, null);
    assert.equal(aggregateDistricts([row(0, 0)]).existing, 0);
    assert.notEqual(penetrationColor(null), penetrationColor(0));
});

test('a shared polygon combines only the supplied branch-owned records without changing them', () => {
    const payload = { districts: [
        { ...row(40, 10), code: '35.20.01', name: 'Poncol', branch: 'magetan', allocation_estimate: true },
        { ...row(60, 20), code: '35.20.01', name: 'Poncol', branch: 'madiun', allocation_estimate: true },
    ] };
    const original = structuredClone(payload);
    const index = districtIndex(payload);
    assert.equal(index.size, 1);
    assert.equal(index.get('35.20.01').estimated, true);
    assert.equal(aggregateDistricts(index.get('35.20.01').records).potential, 100);
    const restricted = districtIndex({ districts: payload.districts.filter(row => row.branch === 'magetan') });
    assert.equal(aggregateDistricts(restricted.get('35.20.01').records).potential, 40);
    assert.deepEqual(payload, original);
});

test('branch map includes its administrative area and supplied cross-border service area only', () => {
    const feature = (code, regency) => ({ properties: { KDCPUM: code, KDPKAB: regency }, geometry: { type: 'Polygon', coordinates: [] } });
    const geo = { features: [feature('A', '35.20'), feature('B', '35.19'), feature('C', '35.21')] };
    const payload = { scope: 'magetan', regions: { magetan: { regency_codes: ['35.20'] } }, districts: [{ code: 'B' }] };
    assert.deepEqual(visibleFeatures(geo, payload).map(row => row.properties.KDCPUM), ['A', 'B']);
});

test('all 76 existing BIG boundaries project to finite selectable polygon paths', () => {
    const geo = JSON.parse(readFileSync(new URL('../../public/data/marketshare-area6-kecamatan.geojson', import.meta.url)));
    const geometry = mapGeometry(geo.features);
    assert.equal(geometry.entries.length, 76);
    assert.equal(new Set(geometry.entries.map(row => row.code)).size, 76);
    for (const entry of geometry.entries) {
        assert.ok(entry.path.startsWith('M') && entry.path.endsWith('Z'));
        assert.ok(!/NaN|Infinity/.test(entry.path));
        assert.ok(entry.center.every(Number.isFinite));
        assert.ok(entry.bounds.every(Number.isFinite));
    }
});

test('polygon holes and MultiPolygon pieces survive projection, and invalid data is explicit', () => {
    const ring = [[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]];
    const shape = { properties: { KDCPUM: 'X' }, geometry: { type: 'MultiPolygon', coordinates: [[ring, ring.map(([x, y]) => [x / 2, y / 2])], [ring.map(([x, y]) => [x + 4, y])]] } };
    assert.equal(mapGeometry([shape]).entries[0].path.split('M').length - 1, 3);
    assert.throws(() => mapGeometry([]), /Batas kecamatan/);
});
