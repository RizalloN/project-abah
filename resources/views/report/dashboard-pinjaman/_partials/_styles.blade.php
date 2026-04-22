<style>
    :root {
        --loan-surface: #ffffff;
        --loan-surface-soft: #f8fbff;
        --loan-border: rgba(8, 87, 195, 0.12);
        --loan-border-strong: rgba(8, 87, 195, 0.2);
        --loan-text: #0f172a;
        --loan-muted: #5b7da7;
        --loan-blue: #0857c3; /* BRI Nusantara */
        --loan-blue-deep: #053b82; /* BRI Ink */
        --loan-blue-ink: #042a5f; /* BRI Night */
        --loan-blue-soft: #f2f7ff; /* BRI Mist */
        --loan-cyan: #71c5e8; /* BRI Mentari */
        --loan-red: #ef4444;
        --loan-radius: 20px;
        --loan-shadow: 0 18px 34px -28px rgba(4, 42, 95, 0.28);
    }

    .loan-dashboard {
        padding-bottom: 1.5rem;
        color: var(--loan-text);
        background: #fdfdfe;
    }

    .loan-shell,
    .loan-table-shell {
        position: relative;
        border: 1px solid var(--loan-border);
        border-radius: var(--loan-radius);
        background: #ffffff;
        box-shadow: var(--loan-shadow);
        overflow: hidden;
        transition: transform 0.3s ease;
    }

    .loan-shell::before,
    .loan-table-shell::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 6px;
        background: linear-gradient(90deg, var(--loan-blue-ink), var(--loan-blue), var(--loan-cyan));
        z-index: 5;
    }

    .loan-filter-grid .form-group {
        position: relative;
        margin-bottom: 0.75rem;
        padding: 0.75rem 0.85rem;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        background: #f8fafc;
        transition: all 0.2s ease;
        min-height: 90px;
    }

    .loan-filter-grid .form-group:focus-within {
        background: #ffffff;
        border-color: var(--loan-blue);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .loan-filter-label {
        display: block;
        font-size: 0.65rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.4rem;
    }

    .loan-filter-control {
        border-radius: 8px !important;
        min-height: 38px !important;
        height: 38px !important;
        border-color: #e2e8f0 !important;
        background: #ffffff !important;
        font-size: 0.85rem;
        font-weight: 700;
        color: #1e293b !important;
    }

    .loan-loading-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        border-radius: 999px;
        padding: 0.4rem 1rem;
        background: #f1f5f9;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 800;
        border: 1px solid #e2e8f0;
    }

    .loan-loading-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #14b8a6;
        animation: loanPulse 1.6s infinite;
    }

    @keyframes loanPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(20, 184, 166, 0.45); }
        70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(20, 184, 166, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(20, 184, 166, 0); }
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
        top: 38px;
        height: 34px;
    }


    .loan-matrix .matrix-before { 
        background: var(--loan-blue-deep) !important; 
        color: #ffffff !important;
        position: sticky; 
        left: 0; 
        z-index: 20; 
        text-align: left; 
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
        cursor: pointer;
    }

    .loan-matrix tbody tr.loan-drill-row.is-selected th,
    .loan-matrix tbody tr.loan-drill-row.is-selected td {
        outline: 2px solid rgba(8, 87, 195, 0.3);
        outline-offset: -2px;
        background-color: #eff6ff !important;
    }

    .loan-drill-modal .modal-dialog {
        max-width: min(1320px, calc(100vw - 2rem));
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
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        background: #f8fafc;
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
        color: #64748b;
        font-weight: 800;
    }

    .loan-drill-footer-note {
        margin-top: 0.75rem;
        font-size: 0.72rem;
        font-weight: 700;
        color: #64748b;
    }

    /* Legend Styles */
    .loan-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        margin-top: 1.5rem;
        padding: 1.25rem;
        background: var(--loan-surface-soft);
        border-radius: 16px;
        border: 1px solid var(--loan-border);
    }

    .loan-legend-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.82rem;
        font-weight: 800;
        color: var(--loan-blue-ink);
        padding: 0.5rem 1rem;
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(4, 42, 95, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.5);
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
        grid-template-columns: repeat(4, 1fr);
        gap: 1.25rem;
        margin: 1.5rem 0;
    }

    .loan-mismatch-card {
        position: relative;
        border: 1px solid var(--loan-border);
        border-radius: 16px;
        padding: 1.25rem;
        background: linear-gradient(135deg, #ffffff, var(--loan-surface-soft));
        box-shadow: 0 4px 12px rgba(4, 42, 95, 0.04);
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .loan-mismatch-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(4, 42, 95, 0.08);
    }

    .loan-mismatch-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
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
        background: var(--loan-surface-soft);
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
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(14px) saturate(160%);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 100;
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 12px;
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
        background: #f1f5f9;
        border-radius: 999px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }

    .loan-loading-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--loan-blue-deep), var(--loan-blue));
        width: 0%;
        transition: width 0.3s ease;
    }

    /* ── Select2 overrides ── */
    .select2-container--bootstrap4 .select2-selection {
        border-radius: 8px !important;
        min-height: 38px !important;
        border-color: #e2e8f0 !important;
        background: #ffffff !important;
    }

    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: 36px !important;
        font-weight: 700 !important;
        color: #1e293b !important;
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
        border-radius: 12px;
        height: 40px;
        font-weight: 700;
        border-width: 2px;
        transition: all 0.2s ease;
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
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(8, 87, 195, 0.15);
    }

    /* ── Capture Status Modal (Series Logic) ── */
    .capture-status-modal .modal-content {
        border-radius: 24px;
        border: none;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
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

    @media (max-width: 768px) {
        .loan-mismatch-summary {
            grid-template-columns: repeat(2, 1fr);
        }
        .loan-filter-grid .form-group {
            min-height: auto;
        }
    }
</style>
