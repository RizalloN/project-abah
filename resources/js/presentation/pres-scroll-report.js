import {
    StructuredDeckRenderer,
    escapeHtml as h,
    asNumber,
    formatCurrency,
    formatPercent,
    formatInteger,
    valueOrDash,
    findByKey,
    findScope,
    deltaClass,
    percentOf,
    maxBy,
    iconFor,
    sameOriginAsset,
    marketShareNumber,
    marketShareValueTone,
    normaliseMarketShareToken,
    comparisonPresentationState,
} from './pres-structured-deck';
import { portfolioSculpture, qualityLens } from './pres-data-art';

/*
 * Scroll report renderer. Every figure is read through the deck renderer's own
 * accessors (scope, comparison, timeseries, market share, micro), so numbers stay
 * identical to the slide deck and the PowerPoint export; only the presentation
 * is rebuilt for a vertical, scroll-driven reading. Markup carries hooks for the
 * motion driver in pres-scroll.js:
 *   data-rv          reveal once on entry (children stagger by --i)
 *   data-count       number blooms from zero to its exact formatted value
 *   data-sp          receives --sp, its 0..1 progress through the viewport
 */

const POSITION_KEYS = ['yoy', 'ytd', 'm2', 'mom', 'mtd', 'dtd', 'current'];
const DELTA_KEYS = ['yoy', 'ytd', 'mom', 'mtd', 'dtd'];
const finiteValue = (value) => (typeof value !== 'number' && typeof value !== 'string') || String(value).trim() === '' || !Number.isFinite(Number(value)) ? null : Number(value);
let trendSequence = 0;
const toneOfDelta = (value, inverse = false) => {
    const amount = asNumber(value);
    if (amount === 0) return 'neutral';
    return (inverse ? amount < 0 : amount > 0) ? 'green' : 'red';
};

/* ------------------------------------------------------------ components -- */

const sectionHead = ({ title, subtitle, narrative, period, periodLabel = 'Posisi data', controls = '' }) => `
    <header class="rp-head">
        <div class="rp-head__lead">
            ${period ? `<p class="rp-head__period" data-rv style="--i:0">${h(periodLabel)} ${h(period)}</p>` : ''}
            <h2 class="rp-title" data-rv style="--i:1">${h(title)}</h2>
            ${subtitle ? `<p class="rp-lede" data-rv style="--i:2">${h(subtitle)}</p>` : ''}
        </div>
        <div class="rp-head__reading" data-rv style="--i:3">
            ${narrative ? `<p class="rp-reading">${h(narrative)}</p>` : ''}
            ${controls ? `<div class="rp-controls">${controls}</div>` : ''}
        </div>
    </header>
`;

const stickyBar = (label, figure = '') => `
    <div class="rp-sticky" aria-hidden="true">
        <div class="rp-sticky__bar"><span>${h(label)}</span>${figure ? `<b>${h(figure)}</b>` : ''}</div>
    </div>
`;

const keepUnit = (value) => String(valueOrDash(value)).replace(/\s+(T|M|Jt|nasabah|mantri|debitur)$/u, '\u00A0$1');

const figures = (items, className = '') => `
    <dl class="rp-figures ${h(className)}" style="--count:${Math.max(1, items.length)}">
        ${items.map((item, index) => `
            <div class="rp-fig is-${h(item.tone || 'blue')}" data-rv style="--i:${index}">
                <dt>${h(item.label)}</dt>
                <dd class="rp-fig__value"><span data-count>${h(keepUnit(item.value))}</span></dd>
                ${item.meta ? `<dd class="rp-fig__meta">${h(item.meta)}</dd>` : ''}
            </div>
        `).join('')}
    </dl>
`;

const cardHead = (title, meta = '') => `
    <div class="rp-card-head">
        <h3>${h(title)}</h3>
        ${meta ? `<span>${h(meta)}</span>` : ''}
    </div>
`;

const segmented = (items, attribute, activeKey) => `
    <div class="rp-segmented" role="group">
        ${items.map((item) => `
            <button type="button" ${attribute}="${h(item.key)}" class="${item.key === activeKey ? 'is-active' : ''}" aria-pressed="${item.key === activeKey ? 'true' : 'false'}">${h(item.label)}</button>
        `).join('')}
    </div>
`;

const deltaChip = (formatted, raw, inverse = false) => `<span class="rp-delta is-${toneOfDelta(raw, inverse)}">${h(valueOrDash(formatted))}</span>`;

const achievementCell = (metric) => {
    if (metric.achievement === null || metric.achievement === undefined) return `<span class="rp-muted">${h(metric.achievement_fmt || '-')}</span>`;
    const value = asNumber(metric.achievement);
    return `
        <span class="rp-ach is-${value >= 100 ? 'green' : 'red'}">
            <b>${h(metric.achievement_fmt || formatPercent(value))}</b>
            <i style="--w:${Math.max(2, Math.min(100, value))}%"></i>
        </span>
    `;
};

/**
 * The 17-column comparison, rebuilt as two stacked tables in one card:
 * historical positions, then deltas with RKA. Same rows, same figures, and
 * every column fits on screen. Scope rows are merged per branch.
 */
