/*
 * Segmented presentation: the reader picks segments and a scope on the launcher,
 * and the deck below stays locked until at least one segment is chosen and started.
 * Once started, the choice is written to the URL (?segmen=...&scope=...) so a
 * configured presentation can be shared while still respecting the launcher gate.
 *
 * Data: one call for the Wilayah segments (scope-independent, the server limits
 * branch users), one per scope for the Kinerja segments, and the kecamatan
 * GeoJSON the territory relief is built from.
 */

import '../vendor/scrollcraft.js';
import { TerritoryStudio } from '../worlds/territory-studio.js';
import { SegmentMotion } from './motion.js';
import { bindMapControls } from './map-controls.js';
import { PolygonJourney } from './polygon-journey.js';
import { TerritoryIndex, withVillageTerritory } from './territory-data.js';
import { bindDonuts, bindTimeseries, disposeCharts } from './charts.js';
import { h } from './format.js';
import {
    describeLauncherState,
    isSameChoice,
    normalizeSegments,
    readInitialChoice,
    shouldKeepDeckOpen,
} from './launcher-choice.js';
import * as wilayah from './render-wilayah.js';
import * as kinerja from './render-kinerja.js';

const config = window.__SEGMENT_CONFIG__ || {};
const RENDERERS = {
    marketshare: wilayah.marketshare,
    mapping: wilayah.mapping,
    sektoral: wilayah.sektoral,
    simpanan: kinerja.simpanan,
    pinjaman: kinerja.pinjaman,
    kualitas: kinerja.kualitas,
    recovery: kinerja.recovery,
};
const ORDER = (config.groups || []).flatMap((group) => group.segments.map((segment) => segment.key));
const LABELS = Object.fromEntries((config.groups || []).flatMap((group) => group.segments.map((segment) => [segment.key, segment.label])));
const STORAGE_KEY = 'sg-presentation-choice';
const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const root = document.documentElement;
const launcher = document.getElementById('sg-launcher');
const form = document.querySelector('[data-sg-form]');
const actionsBar = form?.querySelector('[data-sg-actions]') || form?.querySelector('.sg-panel__actions');
const summaryNode = form?.querySelector('[data-sg-summary]');
const startButton = form?.querySelector('.sg-start');
const startIcon = startButton?.querySelector('i');
const deck = document.querySelector('[data-sg-deck]');
const nav = document.querySelector('[data-sg-nav]');
const navChapters = nav?.querySelector('[data-sg-nav-chapters]');
const loader = document.getElementById('sg-loader');

/* --------------------------------------------------------------------- data -- */

const progress = (value, status) => {
    const percent = Math.round(Math.min(100, Math.max(0, value)));
    loader.querySelector('[data-sg-loader-bar]').style.width = `${percent}%`;
    loader.querySelector('[data-sg-loader-percent]').textContent = `${percent}%`;
    if (status) loader.querySelector('[data-sg-loader-status]').textContent = status;
};

const getJson = async (url) => {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!response.ok) throw new Error(`${response.status} ${url}`);
    return response.json();
};

const segmentCache = new Map();
const loadSegments = (scope) => {
    if (!segmentCache.has(scope)) {
        const url = new URL(config.segmentsUrl, window.location.origin);
        url.searchParams.set('scope', scope);
        if (config.period) url.searchParams.set('periode', config.period);
        segmentCache.set(scope, getJson(url).catch((error) => {
            segmentCache.delete(scope);
            console.warn('Segment data failed.', error);
            return null;
        }));
    }
    return segmentCache.get(scope);
};

let wilayahPromise = null;
const loadWilayah = () => {
    wilayahPromise ||= getJson(config.wilayahUrl).then((payload) => ({ ...payload,
        mapping: withVillageTerritory(payload.mapping, payload.sektoral),
    })).catch((error) => {
        wilayahPromise = null;
        console.warn('Wilayah data failed.', error);
        return null;
    });
    return wilayahPromise;
};

/* ------------------------------------------------------------------- choice -- */

const studio = new TerritoryStudio({ geographyUrl: config.geographyUrl });
const motion = new SegmentMotion({ nav });
let index = null;
let built = null;
let building = false;
let deckUnlocked = false;
let lastCoverScope = null;
let nudgeTimer = null;
let touchStartY = null;
let scrollCraftInstance = null;

