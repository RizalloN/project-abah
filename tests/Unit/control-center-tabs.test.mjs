import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync('resources/views/import/partials/control-center.blade.php', 'utf8');
const script = source.match(/<script>\s*([\s\S]*?)\s*<\/script>/)[1];
const keys = ['overview', 'operations', 'workers', 'snapshot', 'data', 'database'];

function loadPage(search, savedTab = null) {
    const listeners = {};
    const buttons = Object.fromEntries(keys.map(key => [key, {
        dataset: { ccTab: key },
        setAttribute(name, value) { this[name] = value; },
        focus() {},
    }]));
    const panels = Object.fromEntries(keys.filter(key => key !== 'operations').map(key => [key, { hidden: true }]));
    const dummy = { addEventListener() {}, style: { setProperty() {} } };
    const root = {
        dataset: {},
        addEventListener() {},
        querySelector(selector) {
            const match = selector.match(/^\[data-cc-panel="(\w+)"\]$/);
            return match ? panels[match[1]] : dummy;
        },
    };
    const tabs = {
        querySelectorAll() { return Object.values(buttons); },
        addEventListener(type, handler) { listeners['tabs:' + type] = handler; },
    };
    const document = {
        hidden: false,
        getElementById(id) {
            if (id === 'controlCenter') return root;
            if (id === 'ccTabs') return tabs;
            if (id === 'cc-panel-operations') return panels.operations || null;
            if (id.startsWith('cc-tab-')) return buttons[id.slice(7)];
            return null;
        },
        addEventListener(type, handler) { listeners[type] = handler; },
    };
    const window = {
        location: { href: 'http://localhost/job-management' + search },
        history: { replaceState() {} },
        setInterval() {},
    };
    const localStorage = { getItem() { return savedTab; }, setItem() {} };
    runInNewContext(script, { document, window, localStorage, URL, Promise, fetch: () => new Promise(() => {}) });
    // The operations panel is parsed after the partial's inline script.
    panels.operations = { hidden: true };
    listeners.DOMContentLoaded?.();
    return { buttons, panels, listeners };
}

test('the operations panel is visible immediately when selected by URL or saved preference', () => {
    for (const [search, saved] of [['?tab=operations', null], ['', 'operations']]) {
        const { buttons, panels } = loadPage(search, saved);
        assert.equal(buttons.operations['aria-selected'], 'true');
        assert.equal(panels.operations.hidden, false);
        assert.equal(panels.overview.hidden, true);
    }
});

test('initial selection and tab clicks keep the selected tab and visible panel in sync', () => {
    const { buttons, panels, listeners } = loadPage('?tab=snapshot');
    assert.equal(buttons.snapshot['aria-selected'], 'true');
    assert.equal(panels.snapshot.hidden, false);
    assert.equal(panels.operations.hidden, true);
    listeners['tabs:click']({ target: { closest: () => buttons.operations } });
    assert.equal(buttons.operations['aria-selected'], 'true');
    assert.equal(panels.operations.hidden, false);
    assert.equal(panels.snapshot.hidden, true);
});
