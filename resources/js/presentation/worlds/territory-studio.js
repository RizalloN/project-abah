/*
 * Territory studio: the persistent Area 6 relief of the segmented presentation.
 *
 * One fixed canvas renders the kecamatan relief (ReliefScene) into whichever
 * [data-territory] slot owns the most of the viewport, like the keynote, but its
 * states are registered from data rather than hard-coded. A state says how high
 * and in what colour every kecamatan stands, which labels float above it, and a
 * journey: the camera stops it makes as its section scrolls (the whole area, then
 * one branch or unit after another, then back out). Every stop is a slow damped
 * dolly; while the camera travels the canvas takes a slight lens blur, which is
 * what makes the zoom read as a focus pull instead of a jump.
 *
 * The pointer hovers kecamatan (a tooltip names it and the units serving it),
 * drags to turn the relief, and clicks to drill in: the state decides what a
 * click on a kecamatan focuses (its branch from the Area, its unit from a branch).
 */

import { deviceEnvironment, deviceProfile, FrameBudget } from '../segments/device-runtime.js';

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const clamp01 = (value) => Math.min(1, Math.max(0, value));
const damp = (current, target, lambda, dt) => current + (target - current) * (1 - Math.exp(-lambda * dt));
const smoothstep = (from, to, value) => { const t = clamp01((value - from) / (to - from)); return t * t * (3 - 2 * t); };
const FLIGHT_SPEC = () => ({
    journey: [{ until: 1, focus: null, heights: () => ({ lift: 0.16, color: 0x9db5d2, roughness: 0.38 }) }],
    dark: 1,
    fx: { beams: false, flow: 0, speed: 0.8, color: 0x7fa6e8 },
});
const escapeText = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));

const supportsWebGL = () => {
    try {
        const context = document.createElement('canvas').getContext('webgl2');
        context?.getExtension('WEBGL_lose_context')?.loseContext();
        return Boolean(context);
    } catch (_) {
        return false;
    }
};

/** Camera pose per slot kind; p (0..1) runs through the slot's own section. */
const POSES = {
    hero: (p) => ({ yaw: -50 + p * 40, pitch: 24 + p * 30, fill: 0.92 }),
    lead: (p) => ({ yaw: -34 + p * 36, pitch: 40 + p * 12, fill: 0.9 }),
    trail: (p) => ({ yaw: 34 - p * 36, pitch: 40 + p * 12, fill: 0.9 }),
    map: (p) => ({ yaw: -16 + p * 22, pitch: 54 + p * 6, fill: 0.94 }),
    close: (p) => ({ yaw: -40 + p * 24, pitch: 30, fill: 0.95 }),
};

const BLOCKERS = 'a, button, input, select, textarea, label, dialog, .sg-sheet, .sg-nav, .sg-panel, .sg-opener__copy, [data-territory-ignore], [data-polygon-surface]';

export class TerritoryStudio {
    constructor({ geographyUrl }) {
        this.geographyUrl = geographyUrl;
        this.states = new Map();
        this.overrides = new Map();
        this.interactions = new Map();
        this.interactiveTargets = new Map();
        this.slots = [];
        this.features = [];
        this.scene = null;
        this.frame = null;
        this.idleTimer = null;
        this.budget = new FrameBudget();
        this.profile = deviceProfile();
        this.continuous = false;
        this.lastTime = null;
        this.time = 0;
        this.rect = null;
        this.activeSlot = null;
        this.appliedKey = null;
        this.labels = new Map();
        this.pointer = { x: 0, y: 0, sx: 0, sy: 0, clientX: 0, clientY: 0, free: false, dirty: false, seen: false };
        this.drag = { active: false, moved: 0, yaw: 0, pitch: 0, releasedAt: 0, lastX: 0, lastY: 0 };
        this.velocity = 0;
        this.lastScrollY = null;
        this.swoop = 0;
        this.swoopDirection = 1;
        this.blur = 0;
        this.introStart = null;
        this.focusWait = null;
        this.flightP = null;
        this.handoff = null;
        this.clip = null;
        this.lastPose = null;
        this.arrival = null;
        this.rebuilds = 0;
        this.ready = new Promise((resolve) => { this.resolveReady = resolve; });
    }

