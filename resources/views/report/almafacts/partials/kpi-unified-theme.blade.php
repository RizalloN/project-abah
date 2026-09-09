<style>
    .kpi-unified-page {
        --kpi-blue: #00529c;
        --kpi-blue-dark: #073b74;
        --kpi-cyan: #009fdb;
        --kpi-green: #07966f;
        --kpi-amber: #f59e0b;
        --kpi-red: #dc3545;
        --kpi-ink: #10213d;
        --kpi-muted: #607089;
        --kpi-line: #d9e4f0;
        padding: 1rem;
        color: var(--kpi-ink);
    }

    .kpi-unified-page .kpi-hero {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(240px, 360px);
        align-items: stretch;
        gap: 1rem;
        overflow: hidden;
        margin-bottom: .85rem;
        padding: 1.2rem;
        border: 1px solid rgba(0, 82, 156, .22);
        border-radius: 16px;
        background: linear-gradient(118deg, #073b74 0%, #00529c 58%, #008cc9 100%);
        box-shadow: 0 18px 42px -30px rgba(7, 59, 116, .7);
    }

    .kpi-unified-page .kpi-hero::after {
        content: '';
        position: absolute;
        right: -72px;
        bottom: -108px;
        width: 300px;
        height: 300px;
        border: 38px solid rgba(255, 255, 255, .09);
        border-radius: 50%;
        pointer-events: none;
    }

    .kpi-hero-copy,
    .kpi-hero-facts { position: relative; z-index: 1; min-width: 0; }

    .kpi-unified-page .kpi-eyebrow {
        display: flex;
        align-items: center;
        gap: .45rem;
        margin-bottom: .35rem;
        color: #8ee4ff;
        font-size: .7rem;
    }

    .kpi-unified-page .kpi-hero h1 {
        font-size: clamp(1.45rem, 2vw, 2rem);
        line-height: 1.2;
    }

    .kpi-unified-page .kpi-hero p {
        margin-top: .45rem;
        font-size: .85rem;
        line-height: 1.5;
    }

    .kpi-hero-facts {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        border: 1px solid rgba(255,255,255,.22);
        border-radius: 12px;
        background: rgba(255,255,255,.1);
        backdrop-filter: blur(10px);
    }

    .kpi-hero-fact { min-width: 0; padding: .8rem; }
    .kpi-hero-fact:nth-child(odd) { border-right: 1px solid rgba(255,255,255,.16); }
    .kpi-hero-fact:nth-child(-n+2) { border-bottom: 1px solid rgba(255,255,255,.16); }
    .kpi-hero-fact span { display: block; color: rgba(255,255,255,.7); font-size: .65rem; font-weight: 800; text-transform: uppercase; }
    .kpi-hero-fact strong { display: block; overflow: hidden; margin-top: .2rem; color: #fff; font-size: 1rem; font-weight: 900; text-overflow: ellipsis; white-space: nowrap; }

    .kpi-unified-page .kpi-toolbar,
    .kpi-analysis-panel,
    .kpi-unified-page .kpi-meta,
    .kpi-unified-page .kpi-table-panel {
        border: 1px solid var(--kpi-line);
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 14px 30px -26px rgba(15, 23, 42, .3);
    }

    .kpi-unified-page .kpi-toolbar {
        align-items: center;
        margin-bottom: .85rem;
        padding: .7rem;
    }

    .kpi-unified-page .kpi-tabs,
    .kpi-unified-page .kpi-actions { gap: .4rem; }

    .kpi-unified-page .kpi-tab,
    .kpi-unified-page .kpi-action {
        justify-content: center;
        min-height: 44px;
        padding: .5rem .75rem;
        border: 1px solid #cddbea;
        border-radius: 8px;
        background: #f6f9fc;
        color: #315373;
        font-size: .76rem;
        box-shadow: none;
    }

    .kpi-unified-page .kpi-tab.active,
    .kpi-unified-page .kpi-action.primary {
        border-color: var(--kpi-blue);
        background: var(--kpi-blue);
        color: #fff;
    }

    .kpi-unified-page .kpi-tab:hover,
    .kpi-unified-page .kpi-action:hover {
        border-color: var(--kpi-blue-dark);
        background: var(--kpi-blue-dark);
        color: #fff;
    }

    .kpi-unified-page :is(a, button, input, select):focus-visible {
        outline: 3px solid rgba(0, 159, 219, .3);
        outline-offset: 2px;
    }

    .kpi-analysis-panel { overflow: hidden; margin-bottom: .85rem; }
    .kpi-analysis-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .8rem .95rem;
        border-bottom: 1px solid var(--kpi-line);
        background: #f6f9fc;
    }
    .kpi-analysis-title { display: flex; align-items: center; gap: .55rem; min-width: 0; }
    .kpi-analysis-icon,
    .kpi-table-icon { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; border-radius: 8px; background: var(--kpi-blue); color: #fff; }
    .kpi-analysis-title h2 { margin: 0; font-size: .92rem; font-weight: 900; }
    .kpi-analysis-title p { margin: .1rem 0 0; color: var(--kpi-muted); font-size: .69rem; }
    .kpi-result-summary { flex: 0 0 auto; color: var(--kpi-blue); font-size: .72rem; font-weight: 900; }
    .kpi-analysis-body { padding: .9rem; }
    .kpi-filter-grid { display: grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap: .7rem; }
    .kpi-filter-field { display: block; min-width: 0; padding: 0; border: 0; border-radius: 0; background: transparent; }
    .kpi-filter-field label { display: block; margin: 0 0 .3rem; color: #52657c; font-size: .67rem; font-weight: 900; text-transform: uppercase; }
    .kpi-filter-field select,
    .kpi-search-control {
        width: 100%;
        min-width: 0;
        min-height: 44px;
        border: 1px solid #c8d7e8;
        border-radius: 8px;
        background: #fff;
    }
    .kpi-filter-field select { height: 44px; padding: .45rem .65rem; color: var(--kpi-ink); font-size: .8rem; font-weight: 750; }
    .kpi-filter-field select:disabled { background: #eef3f8; color: #52657c; }
    .kpi-search-control { display: grid; grid-template-columns: 38px minmax(0, 1fr) 42px; align-items: center; overflow: hidden; }
    .kpi-search-control > i { color: #6b7f96; text-align: center; }
    .kpi-search-control input { width: 100%; min-width: 0; height: 42px; padding: 0 .2rem; border: 0; outline: 0; color: var(--kpi-ink); font-size: .8rem; }
    .kpi-search-control button { width: 42px; height: 42px; border: 0; background: transparent; color: #52657c; cursor: pointer; }
    .kpi-search-control button:disabled { opacity: .35; cursor: default; }
    .kpi-filter-field select:focus,
    .kpi-search-control:focus-within { border-color: var(--kpi-blue); box-shadow: 0 0 0 3px rgba(0,82,156,.12); }

    .kpi-unified-page .kpi-meta-grid { gap: .7rem; margin-bottom: .85rem; }
    .kpi-unified-page .kpi-meta {
        position: relative;
        min-width: 0;
        overflow: hidden;
        padding: .85rem .9rem .85rem 1rem;
        border-left: 4px solid var(--kpi-tone, var(--kpi-blue));
        background: #fff;
    }
    .kpi-unified-page .kpi-meta::after { content: ''; position: absolute; right: -24px; bottom: -36px; width: 92px; height: 92px; border: 15px solid var(--kpi-tone, var(--kpi-blue)); border-radius: 50%; opacity: .09; pointer-events: none; }
    .kpi-unified-page .kpi-meta span { font-size: .65rem; }
    .kpi-unified-page .kpi-meta strong { position: relative; z-index: 1; margin-top: .28rem; font-size: 1.3rem; line-height: 1.15; }
    .kpi-unified-page .kpi-meta small { position: relative; z-index: 1; display: block; overflow: hidden; margin-top: .3rem; color: #52657c; font-size: .69rem; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }

    .kpi-unified-page .kpi-table-panel { border-radius: 12px; }
    .kpi-unified-page .kpi-table-title { padding: .8rem .95rem; background: #f6f9fc; }
    .kpi-table-heading { display: flex; align-items: center; gap: .55rem; min-width: 0; }
    .kpi-table-heading > div { min-width: 0; }
    .kpi-table-heading strong,
    .kpi-table-heading span { overflow-wrap: anywhere; }
    .kpi-source-badge { display: inline-flex !important; align-items: center; gap: .4rem; flex: 0 0 auto; color: #315373 !important; font-size: .68rem !important; font-weight: 850 !important; }
    .kpi-source-badge i { color: var(--kpi-green); font-size: .48rem; }
    .kpi-unified-page .kpi-excel-wrap.table-container { background: #edf3f8; }
    .kpi-unified-page .kpi-excel-table thead tr:first-child th { background: var(--kpi-blue-dark); }
    .kpi-unified-page .kpi-excel-table thead tr:nth-child(2) th { background: var(--kpi-blue); }
    .kpi-unified-page .kpi-excel-table th.kpi-group-head { background: #062f5f !important; }
    .kpi-unified-page .kpi-excel-table th.kpi-sticky-col-0,
    .kpi-unified-page .kpi-excel-table th.kpi-sticky-col-1 { background: #062f5f !important; }
    .kpi-excel-table tbody tr[hidden] { display: none; }
    .kpi-table-panel--no-match .kpi-excel-wrap::after {
        content: 'Tidak ada data yang cocok dengan pencarian.';
        display: block;
        position: sticky;
        left: 0;
        width: min(100%, 520px);
        padding: 1.5rem 1rem;
        color: var(--kpi-muted);
        font-size: .78rem;
        font-weight: 800;
        text-align: center;
    }

    @media (max-width: 1199.98px) {
        .kpi-unified-page .kpi-meta-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 991.98px) {
        .kpi-unified-page .kpi-hero { grid-template-columns: 1fr; }
        .kpi-unified-page .kpi-hero-facts { max-width: none; }
        .kpi-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .kpi-search-field { grid-column: 1 / -1; }
    }

    @media (max-width: 767.98px) {
        .kpi-unified-page { padding: .6rem; }
        .kpi-unified-page .kpi-hero { display: grid; grid-template-columns: 1fr; padding: .95rem; border-radius: 12px; }
        .kpi-unified-page .kpi-hero p { display: block; -webkit-line-clamp: initial; }
        .kpi-unified-page .kpi-toolbar,
        .kpi-analysis-body { padding: .65rem; }
        .kpi-unified-page .kpi-tabs,
        .kpi-unified-page .kpi-actions { width: 100%; }
        .kpi-unified-page .kpi-tab,
        .kpi-unified-page .kpi-action { flex: 1 1 135px; min-width: 0; min-height: 44px; }
        .kpi-filter-grid,
        .kpi-unified-page .kpi-meta-grid { grid-template-columns: 1fr; }
        .kpi-search-field { grid-column: auto; }
        .kpi-analysis-head,
        .kpi-unified-page .kpi-table-title { align-items: flex-start; flex-direction: column; }
        .kpi-result-summary,
        .kpi-source-badge { align-self: flex-start; }
    }

    @media (max-width: 420px) {
        .kpi-hero-facts { grid-template-columns: 1fr; }
        .kpi-hero-fact:nth-child(n) { border-right: 0; border-bottom: 1px solid rgba(255,255,255,.16); }
        .kpi-hero-fact:last-child { border-bottom: 0; }
        .kpi-unified-page .kpi-tab span { display: none; }
        .kpi-unified-page .kpi-tab { flex-basis: 44px; padding-inline: .65rem; }
    }
</style>
