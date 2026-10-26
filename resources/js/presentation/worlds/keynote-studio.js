/*
 * Keynote studio: the one persistent relief.
 *
 * A single fixed canvas sits under the report sheets and above the statement
 * grounds. Every [data-relief-slot] (the hero stage, each chapter statement, the
 * close) is a rectangle the relief fits itself into; the relief glides between
 * them and re-forms to the state of the slot in view. Sheets are opaque and
 * sit above the canvas, so they slide over the object like pages.
 *
 * Three.js loads lazily. Without WebGL (or before it is ready) each slot shows
 * an SVG silhouette drawn from the same kecamatan polygons.
 */

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const clamp01 = (value) => Math.min(1, Math.max(0, value));
const smooth = (value) => value * value * (3 - 2 * value);
const damp = (current, target, lambda, dt) => current + (target - current) * (1 - Math.exp(-lambda * dt));
const escapeText = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));

const supportsWebGL = () => {
    try {
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('webgl2');
        context?.getExtension('WEBGL_lose_context')?.loseContext();
        return Boolean(context);
    } catch (_) {
        return false;
    }
};

/** Branch office to the regencies (WADMKK) it serves. */
export const BRANCH_REGENCIES = [
    { key: 'madiun', label: 'KC Madiun', regencies: ['MADIUN', 'KOTA MADIUN'] },
    { key: 'magetan', label: 'KC Magetan', regencies: ['MAGETAN'] },
    { key: 'ngawi', label: 'KC Ngawi', regencies: ['NGAWI'] },
    { key: 'ponorogo', label: 'KC Ponorogo', regencies: ['PONOROGO'] },
];

export const branchKeyOf = (label) => {
    const token = String(label || '').toUpperCase().replace(/^KC\s+/, '').trim();
    return BRANCH_REGENCIES.find((branch) => branch.regencies[0] === token || token.includes(branch.regencies[0]))?.key || null;
};

const PALETTE = {
    titanium: 0xc8cacf,
    titaniumDark: 0x8e9096,
    blue: 0x2c6fe0,
    blueDeep: 0x1d4fb0,
    silverBlue: 0xc9d6ea,
    natural: 0xb9b3a8,
    down: 0xd2453b,
    amber: 0xff9a2e,
    red: 0xff3b30,
    muted: 0x7b7d84,
};

const mix = (a, b, t) => {
    const ar = (a >> 16) & 255; const ag = (a >> 8) & 255; const ab = a & 255;
    const br = (b >> 16) & 255; const bg = (b >> 8) & 255; const bb = b & 255;
    return (Math.round(ar + (br - ar) * t) << 16) | (Math.round(ag + (bg - ag) * t) << 8) | Math.round(ab + (bb - ab) * t);
};

/** Camera pose per slot kind; progress (0..1) runs through the slot's own section. */
const POSES = {
    hero: (p) => ({ yaw: -30 + p * 22, pitch: 24 + p * 38, fill: 1 - p * 0.08 }),
    lead: (p) => ({ yaw: -30 + p * 24, pitch: 44 + p * 8, fill: 0.9 }),
    trail: (p) => ({ yaw: 30 - p * 24, pitch: 44 + p * 8, fill: 0.9 }),
    theatre: (p) => ({ yaw: -40 + p * 46, pitch: 34 + p * 10, fill: 0.84 }),
    close: () => ({ yaw: -24, pitch: 26, fill: 0.95 }),
};

const SLOT_POSE = { hero: 'hero', pasar: 'lead', dana: 'trail', pinjaman: 'lead', kualitas: 'theatre', tren: 'trail', aksi: 'lead', close: 'close' };

export class KeynoteStudio {
    constructor({ root, config, source }) {
        this.root = root;
        this.config = config || {};
        this.source = source;
        this.slots = [];
        this.features = [];
        this.data = null;
        this.scene = null;
        this.frame = null;
        this.lastTime = null;
        this.time = 0;
        this.motionPaused = false;
        this.rect = null;
        this.activeKey = null;
        this.stateKey = null;
        this.labels = new Map();
    }

