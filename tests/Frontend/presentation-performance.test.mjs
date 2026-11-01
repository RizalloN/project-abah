import test from 'node:test';
import assert from 'node:assert/strict';
import { PerformanceJourney, PERFORMANCE_KEYS, validPerformancePayload } from '../../resources/js/presentation/segments/performance-journey.js';
import { normalizeRoomState } from '../../resources/js/presentation/segments/presentation-room.js';

const payload = (key, available = true) => ({ available, scope: { key }, period: '2026-10-25', entities: [] });
const deferred = () => { let resolve; const promise = new Promise(done => { resolve = done; }); return { promise, resolve }; };

test('every Kinerja chapter uses explicit map/detail history while preserving KC identity', () => {
    for (const key of PERFORMANCE_KEYS) {
        assert.equal(normalizeRoomState({ key }, PERFORMANCE_KEYS).view, 'map');
        assert.equal(normalizeRoomState({ key, view: 'detail', branchKey: 'ngawi' }, PERFORMANCE_KEYS).branchKey, 'ngawi');
    }
});

test('a clamped scope or different effective period cannot become the selected KC', () => {
    assert.equal(validPerformancePayload(payload('madiun'), 'madiun', '2026-10-25'), true);
    assert.equal(validPerformancePayload(payload('ngawi'), 'madiun', '2026-10-25'), false);
    assert.equal(validPerformancePayload(payload('madiun'), 'madiun', '2026-09-30'), false);
    assert.equal(validPerformancePayload(null, 'madiun'), false);
    assert.equal(validPerformancePayload({ available: false, scope: { key: 'madiun' } }, 'madiun', '2026-10-25'), true);
    assert.equal(validPerformancePayload({ ...payload('madiun'), entities: null }, 'madiun'), false);
});

test('performance polygon resolves only an authorized KC, never the first outlet of a shared polygon', () => {
    const journey = Object.assign(Object.create(PerformanceJourney.prototype), { scope: { key: 'madiun', label: 'KC Madiun' }, index: { scopeCodes: key => key === 'area6' ? null : new Set([key]), codesOfBranch: key => new Set([key]) } });
    assert.equal(journey.selectionFor('ngawi'), null);
    assert.equal(journey.selectionFor('area6'), null);
    assert.deepEqual(journey.selectionFor('madiun').unitCodes, []);
    journey.scope = { key: 'area6', label: 'Area 6 Konsol' };
    assert.equal(journey.selectionFor('ponorogo').level, 'branch');
    assert.equal(journey.selectionFor('unknown'), null);
    assert.equal(journey.selectionFor('area6').label, 'Area 6 Konsol');
});

function harness(load) {
    const messages = [], applied = [], views = [];
    const record = { key: 'simpanan', view: 'map', chapter: { classList: { add() {}, remove() {} }, setAttribute() {}, removeAttribute() {} } };
    const journey = Object.assign(Object.create(PerformanceJourney.prototype), {
        requestId: 0, pending: null, transition: null, overview: payload('area6'), scope: { key: 'area6', label: 'Area 6 Konsol' },
        room: { main: { dataset: {} }, deck: { dataset: {} }, head: { querySelector: () => ({}) }, commit() {} },
        index: { scopeCodes: key => key === 'area6' ? null : new Set([key]), codesOfBranch: key => new Set([key]), branchOf: code => code }, load,
        available: () => true, paintHud() {}, prompt(_, message) { messages.push(message); },
        applySelection(_, selection, data) { applied.push({ selection, data }); },
        async moveRoom(_, destination) { views.push(destination); },
    });
    return { journey, record, messages, applied, views };
}

test('late KC response cannot overwrite a newer polygon choice', async () => {
    const a = deferred(), b = deferred();
    const h = harness(scope => scope === 'madiun' ? a.promise : b.promise);
    const first = h.journey.choose(h.record, { code: 'madiun' });
    const second = h.journey.choose(h.record, { code: 'ngawi' });
    b.resolve(payload('ngawi')); await second;
    a.resolve(payload('madiun')); await first;
    assert.deepEqual(h.applied.map(item => item.selection.branchKey), ['ngawi']);
    assert.deepEqual(h.views, ['detail']);
});

