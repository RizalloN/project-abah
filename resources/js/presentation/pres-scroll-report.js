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
import { AREA6_TOTALS, area6Legend, area6Poster, area6ProfileDetails } from './pres-area6-profile';
import { penetrationHex, PENETRATION_STOPS } from './pres-area6-map';
import { buildMappingHierarchy, hierarchyColors, mappingPatternId, mappingToken } from './pres-mapping-hierarchy';

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
const trendChart = ({ title, labels = [], series = {}, seriesKeys = [], tone = 'blue', note = '', fullHistory = false }) => {
    const values = Array.isArray(series.values) ? series.values : [];
    const display = Array.isArray(series.display_values) ? series.display_values : [];
    const count = Math.min(fullHistory ? labels.length : 7, labels.length, values.length);
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
        this.mappingFocusedKey = '';
        this.mappingReading = 'members';
        this.root.addEventListener('mappingcue', (event) => {
            this.mappingReading = event.detail?.reading || 'members';
            this.focusMapping(event.detail?.key || '');
        });
    }

    renderSlide(index) {
        const section = document.getElementById(`pres-slide-${index}`);
        if (!section || !this.data) return;
        if ([4, 5, 7, 8, 9, 11, 12, 13].includes(index)) this.closeTimeseriesModal({ restoreFocus: false });
        if (index === 13) {
            this.trendChart?.destroy();
            this.trendChart = null;
        }
        if (index === 3) { this.destroyMarketShareMap(); this.mappingFocusedKey = ''; this.lastMappingWorld = null; }

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
        section.classList.toggle('has-narrative-stage', index === 2 || index === 3);
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
        if (index === 2) {
            const inner = document.querySelector('.pres-chapter--pasar .pres-chapter__inner');
            if (inner) {
                let position = inner.querySelector('.rp-market-position');
                if (!position) {
                    position = document.createElement('div');
                    position.className = 'rp-market-position';
                    inner.insertBefore(position, inner.querySelector('.pres-chapter__contents'));
                }
                position.innerHTML = this.marketPosition(true);
                const host = document.querySelector('.pres-chapter--pasar [data-world="pasar"]');
                if (host) {
                    let poster = host.querySelector('.pres-world__fallback');
                    if (!poster) {
                        poster = document.createElement('div');
                        poster.className = 'pres-world__fallback rp-market-poster';
                        host.prepend(poster);
                    }
                    const market = this.worldMarket();
                    const share = market.total?.share;
                    poster.innerHTML = market.competitors?.length ? `<svg viewBox="0 0 400 400" role="img" aria-label="Pangsa BRI ${h(market.total.label)} dan bank lain ${h(market.competitors[1].label)}"><circle cx="200" cy="200" r="130" fill="none" stroke="#d4b789" stroke-width="65"/><circle cx="200" cy="200" r="130" fill="none" stroke="#4aaff0" stroke-width="65" pathLength="100" stroke-dasharray="${share} ${100 - share}" transform="rotate(-90 200 200)"/></svg>` : '';
                }
            }
        }
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
            market: { ...this.worldMarket(), scenes: this.marketStoryScenes().map((scene) => scene.world) },
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

    worldMarket(selectedSegment = null, rowOverride = null) {
        const payload = this.marketShareAreaPayload();
        const segment = selectedSegment || this.marketShareSegmentPayload(payload);
        const kind = String(segment?.kind || 'deposit').toLowerCase();
        if (!payload?.available || !segment) return { key: segment?.key || '', kind, total: null, branches: [], competitors: [], quality: [] };
        const shareIndex = kind === 'deposit' ? 14 : 16;
        const rows = Array.isArray(segment.rows) ? segment.rows.filter(Boolean) : [];
        const branchRows = this.marketShareAreaRows(segment);
        const totalRow = rows.find((row) => row?.total || normaliseMarketShareToken(row?.branch) === 'area6') || null;
        const summaryRow = rowOverride || (this.isArea() ? totalRow : branchRows[0]);
        const entry = (row, name) => {
            const raw = String(row?.values?.[shareIndex] ?? '').trim();
            const number = /^[+]?\d+(?:[.,]\d+)?\s*%?$/.test(raw) ? Number(raw.replace(/[%\s]/g, '').replace(',', '.')) : NaN;
            return { name, share: Number.isFinite(number) && number >= 0 && number <= 100 ? number : null, label: valueOrDash(row?.values?.[shareIndex]) };
        };
        const total = summaryRow ? entry(summaryRow, `${this.isArea() ? 'Area 6' : this.scopeLabel()}, ${segment.label || 'market share'}`) : null;
        const sourceDecimals = String(total?.label || '').match(/[.,](\d+)/)?.[1]?.length || 0;
        const otherLabel = total?.share !== null && total?.share !== undefined
            ? `${(100 - total.share).toLocaleString(String(total.label).includes(',') ? 'id-ID' : 'en-US', { minimumFractionDigits: Math.min(20, sourceDecimals), maximumFractionDigits: Math.min(20, sourceDecimals) })}%`
            : '-';
        return {
            key: segment.key, kind,
            quality: kind === 'quality' ? [
                { name: 'Porsi SML BRI', ...entry({ values: { [shareIndex]: summaryRow?.values?.[16] } }, 'SML'), color: '#e4b56f' },
                { name: 'Porsi NPL BRI', ...entry({ values: { [shareIndex]: summaryRow?.values?.[17] } }, 'NPL'), color: '#bd91d8' },
            ] : [],
            segment: segment.label || '',
            period: payload.period || '-',
            total,
            competitors: kind !== 'quality' && total?.share !== null && total?.share !== undefined ? [
                { name: 'BRI', share: total.share, label: total.label, color: '#4aaff0' },
                { name: 'Bank lain (agregat)', share: 100 - total.share, label: otherLabel, color: '#d4b789' },
            ] : [],
            branches: this.isArea() ? branchRows.map((row) => entry(row, row.branch || '-')) : [],
        };
    }

    marketPosition(compact = false, market = this.worldMarket()) {
        if (!market.competitors?.length) return '<p class="rp-market-position__note">Perbandingan pangsa bank belum tersedia untuk bagian ini.</p>';
        return `<div class="rp-market-position__bar" aria-hidden="true">${market.competitors.map((bank) => `<i style="--bank:${bank.color};--share:${bank.share}%"></i>`).join('')}</div><dl>${market.competitors.map((bank) => `<div style="--bank:${bank.color}"><dt>${h(bank.name)}</dt><dd>${h(bank.label)}</dd></div>`).join('')}</dl><p class="rp-market-position__note">${h(market.segment)} · ${h(this.isArea() ? 'Area 6' : this.scopeLabel())} · ${h(market.period)}. ${compact ? 'Bank lain: 100% dikurangi pangsa BRI.' : 'Bank lain dihitung dari 100% dikurangi pangsa BRI; rincian per bank belum tersedia.'}</p>`;
    }

    /** Only the scroll report changes the inherited map's visual scale. */
    drawMarketShareMap() {
        super.drawMarketShareMap();
        if (!this.marketShareLayer) return;
        const units = this.marketShareActiveUnits(this.marketShareMappingPayload());
        const mapping = this.lastMappingWorld || this.worldMapping();
        const hierarchy = mapping?.hierarchy;
        const originalStyle = this.marketShareLayer.options.style;
        // Replace the style callback too, so Leaflet's mouseout restores the same palette.
        if (!this.marketShareLayer._area6Palette) {
            this.marketShareLayer.options.style = (feature) => ({
                ...(typeof originalStyle === 'function' ? originalStyle(feature) : originalStyle),
                fillColor: this.mappingReading === 'penetration' ? penetrationHex(this.marketShareDistrictValues(String(feature?.properties?.KDCPUM || ''), units, 'total').penetration) : this.mappingDistrictFill(hierarchy, String(feature?.properties?.KDCPUM || '')),
                fillOpacity: this.mappingFocusedKey && !hierarchy?.ownership?.[String(feature?.properties?.KDCPUM || '')]?.groupKeys.includes(this.mappingFocusedKey) ? 0.2 : 0.96,
                color: '#f4e7da', weight: 0.8,
            });
            this.marketShareLayer._area6Palette = true;
        }
        const svg = this.marketShareLayer.getLayers()[0]?._path?.ownerSVGElement;
        if (svg && !svg.querySelector('[data-mapping-patterns]')) {
            const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
            defs.dataset.mappingPatterns = '1';
            defs.innerHTML = this.mappingPatternMarkup(hierarchy);
            svg.prepend(defs);
        }
        this.marketShareLayer.resetStyle();
        const scale = document.querySelector('[data-psd-marketshare-legend-scale]');
        if (scale) scale.style.background = `linear-gradient(90deg, ${PENETRATION_STOPS.map(([stop, color]) => `${color} ${stop}%`).join(', ')})`;
    }

    mappingDistrictFill(hierarchy, code) {
        const colors = hierarchyColors(hierarchy, code);
        return colors.length > 1 ? `url(#${mappingPatternId(code)})` : colors[0] || '#698097';
    }

    mappingPatternMarkup(hierarchy) {
        return Object.keys(hierarchy?.ownership || {}).map((code) => {
            const colors = hierarchyColors(hierarchy, code);
            if (colors.length < 2) return '';
            return `<pattern id="${mappingPatternId(code)}" width="${colors.length * 6}" height="12" patternUnits="userSpaceOnUse" patternTransform="rotate(35)">${colors.map((color, index) => `<rect x="${index * 6}" width="6" height="12" fill="${color}"/>`).join('')}</pattern>`;
        }).join('');
    }

    focusMapping(key = '') {
        const mapping = this.lastMappingWorld || this.worldMapping();
        const hierarchy = mapping?.hierarchy;
        if (!hierarchy) return;
        const selected = hierarchy.groups.find((group) => group.key === key);
        this.mappingFocusedKey = selected?.key || '';
        mapping.focusKey = this.mappingFocusedKey;
        mapping.reading = this.mappingReading;
        this.root.querySelectorAll('[data-mapping-group]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.mappingGroup === this.mappingFocusedKey)));
        this.root.querySelectorAll('[data-mapping-reading]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.mappingReading === this.mappingReading)));
        const detail = this.root.querySelector('[data-mapping-selection]');
        if (detail) detail.innerHTML = this.mappingSelection(mapping, selected);
        this.root.querySelectorAll('[data-mapping-stage]').forEach((host) => host.dispatchEvent(new CustomEvent('mappingfocus', { detail: { key: this.mappingFocusedKey, reading: this.mappingReading } })));
        this.root.querySelectorAll('[data-mapping-stage] [data-mapping-district]').forEach((path) => {
            const code = path.dataset.mappingDistrict;
            path.style.opacity = !this.mappingFocusedKey || hierarchy.ownership[code]?.groupKeys.includes(this.mappingFocusedKey) ? '1' : '.2';
            const district = mapping.districts.find((item) => item.code === code);
            path.setAttribute('fill', this.mappingReading === 'penetration' ? penetrationHex(district?.penetration) : this.mappingDistrictFill(hierarchy, code).replace('url(#', `url(#${path.closest('[data-map-poster-prefix]')?.dataset.mapPosterPrefix || 'poster-'}`));
        });
        this.marketShareLayer?.resetStyle();
        const legend = this.root.querySelector('.rp-map__legend');
        if (legend) legend.hidden = this.mappingReading !== 'penetration';
    }

    mappingSelection(mapping, group = null) {
        const hierarchy = mapping.hierarchy;
        const values = group || hierarchy.totals;
        const ratio = values.potential > 0 ? values.existing / values.potential * 100 : 0;
        const shared = group ? group.sharedCount : hierarchy.sharedCount;
        return `<strong>${h(group?.label || (hierarchy.level === 'branch' ? 'Seluruh cabang Area 6' : `Seluruh unit ${this.scopeLabel()}`))}</strong><dl><div><dt>Potensi</dt><dd>${h(formatInteger(values.potential))}</dd></div><div><dt>Nasabah BRI</dt><dd>${h(formatInteger(values.existing))}</dd></div><div><dt>Penetrasi</dt><dd>${h(formatPercent(ratio))}</dd></div></dl>${shared ? `<p>${formatInteger(shared)} kecamatan dilayani lebih dari satu unit. ${hierarchy.level === 'unit' ? 'Bidang bergaris menunjukkan cakupan layanan bersama.' : 'Buka cabang untuk melihat cakupan setiap unit.'}</p>` : ''}${group && hierarchy.level === 'branch' ? `<button type="button" class="rp-map-open" data-mapping-open-branch="${h(group.key)}">Lihat unit ${h(group.label)}</button>` : ''}${group && hierarchy.level === 'unit' && shared ? `<details><summary>Unit pada wilayah yang sama</summary><ul>${hierarchy.members.filter((member) => member.key !== group.key && member.districtCodes.some((code) => group.districtCodes.includes(code))).map((member) => `<li>${h(member.code)} ${h(member.label)}</li>`).join('')}</ul></details>` : ''}`;
    }

    mappingHierarchyPanel(mapping) {
        const hierarchy = mapping?.hierarchy;
        if (!hierarchy) return '<p class="rp-empty">Cakupan unit belum tersedia.</p>';
        return `<div class="rp-mapping-scenes">${hierarchy.groups.map((group) => `<article class="rp-mapping-cue" data-mapping-cue="${h(group.key)}" style="--group:${group.color}">
            <header><i aria-hidden="true"></i><div><h3>${h(group.label)}</h3><p>${formatInteger(group.unitCount)} unit melayani ${formatInteger(group.districtCodes.length)} kecamatan${group.sharedCount ? `; ${formatInteger(group.sharedCount)} kecamatan dilayani bersama` : ''}.</p></div></header>
            <dl class="rp-mapping-values"><div><dt>Potensi</dt><dd>${formatInteger(group.potential)}</dd></div><div><dt>Nasabah BRI</dt><dd>${formatInteger(group.existing)}</dd></div><div><dt>Penetrasi</dt><dd>${formatPercent(group.penetration)}</dd></div></dl>
            ${hierarchy.level === 'branch' ? `<p class="rp-source">Rincian per unit tersedia saat mode presentasi memilih ${h(group.label)}.</p>` : `<p class="rp-source">Kode unit ${h(group.code)}. ${group.sharedCount ? 'Bidang bergaris menunjukkan kecamatan yang juga dilayani unit lain.' : 'Warna bidang menunjukkan cakupan layanan unit.'}</p>`}
        </article>`).join('')}<article class="rp-mapping-cue rp-mapping-cue--penetration" data-mapping-cue="" data-mapping-view="penetration"><h3>Penetrasi per kecamatan.</h3><p>Seluruh cakupan kembali menyatu. Warna memperlihatkan penetrasi nasabah BRI pada masing-masing kecamatan.</p><div class="rp-penetration-key"><span>0%</span><i aria-hidden="true"></i><span>100%</span></div><p class="rp-source">Nilai unit yang melayani beberapa kecamatan dialokasikan merata pada pembacaan ini. Angka cabang dan unit di atas tetap memakai nilai sumber utuh.</p></article></div>`;
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
        const hierarchy = buildMappingHierarchy({ units, districts, area: this.isArea(), valuesOf: (unit) => this.marketShareUnitValues(unit, 'total') });
        const result = { districts, regencies, hierarchy, scopeLabel: this.scopeLabel(), reading: this.mappingReading, focusKey: this.mappingFocusedKey, center: centerOf(all), span: Math.max(all.maxLon - all.minLon, all.maxLat - all.minLat), units: units.length };
        this.lastMappingWorld = result;
        return result;
    }

    /* ---------------------------------------------------------- 0 cover -- */
    scrollCover() {
        const cards = this.summaryScope().cards || [];
        const os = findByKey(cards, 'os') || {};
        const funding = findByKey(cards, 'simpanan') || {};
        const sml = findByKey(cards, 'sml') || {};
        const npl = findByKey(cards, 'npl') || {};
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
                <div class="rp-cover__content">
                    <p class="rp-cover__brand" data-rv style="--i:0">
                        <img src="${h(danantara)}" alt="Danantara Indonesia">
                        <img src="${h(briLogo)}" alt="Bank Rakyat Indonesia">
                    </p>
                    <p class="rp-cover__region" data-rv>Region 13</p>
                    <h1 class="rp-cover__title" data-rv style="--i:1">Kinerja ${h(this.isArea() ? 'Area 6' : this.scopeLabel())}</h1>
                    <p class="rp-cover__sub" data-rv style="--i:2">Lima wilayah, satu arah pertumbuhan.</p>
                    <p class="rp-cover__lede" data-rv style="--i:3">4 kabupaten dan 1 kota di barat Jawa Timur. ${AREA6_TOTALS.districts} kecamatan, ${AREA6_TOTALS.wards} kelurahan, dan ${AREA6_TOTALS.villages} desa.</p>
                    <ul class="rp-cover__agenda">
                        ${agenda.map(([title, meta], index) => `<li data-rv style="--i:${index + 4}"><strong>${h(title)}</strong><small>${h(meta)}</small></li>`).join('')}
                    </ul>
                </div>
                <figure class="rp-cover__territory" data-rv style="--i:2">
                    <div class="rp-cover__art pres-world pres-world--area6" data-world="area6"><div class="pres-world__fallback">${area6Poster()}</div></div>
                    <figcaption>${area6Legend()}</figcaption>
                </figure>
                ${figures([
                    { label: 'Funding', value: funding.value || '-', meta: funding.trend ? `${funding.trend} tren` : 'Posisi terkini', tone: 'blue' },
                    { label: 'Outstanding', value: os.value || '-', meta: os.trend ? `${os.trend} tren` : 'Posisi terkini', tone: 'cyan' },
                    { label: 'SML', value: sml.value || '-', meta: sml.ratio || 'Rasio kualitas', tone: 'amber' },
                    { label: 'NPL', value: npl.value || '-', meta: npl.ratio || 'Rasio kualitas', tone: 'red' },
                ], 'rp-cover__figures')}
                <p class="rp-cover__foot">Posisi ${h(this.period())}<span>${h(this.isArea() ? 'Area 6' : this.scopeLabel())}</span></p>
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
            { chapter: 2, title: 'Pasar dan wilayah', part: 'Pangsa pasar dan penetrasi', description: 'Lihat posisi BRI di pasar dan kecamatan yang masih menyimpan peluang.' },
            { chapter: 4, title: 'Simpanan', part: 'Posisi dana dan sumber pertumbuhan', description: 'Kenali kontribusi produk, perkembangan cabang, dan peluang menambah simpanan.' },
            { chapter: 7, title: 'Pinjaman', part: 'SME, Konsumer, dan Mikro', description: 'Bandingkan penyaluran kredit dengan posisi sebelumnya dan target yang tersedia.' },
            { chapter: 11, title: 'Kualitas kredit', part: 'SML dan NPL', description: 'Temukan sumber tekanan kualitas dan tentukan prioritas penanganannya.' },
            { chapter: 13, title: 'Tren dan tindak lanjut', part: 'Pergerakan angka dan prioritas kerja', description: 'Hubungkan perubahan kinerja dengan langkah berikutnya.' },
        ];

        return `
            ${sectionHead({
                title: 'Yang kita bahas hari ini',
                subtitle: 'Dari peluang di wilayah kita, hingga langkah yang perlu diambil.',
                narrative: `Angka pada laporan ini mengikuti cakupan ${this.scopeLabel()} dan periode yang dipilih.`,
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
                { label: 'Cakupan laporan', value: this.scopeLabel(), meta: 'Berlaku untuk seluruh bagian' },
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
    marketPoster(market) {
        const rings = market.kind === 'quality' ? market.quality : market.competitors?.length ? [market.competitors[0]] : [];
        if (!rings?.length) return '<p class="rp-empty">Data market share belum tersedia.</p>';
        const quality = market.kind === 'quality';
        return `<svg viewBox="0 0 500 400" role="img" aria-label="${h(market.segment)}. ${h(rings.map((item) => `${item.name} ${item.label}`).join(', '))}">${rings.map((item, index) => {
            const x = quality ? 140 + index * 220 : 250;
            const radius = quality ? 82 : 128;
            const share = Number.isFinite(item.share) ? item.share : 0;
            // Each arc prints its own share at the arc's middle, in a dark chip that reads on both colours.
            const tag = (value, angle, label) => {
                const px = quality ? x : x + Math.cos((angle - 90) * Math.PI / 180) * radius;
                const py = quality ? 195 : 195 + Math.sin((angle - 90) * Math.PI / 180) * radius;
                return `<g class="rp-market-poster__tag"><rect x="${(px - 46).toFixed(1)}" y="${(py - 21).toFixed(1)}" width="92" height="42" rx="10" fill="#061730" fill-opacity=".72"/><text x="${px.toFixed(1)}" y="${(py - 1).toFixed(1)}" text-anchor="middle" fill="#f5f8fd" font-size="17" font-weight="700">${h(value)}</text><text x="${px.toFixed(1)}" y="${(py + 14).toFixed(1)}" text-anchor="middle" fill="#c9d8ee" font-size="11">${h(label)}</text></g>`;
            };
            const other = market.competitors?.[1];
            const tags = quality
                ? tag(item.label, 0, item.name)
                : `${tag(item.label, share * 1.8, item.name)}${other && Number.isFinite(other.share) && other.share > 0 ? tag(other.label, share * 3.6 + other.share * 1.8, 'Bank lain') : ''}`;
            return `<g><circle cx="${x}" cy="195" r="${radius}" fill="none" stroke="${quality ? '#263f59' : '#d4b789'}" stroke-width="${quality ? 26 : 48}"/><circle cx="${x}" cy="195" r="${radius}" fill="none" stroke="${item.color || '#4aaff0'}" stroke-width="${quality ? 26 : 48}" pathLength="100" stroke-dasharray="${share} ${100 - share}" transform="rotate(-90 ${x} 195)"/>${tags}</g>`;
        }).join('')}</svg>`;
    }

    /**
     * Four presenter beats read from the same workbook columns as the deck:
     * DPK, total loans, then the SML and NPL shares of total-loan quality.
     */
    marketStoryScenes(payload = this.marketShareAreaPayload()) {
        const segments = this.marketShareSegments(payload);
        const scope = this.isArea() ? 'Area 6' : this.scopeLabel();
        const valuesOf = (segment) => {
            const rows = Array.isArray(segment?.rows) ? segment.rows.filter(Boolean) : [];
            const branchRows = this.marketShareAreaRows(segment);
            const totalRow = rows.find((row) => row?.total || normaliseMarketShareToken(row?.branch) === 'area6') || null;
            const summary = this.isArea() ? totalRow : branchRows[0];
            return { values: Array.isArray(summary?.values) ? summary.values : [], branchRows };
        };
        const percent = (raw) => {
            const text = String(raw ?? '').trim();
            const number = /^[+-]?\d+(?:[.,]\d+)?\s*%?$/.test(text) ? Number(text.replace(/[%\s]/g, '').replace(',', '.')) : NaN;
            return Number.isFinite(number) ? number : null;
        };
        const complement = (label, share) => {
            const decimals = String(label || '').match(/[.,](\d+)/)?.[1]?.length || 0;
            return `${(100 - share).toLocaleString(String(label).includes(',') ? 'id-ID' : 'en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}%`;
        };
        const definitions = [
            { key: 'dpk', source: 'dpk', group: 'Simpanan', title: 'DPK', short: 'DPK', color: '#4aaff0', share: 14, bri: 2, industry: 6, ytd: 16, nominal: true,
                products: [['tabungan', 'Tabungan'], ['deposito', 'Deposito'], ['giro', 'Giro']] },
            { key: 'pinjaman_total', source: 'pinjaman_total', group: 'Pinjaman', title: 'Pinjaman', short: 'Pinjaman', color: '#35c3a4', share: 16, bri: 2, industry: 9, ytd: 18, nominal: true },
            { key: 'quality_sml', source: 'quality_total', group: 'Kualitas kredit', title: 'Kualitas SML', short: 'SML', color: '#f29d38', share: 16, bri: 1, industry: 9, ytd: 20, nominal: false },
            { key: 'quality_npl', source: 'quality_total', group: 'Kualitas kredit', title: 'Kualitas NPL', short: 'NPL', color: '#c77dde', share: 17, bri: 3, industry: 11, ytd: 21, nominal: false },
        ];
        return definitions.map((definition) => {
            const segment = segments.find((item) => item.key === definition.source);
            if (!segment) return null;
            const { values, branchRows } = valuesOf(segment);
            const shareLabel = valueOrDash(values[definition.share]);
            const share = percent(values[definition.share]);
            const valid = share !== null && share >= 0 && share <= 100;
            const otherLabel = valid ? complement(shareLabel, share) : '-';
            const products = (definition.products || []).map(([key, label]) => {
                const product = segments.find((item) => item.key === key);
                return product ? { label, value: valueOrDash(valuesOf(product).values[14]) } : null;
            }).filter(Boolean);
            const branches = this.isArea()
                ? branchRows.map((row) => ({ label: String(row?.branch || '-').replace(/^KC\s+/i, ''), value: valueOrDash(row?.values?.[definition.share]) }))
                : [];
            const ytd = valueOrDash(values[definition.ytd]);
            return {
                ...definition,
                scope,
                shareLabel,
                otherLabel,
                ytd,
                ytdTone: marketShareValueTone(ytd).replace('is-positive', 'is-green').replace('is-negative', 'is-red'),
                facts: definition.nominal
                    ? [['BRI', `Rp${valueOrDash(values[definition.bri])} M`], ['Industri', `Rp${valueOrDash(values[definition.industry])} M`]]
                    : [[`Rasio ${definition.short} BRI`, valueOrDash(values[definition.bri])], [`Rasio ${definition.short} industri`, valueOrDash(values[definition.industry])]],
                products,
                branches,
                world: {
                    key: definition.key,
                    kind: 'share',
                    segment: definition.title,
                    period: payload?.period || '-',
                    competitors: valid ? [
                        { name: 'BRI', share, label: shareLabel, color: definition.color },
                        { name: 'Bank lain (agregat)', share: 100 - share, label: otherLabel, color: '#d4b789' },
                    ] : [],
                },
            };
        }).filter(Boolean);
    }

    scrollMarketShare() {
        const payload = this.marketShareAreaPayload();
        const scenes = this.marketStoryScenes(payload);
        const scopeLabel = this.isArea() ? 'Area 6' : this.scopeLabel();
        const first = scenes[0]?.world || this.worldMarket();
        const beat = (scene, index) => `
            <section class="rp-market-beat" data-market-beat="${index}" data-market-key="${h(scene.key)}" style="--bank:${scene.color}" aria-label="${h(scene.title)}">
                <p class="rp-market-beat__kicker"><span>${index + 1} / ${scenes.length}</span>${h(scene.group)}</p>
                <h3>${h(scene.title)}</h3>
                <div class="rp-market-beat__hero"><strong>${h(scene.shareLabel)}</strong><span>pangsa BRI ${h(scene.scope)}${scene.nominal ? '' : ` atas ${h(scene.short)} industri`}</span></div>
                <div class="rp-market-beat__split" aria-hidden="true"><i style="--share:${h(String(scene.world.competitors[0]?.share ?? 0))}%"></i></div>
                <dl class="rp-market-beat__facts">
                    ${scene.facts.map(([label, value]) => `<div><dt>${h(label)}</dt><dd>${h(value)}</dd></div>`).join('')}
                    <div><dt>Bank lain</dt><dd>${h(scene.otherLabel)}</dd></div>
                    <div><dt>Perubahan pangsa YTD</dt><dd><span class="rp-delta ${scene.ytdTone}">${h(scene.ytd)}</span></dd></div>
                </dl>
                ${scene.products.length ? `<ul class="rp-market-beat__chips" aria-label="Pangsa per produk">${scene.products.map((item) => `<li><span>${h(item.label)}</span><b>${h(item.value)}</b></li>`).join('')}</ul>` : ''}
                ${scene.branches.length ? `<ul class="rp-market-beat__chips is-branches" aria-label="Pangsa per cabang">${scene.branches.map((item) => `<li><span>${h(item.label)}</span><b>${h(item.value)}</b></li>`).join('')}</ul>` : ''}
            </section>
        `;
        return `
            ${stickyBar(`Market share ${this.scopeLabel()}`, payload?.period || '')}
            ${sectionHead({ title: `Market Share ${scopeLabel}`, subtitle: 'DPK, pinjaman, lalu kualitas SML dan NPL: pangsa BRI dibanding bank lain.', narrative: 'Gulir untuk berpindah bagian. Biru, hijau, jingga, dan ungu adalah BRI; krem adalah bank lain.', period: payload?.period || this.period() })}
            ${scenes.length ? `
            <div class="rp-market-story" data-market-story data-market-journey data-world-track="pin" data-phase="0" style="--beats:${scenes.length}">
                <div class="rp-market-story__stage">
                    <div class="rp-market-story__beats">${scenes.map(beat).join('')}</div>
                    <div class="rp-market-story__visual">
                        <div class="pres-world rp-market-story__world" data-world="pasar" data-market-stage data-market-scene="${h(first.key || '')}" data-market-progress="1"><div class="pres-world__fallback rp-market-poster" data-market-poster>${this.marketPoster(first)}</div></div>
                        <nav class="rp-market-story__steps" aria-label="Bagian market share">${scenes.map((scene, index) => `<button type="button" data-market-step="${index}" style="--bank:${scene.color}" aria-pressed="${index === 0 ? 'true' : 'false'}">${h(scene.short)}</button>`).join('')}</nav>
                    </div>
                    <p class="rp-market-story__source">${h(payload?.source || 'Sumber market share')}. ${h(payload?.period || '-')}. Bank lain dihitung dari 100% dikurangi pangsa BRI.</p>
                    <div hidden>${scenes.map((scene) => `<template data-market-scene-art="${h(scene.key)}">${this.marketPoster(scene.world)}</template>`).join('')}</div>
                </div>
            </div>` : '<p class="rp-empty">Data market share sedang dimuat.</p>'}
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
        const mapWorld = this.worldMapping();
        const topBranch = [...(mapWorld?.hierarchy?.groups || [])].sort((left, right) => asNumber(right.penetration) - asNumber(left.penetration))[0] || null;
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
                title: `Mapping penetrasi ${scopeLabel}`,
                subtitle: this.isArea() ? 'Cakupan cabang dan potensi nasabah yang dilayani.' : 'Cakupan unit kerja dan potensi nasabah yang dilayani.',
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
            <div class="rp-mapping-journey" data-mapping-journey data-world-track="flow">
                <aside class="rp-mapping-stage"><div class="rp-mapping-stage__inner">
                    <p class="rp-market-stage__scope">${h(scopeLabel)}</p><h3 data-mapping-caption>${this.isArea() ? 'Potensi per cabang' : 'Potensi per unit'}</h3>
                    <div class="pres-world rp-mapping-stage__world" data-world="penetration" data-mapping-stage data-map-poster-prefix="detail-poster-"><div class="pres-world__fallback">${mapWorld ? area6Poster(mapWorld).replaceAll('poster-', 'detail-poster-') : ''}</div></div>
                    <p class="rp-mapping-stage__note">${this.isArea() ? 'Setiap warna menunjukkan cakupan cabang.' : 'Bidang bergaris menandai cakupan layanan bersama.'}</p>
                </div></aside>
                ${this.mappingHierarchyPanel(mapWorld)}
            </div>
            <p class="rp-source">Workbook ${h(mapping?.sheet || 'REKAP')}, diperbarui ${h(mapping?.updated_at || '-')}. Angka cabang dan unit dijumlahkan tanpa pengulangan. Bidang tetap mengikuti batas kecamatan; cakupan layanan bersama tidak membentuk batas unit baru.</p>
            ${this.insights([
                { label: 'Penetrasi agregat', value: penetrationText, meta: status.label, tone: status.tone },
                { label: 'Gap terhadap potensi', value: `${formatInteger(gap)} nasabah`, meta: `${formatInteger(districtCodes.size)} kecamatan terpetakan`, tone: 'amber' },
                this.isArea()
                    ? { label: 'Cabang penetrasi tertinggi', value: topBranch?.label || '-', meta: topBranch ? formatPercent(topBranch.penetration) : 'Belum tersedia', tone: 'cyan' }
                    : { label: 'Unit penetrasi tertinggi', value: ranked[0]?.unit?.name || '-', meta: ranked[0] ? this.formatMarketShareMappingMetric(ranked[0].score, 'penetration') : 'Belum tersedia', tone: 'cyan' },
            ])}
        `;
    }

    /** One geography, read first as a territory and then as a market. */
    orbitDescent({ scopeLabel, units, districtCount, potentialText, existingText, penetrationText }) {
        const mapping = this.worldMapping();
        if (!mapping?.hierarchy) return '';
        return `
            <div class="rp-orbit rp-territory-story" data-world-track="pin" data-area6-story data-phase="0">
                <div class="rp-orbit__stage">
                    <div class="rp-territory-visual">
                        <div class="pres-world pres-world--penetration" data-world="penetration"><div class="pres-world__fallback">${area6Poster(mapping)}</div></div>
                        <p class="rp-territory-caption"><span>Area 6, Region 13</span><span>${h(formatInteger(districtCount))} kecamatan pada cakupan ${h(scopeLabel)}</span></p>
                    </div>
                    <div class="rp-orbit__beats">
                        <section class="rp-orbit__beat" data-area6-beat="0" aria-label="Profil Area 6">
                            <h3>Area 6, Region 13.</h3>
                            <p>4 kabupaten dan 1 kota di barat Jawa Timur: Kabupaten Madiun, Kota Madiun, Magetan, Ngawi, dan Ponorogo.</p>
                            <dl class="rp-territory-counts"><div><dt>Kecamatan</dt><dd>${AREA6_TOTALS.districts}</dd></div><div><dt>Kelurahan</dt><dd>${AREA6_TOTALS.wards}</dd></div><div><dt>Desa</dt><dd>${AREA6_TOTALS.villages}</dd></div></dl>
                        </section>
                        <section class="rp-orbit__beat" data-area6-beat="1" aria-label="Potensi pasar">
                            <h3>Ruang untuk tumbuh.</h3>
                            <p>Pada cakupan ${h(scopeLabel)}, ${h(formatInteger(units.length))} unit kerja mencatat ${h(existingText)} nasabah BRI dari ${h(potentialText)} potensi nasabah.</p>
                            <div class="rp-territory-reach"><strong>${h(penetrationText)}</strong><span>penetrasi pada data terpilih</span></div>
                        </section>
                        <section class="rp-orbit__beat" data-area6-beat="2" aria-label="Mapping berdasarkan penetrasi">
                            <h3>${this.isArea() ? 'Potensi per cabang.' : 'Potensi per unit.'}</h3>
                            <p>${this.isArea() ? `${formatInteger(mapping.hierarchy.groups.length)} cabang melayani wilayah Area 6. Setiap warna menunjukkan cakupan cabang.` : `${formatInteger(mapping.hierarchy.groups.length)} unit melayani cakupan ${h(scopeLabel)}. Bidang bergaris menandai wilayah yang dilayani bersama.`}</p>
                            <ul class="rp-territory-identities">${mapping.hierarchy.groups.slice(0, 4).map((group) => `<li style="--group:${group.color}"><i aria-hidden="true"></i>${h(group.label)}</li>`).join('')}${mapping.hierarchy.groups.length > 4 ? `<li>+${mapping.hierarchy.groups.length - 4} unit lainnya</li>` : ''}</ul>
                        </section>
                    </div>

                </div>
            </div>
            ${area6ProfileDetails()}
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
                title: `Posisi simpanan ${this.scopeLabel()}`,
                subtitle: this.isArea() ? 'Perbandingan total Area 6 dan cabang dengan posisi historis lengkap.' : 'Perbandingan total cabang dan segmen Ritel, Wholesale, serta Mikro.',
                narrative: this.isArea()
                    ? `Funding ${this.scopeLabel()} berada pada ${total.current_fmt || '-'}. Tabel membandingkan total Area dengan empat cabang, dilengkapi posisi historis, delta, dan RKA.`
                    : `Simpanan ${this.scopeLabel()} sebesar ${total.current_fmt || '-'}, dengan rincian Ritel, Wholesale, dan Mikro.`,
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
        return `${stickyBar('Simpanan per produk', funding.total?.current_fmt || '')}${sectionHead({ title: 'Simpanan per produk', subtitle: 'Giro, Tabungan, dan Deposito dibahas berurutan.', narrative: 'Posisi historis seluruh produk diikuti tren dan kontribusi cabang untuk setiap produk.', period: periods.current?.label || this.period() })}
            ${comparisonTable({ title: 'Matriks produk funding', periods, rows: products.map((product) => ({ scope: this.scopeLabel(), dimension: product.label, metric: product })) })}
            ${products.map((product) => {
                const rows = this.isArea() ? (funding.branches || []).map((branch) => ({ label: branch.scope_label, metric: findByKey(branch.products, product.key) || {} })) : [{ label: this.scopeLabel(), metric: product }];
                const tone = product.key === 'deposito' ? 'amber' : 'blue';
                return `<section class="rp-product-reading" data-funding-reading="${h(product.key)}"><h3>${h(product.label)}</h3>${figures([
                    { label: 'Posisi', value: product.current_fmt || '-', meta: `${formatPercent(percentOf(product.current, funding.total?.current))} dari simpanan`, tone },
                    { label: 'Delta YoY', value: product.deltas_fmt?.yoy || '-', meta: periods.yoy?.label || '-', tone: asNumber(product.deltas?.yoy) >= 0 ? 'green' : 'red' },
                    { label: 'Delta MtD', value: product.deltas_fmt?.mtd || '-', meta: periods.mtd?.label || '-', tone: asNumber(product.deltas?.mtd) >= 0 ? 'green' : 'red' },
                ], 'is-compact')}<div class="rp-split">${trendChart({ title: `Timeseries ${product.label}`, labels: this.timeseriesScope()?.labels || [], series: this.timeseriesSeries(product.key), seriesKeys: [product.key], tone, note: 'Angka nominal mengikuti sumber pada setiap titik.' })}${bars({ title: `Distribusi ${product.label} per cabang`, rows, total: rows.reduce((sum, row) => sum + asNumber(row.metric?.current), 0), tone })}</div></section>`;
            }).join('')}`;
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
                subtitle: 'Peluang menambah simpanan melalui transaksi, payroll, nasabah, dan kerja sama bisnis.',
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
                title: `Posisi pinjaman ${this.scopeLabel()}`,
                subtitle: 'Perkembangan penyaluran kredit, kontribusi tiap segmen, dan pencapaian terhadap RKA.',
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
                    ? 'Posisi Kecil Non Cashcoll, Cashcoll, dan perkembangan produktivitas RM Ritel SME.'
                    : 'Posisi Briguna, KPR, dan perkembangan produktivitas RM Ritel Konsumer.',
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
                ${dataTable({
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
                })}
                ${dataTable({
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
                    ? 'Lihat perubahan SML dan cabang atau segmen yang memerlukan perhatian lebih awal.'
                    : 'Tentukan prioritas penanganan dari nominal NPL, rasio, dan perubahan tiap cabang atau segmen.',
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
        const allSeries = this.timeseriesSeries(scope);
        const labels = scope.labels || timeseries.labels || [];
        const business = this.businessSnapshot();
        return `${stickyBar('Timeseries kinerja terintegrasi')}${sectionHead({ title: 'Timeseries Kinerja Terintegrasi', subtitle: 'Seluruh kelompok indikator dibahas berurutan.', narrative: 'Skala bisnis, pertumbuhan, kualitas, rasio, dan komposisi mengikuti kelompok data yang tersedia pada laporan.', period: labels.length ? `${labels[0]} sampai ${labels.at(-1)}` : this.period() })}
            ${Object.entries(groups).map(([key, group]) => {
                const series = (group.keys || []).map((name) => findByKey(allSeries, name)).filter(Boolean);
                const isBusiness = (group.keys || []).includes('simpanan') && (group.keys || []).includes('os');
                const rows = labels.map((label, index) => ({ label, values: series.map((item) => item.display_values?.[index] || (item.values?.[index] == null ? '-' : item.format === 'percent' ? formatPercent(item.values[index]) : formatCurrency(asNumber(item.values[index]) * 1_000_000))) }));
                return `<section class="rp-trend-group" data-trend-group="${h(key)}"><header><h3>${h(group.label || key)}</h3><p>${h(group.description || '')}</p></header>
                    ${series.map((item) => trendChart({ title: item.label || item.key, labels, series: item, seriesKeys: [item.key], tone: ['sml', 'npl'].includes(item.key) ? 'amber' : 'blue', note: 'Nilai sumber pada setiap titik. Detail harian tersedia dengan membuka grafik.', fullHistory: true })).join('')}
                    ${isBusiness ? `<section class="rp-paper rp-legacy" data-rv>${this.renderBusinessQuadrant()}</section><p class="rp-source">LDR ${h(business.ldrLabel)} dihitung dari pinjaman dibagi simpanan.</p>` : ''}
                    ${dataTable({ title: `Posisi bulanan ${group.label || key}`, meta: `${rows.length} periode`, columns: [{ label: 'Periode', render: (row) => h(row.label) }, ...series.map((item, index) => ({ label: h(item.label), render: (row) => h(row.values[index]) }))], rows, empty: 'Timeseries belum tersedia pada cakupan ini.' })}
                </section>`;
            }).join('') || '<p class="rp-empty">Kelompok timeseries belum tersedia pada laporan ini.</p>'}`;
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
                title: 'Langkah berikutnya',
                subtitle: 'Fokus kerja berdasarkan pencapaian bisnis, kualitas kredit, dan produktivitas.',
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
                { label: 'Cakupan tindak lanjut', value: this.scopeLabel(), meta: `Posisi ${this.period()}` },
                { label: 'Perlu intervensi', value: weakest?.title || '-', meta: weakest?.trend || 'Belum tersedia', tone: 'red' },
                { label: 'Fokus eksekusi', value: 'Growth berkualitas', meta: 'Tutup gap tanpa menambah risiko', tone: 'green' },
            ])}
        `;
    }
}
