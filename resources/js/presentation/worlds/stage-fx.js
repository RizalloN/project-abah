import * as THREE from 'three';

/*
 * Stage FX: everything that surrounds the Area 6 relief so the object lives in a
 * space instead of floating on a page.
 *
 *   dust      a depth field of motes rising through the whole viewport (parallax)
 *   rings     machined bezel: tick ring, sweeping arc, two tilted orbits with satellites
 *   floor     a polar grid with a radar sweep that reads as the ground plane
 *   beacons   one light column per branch office, tall where the branch is strong
 *   flow      arcs of data between the branches and Madiun, with pulses travelling them
 *   shock     expanding rings fired on every chapter change and every click
 *
 * calm: the presentation's quiet atlas. Nothing circles or pulses (no radar
 * sweep, beacon foot rings, spinning gems, scanning beams or shocks); the depth
 * cues stay, the motion only answers the reader's scroll and clicks.
 *
 * Every material is unlit and tinted from one palette, so the extra layers add
 * depth cues (occlusion, parallax, scale) without competing with the metal.
 */

const clamp01 = (value) => Math.min(1, Math.max(0, value));
const damp = (current, target, lambda, dt) => current + (target - current) * (1 - Math.exp(-lambda * dt));
const easeOutBack = (t) => 1 + 2.2 * ((t - 1) ** 3) + 1.2 * ((t - 1) ** 2);

const BRANCH_OF_REGENCY = {
    MADIUN: 'madiun', 'KOTA MADIUN': 'madiun', MAGETAN: 'magetan', NGAWI: 'ngawi', PONOROGO: 'ponorogo',
};

const LIGHT_INK = new THREE.Color(0x4a6aa8);
const DARK_INK = new THREE.Color(0xcfe0ff);

const dustVertex = /* glsl */ `
    attribute vec4 aSeed;
    uniform float uTime;
    uniform float uSpeed;
    uniform float uScale;
    uniform float uIntro;
    uniform float uVelocity;
    varying float vAlpha;
    void main() {
        vec3 p = position;
        float rise = uTime * (0.16 + aSeed.w * 0.22) * uSpeed;
        p.y = mod(p.y + rise + 1.0, 10.0) - 1.0;
        p.x += sin(uTime * 0.21 + aSeed.x * 40.0) * 0.42;
        p.z += cos(uTime * 0.17 + aSeed.y * 40.0) * 0.42;
        vec4 mv = modelViewMatrix * vec4(p, 1.0);
        float size = 0.018 + aSeed.w * 0.05;
        gl_PointSize = max(1.2, size * uScale / -mv.z) * (1.0 + uVelocity * 0.6);
        float fade = smoothstep(-1.0, 0.6, p.y) * (1.0 - smoothstep(6.8, 9.0, p.y));
        vAlpha = (0.2 + 0.8 * aSeed.z) * fade * uIntro;
        gl_Position = projectionMatrix * mv;
    }
`;

const dustFragment = /* glsl */ `
    uniform vec3 uColor;
    uniform float uOpacity;
    varying float vAlpha;
    void main() {
        float d = length(gl_PointCoord - 0.5);
        float a = smoothstep(0.5, 0.0, d);
        gl_FragColor = vec4(uColor, a * a * vAlpha * uOpacity);
    }
`;

const floorVertex = /* glsl */ `
    varying vec2 vPos;
    void main() {
        vPos = position.xy;
        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
    }
`;

const floorFragment = /* glsl */ `
    uniform vec3 uColor;
    uniform float uOpacity;
    uniform float uSweep;
    uniform float uRadius;
    uniform float uIntro;
    uniform float uSweepGain;
    varying vec2 vPos;
    void main() {
        float r = length(vPos);
        float a = atan(vPos.y, vPos.x);
        float spoke = abs(fract(a / 6.2831853 * 72.0) - 0.5);
        float spokes = 1.0 - smoothstep(0.0, 0.035 + r * 0.0025, spoke);
        float ringPhase = abs(fract(r / 0.55) - 0.5);
        float rings = 1.0 - smoothstep(0.0, 0.03, ringPhase);
        float grid = max(spokes * 0.55, rings);
        float edge = smoothstep(uRadius * 0.9, uRadius * 1.05, r) * (1.0 - smoothstep(uRadius * 1.9, uRadius * 4.2, r));
        float sweepDelta = mod(a - uSweep + 6.2831853, 6.2831853);
        float sweep = pow(clamp(1.0 - sweepDelta / 1.5, 0.0, 1.0), 3.0);
        float reveal = 1.0 - smoothstep(uIntro * uRadius * 4.4 - 0.4, uIntro * uRadius * 4.4, r);
        float alpha = (grid * 0.5 + sweep * 0.42 * uSweepGain * step(uRadius * 0.96, r)) * edge * reveal;
        gl_FragColor = vec4(uColor, alpha * uOpacity);
    }
`;

