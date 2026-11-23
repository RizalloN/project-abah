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

test('a repaired QRIS source can import and an actual new header exposes both decisions', () => {
    const report = {
        table: 'jumlah_merchant_qris_detail',
        source: { structural_scan: { merchant_name_pipe_repairs: { count: 2, rows: [65734, 348748] } } },
        review_issues: [],
    };
    assert.equal(importBlocked(report), false);

    const withNewColumn = {
        ...report,
        has_pending_decision: true,
        new_columns: [{ header: 'FLAG_PAYROLL', decision: 'pending' }],
        event: { id: 19 },
    };
    assert.equal(importBlocked(withNewColumn), true);
    const decision = script.slice(script.indexOf('function renderDecision()'), script.indexOf('function renderMonitorColumns('));
    const nodes = new Map();
    const query = selector => {
        if (!nodes.has(selector)) nodes.set(selector, {});
        return nodes.get(selector);
    };
    new Function('$', 'pendingEvent', 'pendingColumns', 'pendingAdjustments', 'pendingReviews', 'monitorMode', 'updateAcceptLabel',
        decision + '\nrenderDecision();')(
        query, () => withNewColumn.event, () => withNewColumn.new_columns, () => [], () => [], false, () => {},
    );
    assert.equal(query('[data-sdp-decision-wrap]').hidden, false);
    for (const button of ['[data-sdp-accept]', '[data-sdp-reject]']) {
        assert.equal(query(button).hidden, false);
        assert.equal(query(button).disabled, false);
    }
});

test('source-wide counts do not describe late populated fields as empty samples', () => {
    const populationFunction = script.slice(script.indexOf('function columnPopulation('), script.indexOf('function updateAcceptLabel()'));
    const describe = new Function('report', 'column', populationFunction + '\nreturn columnPopulation(report, column);');
    const description = describe({ source: { structural_scan: { complete: true, total_rows: 317945, column_non_empty: { 66: 52 } } } }, { index: 66, non_empty: 0 });
    assert.equal(description, '52 nilai terisi dari 317.945 baris sumber');
    assert.equal(describe({ source: {} }, { index: 66, non_empty: 0 }), '0 nilai terisi pada sampel');
});

test('column approval asks for confirmation with the exact table, file and nullable column', async () => {
    const start = script.indexOf('function confirmDecision(');
    const end = script.indexOf('async function decide(', start);
    const state = { report: { table: 'daily_loan_dinamis', source: { name: 'sumber.csv' } } };
    const prompts = [];
    const window = { confirm: message => { prompts.push(message); return false; } };
    const confirm = new Function('window', 'state', 'pendingAdjustments', script.slice(start, end) + '\nreturn confirmDecision;')(
        window, state, () => [],
    );

    assert.equal(await confirm('accept', [{ action: 'add', name: 'catatan_baru', type: 'TEXT' }], [], []), false);
    assert.match(prompts[0], /daily_loan_dinamis.*sumber\.csv/);
    assert.match(prompts[0], /Tambah catatan_baru \(TEXT, boleh kosong\)/);
});
