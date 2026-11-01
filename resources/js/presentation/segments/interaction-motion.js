const BLOCKED = '[hidden], [inert], .is-panel-transition, .is-room-transition';
const ITEM = '.sg-vessel, .sg-prism, .sg-orb, .sg-rods > li, .sg-twins > li, .sg-legend > li, .sg-specs > div, tbody > tr, .sg-ts__legend button, [data-story-point], [data-story-reset]';
const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

/** A pointer is expressed in the stationary chart frame, never a transformed material. */
export function interactionPoint(rect, clientX, clientY) {
    if (!rect || ![rect.left, rect.top, rect.width, rect.height, clientX, clientY].every(Number.isFinite)
        || rect.width <= 0 || rect.height <= 0) return null;
    const x = clamp((clientX - rect.left) / rect.width, 0, 1);
    const y = clamp((clientY - rect.top) / rect.height, 0, 1);
    return { x: x * 2 - 1, y: y * 2 - 1, lightX: x * 100, lightY: y * 100 };
}

/** One input owner, sharing the reading RAF; CSS moves local material, never source coordinates. */
export class InteractionMotion {
    constructor({ root, schedule, settle = () => {}, fine = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches,
        reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches }) {
        this.root = root;
        this.schedule = schedule;
        this.settle = settle;
        this.fine = fine;
        this.reduced = reduced;
        this.active = null;
        this.pending = null;
        this.controller = null;
        this.observer = null;
        this.previousTime = null;
    }

