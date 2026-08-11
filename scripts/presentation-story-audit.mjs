import { spawn } from 'node:child_process';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));
const baseUrl = (process.env.AUDIT_BASE_URL || 'http://127.0.0.1:8137').replace(/\/$/, '');
const pn = process.env.AUDIT_PN || '';
const password = process.env.AUDIT_PASSWORD || '';
const period = process.env.AUDIT_PERIOD || '2026-07-23';
const usePrognosa = ['1', 'true', 'yes', 'on'].includes(String(process.env.AUDIT_PROGNOSA || '').toLowerCase());
const renderTimeout = Number(process.env.AUDIT_RENDER_TIMEOUT || 120000);
const expectedSlideCount = 15;
const requestedSlideIndexes = String(process.env.AUDIT_SLIDE_FILTER || '')
    .split(',')
    .map((value) => Number.parseInt(value.trim(), 10))
    .filter((value, index, values) => Number.isInteger(value)
        && value >= 0
        && value < expectedSlideCount
        && values.indexOf(value) === index);
const auditedSlideIndexes = requestedSlideIndexes.length
    ? requestedSlideIndexes
    : Array.from({ length: expectedSlideCount }, (_value, index) => index);
const outputDir = path.resolve(process.env.AUDIT_OUTPUT_DIR || 'storage/framework/testing/presentation-story-audit');
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const allViewports = [
    { name: 'desktop-hd', width: 1920, height: 1080, branch: false, screenshots: false },
    { name: 'desktop', width: 1872, height: 930, branch: true, screenshots: true },
    { name: 'laptop', width: 1366, height: 768, branch: false, screenshots: false },
    { name: 'tablet-landscape', width: 1194, height: 834, branch: false, screenshots: false },
    { name: 'tablet-portrait', width: 834, height: 1194, branch: true, screenshots: true },
    { name: 'phone-landscape', width: 844, height: 390, branch: false, screenshots: false },
    { name: 'phone-portrait', width: 390, height: 844, branch: false, screenshots: false },
];
const viewportFilter = String(process.env.AUDIT_VIEWPORT_FILTER || '')
    .split(',')
    .map((value) => value.trim())
    .filter(Boolean);
const viewports = viewportFilter.length
    ? allViewports.filter((viewport) => viewportFilter.includes(viewport.name))
    : allViewports;

if (!pn || !password) {
    throw new Error('AUDIT_PN dan AUDIT_PASSWORD wajib diisi.');
}

function slug(value) {
    return String(value || 'slide')
        .replace(/[^a-z0-9]+/gi, '-')
        .replace(/^-|-$/g, '')
        .toLowerCase();
}

async function waitForEndpoint(url, timeout = 15000) {
    const startedAt = Date.now();
    while (Date.now() - startedAt < timeout) {
        try {
            const response = await fetch(url);
            if (response.ok) return response.json();
        } catch (_) {
        }
        await sleep(150);
    }
    throw new Error(`Chrome DevTools endpoint tidak siap: ${url}`);
}

class CdpClient {
    constructor(webSocketUrl) {
        this.nextId = 1;
        this.pending = new Map();
        this.events = new Map();
        this.socket = new WebSocket(webSocketUrl);
    }

    async connect() {
        await new Promise((resolve, reject) => {
            this.socket.addEventListener('open', resolve, { once: true });
            this.socket.addEventListener('error', reject, { once: true });
        });
        this.socket.addEventListener('message', async (event) => {
            let raw = event.data;
            if (raw instanceof Blob) raw = await raw.text();
            if (raw instanceof ArrayBuffer) raw = new TextDecoder().decode(raw);
            const payload = JSON.parse(String(raw));
            if (payload.id && this.pending.has(payload.id)) {
                const { resolve, reject } = this.pending.get(payload.id);
                this.pending.delete(payload.id);
                payload.error ? reject(new Error(payload.error.message)) : resolve(payload.result || {});
                return;
            }
            (this.events.get(payload.method) || []).forEach((listener) => listener(payload.params || {}));
        });
    }

    send(method, params = {}) {
        const id = this.nextId++;
        const promise = new Promise((resolve, reject) => this.pending.set(id, { resolve, reject }));
        this.socket.send(JSON.stringify({ id, method, params }));
        return promise;
    }

    on(method, listener) {
        this.events.set(method, [...(this.events.get(method) || []), listener]);
    }

    close() {
        this.socket.close();
    }
}

async function evaluate(client, expression) {
    const response = await client.send('Runtime.evaluate', {
        expression,
        awaitPromise: true,
        returnByValue: true,
    });
    if (response.exceptionDetails) {
        throw new Error(response.exceptionDetails.exception?.description || response.exceptionDetails.text || 'Runtime.evaluate gagal');
    }
    return response.result?.value;
}

async function waitFor(client, predicate, timeout = 60000) {
    const startedAt = Date.now();
    while (Date.now() - startedAt < timeout) {
        if (await evaluate(client, `Boolean(${predicate})`).catch(() => false)) {
            return;
        }
        await sleep(180);
    }
    throw new Error(`Timeout menunggu kondisi: ${predicate}`);
}

async function navigate(client, url) {
    await client.send('Page.navigate', { url });
    const expectedPath = JSON.stringify(new URL(String(url)).pathname);
    try {
        await waitFor(
            client,
            `location.pathname === ${expectedPath} && ['interactive', 'complete'].includes(document.readyState) && Boolean(document.body)`,
            60000,
        );
    } catch (error) {
        const state = await evaluate(client, `({ href: location.href, readyState: document.readyState, title: document.title })`)
            .catch(() => null);
        throw new Error(`${error.message}; navigation=${JSON.stringify(state)}`);
    }
}

