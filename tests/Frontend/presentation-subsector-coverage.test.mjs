import test from 'node:test';
import assert from 'node:assert/strict';
import { subsectorReference } from '../../resources/js/presentation/segments/subsector-coverage.js';

const source = {
    units: [
        { code: '01', potential_available: true, village_keys: ['A', 'A', 'B'] },
        { code: '02', potential_available: true, village_keys: ['A'] },
    ],
    villages: [
        { key: 'A', source_values: { G: 400 } },
        { key: 'B', source_values: { G: 600 } },
    ],
    selected_context: { parent_group: { key: 'trade', label: 'Perdagangan', categories: ['G'] },
        subsector_parent_coverage: { potential: 1000, existing: 30, penetration: 3, penetration_raw: 3 } },
};
const units = [{ code: '01', values: { jumlah_debitur: 10 } }, { code: '02', values: { jumlah_debitur: 20 } }];

test('subsector coverage uses the parent category and deduplicates village keys', () => {
    const reference = subsectorReference({ sector_potential: source }, units, { level: 'district' });
    assert.equal(reference.group.key, 'trade');
    assert.equal(reference.total.potential, 1000);
    assert.equal(reference.total.existing, 30);
    assert.equal(reference.total.penetration, 3);
});

test('a partial source keeps the known village pool and discloses missing population', () => {
    const partialSource = { ...source, units: [{ ...source.units[0], potential_partial: true, missing_population_village_count: 1 }] };
    const result = subsectorReference({ sector_potential: partialSource }, [units[0]], { level: 'unit', unitCode: '01' });
    assert.equal(result.total.potential, 1000);
    assert.equal(result.potential_partial, true);
    assert.equal(result.missing_population_village_count, 1);
});

test('subsector coverage retains raw numerator and ratio while bounding the presented percentage', () => {
    const reference = subsectorReference({ sector_potential: source }, [{ code: '02', values: { jumlah_debitur: 500 } }], { level: 'unit' });
    assert.equal(reference.total.potential, 400);
    assert.equal(reference.total.existing, 500);
    assert.equal(reference.total.penetration_raw, 125);
    assert.equal(reference.total.penetration, 100);
    assert.equal(reference.total.normalization_applied, true);
});

test('branch coverage uses the authorized unique branch reference, without summing unit denominators', () => {
    const reference = subsectorReference({ sector_potential: source }, units, { level: 'branch' });
    assert.equal(reference.total, source.selected_context.subsector_parent_coverage);
    assert.equal(reference.total.potential, 1000);
});

test('an ambiguous parent or incomplete geographic match never produces a fabricated denominator', () => {
    const unknown = subsectorReference({ sector_potential: { ...source, selected_context: {} } }, units, { level: 'unit' });
    assert.equal(unknown.total.potential, null);
    assert.equal(unknown.total.penetration, null);
    const missing = subsectorReference({ sector_potential: source }, [{ code: '99', values: { jumlah_debitur: 20 } }], { level: 'unit' });
    assert.equal(missing.total.potential, null);
    assert.equal(missing.total.penetration, null);
});

test('a zero-customer subsector does not erase or shrink the fixed selected service territory', () => {
    const emptyUnit = subsectorReference({ sector_potential: source }, [], { level: 'unit', unitCodes: ['01'] });
    assert.equal(emptyUnit.total.potential, 1000);
    assert.equal(emptyUnit.total.existing, 0);
    assert.equal(emptyUnit.total.penetration, 0);
    const partial = subsectorReference({ sector_potential: source }, [units[1]], { level: 'district', unitCodes: ['01', '02'] });
    assert.equal(partial.total.potential, 1000);
    assert.equal(partial.total.existing, 20);
    assert.equal(partial.total.penetration, 2);
});
