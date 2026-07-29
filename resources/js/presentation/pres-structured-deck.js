const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const asNumber = (value) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const formatCurrency = (value) => {
    const amount = asNumber(value);
    const absolute = Math.abs(amount);
    const formatter = (scaled, suffix) => {
        const decimals = Math.abs(scaled) >= 100 ? 1 : 2;
        return `Rp${scaled.toLocaleString('id-ID', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        })} ${suffix}`;
    };

    if (absolute >= 1_000_000_000_000) return formatter(amount / 1_000_000_000_000, 'T');
    if (absolute >= 1_000_000_000) return formatter(amount / 1_000_000_000, 'M');
    if (absolute >= 1_000_000) return formatter(amount / 1_000_000, 'Jt');
    return `Rp${Math.round(amount).toLocaleString('id-ID')}`;
};

const formatPercent = (value) => `${asNumber(value).toLocaleString('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})}%`;

const formatInteger = (value) => Math.round(asNumber(value)).toLocaleString('id-ID');

const valueOrDash = (value, fallback = '-') => {
    if (value === null || value === undefined || value === '') return fallback;
    return String(value);
};

const findByKey = (rows, key) => (Array.isArray(rows) ? rows : [])
    .find((row) => String(row?.key || '').toLowerCase() === String(key || '').toLowerCase());

const findScope = (scopes, scopeKey) => {
    if (!scopes || typeof scopes !== 'object') return null;
    return scopes[scopeKey]
        || Object.entries(scopes).find(([key]) => key.toUpperCase() === String(scopeKey).toUpperCase())?.[1]
        || scopes.area6
        || Object.values(scopes)[0]
        || null;
};

const compactName = (value) => String(value || '-')
    .replace(/^KC\s+/i, 'KC ')
    .replace(/\s+/g, ' ')
    .trim();

const sameOriginAsset = (value, fallback) => {
    const source = value || fallback;

    try {
        const resolved = new URL(source, window.location.origin);
        if (resolved.pathname.startsWith('/images/')) {
            return `${window.location.origin}${resolved.pathname}${resolved.search}`;
        }

        return resolved.href;
    } catch {
        return fallback;
    }
};

const deltaClass = (value, inverse = false) => {
    const amount = asNumber(value);
    if (amount === 0) return 'is-neutral';
    const positive = inverse ? amount < 0 : amount > 0;
    return positive ? 'is-positive' : 'is-negative';
};

const percentOf = (value, total) => {
    const denominator = asNumber(total);
    return denominator === 0 ? 0 : (asNumber(value) / denominator) * 100;
};

const maxBy = (rows, field) => (Array.isArray(rows) ? rows : [])
    .reduce((leader, row) => asNumber(row?.[field]) > asNumber(leader?.[field]) ? row : leader, null);

const metricValue = (metrics, key, field, fallback = 0) => asNumber(metrics?.[key]?.[field] ?? fallback);

const iconFor = (key) => ({
    retail: 'fa-store',
    wholesale: 'fa-building',
    micro: 'fa-users',
    giro: 'fa-university',
    tabungan: 'fa-wallet',
    deposito: 'fa-vault',
    sme: 'fa-briefcase',
    consumer: 'fa-user-tie',
    briguna: 'fa-id-card',
    kpr: 'fa-house',
    non_cashcoll: 'fa-chart-line',
    cashcoll: 'fa-shield-halved',
    briguna_mikro: 'fa-address-card',
    kupedes: 'fa-people-group',
    kur_mikro: 'fa-store',
    kur_kecil: 'fa-building',
    kpp: 'fa-briefcase',
}[key] || 'fa-chart-column');

const storyHeader = ({ kicker, title, subtitle, narrative, period, tone = 'blue', controls = '' }) => `
    <header class="psd-header">
        <div class="psd-heading">
            <div class="psd-heading-meta">
                <span class="psd-kicker">${escapeHtml(kicker)}</span>
                ${period ? `<span class="psd-period"><i class="fas fa-calendar-day" aria-hidden="true"></i> Posisi data: ${escapeHtml(period)}</span>` : ''}
            </div>
            <h1>${escapeHtml(title)}</h1>
            <p>${escapeHtml(subtitle)}</p>
        </div>
        <div class="psd-header-side">
            ${controls ? `<div class="psd-header-controls">${controls}</div>` : ''}
            <div class="psd-reading is-${escapeHtml(tone)}">
                <span><i class="fas fa-comment-dots" aria-hidden="true"></i> Pembacaan data</span>
                <p>${escapeHtml(narrative)}</p>
            </div>
        </div>
    </header>
`;

const statCard = ({ label, value, meta = '', tone = 'blue', raw = null, percent = false }) => `
    <article class="psd-stat is-${escapeHtml(tone)}">
        <span>${escapeHtml(label)}</span>
        <strong${raw !== null ? ` data-raw-val="${escapeHtml(raw)}" data-is-currency="${percent ? 'false' : 'true'}" data-is-percent="${percent ? 'true' : 'false'}"` : ''}>${escapeHtml(value)}</strong>
        ${meta ? `<small>${escapeHtml(meta)}</small>` : ''}
    </article>
`;

const insightStrip = (items) => `
    <footer class="psd-insight-strip" style="--psd-insights:${Math.max(1, items.length)}">
        <div class="psd-insight-label">
            <i class="fas fa-bullseye" aria-hidden="true"></i>
            <span>Fokus pembahasan</span>
            <small>Angka, risiko, dan arah tindakan</small>
        </div>
        <div class="psd-insight-grid">
            ${items.map((item) => `
                <div class="psd-insight is-${escapeHtml(item.tone || 'blue')}">
                    <span>${escapeHtml(item.label)}</span>
                    <strong>${escapeHtml(item.value)}</strong>
                    <small>${escapeHtml(item.meta || '')}</small>
                </div>
            `).join('')}
        </div>
    </footer>
`;

const panelTitle = (title, meta = '', icon = 'fa-table') => `
    <div class="psd-panel-title">
        <span><i class="fas ${escapeHtml(icon)}" aria-hidden="true"></i> ${escapeHtml(title)}</span>
        ${meta ? `<small>${escapeHtml(meta)}</small>` : ''}
    </div>
`;

const matrixTable = ({ columns, rows, emptyMessage = 'Data belum tersedia pada scope ini.', className = '' }) => {
    const safeRows = Array.isArray(rows) ? rows.filter(Boolean) : [];
    const template = columns.map((column) => column.width || '1fr').join(' ');
    const header = columns.map((column) => `<span class="${column.align === 'right' ? 'is-right' : ''}">${escapeHtml(column.label)}</span>`).join('');
    const body = safeRows.length
        ? safeRows.map((row, rowIndex) => `
            <div class="psd-matrix-row ${escapeHtml(row.className || '')}" style="--psd-columns:${template}">
                ${columns.map((column) => {
                    const cell = typeof column.render === 'function' ? column.render(row, rowIndex) : valueOrDash(row?.[column.key]);
                    return `<div class="${column.align === 'right' ? 'is-right' : ''}">${cell}</div>`;
                }).join('')}
            </div>
        `).join('')
        : `<div class="psd-empty"><i class="fas fa-circle-info" aria-hidden="true"></i><span>${escapeHtml(emptyMessage)}</span></div>`;

    return `
        <div class="psd-matrix ${escapeHtml(className)}">
            <div class="psd-matrix-head" style="--psd-columns:${template}">${header}</div>
            <div class="psd-matrix-body" style="--psd-row-count:${Math.max(1, safeRows.length)}">${body}</div>
        </div>
    `;
};

const progressCell = (value, maximum, text, tone = 'blue') => {
    const width = Math.max(2, Math.min(100, percentOf(value, maximum)));
    return `
        <div class="psd-progress-cell">
            <strong>${escapeHtml(text)}</strong>
            <span><i class="is-${escapeHtml(tone)}" style="width:${width}%"></i></span>
        </div>
    `;
};

const metricCells = (metric, prefix = '') => {
    const raw = asNumber(metric?.[`${prefix}raw`] ?? metric?.[prefix] ?? 0);
    const formatted = metric?.[prefix.replace(/_$/, '')] || formatCurrency(raw);
    return { raw, formatted };
};

const comparisonPresentationState = {
    usePrognosa: false,
    prognosa: {},
};

const comparisonTable = ({
    periods = {},
    rows = [],
    inverse = false,
    showRatio = false,
    emptyMessage = 'Data perbandingan belum tersedia.',
    usePrognosa = comparisonPresentationState.usePrognosa,
    prognosa = comparisonPresentationState.prognosa,
}) => {
    const positionKeys = ['yoy', 'ytd', 'm2', 'mom', 'mtd', 'dtd', 'current'];
    const deltaKeys = ['yoy', 'ytd', 'mom', 'mtd', 'dtd'];
    const periodHead = (key) => {
        const period = periods[key] || {};
        return `<span>${escapeHtml(period.name || key)}</span><small>${escapeHtml(period.label || '-')}</small>`;
    };
    const safeRows = (Array.isArray(rows) ? rows : []).filter(Boolean);
    const showPrognosa = Boolean(usePrognosa && prognosa?.available);
    const forecastLabel = prognosa?.label || `Prognosa ${prognosa?.week_label || ''}`.trim();
    const forecastDate = prognosa?.forecast_date_label || '-';
    const positionDate = prognosa?.comparison_position_label || prognosa?.position_date_label || '-';
    const columnCount = 17 + (showPrognosa ? 2 : 0);

    return `
        <div class="psd-v2-comparison-wrap">
            <table class="psd-v2-comparison-table ${showPrognosa ? 'has-prognosa' : ''}">
                <colgroup>
                    <col class="is-scope"><col class="is-dimension">
                    ${positionKeys.map(() => '<col class="is-value">').join('')}
                    ${deltaKeys.map(() => '<col class="is-delta">').join('')}
                    ${showPrognosa ? '<col class="is-prognosa"><col class="is-prognosa-delta">' : ''}
                    <col class="is-rka"><col class="is-rka"><col class="is-achievement">
                </colgroup>
                <thead>
                    <tr class="psd-v2-group-head">
                        <th rowspan="2">Scope</th>
                        <th rowspan="2">Segmen / Produk</th>
                        <th colspan="7">Posisi</th>
                        <th colspan="5">Delta terhadap posisi</th>
                        ${showPrognosa ? `<th colspan="2" class="is-prognosa">${escapeHtml(forecastLabel)}</th>` : ''}
                        <th colspan="3">RKA</th>
                    </tr>
                    <tr class="psd-v2-date-head">
                        ${positionKeys.map((key) => `<th class="${key === 'current' ? 'is-current' : ''}">${periodHead(key)}</th>`).join('')}
                        ${deltaKeys.map((key) => `<th>${periodHead(key)}</th>`).join('')}
                        ${showPrognosa ? `
                            <th class="is-prognosa"><span>Posisi</span><small>${escapeHtml(forecastDate)}</small></th>
                            <th class="is-prognosa"><span>Delta vs Posisi</span><small>${escapeHtml(positionDate)}</small></th>
                        ` : ''}
                        <th>Posisi</th><th>Gap</th><th>Penc.</th>
                    </tr>
                </thead>
                <tbody>
                    ${safeRows.length ? safeRows.map((row) => {
                        const metric = row.metric || {};
                        return `
                            <tr class="${escapeHtml(row.className || '')}">
                                <td><strong>${escapeHtml(row.scope || '-')}</strong></td>
                                <td><span>${escapeHtml(row.dimension || '-')}</span></td>
                                ${positionKeys.map((key) => `
                                    <td class="${key === 'current' ? 'is-current' : ''}">
                                        <span>${escapeHtml(
                                            metric.positions_fmt?.[key]
                                            || formatCurrency(metric.positions?.[key])
                                        )}</span>
                                        ${showRatio ? `<small>${escapeHtml(metric.ratio_positions_fmt?.[key] || '-')}</small>` : ''}
                                    </td>
                                `).join('')}
                                ${deltaKeys.map((key) => `
                                    <td class="${deltaClass(metric.deltas?.[key], inverse)}">${escapeHtml(
                                        metric.deltas_fmt?.[key]
                                        || formatCurrency(metric.deltas?.[key])
                                    )}</td>
                                `).join('')}
                                ${showPrognosa ? `
                                    <td class="is-prognosa">
                                        <span>${escapeHtml(metric.prognosa_fmt || '-')}</span>
                                        ${showRatio ? `<small>${escapeHtml(metric.prognosa_ratio_fmt || '-')}</small>` : ''}
                                    </td>
                                    <td class="is-prognosa ${metric.prognosa_available ? deltaClass(metric.prognosa_delta, inverse) : ''}">
                                        ${escapeHtml(metric.prognosa_delta_fmt || '-')}
                                    </td>
                                ` : ''}
                                <td>${escapeHtml(metric.rka_fmt || '-')}</td>
                                <td class="${deltaClass(metric.gap, inverse)}">${escapeHtml(metric.gap_fmt || '-')}</td>
                                <td class="${asNumber(metric.achievement) >= 100 ? 'is-positive' : metric.achievement == null ? '' : 'is-negative'}">${escapeHtml(metric.achievement_fmt || '-')}</td>
                            </tr>
                        `;
                    }).join('') : `<tr><td colspan="${columnCount}" class="psd-v2-empty-cell">${escapeHtml(emptyMessage)}</td></tr>`}
                </tbody>
            </table>
        </div>
    `;
};

const miniTrendChart = ({
    title,
    labels = [],
    series = {},
    seriesKeys = [],
    tone = 'blue',
    note = '',
}) => {
    const values = Array.isArray(series.values) ? series.values : [];
    const displayValues = Array.isArray(series.display_values) ? series.display_values : [];
    const count = Math.min(7, labels.length, values.length);
    if (count <= 0) {
        return `
            <section class="psd-v2-trend-panel">
                ${panelTitle(title, 'Belum ada data', 'fa-chart-line')}
                <div class="psd-v2-empty-chart">Timeseries belum tersedia pada scope ini.</div>
            </section>
        `;
    }

    const chartLabels = labels.slice(-count);
    const chartValues = values.slice(-count).map(asNumber);
    const chartDisplay = displayValues.slice(-count);
    const width = 660;
    const height = 158;
    const left = 42;
    const right = 20;
    const top = 32;
    const bottom = 31;
    const minimum = Math.min(...chartValues);
    const maximum = Math.max(...chartValues);
    const spread = maximum - minimum || Math.max(1, Math.abs(maximum) * 0.08);
    const x = (index) => left + ((width - left - right) * (count === 1 ? 0.5 : index / (count - 1)));
    const y = (value) => top + ((height - top - bottom) * (1 - ((value - minimum) / spread)));
    const points = chartValues.map((value, index) => `${x(index)},${y(value)}`).join(' ');
    const area = `${left},${height - bottom} ${points} ${x(count - 1)},${height - bottom}`;
    const modalKeys = (Array.isArray(seriesKeys) ? seriesKeys : [seriesKeys])
        .map((key) => String(key || '').trim())
        .filter(Boolean);
    if (!modalKeys.length && series?.key) {
        modalKeys.push(String(series.key));
    }

    return `
        <section class="psd-v2-trend-panel is-${escapeHtml(tone)}">
            ${panelTitle(title, `${count} periode terakhir · Klik 2× untuk detail harian`, 'fa-chart-line')}
            <div
                class="psd-v2-trend-chart is-expandable"
                data-psd-timeseries-expand
                data-psd-timeseries-keys="${escapeHtml(modalKeys.join(','))}"
                data-psd-timeseries-title="${escapeHtml(title)}"
                role="button"
                tabindex="0"
                aria-haspopup="dialog"
                aria-label="Buka ${escapeHtml(title)} harian dalam tampilan penuh"
                title="Klik dua kali untuk membuka detail harian empat bulan"
            >
                <svg viewBox="0 0 ${width} ${height}" role="img" aria-label="${escapeHtml(title)}">
                    <line class="psd-v2-chart-grid" x1="${left}" y1="${top}" x2="${width - right}" y2="${top}"></line>
                    <line class="psd-v2-chart-grid" x1="${left}" y1="${top + ((height - top - bottom) / 2)}" x2="${width - right}" y2="${top + ((height - top - bottom) / 2)}"></line>
                    <line class="psd-v2-chart-axis" x1="${left}" y1="${height - bottom}" x2="${width - right}" y2="${height - bottom}"></line>
                    <polygon class="psd-v2-chart-area" points="${area}"></polygon>
                    <polyline class="psd-v2-chart-line" points="${points}"></polyline>
                    ${chartValues.map((value, index) => `
                        <g>
                            <circle class="psd-v2-chart-point" cx="${x(index)}" cy="${y(value)}" r="4"></circle>
                            <text class="psd-v2-chart-value" x="${x(index)}" y="${Math.max(13, y(value) - 13)}" text-anchor="middle">${escapeHtml(
                                chartDisplay[index] || value.toLocaleString('id-ID')
                            )}</text>
                            <text class="psd-v2-chart-label" x="${x(index)}" y="${height - 10}" text-anchor="middle">${escapeHtml(chartLabels[index])}</text>
                        </g>
                    `).join('')}
                </svg>
                ${note ? `<p>${escapeHtml(note)}</p>` : ''}
            </div>
        </section>
    `;
};

const distributionPanel = ({ title, rows = [], total = 0, valueKey = 'current', tone = 'blue' }) => {
    const maximum = Math.max(1, ...rows.map((row) => asNumber(row.metric?.[valueKey] ?? row.value)));
    return `
        <section class="psd-v2-distribution-panel">
            ${panelTitle(title, `${rows.length} komponen`, 'fa-chart-bar')}
            <div class="psd-v2-distribution-list">
                ${rows.map((row, index) => {
                    const value = asNumber(row.metric?.[valueKey] ?? row.value);
                    const share = percentOf(value, total);
                    return `
                        <article>
                            <span class="psd-v2-rank">${index + 1}</span>
                            <div>
                                <strong>${escapeHtml(row.label || '-')}</strong>
                                <small>${escapeHtml(row.meta || `${formatPercent(share)} dari total`)}</small>
                            </div>
                            <b>${escapeHtml(row.value_fmt || row.metric?.current_fmt || formatCurrency(value))}</b>
                            <em><i class="is-${escapeHtml(tone)}" style="width:${Math.max(2, percentOf(value, maximum))}%"></i></em>
                        </article>
                    `;
                }).join('')}
            </div>
        </section>
    `;
};

const quadrantHistoryPanel = ({ title, history = {} }) => {
    const rows = (history.rows || []).slice(-7);
    const coverage = history.coverage || {};
    const coverageLabel = asNumber(coverage.source_total) > 0
        ? `${asNumber(coverage.classified)}/${asNumber(coverage.source_total)} RM terklasifikasi`
        : `${rows.length} bulan`;
    return `
        <section class="psd-v2-quadrant-panel">
            ${panelTitle(title, coverageLabel, 'fa-border-all')}
            <div class="psd-v2-quadrant-table">
                <div class="psd-v2-quadrant-head">
                    <span>Periode</span><span>Q4</span><span>Q3</span><span>Q2</span><span>Q1</span><span>Total RM</span>
                </div>
                ${rows.length ? rows.map((row) => `
                    <div class="psd-v2-quadrant-row">
                        <strong>${escapeHtml(row.label || '-')}</strong>
                        <span class="is-q4">${formatInteger(row.q4)}</span>
                        <span class="is-q3">${formatInteger(row.q3)}</span>
                        <span class="is-q2">${formatInteger(row.q2)}</span>
                        <span class="is-q1">${formatInteger(row.q1)}</span>
                        <b>${formatInteger(row.total)}</b>
                        <em>
                            ${['q4', 'q3', 'q2', 'q1'].map((key) => `<i class="is-${key}" style="width:${percentOf(row[key], row.total)}%"></i>`).join('')}
                        </em>
                    </div>
                `).join('') : '<div class="psd-v2-empty-chart">Histori kuadran RM belum tersedia.</div>'}
            </div>
        </section>
    `;
};

