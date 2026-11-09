import test from 'node:test';
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';
import { deviceProfile, FrameBudget } from '../../resources/js/presentation/segments/device-runtime.js';
import { TerritoryStudio } from '../../resources/js/presentation/worlds/territory-studio.js';
import { ReliefScene } from '../../resources/js/presentation/worlds/relief.js';
import { SegmentMotion } from '../../resources/js/presentation/segments/motion.js';

test('device fidelity bounds GPU pixels while retaining the same responsive animation cadence', () => {
    const desktop = deviceProfile({ width: 1920, memory: 8, cores: 12 });
    const phone = deviceProfile({ width: 390, coarse: true, memory: 8, cores: 8 });
    const constrained = deviceProfile({ width: 1920, memory: 4 });
    assert.ok(phone.maxPixels < desktop.maxPixels);
    assert.ok(constrained.maxPixels < desktop.maxPixels);
    assert.equal(desktop.activeFps, phone.activeFps);
    assert.equal(constrained.activeFps, 60);
    assert.equal(phone.shadowSize, 1024);
    assert.equal(deviceProfile({ reduced: true }).ambientFps, 0);
    assert.equal(deviceProfile({ saveData: true }).maxPixels, constrained.maxPixels);
});

test('absent device hints retain desktop fidelity and landscape touch stays compact', () => {
    assert.equal(deviceProfile().maxPixelRatio, 1.75);
    assert.equal(deviceProfile({ width: 1200, coarse: true }).shadowSize, 1024);
    assert.equal(deviceProfile({ cores: 2 }).maxPixelRatio, 1.25);
});

test('slow active frames lower pixel resolution in bounded steps, never authoring a fake frame rate', () => {
    const budget = new FrameBudget();
    for (let window = 0; window < 5; window++) {
        for (let sample = 0; sample < 12; sample++) budget.observe(48, true);
    }
    assert.equal(budget.scale, .75);
    assert.equal(budget.observe(100, true), false);
});

test('ambient delays, hidden gaps and occasional outliers do not lower quality', () => {
    const budget = new FrameBudget();
    for (let i = 0; i < 40; i++) budget.observe(2000, false);
    for (let i = 0; i < 24; i++) budget.observe(i % 6 === 0 ? 90 : 16.7, true);
    assert.equal(budget.scale, 1);
    budget.observe(90, true);
    budget.reset();
    assert.equal(budget.samples, 0);
    assert.equal(budget.scale, 1);
});

test('studio scheduling owns at most one wakeup and input replaces the ambient wait immediately', () => {
    const oldWindow = globalThis.window;
    const oldDocument = globalThis.document;
    const timers = new Map();
    const frames = new Map();
    let id = 0;
    globalThis.document = { hidden: false };
    globalThis.window = {
        setTimeout(fn) { timers.set(++id, fn); return id; },
        clearTimeout(key) { timers.delete(key); },
        requestAnimationFrame(fn) { frames.set(++id, fn); return id; },
    };
    try {
        const studio = new TerritoryStudio({ geographyUrl: '/geo' });
        studio.kick(80);
        studio.kick(80);
        assert.equal(timers.size, 1);
        studio.kick();
        studio.kick();
        assert.equal(timers.size, 0);
        assert.equal(frames.size, 1);
        studio.frame = null;
        frames.clear();
        document.hidden = true;
        studio.kick(80);
        studio.kick();
        assert.equal(timers.size + frames.size, 0);
    } finally {
        globalThis.window = oldWindow;
        globalThis.document = oldDocument;
    }
});

test('hover-only frames avoid report layout reads and a pending scroll still gets processed once', () => {
    const oldWindow = globalThis.window, oldDocument = globalThis.document;
    const frames = [];
    globalThis.window = { requestAnimationFrame(fn) { frames.push(fn); return frames.length; } };
    globalThis.document = { hidden: false };
    try {
        const motion = Object.create(SegmentMotion.prototype);
        let reads = 0, updates = 0;
        Object.assign(motion, { frame: null, readDirty: false, layoutDirty: false, read() { reads++; }, interaction: { update() { updates++; return false; } } });
        motion.schedule(false);
        frames.shift()(16);
        assert.equal(reads, 0);
        motion.schedule(false);
        motion.schedule(true);
        motion.schedule(false);
        assert.equal(frames.length, 1);
        frames.shift()(32);
        assert.equal(reads, 1);
        assert.equal(updates, 2);
    } finally { globalThis.window = oldWindow; globalThis.document = oldDocument; }
});

