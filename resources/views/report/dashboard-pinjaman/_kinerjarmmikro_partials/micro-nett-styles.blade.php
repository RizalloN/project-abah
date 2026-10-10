<style>
    .micro-nett-page { padding: 20px 18px !important; }
    .micro-nett-page .rm-mikro-hero { padding: 0; margin: 0 0 18px; border-radius: 0; background: none; box-shadow: none; }
    .micro-nett-page .rm-mikro-hero::before { display: none; }
    .micro-nett-page .rm-mikro-hero h1 { font-size: 25px; font-weight: 800; line-height: 1.3; color: #15324f; background: none; -webkit-text-fill-color: currentColor; text-shadow: none; text-transform: none; }
    .micro-nett-page .rm-mikro-hero p { margin: 5px 0 0; color: #53677b; font-size: 13px; letter-spacing: 0; }
    .micro-nett-page .loan-shell, .micro-nett-page .loan-table-shell { border: 1px solid #dce3eb; border-radius: 10px; background: #fff; box-shadow: none; }
    .micro-nett-page .loan-shell { margin-bottom: 18px !important; }
    .micro-nett-page .loan-shell > .card-body { padding: 16px !important; }
    .micro-nett-page .loan-table-shell > .card-body { padding: 20px !important; }
    .micro-nett-page .loan-filter-grid { display: grid; grid-template-columns: minmax(160px, 1fr) minmax(160px, 1fr) minmax(200px, 1.4fr) 120px; align-items: end; gap: 14px; margin: 0; }
    .micro-nett-page .loan-filter-grid > div { padding: 0; width: auto; max-width: none; margin: 0; }
    .micro-nett-page .loan-filter-label { margin-bottom: 6px; color: #53677b; font-size: 11px; font-weight: 700; letter-spacing: .035em; }
    .micro-nett-page .loan-filter-control { border-radius: 6px !important; min-height: 40px !important; font-size: 13px; font-weight: 600 !important; }
    .micro-nett-page .loan-shell .btn-primary { border-radius: 6px !important; min-height: 40px !important; background: #0063a9 !important; box-shadow: none !important; font-size: 13px; }
    .micro-nett-page .loan-shell .btn-primary:hover { background: #004d85 !important; transform: none; }
    .micro-nett-page .rm-mikro-filter-context { display: none !important; }
    .kpi-nett-workbook { min-width: 0; max-width: 100%; }
    .kpi-nett-workbook .micro-nett-report-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; }
    .kpi-nett-workbook .micro-nett-report-header h2 { margin: 0; color: #15324f; font-size: 21px; font-weight: 800; line-height: 1.4; letter-spacing: -.02em; }
    .kpi-nett-workbook .micro-nett-report-header h2 span { color: #0063a9; }
    .kpi-nett-workbook .micro-nett-report-header p { margin: 4px 0 0; color: #53677b; font-size: 12px; }
    .kpi-nett-workbook .micro-nett-unit-label { color: #53677b; font-size: 12px; }
    .kpi-nett-workbook .micro-nett-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin: 0 0 20px; padding: 14px 0; border-top: 1px solid #dce3eb; border-bottom: 1px solid #dce3eb; }
    .kpi-nett-workbook .micro-nett-summary > div { padding: 0 16px; border-left: 1px solid #dce3eb; }
    .kpi-nett-workbook .micro-nett-summary > div:first-child { padding-left: 0; border-left: 0; }
    .kpi-nett-workbook .micro-nett-summary dt { font-size: 11px; color: #53677b; font-weight: 500; line-height: 1.5; }
    .kpi-nett-workbook .micro-nett-summary dd { margin: 5px 0 0; color: #15324f; font-size: 19px; font-weight: 800; line-height: 1.3; font-variant-numeric: tabular-nums; }
    .kpi-nett-workbook .micro-nett-summary small { font-size: 11px; color: #53677b; font-weight: 500; }
    .kpi-nett-workbook .micro-nett-visually-hidden, .kpi-nett-workbook .micro-nett-sheet-nav legend { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
    .kpi-nett-workbook .micro-nett-sheet-nav { display: flex; flex-wrap: wrap; gap: 4px; padding: 0; margin: 0; border: 0; border-bottom: 1px solid #dce3eb; }
    .kpi-nett-workbook .micro-nett-sheet-nav label { position: relative; display: inline-flex; align-items: center; justify-content: center; min-width: 76px; min-height: 42px; padding: 9px 18px; margin: 0 0 -1px; border: 0; border-bottom: 3px solid transparent; border-radius: 0; background: transparent; color: #53677b; cursor: pointer; font-size: 13px; font-weight: 700; }
    .kpi-nett-workbook .micro-nett-sheet-nav label:hover { color: #0063a9; background: #f3f7fb; }
    .kpi-nett-workbook .micro-nett-sheet-nav label:has(input:checked) { color: #0063a9; border-bottom-color: #0070c0; background: #f3f7fb; }
    .kpi-nett-workbook .micro-nett-sheet-nav label:focus-within { outline: 2px solid #0063a9; outline-offset: -3px; }
    .kpi-nett-workbook .micro-nett-sheet-nav input { position: absolute; width: 1px; height: 1px; opacity: 0; margin: 0; }
    .kpi-nett-workbook .micro-nett-mantri:has(input[value="bo"]:checked) > .micro-nett-sheet-panel:not([data-sheet="bo"]),
    .kpi-nett-workbook .micro-nett-mantri:has(input[value="mbm"]:checked) > .micro-nett-sheet-panel:not([data-sheet="mbm"]),
    .kpi-nett-workbook .micro-nett-mantri:has(input[value="unit"]:checked) > .micro-nett-sheet-panel:not([data-sheet="unit"]),
    .kpi-nett-workbook .micro-nett-rm:has(input[value="bo"]:checked) > .micro-nett-sheet-panel:not([data-sheet="bo"]),
    .kpi-nett-workbook .micro-nett-rm:has(input[value="detail"]:checked) > .micro-nett-sheet-panel:not([data-sheet="detail"]) { display: none; }
    .kpi-nett-workbook .micro-nett-sheet-guide { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px 16px; margin: 10px 0 14px; color: #53677b; font-size: 11px; line-height: 1.5; }
    .kpi-nett-workbook .micro-nett-sheet-guide .micro-guide-desc { display: none; }
    .kpi-nett-workbook .micro-nett-sheets:has(input[value="bo"]:checked) .micro-guide-desc.micro-nett-guide-bo,
    .kpi-nett-workbook .micro-nett-sheets:has(input[value="detail"]:checked) .micro-guide-desc.micro-nett-guide-detail,
    .kpi-nett-workbook .micro-nett-sheets:has(input[value="mbm"]:checked) .micro-guide-desc.micro-nett-guide-mbm,
    .kpi-nett-workbook .micro-nett-sheets:has(input[value="unit"]:checked) .micro-guide-desc.micro-nett-guide-unit { display: inline-block; }
    .kpi-nett-workbook .micro-nett-sheet-guide__info { display: flex; align-items: center; flex-wrap: wrap; gap: 8px 12px; }
    .kpi-nett-workbook .micro-nett-guide-hint { display: inline-flex; align-items: center; padding: 2px 8px; background: #eef6ff; color: #0857c3; border-radius: 999px; font-weight: 700; font-size: 10.5px; }
    .kpi-nett-workbook .micro-nett-scroll-actions { display: inline-flex; align-items: center; gap: 6px; }
    .kpi-nett-workbook .micro-nett-scroll-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 28px;
        padding: 0;
        border: 1px solid #bfdbfe;
        border-radius: 6px;
        background: #ffffff;
        color: #0857c3;
        font-size: 11px;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .kpi-nett-workbook .micro-nett-scroll-btn:hover {
        background: #0857c3;
        color: #ffffff;
        border-color: #0857c3;
        box-shadow: 0 2px 6px rgba(8, 87, 195, 0.25);
    }
    .kpi-nett-workbook .micro-nett-wrap {
        overflow-x: auto;
        overflow-y: auto;
        max-height: min(680px, 75vh);
        max-width: 100%;
        border: 1px solid #b8c7d6;
        border-radius: 6px;
        overscroll-behavior-x: contain;
        overscroll-behavior-y: auto;
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
        scrollbar-color: #839bb1 #eef2f6;
        scrollbar-width: thin;
        background: #ffffff;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
        cursor: default;
    }
    .kpi-nett-workbook .micro-nett-wrap.is-dragging {
        cursor: grabbing;
        user-select: none;
    }
    .kpi-nett-workbook .micro-nett-wrap:focus-visible { outline: 2px solid #0063a9; outline-offset: 3px; }
    .kpi-nett-workbook .micro-nett-wrap::-webkit-scrollbar { width: 8px; height: 9px; }
    .kpi-nett-workbook .micro-nett-wrap::-webkit-scrollbar-track { background: #f1f5f9; }
    .kpi-nett-workbook .micro-nett-wrap::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 4px; }
    .kpi-nett-workbook .micro-nett-wrap::-webkit-scrollbar-thumb:hover { background: #64748b; }
    .kpi-nett-workbook .micro-nett-table {
        border-collapse: separate !important;
        border-spacing: 0 !important;
        width: 100%;
        font-variant-numeric: tabular-nums;
        color: #002060;
        table-layout: auto;
    }
    .kpi-nett-workbook .micro-nett-table caption { caption-side: top; text-align: left; padding: 9px 12px; font-size: 11px; font-weight: 700; color: #1e3a5f; background: #f8fafc; border-bottom: 1px solid #cbd5e1; }
    .kpi-nett-workbook .micro-nett-table th, .kpi-nett-workbook .micro-nett-table td {
        padding: 7px 8px;
        font-size: 11px;
        line-height: 1.4;
        border-right: 1px solid #d1dbe5;
        border-bottom: 1px solid #d1dbe5;
        white-space: nowrap;
        text-align: right;
        vertical-align: middle;
        background: #ffffff;
        background-clip: padding-box !important;
    }
    /* Sheet Mantri: BO */
    .kpi-nett-workbook .micro-nett-sheet-panel[data-sheet="bo"] .micro-mantri-table--summary {
        --col-1-w: 48px;
        --col-2-w: 145px;
        --col-3-w: 0px;
    }
    .kpi-nett-workbook .micro-nett-sheet-panel[data-sheet="bo"] .micro-mantri-table--summary.has-bo-code {
        --col-1-w: 48px;
        --col-2-w: 75px;
        --col-3-w: 145px;
    }
    /* Sheet Mantri: MBM */
    .kpi-nett-workbook .micro-nett-sheet-panel[data-sheet="mbm"] .micro-mantri-table--summary {
        --col-1-w: 44px;
        --col-2-w: 115px;
        --col-3-w: 175px;
    }
    /* Sheet Mantri: UNIT */
    .kpi-nett-workbook .micro-nett-sheet-panel[data-sheet="unit"] .micro-mantri-table--summary {
        --col-1-w: 40px;
        --col-2-w: 105px;
        --col-3-w: 68px;
        --col-4-w: 140px;
        --col-5-w: 145px;
    }
    /* Sheet RM: BO */
    .kpi-nett-workbook .micro-rm-kur-distribution-table {
        --col-1-w: 44px;
        --col-2-w: 125px;
        --col-3-w: 120px;
    }
    /* Sheet RM: Detail */
    .kpi-nett-workbook .micro-rm-kur-table {
        --col-1-w: 40px;
        --col-2-w: 105px;
        --col-3-w: 125px;
        --col-4-w: 72px;
        --col-5-w: 165px;
    }
    /* Core Freeze Columns Positioning */
    .kpi-nett-workbook .micro-nett-table .col-freeze-1 {
        position: sticky !important;
        left: 0 !important;
        width: var(--col-1-w) !important;
        min-width: var(--col-1-w) !important;
        max-width: var(--col-1-w) !important;
        box-sizing: border-box !important;
        text-align: center !important;
    }
    .kpi-nett-workbook .micro-nett-table .col-freeze-2 {
        position: sticky !important;
        left: var(--col-1-w) !important;
        width: var(--col-2-w) !important;
        min-width: var(--col-2-w) !important;
        max-width: var(--col-2-w) !important;
        box-sizing: border-box !important;
        text-align: left !important;
    }
    .kpi-nett-workbook .micro-nett-table .col-freeze-3 {
        position: sticky !important;
        left: calc(var(--col-1-w) + var(--col-2-w)) !important;
        width: var(--col-3-w) !important;
        min-width: var(--col-3-w) !important;
        max-width: var(--col-3-w) !important;
        box-sizing: border-box !important;
        text-align: left !important;
    }
    .kpi-nett-workbook .micro-nett-table .col-freeze-4 {
        position: sticky !important;
        left: calc(var(--col-1-w) + var(--col-2-w) + var(--col-3-w)) !important;
        width: var(--col-4-w) !important;
        min-width: var(--col-4-w) !important;
        max-width: var(--col-4-w) !important;
        box-sizing: border-box !important;
        text-align: left !important;
    }
    .kpi-nett-workbook .micro-nett-table .col-freeze-5 {
        position: sticky !important;
        left: calc(var(--col-1-w) + var(--col-2-w) + var(--col-3-w) + var(--col-4-w)) !important;
        width: var(--col-5-w) !important;
        min-width: var(--col-5-w) !important;
        max-width: var(--col-5-w) !important;
        box-sizing: border-box !important;
        text-align: left !important;
    }
    /* Sticky Header Hierarchy */
    .kpi-nett-workbook .micro-nett-table thead {
        position: sticky;
        top: 0;
        z-index: 20;
    }
    .kpi-nett-workbook .micro-nett-table thead th {
        position: sticky;
        top: 0;
        padding: 9px 6px;
        background: #0070c0 !important;
        color: #ffffff !important;
        text-align: center;
        white-space: normal;
        border-radius: 0;
        font-weight: 700;
        z-index: 20;
        background-clip: padding-box !important;
    }
    .kpi-nett-workbook .micro-nett-table thead th[colspan="2"] { min-width: 100px; }
    .kpi-nett-workbook .micro-rm-kur-range { display: block; max-width: 125px; margin: 4px auto 0; font-size: 10px; font-weight: 500; line-height: 1.4; }
    /* Corner Cells (Sticky Top + Sticky Left) */
    .kpi-nett-workbook .micro-nett-table thead th.col-freeze-1,
    .kpi-nett-workbook .micro-nett-table thead th.col-freeze-2,
    .kpi-nett-workbook .micro-nett-table thead th.col-freeze-3,
    .kpi-nett-workbook .micro-nett-table thead th.col-freeze-4,
    .kpi-nett-workbook .micro-nett-table thead th.col-freeze-5 {
        z-index: 45 !important;
        background-color: #00569e !important;
        color: #ffffff !important;
        font-weight: 800 !important;
    }
    /* Body Frozen Cells */
    .kpi-nett-workbook .micro-nett-table tbody .col-freeze-1,
    .kpi-nett-workbook .micro-nett-table tbody .col-freeze-2,
    .kpi-nett-workbook .micro-nett-table tbody .col-freeze-3,
    .kpi-nett-workbook .micro-nett-table tbody .col-freeze-4,
    .kpi-nett-workbook .micro-nett-table tbody .col-freeze-5 {
        z-index: 10 !important;
        background-color: #ffffff !important;
        background-clip: padding-box !important;
    }
    .kpi-nett-workbook .micro-nett-table tbody th {
        font-weight: 700;
        color: #0f2f66;
    }
    .kpi-nett-workbook .micro-nett-table tbody tr:nth-child(even) .col-freeze-1,
    .kpi-nett-workbook .micro-nett-table tbody tr:nth-child(even) .col-freeze-2,
    .kpi-nett-workbook .micro-nett-table tbody tr:nth-child(even) .col-freeze-3,
    .kpi-nett-workbook .micro-nett-table tbody tr:nth-child(even) .col-freeze-4,
    .kpi-nett-workbook .micro-nett-table tbody tr:nth-child(even) .col-freeze-5 {
        background-color: #f8fbff !important;
    }
    .kpi-nett-workbook .micro-nett-table tbody tr:hover > * {
        background-color: #eaf3ff !important;
        color: #0857c3 !important;
    }
    .kpi-nett-workbook .micro-nett-table tbody tr:hover .col-freeze-1,
    .kpi-nett-workbook .micro-nett-table tbody tr:hover .col-freeze-2,
    .kpi-nett-workbook .micro-nett-table tbody tr:hover .col-freeze-3,
    .kpi-nett-workbook .micro-nett-table tbody tr:hover .col-freeze-4,
    .kpi-nett-workbook .micro-nett-table tbody tr:hover .col-freeze-5 {
        background-color: #eaf3ff !important;
        color: #0857c3 !important;
    }
    /* Frozen Total & Subtotal */
    .kpi-nett-workbook .micro-nett-table tfoot .col-freeze-total,
    .kpi-nett-workbook .micro-nett-table .micro-nett-subtotal .col-freeze-total {
        position: sticky !important;
        left: 0 !important;
        z-index: 25 !important;
        background-color: #0070c0 !important;
        color: #ffffff !important;
        font-weight: 800 !important;
        text-align: left !important;
    }
    /* Freeze Divider Line & Drop Shadow */
    .kpi-nett-workbook .micro-nett-table thead .col-freeze-last {
        border-right: 2px solid #003a6b !important;
        box-shadow: 4px 0 10px -2px rgba(15, 23, 42, 0.20) !important;
    }
    .kpi-nett-workbook .micro-nett-table tbody .col-freeze-last {
        border-right: 2px solid #94a3b8 !important;
        box-shadow: 4px 0 10px -2px rgba(15, 23, 42, 0.12) !important;
    }
    .kpi-nett-workbook .micro-nett-table tfoot .col-freeze-last,
    .kpi-nett-workbook .micro-nett-table .micro-nett-subtotal .col-freeze-last {
        border-right: 2px solid #003a6b !important;
        box-shadow: 4px 0 10px -2px rgba(15, 23, 42, 0.20) !important;
    }
    .kpi-nett-workbook .micro-nett-wrap.is-scrolled-x .col-freeze-last {
        box-shadow: 6px 0 16px -2px rgba(15, 23, 42, 0.24) !important;
    }
    .kpi-nett-workbook .micro-nett-table thead .tone-zero { background: #808080; color: white; }
    .kpi-nett-workbook .micro-nett-mantri .micro-nett-table thead .tone-zero,
    .kpi-nett-workbook .micro-nett-mantri .micro-nett-table thead .tone-none { background: #ff0000; color: white; }
    .kpi-nett-workbook .micro-nett-table thead .tone-extreme_low { background: #a6a6a6; color: #002060; }
    .kpi-nett-workbook .micro-nett-table thead .tone-low { background: #bfbfbf; color: #002060; }
    .kpi-nett-workbook .micro-nett-mantri .micro-nett-table thead .tone-low { background: #808080; color: white; }
    .kpi-nett-workbook .micro-nett-table thead .tone-mid { background: #00b0f0; color: #002060; }
    .kpi-nett-workbook .micro-nett-table thead .tone-high { background: #0070c0; color: white; }
    .kpi-nett-workbook .micro-nett-table thead .tone-acceleration { background: white; color: #002060; }
    .kpi-nett-workbook .micro-nett-table tfoot th, .kpi-nett-workbook .micro-nett-table tfoot td,
    .kpi-nett-workbook .micro-nett-table .micro-nett-subtotal th, .kpi-nett-workbook .micro-nett-table .micro-nett-subtotal td { background: #0070c0; color: white; font-weight: 700; }
    .kpi-nett-workbook .micro-nett-table tfoot th { text-align: left; }
    .kpi-nett-workbook .micro-nett-table .micro-nett-amount { font-weight: 700; }

    .kpi-nett-workbook .micro-nett-help { color: #53677b; font-size: 11px; line-height: 1.6; margin: 12px 0 0; max-width: 100ch; }
    .kpi-nett-workbook .micro-nett-rm > p[role="note"] { margin: 14px 0 0; padding: 12px 14px; background: #fff8eb; border: 1px solid #ead9b6; border-radius: 6px; color: #6c501b; font-size: 12px; line-height: 1.7; max-width: 100%; }
    .kpi-nett-workbook .micro-nett-nominative { margin-top: 16px; border-top: 1px solid #dce3eb; padding-top: 4px; font-size: 12px; color: #53677b; }
    .kpi-nett-workbook .micro-nett-nominative > summary { cursor: pointer; font-weight: 600; padding: 12px 0; color: #234d71; }
    .kpi-nett-workbook ::selection { background: #c5e5fb; color: #15324f; }
    @media(max-width: 1000px) {
        .micro-nett-page .loan-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media(max-width: 640px) {
        .micro-nett-page { padding: 14px 8px !important; }
        .micro-nett-page .rm-mikro-hero { margin-bottom: 14px; }
        .micro-nett-page .rm-mikro-hero h1 { font-size: 22px; }
        .micro-nett-page .loan-shell > .card-body, .micro-nett-page .loan-table-shell > .card-body { padding: 14px !important; }
        .micro-nett-page .loan-filter-grid { grid-template-columns: minmax(0, 1fr); gap: 12px; }
        .kpi-nett-workbook .micro-nett-report-header { gap: 6px; margin-bottom: 14px; }
        .kpi-nett-workbook .micro-nett-report-header h2 { font-size: 19px; }
        .kpi-nett-workbook .micro-nett-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 0; padding: 14px 0; margin-bottom: 14px; }
        .kpi-nett-workbook .micro-nett-summary > div { padding: 0 10px; }
        .kpi-nett-workbook .micro-nett-summary > div:nth-child(odd) { border-left: 0; padding-left: 0; }
        .kpi-nett-workbook .micro-nett-summary dd { font-size: 17px; }
        .kpi-nett-workbook .micro-nett-wrap { max-height: 65vh; }
    }
</style>
