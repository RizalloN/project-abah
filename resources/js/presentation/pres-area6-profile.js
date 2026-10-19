/* Administrative reference; independent of the selected financial report period. */
import { penetrationHex } from './pres-area6-map';
import { hierarchyColors, mappingPatternId } from './pres-mapping-hierarchy';
export const AREA6_PROFILE = [
    { code: '35.19', name: 'Kabupaten Madiun', districts: 15, villages: 198, wards: 8, color: '#4b9cff', source: 'https://jatim.bpk.go.id/kabupaten-madiun/' },
    { code: '35.77', name: 'Kota Madiun', districts: 3, villages: 0, wards: 27, color: '#32d5ed', source: 'https://arsip-ppid.madiunkota.go.id/2022/07/11/kota-madiun-dua-kata-berjuta-cerita/' },
    { code: '35.20', name: 'Kabupaten Magetan', districts: 18, villages: 207, wards: 28, color: '#56cdb2', source: 'https://ppid.magetan.go.id/info-setiap-saat/view?id=807' },
    { code: '35.21', name: 'Kabupaten Ngawi', districts: 19, villages: 213, wards: 4, color: '#efbe72', source: 'https://ngawikab.go.id/wp-content/uploads/2025/10/12.-Laporan-Akuntabilitas-Kinerja-Pemkab.-Ngawi-TA.-2024.pdf' },
    { code: '35.02', name: 'Kabupaten Ponorogo', districts: 21, villages: 281, wards: 26, color: '#ed8f87', source: 'https://ponorogo.go.id/profil-kabupaten-ponorogo/' },
];

export const AREA6_TOTALS = AREA6_PROFILE.reduce((total, region) => ({
    districts: total.districts + region.districts,
    villages: total.villages + region.villages,
    wards: total.wards + region.wards,
}), { districts: 0, villages: 0, wards: 0 });

const h = (text) => String(text ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
export const area6Legend = () => `<ul class="rp-region-legend" aria-label="Lima wilayah Area 6">${AREA6_PROFILE.map((region) => `<li style="--region:${region.color}"><i aria-hidden="true"></i><span>${h(region.name)}<small>${region.districts} kecamatan</small></span></li>`).join('')}</ul>`;
export const area6Poster = (mapping = null) => {
    const districts = mapping?.districts || [];
    if (!districts.length) return `<img class="rp-region-poster" src="${new URL('images/area6-regions.svg', window.__PRESENTATION_CONFIG__?.serviceWorkerUrl || `${window.location.origin}/`).href}" width="800" height="720" alt="Batas Kabupaten Madiun, Kota Madiun, Kabupaten Magetan, Kabupaten Ngawi, dan Kabupaten Ponorogo. Setiap wilayah memiliki warna tersendiri." decoding="async">`;
    const polygons = (geometry) => geometry?.type === 'Polygon' ? [geometry.coordinates] : geometry?.type === 'MultiPolygon' ? geometry.coordinates : [];
    const points = districts.flatMap((district) => polygons(district.geometry).flat(2)).filter((point) => Array.isArray(point) && point.length >= 2 && point.every(Number.isFinite));
    if (!points.length) return area6Poster();
    const lons = points.map((point) => point[0]);
    const lats = points.map((point) => point[1]);
    const minLon = Math.min(...lons), maxLon = Math.max(...lons), minLat = Math.min(...lats), maxLat = Math.max(...lats);
    const correction = Math.cos(((minLat + maxLat) / 2) * Math.PI / 180);
    const scale = Math.min(640 / Math.max(0.0001, (maxLon - minLon) * correction), 590 / Math.max(0.0001, maxLat - minLat));
    const x = (lon) => 400 + (lon - (minLon + maxLon) / 2) * correction * scale;
    const y = (lat) => 350 - (lat - (minLat + maxLat) / 2) * scale;
    const colorsOf = (district) => hierarchyColors(mapping.hierarchy, district.code);
    const fillOf = (district) => !mapping.hierarchy || mapping.reading === 'penetration' ? penetrationHex(district.penetration) : colorsOf(district).length > 1 ? `url(#poster-${mappingPatternId(district.code)})` : colorsOf(district)[0] || '#698097';
    const patterns = districts.map((district) => { const colors = colorsOf(district); return colors.length < 2 ? '' : `<pattern id="poster-${mappingPatternId(district.code)}" width="${colors.length * 6}" height="12" patternUnits="userSpaceOnUse" patternTransform="rotate(35)">${colors.map((color, index) => `<rect x="${index * 6}" width="6" height="12" fill="${color}"/>`).join('')}</pattern>`; }).join('');
    const mapCaption = mapping.hierarchy && mapping.reading !== 'penetration' ? `Cakupan ${mapping.hierarchy.level === 'branch' ? 'cabang' : 'unit'} pada ${mapping.scopeLabel || 'cakupan terpilih'}. Bidang bergaris menunjukkan cakupan bersama.` : `Penetrasi nasabah BRI per kecamatan pada cakupan ${mapping.scopeLabel || 'terpilih'}`;
    return `<svg class="rp-region-poster" viewBox="0 0 800 720" role="img" aria-label="${h(mapCaption)}"><defs>${patterns}</defs><g fill-rule="evenodd" stroke="#d2e7ff" stroke-opacity=".5" stroke-width=".7">${districts.map((district) => `<path data-mapping-district="${h(district.code)}" fill="${fillOf(district)}" d="${polygons(district.geometry).map((rings) => rings.map((ring) => ring.map(([lon, lat], index) => `${index ? 'L' : 'M'}${x(lon).toFixed(1)},${y(lat).toFixed(1)}`).join('') + 'Z').join('')).join('')}"><title>${h(district.name)}: ${h(district.penetrationLabel)}</title></path>`).join('')}</g></svg>`;
};
export const area6ProfileDetails = () => `<details class="rp-region-reference"><summary>Rincian wilayah Area 6</summary><div class="rp-table-scroll" tabindex="0" role="region" aria-label="Profil administrasi lima wilayah Area 6"><table class="rp-table"><thead><tr><th>Wilayah</th><th>Kecamatan</th><th>Kelurahan</th><th>Desa</th><th>Sumber</th></tr></thead><tbody>${AREA6_PROFILE.map((region) => `<tr><th scope="row">${h(region.name)}</th><td>${region.districts}</td><td>${region.wards}</td><td>${region.villages}</td><td><a href="${h(region.source)}" target="_blank" rel="noopener noreferrer">Profil resmi<span class="sr-only"> ${h(region.name)} (tab baru)</span></a></td></tr>`).join('')}</tbody></table></div><p>Profil administrasi merupakan referensi wilayah. Angka kinerja dan penetrasi mengikuti sumber serta periode laporan yang dipilih.</p></details>`;
