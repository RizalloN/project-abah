import { spawn } from 'node:child_process';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

const baseUrl = (process.env.AUDIT_BASE_URL || 'http://127.0.0.1:8137').replace(/\/$/, '');
const outputDir = path.resolve(process.env.AUDIT_OUTPUT_DIR || 'storage/framework/testing/responsive-ui-audit');
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const loginPn = process.env.AUDIT_PN || 'responsive-audit';
const loginPassword = process.env.AUDIT_PASSWORD || 'responsive-audit';
const waitSelector = String(process.env.AUDIT_WAIT_SELECTOR || '').trim();
const auditStickyScroll = process.env.AUDIT_STICKY_SCROLL === '1';
const positiveInteger = (value, fallback) => {
    const parsed = Number.parseInt(String(value || ''), 10);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
};
const chromeStartupTimeoutMs = positiveInteger(process.env.AUDIT_CHROME_TIMEOUT_MS, 30000);
const navigationTimeoutMs = positiveInteger(process.env.AUDIT_NAVIGATION_TIMEOUT_MS, 120000);
const loginTimeoutMs = positiveInteger(process.env.AUDIT_LOGIN_TIMEOUT_MS, navigationTimeoutMs);
const waitSelectorTimeoutMs = positiveInteger(process.env.AUDIT_WAIT_TIMEOUT_MS, 90000);
const routes = (process.env.AUDIT_ROUTES || '/dashboard,/dashboard-harian,/import,/report/dashboard-pinjaman,/report/optimalisasi-digital/qlola,/user-management')
    .split(',')
    .map((route) => route.trim())
    .filter(Boolean);
const allViewports = [
    { name: 'fold-portrait', width: 280, height: 653, deviceScaleFactor: 1 },
    { name: 'phone-portrait', width: 390, height: 844, deviceScaleFactor: 1 },
    { name: 'phone-landscape', width: 844, height: 390, deviceScaleFactor: 1 },
    { name: 'tablet-portrait', width: 768, height: 1024, deviceScaleFactor: 1 },
    { name: 'tablet-landscape', width: 1024, height: 768, deviceScaleFactor: 1 },
    { name: 'laptop', width: 1366, height: 768, deviceScaleFactor: 1 },
    { name: 'desktop', width: 1440, height: 900, deviceScaleFactor: 1 },
    { name: 'high-end-desktop', width: 2560, height: 1440, deviceScaleFactor: 1 },
];
const requestedViewports = new Set((process.env.AUDIT_VIEWPORTS || '')
    .split(',')
    .map((name) => name.trim())
    .filter(Boolean));
const viewports = requestedViewports.size
    ? allViewports.filter((viewport) => requestedViewports.has(viewport.name))
    : allViewports;

if (!viewports.length) {
    throw new Error(`Viewport audit tidak dikenali. Pilihan: ${allViewports.map((viewport) => viewport.name).join(', ')}`);
}

function slug(value) {
    return String(value || 'home')
        .replace(/^https?:\/\//, '')
        .replace(/[^a-z0-9]+/gi, '-')
        .replace(/^-|-$/g, '')
        .toLowerCase() || 'home';
}

async function waitForEndpoint(url, timeout = 15000) {
    const startedAt = Date.now();
    while (Date.now() - startedAt < timeout) {
        try {
            const response = await fetch(url);
            if (response.ok) {
                return response.json();
            }
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
            let rawPayload = event.data;
            if (rawPayload instanceof Blob) {
                rawPayload = await rawPayload.text();
            } else if (rawPayload instanceof ArrayBuffer) {
                rawPayload = new TextDecoder().decode(rawPayload);
            }
            const payload = JSON.parse(String(rawPayload));
            if (payload.id && this.pending.has(payload.id)) {
                const { resolve, reject } = this.pending.get(payload.id);
                this.pending.delete(payload.id);
                if (payload.error) {
                    reject(new Error(`${payload.error.message}: ${JSON.stringify(payload.error.data || {})}`));
                } else {
                    resolve(payload.result || {});
                }
                return;
            }

            if (payload.method && this.events.has(payload.method)) {
                this.events.get(payload.method).forEach((listener) => listener(payload.params || {}));
            }
        });

        this.socket.addEventListener('close', () => {
            this.pending.forEach(({ reject }) => reject(new Error('Koneksi Chrome DevTools ditutup.')));
            this.pending.clear();
        });
    }

    send(method, params = {}) {
        const id = this.nextId++;
        const promise = new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
        });
        this.socket.send(JSON.stringify({ id, method, params }));
        return promise;
    }

    on(method, listener) {
        const listeners = this.events.get(method) || [];
        listeners.push(listener);
        this.events.set(method, listeners);
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
        throw new Error(response.exceptionDetails.text || 'Runtime.evaluate gagal');
    }
    return response.result?.value;
}

