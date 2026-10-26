/*
 * Motion for the segmented presentation, all on one scroll clock:
 *   --e      tiles rise out of depth as they reach reading height (0..1)
 *   --sp     an opener's own progress through its pinned track (0..1)
 *   --cover  how far the sheet after an opener has slid over it (0..1)
 *   --rise   that sheet tipping up like a page (0..1)
 * plus reveals that fire once, count-ups, pointer highlights, and the
 * navigation's active segment and reading progress. Reduced motion settles
 * every value at its end state.
 */

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const clamp01 = (value) => Math.min(1, Math.max(0, value));
const tiltFor = (width) => Math.max(1.2, Math.min(7, 2600 / Math.max(1, width)));

/** Document position from the offset chain, which ignores the transforms this module writes. */
const layoutBox = (element) => {
    let top = 0;
    let left = 0;
    for (let node = element; node; node = node.offsetParent) {
        top += node.offsetTop;
        left += node.offsetLeft;
    }
    return { top, left, width: element.offsetWidth, height: element.offsetHeight };
};

/** "Rp14,35 T" -> prefix, value, decimals, suffix, so a number can bloom from zero to its exact text. */
const parseFigure = (text) => {
    const match = String(text).match(/^(\D{0,3}?)([-+]?)(\d{1,3}(?:\.\d{3})*|\d+)(?:,(\d+))?(\D{0,4})$/);
    if (!match) {
        // The RO market-share report prints decimals with a point ("29.3%"); keep its own style while rolling.
        const dotted = String(text).match(/^(\D{0,3}?)([-+]?)(\d+)\.(\d+)(\D{0,4})$/);
        if (!dotted) return null;
        const [, prefix, sign, whole, fraction, suffix] = dotted;
        return { prefix: prefix + sign, value: Number(`${whole}.${fraction}`), decimals: fraction.length, suffix, locale: 'en-US' };
    }
    const [, prefix, sign, whole, fraction = '', suffix] = match;
    const value = Number(`${whole.replaceAll('.', '')}.${fraction || '0'}`);
    return Number.isFinite(value) ? { prefix: prefix + sign, value, decimals: fraction.length, suffix, locale: 'id-ID' } : null;
};

