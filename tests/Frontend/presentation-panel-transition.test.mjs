import test from 'node:test';
import assert from 'node:assert/strict';
import { PanelTransition } from '../../resources/js/presentation/segments/panel-transition.js';
import { TerritoryStudio } from '../../resources/js/presentation/worlds/territory-studio.js';
import { PolygonJourney } from '../../resources/js/presentation/segments/polygon-journey.js';

test('cancelling a pending panel wait prevents its continuation and runs cleanup once', async () => {
    const transition = new PanelTransition();
    let early = 0;
    let late = 0;
    transition.cleanup(() => early++);
    const pending = transition.wait(10000);
    transition.cancel();
    transition.cancel();
    transition.cleanup(() => late++);
    assert.equal(await pending, false);
    assert.equal(await transition.wait(0), false);
    assert.equal(transition.active, false);
    assert.equal(early, 1);
    assert.equal(late, 1);
});

test('rapid replacement cancels the old panel session while the new session completes', async () => {
    const old = new PanelTransition();
    const stale = old.wait(10000);
    old.cancel();
    const current = new PanelTransition();
    assert.equal(await stale, false);
    assert.equal(await current.wait(0), true);
    assert.equal(current.active, true);
    current.cancel();
});

test('animation cancellation is handled without a rejected continuation or duplicate disposal', async (t) => {
    let rejectFinished;
    let cancelled = 0;
    let created = 0;
    const unhandled = [];
    const onUnhandled = error => unhandled.push(error);
    process.on('unhandledRejection', onUnhandled);
    t.after(() => process.removeListener('unhandledRejection', onUnhandled));
    const element = { animate() {
        created++;
        return { finished: new Promise((_, reject) => { rejectFinished = reject; }), cancel() { cancelled++; rejectFinished(new Error('cancelled')); } };
    } };
    const transition = new PanelTransition();
    const pending = transition.animate(element, [{ opacity: 0 }, { opacity: 1 }], { duration: 10000 });
    transition.cancel();
    transition.cancel();
    assert.equal(await pending, false);
    assert.equal(await transition.animate(element, [], {}), false);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(cancelled, 1);
    assert.equal(created, 1);
    assert.deepEqual(unhandled, []);
});

const poster = (boxes) => {
    let viewBox = '10 20 300 400';
    let reads = 0;
    const paths = boxes.map((box, index) => ({ dataset: { code: String(index) }, classList: { toggle() {} }, getBBox() { reads++; return box; } }));
    const svg = { querySelectorAll: () => paths, setAttribute(name, value) { if (name === 'viewBox') viewBox = value; } };
    const slot = { isConnected: true, querySelector: () => svg, closest: () => null, getClientRects: () => [{}] };
    return { slot, viewBox: () => viewBox, reads: () => reads };
};

test('hidden or detached map slots are not measured while a panel is away', () => {
    const studio = new TerritoryStudio({ geographyUrl: '/geography' });
    for (const kind of ['detached', 'hidden', 'empty-layout']) {
        const map = poster([{ x: 0, y: 0, width: 100, height: 100 }]);
        if (kind === 'detached') map.slot.isConnected = false;
        if (kind === 'hidden') map.slot.closest = () => ({});
        if (kind === 'empty-layout') map.slot.getClientRects = () => [];
        studio.framePoster(map.slot, new Set(['0']));
        assert.equal(map.reads(), 0, kind);
        assert.equal(map.viewBox(), '10 20 300 400', kind);
    }
});

test('invalid focused SVG bounds preserve a valid viewBox until usable geometry is available', () => {
    const studio = new TerritoryStudio({ geographyUrl: '/geography' });
    const invalid = poster([{ x: NaN, y: 0, width: 10, height: 10 }, { x: 0, y: 0, width: 0, height: 10 }]);
    studio.framePoster(invalid.slot, new Set(['0', '1']));
    assert.equal(invalid.viewBox(), '10 20 300 400');
    const valid = poster([{ x: 20, y: 30, width: 40, height: 50 }]);
    studio.framePoster(valid.slot, new Set(['0']));
    const [x, y, width, height] = valid.viewBox().split(' ').map(Number);
    assert.ok([x, y, width, height].every(Number.isFinite));
    assert.ok(width > 0 && height > 0 && x <= 20 && y <= 30 && x + width >= 60 && y + height >= 80);
});

const viewport = (t) => {
    const previousWindow = globalThis.window;
    const previousStyle = globalThis.getComputedStyle;
    globalThis.window = { innerWidth: 1000, innerHeight: 800 };
    globalThis.getComputedStyle = slot => ({ visibility: slot.visibility || 'visible' });
    t.after(() => {
        if (previousWindow === undefined) delete globalThis.window; else globalThis.window = previousWindow;
        if (previousStyle === undefined) delete globalThis.getComputedStyle; else globalThis.getComputedStyle = previousStyle;
    });
};
const slot = (key, { x = 100, y = 100, width = 400, height = 400, hidden = false, inert = false, connected = true, visibility = 'visible', cover = null } = {}) => ({
    dataset: { territory: key }, isConnected: connected, visibility,
    getBoundingClientRect: () => ({ x, y, left: x, top: y, right: x + width, bottom: y + height, width, height }),
    getClientRects: () => [{}],
    closest(selector) {
        if (selector.includes('[hidden]') && hidden || selector.includes('[inert]') && inert) return { classList: { contains: () => false } };
        if (selector === '[data-territory-track]') return { nextElementSibling: cover };
        return null;
    },
});

