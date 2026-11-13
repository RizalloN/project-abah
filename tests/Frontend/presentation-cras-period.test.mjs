import test from 'node:test';
import assert from 'node:assert/strict';
import { bindSubsectors } from '../../resources/js/presentation/segments/cras-subsectors.js';

class Element {
    value = '';
    listeners = new Map();
    children = new Map();
    querySelector(selector) {
        if (!this.children.has(selector)) this.children.set(selector, new Element());
        return this.children.get(selector);
    }
    setAttribute() {}
    addEventListener(name, callback) { this.listeners.set(name, callback); }
}

test('CRAS initial load and retry resolve latest data despite stale deck and wilayah periods', async (t) => {
    const originalWindow = globalThis.window;
    const originalFetch = globalThis.fetch;
    t.after(() => {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
        globalThis.fetch = originalFetch;
    });
    globalThis.window = { location: { origin: 'https://abah.test' }, setTimeout, clearTimeout };
    const requests = [];
    globalThis.fetch = async (url) => {
        requests.push(new URL(url));
        return { ok: true, json: async () => ({ ready: false }) };
    };
    const root = new Element();
    const form = root.querySelector('[data-sg-cras]').querySelector('form');
    form.elements = { sektor: new Element(), sub_sektor: new Element() };
    const dispose = bindSubsectors(root, {
        crasUrl: '/cras/data?periode=2026-09-30', period: '2026-09-30',
        wilayah: { sektoral: { period: '2026-09-30' } },
        scope: { key: 'madiun', label: 'KC Madiun' },
        presentationState: {},
    }, {});
    t.after(dispose);
    await new Promise(setImmediate);
    form.listeners.get('submit')({ preventDefault() {} });
    await new Promise(setImmediate);
    assert.equal(requests.length, 2);
    for (const request of requests) {
        assert.equal(request.searchParams.has('periode'), false);
        assert.equal(request.searchParams.get('wilayah'), 'madiun');
        assert.equal(request.searchParams.get('sektor'), 'all');
        assert.equal(request.searchParams.get('sub_sektor'), 'all');
    }
});
