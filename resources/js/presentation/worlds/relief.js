import * as THREE from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { StageFX } from './stage-fx.js';

/*
 * The Area 6 relief: 76 real kecamatan, machined from anodised aluminium on a
 * graphite plinth, lit like a product shot. One object for the whole report.
 *
 * Heights move the upper half of each extrusion (cap and top bevel) instead of
 * scaling it, so the bevel that catches the light stays crisp at any height.
 * The Kualitas theatre swaps each piece to smoked glass and lights a core inside
 * it; both material sets are compiled up front so the swap never stalls a frame.
 *
 * Every piece is its own rigid body around its centroid, so the relief can also
 * change form: float apart in layers (explode), separate into the four branch
 * islands (spread), lift one branch out of the rest (levitate), raise the data
 * from flat (grow) and breathe like water (wave). The studio drives all of them
 * from the scroll position.
 */

const FOV = 24;
const UNIT_WIDTH = 6.4;
const PLINTH_DEPTH = 0.16;
const BASE_LIFT = 0.09;
const MAX_LIFT = 1.05;
const BEVEL = 0.018;
const GAP = 0.016;

const clamp01 = (value) => Math.min(1, Math.max(0, value));
const damp = (current, target, lambda, dt) => current + (target - current) * (1 - Math.exp(-lambda * dt));

/** Douglas-Peucker on a lon/lat ring; keeps the shape honest at a fraction of the vertices. */
const simplify = (points, tolerance) => {
    if (points.length <= 6) return points;
    const keep = new Uint8Array(points.length);
    keep[0] = 1;
    keep[points.length - 1] = 1;
    const stack = [[0, points.length - 1]];
    while (stack.length) {
        const [first, last] = stack.pop();
        let index = -1;
        let maxDistance = 0;
        const [ax, ay] = points[first];
        const [bx, by] = points[last];
        const dx = bx - ax;
        const dy = by - ay;
        const length = Math.hypot(dx, dy) || 1e-12;
        for (let i = first + 1; i < last; i += 1) {
            const distance = Math.abs(dy * points[i][0] - dx * points[i][1] + bx * ay - by * ax) / length;
            if (distance > maxDistance) {
                maxDistance = distance;
                index = i;
            }
        }
        if (maxDistance > tolerance && index > 0) {
            keep[index] = 1;
            stack.push([first, index], [index, last]);
        }
    }
    const result = points.filter((_, i) => keep[i]);
    return result.length >= 4 ? result : points;
};

const ringsOf = (geometry) => {
    if (geometry?.type === 'Polygon') return [geometry.coordinates];
    if (geometry?.type === 'MultiPolygon') return geometry.coordinates;
    return [];
};

/** A soft contact shadow texture: darkest under the plinth, fading to nothing. */
const contactTexture = () => {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 256;
    const context = canvas.getContext('2d');
    const gradient = context.createRadialGradient(128, 128, 10, 128, 128, 128);
    gradient.addColorStop(0, 'rgba(0,0,0,0.55)');
    gradient.addColorStop(0.45, 'rgba(0,0,0,0.28)');
    gradient.addColorStop(1, 'rgba(0,0,0,0)');
    context.fillStyle = gradient;
    context.fillRect(0, 0, 256, 256);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    return texture;
};

const easeOutBack = (t) => 1 + 2.2 * ((t - 1) ** 3) + 1.2 * ((t - 1) ** 2);

/** Deterministic 0..1 noise per piece, so the exploded layout is the same on every visit. */
const seeded = (index, salt) => {
    const value = Math.sin(index * 127.1 + salt * 311.7) * 43758.5453;
    return value - Math.floor(value);
};

const BRANCH_OF_REGENCY = { MADIUN: 'madiun', 'KOTA MADIUN': 'madiun', MAGETAN: 'magetan', NGAWI: 'ngawi', PONOROGO: 'ponorogo' };

