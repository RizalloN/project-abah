import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeRoomState, showOnlyRoom, PresentationRoom, topicContext } from '../../resources/js/presentation/segments/presentation-room.js';
import { TerritoryStudio } from '../../resources/js/presentation/worlds/territory-studio.js';

test('topic navigation carries a chosen KC or Area but drops unit and district detail', () => {
    for (const branchKey of ['madiun', 'area6']) {
        const state = { key: 'mapping', view: 'detail', branchKey, unitCode: '1234', districtCode: 'A' };
        assert.deepEqual(topicContext(state, 'simpanan', ['mapping', 'simpanan']), { key: 'simpanan', view: 'detail', branchKey });
        assert.equal(state.unitCode, '1234');
    }
});

test('map browsing, repeated topic choice and excluded topics never imply a KC selection', () => {
    const state = { key: 'mapping', view: 'detail', branchKey: 'madiun' };
    assert.equal(topicContext({ ...state, view: 'map' }, 'simpanan', ['mapping', 'simpanan']), null);
    assert.equal(topicContext(state, 'mapping', ['mapping', 'simpanan']), null);
    assert.equal(topicContext(state, 'recovery', ['mapping', 'simpanan']), null);
    assert.equal(topicContext(null, 'simpanan', ['simpanan']), null);
});

test('history accepts only selected topics and retains branch detail for geographic and performance pages', () => {
    const keys = ['mapping', 'pinjaman'];
    assert.equal(normalizeRoomState({ key: 'sektoral' }, keys), null);
    assert.deepEqual(normalizeRoomState({ key: 'mapping', view: 'invalid', branchKey: {}, unitCode: 123 }, keys), {
        key: 'mapping', view: 'map', branchKey: null, unitCode: null, districtCode: null,
    });
    assert.deepEqual(normalizeRoomState({ key: 'pinjaman', view: 'detail', branchKey: 'madiun' }, keys), {
        key: 'pinjaman', view: 'detail', branchKey: 'madiun', unitCode: null, districtCode: null,
    });
});

test('repeated room switching leaves one layout and keyboard owner, retaining the source nodes', () => {
    const nodes = ['marketshare', 'mapping', 'sektoral', 'close'].map(key => ({ dataset: { sgSegment: key }, attributes: {}, setAttribute(k, v) { this.attributes[k] = v; } }));
    const original = [...nodes];
    for (const key of ['marketshare', 'mapping', 'sektoral', 'marketshare', null, 'mapping']) {
        showOnlyRoom(nodes, key);
        assert.deepEqual(nodes.filter(n => !n.hidden).map(n => n.dataset.sgSegment), key ? [key] : []);
        for (const node of nodes) {
            assert.equal(node.inert, node.hidden);
            assert.equal(node.attributes['aria-hidden'], String(node.hidden));
            assert.equal(node.dataset.roomActive, node.hidden ? undefined : 'true');
        }
    }
    nodes.forEach((node, i) => assert.equal(node, original[i]));
});

test('room history preserves foreign state and an explicit map parent for browser Back', t => {
    const previous = globalThis.window;
    t.after(() => { globalThis.window = previous; });
    const entries = [];
    const history = { state: { outside: 'retained' }, pushState(value, _, url) { this.state = value; entries.push(value); window.location.href = String(url); }, replaceState(value, _, url) { this.state = value; window.location.href = String(url); } };
    globalThis.window = { location: { href: 'http://localhost/presentation?scope=madiun&segmen=mapping' }, history };
    const room = Object.assign(Object.create(PresentationRoom.prototype), { signature: 'madiun:mapping' });
    room.writeHistory({ key: 'mapping', view: 'map' }, 'replace');
    room.writeHistory({ key: 'mapping', view: 'detail', branchKey: 'madiun' });
    assert.equal(history.state.outside, 'retained');
    assert.deepEqual(history.state.asixPresentationRoom.parent, { key: 'mapping', view: 'map', signature: room.signature });
    assert.match(window.location.href, /scope=madiun/);
    assert.match(window.location.href, /kc=madiun/);
    room.writeHistory({ key: 'mapping', view: 'map' }, 'replace');
    assert.ok(!window.location.href.includes('kc='));
    assert.equal(entries.length, 1);
});

