import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';

/*
 * Chapter sculptures. Each one is built from that chapter's own figures, read
 * through the scroll renderer's accessors, so the object is a chart with mass:
 * a height, a fill or a slice always encodes a real value, and every value the
 * object encodes is also printed in a real HTML label beside it.
 */

const DEG = Math.PI / 180;
const clamp01 = (value) => Math.min(1, Math.max(0, value));
const range = (value, start, end) => clamp01((value - start) / (end - start));
const easeOut = (t) => 1 - ((1 - t) ** 3);
const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - (((-2 * t) + 2) ** 3) / 2);
const easeBack = (t) => {
    const c1 = 1.4;
    return 1 + ((c1 + 1) * ((t - 1) ** 3)) + (c1 * ((t - 1) ** 2));
};
const lerp = (from, to, t) => from + ((to - from) * t);
const finite = (value) => (Number.isFinite(Number(value)) ? Number(value) : 0);
const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));

/** Orbit the camera around a target by azimuth/elevation, plus a little pointer sway. */
function orbit(camera, { target, distance: base, azimuth, elevation, pointer }) {
    const distance = base * (camera.userData.fit || 1);
    const az = (azimuth + (pointer.x * 3)) * DEG;
    const el = (elevation - (pointer.y * 2)) * DEG;
    camera.position.set(
        target.x + (distance * Math.cos(el) * Math.sin(az)),
        target.y + (distance * Math.sin(el)),
        target.z + (distance * Math.cos(el) * Math.cos(az)),
    );
    camera.lookAt(target);
}

/** Lead copy keeps the left, so the subject takes the right, and the reverse for trail. */
function sideShift(stage, width, height, side) {
    const wide = width / height > 1.15;
    stage.shift = wide ? { x: side === 'trail' ? -0.24 : 0.24, y: 0.02 } : { x: 0, y: 0.18 };
    return wide;
}

function disposeGroup(group) {
    group.traverse((object) => {
        object.geometry?.dispose?.();
        if (object.material && !object.material.userData?.shared) object.material.dispose?.();
    });
    group.parent?.remove(group);
}

/* =================================================== Pasar: market ring == */

function sectorGeometry(THREE, inner, outer, start, end, depth) {
    const shape = new THREE.Shape();
    shape.absarc(0, 0, outer, start, end, false);
    shape.absarc(0, 0, inner, end, start, true);
    shape.closePath();
    const geometry = new THREE.ExtrudeGeometry(shape, {
        depth, bevelEnabled: true, bevelThickness: 0.035, bevelSize: 0.03, bevelSegments: 4, curveSegments: Math.max(8, Math.round((end - start) / (Math.PI * 2) * 160)),
    });
    geometry.rotateX(-Math.PI / 2);
    return geometry;
}