const launcherLimit = () => {
    if (!launcher) return 0;
    const top = launcher.getBoundingClientRect().top + window.scrollY;
    return Math.max(0, top + launcher.offsetHeight - window.innerHeight);
};

const atLauncherBottom = () => {
    const docBottom = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
    return window.scrollY >= Math.min(launcherLimit(), docBottom) - 8;
};

const clearNudge = () => {
    window.clearTimeout(nudgeTimer);
    actionsBar?.classList.remove('is-nudged');
};

const setStartBusy = (busy) => {
    if (!startIcon) return;
    startIcon.className = busy ? 'fas fa-circle-notch fa-spin' : 'fas fa-arrow-down';
};

const setDeckOpen = (open) => {
    deckUnlocked = Boolean(open);
    if (deck) {
        deck.hidden = !deckUnlocked;
        deck.inert = !deckUnlocked;
        deck.setAttribute('aria-hidden', String(!deckUnlocked));
    }
    root.classList.toggle('sg-deck-open', deckUnlocked);
    root.classList.toggle('sg-launcher-locked', !deckUnlocked);
    if (navChapters) navChapters.hidden = !deckUnlocked;
    nav?.querySelectorAll('[data-sg-open-launcher]').forEach((button) => {
        button.hidden = !deckUnlocked;
    });
    if (!deckUnlocked) {
        nav?.querySelector('[data-sg-progress]')?.style.setProperty('--kn-read', '0');
        studio.activeSlot = null;
        studio.appliedKey = null;
        studio.kick();
    }
};

const enforceLauncherGate = () => {
    if (deckUnlocked) return;
    const limit = launcherLimit();
    if (window.scrollY > limit + 1) {
        window.scrollTo({ top: limit, behavior: 'instant' });
    }
};

const readChoice = () => readInitialChoice({
    search: window.location.search,
    scopes: config.scopes || [],
    defaultScope: config.scope,
    order: ORDER,
});

const writeChoice = (choice) => {
    const url = new URL(window.location.href);
    if (choice.segments.length) url.searchParams.set('segmen', choice.segments.join(','));
    else url.searchParams.delete('segmen');
    url.searchParams.set('scope', choice.scope);
    if (config.period) url.searchParams.set('periode', config.period);
    window.history.replaceState(null, '', url.toString());
    try {
        if (choice.segments.length) window.localStorage.setItem(STORAGE_KEY, JSON.stringify(choice));
        else window.localStorage.removeItem(STORAGE_KEY);
    } catch (_) { /* private mode */ }
};

const formChoice = () => ({
    segments: normalizeSegments(
        Array.from(form.querySelectorAll('input[name="segmen"]:checked')).map((input) => input.value),
        ORDER,
    ),
    scope: form.querySelector('input[name="scope"]:checked')?.value || config.scope,
});

/** The cover and the close are the same neutral relief: floating apart at the cover, landing at the close. */
const registerFrame = (choice) => {
    const neutral = () => ({ lift: 0.16, color: 0xc8cacf, roughness: 0.3 });
    const focus = index?.scopeCodes(choice.scope) || null;
    studio.register('cover', {
        journey: [{ until: 1, focus, heights: neutral }],
        dark: 1,
        fx: { beams: false, flow: 0, speed: 0.8, color: 0x7fa6e8 },
        arrangement: (p, still) => ({ explode: still ? 0 : 1 - Math.min(1, p / 0.8), spread: 0, levitate: 0, levitateBranch: null, grow: 1, wave: 0 }),
    });
    studio.register('close', {
        journey: [{ until: 1, focus, heights: neutral }],
        dark: 1,
        fx: { beams: false, flow: 0, speed: 0.7, color: 0x7fa6e8 },
        arrangement: (p, still) => ({ explode: still ? 0 : 0.4 * (1 - Math.min(1, p / 0.7)), spread: 0, levitate: 0, levitateBranch: null, grow: 1, wave: 0 }),
    });
};

const syncCoverScope = (choice) => {
    const scope = (config.scopes || []).find((item) => item.key === choice.scope) || config.scopes?.[0];
    nav?.querySelectorAll('[data-sg-scope-label]').forEach((node) => { node.textContent = scope?.label || ''; });
    if (index && lastCoverScope !== choice.scope) {
        lastCoverScope = choice.scope;
        studio.allowedCodes = index.scopeCodes(choice.scope);
        registerFrame(choice);
    }
};