const beamVertex = /* glsl */ `
    varying float vY;
    void main() {
        vY = position.y;
        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
    }
`;

const beamFragment = /* glsl */ `
    uniform vec3 uColor;
    uniform float uOpacity;
    uniform float uTime;
    uniform float uScan;
    varying float vY;
    void main() {
        float body = pow(1.0 - clamp(vY, 0.0, 1.0), 1.6);
        float scan = mix(1.0, 0.65 + 0.35 * sin(vY * 38.0 - uTime * 3.2), uScan);
        gl_FragColor = vec4(uColor, body * scan * uOpacity);
    }
`;

const dotTexture = () => {
    const canvas = document.createElement('canvas');
    canvas.width = 64;
    canvas.height = 64;
    const context = canvas.getContext('2d');
    const gradient = context.createRadialGradient(32, 32, 0, 32, 32, 32);
    gradient.addColorStop(0, 'rgba(255,255,255,1)');
    gradient.addColorStop(0.35, 'rgba(255,255,255,0.7)');
    gradient.addColorStop(1, 'rgba(255,255,255,0)');
    context.fillStyle = gradient;
    context.fillRect(0, 0, 64, 64);
    return new THREE.CanvasTexture(canvas);
};

export class StageFX {
    constructor(relief, { dustCount = 1100 } = {}) {
        this.relief = relief;
        this.scene = relief.scene;
        this.radius = relief.radius;
        this.group = new THREE.Group();
        this.scene.add(this.group);
        this.time = 0;
        this.dark = 0;
        this.color = new THREE.Color(0x2c6fe0);
        this.targetColor = new THREE.Color(0x2c6fe0);
        this.ink = new THREE.Color();
        this.mode = { beams: false, flow: 0, speed: 1 };
        this.beamK = 0;
        this.flowK = 0;
        this.quality = 1;
        this.shocks = [];
        this.sweep = 0;
        this.calm = false;
        this.velocity = 0;
        this.intro = 1;

        this.buildBranches();
        this.buildDust(dustCount);
        this.buildFloor();
        this.buildRings();
        this.buildBeacons();
        this.buildFlow();
        this.buildShocks();
    }

    buildBranches() {
        this.branches = new Map();
        this.relief.pieces.forEach((piece) => {
            const key = BRANCH_OF_REGENCY[String(piece.regency).toUpperCase()];
            if (!key) return;
            piece.branch = key;
            if (!this.branches.has(key)) this.branches.set(key, { key, pieces: [], center: new THREE.Vector3() });
            this.branches.get(key).pieces.push(piece);
        });
        this.branches.forEach((branch) => {
            branch.pieces.forEach((piece) => branch.center.add(piece.center));
            branch.center.divideScalar(branch.pieces.length);
            branch.strength = 0;
            branch.top = 0;
        });
        this.hub = this.branches.get('madiun')?.center || new THREE.Vector3();
    }

    buildDust(count) {
        const positions = new Float32Array(count * 3);
        const seeds = new Float32Array(count * 4);
        for (let i = 0; i < count; i += 1) {
            const angle = Math.random() * Math.PI * 2;
            const spread = this.radius * (0.4 + Math.pow(Math.random(), 0.7) * 4.6);
            positions[i * 3] = Math.cos(angle) * spread;
            positions[i * 3 + 1] = Math.random() * 10 - 1;
            positions[i * 3 + 2] = Math.sin(angle) * spread * 0.8;
            seeds[i * 4] = Math.random();
            seeds[i * 4 + 1] = Math.random();
            seeds[i * 4 + 2] = Math.random();
            seeds[i * 4 + 3] = Math.random();
        }
        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('aSeed', new THREE.BufferAttribute(seeds, 4));
        this.dustMaterial = new THREE.ShaderMaterial({
            vertexShader: dustVertex,
            fragmentShader: dustFragment,
            transparent: true,
            depthWrite: false,
            uniforms: {
                uTime: { value: 0 }, uSpeed: { value: 1 }, uScale: { value: 600 }, uIntro: { value: 0 }, uVelocity: { value: 0 },
                uColor: { value: new THREE.Color(0x4a6aa8) }, uOpacity: { value: 0.7 },
            },
        });
        this.dust = new THREE.Points(geometry, this.dustMaterial);
        this.dust.frustumCulled = false;
        this.dust.renderOrder = 5;
        this.dustCount = count;
        this.group.add(this.dust);
    }