export function marketRingWorld(stage, data) {
    const { THREE, scene, camera } = stage;
    stage.useStudio(0.5);
    stage.useLights({ key: [3.5, 7, 4], shadowSize: 4 });
    stage.shadowFloor(16, 0.45);
    const root = new THREE.Group();
    scene.add(root);
    const target = new THREE.Vector3(0, 0.25, 0);
    let rings = [];

    const shareMaterial = (lightness) => new THREE.MeshPhysicalMaterial({ color: new THREE.Color().setHSL(0.6, 0.9, lightness), roughness: 0.3, metalness: 0, clearcoat: 0.7, clearcoatRoughness: 0.12 });
    const restMaterial = new THREE.MeshPhysicalMaterial({ color: 0x8fa6c8, roughness: 0.45, metalness: 0, clearcoat: 1, clearcoatRoughness: 0.25, transparent: true, opacity: 0.24, depthWrite: false });

    const build = (next) => {
        rings.forEach(({ group }) => disposeGroup(group));
        stage.clearLabels();
        const market = next?.market || {};
        const entries = [market.total, ...(market.branches || [])].filter((entry) => entry && finite(entry.share) > 0).slice(0, 5);
        const widths = entries.map((_, index) => (index === 0 ? [1.42, 2.02, 0.34] : [1.2 - ((index - 1) * 0.25), 1.36 - ((index - 1) * 0.25), 0.2]));
        rings = entries.map((entry, index) => {
            const [inner, outer, depth] = widths[index];
            const share = Math.min(0.999, finite(entry.share) / 100) * Math.PI * 2;
            const start = Math.PI * 0.5;
            const group = new THREE.Group();
            const slice = new THREE.Mesh(sectorGeometry(THREE, inner, outer, start, start + share, depth), shareMaterial(index === 0 ? 0.36 : 0.4 + (index * 0.06)));
            const rest = new THREE.Mesh(sectorGeometry(THREE, inner, outer, start + share + 0.03, start + (Math.PI * 2) - 0.03, depth * 0.82), restMaterial);
            rest.material.userData.shared = true;
            slice.castShadow = true;
            rest.castShadow = true;
            group.add(rest);
            const lift = new THREE.Group();
            lift.add(slice);
            group.add(lift);
            group.position.y = index === 0 ? 0 : 0.06 + (index * 0.12);
            root.add(group);
            const label = index === 0 ? stage.label('pres-world__label--lead', `<b>${escape(entry.label)}</b><span>${escape(entry.name)}</span>`) : null;
            const middle = start + (share / 2);
            const anchor = new THREE.Vector3(Math.cos(middle) * (outer + 0.12), depth + 0.2, -Math.sin(middle) * (outer + 0.12));
            const swatch = `#${slice.material.color.getHexString()}`;
            return { group, lift, slice, label, anchor, index, depth, entry, swatch };
        });
        // Branch rings read from one legend beside the sculpture, inner ring last.
        const branches = rings.filter((ring) => ring.index > 0);
        legend = branches.length ? stage.label('pres-world__label--legend', branches.map((ring) => `<p><i style="--tone:${ring.swatch}"></i><span>${escape(ring.entry.name)}</span><b>${escape(ring.entry.label)}</b></p>`).join('')) : null;
    };
    let legend = null;
    const legendAnchor = new THREE.Vector3(0, 0, 2.6);
    build(data);
    const anchorWorld = new THREE.Vector3();

    return {
        resize(width, height) {
            const wide = sideShift(stage, width, height, 'lead');
            stage.shift = wide ? { x: 0.22, y: -0.12 } : { x: 0, y: 0.2 };
        },
        setData(next) { build(next); },
        update(progress, time, pointer, still) {
            const p = still ? 1 : progress;
            rings.forEach((ring) => {
                const k = easeBack(range(p, 0.04 + (ring.index * 0.07), 0.4 + (ring.index * 0.07)));
                ring.lift.position.y = lerp(-0.9, 0.16 * easeOut(range(p, 0.5, 0.8)), Math.min(1, k));
                ring.lift.rotation.y = (1 - Math.min(1, k)) * -80 * DEG;
                ring.slice.material.opacity = 1;
            });
            root.rotation.y = (still ? 0 : time * 0.04) + (p * 50 * DEG);
            orbit(camera, { target, distance: lerp(15.4, 13.8, easeInOut(p)), azimuth: lerp(-24, 8, p), elevation: lerp(26, 38, p), pointer });
        },
        afterRender(progress) {
            const reveal = range(progress, 0.42, 0.56);
            rings.forEach((ring) => {
                if (!ring.label) return;
                anchorWorld.copy(ring.anchor);
                ring.lift.localToWorld(anchorWorld);
                stage.place(ring.label, anchorWorld, { opacity: reveal, hide: reveal <= 0 });
            });
            if (legend) stage.place(legend, legendAnchor, { opacity: reveal, hide: reveal <= 0 });
        },
        dispose() { restMaterial.dispose(); },
    };
}

/* ====================================================== Dana: coin vault == */

function coinGeometry(THREE) {
    const profile = [
        [0, -0.046], [0.5, -0.046], [0.528, -0.042], [0.546, -0.03], [0.55, -0.012], [0.55, 0.012],
        [0.546, 0.03], [0.528, 0.042], [0.5, 0.046], [0.474, 0.046], [0.462, 0.036], [0.0, 0.036],
    ].map(([x, y]) => new THREE.Vector2(x, y));
    const geometry = new THREE.LatheGeometry(profile, 72);
    geometry.computeVertexNormals();
    return geometry;
}

const COIN_FINISH = {
    tabungan: { color: 0xe7b85a, roughness: 0.24 },
    deposito: { color: 0xdfe5ee, roughness: 0.2 },
    giro: { color: 0xd08f68, roughness: 0.27 },
};

