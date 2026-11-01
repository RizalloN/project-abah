import { h } from './format.js';
import { PanelTransition } from './panel-transition.js';

const HISTORY_KEY = 'asixPresentationRoom';
const GEO = new Set(['marketshare', 'mapping', 'sektoral']);
const GATEWAYS = new Set([...GEO, 'simpanan', 'pinjaman', 'kualitas', 'recovery']);

/** Move focus only; Enter/Space retain native explicit topic activation. */
export function topicFocusIndex(key, index, count) {
    if (!count || index < 0 || index >= count) return null;
    if (key === 'Home') return 0;
    if (key === 'End') return count - 1;
    if (key === 'ArrowRight' || key === 'ArrowDown') return (index + 1) % count;
    if (key === 'ArrowLeft' || key === 'ArrowUp') return (index + count - 1) % count;
    return null;
}

/** Carry only the chosen office, never a stale unit/subsector, into a different topic. */
export function topicContext(state, key, keys) {
    return state?.key !== key && state?.view === 'detail' && typeof state.branchKey === 'string' && state.branchKey && keys.includes(key)
        ? { key, view: 'detail', branchKey: state.branchKey } : null;
}

export function normalizeRoomState(value, keys) {
    if (!value || !keys.includes(value.key)) return null;
    const geographic = GATEWAYS.has(value.key);
    return { key: value.key, view: geographic ? value.view === 'detail' ? 'detail' : 'map' : 'report',
        branchKey: geographic && typeof value.branchKey === 'string' ? value.branchKey : null,
        unitCode: geographic && typeof value.unitCode === 'string' ? value.unitCode : null,
        districtCode: geographic && typeof value.districtCode === 'string' ? value.districtCode : null };
}

/** Hidden siblings retain their actual source nodes, listeners and controls, but no layout or tab stops. */
export function showOnlyRoom(chapters, key) {
    for (const chapter of chapters) {
        const active = chapter.dataset.sgSegment === key;
        chapter.hidden = !active;
        chapter.inert = !active;
        chapter.setAttribute('aria-hidden', String(!active));
        if (active) chapter.dataset.roomActive = 'true';
        else delete chapter.dataset.roomActive;
    }
}

export class PresentationRoom {
    constructor({ main, deck, launcher, nav, labels, onNavigate, onBack, onLauncher, onLeave, onArrive, onArriveCancel, onFinale }) {
        Object.assign(this, { main, deck, launcher, nav, labels, onNavigate, onBack, onLauncher, onLeave, onArrive, onArriveCancel, onFinale });
        this.keys = [];
        this.activeKey = null;
        this.state = null;
        this.events = new AbortController();
        const params = new URLSearchParams(window.location.search);
        this.initial = { key: params.get('materi'), view: params.get('halaman') === 'detail' ? 'detail' : 'map', branchKey: params.get('kc') };
    }