test('slot selection rejects inactive and horizontally offscreen map panels', (t) => {
    viewport(t);
    const studio = new TerritoryStudio({ geographyUrl: '/geography' });
    const current = slot('current');
    studio.slots = [slot('off-left', { x: -600 }), slot('off-right', { x: 1001 }), slot('hidden', { hidden: true }), slot('inert', { inert: true }), slot('detached', { connected: false }), slot('zero', { width: 0 }), slot('invalid', { x: NaN }), slot('css-hidden', { visibility: 'hidden' }), current];
    studio.slots.forEach(node => studio.states.set(node.dataset.territory, {}));
    assert.equal(studio.pickSlot(), current);
});

test('only the actual visible sheet overlap can obscure a map slot', (t) => {
    viewport(t);
    const studio = new TerritoryStudio({ geographyUrl: '/geography' });
    for (const bounds of [
        { left: 0, top: 250, right: 1000, bottom: 1200, width: 1000, height: 950 },
        { left: 1100, top: 0, right: 2100, bottom: 800, width: 1000, height: 800 },
    ]) {
        const cover = { hidden: false, classList: { contains: value => value === 'sg-sheet' }, getBoundingClientRect: () => bounds };
        const current = slot('current', { y: -100, height: 600, cover });
        studio.slots = [current]; studio.states.set('current', {});
        assert.equal(studio.pickSlot(), current, 'A still-visible map area must remain active');
    }
});

test('surface release clears stale viewport and interactive labels while keeping the scene alive', (t) => {
    const previousDocument = globalThis.document;
    const removedClasses = [];
    globalThis.document = { documentElement: { classList: { remove: (...names) => removedClasses.push(...names) } } };
    t.after(() => { if (previousDocument === undefined) delete globalThis.document; else globalThis.document = previousDocument; });
    const studio = new TerritoryStudio({ geographyUrl: '/geography' });
    const hoverStates = [];
    const scene = { setHovered: value => hoverStates.push(value), dispose() { throw new Error('persistent scene must survive'); } };
    const label = { style: { opacity: '1' }, inert: false };
    studio.scene = scene;
    studio.activeSlot = {};
    studio.appliedKey = 'old';
    studio.rect = { x: 0, y: 0, w: 600, h: 500 };
    studio.blur = 2;
    studio.pointer.free = true;
    studio.tap = { id: 1 };
    Object.assign(studio.drag, { active: true, yaw: 12, pitch: 4 });
    studio.labels.set('old', label);
    studio.labelItems = [{}];
    studio.layer = { classList: { remove() {} }, style: { clipPath: 'inset(10px)', setProperty(name, value) { this[name] = value; } } };
    studio.releaseSurface();
    assert.equal(studio.scene, scene);
    assert.equal(studio.activeSlot, null);
    assert.equal(studio.appliedKey, null);
    assert.equal(studio.rect, null);
    assert.equal(studio.blur, 0);
    assert.equal(studio.layer.style.clipPath, '');
    assert.equal(studio.pointer.free, false);
    assert.equal(studio.tap, null);
    assert.equal(studio.drag.active, false);
    assert.equal(studio.drag.yaw, 0);
    assert.equal(studio.drag.pitch, 0);
    assert.equal(label.style.opacity, '0');
    assert.equal(label.inert, true);
    assert.deepEqual(studio.labelItems, []);
    assert.ok(removedClasses.includes('sg-dragging'));
    assert.ok(removedClasses.includes('sg-relief-hot'));
    assert.deepEqual(hoverStates, [null]);
});

test('choosing a polygon while its deck is hidden or inert cannot replace selection or render content', async () => {
    const journey = Object.create(PolygonJourney.prototype);
    const selection = { marker: 'existing selection' };
    const record = { chapter: { isConnected: true, inert: false }, selection };
    let rendered = 0;
    Object.assign(journey, { disposed: false, deck: { hidden: true, inert: false }, branchKey: 'existing branch', transition: null, renderSelection() { rendered++; } });
    await journey.choose(record, { code: 'new polygon' });
    journey.deck.hidden = false;
    journey.deck.inert = true;
    await journey.choose(record, { code: 'new polygon' });
    assert.equal(record.selection, selection);
    assert.equal(journey.branchKey, 'existing branch');
    assert.equal(journey.transition, null);
    assert.equal(rendered, 0);
});

test('suspending cancels pending panel completion and resumes map view without a stale continuation', async () => {
    const journey = Object.create(PolygonJourney.prototype);
    const task = new PanelTransition();
    let completed = 0;
    const pending = task.wait(10000).then(active => { if (active) completed++; });
    const removed = [];
    const record = { view: 'detail', slot: { classList: { remove: name => removed.push(name) } } };
    let released = 0;
    let scanned = 0;
    Object.assign(journey, { disposed: false, deck: { hidden: true }, transition: task, transitionRecord: record, records: new Map([['map', record]]), setView(item, view) { item.view = view; }, prepareMap(item) { item.mapOverview = true; }, enforce() {}, studio: { releaseSurface() { released++; }, scan() { scanned++; } } });
    journey.suspend();
    await pending;
    assert.equal(completed, 0);
    assert.equal(task.active, false);
    assert.equal(journey.transition, null);
    assert.equal(journey.transitionRecord, null);
    assert.equal(record.view, 'map');
    assert.ok(removed.includes('is-keyboard-map'));
    journey.deck.hidden = false;
    journey.resume();
    assert.equal(record.view, 'map');
    assert.equal(record.mapOverview, true);
    assert.equal(released, 1);
    assert.equal(scanned, 1);
    assert.equal(await task.wait(0), false);
});