export function coinVaultWorld(stage, data) {
    const { THREE, scene, camera } = stage;
    stage.useStudio(1.1);
    stage.useLights({ key: [3, 8, 5], keyIntensity: 2.8, shadowSize: 5 });
    stage.shadowFloor(20, 0.5);
    const geometry = coinGeometry(THREE);
    const target = new THREE.Vector3(0, 1.15, 0);
    const matrix = new THREE.Matrix4();
    const quaternion = new THREE.Quaternion();
    const scale = new THREE.Vector3();
    const position = new THREE.Vector3();
    const axis = new THREE.Vector3(0, 1, 0);
    let stacks = [];

    const build = (next) => {
        stacks.forEach(({ mesh }) => { scene.remove(mesh); mesh.material.dispose(); mesh.dispose(); });
        stage.clearLabels();
        const products = (next?.funding?.products || []).filter((product) => finite(product.value) > 0);
        const maximum = Math.max(1, ...products.map((product) => finite(product.value)));
        const spacing = products.length > 2 ? 1.55 : 1.8;
        stacks = products.map((product, stackIndex) => {
            const count = Math.max(1, Math.round((finite(product.value) / maximum) * 26));
            const finish = COIN_FINISH[product.key] || COIN_FINISH.tabungan;
            const material = new THREE.MeshPhysicalMaterial({ color: finish.color, metalness: 1, roughness: finish.roughness, clearcoat: 0.4, clearcoatRoughness: 0.3 });
            const mesh = new THREE.InstancedMesh(geometry, material, count);
            mesh.castShadow = true;
            mesh.receiveShadow = true;
            const x = (stackIndex - ((products.length - 1) / 2)) * spacing;
            const jitter = Array.from({ length: count }, (_, index) => ({
                x: (Math.sin((index + 1) * 12.9898 + stackIndex) * 0.022),
                z: (Math.cos((index + 1) * 78.233 + stackIndex) * 0.022),
                spin: ((index * 47) % 360) * DEG,
                tilt: (Math.sin(index * 3.1) * 0.6) * DEG,
            }));
            scene.add(mesh);
            const label = stage.label('pres-world__label--base', `<b>${escape(product.label)}</b><span>${escape(product.formatted)}</span><small>${escape(product.shareLabel || '')}</small>`);
            return { mesh, count, x, jitter, label, stackIndex, base: new THREE.Vector3(x, -0.05, 0.85) };
        });
    };
    build(data);

    return {
        resize(width, height) {
            sideShift(stage, width, height, 'trail');
        },
        setData(next) { build(next); },
        update(progress, time, pointer, still) {
            const p = still ? 1 : progress;
            stacks.forEach((stack) => {
                for (let index = 0; index < stack.count; index += 1) {
                    const start = 0.04 + (stack.stackIndex * 0.05) + ((index / 26) * 0.5);
                    const k = still ? 1 : range(p, start, start + 0.07);
                    const fall = 1 - easeOut(k);
                    const jitter = stack.jitter[index];
                    position.set(stack.x + jitter.x, 0.047 + (index * 0.093) + (fall * 1.6), jitter.z);
                    quaternion.setFromAxisAngle(axis, jitter.spin + (fall * 2.4));
                    scale.setScalar(k > 0 ? 1 : 0.0001);
                    matrix.compose(position, quaternion, scale);
                    stack.mesh.setMatrixAt(index, matrix);
                }
                stack.mesh.instanceMatrix.needsUpdate = true;
            });
            orbit(camera, { target, distance: lerp(11, 9.6, easeInOut(p)), azimuth: lerp(-22, 14, p) + (still ? 0 : Math.sin(time * 0.2) * 1.5), elevation: lerp(14, 22, p), pointer });
        },
        afterRender(progress) {
            const reveal = range(progress, 0.3, 0.46);
            stacks.forEach((stack) => stage.place(stack.label, stack.base, { opacity: reveal, hide: reveal <= 0 }));
        },
        dispose() { geometry.dispose(); },
    };
}

/* ================================================ Pinjaman: glass skyline == */

const SEGMENT_GLASS = {
    sme: 0x2f7dff,
    consumer: 0x19c3d6,
    micro: 0xff9f1c,
};