    init() {
        this.webgl = 'IntersectionObserver' in window && supportsWebGL();
        document.documentElement.classList.add(this.webgl ? 'kn-relief-on' : 'kn-relief-off');

        this.layer = document.createElement('div');
        this.layer.className = 'kn-relief';
        this.layer.setAttribute('aria-hidden', 'true');
        this.labelLayer = document.createElement('div');
        this.labelLayer.className = 'kn-relief__labels';
        this.labelLayer.setAttribute('aria-hidden', 'true');
        document.body.append(this.layer, this.labelLayer);

        window.addEventListener('scroll', () => this.kick(), { passive: true });
        window.addEventListener('resize', () => { this.resize(); this.kick(); }, { passive: true });
        document.addEventListener('visibilitychange', () => this.kick());
        this.scan();
        this.load();
    }

    async load() {
        try {
            const url = this.config.geographyUrl || new URL('data/marketshare-area6-kecamatan.geojson', document.baseURI).href;
            const response = await fetch(url, { cache: 'force-cache' });
            const geo = await response.json();
            this.features = (geo?.features || []).filter((feature) => feature?.geometry);
            this.paintPosters();
            if (!this.webgl || !this.features.length) return;
            const { ReliefScene } = await import('./relief.js');
            this.canvas = document.createElement('canvas');
            this.layer.append(this.canvas);
            this.scene = new ReliefScene(this.canvas, this.features, { maxPixelRatio: finePointer() ? 1.75 : 1.5 });
            this.resize();
            this.applyState(true);
            this.scene.precompile();
            document.documentElement.classList.add('kn-relief-live');
            this.kick();
        } catch (error) {
            console.warn('Keynote relief fell back to its poster.', error);
            document.documentElement.classList.remove('kn-relief-on');
            document.documentElement.classList.add('kn-relief-off');
            this.layer.classList.remove('is-visible');
        }
    }

    /** Slots are re-read after every section render (the hero slot lives in a rendered section). */
    scan() {
        this.slots = Array.from(document.querySelectorAll('[data-relief-slot]'));
        this.paintPosters();
        this.kick();
    }

    setPaused(paused) {
        this.motionPaused = Boolean(paused);
        this.kick();
    }

    refreshData() {
        try {
            this.data = this.source?.() || null;
        } catch (error) {
            console.warn('Keynote relief: data source failed.', error);
            this.data = null;
        }
        this.applyState(true);
        this.kick();
    }

    resize() {
        if (!this.scene) return;
        this.scene.resize(window.innerWidth, window.innerHeight);
    }

    paintPosters() {
        if (!this.features.length) return;
        const markup = this.posterMarkup();
        document.querySelectorAll('[data-relief-poster]').forEach((poster) => {
            if (!poster.dataset.painted) {
                poster.innerHTML = markup;
                poster.dataset.painted = '1';
            }
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
            return `<path d="${d}"/>`;
        }).join('');
        this.posterCache = `<svg viewBox="0 0 600 560" role="presentation" focusable="false">${paths}</svg>`;
        return this.posterCache;
    }

    kick() {
        if (this.frame !== null) return;
        this.frame = window.requestAnimationFrame((time) => this.tick(time));
    }

    progressOf(slot) {
        const host = slot.closest('.kn-statement, .kn-hero, .kn-close');
        if (!host) return 0;
        const rect = host.getBoundingClientRect();
        const viewport = window.innerHeight || 1;
        if (host.classList.contains('kn-close')) return clamp01((viewport - rect.top) / viewport);
        return clamp01(-rect.top / Math.max(1, rect.height - viewport));
    }

    /** The slot with the most visible area wins; none means the object rests. */
    activeSlot() {
        const viewport = window.innerHeight || 1;
        let best = null;
        let bestArea = 0;
        this.slots.forEach((slot) => {
            if (!slot.isConnected) return;
            const rect = slot.getBoundingClientRect();
            const visible = Math.min(rect.bottom, viewport) - Math.max(rect.top, 0);
            if (visible <= viewport * 0.08 || rect.width < 40) return;
            // A sheet sliding over the statement hides the slot even while it is in the viewport.
            const host = slot.closest('.kn-statement, .kn-hero, .kn-close');
            const next = host?.closest('.kn-statement') ? host.nextElementSibling : null;
            const covered = next ? Math.max(0, viewport - Math.max(0, next.getBoundingClientRect().top)) : 0;
            const area = (visible - covered) * rect.width;
            if (area > bestArea) {
                bestArea = area;
                best = slot;
            }
        });
        return bestArea > 0 ? best : null;
    }

