document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('.content-wrapper');
    if (!root) {
        return;
    }

    const wrapperSelector = [
        '.abah-table-scroll',
        '.table-responsive',
        '.table-container',
        '.kinerja-table-container',
        '[class*="table-wrap"]',
        '[class*="table-scroll"]'
    ].join(',');
    const skipSelector = [
        '[data-abah-no-table-guard]',
        '.no-abah-table-guard',
        '.select2-results',
        '.fc',
        '.fc-scrollgrid'
    ].join(',');
    let syncFrame = null;
    let floatingFrame = null;
    let floatingGeometryDirty = true;
    let activeFloatingTable = null;
    let activeFloatingClone = null;
    const pendingEnhancementRoots = new Set();
    const freezeTableHeaders = document.body.dataset.abahTableFreeze === 'on';
    const mainHeader = document.querySelector('.main-header');
    let floatingHeader = null;
    let floatingTrack = null;

    const shouldSkipTable = function (table) {
        return table.closest(skipSelector)
            || table.closest('.dropdown-menu')
            || table.closest('.note-editor')
            || table.classList.contains('dataTable')
            || table.dataset.abahNoTableGuard === '1';
    };

    const ensureWrapper = function (table) {
        const existingWrapper = table.closest(wrapperSelector);
        if (existingWrapper) {
            return existingWrapper;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'abah-table-scroll';
        wrapper.dataset.abahAutoTableScroll = '1';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);

        return wrapper;
    };

    const syncScrollableWrapper = function (table, wrapper) {
        if (!wrapper) {
            return;
        }

        const isHorizontallyScrollable = table.scrollWidth > wrapper.clientWidth + 2;
        wrapper.classList.toggle('abah-table-scrollable-x', isHorizontallyScrollable);

        if (!isHorizontallyScrollable) {
            if (wrapper.dataset.abahManagedTabindex === '1') {
                wrapper.removeAttribute('tabindex');
                delete wrapper.dataset.abahManagedTabindex;
            }
            if (wrapper.dataset.abahManagedRole === '1') {
                wrapper.removeAttribute('role');
                delete wrapper.dataset.abahManagedRole;
            }
            if (wrapper.dataset.abahManagedLabel === '1') {
                wrapper.removeAttribute('aria-label');
                delete wrapper.dataset.abahManagedLabel;
            }
            return;
        }

        if (!wrapper.hasAttribute('tabindex')) {
            wrapper.setAttribute('tabindex', '0');
            wrapper.dataset.abahManagedTabindex = '1';
        }

        if (!wrapper.hasAttribute('role')) {
            wrapper.setAttribute('role', 'region');
            wrapper.dataset.abahManagedRole = '1';
        }

        if (!wrapper.hasAttribute('aria-label')) {
            const caption = table.caption?.textContent?.replace(/\s+/g, ' ').trim();
            const cardTitle = table.closest('.card')?.querySelector('.card-title')?.textContent?.replace(/\s+/g, ' ').trim();
            const label = caption || cardTitle || 'data';
            wrapper.setAttribute('aria-label', `Tabel ${label}. Geser horizontal untuk melihat kolom lainnya.`);
            wrapper.dataset.abahManagedLabel = '1';
        }
    };

    const syncHeaderOffsets = function (table) {
        const rows = table.tHead ? Array.from(table.tHead.rows) : [];
        let top = 0;

        rows.forEach(function (row) {
            Array.from(row.cells).forEach(function (cell) {
                cell.style.setProperty('--abah-table-head-top', top + 'px');
            });

            top += Math.ceil(row.getBoundingClientRect().height || 0);
        });

        const wrapper = table.closest(wrapperSelector);
        if (wrapper) {
            wrapper.style.setProperty('--abah-table-header-height', top + 'px');
        }
    };

    const hasVisibleBackground = function (style) {
        if (style.backgroundImage && style.backgroundImage !== 'none') {
            return true;
        }

        const color = String(style.backgroundColor || '').replace(/\s+/g, '').toLowerCase();
        return color !== ''
            && color !== 'transparent'
            && color !== 'rgba(0,0,0,0)'
            && !color.endsWith(',0)');
    };

    const syncStickyHeaderSurfaces = function (table) {
        if (!table.tHead) {
            return;
        }

        Array.from(table.tHead.querySelectorAll('th')).forEach(function (cell) {
            cell.classList.remove('abah-sticky-surface');
            cell.style.removeProperty('--abah-sticky-surface');

            if (hasVisibleBackground(window.getComputedStyle(cell))) {
                return;
            }

            let surface = cell.parentElement;
            let background = '';
            while (surface && surface !== root) {
                const surfaceStyle = window.getComputedStyle(surface);
                if (hasVisibleBackground(surfaceStyle)) {
                    background = surfaceStyle.backgroundColor;
                    break;
                }
                surface = surface.parentElement;
            }

            cell.style.setProperty('--abah-sticky-surface', background || '#ffffff');
            cell.classList.add('abah-sticky-surface');
        });
    };

    const syncHorizontalStickyLayers = function (table) {
        table.querySelectorAll('th, td').forEach(function (cell) {
            const style = window.getComputedStyle(cell);
            const left = Number.parseFloat(style.left);
            const right = Number.parseFloat(style.right);
            const isHorizontalSticky = style.position === 'sticky'
                && (Number.isFinite(left) || Number.isFinite(right));

            cell.classList.toggle('abah-table-sticky-x', isHorizontalSticky);

            if (!isHorizontalSticky) {
                if (cell.dataset.abahManagedStickyLayer === '1') {
                    const originalValue = cell.dataset.abahOriginalStickyZ || '';
                    const originalPriority = cell.dataset.abahOriginalStickyZPriority || '';
                    if (originalValue) {
                        cell.style.setProperty('z-index', originalValue, originalPriority);
                    } else {
                        cell.style.removeProperty('z-index');
                    }
                    delete cell.dataset.abahManagedStickyLayer;
                    delete cell.dataset.abahOriginalStickyZ;
                    delete cell.dataset.abahOriginalStickyZPriority;
                }
                return;
            }

            const minimumLayer = cell.closest('thead') ? 80 : 20;
            const currentLayer = Number.parseInt(window.getComputedStyle(cell).zIndex, 10);
            if (Number.isFinite(currentLayer) && currentLayer >= minimumLayer) {
                return;
            }

            if (cell.dataset.abahManagedStickyLayer !== '1') {
                cell.dataset.abahManagedStickyLayer = '1';
                cell.dataset.abahOriginalStickyZ = cell.style.getPropertyValue('z-index');
                cell.dataset.abahOriginalStickyZPriority = cell.style.getPropertyPriority('z-index');
            }
            cell.style.setProperty('z-index', String(minimumLayer), 'important');
        });
    };

    const syncReadableCellTitles = function (table) {
        table.querySelectorAll('th, td').forEach(function (cell) {
            if (cell.hasAttribute('title') || cell.closest('[data-abah-no-cell-title]')) {
                return;
            }

            const text = (cell.textContent || '').replace(/\s+/g, ' ').trim();
            if (text.length > 18) {
                cell.setAttribute('title', text);
            }
        });
    };

    const ensureFloatingHeader = function () {
        if (!freezeTableHeaders || floatingHeader) {
            return;
        }

        floatingHeader = document.createElement('div');
        floatingHeader.className = 'abah-floating-table-header';
        floatingHeader.hidden = true;
        floatingHeader.inert = true;
        floatingHeader.dataset.abahFloatingHeader = '1';
        floatingHeader.setAttribute('aria-hidden', 'true');

        floatingTrack = document.createElement('div');
        floatingTrack.className = 'abah-floating-table-header__track';
        floatingHeader.appendChild(floatingTrack);
        document.body.appendChild(floatingHeader);
    };

    const isVisible = function (element) {
        if (!element || !element.isConnected) {
            return false;
        }

        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return !element.hidden
            && style.display !== 'none'
            && style.visibility !== 'hidden'
            && Number(style.opacity || 1) > 0
            && rect.width > 1
            && rect.height > 1;
    };

    const getNavbarBottom = function () {
        if (!mainHeader || !isVisible(mainHeader)) {
            return 0;
        }

        const rect = mainHeader.getBoundingClientRect();
        return Math.max(0, Math.min(window.innerHeight, rect.bottom));
    };

    const hasOwnVerticalScroll = function (wrapper) {
        if (!wrapper) {
            return false;
        }

        const style = window.getComputedStyle(wrapper);
        return ['auto', 'scroll'].includes(style.overflowY)
            && wrapper.scrollHeight > wrapper.clientHeight + 2;
    };

    const getHorizontalClip = function (wrapper) {
        const wrapperRect = wrapper.getBoundingClientRect();
        let left = Math.max(0, wrapperRect.left + wrapper.clientLeft);
        let right = Math.min(window.innerWidth, wrapperRect.right);
        let parent = wrapper.parentElement;

        while (parent && parent !== document.body && parent !== document.documentElement) {
            const style = window.getComputedStyle(parent);
            if (['auto', 'scroll', 'hidden', 'clip'].includes(style.overflowX)) {
                const rect = parent.getBoundingClientRect();
                left = Math.max(left, rect.left + parent.clientLeft);
                right = Math.min(right, rect.left + parent.clientLeft + parent.clientWidth);
            }
            parent = parent.parentElement;
        }

        return { left, right, width: Math.max(0, right - left) };
    };

    const getFloatingCandidate = function () {
        const navbarBottom = getNavbarBottom();
        let candidate = null;

        root.querySelectorAll('table.abah-table-managed').forEach(function (table) {
            if (!table.tHead
                || !table.tHead.rows.length
                || table.closest('[data-abah-no-table-freeze]')
                || shouldSkipTable(table)
                || !isVisible(table)) {
                return;
            }

            const wrapper = table.closest(wrapperSelector);
            if (!wrapper || !isVisible(wrapper) || hasOwnVerticalScroll(wrapper)) {
                return;
            }

            const tableRect = table.getBoundingClientRect();
            const headRect = table.tHead.getBoundingClientRect();
            const modalBody = table.closest('.modal.show .modal-body');
            const modalBodyRect = modalBody?.getBoundingClientRect();
            const top = Math.max(navbarBottom, modalBodyRect?.top || 0);
            const bottom = Math.min(window.innerHeight, modalBodyRect?.bottom || window.innerHeight);
            if (headRect.height <= 1
                || headRect.top >= top - 1
                || tableRect.bottom <= top + headRect.height + 1
                || tableRect.top >= bottom
                || tableRect.bottom <= top) {
                return;
            }

            const clip = getHorizontalClip(wrapper);
            if (clip.width <= 2) {
                return;
            }

            const current = { table, tableRect, headRect, clip, top, bottom, modal: Boolean(modalBody) };
            if (!candidate || current.tableRect.top > candidate.tableRect.top) {
                candidate = current;
            }
        });

        return candidate;
    };

    const sanitizeFloatingClone = function (clone) {
        clone.removeAttribute('id');
        clone.removeAttribute('tabindex');
        clone.dataset.abahFloatingClone = '1';
        clone.querySelectorAll('[id]').forEach(function (element) {
            element.removeAttribute('id');
        });
        clone.querySelectorAll('[for], [aria-controls], [aria-describedby], [aria-labelledby]').forEach(function (element) {
            element.removeAttribute('for');
            element.removeAttribute('aria-controls');
            element.removeAttribute('aria-describedby');
            element.removeAttribute('aria-labelledby');
        });
        clone.querySelectorAll('a, button, input, select, textarea, [tabindex]').forEach(function (element) {
            element.setAttribute('tabindex', '-1');
        });
    };

    const syncFloatingCloneGeometry = function (table, clone) {
        const copiedProperties = [
            'background-color', 'background-image', 'background-position', 'background-size',
            'border-top-color', 'border-top-style', 'border-top-width',
            'border-right-color', 'border-right-style', 'border-right-width',
            'border-bottom-color', 'border-bottom-style', 'border-bottom-width',
            'border-left-color', 'border-left-style', 'border-left-width',
            'box-shadow', 'color', 'font-family', 'font-size', 'font-style', 'font-weight',
            'letter-spacing', 'line-height', 'padding-top', 'padding-right', 'padding-bottom',
            'padding-left', 'text-align', 'text-transform', 'vertical-align', 'white-space'
        ];
        const tableRect = table.getBoundingClientRect();
        clone.style.setProperty('width', tableRect.width + 'px', 'important');
        clone.style.setProperty('min-width', tableRect.width + 'px', 'important');
        clone.style.setProperty('max-width', tableRect.width + 'px', 'important');
        clone.style.tableLayout = window.getComputedStyle(table).tableLayout;

        const sourceColumns = Array.from(table.querySelector(':scope > colgroup')?.children || []);
        const cloneColumns = Array.from(clone.querySelector(':scope > colgroup')?.children || []);
        sourceColumns.forEach(function (column, columnIndex) {
            const cloneColumn = cloneColumns[columnIndex];
            const columnWidth = column.getBoundingClientRect().width;
            if (cloneColumn && columnWidth > 0) {
                cloneColumn.style.setProperty('width', columnWidth + 'px', 'important');
                cloneColumn.style.setProperty('min-width', columnWidth + 'px', 'important');
                cloneColumn.style.setProperty('max-width', columnWidth + 'px', 'important');
            }
        });

        const sourceRows = Array.from(table.tHead?.rows || []);
        const cloneRows = Array.from(clone.tHead?.rows || []);
        sourceRows.forEach(function (row, rowIndex) {
            const rowRect = row.getBoundingClientRect();
            const cloneRow = cloneRows[rowIndex];
            if (!cloneRow) {
                return;
            }

            cloneRow.style.height = rowRect.height + 'px';
            Array.from(row.cells).forEach(function (cell, cellIndex) {
                const cloneCell = cloneRow.cells[cellIndex];
                if (!cloneCell) {
                    return;
                }

                const width = cell.getBoundingClientRect().width;
                const sourceStyle = window.getComputedStyle(cell);
                cloneCell.style.setProperty('width', width + 'px', 'important');
                cloneCell.style.setProperty('min-width', width + 'px', 'important');
                cloneCell.style.setProperty('max-width', width + 'px', 'important');
                copiedProperties.forEach(function (property) {
                    cloneCell.style.setProperty(property, sourceStyle.getPropertyValue(property), 'important');
                });

                const left = Number.parseFloat(sourceStyle.left);
                const right = Number.parseFloat(sourceStyle.right);
                const isStickyX = sourceStyle.position === 'sticky'
                    && (Number.isFinite(left) || Number.isFinite(right));
                cloneCell.classList.toggle('abah-floating-sticky-x', isStickyX);
                if (isStickyX) {
                    cloneCell.style.setProperty('--abah-floating-sticky-left', Number.isFinite(left) ? sourceStyle.left : 'auto');
                    cloneCell.style.setProperty('--abah-floating-sticky-right', Number.isFinite(right) ? sourceStyle.right : 'auto');
                    cloneCell.style.setProperty('z-index', sourceStyle.zIndex === 'auto' ? '2' : sourceStyle.zIndex, 'important');
                }
            });
        });
    };

    const buildFloatingClone = function (table) {
        const clone = table.cloneNode(false);
        clone.classList.add('abah-floating-table-header__table');
        clone.setAttribute('aria-hidden', 'true');

        Array.from(table.children).forEach(function (child) {
            if (child.tagName === 'COLGROUP') {
                clone.appendChild(child.cloneNode(true));
            }
        });
        clone.appendChild(table.tHead.cloneNode(true));
        sanitizeFloatingClone(clone);
        floatingTrack.replaceChildren(clone);
        syncFloatingCloneGeometry(table, clone);

        return clone;
    };

    const hideFloatingHeader = function () {
        if (floatingHeader) {
            floatingHeader.hidden = true;
            floatingHeader.classList.remove('is-modal');
        }
    };

    const renderFloatingHeader = function () {
        floatingFrame = null;
        if (!freezeTableHeaders) {
            return;
        }

        ensureFloatingHeader();
        const candidate = getFloatingCandidate();
        if (!candidate) {
            hideFloatingHeader();
            return;
        }

        if (activeFloatingTable !== candidate.table || !activeFloatingClone || floatingGeometryDirty) {
            activeFloatingTable = candidate.table;
            activeFloatingClone = buildFloatingClone(candidate.table);
        }

        floatingGeometryDirty = false;

        const { tableRect, headRect, clip, top, bottom, modal } = candidate;
        const visibleHeight = Math.min(headRect.height, Math.max(0, bottom - top));

        if (visibleHeight <= 1) {
            hideFloatingHeader();
            return;
        }

        floatingHeader.classList.toggle('is-modal', modal);
        floatingHeader.style.left = clip.left + 'px';
        floatingHeader.style.top = top + 'px';
        floatingHeader.style.width = clip.width + 'px';
        floatingHeader.style.height = visibleHeight + 'px';
        floatingTrack.style.width = tableRect.width + 'px';
        floatingHeader.scrollLeft = Math.max(0, clip.left - tableRect.left);
        floatingHeader.hidden = false;
    };

    const scheduleFloatingHeader = function (geometryDirty = false) {
        floatingGeometryDirty = floatingGeometryDirty || geometryDirty;
        if (!freezeTableHeaders || floatingFrame !== null) {
            return;
        }

        floatingFrame = window.requestAnimationFrame(renderFloatingHeader);
    };

    const enhanceTable = function (table) {
        if (!(table instanceof HTMLTableElement) || shouldSkipTable(table)) {
            return;
        }

        const wrapper = ensureWrapper(table);
        table.classList.add('abah-table-managed');
        syncHeaderOffsets(table);
        syncStickyHeaderSurfaces(table);
        syncHorizontalStickyLayers(table);
        syncReadableCellTitles(table);
        syncScrollableWrapper(table, wrapper);
    };

    const enhanceTables = function () {
        syncFrame = null;
        const targets = pendingEnhancementRoots.has(root) ? [root] : Array.from(pendingEnhancementRoots);
        pendingEnhancementRoots.clear();

        const tables = new Set();
        targets.forEach(function (target) {
            if (!root.contains(target)) {
                return;
            }

            const containingTable = target.closest('table');
            if (containingTable) {
                tables.add(containingTable);
            }

            target.querySelectorAll('table').forEach(function (table) {
                tables.add(table);
            });
        });
        tables.forEach(enhanceTable);

        scheduleFloatingHeader(true);
    };

    const scheduleEnhance = function (target = root) {
        pendingEnhancementRoots.add(target);
        if (syncFrame !== null) {
            return;
        }

        syncFrame = window.requestAnimationFrame(enhanceTables);
    };

    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node instanceof Element) {
                    scheduleEnhance(node);
                }
            });
        });
    });
    observer.observe(root, {
        childList: true,
        subtree: true
    });

    let windowResizeTimer = null;
    window.addEventListener('resize', function () {
        document.body.classList.add('is-resizing');
        if (windowResizeTimer) {
            window.clearTimeout(windowResizeTimer);
        }
        windowResizeTimer = window.setTimeout(function () {
            document.body.classList.remove('is-resizing');
            windowResizeTimer = null;
        }, 120);
        scheduleEnhance(root);
    }, { passive: true });
    window.addEventListener('orientationchange', function () { scheduleEnhance(root); });
    window.addEventListener('load', function () { scheduleEnhance(root); });
    document.addEventListener('shown.bs.tab', function () { scheduleEnhance(root); });
    document.addEventListener('shown.bs.collapse', function () { scheduleEnhance(root); });
    document.addEventListener('shown.bs.modal', function () { scheduleEnhance(root); });
    document.addEventListener('hidden.bs.modal', function () { scheduleFloatingHeader(true); });
    document.addEventListener('scroll', function () { scheduleFloatingHeader(false); }, { capture: true, passive: true });

    if (window.jQuery) {
        window.jQuery(document).on('collapsed.lte.pushmenu shown.lte.pushmenu', function () {
            scheduleFloatingHeader(true);
        });
    }

    if ('ResizeObserver' in window) {
        const resizeObserver = new ResizeObserver(function () {
            scheduleEnhance(root);
            scheduleFloatingHeader(true);
        });
        resizeObserver.observe(root);
    }

    scheduleEnhance();
    scheduleFloatingHeader(true);
    document.fonts?.ready?.then(function () {
        scheduleEnhance(root);
        scheduleFloatingHeader(true);
    });
});
