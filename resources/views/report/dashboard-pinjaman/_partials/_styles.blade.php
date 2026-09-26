<style>
    :root {
        --loan-surface: #ffffff;
        --loan-surface-soft: var(--app-surface-soft, #f7faff);
        --loan-border: var(--app-line, rgba(8, 87, 195, 0.14));
        --loan-border-strong: rgba(8, 87, 195, 0.24);
        --loan-text: var(--app-ink, #082b59);
        --loan-muted: var(--app-muted, #526987);
        --loan-blue: var(--bri-nusantara, #0857c3); /* BRI Nusantara */
        --loan-blue-deep: var(--bri-ink, #053b82); /* BRI Ink */
        --loan-blue-ink: var(--bri-night, #042a5f); /* BRI Night */
        --loan-blue-soft: var(--bri-mist, #f2f7ff); /* BRI Mist */
        --loan-cyan: var(--bri-mentari, #71c5e8); /* BRI Mentari */
        --loan-red: #ef4444;
        --loan-radius: var(--app-radius, 14px);
        --loan-shadow: var(--app-shadow, 0 18px 42px -30px rgba(4, 42, 95, 0.34));
        --loan-focus: rgba(48, 127, 226, 0.34);
    }

    .loan-dashboard {
        padding-bottom: 1.5rem;
        color: var(--loan-text);
        background: transparent;
    }

    .loan-shell,
    .loan-table-shell {
        position: relative;
        border: 1px solid var(--loan-border);
        border-radius: var(--loan-radius);
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        box-shadow: var(--loan-shadow);
        overflow: hidden;
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }

    .loan-shell::before,
    .loan-table-shell::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 1px;
        background: linear-gradient(90deg, var(--loan-blue-ink), var(--loan-blue), var(--loan-cyan));
        z-index: 5;
    }

    .loan-filter-grid .form-group {
        position: relative;
        margin-bottom: 0.75rem;
        padding: 0.75rem 0.85rem;
        border: 1px solid var(--loan-border);
        border-radius: 11px;
        background: linear-gradient(180deg, #f7faff 0%, #ffffff 100%);
        transition: border-color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
        min-height: 90px;
    }

    .loan-filter-grid .form-group:focus-within {
        background: #ffffff;
        border-color: var(--loan-blue);
        box-shadow: 0 0 0 3px var(--loan-focus);
    }

    .loan-filter-label {
        display: block;
        font-size: 0.65rem;
        font-weight: 800;
        color: var(--loan-muted);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.4rem;
    }

    .loan-filter-control {
        border-radius: 10px !important;
        min-height: 38px !important;
        height: 38px !important;
        border-color: #c4d6eb !important;
        background: #ffffff !important;
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--loan-text) !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }

    .loan-filter-control:focus,
    .loan-filter-control:focus-visible {
        border-color: var(--loan-blue) !important;
        outline: none !important;
        box-shadow: 0 0 0 3px var(--loan-focus) !important;
    }

    .loan-loading-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        border-radius: 999px;
        padding: 0.4rem 1rem;
        background: var(--loan-blue-soft);
        color: var(--loan-blue-deep);
        font-size: 0.75rem;
        font-weight: 800;
        border: 1px solid var(--loan-border-strong);
    }

    .loan-loading-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: var(--loan-blue);
        animation: loanPulse 1.6s infinite;
    }

    @keyframes loanPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(8, 87, 195, 0.38); }
        70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(8, 87, 195, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(8, 87, 195, 0); }
    }

    .loan-filter-meta {
        display: flex;
        gap: 1.25rem;
        font-size: 0.8rem;
        color: var(--loan-muted);
        font-weight: 700;
    }

    .loan-filter-meta strong {
        color: var(--loan-blue-ink);
        font-weight: 800;
    }

    .loan-title-hero {
        position: relative;
        margin-bottom: 1rem;
        padding: 1.25rem 1.35rem;
        background: linear-gradient(125deg, #ffffff 0%, #f3f8ff 72%, #edf6ff 100%);
        color: var(--loan-blue-ink);
        border: 1px solid var(--loan-border);
        border-radius: var(--loan-radius);
        box-shadow: var(--loan-shadow);
    }

    .loan-title-hero__wrap {
        width: 100%;
        text-align: left;
        padding: 0;
    }

    .loan-title-hero__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        margin-bottom: 0.4rem;
        padding: 0.2rem 0.6rem;
        border-radius: 999px;
        background: var(--loan-blue-soft);
        color: var(--loan-blue);
        font-size: 0.6rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        border: 1px solid rgba(8, 87, 195, 0.1);
    }

    .loan-title-hero__badge i {
        color: var(--loan-blue);
        opacity: 0.7;
    }

    .loan-title-hero__title {
        margin: 0;
        font-size: 1.45rem;
        font-weight: 900;
        color: var(--loan-blue-ink);
        letter-spacing: -0.025em;
        line-height: 1.1;
    }

    .loan-title-hero__desc {
        margin: 0.35rem 0 0;
        color: var(--loan-muted);
        font-size: 0.8rem;
        line-height: 1.5;
        max-width: 800px;
        font-weight: 600;
    }

    @media (max-width: 575.98px) {
        .loan-title-hero {
            padding: 1rem 0.75rem;
            border-radius: 0;
        }
        .loan-title-hero__title {
            font-size: 1.25rem;
        }
    }

    /* ── Matrix Specific ─────────────────────────── */
    .loan-matrix-wrap { 
        overflow: auto; 
        max-height: 80vh; 
        border-radius: 12px; 
        border: 1px solid #e2e8f0;
    }
    
    .loan-matrix { 
        width: 100%; 
        min-width: 1600px; 
        border-collapse: separate; 
        border-spacing: 0; 
        font-size: 0.85rem;
    }
    
    .loan-matrix th, .loan-matrix td { 
        padding: 4px 8px; 
        border-right: 1px solid rgba(8, 87, 195, 0.08); 
        border-bottom: 1px solid rgba(8, 87, 195, 0.08); 
        text-align: right; 
        font-weight: 700;
        vertical-align: middle;
    }
    
    .loan-matrix thead th { 
        position: sticky;
        top: 0;
        z-index: 10;
        background: var(--loan-blue-ink) !important;
        backdrop-filter: blur(8px);
        color: #ffffff; 
        text-align: center; 
        font-weight: 800; 
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid rgba(255, 255, 255, 0.15);
        vertical-align: middle !important;
        height: 38px;
        padding: 4px 8px !important;
    }

    .loan-matrix thead tr:nth-child(2) th {
        top: var(--abah-table-head-top, 0px);
        height: 34px;
    }


    .loan-matrix thead th.matrix-before {
        background: var(--loan-blue-deep) !important;
        color: #ffffff !important;
        position: sticky !important;
        top: var(--abah-table-head-top, 0px) !important;
        left: 0 !important;
        z-index: 50 !important;
        text-align: left;
    }

    .loan-matrix thead tr:first-child th.matrix-before {
        top: 0 !important;
    }

    .loan-matrix tbody th { 
        background: #ffffff; 
        color: var(--loan-blue-ink); 
        position: sticky; 
        left: 0; 
        z-index: 15; 
        text-align: left; 
        border-left: 5px solid var(--loan-blue);
        font-weight: 800;
        box-shadow: 2px 0 5px rgba(0,0,0,0.02);
    }

    .loan-matrix tbody tr:hover th,
    .loan-matrix tbody tr:hover td {
        background-color: #f8fbff !important;
    }

    .loan-matrix .matrix-total-col { 
        background: #f8fafc !important; 
        color: #0f172a !important; 
        font-weight: 800; 
    }

    /* Matrix State Colors - Premium Palette */
    .matrix-up { 
        background: #dcfce7 !important; 
        color: #15803d !important; 
        border: 1px solid #bbf7d0 !important;
    }
    .matrix-down { 
        background: #fee2e2 !important; 
        color: #b91c1c !important;
        border: 1px solid #fecaca !important;
    }
    .matrix-stagnant { 
        background: var(--loan-blue-soft) !important; 
        color: var(--loan-blue) !important;
        border: 1px solid rgba(8, 87, 195, 0.1) !important;
    }
    .matrix-new-account { 
        background: #f0f7ff !important; 
        color: #2563eb !important;
        border: 1px solid #bfdbfe !important;
        font-weight: 800 !important;
    }
    .matrix-empty { color: rgba(15, 23, 42, 0.08) !important; }

    .loan-matrix tbody tr.loan-drill-row {
        cursor: default;
    }

    .loan-matrix tbody td.loan-drill-cell {
        cursor: zoom-in;
    }

    .loan-matrix tbody tr.loan-drill-row.is-selected th,
    .loan-matrix tbody tr.loan-drill-row.is-selected td {
        outline: 2px solid rgba(8, 87, 195, 0.3);
        outline-offset: -2px;
        background-color: #eff6ff !important;
    }

    .loan-matrix tbody td.loan-drill-cell.is-selected {
        outline: 2px solid rgba(8, 87, 195, 0.55);
        outline-offset: -2px;
        background-color: #dbeafe !important;
    }

    .loan-drill-modal .modal-dialog {
        max-width: min(1320px, calc(100vw - 2rem));
    }

    @media (min-width: 1600px) {
        .loan-drill-modal .modal-dialog {
            max-width: min(1800px, 94vw);
        }
    }

    @media (min-width: 2400px) {
        .loan-drill-modal .modal-dialog {
            max-width: min(2400px, 95vw);
        }
    }

    .loan-drill-modal {
        z-index: 1065;
    }

    .modal-backdrop.loan-drill-backdrop {
        z-index: 1055;
    }

    .loan-drill-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .loan-drill-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        font-size: 0.75rem;
        font-weight: 800;
        color: #475569;
    }

    .loan-drill-meta span {
        border: 1px solid var(--loan-border);
        border-radius: 999px;
        background: var(--loan-blue-soft);
        padding: 0.35rem 0.7rem;
    }

    .loan-drill-table-wrap {
        overflow: auto;
        max-height: 58vh;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    }

    .loan-drill-table {
        width: 100%;
        min-width: 2200px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.76rem;
    }

    .loan-drill-table th,
    .loan-drill-table td {
        padding: 4px 8px;
        border-right: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
        white-space: nowrap;
        vertical-align: top;
        font-size: 0.72rem;
    }

    .loan-drill-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--loan-blue-ink);
        color: #ffffff;
        font-size: 0.68rem;
        text-transform: uppercase;
    }

    .loan-drill-state {
        padding: 2rem;
        text-align: center;
        color: var(--loan-muted);
        font-weight: 800;
    }

    .loan-drill-footer-note {
        margin-top: 0.75rem;
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--loan-muted);
    }

    /* Legend Styles */
    .loan-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 1.5rem;
        padding: 1.25rem;
        background: var(--loan-surface-soft);
        border-radius: var(--loan-radius);
        border: 1px solid var(--loan-border);
    }

    .loan-legend-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.82rem;
        font-weight: 800;
        color: var(--loan-blue-ink);
        padding: 0.5rem 0.85rem;
        background: #ffffff;
        border-radius: 9px;
        box-shadow: 0 8px 20px -18px rgba(4, 42, 95, 0.4);
        border: 1px solid var(--loan-border);
    }

    .loan-legend-swatch {
        width: 14px;
        height: 14px;
        border-radius: 4px;
        display: inline-block;
        flex-shrink: 0;
    }

    /* Matrix State Colors - Extended to Legend */
    .loan-legend-swatch.matrix-up { background-color: #22c55e !important; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1); }
    .loan-legend-swatch.matrix-down { background-color: #ef4444 !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1); }
    .loan-legend-swatch.matrix-stagnant { background-color: var(--loan-blue) !important; box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.1); }
    .loan-legend-swatch.matrix-new-account { background-color: #3b82f6 !important; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }


    /* ── Mismatch Specific ───────────────────────── */
    .loan-mismatch-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1.25rem;
        margin: 1.5rem 0;
    }

    .loan-mismatch-card {
        position: relative;
        border: 1px solid var(--loan-border);
        border-radius: var(--loan-radius);
        padding: 1.25rem;
        background: linear-gradient(135deg, #ffffff, var(--loan-surface-soft));
        box-shadow: 0 14px 30px -26px rgba(4, 42, 95, 0.38);
        overflow: hidden;
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }

    .loan-mismatch-card:hover {
        border-color: var(--loan-border-strong);
        box-shadow: 0 18px 36px -28px rgba(4, 42, 95, 0.48);
    }

    .loan-mismatch-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 1px;
        background: linear-gradient(90deg, var(--loan-blue), var(--loan-cyan));
    }

    .loan-audit-label {
        font-size: 0.72rem;
        font-weight: 800;
        color: var(--loan-muted);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.5rem;
        display: block;
    }

    .loan-audit-value {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--loan-blue-ink);
        line-height: 1;
        display: block;
    }

    .loan-mismatch-table-shell {
        border-radius: var(--loan-radius);
        overflow: hidden;
    }

    .loan-mismatch-table-wrap {
        margin-top: 1rem;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--loan-border);
    }

    .loan-mismatch-table thead th {
        background: var(--loan-blue-ink) !important;
        color: #ffffff !important;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 12px;
        border: none;
    }

    .loan-table-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
    }

    .loan-table-heading h5 {
        margin: 0;
        font-weight: 800;
        color: var(--loan-blue-ink);
    }

    .loan-table-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.8rem;
        background: var(--loan-blue-soft);
        color: var(--loan-blue-deep);
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 800;
        border: 1px solid rgba(8, 87, 195, 0.1);
    }

    .loan-empty-state {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--loan-muted);
        background: linear-gradient(180deg, #fbfdff 0%, var(--loan-blue-soft) 100%);
    }

    .loan-empty-state strong {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--loan-blue-ink);
    }

    /* ── Loading Overlay ─────────────────────────── */
    .loan-table-stage {
        position: relative;
        min-height: 400px;
    }

    .loan-loading-overlay {
        position: absolute;
        inset: 0;
        background: rgba(248, 251, 255, 0.94);
        backdrop-filter: blur(8px) saturate(120%);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 100;
        transition: opacity 0.24s ease, visibility 0.24s ease, transform 0.24s ease;
        border: 1px solid var(--loan-border);
        border-radius: var(--loan-radius);
    }

    .loan-loading-overlay.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transform: scale(1.02);
    }

    .loan-loading-title {
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--loan-blue-ink);
        margin-top: 1rem;
    }

    .loan-loading-copy {
        font-size: 0.85rem;
        color: var(--loan-muted);
        margin-bottom: 1.5rem;
    }

    .loan-loading-progress {
        width: 280px;
        max-width: 90%;
    }

    .loan-loading-progress-meta {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--loan-blue-deep);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .loan-loading-progress-track {
        height: 8px;
        background: #eaf2fc;
        border-radius: 999px;
        overflow: hidden;
        border: 1px solid var(--loan-border);
    }

    .loan-loading-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--loan-blue-deep), var(--loan-blue));
        width: 0%;
        transition: width 0.3s ease;
    }

    /* ── Select2 overrides ── */
    .select2-container--bootstrap4 .select2-selection {
        border-radius: 10px !important;
        min-height: 38px !important;
        border-color: #c4d6eb !important;
        background: #ffffff !important;
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }

    .select2-container--bootstrap4.select2-container--focus .select2-selection,
    .select2-container--bootstrap4.select2-container--open .select2-selection {
        border-color: var(--loan-blue) !important;
        box-shadow: 0 0 0 3px var(--loan-focus) !important;
    }

    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: 36px !important;
        font-weight: 700 !important;
        color: var(--loan-text) !important;
        font-size: 0.85rem !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple {
        min-height: 38px !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__rendered {
        display: flex !important;
        align-items: center !important;
    }

    .loan-select2-option {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0;
    }

    .loan-select2-option input[type="checkbox"] {
        margin: 0;
    }

    /* Summary Tables Styles */
    .loan-summary-table-wrap { 
        width: 100%;
        margin-bottom: 1.25rem; 
        border-radius: 12px; 
        overflow: hidden; 
        border: 1px solid var(--loan-border); 
        background: #fff; 
    }
    
    .loan-summary-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.78rem; }
    
    .loan-summary-table thead th { 
        background: var(--loan-blue-ink); 
        color: #ffffff; 
        text-align: center; 
        padding: 4px 4px; 
        border: 1px solid rgba(255, 255, 255, 0.15); 
        font-weight: 800;
        vertical-align: middle;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        font-size: 0.7rem;
    }

    .loan-summary-table td {
        padding: 3px 8px;
        border-right: 1px solid rgba(8, 87, 195, 0.06);
        border-bottom: 1px solid rgba(8, 87, 195, 0.06);
        text-align: right; /* Accounting standard: numbers to the right */
        font-weight: 700;
        color: #334155;
        vertical-align: middle;
    }

    .text-start-important { text-align: left !important; }
    .text-center-important { text-align: center !important; }
    
    .loan-summary-table thead th.sub-head { 
        background: var(--loan-blue-deep); 
        color: #ffffff;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .loan-branch-subtotal {
        background: var(--loan-blue-ink) !important;
    }

    .loan-branch-subtotal td {
        color: #ffffff !important;
        font-weight: 900 !important;
    }

    .loan-summary-title { background: var(--loan-blue); color: white; padding: 8px 16px; font-weight: 800; text-transform: uppercase; font-size: 0.9rem; border-radius: 12px 12px 0 0; }
    .loan-summary-section-title { width: 100%; background: var(--loan-blue-ink); color: white; text-align: center; padding: 6px; font-weight: 800; font-size: 0.95rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1); text-transform: uppercase; }
    
    .loan-section-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin: 1rem 0 0.5rem;
        padding-left: 0.5rem;
        border-left: 5px solid var(--loan-blue);
    }

    .loan-section-header h3 {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--loan-blue-ink);
        text-transform: uppercase;
    }
    .achieve-positive { color: #10b981 !important; font-weight: 800; }
    .achieve-negative { color: #ef4444 !important; font-weight: 800; }
    .achieve-neutral { color: #f59e0b !important; font-weight: 800; }

    /* ── Percentage Data Bars (International UI Standard) ── */
    .pct-data-bar-wrap {
        position: relative;
        width: 100%;
        min-width: 65px;
        height: 16px;
        background: #f1f5f9; /* Subtle track */
        border-radius: 4px;
        overflow: hidden;
        display: flex;
        align-items: center;
        border: 1px solid rgba(0,0,0,0.03);
    }
    
    .pct-data-bar {
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        opacity: 0.25; /* Subtle background fill */
    }
    
    .pct-data-bar.bar-success { background: #10b981; }
    .pct-data-bar.bar-danger { background: #ef4444; }
    .pct-data-bar.bar-warning { background: #f59e0b; }
    
    .pct-data-label {
        position: relative;
        z-index: 2;
        width: 100%;
        text-align: center;
        font-weight: 800;
        font-size: 0.65rem;
        color: #1e293b;
    }

    /* ── Capture & Export Buttons ── */
    .btn-capture-all {
        border-radius: 11px;
        height: 40px;
        font-weight: 700;
        border-width: 2px;
        transition: border-color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-snapshot {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        padding: 0;
        cursor: pointer;
    }

    .btn-snapshot:hover {
        background: #f8fbff;
        border-color: var(--loan-blue);
        color: var(--loan-blue);
        box-shadow: 0 4px 10px rgba(8, 87, 195, 0.15);
    }

    .btn-capture-all:focus-visible,
    .btn-snapshot:focus-visible,
    .capture-status-modal .btn-primary:focus-visible {
        outline: 3px solid var(--loan-focus);
        outline-offset: 2px;
    }

    /* ── Capture Status Modal (Series Logic) ── */
    .capture-status-modal .modal-content {
        border-radius: var(--loan-radius);
        border: 1px solid var(--loan-border);
        box-shadow: 0 30px 72px -36px rgba(4, 42, 95, 0.54);
        overflow: hidden;
    }

    .capture-status-modal .modal-body {
        padding: 3rem 2rem;
    }

    .capture-status-modal-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2.5rem;
    }

    .icon-loading { background: rgba(8, 87, 195, 0.1); color: var(--loan-blue); }
    .icon-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .icon-success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }

    .capture-status-modal .btn-primary {
        border-radius: 12px;
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    /* Fix for backdrop overlaying too dark */
    .modal-backdrop.show {
        opacity: 0.15 !important;
        background-color: #0f172a !important;
    }

    body.modal-open {
        padding-right: 0 !important;
    }

    @media (max-width: 991.98px), (max-height: 760px) {
        .loan-dashboard {
            padding-top: 0.75rem !important;
        }

        .loan-title-hero {
            margin-bottom: 0.65rem;
            padding: 0.8rem 0.75rem;
        }

        .loan-title-hero__title {
            font-size: clamp(1.15rem, 4vw, 1.45rem);
            line-height: 1.12;
        }

        .loan-title-hero__desc {
            display: -webkit-box;
            overflow: hidden;
            margin-top: 0.25rem;
            font-size: 0.74rem;
            line-height: 1.35;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 1;
        }

        .loan-shell,
        .loan-table-shell {
            margin-bottom: 0.85rem !important;
        }

        .loan-shell .card-body,
        .loan-table-shell .card-body {
            padding: 0.85rem !important;
        }

        .loan-filter-grid .form-group {
            min-height: auto;
            padding: 0.58rem 0.68rem;
        }

        .loan-filter-label {
            margin-bottom: 0.25rem;
            font-size: 0.62rem;
            letter-spacing: 0.05em;
        }

        .loan-filter-control {
            min-height: 34px !important;
            height: 34px !important;
            font-size: 0.76rem;
        }

        .loan-section-header {
            margin-top: 0.75rem;
            gap: 0.65rem;
        }

        .loan-section-header h3 {
            font-size: clamp(0.98rem, 3.2vw, 1.15rem);
            line-height: 1.15;
        }

        .loan-matrix-wrap,
        .loan-summary-table-wrap {
            max-height: calc(100vh - 220px);
            overflow: auto;
        }
    }

    @media (orientation: landscape) and (max-height: 640px) {
        .loan-title-hero__badge,
        .loan-title-hero__desc,
        .loan-section-header .legend-box span {
            display: none !important;
        }

        .loan-title-hero {
            padding-top: 0.55rem;
            padding-bottom: 0.55rem;
        }

        .loan-shell .card-body,
        .loan-table-shell .card-body {
            padding: 0.65rem !important;
        }

        .loan-matrix-wrap,
        .loan-summary-table-wrap {
            max-height: none;
            overflow-x: auto;
            overflow-y: visible;
        }
    }

    @media (max-width: 768px) {
        .loan-mismatch-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .loan-filter-grid .form-group {
            min-height: auto;
        }
    }

    @media (max-width: 420px) {
        .loan-title-hero {
            padding: 1rem;
        }

        .loan-title-hero__title {
            overflow-wrap: anywhere;
        }

        .loan-filter-meta,
        .loan-table-heading {
            flex-wrap: wrap;
            gap: 0.55rem;
        }

        .loan-mismatch-summary {
            grid-template-columns: minmax(0, 1fr);
            gap: 0.75rem;
        }

        .loan-mismatch-card,
        .loan-legend {
            padding: 1rem;
        }
    }

    @media (pointer: coarse) {
        .loan-filter-control,
        .btn-capture-all {
            min-height: 44px !important;
            height: 44px !important;
        }

        .select2-container--bootstrap4 .select2-selection {
            min-height: 44px !important;
        }

        .select2-container--bootstrap4 .select2-selection--single {
            height: 44px !important;
        }

        .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
            line-height: 42px !important;
        }

        .btn-snapshot {
            width: 44px;
            height: 44px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .loan-shell,
        .loan-table-shell,
        .loan-filter-grid .form-group,
        .loan-filter-control,
        .loan-loading-dot,
        .loan-mismatch-card,
        .loan-loading-overlay,
        .loan-loading-progress-bar,
        .select2-container--bootstrap4 .select2-selection,
        .btn-capture-all,
        .btn-snapshot,
        .pct-data-bar {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }
</style>
