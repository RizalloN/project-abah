<style>
    :root {
        --bri-ui-blue: #00529c;
        --bri-ui-blue-deep: #004685;
        --bri-ui-blue-soft: #eaf2ff;
        --bri-ui-border: #dbe5ef;
        --bri-ui-muted: #64748b;
        --bri-ui-surface: #ffffff;
    }

    .report-filter-card,
    .report-data-card,
    .report-card,
    .casa-shell,
    .casa-table-shell,
    .dormant-shell,
    .dormant-table-shell {
        border: 1px solid var(--bri-ui-border);
        border-radius: 18px;
        overflow: visible;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, 0.22) !important;
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
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
    }

    .report-filter-card .form-control:focus,
    .report-input:focus,
    .casa-filter-control:focus,
    .dormant-filter-control:focus,
    .branch-dropdown-toggle:focus,
    .casa-dropdown-toggle:focus,
    .dormant-dropdown-toggle:focus {
        border-color: var(--bri-ui-blue) !important;
        box-shadow: 0 0 0 3px rgba(0, 82, 156, 0.14), 0 12px 22px -22px rgba(0, 70, 133, 0.18) !important;
        outline: none !important;
        background: #ffffff !important;
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
        border-radius: 14px;
        box-shadow: 0 20px 34px -28px rgba(0, 70, 133, 0.22);
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
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, 0.22);
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
</style>
