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
import { PresentationCinema, seamDirection } from './cinema.js';
import { PolygonJourney } from './polygon-journey.js';
import { PerformanceJourney, PERFORMANCE_KEYS } from './performance-journey.js';
import { PresentationRoom, topicContext } from './presentation-room.js';
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
import { chapter, empty } from './layout.js';

const config = window.__SEGMENT_CONFIG__ || {};
const RENDERERS = {};
let wilayahModule;
let kinerjaModule;
const loadRenderers = (keys) => Promise.all([
    keys.some(key => ['marketshare', 'mapping', 'sektoral'].includes(key))
        ? (wilayahModule ||= import('./render-wilayah.js').then(module => Object.assign(RENDERERS, module)).catch(error => { wilayahModule = null; throw error; })) : null,
    keys.some(key => PERFORMANCE_KEYS.includes(key))
        ? (kinerjaModule ||= import('./render-kinerja.js').then(module => Object.assign(RENDERERS, module)).catch(error => { kinerjaModule = null; throw error; })) : null,
]);
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
const cinema = new PresentationCinema({ studio, labels: LABELS });
let index = null;
let built = null;
let building = false;
let deckUnlocked = false;
let lastCoverScope = null;
let nudgeTimer = null;
let touchStartY = null;
let scrollCraftInstance = null;
let navigationId = 0;
let pendingRoomContext = null;
const rooms = new PresentationRoom({ main: document.querySelector('.sg-main'), deck, launcher, nav, labels: LABELS,
    onNavigate: (key, options) => navigateRoom(key, options), onBack: () => backFromRoom(),
    onLauncher: (options) => openLauncher(options), onLeave: (key) => {
        built?.journey?.cancelTransition();
        built?.performance?.cancelTransition();
        const chapter = key && document.getElementById(`sg-${key}`);
        if (chapter) motion.release(chapter);
        studio.releaseSurface();
    }, onArrive: (chapter, view, direction) => cinema.arrive(chapter, view, direction), onArriveCancel: () => cinema.cancelArrival(),
    onFinale: () => {
        // The last gesture: every piece of the territory closes up and holds.
        studio.scan();
        studio.seam(1, { assemble: 0.55 });
        cinema.arrive(document.getElementById('sg-close'), 'finale', 1);
        const title = document.querySelector('#sg-close .sg-close__title');
        if (title) { title.tabIndex = -1; title.focus({ preventScroll: true }); }
    } });

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
    startButton?.setAttribute('aria-busy', String(busy));
    const label = startButton?.querySelector('[data-sg-start-label]');
    if (label) label.textContent = busy ? 'Menyiapkan presentasi…' : 'Mulai presentasi';
    if (!startIcon) return;
    startIcon.className = busy ? 'fas fa-circle-notch fa-spin' : 'fas fa-arrow-right';
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
        built?.journey?.suspend();
        built?.performance?.suspend();
        motion.release(deck);
        nav?.querySelector('[data-sg-progress]')?.style.setProperty('--kn-read', '0');
        studio.releaseSurface();
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
    window.history.replaceState(window.history.state, '', url.toString());
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

/** The territory on the launcher closes up and rises as the agenda is composed: 0 picks, parted; all picks, whole. */
let agendaShare = 0;
const registerFrame = (choice) => {
    const neutral = () => ({ lift: 0.1 + agendaShare * 0.1, color: 0x9db5d2, roughness: 0.38 });
    const focus = index?.scopeCodes(choice.scope) || null;
    studio.register('cover', {
        journey: [{ until: 1, focus, heights: neutral }],
        dark: 1,
        version: agendaShare,
        fx: { beams: false, flow: 0, speed: 0.8, color: 0x7fa6e8 },
        arrangement: (p, still) => ({ explode: still ? 0 : Math.max(.16 * (1 - Math.min(1, p / 0.8)), .2 * (1 - agendaShare)), spread: 0, levitate: 0, levitateBranch: null, grow: 1, wave: 0 }),
    });
    studio.registerFlightState();
    studio.register('close', {
        journey: [{ until: 1, focus, heights: neutral }],
        dark: 1,
        fx: { beams: false, flow: 0, speed: 0.7, color: 0x7fa6e8 },
        arrangement: () => ({ explode: 0, spread: 0, levitate: 0, levitateBranch: null, grow: 1, wave: 0 }),
    });
};

