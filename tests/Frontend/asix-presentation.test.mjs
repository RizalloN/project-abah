import test from 'node:test';
import assert from 'node:assert/strict';
import { count } from '../../resources/js/presentation/segments/format.js';
import { donut, timeseries } from '../../resources/js/presentation/segments/charts.js';
import { TerritoryIndex } from '../../resources/js/presentation/segments/territory-data.js';
import { recovery } from '../../resources/js/presentation/segments/render-kinerja.js';
import {
    describeLauncherState,
    readInitialChoice,
    shouldKeepDeckOpen,
} from '../../resources/js/presentation/segments/launcher-choice.js';

test('missing counts stay unavailable while a source zero remains zero', () => {
    assert.equal(count(null), '-');
    assert.equal(count(undefined), '-');
    assert.equal(count(0), '0');
    assert.equal(count(1234), '1.234');
});

test('incomplete composition preserves source amounts without inventing a complete ring', () => {
    const html = donut({ items: [
        { key: 'bri', label: 'BRI', value: 20, formatted: '20', color: '#0058d0' },
        { key: 'others', label: 'Bank lain', value: null, formatted: '-', color: '#ccc' },
    ] });
    assert.ok(html.includes('Komposisi belum lengkap'));
    assert.ok(html.includes('<strong>20</strong>'));
    assert.ok(html.includes('<strong>-</strong>'));
    assert.ok(!html.includes('100,00%'));
    assert.ok(!html.includes('NaN'));
});

test('valid composition uses actual shares and explicit source zeros', () => {
    const html = donut({ items: [
        { key: 'a', label: 'A', value: 1, formatted: '1', color: '#0058d0' },
        { key: 'b', label: 'B', value: 3, formatted: '3', color: '#ccc' },
        { key: 'c', label: 'C', value: 0, formatted: '0', color: '#aaa' },
    ] });
    assert.ok(html.includes('25,00%'));
    assert.ok(html.includes('75,00%'));
    assert.ok(html.includes('0,00%'));
    assert.ok(!html.includes('Komposisi belum lengkap'));
});

test('timeseries leaves source gaps in both line and fill and exposes the figures as a table', () => {
    const html = timeseries({ title: 'Simpanan', labels: ['A', 'B', 'C'], series: [{ key: 'total', label: 'Total', color: '#0058d0', values: [10, null, 30] }] });
    const line = html.match(/class="sg-ts__line" d="([^"]*)"/)[1];
    const area = html.match(/class="sg-ts__area" d="([^"]*)"/)[1];
    assert.equal((line.match(/M/g) || []).length, 2);
    assert.equal((area.match(/Z/g) || []).length, 2);
    assert.ok(html.includes('Lihat angka per periode'));
    assert.ok(html.includes('Rp10'));
    assert.ok(html.includes('Rp30'));
    assert.ok(!html.includes('NaN'));
    const missing = timeseries({ title: 'Tidak tersedia', labels: ['A', 'B'], series: [{ key: 'total', label: 'Total', color: '#0058d0', values: [null, null] }] });
    assert.ok(!missing.includes('class="sg-ts__area"'));
    assert.ok(!missing.includes('NaN'));
});

test('geographic allocation excludes unknown values and preserves whole-unit totals', () => {
    const index = new TerritoryIndex([], null);
    const codes = new Map([['known', new Set(['A', 'B'])], ['unknown', new Set(['C'])], ['zero', new Set(['D'])]]);
    const values = index.entityValuesByDistrict(codes, (key) => ({ known: 100, unknown: null, zero: 0 })[key]);
    assert.equal(values.get('A'), 50);
    assert.equal(values.get('B'), 50);
    assert.equal(values.has('C'), false);
    assert.equal(values.get('D'), 0);
    assert.equal([...values.values()].reduce((sum, value) => sum + value, 0), 100);
});

test('matching an outlet name only considers units inside the selected branch', () => {
    const index = new TerritoryIndex([], { ready: true, units: [
        { code: '1', name: 'Sentra', branch: 'madiun', district_codes: ['A'] },
        { code: '2', name: 'Sentra', branch: 'ngawi', district_codes: ['B'] },
    ] });
    const madiun = index.entityCodes([{ key: 'outlet', label: 'UNIT Sentra Madiun' }], false, 'madiun');
    const ngawi = index.entityCodes([{ key: 'outlet', label: 'UNIT Sentra Ngawi' }], false, 'ngawi');
    assert.deepEqual([...madiun.get('outlet')], ['A']);
    assert.deepEqual([...ngawi.get('outlet')], ['B']);
});

test('missing recovery is distinguished from an actual source zero', () => {
    const render = (value) => recovery({ segments: { available: true, scope: { entity_level: 'branch' }, recovery: { total: { value }, segments: [], rows: [] } } }).html;
    assert.ok(render(null).includes('Data recovery DH belum tersedia'));
    assert.ok(!render(null).includes('bernilai nol'));
    assert.ok(render(0).includes('Recovery DH bernilai nol'));
});

test('launcher starts with no selected segments when opened from dashboard without segmen query', () => {
    const order = ['marketshare', 'mapping', 'sektoral', 'simpanan', 'pinjaman', 'kualitas', 'recovery'];
    const scopes = [{ key: 'area6' }, { key: 'madiun' }];
    const landingEntry = readInitialChoice({
        search: '?scope=area6&periode=2026-10-25',
        scopes,
        defaultScope: 'area6',
        order,
    });
    assert.deepEqual(landingEntry.segments, []);
    assert.equal(landingEntry.scope, 'area6');
    assert.equal(landingEntry.hasUrlSegments, false);

    const sharedUrl = readInitialChoice({
        search: '?scope=madiun&segmen=kualitas,pinjaman,unknown,pinjaman',
        scopes,
        defaultScope: 'area6',
        order,
    });
    assert.deepEqual(sharedUrl.segments, ['pinjaman', 'kualitas']);
    assert.equal(sharedUrl.scope, 'madiun');
    assert.equal(sharedUrl.hasUrlSegments, true);
});

test('deck stays locked until confirmed and relocks whenever selection is cleared or edited', () => {
    const emptyState = describeLauncherState({
        choice: { scope: 'area6', segments: [] },
        scopeLabel: 'Area 6 Konsol',
        unlocked: false,
        activeChoice: null,
    });
    assert.equal(emptyState.canStart, false);
    assert.equal(emptyState.keepOpen, false);
    assert.match(emptyState.summary, /Pilih paling sedikit satu segmen/);

    const draftChoice = { scope: 'area6', segments: ['pinjaman'] };
    const beforeStart = describeLauncherState({
        choice: draftChoice,
        scopeLabel: 'Area 6 Konsol',
        unlocked: false,
        activeChoice: null,
    });
    assert.equal(beforeStart.canStart, true);
    assert.equal(beforeStart.keepOpen, false);
    assert.match(beforeStart.summary, /Klik Mulai presentasi/);

    assert.equal(shouldKeepDeckOpen({ unlocked: true, activeChoice: draftChoice, draftChoice }), true);
    assert.equal(shouldKeepDeckOpen({
        unlocked: true,
        activeChoice: draftChoice,
        draftChoice: { scope: 'area6', segments: ['pinjaman', 'kualitas'] },
    }), false);
    assert.equal(shouldKeepDeckOpen({
        unlocked: true,
        activeChoice: draftChoice,
        draftChoice: { scope: 'madiun', segments: ['pinjaman'] },
    }), false);
    assert.equal(shouldKeepDeckOpen({
        unlocked: true,
        activeChoice: draftChoice,
        draftChoice: { scope: 'area6', segments: [] },
    }), false);
});
