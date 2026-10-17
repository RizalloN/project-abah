import './vendor/scrollcraft.js';
import { WorldDirector } from './worlds/director.js';

// The headline figures the ledger spine carries, and the chapter that locks each one in.
const LEDGER_KEYS = ['simpanan', 'os', 'ldr', 'sml', 'npl'];
const LEDGER_CHAPTER = { simpanan: 'dana', os: 'pinjaman', ldr: 'pinjaman', sml: 'kualitas', npl: 'kualitas' };
const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const escapeText = (value) => String(value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
// Surfaces that catch light under the pointer.
const LIT_SURFACES = '.rp-paper, .rp-fig, .rp-insights > li, .rp-agenda-board > li, .rp-signals li, .rp-timeline__item';

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
    constructor({ root, onActivate, worldSource = null }) {
        this.root = root;
        this.onActivate = onActivate;
        this.worlds = new WorldDirector({ root, source: worldSource });
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
        this.root.addEventListener('click', (event) => {
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
                if (surface && !surface.contains(event.relatedTarget)) surface.classList.remove('is-lit');
            });
        }
        this.scan(this.root);
        this.schedule();
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
        surface.style.setProperty('--mx', ((event.clientX - rect.left) / Math.max(1, rect.width)).toFixed(3));
        surface.style.setProperty('--my', ((event.clientY - rect.top) / Math.max(1, rect.height)).toFixed(3));
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
        this.depthElements = Array.from(this.root.querySelectorAll('.pres-report-section .rp-paper, .pres-report-section .rp-figures'));
        scope.querySelectorAll('[data-rv]:not(.is-in)').forEach((element) => {
            if (this.revealObserver && !reduceMotion()) {
                this.revealObserver.observe(element);
                this.observedReveals.add(element);
            }
            else this.reveal(element);
        });
        this.progressElements = Array.from(this.root.querySelectorAll('[data-sp]'));
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
        const topBar = this.topBarHeight();
        const readingLine = topBar + ((viewportHeight - topBar) * 0.38);

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
            const rect = element.getBoundingClientRect();
            if (rect.top > viewportHeight + 40 || rect.bottom < -40) return;
            const entry = reduceMotion() ? 1 : Math.min(Math.max((viewportHeight - rect.top) / (viewportHeight * 0.58), 0), 1);
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
        if (label) this.root.querySelectorAll('[data-ledger-scope]').forEach((slot) => { slot.textContent = label; });
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