const slideAuditExpression = (index, scopeLabel) => `(() => {
    const index = ${Number(index)};
    const slide = document.getElementById('pres-slide-' + index);
    const active = document.querySelector('.apple-slide.active');
    if (!slide || active !== slide) return { fatal: 'Slide aktif tidak sesuai.' };
    const shell = slide.querySelector('.psd-slide, .psd-cover');
    const slideRect = slide.getBoundingClientRect();
    const shellRect = shell?.getBoundingClientRect();
    const stageRect = document.querySelector('.pres-slides-container')?.getBoundingClientRect();
    const visible = (element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.display !== 'none'
            && style.visibility !== 'hidden'
            && Number(style.opacity || 1) !== 0
            && rect.width > 1
            && rect.height > 1;
    };
    const selector = (element) => {
        if (element.id) return '#' + element.id;
        const classes = Array.from(element.classList || []).slice(0, 3).join('.');
        return element.tagName.toLowerCase() + (classes ? '.' + classes : '');
    };
    const textClips = Array.from(slide.querySelectorAll('h1,h2,h3,p,span,strong,small,th,td,label,button'))
        .filter(visible)
        .filter((element) => {
            const style = getComputedStyle(element);
            const clippedWidth = element.scrollWidth > element.clientWidth + 2;
            const clippedHeight = element.scrollHeight > element.clientHeight + 2;
            const intentionallyScrollable = Boolean(element.closest('.psd-v2-comparison-scroll,.psd-table-scroll,.pres-table-scroll'));
            const iconOnly = !String(element.textContent || '').trim();
            return !intentionallyScrollable && !iconOnly && (
                (clippedWidth && ['hidden', 'clip'].includes(style.overflowX))
                || (clippedHeight && ['hidden', 'clip'].includes(style.overflowY))
            );
        })
        .slice(0, 30)
        .map((element) => ({
            selector: selector(element),
            text: String(element.textContent || '').trim().slice(0, 100),
            client: [element.clientWidth, element.clientHeight],
            scroll: [element.scrollWidth, element.scrollHeight],
        }));
    const outside = Array.from(slide.querySelectorAll('.psd-header,.psd-main,.psd-v2-data-main,.psd-insight-strip'))
        .filter(visible)
        .filter((element) => {
            const rect = element.getBoundingClientRect();
            return rect.left < slideRect.left - 2
                || rect.top < slideRect.top - 2
                || rect.right > slideRect.right + 2
                || rect.bottom > slideRect.bottom + 2;
        })
        .map((element) => selector(element));
    const outsideText = Array.from(slide.querySelectorAll('h1,h2,h3,p,span,strong,small,th,td,label,button'))
        .filter(visible)
        .filter((element) => {
            const rect = element.getBoundingClientRect();
            return rect.left < slideRect.left - 2
                || rect.top < slideRect.top - 2
                || rect.right > slideRect.right + 2
                || rect.bottom > slideRect.bottom + 2;
        })
        .slice(0, 30)
        .map((element) => ({
            selector: selector(element),
            text: String(element.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 100),
            rect: Array.from(element.getBoundingClientRect().toJSON
                ? Object.values(element.getBoundingClientRect().toJSON()).slice(0, 4)
                : [element.getBoundingClientRect().left, element.getBoundingClientRect().top, element.getBoundingClientRect().width, element.getBoundingClientRect().height]),
        }));
    const emptyPanels = Array.from(slide.querySelectorAll(
        '.psd-v2-table-panel,.psd-v2-support-split > section,.psd-panel,.psd-v2-strategy-column,.psd-v2-agenda-list'
    ))
        .filter(visible)
        .filter((element) => {
            const rect = element.getBoundingClientRect();
            const meaningful = String(element.textContent || '').replace(/\\s+/g, ' ').trim().length > 18
                || element.querySelector('svg,canvas,img,table,.psd-v2-trend-svg');
            return rect.width * rect.height > slideRect.width * slideRect.height * 0.08 && !meaningful;
        })
        .map((element) => selector(element));
    const reading = slide.querySelector('.psd-reading');
    const readingClip = reading
        ? reading.scrollWidth > reading.clientWidth + 2 || reading.scrollHeight > reading.clientHeight + 2
        : false;
    const clippedTableRows = Array.from(slide.querySelectorAll('.psd-v2-comparison-table tbody tr'))
        .filter((row) => {
            const wrapper = row.closest('.psd-v2-comparison-wrap');
            if (!wrapper) return false;
            const rowRect = row.getBoundingClientRect();
            const wrapperRect = wrapper.getBoundingClientRect();
            return rowRect.top < wrapperRect.top - 1 || rowRect.bottom > wrapperRect.bottom + 1;
        })
        .map((row) => String(row.textContent || '').replace(/\\s+/g, ' ').trim().slice(0, 100));
    const layoutOverlaps = Array.from(slide.querySelectorAll(
        '.psd-v2-support-split,.psd-micro-support-grid,.psd-micro-kpi-grid,.psd-micro-branch-head,.psd-micro-branch-row,.psd-insight-strip,.psd-v2-agenda-list,.psd-v2-data-main'
    ))
        .filter(visible)
        .flatMap((container) => {
            const children = Array.from(container.children)
                .filter(visible)
                .filter((element) => {
                    const style = getComputedStyle(element);
                    return !['absolute', 'fixed'].includes(style.position);
                });
            const overlaps = [];
            children.forEach((left, leftIndex) => {
                const leftRect = left.getBoundingClientRect();
                children.slice(leftIndex + 1).forEach((right) => {
                    const rightRect = right.getBoundingClientRect();
                    const overlapWidth = Math.min(leftRect.right, rightRect.right)
                        - Math.max(leftRect.left, rightRect.left);
                    const overlapHeight = Math.min(leftRect.bottom, rightRect.bottom)
                        - Math.max(leftRect.top, rightRect.top);
                    if (overlapWidth > 1 && overlapHeight > 1) {
                        overlaps.push({
                            container: selector(container),
                            left: selector(left),
                            right: selector(right),
                            overlap: [
                                Math.round(overlapWidth * 10) / 10,
                                Math.round(overlapHeight * 10) / 10,
                            ],
                        });
                    }
                });
            });
            return overlaps;
        })
        .slice(0, 40);
    const comparisonTable = slide.querySelector('.psd-v2-comparison-table');
    const hasPrognosa = Boolean(comparisonTable?.classList.contains('has-prognosa'));
    const rkaCellIndex = hasPrognosa ? 16 : 14;
    const expectedCellCount = hasPrognosa ? 19 : 17;
    const rkaCells = Array.from(slide.querySelectorAll('.psd-v2-comparison-table tbody tr'))
        .map((row) => {
            const cells = Array.from(row.cells || []);
            if (cells.length < expectedCellCount) return null;
            return {
                row: [
                    String(cells[0]?.textContent || '').trim(),
                    String(cells[1]?.textContent || '').trim(),
                ].filter(Boolean).join(' / '),
                value: String(cells[rkaCellIndex]?.textContent || '').trim(),
            };
        })
        .filter(Boolean);
    const missingRkaCells = rkaCells.filter((cell) => !cell.value || cell.value === '-');
    const fontSizes = Array.from(slide.querySelectorAll(
        '.psd-v2-comparison-table td,.psd-v2-comparison-table th,.psd-reading p,.psd-insight strong,.psd-v2-strategy-node'
    ))
        .filter(visible)
        .map((element) => Number.parseFloat(getComputedStyle(element).fontSize))
        .filter(Number.isFinite);
    const audit = window.__presentationLayoutAudit?.();
    const ownAudit = audit?.slides?.[index] || {};
    return {
        index,
        story: slide.dataset.storyKey || '',
        scope: ${JSON.stringify(scopeLabel)},
        logicalSize: [slide.clientWidth, slide.clientHeight],
        scrollSize: [slide.scrollWidth, slide.scrollHeight],
        rendered: Boolean(shell && String(shell.textContent || '').trim().length > 60),
        busy: slide.getAttribute('aria-busy') === 'true' || slide.classList.contains('is-section-loading'),
        overflowX: Boolean(ownAudit.overflowX),
        overflowY: Boolean(ownAudit.overflowY),
        readingClip,
        clippedTableRows,
        layoutOverlaps,
        textClips,
        outside,
        outsideText,
        emptyPanels,
        minDataFont: fontSizes.length ? Math.min(...fontSizes) : null,
        shellWithinSlide: Boolean(shellRect
            && shellRect.left >= slideRect.left - 2
            && shellRect.top >= slideRect.top - 2
            && shellRect.right <= slideRect.right + 2
            && shellRect.bottom <= slideRect.bottom + 2),
        stageHorizontalGap: stageRect
            ? Math.max(0, slideRect.left - stageRect.left) + Math.max(0, stageRect.right - slideRect.right)
            : null,
        viewportHorizontalGap: Math.max(0, slideRect.left)
            + Math.max(0, window.innerWidth - slideRect.right),
        runtimeViewport: [window.innerWidth, window.innerHeight, window.devicePixelRatio],
        renderedSlideRect: [
            Math.round(slideRect.left),
            Math.round(slideRect.top),
            Math.round(slideRect.width),
            Math.round(slideRect.height),
        ],
        stageCss: {
            width: getComputedStyle(document.getElementById('apple-presentation-mode')).getPropertyValue('--pres-slide-width').trim(),
            scale: getComputedStyle(document.getElementById('apple-presentation-mode')).getPropertyValue('--pres-stage-scale').trim(),
        },
        prognosaExpected: ${JSON.stringify(usePrognosa)},
        prognosaEnabled: Boolean(document.getElementById('pres-prognosa-toggle')?.checked),
        prognosaColumns: hasPrognosa
            ? Array.from(comparisonTable.querySelectorAll('tbody tr:first-child td.is-prognosa'))
                .map((cell) => String(cell.textContent || '').replace(/\\s+/g, ' ').trim())
            : [],
        prognosaValid: !${JSON.stringify(usePrognosa)}
            || !comparisonTable
            || (hasPrognosa
                && Array.from(comparisonTable.querySelectorAll('tbody tr'))
                    .every((row) => row.cells.length === 19)
                && Array.from(comparisonTable.querySelectorAll('tbody tr:first-child td.is-prognosa')).length === 2),
        comparisonColumns: slide.querySelectorAll('.psd-v2-comparison-table thead th').length,
        comparisonRows: slide.querySelectorAll('.psd-v2-comparison-table tbody tr').length,
        rkaCells,
        missingRkaCells,
        emptyStates: Array.from(slide.querySelectorAll('.psd-empty')).filter(visible).map((element) => String(element.textContent || '').trim()),
        viewportOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
    };
})()`;