async function waitForPage(client, predicate, timeout = 20000) {
    const startedAt = Date.now();
    while (Date.now() - startedAt < timeout) {
        const ready = await evaluate(client, `Boolean(${predicate})`).catch(() => false);
        if (ready) {
            await sleep(350);
            return;
        }
        await sleep(150);
    }
    throw new Error(`Halaman tidak siap setelah ${timeout}ms: ${predicate}`);
}

async function navigate(client, url, timeout = navigationTimeoutMs) {
    await client.send('Page.navigate', { url });
    await waitForPage(client, "document.readyState === 'complete'", timeout);
}

const auditExpression = `(async () => {
    const auditStickyScroll = ${JSON.stringify(auditStickyScroll)};
    const viewportWidth = document.documentElement.clientWidth;
    const viewportHeight = window.innerHeight;
    const ignoredOverflowHosts = [
        '.abah-table-scroll', '.table-responsive', '.table-container',
        '[class*="table-wrap"]', '[class*="table-scroll"]',
        '.nav-tabs', '.dropdown-menu', '.select2-dropdown', '.leaflet-container',
        '.main-sidebar', '.control-sidebar', '.route-loading-overlay'
    ].join(',');
    const isVisible = (element, style, rect) => {
        if (style.display === 'none'
            || style.visibility === 'hidden'
            || Number(style.opacity || 1) === 0
            || rect.width <= 1
            || rect.height <= 1) {
            return false;
        }

        let current = element;
        while (current && current !== document.documentElement) {
            const currentStyle = getComputedStyle(current);
            if (current.hidden
                || current.getAttribute('aria-hidden') === 'true'
                || currentStyle.display === 'none'
                || currentStyle.visibility === 'hidden'
                || Number(currentStyle.opacity || 1) === 0) {
                return false;
            }
            current = current.parentElement;
        }

        return true;
    };
    const visibleRectWithinAncestors = (element, rect) => {
        let left = rect.left;
        let top = rect.top;
        let right = rect.right;
        let bottom = rect.bottom;
        let parent = element.parentElement;

        while (parent && parent !== document.body && parent !== document.documentElement) {
            const parentStyle = getComputedStyle(parent);
            const parentRect = parent.getBoundingClientRect();
            if (['auto', 'scroll', 'hidden', 'clip'].includes(parentStyle.overflowX)) {
                left = Math.max(left, parentRect.left);
                right = Math.min(right, parentRect.right);
            }
            if (['auto', 'scroll', 'hidden', 'clip'].includes(parentStyle.overflowY)) {
                top = Math.max(top, parentRect.top);
                bottom = Math.min(bottom, parentRect.bottom);
            }
            parent = parent.parentElement;
        }

        return {
            left,
            top,
            right,
            bottom,
            width: Math.max(0, right - left),
            height: Math.max(0, bottom - top),
        };
    };
    const selectorFor = (element) => {
        if (element.id) return '#' + CSS.escape(element.id);
        const classes = Array.from(element.classList || []).slice(0, 3).map((value) => '.' + CSS.escape(value)).join('');
        return element.tagName.toLowerCase() + classes;
    };
    const isContainedByOverflowHost = (element) => {
        let parent = element.parentElement;
        while (parent && parent !== document.body) {
            const parentStyle = getComputedStyle(parent);
            const parentRect = parent.getBoundingClientRect();
            if (['auto', 'scroll', 'hidden', 'clip'].includes(parentStyle.overflowX)
                && parentRect.left >= -1
                && parentRect.right <= viewportWidth + 1) {
                return true;
            }
            parent = parent.parentElement;
        }
        return false;
    };
    const horizontalOffenders = [];

    document.querySelectorAll('body *').forEach((element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        if (!isVisible(element, style, rect)) return;
        if (element.closest(ignoredOverflowHosts)) return;
        if (isContainedByOverflowHost(element)) return;
        if (style.position === 'fixed' && (rect.right <= 0 || rect.left >= viewportWidth)) return;
        if (rect.right > viewportWidth + 1 || rect.left < -1) {
            horizontalOffenders.push({
                selector: selectorFor(element),
                left: Math.round(rect.left * 10) / 10,
                right: Math.round(rect.right * 10) / 10,
                width: Math.round(rect.width * 10) / 10,
            });
        }
    });

    const tableMetrics = Array.from(document.querySelectorAll('table')).slice(0, 12).map((table) => {
        const wrapper = table.closest('.abah-table-scroll, .table-responsive, .table-container, [class*="table-wrap"], [class*="table-scroll"]');
        const rows = Array.from(table.tBodies || []).flatMap((body) => Array.from(body.rows));
        const rowHeights = rows.slice(0, 10).map((row) => row.getBoundingClientRect().height).filter((height) => height > 0);
        const averageRowHeight = rowHeights.length ? rowHeights.reduce((sum, height) => sum + height, 0) / rowHeights.length : 0;
        return {
            selector: selectorFor(table),
            wrapped: Boolean(wrapper),
            wrapperWidth: wrapper ? Math.round(wrapper.clientWidth) : null,
            wrapperHeight: wrapper ? Math.round(wrapper.clientHeight) : null,
            scrollWidth: wrapper ? Math.round(wrapper.scrollWidth) : Math.round(table.scrollWidth),
            estimatedVisibleRows: wrapper && averageRowHeight ? Math.max(1, Math.floor(wrapper.clientHeight / averageRowHeight) - 1) : null,
        };
    });
    const nextFrame = () => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    const findScrollHost = (table, axis) => {
        let parent = table.parentElement;
        let fallback = null;

        while (parent && parent !== document.body && parent !== document.documentElement) {
            const style = getComputedStyle(parent);
            const permitsScroll = ['auto', 'scroll'].includes(axis === 'x' ? style.overflowX : style.overflowY);
            const hasScrollRange = axis === 'x'
                ? parent.scrollWidth > parent.clientWidth + 2
                : parent.scrollHeight > parent.clientHeight + 2;

            if (!fallback && permitsScroll) {
                fallback = parent;
            }
            if (permitsScroll && hasScrollRange) {
                return parent;
            }
            parent = parent.parentElement;
        }

        return fallback;
    };
    const rounded = (value) => Number.isFinite(value) ? Math.round(value * 10) / 10 : null;
    const isTransparent = (color) => {
        const match = String(color || '').match(/rgba?\\(([^)]+)\\)/i);
        if (!match) return color === 'transparent';
        const channels = match[1].split(',').map((value) => value.trim());
        return channels.length > 3 && Number(channels[3]) < 0.98;
    };
    const inspectStickyTable = async (table, index) => {
        const verticalWrapper = findScrollHost(table, 'y');
        const horizontalWrapper = findScrollHost(table, 'x');
        const verticalWrapperRect = verticalWrapper?.getBoundingClientRect();
        const horizontalWrapperRect = horizontalWrapper?.getBoundingClientRect();
        const headerCells = Array.from(table.querySelectorAll('thead th, thead td'));
        const sampleBodyCells = Array.from(table.tBodies || [])
            .flatMap((body) => Array.from(body.rows).slice(0, 12))
            .flatMap((row) => Array.from(row.cells));
        const visibleStickyCells = [...headerCells, ...sampleBodyCells]
            .map((cell) => {
                const style = getComputedStyle(cell);
                const rect = cell.getBoundingClientRect();
                return { cell, style, rect };
            })
            .filter(({ style, rect }) => style.position === 'sticky' && isVisible(null, style, rect));
        const transparentStickyCells = visibleStickyCells
            .filter(({ style }) => isTransparent(style.backgroundColor) && style.backgroundImage === 'none')
            .slice(0, 12)
            .map(({ cell, style }) => ({
                selector: selectorFor(cell),
                backgroundColor: style.backgroundColor,
            }));
        const result = {
            index,
            selector: selectorFor(table),
            wrapper: verticalWrapper || horizontalWrapper ? selectorFor(verticalWrapper || horizontalWrapper) : null,
            verticalWrapper: verticalWrapper ? selectorFor(verticalWrapper) : null,
            horizontalWrapper: horizontalWrapper ? selectorFor(horizontalWrapper) : null,
            transparentStickyCells,
            verticalHeader: {
                tested: false,
                scrollDistance: 0,
                declaredCells: 0,
                alignedCells: 0,
                testedCells: 0,
                frozen: true,
                noOverlap: true,
                rowBands: [],
                diagnostics: [],
            },
            horizontalColumns: {
                tested: false,
                scrollDistance: 0,
                declaredCells: 0,
                alignedCells: 0,
                frozen: true,
                diagnostics: [],
            },
        };

        if (!verticalWrapperRect && !horizontalWrapperRect) {
            return result;
        }

        const initialScrollTop = verticalWrapper?.scrollTop || 0;
        const verticalRange = verticalWrapper
            ? Math.max(0, verticalWrapper.scrollHeight - verticalWrapper.clientHeight)
            : 0;
        const verticalDistance = verticalWrapper
            ? Math.min(verticalRange, Math.max(120, Math.round(verticalWrapper.clientHeight * 0.35)))
            : 0;
        const verticalCandidates = Array.from(table.querySelectorAll('thead th, thead td'))
            .map((cell) => {
                const style = getComputedStyle(cell);
                const rect = cell.getBoundingClientRect();
                const top = Number.parseFloat(style.top);
                const expectedTop = (verticalWrapperRect?.top || 0) + (verticalWrapper?.clientTop || 0) + top;
                return { cell, style, rect, top, expectedTop };
            })
            .filter(({ style, rect, top }) => style.position === 'sticky'
                && Number.isFinite(top)
                && isVisible(null, style, rect)
                && verticalWrapperRect
                && rect.bottom > verticalWrapperRect.top
                && rect.top < verticalWrapperRect.bottom);
        const testableVerticalCandidates = verticalCandidates
            .filter(({ rect, expectedTop }) => verticalDistance + 2 >= Math.max(0, rect.top - expectedTop));

        result.verticalHeader.declaredCells = verticalCandidates.length;
        result.verticalHeader.alignedCells = verticalCandidates
            .filter(({ rect, expectedTop }) => Math.abs(rect.top - expectedTop) <= 4)
            .length;
        result.verticalHeader.testedCells = testableVerticalCandidates.length;
        result.verticalHeader.scrollDistance = Math.round(verticalDistance);
        result.verticalHeader.tested = verticalDistance > 0 && verticalCandidates.length > 0;
        if (result.verticalHeader.tested) {
            const before = testableVerticalCandidates.map(({ cell, rect, expectedTop }) => ({
                cell,
                top: rect.top,
                expectedTop,
                initiallyAligned: Math.abs(rect.top - expectedTop) <= 4,
            }));
            verticalWrapper.scrollTop = Math.min(verticalRange, initialScrollTop + verticalDistance);
            await nextFrame();
            const verticalDiagnostics = before.map(({ cell, top, expectedTop, initiallyAligned }) => {
                const afterTop = cell.getBoundingClientRect().top;
                return {
                    selector: selectorFor(cell),
                    beforeTop: rounded(top),
                    afterTop: rounded(afterTop),
                    expectedTop: rounded(expectedTop),
                    initiallyAligned,
                    frozen: initiallyAligned
                        ? Math.abs(afterTop - top) <= 2
                        : Math.abs(afterTop - expectedTop) <= 2,
                };
            });
            result.verticalHeader.frozen = before.length > 0
                && verticalDiagnostics.every(({ frozen }) => frozen);
            result.verticalHeader.diagnostics = verticalDiagnostics.slice(0, 16);

            const stickyRowBands = Array.from(table.tHead?.rows || [])
                .map((row) => {
                    const rects = Array.from(row.cells)
                        .filter((cell) => cell.rowSpan === 1 && getComputedStyle(cell).position === 'sticky')
                        .map((cell) => cell.getBoundingClientRect())
                        .filter((rect) => rect.width > 1 && rect.height > 1);
                    return rects.length
                        ? {
                            top: Math.min(...rects.map((rect) => rect.top)),
                            bottom: Math.max(...rects.map((rect) => rect.bottom)),
                        }
                        : null;
                })
                .filter(Boolean);
            result.verticalHeader.rowBands = stickyRowBands.map(({ top, bottom }) => ({
                top: rounded(top),
                bottom: rounded(bottom),
            }));
            result.verticalHeader.noOverlap = stickyRowBands.every((band, rowIndex) => (
                rowIndex === 0 || band.top >= stickyRowBands[rowIndex - 1].bottom - 1
            ));
            verticalWrapper.scrollTop = initialScrollTop;
            await nextFrame();
        }

        const initialScrollLeft = horizontalWrapper?.scrollLeft || 0;
        const horizontalRange = horizontalWrapper
            ? Math.max(0, horizontalWrapper.scrollWidth - horizontalWrapper.clientWidth)
            : 0;
        const horizontalDistance = horizontalWrapper
            ? Math.min(horizontalRange, Math.max(160, Math.round(horizontalWrapper.clientWidth * 0.4)))
            : 0;
        const horizontalCandidates = visibleStickyCells
            .map(({ cell, style, rect }) => {
                const left = Number.parseFloat(style.left);
                const right = Number.parseFloat(style.right);
                const side = Number.isFinite(left) ? 'left' : (Number.isFinite(right) ? 'right' : null);
                const offset = side === 'left' ? left : right;
                const rightBorder = horizontalWrapper
                    ? horizontalWrapper.offsetWidth - horizontalWrapper.clientWidth - horizontalWrapper.clientLeft
                    : 0;
                const expected = side === 'left'
                    ? (horizontalWrapperRect?.left || 0) + (horizontalWrapper?.clientLeft || 0) + offset
                    : (horizontalWrapperRect?.right || 0) - rightBorder - offset;
                const actual = side === 'left' ? rect.left : rect.right;
                return { cell, rect, side, actual, expected };
            })
            .filter(({ side, rect }) => side
                && horizontalWrapperRect
                && rect.right > horizontalWrapperRect.left
                && rect.left < horizontalWrapperRect.right);
        const alignedHorizontalCandidates = horizontalCandidates
            .filter(({ actual, expected }) => Math.abs(actual - expected) <= 4);

        result.horizontalColumns.declaredCells = horizontalCandidates.length;
        result.horizontalColumns.alignedCells = alignedHorizontalCandidates.length;
        result.horizontalColumns.scrollDistance = Math.round(horizontalDistance);
        result.horizontalColumns.tested = horizontalDistance > 0 && horizontalCandidates.length > 0;
        if (result.horizontalColumns.tested) {
            const before = alignedHorizontalCandidates.map(({ cell, rect, side, expected }) => ({
                cell,
                side,
                value: side === 'left' ? rect.left : rect.right,
                expected,
            }));
            horizontalWrapper.scrollLeft = Math.min(horizontalRange, initialScrollLeft + horizontalDistance);
            await nextFrame();
            const horizontalDiagnostics = before.map(({ cell, side, value, expected }) => {
                const afterRect = cell.getBoundingClientRect();
                const afterValue = side === 'left' ? afterRect.left : afterRect.right;
                return {
                    selector: selectorFor(cell),
                    side,
                    before: rounded(value),
                    after: rounded(afterValue),
                    expected: rounded(expected),
                    stable: Math.abs(afterValue - value) <= 2,
                };
            });
            result.horizontalColumns.frozen = before.length > 0
                && horizontalDiagnostics.every(({ stable }) => stable);
            result.horizontalColumns.diagnostics = horizontalDiagnostics.slice(0, 16);
            horizontalWrapper.scrollLeft = initialScrollLeft;
            await nextFrame();
        }

        return result;
    };
    const stickyAudits = [];
    if (auditStickyScroll) {
        const tables = Array.from(document.querySelectorAll('table'))
            .filter((table) => table.querySelector('thead th, thead td'));
        for (let index = 0; index < tables.length; index += 1) {
            stickyAudits.push(await inspectStickyTable(tables[index], index));
        }
    }

    const controls = Array.from(document.querySelectorAll('button, .btn, input:not([type="hidden"]), select, textarea'))
        .filter((element) => !element.classList.contains('select2-hidden-accessible') && element.getAttribute('aria-hidden') !== 'true')
        .map((element) => {
            const rect = element.getBoundingClientRect();
            let targetRect = rect;
            if (element.matches('input[type="checkbox"], input[type="radio"]') && element.labels?.length) {
                const labelRect = element.labels[0].getBoundingClientRect();
                const left = Math.min(rect.left, labelRect.left);
                const top = Math.min(rect.top, labelRect.top);
                const right = Math.max(rect.right, labelRect.right);
                const bottom = Math.max(rect.bottom, labelRect.bottom);
                targetRect = { left, top, right, bottom, width: right - left, height: bottom - top };
            }
            return { element, rect: targetRect, style: getComputedStyle(element) };
        })
        .filter(({ element, rect, style }) => isVisible(element, style, rect));
    const undersizedControls = controls
        .filter(({ rect }) => rect.height < 32 || rect.width < 28)
        .slice(0, 12)
        .map(({ element, rect }) => ({ selector: selectorFor(element), width: Math.round(rect.width), height: Math.round(rect.height) }));

    const headingElements = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, .card-title, [data-ui="title"]'))
        .map((element) => {
            const rect = element.getBoundingClientRect();
            return { element, rect: visibleRectWithinAncestors(element, rect), style: getComputedStyle(element) };
        })
        .filter(({ element, rect, style }) => isVisible(element, style, rect));
    const interactiveElements = Array.from(document.querySelectorAll('button, a.btn, [role="button"], input:not([type="hidden"]), select, textarea'))
        .filter((element) => !element.classList.contains('select2-hidden-accessible') && element.getAttribute('aria-hidden') !== 'true')
        .map((element) => {
            const rect = element.getBoundingClientRect();
            return { element, rect: visibleRectWithinAncestors(element, rect), style: getComputedStyle(element) };
        })
        .filter(({ element, rect, style }) => isVisible(element, style, rect));
    const interactiveOverlaps = [];

    headingElements.forEach(({ element: heading, rect: headingRect }) => {
        interactiveElements.forEach(({ element: control, rect: controlRect }) => {
            if (heading.contains(control) || control.contains(heading)) return;

            const overlapWidth = Math.min(headingRect.right, controlRect.right) - Math.max(headingRect.left, controlRect.left);
            const overlapHeight = Math.min(headingRect.bottom, controlRect.bottom) - Math.max(headingRect.top, controlRect.top);
            if (overlapWidth > 2 && overlapHeight > 2) {
                interactiveOverlaps.push({
                    heading: selectorFor(heading),
                    headingText: (heading.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 120),
                    control: selectorFor(control),
                    controlText: (control.textContent || control.getAttribute('aria-label') || '').replace(/\s+/g, ' ').trim().slice(0, 120),
                    overlapWidth: Math.round(overlapWidth),
                    overlapHeight: Math.round(overlapHeight),
                    headingRect: {
                        left: rounded(headingRect.left),
                        top: rounded(headingRect.top),
                        right: rounded(headingRect.right),
                        bottom: rounded(headingRect.bottom),
                    },
                    controlRect: {
                        left: rounded(controlRect.left),
                        top: rounded(controlRect.top),
                        right: rounded(controlRect.right),
                        bottom: rounded(controlRect.bottom),
                    },
                });
            }
        });
    });

    return {
        url: location.href,
        title: document.title,
        viewport: { width: viewportWidth, height: viewportHeight },
        document: {
            scrollWidth: document.documentElement.scrollWidth,
            scrollHeight: document.documentElement.scrollHeight,
            horizontalOverflow: document.documentElement.scrollWidth > viewportWidth + 1,
        },
        horizontalOffenders: horizontalOffenders.slice(0, 20),
        undersizedControls,
        interactiveOverlaps: interactiveOverlaps.slice(0, 12),
        tableMetrics,
        stickyAudits,
        // Alias dipertahankan agar pemroses report versi lama tidak langsung rusak.
        stickyFrozenColumns: stickyAudits,
        contentHeight: Math.round(document.querySelector('.content-wrapper')?.getBoundingClientRect().height || 0),
        applicationError: Boolean(document.querySelector('pre.shiki, [data-exception], .exception-message')),
    };
})()`;

