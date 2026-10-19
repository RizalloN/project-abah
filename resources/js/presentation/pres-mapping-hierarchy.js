/* Business identities follow the source assignments; district boundaries stay geographic. */
export const BRANCH_COLORS = { madiun: '#52aff0', magetan: '#4fc5af', ngawi: '#e4b56f', ponorogo: '#bd91d8' };
const UNIT_COLORS = ['#4baed0', '#dfaa68', '#a68bd0', '#63baa4', '#d58496', '#7e9fdd', '#b5c47c', '#c28bb0', '#72c7c9', '#d69c7a', '#8798c9', '#8cba89'];
export const mappingToken = (value) => String(value ?? '').toLowerCase().trim().replace(/^kc\s+/, '').replace(/[^a-z0-9]/g, '');
const unitColor = (key) => UNIT_COLORS[[...key].reduce((hash, letter) => (hash * 31 + letter.charCodeAt(0)) >>> 0, 0) % UNIT_COLORS.length];
const finite = (value) => Number.isFinite(Number(value)) ? Number(value) : 0;

export function buildMappingHierarchy({ units, districts, area, valuesOf }) {
    const members = units.map((unit, index) => {
        const values = valuesOf(unit);
        const key = String(unit.code ?? unit.key ?? `source-${index}`);
        const branchKey = mappingToken(unit.branch || unit.branch_label);
        const rawLabel = String(unit.branch_label || unit.branch || 'Cabang');
        return {
            key, label: String(unit.name || unit.label || key), code: String(unit.code || ''),
            branchKey, branchLabel: /^KC\s/i.test(rawLabel) ? rawLabel : `KC ${rawLabel}`,
            districtCodes: [...new Set((unit.district_codes || []).map(String))],
            potential: finite(values.potential), existing: finite(values.existing), penetration: finite(values.penetration),
            color: unitColor(key),
        };
    });
    const byDistrict = new Map(districts.map((district) => [district.code, members.filter((member) => member.districtCodes.includes(district.code))]));
    // Shared catchments must stay visibly multi-owner, even when source-code hashes collide.
    const assigned = new Map();
    [...members].sort((left, right) => left.key.localeCompare(right.key)).forEach((member) => {
        const neighbors = [...new Set(member.districtCodes.flatMap((code) => (byDistrict.get(code) || []).map((owner) => owner.key)))];
        const used = new Set(neighbors.map((key) => assigned.get(key)).filter(Boolean));
        if (used.has(member.color)) {
            member.color = UNIT_COLORS.find((color) => !used.has(color)) || `hsl(${(assigned.size * 137.508 % 360).toFixed(2)}, 48%, 64%)`;
        }
        assigned.set(member.key, member.color);
    });
    const grouped = new Map();
    members.forEach((member) => {
        const key = area ? member.branchKey : member.key;
        if (!grouped.has(key)) grouped.set(key, { key, label: area ? member.branchLabel : member.label, code: area ? '' : member.code, color: area ? (BRANCH_COLORS[key] || unitColor(key)) : member.color, members: [] });
        grouped.get(key).members.push(member);
    });
    const groups = [...grouped.values()].map((group) => {
        const codes = [...new Set(group.members.flatMap((member) => member.districtCodes))];
        const potential = group.members.reduce((sum, member) => sum + member.potential, 0);
        const existing = group.members.reduce((sum, member) => sum + member.existing, 0);
        return { ...group, potential, existing, penetration: potential > 0 ? existing / potential * 100 : 0, districtCodes: codes, unitCount: group.members.length, sharedCount: codes.filter((code) => (byDistrict.get(code)?.length || 0) > 1).length };
    }).sort((left, right) => area ? ['madiun', 'magetan', 'ngawi', 'ponorogo'].indexOf(left.key) - ['madiun', 'magetan', 'ngawi', 'ponorogo'].indexOf(right.key) : left.label.localeCompare(right.label, 'id'));
    const ownership = Object.fromEntries([...byDistrict].map(([code, owners]) => [code, {
        unitKeys: owners.map((owner) => owner.key),
        groupKeys: [...new Set(owners.map((owner) => area ? owner.branchKey : owner.key))],
        shared: owners.length > 1,
    }]));
    return { level: area ? 'branch' : 'unit', groups, members, ownership, sharedCount: Object.values(ownership).filter((entry) => entry.shared).length, totals: { potential: members.reduce((sum, member) => sum + member.potential, 0), existing: members.reduce((sum, member) => sum + member.existing, 0) } };
}

export const hierarchyColors = (hierarchy, code) => (hierarchy?.ownership?.[code]?.groupKeys || []).map((key) => hierarchy.groups.find((group) => group.key === key)?.color).filter(Boolean);
export const mappingPatternId = (code) => `mapping-owner-${String(code).replace(/[^a-z0-9]/gi, '-')}`;
