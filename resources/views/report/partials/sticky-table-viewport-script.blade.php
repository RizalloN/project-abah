@php
    $wrapperSelector = $wrapperSelector ?? '.table-container';
    $tableSelector = $tableSelector ?? '.table-report';
    $stickyTrim = $stickyTrim ?? 24;
@endphp

<script>
document.addEventListener('DOMContentLoaded', function () {
    const wrapperSelector = @json($wrapperSelector);
    const tableSelector = @json($tableSelector);
    const stickyTrim = {{ (int) $stickyTrim }};
    const mainHeader = document.querySelector('.main-header');
    let syncFrame = null;
    const wrapperObservers = new Map();
    const observerOptions = {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'style', 'hidden'],
    };

    const getStickyTopOffset = function () {
        const headerHeight = mainHeader ? Math.ceil(mainHeader.getBoundingClientRect().height || 0) : 0;
        return Math.max(0, headerHeight - stickyTrim);
    };

    const getManagedWrappers = function () {
        return Array.from(document.querySelectorAll(wrapperSelector)).filter(function (wrapper) {
            return Boolean(wrapper.querySelector(tableSelector));
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

    const getHorizontalScrollbarReserve = function (wrapper, table) {
        if (!wrapper || !table) {
            return 0;
        }

        const needsHorizontalScroll = table.scrollWidth - wrapper.clientWidth > 1;
        return needsHorizontalScroll ? 16 : 0;
    };

    const syncWrapperViewport = function (wrapper) {
        const table = wrapper.querySelector(tableSelector);

        if (!table) {
            return;
        }

        if (!wrapper.hasAttribute('tabindex')) {
            wrapper.setAttribute('tabindex', '0');
        }

        const stickyTop = getStickyTopOffset();
        wrapper.style.setProperty('--table-sticky-top', stickyTop + 'px');

        syncHeaderOffsets(table);
        const scrollbarReserve = getHorizontalScrollbarReserve(wrapper, table);
        wrapper.style.setProperty('--table-scrollbar-space', scrollbarReserve + 'px');
        wrapper.style.height = 'auto';
        wrapper.style.maxHeight = 'none';
    };

    const observeWrapper = function (wrapper) {
        let observer = wrapperObservers.get(wrapper);

        if (!observer) {
            observer = new MutationObserver(scheduleViewportSync);
            wrapperObservers.set(wrapper, observer);
        }

        observer.observe(wrapper, observerOptions);
    };

    const syncAllViewports = function () {
        syncFrame = null;
        const wrappers = getManagedWrappers();

        /*
         * Mutating wrapper/header inline styles while their observers are active
         * would schedule this same sync again indefinitely. Disconnect every
         * managed observer for the short synchronous measurement/write cycle;
         * external row/class/visibility mutations remain observed afterwards.
         */
        wrappers.forEach(function (wrapper) {
            const observer = wrapperObservers.get(wrapper);

            if (observer) {
                observer.disconnect();
            }
        });

        try {
            wrappers.forEach(syncWrapperViewport);
        } finally {
            wrappers.forEach(observeWrapper);
        }
    };

    const scheduleViewportSync = function () {
        if (syncFrame !== null) {
            return;
        }

        syncFrame = window.requestAnimationFrame(syncAllViewports);
    };

    getManagedWrappers().forEach(observeWrapper);

    window.addEventListener('resize', scheduleViewportSync);
    window.addEventListener('load', scheduleViewportSync);
    document.addEventListener('shown.bs.tab', scheduleViewportSync);
    document.addEventListener('shown.bs.collapse', scheduleViewportSync);
    document.addEventListener('shown.bs.modal', scheduleViewportSync);

    scheduleViewportSync();
});
</script>
