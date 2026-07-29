import { PresentationOfflineStore } from './pres-offline-store';

const mergePayload = (base, patch) => {
    if (!base || typeof base !== 'object') {
        return structuredClone(patch || {});
    }
    if (!patch || typeof patch !== 'object') {
        return structuredClone(base);
    }

    return {
        ...base,
        ...patch,
        loading: {
            ...(base.loading || {}),
            ...(patch.loading || {}),
        },
    };
};

const mergeSummaryPayload = (base, summary) => {
    if (!base || typeof base !== 'object') {
        return mergePayload(base, summary);
    }

    const patch = structuredClone(summary || {});
    [
        'micro',
        'productivity',
        'timeseries',
        'cover_card_timeseries',
        'digital_strategy',
    ].forEach((key) => {
        if (patch?.[key]?.loading && base?.[key] && !base[key].loading) {
            delete patch[key];
        }
    });

    return mergePayload(base, patch);
};

const withPeriod = (source, period) => {
    const url = new URL(source, window.location.origin);
    if (period) {
        url.searchParams.set('periode', period);
    }
    return url;
};

export class PresentationDataLoader {
    constructor(config, offlineStore = new PresentationOfflineStore()) {
        this.config = config || {};
        this.offlineStore = offlineStore;
        this.timeout = 30000;
        this.summaryRefreshTimeout = 120000;
        this.aggregate = null;
        this.progressiveContext = null;
        this.deferredSections = [];
        this.loadedSections = new Set();
        this.sectionPromises = new Map();
        this.idlePreloadTimer = null;
        this.onlineRefreshBound = false;
        this.requestedSlideIndex = 0;
    }

    async registerServiceWorker() {
        if (!('serviceWorker' in navigator) || !this.config.serviceWorkerUrl) {
            return null;
        }

        try {
            const registration = await navigator.serviceWorker.register(this.config.serviceWorkerUrl, {
                scope: '/',
            });
            if ('sync' in registration) {
                registration.sync.register('presentation-data-refresh').catch(() => {});
            }
            if (!this.onlineRefreshBound) {
                this.onlineRefreshBound = true;
                window.addEventListener('online', () => {
                    navigator.serviceWorker.controller?.postMessage({
                        type: 'REFRESH_PRESENTATION_DATA',
                    });
                });
            }

            return registration;
        } catch (error) {
            console.warn('Presentation service worker registration failed.', error);
            return null;
        }
    }

    async fetchJson(source, period, timeout = this.timeout) {
        const controller = new AbortController();
        const timeoutId = window.setTimeout(() => controller.abort(), timeout);

        try {
            const response = await fetch(withPeriod(source, period), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error(`Presentation data request failed (${response.status}).`);
            }

            return await response.json();
        } finally {
            window.clearTimeout(timeoutId);
        }
    }

    detailUrl(section) {
        return String(this.config.detailDataUrl || '')
            .replace('__SECTION__', encodeURIComponent(section));
    }

    async fetchDetail(section, period, onStatus = () => {}) {
        const startedAt = Date.now();
        while ((Date.now() - startedAt) < this.summaryRefreshTimeout) {
            const response = await this.fetchJson(
                this.detailUrl(section),
                period,
                section === 'micro' ? this.summaryRefreshTimeout : this.timeout,
            );
            if (!response?.pending) {
                return response;
            }

            onStatus('cache-warming');
            await this.wait(Math.max(500, Number(response.retry_after_ms) || 1500));
        }

        throw new Error(`Detail presentasi "${section}" belum selesai dibentuk oleh worker.`);
    }

    isStalePayload(payload) {
        return payload?.meta?.cache_state === 'stale-refreshing';
    }

    wait(milliseconds) {
        return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
    }

