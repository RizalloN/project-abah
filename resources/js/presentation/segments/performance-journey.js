import { PolygonJourney } from './polygon-journey.js';
import { BRANCH_LABELS } from './territory-data.js';
import { h } from './format.js';

export const PERFORMANCE_KEYS = ['simpanan', 'pinjaman', 'kualitas', 'recovery'];
const TITLES = { simpanan: 'Kinerja Simpanan', pinjaman: 'Kinerja Pinjaman', kualitas: 'Kinerja Kualitas', recovery: 'Kinerja Recovery DH' };
const READINGS = { simpanan: 'Produk, komposisi dana, dan pertumbuhan unit.', pinjaman: 'Segmen, produk kredit, dan kontribusi unit.', kualitas: 'Rasio SML, NPL, dan kualitas setiap unit.', recovery: 'Pemulihan daftar hitam, tren, dan kontribusi unit.' };

export function validPerformancePayload(payload, scope, referencePeriod) {
    return Boolean(payload && typeof payload.available === 'boolean' && payload.scope?.key === scope
        && (!payload.available || Array.isArray(payload.entities) && typeof payload.period === 'string'
            && (!referencePeriod || payload.period === referencePeriod)));
}

/** Source KC snapshots are loaded before selection is committed. No outlet totals are used as KC series. */
export class PerformanceJourney extends PolygonJourney {
    constructor(options) {
        super({ ...options, keys: PERFORMANCE_KEYS });
        this.load = options.load;
        this.overview = options.overview;
        this.presentationState = options.presentationState || {};
        if (!['sml', 'npl'].includes(this.presentationState.qualityMetric)) this.presentationState.qualityMetric = 'sml';
        this.requestId = 0;
        this.pending = null;
        this.qualityFrame = null;
    }

    init() {
        super.init();
        this.records.forEach(record => {
            record.chapter.classList.add('sg-performance-chapter');
            record.chapter.dataset.performanceTheme = record.key;
            record.slot.setAttribute('aria-label', `Peta ${TITLES[record.key]}, pilih kantor cabang`);
            if (record.key === 'kualitas') record.chapter.classList.add('sg-quality-tour');
        });
        const signal = this.events.signal;
        window.addEventListener('scroll', () => this.scheduleQualityRead(), { passive: true, signal });
        window.addEventListener('resize', () => this.scheduleQualityRead(), { passive: true, signal });
        return this;
    }

    qualityTrack(record) {
        const opener = record.chapter.querySelector('.sg-opener');
        const stage = opener?.querySelector('.sg-opener__sticky');
        if (!opener || !stage || !opener.getClientRects().length) return null;
        return { start: opener.getBoundingClientRect().top + window.scrollY,
            travel: Math.max(0, opener.offsetHeight - stage.offsetHeight) };
    }