    buildFloor() {
        const size = this.radius * 10;
        this.floorMaterial = new THREE.ShaderMaterial({
            vertexShader: floorVertex,
            fragmentShader: floorFragment,
            transparent: true,
            depthWrite: false,
            side: THREE.DoubleSide,
            uniforms: {
                uColor: { value: new THREE.Color(0x4a6aa8) }, uOpacity: { value: 0.5 }, uSweep: { value: 0 },
                uRadius: { value: this.radius }, uIntro: { value: 0 }, uSweepGain: { value: 1 },
            },
        });
        this.grid = new THREE.Mesh(new THREE.PlaneGeometry(size, size), this.floorMaterial);
        this.grid.rotation.x = -Math.PI / 2;
        this.grid.position.y = -0.178;
        this.grid.renderOrder = -2;
        this.group.add(this.grid);
    }

    buildRings() {
        const R = this.radius;
        this.ringMaterial = new THREE.MeshBasicMaterial({ color: 0x4a6aa8, transparent: true, opacity: 0.5, depthWrite: false });
        this.accentMaterial = new THREE.MeshBasicMaterial({ color: 0x2c6fe0, transparent: true, opacity: 0.95, depthWrite: false });
        this.rings = new THREE.Group();
        this.rings.position.y = -0.1;

        const torus = (radius, tube, arc = Math.PI * 2, material = this.ringMaterial) => {
            const mesh = new THREE.Mesh(new THREE.TorusGeometry(radius, tube, 6, Math.max(24, Math.round(arc * 40)), arc), material);
            mesh.rotation.x = -Math.PI / 2;
            return mesh;
        };

        // Bezel: a hairline ring with machined ticks, and a bright arc that circles it.
        this.rings.add(torus(R * 1.16, 0.0045));
        this.tickRing = new THREE.Group();
        const ticks = 120;
        const tickMesh = new THREE.InstancedMesh(new THREE.BoxGeometry(0.012, 0.006, 0.075), this.ringMaterial, ticks);
        const long = new THREE.InstancedMesh(new THREE.BoxGeometry(0.018, 0.008, 0.16), this.ringMaterial, ticks / 10);
        const matrix = new THREE.Matrix4();
        let longIndex = 0;
        for (let i = 0; i < ticks; i += 1) {
            const angle = (i / ticks) * Math.PI * 2;
            const isLong = i % 10 === 0;
            matrix.makeRotationY(-angle).setPosition(Math.cos(angle) * R * 1.3, 0, Math.sin(angle) * R * 1.3);
            if (isLong) long.setMatrixAt(longIndex++, matrix);
            tickMesh.setMatrixAt(i, matrix);
        }
        this.tickRing.add(tickMesh, long);
        this.rings.add(this.tickRing);
        this.arc = torus(R * 1.52, 0.011, Math.PI * 0.62, this.accentMaterial);
        this.rings.add(this.arc);
        this.rings.add(torus(R * 1.52, 0.0032));

        // Two tilted orbits, each carrying a satellite: the part that makes the object read as 3D from any angle.
        this.orbits = [];
        [
            { radius: R * 1.34, tilt: 1.12, spin: 0.26, phase: 0.4 },
            { radius: R * 1.62, tilt: -0.82, spin: -0.17, phase: 2.2 },
        ].forEach((spec) => {
            const pivot = new THREE.Group();
            pivot.rotation.set(spec.tilt, spec.phase, 0);
            const orbit = new THREE.Mesh(new THREE.TorusGeometry(spec.radius, 0.0042, 6, 160), this.ringMaterial);
            orbit.rotation.x = Math.PI / 2;
            const satellite = new THREE.Mesh(new THREE.SphereGeometry(0.052, 20, 14), this.accentMaterial);
            const halo = new THREE.Sprite(new THREE.SpriteMaterial({ map: dotTexture(), color: 0x2c6fe0, transparent: true, opacity: 0.55, depthWrite: false }));
            halo.scale.setScalar(0.5);
            satellite.add(halo);
            pivot.add(orbit, satellite);
            this.rings.add(pivot);
            this.orbits.push({ pivot, satellite, halo, radius: spec.radius, spin: spec.spin, angle: spec.phase * 2 });
        });
        this.group.add(this.rings);
    }

