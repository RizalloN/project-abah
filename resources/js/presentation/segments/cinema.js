/*
 * The finishing film around the deck. One camera from the first click to the last
 * screen: the flight from Earth into Area 6, a directional seam between rooms, a
 * staged arrival of each room's copy, scroll-driven atmosphere behind the report
 * and a closing frame. Everything here is decoration: it never owns a figure, a
 * row or a control, and each gesture has one cancellable owner.
 */

import { PanelTransition } from './panel-transition.js';
import { SmoothWheel } from './smooth-scroll.js';
import { h } from './format.js';

const EASE = 'cubic-bezier(.16,1,.3,1)';
const MAP_COPY = '.sg-kicker, .sg-opener__title, .sg-figure, .sg-figure__label, .sg-deltas, .sg-opener__lede, .sg-polygon-hud, .sg-opener__note';
const FINALE_COPY = '.sg-close__title, .sg-close__list, .sg-close__actions';
const DETAIL_COPY = '.sg-detail-head, .sg-performance-path:not(.sg-act-rail), .sg-specs, .sg-block';
const STAGGER_LIMIT = 8;

/* Flight timeline, in the progress units of territoryFlight (Earth is gone by .43). */
export const FLIGHT = { hold: 0.06, handoff: 0.62, end: 0.8, landing: 3400 };

export const easeInOut = (t) => {
    const value = Math.max(0, Math.min(1, t));
    return value * value * (3 - 2 * value);
};

/** Holds high above Earth until the data is ready, then descends on a fixed clock. */
export const flightProgress = (elapsed, landedAt = null) => {
    if (landedAt === null) return Math.min(FLIGHT.hold, FLIGHT.hold * (elapsed / 900));
    return FLIGHT.hold + (FLIGHT.end - FLIGHT.hold) * easeInOut((elapsed - landedAt) / FLIGHT.landing);
};

/** How much of the agenda has already been passed by the descending camera. */
export const litAgendaCount = (progress, total) => {
    if (total <= 0) return 0;
    const share = Math.max(0, Math.min(1, (progress - FLIGHT.hold) / (FLIGHT.handoff - FLIGHT.hold)));
    return Math.min(total, Math.ceil(share * total));
};

/**
 * still: no motion wanted. lite: phones and small machines keep the seams but not
 * the planet or the wheel glide. full: everything.
 */
export const cinemaTier = ({ reduced = false, width = 1440, coarse = false, memory = 0, cores = 0, saveData = false } = {}) => {
    if (reduced) return 'still';
    if (width <= 760 || coarse || saveData || (memory && memory <= 4) || (cores && cores <= 4)) return 'lite';
    return 'full';
};

export const seamDirection = (keys, from, to) => {
    const start = keys.indexOf(from);
    const end = keys.indexOf(to);
    return start >= 0 && end >= 0 && end < start ? -1 : 1;
};

const environment = () => ({
    reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    width: window.innerWidth,
    coarse: window.matchMedia('(pointer: coarse)').matches,
    memory: navigator.deviceMemory || 0,
    cores: navigator.hardwareConcurrency || 0,
    saveData: Boolean(navigator.connection?.saveData),
});

export class PresentationCinema {
    constructor({ studio, labels = {} }) {
        Object.assign(this, { studio, labels });
        this.flown = false;
        this.flight = null;
        this.arrival = null;
        this.seamOwner = null;
        this.wheel = new SmoothWheel({ enabled: () => this.tier() === 'full' && !this.flight && !document.documentElement.classList.contains('sg-room-moving') });
        this.scrollFrame = null;
    }

    tier() { return cinemaTier(environment()); }

