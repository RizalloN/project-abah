import { installPresentationChartManager } from './pres-charts';
import { PresentationDataLoader } from './pres-data-loader';
import { setupPresentationInteractions } from './pres-interactions';
import { StructuredDeckRenderer } from './pres-structured-deck';

const presentationConfig = window.__PRESENTATION_CONFIG__ || {};

document.addEventListener('DOMContentLoaded', async function() {
      // Relocate loader to document body to guarantee perfect centering free of parent transforms
      const globalLoader = document.getElementById('dashboard-global-loader');
      if (globalLoader) {
        document.body.appendChild(globalLoader);
      }

      const presMode = document.getElementById('apple-presentation-mode');
      const presPrevBtn = document.getElementById('pres-prev-btn');
      const presNextBtn = document.getElementById('pres-next-btn');
      const presStartBtn = document.getElementById('pres-start-slides-btn');
      const presFinishBtn = document.getElementById('pres-finish-close-btn');
      const presDots = document.getElementById('pres-paginator-dots');
      const presPeriodeSelector = document.getElementById('pres-periode-selector');
      const presPrognosaToggle = document.getElementById('pres-prognosa-toggle');
      const presPrognosaControl = document.getElementById('pres-prognosa-control');
      const presPrognosaState = document.getElementById('pres-prognosa-state');
      const presAutoplayBtn = document.getElementById('pres-autoplay-btn');
      const presAutoState = document.getElementById('pres-auto-state');
      const presAutoProgressFill = document.getElementById('pres-auto-progress-fill');
      const presLiveStatus = document.getElementById('pres-live-status');
      const presSlidesContainer = document.querySelector('.pres-slides-container');
      const presTopBar = document.querySelector('.pres-top-bar');
      const presBottomBar = document.querySelector('.pres-bottom-bar');
      const chartManager = installPresentationChartManager();
      const presentationDataLoader = new PresentationDataLoader(presentationConfig);
      const structuredDeckEnabled = true;

      let currentSlideIndex = 0;
      const totalSlides = document.querySelectorAll('.apple-slide').length;
      const autoplayDelay = 9000;
      let autoplayTimer = null;
      let autoplayEnabled = false;
      let presentationData = null;
      let timeseriesChartDana = null;
      let timeseriesChartQuality = null;
      let performanceChart = null;
      let performanceBarChart = null;
      let riskCompositionChart = null;
      let digitalChart = null;
      let productivityChart = null;
      let trendLabChart = null;
      let presentationInteractions = null;
      let ktsLoadPromise = null;
      const performanceState = { metric: 'simpanan', scope: 'area6', view: 'table' };
      const segmentState = { metric: 'sme_os', scope: 'area6' };
      const riskState = { scope: 'area6' };
      const ktsState = { category: 'membaik', scope: 'ritel' };
      const digitalState = { view: 'table' };
      const productivityState = { category: 'retail_sme' };
      const trendState = { group: 'business' };
      const branchAnalysisState = { scope: 'all' };
      const branchDetailState = { scope: null };
      const presentationQuery = new URLSearchParams(window.location.search);
      const presentationHash = new URLSearchParams(window.location.hash.replace(/^#/, ''));
      const prognosaQueryValue = presentationHash.get('prognosa') ?? presentationQuery.get('prognosa');
      const deckState = { scope: presentationHash.get('scope') || presentationQuery.get('scope') || 'area6',
        usePrognosa: ['1', 'true', 'yes', 'on'].includes(String(prognosaQueryValue || '').toLowerCase()) };
      const structuredDeck = new StructuredDeckRenderer({
        root: presMode || document,
        getScope: () => deckState.scope,
        getUsePrognosa: () => deckState.usePrognosa,
        chartManager,
        requestKts: () => loadPresentationKts(),
      });
      const requestedSlideIndex = Math.min(
        Math.max(Number.parseInt(presentationHash.get('slide') || presentationQuery.get('slide') || '0', 10) || 0, 0),
        Math.max(totalSlides - 1, 0)
      );
      const baseLogicalSlideWidth = 1440;
      const logicalSlideHeight = 810;
      let stageLayoutFrame = null;
      let stageLayoutObserver = null;

      const presentationCharts = () => [
        timeseriesChartDana,
        timeseriesChartQuality,
        performanceChart,
        performanceBarChart,
        riskCompositionChart,
        digitalChart,
        productivityChart,
        trendLabChart,
      ].filter(Boolean);

      const resizePresentationChart = (chart) => {
        const container = chart?.canvas?.parentElement;
        if (!chart || !container) return;

        const style = window.getComputedStyle(container);
        const width = Math.max(
          1,
          container.clientWidth - (Number.parseFloat(style.paddingLeft) || 0) - (Number.parseFloat(style.paddingRight) || 0)
        );
        const height = Math.max(
          1,
          container.clientHeight - (Number.parseFloat(style.paddingTop) || 0) - (Number.parseFloat(style.paddingBottom) || 0)
        );
        chart.resize(Math.floor(width), Math.floor(height));
      };

      const collectPresentationLayoutAudit = () => {
        const measureElement = (selector) => {
          const element = document.querySelector(selector);
          if (!element) return null;
          const rect = element.getBoundingClientRect();
          const style = window.getComputedStyle(element);
          return {
            selector,
            display: style.display,
            visible: style.visibility !== 'hidden' && style.display !== 'none',
            left: Math.round(rect.left),
            top: Math.round(rect.top),
            right: Math.round(rect.right),
            bottom: Math.round(rect.bottom),
            width: Math.round(rect.width),
            height: Math.round(rect.height),
            scrollWidth: element.scrollWidth,
            clientWidth: element.clientWidth,
            overflowX: element.scrollWidth > element.clientWidth + 2,
            outsideViewport: rect.left < -2
              || rect.top < -2
              || rect.right > window.innerWidth + 2
              || rect.bottom > window.innerHeight + 2,
          };
        };

        return {
          viewport: {
            width: window.innerWidth,
            height: window.innerHeight,
            orientation: presMode?.dataset.presentationOrientation || null,
            scale: Number(presMode?.dataset.presentationScale || 0),
          },
          chrome: [
            '.pres-top-bar',
            '.pres-title-brand',
            '.pres-controls-right',
            '.pres-slides-container',
            '.pres-bottom-bar',
          ].map(measureElement).filter(Boolean),
          slides: Array.from(document.querySelectorAll('.apple-slide')).map((slide, index) => ({
            index,
            id: slide.id,
            active: slide.classList.contains('active'),
            width: slide.clientWidth,
            height: slide.clientHeight,
            scrollWidth: slide.scrollWidth,
            scrollHeight: slide.scrollHeight,
            overflowX: slide.scrollWidth > slide.clientWidth + 2,
            overflowY: slide.scrollHeight > slide.clientHeight + 2,
            horizontalScrollRegions: Array.from(slide.querySelectorAll('.pres-table-scroll'))
              .filter(region => region.scrollWidth > region.clientWidth + 2)
              .map(region => ({
                id: region.id || null,
                width: region.clientWidth,
                scrollWidth: region.scrollWidth,
              })),
          })),
        };
      };

      const publishPresentationLayoutAudit = () => {
        const audit = collectPresentationLayoutAudit();
        document.querySelectorAll('.apple-slide').forEach((slide, index) => {
          const result = audit.slides[index];
          slide.dataset.layoutOverflow = result?.overflowX || result?.overflowY ? 'true' : 'false';
        });

        if (!presentationQuery.has('layout_audit')) return;

        let output = document.getElementById('presentation-layout-audit');
        if (!output) {
          output = document.createElement('pre');
          output.id = 'presentation-layout-audit';
          output.hidden = true;
          document.body.appendChild(output);
        }
        output.textContent = JSON.stringify(audit);
      };

      const fitPresentationStage = () => {
        if (!presMode || !presSlidesContainer) return;

        const viewportWidth = Math.max(window.innerWidth, 1);
        const viewportHeight = Math.max(window.innerHeight, 1);
        const measuredTop = Math.max(8, (presTopBar?.getBoundingClientRect().bottom || 0) + 8);
        const measuredBottom = Math.min(
          viewportHeight - 8,
          (presBottomBar?.getBoundingClientRect().top || viewportHeight) - 8
        );
        const minimumUsableHeight = Math.min(
          logicalSlideHeight,
          Math.max(180, viewportHeight * 0.35)
        );
        const measurementsAreUsable = measuredBottom - measuredTop >= minimumUsableHeight;
        const fallbackTop = Math.min(Math.max(72, viewportHeight * 0.08), 104);
        const fallbackBottom = Math.max(
          fallbackTop + 1,
          viewportHeight - Math.min(Math.max(72, viewportHeight * 0.08), 104)
        );
        const topSafe = measurementsAreUsable ? measuredTop : fallbackTop;
        const bottomSafe = measurementsAreUsable ? measuredBottom : fallbackBottom;
        const availableWidth = Math.max(viewportWidth - 16, 1);
        const availableHeight = Math.max(bottomSafe - topSafe, 1);
        const logicalSlideWidth = Math.max(
          baseLogicalSlideWidth,
          Math.round(logicalSlideHeight * (availableWidth / availableHeight))
        );
        const scale = Math.max(
          0.05,
          Math.min(availableWidth / logicalSlideWidth, availableHeight / logicalSlideHeight)
        );

        presMode.style.setProperty('--pres-slide-width', `${logicalSlideWidth}px`);
        presMode.style.setProperty('--pres-stage-scale', scale.toFixed(6));
        presMode.style.setProperty('--pres-stage-center-y', `${topSafe + (availableHeight / 2)}px`);
        presMode.dataset.presentationOrientation = viewportWidth >= viewportHeight ? 'landscape' : 'portrait';
        presMode.dataset.presentationScale = scale.toFixed(6);

        window.requestAnimationFrame(() => {
          presentationCharts().forEach(resizePresentationChart);
          publishPresentationLayoutAudit();
        });
      };

      const schedulePresentationFit = () => {
        if (stageLayoutFrame !== null) {
          window.cancelAnimationFrame(stageLayoutFrame);
        }
        stageLayoutFrame = window.requestAnimationFrame(() => {
          stageLayoutFrame = null;
          fitPresentationStage();
        });
      };

      window.__presentationLayoutAudit = collectPresentationLayoutAudit;
      window.addEventListener('resize', schedulePresentationFit, { passive: true });
      window.addEventListener('orientationchange', () => {
        window.setTimeout(schedulePresentationFit, 120);
      }, { passive: true });
      window.visualViewport?.addEventListener('resize', schedulePresentationFit, { passive: true });
      document.fonts?.ready.then(schedulePresentationFit);
      if (typeof ResizeObserver === 'function' && (presTopBar || presBottomBar)) {
        stageLayoutObserver = new ResizeObserver(schedulePresentationFit);
        if (presTopBar) stageLayoutObserver.observe(presTopBar);
        if (presBottomBar) stageLayoutObserver.observe(presBottomBar);
      }
      schedulePresentationFit();
      window.setTimeout(schedulePresentationFit, 80);
      window.setTimeout(schedulePresentationFit, 240);

      // Period change handler
      if (presPeriodeSelector) {
        presPeriodeSelector.addEventListener('change', function() {
          document.getElementById('dashboard-global-loader').classList.add('active');
          const url = new URL(window.location.href);
          url.searchParams.set('periode', this.value);
          url.searchParams.set('scope', deckState.scope);
          url.searchParams.set('prognosa', deckState.usePrognosa ? '1' : '0');
          window.location.href = url.toString();
        });
      }

      // Safe number formatting helper
      const displayValue = (value, fallback = '–') => {
        if (value === null || value === undefined || value === '') return fallback;
        return String(value);
      };

      const escapeHtml = (value) => displayValue(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const formatCurrencyCompact = (value) => {
        const numeric = Number(value || 0);
        const abs = Math.abs(numeric);
        const formatter = new Intl.NumberFormat('id-ID', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });

        if (abs >= 1000000000000) return 'Rp' + formatter.format(numeric / 1000000000000) + ' T';
        if (abs >= 1000000000) return 'Rp' + formatter.format(numeric / 1000000000) + ' M';
        if (abs >= 1000000) return 'Rp' + formatter.format(numeric / 1000000) + ' Jt';
        return 'Rp' + new Intl.NumberFormat('id-ID').format(numeric);
      };

      const formatPercent = (value) => {
        const numeric = Number(value || 0);
        return new Intl.NumberFormat('id-ID', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }).format(numeric) + '%';
      };

      const sumNumeric = (rows, key) => rows.reduce((total, row) => total + Number(row?.[key] || 0), 0);

      const colorForAchievement = (value) => {
        if (!value || value === '-') return '#64748b';
        // Clean and normalize percentage string (supports both id-ID and en-US locales)
        let clean = String(value).replace('%', '').trim();
        if (clean.includes(',') && clean.includes('.')) {
          clean = clean.replace(/\./g, '').replace(',', '.');
        } else if (clean.includes(',')) {
          clean = clean.replace(',', '.');
        }
        const numeric = parseFloat(clean);
        if (Number.isNaN(numeric)) return '#64748b';

        if (numeric >= 100) {
          return '#16a34a'; // Hijau (Premium green for >=100%)
        } else if (numeric >= 90) {
          return '#ca8a04'; // Kuning (Premium gold-yellow for >=90%)
        }
        return '#dc2626';   // Merah (Premium vibrant red for <90%)
      };

      const metricTone = (metric) => ({
        simpanan: '#1155c8',
        os: '#059669',
        sml: '#d97706',
        npl: '#dc2626'
      }[metric] || '#1155c8');

      const metricLabel = (metric) => ({
        simpanan: 'Simpanan',
        os: 'OS',
        sml: 'SML',
        npl: 'NPL'
      }[metric] || 'Simpanan');

      const parseCompactNumber = (value) => {
        const text = String(value || '').replace(/\s/g, '').replace('Rp', '').replace(/\./g, '').replace(',', '.');
        const numeric = parseFloat(text.replace(/[^0-9.-]/g, ''));
        return Number.isFinite(numeric) ? numeric : 0;
      };

      const digitalTrendNumber = (trend) => {
        const numeric = parseFloat(String(trend || '').replace('%', '').replace(/\./g, '').replace(',', '.'));
        return Number.isFinite(numeric) ? numeric : 0;
      };

      const renderCoverMiniSeries = (series) => {
        const points = Array.isArray(series?.points) ? series.points : [];
        const color = /^#[0-9a-f]{6}$/i.test(series?.tone || '') ? series.tone : '#1155c8';
        const values = points.map(point => {
          const numeric = Number(point?.value);
          return Number.isFinite(numeric) ? numeric : null;
        });
        const validValues = values.filter(value => value !== null);

        if (points.length < 4 || validValues.length < 2) {
          return `
            <div class="pres-cover-card-chart" style="position: relative;">
              <div class="pres-mini-series-empty">Data belum tersedia</div>
              <div class="pres-mini-series-labels">
                <span>YtD</span><span>MtM</span><span>MtD</span><span>Posisi</span>
              </div>
            </div>
          `;
        }

        const min = Math.min(...validValues);
        const max = Math.max(...validValues);
        const range = max - min;

        // Add padding margins so circular markers are fully inside the card container bounds
        const coords = values.map((value, index) => {
          if (value === null) return null;
          const x = points.length === 1 ? 50 : 6 + (index / (points.length - 1)) * 88;
          const y = range === 0 ? 40 : 15 + (1 - ((value - min) / range)) * 50;
          return { x, y, point: points[index] };
        });

        const pathPoints = coords
          .filter(Boolean)
          .map(item => `${item.x.toFixed(2)},${item.y.toFixed(2)}`)
          .join(' ');
        const areaCoords = coords.filter(Boolean);
        const areaPath = areaCoords.length >= 2
          ? `M ${areaCoords[0].x.toFixed(2)} 95 L ${areaCoords.map(item => `${item.x.toFixed(2)} ${item.y.toFixed(2)}`).join(' L ')} L ${areaCoords[areaCoords.length - 1].x.toFixed(2)} 95 Z`
          : '';

        // Build premium crisp HTML overlays for dots and labels to keep them HD and avoid SVG aspect ratio stretching
        const overlayItems = coords.filter(Boolean).map(item => {
          const valDisplay = item.point?.display_value || '-';
          let compactVal = valDisplay.replace('Rp', '').replace(' rek', '').replace(' strategi', '');
          return `
            <div style="position: absolute; left: ${item.x}%; top: ${item.y}%; width: 5px; height: 5px; background: #ffffff; border: 1.8px solid ${color}; border-radius: 50%; transform: translate(-50%, -50%); box-shadow: 0 1px 2px rgba(0,0,0,0.15); z-index: 2;"></div>
            <div style="position: absolute; left: ${item.x}%; top: ${item.y}%; transform: translate(-50%, -100%); margin-top: -6px; font-size: 0.6rem; font-weight: 850; color: #1e293b; font-family: 'Inter', sans-serif; white-space: nowrap; z-index: 3; text-shadow: 0 1px 2px #ffffff, 0 -1px 2px #ffffff, 1px 0 2px #ffffff, -1px 0 2px #ffffff;">
              ${escapeHtml(compactVal)}
            </div>
          `;
        }).join('');

        return `
          <div class="pres-cover-card-chart" style="position: relative; min-height: 72px;">
            <svg style="display: block; width: 100%; height: 52px; overflow: visible;" viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="${escapeHtml(series?.label || 'Timeseries')}">
              <line x1="0" y1="95" x2="100" y2="95" stroke="rgba(148, 163, 184, 0.2)" stroke-width="1"></line>
              ${areaPath ? `<path d="${areaPath}" fill="${color}" opacity="0.07"></path>` : ''}
              <polyline points="${pathPoints}" fill="none" stroke="${color}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></polyline>
            </svg>
            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 52px; pointer-events: none;">
              ${overlayItems}
            </div>
            <div class="pres-mini-series-labels" style="margin-top: 4px;">
              ${points.map(point => `<span title="${escapeHtml(point?.period_label || '-')}">${escapeHtml(point?.label || '-')}</span>`).join('')}
            </div>
          </div>
        `;
      };

      const setActiveButton = (container, attr, value) => {
        if (!container) return;
        container.querySelectorAll('.pres-toggle-btn').forEach(btn => {
          btn.classList.toggle('active', btn.getAttribute(attr) === value);
        });
      };

      const slideStoryLabels = [
        'Cover',
        'Daftar isi',
        'Summary funding',
        'Funding per produk',
        '8 strategi funding',
        'Outstanding summary',
        'Pinjaman SME',
        'Pinjaman Konsumer',
        'Highlight mikro',
        'Kualitas SML',
        'Kualitas NPL',
        'Timeseries terintegrasi',
        'Prioritas aksi'
      ];

      const countUpFrames = new WeakMap();

      const renderCountUpValue = (el, value, isCurrency, isPercent, suffix) => {
        if (isCurrency) {
          el.textContent = formatCurrencyCompact(value);
        } else if (isPercent) {
          el.textContent = value.toFixed(2).replace('.', ',') + '%';
        } else {
          el.textContent = Math.floor(value).toLocaleString('id-ID') + suffix;
        }
      };

      const runCountUpAnimationsInSlide = (slideIndex) => {
        const slide = document.getElementById('pres-slide-' + slideIndex);
        if (!slide) return;

        const elements = slide.querySelectorAll('[data-raw-val]');
        elements.forEach(el => {
          const endVal = parseFloat(el.getAttribute('data-raw-val') || '0');
          if (isNaN(endVal)) return;
          const isCurrency = el.getAttribute('data-is-currency') === 'true';
          const isPercent = el.getAttribute('data-is-percent') === 'true';
          const suffix = el.getAttribute('data-suffix') || '';
          const valueKey = [endVal, isCurrency, isPercent, suffix].join('|');

          const activeFrame = countUpFrames.get(el);
          if (activeFrame) window.cancelAnimationFrame(activeFrame);

          if (el.dataset.countupKey === valueKey && el.dataset.countupComplete === 'true') {
            renderCountUpValue(el, endVal, isCurrency, isPercent, suffix);
            return;
          }

          el.dataset.countupKey = valueKey;
          el.dataset.countupComplete = 'false';

          let startTimestamp = null;
          const duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 420;

          const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = duration === 0 ? 1 : Math.min((timestamp - startTimestamp) / duration, 1);
            const easedProgress = 1 - Math.pow(1 - progress, 3);
            renderCountUpValue(el, easedProgress * endVal, isCurrency, isPercent, suffix);

            if (progress < 1) {
              countUpFrames.set(el, window.requestAnimationFrame(step));
            } else {
              renderCountUpValue(el, endVal, isCurrency, isPercent, suffix);
              el.dataset.countupComplete = 'true';
              countUpFrames.delete(el);
            }
          };
          countUpFrames.set(el, window.requestAnimationFrame(step));
        });
      };

      const resetAutoplayProgress = () => {
        if (!presAutoProgressFill) return;
        presAutoProgressFill.classList.remove('is-running');
        presAutoProgressFill.style.width = '0%';
        void presAutoProgressFill.offsetWidth;
        if (autoplayEnabled) {
          presAutoProgressFill.style.setProperty('--pres-autoplay-duration', autoplayDelay + 'ms');
          presAutoProgressFill.classList.add('is-running');
        }
      };

      const updateAutoplayUi = () => {
        if (presAutoplayBtn) {
          presAutoplayBtn.classList.toggle('is-running', autoplayEnabled);
          presAutoplayBtn.setAttribute('title', autoplayEnabled ? 'Jeda autoplay' : 'Putar otomatis');
          presAutoplayBtn.innerHTML = autoplayEnabled ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>';
        }
        if (presAutoState) {
          presAutoState.textContent = autoplayEnabled ? 'ON' : 'OFF';
        }
        resetAutoplayProgress();
      };

      const scheduleAutoplay = () => {
        if (autoplayTimer !== null) {
          window.clearTimeout(autoplayTimer);
          autoplayTimer = null;
        }
        if (!autoplayEnabled) return;

        autoplayTimer = window.setTimeout(() => {
          const nextIndex = currentSlideIndex >= totalSlides - 1 ? 0 : currentSlideIndex + 1;
          showSlide(nextIndex);
          scheduleAutoplay();
        }, autoplayDelay);
      };

      const startAutoplay = () => {
        autoplayEnabled = true;
        updateAutoplayUi();
        scheduleAutoplay();
      };

      const stopAutoplay = () => {
        autoplayEnabled = false;
        if (autoplayTimer !== null) {
          window.clearTimeout(autoplayTimer);
          autoplayTimer = null;
        }
        updateAutoplayUi();
      };

      const showSlide = (index) => {
        currentSlideIndex = index;
        const slides = document.querySelectorAll('.apple-slide');
        slides.forEach((slide, idx) => {
          slide.classList.remove('active', 'prev');
          if (idx === index) {
            slide.classList.add('active');
          } else if (idx < index) {
            slide.classList.add('prev');
          }
        });

        // Toggle dark theme for cover and closing slides.
        if (presMode) {
          const manualDark = presMode.classList.contains('manual-dark');
          presMode.classList.toggle('theme-dark', manualDark || index === 0 || index === 12);
        }

        // Run count-up animations on active slide elements
        runCountUpAnimationsInSlide(index);

        // Update dots
        const dots = document.querySelectorAll('.pres-dot');
        dots.forEach((dot, idx) => {
          dot.classList.toggle('active', idx === index);
        });

        // Update slide counter text
        const slideCounter = document.getElementById('pres-slide-counter-badge');
        if (slideCounter) {
          slideCounter.innerText = 'Slide ' + (index + 1) + ' dari ' + totalSlides;
        }
        if (presLiveStatus) {
          presLiveStatus.textContent = slideStoryLabels[index] || 'Live Dashboard';
        }
        if (autoplayEnabled) {
          resetAutoplayProgress();
        }

        if (structuredDeckEnabled) {
          structuredDeck.activate(index);
          schedulePresentationFit();
          window.setTimeout(() => {
            const activeSlide = document.getElementById(`pres-slide-${index}`);
            chartManager.activate(activeSlide);
          }, 80);
          presentationDataLoader.preloadForSlide(index);
          presentationInteractions?.syncSlide(index);
          return;
        }

        // Trigger animations
        if (index === 5 && presentationData) {
          renderBranchAnalysis();
        }

        if (index === 7 && presentationData) {
          renderBranchWarRoom(presentationData);
        }

        if (false && index === 5 && presentationData) {
          const tsData = presentationData.timeseries || {};
          if (tsData.available && tsData.series) {
            const canvasDana = document.getElementById('pres-timeseries-chart-dana');
            const canvasQuality = document.getElementById('pres-timeseries-chart-quality');

            const simpananSeries = tsData.series.find(s => s.key === 'simpanan_total') || {};
            const osSeries = tsData.series.find(s => s.key === 'os_total') || {};
            const smlSeries = tsData.series.find(s => s.key === 'sml_nominal') || {};
            const nplSeries = tsData.series.find(s => s.key === 'npl_nominal') || {};

            // 1. Render Simpanan vs OS Chart
            if (canvasDana) {
              const ctx = canvasDana.getContext('2d');
              if (timeseriesChartDana) {
                timeseriesChartDana.destroy();
              }

              const blueGradient = ctx.createLinearGradient(0, 0, 0, 300);
              blueGradient.addColorStop(0, 'rgba(0, 113, 227, 0.22)');
              blueGradient.addColorStop(1, 'rgba(0, 113, 227, 0.01)');

              const greenGradient = ctx.createLinearGradient(0, 0, 0, 300);
              greenGradient.addColorStop(0, 'rgba(16, 185, 129, 0.22)');
              greenGradient.addColorStop(1, 'rgba(16, 185, 129, 0.01)');

              timeseriesChartDana = new Chart(ctx, {
                type: 'line',
                data: {
                  labels: tsData.labels || [],
                  datasets: [
                    {
                      label: 'Total Simpanan',
                      data: simpananSeries.values || [],
                      borderColor: '#0071e3',
                      borderWidth: 3,
                      backgroundColor: blueGradient,
                      fill: true,
                      tension: 0.32,
                      pointBackgroundColor: '#0071e3',
                      pointBorderColor: '#ffffff',
                      pointBorderWidth: 2,
                      pointRadius: 4,
                      pointHoverRadius: 6
                    },
                    {
                      label: 'Total OS',
                      data: osSeries.values || [],
                      borderColor: '#10b981',
                      borderWidth: 3,
                      backgroundColor: greenGradient,
                      fill: true,
                      tension: 0.32,
                      pointBackgroundColor: '#10b981',
                      pointBorderColor: '#ffffff',
                      pointBorderWidth: 2,
                      pointRadius: 4,
                      pointHoverRadius: 6
                    }
                  ]
                },
                options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: {
                    legend: {
                      position: 'top',
                      labels: {
                        font: { family: 'Inter', size: 10, weight: '600' },
                        color: '#1d1d1f',
                        usePointStyle: true,
                        padding: 8
                      }
                    },
                    tooltip: {
                      backgroundColor: 'rgba(255, 255, 255, 0.95)',
                      titleColor: '#1d1d1f',
                      bodyColor: '#1d1d1f',
                      borderColor: 'rgba(0,0,0,0.08)',
                      borderWidth: 1,
                      padding: 10,
                      titleFont: { family: 'Inter', weight: 'bold', size: 11 },
                      bodyFont: { family: 'Inter', size: 11 },
                      callbacks: {
                        label: function(context) {
                          let label = context.dataset.label || '';
                          if (label) label += ': ';
                          if (context.parsed.y !== null) {
                            label += 'Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y) + ' Juta';
                          }
                          return label;
                        }
                      }
                    }
                  },
                  scales: {
                    x: {
                      grid: { display: false },
                      ticks: {
                        font: { family: 'Inter', size: 8, weight: '500' },
                        color: 'rgba(0,0,0,0.5)'
                      }
                    },
                    y: {
                      grid: { color: 'rgba(0,0,0,0.04)' },
                      ticks: {
                        font: { family: 'Inter', size: 8 },
                        color: 'rgba(0,0,0,0.5)',
                        callback: function(value) {
                          return 'Rp ' + new Intl.NumberFormat('id-ID').format(value / 1000) + ' M';
                        }
                      }
                    }
                  }
                }
              });
            }

            // 2. Render OS, SML, NPL Chart (with dual Y axes so SML & NPL trends are clearly readable)
            if (canvasQuality) {
              const ctx = canvasQuality.getContext('2d');
              if (timeseriesChartQuality) {
                timeseriesChartQuality.destroy();
              }

              const greenGradient = ctx.createLinearGradient(0, 0, 0, 300);
              greenGradient.addColorStop(0, 'rgba(16, 185, 129, 0.15)');
              greenGradient.addColorStop(1, 'rgba(16, 185, 129, 0.01)');

              const orangeGradient = ctx.createLinearGradient(0, 0, 0, 300);
              orangeGradient.addColorStop(0, 'rgba(245, 158, 11, 0.15)');
              orangeGradient.addColorStop(1, 'rgba(245, 158, 11, 0.01)');

              const redGradient = ctx.createLinearGradient(0, 0, 0, 300);
              redGradient.addColorStop(0, 'rgba(239, 68, 68, 0.15)');
              redGradient.addColorStop(1, 'rgba(239, 68, 68, 0.01)');

              timeseriesChartQuality = new Chart(ctx, {
                type: 'line',
                data: {
                  labels: tsData.labels || [],
                  datasets: [
                    {
                      label: 'Total OS (Kiri)',
                      data: osSeries.values || [],
                      borderColor: '#10b981',
                      borderWidth: 2.5,
                      backgroundColor: greenGradient,
                      fill: true,
                      tension: 0.32,
                      pointBackgroundColor: '#10b981',
                      pointBorderColor: '#ffffff',
                      pointBorderWidth: 1.5,
                      pointRadius: 3.5,
                      pointHoverRadius: 5.5,
                      yAxisID: 'y'
                    },
                    {
                      label: 'Nominal Kol 2 / SML (Kanan)',
                      data: smlSeries.values || [],
                      borderColor: '#f59e0b',
                      borderWidth: 2.5,
                      backgroundColor: orangeGradient,
                      fill: true,
                      tension: 0.32,
                      pointBackgroundColor: '#f59e0b',
                      pointBorderColor: '#ffffff',
                      pointBorderWidth: 1.5,
                      pointRadius: 3.5,
                      pointHoverRadius: 5.5,
                      yAxisID: 'y1'
                    },
                    {
                      label: 'Nominal Kol 3-5 / NPL (Kanan)',
                      data: nplSeries.values || [],
                      borderColor: '#ef4444',
                      borderWidth: 2.5,
                      backgroundColor: redGradient,
                      fill: true,
                      tension: 0.32,
                      pointBackgroundColor: '#ef4444',
                      pointBorderColor: '#ffffff',
                      pointBorderWidth: 1.5,
                      pointRadius: 3.5,
                      pointHoverRadius: 5.5,
                      yAxisID: 'y1'
                    }
                  ]
                },
                options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: {
                    legend: {
                      position: 'top',
                      labels: {
                        font: { family: 'Inter', size: 10, weight: '600' },
                        color: '#1d1d1f',
                        usePointStyle: true,
                        padding: 8
                      }
                    },
                    tooltip: {
                      backgroundColor: 'rgba(255, 255, 255, 0.95)',
                      titleColor: '#1d1d1f',
                      bodyColor: '#1d1d1f',
                      borderColor: 'rgba(0,0,0,0.08)',
                      borderWidth: 1,
                      padding: 10,
                      titleFont: { family: 'Inter', weight: 'bold', size: 11 },
                      bodyFont: { family: 'Inter', size: 11 },
                      callbacks: {
                        label: function(context) {
                          let label = context.dataset.label || '';
                          if (label) label += ': ';
                          if (context.parsed.y !== null) {
                            label += 'Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y) + ' Juta';
                          }
                          return label;
                        }
                      }
                    }
                  },
                  scales: {
                    x: {
                      grid: { display: false },
                      ticks: {
                        font: { family: 'Inter', size: 8, weight: '500' },
                        color: 'rgba(0,0,0,0.5)'
                      }
                    },
                    y: {
                      type: 'linear',
                      display: true,
                      position: 'left',
                      grid: { color: 'rgba(0,0,0,0.04)' },
                      ticks: {
                        font: { family: 'Inter', size: 8 },
                        color: 'rgba(0,0,0,0.5)',
                        callback: function(value) {
                          return 'Rp ' + new Intl.NumberFormat('id-ID').format(value / 1000) + ' M';
                        }
                      }
                    },
                    y1: {
                      type: 'linear',
                      display: true,
                      position: 'right',
                      grid: { drawOnChartArea: false }, // Only keep grid lines from left axis
                      ticks: {
                        font: { family: 'Inter', size: 8 },
                        color: 'rgba(0,0,0,0.5)',
                        callback: function(value) {
                          return 'Rp ' + new Intl.NumberFormat('id-ID').format(value / 1000) + ' M';
                        }
                      }
                    }
                  }
                }
              });
            }
          }
        }

        if (index === 9 && presentationData) {
          renderPerformanceExplorer();
        }

        if (index === 10 && presentationData) {
          renderSegmentExplorer();
        }

        if (index === 11 && presentationData) {
          renderRiskOverview();
        }

        if (index === 14 && presentationData) {
          if (presentationData?.kts?.loading_details) {
            loadPresentationKts();
          }
          renderKts();
        }

        if (index === 15 && presentationData) {
          renderProductivity();
        }

        if (index === 16 && presentationData) {
          renderTrendLab();
        }

        if (index === 17 && presentationData) {
          renderDigitalTableAndChart();
        }

        schedulePresentationFit();
        window.setTimeout(() => {
          const activeSlide = document.getElementById(`pres-slide-${index}`);
          chartManager.activate(activeSlide);
        }, 80);
        presentationDataLoader.preloadForSlide(index);
        presentationInteractions?.syncSlide(index);
      };

      const deckScopeOptions = (data = presentationData) => {
        const options = data?.scope?.options || data?.performance_overview?.matrix?.scope_options || [];
        return Array.isArray(options) ? options : [];
      };

      const activeScopeOption = (data = presentationData) => {
        const options = deckScopeOptions(data);
        return options.find(option => option.key === deckState.scope)
          || options.find(option => option.key === 'area6')
          || { key: 'area6', label: 'Area 6 Konsol' };
      };

      const activeScopeLabel = (data = presentationData) => activeScopeOption(data).label || 'Area 6 Konsol';

      const allBranchRows = (data = presentationData) => {
        const rows = data?.performance_overview?.branches || [];
        return Array.isArray(rows) ? rows : [];
      };

      const scopedBranchRows = (data = presentationData) => {
        const branches = allBranchRows(data);
        if (deckState.scope === 'area6') return branches;

        return branches.filter(branch => String(branch?.name || '').toUpperCase() === String(deckState.scope).toUpperCase());
      };

      const activeScopedSection = (key, data = presentationData) => {
        const section = data?.[key] || {};
        return section?.scopes?.[deckState.scope] || section;
      };

      const activeSummary = (data = presentationData) => activeScopedSection('summary', data);
      const activeSavings = (data = presentationData) => activeScopedSection('savings_breakdown', data);
      const activeLoanProducts = (data = presentationData) => activeScopedSection('loan_products', data);
      const activeFinancial = (data = presentationData) => activeScopedSection('financial_highlights', data);

      const updateDeckScopeLabels = (data = presentationData) => {
        const option = activeScopeOption(data);
        const label = option.label || 'Area 6 Konsol';
        const isArea = option.key === 'area6';
        const shortLabel = isArea ? 'Area 6' : label;
        const title = document.querySelector('.pres-title-lbl');

        if (title) {
          title.innerHTML = isArea
            ? 'Kinerja Area 6 <span>- Madiun, Magetan, Ngawi, Ponorogo</span>'
            : `Kinerja ${escapeHtml(label)} <span>- Scope cabang terpilih</span>`;
        }

        setText('pres-cover-scope-label', isArea ? 'Area 6 - Madiun, Magetan, Ngawi, Ponorogo' : `${label} - Branch Performance Review`);
        setText('pres-loan-highlight-kicker', `${shortLabel} Loan Highlight`);
        setText('pres-loan-highlight-title', `Highlight Pinjaman ${shortLabel}`);
        setText('pres-funding-highlight-kicker', `${shortLabel} Funding Highlight`);
        setText('pres-funding-highlight-title', `Highlight Simpanan ${shortLabel}`);
        setText('pres-digital-scope-badge', isArea ? 'Scope: Area 6 Konsol' : `Benchmark Area 6 untuk ${label}`);
      };

      const populateGlobalScopeControls = (data) => {
        const options = deckScopeOptions(data);
        if (!options.length) return;
        if (!options.some(option => option.key === deckState.scope)) {
          deckState.scope = options[0]?.key || 'area6';
        }

        const globalSelector = document.getElementById('pres-global-scope-selector');
        if (globalSelector) {
          globalSelector.innerHTML = options.map(option => `
            <option value="${escapeHtml(option.key)}">${escapeHtml(option.label)}</option>
          `).join('');
          globalSelector.value = deckState.scope;
        }

        const exportSelector = document.getElementById('pres-export-global-scope');
        if (exportSelector) {
          exportSelector.innerHTML = options.map(option => `
            <option value="${escapeHtml(option.key === 'area6' ? 'area6' : option.label)}">${escapeHtml(option.label)}</option>
          `).join('');
          exportSelector.value = deckState.scope === 'area6' ? 'area6' : activeScopeLabel(data);
        }
      };

      const populateCover = (data) => {
        updateDeckScopeLabels(data);
        document.getElementById('pres-cover-period').textContent = data?.meta?.period_label || '-';
        document.getElementById('pres-cover-loan-period').textContent = data?.meta?.daily_loan_period_label || data?.meta?.loan_period_label || '-';

        const ktsTotal = deckState.scope === 'area6'
          ? Number(data?.kts?.ritel_total || 0) + Number(data?.kts?.micro_total || 0)
          : ['membaik', 'memburuk'].reduce((categoryTotal, category) => {
              return categoryTotal + ['ritel', 'micro'].reduce(
                (scopeTotal, scope) => scopeTotal + Number(scopedKtsPayload(category, scope)?.total_count || 0),
                0
              );
            }, 0);
        const coverKts = document.getElementById('pres-cover-kts');
        coverKts.textContent = data?.kts?.loading_details ? 'Memuat...' : new Intl.NumberFormat('id-ID').format(ktsTotal) + ' rek';
        if (!data?.kts?.loading_details) {
          coverKts.setAttribute('data-raw-val', ktsTotal);
          coverKts.setAttribute('data-suffix', ' rek');
        }

        const digCards = data?.digital_strategy?.cards || [];
        const digCountVal = digCards.filter(card => card.available !== false).length;
        const coverDigital = document.getElementById('pres-cover-digital-count');
        coverDigital.textContent = digCountVal + ' strategi';
        coverDigital.setAttribute('data-raw-val', digCountVal);
        coverDigital.setAttribute('data-suffix', ' strategi');

        const board = document.getElementById('pres-cover-board');
        if (!board) return;
        board.innerHTML = '';

        const summaryCards = activeSummary(data)?.cards || [];
        const cardMap = Object.fromEntries(summaryCards.map(card => [card.key, card]));
        const branches = scopedBranchRows(data);
        const topSimpanan = branches.slice().sort((a, b) => Number(b.simpanan || 0) - Number(a.simpanan || 0))[0];
        const topOs = branches.slice().sort((a, b) => Number(b.pinjaman || 0) - Number(a.pinjaman || 0))[0];
        const coverSeriesCards = data?.cover_card_timeseries?.cards || {};
        const coverCards = [
          { seriesKey: 'simpanan', label: 'Simpanan', value: cardMap.simpanan?.value || '-', meta: `${cardMap.simpanan?.trend || '0,00%'} MtM`, iconClass: 'fas fa-wallet', iconColor: '#059669', iconBg: 'rgba(5, 150, 105, 0.1)' },
          { seriesKey: 'os', label: 'OS', value: cardMap.os?.value || '-', meta: `${cardMap.os?.trend || '0,00%'} MtM`, iconClass: 'fas fa-coins', iconColor: '#2563eb', iconBg: 'rgba(37, 99, 235, 0.1)' },
          { seriesKey: 'ldr', label: 'LDR', value: cardMap.ldr?.value || '-', meta: cardMap.ldr?.meta || '-', iconClass: 'fas fa-balance-scale', iconColor: '#7c3aed', iconBg: 'rgba(124, 58, 237, 0.1)' },
          { seriesKey: 'sml', label: 'SML', value: cardMap.sml?.value || '-', meta: cardMap.sml?.ratio || '-', iconClass: 'fas fa-exclamation-triangle', iconColor: '#ea580c', iconBg: 'rgba(234, 88, 12, 0.1)' },
          { seriesKey: 'npl', label: 'NPL', value: cardMap.npl?.value || '-', meta: cardMap.npl?.ratio || '-', iconClass: 'fas fa-ban', iconColor: '#dc2626', iconBg: 'rgba(220, 38, 38, 0.1)' },
        ];

        coverCards.forEach((card, index) => {
          const div = document.createElement('div');
          div.className = 'pres-glass-card pres-cover-card';
          if (index < 3) {
            div.classList.add('card-span-2');
          } else {
            div.classList.add('card-span-3');
          }
          div.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
              <div>
                <div class="label">${escapeHtml(card.label)}</div>
                <div class="value">${escapeHtml(card.value)}</div>
              </div>
              <div style="color: ${card.iconColor}; background: ${card.iconBg}; width: 2.2rem; height: 2.2rem; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); transition: transform 0.2s ease-in-out;" class="pres-card-icon"><i class="${card.iconClass}"></i></div>
            </div>
            ${renderCoverMiniSeries(coverSeriesCards[card.seriesKey])}
            <div class="meta">${escapeHtml(card.meta)}</div>
          `;
          board.appendChild(div);
        });
      };

      const populatePerformanceControls = (data) => {
        const select = document.getElementById('pres-scope-select');
        const options = data?.performance_overview?.matrix?.scope_options || [];
        if (!select || !options.length) return;

        select.innerHTML = options.map(option => `
          <option value="${escapeHtml(option.key)}">${escapeHtml(option.label)}</option>
        `).join('');
        select.value = performanceState.scope;
      };

      const getPerformanceRows = () => {
        const matrix = presentationData?.performance_overview?.matrix || {};
        const rows = matrix?.rows?.[performanceState.scope] || [];
        return rows.slice().sort((a, b) => {
          const metric = performanceState.metric;
          return Number(b?.metrics?.[metric]?.latest_raw || 0) - Number(a?.metrics?.[metric]?.latest_raw || 0);
        });
      };

      const performancePointLabelPlugin = {
        id: 'performancePointLabels',
        afterDatasetsDraw(chart, _args, options) {
          if (options?.display === false) return;

          const dataset = chart.data.datasets?.[0];
          const points = chart.getDatasetMeta(0)?.data || [];
          if (!dataset || !points.length) return;

          const ctx = chart.ctx;
          const chartArea = chart.chartArea;
          const tone = options?.tone || dataset.borderColor || '#0857c3';
          const formatter = typeof options?.formatter === 'function'
            ? options.formatter
            : value => String(value ?? '-');

          const roundedRect = (x, y, width, height, radius) => {
            const right = x + width;
            const bottom = y + height;
            ctx.beginPath();
            ctx.moveTo(x + radius, y);
            ctx.lineTo(right - radius, y);
            ctx.quadraticCurveTo(right, y, right, y + radius);
            ctx.lineTo(right, bottom - radius);
            ctx.quadraticCurveTo(right, bottom, right - radius, bottom);
            ctx.lineTo(x + radius, bottom);
            ctx.quadraticCurveTo(x, bottom, x, bottom - radius);
            ctx.lineTo(x, y + radius);
            ctx.quadraticCurveTo(x, y, x + radius, y);
            ctx.closePath();
          };

          ctx.save();
          ctx.font = '800 9px Inter, Arial, sans-serif';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';

          points.forEach((point, index) => {
            const value = Number(dataset.data?.[index]);
            if (!Number.isFinite(value)) return;

            const label = formatter(value, index);
            const width = Math.min(82, Math.max(48, ctx.measureText(label).width + 12));
            const height = 19;
            const x = Math.max(chartArea.left, Math.min(point.x - (width / 2), chartArea.right - width));
            const placeBelow = point.y - 28 < chartArea.top;
            const y = placeBelow ? point.y + 12 : point.y - 27;
            const connectorY = placeBelow ? y : y + height;

            ctx.strokeStyle = tone;
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(point.x, point.y + (placeBelow ? 4 : -4));
            ctx.lineTo(point.x, connectorY);
            ctx.stroke();

            roundedRect(x, y, width, height, 5);
            ctx.fillStyle = 'rgba(255,255,255,0.97)';
            ctx.fill();
            ctx.strokeStyle = tone;
            ctx.lineWidth = 1;
            ctx.stroke();
            ctx.fillStyle = '#0f172a';
            ctx.fillText(label, x + (width / 2), y + (height / 2) + 0.5, width - 8);
          });

          ctx.restore();
        }
      };

      const renderPerformanceExplorer = () => {
        const matrix = presentationData?.performance_overview?.matrix || {};
        const rows = getPerformanceRows();
        const metric = performanceState.metric;
        const scopeOption = (matrix.scope_options || []).find(option => option.key === performanceState.scope);
        const metricInfo = matrix.metrics?.[metric] || { label: metricLabel(metric) };
        const tone = metricTone(metric);
        const totals = rows.reduce((acc, row) => {
          const item = row.metrics?.[metric] || {};
          acc.latest += Number(item.latest_raw || 0);
          acc.ytd += Number(item.ytd_raw || 0);
          acc.mtm += Number(item.mtm_raw || 0);
          acc.mtd += Number(item.mtd_raw || 0);
          return acc;
        }, { latest: 0, ytd: 0, mtm: 0, mtd: 0 });

        const latestEl = document.getElementById('pres-explorer-latest');
        latestEl.textContent = formatCurrencyCompact(totals.latest);
        latestEl.setAttribute('data-raw-val', totals.latest);
        latestEl.setAttribute('data-is-currency', 'true');

        const countEl = document.getElementById('pres-explorer-count');
        const suffix = performanceState.scope === 'area6' ? ' cabang' : ' unit';
        countEl.textContent = rows.length + suffix;
        countEl.setAttribute('data-raw-val', rows.length);
        countEl.setAttribute('data-suffix', suffix);

        document.getElementById('pres-explorer-ytd').textContent = (totals.ytd >= 0 ? '+' : '-') + formatCurrencyCompact(Math.abs(totals.ytd));
        document.getElementById('pres-explorer-mtm-mtd').textContent = `${totals.mtm >= 0 ? '+' : '-'}${formatCurrencyCompact(Math.abs(totals.mtm))} / ${totals.mtd >= 0 ? '+' : '-'}${formatCurrencyCompact(Math.abs(totals.mtd))}`;
        document.getElementById('pres-explorer-caption').textContent = scopeOption?.label || 'Area 6 Konsol';
        document.getElementById('pres-explorer-title').textContent = metricInfo.label || metricLabel(metric);

        const periods = matrix.periods || {};
        document.getElementById('pres-explorer-periods').textContent = ['ytd', 'mtm', 'mtd', 'current']
          .map(key => periods[key] ? `${periods[key].label} ${periods[key].display}` : null)
          .filter(Boolean)
          .join(' | ');

        const thead = document.getElementById('pres-explorer-thead');
        const tbody = document.getElementById('pres-explorer-tbody');

        if (metric === 'simpanan' || metric === 'os') {
          thead.innerHTML = `
            <tr>
              <th style="border-top-left-radius: 6px; border-bottom-left-radius: 6px;">Unit/Cabang</th>
              <th style="text-align:right;">Terbaru</th>
              <th style="text-align:right;">YtD</th>
              <th style="text-align:right;">MtM</th>
              <th style="text-align:right;">MtD</th>
              <th style="text-align:right;">RKA</th>
              <th style="text-align:right;">Gap RKA</th>
              <th style="text-align:right; border-top-right-radius: 6px; border-bottom-right-radius: 6px;">Penc. RKA</th>
            </tr>
          `;

          const maxLatest = Math.max(...rows.map(row => Number(row.metrics?.[metric]?.latest_raw || 0)), 0);
          tbody.innerHTML = rows.map(row => {
            const item = row.metrics?.[metric] || {};
            const pencColor = colorForAchievement(item.penc_fmt);
            const barWidth = maxLatest > 0 ? Math.max(5, Math.min(100, (Number(item.latest_raw || 0) / maxLatest) * 100)) : 0;
            return `
              <tr>
                <td style="font-weight:800;">${escapeHtml(row.label || '-')}</td>
                <td style="text-align:right; font-weight:850; color:${tone};">
                  <div class="pres-value-bar">
                    <strong>${escapeHtml(item.latest || '-')}</strong>
                    <span class="pres-value-bar-track"><span class="pres-value-bar-fill" style="width:${barWidth}%; background:${tone};"></span></span>
                  </div>
                </td>
                <td style="text-align:right;"><span class="pres-delta ${item.ytd?.class || ''}">${escapeHtml(item.ytd?.value || '-')}</span></td>
                <td style="text-align:right;"><span class="pres-delta ${item.mtm?.class || ''}">${escapeHtml(item.mtm?.value || '-')}</span></td>
                <td style="text-align:right;"><span class="pres-delta ${item.mtd?.class || ''}">${escapeHtml(item.mtd?.value || '-')}</span></td>
                <td style="text-align:right; font-weight:800; color:#475569;">${escapeHtml(item.rka_fmt || '-')}</td>
                <td style="text-align:right;"><span class="pres-delta ${item.gap_class || ''}">${escapeHtml(item.gap_fmt || '-')}</span></td>
                <td style="text-align:right; font-weight:850; color:${pencColor};">${escapeHtml(item.penc_fmt || '-')}</td>
              </tr>
            `;
          }).join('') || '<tr><td colspan="8" style="text-align:center; padding:2rem; color:#64748b;">Data belum tersedia</td></tr>';
        } else {
          thead.innerHTML = `
            <tr>
              <th style="border-top-left-radius: 6px; border-bottom-left-radius: 6px;">Unit/Cabang</th>
              <th style="text-align:right;">Terbaru</th>
              <th style="text-align:right;">YtD</th>
              <th style="text-align:right;">MtM</th>
              <th style="text-align:right;">MtD</th>
              <th style="text-align:right; border-top-right-radius: 6px; border-bottom-right-radius: 6px;">Rasio</th>
            </tr>
          `;

          const maxLatest = Math.max(...rows.map(row => Number(row.metrics?.[metric]?.latest_raw || 0)), 0);
          tbody.innerHTML = rows.map(row => {
            const item = row.metrics?.[metric] || {};
            const barWidth = maxLatest > 0 ? Math.max(5, Math.min(100, (Number(item.latest_raw || 0) / maxLatest) * 100)) : 0;
            return `
              <tr>
                <td style="font-weight:800;">${escapeHtml(row.label || '-')}</td>
                <td style="text-align:right; font-weight:850; color:${tone};">
                  <div class="pres-value-bar">
                    <strong>${escapeHtml(item.latest || '-')}</strong>
                    <span class="pres-value-bar-track"><span class="pres-value-bar-fill" style="width:${barWidth}%; background:${tone};"></span></span>
                  </div>
                </td>
                <td style="text-align:right;"><span class="pres-delta ${item.ytd?.class || ''}">${escapeHtml(item.ytd?.value || '-')}</span></td>
                <td style="text-align:right;"><span class="pres-delta ${item.mtm?.class || ''}">${escapeHtml(item.mtm?.value || '-')}</span></td>
                <td style="text-align:right;"><span class="pres-delta ${item.mtd?.class || ''}">${escapeHtml(item.mtd?.value || '-')}</span></td>
                <td style="text-align:right; font-weight:800;">${escapeHtml(item.ratio || '-')}</td>
              </tr>
            `;
          }).join('') || '<tr><td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">Data belum tersedia</td></tr>';
        }

        const explorerReadout = document.getElementById('pres-explorer-readout');
        if (explorerReadout) {
          const isQuality = metric === 'sml' || metric === 'npl';
          const topRow = rows[0];
          const movementRows = [...rows].sort((a, b) => {
            const aValue = Number(a.metrics?.[metric]?.mtd_raw || 0);
            const bValue = Number(b.metrics?.[metric]?.mtd_raw || 0);
            return isQuality ? aValue - bValue : bValue - aValue;
          });
          const bestMovement = movementRows[0];
          const totalRka = rows.reduce((sum, row) => sum + Number(row.metrics?.[metric]?.rka_raw || 0), 0);
          const totalGap = totals.latest - totalRka;
          const scopeOs = rows.reduce((sum, row) => sum + Number(row.metrics?.os?.latest_raw || 0), 0);
          const concentration = totals.latest !== 0 && topRow
            ? (Number(topRow.metrics?.[metric]?.latest_raw || 0) / totals.latest) * 100
            : 0;
          const bestMtd = Number(bestMovement?.metrics?.[metric]?.mtd_raw || 0);
          explorerReadout.innerHTML = `
            <div class="pres-readout-item"><span>Kontributor terbesar</span><strong>${escapeHtml(topRow?.label || '-')}</strong><small>${formatPercent(concentration)} dari posisi scope terpilih.</small></div>
            <div class="pres-readout-item"><span>${isQuality ? 'Perbaikan kualitas' : 'Akselerasi MtD'}</span><strong>${escapeHtml(bestMovement?.label || '-')}</strong><small>${bestMtd >= 0 ? '+' : '-'}${escapeHtml(formatCurrencyCompact(Math.abs(bestMtd)))} MtD.</small></div>
            <div class="pres-readout-item"><span>${isQuality ? 'Rasio scope' : 'Gap terhadap RKA'}</span><strong>${isQuality ? escapeHtml(formatPercent(totals.latest > 0 ? (totals.latest / Math.max(1, scopeOs)) * 100 : 0)) : escapeHtml(signedCurrency(totalGap))}</strong><small>${isQuality ? 'Nominal kualitas dibandingkan total OS pada scope terpilih.' : (totalGap >= 0 ? 'Realisasi berada di atas RKA.' : 'Masih memerlukan akselerasi untuk menutup gap.')}</small></div>
          `;
        }

        const periodKeys = ['ytd', 'mtm', 'mtd', 'current'];
        const periodFallbacks = ['YtD', 'MtM', 'MtD', 'Posisi'];
        const periodPoints = periodKeys.map((key, index) => ({
          key,
          label: periods[key]?.label || periodFallbacks[index],
          display: periods[key]?.display || '-'
        }));
        const labels = periodPoints.map(point => [point.label, point.display]);
        const totalSeries = periodKeys.map((_, idx) => rows.reduce((sum, row) => sum + Number(row.metrics?.[metric]?.series?.[idx] || 0), 0));
        const firstValue = Number(totalSeries[0] || 0);
        const latestValue = Number(totalSeries[totalSeries.length - 1] || 0);
        const previousValue = Number(totalSeries[totalSeries.length - 2] || 0);
        const netMovement = latestValue - firstValue;
        const latestMovement = latestValue - previousValue;
        const isQualityMetric = metric === 'sml' || metric === 'npl';
        const netIsGood = isQualityMetric ? netMovement <= 0 : netMovement >= 0;
        const latestIsGood = isQualityMetric ? latestMovement <= 0 : latestMovement >= 0;
        const peakValue = Math.max(...totalSeries);
        const peakIndex = totalSeries.indexOf(peakValue);
        const trendVerb = netMovement === 0 ? 'stabil' : (netMovement > 0 ? 'meningkat' : 'menurun');
        const trendMeaning = isQualityMetric
          ? (netIsGood ? 'Penurunan nominal menunjukkan perbaikan kualitas portofolio.' : 'Kenaikan nominal menunjukkan tekanan kualitas yang perlu ditangani.')
          : (netIsGood ? 'Arah tersebut memperkuat posisi bisnis pada scope terpilih.' : 'Arah tersebut memerlukan akselerasi untuk memulihkan posisi.');
        const trendAnalysis = `${metricInfo.label || metricLabel(metric)} ${scopeOption?.label || 'Area 6 Konsol'} ${trendVerb} ${signedCurrency(netMovement * 1000000)} dari ${periodPoints[0].display} ke ${periodPoints.at(-1).display}. ${trendMeaning}`;
        const trendAction = isQualityMetric
          ? (latestIsGood
            ? `Pertahankan curing dan pencegahan roll rate agar perbaikan ${metricInfo.label || metricLabel(metric)} pada posisi terakhir tetap berlanjut.`
            : `Prioritaskan unit penyumbang kenaikan ${metricInfo.label || metricLabel(metric)} dan percepat tindak lanjut rekening dengan eksposur terbesar.`)
          : (latestIsGood
            ? 'Pertahankan momentum posisi terakhir dan arahkan akselerasi tambahan pada unit yang masih memiliki gap terhadap RKA.'
            : 'Fokuskan recovery posisi pada unit dengan kontraksi terdalam dan susun akselerasi sebelum periode berikutnya.');

        const chartCaption = document.getElementById('pres-explorer-chart-caption');
        const chartWindow = document.getElementById('pres-explorer-chart-window');
        const trendAnalysisEl = document.getElementById('pres-explorer-trend-analysis');
        const trendActionEl = document.getElementById('pres-explorer-trend-action');
        const trendFacts = document.getElementById('pres-explorer-trend-facts');
        const trendCard = document.querySelector('#pres-slide-9 .pres-explorer-trend-analysis');
        if (chartCaption) chartCaption.textContent = `${scopeOption?.label || 'Area 6 Konsol'} - ${metricInfo.label || metricLabel(metric)}`;
        if (chartWindow) chartWindow.textContent = `${periodPoints[0].display} - ${periodPoints.at(-1).display}`;
        if (trendAnalysisEl) trendAnalysisEl.textContent = trendAnalysis;
        if (trendActionEl) trendActionEl.textContent = trendAction;
        if (trendCard) trendCard.style.setProperty('--trend-tone', tone);
        if (trendFacts) {
          trendFacts.innerHTML = `
            <div class="pres-explorer-trend-fact ${netIsGood ? 'is-good' : 'is-risk'}">
              <span>Perubahan total</span>
              <strong>${escapeHtml(signedCurrency(netMovement * 1000000))}</strong>
              <small>${escapeHtml(periodPoints[0].display)} ke ${escapeHtml(periodPoints.at(-1).display)}</small>
            </div>
            <div class="pres-explorer-trend-fact ${latestIsGood ? 'is-good' : 'is-risk'}">
              <span>Momentum terakhir</span>
              <strong>${escapeHtml(signedCurrency(latestMovement * 1000000))}</strong>
              <small>dibanding ${escapeHtml(periodPoints.at(-2).display)}</small>
            </div>
            <div class="pres-explorer-trend-fact ${isQualityMetric ? 'is-risk' : ''}">
              <span>Posisi tertinggi</span>
              <strong>${escapeHtml(formatCurrencyCompact(peakValue * 1000000))}</strong>
              <small>${escapeHtml(periodPoints[peakIndex]?.display || '-')}</small>
            </div>
          `;
        }

        const lineCanvas = document.getElementById('pres-explorer-chart');
        if (lineCanvas) {
          if (performanceChart) performanceChart.destroy();
          performanceChart = new Chart(lineCanvas.getContext('2d'), {
            type: 'line',
            data: {
              labels,
              datasets: [{
                label: metricInfo.label || metricLabel(metric),
                data: totalSeries,
                borderColor: tone,
                backgroundColor: tone + '22',
                fill: true,
                tension: 0.28,
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: tone,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2
              }]
            },
            plugins: [performancePointLabelPlugin],
            options: {
              animation: false,
              responsive: true,
              maintainAspectRatio: false,
              layout: { padding: { left: 4, right: 8, top: 30, bottom: 2 } },
              interaction: { intersect: false, mode: 'index' },
              plugins: {
                legend: { display: false },
                performancePointLabels: {
                  display: true,
                  tone,
                  formatter: value => formatCurrencyCompact(Number(value || 0) * 1000000)
                },
                tooltip: {
                  displayColors: false,
                  callbacks: {
                    title: items => {
                      const label = items?.[0]?.label;
                      return Array.isArray(label) ? label.join(' - ') : String(label || '-');
                    },
                    label: context => `${metricInfo.label || metricLabel(metric)}: ${formatCurrencyCompact(Number(context.raw || 0) * 1000000)}`
                  }
                }
              },
              scales: {
                x: {
                  grid: { display: false },
                  ticks: {
                    autoSkip: false,
                    maxRotation: 0,
                    color: '#64748b',
                    font: { family: 'Inter', size: 9, weight: '700' }
                  }
                },
                y: {
                  grid: { color: 'rgba(15,23,42,0.07)' },
                  ticks: {
                    maxTicksLimit: 5,
                    color: '#64748b',
                    font: { family: 'Inter', size: 8, weight: '700' },
                    callback: value => formatCurrencyCompact(Number(value || 0) * 1000000)
                  }
                }
              }
            }
          });
        }

        const tableWrap = document.getElementById('pres-explorer-table-wrap');
        const barWrap = document.getElementById('pres-explorer-bar-wrap');
        tableWrap.classList.toggle('hidden', performanceState.view !== 'table');
        barWrap.classList.toggle('hidden', performanceState.view !== 'chart');

        if (performanceState.view === 'chart') {
          const barCanvas = document.getElementById('pres-explorer-bar-chart');
          if (performanceBarChart) performanceBarChart.destroy();
          performanceBarChart = new Chart(barCanvas.getContext('2d'), {
            type: 'bar',
            data: {
              labels: rows.slice(0, 12).map(row => row.label),
              datasets: [{
                label: metricInfo.label || metricLabel(metric),
                data: rows.slice(0, 12).map(row => Math.round(Number(row.metrics?.[metric]?.latest_raw || 0) / 1000000)),
                backgroundColor: tone,
                borderRadius: 6
              }]
            },
            options: {
              animation: false,
              indexAxis: 'y',
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: false } },
              scales: {
                x: { grid: { color: 'rgba(15,23,42,0.07)' }, ticks: { color: '#64748b', callback: value => 'Rp ' + new Intl.NumberFormat('id-ID').format(value / 1000) + ' M' } },
                y: { grid: { display: false }, ticks: { color: '#0f172a', font: { family: 'Inter', size: 10, weight: '700' } } }
              }
            }
          });
        }
        renderSlideNarratives();
      };

      const populateSegmentControls = (data) => {
        const select = document.getElementById('pres-seg-scope-select');
        const options = data?.performance_overview?.matrix?.scope_options || [];
        if (!select || !options.length) return;

        select.innerHTML = options.map(option => `
          <option value="${escapeHtml(option.key)}">${escapeHtml(option.label)}</option>
        `).join('');
        select.value = segmentState.scope;
      };

      const isRetailOutletLabel = (label) => /^(KC|KCP)\b/i.test(String(label || '').trim());
      const isMicroOutletLabel = (label) => /^UNIT\b/i.test(String(label || '').trim());
      const isRetailSegmentMetric = (metric) => metric === 'sme_os' || metric === 'consumer_os';
      const segmentMetricLabel = (metric) => ({
        sme_os: 'OS SME',
        micro_os: 'OS Mikro',
        consumer_os: 'OS Konsumer'
      }[metric] || 'OS Segmen');

      const segmentOutletLabel = (metric) => isRetailSegmentMetric(metric) ? 'KC/KCP' : 'Unit Kerja';
      const segmentFootnote = (metric) => isRetailSegmentMetric(metric)
        ? 'SME dan Konsumer hanya dihitung pada outlet ritel KC/KCP; unit mikro tidak ditampilkan.'
        : 'Mikro ditampilkan pada outlet Unit; KC/KCP hanya dipakai sebagai ringkasan saat scope Area 6.';

      const hasSegmentMetricValue = (row, metric) => {
        const item = row?.metrics?.[metric] || {};
        return ['latest_raw', 'rka_raw', 'ytd_raw', 'mtm_raw', 'mtd_raw']
          .some(key => Math.abs(Number(item?.[key] || 0)) > 0);
      };

      const rowMatchesSegmentMetric = (row, metric, scope) => {
        const label = row?.label || '';
        if (isRetailSegmentMetric(metric)) {
          return row?.type === 'Cabang Konsol' || isRetailOutletLabel(label);
        }

        if (metric === 'micro_os') {
          return scope === 'area6' ? row?.type === 'Cabang Konsol' : isMicroOutletLabel(label);
        }

        return true;
      };

      const getSegmentRows = () => {
        const matrix = presentationData?.performance_overview?.matrix || {};
        const metric = segmentState.metric;
        const rows = matrix?.rows?.[segmentState.scope] || [];
        let filteredRows = rows
          .filter(row => rowMatchesSegmentMetric(row, metric, segmentState.scope))
          .filter(row => hasSegmentMetricValue(row, metric));

        if (!filteredRows.length && isRetailSegmentMetric(metric) && segmentState.scope !== 'area6') {
          const selectedScope = String(segmentState.scope || '').trim().toUpperCase();
          filteredRows = (matrix?.rows?.area6 || [])
            .filter(row => String(row?.label || row?.branch || '').trim().toUpperCase() === selectedScope)
            .filter(row => hasSegmentMetricValue(row, metric));
        }

        return filteredRows.slice().sort((a, b) => {
          return Number(b?.metrics?.[metric]?.latest_raw || 0) - Number(a?.metrics?.[metric]?.latest_raw || 0);
        });
      };

      const renderSegmentExplorer = () => {
        const matrix = presentationData?.performance_overview?.matrix || {};
        const rows = getSegmentRows();
        const metric = segmentState.metric;
        const scopeOption = (matrix.scope_options || []).find(option => option.key === segmentState.scope);
        const tone = '#1155c8';

        const titleEl = document.getElementById('pres-seg-explorer-title');
        if (titleEl) titleEl.textContent = segmentMetricLabel(metric);
        document.getElementById('pres-seg-explorer-caption').textContent = scopeOption?.label || 'Area 6 Konsol';
        const firstColLabel = document.getElementById('pres-seg-first-col-label');
        if (firstColLabel) firstColLabel.textContent = segmentOutletLabel(metric);
        const footnote = document.getElementById('pres-seg-footnote');
        if (footnote) {
          footnote.innerHTML = `<i class="fas fa-info-circle"></i><span>${escapeHtml(segmentFootnote(metric))}</span>`;
        }

        const periods = matrix.periods || {};
        document.getElementById('pres-seg-explorer-periods').textContent = ['ytd', 'mtm', 'mtd', 'current']
          .map(key => periods[key] ? `${periods[key].label} ${periods[key].display}` : null)
          .filter(Boolean)
          .join(' | ');

        const totals = rows.reduce((acc, row) => {
          const item = row.metrics?.[metric] || {};
          acc.latest += Number(item.latest_raw || 0);
          acc.rka += Number(item.rka_raw || 0);
          acc.gap += Number(item.gap_raw || 0);
          return acc;
        }, { latest: 0, rka: 0, gap: 0 });
        const achievement = totals.rka > 0 ? (totals.latest / totals.rka) * 100 : null;
        const achievementText = achievement === null ? '-' : formatPercent(achievement);
        const achievementColor = colorForAchievement(achievementText);
        const latestEl = document.getElementById('pres-seg-total-latest');
        const rkaEl = document.getElementById('pres-seg-total-rka');
        const achEl = document.getElementById('pres-seg-total-ach');
        const outletEl = document.getElementById('pres-seg-total-outlet');
        if (latestEl) latestEl.textContent = formatCurrencyCompact(totals.latest);
        if (rkaEl) rkaEl.textContent = totals.rka > 0 ? formatCurrencyCompact(totals.rka) : '-';
        if (achEl) {
          achEl.textContent = achievementText;
          achEl.style.color = achievementColor;
        }
        if (outletEl) outletEl.textContent = rows.length + (isRetailSegmentMetric(metric) ? ' outlet' : (segmentState.scope === 'area6' ? ' cabang' : ' unit'));

        const tbody = document.getElementById('pres-seg-explorer-tbody');
        if (!tbody) return;

        const maxSegmentLatest = Math.max(...rows.map(row => Number(row.metrics?.[metric]?.latest_raw || 0)), 0);
        tbody.innerHTML = rows.map(row => {
          const item = row.metrics?.[metric] || {};
          const pencColor = colorForAchievement(item.penc_fmt);
          const mutedClass = Number(item.latest_raw || 0) === 0 && Number(item.rka_raw || 0) === 0 ? 'pres-seg-row-muted' : '';
          const metaLabel = row.branch && row.branch !== row.label ? `<div style="margin-top:0.12rem; color:#64748b; font-size:0.64rem; font-weight:750;">${escapeHtml(row.branch)}</div>` : '';
          const barWidth = maxSegmentLatest > 0 ? Math.max(5, Math.min(100, (Number(item.latest_raw || 0) / maxSegmentLatest) * 100)) : 0;
          return `
            <tr class="${mutedClass}">
              <td style="font-weight:800; padding: 0.48rem 0.55rem; color:#1d1d1f;">
                <div style="line-height:1.12;">${escapeHtml(row.label || '-')}</div>
                ${metaLabel}
              </td>
              <td style="text-align:right; padding: 0.48rem 0.55rem; font-weight:850; color:${tone};">
                <div class="pres-value-bar">
                  <strong>${escapeHtml(item.latest || '-')}</strong>
                  <span class="pres-value-bar-track"><span class="pres-value-bar-fill" style="width:${barWidth}%; background:${tone};"></span></span>
                </div>
              </td>
              <td style="text-align:right; padding: 0.45rem 0.55rem;"><span class="pres-delta ${item.ytd?.class || ''}">${escapeHtml(item.ytd?.value || '-')}</span></td>
              <td style="text-align:right; padding: 0.45rem 0.55rem;"><span class="pres-delta ${item.mtm?.class || ''}">${escapeHtml(item.mtm?.value || '-')}</span></td>
              <td style="text-align:right; padding: 0.45rem 0.55rem;"><span class="pres-delta ${item.mtd?.class || ''}">${escapeHtml(item.mtd?.value || '-')}</span></td>
              <td style="text-align:right; padding: 0.45rem 0.55rem; font-weight:800; color:#475569;">${escapeHtml(item.rka_fmt || '-')}</td>
              <td style="text-align:right; padding: 0.45rem 0.55rem;"><span class="pres-delta ${item.gap_class || ''}">${escapeHtml(item.gap_fmt || '-')}</span></td>
              <td style="text-align:right; padding: 0.45rem 0.55rem; font-weight:850; color:${pencColor};">${escapeHtml(item.penc_fmt || '-')}</td>
            </tr>
          `;
        }).join('') || '<tr><td colspan="8" style="text-align:center; padding:2rem; color:#64748b;">Data belum tersedia</td></tr>';

        const segmentInsights = document.getElementById('pres-segment-insights');
        if (segmentInsights) {
          const topRow = rows[0];
          const fastestRow = [...rows].sort((a, b) => Number(b.metrics?.[metric]?.mtd_raw || 0) - Number(a.metrics?.[metric]?.mtd_raw || 0))[0];
          const gapRow = [...rows].sort((a, b) => Number(a.metrics?.[metric]?.gap_raw || 0) - Number(b.metrics?.[metric]?.gap_raw || 0))[0];
          const topContribution = totals.latest > 0 && topRow ? (Number(topRow.metrics?.[metric]?.latest_raw || 0) / totals.latest) * 100 : 0;
          const fastestMtd = Number(fastestRow?.metrics?.[metric]?.mtd_raw || 0);
          const largestGap = Number(gapRow?.metrics?.[metric]?.gap_raw || 0);
          segmentInsights.innerHTML = rows.length ? `
            <div class="pres-segment-insight"><span>Kontributor utama</span><strong>${escapeHtml(topRow?.label || '-')}</strong><small>${formatPercent(topContribution)} dari OS segmen pada scope ini.</small></div>
            <div class="pres-segment-insight"><span>Momentum MtD</span><strong>${escapeHtml(fastestRow?.label || '-')}</strong><small>${fastestMtd >= 0 ? '+' : '-'}${escapeHtml(formatCurrencyCompact(Math.abs(fastestMtd)))}.</small></div>
            <div class="pres-segment-insight"><span>Gap RKA terbesar</span><strong>${escapeHtml(gapRow?.label || '-')}</strong><small>${escapeHtml(signedCurrency(largestGap))} terhadap target.</small></div>
          ` : '<div class="pres-segment-insight" style="grid-column:1 / -1;"><span>Data scope</span><strong>Belum tersedia</strong><small>Tidak ada outlet yang memenuhi aturan segmen untuk pilihan ini.</small></div>';
        }
        renderSlideNarratives();
      };

      const populateRiskControls = (data) => {
        const select = document.getElementById('pres-risk-scope-select');
        if (!select) return;

        const branches = data?.performance_overview?.branches || [];
        const options = [
          { key: 'area6', label: 'Area 6 Konsol' },
          ...branches.map(b => ({ key: b.name.toUpperCase(), label: b.name }))
        ];

        select.innerHTML = options.map(option => `
          <option value="${escapeHtml(option.key)}">${escapeHtml(option.label)}</option>
        `).join('');
        select.value = riskState.scope;
      };

      const renderRiskOverview = () => {
        if (!presentationData) return;
        const branches = presentationData?.performance_overview?.branches || [];
        const composition = presentationData?.performance_overview?.composition || {};

        let healthyPct = 0;
        let restrukPct = 0;
        let smlPct = 0;
        let nplPct = 0;
        let larPct = 0;
        let subtitle = 'Struktur SML dan NPL Area 6';

        if (riskState.scope === 'area6') {
          larPct = composition.os ? (composition.os.raw_pct || 0) : 0;
          smlPct = composition.sml ? (composition.sml.raw_pct || 0) : 0;
          nplPct = composition.npl ? (composition.npl.raw_pct || 0) : 0;
          const explicitRestruk = Number(composition.restruk?.raw_pct);
          restrukPct = Number.isFinite(explicitRestruk)
            ? explicitRestruk
            : Math.max(0, larPct - smlPct - nplPct);
          healthyPct = Math.max(0, 100 - larPct);
          subtitle = 'Struktur SML dan NPL Area 6';
        } else {
          const branch = branches.find(b => b.name.toUpperCase() === riskState.scope);
          if (branch) {
            larPct = branch.lar_pct || 0;
            smlPct = branch.sml_pct || 0;
            nplPct = branch.npl_pct || 0;
            const explicitRestruk = Number(branch.restruk_pct);
            restrukPct = Number.isFinite(explicitRestruk)
              ? explicitRestruk
              : Math.max(0, larPct - smlPct - nplPct);
            healthyPct = Math.max(0, 100 - larPct);
            subtitle = `Struktur SML dan NPL ${branch.name}`;
          }
        }

        document.getElementById('pres-risk-subtitle').textContent = subtitle;

        const healthyValEl = document.getElementById('pres-lar-healthy-val');
        healthyValEl.textContent = `${healthyPct.toFixed(2).replace('.', ',')}%`;
        healthyValEl.setAttribute('data-raw-val', healthyPct);
        healthyValEl.setAttribute('data-is-percent', 'true');

        const restrukValEl = document.getElementById('pres-lar-restruk-val');
        restrukValEl.textContent = `${restrukPct.toFixed(2).replace('.', ',')}%`;
        restrukValEl.setAttribute('data-raw-val', restrukPct);
        restrukValEl.setAttribute('data-is-percent', 'true');

        const smlValEl = document.getElementById('pres-lar-sml-val');
        smlValEl.textContent = `${smlPct.toFixed(2).replace('.', ',')}%`;
        smlValEl.setAttribute('data-raw-val', smlPct);
        smlValEl.setAttribute('data-is-percent', 'true');

        const nplValEl = document.getElementById('pres-lar-npl-val');
        nplValEl.textContent = `${nplPct.toFixed(2).replace('.', ',')}%`;
        nplValEl.setAttribute('data-raw-val', nplPct);
        nplValEl.setAttribute('data-is-percent', 'true');

        const ratioValEl = document.getElementById('pres-lar-ratio-val');
        ratioValEl.textContent = `${larPct.toFixed(2).replace('.', ',')}%`;
        ratioValEl.setAttribute('data-raw-val', larPct);
        ratioValEl.setAttribute('data-is-percent', 'true');

        const healthyEl = document.getElementById('pres-spectrum-healthy');
        const restrukEl = document.getElementById('pres-spectrum-restruk');
        const smlEl = document.getElementById('pres-spectrum-sml');
        const nplEl = document.getElementById('pres-spectrum-npl');

        if (healthyEl) healthyEl.style.width = healthyPct + '%';
        if (restrukEl) restrukEl.style.width = restrukPct + '%';
        if (smlEl) smlEl.style.width = smlPct + '%';
        if (nplEl) nplEl.style.width = nplPct + '%';

        const donutCenter = document.getElementById('pres-risk-donut-center');
        if (donutCenter) {
          donutCenter.textContent = `${larPct.toFixed(2).replace('.', ',')}%`;
          donutCenter.setAttribute('data-raw-val', larPct);
          donutCenter.setAttribute('data-is-percent', 'true');
        }

        const riskCanvas = document.getElementById('pres-risk-composition-chart');
        if (riskCanvas) {
          if (riskCompositionChart) riskCompositionChart.destroy();

          riskCompositionChart = new Chart(riskCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
              labels: ['Lancar', 'Lancar Restruk', 'SML', 'NPL'],
              datasets: [{
                data: [healthyPct, restrukPct, smlPct, nplPct],
                backgroundColor: ['#10b981', '#1155c8', '#f59e0b', '#ef4444'],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 4
              }]
            },
            options: {
              animation: false,
              responsive: true,
              maintainAspectRatio: true,
              cutout: '68%',
              plugins: {
                legend: { display: false },
                tooltip: {
                  backgroundColor: 'rgba(255, 255, 255, 0.96)',
                  titleColor: '#111827',
                  bodyColor: '#111827',
                  borderColor: 'rgba(207, 224, 244, 0.95)',
                  borderWidth: 1,
                  padding: 10,
                  callbacks: {
                    label: context => `${context.label}: ${formatPercent(context.parsed)}`
                  }
                }
              }
            }
          });
        }
        renderSlideNarratives();
      };

      const scopedKtsPayload = (category = ktsState.category, scope = ktsState.scope) => {
        const raw = presentationData?.kts?.categories?.[category]?.[scope] || {};
        const branches = Array.isArray(raw.branches) ? raw.branches : [];
        if (deckState.scope === 'area6') return raw;

        const scopeLabel = activeScopeLabel();
        const filtered = branches.filter(branch => String(branch?.branch_name || '').toUpperCase() === scopeLabel.toUpperCase());
        const totalCount = filtered.reduce((sum, branch) => sum + Number(branch.total_count || 0), 0);
        const totalOs = filtered.reduce((sum, branch) => sum + Number(branch.total_os || 0), 0);

        return {
          ...raw,
          branches: filtered,
          total_count: totalCount,
          total_os: totalOs,
          total_os_fmt: formatCurrencyCompact(totalOs),
        };
      };

      const renderKts = () => {
        renderSlideNarratives();
        const detailPanel = document.getElementById('pres-kts-detail-panel');
        const ktsInsights = document.getElementById('pres-kts-insights');
        if (presentationData?.kts?.loading_details) {
          detailPanel?.classList.remove('is-empty');
          document.getElementById('pres-kts-total-count').textContent = 'Memuat...';
          document.getElementById('pres-kts-total-os').textContent = '-';
          document.getElementById('pres-kts-period').textContent = presentationData?.kts?.period_label || '-';
          document.getElementById('pres-kts-caption').textContent = ktsState.category === 'membaik' ? 'KTS Membaik' : 'KTS Memburuk';
          document.getElementById('pres-kts-title').textContent = `${ktsState.scope === 'ritel' ? 'Ritel' : 'Micro'} - ${activeScopeLabel()}`;
          document.getElementById('pres-kts-note').textContent = 'Detail KTS sedang dimuat setelah deck presentasi tampil agar mode PPT tidak tertahan.';
          document.getElementById('pres-kts-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem; color:#64748b;">Memuat detail KTS...</td></tr>';
          if (ktsInsights) {
            ktsInsights.innerHTML = '<div class="pres-kts-insight is-wide"><span>Analisis filter</span><strong>Menunggu detail KTS</strong><small>Ringkasan cabang, pola kolektibilitas, dan eksposur terbesar akan muncul setelah payload siap.</small></div>';
          }
          return;
        }

        const payload = scopedKtsPayload();
        const branches = payload.branches || [];
        const countEl = document.getElementById('pres-kts-total-count');
        countEl.textContent = `${payload.total_count || 0} rek`;
        countEl.setAttribute('data-raw-val', payload.total_count || 0);
        countEl.setAttribute('data-suffix', ' rek');

        const osEl = document.getElementById('pres-kts-total-os');
        osEl.textContent = payload.total_os_fmt || formatCurrencyCompact(payload.total_os || 0);
        osEl.setAttribute('data-raw-val', payload.total_os || 0);
        osEl.setAttribute('data-is-currency', 'true');
        document.getElementById('pres-kts-period').textContent = presentationData?.kts?.period_label || '-';
        document.getElementById('pres-kts-caption').textContent = ktsState.category === 'membaik' ? 'KTS Membaik' : 'KTS Memburuk';
        document.getElementById('pres-kts-title').textContent = `${ktsState.scope === 'ritel' ? 'Ritel' : 'Micro'} - ${activeScopeLabel()}`;
        document.getElementById('pres-kts-note').textContent = ktsState.category === 'membaik'
          ? 'Aktual lebih baik dari kolektibilitas seharusnya berdasarkan umur tunggakan.'
          : 'Aktual lebih buruk dari kolektibilitas seharusnya berdasarkan umur tunggakan.';

        const tbody = document.getElementById('pres-kts-tbody');
        if (!branches || branches.length === 0) {
          detailPanel?.classList.add('is-empty');
          tbody.innerHTML = `
            <tr><td colspan="5" class="pres-kts-empty-cell">
              <div class="pres-empty-state">
                <i class="fas fa-filter"></i>
                <strong>Data KTS belum tersedia untuk filter ini</strong>
                <p>Pilih arah KTS atau scope lain. Panel tidak menyimpulkan nihil temuan ketika detail sumber belum tersedia.</p>
              </div>
            </td></tr>
          `;
          if (ktsInsights) {
            ktsInsights.innerHTML = '<div class="pres-kts-insight is-wide"><span>Coverage filter</span><strong>Tidak ada baris detail</strong><small>Filter aktif tidak memiliki cabang dengan rekening KTS pada payload periode ini.</small></div>';
          }
          return;
        }

        detailPanel?.classList.remove('is-empty');

        let html = '';
        branches.forEach(branch => {
          // Render Branch Header Row
          html += `
            <tr class="pres-kts-branch-header">
              <td colspan="5">
                <i class="fas fa-university mr-2" style="color:#0071e3;"></i>
                ${escapeHtml(branch.branch_name)}
                <span style="float:right; font-weight:600; font-size:0.75rem; color:#475569;">
                  ${branch.total_count || 0} debitur &bull; ${escapeHtml(branch.total_os_fmt || 'Rp0')}
                </span>
              </td>
            </tr>
          `;

          const debiturs = branch.debiturs || [];
          if (debiturs.length === 0) {
            html += `
              <tr>
                <td colspan="5" style="text-align:center; padding:0.65rem; color:#94a3b8; font-style:italic; background:rgba(248,250,252,0.4);">
                  <i class="fas fa-check-circle text-success mr-1"></i> Tidak ada KTS (0 debitur)
                </td>
              </tr>
            `;
          } else {
            debiturs.forEach(deb => {
              const badgeClass = ktsState.category === 'membaik' ? 'badge-membaik' : 'badge-memburuk';
              html += `
                <tr>
                  <td style="font-weight:850; color:#64748b; padding-left:1.25rem;">${deb.rank}</td>
                  <td>
                    <div style="font-weight:800; color:#0f172a;">${escapeHtml(deb.nama_debitur || '-')}</div>
                    <div style="font-size:0.75rem; color:#64748b; font-family:monospace; font-weight:500;">${escapeHtml(deb.nomor_rekening || '-')}</div>
                  </td>
                  <td>
                    <div style="font-weight:600; color:#475569;">${escapeHtml(deb.unit || '-')}</div>
                  </td>
                  <td style="text-align:center;">
                    <span class="pres-kts-badge ${badgeClass}">
                      Kol ${deb.kolek_aktual} <i class="fas fa-long-arrow-alt-right mx-1"></i> Kol ${deb.kolek_seharusnya}
                    </span>
                  </td>
                  <td style="text-align:right; font-weight:850; color:#0f172a;">${escapeHtml(deb.baki_debet_fmt || 'Rp0')}</td>
                </tr>
              `;
            });
          }
        });

        tbody.innerHTML = html;
        if (ktsInsights) {
          const activeBranches = branches.filter(branch => Number(branch.total_count || 0) > 0);
          const topBranch = [...activeBranches].sort((a, b) => Number(b.total_os || 0) - Number(a.total_os || 0))[0];
          const debtors = branches.flatMap(branch => (branch.debiturs || []).map(debtor => ({
            ...debtor,
            branch_name: branch.branch_name,
          })));
          const debtorExposureValue = debtor => {
            const raw = Number(debtor?.baki_debet_raw ?? debtor?.baki_debet);
            return Number.isFinite(raw) && raw !== 0
              ? raw
              : parseCompactNumber(debtor?.baki_debet_fmt || '0');
          };
          const topDebtor = [...debtors].sort((a, b) => debtorExposureValue(b) - debtorExposureValue(a))[0];
          const movementCounts = debtors.reduce((counts, debtor) => {
            const movement = `Kol ${debtor.kolek_aktual ?? '-'} \u2192 Kol ${debtor.kolek_seharusnya ?? '-'}`;
            counts.set(movement, (counts.get(movement) || 0) + 1);
            return counts;
          }, new Map());
          const dominantMovement = [...movementCounts.entries()].sort((a, b) => b[1] - a[1])[0];
          ktsInsights.innerHTML = `
            <div class="pres-kts-insight"><span>Cabang terdampak</span><strong>${activeBranches.length} dari ${branches.length}</strong><small>${escapeHtml(topBranch?.branch_name || '-')} memiliki OS terbesar ${escapeHtml(topBranch?.total_os_fmt || 'Rp0')}.</small></div>
            <div class="pres-kts-insight"><span>Eksposur rekening</span><strong>${escapeHtml(topDebtor?.baki_debet_fmt || '-')}</strong><small>${escapeHtml(topDebtor?.nama_debitur || '-')} pada ${escapeHtml(topDebtor?.branch_name || '-')}.</small></div>
            <div class="pres-kts-insight"><span>Pola dominan</span><strong>${escapeHtml(dominantMovement?.[0] || '-')}</strong><small>${new Intl.NumberFormat('id-ID').format(Number(dominantMovement?.[1] || 0))} rekening pada filter aktif.</small></div>
          `;
        }
      };

      const renderDigitalTableAndChart = () => {
        renderSlideNarratives();
        const cards = presentationData?.digital_strategy?.cards || [];
        const tbody = document.getElementById('pres-digital-tbody');
        tbody.innerHTML = cards.map(card => {
          const trend = card.trend || '-';
          const trendClass = String(trend).includes('-') && card.key !== 'casa' ? 'neg' : 'pos';
          return `
            <tr>
              <td style="font-weight:850;">${escapeHtml(card.title || '-')}</td>
              <td style="text-align:right; font-weight:850;">${escapeHtml(card.current_value || '-')}</td>
              <td style="text-align:right;">${escapeHtml(card.secondary_value || '-')}</td>
              <td style="text-align:right;"><span class="pres-delta ${trendClass}">${escapeHtml(trend)}</span></td>
              <td>${escapeHtml(card.source || '-')}</td>
            </tr>
          `;
        }).join('');

        const priorities = document.getElementById('pres-digital-priorities');
        if (priorities) {
          const availableCards = cards.filter(card => card.available !== false);
          const trendableCards = availableCards
            .filter(card => card.key !== 'casa' && /-?\d/.test(String(card.trend || '')))
            .map(card => ({ ...card, trendRaw: digitalTrendNumber(card.trend) }));
          const strongest = [...trendableCards].sort((a, b) => b.trendRaw - a.trendRaw)[0];
          const weakest = [...trendableCards].sort((a, b) => a.trendRaw - b.trendRaw)[0];
          priorities.innerHTML = `
            <div class="pres-digital-priority"><span>Coverage data</span><strong>${availableCards.length} dari ${cards.length} strategi</strong><small>Strategi dengan sumber angka aktif pada posisi terpilih.</small></div>
            <div class="pres-digital-priority"><span>Momentum terkuat</span><strong>${escapeHtml(strongest?.title || '-')}</strong><small>${escapeHtml(strongest?.trend || '-')} terhadap basis pembanding.</small></div>
            <div class="pres-digital-priority"><span>Perlu intervensi</span><strong>${escapeHtml(weakest?.title || '-')}</strong><small>${weakest && weakest.trendRaw < 0 ? escapeHtml(weakest.trend || '-') + ' membutuhkan recovery.' : 'Tidak ada pertumbuhan negatif pada data tersedia.'}</small></div>
          `;
        }

        const tableWrap = document.getElementById('pres-digital-table-wrap');
        const chartWrap = document.getElementById('pres-digital-chart-wrap');
        tableWrap.classList.toggle('hidden', digitalState.view !== 'table');
        chartWrap.classList.toggle('hidden', digitalState.view !== 'timeseries');

        if (digitalState.view === 'timeseries') {
          const canvas = document.getElementById('pres-digital-chart');
          if (digitalChart) digitalChart.destroy();
          digitalChart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
              labels: ['Basis', 'Posisi'],
              datasets: cards.map((card, idx) => {
                const current = parseCompactNumber(card.current_value);
                const growth = digitalTrendNumber(card.trend);
                const previous = growth === -100 ? 0 : current / (1 + (growth / 100));
                const colors = ['#1155c8', '#059669', '#f59e0b', '#dc2626', '#2f80ed', '#0f766e', '#64748b', '#2fb8df'];
                return {
                  label: card.title || card.key,
                  data: [Math.max(0, previous), current],
                  borderColor: colors[idx % colors.length],
                  backgroundColor: colors[idx % colors.length] + '1F',
                  tension: 0.25,
                  borderWidth: 2,
                  pointRadius: 3
                };
              })
            },
            options: {
              animation: false,
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { family: 'Inter', size: 10, weight: '700' } } }
              },
              scales: {
                x: { grid: { display: false }, ticks: { color: '#64748b' } },
                y: { grid: { color: 'rgba(15,23,42,0.07)' }, ticks: { color: '#64748b' } }
              }
            }
          });
        }
      };

      const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
      };

      const metricAggregate = (metric) => {
        const scopedMetric = activeSummary()?.metrics?.[metric];
        if (scopedMetric) {
          return {
            latest: Number(scopedMetric.latest || 0),
            ytd: Number(scopedMetric.ytd || 0),
            mtm: Number(scopedMetric.mtm || 0),
            mtd: Number(scopedMetric.mtd || 0),
            rka: Number(scopedMetric.rka || 0),
          };
        }

        const rows = presentationData?.performance_overview?.matrix?.rows?.[deckState.scope] || [];
        return rows.reduce((acc, row) => {
          const item = row?.metrics?.[metric] || {};
          acc.latest += Number(item.latest_raw || 0);
          acc.ytd += Number(item.ytd_raw || 0);
          acc.mtm += Number(item.mtm_raw || 0);
          acc.mtd += Number(item.mtd_raw || 0);
          acc.rka += Number(item.rka_raw || 0);
          return acc;
        }, { latest: 0, ytd: 0, mtm: 0, mtd: 0, rka: 0 });
      };

      const signedCurrency = (value) => {
        const numeric = Number(value || 0);
        const prefix = numeric >= 0 ? '+' : '-';
        return prefix + formatCurrencyCompact(Math.abs(numeric));
      };

      const setSlideNarrative = (slideIndex, value) => {
        const target = document.getElementById(`pres-slide-narrative-text-${slideIndex}`);
        if (!target) return;

        const ruleNarrative = presentationData?.narrative?.slides?.[slideIndex] || null;
        const selectorDrivenSlides = [7, 9, 10, 14, 15, 16];
        const useRuleNarrative = deckState.scope === 'area6'
          && !selectorDrivenSlides.includes(slideIndex)
          && ruleNarrative?.body;
        const narrative = useRuleNarrative ? ruleNarrative.body : value;
        const wrapper = target.closest('[data-slide-narrative]');
        const label = target.previousElementSibling;

        target.textContent = narrative || 'Data belum tersedia untuk membentuk pembacaan pada slide ini.';
        target.dataset.dynamicNarrative = 'true';
        target.dataset.narrativeEngine = useRuleNarrative ? 'rule' : 'live-selector';
        if (label && ruleNarrative?.headline) {
          label.textContent = ruleNarrative.headline;
        }
        if (wrapper) {
          wrapper.dataset.severity = ruleNarrative?.severity || 'neutral';
        }
      };

      const qualityGroupsForScope = (data, type) => {
        if (deckState.scope === 'area6') {
          const payload = data?.quality?.[type] || {};
          return [
            ['Ritel Nominal', payload.ritel_nominal || []],
            ['Ritel Rasio', payload.ritel_ratio || []],
            ['Micro Nominal', payload.micro_nominal || []],
            ['Micro Rasio', payload.micro_ratio || []],
          ].filter(([, rows]) => Array.isArray(rows) && rows.length > 0);
        }

        const rows = data?.performance_overview?.matrix?.rows?.[deckState.scope] || [];
        const nominal = rows.map(row => {
          const value = Number(row?.metrics?.[type]?.latest_raw || 0);
          return {
            label: row.label || '-',
            value_raw: value,
            value: row?.metrics?.[type]?.latest_fmt || formatCurrencyCompact(value),
          };
        }).filter(row => row.value_raw > 0).sort((a, b) => b.value_raw - a.value_raw);
        const ratio = rows.map(row => {
          const value = Number(row?.metrics?.[type]?.latest_raw || 0);
          const os = Number(row?.metrics?.os?.latest_raw || 0);
          const percentage = os > 0 ? (value / os) * 100 : 0;
          return {
            label: row.label || '-',
            value_raw: percentage,
            value: formatPercent(percentage),
          };
        }).filter(row => row.value_raw > 0).sort((a, b) => b.value_raw - a.value_raw);

        return [
          ['Unit Nominal', nominal],
          ['Unit Rasio', ratio],
        ].filter(([, groupRows]) => groupRows.length > 0);
      };

      const qualityRankingLeader = (data, type, mode) => {
        const rows = qualityGroupsForScope(data, type)
          .filter(([title]) => mode === 'ratio' ? /rasio/i.test(title) : /nominal/i.test(title))
          .flatMap(([, groupRows]) => groupRows);

        return rows.slice().sort((a, b) => {
          const aDisplay = Number(a?.value_raw ?? parseCompactNumber(a?.value || a?.value_fmt || a?.amount_fmt || 0));
          const bDisplay = Number(b?.value_raw ?? parseCompactNumber(b?.value || b?.value_fmt || b?.amount_fmt || 0));
          return bDisplay - aDisplay;
        })[0] || null;
      };

      const activeProductivityScope = (data = presentationData) => {
        return data?.productivity?.scopes?.[deckState.scope] || {};
      };

      const activeProductivityCategory = (data = presentationData) => {
        return activeProductivityScope(data)?.categories?.[productivityState.category] || {};
      };

      const activeMicroPdwkScope = (data = presentationData) => {
        const scopes = data?.micro?.pdwk?.scopes || {};
        if (scopes?.[deckState.scope]) return scopes[deckState.scope];
        return deckState.scope === 'area6' ? (scopes?.area6 || {}) : {};
      };

      const activeTrendScope = (data = presentationData) => {
        return data?.timeseries?.scopes?.[deckState.scope] || {};
      };

      const activeTrendGroup = (data = presentationData) => {
        return data?.timeseries?.groups?.[trendState.group] || {};
      };

      const renderSlideNarratives = (data = presentationData) => {
        if (!data) return;

        const period = data?.meta?.period_label || 'periode aktif';
        const loanPeriod = data?.meta?.daily_loan_period_label || data?.meta?.loan_period_label || period;
        const branches = scopedBranchRows(data);
        const comparisonBranches = allBranchRows(data);
        const scopeLabel = activeScopeLabel(data);
        const summaryCards = activeSummary(data)?.cards || [];
        const summaryMap = Object.fromEntries(summaryCards.map(card => [card.key, card]));
        const loanProducts = activeLoanProducts(data)?.rows || [];
        const digitalCards = data?.digital_strategy?.cards || [];
        const availableDigitalCards = digitalCards.filter(card => card.available !== false);

        setSlideNarrative(0, `${scopeLabel} pada ${period}: deck memakai posisi loan ${loanPeriod}, ${loanProducts.length} produk kredit, produktivitas RM, 13 titik tren bulanan, dan ${availableDigitalCards.length} strategi digital dari payload terbaru.`);

        const totalOs = Number(summaryMap.os?.value_raw || metricAggregate('os').latest || 0);
        const totalRkaOs = sumNumeric(branches, 'pinjaman_target');
        const osAchievement = totalRkaOs > 0 ? (totalOs / totalRkaOs) * 100 : null;
        const topOsBranch = branches.slice().sort((a, b) => Number(b.pinjaman || 0) - Number(a.pinjaman || 0))[0];
        setSlideNarrative(1, totalOs > 0
          ? `${period}: OS ${formatCurrencyCompact(totalOs)}${osAchievement === null ? '' : ` mencapai ${formatPercent(osAchievement)} RKA`}. ${topOsBranch?.name || '-'} menjadi kontributor terbesar; rasio SML ${summaryMap.sml?.ratio || '-'} dan NPL ${summaryMap.npl?.ratio || '-'}.`
          : 'OS dan kualitas kredit belum tersedia pada payload periode aktif.');

        const savingsCards = activeSavings(data)?.cards || [];
        const savingsMap = Object.fromEntries(savingsCards.map(card => [card.key, card]));
        const totalSavingsCard = savingsMap.total_simpanan || savingsMap.simpanan || savingsCards[0] || {};
        const savingsComponents = savingsCards.filter(card => !['total_simpanan', 'simpanan', 'casa'].includes(card.key));
        const leadingSavings = savingsComponents.slice().sort((a, b) => Number(b.pct_raw || b.value_raw || 0) - Number(a.pct_raw || a.value_raw || 0))[0];
        const casaCard = savingsMap.casa || {};
        setSlideNarrative(2, savingsCards.length
          ? `${period}: total simpanan ${totalSavingsCard.value || summaryMap.simpanan?.value || '-'}. ${leadingSavings?.label || 'Komponen utama'} memberi porsi terbesar ${leadingSavings?.pct || '-'}, sedangkan CASA berada pada ${casaCard.pct || casaCard.value || summaryMap.casa?.value || '-'}.`
          : 'Komposisi simpanan belum tersedia pada payload periode aktif.');

        const sortedProducts = loanProducts.slice().sort((a, b) => Number(b.os_raw || 0) - Number(a.os_raw || 0));
        const productLeader = sortedProducts[0];
        const highestProductRisk = sortedProducts.slice().sort((a, b) => {
          const bRisk = (Number(b.sml_raw || 0) + Number(b.npl_raw || 0)) / Math.max(1, Number(b.os_raw || 0));
          const aRisk = (Number(a.sml_raw || 0) + Number(a.npl_raw || 0)) / Math.max(1, Number(a.os_raw || 0));
          return bRisk - aRisk;
        })[0];
        const productRiskRatio = highestProductRisk
          ? ((Number(highestProductRisk.sml_raw || 0) + Number(highestProductRisk.npl_raw || 0)) / Math.max(1, Number(highestProductRisk.os_raw || 0))) * 100
          : 0;
        setSlideNarrative(3, sortedProducts.length
          ? `${productLeader?.label || '-'} memimpin OS produk sebesar ${productLeader?.os || '-'}. Risiko gabungan SML dan NPL tertinggi berada pada ${highestProductRisk?.label || '-'} dengan rasio ${formatPercent(productRiskRatio)} terhadap OS produknya.`
          : 'Detail produk kredit belum tersedia pada payload periode aktif.');

        const smlRaw = Number(summaryMap.sml?.value_raw || metricAggregate('sml').latest || 0);
        const nplRaw = Number(summaryMap.npl?.value_raw || metricAggregate('npl').latest || 0);
        const healthyRaw = Math.max(0, totalOs - smlRaw - nplRaw);
        const healthyRatio = totalOs > 0 ? (healthyRaw / totalOs) * 100 : 0;
        setSlideNarrative(4, totalOs > 0
          ? `Portofolio lancar mencakup ${formatPercent(healthyRatio)} dari OS. Eksposur Kol 2 ${formatCurrencyCompact(smlRaw)} perlu dimigrasikan, sementara Kol 3-5 ${formatCurrencyCompact(nplRaw)} menjadi fokus recovery.`
          : 'Komposisi kolektibilitas belum dapat dibentuk karena total OS tidak tersedia.');

        const branchFocus = branchAnalysisState.scope === 'all'
          ? null
          : comparisonBranches.find(branch => String(branch.name || '').toUpperCase() === branchAnalysisState.scope);
        setSlideNarrative(5, branchFocus
          ? `${branchFocus.name}: Dana ${branchFocus.simpanan_fmt || '-'}, OS ${branchFocus.pinjaman_fmt || '-'}, SML ${branchFocus.sml_pct_fmt || '-'}, dan NPL ${branchFocus.npl_pct_fmt || '-'}. Garis rata-rata tetap memakai seluruh cabang sebagai pembanding.`
          : `${comparisonBranches.length} cabang dibandingkan pada basis Dana, OS, dan kolektibilitas. ${scopeLabel === 'Area 6 Konsol' ? 'Seluruh cabang aktif' : scopeLabel + ' disorot'} terhadap rata-rata Area 6; ukuran bubble kualitas mengikuti nominal bucket.`);

        setSlideNarrative(6, savingsCards.length
          ? `CASA ${casaCard.pct || casaCard.value || summaryMap.casa?.value || '-'} dibentuk oleh giro dan tabungan. ${leadingSavings?.label || 'Komponen terbesar'} mendominasi mix ${leadingSavings?.pct || '-'}; deposito menunjukkan porsi dana berbiaya yang perlu dikendalikan.`
          : 'Mix funding dan CASA belum tersedia pada payload periode aktif.');

        const selectedBranch = comparisonBranches.find(branch => String(branch.name || '').toUpperCase() === branchDetailState.scope) || branches[0] || comparisonBranches[0];
        if (selectedBranch) {
          const selectedFundingAch = Number(selectedBranch.simpanan_target || 0) > 0
            ? (Number(selectedBranch.simpanan || 0) / Number(selectedBranch.simpanan_target)) * 100
            : null;
          const selectedOsAch = Number(selectedBranch.pinjaman_target || 0) > 0
            ? (Number(selectedBranch.pinjaman || 0) / Number(selectedBranch.pinjaman_target)) * 100
            : null;
          setSlideNarrative(7, `${selectedBranch.name}: Dana ${selectedFundingAch === null ? '-' : formatPercent(selectedFundingAch)} RKA dan OS ${selectedOsAch === null ? '-' : formatPercent(selectedOsAch)} RKA. Risiko SML ${selectedBranch.sml_pct_fmt || '-'} serta NPL ${selectedBranch.npl_pct_fmt || '-'} menentukan prioritas tindak lanjut.`);
        } else {
          setSlideNarrative(7, 'Data cabang belum tersedia untuk membentuk bedah kinerja.');
        }

        const financial = activeFinancial(data);
        const financialMap = Object.fromEntries((financial.cards || []).map(card => [card.key, card]));
        const financialBranches = financial.branches || [];
        const topProfitBranch = financialBranches.slice().sort((a, b) => Number(b.value_raw || 0) - Number(a.value_raw || 0))[0];
        const positiveProfitBranches = financialBranches.filter(row => Number(row.value_raw || 0) > 0).length;
        setSlideNarrative(8, (financial.cards || []).length
          ? `${financial.period_label || period}: laba setelah pajak ${financialMap.profit_after_tax?.value || '-'}, NIM ${financialMap.nim?.value || '-'}, dan BOPO ${financialMap.bopo?.value || '-'}. ${topProfitBranch?.name || '-'} menjadi kontributor tertinggi; ${positiveProfitBranches}/${financialBranches.length} cabang mencatat laba positif.`
          : 'Financial highlight Almafacts belum tersedia pada payload periode aktif.');

        const performanceRows = getPerformanceRows();
        const performanceMetric = performanceState.metric;
        const performanceScope = (data?.performance_overview?.matrix?.scope_options || []).find(option => option.key === performanceState.scope)?.label || 'Area 6 Konsol';
        const performanceTotals = performanceRows.reduce((acc, row) => {
          const metric = row?.metrics?.[performanceMetric] || {};
          acc.latest += Number(metric.latest_raw || 0);
          acc.mtd += Number(metric.mtd_raw || 0);
          acc.rka += Number(metric.rka_raw || 0);
          acc.os += Number(row?.metrics?.os?.latest_raw || 0);
          return acc;
        }, { latest: 0, mtd: 0, rka: 0, os: 0 });
        const performanceLeader = performanceRows[0];
        const performanceContext = ['sml', 'npl'].includes(performanceMetric)
          ? `rasio scope ${formatPercent(performanceTotals.os > 0 ? (performanceTotals.latest / performanceTotals.os) * 100 : 0)}`
          : `pencapaian RKA ${performanceTotals.rka > 0 ? formatPercent((performanceTotals.latest / performanceTotals.rka) * 100) : '-'}`;
        setSlideNarrative(9, performanceRows.length
          ? `${metricLabel(performanceMetric)} ${performanceScope} berada di ${formatCurrencyCompact(performanceTotals.latest)} dengan MtD ${signedCurrency(performanceTotals.mtd)} dan ${performanceContext}. Kontributor terbesar: ${performanceLeader?.label || '-'}.`
          : `Data ${metricLabel(performanceMetric)} belum tersedia untuk ${performanceScope}.`);

        const segmentRows = getSegmentRows();
        const segmentScope = (data?.performance_overview?.matrix?.scope_options || []).find(option => option.key === segmentState.scope)?.label || 'Area 6 Konsol';
        const segmentTotals = segmentRows.reduce((acc, row) => {
          const metric = row?.metrics?.[segmentState.metric] || {};
          acc.latest += Number(metric.latest_raw || 0);
          acc.rka += Number(metric.rka_raw || 0);
          acc.mtd += Number(metric.mtd_raw || 0);
          return acc;
        }, { latest: 0, rka: 0, mtd: 0 });
        const segmentLeader = segmentRows[0];
        setSlideNarrative(10, segmentRows.length
          ? `${segmentMetricLabel(segmentState.metric)} ${segmentScope} mencapai ${formatCurrencyCompact(segmentTotals.latest)} atau ${segmentTotals.rka > 0 ? formatPercent((segmentTotals.latest / segmentTotals.rka) * 100) : '-'} RKA. ${segmentLeader?.label || '-'} menjadi kontributor utama; MtD scope ${signedCurrency(segmentTotals.mtd)}.`
          : `${segmentMetricLabel(segmentState.metric)} belum tersedia untuk ${segmentScope}.`);

        const composition = data?.performance_overview?.composition || {};
        const riskBranch = riskState.scope === 'area6'
          ? null
          : comparisonBranches.find(branch => String(branch.name || '').toUpperCase() === riskState.scope);
        const riskLabel = riskBranch?.name || scopeLabel;
        const riskLar = riskBranch ? Number(riskBranch.lar_pct || 0) : Number(composition.os?.raw_pct || 0);
        const riskSml = riskBranch ? Number(riskBranch.sml_pct || 0) : Number(composition.sml?.raw_pct || 0);
        const riskNpl = riskBranch ? Number(riskBranch.npl_pct || 0) : Number(composition.npl?.raw_pct || 0);
        const explicitRiskRestruk = riskBranch
          ? Number(riskBranch.restruk_pct)
          : Number(composition.restruk?.raw_pct);
        const riskRestruk = Number.isFinite(explicitRiskRestruk)
          ? explicitRiskRestruk
          : Math.max(0, riskLar - riskSml - riskNpl);
        setSlideNarrative(11, `${riskLabel}: LAR ${formatPercent(riskLar)}, terdiri dari SML ${formatPercent(riskSml)}, NPL ${formatPercent(riskNpl)}, dan restruktur lancar ${formatPercent(riskRestruk)}.`);

        ['sml', 'npl'].forEach((type, index) => {
          const nominalLeader = qualityRankingLeader(data, type, 'nominal');
          const ratioLeader = qualityRankingLeader(data, type, 'ratio');
          const nominalValue = nominalLeader?.value || nominalLeader?.value_fmt || nominalLeader?.amount_fmt || '-';
          const ratioValue = ratioLeader?.value || ratioLeader?.value_fmt || ratioLeader?.amount_fmt || '-';
          setSlideNarrative(12 + index, nominalLeader || ratioLeader
            ? `${type.toUpperCase()} nominal tertinggi berada pada ${nominalLeader?.label || nominalLeader?.name || '-'} sebesar ${nominalValue}; rasio tertinggi pada ${ratioLeader?.label || ratioLeader?.name || '-'} sebesar ${ratioValue}. Keduanya dibaca terpisah agar skala dan intensitas risiko tidak tertukar.`
            : `Ranking ${type.toUpperCase()} belum tersedia pada periode aktif.`);
        });

        if (data?.kts?.loading_details) {
          setSlideNarrative(14, `Detail KTS ${ktsState.category} ${ktsState.scope === 'ritel' ? 'Ritel' : 'Micro'} sedang dimuat terpisah agar deck utama tetap responsif.`);
        } else {
          const ktsPayload = scopedKtsPayload(ktsState.category, ktsState.scope);
          const topKtsBranch = (ktsPayload.branches || []).slice().sort((a, b) => Number(b.total_os || 0) - Number(a.total_os || 0))[0];
          setSlideNarrative(14, `${scopeLabel}: KTS ${ktsState.category} ${ktsState.scope === 'ritel' ? 'Ritel' : 'Micro'} mencakup ${new Intl.NumberFormat('id-ID').format(Number(ktsPayload.total_count || 0))} rekening dengan OS ${ktsPayload.total_os_fmt || formatCurrencyCompact(ktsPayload.total_os || 0)}${topKtsBranch ? `; eksposur terbesar berada di ${topKtsBranch.branch_name || '-'}.` : '.'}`);
        }

        const productivity = activeProductivityCategory(data);
        const productivityTotal = productivity?.total || {};
        const productivityRows = productivity?.rows || [];
        const productivityLeader = productivityRows[0];
        setSlideNarrative(15, productivity?.available
          ? `${scopeLabel}: ${productivity.label || 'Produktivitas RM'} mencakup ${new Intl.NumberFormat('id-ID').format(Number(productivityTotal.rm_count || 0))} RM dengan realisasi ${productivityTotal.realisasi_os_fmt || '-'} dan rata-rata ${productivityTotal.average_per_rm_fmt || '-'} per RM. Kontributor teratas ${productivityLeader?.name || '-'} sebesar ${productivityLeader?.realisasi_os_fmt || '-'}.`
          : `Produktivitas ${productivity?.label || 'RM'} belum tersedia untuk ${scopeLabel}.`);

        const trendScope = activeTrendScope(data);
        const trendGroup = activeTrendGroup(data);
        const trendKeys = trendGroup?.keys || [];
        const firstTrend = trendScope?.series?.[trendKeys[0]];
        const trendValues = firstTrend?.values || [];
        const trendStart = Number(trendValues[0] || 0);
        const trendEnd = Number(trendValues[trendValues.length - 1] || 0);
        const trendChange = trendStart !== 0 ? ((trendEnd / trendStart) - 1) * 100 : 0;
        setSlideNarrative(16, trendScope?.available && firstTrend
          ? `${scopeLabel}: ${trendGroup.label || 'Tren'} mencakup ${trendScope.labels?.length || 0} titik bulanan. ${firstTrend.label || '-'} bergerak dari ${firstTrend.display_values?.[0] || '-'} menjadi ${firstTrend.display_values?.[trendValues.length - 1] || '-'} atau ${trendChange >= 0 ? '+' : ''}${formatPercent(trendChange)}.`
          : `Timeseries ${trendGroup?.label || ''} belum tersedia untuk ${scopeLabel}.`);

        const trendableDigital = availableDigitalCards
          .filter(card => card.key !== 'casa' && /-?\d/.test(String(card.trend || '')))
          .map(card => ({ ...card, trendRaw: digitalTrendNumber(card.trend) }));
        const strongestDigital = trendableDigital.slice().sort((a, b) => b.trendRaw - a.trendRaw)[0];
        const weakestDigital = trendableDigital.slice().sort((a, b) => a.trendRaw - b.trendRaw)[0];
        setSlideNarrative(17, `${availableDigitalCards.length}/${digitalCards.length} strategi memiliki sumber aktif pada benchmark Area 6. Momentum tertinggi: ${strongestDigital?.title || '-'} ${strongestDigital?.trend || '-'}; prioritas intervensi: ${weakestDigital?.title || '-'} ${weakestDigital?.trend || '-'}.`);
      };

      const signedClass = (value) => Number(value || 0) < 0 ? 'neg' : '';

      const simpleBar = (value, max, color = '#0857c3') => {
        const width = max > 0 ? Math.max(5, Math.min(100, (Number(value || 0) / max) * 100)) : 0;
        return `<span class="pres-value-bar-track"><span class="pres-value-bar-fill" style="width:${width}%; background:${color};"></span></span>`;
      };

      const renderExecutiveLoanDashboard = (data) => {
        const summaryCards = activeSummary(data)?.cards || [];
        const cardMap = Object.fromEntries(summaryCards.map(card => [card.key, card]));
        const os = metricAggregate('os');
        const sml = metricAggregate('sml');
        const npl = metricAggregate('npl');
        const osRaw = Number(cardMap.os?.value_raw || os.latest || 0);

        setText('pres-loan-sml-value', cardMap.sml?.value || formatCurrencyCompact(sml.latest));
        setText('pres-loan-sml-ratio', cardMap.sml?.ratio || '-');
        setText('pres-loan-sml-status', cardMap.sml?.ratio || '-');
        setText('pres-loan-npl-value', cardMap.npl?.value || formatCurrencyCompact(npl.latest));
        setText('pres-loan-npl-ratio', cardMap.npl?.ratio || '-');
        setText('pres-loan-npl-status', cardMap.npl?.ratio || '-');

        [
          ['pres-loan-os-ytd', os.ytd],
          ['pres-loan-os-mtm', os.mtm],
          ['pres-loan-os-mtd', os.mtd],
          ['pres-loan-sml-ytd', sml.ytd],
          ['pres-loan-sml-mtm', sml.mtm],
          ['pres-loan-sml-mtd', sml.mtd],
          ['pres-loan-npl-ytd', npl.ytd],
          ['pres-loan-npl-mtm', npl.mtm],
          ['pres-loan-npl-mtd', npl.mtd],
        ].forEach(([id, value]) => {
          const el = document.getElementById(id);
          if (!el) return;
          el.textContent = signedCurrency(value);
          el.classList.toggle('neg', Number(value || 0) < 0);
        });

        renderLoanProducts(data);

        const healthy = Math.max(0, osRaw - Number(cardMap.sml?.value_raw || sml.latest || 0) - Number(cardMap.npl?.value_raw || npl.latest || 0));
        const total = Math.max(1, healthy + Number(cardMap.sml?.value_raw || sml.latest || 0) + Number(cardMap.npl?.value_raw || npl.latest || 0));
        const p1 = (healthy / total) * 100;
        const p2 = (Number(cardMap.sml?.value_raw || sml.latest || 0) / total) * 100;
        const donut = document.getElementById('pres-loan-mini-donut');
        if (donut) {
          donut.style.setProperty('--p1', p1.toFixed(2) + '%');
          donut.style.setProperty('--p2', p2.toFixed(2) + '%');
        }

        const summary = document.getElementById('pres-loan-composition-summary');
        if (summary) {
          summary.innerHTML = `
            <div class="metric-line is-os"><span>OS</span><strong>${escapeHtml(cardMap.os?.value || '-')}</strong></div>
            <div class="metric-line is-sml"><span>SML</span><strong>${escapeHtml(cardMap.sml?.value || '-')} <small>${escapeHtml(cardMap.sml?.ratio || '-')}</small></strong></div>
            <div class="metric-line is-npl"><span>NPL</span><strong>${escapeHtml(cardMap.npl?.value || '-')} <small>${escapeHtml(cardMap.npl?.ratio || '-')}</small></strong></div>
          `;
        }

        const donutBig = document.getElementById('pres-loan-composition-donut');
        if (donutBig) {
          donutBig.style.setProperty('--p1', p1.toFixed(2) + '%');
          donutBig.style.setProperty('--p2', p2.toFixed(2) + '%');
        }
        setText('pres-loan-composition-total', cardMap.os?.value || formatCurrencyCompact(osRaw));

        const legend = document.getElementById('pres-loan-composition-legend');
        if (legend) {
          legend.innerHTML = `
            <div class="pres-portfolio-metric" style="--metric-tone:#10b981;"><span>Kol 1 / Lancar</span><strong>${escapeHtml(formatCurrencyCompact(healthy))}</strong><small>${formatPercent(p1)} dari OS</small></div>
            <div class="pres-portfolio-metric" style="--metric-tone:#f59e0b;"><span>Kol 2 / SML</span><strong>${escapeHtml(cardMap.sml?.value || '-')}</strong><small>${escapeHtml(cardMap.sml?.ratio || formatPercent(p2))}</small></div>
            <div class="pres-portfolio-metric" style="--metric-tone:#ef4444;"><span>Kol 3-5 / NPL</span><strong>${escapeHtml(cardMap.npl?.value || '-')}</strong><small>${escapeHtml(cardMap.npl?.ratio || formatPercent(100 - p1 - p2))}</small></div>
            <div class="pres-portfolio-metric" style="--metric-tone:#0857c3;"><span>Intermediasi / LDR</span><strong>${escapeHtml(cardMap.ldr?.value || '-')}</strong><small>Pinjaman terhadap DPK</small></div>
          `;
        }

        const nplPct = Math.max(0, 100 - p1 - p2);
        const stack = document.getElementById('pres-loan-risk-stack');
        if (stack) {
          stack.innerHTML = `
            <div class="pres-risk-stack-head"><span>Distribusi kolektibilitas</span><strong>100% total OS</strong></div>
            <div class="pres-risk-stack-track" aria-label="Komposisi kolektibilitas kredit">
              <span style="width:${p1.toFixed(2)}%; background:#10b981;">${p1 >= 8 ? formatPercent(p1) : ''}</span>
              <span style="width:${p2.toFixed(2)}%; background:#f59e0b;">${p2 >= 4 ? formatPercent(p2) : ''}</span>
              <span style="width:${nplPct.toFixed(2)}%; background:#ef4444;">${nplPct >= 4 ? formatPercent(nplPct) : ''}</span>
            </div>
          `;
        }

        const reading = document.getElementById('pres-loan-risk-reading');
        if (reading) {
          const atRisk = Number(cardMap.sml?.value_raw || sml.latest || 0) + Number(cardMap.npl?.value_raw || npl.latest || 0);
          const atRiskRatio = Math.max(0, 100 - p1);
          reading.innerHTML = `
            <i class="fas fa-crosshairs"></i>
            <div><strong>${formatPercent(p1)} portofolio berada di Kol 1/lancar</strong><p>Eksposur yang memerlukan perhatian berjumlah ${escapeHtml(formatCurrencyCompact(atRisk))}: ${escapeHtml(formatCurrencyCompact(Number(cardMap.sml?.value_raw || sml.latest || 0)))} pada Kol 2 dan ${escapeHtml(formatCurrencyCompact(Number(cardMap.npl?.value_raw || npl.latest || 0)))} pada Kol 3-5. Fokus pengelolaan kualitas diarahkan pada migrasi Kol 2 dan recovery NPL.</p></div>
            <div class="pres-portfolio-action-grid">
              <div>
                <span>Protect</span><strong>${formatPercent(p1)}</strong><small>Pertahankan dominasi portofolio lancar.</small>
                <ul>
                  <li><span>Nominal lancar</span><b>${escapeHtml(formatCurrencyCompact(healthy))}</b></li>
                  <li><span>Total at risk</span><b>${escapeHtml(formatCurrencyCompact(atRisk))}</b></li>
                  <li><span>Porsi at risk</span><b>${formatPercent(atRiskRatio)}</b></li>
                </ul>
              </div>
              <div>
                <span>Migrate</span><strong>${escapeHtml(cardMap.sml?.value || formatCurrencyCompact(sml.latest))}</strong><small>Kol 2 / SML ${escapeHtml(cardMap.sml?.ratio || formatPercent(p2))} dari OS.</small>
                <ul>
                  <li><span>YtD</span><b>${escapeHtml(signedCurrency(sml.ytd))}</b></li>
                  <li><span>MtM</span><b>${escapeHtml(signedCurrency(sml.mtm))}</b></li>
                  <li><span>MtD</span><b>${escapeHtml(signedCurrency(sml.mtd))}</b></li>
                </ul>
              </div>
              <div>
                <span>Recover</span><strong>${escapeHtml(cardMap.npl?.value || formatCurrencyCompact(npl.latest))}</strong><small>Kol 3-5 / NPL ${escapeHtml(cardMap.npl?.ratio || formatPercent(nplPct))} dari OS.</small>
                <ul>
                  <li><span>YtD</span><b>${escapeHtml(signedCurrency(npl.ytd))}</b></li>
                  <li><span>MtM</span><b>${escapeHtml(signedCurrency(npl.mtm))}</b></li>
                  <li><span>MtD</span><b>${escapeHtml(signedCurrency(npl.mtd))}</b></li>
                </ul>
              </div>
            </div>
          `;
        }
      };

      const renderLoanProducts = (data) => {
        const rows = [...(activeLoanProducts(data)?.rows || [])].sort((a, b) => Number(b.os_raw || 0) - Number(a.os_raw || 0));
        const maxOs = Math.max(...rows.map(row => Number(row.os_raw || 0)), 0);
        const totalOs = rows.reduce((sum, row) => sum + Number(row.os_raw || 0), 0);
        const maxSml = Math.max(...rows.map(row => Number(row.sml_raw || 0)), 0);
        const maxNpl = Math.max(...rows.map(row => Number(row.npl_raw || 0)), 0);
        const compactHtml = rows.map(row => `
          <div class="bri-product-row">
            <div style="font-weight:900; color:#0f172a;"><i class="${escapeHtml(row.icon || 'fas fa-chart-bar')}" style="color:#0857c3; margin-right:0.35rem;"></i>${escapeHtml(row.label || '-')}</div>
            <div><strong>${escapeHtml(row.os || '-')}</strong>${simpleBar(row.os_raw, maxOs, '#0857c3')}</div>
            <div><strong>${escapeHtml(row.sml || '-')}</strong>${simpleBar(row.sml_raw, maxSml, '#71c5e8')}</div>
            <div><strong>${escapeHtml(row.npl || '-')}</strong>${simpleBar(row.npl_raw, maxNpl, '#ef4444')}</div>
          </div>
        `).join('');

        const compactRows = document.getElementById('pres-loan-product-rows');
        if (compactRows) compactRows.innerHTML = compactHtml || '<div class="pres-mini-stat"><span>Produk</span><strong>Data belum tersedia</strong></div>';

        const bars = document.getElementById('pres-loan-product-bars');
        if (bars) {
          bars.innerHTML = rows.map((row, index) => {
            const share = totalOs > 0 ? (Number(row.os_raw || 0) / totalOs) * 100 : 0;
            const smlRatio = Number(row.os_raw || 0) > 0 ? (Number(row.sml_raw || 0) / Number(row.os_raw || 0)) * 100 : 0;
            const nplRatio = Number(row.os_raw || 0) > 0 ? (Number(row.npl_raw || 0) / Number(row.os_raw || 0)) * 100 : 0;
            const tones = ['#0857c3', '#307fe2', '#00a6d6', '#64748b', '#0f766e'];
            return `
              <article class="pres-product-scorecard" style="--product-tone:${tones[index % tones.length]};">
                <div class="pres-product-scorecard-head"><strong><i class="${escapeHtml(row.icon || 'fas fa-chart-bar')}" style="color:${tones[index % tones.length]}; margin-right:0.35rem;"></i>${escapeHtml(row.label || '-')}</strong><span class="pres-product-share">${formatPercent(share)} OS</span></div>
                <div class="pres-product-os"><span>Outstanding</span><strong>${escapeHtml(row.os || '-')}</strong></div>
                <div class="pres-product-risk-grid">
                  <div class="pres-product-risk" style="--risk-tone:#f59e0b;"><span>SML</span><strong>${escapeHtml(row.sml || '-')} / ${formatPercent(smlRatio)}</strong></div>
                  <div class="pres-product-risk" style="--risk-tone:#ef4444;"><span>NPL</span><strong>${escapeHtml(row.npl || '-')} / ${formatPercent(nplRatio)}</strong></div>
                </div>
              </article>
            `;
          }).join('') || '<div class="pres-empty-state"><strong>Data produk belum tersedia</strong></div>';
        }

        setText('pres-product-count', `${rows.length} produk`);

        const insights = document.getElementById('pres-product-insights');
        if (insights) {
          const leader = rows[0];
          const highestSml = [...rows].sort((a, b) => (Number(b.sml_raw || 0) / Math.max(1, Number(b.os_raw || 0))) - (Number(a.sml_raw || 0) / Math.max(1, Number(a.os_raw || 0))))[0];
          const highestNpl = [...rows].sort((a, b) => (Number(b.npl_raw || 0) / Math.max(1, Number(b.os_raw || 0))) - (Number(a.npl_raw || 0) / Math.max(1, Number(a.os_raw || 0))))[0];
          const ratioText = (row, key) => row && Number(row.os_raw || 0) > 0 ? formatPercent((Number(row[key] || 0) / Number(row.os_raw || 0)) * 100) : '-';
          insights.innerHTML = `
            <div class="pres-readout-item"><span>Kontributor OS</span><strong>${escapeHtml(leader?.label || '-')}</strong><small>${escapeHtml(leader?.os || '-')} atau ${leader ? formatPercent((Number(leader.os_raw || 0) / Math.max(1, totalOs)) * 100) : '-'} portofolio produk.</small></div>
            <div class="pres-readout-item"><span>Rasio SML tertinggi</span><strong>${escapeHtml(highestSml?.label || '-')}</strong><small>${ratioText(highestSml, 'sml_raw')} terhadap OS produknya.</small></div>
            <div class="pres-readout-item"><span>Rasio NPL tertinggi</span><strong>${escapeHtml(highestNpl?.label || '-')}</strong><small>${ratioText(highestNpl, 'npl_raw')} terhadap OS produknya.</small></div>
          `;
        }

        const table = document.getElementById('pres-loan-product-table');
        if (table) {
          table.innerHTML = rows.map(row => `
            <tr>
              <td style="font-weight:850;">${escapeHtml(row.label || '-')}</td>
              <td style="text-align:right;">${escapeHtml(row.os || '-')}</td>
              <td style="text-align:right;">${escapeHtml(row.sml || '-')} <span style="color:#64748b;">${escapeHtml(row.sml_pct || '')}</span></td>
              <td style="text-align:right;">${escapeHtml(row.npl || '-')} <span style="color:#64748b;">${escapeHtml(row.npl_pct || '')}</span></td>
            </tr>
          `).join('') || '<tr><td colspan="4" style="text-align:center;">Data belum tersedia</td></tr>';
        }
      };

      const renderSavingsDashboard = (data) => {
        const cards = activeSavings(data)?.cards || [];
        const grid = document.getElementById('pres-saving-cards');
        if (grid) {
          grid.innerHTML = cards.map(card => `
            <div class="bri-dashboard-card">
              <div class="card-head" style="background:linear-gradient(90deg, ${escapeHtml(card.tone || '#0857c3')}, #307fe2);">${escapeHtml(card.label || '-')}</div>
              <div class="metric-main" style="grid-template-columns:1fr;">
                <div><span>Nominal</span><strong>${escapeHtml(card.value || '-')}</strong></div>
              </div>
              <div class="achievement"><span>Kontribusi</span><strong>${escapeHtml(card.pct || '-')}</strong></div>
            </div>
          `).join('');
        }

        const barItems = cards.filter(card => card.key !== 'total_simpanan');
        const barHtml = barItems.map(card => {
          const pct = Math.max(0, Math.min(100, Number(card.pct_raw || 0)));
          const barReading = ({
            giro: `Kontribusi giro sebagai dana transaksi sebesar ${card.pct || '-'}.`,
            tabungan: `Komponen utama pembentuk CASA dengan share ${card.pct || '-'}.`,
            deposito: `Porsi dana berjangka mencapai ${card.pct || '-'} dari total simpanan.`,
            casa: `Gabungan giro dan tabungan membentuk CASA ${card.pct || '-'}.`,
          })[card.key] || `Kontribusi terhadap total simpanan sebesar ${card.pct || '-'}.`;
          return `
          <div class="bri-raised-bar">
            <div class="bar-meta">
              <strong>${escapeHtml(card.label || '-')}</strong>
              <span>${escapeHtml(card.value || '-')}</span>
            </div>
            <div class="bar-track">
              <div class="bar" style="--bar-width:${pct.toFixed(2)}%; --bar-color:${escapeHtml(card.tone || '#0857c3')};"></div>
            </div>
            <em>${escapeHtml(card.pct || '-')}</em>
            <div class="pres-bar-reading">${escapeHtml(barReading)}</div>
          </div>
        `;
        }).join('');
        ['pres-saving-bar-stage', 'pres-saving-composition-bars'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.innerHTML = barHtml || '<div class="pres-mini-stat"><span>Komposisi</span><strong>Data belum tersedia</strong></div>';
        });

        const tableHtml = `
          <table class="pres-table-dense">
            <thead><tr><th>Komponen</th><th style="text-align:right;">Nominal</th><th style="text-align:right;">Share</th></tr></thead>
            <tbody>
              ${cards.map(card => `<tr><td style="font-weight:850;">${escapeHtml(card.label || '-')}</td><td style="text-align:right;">${escapeHtml(card.value || '-')}</td><td style="text-align:right;">${escapeHtml(card.pct || '-')}</td></tr>`).join('')}
            </tbody>
          </table>
        `;
        const cardMap = Object.fromEntries(cards.map(card => [card.key, card]));
        const mixComponents = cards.filter(card => ['giro', 'tabungan', 'deposito'].includes(card.key));
        const leadingComponent = [...mixComponents].sort((a, b) => Number(b.pct_raw || 0) - Number(a.pct_raw || 0))[0];
        const casaCard = cardMap.casa || {};
        const depositoCard = cardMap.deposito || {};
        const giroCard = cardMap.giro || {};
        const fundingLensHtml = `
          <div class="pres-funding-mix-strip" aria-label="Komposisi giro, tabungan, dan deposito">
            ${mixComponents.map(card => `<span style="width:${Math.max(0, Number(card.pct_raw || 0)).toFixed(2)}%; background:${escapeHtml(card.tone || '#0857c3')};" title="${escapeHtml(card.label || '-')}: ${escapeHtml(card.pct || '-')}"></span>`).join('')}
          </div>
          <div class="pres-funding-lens-grid">
            <div class="pres-funding-lens"><span>CASA</span><strong>${escapeHtml(casaCard.value || '-')}</strong><small>${escapeHtml(casaCard.pct || '-')} dari total simpanan.</small></div>
            <div class="pres-funding-lens"><span>Dana berjangka</span><strong>${escapeHtml(depositoCard.value || '-')}</strong><small>Deposito ${escapeHtml(depositoCard.pct || '-')}.</small></div>
            <div class="pres-funding-lens"><span>Giro</span><strong>${escapeHtml(giroCard.value || '-')}</strong><small>Porsi ${escapeHtml(giroCard.pct || '-')}.</small></div>
            <div class="pres-funding-lens"><span>Komponen dominan</span><strong>${escapeHtml(leadingComponent?.label || '-')}</strong><small>${escapeHtml(leadingComponent?.pct || '-')} dari total simpanan.</small></div>
          </div>
          <div class="pres-funding-reading"><i class="fas fa-chart-pie"></i><p>${escapeHtml(leadingComponent?.label || 'Komponen utama')} menjadi komponen terbesar ${escapeHtml(leadingComponent?.pct || '-')}; CASA ${escapeHtml(casaCard.pct || '-')} dan deposito ${escapeHtml(depositoCard.pct || '-')} menunjukkan struktur dana transaksi versus dana berjangka.</p></div>
        `;
        ['pres-saving-summary-table', 'pres-saving-composition-table'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.innerHTML = tableHtml + fundingLensHtml;
        });
      };

      const setChartExplanation = (id, value) => {
        const paragraph = document.querySelector(`#${id} p`);
        if (!paragraph) return;

        paragraph.textContent = value;
        paragraph.dataset.dynamicExplanation = 'true';
      };

      const renderBranchBubbleExplanations = (branches) => {
        if (!Array.isArray(branches) || branches.length === 0) {
          setChartExplanation('pres-funding-loan-explanation', 'Data cabang belum tersedia untuk membentuk kuadran Dana dan OS.');
          setChartExplanation('pres-quality-bubble-explanation', 'Data cabang belum tersedia untuk membaca distribusi kolektibilitas.');
          return;
        }

        const fundingAverage = sumNumeric(branches, 'simpanan') / branches.length;
        const osAverage = sumNumeric(branches, 'pinjaman') / branches.length;
        const selected = branchAnalysisState.scope === 'all'
          ? null
          : branches.find(branch => String(branch.name || '').toUpperCase() === branchAnalysisState.scope);

        if (selected) {
          const highFunding = Number(selected.simpanan || 0) >= fundingAverage;
          const highOs = Number(selected.pinjaman || 0) >= osAverage;
          const quadrantMeaning = highFunding && highOs
            ? 'skala Dana dan OS sama-sama di atas rata-rata; fokusnya menjaga kualitas serta kontribusi.'
            : highFunding
              ? 'Dana di atas rata-rata tetapi OS di bawah rata-rata; terdapat ruang memperkuat intermediasi.'
              : highOs
                ? 'OS di atas rata-rata tetapi Dana di bawah rata-rata; penguatan funding menjadi prioritas.'
                : 'Dana dan OS berada di bawah rata-rata; cabang memerlukan akselerasi skala yang seimbang.';
          setChartExplanation('pres-funding-loan-explanation', `${selected.name} berada pada Dana ${highFunding ? 'tinggi' : 'rendah'} dan OS ${highOs ? 'tinggi' : 'rendah'} terhadap rata-rata ${formatCurrencyCompact(fundingAverage)} / ${formatCurrencyCompact(osAverage)}. Artinya, ${quadrantMeaning}`);

          const healthyRatio = Math.max(0, 100 - Number(selected.sml_pct || 0) - Number(selected.npl_pct || 0));
          setChartExplanation('pres-quality-bubble-explanation', `Ukuran bubble menunjukkan nominal bucket, bukan jumlah rekening. ${selected.name}: Kol 1 ${formatPercent(healthyRatio)}, SML ${selected.sml_abs_fmt || '-'} (${selected.sml_pct_fmt || '-'}), dan NPL ${selected.npl_abs_fmt || '-'} (${selected.npl_pct_fmt || '-'}).`);
          return;
        }

        const highScaleBranches = branches.filter(branch => Number(branch.simpanan || 0) >= fundingAverage && Number(branch.pinjaman || 0) >= osAverage);
        const fundingPressureBranches = branches.filter(branch => Number(branch.simpanan || 0) < fundingAverage && Number(branch.pinjaman || 0) >= osAverage);
        const lendingCapacityBranches = branches.filter(branch => Number(branch.simpanan || 0) >= fundingAverage && Number(branch.pinjaman || 0) < osAverage);
        const highScaleNames = highScaleBranches.map(branch => branch.name).join(', ') || 'tidak ada';
        setChartExplanation('pres-funding-loan-explanation', `Rata-rata Dana ${formatCurrencyCompact(fundingAverage)} dan OS ${formatCurrencyCompact(osAverage)} membentuk empat kuadran. Kanan-atas: ${highScaleNames}; ${fundingPressureBranches.length} cabang perlu penguatan Dana dan ${lendingCapacityBranches.length} cabang memiliki ruang ekspansi OS.`);

        const highestSml = branches.slice().sort((a, b) => Number(b.sml_pct || 0) - Number(a.sml_pct || 0))[0];
        const highestNpl = branches.slice().sort((a, b) => Number(b.npl_pct || 0) - Number(a.npl_pct || 0))[0];
        const largestRisk = branches.slice().sort((a, b) => {
          return (Number(b.sml_abs || 0) + Number(b.npl_abs || 0)) - (Number(a.sml_abs || 0) + Number(a.npl_abs || 0));
        })[0];
        setChartExplanation('pres-quality-bubble-explanation', `Bubble makin besar berarti nominal bucket makin besar. Rasio SML tertinggi: ${highestSml?.name || '-'} ${highestSml?.sml_pct_fmt || '-'}; NPL tertinggi: ${highestNpl?.name || '-'} ${highestNpl?.npl_pct_fmt || '-'}; eksposur risiko nominal terbesar: ${largestRisk?.name || '-'}.`);
      };

      const branchQuadrantGuide = {
        id: 'branchQuadrantGuide',
        beforeDatasetsDraw(chart, _args, options) {
          const { ctx, chartArea, scales } = chart;
          if (!chartArea || !scales?.x || !scales?.y) return;

          const xPixel = scales.x.getPixelForValue(options.xAverage || 0);
          const yPixel = scales.y.getPixelForValue(options.yAverage || 0);
          ctx.save();
          ctx.strokeStyle = 'rgba(8, 87, 195, 0.42)';
          ctx.lineWidth = 1;
          ctx.setLineDash([5, 4]);
          ctx.beginPath();
          ctx.moveTo(xPixel, chartArea.top);
          ctx.lineTo(xPixel, chartArea.bottom);
          ctx.moveTo(chartArea.left, yPixel);
          ctx.lineTo(chartArea.right, yPixel);
          ctx.stroke();
          ctx.setLineDash([]);
          ctx.fillStyle = 'rgba(71, 85, 105, 0.82)';
          ctx.font = '700 9px Inter, sans-serif';
          ctx.textAlign = 'right';
          ctx.fillText(options.xLabel || 'Rata-rata X', Math.min(chartArea.right - 4, xPixel - 5), chartArea.bottom - 6);
          ctx.textAlign = 'left';
          ctx.fillText(options.yLabel || 'Rata-rata Y', chartArea.left + 5, Math.max(chartArea.top + 11, yPixel - 5));
          ctx.restore();
        },
      };

      const qualityBandGuide = {
        id: 'qualityBandGuide',
        beforeDraw(chart) {
          const { ctx, chartArea, scales } = chart;
          if (!chartArea || !scales?.y) return;

          const paintBand = (from, to, color) => {
            const first = scales.y.getPixelForValue(from);
            const second = scales.y.getPixelForValue(to);
            const top = Math.min(first, second);
            ctx.fillStyle = color;
            ctx.fillRect(chartArea.left, top, chartArea.width, Math.abs(second - first));
          };

          ctx.save();
          paintBand(0.5, 1.5, 'rgba(16, 185, 129, 0.08)');
          paintBand(1.5, 2.5, 'rgba(245, 158, 11, 0.09)');
          paintBand(2.5, 5.5, 'rgba(239, 68, 68, 0.065)');
          ctx.restore();
        },
      };

      const populateBranchPresentationControls = (data) => {
        const branches = data?.performance_overview?.branches || [];
        const analysisSelector = document.getElementById('pres-branch-analysis-selector');
        const detailSelector = document.getElementById('pres-branch-detail-selector');

        if (analysisSelector) {
          analysisSelector.innerHTML = [
            '<option value="all">Semua cabang</option>',
            ...branches.map(branch => `<option value="${escapeHtml(String(branch.name || '').toUpperCase())}">${escapeHtml(branch.name || '-')}</option>`),
          ].join('');
          analysisSelector.value = branchAnalysisState.scope;
        }

        if (!branchDetailState.scope && branches.length > 0) {
          branchDetailState.scope = String(branches[0].name || '').toUpperCase();
        }

        if (detailSelector) {
          detailSelector.innerHTML = branches.map(branch => `
            <option value="${escapeHtml(String(branch.name || '').toUpperCase())}">${escapeHtml(branch.name || '-')}</option>
          `).join('');
          detailSelector.value = branchDetailState.scope || '';
        }
      };

      const renderBranchAnalysisInsights = (branches) => {
        const target = document.getElementById('pres-branch-analysis-insights');
        if (!target || branches.length === 0) return;

        const selected = branchAnalysisState.scope === 'all'
          ? null
          : branches.find(branch => String(branch.name || '').toUpperCase() === branchAnalysisState.scope);
        let items;

        if (selected) {
          items = [
            ['fa-university', 'Dana cabang', selected.simpanan_fmt, `Kontribusi ${selected.simpanan_contribution_pct_fmt || '-'}`, '#0857c3'],
            ['fa-coins', 'OS cabang', selected.pinjaman_fmt, `Kontribusi ${selected.pinjaman_contribution_pct_fmt || '-'}`, '#307fe2'],
            ['fa-triangle-exclamation', 'Kol 2 / SML', selected.sml_abs_fmt, `Porsi ${selected.sml_pct_fmt || '-'}`, '#d97706'],
            ['fa-shield-halved', 'Kol 3-5 / NPL', selected.npl_abs_fmt, `Porsi ${selected.npl_pct_fmt || '-'}`, '#dc2626'],
          ];
        } else {
          const topFunding = branches.slice().sort((a, b) => Number(b.simpanan || 0) - Number(a.simpanan || 0))[0];
          const topOs = branches.slice().sort((a, b) => Number(b.pinjaman || 0) - Number(a.pinjaman || 0))[0];
          const highestSml = branches.slice().sort((a, b) => Number(b.sml_pct || 0) - Number(a.sml_pct || 0))[0];
          const highestNpl = branches.slice().sort((a, b) => Number(b.npl_pct || 0) - Number(a.npl_pct || 0))[0];
          items = [
            ['fa-university', 'Dana terbesar', topFunding?.name, topFunding?.simpanan_fmt, '#0857c3'],
            ['fa-coins', 'OS terbesar', topOs?.name, topOs?.pinjaman_fmt, '#307fe2'],
            ['fa-triangle-exclamation', 'Rasio Kol 2 tertinggi', highestSml?.name, highestSml?.sml_pct_fmt, '#d97706'],
            ['fa-shield-halved', 'Rasio NPL tertinggi', highestNpl?.name, highestNpl?.npl_pct_fmt, '#dc2626'],
          ];
        }

        target.innerHTML = items.map(([icon, label, value, meta, tone]) => `
          <div class="pres-analysis-insight">
            <i class="fas ${icon}" style="background:${tone};"></i>
            <div><span>${escapeHtml(label || '-')}</span><strong>${escapeHtml(value || '-')}</strong><small>${escapeHtml(meta || '-')}</small></div>
          </div>
        `).join('');
      };

      const renderBranchAnalysis = () => {
        const branches = presentationData?.performance_overview?.branches || [];
        renderBranchBubbleExplanations(branches);
        renderSlideNarratives();
        if (branches.length === 0 || typeof Chart === 'undefined') return;

        const selectedScope = branchAnalysisState.scope;
        const focusLabel = selectedScope === 'all'
          ? 'Semua cabang'
          : branches.find(branch => String(branch.name || '').toUpperCase() === selectedScope)?.name || 'Semua cabang';
        setText('pres-funding-loan-focus', focusLabel);
        renderBranchAnalysisInsights(branches);

        const xAverage = sumNumeric(branches, 'simpanan') / branches.length;
        const yAverage = sumNumeric(branches, 'pinjaman') / branches.length;
        const osAverage = yAverage;
        const pointIsActive = branch => selectedScope === 'all' || String(branch.name || '').toUpperCase() === selectedScope;
        const fundingLoanCanvas = document.getElementById('pres-timeseries-chart-dana');

        if (fundingLoanCanvas) {
          if (timeseriesChartDana) timeseriesChartDana.destroy();
          timeseriesChartDana = new Chart(fundingLoanCanvas.getContext('2d'), {
            type: 'bubble',
            data: {
              datasets: [{
                label: 'Kantor Cabang',
                data: branches.map(branch => ({
                  x: Number(branch.simpanan || 0),
                  y: Number(branch.pinjaman || 0),
                  r: 9 + (Number(branch.pinjaman_contribution_pct || 0) / 5),
                  branch: branch.name,
                  funding: Number(branch.simpanan || 0),
                  os: Number(branch.pinjaman || 0),
                  active: pointIsActive(branch),
                })),
                backgroundColor: context => context.raw?.active ? 'rgba(8, 87, 195, 0.78)' : 'rgba(148, 163, 184, 0.24)',
                borderColor: context => context.raw?.active ? '#06469c' : '#94a3b8',
                borderWidth: context => context.raw?.active ? 2 : 1,
                hoverBackgroundColor: '#e61c24',
              }],
            },
            plugins: [branchQuadrantGuide],
            options: {
              responsive: true,
              maintainAspectRatio: false,
              animation: false,
              layout: { padding: { top: 8, right: 10, bottom: 2, left: 4 } },
              plugins: {
                legend: { display: false },
                branchQuadrantGuide: {
                  xAverage,
                  yAverage,
                  xLabel: 'Rata-rata Dana',
                  yLabel: 'Rata-rata OS',
                },
                tooltip: {
                  backgroundColor: 'rgba(255,255,255,0.98)',
                  titleColor: '#0f172a',
                  bodyColor: '#334155',
                  borderColor: '#b9cce4',
                  borderWidth: 1,
                  callbacks: {
                    title: items => items[0]?.raw?.branch || '-',
                    label: context => [
                      `Dana (X): ${formatCurrencyCompact(context.raw.funding)}`,
                      `OS (Y): ${formatCurrencyCompact(context.raw.os)}`,
                    ],
                  },
                },
              },
              scales: {
                x: {
                  title: { display: true, text: 'TOTAL DANA', color: '#475569', font: { size: 9, weight: '800' } },
                  grid: { color: 'rgba(148,163,184,0.16)' },
                  ticks: { color: '#64748b', font: { size: 8 }, callback: value => formatCurrencyCompact(value) },
                },
                y: {
                  title: { display: true, text: 'TOTAL OS', color: '#475569', font: { size: 9, weight: '800' } },
                  grid: { color: 'rgba(148,163,184,0.16)' },
                  ticks: { color: '#64748b', font: { size: 8 }, callback: value => formatCurrencyCompact(value) },
                },
              },
            },
          });
        }

        const qualityCanvas = document.getElementById('pres-timeseries-chart-quality');
        if (qualityCanvas) {
          if (timeseriesChartQuality) timeseriesChartQuality.destroy();
          const buckets = [
            { key: 'healthy', label: 'Kol 1 - Lancar', y: 1, color: '#10b981', amount: branch => Math.max(0, Number(branch.pinjaman || 0) - Number(branch.sml_abs || 0) - Number(branch.npl_abs || 0)), ratio: branch => Math.max(0, 100 - Number(branch.sml_pct || 0) - Number(branch.npl_pct || 0)) },
            { key: 'sml', label: 'Kol 2 - SML', y: 2, color: '#f59e0b', amount: branch => Number(branch.sml_abs || 0), ratio: branch => Number(branch.sml_pct || 0) },
            { key: 'npl', label: 'Kol 3-5 - NPL agregat', y: 4, color: '#ef4444', amount: branch => Number(branch.npl_abs || 0), ratio: branch => Number(branch.npl_pct || 0) },
          ];
          const maxBucket = Math.max(...buckets.flatMap(bucket => branches.map(branch => bucket.amount(branch))), 1);

          timeseriesChartQuality = new Chart(qualityCanvas.getContext('2d'), {
            type: 'bubble',
            data: {
              datasets: buckets.map(bucket => ({
                label: bucket.label,
                data: branches.map(branch => ({
                  x: Number(branch.pinjaman || 0),
                  y: bucket.y,
                  r: 5 + (Math.sqrt(bucket.amount(branch) / maxBucket) * 16),
                  branch: branch.name,
                  branchKey: String(branch.name || '').toUpperCase(),
                  os: Number(branch.pinjaman || 0),
                  amount: bucket.amount(branch),
                  ratio: bucket.ratio(branch),
                  bucket: bucket.key,
                })),
                backgroundColor: context => {
                  const active = selectedScope === 'all' || context.raw?.branchKey === selectedScope;
                  const alpha = active ? 'CC' : '35';
                  return bucket.color + alpha;
                },
                borderColor: bucket.color,
                borderWidth: context => selectedScope === 'all' || context.raw?.branchKey === selectedScope ? 2 : 1,
              })),
            },
            plugins: [qualityBandGuide, branchQuadrantGuide],
            options: {
              responsive: true,
              maintainAspectRatio: false,
              animation: false,
              layout: { padding: { top: 8, right: 10, bottom: 2, left: 4 } },
              plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 7, padding: 10, color: '#475569', font: { size: 8, weight: '700' } } },
                branchQuadrantGuide: {
                  xAverage: osAverage,
                  yAverage: 2.5,
                  xLabel: 'Rata-rata OS',
                  yLabel: 'Batas SML / NPL',
                },
                tooltip: {
                  backgroundColor: 'rgba(255,255,255,0.98)',
                  titleColor: '#0f172a',
                  bodyColor: '#334155',
                  borderColor: '#b9cce4',
                  borderWidth: 1,
                  callbacks: {
                    title: items => items[0]?.raw?.branch || '-',
                    label: context => {
                      const lines = [
                        `${context.dataset.label}: ${formatCurrencyCompact(context.raw.amount)}`,
                        `Porsi terhadap OS: ${formatPercent(context.raw.ratio)}`,
                        `Total OS (X): ${formatCurrencyCompact(context.raw.os)}`,
                      ];
                      if (context.raw.bucket === 'npl') lines.push('NPL tersedia agregat Kol 3-5');
                      return lines;
                    },
                  },
                },
              },
              scales: {
                x: {
                  title: { display: true, text: 'TOTAL OS', color: '#475569', font: { size: 9, weight: '800' } },
                  grid: { color: 'rgba(148,163,184,0.16)' },
                  ticks: { color: '#64748b', font: { size: 8 }, callback: value => formatCurrencyCompact(value) },
                },
                y: {
                  min: 0.5,
                  max: 5.5,
                  title: { display: true, text: 'KOLEKTIBILITAS', color: '#475569', font: { size: 9, weight: '800' } },
                  grid: { color: 'rgba(148,163,184,0.18)' },
                  ticks: {
                    stepSize: 1,
                    color: '#475569',
                    font: { size: 8, weight: '700' },
                    callback: value => ({ 1: 'Kol 1 Lancar', 2: 'Kol 2 SML', 3: 'Kol 3 NPL', 4: 'Kol 4 NPL', 5: 'Kol 5 NPL' }[value] || ''),
                  },
                },
              },
            },
          });
        }
      };

      const renderBranchWarRoom = (data) => {
        const branches = data?.performance_overview?.branches || [];
        const target = document.getElementById('pres-branch-war-room');
        if (!target || branches.length === 0) return;

        const branch = branches.find(item => String(item.name || '').toUpperCase() === branchDetailState.scope) || branches[0];
        branchDetailState.scope = String(branch.name || '').toUpperCase();
        const fundingAchievement = Number(branch.simpanan_target || 0) > 0 ? (Number(branch.simpanan || 0) / Number(branch.simpanan_target)) * 100 : 0;
        const osAchievement = Number(branch.pinjaman_target || 0) > 0 ? (Number(branch.pinjaman || 0) / Number(branch.pinjaman_target)) * 100 : 0;
        const healthyAmount = Math.max(0, Number(branch.pinjaman || 0) - Number(branch.sml_abs || 0) - Number(branch.npl_abs || 0));
        const healthyRatio = Math.max(0, 100 - Number(branch.sml_pct || 0) - Number(branch.npl_pct || 0));
        const fundingGap = Number(branch.simpanan || 0) - Number(branch.simpanan_target || 0);
        const osGap = Number(branch.pinjaman || 0) - Number(branch.pinjaman_target || 0);
        const fundingToOsGap = Number(branch.simpanan || 0) - Number(branch.pinjaman || 0);
        const intermediationRatio = Number(branch.simpanan || 0) > 0
          ? (Number(branch.pinjaman || 0) / Number(branch.simpanan)) * 100
          : 0;
        const atLeastOneTargetAchieved = fundingAchievement >= 100 || osAchievement >= 100;

        setText('pres-branch-focus-name', branch.name || '-');
        setText('pres-branch-funding-value', branch.simpanan_fmt || '-');
        setText('pres-branch-funding-ach', `RKA ${formatPercent(fundingAchievement)}`);
        setText('pres-branch-os-value', branch.pinjaman_fmt || '-');
        setText('pres-branch-os-ach', `RKA ${formatPercent(osAchievement)}`);
        setText('pres-branch-sml-value', branch.sml_abs_fmt || '-');
        setText('pres-branch-sml-ratio', branch.sml_pct_fmt || '-');
        setText('pres-branch-npl-value', branch.npl_abs_fmt || '-');
        setText('pres-branch-npl-ratio', branch.npl_pct_fmt || '-');
        setText('pres-branch-funding-target-label', `${branch.simpanan_fmt || '-'} / ${branch.simpanan_target_fmt || '-'}`);
        setText('pres-branch-os-target-label', `${branch.pinjaman_fmt || '-'} / ${branch.pinjaman_target_fmt || '-'}`);
        setText('pres-branch-healthy-ratio', formatPercent(healthyRatio));
        setText('pres-branch-healthy-value', formatCurrencyCompact(healthyAmount));
        setText('pres-branch-quality-sml-ratio', branch.sml_pct_fmt || '-');
        setText('pres-branch-quality-sml-value', branch.sml_abs_fmt || '-');
        setText('pres-branch-quality-npl-ratio', branch.npl_pct_fmt || '-');
        setText('pres-branch-quality-npl-value', branch.npl_abs_fmt || '-');
        setText('pres-branch-quality-period', data?.meta?.loan_period_label || data?.meta?.period_label || 'Posisi berjalan');
        setText('pres-branch-intermediation-ratio', formatPercent(intermediationRatio));
        setText('pres-branch-funding-position', signedCurrency(fundingToOsGap));
        setText('pres-branch-funding-position-label', fundingToOsGap >= 0 ? 'Surplus Dana terhadap OS' : 'Defisit Dana terhadap OS');

        const status = document.getElementById('pres-branch-rka-status');
        if (status) {
          status.textContent = `Dana ${formatPercent(fundingAchievement)} | OS ${formatPercent(osAchievement)}`;
          status.classList.toggle('achieved', atLeastOneTargetAchieved);
        }

        const fundingBar = document.getElementById('pres-branch-funding-target-bar');
        const osBar = document.getElementById('pres-branch-os-target-bar');
        if (fundingBar) {
          fundingBar.style.width = `${Math.min(100, Math.max(0, fundingAchievement))}%`;
          fundingBar.style.background = fundingAchievement >= 100 ? '#10b981' : '#0857c3';
        }
        if (osBar) {
          osBar.style.width = `${Math.min(100, Math.max(0, osAchievement))}%`;
          osBar.style.background = osAchievement >= 100 ? '#10b981' : '#307fe2';
        }

        const riskNominal = Number(branch.sml_abs || 0) + Number(branch.npl_abs || 0);
        const reading = `OS ${branch.pinjaman_fmt || '-'} menyumbang ${branch.pinjaman_contribution_pct_fmt || '-'} terhadap Area 6. `
          + `Dana berada ${fundingGap >= 0 ? 'di atas' : 'di bawah'} RKA sebesar ${formatCurrencyCompact(Math.abs(fundingGap))}, sedangkan OS ${osGap >= 0 ? 'di atas' : 'di bawah'} RKA sebesar ${formatCurrencyCompact(Math.abs(osGap))}. `
          + `Eksposur Kol 2 dan Kol 3-5 berjumlah ${formatCurrencyCompact(riskNominal)} atau ${formatPercent(Number(branch.sml_pct || 0) + Number(branch.npl_pct || 0))} dari OS.`;
        setText('pres-branch-business-reading', reading);

        const priorityList = document.getElementById('pres-branch-priority-list');
        if (priorityList) {
          const priorities = [
            fundingAchievement >= 100
              ? `Pertahankan buffer Dana di atas RKA ${formatCurrencyCompact(Math.abs(fundingGap))}.`
              : `Tutup gap Dana terhadap RKA ${formatCurrencyCompact(Math.abs(fundingGap))}.`,
            osAchievement >= 100
              ? `Jaga pertumbuhan OS sambil mempertahankan pencapaian ${formatPercent(osAchievement)}.`
              : `Akselerasi OS untuk menutup gap RKA ${formatCurrencyCompact(Math.abs(osGap))}.`,
            `Prioritaskan migrasi Kol 2 ${branch.sml_abs_fmt || '-'} dan recovery NPL ${branch.npl_abs_fmt || '-'}.`,
          ];
          priorityList.innerHTML = priorities.map(item => `<li>${escapeHtml(item)}</li>`).join('');
        }

        const items = [
          ['Kontribusi Dana', branch.simpanan_contribution_pct_fmt, branch.simpanan_fmt, '#0857c3'],
          ['Kontribusi OS', branch.pinjaman_contribution_pct_fmt, branch.pinjaman_fmt, '#307fe2'],
          ['Gap Dana vs RKA', signedCurrency(fundingGap), branch.simpanan_target_fmt, fundingGap >= 0 ? '#10b981' : '#dc2626'],
          ['Gap OS vs RKA', signedCurrency(osGap), branch.pinjaman_target_fmt, osGap >= 0 ? '#10b981' : '#dc2626'],
        ];
        target.innerHTML = items.map(([label, value, meta, tone]) => `
          <div class="pres-branch-action-item" style="--tone:${tone};">
            <span>${escapeHtml(label || '-')}</span><strong>${escapeHtml(value || '-')}</strong><small>Basis: ${escapeHtml(meta || '-')}</small>
          </div>
        `).join('');
        renderSlideNarratives();
      };

      const renderFinancialHighlights = (data) => {
        const financial = activeFinancial(data);
        setText('pres-financial-period', financial.period_label || '-');
        const financialCards = financial.cards || [];
        const cards = document.getElementById('pres-financial-cards');
        if (cards) {
          cards.innerHTML = financialCards.map(card => `
            <div class="bri-finance-card" style="--tone:${escapeHtml(card.tone || '#0857c3')};">
              <h3>${escapeHtml(card.label || '-')}</h3>
              <strong>${escapeHtml(card.value || '-')}</strong>
              <div class="metric-line"><span>Sumber</span><span>Almafacts</span></div>
            </div>
          `).join('');
        }
        const branches = document.getElementById('pres-financial-branches');
        if (branches) {
          const rows = [...(financial.branches || [])].sort((a, b) => Number(b.value_raw || 0) - Number(a.value_raw || 0));
          const max = Math.max(...rows.map(row => Math.abs(Number(row.value_raw || 0))), 1);
          branches.innerHTML = rows.map(row => `
            <div class="pres-profit-row">
              <div class="pres-profit-row-head">
                <span>${escapeHtml(row.name || '-')}</span>
                <strong style="color:${Number(row.value_raw || 0) < 0 ? '#dc2626' : '#047857'};">${escapeHtml(row.value || '-')}</strong>
              </div>
              <span class="pres-profit-bar-track"><span class="pres-profit-bar-fill" style="width:${Math.max(2, (Math.abs(Number(row.value_raw || 0)) / max) * 100)}%; background:${Number(row.value_raw || 0) < 0 ? '#dc2626' : '#0857c3'};"></span></span>
            </div>
          `).join('') || '<div class="pres-mini-stat"><span>Financial</span><strong>Data belum tersedia</strong></div>';
        }

        const brief = document.getElementById('pres-financial-brief');
        if (brief) {
          const cardMap = Object.fromEntries(financialCards.map(card => [card.key, card]));
          const branchRows = financial.branches || [];
          const profitableBranches = branchRows.filter(row => Number(row.value_raw || 0) > 0).length;
          const topBranch = [...branchRows].sort((a, b) => Number(b.value_raw || 0) - Number(a.value_raw || 0))[0];
          const totalProfit = Number(cardMap.profit_after_tax?.value_raw || 0);
          brief.innerHTML = `
            <div class="pres-section-eyebrow">Financial interpretation</div>
            <div class="pres-readout-item"><span>Bottom line ${escapeHtml(activeScopeLabel(data))}</span><strong style="color:${totalProfit < 0 ? '#dc2626' : '#047857'};">${escapeHtml(cardMap.profit_after_tax?.value || '-')}</strong><small>PPOP ${escapeHtml(cardMap.ppop?.value || '-')} menjadi basis daya hasil sebelum biaya risiko.</small></div>
            <div class="pres-readout-item"><span>Efisiensi dan margin</span><strong>NIM ${escapeHtml(cardMap.nim?.value || '-')} | BOPO ${escapeHtml(cardMap.bopo?.value || '-')}</strong><small>ROA After Tax berada pada ${escapeHtml(cardMap.roa_after_tax?.value || '-')}.</small></div>
            <div class="pres-readout-item"><span>Kontributor profit</span><strong>${escapeHtml(topBranch?.name || '-')}</strong><small>${profitableBranches} dari ${branchRows.length} cabang mencatat laba positif pada periode ini.</small></div>
            <div class="pres-readout-item"><span>Funding dan cost lens</span><strong>CASA ${escapeHtml(cardMap.casa?.value || '-')} | CER ${escapeHtml(cardMap.cer?.value || '-')}</strong><small>Struktur dana dan cost efficiency dibaca bersama profitabilitas periode aktif.</small></div>
          `;
        }
      };

      const renderQualityDeepDive = (data, type) => {
        const target = document.getElementById(type === 'sml' ? 'pres-sml-deep-grid' : 'pres-npl-deep-grid');
        if (!target) return;
        const groups = qualityGroupsForScope(data, type);

        target.classList.remove('pres-deep-grid--1', 'pres-deep-grid--2', 'pres-deep-grid--3', 'pres-deep-grid--4');
        target.classList.add(`pres-deep-grid--${Math.max(1, groups.length)}`);

        if (!groups.length) {
          target.innerHTML = '<div class="bri-inner-panel"><div class="pres-empty-state"><i class="fas fa-database"></i><strong>Data ranking belum tersedia</strong><p>Tidak ada baris nominal maupun rasio untuk periode dan scope presentasi ini.</p></div></div>';
          return;
        }

        target.innerHTML = groups.map(([title, rows]) => {
          const rankingRows = (rows || []).slice(0, 8);
          const maxValue = Math.max(...rankingRows.map(row => Number(row.value_raw ?? parseCompactNumber(row.value || row.value_fmt || row.amount_fmt || '0'))), 1);
          const isRatio = /rasio/i.test(title);
          return `
            <div class="bri-inner-panel pres-deep-ranking-panel">
              <div class="bri-blue-panel-title"><i class="fas fa-ranking-star"></i> ${escapeHtml(title)} <span style="margin-left:auto;">${rows.length} data</span></div>
              <div class="bri-panel-body pres-ranking-list">
                ${rankingRows.map((row, idx) => {
                  const display = row.value || row.value_fmt || row.amount_fmt || '-';
                  const numeric = Number(row.value_raw ?? parseCompactNumber(display));
                  const width = Math.max(5, Math.min(100, (numeric / maxValue) * 100));
                  return `
                    <article class="pres-ranking-item" style="--rank-tone:${isRatio ? '#ef4444' : '#f59e0b'};">
                      <span class="pres-ranking-number">${idx + 1}</span>
                      <div class="pres-ranking-copy"><strong>${escapeHtml(row.label || row.name || '-')}</strong><span class="pres-ranking-track"><i style="width:${width}%;"></i></span></div>
                      <b>${escapeHtml(display)}</b>
                    </article>
                  `;
                }).join('')}
              </div>
            </div>
          `;
        }).join('');
      };

      const renderMicroPdwk = () => {
        const panel = document.getElementById('pres-micro-pdwk-panel');
        const grid = document.getElementById('pres-micro-pdwk-grid');
        const summaryPanel = panel?.closest('.pres-productivity-summary-panel');
        const show = productivityState.category === 'micro';
        if (!panel || !grid) return null;

        panel.hidden = !show;
        summaryPanel?.classList.toggle('has-pdwk', show);
        if (!show) {
          grid.innerHTML = '';
          return null;
        }

        const pdwk = activeMicroPdwkScope();
        const total = pdwk?.total || {};
        const roles = Array.isArray(pdwk?.roles) ? pdwk.roles : [];
        const workingDays = Number(presentationData?.micro?.pdwk?.working_days || 0);
        const tones = ['#0857c3', '#0891b2', '#6d28d9'];

        setText('pres-micro-pdwk-total', `${total.os_fmt || '-'} | ${new Intl.NumberFormat('id-ID').format(Number(total.deb || 0))} debitur`);
        setText(
          'pres-micro-pdwk-context',
          `${pdwk?.boh_name || activeScopeLabel()} | ${new Intl.NumberFormat('id-ID').format(Number(total.jumlah_unit || 0))} unit | ${workingDays} hari kerja`
        );

        grid.innerHTML = roles.length
          ? roles.map((role, index) => `
              <article class="pres-micro-pdwk-role" style="--pdwk-tone:${tones[index] || '#0857c3'};">
                <span>${escapeHtml(role.label || '-')}</span>
                <em>${escapeHtml(role.share_pct_fmt || '-')} porsi</em>
                <strong>${escapeHtml(role.total_os_fmt || '-')} | ${new Intl.NumberFormat('id-ID').format(Number(role.total_deb || 0))} deb</strong>
                <div class="pres-micro-pdwk-breakdown">
                  <small>Sesuai PDWK<b>${escapeHtml(role.pdwk_os_fmt || '-')} | ${new Intl.NumberFormat('id-ID').format(Number(role.pdwk_deb || 0))}</b></small>
                  <small>Override<b>${escapeHtml(role.override_os_fmt || '-')} | ${new Intl.NumberFormat('id-ID').format(Number(role.override_deb || 0))}</b></small>
                </div>
              </article>
            `).join('')
          : '<div class="pres-empty-state"><strong>Rekap PDWK belum tersedia</strong><p>Tidak ada putusan pada scope aktif.</p></div>';

        return pdwk;
      };

      const renderProductivity = () => {
        const productivity = activeProductivityCategory();
        const total = productivity?.total || {};
        const rows = Array.isArray(productivity?.rows) ? productivity.rows : [];
        const categoryDefinitions = presentationData?.productivity?.categories || {};
        const categoryLabel = productivity?.label
          || categoryDefinitions?.[productivityState.category]?.label
          || 'Produktivitas RM';

        setText('pres-productivity-scope-label', activeScopeLabel());
        setText('pres-productivity-period', presentationData?.productivity?.period_label || '-');
        setText('pres-productivity-rm-count', new Intl.NumberFormat('id-ID').format(Number(total.rm_count || 0)));
        setText('pres-productivity-realization', total.realisasi_os_fmt || '-');
        setText('pres-productivity-debtors', `${new Intl.NumberFormat('id-ID').format(Number(total.realisasi_deb || 0))} debitur`);
        setText('pres-productivity-average-rm', total.average_per_rm_fmt || '-');
        setText('pres-productivity-ticket', total.average_ticket_fmt || '-');
        setText('pres-productivity-lar', total.lar_pct_fmt || '-');
        setText('pres-productivity-managed-os', `OS kelolaan ${total.loan_os_fmt || '-'}`);
        setText('pres-productivity-category-label', categoryLabel);
        const pdwk = renderMicroPdwk();

        const tbody = document.getElementById('pres-productivity-tbody');
        if (tbody) {
          tbody.innerHTML = rows.length
            ? rows.slice(0, 6).map((row, index) => `
              <tr>
                <td>${index + 1}</td>
                <td><strong>${escapeHtml(row.name || '-')}</strong><small>${escapeHtml([row.unit, row.branch].filter(Boolean).join(' - ') || '-')}</small></td>
                <td style="text-align:right;">${new Intl.NumberFormat('id-ID').format(Number(row.realisasi_deb || 0))}</td>
                <td style="text-align:right;"><strong>${escapeHtml(row.realisasi_os_fmt || '-')}</strong></td>
                <td style="text-align:right;">${escapeHtml(row.average_ticket_fmt || '-')}</td>
                <td style="text-align:right;">${escapeHtml(row.loan_os_fmt || '-')}</td>
                <td style="text-align:right;"><span class="pres-risk-value">${escapeHtml(row.lar_pct_fmt || '-')}</span></td>
              </tr>
            `).join('')
            : '<tr><td colspan="7"><div class="pres-empty-state"><strong>Data produktivitas RM belum tersedia</strong><p>Tidak ada RM pada kategori dan scope deck aktif.</p></div></td></tr>';
        }

        const leader = rows[0];
        const highestTicket = [...rows].sort((a, b) => Number(b.average_ticket || 0) - Number(a.average_ticket || 0))[0];
        const highestLar = [...rows].sort((a, b) => Number(b.lar_pct || 0) - Number(a.lar_pct || 0))[0];
        const insights = document.getElementById('pres-productivity-insights');
        if (insights) {
          insights.innerHTML = `
            <div><span>Kontributor terbesar</span><strong>${escapeHtml(leader?.name || '-')}</strong><small>${escapeHtml(leader?.realisasi_os_fmt || '-')} realisasi.</small></div>
            <div><span>Average ticket tertinggi</span><strong>${escapeHtml(highestTicket?.name || '-')}</strong><small>${escapeHtml(highestTicket?.average_ticket_fmt || '-')} per debitur.</small></div>
            <div class="is-risk"><span>LAR tertinggi</span><strong>${escapeHtml(highestLar?.name || '-')}</strong><small>${escapeHtml(highestLar?.lar_pct_fmt || '-')} dari OS kelolaan.</small></div>
          `;
        }

        const reading = document.getElementById('pres-productivity-reading');
        if (reading) {
          const concentration = Number(total.realisasi_os || 0) > 0 && leader
            ? (Number(leader.realisasi_os || 0) / Number(total.realisasi_os)) * 100
            : 0;
          const pdwkSentence = productivityState.category === 'micro' && pdwk?.total
            ? ` Rekap keputusan mencatat ${escapeHtml(pdwk.total.os_fmt || '-')} pada ${new Intl.NumberFormat('id-ID').format(Number(pdwk.total.deb || 0))} debitur melalui K Unit, MBM, dan BOH.`
            : '';
          reading.innerHTML = `
            <i class="fas fa-user-tie"></i>
            <p><strong>${escapeHtml(categoryLabel)}</strong> pada ${escapeHtml(activeScopeLabel())} menghasilkan ${escapeHtml(total.realisasi_os_fmt || '-')} dari ${new Intl.NumberFormat('id-ID').format(Number(total.rm_count || 0))} RM. Kontributor teratas menyumbang ${formatPercent(concentration)}; rasio LAR portofolio kelolaan berada di ${escapeHtml(total.lar_pct_fmt || '-')}.${
              pdwkSentence
            }</p>
          `;
        }

        const canvas = document.getElementById('pres-productivity-chart');
        if (productivityChart) {
          productivityChart.destroy();
          productivityChart = null;
        }
        if (canvas && rows.length && typeof Chart !== 'undefined') {
          const chartRows = rows.slice(0, 8).reverse();
          productivityChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
              labels: chartRows.map(row => row.name || '-'),
              datasets: [{
                label: 'Realisasi OS',
                data: chartRows.map(row => Number(row.realisasi_os || 0)),
                backgroundColor: chartRows.map((_row, index) => index === chartRows.length - 1 ? '#0857c3' : '#71c5e8'),
                borderRadius: 3,
                barThickness: 14,
              }],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              animation: false,
              indexAxis: 'y',
              layout: { padding: { right: 8 } },
              plugins: {
                legend: { display: false },
                tooltip: {
                  callbacks: {
                    label: context => `Realisasi ${formatCurrencyCompact(Number(context.raw || 0))}`,
                  },
                },
              },
              scales: {
                x: {
                  beginAtZero: true,
                  grid: { color: 'rgba(148,163,184,0.16)' },
                  ticks: { color: '#64748b', font: { size: 9 }, callback: value => formatCurrencyCompact(Number(value || 0)) },
                },
                y: {
                  grid: { display: false },
                  ticks: { color: '#334155', font: { size: 9, weight: '700' }, autoSkip: false },
                },
              },
            },
          });
        }

        setActiveButton(document.getElementById('pres-productivity-category-toggle'), 'data-productivity-category', productivityState.category);
        renderSlideNarratives();
      };

      const trendDeltaDisplay = (series, start, end) => {
        const delta = Number(end || 0) - Number(start || 0);
        if (series?.format === 'percent') {
          return `${delta >= 0 ? '+' : ''}${delta.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} pp`;
        }

        return signedCurrency(delta * 1000000);
      };

      const hideTrendTooltip = () => {
        const tooltip = document.getElementById('pres-trend-tooltip');
        if (!tooltip) return;
        tooltip.classList.remove('is-visible', 'is-below');
        tooltip.setAttribute('aria-hidden', 'true');
      };

      const renderTrendTooltip = (context, series, labels) => {
        const tooltipModel = context?.tooltip;
        const chart = context?.chart;
        const tooltip = document.getElementById('pres-trend-tooltip');
        const wrap = tooltip?.closest('.pres-trend-chart-wrap');
        if (!tooltip || !wrap || !chart || !tooltipModel || tooltipModel.opacity === 0) {
          hideTrendTooltip();
          return;
        }

        const pointIndex = Number(tooltipModel.dataPoints?.[0]?.dataIndex ?? -1);
        if (pointIndex < 0 || pointIndex >= labels.length) {
          hideTrendTooltip();
          return;
        }

        tooltip.innerHTML = `
          <div class="pres-trend-tooltip-head">
            <span>Posisi timeseries</span>
            <strong>${escapeHtml(labels[pointIndex] || '-')}</strong>
          </div>
          ${series.map(item => {
            const rawValue = Number(item?.values?.[pointIndex] || 0);
            const previousValue = pointIndex > 0 ? Number(item?.values?.[pointIndex - 1] || 0) : rawValue;
            const displayValue = item?.display_values?.[pointIndex]
              || (item?.format === 'percent' ? formatPercent(rawValue) : formatCurrencyCompact(rawValue * 1000000));
            const delta = pointIndex > 0 ? `${trendDeltaDisplay(item, previousValue, rawValue)} vs bulan lalu` : 'Titik awal rentang';
            return `
              <div class="pres-trend-tooltip-row">
                <i class="pres-trend-tooltip-dot" style="--tooltip-tone:${escapeHtml(item?.color || '#0857c3')};"></i>
                <div><span>${escapeHtml(item?.label || '-')}</span><small>${escapeHtml(delta)}</small></div>
                <strong>${escapeHtml(displayValue)}</strong>
              </div>
            `;
          }).join('')}
        `;

        const canvas = chart.canvas;
        const width = Math.min(242, Math.max(168, wrap.clientWidth - 16));
        const halfWidth = width / 2;
        const rawLeft = canvas.offsetLeft + Number(tooltipModel.caretX || 0);
        const left = Math.max(halfWidth + 6, Math.min(wrap.clientWidth - halfWidth - 6, rawLeft));
        const rawTop = canvas.offsetTop + Number(tooltipModel.caretY || 0);
        const showBelow = rawTop < tooltip.offsetHeight + 24;

        tooltip.style.width = `${width}px`;
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${Math.max(8, rawTop)}px`;
        tooltip.classList.toggle('is-below', showBelow);
        tooltip.classList.add('is-visible');
        tooltip.setAttribute('aria-hidden', 'false');
      };

      const renderTrendLab = () => {
        const timeseries = presentationData?.timeseries || {};
        const scope = activeTrendScope();
        const groups = timeseries?.groups || {};
        if (!groups[trendState.group]) {
          trendState.group = Object.keys(groups)[0] || 'business';
        }
        const group = groups?.[trendState.group] || {};
        const keys = Array.isArray(group.keys) ? group.keys : [];
        const series = keys.map(key => scope?.series?.[key]).filter(Boolean);
        const labels = Array.isArray(scope?.labels) ? scope.labels : [];

        const toggle = document.getElementById('pres-trend-group-toggle');
        if (toggle) {
          toggle.innerHTML = Object.entries(groups).map(([key, definition]) => `
            <button type="button" class="pres-toggle-btn ${key === trendState.group ? 'active' : ''}" data-trend-group="${escapeHtml(key)}">${escapeHtml(definition.label || key)}</button>
          `).join('');
        }

        setText('pres-trend-scope-label', activeScopeLabel());
        setText('pres-trend-group-title', group.label || 'Timeseries');
        setText('pres-trend-group-description', group.description || 'Pergerakan indikator pada scope aktif.');
        setText('pres-trend-period-range', labels.length ? `${labels[0]} - ${labels[labels.length - 1]}` : '-');
        setText('pres-trend-point-count', `${labels.length} titik data`);

        const kpiGrid = document.getElementById('pres-trend-kpi-grid');
        if (kpiGrid) {
          kpiGrid.innerHTML = series.map(item => {
            const values = item.values || [];
            const start = Number(values[0] || 0);
            const end = Number(values[values.length - 1] || 0);
            return `
              <article style="--series-tone:${escapeHtml(item.color || '#0857c3')};">
                <span>${escapeHtml(item.label || '-')}</span>
                <strong>${escapeHtml(item.display_values?.[values.length - 1] || '-')}</strong>
                <small>${escapeHtml(trendDeltaDisplay(item, start, end))} dari titik awal</small>
              </article>
            `;
          }).join('') || '<div class="pres-empty-state"><strong>Timeseries belum tersedia</strong></div>';
        }

        const thead = document.getElementById('pres-trend-thead');
        const tbody = document.getElementById('pres-trend-tbody');
        const recentStart = Math.max(0, labels.length - 7);
        if (thead) {
          thead.innerHTML = `
            <tr>
              <th>Periode</th>
              ${series.map(item => `<th style="text-align:right;"><span class="pres-series-key" style="--series-tone:${escapeHtml(item.color || '#0857c3')};"></span>${escapeHtml(item.label || '-')}</th>`).join('')}
            </tr>
          `;
        }
        if (tbody) {
          tbody.innerHTML = labels.slice(recentStart).map((label, offset) => {
            const valueIndex = recentStart + offset;
            return `
              <tr>
                <td><strong>${escapeHtml(label || '-')}</strong></td>
                ${series.map(item => `<td style="text-align:right;" class="${valueIndex === labels.length - 1 ? 'is-latest' : ''}">${escapeHtml(item.display_values?.[valueIndex] || '-')}</td>`).join('')}
              </tr>
            `;
          }).join('') || `<tr><td colspan="${Math.max(2, series.length + 1)}" style="text-align:center;">Data belum tersedia</td></tr>`;
        }

        const rankedChanges = series.map(item => {
          const values = item.values || [];
          const start = Number(values[0] || 0);
          const end = Number(values[values.length - 1] || 0);
          return {
            item,
            start,
            end,
            change: start !== 0 ? ((end / start) - 1) * 100 : 0,
          };
        }).sort((a, b) => b.change - a.change);
        const strongest = rankedChanges[0];
        const weakest = rankedChanges[rankedChanges.length - 1];
        const reading = document.getElementById('pres-trend-reading');
        if (reading) {
          reading.innerHTML = `
            <div><span>Momentum terkuat</span><strong>${escapeHtml(strongest?.item?.label || '-')}</strong><small>${strongest ? `${strongest.change >= 0 ? '+' : ''}${formatPercent(strongest.change)}` : '-'} selama rentang tampil.</small></div>
            <div><span>Perlu perhatian</span><strong>${escapeHtml(weakest?.item?.label || '-')}</strong><small>${weakest ? `${weakest.change >= 0 ? '+' : ''}${formatPercent(weakest.change)}` : '-'} selama rentang tampil.</small></div>
            <div><span>Basis analisis</span><strong>${labels.length} bulan</strong><small>Posisi terakhir setiap bulan, mengikuti scope deck.</small></div>
          `;
        }

        const canvas = document.getElementById('pres-trend-chart');
        hideTrendTooltip();
        if (trendLabChart) {
          trendLabChart.destroy();
          trendLabChart = null;
        }
        if (canvas && series.length && labels.length && typeof Chart !== 'undefined') {
          const hasCurrency = series.some(item => item.format !== 'percent');
          const hasPercent = series.some(item => item.format === 'percent');
          trendLabChart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
              labels,
              datasets: series.map(item => ({
                label: item.label,
                data: item.values || [],
                borderColor: item.color || '#0857c3',
                backgroundColor: item.color || '#0857c3',
                yAxisID: item.format === 'percent' ? 'y1' : 'y',
                borderWidth: 2.4,
                pointRadius: 2.5,
                pointHoverRadius: 4,
                tension: 0.3,
                fill: false,
              })),
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              animation: false,
              interaction: { mode: 'index', intersect: false },
              plugins: {
                legend: {
                  position: 'top',
                  align: 'start',
                  labels: { usePointStyle: true, boxWidth: 7, padding: 12, color: '#475569', font: { size: 9, weight: '700' } },
                },
                tooltip: {
                  enabled: false,
                  external: context => renderTrendTooltip(context, series, labels),
                },
              },
              scales: {
                x: {
                  grid: { display: false },
                  ticks: { color: '#64748b', font: { size: 8 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 13 },
                },
                y: {
                  display: hasCurrency,
                  position: 'left',
                  grid: { color: 'rgba(148,163,184,0.16)' },
                  ticks: { color: '#64748b', font: { size: 8 }, callback: value => formatCurrencyCompact(Number(value || 0) * 1000000) },
                },
                y1: {
                  display: hasPercent,
                  position: 'right',
                  grid: { drawOnChartArea: !hasCurrency, color: 'rgba(148,163,184,0.12)' },
                  ticks: { color: '#64748b', font: { size: 8 }, callback: value => formatPercent(Number(value || 0)) },
                },
              },
            },
          });
        }

        renderSlideNarratives();
      };

      const renderMicroProductivity = (data) => {
        const grid = document.getElementById('pres-micro-productivity-grid');
        if (!grid) return;
        const micro = data?.micro || {};
        const cards = [
          ['Putusan', micro?.decision?.total?.total_os_fmt || '-', (micro?.decision?.total?.total_deb || 0) + ' deb'],
          ['Mantri', micro?.mantri_productivity?.total?.realisasi_os_fmt || '-', (micro?.mantri_productivity?.total?.jumlah_mantri || 0) + ' mantri'],
          ['RM KUR Mikro', micro?.rm_kur_micro?.total?.realisasi_os_fmt || '-', (micro?.rm_kur_micro?.total?.realisasi_deb || 0) + ' deb'],
        ];
        grid.innerHTML = cards.map(([label, value, meta]) => `
          <div class="pres-mini-stat" style="margin-top:0.65rem;">
            <span>${escapeHtml(label)}</span>
            <strong>${escapeHtml(value)}</strong>
            <small style="color:#64748b; font-weight:800;">${escapeHtml(meta)}</small>
          </div>
        `).join('');
      };

      const populatePresentationData = (data, section = null) => {
        presentationData = data;
        const options = deckScopeOptions(data);
        if (!options.some(option => option.key === deckState.scope)) {
          deckState.scope = data?.scope?.default || options[0]?.key || 'area6';
        }
        performanceState.scope = deckState.scope;
        segmentState.scope = deckState.scope;
        riskState.scope = deckState.scope;
        branchAnalysisState.scope = deckState.scope === 'area6' ? 'all' : deckState.scope;
        if (deckState.scope !== 'area6') {
          branchDetailState.scope = deckState.scope;
        }

        populateGlobalScopeControls(data);
        syncPrognosaControls(data);
        syncExportScopeControls(data);
        if (structuredDeckEnabled) {
          if (section) {
            structuredDeck.renderSection(section, data);
          } else {
            structuredDeck.render(data);
          }
          return;
        }
        populateCover(data);
        populatePerformanceControls(data);
        populateBranchPresentationControls(data);
        renderExecutiveLoanDashboard(data);
        renderSavingsDashboard(data);
        renderBranchWarRoom(data);
        renderFinancialHighlights(data);
        renderQualityDeepDive(data, 'sml');
        renderQualityDeepDive(data, 'npl');
        renderMicroProductivity(data);
        renderProductivity();
        renderTrendLab();

        // Shared branch data
        const scopedSummaryCards = activeSummary(data)?.cards || [];
        const simpCard = scopedSummaryCards.find(c => c.key === 'simpanan') || {};
        const branches = scopedBranchRows(data);
        const dpkTarget = sumNumeric(branches, 'simpanan_target');
        const dpkRaw = Number(simpCard.value_raw || 0);

        // Slide 3: Kredit Performance
        const osCard = scopedSummaryCards.find(c => c.key === 'os') || {};
        const totalVolumeEl = document.getElementById('pres-kredit-total-volume');
        totalVolumeEl.textContent = osCard.value || 'Rp -';
        totalVolumeEl.setAttribute('data-raw-val', osCard.value_raw || 0);
        totalVolumeEl.setAttribute('data-is-currency', 'true');
        const loanTrendEl = document.getElementById('pres-kredit-total-trend');
        loanTrendEl.className = 'pres-kpi-sub-trend ' + (osCard.trend && osCard.trend.startsWith('-') ? 'neg' : 'pos');
        loanTrendEl.innerHTML = `<i class="fas ${osCard.trend && osCard.trend.startsWith('-') ? 'fa-arrow-down' : 'fa-arrow-up'} mr-1"></i> ${osCard.trend || '0%'} MtM`;

        const kreditTarget = sumNumeric(branches, 'pinjaman_target');
        const kreditRaw = Number(osCard.value_raw || 0);
        document.getElementById('pres-kredit-rka').textContent = kreditTarget > 0 ? formatCurrencyCompact(kreditTarget) : 'Data belum tersedia';
        document.getElementById('pres-kredit-achievement').textContent = kreditTarget > 0 && kreditRaw > 0 ? formatPercent((kreditRaw / kreditTarget) * 100) : 'Data belum tersedia';

        // Kredit Branch Table Shares
        const kreditBranchSharesTbody = document.getElementById('pres-kredit-branch-shares-tbody');
        kreditBranchSharesTbody.innerHTML = '';
        branches.forEach(b => {
          const tr = document.createElement('tr');
          const achievementColor = colorForAchievement(b.pinjaman_share_fmt);
          tr.innerHTML = `
            <td style="font-weight:700;">${escapeHtml(String(b.name || '-').toUpperCase())}</td>
            <td style="text-align:right; font-weight:600;">${escapeHtml(displayValue(b.pinjaman_fmt))}</td>
            <td style="text-align:right; font-weight:600; color:#475569;">${escapeHtml(displayValue(b.pinjaman_target_fmt))}</td>
            <td style="text-align:right; font-weight:800; color:${achievementColor};">${escapeHtml(displayValue(b.pinjaman_share_fmt))}</td>
            <td style="text-align:right; font-weight:700; color:#0857c3;">${escapeHtml(displayValue(b.pinjaman_contribution_pct_fmt))}</td>
          `;
          kreditBranchSharesTbody.appendChild(tr);
        });

        // Slide 3: Segment outstanding (OS) interactive workspace
        populateSegmentControls(data);
        renderSegmentExplorer();

        // Slide 5: Risk metrics (SML, NPL, LAR) workspace
        populateRiskControls(data);
        renderRiskOverview();

        // Comparative Quality Table
        const qualityComparisonTbody = document.getElementById('pres-quality-comparison-tbody');
        qualityComparisonTbody.innerHTML = '';

        let totalOsArea = 0;
        let totalSmlArea = 0;
        let totalNplArea = 0;
        let totalLarArea = 0;

        const qualityBranches = scopedBranchRows(data);
        qualityBranches.forEach(b => {
          const osVal = b.pinjaman || 0;
          const smlVal = b.sml_abs || 0;
          const nplVal = b.npl_abs || 0;
          const larVal = b.lar_abs || (smlVal + nplVal);
          const larPctVal = b.lar_pct || (osVal > 0 ? (larVal / osVal) * 100 : 0);

          totalOsArea += osVal;
          totalSmlArea += smlVal;
          totalNplArea += nplVal;
          totalLarArea += larVal;

          const tr = document.createElement('tr');
          tr.style.borderBottom = '1px solid rgba(0, 0, 0, 0.05)';
          tr.innerHTML = `
            <td style="font-weight:700; padding: 0.65rem 0.8rem; color: #1d1d1f;">${escapeHtml(displayValue(b.name))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:600; color: #1d1d1f;">${escapeHtml(displayValue(b.pinjaman_fmt))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:500; color: #b45309;">${escapeHtml(displayValue(b.sml_abs_fmt))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:700; color: #b45309;">${escapeHtml(displayValue(b.sml_pct_fmt, '0,00%'))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:500; color: #ef4444;">${escapeHtml(displayValue(b.npl_abs_fmt))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:700; color: #ef4444;">${escapeHtml(displayValue(b.npl_pct_fmt, '0,00%'))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:500; color: #0071e3;">${escapeHtml(displayValue(b.lar_abs_fmt))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:700; color: #0071e3;">${escapeHtml(displayValue(b.lar_pct_fmt, '0,00%'))}</td>
          `;
          qualityComparisonTbody.appendChild(tr);
        });

        // Add a total row at the bottom!
        const totalLarPctVal = totalOsArea > 0 ? (totalLarArea / totalOsArea) * 100 : 0;
        const totalSmlPctVal = totalOsArea > 0 ? (totalSmlArea / totalOsArea) * 100 : 0;
        const totalNplPctVal = totalOsArea > 0 ? (totalNplArea / totalOsArea) * 100 : 0;

        const totalTr = document.createElement('tr');
        totalTr.style.background = 'rgba(0, 113, 227, 0.05)';
        totalTr.style.fontWeight = 'bold';
        totalTr.innerHTML = `
          <td style="padding: 0.7rem 0.8rem; color: #0071e3; font-weight:800; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">TOTAL ${escapeHtml(activeScopeLabel(data).toUpperCase())}</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #1d1d1f;">${formatCurrencyCompact(totalOsArea)}</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #b45309;">${formatCurrencyCompact(totalSmlArea)}</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #b45309;">${totalSmlPctVal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}%</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #ef4444;">${formatCurrencyCompact(totalNplArea)}</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #ef4444;">${totalNplPctVal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}%</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #0071e3;">${formatCurrencyCompact(totalLarArea)}</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #0071e3; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">${totalLarPctVal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}%</td>
        `;
        qualityComparisonTbody.appendChild(totalTr);

        // Slide 5: KTS
        renderKts();
        if (data?.kts?.loading_details) {
          window.setTimeout(loadPresentationKts, 500);
        }

        // Slide 6: 8 Digital Strategy Grid
        const digGrid = document.getElementById('pres-digital-cards-grid');
        digGrid.innerHTML = '';
        const digCards = data.digital_strategy.cards || [];
        digCards.forEach(c => {
          const cardDiv = document.createElement('div');
          cardDiv.className = 'pres-glass-card';
          cardDiv.style.display = 'flex';
          cardDiv.style.flexDirection = 'column';
          cardDiv.style.justifyContent = 'space-between';
          cardDiv.style.padding = '1.25rem';

          const isCasa = c.key === 'casa';
          const trendIsNeg = c.trend && c.trend.includes('-') && !isCasa;
          const trendColor = trendIsNeg ? '#ef4444' : '#047857';

          cardDiv.innerHTML = `
            <div>
              <div style="font-size:0.7rem; font-weight:700; color:rgba(0,0,0,0.4); text-transform:uppercase; letter-spacing:0.05em; display:flex; justify-content:space-between; align-items:center;">
                <span>${escapeHtml(c.title || '-')}</span>
                <i class="fas fa-chart-line" style="color:rgba(0,0,0,0.25);"></i>
              </div>
              <div style="font-weight:800; margin:0.35rem 0;" class="pres-text-gradient-silver pres-digital-value">${escapeHtml(c.current_value || '-')}</div>
              ${c.secondary_value && c.secondary_value !== '-' ? `<div style="font-size:0.72rem; color:rgba(0,0,0,0.55); font-weight:500;">Vol: <strong>${escapeHtml(c.secondary_value)}</strong></div>` : ''}
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid rgba(0,0,0,0.05); padding-top:0.5rem; margin-top:0.5rem; font-size:0.72rem;">
              <span style="color:rgba(0,0,0,0.4); font-size:0.68rem; font-weight:500;">Growth</span>
              <span style="color:${trendColor}; font-weight:700;">${escapeHtml(c.trend || '-')}</span>
            </div>
          `;
          digGrid.appendChild(cardDiv);
        });
        renderDigitalTableAndChart();
        renderBranchBubbleExplanations(allBranchRows(data));
        renderSlideNarratives(data);
      };

      const syncExportScopeControls = (data = presentationData) => {
        const option = activeScopeOption(data);
        const exportValue = option.key === 'area6' ? 'area6' : option.label;
        const form = document.querySelector('#ppt-export-dialog form');
        if (!form) return;

        ['global_scope', 'funding_scope', 'sme_scope', 'consumer_scope'].forEach(name => {
          const select = form.querySelector(`[name="${name}"]`);
          if (select && Array.from(select.options).some(item => item.value === exportValue)) {
            select.value = exportValue;
          }
        });

        const title = form.querySelector('[name="title"]');
        if (title && title.dataset.userEdited !== 'true') {
            title.value = option.key === 'area6'
            ? 'Performance Review - Area 6 Region 13'
            : `Performance Review - ${option.label} Region 13`;
        }

        const exportPrognosa = form.querySelector('#pres-export-use-prognosa');
        if (exportPrognosa) {
          exportPrognosa.checked = deckState.usePrognosa;
          exportPrognosa.disabled = !Boolean(data?.comparison?.prognosa?.available);
        }
      };

      const syncPrognosaControls = (data = presentationData) => {
        const meta = data?.comparison?.prognosa || {};
        const available = Boolean(meta.available);
        if (!available) {
          deckState.usePrognosa = false;
        }

        if (presPrognosaToggle) {
          presPrognosaToggle.checked = deckState.usePrognosa;
          presPrognosaToggle.disabled = !available;
        }
        if (presPrognosaControl) {
          presPrognosaControl.classList.toggle('is-active', available && deckState.usePrognosa);
          presPrognosaControl.classList.toggle('is-disabled', !available);
          presPrognosaControl.title = available
            ? `${meta.label || 'Prognosa'} posisi ${meta.forecast_date_label || '-'}; pembanding ${meta.comparison_position_label || meta.position_date_label || '-'}.`
            : 'Prognosa Weekly belum tersedia.';
        }
        if (presPrognosaState) {
          presPrognosaState.textContent = available
            ? (deckState.usePrognosa
              ? `${meta.week_label || 'Aktif'} | ${meta.forecast_date_label || '-'}`
              : 'Nonaktif')
            : 'Tidak tersedia';
        }

        const exportPrognosa = document.getElementById('pres-export-use-prognosa');
        if (exportPrognosa) {
          exportPrognosa.checked = deckState.usePrognosa;
          exportPrognosa.disabled = !available;
        }
        const exportNote = document.getElementById('pres-export-prognosa-note');
        if (exportNote) {
          exportNote.textContent = available
            ? `${meta.label || 'Prognosa'} ${meta.forecast_date_label || '-'} dibanding posisi ${meta.comparison_position_label || meta.position_date_label || '-'}.`
            : 'Prognosa Weekly belum tersedia pada payload ini.';
        }
      };

      const applyDeckScope = (scope) => {
        if (!presentationData) return;
        const options = deckScopeOptions();
        const next = options.find(option => option.key === scope);
        if (!next) return;

        deckState.scope = next.key;
        performanceState.scope = next.key;
        segmentState.scope = next.key;
        riskState.scope = next.key;
        branchAnalysisState.scope = next.key === 'area6' ? 'all' : next.key;
        if (next.key !== 'area6') {
          branchDetailState.scope = next.key;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('scope', next.key);
        window.history.replaceState({}, '', url);

        populatePresentationData(presentationData);
        syncExportScopeControls();
        showSlide(currentSlideIndex);
      };

      const applyPrognosaMode = (enabled) => {
        if (!presentationData) return;
        const available = Boolean(presentationData?.comparison?.prognosa?.available);
        deckState.usePrognosa = available && Boolean(enabled);

        const url = new URL(window.location.href);
        url.searchParams.set('prognosa', deckState.usePrognosa ? '1' : '0');
        window.history.replaceState({}, '', url);

        syncPrognosaControls();
        syncExportScopeControls();
        if (structuredDeckEnabled) {
          structuredDeck.render(presentationData);
        }
        showSlide(currentSlideIndex);
      };

      // Load Presentation Data
      const selectedPeriod = presentationConfig.selectedPeriod || '';
      const presentationKtsDataUrl = presentationConfig.ktsDataUrl || '';

      const updateCoverKtsTotals = () => {
        if (!presentationData?.kts || presentationData.kts.loading_details) return;

        const totalMembaik = Number(scopedKtsPayload('membaik', 'ritel')?.total_count || 0)
          + Number(scopedKtsPayload('membaik', 'micro')?.total_count || 0);
        const totalMemburuk = Number(scopedKtsPayload('memburuk', 'ritel')?.total_count || 0)
          + Number(scopedKtsPayload('memburuk', 'micro')?.total_count || 0);
        const totalKts = totalMembaik + totalMemburuk;

        const coverKts = document.getElementById('pres-cover-kts');
        if (coverKts) coverKts.textContent = new Intl.NumberFormat('id-ID').format(totalKts) + ' rek';

        const coverCards = document.querySelectorAll('#pres-cover-board .pres-cover-card');
        if (coverCards[7]) {
          const value = coverCards[7].querySelector('.value');
          if (value) value.textContent = new Intl.NumberFormat('id-ID').format(totalMembaik) + ' rek';
        }
        if (coverCards[8]) {
          const value = coverCards[8].querySelector('.value');
          if (value) value.textContent = new Intl.NumberFormat('id-ID').format(totalMemburuk) + ' rek';
        }
      };

      const loadPresentationKts = () => {
        if (!presentationData || !presentationData?.kts?.loading_details) {
          return Promise.resolve(presentationData?.kts || null);
        }

        if (ktsLoadPromise) return ktsLoadPromise;

        const url = new URL(presentationKtsDataUrl, window.location.origin);
        const ktsPeriod = presentationData?.meta?.daily_loan_period || selectedPeriod;
        if (ktsPeriod) {
          url.searchParams.set('periode', ktsPeriod);
        }

        ktsLoadPromise = fetch(url.toString(), {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
          cache: 'no-store',
        })
          .then(response => {
            if (!response.ok) {
              throw new Error(`Failed to load KTS data (${response.status})`);
            }
            return response.json();
          })
          .then(data => {
            presentationData.kts = data?.kts || data || presentationData.kts;
            updateCoverKtsTotals();
            if (structuredDeckEnabled) {
              structuredDeck.activate(currentSlideIndex);
            } else {
              renderKts();
            }
            return presentationData.kts;
          })
          .catch(err => {
            console.error(err);
            if (structuredDeckEnabled) {
              presentationData.kts = {
                ...(presentationData.kts || {}),
                loading_details: false,
                load_error: true,
              };
            } else {
              const note = document.getElementById('pres-kts-note');
              const tbody = document.getElementById('pres-kts-tbody');
              if (note) note.textContent = 'Detail KTS belum berhasil dimuat. Data utama presentasi tetap dapat digunakan.';
              if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem; color:#ef4444;">Gagal memuat detail KTS</td></tr>';
            }
            ktsLoadPromise = null;
            return null;
          });

        return ktsLoadPromise;
      };

      const loadPresentation = async () => {
        const progressBar = document.getElementById('loading-progress-bar');
        const progressPercent = document.getElementById('loading-progress-percent');
        const progressStatus = document.getElementById('dashboard-loading-status');
        const deferredSectionKeys = ['micro', 'productivity', 'timeseries', 'digital'];
        let currentProgress = 0;
        let firstPayloadRendered = false;
        let lastRenderedPayload = null;

        const syncDeferredSkeletons = (data) => {
          const ready = new Set(deferredSectionKeys.filter((section) => {
            const key = section === 'digital' ? 'digital_strategy' : section;
            const value = data?.[key];
            return value && value.loading !== true;
          }));

          document.querySelectorAll('[data-progressive-section]').forEach((slide) => {
            const required = String(slide.dataset.progressiveSection || '')
              .split(/\s+/)
              .filter(Boolean);
            const pending = required.some(section => !ready.has(section));
            slide.classList.toggle('is-section-loading', pending);
            slide.setAttribute('aria-busy', pending ? 'true' : 'false');
          });
        };

        const setProgress = (value, text = null) => {
          currentProgress = Math.max(currentProgress, Math.min(100, value));
          if (progressBar) progressBar.style.width = currentProgress + '%';
          if (progressPercent) progressPercent.textContent = Math.round(currentProgress) + '%';
          if (text && progressStatus) progressStatus.textContent = text;
        };

        const finishInitialRender = () => {
          const loader = document.getElementById('dashboard-global-loader');
          loader?.classList.remove('active');
          presMode?.classList.add('is-progressive-ready');
          showSlide(requestedSlideIndex);
          schedulePresentationFit();
        };

        const renderPayload = (data, { initial = false, section = null } = {}) => {
          if (!data || data === lastRenderedPayload) return;
          lastRenderedPayload = data;
          populatePresentationData(data, section);
          syncDeferredSkeletons(data);

          if (!firstPayloadRendered || initial) {
            firstPayloadRendered = true;
            requestAnimationFrame(finishInitialRender);
          } else {
            showSlide(currentSlideIndex);
          }
        };

        showSlide(0);
        setProgress(8, 'Cover siap. Menyiapkan ringkasan data...');
        requestAnimationFrame(() => {
          document.getElementById('dashboard-global-loader')?.classList.remove('active');
        });

        try {
          await presentationDataLoader.load({
            onStatus(status) {
              const statusMap = {
                'server-cache': [82, 'Cache server siap. Merender seluruh slide...'],
                'offline-cache': [30, 'Cache offline ditemukan. Memperbarui data terbaru...'],
                'cache-warming': [18, 'Worker sedang menyusun cache presentasi...'],
                'cache-refreshing': [24, 'Data tersimpan ditampilkan. Pembaruan terbaru berjalan...'],
                'summary-ready': [42, 'Ringkasan siap. Menyusun detail analisis di latar belakang...'],
                summary: [42, 'Memuat ringkasan eksekutif...'],
                'detail:micro': [58, 'Memuat detail mikro dan KTS...'],
                'detail:productivity': [68, 'Memuat produktivitas RM...'],
                'detail:timeseries': [78, 'Memuat timeseries dan tren...'],
                'detail:digital': [88, 'Memuat strategi digital...'],
                'offline-fallback': [96, 'Jaringan tidak tersedia. Menggunakan cache offline...'],
              };
              const [value, message] = statusMap[status] || [currentProgress, null];
              setProgress(value, message);
            },
            onSummary(data, meta) {
              renderPayload(data, { initial: !firstPayloadRendered });
              if (meta?.stale) {
                const isOffline = String(meta?.source || '').includes('offline');
                presMode?.classList.toggle('is-offline-data', isOffline);
                presMode?.classList.toggle('is-stale-data', !isOffline);
              }
            },
            onSection(section, data) {
              renderPayload(data, { section });
            },
            onComplete(data, meta) {
              renderPayload(data, { initial: !firstPayloadRendered });
              const isOffline = String(meta?.source || '').includes('offline');
              presMode?.classList.toggle('is-offline-data', isOffline);
              presMode?.classList.toggle('is-stale-data', Boolean(meta?.stale) && !isOffline);
              presMode?.classList.add('is-data-complete');
              setProgress(
                100,
                meta?.source === 'offline-fallback'
                  ? 'Mode offline aktif.'
                  : (meta?.stale ? 'Data tersimpan siap; pembaruan masih diproses.' : 'Semua slide siap.'),
              );
            },
          });
        } catch (err) {
          const loader = document.getElementById('dashboard-global-loader');
          if (loader) loader.classList.remove('active');
          console.error(err);
          const message = err.name === 'AbortError'
            ? 'Request data presentasi melewati 30 detik. Silakan coba lagi setelah cache dashboard selesai terbentuk.'
            : err.message;
          alert('Gagal mengambil data presentasi: ' + message);
        }
      };

      // Initialize
      void loadPresentation();
      presentationInteractions = setupPresentationInteractions({
        root: presMode,
        config: presentationConfig,
        dataLoader: presentationDataLoader,
        getData: () => presentationData,
        getSlideIndex: () => currentSlideIndex,
        getTotalSlides: () => totalSlides,
        getScope: () => deckState.scope,
        showSlide,
        toggleAutoplay: () => {
          if (autoplayEnabled) {
            stopAutoplay();
          } else {
            startAutoplay();
          }
        },
      });

      const branchAnalysisSelector = document.getElementById('pres-branch-analysis-selector');
      if (branchAnalysisSelector) {
        branchAnalysisSelector.addEventListener('change', () => {
          branchAnalysisState.scope = branchAnalysisSelector.value || 'all';
          renderBranchAnalysis();
        });
      }

      const branchDetailSelector = document.getElementById('pres-branch-detail-selector');
      if (branchDetailSelector) {
        branchDetailSelector.addEventListener('change', () => {
          branchDetailState.scope = branchDetailSelector.value || branchDetailState.scope;
          renderBranchWarRoom(presentationData);
        });
      }

      const metricToggle = document.getElementById('pres-metric-toggle');
      if (metricToggle) {
        metricToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-metric]');
          if (!btn) return;
          performanceState.metric = btn.getAttribute('data-metric');
          setActiveButton(metricToggle, 'data-metric', performanceState.metric);
          renderPerformanceExplorer();
        });
      }

      const scopeSelect = document.getElementById('pres-scope-select');
      if (scopeSelect) {
        scopeSelect.addEventListener('change', () => {
          applyDeckScope(scopeSelect.value || 'area6');
        });
      }

      const viewToggle = document.getElementById('pres-view-toggle');
      if (viewToggle) {
        viewToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-view]');
          if (!btn) return;
          performanceState.view = btn.getAttribute('data-view');
          setActiveButton(viewToggle, 'data-view', performanceState.view);
          renderPerformanceExplorer();
        });
      }

      const segMetricToggle = document.getElementById('pres-seg-metric-toggle');
      if (segMetricToggle) {
        segMetricToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-seg-metric]');
          if (!btn) return;
          segmentState.metric = btn.getAttribute('data-seg-metric');
          setActiveButton(segMetricToggle, 'data-seg-metric', segmentState.metric);

          // Update Title
          const titleEl = document.getElementById('pres-seg-explorer-title');
          if (segmentState.metric === 'sme_os') titleEl.textContent = 'OS SME';
          else if (segmentState.metric === 'consumer_os') titleEl.textContent = 'OS KONSUMER';
          else if (segmentState.metric === 'micro_os') titleEl.textContent = 'OS MIKRO';

          renderSegmentExplorer();
        });
      }

      const segScopeSelect = document.getElementById('pres-seg-scope-select');
      if (segScopeSelect) {
        segScopeSelect.addEventListener('change', () => {
          applyDeckScope(segScopeSelect.value || 'area6');
        });
      }

      const riskScopeSelect = document.getElementById('pres-risk-scope-select');
      if (riskScopeSelect) {
        riskScopeSelect.addEventListener('change', () => {
          applyDeckScope(riskScopeSelect.value || 'area6');
        });
      }

      const globalScopeSelector = document.getElementById('pres-global-scope-selector');
      if (globalScopeSelector) {
        globalScopeSelector.addEventListener('change', () => {
          applyDeckScope(globalScopeSelector.value || 'area6');
        });
      }

      if (presPrognosaToggle) {
        presPrognosaToggle.addEventListener('change', () => {
          applyPrognosaMode(presPrognosaToggle.checked);
        });
      }

      const exportGlobalScope = document.getElementById('pres-export-global-scope');
      if (exportGlobalScope) {
        exportGlobalScope.addEventListener('change', () => {
          const value = exportGlobalScope.value || 'area6';
          const option = deckScopeOptions().find(item => item.key === value || item.label === value);
          applyDeckScope(option?.key || 'area6');
        });
      }

      const exportUsePrognosa = document.getElementById('pres-export-use-prognosa');
      if (exportUsePrognosa) {
        exportUsePrognosa.addEventListener('change', () => {
          applyPrognosaMode(exportUsePrognosa.checked);
        });
      }

      const exportTitleInput = document.querySelector('#ppt-export-dialog [name="title"]');
      if (exportTitleInput) {
        exportTitleInput.addEventListener('input', () => {
          exportTitleInput.dataset.userEdited = 'true';
        });
      }

      const ktsCategoryToggle = document.getElementById('pres-kts-category-toggle');
      if (ktsCategoryToggle) {
        ktsCategoryToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-kts-category]');
          if (!btn) return;
          ktsState.category = btn.getAttribute('data-kts-category');
          setActiveButton(ktsCategoryToggle, 'data-kts-category', ktsState.category);
          renderKts();
        });
      }

      const ktsScopeToggle = document.getElementById('pres-kts-scope-toggle');
      if (ktsScopeToggle) {
        ktsScopeToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-kts-scope]');
          if (!btn) return;
          ktsState.scope = btn.getAttribute('data-kts-scope');
          setActiveButton(ktsScopeToggle, 'data-kts-scope', ktsState.scope);
          renderKts();
        });
      }

      const productivityCategoryToggle = document.getElementById('pres-productivity-category-toggle');
      if (productivityCategoryToggle) {
        productivityCategoryToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-productivity-category]');
          if (!btn) return;
          productivityState.category = btn.getAttribute('data-productivity-category') || 'retail_sme';
          setActiveButton(productivityCategoryToggle, 'data-productivity-category', productivityState.category);
          renderProductivity();
        });
      }

      const trendGroupToggle = document.getElementById('pres-trend-group-toggle');
      if (trendGroupToggle) {
        trendGroupToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-trend-group]');
          if (!btn) return;
          trendState.group = btn.getAttribute('data-trend-group') || 'business';
          renderTrendLab();
        });
      }

      const digitalViewToggle = document.getElementById('pres-digital-view-toggle');
      if (digitalViewToggle) {
        digitalViewToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-digital-view]');
          if (!btn) return;
          digitalState.view = btn.getAttribute('data-digital-view');
          setActiveButton(digitalViewToggle, 'data-digital-view', digitalState.view);
          renderDigitalTableAndChart();
        });
      }

      // Start slideshow trigger
      if (presStartBtn) {
        presStartBtn.addEventListener('click', () => {
          showSlide(1);
          startAutoplay();
        });
      }

      if (presFinishBtn) {
        presFinishBtn.addEventListener('click', () => {
          stopAutoplay();
          window.location.href = presentationConfig.dashboardUrl || "/dashboard";
        });
      }

      if (presAutoplayBtn) {
        presAutoplayBtn.addEventListener('click', () => {
          if (autoplayEnabled) {
            stopAutoplay();
          } else {
            startAutoplay();
          }
        });
      }

      // Navigations
      if (presPrevBtn) {
        presPrevBtn.addEventListener('click', () => {
          if (currentSlideIndex > 0) {
            showSlide(currentSlideIndex - 1);
            scheduleAutoplay();
          }
        });
      }

      if (presNextBtn) {
        presNextBtn.addEventListener('click', () => {
          if (currentSlideIndex < totalSlides - 1) {
            showSlide(currentSlideIndex + 1);
            scheduleAutoplay();
          }
        });
      }

      // Dots
      if (presDots) {
        presDots.addEventListener('click', (e) => {
          const dot = e.target.closest('.pres-dot');
          if (dot) {
            const idx = parseInt(dot.getAttribute('data-index'));
            if (!isNaN(idx)) {
              showSlide(idx);
              scheduleAutoplay();
            }
          }
        });
      }

      // Keyboard navigation
      document.addEventListener('keydown', (e) => {
        const openDialog = document.querySelector('dialog[open]');
        if (openDialog) {
          if (e.key === 'Escape') {
            e.preventDefault();
            openDialog.close();
          }
          return;
        }

        const isInteractiveTarget = e.target instanceof Element
          && Boolean(e.target.closest('input, select, textarea, button, [contenteditable="true"], [data-psd-timeseries-expand]'));

        if (isInteractiveTarget && e.key !== 'Escape') return;

        if (e.key === 'ArrowRight' || e.key === ' ') {
          e.preventDefault();
          if (currentSlideIndex < totalSlides - 1) {
            showSlide(currentSlideIndex + 1);
            scheduleAutoplay();
          }
        } else if (e.key === 'ArrowLeft') {
          e.preventDefault();
          if (currentSlideIndex > 0) {
            showSlide(currentSlideIndex - 1);
            scheduleAutoplay();
          }
        } else if (e.key === 'Home') {
          e.preventDefault();
          showSlide(0);
          scheduleAutoplay();
        } else if (e.key === 'End') {
          e.preventDefault();
          showSlide(totalSlides - 1);
          scheduleAutoplay();
        } else if (e.key.toLowerCase() === 'a') {
          e.preventDefault();
          if (autoplayEnabled) {
            stopAutoplay();
          } else {
            startAutoplay();
          }
        } else if (e.key.toLowerCase() === 'f') {
          e.preventDefault();
          if (document.fullscreenElement) {
            document.exitFullscreen?.();
          } else {
            document.documentElement.requestFullscreen?.();
          }
        } else if (e.key === 'Escape') {
          e.preventDefault();
          if (document.fullscreenElement) {
            document.exitFullscreen?.();
            return;
          }
          stopAutoplay();
          // Redirect back to dashboard
          window.location.href = presentationConfig.dashboardUrl || "/dashboard";
        }
      });
    });