    buildBeacons() {
        this.beacons = [];
        const cylinder = new THREE.CylinderGeometry(0.075, 0.075, 1, 28, 1, true);
        cylinder.translate(0, 0.5, 0);
        const ringGeometry = new THREE.RingGeometry(0.2, 0.236, 64);
        ringGeometry.rotateX(-Math.PI / 2);
        const gem = new THREE.OctahedronGeometry(0.085, 0);
        this.branches.forEach((branch) => {
            const group = new THREE.Group();
            group.position.set(branch.center.x, 0, branch.center.z);
            const beamMaterial = new THREE.ShaderMaterial({
                vertexShader: beamVertex, fragmentShader: beamFragment, transparent: true, depthWrite: false, side: THREE.DoubleSide,
                uniforms: { uColor: { value: new THREE.Color(0x2c6fe0) }, uOpacity: { value: 0 }, uTime: { value: 0 }, uScan: { value: 1 } },
            });
            const beam = new THREE.Mesh(cylinder, beamMaterial);
            const foot = new THREE.Mesh(ringGeometry, new THREE.MeshBasicMaterial({ color: 0x2c6fe0, transparent: true, opacity: 0, depthWrite: false }));
            const gemMesh = new THREE.Mesh(gem, new THREE.MeshBasicMaterial({ color: 0x2c6fe0, transparent: true, opacity: 0, depthWrite: false }));
            group.add(beam, foot, gemMesh);
            group.visible = false;
            this.group.add(group);
            this.beacons.push({ branch, group, beam, beamMaterial, foot, gem: gemMesh, phase: Math.random() * 6 });
        });
    }

    buildFlow() {
        // Arcs run between every other branch and the Madiun hub; pulses travel them.
        this.arcs = [];
        const hub = this.hub;
        const material = new THREE.LineBasicMaterial({ color: 0x2c6fe0, transparent: true, opacity: 0, depthWrite: false });
        this.flowLineMaterial = material;
        this.branches.forEach((branch) => {
            if (branch.key === 'madiun') return;
            const from = new THREE.Vector3(branch.center.x, 0.1, branch.center.z);
            const to = new THREE.Vector3(hub.x, 0.1, hub.z);
            const middle = from.clone().add(to).multiplyScalar(0.5);
            middle.y = 0.9 + from.distanceTo(to) * 0.28;
            const curve = new THREE.QuadraticBezierCurve3(from, middle, to);
            const line = new THREE.Line(new THREE.BufferGeometry().setFromPoints(curve.getPoints(56)), material);
            line.visible = false;
            this.group.add(line);
            this.arcs.push({ branch, curve, line });
        });
        const pulses = this.arcs.length * 4;
        this.pulseGeometry = new THREE.BufferGeometry();
        this.pulseGeometry.setAttribute('position', new THREE.BufferAttribute(new Float32Array(pulses * 3), 3));
        this.pulseMaterial = new THREE.PointsMaterial({ color: 0x2c6fe0, size: 0.22, map: dotTexture(), transparent: true, opacity: 0, depthWrite: false, sizeAttenuation: true });
        this.pulses = new THREE.Points(this.pulseGeometry, this.pulseMaterial);
        this.pulses.frustumCulled = false;
        this.pulses.renderOrder = 6;
        this.group.add(this.pulses);
        this.tmp = new THREE.Vector3();
    }

    buildShocks() {
        const geometry = new THREE.RingGeometry(0.985, 1, 128);
        geometry.rotateX(-Math.PI / 2);
        for (let i = 0; i < 4; i += 1) {
            const mesh = new THREE.Mesh(geometry, new THREE.MeshBasicMaterial({ color: 0x2c6fe0, transparent: true, opacity: 0, depthWrite: false, side: THREE.DoubleSide }));
            mesh.visible = false;
            mesh.position.y = -0.17;
            this.group.add(mesh);
            this.shocks.push({ mesh, age: 9, strength: 1, x: 0, z: 0 });
        }
    }

    /** Fire an expanding ring from a point on the floor (default: the centre of the relief). */
    pulse({ strength = 1, x = 0, z = 0 } = {}) {
        if (this.calm) return;
        const slot = this.shocks.reduce((best, shock) => (shock.age > best.age ? shock : best), this.shocks[0]);
        Object.assign(slot, { age: 0, strength, x, z });
        slot.mesh.visible = true;
    }

