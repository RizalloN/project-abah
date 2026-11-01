import test from 'node:test';
import assert from 'node:assert/strict';
import { expressiveSequence, performanceSequence, ExpressiveMotion } from '../../resources/js/presentation/segments/expressive-motion.js';

const kinds = ['vessels', 'skyline', 'composition', 'opportunity', 'coverage', 'trend', 'slope', 'table', 'ledger', 'header', 'metrics', 'surface'];

test('Kinerja choreography targets only decorative material and preserves financial coordinate owners', () => {
    for (const theme of ['simpanan', 'pinjaman', 'kualitas', 'recovery']) for (const compact of [false, true]) {
        for (const kind of ['header', 'table', 'trend', 'composition']) {
            const plan = performanceSequence(kind, theme, compact);
            assert.ok(plan.length > 0);
            for (const spec of plan) {
                assert.ok(spec.duration >= 1000 && spec.duration <= 1800);
                assert.doesNotMatch(spec.selector, /__title|__center|__legend|__axis|\b(?:td|tr|th|text)\b/);
                const frames = typeof spec.keyframes === 'function' ? spec.keyframes({}, 0) : spec.keyframes;
                assert.ok(frames.length > 1);
                assert.ok(frames.every(frame => Object.keys(frame).every(key => ['transform', 'opacity', 'offset'].includes(key))));
            }
        }
    }
    assert.deepEqual(performanceSequence('composition', 'marketshare'), []);
    assert.deepEqual(performanceSequence('opportunity', 'simpanan'), []);
});

test('assemblies target graphic materials with bounded durations and visible compact depth', () => {
    for (const compact of [false, true]) for (const kind of kinds) {
        const plan = expressiveSequence(kind, compact);
        assert.ok(plan.length >= 2, kind);
        assert.ok(plan.some(spec => spec.duration >= 1200), kind);
        for (const spec of plan) {
            assert.ok(spec.duration >= 900 && spec.duration <= 1500, `${kind}: duration`);
            assert.doesNotMatch(spec.selector, /sg-figure|sg-block__title|sg-vessel__value|sg-vessel__label|sg-ts__legend|sg-opportunity__(?:field|x|y)|\btd\b|\btr\b|\bth\b/);
            if (spec.selector.includes('sg-orb')) assert.match(spec.selector, /> span$/);
        }
        const depth = plan[0].keyframes[0].transform;
        assert.match(depth, /perspective\(1100px\).*rotateX\(42deg\)/);
        assert.match(depth, compact ? /-80px/ : /-180px/);
    }
});

const fixtures = (t) => {
    const previousDocument = globalThis.document;
    const trace = [];
    const created = [];
    globalThis.document = { createElement() {
        const attributes = new Map();
        const plane = { className: '', inert: false, attributes, setAttribute: (name, value) => attributes.set(name, value), innerHTML: '' };
        created.push(plane); return plane;
    } };
    const roots = [];
    const motion = new ExpressiveMotion({ settle: root => trace.push(`settle:${root.id}`) });
    t.after(() => { motion.finishAll(); if (previousDocument === undefined) delete globalThis.document; else globalThis.document = previousDocument; });
    const root = (id, kind = 'composition') => {
        const jobs = [];
        const styles = new Map();
        const classes = new Set();
        const element = { id, isConnected: true, blocked: false, dataset: { sgMotion: kind }, plane: null,
            style: { setProperty: (key, value) => styles.set(key, value) },
            classList: { add: key => classes.add(key), remove: key => classes.delete(key), contains: key => classes.has(key) },
            closest: () => element.blocked ? {} : null,
            querySelector: () => element.plane,
            prepend: plane => { element.plane = plane; },
            animate() { throw Error('A financial root must never receive the material animation'); },
            querySelectorAll(selector) { return selector.includes('__floor') ? [nodes.floor] : selector.includes('__light') ? [nodes.light] : selector.includes('__disc') ? [nodes.disc] : selector.includes('__layer') ? [nodes.layer] : []; },
        };
        const graphic = name => ({ isConnected: true, animate(frames, options) {
            let resolve, reject;
            const finished = new Promise((done, fail) => { resolve = done; reject = fail; });
            const job = { name, frames, options, finished, resolve, cancelled: 0, cancel() { this.cancelled++; trace.push(`cancel:${id}:${name}`); reject(Error('cancelled')); } };
            jobs.push(job); return job;
        } });
        const nodes = { floor: graphic('floor'), light: graphic('light'), disc: graphic('disc'), layer: graphic('layer') };
        Object.assign(element, { jobs, styles, classes, nodes }); roots.push(element); return element;
    };
    return { motion, root, trace, created, roots };
};
const visible = { top: 200, height: 500, viewport: 900, ceiling: 140, pass: .45, impulse: .8 };

