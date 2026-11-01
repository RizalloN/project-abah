/*
 * A damped wheel: the same scroll clock, but every notch of a mouse wheel glides
 * instead of stepping, so all scroll-written depth moves with weight. Keyboard,
 * touch, the scrollbar, anchors and nested scrollers keep their native behaviour.
 */

const LAMBDA = 11;
const REST = 0.4;
/** A browser rounds what it is told; anything beyond this came from someone else. */
const FOREIGN = 3;

export const wheelPixels = ({ deltaY = 0, deltaMode = 0 }, viewport = 800) => {
    if (deltaMode === 1) return deltaY * 16;
    if (deltaMode === 2) return deltaY * viewport;
    return deltaY;
};

export const glide = (current, target, dt) => {
    const next = current + (target - current) * (1 - Math.exp(-LAMBDA * dt));
    return Math.abs(target - next) <= REST ? target : next;
};

/** A nested scroller that can still travel this way owns the wheel, as it would natively. */
export const nestedScrollerTakes = (start, delta, boundary = null) => {
    for (let node = start; node && node !== boundary && node.nodeType === 1; node = node.parentElement) {
        if (node === document.body || node === document.documentElement) return false;
        const room = node.scrollHeight - node.clientHeight;
        if (room <= 1) continue;
        const { overflowY } = getComputedStyle(node);
        if (overflowY !== 'auto' && overflowY !== 'scroll') continue;
        if (delta < 0 ? node.scrollTop > 0 : node.scrollTop < room - 1) return true;
    }
    return false;
};

export class SmoothWheel {
    constructor({ enabled = () => true } = {}) {
        this.enabled = enabled;
        this.frame = null;
        this.target = null;
        this.position = 0;
        this.last = 0;
        this.events = null;
    }

    init() {
        if (this.events) return this;
        this.events = new AbortController();
        const { signal } = this.events;
        window.addEventListener('wheel', (event) => this.onWheel(event), { passive: false, signal });
        const stop = () => this.stop();
        for (const type of ['pointerdown', 'touchstart', 'keydown']) window.addEventListener(type, stop, { passive: true, signal });
        return this;
    }

    limit() {
        return Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
    }

    onWheel(event) {
        if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || !this.enabled()) { this.stop(); return; }
        if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;
        const delta = wheelPixels(event, window.innerHeight);
        if (!delta || nestedScrollerTakes(event.target, delta)) return;
        event.preventDefault();
        if (this.target === null) {
            this.position = window.scrollY;
            this.target = this.position;
            this.last = 0;
            this.written = this.position;
        }
        this.target = Math.max(0, Math.min(this.limit(), this.target + delta));
        this.frame ??= window.requestAnimationFrame((now) => this.step(now));
    }

    step(now) {
        this.frame = null;
        if (this.target === null) return;
        // Anchors, room changes and gates move the page directly: never fight them.
        if (Math.abs(window.scrollY - this.written) > FOREIGN) { this.stop(); return; }
        const dt = this.last ? Math.min(0.05, (now - this.last) / 1000) : 1 / 60;
        this.last = now;
        this.position = glide(this.position, Math.min(this.limit(), this.target), dt);
        this.written = this.position;
        window.scrollTo({ top: this.position, behavior: 'instant' });
        if (this.position === this.target) { this.target = null; return; }
        this.frame = window.requestAnimationFrame((time) => this.step(time));
    }

    stop() {
        if (this.frame !== null) window.cancelAnimationFrame(this.frame);
        this.frame = null;
        this.target = null;
    }

    destroy() {
        this.stop();
        this.events?.abort();
        this.events = null;
    }
}
