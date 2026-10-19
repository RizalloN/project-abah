/* Shared geography, projection and legend for the Area 6 presentation maps. */

export const AREA6_REGENCIES = Object.freeze([
    { code: '35.19', name: 'Kabupaten Madiun', color: '#4b9cff', labelSide: 'right', labelOrder: 1 },
    { code: '35.77', name: 'Kota Madiun', color: '#32d5ed', labelSide: 'right', labelOrder: 0 },
    { code: '35.20', name: 'Magetan', color: '#56cdb2', labelSide: 'left', labelOrder: 1 },
    { code: '35.21', name: 'Ngawi', color: '#efbe72', labelSide: 'left', labelOrder: 0 },
    { code: '35.02', name: 'Ponorogo', color: '#ed8f87', labelSide: 'right', labelOrder: 2 },
]);

export const AREA6_PALETTE = Object.freeze(Object.fromEntries(AREA6_REGENCIES.map(({ code, color }) => [code, color])));
export const PENETRATION_STOPS = Object.freeze([
    [0, '#38345f'], [25, '#6c568b'], [50, '#b8798a'], [75, '#dfb67a'], [100, '#c6e0b1'],
]);

export const numericMapValue = (value) => {
    if (typeof value !== 'number' && typeof value !== 'string') return null;
    if (typeof value === 'string' && !value.trim()) return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

export const districtCode = (feature) => String(feature?.properties?.KDCPUM ?? feature?.code ?? '');

export const polygonsOf = (geometry) => {
    if (geometry?.type === 'Polygon' && Array.isArray(geometry.coordinates)) return [geometry.coordinates];
    if (geometry?.type === 'MultiPolygon' && Array.isArray(geometry.coordinates)) return geometry.coordinates;
    return [];
};

export const openRing = (ring) => {
    const points = (Array.isArray(ring) ? ring : []).filter((point) => Array.isArray(point) && point.length > 1)
        .map(([lon, lat]) => [numericMapValue(lon), numericMapValue(lat)])
        .filter(([lon, lat]) => lon !== null && lat !== null);
    if (points.length > 2 && points[0][0] === points.at(-1)[0] && points[0][1] === points.at(-1)[1]) points.pop();
    return points;
};

export const geometryBounds = (geometry) => {
    const bounds = { minLon: Infinity, maxLon: -Infinity, minLat: Infinity, maxLat: -Infinity };
    polygonsOf(geometry).forEach((polygon) => polygon.forEach((ring) => openRing(ring).forEach(([lon, lat]) => {
        bounds.minLon = Math.min(bounds.minLon, lon);
        bounds.maxLon = Math.max(bounds.maxLon, lon);
        bounds.minLat = Math.min(bounds.minLat, lat);
        bounds.maxLat = Math.max(bounds.maxLat, lat);
    })));
    return Number.isFinite(bounds.minLon) ? bounds : null;
};

export const mergeMapBounds = (list) => list.filter(Boolean).reduce((result, bounds) => ({
    minLon: Math.min(result.minLon, bounds.minLon), maxLon: Math.max(result.maxLon, bounds.maxLon),
    minLat: Math.min(result.minLat, bounds.minLat), maxLat: Math.max(result.maxLat, bounds.maxLat),
}), { minLon: Infinity, maxLon: -Infinity, minLat: Infinity, maxLat: -Infinity });

/** Local equirectangular projection: east right, north up, longitude adjusted for latitude. */
export const projectArea6 = (lon, lat, frame = {}) => {
    const centerLon = numericMapValue(frame.lon) ?? 111.49;
    const centerLat = numericMapValue(frame.lat) ?? -7.7;
    const scale = numericMapValue(frame.scale) ?? 8;
    return [(Number(lon) - centerLon) * Math.cos(centerLat * Math.PI / 180) * scale, (Number(lat) - centerLat) * scale];
};

export const area6Frame = (features = []) => {
    const bounds = mergeMapBounds(features.map((feature) => geometryBounds(feature.geometry)));
    if (!Number.isFinite(bounds.minLon)) return { lon: 111.49, lat: -7.7, scale: 8, bounds: null };
    const lat = (bounds.minLat + bounds.maxLat) / 2;
    const lon = (bounds.minLon + bounds.maxLon) / 2;
    const span = Math.max((bounds.maxLon - bounds.minLon) * Math.cos(lat * Math.PI / 180), bounds.maxLat - bounds.minLat, 0.001);
    return { lon, lat, scale: 6.8 / span, bounds };
};

export const regencyFor = (feature) => {
    const code = String(feature?.properties?.KDPKAB ?? feature?.regencyCode ?? feature?.code?.slice(0, 5) ?? '');
    const byCode = AREA6_REGENCIES.find((regency) => regency.code === code);
    if (byCode) return byCode;
    const name = String(feature?.properties?.WADMKK ?? feature?.regency ?? '').toLowerCase().replace(/kabupaten|kab\.?/g, '').trim();
    return AREA6_REGENCIES.find((regency) => regency.name.toLowerCase().replace('kabupaten ', '') === name) || null;
};

const channels = (color) => [1, 3, 5].map((offset) => parseInt(color.slice(offset, offset + 2), 16));

/** Fixed 0–100% legend. Missing and out-of-range values use a neutral swatch. */
export const penetrationHex = (value) => {
    const numeric = numericMapValue(value);
    if (numeric === null || numeric < 0 || numeric > 100) return '#698097';
    for (let index = 1; index < PENETRATION_STOPS.length; index += 1) {
        const [stop, color] = PENETRATION_STOPS[index];
        if (numeric > stop) continue;
        const [previousStop, previousColor] = PENETRATION_STOPS[index - 1];
        const t = (numeric - previousStop) / (stop - previousStop);
        const previous = channels(previousColor);
        return `#${channels(color).map((channel, channelIndex) => Math.round(previous[channelIndex] + (channel - previous[channelIndex]) * t).toString(16).padStart(2, '0')).join('')}`;
    }
    return PENETRATION_STOPS.at(-1)[1];
};

export const area6AssetUrl = () => {
    const config = typeof window !== 'undefined' ? window.__PRESENTATION_CONFIG__ : null;
    const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
    const worker = config?.serviceWorkerUrl;
    const base = worker ? String(worker).replace(/presentation-sw\.js(?:\?.*)?$/, '') : `${origin}/`;
    return new URL('data/marketshare-area6-kecamatan.geojson', new URL(base, `${origin}/`)).href;
};

let geometryPromise = null;

/** One shared same-origin request, including when the financial mapping payload is absent. */
export const loadArea6Geometry = () => {
    if (!geometryPromise) {
        geometryPromise = fetch(area6AssetUrl(), { credentials: 'same-origin' }).then((response) => {
            if (!response.ok) throw new Error(`Area 6 geometry: HTTP ${response.status}`);
            return response.json();
        }).then((geojson) => {
            if (geojson?.type !== 'FeatureCollection' || !Array.isArray(geojson.features)) throw new Error('Area 6 geometry is unavailable.');
            return { ...geojson, features: geojson.features.filter((feature) => regencyFor(feature) && polygonsOf(feature.geometry).length) };
        }).catch((error) => { geometryPromise = null; throw error; });
    }
    return geometryPromise;
};
