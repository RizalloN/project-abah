import { PanelTransition } from './panel-transition.js';

const EASE = 'cubic-bezier(.22,1,.36,1)';
const PLANE = ':scope > .sg-expressive-plane';
const clamp = (value) => Math.max(0, Math.min(1, Number.isFinite(value) ? value : 0));
const frames = (...transforms) => transforms.map((transform) => ({ transform }));

/** Extra Kinerja choreography is confined to material; geometry, labels and hit targets stay fixed. */
export function performanceSequence(kind, theme, compact = false) {
    if (!['simpanan', 'pinjaman', 'kualitas', 'recovery'].includes(theme)) return [];
    const depth = compact ? 65 : 150;
    if (kind === 'header') return [
        { selector: '.sg-performance-sculpture > i', keyframes: (node, index) => frames(
            `perspective(900px) translate3d(${(index - 1) * 22}px,25px,-${depth}px) rotateX(72deg) rotateY(-25deg) scale(.45)`,
            `perspective(900px) translate3d(0,0,${12 + index * 8}px) rotateX(18deg) rotateY(0deg) scale(1)`), duration: 1500, stagger: 95, finishAtBase: true },
        { selector: '.sg-performance-signature > i', keyframes: frames('scaleX(.02)', 'scaleX(1)'), duration: 1250, stagger: 110, finishAtBase: true },
    ];
    if (kind === 'table') return [
        { selector: '.sg-meter > i', keyframes: frames('scaleX(.02)', 'scaleX(1)'), duration: 1400, stagger: 35, finishAtBase: true },
    ];
    if (kind === 'trend') return [
        { selector: '.sg-ts__area', keyframes: [{ opacity: .04 }, { opacity: .45, offset: .55 }, { opacity: 1 }], duration: 1700 },
    ];
    if (kind === 'composition') return [
        { selector: '.sg-donut__layer', keyframes: (node, index) => frames(
            `translate3d(${theme === 'pinjaman' ? (index + 1) * -6 : 0}px,0,-${depth + index * 10}px) rotateZ(${theme === 'kualitas' ? 0 : -20}deg)`,
            `translateZ(-${(index + 1) * 2.4}px) rotateZ(0deg)`), duration: 1550, stagger: 28, finishAtBase: true },
    ];
    return [];
}