const summarize = () => {
    clearNudge();
    const choice = formChoice();
    const scope = (config.scopes || []).find((item) => item.key === choice.scope);
    if (deckUnlocked && !shouldKeepDeckOpen({ unlocked: deckUnlocked, activeChoice: built?.choice || null, draftChoice: choice })) {
        const currentScroll = window.scrollY;
        setDeckOpen(false);
        window.scrollTo({ top: Math.min(currentScroll, launcherLimit()), behavior: 'instant' });
    }
    const state = describeLauncherState({
        choice,
        scopeLabel: scope?.label || '',
        unlocked: deckUnlocked,
        activeChoice: built?.choice || null,
    });
    if (summaryNode) summaryNode.textContent = state.summary;
    if (startButton) startButton.disabled = building || !state.canStart;
    form.querySelectorAll('[data-sg-toggle-group]').forEach((button) => {
        const boxes = form.querySelectorAll(`[data-sg-group="${button.dataset.sgToggleGroup}"] input[name="segmen"]`);
        button.textContent = Array.from(boxes).every((box) => box.checked) ? 'Kosongkan' : 'Pilih semua';
    });
    syncCoverScope(choice);
};

const paintForm = (choice) => {
    form.querySelectorAll('input[name="segmen"]').forEach((input) => { input.checked = choice.segments.includes(input.value); });
    form.querySelectorAll('input[name="scope"]').forEach((input) => { input.checked = input.value === choice.scope; });
    summarize();
};

const nudgeLauncher = () => {
    const choice = formChoice();
    const scope = (config.scopes || []).find((item) => item.key === choice.scope);
    const state = describeLauncherState({
        choice,
        scopeLabel: scope?.label || '',
        unlocked: deckUnlocked,
        activeChoice: built?.choice || null,
    });
    if (summaryNode && state.nudgeMessage) summaryNode.textContent = state.nudgeMessage;
    if (actionsBar) {
        actionsBar.classList.add('is-nudged');
        window.clearTimeout(nudgeTimer);
        nudgeTimer = window.setTimeout(() => actionsBar.classList.remove('is-nudged'), 1400);
    }
};

/* --------------------------------------------------------------------- deck -- */

