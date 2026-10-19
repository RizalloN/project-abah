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
    stage.animated = true;
    stage.useStudio(0.85);
    stage.useLights({ key: [-3, 7, 5], keyIntensity: 2.1, shadowSize: 5 });
    stage.shadowFloor(18, 0.4, -0.28);
    const root = new THREE.Group();
    scene.add(root);
    const chart = new THREE.Group();
    root.add(chart);
    const target = new THREE.Vector3(0, 0.2, 0);
    // These two fine rims are the physical mount, not additional market-share series.
    const mountMaterial = new THREE.MeshPhysicalMaterial({ color: 0x91aec4, metalness: 0.9, roughness: 0.26, clearcoat: 0.45 });
    const mountGeometry = new THREE.TorusGeometry(2.88, 0.025, 10, 144);
    const innerGeometry = new THREE.TorusGeometry(1.29, 0.018, 8, 112);
    const mounts = [mountGeometry, innerGeometry].map((geometry) => {
        const mesh = new THREE.Mesh(geometry, mountMaterial);
        mesh.rotation.x = -Math.PI / 2;
        mesh.position.y = -0.16;
        mesh.castShadow = true;
        root.add(mesh);
        return mesh;
    });
    let pieces = [];
    let qualityTracks = [];
    let caption = null;
    let payload = data || {};
    let activeKey = null;
    let sceneProgress = 0;
    let sceneStarted = null;
    let qualityMode = false;
    let morph = null;
    let disposed = false;
    let frameWidth = stage.width || 1;
    let frameHeight = stage.height || 1;
    const staged = Boolean(stage.host?.hasAttribute?.('data-market-stage'));
    const validShare = (value) => typeof value === 'number' && Number.isFinite(value) && value >= 0 && value <= 100;
    const scenes = () => Array.isArray(payload?.market?.scenes) ? payload.market.scenes.filter(Boolean) : [];
    const selectedMarket = () => scenes().find((item) => String(item.key) === activeKey) || payload?.market || {};
    const releaseChart = () => {
        pieces.forEach(({ mesh }) => { mesh.geometry.dispose(); mesh.material.dispose(); });
        qualityTracks.forEach(({ mesh }) => { mesh.geometry.dispose(); mesh.material.dispose(); });
        pieces = [];
        qualityTracks = [];
        chart.clear();
        stage.clearLabels();
        caption = null;
    };
    const shareTag = (bank) => `<i style="--bank:${escape(bank.color)}"></i><b>${escape(bank.label ?? `${bank.share}%`)}</b><span>${escape(String(bank.name || '').replace(/\s*\(agregat\)$/i, ''))}</span>`;
    const shareEntries = (market) => (market.kind === 'quality' ? [] : (Array.isArray(market.competitors) ? market.competitors.filter((bank) => bank && validShare(bank.share) && bank.share > 0) : []));
    /** Re-cut the ring at the given shares; slices stay in order from twelve o'clock. */
    const reshape = (shares) => {
        let start = Math.PI * 0.5;
        pieces.forEach((piece, index) => {
            const arc = Math.max(0.0001, shares[index] / 100 * Math.PI * 2);
            piece.mesh.geometry.dispose();
            piece.mesh.geometry = sectorGeometry(THREE, 1.42, 2.72, start, start + arc, 0.48);
            piece.middle = start + arc / 2;
            start += arc;
        });
    };
    /**
     * Between two share scenes the ring is not rebuilt: it lifts, turns once,
     * and its slices grow or shrink into the new shares while the colours blend.
     */
    const morphTo = (market) => {
        const next = shareEntries(market);
        if (qualityMode || !pieces.length || next.length !== pieces.length) return false;
        morph = {
            started: null,
            swapped: false,
            market,
            from: pieces.map((piece) => piece.share),
            to: next.map((bank) => bank.share),
            fromColors: pieces.map((piece) => piece.mesh.material.color.clone()),
            toColors: next.map((bank) => new THREE.Color(bank.color)),
            banks: next,
        };
        return true;
    };
    const build = (market) => {
        morph = null;
        releaseChart();
        sceneStarted = null;
        qualityMode = market.kind === 'quality';
        let start = Math.PI * 0.5;
        const entries = qualityMode ? (Array.isArray(market.quality) ? market.quality.filter(Boolean).slice(0, 2) : []) : (Array.isArray(market.competitors) ? market.competitors.filter(Boolean) : []);
        if (qualityMode) entries.forEach((entry, index) => {
            const x = (index - (entries.length - 1) / 2) * 3.25;
            const track = new THREE.Mesh(sectorGeometry(THREE, 0.90, 1.38, 0, Math.PI * 2, 0.28), new THREE.MeshPhysicalMaterial({
                color: 0x61778c, metalness: 0.35, roughness: 0.40, clearcoat: 0.55, transparent: true, opacity: 0.38,
            }));
            track.position.set(x, -0.04, 0);
            track.castShadow = true;
            chart.add(track);
            qualityTracks.push({ mesh: track, x, index });
        });
        pieces = entries.filter((bank) => validShare(bank.share) && bank.share > 0).map((bank, index) => {
            const sourceIndex = entries.indexOf(bank);
            const arc = bank.share / 100 * Math.PI * 2;
            const beginning = qualityMode ? Math.PI * 0.5 : start;
            const middle = beginning + arc / 2;
            const material = new THREE.MeshPhysicalMaterial({ color: bank.color, roughness: index === 0 ? 0.19 : 0.34, metalness: index === 0 ? 0.30 : 0.16, clearcoat: 0.9, clearcoatRoughness: 0.14, emissive: bank.color, emissiveIntensity: 0.035 });
            // Quality shares have two independent full-scale tracks; only deposit/loan shares compose one whole.
            const mesh = new THREE.Mesh(qualityMode
                ? sectorGeometry(THREE, 0.90, 1.38, beginning, beginning + arc, 0.30)
                : sectorGeometry(THREE, 1.42, 2.72, beginning, beginning + arc, 0.48), material);
            if (!qualityMode) start += arc;
            mesh.castShadow = true;
            mesh.receiveShadow = true;
            chart.add(mesh);
            // Every slice prints its own share on its top face, in the slice colour.
            const tag = stage.label('pres-world__label--market-share', shareTag(bank));
            return {
                mesh, index: sourceIndex, middle, tag, share: bank.share,
                x: qualityMode ? (sourceIndex - (entries.length - 1) / 2) * 3.25 : 0,
                radius: qualityMode ? 1.14 : 2.07,
                top: qualityMode ? 0.34 : 0.52,
            };
        });
        mounts.forEach((mount) => { mount.visible = !qualityMode && pieces.length > 0; });
        const qualityLabels = entries.map((entry) => `${entry.name}: ${entry.label ?? 'Belum tersedia'}`).join(' · ');
        if (entries.length) {
            caption = stage.label('pres-world__label--market-meta', `<b>${escape(market.segment)}</b><span>${escape(qualityMode ? qualityLabels : market.period)}</span>${qualityMode ? '<small>Masing-masing terhadap SML atau NPL industri, skala 0–100%</small>' : ''}`);
            if (caption.style) {
                caption.style.whiteSpace = 'normal';
                caption.style.width = 'max-content';
                caption.style.maxWidth = 'calc(100% - 24px)';
                caption.style.translate = '-50% 0';
            }
        }
    };
    if (staged) {
        const requested = String(stage.host.dataset?.marketScene || '');
        activeKey = scenes().find((item) => String(item.key) === requested)
            ? requested
            : (scenes().length ? String(scenes()[0].key) : null);
        const requestedProgress = Number(stage.host.dataset?.marketProgress);
        if (Number.isFinite(requestedProgress)) sceneProgress = clamp01(requestedProgress);
    }
    build(selectedMarket());
    const onFocus = (event) => {
        if (disposed || !staged) return;
        const key = String(event.detail?.key ?? '');
        const selected = scenes().find((item) => String(item.key) === key);
        if (!selected) return;
        if (typeof event.detail?.progress === 'number' && Number.isFinite(event.detail.progress)) sceneProgress = clamp01(event.detail.progress);
        if (key !== activeKey) { activeKey = key; if (!morphTo(selected)) build(selected); }
        if (typeof CustomEvent !== 'undefined') stage.host.dispatchEvent(new CustomEvent('worlddirty', { bubbles: true }));
    };
    if (staged) stage.host.addEventListener('marketfocus', onFocus);
    const captionAnchor = new THREE.Vector3(0, 0, 3.5);
    const shareAnchor = new THREE.Vector3();
    const safeCaptionAnchor = new THREE.Vector3();
    let captionOpacity = 0;
    return {
        resize(width, height) {
            frameWidth = width;
            frameHeight = height;
            stage.shift = { x: 0, y: 0 };
        },
        setData(next) {
            payload = next || {};
            if (activeKey !== null && !scenes().some((item) => String(item.key) === activeKey)) activeKey = null;
            build(selectedMarket());
        },
        update(progress, time, pointer, still) {
            sceneStarted ??= time;
            const entrance = still || stage.motionPaused ? 1 : easeOut(clamp01((time - sceneStarted) / 0.55));
            const p = still ? 1 : staged ? lerp(0.08, 0.62 + sceneProgress * 0.38, entrance) : progress;
            const settle = easeInOut(range(p, 0.05, 0.55));
            const ambient = still ? 0 : time;
            const hover = still ? 0 : Math.sin(ambient * 0.65) * 0.055;
            let arch = 0;
            let turn = 0;
            if (morph) {
                morph.started ??= time;
                const raw = still || stage.motionPaused ? 1 : clamp01((time - morph.started) / 1.15);
                const t = easeInOut(raw);
                arch = Math.sin(raw * Math.PI);
                turn = t * 360;
                reshape(morph.from.map((share, index) => lerp(share, morph.to[index], t)));
                pieces.forEach((piece, index) => {
                    piece.mesh.material.color.lerpColors(morph.fromColors[index], morph.toColors[index], t);
                    piece.mesh.material.emissive.copy(piece.mesh.material.color);
                });
                // The printed shares change while the ring is at its highest, out of reading range.
                if (!morph.swapped && raw >= 0.5) {
                    morph.swapped = true;
                    pieces.forEach((piece, index) => { if (piece.tag) piece.tag.innerHTML = shareTag(morph.banks[index]); });
                    if (caption) caption.innerHTML = `<b>${escape(morph.market.segment)}</b><span>${escape(morph.market.period)}</span>`;
                }
                if (raw >= 1) {
                    pieces.forEach((piece, index) => { piece.share = morph.to[index]; });
                    morph = null;
                    turn = 0;
                }
            }
            pieces.forEach(({ mesh, index, middle, x }) => {
                const local = still ? 1 : easeOut(range(p, 0.025 + index * 0.055, 0.44 + index * 0.045));
                const separation = (1 - local) * 0.7 + arch * (index === 0 ? 0.55 : 0.3);
                mesh.position.set(x + Math.cos(middle) * separation, lerp(index === 0 ? 1.15 : -0.08, 0.14, local) + hover + arch * (index === 0 ? 0.75 : 0.4), -Math.sin(middle) * separation);
                mesh.rotation.y = (1 - local) * (index === 0 ? -32 : 20) * DEG;
                mesh.scale.y = lerp(0.32, 1, local);
                mesh.material.emissiveIntensity = 0.035 + (still ? 0 : (Math.sin(ambient * 0.42 + index * 1.7) + 1) * 0.018);
            });
            qualityTracks.forEach(({ mesh, x, index }) => {
                mesh.position.set(x, -0.04 - (1 - settle) * (0.15 + index * 0.12) + hover, 0);
            });
            root.rotation.y = (lerp(qualityMode ? -12 : -30, qualityMode ? 3 : 15, settle) + turn + (still ? 0 : Math.sin(ambient * 0.13) * (qualityMode ? 3 : 7))) * DEG;
            root.rotation.x = arch * 10 * DEG;
            mounts.forEach((mount, index) => { mount.position.y = -0.16 - (1 - settle) * (0.2 + index * 0.12); });
            const compactStage = staged && frameHeight < 200 && frameWidth / frameHeight > 1.65;
            const cameraDistance = compactStage ? (qualityMode ? 9 : 11) : lerp(16.2, 14.2, settle);
            orbit(camera, { target, distance: cameraDistance, azimuth: lerp(-22, 8, settle) + (still ? 0 : Math.sin(ambient * 0.17) * 2.2), elevation: lerp(29, 49, settle) + (still ? 0 : Math.sin(ambient * 0.14) * 1.5), pointer });
            captionOpacity = easeOut(range(p, 0.35, 0.65)) * (1 - arch * 0.95);
        },
        afterRender(progress) {
            pieces.forEach(({ mesh, middle, radius, top, tag }) => {
                if (!tag) return;
                // Geometry is rotated onto the floor: shape (x, y) maps to local (x, depth, -y).
                shareAnchor.set(Math.cos(middle) * radius, top, -Math.sin(middle) * radius);
                mesh.localToWorld(shareAnchor);
                stage.place(tag, shareAnchor, { opacity: captionOpacity });
            });
            if (!caption) return;
            safeCaptionAnchor.copy(captionAnchor).project(camera);
            const marginX = Math.min(frameWidth / 2, (caption.offsetWidth || 220) / 2 + 12);
            const bottom = Math.max(12, frameHeight - (caption.offsetHeight || 64) - 12);
            safeCaptionAnchor.x = Math.max(-1 + marginX * 2 / frameWidth, Math.min(1 - marginX * 2 / frameWidth, safeCaptionAnchor.x));
            const y = Math.max(12, Math.min(bottom, (1 - safeCaptionAnchor.y) * frameHeight / 2));
            safeCaptionAnchor.y = 1 - y * 2 / frameHeight;
            safeCaptionAnchor.unproject(camera);
            stage.place(caption, safeCaptionAnchor, { opacity: captionOpacity });
        },
        dispose() {
            disposed = true;
            if (staged) stage.host.removeEventListener('marketfocus', onFocus);
            releaseChart();
            mountGeometry.dispose(); innerGeometry.dispose(); mountMaterial.dispose();
        },
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
    stage.animated = true;
    stage.useStudio(1.1);
    stage.useLights({ key: [3, 8, 5], keyIntensity: 2.8, shadowSize: 5 });
    stage.shadowFloor(20, 0.5);
    const geometry = coinGeometry(THREE);
    const target = new THREE.Vector3(0, 1.15, 0);
    const matrix = new THREE.Matrix4();
    const quaternion = new THREE.Quaternion();
    const scale = new THREE.Vector3();
    const position = new THREE.Vector3();
    const coinEuler = new THREE.Euler();
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
                    const approach = (stack.stackIndex % 2 === 0 ? -1 : 1) * fall;
                    position.set(stack.x + jitter.x + approach * 0.22, 0.047 + (index * 0.093) + (fall * 1.6), jitter.z + fall * 0.32);
                    coinEuler.set(jitter.tilt + fall * 0.18, jitter.spin + (fall * 2.4), approach * 0.12);
                    quaternion.setFromEuler(coinEuler);
                    scale.setScalar(k > 0 ? 1 : 0.0001);
                    matrix.compose(position, quaternion, scale);
                    stack.mesh.setMatrixAt(index, matrix);
                }
                stack.mesh.instanceMatrix.needsUpdate = true;
            });
            if (stage.keyLight) stage.keyLight.intensity = 2.8 + (still ? 0 : Math.sin(time * 0.22) * 0.12);
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
    stage.animated = true;
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
                towers.push({ tower, glass, row, column, value: finite(segment.value) });
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
            towers.forEach(({ tower, glass, row, column }) => {
                const start = 0.04 + (row * 0.06) + (column * 0.05);
                const k = still ? 1 : easeOut(range(p, start, start + 0.36));
                tower.scale.set(1, Math.max(0.001, k), 1);
                glass.material.emissiveIntensity = 0.12 + k * 0.04 + (still ? 0 : (Math.sin(time * 0.35 - row * 0.55 - column * 0.4) + 1) * 0.025);
            });
            city.rotation.y = ((1 - easeOut(range(p, 0.02, 0.55))) * -8 + (still ? 0 : Math.sin(time * 0.15) * 1.4)) * DEG;
            orbit(camera, { target, distance: lerp(15.5, 13.2, easeInOut(p)), azimuth: lerp(46, 18, p) + (still ? 0 : Math.sin(time * 0.16) * 1.2), elevation: lerp(16, 28, easeInOut(p)), pointer });
        },
        afterRender(progress) {
            const reveal = range(progress, 0.32, 0.5);
            city.updateMatrixWorld(true);
            rowLabels.forEach(({ element, position }) => stage.place(element, city.localToWorld(position.clone()), { opacity: reveal, hide: reveal <= 0 }));
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
    stage.animated = true;
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
                capsule.liquid.visible = capsule.ratio * fill > 0;
                capsule.meniscus.visible = capsule.ratio * fill > 0;
                capsule.bubbles.visible = capsule.ratio * fill > 0;
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
            orbit(camera, { target, distance: lerp(12.6, 11.4, easeInOut(p)), azimuth: lerp(-14, 7, p) + (still ? 0 : Math.sin(time * 0.16) * 1.3), elevation: lerp(4, 10, p), pointer });
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
    stage.animated = true;
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
            const tracer = new THREE.Mesh(new THREE.SphereGeometry(0.052, 16, 12), new THREE.MeshPhysicalMaterial({ color: 0xffffff, emissive: tone, emissiveIntensity: 0.65, metalness: 0.25, roughness: 0.12 }));
            group.add(curtain, tube, tracer);
            root.add(group);
            const tag = stage.label('pres-world__label--series', `<i style="--tone:${escape(item.color || '#4c95ff')}"></i><b>${escape(item.label)}</b>`);
            const readings = points.map((point, pointIndex) => ({
                element: stage.label('pres-world__label--reading', `<b>${escape((item.display || [])[item.values.length - count + pointIndex] || '')}</b>`),
                position: point.clone().add(new THREE.Vector3(0, 0.32, 0)),
            }));
            return { group, tube, curtain, tracer, curve, beads, points, readings, tag, samples, z };
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
            ribbons.forEach((ribbon, index) => {
                const drawn = still ? 1 : Math.min(1, ((focusX + 1.6) / Math.max(0.001, length)));
                const tubeCount = ribbon.tube.geometry.index.count;
                ribbon.tube.geometry.setDrawRange(0, Math.ceil((tubeCount / 6) * drawn) * 6);
                ribbon.curtain.geometry.setDrawRange(0, Math.ceil(ribbon.samples * drawn) * 6);
                ribbon.beads.forEach((bead) => { bead.visible = bead.position.x <= (drawn * length) + 0.01; });
                ribbon.tracer.visible = !still && drawn > 0.02;
                if (ribbon.tracer.visible) {
                    const along = ((time * 0.055 + index * 0.29) % 1) * drawn;
                    ribbon.curve.getPoint(along, ribbon.tracer.position);
                }
                ribbon.tube.material.emissiveIntensity = 0.45 + (still ? 0 : (Math.sin(time * 0.32 - index * 0.8) + 1) * 0.045);
            });
            target.set(focusX + 1.4, 1.15, 0);
            const fit = camera.userData.fit || 1;
            camera.position.set(focusX - (4.4 * fit) + (pointer.x * 0.3) + (still ? 0 : Math.sin(time * 0.13) * 0.08), (3.6 * fit) - (pointer.y * 0.25), 9.8 * fit);
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
    stage.animated = true;
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
            return { group, cap, label, index, height };
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
                pillar.cap.material.emissiveIntensity = 0.9 + (still ? 0 : (Math.sin(time * 0.7 - pillar.index * 0.65) + 1) * 0.16);
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