const comparisonTable = ({ title, meta, periods = {}, rows = [], inverse = false }) => {
    const usePrognosa = Boolean(comparisonPresentationState.usePrognosa && comparisonPresentationState.prognosa?.available);
    const prognosa = comparisonPresentationState.prognosa || {};
    const safeRows = rows.filter(Boolean);
    const spans = safeRows.map((row, index) => {
        if (index > 0 && safeRows[index - 1].scope === row.scope) return 0;
        let span = 1;
        while (safeRows[index + span] && safeRows[index + span].scope === row.scope) span += 1;
        return span;
    });
    const head = (key) => `<span>${h(periods[key]?.name || key)}</span><small>${h(periods[key]?.label || '-')}</small>`;
    const labelCells = (row, index) => `
        ${spans[index] ? `<th scope="rowgroup" rowspan="${spans[index]}" class="is-sticky">${h(row.scope || '-')}</th>` : ''}
        <td class="is-label">${h(row.dimension || '-')}</td>
    `;
    const empty = (colspan) => `<tr><td colspan="${colspan}" class="rp-empty">Data perbandingan belum tersedia.</td></tr>`;
    // The current column carries a lit bar in each detail row, scaled to the largest detail row.
    const isTotal = (row) => String(row.className || '').includes('is-total');
    const largest = Math.max(1e-9, ...safeRows.filter((row) => !isTotal(row)).map((row) => Math.abs(asNumber(row.metric?.positions?.current))));
    const level = (row) => (isTotal(row) ? '' : ` style="--v:${Math.min(1, Math.abs(asNumber(row.metric?.positions?.current)) / largest).toFixed(3)}"`);

    return `
        <section class="rp-paper rp-table-card rp-compare" data-rv>
            ${cardHead(title, meta)}
            <h4 class="rp-compare__part">Posisi historis</h4>
            <div class="rp-table-scroll" tabindex="0" role="region" aria-label="${h(title)}: posisi historis">
                <table class="rp-table rp-table--compare">
                    <thead>
                        <tr>
                            <th class="is-sticky">Scope</th>
                            <th class="is-label">Segmen / produk</th>
                            ${POSITION_KEYS.map((key) => `<th class="${key === 'current' ? 'is-current' : ''}">${head(key)}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>
                        ${safeRows.length ? safeRows.map((row, index) => {
                            const metric = row.metric || {};
                            return `
                                <tr class="${h(row.className || '')}" style="--i:${index}">
                                    ${labelCells(row, index)}
                                    ${POSITION_KEYS.map((key) => `<td class="${key === 'current' ? 'is-current' : ''}"${key === 'current' ? level(row) : ''}>${h(metric.positions_fmt?.[key] || formatCurrency(metric.positions?.[key]))}</td>`).join('')}
                                </tr>
                            `;
                        }).join('') : empty(9)}
                    </tbody>
                </table>
            </div>
            <h4 class="rp-compare__part">Delta terhadap posisi ${h(periods.current?.label || '')} dan RKA</h4>
            <div class="rp-table-scroll" tabindex="0" role="region" aria-label="${h(title)}: delta dan RKA">
                <table class="rp-table rp-table--compare">
                    <thead>
                        <tr>
                            <th class="is-sticky">Scope</th>
                            <th class="is-label">Segmen / produk</th>
                            ${DELTA_KEYS.map((key) => `<th>${head(key)}</th>`).join('')}
                            ${usePrognosa ? `
                                <th class="is-prognosa"><span>${h(prognosa.label || 'Prognosa')}</span><small>${h(prognosa.forecast_date_label || '-')}</small></th>
                                <th class="is-prognosa"><span>Delta vs posisi</span><small>${h(prognosa.comparison_position_label || prognosa.position_date_label || '-')}</small></th>
                            ` : ''}
                            <th class="is-rka"><span>RKA</span><small>Posisi</small></th>
                            <th class="is-rka"><span>Gap</span><small>terhadap RKA</small></th>
                            <th class="is-rka"><span>Pencapaian</span><small>RKA</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${safeRows.length ? safeRows.map((row, index) => {
                            const metric = row.metric || {};
                            return `
                                <tr class="${h(row.className || '')}" style="--i:${index}">
                                    ${labelCells(row, index)}
                                    ${DELTA_KEYS.map((key) => `<td>${deltaChip(metric.deltas_fmt?.[key] || formatCurrency(metric.deltas?.[key]), metric.deltas?.[key], inverse)}</td>`).join('')}
                                    ${usePrognosa ? `
                                        <td class="is-prognosa">${h(metric.prognosa_fmt || '-')}</td>
                                        <td class="is-prognosa">${metric.prognosa_available ? deltaChip(metric.prognosa_delta_fmt, metric.prognosa_delta, inverse) : '-'}</td>
                                    ` : ''}
                                    <td class="is-rka">${h(metric.rka_fmt || '-')}</td>
                                    <td class="is-rka">${deltaChip(metric.gap_fmt || '-', metric.gap, inverse)}</td>
                                    <td class="is-rka">${achievementCell(metric)}</td>
                                </tr>
                            `;
                        }).join('') : empty(usePrognosa ? 12 : 10)}
                    </tbody>
                </table>
            </div>
        </section>
    `;
};

/** A plain data table for the smaller matrices (market share, micro, strategies). */
const dataTable = ({ title, meta = '', columns = [], rows = [], empty = 'Data belum tersedia pada scope ini.', className = '' }) => `
    <section class="rp-paper rp-table-card ${h(className)}" data-rv>
        ${title ? cardHead(title, meta) : ''}
        <div class="rp-table-scroll" tabindex="0" role="region" aria-label="${h(title || 'Tabel data')}">
            <table class="rp-table">
                <thead><tr>${columns.map((column, index) => `<th class="${index === 0 ? 'is-sticky' : ''} ${column.align === 'left' ? 'is-left' : ''}">${column.label}</th>`).join('')}</tr></thead>
                <tbody>
                    ${rows.length ? rows.map((row, rowIndex) => `
                        <tr class="${h(row.className || '')}" style="--i:${rowIndex}">
                            ${columns.map((column, index) => index === 0
                                ? `<th scope="row" class="is-sticky">${column.render(row, rowIndex)}</th>`
                                : `<td class="${column.align === 'left' ? 'is-left' : ''}">${column.render(row, rowIndex)}</td>`).join('')}
                        </tr>
                    `).join('') : `<tr><td colspan="${columns.length}" class="rp-empty">${h(empty)}</td></tr>`}
                </tbody>
            </table>
        </div>
    </section>
`;

/** A trend line the reader draws by scrolling; double-click still opens the daily detail. */
const trendChart = ({ title, labels = [], series = {}, seriesKeys = [], tone = 'blue', note = '' }) => {
    const values = Array.isArray(series.values) ? series.values : [];
    const display = Array.isArray(series.display_values) ? series.display_values : [];
    const count = Math.min(7, labels.length, values.length);
    if (count <= 0) {
        return `<section class="rp-paper rp-trend" data-rv>${cardHead(title, 'Belum ada data')}<p class="rp-empty">Timeseries belum tersedia pada scope ini.</p></section>`;
    }
    const chartLabels = labels.slice(-count);
    const chartValues = values.slice(-count).map(finiteValue);
    const chartDisplay = display.slice(-count);
    const validValues = chartValues.filter((value) => value !== null);
    if (!validValues.length) return `<section class="rp-paper rp-trend" data-rv>${cardHead(title, 'Belum ada data')}<p class="rp-empty">Timeseries belum tersedia pada scope ini.</p></section>`;
    const width = 800;
    const height = 330;
    const pad = { left: 40, right: 40, top: 62, bottom: 48 };
    const minimum = Math.min(...validValues);
    const maximum = Math.max(...validValues);
    const spread = maximum - minimum || Math.max(1, Math.abs(maximum) * 0.08);
    const ordinate = (value) => {
        if (Number.isFinite(spread)) return (value - minimum) / spread;
        const scale = Math.max(Math.abs(minimum), Math.abs(maximum));
        return ((value / scale) - (minimum / scale)) / ((maximum / scale) - (minimum / scale));
    };
    const x = (index) => pad.left + ((width - pad.left - pad.right) * (count === 1 ? 0.5 : index / (count - 1)));
    const y = (value) => pad.top + ((height - pad.top - pad.bottom) * (maximum === minimum ? 0.5 : 1 - ordinate(value)));
    const pointRows = chartValues.map((value, index) => value === null ? null : ({ x: x(index), y: y(value), index }));
    const segments = [];
    pointRows.forEach((point, index) => {
        if (!point) return;
        if (!pointRows[index - 1]) segments.push([]);
        segments[segments.length - 1].push(point);
    });
    const pointString = (points) => points.map((point) => `${point.x.toFixed(1)},${point.y.toFixed(1)}`).join(' ');
    const gradientId = `rp-trend-fill-${++trendSequence}`;
    const finalIndex = chartValues.findLastIndex((value) => value !== null);
    const exactValue = (index) => chartDisplay[index] || chartValues[index]?.toLocaleString('id-ID') || '-';
    const keys = (Array.isArray(seriesKeys) ? seriesKeys : [seriesKeys]).filter(Boolean);

    return `
        <figure class="rp-paper rp-trend rp-trend--stage is-${h(tone)}" data-rv data-sp data-trend-scene
            data-psd-timeseries-expand data-psd-timeseries-keys="${h(keys.join(','))}" data-psd-timeseries-title="${h(title)}"
            role="button" tabindex="0" aria-haspopup="dialog" aria-label="Buka ${h(title)} harian dalam tampilan penuh">
            ${cardHead(title, 'Klik dua kali untuk detail harian')}
            <div class="rp-trend__headline"><strong>${h(exactValue(finalIndex))}</strong><span>${h(chartLabels[finalIndex])}<small>${count} periode terakhir</small></span></div>
            <svg viewBox="0 0 ${width} ${height}" role="img" aria-label="${h(title)}. ${h(chartLabels[finalIndex])}: ${h(exactValue(finalIndex))}">
                <defs><linearGradient id="${gradientId}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="currentColor" stop-opacity="0.24"/><stop offset="100%" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>
                ${[0, 0.5, 1].map((fraction) => `<line class="rp-trend__grid" x1="${pad.left}" y1="${pad.top + fraction * (height - pad.top - pad.bottom)}" x2="${width - pad.right}" y2="${pad.top + fraction * (height - pad.top - pad.bottom)}"/>`).join('')}
                <line class="rp-trend__base" x1="${pad.left}" y1="${height - pad.bottom}" x2="${width - pad.right}" y2="${height - pad.bottom}"></line>
                ${segments.map((points) => `<polygon class="rp-trend__wash" fill="url(#${gradientId})" points="${points[0].x},${height - pad.bottom} ${pointString(points)} ${points[points.length - 1].x},${height - pad.bottom}"/><polyline class="rp-trend__line" data-trend-path pathLength="1" points="${pointString(points)}"/>`).join('')}
                ${chartValues.map((value, index) => value === null ? '' : `
                    <g class="rp-trend__pt" style="--k:${count === 1 ? 0 : (index / (count - 1)).toFixed(3)}">
                        <circle cx="${x(index).toFixed(1)}" cy="${y(value).toFixed(1)}" r="5"></circle>
                        <text class="rp-trend__value" x="${x(index).toFixed(1)}" y="${Math.max(18, y(value) - 18).toFixed(1)}" text-anchor="middle">${h(exactValue(index))}</text>
                        <text class="rp-trend__label" x="${x(index).toFixed(1)}" y="${height - 14}" text-anchor="middle">${h(chartLabels[index])}</text>
                    </g>
                `).join('')}
                <g class="rp-trend__probe" data-trend-probe data-points="${h(JSON.stringify(pointRows))}" transform="translate(${pointRows[finalIndex].x},${pointRows[finalIndex].y})" aria-hidden="true"><circle r="14"/><circle r="5"/></g>
            </svg>
            <ol class="rp-trend__readings">${chartValues.map((value, index) => `<li data-trend-reading="${index}"><span>${h(chartLabels[index])}</span><b>${value === null ? 'Belum tersedia' : h(exactValue(index))}</b></li>`).join('')}</ol>
            <figcaption>${h(note)} <span>Skala mengikuti rentang data.</span></figcaption>
        </figure>
    `;
};

/** Ranked horizontal bars that grow in on entry. */
const bars = ({ title, rows = [], total = 0, valueKey = 'current', tone = 'blue' }) => {
    const maximum = Math.max(1, ...rows.map((row) => asNumber(row.metric?.[valueKey] ?? row.value)));
    return `
        <section class="rp-paper rp-bars is-${h(tone)}" data-rv>
            ${cardHead(title, `${rows.length} komponen`)}
            <ol class="rp-bars__list">
                ${rows.map((row, index) => {
                    const value = asNumber(row.metric?.[valueKey] ?? row.value);
                    return `
                        <li style="--i:${index}; --w:${Math.max(2, percentOf(value, maximum)).toFixed(2)}%">
                            <span class="rp-bars__label"><b>${h(row.label || '-')}</b><small>${h(row.meta || `${formatPercent(percentOf(value, total))} dari total`)}</small></span>
                            <strong>${h(row.value_fmt || row.metric?.current_fmt || formatCurrency(value))}</strong>
                            <span class="rp-bars__track" aria-hidden="true"><i></i></span>
                        </li>
                    `;
                }).join('')}
            </ol>
        </section>
    `;
};

const quadrantTable = ({ title, history = {} }) => {
    const rows = (history.rows || []).slice(-7);
    const coverage = history.coverage || {};
    const meta = asNumber(coverage.source_total) > 0
        ? `${asNumber(coverage.classified)}/${asNumber(coverage.source_total)} RM terklasifikasi`
        : `${rows.length} bulan`;
    return `
        <section class="rp-paper rp-quadrant" data-rv>
            ${cardHead(title, meta)}
            ${rows.length ? `
                <ol class="rp-quadrant__list">
                    ${rows.map((row, index) => `
                        <li style="--i:${index}">
                            <span class="rp-quadrant__month">${h(row.label || '-')}</span>
                            <span class="rp-quadrant__stack" aria-label="Q4 ${formatInteger(row.q4)}, Q3 ${formatInteger(row.q3)}, Q2 ${formatInteger(row.q2)}, Q1 ${formatInteger(row.q1)}">
                                ${['q4', 'q3', 'q2', 'q1'].map((key) => `<i class="is-${key}" style="--w:${percentOf(row[key], row.total).toFixed(2)}%"><em>${formatInteger(row[key])}</em></i>`).join('')}
                            </span>
                            <b>${formatInteger(row.total)} RM</b>
                        </li>
                    `).join('')}
                </ol>
                <p class="rp-quadrant__legend"><span class="is-q4">Q4</span><span class="is-q3">Q3</span><span class="is-q2">Q2</span><span class="is-q1">Q1</span></p>
            ` : '<p class="rp-empty">Histori kuadran RM belum tersedia.</p>'}
        </section>
    `;
};

/* -------------------------------------------------------------- renderer -- */

export class ScrollReportRenderer extends StructuredDeckRenderer {
    constructor(options) {
        super(options);
        this.onSectionRendered = options.onSectionRendered || null;
    }

    renderSlide(index) {
        const section = document.getElementById(`pres-slide-${index}`);
        if (!section || !this.data) return;
        if ([4, 5, 7, 8, 9, 11, 12, 13].includes(index)) this.closeTimeseriesModal({ restoreFocus: false });
        if (index === 13) {
            this.trendChart?.destroy();
            this.trendChart = null;
        }
        if (index === 3) this.destroyMarketShareMap();

        const renderers = [
            () => this.scrollCover(),
            () => this.scrollAgenda(),
            () => this.scrollMarketShare(),
            () => this.scrollMapping(),
            () => this.scrollFundingSummary(),
            () => this.scrollFundingProducts(),
            () => this.scrollStrategies(),
            () => this.scrollLoanSummary(),
            () => this.scrollSegment('sme'),
            () => this.scrollSegment('consumer'),
            () => this.scrollMicro(),
            () => this.scrollQuality('sml'),
            () => this.scrollQuality('npl'),
            () => this.scrollTimeseries(),
            () => this.scrollPriorities(),
        ];
        section.innerHTML = renderers[index]?.() || '';
        this.renderChapterArt(index);
        section.classList.remove('is-section-loading');
        section.setAttribute('aria-busy', 'false');
        this.onSectionRendered?.(section, index);
    }

    portfolioArt(compact = false) {
        const credit = this.comparisonScope()?.credit || {};
        return portfolioSculpture({
            title: 'Komposisi outstanding',
            totalLabel: credit.total?.os?.current_fmt || '',
            compact,
            items: (credit.segments || []).map((segment, index) => ({
                key: segment.key, label: segment.label, value: segment.os?.current,
                formatted: segment.os?.current_fmt || '-', tone: ['blue', 'cyan', 'silver'][index % 3],
            })),
        });
    }

    qualityArt() {
        const total = this.comparisonScope()?.credit?.total || {};
        return qualityLens({
            title: 'SML dan NPL terhadap outstanding',
            items: ['sml', 'npl'].map((key) => ({
                key, label: key.toUpperCase(), value: total[key]?.current,
                formatted: total[key]?.current_fmt || '-', ratio: total[key]?.ratio_positions?.current,
                ratioFormatted: total[key]?.ratio_positions_fmt?.current || '-', tone: key === 'sml' ? 'amber' : 'red',
            })),
        });
    }

    renderChapterArt(index) {
        if (![0, 4, 7, 11, 12, 13].includes(index)) return;
        const visuals = { pinjaman: () => this.portfolioArt(true), kualitas: () => this.qualityArt() };
        Object.entries(visuals).forEach(([key, render]) => {
            const slot = document.querySelector(`[data-chapter-art="${key}"]`);
            if (slot) slot.innerHTML = render();
        });
    }

    /**
     * Figures for the 3D worlds, read through the same accessors as the tables,
     * so every height, fill and slice matches the numbers printed beside it.
     */
    worldData() {
        if (!this.data) return null;
        const comparison = this.comparisonScope() || {};
        const funding = comparison.funding || {};
        const credit = comparison.credit || {};
        const fundingTotal = asNumber(funding.total?.current);
        const qualityOf = (key) => {
            const metric = credit.total?.[key] || {};
            return { ratio: asNumber(metric.ratio_positions?.current), ratioLabel: metric.ratio_positions_fmt?.current || '-', nominalLabel: metric.current_fmt || '' };
        };
        const segmentsOf = (segments) => (segments || []).map((segment) => ({ key: segment.key, label: segment.label, value: asNumber(segment.os?.current), formatted: segment.os?.current_fmt || '-' }));
        const timeseries = this.data?.timeseries || {};
        const trendScope = this.timeseriesScope();
        const trendKeys = timeseries.groups?.business?.keys || ['simpanan', 'os'];
        const allSeries = this.timeseriesSeries(trendScope);

        return {
            scopeLabel: this.scopeLabel(),
            market: this.worldMarket(),
            mapping: this.worldMapping(),
            funding: {
                products: (funding.products || []).map((product) => ({
                    key: product.key, label: product.label, value: asNumber(product.current), formatted: product.current_fmt || '-',
                    shareLabel: fundingTotal > 0 ? `${formatPercent(percentOf(product.current, fundingTotal))} dari funding` : '',
                })),
            },
            credit: {
                branches: this.isArea()
                    ? (credit.branches || []).map((branch) => ({ name: branch.scope_label, formatted: branch.total?.os?.current_fmt || '', segments: segmentsOf(branch.segments) }))
                    : [{ name: this.scopeLabel(), formatted: credit.total?.os?.current_fmt || '', segments: segmentsOf(credit.segments) }],
            },
            quality: { sml: qualityOf('sml'), npl: qualityOf('npl') },
            trend: {
                labels: trendScope?.labels || timeseries.labels || [],
                series: trendKeys.map((key) => findByKey(allSeries, key)).filter(Boolean).map((item) => ({
                    key: item.key, label: item.label, color: item.color, values: item.values || [], display: item.display_values || [],
                })),
            },
            strategies: (this.data?.digital_strategy?.cards || []).slice(0, 8).map((card) => ({
                title: card.title || '-', value: card.current_value || '-', trend: card.trend || '-', tone: String(card.trend || '').includes('-') ? 'red' : 'green',
            })),
        };
    }

    worldMarket() {
        const payload = this.marketShareAreaPayload();
        const segment = this.marketShareSegmentPayload(payload);
        const kind = String(segment?.kind || 'deposit').toLowerCase();
        if (!payload?.available || !segment || kind === 'quality') return { total: null, branches: [] };
        const shareIndex = kind === 'loan' ? 16 : 14;
        const rows = Array.isArray(segment.rows) ? segment.rows.filter(Boolean) : [];
        const branchRows = this.marketShareAreaRows(segment);
        const totalRow = rows.find((row) => row?.total || normaliseMarketShareToken(row?.branch) === 'area6') || null;
        const summaryRow = this.isArea() ? (totalRow || branchRows[0]) : branchRows[0];
        const entry = (row, name) => ({ name, share: marketShareNumber(row?.values?.[shareIndex]), label: valueOrDash(row?.values?.[shareIndex]) });
        return {
            segment: segment.label || '',
            total: summaryRow ? entry(summaryRow, `${this.isArea() ? 'Area 6' : this.scopeLabel()}, ${segment.label || 'market share'}`) : null,
            branches: this.isArea() ? branchRows.map((row) => entry(row, row.branch || '-')) : [],
        };
    }

    worldMapping() {
        const mapping = this.marketShareMappingPayload();
        if (!mapping?.ready) return null;
        const units = this.marketShareActiveUnits(mapping);
        const codes = new Set(units.flatMap((unit) => (Array.isArray(unit?.district_codes) ? unit.district_codes.map(String) : [])));
        const features = (mapping.geojson?.features || []).filter((feature) => codes.has(String(feature?.properties?.KDCPUM || '')));
        if (!features.length) return null;
        const boundsOf = (geometry) => {
            const points = (geometry?.type === 'Polygon' ? [geometry.coordinates] : (geometry?.coordinates || [])).flat(2);
            const lons = points.map((point) => Number(point[0])).filter(Number.isFinite);
            const lats = points.map((point) => Number(point[1])).filter(Number.isFinite);
            return { minLon: Math.min(...lons), maxLon: Math.max(...lons), minLat: Math.min(...lats), maxLat: Math.max(...lats) };
        };
        const centerOf = (bounds) => ({ lon: (bounds.minLon + bounds.maxLon) / 2, lat: (bounds.minLat + bounds.maxLat) / 2 });
        const districts = features.map((feature) => {
            const code = String(feature.properties.KDCPUM);
            const values = this.marketShareDistrictValues(code, units, 'total');
            const bounds = boundsOf(feature.geometry);
            return {
                code,
                name: String(feature.properties.WADMKC || 'Kecamatan'),
                regency: String(feature.properties.WADMKK || '-'),
                geometry: feature.geometry,
                bounds,
                center: centerOf(bounds),
                potential: values.potential,
                existing: values.existing,
                penetration: values.penetration,
                penetrationLabel: formatPercent(values.penetration),
                potentialLabel: formatInteger(values.potential),
                existingLabel: formatInteger(values.existing),
            };
        });
        const merge = (list) => list.reduce((carry, item) => ({
            minLon: Math.min(carry.minLon, item.bounds.minLon), maxLon: Math.max(carry.maxLon, item.bounds.maxLon),
            minLat: Math.min(carry.minLat, item.bounds.minLat), maxLat: Math.max(carry.maxLat, item.bounds.maxLat),
        }), { minLon: Infinity, maxLon: -Infinity, minLat: Infinity, maxLat: -Infinity });
        const all = merge(districts);
        const regencies = Object.entries(districts.reduce((carry, district) => {
            (carry[district.regency] ||= []).push(district);
            return carry;
        }, {})).map(([name, list]) => {
            const potential = list.reduce((sum, item) => sum + item.potential, 0);
            const existing = list.reduce((sum, item) => sum + item.existing, 0);
            return { name: name.toLowerCase().replace(/(^|\s)\S/g, (letter) => letter.toUpperCase()), center: centerOf(merge(list)), penetrationLabel: formatPercent(potential > 0 ? (existing / potential) * 100 : 0) };
        });
        return { districts, regencies, center: centerOf(all), span: Math.max(all.maxLon - all.minLon, all.maxLat - all.minLat), units: units.length };
    }

    /* ---------------------------------------------------------- 0 cover -- */
    scrollCover() {
        const cards = this.summaryScope().cards || [];
        const os = findByKey(cards, 'os') || {};
        const funding = findByKey(cards, 'simpanan') || {};
        const sml = findByKey(cards, 'sml') || {};
        const npl = findByKey(cards, 'npl') || {};
        const image = sameOriginAsset(this.data?.assets?.branch_building, '/images/bri-area6-building.png');
        const briLogo = sameOriginAsset(this.data?.assets?.bri_logo, '/images/bri-logo-template.png');
        const danantara = sameOriginAsset(this.data?.assets?.danantara_logo, '/images/danantara-logo-template.png');
        const agenda = [
            ['Funding', 'Total, segmen, produk'],
            ['Pinjaman', 'SME, Konsumer, Mikro'],
            ['Kualitas', 'SML dan NPL'],
            ['Produktivitas', 'RM, Mantri, KTS'],
        ];

        return `
            <div class="rp-cover" data-sp data-world-track="flow">
                <div class="rp-cover__photo" style="background-image:url('${h(image)}')" aria-hidden="true"></div>
                <div class="rp-cover__scrim" aria-hidden="true"></div>
                <div class="rp-cover__content">
                    <p class="rp-cover__brand" data-rv style="--i:0">
                        <img src="${h(danantara)}" alt="Danantara Indonesia">
                        <img src="${h(briLogo)}" alt="Bank Rakyat Indonesia">
                    </p>
                    <h1 class="rp-cover__title" data-rv style="--i:1">Kinerja ${h(this.scopeLabel())}</h1>
                    <p class="rp-cover__sub" data-rv style="--i:2">Funding, Pinjaman, Kualitas, dan Produktivitas</p>
                    <p class="rp-cover__lede" data-rv style="--i:3">${h(this.period())}. Materi pendukung asistensi dengan pembacaan angka dari total menuju segmen, produk, cabang, dan penggerak bisnis.</p>
                    <ul class="rp-cover__agenda">
                        ${agenda.map(([title, meta], index) => `<li data-rv style="--i:${index + 4}"><strong>${h(title)}</strong><small>${h(meta)}</small></li>`).join('')}
                    </ul>
                </div>
                <div class="rp-cover__art pres-world pres-world--globe" data-world="globe" data-rv style="--i:3"><div class="pres-world__fallback">${this.portfolioArt(true)}</div></div>
                ${figures([
                    { label: 'Funding', value: funding.value || '-', meta: funding.trend ? `${funding.trend} tren` : 'Posisi terkini', tone: 'blue' },
                    { label: 'Outstanding', value: os.value || '-', meta: os.trend ? `${os.trend} tren` : 'Posisi terkini', tone: 'cyan' },
                    { label: 'SML', value: sml.value || '-', meta: sml.ratio || 'Rasio kualitas', tone: 'amber' },
                    { label: 'NPL', value: npl.value || '-', meta: npl.ratio || 'Rasio kualitas', tone: 'red' },
                ], 'rp-cover__figures')}
                <p class="rp-cover__foot">Area 6 Region 13. Data diperbarui ${h(this.data?.meta?.generated_at || this.period())}</p>
            </div>
        `;
    }

    /* --------------------------------------------------------- 1 agenda -- */
    scrollAgenda() {
        const funding = this.summaryCard('simpanan');
        const os = this.summaryCard('os');
        const sml = this.summaryCard('sml');
        const npl = this.summaryCard('npl');
        const groups = [
            { chapter: 2, title: 'Market Intelligence', part: 'Market share dan peta penetrasi', description: 'Market Share Area 6 per bagian dan peta penetrasi membuka deck dengan konteks posisi BRI terhadap pasar.' },
            { chapter: 4, title: 'Funding', part: 'Summary, produk, delapan strategi', description: 'Summary cabang atau segmen, produk dana, timeseries, dan delapan strategi penguatan funding.' },
            { chapter: 7, title: 'Pinjaman', part: 'Outstanding, SME, Konsumer, Mikro', description: 'Outstanding total, SME, Konsumer, dan Mikro dari posisi historis menuju RKA dan penggerak produktivitas.' },
            { chapter: 11, title: 'Kualitas', part: 'SML dan NPL', description: 'SML dibaca sebagai early warning, lalu NPL sebagai prioritas recovery berdasarkan nominal dan rasio.' },
            { chapter: 13, title: 'Tren dan Aksi', part: 'Timeseries dan prioritas', description: 'Timeseries terintegrasi menghubungkan arah indikator dengan prioritas eksekusi berikutnya.' },
        ];

        return `
            ${sectionHead({
                title: 'Daftar Isi dan Alur Keputusan',
                subtitle: 'Lima bab ringkas dengan urutan pembacaan yang konsisten dan dapat ditindaklanjuti.',
                narrative: `Laporan dibaca berurutan dari konteks pasar, sumber dana, penyaluran, kualitas, tren, hingga keputusan. Seluruh bagian mengikuti scope ${this.scopeLabel()}.`,
                period: this.period(),
            })}
            <div class="rp-agenda">
                <ol class="rp-timeline" data-sp>
                    ${groups.map((group, index) => `
                        <li class="rp-timeline__item" data-rv style="--i:${index}">
                            <a href="#slide=${group.chapter}" data-spine-target="${group.chapter}">
                                <strong>${h(group.title)}</strong>
                                <small>${h(group.part)}</small>
                            </a>
                            <p>${h(group.description)}</p>
                        </li>
                    `).join('')}
                </ol>
                <aside class="rp-agenda__pulse">
                    <p class="rp-agenda__route" data-rv style="--i:0">Market, Funding, Pinjaman, Kualitas, Aksi</p>
                    ${figures([
                        { label: 'Funding', value: funding.value || '-', tone: 'blue' },
                        { label: 'Outstanding', value: os.value || '-', tone: 'cyan' },
                        { label: 'Rasio SML', value: sml.ratio || '-', tone: 'amber' },
                        { label: 'Rasio NPL', value: npl.ratio || '-', tone: 'red' },
                    ], 'is-grid')}
                </aside>
            </div>
            ${this.insights([
                { label: 'Scope aktif', value: this.scopeLabel(), meta: 'Berlaku untuk seluruh laporan' },
                { label: 'Periode aktif', value: this.period(), meta: 'Tanggal terakhir yang dipilih' },
                { label: 'Format analisis', value: 'Posisi, delta, RKA', meta: 'Diperkuat distribusi dan tren' },
            ])}
        `;
    }

    insights(items) {
        return `
            <ul class="rp-insights">
                ${items.map((item, index) => `
                    <li class="is-${h(item.tone || 'blue')}" data-rv style="--i:${index}">
                        <span>${h(item.label)}</span>
                        <strong>${h(valueOrDash(item.value))}</strong>
                        ${item.meta ? `<small>${h(item.meta)}</small>` : ''}
                    </li>
                `).join('')}
            </ul>
        `;
    }

    /* ---------------------------------------------- 2 market share area -- */
    scrollMarketShare() {
        const payload = this.marketShareAreaPayload();
        const segments = this.marketShareSegments(payload);
        const segment = this.marketShareSegmentPayload(payload);
        const sourceRows = Array.isArray(segment?.rows) ? segment.rows.filter(Boolean) : [];
        const branchRows = this.marketShareAreaRows(segment);
        const totalRow = sourceRows.find((row) => row?.total || normaliseMarketShareToken(row?.branch) === 'area6') || null;
        const summaryRow = this.isArea() ? (totalRow || branchRows[0]) : branchRows[0];
        const summaryValues = Array.isArray(summaryRow?.values) ? summaryRow.values : [];
        const kind = String(segment?.kind || 'deposit').toLowerCase();
        const columns = Array.isArray(segment?.headers?.columns) ? segment.headers.columns : [];
        const scopeLabel = this.isArea() ? 'Area 6' : this.scopeLabel();
        const available = Boolean(payload?.available && segment && summaryRow && branchRows.length);
        const isQuality = kind === 'quality';
        const isLoan = kind === 'loan';
        const currentShareIndex = isQuality ? 16 : (isLoan ? 16 : 14);
        const secondaryShareIndex = isQuality ? 17 : currentShareIndex - 1;
        const briCurrentIndex = isQuality ? 1 : 2;
        const industryCurrentIndex = isQuality ? 3 : (isLoan ? 9 : 6);
        const briYtdIndex = isQuality ? 7 : (isLoan ? 6 : 3);
        const marketYoyIndex = isQuality ? 19 : currentShareIndex + 1;
        const marketYtdIndex = isQuality ? 21 : currentShareIndex + 2;
        const value = (index) => valueOrDash(summaryValues[index]);
        const share = marketShareNumber(summaryValues[currentShareIndex]);
        const qualityNpl = marketShareNumber(summaryValues[3]);
        const status = isQuality
            ? (qualityNpl <= 3 ? { label: 'Kualitas terjaga', tone: 'green' } : (qualityNpl <= 5 ? { label: 'Perlu pengawalan', tone: 'amber' } : { label: 'Risiko perlu ditekan', tone: 'red' }))
            : (share >= 40 ? { label: 'Posisi kuat', tone: 'green' } : (share >= 25 ? { label: 'Basis tumbuh', tone: 'blue' } : { label: 'Ruang penetrasi luas', tone: 'amber' }));
        const chart = isQuality
            ? { title: 'Market share kualitas per cabang', primaryLabel: 'SML', primaryIndex: 16, secondaryLabel: 'NPL', secondaryIndex: 17 }
            : { title: 'Perbandingan market share cabang', primaryLabel: columns[currentShareIndex] || 'Terkini', primaryIndex: currentShareIndex, secondaryLabel: columns[secondaryShareIndex] || 'Pembanding', secondaryIndex: secondaryShareIndex };
        const chartRows = branchRows.map((row) => {
            const values = Array.isArray(row?.values) ? row.values : [];
            return {
                branch: row?.branch || '-',
                primaryLabel: valueOrDash(values[chart.primaryIndex]),
                primary: marketShareNumber(values[chart.primaryIndex]),
                secondaryLabel: valueOrDash(values[chart.secondaryIndex]),
                secondary: marketShareNumber(values[chart.secondaryIndex]),
            };
        });
        const chartMaximum = Math.max(1, ...chartRows.flatMap((row) => [row.primary, row.secondary])) * 1.08;
        const strongest = [...chartRows].sort((left, right) => right.primary - left.primary)[0] || null;
        const focus = [...chartRows].sort((left, right) => left.primary - right.primary)[0] || null;
        const tableColumns = isQuality
            ? [
                { label: 'SML BRI', index: 1 }, { label: 'NPL BRI', index: 3 }, { label: 'MS SML', index: 16 },
                { label: 'MS NPL', index: 17 }, { label: 'YTD SML', index: 20, delta: true }, { label: 'YTD NPL', index: 21, delta: true },
            ]
            : [
                { label: `OS BRI ${columns[briCurrentIndex] || 'Terkini'}`, index: briCurrentIndex },
                { label: `Industri ${columns[industryCurrentIndex] || 'Terkini'}`, index: industryCurrentIndex },
                { label: `MS ${columns[secondaryShareIndex] || 'Pembanding'}`, index: secondaryShareIndex },
                { label: `MS ${columns[currentShareIndex] || 'Terkini'}`, index: currentShareIndex },
                { label: 'Delta YoY', index: marketYoyIndex, delta: true },
                { label: 'Delta YTD', index: marketYtdIndex, delta: true },
            ];
        const tableRows = this.isArea() && totalRow ? [...branchRows, totalRow] : branchRows;
        const groups = segments.reduce((carry, item) => {
            (carry[item?.group || 'Lainnya'] ||= []).push(item);
            return carry;
        }, {});
        const controls = `
            <label class="rp-select" data-psd-marketshare-interactive>
                <span>Bagian</span>
                <select data-psd-marketshare-segment aria-label="Pilih bagian Market Share Area 6">
                    ${Object.entries(groups).map(([group, items]) => `
                        <optgroup label="${h(group)}">
                            ${items.map((item) => `<option value="${h(item.key)}" ${item.key === segment?.key ? 'selected' : ''}>${h(item.label || item.key)}</option>`).join('')}
                        </optgroup>
                    `).join('')}
                </select>
            </label>
        `;
        const narrative = available
            ? (isQuality
                ? `${scopeLabel} pada ${segment?.label || 'kualitas pinjaman'} mencatat SML BRI ${value(1)} dan NPL BRI ${value(3)}. Porsi BRI terhadap kualitas industri berada pada ${value(16)} untuk SML dan ${value(17)} untuk NPL.`
                : `${scopeLabel} pada ${segment?.label || 'segmen terpilih'} mencatat OS BRI ${value(briCurrentIndex)} dari total industri ${value(industryCurrentIndex)}, sehingga market share terkini ${value(currentShareIndex)}. Pertumbuhan YTD BRI berada di ${value(briYtdIndex)} dan perubahan market share YTD ${value(marketYtdIndex)}.`)
            : 'Data Market Share Area 6 sedang dimuat untuk bagian dan scope aktif.';
        const cards = isQuality
            ? [
                { label: 'SML BRI', value: value(1), meta: 'Rasio kualitas BRI', tone: 'amber' },
                { label: 'NPL BRI', value: value(3), meta: status.label, tone: status.tone },
                { label: 'Market share SML', value: value(16), meta: scopeLabel, tone: 'cyan' },
                { label: 'Market share NPL', value: value(17), meta: scopeLabel, tone: 'blue' },
            ]
            : [
                { label: 'OS BRI', value: value(briCurrentIndex), meta: columns[briCurrentIndex] || 'Posisi terkini', tone: 'blue' },
                { label: 'Total industri', value: value(industryCurrentIndex), meta: columns[industryCurrentIndex] || 'Posisi terkini', tone: 'cyan' },
                { label: 'Market share', value: value(currentShareIndex), meta: status.label, tone: status.tone },
                { label: 'Pertumbuhan YTD BRI', value: value(briYtdIndex), meta: 'Terhadap Desember', tone: marketShareNumber(summaryValues[briYtdIndex]) >= 0 ? 'green' : 'red' },
            ];
        const insightItems = isQuality
            ? [
                { label: 'SML terendah', value: focus?.branch || '-', meta: focus?.primaryLabel || 'Belum tersedia', tone: 'green' },
                { label: 'NPL BRI scope aktif', value: value(3), meta: status.label, tone: status.tone },
                { label: 'Perubahan NPL YTD', value: value(7), meta: payload?.source || 'Sumber market share', tone: marketShareNumber(summaryValues[7]) <= 0 ? 'green' : 'amber' },
            ]
            : [
                { label: 'Market share tertinggi', value: strongest?.branch || '-', meta: strongest?.primaryLabel || 'Belum tersedia', tone: 'green' },
                { label: 'Prioritas penguatan', value: focus?.branch || '-', meta: focus?.primaryLabel || 'Belum tersedia', tone: 'amber' },
                { label: 'Perubahan market share YTD', value: value(marketYtdIndex), meta: payload?.source || 'Sumber market share', tone: marketShareNumber(summaryValues[marketYtdIndex]) >= 0 ? 'green' : 'red' },
            ];

        return `
            ${stickyBar(`Market share ${scopeLabel}`, value(currentShareIndex))}
            ${sectionHead({
                title: `Market Share ${scopeLabel}`,
                subtitle: `${segment?.label || 'Memuat data'}. ${payload?.period || '-'}, ${payload?.unit || 'Rp dalam Miliar'}, BRI dibanding total industri.`,
                narrative,
                period: payload?.period || this.period(),
                controls,
            })}
            ${figures(cards)}
            <div class="rp-stackcards">
                <section class="rp-paper rp-pairs" data-rv>
                    ${cardHead(chart.title, `${chartRows.length} cabang`)}
                    <p class="rp-pairs__legend"><span class="is-primary">${h(chart.primaryLabel)}</span><span class="is-secondary">${h(chart.secondaryLabel)}</span></p>
                    <ol class="rp-pairs__list">
                        ${chartRows.length ? chartRows.map((row, index) => `
                            <li style="--i:${index}">
                                <strong>${h(row.branch)}</strong>
                                <span class="rp-pairs__bar is-primary" style="--w:${Math.max(2, percentOf(row.primary, chartMaximum)).toFixed(2)}%"><i></i><b>${h(row.primaryLabel)}</b></span>
                                <span class="rp-pairs__bar is-secondary" style="--w:${Math.max(2, percentOf(row.secondary, chartMaximum)).toFixed(2)}%"><i></i><b>${h(row.secondaryLabel)}</b></span>
                            </li>
                        `).join('') : '<li class="rp-empty">Data cabang belum tersedia.</li>'}
                    </ol>
                </section>
                ${dataTable({
                    title: 'Ringkasan cabang',
                    meta: `${tableRows.length} baris`,
                    columns: [
                        { label: 'Cabang', render: (row) => h(row?.branch || '-') },
                        ...tableColumns.map((column) => ({
                            label: h(column.label),
                            render: (row) => {
                                const cell = valueOrDash((row?.values || [])[column.index]);
                                return column.delta ? `<span class="rp-delta ${marketShareValueTone(cell).replace('is-positive', 'is-green').replace('is-negative', 'is-red').replace('is-neutral', 'is-neutral')}">${h(cell)}</span>` : h(cell);
                            },
                        })),
                    ],
                    rows: tableRows.map((row) => ({ ...row, className: row?.total ? 'is-total' : '' })),
                    empty: 'Data cabang belum tersedia.',
                })}
            </div>
            ${this.insights(insightItems)}
        `;
    }

    /* ---------------------------------------------------- 3 mapping map -- */
    scrollMapping() {
        const mapping = this.marketShareMappingPayload();
        const units = this.marketShareActiveUnits(mapping);
        const totals = this.marketShareMappingTotals(mapping, units, 'total');
        const districtCodes = new Set(units.flatMap((unit) => (Array.isArray(unit?.district_codes) ? unit.district_codes.map(String) : [])));
        const allRanked = units
            .map((unit) => ({ unit, score: asNumber(this.marketShareUnitValues(unit, 'total')?.penetration) }))
            .sort((left, right) => right.score - left.score);
        const ranked = allRanked.slice(0, 6);
        const weakest = allRanked.at(-1) || null;
        const maximumScore = Math.max(1, ...ranked.map((row) => row.score));
        const available = Boolean(mapping?.ready && units.length);
        const scopeLabel = this.isArea() ? 'Area 6' : this.scopeLabel();
        const dashboard = this.isArea() && mapping?.dashboard?.ready ? mapping.dashboard : null;
        const items = (source) => (Array.isArray(source) ? source : Object.values(source || {})).filter((item) => item && typeof item === 'object');
        const headlineItems = items(dashboard?.headlineMetrics || dashboard?.headline_metrics || dashboard?.headline);
        const totalItems = items(dashboard?.totalMetrics || dashboard?.total_metrics || dashboard?.totals);
        const demographicItems = items(dashboard?.demographics);
        const highlightItems = items(dashboard?.highlights || dashboard?.highlight);
        const metricOf = (pattern) => totalItems.find((item) => pattern.test(String(item?.label || item?.name || '')));
        const potentialItem = metricOf(/potensi/i);
        const existingItem = metricOf(/debitur|existing/i);
        const penetrationItem = metricOf(/penetrasi|share/i);
        const potential = potentialItem ? marketShareNumber(potentialItem.value) : asNumber(totals.potential);
        const existing = existingItem ? marketShareNumber(existingItem.value) : asNumber(totals.existing);
        const penetration = penetrationItem ? marketShareNumber(penetrationItem.value) : asNumber(totals.penetration);
        const gap = Math.max(0, potential - existing);
        const status = penetration >= 50 ? { label: 'Penetrasi kuat', tone: 'green' } : (penetration >= 30 ? { label: 'Penetrasi berkembang', tone: 'blue' } : { label: 'Ruang akuisisi tinggi', tone: 'amber' });
        const potentialText = valueOrDash(potentialItem?.value, formatInteger(potential));
        const existingText = valueOrDash(existingItem?.value, formatInteger(existing));
        const penetrationText = valueOrDash(penetrationItem?.value, formatPercent(penetration));
        const contextCard = demographicItems.find((item) => item?.label && item?.value) || headlineItems.find((item) => item?.label && item?.value) || null;
        const headlineContext = headlineItems.slice(0, 2).map((item) => `${item?.label || 'KPI'} ${item?.value || '-'}`).join(', ');
        const highlightContext = highlightItems.slice(0, 1).map((item) => item?.text || item?.description || item?.note || [item?.label || item?.title, item?.value].filter(Boolean).join(' ')).filter(Boolean).join(' ');
        const narrative = available
            ? `${scopeLabel} mencakup ${formatInteger(units.length)} unit kerja pada ${formatInteger(districtCodes.size)} kecamatan. BRI telah menjangkau ${existingText} dari ${potentialText} total potensi atau ${penetrationText}; gap ${formatInteger(gap)} calon nasabah menjadi ruang ekspansi pasar.`
            : 'Polygon dan data unit kerja sedang dimuat dari sheet REKAP.';
        const reading = available
            ? [
                `${status.label}. ${ranked[0]?.unit?.name || 'Unit teratas'} memimpin pada ${this.formatMarketShareMappingMetric(ranked[0]?.score, 'penetration')}, sedangkan ${weakest?.unit?.name || 'unit terbawah'} berada di ${this.formatMarketShareMappingMetric(weakest?.score, 'penetration')} dan menjadi titik prioritas penguatan penetrasi.`,
                headlineContext ? `Konteks dashboard: ${headlineContext}.` : '',
                highlightContext ? `Sorotan: ${highlightContext}.` : '',
            ].filter(Boolean).join(' ')
            : 'Status penetrasi akan terbentuk setelah polygon dan data unit tersedia.';

        return `
            ${available ? this.orbitDescent({ scopeLabel, units, districtCount: districtCodes.size, potentialText, existingText, penetrationText }) : ''}
            ${stickyBar(`Mapping market share ${scopeLabel}`, penetrationText)}
            ${sectionHead({
                title: `Mapping Market Share ${scopeLabel}`,
                subtitle: `Total seluruh sektor. Pewarnaan penetrasi absolut 0 sampai 100%, sheet ${mapping?.sheet || 'REKAP'}.`,
                narrative,
                period: mapping?.updated_at || this.period(),
                periodLabel: mapping?.updated_at ? 'Diperbarui' : 'Posisi data',
            })}
            ${figures([
                { label: 'Potensi', value: potentialText, meta: 'nasabah', tone: 'blue' },
                { label: 'Existing', value: existingText, meta: 'nasabah', tone: 'cyan' },
                { label: 'Penetrasi', value: penetrationText, meta: status.label, tone: status.tone },
                contextCard
                    ? { label: contextCard.label, value: contextCard.value, meta: 'Dashboard workbook', tone: 'blue' }
                    : { label: 'Cakupan', value: formatInteger(districtCodes.size), meta: 'kecamatan', tone: 'blue' },
            ])}
            <div class="rp-map-stage">
                <section class="rp-paper rp-map" data-rv>
                    ${cardHead(mapping?.title || 'Peta potensi dan penetrasi', available ? `${districtCodes.size} kecamatan` : 'Memuat polygon')}
                    <div class="rp-map__shell" data-psd-marketshare-interactive>
                        <div id="psd-marketshare-map" class="psd-marketshare-map rp-map__canvas" role="application" tabindex="0" aria-label="Peta market share ${h(scopeLabel)}"></div>
                        <div class="psd-marketshare-map-state rp-map__state" data-psd-marketshare-map-state><span>${available ? 'Menyiapkan polygon wilayah...' : 'Data mapping belum tersedia.'}</span></div>
                        <div class="rp-map__legend" aria-label="Legenda warna peta">
                            <strong>Penetrasi</strong>
                            <span class="rp-map__scale" data-psd-marketshare-legend-scale></span>
                            <small><b data-psd-marketshare-legend-min>0%</b><b data-psd-marketshare-legend-max>100%</b></small>
                        </div>
                    </div>
                </section>
                <aside class="rp-map__side">
                    <div class="rp-signal is-${h(status.tone)}" id="psd-marketshare-map-selection" data-rv>
                        <span>Executive signal: ${h(status.label)}</span>
                        <strong>${h(scopeLabel)}, penetrasi ${h(penetrationText)}</strong>
                        <p>${h(reading)}</p>
                    </div>
                    ${bars({
                        title: 'Peringkat unit, penetrasi',
                        rows: ranked.map((entry) => ({
                            label: entry.unit?.name || entry.unit?.label || '-',
                            meta: entry.unit?.branch_label || scopeLabel,
                            value: entry.score,
                            value_fmt: this.formatMarketShareMappingMetric(entry.score, 'penetration'),
                        })),
                        total: maximumScore,
                        tone: 'cyan',
                    })}
                    <p class="rp-source">Workbook ${h(mapping?.sheet || 'REKAP')}, diperbarui ${h(mapping?.updated_at || '-')}. ${h(mapping?.source?.label || 'Batas administratif')}. Nilai unit multi-kecamatan dialokasikan merata.</p>
                </aside>
            </div>
            ${this.insights([
                { label: 'Penetrasi agregat', value: penetrationText, meta: status.label, tone: status.tone },
                { label: 'Gap terhadap potensi', value: `${formatInteger(gap)} nasabah`, meta: `${formatInteger(districtCodes.size)} kecamatan terpetakan`, tone: 'amber' },
                { label: 'Unit penetrasi tertinggi', value: ranked[0]?.unit?.name || '-', meta: ranked[0] ? this.formatMarketShareMappingMetric(ranked[0].score, 'penetration') : 'Belum tersedia', tone: 'cyan' },
            ])}
        `;
    }

    /**
     * Signature move: the reader falls from orbit onto the kecamatan of the
     * active scope. The 3D stage pins for 3.4 viewport heights while three
     * real-copy beats hand over against the same scroll clock (--wp).
     */
    orbitDescent({ scopeLabel, units, districtCount, potentialText, existingText, penetrationText }) {
        const regencies = this.worldMapping()?.regencies || [];
        const words = ['Nol', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan'];
        const count = words[regencies.length] || String(regencies.length);
        const names = regencies.map((regency) => regency.name);
        const list = names.length > 1 ? `${names.slice(0, -1).join(', ')} dan ${names.at(-1)}` : (names[0] || scopeLabel);
        return `
            <div class="rp-orbit" data-world-track="pin">
                <div class="rp-orbit__stage">
                    <div class="pres-world pres-world--descent" data-world="descent"></div>
                    <div class="rp-orbit__veil" aria-hidden="true"></div>
                    <div class="rp-orbit__beats">
                        <section class="rp-orbit__beat" style="--from:-1;--to:0.3" aria-label="Dari orbit">
                            <h3>Mulai dari Bumi.</h3>
                            <p>${h(scopeLabel)} adalah ${h(formatInteger(districtCount))} kecamatan di sisi barat Jawa Timur. Gulir untuk turun ke sana.</p>
                        </section>
                        <section class="rp-orbit__beat" style="--from:0.33;--to:0.64" aria-label="Wilayah">
                            <h3>${h(count)} wilayah, satu pasar.</h3>
                            <p>${h(list)}. ${h(formatInteger(units.length))} unit kerja menjangkau ${h(existingText)} dari ${h(potentialText)} potensi nasabah.</p>
                        </section>
                        <section class="rp-orbit__beat" style="--from:0.68;--to:2" aria-label="Cara membaca prisma">
                            <h3>Setiap prisma adalah satu kecamatan.</h3>
                            <p>Tinggi dan terang prisma mengikuti penetrasi. Penetrasi agregat ${h(penetrationText)}. Arahkan kursor ke prisma untuk membaca angkanya.</p>
                            <p class="rp-orbit__legend"><span>0%</span><i aria-hidden="true"></i><span>100%</span></p>
                        </section>
                    </div>
                    <div class="rp-orbit__tip" data-orbit-tip role="status" hidden></div>
                </div>
            </div>
        `;
    }

    /* ---------------------------------------- shared comparison section -- */
    comparisonSection({ sticky, head, hero, table, trend, side }) {
        return `
            ${stickyBar(sticky.label, sticky.figure)}
            ${sectionHead(head)}
            ${figures(hero)}
            <div class="rp-split rp-split--visual">
                ${trendChart(trend)}
                ${side}
            </div>
            ${comparisonTable(table)}
        `;
    }

    /* ------------------------------------------------ 4 funding summary -- */
    scrollFundingSummary() {
        const periods = this.comparisonPeriods();
        const funding = this.comparisonScope().funding || {};
        const total = funding.total || {};
        const sourceRows = this.isArea()
            ? (funding.branches || []).map((branch) => ({ scope: branch.scope_label, dimension: 'Total Simpanan', metric: branch.total }))
            : (funding.segments || []).map((segment) => ({ scope: this.scopeLabel(), dimension: segment.label, metric: segment }));
        const rows = [{ scope: this.scopeLabel(), dimension: this.isArea() ? 'TOTAL AREA' : 'TOTAL CABANG', metric: total, className: 'is-total' }, ...sourceRows];
        const distribution = this.isArea()
            ? (funding.branches || []).map((branch) => ({ label: branch.scope_label, metric: branch.total }))
            : (funding.segments || []).map((segment) => ({ label: segment.label, metric: segment }));
        const leader = maxBy(distribution.map((row) => ({ ...row, current: asNumber(row.metric?.current) })), 'current');

        return this.comparisonSection({
            sticky: { label: `Summary funding ${this.scopeLabel()}`, figure: total.current_fmt },
            head: {
                title: `Summary Funding ${this.scopeLabel()}`,
                subtitle: this.isArea() ? 'Perbandingan total Area 6 dan cabang dengan posisi historis lengkap.' : 'Perbandingan total cabang dan segmen Ritel, Wholesale, serta Mikro.',
                narrative: this.isArea()
                    ? `Funding ${this.scopeLabel()} berada pada ${total.current_fmt || '-'}. Tabel membandingkan total Area dengan empat cabang, dilengkapi posisi historis, delta, dan RKA.`
                    : `Funding ${this.scopeLabel()} berada pada ${total.current_fmt || '-'}. Karena scope cabang aktif, pembacaan dipecah menjadi Ritel, Wholesale, dan Mikro.`,
                period: periods.current?.label || this.period(),
            },
            hero: [
                { label: 'Posisi funding', value: total.current_fmt || '-', meta: total.achievement_fmt ? `RKA ${total.achievement_fmt}` : 'Posisi terbaru', tone: 'blue' },
                { label: 'Kontributor terbesar', value: leader?.label || '-', meta: leader?.metric?.current_fmt || '-', tone: 'cyan' },
                { label: 'Momentum MtD', value: total.deltas_fmt?.mtd || '-', meta: periods.mtd?.label || '-', tone: asNumber(total.deltas?.mtd) >= 0 ? 'green' : 'red' },
            ],
            table: { title: this.isArea() ? 'Funding area dan cabang' : 'Funding cabang dan segmen', meta: `${rows.length} baris analisis`, periods, rows },
            trend: { title: 'Timeseries total funding', labels: this.timeseriesScope()?.labels || [], series: this.timeseriesSeries('simpanan'), seriesKeys: ['simpanan'], tone: 'blue', note: `Tren ${this.scopeLabel()} sampai ${periods.current?.label || this.period()}.` },
            side: bars({ title: this.isArea() ? 'Kontribusi funding per cabang' : 'Komposisi funding per segmen', rows: distribution, total: asNumber(total.current), tone: 'blue' }),
        });
    }

    /* ----------------------------------------------- 5 funding products -- */
    scrollFundingProducts() {
        const periods = this.comparisonPeriods();
        const funding = this.comparisonScope()?.funding || {};
        const products = funding.products || [];
        const selected = findByKey(products, this.fundingProduct) || products[0] || {};
        if (selected.key) this.fundingProduct = selected.key;
        const rows = products.map((product) => ({ scope: this.scopeLabel(), dimension: product.label, metric: product, className: product.key === selected.key ? 'is-selected' : '' }));
        const branchRows = this.isArea()
            ? (funding.branches || []).map((branch) => ({ label: branch.scope_label, metric: findByKey(branch.products, selected.key) || {} }))
            : products.map((product) => ({ label: product.label, metric: product }));
        const total = branchRows.reduce((sum, row) => sum + asNumber(row.metric?.current), 0);
        const tone = selected.key === 'deposito' ? 'amber' : 'blue';

        return this.comparisonSection({
            sticky: { label: `Funding per produk, ${selected.label || '-'}`, figure: selected.current_fmt },
            head: {
                title: 'Summary Funding per Produk',
                subtitle: 'Giro, Tabungan, dan Deposito dibandingkan pada tujuh posisi historis serta lima delta utama.',
                narrative: `${selected.label || 'Produk'} berada pada ${selected.current_fmt || '-'} atau ${formatPercent(percentOf(selected.current, funding.total?.current))} dari funding ${this.scopeLabel()}. Pilihan produk mengubah timeseries dan distribusi tanpa mengubah scope.`,
                period: periods.current?.label || this.period(),
                controls: segmented(products, 'data-psd-funding-product', selected.key),
            },
            hero: [
                { label: 'Produk terpilih', value: selected.current_fmt || '-', meta: selected.label || '-', tone },
                { label: 'Delta YoY', value: selected.deltas_fmt?.yoy || '-', meta: periods.yoy?.label || '-', tone: asNumber(selected.deltas?.yoy) >= 0 ? 'green' : 'red' },
                { label: 'Delta MtD', value: selected.deltas_fmt?.mtd || '-', meta: periods.mtd?.label || '-', tone: asNumber(selected.deltas?.mtd) >= 0 ? 'green' : 'red' },
            ],
            table: { title: 'Matriks produk funding', meta: `${products.length} produk`, periods, rows },
            trend: { title: `Timeseries ${selected.label || 'produk'}`, labels: this.timeseriesScope()?.labels || [], series: this.timeseriesSeries(selected.key || 'giro'), seriesKeys: [selected.key || 'giro'], tone, note: `Produk aktif: ${selected.label || '-'}. Nilai nominal tampil pada setiap titik.` },
            side: bars({ title: this.isArea() ? `Distribusi ${selected.label || 'produk'} per cabang` : 'Komposisi produk cabang', rows: branchRows, total, tone }),
        });
    }

    /* ---------------------------------------------------- 6 strategies -- */
    scrollStrategies() {
        const scope = findScope(this.data?.digital_strategy?.scopes, this.scopeKey()) || {};
        const digitalRows = Array.isArray(scope?.digital?.rows) ? scope.digital.rows : [];
        const casaRows = Array.isArray(scope?.casa_debitur?.rows) ? scope.casa_debitur.rows : [];
        const payrollRows = Array.isArray(scope?.payroll?.rows) ? scope.payroll.rows : [];
        const clusterRows = Array.isArray(scope?.business_cluster?.rows) ? scope.business_cluster.rows : [];
        const supportingRows = Array.isArray(scope?.supporting) ? scope.supporting : [];
        const dormant = scope?.dormant?.row || {};
        const activeCount = digitalRows.filter((row) => row?.positions?.current?.raw !== null && row?.positions?.current?.raw !== undefined).length;
        const topCasa = maxBy(casaRows, 'ratio');
        const topCluster = clusterRows[0] || {};
        const firstDigital = digitalRows[0] || {};
        const periodLabel = (path, fallback) => firstDigital?.positions?.[path]?.label || fallback;
        const point = (metric) => h(metric?.fmt || '-');
        const delta = (metric) => deltaChip(metric?.fmt || '-', metric?.raw);
        const payroll = (metric) => `
            <span class="rp-stack"><b>${h(metric?.positions?.current?.fmt || '-')}</b>
            <small>Posisi ${h(metric?.positions?.ytd?.fmt || '-')} / ${h(metric?.positions?.mtd?.fmt || '-')}</small>
            <small>${deltaChip(metric?.deltas?.ytd?.fmt || '-', metric?.deltas?.ytd?.raw)} ${deltaChip(metric?.deltas?.mtd?.fmt || '-', metric?.deltas?.mtd?.raw)}</small></span>
        `;
        const supportRow = (number, title) => supportingRows.find((row) => String(row?.number) === String(number)) || { number, title, position: '-', delta_ytd: '-', delta_mtd: '-', rka: '-' };
        const supportFigures = (row) => figures([
            { label: 'Posisi', value: row?.position || '-', tone: 'blue' },
            { label: 'Delta YTD', value: row?.delta_ytd || '-', tone: 'cyan' },
            { label: 'Delta MtD', value: row?.delta_mtd || '-', tone: 'cyan' },
            { label: 'RKA', value: row?.rka || '-', tone: 'blue' },
        ], 'is-compact');
        const six = supportRow(6, 'Kolaborasi Perusahaan Anak');
        const seven = supportRow(7, 'Optimalisasi Nasabah Prioritas BOD / BOC');
        const eight = supportRow(8, 'Penguatan Produk & Fungsi RM');
        const lever = (number, title, description, meta, body) => `
            <article class="rp-lever" data-rv data-lever="${number}">
                <header class="rp-lever__head">
                    <span class="rp-lever__number" aria-hidden="true">${number}</span>
                    <div><h3>${h(title)}</h3><p>${h(description)}</p></div>
                    ${meta ? `<span class="rp-lever__meta">${h(meta)}</span>` : ''}
                </header>
                ${body}
            </article>
        `;
        const levers = [
            lever(1, 'Optimalisasi Digital Channel', 'EDC, QRIS, CASA Merchant, BRIMO, BRILINK, dan QLOLA.', `${digitalRows.length} kanal`, dataTable({
                columns: [
                    { label: 'Kategori', render: (row) => `<span class="rp-stack"><b>${h(row.label || '-')}</b><small>${h(row.metric_label || '')}</small></span>` },
                    { label: `YTD<small>${h(periodLabel('ytd', '-'))}</small>`, render: (row) => point(row.positions?.ytd) },
                    { label: `MtD<small>${h(periodLabel('mtd', '-'))}</small>`, render: (row) => point(row.positions?.mtd) },
                    { label: `Terakhir<small>${h(periodLabel('current', scope?.period_label || '-'))}</small>`, render: (row) => `<b>${point(row.positions?.current)}</b>` },
                    { label: 'Delta YTD', render: (row) => delta(row.deltas?.ytd) },
                    { label: 'Delta MtD', render: (row) => delta(row.deltas?.mtd) },
                    { label: 'RKA', render: (row) => h(row.rka?.fmt || '-') },
                ],
                rows: digitalRows,
                empty: 'Data kanal digital sedang dimuat.',
            })),
            lever(2, 'Rekening Transaksi Debitur', 'Rasio CASA terhadap OS untuk mengukur kedalaman transaksi.', scope?.casa_debitur?.period_label || '', dataTable({
                columns: [
                    { label: this.isArea() ? 'Cabang' : 'Segmen', render: (row) => h(row.label || '-') },
                    { label: 'OS', render: (row) => h(row.os_fmt || '-') },
                    { label: 'CASA', render: (row) => h(row.casa_fmt || '-') },
                    { label: 'Rasio', render: (row) => `<b>${h(row.ratio_fmt || '-')}</b>` },
                ],
                rows: casaRows,
                empty: 'Snapshot CASA debitur belum tersedia.',
            })),
            lever(3, 'Bisnis Cluster, lima teratas', 'Potensi terbesar yang dapat dikonversi menjadi bisnis BRI.', `${formatInteger(scope?.business_cluster?.total || 0)} potensi`, dataTable({
                columns: [
                    { label: 'Cluster', render: (row) => h(row.category || '-') },
                    { label: 'Total', render: (row) => h(row.total_fmt || '-') },
                    { label: 'BRI', render: (row) => h(row.sudah_bri_fmt || '-') },
                    { label: 'Potensi', render: (row) => h(row.belum_bri_fmt || '-') },
                    { label: 'Penetrasi', render: (row) => `<b>${h(row.penetration_fmt || '-')}</b>` },
                ],
                rows: clusterRows,
                empty: 'Data cluster belum tersedia pada cache sumber.',
            })),
            lever(4, 'Peningkatan Payroll Berkualitas', 'Rekening baru, saldo baru, kualitas payroll, serta RKA dan pencapaian.', scope?.payroll?.period_label || '', dataTable({
                columns: [
                    { label: this.isArea() ? 'Cabang' : 'Scope', render: (row) => h(row.label || '-') },
                    { label: 'New rekening', render: (row) => payroll(row.rekening) },
                    { label: 'Saldo new', render: (row) => payroll(row.saldo) },
                    { label: 'Payroll berkualitas', render: (row) => payroll(row.kualitas) },
                    { label: 'RKA / Penc.', render: (row) => `<span class="rp-stack"><b>${h(row.rekening?.rka?.fmt || '-')}</b><small>${h(row.rekening?.achievement?.fmt || '-')}</small></span>` },
                ],
                rows: payrollRows,
                empty: 'Data payroll sedang dimuat.',
            })),
            lever(5, 'Rekening Dormant', 'Reaktivasi rekening tidak bertransaksi untuk memperkuat CASA.', dormant?.positions?.current?.label || '', figures([
                { label: 'YTD', value: dormant?.positions?.ytd?.fmt || '-', tone: 'blue' },
                { label: 'MtD', value: dormant?.positions?.mtd?.fmt || '-', tone: 'blue' },
                { label: dormant?.positions?.current?.label || 'Terakhir', value: dormant?.positions?.current?.fmt || '-', tone: 'cyan' },
                { label: 'Delta YTD', value: dormant?.deltas?.ytd?.fmt || '-', tone: toneOfDelta(dormant?.deltas?.ytd?.raw) },
                { label: 'Delta MtD', value: dormant?.deltas?.mtd?.fmt || '-', tone: toneOfDelta(dormant?.deltas?.mtd?.raw) },
            ], 'is-compact')),
            lever(6, six.title || 'Kolaborasi Perusahaan Anak', 'Sinergi ekosistem untuk memperluas sumber dana dan transaksi.', '', supportFigures(six)),
            lever(7, seven.title || 'Optimalisasi Nasabah Prioritas BOD / BOC', 'Aktivasi peluang nasabah prioritas wholesale dan komersial.', '', supportFigures(seven)),
            lever(8, eight.title || 'Penguatan Produk & Fungsi RM', 'Penguatan kapasitas, akuisisi, dan fungsi pengelolaan RM.', '', supportFigures(eight)),
        ];
        const action = topCasa
            ? `Perkuat kanal digital, pertahankan CASA/OS ${topCasa.label || 'tertinggi'} ${topCasa.ratio_fmt || '-'}, dan tindak lanjuti potensi cluster serta rekening dormant.`
            : 'Perkuat kanal digital, payroll, bisnis cluster, dan reaktivasi rekening dormant berdasarkan posisi terakhir.';

        return `
            ${stickyBar('Rangkuman 8 strategi funding', `${activeCount}/6 kanal aktif`)}
            ${sectionHead({
                title: 'Rangkuman 8 Strategi Funding',
                subtitle: 'Delapan pengungkit funding disusun sebagai satu peta eksekusi yang ringkas, terukur, dan mudah dipresentasikan.',
                narrative: `${activeCount}/6 kanal digital memiliki posisi terbaru. CASA/OS tertinggi berada pada ${topCasa?.label || 'scope yang belum tersedia'} sebesar ${topCasa?.ratio_fmt || '-'}; payroll, cluster, dan dormant menjadi pengungkit berikutnya.`,
                period: scope?.period_label || this.period(),
            })}
            ${figures([
                { label: 'Kanal digital aktif', value: `${activeCount}/6`, tone: 'blue' },
                { label: 'CASA/OS tertinggi', value: topCasa?.ratio_fmt || '-', meta: topCasa?.label || '-', tone: 'green' },
                { label: 'Potensi cluster', value: formatInteger(scope?.business_cluster?.total || 0), meta: topCluster?.category || '-', tone: 'cyan' },
            ])}
            <div class="rp-levers">
                <div class="rp-levers__dial" aria-hidden="true"><span data-lever-current>1</span><small>dari 8 strategi</small></div>
                <div class="rp-levers__list">${levers.join('')}</div>
            </div>
            <div class="rp-action" data-rv>
                <strong>Fokus eksekusi funding</strong>
                <p>${h(action)}</p>
                <dl>
                    <div><dt>Cluster utama</dt><dd>${h(topCluster?.category || '-')}</dd></div>
                    <div><dt>CASA / OS</dt><dd>${h(topCasa?.ratio_fmt || '-')}</dd></div>
                    <div><dt>Dormant MtD</dt><dd>${deltaChip(dormant?.deltas?.mtd?.fmt || '-', dormant?.deltas?.mtd?.raw)}</dd></div>
                </dl>
            </div>
        `;
    }

    /* --------------------------------------------------- 7 loan summary -- */
    scrollLoanSummary() {
        const periods = this.comparisonPeriods();
        const credit = this.comparisonScope()?.credit || {};
        const total = credit.total?.os || {};
        const rows = this.isArea()
            ? (credit.branches || []).flatMap((branch) => (branch.segments || []).map((segment) => ({ scope: branch.scope_label, dimension: segment.label, metric: segment.os })))
            : (credit.segments || []).map((segment) => ({ scope: this.scopeLabel(), dimension: segment.label, metric: segment.os }));
        const distribution = this.isArea()
            ? (credit.branches || []).map((branch) => ({ label: branch.scope_label, metric: branch.total?.os }))
            : (credit.segments || []).map((segment) => ({ label: segment.label, metric: segment.os }));

        return this.comparisonSection({
            sticky: { label: `Outstanding ${this.scopeLabel()}`, figure: total.current_fmt },
            head: {
                title: `Outstanding Summary ${this.scopeLabel()}`,
                subtitle: 'Cabang dan segmen disajikan dalam satu matriks historis untuk membaca skala, momentum, dan ruang terhadap RKA.',
                narrative: `Outstanding ${this.scopeLabel()} sebesar ${total.current_fmt || '-'}. Matriks menampilkan SME, Konsumer, dan Mikro dengan posisi YoY hingga ${periods.current?.label || this.period()}, delta, serta RKA yang tersedia.`,
                period: periods.current?.label || this.period(),
            },
            hero: [
                { label: 'Posisi OS', value: total.current_fmt || '-', meta: total.achievement_fmt ? `RKA ${total.achievement_fmt}` : 'Posisi terbaru', tone: 'cyan' },
                { label: 'Delta YtD', value: total.deltas_fmt?.ytd || '-', meta: periods.ytd?.label || '-', tone: asNumber(total.deltas?.ytd) >= 0 ? 'green' : 'red' },
                { label: 'Gap RKA', value: total.gap_fmt || '-', meta: total.achievement_fmt || '-', tone: asNumber(total.gap) >= 0 ? 'green' : 'red' },
            ],
            table: { title: 'Outstanding per cabang dan segmen', meta: `${rows.length} baris`, periods, rows },
            trend: { title: 'Timeseries total outstanding', labels: this.timeseriesScope()?.labels || [], series: this.timeseriesSeries('os'), seriesKeys: ['os'], tone: 'green', note: `Pergerakan OS ${this.scopeLabel()} sampai posisi terakhir.` },
            side: bars({ title: this.isArea() ? 'Portofolio pinjaman per cabang' : 'Portofolio pinjaman per segmen', rows: distribution, total: asNumber(total.current), tone: 'green' }),
        });
    }

    /* ------------------------------------------------- 8, 9 segments -- */
    scrollSegment(segmentKey) {
        const periods = this.comparisonPeriods();
        const credit = this.comparisonScope()?.credit || {};
        const segment = findByKey(credit.segments, segmentKey) || {};
        const products = segment.products || [];
        const rows = [
            { scope: this.scopeLabel(), dimension: `TOTAL ${String(segment.label || segmentKey).toUpperCase()}`, metric: segment.os, className: 'is-total' },
            ...products.map((product) => ({ scope: this.scopeLabel(), dimension: product.label, metric: product.os })),
        ];
        const productivity = this.productivityCategory(segmentKey === 'sme' ? 'retail_sme' : 'retail_consumer');
        const timeseriesKey = segmentKey === 'sme' ? 'sme_os' : 'consumer_os';
        const title = segmentKey === 'sme' ? 'Pinjaman SME' : 'Pinjaman Konsumer';
        const tone = segmentKey === 'sme' ? 'blue' : 'cyan';

        return this.comparisonSection({
            sticky: { label: `${title} ${this.scopeLabel()}`, figure: segment.os?.current_fmt },
            head: {
                title: `${title} ${this.scopeLabel()}`,
                subtitle: segmentKey === 'sme'
                    ? 'Kecil Non Cashcoll dan Cashcoll dibaca bersama perkembangan kuadran RM Ritel SME.'
                    : 'Briguna dan KPR dibaca bersama perkembangan kuadran RM Ritel Konsumer.',
                narrative: `${segment.label || title} berada pada ${segment.os?.current_fmt || '-'}. Pencapaian RKA ${segment.os?.achievement_fmt || '-'} dengan ${products.length} produk dan histori kuadran RM sejak Januari.`,
                period: periods.current?.label || this.period(),
            },
            hero: [
                { label: 'Posisi segmen', value: segment.os?.current_fmt || '-', meta: segment.os?.achievement_fmt ? `RKA ${segment.os.achievement_fmt}` : 'Posisi terbaru', tone },
                { label: 'Delta MtD', value: segment.os?.deltas_fmt?.mtd || '-', meta: periods.mtd?.label || '-', tone: asNumber(segment.os?.deltas?.mtd) >= 0 ? 'green' : 'red' },
                { label: 'RM aktif', value: formatInteger(productivity.total?.rm_count), meta: `${formatInteger(productivity.total?.realisasi_deb)} debitur`, tone: 'blue' },
            ],
            table: { title: `Matriks ${segment.label || title} per produk`, meta: `${rows.length} baris`, periods, rows },
            trend: { title: `Timeseries OS ${segment.label || ''}`, labels: this.timeseriesScope()?.labels || [], series: this.timeseriesSeries(timeseriesKey), seriesKeys: [timeseriesKey], tone, note: `Nominal OS ${segment.label || ''} pada tujuh bulan terakhir.` },
            side: quadrantTable({ title: `Kuadran RM ${segment.label || ''}, Januari sampai terkini`, history: productivity.quadrant_history || {} }),
        });
    }

    /* ----------------------------------------------------------- 10 micro -- */
    scrollMicro() {
        const credit = this.creditScope();
        const segment = findByKey(credit.segments, 'micro') || { key: 'micro', label: 'Mikro', products: [] };
        const products = (segment.products || []).filter((product) => asNumber(product.os_raw) > 0);
        const osMetric = this.summaryMetric('micro_os');
        const activeScope = String(this.scopeLabel()).trim().toUpperCase();
        const matchesScope = (row) => this.isArea() || String(row?.cabang || row?.branch_office || row?.label || row?.unit || '').trim().toUpperCase() === activeScope;
        const pdwkRows = (this.data?.micro?.pdwk?.branches || []).filter(matchesScope).slice(0, 4);
        const extremeLowRows = (this.data?.micro?.extreme_low_mantri?.rows || []).filter(matchesScope).slice(0, 4);
        const tieringRows = (this.data?.micro?.rm_kur_tiering?.rows || []).filter(matchesScope).slice(0, 4);
        const showRmKur = this.microProductivityView === 'rm_kur';
        const roleFor = (row, key) => (row?.roles || []).find((role) => role.key === key) || {};
        const pair = (top, bottom) => `<span class="rp-stack"><b>${h(top)}</b><small>${h(bottom)}</small></span>`;
        const smlLeader = maxBy(products, 'sml_raw');
        const nplLeader = maxBy(products, 'npl_raw');
        const largest = maxBy(products, 'os_raw');
        const totalRisk = asNumber(segment.sml_raw) + asNumber(segment.npl_raw);
        const scopeTitle = this.isArea() ? 'Area 6' : this.scopeLabel();
        const source = this.data?.micro?.period_label || credit.period_label || this.period();
        const totalMantri = extremeLowRows.reduce((sum, row) => sum + asNumber(row?.total_mantri), 0);
        const kpi = (tone, title, value, target, lines) => `
            <article class="rp-kpi is-${tone}" data-rv>
                <h3>${h(title)}</h3>
                <strong><span data-count>${h(value)}</span></strong>
                <p class="rp-kpi__target">${target}</p>
                <dl>${lines.map(([label, val, meta, cls]) => `<div><dt>${h(label)}</dt><dd class="${cls || ''}">${h(val)}</dd>${meta ? `<dd class="rp-kpi__meta">${h(meta)}</dd>` : ''}</div>`).join('')}</dl>
            </article>
        `;

        return `
            ${stickyBar(`Highlight mikro ${scopeTitle}`, segment.os || formatCurrency(segment.os_raw))}
            ${sectionHead({
                title: `Highlight Kinerja Mikro ${scopeTitle}`,
                subtitle: `OS dan kualitas posisi laporan; PDWK, kategori Mantri Extreme Low sampai High, serta tiering RM KUR memakai data Mikro siap terbaru (${source}).`,
                narrative: `${scopeTitle}: OS Mikro ${segment.os || formatCurrency(segment.os_raw)} dengan pencapaian RKA ${osMetric.achievement_fmt || '-'}. SML ${segment.sml || formatCurrency(segment.sml_raw)} dan NPL ${segment.npl || formatCurrency(segment.npl_raw)} menjadi fokus kualitas.`,
                period: credit.period_label || this.period(),
                controls: segmented([{ key: 'extreme_low', label: 'Kategori Mantri' }, { key: 'rm_kur', label: 'RM KUR per tiering' }], 'data-psd-micro-view', showRmKur ? 'rm_kur' : 'extreme_low'),
            })}
            <div class="rp-kpis">
                ${kpi('cyan', 'Outstanding (OS)', segment.os || formatCurrency(segment.os_raw), `RKA <b>${h(osMetric.rka_fmt || '-')}</b>, pencapaian <b>${h(osMetric.achievement_fmt || '-')}</b>`, [
                    ['YtD', formatCurrency(osMetric.ytd), '', `rp-tone-${toneOfDelta(osMetric.ytd)}`],
                    ['MtM', formatCurrency(osMetric.mtm), '', `rp-tone-${toneOfDelta(osMetric.mtm)}`],
                    ['MtD', formatCurrency(osMetric.mtd), '', `rp-tone-${toneOfDelta(osMetric.mtd)}`],
                ])}
                ${kpi('amber', 'Special Mention Loan', segment.sml || formatCurrency(segment.sml_raw), `Rasio terhadap OS <b>${h(segment.sml_ratio || formatPercent(segment.sml_ratio_raw))}</b>`, [
                    ['Kontributor', smlLeader?.label || '-', smlLeader?.sml || '-'],
                    ['Porsi SML', formatPercent(percentOf(smlLeader?.sml_raw, segment.sml_raw)), 'dari SML Mikro', 'rp-tone-red'],
                    ['At risk', formatCurrency(totalRisk), 'SML + NPL', 'rp-tone-red'],
                ])}
                ${kpi('red', 'Non-Performing Loan', segment.npl || formatCurrency(segment.npl_raw), `Rasio terhadap OS <b>${h(segment.npl_ratio || formatPercent(segment.npl_ratio_raw))}</b>`, [
                    ['Kontributor', nplLeader?.label || '-', nplLeader?.npl || '-'],
                    ['Porsi NPL', formatPercent(percentOf(nplLeader?.npl_raw, segment.npl_raw)), 'dari NPL Mikro', 'rp-tone-red'],
                    ['Produk terbesar', largest?.label || '-', largest?.os || '-'],
                ])}
            </div>
            ${dataTable({
                title: 'Kinerja per produk kredit',
                meta: `${products.length} produk aktif`,
                columns: [
                    { label: 'Produk', render: (row) => `<i class="fas ${iconFor(row.key)}" aria-hidden="true"></i> ${h(row.label)}` },
                    { label: 'Posisi OS', render: (row) => `<b>${h(row.os || formatCurrency(row.os_raw))}</b>` },
                    { label: 'Porsi', render: (row) => `<span class="rp-share" style="--w:${percentOf(row.os_raw, segment.os_raw).toFixed(2)}%"><b>${formatPercent(percentOf(row.os_raw, segment.os_raw))}</b><i></i></span>` },
                    { label: 'Posisi SML', render: (row) => h(row.sml || formatCurrency(row.sml_raw)) },
                    { label: 'Rasio SML', render: (row) => `<span class="${asNumber(row.sml_ratio_raw) >= 10 ? 'rp-tone-red' : ''}">${h(row.sml_ratio || formatPercent(row.sml_ratio_raw))}</span>` },
                    { label: 'Posisi NPL', render: (row) => h(row.npl || formatCurrency(row.npl_raw)) },
                    { label: 'Rasio NPL', render: (row) => `<span class="${asNumber(row.npl_ratio_raw) >= 3 ? 'rp-tone-red' : ''}">${h(row.npl_ratio || formatPercent(row.npl_ratio_raw))}</span>` },
                ],
                rows: products,
            })}
            <div class="rp-stackcards">
                ${dataTable({
                    title: 'Rekap PDWK per cabang',
                    meta: `${pdwkRows.length} cabang`,
                    columns: [
                        { label: 'Cabang', render: (row) => h(row.label || row.key || '-') },
                        { label: 'Putusan KA Unit', render: (row) => pair(roleFor(row, 'kaunit').total_os_fmt || formatCurrency(roleFor(row, 'kaunit').total_os), `${formatInteger(roleFor(row, 'kaunit').total_deb)} debitur`) },
                        { label: 'Putusan MBM', render: (row) => pair(roleFor(row, 'mbm').total_os_fmt || formatCurrency(roleFor(row, 'mbm').total_os), `${formatInteger(roleFor(row, 'mbm').total_deb)} debitur`) },
                        { label: 'Putusan BOH', render: (row) => pair(roleFor(row, 'boh').total_os_fmt || formatCurrency(roleFor(row, 'boh').total_os), `${formatInteger(roleFor(row, 'boh').total_deb)} debitur`) },
                        { label: 'Total realisasi', render: (row) => pair(row.total?.os_fmt || formatCurrency(row.total?.os), `${formatInteger(row.total?.deb)} debitur`) },
                    ],
                    rows: pdwkRows,
                    empty: 'Data PDWK per cabang belum tersedia.',
                })}
                ${showRmKur ? dataTable({
                    title: 'RM Mikro KUR per tiering dan cabang',
                    meta: `${tieringRows.length} cabang`,
                    columns: [
                        { label: 'Cabang', render: (row) => h(row.cabang || row.key || '-') },
                        { label: 'Tier &lt; Rp250 Jt', render: (row) => pair(row.lt_250?.os_fmt || formatCurrency(row.lt_250?.os), `${formatInteger(row.lt_250?.deb)} debitur`) },
                        { label: 'Tier &gt; Rp250 Jt', render: (row) => pair(row.gt_250?.os_fmt || formatCurrency(row.gt_250?.os), `${formatInteger(row.gt_250?.deb)} debitur`) },
                        { label: 'Total', render: (row) => pair(row.total?.os_fmt || formatCurrency(row.total?.os), `${formatInteger(row.total?.deb)} debitur`) },
                    ],
                    rows: tieringRows,
                    empty: 'Tiering RM KUR belum tersedia pada scope ini.',
                }) : dataTable({
                    title: 'Kategori Mantri per cabang',
                    meta: `${extremeLowRows.length} cabang, ${formatInteger(totalMantri)} mantri`,
                    columns: [
                        { label: 'Cabang', render: (row) => h(row.branch_office || row.cabang || '-') },
                        ...[['extreme_low', 'Extreme Low'], ['low', 'Low'], ['under_800', 'Total &le; 800 Jt'], ['mid', 'Mid'], ['high', 'High']].map(([key, label]) => ({
                            label,
                            render: (row) => pair(`${formatInteger(row?.[key]?.deb)} mantri`, row?.[key]?.pct_fmt || formatPercent(row?.[key]?.pct)),
                        })),
                    ],
                    rows: extremeLowRows,
                    empty: 'Rekap Extreme Low belum tersedia pada scope ini.',
                })}
            </div>
            ${this.insights([
                { label: 'Pencapaian RKA', value: osMetric.achievement_fmt || '-', meta: osMetric.gap_fmt || 'Gap RKA', tone: asNumber(osMetric.achievement) >= 100 ? 'green' : 'red' },
                { label: 'Produk dominan', value: largest?.label || '-', meta: largest?.os || '-' },
                { label: 'Fokus kualitas', value: `SML ${segment.sml_ratio || '-'}`, meta: `NPL ${segment.npl_ratio || '-'}`, tone: 'amber' },
            ])}
        `;
    }

    /* ---------------------------------------------------- 11, 12 quality -- */
    scrollQuality(metricKey) {
        const periods = this.comparisonPeriods();
        const credit = this.comparisonScope()?.credit || {};
        const total = credit.total?.[metricKey] || {};
        const rows = this.isArea()
            ? (credit.branches || []).flatMap((branch) => (branch.segments || []).map((segment) => ({ scope: branch.scope_label, dimension: segment.label, metric: segment[metricKey] })))
            : (credit.segments || []).map((segment) => ({ scope: this.scopeLabel(), dimension: segment.label, metric: segment[metricKey] }));
        const ranking = [...rows]
            .sort((a, b) => asNumber(b.metric?.current) - asNumber(a.metric?.current))
            .slice(0, 5)
            .map((row) => ({ label: `${row.scope} - ${row.dimension}`, metric: row.metric, meta: row.metric?.ratio_positions_fmt?.current ? `Rasio ${row.metric.ratio_positions_fmt.current}` : 'Nominal kualitas' }));
        const upper = metricKey.toUpperCase();
        const title = metricKey === 'sml' ? 'Kualitas SML' : 'Kualitas NPL';
        const tone = metricKey === 'sml' ? 'amber' : 'red';

        return this.comparisonSection({
            sticky: { label: `${title} ${this.scopeLabel()}`, figure: total.current_fmt },
            head: {
                title: `${title} ${this.scopeLabel()}`,
                subtitle: metricKey === 'sml'
                    ? 'Early warning dibaca dari nominal, rasio, pergeseran posisi, dan sumber tekanan per cabang serta segmen.'
                    : 'Prioritas recovery dibaca dari nominal, rasio, pergeseran posisi, dan konsentrasi eksposur.',
                narrative: `${upper} ${this.scopeLabel()} sebesar ${total.current_fmt || '-'} dengan rasio ${total.ratio_positions_fmt?.current || '-'}. Delta negatif dibaca sebagai perbaikan kualitas.`,
                period: periods.current?.label || this.period(),
            },
            hero: [
                { label: `Posisi ${upper}`, value: total.current_fmt || '-', meta: total.ratio_positions_fmt?.current || 'Rasio terhadap OS', tone },
                { label: 'Delta MtD', value: total.deltas_fmt?.mtd || '-', meta: periods.mtd?.label || '-', tone: asNumber(total.deltas?.mtd) <= 0 ? 'green' : 'red' },
                { label: 'Tekanan terbesar', value: ranking[0]?.label || '-', meta: ranking[0]?.metric?.current_fmt || '-', tone: 'red' },
            ],
            table: { title: `Matriks ${upper} per cabang dan segmen`, meta: `${rows.length} baris`, periods, rows, inverse: true },
            trend: { title: `Timeseries nominal ${upper}`, labels: this.timeseriesScope()?.labels || [], series: this.timeseriesSeries(metricKey), seriesKeys: [metricKey], tone, note: `Penurunan nominal ${upper} merupakan perbaikan kualitas.` },
            side: bars({ title: `Eksposur ${upper} terbesar`, rows: ranking, total: asNumber(total.current), tone }),
        });
    }

    /* -------------------------------------------------- 13 timeseries -- */
    scrollTimeseries() {
        const timeseries = this.data?.timeseries || {};
        const scope = this.timeseriesScope();
        const groups = timeseries.groups || {};
        const group = groups[this.trendGroup] || groups.business || { label: 'Skala Bisnis', description: 'Pergerakan indikator utama.', keys: ['simpanan', 'os'] };
        const allSeries = this.timeseriesSeries(scope);
        const series = (group.keys || []).map((key) => findByKey(allSeries, key)).filter(Boolean);
        const labels = scope.labels || timeseries.labels || [];
        const latestRows = labels.slice(-6).map((label, index) => {
            const actual = labels.length - Math.min(6, labels.length) + index;
            const row = { label };
            series.forEach((item) => {
                row[item.key] = item.display_values?.[actual] || (item.format === 'percent' ? formatPercent(item.values?.[actual]) : formatCurrency(asNumber(item.values?.[actual]) * 1_000_000));
            });
            return row;
        });
        const momentum = series.map((item) => {
            const values = item.values || [];
            const first = asNumber(values[0]);
            const last = asNumber(values.at(-1));
            return { ...item, change: first === 0 ? 0 : ((last / first) - 1) * 100 };
        });
        const strongest = [...momentum].sort((a, b) => b.change - a.change)[0];
        const weakest = [...momentum].sort((a, b) => a.change - b.change)[0];
        const isBusiness = (group.keys || []).includes('simpanan') && (group.keys || []).includes('os');
        const business = this.businessSnapshot();

        return `
            ${stickyBar('Timeseries kinerja terintegrasi', group.label)}
            ${sectionHead({
                title: 'Timeseries Kinerja Terintegrasi',
                subtitle: group.description || 'Pergerakan indikator pada tiga belas posisi bulanan.',
                narrative: strongest
                    ? `${strongest.label} mencatat perubahan terkuat ${formatPercent(strongest.change)} dari titik awal, sedangkan ${weakest?.label || '-'} berubah ${formatPercent(weakest?.change)}.${isBusiness ? ` LDR ${business.ldrLabel} dihitung dari Pinjaman dibagi Simpanan.` : ''}`
                    : 'Timeseries sedang dimuat untuk scope dan periode aktif.',
                period: labels.length ? `${labels[0]} sampai ${labels.at(-1)}` : this.period(),
                controls: segmented(Object.entries(groups).map(([key, item]) => ({ key, label: item.label })), 'data-psd-trend-group', this.trendGroup),
            })}
            ${figures([
                { label: 'Momentum terkuat', value: strongest ? formatPercent(strongest.change) : '-', meta: strongest?.label || 'Menunggu data', tone: 'green' },
                { label: 'Perlu perhatian', value: weakest ? formatPercent(weakest.change) : '-', meta: weakest?.label || 'Menunggu data', tone: weakest && weakest.change < 0 ? 'red' : 'amber' },
                isBusiness
                    ? { label: 'Loan deposit ratio', value: business.ldrLabel, meta: 'Pinjaman dibagi dana / simpanan', tone: business.ldrRatio > 1 ? 'amber' : 'green' }
                    : { label: 'Basis analisis', value: `${labels.length} titik`, meta: 'Nominal dan bulan tampil pada grafik', tone: 'blue' },
            ])}
            <section class="rp-paper rp-canvas" data-rv>
                ${cardHead(group.label || 'Timeseries', `${labels.length} titik bulanan. Klik dua kali untuk detail harian.`)}
                <div class="psd-chart-wrap rp-canvas__wrap is-expandable" data-psd-timeseries-expand role="button" tabindex="0" aria-haspopup="dialog" aria-label="Buka grafik ${h(group.label || 'timeseries')} harian dalam tampilan penuh">
                    <canvas id="psd-timeseries-chart" aria-label="Grafik timeseries ${h(group.label || '')}"></canvas>
                </div>
                <p class="rp-canvas__legend">${series.map((item) => `<span><i style="background:${h(item.color || '#0857c3')}"></i>${h(item.label)}</span>`).join('')}</p>
            </section>
            ${isBusiness ? `
                <div class="rp-split">
                    ${figures([
                        { label: 'Dana / simpanan', value: business.fundingLabel, meta: 'Sumbu X kuadran', tone: 'blue' },
                        { label: 'Pinjaman / OS', value: business.loanLabel, meta: 'Sumbu Y kuadran', tone: 'green' },
                        { label: 'LDR', value: business.ldrLabel, meta: 'Pinjaman / simpanan', tone: business.ldrRatio > 1 ? 'amber' : 'green' },
                    ], 'is-stack')}
                    <section class="rp-paper rp-legacy" data-rv>${this.renderBusinessQuadrant()}</section>
                </div>
            ` : dataTable({
                title: 'Posisi bulanan',
                meta: `${latestRows.length} periode terakhir`,
                columns: [
                    { label: 'Periode', render: (row) => h(row.label) },
                    ...series.map((item) => ({ label: h(item.label), render: (row) => `<b>${h(row[item.key] || '-')}</b>` })),
                ],
                rows: latestRows,
                empty: 'Timeseries belum tersedia pada scope ini.',
            })}
        `;
    }

    /* --------------------------------------------------- 14 priorities -- */
    scrollPriorities() {
        const comparison = this.comparisonScope();
        const funding = comparison.funding?.total || {};
        const os = comparison.credit?.total?.os || {};
        const sml = comparison.credit?.total?.sml || {};
        const npl = comparison.credit?.total?.npl || {};
        const strategies = (this.data?.digital_strategy?.cards || []).slice(0, 8);
        const weakest = strategies
            .map((item) => ({ ...item, trend_raw: Number(String(item.trend || '').replace(/[^0-9,-]/g, '').replace(',', '.')) || 0 }))
            .sort((a, b) => a.trend_raw - b.trend_raw)[0];
        const sme = this.productivityCategory('retail_sme');
        const consumer = this.productivityCategory('retail_consumer');
        const actions = [
            { title: 'Funding', basis: `${funding.current_fmt || '-'}, gap RKA ${funding.gap_fmt || '-'}`, action: asNumber(funding.gap) < 0 ? 'Tutup gap RKA melalui cabang atau segmen dengan kontraksi MtD terbesar.' : 'Pertahankan buffer RKA dan optimalkan komposisi CASA.', tone: asNumber(funding.gap) >= 0 ? 'green' : 'red' },
            { title: 'Pinjaman', basis: `${os.current_fmt || '-'}, gap RKA ${os.gap_fmt || '-'}`, action: asNumber(os.gap) < 0 ? 'Akselerasi produk dengan pipeline sehat tanpa menambah tekanan kualitas.' : 'Jaga pertumbuhan dan distribusi portofolio antar cabang.', tone: asNumber(os.gap) >= 0 ? 'green' : 'red' },
            { title: 'SML', basis: `${sml.current_fmt || '-'}, ${sml.ratio_positions_fmt?.current || '-'}`, action: 'Lakukan curing eksposur terbesar sebelum bermigrasi menjadi NPL.', tone: 'amber' },
            { title: 'NPL', basis: `${npl.current_fmt || '-'}, ${npl.ratio_positions_fmt?.current || '-'}`, action: 'Prioritaskan recovery berdasarkan kombinasi nominal, rasio, dan tren memburuk.', tone: 'red' },
            { title: 'Produktivitas RM', basis: `${formatInteger(asNumber(sme.total?.rm_count) + asNumber(consumer.total?.rm_count))} RM Ritel`, action: 'Coaching diarahkan pada RM kuadran 4 dengan basis kelolaan besar dan realisasi rendah.', tone: 'blue' },
        ];

        return `
            ${stickyBar('Prioritas aksi berikutnya', this.scopeLabel())}
            ${sectionHead({
                title: 'Prioritas Aksi Berikutnya',
                subtitle: 'Agenda manajemen disusun dari gap bisnis, tekanan kualitas, produktivitas, dan indikator strategi.',
                narrative: `Lima agenda manajemen merangkum temuan funding, pinjaman, kualitas, dan produktivitas pada ${this.scopeLabel()}. Prioritas digital terlemah berada pada ${weakest?.title || 'indikator yang belum tersedia'}.`,
                period: this.period(),
            })}
            <ol class="rp-agenda-board">
                ${actions.map((item, index) => `
                    <li class="is-${h(item.tone)}" data-rv style="--i:${index}">
                        <h3>${h(item.title)}</h3>
                        <p class="rp-agenda-board__basis">${h(item.basis)}</p>
                        <p>${h(item.action)}</p>
                    </li>
                `).join('')}
            </ol>
            <section class="rp-signals" data-rv>
                ${cardHead('Sinyal 8 strategi pendukung', `${strategies.length} sumber aktif`)}
                <ul>
                    ${strategies.map((strategy, index) => `
                        <li style="--i:${index}">
                            <span>${h(strategy.title || '-')}</span>
                            <strong>${h(strategy.current_value || '-')}</strong>
                            <b class="rp-delta is-${String(strategy.trend || '').includes('-') ? 'red' : 'green'}">${h(strategy.trend || '-')}</b>
                        </li>
                    `).join('')}
                </ul>
            </section>
            ${this.insights([
                { label: 'Scope keputusan', value: this.scopeLabel(), meta: `Posisi ${this.period()}` },
                { label: 'Perlu intervensi', value: weakest?.title || '-', meta: weakest?.trend || 'Belum tersedia', tone: 'red' },
                { label: 'Fokus eksekusi', value: 'Growth berkualitas', meta: 'Tutup gap tanpa menambah risiko', tone: 'green' },
            ])}
        `;
    }
}
