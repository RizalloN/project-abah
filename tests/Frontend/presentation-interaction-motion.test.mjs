import test from 'node:test';
import assert from 'node:assert/strict';
import { interactionPoint, InteractionMotion } from '../../resources/js/presentation/segments/interaction-motion.js';

test('pointer coordinates stay finite and clamp inside the stationary chart frame', () => {
    const rect = { left: 100, top: 200, width: 400, height: 200 };
    assert.deepEqual(interactionPoint(rect, 300, 300), { x: 0, y: 0, lightX: 50, lightY: 50 });
    assert.deepEqual(interactionPoint(rect, -20, 900), { x: -1, y: 1, lightX: 0, lightY: 100 });
    for (const value of [{ ...rect, width: 0 }, { ...rect, height: -1 }, { ...rect, left: NaN }, null]) assert.equal(interactionPoint(value, 0, 0), null);
    assert.equal(interactionPoint(rect, Infinity, 0), null);
});

const setup = (t) => {
    const original = globalThis.document;
    globalThis.document = { hidden: false, activeElement: null };
    t.after(() => { if (original === undefined) delete globalThis.document; else globalThis.document = original; });
    let fine = true, reduced = false, scheduled = 0, measurements = 0;
    const settlements = [];
    const root = { nodes: [], contains: node => root.nodes.includes(node), classList: { add() {} }, querySelectorAll: () => [] };
    const owner = new InteractionMotion({ root, schedule: () => scheduled++, settle: element => settlements.push(element), fine: () => fine, reduced: () => reduced });
    const make = () => {
        const values = new Map();
        const element = { isConnected: true, blocked: false, dataset: { sgInteraction: 'composition' }, style: { setProperty: (key, value) => values.set(key, value), removeProperty: key => values.delete(key) },
            closest: () => element.blocked ? {} : null, contains: node => node === element || node === item,
            getBoundingClientRect: () => { measurements++; return { left: 100, top: 200, width: 400, height: 200 }; }, values };
        const itemValues = new Map();
        const item = { isConnected: true, dataset: {}, style: { setProperty: (key, value) => itemValues.set(key, value), removeProperty: key => itemValues.delete(key) }, values: itemValues, closest: () => element };
        const target = { closest: selector => selector === '[data-sg-interaction]' ? element : item, matches: selector => selector === ':focus-visible' };
        root.nodes.push(element, item, target);
        return { element, item, target };
    };
    return { owner, root, make, settlements, setFine: value => { fine = value; }, setReduced: value => { reduced = value; }, scheduled: () => scheduled, measurements: () => measurements };
};
const mouse = (target, x = 200, y = 250) => ({ target, clientX: x, clientY: y, pointerType: 'mouse', button: 0 });

test('pointer bursts queue only the latest position and measure once on the shared update', (t) => {
    const s = setup(t), { element, target } = s.make();
    s.owner.pointer(mouse(target, 110, 210)); s.owner.pointer(mouse(target, 490, 390));
    assert.equal(s.measurements(), 0);
    assert.equal(s.scheduled(), 2);
    assert.equal(s.owner.update(20), true);
    assert.equal(s.measurements(), 1);
    assert.equal(s.owner.active.targetX, .95);
    assert.ok(Math.abs(s.owner.active.targetY - .9) < 1e-12);
    assert.equal(element.dataset.sgEngagement, 'pointer');
    assert.ok(Number.isFinite(+element.values.get('--sg-pointer-x')));
});

test('hover is unavailable for touch, coarse pointers, reduced motion and hidden documents', (t) => {
    const s = setup(t), { target } = s.make();
    for (const blocked of ['touch', 'coarse', 'reduced', 'hidden']) {
        s.setFine(blocked !== 'coarse'); s.setReduced(blocked === 'reduced'); document.hidden = blocked === 'hidden';
        s.owner.pointer({ ...mouse(target), pointerType: blocked === 'touch' ? 'touch' : 'mouse' });
        assert.equal(s.owner.pending, null, blocked);
        assert.equal(s.owner.active, null, blocked);
    }
});

test('a takeover settles the assembly once and keeps hover on local material metadata', (t) => {
    const s = setup(t), { element, item, target } = s.make();
    s.owner.pointer(mouse(target)); s.owner.update(0); s.owner.pointer(mouse(target, 450, 350)); s.owner.update(30);
    assert.deepEqual(s.settlements, [element]);
    assert.equal(item.dataset.sgHot, 'true');
    assert.equal(element.dataset.sgEngagement, 'pointer');
    assert.equal(Object.hasOwn(element, 'transform'), false);
    assert.equal(Object.hasOwn(item, 'transform'), false);
});

test('keyboard focus uses a centered owner and settles without pointer drift', (t) => {
    const s = setup(t), { element, target } = s.make();
    s.owner.focus(target); s.owner.update(20);
    assert.equal(s.owner.active.mode, 'focus');
    assert.equal(s.owner.active.x, 0); assert.equal(s.owner.active.y, 0);
    assert.equal(element.values.get('--sg-light-x'), '50.00%');
    assert.equal(element.values.get('--sg-light-y'), '50.00%');
    const notVisible = { matches: () => false };
    s.owner.clear(); s.owner.focus(notVisible); assert.equal(s.owner.active, null);
});

