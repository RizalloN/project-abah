import { BRANCH_LABELS } from './territory-data.js';

const valueOf = (value) => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)) ? Number(value) : null;

export const sectorMetrics = (potential, existing) => {
    const raw = potential !== null && potential > 0 && existing !== null ? existing / potential * 100 : null;
    return { potential, existing, penetration_raw: raw,
        penetration: raw === null ? null : Math.min(100, Math.max(0, raw)),
        normalization_applied: raw !== null && (raw > 100 || raw < 0),
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
    return { ...sectorMetrics(potential, existing), intra: sum('intra'), extra: sum('extra'),
        credit_intra: sum('credit_intra'), credit_extra: sum('credit_extra'), credit_total: sum('credit_total') };
};

/** Population follows owned villages. Only map debtors use the disclosed district allocation. */
export const sectorView = (ctx) => {
    const source = ctx.wilayah?.sektoral || {};
    const selection = ctx.selection;
    const branch = ctx.scope.key !== 'area6' ? ctx.scope.key : selection?.branchKey || null;
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
        units.forEach((unit) => {
            const existing = valueOf(unit.values?.[key]?.existing);
            if (existing === null) return;
            const codes = [...new Set(unit.service_district_codes || unit.district_codes || [])];
            codes.forEach((code) => {
                const previous = result.get(String(code));
                const categories = (source.comparison_groups || []).find((group) => group.key === key)?.categories || [];
                const villages = (source.villages || []).filter((village) => (unit.village_keys || []).includes(village.key) && String(village.district_code) === String(code));
                const values = villages.flatMap((village) => categories.map((category) => valueOf(village.source_values?.[category])));
                const potential = values.length && values.every((value) => value !== null) ? values.reduce((sum, value) => sum + value, 0) : null;
                const combined = previous ? previous.potential !== null && potential !== null ? previous.potential + potential : null : potential;
                result.set(String(code), sectorMetrics(combined, (previous?.existing || 0) + existing / codes.length));
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