test('leaving a loading chapter cancels its commit and releases the loading state', async () => {
    const pending = deferred();
    const h = harness(() => pending.promise);
    const request = h.journey.choose(h.record, { code: 'madiun' });
    h.journey.cancelTransition();
    pending.resolve(payload('madiun')); await request;
    assert.equal(h.journey.pending, null);
    assert.deepEqual(h.applied, []);
    assert.deepEqual(h.views, []);
    assert.equal(h.journey.room.main.dataset.roomPhase, 'idle');
});

test('failed request leaves the existing map readable and permits retry', async t => {
    const original = console.warn;
    console.warn = () => {};
    t.after(() => { console.warn = original; });
    let failure = true;
    const h = harness(async () => { if (failure) return null; return payload('madiun'); });
    await h.journey.choose(h.record, { code: 'madiun' });
    assert.equal(h.applied.length, 0);
    assert.ok(h.messages.at(-1).includes('belum dapat dimuat'));
    assert.equal(h.record.view, 'map');
    failure = false;
    await h.journey.choose(h.record, { code: 'madiun' });
    assert.equal(h.applied.length, 1);
});

test('consolidation uses the actual Area payload rather than summing visible KC figures', async () => {
    const h = harness(() => { throw Error('Area overview should be reused'); });
    await h.journey.choose(h.record, { scopeKey: 'area6' });
    assert.equal(h.applied[0].data, h.journey.overview);
    assert.equal(h.applied[0].selection.label, 'Area 6 Konsol');
});

test('a stale restored history response cannot render into a topic that was left', async () => {
    const pending = deferred();
    const h = harness(() => pending.promise);
    const restoration = h.journey.restoreSelection(h.record, { branchKey: 'madiun' });
    h.journey.cancelTransition();
    pending.resolve(payload('madiun'));
    assert.equal(await restoration, false);
    assert.deepEqual(h.applied, []);
});

test('restored history rejects a clamped KC and a different effective source period', async () => {
    for (const response of [payload('ngawi'), { ...payload('madiun'), period: '2026-09-30' }]) {
        const h = harness(async () => response);
        assert.equal(await h.journey.restoreSelection(h.record, { branchKey: 'madiun' }), false);
        assert.deepEqual(h.applied, []);
        assert.deepEqual(h.views, []);
    }
});

test('restored history never requests an unauthorized branch', async () => {
    let requested = 0;
    const h = harness(async () => { requested++; return payload('ngawi'); });
    h.journey.scope = { key: 'madiun', label: 'KC Madiun' };
    assert.equal(await h.journey.restoreSelection(h.record, { branchKey: 'ngawi' }), false);
    assert.equal(requested, 0);
    assert.deepEqual(h.applied, []);
});

test('a rejected history request returns a recoverable result without an unhandled navigation rejection', async t => {
    const original = console.warn;
    console.warn = () => {};
    t.after(() => { console.warn = original; });
    const h = harness(async () => { throw Error('request interrupted'); });
    assert.equal(await h.journey.restoreSelection(h.record, { branchKey: 'madiun' }), false);
    assert.equal(h.journey.pending, null);
    assert.deepEqual(h.applied, []);
    assert.deepEqual(h.views, []);
});

test('hidden or inactive performance chapters cannot request or select a branch', async () => {
    let requested = 0;
    const h = harness(async () => { requested++; return payload('madiun'); });
    h.journey.available = () => false;
    await h.journey.choose(h.record, { code: 'madiun' });
    assert.equal(requested, 0);
    assert.deepEqual(h.applied, []);
    assert.equal(h.journey.pending, null);
});

test('a failed branch render releases loading ownership and allows the next choice', async t => {
    const original = console.warn;
    console.warn = () => {};
    t.after(() => { console.warn = original; });
    const h = harness(async key => payload(key));
    const apply = h.journey.applySelection;
    h.journey.applySelection = () => { throw Error('render interrupted'); };
    await h.journey.choose(h.record, { code: 'madiun' });
    assert.equal(h.journey.pending, null);
    assert.equal(h.journey.room.main.dataset.roomPhase, 'idle');
    assert.deepEqual(h.views, []);
    h.journey.applySelection = apply;
    await h.journey.choose(h.record, { code: 'ngawi' });
    assert.equal(h.applied[0].selection.branchKey, 'ngawi');
    assert.deepEqual(h.views, ['detail']);
});