    init() {
        this.main.classList.add('sg-room-mode');
        this.head = document.createElement('nav');
        this.head.className = 'sg-room-head';
        this.head.hidden = true;
        this.head.setAttribute('aria-label', 'Kontrol halaman presentasi');
        this.head.innerHTML = `<button type="button" class="sg-room-head__back" data-room-back>Pilihan presentasi</button>
            <div class="sg-room-head__identity"><small data-room-scope></small><strong data-room-title></strong><span data-room-section hidden></span></div>
            <div class="sg-room-head__chapters"><span data-room-count></span><button type="button" data-room-step="-1" aria-label="Materi sebelumnya" title="Materi sebelumnya">←</button><button type="button" data-room-chooser aria-expanded="false" aria-controls="sg-room-topics">Semua materi <span aria-hidden="true">⌄</span></button><button type="button" data-room-step="1" aria-label="Materi berikutnya" title="Materi berikutnya">→</button></div>
            <div class="sg-room-route" aria-label="Jalur materi presentasi" data-room-route></div>
            <div class="sg-room-topics" id="sg-room-topics" hidden><p>Materi presentasi</p><div data-room-topic-list></div></div>`;
        this.deck.before(this.head);
        this.portal = document.createElement('section');
        this.portal.className = 'sg-room-portal';
        this.portal.hidden = true;
        this.portal.setAttribute('aria-label', 'Transisi kantor cabang');
        this.portal.innerHTML = `<div class="sg-room-portal__world" aria-hidden="true"><i></i><i></i><i></i></div><div class="sg-room-portal__copy"><span class="sg-room-portal__signature" aria-hidden="true">ABAH</span><small data-room-portal-topic></small><h2 data-room-kc></h2><p data-room-portal-status role="status"></p><span class="sg-room-portal__line" aria-hidden="true"><i></i></span><div class="sg-room-portal__steps" aria-hidden="true"><span>Wilayah</span><i></i><span>Cabang</span><i></i><span>Informasi</span></div></div>`;
        this.main.append(this.portal);
        const signal = this.events.signal;
        window.addEventListener('resize', () => this.positionRoute(), { signal, passive: true });
        window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', () => this.cancelArrival(), { signal });
        document.addEventListener('click', (event) => {
            const sectionLink = event.target.closest('.sg-performance-path a[href^="#performance-"]');
            if (sectionLink && !this.deck.hidden) {
                const target = document.getElementById(sectionLink.hash.slice(1));
                if (target && target.closest('[data-sg-segment]')?.dataset.sgSegment === this.activeKey) {
                    event.preventDefault();
                    target.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
                    const heading = target.querySelector('h3');
                    if (heading) { heading.tabIndex = -1; heading.focus({ preventScroll: true }); }
                }
                return;
            }
            const back = event.target.closest('[data-room-back], [data-room-end-back]');
            const chooser = event.target.closest('[data-room-chooser]');
            const step = event.target.closest('[data-room-step]');
            const finale = event.target.closest('[data-room-finale]');
            if (finale) { event.preventDefault(); event.stopPropagation(); this.showFinale(); return; }
            const topic = event.target.closest('[data-room-topic], a[data-sg-nav-link], a[data-story-point], .sg-close a[href^="#sg-"]');
            if (back) { event.preventDefault(); event.stopPropagation(); this.back(); return; }
            if (chooser) { event.preventDefault(); this.toggleTopics(); return; }
            if (step && !step.disabled) {
                event.preventDefault();
                const delta = Number(step.dataset.roomStep);
                const key = this.state?.view === 'finale' ? (delta < 0 ? this.keys.at(-1) : null) : this.keys[this.keys.indexOf(this.activeKey) + delta];
                if (key) this.onNavigate(key);
                else if (delta > 0 && this.state?.view !== 'finale') this.showFinale();
                return;
            }
            if (topic && !this.deck.hidden) {
                event.preventDefault(); event.stopPropagation();
                const key = topic.dataset.roomTopic || topic.dataset.sgNavLink || topic.dataset.storyPoint || topic.hash.slice(4);
                this.toggleTopics(false);
                if (this.keys.includes(key)) this.onNavigate(key);
                return;
            }
            if (!event.target.closest('.sg-room-head')) this.toggleTopics(false);
        }, { signal, capture: true });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !this.head.querySelector('.sg-room-topics').hidden) {
                event.preventDefault(); event.stopPropagation(); this.toggleTopics(false);
                this.head.querySelector('[data-room-chooser]').focus();
                return;
            }
            const group = event.target.closest('[data-room-topic-list], [data-room-route]');
            if (!group || !this.head.contains(group) || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
            const buttons = [...group.querySelectorAll('[data-room-topic]')];
            const index = buttons.indexOf(event.target.closest('[data-room-topic]'));
            const target = topicFocusIndex(event.key, index, buttons.length);
            if (target === null) return;
            event.preventDefault();
            buttons[target]?.focus({ preventScroll: true });
            buttons[target]?.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'instant' });
        }, { signal, capture: true });
        document.addEventListener('focusin', (event) => {
            if (!this.head.contains(event.target)) this.toggleTopics(false);
            if (this.main.dataset.roomPhase && this.main.dataset.roomPhase !== 'idle' && this.deck.contains(event.target)) {
                this.head.querySelector('[data-room-back]').focus({ preventScroll: true });
            }
        }, { signal });
        window.addEventListener('popstate', (event) => {
            const value = event.state?.[HISTORY_KEY];
            if (!value || value.signature !== this.signature || !value.key) { this.onLauncher({ history: 'replace' }); return; }
            if (value.view === 'finale') { this.showFinale('replace'); return; }
            const target = normalizeRoomState(value, this.keys);
            if (target) this.onNavigate(target.key, { restore: target, history: 'replace' });
            else this.onLauncher({ history: 'replace' });
        }, { signal });
        if ('ResizeObserver' in window) {
            this.resize = new ResizeObserver(() => {
                if (!this.head.hidden) this.main.style.setProperty('--sg-room-offset', `${this.head.offsetHeight}px`);
                this.positionRoute();
            });
            this.resize.observe(this.head);
        }
        this.writeHistory({ key: null, view: 'launcher' }, 'replace');
        return this;
    }

    mount(keys, scope) {
        this.keys = [...keys];
        this.scope = scope;
        this.signature = `${scope.key}:${keys.join(',')}`;
        this.head.querySelector('[data-room-topic-list]').innerHTML = keys.map((key) => `<button type="button" data-room-topic="${h(key)}"><small>${GEO.has(key) ? 'Wilayah' : 'Kinerja'}</small><strong>${h(this.labels[key])}</strong><span aria-hidden="true">↗</span></button>`).join('');
        this.head.querySelector('[data-room-route]').innerHTML = '<span class="sg-room-route__cursor" aria-hidden="true" hidden></span>' + keys.map((key, i) => `<button type="button" data-room-topic="${h(key)}" data-room-route-topic="${h(key)}"><small>${String(i + 1).padStart(2, '0')}</small><span>${h(this.labels[key].replace('Kinerja ', ''))}</span></button>`).join('');
        this.chapters = [...this.deck.querySelectorAll('[data-sg-segment]')];
        this.chapters.forEach((chapter) => {
            if (!keys.includes(chapter.dataset.sgSegment)) return;
            const next = keys[keys.indexOf(chapter.dataset.sgSegment) + 1];
            const end = document.createElement('footer');
            end.className = 'sg-room-end';
            end.innerHTML = `<div><small>Materi selesai</small><h3>${h(this.labels[chapter.dataset.sgSegment])}</h3><p>Pilih langkah berikutnya untuk melanjutkan presentasi.</p></div><div class="sg-room-end__actions"><button type="button" class="kn-button" data-room-end-back>${GATEWAYS.has(chapter.dataset.sgSegment) ? 'Kembali ke peta' : 'Pilihan presentasi'}</button>${next ? `<button type="button" class="kn-button kn-button--primary" data-room-topic="${h(next)}">Lanjut ke ${h(this.labels[next])}</button>` : '<button type="button" class="kn-button kn-button--primary" data-room-finale>Tutup presentasi</button>'}</div>`;
            chapter.append(end);
        });
        showOnlyRoom(this.chapters, null);
        this.activeKey = null;
        this.state = null;
        this.writeHistory({ key: null, view: 'launcher' }, 'replace');
    }

    activate(key) {
        if (!this.keys.includes(key)) return false;
        this.cancelArrival();
        this.previousKey = this.activeKey;
        this.onLeave(this.activeKey);
        showOnlyRoom(this.chapters, key);
        this.activeKey = key;
        this.deck.dataset.roomKey = key;
        this.launcher.hidden = true;
        this.launcher.inert = true;
        this.head.hidden = false;
        this.toggleTopics(false);
        window.scrollTo({ top: 0, behavior: 'instant' });
        return true;
    }

    commit({ key = this.activeKey, view, selection = null, context = null }, history = 'push') {
        if (key !== this.activeKey) return;
        const next = normalizeRoomState({ key, view, branchKey: selection?.branchKey, unitCode: selection?.unitCode, districtCode: selection?.districtCode }, this.keys);
        if (!next) return;
        const unchanged = JSON.stringify(this.state) === JSON.stringify(next);
        this.state = next;
        this.main.dataset.roomView = next.view;
        this.deck.dataset.roomView = next.view;
        this.head.querySelector('[data-room-scope]').textContent = context || this.scope?.label || '';
        this.head.querySelector('[data-room-title]').textContent = this.labels[key];
        const section = this.head.querySelector('[data-room-section]');
        section.textContent = next.view === 'detail' ? 'Informasi cabang' : next.view === 'map' ? 'Peta wilayah' : 'Laporan';
        section.hidden = false;
        this.head.querySelector('[data-room-back]').textContent = next.view === 'detail' ? 'Kembali ke peta' : 'Pilihan presentasi';
        this.head.querySelector('[data-room-count]').textContent = `${this.keys.indexOf(key) + 1} / ${this.keys.length}`;
        this.head.querySelectorAll('[data-room-topic]').forEach((button) => { button.setAttribute('aria-current', button.dataset.roomTopic === key ? 'page' : 'false'); });
        this.head.querySelectorAll('[data-room-step]').forEach(button => {
            const target = this.keys[this.keys.indexOf(key) + Number(button.dataset.roomStep)];
            const closes = !target && Number(button.dataset.roomStep) > 0;
            button.disabled = !target && !closes;
            button.title = closes ? 'Berikutnya: Penutup' : target ? `${Number(button.dataset.roomStep) < 0 ? 'Sebelumnya' : 'Berikutnya'}: ${this.labels[target]}` : 'Tidak ada materi lain';
        });
        const route = this.head.querySelector('[data-room-route]');
        const current = route?.querySelector(`[data-room-route-topic="${key}"]`);
        if (current && (current.offsetLeft < route.scrollLeft || current.offsetLeft + current.offsetWidth > route.scrollLeft + route.clientWidth)) route.scrollLeft = Math.max(0, current.offsetLeft - route.clientWidth / 2 + current.offsetWidth / 2);
        this.positionRoute();
        if (!unchanged || history === 'replace') this.writeHistory(next, history);
    }

    /** The agenda's last frame: the whole territory reassembled, with where to go next. */
    showFinale(history = 'push') {
        if (!this.chapters?.some((chapter) => chapter.dataset.sgSegment === 'penutup')) { this.onLauncher({ history }); return; }
        this.cancelArrival();
        this.onLeave(this.activeKey);
        this.previousKey = null;
        showOnlyRoom(this.chapters, 'penutup');
        this.activeKey = null;
        this.state = { key: 'penutup', view: 'finale' };
        this.deck.dataset.roomKey = 'penutup';
        this.main.dataset.roomView = 'finale';
        this.deck.dataset.roomView = 'finale';
        this.head.hidden = false;
        this.toggleTopics(false);
        this.head.querySelector('[data-room-scope]').textContent = this.scope?.label || '';
        this.head.querySelector('[data-room-title]').textContent = 'Penutup';
        const section = this.head.querySelector('[data-room-section]');
        section.textContent = '';
        section.hidden = true;
        this.head.querySelector('[data-room-back]').textContent = 'Pilihan presentasi';
        this.head.querySelector('[data-room-count]').textContent = 'Selesai';
        this.head.querySelectorAll('[data-room-topic]').forEach((button) => button.setAttribute('aria-current', 'false'));
        this.head.querySelector('.sg-room-route__cursor')?.setAttribute('hidden', '');
        this.head.querySelectorAll('[data-room-step]').forEach((button) => {
            const back = Number(button.dataset.roomStep) < 0;
            button.disabled = !back;
            button.title = back ? `Sebelumnya: ${this.labels[this.keys.at(-1)]}` : 'Tidak ada materi lain';
        });
        window.scrollTo({ top: 0, behavior: 'instant' });
        this.writeHistory(this.state, history);
        this.onFinale?.();
    }

    writeHistory(state, mode = 'push') {
        if (mode === 'none') return;
        const url = new URL(window.location.href);
        for (const key of ['materi', 'halaman', 'kc']) url.searchParams.delete(key);
        if (state.key) {
            url.searchParams.set('materi', state.key);
            url.searchParams.set('halaman', state.view === 'map' ? 'peta' : state.view);
            if (state.view === 'detail' && state.branchKey) url.searchParams.set('kc', state.branchKey);
        }
        url.hash = state.key ? `sg-${state.key}` : '';
        const previous = window.history.state?.[HISTORY_KEY];
        const value = { ...state, signature: this.signature, parent: mode === 'push' && previous ? { key: previous.key, view: previous.view, signature: previous.signature } : previous?.parent || null };
        window.history[mode === 'replace' ? 'replaceState' : 'pushState']({ ...(window.history.state || {}), [HISTORY_KEY]: value }, '', url);
    }

    back() {
        if (this.main.dataset.roomPhase && this.main.dataset.roomPhase !== 'idle') { this.onBack(); return; }
        if (this.state?.view !== 'detail') { this.onLauncher(); return; }
        const parent = window.history.state?.[HISTORY_KEY]?.parent;
        if (parent?.signature === this.signature && parent.key === this.activeKey && parent.view === 'map') window.history.back();
        else this.onBack();
    }

    showLauncher(history = 'push') {
        this.cancelArrival();
        this.onLeave(this.activeKey);
        showOnlyRoom(this.chapters || [], null);
        this.activeKey = null;
        this.state = null;
        delete this.deck.dataset.roomKey;
        this.head.hidden = true;
        this.portal.hidden = true;
        this.launcher.hidden = false;
        this.launcher.inert = false;
        this.toggleTopics(false);
        this.writeHistory({ key: null, view: 'launcher' }, history);
    }

    toggleTopics(open = this.head.querySelector('.sg-room-topics').hidden) {
        const panel = this.head.querySelector('.sg-room-topics');
        const trigger = this.head.querySelector('[data-room-chooser]');
        const restoreFocus = !open && !this.head.hidden && panel.contains(document.activeElement);
        panel.hidden = !open;
        trigger.setAttribute('aria-expanded', String(open));
        if (restoreFocus) trigger.focus({ preventScroll: true });
        if (open) {
            const current = this.head.querySelector(`[data-room-topic-list] [data-room-topic="${this.activeKey}"]`);
            current?.focus({ preventScroll: true });
            current?.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'instant' });
        }
    }

    cancelArrival() { this.arrival?.cancel(); this.arrival = null; this.onArriveCancel?.(); }

    positionRoute() {
        if (!this.head || this.head.hidden) return;
        const route = this.head.querySelector('[data-room-route]');
        const cursor = route?.querySelector('.sg-room-route__cursor');
        const current = route?.querySelector('[data-room-route-topic][aria-current="page"]');
        if (!cursor || !current?.offsetWidth) return;
        cursor.hidden = false;
        cursor.style.width = `${current.offsetWidth}px`;
        cursor.style.height = `${current.offsetHeight}px`;
        cursor.style.transform = `translate3d(${current.offsetLeft}px, ${current.offsetTop}px, 0)`;
    }

    async animateArrival() {
        this.cancelArrival();
        if (!this.previousKey || this.previousKey === this.activeKey || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const chapter = this.chapters.find(node => node.dataset.sgSegment === this.activeKey);
        const surface = chapter?.querySelector(this.state?.view === 'detail' ? '.sg-sheet' : '.sg-opener__copy');
        if (!surface || surface.hidden) return;
        const direction = this.keys.indexOf(this.activeKey) >= this.keys.indexOf(this.previousKey) ? 1 : -1;
        if (this.onArrive) { this.onArrive(chapter, this.state?.view, direction); return; }
        const owner = this.arrival = new PanelTransition();
        await owner.animate(surface, [{ opacity: .18, transform: `translate3d(${direction * 26}px, 0, 0)` }, { opacity: 1, transform: 'translate3d(0, 0, 0)' }], { duration: 520, easing: 'cubic-bezier(.16,1,.3,1)', fill: 'none' });
        if (this.arrival === owner) this.cancelArrival();
    }

    phase(record, phase, destination) {
        this.cancelArrival();
        this.main.dataset.roomPhase = phase;
        this.deck.dataset.roomPhase = phase;
        this.portal.dataset.roomPhase = phase;
        this.portal.dataset.performanceTheme = GEO.has(record.key) ? '' : record.key;
        this.portal.querySelector('[data-room-kc]').textContent = record.selection?.label && record.selection.level === 'branch' ? record.selection.label : record.branchLabel;
        this.portal.querySelector('[data-room-portal-topic]').textContent = this.labels[record.key];
        this.head.querySelector('[data-room-scope]').textContent = record.branchLabel;
        this.portal.querySelector('[data-room-portal-status]').textContent = destination === 'detail' ? 'Memasuki ruang informasi cabang' : 'Kembali melihat wilayah cabang';
        this.head.querySelector('[data-room-back]').textContent = phase === 'idle' && record.view !== 'detail' ? 'Pilihan presentasi' : 'Kembali ke peta';
    }

    paintPortalMap(record) {
        this.portal.querySelector('.sg-room-portal__map')?.remove();
        const source = record.slot.querySelector('[data-territory-poster] svg');
        const viewBox = source?.getAttribute('viewBox');
        const values = viewBox?.trim().split(/\s+/).map(Number);
        if (!values || values.length !== 4 || !values.every(Number.isFinite) || values[2] <= 0 || values[3] <= 0) return null;
        const map = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        map.setAttribute('class', 'sg-room-portal__map');
        map.setAttribute('viewBox', viewBox);
        map.setAttribute('aria-hidden', 'true');
        map.setAttribute('focusable', 'false');
        // Decorative copy of the actual selected geography; never duplicate its interactive nodes.
        source.querySelectorAll('[data-code]').forEach(path => {
            if (!record.selection?.codes.has(path.dataset.code)) return;
            const shape = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            shape.setAttribute('d', path.getAttribute('d'));
            shape.setAttribute('pathLength', '1');
            map.append(shape);
        });
        this.portal.querySelector('.sg-room-portal__world').prepend(map);
        // Fit only the selected geography. If SVG measurement is unavailable, retain the valid source bounds.
        try {
            const box = map.getBBox();
            if ([box.x, box.y, box.width, box.height].every(Number.isFinite) && box.width > 0 && box.height > 0) {
                const pad = Math.max(box.width, box.height) * .12;
                map.setAttribute('viewBox', `${box.x - pad} ${box.y - pad} ${box.width + pad * 2} ${box.height + pad * 2}`);
                // pathLength draws the outline; a non-scaling stroke would ignore it, so scale the line here.
                map.style.strokeWidth = String(Math.max(box.width, box.height) * .0022);
            }
        } catch { /* Decorative fitting must never interrupt branch navigation. */ }
        return map;
    }
}
