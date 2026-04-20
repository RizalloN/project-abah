@extends('layouts.admin')

@section('title', 'Dashboard Pinjaman')

@section('content')
<style>
    :root {
        --loan-surface: #ffffff;
        --loan-surface-soft: #f8fbff;
        --loan-border: #dbe5ef;
        --loan-border-strong: #c9d6e6;
        --loan-text: #0f172a;
        --loan-muted: #64748b;
        --loan-blue: #1d4ed8;
        --loan-blue-deep: #0f4c97;
        --loan-blue-ink: #0b3a78;
        --loan-blue-soft: #eff6ff;
        --loan-red: #ef4444;
    }

    .loan-dashboard {
        padding-bottom: 1.5rem;
        color: var(--loan-text);
    }

    .loan-shell,
    .loan-table-shell {
        position: relative;
        border: 1px solid var(--loan-border);
        border-radius: 22px;
        background:
            radial-gradient(circle at top right, rgba(29, 78, 216, 0.08), transparent 24%),
            linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(246, 250, 255, 0.97)),
            var(--loan-surface);
        box-shadow: 0 18px 34px -26px rgba(15, 23, 42, 0.24);
    }

    .loan-shell::before,
    .loan-table-shell::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 4px;
        border-radius: 22px 22px 0 0;
        background: linear-gradient(90deg, var(--loan-blue-deep), var(--loan-blue), #3b82f6, #ef4444);
        pointer-events: none;
    }

    .loan-shell .card-body,
    .loan-table-shell .card-body {
        background: transparent;
    }

    .loan-page-title {
        font-size: clamp(1.7rem, 2.7vw, 2.5rem);
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.45rem;
    }

    .loan-filter-hero {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 1rem 1rem 0.95rem;
        border: 1px solid rgba(29, 78, 216, 0.12);
        border-radius: 18px;
        background:
            linear-gradient(135deg, rgba(239, 246, 255, 0.98), rgba(255, 255, 255, 0.98));
        box-shadow: 0 16px 28px -24px rgba(15, 23, 42, 0.2);
    }

    .loan-filter-hero-copy {
        min-width: 0;
    }

    .loan-filter-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.34rem 0.72rem;
        border-radius: 999px;
        background: rgba(29, 78, 216, 0.09);
        color: var(--loan-blue-deep);
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        margin-bottom: 0.55rem;
    }

    .loan-filter-hero-title {
        font-size: 1.08rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.15rem;
    }

    .loan-filter-hero-subtitle {
        color: #5b6d88;
        font-size: 0.88rem;
        line-height: 1.55;
        margin: 0;
        max-width: 54rem;
    }

    .loan-filter-hero-badges {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    .loan-filter-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.52rem 0.78rem;
        border-radius: 999px;
        border: 1px solid rgba(29, 78, 216, 0.12);
        background: linear-gradient(135deg, #ffffff, #eef5ff);
        color: #35517c;
        font-size: 0.8rem;
        font-weight: 800;
        box-shadow: 0 10px 20px -18px rgba(0, 82, 156, 0.25);
        white-space: nowrap;
    }

    .loan-filter-grid .form-group {
        position: relative;
        min-height: 114px;
        margin-bottom: 0.9rem;
        padding: 0.95rem 0.98rem 0.9rem;
        border: 1px solid rgba(29, 78, 216, 0.12);
        border-radius: 18px;
        background:
            radial-gradient(circle at top right, rgba(29, 78, 216, 0.06), transparent 36%),
            linear-gradient(180deg, rgba(236, 243, 255, 0.98) 0%, rgba(255, 255, 255, 0.98) 74%),
            #ffffff;
        box-shadow: 0 16px 28px -26px rgba(15, 23, 42, 0.24);
        overflow: hidden;
    }

    .loan-filter-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 800;
        color: #516b91;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 0.45rem;
        position: relative;
        z-index: 1;
    }

    .loan-filter-grid .form-group::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(90deg, var(--loan-blue-deep), var(--loan-blue), #7fa8ff 72%, var(--loan-red));
    }

    .loan-filter-grid .form-group::after {
        content: '';
        position: absolute;
        right: -18px;
        top: -18px;
        width: 90px;
        height: 90px;
        border-radius: 50%;
        background: radial-gradient(circle at center, rgba(29, 78, 216, 0.12), rgba(29, 78, 216, 0) 70%);
        pointer-events: none;
    }

    .loan-filter-control,
    .loan-filter-control.select2-selection {
        border-radius: 16px !important;
        min-height: 46px !important;
        height: 46px !important;
        border-color: rgba(29, 78, 216, 0.18) !important;
        background: linear-gradient(180deg, rgba(233, 241, 255, 0.98) 0%, rgba(255, 255, 255, 0.99) 78%) !important;
        font-size: 0.94rem;
        display: flex;
        align-items: center;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.96),
            0 14px 24px -22px rgba(15, 23, 42, 0.24);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
        position: relative;
        z-index: 1;
    }

    input.loan-filter-control {
        padding: 0.55rem 0.85rem;
    }

    .loan-filter-control:disabled {
        background: linear-gradient(180deg, #edf4ff, #f8fbff) !important;
        color: var(--loan-muted) !important;
        box-shadow: none;
        cursor: not-allowed;
    }

    .loan-filter-control:focus,
    .loan-filter-control:focus-visible {
        border-color: var(--loan-blue) !important;
        box-shadow:
            0 0 0 3px rgba(29, 78, 216, 0.16),
            0 12px 26px -18px rgba(15, 23, 42, 0.22) !important;
        outline: none !important;
        transform: translateY(-1px) scale(1.01);
    }

    .select2-container--bootstrap4 .select2-selection--single.loan-filter-control,
    .select2-container--bootstrap4 .select2-selection--single,
    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control,
    .select2-container--bootstrap4 .select2-selection--multiple {
        min-height: 46px !important;
        height: 46px !important;
        border-radius: 16px !important;
        border-color: rgba(29, 78, 216, 0.18) !important;
        background: linear-gradient(180deg, rgba(233, 241, 255, 0.98) 0%, rgba(255, 255, 255, 0.99) 78%) !important;
        display: flex !important;
        align-items: center !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.96),
            0 14px 24px -22px rgba(15, 23, 42, 0.24);
        overflow: hidden !important;
    }

    .select2-container--bootstrap4.select2-container--focus .select2-selection--single,
    .select2-container--bootstrap4.select2-container--focus .select2-selection--multiple,
    .select2-container--bootstrap4.select2-container--open .select2-selection--single,
    .select2-container--bootstrap4.select2-container--open .select2-selection--multiple {
        border-color: var(--loan-blue) !important;
        box-shadow:
            0 0 0 3px rgba(29, 78, 216, 0.16),
            0 12px 26px -18px rgba(15, 23, 42, 0.24) !important;
    }

    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered,
    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__rendered {
        color: #24324a !important;
        font-size: 0.95rem !important;
        font-weight: 700 !important;
        line-height: 44px !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__rendered {
        display: flex !important;
        align-items: center !important;
        gap: 0.55rem !important;
    }

    .loan-select2-summary {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        min-width: 0;
        width: 100%;
    }

    .loan-select2-summary-main {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .loan-select2-summary-count {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        padding: 0.18rem 0.6rem;
        border-radius: 999px;
        background: rgba(29, 78, 216, 0.1);
        border: 1px solid rgba(29, 78, 216, 0.14);
        color: var(--loan-blue-ink);
        font-size: 0.72rem;
        font-weight: 800;
    }

    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
        height: 44px !important;
        right: 0.75rem !important;
    }

    .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow b {
        border-color: #6a84b0 transparent transparent transparent !important;
        border-width: 5px 4px 0 4px !important;
    }

    .select2-container--bootstrap4 .select2-selection--single:hover .select2-selection__arrow b {
        border-color: var(--loan-blue) transparent transparent transparent !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control,
    .select2-container--bootstrap4 .select2-selection--multiple {
        padding: 0 2.25rem 0 0.95rem !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control .select2-selection__choice,
    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
        display: none !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control .select2-selection__rendered,
    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__rendered {
        display: block !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        line-height: 44px !important;
        color: #24324a !important;
        font-size: 0.95rem !important;
        transform: translateY(-1px);
        font-weight: 700 !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control .select2-search--inline,
    .select2-container--bootstrap4 .select2-selection--multiple .select2-search--inline {
        position: absolute !important;
        inset: 0 !important;
        width: 100% !important;
        height: 100% !important;
        margin: 0 !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control .select2-search__field,
    .select2-container--bootstrap4 .select2-selection--multiple .select2-search__field {
        width: 100% !important;
        height: 100% !important;
        margin: 0 !important;
        opacity: 0 !important;
        cursor: pointer !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control .select2-selection__clear,
    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__clear {
        position: absolute !important;
        right: 0.8rem !important;
        top: 50% !important;
        margin: 0 !important;
        transform: translateY(-50%) !important;
        line-height: 1 !important;
    }

    .loan-filter-summary-empty {
        color: var(--loan-muted) !important;
    }

    .select2-container--bootstrap4 .select2-dropdown {
        border: 1px solid rgba(29, 78, 216, 0.18);
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 30px 46px -30px rgba(15, 23, 42, 0.32);
        background: linear-gradient(180deg, #ffffff 0%, #f7faff 100%);
        padding: 0.45rem;
    }

    .select2-container--bootstrap4 .select2-results__option {
        padding: 0.78rem 0.82rem;
        font-size: 0.9rem;
        border-radius: 13px;
        margin-bottom: 0.25rem;
    }

    .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
        background: linear-gradient(135deg, rgba(29, 78, 216, 0.1), rgba(239, 246, 255, 0.94)) !important;
        color: var(--loan-blue-ink) !important;
    }

    .select2-container--bootstrap4 .select2-results__option[aria-selected="true"] {
        background: linear-gradient(135deg, rgba(29, 78, 216, 0.12), rgba(239, 246, 255, 0.98)) !important;
        color: var(--loan-blue-ink) !important;
        font-weight: 700;
    }

    .loan-select2-option {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        width: 100%;
    }

    .loan-select2-option input {
        pointer-events: none;
        width: 14px;
        height: 14px;
        accent-color: var(--loan-blue);
        flex: 0 0 auto;
    }

    .loan-select2-option .loan-select2-text {
        flex: 1 1 auto;
        min-width: 0;
    }

    .loan-select2-option .loan-select2-badge {
        margin-left: auto;
        display: inline-flex;
        align-items: center;
        padding: 0.18rem 0.55rem;
        border-radius: 999px;
        background: rgba(29, 78, 216, 0.09);
        border: 1px solid rgba(29, 78, 216, 0.12);
        color: var(--loan-blue-deep);
        font-size: 0.72rem;
        font-weight: 800;
    }

    .loan-filter-actions {
        padding: 0.95rem 1rem;
        border: 1px solid rgba(29, 78, 216, 0.12);
        border-radius: 18px;
        background: linear-gradient(180deg, rgba(239, 246, 255, 0.9), rgba(255, 255, 255, 1));
        box-shadow: 0 16px 26px -24px rgba(15, 23, 42, 0.2);
    }

    .loan-filter-actions .btn {
        min-height: 46px;
        border-radius: 14px;
        padding: 0.62rem 1.05rem;
        font-weight: 800;
    }

    .loan-filter-actions .btn-primary {
        background: linear-gradient(135deg, var(--loan-blue-deep), var(--loan-blue));
        border-color: transparent;
        box-shadow: 0 16px 28px -20px rgba(29, 78, 216, 0.75);
    }

    .loan-filter-actions .btn-primary:hover,
    .loan-filter-actions .btn-primary:focus {
        background: linear-gradient(135deg, #0b3a78, #2563eb);
        border-color: transparent;
        box-shadow: 0 18px 30px -20px rgba(29, 78, 216, 0.84);
    }

    .loan-filter-actions .btn-light {
        background: linear-gradient(180deg, #ffffff, #eef5ff);
        border-color: #d6e3f3;
        color: #29405f;
    }

    .loan-filter-actions .btn-light:hover,
    .loan-filter-actions .btn-light:focus {
        color: #12213a;
        border-color: #c5d8f2;
        background: linear-gradient(180deg, #ffffff, #e7f0ff);
    }

    .loan-loading-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.54rem 0.95rem;
        border-radius: 999px;
        background: rgba(29, 78, 216, 0.08);
        border: 1px solid rgba(29, 78, 216, 0.12);
        color: var(--loan-blue-deep);
        font-weight: 800;
    }

    .loan-loading-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: var(--loan-red);
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.14);
    }

    .loan-filter-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        color: var(--loan-muted);
        font-size: 0.84rem;
        margin-top: 0.25rem;
    }

    #loanMismatchPanel .loan-shell,
    #loanMismatchPanel .loan-table-shell {
        background:
            radial-gradient(circle at top right, rgba(0, 82, 156, 0.08), transparent 26%),
            linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    }

    #loanMismatchPanel .loan-shell::before,
    #loanMismatchPanel .loan-table-shell::before {
        background: linear-gradient(90deg, var(--bri-blue-dark), var(--bri-blue-main), #3b82f6);
    }

    .loan-mismatch-shell {
        overflow: visible;
    }

    .loan-mismatch-hero {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.15rem;
    }

    .loan-mismatch-title {
        margin: 0 0 0.35rem;
        font-size: 1.1rem;
        font-weight: 800;
        color: #0f172a;
    }

    .loan-mismatch-copy {
        margin: 0;
        color: #51657f;
        font-size: 0.88rem;
        line-height: 1.55;
    }

    .loan-mismatch-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.5rem 0.8rem;
        border-radius: 999px;
        border: 1px solid #d6e5fb;
        background: linear-gradient(135deg, #eef5ff, #f8fbff);
        color: #35517c;
        font-size: 0.8rem;
        font-weight: 800;
        white-space: nowrap;
        box-shadow: 0 10px 20px -18px rgba(0, 82, 156, 0.25);
    }

    .loan-mismatch-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.85rem;
        margin-top: 1rem;
    }

    .loan-mismatch-card {
        position: relative;
        border: 1px solid #dbe5ef;
        border-radius: 16px;
        padding: 1rem 1.05rem 0.95rem;
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(246, 250, 255, 0.97)),
            #ffffff;
        box-shadow: 0 12px 24px -22px rgba(15, 23, 42, 0.24);
        overflow: hidden;
    }

    .loan-mismatch-card::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(90deg, var(--loan-blue-deep), var(--loan-blue), #3b82f6);
    }

    /* Hover style moved to .hover-lift class logic */

    .loan-mismatch-card .loan-audit-label {
        color: #51657f;
        font-size: 0.74rem;
        font-weight: 800;
        letter-spacing: 0.08em;
    }

    .loan-mismatch-card .loan-audit-value {
        color: #0f172a;
        font-size: 1.55rem;
        margin-top: 0.1rem;
    }

    .loan-mismatch-card .loan-audit-note {
        color: #64748b;
        font-size: 0.78rem;
        margin-top: 0.3rem;
    }

    .loan-mismatch-action-col {
        display: flex;
        align-items: flex-start;
        justify-content: flex-start;
        padding-top: 1.55rem;
    }

    .loan-mismatch-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-start;
        gap: 0.75rem;
        width: 100%;
    }

    .loan-mismatch-actions .btn {
        min-height: 40px;
        border-radius: 12px;
        padding: 0.56rem 1rem;
        font-weight: 700;
        box-shadow: 0 10px 20px -18px rgba(15, 23, 42, 0.22);
    }

    .loan-mismatch-actions .btn-primary {
        background: linear-gradient(135deg, var(--loan-blue-deep), var(--loan-blue), #3b82f6);
        border-color: transparent;
        color: #ffffff;
        box-shadow: 0 16px 26px -18px rgba(29, 78, 216, 0.68);
    }

    .loan-mismatch-actions .btn-primary:hover {
        background: linear-gradient(135deg, var(--loan-blue-deep), #2563eb);
        transform: translateY(-1px);
        color: #ffffff;
        box-shadow: 0 18px 28px -18px rgba(29, 78, 216, 0.76);
    }

    .loan-mismatch-actions .btn-light {
        background: linear-gradient(180deg, #ffffff, #f8fbff);
        border-color: #cfdbee;
        color: #334155;
    }

    .loan-mismatch-actions .btn-light:hover {
        border-color: var(--bri-blue-main);
        color: var(--bri-blue-dark);
        background: #ffffff;
    }

    .loan-mismatch-actions .loan-loading-chip {
        border: 1px solid #d6e5fb;
    }

    .loan-mismatch-panel {
        overflow: visible;
    }

    .loan-mismatch-table-shell {
        margin-top: 1.25rem;
        overflow: hidden;
    }

    .loan-mismatch-table-shell .card-body {
        padding: 1.15rem 1.25rem 1.25rem;
    }

    .loan-mismatch-table-wrap {
        margin-top: 0.9rem;
        border: 1px solid rgba(29, 78, 216, 0.14);
        border-radius: 18px;
        overflow: auto;
        background: linear-gradient(180deg, #ffffff, #f8fbff);
        box-shadow: 0 18px 30px -26px rgba(15, 23, 42, 0.22);
        scrollbar-gutter: stable both-edges;
    }

    .loan-table-heading {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .loan-table-heading h5 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
    }

    .loan-table-heading p {
        margin: 0.25rem 0 0;
        color: #64748b;
        font-size: 0.88rem;
    }

    .loan-table-unit {
        margin-top: 0.35rem;
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .loan-table-note {
        margin-top: 0.6rem;
        color: #475569;
        font-size: 0.82rem;
        line-height: 1.5;
    }

    .loan-loading-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        border-radius: 999px;
        padding: 0.55rem 0.9rem;
        background: linear-gradient(135deg, #eff6ff, #ecfeff);
        color: #0f766e;
        font-size: 0.8rem;
        font-weight: 800;
    }

    .loan-loading-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #14b8a6;
        box-shadow: 0 0 0 rgba(20, 184, 166, 0.45);
        animation: loanPulse 1.6s infinite;
    }

    @keyframes loanPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(20, 184, 166, 0.45); }
        70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(20, 184, 166, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(20, 184, 166, 0); }
    }

    .loan-table-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border-radius: 999px;
        padding: 0.45rem 0.7rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 0.79rem;
        font-weight: 700;
    }

    .loan-table-stage {
        position: relative;
        min-height: 520px;
        padding-top: 0.25rem;
    }

    .loan-loading-overlay {
        position: absolute;
        inset: 0;
        z-index: 5;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        justify-content: center;
        align-items: center;
        border-radius: 18px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(248, 250, 252, 0.96));
        backdrop-filter: blur(4px);
        transition: opacity 0.28s ease, visibility 0.28s ease;
    }

    .loan-loading-overlay.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .loan-loading-title {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
    }

    .loan-loading-copy {
        max-width: 480px;
        text-align: center;
        color: #64748b;
        font-size: 0.9rem;
        margin: 0;
    }

    .loan-loading-progress {
        width: min(420px, 100%);
        display: grid;
        gap: 0.5rem;
    }

    .loan-loading-progress-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        font-size: 0.82rem;
        font-weight: 700;
        color: #475569;
    }

    .loan-loading-progress-track {
        width: 100%;
        height: 12px;
        border-radius: 999px;
        overflow: hidden;
        background: #dbe5ef;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
    }

    .loan-loading-progress-bar {
        width: 0%;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #0f766e, #14b8a6);
        transition: width 0.18s ease;
    }

    .loan-loading-phase {
        color: #0f172a;
    }

    .loan-skeleton-grid {
        width: min(780px, 100%);
        display: grid;
        grid-template-columns: 220px repeat(9, 1fr);
        gap: 0.75rem;
    }

    .loan-skeleton-cell {
        height: 16px;
        border-radius: 999px;
        background: linear-gradient(90deg, #e2e8f0 25%, #f8fafc 50%, #e2e8f0 75%);
        background-size: 220% 100%;
        animation: loanShimmer 1.3s infinite linear;
    }

    .loan-skeleton-cell.is-wide {
        height: 18px;
    }

    @keyframes loanShimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .loan-matrix-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid rgba(29, 78, 216, 0.14);
        border-radius: 20px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: 0 18px 32px -28px rgba(15, 23, 42, 0.24);
    }

    .loan-matrix {
        width: 100%;
        min-width: 1620px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .loan-matrix th,
    .loan-matrix td {
        padding: 13px 11px;
        border-right: 1px solid rgba(148, 163, 184, 0.16);
        border-bottom: 1px solid rgba(148, 163, 184, 0.16);
        text-align: right;
        vertical-align: middle;
    }

    .loan-matrix thead th {
        color: #ffffff;
        font-size: 0.82rem;
        font-weight: 800;
        text-align: center;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .loan-matrix thead th.matrix-wrap-head {
        white-space: normal;
        min-width: 170px;
        line-height: 1.35;
    }

    .loan-matrix .matrix-wrap-head .matrix-head-copy {
        display: inline-block;
    }

    .loan-matrix .matrix-before {
        background: linear-gradient(135deg, #d97706, #f59e0b);
        text-align: left;
        min-width: 180px;
        position: sticky;
        left: 0;
        z-index: 3;
        box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.18);
    }

    .loan-matrix .matrix-after-group,
    .loan-matrix .matrix-subhead {
        background: linear-gradient(135deg, #1d4ed8, #2563eb);
    }

    .loan-matrix .matrix-total-head {
        background: linear-gradient(135deg, #0b3a78, #0f4c97);
    }

    .loan-matrix tbody th {
        background: linear-gradient(135deg, #ea580c, #fb923c);
        color: #ffffff;
        text-align: left;
        font-size: 0.88rem;
        font-weight: 800;
        position: sticky;
        left: 0;
        z-index: 2;
        box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.18);
    }

    .loan-matrix tbody td {
        background: #eef5ff;
        color: #23324c;
        font-weight: 700;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        transition: filter 0.18s ease, transform 0.18s ease;
    }

    .loan-matrix tbody td.matrix-empty {
        color: #94a3b8;
        background: #f8fafc;
    }

    .loan-matrix tbody td.matrix-stagnant {
        background: #ffffff;
        color: #334155;
    }

    .loan-matrix tbody td.matrix-up {
        background: linear-gradient(135deg, #dcfce7, #f0fdf4);
        color: #166534;
    }

    .loan-matrix tbody td.matrix-down {
        background: linear-gradient(135deg, #fee2e2, #fff1f2);
        color: #991b1b;
    }

    .loan-matrix tbody td.matrix-new-account {
        background: linear-gradient(135deg, #e5e7eb, #f8fafc);
        color: #334155;
    }

    .loan-matrix .matrix-total-col {
        background: linear-gradient(135deg, #dbeafe, #eff6ff) !important;
        color: #0f4c97 !important;
        font-weight: 800;
    }

    .loan-matrix tfoot th,
    .loan-matrix tfoot td {
        background: linear-gradient(135deg, #0f172a, #111827);
        color: #ffffff;
        font-weight: 800;
        border-bottom: 0;
    }

    .loan-matrix tfoot .matrix-total-col {
        background: linear-gradient(135deg, #0b3a78, #1d4ed8) !important;
        color: #ffffff !important;
    }

    .loan-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.7rem;
        margin-top: 1rem;
    }

    .loan-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #475569;
        font-size: 0.82rem;
        font-weight: 700;
        padding: 0.45rem 0.7rem;
        border-radius: 999px;
        border: 1px solid rgba(148, 163, 184, 0.18);
        background: rgba(255, 255, 255, 0.88);
        box-shadow: 0 10px 18px -18px rgba(15, 23, 42, 0.2);
    }

    .loan-legend-swatch {
        width: 12px;
        height: 12px;
        border-radius: 999px;
    }

    .loan-empty-state {
        padding: 3.25rem 1rem;
        text-align: center;
        color: #64748b;
        background: linear-gradient(180deg, #fbfdff, #f8fafc);
    }

    .loan-empty-state strong {
        display: block;
        margin-bottom: 0.4rem;
        color: #0f172a;
    }

    .loan-matrix tbody tr:hover td:not(.matrix-up):not(.matrix-down):not(.matrix-new-account):not(.matrix-empty),
    .loan-matrix tbody tr:hover th {
        filter: brightness(0.98);
    }

    .loan-mode-shell {
        border: 1px solid var(--loan-border);
        border-radius: 18px;
        background: linear-gradient(135deg, #f8fbff, #ffffff);
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, 0.18);
    }

    .loan-mode-grid {
        display: grid;
        grid-template-columns: minmax(220px, 320px) 1fr;
        gap: 1rem;
        align-items: end;
    }

    .loan-mode-copy {
        color: #64748b;
        font-size: 0.88rem;
        margin: 0;
    }

    .loan-mismatch-audit {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.85rem;
        margin-bottom: 1rem;
    }

    .loan-audit-card {
        border: 1px solid var(--loan-border);
        border-radius: 16px;
        padding: 1rem 1.05rem;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
    }

    .loan-audit-label {
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.35rem;
    }

    .loan-audit-value {
        color: #0f172a;
        font-size: 1.35rem;
        font-weight: 800;
        line-height: 1.1;
    }

    .loan-audit-note {
        color: #64748b;
        font-size: 0.8rem;
        margin-top: 0.25rem;
    }

    .loan-mismatch-table th,
    .loan-mismatch-table td {
        vertical-align: middle;
    }

    .loan-mismatch-table-wrap {
        margin-top: 0.9rem;
        border: 1px solid rgba(29, 78, 216, 0.14);
        border-radius: 18px;
        overflow: auto;
        background: linear-gradient(180deg, #ffffff, #f8fbff);
        box-shadow: 0 18px 30px -26px rgba(15, 23, 42, 0.22);
        scrollbar-gutter: stable both-edges;
    }

    .loan-mismatch-table {
        width: 100%;
        min-width: 760px;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }

    .loan-mismatch-table thead th {
        background: linear-gradient(135deg, #0f4c97, #1d4ed8);
        color: #ffffff;
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.95rem 0.85rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.18);
        position: sticky;
        top: 0;
        z-index: 3;
    }

    .loan-mismatch-table tbody td {
        padding: 0.95rem 0.85rem;
        border-top: 1px solid #e5ebf3;
        color: #334155;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .loan-mismatch-table tbody tr:nth-child(even) td {
        background: #fafcff;
    }

    .loan-mismatch-table tbody tr:hover td {
        background: #eef5ff;
    }

    .loan-mismatch-table td:last-child,
    .loan-mismatch-table th:last-child {
        text-align: right;
    }

    .loan-mismatch-table th:first-child,
    .loan-mismatch-table td:first-child {
        width: 72px;
        text-align: center;
    }

    .loan-mismatch-table th:nth-child(2),
    .loan-mismatch-table td:nth-child(2) {
        width: auto;
    }

    .loan-mismatch-table th:nth-child(3),
    .loan-mismatch-table td:nth-child(3) {
        width: 220px;
        text-align: center;
    }

    .loan-mismatch-table th:nth-child(4),
    .loan-mismatch-table td:nth-child(4) {
        width: 180px;
        text-align: center;
    }

    .loan-mismatch-table tbody td .btn {
        white-space: nowrap;
        border-radius: 10px;
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    @media (max-width: 991.98px) {
        .loan-mode-grid {
            grid-template-columns: 1fr;
        }
    }

    /* ── Responsivitas ─────────────────────────────── */
    @media (max-width: 767px) {
        .loan-mismatch-audit {
            grid-template-columns: repeat(2, 1fr);
        }

        .loan-mismatch-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .loan-page-title {
            font-size: 1.5rem;
        }

        .loan-matrix {
            font-size: 0.78rem;
        }

        .loan-matrix th,
        .loan-matrix td {
            padding: 8px 6px;
        }

        .loan-matrix {
            min-width: 1480px;
        }

        .loan-mismatch-table {
            min-width: 620px;
        }

        .loan-filter-grid .col-xl-2,
        .loan-filter-grid .col-lg-4 {
            margin-bottom: 0;
        }
    }

    .loan-filter-actions {
        gap: 0.6rem;
    }

    .loan-filter-actions .btn {
        border-radius: 12px;
        min-height: 40px;
        padding: 0.55rem 0.95rem;
        font-weight: 700;
        box-shadow: 0 10px 20px -18px rgba(15, 23, 42, 0.22);
    }

    .loan-filter-actions .btn-primary {
        background: linear-gradient(135deg, var(--loan-blue-deep), var(--loan-blue));
        border-color: transparent;
        color: #ffffff;
        box-shadow: 0 16px 26px -18px rgba(29, 78, 216, 0.6);
    }

    .loan-filter-actions .btn-primary:hover {
        transform: translateY(-1px);
        color: #ffffff;
        box-shadow: 0 18px 28px -18px rgba(29, 78, 216, 0.68);
    }

    .loan-filter-actions .btn-light {
        background: linear-gradient(180deg, #ffffff, #f8fafc);
        border-color: #cfdbee;
        color: #334155;
    }

    .loan-filter-actions .btn-light:hover {
        background: #ffffff;
        border-color: var(--loan-blue);
        color: var(--loan-blue-deep);
    }

    @media (max-width: 479px) {
        .loan-mismatch-audit {
            grid-template-columns: 1fr 1fr;
        }

        .loan-mismatch-summary {
            grid-template-columns: 1fr 1fr;
        }

        .loan-audit-value {
            font-size: 1.1rem;
        }

        .loan-table-heading {
            flex-direction: column;
            align-items: flex-start;
        }

        .loan-mismatch-action-col {
            padding-top: 0;
        }
    }

    /* ── Scroll indicator untuk tabel horizontal ─── */
    .loan-matrix-wrap {
        position: relative;
    }

    .loan-matrix-scroll-hint {
        display: none;
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        width: 40px;
        background: linear-gradient(to right, transparent, rgba(255,255,255,0.85));
        pointer-events: none;
        z-index: 4;
    }

    @media (max-width: 991.98px) {
        .loan-matrix-scroll-hint {
            display: block;
        }
    }
</style>

<div class="loan-dashboard pt-4">


    @if ($selectedMode === 'matrix')
    <div id="loanMatrixPanel">
    <div class="card loan-shell mb-4">
        <div class="card-body p-4">
            <form id="loanFilterForm" method="GET" action="{{ route('report.dashboard-pinjaman.matrix') }}">
                <div class="loan-filter-hero">
                    <div class="loan-filter-hero-copy">
                        <div class="loan-filter-kicker">
                            <i class="fas fa-university"></i>
                            BRI Loan Intelligence
                        </div>
                        <div class="loan-filter-hero-title">Report Kejar Laba</div>
                        <p class="loan-filter-hero-subtitle">
                            Pilih periode, cabang, unit, dan posisi RKA untuk menelusuri pergerakan pinjaman dengan tampilan yang lebih rapi, modern, dan profesional.
                        </p>
                    </div>
                    <div class="loan-filter-hero-badges">
                        <span class="loan-filter-hero-badge">
                            <i class="fas fa-calendar-alt"></i>
                            Periode aktif: <strong id="loanActivePeriodMeta">{{ $selectedPeriod ? \Carbon\Carbon::parse($selectedPeriod)->format('d/m/Y') : '-' }}</strong>
                        </span>
                        <span class="loan-filter-hero-badge">
                            <i class="fas fa-exchange-alt"></i>
                            Pembanding M-1: <strong id="loanComparisonPeriodMeta">{{ $comparisonPeriod ? \Carbon\Carbon::parse($comparisonPeriod)->format('d/m/Y') : '-' }}</strong>
                        </span>
                    </div>
                </div>

                <div class="row loan-filter-grid">
                    <div class="col-xl-2 col-lg-4 col-md-6 animate-reveal stagger-1">
                        <div class="form-group">
                            <label class="loan-filter-label"><i class="fas fa-calendar-day mr-1"></i>Periode</label>
                            <input
                                id="loanPeriodeInput"
                                type="date"
                                name="periode"
                                class="form-control loan-filter-control"
                                value="{{ $requestedPeriod ?: $selectedPeriod }}"
                                max="{{ $periods->first() }}"
                            >
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6 animate-reveal stagger-2">
                        <div class="form-group">
                            <label class="loan-filter-label"><i class="fas fa-layer-group mr-1"></i>Segmen</label>
                            <select id="loanSegmenSelect" name="segmen_dashboard[]" class="form-control select2 loan-filter-control loan-filter-multiselect" multiple data-placeholder="Semua Segmen" data-selected='@json($filters["segmen"] ?? [])'>
                                <option value="">Pilih periode dulu</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6 animate-reveal stagger-3">
                        <div class="form-group">
                            <label class="loan-filter-label"><i class="fas fa-box-open mr-1"></i>Produk</label>
                            <select id="loanProdukSelect" name="produk_dashboard[]" class="form-control select2 loan-filter-control loan-filter-multiselect" multiple data-placeholder="Semua Produk" data-selected='@json($filters["produk"] ?? [])'>
                                <option value="">Pilih periode dulu</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6 animate-reveal stagger-4">
                        <div class="form-group">
                            <label class="loan-filter-label"><i class="fas fa-map-marked-alt mr-1"></i>Regional Office</label>
                            <input type="text" class="form-control loan-filter-control" value="Area 6" disabled>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6 animate-reveal stagger-5">
                        <div class="form-group">
                            <label class="loan-filter-label"><i class="fas fa-building mr-1"></i>Kantor Cabang</label>
                            <select id="loanCabangSelect" name="cabang1[]" class="form-control select2 loan-filter-control loan-filter-multiselect" multiple data-placeholder="Semua Kantor Cabang" data-selected='@json($filters["cabang"] ?? [])'>
                                <option value="">Pilih periode dulu</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="loan-filter-label"><i class="fas fa-sitemap mr-1"></i>Unit Kerja</label>
                            <select id="loanUnitSelect" name="unit1[]" class="form-control select2 loan-filter-control loan-filter-multiselect" multiple data-placeholder="Semua Unit Kerja" data-selected='@json($filters["unit"] ?? [])'>
                                <option value="">Pilih periode dulu</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center loan-filter-actions" style="gap: 0.75rem;">
                    <button id="loanSubmitButton" type="submit" class="btn btn-primary">
                        <i class="fas fa-filter mr-1"></i>
                        Telusuri Data
                    </button>
                    <a href="{{ route('report.dashboard-pinjaman.matrix') }}" class="btn btn-light">
                        <i class="fas fa-undo mr-1"></i>
                        Reset Filter
                    </a>
                    <div id="loanLoadingChip" class="loan-loading-chip d-none">
                        <span class="loan-loading-dot"></span>
                        Sedang Mengolah
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card loan-table-shell">
        <div class="card-body p-4">
            <div class="loan-table-heading">
                <div>
                    <h5>Matriks Pergerakan Kualitas Pinjaman</h5>
                    <div class="loan-table-unit">Satuan: Rp</div>
                    <div class="loan-table-note">
                        Kolom <strong>Kualitas After</strong> pada footer adalah snapshot query periode aktif dari <strong>daily_loan_dinamis</strong>.
                        Kolom kanan adalah <strong>total movement per baris before</strong>, jadi nilainya memang tidak sama dengan snapshot query per bucket.
                    </div>
                </div>
                <div class="loan-table-badge">
                    <i class="fas fa-table"></i>
                    <span id="loanPeriodBadge">
                        {{ $selectedPeriod ? \Carbon\Carbon::parse($selectedPeriod)->format('d/m/Y') : '-' }} vs {{ $comparisonPeriod ? \Carbon\Carbon::parse($comparisonPeriod)->format('d/m/Y') : '-' }}
                    </span>
                </div>
            </div>

            <div class="loan-table-stage">
                <div id="loanLoadingOverlay" class="loan-loading-overlay">
                    <div class="loan-loading-title">Sedang Mengolah</div>
                    <p id="loanLoadingCopy" class="loan-loading-copy">Menyiapkan data dashboard...</p>
                    <div class="loan-loading-progress" aria-live="polite">
                        <div class="loan-loading-progress-meta">
                            <span id="loanLoadingPhase" class="loan-loading-phase">Inisialisasi</span>
                            <span id="loanLoadingPercent">0%</span>
                        </div>
                        <div class="loan-loading-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                            <div id="loanLoadingProgressBar" class="loan-loading-progress-bar"></div>
                        </div>
                    </div>
                    <div class="loan-skeleton-grid" aria-hidden="true">
                        @for ($row = 0; $row < 7; $row++)
                            <div class="loan-skeleton-cell is-wide"></div>
                            @for ($col = 0; $col < 6; $col++)
                                <div class="loan-skeleton-cell"></div>
                            @endfor
                        @endfor
                    </div>
                </div>

                <div class="loan-matrix-wrap">
                    <div class="loan-matrix-scroll-hint" aria-hidden="true"></div>
                    <table class="loan-matrix">
                        <thead>
                            <tr>
                                <th rowspan="2" class="matrix-before">Kualitas Before</th>
                                <th colspan="{{ count($matrixColumns) }}" class="matrix-after-group">Kualitas After</th>
                                <th rowspan="2" class="matrix-total-head matrix-wrap-head">
                                    <span id="loanTotalValueHeader" class="matrix-head-copy">
                                        Total Movement<br>per Baris
                                    </span>
                                </th>
                                <th rowspan="2" class="matrix-subhead">Turunan Pokok</th>
                                <th rowspan="2" class="matrix-subhead">Suplesi</th>
                                <th rowspan="2" class="matrix-subhead">PH</th>
                                <th rowspan="2" class="matrix-subhead">Lunas</th>
                            </tr>
                            <tr>
                                @foreach ($matrixColumns as $column)
                                    <th class="matrix-subhead">{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody id="loanMatrixBody">
                            <tr>
                                <td colspan="{{ count($matrixColumns) + 6 }}" class="loan-empty-state">
                                    <strong>Filter belum dijalankan</strong>
                                    Pilih periode atau filter lain lalu klik <strong>Tampilkan</strong>.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr id="loanMatrixFoot">
                                <th>Grand Total</th>
                                @foreach ($matrixColumns as $column)
                                    <td>-</td>
                                @endforeach
                                <td class="matrix-total-col">-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="loan-legend">
                <span class="loan-legend-item">
                    <span class="loan-legend-swatch" style="background:#22c55e;"></span>
                    Naik
                </span>
                <span class="loan-legend-item">
                    <span class="loan-legend-swatch" style="background:#ef4444;"></span>
                    Turun
                </span>
                <span class="loan-legend-item">
                    <span class="loan-legend-swatch" style="background:#d1d5db;"></span>
                    New Account
                </span>
            </div>
        </div>
    </div>

    </div>
    @endif

    @if ($selectedMode === 'mismatch')
    <div id="loanMismatchPanel" class="loan-mismatch-panel">
        <div class="card loan-shell loan-mismatch-shell mb-4">
            <div class="card-body p-4">
                <form id="loanMismatchForm" method="GET" action="{{ route('report.dashboard-pinjaman.kolek-tidak-sesuai') }}">
                    <div class="loan-mismatch-hero">
                        <div>
                            <h5 class="loan-mismatch-title">Filter Kolek Tidak Sesuai</h5>
                        </div>
                        <div class="loan-mismatch-hero-badge">
                            <i class="fas fa-shield-alt"></i>
                            Audit Cabang
                        </div>
                    </div>

                    <div class="row loan-filter-grid">
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label class="loan-filter-label">Periode</label>
                                <input
                                    id="loanMismatchPeriodeInput"
                                    type="date"
                                    name="mismatch_periode"
                                    class="form-control loan-filter-control"
                                    value="{{ $mismatchRequestedPeriod ?: $mismatchSelectedPeriod }}"
                                    max="{{ $periods->first() }}"
                                >
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-5 col-md-6">
                            <div class="form-group">
                                <label class="loan-filter-label">Kantor Cabang</label>
                                <select id="loanMismatchCabangSelect" name="mismatch_cabang1" class="form-control loan-filter-control" data-selected="{{ $mismatchSelectedBranch }}">
                                    <option value="">Pilih periode dulu</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-xl-5 col-lg-3 col-md-12 loan-mismatch-action-col">
                            <div class="loan-mismatch-actions w-100">
                                <button id="loanMismatchSubmitButton" type="submit" class="btn btn-primary">
                                    <i class="fas fa-search mr-1"></i>
                                    Proses
                                </button>
                                <a href="{{ route('report.dashboard-pinjaman.kolek-tidak-sesuai') }}" class="btn btn-light">
                                    Reset
                                </a>
                                <div id="loanMismatchLoadingChip" class="loan-loading-chip d-none">
                                    <span class="loan-loading-dot"></span>
                                    Audit Sedang Jalan
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card loan-table-shell loan-mismatch-table-shell">
            <div class="card-body p-4">
                <div class="loan-table-heading">
                    <div>
                        <h5>Kolek Tidak Sesuai</h5>
                        
                    </div>
                    <div class="loan-table-badge">
                        <i class="fas fa-file-excel"></i>
                        <span id="loanMismatchPeriodBadge" class="loan-mismatch-hero-badge">
                            {{ $mismatchSelectedPeriod ? \Carbon\Carbon::parse($mismatchSelectedPeriod)->format('d/m/Y') : '-' }} | {{ $mismatchSelectedBranch ?: 'Belum pilih cabang' }}
                        </span>
                    </div>
                </div>

                <div class="loan-mismatch-summary">
                    <div class="loan-audit-card loan-mismatch-card">
                        <div class="loan-audit-label">Baris Discanning</div>
                        <div id="loanMismatchScanned" class="loan-audit-value">0</div>
                        <div class="loan-audit-note">Semua row yang masuk filter audit</div>
                    </div>
                    <div class="loan-audit-card loan-mismatch-card">
                        <div class="loan-audit-label">Mismatch</div>
                        <div id="loanMismatchTotal" class="loan-audit-value">0</div>
                        <div class="loan-audit-note">Jumlah row dengan kolek tidak sesuai</div>
                    </div>
                    <div class="loan-audit-card loan-mismatch-card">
                        <div class="loan-audit-label">Sesuai</div>
                        <div id="loanMismatchMatched" class="loan-audit-value">0</div>
                        <div class="loan-audit-note">Jumlah row yang sesuai rule audit</div>
                    </div>
                    <div class="loan-audit-card loan-mismatch-card">
                        <div class="loan-audit-label">Unit Bermasalah</div>
                        <div id="loanMismatchUnits" class="loan-audit-value">0</div>
                        <div class="loan-audit-note">Unit kerja yang memiliki mismatch</div>
                    </div>
                </div>

                <div class="loan-mismatch-table-wrap">
                        <table class="table table-hover loan-mismatch-table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 72px;">No</th>
                                <th>Unit Kerja</th>
                                <th style="width: 220px;">Jumlah Kolek Tidak Sesuai</th>
                                <th style="width: 180px;">Export Detail</th>
                            </tr>
                        </thead>
                        <tbody id="loanMismatchBody">
                            <tr>
                                <td colspan="4" class="loan-empty-state">
                                    <strong>Audit belum dijalankan</strong>
                                    Pilih periode dan cabang lalu klik <strong>Proses</strong>.
                                </td>
                            </tr>
                        </tbody>
                        </table>
                    </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('loanFilterForm');
        const body = document.getElementById('loanMatrixBody');
        const foot = document.getElementById('loanMatrixFoot');
        const overlay = document.getElementById('loanLoadingOverlay');
        const loadingCopy = document.getElementById('loanLoadingCopy');
        const loadingPhase = document.getElementById('loanLoadingPhase');
        const loadingPercent = document.getElementById('loanLoadingPercent');
        const loadingProgressBar = document.getElementById('loanLoadingProgressBar');
        const chip = document.getElementById('loanLoadingChip');
        const periodBadge = document.getElementById('loanPeriodBadge');
        const submitButton = document.getElementById('loanSubmitButton');
        const periodInput = document.getElementById('loanPeriodeInput');
        const activePeriodMeta = document.getElementById('loanActivePeriodMeta');
        const comparisonPeriodMeta = document.getElementById('loanComparisonPeriodMeta');
        const totalValueHeader = document.getElementById('loanTotalValueHeader');
        const segmenSelect = document.getElementById('loanSegmenSelect');
        const produkSelect = document.getElementById('loanProdukSelect');
        const cabangSelect = document.getElementById('loanCabangSelect');
        const unitSelect = document.getElementById('loanUnitSelect');
        const dataUrl = @json(route('report.dashboard-pinjaman.data'));
        const filtersUrl = @json(route('report.dashboard-pinjaman.filters'));
        const qualityColumns = @json($matrixColumns);
        const outputColumns = ['principal_reduction', 'suplesi', 'ph', 'lunas'];
        const qualityRanks = qualityColumns.reduce((accumulator, column, index) => {
            accumulator[column] = index;
            return accumulator;
        }, {});
        let activeController = null;
        let activeFilterController = null;
        let isRefreshingFilters = false;
        let filterReloadTimer = null;
        let loadingProgressValue = 0;
        let activeMatrixRequestId = 0;
        let activeFilterRequestId = 0;
        let isNavigatingAway = false;

        if (!form || !body || !foot || !overlay || !chip || !submitButton || !periodInput) {
            return;
        }

        function abortInFlightRequests() {
            if (activeController) {
                activeController.abort();
                activeController = null;
            }

            if (activeFilterController) {
                activeFilterController.abort();
                activeFilterController = null;
            }

            window.clearTimeout(filterReloadTimer);
        }

        function releaseLoadingUi() {
            overlay.classList.add('is-hidden');
            chip.classList.add('d-none');
            submitButton.disabled = false;
        }

        const filterSelects = [
            { element: segmenSelect, placeholder: 'Semua Segmen' },
            { element: produkSelect, placeholder: 'Semua Produk' },
            { element: cabangSelect, placeholder: 'Semua Kantor Cabang' },
            { element: unitSelect, placeholder: 'Semua Unit Kerja' },
        ];

        filterSelects.forEach(({ element }) => {
            element.dataset.state = periodInput.value ? 'idle' : 'disabled';
        });

        function parseSelectedDataset(select) {
            try {
                const parsed = JSON.parse(select.dataset.selected || '[]');
                return Array.isArray(parsed) ? parsed.map(String) : [];
            } catch (error) {
                return [];
            }
        }

        function syncSelectedDataset(select) {
            select.dataset.selected = JSON.stringify(window.jQuery(select).val() || []);
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function buildOptionTemplate(option) {
            if (!option.id) {
                return option.text;
            }

            const isChecked = option.element ? option.element.selected : false;
            const wrapper = document.createElement('span');
            wrapper.className = 'loan-select2-option';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = isChecked;

            const label = document.createElement('span');
            label.className = 'loan-select2-text';
            label.textContent = option.text;

            const badge = document.createElement('span');
            badge.className = 'loan-select2-badge';
            badge.textContent = isChecked ? 'Dipilih' : 'Pilih';

            wrapper.appendChild(checkbox);
            wrapper.appendChild(label);
            wrapper.appendChild(badge);

            return wrapper;
        }

        function initMultiSelect(select, placeholder) {
            if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) {
                return;
            }

            const $select = window.jQuery(select);
            // Hanya inisialisasi jika belum ada, jangan destroy & recreate
            if (!$select.data('select2')) {
                $select.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    placeholder,
                    closeOnSelect: false,
                    allowClear: true,
                    language: {
                        noResults: function () {
                            const state = select.dataset.state || 'ready';
                            if (state === 'loading') {
                                return 'Memuat opsi...';
                            }

                            if (state === 'empty') {
                                return 'Tidak ada opsi';
                            }

                            return 'Tidak ada opsi';
                        },
                    },
                    templateResult: buildOptionTemplate,
                    templateSelection: function (data) {
                        return data.text;
                    },
                    escapeMarkup: function (markup) {
                        return markup;
                    },
                });
            }
        }

        // Cache Intl formatter singleton to avoid object creation per cell (P5)
        const intlNumberFormat = new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });

        function formatNumber(value) {
            if (value === null || value === undefined || value === '') {
                return '-';
            }

            const number = Number(value);

            if (Number.isNaN(number)) {
                return '-';
            }

            return intlNumberFormat.format(number);
        }

        function formatDate(value) {
            if (!value) {
                return '-';
            }

            const date = new Date(value + 'T00:00:00');
            return new Intl.DateTimeFormat('id-ID').format(date);
        }

        function formatHeaderDate(value) {
            if (!value) {
                return 'Periode Terakhir';
            }

            const date = new Date(value + 'T00:00:00');
            return new Intl.DateTimeFormat('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            }).format(date);
        }

        function updateTotalValueHeader(period) {
            if (!totalValueHeader) {
                return;
            }

            totalValueHeader.innerHTML = 'Total Movement<br>per Baris';
        }

        function nextFrame() {
            return new Promise((resolve) => {
                window.requestAnimationFrame(() => resolve());
            });
        }

        function idlePause(timeout = 0) {
            return new Promise((resolve) => {
                window.setTimeout(resolve, timeout);
            });
        }

        function updateLoadingProgress(value, phase, copy) {
            loadingProgressValue = Math.max(0, Math.min(100, Math.round(value)));

            if (loadingPhase && phase) {
                loadingPhase.textContent = phase;
            }

            if (loadingCopy && copy) {
                loadingCopy.textContent = copy;
            }

            if (loadingPercent) {
                loadingPercent.textContent = `${loadingProgressValue}%`;
            }

            if (loadingProgressBar) {
                loadingProgressBar.style.width = `${loadingProgressValue}%`;
                loadingProgressBar.parentElement?.setAttribute('aria-valuenow', String(loadingProgressValue));
            }
        }

        function startLoadingProgress() {
            updateLoadingProgress(8, 'Mengambil Data', 'Menghubungi server dan menyiapkan data dashboard...');
            overlay.classList.remove('is-hidden');
            chip.classList.remove('d-none');
            submitButton.disabled = true;
        }

        async function finishLoadingProgress() {
            updateLoadingProgress(100, 'Selesai', 'Tabel selesai dirender.');
            await nextFrame();
            await nextFrame();
            releaseLoadingUi();
        }

        function renderRows(rows) {
            if (!rows || rows.length === 0) {
                body.innerHTML = `
                    <tr>
                        <td colspan="${qualityColumns.length + 6}" class="loan-empty-state">
                            <strong>Data tidak ditemukan</strong>
                            Coba ubah periode atau filter agar hasil pivot tersedia.
                        </td>
                    </tr>
                `;
                return;
            }

            body.innerHTML = rows.map((row) => {
                const cells = row.values.map((value, index) => {
                    let extraClass = '';

                    if (value === null || value === undefined || value === '') {
                        extraClass = 'matrix-empty';
                    } else if (row.label === 'New Account') {
                        extraClass = 'matrix-new-account';
                    } else {
                        const rowRank = qualityRanks[row.label];
                        if (rowRank === index) {
                            extraClass = 'matrix-stagnant';
                        } else if (rowRank > index) {
                            extraClass = 'matrix-up';
                        } else {
                            extraClass = 'matrix-down';
                        }
                    }

                    return `<td class="${extraClass}">${formatNumber(value)}</td>`;
                }).join('');

                const metricCells = outputColumns.map((key) => {
                    return `<td>${formatNumber(row.metrics?.[key] ?? null)}</td>`;
                }).join('');

                return `
                    <tr>
                        <th>${row.label}</th>
                        ${cells}
                        <td class="matrix-total-col">${formatNumber(row.total)}</td>
                        ${metricCells}
                    </tr>
                `;
            }).join('');
        }

        async function renderRowsProgressively(rows, requestId) {
            if (!rows || rows.length === 0) {
                renderRows(rows);
                updateLoadingProgress(88, 'Render Tabel', 'Tidak ada data yang perlu dirender.');
                return;
            }

            const fragment = document.createDocumentFragment();
            const chunkSize = Math.max(12, Math.ceil(rows.length / 8)); // Optimal chunk: render dalam ~8 batches
            const isSmallDataset = rows.length <= 15;

            // Untuk dataset kecil, render sekaligus
            if (isSmallDataset) {
                rows.forEach((row) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = buildRowHtml(row);
                    fragment.appendChild(tr);
                });
                body.appendChild(fragment);
                updateLoadingProgress(87, 'Render Tabel', `Merender ${rows.length} baris...`);
                return;
            }

            // Untuk dataset besar, gunakan progressive rendering
            let processedCount = 0;
            for (let index = 0; index < rows.length; index += 1) {
                if (requestId !== activeMatrixRequestId || isNavigatingAway) {
                    return;
                }

                const row = rows[index];
                const tr = document.createElement('tr');
                tr.innerHTML = buildRowHtml(row);
                fragment.appendChild(tr);
                processedCount++;

                const isChunkBoundary = (processedCount % chunkSize === 0) || (index === rows.length - 1);
                if (isChunkBoundary) {
                    body.appendChild(fragment);
                    const progress = 55 + Math.round(((index + 1) / rows.length) * 35);
                    updateLoadingProgress(progress, 'Render Tabel', `Merender baris ${index + 1} dari ${rows.length}...`);
                    await nextFrame();
                }
            }
        }

        function buildRowHtml(row) {
            const rowRank = row.label !== 'New Account' ? qualityRanks[row.label] : -1;
            
            const cells = row.values.map((value, columnIndex) => {
                let extraClass = '';
                
                if (value === null || value === undefined || value === '') {
                    extraClass = 'matrix-empty';
                } else if (row.label === 'New Account') {
                    extraClass = 'matrix-new-account';
                } else if (rowRank === columnIndex) {
                    extraClass = 'matrix-stagnant';
                } else if (rowRank > columnIndex) {
                    extraClass = 'matrix-up';
                } else {
                    extraClass = 'matrix-down';
                }

                return `<td class="${extraClass}">${formatNumber(value)}</td>`;
            }).join('');

            const metricCells = outputColumns.map((key) => {
                return `<td>${formatNumber(row.metrics?.[key] ?? null)}</td>`;
            }).join('');

            return `
                <th>${row.label}</th>
                ${cells}
                <td class="matrix-total-col">${formatNumber(row.total)}</td>
                ${metricCells}
            `;
        }

        function renderFoot(grandTotals, grandTotalValue) {
            const totalCells = qualityColumns.map((column, index) => {
                return `<td>${formatNumber(grandTotals?.matrix?.[index] ?? null)}</td>`;
            }).join('');

            const metricTotals = outputColumns.map((key) => {
                return `<td>${formatNumber(grandTotals?.metrics?.[key] ?? null)}</td>`;
            }).join('');

            foot.innerHTML = `
                <th>Grand Total</th>
                ${totalCells}
                <td class="matrix-total-col">${formatNumber(grandTotalValue)}</td>
                ${metricTotals}
            `;
        }

        function resetMatrixState() {
            body.innerHTML = `
                <tr>
                    <td colspan="${qualityColumns.length + 6}" class="loan-empty-state">
                        <strong>Filter belum dijalankan</strong>
                        Pilih periode atau filter lain lalu klik <strong>Tampilkan</strong>.
                    </td>
                </tr>
            `;
            renderFoot([], null);
            periodBadge.textContent = '- vs -';
        }

        function setSelectOptions(select, items, placeholder, selectedValues = []) {
            select.innerHTML = '';
            select.dataset.state = items.length ? 'ready' : (periodInput.value ? 'empty' : 'disabled');
            const normalizedSelectedValues = Array.isArray(selectedValues)
                ? selectedValues.map(String)
                : [];

            // Gunakan DocumentFragment untuk batch DOM insertion
            const fragment = document.createDocumentFragment();
            items.forEach((item) => {
                const option = document.createElement('option');
                option.value = item;
                option.textContent = item;
                option.selected = normalizedSelectedValues.includes(String(item));
                fragment.appendChild(option);
            });
            select.appendChild(fragment);

            select.disabled = !periodInput.value;
            select.dataset.selected = JSON.stringify(
                normalizedSelectedValues.filter((value) => items.map(String).includes(value))
            );
            refreshSelectUi(select);
        }

        function setFilterLoadingState(isLoading) {
            filterSelects.forEach(({ element }) => {
                element.disabled = isLoading || !periodInput.value;
                element.dataset.state = isLoading ? 'loading' : (periodInput.value ? 'ready' : 'disabled');

                if (isLoading || !periodInput.value || !element.options.length) {
                    element.innerHTML = '';
                }

                refreshSelectUi(element);
            });
        }

        function scheduleFilterReload() {
            window.clearTimeout(filterReloadTimer);
            filterReloadTimer = window.setTimeout(function () {
                loadFilterOptions();
            }, 100);  // Reduced dari 250ms untuk performa lebih cepat
        }

        function refreshSelectUi(select) {
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                const placeholder = select.dataset.placeholder || '';
                initMultiSelect(select, placeholder);
                const selectedValues = parseSelectedDataset(select);
                const $select = window.jQuery(select);
                
                // Batch update: set data, trigger change, dan update summary sekaligus
                $select.val(selectedValues).trigger('change.select2');
                updateSelectSummary(select);
            }
        }

        function updateSelectSummary(select) {
            if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) {
                return;
            }

            const $select = window.jQuery(select);
            const select2 = $select.data('select2');

            if (!select2 || !select2.$container) {
                return;
            }

            const selectedItems = ($select.select2('data') || [])
                .filter((item) => item && item.id)
                .map((item) => String(item.text || '').trim())
                .filter(Boolean);

            const summary = selectedItems.length === 0
                ? (select.dataset.placeholder || '')
                : selectedItems.length <= 2
                    ? selectedItems.join(', ')
                    : `${selectedItems.slice(0, 2).join(', ')}, ...`;

            const rendered = select2.$container.find('.select2-selection__rendered');
            if (selectedItems.length === 0) {
                rendered
                    .html(`<span class="loan-select2-summary-main">${escapeHtml(select.dataset.placeholder || '')}</span>`)
                    .attr('title', select.dataset.placeholder || '')
                    .toggleClass('loan-filter-summary-empty', true);
                return;
            }

            rendered
                .html(`
                    <span class="loan-select2-summary">
                        <span class="loan-select2-summary-main">${escapeHtml(summary)}</span>
                        <span class="loan-select2-summary-count">${selectedItems.length} dipilih</span>
                    </span>
                `)
                .attr('title', selectedItems.join(', '))
                .toggleClass('loan-filter-summary-empty', false);
        }

        function appendParams(params, key, values) {
            values.forEach((value) => {
                if (value) {
                    params.append(`${key}[]`, value);
                }
            });
        }

        function collectSelectedValues(select) {
            return (window.jQuery(select).val() || []).filter(Boolean);
        }

        async function loadFilterOptions() {
            if (activeFilterController) {
                activeFilterController.abort();
            }

            const requestId = ++activeFilterRequestId;

            if (!periodInput.value) {
                setFilterLoadingState(false);
                activePeriodMeta.textContent = '-';
                comparisonPeriodMeta.textContent = '-';
                return;
            }

            activeFilterController = new AbortController();
            const timeoutId = window.setTimeout(function () {
                activeFilterController?.abort('timeout');
            }, 8000);  // Reduced dari 15000ms ke 8000ms untuk respons lebih cepat
            setFilterLoadingState(true);

            const params = new URLSearchParams();
            params.set('periode', periodInput.value);
            appendParams(params, 'segmen_dashboard', collectSelectedValues(segmenSelect));
            appendParams(params, 'produk_dashboard', collectSelectedValues(produkSelect));
            appendParams(params, 'cabang1', collectSelectedValues(cabangSelect));
            appendParams(params, 'unit1', collectSelectedValues(unitSelect));
            params.set('_ts', String(Date.now()));

            try {
                const response = await fetch(`${filtersUrl}?${params.toString()}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    signal: activeFilterController.signal,
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat opsi filter.');
                }

                const payload = await response.json();
                if (requestId !== activeFilterRequestId || isNavigatingAway) {
                    return;
                }

                activePeriodMeta.textContent = formatDate(payload.selected_period);
                comparisonPeriodMeta.textContent = formatDate(payload.comparison_period);
                updateTotalValueHeader(payload.selected_period);

                isRefreshingFilters = true;
                setSelectOptions(segmenSelect, payload.segments || [], 'Semua Segmen', parseSelectedDataset(segmenSelect));
                setSelectOptions(produkSelect, payload.products || [], 'Semua Produk', parseSelectedDataset(produkSelect));
                setSelectOptions(cabangSelect, payload.branches || [], 'Semua Kantor Cabang', parseSelectedDataset(cabangSelect));
                setSelectOptions(unitSelect, payload.units || [], 'Semua Unit Kerja', parseSelectedDataset(unitSelect));
                isRefreshingFilters = false;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setFilterLoadingState(false);
                    activePeriodMeta.textContent = '-';
                    comparisonPeriodMeta.textContent = '-';
                    filterSelects.forEach(({ element }) => {
                        element.dataset.state = 'empty';
                        refreshSelectUi(element);
                    });
                }
            } finally {
                window.clearTimeout(timeoutId);
                isRefreshingFilters = false;

                if (requestId !== activeFilterRequestId || activeFilterController?.signal.aborted) {
                    return;
                }

                activeFilterController = null;
                filterSelects.forEach(({ element }) => {
                    element.disabled = !periodInput.value;
                });
            }
        }

        async function loadMatrix(pushHistory = false) {
            if (activeController) {
                activeController.abort();
            }

            activeController = new AbortController();
            const requestId = ++activeMatrixRequestId;

            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                if (value) {
                    params.append(key, value);
                }
            }
            params.set('_ts', String(Date.now()));

            startLoadingProgress();

            try {
                const response = await fetch(`${dataUrl}?${params.toString()}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    signal: activeController.signal,
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat data dashboard.');
                }

                updateLoadingProgress(42, 'Memproses Respons', 'Data diterima, menyiapkan render tabel...');
                const payload = await response.json();
                if (requestId !== activeMatrixRequestId || isNavigatingAway) {
                    return;
                }

                updateLoadingProgress(52, 'Sinkronisasi Header', 'Memperbarui header dan metadata periode...');
                await nextFrame();
                await renderRowsProgressively(payload.matrix_rows, requestId);
                if (requestId !== activeMatrixRequestId || isNavigatingAway) {
                    return;
                }

                updateLoadingProgress(92, 'Menghitung Total', 'Menyusun grand total dan ringkasan...');
                renderFoot(payload.grand_totals, payload.grand_total_value);
                periodBadge.textContent = `${formatDate(payload.selected_period)} vs ${formatDate(payload.comparison_period)}`;
                updateTotalValueHeader(payload.selected_period);
                await nextFrame();

                if (pushHistory) {
                    const pageUrl = new URL(@json(route('report.dashboard-pinjaman.matrix')), window.location.origin);
                    params.forEach((value, key) => {
                        if (key === '_ts') {
                            return;
                        }

                        pageUrl.searchParams.append(key, value);
                    });
                    window.history.replaceState({}, '', pageUrl.toString());
                }

                await finishLoadingProgress();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    updateLoadingProgress(100, 'Gagal', 'Proses render gagal. Silakan coba lagi.');
                    body.innerHTML = `
                        <tr>
                            <td colspan="${qualityColumns.length + 6}" class="loan-empty-state">
                                <strong>Gagal memuat dashboard</strong>
                                Silakan coba lagi.
                            </td>
                        </tr>
                    `;
                    renderFoot([], null);
                    periodBadge.textContent = '- vs -';
                }

                await nextFrame();
                releaseLoadingUi();
            } finally {
                if (requestId === activeMatrixRequestId) {
                    activeController = null;
                }
            }
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            loadMatrix(true);
        });

        periodInput.addEventListener('change', function () {
            [segmenSelect, produkSelect, cabangSelect, unitSelect].forEach((element) => {
                element.dataset.selected = '[]';
            });

            resetMatrixState();
            loadFilterOptions();
        });

        [segmenSelect, produkSelect, cabangSelect, unitSelect].forEach((element) => {
            refreshSelectUi(element);

            window.jQuery(element).on('change', function () {
                syncSelectedDataset(element);
                updateSelectSummary(element);
                resetMatrixState();

                if (!isRefreshingFilters && periodInput.value) {
                    scheduleFilterReload();
                }
            });
        });

        overlay.classList.add('is-hidden');
        document.addEventListener('click', function (event) {
            const link = event.target.closest('a[href]');
            if (!link) {
                return;
            }

            const href = link.getAttribute('href') || '';
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
                return;
            }

            if (link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            isNavigatingAway = true;
            abortInFlightRequests();
            releaseLoadingUi();
        });

        window.addEventListener('beforeunload', function () {
            isNavigatingAway = true;
            abortInFlightRequests();
            releaseLoadingUi();
        });
        window.addEventListener('pagehide', abortInFlightRequests);
        setFilterLoadingState(!periodInput.value);
        updateTotalValueHeader(periodInput.value || @json($selectedPeriod));

        if (periodInput.value) {
            loadFilterOptions();
        }

    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mismatchForm = document.getElementById('loanMismatchForm');
        const mismatchPeriodInput = document.getElementById('loanMismatchPeriodeInput');
        const mismatchBranchSelect = document.getElementById('loanMismatchCabangSelect');
        const mismatchBody = document.getElementById('loanMismatchBody');
        const mismatchChip = document.getElementById('loanMismatchLoadingChip');
        const mismatchSubmitButton = document.getElementById('loanMismatchSubmitButton');
        const mismatchPeriodBadge = document.getElementById('loanMismatchPeriodBadge');
        const mismatchScanned = document.getElementById('loanMismatchScanned');
        const mismatchTotal = document.getElementById('loanMismatchTotal');
        const mismatchMatched = document.getElementById('loanMismatchMatched');
        const mismatchUnits = document.getElementById('loanMismatchUnits');
        const mismatchFiltersUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.filters'));
        const mismatchDataUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.data'));
        const mismatchExportUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.export'));
        const pageUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai'));
        let mismatchFilterController = null;
        let mismatchDataController = null;

        if (!mismatchForm) {
            return;
        }

        // Reuse the shared formatDate from the matrix script block (C6 - avoid duplication)
        // formatDate and intlNumberFormat are defined in the DOMContentLoaded above.
        // However since each <script> block has its own DOMContentLoaded scope, we define
        // lightweight wrappers here that mirror the same logic.
        const _mismatchIntlFmt = new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });

        function formatDate(value) {
            if (!value) {
                return '-';
            }

            const date = new Date(`${value}T00:00:00`);
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            });
        }

        // Unified, safe formatNumber using Intl singleton (B8 fix)
        function formatNumber(value) {
            if (value === null || value === undefined || value === '') {
                return '0';
            }
            const n = Number(value);
            return Number.isNaN(n) ? '0' : _mismatchIntlFmt.format(n);
        }

        function resetMismatchState(message = 'Pilih periode dan cabang lalu klik <strong>Proses</strong>.') {
            // Build state row safely to avoid XSS (B7)
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 4;
            td.className = 'loan-empty-state';
            td.innerHTML = '<strong>Audit belum dijalankan</strong> ' + message;
            tr.appendChild(td);
            mismatchBody.innerHTML = '';
            mismatchBody.appendChild(tr);
            mismatchScanned.textContent = '0';
            mismatchTotal.textContent = '0';
            mismatchMatched.textContent = '0';
            mismatchUnits.textContent = '0';
        }

        function updateMismatchBadge(period, branch) {
            mismatchPeriodBadge.textContent = `${formatDate(period)} | ${branch || 'Belum pilih cabang'}`;
        }

        function setMismatchLoadingState(isLoading) {
            mismatchChip.classList.toggle('d-none', !isLoading);
            mismatchSubmitButton.disabled = isLoading || !mismatchPeriodInput.value || !mismatchBranchSelect.value;
            mismatchPeriodInput.disabled = isLoading;
            mismatchBranchSelect.disabled = isLoading || !mismatchPeriodInput.value;
        }

        function populateMismatchBranches(branches, selectedBranch) {
            const normalizedSelected = String(selectedBranch || '').trim();
            mismatchBranchSelect.innerHTML = '<option value="">Pilih kantor cabang</option>';

            (branches || []).forEach((branch) => {
                const option = document.createElement('option');
                option.value = branch;
                option.textContent = branch;
                if (branch === normalizedSelected) {
                    option.selected = true;
                }
                mismatchBranchSelect.appendChild(option);
            });

            if (!normalizedSelected || !Array.from(mismatchBranchSelect.options).some((option) => option.value === normalizedSelected)) {
                mismatchBranchSelect.value = '';
            }

            mismatchBranchSelect.dataset.selected = mismatchBranchSelect.value || '';
            mismatchBranchSelect.disabled = !mismatchPeriodInput.value;
            mismatchSubmitButton.disabled = !mismatchPeriodInput.value || !mismatchBranchSelect.value;
        }

        async function loadMismatchBranches() {
            if (mismatchFilterController) {
                mismatchFilterController.abort();
            }

            if (!mismatchPeriodInput.value) {
                populateMismatchBranches([], '');
                updateMismatchBadge('', '');
                resetMismatchState();
                return;
            }

            mismatchFilterController = new AbortController();
            // Hard timeout of 15 seconds to prevent requests hanging forever (B9)
            const timeoutId = window.setTimeout(() => mismatchFilterController?.abort('timeout'), 15000);
            mismatchBranchSelect.disabled = true;

            try {
                const params = new URLSearchParams();
                params.set('periode', mismatchPeriodInput.value);
                params.set('_ts', String(Date.now()));

                const response = await fetch(`${mismatchFiltersUrl}?${params.toString()}`, {
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    signal: mismatchFilterController.signal,
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat daftar cabang.');
                }

                const payload = await response.json();
                populateMismatchBranches(payload.branches || [], mismatchBranchSelect.dataset.selected || '');
                updateMismatchBadge(payload.selected_period, mismatchBranchSelect.value || '');
            } catch (error) {
                if (error.name !== 'AbortError') {
                    populateMismatchBranches([], '');
                    resetMismatchState('Daftar cabang gagal dimuat. Ulangi proses filter.');
                }
            } finally {
                window.clearTimeout(timeoutId);
                mismatchFilterController = null;
            }
        }

        function renderMismatchTable(rows, period, branch) {
            if (!rows || rows.length === 0) {
                mismatchBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="loan-empty-state">
                            <strong>Tidak ada mismatch</strong>
                            Semua data pada cabang ini sesuai dengan rule audit.
                        </td>
                    </tr>
                `;
                return;
            }

            mismatchBody.innerHTML = rows.map((row, index) => {
                const exportParams = new URLSearchParams({
                    periode: period,
                    cabang1: branch,
                    unit1: row.unit,
                });

                return `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${row.unit}</td>
                        <td><strong>${formatNumber(row.mismatch_count)}</strong></td>
                        <td>
                            <a class="btn btn-sm btn-outline-success" href="${mismatchExportUrl}?${exportParams.toString()}">
                                <i class="fas fa-file-excel mr-1"></i>
                                Export Excel
                            </a>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        async function processMismatch(pushHistory = true) {
            if (mismatchDataController) {
                mismatchDataController.abort();
            }

            if (!mismatchPeriodInput.value || !mismatchBranchSelect.value) {
                resetMismatchState();
                updateMismatchBadge(mismatchPeriodInput.value, mismatchBranchSelect.value);
                return;
            }

            mismatchDataController = new AbortController();
            setMismatchLoadingState(true);

            try {
                const params = new URLSearchParams();
                params.set('periode', mismatchPeriodInput.value);
                params.set('cabang1', mismatchBranchSelect.value);
                params.set('_ts', String(Date.now()));

                const response = await fetch(`${mismatchDataUrl}?${params.toString()}`, {
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    signal: mismatchDataController.signal,
                });

                if (!response.ok) {
                    throw new Error('Gagal memproses audit mismatch.');
                }

                const payload = await response.json();
                renderMismatchTable(payload.summary_rows || [], payload.selected_period, payload.selected_branch);
                mismatchScanned.textContent = formatNumber(payload.audit?.scanned_rows);
                mismatchTotal.textContent = formatNumber(payload.audit?.mismatch_rows);
                mismatchMatched.textContent = formatNumber(payload.audit?.matched_rows);
                mismatchUnits.textContent = formatNumber(payload.audit?.units_with_mismatch);
                updateMismatchBadge(payload.selected_period, payload.selected_branch);

                if (pushHistory) {
                    const currentUrl = new URL(pageUrl, window.location.origin);
                    currentUrl.searchParams.set('mismatch_periode', mismatchPeriodInput.value);
                    currentUrl.searchParams.set('mismatch_cabang1', mismatchBranchSelect.value);
                    window.history.replaceState({}, '', currentUrl.toString());
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    resetMismatchState('Audit gagal diproses. Ulangi proses dan periksa filter.');
                }
            } finally {
                mismatchDataController = null;
                setMismatchLoadingState(false);
            }
        }

        mismatchPeriodInput.addEventListener('change', function () {
            mismatchBranchSelect.dataset.selected = '';
            resetMismatchState();
            updateMismatchBadge(mismatchPeriodInput.value, '');
            loadMismatchBranches();
        });

        mismatchBranchSelect.addEventListener('change', function () {
            mismatchBranchSelect.dataset.selected = mismatchBranchSelect.value || '';
            mismatchSubmitButton.disabled = !mismatchPeriodInput.value || !mismatchBranchSelect.value;
            resetMismatchState();
            updateMismatchBadge(mismatchPeriodInput.value, mismatchBranchSelect.value);
        });

        mismatchForm.addEventListener('submit', function (event) {
            event.preventDefault();
            processMismatch(true);
        });

        resetMismatchState();
        loadMismatchBranches().then(function () {
            if (mismatchPeriodInput.value && mismatchBranchSelect.value) {
                processMismatch(false);
            } else {
                setMismatchLoadingState(false);
            }
        });
    });
</script>
@endsection