    async fetchFreshSummary(period, {
        onStatus = () => {},
        onStale = () => {},
    } = {}) {
        const startedAt = Date.now();
        let lastStalePayload = null;
        let staleRendered = false;

        while ((Date.now() - startedAt) < this.summaryRefreshTimeout) {
            const response = await this.fetchJson(
                this.config.summaryDataUrl || this.config.dataUrl,
                period,
            );
            const retryAfter = Math.max(500, Number(response?.retry_after_ms) || 1500);

            if (response?.pending) {
                onStatus('cache-warming');
                await this.wait(retryAfter);
                continue;
            }

            const payload = response?.payload || response;
            if (!payload || typeof payload !== 'object') {
                throw new Error('Presentation summary returned an invalid payload.');
            }

            if (!this.isStalePayload(payload)) {
                return { payload, fresh: true, stale: false };
            }

            lastStalePayload = payload;
            onStatus(payload?.meta?.cache_state === 'summary-only'
                ? 'summary-ready'
                : 'cache-refreshing');
            if (!staleRendered) {
                staleRendered = true;
                onStale(payload);
            }

            return { payload, fresh: true, stale: true };
        }

        if (lastStalePayload) {
            return { payload: lastStalePayload, fresh: false };
        }

        throw new Error('Cache presentasi belum selesai dibentuk oleh worker.');
    }

    sectionNamesForSlide(index) {
        const sections = new Set();
        const slideMap = {
            2: ['timeseries'],
            3: ['timeseries'],
            4: ['digital'],
            5: ['timeseries'],
            6: ['productivity', 'timeseries'],
            7: ['productivity', 'timeseries'],
            8: ['micro'],
            9: ['timeseries'],
            10: ['timeseries'],
            11: ['timeseries'],
            12: ['digital'],
        };

        [index - 1, index, index + 1].forEach((slideIndex) => {
            (slideMap[slideIndex] || []).forEach((section) => sections.add(section));
        });

        return Array.from(sections);
    }

    async completeProgressiveLoadIfReady() {
        if (!this.progressiveContext || this.progressiveContext.completed) {
            return;
        }

        const allLoaded = this.deferredSections.every((section) => this.loadedSections.has(section));
        if (!allLoaded) {
            return;
        }

        this.progressiveContext.completed = true;
        this.aggregate = mergePayload(this.aggregate, {
            loading: {
                progressive: true,
                complete: true,
            },
        });
        await this.offlineStore.put(
            this.progressiveContext.period || this.aggregate?.meta?.period,
            this.aggregate,
        );
        this.progressiveContext.onComplete(this.aggregate, { source: 'network-progressive' });
    }

    async loadSection(section) {
        if (!this.progressiveContext || !this.deferredSections.includes(section)) {
            return this.aggregate;
        }
        if (this.loadedSections.has(section)) {
            return this.aggregate;
        }
        if (this.sectionPromises.has(section)) {
            return this.sectionPromises.get(section);
        }

        const promise = (async () => {
            this.progressiveContext.onStatus(`detail:${section}`);
            const detailResponse = await this.fetchDetail(
                section,
                this.progressiveContext.period,
                this.progressiveContext.onStatus,
            );
            const detail = detailResponse?.payload || detailResponse;
            this.aggregate = mergePayload(this.aggregate, detail);
            this.loadedSections.add(section);
            this.progressiveContext.onSection(section, this.aggregate);
            await this.completeProgressiveLoadIfReady();

            return this.aggregate;
        })()
            .catch((error) => {
                console.warn(`Presentation detail "${section}" could not be loaded.`, error);
                this.progressiveContext?.onStatus(`detail-error:${section}`);
                return this.aggregate;
            })
            .finally(() => {
                this.sectionPromises.delete(section);
            });

        this.sectionPromises.set(section, promise);
        return promise;
    }

    preloadForSlide(index) {
        this.requestedSlideIndex = Number(index) || 0;
        const sections = this.sectionNamesForSlide(this.requestedSlideIndex);
        if (!sections.length) {
            return Promise.resolve(this.aggregate);
        }

        return Promise.all(sections.map((section) => this.loadSection(section)));
    }

    scheduleIdlePreload() {
        if (this.idlePreloadTimer !== null) {
            window.clearTimeout(this.idlePreloadTimer);
        }

        this.idlePreloadTimer = window.setTimeout(() => {
            const remaining = this.deferredSections
                .filter((section) => !this.loadedSections.has(section));
            Promise.allSettled(remaining.map((section) => this.loadSection(section)));
        }, 1200);
    }

