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
    }

    .loan-shell,
    .loan-table-shell {
        position: relative;
        border: 1px solid var(--loan-border);
        border-radius: 18px;
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 251, 255, 0.96)),
            var(--loan-surface);
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, 0.22);
    }

    .loan-shell::before,
    .loan-table-shell::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 4px;
        border-radius: 18px 18px 0 0;
        background: linear-gradient(90deg, var(--loan-blue-deep), var(--loan-blue), #3b82f6);
        pointer-events: none;
    }

    .loan-filter-grid .form-group {
        position: relative;
        min-height: 106px;
        margin-bottom: 0.85rem;
        padding: 0.9rem 0.95rem 0.85rem;
        border: 1px solid #dbe5ef;
        border-radius: 16px;
        background: linear-gradient(180deg, rgba(234, 242, 255, 0.98) 0%, rgba(255, 255, 255, 0.98) 74%), #ffffff;
        box-shadow: 0 14px 26px -24px rgba(15, 23, 42, 0.24);
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
        background: linear-gradient(90deg, var(--loan-blue-deep), var(--loan-blue), #8fb4ff, #ffffff);
    }

    .loan-filter-control {
        border-radius: 11px !important;
        min-height: 40px !important;
        height: 40px !important;
        border-color: var(--loan-border-strong) !important;
        background: linear-gradient(180deg, #eaf2ff 0%, #ffffff 78%) !important;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 12px 22px -20px rgba(15, 23, 42, 0.2);
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
        animation: loanPulse 1.6s infinite;
    }

    @keyframes loanPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(20, 184, 166, 0.45); }
        70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(20, 184, 166, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(20, 184, 166, 0); }
    }

    /* ── Matrix Specific ─────────────────────────── */
    .loan-matrix-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .loan-matrix { width: 100%; min-width: 1580px; border-collapse: separate; border-spacing: 0; }
    .loan-matrix th, .loan-matrix td { padding: 12px 10px; border-right: 1px solid rgba(255, 255, 255, 0.3); border-bottom: 1px solid rgba(255, 255, 255, 0.3); text-align: right; }
    .loan-matrix thead th { background: #1d4ed8; color: white; text-align: center; font-weight: 800; }
    .loan-matrix .matrix-before { background: #f59e0b; position: sticky; left: 0; z-index: 3; text-align: left; }
    .loan-matrix tbody th { background: #fb923c; color: white; position: sticky; left: 0; z-index: 2; text-align: left; }
    .loan-matrix .matrix-total-col { background: #ccfbf1 !important; color: #115e59 !important; font-weight: 800; }
    .matrix-up { background: #22c55e !important; color: white !important; }
    .matrix-down { background: #ef4444 !important; color: white !important; }
    .matrix-stagnant { background: #ffffff !important; }

    /* ── Summary Dashboard Styles ─────────────────── */
    .loan-summary-card { margin-bottom: 2rem; }

    .loan-table-container {
        max-width: 98%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .loan-summary-table-wrap { 
        width: 100%;
        margin-bottom: 2.5rem; 
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
        background-color: #f1f5f9 !important;
        font-weight: 800 !important;
    }

    .loan-branch-subtotal td {
        color: #1e40af !important;
        border-top: 2px solid var(--loan-border-strong) !important;
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
        margin: 2.5rem 0 1rem;
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
</style>