async function activateSlide(client, index) {
    await evaluate(client, `(() => {
        const dot = document.querySelectorAll('.pres-dot')[${index}];
        if (!dot) return false;
        dot.click();
        return true;
    })()`);
    try {
        await waitFor(
            client,
            `document.getElementById('pres-slide-${index}')?.classList.contains('active')
                && !document.getElementById('pres-slide-${index}')?.classList.contains('is-section-loading')
                && document.getElementById('pres-slide-${index}')?.getAttribute('aria-busy') !== 'true'
                && document.getElementById('pres-slide-${index}')?.querySelector('.psd-slide, .psd-cover')`,
            Math.max(90000, renderTimeout),
        );
    } catch (error) {
        const state = await evaluate(client, `(() => {
            const slide = document.getElementById('pres-slide-${index}');
            return {
                activeSlide: document.querySelector('.apple-slide.active')?.id || null,
                classes: slide?.className || null,
                ariaBusy: slide?.getAttribute('aria-busy') || null,
                hasShell: Boolean(slide?.querySelector('.psd-slide, .psd-cover')),
                text: String(slide?.innerText || '').trim().slice(0, 240),
                html: String(slide?.innerHTML || '').trim().slice(0, 240),
            };
        })()`);
        throw new Error(`${error.message}; slideState=${JSON.stringify(state)}`);
    }
    await evaluate(client, `(async () => {
        if (document.fonts?.ready) await document.fonts.ready;
        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        return true;
    })()`);
    await sleep(index === 13 ? 850 : 350);
}

