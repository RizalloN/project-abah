import test from 'node:test';
import assert from 'node:assert/strict';
import { timeseriesScale, timeseries } from '../../resources/js/presentation/segments/charts.js';

test('money trend uses an honest zero baseline so a small decrease cannot read as a collapse', () => {
    const series = [{ values: [2.70e12, 2.69e12, 2.67e12, 2.8e12] }];
    const labels = ['A', 'B', 'C', 'D'];
    const original = structuredClone(series);
    const scale = timeseriesScale(series, labels, true);
    assert.equal(scale.min, 0);
    assert.ok(scale.max > 2.8e12);
    assert.ok(Math.abs(scale.y(2.69e12) - scale.y(2.67e12)) < 3);
    assert.ok(scale.y(2.67e12) < scale.y(0) - 150);
    assert.deepEqual(series, original);
});

test('zero and unavailable trend periods remain distinct on the zero baseline', () => {
    assert.equal(timeseriesScale([{ values: [0, 0] }], ['A', 'B'], true).min, 0);
    const html = timeseries({ title: 'Total', labels: ['A', 'B', 'C'], zeroBaseline: true,
        series: [{ key: 'total', label: 'Total', values: [50, null, 0], color: '#0058d0' }] });
    const line = html.match(/class="sg-ts__line" d="([^"]*)"/)[1];
    assert.equal((line.match(/M/g) || []).length, 2);
    assert.ok(html.includes('Rp50'));
    assert.ok(html.includes('Rp0'));
    assert.ok(!/NaN|Infinity/.test(html));
});
