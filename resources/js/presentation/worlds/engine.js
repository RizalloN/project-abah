import * as THREE from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { globeWorld, descentWorld } from './globe.js';
import { marketRingWorld, coinVaultWorld, glassSkylineWorld, riskCapsuleWorld, trendRibbonWorld, strategyPillarsWorld } from './sculptures.js';

/*
 * One rendering language for every world: physically based materials, ACES
 * tone mapping, a studio room for reflections, one warm key with a soft
 * shadow and one cool rim. Each factory receives the stage and returns
 * { update(p, t, pointer, still), setData(data), afterRender(), dispose() }.
 */

THREE.Cache.enabled = true;

const FACTORIES = {
    globe: globeWorld,
    descent: descentWorld,
    pasar: marketRingWorld,
    dana: coinVaultWorld,
    pinjaman: glassSkylineWorld,
    kualitas: riskCapsuleWorld,
    tren: trendRibbonWorld,
    aksi: strategyPillarsWorld,
};

const finePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;

export class Stage {
    constructor(host, kind) {
        this.host = host;
        this.kind = kind;
        this.THREE = THREE;
        this.hasRendered = false;
        this.animated = true;
        this.width = 1;
        this.height = 1;
        this.shift = { x: 0, y: 0 };

        this.canvas = document.createElement('canvas');
        this.canvas.className = 'pres-world__canvas';
        this.canvas.setAttribute('aria-hidden', 'true');
        this.labels = document.createElement('div');
        this.labels.className = 'pres-world__labels';
        this.labels.setAttribute('aria-hidden', 'true');
        host.append(this.canvas, this.labels);

        this.renderer = new THREE.WebGLRenderer({ canvas: this.canvas, antialias: true, alpha: true, powerPreference: 'high-performance' });
        this.renderer.setClearColor(0x000000, 0);
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.05;
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.maxPixelRatio = 1.75;

        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(30, 1, 0.05, 120);

        this.pointer = { x: 0, y: 0, tx: 0, ty: 0 };
        this.onPointer = (event) => {
            const rect = host.getBoundingClientRect();
            this.pointer.tx = ((event.clientX - rect.left) / Math.max(1, rect.width)) * 2 - 1;
            this.pointer.ty = ((event.clientY - rect.top) / Math.max(1, rect.height)) * 2 - 1;
        };
        this.onPointerLeave = () => { this.pointer.tx = 0; this.pointer.ty = 0; };
        if (finePointer()) {
            window.addEventListener('pointermove', this.onPointer, { passive: true });
            host.addEventListener('pointerleave', this.onPointerLeave);
        }
    }

    /** Studio reflections. Only scenes with hard surfaces ask for it. */
    useStudio(intensity = 0.9) {
        const pmrem = new THREE.PMREMGenerator(this.renderer);
        this.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
        pmrem.dispose();
        this.scene.environment = this.environment;
        this.scene.environmentIntensity = intensity;
    }

    /** Warm key with a soft shadow, cool rim from behind, faint sky fill. */
    useLights({ key = [4.5, 7, 5.5], keyIntensity = 2.4, rimIntensity = 1.6, shadowSize = 7, shadow = true } = {}) {
        const keyLight = new THREE.DirectionalLight(0xfff1de, keyIntensity);
        keyLight.position.set(...key);
        if (shadow) {
            keyLight.castShadow = true;
            keyLight.shadow.mapSize.set(2048, 2048);
            keyLight.shadow.radius = 6;
            keyLight.shadow.blurSamples = 16;
            keyLight.shadow.bias = -0.0004;
            keyLight.shadow.normalBias = 0.02;
            Object.assign(keyLight.shadow.camera, { left: -shadowSize, right: shadowSize, top: shadowSize, bottom: -shadowSize, near: 0.5, far: 40 });
        }
        const rim = new THREE.DirectionalLight(0x9cc4ff, rimIntensity);
        rim.position.set(-5, 4, -6);
        const sky = new THREE.HemisphereLight(0xcfe0ff, 0x0b1426, 0.35);
        this.scene.add(keyLight, rim, sky);
        this.keyLight = keyLight;
        return keyLight;
    }