/** Visual material only: source labels, axes and hit-target coordinates never enter this plan. */
export function expressiveSequence(kind, compact = false) {
    const depth = compact ? 80 : 180;
    const base = [
        { selector: `${PLANE} > .sg-expressive-plane__floor`, keyframes: frames(`perspective(1100px) translate3d(0,38px,-${depth}px) rotateX(42deg) scale(.8)`, 'perspective(1100px) translate3d(0,0,0) rotateX(0deg) scale(1)'), duration: 1300, finishAtBase: true },
        { selector: `${PLANE} > .sg-expressive-plane__light`, keyframes: [{ transform: 'translate3d(-130%,0,0) rotate(-18deg)', opacity: 0 }, { transform: 'translate3d(-20%,0,0) rotate(-18deg)', opacity: .8, offset: .4 }, { transform: 'translate3d(180%,0,0) rotate(-18deg)', opacity: 0 }], duration: 1500 },
    ];
    const spin = compact ? 18 : 34;
    const fill = { keyframes: frames('scaleY(.02)', 'scaleY(.68)', 'scaleY(1)'), duration: 1100, delay: 220, stagger: 45 };
    const glint = { selector: '.sg-material-glint', keyframes: [
        { transform: 'translate3d(-170%,0,0)', opacity: 0 },
        { transform: 'translate3d(20%,0,0)', opacity: .48, offset: .45 },
        { transform: 'translate3d(250%,0,0)', opacity: 0 },
    ], duration: 1150, delay: 320, stagger: 45 };
    if (kind === 'metrics') return base.map((spec) => ({ ...spec, selector: spec.selector.replace(PLANE, ':scope > div > .sg-expressive-plane'), stagger: 65 }));
    if (kind === 'vessels') return [...base,
        { selector: '.sg-vessel__body', keyframes: frames(`perspective(1100px) translateZ(-${depth}px) rotateX(${spin}deg) rotateY(-${spin}deg) scale(.54)`, 'perspective(1100px) translateZ(12px) rotateX(-3deg) rotateY(4deg) scale(.98)', 'perspective(1100px) translateZ(0) rotateX(0deg) rotateY(0deg) scale(1)'), duration: 1250, stagger: 55 },
        { selector: '.sg-vessel__liquid', ...fill },
        glint,
    ];
    if (kind === 'skyline') return [...base,
        { selector: '.sg-prism__solid', keyframes: frames(`perspective(1200px) translateZ(-${depth}px) rotateX(${spin + 18}deg) rotateY(-${spin}deg) scaleY(.24)`, 'perspective(1200px) translateZ(8px) rotateX(-3deg) rotateY(3deg) scaleY(.98)', 'perspective(1200px) translateZ(0) rotateX(0deg) rotateY(0deg) scaleY(1)'), duration: 1300, stagger: 38 },
        { selector: '.sg-prism__top', keyframes: frames('skewX(-45deg) rotateX(-85deg)', 'skewX(-45deg) rotateX(0deg)'), duration: 950, delay: 130, stagger: 38 },
        { selector: '.sg-prism__side', keyframes: frames('skewY(-45deg) rotateY(85deg)', 'skewY(-45deg) rotateY(0deg)'), duration: 950, delay: 180, stagger: 38 },
        { selector: '.sg-prism__front b, .sg-prism__side b', ...fill, stagger: 18 },
        glint,
    ];
    if (kind === 'composition') return [...base,
        { selector: '.sg-donut__disc', keyframes: frames(`perspective(1300px) translateZ(-${depth}px) rotateX(82deg) rotateZ(-78deg) scale(.6)`, 'perspective(1300px) translateZ(10px) rotateX(50deg) rotateZ(-14deg) scale(.98)', 'perspective(1300px) translateZ(0) rotateX(54deg) rotateZ(-18deg) scale(1)'), duration: 1500, finishAtBase: true },
        { selector: '.sg-donut__layer', keyframes: (node, index) => frames(`translateZ(-${(index + 1) * (compact ? 5 : 8)}px) rotateZ(${(index + 1) * -4}deg)`, `translateZ(-${(index + 1) * 2.4}px) rotateZ(0deg)`), duration: 1200, stagger: 35 },
        { selector: '.sg-donut__sheen', keyframes: [{ opacity: .1 }, { opacity: .95, offset: .58 }, { opacity: .65 }], duration: 1300, delay: 180 },
    ];
    if (kind === 'opportunity') return [...base,
        { selector: '.sg-orb[data-motion-bubble] > span', keyframes: frames('scale(.22)', 'scale(.94)', 'scale(1)'), duration: 1150, stagger: 18 },
    ];
    if (kind === 'coverage') return [...base,
        { selector: '.sg-coverage__slab', keyframes: frames('perspective(1400px) rotateX(75deg) scale(.72)', 'perspective(1400px) rotateX(26deg) scale(1)'), duration: 1300 },
        { selector: '.sg-coverage__fill', keyframes: frames('scaleX(.02)', 'scaleX(1)'), duration: 1200, delay: 200 },
    ];
    if (kind === 'trend' || kind === 'slope') return [...base,
        { selector: kind === 'trend' ? '.sg-ts__line' : '.sg-slope__line', keyframes: [{ strokeDashoffset: '1', opacity: .7 }, { strokeDashoffset: '0', opacity: 1 }], duration: 1450, stagger: 65 },
        { selector: kind === 'trend' ? '.sg-ts__area' : '.sg-slope__dot.is-now', keyframes: [{ opacity: .2 }, { opacity: 1 }], duration: 1000, delay: 350 },
    ];
    if (kind === 'surface') return [...base,
        { selector: '.sg-rods__track > i, .sg-twins__bars > i', keyframes: frames('scaleX(.02)', 'scaleX(1)'), duration: 1250, stagger: 50 },
    ];
    return base;
}

/** Visible, bounded assemblies own their handles independently from the panel/camera owner. */
export class ExpressiveMotion {
    constructor({ settle }) {
        this.settle = settle;
        this.records = new Map();
    }

    scan(elements) {
        const current = new Set(elements);
        this.records.forEach((record, element) => {
            if (!element.isConnected || !current.has(element)) { this.finish(record); this.records.delete(element); }
        });
        elements.forEach((element) => {
            if (this.records.has(element)) return;
            const record = { element, kind: element.dataset.sgMotion, played: element.dataset.expressivePlayed === 'true', task: null, nodes: new Set() };
            this.records.set(element, record);
            element.dataset.expressiveState = record.played ? 'settled' : 'waiting';
            const hosts = record.kind === 'metrics' ? [...element.querySelectorAll(':scope > div')] : [element];
            hosts.forEach((host) => {
                if (host.querySelector(':scope > .sg-expressive-plane')) return;
                const plane = document.createElement('span');
                plane.className = 'sg-expressive-plane';
                plane.setAttribute('aria-hidden', 'true');
                plane.inert = true;
                plane.innerHTML = '<i class="sg-expressive-plane__floor"></i><i class="sg-expressive-plane__light"></i>';
                host.prepend(plane);
            });
            // A reflection lives inside the material's clipping box, never across labels or readouts.
            const materialSelector = record.kind === 'vessels' ? '.sg-vessel__tank' : record.kind === 'skyline' ? '.sg-prism__front' : null;
            if (materialSelector) element.querySelectorAll(materialSelector).forEach((host) => {
                if (host.querySelector(':scope > .sg-material-glint')) return;
                const glint = document.createElement('span');
                glint.className = 'sg-material-glint';
                glint.setAttribute('aria-hidden', 'true');
                glint.inert = true;
                host.append(glint);
            });
        });
    }