    scheduleQualityRead() {
        const record = this.records.get('kualitas');
        if (this.qualityFrame !== null || !record || !this.available(record) || record.view !== 'map' || this.pending || this.transition) return;
        this.qualityFrame = window.requestAnimationFrame(() => {
            this.qualityFrame = null;
            if (!this.available(record) || record.view !== 'map' || this.pending || this.transition
                || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const track = this.qualityTrack(record);
            if (!track || track.travel < 1) return;
            const chrome = document.querySelector('.sg-room-head')?.getBoundingClientRect().bottom || 0;
            const progress = Math.max(0, Math.min(1, (window.scrollY - track.start + chrome) / track.travel));
            const current = this.presentationState.qualityMetric;
            const metric = progress > .58 ? 'npl' : progress < .42 ? 'sml' : current;
            if (metric !== current) this.setQualityMetric(record, metric);
        });
    }

    setQualityMetric(record, metric, { explicit = false } = {}) {
        if (!['sml', 'npl'].includes(metric) || !this.available(record) || record.view !== 'map' || this.pending || this.transition) return;
        if (metric !== this.presentationState.qualityMetric) {
            const scrollY = window.scrollY;
            this.presentationState.qualityMetric = metric;
            record.mapOverview = true;
            this.renderSelection(record.key, null, this.overview);
            this.setView(record, 'map');
            this.paintHud(record); this.paintDetailHeader(record); this.paintTargets(record);
            record.overlay.hidden = true;
            super.prepareRoomMap(record, true);
            window.scrollTo({ top: scrollY, behavior: 'instant' });
            this.rescan(record.chapter);
        }
        record.chapter.dataset.qualityMetric = metric;
        if (explicit && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            const track = this.qualityTrack(record);
            if (track?.travel > 0) {
                const chrome = document.querySelector('.sg-room-head')?.getBoundingClientRect().bottom || 0;
                window.scrollTo({ top: Math.max(0, track.start - chrome + (metric === 'npl' ? track.travel : 0)), behavior: 'instant' });
            }
        }
        if (explicit) record.chapter.querySelector(`[data-quality-metric="${metric}"]`)?.focus({ preventScroll: true });
    }

    cancelTransition() {
        this.requestId = (this.requestId || 0) + 1;
        if (this.pending) {
            const record = this.pending;
            this.pending = null;
            record.chapter.classList.remove('is-performance-loading');
            record.chapter.removeAttribute('aria-busy');
            this.paintHud(record);
            this.room.main.dataset.roomPhase = 'idle';
            this.room.deck.dataset.roomPhase = 'idle';
        }
        super.cancelTransition();
    }

    selectionFor(branchKey) {
        const allowed = branchKey === this.scope.key || this.scope.key === 'area6' && Object.hasOwn(BRANCH_LABELS, branchKey);
        if (!allowed) return null;
        const codes = branchKey === 'area6'
            ? new Set(Object.keys(BRANCH_LABELS).flatMap(key => [...this.index.codesOfBranch(key)]))
            : this.index.scopeCodes(branchKey);
        return codes?.size ? { level: 'branch', branchKey, label: BRANCH_LABELS[branchKey] || this.scope.label, codes,
            unitCode: null, unitCodes: [], districtCode: null } : null;
    }

    async choose(record, input) {
        if (!this.available(record) || record.view !== 'map' || this.transition) return;
        const selection = this.selectionFor(input.scopeKey || this.index.branchOf(input.code));
        if (!selection) return;
        this.cancelTransition();
        const owner = this.requestId;
        this.pending = record;
        record.chapter.classList.add('is-performance-loading');
        record.chapter.setAttribute('aria-busy', 'true');
        this.room.main.dataset.roomPhase = 'loading';
        this.room.deck.dataset.roomPhase = 'loading';
        this.room.head.querySelector('[data-room-back]').textContent = 'Kembali ke peta';
        this.paintHud(record);
        this.prompt(record, `Menyiapkan ${TITLES[record.key]} ${selection.label}…`);
        try {
            const payload = selection.branchKey === this.scope.key && this.overview ? this.overview : await this.load(selection.branchKey);
            if (owner !== this.requestId || !this.available(record)) return;
            if (!validPerformancePayload(payload, selection.branchKey, this.overview?.period)) throw new Error('Scope or period of performance response does not match selection');
            this.applySelection(record, selection, payload);
            this.pending = null;
            record.chapter.classList.remove('is-performance-loading');
            record.chapter.removeAttribute('aria-busy');
            await this.moveRoom(record, 'detail');
        } catch (error) {
            if (owner !== this.requestId || !this.available(record)) return;
            this.cancelTransition();
            this.room.commit({ key: record.key, view: 'map', context: this.scope.label }, 'replace');
            this.prompt(record, `Data ${selection.label} belum dapat dimuat. Pilih wilayah kembali untuk mencoba lagi.`);
            console.warn('Performance branch could not be loaded.', error);
        }
    }

    applySelection(record, selection, payload) {
        record.selection = selection;
        record.branchLabel = selection.label;
        record.payload = payload;
        record.mapOverview = false;
        record.unlocked = true;
        record.chapter.dataset.branchSelected = selection.branchKey;
        record.chapter.classList.remove('is-awaiting-choice');
        this.renderSelection(record.key, selection, payload);
        this.setView(record, record.view);
        this.paintHud(record); this.paintDetailHeader(record); this.paintOverlay(record); this.paintTargets(record);
        this.studio.setOverride(record.key, selection.codes);
        record.slot.querySelectorAll('[data-code]').forEach(path => path.setAttribute('aria-pressed', String(selection.codes.has(path.dataset.code))));
    }

    async restoreSelection(record, state) {
        const selection = this.selectionFor(state.branchKey);
        if (!selection) return false;
        this.cancelTransition();
        const owner = this.requestId;
        this.pending = record;
        record.chapter.classList.add('is-performance-loading');
        record.chapter.setAttribute('aria-busy', 'true');
        this.room.main.dataset.roomPhase = 'loading';
        this.room.deck.dataset.roomPhase = 'loading';
        try {
            const payload = selection.branchKey === this.scope.key && this.overview ? this.overview : await this.load(selection.branchKey);
            if (owner !== this.requestId || !this.available(record) || !validPerformancePayload(payload, selection.branchKey, this.overview?.period)) return false;
            this.applySelection(record, selection, payload);
            return true;
        } catch {
            return false;
        } finally {
            if (owner === this.requestId) {
                this.pending = null;
                record.chapter.classList.remove('is-performance-loading');
                record.chapter.removeAttribute('aria-busy');
                this.room.main.dataset.roomPhase = 'idle';
                this.room.deck.dataset.roomPhase = 'idle';
            }
        }
    }

    prepareRoomMap(record) {
        // Rebuild from the authorized overview, including its exact figures and all branch labels.
        this.renderSelection(record.key, null, this.overview);
        record.mapOverview = true;
        this.setView(record, 'map');
        this.paintHud(record); this.paintDetailHeader(record);
        record.overlay.hidden = true;
        super.prepareRoomMap(record, true);
        if (record.key === 'kualitas') record.chapter.dataset.qualityMetric = this.presentationState.qualityMetric;
    }

    reset(record) {
        if (this.pending) { this.cancelTransition(); this.finishRoom(record, 'map', 'replace'); return; }
        if (this.transition) return;
        this.prepareRoomMap(record);
        this.finishRoom(record, 'map', 'replace');
    }

    paintTargets(record) {
        this.studio.setInteractiveTargets(record.key, []);
        const hint = record.slot.querySelector('.sg-polygon-map-hint');
        if (hint) hint.textContent = 'Klik wilayah untuk membuka kinerja kantor cabang';
    }

    paintOverlay(record) {
        record.overlay.hidden = !record.selection;
        if (record.selection) record.overlay.innerHTML = `<p class="sg-branch-overlay__kicker">${record.selection.branchKey === 'area6' ? 'Konsolidasi terpilih' : 'Kantor cabang terpilih'}</p><h3 class="sg-branch-overlay__name">${h(record.selection.label)}</h3><p class="sg-branch-overlay__detail">${h(TITLES[record.key])}</p>`;
    }

    paintHud(record) {
        record.chapter.querySelector('[data-polygon-hud]')?.remove();
        const hud = document.createElement('div');
        hud.className = 'sg-polygon-hud sg-performance-invitation';
        hud.dataset.polygonHud = record.key;
        hud.innerHTML = `<p class="sg-performance-invitation__title">Pilih kantor cabang pada peta.</p><p>${h(READINGS[record.key])}</p><div class="sg-performance-invitation__actions">${this.scope.key === 'area6' ? '<button type="button" class="kn-button" data-performance-area>Lihat konsolidasi Area</button>' : '<button type="button" class="kn-button" data-performance-area>Buka kinerja cabang</button>'}</div><p class="sg-polygon-hud__state" role="status">${this.pending === record ? 'Menyiapkan data cabang…' : 'Satu pilihan membuka ruang presentasi cabang.'}</p>`;
        record.chapter.querySelector('.sg-opener__copy').append(hud);
        hud.querySelector('[data-performance-area]').addEventListener('click', () => this.choose(record, { scopeKey: this.scope.key }));
        if (record.key === 'kualitas') {
            const metric = this.presentationState?.qualityMetric || 'sml';
            record.chapter.dataset.qualityMetric = metric;
            const choices = document.createElement('div');
            choices.className = 'sg-quality-tour__choices';
            choices.setAttribute('role', 'group');
            choices.setAttribute('aria-label', 'Ukuran kualitas pada peta');
            choices.innerHTML = `<p>Jelajahi kualitas cabang</p><div>${[['sml', 'SML', 'Special Mention Loan'], ['npl', 'NPL', 'Non-Performing Loan']].map(([key, label, detail]) => `<button type="button" data-quality-metric="${key}" aria-pressed="${key === metric}"><strong>${label}</strong><small>${detail}</small></button>`).join('')}</div><small class="sg-quality-tour__scroll">Scroll dari SML ke NPL, atau pilih ukuran langsung. Klik polygon untuk membuka cabang.</small>`;
            choices.addEventListener('click', event => {
                const button = event.target.closest('[data-quality-metric]');
                if (button) this.setQualityMetric(record, button.dataset.qualityMetric, { explicit: true });
            });
            hud.prepend(choices);
        }
        if (this.scope.key !== 'area6' && this.allowArea) {
            const area = document.createElement('button');
            area.type = 'button'; area.className = 'kn-button sg-area-choice'; area.dataset.polygonArea = record.key;
            area.textContent = 'Lihat Area 6 Konsol';
            area.addEventListener('click', () => this.onArea?.(record.key));
            hud.querySelector('.sg-performance-invitation__actions').append(area);
        }
    }

    paintDetailHeader(record) {
        const sheet = record.chapter.querySelector('.sg-sheet');
        let header = sheet.querySelector('.sg-detail-head');
        if (!header) { header = document.createElement('header'); header.className = 'sg-detail-head'; sheet.prepend(header); }
        const recoverySource = record.key === 'recovery' ? record.payload?.recovery?.source || this.overview?.recovery?.source : null;
        const period = recoverySource?.period_label || record.payload?.period_label || this.overview?.period_label || '';
        header.innerHTML = `<div class="sg-detail-head__copy"><small>${h(record.selection?.label || this.scope.label)}</small><h3 class="sg-detail-head__title" tabindex="-1">${h(TITLES[record.key])}</h3><p class="sg-performance-context">${h(READINGS[record.key])} ${h(period)}${recoverySource ? ' · Sumber Data PH.' : ''}</p></div><div class="sg-detail-summary"></div>`;
        this.syncSummary(record, record.view);
    }

    destroy() {
        if (this.qualityFrame !== null) window.cancelAnimationFrame(this.qualityFrame);
        this.qualityFrame = null;
        super.destroy();
    }
}