test('Back during camera travel reverses the owner; stable detail uses its map history parent', () => {
    const previous = globalThis.window;
    let historyBack = 0, reverse = 0, launcher = 0;
    globalThis.window = { history: { state: { asixPresentationRoom: { parent: { signature: 's', key: 'mapping', view: 'map' } } }, back() { historyBack++; } } };
    try {
        const room = Object.assign(Object.create(PresentationRoom.prototype), { signature: 's', activeKey: 'mapping', state: { view: 'detail' }, main: { dataset: { roomPhase: 'zoom-in' } }, onBack() { reverse++; }, onLauncher() { launcher++; } });
        room.back();
        room.main.dataset.roomPhase = 'idle';
        room.back();
        room.state.view = 'map';
        room.back();
        assert.deepEqual([reverse, historyBack, launcher], [1, 1, 1]);
    } finally { globalThis.window = previous; }
});

function camera(t) {
    const previous = globalThis.document;
    globalThis.document = { hidden: false };
    t.after(() => { globalThis.document = previous; });
    const studio = new TerritoryStudio({ geographyUrl: '/unused' });
    studio.kick = () => {};
    studio.states.set('mapping', {});
    const slot = { isConnected: true, closest: () => null };
    const target = { x: 0, y: 0, w: 800, h: 600 };
    studio.activeSlot = slot;
    studio.rect = { ...target };
    studio.appliedKey = 'mapping|0|';
    studio.blur = 0;
    studio.swoop = 0;
    const arrangement = { explode: 0, spread: 0, levitate: 0, levitateBranch: null, grow: 1, wave: 0 };
    studio.scene = { view: { x: 0, z: 0, radius: 10 }, viewGoal: { x: 0, z: 0, radius: 10 }, zooming: 0, arrangement: { ...arrangement }, arrangementTarget: { ...arrangement } };
    return { studio, frame: { key: 'mapping', slot, target, rendered: true, now: performance.now() + 1000 } };
}

test('camera completion requires three matching settled frames, not a focus event or timer', async t => {
    const { studio, frame } = camera(t);
    const pending = studio.waitForFocus('mapping', { minimum: 0 });
    studio.scene.view.x = 5;
    studio.checkFocusFrame(frame);
    assert.equal(studio.focusWait.stable, 0);
    studio.scene.view.x = 0;
    studio.checkFocusFrame(frame);
    studio.checkFocusFrame(frame);
    assert.ok(studio.focusWait);
    studio.checkFocusFrame(frame);
    assert.equal(await pending, true);
    assert.equal(studio.focusWait, null);
});

test('a changed selection, hidden page and aborted owner cannot complete stale camera travel', async t => {
    const { studio, frame } = camera(t);
    const first = studio.waitForFocus('mapping', { minimum: 0 });
    studio.overrides.set('mapping', new Set(['new']));
    studio.checkFocusFrame(frame);
    assert.equal(await first, false);
    const second = studio.waitForFocus('mapping');
    document.hidden = true;
    studio.checkFocusFrame(frame);
    assert.equal(await second, false);
    document.hidden = false;
    const controller = new AbortController();
    const third = studio.waitForFocus('mapping', { signal: controller.signal });
    controller.abort();
    assert.equal(await third, false);
    assert.equal(studio.focusWait, null);
});

test('new camera waits cancel the old owner and a bounded timeout reports incomplete travel', async t => {
    const { studio } = camera(t);
    const first = studio.waitForFocus('mapping');
    const second = studio.waitForFocus('mapping', { minimum: 0, maximum: 5 });
    assert.equal(await first, false);
    assert.equal(await second, false);
    assert.equal(studio.focusWait, null);
});

test('SVG completion needs a rendered valid poster; pending WebGL is not a fallback', async t => {
    const { studio, frame } = camera(t);
    studio.scene = null;
    studio.framePoster = () => {};
    frame.slot.querySelector = () => ({ viewBox: { baseVal: { x: 0, y: 0, width: 600, height: 560 } } });
    const pending = studio.waitForFocus('mapping');
    studio.webgl = true;
    studio.checkFocusFrame(frame);
    assert.ok(studio.focusWait);
    studio.webgl = false;
    studio.checkFocusFrame(frame);
    assert.equal(await pending, true);
});
