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
        --loan-blue-soft: #eff6ff;
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
        border-radius: 20px;
        background: #ffffff;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .loan-shell::before,
    .loan-table-shell::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 6px;
        background: linear-gradient(90deg, #0f172a, #1e293b, #334155);
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
        padding: 10px 12px; 
        border-right: 1px solid #f1f5f9; 
        border-bottom: 1px solid #f1f5f9; 
        text-align: right; 
        font-weight: 700;
    }
    
    .loan-matrix thead th { 
        position: sticky;
        top: 0;
        z-index: 10;
        background: #0f172a !important; 
        color: #f8fafc; 
        text-align: center; 
        font-weight: 800; 
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid #334155;
    }

    .loan-matrix thead tr:nth-child(2) th {
        top: 41px;
    }

    .loan-matrix .matrix-before { 
        background: #1e293b !important; 
        color: #f8fafc !important;
        position: sticky; 
        left: 0; 
        z-index: 20; 
        text-align: left; 
    }

    .loan-matrix tbody th { 
        background: #ffffff; 
        color: #0f172a; 
        position: sticky; 
        left: 0; 
        z-index: 15; 
        text-align: left; 
        border-left: 5px solid #0f172a;
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
        background: #f8fafc !important; 
        color: #475569 !important;
        border: 1px solid #e2e8f0 !important;
    }
    .matrix-new-account { 
        background: #f0f7ff !important; 
        color: #2563eb !important;
        border: 1px solid #bfdbfe !important;
        font-weight: 800 !important;
    }
    .matrix-empty { color: #f1f5f9 !important; }

    /* ── Loading Overlay ─────────────────────────── */
    .loan-table-stage {
        position: relative;
        min-height: 400px;
    }

    .loan-loading-overlay {
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(8px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 100;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 12px;
    }

    .loan-loading-overlay.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .loan-loading-title {
        font-size: 1.1rem;
        font-weight: 800;
        color: #0f172a;
        margin: 1.25rem 0 0.25rem;
    }

    .loan-loading-copy {
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 1.5rem;
    }

    .loan-loading-progress {
        width: 240px;
    }

    .loan-loading-progress-meta {
        display: flex;
        justify-content: space-between;
        font-size: 0.7rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .loan-loading-progress-track {
        height: 6px;
        background: #f1f5f9;
        border-radius: 999px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }

    .loan-loading-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #0f172a, #2563eb);
        width: 0%;
        transition: width 0.3s ease;
    }

    .loan-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        margin-top: 1.5rem;
        padding: 1rem 1.25rem;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }

    .loan-legend-item {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: #475569;
    }

    .loan-legend-swatch {
        width: 16px;
        height: 16px;
        border-radius: 4px;
        border: 1px solid rgba(0,0,0,0.05);
    }

    .loan-summary-table-wrap { 
        width: 100%;
        margin-bottom: 1.25rem; 
        border-radius: 12px; 
        overflow: hidden; 
        border: 1px solid var(--loan-border); 
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.08); 
        background: #fff; 
    }
    
    .loan-summary-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.82rem; }
    
    .legend-box {
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid var(--loan-border);
        border-radius: 8px;
        padding: 4px 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--loan-muted);
        margin-bottom: 0.5rem;
        letter-spacing: 0.02em;
    }
    
    /* Main Headers */
    .loan-summary-table thead th { 
        background: #002d5a; 
        color: #ffffff; 
        text-align: center; 
        padding: 6px 4px; 
        border: 1px solid rgba(255, 255, 255, 0.15); 
        font-weight: 800;
        vertical-align: middle;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        font-size: 0.72rem;
    }
    
    /* Sub Headers / Data Range Headers */
    .loan-summary-table thead th.sub-head { 
        background: #004280; 
        color: #ffffff;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .loan-summary-table thead th.accent-head {
        background: #0d1117;
    }
    
    /* Body Styling */
    .loan-summary-table tbody td { 
        padding: 4px 4px; 
        border: 1px solid #e2e8f0; 
        text-align: right; 
        font-weight: 600; 
        color: #334155;
        vertical-align: middle;
        font-size: 0.75rem;
    }

    .loan-summary-table tbody td.text-center-v {
        vertical-align: middle !important;
        text-align: center !important;
    }

    .merged-branch-cell {
        background: linear-gradient(135deg, #f0f7ff 0%, #e0f2fe 100%) !important;
        border-right: 1px solid var(--loan-border) !important;
        border-left: 6px solid #004280 !important;
        font-weight: 800 !important;
        color: #002d5a !important;
        text-transform: uppercase;
        text-align: center !important;
        vertical-align: middle !important;
        font-size: 0.68rem;
        padding: 4px !important;
        box-shadow: inset 2px 0 10px rgba(0, 66, 128, 0.05);
    }

    .loan-branch-subtotal {
        background: #1e293b !important;
    }

    .loan-branch-subtotal td {
        color: #ffffff !important;
        font-weight: 900 !important;
        border-top: 2px solid #030712 !important;
        border-bottom: 2px solid #334155 !important;
        font-size: 0.76rem !important;
        padding-top: 4px !important;
        padding-bottom: 4px !important;
    }

    .loan-branch-subtotal td[rowspan] {
        color: #1e293b !important;
        background: #f8fbff !important;
        border-bottom: 2px solid #cbd5e1 !important;
    }
    
    /* Category/Branch Columns (Left Aligned) */
    .loan-summary-table tbody td.text-start-important {
        text-align: left !important;
        font-weight: 700;
        color: #0f172a;
    }

    .loan-summary-table tbody tr:nth-child(even) {
        background-color: #f8fbff;
    }

    .loan-summary-table tbody tr:hover {
        background-color: #f1f5f9;
    }
    
    .loan-summary-title { background: #00529c; color: white; padding: 12px 20px; font-weight: 800; text-transform: uppercase; font-size: 1rem; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0; }
    .loan-summary-section-title { width: 100%; background: #1e293b; color: white; text-align: center; padding: 10px; font-weight: 800; font-size: 1.05rem; border-bottom: 2px solid #334155; text-transform: uppercase; }
    
    .achieve-positive { background: #dcfce7 !important; color: #166534 !important; font-weight: 800; }
    .achieve-negative { background: #fee2e2 !important; color: #991b1b !important; font-weight: 800; }
    .achieve-neutral { background: #f1f5f9 !important; color: #475569 !important; }

    .loan-section-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin: 1.5rem 0 0.8rem;
        padding-left: 0.5rem;
        border-left: 5px solid #00529c;
    }

    .loan-section-header h3 {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .loan-summary-toggle { cursor: pointer; transition: transform 0.2s ease; }
    .loan-summary-toggle.collapsed i { transform: rotate(-90deg); }

    /* Select2 overrides */
    .select2-container--bootstrap4 .select2-selection {
        border-radius: 11px !important;
        min-height: 40px !important;
        border-color: var(--loan-border-strong) !important;
        background: linear-gradient(180deg, #eaf2ff 0%, #ffffff 78%) !important;
    }

    /* RKA Achievement badges */
    .pct-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        font-weight: 800;
        font-style: italic;
        min-width: 60px;
        text-align: center;
        font-size: 0.75rem;
    }

    .pct-good {
        background: #10b981;
        color: white;
    }

    .pct-mid {
        background: #fbbf24;
        color: #92400e;
    }

    .pct-bad {
        background: #ef4444;
        color: white;
    }
</style>

