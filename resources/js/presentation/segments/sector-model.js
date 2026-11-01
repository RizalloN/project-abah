import { BRANCH_LABELS } from './territory-data.js';

const valueOf = (value) => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)) ? Number(value) : null;

/**
 * `existing` is the sector-normalized debtor count (server side, matched to the BPS business
 * sectors of the UKER villages); `source` is the count as coded in SSA, kept as the source ratio.
 */
export const sectorMetrics = (potential, existing, source = existing) => {
    const ratio = potential !== null && potential > 0 && existing !== null ? existing / potential * 100 : null;
    const raw = potential !== null && potential > 0 && existing !== null && source !== null ? source / potential * 100 : null;
    return { potential, existing, existing_source: source, penetration_raw: raw,
        penetration: ratio === null ? null : Math.min(100, Math.max(0, ratio)),
        normalization_applied: raw !== null && (Math.abs(raw - ratio) > 0.0001 || raw > 100 || raw < 0),
        penetration_excess: raw === null ? null : Math.max(0, raw - 100),
        gap: potential !== null && existing !== null ? Math.max(0, potential - existing) : null };
};

/** Missing denominators must survive aggregation, including at unit/subsector grain. */
export const aggregateSectorValues = (rows, key = 'total') => {
    const sum = (field) => {
        const values = rows.map((row) => valueOf(row.values?.[key]?.[field]));
        return values.length && values.every((value) => value !== null) ? values.reduce((total, value) => total + value, 0) : null;
    };
    const potential = sum('potential');
    const existing = sum('existing');
    const source = sum('existing_source');
    return { ...sectorMetrics(potential, existing, source ?? existing), intra: sum('intra'), extra: sum('extra'), extra_estimated: sum('extra_estimated'),
        credit_intra: sum('credit_intra'), credit_extra: sum('credit_extra'), credit_total: sum('credit_total') };
};

/** Population follows owned villages. Only map debtors use the disclosed district allocation. */
export const sectorView = (ctx) => {
    const source = ctx.wilayah?.sektoral || {};
    const selection = ctx.selection;
    const branch = ctx.scope.key !== 'area6' ? ctx.scope.key : selection?.branchKey && selection.branchKey !== 'area6' ? selection.branchKey : null;
    const inScope = (row) => !branch || (row.branch || row.key) === branch;
    const branches = (source.branches || []).filter(inScope);
    const districts = (source.districts || []).filter(inScope);
    const unitCodes = new Set((selection?.unitCodes || (selection?.unitCode ? [selection.unitCode] : [])).map(String));
    const allUnits = (source.units || []).filter(inScope);
    const units = allUnits.filter((row) => unitCodes.has(String(row.code)));
    const unitLevel = selection?.level === 'unit';
    const districtLevel = selection?.level === 'district';
    const selectedDistricts = districtLevel ? districts.filter((row) => String(row.code) === String(selection.districtCode)) : districts;
    const selectedRows = unitLevel ? units : districtLevel ? selectedDistricts : branches;
    const rows = unitLevel ? units : branch ? districtLevel ? units : allUnits : branches;
    const label = selection && selection.level !== 'branch'
        ? `${BRANCH_LABELS[branch]} / ${selection.label}` : BRANCH_LABELS[branch] || ctx.scope.label;
    const byDistrict = (key) => {
        if (!unitLevel) return new Map(selectedDistricts.map((row) => [String(row.code), { ...aggregateSectorValues([row], key), name: row.name }]));
        const result = new Map();
        const categories = (source.comparison_groups || []).find((group) => group.key === key)?.categories || [];
        units.forEach((unit) => {
            const figures = unit.values?.[key] || {};
            const existing = valueOf(figures.existing);
            if (existing === null) return;
            const codes = [...new Set(unit.service_district_codes || unit.district_codes || [])].map(String);
            // The unit's owned population of this group in each district: the map weights its debtors by it, as the server does.
            const potentials = codes.map((code) => {
                const villages = (source.villages || []).filter((village) => (unit.village_keys || []).includes(village.key) && String(village.district_code) === code);
                const values = villages.flatMap((village) => categories.map((category) => valueOf(village.source_values?.[category])));
                return values.length && values.every((value) => value !== null) ? values.reduce((sum, value) => sum + value, 0) : null;
            });
            const weight = potentials.reduce((sum, value) => sum + (value || 0), 0);
            codes.forEach((code, index) => {
                const share = weight > 0 ? (potentials[index] || 0) / weight : 1 / codes.length;
                const previous = result.get(code);
                const potential = potentials[index];
                const combined = previous ? previous.potential !== null && potential !== null ? previous.potential + potential : null : potential;
                const part = (field) => (previous?.[field] || 0) + (valueOf(figures[field]) || 0) * share;
                const source = valueOf(figures.existing_source) ?? existing;
                result.set(code, { ...sectorMetrics(combined, (previous?.existing || 0) + existing * share, (previous?.existing_source || 0) + source * share), intra: part('intra'), extra: part('extra') });
            });
        });
        return result;
    };
    return { source, branch, branches, districts, units, rows, selectedRows, label, unitLevel, districtLevel,
        headLabel: branch ? 'UKER' : 'Cabang',
        groups: (source.comparison_groups || []).filter((group) => !['total', 'unclassified'].includes(group.key)),
        totals: (key = 'total') => aggregateSectorValues(selectedRows, key),
        rowValues: (key) => rows.map((row) => ({ ...row, label: row.label || row.name, ...aggregateSectorValues([row], key) })), byDistrict };
};
