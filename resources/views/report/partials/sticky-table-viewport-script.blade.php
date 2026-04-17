@php
    $wrapperSelector = $wrapperSelector ?? '.table-container';
    $tableSelector = $tableSelector ?? '.table-report';
    $visibleRowLimit = $visibleRowLimit ?? 25;
    $stickyTrim = $stickyTrim ?? 24;
@endphp

<script>
document.addEventListener('DOMContentLoaded', function () {
    const wrapperSelector = @json($wrapperSelector);
    const tableSelector = @json($tableSelector);
    const visibleRowLimit = {{ (int) $visibleRowLimit }};
    const stickyTrim = {{ (int) $stickyTrim }};
    const mainHeader = document.querySelector('.main-header');
    let syncFrame = null;

    const getStickyTopOffset = function () {
        const headerHeight = mainHeader ? Math.ceil(mainHeader.getBoundingClientRect().height || 0) : 0;
        return Math.max(0, headerHeight - stickyTrim);
    };

    const getManagedWrappers = function () {
        return Array.from(document.querySelectorAll(wrapperSelector)).filter(function (wrapper) {
            return Boolean(wrapper.querySelector(tableSelector));
        });
    };

    const getVisibleBodyRows = function (table) {
        const body = table.tBodies && table.tBodies.length ? table.tBodies[0] : null;

        if (!body) {
            return [];
        }

        return Array.from(body.rows).filter(function (row) {
            const styles = window.getComputedStyle(row);
            return !row.hidden && styles.display !== 'none' && styles.visibility !== 'collapse';
        });
    };

    const syncHeaderOffsets = function (table) {
        const headerRows = Array.from(table.querySelectorAll('thead tr'));
        let cumulativeTop = 0;

        headerRows.forEach(function (row) {
            Array.from(row.cells).forEach(function (cell) {
                cell.style.setProperty('--table-head-top', cumulativeTop + 'px');
            });

            cumulativeTop += Math.ceil(row.getBoundingClientRect().height || 0);
        });

        return cumulativeTop;
    };

    const canScrollVertically = function (wrapper) {
        return wrapper.scrollHeight - wrapper.clientHeight > 1;
    };

    const getHorizontalScrollbarReserve = function (wrapper, table) {
        if (!wrapper || !table) {
            return 0;
        }

        const needsHorizontalScroll = table.scrollWidth - wrapper.clientWidth > 1;
        return needsHorizontalScroll ? 16 : 0;
    };

    const bindWrapperInteractions = function (wrapper) {
        if (wrapper.dataset.viewportWheelBound === '1') {
            return;
        }

        wrapper.dataset.viewportWheelBound = '1';
        wrapper.addEventListener('wheel', function (event) {
            if (!canScrollVertically(wrapper) || event.deltaY === 0) {
                return;
            }

            const maxScrollTop = Math.max(0, wrapper.scrollHeight - wrapper.clientHeight);
            const nextScrollTop = Math.min(maxScrollTop, Math.max(0, wrapper.scrollTop + event.deltaY));

            if (nextScrollTop === wrapper.scrollTop) {
                return;
            }

            wrapper.scrollTop = nextScrollTop;
            event.preventDefault();
        }, { passive: false });
    };

    const syncWrapperViewport = function (wrapper) {
        const table = wrapper.querySelector(tableSelector);

        if (!table) {
            return;
        }

        if (!wrapper.hasAttribute('tabindex')) {
            wrapper.setAttribute('tabindex', '0');
        }

        bindWrapperInteractions(wrapper);

        const stickyTop = getStickyTopOffset();
        wrapper.style.setProperty('--table-sticky-top', stickyTop + 'px');

        const headerHeight = syncHeaderOffsets(table);
        const visibleRows = getVisibleBodyRows(table);
        const scrollbarReserve = getHorizontalScrollbarReserve(wrapper, table);
        wrapper.style.setProperty('--table-scrollbar-space', scrollbarReserve + 'px');

        if (!visibleRows.length) {
            wrapper.style.height = 'auto';
            wrapper.style.maxHeight = 'none';
            return;
        }

        const bodyHeight = visibleRows.slice(0, visibleRowLimit).reduce(function (total, row) {
            return total + Math.ceil(row.getBoundingClientRect().height || 0);
        }, 0);

        const viewportLimit = Math.max(320, window.innerHeight - stickyTop - 20);
        const desiredHeight = Math.min(viewportLimit, headerHeight + bodyHeight + scrollbarReserve + 2);

        wrapper.style.height = desiredHeight + 'px';
        wrapper.style.maxHeight = desiredHeight + 'px';
    };

    const syncAllViewports = function () {
        syncFrame = null;
        getManagedWrappers().forEach(syncWrapperViewport);
    };

    const scheduleViewportSync = function () {
        if (syncFrame !== null) {
            return;
        }

        syncFrame = window.requestAnimationFrame(syncAllViewports);
    };

    getManagedWrappers().forEach(function (wrapper) {
        const observer = new MutationObserver(scheduleViewportSync);
        observer.observe(wrapper, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style', 'hidden'],
        });
    });

    window.addEventListener('resize', scheduleViewportSync);
    window.addEventListener('load', scheduleViewportSync);
    document.addEventListener('shown.bs.tab', scheduleViewportSync);
    document.addEventListener('shown.bs.collapse', scheduleViewportSync);
    document.addEventListener('shown.bs.modal', scheduleViewportSync);

    scheduleViewportSync();
});
</script>