test('inactive or invalid objects never start a hidden assembly', (t) => {
    const { motion, root } = fixtures(t);
    const element = root('hidden'); motion.scan([element]);
    for (const state of [{ active: false }, { top: NaN }, { height: 0 }, { viewport: 0 }, { top: 950 }, { top: -600 }]) motion.update(element, { ...visible, ...state });
    element.blocked = true; motion.update(element, visible);
    element.blocked = false; element.isConnected = false; motion.update(element, visible);
    assert.equal(element.jobs.length, 0);
    assert.equal(element.dataset.expressivePlayed, undefined);
});

test('a wheel jump directly into reading height still starts a timed assembly', (t) => {
    const { motion, root } = fixtures(t); const element = root('jump');
    element.dataset.readingSettled = 'true';
    motion.scan([element]); motion.update(element, visible);
    assert.equal(element.dataset.expressiveState, 'assembling');
    assert.equal(element.dataset.expressivePlayed, 'true');
    assert.ok(element.jobs.some(job => job.options.duration === 1500));
    assert.ok(element.jobs.some(job => job.frames.some(frame => frame.transform?.includes('rotateX(82deg)'))));
    assert.equal(element.styles.get('--expressive-pass'), '0.4500');
});

test('repeat scans and reverse scroll keep one plane and one entry owner', (t) => {
    const { motion, root, created } = fixtures(t); const element = root('once');
    motion.scan([element]); motion.update(element, visible);
    const jobs = element.jobs.length; const task = motion.records.get(element).task;
    motion.scan([element]); motion.update(element, { ...visible, top: 450, pass: .1 });
    assert.equal(created.length, 1);
    assert.equal(element.jobs.length, jobs);
    assert.equal(motion.records.get(element).task, task);
    motion.finishAll(); motion.update(element, visible);
    assert.equal(element.jobs.length, jobs);
    assert.equal(element.dataset.expressiveState, 'settled');
});

test('finish settles the source base before cancelling filled material animations', (t) => {
    const { motion, root, trace } = fixtures(t); const element = root('order');
    motion.scan([element]); motion.update(element, visible); trace.length = 0;
    const record = motion.records.get(element); motion.finish(record);
    assert.equal(trace[0], 'settle:order');
    assert.ok(trace.slice(1).every(item => item.startsWith('cancel:')));
    assert.ok(element.jobs.every(job => job.cancelled === 1));
    assert.equal(record.task, null);
    assert.equal(record.nodes.size, 0);
    assert.equal(element.dataset.expressiveState, 'settled');
    assert.equal(element.classes.has('is-expressive-entering'), false);
});

test('release cancels only roots owned by the replaced subtree', (t) => {
    const { motion, root } = fixtures(t); const a = root('a'), b = root('b');
    motion.scan([a, b]); motion.update(a, visible); motion.update(b, visible);
    const kept = motion.records.get(b).task;
    motion.release({ contains: node => node === a });
    assert.equal(motion.records.has(a), false);
    assert.equal(motion.records.get(b).task, kept);
    assert.ok(kept.active);
    assert.ok(a.jobs.every(job => job.cancelled === 1));
    assert.ok(b.jobs.every(job => job.cancelled === 0));
});

test('a replacement root owns a fresh assembly while disconnected jobs are discarded', (t) => {
    const { motion, root } = fixtures(t); const old = root('old');
    motion.scan([old]); motion.update(old, visible); old.isConnected = false;
    const replacement = root('replacement'); motion.scan([replacement]); motion.update(replacement, visible);
    assert.equal(motion.records.has(old), false);
    assert.ok(old.jobs.every(job => job.cancelled === 1));
    assert.equal(replacement.dataset.expressiveState, 'assembling');
    assert.ok(replacement.jobs.every(job => job.cancelled === 0));
});

