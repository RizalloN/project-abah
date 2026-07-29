const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const numeric = (value) => Number.isFinite(Number(value)) ? Number(value) : 0;

const summaryCardsForScope = (data, scope) => {
    const scoped = data?.summary?.scopes?.[scope];
    return scoped?.cards || data?.summary?.cards || [];
};

const comparisonMetrics = (data, scope) => {
    const summaryCards = summaryCardsForScope(data, scope);
    const summaryMap = Object.fromEntries(summaryCards.map((card) => [card.key, card]));
    const savingsCards = data?.savings_breakdown?.scopes?.[scope]?.cards
        || data?.savings_breakdown?.cards
        || [];
    const savingsMap = Object.fromEntries(savingsCards.map((card) => [card.key, card]));
    const financialCards = data?.financial_highlights?.scopes?.[scope]?.cards
        || data?.financial_highlights?.cards
        || [];
    const financialMap = Object.fromEntries(financialCards.map((card) => [card.key, card]));

    return [
        {
            key: 'simpanan',
            label: 'Simpanan',
            display: summaryMap.simpanan?.value || '-',
            raw: numeric(summaryMap.simpanan?.value_raw),
            inverse: false,
        },
        {
            key: 'os',
            label: 'Outstanding',
            display: summaryMap.os?.value || '-',
            raw: numeric(summaryMap.os?.value_raw),
            inverse: false,
        },
        {
            key: 'sml',
            label: 'Rasio SML',
            display: summaryMap.sml?.ratio || summaryMap.sml?.value || '-',
            raw: numeric(summaryMap.sml?.ratio_raw),
            inverse: true,
        },
        {
            key: 'npl',
            label: 'Rasio NPL',
            display: summaryMap.npl?.ratio || summaryMap.npl?.value || '-',
            raw: numeric(summaryMap.npl?.ratio_raw),
            inverse: true,
        },
        {
            key: 'casa',
            label: 'CASA',
            display: savingsMap.casa?.pct || savingsMap.casa?.value || '-',
            raw: numeric(savingsMap.casa?.pct_raw),
            inverse: false,
        },
        {
            key: 'profit',
            label: 'Laba Setelah Pajak',
            display: financialMap.laba_setelah_pajak?.value || financialCards[0]?.value || '-',
            raw: numeric(financialMap.laba_setelah_pajak?.value_raw || financialCards[0]?.value_raw),
            inverse: false,
        },
    ];
};

const deltaFor = (current, previous, inverse) => {
    if (!previous) {
        return { text: 'Pembanding 0', className: '' };
    }

    const change = ((current / previous) - 1) * 100;
    const positive = inverse ? change <= 0 : change >= 0;
    return {
        text: `${change >= 0 ? '+' : ''}${new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(change)}%`,
        className: positive ? '' : 'negative',
    };
};

