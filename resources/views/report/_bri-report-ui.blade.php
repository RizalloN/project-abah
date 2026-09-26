<style>
    :root {
        --bri-ui-blue: var(--bri-nusantara, #0857c3);
        --bri-ui-blue-deep: var(--bri-ink, #053b82);
        --bri-ui-blue-soft: #edf5ff;
        --bri-ui-border: var(--app-line, #d8e5f7);
        --bri-ui-muted: var(--app-muted, #526987);
        --bri-ui-surface: var(--app-surface, #ffffff);
        --bri-ui-text: var(--app-ink, #082b59);
        --bri-ui-radius: var(--app-radius, 14px);
        --bri-ui-shadow: var(--app-shadow, 0 18px 42px -30px rgba(4, 42, 95, 0.34));
        --bri-ui-focus: rgba(48, 127, 226, 0.34);
    }

    .report-filter-card,
    .report-data-card,
    .report-card,
    .casa-shell,
    .casa-table-shell,
    .dormant-shell,
    .dormant-table-shell {
        border: 1px solid var(--bri-ui-border);
        border-radius: var(--bri-ui-radius);
        overflow: visible;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        box-shadow: var(--bri-ui-shadow) !important;
    }                   

    .report-filter-card .card-body,
    .report-data-card .card-header,
    .report-data-card .card-body,
    .report-card .card-body,
    .casa-shell .card-body,
    .casa-table-shell .card-body,
    .dormant-shell .card-body,
    .dormant-table-shell .card-body {
        background-color: var(--bri-ui-surface);
    }

    .report-data-card > .card-header,
    .report-card > .card-header,
    .casa-shell > .card-header,
    .dormant-shell > .card-header {
        border-bottom: 1px solid var(--bri-ui-border);
        background: linear-gradient(180deg, #ffffff 0%, #f7faff 100%);
        color: var(--bri-ui-text);
        box-shadow: inset 0 1px 0 rgba(48, 127, 226, 0.42);
    }

    .report-data-card .card-title,
    .report-card .card-title,
    .casa-shell .card-title,
    .dormant-shell .card-title,
    .report-title {
        color: var(--bri-ui-text);
        font-weight: 800;
        letter-spacing: -0.018em;
    }

    .report-filter-card .card-body,
    .casa-shell .card-body,
    .dormant-shell .card-body {
        overflow: visible;
        padding: 1rem 1.1rem 1.05rem;
    }

    .report-filter-card .form-group,
    .casa-shell .form-group,
    .dormant-shell .form-group {
        position: relative;
        margin-bottom: 0.85rem;
    }

    .report-filter-card .form-group > label,
    .report-filter-label,
    .casa-filter-label,
    .dormant-filter-label {
        display: block;
        margin-bottom: 0.38rem !important;
        color: #516b91 !important;
        font-size: 0.72rem !important;
        font-weight: 800 !important;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .report-filter-card .form-control,
    .report-input,
    .casa-filter-control,
    .dormant-filter-control,
    .branch-dropdown-toggle,
    .casa-dropdown-toggle,
    .dormant-dropdown-toggle {
        border-radius: 12px !important;
        min-height: 40px !important;
        height: 40px !important;
        border: 1px solid #cbd8e8 !important;
        background: linear-gradient(180deg, #eaf2ff 0%, #ffffff 78%) !important;
        color: #334155;
        font-size: 0.9rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 12px 22px -20px rgba(15, 23, 42, 0.2);
        transition: border-color 0.18s ease, box-shadow 0.2s ease, background-color 0.18s ease;
    }

    .report-filter-card .form-control:focus,
    .report-filter-card .form-control:focus-visible,
    .report-input:focus,
    .report-input:focus-visible,
    .casa-filter-control:focus,
    .casa-filter-control:focus-visible,
    .dormant-filter-control:focus,
    .dormant-filter-control:focus-visible,
    .branch-dropdown-toggle:focus,
    .branch-dropdown-toggle:focus-visible,
    .casa-dropdown-toggle:focus,
    .casa-dropdown-toggle:focus-visible,
    .dormant-dropdown-toggle:focus,
    .dormant-dropdown-toggle:focus-visible {
        border-color: var(--bri-ui-blue) !important;
        box-shadow: 0 0 0 3px var(--bri-ui-focus), 0 12px 22px -22px rgba(0, 70, 133, 0.18) !important;
        outline: none !important;
        background: #ffffff !important;
    }

    .report-filter-card :where(.btn-primary, .btn-info),
    .casa-shell :where(.btn-primary, .btn-info),
    .dormant-shell :where(.btn-primary, .btn-info) {
        border-color: var(--bri-ui-blue-deep);
        border-radius: 11px;
        background: linear-gradient(135deg, var(--bri-ui-blue-deep), var(--bri-ui-blue));
        box-shadow: 0 10px 22px -14px rgba(5, 59, 130, 0.72);
        font-weight: 750;
        transition: border-color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
    }

    .report-filter-card :where(.btn-primary, .btn-info):hover,
    .casa-shell :where(.btn-primary, .btn-info):hover,
    .dormant-shell :where(.btn-primary, .btn-info):hover {
        border-color: var(--bri-ui-blue-deep);
        background: linear-gradient(135deg, #042a5f, var(--bri-ui-blue-deep));
        box-shadow: 0 12px 24px -15px rgba(4, 42, 95, 0.78);
    }

    .report-filter-card :where(.btn, button):focus-visible,
    .report-data-card :where(.btn, button):focus-visible,
    .report-card :where(.btn, button):focus-visible,
    .casa-shell :where(.btn, button):focus-visible,
    .dormant-shell :where(.btn, button):focus-visible {
        outline: 3px solid var(--bri-ui-focus);
        outline-offset: 2px;
    }

    .report-filter-card .form-control:disabled,
    .report-input:disabled,
    .casa-filter-control:disabled,
    .dormant-filter-control:disabled,
    .branch-dropdown-toggle:disabled,
    .casa-dropdown-toggle:disabled,
    .dormant-dropdown-toggle:disabled {
        background: linear-gradient(180deg, #edf4ff, #f8fbff) !important;
        color: var(--bri-ui-muted) !important;
        cursor: not-allowed;
        opacity: 1;
        box-shadow: none;
    }

    .branch-filter-dropdown,
    .uker-filter-dropdown,
    .casa-filter-dropdown,
    .dormant-filter-dropdown {
        position: relative;
    }

    .branch-dropdown-toggle,
    .casa-dropdown-toggle,
    .dormant-dropdown-toggle {
        width: 100%;
        justify-content: space-between;
        text-align: left;
    }

    .branch-dropdown-label,
    .casa-dropdown-label,
    .dormant-dropdown-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .branch-dropdown-menu,
    .uker-dropdown-menu,
    .casa-dropdown-menu,
    .dormant-dropdown-menu {
        position: absolute;
        top: calc(100% + 0.45rem);
        left: 0;
        right: 0;
        z-index: 1050;
        display: none;
        width: 100%;
        max-height: 260px;
        overflow-y: auto;
        background: rgba(255, 255, 255, 0.98);
        border: 1px solid var(--bri-ui-border);
        border-radius: var(--bri-ui-radius);
        box-shadow: 0 22px 46px -30px rgba(4, 42, 95, 0.42);
        padding: 0.45rem;
    }

    .branch-dropdown-menu.show,
    .uker-dropdown-menu.show,
    .casa-dropdown-menu.show,
    .dormant-dropdown-menu.show {
        display: block;
    }

    .branch-dropdown-menu .dropdown-item,
    .uker-dropdown-menu .dropdown-item,
    .casa-dropdown-menu .dropdown-item,
    .dormant-dropdown-menu .dropdown-item {
        padding: 0.62rem 0.72rem;
        cursor: pointer;
        margin-bottom: 0;
        border-radius: 10px;
    }

    .branch-dropdown-menu .dropdown-item:hover,
    .uker-dropdown-menu .dropdown-item:hover,
    .casa-dropdown-menu .dropdown-item:hover,
    .dormant-dropdown-menu .dropdown-item:hover {
        background: linear-gradient(135deg, #edf5ff, #f8fbff);
    }

    .branch-dropdown-menu .form-check,
    .uker-dropdown-menu .form-check,
    .casa-dropdown-menu .form-check,
    .dormant-dropdown-menu .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .branch-dropdown-menu .form-check-input,
    .uker-dropdown-menu .form-check-input,
    .casa-dropdown-menu .form-check-input,
    .dormant-dropdown-menu .form-check-input {
        position: static;
        margin: 0;
        width: 1rem;
        height: 1rem;
        border-color: #b9cbe3;
        cursor: pointer;
    }

    .branch-dropdown-menu .form-check-input:checked,
    .uker-dropdown-menu .form-check-input:checked,
    .casa-dropdown-menu .form-check-input:checked,
    .dormant-dropdown-menu .form-check-input:checked {
        background-color: var(--bri-ui-blue);
        border-color: var(--bri-ui-blue);
    }

    .branch-dropdown-menu .form-check-label,
    .uker-dropdown-menu .form-check-label,
    .casa-dropdown-menu .form-check-label,
    .dormant-dropdown-menu .form-check-label {
        margin: 0;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
    }

    .table-container {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        border: 1px solid var(--bri-ui-border);
        border-radius: var(--bri-ui-radius);
        background: #ffffff;
        box-shadow: var(--bri-ui-shadow);
        scrollbar-width: thin;
        scrollbar-color: #9aa8bd #eef3f9;
    }

    .table-container::-webkit-scrollbar {
        height: 10px;
    }

    .table-container::-webkit-scrollbar-track {
        background: #eef3f9;
        border-radius: 999px;
    }

    .table-container::-webkit-scrollbar-thumb {
        background: #9aa8bd;
        border-radius: 999px;
    }

    .table-report {
        border-collapse: separate;
        border-spacing: 0;
        width: max-content;
        min-width: 100%;
        table-layout: auto;
        margin-bottom: 0;
        background: #ffffff;
    }

    .table-report th,
    .table-report td {
        vertical-align: middle !important;
        border: 1px solid #e4ebf3;
        word-wrap: break-word;
        white-space: normal;
    }

    .table-report thead th {
        font-size: 0.68rem;
        padding: 11px 6px;
        text-align: center;
        font-weight: 800;
        letter-spacing: 0.02em;
        border-color: rgba(255, 255, 255, 0.22);
    }

    .table-report tbody td {
        font-size: 0.7rem;
        padding: 7px 6px;
        text-align: right;
        background: #ffffff;
        color: #334155;
        font-variant-numeric: tabular-nums;
    }

    .table-report td.text-left {
        text-align: left;
    }

    .table-report tbody tr:nth-child(even):not(.row-total):not(.row-total-blue) > td {
        background: #fafcff;
    }

    .table-report tbody tr:hover > td {
        background: #eef5ff !important;
    }

    .row-total,
    .row-total-blue {
        background: #003366 !important;
        color: #ffffff !important;
        font-weight: 700;
    }

    .row-total td,
    .row-total-blue td {
        background: #003366 !important;
        color: #ffffff !important;
        font-weight: 700;
    }

    .row-total .rka-col,
    .row-total-blue .rka-col {
        background: #003366 !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
    }

    .nav-tabs.report-tabs {
        border-bottom: 1px solid #dbe5ef;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
        scrollbar-width: thin;
        align-items: flex-end;
        min-height: 58px;
        margin-top: 0.2rem;
    }

    .nav-tabs.report-tabs .nav-link {
        border: none;
        font-weight: 700;
        color: #6b7280;
        padding: 13px 18px 12px;
        font-size: 0.95rem;
        line-height: 1.2;
        background: transparent;
    }

    .nav-tabs.report-tabs .nav-link.active {
        border-bottom: 3px solid var(--bri-ui-blue);
        color: var(--bri-ui-blue);
        background: transparent;
    }

    .nav-tabs.report-tabs .nav-link:hover {
        border-bottom: 3px solid #9ec5fe;
        color: var(--bri-ui-blue);
        background: transparent;
    }

    .nav-tabs.report-tabs .nav-link:focus-visible,
    .branch-dropdown-menu .dropdown-item:focus-visible,
    .uker-dropdown-menu .dropdown-item:focus-visible,
    .casa-dropdown-menu .dropdown-item:focus-visible,
    .dormant-dropdown-menu .dropdown-item:focus-visible {
        outline: 3px solid var(--bri-ui-focus);
        outline-offset: -2px;
    }

    .report-data-card .empty-state,
    .report-data-card .report-empty-state,
    .casa-table-shell .casa-empty-state,
    .dormant-table-shell .dormant-empty-state {
        background: linear-gradient(180deg, #fbfdff 0%, var(--bri-ui-blue-soft) 100%);
        color: var(--bri-ui-muted);
    }

    .report-data-card .empty-state strong,
    .report-data-card .report-empty-state strong,
    .casa-table-shell .casa-empty-state strong,
    .dormant-table-shell .dormant-empty-state strong {
        color: var(--bri-ui-text);
    }

    @media (max-width: 767px) {
        .report-filter-card .card-body,
        .casa-shell .card-body,
        .dormant-shell .card-body {
            padding: 0.85rem;
        }

        .table-report th,
        .table-report td {
            padding: 0.6rem 0.55rem;
        }
    }

    @media (max-width: 575.98px) {
        .report-filter-card .card-body,
        .report-data-card > .card-header,
        .report-data-card > .card-body,
        .report-card > .card-header,
        .report-card > .card-body,
        .casa-shell > .card-header,
        .casa-shell > .card-body,
        .dormant-shell > .card-header,
        .dormant-shell > .card-body {
            padding-right: 0.75rem;
            padding-left: 0.75rem;
        }

        .report-data-card .card-title,
        .report-card .card-title,
        .casa-shell .card-title,
        .dormant-shell .card-title,
        .report-title {
            font-size: clamp(1rem, 6vw, 1.2rem);
            line-height: 1.25;
        }
    }

    @media (pointer: coarse) {
        .report-filter-card :where(button, .btn, select, input, [role="button"]),
        .casa-shell :where(button, .btn, select, input, [role="button"]),
        .dormant-shell :where(button, .btn, select, input, [role="button"]),
        .nav-tabs.report-tabs .nav-link {
            min-height: 44px !important;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .report-filter-card *,
        .report-data-card *,
        .report-card *,
        .casa-shell *,
        .dormant-shell * {
            transition-duration: 0.01ms !important;
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
        }
    }
</style>
