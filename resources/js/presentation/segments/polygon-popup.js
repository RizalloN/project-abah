import { h } from './format.js';

const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Move the live report, including its existing forms and chart handlers, into the top layer. */
export class PolygonPopup {
    constructor({ record, rescan }) {
        Object.assign(this, { record, rescan });
        this.sheet = record.chapter.querySelector('.sg-sheet');
        this.anchor = document.createComment(`polygon-details-${record.key}`);
        this.sheet.before(this.anchor);
        this.prepared = false;
        this.activePanel = 0;
        this.dialog = document.createElement('dialog');
        this.dialog.className = 'sg-popup';
        this.dialog.dataset.polygonDialog = record.key;
        this.dialog.setAttribute('aria-labelledby', `sg-popup-title-${record.key}`);
        this.dialog.setAttribute('aria-describedby', `sg-popup-caption-${record.key}`);
        this.dialog.innerHTML = `
            <header class="sg-popup__header">
                <div><p class="sg-popup__eyebrow"></p><h2 class="sg-popup__title" id="sg-popup-title-${h(record.key)}"></h2><p class="sg-popup__caption" id="sg-popup-caption-${h(record.key)}"></p></div>
                <button type="button" class="sg-popup__close" data-popup-close aria-label="Tutup rincian" autofocus><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></button>
            </header>
            <nav class="sg-popup__tabs" role="tablist" aria-label="Bagian rincian"></nav>
            <div class="sg-popup__body"></div>
            <footer class="sg-popup__footer"><span></span><button type="button" data-popup-map>Kembali ke peta</button></footer>`;
        record.chapter.append(this.dialog);
        this.body = this.dialog.querySelector('.sg-popup__body');
        this.tabs = this.dialog.querySelector('.sg-popup__tabs');
        this.dialog.querySelector('[data-popup-close]').addEventListener('click', () => this.close());
        this.dialog.querySelector('[data-popup-map]').addEventListener('click', () => this.close());
        this.dialog.addEventListener('cancel', (event) => { event.preventDefault(); this.close(); });
        this.dialog.addEventListener('close', () => {
            // A queued close from the previous opening must not empty a reopened dialog.
            if (!this.dialog.open) this.restore();
        });
        this.dialog.addEventListener('change', () => this.syncCaption());
        this.dialog.addEventListener('click', (event) => {
            this.syncCaption();
            if (event.target !== this.dialog) return;
            const box = this.dialog.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) this.close();
        });
        this.tabs.addEventListener('click', (event) => {
            const tab = event.target.closest('[data-popup-panel]');
            if (tab) this.selectPanel(Number(tab.dataset.popupPanel));
        });
        this.tabs.addEventListener('keydown', (event) => {
            const tab = event.target.closest('[data-popup-panel]');
            if (!tab || !['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const length = this.panels.length;
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? length - 1 : (this.activePanel + (event.key === 'ArrowRight' ? 1 : -1) + length) % length;
            this.selectPanel(next);
            this.tabs.children[next].focus();
        });
    }

    invalidate() { this.prepared = false; }

    prepare() {
        if (this.prepared) return;
        const nodes = [...this.sheet.children];
        const groups = [];
        const notes = [];
        const add = (node, title) => groups.push({ nodes: [node], title });
        for (const node of nodes) {
            if (node.matches('.sg-note')) { notes.push(node); continue; }
            if (node.matches('.sg-specs')) { add(node, 'Ringkasan'); continue; }
            if (node.matches('[data-sg-cras]')) { add(node, 'Subsektor CRAS'); continue; }
            if (node.matches('[data-sg-sector-table]')) { add(node, 'Rincian wilayah'); continue; }
            if (node.matches('.sg-grid') && node.children.length && [...node.children].every((child) => child.matches('.sg-tile'))) {
                for (const tile of [...node.children]) add(tile, this.record.key === 'mapping' ? 'Unit kerja' : tile.querySelector('h3')?.textContent || 'Potensi per sektor');
                node.remove();
                continue;
            }
            add(node, node.querySelector('.sg-block__title, h3')?.textContent || 'Rincian');
        }
        if (notes.length) groups.push({ title: 'Sumber data', nodes: notes });
        this.panels = groups.map((group, index) => {
            const panel = document.createElement('section');
            panel.className = 'sg-popup__panel';
            panel.id = `sg-popup-panel-${this.record.key}-${index}`;
            panel.setAttribute('role', 'tabpanel');
            panel.tabIndex = 0;
            panel.setAttribute('aria-labelledby', `sg-popup-tab-${this.record.key}-${index}`);
            group.nodes.forEach((node) => panel.append(node));
            this.sheet.append(panel);
            return panel;
        });
        this.tabs.innerHTML = groups.map((group, index) => `<button type="button" role="tab" id="sg-popup-tab-${h(this.record.key)}-${index}" aria-controls="sg-popup-panel-${h(this.record.key)}-${index}" data-popup-panel="${index}">${h(group.title)}</button>`).join('');
        this.activePanel = Math.min(this.activePanel, Math.max(0, this.panels.length - 1));
        this.prepared = true;
    }

    selectPanel(index) {
        this.activePanel = index;
        this.panels.forEach((panel, item) => { panel.hidden = item !== index; });
        [...this.tabs.children].forEach((tab, item) => {
            tab.setAttribute('aria-selected', String(item === index));
            tab.tabIndex = item === index ? 0 : -1;
        });
        this.body.scrollTop = 0;
        this.syncCaption();
        if (this.dialog.open) this.rescan(this.panels[index]);
    }

    syncCaption() {
        const cras = this.panels?.[this.activePanel]?.querySelector('[data-sg-cras]');
        this.dialog.querySelector('.sg-popup__caption').textContent = cras
            ? 'Portofolio SSA CRAS pada wilayah terpilih. Periode mengikuti sumber di bawah.'
            : this.record.chapter.querySelector('.sg-figure__label')?.textContent || '';
    }

    open() {
        if (!this.record.selection || !this.record.unlocked || !this.dialog.isConnected) return;
        window.clearTimeout(this.closeTimer);
        this.restoreFocus = true;
        this.returnFocus = document.activeElement;
        this.pageScrollY = window.scrollY;
        this.prepare();
        this.dialog.querySelector('.sg-popup__eyebrow').textContent = this.record.chapter.querySelector('.sg-kicker')?.textContent || 'Rincian wilayah';
        this.dialog.querySelector('.sg-popup__title').textContent = this.record.selection.label;
        this.dialog.querySelector('.sg-popup__caption').textContent = this.record.chapter.querySelector('.sg-figure__label')?.textContent || '';
        this.dialog.querySelector('.sg-popup__footer span').textContent = 'Dipilih langsung dari polygon';
        this.body.append(this.sheet);
        this.sheet.hidden = false;
        this.sheet.inert = false;
        this.sheet.setAttribute('aria-hidden', 'false');
        this.dialog.classList.remove('is-closing');
        this.dialog.classList.add('is-opening');
        document.documentElement.classList.add('sg-polygon-popup-open');
        if (!this.dialog.open) this.dialog.showModal();
        this.selectPanel(this.activePanel);
        this.rescan(this.dialog);
        this.dialog.querySelector('[data-popup-close]').focus({ preventScroll: true });
        window.scrollTo({ top: this.pageScrollY, behavior: 'instant' });
        this.record.chapter.dataset.polygonPopup = 'open';
    }

    close({ immediate = false, restoreFocus = true } = {}) {
        window.clearTimeout(this.closeTimer);
        this.restoreFocus = restoreFocus;
        if (!this.dialog.open) { this.restore(); return; }
        if (immediate || reduced()) { this.dialog.close(); this.restore(); return; }
        this.dialog.classList.remove('is-opening');
        this.dialog.classList.add('is-closing');
        this.closeTimer = window.setTimeout(() => { this.dialog.close(); this.restore(); }, 220);
    }

    restore() {
        this.sheet.hidden = true;
        this.sheet.inert = true;
        this.sheet.setAttribute('aria-hidden', 'true');
        if (this.anchor.isConnected) this.anchor.after(this.sheet);
        this.dialog.classList.remove('is-opening', 'is-closing');
        this.record.chapter.dataset.polygonPopup = 'closed';
        if (!document.querySelector('.sg-popup[open]')) document.documentElement.classList.remove('sg-polygon-popup-open');
        if (this.restoreFocus) {
            const origin = this.returnFocus;
            const target = origin?.isConnected && !origin.inert && origin !== document.body ? origin : this.record.chapter.querySelector('[data-polygon-continue]');
            target?.focus({ preventScroll: true });
            this.restoreFocus = false;
        }
    }

    destroy() {
        this.close({ immediate: true, restoreFocus: false });
        this.dialog.remove();
        this.anchor.remove();
    }
}