    /** A transparent floor that only receives shadow, so the chapter ground shows through. */
    shadowFloor(size = 30, opacity = 0.42, y = 0) {
        const floor = new THREE.Mesh(new THREE.PlaneGeometry(size, size), new THREE.ShadowMaterial({ opacity, color: 0x020814 }));
        floor.rotation.x = -Math.PI / 2;
        floor.position.y = y;
        floor.receiveShadow = true;
        this.scene.add(floor);
        return floor;
    }

    label(className, html = '') {
        const element = document.createElement('div');
        element.className = `pres-world__label ${className}`;
        element.innerHTML = html;
        this.labels.append(element);
        return element;
    }

    clearLabels() {
        this.labels.replaceChildren();
    }

    /** Pin an HTML label to a world position. Hidden when behind the camera or occluded by `hide`. */
    place(element, position, { hide = false, opacity = 1 } = {}) {
        const projected = this.projectVector || (this.projectVector = new THREE.Vector3());
        projected.copy(position).project(this.camera);
        const visible = !hide && projected.z < 1 && Math.abs(projected.x) < 1.15 && Math.abs(projected.y) < 1.15;
        const x = (projected.x * 0.5 + 0.5) * this.width;
        const y = (-projected.y * 0.5 + 0.5) * this.height;
        element.style.transform = `translate3d(${x.toFixed(1)}px, ${y.toFixed(1)}px, 0)`;
        element.style.opacity = visible ? String(opacity) : '0';
    }

    /** Move the subject off-centre so it holds the side the copy does not. */
    applyShift() {
        const { x, y } = this.shift;
        if (!x && !y) {
            this.camera.clearViewOffset();
            return;
        }
        this.camera.setViewOffset(this.width, this.height, -x * this.width, -y * this.height, this.width, this.height);
    }

    resize() {
        const width = Math.max(1, this.host.clientWidth);
        const height = Math.max(1, this.host.clientHeight);
        this.width = width;
        this.height = height;
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, this.maxPixelRatio));
        this.renderer.setSize(width, height, false);
        this.camera.aspect = width / height;
        // Portrait screens see less horizontally; pull the camera back so subjects keep their margins.
        this.camera.userData.fit = this.camera.aspect >= 1.15 ? 1 : Math.min(2.2, 1.2 / Math.sqrt(this.camera.aspect));
        this.world?.resize?.(width, height);
        this.applyShift();
        this.camera.updateProjectionMatrix();
    }

    render(progress, time, still) {
        const ease = still ? 1 : 0.08;
        this.pointer.x += (this.pointer.tx - this.pointer.x) * ease;
        this.pointer.y += (this.pointer.ty - this.pointer.y) * ease;
        this.world.update(progress, time / 1000, this.pointer, still);
        this.renderer.render(this.scene, this.camera);
        this.world.afterRender?.(progress);
        this.hasRendered = true;
    }

    setData(data) {
        this.world.setData?.(data);
    }

    dispose() {
        window.removeEventListener('pointermove', this.onPointer);
        this.host.removeEventListener('pointerleave', this.onPointerLeave);
        this.world?.dispose?.();
        this.scene.traverse((object) => {
            object.geometry?.dispose?.();
            const materials = Array.isArray(object.material) ? object.material : [object.material];
            materials.filter(Boolean).forEach((material) => {
                Object.values(material).forEach((value) => { if (value?.isTexture) value.dispose(); });
                if (material.uniforms) Object.values(material.uniforms).forEach(({ value }) => { if (value?.isTexture) value.dispose(); });
                material.dispose();
            });
        });
        this.environment?.dispose();
        this.renderer.dispose();
        this.renderer.forceContextLoss();
        this.canvas.remove();
        this.labels.remove();
    }
}

export function createStage(host, kind, data) {
    const factory = FACTORIES[kind];
    if (!factory) return null;
    const stage = new Stage(host, kind);
    stage.world = factory(stage, data || {});
    stage.resize();
    return stage;
}