async function capture(client, filename) {
    const result = await client.send('Page.captureScreenshot', {
        format: 'png',
        fromSurface: true,
        captureBeyondViewport: false,
    });
    await writeFile(path.join(outputDir, filename), Buffer.from(result.data, 'base64'));
}

async function verifyFundingProductSelector(client) {
    const target = await evaluate(client, `(() => {
        const buttons = Array.from(document.querySelectorAll('#pres-slide-5 [data-psd-funding-product]'));
        const button = buttons.find((item) => item.dataset.psdFundingProduct === 'tabungan')
            || buttons.find((item) => !item.classList.contains('active'));
        if (!button) return null;
        button.click();
        return button.dataset.psdFundingProduct;
    })()`);

    if (!target) {
        return { available: false, valid: false };
    }

    await waitFor(
        client,
        `document.querySelector('#pres-slide-5 [data-psd-funding-product="${target}"]')?.classList.contains('active')`,
        10000,
    );

    return evaluate(client, `(() => {
        const slide = document.getElementById('pres-slide-5');
        const active = slide?.querySelector('[data-psd-funding-product].active');
        const title = String(slide?.querySelector('.psd-v2-trend-panel .psd-panel-title span')?.textContent || '').trim();
        const insight = String(slide?.querySelector('.psd-insight-strip strong')?.textContent || '').trim();
        const expected = String(active?.textContent || '').trim();
        return {
            available: true,
            activeKey: active?.dataset.psdFundingProduct || null,
            activeLabel: expected,
            title,
            insight,
            valid: Boolean(expected && title.toLowerCase().includes(expected.toLowerCase()) && insight === expected),
        };
    })()`);
}

async function verifyMicroViewSelector(client) {
    const initial = await evaluate(client, `(() => {
        const slide = document.getElementById('pres-slide-10');
        const button = slide?.querySelector('[data-psd-micro-view="extreme_low"]');
        const pdwkTable = slide?.querySelector('.psd-micro-branch-table.is-pdwk');
        const activeTable = slide?.querySelector('.psd-micro-branch-table.is-extreme-low');
        const tableCells = Array.from(slide?.querySelectorAll('.psd-micro-branch-table .psd-micro-branch-row > *') || []);
        const minimumPadding = tableCells.length
            ? Math.min(...tableCells.map((cell) => {
                const style = getComputedStyle(cell);
                return Math.min(parseFloat(style.paddingLeft) || 0, parseFloat(style.paddingRight) || 0);
            }))
            : 0;
        const rowOverlaps = Array.from(slide?.querySelectorAll('.psd-micro-branch-row') || [])
            .some((row) => {
                const cells = Array.from(row.children).map((cell) => cell.getBoundingClientRect());
                return cells.some((rect, index) => index > 0 && rect.left < cells[index - 1].right - 0.5);
            });
        return {
            available: Boolean(button),
            active: Boolean(button?.classList.contains('is-active')),
            title: String(slide?.querySelector('.psd-micro-support-grid > section:nth-child(2) .psd-panel-title span')?.textContent || '').trim(),
            rows: activeTable?.querySelectorAll('.psd-micro-branch-row').length || 0,
            pdwkRows: pdwkTable?.querySelectorAll('.psd-micro-branch-row').length || 0,
            pdwkColumns: Array.from(pdwkTable?.querySelectorAll('.psd-micro-branch-head > span') || [])
                .map((cell) => String(cell.textContent || '').trim()),
            categoryColumns: Array.from(activeTable?.querySelectorAll('.psd-micro-branch-head > span') || [])
                .map((cell) => String(cell.textContent || '').trim()),
            assistanceRemoved: !String(slide?.textContent || '').includes('Unit Pemutus Perlu Asistensi'),
            minimumPadding,
            rowOverlaps,
        };
    })()`);
    if (!initial.available) {
        return { available: false, valid: false };
    }

    await evaluate(client, `document.querySelector('#pres-slide-10 [data-psd-micro-view="rm_kur"]')?.click()`);
    await waitFor(
        client,
        "document.querySelector('#pres-slide-10 [data-psd-micro-view=\"rm_kur\"]')?.classList.contains('is-active')",
        10000,
    );
    const alternate = await evaluate(client, `(() => {
        const slide = document.getElementById('pres-slide-10');
        const table = slide?.querySelector('.psd-micro-branch-table.is-tiering');
        const rowOverlaps = Array.from(table?.querySelectorAll('.psd-micro-branch-row') || [])
            .some((row) => {
                const cells = Array.from(row.children).map((cell) => cell.getBoundingClientRect());
                return cells.some((rect, index) => index > 0 && rect.left < cells[index - 1].right - 0.5);
            });
        return {
            title: String(slide?.querySelector('.psd-micro-support-grid > section:nth-child(2) .psd-panel-title span')?.textContent || '').trim(),
            rows: table?.querySelectorAll('.psd-micro-branch-row').length || 0,
            columns: Array.from(table?.querySelectorAll('.psd-micro-branch-head > span') || [])
                .map((cell) => String(cell.textContent || '').trim()),
            rowOverlaps,
        };
    })()`);

    await evaluate(client, `document.querySelector('#pres-slide-10 [data-psd-micro-view="extreme_low"]')?.click()`);
    await waitFor(
        client,
        "document.querySelector('#pres-slide-10 [data-psd-micro-view=\"extreme_low\"]')?.classList.contains('is-active')",
        10000,
    );

    return {
        available: true,
        initial,
        alternate,
        valid: initial.active
            && initial.title.includes('Kategori Mantri per Cabang')
            && initial.rows > 0
            && initial.pdwkRows > 0
            && initial.pdwkColumns.join('|') === 'Cabang|Putusan KA Unit|Putusan MBM|Putusan BOH|Total Realisasi'
            && initial.categoryColumns.join('|') === 'Cabang|Extreme Low|Low|Total ≤ 800 Jt|Mid|High'
            && initial.assistanceRemoved
            && initial.minimumPadding >= 5
            && !initial.rowOverlaps
            && alternate.title.includes('RM Mikro KUR per Tiering dan Cabang')
            && alternate.rows > 0
            && alternate.columns.length === 4
            && !alternate.rowOverlaps,
    };
}

