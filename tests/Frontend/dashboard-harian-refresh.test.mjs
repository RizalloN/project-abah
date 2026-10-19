import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const flush = async () => { for (let i = 0; i < 4; i++) await new Promise(setImmediate); };

function harness(kind) {
    const keragaan = kind === 'keragaan';
    const path = keragaan ? 'dashboard-harian-keragaan-uker' : 'dashboard-harian';
    const source = readFileSync(new URL(`../../resources/views/report/${path}.blade.php`, import.meta.url), 'utf8');
    const start = keragaan ? '        function currentQuery()' : '        const updateFollowLatest = function ()';
    const end = keragaan ? '        function renderCurrentWithScrollPreserved()' : '        const refreshUnitOptions = function ()';
    const block = source.slice(source.indexOf(start), source.indexOf(end));
    assert.ok(block.length > 1000);
    const calls = [];
    const painted = [];
    const classes = new Set();
    const classList = { add: value => classes.add(value), remove: value => classes.delete(value), contains: value => classes.has(value) };
    const period = { value: '2026-10-16', max: '2026-10-17' };
    const state = { unit_kerja: 'all', posisi_terakhir: period.value };
    const context = {
        URLSearchParams, AbortController, Boolean,
        console: { error() {} },
        followLatest: true, lastAppliedQuery: null, activeRequest: null, latestRequestId: 0,
        document: { hidden: false },
        selects: { posisi_terakhir: period },
        els: { kanca: { value: 'area6' }, unit: { value: 'all' }, dataType: { value: 'pinjaman' }, period, rka: { value: '' }, empty: { textContent: '' } },
        selectedKancaValue: 'area6', page: { routes: { data: '/data' } },
        dataUrl: '/data', tableRegion: { classList }, shell: { classList },
        applyButton: { disabled: false, innerHTML: 'Terapkan' },
        body: { innerHTML: 'valid table' }, positionH1Header: null,
        buildQueryParams: () => new URLSearchParams({ ...state, posisi_terakhir: period.value }),
        applyPayload: payload => { painted.push(payload); period.value = payload.period; },
        buildSelects: payload => { period.value = payload.period; },
        render: payload => { painted.push(payload); },
        fetch: (url, options) => new Promise((resolve, reject) => calls.push({ url, options, resolve: payload => resolve({ ok: true, headers: { get: () => 'application/json' }, json: async () => payload }), reject })),
    };
    const api = vm.runInNewContext(`${block}\n({ fetchData, key: () => ${keragaan ? 'currentQuery().toString()' : 'filterQueryKey()'}, chooseDate: () => ${keragaan ? '(followLatest = Boolean(els.period.max && els.period.value === els.period.max))' : 'updateFollowLatest()'} })`, context);
    context.lastAppliedQuery = api.key();
    const refresh = () => keragaan ? api.fetchData(false, true) : api.fetchData(true);
    const manual = () => keragaan ? api.fetchData(false) : api.fetchData();
    const changeUnit = value => { state.unit_kerja = value; context.els.unit.value = value; };
    return { context, calls, painted, period, classes, refresh, manual, changeUnit, api };
}

for (const kind of ['harian', 'keragaan']) {
    test(`${kind}: complete inline scripts have valid JavaScript syntax`, () => {
        const file = kind === 'keragaan' ? 'dashboard-harian-keragaan-uker' : 'dashboard-harian';
        const source = readFileSync(new URL(`../../resources/views/report/${file}.blade.php`, import.meta.url), 'utf8');
        const scripts = Array.from(source.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g));
        assert.ok(scripts.length > 0);
        scripts.forEach((match, index) => {
            const script = match[1].replace(/@json\([^\n]+\);/g, '{};');
            assert.doesNotThrow(() => new vm.Script(script, { filename: `${file}:${index}` }));
        });
    });

    test(`${kind}: background follows latest and keeps displayed data on failure`, async () => {
        const h = harness(kind);
        h.refresh();
        assert.equal(new URL(h.calls[0].url, 'http://localhost').searchParams.get('follow_latest'), '1');
        assert.equal(h.calls[0].options.cache, 'no-store');
        assert.equal(h.classes.size, 0, 'background refresh must not show a loading overlay');
        h.calls[0].resolve({ period: '2026-10-17' });
        await flush();
        assert.equal(h.period.value, '2026-10-17');
        h.refresh();
        h.calls[1].reject(new Error('offline'));
        await flush();
        assert.equal(h.painted.length, 1);
        assert.equal(h.context.body.innerHTML, 'valid table');
        assert.equal(h.classes.size, 0);
    });

    test(`${kind}: hidden tabs and unapplied filters do not refresh`, async () => {
        const h = harness(kind);
        h.context.document.hidden = true;
        h.refresh();
        assert.equal(h.calls.length, 0);
        h.context.document.hidden = false;
        h.changeUnit('123');
        h.refresh();
        assert.equal(h.calls.length, 0);
        h.manual();
        h.calls[0].resolve({ period: '2026-10-17' });
        await flush();
        h.refresh();
        assert.equal(h.calls.length, 2);
        h.calls[1].resolve({ period: '2026-10-17' });
        await flush();
    });

    test(`${kind}: manually selected history stays pinned; selecting latest resumes follow`, async () => {
        const h = harness(kind);
        h.period.value = '2026-10-15';
        h.api.chooseDate();
        h.manual();
        assert.equal(new URL(h.calls[0].url, 'http://localhost').searchParams.has('follow_latest'), false);
        h.calls[0].resolve({ period: '2026-10-15' });
        await flush();
        h.refresh();
        assert.equal(h.calls.length, 1);
        h.period.value = h.period.max;
        h.api.chooseDate();
        h.manual();
        assert.equal(new URL(h.calls[1].url, 'http://localhost').searchParams.get('follow_latest'), '1');
        h.calls[1].resolve({ period: h.period.max });
        await flush();
    });

    test(`${kind}: newer requests and filter edits prevent older responses repainting`, async () => {
        const h = harness(kind);
        h.refresh();
        h.refresh();
        assert.equal(h.calls.length, 1, 'background requests must not overlap');
        h.changeUnit('123');
        h.manual();
        assert.equal(h.calls[0].options.signal.aborted, true);
        h.calls[1].resolve({ period: '2026-10-17' });
        await flush();
        h.calls[0].resolve({ period: '2026-10-16' });
        await flush();
        assert.deepEqual(h.painted.map(payload => payload.period), ['2026-10-17']);
        h.refresh();
        h.changeUnit('456');
        h.calls[2].resolve({ period: '2026-10-18' });
        await flush();
        assert.equal(h.period.value, '2026-10-17', 'an in-flight refresh must not overwrite filter edits');
    });
}