export function glassSkylineWorld(stage, data) {
    const { THREE, scene, camera } = stage;
    stage.useStudio(1.0);
    stage.useLights({ key: [6, 10, 7], keyIntensity: 2.6, shadowSize: 8 });
    stage.shadowFloor(30, 0.5, -0.12);
    const target = new THREE.Vector3(0, 1.1, 0);
    const box = new THREE.BoxGeometry(1, 1, 1).translate(0, 0.5, 0);
    const plateGeometry = new THREE.BoxGeometry(1, 0.014, 1);
    const plateMaterial = new THREE.MeshStandardMaterial({ color: 0x5f6f86, roughness: 0.7, metalness: 0.2 });
    const coreMaterial = new THREE.MeshStandardMaterial({ color: 0x2a2f38, emissive: 0xffd39a, emissiveIntensity: 0.9, roughness: 0.5 });
    const plinthMaterial = new THREE.MeshPhysicalMaterial({ color: 0x0c1c33, roughness: 0.28, metalness: 0.2, clearcoat: 1, clearcoatRoughness: 0.15 });
    [plateMaterial, coreMaterial, plinthMaterial].forEach((material) => { material.userData.shared = true; });
    const city = new THREE.Group();
    scene.add(city);
    let towers = [];
    let rowLabels = [];

    const build = (next) => {
        disposeGroup(city);
        city.clear();
        scene.add(city);
        stage.clearLabels();
        const branches = next?.credit?.branches || [];
        const segmentKeys = [...new Set(branches.flatMap((branch) => (branch.segments || []).map((segment) => segment.key)))];
        const maximum = Math.max(1, ...branches.flatMap((branch) => (branch.segments || []).map((segment) => finite(segment.value))));
        const pitch = 1.18;
        const width = 0.8;
        const columns = Math.max(1, segmentKeys.length);
        const rows = Math.max(1, branches.length);
        const plinth = new THREE.Mesh(new RoundedBoxGeometry((columns * pitch) + 0.6, 0.16, (rows * pitch) + 0.6, 4, 0.06), plinthMaterial);
        plinth.position.y = -0.08;
        plinth.receiveShadow = true;
        plinth.castShadow = true;
        city.add(plinth);
        towers = [];
        branches.forEach((branch, row) => {
            (branch.segments || []).forEach((segment) => {
                const column = segmentKeys.indexOf(segment.key);
                const height = 0.14 + (3.6 * finite(segment.value) / maximum);
                const tower = new THREE.Group();
                tower.position.set((column - ((columns - 1) / 2)) * pitch, 0, (row - ((rows - 1) / 2)) * pitch);
                const glass = new THREE.Mesh(box, new THREE.MeshPhysicalMaterial({
                    color: SEGMENT_GLASS[segment.key] || 0x9fc1ff, roughness: 0.04, metalness: 0.1, clearcoat: 1, clearcoatRoughness: 0.04,
                    transparent: true, opacity: 0.3, envMapIntensity: 1.5, depthWrite: false, emissive: SEGMENT_GLASS[segment.key] || 0x9fc1ff, emissiveIntensity: 0.16,
                }));
                glass.scale.set(width, height, width);
                glass.castShadow = true;
                glass.renderOrder = 2;
                const floors = Math.max(1, Math.floor(height / 0.17));
                const plates = new THREE.InstancedMesh(plateGeometry, plateMaterial, floors);
                const matrix = new THREE.Matrix4();
                for (let index = 0; index < floors; index += 1) {
                    matrix.makeScale(width * 0.92, 1, width * 0.92).setPosition(0, (index + 0.5) * (height / floors), 0);
                    plates.setMatrixAt(index, matrix);
                }
                plates.castShadow = true;
                const core = new THREE.Mesh(box, coreMaterial);
                core.scale.set(width * 0.26, height * 0.98, width * 0.26);
                tower.add(core, plates, glass);
                city.add(tower);
                towers.push({ tower, row, column, value: finite(segment.value) });
            });
        });
        rowLabels = branches.map((branch, row) => ({
            element: stage.label('pres-world__label--row', `<b>${escape(branch.name)}</b><span>${escape(branch.formatted || '')}</span>`),
            position: new THREE.Vector3((-((columns - 1) / 2) * pitch) - (pitch * 0.85), 0.1, (row - ((rows - 1) / 2)) * pitch),
        }));
        segmentKeys.forEach((key, column) => {
            const segment = branches.flatMap((branch) => branch.segments || []).find((item) => item.key === key);
            rowLabels.push({
                element: stage.label('pres-world__label--column', `<i style="--tone:#${(SEGMENT_GLASS[key] || 0x9fc1ff).toString(16).padStart(6, '0')}"></i><b>${escape(segment?.label || key)}</b>`),
                position: new THREE.Vector3((column - ((columns - 1) / 2)) * pitch, 0.02, ((rows - 1) / 2) * pitch + (pitch * 0.85)),
            });
        });
        target.set(0, 1.0, 0);
    };
    build(data);

    return {
        resize(width, height) {
            const wide = sideShift(stage, width, height, 'lead');
            if (!wide) stage.shift = { x: 0.1, y: 0.18 };
        },
        setData(next) { build(next); },
        update(progress, time, pointer, still) {
            const p = still ? 1 : progress;
            towers.forEach(({ tower, row, column }) => {
                const start = 0.04 + (row * 0.06) + (column * 0.05);
                const k = still ? 1 : easeOut(range(p, start, start + 0.36));
                tower.scale.set(1, Math.max(0.001, k), 1);
            });
            orbit(camera, { target, distance: lerp(15.5, 13.2, easeInOut(p)), azimuth: lerp(46, 18, p) + (still ? 0 : Math.sin(time * 0.16) * 1.2), elevation: lerp(16, 28, easeInOut(p)), pointer });
        },
        afterRender(progress) {
            const reveal = range(progress, 0.32, 0.5);
            rowLabels.forEach(({ element, position }) => stage.place(element, position, { opacity: reveal, hide: reveal <= 0 }));
        },
        dispose() {
            box.dispose();
            plateGeometry.dispose();
            [plateMaterial, coreMaterial, plinthMaterial].forEach((material) => material.dispose());
        },
    };
}

/* ============================================== Kualitas: risk capsules == */

const CAPSULE = { radius: 0.52, length: 2.3 };
const LIQUID = { radius: 0.47, length: 2.3 };
const INNER_BOTTOM = -(LIQUID.length / 2) - LIQUID.radius;
const INNER_HEIGHT = LIQUID.length + (LIQUID.radius * 2);

/** Radius of the inner capsule cross-section at a given height, for the meniscus. */
function crossSection(level) {
    const top = LIQUID.length / 2;
    const offset = Math.abs(level) > top ? Math.abs(level) - top : 0;
    return Math.sqrt(Math.max(0.0001, (LIQUID.radius ** 2) - (offset ** 2)));
}