async function verifyTimeseriesModal(client, selector, screenshotName = null) {
    await waitFor(
        client,
        `Boolean(document.querySelector(${JSON.stringify(selector)}))`,
        30000,
    ).catch(() => {});
    const trigger = await evaluate(client, `(() => {
        const target = document.querySelector(${JSON.stringify(selector)});
        if (!target) return null;
        return {
            keys: String(target.dataset.psdTimeseriesKeys || '').split(',').map((key) => key.trim()).filter(Boolean),
            title: String(target.dataset.psdTimeseriesTitle || target.getAttribute('aria-label') || '').trim(),
        };
    })()`);
    if (!trigger) {
        return { available: false, valid: false };
    }

    await evaluate(client, `(() => {
        const target = document.querySelector(${JSON.stringify(selector)});
        target?.dispatchEvent(new MouseEvent('dblclick', { bubbles: true, cancelable: true }));
    })()`);
    await waitFor(client, "Boolean(document.querySelector('[data-psd-timeseries-modal] [role=\"dialog\"]'))", 10000);
    await sleep(350);

    const opened = await evaluate(client, `(() => {
        const modal = document.querySelector('[data-psd-timeseries-modal]');
        const dialog = modal?.querySelector('[role="dialog"]');
        const rect = dialog?.getBoundingClientRect();
        const status = String(modal?.querySelector('.psd-timeseries-dialog-status')?.textContent || '').trim();
        const canvas = modal?.querySelector('#psd-timeseries-modal-chart');
        const chart = canvas && typeof window.Chart?.getChart === 'function'
            ? window.Chart.getChart(canvas)
            : null;
        const datasets = Array.from(chart?.data?.datasets || []);
        const labels = Array.from(chart?.data?.labels || []).map(String);
        const monthKeys = datasets.map((dataset) => String(dataset.monthKey || ''));
        const activeMetric = String(modal?.dataset.psdTimeseriesActiveMetric || '');
        const monthSequenceValid = monthKeys.length === 4 && monthKeys.every((key, index) => {
            const [year, month] = monthKeys[0].split('-').map(Number);
            const expected = new Date(Date.UTC(year, month - 1 - index, 1));
            return key === [
                expected.getUTCFullYear(),
                String(expected.getUTCMonth() + 1).padStart(2, '0'),
            ].join('-');
        });
        const calendarNullsValid = datasets.every((dataset) => {
            const [year, month] = String(dataset.monthKey || '').split('-').map(Number);
            const daysInMonth = new Date(Date.UTC(year, month, 0)).getUTCDate();
            return Array.from(dataset.data || []).every((value, index) => (
                index < daysInMonth || value === null
            ));
        });
        const latestDataset = datasets[0];
        const latestActualDays = Array.from(latestDataset?.actualDates || [])
            .map((date) => Number(String(date || '').slice(-2)))
            .filter(Number.isFinite);
        const latestAvailableDay = latestActualDays.length ? Math.max(...latestActualDays) : 0;
        const futureDatesNull = Array.from(latestDataset?.data || [])
            .every((value, index) => index < latestAvailableDay || value === null);
        const modalLayoutOverlaps = Array.from(modal?.querySelectorAll(
            '.psd-timeseries-dialog-header,.psd-timeseries-dialog-footer'
        ) || []).flatMap((container) => {
            const children = Array.from(container.children).filter((element) => {
                const style = getComputedStyle(element);
                const childRect = element.getBoundingClientRect();
                return style.display !== 'none'
                    && style.visibility !== 'hidden'
                    && childRect.width > 1
                    && childRect.height > 1
                    && !['absolute', 'fixed'].includes(style.position);
            });
            const overlaps = [];
            children.forEach((left, leftIndex) => {
                const leftRect = left.getBoundingClientRect();
                children.slice(leftIndex + 1).forEach((right) => {
                    const rightRect = right.getBoundingClientRect();
                    const overlapWidth = Math.min(leftRect.right, rightRect.right)
                        - Math.max(leftRect.left, rightRect.left);
                    const overlapHeight = Math.min(leftRect.bottom, rightRect.bottom)
                        - Math.max(leftRect.top, rightRect.top);
                    if (overlapWidth > 1 && overlapHeight > 1) {
                        overlaps.push([left.className || left.tagName, right.className || right.tagName]);
                    }
                });
            });
            return overlaps;
        });
        const modalTextClips = Array.from(modal?.querySelectorAll('h2,p,span,strong,button') || [])
            .filter((element) => {
                const style = getComputedStyle(element);
                const text = String(element.textContent || '').trim();
                const rect = element.getBoundingClientRect();
                return text
                    && style.display !== 'none'
                    && style.visibility !== 'hidden'
                    && rect.width > 2
                    && rect.height > 2
                    && ((element.scrollWidth > element.clientWidth + 2
                            && ['hidden', 'clip'].includes(style.overflowX))
                        || (element.scrollHeight > element.clientHeight + 2
                            && ['hidden', 'clip'].includes(style.overflowY)));
            })
            .map((element) => String(element.textContent || '').trim().slice(0, 80));
        return {
            chartId: chart?.id ?? null,
            role: dialog?.getAttribute('role') || null,
            ariaModal: dialog?.getAttribute('aria-modal') || null,
            hasCanvas: Boolean(canvas),
            hasClose: Boolean(modal?.querySelector('[data-psd-timeseries-close]')),
            daily: modal?.querySelector('.psd-timeseries-dialog-status')?.classList.contains('is-daily') || false,
            status,
            title: String(modal?.querySelector('#psd-timeseries-modal-title')?.textContent || '').trim(),
            activeMetric,
            labels,
            datasetKeys: datasets.map((dataset) => String(dataset.metricKey || '')),
            datasetLabels: datasets.map((dataset) => String(dataset.label || '')),
            monthKeys,
            monthSequenceValid,
            calendarNullsValid,
            latestAvailableDay,
            futureDatesNull,
            datasetLengths: datasets.map((dataset) => Array.from(dataset.data || []).length),
            displayLengths: datasets.map((dataset) => Array.from(dataset.displayValues || []).length),
            actualDateLengths: datasets.map((dataset) => Array.from(dataset.actualDates || []).length),
            spanGaps: datasets.map((dataset) => dataset.spanGaps),
            colors: datasets.map((dataset) => String(dataset.borderColor || '')),
            legendMonths: Array.from(modal?.querySelectorAll('[data-psd-timeseries-month]') || [])
                .map((item) => String(item.dataset.psdTimeseriesMonth || '')),
            selectorKeys: Array.from(modal?.querySelectorAll('[data-psd-timeseries-metric]') || [])
                .map((button) => String(button.dataset.psdTimeseriesMetric || '')),
            modalLayoutOverlaps,
            modalTextClips,
            withinViewport: Boolean(rect
                && rect.left >= 0
                && rect.top >= 0
                && rect.right <= window.innerWidth
                && rect.bottom <= window.innerHeight),
            overflowX: Boolean(dialog && dialog.scrollWidth > dialog.clientWidth + 1),
            overflowY: Boolean(dialog && dialog.scrollHeight > dialog.clientHeight + 1),
        };
    })()`);
    let switched = null;
    if (opened.selectorKeys.length > 1) {
        const nextMetric = opened.selectorKeys[1];
        await evaluate(client, `document.querySelector('[data-psd-timeseries-metric="${nextMetric}"]')?.click()`);
        await waitFor(
            client,
            `document.querySelector('[data-psd-timeseries-modal]')?.dataset.psdTimeseriesActiveMetric === ${JSON.stringify(nextMetric)}`,
            10000,
        );
        await sleep(100);
        switched = await evaluate(client, `(() => {
            const modal = document.querySelector('[data-psd-timeseries-modal]');
            const canvas = modal?.querySelector('#psd-timeseries-modal-chart');
            const chart = canvas && typeof window.Chart?.getChart === 'function'
                ? window.Chart.getChart(canvas)
                : null;
            const datasets = Array.from(chart?.data?.datasets || []);
            return {
                chartId: chart?.id ?? null,
                activeMetric: String(modal?.dataset.psdTimeseriesActiveMetric || ''),
                datasetKeys: datasets.map((dataset) => String(dataset.metricKey || '')),
                monthKeys: datasets.map((dataset) => String(dataset.monthKey || '')),
                lengths: datasets.map((dataset) => Array.from(dataset.data || []).length),
            };
        })()`);
    }
    if (screenshotName) {
        await capture(client, screenshotName);
    }

    await evaluate(client, `document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
    await waitFor(client, "!document.querySelector('[data-psd-timeseries-modal]')", 10000);

    return {
        available: true,
        trigger,
        opened,
        switched,
        closedWithEscape: true,
        valid: opened.role === 'dialog'
            && opened.ariaModal === 'true'
            && opened.hasCanvas
            && opened.hasClose
            && opened.daily
            && opened.status.includes('4 garis bulanan')
            && opened.labels.length === 31
            && opened.labels[0] === '1'
            && opened.labels[30] === '31'
            && opened.datasetKeys.length === 4
            && opened.datasetKeys.every((key) => key === opened.activeMetric)
            && (!trigger.keys.length || trigger.keys.includes(opened.activeMetric))
            && new Set(opened.monthKeys).size === 4
            && opened.monthSequenceValid
            && opened.calendarNullsValid
            && opened.futureDatesNull
            && opened.datasetLengths.every((length) => length === 31)
            && opened.displayLengths.every((length) => length === 31)
            && opened.actualDateLengths.every((length) => length === 31)
            && opened.spanGaps.every((value) => value === false)
            && new Set(opened.colors).size === 4
            && opened.legendMonths.length === 4
            && opened.modalLayoutOverlaps.length === 0
            && opened.modalTextClips.length === 0
            && (!switched
                || (switched.chartId !== opened.chartId
                    && switched.activeMetric === opened.selectorKeys[1]
                    && switched.datasetKeys.length === 4
                    && switched.datasetKeys.every((key) => key === switched.activeMetric)
                    && switched.monthKeys.length === 4
                    && switched.lengths.every((length) => length === 31)))
            && opened.withinViewport
            && !opened.overflowX
            && !opened.overflowY,
    };
}

async function auditScope(client, viewport, scopeKey, scopeLabel, takeScreenshots) {
    if (scopeKey !== 'area6') {
        await evaluate(client, `(() => {
            const select = document.getElementById('pres-global-scope-selector');
            select.value = ${JSON.stringify(scopeKey)};
            select.dispatchEvent(new Event('change', { bubbles: true }));
        })()`);
        await waitFor(client, `new URL(location.href).searchParams.get('scope') === ${JSON.stringify(scopeKey)}`, 30000);
        await sleep(500);
    }

    const results = [];
    const timeseriesSelectors = {
        4: '#pres-slide-4 .psd-v2-trend-chart[data-psd-timeseries-expand]',
        5: '#pres-slide-5 .psd-v2-trend-chart[data-psd-timeseries-expand]',
        7: '#pres-slide-7 .psd-v2-trend-chart[data-psd-timeseries-expand]',
        8: '#pres-slide-8 .psd-v2-trend-chart[data-psd-timeseries-expand]',
        9: '#pres-slide-9 .psd-v2-trend-chart[data-psd-timeseries-expand]',
        11: '#pres-slide-11 .psd-v2-trend-chart[data-psd-timeseries-expand]',
        12: '#pres-slide-12 .psd-v2-trend-chart[data-psd-timeseries-expand]',
        13: '#pres-slide-13 [data-psd-timeseries-expand]',
    };
    for (const index of auditedSlideIndexes) {
        await activateSlide(client, index);
        const audit = await evaluate(client, slideAuditExpression(index, scopeLabel));
        if (index === 5) {
            audit.fundingProductSelector = await verifyFundingProductSelector(client);
        }
        if (index === 10) {
            audit.microViewSelector = await verifyMicroViewSelector(client);
        }
        if (timeseriesSelectors[index]) {
            audit.timeseriesModal = await verifyTimeseriesModal(
                client,
                timeseriesSelectors[index],
                takeScreenshots && [4, 7, 11, 12, 13].includes(index)
                    ? `${viewport.name}--${slug(scopeLabel)}--slide-${String(index + 1).padStart(2, '0')}--timeseries-modal.png`
                    : null,
            );
        }
        audit.viewport = viewport.name;
        audit.viewportSize = [viewport.width, viewport.height];
        results.push(audit);
        if (takeScreenshots) {
            await capture(client, `${viewport.name}--${slug(scopeLabel)}--slide-${String(index + 1).padStart(2, '0')}.png`);
        }
    }
    return results;
}

await mkdir(outputDir, { recursive: true });
const profileDir = path.join(os.tmpdir(), `project-abah-presentation-audit-${process.pid}`);
const port = 9400 + Math.floor(Math.random() * 300);
const chrome = spawn(chromePath, [
    '--headless=new',
    '--disable-gpu',
    '--disable-extensions',
    '--disable-background-networking',
    '--disable-dev-shm-usage',
    '--no-sandbox',
    '--no-first-run',
    '--no-default-browser-check',
    '--remote-allow-origins=*',
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${profileDir}`,
    'about:blank',
], { stdio: 'ignore', windowsHide: true });

