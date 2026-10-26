import { BRANCH_LABELS } from './territory-data.js';

export const POLYGON_CHAPTERS = new Set(['marketshare', 'mapping', 'sektoral']);

/** Resolve identities from the source mapping, never by choosing the largest/first serving unit. */
export const resolvePolygonSelection = ({ key, code, unitCode, current, scope, index }) => {
    if (!POLYGON_CHAPTERS.has(key)) return null;
    if (unitCode) {
        if (key === 'marketshare') return null;
        const unit = index.unitsInScope(scope.key).find((item) => item.code === unitCode);
        if (!unit || !current || current.branchKey !== unit.branch || !current.unitCodes.includes(unit.code)) return null;
        return { level: 'unit', branchKey: unit.branch, unitCode: unit.code, unitCodes: [unit.code], districtCode: null, label: `Unit ${unit.name}`, codes: index.codesOfUnit(unit) };
    }
    const branchKey = index.branchOf(code);
    if (!branchKey || (scope.key !== 'area6' && scope.key !== branchKey)) return null;
    if (key === 'marketshare' || !current || current.branchKey !== branchKey) {
        return { level: 'branch', branchKey, unitCode: null, unitCodes: [], districtCode: null, label: BRANCH_LABELS[branchKey], codes: index.codesOfBranch(branchKey) };
    }
    const units = [...new Map(index.unitsOf(code).filter((unit) => unit.branch === branchKey).map((unit) => [unit.code, unit])).values()];
    if (!units.length) return null;
    if (units.length === 1) {
        const unit = units[0];
        return { level: 'unit', branchKey, unitCode: unit.code, unitCodes: [unit.code], districtCode: code, label: `Unit ${unit.name}`, codes: index.codesOfUnit(unit) };
    }
    return { level: 'district', branchKey, unitCode: null, unitCodes: units.map((unit) => unit.code), districtCode: code,
        label: `Wilayah layanan ${index.nameOf(code).district}`, codes: new Set([code]) };
};