    beginProgressiveLoad(period, sections, callbacks) {
        this.deferredSections = Array.from(new Set(sections.filter(Boolean)));
        this.loadedSections.clear();
        this.sectionPromises.clear();
        this.progressiveContext = {
            period,
            completed: false,
            ...callbacks,
        };

        if (!this.deferredSections.length) {
            this.completeProgressiveLoadIfReady();
            return;
        }

        this.preloadForSlide(this.requestedSlideIndex);
        if (this.deferredSections.includes('micro')) {
            this.loadSection('micro');
        }
        this.scheduleIdlePreload();
    }

    async load({
        onSummary = () => {},
        onSection = () => {},
        onComplete = () => {},
        onStatus = () => {},
    } = {}) {
        const period = this.config.selectedPeriod || '';
        const serverData = this.config.serverData || null;

        this.registerServiceWorker();

        if (serverData && !this.isStalePayload(serverData)) {
            onStatus('server-cache');
            this.aggregate = serverData;
            onSummary(serverData, { source: 'server-cache', complete: true });
            onComplete(serverData, { source: 'server-cache' });
            this.offlineStore.put(period || serverData?.meta?.period, serverData);
            this.beginProgressiveLoad(
                period,
                ['micro'],
                { onSection, onComplete, onStatus },
            );
            return serverData;
        }

        let aggregate = serverData || await this.offlineStore.get(period);
        if (serverData) {
            onStatus('cache-refreshing');
            onSummary(serverData, { source: 'server-stale', stale: true, complete: true });
            this.offlineStore.put(period || serverData?.meta?.period, serverData);
        } else if (aggregate) {
            onStatus('offline-cache');
            onSummary(aggregate, { source: 'offline-cache', stale: true, complete: true });
        }

        try {
            onStatus('summary');
            const summaryResult = await this.fetchFreshSummary(period, {
                onStatus,
                onStale: (staleSummary) => {
                    aggregate = mergeSummaryPayload(aggregate, staleSummary);
                    onSummary(aggregate, {
                        source: 'server-stale',
                        stale: true,
                        complete: Boolean(serverData),
                    });
                },
            });
            const summary = summaryResult.payload;
            aggregate = mergeSummaryPayload(aggregate, summary);
            this.aggregate = aggregate;
            onSummary(aggregate, {
                source: summaryResult.stale
                    ? 'server-stale'
                    : (summaryResult.fresh ? 'network-summary' : 'server-stale'),
                stale: Boolean(summaryResult.stale) || !summaryResult.fresh,
                complete: false,
            });

            if (!summaryResult.fresh) {
                aggregate = mergePayload(aggregate, {
                    loading: {
                        progressive: true,
                        complete: true,
                    },
                });
                await this.offlineStore.put(period || aggregate?.meta?.period, aggregate);
                onComplete(aggregate, { source: 'server-stale', stale: true });
                return aggregate;
            }

            this.beginProgressiveLoad(
                period,
                summary?.loading?.deferred_sections || [],
                { onSection, onComplete, onStatus },
            );
            return aggregate;
        } catch (error) {
            if (aggregate) {
                onStatus('offline-fallback');
                onComplete(aggregate, { source: 'offline-fallback', error });
                return aggregate;
            }

            const latest = await this.offlineStore.latest();
            if (latest) {
                onStatus('offline-fallback');
                onSummary(latest, { source: 'offline-fallback', stale: true, complete: true });
                onComplete(latest, { source: 'offline-fallback', error });
                return latest;
            }

            throw error;
        }
    }

    async loadPeriod(period) {
        const cached = await this.offlineStore.get(period);
        try {
            const payload = await this.fetchJson(this.config.dataUrl, period);
            await this.offlineStore.put(period || payload?.meta?.period, payload);
            return payload;
        } catch (error) {
            if (cached) {
                return cached;
            }
            throw error;
        }
    }
}

export { mergePayload };
