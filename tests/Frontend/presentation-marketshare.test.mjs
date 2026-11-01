import test from 'node:test';
import assert from 'node:assert/strict';
import { marketshare } from '../../resources/js/presentation/segments/render-wilayah.js';

const labels = { dpk: 'Total DPK', tabungan: 'Tabungan', giro: 'Giro', deposito: 'Deposito', casa: 'CASA', pinjaman_total: 'Total Pinjaman', umkm: 'Pinjaman UMKM', konsumer: 'Pinjaman Konsumer', briguna: 'Pinjaman BRIGUNA', kpr: 'Pinjaman KPR' };
const sourceRow = (key, scope, factor) => ({ key: scope, total: scope === 'area6', label: scope === 'area6' ? 'Area 6' : `KC ${scope}`, bri: factor * 10, industry: factor * 27, share: factor / 3, share_prev: factor / 4, share_ytd: factor / 12,
    labels: { bri: `Sumber BRI ${scope} ${key}`, industry: `Sumber industri ${scope} ${key}`, share: `${factor / 3}%`, share_prev: `${factor / 4}%`, share_ytd: `${factor / 12}%` } });
const segment = (key, index) => ({ key, label: labels[key], kind: key === 'dpk' || ['tabungan', 'giro', 'deposito', 'casa'].includes(key) ? 'deposit' : 'loan', period_now: 'Mei-26', period_prev: 'Dec-25', rows: [sourceRow(key, 'area6', index + 100), sourceRow(key, 'madiun', index + 10), sourceRow(key, 'ngawi', index + 20)] });
const context = (overrides = {}) => ({ scope: { key: 'area6', label: 'Area 6' }, presentationState: {}, wilayah: { marketshare: { available: true, source: 'Laporan resmi Mei 2026', unit: 'Rp Miliar', groups: { simpanan: ['giro', 'dpk', 'deposito', 'casa', 'tabungan'].map(segment), pinjaman: ['kpr', 'konsumer', 'pinjaman_total', 'briguna', 'umkm'].map(segment) } } }, ...overrides });
const panel = (html, key) => html.split(`data-market-kind="${key}"`)[1]?.split('data-market-kind=')[0]?.split('<div class="sg-grid')[0] || '';
const firstVessels = html => html.split('data-sg-vessels')[1]?.split('</figure>')[0] || '';

test('both source totals and eight ordered products are visible together without category selectors', () => {
    const html = marketshare(context()).html;
    assert.doesNotMatch(html, /role="tab"|role="tabpanel"|data-sg-market-picker|hidden inert/);
    for (const [key, products, total] of [['simpanan',['tabungan','giro','deposito','casa'],'dpk'],['pinjaman',['umkm','konsumer','briguna','kpr'],'pinjaman_total']]) {
        const content=panel(html,key);
        assert.deepEqual([...firstVessels(content).matchAll(/data-vessel="([^"]+)"/g)].map(m=>m[1]),products);
        assert.ok(content.indexOf(`data-market-total="${key}"`)<content.indexOf('data-sg-vessels'));
        assert.ok(content.includes(`Sumber BRI area6 ${total}`));
        assert.ok(content.includes(`Sumber industri area6 ${total}`));
        assert.match(content,/tidak dijumlahkan/);
        assert.match(content,/sg-market-insight/);
    }
});
test('both comparisons follow the selected KC and ignore obsolete category preferences without mutation', () => {
    const ctx=context({selection:{level:'branch',branchKey:'madiun',label:'KC Madiun'},presentationState:{marketshare:{category:'pinjaman'}}});
    const before=JSON.stringify(ctx), html=marketshare(ctx).html;
    assert.match(panel(html,'simpanan'),/Sumber BRI madiun dpk/);
    assert.match(panel(html,'pinjaman'),/Sumber industri madiun pinjaman_total/);
    assert.doesNotMatch(html,/data-sg-market-panel/);
    assert.equal(JSON.stringify(ctx),before);
});
test('a missing total is unavailable and never fabricated from overlapping products', () => {
    const ctx=context();ctx.wilayah.marketshare.groups.simpanan=ctx.wilayah.marketshare.groups.simpanan.filter(v=>v.key!=='dpk');
    const html=marketshare(ctx).html;
    assert.match(panel(html,'simpanan'),/tidak dihitung dari penjumlahan produk/);
    assert.equal([...firstVessels(panel(html,'simpanan')).matchAll(/data-vessel=/g)].length,4);
});
test('insights use actual product ranks and source-labelled changes, including negative changes', () => {
    const ctx=context();const group=ctx.wilayah.marketshare.groups.simpanan;
    for(const item of group)for(const row of item.rows){row.share_ytd=-2;row.labels.share_ytd='-2.00%';}
    const row=group.find(v=>v.key==='giro').rows[0];row.share=99;row.labels.share='99.00%';row.share_ytd=-.5;row.labels.share_ytd='-0.50%';
    const html=panel(marketshare(ctx).html,'simpanan');
    assert.match(html,/<strong>Giro<\/strong> memimpin pangsa produk: <b>99.00%<\/b>/);
    assert.match(html,/Perubahan YtD tertinggi: <strong>Giro<\/strong> <b>-0.50 pp<\/b>/);
});