    setMode({ beams = false, flow = 0, speed = 1, color = 0x2c6fe0 } = {}) {
        this.mode = { beams, flow, speed };
        this.targetColor.set(color);
    }

    setQuality(quality) {
        this.quality = quality;
        this.dust.geometry.setDrawRange(0, Math.round(this.dustCount * Math.max(0.2, quality)));
    }

    resize(height, pixelRatio, fov) {
        this.dustMaterial.uniforms.uScale.value = (height * pixelRatio) / (2 * Math.tan(THREE.MathUtils.degToRad(fov / 2)));
    }

    /** Branch strength (0..1) from the relief's own target heights, so beams always match the pieces. */
    measureBranches(baseLift, maxLift) {
        this.branches.forEach((branch) => {
            let top = 0;
            let total = 0;
            branch.pieces.forEach((piece) => {
                top = Math.max(top, piece.lift);
                total += piece.targetLift;
            });
            branch.top = top;
            branch.strength = clamp01((total / branch.pieces.length - baseLift) / (maxLift - baseLift));
        });
    }

    update({ dt, still, dark, time, velocity = 0, intro = 1, baseLift, maxLift, glass = 0 }) {
        this.time = time;
        this.intro = intro;
        this.velocity = damp(this.velocity, velocity, 6, dt);
        this.dark = dark;
        const rate = still ? 1000 : 4;
        this.color.lerp(this.targetColor, 1 - Math.exp(-rate * dt));
        this.ink.copy(LIGHT_INK).lerp(DARK_INK, dark);
        const accent = this.color;

        // Dust: a slow field on light grounds, a brighter ember field on dark.
        const du = this.dustMaterial.uniforms;
        du.uTime.value = time;
        du.uSpeed.value = this.mode.speed * (1 + this.velocity * 2.4);
        du.uIntro.value = intro;
        du.uVelocity.value = this.velocity;
        du.uColor.value.copy(this.ink).lerp(accent, 0.38 + glass * 0.5);
        du.uOpacity.value = 0.55 + dark * 0.4;
        this.dustMaterial.blending = dark > 0.5 ? THREE.AdditiveBlending : THREE.NormalBlending;

        // Floor grid and radar sweep (faster and redder in the risk theatre).
        if (!this.calm) this.sweep = (this.sweep + dt * (0.5 + glass * 0.9 + this.velocity * 2)) % (Math.PI * 2);
        const fu = this.floorMaterial.uniforms;
        fu.uSweepGain.value = this.calm ? 0 : 1;
        fu.uSweep.value = this.sweep;
        fu.uIntro.value = intro;
        fu.uColor.value.copy(this.ink).lerp(accent, 0.25 + glass * 0.45);
        fu.uOpacity.value = 0.42 + dark * 0.25;

        // Bezel, arc and orbits: scroll velocity spins them up.
        const spin = dt * (0.07 + this.velocity * 0.9);
        this.tickRing.rotation.y += spin;
        this.arc.rotation.z -= dt * (0.45 + this.velocity * 2.4);
        this.rings.scale.setScalar(0.55 + 0.45 * easeOutBack(clamp01(intro * 1.25)));
        this.ringMaterial.color.copy(this.ink);
        this.ringMaterial.opacity = (0.34 + dark * 0.3) * clamp01(intro * 1.4);
        this.accentMaterial.color.copy(accent);
        this.accentMaterial.opacity = 0.95 * clamp01(intro * 1.4);
        this.orbits.forEach((orbit) => {
            orbit.angle += dt * orbit.spin * (1 + this.velocity * 5);
            orbit.satellite.position.set(Math.cos(orbit.angle) * orbit.radius, 0, Math.sin(orbit.angle) * orbit.radius);
            orbit.halo.material.color.copy(accent);
        });

        // Beacons and flow only exist while the state carries data.
        this.beamK = damp(this.beamK, this.mode.beams ? 1 : 0, 3.4, dt);
        this.flowK = damp(this.flowK, this.mode.flow !== 0 ? 1 : 0, 3, dt);
        this.measureBranches(baseLift, maxLift);
        this.beacons.forEach((beacon) => {
            const { branch } = beacon;
            const on = this.beamK > 0.01;
            beacon.group.visible = on;
            if (!on) return;
            const height = (branch.top + 0.35 + branch.strength * 1.7) * this.beamK;
            beacon.beam.scale.set(1, Math.max(0.001, height), 1);
            // Beacons ride their island when the relief separates into branches.
            const drift = this.relief.branchOffsets?.get(branch.key);
            beacon.group.position.set(branch.center.x + (drift?.x || 0), drift?.y || 0, branch.center.z + (drift?.z || 0));
            const u = beacon.beamMaterial.uniforms;
            u.uColor.value.copy(accent);
            u.uTime.value = time;
            u.uScan.value = this.calm ? 0 : 1;
            u.uOpacity.value = (0.2 + branch.strength * 0.5) * this.beamK;
            beacon.foot.visible = !this.calm;
            beacon.gem.visible = !this.calm;
            if (this.calm) return;
            const pulse = (time * 0.8 + beacon.phase) % 1;
            beacon.foot.scale.setScalar(1 + pulse * 2.4);
            beacon.foot.material.color.copy(accent);
            beacon.foot.material.opacity = (1 - pulse) * 0.7 * this.beamK;
            beacon.foot.position.y = -0.165;
            beacon.gem.position.y = height + 0.1 + Math.sin(time * 2 + beacon.phase) * 0.04;
            beacon.gem.rotation.y = time * 1.4 + beacon.phase;
            beacon.gem.material.color.copy(accent);
            beacon.gem.material.opacity = 0.95 * this.beamK;
        });

        this.flowLineMaterial.color.copy(accent);
        this.flowLineMaterial.opacity = 0.42 * this.flowK;
        this.pulseMaterial.color.copy(accent);
        this.pulseMaterial.opacity = 0.95 * this.flowK;
        const positions = this.pulseGeometry.attributes.position;
        let cursor = 0;
        const hubDrift = this.relief.branchOffsets?.get('madiun');
        this.arcs.forEach((arc, arcIndex) => {
            arc.line.visible = this.flowK > 0.01;
            const drift = this.relief.branchOffsets?.get(arc.branch.key);
            if (arc.line.visible && drift && hubDrift) this.bendArc(arc, drift, hubDrift);
            const direction = this.mode.flow >= 0 ? 1 : -1;
            for (let i = 0; i < 4; i += 1) {
                let t = ((time * 0.22 * direction) + i / 4 + arcIndex * 0.19) % 1;
                if (t < 0) t += 1;
                arc.curve.getPoint(this.mode.flow >= 0 ? 1 - t : t, this.tmp);
                positions.setXYZ(cursor++, this.tmp.x, this.tmp.y, this.tmp.z);
            }
        });
        positions.needsUpdate = true;

        // Shock rings.
        this.shocks.forEach((shock) => {
            if (shock.age > 2.2) {
                shock.mesh.visible = false;
                return;
            }
            shock.age += dt;
            const t = clamp01(shock.age / 1.6);
            const eased = 1 - (1 - t) ** 3;
            shock.mesh.position.x = shock.x;
            shock.mesh.position.z = shock.z;
            shock.mesh.scale.setScalar(0.4 + eased * this.radius * 2.8);
            shock.mesh.material.color.copy(accent);
            shock.mesh.material.opacity = (1 - t) * 0.75 * shock.strength;
        });
    }