export function riskCapsuleWorld(stage, data) {
    const { THREE, scene, camera, renderer } = stage;
    renderer.localClippingEnabled = true;
    stage.useStudio(1.2);
    stage.useLights({ key: [3, 6, 5], keyIntensity: 2.2, shadowSize: 4 });
    stage.shadowFloor(16, 0.5, INNER_BOTTOM - 0.2);
    const target = new THREE.Vector3(0, 0, 0);
    const group = new THREE.Group();
    scene.add(group);
    const shellGeometry = new THREE.CapsuleGeometry(CAPSULE.radius, CAPSULE.length, 16, 64);
    const liquidGeometry = new THREE.CapsuleGeometry(LIQUID.radius, LIQUID.length, 16, 48);
    const bubbleGeometry = new THREE.SphereGeometry(1, 12, 8);
    const shellMaterial = new THREE.MeshPhysicalMaterial({ color: 0xffffff, roughness: 0.03, metalness: 0, clearcoat: 1, clearcoatRoughness: 0.02, transparent: true, opacity: 0.16, envMapIntensity: 2.2, depthWrite: false, side: THREE.DoubleSide });
    const baseMaterial = new THREE.MeshPhysicalMaterial({ color: 0x1b2230, metalness: 0.9, roughness: 0.28, clearcoat: 0.6 });
    const tickMaterial = new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.38 });
    [shellMaterial, baseMaterial, tickMaterial].forEach((material) => { material.userData.shared = true; });
    let capsules = [];
    let ticks = [];

    const build = (next) => {
        capsules.forEach((capsule) => disposeGroup(capsule.group));
        stage.clearLabels();
        const quality = next?.quality || {};
        const entries = [
            { key: 'sml', title: 'SML', tone: 0xf08a0c, ...(quality.sml || {}) },
            { key: 'npl', title: 'NPL', tone: 0xd61f2c, ...(quality.npl || {}) },
        ];
        capsules = entries.map((entry, index) => {
            const capsuleGroup = new THREE.Group();
            capsuleGroup.position.x = (index - 0.5) * 1.75;
            const shell = new THREE.Mesh(shellGeometry, shellMaterial);
            shell.renderOrder = 3;
            const plane = new THREE.Plane(new THREE.Vector3(0, -1, 0), INNER_BOTTOM);
            const tone = new THREE.Color(entry.tone);
            const liquid = new THREE.Mesh(liquidGeometry, new THREE.MeshPhysicalMaterial({
                color: tone, roughness: 0.08, metalness: 0, clearcoat: 1, emissive: tone, emissiveIntensity: 0.22, envMapIntensity: 0.5, transparent: true, opacity: 0.96, clippingPlanes: [plane],
            }));
            liquid.castShadow = true;
            const meniscus = new THREE.Mesh(new THREE.CircleGeometry(1, 48), new THREE.MeshPhysicalMaterial({ color: tone.clone().lerp(new THREE.Color(0xffffff), 0.12), roughness: 0.04, emissive: tone, emissiveIntensity: 0.4, clearcoat: 1, envMapIntensity: 0.6 }));
            meniscus.rotation.x = -Math.PI / 2;
            const base = new THREE.Mesh(new THREE.CylinderGeometry(0.66, 0.74, 0.22, 64), baseMaterial);
            base.position.y = INNER_BOTTOM - 0.09;
            base.castShadow = true;
            base.receiveShadow = true;
            const bubbleMaterial = new THREE.MeshPhysicalMaterial({ color: 0xffffff, roughness: 0, transparent: true, opacity: 0.55, clippingPlanes: [plane] });
            const bubbles = new THREE.InstancedMesh(bubbleGeometry, bubbleMaterial, 22);
            const seeds = Array.from({ length: 22 }, (_, seed) => ({ x: Math.sin(seed * 2.3) * 0.28, z: Math.cos(seed * 1.7) * 0.28, speed: 0.12 + ((seed % 5) * 0.04), offset: (seed * 0.137) % 1, size: 0.012 + ((seed % 4) * 0.006) }));
            capsuleGroup.add(base, liquid, meniscus, bubbles, shell);
            group.add(capsuleGroup);
            const ratio = Math.min(1, Math.max(0, finite(entry.ratio) / 100));
            const label = stage.label(`pres-world__label--gauge is-${entry.key}`, `<b>${escape(entry.ratioLabel || '-')}</b><span>${escape(entry.title)} ${escape(entry.nominalLabel || '')}</span>`);
            const title = stage.label('pres-world__label--cap', `<b>${escape(entry.title)}</b><span>skala 0 sampai 100%</span>`);
            return { group: capsuleGroup, liquid, meniscus, plane, bubbles, seeds, ratio, label, title, index };
        });
        ticks.forEach((tick) => tick.mesh.parent?.remove(tick.mesh));
        ticks = [0.25, 0.5, 0.75].map((fraction) => {
            const level = INNER_BOTTOM + (INNER_HEIGHT * fraction);
            const meshes = capsules.map((capsule) => {
                const ring = new THREE.Mesh(new THREE.TorusGeometry(crossSection(level) + 0.055, 0.004, 6, 64), tickMaterial);
                ring.rotation.x = Math.PI / 2;
                ring.position.y = level;
                capsule.group.add(ring);
                return ring;
            });
            return { mesh: meshes[0], level, element: stage.label('pres-world__label--tick', `${Math.round(fraction * 100)}%`) };
        });
    };
    build(data);

    const matrix = new THREE.Matrix4();
    const anchor = new THREE.Vector3();

    return {
        resize(width, height) {
            sideShift(stage, width, height, 'lead');
            stage.shift.x = width / height > 1.15 ? 0.17 : 0;
        },
        setData(next) { build(next); },
        update(progress, time, pointer, still) {
            const p = still ? 1 : progress;
            const fill = easeInOut(range(p, 0.12, 0.62));
            capsules.forEach((capsule) => {
                const level = INNER_BOTTOM + (INNER_HEIGHT * capsule.ratio * fill);
                capsule.level = level;
                capsule.plane.constant = level;
                capsule.meniscus.position.y = level + 0.001;
                capsule.meniscus.scale.setScalar(capsule.ratio * fill > 0.002 ? crossSection(level) : 0.0001);
                for (let index = 0; index < capsule.seeds.length; index += 1) {
                    const seed = capsule.seeds[index];
                    const travel = Math.max(0, level - INNER_BOTTOM - 0.05);
                    const y = INNER_BOTTOM + 0.05 + ((((still ? 0.5 : time * seed.speed) + seed.offset) % 1) * travel);
                    matrix.makeScale(seed.size, seed.size, seed.size).setPosition(seed.x * Math.min(1, crossSection(y) / LIQUID.radius), y, seed.z * Math.min(1, crossSection(y) / LIQUID.radius));
                    capsule.bubbles.setMatrixAt(index, matrix);
                }
                capsule.bubbles.instanceMatrix.needsUpdate = true;
            });
            group.rotation.y = (pointer.x * 8 * DEG) + (still ? 0 : Math.sin(time * 0.3) * 2 * DEG);
            target.y = 0.15;
            orbit(camera, { target, distance: lerp(12.6, 11.4, easeInOut(p)), azimuth: lerp(-10, 6, p), elevation: lerp(4, 10, p), pointer });
        },
        afterRender(progress) {
            const reveal = range(progress, 0.2, 0.36);
            capsules.forEach((capsule) => {
                anchor.set(CAPSULE.radius + 0.18, capsule.level ?? INNER_BOTTOM, 0);
                capsule.group.localToWorld(anchor);
                stage.place(capsule.label, anchor, { opacity: reveal, hide: reveal <= 0 });
                anchor.set(0, (CAPSULE.length / 2) + CAPSULE.radius + 0.32, 0);
                capsule.group.localToWorld(anchor);
                stage.place(capsule.title, anchor, { opacity: reveal, hide: reveal <= 0 });
            });
            ticks.forEach((tick) => {
                anchor.set(-(CAPSULE.radius + 0.2), tick.level, 0);
                capsules[0]?.group.localToWorld(anchor);
                stage.place(tick.element, anchor, { opacity: reveal * 0.9, hide: reveal <= 0 || !capsules.length });
            });
        },
        dispose() {
            [shellGeometry, liquidGeometry, bubbleGeometry].forEach((geometry) => geometry.dispose());
            [shellMaterial, baseMaterial, tickMaterial].forEach((material) => material.dispose());
        },
    };
}