const syncCoverScope = (choice) => {
    const scope = (config.scopes || []).find((item) => item.key === choice.scope) || config.scopes?.[0];
    nav?.querySelectorAll('[data-sg-scope-label]').forEach((node) => { node.textContent = scope?.label || ''; });
    const share = ORDER.length ? choice.segments.length / ORDER.length : 0;
    if (index && (lastCoverScope !== choice.scope || share !== agendaShare)) {
        lastCoverScope = choice.scope;
        agendaShare = share;
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
    const agenda = launcher.querySelector('[data-sg-agenda]');
    if (agenda) agenda.innerHTML = choice.segments.map((key, i) => `<span><small>${String(i + 1).padStart(2, '0')}</small>${h(LABELS[key].replace('Kinerja ', ''))}</span>`).join('') || '<span>Pilih materi untuk menyusun agenda.</span>';
    const agendaCount = launcher.querySelector('[data-sg-agenda-count]');
    if (agendaCount) agendaCount.textContent = choice.segments.length;
    const coverScope = launcher.querySelector('[data-sg-cover-scope]');
    if (coverScope) coverScope.textContent = scope?.label || '';
    if (startButton) startButton.disabled = building || !state.canStart;
    form.classList.toggle('has-agenda', choice.segments.length > 0);
    form.querySelectorAll('[data-sg-toggle-group]').forEach((button) => {
        const boxes = form.querySelectorAll(`[data-sg-group="${button.dataset.sgToggleGroup}"] input[name="segmen"]`);
        const selected = Array.from(boxes).filter((box) => box.checked).length;
        button.textContent = selected === boxes.length ? 'Kosongkan' : 'Pilih semua';
        button.setAttribute('aria-label', `${button.textContent} materi ${button.dataset.sgToggleGroup === 'wilayah' ? 'Wilayah' : 'Kinerja'}`);
        const count = form.querySelector(`[data-sg-group-count="${button.dataset.sgToggleGroup}"]`);
        if (count) count.textContent = `${selected} / ${boxes.length}`;
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
    cinema.scan(host);
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
        loadRenderers(choice.segments),
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
            return { key, html: chapter({ key, group: 'Segmen', title: LABELS[key] || key, lede: 'Data belum tersedia.', territory: false, span: 120, sheet: empty('Data belum tersedia untuk cakupan ini.') }) };
        }
    });
    built?.parts.forEach((part) => part.dispose?.());
    built?.journey?.destroy();
    built?.performance?.destroy();
    motion.release(deck);
    studio.states.clear();
    studio.overrides.clear();
    studio.allowedCodes = index.scopeCodes(scope.key);
    disposeCharts(deck);
    deck.innerHTML = parts.map((part) => part.html).join('') + closing(choice, scope);
    built = { choice, parts };
    rooms.mount(choice.segments, scope);
    rooms.activate(choice.segments[0]);
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
    const renderSelection = (key, selection, performanceData = null) => {
        const part = parts.find((item) => item.key === key);
        const chapter = document.getElementById(`sg-${key}`);
        const next = RENDERERS[key]({ ...ctx, selection, ...(PERFORMANCE_KEYS.includes(key) ? { segments: performanceData || segments, overviewSegments: segments } : {}) });
        const template = document.createElement('template');
        template.innerHTML = next.html;
        part.dispose?.();
        motion.release(chapter);
        disposeCharts(chapter);
        const previousFigure = chapter.querySelector('.sg-opener__copy .sg-figure__value')?.textContent.trim();
        chapter.querySelector('.sg-opener__copy').innerHTML = template.content.querySelector('.sg-opener__copy').innerHTML;
        chapter.querySelector('.sg-sheet').innerHTML = template.content.querySelector('.sg-sheet').innerHTML;
        next.territory?.(studio);
        part.dispose = next.bind?.(deck, studio);
        rescan(chapter);
        if (previousFigure) motion.morph(chapter.querySelector('.sg-opener__copy .sg-figure__value'), previousFigure);
    };
    const areaNavigation = { allowArea: (config.scopes || []).some(item => item.key === 'area6'), onArea: openAreaRoom };
    built.journey = new PolygonJourney({ deck, scope, index, studio, renderSelection, rescan, room: rooms, ...areaNavigation }).init();
    built.performance = new PerformanceJourney({ deck, scope, index, studio, renderSelection, rescan, room: rooms,
        overview: segments, load: loadSegments, presentationState: ctx.presentationState, ...areaNavigation }).init();
    rescan(deck);
    progress(100, 'Siap');
};

