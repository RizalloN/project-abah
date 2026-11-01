import test from 'node:test';
import assert from 'node:assert/strict';
import { measureReadingBox, readingFrame } from '../../resources/js/presentation/segments/reading-clock.js';
import { SegmentMotion } from '../../resources/js/presentation/segments/motion.js';
import { PanelTransition } from '../../resources/js/presentation/segments/panel-transition.js';
import { PolygonJourney } from '../../resources/js/presentation/segments/polygon-journey.js';

const frame = (top, options = {}) => readingFrame({ top, height: 500, viewport: 900, ceiling: 150, ...options });

test('reading phases distinguish approach, entry, reading and departure', () => {
    assert.equal(frame(950).phase, 'before');
    assert.equal(frame(850).phase, 'enter');
    assert.equal(frame(600).phase, 'hold');
    assert.equal(frame(100).phase, 'leave');
    assert.equal(frame(-600).phase, 'after');
    assert.equal(frame(950).enter, 0);
    assert.equal(frame(600).enter, 1);
});

test('completed entry stays exact on reverse scroll while decoration can reverse', () => {
    let state = frame(600);
    assert.equal(state.settled, true);
    const pass = state.pass;
    for (const top of [750, 850, 950, 1200]) {
        state = frame(top, { settled: state.settled });
        assert.equal(state.enter, 1);
        assert.equal(state.settled, true);
        assert.ok(state.pass <= pass);
    }
    assert.equal(state.pass, 0);
});

test('invalid or inactive boxes cannot advance the entry latch', () => {
    for (const options of [{ top: NaN }, { height: 0 }, { viewport: -1 }, { ceiling: Infinity }, { active: false }]) {
        const result = frame(700, options);
        assert.equal(result.usable, false);
        assert.equal(result.settled, false);
        assert.equal(result.enter, 0);
        assert.equal(result.pass, 0);
        const previous = frame(700, { ...options, settled: true });
        assert.equal(previous.enter, 1);
        assert.equal(previous.settled, true);
    }
});

test('focused and reduced-motion readers immediately receive final geometry', () => {
    const normal = frame(850);
    for (const options of [{ focused: true }, { reduced: true }]) {
        const result = frame(850, options);
        assert.equal(result.enter, 1);
        assert.equal(result.settled, true);
        assert.equal(result.pass, normal.pass);
    }
});

test('very short and long layouts always produce finite bounded progress', () => {
    for (const viewport of [1, 64, 390, 900, 100000]) for (const height of [1, 300, 1000000]) {
        for (const ceiling of [-1000, 0, 150, viewport * 2]) for (const top of [-1000000, -1, 0, 100, 900, 1000000]) {
            const result = readingFrame({ top, height, viewport, ceiling });
            assert.equal(result.usable, true);
            assert.ok(Number.isFinite(result.enter) && result.enter >= 0 && result.enter <= 1);
            assert.ok(Number.isFinite(result.pass) && result.pass >= 0 && result.pass <= 1);
        }
    }
});

const box = (options = {}) => ({
    isConnected: true, offsetWidth: 600, offsetHeight: 300, offsetTop: 80, offsetLeft: 20,
    offsetParent: { offsetTop: 200, offsetLeft: 10, offsetParent: null },
    closest: () => null, getClientRects: () => [{}], ...options,
});

test('layout measurement uses untransformed offset coordinates', () => {
    const element = box({ getBoundingClientRect() { throw Error('transformed geometry must not drive the reading clock'); } });
    assert.deepEqual(measureReadingBox(element), { top: 280, left: 30, width: 600, height: 300 });
    assert.equal(measureReadingBox(box({ offsetParent: { offsetTop: NaN, offsetLeft: 0 } })), null);
    assert.equal(measureReadingBox(box({ offsetWidth: 0 })), null);
    assert.equal(measureReadingBox(box({ offsetHeight: -1 })), null);
});

test('hidden, inert, transitioning and detached objects are never measured', () => {
    for (const hidden of [true, 'hidden', 'inert', 'panel']) {
        const element = box({ isConnected: hidden !== true, closest: () => hidden === true ? null : {} });
        Object.defineProperty(element, 'offsetWidth', { get() { throw Error('inactive width read'); } });
        element.getClientRects = () => { throw Error('inactive rect read'); };
        assert.equal(measureReadingBox(element), null);
    }
    assert.equal(measureReadingBox(box({ getClientRects: () => [] })), null);
});

