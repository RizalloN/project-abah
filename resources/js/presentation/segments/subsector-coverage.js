import { sectorMetrics } from './sector-model.js';

/** The agreed denominator is the parent sector's business population in the service territory. */
export const subsectorReference = (payload, units, selection = null) => {
    const source = payload.sector_potential || {};
    const context = source.selected_context || {};
    const group = context.parent_group;
    const existing = units.reduce((sum, unit) => sum + (Number(unit.values?.jumlah_debitur) || 0), 0);
    if (!group) return { group: null, total: sectorMetrics(null, existing), available: false };
    const restricted = selection?.level === 'unit' || selection?.level === 'district';
    if (!restricted) return { group, total: context.subsector_parent_coverage || sectorMetrics(null, existing), available: true,
        potential_partial: (source.units || []).some((record) => record.potential_partial),
        missing_population_village_count: (source.units || []).reduce((sum, record) => sum + Number(record.missing_population_village_count || 0), 0) };
    // The selected territory stays fixed even when a finer filter returns no customers for a UKER.
    const chosen = selection?.unitCodes?.length ? selection.unitCodes : selection?.unitCode ? [selection.unitCode] : units.map((unit) => unit.code);
    const records = [...new Set(chosen.map(String))].map((code) => (source.units || []).find((record) => String(record.code) === code));
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
    return { group, total: sectorMetrics(potential, existing), available: true,
        potential_partial: records.some((record) => record?.potential_partial),
        missing_population_village_count: records.reduce((sum, record) => sum + Number(record?.missing_population_village_count || 0), 0) };
};
