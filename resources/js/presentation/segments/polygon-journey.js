import { h } from './format.js';
import { BRANCH_LABELS } from './territory-data.js';
import { POLYGON_CHAPTERS, resolvePolygonSelection } from './polygon-selection.js';
import { PanelTransition } from './panel-transition.js';

const still = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const go = (node) => node?.scrollIntoView({ behavior: still() ? 'auto' : 'smooth', block: 'start' });
const branchLabel = key => key === 'area6' ? 'Area 6 Konsol' : BRANCH_LABELS[key];

/** Explicit KC selection owns the map-to-detail journey and its reversible camera transition. */
export class PolygonJourney {
    constructor({ deck, scope, index, studio, renderSelection, rescan, room = null, keys = POLYGON_CHAPTERS, allowArea = false, onArea = null }) {
        Object.assign(this, { deck, scope, index, studio, renderSelection, rescan, room, keys, allowArea, onArea });
        this.records = new Map();
        this.events = new AbortController();
        this.disposed = false;
    }

    init() {
        this.deck.classList.add('sg-branch-story');
        for (const key of this.keys) {
            const chapter = this.deck.querySelector(`#sg-${key}`);
            const slot = chapter?.querySelector('[data-territory]');
            if (!slot || !this.index.ready || !this.studio.states.has(key)) continue;
            const record = { key, chapter, slot, selection: null, unlocked: false, view: 'map', mapOverview: true };
            this.records.set(key, record);
            const overlay = document.createElement('aside');
            overlay.className = 'sg-branch-overlay';
            overlay.hidden = true;
            overlay.setAttribute('aria-live', 'polite');
            slot.append(overlay);
            record.overlay = overlay;
            chapter.addEventListener('polygonshowmap', () => this.showMap(record), { signal: this.events.signal });
            chapter.classList.add('sg-polygon-chapter', 'is-awaiting-choice');
            this.setSheet(record, false);
            slot.removeAttribute('aria-hidden');
            slot.dataset.polygonInteractive = 'true';
            slot.setAttribute('role', 'group');
            slot.tabIndex = -1;
            slot.setAttribute('aria-label', `Peta ${key}, pilih wilayah untuk membuka rincian`);
            const mapHint = document.createElement('p');
            mapHint.className = 'sg-polygon-map-hint';
            mapHint.textContent = 'Klik wilayah untuk memilih kantor cabang';
            slot.append(mapHint);
            this.studio.interactions.set(key, (spec) => this.decorate(record, spec));
            this.decorate(record, this.studio.states.get(key));
            this.bindSurface(record);
            this.paintHud(record);
            this.paintDetailHeader(record);
        }
        this.rail = document.createElement('nav');
        this.rail.className = 'sg-story-rail';
        this.rail.setAttribute('aria-label', 'Materi wilayah');
        this.rail.hidden = true;
        this.rail.innerHTML = `<div class="sg-story-rail__identity"><small>Kantor cabang</small><strong data-story-branch></strong></div><div class="sg-story-rail__points">${[...this.records.keys()].map((key, index) => `<a href="#sg-${key}" data-story-point="${key}"><span aria-hidden="true">${String(index + 1).padStart(2, '0')}</span>${({ marketshare: 'Marketshare', mapping: 'Mapping wilayah', sektoral: 'Penetrasi sektoral' })[key]}</a>`).join('')}</div><button type="button" data-story-reset>Ubah KC</button>`;
        this.deck.prepend(this.rail);
        this.rail.querySelector('[data-story-reset]').addEventListener('click', () => this.resetBranch());
        const signal = this.events.signal;
        const interrupt = () => this.cancelTransition();
        window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', (event) => {
            if (event.matches) this.finishTransition();
        }, { signal });
        window.addEventListener('wheel', () => { if (!this.room) interrupt(); }, { passive: true, signal });
        window.addEventListener('touchmove', () => { if (!this.room) interrupt(); }, { passive: true, signal });
        document.addEventListener('keydown', (event) => {
            if (this.room) {
                if (event.key === 'Escape' && !this.deck.hidden && !event.target.closest('[data-choice-field]')) {
                    const current = this.records.get(this.room.activeKey);
                    if (current && this.pending) { event.preventDefault(); this.cancelTransition(); this.finishRoom(current, 'map', 'replace'); }
                    else if (current && (this.transition || current.view === 'detail')) { event.preventDefault(); this.showMap(current, { history: 'replace' }); }
                }
                return;
            }
            if (['PageUp', 'PageDown', 'Home', 'End', 'ArrowUp', 'ArrowDown'].includes(event.key)) interrupt();
            if (event.key === 'Escape' && !this.deck.hidden) {
                if (this.transition) { event.preventDefault(); interrupt(); return; }
                const readingTop = Math.max(0, document.querySelector('.sg-nav')?.getBoundingClientRect().bottom || 0,
                    this.rail?.hidden ? 0 : this.rail?.getBoundingClientRect().bottom || 0);
                const current = [...this.records.values()].filter((record) => record.view === 'detail')
                    .map((record) => { const box = record.chapter.getBoundingClientRect(); return { record, visible: Math.min(box.bottom, window.innerHeight) - Math.max(box.top, readingTop) }; })
                    .filter((item) => item.visible > 0).sort((a, b) => b.visible - a.visible)[0]?.record;
                if (current && !event.target.closest('[data-choice-field]')) { event.preventDefault(); this.showMap(current); }
                else interrupt();
            }
        }, { signal });
        window.addEventListener('scroll', () => this.enforce(), { passive: true, signal });
        window.addEventListener('resize', () => this.enforce(), { passive: true, signal });
        document.addEventListener('click', (event) => {
            if (this.room) return;
            if (this.deck.hidden) return;
            const link = event.target.closest('a[href^="#sg-"]');
            const target = link && document.getElementById(link.hash.slice(1));
            const point = target && this.records.get(target.dataset.sgSegment);
            if (point?.selection && link.matches('[data-story-point], [data-sg-nav-link]')) {
                event.preventDefault();
                this.showDetail(point);
                return;
            }
            const locked = this.firstLocked();
            if (!target || !locked || this.topOf(target) <= this.boundary(locked) + 1) return;
            event.preventDefault();
            go(locked.chapter);
            this.prompt(locked, 'Pilih wilayah pada peta ini untuk melanjutkan.');
            locked.slot.querySelector('[data-code][tabindex="0"]')?.focus({ preventScroll: true });
        }, { signal });
        this.enforce();
        return this;
    }

    decorate(record, spec) {
        spec.allowedCodes = this.index.scopeCodes(this.scope.key);
        spec.activate = (code) => code ? this.choose(record, { code }) : this.reset(record);
        spec.interactive = true;
        // A camera tour cannot choose on behalf of the user.
        if (spec.journey?.length > 1) spec.journey = [{ ...spec.journey[0], until: 1 }];
        const arrange = spec.arrangement;
        spec.arrangement = (progress, reduced) => ({ ...(arrange?.(progress, reduced) || {}), explode: 0, spread: 0,
            levitate: record.selection && !reduced ? 0.5 : 0, levitateBranch: record.selection?.branchKey || null, grow: 1, wave: 0 });
        record.sourceJourney = spec.journey || [];
        const labelsFor = spec.labelsFor;
        spec.labelsFor = (codes) => record.mapOverview ? this.branchLabels(record, labelsFor?.(codes) || []) : labelsFor?.(codes) || [];
        if (record.mapOverview) spec.journey = record.sourceJourney.map((stop) => ({ ...stop, focus: this.index.scopeCodes(this.scope.key), labels: this.branchLabels(record, stop.labels || []) }));
    }

    branchLabels(record, labels) {
        return (this.scope.key === 'area6' ? Object.keys(BRANCH_LABELS) : [this.scope.key]).map((branch) => {
            const codes = this.index.codesOfBranch(branch);
            const source = labels.find((item) => item.codes && [...item.codes].some((code) => codes.has(code)));
            return { ...source, id: `pick-${record.key}-${branch}`, codes, title: BRANCH_LABELS[branch], value: source?.value || 'Pilih wilayah',
                action: () => this.choose(record, { code: [...codes][0], branchChoice: true }), ariaLabel: `Pilih ${BRANCH_LABELS[branch]} pada peta ${record.key}` };
        });
    }

    bindSurface(record) {
        const svg = record.slot.querySelector('[data-territory-poster] svg');
        if (!svg) return;
        svg.dataset.polygonSurface = record.key;
        svg.setAttribute('role', 'group');
        svg.setAttribute('aria-label', 'Polygon wilayah. Gunakan tombol arah lalu Enter untuk memilih.');
        svg.removeAttribute('focusable');
        const paths = [...svg.querySelectorAll('[data-code]')];
        const branches = new Set();
        const eligible = paths.filter((path) => {
            const branch = this.index.branchOf(path.dataset.code);
            const allowed = Boolean(branch && (this.scope.key === 'area6' || branch === this.scope.key));
            path.setAttribute('aria-hidden', String(!allowed));
            if (!allowed) { path.style.pointerEvents = 'none'; return false; }
            path.setAttribute('role', 'button');
            path.setAttribute('aria-label', `Pilih ${this.index.nameOf(path.dataset.code).district}, ${BRANCH_LABELS[branch]}`);
            path.setAttribute('tabindex', branches.has(branch) ? '-1' : '0');
            branches.add(branch);
            path.addEventListener('click', (event) => { event.stopPropagation(); this.choose(record, { code: path.dataset.code }); }, { signal: this.events.signal });
            return true;
        });
        svg.addEventListener('keydown', (event) => {
            const path = event.target.closest('[data-code]');
            if (!path || !eligible.includes(path)) return;
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.choose(record, { code: path.dataset.code }); return; }
            const delta = ['ArrowRight', 'ArrowDown'].includes(event.key) ? 1 : ['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 0;
            if (!delta) return;
            event.preventDefault();
            const next = eligible[(eligible.indexOf(path) + delta + eligible.length) % eligible.length];
            eligible.forEach((item) => item.setAttribute('tabindex', item === next ? '0' : '-1'));
            next.focus();
        }, { signal: this.events.signal });
        svg.addEventListener('focusin', (event) => {
            record.slot.classList.add('is-keyboard-map');
            const path = event.target.closest('[data-code]');
            if (!path) return;
            const box = path.getBBox();
            const view = svg.viewBox.baseVal;
            if (box.x < view.x || box.y < view.y || box.x + box.width > view.x + view.width || box.y + box.height > view.y + view.height) svg.setAttribute('viewBox', '0 0 600 560');
        }, { signal: this.events.signal });
        svg.addEventListener('focusout', (event) => {
            if (!svg.contains(event.relatedTarget)) record.slot.classList.remove('is-keyboard-map');
        }, { signal: this.events.signal });
    }

    async choose(record, input) {
        if (!this.available(record) || record.view !== 'map') return;
        if (this.room) {
            if (this.transition) return;
            const selection = resolvePolygonSelection({ key: record.key, current: record.mapOverview || input.branchChoice ? null : record.selection, scope: this.scope, index: this.index, ...input });
            if (!selection?.codes.size) { this.prompt(record, 'Wilayah ini belum memiliki pemetaan unit. Pilih polygon lainnya.'); return; }
            this.applyRoomSelection(record, selection);
            await this.moveRoom(record, 'detail');
            return;
        }
        const selection = resolvePolygonSelection({ key: record.key, current: input.branchChoice ? null : record.selection, scope: this.scope, index: this.index, ...input });
        if (!selection?.codes.size) { this.prompt(record, 'Wilayah ini belum memiliki pemetaan unit. Pilih polygon lainnya.'); return; }
        this.cancelTransition();
        const task = this.transition = new PanelTransition();
        this.transitionRecord = record;
        this.transitionDestination = 'detail';
        const changingBranch = this.branchKey !== selection.branchKey;
        this.branchKey = selection.branchKey;
        const affected = [...this.records.values()].filter((item) => changingBranch || item === record || !item.unlocked);
        const viewportTop = record.chapter.getBoundingClientRect().top;
        for (const item of affected) {
            item.selection = item === record ? selection : { level: 'branch', branchKey: selection.branchKey, unitCode: null, unitCodes: [], districtCode: null,
                label: branchLabel(selection.branchKey), codes: this.index.codesOfBranch(selection.branchKey) };
            item.mapOverview = item !== record && item.view === 'map';
            item.unlocked = true;
            item.chapter.classList.remove('is-awaiting-choice', 'is-choice-ready');
            item.chapter.classList.toggle('is-focusing-choice', item === record);
            item.chapter.dataset.branchSelected = selection.branchKey;
            this.renderSelection(item.key, item.selection);
            this.setView(item, item.view);
            this.paintHud(item);
            this.paintDetailHeader(item);
            this.paintOverlay(item);
            this.studio.setOverride(item.key, item.mapOverview ? this.index.scopeCodes(this.scope.key) : item.selection.codes);
            this.paintTargets(item);
            item.slot.querySelectorAll('[data-code]').forEach((path) => path.setAttribute('aria-pressed', String(item.selection.codes.has(path.dataset.code))));
        }
        window.scrollTo({ top: this.topOf(record.chapter) - viewportTop, behavior: 'instant' });
        task.cleanup(() => { record.chapter.classList.remove('is-focusing-choice'); this.paintHud(record); });
        this.studio.appliedKey = null;
        this.studio.kick();
        record.slot.dispatchEvent(new CustomEvent('territoryselect', { bubbles: true, detail: { key: record.key, selection, explicit: true } }));
        this.rail.querySelector('[data-story-branch]').textContent = branchLabel(this.branchKey);
        this.rail.hidden = false;
        this.enforce();
        if (!still() && !(await task.wait(620))) return;
        if (!this.available(record) || !task.active) { this.cancelTransition(); return; }
        await this.movePanel(record, 'detail', task);
    }

    paintOverlay(record) {
        const selection = record.selection;
        record.overlay.hidden = !selection;
        if (!selection) return;
        record.overlay.innerHTML = `<p class="sg-branch-overlay__kicker">${selection.branchKey === 'area6' ? 'Konsolidasi area' : 'Kantor cabang terpilih'}</p><h3 class="sg-branch-overlay__name">${h(branchLabel(selection.branchKey))}</h3><p class="sg-branch-overlay__detail">${selection.branchKey === 'area6' ? 'Seluruh kantor cabang Area 6' : selection.level === 'branch' ? 'Seluruh wilayah cabang' : h(selection.label)}</p>`;
    }

    paintTargets(record) {
        const selection = record.selection;
        const mapHint = record.slot.querySelector('.sg-polygon-map-hint');
        const detail = selection?.level === 'district' ? 'Klik pin untuk memilih satu unit'
            : selection?.level === 'branch' ? record.key === 'marketshare' ? 'Pangsa BRI terhadap industri' : 'Klik kecamatan untuk menelusuri unit' : selection?.label;
        if (mapHint) mapHint.textContent = selection ? `${branchLabel(selection.branchKey)} · ${detail}` : 'Klik wilayah untuk memilih kantor cabang';
        const units = selection?.level === 'district' ? this.index.unitsInScope(selection.branchKey).filter((unit) => selection.unitCodes.includes(unit.code)) : [];
        const targets = units.map((unit) => ({ id: `unit-pick-${record.key}-${unit.code}`, codes: this.index.codesOfUnit(unit), title: unit.name,
            value: 'Lihat unit', ariaLabel: `Pilih Unit ${unit.name}`, action: () => this.choose(record, { unitCode: unit.code }) }));
        this.studio.setInteractiveTargets(record.key, targets);
        const svg = record.slot.querySelector('[data-polygon-surface]');
        svg?.querySelector('[data-unit-targets]')?.remove();
        if (!svg || !targets.length) return;
        const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        group.dataset.unitTargets = 'true';
        // These are labelled pins anchored to the clicked polygon, not a geographic dropdown.
        const path = svg.querySelector(`[data-code="${CSS.escape(selection.districtCode)}"]`);
        const box = path?.getBBox();
        if (box) svg.setAttribute('viewBox', `${box.x + box.width / 2 - 135} ${box.y + box.height / 2 - 70} 270 ${Math.max(160, targets.length * 42 + 80)}`);
        targets.forEach((target, i) => {
            const pin = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            pin.setAttribute('class', 'sg-polygon-unit');
            pin.setAttribute('transform', `translate(${box ? box.x + box.width / 2 : 300},${(box ? box.y + box.height / 2 : 280) + i * 42})`);
            pin.setAttribute('role', 'button'); pin.setAttribute('tabindex', '0'); pin.setAttribute('aria-label', target.ariaLabel);
            pin.innerHTML = `<rect x="-100" y="-16" width="200" height="34" rx="12"/><text text-anchor="middle" dy="5">${h(target.title)}</text>`;
            pin.addEventListener('click', (event) => { event.stopPropagation(); target.action(); });
            pin.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); target.action(); } });
            group.append(pin);
        });
        svg.append(group);
    }

    paintHud(record) {
        const copy = record.chapter.querySelector('.sg-opener__copy');
        copy.querySelector('[data-polygon-hud]')?.remove();
        const hud = document.createElement('div');
        hud.className = 'sg-polygon-hud'; hud.dataset.polygonHud = record.key;
        const selection = record.selection;
        hud.innerHTML = selection ? `
            <p class="sg-polygon-hud__breadcrumb"><span>${h(branchLabel(selection.branchKey))}</span>${selection.level !== 'branch' ? `<span>${h(selection.label)}</span>` : ''}</p>
            <p class="sg-polygon-hud__state" role="status">${record.chapter.classList.contains('is-focusing-choice') ? `Mendekat ke ${h(branchLabel(selection.branchKey))}…` : 'Pilih polygon untuk menelusuri unit, atau buka informasi wilayah ini.'}</p>
            ${selection.level === 'district' ? `<p class="sg-polygon-hud__hint">${selection.unitCodes.length} unit melayani kecamatan ini. Klik pin unit di peta untuk melihat satu unit.</p>` : record.key !== 'marketshare' && selection.level === 'branch' ? '<p class="sg-polygon-hud__hint">Klik kecamatan untuk menelusuri wilayah layanan unit.</p>' : ''}
            <div class="sg-polygon-hud__actions"><button type="button" data-polygon-reset>${selection.level === 'branch' ? 'Ubah KC' : 'Kembali ke cabang'}</button><button type="button" data-polygon-continue ${record.unlocked ? '' : 'disabled'}>Buka informasi <span aria-hidden="true">→</span></button></div>` : `
            <div class="sg-polygon-hud__invitation"><span class="sg-polygon-cue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3.5 18.5 12l-5.6 1.4 3.3 6.1-2.4 1.2-3.2-6.1L6 18.4z" fill="currentColor"/></svg></span><p>Klik wilayah di peta.<small>${record.key === 'marketshare' ? 'Pilih cabang untuk membuka perbandingan BRI dan industri.' : 'Pilih polygon untuk membuka potensi dan rincian wilayahnya.'}</small></p></div>
            <p class="sg-polygon-hud__state" role="status">Rincian terbuka setelah Anda memilih wilayah.</p>`;
        copy.append(hud);
        if (this.scope.key === 'area6' || this.allowArea) {
            const area = document.createElement('button');
            area.type = 'button'; area.className = 'kn-button sg-area-choice'; area.dataset.polygonArea = record.key;
            area.textContent = 'Lihat Area 6 Konsol';
            area.addEventListener('click', () => this.scope.key === 'area6' ? this.choose(record, { scopeKey: 'area6' }) : this.onArea?.(record.key));
            hud.append(area);
        }
        hud.querySelector('[data-polygon-reset]')?.addEventListener('click', () => this.reset(record));
        hud.querySelector('[data-polygon-continue]')?.addEventListener('click', () => this.showDetail(record));
    }

    available(record) {
        return !this.disposed && !this.deck.hidden && !this.deck.inert && record.chapter.isConnected && !record.chapter.inert
            && !record.chapter.hidden && (!this.room || this.room.activeKey === record.key);
    }

    paintDetailHeader(record) {
        const sheet = record.chapter.querySelector('.sg-sheet');
        sheet.querySelector('[data-polygon-detail-head]')?.remove();
        const header = document.createElement('header');
        header.className = 'sg-detail-head';
        header.dataset.polygonDetailHead = record.key;
        header.innerHTML = `<button type="button" class="kn-button" data-polygon-back>Kembali ke peta</button><div class="sg-detail-head__copy"><small>${h(record.selection ? branchLabel(record.selection.branchKey) : this.scope.label)}${record.selection && record.selection.level !== 'branch' ? ` / ${h(record.selection.label)}` : ''}</small><h3 class="sg-detail-head__title" tabindex="-1">${({ marketshare: 'Marketshare BRI dan industri', mapping: 'Mapping wilayah', sektoral: 'Penetrasi sektoral' })[record.key]}</h3></div><div class="sg-detail-summary"></div>`;
        sheet.prepend(header);
        header.querySelector('[data-polygon-back]').addEventListener('click', () => this.showMap(record));
        if (this.room && record.key !== 'marketshare' && record.selection) {
            const explore = document.createElement('button');
            explore.type = 'button'; explore.className = 'kn-button sg-room-explore';
            explore.textContent = record.selection.level === 'unit' ? 'Lihat wilayah layanan unit' : 'Jelajahi unit pada peta';
            explore.dataset.roomExplore = record.key;
            explore.addEventListener('click', () => this.showMap(record, { overview: false }));
            header.querySelector('.sg-detail-head__copy').append(explore);
        }
        this.syncSummary(record, record.view);
    }

    syncSummary(record, view) {
        if (view === 'map') {
            (record.summaryNodes || []).forEach(({ node, marker }) => { if (marker.isConnected && node.isConnected) marker.replaceWith(node); });
            record.summaryNodes = [];
            return;
        }
        const target = record.chapter.querySelector('.sg-detail-summary');
        if (!target || record.summaryNodes?.some(({ node }) => target.contains(node))) return;
        record.summaryNodes = [...record.chapter.querySelectorAll('.sg-opener__copy > .sg-figure, .sg-opener__copy > .sg-figure__label, .sg-opener__copy > .sg-deltas')].map((node) => {
            const marker = document.createComment('financial-summary');
            node.before(marker);
            target.append(node);
            return { node, marker };
        });
    }

    setView(record, view) {
        record.view = view;
        this.syncSummary(record, view);
        const opener = record.chapter.querySelector('.sg-opener');
        const sheet = record.chapter.querySelector('.sg-sheet');
        for (const [node, visible] of [[opener, view === 'map'], [sheet, view === 'detail']]) {
            node.hidden = !visible;
            node.inert = !visible;
            node.setAttribute('aria-hidden', String(!visible));
        }
        record.chapter.dataset.polygonView = view;
        record.chapter.dataset.polygonState = view === 'detail' ? 'open' : record.selection ? 'ready' : 'locked';
        record.chapter.classList.toggle('is-choice-ready', Boolean(record.selection));
        sheet.tabIndex = -1;
    }

    cancelTransition() {
        const task = this.transition;
        this.transition = null;
        this.transitionRecord = null;
        this.transitionDestination = null;
        task?.cancel();
    }

    /** A changed system preference commits the requested view without further spatial travel. */
    finishTransition() {
        const record = this.transitionRecord;
        const destination = this.transitionDestination;
        if (!this.transition || !record || !destination) return;
        if (this.room) {
            const history = this.transitionHistory || 'push';
            const overview = this.transitionOverview !== false;
            this.cancelTransition();
            if (!this.available(record)) return;
            this.setView(record, destination);
            if (destination === 'map') this.prepareRoomMap(record, overview);
            this.finishRoom(record, destination, history);
            return;
        }
        this.cancelTransition();
        if (!this.available(record)) return;
        this.setView(record, destination);
        if (destination === 'map') this.prepareMap(record);
        this.align(record);
        this.studio.releaseSurface();
        this.studio.scan();
        this.rescan(record.chapter);
        this.enforce();
        const focus = destination === 'detail' ? record.chapter.querySelector('.sg-detail-head__title') : record.slot;
        focus?.focus({ preventScroll: true });
    }

    align(record) {
        const top = Math.max(0, this.topOf(record.chapter) - (document.querySelector('.sg-nav')?.offsetHeight || 0));
        window.scrollTo({ top, behavior: 'instant' });
    }

    prepareMap(record) {
        record.mapOverview = !record.selection || record.selection.level === 'branch';
        const spec = this.studio.states.get(record.key);
        if (spec && record.sourceJourney) spec.journey = record.sourceJourney.map((stop) => record.mapOverview
            ? { ...stop, focus: this.index.scopeCodes(this.scope.key), labels: this.branchLabels(record, stop.labels || []) } : stop);
        this.studio.setOverride(record.key, record.mapOverview ? this.index.scopeCodes(this.scope.key) : record.selection?.codes);
        this.paintTargets(record);
    }

    async showDetail(record) {
        if (!this.available(record) || !record.selection) return;
        if (this.room) { await this.moveRoom(record, 'detail'); return; }
        this.cancelTransition();
        if (record.view === 'detail') { this.align(record); record.chapter.querySelector('.sg-detail-head__title')?.focus({ preventScroll: true }); return; }
        const task = this.transition = new PanelTransition();
        this.transitionRecord = record;
        await this.movePanel(record, 'detail', task);
    }

    async showMap(record, options = {}) {
        if (!this.available(record)) return;
        if (this.room) { await this.moveRoom(record, 'map', options); return; }
        this.cancelTransition();
        if (record.view === 'map') { this.align(record); this.prepareMap(record); this.studio.scan(); return; }
        const task = this.transition = new PanelTransition();
        this.transitionRecord = record;
        await this.movePanel(record, 'map', task);
    }

    async movePanel(record, destination, task) {
        if (!task.active || !this.available(record)) return;
        this.transitionDestination = destination;
        const origin = record.view;
        let finalView = origin;
        const opener = record.chapter.querySelector('.sg-opener');
        const sheet = record.chapter.querySelector('.sg-sheet');
        const copy = opener.querySelector('.sg-opener__copy');
        this.align(record);
        const viewport = window.innerHeight;
        record.chapter.style.setProperty('--sg-panel-height', `${Math.max(viewport, Math.min(viewport * 1.5, opener.offsetHeight || viewport))}px`);
        record.chapter.classList.add('is-panel-transition');
        record.chapter.setAttribute('aria-busy', 'true');
        task.cleanup(() => {
            record.chapter.classList.remove('is-panel-transition', 'is-focusing-choice');
            record.chapter.style.removeProperty('--sg-panel-height');
            record.chapter.removeAttribute('aria-busy');
            this.setView(record, finalView);
            if (this.available(record)) { this.rescan(record.chapter); this.enforce(); }
        });
        for (const node of [opener, sheet]) { node.hidden = false; node.inert = true; node.setAttribute('aria-hidden', 'true'); }
        this.syncSummary(record, destination);
        if (destination === 'map') this.prepareMap(record);
        this.studio.scan();
        // Keep the actual map rectangle stationary: only the visual world planes travel.
        if (!still()) {
            const duration = 1080;
            const options = { duration, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'both' };
            const resting = 'perspective(2400px) translate3d(0,0,0) rotateY(0deg)';
            const incoming = [{ transform: 'perspective(2400px) translate3d(100vw,0,-180px) rotateY(9deg)', opacity: .4 }, { transform: resting, opacity: 1 }];
            const outgoing = [{ transform: resting, opacity: 1 }, { transform: 'perspective(2400px) translate3d(-68vw,0,-120px) rotateY(-8deg)', opacity: 0 }];
            const backOut = [{ transform: resting, opacity: 1 }, { transform: 'perspective(2400px) translate3d(100vw,0,-180px) rotateY(9deg)', opacity: .4 }];
            const backIn = [{ transform: 'perspective(2400px) translate3d(-68vw,0,-120px) rotateY(-8deg)', opacity: 0 }, { transform: resting, opacity: 1 }];
            const animations = [task.animate(sheet, destination === 'detail' ? incoming : backOut, options), task.animate(copy, destination === 'detail' ? outgoing : backIn, options)];
            const worldFrames = destination === 'detail'
                ? [{ transform: resting, opacity: 1 }, { transform: 'perspective(2400px) translate3d(-42vw,0,-220px) rotateY(-18deg)', opacity: 0 }]
                : [{ transform: 'perspective(2400px) translate3d(-42vw,0,-220px) rotateY(-18deg)', opacity: 0 }, { transform: resting, opacity: 1 }];
            animations.push(task.animate(this.studio.layer, worldFrames, options), task.animate(this.studio.labelLayer, worldFrames, options));
            if (!(await task.wait(duration)) || !(await Promise.all(animations)).every(Boolean)) return;
        }
        if (!task.active || !this.available(record)) return;
        finalView = destination;
        this.cancelTransition();
        this.align(record);
        this.studio.releaseSurface();
        this.studio.scan();
        if (destination === 'detail') sheet.querySelector('.sg-detail-head__title')?.focus({ preventScroll: true });
        else { record.slot.classList.remove('is-keyboard-map'); record.slot.focus({ preventScroll: true }); }
    }

    suspend() {
        this.cancelTransition();
        this.records.forEach((record) => { this.setView(record, 'map'); record.slot.classList.remove('is-keyboard-map'); });
    }

    resume() {
        if (this.disposed || this.deck.hidden) return;
        this.records.forEach((record) => { this.setView(record, 'map'); this.prepareMap(record); });
        this.studio.releaseSurface();
        this.studio.scan();
        this.enforce();
    }

    reset(record) {
        if (!record.selection) return;
        if (record.selection.level === 'branch') { this.resetBranch(); return; }
        // Return to the named KC before another unit is explored.
        record.selection = null;
        this.choose(record, { code: [...this.index.codesOfBranch(this.branchKey)][0] });
        if (!this.room) go(record.chapter);
    }

    resetBranch() {
        this.cancelTransition();
        this.branchKey = null;
        this.rail.hidden = true;
        const first = this.room ? this.records.get(this.room.activeKey) : [...this.records.values()][0];
        if (first) window.scrollTo({ top: this.topOf(first.chapter), behavior: 'instant' });
        for (const record of this.records.values()) this.resetRecord(record);
        if (this.room && first) {
            this.prepareRoomMap(first);
            this.finishRoom(first, 'map', 'replace');
        }
        this.enforce();
        first?.slot.focus({ preventScroll: true });
    }

    resetRecord(record) {
        record.selection = null; record.unlocked = false;
        record.mapOverview = true;
        this.setView(record, 'map');
        delete record.chapter.dataset.branchSelected;
        this.renderSelection(record.key, null);
        this.studio.setOverride(record.key, null);
        this.studio.setInteractiveTargets(record.key, []);
        record.slot.querySelectorAll('[data-code]').forEach((path) => path.setAttribute('aria-pressed', 'false'));
        this.paintTargets(record);
        record.chapter.classList.remove('is-choice-ready', 'is-focusing-choice');
        record.chapter.classList.add('is-awaiting-choice');
        this.setSheet(record, false);
        this.paintHud(record);
        this.paintDetailHeader(record);
        this.paintOverlay(record);
        this.rescan(record.chapter);
    }

    setSheet(record, open) {
        this.setView(record, open ? 'detail' : 'map');
    }

    prompt(record, message) {
        const status = record.chapter.querySelector('[data-polygon-hud] [role="status"]');
        if (status) status.textContent = message;
        record.chapter.classList.remove('sg-nudge');
        window.requestAnimationFrame(() => record.chapter.classList.add('sg-nudge'));
    }

    topOf(node) { return node.getBoundingClientRect().top + window.scrollY; }
    firstLocked() { return [...this.records.values()].find((record) => !record.unlocked); }
    boundary(record) {
        const opener = record.chapter.querySelector('.sg-opener');
        const boundary = Math.max(0, this.topOf(opener) + opener.offsetHeight - window.innerHeight);
        return record.chapter.classList.contains('is-focusing-choice') ? Math.max(boundary, record.selectionScrollY ?? boundary) : boundary;
    }
    enforce() {
        if (this.disposed || this.deck.hidden) return;
        if (this.room) {
            if (this.rail) this.rail.hidden = true;
            if (this.transitionRecord && !this.available(this.transitionRecord)) this.cancelTransition();
            return;
        }
        if (this.transitionRecord) {
            const box = this.transitionRecord.chapter.getBoundingClientRect();
            if (box.bottom <= 0 || box.top >= window.innerHeight) this.cancelTransition();
        }
        const record = this.firstLocked();
        const limit = record ? this.boundary(record) : Infinity;
        if (window.scrollY > limit + 1) window.scrollTo({ top: limit, behavior: 'instant' });
        let after = false;
        this.deck.querySelectorAll('[data-sg-segment]').forEach((chapter) => {
            chapter.inert = after;
            if (chapter === record?.chapter) after = true;
        });
        const records = [...this.records.values()];
        // Native anchor scrolling adds both the root padding and the chapter margin.
        const anchorLine = (parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0)
            + (records[0] ? parseFloat(getComputedStyle(records[0].chapter).scrollMarginTop) || 0 : 0);
        const readingLine = Math.max(window.innerHeight * 0.4, anchorLine + 4);
        const chapter = [...this.deck.querySelectorAll('[data-sg-segment]')]
            .filter((item) => item.getBoundingClientRect().top <= readingLine).at(-1);
        const current = chapter && this.records.get(chapter.dataset.sgSegment);
        if (this.rail) this.rail.hidden = !this.branchKey || !current;
        this.rail?.querySelectorAll('[data-story-point]').forEach((point) => point.setAttribute('aria-current', String(point.dataset.storyPoint === current?.key)));
    }

    destroy() {
        this.disposed = true;
        this.cancelTransition();
        this.events.abort();
        this.rail?.remove();
        this.records.forEach((record, key) => { this.studio.interactions.delete(key); this.studio.setInteractiveTargets(key, []); });
    }

    applyRoomSelection(record, selection) {
        const changing = this.branchKey !== selection.branchKey;
        this.branchKey = selection.branchKey;
        const viewportScroll = window.scrollY;
        for (const item of this.records.values()) {
            if (!changing && item !== record && item.unlocked) continue;
            item.selection = item === record ? selection : { level: 'branch', branchKey: selection.branchKey, unitCode: null, unitCodes: [], districtCode: null,
                label: branchLabel(selection.branchKey), codes: selection.branchKey === 'area6' ? selection.codes : this.index.codesOfBranch(selection.branchKey) };
            item.branchLabel = branchLabel(selection.branchKey);
            item.mapOverview = item !== record;
            item.unlocked = true;
            item.chapter.dataset.branchSelected = selection.branchKey;
            item.chapter.classList.remove('is-awaiting-choice');
            this.renderSelection(item.key, item.selection);
            this.setView(item, item.view);
            this.paintHud(item); this.paintDetailHeader(item); this.paintOverlay(item); this.paintTargets(item);
            this.studio.setOverride(item.key, item.mapOverview ? this.index.scopeCodes(this.scope.key) : item.selection.codes);
            item.slot.querySelectorAll('[data-code]').forEach((path) => path.setAttribute('aria-pressed', String(item.selection.codes.has(path.dataset.code))));
        }
        window.scrollTo({ top: viewportScroll, behavior: 'instant' });
        this.studio.appliedKey = null;
        this.studio.kick();
    }

    restoreSelection(record, state) {
        if (state.branchKey === 'area6') {
            const selection = resolvePolygonSelection({ key: record.key, scopeKey: 'area6', scope: this.scope, index: this.index });
            if (!selection) return false;
            this.applyRoomSelection(record, selection);
            return true;
        }
        if (!state.branchKey || !Object.hasOwn(BRANCH_LABELS, state.branchKey) || (this.scope.key !== 'area6' && this.scope.key !== state.branchKey)) return false;
        const code = [...this.index.codesOfBranch(state.branchKey)][0];
        let selection = resolvePolygonSelection({ key: record.key, code, current: null, scope: this.scope, index: this.index });
        if (!selection) return false;
        if (state.districtCode && this.index.branchOf(state.districtCode) === state.branchKey) {
            selection = resolvePolygonSelection({ key: record.key, code: state.districtCode, current: selection, scope: this.scope, index: this.index }) || selection;
        }
        if (state.unitCode && record.key !== 'marketshare') {
            const unit = this.index.unitsInScope(state.branchKey).find((item) => item.code === state.unitCode);
            if (unit) selection = resolvePolygonSelection({ key: record.key, unitCode: unit.code, current: { ...selection, unitCodes: [unit.code] }, scope: this.scope, index: this.index }) || selection;
        }
        this.applyRoomSelection(record, selection);
        return true;
    }

    prepareRoomMap(record, overview = true) {
        if (overview) {
            this.prepareMap(record);
            // Back always returns to the authorized branch overview, also after a unit drilldown.
            record.mapOverview = true;
            const spec = this.studio.states.get(record.key);
            if (spec) spec.journey = record.sourceJourney.map((stop) => ({ ...stop, focus: this.index.scopeCodes(this.scope.key), labels: this.branchLabels(record, stop.labels || []) }));
            this.studio.setOverride(record.key, this.index.scopeCodes(this.scope.key));
        } else {
            record.mapOverview = false;
            const spec = this.studio.states.get(record.key);
            if (spec) spec.journey = record.sourceJourney;
            this.studio.setOverride(record.key, record.selection?.codes);
        }
        this.paintTargets(record);
        this.studio.appliedKey = null;
        this.studio.kick();
    }

    finishRoom(record, destination, history) {
        this.room.main.dataset.roomPhase = 'idle';
        this.room.deck.dataset.roomPhase = 'idle';
        this.room.portal.hidden = true;
        if (destination === 'detail') window.scrollTo({ top: 0, behavior: 'instant' });
        this.studio.scan();
        this.rescan(record.chapter);
        this.room.commit({ key: record.key, view: destination, selection: destination === 'detail' ? record.selection : null,
            context: destination === 'detail' ? `${record.branchLabel}${record.selection?.level !== 'branch' ? ` / ${record.selection.label}` : ''}` : this.scope.label }, history);
        let focus = destination === 'detail' ? record.chapter.querySelector('.sg-detail-head__title') : record.slot;
        if (!focus?.getClientRects().length) focus = this.room.head.querySelector('[data-room-title]');
        if (focus) { focus.tabIndex = -1; focus.focus({ preventScroll: true }); }
    }

    async moveRoom(record, destination, { history = 'push', overview = true } = {}) {
        if (!this.available(record) || destination === 'detail' && !record.selection) return;
        this.cancelTransition();
        if (destination === record.view && destination === 'detail') { this.finishRoom(record, destination, history); return; }
        const origin = record.view;
        let finalView = origin;
        const task = this.transition = new PanelTransition();
        this.transitionRecord = record;
        this.transitionDestination = destination;
        this.transitionHistory = history;
        this.transitionOverview = overview;
        const { portal } = this.room;
        const world = portal.querySelector('.sg-room-portal__world');
        const progress = portal.querySelector('.sg-room-portal__line > i');
        record.chapter.classList.add('is-room-transition');
        record.chapter.setAttribute('aria-busy', 'true');
        document.documentElement.classList.add('sg-room-moving');
        task.cleanup(() => {
            record.chapter.classList.remove('is-room-transition', 'is-focusing-choice');
            record.chapter.removeAttribute('aria-busy');
            document.documentElement.classList.remove('sg-room-moving');
            portal.hidden = true;
            this.room.main.dataset.roomPhase = 'idle';
            this.room.deck.dataset.roomPhase = 'idle';
            this.setView(record, finalView);
            if (this.available(record)) this.rescan(record.chapter);
        });
        const phase = (name) => this.room.phase(record, name, destination);
        const options = (duration) => ({ duration, easing: 'cubic-bezier(.3,0,.15,1)', fill: 'both' });
        const cover = async () => {
            phase('overlay');
            portal.hidden = false;
            const geography = this.room.paintPortalMap(record);
            const identity = task.animate(portal.querySelector('.sg-room-portal__copy'), [
                { opacity: 0, transform: 'translate3d(0, 28px, 0)' },
                { opacity: 1, transform: 'translate3d(0, 0, 0)' },
            ], { duration: 620, delay: 260, easing: 'cubic-bezier(.16,1,.3,1)', fill: 'both' });
            // The real outline of the chosen office draws itself while the title holds.
            [...(geography?.querySelectorAll('path') || [])].slice(0, 48).forEach((path, index) => task.animate(path, [
                { strokeDashoffset: 1 }, { strokeDashoffset: 0 },
            ], { duration: 1100, delay: 120 + Math.min(420, index * 26), easing: 'cubic-bezier(.45,0,.2,1)', fill: 'both' }));
            task.animate(world, [{ transform: 'scale(.74) translateZ(-140px)' }, { transform: 'scale(1.08) translateZ(80px)' }], options(1250));
            if (geography) task.animate(geography, [
                { transform: 'translate3d(12%, 8%, -90px) rotateX(28deg) rotateZ(-8deg)', opacity: .08 },
                { transform: 'translate3d(0, -4%, 90px) rotateX(0deg) rotateZ(0deg)', opacity: .28 },
            ], options(1500));
            task.animate(progress, [{ transform: 'scaleX(.08)' }, { transform: 'scaleX(.82)' }], options(1250));
            // An iris opening from what was just chosen: never a dissolve through the page's figures.
            const box = portal.getBoundingClientRect();
            const pointer = this.studio.pointer;
            const focus = destination === 'detail' && pointer?.seen
                ? { x: pointer.clientX - box.left, y: pointer.clientY - box.top } : { x: box.width * .7, y: box.height * .5 };
            const reach = Math.hypot(Math.max(focus.x, box.width - focus.x), Math.max(focus.y, box.height - focus.y));
            const veil = task.animate(portal, [
                { clipPath: `circle(0px at ${focus.x}px ${focus.y}px)` },
                { clipPath: `circle(${reach}px at ${focus.x}px ${focus.y}px)` },
            ], { duration: 640, easing: 'cubic-bezier(.65,0,.2,1)', fill: 'both' });
            return (await Promise.all([veil, identity])).every(Boolean);
        };
        try {
        if (still()) {
            finalView = destination;
            this.cancelTransition();
            if (destination === 'map') this.prepareRoomMap(record, overview);
            this.finishRoom(record, destination, history);
            return;
        }
        if (destination === 'detail') {
            record.mapScroll = window.scrollY;
            record.mapOverview = false;
            this.studio.setOverride(record.key, record.selection.codes);
            this.studio.appliedKey = null;
            this.studio.kick();
            phase('zoom-in');
            const focus = this.studio.waitForFocus(record.key, { signal: task.controller.signal });
            if (!(await task.wait(520)) || !(await cover())) return;
            const settled = await focus;
            if (!task.active || !this.available(record)) return;
            record.chapter.dataset.roomCamera = settled ? 'settled' : 'bounded-fallback';
            if (!(await task.wait(180))) return;
            phase('entering');
            this.setView(record, 'detail');
            this.studio.releaseSurface();
            window.scrollTo({ top: 0, behavior: 'instant' });
        } else {
            if (!(await cover())) return;
            this.setView(record, 'map');
            record.mapOverview = false;
            this.studio.setOverride(record.key, record.selection?.codes);
            this.studio.releaseSurface();
            window.scrollTo({ top: record.mapScroll || 0, behavior: 'instant' });
            this.studio.scan();
            // Establish the selected branch under the opaque identity before revealing the return journey.
            await this.studio.waitForFocus(record.key, { signal: task.controller.signal, minimum: 0, maximum: 1600 });
            if (!task.active || !this.available(record)) return;
            phase('zoom-out');
            this.prepareRoomMap(record, overview);
        }
        if (!task.active || !this.available(record)) return;
        finalView = destination;
        task.animate(progress, [{ transform: 'scaleX(.82)' }, { transform: 'scaleX(1)' }], options(260));
        // A curtain lifting off the room, so the title is never seen through the report.
        const reveal = task.animate(portal, [
            { clipPath: 'inset(0 0 0 0)' },
            { clipPath: destination === 'detail' ? 'inset(0 0 100% 0)' : 'inset(100% 0 0 0)' },
        ], { duration: 680, easing: 'cubic-bezier(.76,0,.24,1)', fill: 'both' });
        if (destination === 'detail') task.animate(record.chapter.querySelector('.sg-sheet'), [
            { transform: 'translate3d(0, 84px, 0)' }, { transform: 'translate3d(0, 0, 0)' },
        ], { duration: 840, easing: 'cubic-bezier(.16,1,.3,1)' });
        const settle = destination === 'map' ? this.studio.waitForFocus(record.key, { signal: task.controller.signal, minimum: 420, maximum: 2400 }) : task.wait(680);
        await Promise.all([reveal, settle]);
        if (!task.active || !this.available(record)) return;
        this.cancelTransition();
        this.finishRoom(record, destination, history);
        } catch (error) {
            // Only this owner may recover its page. A newer navigation owns its own cleanup.
            if (this.transition === task) {
                this.cancelTransition();
                if (this.available(record)) {
                    if (finalView === 'map') this.prepareRoomMap(record);
                    this.finishRoom(record, finalView, 'replace');
                }
            }
            console.warn('Presentation transition returned to its stable page.', error);
        }
    }
}