let client;
try {
    await waitForEndpoint(`http://127.0.0.1:${port}/json/version`);
    const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json();
    client = new CdpClient(target.webSocketDebuggerUrl);
    await client.connect();
    await Promise.all([
        client.send('Page.enable'),
        client.send('Runtime.enable'),
        client.send('Network.enable'),
    ]);

    const runtimeErrors = [];
    client.on('Runtime.exceptionThrown', ({ exceptionDetails }) => {
        runtimeErrors.push(exceptionDetails?.exception?.description || exceptionDetails?.text || 'JavaScript exception');
    });

    await navigate(client, `${baseUrl}/login`);
    const submitted = await evaluate(client, `(() => {
        const pn = document.querySelector('input[name="pn"]');
        const password = document.querySelector('input[name="password"]');
        const form = pn?.form;
        if (!pn || !password || !form) return false;
        pn.value = ${JSON.stringify(pn)};
        password.value = ${JSON.stringify(password)};
        pn.dispatchEvent(new Event('input', { bubbles: true }));
        password.dispatchEvent(new Event('input', { bubbles: true }));
        form.requestSubmit();
        return true;
    })()`);
    if (!submitted) throw new Error('Form login tidak ditemukan.');
    await waitFor(client, "location.pathname !== '/login'", 30000);

    const results = [];
    const branchScopes = [];
    for (const viewport of viewports) {
        await client.send('Emulation.setDeviceMetricsOverride', {
            width: viewport.width,
            height: viewport.height,
            deviceScaleFactor: 1,
            mobile: viewport.width < 992,
            screenWidth: viewport.width,
            screenHeight: viewport.height,
        });
        await navigate(client, `${baseUrl}/dashboard/presentation?periode=${encodeURIComponent(period)}&scope=area6&prognosa=${usePrognosa ? '1' : '0'}`);
        try {
            await waitFor(
                client,
                `document.querySelectorAll('.apple-slide').length === ${expectedSlideCount}`
                    + ` && document.querySelectorAll('.pres-dot').length === ${expectedSlideCount}`
                    + " && typeof window.__presentationLayoutAudit === 'function'"
                    + " && document.querySelector('#pres-slide-0 .psd-cover')",
                renderTimeout,
            );
        } catch (error) {
            const state = await evaluate(client, `(() => ({
                url: location.href,
                readyState: document.readyState,
                slides: document.querySelectorAll('.apple-slide').length,
                dots: document.querySelectorAll('.pres-dot').length,
                firstRendered: Boolean(document.querySelector('#pres-slide-0 .psd-cover')),
                firstBusy: document.getElementById('pres-slide-0')?.getAttribute('aria-busy'),
                loaderActive: document.getElementById('dashboard-global-loader')?.classList.contains('active'),
                bodyText: String(document.body.innerText || '').trim().slice(0, 500),
            }))()`); 
            await capture(client, 'presentation-load-failure.png');
            throw new Error(`${error.message}; state=${JSON.stringify(state)}; js=${JSON.stringify(runtimeErrors)}`);
        }
        await sleep(800);

        results.push(...await auditScope(client, viewport, 'area6', 'Area 6 Konsol', viewport.screenshots));
        if (viewport.branch) {
            const branch = await evaluate(client, `(() => {
                const select = document.getElementById('pres-global-scope-selector');
                const option = Array.from(select?.options || []).find((item) => item.value && item.value !== 'area6');
                return option ? { key: option.value, label: option.textContent.trim() } : null;
            })()`);
            if (branch?.key) {
                branchScopes.push(branch);
                results.push(...await auditScope(client, viewport, branch.key, branch.label, viewport.screenshots));
            }
        }
    }

    const coverageIssues = [];
    const expectedIndexes = auditedSlideIndexes;
    const expectedScopeAudits = expectedIndexes.length;
    for (const viewport of viewports) {
        const areaResults = results.filter((result) => (
            result.viewport === viewport.name && result.scope === 'Area 6 Konsol'
        ));
        if (areaResults.length !== expectedScopeAudits
            || areaResults.some((result, index) => result.index !== expectedIndexes[index])) {
            coverageIssues.push(`${viewport.name}: Area 6 tidak memiliki urutan audit slide ${expectedIndexes.join(', ')}.`);
        }
        if (viewport.branch) {
            const branchResults = results.filter((result) => (
                result.viewport === viewport.name && result.scope !== 'Area 6 Konsol'
            ));
            if (branchResults.length !== expectedScopeAudits
                || branchResults.some((result, index) => result.index !== expectedIndexes[index])) {
                coverageIssues.push(`${viewport.name}: scope cabang tidak memiliki urutan audit slide ${expectedIndexes.join(', ')}.`);
            }
        }
    }
    const expectedAudits = viewports.reduce(
        (total, viewport) => total + (viewport.branch ? expectedScopeAudits * 2 : expectedScopeAudits),
        0,
    );
    if (results.length !== expectedAudits) {
        coverageIssues.push(`Jumlah audit ${results.length}, seharusnya ${expectedAudits}.`);
    }

    const critical = results.filter((result) => result.fatal
        || !result.rendered
        || result.busy
        || result.overflowX
        || result.overflowY
        || result.readingClip
        || result.clippedTableRows?.length
        || result.layoutOverlaps?.length
        || result.missingRkaCells?.length
        || result.fundingProductSelector?.valid === false
        || result.microViewSelector?.valid === false
        || result.timeseriesModal?.valid === false
        || result.prognosaValid === false
        || !result.shellWithinSlide
        || result.outside?.length
        || result.outsideText?.length
        || result.emptyPanels?.length
        || (result.viewportHorizontalGap !== null && result.viewportHorizontalGap > 20)
        || result.viewportOverflow);
    const warnings = results.filter((result) => result.textClips?.length || (result.minDataFont !== null && result.minDataFont < 9));
    const report = {
        generatedAt: new Date().toISOString(),
        period,
        usePrognosa,
        viewports,
        branchScopes,
        runtimeErrors,
        coverageIssues,
        totals: {
            audited: results.length,
            expected: expectedAudits,
            critical: critical.length + coverageIssues.length,
            warnings: warnings.length,
        },
        critical,
        warnings,
        results,
    };
    await writeFile(path.join(outputDir, 'report.json'), JSON.stringify(report, null, 2));
    process.stdout.write(`${JSON.stringify(report.totals, null, 2)}\n`);
    process.stdout.write(`Branch scope: ${JSON.stringify(branchScopes[0] || null)}\n`);
    process.stdout.write(`JavaScript errors: ${runtimeErrors.length}\n`);
    process.stdout.write(`Laporan: ${path.join(outputDir, 'report.json')}\n`);
    if (critical.length || coverageIssues.length || runtimeErrors.length) process.exitCode = 2;
} finally {
    client?.close();
    chrome.kill();
    await rm(profileDir, { recursive: true, force: true }).catch(() => {});
}
