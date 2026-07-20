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
const routes = (process.env.AUDIT_ROUTES || '/dashboard,/dashboard-harian,/import,/report/dashboard-pinjaman,/report/optimalisasi-digital/qlola,/user-management')
    .split(',')
    .map((route) => route.trim())
    .filter(Boolean);
const allViewports = [
    { name: 'fold-portrait', width: 280, height: 653, deviceScaleFactor: 1 },
    { name: 'phone-portrait', width: 390, height: 844, deviceScaleFactor: 1 },
    { name: 'tablet-portrait', width: 768, height: 1024, deviceScaleFactor: 1 },
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

async function navigate(client, url) {
    await client.send('Page.navigate', { url });
    await waitForPage(client, "document.readyState === 'complete'");
}

const auditExpression = `(() => {
    const viewportWidth = document.documentElement.clientWidth;
    const viewportHeight = window.innerHeight;
    const ignoredOverflowHosts = [
        '.abah-table-scroll', '.table-responsive', '.table-container',
        '[class*="table-wrap"]', '[class*="table-scroll"]',
        '.nav-tabs', '.dropdown-menu', '.select2-dropdown', '.leaflet-container',
        '.main-sidebar', '.control-sidebar', '.route-loading-overlay'
    ].join(',');
    const isVisible = (element, style, rect) => style.display !== 'none'
        && style.visibility !== 'hidden'
        && Number(style.opacity || 1) !== 0
        && rect.width > 1
        && rect.height > 1;
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
        .filter(({ rect, style }) => isVisible(null, style, rect));
    const undersizedControls = controls
        .filter(({ rect }) => rect.height < 32 || rect.width < 28)
        .slice(0, 12)
        .map(({ element, rect }) => ({ selector: selectorFor(element), width: Math.round(rect.width), height: Math.round(rect.height) }));

    const headingElements = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, .card-title, [data-ui="title"]'))
        .map((element) => ({ element, rect: element.getBoundingClientRect(), style: getComputedStyle(element) }))
        .filter(({ rect, style }) => isVisible(null, style, rect));
    const interactiveElements = Array.from(document.querySelectorAll('button, a.btn, [role="button"], input:not([type="hidden"]), select, textarea'))
        .filter((element) => !element.classList.contains('select2-hidden-accessible') && element.getAttribute('aria-hidden') !== 'true')
        .map((element) => ({ element, rect: element.getBoundingClientRect(), style: getComputedStyle(element) }))
        .filter(({ rect, style }) => isVisible(null, style, rect));
    const interactiveOverlaps = [];

    headingElements.forEach(({ element: heading, rect: headingRect }) => {
        interactiveElements.forEach(({ element: control, rect: controlRect }) => {
            if (heading.contains(control) || control.contains(heading)) return;

            const overlapWidth = Math.min(headingRect.right, controlRect.right) - Math.max(headingRect.left, controlRect.left);
            const overlapHeight = Math.min(headingRect.bottom, controlRect.bottom) - Math.max(headingRect.top, controlRect.top);
            if (overlapWidth > 2 && overlapHeight > 2) {
                interactiveOverlaps.push({
                    heading: selectorFor(heading),
                    control: selectorFor(control),
                    overlapWidth: Math.round(overlapWidth),
                    overlapHeight: Math.round(overlapHeight),
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
    await waitForEndpoint(`http://127.0.0.1:${port}/json/version`);
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
    await waitForPage(client, "location.pathname !== '/login' && document.readyState === 'complete'");

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

    const summary = {
        generatedAt: new Date().toISOString(),
        baseUrl,
        routes,
        viewports,
        results,
        failures: results.filter((result) => !result.applicationError && (result.document.horizontalOverflow || result.horizontalOffenders.length > 0 || result.interactiveOverlaps.length > 0 || result.runtimeErrors.length > 0)),
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
