{{--
    Landing Pinjaman refinements:
    - Scroll reveal: each major block settles once as it enters the viewport and
      its progress bars draw from the left. Values never animate, so the numbers
      on screen are always the real ones. Reduced-motion users get no motion.
    - Digital focus cards size to their own panel width (container queries), so
      the eight cards no longer crush their mini statistics into broken words.
--}}
<style>
    .digital-panel {
        container-type: inline-size;
        container-name: asix-digital;
    }

    @container asix-digital (max-width: 900px) {
        .digital-panel .dp-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @container asix-digital (max-width: 430px) {
        .digital-panel .dp-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    .digital-panel .dc {
        container-type: inline-size;
        min-width: 0;
    }

    .digital-panel :is(.dc-stat-lbl, .dc-stat-val, .dc-sub, .dc-val) {
        word-break: normal;
        overflow-wrap: break-word;
    }

    @container (max-width: 250px) {
        .digital-panel .dc-stats {
            grid-template-columns: minmax(0, 1fr);
        }

        .digital-panel .dc-stat {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 0.6rem;
        }

        .digital-panel .dc-stat-val {
            text-align: right;
            white-space: nowrap;
        }
    }

    /* PDWK limit cards: header captions were overridden to grey, and white
       text on the yellow/orange headers fell below 3:1 in both themes. */
    .micro-pdwk-status.micro-pdwk-status > header small {
        color: rgba(255, 255, 255, 0.9);
    }

    .micro-pdwk-status.tone-yellow > header,
    .micro-pdwk-status.tone-orange > header {
        color: #2b1d00;
    }

    .micro-pdwk-status.micro-pdwk-status.tone-yellow > header small,
    .micro-pdwk-status.micro-pdwk-status.tone-orange > header small {
        color: rgba(43, 29, 0, 0.82);
    }

    @keyframes asix-landing-reveal {
        from {
            opacity: 0;
            transform: translateY(14px);
        }
        to {
            opacity: 1;
            transform: none;
        }
    }

    @keyframes asix-landing-bar {
        from {
            transform: scaleX(0);
        }
        to {
            transform: none;
        }
    }

    :root.landing-motion-ready .landing-reveal:not(.is-revealed) {
        opacity: 0;
    }

    :root.landing-motion-ready .landing-reveal.is-revealed {
        animation: asix-landing-reveal 560ms cubic-bezier(0.16, 1, 0.3, 1) backwards;
        animation-delay: var(--reveal-delay, 0ms);
    }

    :root.landing-motion-ready .landing-reveal:not(.is-revealed) :is(.perf-bar-fill, .meter-bar, .asc-bar, .tcc-cell-bar) {
        transform: scaleX(0);
        transform-origin: left center;
    }

    :root.landing-motion-ready .landing-reveal.is-revealed :is(.perf-bar-fill, .meter-bar, .asc-bar, .tcc-cell-bar) {
        transform-origin: left center;
        animation: asix-landing-bar 900ms cubic-bezier(0.16, 1, 0.3, 1) backwards;
        animation-delay: calc(var(--reveal-delay, 0ms) + 180ms);
    }

    @media (prefers-reduced-motion: reduce) {
        :root.landing-motion-ready .landing-reveal,
        :root.landing-motion-ready .landing-reveal :is(.perf-bar-fill, .meter-bar, .asc-bar, .tcc-cell-bar) {
            opacity: 1 !important;
            transform: none !important;
            animation: none !important;
        }
    }

    @media print {
        :root.landing-motion-ready .landing-reveal {
            opacity: 1 !important;
            animation: none !important;
        }
    }
</style>
<script>
    (function () {
        var root = document.documentElement;
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce || !('IntersectionObserver' in window)) {
            return;
        }

        var SECTION_SELECTORS = [
            '.db-shell > *',
            '.landing-insights__head',
            '.landing-insights__grid > *',
            '.cabang-performance-grid > *',
            '.main-grid > *',
            '.micro-ops-section',
            '.sme-ops-section',
            '.consumer-ops-section',
        ].join(', ');

        var seen = new WeakSet();
        var queued = 0;
        var queueTimer = null;

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                observer.unobserve(entry.target);
                // Blocks entering together settle in a short sequence, not all at once.
                entry.target.style.setProperty('--reveal-delay', Math.min(queued, 4) * 70 + 'ms');
                queued += 1;
                window.clearTimeout(queueTimer);
                queueTimer = window.setTimeout(function () { queued = 0; }, 120);
                entry.target.classList.add('is-revealed');
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });

        function register(scope) {
            (scope || document).querySelectorAll(SECTION_SELECTORS).forEach(function (el) {
                if (seen.has(el) || el.offsetHeight < 48 || el.closest('.modal')) {
                    return;
                }
                seen.add(el);
                el.classList.add('landing-reveal');
                observer.observe(el);
            });
        }

        function start() {
            root.classList.add('landing-motion-ready');
            register(document);

            // Operational desks (SME, Mikro, Konsumer) arrive by AJAX after load.
            if ('MutationObserver' in window) {
                var pending = false;
                new MutationObserver(function () {
                    if (pending) {
                        return;
                    }
                    pending = true;
                    window.requestAnimationFrame(function () {
                        pending = false;
                        register(document);
                    });
                }).observe(document.querySelector('.content-wrapper') || document.body, { childList: true, subtree: true });
            }

            // Safety net: nothing may stay hidden if an observer callback never fires.
            window.setTimeout(function () {
                document.querySelectorAll('.landing-reveal:not(.is-revealed)').forEach(function (el) {
                    var rect = el.getBoundingClientRect();
                    if (rect.top < window.innerHeight && rect.bottom > 0) {
                        el.classList.add('is-revealed');
                    }
                });
            }, 1600);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    }());
</script>
