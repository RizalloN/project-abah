import { measureReadingBox, readingFrame } from './reading-clock.js';
import { ExpressiveMotion } from './expressive-motion.js';
import { InteractionMotion } from './interaction-motion.js';

/* One scroll clock: material enters once; data stays settled while decoration scrubs. */

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const clamp01 = (value) => Math.min(1, Math.max(0, value));

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
        this.figureAnimations = new Map();
        this.layoutDirty = false;
        this.expressiveImpulse = 0;
        this.expressiveScroll = null;
        this.expressive = new ExpressiveMotion({ settle: (element) => {
            // Hidden rooms can miss an intersection event; settled charts must retain their source geometry.
            if (element.matches('.sg-tile')) this.reveal(element);
            element.dataset.readingSettled = 'true';
            this.set(element, '--sg-read', 1, '_sgRead');
            this.set(element, '--reading-enter', 1, '_readingEnter');
        } });
        this.interaction = new InteractionMotion({ root: document.querySelector('.sg-main'), schedule: () => this.schedule(),
            settle: (element) => this.expressive.takeover(element) });
        this.observer = 'IntersectionObserver' in window
            ? new IntersectionObserver((entries) => entries.forEach((entry) => {
                if (!entry.isIntersecting || !entry.target.isConnected || entry.target.closest('[hidden], [inert], .is-panel-transition, .is-room-transition')) return;
                this.reveal(entry.target);
                this.observer.unobserve(entry.target);
            }), { rootMargin: '0px 0px -8% 0px', threshold: 0.06 })
            : null;
    }

    init() {
        if (!reduceMotion()) document.documentElement.classList.add('sg-motion');
        this.interaction.init();
        window.addEventListener('scroll', () => this.schedule(), { passive: true });
        window.addEventListener('resize', () => this.measure(), { passive: true });
        document.addEventListener('focusin', (event) => {
            const element = event.target.closest?.('[data-sg-motion]');
            const record = element && this.expressive.records.get(element);
            if (record) this.expressive.finish(record);
            this.schedule();
        });
        document.addEventListener('click', (event) => {
            if (!reduceMotion()) this.expressive.interact(event.target, window.innerWidth <= 760);
        });
        document.addEventListener('visibilitychange', () => { if (document.hidden) this.expressive.finishAll(); });
        const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
        preference.addEventListener('change', () => {
            document.documentElement.classList.toggle('sg-motion', !preference.matches);
            this.figureAnimations.forEach((animation) => animation.cancel());
            this.figureAnimations.clear();
            this.expressive.finishAll();
            this.measure();
        });
        if ('ResizeObserver' in window) {
            new ResizeObserver(() => {
                this.layoutDirty = true;
                this.schedule();
            }).observe(document.querySelector('.sg-main') || document.body);
        }
    }

    /** Called after the deck is (re)rendered. */
    scan(root) {
        document.querySelector('[data-sg-deck]')?.classList.add('sg-scroll-luxury', 'sg-reading-motion', 'sg-expressive-motion');
        const objects = {
            '.sg-block': 'section', '.sg-tile': 'surface', '.sg-coverage': 'coverage',
            '.sg-vessels': 'vessels', '.sg-opportunity': 'opportunity', '.sg-skyline': 'skyline',
            '.sg-ledger-wrap': 'ledger', '.sg-slope': 'slope', '.sg-table-wrap': 'table',
            '.sg-ts': 'trend', '.sg-specs': 'metrics', '.sg-donut': 'composition', '.sg-block__head': 'header',
        };
        for (const [selector, kind] of Object.entries(objects)) {
            document.querySelectorAll(`.sg-deck ${selector}`).forEach((element) => { element.dataset.sgMotion = kind; });
        }
        this.readings = [...document.querySelectorAll('.sg-deck [data-sg-motion]')];
        this.readings.forEach((element) => {
            if (element.dataset.readingSettled !== undefined) return;
            element.dataset.readingSettled = 'false';
            element.dataset.readingPhase = 'before';
            this.set(element, '--sg-read', 0, '_sgRead');
            this.set(element, '--reading-enter', 0, '_readingEnter');
            this.set(element, '--reading-pass', 0, '_readingPass');
        });
        this.expressive.scan(this.readings);
        this.interaction.scan();
        root.querySelectorAll('[data-sg-rv]:not(.is-in), .sg-tile:not(.is-in)').forEach((element) => {
            if (this.observer && !reduceMotion()) this.observer.observe(element);
            else this.reveal(element);
        });
        root.querySelectorAll('[data-sg-lines]:not([data-split])').forEach((title) => this.splitLines(title));
        this.tiles = Array.from(document.querySelectorAll('.sg-tile'));
        this.tiles.forEach((tile) => { tile.style.setProperty('--e', '1'); tile.classList.add('is-settled'); });
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

    release(root) {
        this.expressive.release(root);
        this.interaction?.release(root);
        root.querySelectorAll('[data-sg-rv], .sg-tile').forEach((element) => this.observer?.unobserve(element));
        this.figureAnimations.forEach((animation, element) => {
            if (root.contains(element)) { animation.cancel(); this.figureAnimations.delete(element); }
        });
    }

    measure(schedule = true) {
        this.readings.forEach((element) => { element._readingBox = measureReadingBox(element); });
        this.layoutDirty = false;
        if (schedule) this.schedule();
    }

    reveal(element) {
        element.classList.add('is-in');
        element.querySelectorAll('[data-sg-count]').forEach((counter) => this.countUp(counter));
        if (element.matches('[data-sg-count]')) this.countUp(element);
    }

    countUp(element) {
        if (element.dataset.counted) return;
        element.dataset.counted = '1';
        // Reading surfaces always show the exact source text, including during a scroll.
        if (element.closest('.sg-reading-motion')) return;
        const finalText = element.textContent.trim();
        const figure = parseFigure(finalText);
        if (!figure || figure.value === 0 || reduceMotion()) return;
        const started = performance.now();
        const duration = 1200;
        const format = (value) => `${figure.prefix}${value.toLocaleString(figure.locale, { minimumFractionDigits: figure.decimals, maximumFractionDigits: figure.decimals, useGrouping: figure.locale !== 'en-US' })}${figure.suffix}`;
        const step = (now) => {
            if (!element.isConnected) return;
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
        if (element.closest('.sg-reading-motion')) {
            this.figureAnimations.get(element)?.cancel();
            if (reduceMotion() || !element.animate || !element.isConnected) return;
            const animation = element.animate([{ opacity: .65 }, { opacity: 1 }], { duration: 420, easing: 'cubic-bezier(.22,1,.36,1)' });
            this.figureAnimations.set(element, animation);
            animation.finished.catch(() => {}).finally(() => {
                if (this.figureAnimations.get(element) === animation) this.figureAnimations.delete(element);
            });
            return;
        }
        const finalText = element.textContent.trim();
        const from = parseFigure(fromText);
        const to = parseFigure(finalText);
        if (!from || !to || from.prefix !== to.prefix || from.suffix !== to.suffix || from.value === to.value || reduceMotion()) return;
        const started = performance.now();
        const duration = 900;
        const decimals = to.decimals;
        const format = (value) => `${to.prefix}${value.toLocaleString(to.locale, { minimumFractionDigits: decimals, maximumFractionDigits: decimals, useGrouping: to.locale !== 'en-US' })}${to.suffix}`;
        const step = (now) => {
            if (!element.isConnected) return;
            const t = Math.min((now - started) / duration, 1);
            const eased = 1 - ((1 - t) ** 3);
            element.textContent = t >= 1 ? finalText : format(from.value + (to.value - from.value) * eased);
            if (t < 1) window.requestAnimationFrame(step);
        };
        element.textContent = format(from.value);
        window.requestAnimationFrame(step);
    }

    schedule() {
        if (this.frame !== null) return;
        this.frame = window.requestAnimationFrame((now) => {
            this.frame = null;
            if (this.layoutDirty) this.measure(false);
            this.read();
            if (this.interaction.update(now)) this.schedule();
        });
    }

    set(element, name, value, key) {
        if (!Number.isFinite(value)) return;
        if (Math.abs((element[key] ?? -1) - value) <= 0.002) return;
        element[key] = value;
        element.style.setProperty(name, value.toFixed(4));
    }

    read() {
        const viewport = window.innerHeight;
        const still = reduceMotion();
        const scrollY = window.scrollY;
        const movement = Math.abs(scrollY - (this.expressiveScroll ?? scrollY));
        this.expressiveScroll = scrollY;
        this.expressiveImpulse = still ? 0 : Math.max(Math.min(1, movement / 260), this.expressiveImpulse * .84);
        const rail = document.querySelector('.sg-story-rail:not([hidden])');
        const roomHead = document.querySelector('.sg-room-head:not([hidden])');
        const ceiling = Math.max(0, this.nav?.getBoundingClientRect().bottom || 0, rail?.getBoundingClientRect().bottom || 0, roomHead?.getBoundingClientRect().bottom || 0);

        this.readings.forEach((element) => {
            if (!element.isConnected || element.closest('[hidden], [inert], .is-panel-transition, .is-room-transition')) {
                element._readingBox = null;
                this.expressive.update(element, { active: false });
                return;
            }
            const box = element._readingBox || (element._readingBox = measureReadingBox(element));
            if (!box) return;
            const top = box.top - scrollY;
            const frame = readingFrame({ top, height: box.height, viewport, ceiling, reduced: still,
                settled: element.dataset.readingSettled === 'true', focused: element.contains(document.activeElement) });
            if (!frame.usable) return;
            element.dataset.readingSettled = String(frame.settled);
            element.dataset.readingPhase = frame.phase;
            this.set(element, '--sg-read', frame.enter, '_sgRead');
            this.set(element, '--reading-enter', frame.enter, '_readingEnter');
            this.set(element, '--reading-pass', frame.pass, '_readingPass');
            this.expressive.update(element, { top, height: box.height, viewport, ceiling, pass: frame.pass,
                impulse: this.expressiveImpulse, reduced: still, compact: window.innerWidth <= 760 });
        });
        if (this.expressiveImpulse > .012) this.schedule();

        const currentPerformance = document.querySelector('.sg-performance[data-room-active="true"][data-polygon-view="detail"]');
        if (currentPerformance) {
            const acts = [...currentPerformance.querySelectorAll('.sg-performance-act')];
            const readingLine = ceiling + Math.max(70, (viewport - ceiling) * .24);
            let currentAct = acts[0]?.id;
            acts.forEach(act => {
                const box = act.querySelector('.sg-block')?._readingBox;
                if (box && box.top - scrollY <= readingLine) currentAct = act.id;
            });
            currentPerformance.querySelectorAll('.sg-performance-path a').forEach(link => {
                const active = link.hash === `#${currentAct}`;
                if (active) link.setAttribute('aria-current', 'location');
                else link.removeAttribute('aria-current');
            });
            const sectionName = document.querySelector('[data-room-section]');
            const currentLink = currentPerformance.querySelector('.sg-performance-path a[aria-current="location"] strong');
            if (sectionName) {
                const label = currentLink?.textContent.trim() || '';
                if (sectionName.textContent !== label) sectionName.textContent = label;
                sectionName.hidden = !label;
            }
        }

        this.openers.forEach((opener) => {
            if (!measureReadingBox(opener)) return;
            const rect = opener.getBoundingClientRect();
            if (rect.bottom < -viewport || rect.top > viewport * 2) return;
            this.set(opener, '--sp', still ? 1 : clamp01(-rect.top / Math.max(1, rect.height - viewport)), '_sp');
            // The title turns up as soon as the opener owns most of the screen, ahead of its figure.
            if (!opener.classList.contains('is-live') && rect.top < viewport * 0.45) opener.classList.add('is-live');
            const sheet = opener.nextElementSibling;
            if (opener.closest('[data-polygon-view]')) {
                this.set(opener, '--cover', 0, '_cover');
                if (sheet) { this.set(sheet, '--rise', 1, '_rise'); sheet.classList.remove('is-rising'); }
                return;
            }
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
            let current = document.querySelector('.sg-room-mode .sg-deck:not([hidden])')?.dataset.roomKey || null;
            if (!document.querySelector('.sg-room-mode')) this.chapters.forEach((chapter) => {
                if (!chapter.hidden && !chapter.inert && chapter.getBoundingClientRect().top <= line) current = chapter.dataset.sgSegment;
            });
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