/* ------------------------------------------------------------------- wiring -- */

const scrollTo = (element) => element?.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'start' });

function openLauncher({ history = 'push' } = {}) {
    navigationId++;
    pendingRoomContext = null;
    rooms.showLauncher(history);
    setDeckOpen(false);
    window.scrollTo({ top: 0, behavior: 'instant' });
    summarize();
    studio.scan();
    const heading = form.querySelector('[data-sg-compose-title]');
    heading?.scrollIntoView({ behavior: 'instant', block: 'start' });
    heading?.focus({ preventScroll: true });
}

function backFromRoom() {
    navigationId++;
    pendingRoomContext = null;
    const journey = PERFORMANCE_KEYS.includes(rooms.activeKey) ? built?.performance : built?.journey;
    const record = journey?.records.get(rooms.activeKey);
    if (record && journey.pending) { journey.cancelTransition(); journey.finishRoom(record, 'map', 'replace'); }
    else if (record && (record.view === 'detail' || journey.transition)) journey.showMap(record, { history: 'replace' });
    else openLauncher();
}

async function openAreaRoom(key) {
    if (building || !built || !(config.scopes || []).some(item => item.key === 'area6')) return;
    rooms.initial = { key, view: 'map' };
    paintForm({ ...built.choice, scope: 'area6' });
    await start();
    if (!built || built.choice.scope !== 'area6' || rooms.activeKey !== key) return;
    const journey = PERFORMANCE_KEYS.includes(key) ? built.performance : built.journey;
    const record = journey?.records.get(key);
    if (record) await journey.choose(record, { scopeKey: 'area6' });
}

async function navigateRoom(key, { restore = null, history = 'push' } = {}) {
    if (!built || !built.choice.segments.includes(key)) return;
    // A loading map is temporary; retain the requested KC while its owner is current.
    const context = pendingRoomContext && built.performance?.pending && pendingRoomContext.requestId === built.performance.requestId
        ? pendingRoomContext.state : rooms.state;
    restore ||= topicContext(context, key, built.choice.segments);
    pendingRoomContext = null;
    const ticket = ++navigationId;
    const journey = PERFORMANCE_KEYS.includes(key) ? built.performance : built.journey;
    const record = journey?.records.get(key);
    const requested = restore?.view || (PERFORMANCE_KEYS.includes(key) ? record?.view || 'map'
        : record?.selection && rooms.state?.view === 'detail' ? 'detail' : record?.view || 'report');
    if (key === rooms.activeKey && record && requested === 'map' && record.view === 'detail') {
        await journey.showMap(record, { history });
        return;
    }
    setDeckOpen(true);
    rooms.activate(key);
    if (rooms.previousKey && rooms.previousKey !== key) cinema.seam(seamDirection(built.choice.segments, rooms.previousKey, key));
    if (record) {
        if (PERFORMANCE_KEYS.includes(key) && restore?.view === 'detail') {
            journey.setView(record, 'map');
            journey.prepareRoomMap(record);
            rooms.commit({ key, view: 'map', context: rooms.scope.label }, 'none');
        }
        const restoration = restore && requested === 'detail' ? journey.restoreSelection(record, restore) : null;
        const requestOwner = journey.requestId;
        if (restoration && PERFORMANCE_KEYS.includes(key)) pendingRoomContext = { ticket, requestId: requestOwner, state: restore };
        const restored = restoration ? await restoration : true;
        if (pendingRoomContext?.ticket === ticket) pendingRoomContext = null;
        if (ticket !== navigationId || rooms.activeKey !== key
            || restoration && PERFORMANCE_KEYS.includes(key) && requestOwner !== journey.requestId) return;
        const view = requested === 'detail' && restored && record.selection ? 'detail' : 'map';
        journey.setView(record, view);
        if (view === 'map') journey.prepareRoomMap(record);
        rooms.commit({ key, view, selection: view === 'detail' ? record.selection : null,
            context: view === 'detail' ? record.branchLabel : built.choice.scope === 'area6' ? 'Pilih kantor cabang pada peta' : rooms.scope.label }, history);
        journey.enforce();
    } else rooms.commit({ key, view: 'report', context: rooms.scope.label }, history);
    studio.scan();
    rescan(document.getElementById(`sg-${key}`));
    let heading = document.getElementById(`sg-${key}`).querySelector(record?.view === 'detail' ? '.sg-detail-head__title' : '.sg-opener__title, h2');
    if (!heading?.getClientRects().length) heading = rooms.head.querySelector('[data-room-title]');
    if (heading) { heading.tabIndex = -1; heading.focus({ preventScroll: true }); }
    rooms.animateArrival();
}

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
    const flying = cinema.beginFlight({
        scopeLabel: (config.scopes || []).find((item) => item.key === choice.scope)?.label || '',
        periodLabel: config.periodLabel || '',
        agenda: choice.segments.map((key) => (LABELS[key] || key).replace('Kinerja ', '')),
        onUncover: () => cinema.arrive(document.querySelector('[data-sg-segment][data-room-active="true"]'), rooms.state?.view, 1),
    });
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
            cinema.abortFlight();
            openLauncher({ history: 'replace' });
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
        motion.scan(deck);
        built.journey?.enforce();
        built.performance?.enforce();
        summarize();
    }
    scrollCraftInstance?.destroy?.();
    scrollCraftInstance = window.ScrollCraft?.mount?.(document.body) || null;
    const initial = rooms.initial && choice.segments.includes(rooms.initial.key) ? rooms.initial : null;
    rooms.initial = null;
    await navigateRoom(initial?.key || choice.segments[0], { restore: initial, history: 'push' });
    if (flying) await cinema.land();
    else cinema.arrive(document.querySelector('[data-sg-segment][data-room-active="true"]'), rooms.state?.view, 1);
};