/* ================================================ Tren: the time ribbon == */

export function trendRibbonWorld(stage, data) {
    const { THREE, scene, camera } = stage;
    stage.useStudio(0.9);
    stage.useLights({ key: [4, 9, 6], keyIntensity: 2.2, shadow: false });
    const root = new THREE.Group();
    scene.add(root);
    const grid = new THREE.GridHelper(40, 40, 0x2f5d9e, 0x1c3a66);
    grid.material.transparent = true;
    grid.material.opacity = 0.32;
    scene.add(grid);
    const focus = new THREE.Vector3();
    const target = new THREE.Vector3();
    let ribbons = [];
    let months = [];
    let length = 1;

    const build = (next) => {
        ribbons.forEach(({ group }) => disposeGroup(group));
        stage.clearLabels();
        const trend = next?.trend || {};
        const labels = trend.labels || [];
        const series = (trend.series || []).filter((item) => (item.values || []).some((value) => Number.isFinite(Number(value)))).slice(0, 3);
        const count = Math.max(2, Math.min(labels.length || 0, ...series.map((item) => item.values.length)));
        const values = series.flatMap((item) => item.values.slice(-count).map(Number).filter(Number.isFinite));
        const minimum = Math.min(...values);
        const maximum = Math.max(...values);
        const spread = (maximum - minimum) || Math.max(1, Math.abs(maximum) * 0.1);
        const step = 1.15;
        length = (count - 1) * step;
        const y = (value) => 0.4 + (((Number(value) - minimum) / spread) * 2.1);
        grid.position.x = length / 2;
        ribbons = series.map((item, index) => {
            const group = new THREE.Group();
            const z = (index - ((series.length - 1) / 2)) * 1.2;
            const tone = new THREE.Color(item.color || (index === 0 ? '#4c95ff' : '#3fd09a'));
            const points = item.values.slice(-count).map((value, pointIndex) => new THREE.Vector3(pointIndex * step, Number.isFinite(Number(value)) ? y(value) : 0.4, z));
            const curve = new THREE.CatmullRomCurve3(points, false, 'centripetal');
            const samples = 220;
            const tube = new THREE.Mesh(new THREE.TubeGeometry(curve, samples, 0.03, 14, false), new THREE.MeshPhysicalMaterial({ color: tone, emissive: tone, emissiveIntensity: 0.5, roughness: 0.18, metalness: 0.3, clearcoat: 1 }));
            const curtainPositions = [];
            const curtainColors = [];
            const bottom = tone.clone().multiplyScalar(0.12);
            const top = tone.clone().multiplyScalar(0.9);
            for (let sample = 0; sample <= samples; sample += 1) {
                const point = curve.getPoint(sample / samples);
                curtainPositions.push(point.x, 0, point.z, point.x, point.y, point.z);
                curtainColors.push(bottom.r, bottom.g, bottom.b, top.r, top.g, top.b);
            }
            const indices = [];
            for (let sample = 0; sample < samples; sample += 1) {
                const a = sample * 2;
                indices.push(a, a + 2, a + 1, a + 1, a + 2, a + 3);
            }
            const curtainGeometry = new THREE.BufferGeometry();
            curtainGeometry.setAttribute('position', new THREE.Float32BufferAttribute(curtainPositions, 3));
            curtainGeometry.setAttribute('color', new THREE.Float32BufferAttribute(curtainColors, 3));
            curtainGeometry.setIndex(indices);
            curtainGeometry.computeVertexNormals();
            const curtain = new THREE.Mesh(curtainGeometry, new THREE.MeshPhysicalMaterial({ vertexColors: true, transparent: true, opacity: 0.24, roughness: 0.25, clearcoat: 1, side: THREE.DoubleSide, depthWrite: false }));
            const beads = points.map((point) => {
                const bead = new THREE.Mesh(new THREE.SphereGeometry(0.075, 24, 16), new THREE.MeshPhysicalMaterial({ color: 0xffffff, metalness: 0.6, roughness: 0.12, clearcoat: 1 }));
                bead.position.copy(point);
                group.add(bead);
                return bead;
            });
            group.add(curtain, tube);
            root.add(group);
            const tag = stage.label('pres-world__label--series', `<i style="--tone:${escape(item.color || '#4c95ff')}"></i><b>${escape(item.label)}</b>`);
            const readings = points.map((point, pointIndex) => ({
                element: stage.label('pres-world__label--reading', `<b>${escape((item.display || [])[item.values.length - count + pointIndex] || '')}</b>`),
                position: point.clone().add(new THREE.Vector3(0, 0.32, 0)),
            }));
            return { group, tube, curtain, beads, points, readings, tag, samples, z };
        });
        months = labels.slice(-count).map((label, index) => ({
            element: stage.label('pres-world__label--month', escape(label)),
            position: new THREE.Vector3(index * step, 0.02, ((series.length / 2) * 1.2) + 0.5),
            x: index * step,
        }));
    };
    build(data);

    return {
        resize(width, height) {
            const wide = sideShift(stage, width, height, 'trail');
            stage.shift = wide ? { x: -0.1, y: -0.2 } : { x: 0, y: 0.14 };
        },
        setData(next) { build(next); },
        update(progress, time, pointer, still) {
            const p = still ? 1 : progress;
            const travel = easeInOut(range(p, 0.06, 0.92));
            const focusX = lerp(0, length, travel);
            focus.set(focusX, 0, 0);
            ribbons.forEach((ribbon) => {
                const drawn = still ? 1 : Math.min(1, ((focusX + 1.6) / Math.max(0.001, length)));
                const tubeCount = ribbon.tube.geometry.index.count;
                ribbon.tube.geometry.setDrawRange(0, Math.ceil((tubeCount / 6) * drawn) * 6);
                ribbon.curtain.geometry.setDrawRange(0, Math.ceil(ribbon.samples * drawn) * 6);
                ribbon.beads.forEach((bead) => { bead.visible = bead.position.x <= (drawn * length) + 0.01; });
            });
            target.set(focusX + 1.4, 1.15, 0);
            const fit = camera.userData.fit || 1;
            camera.position.set(focusX - (4.4 * fit) + (pointer.x * 0.3), (3.6 * fit) - (pointer.y * 0.25), 9.8 * fit);
            camera.lookAt(target);
            this.focusX = focusX;
        },
        afterRender(progress) {
            const focusX = this.focusX ?? 0;
            const reveal = range(progress, 0.04, 0.12);
            ribbons.forEach((ribbon) => {
                const last = ribbon.points.findLast((point) => point.x <= focusX + 1.6) || ribbon.points[0];
                stage.place(ribbon.tag, last.clone().add(new THREE.Vector3(0.25, 0.62, 0)), { opacity: reveal });
                ribbon.readings.forEach((reading) => {
                    const distance = Math.abs(reading.position.x - (focusX + 0.9));
                    stage.place(reading.element, reading.position, { opacity: Math.max(0, 1 - (distance / 1.3)) * reveal, hide: distance > 1.3 });
                });
            });
            months.forEach((month) => {
                const distance = Math.abs(month.x - (focusX + 0.9));
                stage.place(month.element, month.position, { opacity: Math.max(0.18, 1 - (distance / 3)) * reveal });
            });
        },
    };
}