    init() {
        this.profile = deviceProfile(deviceEnvironment());
        this.webgl = 'IntersectionObserver' in window && supportsWebGL();
        document.documentElement.classList.add(this.webgl ? 'sg-relief-on' : 'sg-relief-off');
        this.layer = this.element('div', 'sg-relief');
        this.labelLayer = this.element('div', 'sg-relief__labels');
        this.labelLayer.removeAttribute('aria-hidden');
        this.tip = this.element('div', 'sg-relief__tip');
        document.body.append(this.layer, this.labelLayer, this.tip);
        this.bindPointer();
        window.addEventListener('scroll', () => this.kick(), { passive: true });
        window.addEventListener('resize', () => { this.scene?.resize(window.innerWidth, window.innerHeight); this.kick(); }, { passive: true });
        document.addEventListener('visibilitychange', () => {
            window.cancelAnimationFrame(this.frame);
            window.clearTimeout(this.idleTimer);
            this.frame = this.idleTimer = null;
            this.lastTime = null;
            this.continuous = false;
            this.budget.reset();
            this.kick();
        });
        window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', () => {
            this.profile = deviceProfile(deviceEnvironment());
            this.kick();
        });
        this.load();
    }

    element(tag, className) {
        const node = document.createElement(tag);
        node.className = className;
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    async load() {
        try {
            const response = await fetch(this.geographyUrl, { cache: 'force-cache' });
            const geo = await response.json();
            this.features = (geo?.features || []).filter((feature) => feature?.geometry);
            this.paintPosters();
            this.resolveReady(this.features);
            if (!this.webgl || !this.features.length) return;
            // The SVG map and controls can paint before downloading/compiling the GPU enhancement.
            await new Promise(resolve => {
                if (window.requestIdleCallback) window.requestIdleCallback(resolve, { timeout: 900 });
                else window.setTimeout(resolve, 0);
            });
            await this.buildScene();
        } catch (error) {
            console.warn('Territory relief fell back to its poster.', error);
            this.resolveReady(this.features);
            this.webgl = false;
            document.documentElement.classList.replace('sg-relief-on', 'sg-relief-off');
            this.kick();
        }
    }

    async buildScene() {
        let pendingScene = null;
        const canvas = document.createElement('canvas');
        try {
            const { ReliefScene } = await import('./relief.js');
            this.canvas = canvas;
            this.layer.append(canvas);
            // The GPU may drop the context (driver reset, memory pressure from the Earth textures).
            // Three.js cannot rebuild this scene's targets, so the poster takes over until a new one is ready.
            canvas.addEventListener('webglcontextlost', (event) => {
                event.preventDefault();
                if (this.canvas === canvas) this.dropScene();
            });
            canvas.addEventListener('webglcontextrestored', () => {
                if (this.canvas !== canvas || this.scene) return;
                canvas.remove();
                if (++this.rebuilds <= 2) this.buildScene().catch(() => {});
            });
            pendingScene = new ReliefScene(canvas, this.features, this.profile);
            // The presentation uses a quiet atlas, without the shared scene's orbiting bezel/satellites.
            pendingScene.fx.rings.visible = false;
            // Nothing circles, pulses or lifts under the pointer: hover only names the kecamatan.
            pendingScene.fx.calm = true;
            pendingScene.quietHover = true;
            pendingScene.resize(window.innerWidth, window.innerHeight);
            await pendingScene.precompileAsync();
            if (pendingScene.renderer.getContext().isContextLost()) throw new Error('WebGL context lost while preparing the relief.');
            // An orientation change may happen while shaders are still preparing.
            pendingScene.resize(window.innerWidth, window.innerHeight);
            this.scene = pendingScene;
            pendingScene = null;
            this.appliedKey = null;
            this.webgl = true;
            document.documentElement.classList.replace('sg-relief-off', 'sg-relief-on');
            document.documentElement.classList.add('sg-relief-live');
            this.kick();
        } catch (error) {
            pendingScene?.dispose();
            canvas.remove();
            if (this.canvas === canvas) this.canvas = null;
            throw error;
        }
    }

    /** A lost context leaves a blank canvas over the frame: show the SVG poster and end any flight. */
    dropScene() {
        if (!this.scene) return;
        this.endFlight();
        this.handoff = null;
        // Its GL objects belong to the dead context; dropping the references is the whole release.
        this.scene = null;
        this.releaseSurface();
        this.canvas.style.display = 'none';
        // Until a rebuilt scene is live, the poster is the completed map: focus waits must not hang on the GPU.
        this.webgl = false;
        document.documentElement.classList.replace('sg-relief-on', 'sg-relief-off');
        document.documentElement.classList.remove('sg-relief-live');
        this.scan();
    }

    /** Kecamatan features (once loaded), for callers that derive district values. */
    get districts() {
        return this.features;
    }

    /**
     * spec: {
     *   heights(code) -> { lift 0..1, color hex, core 0..1 } | null,   // per kecamatan
     *   glass, dark, fx: { beams, flow, speed, color },
     *   labels: [{ codes: Set, title, value }],
     *   journey: [{ until 0..1, focus: Set|null, labels?, heights?, caption? }],   // camera stops by section progress
     *   labelsFor(codes), captionFor(codes) -> what a clicked focus shows,
     *   describe(code) -> html for the hover tip,
     *   pick(code) -> Set|null to focus on click (null resets),
     *   arrangement(p) -> relief arrangement,
     * }
     */
    register(key, spec) {
        spec.allowedCodes ??= this.allowedCodes;
        this.interactions.get(key)?.(spec);
        this.states.set(key, spec);
        if (this.activeSlot?.dataset.territory === key) this.appliedKey = null;
        this.kick();
    }

    scan() {
        this.slots = Array.from(document.querySelectorAll('[data-territory]'));
        this.paintPosters();
        this.slots.forEach((slot) => this.framePoster(slot, this.overrides.get(slot.dataset.territory)));
        if (this.activeSlot && !this.activeSlot.isConnected) this.releaseSurface();
        this.kick();
    }

    /* The flight: the same scene and camera, from orbit down into the first room's slot. */
    canFly() { return Boolean(this.scene); }

    beginFlight() {
        if (!this.scene) return false;
        const assets = new URL('../vendor/earth/', new URL(this.geographyUrl, window.location.href));
        this.flightHandle = this.scene.beginFlight(assets);
        this.flightP = 0;
        this.handoff = null;
        this.introStart = Number.NEGATIVE_INFINITY;
        this.registerFlightState();
        this.scan();
        return true;
    }

    /** The deck rebuild clears every state; the flight's own state has to outlive it. */
    registerFlightState() {
        if (this.flightP !== null) this.register('flight', FLIGHT_SPEC());
    }

    flightReady() { return Boolean(this.flightHandle?.ready); }

    setFlightProgress(progress) {
        this.flightP = progress;
        this.kick();
    }

    /** The full-screen slot leaves; the frame and the camera glide on into the room below it. */
    handOffFlight(slot) {
        slot?.remove();
        this.handoff = { until: performance.now() + 1500 };
        this.scan();
    }

    endFlight() {
        if (this.flightP === null) return;
        this.flightP = null;
        this.flightHandle = null;
        this.scene?.endFlight();
        this.states.delete('flight');
        this.appliedKey = null;
        this.kick();
    }

    /** A room change: swing the way the reader travels, while the new figures rise from the floor. */
    seam(direction = 1, { assemble = 0.14 } = {}) {
        if (reduceMotion()) return;
        this.swoop = 1.3;
        this.swoopDirection = direction < 0 ? -1 : 1;
        this.arrival = { start: null, assemble };
        this.kick();
    }

    /** Release viewport state when a panel/deck leaves; the actual scene stays alive. */
    releaseSurface() {
        this.cancelFocusWait();
        this.activeSlot = null;
        this.appliedKey = null;
        this.rect = null;
        this.blur = 0;
        this.swoop = 0;
        this.velocity = 0;
        this.lastScrollY = null;
        this.pointer.free = false;
        this.tap = null;
        this.clip = null;
        this.lastPose = null;
        this.drag.active = false;
        this.drag.yaw = 0;
        this.drag.pitch = 0;
        document.documentElement.classList.remove('sg-dragging', 'sg-relief-hot');
        this.scene?.setHovered?.(null);
        this.layer?.classList.remove('is-visible');
        if (this.layer) { this.layer.style.clipPath = ''; this.layer.style.setProperty('--sg-blur', '0px'); }
        this.tip?.classList.remove('is-on');
        this.labels.forEach((element) => this.hideLabel(element));
        this.labelItems = [];
    }

    /** Resolve only after this focus has reached a visible frame, or false when its owner leaves. */
    waitForFocus(key, { signal, minimum = 650, maximum = 2400 } = {}) {
        this.cancelFocusWait();
        if (signal?.aborted || !this.states.has(key) || document.hidden) return Promise.resolve(false);
        const duration = Number.isFinite(maximum) ? Math.max(1, maximum) : 2400;
        const hold = Math.min(duration, Number.isFinite(minimum) ? Math.max(0, minimum) : 650);
        return new Promise((resolve) => {
            const wait = { key, signal, resolve, minimum: hold, startedAt: performance.now(), stable: 0,
                override: this.focusSignature(this.overrides.get(key)), timer: null, abort: null };
            wait.abort = () => this.finishFocusWait(wait, false);
            this.focusWait = wait;
            wait.timer = setTimeout(() => this.finishFocusWait(wait, false), duration);
            signal?.addEventListener('abort', wait.abort, { once: true });
            this.kick();
        });
    }

    focusSignature(codes) {
        return codes ? [...codes].map(String).sort().join(',') : null;
    }

    finishFocusWait(wait, completed) {
        if (this.focusWait !== wait) return;
        this.focusWait = null;
        clearTimeout(wait.timer);
        wait.signal?.removeEventListener('abort', wait.abort);
        wait.resolve(completed);
    }

    cancelFocusWait() {
        if (this.focusWait) this.finishFocusWait(this.focusWait, false);
    }

    /** Called by the existing render clock; no second camera loop or synthetic completion timer. */
    checkFocusFrame({ key, slot, still, rendered = false, target = null, now = performance.now() }) {
        const wait = this.focusWait;
        if (!wait) return;
        if (document.hidden || wait.signal?.aborted || key !== wait.key || !slot?.isConnected || slot.closest('[hidden]')
            || this.focusSignature(this.overrides.get(key)) !== wait.override) {
            this.finishFocusWait(wait, false);
            return;
        }
        const inert = slot.closest('[inert]');
        const temporary = inert?.classList.contains('sg-opener') && slot.closest('.is-panel-transition');
        if (inert && !temporary) { this.finishFocusWait(wait, false); return; }
        if (!this.scene) {
            // A still-loading WebGL scene is not a completed SVG fallback.
            if (this.webgl !== false) return;
            this.framePoster(slot, this.overrides.get(key));
            const box = slot.querySelector('[data-territory-poster] svg')?.viewBox?.baseVal;
            if (box && [box.x, box.y, box.width, box.height].every(Number.isFinite) && box.width > 0 && box.height > 0) this.finishFocusWait(wait, true);
            return;
        }
        const appliedFocus = this.overrides.has(key) ? [...this.overrides.get(key)].join(',') : '';
        if (!rendered || this.activeSlot !== slot || !this.appliedKey?.startsWith(`${key}|`) || this.appliedKey.split('|')[2] !== appliedFocus) return;
        const view = this.scene.view;
        const goal = this.scene.viewGoal;
        const arrangement = this.scene.arrangement;
        const arrangementGoal = this.scene.arrangementTarget;
        const carried = goal?.branch && goal.branch === arrangement?.levitateBranch ? this.scene.branchOffsets?.get(goal.branch) : null;
        const values = [view?.x, view?.z, view?.radius, goal?.x, goal?.z, goal?.radius, this.scene.zooming, this.blur,
            carried?.x ?? 0, carried?.z ?? 0,
            ...['x', 'y', 'w', 'h'].flatMap((side) => [this.rect?.[side], target?.[side]])];
        if (!values.every(Number.isFinite) || goal.radius <= 0 || this.rect.w <= 0 || this.rect.h <= 0) { wait.stable = 0; return; }
        const travel = Math.hypot(goal.x + (carried?.x || 0) - view.x, goal.z + (carried?.z || 0) - view.z) + Math.abs(goal.radius - view.radius);
        const rectSettled = ['x', 'y', 'w', 'h'].every((side) => Math.abs(this.rect[side] - target[side]) <= .5);
        const arrangementSettled = arrangement && arrangementGoal && arrangement.levitateBranch === arrangementGoal.levitateBranch
            && ['explode', 'spread', 'levitate', 'grow', 'wave'].every((name) => Number.isFinite(arrangement[name]) && Number.isFinite(arrangementGoal[name])
                && Math.abs(arrangement[name] - arrangementGoal[name]) <= .015);
        const settled = travel / goal.radius <= .015 && this.scene.zooming <= .03 && this.blur <= .18
            && rectSettled && arrangementSettled && !this.drag.active && this.swoop <= .01;
        wait.stable = settled ? wait.stable + 1 : 0;
        if (still && settled || wait.stable >= 3 && now - wait.startedAt >= wait.minimum) this.finishFocusWait(wait, true);
    }

    setInteractiveTargets(key, items) {
        this.interactiveTargets.set(key, items);
        this.appliedKey = null;
        this.kick();
    }

    /** A click focus that holds until the reader clicks empty floor or the slot changes. */
    setOverride(key, codes) {
        const allowed = this.states.get(key)?.allowedCodes;
        if (codes && allowed) codes = new Set([...codes].filter((code) => allowed.has(code)));
        if (codes && !codes.size) codes = null;
        if (codes) this.overrides.set(key, codes);
        else this.overrides.delete(key);
        this.slots.filter((slot) => slot.dataset.territory === key).forEach((slot) => this.framePoster(slot, codes));
        this.appliedKey = null;
        this.kick();
    }

    framePoster(slot, codes) {
        const svg = slot.querySelector('[data-territory-poster] svg');
        if (!svg || !slot.isConnected || slot.closest('[hidden]') || !slot.getClientRects().length) return;
        const boxes = [];
        svg.querySelectorAll('[data-code]').forEach((path) => {
            const focused = Boolean(codes?.has(path.dataset.code));
            path.classList.toggle('is-focused', focused);
            path.classList.toggle('is-outside', Boolean(codes && !focused));
            if (focused) {
                const box = path.getBBox();
                if ([box.x, box.y, box.width, box.height].every(Number.isFinite) && box.width > 0 && box.height > 0) boxes.push(box);
            }
        });
        if (boxes.length) {
            const x = Math.min(...boxes.map((box) => box.x));
            const y = Math.min(...boxes.map((box) => box.y));
            const right = Math.max(...boxes.map((box) => box.x + box.width));
            const bottom = Math.max(...boxes.map((box) => box.y + box.height));
            svg.setAttribute('viewBox', `${x - 12} ${y - 12} ${right - x + 24} ${bottom - y + 24}`);
        } else if (!codes) svg.setAttribute('viewBox', '0 0 600 560');
    }

    paintPosters() {
        if (!this.features.length) return;
        const markup = this.posterMarkup();
        document.querySelectorAll('[data-territory-poster]:not([data-painted])').forEach((poster) => {
            poster.innerHTML = markup;
            poster.dataset.painted = '1';
        });
    }

    posterMarkup() {
        if (this.posterCache) return this.posterCache;
        const rings = this.features.flatMap((feature) => (feature.geometry.type === 'Polygon' ? [feature.geometry.coordinates] : feature.geometry.coordinates));
        const points = rings.flat(2);
        const lons = points.map((point) => point[0]);
        const lats = points.map((point) => point[1]);
        const minLon = Math.min(...lons); const maxLon = Math.max(...lons);
        const minLat = Math.min(...lats); const maxLat = Math.max(...lats);
        const correction = Math.cos(((minLat + maxLat) / 2) * Math.PI / 180);
        const scale = Math.min(560 / ((maxLon - minLon) * correction), 520 / (maxLat - minLat));
        const x = (lon) => 300 + (lon - (minLon + maxLon) / 2) * correction * scale;
        const y = (lat) => 280 - (lat - (minLat + maxLat) / 2) * scale;
        const paths = this.features.map((feature) => {
            const polygons = feature.geometry.type === 'Polygon' ? [feature.geometry.coordinates] : feature.geometry.coordinates;
            const d = polygons.map((polygon) => polygon.map((ring) => ring.filter((_, index) => index % 3 === 0 || index === ring.length - 1).map(([lon, lat], index) => `${index ? 'L' : 'M'}${x(lon).toFixed(1)},${y(lat).toFixed(1)}`).join('') + 'Z').join('')).join('');
            return `<path data-code="${escapeText(feature.properties?.KDCPUM)}" d="${d}"/>`;
        }).join('');
        this.posterCache = `<svg viewBox="0 0 600 560" role="presentation" focusable="false">${paths}</svg>`;
        return this.posterCache;
    }

    bindPointer() {
        const insideSlot = (x, y) => this.rect && x >= this.rect.x && x <= this.rect.x + this.rect.w && y >= this.rect.y && y <= this.rect.y + this.rect.h;
        const track = (event) => {
            const pointer = this.pointer;
            Object.assign(pointer, {
                clientX: event.clientX,
                clientY: event.clientY,
                x: (event.clientX / window.innerWidth) * 2 - 1,
                y: (event.clientY / window.innerHeight) * 2 - 1,
                dirty: true,
                seen: true,
            });
            const element = document.elementFromPoint(event.clientX, event.clientY);
            pointer.free = Boolean(element) && !element.closest(BLOCKERS);
            if (this.drag.active) {
                const dx = event.clientX - this.drag.lastX;
                const dy = event.clientY - this.drag.lastY;
                Object.assign(this.drag, { lastX: event.clientX, lastY: event.clientY });
                this.drag.moved += Math.abs(dx) + Math.abs(dy);
                this.drag.yaw += dx * 0.32;
                this.drag.pitch = Math.max(-24, Math.min(24, this.drag.pitch - dy * 0.18));
            }
            this.kick();
        };
        window.addEventListener('pointermove', (event) => { if (event.pointerType !== 'touch') track(event); }, { passive: true });
        window.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) return;
            track(event);
            if (!this.pointer.free || !insideSlot(event.clientX, event.clientY) || !this.scene) return;
            if (event.pointerType === 'touch') {
                this.tap = { id: event.pointerId, x: event.clientX, y: event.clientY, key: this.activeSlot?.dataset.territory, time: performance.now() };
                return;
            }
            Object.assign(this.drag, { active: true, moved: 0, lastX: event.clientX, lastY: event.clientY });
            document.documentElement.classList.add('sg-dragging');
        });
        const release = (event) => {
            if (event.pointerType === 'touch') {
                const tap = this.tap;
                this.tap = null;
                if (!tap || tap.id !== event.pointerId || tap.key !== this.activeSlot?.dataset.territory || performance.now() - tap.time > 700 || Math.hypot(event.clientX - tap.x, event.clientY - tap.y) > 10) return;
                track(event);
                this.click();
                this.pointer.seen = false;
                return;
            }
            if (!this.drag.active) return;
            const wasClick = this.drag.moved < 5;
            Object.assign(this.drag, { active: false, releasedAt: performance.now() });
            document.documentElement.classList.remove('sg-dragging');
            if (wasClick) this.click();
            this.kick();
        };
        window.addEventListener('pointerup', release);
        window.addEventListener('pointercancel', () => {
            this.tap = null;
            this.drag.active = false;
            document.documentElement.classList.remove('sg-dragging');
            this.kick();
        });
        document.addEventListener('pointerleave', () => { this.pointer.seen = false; this.pointer.free = false; this.kick(); });
    }

    /** Click: drill into what the state says the kecamatan belongs to; clicking empty floor zooms back out. */
    click() {
        const key = this.activeSlot?.dataset.territory;
        const spec = key ? this.states.get(key) : null;
        if (!spec || !this.scene) return;
        // Picked at the pointer: hover is cleared while the button is held, so it cannot be trusted here.
        const piece = this.scene.pick(this.pointer.x, -this.pointer.y);
        if (piece && spec.allowedCodes && !spec.allowedCodes.has(piece.code)) return;
        this.scene.fx.pulse({ strength: 1, x: piece ? piece.center.x : 0, z: piece ? piece.center.z : 0 });
        if (spec.activate) { spec.activate(piece?.code || null); return; }
        const codes = piece ? spec.pick?.(piece.code) : null;
        const current = this.overrides.get(key);
        const same = codes && current && codes.size === current.size && [...codes].every((code) => current.has(code));
        this.setOverride(key, same ? null : codes || null);
        this.activeSlot?.dispatchEvent(new CustomEvent('territoryfocus', { bubbles: true, detail: { key, code: piece?.code || null, codes: same ? null : codes || null } }));
    }

    updateHover(spec) {
        const pointer = this.pointer;
        const rect = this.rect;
        const inside = pointer.seen && pointer.free && rect
            && pointer.clientX >= rect.x && pointer.clientX <= rect.x + rect.w && pointer.clientY >= rect.y && pointer.clientY <= rect.y + rect.h;
        document.documentElement.classList.toggle('sg-relief-hot', Boolean(inside && spec?.pick));
        if (!this.scene || !inside || this.drag.active) {
            this.scene?.setHovered(null);
            this.tip.classList.remove('is-on');
            return;
        }
        if (pointer.dirty) {
            pointer.dirty = false;
            this.scene.setHovered(this.scene.pick(pointer.x, -pointer.y));
        }
        const piece = this.scene.hovered;
        if (!piece || !spec?.describe || (spec.allowedCodes && !spec.allowedCodes.has(piece.code))) {
            this.tip.classList.remove('is-on');
            return;
        }
        if (this.tipCode !== piece.code || this.tipState !== this.appliedKey) {
            this.tipCode = piece.code;
            this.tipState = this.appliedKey;
            this.tip.innerHTML = spec.describe(piece.code) || '';
        }
        this.tip.style.transform = `translate3d(${(pointer.clientX + 18).toFixed(1)}px, ${(pointer.clientY - 14).toFixed(1)}px, 0)`;
        this.tip.classList.toggle('is-on', Boolean(this.tip.innerHTML));
    }

    kick(delay = 0) {
        window.clearTimeout(this.idleTimer);
        this.idleTimer = null;
        if (document.hidden) return;
        if (this.frame !== null) return;
        if (delay > 0) {
            this.idleTimer = window.setTimeout(() => { this.idleTimer = null; this.kick(); }, delay);
            return;
        }
        this.queuedAt = performance.now();
        this.frame = window.requestAnimationFrame((time) => this.tick(time));
    }

    progressOf(slot) {
        const host = slot.closest('[data-territory-track]') || slot;
        const rect = host.getBoundingClientRect();
        const viewport = window.innerHeight || 1;
        return host.dataset.territoryTrack === 'flow'
            ? clamp01((viewport - rect.top) / (viewport + rect.height))
            : clamp01(-rect.top / Math.max(1, rect.height - viewport));
    }

    pickSlot() {
        const viewport = window.innerHeight || 1;
        let best = null;
        let bestArea = 0;
        this.slots.forEach((slot) => {
            const inert = slot.closest('[inert]');
            const temporary = inert?.classList.contains('sg-opener') && slot.closest('.is-panel-transition');
            if (!slot.isConnected || slot.closest('[hidden]') || (inert && !temporary) || !this.states.has(slot.dataset.territory)) return;
            const rect = slot.getBoundingClientRect();
            const visible = Math.min(rect.bottom, viewport) - Math.max(rect.top, 0);
            const width = Math.min(rect.right, window.innerWidth) - Math.max(rect.left, 0);
            if (![rect.top, rect.left, rect.width, rect.height].every(Number.isFinite) || visible <= viewport * 0.08 || width < 40 || rect.height < 40 || getComputedStyle(slot).visibility === 'hidden') return;
            // An opaque sheet sliding over the slot hides it while it is still in the viewport.
            const cover = slot.closest('[data-territory-track]')?.nextElementSibling;
            let covered = 0;
            if (cover?.classList.contains('sg-sheet') && !cover.hidden && !slot.closest('.is-panel-transition')) {
                const box = cover.getBoundingClientRect();
                const overlapWidth = Math.max(0, Math.min(rect.right, box.right, window.innerWidth) - Math.max(rect.left, box.left, 0));
                const overlapHeight = Math.max(0, Math.min(rect.bottom, box.bottom, viewport) - Math.max(rect.top, box.top, 0));
                covered = overlapWidth * overlapHeight;
            }
            const area = visible * width - covered;
            if (area > bestArea) {
                bestArea = area;
                best = slot;
            }
        });
        return best;
    }

    /** The journey stop for this progress: focus and labels, unless a click holds its own focus. */
    stopFor(spec, key, progress) {
        const journey = spec.journey || [];
        const stop = journey.find((item) => progress <= item.until) || journey[journey.length - 1] || {};
        const override = this.overrides.get(key);
        return {
            index: journey.indexOf(stop),
            focus: override || stop.focus || null,
            labels: override ? (spec.labelsFor?.(override) || spec.labels || []) : (stop.labels || spec.labels || []),
            heights: stop.heights || spec.heights,
            caption: override ? (spec.captionFor?.(override) || '') : (stop.caption || ''),
        };
    }

    applyState(key, spec, stop) {
        const identity = `${key}|${stop.index}|${this.overrides.has(key) ? [...this.overrides.get(key)].join(',') : ''}|${spec.version || 0}`;
        if (this.appliedKey === identity) return;
        const swapped = this.appliedKey?.split('|')[0] !== key;
        this.appliedKey = identity;
        const targets = new Map();
        const focus = stop.focus;
        this.features.forEach((feature) => {
            const code = String(feature.properties?.KDCPUM);
            const value = stop.heights?.(code) || { lift: 0, color: 0xb9bcc4 };
            // Outside the focus the relief sinks and greys out, so the eye lands where the camera goes.
            const outside = focus && !focus.has(code);
            targets.set(code, outside ? { lift: (value.lift || 0) * 0.18, color: spec.dark ? 0x2e3036 : 0xd5d7dc, roughness: 0.5, core: 0 } : value);
        });
        this.scene.setTargets(targets, { glass: spec.glass || 0, dark: spec.dark || 0 });
        this.scene.fx.setMode(spec.fx || { beams: false, flow: 0, speed: 0.8, color: 0x2c6fe0 });
        this.scene.setFocus(focus);
        if (!swapped) this.scene.fx.pulse({ strength: 0.6 });
        this.labelLayer.removeAttribute('aria-hidden');
        const targetsForSelection = this.interactiveTargets.get(key) || [];
        this.updateLabels(targetsForSelection.length ? targetsForSelection : stop.labels);
        this.activeSlot?.dispatchEvent(new CustomEvent('territorystop', { bubbles: true, detail: { key, index: stop.index, caption: stop.caption } }));
    }

    /** A figure from the previous state must never linger over the next one: only entrances are staged. */
    hideLabel(element) {
        element.style.transition = 'none';
        element.style.opacity = '0';
        element.inert = true;
    }

    updateLabels(items) {
        const keep = new Set(items.map((item) => item.id));
        this.labels.forEach((element, id) => {
            if (!keep.has(id)) this.hideLabel(element);
        });
        items.forEach((item) => {
            let element = this.labels.get(item.id);
            if (!element) {
                element = document.createElement(item.action ? 'button' : 'div');
                element.className = 'sg-relief__label';
                if (item.action) { element.type = 'button'; element.classList.add('sg-relief__target'); element.addEventListener('click', () => element._action?.()); }
                this.labelLayer.append(element);
                this.labels.set(item.id, element);
            }
            element.innerHTML = `<small>${escapeText(item.title)}</small><strong>${escapeText(item.value)}</strong>`;
            element._action = item.action;
            element.setAttribute('aria-label', item.ariaLabel || item.title);
            element.setAttribute('aria-hidden', String(!item.action));
            element.inert = false;
            element._w = 0;
            element._h = 0;
            element.style.setProperty('--sg-label-i', String(items.indexOf(item)));
        });
        this.labelItems = items;
    }

    placeLabels(show) {
        if (!this.scene || !this.labelItems) return;
        const placed = [];
        this.labelItems.forEach((item) => {
            const element = this.labels.get(item.id);
            const anchor = element && this.scene.anchorOf(item.codes);
            if (!anchor || !show) {
                if (element) this.hideLabel(element);
                return;
            }
            const point = this.scene.screenOf(anchor);
            const rect = this.rect;
            const inside = point.visible && (element._action || (point.x > rect.x - 30 && point.x < rect.x + rect.w + 30 && point.y > rect.y - 10 && point.y < rect.y + rect.h + 10));
            element._w ||= element.offsetWidth || 120;
            element._h ||= element.offsetHeight || 44;
            placed.push({ element, x: point.x, y: point.y, anchorY: point.y, inside });
        });
        placed.sort((a, b) => b.y - a.y);
        placed.forEach((label, index) => {
            for (let j = 0; j < index; j += 1) {
                const other = placed[j];
                if (Math.abs(label.x - other.x) < (label.element._w + other.element._w) / 2 + 8 && label.y > other.y - other.element._h - 10) label.y = other.y - other.element._h - 10;
            }
        });
        const ceiling = Math.max(document.querySelector('.sg-nav')?.getBoundingClientRect().bottom || 0,
            this.activeSlot?.closest('.sg-performance-chapter') ? document.querySelector('.sg-room-head:not([hidden])')?.getBoundingClientRect().bottom || 0 : 0);
        const actions = placed.filter((label) => label.element._action && label.inside);
        if (actions.length) {
            const highest = Math.min(...actions.map((label) => label.y - label.element._h - 14));
            const shift = Math.max(0, ceiling + 12 - highest);
            actions.forEach((label) => {
                label.y = Math.min(window.innerHeight - 12, label.y + shift);
                label.x = Math.max(label.element._w / 2 + 12, Math.min(window.innerWidth - label.element._w / 2 - 12, label.x));
            });
        }
        // Clamping projected KC labels to a narrow viewport can reintroduce overlap.
        // Pack the financial gateway targets after that clamp; stems retain their map anchors.
        if (actions.length > 1 && window.innerWidth <= 760 && this.activeSlot?.closest('.sg-performance-chapter, .sg-polygon-chapter')) {
            const ordered = [...actions].sort((a, b) => b.y - a.y);
            ordered.forEach((label, index) => {
                for (let j = 0; j < index; j += 1) {
                    const other = ordered[j];
                    if (Math.abs(label.x - other.x) < (label.element._w + other.element._w) / 2 + 8
                        && label.y > other.y - other.element._h - 10) label.y = other.y - other.element._h - 10;
                }
            });
            const top = Math.max(ceiling, this.rect.y) + 10;
            const bottom = Math.min(window.innerHeight, this.rect.y + this.rect.h) - 12;
            const totalHeight = actions.reduce((sum, label) => sum + label.element._h, 0);
            if (totalHeight + (actions.length - 1) * 6 <= bottom - top) {
                const gap = Math.min(10, (bottom - top - totalHeight) / (actions.length - 1));
                const span = totalHeight + gap * (actions.length - 1);
                const ascending = [...ordered].reverse();
                let cursor = Math.max(top, Math.min(ascending[0].y - ascending[0].element._h - 14, bottom - span));
                ascending.forEach((label) => { label.y = cursor + label.element._h + 14; cursor += label.element._h + gap; });
            }
        }
        // A short landscape viewport must keep every KC hit target separate below the rail.
        if (actions.length > 1 && window.innerHeight <= 500 && window.innerWidth > 760 && window.innerWidth <= 960
            && this.activeSlot?.closest('.sg-polygon-chapter') && this.labelItems.every((item) => item.id.startsWith('pick-'))) {
            const rail = this.activeSlot.closest('[data-sg-deck]')?.querySelector('.sg-story-rail:not([hidden])');
            const top = Math.max(ceiling, rail?.getBoundingClientRect().bottom || 0) + 8;
            const bottom = window.innerHeight - 12;
            const totalHeight = actions.reduce((sum, label) => sum + label.element._h, 0);
            if (totalHeight <= bottom - top) {
                const gap = Math.min(10, (bottom - top - totalHeight) / (actions.length - 1));
                const ordered = [...actions].sort((a, b) => a.y - b.y);
                const span = totalHeight + gap * (actions.length - 1);
                let cursor = Math.max(top, Math.min(ordered[0].y - ordered[0].element._h - 14, bottom - span));
                ordered.forEach((label) => { label.y = cursor + label.element._h + 14; cursor += label.element._h + gap; });
            }
        }
        placed.forEach((label) => {
            const labelCeiling = window.innerWidth <= 760 && this.activeSlot?.closest('.sg-performance-chapter, .sg-polygon-chapter')
                ? Math.max(ceiling + 6, this.rect.y + 6) : ceiling + 6;
            const visible = label.inside && label.y - label.element._h - 14 > labelCeiling;
            label.element.style.setProperty('--sg-stem', `${(14 + Math.max(0, label.anchorY - label.y)).toFixed(1)}px`);
            label.element.style.transform = `translate3d(${label.x.toFixed(1)}px, ${label.y.toFixed(1)}px, 0)`;
            if (visible) { label.element.style.transition = ''; label.element.style.opacity = '1'; label.element.inert = false; }
            else this.hideLabel(label.element);
        });
    }

    tick(time) {
        this.frame = null;
        if (document.hidden) { this.cancelFocusWait(); return; }
        if (this.lastTime !== null && time - this.lastTime < 1000 / this.profile.activeFps - .5) {
            this.kick(1000 / this.profile.activeFps - (time - this.lastTime));
            return;
        }
        if (this.budget.observe(time - this.queuedAt, this.continuous) && this.scene) {
            this.scene.maxPixelRatio = this.profile.maxPixelRatio * this.budget.scale;
            this.scene.maxPixels = this.profile.maxPixels * this.budget.scale ** 2;
            this.scene.resize(window.innerWidth, window.innerHeight);
        }
        const still = reduceMotion();
        const dt = this.lastTime === null ? 1 / 60 : Math.min(0.05, Math.max(0, (time - this.lastTime) / 1000));
        const elapsed = this.lastTime === null ? dt : Math.min(0.25, (time - this.lastTime) / 1000);
        this.lastTime = time;
        if (!still) this.time += dt;

        const scrollY = window.scrollY;
        if (this.lastScrollY !== null && elapsed > 0) {
            const raw = Math.min(1.4, Math.abs(scrollY - this.lastScrollY) / elapsed / 2600);
            this.velocity = damp(this.velocity, raw, raw > this.velocity ? 12 : 3.2, dt);
        }
        this.lastScrollY = scrollY;
        this.pointer.sx = damp(this.pointer.sx, this.pointer.seen ? this.pointer.x : 0, 4.5, dt);
        this.pointer.sy = damp(this.pointer.sy, this.pointer.seen ? this.pointer.y : 0, 4.5, dt);
        if (!this.drag.active && performance.now() - this.drag.releasedAt > 2400) {
            this.drag.yaw = damp(this.drag.yaw, 0, 1.4, dt);
            this.drag.pitch = damp(this.drag.pitch, 0, 1.4, dt);
        }
        this.swoop = still ? 0 : damp(this.swoop, 0, 2.4, dt);

        const slot = this.pickSlot();
        this.layer.classList.toggle('is-visible', Boolean(slot && this.scene));
        if (!slot) {
            this.releaseSurface();
            return;
        }
        const key = slot.dataset.territory;
        const spec = this.states.get(key);
        if (slot !== this.activeSlot) {
            if (this.activeSlot && !still && this.flightP === null) {
                this.swoop = 1;
                this.swoopDirection *= -1;
            }
            this.activeSlot = slot;
            if (!this.handoff) this.rect = null;
        }
        const dark = spec.dark > 0.5;
        this.layer.classList.toggle('is-dark', dark);
        this.labelLayer.classList.toggle('is-dark', dark);
        if (!this.scene) {
            this.checkFocusFrame({ key, slot, still });
            return;
        }

        const progress = still ? 0.5 : this.progressOf(slot);
        // Without motion the camera holds the overview: the first stop, not whichever stop sits mid-way.
        const stop = this.stopFor(spec, key, still ? 0 : progress);
        this.applyState(key, spec, stop);
        const arrangement = { ...(spec.arrangement?.(progress, still) || { explode: 0, spread: 0, levitate: 0, levitateBranch: null, grow: 1, wave: 0 }) };
        // Arrival: the new room's figures rise out of the floor and its pieces close up.
        let settledIn = 1;
        if (this.arrival && !still) {
            if (this.arrival.start === null) {
                this.arrival.start = time;
                Object.assign(this.scene.arrangement, { grow: 0.1, explode: Math.max(arrangement.explode || 0, this.arrival.assemble) });
            }
            settledIn = clamp01((time - this.arrival.start) / 1400);
            const rise = 1 - ((1 - settledIn) ** 3);
            arrangement.grow = (arrangement.grow ?? 1) * (0.1 + 0.9 * rise);
            arrangement.explode = Math.max(arrangement.explode || 0, this.arrival.assemble * (1 - rise));
            if (settledIn >= 1) this.arrival = null;
        } else if (still) this.arrival = null;
        this.scene.setArrangement(arrangement);

        const bounds = slot.getBoundingClientRect();
        // The camera owns this reserved visual frame, never the surrounding financial reading.
        const clip = { top: Math.max(0, bounds.top), right: Math.max(0, window.innerWidth - bounds.right), bottom: Math.max(0, window.innerHeight - bounds.bottom), left: Math.max(0, bounds.left) };
        // During a hand-off the frame itself travels, so a full-screen flight lands inside the slot.
        if (this.handoff && !still && this.clip) ['top', 'right', 'bottom', 'left'].forEach((side) => { this.clip[side] = damp(this.clip[side], clip[side], 5, dt); });
        else this.clip = clip;
        this.layer.style.clipPath = `inset(${this.clip.top.toFixed(1)}px ${this.clip.right.toFixed(1)}px ${this.clip.bottom.toFixed(1)}px ${this.clip.left.toFixed(1)}px)`;
        const headroom = Math.min(64, bounds.height * 0.14);
        const target = { x: bounds.left, y: bounds.top + headroom, w: bounds.width, h: bounds.height - headroom };
        if (!this.rect || still) this.rect = { ...target };
        else ['x', 'y', 'w', 'h'].forEach((side) => { this.rect[side] = damp(this.rect[side], target[side], this.handoff ? 5 : 9, dt); });

        const pose = { ...(POSES[slot.dataset.territoryPose] || POSES.lead)(still ? 0.5 : progress) };
        if (this.introStart === null && !still && document.documentElement.classList.contains('sg-ready')) this.introStart = time;
        const intro = still ? 1 : this.introStart === null ? 0 : Math.min(1, (time - this.introStart) / 3000);
        const introEase = 1 - ((1 - intro) ** 3);
        pose.yaw += -(1 - introEase) * 34 + this.swoop * this.swoopDirection * 24 + this.drag.yaw;
        pose.pitch += -(1 - introEase) * 12 - this.swoop * 8 + this.drag.pitch;
        pose.fill *= (0.86 + introEase * 0.14) * (1 - this.swoop * 0.1);
        if (intro < 1) this.kick();
        if (this.flightP !== null) {
            // territoryFlight arrives overhead; the slot's own pose is reached from that same angle.
            const overhead = 1 - smoothstep(0.49, 0.8, this.flightP);
            pose.yaw += (0 - pose.yaw) * overhead;
            pose.pitch += (89.8 - pose.pitch) * overhead;
        }
        if (this.handoff && !still && this.lastPose) {
            pose.yaw = damp(this.lastPose.yaw, pose.yaw, 4, dt);
            pose.pitch = damp(this.lastPose.pitch, pose.pitch, 4, dt);
            pose.fill = damp(this.lastPose.fill, pose.fill, 4, dt);
        }
        this.lastPose = pose;
        if (this.handoff && performance.now() > this.handoff.until) this.handoff = null;

        const settling = this.scene.render({
            dt: still ? 1 : dt,
            still,
            rect: this.rect,
            yaw: pose.yaw,
            pitch: pose.pitch,
            fill: pose.fill,
            light: still ? 0.12 : 0.12 + this.time * 0.016,
            time: this.time,
            intro,
            flight: this.flightP ?? undefined,
            velocity: still ? 0 : this.velocity,
            direction: 1,
            px: 0,
            py: 0,
        });

        // Lens: a touch of blur while the camera travels to a new focus or swings to a new chapter.
        const blur = still ? 0 : Math.min(3.2, this.scene.zooming * 4.2 + this.swoop * 2.4);
        this.blur = still ? 0 : damp(this.blur, blur, blur > this.blur ? 14 : 5, dt);
        this.layer.style.setProperty('--sg-blur', `${this.blur.toFixed(2)}px`);

        this.checkFocusFrame({ key, slot, still, rendered: true, target });
        if (this.focusWait) this.kick();

        this.placeLabels((!this.scene.zooming || this.scene.zooming < 0.25) && settledIn > 0.55 && !this.handoff && this.flightP === null);
        if (!still) this.updateHover(spec);
        const moving = ['x', 'y', 'w', 'h'].some((side) => Math.abs(this.rect[side] - target[side]) > 0.5) || this.blur > 0.05;
        this.continuous = !still && Boolean(settling || moving || this.swoop > 0.01 || this.arrival || this.handoff || this.flightP !== null || this.drag.active || Math.abs(this.drag.yaw) > 0.05 || this.velocity > .012);
        if (this.continuous) this.kick();
        else if (!still && this.profile.ambientFps) this.kick(1000 / this.profile.ambientFps);
    }
}
