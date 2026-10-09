import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { test } from 'node:test';

// Exercise the shipped inline functions, not a copied implementation.
const blade = readFileSync(new URL('../resources/views/dashboard.blade.php', import.meta.url), 'utf8');
const extract = name => {
  const start = blade.indexOf(`  const ${name} =`);
  return blade.slice(start, blade.indexOf('\n  const ', start + 10));
};
const names = ['landingRecovery', 'landingResponseError', 'landingPartialPending', 'loanAnalyticsSlots',
  'loadLoanAnalytics', 'loadSmeOperations', 'loadMicroPerformance', 'loadConsumerOperations'];
const datasetFromHtml = html => Object.fromEntries([...html.matchAll(/data-([a-z-]+)="([^"]*)"/g)]
  .map(([, key, value]) => [key.replace(/-([a-z])/g, (_, char) => char.toUpperCase()), value]));
const element = dataset => ({
  dataset, innerHTML: '', offsetParent: null,
  classList: { add() {}, remove() {}, toggle() {} }, setAttribute() {},
  querySelector() { return { dataset: datasetFromHtml(this.innerHTML) }; },
});
const flush = async () => { for (let i = 0; i < 15; i++) await Promise.resolve(); };
function harness() {
  const timers = new Map();
  let sequence = 0;
  const requests = [];
  const responses = [];
  const period = { value: '2026-10-04' };
  const slots = [element({ loanAnalyticsSlot: 'tariff' }), element({ loanAnalyticsSlot: 'quality' })];
  const roots = Object.fromEntries(['sme', 'consumer', 'micro'].map(scope => [scope, element({ url: `/${scope}?periode=2026-10-04&cabang=00045` })]));
  const noop = () => {};
  const context = {
    URL, AbortController, Date, Promise, Map, Error, Boolean, String, Array,
    window: { location: { origin: 'https://example.test' },
      setTimeout(fn, delay) { const id = ++sequence; timers.set(id, { fn, delay }); return id; },
      clearTimeout(id) { timers.delete(id); } },
    document: { querySelectorAll: () => slots, querySelector: () => null, getElementById: () => period },
    DOMParser: class { parseFromString(html) { return { querySelector: () => ({ dataset: datasetFromHtml(html) }) }; } },
    dashboardShell: { dataset: { loanAnalyticsUrl: '/analytics?periode=2026-10-04&cabang=00045' } },
    loanAnalyticsRequests: new Map(),
    smeOperationsDashboard: roots.sme, consumerOperationsDashboard: roots.consumer, microPerformanceDashboard: roots.micro,
    smeOperationsRequest: null, consumerOperationsRequest: null, microPerformanceRequest: null,
    escapeHtml: String, initializeLandingAnalyticsCharts: noop,
    setSmeRefreshState: noop, closeSmeVendorModal: noop, closeSmeUnproductiveModal: noop,
    prepareSmeVendorModal: noop, prepareSmeUnproductiveModal: noop,
    setConsumerRefreshState: noop, setMicroRefreshState: noop, closeMicroPipelineModal: noop,
    closeMicroPipelineSourceModal: noop, initializeMicroBilling: noop, prepareMicroPipelineModal: noop,
    smeOperationsErrorHtml: String, consumerOperationsErrorHtml: String, microPerformanceErrorHtml: String,
    fetch(url) { requests.push(new URL(url)); const next = responses.shift(); return next instanceof Error ? Promise.reject(next) : Promise.resolve(next); },
  };
  vm.createContext(context);
  vm.runInContext(names.map(extract).join('\n') + '\nglobalThis.api = { ' + names.join(', ') + ' };', context);
  return { ...context.api, timers, requests, responses, slots, roots, period,
    async tick() { const entry = timers.entries().next().value; assert.ok(entry, 'retry scheduled'); timers.delete(entry[0]); entry[1].fn(); await flush(); },
  };
}
const json = (pending = false) => ({ ok: true, status: 200, redirected: false, headers: { get: () => 'application/json' },
  json: async () => ({ scope: 'sme', meta: { refresh_pending: pending }, tariff_html: 'tariff', quality_html: 'quality' }) });
const html = (scope, pending = false, error = false) => ({ ok: true, status: 200, redirected: false,
  text: async () => `<div data-${scope === 'micro' ? 'micro-performance' : `${scope}-operations`}-ready="1" data-refresh-pending="${pending ? 1 : 0}" data-load-error="${error ? 1 : 0}" data-requested-period="2026-10-04">data</div>` });

