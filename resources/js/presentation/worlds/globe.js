import {
    AREA6_REGENCIES, numericMapValue, polygonsOf, openRing, geometryBounds, mergeMapBounds,
    area6Frame, projectArea6, regencyFor, penetrationHex, loadArea6Geometry, districtCode,
} from '../pres-area6-map.js';
import { territoryFlight } from './territory-flight.js';
import { hierarchyColors } from '../pres-mapping-hierarchy.js';

/* North-up geographic relief; only the regional story approaches it from Earth. */

const DEG = Math.PI / 180;
const clamp01 = (value) => Math.max(0, Math.min(1, value));
const ease = (value) => 1 - (1 - clamp01(value)) ** 3;
const smooth = (from, to, value) => { const t = clamp01((value - from) / (to - from)); return t * t * (3 - 2 * t); };
const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));

// Compatibility for existing imports; these worlds use only the local planar projection.
export function latLonToVector(THREE, lat, lon, radius = 1, target = new THREE.Vector3()) {
    const [x, north] = projectArea6(lon, lat);
    return target.set(x, radius - 1, -north);
}

export const penetrationColor = (THREE, value) => new THREE.Color(penetrationHex(value));

const geometryShapes = (THREE, geometry, frame) => polygonsOf(geometry).map((rings) => {
    const contour = openRing(rings[0]);
    if (contour.length < 3) return null;
    const shape = new THREE.Shape(contour.map(([lon, lat]) => new THREE.Vector2(...projectArea6(lon, lat, frame))));
    rings.slice(1).forEach((ring) => {
        const points = openRing(ring);
        if (points.length > 2) shape.holes.push(new THREE.Path(points.map(([lon, lat]) => new THREE.Vector2(...projectArea6(lon, lat, frame)))));
    });
    return shape;
}).filter(Boolean);

const boundaryGeometry = (THREE, features, frame, depth, onlyOutside = false) => {
    const edges = new Map();
    features.forEach((feature) => polygonsOf(feature.geometry).forEach((rings) => rings.forEach((ring) => {
        const points = openRing(ring);
        points.forEach((point, index) => {
            const next = points[(index + 1) % points.length];
            const keyA = point.map((value) => value.toFixed(5)).join(',');
            const keyB = next.map((value) => value.toFixed(5)).join(',');
            const key = keyA < keyB ? `${keyA}|${keyB}` : `${keyB}|${keyA}`;
            const previous = edges.get(key);
            if (previous) previous.count += 1;
            else edges.set(key, { a: point, b: next, count: 1 });
        });
    })));
    const positions = [];
    edges.forEach(({ a, b, count }) => {
        if (onlyOutside && count > 1) return;
        [a, b].forEach(([lon, lat]) => {
            const [x, north] = projectArea6(lon, lat, frame);
            positions.push(x, depth + 0.004, -north);
        });
    });
    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    return geometry;
};

const materialPair = (THREE, color, district = false) => [
    new THREE.MeshPhysicalMaterial({ color, roughness: 0.31, metalness: 0.14, clearcoat: 0.65, clearcoatRoughness: 0.22, side: THREE.DoubleSide }),
    new THREE.MeshPhysicalMaterial({ color: new THREE.Color(color).multiplyScalar(district ? 0.34 : 0.40), roughness: 0.36, metalness: 0.28, clearcoat: 0.40 }),
];

function disposeObject(object) {
    object.traverse((child) => {
        child.geometry?.dispose?.();
        child.material?.[0]?.map?.dispose?.();
        (Array.isArray(child.material) ? child.material : [child.material]).filter(Boolean).forEach((material) => material.dispose());
    });
    object.parent?.remove(object);
}

