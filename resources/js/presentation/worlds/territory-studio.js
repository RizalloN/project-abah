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

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const clamp01 = (value) => Math.min(1, Math.max(0, value));
const damp = (current, target, lambda, dt) => current + (target - current) * (1 - Math.exp(-lambda * dt));
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
        this.ready = new Promise((resolve) => { this.resolveReady = resolve; });
    }

    init() {
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
        document.addEventListener('visibilitychange', () => this.kick());
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
            const { ReliefScene } = await import('./relief.js');
            this.canvas = document.createElement('canvas');
            this.layer.append(this.canvas);
            this.scene = new ReliefScene(this.canvas, this.features, { maxPixelRatio: finePointer() ? 1.75 : 1.5, dustCount: finePointer() ? 900 : 420 });
            // The presentation uses a quiet atlas, without the shared scene's orbiting bezel/satellites.
            this.scene.fx.rings.visible = false;
            // Nothing circles, pulses or lifts under the pointer: hover only names the kecamatan.
            this.scene.fx.calm = true;
            this.scene.quietHover = true;
            this.scene.resize(window.innerWidth, window.innerHeight);
            this.scene.precompile();
            this.appliedKey = null;
            document.documentElement.classList.add('sg-relief-live');
            this.kick();
        } catch (error) {
            console.warn('Territory relief fell back to its poster.', error);
            this.resolveReady(this.features);
            document.documentElement.classList.replace('sg-relief-on', 'sg-relief-off');
        }
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
        this.kick();
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
        this.slots.filter((slot) => slot.dataset.territory === key).forEach((slot) => {
            const svg = slot.querySelector('[data-territory-poster] svg');
            if (!svg) return;
            const boxes = [];
            svg.querySelectorAll('[data-code]').forEach((path) => {
                const focused = Boolean(codes?.has(path.dataset.code));
                path.classList.toggle('is-focused', focused);
                path.classList.toggle('is-outside', Boolean(codes && !focused));
                if (focused) boxes.push(path.getBBox());
            });
            if (boxes.length) {
                const x = Math.min(...boxes.map((box) => box.x));
                const y = Math.min(...boxes.map((box) => box.y));
                const right = Math.max(...boxes.map((box) => box.x + box.width));
                const bottom = Math.max(...boxes.map((box) => box.y + box.height));
                svg.setAttribute('viewBox', `${x - 12} ${y - 12} ${right - x + 24} ${bottom - y + 24}`);
            } else svg.setAttribute('viewBox', '0 0 600 560');
        });
        this.appliedKey = null;
        this.kick();
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

    kick() {
        if (this.frame !== null) return;
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
            if (!slot.isConnected || !this.states.has(slot.dataset.territory)) return;
            const rect = slot.getBoundingClientRect();
            const visible = Math.min(rect.bottom, viewport) - Math.max(rect.top, 0);
            if (visible <= viewport * 0.08 || rect.width < 40) return;
            // An opaque sheet sliding over the slot hides it while it is still in the viewport.
            const cover = slot.closest('[data-territory-track]')?.nextElementSibling;
            const covered = cover?.classList.contains('sg-sheet') && !cover.hidden ? Math.max(0, viewport - Math.max(0, cover.getBoundingClientRect().top)) : 0;
            const area = (visible - Math.min(visible, covered)) * rect.width;
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

    updateLabels(items) {
        const keep = new Set(items.map((item) => item.id));
        this.labels.forEach((element, id) => {
            if (!keep.has(id)) { element.style.opacity = '0'; element.inert = true; }
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
                if (element) { element.style.opacity = '0'; element.inert = true; }
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
        const ceiling = document.querySelector('.sg-nav')?.getBoundingClientRect().bottom || 0;
        const actions = placed.filter((label) => label.element._action && label.inside);
        if (actions.length) {
            const highest = Math.min(...actions.map((label) => label.y - label.element._h - 14));
            const shift = Math.max(0, ceiling + 12 - highest);
            actions.forEach((label) => {
                label.y = Math.min(window.innerHeight - 12, label.y + shift);
                label.x = Math.max(label.element._w / 2 + 12, Math.min(window.innerWidth - label.element._w / 2 - 12, label.x));
            });
        }
        placed.forEach((label) => {
            const visible = label.inside && label.y - label.element._h - 14 > ceiling + 6;
            label.element.style.setProperty('--sg-stem', `${(14 + Math.max(0, label.anchorY - label.y)).toFixed(1)}px`);
            label.element.style.transform = `translate3d(${label.x.toFixed(1)}px, ${label.y.toFixed(1)}px, 0)`;
            label.element.style.opacity = visible ? '1' : '0';
            label.element.inert = !visible;
        });
    }

    tick(time) {
        this.frame = null;
        if (document.hidden) return;
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
        this.swoop = damp(this.swoop, 0, 2.4, dt);

        const slot = this.pickSlot();
        this.layer.classList.toggle('is-visible', Boolean(slot && this.scene));
        if (!slot) {
            this.labels.forEach((element) => { element.style.opacity = '0'; element.inert = true; });
            this.tip.classList.remove('is-on');
            this.rect = null;
            return;
        }
        const key = slot.dataset.territory;
        const spec = this.states.get(key);
        if (slot !== this.activeSlot) {
            if (this.activeSlot && !still) {
                this.swoop = 1;
                this.swoopDirection *= -1;
            }
            this.activeSlot = slot;
            this.rect = null;
        }
        const dark = spec.dark > 0.5;
        this.layer.classList.toggle('is-dark', dark);
        this.labelLayer.classList.toggle('is-dark', dark);
        if (!this.scene) return;

        const progress = still ? 0.5 : this.progressOf(slot);
        // Without motion the camera holds the overview: the first stop, not whichever stop sits mid-way.
        const stop = this.stopFor(spec, key, still ? 0 : progress);
        this.applyState(key, spec, stop);
        this.scene.setArrangement(spec.arrangement?.(progress, still) || { explode: 0, spread: 0, levitate: 0, levitateBranch: null, grow: 1, wave: 0 });

        const bounds = slot.getBoundingClientRect();
        // On stacked phone layouts, outside-branch context must not cover the reading below the map.
        this.layer.style.clipPath = window.innerWidth <= 960
            ? `inset(${Math.max(0, bounds.top).toFixed(1)}px 0 ${Math.max(0, window.innerHeight - bounds.bottom).toFixed(1)}px 0)`
            : '';
        const headroom = Math.min(64, bounds.height * 0.14);
        const target = { x: bounds.left, y: bounds.top + headroom, w: bounds.width, h: bounds.height - headroom };
        if (!this.rect || still) this.rect = { ...target };
        else ['x', 'y', 'w', 'h'].forEach((side) => { this.rect[side] = damp(this.rect[side], target[side], 9, dt); });

        const pose = { ...(POSES[slot.dataset.territoryPose] || POSES.lead)(still ? 0.5 : progress) };
        if (this.introStart === null && !still && document.documentElement.classList.contains('sg-ready')) this.introStart = time;
        const intro = still ? 1 : this.introStart === null ? 0 : Math.min(1, (time - this.introStart) / 3000);
        const introEase = 1 - ((1 - intro) ** 3);
        pose.yaw += -(1 - introEase) * 34 + this.swoop * this.swoopDirection * 24 + this.drag.yaw;
        pose.pitch += -(1 - introEase) * 12 - this.swoop * 8 + this.drag.pitch;
        pose.fill *= (0.86 + introEase * 0.14) * (1 - this.swoop * 0.1);
        if (intro < 1) this.kick();

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
            velocity: still ? 0 : this.velocity,
            direction: 1,
            px: 0,
            py: 0,
        });

        // Lens: a touch of blur while the camera travels to a new focus or swings to a new chapter.
        const blur = still ? 0 : Math.min(3.2, this.scene.zooming * 4.2 + this.swoop * 2.4);
        this.blur = still ? 0 : damp(this.blur, blur, blur > this.blur ? 14 : 5, dt);
        this.layer.style.setProperty('--sg-blur', `${this.blur.toFixed(2)}px`);

        this.placeLabels(!this.scene.zooming || this.scene.zooming < 0.25);
        if (!still) this.updateHover(spec);
        const moving = ['x', 'y', 'w', 'h'].some((side) => Math.abs(this.rect[side] - target[side]) > 0.5) || this.blur > 0.05;
        if (!still && (settling || moving || this.swoop > 0.01 || this.drag.active || Math.abs(this.drag.yaw) > 0.05 || this.pointer.seen)) this.kick();
        else if (!still) window.setTimeout(() => this.kick(), 120);
    }
}