test('reduced motion finishes owned material and clamps decorative input', (t) => {
    const { motion, root } = fixtures(t); const element = root('reduce');
    motion.scan([element]); motion.update(element, visible);
    motion.update(element, { ...visible, reduced: true, impulse: 99, pass: Infinity });
    assert.equal(motion.records.get(element).task, null);
    assert.ok(element.jobs.every(job => job.cancelled === 1));
    assert.equal(element.styles.get('--expressive-impulse'), '0');
    assert.equal(element.styles.get('--expressive-pass'), '0.0000');
    assert.equal(element.dataset.expressiveState, 'settled');
});

test('rapid interactions replace the old owner without a stale completion clearing the new one', async (t) => {
    const { motion, root } = fixtures(t); const element = root('rapid');
    motion.scan([element]); motion.update(element, visible);
    const first = motion.records.get(element).task; const count = element.jobs.length;
    const control = { closest: () => element };
    const target = { closest: selector => selector === '.sg-expressive-motion' ? {} : control };
    motion.interact(target); const second = motion.records.get(element).task;
    await Promise.resolve(); await Promise.resolve();
    assert.equal(first.active, false);
    assert.notEqual(second, first);
    assert.ok(second.active);
    assert.equal(motion.records.get(element).task, second);
    assert.ok(element.jobs.slice(0, count).every(job => job.cancelled === 1));
    assert.ok(element.jobs.slice(count).every(job => job.cancelled === 0));
});

test('without the animation API the actual data view settles immediately', (t) => {
    const { motion, root } = fixtures(t); const element = root('fallback');
    element.animate = undefined; element.textContent = 'Source figure 80.816';
    motion.scan([element]); motion.update(element, visible);
    assert.equal(element.dataset.expressiveState, 'settled');
    assert.equal(element.textContent, 'Source figure 80.816');
    assert.equal(element.jobs.length, 0);
    assert.equal(motion.records.get(element).task, null);
});

test('metric planes belong inside metric panels and remain decorative to assistive technology', (t) => {
    const { motion, root, created } = fixtures(t); const element = root('metrics', 'metrics');
    const panels = [0, 1].map(() => ({ plane: null, querySelector() { return this.plane; }, prepend(plane) { this.plane = plane; } }));
    element.querySelectorAll = selector => selector === ':scope > div' ? panels : [];
    motion.scan([element]);
    assert.equal(element.plane, null, 'Do not insert a span directly into a description list');
    assert.equal(created.length, 2);
    assert.ok(panels.every(panel => panel.plane.inert && panel.plane.attributes.get('aria-hidden') === 'true'));
    motion.scan([element]);
    assert.equal(created.length, 2);
});

test('early input takeover settles an unplayed object before the next visible frame', (t) => {
    const { motion, root } = fixtures(t); const element = root('early-focus');
    motion.scan([element]);
    assert.equal(element.dataset.expressiveState, 'waiting');
    motion.takeover(element);
    motion.update(element, visible);
    assert.equal(element.dataset.expressivePlayed, 'true');
    assert.equal(element.dataset.expressiveState, 'settled');
    assert.equal(element.jobs.length, 0);
    assert.equal(motion.records.get(element).task, null);
    const control = { closest: () => element };
    const target = { closest: selector => selector === '.sg-expressive-motion' ? {} : control };
    motion.interact(target);
    assert.equal(element.dataset.expressiveState, 'assembling');
    assert.ok(element.jobs.length > 0);
});

test('material reflections survive release and rescans without duplicating or replacing source children', (t) => {
    const { motion, root } = fixtures(t);
    for (const [kind, selector] of [['vessels', '.sg-vessel__tank'], ['skyline', '.sg-prism__front']]) {
        const element = root(kind, kind);
        const source = { textContent: 'BRI 11.380', value: 11380 };
        const host = { children: [source], querySelector() { return this.children.find(node => node.className === 'sg-material-glint'); }, append(node) { this.children.push(node); } };
        const query = element.querySelectorAll.bind(element);
        element.querySelectorAll = value => value === selector ? [host] : query(value);
        motion.scan([element]);
        const reflection = host.children[1];
        assert.equal(reflection.attributes.get('aria-hidden'), 'true');
        assert.equal(reflection.inert, true);
        motion.release({ contains: node => node === element });
        motion.scan([element]);
        assert.equal(host.children.length, 2);
        assert.equal(host.children[0], source);
        assert.equal(host.children[1], reflection);
        assert.deepEqual(source, { textContent: 'BRI 11.380', value: 11380 });
    }
});
