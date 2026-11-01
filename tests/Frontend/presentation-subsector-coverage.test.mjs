import test from 'node:test';
import assert from 'node:assert/strict';
import { subsectorReference, subsectorGroup, subsectorUnitCoverage } from '../../resources/js/presentation/segments/subsector-coverage.js';

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

const allGroups = [
    { key: 'total', label: 'Seluruh sektor usaha', categories: ['A', 'G'] },
    { key: 'trade', label: 'Perdagangan', categories: ['G'] },
];
const allPayload = () => ({
    sector_reference_ready: true,
    filters: { selected: { sektor: 'all', sub_sektor: 'all' } },
    sector_potential: {
        comparison_groups: allGroups,
        selected_context: { sector: 'all', subsector: 'all', parent_group: null, reference_ready: true,
            subsector_parent_coverage: { potential: null, existing: 30, penetration: null } },
        totals: { total: { potential: 1200 }, trade: { potential: 1000 } },
        branches: [{ key: 'madiun', values: { total: { potential: 1200 }, trade: { potential: 1000 } } }],
        units: source.units.map(record => ({ ...record, branch: 'madiun', values: { total: { potential: record.code === '01' ? 1200 : 500 }, trade: { potential: record.code === '01' ? 1000 : 400 } } })),
        villages: [{ key: 'A', source_values: { A: 100, G: 400 } }, { key: 'B', source_values: { A: 100, G: 600 } }],
    },
});

test('all sectors and subsectors use the explicit aggregate category instead of blank parent fields', () => {
    const payload = allPayload();
    const reference = subsectorReference(payload, units, { level: 'branch', branchKey: 'madiun' });
    assert.equal(reference.group.key, 'total');
    assert.equal(reference.total.potential, 1200);
    assert.equal(reference.total.existing, 30);
    assert.equal(reference.total.penetration, 2.5);
    assert.equal(payload.sector_potential.selected_context.parent_group, null);
    assert.equal(payload.sector_potential.selected_context.subsector_parent_coverage.potential, null);
    assert.equal(subsectorReference(payload, units, { level: 'branch', branchKey: 'area6' }).total.potential, 1200);
});

test('unfiltered UKER rows use their own total potential and preserve ratios exceeding 100 percent', () => {
    const payload = allPayload();
    const coverage = subsectorUnitCoverage(payload, { code: '02', branch_key: 'madiun', values: { jumlah_debitur: 600 } });
    assert.equal(coverage.potential, 500);
    assert.equal(coverage.existing, 600);
    assert.equal(coverage.penetration_raw, 120);
    assert.equal(coverage.penetration, 100);
    assert.equal(coverage.normalization_applied, true);
});

test('choosing a parent sector keeps its exact UKER denominator instead of the all-sector population', () => {
    const payload = allPayload();
    payload.filters.selected = { sektor: 'PERDAGANGAN', sub_sektor: 'Rincian perdagangan' };
    payload.sector_potential.selected_context.parent_group = allGroups[1];
    const coverage = subsectorUnitCoverage(payload, { code: '02', branch_key: 'madiun', values: { jumlah_debitur: 20 } });
    assert.equal(subsectorGroup(payload).key, 'trade');
    assert.equal(coverage.potential, 400);
    assert.equal(coverage.penetration, 5);
});

test('all-sector district coverage deduplicates source villages without summing repeated unit populations', () => {
    const reference = subsectorReference(allPayload(), units, { level: 'district', branchKey: 'madiun', unitCodes: ['01', '02'] });
    assert.equal(reference.total.potential, 1200);
    assert.equal(reference.total.existing, 30);
});

test('an ambiguous filtered parent cannot silently fall back to the all-sector denominator', () => {
    const payload = allPayload();
    payload.filters.selected.sub_sektor = 'Mixed subsector';
    assert.equal(subsectorGroup(payload), null);
    assert.equal(subsectorUnitCoverage(payload, { code: '01', values: { jumlah_debitur: 10 } }).potential, null);
    payload.filters.selected = { sektor: 'Unknown sector', sub_sektor: 'all' };
    assert.equal(subsectorReference(payload, units).total.potential, null);
});

test('same UKER code in another branch cannot supply the chosen UKER population', () => {
    const payload = allPayload();
    payload.sector_potential.units.push({ code: '02', branch: 'ngawi', potential_available: true, values: { total: { potential: 9000 } } });
    assert.equal(subsectorUnitCoverage(payload, { code: '02', branch_key: 'madiun', values: { jumlah_debitur: 20 } }).potential, 500);
    assert.equal(subsectorUnitCoverage(payload, { code: '02', values: { jumlah_debitur: 20 } }).potential, null);
    assert.equal(subsectorReference(payload, units, { level: 'branch', branchKey: 'unknown' }).total.potential, null);
});

test('missing, conflicted, and zero source populations remain distinct', () => {
    const payload = allPayload();
    const unit = { code: '02', branch_key: 'madiun', values: { jumlah_debitur: 20 } };
    payload.sector_potential.units[1].values.total.potential = 0;
    assert.equal(subsectorUnitCoverage(payload, unit).potential, 0);
    assert.equal(subsectorUnitCoverage(payload, unit).penetration, null);
    payload.sector_potential.units[1].values.total.potential = null;
    assert.equal(subsectorUnitCoverage(payload, unit).potential, null);
    payload.sector_potential.units[1].values.total.potential = 500;
    payload.sector_potential.units[1].potential_available = false;
    assert.equal(subsectorUnitCoverage(payload, unit).potential, null);
    assert.equal(subsectorUnitCoverage(payload, { ...unit, code: 'missing' }).potential, null);
});

test('a reference period or source flagged unavailable never supplies presentation coverage', () => {
    const payload = allPayload();
    payload.sector_reference_ready = false;
    assert.equal(subsectorReference(payload, units).total.potential, null);
    assert.equal(subsectorUnitCoverage(payload, { code: '01', values: { jumlah_debitur: 10 } }).potential, null);
    payload.sector_reference_ready = true;
    payload.sector_potential.selected_context.reference_ready = false;
    assert.equal(subsectorGroup(payload), null);
});