    stateFor(slotKey, progress) {
        if (slotKey === 'kualitas') return progress < 0.5 ? 'sml' : 'npl';
        if (slotKey === 'close' || slotKey === 'aksi') return 'rest';
        return slotKey;
    }

    /** Target heights, colours and glass for one state, from the renderer's figures. */
    targetsFor(stateKey) {
        const targets = new Map();
        const states = this.data?.states || {};
        const codesByBranch = this.codesByBranch();
        const branchOf = this.branchOfCode();
        const scoped = this.data?.scopedBranch || null;
        const set = (code, values) => targets.set(code, values);
        const dimmed = (code) => scoped && branchOf.get(code) !== scoped;

        const neutral = (color = PALETTE.titanium) => this.features.forEach((feature) => set(String(feature.properties.KDCPUM), { lift: 0, color, roughness: 0.3 }));

        if (stateKey === 'pasar' && states.pasar?.kind === 'branch') {
            const values = states.pasar.values || {};
            const max = Math.max(1e-6, ...Object.values(values).map(Number).filter(Number.isFinite));
            this.features.forEach((feature) => {
                const code = String(feature.properties.KDCPUM);
                const raw = Number(values[branchOf.get(code)]);
                const t = Number.isFinite(raw) ? clamp01(raw / max) : 0;
                set(code, Number.isFinite(raw) && !dimmed(code)
                    ? { lift: 0.08 + t * 0.92, color: mix(PALETTE.silverBlue, PALETTE.blue, smooth(t)), roughness: 0.26 }
                    : { lift: 0, color: PALETTE.muted, roughness: 0.4 });
            });
            return { targets, glass: 0, dark: 0 };
        }

        if (stateKey === 'pasar' && states.pasar) {
            const values = states.pasar.values || {};
            const max = Math.max(1e-6, ...Object.values(values).map(Number).filter(Number.isFinite));
            this.features.forEach((feature) => {
                const code = String(feature.properties.KDCPUM);
                const raw = Number(values[code]);
                const t = Number.isFinite(raw) ? clamp01(raw / max) : 0;
                set(code, { lift: Number.isFinite(raw) ? 0.05 + t * 0.95 : 0, color: Number.isFinite(raw) ? mix(PALETTE.silverBlue, PALETTE.blue, smooth(t)) : PALETTE.muted, roughness: 0.28 });
            });
            return { targets, glass: 0, dark: 0 };
        }

        const branchState = (values, color, { signed = false } = {}) => {
            const numbers = Object.values(values).map(Number).filter(Number.isFinite);
            const max = Math.max(1e-6, ...numbers.map((value) => Math.abs(value)));
            this.features.forEach((feature) => {
                const code = String(feature.properties.KDCPUM);
                const branch = branchOf.get(code);
                const raw = Number(values[branch]);
                if (!Number.isFinite(raw) || dimmed(code)) {
                    set(code, { lift: 0, color: PALETTE.muted, roughness: 0.4 });
                    return;
                }
                if (signed) {
                    const t = raw / max;
                    set(code, { lift: 0.36 + t * 0.6, color: raw >= 0 ? PALETTE.blue : PALETTE.down, roughness: 0.3 });
                } else {
                    set(code, { lift: 0.08 + (raw / max) * 0.92, color, roughness: 0.3 });
                }
            });
        };

        if (stateKey === 'dana' && states.dana) {
            branchState(states.dana.values || {}, PALETTE.blue);
            return { targets, glass: 0, dark: 0 };
        }
        if (stateKey === 'pinjaman' && states.pinjaman) {
            branchState(states.pinjaman.values || {}, PALETTE.natural);
            return { targets, glass: 0, dark: 0 };
        }
        if (stateKey === 'tren' && states.tren) {
            branchState(states.tren.values || {}, PALETTE.blue, { signed: true });
            return { targets, glass: 0, dark: 0 };
        }
        if ((stateKey === 'sml' || stateKey === 'npl') && states[stateKey]) {
            const values = states[stateKey].values || {};
            const max = Math.max(1e-6, ...Object.values(values).map(Number).filter(Number.isFinite));
            const color = stateKey === 'sml' ? PALETTE.amber : PALETTE.red;
            this.features.forEach((feature) => {
                const code = String(feature.properties.KDCPUM);
                const raw = Number(values[branchOf.get(code)]);
                set(code, { lift: 0.62, color, roughness: 0.12, core: Number.isFinite(raw) && !dimmed(code) ? 0.14 + (raw / max) * 0.72 : 0 });
            });
            return { targets, glass: 1, dark: 1 };
        }
        if (stateKey === 'hero') {
            neutral(PALETTE.titanium);
            return { targets, glass: 0, dark: 1 };
        }
        neutral(PALETTE.titanium);
        return { targets, glass: 0, dark: stateKey === 'rest' ? 0.6 : 0 };
    }