    update(element, { top, height, viewport, ceiling = 0, pass = 0, impulse = 0, reduced = false, active = true, compact = false }) {
        const record = this.records.get(element);
        if (!record) return;
        const visible = active && element.isConnected && !element.closest('[hidden], [inert], .is-panel-transition, .is-room-transition')
            && [top, height, viewport, ceiling].every(Number.isFinite) && height > 0 && viewport > 0
            && top < viewport - 16 && top + height > ceiling + 16;
        if (record.task && (!visible || reduced || [...record.nodes].some((node) => !node.isConnected))) this.finish(record);
        if (!visible) return;
        element.style.setProperty('--expressive-pass', clamp(pass).toFixed(4));
        element.style.setProperty('--expressive-impulse', reduced ? '0' : clamp(impulse).toFixed(4));
        if (reduced) { this.settle(element); return; }
        if (!record.played) this.start(record, compact);
    }

    start(record, compact = false) {
        this.finish(record);
        const { element } = record;
        if (!element.isConnected || element.closest('[hidden], [inert], .is-panel-transition, .is-room-transition')) return;
        record.played = true;
        element.dataset.expressivePlayed = 'true';
        this.settle(element);
        if (!element.animate) { element.dataset.expressiveState = 'settled'; return; }
        const task = record.task = new PanelTransition();
        element.classList.add('is-expressive-entering');
        element.dataset.expressiveState = 'assembling';
        const promises = [];
        let horizon = 0;
        const theme = element.closest('.sg-performance')?.dataset.performanceTheme;
        const performance = performanceSequence(record.kind, theme, compact);
        const replacedSelectors = new Set(performance.map(spec => spec.selector));
        const sequence = [...expressiveSequence(record.kind, compact).filter(spec => !replacedSelectors.has(spec.selector)), ...performance];
        for (const spec of sequence) {
            [...element.querySelectorAll(spec.selector)].slice(0, 48).forEach((node, index) => {
                const delay = (spec.delay || 0) + Math.min(300, index * (spec.stagger || 0));
                const options = { duration: spec.duration, delay, easing: EASE, fill: 'both' };
                record.nodes.add(node);
                horizon = Math.max(horizon, delay + spec.duration);
                const keyframes = (typeof spec.keyframes === 'function' ? spec.keyframes(node, index) : spec.keyframes).map((frame) => ({ ...frame }));
                if (spec.selector.includes('__light') && element.closest('.sg-ground--dark')) {
                    keyframes.forEach((frame) => { if (typeof frame.opacity === 'number') frame.opacity = Math.min(frame.opacity, .28); });
                }
                if (spec.finishAtBase && typeof getComputedStyle === 'function') keyframes[keyframes.length - 1].transform = getComputedStyle(node).transform;
                promises.push(task.animate(node, keyframes, options));
            });
        }
        task.wait(horizon).then(async (active) => {
            if (!active) return;
            await Promise.all(promises);
            if (record.task === task) this.finish(record);
        });
    }

    finish(record) {
        const task = record.task;
        record.task = null;
        if (task && record.element.isConnected) this.settle(record.element);
        task?.cancel();
        record.nodes.clear();
        record.element.classList.remove('is-expressive-entering');
        if (record.played) record.element.dataset.expressiveState = 'settled';
    }

    finishAll() {
        this.records.forEach((record) => {
            this.finish(record);
            record.element.style.setProperty('--expressive-impulse', '0');
        });
    }

    /** Input owns the settled material even if focus arrives before its first scroll frame. */
    takeover(element) {
        const record = this.records.get(element);
        if (!record) return;
        record.played = true;
        element.dataset.expressivePlayed = 'true';
        this.settle(element);
        this.finish(record);
    }

    release(root) {
        this.records.forEach((record, element) => {
            if (root.contains(element)) { this.finish(record); this.records.delete(element); }
        });
    }

    interact(target, compact = false) {
        if (!target?.closest('.sg-expressive-motion')) return;
        const control = target.closest('.sg-vessel, .sg-prism, .sg-legend li, .sg-ts__legend button, .sg-disclosure > summary, [data-choice-option]');
        const element = control?.closest('[data-sg-motion]');
        const record = element && this.records.get(element);
        if (record) this.start(record, compact);
    }
}
