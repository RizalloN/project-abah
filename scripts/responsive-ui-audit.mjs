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
const publicOnly = process.env.AUDIT_PUBLIC_ONLY === '1';
const waitSelector = String(process.env.AUDIT_WAIT_SELECTOR || '').trim();
const scrollSelector = String(process.env.AUDIT_SCROLL_SELECTOR || '').trim();
const auditStickyScroll = process.env.AUDIT_STICKY_SCROLL === '1';
const landingScope = String(process.env.AUDIT_LANDING_SCOPE || '').trim().toLowerCase();
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
        '.kinerja-table-container', '[class*="table-wrap"]', '[class*="table-scroll"]',
        '[class*="table-container"]', '[class*="table-shell"]',
        '.nav-tabs', '.dropdown-menu', '.select2-dropdown', '.leaflet-container',
        '.micro-need-filter',
        '.main-sidebar', '.control-sidebar', '.route-loading-overlay',
        '.abah-floating-table-header'
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

    const sourceTables = Array.from(document.querySelectorAll('table:not([data-abah-floating-clone])'));
    const tableMetrics = sourceTables.slice(0, 12).map((table) => {
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
            pageHeader: {
                eligible: false,
                tested: false,
                scrollDistance: 0,
                frozen: true,
                navbarVisible: true,
                mirrorVisible: false,
                alignedBelowNavbar: true,
                widthsAligned: true,
                noOverlap: true,
                horizontalSynced: true,
                diagnostics: {},
            },
        };

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

        const freezeMode = document.body.dataset.abahTableFreeze;
        const tableRect = table.getBoundingClientRect();
        const headRect = table.tHead?.getBoundingClientRect();
        const navbar = document.querySelector('.main-header');
        const navbarRect = navbar?.getBoundingClientRect();
        const originalPageX = window.scrollX;
        const originalPageY = window.scrollY;
        const maxPageScroll = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
        const absoluteHeadTop = originalPageY + (headRect?.top || 0);
        const absoluteTableBottom = originalPageY + tableRect.bottom;
        const navbarBottom = Math.max(0, navbarRect?.bottom || 0);
        const headHeight = headRect?.height || 0;
        const targetPageY = Math.min(
            maxPageScroll,
            Math.max(0, Math.round(absoluteHeadTop - navbarBottom + Math.max(12, headHeight * 0.35)))
        );
        const keepsTableBodyVisible = absoluteTableBottom - targetPageY > navbarBottom + headHeight + 2;
        const canScrollHeaderBehindNavbar = targetPageY > absoluteHeadTop - navbarBottom + 2;
        const hasOwnVerticalRange = Boolean(verticalWrapper && verticalRange > 2);
        result.pageHeader.eligible = freezeMode === 'on'
            && table.classList.contains('abah-table-managed')
            && Boolean(table.tHead)
            && !table.closest('.modal')
            && !hasOwnVerticalRange
            && headHeight > 1
            && targetPageY > 1
            && canScrollHeaderBehindNavbar
            && keepsTableBodyVisible;

        if (result.pageHeader.eligible) {
            result.pageHeader.tested = true;
            result.pageHeader.scrollDistance = Math.abs(targetPageY - originalPageY);
            try {
                window.scrollTo({ left: originalPageX, top: targetPageY, behavior: 'auto' });
                await nextFrame();

                const currentNavbarRect = navbar?.getBoundingClientRect();
                const mirror = document.querySelector('.abah-floating-table-header:not([hidden])');
                const clone = mirror?.querySelector('table[data-abah-floating-clone]');
                const mirrorRect = mirror?.getBoundingClientRect();
                const cloneRows = Array.from(clone?.tHead?.rows || []);
                const rowBands = cloneRows.map((row) => row.getBoundingClientRect());
                const sourceCells = Array.from(table.tHead?.querySelectorAll('th, td') || []);
                const cloneCells = Array.from(clone?.tHead?.querySelectorAll('th, td') || []);
                const normalizeText = (value) => String(value || '').replace(/\\s+/g, ' ').trim();

                result.pageHeader.navbarVisible = Boolean(currentNavbarRect
                    && currentNavbarRect.top >= -2
                    && currentNavbarRect.bottom > 2
                    && currentNavbarRect.bottom <= window.innerHeight + 2);
                result.pageHeader.mirrorVisible = Boolean(mirrorRect
                    && mirrorRect.width > 2
                    && mirrorRect.height > 2);
                result.pageHeader.alignedBelowNavbar = Boolean(mirrorRect && currentNavbarRect
                    && Math.abs(mirrorRect.top - currentNavbarRect.bottom) <= 3);
                result.pageHeader.widthsAligned = sourceCells.length > 0
                    && sourceCells.length === cloneCells.length
                    && sourceCells.every((cell, cellIndex) => (
                        Math.abs(cell.getBoundingClientRect().width - cloneCells[cellIndex].getBoundingClientRect().width) <= 2
                    ));
                result.pageHeader.noOverlap = rowBands.every((band, rowIndex) => (
                    rowIndex === 0 || band.top >= rowBands[rowIndex - 1].bottom - 1
                ));

                if (mirror && clone && horizontalWrapper && horizontalRange > 2) {
                    const syncDistance = Math.min(
                        horizontalRange,
                        Math.max(120, Math.round(horizontalWrapper.clientWidth * 0.35))
                    );
                    horizontalWrapper.scrollLeft = Math.min(horizontalRange, initialScrollLeft + syncDistance);
                    await nextFrame();
                    const syncedSourceCells = Array.from(table.tHead?.querySelectorAll('th, td') || []).slice(0, 8);
                    const syncedCloneCells = Array.from(clone.tHead?.querySelectorAll('th, td') || []).slice(0, 8);
                    result.pageHeader.horizontalSynced = syncedSourceCells.length > 0
                        && syncedSourceCells.length === syncedCloneCells.length
                        && syncedSourceCells.every((cell, cellIndex) => {
                            const sourceRect = cell.getBoundingClientRect();
                            const cloneRect = syncedCloneCells[cellIndex].getBoundingClientRect();
                            return Math.abs(sourceRect.left - cloneRect.left) <= 3
                                && Math.abs(sourceRect.width - cloneRect.width) <= 2;
                        });
                    result.pageHeader.diagnostics.horizontalMismatches = syncedSourceCells.map((cell, cellIndex) => ({
                        index: cellIndex,
                        sourceLeft: rounded(cell.getBoundingClientRect().left),
                        cloneLeft: rounded(syncedCloneCells[cellIndex]?.getBoundingClientRect().left),
                    })).filter(({ sourceLeft, cloneLeft }) => (
                        cloneLeft === null || Math.abs(sourceLeft - cloneLeft) > 3
                    )).slice(0, 8);
                }

                const headerTextMatches = Boolean(clone)
                    && normalizeText(table.tHead?.textContent) === normalizeText(clone.tHead?.textContent);
                result.pageHeader.frozen = result.pageHeader.navbarVisible
                    && result.pageHeader.mirrorVisible
                    && result.pageHeader.alignedBelowNavbar
                    && result.pageHeader.widthsAligned
                    && result.pageHeader.noOverlap
                    && result.pageHeader.horizontalSynced
                    && headerTextMatches;
                result.pageHeader.diagnostics = Object.assign(result.pageHeader.diagnostics, {
                    targetPageY,
                    navbarBottom: rounded(currentNavbarRect?.bottom),
                    mirrorTop: rounded(mirrorRect?.top),
                    mirrorHeight: rounded(mirrorRect?.height),
                    sourceTableTop: rounded(table.getBoundingClientRect().top),
                    sourceTableBottom: rounded(table.getBoundingClientRect().bottom),
                    sourceHeadTop: rounded(table.tHead?.getBoundingClientRect().top),
                    sourceHeadHeight: rounded(table.tHead?.getBoundingClientRect().height),
                    managed: table.classList.contains('abah-table-managed'),
                    verticalWrapperRange: verticalRange,
                    sourceCellCount: sourceCells.length,
                    cloneCellCount: cloneCells.length,
                    widthMismatches: sourceCells.map((cell, cellIndex) => ({
                        index: cellIndex,
                        source: rounded(cell.getBoundingClientRect().width),
                        clone: rounded(cloneCells[cellIndex]?.getBoundingClientRect().width),
                    })).filter(({ source, clone: cloneWidth }) => (
                        cloneWidth === null || Math.abs(source - cloneWidth) > 2
                    )).slice(0, 8),
                    horizontalScrollLeft: horizontalWrapper ? rounded(horizontalWrapper.scrollLeft) : null,
                    mirrorScrollLeft: mirror ? rounded(mirror.scrollLeft) : null,
                    headerTextMatches,
                });
            } finally {
                if (horizontalWrapper) {
                    horizontalWrapper.scrollLeft = initialScrollLeft;
                }
                window.scrollTo({ left: originalPageX, top: originalPageY, behavior: 'auto' });
                await nextFrame();
            }
        }

        return result;
    };
    const stickyAudits = [];
    if (auditStickyScroll) {
        const tables = sourceTables
            .filter((table) => table.querySelector('thead th, thead td'));
        for (let index = 0; index < tables.length; index += 1) {
            stickyAudits.push(await inspectStickyTable(tables[index], index));
        }
    }

    const nestedVerticalTableScrolls = [];
    const inspectedVerticalHosts = new Set();
    sourceTables.forEach((table) => {
        const tableRect = table.getBoundingClientRect();
        const tableStyle = getComputedStyle(table);
        if (!isVisible(table, tableStyle, tableRect)) {
            return;
        }

        let host = table.parentElement;
        while (host && host !== document.body && host !== document.documentElement && !host.classList.contains('content-wrapper')) {
            if (!inspectedVerticalHosts.has(host)) {
                const style = getComputedStyle(host);
                const overflowY = style.overflowY;
                const hasNestedScroll = ['auto', 'scroll'].includes(overflowY)
                    && host.scrollHeight > host.clientHeight + 1;

                if (hasNestedScroll) {
                    inspectedVerticalHosts.add(host);
                    nestedVerticalTableScrolls.push({
                        selector: selectorFor(host),
                        overflowY,
                        clientHeight: Math.round(host.clientHeight),
                        scrollHeight: Math.round(host.scrollHeight),
                    });
                }
            }
            host = host.parentElement;
        }
    });

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

    const auditedCards = Array.from(document.querySelectorAll([
        '.kpi-card', '.area6-card-premium', '.landing-insight', '.chart-panel', '.digital-panel', '.dc',
        '.sme-ops-intro', '.sme-ops-feature', '.sme-ops-status-item', '.sme-ops-vendor-item',
        '.micro-ops-section', '.micro-realization-type-card', '.micro-decision-card', '.micro-pattern-card',
    ].join(',')))
        .map((element) => ({ element, rect: element.getBoundingClientRect(), style: getComputedStyle(element) }))
        .filter(({ element, rect, style }) => isVisible(element, style, rect));
    const clippedCards = auditedCards
        .map(({ element, rect, style }) => {
            if (!['hidden', 'clip'].includes(style.overflowX)) {
                return null;
            }

            const overflowingChildren = Array.from(element.querySelectorAll('*'))
                .filter((child) => {
                    const childStyle = getComputedStyle(child);
                    const childRect = child.getBoundingClientRect();
                    if (!isVisible(child, childStyle, childRect)
                        || ['absolute', 'fixed'].includes(childStyle.position)
                        || child.closest(ignoredOverflowHosts)) {
                        return false;
                    }

                    return childRect.left < rect.left - 2 || childRect.right > rect.right + 2;
                })
                .slice(0, 5);

            return overflowingChildren.length ? { element, overflowingChildren } : null;
        })
        .filter(Boolean)
        .slice(0, 20)
        .map(({ element, overflowingChildren }) => ({
            selector: selectorFor(element),
            clientWidth: Math.round(element.clientWidth),
            scrollWidth: Math.round(element.scrollWidth),
            children: overflowingChildren.map((child) => ({
                selector: selectorFor(child),
                text: (child.textContent || '').replace(/\\s+/g, ' ').trim().slice(0, 120),
            })),
        }));

    const headingElements = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, .card-title, [data-ui="title"]'))
        .map((element) => {
            const rect = element.getBoundingClientRect();
            return { element, rect: visibleRectWithinAncestors(element, rect), style: getComputedStyle(element) };
        })
        .filter(({ element, rect, style }) => isVisible(element, style, rect));
    const narrowHeadings = headingElements
        .map(({ element, rect, style }) => {
            const lineHeight = Number.parseFloat(style.lineHeight) || Number.parseFloat(style.fontSize) * 1.25 || 16;
            const text = (element.textContent || '').replace(/\\s+/g, ' ').trim();
            return { element, rect, lineHeight, text };
        })
        .filter(({ rect, lineHeight, text }) => text.length >= 10 && rect.width < 72 && rect.height > lineHeight * 3.25)
        .slice(0, 12)
        .map(({ element, rect, lineHeight, text }) => ({
            selector: selectorFor(element),
            text: text.slice(0, 120),
            width: Math.round(rect.width),
            height: Math.round(rect.height),
            estimatedLines: Math.round((rect.height / lineHeight) * 10) / 10,
        }));
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
                    headingText: (heading.textContent || '').replace(/\\s+/g, ' ').trim().slice(0, 120),
                    control: selectorFor(control),
                    controlText: (control.textContent || control.getAttribute('aria-label') || '').replace(/\\s+/g, ' ').trim().slice(0, 120),
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

    const editorShell = document.querySelector('.asix-sheet-app, .asix-office-shell');
    const editorCanvas = document.querySelector('.asix-sheet-body-viewport, .asix-office-canvas');
    const editorViewport = editorShell ? {
        shell: selectorFor(editorShell),
        top: rounded(editorShell.getBoundingClientRect().top),
        bottom: rounded(editorShell.getBoundingClientRect().bottom),
        canvasHeight: rounded(editorCanvas?.getBoundingClientRect().height),
        clipped: editorShell.getBoundingClientRect().bottom > viewportHeight + 2
            || editorShell.getBoundingClientRect().top < -2
            || (editorCanvas && editorCanvas.getBoundingClientRect().height < 2),
    } : null;

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
        clippedCards,
        narrowHeadings,
        undersizedControls,
        interactiveOverlaps: interactiveOverlaps.slice(0, 12),
        editorViewport,
        tableMetrics,
        nestedVerticalTableScrolls,
        stickyAudits,
        tableFreeze: {
            mode: document.body.dataset.abahTableFreeze || null,
            floatingHeaderPresent: Boolean(document.querySelector('.abah-floating-table-header')),
            floatingHeaderVisible: Boolean(document.querySelector('.abah-floating-table-header:not([hidden])')),
        },
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

    if (!publicOnly) {
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
    }

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
            let landingScopeState = null;
            if (landingScope && new URL(route, baseUrl).pathname === '/dashboard') {
                await waitForPage(
                    client,
                    `document.querySelector('[data-area6-scope="${landingScope}"]')`,
                    waitSelectorTimeoutMs
                );
                const activation = await evaluate(client, `(() => {
                    const scope = ${JSON.stringify(landingScope)};
                    const button = document.querySelector('[data-area6-scope="' + scope + '"]');
                    if (!button) return { clicked: false, scope };
                    button.click();
                    return { clicked: true, scope };
                })()`);
                if (!activation?.clicked) {
                    throw new Error(`Trigger landing scope tidak ditemukan: ${landingScope}`);
                }

                const readyPredicate = landingScope === 'micro'
                    ? "document.querySelector('.db-shell.micro-performance-active') && document.querySelector('[data-micro-performance-ready=\"1\"]')"
                    : (landingScope === 'consumer'
                        ? "document.querySelector('.db-shell.consumer-active') && document.querySelector('[data-consumer-operations-ready=\"1\"]')"
                        : `document.querySelector('[data-area6-content-scope="${landingScope}"]:not(.d-none)')`);
                await waitForPage(client, readyPredicate, waitSelectorTimeoutMs);
                landingScopeState = await evaluate(client, `(() => {
                    const scope = ${JSON.stringify(landingScope)};
                    const visible = (element) => {
                        if (!element) return false;
                        const style = getComputedStyle(element);
                        const rect = element.getBoundingClientRect();
                        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
                    };
                    const shell = document.querySelector('.db-shell');
                    const content = document.querySelector('[data-area6-content-scope="' + scope + '"]');
                    const grid = content?.querySelector('.area6-card-grid');
                    const cards = Array.from(grid?.querySelectorAll('.area6-card-premium') || []).filter(visible);
                    const segments = Array.from(content?.querySelectorAll('.area6-segment-container') || []).filter(visible);
                    const microProductRows = Array.from(content?.querySelectorAll('.area6-segment-card .asc-tr-data') || [])
                        .filter(visible)
                        .map((row) => row.querySelector('.asc-seg-name')?.textContent?.trim() || '');
                    const desk = document.getElementById('micro-performance-dashboard');
                    const consumerDesk = document.getElementById('consumer-operations-dashboard');
                    const consumerIllustration = consumerDesk?.querySelector('[aria-labelledby="consumer-rm-illustration-title consumer-rm-illustration-desc"]');
                    const consumerPipelineTabs = Array.from(consumerDesk?.querySelectorAll('[data-consumer-pipeline-tab]') || []).filter(visible);
                    const consumerPipelineTables = Array.from(consumerDesk?.querySelectorAll('[data-consumer-pipeline-panel]:not([hidden]) .consumer-ops-table') || []).filter(visible);
                    const consumerKprPipeline = consumerDesk?.querySelector('[aria-labelledby="consumer-kpr-pipeline-title"]');
                    const consumerKprPipelineTables = Array.from(consumerKprPipeline?.querySelectorAll('.consumer-ops-table') || []).filter(visible);
                    const consumerQuadrantProducts = Array.from(consumerDesk?.querySelectorAll('[data-consumer-quadrant-product].consumer-ops-product') || []).filter(visible);
                    const consumerQuadrantTables = Array.from(consumerDesk?.querySelectorAll('[data-consumer-quadrant-panel]:not([hidden]) .consumer-ops-rm-table') || []).filter(visible);
                    const consumerArea6Triggers = Array.from(consumerDesk?.querySelectorAll('[data-consumer-area6-trigger="1"]') || []).filter(visible);
                    const consumerRmRows = Array.from(consumerDesk?.querySelectorAll('[data-consumer-quadrant-panel]:not([hidden]) .consumer-ops-rm-table tbody tr') || []).filter(visible);
                    const consumerDeskText = consumerDesk?.textContent || '';
                    const hero = desk?.querySelector('.micro-ops-hero');
                    const mantriIllustration = desk?.querySelector('.micro-mantri-stage__visual svg');
                    const realizationCards = Array.from(desk?.querySelectorAll('.micro-realization-summary-grid .micro-realization-type-card') || []).filter(visible);
                    const pdwkStatuses = Array.from(desk?.querySelectorAll('[data-micro-pdwk-panel]:not([hidden]) .micro-pdwk-status') || []).filter(visible);
                    const pdwkRoleButtons = Array.from(desk?.querySelectorAll('[data-micro-pdwk-role]') || []).filter(visible);
                    const oneTimeInteractive = desk?.querySelector('[data-micro-one-time-detail], [data-micro-nominative-modal]');
                    const mantriSummaryTable = desk?.querySelector('.micro-mantri-table--summary');
                    const mantriTierTables = Array.from(desk?.querySelectorAll('.micro-mantri-table--tiers') || []).filter(visible);
                    const rmKurProductivity = desk?.querySelector('[data-micro-rm-kur-productivity]');
                    const rmKurTables = Array.from(rmKurProductivity?.querySelectorAll('.micro-rm-kur-table') || []).filter(visible);
                    const microDeskText = desk?.textContent || '';
                    const qualityComposition = content?.querySelector('.total-composition-card--micro .tcc-quality-matrix');
                    const qualityCompositionRows = Array.from(qualityComposition?.querySelectorAll('.tcc-quality-row[role="row"]:not(.tcc-quality-row--head):not(.tcc-quality-row--total)') || []).filter(visible);
                    const musimanBreakdown = desk?.querySelector('.micro-pattern-card.pattern-musiman .micro-pattern-card__breakdown');
                    let billingInteractionFunctional = scope !== 'micro';
                    let billingInteractionDiagnostics = null;
                    if (scope === 'micro') {
                        const billingSection = desk?.querySelector('[data-micro-billing-section]');
                        const m0Button = billingSection?.querySelector('[data-billing-view="m0"]');
                        const m1Button = billingSection?.querySelector('[data-billing-view="m1"]');
                        const osButton = billingSection?.querySelector('[data-billing-metric="os"]');
                        const debButton = billingSection?.querySelector('[data-billing-metric="deb"]');

                        m1Button?.click();
                        debButton?.click();

                        const m0Content = billingSection?.querySelector('.card-view-content.view-m0');
                        const m1Content = billingSection?.querySelector('.card-view-content.view-m1');
                        const m1DebDisplay = m1Content?.querySelector('[data-metric-display="deb"]');
                        const m1OsDisplay = m1Content?.querySelector('[data-metric-display="os"]');
                        const billingGrid = billingSection?.querySelector('.micro-billing-calendar-grid');

                        billingInteractionDiagnostics = {
                            sectionPresent: Boolean(billingSection),
                            m1Active: Boolean(m1Button?.classList.contains('is-active')),
                            m1Pressed: m1Button?.getAttribute('aria-pressed') === 'true',
                            debActive: Boolean(debButton?.classList.contains('is-active')),
                            debPressed: debButton?.getAttribute('aria-pressed') === 'true',
                            m0Hidden: Boolean(m0Content) && !visible(m0Content),
                            m1Visible: visible(m1Content),
                            debVisible: visible(m1DebDisplay),
                            osHidden: Boolean(m1OsDisplay) && !visible(m1OsDisplay),
                            gridModeM1: Boolean(billingGrid?.classList.contains('view-mode-m1')),
                        };
                        billingInteractionFunctional = Object.values(billingInteractionDiagnostics).every(Boolean);

                        m0Button?.click();
                        osButton?.click();
                    }
                    const contentRect = content?.getBoundingClientRect();
                    const gridRect = grid?.getBoundingClientRect();
                    const lastCardRect = cards.at(-1)?.getBoundingClientRect();
                    const resolvedColumns = grid ? getComputedStyle(grid).gridTemplateColumns : '';
                    return {
                        scope,
                        active: Boolean(document.querySelector('.area6-scope-btn.active[data-area6-scope="' + scope + '"]')),
                        shellModeActive: scope === 'micro'
                            ? shell?.classList.contains('micro-performance-active')
                            : (scope === 'consumer' ? shell?.classList.contains('consumer-active') : true),
                        coreVisible: visible(content),
                        cardCount: cards.length,
                        recoveryVisible: cards.some((card) => card.dataset.metric === 'recovery'),
                        segmentContainersVisible: segments.length,
                        microProductRows,
                        microProductRowCount: microProductRows.length,
                        microDeskVisible: scope !== 'micro' || visible(desk),
                        consumerDeskVisible: scope !== 'consumer' || visible(consumerDesk),
                        consumerIllustrationVisible: scope !== 'consumer' || visible(consumerIllustration),
                        consumerPipelineTabCount: consumerPipelineTabs.length,
                        consumerPipelineTableCount: consumerPipelineTables.length,
                        consumerKprPipelineVisible: scope !== 'consumer' || visible(consumerKprPipeline),
                        consumerKprPipelineTableCount: consumerKprPipelineTables.length,
                        consumerQuadrantProductCount: consumerQuadrantProducts.length,
                        consumerQuadrantTableCount: consumerQuadrantTables.length,
                        consumerArea6TriggerCount: consumerArea6Triggers.length,
                        consumerRmRowCount: consumerRmRows.length,
                        hasSeparateConsumerQuadrants: scope !== 'consumer'
                            || (consumerDeskText.includes('Kuadran RM Briguna') && consumerDeskText.includes('Kuadran RM KPR')),
                        heroRemoved: scope !== 'micro' || !hero,
                        illustrationVisible: scope !== 'micro' || visible(mantriIllustration),
                        mantriIllustrationVisible: scope !== 'micro' || visible(mantriIllustration),
                        realizationCardCount: realizationCards.length,
                        pdwkStatusCount: pdwkStatuses.length,
                        pdwkRoleButtonCount: pdwkRoleButtons.length,
                        oneTimeInteractiveRemoved: scope !== 'micro' || !oneTimeInteractive,
                        mantriSummaryVisible: scope !== 'micro' || visible(mantriSummaryTable),
                        mantriTierTableCount: mantriTierTables.length,
                        rmKurProductivityVisible: scope !== 'micro' || visible(rmKurProductivity),
                        rmKurTableCount: rmKurTables.length,
                        hasRmKurProductivityLabel: scope !== 'micro' || microDeskText.includes('Produktivitas RM KUR Kecil Mikro'),
                        hasPlafondMetric: scope !== 'micro' || microDeskText.includes('Plafon (Realisasi Baru)'),
                        hasNettMetric: scope !== 'micro' || microDeskText.includes('Nett Disbursement'),
                        hasRunoffMetric: scope !== 'micro' || microDeskText.includes('Run Off Mikro'),
                        hasPhMetric: scope !== 'micro' || microDeskText.includes('PH Mikro'),
                        hasCifLabel: scope === 'micro' && /\bCIF\b/.test(microDeskText),
                        qualityCompositionVisible: scope !== 'micro' || visible(qualityComposition),
                        qualityCompositionRowCount: qualityCompositionRows.length,
                        musimanBreakdownVisible: scope !== 'micro' || visible(musimanBreakdown),
                        billingInteractionFunctional,
                        billingInteractionDiagnostics,
                        gridColumnCount: resolvedColumns && resolvedColumns !== 'none'
                            ? resolvedColumns.trim().split(/\\s+/).length
                            : 0,
                        centerDelta: contentRect && gridRect
                            ? Math.abs((contentRect.left + contentRect.right - gridRect.left - gridRect.right) / 2)
                            : null,
                        lastCardCenterDelta: contentRect && lastCardRect
                            ? Math.abs((contentRect.left + contentRect.right - lastCardRect.left - lastCardRect.right) / 2)
                            : null,
                    };
                })()`);
            }
            if (waitSelector) {
                await waitForPage(client, `document.querySelectorAll(${JSON.stringify(waitSelector)}).length > 0`, waitSelectorTimeoutMs);
            }
            if (scrollSelector) {
                const didScroll = await evaluate(client, `(() => {
                    const element = document.querySelector(${JSON.stringify(scrollSelector)});
                    if (!element) return false;
                    element.scrollIntoView({ block: 'start', inline: 'nearest' });
                    return true;
                })()`);
                if (!didScroll) {
                    throw new Error(`Selector scroll audit tidak ditemukan: ${scrollSelector}`);
                }
                await sleep(500);
            }
            const pageResult = await evaluate(client, auditExpression);
            pageResult.route = route;
            pageResult.viewportName = viewport.name;
            pageResult.runtimeErrors = [...runtimeErrors];
            pageResult.landingScopeState = landingScopeState;

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
        || (table.pageHeader.eligible && (!table.pageHeader.tested || !table.pageHeader.frozen))
        || table.transparentStickyCells.length > 0
    );
    const tableFreezeAuditFailed = (result) => {
        const routePath = String(result.route || '').split('?')[0];
        const isLanding = routePath === '/dashboard' || routePath === '/dashboard/simpanan';
        if (isLanding) {
            return result.tableFreeze.mode !== 'off' || result.tableFreeze.floatingHeaderPresent;
        }

        return result.tableMetrics.length > 0
            && (result.tableFreeze.mode !== 'on' || !result.tableFreeze.floatingHeaderPresent);
    };
    const landingScopeAuditFailed = (result) => {
        const state = result.landingScopeState;
        if (!state) return false;
        if (!state.active || !state.shellModeActive || !state.coreVisible) return true;
        if (state.scope === 'consumer') {
            return !state.consumerDeskVisible
                || !state.consumerIllustrationVisible
                || state.consumerPipelineTabCount < 1
                || state.consumerPipelineTableCount !== 1
                || !state.consumerKprPipelineVisible
                || state.consumerKprPipelineTableCount !== 1
                || state.consumerQuadrantProductCount !== 2
                || state.consumerQuadrantTableCount !== 2
                || state.consumerArea6TriggerCount !== 2
                || state.consumerRmRowCount < 1
                || !state.hasSeparateConsumerQuadrants;
        }
        if (state.scope !== 'micro') return false;
        const expectedColumns = result.viewport.width >= 1200 ? 3 : (result.viewport.width >= 768 ? 2 : 1);

        return state.cardCount !== 3
            || state.recoveryVisible
            || state.segmentContainersVisible < 2
            || state.microProductRowCount !== 5
            || state.microProductRows.includes('OS MIKRO')
            || !state.microDeskVisible
            || !state.heroRemoved
            || !state.mantriIllustrationVisible
            || state.realizationCardCount !== 4
            || state.pdwkStatusCount !== 4
            || state.pdwkRoleButtonCount < 1
            || !state.oneTimeInteractiveRemoved
            || !state.mantriSummaryVisible
            || state.mantriTierTableCount !== 2
            || !state.rmKurProductivityVisible
            || state.rmKurTableCount !== 1
            || !state.hasRmKurProductivityLabel
            || !state.hasPlafondMetric
            || !state.hasNettMetric
            || !state.hasRunoffMetric
             || !state.hasPhMetric
             || !state.billingInteractionFunctional
            || state.hasCifLabel
            || !state.qualityCompositionVisible
            || state.qualityCompositionRowCount !== 7
            || !state.musimanBreakdownVisible
            || state.gridColumnCount !== expectedColumns
            || (state.centerDelta !== null && state.centerDelta > 4)
            || (expectedColumns < 3 && state.lastCardCenterDelta !== null && state.lastCardCenterDelta > 4);
    };
    const summary = {
        generatedAt: new Date().toISOString(),
        baseUrl,
        routes,
        viewports,
        results,
        failures: results.filter((result) => !result.applicationError && (
            result.document.horizontalOverflow
            || result.horizontalOffenders.length > 0
            || result.clippedCards.length > 0
            || result.narrowHeadings.length > 0
            || result.interactiveOverlaps.length > 0
            || result.editorViewport?.clipped
            || result.nestedVerticalTableScrolls.length > 0
            || result.runtimeErrors.length > 0
            || result.stickyAudits.some(stickyAuditFailed)
            || tableFreezeAuditFailed(result)
            || landingScopeAuditFailed(result)
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
        clippedCards: result.clippedCards.length,
        narrowHeadings: result.narrowHeadings.length,
        overlaps: result.interactiveOverlaps.length,
        editorClipped: Boolean(result.editorViewport?.clipped),
        smallControls: result.undersizedControls.length,
        tables: result.tableMetrics.length,
        nestedTableScrolls: result.nestedVerticalTableScrolls.length,
        stickyFailures: result.stickyAudits.filter(stickyAuditFailed).length,
        tableFreezeFailed: tableFreezeAuditFailed(result),
        landingScopeFailed: landingScopeAuditFailed(result),
        landingScopeState: result.landingScopeState,
        jsErrors: result.runtimeErrors.length,
        applicationError: result.applicationError,
    }));
    process.stdout.write(`${JSON.stringify(compact, null, 2)}\n`);
    process.stdout.write(`Laporan lengkap: ${path.join(outputDir, 'report.json')}\n`);
    if (summary.failures.length > 0 || summary.applicationErrors.length > 0) {
        process.exitCode = 2;
    }
} finally {
    client?.close();
    chrome.kill();
    await rm(profileDir, { recursive: true, force: true }).catch(() => {});
}