export class StructuredDeckRenderer {
    constructor({ root = document, getScope, getUsePrognosa, chartManager, requestKts }) {
        this.root = root;
        this.getScope = getScope;
        this.getUsePrognosa = getUsePrognosa;
        this.chartManager = chartManager;
        this.requestKts = requestKts;
        this.data = null;
        this.activeIndex = 0;
        this.trendGroup = 'business';
        this.fundingProduct = 'giro';
        this.ktsCategory = 'membaik';
        this.ktsScope = 'ritel';
        this.microProductivityView = 'extreme_low';
        this.trendChart = null;
        this.timeseriesModal = null;
        this.timeseriesModalChart = null;
        this.timeseriesModalPayload = null;
        this.timeseriesModalMetricKey = null;
        this.timeseriesModalRestoreFocus = null;
        this.timeseriesModalBodyWasLocked = false;
        this.handleTimeseriesModalKeydown = (event) => {
            if (!this.timeseriesModal) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopImmediatePropagation();
                this.closeTimeseriesModal();
                return;
            }

            if (event.key !== 'Tab') return;
            const focusable = Array.from(this.timeseriesModal.querySelectorAll(
                'button:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'
            )).filter((element) => !element.hasAttribute('hidden'));
            if (!focusable.length) {
                event.preventDefault();
                return;
            }

            const first = focusable[0];
            const last = focusable.at(-1);
            const activeElement = this.timeseriesModal.ownerDocument.activeElement;
            if (!this.timeseriesModal.contains(activeElement)) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus();
            } else if (event.shiftKey && activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };
        this.bound = false;
        this.bindInteractions();
    }

    bindInteractions() {
        if (this.bound) return;
        this.bound = true;
        this.root.addEventListener('click', (event) => {
            const microViewButton = event.target.closest('[data-psd-micro-view]');
            if (microViewButton) {
                this.microProductivityView = microViewButton.dataset.psdMicroView === 'rm_kur'
                    ? 'rm_kur'
                    : 'extreme_low';
                this.renderSlide(8);
                this.activate(8);
                return;
            }

            const trendButton = event.target.closest('[data-psd-trend-group]');
            if (trendButton) {
                this.trendGroup = trendButton.dataset.psdTrendGroup || 'business';
                this.renderSlide(11);
                this.activate(11);
                return;
            }

            const fundingProduct = event.target.closest('[data-psd-funding-product]');
            if (fundingProduct) {
                this.fundingProduct = fundingProduct.dataset.psdFundingProduct || 'giro';
                this.renderSlide(3);
                this.activate(3);
                return;
            }

        });

        this.root.addEventListener('dblclick', (event) => {
            const trigger = event.target.closest('[data-psd-timeseries-expand]');
            if (!trigger) return;
            event.preventDefault();
            this.openTimeseriesModal(trigger);
        });

        this.root.addEventListener('keydown', (event) => {
            const trigger = event.target.closest('[data-psd-timeseries-expand]');
            if (!trigger || !['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            event.stopPropagation();
            this.openTimeseriesModal(trigger);
        });
    }

    render(data) {
        this.setData(data);
        for (let index = 0; index < 13; index += 1) {
            this.renderSlide(index);
        }
    }

    renderSection(section, data) {
        this.setData(data);
        const slideIndexes = {
            micro: [8],
            productivity: [6, 7, 12],
            timeseries: [2, 3, 5, 6, 7, 9, 10, 11],
            digital: [4, 12],
        };

        (slideIndexes[section] || []).forEach((index) => this.renderSlide(index));
    }

    setData(data) {
        this.data = data || {};
        comparisonPresentationState.prognosa = this.data?.comparison?.prognosa || {};
        comparisonPresentationState.usePrognosa = Boolean(
            comparisonPresentationState.prognosa?.available
            && this.getUsePrognosa?.()
        );
    }

    renderSlide(index) {
        const slide = document.getElementById(`pres-slide-${index}`);
        if (!slide || !this.data) return;

        if ([2, 3, 5, 6, 7, 9, 10, 11].includes(index)) {
            this.closeTimeseriesModal({ restoreFocus: false });
        }
        if (index === 11) {
            this.trendChart?.destroy();
            this.trendChart = null;
        }

        const renderers = [
            () => this.renderCover(),
            () => this.renderAgendaV2(),
            () => this.renderFundingSummaryV2(),
            () => this.renderFundingProductsV2(),
            () => this.renderStrategiesV2(),
            () => this.renderLoanSummaryV2(),
            () => this.renderSegmentPerformanceV2('sme'),
            () => this.renderSegmentPerformanceV2('consumer'),
            () => this.renderMicroHighlight(),
            () => this.renderQualityV2('sml'),
            () => this.renderQualityV2('npl'),
            () => this.renderTimeseries(),
            () => this.renderPrioritiesV2(),
        ];

        slide.innerHTML = renderers[index]?.() || '';
        slide.classList.remove('is-section-loading');
        slide.setAttribute('aria-busy', 'false');
    }

    activate(index) {
        this.activeIndex = index;
        if (index !== 11) {
            this.closeTimeseriesModal({ restoreFocus: false });
        }
        if (index === 11) {
            window.requestAnimationFrame(() => this.drawTimeseriesChart());
        }
        const slide = document.getElementById(`pres-slide-${index}`);
        this.chartManager?.activate(slide);
    }

    scopeKey() {
        return this.getScope?.() || this.data?.scope?.default || 'area6';
    }

    scopeOption() {
        const key = this.scopeKey();
        const options = this.data?.scope?.options || [];
        return options.find((option) => option.key === key)
            || options.find((option) => String(option.key).toUpperCase() === String(key).toUpperCase())
            || { key, label: key === 'area6' ? 'Area 6 Konsol' : key };
    }

    scopeLabel() {
        return this.scopeOption().label || 'Area 6 Konsol';
    }

    isArea() {
        return this.scopeKey() === 'area6';
    }

    period() {
        return this.data?.meta?.period_label || this.data?.meta?.loan_period_label || '-';
    }

    summaryScope() {
        return findScope(this.data?.summary?.scopes, this.scopeKey()) || {};
    }

    summaryMetric(key) {
        return this.summaryScope()?.metrics?.[key] || {};
    }

    summaryCard(key) {
        return findByKey(this.summaryScope()?.cards || this.data?.summary?.cards, key) || {};
    }

    comparisonScope(scopeKey = this.scopeKey()) {
        return findScope(this.data?.comparison?.scopes, scopeKey) || {};
    }

    comparisonPeriods() {
        return this.data?.comparison?.periods || {};
    }

    productivityCategory(key) {
        const scope = findScope(this.data?.productivity?.scopes, this.scopeKey()) || {};
        const categories = scope.categories || {};
        if (Array.isArray(categories)) {
            return findByKey(categories, key) || {};
        }
        return categories[key] || {};
    }

    branches() {
        const rows = Array.isArray(this.data?.performance_overview?.branches)
            ? this.data.performance_overview.branches
            : [];
        if (this.isArea()) return rows;
        const label = this.scopeLabel().toUpperCase();
        return rows.filter((row) => String(row?.name || '').toUpperCase() === label);
    }

    fundingScope() {
        const structured = findScope(this.data?.funding_structure?.scopes, this.scopeKey());
        if (structured) return structured;

        const saving = findScope(this.data?.savings_breakdown?.scopes, this.scopeKey()) || {};
        const cards = Array.isArray(saving.cards) ? saving.cards : [];
        const total = findByKey(cards, 'total_simpanan') || {};
        const products = ['giro', 'tabungan', 'deposito'].map((key) => {
            const card = findByKey(cards, key) || {};
            return {
                key,
                label: card.label || key,
                value_raw: asNumber(card.value_raw),
                value: card.value || formatCurrency(card.value_raw),
                share_raw: asNumber(card.pct_raw),
                share: card.pct || formatPercent(card.pct_raw),
            };
        });
        const branches = this.isArea()
            ? (this.data?.scope?.options || []).filter((option) => option.key !== 'area6').map((option) => {
                const branchSaving = findScope(this.data?.savings_breakdown?.scopes, option.key) || {};
                const branchCards = branchSaving.cards || [];
                const branchTotal = findByKey(branchCards, 'total_simpanan') || {};
                return {
                    scope_key: option.key,
                    scope_label: option.label,
                    total_raw: asNumber(branchTotal.value_raw),
                    total: branchTotal.value || formatCurrency(branchTotal.value_raw),
                    products: ['giro', 'tabungan', 'deposito'].map((key) => {
                        const card = findByKey(branchCards, key) || {};
                        return {
                            key,
                            label: card.label || key,
                            value_raw: asNumber(card.value_raw),
                            value: card.value || formatCurrency(card.value_raw),
                            share_raw: asNumber(card.pct_raw),
                            share: card.pct || formatPercent(card.pct_raw),
                        };
                    }),
                    segments: [],
                };
            })
            : [];

        return {
            available: asNumber(total.value_raw) > 0,
            scope_key: this.scopeKey(),
            scope_label: this.scopeLabel(),
            period_label: saving.period_label || this.period(),
            total_raw: asNumber(total.value_raw),
            total: total.value || formatCurrency(total.value_raw),
            segments: [],
            products,
            branches,
        };
    }

    creditScope() {
        const structured = findScope(this.data?.credit_structure?.scopes, this.scopeKey());
        if (structured) return structured;

        const scope = this.summaryScope();
        const os = this.summaryCard('os');
        const sml = this.summaryCard('sml');
        const npl = this.summaryCard('npl');
        const segments = [
            ['sme', 'SME', 'sme_os'],
            ['consumer', 'Konsumer', 'consumer_os'],
            ['micro', 'Mikro', 'micro_os'],
        ].map(([key, label, metricKey]) => {
            const metric = scope?.metrics?.[metricKey] || {};
            return {
                key,
                label,
                os_raw: asNumber(metric.latest),
                os: metric.latest_fmt || formatCurrency(metric.latest),
                sml_raw: 0,
                sml: 'Memuat struktur',
                sml_ratio_raw: 0,
                sml_ratio: '-',
                npl_raw: 0,
                npl: 'Memuat struktur',
                npl_ratio_raw: 0,
                npl_ratio: '-',
                products: key === 'micro'
                    ? (findScope(this.data?.loan_products?.scopes, this.scopeKey())?.rows || []).map((row) => ({
                        key: row.key,
                        label: row.label,
                        os_raw: asNumber(row.os_raw),
                        os: row.os,
                        sml_raw: asNumber(row.sml_raw),
                        sml: row.sml,
                        sml_ratio_raw: percentOf(row.sml_raw, row.os_raw),
                        sml_ratio: row.sml_pct,
                        npl_raw: asNumber(row.npl_raw),
                        npl: row.npl,
                        npl_ratio_raw: percentOf(row.npl_raw, row.os_raw),
                        npl_ratio: row.npl_pct,
                    }))
                    : [],
            };
        });

        return {
            available: asNumber(os.value_raw) > 0,
            scope_key: this.scopeKey(),
            scope_label: this.scopeLabel(),
            period_label: this.period(),
            total: {
                os_raw: asNumber(os.value_raw),
                os: os.value || formatCurrency(os.value_raw),
                sml_raw: asNumber(sml.value_raw),
                sml: sml.value || formatCurrency(sml.value_raw),
                sml_ratio_raw: asNumber(sml.ratio_raw),
                sml_ratio: sml.ratio || formatPercent(sml.ratio_raw),
                npl_raw: asNumber(npl.value_raw),
                npl: npl.value || formatCurrency(npl.value_raw),
                npl_ratio_raw: asNumber(npl.ratio_raw),
                npl_ratio: npl.ratio || formatPercent(npl.ratio_raw),
            },
            segments,
            branches: this.branches().map((row) => ({
                scope_label: row.name,
                total: {
                    os_raw: asNumber(row.pinjaman),
                    os: row.pinjaman_fmt,
                    sml_raw: asNumber(row.sml_abs),
                    sml: row.sml_abs_fmt,
                    sml_ratio_raw: asNumber(row.sml_pct),
                    sml_ratio: row.sml_pct_fmt,
                    npl_raw: asNumber(row.npl_abs),
                    npl: row.npl_abs_fmt,
                    npl_ratio_raw: asNumber(row.npl_pct),
                    npl_ratio: row.npl_pct_fmt,
                },
                segments: [],
            })),
        };
    }

    renderCover() {
        const summary = this.summaryScope();
        const cards = summary.cards || [];
        const os = findByKey(cards, 'os') || {};
        const funding = findByKey(cards, 'simpanan') || {};
        const sml = findByKey(cards, 'sml') || {};
        const npl = findByKey(cards, 'npl') || {};
        const image = sameOriginAsset(this.data?.assets?.branch_building, '/images/bri-area6-building.png');
        const briLogo = sameOriginAsset(this.data?.assets?.bri_logo, '/images/bri-logo-template.png');
        const danantara = sameOriginAsset(this.data?.assets?.danantara_logo, '/images/danantara-logo-template.png');

        return `
            <div class="psd-cover" style="--psd-cover-image:url('${escapeHtml(image)}')">
                <div class="psd-cover-brand">
                    <img src="${escapeHtml(danantara)}" alt="Danantara Indonesia">
                    <span></span>
                    <img src="${escapeHtml(briLogo)}" alt="Bank Rakyat Indonesia">
                </div>
                <div class="psd-cover-copy">
                    <span class="psd-kicker is-light">Performance review</span>
                    <h1>Kinerja ${escapeHtml(this.scopeLabel())}</h1>
                    <h2>Funding, Pinjaman, Kualitas, dan Produktivitas</h2>
                    <p>${escapeHtml(this.period())}. Materi pendukung asistensi dengan pembacaan angka dari total menuju segmen, produk, cabang, dan penggerak bisnis.</p>
                    <div class="psd-cover-agenda">
                        <span><b>01</b><strong>Funding</strong><small>Total, segmen, produk</small></span>
                        <span><b>02</b><strong>Pinjaman</strong><small>SME, Konsumer, Mikro</small></span>
                        <span><b>03</b><strong>Kualitas</strong><small>SML dan NPL</small></span>
                        <span><b>04</b><strong>Produktivitas</strong><small>RM, Mantri, KTS</small></span>
                    </div>
                </div>
                <div class="psd-cover-metrics">
                    ${statCard({ label: 'Funding', value: funding.value || '-', meta: funding.trend ? `${funding.trend} tren` : 'Posisi terkini', tone: 'blue' })}
                    ${statCard({ label: 'Outstanding', value: os.value || '-', meta: os.trend ? `${os.trend} tren` : 'Posisi terkini', tone: 'cyan' })}
                    ${statCard({ label: 'SML', value: sml.value || '-', meta: sml.ratio || 'Rasio kualitas', tone: 'amber' })}
                    ${statCard({ label: 'NPL', value: npl.value || '-', meta: npl.ratio || 'Rasio kualitas', tone: 'red' })}
                </div>
                <div class="psd-cover-footer">
                    <strong>Area 6 Region 13</strong>
                    <span>Data diperbarui ${escapeHtml(this.data?.meta?.generated_at || this.period())}</span>
                </div>
            </div>
        `;
    }

    renderAgendaV2() {
        const image = sameOriginAsset(this.data?.assets?.branch_building, '/images/bri-area6-building.png');
        const funding = this.summaryCard('simpanan');
        const os = this.summaryCard('os');
        const sml = this.summaryCard('sml');
        const npl = this.summaryCard('npl');
        const groups = [
            {
                number: '01',
                title: 'Funding',
                slides: 'Slide 3-5',
                description: 'Summary cabang atau segmen, produk dana, timeseries, dan delapan strategi penguatan funding.',
                icon: 'fa-wallet',
                tone: 'blue',
            },
            {
                number: '02',
                title: 'Pinjaman',
                slides: 'Slide 6-9',
                description: 'Outstanding total, SME, Konsumer, dan Mikro dari posisi historis menuju RKA dan penggerak produktivitas.',
                icon: 'fa-hand-holding-usd',
                tone: 'cyan',
            },
            {
                number: '03',
                title: 'Kualitas',
                slides: 'Slide 10-11',
                description: 'SML dibaca sebagai early warning, lalu NPL sebagai prioritas recovery berdasarkan nominal dan rasio.',
                icon: 'fa-shield-alt',
                tone: 'amber',
            },
            {
                number: '04',
                title: 'Tren dan Aksi',
                slides: 'Slide 12-13',
                description: 'Timeseries terintegrasi menjadi dasar prioritas aksi berikutnya pada scope yang dipilih.',
                icon: 'fa-route',
                tone: 'green',
            },
        ];
        const narrative = `Deck terdiri dari 13 slide dan dibaca berurutan dari sumber dana, penyaluran, kualitas, hingga keputusan. Seluruh slide mengikuti scope ${this.scopeLabel()}.`;

        return `
            <div class="psd-slide psd-v2-agenda-slide">
                ${storyHeader({
                    kicker: 'Executive storyline',
                    title: 'Daftar Isi dan Alur Keputusan',
                    subtitle: 'Empat bab ringkas dengan urutan pembacaan yang konsisten dan dapat ditindaklanjuti.',
                    narrative,
                    period: this.period(),
                })}
                <main class="psd-v2-agenda-main">
                    <section class="psd-v2-agenda-list">
                        ${groups.map((group) => `
                            <article class="is-${escapeHtml(group.tone)}">
                                <span class="psd-v2-agenda-number">${group.number}</span>
                                <i class="fas ${escapeHtml(group.icon)}" aria-hidden="true"></i>
                                <div>
                                    <header><strong>${escapeHtml(group.title)}</strong><small>${escapeHtml(group.slides)}</small></header>
                                    <p>${escapeHtml(group.description)}</p>
                                </div>
                            </article>
                        `).join('')}
                    </section>
                    <aside class="psd-v2-agenda-visual" style="--psd-agenda-image:url('${escapeHtml(image)}')">
                        <div class="psd-v2-agenda-visual-copy">
                            <span>13 slide terstruktur</span>
                            <strong>Funding -> Pinjaman -> Kualitas -> Aksi</strong>
                            <p>Setiap bab menghubungkan posisi historis, delta, target RKA, distribusi portofolio, dan timeseries.</p>
                        </div>
                        <div class="psd-v2-agenda-pulse">
                            <span><small>Funding</small><b>${escapeHtml(funding.value || '-')}</b></span>
                            <span><small>Outstanding</small><b>${escapeHtml(os.value || '-')}</b></span>
                            <span><small>SML</small><b>${escapeHtml(sml.ratio || '-')}</b></span>
                            <span><small>NPL</small><b>${escapeHtml(npl.ratio || '-')}</b></span>
                        </div>
                    </aside>
                </main>
                ${insightStrip([
                    { label: 'Scope aktif', value: this.scopeLabel(), meta: 'Berlaku untuk seluruh deck' },
                    { label: 'Periode aktif', value: this.period(), meta: 'Tanggal terakhir yang dipilih' },
                    { label: 'Format analisis', value: 'Posisi + Delta + RKA', meta: 'Diperkuat distribusi dan tren' },
                ])}
            </div>
        `;
    }

    renderFundingSummaryV2() {
        const periods = this.comparisonPeriods();
        const comparison = this.comparisonScope();
        const funding = comparison.funding || {};
        const total = funding.total || {};
        const sourceRows = this.isArea()
            ? (funding.branches || []).map((branch) => ({
                scope: branch.scope_label,
                dimension: 'Total Simpanan',
                metric: branch.total,
            }))
            : (funding.segments || []).map((segment) => ({
                scope: this.scopeLabel(),
                dimension: segment.label,
                metric: segment,
            }));
        const rows = [
            {
                scope: this.scopeLabel(),
                dimension: this.isArea() ? 'TOTAL AREA' : 'TOTAL CABANG',
                metric: total,
                className: 'is-total',
            },
            ...sourceRows,
        ];
        const distributionRows = this.isArea()
            ? (funding.branches || []).map((branch) => ({
                label: branch.scope_label,
                metric: branch.total,
            }))
            : (funding.segments || []).map((segment) => ({
                label: segment.label,
                metric: segment,
            }));
        const series = this.timeseriesSeries('simpanan');
        const labels = this.timeseriesScope()?.labels || [];
        const leader = maxBy(distributionRows.map((row) => ({
            ...row,
            current: asNumber(row.metric?.current),
        })), 'current');
        const narrative = this.isArea()
            ? `Funding ${this.scopeLabel()} berada pada ${total.current_fmt || '-'}. Tabel membandingkan total Area dengan empat cabang, dilengkapi posisi historis, delta, dan RKA.`
            : `Funding ${this.scopeLabel()} berada pada ${total.current_fmt || '-'}. Karena scope cabang aktif, pembacaan dipecah menjadi Ritel, Wholesale, dan Mikro.`;

        return `
            <div class="psd-slide psd-v2-data-slide psd-v2-funding-summary">
                ${storyHeader({
                    kicker: '1. Funding | Summary',
                    title: `Summary Funding ${this.scopeLabel()}`,
                    subtitle: this.isArea()
                        ? 'Perbandingan total Area 6 dan cabang dengan posisi historis lengkap.'
                        : 'Perbandingan total cabang dan segmen Ritel, Wholesale, serta Mikro.',
                    narrative,
                    period: periods.current?.label || this.period(),
                    tone: 'blue',
                })}
                <main class="psd-v2-data-main">
                    <section class="psd-v2-table-panel">
                        ${panelTitle(
                            this.isArea() ? 'Funding Area dan Cabang' : 'Funding Cabang dan Segmen',
                            `${rows.length} baris analisis`,
                            'fa-table-list'
                        )}
                        ${comparisonTable({ periods, rows })}
                    </section>
                    <section class="psd-v2-support-split">
                        ${miniTrendChart({
                            title: 'Timeseries Total Funding',
                            labels,
                            series,
                            seriesKeys: ['simpanan'],
                            tone: 'blue',
                            note: `Tren ${this.scopeLabel()} sampai ${periods.current?.label || this.period()}.`,
                        })}
                        ${distributionPanel({
                            title: this.isArea() ? 'Kontribusi Funding per Cabang' : 'Komposisi Funding per Segmen',
                            rows: distributionRows,
                            total: asNumber(total.current),
                            tone: 'blue',
                        })}
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Posisi Funding', value: total.current_fmt || '-', meta: total.achievement_fmt ? `RKA ${total.achievement_fmt}` : 'Posisi terbaru' },
                    { label: 'Kontributor terbesar', value: leader?.label || '-', meta: leader?.metric?.current_fmt || '-' },
                    { label: 'Momentum MtD', value: total.deltas_fmt?.mtd || '-', meta: periods.mtd?.label || '-', tone: asNumber(total.deltas?.mtd) >= 0 ? 'green' : 'red' },
                ])}
            </div>
        `;
    }

    renderFundingProductsV2() {
        const periods = this.comparisonPeriods();
        const funding = this.comparisonScope()?.funding || {};
        const products = funding.products || [];
        const selected = findByKey(products, this.fundingProduct) || products[0] || {};
        if (selected.key) {
            this.fundingProduct = selected.key;
        }
        const rows = products.map((product) => ({
            scope: this.scopeLabel(),
            dimension: product.label,
            metric: product,
            className: product.key === selected.key ? 'is-selected' : '',
        }));
        const branchRows = this.isArea()
            ? (funding.branches || []).map((branch) => {
                const product = findByKey(branch.products, selected.key) || {};
                return { label: branch.scope_label, metric: product };
            })
            : products.map((product) => ({ label: product.label, metric: product }));
        const total = branchRows.reduce((sum, row) => sum + asNumber(row.metric?.current), 0);
        const labels = this.timeseriesScope()?.labels || [];
        const series = this.timeseriesSeries(selected.key || 'giro');
        const controls = `
            <div class="psd-segmented" aria-label="Produk funding">
                ${products.map((product) => `
                    <button type="button" data-psd-funding-product="${escapeHtml(product.key)}" class="${product.key === selected.key ? 'active' : ''}">
                        ${escapeHtml(product.label)}
                    </button>
                `).join('')}
            </div>
        `;
        const narrative = `${selected.label || 'Produk'} berada pada ${selected.current_fmt || '-'} atau ${formatPercent(percentOf(selected.current, funding.total?.current))} dari funding ${this.scopeLabel()}. Selector mengubah timeseries dan distribusi tanpa mengubah scope deck.`;

        return `
            <div class="psd-slide psd-v2-data-slide psd-v2-funding-products">
                ${storyHeader({
                    kicker: '1. Funding | Produk',
                    title: 'Summary Funding per Produk',
                    subtitle: 'Giro, Tabungan, dan Deposito dibandingkan pada tujuh posisi historis serta lima delta utama.',
                    narrative,
                    period: periods.current?.label || this.period(),
                    controls,
                })}
                <main class="psd-v2-data-main">
                    <section class="psd-v2-table-panel">
                        ${panelTitle('Matriks Produk Funding', `${products.length} produk`, 'fa-table-list')}
                        ${comparisonTable({ periods, rows })}
                    </section>
                    <section class="psd-v2-support-split">
                        ${miniTrendChart({
                            title: `Timeseries ${selected.label || 'Produk'}`,
                            labels,
                            series,
                            seriesKeys: [selected.key || 'giro'],
                            tone: selected.key === 'deposito' ? 'amber' : 'blue',
                            note: `Produk aktif: ${selected.label || '-'}. Nilai nominal ditampilkan langsung pada setiap titik.`,
                        })}
                        ${distributionPanel({
                            title: this.isArea()
                                ? `Distribusi ${selected.label || 'Produk'} per Cabang`
                                : 'Komposisi Produk Cabang',
                            rows: branchRows,
                            total,
                            tone: selected.key === 'deposito' ? 'amber' : 'blue',
                        })}
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Produk terpilih', value: selected.label || '-', meta: selected.current_fmt || '-' },
                    { label: 'Delta YoY', value: selected.deltas_fmt?.yoy || '-', meta: periods.yoy?.label || '-', tone: asNumber(selected.deltas?.yoy) >= 0 ? 'green' : 'red' },
                    { label: 'Delta MtD', value: selected.deltas_fmt?.mtd || '-', meta: periods.mtd?.label || '-', tone: asNumber(selected.deltas?.mtd) >= 0 ? 'green' : 'red' },
                ])}
            </div>
        `;
    }

    renderStrategiesV2() {
        const scope = findScope(this.data?.digital_strategy?.scopes, this.scopeKey()) || {};
        const digitalRows = Array.isArray(scope?.digital?.rows) ? scope.digital.rows : [];
        const casaRows = Array.isArray(scope?.casa_debitur?.rows) ? scope.casa_debitur.rows : [];
        const payrollRows = Array.isArray(scope?.payroll?.rows) ? scope.payroll.rows : [];
        const clusterRows = Array.isArray(scope?.business_cluster?.rows) ? scope.business_cluster.rows : [];
        const supportingRows = Array.isArray(scope?.supporting) ? scope.supporting : [];
        const dormant = scope?.dormant?.row || {};
        const activeCount = digitalRows.filter((row) => row?.positions?.current?.raw !== null
            && row?.positions?.current?.raw !== undefined).length;
        const topCasa = maxBy(casaRows, 'ratio');
        const topCluster = clusterRows[0] || {};
        const narrative = `${activeCount}/6 kanal digital memiliki posisi terbaru. CASA/OS tertinggi berada pada ${topCasa?.label || 'scope yang belum tersedia'} sebesar ${topCasa?.ratio_fmt || '-'}; payroll, cluster, dan dormant menjadi pengungkit berikutnya.`;
        const firstDigital = digitalRows[0] || {};
        const periodLabel = (path, fallback) => firstDigital?.positions?.[path]?.label || fallback;
        const compactMetric = (value) => String(value?.fmt || value || '-')
            .replace(/^([+-]?)Rp/i, '$1')
            .replace(/\s+(Jt|M|T)$/i, '$1')
            .replace(/\s+/g, ' ')
            .trim();
        const pointValue = (value, extraClass = '') => `
            <span class="psd-strategy-cell ${escapeHtml(extraClass)}" title="${escapeHtml(`${value?.fmt || '-'} | ${value?.label || '-'}`)}">
                <strong>${escapeHtml(compactMetric(value))}</strong>
            </span>
        `;
        const deltaValue = (value) => `
            <span class="psd-strategy-cell ${deltaClass(value?.raw)}" title="${escapeHtml(`${value?.fmt || '-'} | ${value?.label || '-'}`)}">
                <strong>${escapeHtml(compactMetric(value))}</strong>
            </span>
        `;
        const node = ({
            number,
            title,
            description,
            icon,
            side,
            className = '',
            meta = '',
            body,
        }) => `
            <article class="psd-strategy-orbit-node is-${escapeHtml(side)} ${escapeHtml(className)}">
                <span class="psd-strategy-node-number">${escapeHtml(number)}</span>
                <div class="psd-strategy-node-body">
                    <header>
                        <div>
                            <strong>${escapeHtml(title)}</strong>
                            <p>${escapeHtml(description)}</p>
                        </div>
                        ${meta ? `<small>${escapeHtml(meta)}</small>` : ''}
                    </header>
                    ${body}
                </div>
                <span class="psd-strategy-node-icon" aria-hidden="true">
                    <i class="fas ${escapeHtml(icon)}"></i>
                </span>
            </article>
        `;
        const digitalTable = `
            <div class="psd-strategy-mini-table-wrap">
                <table class="psd-strategy-mini-table is-digital">
                    <colgroup>
                        <col class="is-number"><col class="is-label">
                        <col><col><col><col><col><col class="is-rka">
                    </colgroup>
                    <thead>
                        <tr class="is-group-head">
                            <th rowspan="2">No</th>
                            <th rowspan="2">Kategori</th>
                            <th colspan="3">Posisi</th>
                            <th colspan="2">Delta</th>
                            <th rowspan="2">RKA</th>
                        </tr>
                        <tr class="is-date-head">
                            <th>YTD<small>${escapeHtml(periodLabel('ytd', '-'))}</small></th>
                            <th>MtD<small>${escapeHtml(periodLabel('mtd', '-'))}</small></th>
                            <th>Terakhir<small>${escapeHtml(periodLabel('current', scope?.period_label || '-'))}</small></th>
                            <th>YTD</th>
                            <th>MtD</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${digitalRows.length ? digitalRows.map((row, index) => `
                            <tr>
                                <td><b>${index + 1}</b></td>
                                <td>
                                    <strong>${escapeHtml(row.label || '-')}</strong>
                                    <small>${escapeHtml(row.metric_label || '')}</small>
                                </td>
                                <td>${pointValue(row.positions?.ytd)}</td>
                                <td>${pointValue(row.positions?.mtd)}</td>
                                <td>${pointValue(row.positions?.current, 'is-current')}</td>
                                <td>${deltaValue(row.deltas?.ytd)}</td>
                                <td>${deltaValue(row.deltas?.mtd)}</td>
                                <td title="${escapeHtml(row.rka?.fmt || '-')}"><strong>${escapeHtml(compactMetric(row.rka))}</strong></td>
                            </tr>
                        `).join('') : `
                            <tr><td colspan="8" class="is-empty">Data kanal digital sedang dimuat.</td></tr>
                        `}
                    </tbody>
                </table>
            </div>
        `;
        const casaTable = `
            <div class="psd-strategy-mini-table-wrap">
                <table class="psd-strategy-mini-table is-casa">
                    <thead>
                        <tr>
                            <th>${this.isArea() ? 'Cabang' : 'Segmen'}</th>
                            <th>OS</th>
                            <th>CASA</th>
                            <th>Rasio</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${casaRows.length ? casaRows.map((row) => `
                            <tr>
                                <td><strong>${escapeHtml(row.label || '-')}</strong></td>
                                <td>${escapeHtml(row.os_fmt || '-')}</td>
                                <td>${escapeHtml(row.casa_fmt || '-')}</td>
                                <td><strong>${escapeHtml(row.ratio_fmt || '-')}</strong></td>
                            </tr>
                        `).join('') : `
                            <tr><td colspan="4" class="is-empty">Snapshot CASA debitur belum tersedia.</td></tr>
                        `}
                    </tbody>
                </table>
            </div>
        `;
        const payrollMetric = (metric) => `
            <div class="psd-strategy-payroll-metric">
                <strong>${escapeHtml(metric?.positions?.current?.fmt || '-')}</strong>
                <small>P: ${escapeHtml(metric?.positions?.ytd?.fmt || '-')} / ${escapeHtml(metric?.positions?.mtd?.fmt || '-')}</small>
                <small>
                    <span class="${deltaClass(metric?.deltas?.ytd?.raw)}">D: ${escapeHtml(metric?.deltas?.ytd?.fmt || '-')}</span>
                    <span class="${deltaClass(metric?.deltas?.mtd?.raw)}">/ ${escapeHtml(metric?.deltas?.mtd?.fmt || '-')}</span>
                </small>
            </div>
        `;
        const payrollTable = `
            <div class="psd-strategy-mini-table-wrap">
                <table class="psd-strategy-mini-table is-payroll">
                    <thead>
                        <tr>
                            <th>${this.isArea() ? 'Cabang' : 'Scope'}</th>
                            <th>New Rekening</th>
                            <th>Saldo New</th>
                            <th>Payroll Berkualitas</th>
                            <th>RKA/Penc.</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${payrollRows.length ? payrollRows.map((row) => `
                            <tr>
                                <td><strong>${escapeHtml(row.label || '-')}</strong></td>
                                <td>${payrollMetric(row.rekening)}</td>
                                <td>${payrollMetric(row.saldo)}</td>
                                <td>${payrollMetric(row.kualitas)}</td>
                                <td>
                                    <strong>${escapeHtml(row.rekening?.rka?.fmt || '-')}</strong>
                                    <small>${escapeHtml(row.rekening?.achievement?.fmt || '-')}</small>
                                </td>
                            </tr>
                        `).join('') : `
                            <tr><td colspan="5" class="is-empty">Data payroll sedang dimuat.</td></tr>
                        `}
                    </tbody>
                </table>
            </div>
        `;
        const clusterTable = `
            <div class="psd-strategy-mini-table-wrap">
                <table class="psd-strategy-mini-table is-cluster">
                    <thead>
                        <tr>
                            <th>Cluster</th>
                            <th>Total</th>
                            <th>BRI</th>
                            <th>Potensi</th>
                            <th>Penetrasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${clusterRows.length ? clusterRows.map((row) => `
                            <tr>
                                <td><strong>${escapeHtml(row.category || '-')}</strong></td>
                                <td>${escapeHtml(row.total_fmt || '-')}</td>
                                <td>${escapeHtml(row.sudah_bri_fmt || '-')}</td>
                                <td>${escapeHtml(row.belum_bri_fmt || '-')}</td>
                                <td><strong>${escapeHtml(row.penetration_fmt || '-')}</strong></td>
                            </tr>
                        `).join('') : `
                            <tr><td colspan="5" class="is-empty">Data cluster belum tersedia pada cache sumber.</td></tr>
                        `}
                    </tbody>
                </table>
            </div>
        `;
        const dormantSummary = `
            <div class="psd-strategy-dormant-grid">
                <span><small>YTD</small><strong>${escapeHtml(dormant?.positions?.ytd?.fmt || '-')}</strong></span>
                <span><small>MtD</small><strong>${escapeHtml(dormant?.positions?.mtd?.fmt || '-')}</strong></span>
                <span class="is-current"><small>${escapeHtml(dormant?.positions?.current?.label || 'Terakhir')}</small><strong>${escapeHtml(dormant?.positions?.current?.fmt || '-')}</strong></span>
                <span class="${deltaClass(dormant?.deltas?.ytd?.raw)}"><small>Delta YTD</small><strong>${escapeHtml(dormant?.deltas?.ytd?.fmt || '-')}</strong></span>
                <span class="${deltaClass(dormant?.deltas?.mtd?.raw)}"><small>Delta MtD</small><strong>${escapeHtml(dormant?.deltas?.mtd?.fmt || '-')}</strong></span>
            </div>
        `;
        const supportRow = (number, fallbackTitle) => supportingRows.find((row) => String(row?.number) === String(number))
            || { number, title: fallbackTitle, position: '-', delta_ytd: '-', delta_mtd: '-', rka: '-' };
        const supportSummary = (row) => `
            <div class="psd-strategy-support-values">
                <span><small>Posisi</small><strong>${escapeHtml(row?.position || '-')}</strong></span>
                <span><small>Delta YTD</small><strong>${escapeHtml(row?.delta_ytd || '-')}</strong></span>
                <span><small>Delta MtD</small><strong>${escapeHtml(row?.delta_mtd || '-')}</strong></span>
                <span><small>RKA</small><strong>${escapeHtml(row?.rka || '-')}</strong></span>
            </div>
        `;
        const strategySix = supportRow(6, 'Kolaborasi Perusahaan Anak');
        const strategySeven = supportRow(7, 'Optimalisasi Nasabah Prioritas BOD / BOC');
        const strategyEight = supportRow(8, 'Penguatan Produk & Fungsi RM');
        const actionMessage = topCasa
            ? `Perkuat kanal digital, pertahankan CASA/OS ${topCasa.label || 'tertinggi'} ${topCasa.ratio_fmt || '-'}, dan tindak lanjuti potensi cluster serta rekening dormant.`
            : 'Perkuat kanal digital, payroll, bisnis cluster, dan reaktivasi rekening dormant berdasarkan posisi terakhir.';

        return `
            <div class="psd-slide psd-v2-strategy-slide">
                ${storyHeader({
                    kicker: '1. Funding | 8 Strategi',
                    title: 'Rangkuman 8 Strategi Funding',
                    subtitle: 'Delapan pengungkit funding disusun sebagai satu peta eksekusi yang ringkas, terukur, dan mudah dipresentasikan.',
                    narrative,
                    period: scope?.period_label || this.period(),
                    tone: 'green',
                })}
                <main class="psd-v2-strategy-orbit">
                    <section class="psd-v2-strategy-rail is-left">
                        ${node({
                            number: '1',
                            title: 'Optimalisasi Digital Channel',
                            description: 'EDC, QRIS, CASA Merchant, BRIMO, BRILINK, dan QLOLA.',
                            icon: 'fa-mobile-screen-button',
                            side: 'left',
                            className: 'is-digital',
                            meta: `${digitalRows.length} kanal`,
                            body: digitalTable,
                        })}
                        ${node({
                            number: '8',
                            title: strategyEight.title || 'Penguatan Produk & Fungsi RM',
                            description: 'Penguatan kapasitas, akuisisi, dan fungsi pengelolaan RM.',
                            icon: 'fa-users-gear',
                            side: 'left',
                            className: 'is-supporting',
                            body: supportSummary(strategyEight),
                        })}
                        ${node({
                            number: '7',
                            title: strategySeven.title || 'Optimalisasi Nasabah Prioritas BOD / BOC',
                            description: 'Aktivasi peluang nasabah prioritas wholesale dan komersial.',
                            icon: 'fa-user-tie',
                            side: 'left',
                            className: 'is-supporting',
                            body: supportSummary(strategySeven),
                        })}
                        ${node({
                            number: '6',
                            title: strategySix.title || 'Kolaborasi Perusahaan Anak',
                            description: 'Sinergi ekosistem untuk memperluas sumber dana dan transaksi.',
                            icon: 'fa-handshake',
                            side: 'left',
                            className: 'is-supporting',
                            body: supportSummary(strategySix),
                        })}
                    </section>
                    <section class="psd-v2-strategy-core" aria-label="Pusat strategi funding">
                        <div class="psd-strategy-core-ring">
                            <span>8</span>
                            <strong>Strategi</strong>
                            <small>Retail Funding</small>
                        </div>
                        <div class="psd-strategy-skyline" aria-hidden="true">
                            <i style="--h:36%"></i>
                            <i style="--h:52%"></i>
                            <i style="--h:76%"></i>
                            <i style="--h:94%"></i>
                            <i style="--h:67%"></i>
                            <i style="--h:45%"></i>
                        </div>
                        <div class="psd-strategy-core-copy">
                            <strong>${escapeHtml(this.scopeLabel())}</strong>
                            <span>Funding execution map</span>
                            <div>
                                <small><b>${activeCount}/6</b> kanal aktif</small>
                                <small><b>${escapeHtml(topCasa?.ratio_fmt || '-')}</b> CASA/OS tertinggi</small>
                                <small><b>${escapeHtml(formatInteger(scope?.business_cluster?.total || 0))}</b> potensi cluster</small>
                            </div>
                        </div>
                    </section>
                    <section class="psd-v2-strategy-rail is-right">
                        ${node({
                            number: '2',
                            title: 'Rekening Transaksi Debitur',
                            description: 'Rasio CASA terhadap OS untuk mengukur kedalaman transaksi.',
                            icon: 'fa-wallet',
                            side: 'right',
                            className: 'is-casa',
                            meta: scope?.casa_debitur?.period_label || '-',
                            body: casaTable,
                        })}
                        ${node({
                            number: '3',
                            title: 'Bisnis Cluster | Top 5',
                            description: 'Potensi terbesar yang dapat dikonversi menjadi bisnis BRI.',
                            icon: 'fa-chart-column',
                            side: 'right',
                            className: 'is-cluster',
                            meta: `${formatInteger(scope?.business_cluster?.total || 0)} potensi`,
                            body: clusterTable,
                        })}
                        ${node({
                            number: '4',
                            title: 'Peningkatan Payroll Berkualitas',
                            description: 'Rekening baru, saldo baru, kualitas payroll, serta RKA / Penc.',
                            icon: 'fa-money-check-dollar',
                            side: 'right',
                            className: 'is-payroll',
                            meta: scope?.payroll?.period_label || '-',
                            body: payrollTable,
                        })}
                        ${node({
                            number: '5',
                            title: 'Rekening Dormant',
                            description: 'Reaktivasi rekening tidak bertransaksi untuk memperkuat CASA.',
                            icon: 'fa-power-off',
                            side: 'right',
                            className: 'is-dormant',
                            meta: dormant?.positions?.current?.label || '-',
                            body: dormantSummary,
                        })}
                    </section>
                </main>
                <footer class="psd-strategy-actionbar">
                    <span><i class="fas fa-bullseye" aria-hidden="true"></i></span>
                    <div>
                        <strong>Fokus eksekusi funding</strong>
                        <p>${escapeHtml(actionMessage)}</p>
                    </div>
                    <dl>
                        <div><dt>Cluster utama</dt><dd>${escapeHtml(topCluster?.category || '-')}</dd></div>
                        <div><dt>CASA / OS</dt><dd>${escapeHtml(topCasa?.ratio_fmt || '-')}</dd></div>
                        <div><dt>Dormant MtD</dt><dd class="${deltaClass(dormant?.deltas?.mtd?.raw)}">${escapeHtml(dormant?.deltas?.mtd?.fmt || '-')}</dd></div>
                    </dl>
                </footer>
            </div>
        `;
    }

    renderLoanSummaryV2() {
        const periods = this.comparisonPeriods();
        const credit = this.comparisonScope()?.credit || {};
        const total = credit.total?.os || {};
        const rows = this.isArea()
            ? (credit.branches || []).flatMap((branch) => (branch.segments || []).map((segment) => ({
                scope: branch.scope_label,
                dimension: segment.label,
                metric: segment.os,
            })))
            : (credit.segments || []).map((segment) => ({
                scope: this.scopeLabel(),
                dimension: segment.label,
                metric: segment.os,
            }));
        const distributionRows = this.isArea()
            ? (credit.branches || []).map((branch) => ({
                label: branch.scope_label,
                metric: branch.total?.os,
            }))
            : (credit.segments || []).map((segment) => ({
                label: segment.label,
                metric: segment.os,
            }));
        const series = this.timeseriesSeries('os');
        const labels = this.timeseriesScope()?.labels || [];
        const narrative = `Outstanding ${this.scopeLabel()} sebesar ${total.current_fmt || '-'}. Matriks menampilkan SME, Konsumer, dan Mikro dengan posisi YoY hingga ${periods.current?.label || this.period()}, delta, serta RKA yang tersedia.`;

        return `
            <div class="psd-slide psd-v2-data-slide psd-v2-loan-summary">
                ${storyHeader({
                    kicker: '2. Pinjaman | Outstanding Summary',
                    title: `Outstanding Summary ${this.scopeLabel()}`,
                    subtitle: 'Cabang dan segmen disajikan dalam satu matriks historis untuk membaca skala, momentum, dan ruang terhadap RKA.',
                    narrative,
                    period: periods.current?.label || this.period(),
                    tone: 'cyan',
                })}
                <main class="psd-v2-data-main is-dense-table">
                    <section class="psd-v2-table-panel">
                        ${panelTitle('Outstanding per Cabang dan Segmen', `${rows.length} baris`, 'fa-table-list')}
                        ${comparisonTable({ periods, rows })}
                    </section>
                    <section class="psd-v2-support-split">
                        ${miniTrendChart({
                            title: 'Timeseries Total Outstanding',
                            labels,
                            series,
                            seriesKeys: ['os'],
                            tone: 'green',
                            note: `Pergerakan OS ${this.scopeLabel()} sampai posisi terakhir.`,
                        })}
                        ${distributionPanel({
                            title: this.isArea() ? 'Portofolio Pinjaman per Cabang' : 'Portofolio Pinjaman per Segmen',
                            rows: distributionRows,
                            total: asNumber(total.current),
                            tone: 'green',
                        })}
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Posisi OS', value: total.current_fmt || '-', meta: total.achievement_fmt ? `RKA ${total.achievement_fmt}` : 'Posisi terbaru' },
                    { label: 'Delta YtD', value: total.deltas_fmt?.ytd || '-', meta: periods.ytd?.label || '-', tone: asNumber(total.deltas?.ytd) >= 0 ? 'green' : 'red' },
                    { label: 'Gap RKA', value: total.gap_fmt || '-', meta: total.achievement_fmt || '-', tone: asNumber(total.gap) >= 0 ? 'green' : 'red' },
                ])}
            </div>
        `;
    }

    renderSegmentPerformanceV2(segmentKey) {
        const periods = this.comparisonPeriods();
        const credit = this.comparisonScope()?.credit || {};
        const segment = findByKey(credit.segments, segmentKey) || {};
        const productRows = segment.products || [];
        const rows = [
            {
                scope: this.scopeLabel(),
                dimension: `TOTAL ${String(segment.label || segmentKey).toUpperCase()}`,
                metric: segment.os,
                className: 'is-total',
            },
            ...productRows.map((product) => ({
                scope: this.scopeLabel(),
                dimension: product.label,
                metric: product.os,
            })),
        ];
        const productivityKey = segmentKey === 'sme' ? 'retail_sme' : 'retail_consumer';
        const productivity = this.productivityCategory(productivityKey);
        const quadrantHistory = productivity.quadrant_history || {};
        const timeseriesKey = segmentKey === 'sme' ? 'sme_os' : 'consumer_os';
        const labels = this.timeseriesScope()?.labels || [];
        const series = this.timeseriesSeries(timeseriesKey);
        const title = segmentKey === 'sme' ? 'Pinjaman SME' : 'Pinjaman Konsumer';
        const subtitle = segmentKey === 'sme'
            ? 'Kecil Non Cashcoll dan Cashcoll dibaca bersama perkembangan kuadran RM Ritel SME.'
            : 'Briguna dan KPR dibaca bersama perkembangan kuadran RM Ritel Konsumer.';
        const narrative = `${segment.label || title} berada pada ${segment.os?.current_fmt || '-'}. Pencapaian RKA ${segment.os?.achievement_fmt || '-'} dengan ${productRows.length} produk dan histori kuadran RM sejak Januari.`;

        return `
            <div class="psd-slide psd-v2-data-slide psd-v2-segment-slide">
                ${storyHeader({
                    kicker: `2. Pinjaman | ${segment.label || title}`,
                    title: `${title} ${this.scopeLabel()}`,
                    subtitle,
                    narrative,
                    period: periods.current?.label || this.period(),
                    tone: segmentKey === 'sme' ? 'blue' : 'cyan',
                })}
                <main class="psd-v2-data-main">
                    <section class="psd-v2-table-panel">
                        ${panelTitle(`Matriks ${segment.label || title} per Produk`, `${rows.length} baris`, 'fa-table-list')}
                        ${comparisonTable({ periods, rows })}
                    </section>
                    <section class="psd-v2-support-split is-equal">
                        ${miniTrendChart({
                            title: `Timeseries OS ${segment.label || ''}`,
                            labels,
                            series,
                            seriesKeys: [timeseriesKey],
                            tone: segmentKey === 'sme' ? 'blue' : 'cyan',
                            note: `Nominal OS ${segment.label || ''} ditampilkan pada tujuh bulan terakhir.`,
                        })}
                        ${quadrantHistoryPanel({
                            title: `Kuadran RM ${segment.label || ''} Jan - Terkini`,
                            history: quadrantHistory,
                        })}
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Posisi segmen', value: segment.os?.current_fmt || '-', meta: segment.os?.achievement_fmt ? `RKA ${segment.os.achievement_fmt}` : 'Posisi terbaru' },
                    { label: 'Delta MtD', value: segment.os?.deltas_fmt?.mtd || '-', meta: periods.mtd?.label || '-', tone: asNumber(segment.os?.deltas?.mtd) >= 0 ? 'green' : 'red' },
                    { label: 'RM aktif', value: formatInteger(productivity.total?.rm_count), meta: `${formatInteger(productivity.total?.realisasi_deb)} debitur` },
                ])}
            </div>
        `;
    }

    renderQualityV2(metricKey) {
        const periods = this.comparisonPeriods();
        const credit = this.comparisonScope()?.credit || {};
        const total = credit.total?.[metricKey] || {};
        const rows = this.isArea()
            ? (credit.branches || []).flatMap((branch) => (branch.segments || []).map((segment) => ({
                scope: branch.scope_label,
                dimension: segment.label,
                metric: segment[metricKey],
            })))
            : (credit.segments || []).map((segment) => ({
                scope: this.scopeLabel(),
                dimension: segment.label,
                metric: segment[metricKey],
            }));
        const ranking = [...rows]
            .sort((a, b) => asNumber(b.metric?.current) - asNumber(a.metric?.current))
            .slice(0, 5)
            .map((row) => ({
                label: `${row.scope} - ${row.dimension}`,
                metric: row.metric,
                meta: row.metric?.ratio_positions_fmt?.current
                    ? `Rasio ${row.metric.ratio_positions_fmt.current}`
                    : 'Nominal kualitas',
            }));
        const labels = this.timeseriesScope()?.labels || [];
        const series = this.timeseriesSeries(metricKey);
        const upper = metricKey.toUpperCase();
        const title = metricKey === 'sml' ? 'Kualitas SML' : 'Kualitas NPL';
        const narrative = `${upper} ${this.scopeLabel()} sebesar ${total.current_fmt || '-'} dengan rasio ${total.ratio_positions_fmt?.current || '-'}. Delta negatif dibaca sebagai perbaikan kualitas.`;

        return `
            <div class="psd-slide psd-v2-data-slide psd-v2-quality-slide is-${escapeHtml(metricKey)}">
                ${storyHeader({
                    kicker: `3. Kualitas | ${upper}`,
                    title: `${title} ${this.scopeLabel()}`,
                    subtitle: metricKey === 'sml'
                        ? 'Early warning dibaca dari nominal, rasio, pergeseran posisi, dan sumber tekanan per cabang serta segmen.'
                        : 'Prioritas recovery dibaca dari nominal, rasio, pergeseran posisi, dan konsentrasi eksposur.',
                    narrative,
                    period: periods.current?.label || this.period(),
                    tone: metricKey === 'sml' ? 'amber' : 'red',
                })}
                <main class="psd-v2-data-main is-dense-table">
                    <section class="psd-v2-table-panel">
                        ${panelTitle(`Matriks ${upper} per Cabang dan Segmen`, `${rows.length} baris`, 'fa-table-list')}
                        ${comparisonTable({ periods, rows, inverse: true })}
                    </section>
                    <section class="psd-v2-support-split">
                        ${miniTrendChart({
                            title: `Timeseries Nominal ${upper}`,
                            labels,
                            series,
                            seriesKeys: [metricKey],
                            tone: metricKey === 'sml' ? 'amber' : 'red',
                            note: `Penurunan nominal ${upper} merupakan perbaikan kualitas.`,
                        })}
                        ${distributionPanel({
                            title: `Eksposur ${upper} Terbesar`,
                            rows: ranking,
                            total: asNumber(total.current),
                            tone: metricKey === 'sml' ? 'amber' : 'red',
                        })}
                    </section>
                </main>
                ${insightStrip([
                    { label: `Posisi ${upper}`, value: total.current_fmt || '-', meta: total.ratio_positions_fmt?.current || 'Rasio terhadap OS' },
                    { label: 'Delta MtD', value: total.deltas_fmt?.mtd || '-', meta: periods.mtd?.label || '-', tone: asNumber(total.deltas?.mtd) <= 0 ? 'green' : 'red' },
                    { label: 'Tekanan terbesar', value: ranking[0]?.label || '-', meta: ranking[0]?.metric?.current_fmt || '-' },
                ])}
            </div>
        `;
    }

    renderPrioritiesV2() {
        const comparison = this.comparisonScope();
        const funding = comparison.funding?.total || {};
        const os = comparison.credit?.total?.os || {};
        const sml = comparison.credit?.total?.sml || {};
        const npl = comparison.credit?.total?.npl || {};
        const strategies = (this.data?.digital_strategy?.cards || []).slice(0, 8);
        const weakestStrategy = strategies
            .map((item) => ({
                ...item,
                trend_raw: Number(String(item.trend || '').replace(/[^0-9,-]/g, '').replace(',', '.')) || 0,
            }))
            .sort((a, b) => a.trend_raw - b.trend_raw)[0];
        const smeProductivity = this.productivityCategory('retail_sme');
        const consumerProductivity = this.productivityCategory('retail_consumer');
        const actions = [
            {
                number: '01',
                title: 'Funding',
                basis: `${funding.current_fmt || '-'} | Gap RKA ${funding.gap_fmt || '-'}`,
                action: asNumber(funding.gap) < 0
                    ? 'Tutup gap RKA melalui cabang atau segmen dengan kontraksi MtD terbesar.'
                    : 'Pertahankan buffer RKA dan optimalkan komposisi CASA.',
                tone: asNumber(funding.gap) >= 0 ? 'green' : 'red',
            },
            {
                number: '02',
                title: 'Pinjaman',
                basis: `${os.current_fmt || '-'} | Gap RKA ${os.gap_fmt || '-'}`,
                action: asNumber(os.gap) < 0
                    ? 'Akselerasi produk dengan pipeline sehat tanpa menambah tekanan kualitas.'
                    : 'Jaga pertumbuhan dan distribusi portofolio antar cabang.',
                tone: asNumber(os.gap) >= 0 ? 'green' : 'red',
            },
            {
                number: '03',
                title: 'SML',
                basis: `${sml.current_fmt || '-'} | ${sml.ratio_positions_fmt?.current || '-'}`,
                action: 'Lakukan curing eksposur terbesar sebelum bermigrasi menjadi NPL.',
                tone: 'amber',
            },
            {
                number: '04',
                title: 'NPL',
                basis: `${npl.current_fmt || '-'} | ${npl.ratio_positions_fmt?.current || '-'}`,
                action: 'Prioritaskan recovery berdasarkan kombinasi nominal, rasio, dan tren memburuk.',
                tone: 'red',
            },
            {
                number: '05',
                title: 'Produktivitas RM',
                basis: `${formatInteger(asNumber(smeProductivity.total?.rm_count) + asNumber(consumerProductivity.total?.rm_count))} RM Ritel`,
                action: 'Coaching diarahkan pada RM kuadran 4 dengan basis kelolaan besar dan realisasi rendah.',
                tone: 'blue',
            },
        ];
        const narrative = `Lima agenda manajemen merangkum temuan funding, pinjaman, kualitas, dan produktivitas pada ${this.scopeLabel()}. Prioritas digital terlemah berada pada ${weakestStrategy?.title || 'indikator yang belum tersedia'}.`;

        return `
            <div class="psd-slide psd-v2-priority-slide">
                ${storyHeader({
                    kicker: '5. Executive closing',
                    title: 'Prioritas Aksi Berikutnya',
                    subtitle: 'Agenda manajemen disusun dari gap bisnis, tekanan kualitas, produktivitas, dan indikator strategi.',
                    narrative,
                    period: this.period(),
                    tone: 'green',
                })}
                <main class="psd-v2-priority-main">
                    <section class="psd-v2-action-board">
                        ${panelTitle('Agenda Manajemen', this.scopeLabel(), 'fa-list-check')}
                        <div class="psd-v2-action-list">
                            ${actions.map((action) => `
                                <article class="is-${escapeHtml(action.tone)}">
                                    <span>${action.number}</span>
                                    <div>
                                        <header><strong>${escapeHtml(action.title)}</strong><small>${escapeHtml(action.basis)}</small></header>
                                        <p>${escapeHtml(action.action)}</p>
                                    </div>
                                </article>
                            `).join('')}
                        </div>
                    </section>
                    <section class="psd-v2-priority-signals">
                        ${panelTitle('Sinyal 8 Strategi Pendukung', `${strategies.length} sumber aktif`, 'fa-bullseye')}
                        <div class="psd-v2-signal-grid">
                            ${strategies.map((strategy, index) => `
                                <article>
                                    <span>${String(index + 1).padStart(2, '0')}</span>
                                    <div>
                                        <small>${escapeHtml(strategy.title || '-')}</small>
                                        <strong>${escapeHtml(strategy.current_value || '-')}</strong>
                                    </div>
                                    <b class="${String(strategy.trend || '').includes('-') ? 'is-negative' : 'is-positive'}">${escapeHtml(strategy.trend || '-')}</b>
                                </article>
                            `).join('')}
                        </div>
                        <div class="psd-v2-closing-statement">
                            <strong>Urutan keputusan yang konsisten mempercepat tindak lanjut.</strong>
                            <span>Funding -> Pinjaman -> SML -> NPL -> Produktivitas -> Aksi</span>
                        </div>
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Scope keputusan', value: this.scopeLabel(), meta: `Posisi ${this.period()}` },
                    { label: 'Perlu intervensi', value: weakestStrategy?.title || '-', meta: weakestStrategy?.trend || 'Belum tersedia', tone: 'red' },
                    { label: 'Fokus eksekusi', value: 'Growth berkualitas', meta: 'Tutup gap tanpa menambah risiko', tone: 'green' },
                ])}
            </div>
        `;
    }

    renderRoadmap() {
        const os = this.summaryCard('os');
        const funding = this.summaryCard('simpanan');
        const sml = this.summaryCard('sml');
        const npl = this.summaryCard('npl');
        const steps = [
            { number: '01', title: 'Funding', meta: 'Total -> segmen -> produk', slides: 'Slide 3-5', tone: 'blue' },
            { number: '02', title: 'Pinjaman', meta: 'Total -> SME -> Konsumer -> Mikro', slides: 'Slide 6-9', tone: 'cyan' },
            { number: '03', title: 'Kualitas', meta: 'SML lebih dahulu, kemudian NPL', slides: 'Slide 10-11', tone: 'amber' },
            { number: '04', title: 'Penggerak', meta: 'RM Ritel -> RM Mikro -> Mantri -> KTS', slides: 'Slide 12-16', tone: 'green' },
        ];
        const narrative = `Alur disusun dari ukuran bisnis menuju kualitas dan produktivitas. Setiap bagian mempertahankan scope ${this.scopeLabel()} agar perbandingan tetap konsisten.`;

        return `
            <div class="psd-slide psd-roadmap-slide">
                ${storyHeader({
                    kicker: 'Executive storyline',
                    title: 'Alur Pembahasan yang Terstruktur',
                    subtitle: 'Satu alur keputusan: pahami sumber dana, penyaluran, tekanan kualitas, lalu pemilik aksi.',
                    narrative,
                    period: this.period(),
                })}
                <main class="psd-roadmap-main">
                    <div class="psd-roadmap-flow">
                        ${steps.map((step, index) => `
                            <article class="psd-roadmap-step is-${step.tone}">
                                <span class="psd-roadmap-number">${step.number}</span>
                                <div>
                                    <h3>${escapeHtml(step.title)}</h3>
                                    <p>${escapeHtml(step.meta)}</p>
                                </div>
                                <small>${escapeHtml(step.slides)}</small>
                                ${index < steps.length - 1 ? '<i class="fas fa-arrow-right" aria-hidden="true"></i>' : ''}
                            </article>
                        `).join('')}
                    </div>
                    <aside class="psd-pulse">
                        ${panelTitle('Executive Pulse', this.scopeLabel(), 'fa-gauge-high')}
                        <div class="psd-pulse-grid">
                            ${statCard({ label: 'Funding', value: funding.value || '-', meta: funding.trend || 'Posisi', tone: 'blue' })}
                            ${statCard({ label: 'OS', value: os.value || '-', meta: os.trend || 'Posisi', tone: 'cyan' })}
                            ${statCard({ label: 'Rasio SML', value: sml.ratio || '-', meta: sml.value || 'Nominal', tone: 'amber', percent: true })}
                            ${statCard({ label: 'Rasio NPL', value: npl.ratio || '-', meta: npl.value || 'Nominal', tone: 'red', percent: true })}
                        </div>
                        <div class="psd-decision-note">
                            <i class="fas fa-bullseye" aria-hidden="true"></i>
                            <div>
                                <strong>Tujuan akhir</strong>
                                <p>Menentukan cabang, segmen, produk, dan RM yang perlu dipertahankan, diakselerasi, atau dipulihkan.</p>
                            </div>
                        </div>
                    </aside>
                </main>
                ${insightStrip([
                    { label: 'Cakupan', value: this.scopeLabel(), meta: 'Selector global mengubah seluruh deck' },
                    { label: 'Posisi', value: this.period(), meta: 'Periode pembacaan utama' },
                    { label: 'Urutan', value: 'Dana -> Kredit -> Kualitas', meta: 'Produktivitas menjadi penutup aksi' },
                ])}
            </div>
        `;
    }

    renderFundingTotal() {
        const structure = this.fundingScope();
        const metric = this.summaryMetric('simpanan');
        const branches = structure.branches || [];
        const total = asNumber(structure.total_raw);
        const leader = maxBy(branches, 'total_raw');
        const comparisonRows = this.isArea()
            ? branches
            : [...(structure.segments || []), ...(structure.products || [])];
        const maximum = Math.max(1, ...comparisonRows.map((row) => asNumber(row.total_raw ?? row.value_raw)));
        const columns = this.isArea()
            ? [
                { label: 'Cabang', key: 'scope_label', width: '1.25fr', render: (row) => `<strong>${escapeHtml(compactName(row.scope_label))}</strong>` },
                { label: 'Funding', key: 'total', width: '1fr', align: 'right', render: (row) => progressCell(row.total_raw, maximum, row.total || formatCurrency(row.total_raw), 'blue') },
                { label: 'Kontribusi', key: 'share', width: '0.7fr', align: 'right', render: (row) => `<strong>${escapeHtml(formatPercent(percentOf(row.total_raw, total)))}</strong>` },
            ]
            : [
                { label: 'Komponen', key: 'label', width: '1.2fr', render: (row) => `<strong>${escapeHtml(row.label)}</strong>` },
                { label: 'Nominal', key: 'value', width: '1fr', align: 'right', render: (row) => progressCell(row.value_raw, maximum, row.value || formatCurrency(row.value_raw), 'blue') },
                { label: 'Porsi', key: 'share', width: '0.7fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.share || formatPercent(row.share_raw))}</strong>` },
            ];
        const trendNarrative = metric.mtd >= 0
            ? `Funding ${this.scopeLabel()} berada di ${structure.total || formatCurrency(total)} dan bertumbuh ${formatCurrency(metric.mtd)} selama MtD.`
            : `Funding ${this.scopeLabel()} berada di ${structure.total || formatCurrency(total)} dengan kontraksi ${formatCurrency(metric.mtd)} selama MtD.`;

        return `
            <div class="psd-slide">
                ${storyHeader({
                    kicker: '1. Funding | Total',
                    title: `Funding ${this.scopeLabel()}`,
                    subtitle: this.isArea()
                        ? 'Posisi total Area 6, momentum terhadap titik pembanding, dan kontribusi setiap cabang.'
                        : 'Posisi total cabang, momentum terhadap titik pembanding, serta pembentuk funding per segmen dan produk.',
                    narrative: `${trendNarrative} Pencapaian RKA ${metric.achievement_fmt || '-'}.`,
                    period: structure.period_label || this.period(),
                    tone: metric.mtd < 0 ? 'amber' : 'green',
                })}
                <main class="psd-total-layout">
                    <section class="psd-total-summary">
                        ${panelTitle('Ringkasan Funding', 'Posisi dan momentum', 'fa-piggy-bank')}
                        <div class="psd-hero-number">
                            <span>Total Funding</span>
                            <strong>${escapeHtml(structure.total || formatCurrency(total))}</strong>
                            <small>${escapeHtml(this.scopeLabel())}</small>
                        </div>
                        <div class="psd-delta-grid">
                            ${statCard({ label: 'YtD', value: formatCurrency(metric.ytd), meta: 'vs 31 Des', tone: metric.ytd >= 0 ? 'green' : 'red' })}
                            ${statCard({ label: 'MtM', value: formatCurrency(metric.mtm), meta: 'vs bulan lalu', tone: metric.mtm >= 0 ? 'green' : 'red' })}
                            ${statCard({ label: 'MtD', value: formatCurrency(metric.mtd), meta: 'vs akhir bulan', tone: metric.mtd >= 0 ? 'green' : 'red' })}
                            ${statCard({ label: 'Penc. RKA', value: metric.achievement_fmt || '-', meta: metric.rka_fmt || 'RKA belum tersedia', tone: metric.achievement >= 100 ? 'green' : 'amber', percent: true })}
                        </div>
                    </section>
                    <section class="psd-analysis-column">
                        <section class="psd-panel psd-table-panel">
                            ${panelTitle(this.isArea() ? 'Kontribusi per Cabang' : 'Pembentuk Funding Cabang', `${comparisonRows.length} baris aktif`, 'fa-table')}
                            ${matrixTable({ columns, rows: comparisonRows })}
                        </section>
                        ${this.renderCompactTrend(['simpanan'], 'Trend Funding', 'Enam posisi bulanan terakhir')}
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Kontributor utama', value: leader?.scope_label || findByKey(structure.products, 'tabungan')?.label || '-', meta: leader ? `${formatPercent(percentOf(leader.total_raw, total))} dari funding scope` : 'Pembentuk funding terbesar' },
                    { label: 'Gap RKA', value: metric.gap_fmt || '-', meta: asNumber(metric.gap) >= 0 ? 'Posisi di atas target' : 'Perlu akselerasi funding' , tone: asNumber(metric.gap) >= 0 ? 'green' : 'red' },
                    { label: 'Momentum MtD', value: formatCurrency(metric.mtd), meta: asNumber(metric.mtd) >= 0 ? 'Pertumbuhan selama bulan berjalan' : 'Kontraksi selama bulan berjalan', tone: asNumber(metric.mtd) >= 0 ? 'green' : 'red' },
                ])}
            </div>
        `;
    }

    renderFundingBreakdown(type) {
        const structure = this.fundingScope();
        const isSegment = type === 'segments';
        const items = structure[type] || [];
        const branches = structure.branches || [];
        const total = asNumber(structure.total_raw);
        const labels = isSegment
            ? { kicker: '1. Funding | Segmen', title: 'Funding per Segmen', subtitle: 'Ritel, Wholesale, dan Mikro dibaca sebagai sumber struktur dana.' }
            : { kicker: '1. Funding | Produk', title: 'Funding per Produk', subtitle: 'Giro, Tabungan, dan Deposito menunjukkan kualitas mix dan biaya dana.' };
        const leader = maxBy(items, 'value_raw');
        const branchRows = branches.map((branch) => {
            const values = {};
            items.forEach((item) => {
                const branchItem = findByKey(branch[type], item.key) || {};
                values[item.key] = branchItem;
            });
            return { ...branch, values };
        });
        const columns = [
            { label: this.isArea() ? 'Cabang' : 'Komponen', key: 'scope_label', width: '1.15fr', render: (row) => `<strong>${escapeHtml(row.scope_label || row.label)}</strong>` },
            ...items.map((item) => ({
                label: item.label,
                key: item.key,
                width: '1fr',
                align: 'right',
                render: (row) => {
                    const value = row.values?.[item.key] || row;
                    return `<strong>${escapeHtml(value.value || formatCurrency(value.value_raw))}</strong><small>${escapeHtml(value.share || formatPercent(value.share_raw))}</small>`;
                },
            })),
            { label: 'Total', key: 'total', width: '1fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.total || structure.total)}</strong>` },
        ];
        const rows = this.isArea() ? branchRows : items.map((item) => ({ ...item, scope_label: item.label, total: item.value }));
        const narrative = leader
            ? `${leader.label} menjadi komponen terbesar sebesar ${leader.value || formatCurrency(leader.value_raw)}, setara ${leader.share || formatPercent(leader.share_raw)} dari funding ${this.scopeLabel()}.`
            : `Struktur ${isSegment ? 'segmen' : 'produk'} sedang menunggu snapshot terbaru untuk ${this.scopeLabel()}.`;

        return `
            <div class="psd-slide">
                ${storyHeader({
                    ...labels,
                    narrative,
                    period: structure.period_label || this.period(),
                    tone: 'blue',
                })}
                <main class="psd-breakdown-layout">
                    <section class="psd-component-board" style="--psd-components:${Math.max(1, items.length)}">
                        ${items.length ? items.map((item) => `
                            <article class="psd-component-card is-${escapeHtml(item.key)}">
                                <div class="psd-component-icon"><i class="fas ${iconFor(item.key)}" aria-hidden="true"></i></div>
                                <span>${escapeHtml(item.label)}</span>
                                <strong>${escapeHtml(item.value || formatCurrency(item.value_raw))}</strong>
                                <div class="psd-share-bar"><i style="width:${Math.min(100, asNumber(item.share_raw))}%"></i></div>
                                <small>${escapeHtml(item.share || formatPercent(item.share_raw))} dari total</small>
                            </article>
                        `).join('') : `
                            <div class="psd-empty is-large">
                                <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
                                <span>Snapshot struktur ${isSegment ? 'segmen' : 'produk'} sedang diperbarui. Total funding tetap tersedia.</span>
                            </div>
                        `}
                    </section>
                    <section class="psd-analysis-column">
                        <section class="psd-panel psd-table-panel">
                            ${panelTitle(this.isArea() ? `Matriks ${isSegment ? 'Segmen' : 'Produk'} per Cabang` : `Komposisi ${this.scopeLabel()}`, `${rows.length} baris aktual`, 'fa-table-columns')}
                            ${matrixTable({
                                columns,
                                rows,
                                emptyMessage: `Belum ada rincian ${isSegment ? 'segmen' : 'produk'} pada snapshot terpilih.`,
                                className: 'is-compact',
                            })}
                        </section>
                        ${this.renderCompactTrend(
                            isSegment ? ['simpanan', 'casa_ratio'] : ['giro', 'tabungan', 'deposito'],
                            isSegment ? 'Arah Funding dan CASA' : 'Trend Produk Funding',
                            'Enam posisi bulanan terakhir',
                        )}
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Total Funding', value: structure.total || formatCurrency(total), meta: this.scopeLabel() },
                    { label: 'Komponen dominan', value: leader?.label || '-', meta: leader ? `${leader.share || formatPercent(leader.share_raw)} dari total` : 'Menunggu struktur' },
                    { label: 'Arah pengelolaan', value: isSegment ? 'Perkuat basis stabil' : 'Jaga CASA', meta: isSegment ? 'Seimbangkan sumber dana antar segmen' : 'Giro dan tabungan menekan biaya dana' },
                ])}
            </div>
        `;
    }

    renderLoanTotal() {
        const credit = this.creditScope();
        const total = credit.total || {};
        const metric = this.summaryMetric('os');
        const branches = credit.branches || [];
        const oldBranches = this.branches();
        const comparisonRows = this.isArea() ? branches : credit.segments || [];
        const maximum = Math.max(1, ...comparisonRows.map((row) => asNumber(row.total?.os_raw ?? row.os_raw)));
        const columns = this.isArea()
            ? [
                { label: 'Cabang', key: 'scope_label', width: '1.1fr', render: (row) => `<strong>${escapeHtml(row.scope_label)}</strong>` },
                { label: 'OS', key: 'os', width: '1.2fr', align: 'right', render: (row) => progressCell(row.total?.os_raw, maximum, row.total?.os || formatCurrency(row.total?.os_raw), 'cyan') },
                { label: 'SML', key: 'sml', width: '0.9fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.total?.sml || '-')}</strong><small>${escapeHtml(row.total?.sml_ratio || '-')}</small>` },
                { label: 'NPL', key: 'npl', width: '0.9fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.total?.npl || '-')}</strong><small>${escapeHtml(row.total?.npl_ratio || '-')}</small>` },
            ]
            : [
                { label: 'Segmen', key: 'label', width: '1.1fr', render: (row) => `<strong>${escapeHtml(row.label)}</strong>` },
                { label: 'OS', key: 'os', width: '1.2fr', align: 'right', render: (row) => progressCell(row.os_raw, maximum, row.os || formatCurrency(row.os_raw), 'cyan') },
                { label: 'SML', key: 'sml', width: '0.9fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.sml || '-')}</strong><small>${escapeHtml(row.sml_ratio || '-')}</small>` },
                { label: 'NPL', key: 'npl', width: '0.9fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.npl || '-')}</strong><small>${escapeHtml(row.npl_ratio || '-')}</small>` },
            ];
        const leader = this.isArea()
            ? maxBy(oldBranches, 'pinjaman')
            : maxBy(credit.segments, 'os_raw');
        const healthy = Math.max(0, 100 - asNumber(total.sml_ratio_raw) - asNumber(total.npl_ratio_raw));

        return `
            <div class="psd-slide">
                ${storyHeader({
                    kicker: '2. Pinjaman | Total',
                    title: `Pinjaman ${this.scopeLabel()}`,
                    subtitle: this.isArea()
                        ? 'Outstanding total, pencapaian RKA, kontribusi cabang, dan profil kualitas portofolio.'
                        : 'Outstanding cabang, pencapaian RKA, serta distribusi SME, Konsumer, dan Mikro.',
                    narrative: `OS mencapai ${total.os || formatCurrency(total.os_raw)} dengan pencapaian RKA ${metric.achievement_fmt || '-'}. Portofolio lancar sekitar ${formatPercent(healthy)}.`,
                    period: credit.period_label || this.period(),
                    tone: asNumber(metric.gap) >= 0 ? 'green' : 'amber',
                })}
                <main class="psd-loan-total-layout">
                    <section class="psd-total-summary">
                        ${panelTitle('Skala dan Kualitas', this.scopeLabel(), 'fa-chart-pie')}
                        <div class="psd-hero-number">
                            <span>Total Outstanding</span>
                            <strong>${escapeHtml(total.os || formatCurrency(total.os_raw))}</strong>
                            <small>RKA ${escapeHtml(metric.rka_fmt || '-')}</small>
                        </div>
                        <div class="psd-quality-bar" aria-label="Komposisi kolektibilitas">
                            <i class="is-healthy" style="width:${healthy}%"></i>
                            <i class="is-sml" style="width:${Math.max(0, asNumber(total.sml_ratio_raw))}%"></i>
                            <i class="is-npl" style="width:${Math.max(0, asNumber(total.npl_ratio_raw))}%"></i>
                        </div>
                        <div class="psd-quality-legend">
                            <span><i class="is-healthy"></i>Lancar <strong>${formatPercent(healthy)}</strong></span>
                            <span><i class="is-sml"></i>SML <strong>${escapeHtml(total.sml_ratio || '-')}</strong></span>
                            <span><i class="is-npl"></i>NPL <strong>${escapeHtml(total.npl_ratio || '-')}</strong></span>
                        </div>
                        <div class="psd-delta-grid">
                            ${statCard({ label: 'YtD', value: formatCurrency(metric.ytd), meta: 'Pertumbuhan OS', tone: metric.ytd >= 0 ? 'green' : 'red' })}
                            ${statCard({ label: 'MtM', value: formatCurrency(metric.mtm), meta: 'Momentum bulanan', tone: metric.mtm >= 0 ? 'green' : 'red' })}
                            ${statCard({ label: 'MtD', value: formatCurrency(metric.mtd), meta: 'Bulan berjalan', tone: metric.mtd >= 0 ? 'green' : 'red' })}
                            ${statCard({ label: 'Penc. RKA', value: metric.achievement_fmt || '-', meta: metric.gap_fmt || 'Gap RKA', tone: metric.achievement >= 100 ? 'green' : 'amber', percent: true })}
                        </div>
                    </section>
                    <section class="psd-analysis-column">
                        <section class="psd-panel psd-table-panel">
                            ${panelTitle(this.isArea() ? 'Skala Cabang dan Kualitas' : 'Komposisi Pinjaman per Segmen', `${comparisonRows.length} baris aktual`, 'fa-table')}
                            ${matrixTable({ columns, rows: comparisonRows })}
                        </section>
                        ${this.renderCompactTrend(['os', 'sml_ratio', 'npl_ratio'], 'Trend Skala dan Kualitas', 'OS, rasio SML, dan rasio NPL')}
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Kontributor OS', value: leader?.name || leader?.label || '-', meta: this.isArea() ? (leader?.pinjaman_fmt || '-') : (leader?.os || '-') },
                    { label: 'Gap terhadap RKA', value: metric.gap_fmt || '-', meta: asNumber(metric.gap) >= 0 ? 'OS di atas target' : 'Akselerasi masih dibutuhkan', tone: asNumber(metric.gap) >= 0 ? 'green' : 'red' },
                    { label: 'Eksposur at risk', value: formatCurrency(asNumber(total.sml_raw) + asNumber(total.npl_raw)), meta: `${total.sml_ratio || '-'} SML | ${total.npl_ratio || '-'} NPL`, tone: 'amber' },
                ])}
            </div>
        `;
    }

    renderMicroHighlight() {
        const credit = this.creditScope();
        const segment = findByKey(credit.segments, 'micro') || {
            key: 'micro',
            label: 'Mikro',
            products: [],
        };
        const products = (segment.products || []).filter((product) => asNumber(product.os_raw) > 0);
        const osMetric = this.summaryMetric('micro_os');
        const activeScope = String(this.scopeLabel()).trim().toUpperCase();
        const matchesScope = (row) => this.isArea()
            || String(row?.cabang || row?.branch_office || row?.label || row?.unit || '')
                .trim()
                .toUpperCase() === activeScope;
        const pdwkRows = (this.data?.micro?.pdwk?.branches || [])
            .filter(matchesScope)
            .slice(0, 4);
        const extremeLowRows = (this.data?.micro?.extreme_low_mantri?.rows || [])
            .filter(matchesScope)
            .slice(0, 4);
        const rmKurTieringRows = (this.data?.micro?.rm_kur_tiering?.rows || [])
            .filter(matchesScope)
            .slice(0, 4);
        const showRmKur = this.microProductivityView === 'rm_kur';
        const roleFor = (row, roleKey) => (row?.roles || [])
            .find((role) => role.key === roleKey) || {};
        const decisionCell = (role) => `
            <div class="psd-micro-value-pair">
                <strong>${escapeHtml(role?.total_os_fmt || formatCurrency(role?.total_os))}</strong>
                <small>${formatInteger(role?.total_deb)} debitur</small>
            </div>
        `;
        const tieringCell = (tier) => `
            <div class="psd-micro-value-pair">
                <strong>${escapeHtml(tier?.os_fmt || formatCurrency(tier?.os))}</strong>
                <small>${formatInteger(tier?.deb)} debitur</small>
            </div>
        `;
        const mantriCategoryCell = (category) => `
            <div class="psd-micro-value-pair">
                <strong>${formatInteger(category?.deb)} mantri</strong>
                <small>${escapeHtml(category?.pct_fmt || formatPercent(category?.pct))}</small>
            </div>
        `;
        const totalCategorizedMantri = extremeLowRows
            .reduce((total, row) => total + asNumber(row?.total_mantri), 0);
        const microViewControls = `
            <div class="psd-segmented is-wide" role="group" aria-label="Tampilan kinerja Mikro">
                <button
                    type="button"
                    data-psd-micro-view="extreme_low"
                    class="${showRmKur ? '' : 'is-active'}"
                    aria-pressed="${showRmKur ? 'false' : 'true'}"
                >Kategori Mantri</button>
                <button
                    type="button"
                    data-psd-micro-view="rm_kur"
                    class="${showRmKur ? 'is-active' : ''}"
                    aria-pressed="${showRmKur ? 'true' : 'false'}"
                >RM KUR per Tiering</button>
            </div>
        `;
        const smlLeader = maxBy(products, 'sml_raw');
        const nplLeader = maxBy(products, 'npl_raw');
        const largestProduct = maxBy(products, 'os_raw');
        const totalRisk = asNumber(segment.sml_raw) + asNumber(segment.npl_raw);
        const scopeTitle = this.isArea() ? 'Area 6' : this.scopeLabel();
        const microSourcePeriod = this.data?.micro?.period_label || credit.period_label || this.period();
        const narrative = `${scopeTitle}: OS Mikro ${segment.os || formatCurrency(segment.os_raw)} dengan pencapaian RKA ${osMetric.achievement_fmt || '-'}. SML ${segment.sml || formatCurrency(segment.sml_raw)} dan NPL ${segment.npl || formatCurrency(segment.npl_raw)} menjadi fokus kualitas.`;
        const trendLine = (label, value, raw, inverse = false) => `
            <span>
                <small>${escapeHtml(label)}</small>
                <strong class="${deltaClass(raw, inverse)}">${escapeHtml(value)}</strong>
            </span>
        `;
        const qualityLine = (label, value, meta, tone = '') => `
            <span>
                <small>${escapeHtml(label)}</small>
                <strong class="${escapeHtml(tone)}">${escapeHtml(value)}</strong>
                <em>${escapeHtml(meta)}</em>
            </span>
        `;

        return `
            <div class="psd-slide psd-micro-highlight-slide">
                ${storyHeader({
                    kicker: '2. Pinjaman | Executive Micro Highlight',
                    title: `Highlight Kinerja Mikro ${scopeTitle}`,
                    subtitle: `OS dan kualitas posisi deck; PDWK, kategori Mantri Extreme Low sampai High, serta tiering RM KUR memakai data Mikro siap terbaru (${microSourcePeriod}).`,
                    narrative,
                    period: credit.period_label || this.period(),
                    tone: 'blue',
                    controls: microViewControls,
                })}
                <main class="psd-micro-highlight-layout">
                    <section class="psd-micro-highlight-top">
                        <div class="psd-micro-kpi-panel">
                            ${panelTitle('Ringkasan Tiga KPI Utama', 'OS, SML, dan NPL', 'fa-chart-column')}
                            <div class="psd-micro-kpi-grid">
                                <article class="psd-micro-kpi is-os">
                                    <header><i class="fas fa-chart-column" aria-hidden="true"></i><span>1. Outstanding (OS)</span></header>
                                    <strong>${escapeHtml(segment.os || formatCurrency(segment.os_raw))}</strong>
                                    <div class="psd-micro-kpi-target">
                                        <span>RKA <b>${escapeHtml(osMetric.rka_fmt || '-')}</b></span>
                                        <span>Pencapaian <b>${escapeHtml(osMetric.achievement_fmt || '-')}</b></span>
                                    </div>
                                    <div class="psd-micro-kpi-lines">
                                        ${trendLine('YtD', formatCurrency(osMetric.ytd), osMetric.ytd)}
                                        ${trendLine('MtM', formatCurrency(osMetric.mtm), osMetric.mtm)}
                                        ${trendLine('MtD', formatCurrency(osMetric.mtd), osMetric.mtd)}
                                    </div>
                                </article>
                                <article class="psd-micro-kpi is-sml">
                                    <header><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><span>2. Special Mention Loan</span></header>
                                    <strong>${escapeHtml(segment.sml || formatCurrency(segment.sml_raw))}</strong>
                                    <div class="psd-micro-kpi-target">
                                        <span>Rasio terhadap OS <b>${escapeHtml(segment.sml_ratio || formatPercent(segment.sml_ratio_raw))}</b></span>
                                    </div>
                                    <div class="psd-micro-kpi-lines">
                                        ${qualityLine('Kontributor', smlLeader?.label || '-', smlLeader?.sml || '-')}
                                        ${qualityLine('Porsi SML', formatPercent(percentOf(smlLeader?.sml_raw, segment.sml_raw)), 'dari SML Mikro', 'is-negative')}
                                        ${qualityLine('At risk', formatCurrency(totalRisk), 'SML + NPL', 'is-negative')}
                                    </div>
                                </article>
                                <article class="psd-micro-kpi is-npl">
                                    <header><i class="fas fa-shield-halved" aria-hidden="true"></i><span>3. Non-Performing Loan</span></header>
                                    <strong>${escapeHtml(segment.npl || formatCurrency(segment.npl_raw))}</strong>
                                    <div class="psd-micro-kpi-target">
                                        <span>Rasio terhadap OS <b>${escapeHtml(segment.npl_ratio || formatPercent(segment.npl_ratio_raw))}</b></span>
                                    </div>
                                    <div class="psd-micro-kpi-lines">
                                        ${qualityLine('Kontributor', nplLeader?.label || '-', nplLeader?.npl || '-')}
                                        ${qualityLine('Porsi NPL', formatPercent(percentOf(nplLeader?.npl_raw, segment.npl_raw)), 'dari NPL Mikro', 'is-negative')}
                                        ${qualityLine('Produk terbesar', largestProduct?.label || '-', largestProduct?.os || '-')}
                                    </div>
                                </article>
                            </div>
                        </div>
                        <section class="psd-panel psd-micro-product-panel">
                            ${panelTitle('Kinerja per Produk Kredit', `${products.length} produk aktif`, 'fa-table-list')}
                            <div class="psd-micro-product-table">
                                <div class="psd-micro-product-head">
                                    <span>Produk</span><span>Posisi OS</span><span>Porsi</span><span>Posisi SML</span><span>Rasio</span><span>Posisi NPL</span><span>Rasio</span>
                                </div>
                                ${products.map((product) => `
                                    <div class="psd-micro-product-row">
                                        <strong><i class="fas ${iconFor(product.key)}" aria-hidden="true"></i>${escapeHtml(product.label)}</strong>
                                        <b>${escapeHtml(product.os || formatCurrency(product.os_raw))}</b>
                                        <span>${formatPercent(percentOf(product.os_raw, segment.os_raw))}</span>
                                        <b>${escapeHtml(product.sml || formatCurrency(product.sml_raw))}</b>
                                        <span class="${asNumber(product.sml_ratio_raw) >= 10 ? 'is-risk' : ''}">${escapeHtml(product.sml_ratio || formatPercent(product.sml_ratio_raw))}</span>
                                        <b>${escapeHtml(product.npl || formatCurrency(product.npl_raw))}</b>
                                        <span class="${asNumber(product.npl_ratio_raw) >= 3 ? 'is-risk' : ''}">${escapeHtml(product.npl_ratio || formatPercent(product.npl_ratio_raw))}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </section>
                    </section>
                    <section class="psd-micro-support-grid">
                        <section class="psd-panel psd-micro-list-panel psd-micro-table-panel">
                            ${panelTitle('Rekap PDWK per Cabang', `${pdwkRows.length} cabang`, 'fa-user-check')}
                            ${pdwkRows.length ? `
                                <div class="psd-micro-branch-table is-pdwk" style="--psd-micro-row-count:${pdwkRows.length}">
                                    <div class="psd-micro-branch-head">
                                        <span>Cabang</span>
                                        <span>Putusan KA Unit</span>
                                        <span>Putusan MBM</span>
                                        <span>Putusan BOH</span>
                                        <span>Total Realisasi</span>
                                    </div>
                                    ${pdwkRows.map((row) => `
                                        <div class="psd-micro-branch-row">
                                            <strong>${escapeHtml(row.label || row.key || '-')}</strong>
                                            ${decisionCell(roleFor(row, 'kaunit'))}
                                            ${decisionCell(roleFor(row, 'mbm'))}
                                            ${decisionCell(roleFor(row, 'boh'))}
                                            ${tieringCell(row.total)}
                                        </div>
                                    `).join('')}
                                </div>
                            ` : '<div class="psd-empty">Data PDWK per cabang belum tersedia.</div>'}
                        </section>
                        <section class="psd-panel psd-micro-list-panel psd-micro-table-panel">
                            ${panelTitle(
                                showRmKur ? 'RM Mikro KUR per Tiering dan Cabang' : 'Kategori Mantri per Cabang',
                                showRmKur
                                    ? `${rmKurTieringRows.length} cabang`
                                    : `${extremeLowRows.length} cabang · ${formatInteger(totalCategorizedMantri)} mantri`,
                                showRmKur ? 'fa-user-tie' : 'fa-chart-simple'
                            )}
                            ${showRmKur ? `
                                ${rmKurTieringRows.length ? `
                                    <div class="psd-micro-branch-table is-tiering" style="--psd-micro-row-count:${rmKurTieringRows.length}">
                                        <div class="psd-micro-branch-head">
                                            <span>Cabang</span>
                                            <span>Tier &lt; Rp250 Jt</span>
                                            <span>Tier &gt; Rp250 Jt</span>
                                            <span>Total</span>
                                        </div>
                                        ${rmKurTieringRows.map((row) => `
                                            <div class="psd-micro-branch-row">
                                                <strong>${escapeHtml(row.cabang || row.key || '-')}</strong>
                                                ${tieringCell(row.lt_250)}
                                                ${tieringCell(row.gt_250)}
                                                ${tieringCell(row.total)}
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : '<div class="psd-empty">Tiering RM KUR belum tersedia pada scope ini.</div>'}
                            ` : `
                                ${extremeLowRows.length ? `
                                    <div class="psd-micro-branch-table is-extreme-low" style="--psd-micro-row-count:${extremeLowRows.length}">
                                        <div class="psd-micro-branch-head">
                                            <span>Cabang</span>
                                            <span>Extreme Low</span>
                                            <span>Low</span>
                                            <span>Total &le; 800 Jt</span>
                                            <span>Mid</span>
                                            <span>High</span>
                                        </div>
                                        ${extremeLowRows.map((row) => `
                                            <div class="psd-micro-branch-row">
                                                <strong>${escapeHtml(row.branch_office || row.cabang || '-')}</strong>
                                                ${mantriCategoryCell(row.extreme_low)}
                                                ${mantriCategoryCell(row.low)}
                                                ${mantriCategoryCell(row.under_800)}
                                                ${mantriCategoryCell(row.mid)}
                                                ${mantriCategoryCell(row.high)}
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : '<div class="psd-empty">Rekap Extreme Low belum tersedia pada scope ini.</div>'}
                            `}
                        </section>
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Pencapaian RKA', value: osMetric.achievement_fmt || '-', meta: osMetric.gap_fmt || 'Gap RKA', tone: asNumber(osMetric.achievement) >= 100 ? 'green' : 'red' },
                    { label: 'Produk dominan', value: largestProduct?.label || '-', meta: largestProduct?.os || '-' },
                    { label: 'Fokus kualitas', value: `SML ${segment.sml_ratio || '-'}`, meta: `NPL ${segment.npl_ratio || '-'}`, tone: 'amber' },
                ])}
            </div>
        `;
    }

    renderLoanSegment(segmentKey) {
        if (segmentKey === 'micro') {
            return this.renderMicroHighlight();
        }

        const credit = this.creditScope();
        const segment = findByKey(credit.segments, segmentKey) || {
            key: segmentKey,
            label: segmentKey === 'sme' ? 'SME' : segmentKey === 'consumer' ? 'Konsumer' : 'Mikro',
            products: [],
        };
        const productRows = segment.products || [];
        const branchRows = (credit.branches || []).map((branch) => {
            const branchSegment = findByKey(branch.segments, segmentKey) || {};
            return { ...branchSegment, scope_label: branch.scope_label };
        }).filter((row) => asNumber(row.os_raw) !== 0 || row.scope_label);
        const maximum = Math.max(1, ...productRows.map((row) => asNumber(row.os_raw)), ...branchRows.map((row) => asNumber(row.os_raw)));
        const names = {
            sme: {
                kicker: '2. Pinjaman | SME',
                title: 'Pinjaman SME',
                subtitle: 'Kecil Non Cashcoll dan Cashcoll dibaca bersama untuk melihat skala, risiko, dan kontribusi cabang.',
            },
            consumer: {
                kicker: '2. Pinjaman | Konsumer',
                title: 'Pinjaman Konsumer',
                subtitle: 'Briguna dan KPR menunjukkan struktur portofolio Konsumer dan kualitas per cabang.',
            },
            micro: {
                kicker: '2. Pinjaman | Mikro',
                title: 'Pinjaman Mikro',
                subtitle: 'Briguna Mikro, Kupedes, KUR Mikro, KUR Kecil, dan KUR KPP ditampilkan tanpa baris kosong.',
            },
        }[segmentKey];
        const productLeader = maxBy(productRows, 'os_raw');
        const branchLeader = maxBy(branchRows, 'os_raw');
        const columns = [
            { label: this.isArea() ? 'Cabang' : 'Produk', key: 'label', width: '1.25fr', render: (row) => `<strong>${escapeHtml(row.scope_label || row.label)}</strong>` },
            { label: 'OS', key: 'os', width: '1.25fr', align: 'right', render: (row) => progressCell(row.os_raw, maximum, row.os || formatCurrency(row.os_raw), 'cyan') },
            { label: 'SML', key: 'sml', width: '1fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.sml || formatCurrency(row.sml_raw))}</strong><small>${escapeHtml(row.sml_ratio || formatPercent(row.sml_ratio_raw))}</small>` },
            { label: 'NPL', key: 'npl', width: '1fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.npl || formatCurrency(row.npl_raw))}</strong><small>${escapeHtml(row.npl_ratio || formatPercent(row.npl_ratio_raw))}</small>` },
        ];
        const matrixRows = this.isArea() && branchRows.some((row) => asNumber(row.os_raw) !== 0)
            ? branchRows
            : productRows;
        const narrative = productLeader
            ? `${segment.label} mencapai ${segment.os || formatCurrency(segment.os_raw)}. ${productLeader.label} menjadi produk terbesar ${productLeader.os || formatCurrency(productLeader.os_raw)}.`
            : `${segment.label} mencapai ${segment.os || formatCurrency(segment.os_raw)}; rincian produk sedang mengikuti snapshot struktur terbaru.`;

        return `
            <div class="psd-slide">
                ${storyHeader({
                    ...names,
                    narrative,
                    period: credit.period_label || this.period(),
                    tone: 'blue',
                })}
                <main class="psd-segment-layout">
                    <section class="psd-segment-summary">
                        ${panelTitle(`Struktur ${segment.label}`, `${productRows.length} produk aktif`, 'fa-layer-group')}
                        <div class="psd-segment-hero">
                            <span>Total OS ${escapeHtml(segment.label)}</span>
                            <strong>${escapeHtml(segment.os || formatCurrency(segment.os_raw))}</strong>
                            <div>
                                <small>SML <b>${escapeHtml(segment.sml || '-')}</b> ${escapeHtml(segment.sml_ratio || '-')}</small>
                                <small>NPL <b>${escapeHtml(segment.npl || '-')}</b> ${escapeHtml(segment.npl_ratio || '-')}</small>
                            </div>
                        </div>
                        <div class="psd-product-stack ${productRows.length <= 2 ? 'is-card-grid' : ''}" style="--psd-product-count:${Math.max(1, productRows.length)}">
                            ${productRows.length ? productRows.map((product) => {
                                const share = percentOf(product.os_raw, segment.os_raw);
                                const productBranches = (credit.branches || []).map((branch) => {
                                    const branchSegment = findByKey(branch.segments, segmentKey) || {};
                                    const branchProduct = findByKey(branchSegment.products, product.key) || {};

                                    return {
                                        label: branch.scope_label,
                                        value: branchProduct.os || formatCurrency(branchProduct.os_raw),
                                        valueRaw: asNumber(branchProduct.os_raw),
                                    };
                                }).filter((row) => row.valueRaw > 0);
                                const branchMaximum = Math.max(1, ...productBranches.map((row) => row.valueRaw));

                                return `
                                <article>
                                    <i class="fas ${iconFor(product.key)}" aria-hidden="true"></i>
                                    <div class="psd-product-main">
                                        <span>${escapeHtml(product.label)}</span>
                                        <strong>${escapeHtml(product.os || formatCurrency(product.os_raw))}</strong>
                                        <div class="psd-product-share">
                                            <i style="width:${Math.max(0, Math.min(100, share))}%"></i>
                                        </div>
                                        <small>${formatPercent(share)} dari OS segmen</small>
                                    </div>
                                    <div class="psd-product-quality">
                                        <span><small>SML</small><b>${escapeHtml(product.sml || '-')}</b><em>${escapeHtml(product.sml_ratio || '-')}</em></span>
                                        <span><small>NPL</small><b>${escapeHtml(product.npl || '-')}</b><em>${escapeHtml(product.npl_ratio || '-')}</em></span>
                                    </div>
                                    ${this.isArea() && productRows.length <= 2 && productBranches.length ? `
                                        <div class="psd-product-branches">
                                            <strong>Distribusi per cabang</strong>
                                            ${productBranches.map((branch) => `
                                                <span>
                                                    <b>${escapeHtml(branch.label)}</b>
                                                    <i><em style="width:${Math.max(2, Math.min(100, percentOf(branch.valueRaw, branchMaximum)))}%"></em></i>
                                                    <small>${escapeHtml(branch.value)}</small>
                                                </span>
                                            `).join('')}
                                        </div>
                                    ` : ''}
                                </article>
                            `;
                            }).join('') : `
                                <div class="psd-empty">
                                    <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
                                    <span>Rincian produk sedang diperbarui; total segmen tetap tersedia.</span>
                                </div>
                            `}
                        </div>
                    </section>
                    <section class="psd-analysis-column">
                        <section class="psd-panel psd-table-panel">
                            ${panelTitle(this.isArea() && branchRows.length ? `${segment.label} per Cabang` : `Detail Produk ${segment.label}`, `${matrixRows.length} baris aktual`, 'fa-table')}
                            ${matrixTable({
                                columns,
                                rows: matrixRows,
                                emptyMessage: `Belum ada rincian ${segment.label} pada periode terpilih.`,
                            })}
                        </section>
                        ${this.renderCompactTrend([
                            segmentKey === 'sme' ? 'sme_os' : segmentKey === 'consumer' ? 'consumer_os' : 'micro_os',
                        ], `Trend OS ${segment.label}`, 'Enam posisi bulanan terakhir')}
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Produk terbesar', value: productLeader?.label || '-', meta: productLeader?.os || 'Menunggu rincian produk' },
                    { label: this.isArea() ? 'Cabang terbesar' : 'Rasio SML', value: this.isArea() ? (branchLeader?.scope_label || '-') : (segment.sml_ratio || '-'), meta: this.isArea() ? (branchLeader?.os || '-') : (segment.sml || '-') },
                    { label: 'Fokus kualitas', value: `SML ${segment.sml_ratio || '-'}`, meta: `NPL ${segment.npl_ratio || '-'}`, tone: 'amber' },
                ])}
            </div>
        `;
    }

    renderQuality(metricKey) {
        const credit = this.creditScope();
        const total = credit.total || {};
        const isSml = metricKey === 'sml';
        const title = isSml ? 'Kualitas SML' : 'Kualitas NPL';
        const ratioKey = `${metricKey}_ratio_raw`;
        const formattedRatioKey = `${metricKey}_ratio`;
        const nominalKey = `${metricKey}_raw`;
        const formattedKey = metricKey;
        const metric = this.summaryMetric(metricKey);
        const segments = credit.segments || [];
        const products = segments.flatMap((segment) => (segment.products || []).map((product) => ({
            ...product,
            segment_label: segment.label,
        }))).sort((a, b) => asNumber(b[nominalKey]) - asNumber(a[nominalKey])).slice(0, 7);
        const branches = (credit.branches || []).map((branch) => ({
            ...(branch.total || {}),
            scope_label: branch.scope_label,
        })).sort((a, b) => asNumber(b[nominalKey]) - asNumber(a[nominalKey]));
        const maximum = Math.max(1, ...branches.map((row) => asNumber(row[nominalKey])));
        const leader = maxBy(branches, nominalKey);
        const narrative = `${title} ${this.scopeLabel()} sebesar ${total[formattedKey] || formatCurrency(total[nominalKey])} atau ${total[formattedRatioKey] || formatPercent(total[ratioKey])}. ${isSml ? 'SML menjadi early warning sebelum memburuk ke NPL.' : 'NPL menjadi fokus recovery dan penyelesaian eksposur.'}`;

        const branchColumns = [
            { label: this.isArea() ? 'Cabang' : 'Segmen', key: 'scope_label', width: '1.2fr', render: (row) => `<strong>${escapeHtml(row.scope_label || row.label)}</strong>` },
            { label: 'Nominal', key: formattedKey, width: '1.25fr', align: 'right', render: (row) => progressCell(row[nominalKey], maximum, row[formattedKey] || formatCurrency(row[nominalKey]), isSml ? 'amber' : 'red') },
            { label: 'Rasio', key: formattedRatioKey, width: '0.75fr', align: 'right', render: (row) => `<strong class="${asNumber(row[ratioKey]) > (isSml ? 10 : 3) ? 'psd-risk-text' : ''}">${escapeHtml(row[formattedRatioKey] || formatPercent(row[ratioKey]))}</strong>` },
        ];
        const rankingRows = this.isArea() && branches.length ? branches : segments.map((segment) => ({
            ...segment,
            scope_label: segment.label,
        }));

        return `
            <div class="psd-slide">
                ${storyHeader({
                    kicker: `3. Kualitas | ${isSml ? 'SML' : 'NPL'}`,
                    title,
                    subtitle: isSml
                        ? 'Dibaca lebih dahulu sebagai peringatan dini, lalu ditelusuri dari segmen ke produk dan cabang.'
                        : 'Dibaca setelah SML sebagai prioritas recovery, lalu ditelusuri dari segmen ke produk dan cabang.',
                    narrative,
                    period: credit.period_label || this.period(),
                    tone: 'red',
                })}
                <main class="psd-quality-layout">
                    <section class="psd-quality-overview">
                        ${panelTitle(title, this.scopeLabel(), isSml ? 'fa-triangle-exclamation' : 'fa-shield-halved')}
                        <div class="psd-quality-hero is-${metricKey}">
                            <span>Total ${metricKey.toUpperCase()}</span>
                            <strong>${escapeHtml(total[formattedKey] || formatCurrency(total[nominalKey]))}</strong>
                            <b>${escapeHtml(total[formattedRatioKey] || formatPercent(total[ratioKey]))} dari OS</b>
                        </div>
                        <div class="psd-segment-risk-grid" style="--psd-risk-count:${Math.max(1, segments.length)}">
                            ${segments.map((segment) => `
                                <article>
                                    <span>${escapeHtml(segment.label)}</span>
                                    <strong>${escapeHtml(segment[formattedKey] || formatCurrency(segment[nominalKey]))}</strong>
                                    <small>${escapeHtml(segment[formattedRatioKey] || formatPercent(segment[ratioKey]))} dari OS segmen</small>
                                </article>
                            `).join('')}
                        </div>
                        <div class="psd-quality-deltas">
                            <span>YtD <strong class="${deltaClass(metric.ytd, true)}">${escapeHtml(formatCurrency(metric.ytd))}</strong></span>
                            <span>MtM <strong class="${deltaClass(metric.mtm, true)}">${escapeHtml(formatCurrency(metric.mtm))}</strong></span>
                            <span>MtD <strong class="${deltaClass(metric.mtd, true)}">${escapeHtml(formatCurrency(metric.mtd))}</strong></span>
                        </div>
                    </section>
                    <section class="psd-analysis-column">
                        <section class="psd-panel psd-table-panel">
                            ${panelTitle(this.isArea() ? `Ranking ${metricKey.toUpperCase()} Cabang` : `${metricKey.toUpperCase()} per Segmen`, `${rankingRows.length} baris aktual`, 'fa-ranking-star')}
                            ${matrixTable({ columns: branchColumns, rows: rankingRows })}
                        </section>
                        ${this.renderCompactTrend([metricKey, `${metricKey}_ratio`], `Trend ${metricKey.toUpperCase()}`, 'Nominal dan rasio enam bulan terakhir', true)}
                    </section>
                    <section class="psd-panel psd-product-risk">
                        ${panelTitle(`Produk dengan ${metricKey.toUpperCase()} Terbesar`, `${products.length} produk berisi`, 'fa-list-ol')}
                        <div class="psd-risk-list" style="--psd-risk-rows:${Math.max(1, products.length)}">
                            ${products.length ? products.map((product, index) => `
                                <div>
                                    <b>${index + 1}</b>
                                    <span><strong>${escapeHtml(product.label)}</strong><small>${escapeHtml(product.segment_label)}</small></span>
                                    <em>${escapeHtml(product[formattedKey] || formatCurrency(product[nominalKey]))}<small>${escapeHtml(product[formattedRatioKey] || formatPercent(product[ratioKey]))}</small></em>
                                </div>
                            `).join('') : `
                                <div class="psd-empty">
                                    <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
                                    <span>Rincian kualitas produk sedang diperbarui.</span>
                                </div>
                            `}
                        </div>
                    </section>
                </main>
                ${insightStrip([
                    { label: `${metricKey.toUpperCase()} tertinggi`, value: leader?.scope_label || '-', meta: leader ? `${leader[formattedKey] || formatCurrency(leader[nominalKey])} | ${leader[formattedRatioKey] || formatPercent(leader[ratioKey])}` : 'Scope cabang tunggal' , tone: 'red' },
                    { label: 'Arah yang sehat', value: 'Nominal dan rasio turun', meta: 'Penurunan kualitas diberi indikator hijau', tone: 'green' },
                    { label: 'Tindakan', value: isSml ? 'Curing sebelum jatuh tempo' : 'Recovery terukur', meta: isSml ? 'Prioritaskan eksposur SML terbesar' : 'Prioritaskan NPL nominal dan rasio tinggi', tone: 'amber' },
                ])}
            </div>
        `;
    }

    productivityScope() {
        return findScope(this.data?.productivity?.scopes, this.scopeKey()) || {};
    }

    renderProductivity(categoryKey) {
        const scope = this.productivityScope();
        const category = scope?.categories?.[categoryKey] || {};
        const total = category.total || {};
        const rows = (category.rows || []).slice(0, 8);
        const label = categoryKey === 'retail_consumer' ? 'RM Ritel Konsumer' : 'RM Ritel SME';
        const leader = rows[0] || null;
        const maximum = Math.max(1, ...rows.map((row) => asNumber(row.realisasi_os)));
        const columns = [
            { label: '#', key: 'rank', width: '0.25fr', render: (_row, index) => String(index + 1) },
            { label: 'RM / Unit', key: 'name', width: '1.8fr', render: (row) => `<strong>${escapeHtml(row.name)}</strong><small>${escapeHtml(row.unit || row.branch || '-')}</small>` },
            { label: 'Debitur', key: 'realisasi_deb', width: '0.55fr', align: 'right', render: (row) => `<strong>${formatInteger(row.realisasi_deb)}</strong>` },
            { label: 'Realisasi OS', key: 'realisasi_os', width: '1.2fr', align: 'right', render: (row) => progressCell(row.realisasi_os, maximum, row.realisasi_os_fmt || formatCurrency(row.realisasi_os), 'cyan') },
            { label: 'Avg. Ticket', key: 'average_ticket', width: '0.9fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.average_ticket_fmt || formatCurrency(row.average_ticket))}</strong>` },
            { label: 'LAR', key: 'lar_pct', width: '0.7fr', align: 'right', render: (row) => `<strong class="${asNumber(row.lar_pct) > 15 ? 'psd-risk-text' : ''}">${escapeHtml(row.lar_pct_fmt || formatPercent(row.lar_pct))}</strong>` },
        ];

        return `
            <div class="psd-slide">
                ${storyHeader({
                    kicker: '4. Produktivitas | RM Ritel',
                    title: label,
                    subtitle: 'Produktivitas dibaca dari jumlah RM, debitur, realisasi OS, average ticket, portofolio kelolaan, dan LAR.',
                    narrative: `${label} ${this.scopeLabel()} mencatat ${formatInteger(total.rm_count)} RM dan realisasi ${total.realisasi_os_fmt || formatCurrency(total.realisasi_os)}. Kontributor terbesar ${leader?.name || '-'}.`,
                    period: scope.period_label || this.data?.productivity?.period_label || this.period(),
                    tone: 'green',
                })}
                <main class="psd-productivity-layout">
                    <section class="psd-productivity-summary">
                        ${panelTitle('Ringkasan Produktivitas', this.scopeLabel(), 'fa-users-gear')}
                        <div class="psd-productivity-kpis">
                            ${statCard({ label: 'Jumlah RM', value: formatInteger(total.rm_count), meta: 'RM aktif', tone: 'blue' })}
                            ${statCard({ label: 'Realisasi OS', value: total.realisasi_os_fmt || formatCurrency(total.realisasi_os), meta: `${formatInteger(total.realisasi_deb)} debitur`, tone: 'green' })}
                            ${statCard({ label: 'Rata-rata / RM', value: total.average_per_rm_fmt || formatCurrency(total.average_per_rm), meta: 'Produktivitas nominal', tone: 'cyan' })}
                            ${statCard({ label: 'Average Ticket', value: total.average_ticket_fmt || formatCurrency(total.average_ticket), meta: 'Per debitur', tone: 'blue' })}
                            ${statCard({ label: 'Rasio LAR', value: total.lar_pct_fmt || formatPercent(total.lar_pct), meta: total.lar_fmt || 'Eksposur LAR', tone: asNumber(total.lar_pct) > 15 ? 'red' : 'amber', percent: true })}
                        </div>
                        <div class="psd-productivity-readout">
                            <i class="fas fa-lightbulb" aria-hidden="true"></i>
                            <p>Produktivitas tinggi perlu dijaga bersama kualitas. RM dengan realisasi besar dan LAR tinggi menjadi prioritas pendampingan, bukan hanya peringkat volume.</p>
                        </div>
                    </section>
                    <section class="psd-panel psd-table-panel">
                        ${panelTitle('Ranking RM', `${rows.length} RM teratas`, 'fa-ranking-star')}
                        ${matrixTable({ columns, rows })}
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Kontributor terbesar', value: leader?.name || '-', meta: leader?.realisasi_os_fmt || '-' },
                    { label: 'Rata-rata per RM', value: total.average_per_rm_fmt || formatCurrency(total.average_per_rm), meta: `${formatInteger(total.rm_count)} RM aktif` },
                    { label: 'Kualitas portofolio', value: total.lar_pct_fmt || formatPercent(total.lar_pct), meta: total.lar_fmt || 'LAR kelolaan', tone: asNumber(total.lar_pct) > 15 ? 'red' : 'amber' },
                ])}
            </div>
        `;
    }

    renderRmMicro() {
        const source = this.data?.micro?.rm_kur_micro || {};
        const total = source.total || {};
        const rows = (source.rows || []).filter((row) => asNumber(row.realisasi_os) !== 0 || asNumber(row.realisasi_deb) !== 0).slice(0, 9);
        const maximum = Math.max(1, ...rows.map((row) => asNumber(row.realisasi_os)));
        const columns = [
            { label: '#', key: 'rank', width: '0.25fr', render: (_row, index) => String(index + 1) },
            { label: 'RM Mikro KUR', key: 'nama', width: '1.7fr', render: (row) => `<strong>${escapeHtml(row.nama || row.name)}</strong><small>${escapeHtml(row.unit || row.cabang || '-')}</small>` },
            { label: 'Total Kelolaan', key: 'total_os', width: '1fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.total_os_fmt || formatCurrency(row.total_os))}</strong><small>${formatInteger(row.total_deb)} deb</small>` },
            { label: 'Realisasi', key: 'realisasi_os', width: '1.25fr', align: 'right', render: (row) => progressCell(row.realisasi_os, maximum, row.realisasi_os_fmt || formatCurrency(row.realisasi_os), 'green') },
            { label: 'Debitur', key: 'realisasi_deb', width: '0.55fr', align: 'right', render: (row) => `<strong>${formatInteger(row.realisasi_deb)}</strong>` },
        ];
        const leader = rows[0] || null;

        return `
            <div class="psd-slide">
                ${storyHeader({
                    kicker: '4. Produktivitas | RM Mikro',
                    title: 'Produktivitas RM Mikro KUR',
                    subtitle: 'RM Mikro KUR dibaca dari portofolio kelolaan, realisasi debitur, dan nominal pencairan pada scope aktif.',
                    narrative: `${this.scopeLabel()} mencatat realisasi ${total.realisasi_os_fmt || formatCurrency(total.realisasi_os)} dari ${formatInteger(total.realisasi_deb)} debitur. RM teratas ${leader?.nama || leader?.name || '-'}.`,
                    period: this.data?.micro?.period_label || this.period(),
                    tone: 'green',
                })}
                <main class="psd-productivity-layout">
                    <section class="psd-productivity-summary">
                        ${panelTitle('Ringkasan RM Mikro KUR', this.scopeLabel(), 'fa-store')}
                        <div class="psd-productivity-kpis is-two-column">
                            ${statCard({ label: 'Portofolio Kelolaan', value: total.total_os_fmt || formatCurrency(total.total_os), meta: `${formatInteger(total.total_deb)} debitur`, tone: 'blue' })}
                            ${statCard({ label: 'Realisasi OS', value: total.realisasi_os_fmt || formatCurrency(total.realisasi_os), meta: `${formatInteger(total.realisasi_deb)} debitur baru`, tone: 'green' })}
                            ${statCard({ label: 'Konversi Debitur', value: formatPercent(percentOf(total.realisasi_deb, total.total_deb)), meta: 'Realisasi terhadap kelolaan', tone: 'cyan', percent: true })}
                            ${statCard({ label: 'Average Ticket', value: formatCurrency(asNumber(total.realisasi_os) / Math.max(1, asNumber(total.realisasi_deb))), meta: 'Nominal per debitur', tone: 'blue' })}
                        </div>
                        <div class="psd-productivity-readout">
                            <i class="fas fa-bullseye" aria-hidden="true"></i>
                            <p>Ranking menonjolkan realisasi, sedangkan portofolio kelolaan memberi konteks kapasitas. Fokuskan coaching pada RM dengan basis besar namun realisasi relatif rendah.</p>
                        </div>
                    </section>
                    <section class="psd-panel psd-table-panel">
                        ${panelTitle('Ranking RM Mikro KUR', `${rows.length} baris berisi`, 'fa-ranking-star')}
                        ${matrixTable({ columns, rows })}
                    </section>
                </main>
                ${insightStrip([
                    { label: 'RM teratas', value: leader?.nama || leader?.name || '-', meta: leader?.realisasi_os_fmt || '-' },
                    { label: 'Realisasi debitur', value: formatInteger(total.realisasi_deb), meta: `Dari ${formatInteger(total.total_deb)} debitur kelolaan` },
                    { label: 'Produktivitas nominal', value: total.realisasi_os_fmt || formatCurrency(total.realisasi_os), meta: 'Akumulasi scope aktif', tone: 'green' },
                ])}
            </div>
        `;
    }

    renderMantri() {
        const mantri = this.data?.micro?.mantri_productivity || {};
        const total = mantri.total || {};
        const rows = (mantri.rows || []).filter((row) => asNumber(row.realisasi_os) !== 0 || asNumber(row.realisasi_deb) !== 0).slice(0, 7);
        const pdwkScope = findScope(this.data?.micro?.pdwk?.scopes, this.scopeKey()) || {};
        const roles = pdwkScope.roles || [];
        const maximum = Math.max(1, ...rows.map((row) => asNumber(row.realisasi_os)));
        const columns = [
            { label: 'Mantri / Unit', key: 'nama_mantri', width: '1.7fr', render: (row) => `<strong>${escapeHtml(row.nama_mantri)}</strong><small>${escapeHtml(row.unit || row.cabang || '-')}</small>` },
            { label: 'Debitur', key: 'realisasi_deb', width: '0.55fr', align: 'right', render: (row) => `<strong>${formatInteger(row.realisasi_deb)}</strong>` },
            { label: 'Realisasi OS', key: 'realisasi_os', width: '1.2fr', align: 'right', render: (row) => progressCell(row.realisasi_os, maximum, row.realisasi_os_fmt || formatCurrency(row.realisasi_os), 'green') },
            { label: 'Ratas HK', key: 'ratas_mantri_hk', width: '0.65fr', align: 'right', render: (row) => `<strong>${asNumber(row.ratas_mantri_hk).toLocaleString('id-ID', { maximumFractionDigits: 1 })}</strong>` },
            { label: 'Kuadran', key: 'ket', width: '0.75fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.ket || '-')}</strong>` },
        ];

        return `
            <div class="psd-slide">
                ${storyHeader({
                    kicker: '4. Produktivitas | Mantri dan PDWK',
                    title: 'Produktivitas Mantri dan Rekap PDWK',
                    subtitle: 'Realisasi Mantri dipadukan dengan putusan K Unit, MBM, dan BOH agar pemilik kontribusi terlihat jelas.',
                    narrative: `${this.scopeLabel()} menghasilkan ${total.realisasi_os_fmt || formatCurrency(total.realisasi_os)} dari ${formatInteger(total.realisasi_deb)} debitur dan ${formatInteger(total.jumlah_mantri)} Mantri. PDWK memisahkan kontribusi K Unit, MBM, dan BOH.`,
                    period: this.data?.micro?.pdwk?.period_label || this.data?.micro?.period_label || this.period(),
                    tone: 'green',
                })}
                <main class="psd-mantri-layout">
                    <section class="psd-panel psd-pdwk-panel">
                        ${panelTitle('Rekap per Putusan', `${roles.length} pemilik putusan`, 'fa-user-check')}
                        <div class="psd-pdwk-total">
                            <span>Total PDWK ${escapeHtml(this.scopeLabel())}</span>
                            <strong>${escapeHtml(pdwkScope.total?.os_fmt || total.realisasi_os_fmt || formatCurrency(total.realisasi_os))}</strong>
                            <small>${formatInteger(pdwkScope.total?.deb || total.realisasi_deb)} debitur</small>
                        </div>
                        <div class="psd-role-grid" style="--psd-role-count:${Math.max(1, roles.length)}">
                            ${roles.length ? roles.map((role) => `
                                <article>
                                    <span>${escapeHtml(role.label)}</span>
                                    <strong>${escapeHtml(role.total_os_fmt || formatCurrency(role.total_os))}</strong>
                                    <small>${formatInteger(role.total_deb)} deb | ${escapeHtml(role.share_pct_fmt || formatPercent(role.share_pct))}</small>
                                    <div class="psd-share-bar"><i style="width:${Math.min(100, asNumber(role.share_pct))}%"></i></div>
                                </article>
                            `).join('') : `
                                <div class="psd-empty">
                                    <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
                                    <span>Rekap PDWK sedang dimuat.</span>
                                </div>
                            `}
                        </div>
                    </section>
                    <section class="psd-panel psd-table-panel">
                        ${panelTitle('Ranking Produktivitas Mantri', `${rows.length} baris berisi`, 'fa-ranking-star')}
                        ${matrixTable({ columns, rows })}
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Total Mantri', value: formatInteger(total.jumlah_mantri), meta: `${formatInteger(total.realisasi_deb)} debitur terealisasi` },
                    { label: 'Realisasi OS', value: total.realisasi_os_fmt || formatCurrency(total.realisasi_os), meta: 'Akumulasi scope aktif', tone: 'green' },
                    { label: 'Kontributor putusan', value: roles[0]?.label || '-', meta: roles[0] ? `${roles[0].share_pct_fmt} dari PDWK` : 'Menunggu data PDWK' },
                ])}
            </div>
        `;
    }

    scopedKts() {
        return this.data?.kts?.categories?.[this.ktsCategory]?.[this.ktsScope] || {};
    }

    scopedKtsDetails(payload = this.scopedKts()) {
        const allBranches = Array.isArray(payload?.branches) ? payload.branches : [];
        const activeLabel = String(this.scopeLabel() || '').trim().toUpperCase();
        const branches = this.isArea()
            ? allBranches
            : allBranches.filter((branch) => String(branch?.branch_name || '').trim().toUpperCase() === activeLabel);
        const detailRows = branches.flatMap((branch) => (Array.isArray(branch?.debiturs) ? branch.debiturs : []).map((row) => ({
            ...row,
            cabang: branch.branch_name,
            debitur: row.debitur || row.nama || row.nama_debitur,
            rekening: row.rekening || row.no_rekening || row.norek || row.nomor_rekening,
            os: asNumber(row.os || row.baki_debet),
            os_fmt: row.os_fmt || row.baki_debet_fmt,
        })));
        const fallbackRows = detailRows.length
            ? []
            : (Array.isArray(payload?.rows) ? payload.rows : []).filter((row) => (
                row?.debitur
                || row?.nama
                || row?.nama_debitur
                || row?.rekening
                || row?.nomor_rekening
            ));
        const rows = [...detailRows, ...fallbackRows]
            .filter((row) => row && (asNumber(row.os || row.baki_debet) !== 0 || row.debitur || row.rekening))
            .sort((a, b) => asNumber(b.os || b.baki_debet) - asNumber(a.os || a.baki_debet));
        const branchTotalCount = branches.reduce((total, branch) => total + asNumber(branch?.total_count), 0);
        const branchTotalOs = branches.reduce((total, branch) => total + asNumber(branch?.total_os), 0);
        const totalCount = this.isArea() ? asNumber(payload?.total_count) : branchTotalCount;
        const totalOs = this.isArea() ? asNumber(payload?.total_os) : branchTotalOs;

        return {
            branches,
            rows,
            totalCount,
            totalOs,
            affectedBranches: branches.filter((branch) => asNumber(branch?.total_count) > 0).length,
        };
    }

    renderKts() {
        const loading = Boolean(this.data?.kts?.loading_details);
        const payload = this.scopedKts();
        const details = this.scopedKtsDetails(payload);
        const rows = details.rows.slice(0, 9);
        const categories = [
            ['membaik', 'Membaik'],
            ['memburuk', 'Memburuk'],
        ];
        const scopes = [
            ['ritel', 'Ritel'],
            ['micro', 'Mikro'],
        ];
        const controls = `
            <div class="psd-segmented" role="group" aria-label="Filter KTS">
                ${categories.map(([key, label]) => `<button type="button" data-psd-kts-category="${key}" class="${this.ktsCategory === key ? 'is-active' : ''}">${label}</button>`).join('')}
            </div>
            <div class="psd-segmented" role="group" aria-label="Segmen KTS">
                ${scopes.map(([key, label]) => `<button type="button" data-psd-kts-scope="${key}" class="${this.ktsScope === key ? 'is-active' : ''}">${label}</button>`).join('')}
            </div>
        `;
        const columns = [
            { label: '#', key: 'rank', width: '0.25fr', render: (_row, index) => String(index + 1) },
            { label: 'Debitur / Rekening', key: 'debitur', width: '1.8fr', render: (row) => `<strong>${escapeHtml(row.debitur || row.nama || row.nama_debitur || '-')}</strong><small>${escapeHtml(row.rekening || row.no_rekening || row.norek || row.nomor_rekening || '-')}</small>` },
            { label: 'Unit Kerja', key: 'unit', width: '1.15fr', render: (row) => `<strong>${escapeHtml(row.unit || row.unit_kerja || row.cabang || '-')}</strong>` },
            { label: 'Kolek Aktual -> Seharusnya', key: 'kolek', width: '1.05fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.kolek_label || row.pergeseran || `${row.kolek_aktual || '-'} -> ${row.kolek_seharusnya || '-'}`)}</strong>` },
            { label: 'Baki Debet', key: 'os', width: '0.9fr', align: 'right', render: (row) => `<strong>${escapeHtml(row.os_fmt || row.baki_debet_fmt || formatCurrency(row.os || row.baki_debet))}</strong>` },
        ];
        const title = this.ktsCategory === 'membaik' ? 'KTS Membaik' : 'KTS Memburuk';

        return `
            <div class="psd-slide">
                ${storyHeader({
                    kicker: '4. Pendamping | KTS',
                    title: 'KTS Decision Support',
                    subtitle: 'Rekening yang kolektibilitas aktualnya berbeda dari umur tunggakan untuk memperjelas prioritas tindakan.',
                    narrative: loading
                        ? 'Detail KTS dimuat saat slide dibuka agar deck awal tetap cepat. Ringkasan lain tetap dapat digunakan.'
                        : `${title} ${this.ktsScope === 'ritel' ? 'Ritel' : 'Mikro'} ${this.scopeLabel()} mencakup ${formatInteger(details.totalCount)} rekening dengan eksposur ${formatCurrency(details.totalOs)}.`,
                    period: this.data?.kts?.period_label || this.period(),
                    tone: this.ktsCategory === 'membaik' ? 'green' : 'red',
                    controls,
                })}
                <main class="psd-kts-layout">
                    <aside class="psd-kts-summary">
                        ${statCard({ label: 'Kategori', value: title, meta: this.ktsScope === 'ritel' ? 'Segmen Ritel' : 'Segmen Mikro', tone: this.ktsCategory === 'membaik' ? 'green' : 'red' })}
                        ${statCard({ label: 'Total Rekening', value: loading ? 'Memuat...' : formatInteger(details.totalCount), meta: 'Setelah filter aktif', tone: 'blue' })}
                        ${statCard({ label: 'OS Terdampak', value: loading ? 'Memuat...' : formatCurrency(details.totalOs), meta: 'Baki debet KTS', tone: 'amber' })}
                        <div class="psd-kts-definition">
                            <strong>${this.ktsCategory === 'membaik' ? 'Potensi curing' : 'Potensi penurunan kualitas'}</strong>
                            <p>${this.ktsCategory === 'membaik'
                                ? 'Kolektibilitas aktual lebih baik dibanding kolektibilitas seharusnya berdasarkan tunggakan.'
                                : 'Kolektibilitas aktual lebih buruk atau mengarah pada tekanan berdasarkan tunggakan.'}</p>
                        </div>
                    </aside>
                    <section class="psd-panel psd-table-panel">
                        ${panelTitle(`${title} ${this.ktsScope === 'ritel' ? 'Ritel' : 'Mikro'}`, loading ? 'Detail on-demand' : `${rows.length} dari ${formatInteger(details.totalCount)} rekening`, 'fa-list-check')}
                        ${loading
                            ? `<div class="psd-loading-state"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i><strong>Memuat detail KTS</strong><span>Data utama deck tetap siap digunakan.</span></div>`
                            : matrixTable({
                                columns,
                                rows,
                                emptyMessage: `Tidak ada rekening ${title.toLowerCase()} pada filter ini.`,
                            })}
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Cabang terdampak', value: loading ? '-' : formatInteger(details.affectedBranches), meta: this.scopeLabel() },
                    { label: 'Eksposur rekening terbesar', value: loading ? '-' : (rows[0]?.os_fmt || rows[0]?.baki_debet_fmt || formatCurrency(rows[0]?.os || rows[0]?.baki_debet)), meta: rows[0]?.debitur || rows[0]?.nama || 'Tidak ada data' },
                    { label: 'Arah tindakan', value: this.ktsCategory === 'membaik' ? 'Validasi curing' : 'Intervensi dini', meta: this.ktsCategory === 'membaik' ? 'Pastikan status membaik berkelanjutan' : 'Cegah migrasi kolektibilitas', tone: this.ktsCategory === 'membaik' ? 'green' : 'red' },
                ])}
            </div>
        `;
    }

    renderCompactTrend(keys, title, meta, inverse = false) {
        const scope = this.timeseriesScope();
        const labels = Array.isArray(scope.labels) ? scope.labels : [];
        const allSeries = this.timeseriesSeries(scope);
        const lanes = (Array.isArray(keys) ? keys : [])
            .map((key) => findByKey(allSeries, key))
            .filter((series) => series && Array.isArray(series.values) && series.values.some((value) => Number.isFinite(Number(value))))
            .map((series) => {
                const values = series.values.map(asNumber);
                const displays = Array.isArray(series.display_values) ? series.display_values : [];
                const startIndex = Math.max(0, values.length - 6);
                const recentValues = values.slice(startIndex);
                const recentDisplays = displays.slice(startIndex);
                const recentLabels = labels.slice(startIndex, startIndex + recentValues.length);
                const minimum = Math.min(...recentValues);
                const maximum = Math.max(...recentValues);
                const range = maximum - minimum || 1;
                const width = 360;
                const height = 42;
                const color = /^#[0-9a-f]{6}$/i.test(String(series.color || '')) ? series.color : '#0857c3';
                const points = recentValues.map((value, index) => {
                    const x = recentValues.length > 1 ? (index / (recentValues.length - 1)) * width : width / 2;
                    const y = height - 4 - (((value - minimum) / range) * (height - 8));
                    return { x, y };
                });
                const first = recentValues[0] ?? 0;
                const latest = recentValues[recentValues.length - 1] ?? 0;
                const delta = latest - first;
                const isPercent = series.format === 'percent';
                const deltaText = isPercent
                    ? `${delta >= 0 ? '+' : ''}${formatPercent(delta)} poin`
                    : `${delta >= 0 ? '+' : ''}${formatCurrency(delta * 1_000_000)}`;
                const latestDisplay = recentDisplays[recentDisplays.length - 1]
                    || (isPercent ? formatPercent(latest) : formatCurrency(latest * 1_000_000));
                const positive = inverse ? delta <= 0 : delta >= 0;

                return {
                    ...series,
                    color,
                    points,
                    latestDisplay,
                    deltaText,
                    deltaTone: delta === 0 ? 'neutral' : positive ? 'positive' : 'negative',
                    firstLabel: recentLabels[0] || '-',
                    lastLabel: recentLabels[recentLabels.length - 1] || '-',
                };
            });

        if (!lanes.length) {
            return `
                <section class="psd-mini-trend is-fallback">
                    ${panelTitle(title, meta, 'fa-chart-line')}
                    <div class="psd-mini-trend-fallback">
                        <i class="fas fa-circle-info" aria-hidden="true"></i>
                        <span>Trend akan mengikuti snapshot bulanan terbaru pada scope ${escapeHtml(this.scopeLabel())}.</span>
                    </div>
                </section>
            `;
        }

        const modalKeys = lanes
            .map((lane) => String(lane.key || '').trim())
            .filter(Boolean);

        return `
            <section
                class="psd-mini-trend is-expandable"
                style="--psd-mini-series:${lanes.length}"
                data-psd-timeseries-expand
                data-psd-timeseries-keys="${escapeHtml(modalKeys.join(','))}"
                data-psd-timeseries-title="${escapeHtml(title)}"
                role="button"
                tabindex="0"
                aria-haspopup="dialog"
                aria-label="Buka ${escapeHtml(title)} harian dalam tampilan penuh"
                title="Klik dua kali untuk membuka detail harian empat bulan"
            >
                ${panelTitle(title, `${meta} · Klik 2× untuk detail harian`, 'fa-chart-line')}
                <div class="psd-mini-trend-body">
                    ${lanes.map((lane) => `
                        <article class="psd-mini-trend-lane">
                            <span>
                                <strong>${escapeHtml(lane.label || lane.key)}</strong>
                                <small>${escapeHtml(lane.latestDisplay)}</small>
                            </span>
                            <svg viewBox="0 0 360 42" preserveAspectRatio="none" aria-hidden="true">
                                <line x1="0" y1="38" x2="360" y2="38" class="psd-mini-grid"></line>
                                <polyline points="${lane.points.map((point) => `${point.x.toFixed(1)},${point.y.toFixed(1)}`).join(' ')}" style="stroke:${escapeHtml(lane.color)}"></polyline>
                                ${lane.points.length ? `<circle cx="${lane.points[lane.points.length - 1].x.toFixed(1)}" cy="${lane.points[lane.points.length - 1].y.toFixed(1)}" r="4" style="fill:${escapeHtml(lane.color)}"></circle>` : ''}
                            </svg>
                            <em class="is-${lane.deltaTone}">
                                ${escapeHtml(lane.deltaText)}
                                <small>${escapeHtml(lane.firstLabel)} - ${escapeHtml(lane.lastLabel)}</small>
                            </em>
                        </article>
                    `).join('')}
                </div>
            </section>
        `;
    }

    timeseriesScope() {
        return findScope(this.data?.timeseries?.scopes, this.scopeKey()) || {
            labels: this.data?.timeseries?.labels || [],
            series: this.data?.timeseries?.series || [],
        };
    }

    timeseriesSeries(scopeOrKey = null) {
        const scope = scopeOrKey && typeof scopeOrKey === 'object'
            ? scopeOrKey
            : this.timeseriesScope();
        const source = scope?.series || {};
        const series = Array.isArray(source) ? source : Object.values(source);

        if (typeof scopeOrKey === 'string') {
            return findByKey(series, scopeOrKey) || {};
        }

        return series;
    }

    businessSnapshot() {
        const funding = this.summaryMetric('simpanan');
        const loan = this.summaryMetric('os');
        const fundingValue = asNumber(funding.latest);
        const loanValue = asNumber(loan.latest);
        const ldrRatio = fundingValue > 0
            ? loanValue / fundingValue
            : asNumber(this.summaryCard('ldr')?.value_raw);

        return {
            funding: fundingValue,
            fundingLabel: funding.latest_fmt || formatCurrency(fundingValue),
            loan: loanValue,
            loanLabel: loan.latest_fmt || formatCurrency(loanValue),
            ldrRatio,
            ldrLabel: formatPercent(ldrRatio * 100),
        };
    }

    renderBusinessQuadrant() {
        const sourceRows = Array.isArray(this.data?.performance_overview?.branches)
            ? this.data.performance_overview.branches
            : [];
        const rows = sourceRows
            .filter((row) => asNumber(row?.simpanan) > 0 || asNumber(row?.pinjaman) > 0)
            .map((row) => ({
                ...row,
                funding: asNumber(row.simpanan),
                loan: asNumber(row.pinjaman),
                ldr: asNumber(row.simpanan) > 0 ? (asNumber(row.pinjaman) / asNumber(row.simpanan)) * 100 : 0,
            }));
        const width = 560;
        const height = 520;
        const left = 58;
        const right = 24;
        const top = 36;
        const bottom = 58;
        const plotWidth = width - left - right;
        const plotHeight = height - top - bottom;
        const maximum = Math.max(1, ...rows.flatMap((row) => [row.funding, row.loan])) * 1.08;
        const fundingAverage = rows.length ? rows.reduce((total, row) => total + row.funding, 0) / rows.length : maximum / 2;
        const loanAverage = rows.length ? rows.reduce((total, row) => total + row.loan, 0) / rows.length : maximum / 2;
        const xFor = (value) => left + ((asNumber(value) / maximum) * plotWidth);
        const yFor = (value) => top + plotHeight - ((asNumber(value) / maximum) * plotHeight);
        const activeScope = String(this.scopeLabel() || '').trim().toUpperCase();
        const points = rows.map((row, index) => {
            const x = xFor(row.funding);
            const y = yFor(row.loan);
            const isActive = this.isArea() || String(row.name || '').trim().toUpperCase() === activeScope;
            const anchor = x > width * 0.7 ? 'end' : 'start';
            const labelX = x + (anchor === 'end' ? -10 : 10);
            const labelY = Math.max(top + 18, Math.min(top + plotHeight - 28, y + (index % 2 === 0 ? -30 : 34)));

            return `
                <g class="psd-quadrant-point ${isActive ? 'is-active' : ''}">
                    <circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="${isActive ? 8 : 6}"></circle>
                    <text x="${labelX.toFixed(1)}" y="${labelY.toFixed(1)}" text-anchor="${anchor}">
                        <tspan>${escapeHtml(compactName(row.name).replace(/^KC\s+/i, ''))}</tspan>
                        <tspan x="${labelX.toFixed(1)}" dy="14">${escapeHtml(formatPercent(row.ldr))} LDR</tspan>
                    </text>
                </g>
            `;
        }).join('');
        const halfLabel = formatCurrency(maximum / 2).replace('Rp', '').trim();
        const maxLabel = formatCurrency(maximum).replace('Rp', '').trim();

        return `
            <section class="psd-panel psd-quadrant-panel">
                ${panelTitle('Kuadran Dana dan Pinjaman', 'X: Dana | Y: Pinjaman', 'fa-chart-scatter')}
                <div class="psd-quadrant-body">
                    <svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Kuadran Dana pada sumbu X dan Pinjaman pada sumbu Y">
                        <rect class="psd-quadrant-zone is-low-low" x="${left}" y="${yFor(loanAverage)}" width="${Math.max(0, xFor(fundingAverage) - left)}" height="${Math.max(0, top + plotHeight - yFor(loanAverage))}"></rect>
                        <rect class="psd-quadrant-zone is-high-high" x="${xFor(fundingAverage)}" y="${top}" width="${Math.max(0, left + plotWidth - xFor(fundingAverage))}" height="${Math.max(0, yFor(loanAverage) - top)}"></rect>
                        <line class="psd-quadrant-grid" x1="${left}" y1="${yFor(maximum / 2)}" x2="${left + plotWidth}" y2="${yFor(maximum / 2)}"></line>
                        <line class="psd-quadrant-grid" x1="${xFor(maximum / 2)}" y1="${top}" x2="${xFor(maximum / 2)}" y2="${top + plotHeight}"></line>
                        <line class="psd-quadrant-average" x1="${left}" y1="${yFor(loanAverage)}" x2="${left + plotWidth}" y2="${yFor(loanAverage)}"></line>
                        <line class="psd-quadrant-average" x1="${xFor(fundingAverage)}" y1="${top}" x2="${xFor(fundingAverage)}" y2="${top + plotHeight}"></line>
                        <line class="psd-quadrant-balance" x1="${left}" y1="${top + plotHeight}" x2="${left + plotWidth}" y2="${top}"></line>
                        <line class="psd-quadrant-axis" x1="${left}" y1="${top + plotHeight}" x2="${left + plotWidth}" y2="${top + plotHeight}"></line>
                        <line class="psd-quadrant-axis" x1="${left}" y1="${top}" x2="${left}" y2="${top + plotHeight}"></line>
                        <text class="psd-quadrant-tick" x="${left}" y="${top + plotHeight + 21}" text-anchor="middle">0</text>
                        <text class="psd-quadrant-tick" x="${xFor(maximum / 2)}" y="${top + plotHeight + 21}" text-anchor="middle">${escapeHtml(halfLabel)}</text>
                        <text class="psd-quadrant-tick" x="${left + plotWidth}" y="${top + plotHeight + 21}" text-anchor="middle">${escapeHtml(maxLabel)}</text>
                        <text class="psd-quadrant-tick" x="${left - 8}" y="${top + plotHeight + 4}" text-anchor="end">0</text>
                        <text class="psd-quadrant-tick" x="${left - 8}" y="${yFor(maximum / 2) + 4}" text-anchor="end">${escapeHtml(halfLabel)}</text>
                        <text class="psd-quadrant-tick" x="${left - 8}" y="${top + 4}" text-anchor="end">${escapeHtml(maxLabel)}</text>
                        <text class="psd-quadrant-axis-label" x="${left + (plotWidth / 2)}" y="${height - 5}" text-anchor="middle">Dana / Simpanan (X)</text>
                        <text class="psd-quadrant-axis-label" transform="translate(14 ${top + (plotHeight / 2)}) rotate(-90)" text-anchor="middle">Pinjaman / OS (Y)</text>
                        <text class="psd-quadrant-balance-label" x="${left + plotWidth - 4}" y="${top + 15}" text-anchor="end">LDR 100%</text>
                        ${points}
                    </svg>
                    <div class="psd-quadrant-note">
                        <strong>Garis diagonal = LDR 100%</strong>
                        <span>Titik di atas garis menunjukkan Pinjaman lebih besar dari Dana; garis putus-putus membagi posisi terhadap rata-rata Area 6.</span>
                    </div>
                </div>
            </section>
        `;
    }

    renderTimeseries() {
        const timeseries = this.data?.timeseries || {};
        const scope = this.timeseriesScope();
        const groups = timeseries.groups || {};
        const group = groups[this.trendGroup] || groups.business || { label: 'Skala Bisnis', description: 'Pergerakan indikator utama.', keys: ['simpanan', 'os'] };
        const allSeries = this.timeseriesSeries(scope);
        const series = (group.keys || []).map((key) => findByKey(allSeries, key)).filter(Boolean);
        const labels = scope.labels || timeseries.labels || [];
        const controls = `
            <div class="psd-segmented is-wide" role="group" aria-label="Kelompok timeseries">
                ${Object.entries(groups).map(([key, item]) => `<button type="button" data-psd-trend-group="${escapeHtml(key)}" class="${this.trendGroup === key ? 'is-active' : ''}">${escapeHtml(item.label)}</button>`).join('')}
            </div>
        `;
        const latestRows = labels.slice(-6).map((label, index) => {
            const actualIndex = labels.length - Math.min(6, labels.length) + index;
            const row = { label };
            series.forEach((item) => {
                row[item.key] = item.display_values?.[actualIndex]
                    || (item.format === 'percent' ? formatPercent(item.values?.[actualIndex]) : formatCurrency(asNumber(item.values?.[actualIndex]) * 1_000_000));
            });
            return row;
        });
        const columns = [
            { label: 'Periode', key: 'label', width: '0.8fr', render: (row) => `<strong>${escapeHtml(row.label)}</strong>` },
            ...series.map((item) => ({
                label: item.label,
                key: item.key,
                width: '1fr',
                align: 'right',
                render: (row) => `<strong>${escapeHtml(row[item.key] || '-')}</strong>`,
            })),
        ];
        const momentum = series.map((item) => {
            const values = item.values || [];
            const first = asNumber(values[0]);
            const last = asNumber(values.at(-1));
            const change = first === 0 ? 0 : ((last / first) - 1) * 100;
            return { ...item, change, last };
        });
        const strongest = [...momentum].sort((a, b) => b.change - a.change)[0];
        const weakest = [...momentum].sort((a, b) => a.change - b.change)[0];
        const isBusiness = (group.keys || []).includes('simpanan') && (group.keys || []).includes('os');
        const business = this.businessSnapshot();

        return `
            <div class="psd-slide">
                ${storyHeader({
                    kicker: '5. Tren | Timeseries',
                    title: 'Timeseries Kinerja Terintegrasi',
                    subtitle: group.description || 'Pergerakan indikator pada tiga belas posisi bulanan.',
                    narrative: strongest
                        ? `${strongest.label} mencatat perubahan terkuat ${formatPercent(strongest.change)} dari titik awal, sedangkan ${weakest?.label || '-'} berubah ${formatPercent(weakest?.change)}.${isBusiness ? ` LDR ${business.ldrLabel} dihitung dari Pinjaman dibagi Simpanan.` : ''}`
                        : 'Timeseries sedang dimuat untuk scope dan periode aktif.',
                    period: labels.length ? `${labels[0]} - ${labels.at(-1)}` : this.period(),
                    tone: 'blue',
                    controls,
                })}
                <main class="psd-timeseries-layout">
                    <section class="psd-panel psd-chart-panel">
                        ${panelTitle(group.label || 'Timeseries', `${labels.length} titik bulanan · Klik 2× untuk detail harian`, 'fa-chart-line')}
                        <div
                            class="psd-chart-wrap is-expandable"
                            data-psd-timeseries-expand
                            role="button"
                            tabindex="0"
                            aria-haspopup="dialog"
                            aria-label="Buka grafik ${escapeHtml(group.label || 'timeseries')} harian dalam tampilan penuh"
                            title="Klik dua kali untuk membuka detail harian empat bulan"
                        >
                            <canvas id="psd-timeseries-chart" aria-label="Grafik timeseries ${escapeHtml(group.label || '')}"></canvas>
                        </div>
                        <div class="psd-series-legend">
                            ${series.map((item) => `<span><i style="background:${escapeHtml(item.color || '#0857c3')}"></i>${escapeHtml(item.label)}</span>`).join('')}
                        </div>
                    </section>
                    ${isBusiness ? `
                        <aside class="psd-timeseries-side">
                            <div class="psd-business-kpis">
                                ${statCard({ label: 'Dana / Simpanan', value: business.fundingLabel, meta: 'Sumbu X kuadran', tone: 'blue' })}
                                ${statCard({ label: 'Pinjaman / OS', value: business.loanLabel, meta: 'Sumbu Y kuadran', tone: 'green' })}
                                ${statCard({ label: 'LDR', value: business.ldrLabel, meta: 'Pinjaman / Simpanan', tone: business.ldrRatio > 1 ? 'amber' : 'green', percent: true })}
                            </div>
                            ${this.renderBusinessQuadrant()}
                        </aside>
                    ` : `
                        <section class="psd-panel psd-table-panel">
                            ${panelTitle('Posisi Bulanan', `${latestRows.length} periode terakhir`, 'fa-calendar-days')}
                            ${matrixTable({
                                columns,
                                rows: latestRows,
                                emptyMessage: 'Timeseries belum tersedia pada scope ini.',
                                className: 'is-timeseries',
                            })}
                        </section>
                    `}
                </main>
                ${insightStrip([
                    { label: 'Momentum terkuat', value: strongest?.label || '-', meta: strongest ? formatPercent(strongest.change) : 'Menunggu data', tone: 'green' },
                    { label: 'Perlu perhatian', value: weakest?.label || '-', meta: weakest ? formatPercent(weakest.change) : 'Menunggu data', tone: weakest && weakest.change < 0 ? 'red' : 'amber' },
                    isBusiness
                        ? { label: 'Loan Deposit Ratio', value: business.ldrLabel, meta: 'Pinjaman dibagi Dana / Simpanan', tone: business.ldrRatio > 1 ? 'amber' : 'green' }
                        : { label: 'Basis analisis', value: `${labels.length} titik data`, meta: 'Nominal dan bulan tampil pada grafik' },
                ])}
            </div>
        `;
    }

    timeseriesModalData(trigger = null) {
        const scope = this.timeseriesScope();
        const daily = scope?.daily && typeof scope.daily === 'object' ? scope.daily : {};
        const hasDaily = daily.available !== false
            && Array.isArray(daily.periods)
            && daily.periods.length > 0
            && daily.series
            && typeof daily.series === 'object';
        const source = hasDaily ? daily : scope;
        const periods = Array.isArray(source?.periods) ? source.periods : [];
        const labels = Array.isArray(source?.labels) ? source.labels : [];
        const sourceSeries = Array.isArray(source?.series)
            ? source.series
            : Object.values(source?.series || {});
        const groups = this.data?.timeseries?.groups || {};
        const requestedKeys = String(trigger?.dataset?.psdTimeseriesKeys || '')
            .split(',')
            .map((key) => key.trim())
            .filter(Boolean);
        const activeGroup = groups[this.trendGroup]
            || groups.business
            || { label: 'Timeseries', keys: ['simpanan', 'os'] };
        const group = requestedKeys.length
            ? {
                label: trigger?.dataset?.psdTimeseriesTitle || activeGroup.label || 'Timeseries',
                keys: requestedKeys,
            }
            : activeGroup;
        const groupSeries = (group.keys || [])
            .map((key) => findByKey(sourceSeries, key))
            .filter((item) => item && Array.isArray(item.values));
        const pointCount = Math.min(
            labels.length || Number.POSITIVE_INFINITY,
            periods.length || Number.POSITIVE_INFINITY,
            ...groupSeries.map((item) => item.values.length)
        );

        if (!Number.isFinite(pointCount) || pointCount <= 0 || !groupSeries.length) {
            return {
                available: false,
                group,
                daily: hasDaily,
                overlay: false,
                labels: [],
                periods: [],
                series: [],
                metrics: [],
                months: [],
            };
        }

        const normalizedPeriods = periods.slice(-pointCount);
        const normalizedLabels = labels.slice(-pointCount);
        const normalizedSeries = groupSeries.map((item) => ({
            ...item,
            values: item.values.slice(-pointCount),
            display_values: Array.isArray(item.display_values)
                ? item.display_values.slice(-pointCount)
                : [],
        }));

        if (hasDaily) {
            const latestPeriod = daily.end_period || normalizedPeriods.at(-1);
            const end = this.parseTimeseriesDate(latestPeriod);
            if (!end) {
                return {
                    available: false,
                    group,
                    daily: true,
                    overlay: false,
                    labels: [],
                    periods: [],
                    series: [],
                    metrics: [],
                    months: [],
                };
            }

            const palette = [
                { color: '#0857c3', dash: [], width: 3.2 },
                { color: '#10b981', dash: [10, 4], width: 2.5 },
                { color: '#f59e0b', dash: [5, 4], width: 2.4 },
                { color: '#7c3aed', dash: [2, 4], width: 2.4 },
            ];
            const months = Array.from({ length: 4 }, (_value, offset) => {
                const date = new Date(Date.UTC(
                    end.getUTCFullYear(),
                    end.getUTCMonth() - offset,
                    1
                ));
                const year = date.getUTCFullYear();
                const month = String(date.getUTCMonth() + 1).padStart(2, '0');

                return {
                    key: `${year}-${month}`,
                    label: this.formatTimeseriesMonth(date),
                    offset,
                    ...palette[offset],
                };
            });
            const monthIndex = new Map(months.map((month, index) => [month.key, index]));
            const metrics = normalizedSeries.map((item) => {
                const monthlyLines = months.map((month) => ({
                    ...month,
                    values: Array(31).fill(null),
                    display_values: Array(31).fill('-'),
                    actual_dates: Array(31).fill(null),
                    point_count: 0,
                }));

                normalizedPeriods.forEach((period, index) => {
                    const date = this.parseTimeseriesDate(period);
                    if (!date || date.getTime() > end.getTime()) return;
                    const key = `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
                    const targetIndex = monthIndex.get(key);
                    if (targetIndex === undefined) return;
                    const dayIndex = date.getUTCDate() - 1;
                    const rawValue = item.values[index];
                    const value = rawValue === null || rawValue === undefined || rawValue === ''
                        ? null
                        : Number(rawValue);
                    if (value === null || !Number.isFinite(value)) return;

                    monthlyLines[targetIndex].values[dayIndex] = value;
                    monthlyLines[targetIndex].display_values[dayIndex] = item.display_values?.[index]
                        || (item.format === 'percent'
                            ? formatPercent(value)
                            : formatCurrency(value * 1_000_000));
                    monthlyLines[targetIndex].actual_dates[dayIndex] = period;
                    monthlyLines[targetIndex].point_count += 1;
                });

                return {
                    ...item,
                    months: monthlyLines,
                };
            });
            const available = metrics.some((metric) => metric.months
                .some((month) => month.point_count > 0));

            return {
                available,
                group,
                daily: true,
                overlay: true,
                labels: Array.from({ length: 31 }, (_value, index) => String(index + 1)),
                periods: normalizedPeriods,
                series: metrics,
                metrics,
                months,
                start_period: `${months.at(-1)?.key}-01`,
                end_period: latestPeriod,
                latest_period: latestPeriod,
            };
        }

        const indexes = Array.from({ length: pointCount }, (_value, index) => index).slice(-5);
        return {
            available: indexes.length > 0,
            group,
            daily: false,
            overlay: false,
            labels: indexes.map((index) => normalizedLabels[index] || normalizedPeriods[index] || '-'),
            periods: indexes.map((index) => normalizedPeriods[index] || ''),
            series: normalizedSeries.map((item) => ({
                ...item,
                values: indexes.map((index) => item.values[index]),
                display_values: indexes.map((index) => item.display_values?.[index] || ''),
            })),
            metrics: normalizedSeries,
            months: [],
        };
    }

    parseTimeseriesDate(value) {
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) return null;
        const date = new Date(Date.UTC(
            Number(match[1]),
            Number(match[2]) - 1,
            Number(match[3])
        ));
        return Number.isNaN(date.getTime()) ? null : date;
    }

    formatTimeseriesDate(value, fallback = '-') {
        const date = this.parseTimeseriesDate(value);
        if (!date) return fallback;
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            timeZone: 'UTC',
        }).format(date);
    }

    formatTimeseriesMonth(value, fallback = '-') {
        const date = value instanceof Date ? value : this.parseTimeseriesDate(value);
        if (!date || Number.isNaN(date.getTime())) return fallback;
        return new Intl.DateTimeFormat('id-ID', {
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC',
        }).format(date);
    }

    timeseriesMetricLatestPeriod(metric, fallback = '') {
        let latestPeriod = '';
        let latestTimestamp = Number.NEGATIVE_INFINITY;
        (metric?.months || []).forEach((month) => {
            (month.actual_dates || []).forEach((period) => {
                const date = this.parseTimeseriesDate(period);
                if (!date || date.getTime() <= latestTimestamp) return;
                latestPeriod = period;
                latestTimestamp = date.getTime();
            });
        });

        return latestPeriod || fallback;
    }

    openTimeseriesModal(trigger = null) {
        const modalData = this.timeseriesModalData(trigger);
        if (!modalData.available || typeof window.Chart !== 'function') return;

        this.closeTimeseriesModal({ restoreFocus: false });
        const documentRef = this.root?.ownerDocument || document;
        const activeMetric = modalData.overlay
            ? (modalData.metrics || []).find((metric) => (
                (metric.months || []).some((month) => month.point_count > 0)
            )) || modalData.metrics?.[0] || null
            : modalData.series?.[0] || null;
        if (!activeMetric) return;
        this.timeseriesModalPayload = modalData;
        this.timeseriesModalMetricKey = activeMetric.key;
        const startLabel = modalData.overlay
            ? this.formatTimeseriesMonth(modalData.start_period)
            : this.formatTimeseriesDate(modalData.periods[0], modalData.labels[0] || '-');
        const endLabel = modalData.overlay
            ? this.formatTimeseriesMonth(modalData.end_period)
            : this.formatTimeseriesDate(modalData.periods.at(-1), modalData.labels.at(-1) || '-');
        const latestPeriod = modalData.overlay
            ? this.timeseriesMetricLatestPeriod(
                activeMetric,
                modalData.latest_period || modalData.end_period || modalData.periods.at(-1)
            )
            : modalData.periods.at(-1);
        const latestLabel = this.formatTimeseriesDate(
            latestPeriod,
            endLabel
        );
        const modal = documentRef.createElement('div');
        modal.className = 'psd-timeseries-modal';
        modal.dataset.psdTimeseriesModal = '';
        modal.dataset.psdTimeseriesActiveMetric = activeMetric.key || '';
        modal.innerHTML = `
            <section
                class="psd-timeseries-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="psd-timeseries-modal-title"
                aria-describedby="psd-timeseries-modal-description"
            >
                <header class="psd-timeseries-dialog-header">
                    <div class="psd-timeseries-dialog-heading">
                        <span>${modalData.overlay ? 'Perbandingan Timeseries Harian' : 'Timeseries Bulanan'}</span>
                        <h2 id="psd-timeseries-modal-title">${escapeHtml(modalData.group.label || 'Timeseries')}</h2>
                        <p id="psd-timeseries-modal-description">
                            ${escapeHtml(this.scopeLabel())} · ${escapeHtml(startLabel)} – ${escapeHtml(endLabel)}
                            ${modalData.overlay ? ` · Data terakhir <time data-psd-timeseries-latest-period>${escapeHtml(latestLabel)}</time>` : ''}
                        </p>
                    </div>
                    ${modalData.overlay ? `
                        <div class="psd-timeseries-metric-selector">
                            <span>Indikator</span>
                            ${modalData.metrics.length > 1 ? `
                                <div role="group" aria-label="Pilih indikator timeseries">
                                    ${modalData.metrics.map((metric, index) => `
                                        <button
                                            type="button"
                                            data-psd-timeseries-metric="${escapeHtml(metric.key || '')}"
                                            class="${index === 0 ? 'is-active' : ''}"
                                            aria-pressed="${index === 0 ? 'true' : 'false'}"
                                        >${escapeHtml(metric.label || metric.key || '-')}</button>
                                    `).join('')}
                                </div>
                            ` : `
                                <strong data-psd-timeseries-active-metric-label>
                                    ${escapeHtml(activeMetric.label || activeMetric.key || '-')}
                                </strong>
                            `}
                        </div>
                    ` : ''}
                    <button type="button" data-psd-timeseries-close aria-label="Tutup detail timeseries">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        <span>Tutup</span>
                    </button>
                </header>
                <div class="psd-timeseries-dialog-chart">
                    <canvas
                        id="psd-timeseries-modal-chart"
                        aria-label="Grafik ${escapeHtml(modalData.group.label || 'timeseries')} ${modalData.overlay ? 'empat garis bulanan pada tanggal 1 sampai 31' : 'bulanan'}"
                    ></canvas>
                </div>
                <footer class="psd-timeseries-dialog-footer">
                    <div class="psd-timeseries-dialog-status ${modalData.overlay ? 'is-daily' : 'is-fallback'}">
                        <i class="fas ${modalData.overlay ? 'fa-calendar-check' : 'fa-circle-info'}" aria-hidden="true"></i>
                        <span>${modalData.overlay
                            ? '4 garis bulanan · sumbu X tanggal 1–31 · nilai kosong berarti snapshot tanggal tersebut tidak tersedia'
                            : 'Detail harian belum tersedia; sementara menampilkan lima posisi bulanan terbaru.'}</span>
                    </div>
                    <div class="psd-timeseries-dialog-legend" data-psd-timeseries-month-legend aria-label="Legenda grafik">
                        ${this.timeseriesMonthLegend(activeMetric, modalData)}
                    </div>
                </footer>
            </section>
        `;

        modal.addEventListener('click', (event) => {
            const metricButton = event.target.closest('[data-psd-timeseries-metric]');
            if (metricButton) {
                event.preventDefault();
                this.updateTimeseriesModalMetric(metricButton.dataset.psdTimeseriesMetric);
                return;
            }
            if (event.target === modal || event.target.closest('[data-psd-timeseries-close]')) {
                this.closeTimeseriesModal();
            }
        });

        this.timeseriesModal = modal;
        this.timeseriesModalRestoreFocus = trigger || documentRef.activeElement;
        this.timeseriesModalBodyWasLocked = documentRef.body.classList.contains('psd-timeseries-modal-open');
        documentRef.body.classList.add('psd-timeseries-modal-open');
        documentRef.body.appendChild(modal);
        documentRef.addEventListener('keydown', this.handleTimeseriesModalKeydown, true);

        window.requestAnimationFrame(() => {
            modal.querySelector('[data-psd-timeseries-close]')?.focus();
            this.drawTimeseriesModalChart(modalData, activeMetric.key);
        });
    }

    timeseriesMonthLegend(metric, modalData) {
        if (!modalData?.overlay) {
            return (modalData?.series || []).map((item) => `
                <span><i style="background:${escapeHtml(item.color || '#0857c3')}"></i>${escapeHtml(item.label || item.key || '-')}</span>
            `).join('');
        }

        return (metric?.months || []).map((month) => `
            <span data-psd-timeseries-month="${escapeHtml(month.key || '')}">
                <i style="background:${escapeHtml(month.color || '#0857c3')}"></i>
                ${escapeHtml(month.label || month.key || '-')}
                <small>${formatInteger(month.point_count)} hari</small>
            </span>
        `).join('');
    }

    updateTimeseriesModalMetric(metricKey) {
        const modalData = this.timeseriesModalPayload;
        if (!this.timeseriesModal || !modalData?.overlay) return;
        const metric = (modalData.metrics || []).find((item) => item.key === metricKey);
        if (!metric) return;

        this.timeseriesModalMetricKey = metric.key;
        this.timeseriesModal.dataset.psdTimeseriesActiveMetric = metric.key;
        this.timeseriesModal.querySelectorAll('[data-psd-timeseries-metric]').forEach((button) => {
            const active = button.dataset.psdTimeseriesMetric === metric.key;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        const activeLabel = this.timeseriesModal.querySelector('[data-psd-timeseries-active-metric-label]');
        if (activeLabel) {
            activeLabel.textContent = metric.label || metric.key || '-';
        }
        const legend = this.timeseriesModal.querySelector('[data-psd-timeseries-month-legend]');
        if (legend) {
            legend.innerHTML = this.timeseriesMonthLegend(metric, modalData);
        }
        const latestPeriod = this.timeseriesMetricLatestPeriod(
            metric,
            modalData.latest_period || modalData.end_period || modalData.periods.at(-1)
        );
        const latestPeriodElement = this.timeseriesModal.querySelector('[data-psd-timeseries-latest-period]');
        if (latestPeriodElement) {
            latestPeriodElement.textContent = this.formatTimeseriesDate(latestPeriod);
        }
        this.drawTimeseriesModalChart(modalData, metric.key);
    }

    drawTimeseriesModalChart(modalData, metricKey = null) {
        const canvas = this.timeseriesModal?.querySelector('#psd-timeseries-modal-chart');
        if (!canvas || typeof window.Chart !== 'function') return;

        const activeMetric = modalData.overlay
            ? (modalData.metrics || []).find((item) => item.key === metricKey)
                || modalData.metrics?.[0]
            : null;
        const datasets = modalData.overlay
            ? (activeMetric?.months || []).map((month) => ({
                label: month.label || month.key,
                metricKey: activeMetric.key,
                monthKey: month.key,
                data: month.values || [],
                displayValues: month.display_values || [],
                actualDates: month.actual_dates || [],
                borderColor: month.color || '#0857c3',
                backgroundColor: `${month.color || '#0857c3'}18`,
                borderDash: month.dash || [],
                borderWidth: month.width || 2.5,
                pointRadius: month.offset === 0 ? 1.8 : 1.2,
                pointHoverRadius: 5,
                pointHitRadius: 8,
                pointBackgroundColor: month.color || '#0857c3',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 1,
                tension: 0.2,
                spanGaps: false,
                fill: false,
                order: month.offset,
                yAxisID: activeMetric.axis || 'y',
            }))
            : modalData.series.map((item) => ({
                label: item.label || item.key,
                metricKey: item.key,
                data: item.values || [],
                displayValues: item.display_values || [],
                fullPeriods: modalData.periods,
                borderColor: item.color || '#0857c3',
                backgroundColor: `${item.color || '#0857c3'}18`,
                borderWidth: 2.5,
                pointRadius: 2,
                pointHoverRadius: 5,
                pointHitRadius: 9,
                pointBackgroundColor: item.color || '#0857c3',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 1.5,
                tension: 0.22,
                spanGaps: false,
                fill: false,
                yAxisID: item.axis || 'y',
            }));
        const usesPrimaryAxis = modalData.overlay
            ? (activeMetric?.axis || 'y') === 'y'
            : modalData.series.some((item) => (item.axis || 'y') === 'y');
        const usesSecondaryAxis = modalData.overlay
            ? activeMetric?.axis === 'y1'
            : modalData.series.some((item) => item.axis === 'y1');

        this.timeseriesModalChart?.destroy();
        this.timeseriesModalChart = new window.Chart(canvas, {
            type: 'line',
            data: {
                labels: modalData.labels,
                datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                normalized: true,
                interaction: { intersect: false, mode: 'index' },
                layout: { padding: { top: 12, right: 12, bottom: 4, left: 4 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 13,
                        titleFont: { size: 14, weight: '700' },
                        bodyFont: { size: 13, weight: '600' },
                        filter: (context) => context.raw !== null && context.raw !== undefined,
                        callbacks: {
                            title: (items) => {
                                const index = items[0]?.dataIndex;
                                if (modalData.overlay) {
                                    return `Tanggal ${modalData.labels[index] || index + 1}`;
                                }
                                return this.formatTimeseriesDate(
                                    modalData.periods[index],
                                    modalData.labels[index] || '-'
                                );
                            },
                            label(context) {
                                const display = context.dataset.displayValues?.[context.dataIndex];
                                return ` ${context.dataset.label}: ${display || context.formattedValue}`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(148,163,184,0.12)' },
                        title: {
                            display: modalData.overlay,
                            text: 'Tanggal',
                            color: '#526177',
                            font: { size: 12, weight: '700' },
                        },
                        ticks: {
                            autoSkip: !modalData.overlay,
                            maxTicksLimit: modalData.overlay
                                ? 31
                                : (window.matchMedia('(max-width: 700px)').matches ? 6 : 14),
                            maxRotation: 0,
                            color: '#526177',
                            font: { size: 12, weight: '650' },
                            callback: modalData.overlay
                                ? (_value, index) => {
                                    const day = index + 1;
                                    const compact = window.matchMedia('(max-width: 700px)').matches;
                                    return !compact || day === 1 || day === 31 || day % 5 === 0
                                        ? String(day)
                                        : '';
                                }
                                : undefined,
                        },
                    },
                    y: {
                        display: usesPrimaryAxis,
                        grid: { color: 'rgba(148,163,184,0.18)' },
                        ticks: {
                            color: '#64748b',
                            font: { size: 12 },
                            callback: (value) => formatCurrency(asNumber(value) * 1_000_000),
                        },
                    },
                    y1: {
                        display: usesSecondaryAxis,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 12 },
                            callback: (value) => formatPercent(value),
                        },
                    },
                },
            },
        });
    }

    closeTimeseriesModal({ restoreFocus = true } = {}) {
        if (!this.timeseriesModal) return;

        const documentRef = this.timeseriesModal.ownerDocument || document;
        this.timeseriesModalChart?.destroy();
        this.timeseriesModalChart = null;
        this.timeseriesModalPayload = null;
        this.timeseriesModalMetricKey = null;
        this.timeseriesModal.remove();
        this.timeseriesModal = null;
        documentRef.removeEventListener('keydown', this.handleTimeseriesModalKeydown, true);
        if (!this.timeseriesModalBodyWasLocked) {
            documentRef.body.classList.remove('psd-timeseries-modal-open');
        }
        this.timeseriesModalBodyWasLocked = false;

        if (restoreFocus && this.timeseriesModalRestoreFocus?.isConnected) {
            this.timeseriesModalRestoreFocus.focus();
        }
        this.timeseriesModalRestoreFocus = null;
    }

    drawTimeseriesChart() {
        const canvas = document.getElementById('psd-timeseries-chart');
        if (!canvas || typeof window.Chart !== 'function') return;
        const scope = this.timeseriesScope();
        const groups = this.data?.timeseries?.groups || {};
        const group = groups[this.trendGroup] || groups.business || { keys: ['simpanan', 'os'] };
        const allSeries = this.timeseriesSeries(scope);
        const series = (group.keys || []).map((key) => findByKey(allSeries, key)).filter(Boolean);
        const labels = scope.labels || this.data?.timeseries?.labels || [];

        const existing = typeof window.Chart.getChart === 'function' ? window.Chart.getChart(canvas) : null;
        existing?.destroy();

        const valueLabelsPlugin = {
            id: 'psdValueLabels',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.font = '700 14px Inter, sans-serif';
                chart.data.datasets.forEach((dataset, datasetIndex) => {
                    const meta = chart.getDatasetMeta(datasetIndex);
                    meta.data.forEach((point, pointIndex) => {
                        const value = dataset.displayValues?.[pointIndex] || '';
                        const direction = (datasetIndex + pointIndex) % 2 === 0 ? -1 : 1;
                        const y = Math.min(
                            chart.chartArea.bottom - 10,
                            Math.max(chart.chartArea.top + 10, point.y + (direction * 14)),
                        );
                        ctx.fillStyle = '#0f172a';
                        ctx.lineWidth = 3;
                        ctx.strokeStyle = 'rgba(255, 255, 255, 0.95)';
                        ctx.strokeText(String(value).replace('Rp', '').trim(), point.x, y);
                        ctx.fillText(String(value).replace('Rp', '').trim(), point.x, y);
                    });
                });
                ctx.restore();
            },
        };

        this.trendChart = new window.Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: series.map((item) => ({
                    label: item.label,
                    data: item.values || [],
                    displayValues: item.display_values || [],
                    borderColor: item.color || '#0857c3',
                    backgroundColor: `${item.color || '#0857c3'}18`,
                    borderWidth: 2.5,
                    pointRadius: 3.5,
                    pointHoverRadius: 6,
                    pointBackgroundColor: item.color || '#0857c3',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5,
                    tension: 0.3,
                    fill: false,
                    yAxisID: item.axis || 'y',
                })),
            },
            plugins: [valueLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { intersect: false, mode: 'index' },
                layout: { padding: { top: 24, right: 14, bottom: 2, left: 4 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        titleFont: { size: 15, weight: '700' },
                        bodyFont: { size: 14, weight: '600' },
                        callbacks: {
                            label(context) {
                                const display = context.dataset.displayValues?.[context.dataIndex];
                                return ` ${context.dataset.label}: ${display || context.formattedValue}`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#475569',
                            font: { size: 13, weight: '600', lineHeight: 1.05 },
                            maxRotation: 0,
                            autoSkip: false,
                            callback(value) {
                                const label = String(this.getLabelForValue(value) || '').trim();
                                const parts = label.split(/\s+/);
                                return parts.length > 1 ? [parts[0], parts.slice(1).join(' ')] : label;
                            },
                        },
                    },
                    y: {
                        display: series.some((item) => (item.axis || 'y') === 'y'),
                        grid: { color: 'rgba(148,163,184,0.18)' },
                        ticks: {
                            color: '#64748b',
                            font: { size: 13 },
                            callback: (value) => formatCurrency(asNumber(value) * 1_000_000),
                        },
                    },
                    y1: {
                        display: series.some((item) => item.axis === 'y1'),
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 13 },
                            callback: (value) => formatPercent(value),
                        },
                    },
                },
            },
        });
    }

    renderClosing() {
        const cards = (this.data?.digital_strategy?.cards || []).filter((card) => card && card.available !== false);
        const strongest = [...cards].sort((a, b) => {
            const parse = (value) => Number(String(value || '0').replace(/[^\d,-]/g, '').replace('.', '').replace(',', '.')) || 0;
            return parse(b.trend) - parse(a.trend);
        })[0];
        const weakest = [...cards].sort((a, b) => {
            const parse = (value) => Number(String(value || '0').replace(/[^\d,-]/g, '').replace('.', '').replace(',', '.')) || 0;
            return parse(a.trend) - parse(b.trend);
        })[0];
        const funding = this.summaryMetric('simpanan');
        const loan = this.summaryMetric('os');
        const sml = this.summaryCard('sml');
        const npl = this.summaryCard('npl');
        const business = this.businessSnapshot();
        const savingsScope = findScope(this.data?.savings_breakdown?.scopes, this.scopeKey()) || {};
        const casa = findByKey(savingsScope.cards || [], 'casa') || {};
        const productivity = this.productivityScope();
        const rmSme = productivity?.categories?.retail_sme?.total || {};
        const rmConsumer = productivity?.categories?.retail_consumer?.total || {};
        const rmCount = asNumber(rmSme.rm_count) + asNumber(rmConsumer.rm_count);
        const rmRealization = asNumber(rmSme.realisasi_os) + asNumber(rmConsumer.realisasi_os);
        const closingKpis = [
            { label: 'Dana', value: funding.latest_fmt || formatCurrency(funding.latest), meta: `MtD ${funding.mtd_fmt || formatCurrency(funding.mtd)}`, tone: 'blue' },
            { label: 'Pinjaman', value: loan.latest_fmt || formatCurrency(loan.latest), meta: `Penc. RKA ${loan.achievement_fmt || '-'}`, tone: 'green' },
            { label: 'LDR', value: business.ldrLabel, meta: 'Pinjaman / Simpanan', tone: business.ldrRatio > 1 ? 'amber' : 'green' },
            { label: 'SML', value: sml.value || formatCurrency(sml.value_raw), meta: sml.ratio || formatPercent(sml.ratio_raw), tone: 'amber' },
            { label: 'NPL', value: npl.value || formatCurrency(npl.value_raw), meta: npl.ratio || formatPercent(npl.ratio_raw), tone: 'red' },
            { label: 'CASA', value: casa.pct || formatPercent(casa.pct_raw), meta: casa.value || formatCurrency(casa.value_raw), tone: 'cyan' },
        ];
        const actionItems = [
            {
                title: 'Funding',
                value: funding.latest_fmt || formatCurrency(funding.latest),
                text: `Jaga CASA ${casa.pct || formatPercent(casa.pct_raw)} dan pulihkan kontraksi MtD ${funding.mtd_fmt || formatCurrency(funding.mtd)}.`,
            },
            {
                title: 'Pinjaman',
                value: loan.achievement_fmt || '-',
                text: `Tutup gap RKA ${loan.gap_fmt || formatCurrency(loan.gap)} sambil menjaga kualitas akuisisi.`,
            },
            {
                title: 'SML',
                value: sml.ratio || formatPercent(sml.ratio_raw),
                text: `Curing eksposur ${sml.value || formatCurrency(sml.value_raw)} sebelum bermigrasi menjadi NPL.`,
            },
            {
                title: 'NPL',
                value: npl.ratio || formatPercent(npl.ratio_raw),
                text: `Prioritaskan recovery nominal ${npl.value || formatCurrency(npl.value_raw)} pada unit berisiko tertinggi.`,
            },
            {
                title: 'Produktivitas',
                value: `${formatInteger(rmCount)} RM`,
                text: `Realisasi RM Ritel ${formatCurrency(rmRealization)}; arahkan coaching pada basis kelolaan besar dengan realisasi rendah.`,
            },
        ];

        return `
            <div class="psd-slide psd-closing-slide">
                ${storyHeader({
                    kicker: 'Executive Closing',
                    title: 'Prioritas Aksi Berikutnya',
                    subtitle: 'Eksekusi digital dan kualitas portofolio dirangkum menjadi agenda yang dapat ditindaklanjuti.',
                    narrative: `${cards.length} indikator strategi memiliki sumber aktif. Momentum terkuat ${strongest?.title || '-'} ${strongest?.trend || ''}; perhatian utama ${weakest?.title || '-'} ${weakest?.trend || ''}.`,
                    period: this.data?.digital_strategy?.updated_at || this.period(),
                    tone: 'green',
                })}
                <main class="psd-closing-layout">
                    <section class="psd-strategy-board">
                        ${panelTitle('8 Strategi Pendukung', `${cards.length} sumber aktif`, 'fa-bullseye')}
                        <div class="psd-strategy-grid" style="--psd-strategy-count:${Math.max(1, cards.length)}">
                            ${cards.map((card, index) => {
                                const details = [
                                    {
                                        label: card.secondary_label || card.current_label || 'Pendukung',
                                        value: card.secondary_value || '-',
                                    },
                                    ...(Array.isArray(card.stats) ? card.stats.filter(Boolean).slice(0, 3) : []),
                                ];
                                return `
                                <article>
                                    <b>${String(index + 1).padStart(2, '0')}</b>
                                    <div class="psd-strategy-value"><span>${escapeHtml(card.title)}</span><strong>${escapeHtml(card.current_value || '-')}</strong></div>
                                    <em class="${String(card.trend || '').includes('-') ? 'is-negative' : 'is-positive'}">${escapeHtml(card.trend || '-')}</em>
                                    <div class="psd-strategy-detail" style="--psd-strategy-details:${Math.min(4, details.length)}">
                                        ${details.map((detail) => `
                                            <span><i>${escapeHtml(detail.label || 'Detail')}</i><strong>${escapeHtml(detail.value || '-')}</strong></span>
                                        `).join('')}
                                    </div>
                                </article>
                            `;
                            }).join('')}
                        </div>
                    </section>
                    <section class="psd-action-board">
                        ${panelTitle('Agenda Manajemen', this.scopeLabel(), 'fa-list-check')}
                        <div class="psd-closing-kpis">
                            ${closingKpis.map((item) => `
                                <article class="is-${escapeHtml(item.tone)}">
                                    <span>${escapeHtml(item.label)}</span>
                                    <strong>${escapeHtml(item.value)}</strong>
                                    <small>${escapeHtml(item.meta)}</small>
                                </article>
                            `).join('')}
                        </div>
                        <ol>
                            ${actionItems.map((item) => `
                                <li>
                                    <div><b>${escapeHtml(item.title)}</b><em>${escapeHtml(item.value)}</em></div>
                                    <span>${escapeHtml(item.text)}</span>
                                </li>
                            `).join('')}
                        </ol>
                        <div class="psd-closing-message">
                            <strong>Keputusan yang baik berangkat dari urutan pembacaan yang konsisten.</strong>
                            <span>Funding -> Pinjaman -> Kualitas -> Produktivitas -> Aksi</span>
                        </div>
                    </section>
                </main>
                ${insightStrip([
                    { label: 'Momentum digital', value: strongest?.title || '-', meta: strongest?.trend || 'Tidak ada data', tone: 'green' },
                    { label: 'Perlu intervensi', value: weakest?.title || '-', meta: weakest?.trend || 'Tidak ada data', tone: 'red' },
                    { label: 'Cakupan keputusan', value: this.scopeLabel(), meta: `Posisi ${this.period()}` },
                ])}
            </div>
        `;
    }
}
