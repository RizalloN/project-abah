{{-- Theme bootstrap: runs before first paint so a dark preference never flashes light. --}}
<script data-theme-skip>
    (function () {
        var root = document.documentElement;
        var stored = null;
        try { stored = localStorage.getItem('asix-theme'); } catch (e) { stored = null; }
        var followSystem = root.getAttribute('data-theme-follow-system') === '1';
        var dark = stored === 'dark' || (followSystem && stored !== 'light' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        root.setAttribute('data-theme', dark ? 'dark' : 'light');
        root.style.colorScheme = dark ? 'dark' : 'light';
        if (dark) {
            root.classList.add('theme-pending');
            window.setTimeout(function () { root.classList.remove('theme-pending'); }, 2500);
        }
    }());
</script>
<style id="asix-theme-tokens" data-theme-skip>
    :root[data-theme="dark"] {
        color-scheme: dark;
        --bri-nusantara: #3d84e8;
        --bri-cakrawala: #3b80e2;
        --bri-mentari: #70ccee;
        --bri-ink: #a9c8f5;
        --bri-night: #c7dbf7;
        --bri-mist: #0a172a;
        --bri-white: #142238;
        --app-ink: #e2ecf7;
        --app-muted: #a4b6ca;
        --app-surface: #142238;
        --app-surface-soft: #0f1d30;
        --app-line: #2d3c53;
        --app-line-strong: #3f516b;
        --app-shadow: 0 18px 42px -28px rgba(1, 6, 16, 0.78);
        --app-shadow-raised: 0 22px 52px -30px rgba(1, 6, 16, 0.86);
        --app-focus-ring: rgba(88, 153, 243, 0.5);
        --app-success: #5fd3a0;
        --app-success-soft: rgba(46, 160, 108, 0.16);
        --app-warning: #f2b85c;
        --app-warning-soft: rgba(214, 145, 30, 0.16);
        --app-danger: #ff8a92;
        --app-danger-soft: rgba(220, 60, 72, 0.16);
        --app-info-soft: rgba(61, 132, 232, 0.16);
        --theme-raised: #1c2c43;
        --theme-hover: rgba(88, 153, 243, 0.12);
        --theme-link: #7db0f5;
    }

    /* Switching never animates every colour on a 15k-line page. */
    :root.theme-switching *,
    :root.theme-switching *::before,
    :root.theme-switching *::after {
        transition: none !important;
    }

    :root.theme-pending[data-theme="dark"] .content-wrapper {
        opacity: 0;
    }

    :root[data-theme="dark"] body {
        background: var(--bri-mist);
        color: var(--app-ink);
        scrollbar-color: #3f516b #0a172a;
    }

    :root[data-theme="dark"] ::selection {
        color: #ffffff;
        background: rgba(88, 153, 243, 0.42);
    }

    :root[data-theme="dark"] .content-wrapper,
    :root[data-theme="dark"] .main-footer {
        background: var(--bri-mist);
        color: var(--app-ink);
        border-color: var(--app-line);
    }

    :root[data-theme="dark"] .skip-to-content {
        background: var(--theme-raised);
        border-color: var(--bri-cakrawala);
    }

    /* Bootstrap / AdminLTE components (vendor CSS is not adapted automatically). */
    :root[data-theme="dark"] :is(.card, .info-box, .small-box, .callout, .modal-content, .popover, .list-group-item, .toast) {
        background-color: var(--app-surface);
        color: var(--app-ink);
        border-color: var(--app-line);
    }

    :root[data-theme="dark"] :is(.card-header, .card-footer, .modal-header, .modal-footer) {
        border-color: var(--app-line);
    }

    :root[data-theme="dark"] :is(.bg-white, .bg-light) {
        background-color: var(--app-surface) !important;
    }

    :root[data-theme="dark"] :is(.text-dark, .text-body) {
        color: var(--app-ink) !important;
    }

    :root[data-theme="dark"] .text-muted {
        color: var(--app-muted) !important;
    }

    :root[data-theme="dark"] .text-primary {
        color: var(--theme-link) !important;
    }

    :root[data-theme="dark"] .text-success { color: var(--app-success) !important; }
    :root[data-theme="dark"] .text-danger { color: var(--app-danger) !important; }
    :root[data-theme="dark"] .text-warning { color: var(--app-warning) !important; }

    :root[data-theme="dark"] :is(.border, .border-top, .border-bottom, .border-left, .border-right, hr, .dropdown-divider) {
        border-color: var(--app-line) !important;
    }

    :root[data-theme="dark"] a:not(.btn):not(.nav-link):not(.dropdown-item):not(.page-link) {
        color: var(--theme-link);
    }

    :root[data-theme="dark"] .table {
        color: var(--app-ink);
    }

    :root[data-theme="dark"] :is(.table th, .table td, .table thead th) {
        border-color: var(--app-line);
    }

    :root[data-theme="dark"] .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(164, 182, 202, 0.05);
    }

    :root[data-theme="dark"] :is(.table-light, .thead-light th, .table .thead-light th) {
        background-color: var(--theme-raised);
        color: var(--app-ink);
        border-color: var(--app-line);
    }

    :root[data-theme="dark"] :is(.form-control, .custom-select, .custom-file-label, .input-group-text) {
        background-color: var(--app-surface-soft);
        color: var(--app-ink);
        border-color: var(--app-line-strong);
    }

    :root[data-theme="dark"] .form-control::placeholder {
        color: #7f93ab;
    }

    :root[data-theme="dark"] :is(.form-control:disabled, .form-control[readonly], .custom-select:disabled) {
        background-color: #0c1a2c;
        color: var(--app-muted);
    }

    :root[data-theme="dark"] :is(.form-control:focus, .custom-select:focus) {
        background-color: var(--app-surface-soft);
        color: var(--app-ink);
        border-color: var(--bri-cakrawala);
        box-shadow: 0 0 0 3px var(--app-focus-ring);
    }

    :root[data-theme="dark"] :is(.btn-light, .btn-default, .btn-outline-secondary, .btn-outline-light) {
        background-color: var(--theme-raised);
        color: var(--app-ink);
        border-color: var(--app-line-strong);
    }

    :root[data-theme="dark"] :is(.btn-light, .btn-default, .btn-outline-secondary, .btn-outline-light):hover {
        background-color: #24364f;
        color: #ffffff;
    }

    :root[data-theme="dark"] .btn-outline-primary {
        color: var(--theme-link);
        border-color: var(--bri-cakrawala);
    }

    :root[data-theme="dark"] .btn-outline-primary:hover {
        color: #ffffff;
        background-color: var(--bri-nusantara);
    }

    :root[data-theme="dark"] :is(.dropdown-menu, .popover, .tooltip-inner) {
        background-color: var(--theme-raised);
        color: var(--app-ink);
        border-color: var(--app-line-strong);
        box-shadow: 0 18px 40px -18px rgba(1, 6, 16, 0.9);
    }

    :root[data-theme="dark"] .dropdown-item {
        color: var(--app-ink);
    }

    :root[data-theme="dark"] :is(.dropdown-item:hover, .dropdown-item:focus) {
        background-color: var(--theme-hover);
        color: #ffffff;
    }

    :root[data-theme="dark"] :is(.nav-tabs, .nav-tabs .nav-link) {
        border-color: var(--app-line);
    }

    :root[data-theme="dark"] .nav-tabs .nav-link.active {
        background-color: var(--app-surface);
        color: var(--app-ink);
        border-color: var(--app-line) var(--app-line) var(--app-surface);
    }

    :root[data-theme="dark"] .page-link {
        background-color: var(--app-surface);
        color: var(--theme-link);
        border-color: var(--app-line);
    }

    :root[data-theme="dark"] .page-item.disabled .page-link {
        background-color: var(--app-surface-soft);
        color: #6f849d;
    }

    :root[data-theme="dark"] .page-item.active .page-link {
        background-color: var(--bri-nusantara);
        border-color: var(--bri-nusantara);
        color: #ffffff;
    }

    :root[data-theme="dark"] :is(.badge-light, .badge-secondary) {
        background-color: var(--theme-raised);
        color: var(--app-ink);
    }

    :root[data-theme="dark"] .alert-info { background-color: var(--app-info-soft); color: #b9d5fb; border-color: rgba(88, 153, 243, 0.32); }
    :root[data-theme="dark"] .alert-success { background-color: var(--app-success-soft); color: #9de6c3; border-color: rgba(46, 160, 108, 0.36); }
    :root[data-theme="dark"] .alert-warning { background-color: var(--app-warning-soft); color: #f6d39a; border-color: rgba(214, 145, 30, 0.36); }
    :root[data-theme="dark"] .alert-danger { background-color: var(--app-danger-soft); color: #ffb3b8; border-color: rgba(220, 60, 72, 0.36); }

    :root[data-theme="dark"] .progress {
        background-color: #22324a;
    }

    :root[data-theme="dark"] .close {
        color: var(--app-ink);
        text-shadow: none;
    }

    :root[data-theme="dark"] .modal-backdrop.show {
        opacity: 0.72;
    }

    /* Select2 */
    :root[data-theme="dark"] :is(.select2-container--bootstrap4 .select2-selection, .select2-container--default .select2-selection--single, .select2-container--default .select2-selection--multiple) {
        background-color: var(--app-surface-soft);
        border-color: var(--app-line-strong);
        color: var(--app-ink);
    }

    :root[data-theme="dark"] :is(.select2-container .select2-selection__rendered, .select2-container--default .select2-selection--single .select2-selection__rendered) {
        color: var(--app-ink);
    }

    :root[data-theme="dark"] .select2-dropdown {
        background-color: var(--theme-raised);
        border-color: var(--app-line-strong);
        color: var(--app-ink);
    }

    :root[data-theme="dark"] .select2-search--dropdown .select2-search__field {
        background-color: var(--app-surface-soft);
        border-color: var(--app-line-strong);
        color: var(--app-ink);
    }

    :root[data-theme="dark"] .select2-results__option[aria-selected="true"],
    :root[data-theme="dark"] .select2-results__option--selected {
        background-color: rgba(88, 153, 243, 0.18);
        color: #ffffff;
    }

    :root[data-theme="dark"] .select2-container--bootstrap4 .select2-results__option--highlighted,
    :root[data-theme="dark"] .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: var(--bri-nusantara);
        color: #ffffff;
    }

    /* SweetAlert2 */
    :root[data-theme="dark"] .swal2-popup {
        background: var(--theme-raised);
        color: var(--app-ink);
    }

    :root[data-theme="dark"] :is(.swal2-title, .swal2-html-container, .swal2-content) {
        color: var(--app-ink);
    }

    :root[data-theme="dark"] .swal2-input,
    :root[data-theme="dark"] .swal2-textarea,
    :root[data-theme="dark"] .swal2-select {
        background: var(--app-surface-soft);
        color: var(--app-ink);
        border-color: var(--app-line-strong);
    }

    /* daterangepicker */
    :root[data-theme="dark"] .daterangepicker {
        background-color: var(--theme-raised);
        border-color: var(--app-line-strong);
        color: var(--app-ink);
    }

    :root[data-theme="dark"] .daterangepicker::after {
        border-bottom-color: var(--theme-raised);
    }

    :root[data-theme="dark"] .daterangepicker .calendar-table {
        background-color: var(--theme-raised);
        border-color: var(--theme-raised);
    }

    :root[data-theme="dark"] .daterangepicker :is(td.off, td.off.in-range) {
        background-color: transparent;
        color: #5f738c;
    }

    :root[data-theme="dark"] .daterangepicker td.available:hover,
    :root[data-theme="dark"] .daterangepicker th.available:hover {
        background-color: var(--theme-hover);
        color: #ffffff;
    }

    :root[data-theme="dark"] .daterangepicker .calendar-table :is(.next, .prev) span {
        border-color: var(--app-ink);
    }

    :root[data-theme="dark"] .daterangepicker select {
        background-color: var(--app-surface-soft);
        color: var(--app-ink);
        border-color: var(--app-line-strong);
    }

    /* Leaflet maps (Market Share): basemap dimmed, data polygons untouched. */
    :root[data-theme="dark"] .leaflet-container {
        background-color: var(--app-surface-soft);
    }

    :root[data-theme="dark"] .leaflet-tile-pane {
        filter: brightness(0.72) contrast(1.08) saturate(0.85);
    }

    :root[data-theme="dark"] .leaflet-control-attribution {
        background: rgba(15, 29, 48, 0.86) !important;
        color: var(--app-muted);
    }

    :root[data-theme="dark"] :is(.leaflet-bar a, .leaflet-control-layers) {
        background-color: var(--theme-raised);
        color: var(--app-ink);
        border-color: var(--app-line-strong);
    }

    :root[data-theme="dark"] :is(.leaflet-popup-content-wrapper, .leaflet-popup-tip, .leaflet-tooltip) {
        background-color: var(--theme-raised);
        color: var(--app-ink);
        border-color: var(--app-line-strong);
    }

    /* Theme toggle in the navbar */
    .theme-toggle {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        padding: 0;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #334155;
        cursor: pointer;
        transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
    }

    .theme-toggle--floating {
        position: fixed;
        top: max(1rem, env(safe-area-inset-top, 0px));
        right: max(1rem, env(safe-area-inset-right, 0px));
        z-index: 20;
        box-shadow: 0 10px 26px -18px rgba(4, 42, 95, 0.6);
    }

    .theme-toggle:hover {
        border-color: #b9cfee;
        color: #0857c3;
    }

    .theme-toggle:focus-visible {
        outline: 3px solid var(--app-focus-ring);
        outline-offset: 2px;
    }

    .theme-toggle__icon {
        position: absolute;
        font-size: 1rem;
        transition: opacity 0.22s ease, transform 0.32s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .theme-toggle__icon--sun {
        opacity: 0;
        transform: rotate(-70deg) scale(0.6);
    }

    :root[data-theme="dark"] .theme-toggle {
        background: var(--theme-raised);
        border-color: var(--app-line-strong);
        color: #f6d39a;
    }

    :root[data-theme="dark"] .theme-toggle__icon--moon {
        opacity: 0;
        transform: rotate(70deg) scale(0.6);
    }

    :root[data-theme="dark"] .theme-toggle__icon--sun {
        opacity: 1;
        transform: none;
    }

    @media (prefers-reduced-motion: reduce) {
        .theme-toggle__icon {
            transition: none;
        }
    }
</style>
<script src="{{ asset('js/theme/theme-engine.js') }}?v={{ @filemtime(public_path('js/theme/theme-engine.js')) }}" defer data-theme-skip></script>