    init() {
        if (!this.root || this.controller) return;
        this.controller = new AbortController();
        const listen = (node, type, callback, options = {}) => node.addEventListener(type, callback, { ...options, signal: this.controller.signal });
        listen(this.root, 'pointerover', (event) => this.pointer(event), { passive: true });
        listen(this.root, 'pointermove', (event) => this.pointer(event), { passive: true });
        listen(this.root, 'pointerout', (event) => this.leave(event), { passive: true });
        listen(this.root, 'pointerdown', (event) => this.press(event), { passive: true });
        listen(window, 'pointerup', () => this.unpress(), { passive: true });
        listen(window, 'pointercancel', () => this.clear(), { passive: true });
        listen(window, 'blur', () => this.clear());
        listen(window, 'scroll', () => { if (this.active?.mode !== 'focus') this.clear(); }, { passive: true });
        listen(window, 'resize', () => this.clear(), { passive: true });
        listen(this.root, 'click', () => this.clear(), { capture: true });
        listen(this.root, 'focusin', (event) => this.focus(event.target));
        listen(this.root, 'focusout', (event) => {
            if (this.active?.mode === 'focus' && !this.active.element.contains(event.relatedTarget)) this.clear();
        });
        listen(document, 'visibilitychange', () => { if (document.hidden) this.clear(); });
        for (const query of ['(prefers-reduced-motion: reduce)', '(hover: hover) and (pointer: fine)']) {
            listen(window.matchMedia(query), 'change', () => this.clear());
        }
        if ('MutationObserver' in window) {
            this.observer = new MutationObserver(() => {
                if (this.active && (!this.usable(this.active.element) || this.active.item && (!this.active.item.isConnected || !this.active.element.contains(this.active.item)))) this.clear();
            });
            this.observer.observe(this.root, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'inert'] });
        }
        this.scan();
    }

    scan() {
        if (!this.root) return;
        this.root.classList.add('sg-interaction-motion');
        this.root.querySelectorAll('[data-sg-motion]').forEach((element) => { element.dataset.sgInteraction = element.dataset.sgMotion; });
        for (const [selector, kind] of [['.sg-card, .sg-scope', 'choice'], ['.sg-opener__stage', 'map'], ['.sg-story-rail', 'navigation']]) {
            this.root.querySelectorAll(selector).forEach((element) => { element.dataset.sgInteraction = kind; });
        }
        if (this.active && !this.usable(this.active.element)) this.clear();
    }

    usable(element) {
        return Boolean(element?.isConnected && this.root?.contains(element) && !element.closest(BLOCKED));
    }

    target(node) {
        const element = node?.closest?.('[data-sg-interaction]');
        return this.usable(element) ? element : null;
    }

    pointer(event) {
        if (event.pointerType !== 'mouse' || !this.fine() || this.reduced() || document.hidden) { this.clear(); return; }
        const element = this.target(event.target);
        if (!element) { this.clear(); return; }
        this.pending = { element, target: event.target, clientX: event.clientX, clientY: event.clientY };
        this.schedule();
    }

    leave(event) {
        const element = this.pending?.element || this.active?.element;
        if (!element || element.contains(event.relatedTarget)) return;
        this.clear();
        const focused = document.activeElement;
        if (focused && this.root.contains(focused)) this.focus(focused);
    }

    focus(target) {
        if (!target?.matches?.(':focus-visible')) return;
        const element = this.target(target);
        if (!element) return;
        this.pending = null;
        this.activate(element, target, 'focus');
        this.schedule();
    }

    press(event) {
        if (event.button > 0) return;
        const element = this.target(event.target);
        if (!element) return;
        this.pending = null;
        const mode = event.pointerType === 'mouse' && this.fine() ? 'pointer' : 'press';
        this.activate(element, event.target, mode);
        this.active.pressed = true;
        if (mode === 'pointer' && !this.reduced()) this.pending = { element, target: event.target, clientX: event.clientX, clientY: event.clientY };
        this.schedule();
    }

    unpress() {
        if (!this.active) return;
        if (this.active.mode === 'press') { this.clear(); return; }
        this.active.pressed = false;
        this.schedule();
    }

    activate(element, target, mode) {
        const candidate = target?.closest?.(ITEM);
        const item = candidate && element.contains(candidate) ? candidate : null;
        if (this.active?.element !== element || this.active.mode !== mode) {
            this.clear();
            this.settle(element);
            this.active = { element, item: null, mode, x: 0, y: 0, targetX: 0, targetY: 0, engaged: 0, pressed: false };
        }
        if (this.active.item !== item) {
            this.resetItem(this.active.item);
            this.active.item = item;
        }
        if (item) item.dataset.sgHot = 'true';
        element.dataset.sgEngagement = mode;
    }

    update(now = 0) {
        if (document.hidden || this.reduced() && (this.pending || this.active?.mode === 'pointer')) { this.clear(); return false; }
        if (this.pending) {
            const pending = this.pending;
            this.pending = null;
            if (!this.fine() || !this.usable(pending.element)) { this.clear(); return false; }
            const point = interactionPoint(pending.element.getBoundingClientRect(), pending.clientX, pending.clientY);
            if (!point) { this.clear(); return false; }
            this.activate(pending.element, pending.target, 'pointer');
            this.active.targetX = point.x;
            this.active.targetY = point.y;
        }
        const active = this.active;
        if (!active) return false;
        if (!this.usable(active.element) || active.item && (!active.item.isConnected || !active.element.contains(active.item))) { this.clear(); return false; }
        const elapsed = this.previousTime === null ? 16.7 : clamp(now - this.previousTime, 1, 50);
        this.previousTime = now;
        const blend = this.reduced() ? 1 : 1 - Math.exp(-elapsed / 65);
        active.x += (active.targetX - active.x) * blend;
        active.y += (active.targetY - active.y) * blend;
        active.engaged += (1 - active.engaged) * blend;
        for (const node of [active.element, active.item].filter(Boolean)) {
            node.style.setProperty('--sg-pointer-x', active.x.toFixed(4));
            node.style.setProperty('--sg-pointer-y', active.y.toFixed(4));
            node.style.setProperty('--sg-light-x', `${(50 + active.x * 50).toFixed(2)}%`);
            node.style.setProperty('--sg-light-y', `${(50 + active.y * 50).toFixed(2)}%`);
            node.style.setProperty('--sg-engaged', active.engaged.toFixed(4));
            node.style.setProperty('--sg-press', active.pressed ? '1' : '0');
        }
        return Math.max(Math.abs(active.targetX - active.x), Math.abs(active.targetY - active.y), 1 - active.engaged) > .002;
    }

    resetItem(item) {
        if (!item) return;
        delete item.dataset.sgHot;
        for (const name of ['--sg-pointer-x', '--sg-pointer-y', '--sg-light-x', '--sg-light-y', '--sg-engaged', '--sg-press']) item.style.removeProperty(name);
    }

    clear() {
        this.pending = null;
        if (this.active) {
            this.resetItem(this.active.item);
            this.resetItem(this.active.element);
            delete this.active.element.dataset.sgEngagement;
        }
        this.active = null;
        this.previousTime = null;
    }

    release(root) {
        if ((this.active && root.contains(this.active.element)) || (this.pending && root.contains(this.pending.element))) this.clear();
    }

    destroy() {
        this.clear();
        this.controller?.abort();
        this.controller = null;
        this.observer?.disconnect();
        this.observer = null;
    }
}