const rescan = (host) => {
    bindDonuts(host);
    bindTimeseries(host);
    motion.scan(host);
    host.querySelectorAll('[role="tablist"]:not([data-tabs-bound])').forEach((list) => {
        list.dataset.tabsBound = '1';
        const tabs = Array.from(list.querySelectorAll('[role="tab"]'));
        const sync = () => tabs.forEach((tab) => { tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1; });
        list.addEventListener('click', sync);
        list.addEventListener('keydown', (event) => {
            const current = tabs.indexOf(event.target);
            if (current < 0 || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            tabs[next].focus();
            tabs[next].click();
            sync();
        });
        sync();
    });
};

const closing = (choice, scope) => `
    <section class="sg-close sg-ground--dark" id="sg-close" data-sg-segment="penutup" aria-labelledby="sg-close-title">
        <div class="sg-close__stage" data-territory="close" data-territory-pose="close" aria-hidden="true"><div class="sg-poster" data-territory-poster></div></div>
        <div class="sg-close__copy">
            <h2 class="sg-close__title" id="sg-close-title">${h(scope?.label || '')}.<br><span>Posisi ${h(config.periodLabel || '')}.</span></h2>
            <p class="sg-close__list">${choice.segments.map((key) => `<a href="#sg-${h(key)}">${h(LABELS[key] || key)}</a>`).join('')}</p>
            <p class="sg-close__actions">
                <button type="button" class="kn-button kn-button--primary kn-button--large" data-sg-open-launcher data-sc-magnet="0.3">Ubah segmen</button>
                <a class="kn-link" href="${h(config.dashboardUrl)}">Kembali ke dashboard<i class="fas fa-chevron-right" aria-hidden="true"></i></a>
            </p>
        </div>
    </section>`;

const buildDeck = async (choice) => {
    const scope = (config.scopes || []).find((item) => item.key === choice.scope) || config.scopes?.[0];
    const needsKinerja = choice.segments.some((key) => ['simpanan', 'pinjaman', 'kualitas', 'recovery'].includes(key));
    progress(30, 'Mengambil data segmen');
    const [features, segments, wilayahData] = await Promise.all([
        studio.ready,
        needsKinerja ? loadSegments(choice.scope) : Promise.resolve(null),
        // Always: the Kinerja relief places outlets on the map through the same REKAP units.
        loadWilayah(),
    ]);
    progress(78, 'Menyusun presentasi');
    index = new TerritoryIndex(features, wilayahData?.mapping);
    const ctx = { scope, segments, wilayah: wilayahData, index, rescan, presentationState: {} };
    ctx.crasUrl = config.crasUrl;
    ctx.period = config.period;

    const parts = choice.segments.map((key) => {
        try {
            return { key, ...RENDERERS[key](ctx) };
        } catch (error) {
            console.error(`Segment ${key} failed to render.`, error);
            return { key, ...wilayah.emptySegment(key, LABELS[key] || key) };
        }
    });
    built?.parts.forEach((part) => part.dispose?.());
    built?.journey?.destroy();
    studio.states.clear();
    studio.overrides.clear();
    studio.allowedCodes = index.scopeCodes(scope.key);
    disposeCharts(deck);
    deck.innerHTML = parts.map((part) => part.html).join('') + closing(choice, scope);
    built = { choice, parts };
    lastCoverScope = choice.scope;
    setDeckOpen(true);

    nav.querySelector('[data-sg-nav-segments]').innerHTML = choice.segments.map((key) => `<li><a class="kn-nav__chapter" href="#sg-${h(key)}" data-sg-nav-link="${h(key)}">${h((LABELS[key] || key).replace('Kinerja ', ''))}</a></li>`).join('');
    nav.querySelectorAll('[data-sg-scope-label]').forEach((node) => { node.textContent = scope?.label || ''; });

    studio.scan();
    parts.forEach((part) => {
        try {
            part.territory?.(studio);
            part.dispose = part.bind?.(deck, studio);
        } catch (error) {
            console.error(`Segment ${part.key} territory failed.`, error);
        }
    });
    registerFrame(choice);
    bindMapControls({ deck, scope, index, studio, segments });
    const renderSelection = (key, selection) => {
        const part = parts.find((item) => item.key === key);
        const chapter = document.getElementById(`sg-${key}`);
        const next = RENDERERS[key]({ ...ctx, selection });
        const template = document.createElement('template');
        template.innerHTML = next.html;
        part.dispose?.();
        disposeCharts(chapter);
        const previousFigure = chapter.querySelector('.sg-opener__copy .sg-figure__value')?.textContent.trim();
        chapter.querySelector('.sg-opener__copy').innerHTML = template.content.querySelector('.sg-opener__copy').innerHTML;
        chapter.querySelector('.sg-sheet').innerHTML = template.content.querySelector('.sg-sheet').innerHTML;
        next.territory?.(studio);
        part.dispose = next.bind?.(deck, studio);
        rescan(chapter);
        if (previousFigure) motion.morph(chapter.querySelector('.sg-opener__copy .sg-figure__value'), previousFigure);
    };
    built.journey = new PolygonJourney({ deck, scope, index, studio, renderSelection, rescan }).init();
    rescan(deck);
    progress(100, 'Siap');
};

/* ------------------------------------------------------------------- wiring -- */

const scrollTo = (element) => element?.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'start' });

const start = async (event) => {
    event?.preventDefault();
    if (building) return;
    const choice = formChoice();
    if (!choice.segments.length) {
        nudgeLauncher();
        form.querySelector('input[name="segmen"]')?.focus({ preventScroll: true });
        return;
    }
    writeChoice(choice);
    const same = built && isSameChoice(built.choice, choice);
    if (!same) {
        building = true;
        let failed = false;
        form.setAttribute('aria-busy', 'true');
        if (startButton) startButton.disabled = true;
        setStartBusy(true);
        root.classList.add('sg-building');
        try {
            await buildDeck(choice);
        } catch (error) {
            console.warn('Presentation could not be refreshed.', error);
            setDeckOpen(false);
            failed = true;
        } finally {
            building = false;
            form.setAttribute('aria-busy', 'false');
            setStartBusy(false);
            root.classList.remove('sg-building');
            summarize();
        }
        if (failed) {
            if (summaryNode) summaryNode.textContent = 'Pilihan presentasi belum dapat dimuat. Silakan coba kembali.';
            return;
        }
    } else {
        setDeckOpen(true);
        if (index) studio.allowedCodes = index.scopeCodes(choice.scope);
        studio.scan();
        motion.measure();
        built.journey?.enforce();
        summarize();
    }
    scrollCraftInstance?.destroy?.();
    scrollCraftInstance = window.ScrollCraft?.mount?.(document.body) || null;
    scrollTo(document.getElementById(`sg-${choice.segments[0]}`));
};

