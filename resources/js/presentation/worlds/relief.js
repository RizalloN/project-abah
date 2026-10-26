import * as THREE from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

/*
 * The Area 6 relief: 76 real kecamatan, machined from anodised aluminium on a
 * graphite plinth, lit like a product shot. One object for the whole report.
 *
 * Heights move the upper half of each extrusion (cap and top bevel) instead of
 * scaling it, so the bevel that catches the light stays crisp at any height.
 * The Kualitas theatre swaps each piece to smoked glass and lights a core inside
 * it; both material sets are compiled up front so the swap never stalls a frame.
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

export class ReliefScene {
    constructor(canvas, features, { maxPixelRatio = 1.75 } = {}) {
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
        this.build(features);
        this.state = {
            dark: 0,
            glass: 0,
        };
        this.compiled = false;
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
            core.position.set(cx * 0.3, 0, -cy * 0.3);
            core.scale.set(0.7, 1, 0.7);
            this.scene.add(mesh, core);

            this.pieces.push({
                code, regency, mesh, core, metal, glass, coreMaterial, geometry, coreGeometry, base, top,
                center: new THREE.Vector3(cx, 0, -cy),
                lift: BASE_LIFT, targetLift: BASE_LIFT,
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

    applyLift(piece, force = false) {
        if (!force && Math.abs(piece.appliedLift - piece.lift) < 0.0004 && Math.abs(piece.appliedCore - piece.coreLift) < 0.0004) return;
        const write = (geometry, lift) => {
            const position = geometry.attributes.position;
            piece.top.forEach((index) => position.setY(index, piece.base[index] - 1 + lift));
            position.needsUpdate = true;
            geometry.computeBoundingSphere();
        };
        write(piece.geometry, piece.lift);
        write(piece.coreGeometry, Math.max(BEVEL * 2.2, piece.coreLift));
        piece.appliedLift = piece.lift;
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

        this.pieces.forEach((piece, index) => {
            // A short stagger by position reads as one machined sweep rather than 76 jumps.
            const stagger = still ? 1 : 1 + (index % 9) * 0.035;
            piece.lift = damp(piece.lift, piece.targetLift, lambda / stagger, dt);
            piece.coreLift = damp(piece.coreLift, piece.targetCore, lambda * 0.8 / stagger, dt);
            piece.color.lerp(piece.targetColor, 1 - Math.exp(-lambda * 0.9 * dt));
            piece.roughness = damp(piece.roughness, piece.targetRoughness, lambda, dt);
            if (Math.abs(piece.lift - piece.targetLift) > 0.0006 || Math.abs(piece.coreLift - piece.targetCore) > 0.0006) settling = true;
            this.applyLift(piece);

            piece.metal.color.copy(piece.color);
            piece.metal.roughness = piece.roughness;
            piece.mesh.material = glassOn ? piece.glass : piece.metal;
            piece.mesh.castShadow = !glassOn;
            piece.core.visible = glassOn && piece.coreLift > BEVEL;
            if (glassOn) {
                piece.coreMaterial.emissive.copy(piece.color);
                piece.coreMaterial.emissiveIntensity = 0.92 * this.state.glass;
            }
        });

        // Floor and plinth respond to the ground: deeper shadow on light, rim on dark.
        this.floor.material.opacity = 0.16 * (1 - this.state.dark);
        this.contact.material.opacity = 0.85 - this.state.dark * 0.35;
        this.rim.intensity = 1.15 + this.state.dark * 1.4;
        this.scene.environmentIntensity = 0.95 - this.state.dark * 0.25;

        // Studio key sweeps slowly across the brushed tops.
        const sweep = (frame.light ?? 0) * Math.PI * 2;
        this.key.position.set(Math.cos(sweep) * 6 + 2, 8.5, Math.sin(sweep) * 3 + 5);
        this.key.target.position.set(0, 0, 0);

        // Camera: orbit around the relief, fitted to the slot rectangle on screen.
        const yaw = THREE.MathUtils.degToRad(frame.yaw ?? -20);
        const pitch = THREE.MathUtils.degToRad(frame.pitch ?? 40);
        const rect = frame.rect;
        const fill = frame.fill ?? 0.86;
        const tanHalf = Math.tan(THREE.MathUtils.degToRad(FOV / 2));
        const fractionH = Math.max(0.05, (rect.h / this.height) * fill);
        const fractionW = Math.max(0.05, (rect.w / this.width) * fill);
        const distance = Math.max(this.radius / (tanHalf * fractionH), this.radius / (tanHalf * this.camera.aspect * fractionW)) * 0.92;
        this.target.set(0, 0.32 + this.state.glass * 0.15, 0);
        this.camera.position.set(
            Math.sin(yaw) * Math.cos(pitch) * distance,
            Math.sin(pitch) * distance + this.target.y,
            Math.cos(yaw) * Math.cos(pitch) * distance,
        );
        this.camera.lookAt(this.target);
        const dx = rect.x + rect.w / 2 - this.width / 2;
        const dy = rect.y + rect.h / 2 - this.height / 2;
        this.camera.setViewOffset(this.width, this.height, -dx, -dy, this.width, this.height);
        this.camera.updateProjectionMatrix();

        this.renderer.render(this.scene, this.camera);
        return settling;
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
        pieces.forEach((piece) => { x += piece.center.x; z += piece.center.z; top = Math.max(top, piece.lift); });
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
        this.pieces.forEach((piece) => { piece.glass.dispose(); piece.metal.dispose(); });
        this.environment?.dispose();
        this.renderer.dispose();
        this.renderer.forceContextLoss();
    }
}