const init = async () => {
    if (!reduceMotion()) root.classList.add('sg-motion');
    rooms.init();
    setDeckOpen(false);
    studio.init();
    motion.init();
    cinema.init();
    const fullscreen = document.querySelector('[data-sg-fullscreen]');
    const displayStatus = document.querySelector('[data-sg-display-status]');
    if (fullscreen && document.fullscreenEnabled && root.requestFullscreen) {
        fullscreen.hidden = false;
        const syncFullscreen = () => {
            const active = Boolean(document.fullscreenElement);
            fullscreen.setAttribute('aria-pressed', String(active));
            fullscreen.setAttribute('aria-label', active ? 'Keluar layar penuh' : 'Masuk layar penuh');
            fullscreen.querySelector('span').textContent = active ? 'Keluar layar penuh' : 'Layar penuh';
            fullscreen.querySelector('i').className = `fas fa-${active ? 'compress' : 'expand'}`;
        };
        document.addEventListener('fullscreenchange', syncFullscreen);
        fullscreen.addEventListener('click', async () => {
            fullscreen.disabled = true;
            try {
                if (document.fullscreenElement) await document.exitFullscreen();
                else await root.requestFullscreen();
                displayStatus.textContent = document.fullscreenElement ? 'Layar penuh aktif. Tekan Escape untuk keluar.' : 'Layar penuh ditutup.';
            } catch { displayStatus.textContent = 'Layar penuh tidak tersedia pada browser ini. Presentasi tetap dapat digunakan.'; }
            finally { fullscreen.disabled = false; syncFullscreen(); }
        });
    }
    const choice = readChoice();
    paintForm(choice);
    progress(20, 'Memuat peta wilayah');

    form.addEventListener('change', summarize);
    document.querySelector('[data-sg-compose]')?.addEventListener('click', () => {
        const heading = form.querySelector('[data-sg-compose-title]');
        heading?.scrollIntoView({ behavior: reduceMotion() ? 'instant' : 'smooth', block: 'start' });
        heading?.focus({ preventScroll: true });
    });
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
        if (event.target.closest('[data-sg-open-launcher], a[href="#sg-launcher"]')) {
            event.preventDefault();
            openLauncher();
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