const init = async () => {
    if (!reduceMotion()) root.classList.add('sg-motion');
    setDeckOpen(false);
    studio.init();
    motion.init();
    const choice = readChoice();
    paintForm(choice);
    progress(20, 'Memuat peta wilayah');

    form.addEventListener('change', summarize);
    form.addEventListener('submit', start);
    form.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-sg-toggle-group]');
        if (!toggle) return;
        const boxes = Array.from(form.querySelectorAll(`[data-sg-group="${toggle.dataset.sgToggleGroup}"] input[name="segmen"]`));
        const fill = !boxes.every((box) => box.checked);
        boxes.forEach((box) => { box.checked = fill; });
        summarize();
    });
    window.addEventListener('scroll', enforceLauncherGate, { passive: true });
    window.addEventListener('resize', enforceLauncherGate, { passive: true });
    window.addEventListener('wheel', (event) => {
        if (!deckUnlocked && event.deltaY > 8 && atLauncherBottom()) nudgeLauncher();
    }, { passive: true });
    window.addEventListener('touchstart', (event) => {
        touchStartY = event.touches?.[0]?.clientY ?? null;
    }, { passive: true });
    window.addEventListener('touchmove', (event) => {
        if (deckUnlocked || touchStartY === null) return;
        const currentY = event.touches?.[0]?.clientY ?? touchStartY;
        if (touchStartY - currentY > 18 && atLauncherBottom()) {
            touchStartY = currentY;
            nudgeLauncher();
        }
    }, { passive: true });
    window.addEventListener('keydown', (event) => {
        if (deckUnlocked || !['PageDown', 'ArrowDown', 'End', ' '].includes(event.key)) return;
        if (event.key === ' ' && event.target?.matches?.('input, button, a, summary, textarea, select')) return;
        if (atLauncherBottom()) nudgeLauncher();
    });
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-sg-open-launcher]')) {
            event.preventDefault();
            setDeckOpen(false);
            window.scrollTo({ top: Math.min(window.scrollY, launcherLimit()), behavior: 'instant' });
            summarize();
            scrollTo(launcher);
            form.querySelector('input[name="segmen"]:checked, input[name="segmen"]')?.focus({ preventScroll: true });
            return;
        }
        if (!deckUnlocked) {
            const anchor = event.target.closest('a[href^="#"]');
            if (anchor && anchor.getAttribute('href') !== '#sg-launcher') {
                event.preventDefault();
                nudgeLauncher();
            }
        }
    });
    // The relief tells its chapter what it is looking at; the chapter prints it beside the figure.
    document.addEventListener('territorystop', (event) => {
        const caption = event.target.closest('[data-sg-segment]')?.querySelector('[data-sg-caption]');
        if (!caption) return;
        caption.innerHTML = event.detail.caption || '';
        caption.classList.toggle('is-on', Boolean(event.detail.caption));
    });

    try {
        const [features, wilayahData] = await Promise.all([
            studio.ready,
            loadWilayah(),
        ]);
        progress(85, 'Menyiapkan pilihan segmen');
        index = new TerritoryIndex(features, wilayahData?.mapping);
        lastCoverScope = null;
        syncCoverScope(formChoice());
        studio.scan();
        scrollCraftInstance = window.ScrollCraft?.mount?.(document.body) || null;
    } catch (error) {
        console.warn('Presentation launcher loaded without relief preview.', error);
    }
    enforceLauncherGate();
    progress(100, 'Siap');
    loader.classList.remove('active');
    root.classList.add('sg-ready');
};

init();
