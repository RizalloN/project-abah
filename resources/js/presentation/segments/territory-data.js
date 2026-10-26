/*
 * Geography index: which kecamatan (KDCPUM) belong to which branch and unit,
 * and how unit figures land on kecamatan.
 *
 * A unit may serve up to two kecamatan and several units may share one, so a
 * unit's value is split evenly over its kecamatan and summed per kecamatan; the
 * same allocation the mapping report uses. Branch membership comes from the
 * regency (WADMKK) of each polygon.
 */

import { outletToken } from './format.js';

const BRANCH_OF_REGENCY = { MADIUN: 'madiun', 'KOTA MADIUN': 'madiun', MAGETAN: 'magetan', NGAWI: 'ngawi', PONOROGO: 'ponorogo' };

export const BRANCH_LABELS = { madiun: 'KC Madiun', magetan: 'KC Magetan', ngawi: 'KC Ngawi', ponorogo: 'KC Ponorogo' };

/** Refresh geographic ownership without replacing the REKAP financial figures. */
export const withVillageTerritory = (mapping, reference) => {
    if (!mapping?.ready || !reference?.ready || !reference.ownership_source) return mapping;
    const owners = new Map((reference.units || []).filter((unit) => unit.code).map((unit) => [`${unit.branch}|${unit.code}`, unit]));
    return { ...mapping, territory_source: reference.ownership_source,
        units: (mapping.units || []).map((unit) => {
            const owner = owners.get(`${unit.branch}|${unit.code}`);
            return { ...unit, district_codes: [...new Set(owner?.district_codes || [])],
                territory_reference_available: Boolean(owner), owned_village_count: owner?.owned_village_count ?? null };
        }) };
};

/** Snapshot kanca keys ("kc-madiun") to branch keys ("madiun"). */
export const branchOfKanca = (key) => String(key || '').replace(/^kc-/, '').replace(/-detail$/, '');

export class TerritoryIndex {
    constructor(features, mapping) {
        this.features = features || [];
        this.mapping = mapping?.ready ? mapping : null;
        this.names = new Map();
        this.branchCodes = new Map(Object.keys(BRANCH_LABELS).map((key) => [key, new Set()]));
        this.features.forEach((feature) => {
            const code = String(feature.properties?.KDCPUM || '');
            const branch = BRANCH_OF_REGENCY[String(feature.properties?.WADMKK || '').toUpperCase()];
            this.names.set(code, { district: feature.properties?.WADMKC || code, regency: feature.properties?.WADMKK || '', branch });
            if (branch) this.branchCodes.get(branch).add(code);
        });
        this.units = (this.mapping?.units || []).filter((unit) => unit.district_codes?.length);
        this.unitsByCode = new Map();
        this.units.forEach((unit) => unit.district_codes.forEach((code) => {
            if (!this.unitsByCode.has(code)) this.unitsByCode.set(code, []);
            this.unitsByCode.get(code).push(unit);
        }));
    }

    get ready() {
        return this.features.length > 0;
    }

    nameOf(code) {
        return this.names.get(code) || { district: code, regency: '', branch: null };
    }

    branchOf(code) {
        return this.nameOf(code).branch;
    }

    codesOfBranch(branch) {
        return this.branchCodes.get(branch) || new Set();
    }

    codesOfUnit(unit) {
        return new Set(unit?.district_codes || []);
    }

    unitsOf(code) {
        return this.unitsByCode.get(code) || [];
    }

    unitsInScope(scopeKey) {
        return scopeKey === 'area6' ? this.units : this.units.filter((unit) => unit.branch === scopeKey);
    }

    /** Codes a scope covers: null for the whole Area, the branch's kecamatan otherwise. */
    scopeCodes(scopeKey) {
        return scopeKey === 'area6' ? null : this.codesOfBranch(scopeKey);
    }

    /** Potential, existing and penetration of one sector per kecamatan, from the units serving it. */
    sectorByDistrict(sector = 'total', units = this.units) {
        const result = new Map();
        units.forEach((unit) => {
            const values = unit.values?.[sector];
            if (!values) return;
            const share = 1 / unit.district_codes.length;
            unit.district_codes.forEach((code) => {
                const entry = result.get(code) || { potential: 0, existing: 0 };
                entry.potential += (Number(values.potential) || 0) * share;
                entry.existing += (Number(values.existing) || 0) * share;
                result.set(code, entry);
            });
        });
        result.forEach((entry) => { entry.penetration = entry.potential > 0 ? (entry.existing / entry.potential) * 100 : null; });
        return result;
    }

    /** Sector totals of a set of units. */
    sectorTotals(sector, units) {
        const totals = units.reduce((sum, unit) => {
            const values = unit.values?.[sector] || {};
            sum.potential += Number(values.potential) || 0;
            sum.existing += Number(values.existing) || 0;
            return sum;
        }, { potential: 0, existing: 0 });
        totals.penetration = totals.potential > 0 ? (totals.existing / totals.potential) * 100 : null;
        totals.gap = Math.max(0, totals.potential - totals.existing);
        return totals;
    }

    /**
     * Snapshot entities to kecamatan. The Area's entities are branches (all of a
     * branch's kecamatan); a branch's entities are outlets, matched to REKAP units by name.
     */
    entityCodes(entities, isArea, scopeKey = 'area6') {
        const result = new Map();
        if (isArea) {
            entities.forEach((entity) => result.set(entity.key, this.codesOfBranch(branchOfKanca(entity.key))));
            return result;
        }
        const byToken = new Map(this.unitsInScope(scopeKey).map((unit) => [outletToken(unit.name), unit]));
        entities.forEach((entity) => {
            const unit = byToken.get(outletToken(entity.label));
            if (unit) result.set(entity.key, this.codesOfUnit(unit));
        });
        return result;
    }

    /** Entity values spread over their kecamatan (evenly, summed where entities share one). */
    entityValuesByDistrict(entityCodes, valueOf) {
        const result = new Map();
        entityCodes.forEach((codes, key) => {
            const raw = valueOf(key);
            if (raw === null || raw === undefined) return;
            const value = Number(raw);
            if (!Number.isFinite(value) || !codes.size) return;
            codes.forEach((code) => result.set(code, (result.get(code) || 0) + value / codes.size));
        });
        return result;
    }
}

const mixHex = (a, b, t) => {
    const channel = (shift) => Math.round((((a >> shift) & 255) * (1 - t)) + (((b >> shift) & 255) * t));
    return (channel(16) << 16) | (channel(8) << 8) | channel(0);
};

/** Penetration ramp: red where we are thin, amber in the middle, BRI blue where we lead. */
export const penetrationColor = (value) => {
    if (value === null || value === undefined || !Number.isFinite(value)) return 0x8e9096;
    const t = Math.max(0, Math.min(1, value / 100));
    return t < 0.5 ? mixHex(0xd2453b, 0xf2a93b, t * 2) : mixHex(0xf2a93b, 0x2c6fe0, (t - 0.5) * 2);
};

export const penetrationCss = (value) => `#${penetrationColor(value).toString(16).padStart(6, '0')}`;

export const mix = mixHex;