test('async shader preparation compiles material families sequentially and restores the stable projection', async () => {
    const piece = { glass: 'glass', metal: 'metal', mesh: {}, core: {} };
    const calls = [];
    const scene = { pieces: [piece], renderer: { async compileAsync() { calls.push([piece.mesh.material, piece.core.visible]); } } };
    await ReliefScene.prototype.precompileAsync.call(scene);
    assert.deepEqual(calls, [['glass', true], ['metal', false]]);
    assert.equal(scene.compiled, true);
    assert.equal(piece.mesh.material, 'metal');
    assert.equal(piece.core.visible, false);
});

test('failed shader preparation restores materials without marking the scene ready', async () => {
    const piece = { glass: 'glass', metal: 'metal', mesh: {}, core: {} };
    const scene = { pieces: [piece], renderer: { async compileAsync() { throw new Error('GPU unavailable'); } } };
    await assert.rejects(ReliefScene.prototype.precompileAsync.call(scene), /GPU unavailable/);
    assert.ok(!scene.compiled);
    assert.equal(piece.mesh.material, 'metal');
    assert.equal(piece.core.visible, false);
});

test('a lost WebGL context hands the frame to the poster, ends the flight and lets focus waits finish', () => {
    const oldWindow = globalThis.window, oldDocument = globalThis.document;
    const classes = new Set(['sg-relief-on', 'sg-relief-live']);
    const classList = {
        add: (name) => classes.add(name), remove: (...names) => names.forEach((name) => classes.delete(name)),
        replace: (from, to) => { if (classes.delete(from)) classes.add(to); }, contains: (name) => classes.has(name),
    };
    globalThis.window = { setTimeout: () => 1, clearTimeout() {}, requestAnimationFrame: () => 1, cancelAnimationFrame() {} };
    globalThis.document = { hidden: false, documentElement: { classList }, querySelectorAll: () => [] };
    try {
        const studio = new TerritoryStudio({ geographyUrl: '/geo' });
        let flightEnded = 0;
        Object.assign(studio, { webgl: true, canvas: { style: {} }, scene: { endFlight() { flightEnded++; } } });
        studio.flightP = 0.2;
        studio.states.set('flight', {});
        studio.dropScene();
        assert.equal(studio.scene, null);
        assert.equal(studio.canFly(), false, 'the cinema stops flying over a dead canvas');
        assert.equal(flightEnded, 1);
        assert.equal(studio.flightP, null);
        assert.equal(studio.states.has('flight'), false);
        assert.equal(studio.webgl, false, 'the poster is the completed map until a rebuilt scene is live');
        assert.equal(studio.canvas.style.display, 'none');
        assert.ok(classes.has('sg-relief-off') && !classes.has('sg-relief-live') && !classes.has('sg-relief-on'));
        studio.dropScene();
        assert.equal(flightEnded, 1, 'a second loss event is a no-op');
    } finally { globalThis.window = oldWindow; globalThis.document = oldDocument; }
});

test('the relief canvas keeps the context restorable and rebuilds a bounded number of times', () => {
    const studio = readFileSync(new URL('../../resources/js/presentation/worlds/territory-studio.js', import.meta.url), 'utf8');
    assert.match(studio, /addEventListener\('webglcontextlost', \(event\) => \{\s*event\.preventDefault\(\);\s*if \(this\.canvas === canvas\) this\.dropScene\(\);/);
    assert.match(studio, /addEventListener\('webglcontextrestored'[\s\S]{0,160}if \(\+\+this\.rebuilds <= 2\) this\.buildScene\(\)/);
    assert.match(studio, /isContextLost\(\)\) throw/, 'a context lost while compiling never goes live');
});