    codesByBranch() {
        if (this.codesByBranchCache) return this.codesByBranchCache;
        const map = new Map(BRANCH_REGENCIES.map((branch) => [branch.key, new Set()]));
        this.features.forEach((feature) => {
            const regency = String(feature.properties.WADMKK || '').toUpperCase();
            const branch = BRANCH_REGENCIES.find((item) => item.regencies.includes(regency));
            if (branch) map.get(branch.key).add(String(feature.properties.KDCPUM));
        });
        this.codesByBranchCache = map;
        return map;
    }

    branchOfCode() {
        if (this.branchOfCodeCache) return this.branchOfCodeCache;
        const map = new Map();
        this.codesByBranch().forEach((codes, branch) => codes.forEach((code) => map.set(code, branch)));
        this.branchOfCodeCache = map;
        return map;
    }

    applyState(force = false) {
        if (!this.scene || !this.stateKey) return;
        if (!force && this.appliedState === this.stateKey && this.appliedData === this.data) return;
        const { targets, glass, dark } = this.targetsFor(this.stateKey);
        this.scene.setTargets(targets, { glass, dark });
        this.appliedState = this.stateKey;
        this.appliedData = this.data;
        this.updateLabels();
    }

    updateLabels() {
        const items = this.data?.states?.[this.stateKey]?.labels || [];
        const keep = new Set(items.map((item) => item.branch));
        this.labels.forEach((element, branch) => {
            if (!keep.has(branch)) element.style.opacity = '0';
        });
        items.forEach((item) => {
            let element = this.labels.get(item.branch);
            if (!element) {
                element = document.createElement('div');
                element.className = 'kn-relief__label';
                this.labelLayer.append(element);
                this.labels.set(item.branch, element);
            }
            element.innerHTML = `<small>${escapeText(item.title)}</small><strong>${escapeText(item.value)}</strong>`;
            element._w = 0;
            element._h = 0;
        });
        this.labelItems = items;
    }

    placeLabels(slotRect, visible) {
        if (!this.scene || !this.labelItems) return;
        const codesByBranch = this.codesByBranch();
        const show = visible && this.activeKey !== 'hero' && this.activeKey !== 'close';
        const placed = [];
        this.labelItems.forEach((item) => {
            const element = this.labels.get(item.branch);
            const codes = codesByBranch.get(item.branch);
            if (!element || !codes) return;
            const anchor = this.scene.anchorOf(codes);
            if (!anchor || !show) {
                element.style.opacity = '0';
                return;
            }
            const point = this.scene.screenOf(anchor);
            const inside = point.visible && point.x > slotRect.x - 40 && point.x < slotRect.x + slotRect.w + 40 && point.y > slotRect.y - 20 && point.y < slotRect.y + slotRect.h + 20;
            element._w ||= element.offsetWidth || 120;
            element._h ||= element.offsetHeight || 44;
            placed.push({ element, x: point.x, y: point.y, anchorY: point.y, inside });
        });
        // Labels never cover each other: from the bottom up, a label that would overlap the one below it moves above it.
        placed.sort((a, b) => b.y - a.y);
        placed.forEach((label, index) => {
            for (let j = 0; j < index; j += 1) {
                const other = placed[j];
                const overlapX = Math.abs(label.x - other.x) < (label.element._w + other.element._w) / 2 + 8;
                if (overlapX && label.y > other.y - other.element._h - 10) label.y = other.y - other.element._h - 10;
            }
        });
        const bar = document.querySelector('.pres-top-bar');
        const tabs = bar?.querySelector('.kn-nav__chapters');
        const ceiling = Math.max(bar?.getBoundingClientRect().bottom || 0, tabs && getComputedStyle(tabs).position === 'fixed' ? tabs.getBoundingClientRect().bottom : 0);
        placed.forEach((label) => {
            if (label.y - label.element._h - 14 < ceiling + 6) label.inside = false;
            const lift = Math.max(0, label.anchorY - label.y);
            label.element.style.setProperty('--kn-stem', `${(14 + lift).toFixed(1)}px`);
            label.element.style.transform = `translate3d(${label.x.toFixed(1)}px, ${label.y.toFixed(1)}px, 0)`;
            label.element.style.opacity = label.inside ? '1' : '0';
        });
    }