export class SegmentMotion {
    constructor({ nav }) {
        this.nav = nav;
        this.frame = null;
        this.tiles = [];
        this.openers = [];
        this.chapters = [];
        this.readings = [];
        this.observer = 'IntersectionObserver' in window
            ? new IntersectionObserver((entries) => entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                this.reveal(entry.target);
                this.observer.unobserve(entry.target);
            }), { rootMargin: '0px 0px -8% 0px', threshold: 0.06 })
            : null;
    }

    init() {
        if (!reduceMotion()) document.documentElement.classList.add('sg-motion');
        window.addEventListener('scroll', () => this.schedule(), { passive: true });
        window.addEventListener('resize', () => this.measure(), { passive: true });
        if ('ResizeObserver' in window) {
            let timer = null;
            new ResizeObserver(() => {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => this.measure(), 120);
            }).observe(document.querySelector('.sg-main') || document.body);
        }
        if (finePointer()) {
            document.addEventListener('pointermove', (event) => this.light(event), { passive: true });
            document.addEventListener('pointerout', (event) => {
                const surface = event.target.closest?.('.sg-tile');
                if (!surface || surface.contains(event.relatedTarget)) return;
                surface.classList.remove('is-lit');
                surface.style.removeProperty('--tx');
                surface.style.removeProperty('--ty');
            });
        }
    }

    /** Called after the deck is (re)rendered. */
    scan(root) {
        document.querySelector('[data-sg-deck]')?.classList.add('sg-scroll-luxury');
        const objects = {
            '.sg-vessels': 'vessels', '.sg-opportunity': 'opportunity', '.sg-skyline': 'skyline',
            '.sg-ledger-wrap': 'ledger', '.sg-slope': 'slope', '.sg-table-wrap': 'table',
            '.sg-ts': 'trend', '.sg-specs': 'metrics', '.sg-donut': 'composition', '.sg-block__head': 'header',
        };
        for (const [selector, kind] of Object.entries(objects)) {
            document.querySelectorAll(`.sg-deck ${selector}`).forEach((element) => { element.dataset.sgMotion = kind; });
        }
        this.readings = [...document.querySelectorAll('.sg-deck [data-sg-motion]')];
        root.querySelectorAll('[data-sg-rv]:not(.is-in), .sg-tile:not(.is-in)').forEach((element) => {
            if (this.observer && !reduceMotion()) this.observer.observe(element);
            else this.reveal(element);
        });
        root.querySelectorAll('[data-sg-lines]:not([data-split])').forEach((title) => this.splitLines(title));
        this.tiles = Array.from(document.querySelectorAll('.sg-tile'));
        this.openers = Array.from(document.querySelectorAll('[data-sg-opener]'));
        this.chapters = Array.from(document.querySelectorAll('[data-sg-segment]'));
        this.measure();
    }

    splitLines(title) {
        title.dataset.split = '1';
        const text = title.textContent.trim().replace(/\s+/g, ' ');
        title.setAttribute('aria-label', text);
        title.innerHTML = text.split(' ').map((word, index) => `<span class="sg-word" aria-hidden="true"><span style="--w:${index}">${word.replace(/[&<>"']/g, '')}</span></span>`).join(' ');
    }

    measure() {
        const middle = window.innerWidth / 2;
        this.tiles.forEach((tile) => {
            const box = layoutBox(tile);
            tile._box = box;
            const offset = (box.left + box.width / 2 - middle) / Math.max(1, window.innerWidth);
            tile.style.setProperty('--side', Math.abs(offset) < 0.08 || box.width > window.innerWidth * 0.62 ? '0' : String(Math.sign(offset)));
        });
        this.readings.forEach((element) => { element._readingBox = layoutBox(element); });
        this.schedule();
    }

    reveal(element) {
        element.classList.add('is-in');
        element.querySelectorAll('[data-sg-count]').forEach((counter) => this.countUp(counter));
        if (element.matches('[data-sg-count]')) this.countUp(element);
    }

    countUp(element) {
        if (element.dataset.counted) return;
        element.dataset.counted = '1';
        const finalText = element.textContent.trim();
        const figure = parseFigure(finalText);
        if (!figure || figure.value === 0 || reduceMotion()) return;
        const started = performance.now();
        const duration = 1200;
        const format = (value) => `${figure.prefix}${value.toLocaleString(figure.locale, { minimumFractionDigits: figure.decimals, maximumFractionDigits: figure.decimals, useGrouping: figure.locale !== 'en-US' })}${figure.suffix}`;
        const step = (now) => {
            const t = Math.min((now - started) / duration, 1);
            element.textContent = t >= 1 ? finalText : format(figure.value * (1 - ((1 - t) ** 3)));
            if (t < 1) window.requestAnimationFrame(step);
        };
        element.textContent = format(0);
        window.requestAnimationFrame(step);
    }

    /** A figure that changes with the selection rolls from its previous value instead of jumping. */
    morph(element, fromText) {
        if (!element) return;
        element.dataset.counted = '1';
        const finalText = element.textContent.trim();
        const from = parseFigure(fromText);
        const to = parseFigure(finalText);
        if (!from || !to || from.prefix !== to.prefix || from.suffix !== to.suffix || from.value === to.value || reduceMotion()) return;
        const started = performance.now();
        const duration = 900;
        const decimals = to.decimals;
        const format = (value) => `${to.prefix}${value.toLocaleString(to.locale, { minimumFractionDigits: decimals, maximumFractionDigits: decimals, useGrouping: to.locale !== 'en-US' })}${to.suffix}`;
        const step = (now) => {
            const t = Math.min((now - started) / duration, 1);
            const eased = 1 - ((1 - t) ** 3);
            element.textContent = t >= 1 ? finalText : format(from.value + (to.value - from.value) * eased);
            if (t < 1) window.requestAnimationFrame(step);
        };
        element.textContent = format(from.value);
        window.requestAnimationFrame(step);
    }

    light(event) {
        const surface = event.target.closest?.('.sg-tile');
        if (!surface) return;
        const rect = surface.getBoundingClientRect();
        const x = (event.clientX - rect.left) / Math.max(1, rect.width);
        const y = (event.clientY - rect.top) / Math.max(1, rect.height);
        surface.style.setProperty('--mx', x.toFixed(3));
        surface.style.setProperty('--my', y.toFixed(3));
        surface.style.removeProperty('--tx');
        surface.style.removeProperty('--ty');
        surface.classList.add('is-lit');
    }

    schedule() {
        if (this.frame !== null) return;
        this.frame = window.requestAnimationFrame(() => {
            this.frame = null;
            this.read();
        });
    }

    set(element, name, value, key) {
        if (Math.abs((element[key] ?? -1) - value) <= 0.002) return;
        element[key] = value;
        element.style.setProperty(name, value.toFixed(4));
    }

    read() {
        const viewport = window.innerHeight;
        const still = reduceMotion();
        const scrollY = window.scrollY;

        this.readings.forEach((element) => {
            const box = element._readingBox;
            if (!box?.height || !element.isConnected) return;
            const top = box.top - scrollY;
            if (!still && (top > viewport * 1.2 || top + box.height < -80)) return;
            // The object arrives while entering, then holds its true proportions throughout reading.
            const progress = still ? 1 : clamp01((viewport * 1.02 - top) / (viewport * 0.64));
            this.set(element, '--sg-read', progress, '_sgRead');
        });

        this.tiles.forEach((tile) => {
            const box = tile._box || layoutBox(tile);
            const top = box.top - scrollY;
            if (top > viewport + 40 || top + box.height < -40) return;
            const entry = still ? 1 : clamp01((viewport - top) / (viewport * 0.62));
            this.set(tile, '--e', entry, '_e');
            tile.classList.toggle('is-settled', entry >= 1);
        });

        this.openers.forEach((opener) => {
            const rect = opener.getBoundingClientRect();
            if (rect.bottom < -viewport || rect.top > viewport * 2) return;
            this.set(opener, '--sp', still ? 1 : clamp01(-rect.top / Math.max(1, rect.height - viewport)), '_sp');
            // The title turns up as soon as the opener owns most of the screen, ahead of its figure.
            if (!opener.classList.contains('is-live') && rect.top < viewport * 0.45) opener.classList.add('is-live');
            const sheet = opener.nextElementSibling;
            if (!sheet?.classList.contains('sg-sheet') || still) return;
            if (sheet.hidden) {
                this.set(opener, '--cover', 0, '_cover');
                sheet.classList.remove('is-rising');
                return;
            }
            const top = sheet.getBoundingClientRect().top;
            this.set(opener, '--cover', clamp01((viewport - top) / viewport), '_cover');
            const rise = clamp01((viewport - top) / (viewport * 0.85));
            this.set(sheet, '--rise', rise, '_rise');
            sheet.classList.toggle('is-rising', rise > 0 && rise < 1);
        });

        // Navigation: reading progress, the segment under the reading line, and a dark bar over dark grounds.
        if (this.nav) {
            const total = Math.max(1, document.documentElement.scrollHeight - viewport);
            this.nav.querySelector('[data-sg-progress]')?.style.setProperty('--kn-read', clamp01(scrollY / total).toFixed(4));
            const line = viewport * 0.38;
            let current = null;
            this.chapters.forEach((chapter) => { if (chapter.getBoundingClientRect().top <= line) current = chapter.dataset.sgSegment; });
            if (current !== this.current) {
                this.current = current;
                this.nav.querySelectorAll('[data-sg-nav-link]').forEach((link) => {
                    const active = link.dataset.sgNavLink === current;
                    link.classList.toggle('is-active', active);
                    if (active) link.setAttribute('aria-current', 'step');
                    else link.removeAttribute('aria-current');
                });
            }
            const probe = document.elementFromPoint(window.innerWidth / 2, this.nav.getBoundingClientRect().bottom + 2);
            this.nav.classList.toggle('is-on-dark', Boolean(probe?.closest('.sg-ground--dark, .sg-launcher')));
        }
    }
}
