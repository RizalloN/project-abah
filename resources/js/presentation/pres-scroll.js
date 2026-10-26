import './vendor/scrollcraft.js';
import { WorldDirector } from './worlds/director.js';
import { KeynoteStudio } from './worlds/keynote-studio.js';

// The headline figures the ledger spine carries, and the chapter that locks each one in.
const LEDGER_KEYS = ['simpanan', 'os', 'ldr', 'sml', 'npl'];
const LEDGER_CHAPTER = { simpanan: 'dana', os: 'pinjaman', ldr: 'pinjaman', sml: 'kualitas', npl: 'kualitas' };
const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const escapeText = (value) => String(value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
// Surfaces that catch light under the pointer.
const LIT_SURFACES = '.rp-paper, .rp-fig, .rp-lever, .rp-kpi, .rp-insights > li, .rp-agenda-board > li, .rp-signals li, .rp-timeline__item';
// Report surfaces that rise out of depth as they scroll in (nested papers ride their parent).
const DEPTH_SURFACES = '.pres-report-section .rp-paper, .pres-report-section .rp-figures, .pres-report-section .rp-lever, .pres-report-section .rp-kpi';
// Pointer tilt in degrees: small tiles lean further than wide tables, which stay readable.
const tiltFor = (width) => Math.max(1.2, Math.min(7, 2600 / Math.max(1, width)));

/** Document position from the offset chain, which ignores transforms (the surfaces measured here are the ones being transformed). */
const layoutBox = (element) => {
    let top = 0;
    let left = 0;
    for (let node = element; node; node = node.offsetParent) {
        top += node.offsetTop;
        left += node.offsetLeft;
    }
    return { top, left, width: element.offsetWidth, height: element.offsetHeight };
};

/** Split "Rp14,41 T" into prefix, signed number and suffix, keeping id-ID decimals. */
const parseFigure = (text) => {
    const match = String(text).match(/^(\D{0,3}?)([-+]?)(\d{1,3}(?:\.\d{3})*|\d+)(?:,(\d+))?(\D{0,4})$/);
    if (!match) return null;
    const [, prefix, sign, whole, fraction = '', suffix] = match;
    const value = Number(`${whole.replaceAll('.', '')}.${fraction || '0'}`);
    return Number.isFinite(value) ? { prefix: prefix + sign, value, decimals: fraction.length, suffix } : null;
};

/**
 * Vertical, scroll-driven reading of the report. The renderer fills every
 * section; this class owns the reading position, navigation, the ledger
 * spine and the motion layer (reveals, count-ups and scroll progress).
 */
export class PresentationScrollDeck {
    constructor({ root, onActivate, worldSource = null, reliefSource = null, figureSource = null }) {
        this.root = root;
        this.onActivate = onActivate;
        // The keynote layout carries one persistent relief instead of a sculpture per chapter.
        this.keynote = root.hasAttribute('data-keynote');
        this.figureSource = figureSource;
        this.worlds = this.keynote
            ? new KeynoteStudio({ root, config: window.__PRESENTATION_CONFIG__ || {}, source: reliefSource })
            : new WorldDirector({ root, source: worldSource });
        this.worldDataTimer = null;
        this.depthElements = [];
        this.sections = Array.from(root.querySelectorAll('.pres-report-section'))
            .sort((a, b) => Number(a.dataset.panelIndex) - Number(b.dataset.panelIndex));
        this.chapterOrder = Array.from(root.querySelectorAll('[data-spine-chapter]')).map((link) => link.dataset.spineChapter);
        this.spineCurrent = root.querySelector('#pres-spine-current');
        this.autoplayButton = root.querySelector('[data-spine-autoplay]');
        this.currentIndex = -1;
        this.currentChapter = null;
        this.reachedChapter = -1;
        this.frame = null;
        this.engine = null;
        this.autoplayTimer = null;
        this.autoplayDelay = 9000;
        this.progressElements = [];
        this.trendScenes = [];
        this.revealObserver = null;
    }

    init() {
        document.documentElement.classList.add('pres-scroll-ready');
        if (this.keynote) this.initKeynote();
        const tools = this.root.querySelector('.pres-quick-tools');
        if (tools && !tools.querySelector('[data-presentation-motion]')) {
            const pause = document.createElement('button');
            pause.type = 'button';
            pause.dataset.presentationMotion = '1';
            pause.className = 'pres-motion-toggle';
            pause.setAttribute('aria-label', 'Jeda animasi otomatis');
            pause.setAttribute('title', 'Jeda animasi otomatis');
            pause.setAttribute('aria-pressed', 'false');
            pause.innerHTML = '<i class="fas fa-pause" aria-hidden="true"></i>';
            tools.append(pause);
        }
        this.root.addEventListener('click', (event) => {
            const pause = event.target.closest('[data-presentation-motion]');
            if (pause) {
                this.worlds.setPaused(!this.worlds.motionPaused);
                pause.setAttribute('aria-pressed', String(this.worlds.motionPaused));
                pause.setAttribute('aria-label', this.worlds.motionPaused ? 'Lanjutkan animasi otomatis' : 'Jeda animasi otomatis');
                pause.setAttribute('title', pause.getAttribute('aria-label'));
                pause.innerHTML = `<i class="fas fa-${this.worlds.motionPaused ? 'play' : 'pause'}" aria-hidden="true"></i>`;
                return;
            }
            const step = event.target.closest('[data-area6-step]');
            if (step) {
                const story = step.closest('[data-area6-story]');
                const phase = Number(step.dataset.area6Step);
                story.dataset.phase = String(phase);
                const progress = [0.06, 0.60, 0.90][phase];
                if (!reduceMotion() && !document.documentElement.classList.contains('pres-worlds-off')) {
                    window.scrollTo({ top: window.scrollY + story.getBoundingClientRect().top + progress * Math.max(0, story.offsetHeight - window.innerHeight), behavior: 'smooth' });
                }
                return;
            }
            const marketStep = event.target.closest('[data-market-step]');
            if (marketStep) {
                const story = marketStep.closest('[data-market-story]');
                const count = story.querySelectorAll('[data-market-beat]').length || 1;
                const index = Number(marketStep.dataset.marketStep);
                const travel = Math.max(0, story.offsetHeight - window.innerHeight);
                this.stopAutoplay();
                window.scrollTo({ top: window.scrollY + story.getBoundingClientRect().top + ((index + 0.5) / count) * travel, behavior: reduceMotion() ? 'auto' : 'smooth' });
                return;
            }
            const chapterLink = event.target.closest('[data-kn-chapter-link]');
            if (chapterLink && this.keynote) {
                event.preventDefault();
                this.stopAutoplay();
                this.scrollToChapter(chapterLink.dataset.knChapterLink);
                return;
            }
            const target = event.target.closest('[data-spine-target]');
            if (!target || !this.root.contains(target)) return;
            event.preventDefault();
            this.stopAutoplay();
            this.scrollToIndex(Number(target.dataset.spineTarget));
        });
        this.autoplayButton?.addEventListener('click', () => this.toggleAutoplay());
        ['wheel', 'touchstart'].forEach((type) => window.addEventListener(type, () => this.stopAutoplay(), { passive: true }));
        window.addEventListener('scroll', () => this.schedule(), { passive: true });
        window.addEventListener('resize', () => this.refresh(), { passive: true });
        // Surfaces are measured once per layout; opening a disclosure or a late chart moves everything below it.
        if ('ResizeObserver' in window) {
            let layoutTimer = null;
            new ResizeObserver(() => {
                window.clearTimeout(layoutTimer);
                layoutTimer = window.setTimeout(() => this.refresh(), 120);
            }).observe(this.root.querySelector('.pres-slides-container') || this.root);
        }
        if ('IntersectionObserver' in window) {
            this.revealObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    this.reveal(entry.target);
                    this.revealObserver.unobserve(entry.target);
                });
            }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
        }
        if (window.ScrollCraft?.mount) {
            this.engine = window.ScrollCraft.mount(this.root);
        }
        this.worlds.init();
        if (finePointer()) {
            this.root.addEventListener('pointermove', (event) => this.light(event), { passive: true });
            this.root.addEventListener('pointerout', (event) => {
                const surface = event.target.closest?.(LIT_SURFACES);
                if (!surface || surface.contains(event.relatedTarget)) return;
                surface.classList.remove('is-lit');
                surface.style.removeProperty('--tx');
                surface.style.removeProperty('--ty');
            });
        }
        this.scan(this.root);
        this.schedule();
    }

    /* ------------------------------------------------------------ keynote -- */

    initKeynote() {
        const html = document.documentElement;
        if (!reduceMotion()) html.classList.add('kn-motion');
        this.nav = this.root.querySelector('[data-kn-nav]');
        this.progressBar = this.root.querySelector('[data-kn-progress]');
        this.statements = Array.from(this.root.querySelectorAll('[data-kn-statement]'));

        // One menu holds every tool; it closes on Escape, outside click, or after a choice.
        const menu = this.root.querySelector('[data-kn-menu]');
        const toggle = menu?.querySelector('[data-kn-menu-toggle]');
        const panel = menu?.querySelector('[data-kn-menu-panel]');
        const setMenu = (open, { focus = false } = {}) => {
            if (!toggle || !panel) return;
            toggle.setAttribute('aria-expanded', String(open));
            panel.hidden = !open;
            if (open) panel.querySelector('button')?.focus({ preventScroll: true });
            else if (focus) toggle.focus({ preventScroll: true });
        };
        toggle?.addEventListener('click', () => setMenu(toggle.getAttribute('aria-expanded') !== 'true'));
        panel?.addEventListener('click', (event) => {
            if (event.target.closest('button')) window.setTimeout(() => setMenu(false), 0);
        });
        document.addEventListener('click', (event) => {
            if (menu && !menu.contains(event.target) && toggle?.getAttribute('aria-expanded') === 'true') setMenu(false);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') setMenu(false, { focus: true });
        });

        // The hero stage comes up from black once the loader has gone.
        const loader = document.getElementById('dashboard-global-loader');
        const ready = () => html.classList.add('kn-ready');
        if (!loader || !loader.classList.contains('active')) ready();
        else {
            const watch = new MutationObserver(() => {
                if (!loader.classList.contains('active')) {
                    ready();
                    watch.disconnect();
                }
            });
            watch.observe(loader, { attributes: true, attributeFilter: ['class', 'style'] });
            window.setTimeout(ready, 9000);
        }

        const split = () => this.root.querySelectorAll('[data-kn-lines]').forEach((title) => this.splitLines(title));
        if (document.fonts?.ready) document.fonts.ready.then(split);
        else split();
        let resizeTimer = null;
        window.addEventListener('resize', () => {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(split, 180);
        }, { passive: true });
    }

    /** Wrap a heading's words into measured lines that can rise out of their own masks. */
    splitLines(title) {
        const text = title.dataset.knText || title.textContent.trim().replace(/\s+/g, ' ');
        title.dataset.knText = text;
        title.setAttribute('aria-label', text);
        title.innerHTML = text.split(' ').map((word) => `<span class="kn-word">${escapeText(word)}</span>`).join(' ');
        const lines = [];
        let lastTop = null;
        title.querySelectorAll('.kn-word').forEach((word) => {
            const top = word.offsetTop;
            if (lastTop === null || Math.abs(top - lastTop) > 4) {
                lines.push([]);
                lastTop = top;
            }
            lines[lines.length - 1].push(word.textContent);
        });
        title.innerHTML = lines.map((words, index) => `<span class="kn-line" aria-hidden="true"><span style="--line:${index}">${escapeText(words.join(' '))}</span></span>`).join(' ');
    }

    scrollToChapter(key) {
        const statement = this.root.querySelector(`#pres-chapter-${CSS.escape(key)}`);
        if (!statement) return;
        // Land a little into the statement so its copy has already arrived.
        const travel = Math.max(0, statement.offsetHeight - window.innerHeight);
        window.scrollTo({
            top: Math.max(0, window.scrollY + statement.getBoundingClientRect().top + travel * 0.32),
            behavior: reduceMotion() ? 'auto' : 'smooth',
        });
    }

    readKeynote(viewportHeight) {
        const doc = document.documentElement;
        const total = Math.max(1, doc.scrollHeight - viewportHeight);
        const read = Math.min(1, Math.max(0, window.scrollY / total));
        if (this.nav && Math.abs((this.navRead ?? -1) - read) > 0.0015) {
            this.navRead = read;
            this.nav.style.setProperty('--kn-read', read.toFixed(4));
        }

        // Navigation turns dark over the theatre grounds.
        if (this.nav) {
            const probeY = this.nav.getBoundingClientRect().bottom + 2;
            const under = document.elementFromPoint(Math.max(8, window.innerWidth * 0.5), probeY);
            const dark = Boolean(under?.closest?.('.kn-ground--dark, .kn-hero'));
            if (dark !== this.navDark) {
                this.navDark = dark;
                this.nav.classList.toggle('is-on-dark', dark);
            }
        }

        const hero = this.root.querySelector('.kn-hero');
        if (hero) {
            const rect = hero.getBoundingClientRect();
            const progress = reduceMotion() ? 0 : Math.min(1, Math.max(0, -rect.top / Math.max(1, rect.height - viewportHeight)));
            if (Math.abs((hero._hp ?? -1) - progress) > 0.001) {
                hero._hp = progress;
                hero.style.setProperty('--hp', progress.toFixed(4));
            }
        }

        this.statements.forEach((statement) => {
            const rect = statement.getBoundingClientRect();
            if (rect.bottom < -viewportHeight || rect.top > viewportHeight * 2) return;
            // The sheet that follows rises like a page tipping up, and the statement under it recedes.
            const sheet = statement.nextElementSibling;
            if (sheet && !reduceMotion()) {
                const top = sheet.getBoundingClientRect().top;
                const cover = Math.min(1, Math.max(0, (viewportHeight - top) / viewportHeight));
                const rise = Math.min(1, Math.max(0, (viewportHeight - top) / (viewportHeight * 0.85)));
                if (Math.abs((statement._cover ?? -1) - cover) > 0.002) {
                    statement._cover = cover;
                    statement.style.setProperty('--cover', cover.toFixed(4));
                }
                if (Math.abs((sheet._rise ?? -1) - rise) > 0.002) {
                    sheet._rise = rise;
                    sheet.style.setProperty('--rise', rise.toFixed(4));
                    sheet.classList.toggle('is-rising', rise > 0 && rise < 1);
                }
            }
            const progress = reduceMotion() ? 0.5 : Math.min(1, Math.max(0, -rect.top / Math.max(1, rect.height - viewportHeight)));
            if (Math.abs((statement._sp ?? -1) - progress) > 0.001) {
                statement._sp = progress;
                statement.style.setProperty('--sp', progress.toFixed(4));
            }
            if (progress > 0.16 && !statement.dataset.counted) {
                statement.dataset.counted = '1';
                statement.querySelectorAll('.kn-figure__value').forEach((figure) => this.countUp(figure));
            }
            const beats = statement.querySelectorAll('[data-kn-beat]');
            if (beats.length) {
                const current = progress < 0.5 ? 'sml' : 'npl';
                if (statement.dataset.beat !== current) {
                    statement.dataset.beat = current;
                    beats.forEach((beat) => {
                        const active = beat.dataset.knBeat === current;
                        beat.classList.toggle('is-current', active);
                        beat.setAttribute('aria-hidden', String(!active && !reduceMotion()));
                        if (active) beat.querySelectorAll('.kn-figure__value').forEach((figure) => this.countUp(figure));
                    });
                }
            }
        });
    }

    /** Statement figures read through the renderer's own accessors (same numbers as the tables). */
    fillFigures() {
        const figures = this.figureSource?.();
        if (!figures) return;
        Object.entries(figures).forEach(([key, figure]) => {
            this.root.querySelectorAll(`[data-figure="${key}"]`).forEach((slot) => {
                const value = figure?.value || 'Belum tersedia';
                if (slot.dataset.finalValue !== value) {
                    slot.dataset.finalValue = value;
                    slot.dataset.counted = '';
                    slot.textContent = value;
                }
            });
            this.root.querySelectorAll(`[data-figure-trend="${key}"]`).forEach((slot) => {
                slot.textContent = figure?.trend || '';
                slot.classList.toggle('is-up', figure?.tone === 'up');
                slot.classList.toggle('is-down', figure?.tone === 'down');
            });
            this.root.querySelectorAll(`[data-figure-ratio="${key}"]`).forEach((slot) => {
                slot.textContent = figure?.ratio ? `rasio ${figure.ratio}` : 'rasio belum tersedia';
            });
            if (figure?.label) this.root.querySelectorAll(`[data-figure-label="${key}"]`).forEach((slot) => { slot.textContent = figure.label; });
            if (figure?.note) this.root.querySelectorAll(`[data-figure-note="${key}"]`).forEach((slot) => { slot.textContent = figure.note; });
        });
    }

    /** Called by the renderer after a section's markup is replaced. */
    sectionRendered(section) {
        // A scope refresh may replace nodes before they have entered the viewport.
        this.observedReveals?.forEach((element) => {
            if (element.isConnected) return;
            this.revealObserver?.unobserve(element);
            this.observedReveals.delete(element);
        });
        this.scan(section);
        this.worlds.scan(section);
        this.queueWorldData();
        if (this.keynote) this.fillFigures();
        this.refresh();
    }

    /** Scope and segment changes rerender many sections at once; rebuild the worlds once. */
    queueWorldData() {
        window.clearTimeout(this.worldDataTimer);
        this.worldDataTimer = window.setTimeout(() => this.worlds.refreshData(), 160);
    }

    /** A soft specular follows the pointer across a surface, and small tiles lean toward it. */
    light(event) {
        const surface = event.target.closest?.(LIT_SURFACES);
        if (!surface) return;
        const rect = surface.getBoundingClientRect();
        const x = (event.clientX - rect.left) / Math.max(1, rect.width);
        const y = (event.clientY - rect.top) / Math.max(1, rect.height);
        surface.style.setProperty('--mx', x.toFixed(3));
        surface.style.setProperty('--my', y.toFixed(3));
        if (this.keynote && !reduceMotion()) {
            const tilt = tiltFor(rect.width);
            surface.style.setProperty('--tx', `${((0.5 - y) * tilt).toFixed(2)}deg`);
            surface.style.setProperty('--ty', `${((x - 0.5) * tilt).toFixed(2)}deg`);
        }
        surface.classList.add('is-lit');
    }

    scan(scope) {
        this.observedReveals ||= new Set();
        // Section titles assemble word by word, each word turning up out of the page.
        scope.querySelectorAll('.rp-title:not([data-words])').forEach((title) => {
            const text = title.textContent.trim().replace(/\s+/g, ' ');
            title.dataset.words = '1';
            title.setAttribute('aria-label', text);
            title.innerHTML = text.split(' ').map((word, index) => `<span class="rp-w" aria-hidden="true"><span style="--w:${index}">${escapeText(word)}</span></span>`).join(' ');
        });
        this.depthElements = Array.from(this.root.querySelectorAll(DEPTH_SURFACES));
        // Table rows turn up one after another once their card is in, like a departure board.
        scope.querySelectorAll('.rp-table-card tbody').forEach((body) => {
            Array.from(body.rows).forEach((row, index) => row.style.setProperty('--r', String(Math.min(index, 18))));
        });
        this.measureSides();
        scope.querySelectorAll('[data-rv]:not(.is-in)').forEach((element) => {
            if (this.revealObserver && !reduceMotion()) {
                this.revealObserver.observe(element);
                this.observedReveals.add(element);
            }
            else this.reveal(element);
        });
        this.progressElements = Array.from(this.root.querySelectorAll('[data-sp]'));
        this.territoryStories = Array.from(this.root.querySelectorAll('[data-area6-story]'));
        this.marketStories = Array.from(this.root.querySelectorAll('[data-market-story]'));
        this.marketJourneys = Array.from(this.root.querySelectorAll('[data-market-journey]')).map((element) => ({
            element, host: element.querySelector('[data-market-stage]'),
            articles: Array.from(element.querySelectorAll('.rp-market-article[data-market-scene]')),
        }));
        this.mappingJourneys = Array.from(this.root.querySelectorAll('[data-mapping-journey]')).map((element) => ({
            element, articles: Array.from(element.querySelectorAll('[data-mapping-cue]')),
        }));
        this.trendScenes = Array.from(this.root.querySelectorAll('[data-trend-scene]')).map((element) => {
            const probe = element.querySelector('[data-trend-probe]');
            let distance = 0;
            const paths = Array.from(element.querySelectorAll('[data-trend-path]')).map((path) => {
                const start = distance;
                const length = path.getTotalLength();
                distance += length;
                return { path, start, length };
            });
            return { element, probe, paths, length: distance, points: JSON.parse(probe?.dataset.points || '[]'), readings: Array.from(element.querySelectorAll('[data-trend-reading]')) };
        });
    }

    /** Which side of the page each surface sits on, so side-by-side tiles swing in from their own edge. */
    measureSides() {
        const middle = window.innerWidth / 2;
        this.depthElements.forEach((element) => {
            const box = layoutBox(element);
            element._box = box;
            if (!this.keynote) return;
            const offset = (box.left + box.width / 2 - middle) / Math.max(1, window.innerWidth);
            element.style.setProperty('--side', Math.abs(offset) < 0.08 || box.width > window.innerWidth * 0.62 ? '0' : String(Math.sign(offset)));
        });
    }

    reveal(element) {
        this.observedReveals?.delete(element);
        element.classList.add('is-in');
        element.querySelectorAll('[data-count]').forEach((counter) => this.countUp(counter));
        if (element.matches('[data-count]')) this.countUp(element);
    }

    /** Numbers bloom from zero and always land on the exact formatted value. */
    countUp(element) {
        if (element.dataset.counted) return;
        element.dataset.counted = '1';
        const finalText = element.textContent.trim();
        const figure = parseFigure(finalText);
        if (!figure || figure.value === 0 || reduceMotion()) return;
        const started = performance.now();
        const duration = 1100;
        const format = (value) => `${figure.prefix}${value.toLocaleString('id-ID', { minimumFractionDigits: figure.decimals, maximumFractionDigits: figure.decimals })}${figure.suffix}`;
        const step = (now) => {
            const t = Math.min((now - started) / duration, 1);
            const eased = 1 - ((1 - t) ** 3);
            element.textContent = t >= 1 ? finalText : format(figure.value * eased);
            if (t < 1) window.requestAnimationFrame(step);
        };
        element.textContent = format(0);
        window.requestAnimationFrame(step);
    }

    refresh() {
        this.engine?.layout();
        this.measureSides();
        this.schedule();
    }

    schedule() {
        if (this.frame !== null) return;
        this.frame = window.requestAnimationFrame(() => {
            this.frame = null;
            this.read();
        });
    }

    read() {
        const viewportHeight = window.innerHeight;
        if (this.keynote) this.readKeynote(viewportHeight);
        const topBar = this.topBarHeight();
        const readingLine = topBar + ((viewportHeight - topBar) * 0.38);
        this.readNarrative(this.marketJourneys, readingLine, topBar, viewportHeight, 'market');
        this.readNarrative(this.mappingJourneys, readingLine, topBar, viewportHeight, 'mapping');
        this.readMarketStories(viewportHeight);
        this.territoryStories?.forEach((story) => {
            const rect = story.getBoundingClientRect();
            if (rect.bottom < 0 || rect.top > viewportHeight) return;
            if (rect.top <= readingLine && rect.bottom > readingLine && this.mappingCueKey && this.mappingCueKey !== ':members') {
                this.root.dispatchEvent(new CustomEvent('mappingcue', { detail: { key: '', reading: 'members' } }));
                this.mappingCueKey = ':members';
            }
            // The live world publishes its damped clock so text and geography arrive together.
            if (story.querySelector('.pres-world.is-live')) return;
            const progress = reduceMotion() ? 1 : Math.min(1, Math.max(0, -rect.top / Math.max(1, rect.height - viewportHeight)));
            const phase = progress < 0.46 ? 0 : progress < 0.72 ? 1 : 2;
            story.dataset.phase = String(phase);
            story.style.setProperty('--wp', progress.toFixed(4));
            story.querySelectorAll('[data-area6-step]').forEach((button) => button.setAttribute('aria-pressed', String(Number(button.dataset.area6Step) === phase)));
        });

        // Scroll progress for drawn lines, the timeline and the cover's depth planes.
        this.progressElements.forEach((element) => {
            const rect = element.getBoundingClientRect();
            if (rect.bottom < -200 || rect.top > viewportHeight + 200) return;
            const progress = Math.min(Math.max((viewportHeight - rect.top) / (viewportHeight + rect.height), 0), 1);
            if (Math.abs((element._sp ?? -1) - progress) > 0.002) {
                element._sp = progress;
                element.style.setProperty('--sp', progress.toFixed(4));
            }
        });
        this.trendScenes.forEach((scene) => {
            const progress = reduceMotion() ? 1 : Math.min(1, Math.max(0, ((scene.element._sp || 0) - 0.14) / 0.36));
            if (scene.progress === progress) return;
            scene.progress = progress;
            const distance = scene.length * progress;
            scene.paths.forEach(({ path, start, length }) => path.style.setProperty('--line-draw', length > 0 ? Math.min(1, Math.max(0, (distance - start) / length)) : 1));
            const activePath = scene.paths.find(({ start, length }) => distance <= start + length && length > 0);
            const point = activePath ? activePath.path.getPointAtLength(Math.max(0, distance - activePath.start)) : scene.points.findLast(Boolean);
            if (scene.probe) {
                scene.probe.style.opacity = point ? '1' : '0';
                if (point) scene.probe.setAttribute('transform', `translate(${point.x},${point.y})`);
            }
            const closest = point ? scene.points.filter(Boolean).reduce((nearest, candidate) => !nearest || Math.hypot(candidate.x - point.x, candidate.y - point.y) < Math.hypot(nearest.x - point.x, nearest.y - point.y) ? candidate : nearest, null) : null;
            if (scene.index !== closest?.index) {
                scene.index = closest?.index;
                scene.readings.forEach((reading) => reading.classList.toggle('is-current', Number(reading.dataset.trendReading) === scene.index));
            }
        });

        // Surfaces rise out of depth as they enter: --e runs 0 to 1 over the lower half of the viewport.
        this.depthElements.forEach((element) => {
            // Layout position, not the transformed one: the transform is what this value drives.
            const box = element._box || layoutBox(element);
            const top = box.top - window.scrollY;
            if (top > viewportHeight + 40 || top + box.height < -40) return;
            const entry = reduceMotion() ? 1 : Math.min(Math.max((viewportHeight - top) / (viewportHeight * 0.62), 0), 1);
            if (Math.abs((element._e ?? -1) - entry) > 0.003) {
                element._e = entry;
                element.style.setProperty('--e', entry.toFixed(3));
                element.classList.toggle('is-settled', entry >= 1);
            }
        });

        // Current section: the one under the reading line.
        let current = null;
        this.sections.forEach((section) => {
            const rect = section.getBoundingClientRect();
            if (rect.top <= readingLine && rect.bottom > readingLine) current = section;
            const head = section.querySelector('.rp-head');
            if (head) {
                const past = head.getBoundingClientRect().bottom < topBar && rect.bottom > topBar + 120;
                section.classList.toggle('is-past-head', past);
            }
        });

        const chapter = this.chapterAt(readingLine);
        if (current) {
            const index = Number(current.dataset.panelIndex);
            if (index !== this.currentIndex) {
                this.currentIndex = index;
                this.onActivate?.(index);
            }
            if (this.spineCurrent) this.spineCurrent.textContent = current.dataset.storyLabel || '';
            if (index === 6) this.readLevers(current, readingLine);
        } else if (chapter && this.spineCurrent) {
            const link = this.root.querySelector(`[data-spine-chapter="${chapter}"] .pres-spine__name`);
            if (link) this.spineCurrent.textContent = `Bab ${link.textContent.trim()}`;
        }
        if (chapter && chapter !== this.currentChapter) {
            this.currentChapter = chapter;
            this.root.querySelectorAll('[data-spine-chapter]').forEach((link) => {
                const active = link.dataset.spineChapter === chapter;
                link.classList.toggle('is-active', active);
                if (active) link.setAttribute('aria-current', 'step');
                else link.removeAttribute('aria-current');
            });
        }
        this.lockThrough(this.chapterOrder.indexOf(chapter));
    }

    /** Pinned market story: scroll position picks the beat; the donut morphs to its share. */
    readMarketStories(viewportHeight) {
        this.marketStories?.forEach((story) => {
            const rect = story.getBoundingClientRect();
            if (rect.bottom < 0 || rect.top > viewportHeight) return;
            const beats = Array.from(story.querySelectorAll('[data-market-beat]'));
            if (!beats.length) return;
            const progress = Math.min(1, Math.max(0, -rect.top / Math.max(1, rect.height - viewportHeight)));
            const phase = Math.min(beats.length - 1, Math.floor(progress * beats.length));
            if (story.dataset.phase === String(phase) && story.dataset.marketReady) return;
            story.dataset.phase = String(phase);
            story.dataset.marketReady = '1';
            const key = beats[phase].dataset.marketKey;
            story.querySelectorAll('[data-market-step]').forEach((button) => button.setAttribute('aria-pressed', String(Number(button.dataset.marketStep) === phase)));
            const art = Array.from(story.querySelectorAll('template[data-market-scene-art]')).find((item) => item.getAttribute('data-market-scene-art') === key);
            const poster = story.querySelector('[data-market-poster]');
            if (art && poster) poster.innerHTML = art.innerHTML;
            const host = story.querySelector('[data-market-stage]');
            if (!host || host.dataset.marketScene === key) return;
            host.dataset.marketScene = key;
            host.dataset.marketProgress = '1';
            host.dispatchEvent(new CustomEvent('marketfocus', { detail: { key, progress: 1 } }));
        });
    }

    /** Reading the next article changes the illustration; all source text stays in the page. */
    readNarrative(journeys, readingLine, topBar, viewportHeight, kind) {
        journeys?.forEach((journey) => {
            const rect = journey.element.getBoundingClientRect();
            if (rect.bottom < topBar || rect.top > viewportHeight || !journey.articles.length) return;
            const narrow = window.matchMedia('(max-width: 1000px)').matches || viewportHeight <= 560;
            const stage = journey.element.querySelector(kind === 'market' ? '.rp-market-stage' : '.rp-mapping-stage');
            const line = narrow && stage && getComputedStyle(stage).position === 'sticky'
                ? Math.max(topBar + 48, Math.min(viewportHeight - 40, stage.getBoundingClientRect().bottom + 56)) : readingLine;
            if (rect.top > line) return;
            let active = journey.articles[0];
            for (const article of journey.articles) {
                if (article.getBoundingClientRect().top <= line) active = article;
                else break;
            }
            const bounds = active.getBoundingClientRect();
            const progress = Math.min(1, Math.max(0, (line - bounds.top) / Math.max(1, bounds.height)));
            const key = kind === 'market' ? active.dataset.marketScene : active.dataset.mappingCue;
            if (kind === 'market') {
                const changed = journey.key !== key;
                if (changed) {
                    journey.key = key;
                    journey.element.querySelector('[data-market-caption]').textContent = active.dataset.marketTitle;
                    journey.element.querySelector('[data-market-current]').innerHTML = active.querySelector('[data-market-reading]')?.innerHTML || '';
                    journey.element.querySelector('[data-market-poster]').innerHTML = active.querySelector('[data-market-art]')?.innerHTML || '';
                    journey.articles.forEach((article) => article.classList.toggle('is-current', article === active));
                }
                if (changed || Math.abs((journey.progress ?? -1) - progress) > 0.01) {
                    journey.progress = progress;
                    journey.host.dataset.marketScene = key;
                    journey.host.dataset.marketProgress = progress.toFixed(4);
                    journey.host.dispatchEvent(new CustomEvent('marketfocus', { detail: { key, progress } }));
                }
            } else {
                const reading = active.dataset.mappingView || 'members';
                const identity = `${key}:${reading}`;
                if (this.mappingCueKey === identity) return;
                this.mappingCueKey = identity;
                journey.element.querySelector('[data-mapping-caption]').textContent = active.querySelector('h3')?.textContent || '';
                journey.element.querySelector('.rp-mapping-stage__note').textContent = reading === 'penetration'
                    ? 'Warna menunjukkan penetrasi 0–100% per kecamatan.'
                    : 'Cakupan yang sedang dibahas terangkat pada peta.';
                journey.articles.forEach((article) => article.classList.toggle('is-current', article === active));
                this.root.dispatchEvent(new CustomEvent('mappingcue', { detail: { key, reading } }));
            }
        });
    }

    /** The eight strategies: the dial follows the lever under the reading line. */
    readLevers(section, readingLine) {
        let active = null;
        section.querySelectorAll('[data-lever]').forEach((lever) => {
            if (lever.getBoundingClientRect().top <= readingLine) active = lever;
        });
        const dial = section.querySelector('[data-lever-current]');
        const number = active?.dataset.lever || '1';
        if (dial && dial.textContent !== number) {
            dial.textContent = number;
            dial.classList.remove('is-ticking');
            void dial.offsetWidth;
            dial.classList.add('is-ticking');
        }
        section.querySelectorAll('[data-lever]').forEach((lever) => lever.classList.toggle('is-current', lever === active));
    }

    topBarHeight() {
        if (this.keynote) {
            // On narrow screens the chapter tabs drop below the bar; both cover the page.
            const bar = this.root.querySelector('.pres-top-bar');
            const tabs = bar?.querySelector('.kn-nav__chapters');
            const bottom = Math.max(bar?.getBoundingClientRect().bottom || 52, tabs && getComputedStyle(tabs).position === 'fixed' ? tabs.getBoundingClientRect().bottom : 0);
            return bottom;
        }
        return this.root.querySelector('.pres-top-bar')?.getBoundingClientRect().height || 64;
    }

    chapterAt(readingLine) {
        let chapter = this.chapterOrder[0] || null;
        this.root.querySelectorAll('.pres-chapter, .pres-report-section').forEach((element) => {
            if (element.getBoundingClientRect().top <= readingLine) chapter = element.dataset.chapter || chapter;
        });
        return chapter;
    }

    /** Signature move: figures lock into the spine once their chapter is reached, and stay. */
    lockThrough(chapterIndex) {
        if (chapterIndex <= this.reachedChapter) return;
        this.reachedChapter = chapterIndex;
        LEDGER_KEYS.forEach((key) => {
            const row = this.root.querySelector(`[data-ledger-row="${key}"]`);
            if (!row || row.classList.contains('is-locked')) return;
            if (this.chapterOrder.indexOf(LEDGER_CHAPTER[key]) <= chapterIndex) {
                row.classList.add('is-locked');
                if (!reduceMotion()) {
                    row.classList.add('is-locking');
                    window.setTimeout(() => row.classList.remove('is-locking'), 900);
                }
            }
        });
    }

    scrollToIndex(index, { smooth = true } = {}) {
        const section = this.sections[Math.min(Math.max(index, 0), this.sections.length - 1)];
        if (!section) return;
        this.pendingIndex = Number(section.dataset.panelIndex);
        this.pendingUntil = performance.now() + 900;
        window.scrollTo({
            top: Math.max(window.scrollY + section.getBoundingClientRect().top - this.topBarHeight() - 8, 0),
            behavior: smooth && !reduceMotion() ? 'smooth' : 'auto',
        });
    }

    step(direction) {
        const heading = this.pendingIndex !== undefined && performance.now() < this.pendingUntil;
        const from = heading ? this.pendingIndex : Math.max(this.currentIndex, 0);
        this.scrollToIndex(from + direction);
    }

    /** Fill every ledger slot (spine, chapter openers, close) from the active scope. */
    update(data, scopeKey) {
        const summary = data?.summary || {};
        const scoped = summary.scopes?.[scopeKey] || summary.scopes?.area6 || null;
        const cards = scoped?.cards || summary.cards || [];
        const byKey = Object.fromEntries(cards.map((card) => [card.key, card]));
        LEDGER_KEYS.forEach((key) => {
            const card = byKey[key];
            this.root.querySelectorAll(`[data-ledger-value="${key}"]`).forEach((slot) => {
                slot.textContent = card?.available === false || !card?.value ? 'Belum tersedia' : card.value;
            });
            this.root.querySelectorAll(`[data-ledger-trend="${key}"]`).forEach((slot) => {
                const trend = card?.trend && card.trend !== '-' ? card.trend : '';
                slot.textContent = trend ? `${trend} tren` : (card?.meta || '');
            });
        });
        const label = scoped?.scope_label || data?.scope?.options?.find((option) => option.key === scopeKey)?.label;
        if (label && !this.keynote) this.root.querySelectorAll('[data-ledger-scope]').forEach((slot) => { slot.textContent = label; });
        if (this.keynote) this.fillFigures();
        this.queueWorldData();
        this.refresh();
    }

    toggleAutoplay() {
        if (this.autoplayTimer !== null) this.stopAutoplay();
        else this.startAutoplay();
    }

    startAutoplay() {
        this.stopAutoplay();
        this.setAutoplayUi(true);
        const advance = () => {
            const next = this.currentIndex >= this.sections.length - 1 ? 0 : this.currentIndex + 1;
            this.scrollToIndex(next);
            this.autoplayTimer = window.setTimeout(advance, this.autoplayDelay);
        };
        this.autoplayTimer = window.setTimeout(advance, this.autoplayDelay);
    }

    stopAutoplay() {
        if (this.autoplayTimer === null) return;
        window.clearTimeout(this.autoplayTimer);
        this.autoplayTimer = null;
        this.setAutoplayUi(false);
    }

    setAutoplayUi(playing) {
        if (!this.autoplayButton) return;
        this.autoplayButton.setAttribute('aria-pressed', playing ? 'true' : 'false');
        this.autoplayButton.innerHTML = playing
            ? '<i class="fas fa-pause" aria-hidden="true"></i><span>Jeda otomatis</span>'
            : '<i class="fas fa-play" aria-hidden="true"></i><span>Putar otomatis</span>';
    }

    get autoplaying() {
        return this.autoplayTimer !== null;
    }
}
