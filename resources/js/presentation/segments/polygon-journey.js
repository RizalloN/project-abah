import { h } from './format.js';
import { BRANCH_LABELS } from './territory-data.js';
import { POLYGON_CHAPTERS, resolvePolygonSelection } from './polygon-selection.js';

const still = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const go = (node) => node?.scrollIntoView({ behavior: still() ? 'auto' : 'smooth', block: 'start' });

/** One explicit KC choice opens a continuous, branch-scoped reading journey. */
export class PolygonJourney {
    constructor({ deck, scope, index, studio, renderSelection, rescan }) {
        Object.assign(this, { deck, scope, index, studio, renderSelection, rescan });
        this.records = new Map();
        this.events = new AbortController();
        this.disposed = false;
    }

    init() {
        this.deck.classList.add('sg-branch-story');
        for (const key of POLYGON_CHAPTERS) {
            const chapter = this.deck.querySelector(`#sg-${key}`);
            const slot = chapter?.querySelector('[data-territory]');
            if (!slot || !this.index.ready || !this.studio.states.has(key)) continue;
            const record = { key, chapter, slot, selection: null, unlocked: false, token: 0 };
            this.records.set(key, record);
            const overlay = document.createElement('aside');
            overlay.className = 'sg-branch-overlay';
            overlay.hidden = true;
            overlay.setAttribute('aria-live', 'polite');
            slot.append(overlay);
            record.overlay = overlay;
            chapter.addEventListener('polygonshowmap', () => go(chapter), { signal: this.events.signal });
            chapter.classList.add('sg-polygon-chapter', 'is-awaiting-choice');
            this.setSheet(record, false);
            slot.removeAttribute('aria-hidden');
            slot.dataset.polygonInteractive = 'true';
            slot.setAttribute('role', 'group');
            slot.setAttribute('aria-label', `Peta ${key}, pilih wilayah untuk membuka rincian`);
            const mapHint = document.createElement('p');
            mapHint.className = 'sg-polygon-map-hint';
            mapHint.textContent = 'Klik wilayah untuk memilih kantor cabang';
            slot.append(mapHint);
            this.studio.interactions.set(key, (spec) => this.decorate(record, spec));
            this.decorate(record, this.studio.states.get(key));
            this.bindSurface(record);
            this.paintHud(record);
        }
        this.rail = document.createElement('nav');
        this.rail.className = 'sg-story-rail';
        this.rail.setAttribute('aria-label', 'Materi wilayah');
        this.rail.hidden = true;
        this.rail.innerHTML = `<div class="sg-story-rail__identity"><small>Kantor cabang</small><strong data-story-branch></strong></div><div class="sg-story-rail__points">${[...this.records.keys()].map((key, index) => `<a href="#sg-${key}" data-story-point="${key}"><span aria-hidden="true">${String(index + 1).padStart(2, '0')}</span>${({ marketshare: 'Marketshare', mapping: 'Mapping wilayah', sektoral: 'Penetrasi sektoral' })[key]}</a>`).join('')}</div><button type="button" data-story-reset>Ubah KC</button>`;
        this.deck.prepend(this.rail);
        this.rail.querySelector('[data-story-reset]').addEventListener('click', () => this.resetBranch());
        const signal = this.events.signal;
        window.addEventListener('scroll', () => this.enforce(), { passive: true, signal });
        window.addEventListener('resize', () => this.enforce(), { passive: true, signal });
        document.addEventListener('click', (event) => {
            if (this.deck.hidden) return;
            const link = event.target.closest('a[href^="#sg-"]');
            const target = link && document.getElementById(link.hash.slice(1));
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
        if (!record.selection) {
            spec.journey = (spec.journey || []).map((stop) => ({ ...stop, labels: this.branchLabels(record, stop.labels || []) }));
        }
    }

    branchLabels(record, labels) {
        if (record.selection) return labels;
        return (this.scope.key === 'area6' ? Object.keys(BRANCH_LABELS) : [this.scope.key]).map((branch) => {
            const codes = this.index.codesOfBranch(branch);
            const source = labels.find((item) => item.codes && [...item.codes].some((code) => codes.has(code)));
            return { ...source, id: `pick-${record.key}-${branch}`, codes, title: BRANCH_LABELS[branch], value: source?.value || 'Pilih wilayah',
                action: () => this.choose(record, { code: [...codes][0] }), ariaLabel: `Pilih ${BRANCH_LABELS[branch]} pada peta ${record.key}` };
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
        if (this.disposed || record.chapter.inert) return;
        const selection = resolvePolygonSelection({ key: record.key, current: record.selection, scope: this.scope, index: this.index, ...input });
        if (!selection?.codes.size) { this.prompt(record, 'Wilayah ini belum memiliki pemetaan unit. Pilih polygon lainnya.'); return; }
        const token = this.transitionToken = (this.transitionToken || 0) + 1;
        const changingBranch = this.branchKey !== selection.branchKey;
        this.branchKey = selection.branchKey;
        // A rapid district click must also finish other chapters still arriving at this KC.
        const affected = [...this.records.values()].filter((item) => changingBranch || item === record || !item.unlocked || item.chapter.classList.contains('is-focusing-choice'));
        let viewportTop = record.chapter.getBoundingClientRect().top;
        const scrollY = window.scrollY;
        for (const item of affected) {
            item.selectionScrollY = scrollY;
            item.selection = item === record ? selection : { level: 'branch', branchKey: selection.branchKey, unitCode: null, unitCodes: [], districtCode: null,
                label: BRANCH_LABELS[selection.branchKey], codes: this.index.codesOfBranch(selection.branchKey) };
            item.chapter.classList.remove('is-awaiting-choice', 'is-choice-ready');
            item.chapter.classList.add('is-focusing-choice');
            item.chapter.dataset.branchSelected = selection.branchKey;
            this.setSheet(item, item.unlocked);
            this.renderSelection(item.key, item.selection);
            this.paintHud(item);
            this.paintOverlay(item);
            this.studio.setOverride(item.key, item.selection.codes);
            this.paintTargets(item);
            item.slot.querySelectorAll('[data-code]').forEach((path) => path.setAttribute('aria-pressed', String(item.selection.codes.has(path.dataset.code))));
        }
        const restoreAnchor = () => window.scrollTo({ top: this.topOf(record.chapter) - viewportTop, behavior: 'instant' });
        restoreAnchor();
        affected.forEach((item) => { item.selectionScrollY = window.scrollY; });
        this.studio.activeSlot = record.slot;
        this.studio.appliedKey = null;
        this.studio.kick();
        record.slot.dispatchEvent(new CustomEvent('territoryselect', { bubbles: true, detail: { key: record.key, selection, explicit: true } }));
        this.rail.querySelector('[data-story-branch]').textContent = BRANCH_LABELS[this.branchKey];
        if (!still()) await new Promise((resolve) => { this.transitionTimer = window.setTimeout(resolve, 850); });
        if (this.disposed || token !== this.transitionToken) return;
        viewportTop = record.chapter.getBoundingClientRect().top;
        for (const item of affected) {
            item.unlocked = true;
            item.chapter.classList.remove('is-focusing-choice');
            item.chapter.classList.add('is-choice-ready');
            this.setSheet(item, true);
            this.paintHud(item);
            this.rescan(item.chapter);
        }
        this.rail.hidden = false;
        restoreAnchor();
        this.enforce();
    }

    paintOverlay(record) {
        const selection = record.selection;
        record.overlay.hidden = !selection;
        if (!selection) return;
        record.overlay.innerHTML = `<p class="sg-branch-overlay__kicker">Kantor cabang terpilih</p><h3 class="sg-branch-overlay__name">${h(BRANCH_LABELS[selection.branchKey])}</h3><p class="sg-branch-overlay__detail">${selection.level === 'branch' ? 'Seluruh wilayah cabang' : h(selection.label)}</p>`;
    }

    paintTargets(record) {
        const selection = record.selection;
        const mapHint = record.slot.querySelector('.sg-polygon-map-hint');
        const detail = selection?.level === 'district' ? 'Klik pin untuk memilih satu unit'
            : selection?.level === 'branch' ? record.key === 'marketshare' ? 'Pangsa BRI terhadap industri' : 'Klik kecamatan untuk menelusuri unit' : selection?.label;
        if (mapHint) mapHint.textContent = selection ? `${BRANCH_LABELS[selection.branchKey]} · ${detail}` : 'Klik wilayah untuk memilih kantor cabang';
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
            <p class="sg-polygon-hud__breadcrumb"><span>${h(BRANCH_LABELS[selection.branchKey])}</span>${selection.level !== 'branch' ? `<span>${h(selection.label)}</span>` : ''}</p>
            <p class="sg-polygon-hud__state" role="status">${record.unlocked ? 'Jelajahi materi cabang melalui tiga titik di atas atau lanjutkan ke rincian.' : `Mendekat ke ${h(BRANCH_LABELS[selection.branchKey])}…`}</p>
            ${selection.level === 'district' ? `<p class="sg-polygon-hud__hint">${selection.unitCodes.length} unit melayani kecamatan ini. Klik pin unit di peta untuk melihat satu unit.</p>` : record.key !== 'marketshare' && selection.level === 'branch' ? '<p class="sg-polygon-hud__hint">Klik kecamatan untuk menelusuri wilayah layanan unit.</p>' : ''}
            <div class="sg-polygon-hud__actions"><button type="button" data-polygon-reset>${selection.level === 'branch' ? 'Ubah KC' : 'Kembali ke cabang'}</button><button type="button" data-polygon-continue ${record.unlocked ? '' : 'disabled'}>Jelajahi rincian <span aria-hidden="true">↓</span></button></div>` : `
            <div class="sg-polygon-hud__invitation"><span class="sg-polygon-cue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3.5 18.5 12l-5.6 1.4 3.3 6.1-2.4 1.2-3.2-6.1L6 18.4z" fill="currentColor"/></svg></span><p>Klik wilayah di peta.<small>${record.key === 'marketshare' ? 'Pilih cabang untuk membuka perbandingan BRI dan industri.' : 'Pilih polygon untuk membuka potensi dan rincian wilayahnya.'}</small></p></div>
            <p class="sg-polygon-hud__state" role="status">Rincian terbuka setelah Anda memilih wilayah.</p>`;
        copy.append(hud);
        hud.querySelector('[data-polygon-reset]')?.addEventListener('click', () => this.reset(record));
        hud.querySelector('[data-polygon-continue]')?.addEventListener('click', () => {
            const sheet = record.chapter.querySelector('.sg-sheet');
            go(sheet);
            sheet.focus({ preventScroll: true });
        });
    }

    reset(record) {
        if (!record.selection) return;
        if (record.selection.level === 'branch') { this.resetBranch(); return; }
        // Return to the named KC before another unit is explored.
        record.selection = null;
        this.choose(record, { code: [...this.index.codesOfBranch(this.branchKey)][0] });
        go(record.chapter);
    }

    resetBranch() {
        this.transitionToken = (this.transitionToken || 0) + 1;
        this.branchKey = null;
        this.rail.hidden = true;
        const first = [...this.records.values()][0];
        if (first) window.scrollTo({ top: this.topOf(first.chapter), behavior: 'instant' });
        for (const record of this.records.values()) this.resetRecord(record);
        this.enforce();
        first?.slot.querySelector('[data-code][tabindex="0"]')?.focus({ preventScroll: true });
    }

    resetRecord(record) {
        ++record.token;
        window.clearTimeout(record.timer);
        record.selection = null; record.unlocked = false;
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
        this.paintOverlay(record);
        this.rescan(record.chapter);
    }

    setSheet(record, open) {
        const sheet = record.chapter.querySelector('.sg-sheet');
        sheet.hidden = !open;
        sheet.inert = !open;
        sheet.setAttribute('aria-hidden', String(!open));
        sheet.tabIndex = -1;
        record.chapter.dataset.polygonState = open ? 'open' : record.selection ? 'focusing' : 'locked';
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
        const current = records.filter((item) => item.chapter.getBoundingClientRect().top <= readingLine).at(-1) || records[0];
        this.rail?.querySelectorAll('[data-story-point]').forEach((point) => point.setAttribute('aria-current', String(point.dataset.storyPoint === current?.key)));
    }

    destroy() {
        this.disposed = true;
        this.events.abort();
        window.clearTimeout(this.transitionTimer);
        this.rail?.remove();
        this.records.forEach((record, key) => { window.clearTimeout(record.timer); this.studio.interactions.delete(key); this.studio.setInteractiveTargets(key, []); });
    }
}