await mkdir(outputDir, { recursive: true });
const profileDir = path.join(os.tmpdir(), `project-abah-responsive-audit-${process.pid}`);
const port = 9300 + Math.floor(Math.random() * 300);
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
    await waitForEndpoint(`http://127.0.0.1:${port}/json/version`, chromeStartupTimeoutMs);
    const targetResponse = await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' });
    const target = await targetResponse.json();
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
    const loginResult = await evaluate(client, `(() => {
        const pn = document.querySelector('input[name="pn"]');
        const password = document.querySelector('input[name="password"]');
        const form = pn?.form || document.querySelector('form');
        if (!pn || !password || !form) return { ok: false, fields: Array.from(document.querySelectorAll('input')).map((input) => input.name) };
        pn.value = ${JSON.stringify(loginPn)};
        password.value = ${JSON.stringify(loginPassword)};
        pn.dispatchEvent(new Event('input', { bubbles: true }));
        password.dispatchEvent(new Event('input', { bubbles: true }));
        form.requestSubmit();
        return { ok: true };
    })()`);
    if (!loginResult?.ok) {
        throw new Error(`Form login tidak dikenali: ${JSON.stringify(loginResult)}`);
    }
    await waitForPage(client, "location.pathname !== '/login' && document.readyState === 'complete'", loginTimeoutMs);

    const results = [];
    for (const route of routes) {
        for (const viewport of viewports) {
            await client.send('Emulation.setDeviceMetricsOverride', {
                width: viewport.width,
                height: viewport.height,
                deviceScaleFactor: viewport.deviceScaleFactor,
                mobile: viewport.width < 992,
                screenWidth: viewport.width,
                screenHeight: viewport.height,
            });
            runtimeErrors.length = 0;
            await navigate(client, new URL(route, baseUrl).toString());
            if (waitSelector) {
                await waitForPage(client, `document.querySelectorAll(${JSON.stringify(waitSelector)}).length > 0`, waitSelectorTimeoutMs);
            }
            const pageResult = await evaluate(client, auditExpression);
            pageResult.route = route;
            pageResult.viewportName = viewport.name;
            pageResult.runtimeErrors = [...runtimeErrors];

            const screenshot = await client.send('Page.captureScreenshot', {
                format: 'png',
                fromSurface: true,
                captureBeyondViewport: false,
            });
            const screenshotName = `${slug(route)}--${viewport.name}.png`;
            await writeFile(path.join(outputDir, screenshotName), Buffer.from(screenshot.data, 'base64'));
            pageResult.screenshot = screenshotName;
            results.push(pageResult);
        }
    }

    const stickyAuditFailed = (table) => (
        (table.verticalHeader.tested && (!table.verticalHeader.frozen || !table.verticalHeader.noOverlap))
        || (table.horizontalColumns.tested && !table.horizontalColumns.frozen)
        || table.transparentStickyCells.length > 0
    );
    const summary = {
        generatedAt: new Date().toISOString(),
        baseUrl,
        routes,
        viewports,
        results,
        failures: results.filter((result) => !result.applicationError && (
            result.document.horizontalOverflow
            || result.horizontalOffenders.length > 0
            || result.interactiveOverlaps.length > 0
            || result.runtimeErrors.length > 0
            || result.stickyAudits.some(stickyAuditFailed)
        )),
        applicationErrors: results.filter((result) => result.applicationError),
    };
    await writeFile(path.join(outputDir, 'report.json'), JSON.stringify(summary, null, 2));

    const compact = results.map((result) => ({
        route: result.route,
        viewport: result.viewportName,
        size: `${result.viewport.width}x${result.viewport.height}`,
        overflow: result.document.horizontalOverflow,
        offenders: result.horizontalOffenders.length,
        overlaps: result.interactiveOverlaps.length,
        smallControls: result.undersizedControls.length,
        tables: result.tableMetrics.length,
        stickyFailures: result.stickyAudits.filter(stickyAuditFailed).length,
        jsErrors: result.runtimeErrors.length,
        applicationError: result.applicationError,
    }));
    process.stdout.write(`${JSON.stringify(compact, null, 2)}\n`);
    process.stdout.write(`Laporan lengkap: ${path.join(outputDir, 'report.json')}\n`);
    if (summary.failures.length > 0) {
        process.exitCode = 2;
    }
} finally {
    client?.close();
    chrome.kill();
    await rm(profileDir, { recursive: true, force: true }).catch(() => {});
}