export class ReliefScene {
    constructor(canvas, features, { maxPixelRatio = 1.75, dustCount = 1100 } = {}) {
        this.canvas = canvas;
        this.renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'high-performance' });
        this.renderer.setClearColor(0x000000, 0);
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.02;
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.maxPixelRatio = maxPixelRatio;

        this.scene = new THREE.Scene();
        const pmrem = new THREE.PMREMGenerator(this.renderer);
        this.environment = pmrem.fromScene(new RoomEnvironment(), 0.035).texture;
        pmrem.dispose();
        this.scene.environment = this.environment;
        this.scene.environmentIntensity = 0.95;

        this.camera = new THREE.PerspectiveCamera(FOV, 1, 0.1, 200);
        this.target = new THREE.Vector3(0, 0.25, 0);

        this.key = new THREE.DirectionalLight(0xffffff, 2.1);
        this.key.castShadow = true;
        this.key.shadow.mapSize.set(2048, 2048);
        this.key.shadow.radius = 5;
        this.key.shadow.blurSamples = 20;
        this.key.shadow.bias = -0.0006;
        this.key.shadow.normalBias = 0.035;
        Object.assign(this.key.shadow.camera, { left: -6, right: 6, top: 6, bottom: -6, near: 1, far: 40 });
        this.rim = new THREE.DirectionalLight(0xbcd4ff, 1.15);
        this.rim.position.set(-6, 5, -7);
        this.fill = new THREE.HemisphereLight(0xffffff, 0x2a2c33, 0.35);
        this.scene.add(this.key, this.key.target, this.rim, this.fill);

        this.pieces = [];
        // Set before build(): the first lift write already reads the arrangement.
        this.arrangement = { explode: 0, spread: 0, levitate: 0, levitateBranch: null, grow: 1, wave: 0 };
        this.arrangementTarget = { ...this.arrangement };
        this.build(features);
        this.state = {
            dark: 0,
            glass: 0,
        };
        this.compiled = false;
        this.intro = 1;
        this.raycaster = new THREE.Raycaster();
        this.hovered = null;
        // Quiet hover: the pointed piece is lit in place, it never lifts or animates.
        this.quietHover = false;
        this.buildArrangement();
        this.fx = new StageFX(this, { dustCount });
        // Camera framing: the whole relief by default, or the pieces a slot focuses on.
        this.view = { x: 0, z: 0, radius: this.radius };
        this.viewGoal = { ...this.view };
        this.zooming = 0;
    }

    /** Frame the camera on a set of kecamatan codes (null frames the whole relief). Returns the framed centre. */
    setFocus(codes = null) {
        const pieces = codes && codes.size ? this.pieces.filter((piece) => codes.has(piece.code)) : [];
        if (!pieces.length) {
            Object.assign(this.viewGoal, { x: 0, z: 0, radius: this.radius, branch: null });
            return this.viewGoal;
        }
        const branches = new Set(pieces.map((piece) => piece.branch));
        let minX = Infinity; let maxX = -Infinity; let minZ = Infinity; let maxZ = -Infinity;
        pieces.forEach((piece) => {
            const half = piece.size * 0.6;
            minX = Math.min(minX, piece.center.x - half); maxX = Math.max(maxX, piece.center.x + half);
            minZ = Math.min(minZ, piece.center.z - half); maxZ = Math.max(maxZ, piece.center.z + half);
        });
        // A single small kecamatan still keeps some context around it.
        const radius = Math.max(this.radius * 0.22, Math.hypot(maxX - minX, maxZ - minZ) / 2 + 0.12);
        // A focus inside one branch follows that branch when it is lifted out of the relief.
        Object.assign(this.viewGoal, { x: (minX + maxX) / 2, z: (minZ + maxZ) / 2, radius, branch: branches.size === 1 ? [...branches][0] : null });
        return this.viewGoal;
    }

    /** Where every piece goes when the relief floats apart, and which way each branch island drifts. */
    buildArrangement() {
        this.branchCenters = new Map();
        this.pieces.forEach((piece) => {
            piece.branch = BRANCH_OF_REGENCY[String(piece.regency).toUpperCase()] || null;
            if (!piece.branch) return;
            if (!this.branchCenters.has(piece.branch)) this.branchCenters.set(piece.branch, { sum: new THREE.Vector3(), count: 0 });
            const entry = this.branchCenters.get(piece.branch);
            entry.sum.add(piece.center);
            entry.count += 1;
        });
        // Each island drifts away from the centre of Area 6, a little more than proportionally so the seams open clearly.
        this.branchDrift = new Map();
        this.branchOffsets = new Map();
        this.branchCenters.forEach((entry, key) => {
            const center = entry.sum.divideScalar(entry.count);
            entry.center = center.clone();
            const drift = new THREE.Vector3(center.x, 0, center.z);
            const length = drift.length() || 1;
            drift.multiplyScalar(0.42).add(drift.clone().divideScalar(length).multiplyScalar(0.42));
            this.branchDrift.set(key, drift);
            this.branchOffsets.set(key, new THREE.Vector3());
        });
        this.pieces.forEach((piece, index) => {
            const outward = new THREE.Vector3(piece.center.x, 0, piece.center.z);
            const ring = clamp01(piece.ring / (this.radius || 1));
            piece.float = {
                // Layered vortex: outer pieces sit higher and further out, every piece turned a little off its seat.
                offset: outward.multiplyScalar(0.3 + seeded(index, 1) * 0.45).setY(0.55 + seeded(index, 2) * 1.25 + ring * 0.85),
                rx: (seeded(index, 3) - 0.5) * 1.3,
                ry: (seeded(index, 4) - 0.5) * 2.2,
                rz: (seeded(index, 5) - 0.5) * 1.3,
                phase: seeded(index, 6) * Math.PI * 2,
            };
        });
    }

    /** explode 0..1, spread 0..1, levitate 0..1 of levitateBranch, grow 0..1 of the data height, wave 0..1. */
    setArrangement(next = {}) {
        Object.assign(this.arrangementTarget, next);
    }

    build(features) {
        const points = features.flatMap((feature) => ringsOf(feature.geometry).flat(2)).filter((point) => Array.isArray(point) && Number.isFinite(point[0]) && Number.isFinite(point[1]));
        const lons = points.map((point) => point[0]);
        const lats = points.map((point) => point[1]);
        const minLon = Math.min(...lons);
        const maxLon = Math.max(...lons);
        const minLat = Math.min(...lats);
        const maxLat = Math.max(...lats);
        const lat0 = (minLat + maxLat) / 2;
        const lon0 = (minLon + maxLon) / 2;
        const correction = Math.cos(lat0 * Math.PI / 180);
        const scale = UNIT_WIDTH / Math.max((maxLon - minLon) * correction, maxLat - minLat);
        this.project = (lon, lat) => [(lon - lon0) * correction * scale, (lat - lat0) * scale];
        const halfWidth = ((maxLon - minLon) * correction * scale) / 2;
        const halfDepth = ((maxLat - minLat) * scale) / 2;
        this.extent = { halfWidth, halfDepth };
        this.radius = Math.hypot(halfWidth, halfDepth) * 0.96 + 0.12;

        // The plinth is Area 6 itself: one graphite extrusion of every kecamatan, no seams.
        this.plinthMaterial = new THREE.MeshPhysicalMaterial({ color: 0x232429, metalness: 0.9, roughness: 0.34, clearcoat: 0.7, clearcoatRoughness: 0.28, envMapIntensity: 0.85 });
        this.baseShapes = [];

        // Contact shadow on the floor, plus a shadow catcher for the key light.
        this.contact = new THREE.Mesh(
            new THREE.PlaneGeometry(halfWidth * 2 * 1.45, halfDepth * 2 * 1.6),
            new THREE.MeshBasicMaterial({ map: contactTexture(), transparent: true, depthWrite: false, opacity: 0.85 }),
        );
        this.contact.rotation.x = -Math.PI / 2;
        this.contact.position.y = -PLINTH_DEPTH - 0.002;
        this.scene.add(this.contact);
        this.floor = new THREE.Mesh(new THREE.PlaneGeometry(40, 40), new THREE.ShadowMaterial({ opacity: 0.16, color: 0x0b1020 }));
        this.floor.rotation.x = -Math.PI / 2;
        this.floor.position.y = -PLINTH_DEPTH - 0.001;
        this.floor.receiveShadow = true;
        this.scene.add(this.floor);

        const tolerance = 0.0009;
        features.forEach((feature) => {
            const code = String(feature.properties?.KDCPUM || '');
            const regency = String(feature.properties?.WADMKK || '');
            const polygons = ringsOf(feature.geometry);
            const shapes = [];
            let sumX = 0;
            let sumY = 0;
            let count = 0;
            let area = 0;
            polygons.forEach((rings) => {
                const projected = rings.map((ring) => simplify(ring, tolerance).map(([lon, lat]) => this.project(lon, lat)));
                if (!projected[0] || projected[0].length < 4) return;
                projected[0].forEach(([x, y]) => { sumX += x; sumY += y; count += 1; });
                area += Math.abs(THREE.ShapeUtils.area(projected[0].map(([x, y]) => new THREE.Vector2(x, y))));
                shapes.push(projected);
            });
            if (!shapes.length) return;
            const cx = sumX / count;
            const cy = sumY / count;
            const size = Math.sqrt(area) || 1;
            const shrink = Math.max(0.86, 1 - (GAP * 2) / size);
            shapes.forEach((rings) => {
                const shape = new THREE.Shape(rings[0].map(([x, y]) => new THREE.Vector2(x, y)));
                rings.slice(1).forEach((hole) => shape.holes.push(new THREE.Path(hole.map(([x, y]) => new THREE.Vector2(x, y)))));
                this.baseShapes.push(shape);
            });
            const threeShapes = shapes.map((rings) => {
                const toVector = ([x, y]) => new THREE.Vector2(cx + (x - cx) * shrink, cy + (y - cy) * shrink);
                const shape = new THREE.Shape(rings[0].map(toVector));
                rings.slice(1).forEach((hole) => shape.holes.push(new THREE.Path(hole.map(toVector))));
                return shape;
            });
            const geometry = new THREE.ExtrudeGeometry(threeShapes, { depth: 1, bevelEnabled: true, bevelThickness: BEVEL, bevelSize: BEVEL * 0.8, bevelSegments: 3, curveSegments: 1, steps: 1 });
            geometry.rotateX(-Math.PI / 2);
            // Local to the centroid, so the piece can turn on its own axis when the relief comes apart.
            geometry.translate(-cx, 0, cy);
            geometry.computeVertexNormals();
            const position = geometry.attributes.position;
            const base = new Float32Array(position.count);
            const top = [];
            for (let i = 0; i < position.count; i += 1) {
                base[i] = position.getY(i);
                if (base[i] > 0.5) top.push(i);
            }
            const coreGeometry = geometry.clone();

            // Brushed, anodised aluminium: anisotropic highlights stretch across each piece as the light moves.
            const metal = new THREE.MeshPhysicalMaterial({ color: 0xc8cacf, metalness: 1, roughness: 0.28, anisotropy: 0.3, anisotropyRotation: 0.6, clearcoat: 0.5, clearcoatRoughness: 0.18, envMapIntensity: 1.15 });
            // Smoked glass: a dark, clear shell whose clearcoat catches the studio, so the lit core reads through it.
            const glass = new THREE.MeshPhysicalMaterial({
                color: 0x20232a, metalness: 0, roughness: 0.06, transparent: true, opacity: 0.34, depthWrite: false,
                clearcoat: 1, clearcoatRoughness: 0.04, specularIntensity: 1, envMapIntensity: 1.7,
            });
            // Not tone mapped: the light keeps its exact risk colour instead of washing to peach.
            const coreMaterial = new THREE.MeshStandardMaterial({ color: 0x1a0d06, emissive: new THREE.Color(0xff8a2a), emissiveIntensity: 0, roughness: 0.5, metalness: 0, toneMapped: false });

            const mesh = new THREE.Mesh(geometry, metal);
            mesh.castShadow = true;
            mesh.receiveShadow = true;
            const core = new THREE.Mesh(coreGeometry, coreMaterial);
            core.visible = false;
            core.renderOrder = 1;
            mesh.renderOrder = 2;
            mesh.position.set(cx, 0, -cy);
            core.position.set(cx, 0, -cy);
            core.scale.set(0.7, 1, 0.7);
            this.scene.add(mesh, core);

            this.pieces.push({
                code, regency, mesh, core, metal, glass, coreMaterial, geometry, coreGeometry, base, top,
                center: new THREE.Vector3(cx, 0, -cy),
                size,
                lift: BASE_LIFT, targetLift: BASE_LIFT,
                hover: 0, hoverTarget: 0, wave: 0, introK: 1, ring: Math.hypot(cx, cy),
                coreLift: 0, targetCore: 0,
                color: new THREE.Color(0xc8cacf), targetColor: new THREE.Color(0xc8cacf),
                roughness: 0.3, targetRoughness: 0.3,
                appliedLift: -1, appliedCore: -1,
            });
        });
        this.pieces.forEach((piece) => this.applyLift(piece, true));

        const baseGeometry = new THREE.ExtrudeGeometry(this.baseShapes, { depth: PLINTH_DEPTH - 0.04, bevelEnabled: true, bevelThickness: 0.02, bevelSize: 0.022, bevelSegments: 3, curveSegments: 1, steps: 1 });
        baseGeometry.rotateX(-Math.PI / 2);
        baseGeometry.translate(0, -PLINTH_DEPTH + 0.02, 0);
        this.plinth = new THREE.Mesh(baseGeometry, this.plinthMaterial);
        this.plinth.receiveShadow = true;
        this.plinth.castShadow = true;
        this.scene.add(this.plinth);
    }

    /** Resting height plus the extras that ride on it: the opening build, hover lift and the scroll ripple. */
    effectiveLift(piece) {
        const floor = BEVEL * 2.4;
        return floor + Math.max(0, piece.lift - floor) * piece.introK * this.arrangement.grow + piece.hover * 0.16 + piece.wave;
    }

    applyLift(piece, force = false) {
        const lift = this.effectiveLift(piece);
        if (!force && Math.abs(piece.appliedLift - lift) < 0.0004 && Math.abs(piece.appliedCore - piece.coreLift) < 0.0004) return;
        const write = (geometry, value) => {
            const position = geometry.attributes.position;
            piece.top.forEach((index) => position.setY(index, piece.base[index] - 1 + value));
            position.needsUpdate = true;
            geometry.computeBoundingSphere();
        };
        write(piece.geometry, lift);
        piece.shown = lift;
        write(piece.coreGeometry, Math.max(BEVEL * 2.2, piece.coreLift * piece.introK));
        piece.appliedLift = lift;
        piece.appliedCore = piece.coreLift;
    }

    /** Precompile both material sets once, so the glass swap never stalls a frame. */
    precompile() {
        if (this.compiled) return;
        this.pieces.forEach((piece) => { piece.mesh.material = piece.glass; piece.core.visible = true; });
        this.renderer.compile(this.scene, this.camera);
        this.pieces.forEach((piece) => { piece.mesh.material = piece.metal; piece.core.visible = false; });
        this.renderer.compile(this.scene, this.camera);
        this.compiled = true;
    }

    /**
     * targets: Map(code -> { lift 0..1, color hex, roughness, core 0..1 }), glass 0..1, dark 0..1.
     */
    setTargets(targets, { glass = 0, dark = 0 } = {}) {
        this.pieces.forEach((piece) => {
            const target = targets.get(piece.code);
            piece.targetLift = BASE_LIFT + clamp01(target?.lift ?? 0) * (MAX_LIFT - BASE_LIFT);
            piece.targetCore = clamp01(target?.core ?? 0) * piece.targetLift;
            piece.targetColor.set(target?.color ?? 0xc8cacf);
            piece.targetRoughness = target?.roughness ?? 0.3;
        });
        this.targetGlass = glass;
        this.targetDark = dark;
    }

    resize(width, height) {
        this.width = width;
        this.height = height;
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, this.maxPixelRatio, Math.sqrt(4200000 / Math.max(1, width * height))));
        this.renderer.setSize(width, height, false);
        this.camera.aspect = width / Math.max(1, height);
        this.camera.updateProjectionMatrix();
        this.fx.resize(height, this.renderer.getPixelRatio(), FOV);
    }

    /** Which piece sits under a pointer position (NDC, -1..1), if any. */
    pick(ndcX, ndcY) {
        this.raycaster.setFromCamera({ x: ndcX, y: ndcY }, this.camera);
        const hits = this.raycaster.intersectObjects(this.pieces.map((piece) => piece.mesh), false);
        if (!hits.length) return null;
        return this.pieces.find((piece) => piece.mesh === hits[0].object) || null;
    }

    setHovered(piece) {
        if (this.hovered === piece) return;
        this.hovered = piece;
    }

    /**
     * frame: { dt, still, rect {x,y,w,h} (viewport px), yaw, pitch, fill, light (0..1 sweep), time }
     * Returns true while anything is still settling.
     */
    render(frame) {
        const { dt, still } = frame;
        const lambda = still ? 1000 : 4.2;
        let settling = false;

        this.state.glass = still ? (this.targetGlass ?? 0) : damp(this.state.glass, this.targetGlass ?? 0, 5, dt);
        this.state.dark = still ? (this.targetDark ?? 0) : damp(this.state.dark, this.targetDark ?? 0, 5, dt);
        if (Math.abs(this.state.glass - (this.targetGlass ?? 0)) > 0.002 || Math.abs(this.state.dark - (this.targetDark ?? 0)) > 0.002) settling = true;
        const glassOn = this.state.glass > 0.5;

        // The opening build: pieces grow out of the plinth in a wave from the centre outward.
        this.intro = frame.intro ?? 1;
        const introSpan = this.radius || 1;
        const velocity = frame.velocity ?? 0;
        const time = frame.time ?? 0;
        const ripple = still ? 0 : Math.max(Math.min(1, velocity) * 0.075, this.arrangement.wave * 0.055);
        const beat = still ? 0 : 0.5 + 0.5 * Math.sin(time * 2.6);

        // Form: the scroll sets the targets, the pieces follow with a little weight so the motion reads as physical.
        const arrangement = this.arrangement;
        const goal = this.arrangementTarget;
        // A different branch only rises once the current one has settled back into the relief.
        const switching = goal.levitateBranch !== arrangement.levitateBranch;
        if (switching && (still || arrangement.levitate < 0.02)) arrangement.levitateBranch = goal.levitateBranch;
        const levitateGoal = goal.levitateBranch === arrangement.levitateBranch ? goal.levitate : 0;
        [['explode', goal.explode], ['spread', goal.spread], ['levitate', levitateGoal], ['grow', goal.grow], ['wave', goal.wave]].forEach(([key, value]) => {
            arrangement[key] = still ? value : damp(arrangement[key], value, 6.5, dt);
            if (Math.abs(arrangement[key] - value) > 0.0015) settling = true;
        });
        if (goal.levitateBranch !== arrangement.levitateBranch) settling = true;
        const explode = arrangement.explode;
        this.branchOffsets.forEach((offset, key) => {
            offset.copy(this.branchDrift.get(key)).multiplyScalar(arrangement.spread);
            offset.y = key === arrangement.levitateBranch ? arrangement.levitate * 0.95 : 0;
            // The lifted island also steps out of the relief, so it reads as singled out from any angle.
            if (key === arrangement.levitateBranch) offset.addScaledVector(this.branchDrift.get(key), arrangement.levitate * 0.7);
        });
        const floating = explode > 0.001;

        this.pieces.forEach((piece, index) => {
            // A short stagger by position reads as one machined sweep rather than 76 jumps.
            const stagger = still ? 1 : 1 + (index % 9) * 0.035;
            piece.lift = damp(piece.lift, piece.targetLift, lambda / stagger, dt);
            piece.coreLift = damp(piece.coreLift, piece.targetCore, lambda * 0.8 / stagger, dt);
            piece.color.lerp(piece.targetColor, 1 - Math.exp(-lambda * 0.9 * dt));
            piece.roughness = damp(piece.roughness, piece.targetRoughness, lambda, dt);
            if (Math.abs(piece.lift - piece.targetLift) > 0.0006 || Math.abs(piece.coreLift - piece.targetCore) > 0.0006) settling = true;

            const delay = (piece.ring / introSpan) * 0.55 + ((index * 37) % 11) * 0.012;
            piece.introK = this.intro >= 1 ? 1 : Math.max(0, easeOutBack(clamp01((this.intro * 1.7 - delay) / 0.7)));
            if (this.intro < 1) settling = true;

            piece.hoverTarget = this.hovered === piece && !still && !this.quietHover ? 1 : 0;
            piece.hover = still || this.quietHover ? 0 : damp(piece.hover, piece.hoverTarget, 12, dt);
            piece.wave = ripple > 0.001 ? Math.sin(piece.ring * 2.4 - time * 7) * ripple * (0.4 + 0.6 * clamp01(piece.targetLift)) : damp(piece.wave, 0, 8, dt);
            this.applyLift(piece);

            const branchOffset = this.branchOffsets.get(piece.branch);
            const bob = floating ? Math.sin(time * 0.9 + piece.float.phase) * 0.05 * explode : 0;
            piece.mesh.position.set(
                piece.center.x + piece.float.offset.x * explode + (branchOffset?.x || 0),
                piece.float.offset.y * explode + bob + (branchOffset?.y || 0),
                piece.center.z + piece.float.offset.z * explode + (branchOffset?.z || 0),
            );
            const tilt = branchOffset && branchOffset.y > 0 ? branchOffset.y * 0.18 : 0;
            piece.mesh.rotation.set(piece.float.rx * explode + tilt, piece.float.ry * explode, piece.float.rz * explode - tilt * 0.5);
            piece.core.position.copy(piece.mesh.position);
            piece.core.rotation.copy(piece.mesh.rotation);

            piece.metal.color.copy(piece.color);
            piece.metal.roughness = piece.roughness;
            piece.metal.emissive.setHex(0x2c6fe0);
            piece.metal.emissiveIntensity = this.quietHover ? (this.hovered === piece ? 0.3 : 0) : piece.hover * 0.55;
            piece.mesh.material = glassOn ? piece.glass : piece.metal;
            piece.mesh.castShadow = !glassOn;
            piece.core.visible = glassOn && piece.coreLift > BEVEL;
            if (glassOn) {
                piece.coreMaterial.emissive.copy(piece.color);
                // The risk cores breathe, and the hovered one burns brighter.
                const singled = piece.branch === arrangement.levitateBranch ? arrangement.levitate * 0.7 : 0;
                piece.coreMaterial.emissiveIntensity = (0.8 + beat * 0.28 + piece.hover * 0.5 + singled) * this.state.glass;
            }
        });

        // Floor and plinth respond to the ground: deeper shadow on light, rim on dark.
        this.plinth.position.y = -explode * 0.2;
        this.floor.material.opacity = 0.16 * (1 - this.state.dark) * (1 - explode * 0.6);
        this.contact.material.opacity = 0.85 - this.state.dark * 0.35;
        this.rim.intensity = 1.15 + this.state.dark * 1.4;
        this.scene.environmentIntensity = 0.95 - this.state.dark * 0.25;

        // Studio key sweeps slowly across the brushed tops.
        const sweep = (frame.light ?? 0) * Math.PI * 2;
        this.key.position.set(Math.cos(sweep) * 6 + 2, 8.5, Math.sin(sweep) * 3 + 5);
        this.key.target.position.set(0, 0, 0);

        // Camera: orbit around the relief, fitted to the slot rectangle on screen.
        const yaw = THREE.MathUtils.degToRad((frame.yaw ?? -20) + (frame.px ?? 0) * 7);
        const pitch = THREE.MathUtils.degToRad((frame.pitch ?? 40) - (frame.py ?? 0) * 3.2);
        const rect = frame.rect;
        const fill = frame.fill ?? 0.86;
        const tanHalf = Math.tan(THREE.MathUtils.degToRad(FOV / 2));
        const fractionH = Math.max(0.05, (rect.h / this.height) * fill);
        const fractionW = Math.max(0.05, (rect.w / this.width) * fill);
        // Focus glides slowly: a long, damped dolly reads as a deliberate zoom rather than a cut.
        const view = this.view;
        const framing = this.viewGoal;
        const glide = still ? 1 : 1 - Math.exp(-2.4 * dt);
        const carried = framing.branch && framing.branch === arrangement.levitateBranch ? this.branchOffsets.get(framing.branch) : null;
        const goalX = framing.x + (carried?.x || 0);
        const goalZ = framing.z + (carried?.z || 0);
        const travel = Math.hypot(goalX - view.x, goalZ - view.z) + Math.abs(framing.radius - view.radius);
        view.x += (goalX - view.x) * glide;
        view.z += (goalZ - view.z) * glide;
        view.radius += (framing.radius - view.radius) * glide;
        this.zooming = still ? 0 : clamp01((travel / this.radius) * 1.6);
        if (travel > 0.002) settling = true;
        const reach = 1 + arrangement.spread * 0.16 + explode * 0.12;
        const distance = Math.max(view.radius / (tanHalf * fractionH), view.radius / (tanHalf * this.camera.aspect * fractionW)) * 0.92 * reach * (1 - Math.min(1, velocity) * 0.035);
        this.target.set(view.x, 0.32 + this.state.glass * 0.15 + explode * 0.42 + arrangement.levitate * 0.12, view.z);
        this.camera.position.set(
            view.x + Math.sin(yaw) * Math.cos(pitch) * distance,
            Math.sin(pitch) * distance + this.target.y,
            view.z + Math.cos(yaw) * Math.cos(pitch) * distance,
        );
        this.camera.lookAt(this.target);
        // Fast scrolling punches the lens in a touch and leans the horizon; both settle with the scroll.
        this.camera.fov = FOV + Math.min(1, velocity) * 2.6;
        this.camera.rotateZ((frame.px ?? 0) * 0.012 - Math.min(1, velocity) * 0.012 * Math.sign(frame.direction ?? 1));
        const dx = rect.x + rect.w / 2 - this.width / 2;
        const dy = rect.y + rect.h / 2 - this.height / 2;
        this.camera.setViewOffset(this.width, this.height, -dx, -dy, this.width, this.height);
        this.camera.updateProjectionMatrix();

        this.fx.update({
            dt: still ? 1 : dt,
            still,
            dark: this.state.dark,
            glass: this.state.glass,
            time,
            velocity: still ? 0 : velocity,
            intro: this.intro,
            baseLift: BASE_LIFT,
            maxLift: MAX_LIFT,
        });

        this.renderer.render(this.scene, this.camera);
        return settling || velocity > 0.01;
    }

    /** Screen position of a world point (for HTML labels). */
    screenOf(point, out = {}) {
        const vector = this.projectVector || (this.projectVector = new THREE.Vector3());
        vector.copy(point).project(this.camera);
        out.x = (vector.x * 0.5 + 0.5) * this.width;
        out.y = (-vector.y * 0.5 + 0.5) * this.height;
        out.visible = vector.z < 1;
        return out;
    }

    /** Top-centre of a group of pieces, at their current height. */
    anchorOf(codes, out = new THREE.Vector3()) {
        const pieces = this.pieces.filter((piece) => codes.has(piece.code));
        if (!pieces.length) return null;
        let x = 0;
        let z = 0;
        let top = 0;
        pieces.forEach((piece) => { x += piece.mesh.position.x; z += piece.mesh.position.z; top = Math.max(top, (piece.shown ?? piece.lift) + piece.mesh.position.y); });
        return out.set(x / pieces.length, top + BEVEL * 2, z / pieces.length);
    }

    dispose() {
        this.scene.traverse((object) => {
            object.geometry?.dispose?.();
            const materials = Array.isArray(object.material) ? object.material : [object.material];
            materials.filter(Boolean).forEach((material) => {
                material.map?.dispose?.();
                material.dispose();
            });
        });
        this.fx.dispose();
        this.pieces.forEach((piece) => { piece.glass.dispose(); piece.metal.dispose(); });
        this.environment?.dispose();
        this.renderer.dispose();
        this.renderer.forceContextLoss();
    }
}
