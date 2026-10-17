/*
 * World director: owns every [data-world] host in the scroll report.
 *
 * Three.js is loaded lazily (its own chunk) the first time a host comes near the
 * viewport. A stage is created when its host is within ~1 viewport, disposed
 * when it leaves that band, and only stages that are actually visible render.
 * That keeps at most two WebGL contexts alive while reading.
 *
 * Progress: a host reads its track, the nearest [data-world-track] or pinned
 * scroll-craft act. Pinned tracks map 0..1 across their extra height, flow
 * tracks across their passage through the viewport. The value is published as
 * --wp on the track so CSS can choreograph copy beats against the same clock.
 */

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const clamp01 = (value) => Math.min(1, Math.max(0, value));

let enginePromise = null;
const loadEngine = () => {
    enginePromise ||= import('./engine.js');
    return enginePromise;
};

const supportsWebGL = () => {
    try {
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('webgl2') || canvas.getContext('webgl');
        context?.getExtension('WEBGL_lose_context')?.loseContext();
        return Boolean(context);
    } catch (_) {
        return false;
    }
};

export class WorldDirector {
    constructor({ root, source }) {
        this.root = root;
        this.source = source;
        this.stages = new Map();
        this.visibility = new WeakMap();
        this.pending = new WeakSet();
        this.frame = null;
        this.enabled = false;
    }

    init() {
        if (!('IntersectionObserver' in window) || !supportsWebGL()) {
            document.documentElement.classList.add('pres-worlds-off');
            return;
        }
        this.enabled = true;
        document.documentElement.classList.add('pres-worlds-on');
        this.near = new IntersectionObserver((entries) => entries.forEach((entry) => {
            if (entry.isIntersecting) this.mount(entry.target);
            else this.unmount(entry.target);
        }), { rootMargin: '110% 0px 110% 0px' });
        this.view = new IntersectionObserver((entries) => entries.forEach((entry) => {
            this.visibility.set(entry.target, entry.isIntersecting);
            if (entry.isIntersecting) this.kick();
        }), { rootMargin: '4% 0px 4% 0px' });
        window.addEventListener('scroll', () => this.kick(), { passive: true });
        window.addEventListener('resize', () => {
            this.stages.forEach((stage) => stage.resize());
            this.kick();
        }, { passive: true });
        document.addEventListener('visibilitychange', () => this.kick());
        this.scan(this.root);
    }

    /** Observe new hosts; called after every section render. */
    scan(scope) {
        if (!this.enabled) return;
        this.prune();
        scope.querySelectorAll('[data-world]').forEach((host) => {
            if (host.dataset.worldObserved) return;
            host.dataset.worldObserved = '1';
            this.near.observe(host);
            this.view.observe(host);
        });
        this.kick();
    }

    /** A rerendered section detaches its old hosts; release their GPU resources. */
    prune() {
        this.stages.forEach((stage, host) => {
            if (host.isConnected) return;
            stage.dispose();
            this.stages.delete(host);
        });
    }

    /** Fresh data from the renderer (scope change, segment change, late payload). */
    refreshData() {
        if (!this.enabled) return;
        const data = this.data();
        this.stages.forEach((stage) => stage.setData(data));
        this.kick();
    }

    data() {
        try {
            return this.source?.() || null;
        } catch (error) {
            console.warn('Presentation worlds: data source failed.', error);
            return null;
        }
    }

    async mount(host) {
        if (this.stages.has(host) || this.pending.has(host)) return;
        this.pending.add(host);
        try {
            const { createStage } = await loadEngine();
            if (!host.isConnected || this.stages.has(host)) return;
            const stage = createStage(host, host.dataset.world, this.data());
            if (!stage) return;
            this.stages.set(host, stage);
            host.classList.add('is-live');
            this.kick();
        } catch (error) {
            host.classList.add('is-fallback');
            console.warn(`Presentation worlds: "${host.dataset.world}" fell back to its poster.`, error);
        } finally {
            this.pending.delete(host);
        }
    }

    unmount(host) {
        const stage = this.stages.get(host);
        if (!stage) return;
        stage.dispose();
        this.stages.delete(host);
        host.classList.remove('is-live');
    }

    kick() {
        if (this.frame !== null || !this.stages.size) return;
        this.frame = window.requestAnimationFrame((time) => this.tick(time));
    }

    tick(time) {
        this.frame = null;
        if (document.hidden) return;
        const still = reduceMotion();
        let again = false;
        this.stages.forEach((stage, host) => {
            const visible = this.visibility.get(host) !== false;
            if (!visible && stage.hasRendered) return;
            const track = this.trackOf(host);
            const progress = still ? 1 : this.progress(track);
            if (track && track._wp !== progress) {
                track._wp = progress;
                track.style.setProperty('--wp', progress.toFixed(4));
            }
            stage.render(progress, time, still);
            if (visible && !still && stage.animated) again = true;
        });
        if (again) this.kick();
    }

    trackOf(host) {
        return host.closest('[data-world-track]') || host.closest('[data-sc-act="pin"]') || host;
    }

    progress(track) {
        const rect = track.getBoundingClientRect();
        const viewport = window.innerHeight || 1;
        const mode = track.dataset.worldTrack || (track.matches('[data-sc-act="pin"]') ? 'pin' : 'flow');
        if (mode === 'pin') return clamp01(-rect.top / Math.max(1, rect.height - viewport));
        if (mode === 'leave') return clamp01(-rect.top / Math.max(1, rect.height));
        return clamp01((viewport - rect.top) / (rect.height + viewport));
    }
}