    tick(time) {
        this.frame = null;
        if (document.hidden) return;
        const still = reduceMotion();
        const dt = this.lastTime === null ? 1 / 60 : Math.min(0.05, Math.max(0, (time - this.lastTime) / 1000));
        this.lastTime = time;
        if (!still && !this.motionPaused) this.time += dt;

        const slot = this.activeSlot();
        const visible = Boolean(slot);
        this.layer.classList.toggle('is-visible', visible && Boolean(this.scene));
        if (!slot) {
            this.labels.forEach((element) => { element.style.opacity = '0'; });
            this.rect = null;
            return;
        }

        const slotKey = slot.dataset.reliefSlot;
        const progress = still ? 0.5 : this.progressOf(slot);
        const stateKey = this.stateFor(slotKey, progress);
        const dark = Boolean(slot.closest('.kn-ground--dark, .kn-hero'));
        this.labelLayer.classList.toggle('is-dark', dark);
        this.layer.classList.toggle('is-dark', dark);

        if (this.activeKey !== slotKey || this.stateKey !== stateKey) {
            const jumped = this.activeKey !== slotKey;
            this.activeKey = slotKey;
            this.stateKey = stateKey;
            this.applyState();
            if (jumped) this.rect = null;
        }
        if (!this.scene) return;

        const bounds = slot.getBoundingClientRect();
        // Narrow stages keep headroom so the branch labels above the relief clear the bar.
        const headroom = slotKey !== 'hero' && slotKey !== 'close' && window.innerWidth <= 960 ? Math.min(72, bounds.height * 0.16) : 0;
        const target = { x: bounds.left, y: bounds.top + headroom, w: bounds.width, h: bounds.height - headroom };
        if (!this.rect || still) this.rect = { ...target };
        else {
            ['x', 'y', 'w', 'h'].forEach((key) => { this.rect[key] = damp(this.rect[key], target[key], 9, dt); });
        }

        const pose = { ...(POSES[SLOT_POSE[slotKey]] || POSES.lead)(still ? (slotKey === 'hero' ? 0 : 0.5) : progress) };
        // Opening: once the loader leaves, the relief turns in from a deeper angle and settles.
        if (slotKey === 'hero' && !still) {
            if (!this.introStart && document.documentElement.classList.contains('kn-ready')) this.introStart = time;
            const intro = this.introStart ? Math.min(1, (time - this.introStart) / 1800) : 0;
            const eased = 1 - ((1 - intro) ** 3);
            pose.yaw -= (1 - eased) * 38;
            pose.pitch -= (1 - eased) * 12;
            pose.fill *= 0.82 + eased * 0.18;
            if (intro < 1) this.kick();
        }
        const idle = still || this.motionPaused ? 0 : Math.sin(this.time * 0.32) * 2.6;
        const settling = this.scene.render({
            dt: still ? 1 : dt,
            still,
            rect: this.rect,
            yaw: pose.yaw + idle,
            pitch: pose.pitch,
            fill: pose.fill,
            light: still ? 0.12 : 0.12 + this.time * 0.018,
        });
        this.placeLabels(this.rect, visible);

        const moving = ['x', 'y', 'w', 'h'].some((key) => Math.abs(this.rect[key] - target[key]) > 0.5);
        if (!still && (!this.motionPaused || settling || moving)) this.kick();
    }
}
