import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { test } from 'node:test';

const blade = readFileSync(new URL('../resources/views/report/kinerjarm.blade.php', import.meta.url), 'utf8');
const start = blade.indexOf('    function loadKinerjaData()');
const source = blade.slice(start, blade.indexOf('    // RM Detail', start));
const tick = () => new Promise(resolve => setImmediate(resolve));
function setup() {
  const classes = new Set();
  const timers = new Map();
  const requests = [];
  const errors = [];
  const context = {
    filterForm: { action: '/report' }, ajaxWrapper: { classList: { add: x => classes.add(x), remove: x => classes.delete(x) } },
    ajaxContainer: { innerHTML: 'previous valid data' }, submitButton: {},
    FormData: class {}, URLSearchParams: class { toString() { return 'periode=2026-10-06'; } },
    AbortController, REQUEST_TIMEOUT: 45000, requestAbortController: null,
    setTimeout: fn => { const id = Symbol(); timers.set(id, fn); return id; }, clearTimeout: id => timers.delete(id),
    fetch: (url, options) => new Promise((resolve, reject) => {
      options.signal.addEventListener('abort', () => reject(Object.assign(new Error(), { name: 'AbortError' })));
      requests.push({ resolve, reject, signal: options.signal });
    }),
    restoreKinerjaTabState() {}, showErrorAlert: msg => errors.push(msg), console: { error() {} },
    window: { history: { pushState() {} } }, document: { getElementById: () => null },
  };
  vm.createContext(context); vm.runInContext(source, context);
  return { context, requests, timers, classes, errors };
}
test('timeout remains active until response body completes and preserves existing data', async () => {
  const s = setup(); s.context.loadKinerjaData();
  s.requests[0].resolve({ ok: true, text: () => new Promise((resolve, reject) => {
    s.requests[0].signal.addEventListener('abort', () => reject(Object.assign(new Error(), { name: 'AbortError' })));
  }) });
  await tick(); assert.equal(s.timers.size, 1);
  [...s.timers.values()][0](); await tick();
  assert.equal(s.classes.size, 0); assert.equal(s.context.submitButton.disabled, false);
  assert.equal(s.context.ajaxContainer.innerHTML, 'previous valid data'); assert.equal(s.errors.length, 1);
});
test('superseded request cannot clear loading or replace the active response', async () => {
  const s = setup(); s.context.loadKinerjaData(); s.context.loadKinerjaData(); await tick();
  assert.equal(s.context.submitButton.disabled, true); assert.equal(s.classes.has('loading-active'), true);
  s.requests[1].resolve({ ok: true, text: async () => 'new valid data' }); await tick();
  assert.equal(s.context.ajaxContainer.innerHTML, 'new valid data'); assert.equal(s.classes.size, 0);
  assert.equal(s.context.submitButton.disabled, false); assert.equal(s.errors.length, 0);
});
