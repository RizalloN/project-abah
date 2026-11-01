import { sectorMetrics } from './sector-model.js';

const population = value => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)) ? Number(value) : null;
const sourceReady = (payload) => payload.sector_reference_ready !== false && payload.sector_potential?.selected_context?.reference_ready !== false;

/** An unfiltered cohort uses all source categories; an ambiguous filtered parent stays unavailable. */
export const subsectorGroup = (payload) => {
    const source = payload.sector_potential || {};
    if (!sourceReady(payload)) return null;
    if (source.selected_context?.parent_group) return source.selected_context.parent_group;
    const selected = payload.filters?.selected || {};
    const sector = selected.sektor ?? source.selected_context?.sector;
    const subsector = selected.sub_sektor ?? source.selected_context?.subsector;
    return sector === 'all' && subsector === 'all'
        ? (source.comparison_groups || []).find(group => group.key === 'total') || null : null;
};

/** Match the owned UKER by branch and code; never substitute the whole branch population. */
export const subsectorUnitCoverage = (payload, unit, group = subsectorGroup(payload)) => {
    const source = population(unit.values?.jumlah_debitur);
    const existing = population(unit.values?.jumlah_debitur_normalized) ?? source;
    if (!group || !sourceReady(payload)) return sectorMetrics(null, existing, source);
    const records = (payload.sector_potential?.units || []).filter(record => String(record.code) === String(unit.code)
        && (!unit.branch_key || record.branch === unit.branch_key));
    const record = records.length === 1 ? records[0] : null;
    return sectorMetrics(record && record.potential_available !== false ? population(record.values?.[group.key]?.potential) : null, existing, source);
};

/** The agreed denominator is the parent sector's business population in the service territory. */
export const subsectorReference = (payload, units, selection = null) => {
    const source = payload.sector_potential || {};
    const context = source.selected_context || {};
    const group = subsectorGroup(payload);
    const sourceCount = units.reduce((sum, unit) => sum + (Number(unit.values?.jumlah_debitur) || 0), 0);
    const existing = units.reduce((sum, unit) => sum + (Number(unit.values?.jumlah_debitur_normalized ?? unit.values?.jumlah_debitur) || 0), 0);
    if (!group) return { group: null, total: sectorMetrics(null, existing, sourceCount), available: false };
    const branch = selection?.branchKey && !['all', 'area6'].includes(selection.branchKey) ? selection.branchKey : null;
    const sourceUnits = (source.units || []).filter(record => !branch || record.branch === branch);
    const restricted = selection?.level === 'unit' || selection?.level === 'district';
    if (!restricted) {
        const branchRecord = branch ? (source.branches || []).find(record => record.key === branch) : null;
        const total = group.key === 'total' || branch
            ? sectorMetrics(population((branch ? branchRecord?.values : source.totals)?.[group.key]?.potential), existing, sourceCount)
            : context.subsector_parent_coverage || sectorMetrics(null, existing, sourceCount);
        return { group, total, available: true,
            potential_partial: sourceUnits.some((record) => record.potential_partial),
            missing_population_village_count: sourceUnits.reduce((sum, record) => sum + Number(record.missing_population_village_count || 0), 0) };
    }
    // The selected territory stays fixed even when a finer filter returns no customers for a UKER.
    const chosen = selection?.unitCodes?.length ? selection.unitCodes : selection?.unitCode ? [selection.unitCode] : units.map((unit) => unit.code);
    const records = [...new Set(chosen.map(String))].map((code) => {
        const matches = sourceUnits.filter(record => String(record.code) === code);
        return matches.length === 1 ? matches[0] : null;
    });
    const complete = records.length && records.every((record) => record?.potential_available);
    const keys = new Set(records.filter(Boolean).flatMap((record) => record.village_keys || []));
    const values = [...keys].map((key) => {
        const village = (source.villages || []).find((record) => record.key === key);
        if (!village) return null;
        const counts = (group.categories || []).map((category) => village.source_values?.[category]);
        return counts.length && counts.every((value) => value !== null && value !== undefined && Number.isFinite(Number(value)))
            ? counts.reduce((sum, value) => sum + Number(value), 0) : null;
    });
    const potential = complete && values.length && values.every((value) => value !== null && value !== undefined && Number.isFinite(Number(value)))
        ? values.reduce((sum, value) => sum + Number(value), 0) : null;
    return { group, total: sectorMetrics(potential, existing, sourceCount), available: true,
        potential_partial: records.some((record) => record?.potential_partial),
        missing_population_village_count: records.reduce((sum, record) => sum + Number(record?.missing_population_village_count || 0), 0) };
};