test('revealed and replaced objects receive fresh measurements without sharing entry state', () => {
    let hidden = true;
    const original = box({ closest: () => hidden ? {} : null });
    assert.equal(measureReadingBox(original), null);
    hidden = false;
    assert.deepEqual(measureReadingBox(original), { top: 280, left: 30, width: 600, height: 300 });
    const old = frame(500);
    assert.equal(old.settled, true);
    original.isConnected = false;
    assert.equal(measureReadingBox(original), null);
    const replacement = box({ offsetTop: 900 });
    assert.equal(measureReadingBox(replacement).top, 1100);
    assert.equal(frame(1100).settled, false);
    assert.equal(frame(1100).enter, 0);
});

const withWindow = (t, reduced = false) => {
    const previous = globalThis.window;
    globalThis.window = { matchMedia: () => ({ matches: reduced }), requestAnimationFrame() { throw Error('financial source text must not be interpolated'); } };
    t.after(() => { if (previous === undefined) delete globalThis.window; else globalThis.window = previous; });
};

test('reading figures retain exact supplied text rather than counting through invented values', (t) => {
    withWindow(t, true);
    const motion = Object.create(SegmentMotion.prototype);
    motion.figureAnimations = new Map();
    const element = { dataset: {}, textContent: 'Rp1.234,56 M', isConnected: true, closest: () => ({}) };
    motion.countUp(element);
    assert.equal(element.textContent, 'Rp1.234,56 M');
    motion.morph(element, 'Rp999,00 M');
    assert.equal(element.textContent, 'Rp1.234,56 M');
    assert.equal(motion.figureAnimations.size, 0);
});

test('a newer figure highlight survives stale completion and release cancels only owned highlights', async (t) => {
    withWindow(t);
    const motion = Object.create(SegmentMotion.prototype);
    motion.figureAnimations = new Map();
    const animations = [];
    const element = { dataset: {}, textContent: '80.816', isConnected: true, closest: () => ({}), animate() {
        let resolve, reject;
        const finished = new Promise((done, fail) => { resolve = done; reject = fail; });
        const animation = { finished, cancelled: 0, resolve, cancel() { this.cancelled++; reject(Error('cancelled')); } };
        animations.push(animation); return animation;
    } };
    motion.morph(element, '71.250');
    motion.morph(element, '9.566');
    await Promise.resolve(); await Promise.resolve(); await Promise.resolve();
    assert.equal(animations[0].cancelled, 1);
    assert.equal(motion.figureAnimations.get(element), animations[1]);
    assert.equal(element.textContent, '80.816');
    const releasedRoot = { querySelectorAll: () => [], contains: node => node === element };
    let expressiveRoot;
    motion.expressive = { release: root => { expressiveRoot = root; } };
    motion.release(releasedRoot);
    assert.equal(expressiveRoot, releasedRoot);
    await Promise.resolve(); await Promise.resolve(); await Promise.resolve();
    assert.equal(animations[1].cancelled, 1);
    assert.equal(motion.figureAnimations.size, 0);
    assert.equal(element.textContent, '80.816');
});

test('a changed motion preference cancels pending ownership and commits its requested map or detail view', async () => {
    for (const destination of ['detail', 'map']) {
        const journey = Object.create(PolygonJourney.prototype);
        const task = new PanelTransition();
        let stale = 0;
        const pending = task.wait(10000).then(active => { if (active) stale++; });
        let focused = 0;
        const target = { focus: options => { assert.equal(options.preventScroll, true); focused++; } };
        const record = { view: destination === 'detail' ? 'map' : 'detail', slot: target, chapter: { isConnected: true, inert: false, querySelector: () => target } };
        const origin = record.view;
        let disposed = 0;
        task.cleanup(() => { disposed++; record.view = origin; });
        const calls = [];
        Object.assign(journey, { disposed: false, deck: { hidden: false, inert: false }, transition: task, transitionRecord: record, transitionDestination: destination,
            setView: (item, view) => { item.view = view; }, prepareMap: () => calls.push('prepareMap'), align: () => calls.push('align'),
            rescan: () => calls.push('rescan'), enforce: () => calls.push('enforce'), studio: { releaseSurface: () => calls.push('release'), scan: () => calls.push('scan') } });
        journey.finishTransition();
        await pending;
        assert.equal(stale, 0);
        assert.equal(task.active, false);
        assert.equal(disposed, 1);
        assert.equal(record.view, destination);
        assert.equal(journey.transition, null);
        assert.equal(journey.transitionRecord, null);
        assert.equal(journey.transitionDestination, null);
        assert.equal(focused, 1);
        assert.deepEqual(calls, destination === 'map' ? ['prepareMap', 'align', 'release', 'scan', 'rescan', 'enforce'] : ['align', 'release', 'scan', 'rescan', 'enforce']);
    }
});