export const setupPresentationInteractions = ({
    root,
    config,
    dataLoader,
    getData,
    getSlideIndex,
    getTotalSlides,
    getScope,
    showSlide,
    toggleAutoplay,
}) => {
    const compareDialog = document.getElementById('pres-compare-dialog');
    const comparePeriod = document.getElementById('pres-compare-period');
    const compareContent = document.getElementById('pres-compare-content');
    const drilldownDialog = document.getElementById('pres-drilldown-dialog');
    const drilldownTitle = document.getElementById('pres-drilldown-title');
    const drilldownContent = document.getElementById('pres-drilldown-content');
    const noteDialog = document.getElementById('pres-note-dialog');
    const noteTitle = document.getElementById('pres-note-title');
    const noteText = document.getElementById('pres-note-text');
    const noteButton = document.getElementById('pres-note-btn');
    const themeButton = document.getElementById('pres-theme-btn');
    const toast = document.getElementById('pres-toast');
    const exportForm = document.getElementById('pres-export-form');
    const exportProgress = document.getElementById('pres-export-progress');
    const exportProgressTitle = document.getElementById('pres-export-progress-title');
    const exportProgressMessage = document.getElementById('pres-export-progress-message');
    const exportProgressValue = document.getElementById('pres-export-progress-value');
    const exportProgressBar = document.getElementById('pres-export-progress-bar');
    const exportProgressIcon = document.getElementById('pres-export-progress-icon');
    const exportSubmit = document.getElementById('pres-export-submit');
    const periods = Array.isArray(config.periods) ? config.periods : [];
    let comparePayload = null;
    let compareValue = '';
    let toastTimer = null;
    let pointerStart = null;
    let exportPollTimer = null;
    let exportInProgress = false;

    const notify = (message) => {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('visible');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => toast.classList.remove('visible'), 2200);
    };

    const hashState = () => new URLSearchParams(window.location.hash.replace(/^#/, ''));

    const syncUrl = (slideIndex = getSlideIndex()) => {
        const hash = hashState();
        hash.set('slide', String(slideIndex));
        hash.set('scope', getScope());
        if (compareDialog?.open && compareValue) {
            hash.set('compare', compareValue);
        } else {
            hash.delete('compare');
        }

        const next = `${window.location.pathname}${window.location.search}#${hash.toString()}`;
        window.history.replaceState({}, '', next);
    };

    const noteStorageKey = () => [
        'presentation-note',
        config.selectedPeriod || 'latest',
        getScope(),
        getSlideIndex(),
    ].join(':');

    const refreshNoteIndicator = () => {
        const hasNote = Boolean(localStorage.getItem(noteStorageKey()));
        noteButton?.classList.toggle('has-note', hasNote);
    };

    const openNote = () => {
        const slideNumber = getSlideIndex() + 1;
        if (noteTitle) noteTitle.textContent = `Catatan Slide ${slideNumber}`;
        if (noteText) noteText.value = localStorage.getItem(noteStorageKey()) || '';
        noteDialog?.showModal();
    };

    const renderComparison = (current, previous, previousPeriod) => {
        if (!compareContent) return;
        const scope = getScope();
        const currentMetrics = comparisonMetrics(current, scope);
        const previousMetrics = comparisonMetrics(previous, scope);
        const previousMap = Object.fromEntries(previousMetrics.map((metric) => [metric.key, metric]));
        const currentPeriod = current?.meta?.period_label || current?.meta?.period || config.selectedPeriod || '-';
        const previousLabel = previous?.meta?.period_label || previous?.meta?.period || previousPeriod;

        const panel = (title, metrics, currentPanel) => `
          <section class="pres-compare-period-panel">
            <header><span>${escapeHtml(title)}</span><strong>${escapeHtml(scope === 'area6' ? 'Area 6 Konsol' : scope)}</strong></header>
            <div class="pres-compare-metrics">
              ${metrics.map((metric) => {
                const previousMetric = previousMap[metric.key];
                const delta = currentPanel
                    ? deltaFor(metric.raw, previousMetric?.raw || 0, metric.inverse)
                    : null;
                return `
                  <div class="pres-compare-metric">
                    <span>${escapeHtml(metric.label)}</span>
                    <strong>${escapeHtml(metric.display)}</strong>
                    ${delta ? `<small class="pres-compare-delta ${delta.className}">${escapeHtml(delta.text)} vs pembanding</small>` : ''}
                  </div>
                `;
              }).join('')}
            </div>
          </section>
        `;

        compareContent.innerHTML = `
          <div class="pres-compare-grid">
            ${panel(currentPeriod, currentMetrics, true)}
            ${panel(previousLabel, previousMetrics, false)}
          </div>
        `;
    };

    const loadComparison = async (period = comparePeriod?.value) => {
        if (!period || !compareContent) return;
        compareValue = period;
        compareContent.innerHTML = '<div class="pres-drilldown-reading"><span>Memuat pembanding</span><p>Mengambil payload periode terpilih...</p></div>';
        syncUrl();

        try {
            comparePayload = await dataLoader.loadPeriod(period);
            renderComparison(getData(), comparePayload, period);
        } catch (error) {
            compareContent.innerHTML = `<div class="pres-drilldown-reading"><span>Data tidak tersedia</span><p>${escapeHtml(error.message)}</p></div>`;
        }
    };

    const openCompare = async (period = '') => {
        const selected = config.selectedPeriod || '';
        if (comparePeriod && !comparePeriod.options.length) {
            periods
                .filter((item) => String(item) !== selected)
                .forEach((item) => comparePeriod.add(new Option(String(item), String(item))));
        }

        const fallback = periods.find((item) => String(item) !== selected) || '';
        const nextPeriod = period && periods.includes(period) ? period : fallback;
        if (comparePeriod && nextPeriod) comparePeriod.value = nextPeriod;
        compareDialog?.showModal();
        if (nextPeriod) await loadComparison(nextPeriod);
    };

    const drilldownFacts = (element) => {
        const values = Array.from(element.querySelectorAll('strong, b, td'))
            .map((node) => node.textContent.trim())
            .filter(Boolean)
            .slice(0, 3);
        const title = element.dataset.drilldownTitle
            || element.querySelector('strong, b, td')?.textContent?.trim()
            || `Slide ${getSlideIndex() + 1}`;
        const narrative = document.getElementById(`pres-slide-narrative-text-${getSlideIndex()}`)?.textContent?.trim()
            || getData()?.narrative?.slides?.[getSlideIndex()]?.body
            || '-';

        return { title, values, narrative };
    };

    const openDrilldown = (element) => {
        const { title, values, narrative } = drilldownFacts(element);
        if (drilldownTitle) drilldownTitle.textContent = title;
        if (drilldownContent) {
            drilldownContent.innerHTML = `
              <div class="pres-drilldown-summary">
                ${(values.length ? values : ['Data aktif']).map((value, index) => `
                  <div><span>${index === 0 ? 'Posisi' : `Indikator ${index + 1}`}</span><strong>${escapeHtml(value)}</strong></div>
                `).join('')}
              </div>
              <div class="pres-drilldown-reading"><span>Pembacaan</span><p>${escapeHtml(narrative)}</p></div>
            `;
        }
        drilldownDialog?.showModal();
    };

    const refreshDrilldowns = () => {
        const selectors = [
            '#pres-saving-cards > *',
            '#pres-loan-product-rows > *',
            '#pres-loan-product-bars > *',
            '#pres-kredit-branch-shares-tbody > tr',
            '#pres-loan-product-table > tr',
            '#pres-loan-composition-legend > *',
            '#pres-branch-war-room > *',
            '#pres-financial-branches > *',
            '#pres-explorer-tbody > tr',
            '#pres-segment-tbody > tr',
            '#pres-risk-tbody > tr',
            '#pres-productivity-tbody > tr',
        ];

        document.querySelectorAll(selectors.join(',')).forEach((element) => {
            element.dataset.presDrilldown = 'true';
            element.tabIndex = 0;
            element.setAttribute('role', 'button');
        });
    };

    const exportErrorMessage = (payload, fallback) => {
        const firstValidationError = Object.values(payload?.errors || {}).flat().find(Boolean);
        return String(firstValidationError || payload?.message || fallback || 'Ekspor tidak dapat diproses.');
    };

    const updateExportProgress = (payload = {}) => {
        if (!exportProgress) return;
        const status = payload.status || 'queued';
        const progress = Math.max(0, Math.min(100, Number(payload.progress || 0)));
        const labels = {
            queued: 'Menunggu worker',
            processing: 'Menyusun PowerPoint',
            completed: 'PowerPoint siap',
            failed: 'Ekspor gagal',
        };
        const icons = {
            queued: 'fa-clock',
            processing: 'fa-cog fa-spin',
            completed: 'fa-check',
            failed: 'fa-exclamation-triangle',
        };

        exportProgress.hidden = false;
        exportProgress.classList.toggle('is-completed', status === 'completed');
        exportProgress.classList.toggle('is-failed', status === 'failed');
        if (exportProgressTitle) exportProgressTitle.textContent = labels[status] || 'Memproses ekspor';
        if (exportProgressMessage) exportProgressMessage.textContent = payload.message || 'Menyiapkan data presentasi.';
        if (exportProgressValue) exportProgressValue.textContent = `${progress}%`;
        if (exportProgressBar) exportProgressBar.style.width = `${progress}%`;
        if (exportProgressIcon) exportProgressIcon.innerHTML = `<i class="fas ${icons[status] || 'fa-layer-group'}" aria-hidden="true"></i>`;
    };

    const requestJson = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': config.csrfToken || '',
                ...(options.headers || {}),
            },
            ...options,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(exportErrorMessage(payload, `HTTP ${response.status}`));
        }

        return payload;
    };

    const triggerExportDownload = (payload) => {
        if (!payload.download_url) return;
        const link = document.createElement('a');
        link.href = payload.download_url;
        link.download = payload.filename || 'Performance-Review.pptx';
        link.rel = 'noopener';
        document.body.appendChild(link);
        link.click();
        link.remove();
    };

    const pollExport = async (statusUrl, attempt = 0) => {
        try {
            const payload = await requestJson(statusUrl);
            updateExportProgress(payload);
            if (payload.status === 'completed') {
                exportInProgress = false;
                if (exportSubmit) exportSubmit.disabled = false;
                triggerExportDownload(payload);
                notify(`${payload.slide_count || 13} slide PowerPoint selesai dibuat.`);
                return;
            }
            if (payload.status === 'failed') {
                exportInProgress = false;
                if (exportSubmit) exportSubmit.disabled = false;
                notify(payload.message || 'Ekspor PowerPoint gagal.');
                return;
            }
            if (attempt >= 240) {
                exportInProgress = false;
                if (exportSubmit) exportSubmit.disabled = false;
                updateExportProgress({
                    status: 'failed',
                    progress: payload.progress || 30,
                    message: 'Worker belum menyelesaikan ekspor. Status tetap tersimpan; periksa worker reports-low.',
                });
                return;
            }
            exportPollTimer = window.setTimeout(() => pollExport(statusUrl, attempt + 1), 1500);
        } catch (error) {
            if (attempt < 4) {
                exportPollTimer = window.setTimeout(() => pollExport(statusUrl, attempt + 1), 1800);
                return;
            }
            exportInProgress = false;
            if (exportSubmit) exportSubmit.disabled = false;
            updateExportProgress({ status: 'failed', progress: 100, message: error.message });
            notify(error.message);
        }
    };

    exportForm?.addEventListener('submit', async (event) => {
        if (!config.exportStartUrl) return;
        event.preventDefault();
        if (exportInProgress) return;

        exportInProgress = true;
        window.clearTimeout(exportPollTimer);
        if (exportSubmit) exportSubmit.disabled = true;
        updateExportProgress({
            status: 'queued',
            progress: 3,
            message: 'Mengirim konfigurasi deck ke antrean ekspor.',
        });

        try {
            const payload = await requestJson(config.exportStartUrl, {
                method: 'POST',
                body: new FormData(exportForm),
            });
            updateExportProgress(payload);
            await pollExport(payload.status_url);
        } catch (error) {
            exportInProgress = false;
            if (exportSubmit) exportSubmit.disabled = false;
            updateExportProgress({ status: 'failed', progress: 100, message: error.message });
            notify(error.message);
        }
    });

    document.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });

    document.getElementById('pres-compare-btn')?.addEventListener('click', () => openCompare());
    document.getElementById('pres-compare-refresh')?.addEventListener('click', () => loadComparison());
    compareDialog?.addEventListener('close', () => {
        compareValue = '';
        syncUrl();
    });

    noteButton?.addEventListener('click', openNote);
    document.getElementById('pres-note-save')?.addEventListener('click', () => {
        const value = noteText?.value.trim() || '';
        if (value) {
            localStorage.setItem(noteStorageKey(), value);
        } else {
            localStorage.removeItem(noteStorageKey());
        }
        refreshNoteIndicator();
        noteDialog?.close();
        notify('Catatan slide disimpan pada perangkat ini.');
    });
    document.getElementById('pres-note-clear')?.addEventListener('click', () => {
        localStorage.removeItem(noteStorageKey());
        if (noteText) noteText.value = '';
        refreshNoteIndicator();
        notify('Catatan slide dihapus.');
    });

    document.getElementById('pres-share-btn')?.addEventListener('click', async () => {
        syncUrl();
        try {
            await navigator.clipboard.writeText(window.location.href);
            notify('Tautan slide aktif disalin.');
        } catch {
            notify('Tautan siap pada address bar.');
        }
    });

    themeButton?.addEventListener('click', () => {
        const active = root.classList.toggle('manual-dark');
        themeButton.classList.toggle('active', active);
        showSlide(getSlideIndex());
    });

    document.getElementById('pres-print-btn')?.addEventListener('click', () => window.print());

    document.addEventListener('click', (event) => {
        const target = event.target.closest('[data-pres-drilldown]');
        if (target && !event.target.closest('button, a, select, input, textarea')) {
            openDrilldown(target);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.target.matches('input, textarea, select')) return;
        if (event.key.toLowerCase() === 'c') {
            event.preventDefault();
            openCompare();
        } else if (event.key.toLowerCase() === 'n') {
            event.preventDefault();
            openNote();
        } else if (event.key.toLowerCase() === 'd') {
            event.preventDefault();
            themeButton?.click();
        } else if (event.key === 'Enter' && event.target.matches('[data-pres-drilldown]')) {
            openDrilldown(event.target);
        }
    });

    const slideSurface = document.querySelector('.pres-slides-container');
    slideSurface?.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' || event.target.closest('button, a, select, input, textarea, [data-pres-drilldown], [data-psd-timeseries-expand]')) {
            return;
        }
        pointerStart = {
            x: event.clientX,
            y: event.clientY,
            time: performance.now(),
        };
    });
    slideSurface?.addEventListener('pointerup', (event) => {
        if (!pointerStart) return;
        const deltaX = event.clientX - pointerStart.x;
        const deltaY = event.clientY - pointerStart.y;
        const duration = performance.now() - pointerStart.time;
        pointerStart = null;

        if (Math.abs(deltaX) > 60 && Math.abs(deltaX) > Math.abs(deltaY) * 1.25) {
            const direction = deltaX < 0 ? 1 : -1;
            showSlide(Math.max(0, Math.min(getTotalSlides() - 1, getSlideIndex() + direction)));
            return;
        }
        if (Math.abs(deltaX) < 8 && Math.abs(deltaY) < 8 && duration < 420) {
            toggleAutoplay();
        }
    });

    const requestedHash = hashState();
    const hashSlide = Number.parseInt(requestedHash.get('slide') || '', 10);
    if (Number.isInteger(hashSlide)) {
        showSlide(Math.max(0, Math.min(getTotalSlides() - 1, hashSlide)));
    }
    const requestedCompare = requestedHash.get('compare');
    if (requestedCompare) {
        openCompare(requestedCompare);
    }

    refreshNoteIndicator();
    refreshDrilldowns();
    syncUrl();

    return {
        syncSlide(index) {
            syncUrl(index);
            refreshNoteIndicator();
            refreshDrilldowns();
        },
        refreshDrilldowns,
        notify,
    };
};