test('pending analytics rereads without refresh, retains scope/period, completes and deduplicates', async () => {
  const h = harness(); h.responses.push(json(true), json(false));
  const first = h.loadLoanAnalytics('sme');
  assert.equal(h.loadLoanAnalytics('sme'), first);
  await first; assert.equal(h.slots[0].dataset.loaded, '0');
  await h.tick();
  assert.equal(h.requests.length, 2); assert.equal(h.slots[0].dataset.loaded, '1');
  for (const url of h.requests) { assert.equal(url.searchParams.get('refresh'), null); assert.equal(url.searchParams.get('scope'), 'sme'); assert.equal(url.searchParams.get('periode'), '2026-10-04'); assert.equal(url.searchParams.get('cabang'), '00045'); }
  assert.equal(h.timers.size, 0);
});
test('analytics transient errors preserve rendered data and retry; login stops', async () => {
  const h = harness(); h.responses.push(json(), new Error('network'), { ...json(), redirected: true });
  await h.loadLoanAnalytics('sme'); await h.loadLoanAnalytics('sme', true);
  assert.equal(h.slots[0].innerHTML, 'tariff');
  await h.tick(); assert.equal(h.slots[0].innerHTML, 'tariff'); assert.equal(h.timers.size, 0);
});
test('recovery is bounded, deduplicated, cancelled on success and rejects obsolete period', async () => {
  const h = harness(); let calls = 0;
  const retry = () => { calls++; h.landingRecovery.schedule('pending', retry); };
  h.landingRecovery.schedule('pending', retry); h.landingRecovery.schedule('pending', retry);
  for (const delay of [5000, 10000, 20000, 40000, 60000, 60000]) { assert.equal([...h.timers.values()][0].delay, delay); await h.tick(); }
  assert.equal(calls, 6); assert.equal(h.timers.size, 0);
  h.landingRecovery.schedule('old-period', retry, () => false); await h.tick(); assert.equal(calls, 6);
  h.landingRecovery.schedule('clear', retry); h.landingRecovery.clear('clear'); assert.equal(h.timers.size, 0);
});
for (const [scope, loader] of [['sme', 'loadSmeOperations'], ['consumer', 'loadConsumerOperations'], ['micro', 'loadMicroPerformance']]) {
  test(`${scope} pending and error payloads recover without clearing cache or visible data`, async () => {
    const h = harness(); h.responses.push(html(scope, true), html(scope, false, true), html(scope));
    await h[loader](); const visible = h.roots[scope].innerHTML;
    assert.equal(h.roots[scope].dataset.loaded, '0');
    await h.tick(); assert.equal(h.roots[scope].innerHTML, visible);
    await h.tick(); assert.equal(h.roots[scope].dataset.loaded, '1'); assert.equal(h.timers.size, 0);
    assert.equal(h.requests.length, 3);
    assert.ok(h.requests.every(url => !url.searchParams.has('refresh')));
  });
  test(`${scope} permanent authentication error does not loop`, async () => {
    const h = harness(); h.responses.push({ ...html(scope), ok: false, status: 401 });
    await h[loader](); assert.equal(h.timers.size, 0); assert.equal(h.requests.length, 1);
  });
}
test('micro polling ignores a period changed while waiting', async () => {
  const h = harness(); h.responses.push(html('micro', true));
  await h.loadMicroPerformance(); h.period.value = '2026-10-05'; await h.tick(); assert.equal(h.requests.length, 1);
});

const pageRecoveryBlade = readFileSync(new URL('../resources/views/dashboard/partials/landing-page-recovery.blade.php', import.meta.url), 'utf8');
const pageRecoveryCode = pageRecoveryBlade.slice(pageRecoveryBlade.indexOf('  const recoverLandingPage ='), pageRecoveryBlade.indexOf('  recoverLandingPage(@json'));
function pageHarness() {
  const timers = [];
  const storage = new Map();
  let reloads = 0;
  const browser = { location: { href: 'https://example.test/dashboard?periode=2026-10-04&cabang=00045&segment=micro', reload() { reloads++; } },
    sessionStorage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) },
    setTimeout(fn, delay) { timers.push({ fn, delay }); } };
  const page = { visibilityState: 'visible', activeElement: null, querySelectorAll: () => [] };
  const context = { URL, Number, String, Array }; vm.createContext(context);
  vm.runInContext(pageRecoveryCode + '\nglobalThis.recover = recoverLandingPage;', context);
  return { recover: pending => context.recover(pending, browser, page), browser, page, timers, storage, reloads: () => reloads };
}
test('page pending recovery persists bounded budget across reloads and ready resets', () => {
  const h = pageHarness();
  for (const delay of [30000, 90000, 180000]) { h.recover(true); assert.equal(h.timers[0].delay, delay); h.timers.shift().fn(); }
  h.recover(true); assert.equal(h.timers.length, 0); assert.equal(h.reloads(), 3);
  assert.ok(h.browser.location.href.endsWith('&segment=micro'));
  h.recover(false); assert.equal(h.storage.size, 0); h.recover(true); assert.equal(h.timers[0].delay, 30000);
});
test('page waits while hidden or editing and disables reload when storage unavailable', () => {
  const h = pageHarness(); h.page.visibilityState = 'hidden'; h.recover(true); h.timers.shift().fn(); assert.equal(h.reloads(), 0);
  h.page.visibilityState = 'visible'; h.page.activeElement = { tagName: 'SELECT' }; h.timers.shift().fn(); assert.equal(h.reloads(), 0);
  h.page.activeElement = null; h.page.querySelectorAll = () => [{ getClientRects: () => [1] }]; h.timers.shift().fn(); assert.equal(h.reloads(), 0);
  h.page.querySelectorAll = () => []; h.timers.shift().fn(); assert.equal(h.reloads(), 1);
  h.browser.sessionStorage.getItem = () => { throw new Error('blocked'); }; h.recover(true); assert.equal(h.timers.length, 0);
});

test('page automatic reread strips a manual refresh flag to avoid repeated invalidation', () => {
  const h = pageHarness(); h.browser.location.href += '&refresh=1';
  let target; h.browser.location.replace = url => { target = new URL(url); };
  h.recover(true); h.timers.shift().fn();
  assert.equal(target.searchParams.has('refresh'), false);
  assert.equal(target.searchParams.get('periode'), '2026-10-04');
  assert.equal(target.searchParams.get('cabang'), '00045');
});