function mapWorld(stage, initialData, mode) {
    const { THREE, scene, camera, host } = stage;
    const districtMode = mode === 'penetration';
    stage.animated = true;
    stage.ready = false;
    stage.maxPixelRatio = 1.65;
    stage.useStudio?.(0.55);
    stage.useLights?.({ key: [-3, 8, 5], keyIntensity: 2.2, rimIntensity: 1.4, shadowSize: 8, shadow: true });
    stage.shadowFloor?.(20, 0.32, -0.75);

    const root = new THREE.Group();
    scene.add(root);
    let data = initialData || {};
    let geojson = null;
    let disposed = false;
    let groups = [];
    let labels = [];
    let frame = area6Frame();
    let width = stage.width || 1;
    let height = stage.height || 1;
    const depth = districtMode ? 0.46 : 0.54;
    const target = new THREE.Vector3(0, 0.03, 0);
    const scratch = new THREE.Vector3();

    const dirty = () => { if (!disposed) host.dispatchEvent(new CustomEvent('worlddirty', { bubbles: true })); };
    const emit = (type, detail) => host.dispatchEvent(new CustomEvent(type, { detail, bubbles: true }));
    const flight = districtMode && !host.hasAttribute('data-mapping-stage') ? territoryFlight(stage, () => { stage.ready = groups.some((entry) => entry.mesh); dirty(); }) : null;
    let mapOpacity = 1;
    let footprint = null;
    const detailStage = host.hasAttribute('data-mapping-stage');
    let focusedKey = detailStage ? data?.mapping?.focusKey || '' : '';
    let reading = detailStage ? data?.mapping?.reading || 'members' : 'members';
    let lastMotionTime = null;
    let readingBlend = reading === 'penetration' ? 1 : 0;
    const onFocus = (event) => {
        focusedKey = event.detail?.key || '';
        reading = event.detail?.reading || 'members';
        groups.forEach((entry) => {
            if (!entry.mesh) return;
            entry.mesh.material[0].map = reading === 'penetration' ? null : entry.stripeTexture;
            entry.mesh.material[0].needsUpdate = true;
        });
        dirty();
    };
    host.addEventListener('mappingfocus', onFocus);

    const sourceDistricts = () => Array.isArray(data?.mapping?.districts) ? data.mapping.districts.filter(Boolean) : [];
    const payloadFeatures = () => sourceDistricts().filter((district) => polygonsOf(district.geometry).length)
        .map((district) => ({ type: 'Feature', geometry: district.geometry, properties: {
            KDCPUM: district.code, KDPKAB: String(district.code || '').slice(0, 5), WADMKK: district.regency, WADMKC: district.name,
        } }));

    const clear = () => {
        groups.forEach(({ group, stripeTexture }) => { stripeTexture?.dispose(); disposeObject(group); });
        groups = [];
        labels.forEach(({ element }) => element.remove());
        labels = [];
        if (footprint) { disposeObject(footprint); footprint = null; }
    };

    const makeMesh = (features, color, regency, district = null) => {
        const shapes = features.flatMap((feature) => geometryShapes(THREE, feature.geometry, frame));
        if (!shapes.length) return null;
        const geometry = new THREE.ExtrudeGeometry(shapes, { depth, bevelEnabled: true, bevelSize: 0.012, bevelThickness: 0.018, bevelSegments: 2, steps: 1, curveSegments: 1 });
        geometry.rotateX(-Math.PI / 2);
        geometry.computeBoundingBox();
        const mesh = new THREE.Mesh(geometry, materialPair(THREE, color, districtMode));
        mesh.receiveShadow = true;
        mesh.castShadow = true;
        mesh.userData.districtCode = district?.code || null;
        const group = new THREE.Group();
        group.add(mesh);
        const lines = new THREE.LineSegments(boundaryGeometry(THREE, features, frame, depth, !districtMode), new THREE.LineBasicMaterial({
            color: districtMode ? 0xc3e6ed : new THREE.Color(color).lerp(new THREE.Color('#ffffff'), 0.4),
            transparent: true, opacity: districtMode ? 0.30 : 0.54, depthWrite: false,
        }));
        group.add(lines);
        root.add(group);
        const bounds = mergeMapBounds(features.map((feature) => geometryBounds(feature.geometry)));
        const center = projectArea6((bounds.minLon + bounds.maxLon) / 2, (bounds.minLat + bounds.maxLat) / 2, frame);
        const entry = {
            group, mesh, regency, center,
            identityColor: new THREE.Color(regency?.color || '#698097'),
            metricColor: new THREE.Color(color),
            order: AREA6_REGENCIES.findIndex((item) => item.code === regency?.code),
            stripeTexture: null, ownerKeys: districtMode ? data?.mapping?.hierarchy?.ownership?.[district?.code]?.groupKeys || [] : [],
        };
        if (districtMode) {
            const hierarchy = data?.mapping?.hierarchy;
            const colors = hierarchyColors(hierarchy, district?.code);
            const owner = hierarchy?.groups?.find((item) => item.key === entry.ownerKeys[0]);
            entry.order = Math.max(0, hierarchy?.groups?.indexOf(owner) ?? 0);
            const ownerDistricts = sourceDistricts().filter((item) => owner?.districtCodes?.includes(item.code));
            const ownerBounds = mergeMapBounds(ownerDistricts.map((item) => item.bounds || geometryBounds(item.geometry)));
            entry.separation = Number.isFinite(ownerBounds.minLon) ? projectArea6((ownerBounds.minLon + ownerBounds.maxLon) / 2, (ownerBounds.minLat + ownerBounds.maxLat) / 2, frame) : center;
            entry.identityColor.set(colors[0] || '#698097');
            if (colors.length > 1) {
                const canvas = document.createElement('canvas'); canvas.width = colors.length * 12; canvas.height = colors.length * 12;
                const context = canvas.getContext('2d');
                if (context) {
                    colors.forEach((tone, index) => { context.fillStyle = tone; context.fillRect(index * 12, 0, 12, canvas.height); });
                    entry.stripeTexture = new THREE.CanvasTexture(canvas);
                    entry.stripeTexture.wrapS = entry.stripeTexture.wrapT = THREE.RepeatWrapping;
                    entry.stripeTexture.colorSpace = THREE.SRGBColorSpace;
                    entry.stripeTexture.repeat.set(1.8, 1.8);
                    entry.identityColor.set('#ffffff');
                    if (reading !== 'penetration') mesh.material[0].map = entry.stripeTexture;
                }
            }
        }
        groups.push(entry);
        return entry;
    };

    const build = () => {
        if (disposed) return;
        const local = payloadFeatures();
        const selectedCodes = new Set(sourceDistricts().map((district) => String(district.code)));
        // Cover always establishes the complete Area 6; the analytical map follows the active scope exactly.
        const geography = geojson?.features?.length ? geojson.features : (districtMode ? local : []);
        const features = geography.filter((feature) => regencyFor(feature)
            && (!districtMode || selectedCodes.has(districtCode(feature))));
        if (!features.length) {
            clear();
            stage.ready = false;
            host.classList?.remove('is-live');
            dirty();
            return;
        }
        clear();
        frame = area6Frame(features);
        flight?.setFrame(frame);
        const districts = new Map(sourceDistricts().map((district) => [String(district.code), district]));
        const byRegency = AREA6_REGENCIES.map((regency) => ({ regency, features: features.filter((feature) => regencyFor(feature)?.code === regency.code) })).filter((item) => item.features.length);

        if (districtMode) features.forEach((feature) => {
            const code = districtCode(feature);
            const source = districts.get(code);
            const district = source || { code, name: feature.properties?.WADMKC || 'Kecamatan', regency: feature.properties?.WADMKK || '-', penetration: null, penetrationLabel: 'Belum tersedia', potentialLabel: 'Belum tersedia', existingLabel: 'Belum tersedia' };
            makeMesh([feature], penetrationHex(district.penetration), regencyFor(feature), district);
        });
        else byRegency.forEach(({ regency, features: regionFeatures }) => makeMesh(regionFeatures, regency.color, regency));

        const hierarchy = data?.mapping?.hierarchy;
        const labelRegions = districtMode && hierarchy ? hierarchy.groups.map((sourceGroup, index) => ({
            sourceGroup,
            regency: { code: sourceGroup.key, name: sourceGroup.label, color: sourceGroup.color, labelSide: ['madiun', 'ponorogo'].includes(sourceGroup.key) || hierarchy.level === 'unit' ? 'right' : 'left' },
            features: features.filter((feature) => sourceGroup.districtCodes.includes(districtCode(feature))),
        })).filter((item) => item.features.length) : byRegency;
        labelRegions.forEach(({ regency, features: regionFeatures, sourceGroup }, index) => {
            const bounds = mergeMapBounds(regionFeatures.map((feature) => geometryBounds(feature.geometry)));
            const center = projectArea6((bounds.minLon + bounds.maxLon) / 2, (bounds.minLat + bounds.maxLat) / 2, frame);
            const side = regency.labelSide === 'left' ? -1 : 1;
            const source = sourceGroup ? { penetrationLabel: `${sourceGroup.unitCount} unit` } : (data?.mapping?.regencies || []).find((item) => String(item.name).toLowerCase().replace(/kabupaten|kab\.?/g, '').trim() === regency.name.toLowerCase().replace('kabupaten ', ''));
            const element = stage.label(`pres-world__label--regency pres-world__label--area6 is-${regency.labelSide}`, `<i style="--tone:${regency.color}"></i><b>${escape(regency.name)}</b>${districtMode && sourceGroup ? `<span>${escape(sourceGroup.code || `${sourceGroup.unitCount} unit`)} / ${sourceGroup.districtCodes.length} kecamatan</span>` : ''}`);
            const north = sourceGroup ? ({ madiun: 1.0, magetan: 0.2, ngawi: 2.9, ponorogo: -2.8 }[sourceGroup.key] ?? 0.2) : regency.code === '35.21' ? 2.65 : regency.code === '35.20' ? 0.15 : regency.code === '35.77' ? 1.25 : regency.code === '35.19' ? -0.35 : -2.65;
            const anchor = new THREE.Vector3(center[0], depth + 0.018, -center[1]);
            const labelAnchor = new THREE.Vector3(side * 2.6, depth + 0.12, -north);
            const leaderGeometry = new THREE.BufferGeometry().setFromPoints([anchor, new THREE.Vector3(side * 2.4, depth + 0.12, -north), labelAnchor]);
            const leader = new THREE.Line(leaderGeometry, new THREE.LineBasicMaterial({ color: regency.color, transparent: true, opacity: 0.62, depthWrite: false }));
            root.add(leader);
            groups.push({ group: leader, order: index, leader: true, labelKey: sourceGroup?.key });
            labels.push({ element, position: labelAnchor, key: sourceGroup?.key, unitLabel: hierarchy?.level === 'unit' });
        });
        footprint = new THREE.Group();
        const plateGeometry = new THREE.ShapeGeometry(features.flatMap((feature) => geometryShapes(THREE, feature.geometry, frame)));
        plateGeometry.rotateX(-Math.PI / 2);
        const plate = new THREE.Mesh(plateGeometry, new THREE.MeshPhysicalMaterial({ color: '#284a6c', metalness: 0.36, roughness: 0.38, transparent: true, opacity: 0.45, side: THREE.DoubleSide, depthWrite: false }));
        plate.position.y = -0.62; plate.receiveShadow = true; footprint.add(plate);
        footprint.add(new THREE.LineSegments(boundaryGeometry(THREE, features, frame, -0.60, true), new THREE.LineBasicMaterial({ color: '#6eaccb', transparent: true, opacity: 0.36, depthWrite: false })));
        root.add(footprint);
        stage.ready = groups.some((entry) => entry.mesh) && (!flight || flight.ready);
        dirty();
    };

    build();
    loadArea6Geometry().then((next) => { if (!disposed) { geojson = next; build(); } }).catch((error) => {
        if (disposed) return;
        stage.ready = false;
        host.classList?.remove('is-live');
        emit('area6:error', { message: error.message });
        dirty();
    });

    return {
        resize(nextWidth, nextHeight) {
            width = nextWidth;
            height = nextHeight;
            const wide = width / height > 1.15;
            stage.shift = { x: 0, y: 0 };
            camera.fov = wide ? 31 : 37;
            labels.forEach(({ element }) => { element.style.fontSize = wide ? '' : '11px'; });
        },
        setData(next) { data = next || {}; focusedKey = detailStage ? data?.mapping?.focusKey || '' : ''; reading = detailStage ? data?.mapping?.reading || 'members' : 'members'; build(); },
        update(progress, time, pointerInput, still) {
            const p = clamp01(progress);
            const delta = lastMotionTime === null ? 1 / 60 : Math.max(0, Math.min(0.05, time - lastMotionTime));
            lastMotionTime = time;
            const follow = still || stage.motionPaused ? 1 : 1 - Math.exp(-delta / 0.22);
            const assembly = still ? 1 : ease(p / (districtMode ? 0.28 : 0.34));
            const tilt = still ? 1 : ease((p - 0.49) / 0.30);
            readingBlend += ((reading === 'penetration' ? 1 : 0) - readingBlend) * follow;
            const choropleth = districtMode ? readingBlend : 0;
            const separation = still ? 0.45 : smooth(0.46, 0.70, p) * (1 - smooth(0.85, 1, p) * 0.5);
            const ambient = still ? 0 : Math.sin(time * 0.27) * 0.016;
            // The region first establishes location; actual penetration replaces its colors as the story advances.
            // North remains up through the short, input-driven shift from plan view to geographic relief.
            const elevation = (districtMode ? 89.8 - tilt * 40.8 : 80 - assembly * 30) * DEG;
            const azimuth = districtMode ? smooth(0.49, 0.85, p) * (8 * DEG + (still ? 0 : (pointerInput.x * 2.2 + Math.sin(time * 0.19) * 1.25) * DEG)) : (still ? 0 : (Math.sin(time * 0.15) + pointerInput.x * 2) * DEG);
            const portrait = width / height <= 1.15;
            const baseDistance = (portrait ? 12.4 : 14.4) * Math.max(1, Math.min(1.7, Math.sqrt(height / Math.max(1, width))));
            const spanX = frame.bounds ? (frame.bounds.maxLon - frame.bounds.minLon) * Math.cos(frame.lat * DEG) * frame.scale : 6.8;
            const spanZ = frame.bounds ? (frame.bounds.maxLat - frame.bounds.minLat) * frame.scale : 6.8;
            const tangent = Math.tan(camera.fov * DEG / 2) * 0.90;
            let fitDistance = 0;
            // Local mesh boxes fit the real silhouette, without reserving empty map-wide corners.
            groups.forEach((entry) => {
                if (!entry.mesh) return;
                const local = still ? 1 : ease((assembly - Math.max(0, entry.order) * 0.035) / 0.86);
                const selected = focusedKey && entry.ownerKeys.includes(focusedKey);
                entry.focusLift ??= selected ? 0.28 : 0;
                entry.focusLift += ((selected ? 0.28 : 0) - entry.focusLift) * follow;
                entry.focusWeight ??= focusedKey && !selected ? 0.24 : 1;
                entry.focusWeight += ((focusedKey && !selected ? 0.24 : 1) - entry.focusWeight) * follow;
                const direction = entry.separation || entry.center;
                const length = Math.max(0.5, Math.hypot(...direction));
                entry.group.position.x = districtMode ? direction[0] / length * separation * 0.56 : direction[0] / Math.max(1, Math.hypot(...direction)) * (1 - assembly) * 0.3;
                entry.group.position.z = districtMode ? -direction[1] / length * separation * 0.56 : -direction[1] / Math.max(1, Math.hypot(...direction)) * (1 - assembly) * 0.3;
                entry.group.position.y = (1 - local) * -0.8 + (districtMode ? separation * (0.15 + entry.order % 4 * 0.065) : 0) + entry.focusLift + ambient;
                entry.group.scale.y = 0.25 + local * 0.75;
                const bounds = entry.mesh.geometry.boundingBox;
                for (const bx of [bounds.min.x, bounds.max.x]) for (const bz of [bounds.min.z, bounds.max.z]) for (const by of [bounds.min.y, bounds.max.y]) {
                    const x = bx + entry.group.position.x, z = bz + entry.group.position.z, y = by * entry.group.scale.y + entry.group.position.y;
                    const forward = x * Math.sin(azimuth) * Math.cos(elevation) + (y - target.y) * Math.sin(elevation) + z * Math.cos(azimuth) * Math.cos(elevation);
                    const horizontal = x * Math.cos(azimuth) - z * Math.sin(azimuth);
                    const vertical = -x * Math.sin(azimuth) * Math.sin(elevation) + (y - target.y) * Math.cos(elevation) - z * Math.cos(azimuth) * Math.sin(elevation);
                    fitDistance = Math.max(fitDistance, forward + Math.abs(horizontal) / (tangent * Math.max(0.1, width / height)), forward + Math.abs(vertical) / tangent);
                }
            });
            const distance = Math.max(baseDistance, fitDistance);
            camera.position.set(Math.sin(azimuth) * Math.cos(elevation) * distance, Math.sin(elevation) * distance, Math.cos(azimuth) * Math.cos(elevation) * distance);
            camera.up.set(0, 1, 0);
            camera.lookAt(target);
            camera.near = 0.1;
            camera.far = 60;
            const journey = flight?.update(p, still, camera, distance, time);
            mapOpacity = journey?.opacity ?? 1;
            // Match the planetary camera at handover; the final relief tilt begins after arrival.
            if (journey && !journey.flight) {
                camera.position.set(Math.sin(azimuth) * Math.cos(elevation) * distance, Math.sin(elevation) * distance, Math.cos(azimuth) * Math.cos(elevation) * distance);
                camera.lookAt(target);
                camera.far = 60;
            }
            root.visible = mapOpacity > 0.001;
            camera.updateProjectionMatrix();
            if (footprint) footprint.visible = mapOpacity > 0.5;
            groups.forEach((entry) => {
                if (entry.leader) { entry.group.visible = mapOpacity > 0.99 && (!entry.labelKey || (!focusedKey && data?.mapping?.hierarchy?.level !== 'unit') || focusedKey === entry.labelKey) && (typeof window === 'undefined' || window.innerWidth > 760); return; }
                const selected = focusedKey && entry.ownerKeys.includes(focusedKey);
                entry.group.traverse((object) => {
                    (Array.isArray(object.material) ? object.material : [object.material]).filter(Boolean).forEach((material) => {
                        material.transparent = true;
                        material.opacity = mapOpacity * (object.isLineSegments ? (districtMode ? 0.44 : 0.64) : 1) * entry.focusWeight;
                        material.depthWrite = mapOpacity > 0.99 && !object.isLineSegments;
                    });
                });
                if (districtMode) {
                    entry.mesh.material[0].color.copy(entry.identityColor).lerp(entry.metricColor, choropleth);
                    entry.mesh.material[1].color.copy(entry.mesh.material[0].color).multiplyScalar(0.43);
                }
            });
        },
        afterRender() {
            root.updateMatrixWorld(true);
            labels.forEach(({ element, position, key, unitLabel }) => {
                // Constrain the complete HTML label, including long source names, to its own map frame.
                // The depth stays unchanged while the screen-space anchor moves inward when needed.
                scratch.copy(position).project(camera);
                const halfWidth = Math.min(width / 2 - 12, (element.offsetWidth || 120) / 2 + 12);
                const halfHeight = Math.min(height / 2 - 12, (element.offsetHeight || 36) / 2 + 12);
                const limitX = Math.max(0, 1 - (halfWidth * 2 / Math.max(1, width)));
                const limitY = Math.max(0, 1 - (halfHeight * 2 / Math.max(1, height)));
                scratch.x = Math.max(-limitX, Math.min(limitX, scratch.x));
                scratch.y = Math.max(-limitY, Math.min(limitY, scratch.y));
                scratch.unproject(camera);
                stage.place(element, scratch, { opacity: mapOpacity > 0.99 && (!key || (unitLabel ? key === focusedKey : !focusedKey || key === focusedKey)) ? 1 : 0 });
            });
        },
        dispose() {
            disposed = true;
            host.removeEventListener('mappingfocus', onFocus);
            clear();
            flight?.dispose();
            root.parent?.remove(root);
        },
    };
}

export const area6World = (stage, data) => mapWorld(stage, data, 'area6');
export const penetrationWorld = (stage, data) => mapWorld(stage, data, 'penetration');
export const globeWorld = area6World;
export const descentWorld = penetrationWorld;
