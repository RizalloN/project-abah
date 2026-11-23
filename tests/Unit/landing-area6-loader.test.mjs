import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { test } from 'node:test';

// Run the deployed loader with only Blade's endpoint expression substituted.
const blade = readFileSync(new URL('../../resources/views/dashboard.blade.php', import.meta.url), 'utf8');
const marker = blade.indexOf('// --- Area 6 AJAX Skeleton Loader ---');
assert.ok(marker >= 0, 'Area 6 loader must exist');
const start = blade.indexOf('(function()', marker);
const end = blade.indexOf('})();', start);
assert.ok(start >= marker && end > start, 'Area 6 loader must be extractable');
const loader = blade.slice(start, end + 5)
  .replace("{{ route('dashboard.area6-data') }}", '/dashboard/area6-data');

const ready = () => ({
  ok: true,
  status: 200,
  redirected: false,
  json: async () => ({ area6_portfolio: { cards: [{ key: 'os', value: 'Rp12 M' }] } }),
});
const flush = async () => {
  for (let index = 0; index < 12; index++) await Promise.resolve();
};

function harness(responses, { overlayPresent = true, activeScope = 'cabang_konsol' } = {}) {
  const attributes = new Map();
  const classes = new Set();
  const listeners = new Map();
  const requests = [];
  const navigations = [];
  const errors = [];
  const overlay = {
    setAttribute: (name, value) => attributes.set(name, value),
    classList: {
      add: name => classes.add(name),
      remove: name => classes.delete(name),
    },
  };
  const status = { textContent: '' };
  const retry = {
    hidden: true,
    addEventListener: (event, callback) => listeners.set(event, callback),
  };
  const elements = {
    'area6-loading-overlay': overlayPresent ? overlay : null,
    'area6-loading-status': status,
    'area6-loading-retry': retry,
    'periode-selector': { value: '2026-11-12' },
  };
  const context = vm.createContext({
    URL,
    Date,
    console: { error: (...args) => errors.push(args) },
    branchSelector: { value: '00045' },
    document: {
      getElementById: id => elements[id] ?? null,
      querySelector: () => activeScope ? { dataset: { area6Scope: activeScope } } : null,
    },
    window: {
      location: {
        origin: 'https://example.test',
        href: 'https://example.test/dashboard?periode=2026-11-01&cabang=00045&landing_scope=sme&_area6=old',
        replace: value => navigations.push(new URL(value)),
      },
    },
    fetch: (url, options) => {
      requests.push({ url: new URL(url), options });
      const response = responses.shift();
      return response instanceof Error ? Promise.reject(response) : Promise.resolve(response);
    },
  });
  vm.runInContext(loader, context);
  return { attributes, classes, requests, navigations, errors, status, retry, elements,
    clickRetry: async () => {
      assert.ok(listeners.has('click'), 'retry must be wired');
      await listeners.get('click')();
    },
  };
}

test('ready JSON replaces the page once with current period, branch and active segment', async () => {
  const h = harness([ready()], { activeScope: 'micro' });
  assert.equal(h.attributes.get('aria-busy'), 'true');
  assert.equal(h.retry.hidden, true);
  await flush();
  assert.equal(h.requests.length, 1);
  assert.equal(h.requests[0].url.pathname, '/dashboard/area6-data');
  assert.equal(h.requests[0].url.searchParams.get('periode'), '2026-11-12');
  assert.equal(h.requests[0].url.searchParams.get('cabang'), '00045');
  assert.equal(h.requests[0].options.cache, 'no-store');
  assert.equal(h.requests[0].options.headers.Accept, 'application/json');
  assert.equal(h.navigations.length, 1);
  const target = h.navigations[0];
  assert.equal(target.pathname, '/dashboard');
  assert.equal(target.searchParams.get('periode'), '2026-11-12');
  assert.equal(target.searchParams.get('cabang'), '00045');
  assert.equal(target.searchParams.get('landing_scope'), 'micro');
  assert.match(target.searchParams.get('_area6'), /^\d+$/);
  assert.equal(h.attributes.get('aria-busy'), 'true');
  assert.equal(h.retry.disabled, true);
  assert.equal(h.errors.length, 0);
});

for (const [name, payload] of [
  ['empty cards', { area6_portfolio: { cards: [] } }],
  ['missing portfolio', {}],
  ['invalid cards', { area6_portfolio: { cards: 'unavailable' } }],
]) {
  test(`${name} keeps the skeleton and exposes a truthful retry state`, async () => {
    const h = harness([{ ...ready(), json: async () => payload }]);
    await flush();
    assert.equal(h.navigations.length, 0);
    assert.equal(h.elements['area6-loading-overlay'] !== null, true);
    assert.equal(h.attributes.get('aria-busy'), 'false');
    assert.equal(h.retry.hidden, false);
    assert.equal(h.retry.disabled, false);
    assert.equal(h.classes.has('is-settled'), true);
    assert.match(h.status.textContent, /belum tersedia/);
    assert.equal(h.errors.length, 0);
  });
}

for (const [name, response] of [
  ['network failure', new Error('offline')],
  ['HTTP failure', { ...ready(), ok: false, status: 503 }],
  ['authentication redirect', { ...ready(), redirected: true }],
  ['malformed JSON', { ...ready(), json: async () => { throw new SyntaxError('invalid JSON'); } }],
]) {
  test(`${name} exposes retry without navigating or claiming data is ready`, async () => {
    const h = harness([response]);
    await flush();
    assert.equal(h.navigations.length, 0);
    assert.equal(h.attributes.get('aria-busy'), 'false');
    assert.equal(h.retry.hidden, false);
    assert.equal(h.classes.has('is-settled'), true);
    assert.match(h.status.textContent, /belum berhasil dimuat/);
    assert.equal(h.errors.length, 1);
  });
}

test('retry fetches again and a recovered response opens the overview', async () => {
  const h = harness([new Error('offline'), ready()]);
  await flush();
  assert.equal(h.retry.hidden, false);
  await h.clickRetry();
  assert.equal(h.requests.length, 2);
  assert.equal(h.navigations.length, 1);
  assert.equal(h.navigations[0].searchParams.get('landing_scope'), 'cabang_konsol');
  assert.match(h.status.textContent, /Data siap/);
});

test('an already rendered overview never fetches the cold-cache endpoint', async () => {
  const h = harness([], { overlayPresent: false });
  await flush();
  assert.equal(h.requests.length, 0);
  assert.equal(h.navigations.length, 0);
  assert.equal(h.errors.length, 0);
});