/* ============================================= Aksi: eight lit pillars == */

export function strategyPillarsWorld(stage, data) {
    const { THREE, scene, camera } = stage;
    stage.useStudio(1.0);
    stage.useLights({ key: [3, 9, 7], keyIntensity: 2.4, shadowSize: 7 });
    stage.shadowFloor(30, 0.5);
    const target = new THREE.Vector3(0, 1.0, -0.6);
    const pillarGeometry = new RoundedBoxGeometry(0.62, 1, 0.62, 4, 0.07).translate(0, 0.5, 0);
    const capGeometry = new RoundedBoxGeometry(0.66, 0.08, 0.66, 3, 0.03);
    const stoneMaterial = new THREE.MeshPhysicalMaterial({ color: 0x0f2f6b, roughness: 0.2, metalness: 0.25, clearcoat: 1, clearcoatRoughness: 0.1 });
    stoneMaterial.userData.shared = true;
    let pillars = [];

    const build = (next) => {
        pillars.forEach(({ group }) => disposeGroup(group));
        stage.clearLabels();
        const strategies = (next?.strategies || []).slice(0, 8);
        const count = strategies.length;
        pillars = strategies.map((strategy, index) => {
            const angle = count > 1 ? lerp(-62, 62, index / (count - 1)) * DEG : 0;
            const group = new THREE.Group();
            group.position.set(Math.sin(angle) * 3.6, 0, -Math.cos(angle) * 3.6 + 2.4);
            group.rotation.y = -angle;
            const height = 1.7 + ((index % 2) * 0.25);
            const pillar = new THREE.Mesh(pillarGeometry, stoneMaterial);
            pillar.scale.y = height;
            pillar.castShadow = true;
            pillar.receiveShadow = true;
            const tone = new THREE.Color(strategy.tone === 'red' ? 0xff5d6c : 0x3ddc97);
            const cap = new THREE.Mesh(capGeometry, new THREE.MeshPhysicalMaterial({ color: tone, emissive: tone, emissiveIntensity: 1.1, roughness: 0.2, clearcoat: 1 }));
            cap.position.y = height + 0.05;
            group.add(pillar, cap);
            scene.add(group);
            const label = stage.label(`pres-world__label--pillar is-${strategy.tone === 'red' ? 'red' : 'green'}`, `<span>${escape(strategy.title)}</span><b>${escape(strategy.value)}</b><small>${escape(strategy.trend)}</small>`);
            return { group, label, index, height };
        });
    };
    build(data);
    const anchor = new THREE.Vector3();

    return {
        resize(width, height) {
            const wide = sideShift(stage, width, height, 'lead');
            stage.shift = wide ? { x: 0.16, y: -0.08 } : { x: 0, y: 0.12 };
            // The arc is wide; a portrait frame needs extra distance to keep both ends.
            if (!wide) camera.userData.fit *= 1.35;
        },
        setData(next) { build(next); },
        update(progress, time, pointer, still) {
            const p = still ? 1 : progress;
            pillars.forEach((pillar) => {
                const start = 0.04 + (pillar.index * 0.05);
                const k = still ? 1 : easeOut(range(p, start, start + 0.3));
                pillar.group.scale.set(1, Math.max(0.001, k), 1);
                pillar.group.position.y = (1 - k) * -0.2;
            });
            orbit(camera, { target, distance: lerp(17.5, 15.6, easeInOut(p)), azimuth: lerp(-10, 10, p) + (still ? 0 : Math.sin(time * 0.18) * 1.5), elevation: lerp(13, 20, p), pointer });
        },
        afterRender(progress) {
            pillars.forEach((pillar) => {
                const reveal = range(progress, 0.2 + (pillar.index * 0.04), 0.36 + (pillar.index * 0.04));
                anchor.set(0, pillar.height + 0.32, 0);
                pillar.group.localToWorld(anchor);
                stage.place(pillar.label, anchor, { opacity: reveal, hide: reveal <= 0 });
            });
        },
        dispose() {
            pillarGeometry.dispose();
            capGeometry.dispose();
            stoneMaterial.dispose();
        },
    };
}