test('touch press feedback is centered and pointerup clears every hover marker', (t) => {
    const s = setup(t), { element, item, target } = s.make();
    s.owner.press({ ...mouse(target), pointerType: 'touch' }); s.owner.update(20);
    assert.equal(s.owner.active.mode, 'press');
    assert.equal(element.values.get('--sg-press'), '1');
    assert.equal(element.values.get('--sg-pointer-x'), '0.0000');
    s.owner.unpress();
    assert.equal(s.owner.active, null); assert.equal(s.owner.pending, null);
    assert.equal(element.values.size, 0); assert.equal(item.values.size, 0);
    assert.equal(element.dataset.sgEngagement, undefined); assert.equal(item.dataset.sgHot, undefined);
});

test('leave within a chart keeps ownership, leaving the chart resets it', (t) => {
    const s = setup(t), { element, item, target } = s.make();
    s.owner.pointer(mouse(target)); s.owner.update(20);
    s.owner.leave({ relatedTarget: item }); assert.equal(s.owner.active.element, element);
    s.owner.leave({ relatedTarget: null }); assert.equal(s.owner.active, null);
    assert.equal(element.values.size, 0); assert.equal(item.values.size, 0);
});

test('detach, hidden layout and reduced-motion changes clear the active frame', (t) => {
    const s = setup(t);
    for (const reason of ['detach', 'blocked', 'itemDetach', 'reduce', 'documentHidden']) {
        s.setReduced(false); document.hidden = false;
        const { element, item, target } = s.make();
        s.owner.pointer(mouse(target)); s.owner.update(20);
        if (reason === 'detach') element.isConnected = false;
        if (reason === 'blocked') element.blocked = true;
        if (reason === 'itemDetach') item.isConnected = false;
        if (reason === 'reduce') s.setReduced(true);
        if (reason === 'documentHidden') document.hidden = true;
        assert.equal(s.owner.update(40), false, reason);
        assert.equal(s.owner.active, null, reason);
        assert.equal(element.values.size, 0, reason);
    }
});

test('subtree release clears only that owned chart, and destroy is idempotent', (t) => {
    const s = setup(t), a = s.make(), b = s.make();
    s.owner.pointer(mouse(a.target)); s.owner.update(0);
    s.owner.release({ contains: node => node === b.element }); assert.equal(s.owner.active.element, a.element);
    s.owner.release({ contains: node => node === a.element }); assert.equal(s.owner.active, null);
    let aborted = 0, disconnected = 0;
    s.owner.controller = { abort: () => aborted++ }; s.owner.observer = { disconnect: () => disconnected++ };
    s.owner.destroy(); s.owner.destroy();
    assert.equal(aborted, 1); assert.equal(disconnected, 1);
});

test('repeated updates stop requesting frames after bounded damping converges', (t) => {
    const s = setup(t), { target, element } = s.make();
    s.owner.pointer(mouse(target, 450, 330)); let moving = true;
    for (let now = 0; now <= 1600; now += 17) moving = s.owner.update(now);
    assert.equal(moving, false);
    assert.ok(Math.abs(+element.values.get('--sg-pointer-x') - .75) < .002);
    s.owner.clear(); assert.equal(s.owner.update(1700), false);
});

test('focus auto-scroll preserves the keyboard owner while pointer scrolling clears it', (t) => {
    const s = setup(t), { target, element } = s.make();
    const originalWindow = globalThis.window;
    const handlers = new Map();
    const listen = (type, fn) => handlers.set(type, fn);
    globalThis.window = { addEventListener: listen, matchMedia: () => ({ addEventListener() {} }) };
    document.addEventListener = () => {};
    s.root.addEventListener = () => {};
    t.after(() => { if (originalWindow === undefined) delete globalThis.window; else globalThis.window = originalWindow; });
    s.owner.init(); s.owner.focus(target); s.owner.update(20); document.activeElement = target;
    handlers.get('scroll')();
    assert.equal(s.owner.active?.mode, 'focus');
    assert.equal(s.owner.active?.element, element);
    s.owner.clear(); s.owner.pointer(mouse(target)); s.owner.update(40);
    handlers.get('scroll')();
    assert.equal(s.owner.active, null);
});

test('initialization is idempotent and document visibility clears the live owner', (t) => {
    const s = setup(t), { target } = s.make();
    const originalWindow = globalThis.window;
    const counts = new Map(), documentEvents = new Map();
    globalThis.window = { addEventListener: type => counts.set(type, (counts.get(type) || 0) + 1), matchMedia: () => ({ addEventListener() {} }) };
    document.addEventListener = (type, callback) => documentEvents.set(type, callback);
    s.root.addEventListener = type => counts.set(type, (counts.get(type) || 0) + 1);
    t.after(() => { if (originalWindow === undefined) delete globalThis.window; else globalThis.window = originalWindow; });
    s.owner.init(); s.owner.init();
    assert.equal(counts.get('pointermove'), 1);
    assert.equal(counts.get('pointercancel'), 1);
    s.owner.pointer(mouse(target)); s.owner.update(20);
    assert.ok(s.owner.active);
    document.hidden = true; documentEvents.get('visibilitychange')();
    assert.equal(s.owner.active, null);
    assert.equal(s.owner.pending, null);
});