    init() {
        const syncTier = () => { document.documentElement.dataset.sgTier = this.tier(); };
        syncTier();
        window.addEventListener('resize', syncTier, { passive: true });
        window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', () => {
            syncTier();
            this.settleAll();
        });
        document.addEventListener('visibilitychange', () => { if (document.hidden) this.settleAll(); });
        this.wheel.init();
        window.addEventListener('scroll', () => this.scheduleRoom(), { passive: true });
        window.addEventListener('resize', () => this.scheduleRoom(), { passive: true });
        return this;
    }

    /* ------------------------------------------------------------- the flight -- */

    /** Starts the descent only once per page and only where a planet is affordable. */
    canFly() {
        return !this.flown && this.tier() === 'full' && !document.hidden && this.studio.canFly();
    }

    beginFlight({ scopeLabel, periodLabel, agenda, onUncover = null }) {
        if (!this.canFly()) return false;
        this.flown = true;
        const stage = document.createElement('section');
        stage.className = 'sg-flight';
        stage.setAttribute('role', 'status');
        stage.setAttribute('data-territory-ignore', '');
        stage.setAttribute('aria-label', `Membuka presentasi ${scopeLabel}`);
        stage.innerHTML = `<div class="sg-flight__stars" aria-hidden="true"></div>
            <div class="sg-flight__slot" data-territory="flight" data-territory-pose="map" aria-hidden="true"></div>
            <div class="sg-flight__copy">
                <p class="sg-flight__kicker">Ruang presentasi</p>
                <h2 class="sg-flight__title">${h(scopeLabel)}</h2>
                <p class="sg-flight__period">Posisi ${h(periodLabel)}</p>
                <ol class="sg-flight__agenda">${agenda.map((label) => `<li>${h(label)}</li>`).join('')}</ol>
            </div>
            <button type="button" class="sg-flight__skip">Lewati</button>`;
        document.body.append(stage);
        const flight = this.flight = { stage, startedAt: performance.now(), landedAt: null, phase: 'holding', skipped: false,
            items: [...stage.querySelectorAll('.sg-flight__agenda li')], frame: null, slow: 0, previous: null,
            events: new AbortController(), resolve: null, done: null, onUncover };
        flight.done = new Promise((resolve) => { flight.resolve = resolve; });
        document.documentElement.classList.add('sg-flight-on');
        const { signal } = flight.events;
        const skip = () => this.skipFlight();
        stage.querySelector('.sg-flight__skip').addEventListener('click', skip, { signal });
        window.addEventListener('wheel', skip, { passive: true, signal });
        window.addEventListener('touchstart', skip, { passive: true, signal });
        window.addEventListener('keydown', (event) => { if (['Escape', ' ', 'Enter'].includes(event.key)) skip(); }, { signal });
        this.studio.beginFlight();
        flight.frame = window.requestAnimationFrame((now) => this.flightTick(now));
        return true;
    }

    /** Called once the deck exists beneath the stage. Resolves when the first room is uncovered. */
    land() {
        const flight = this.flight;
        if (!flight) return Promise.resolve();
        flight.readyToLand = true;
        if (flight.skipped) this.finishFlight(false);
        return flight.done;
    }

    skipFlight() {
        const flight = this.flight;
        if (!flight || flight.skipped) return;
        flight.skipped = true;
        // Without a deck yet, the title holds over the stars until there is a room to show.
        this.studio.endFlight();
        flight.stage.classList.add('is-skipped');
        if (flight.readyToLand) this.finishFlight(false);
    }

    flightTick(now) {
        const flight = this.flight;
        if (!flight || flight.skipped) return;
        const elapsed = now - flight.startedAt;
        // A machine that cannot hold the planet goes straight to the room.
        if (flight.previous !== null && now - flight.previous > 70) flight.slow += 1;
        flight.previous = now;
        if (flight.slow > 18) { this.skipFlight(); return; }
        if (flight.readyToLand && flight.landedAt === null && (this.studio.flightReady() || elapsed > 2200)) flight.landedAt = elapsed;
        const progress = flightProgress(elapsed, flight.landedAt);
        this.studio.setFlightProgress(progress);
        const lit = litAgendaCount(progress, flight.items.length);
        flight.items.forEach((item, index) => item.classList.toggle('is-lit', index < lit));
        if (progress >= FLIGHT.handoff && flight.phase !== 'handoff') {
            flight.phase = 'handoff';
            flight.stage.classList.add('is-leaving');
            this.studio.handOffFlight(flight.stage.querySelector('[data-territory]'));
            this.uncover(flight);
        }
        if (progress >= FLIGHT.end) { this.finishFlight(true); return; }
        flight.frame = window.requestAnimationFrame((time) => this.flightTick(time));
    }

    async finishFlight(glided) {
        const flight = this.flight;
        if (!flight) return;
        this.flight = null;
        window.cancelAnimationFrame(flight.frame);
        flight.events.abort();
        this.studio.endFlight();
        flight.stage.querySelector('[data-territory]')?.remove();
        this.studio.scan();
        flight.stage.classList.add('is-leaving');
        this.uncover(flight);
        // The glide owns its own settling; the stage only has to finish fading.
        await new Promise((resolve) => setTimeout(resolve, glided ? 420 : 320));
        flight.stage.remove();
        document.documentElement.classList.remove('sg-flight-on');
        flight.resolve();
    }

    /** The room's copy enters as the stage starts to lift, never after it has already been seen. */
    uncover(flight) {
        const callback = flight.onUncover;
        flight.onUncover = null;
        callback?.();
    }

    /** A failed build returns to the launcher without leaving a stage over it. */
    abortFlight() {
        const flight = this.flight;
        if (!flight) return;
        this.flight = null;
        window.cancelAnimationFrame(flight.frame);
        flight.events.abort();
        this.studio.endFlight();
        flight.stage.remove();
        this.studio.scan();
        document.documentElement.classList.remove('sg-flight-on');
        flight.resolve();
    }

    /* -------------------------------------------------------------- the seams -- */

    /** The camera swings the way the reader travels; a band of light carries the cut. */
    seam(direction, { camera = true } = {}) {
        if (this.tier() === 'still') return;
        if (camera) this.studio.seam(direction);
        this.seamOwner?.cancel();
        const band = this.seamBand ||= (() => {
            const node = document.createElement('div');
            node.className = 'sg-seam';
            node.setAttribute('aria-hidden', 'true');
            node.innerHTML = '<i></i>';
            document.querySelector('.sg-main')?.append(node);
            return node;
        })();
        const owner = this.seamOwner = new PanelTransition();
        band.classList.toggle('is-dark', Boolean(document.querySelector('[data-sg-segment][data-room-active="true"].sg-ground--dark')));
        band.classList.add('is-on');
        owner.cleanup(() => band.classList.remove('is-on'));
        owner.animate(band.firstElementChild, [
            { transform: `translate3d(${direction * 120}%,0,0) skewX(${direction * -12}deg)` },
            { transform: `translate3d(${direction * -120}%,0,0) skewX(${direction * -12}deg)` },
        ], { duration: 780, easing: 'cubic-bezier(.65,0,.2,1)', fill: 'both' }).then((active) => {
            if (active && this.seamOwner === owner) { owner.cancel(); this.seamOwner = null; }
        });
    }

    /** Staged entrance of a room's own copy: whole nodes travel, their text stays exact. */
    arrive(chapter, view, direction = 1) {
        this.cancelArrival();
        if (!chapter || this.tier() === 'still' || !chapter.animate) return;
        const selector = view === 'finale' ? FINALE_COPY : view === 'detail' ? DETAIL_COPY : MAP_COPY;
        const host = view === 'detail' ? chapter.querySelector('.sg-sheet') : chapter.querySelector('.sg-opener__copy') || chapter;
        if (!host || host.hidden) return;
        const nodes = [...host.querySelectorAll(selector)].filter((node) => node.getClientRects().length).slice(0, STAGGER_LIMIT);
        const owner = this.arrival = new PanelTransition();
        nodes.forEach((node, index) => {
            owner.animate(node, [
                { opacity: 0, transform: `translate3d(${direction * 34}px, 18px, 0)` },
                { opacity: 1, transform: 'translate3d(0, 0, 0)' },
            ], { duration: 720, delay: 60 + index * 58, easing: EASE, fill: 'backwards' });
        });
        owner.wait(60 + nodes.length * 58 + 760).then((active) => {
            if (active && this.arrival === owner) this.cancelArrival();
        });
    }

    cancelArrival() {
        this.arrival?.cancel();
        this.arrival = null;
    }

    settleAll() {
        this.cancelArrival();
        this.seamOwner?.cancel();
        this.seamOwner = null;
        if (this.flight) this.skipFlight();
    }

    /* ------------------------------------------------------- the report's depth -- */

    /** Purely decorative layers behind a report; rescanning after a re-render is idempotent. */
    scan(root) {
        const sheets = root.matches?.('.sg-sheet') ? [root] : [...root.querySelectorAll('.sg-sheet')];
        sheets.forEach((sheet) => {
            if (!sheet.querySelector(':scope > .sg-atmos-track')) {
                const track = document.createElement('div');
                track.className = 'sg-atmos-track';
                track.setAttribute('aria-hidden', 'true');
                track.innerHTML = '<div class="sg-atmos"><i></i><i></i><i></i></div>';
                sheet.prepend(track);
            }
            sheet.querySelectorAll('.sg-block__head').forEach((head) => {
                const title = head.querySelector('.sg-block__title')?.textContent.trim();
                if (!title || head.querySelector(':scope > .sg-act-ghost')) return;
                const ghost = document.createElement('span');
                ghost.className = 'sg-act-ghost';
                ghost.setAttribute('aria-hidden', 'true');
                ghost.innerHTML = `<b>${h(title)}</b>`;
                head.prepend(ghost);
            });
            const path = sheet.querySelector(':scope .sg-performance-path:not(.sg-act-rail)');
            if (path && !sheet.querySelector('.sg-act-rail')) {
                const rail = path.cloneNode(true);
                rail.classList.add('sg-act-rail');
                rail.setAttribute('aria-label', 'Lompat ke bagian');
                rail.querySelector('p')?.remove();
                // Only numerals show here, so each jump keeps its full name for readers and tooltips.
                rail.querySelectorAll('a').forEach((link) => {
                    const label = link.querySelector('strong')?.textContent.trim();
                    if (label) { link.setAttribute('aria-label', label); link.title = label; }
                });
                path.after(rail);
            }
        });
        this.scheduleRoom();
    }

    scheduleRoom() {
        if (this.scrollFrame !== null) return;
        this.scrollFrame = window.requestAnimationFrame(() => {
            this.scrollFrame = null;
            this.paintRoom();
        });
    }

    paintRoom() {
        const chapter = document.querySelector('[data-sg-segment][data-room-active="true"]');
        if (!chapter) return;
        const limit = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
        const progress = Math.max(0, Math.min(1, window.scrollY / limit));
        chapter.style.setProperty('--room-p', progress.toFixed(4));
        chapter.classList.toggle('is-past-path', window.scrollY > window.innerHeight * 0.55);
    }
}
