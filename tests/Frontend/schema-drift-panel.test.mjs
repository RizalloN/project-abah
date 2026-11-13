import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { Script } from 'node:vm';

const source = readFileSync(new URL('../../resources/views/import/partials/schema-drift-panel.blade.php', import.meta.url), 'utf8');
const script = source.match(/<script>([\s\S]*?)<\/script>/)?.[1];

test('schema review panel script parses independently of Blade rendering', () => {
    assert.ok(script);
    assert.doesNotThrow(() => new Script(script));
});

const gateFunction = script.slice(script.indexOf('function blockedReason()'), script.indexOf('function updateGate()'));
function importBlocked(report, flags = {}) {
    const state = { report, busy: false, error: false, ...flags };
    const pending = key => () => (report?.[key] || []).filter(item => !item.decision || item.decision === 'pending');
    return Boolean(new Function('state', 'pendingReviews', 'pendingColumns', 'pendingAdjustments', gateFunction + '\nreturn blockedReason();')(
        state, pending('review_issues'), pending('new_columns'), pending('adjustments'),
    ));
}

test('an absent or failed scan cannot authorize import, including a failed rescan after a valid preview', () => {
    assert.equal(importBlocked(null), true);
    assert.equal(importBlocked({}, { busy: true }), true);
    assert.equal(importBlocked({}, { error: true }), true);
    assert.equal(importBlocked({ has_pending_decision: false }), false);
});

test('unknown columns hold import even when no notification event was persisted', () => {
    assert.equal(importBlocked({ new_columns: [{ header: 'FLAG_PAYROLL', decision: 'pending' }], event: null }), true);
    assert.equal(importBlocked({ has_pending_decision: true, event: null }), true);
    assert.equal(importBlocked({ unmapped_columns: [{ header: 'GIMMICK' }] }), true);
});

test('acknowledged schema decisions release import while undecided data loss does not', () => {
    assert.equal(importBlocked({ new_columns: [{ header: 'FLAG_PAYROLL', decision: 'rejected' }] }), false);
    assert.equal(importBlocked({ adjustments: [{ column: 'saldo', decision: 'pending' }] }), true);
    assert.equal(importBlocked({ adjustments: [{ column: 'saldo', decision: 'applied' }] }), false);
    assert.equal(importBlocked({ review_issues: [{ key: 'date', decision: 'pending', blocking: false }] }), true);
    assert.equal(importBlocked({ review_issues: [{ key: 'date', decision: 'applied', blocking: false }] }), false);
});

test('malformed rows cannot be authorized by clearing only pending column decisions', () => {
    assert.equal(importBlocked({ has_pending_decision: false, has_blocking_issue: true }), true);
    assert.equal(importBlocked({ review_issues: [{ key: 'row_width', blocking: true, decision: 'pending' }] }), true);
});

test('source-wide counts do not describe late populated fields as empty samples', () => {
    const populationFunction = script.slice(script.indexOf('function columnPopulation('), script.indexOf('function updateAcceptLabel()'));
    const describe = new Function('report', 'column', populationFunction + '\nreturn columnPopulation(report, column);');
    const description = describe({ source: { structural_scan: { complete: true, total_rows: 317945, column_non_empty: { 66: 52 } } } }, { index: 66, non_empty: 0 });
    assert.equal(description, '52 nilai terisi dari 317.945 baris sumber');
    assert.equal(describe({ source: {} }, { index: 66, non_empty: 0 }), '0 nilai terisi pada sampel');
});
