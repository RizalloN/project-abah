<style>
    .em { --em-blue:#307fe2; --em-navy:#0857c3; --em-ink:#153c67; --em-muted:#58718e; --em-line:#e0ebf7; --em-soft:#f4f8fd; --em-green:#176b58; --em-amber:#8a6118; --em-red:#b83d4e; color:var(--em-ink); padding:.65rem 0 2.5rem; font-size:.85rem; line-height:1.6; font-variant-numeric:tabular-nums; }
    .em *, .em *::before, .em *::after { box-sizing:border-box; }
    .em [hidden] { display:none !important; }
    .em :is(h1,h2,h3,p) { margin-top:0; }
    .em :is(h1,h2,h3) { color:var(--em-ink); font-weight:750; line-height:1.3; letter-spacing:-.025em; }
    .em h1 { font-size:1.5rem; margin:0; }
    .em h2 { font-size:1.05rem; margin:0; }
    .em h3 { font-size:.94rem; margin:0; }
    .em a { text-underline-offset:3px; }
    .em :is(a,button,input,select,summary):focus-visible { outline:3px solid #307fe2; outline-offset:4px; }
    .em :is(a,button,input,select,summary) { touch-action:manipulation; }
    .em button { font:inherit; cursor:pointer; }
    .em-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:.8rem; }
    .em-brand { display:flex; align-items:center; gap:.85rem; min-width:0; }
    .em-brand-icon { display:grid; place-items:center; width:46px; height:46px; flex-shrink:0; border-radius:15px; color:var(--em-blue); background:#e7f1ff; font-size:1.25rem; border:1px solid #d1e4fb; }
    .em-head p { margin:.25rem 0 0; font-size:.77rem; color:var(--em-muted); }
    .em-actions { display:flex; align-items:center; flex-wrap:wrap; gap:.55rem; }
    .em-btn { display:inline-flex; align-items:center; justify-content:center; gap:.55rem; min-height:40px; padding:.5rem .85rem; background:#fff; border:1px solid #cdddF1; border-radius:9px; color:var(--em-ink); font:inherit; font-weight:650; text-decoration:none; transition:background-color .18s,color .18s,transform .18s; }
    .em-btn:hover { background:#ecf4ff; color:#0857c3; text-decoration:none; transform:translateY(-1px); }
    .em-btn--primary { background:var(--em-blue); color:#fff; border-color:var(--em-blue); box-shadow:0 4px 10px #307fe222; }
    .em-btn--primary:hover { color:#fff; background:#1866c7; border-color:#1866c7; }
    .em-btn[aria-busy=true] { cursor:progress; }
    .em-text-link { display:inline-flex; gap:.5rem; align-items:center; color:#196bc8; font-size:.75rem; font-weight:650; text-decoration:none; }
    .em-text-link:hover { color:#0857c3; text-decoration:underline; }
    .em-location { display:flex; flex-wrap:wrap; gap:.4rem 1rem; justify-content:space-between; align-items:center; font-size:.69rem; color:var(--em-muted); margin-bottom:1rem; }
    .em-location b { color:var(--em-ink); }
    .em-source-status { display:inline-flex; align-items:center; gap:.4rem; }
    .em-source-status::before { content:''; height:6px; width:6px; border-radius:50%; background:#307fe2; box-shadow:0 0 0 3px #307fe214; }
    .em-source-status--error::before { background:var(--em-red); }
    .em-tabs { display:flex; gap:1.5rem; padding:0 .15rem; border-bottom:1px solid #d5e5f6; margin:0 0 .85rem; overflow-x:auto; scrollbar-width:thin; }
    .em-tabs a { display:inline-flex; align-items:center; gap:.5rem; min-height:46px; padding:.6rem .15rem; border-bottom:3px solid transparent; color:var(--em-muted); text-decoration:none; font-size:.78rem; font-weight:600; white-space:nowrap; }
    .em-tabs a[aria-current=page] { color:#1769c5; border-bottom-color:#307fe2; }
    .em-tabs a:hover { color:#0857c3; }
    .em-tabs a i { font-size:.8rem; opacity:.8; }
    .em-quick-stats { display:none; }
    .em-controls { border:1px solid var(--em-line); background:#fff; border-radius:11px; margin-bottom:1rem; }
    .em-controls > summary { display:flex; justify-content:space-between; gap:.6rem; flex-wrap:wrap; padding:.65rem .9rem; list-style:none; cursor:pointer; color:var(--em-ink); font-size:.77rem; font-weight:600; min-height:44px; align-items:center; }
    .em-controls > summary::-webkit-details-marker { display:none; }
    .em-controls > summary .em-subtle { margin-left:.65rem; font-weight:400; }
    .em-controls > summary i.fa-filter { color:var(--em-blue); margin-right:.25rem; }
    .em-filter { border-top:1px solid var(--em-line); padding:1rem; }
    .em-filter-main { display:grid; grid-template-columns:1fr 1.6fr 1fr 1.2fr auto; gap:.7rem; align-items:end; }
    .em-field { display:flex; flex-direction:column; gap:.3rem; min-width:0; }
    .em label { font-size:.71rem; color:var(--em-muted); margin:0; font-weight:600; }
    .em :is(input,select) { background:#fff; color:var(--em-ink); min-width:0; width:100%; height:40px; border:1px solid #cbdcf0; border-radius:7px; padding:0 .65rem; font:inherit; }
    .em input::placeholder { color:#7489a1; }
    .em select:disabled { background:#f3f7fc; }
    .em-filter-bottom { display:flex; justify-content:space-between; gap:.7rem; margin-top:.75rem; font-size:.69rem; color:var(--em-muted); }
    .em-filter details { margin-top:.65rem; }
    .em-filter summary { cursor:pointer; color:#196bc8; font-size:.74rem; width:fit-content; min-height:28px; }
    .em-filter-extra { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.7rem; margin:.65rem 0; }
    .em-filter-active { display:flex; flex-wrap:wrap; gap:.35rem; margin-top:.65rem; }
    .em-filter-active a { padding:.25rem .5rem; border-radius:5px; color:#145daF; background:#eaf3ff; font-size:.7rem; overflow-wrap:anywhere; }
    .em-hero { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(0,1fr); background:#fff; border:1px solid #d9e8fa; border-radius:20px; overflow:hidden; margin-bottom:1rem; box-shadow:0 8px 30px -22px #0857c34a; }
    .em-hero-copy { padding:1.65rem 1.85rem 1.3rem; }
    .em-scope { display:inline-flex; align-items:center; gap:.4rem; font-size:.71rem; font-weight:650; color:#2d75c7; }
    .em-hero h2 { font-size:clamp(1.6rem,2.25vw,2.4rem); line-height:1.15; letter-spacing:-.045em; margin:.7rem 0; color:#14487d; }
    .em-hero p { font-size:.77rem; color:var(--em-muted); max-width:48ch; margin:0 0 1.1rem; }
    .em-hero-actions { display:flex; align-items:center; flex-wrap:wrap; gap:1.1rem; }
    .em-hero-actions .em-btn { font-size:.74rem; }
    .em-hero-actions .em-text-link i { transform:rotate(45deg); font-size:.67rem; }
    .em-hero-footer { display:flex; flex-wrap:wrap; gap:.45rem 1rem; border-top:1px solid var(--em-line); margin-top:1.3rem; padding-top:.85rem; color:var(--em-muted); font-size:.65rem; }
    .em-hero-footer b { color:#19548b; font-size:.78rem; margin-right:.2rem; }
    .em-network { position:relative; min-height:305px; background:linear-gradient(135deg,#378ce9,#2374cf 70%,#1667c0); isolation:isolate; }
    .em-network-lines { width:100%; height:100%; position:absolute; inset:0; }
    .em-network-core { display:flex; flex-direction:column; align-items:center; justify-content:center; position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:104px; height:104px; background:#fff; border:6px solid #ffffff47; background-clip:padding-box; border-radius:50%; color:#125da9; box-shadow:0 0 0 12px #ffffff12,0 12px 34px #12539830; text-decoration:none; }
    .em-network-core i { font-size:.9rem; color:#307fe2; }
    .em-network-core strong { font-size:1.65rem; line-height:1.1; margin:.25rem 0; }
    .em-network-core span { font-size:.59rem; color:#53759a; }
    .em-network-core:hover { color:#0857c3; text-decoration:none; box-shadow:0 0 0 15px #ffffff1c,0 12px 34px #12539830; }
    .em-network-node { position:absolute; z-index:1; display:flex; align-items:center; gap:.55rem; padding:.6rem .8rem; background:#fff; border-radius:11px; color:#255783; box-shadow:0 7px 20px #124e9822; text-decoration:none; min-width:140px; transition:transform .2s,box-shadow .2s; }
    .em-network-node:hover { color:#0857c3; text-decoration:none; transform:translateY(-3px); box-shadow:0 9px 24px #124e983b; }
    .em-node--one { top:14%; left:7%; }
    .em-node--two { top:26%; right:4%; }
    .em-node--three { bottom:8%; right:14%; }
    .em-ring-id { display:grid; place-items:center; width:30px; height:30px; border-radius:9px; background:#eaf4ff; font-size:.64rem; font-weight:750; color:#216fbe; }
    .em-network-node > span:last-child { font-size:.64rem; }
    .em-network-node b { display:block; font-size:1.05rem; color:#175fA9; line-height:1.35; }
    .em-network-node small { font-size:.58rem; font-weight:400; color:#5e7893; }
    .em-network-caption { position:absolute; bottom:5%; left:6%; color:#e4f1ff; font-size:.59rem; }
    @media(min-width:576px) and (max-width:991.98px) {
        .em .em-node--one { top:8%; left:4%; }
        .em .em-node--two { top:8%; right:4%; }
        .em .em-node--three { bottom:7%; right:12%; }
    }
    .em-finance { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.8rem; margin-bottom:1.5rem; }
    .em-finance > a { position:relative; min-width:0; display:block; text-decoration:none; background:#fff; border:1px solid var(--em-line); border-radius:13px; padding:1rem; transition:transform .2s,border-color .2s; }
    .em-finance > a:hover { transform:translateY(-3px); border-color:#9bc1ef; }
    .em-finance span { display:flex; align-items:center; gap:.45rem; color:var(--em-muted); font-size:.67rem; }
    .em-finance span i { display:grid; place-items:center; width:28px; height:28px; border-radius:8px; background:#e9f3ff; color:#307fe2; font-size:.73rem; }
    .em-finance strong { display:block; font-size:clamp(1.1rem,1.4vw,1.4rem); color:#164d83; font-weight:750; letter-spacing:-.035em; margin:.6rem 0 .3rem; overflow-wrap:anywhere; }
    .em-finance .em-finance-note { font-size:.63rem; color:#65809c; }
    .em-analytics-heading { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin:1.35rem 0 .9rem; }
    .em-analytics-heading h2 { font-size:1.13rem; }
    .em-analytics-heading p { font-size:.72rem; color:var(--em-muted); margin:.2rem 0 0; }
    .em-live-label { display:inline-flex; align-items:center; gap:.4rem; white-space:nowrap; color:#527595; font-size:.64rem; }
    .em-live-label i { width:6px; height:6px; background:#307fe2; border-radius:50%; box-shadow:0 0 0 4px #307fe211; }
    .em-panel { border:1px solid var(--em-line); border-radius:15px; background:#fff; padding:1.25rem; min-width:0; box-shadow:0 5px 18px -16px #12396b26; }
    .em-chart-grid { display:grid; grid-template-columns:minmax(0,1.7fr) minmax(280px,1fr); gap:1rem; margin-bottom:1rem; }
    .em-title-row { display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; margin-bottom:1rem; flex-wrap:wrap; }
    .em-title-row p { font-size:.7rem; color:var(--em-muted); margin:.25rem 0 0; }
    .em-title-row > a:not(.em-text-link) { font-size:.72rem; color:#236fbe; }
    .em-period-switch { display:flex; border:1px solid #dfebf9; border-radius:7px; padding:3px; gap:2px; background:#f5f9ff; }
    .em-period-switch button { background:transparent; border:0; color:#627b96; font-size:.65rem; min-height:28px; border-radius:5px; padding:.25rem .6rem; }
    .em-period-switch button[aria-pressed=true] { color:#1868bc; background:#fff; box-shadow:0 1px 4px #12396b12; font-weight:700; }
    .em-trend-summary { display:flex; align-items:center; gap:1.5rem; margin-bottom:.7rem; }
    .em-trend-summary > div strong { display:block; color:#164d83; font-size:1.65rem; line-height:1.3; font-weight:750; letter-spacing:-.035em; }
    .em-trend-summary > div span { font-size:.64rem; color:#5a7290; display:flex; align-items:center; gap:.4rem; }
    .em-chart-period { margin-left:auto; align-self:end; color:#7a8da4; font-size:.63rem; text-align:right; }
    .em-chart-mode { display:flex; gap:.8rem; margin:.1rem 0 .35rem; }
    .em-chart-mode button { padding:.15rem 0; background:none; border:0; border-bottom:2px solid transparent; color:#7289a1; font-size:.61rem; min-height:26px; }
    .em-chart-mode button[aria-pressed=true] { color:#276fc0; border-bottom-color:#307fe2; font-weight:650; }
    .em-chart-frame { position:relative; min-width:0; }
    .em-chart-frame--trend { height:216px; }
    .em-chart-frame--donut { height:190px; }
    .em-chart-frame--branch { height:178px; }
    .em-chart-frame canvas { display:block; max-width:100%; }
    .em-chart-foot { display:flex; align-items:baseline; justify-content:space-between; gap:.6rem; padding-top:.8rem; border-top:1px solid #edf2fa; margin-top:.7rem; color:#657c96; font-size:.62rem; flex-wrap:wrap; }
    .em-chart-foot i { color:#6e9ccd; margin-right:.2rem; }
    .em-chart-data summary { color:#2774c6; cursor:pointer; white-space:nowrap; }
    .em-chart-data[open] { flex-basis:100%; }
    .em-chart-data .em-scroll { max-height:240px; margin-top:.75rem; }
    .em-icon-tile { display:grid; place-items:center; width:33px; height:33px; background:#eef5ff; color:#307fe2; border-radius:10px; }
    .em-donut-wrap { position:relative; margin:.25rem auto .9rem; max-width:250px; }
    .em-donut-center { display:flex; flex-direction:column; align-items:center; justify-content:center; position:absolute; inset:0; pointer-events:none; }
    .em-donut-center strong { font-size:1.95rem; line-height:1.2; letter-spacing:-.05em; color:#1d5289; }
    .em-donut-center span { color:#627a94; font-size:.64rem; }
    .em-donut-legend { display:grid; gap:.3rem; }
    .em-donut-legend a { display:flex; align-items:center; gap:.5rem; padding:.25rem .1rem; color:#506e8d; font-size:.69rem; text-decoration:none; }
    .em-donut-legend a:hover { color:#0857c3; background:#f4f8fe; }
    .em-donut-legend b { margin-left:auto; color:#2b517b; font-weight:700; }
    .em-donut-legend small { width:42px; text-align:right; color:#6c829b; font-size:.62rem; }
    .em-dot { display:inline-block; width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .em-distribution-grid { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(0,1fr); gap:1rem; margin-bottom:1.5rem; }
    .em-branch-pills { display:flex; flex-wrap:wrap; gap:.5rem; border-top:1px solid #edf2fa; padding-top:.7rem; }
    .em-branch-pills a { display:flex; align-items:center; gap:.6rem; background:#f0f6ff; color:#45749f; font-size:.61rem; padding:.3rem .5rem; border-radius:5px; text-decoration:none; }
    .em-branch-pills b { color:#235b95; }
    .em-stage-flow { list-style:none; margin:.3rem 0 0; padding:0; }
    .em-stage-flow li { display:grid; grid-template-columns:33px minmax(0,1fr) 36px; gap:.65rem; align-items:center; padding:.45rem 0; position:relative; }
    .em-stage-flow li:not(:last-child)::after { content:''; position:absolute; height:14px; border-left:1px dashed #bfd6ee; left:16px; bottom:-8px; }
    .em-stage-marker { display:grid; place-items:center; width:33px; height:33px; border-radius:11px; background:#edf5ff; color:#5899df; font-size:.76rem; }
    .em-stage-flow li:first-child .em-stage-marker { background:#307fe2; color:#fff; }
    .em-stage-flow li > div > span { color:#4e7093; font-size:.7rem; }
    .em-stage-bar { margin-top:.35rem; height:4px; background:#edf4fc; border-radius:4px; overflow:hidden; }
    .em-stage-bar i { display:block; height:100%; width:100%; background:#71b2ef; transform:scaleX(var(--em-stage-ratio)); transform-origin:left; border-radius:inherit; }
    .em-stage-flow b { color:#225885; font-size:.9rem; text-align:right; }
    .em-chart-empty { min-height:210px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.5rem; text-align:center; color:var(--em-muted); font-size:.72rem; }
    .em-chart-empty > i { font-size:1.8rem; color:#a7c6ec; }
    .em-chart-empty strong { color:var(--em-ink); font-size:.85rem; }
    .em-chart-empty span { max-width:38ch; }
    .em-overview { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(0,1fr); gap:1rem; margin-bottom:1rem; }
    .em-map-panel { display:flex; flex-direction:column; }
    .em-map { position:relative; background:#f2f7ff; border:1px solid #edf4fc; border-radius:11px; min-height:280px; display:grid; place-items:center; overflow:hidden; }
    .em-map svg { display:block; width:100%; max-height:330px; height:auto; }
    .em-region path { fill:var(--region-color); stroke:#fff; stroke-width:.7; vector-effect:non-scaling-stroke; transition:fill-opacity .2s; }
    .em-region:hover path,.em-region:focus path { fill-opacity:.7; stroke:#15599a; }
    .em-region[data-selected=true] path { stroke:#164d83; stroke-width:1.8; }
    .em-region rect { fill:#fff; stroke:#dbe8f7; stroke-width:1; }
    .em-region text { fill:#245781; font-size:14px; font-weight:700; text-anchor:middle; pointer-events:none; }
    .em-region .em-map-count { fill:#6483a3; font-size:10px; font-weight:400; }
    .em-map-legend { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.4rem; margin-top:.7rem; }
    .em-map-legend a { padding:.35rem; text-decoration:none; font-size:.65rem; color:#507394; }
    .em-map-legend i { display:inline-block; width:6px; height:6px; margin-right:.3rem; border-radius:2px; background:var(--region-color); }
    .em-map-legend strong { display:block; color:#275884; font-size:.78rem; margin:.1rem 0 0 .6rem; }
    .em-map-note { font-size:.6rem; color:#71869e; margin:.6rem 0 0; }
    .em-map-empty { text-align:center; color:var(--em-muted); padding:2rem; }
    .em-total { display:flex; align-items:center; justify-content:space-between; gap:.7rem; padding-bottom:1rem; border-bottom:1px solid var(--em-line); }
    .em-total strong { font-size:2rem; color:#216cbd; line-height:1.1; letter-spacing:-.04em; }
    .em-total span { display:block; font-size:.72rem; color:var(--em-muted); margin-top:.3rem; }
    .em-execution { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.5rem; margin:.9rem 0; }
    .em-execution a { color:var(--em-ink); text-decoration:none; }
    .em-execution b { display:block; font-size:1.35rem; color:#2465a3; }
    .em-execution span { font-size:.64rem; color:var(--em-muted); }
    .em-stack { display:flex; gap:2px; height:7px; border-radius:7px; overflow:hidden; background:#f2f6fc; margin:.7rem 0; }
    .em-statuses { display:grid; grid-template-columns:1fr 1fr; gap:.35rem .75rem; margin-bottom:.9rem; }
    .em-statuses a { display:flex; align-items:center; gap:.35rem; color:#5a7693; font-size:.68rem; text-decoration:none; }
    .em-statuses b { margin-left:auto; color:#2c5a87; }
    .em-real { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.6rem; padding:.8rem 0; border-top:1px solid var(--em-line); border-bottom:1px solid var(--em-line); }
    .em-real span { color:var(--em-muted); font-size:.67rem; }
    .em-real strong { color:#226fba; font-size:1.15rem; }
    .em-attention { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.5rem; margin-top:.85rem; }
    .em-attention a { padding:.6rem; background:#f4f8fe; border:1px solid #e5eef9; border-radius:8px; text-decoration:none; color:#4e7094; }
    .em-attention b { display:block; color:#245f9e; font-size:1.1rem; }
    .em-attention span { font-size:.6rem; }
    .em-section { margin-bottom:1rem; }
    .em-scroll { overflow-x:auto; scrollbar-width:thin; scrollbar-color:#a3c2e5 #eff6ff; }
    .em-table { width:100%; border-collapse:collapse; font-size:.74rem; }
    .em-table th { padding:.75rem .65rem; background:#f1f7ff; color:#547799; font-size:.65rem; font-weight:650; white-space:nowrap; text-align:left; border-bottom:1px solid #dae8f7; }
    .em-table td { padding:.9rem .65rem; border-bottom:1px solid #eaf1fa; vertical-align:top; }
    .em-table tr:last-child td { border-bottom:0; }
    .em-table tbody tr:hover { background:#f8fbff; }
    .em-table .em-right { text-align:right; white-space:nowrap; }
    .em-table a { color:#276fba; font-weight:650; }
    .em-table td > span.em-subtle { display:block; margin-top:.25rem; }
    .em-table .em-kc { white-space:nowrap; font-weight:700; }
    .em-coverage { display:flex; align-items:center; gap:.4rem; font-size:.7rem; }
    .em-coverage > span { width:45px; height:4px; background:#eaf1fb; border-radius:4px; overflow:hidden; }
    .em-coverage i { display:block; height:100%; background:#307fe2; }
    .em-bottom-grid { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(0,1fr); gap:1rem; margin-bottom:1rem; }
    .em-priority { list-style:none; margin:0; padding:0; }
    .em-priority li+li { border-top:1px solid #edf3fa; }
    .em-priority a { display:flex; align-items:center; justify-content:space-between; gap:.75rem; text-decoration:none; color:var(--em-ink); padding:.85rem 0; }
    .em-priority strong { display:block; font-size:.76rem; overflow-wrap:anywhere; }
    .em-priority .em-subtle { display:block; margin-top:.3rem; }
    .em-badge { display:inline-flex; align-items:center; width:fit-content; padding:.2rem .5rem; font-size:.62rem; line-height:1.5; border-radius:6px; background:#f0f4fa; color:#5c7390; font-weight:600; }
    .em-badge--info { background:#eaf3ff; color:#226bc1; }
    .em-badge--success { background:#eaf6f0; color:#26765b; }
    .em-badge--warning { background:#fff6e8; color:#946b24; }
    .em-badge--danger { background:#fff0f2; color:#b24757; }
    .em-subtle { color:var(--em-muted); font-size:.68rem; }
    .em-bar-list { display:grid; gap:.9rem; }
    .em-bar-list a { display:grid; grid-template-columns:100px minmax(0,1fr) 30px; gap:.7rem; align-items:center; color:#55799a; text-decoration:none; font-size:.73rem; }
    .em-bar { height:7px; border-radius:7px; overflow:hidden; background:#edf4ff; }
    .em-bar i { display:block; height:100%; border-radius:inherit; background:#307fe2; }
    .em-bar-list b { color:#24598a; text-align:right; }
    .em-empty { padding:2rem 1rem; color:var(--em-muted); font-size:.75rem; text-align:center; }
    .em-empty strong { display:block; margin-bottom:.3rem; color:var(--em-ink); }
    .em-notice { border:1px solid #eddfbb; background:#fffdf6; color:#8a692d; border-radius:9px; font-size:.68rem; margin:0 0 1rem; }
    .em-notice summary { padding:.55rem .85rem; cursor:pointer; }
    .em-notice > div { padding:0 .85rem .65rem; }
    .em-alert { background:#fff6e8; border:1px solid #ecd6af; color:#8a6123; border-radius:11px; padding:1rem; }
    .em-method { font-size:.64rem; line-height:1.8; color:#6b8098; padding:.8rem .2rem; }
    .em-log { list-style:none; margin:0; padding:0; }
    .em-log li { display:flex; flex-wrap:wrap; align-items:baseline; gap:.35rem .7rem; font-size:.7rem; padding:.7rem 0; border-top:1px solid #eaf1fa; }
    .em-log time { color:#7189a0; min-width:110px; }
    .em-matrix td { min-width:125px; }
    .em-matrix a { display:block; text-decoration:none; padding:.65rem; border-radius:8px; background:#edf5ff; color:#316da9; }
    .em-matrix .em-cell-attention { background:#fff8eb; color:#947035; }
    .em-matrix b { font-size:1.15rem; }
    .em-ring-cell a { display:block; font-size:.74rem; }
    .em-ring-cell span { display:block; margin-top:.25rem; font-size:.61rem; color:var(--em-muted); }
    .em-eco-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1rem; }
    .em-eco { background:#fff; border:1px solid var(--em-line); border-radius:14px; padding:1.2rem; min-width:0; border-top:3px solid #71b2ed; }
    .em-eco h3 { color:#23619d; margin:.8rem 0 .5rem; overflow-wrap:anywhere; }
    .em-eco p { color:#617b98; font-size:.7rem; margin:.5rem 0; }
    .em-eco-rings { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.45rem; margin-top:1rem; }
    .em-eco-rings a { padding:.5rem; border-radius:7px; background:#eff6ff; color:#3c76b2; text-decoration:none; text-align:center; font-size:.65rem; }
    .em-eco-rings b { display:block; font-size:1.15rem; color:#2666a9; }
    .em-pagination { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.7rem; margin-top:1rem; }
    .em-prospect { min-width:240px; max-width:330px; }
    .em-prospect button { background:none; border:0; padding:0; color:#2468ad; font:inherit; font-weight:650; text-align:left; overflow-wrap:anywhere; }
    .em-prospect button:hover { text-decoration:underline; }
    .em-prospect .em-subtle { font-size:.64rem; }
    .em-dialog { color:var(--em-ink); border:1px solid #d1e4fa; border-radius:18px; padding:0; width:min(900px,calc(100vw - 32px)); max-height:calc(100dvh - 32px); box-shadow:0 24px 90px #0d41754a; overscroll-behavior:contain; }
    .em-dialog::backdrop { background:#1036587a; backdrop-filter:blur(3px); }
    .em-dialog-head { display:flex; justify-content:space-between; gap:1rem; padding:1.3rem 1.5rem; background:#eff6ff; border-bottom:1px solid var(--em-line); }
    .em-dialog-head h2 { font-size:1rem; margin-top:.25rem; }
    .em-dialog-body { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1.6rem; padding:1.4rem 1.5rem; }
    .em-dialog h3 { margin-bottom:.85rem; color:#307abd; }
    .em-dialog dl { display:grid; grid-template-columns:115px minmax(0,1fr); gap:.5rem .75rem; margin:0; font-size:.74rem; }
    .em-dialog dt { color:#6c829a; font-weight:400; }
    .em-dialog dd { margin:0; overflow-wrap:anywhere; white-space:pre-wrap; }
    .em-dialog-wide { grid-column:1 / -1; }
    .em-view-heading { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin:1.3rem 0 1rem; }
    .em-view-heading h2 { font-size:1.3rem; }
    .em-view-heading p { color:var(--em-muted); font-size:.73rem; margin:.25rem 0 0; }
    .em-tl-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin-bottom:1rem; }
    .em-tl-metrics a { display:flex; align-items:center; justify-content:space-between; gap:.6rem; padding:.9rem 1rem; border:1px solid var(--em-line); border-radius:11px; background:#fff; color:#5d7b98; text-decoration:none; font-size:.72rem; }
    .em-tl-metrics a[aria-current=true] { background:#307fe2; border-color:#307fe2; color:#fff; }
    .em-tl-metrics strong { color:#2365a9; font-size:1.5rem; }
    .em-tl-metrics a[aria-current=true] strong { color:#fff; }
    .em-recorded { margin:0; display:grid; gap:.7rem; min-width:145px; }
    .em-recorded dt { color:#7b91a7; font-size:.6rem; font-weight:500; }
    .em-recorded dd { margin:.15rem 0 0; font-size:.73rem; white-space:pre-wrap; overflow-wrap:anywhere; max-width:32ch; }
    .em-followup-table { min-width:850px; }
    .em-followup-table td:first-child { width:19%; }
    .em-followup-table td:nth-child(2) { width:25%; }
    .em-followup-table td:nth-child(n+3) { width:18%; }
    .em-hero { animation:em-arrive .65s cubic-bezier(.2,.8,.2,1) both; }
    .em-network-node { animation:em-node-arrive .75s cubic-bezier(.2,.8,.2,1) both; }
    .em-node--two { animation-delay:.1s; }
    .em-node--three { animation-delay:.2s; }
    @keyframes em-arrive { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
    @keyframes em-node-arrive { from { opacity:0; transform:translateY(10px) scale(.96); } to { opacity:1; transform:translateY(0) scale(1); } }
    @media(min-width:1800px) { .em-hero-copy { padding:2rem 2.4rem; } .em-network { min-height:340px; } .em-chart-frame--trend { height:265px; } .em-chart-frame--donut { height:235px; } }
    @media(max-width:1199.98px) { .em-head { flex-wrap:wrap; } .em-filter-main { grid-template-columns:repeat(4,minmax(0,1fr)); } .em-filter-main .em-field:nth-child(2) { grid-column:span 2; } .em-filter-main .em-btn { grid-column:4; } .em-hero-copy { padding:1.35rem; } .em-network-node { min-width:120px; padding:.5rem; } .em-node--one { left:3%; } .em-node--two { right:2%; top:21%; } .em-node--three { right:7%; } .em-chart-grid { grid-template-columns:minmax(0,1.5fr) minmax(250px,1fr); } .em-finance { gap:.6rem; } .em-finance>a { padding:.85rem; } .em-eco-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:991.98px) { .em-chart-grid,.em-distribution-grid,.em-overview,.em-bottom-grid { grid-template-columns:1fr; } .em-finance { grid-template-columns:repeat(2,minmax(0,1fr)); } .em-network { min-height:295px; } .em-hero h2 { font-size:1.7rem; } .em-hero-actions { gap:.6rem; } .em-tabs { gap:1rem; } .em-status-panel .em-donut-wrap { max-width:260px; } .em-status-panel { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:.5rem 1.25rem; align-items:center; } .em-status-panel>.em-title-row { grid-column:1 / -1; } .em-donut-legend { padding-right:1rem; } .em-map { min-height:270px; } .em-tl-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } .em-network-caption { display:none; } }
    @media(max-width:575.98px) {
        .em { padding:.3rem 0 1.5rem; } .em h1 { font-size:1.15rem; } .em-head { gap:.75rem; } .em-brand-icon { width:39px; height:39px; border-radius:12px; } .em-head p { font-size:.69rem; } .em-head>.em-actions { width:100%; justify-content:flex-end; } .em-head>.em-actions>.em-subtle { margin-right:auto; } .em-head .em-btn { min-height:40px; font-size:.72rem; }
        .em-location { font-size:.63rem; gap:.6rem; } .em-tabs { gap:1.15rem; margin-bottom:.8rem; } .em-tabs a { font-size:.72rem; min-height:44px; } .em-tabs a i { display:none; }
        .em-controls > summary { font-size:.7rem; padding:.6rem .75rem; } .em-controls > summary > .em-subtle { display:none; } .em-controls > summary .em-subtle { font-size:.62rem; } .em-filter { padding:.8rem; } .em-filter-main { grid-template-columns:repeat(2,minmax(0,1fr)); gap:.65rem; } .em-filter-main .em-field:nth-child(2) { grid-column:auto; } .em-filter-main .em-btn { grid-column:1 / -1; } .em-filter-extra { grid-template-columns:repeat(2,minmax(0,1fr)); } .em :is(input,select) { font-size:16px; height:44px; } .em-filter-bottom { flex-wrap:wrap; }
        .em-hero { grid-template-columns:1fr; border-radius:15px; } .em-hero-copy { padding:1.25rem; } .em-hero h2 { font-size:1.9rem; } .em-hero p { font-size:.72rem; } .em-hero-footer { font-size:.6rem; gap:.5rem; justify-content:space-between; margin-top:1.1rem; } .em-network { min-height:225px; } .em-network-core { width:90px; height:90px; } .em-network-core strong { font-size:1.5rem; } .em-network-node { padding:.5rem .6rem; min-width:120px; } .em-node--one { top:9%; left:5%; } .em-node--two { top:26%; right:4%; } .em-node--three { right:12%; bottom:9%; } .em-network-node > span:last-child { font-size:.6rem; } .em-ring-id { width:26px; height:26px; }
        .em-finance { gap:.55rem; margin-bottom:1.1rem; } .em-finance>a { padding:.8rem; border-radius:10px; } .em-finance strong { font-size:1.06rem; margin:.5rem 0 .3rem; } .em-finance span { font-size:.62rem; gap:.3rem; } .em-finance span i { width:24px; height:24px; font-size:.65rem; } .em-finance .em-finance-note { font-size:.58rem; }
        .em-panel { padding:1rem; border-radius:12px; } .em-title-row h3 { font-size:.87rem; } .em-title-row p { font-size:.65rem; } .em-chart-grid { gap:.75rem; } .em-analytics-heading { align-items:flex-start; } .em-analytics-heading .em-live-label { display:none; } .em-chart-frame--trend { height:200px; } .em-chart-frame--donut { height:170px; } .em-trend-summary { gap:1.25rem; } .em-trend-summary>div strong { font-size:1.4rem; } .em-chart-period { font-size:.56rem; max-width:110px; } .em-period-switch button { min-height:36px; } .em-status-panel { gap:.5rem; } .em-status-panel .em-donut-wrap { width:100%; } .em-donut-center strong { font-size:1.6rem; } .em-donut-center span { font-size:.55rem; } .em-donut-legend { padding:0; } .em-donut-legend a { font-size:.61rem; min-height:30px; gap:.3rem; } .em-donut-legend small { width:32px; font-size:.54rem; }
        .em-distribution-grid { gap:.75rem; margin-bottom:1rem; } .em-map { min-height:220px; } .em-map-legend { grid-template-columns:repeat(2,minmax(0,1fr)); } .em-map-legend a { min-height:44px; } .em-priority a { flex-wrap:wrap; gap:.5rem; } .em-eco-grid { grid-template-columns:1fr; } .em-notice { font-size:.63rem; }
        .em-prospect-table { display:block; } .em-prospect-table thead { position:absolute; width:1px; height:1px; overflow:hidden; clip-path:inset(50%); } .em-prospect-table tbody { display:grid; gap:.7rem; } .em-prospect-table tr { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.7rem; border:1px solid var(--em-line); border-radius:9px; padding:.8rem; } .em-prospect-table td { border:0; min-width:0; padding:0; overflow-wrap:anywhere; } .em-prospect-table td::before { content:attr(data-label); display:block; font-size:.6rem; color:#7b91a8; margin-bottom:.25rem; } .em-prospect-table .em-prospect { grid-column:1 / -1; min-width:0; max-width:none; } .em-prospect::before { display:none!important; } .em-prospect-table .em-right { text-align:left; white-space:normal; }
        .em-dialog-head,.em-dialog-body { padding:1rem; } .em-dialog-body { grid-template-columns:1fr; } .em-dialog dl { grid-template-columns:100px minmax(0,1fr); } .em-tl-metrics { gap:.55rem; } .em-tl-metrics a { padding:.7rem; font-size:.65rem; } .em-tl-metrics strong { font-size:1.2rem; } .em-view-heading h2 { font-size:1.15rem; }
    }
    @media(max-width:575.98px) {
        .em .em-network { min-height:260px; }
        .em .em-node--one { top:8%; left:5%; }
        .em .em-node--two { top:8%; right:5%; }
        .em .em-node--three { bottom:8%; right:auto; left:calc(50% - 70px); width:140px; }
    }
    @media(prefers-reduced-motion:reduce) { .em *, .em *::before, .em *::after { animation:none!important; transition:none!important; scroll-behavior:auto!important; } }
</style>