    /** Re-anchor an arc to the islands it joins; only rebuilt while they are moving. */
    bendArc(arc, drift, hubDrift) {
        const key = `${drift.x.toFixed(3)},${drift.y.toFixed(3)},${drift.z.toFixed(3)}|${hubDrift.x.toFixed(3)},${hubDrift.y.toFixed(3)},${hubDrift.z.toFixed(3)}`;
        if (arc.anchoredAt === key) return;
        arc.anchoredAt = key;
        const { v0, v1, v2 } = arc.curve;
        v0.set(arc.branch.center.x + drift.x, 0.1 + drift.y, arc.branch.center.z + drift.z);
        v2.set(this.hub.x + hubDrift.x, 0.1 + hubDrift.y, this.hub.z + hubDrift.z);
        v1.copy(v0).add(v2).multiplyScalar(0.5);
        v1.y = Math.max(v0.y, v2.y) + 0.9 + v0.distanceTo(v2) * 0.28;
        arc.line.geometry.setFromPoints(arc.curve.getPoints(56));
        arc.line.geometry.computeBoundingSphere();
    }

    dispose() {
        this.group.traverse((object) => {
            object.geometry?.dispose?.();
            const materials = Array.isArray(object.material) ? object.material : [object.material];
            materials.filter(Boolean).forEach((material) => { material.map?.dispose?.(); material.dispose(); });
        });
        this.scene.remove(this.group);
    }
}
