@php
    $meta = (array) data_get($consumerOperations, 'meta', []);
    $pipeline = (array) data_get($consumerOperations, 'pipeline', []);
    $pipelineSources = array_values((array) data_get($pipeline, 'sources', []));
    $kprPipeline = (array) data_get($consumerOperations, 'kpr_pipeline', []);
    $kprPipelineRows = array_values((array) data_get($kprPipeline, 'rows', []));
    $quadrants = (array) data_get($consumerOperations, 'quadrants', []);
    $quadrantBranches = array_values((array) data_get($quadrants, 'branches', []));
    $firstPipelineKey = (string) data_get($pipelineSources, '0.key', '');
    $firstQuadrantBranch = (string) data_get($quadrantBranches, '0.key', '');
    $formatInteger = static fn ($value): string => number_format((int) round((float) $value), 0, ',', '.');
    $formatAmount = static function ($value): string {
        $amount = (float) $value;
        $absolute = abs($amount);
        if ($absolute >= 1_000_000_000) {
            return 'Rp '.number_format($amount / 1_000_000_000, 2, ',', '.').' M';
        }
        if ($absolute >= 1_000_000) {
            return 'Rp '.number_format($amount / 1_000_000, 1, ',', '.').' Jt';
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    };
    $formatPercent = static fn ($value): string => $value === null
        ? '-'
        : number_format((float) $value, 1, ',', '.').'%';
@endphp

<style>
.consumer-ops { --co-navy:#062b63; --co-blue:#075ac9; --co-cyan:#0aa6c8; --co-ink:#16324f; --co-muted:#60758e; --co-line:#dce8f5; display:grid; gap:1rem; margin-top:1.15rem; color:var(--co-ink); }
.consumer-ops * { box-sizing:border-box; }
.consumer-ops-hero { position:relative; isolation:isolate; display:grid; grid-template-columns:minmax(0,1.45fr) minmax(250px,.55fr); min-height:230px; overflow:hidden; border:1px solid rgba(110,190,255,.3); border-radius:22px; background:linear-gradient(128deg,#05285e 0%,#064896 53%,#087fab 100%); box-shadow:0 18px 40px rgba(5,43,99,.18); }
.consumer-ops-hero::before { content:""; position:absolute; inset:auto -8% -65% 34%; z-index:-1; height:300px; border-radius:50%; background:radial-gradient(circle,rgba(109,224,239,.24),transparent 68%); }
.consumer-ops-hero__copy { align-self:center; padding:1.45rem 1.55rem; }
.consumer-ops-eyebrow { display:inline-flex; align-items:center; gap:.45rem; margin-bottom:.55rem; color:#99eafb; font-size:.7rem; font-weight:900; letter-spacing:.14em; text-transform:uppercase; }
.consumer-ops-eyebrow::before { content:""; width:24px; height:2px; border-radius:3px; background:#61dced; }
.consumer-ops-hero h2 { margin:0; max-width:720px; color:#fff; font-size:clamp(1.4rem,2.25vw,2.25rem); font-weight:900; letter-spacing:-.035em; line-height:1.08; }
.consumer-ops-hero p { max-width:680px; margin:.7rem 0 1rem; color:#d5eaff; font-size:.86rem; line-height:1.55; }
.consumer-ops-hero__metrics { display:flex; flex-wrap:wrap; gap:.55rem; }
.consumer-ops-hero__metric { display:grid; min-width:130px; padding:.62rem .78rem; border:1px solid rgba(255,255,255,.2); border-radius:12px; background:rgba(255,255,255,.1); backdrop-filter:blur(8px); }
.consumer-ops-hero__metric span { color:#bcd9f7; font-size:.62rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase; }
.consumer-ops-hero__metric strong { margin-top:.12rem; color:#fff; font-size:1rem; font-weight:900; }
.consumer-ops-hero__actions { display:flex; align-items:center; gap:.55rem; margin-top:.85rem; }
.consumer-ops-refresh { min-height:42px; padding:.6rem .86rem; border:1px solid rgba(255,255,255,.38); border-radius:11px; color:#fff; background:rgba(3,29,69,.26); font-size:.75rem; font-weight:850; cursor:pointer; transition:transform .18s ease,background .18s ease; }
.consumer-ops-refresh:hover { transform:translateY(-1px); background:rgba(3,29,69,.46); }
.consumer-ops-refresh.is-loading i { animation:consumerSpin .8s linear infinite; }
.consumer-ops-hero__visual { position:relative; display:grid; place-items:end center; min-height:230px; padding:.7rem 1rem 0; }
.consumer-ops-hero__visual svg { display:block; width:min(100%,285px); height:auto; filter:drop-shadow(0 16px 24px rgba(2,27,63,.28)); }
.consumer-ops-section { overflow:hidden; border:1px solid var(--co-line); border-radius:19px; background:#fff; box-shadow:0 10px 28px rgba(28,67,112,.08); }
.consumer-ops-section__head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding:1.05rem 1.15rem .85rem; }
.consumer-ops-title { display:flex; align-items:flex-start; gap:.72rem; min-width:0; }
.consumer-ops-title__icon { display:grid; flex:0 0 42px; width:42px; height:42px; place-items:center; border-radius:13px; color:#fff; background:linear-gradient(145deg,var(--co-blue),var(--co-cyan)); box-shadow:0 7px 15px rgba(7,90,201,.2); }
.consumer-ops-kicker { display:block; margin-bottom:.15rem; color:#1781b6; font-size:.64rem; font-weight:900; letter-spacing:.11em; text-transform:uppercase; }
.consumer-ops-title h3,.consumer-ops-product__title h4 { margin:0; color:#0b376b; font-size:1.04rem; font-weight:900; }
.consumer-ops-title p { margin:.25rem 0 0; color:var(--co-muted); font-size:.75rem; line-height:1.45; }
.consumer-ops-count { flex:0 0 auto; padding:.5rem .72rem; border-radius:11px; color:#0b568d; background:#eaf7fc; font-size:.7rem; font-weight:850; }
.consumer-ops-tabs { display:flex; flex-wrap:wrap; gap:.45rem; padding:0 1.15rem .9rem; }
.consumer-ops-tab { min-height:42px; padding:.55rem .78rem; border:1px solid #cfdeed; border-radius:11px; color:#315b82; background:#f7fbff; font-size:.72rem; font-weight:850; cursor:pointer; transition:border-color .18s ease,background .18s ease,color .18s ease,transform .18s ease; }
.consumer-ops-tab:hover { transform:translateY(-1px); border-color:#75b4e5; }
.consumer-ops-tab.is-active { border-color:var(--co-blue); color:#fff; background:linear-gradient(135deg,#075ac9,#087eae); box-shadow:0 6px 14px rgba(7,90,201,.2); }
.consumer-ops-tab:focus-visible,.consumer-ops-refresh:focus-visible,.consumer-ops-source-link:focus-visible,.consumer-ops-q:focus-visible { outline:3px solid #fbbf24; outline-offset:2px; }
.consumer-ops-pipeline-panel[hidden],.consumer-ops-quadrant-panel[hidden] { display:none !important; }
.consumer-ops-sourcebar { display:flex; align-items:center; justify-content:space-between; gap:.8rem; padding:.68rem 1.15rem; border-top:1px solid var(--co-line); background:linear-gradient(90deg,#f7fbff,#eef8fc); }
.consumer-ops-sourcebar__identity { min-width:0; }
.consumer-ops-sourcebar__identity strong { display:block; color:#133d69; font-size:.78rem; }
.consumer-ops-sourcebar__identity span { display:block; margin-top:.08rem; color:#66809a; font-size:.66rem; }
.consumer-ops-source-link { display:inline-flex; align-items:center; gap:.4rem; min-height:40px; padding:.48rem .68rem; border:1px solid #bdd7ea; border-radius:10px; color:#0757a4; background:#fff; font-size:.68rem; font-weight:850; text-decoration:none; white-space:nowrap; }
.consumer-ops-source-state { display:inline-flex; align-items:center; gap:.32rem; margin-left:.35rem; color:#16835f; font-weight:850; }
.consumer-ops-source-state.is-stale { color:#ad6700; }
.consumer-ops-source-state.is-offline { color:#a33b4f; }
.consumer-ops-table-wrap { width:100%; overflow:auto; overscroll-behavior-inline:contain; }
.consumer-ops-table { width:100%; min-width:940px; border-collapse:separate; border-spacing:0; font-size:.73rem; }
.consumer-ops-table th,.consumer-ops-table td { padding:.7rem .78rem; border-bottom:1px solid #e6eef7; vertical-align:middle; }
.consumer-ops-table thead th { position:sticky; top:0; z-index:2; color:#e8f4ff; background:#0a3d77; font-size:.64rem; font-weight:900; letter-spacing:.055em; text-align:left; text-transform:uppercase; white-space:nowrap; }
.consumer-ops-table tbody tr:hover { background:#f4faff; }
.consumer-ops-table tbody tr:last-child td { border-bottom:0; }
.consumer-ops-table .is-number { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
.consumer-ops-rank { display:grid; width:28px; height:28px; place-items:center; border-radius:9px; color:#0757a4; background:#e7f3ff; font-weight:900; }
.consumer-ops-instansi strong { display:block; max-width:330px; color:#14395e; font-size:.76rem; line-height:1.35; }
.consumer-ops-instansi span,.consumer-ops-rm span { display:block; margin-top:.18rem; color:#7890a7; font-size:.64rem; }
.consumer-ops-rm strong { display:block; color:#285778; font-size:.7rem; }
.consumer-ops-potential { display:inline-flex; min-width:56px; justify-content:center; padding:.34rem .52rem; border-radius:999px; color:#07573e; background:#dff7eb; font-weight:900; }
.consumer-ops-payroll { display:inline-flex; align-items:center; gap:.3rem; color:#0b6b8f; font-weight:850; white-space:nowrap; }
.consumer-ops-empty { display:grid; min-height:150px; place-items:center; padding:1.2rem; color:#667d94; text-align:center; }
.consumer-ops-empty i { display:block; margin-bottom:.4rem; color:#8baac5; font-size:1.45rem; }
.consumer-ops-products { display:grid; gap:1rem; padding:0 1.15rem 1.15rem; }
.consumer-ops-product { overflow:hidden; border:1px solid #d8e7f4; border-radius:16px; background:#fbfdff; }
.consumer-ops-product__head { display:flex; align-items:flex-start; justify-content:space-between; gap:.8rem; padding:.9rem 1rem .75rem; border-bottom:1px solid #dfebf6; background:linear-gradient(90deg,#f5faff,#fff); }
.consumer-ops-product__title { display:flex; align-items:center; gap:.62rem; }
.consumer-ops-product__mark { display:grid; flex:0 0 36px; width:36px; height:36px; place-items:center; border-radius:11px; color:#075ac9; background:#e5f1ff; }
.consumer-ops-product__title span { display:block; margin-top:.13rem; color:#6d8298; font-size:.64rem; }
.consumer-ops-product__identity { display:flex; align-items:center; justify-content:flex-end; gap:.68rem; }
.consumer-ops-product__period { display:grid; text-align:right; }
.consumer-ops-product__period span { color:#7890a7; font-size:.58rem; font-weight:850; letter-spacing:.08em; text-transform:uppercase; }
.consumer-ops-product__period strong { margin-top:.12rem; color:#0b4b86; font-size:.72rem; }
.consumer-ops-rm-visual { display:grid; width:82px; height:54px; place-items:end center; overflow:hidden; border:1px solid #cfe5f5; border-radius:12px; background:linear-gradient(145deg,#eaf7ff,#f9fdff); }
.consumer-ops-rm-visual svg { display:block; width:76px; height:auto; }
.consumer-ops-quadrant-table { min-width:650px; }
.consumer-ops-quadrant-table thead th:nth-child(n+3),.consumer-ops-quadrant-table td:nth-child(n+3) { text-align:center; }
.consumer-ops-q { display:inline-grid; min-width:44px; min-height:40px; padding:.35rem .55rem; place-items:center; border:1px solid transparent; border-radius:9px; font:inherit; font-weight:900; cursor:pointer; transition:filter .16s ease,box-shadow .16s ease; }
.consumer-ops-q:hover:not(:disabled) { filter:saturate(1.18) brightness(.97); box-shadow:0 4px 10px rgba(15,55,95,.14); }
.consumer-ops-q:disabled { cursor:not-allowed; opacity:.62; }
.consumer-ops-q.q1 { color:#08734e; background:#dcf7e9; }
.consumer-ops-q.q2 { color:#08658e; background:#dff4fb; }
.consumer-ops-q.q3 { color:#9a5e00; background:#fff1cf; }
.consumer-ops-q.q4 { color:#a52b45; background:#ffe3e9; }
.consumer-ops-total { color:#0b4077; font-weight:900; }
.consumer-ops-rm-table { min-width:1220px; }
.consumer-ops-rm-table th,.consumer-ops-rm-table td { border-right:1px solid #e6eef7; }
.consumer-ops-rm-table th:last-child,.consumer-ops-rm-table td:last-child { border-right:0; }
.consumer-ops-rm-table thead tr:first-child th { background:#082f63; text-align:center; }
.consumer-ops-rm-table thead tr:first-child th:nth-child(-n+3) { text-align:left; }
.consumer-ops-rm-table thead tr:nth-child(2) th { top:var(--abah-table-head-top,35px); color:#cfe9ff; background:#0b4d8d; text-align:center; }
.consumer-ops-rm-table tbody td { background:#fff; }
.consumer-ops-rm-table tbody tr:nth-child(even) td { background:#f8fbfe; }
.consumer-ops-rm-table tbody tr:hover td { background:#eef7ff; }
.consumer-ops-rm-table tfoot td { position:sticky; bottom:0; z-index:1; color:#fff; background:#082f63; font-weight:900; }
.consumer-ops-rm-table .consumer-ops-rm-cell { min-width:220px; text-align:left; }
.consumer-ops-rm-profile { display:flex; align-items:center; gap:.62rem; min-width:0; }
.consumer-ops-rm-avatar { display:grid; flex:0 0 34px; width:34px; height:34px; place-items:center; border:1px solid #b9def6; border-radius:10px; color:#07569b; background:linear-gradient(145deg,#dff4ff,#eefbf8); font-size:.64rem; font-weight:950; letter-spacing:.03em; }
.consumer-ops-rm-profile strong { display:block; overflow:hidden; color:#173b5f; font-size:.7rem; text-overflow:ellipsis; white-space:nowrap; }
.consumer-ops-rm-profile span { display:block; margin-top:.12rem; color:#7890a7; font-size:.6rem; }
.consumer-ops-jg { display:inline-flex; min-width:46px; justify-content:center; padding:.3rem .46rem; border:1px solid #cbe3f3; border-radius:999px; color:#075a92; background:#edf8fd; font-size:.64rem; font-weight:900; }
.consumer-ops-achievement { display:grid; gap:.16rem; min-width:86px; }
.consumer-ops-achievement strong { color:#0b477e; font-size:.7rem; }
.consumer-ops-achievement span { color:#6b8299; font-size:.59rem; white-space:nowrap; }
.consumer-ops-quadrant-badge { display:inline-grid; min-width:46px; min-height:38px; place-items:center; border-radius:10px; font-size:.72rem; font-weight:950; }
.consumer-ops-quadrant-badge.q1 { color:#057451; background:#d9f7e8; }
.consumer-ops-quadrant-badge.q2 { color:#08658e; background:#ddf3fb; }
.consumer-ops-quadrant-badge.q3 { color:#946000; background:#fff0c9; }
.consumer-ops-quadrant-badge.q4 { color:#a22d49; background:#ffe1e8; }
.consumer-ops-quadrant-badge.q0 { color:#718399; background:#edf2f7; }
.consumer-ops-rm-note { display:flex; flex-wrap:wrap; gap:.45rem 1rem; margin:.72rem 1rem; color:#55718d; font-size:.66rem; }
.consumer-ops-rm-note span { display:inline-flex; align-items:center; gap:.32rem; }
.consumer-ops-quadrant-hint { display:flex; align-items:center; gap:.4rem; margin:.7rem 1rem 0; color:#55718d; font-size:.68rem; font-weight:750; }
.consumer-ops-coverage { display:flex; flex-wrap:wrap; gap:.5rem; padding:.62rem 1rem; border-top:1px solid #e4edf6; color:#637a91; background:#f8fbfe; font-size:.66rem; }
.consumer-ops-coverage strong { color:#17466f; }
.consumer-ops-coverage__warning { color:#a65d00; }
.consumer-quadrant-modal { max-width:min(680px,calc(100vw - 24px)); }
.consumer-quadrant-modal__lead { margin:0 0 .75rem; color:#526b87; font-size:.82rem; line-height:1.5; text-align:left; }
.consumer-quadrant-modal__list { display:grid; max-height:min(390px,48vh); overflow:auto; border:1px solid #d8e4f1; border-radius:10px; background:#fff; text-align:left; }
.consumer-quadrant-modal__row { display:grid; grid-template-columns:34px minmax(0,1fr); gap:.65rem; align-items:center; min-width:0; padding:.62rem .7rem; border-bottom:1px solid #e5edf5; }
.consumer-quadrant-modal__row:last-child { border-bottom:0; }
.consumer-quadrant-modal__row:nth-child(even) { background:#f8fbfe; }
.consumer-quadrant-modal__number { display:grid; width:30px; height:30px; place-items:center; border-radius:9px; color:#0757a4; background:#e7f3ff; font-size:.68rem; font-weight:900; }
.consumer-quadrant-modal__name,.consumer-quadrant-modal__branch { display:block; overflow-wrap:anywhere; }
.consumer-quadrant-modal__name { color:#152d4f; font-size:.8rem; font-weight:850; }
.consumer-quadrant-modal__branch { margin-top:.08rem; color:#6b7f96; font-size:.68rem; }
.consumer-ops-load-error { display:grid; min-height:260px; place-items:center; align-content:center; gap:.55rem; padding:1.4rem; border:1px solid #f0c6cf; border-radius:18px; color:#7f2639; background:#fff6f7; text-align:center; }
.consumer-ops-load-error button { min-height:42px; padding:.55rem .85rem; border:0; border-radius:10px; color:#fff; background:#a62f49; font-weight:850; }
.consumer-ops-loader { display:grid; gap:.7rem; padding:1rem; }
.consumer-ops-loader__hero,.consumer-ops-loader__block { position:relative; overflow:hidden; border-radius:16px; background:#dfe9f3; }
.consumer-ops-loader__hero { height:150px; }
.consumer-ops-loader__block { height:190px; }
.consumer-ops-loader__hero::after,.consumer-ops-loader__block::after { content:""; position:absolute; inset:0; transform:translateX(-100%); background:linear-gradient(90deg,transparent,rgba(255,255,255,.72),transparent); animation:consumerShimmer 1.35s infinite; }
@keyframes consumerSpin { to { transform:rotate(360deg); } }
@keyframes consumerShimmer { to { transform:translateX(100%); } }
@media (max-width:899.98px) {
  .consumer-ops-hero { grid-template-columns:1fr 220px; }
  .consumer-ops-hero__copy { padding:1.15rem; }
  .consumer-ops-hero__visual { min-height:205px; }
  .consumer-ops-section__head,.consumer-ops-product__head { align-items:flex-start; }
}
@media (max-width:767.98px) {
  .consumer-ops { gap:.8rem; margin-top:.85rem; }
  .consumer-ops-hero { grid-template-columns:1fr; border-radius:17px; }
  .consumer-ops-hero__visual { min-height:150px; max-height:175px; order:-1; place-items:end center; padding-top:.4rem; }
  .consumer-ops-hero__visual svg { width:180px; }
  .consumer-ops-hero__copy { padding:1rem; }
  .consumer-ops-hero__metric { flex:1 1 115px; min-width:0; }
  .consumer-ops-section { border-radius:15px; }
  .consumer-ops-section__head,.consumer-ops-product__head,.consumer-ops-sourcebar { flex-direction:column; }
  .consumer-ops-section__head { padding:.9rem; }
  .consumer-ops-tabs { padding:0 .9rem .8rem; }
  .consumer-ops-tab { flex:1 1 135px; }
  .consumer-ops-sourcebar { align-items:stretch; padding:.7rem .9rem; }
  .consumer-ops-source-link { justify-content:center; }
  .consumer-ops-products { padding:0 .75rem .75rem; }
  .consumer-ops-count { align-self:flex-start; }
  .consumer-ops-product__identity { width:100%; justify-content:space-between; }
  .consumer-ops-product__period { text-align:left; }
}
@media (max-width:379.98px) {
  .consumer-ops-hero__visual { min-height:125px; max-height:140px; }
  .consumer-ops-hero__visual svg { width:148px; }
  .consumer-ops-title__icon { flex-basis:38px; width:38px; height:38px; }
  .consumer-ops-tab { flex-basis:100%; }
}
@media (prefers-reduced-motion:reduce) {
  .consumer-ops * { scroll-behavior:auto !important; transition-duration:.01ms !important; animation-duration:.01ms !important; }
  .consumer-ops-loader__hero::after,.consumer-ops-loader__block::after { animation:none; }
}
</style>

<div class="consumer-ops" data-consumer-operations-ready="1">
    <header class="consumer-ops-hero">
        <div class="consumer-ops-hero__copy">
            <span class="consumer-ops-eyebrow">Consumer Growth Command Center</span>
            <h2>Pipeline Instansi &amp; Peta Kuadran RM</h2>
            <p>{{ data_get($meta, 'scope_label', 'Area 6') }} &middot; prioritas 10 instansi berpotensi terbesar dan tren kuadran RM Konsumer per cabang.</p>
            <div class="consumer-ops-hero__metrics" aria-label="Ringkasan dashboard konsumer">
                <div class="consumer-ops-hero__metric">
                    <span>Sumber aktif</span>
                    <strong>{{ $formatInteger(data_get($pipeline, 'source_count', 0)) }}</strong>
                </div>
                <div class="consumer-ops-hero__metric">
                    <span>Top instansi</span>
                    <strong>{{ $formatInteger(data_get($pipeline, 'institution_count', 0)) }}</strong>
                </div>
                <div class="consumer-ops-hero__metric">
                    <span>Posisi kuadran</span>
                    <strong>{{ data_get($meta, 'period_label', '-') }}</strong>
                </div>
            </div>
            <div class="consumer-ops-hero__actions">
                <button type="button" class="consumer-ops-refresh" data-consumer-operations-refresh>
                    <i class="fas fa-sync-alt" aria-hidden="true"></i> Perbarui data
                </button>
            </div>
        </div>
        <div class="consumer-ops-hero__visual">
            <svg viewBox="0 0 300 250" role="img" aria-labelledby="consumer-rm-illustration-title consumer-rm-illustration-desc">
                <title id="consumer-rm-illustration-title">Ilustrasi Relationship Manager Konsumer</title>
                <desc id="consumer-rm-illustration-desc">Relationship Manager menganalisis pipeline instansi dan kuadran kinerja.</desc>
                <defs>
                    <linearGradient id="consumer-shirt" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#a5f3fc"/><stop offset="1" stop-color="#3bc7de"/></linearGradient>
                    <linearGradient id="consumer-card" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#fff" stop-opacity=".97"/><stop offset="1" stop-color="#d7f2ff" stop-opacity=".92"/></linearGradient>
                </defs>
                <ellipse cx="154" cy="229" rx="118" ry="15" fill="#031d44" opacity=".3"/>
                <rect x="23" y="31" width="111" height="78" rx="15" fill="url(#consumer-card)"/>
                <rect x="38" y="48" width="42" height="7" rx="3.5" fill="#0c77bd" opacity=".3"/>
                <path d="M39 89l17-16 15 9 20-25 27 29" fill="none" stroke="#0aa6c8" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="56" cy="73" r="5" fill="#31c48d"/><circle cx="71" cy="82" r="5" fill="#31c48d"/><circle cx="91" cy="57" r="5" fill="#31c48d"/><circle cx="118" cy="86" r="5" fill="#31c48d"/>
                <rect x="177" y="25" width="99" height="67" rx="14" fill="url(#consumer-card)"/>
                <circle cx="201" cy="52" r="12" fill="#daf8e9"/><path d="M196 52l4 4 8-10" fill="none" stroke="#11956a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                <rect x="221" y="43" width="39" height="6" rx="3" fill="#0a4d8c" opacity=".38"/><rect x="221" y="57" width="29" height="6" rx="3" fill="#0a4d8c" opacity=".22"/>
                <circle cx="164" cy="91" r="34" fill="#ffd0ad"/>
                <path d="M131 91c1-28 16-43 38-42 22 1 33 18 30 43-9-13-23-20-42-18-8 1-17 7-26 17z" fill="#15395f"/>
                <path d="M139 125c12 11 38 12 51 0 26 9 42 31 47 69H91c5-38 22-61 48-69z" fill="url(#consumer-shirt)"/>
                <path d="M153 126l11 19 12-19 8 4-9 48h-23l-7-48z" fill="#fff" opacity=".92"/>
                <path d="M164 145l8 10-8 26-8-26z" fill="#075ac9"/>
                <rect x="73" y="179" width="178" height="43" rx="10" fill="#f8fcff"/>
                <rect x="86" y="191" width="61" height="7" rx="3.5" fill="#0b5da6" opacity=".24"/><rect x="86" y="204" width="92" height="6" rx="3" fill="#0b5da6" opacity=".13"/>
                <circle cx="221" cy="201" r="11" fill="#e1f8ee"/><path d="M216 201l4 4 7-9" fill="none" stroke="#15986e" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
    </header>

    <section class="consumer-ops-section" aria-labelledby="consumer-pipeline-title">
        <div class="consumer-ops-section__head">
            <div class="consumer-ops-title">
                <span class="consumer-ops-title__icon"><i class="fas fa-building" aria-hidden="true"></i></span>
                <div>
                    <span class="consumer-ops-kicker">Prioritas Akuisisi</span>
                    <h3 id="consumer-pipeline-title">Pipeline Instansi per Cabang</h3>
                    <p>Sepuluh instansi dengan nilai Potensi Briguna terbesar dari masing-masing worksheet.</p>
                </div>
            </div>
            <span class="consumer-ops-count">{{ $formatInteger(data_get($pipeline, 'institution_count', 0)) }} instansi ditampilkan</span>
        </div>

        @if($pipelineSources !== [])
            <div class="consumer-ops-tabs" role="tablist" aria-label="Pilih cabang pipeline instansi">
                @foreach($pipelineSources as $source)
                    @php $sourceKey = (string) data_get($source, 'key', ''); @endphp
                    <button type="button"
                            id="consumer-pipeline-tab-{{ $sourceKey }}"
                            class="consumer-ops-tab {{ $sourceKey === $firstPipelineKey ? 'is-active' : '' }}"
                            role="tab"
                            tabindex="{{ $sourceKey === $firstPipelineKey ? '0' : '-1' }}"
                            aria-selected="{{ $sourceKey === $firstPipelineKey ? 'true' : 'false' }}"
                            aria-controls="consumer-pipeline-panel-{{ $sourceKey }}"
                            data-consumer-pipeline-tab="{{ $sourceKey }}">
                        {{ data_get($source, 'label', '-') }}
                    </button>
                @endforeach
            </div>

            @foreach($pipelineSources as $source)
                @php
                    $sourceKey = (string) data_get($source, 'key', '');
                    $sourceRows = array_values((array) data_get($source, 'rows', []));
                    $sourceStale = (bool) data_get($source, 'stale', false);
                    $sourceAvailable = (bool) data_get($source, 'available', false);
                @endphp
                <div id="consumer-pipeline-panel-{{ $sourceKey }}"
                     class="consumer-ops-pipeline-panel"
                     role="tabpanel"
                     aria-labelledby="consumer-pipeline-tab-{{ $sourceKey }}"
                     data-consumer-pipeline-panel="{{ $sourceKey }}"
                     {{ $sourceKey === $firstPipelineKey ? '' : 'hidden' }}>
                    <div class="consumer-ops-sourcebar">
                        <div class="consumer-ops-sourcebar__identity">
                            <strong>{{ data_get($source, 'label', '-') }} &middot; {{ data_get($source, 'sheet', '-') }}</strong>
                            <span>
                                {{ $formatInteger(data_get($source, 'row_count', 0)) }} instansi &middot; Potensi Top 10 {{ $formatInteger(data_get($source, 'total_potential', 0)) }} debitur
                                <span class="consumer-ops-source-state {{ $sourceStale ? 'is-stale' : ($sourceAvailable ? '' : 'is-offline') }}">
                                    <i class="fas {{ $sourceStale ? 'fa-history' : ($sourceAvailable ? 'fa-check-circle' : 'fa-exclamation-circle') }}" aria-hidden="true"></i>
                                    {{ $sourceStale ? 'Cache terakhir' : ($sourceAvailable ? 'Google Sheets terhubung' : 'Sumber belum tersedia') }}
                                </span>
                            </span>
                        </div>
                        <a class="consumer-ops-source-link" href="{{ data_get($source, 'source_url', '#') }}" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-external-link-alt" aria-hidden="true"></i> Buka sumber
                        </a>
                    </div>

                    @if($sourceRows !== [])
                        <div class="consumer-ops-table-wrap" tabindex="0" aria-label="Tabel pipeline instansi {{ data_get($source, 'label', '-') }}">
                            <table class="consumer-ops-table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Instansi</th>
                                        <th>RM PIC</th>
                                        <th class="is-number">Potensi Briguna</th>
                                        <th class="is-number">Total Pegawai</th>
                                        <th class="is-number">Sudah Terlayani</th>
                                        <th class="is-number">MTD Deb</th>
                                        <th class="is-number">OS MTD</th>
                                        <th>Payroll</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sourceRows as $row)
                                        <tr>
                                            <td><span class="consumer-ops-rank">{{ data_get($row, 'rank', $loop->iteration) }}</span></td>
                                            <td class="consumer-ops-instansi">
                                                <strong>{{ data_get($row, 'institution', '-') }}</strong>
                                                @if(data_get($row, 'leader'))<span>{{ data_get($row, 'leader') }}</span>@endif
                                            </td>
                                            <td class="consumer-ops-rm">
                                                @forelse((array) data_get($row, 'rm_names', []) as $rmName)
                                                    <strong>{{ $rmName }}</strong>
                                                @empty
                                                    <span>Belum ditetapkan</span>
                                                @endforelse
                                            </td>
                                            <td class="is-number"><span class="consumer-ops-potential">{{ $formatInteger(data_get($row, 'potential', 0)) }}</span></td>
                                            <td class="is-number">{{ $formatInteger(data_get($row, 'employees', 0)) }}</td>
                                            <td class="is-number">{{ data_get($row, 'served') ?: '-' }}</td>
                                            <td class="is-number">{{ $formatInteger(data_get($row, 'mtd_debtors', 0)) }}</td>
                                            <td class="is-number">{{ $formatAmount(data_get($row, 'latest_outstanding', 0)) }}</td>
                                            <td>
                                                @if(data_get($row, 'payroll'))
                                                    <span class="consumer-ops-payroll"><i class="fas fa-check-circle" aria-hidden="true"></i>{{ data_get($row, 'payroll') }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="consumer-ops-empty" role="status">
                            <div><i class="fas fa-inbox" aria-hidden="true"></i>{{ data_get($source, 'error', 'Data instansi belum tersedia pada worksheet ini.') }}</div>
                        </div>
                    @endif
                </div>
            @endforeach
        @else
            <div class="consumer-ops-empty" role="status"><div><i class="fas fa-inbox" aria-hidden="true"></i>Sumber pipeline instansi belum tersedia untuk lingkup ini.</div></div>
        @endif
    </section>

    @if(data_get($kprPipeline, 'visible', false))
        @php
            $kprPipelineStale = (bool) data_get($kprPipeline, 'stale', false);
            $kprPipelineAvailable = (bool) data_get($kprPipeline, 'available', false);
        @endphp
        <section class="consumer-ops-section" aria-labelledby="consumer-kpr-pipeline-title">
            <div class="consumer-ops-section__head">
                <div class="consumer-ops-title">
                    <span class="consumer-ops-title__icon"><i class="fas fa-home" aria-hidden="true"></i></span>
                    <div>
                        <span class="consumer-ops-kicker">Pipeline KPR</span>
                        <h3 id="consumer-kpr-pipeline-title">Pipeline KPR KC Madiun</h3>
                        <p>Debitur KPR aktif dari worksheet Madiun. Baris template tanpa nama debitur tidak ditampilkan.</p>
                    </div>
                </div>
                <span class="consumer-ops-count">{{ $formatInteger(data_get($kprPipeline, 'row_count', 0)) }} pipeline aktif</span>
            </div>

            <div class="consumer-ops-sourcebar">
                <div class="consumer-ops-sourcebar__identity">
                    <strong>{{ data_get($kprPipeline, 'label', 'KC Madiun') }} &middot; {{ data_get($kprPipeline, 'sheet', 'Madiun') }}</strong>
                    <span>
                        {{ $formatInteger(data_get($kprPipeline, 'rm_count', 0)) }} RM &middot; Total plafon {{ $formatInteger(data_get($kprPipeline, 'total_plafond_juta', 0)) }} juta
                        <span class="consumer-ops-source-state {{ $kprPipelineStale ? 'is-stale' : ($kprPipelineAvailable ? '' : 'is-offline') }}">
                            <i class="fas {{ $kprPipelineStale ? 'fa-history' : ($kprPipelineAvailable ? 'fa-check-circle' : 'fa-exclamation-circle') }}" aria-hidden="true"></i>
                            {{ $kprPipelineStale ? 'Cache terakhir' : ($kprPipelineAvailable ? 'Google Sheets terhubung' : 'Sumber belum tersedia') }}
                        </span>
                    </span>
                </div>
                <a class="consumer-ops-source-link" href="{{ data_get($kprPipeline, 'source_url', '#') }}" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-external-link-alt" aria-hidden="true"></i> Buka sumber
                </a>
            </div>

            @if($kprPipelineRows !== [])
                <div class="consumer-ops-table-wrap" tabindex="0" aria-label="Tabel pipeline KPR KC Madiun">
                    <table class="consumer-ops-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama RM</th>
                                <th>Nama Debitur</th>
                                <th>Fasilitas</th>
                                <th>Jenis Income</th>
                                <th>Nama Developer</th>
                                <th>Rencana Realisasi</th>
                                <th class="is-number">Plafond (Juta)</th>
                                <th>Keterangan Proses</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kprPipelineRows as $row)
                                <tr>
                                    <td><span class="consumer-ops-rank">{{ data_get($row, 'source_number') ?: $loop->iteration }}</span></td>
                                    <td class="consumer-ops-rm"><strong>{{ data_get($row, 'rm') ?: '-' }}</strong></td>
                                    <td class="consumer-ops-instansi"><strong>{{ data_get($row, 'debtor') ?: '-' }}</strong></td>
                                    <td>{{ data_get($row, 'facility') ?: '-' }}</td>
                                    <td>{{ data_get($row, 'income_type') ?: '-' }}</td>
                                    <td>{{ data_get($row, 'developer') ?: '-' }}</td>
                                    <td>{{ data_get($row, 'planned_realisation') ?: '-' }}</td>
                                    <td class="is-number"><span class="consumer-ops-potential">{{ $formatInteger(data_get($row, 'plafond_juta', 0)) }}</span></td>
                                    <td>{{ data_get($row, 'process') ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="consumer-ops-empty" role="status">
                    <div><i class="fas fa-home" aria-hidden="true"></i>{{ data_get($kprPipeline, 'error', 'Pipeline KPR belum tersedia pada worksheet Madiun.') }}</div>
                </div>
            @endif
        </section>
    @endif

    <section class="consumer-ops-section" aria-labelledby="consumer-quadrant-title">
        <div class="consumer-ops-section__head">
            <div class="consumer-ops-title">
                <span class="consumer-ops-title__icon"><i class="fas fa-th-large" aria-hidden="true"></i></span>
                <div>
                    <span class="consumer-ops-kicker">Produktivitas RM &middot; Posisi {{ data_get($quadrants, 'period_label', '-') }}</span>
                    <h3 id="consumer-quadrant-title">Tren Kuadran RM Konsumer</h3>
                    <p>Target, realisasi baru, nett disbursement, capaian, dan posisi kuadran setiap RM dalam satu tampilan bulan berjalan.</p>
                </div>
            </div>
        </div>

        <div class="consumer-ops-products">
            @foreach(['briguna' => ['title' => 'Kuadran RM Briguna', 'icon' => 'fas fa-hand-holding-usd'], 'kpr' => ['title' => 'Kuadran RM KPR', 'icon' => 'fas fa-home']] as $productKey => $productUi)
                <article class="consumer-ops-product" data-consumer-quadrant-product="{{ $productKey }}">
                    <div class="consumer-ops-product__head">
                        <div class="consumer-ops-product__title">
                            <span class="consumer-ops-product__mark"><i class="{{ $productUi['icon'] }}" aria-hidden="true"></i></span>
                            <div>
                                <h4>{{ $productUi['title'] }}</h4>
                                <span>Q1 &ge;105% &middot; Q2 100&ndash;&lt;105% &middot; Q3 50&ndash;&lt;100% &middot; Q4 &lt;50%</span>
                            </div>
                        </div>
                        <div class="consumer-ops-product__identity">
                            <div class="consumer-ops-product__period">
                                <span>Posisi bulan berjalan</span>
                                <strong>{{ data_get($quadrants, 'period_label', '-') }}</strong>
                            </div>
                            <div class="consumer-ops-rm-visual" aria-hidden="true">
                                <svg viewBox="0 0 108 72" focusable="false">
                                    <path d="M7 65h94" stroke="#9bcbea" stroke-width="2" stroke-linecap="round"/>
                                    <rect x="56" y="17" width="44" height="39" rx="5" fill="#fff" stroke="#95c7e6"/>
                                    <path d="M64 44l9-9 7 5 12-15" fill="none" stroke="#08a6a6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M86 25h6v6" fill="none" stroke="#08a6a6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="32" cy="21" r="10" fill="#ffd5b5"/>
                                    <path d="M22 19c1-9 18-11 20 1-5-1-9-4-12-7-1 4-4 6-8 6Z" fill="#123b67"/>
                                    <path d="M18 58c0-17 5-26 14-26s15 9 15 26" fill="#0878bd"/>
                                    <path d="M32 33l6 25H26l6-25Z" fill="#fff"/>
                                    <path d="M31 36h3l2 12-4 6-4-6 3-12Z" fill="#0aa6c8"/>
                                    <rect x="41" y="47" width="28" height="16" rx="3" fill="#dff3fb" stroke="#83bddf"/>
                                    <circle cx="55" cy="55" r="2" fill="#0878bd"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    @if($quadrantBranches !== [])
                        <div class="consumer-ops-tabs" role="tablist" aria-label="Pilih cabang {{ $productUi['title'] }}" style="padding-top:.78rem">
                            @foreach($quadrantBranches as $branch)
                                @php $branchKey = (string) data_get($branch, 'key', ''); @endphp
                                <button type="button"
                                        id="consumer-{{ $productKey }}-tab-{{ $branchKey }}"
                                        class="consumer-ops-tab {{ $branchKey === $firstQuadrantBranch ? 'is-active' : '' }}"
                                        role="tab"
                                        tabindex="{{ $branchKey === $firstQuadrantBranch ? '0' : '-1' }}"
                                        aria-selected="{{ $branchKey === $firstQuadrantBranch ? 'true' : 'false' }}"
                                        aria-controls="consumer-{{ $productKey }}-panel-{{ $branchKey }}"
                                        data-consumer-quadrant-tab="{{ $branchKey }}"
                                        data-consumer-quadrant-product="{{ $productKey }}"
                                        @if($branchKey === 'area6')
                                            data-consumer-area6-trigger="1"
                                            title="Tampilkan gabungan Madiun, Magetan, Ngawi, dan Ponorogo"
                                        @endif>
                                    {{ data_get($branch, 'label', '-') }}
                                </button>
                            @endforeach
                        </div>

                        @foreach($quadrantBranches as $branch)
                            @php
                                $branchKey = (string) data_get($branch, 'key', '');
                                $product = (array) data_get($branch, 'products.'.$productKey, []);
                                $currentRows = array_values((array) data_get($product, 'current_rows', []));
                                $totals = (array) data_get($product, 'totals', []);
                            @endphp
                            <div id="consumer-{{ $productKey }}-panel-{{ $branchKey }}"
                                 class="consumer-ops-quadrant-panel"
                                 role="tabpanel"
                                 aria-labelledby="consumer-{{ $productKey }}-tab-{{ $branchKey }}"
                                 data-consumer-quadrant-panel="{{ $branchKey }}"
                                 data-consumer-quadrant-product="{{ $productKey }}"
                                 {{ $branchKey === $firstQuadrantBranch ? '' : 'hidden' }}>
                                @if(!empty($product['available']) && $currentRows !== [])
                                    <div class="consumer-ops-rm-note">
                                        <span><i class="fas fa-info-circle" aria-hidden="true"></i> Realisasi Baru merupakan bagian dari Nett Disbursement.</span>
                                        <span><i class="fas fa-calculator" aria-hidden="true"></i> Total menampilkan capaian Nett terhadap Target, bukan penjumlahan dua realisasi.</span>
                                    </div>
                                    <div class="consumer-ops-table-wrap" tabindex="0" aria-label="{{ $productUi['title'] }} {{ data_get($branch, 'label', '-') }}">
                                        <table class="consumer-ops-table consumer-ops-rm-table">
                                            <caption class="sr-only">Produktivitas per RM {{ $productUi['title'] }} {{ data_get($branch, 'label', '-') }} posisi {{ data_get($quadrants, 'period_label', '-') }}</caption>
                                            <thead>
                                                <tr>
                                                    <th rowspan="2" scope="col">No</th>
                                                    <th rowspan="2" scope="col">RM</th>
                                                    <th rowspan="2" scope="col">JG</th>
                                                    <th colspan="2" scope="colgroup">Target</th>
                                                    <th colspan="2" scope="colgroup">Realisasi Baru</th>
                                                    <th colspan="2" scope="colgroup">Nett Disbursement</th>
                                                    <th rowspan="2" scope="col">Total</th>
                                                    <th rowspan="2" scope="col">Kuadran</th>
                                                </tr>
                                                <tr>
                                                    <th scope="col">Deb</th>
                                                    <th scope="col">Real</th>
                                                    <th scope="col">Deb</th>
                                                    <th scope="col">Real</th>
                                                    <th scope="col">Deb</th>
                                                    <th scope="col">Real</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($currentRows as $rmRow)
                                                    @php
                                                        $rmName = (string) data_get($rmRow, 'name', '-');
                                                        $rmInitials = collect(preg_split('/\s+/', trim($rmName)) ?: [])
                                                            ->filter()
                                                            ->take(2)
                                                            ->map(static fn ($part): string => strtoupper(substr((string) $part, 0, 1)))
                                                            ->implode('');
                                                        $quadrant = (int) data_get($rmRow, 'quadrant', 0);
                                                    @endphp
                                                    <tr>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td class="consumer-ops-rm-cell">
                                                            <div class="consumer-ops-rm-profile">
                                                                <span class="consumer-ops-rm-avatar" aria-hidden="true">{{ $rmInitials ?: 'RM' }}</span>
                                                                <span><strong>{{ $rmName }}</strong><span>{{ data_get($rmRow, 'branch', '-') }}</span></span>
                                                            </div>
                                                        </td>
                                                        <td><span class="consumer-ops-jg">{{ data_get($rmRow, 'jg', '-') }}</span></td>
                                                        <td class="is-number">{{ $formatInteger(data_get($rmRow, 'target_deb', 0)) }}</td>
                                                        <td class="is-number">{{ $formatAmount(data_get($rmRow, 'target_os', 0)) }}</td>
                                                        <td class="is-number">{{ $formatInteger(data_get($rmRow, 'new_deb', 0)) }}</td>
                                                        <td class="is-number">{{ $formatAmount(data_get($rmRow, 'new_os', 0)) }}</td>
                                                        <td class="is-number">{{ $formatInteger(data_get($rmRow, 'net_deb', 0)) }}</td>
                                                        <td class="is-number">{{ $formatAmount(data_get($rmRow, 'net_os', 0)) }}</td>
                                                        <td>
                                                            <span class="consumer-ops-achievement">
                                                                <strong>{{ $formatPercent(data_get($rmRow, 'achievement_os')) }}</strong>
                                                                <span>Deb {{ $formatPercent(data_get($rmRow, 'achievement_deb')) }}</span>
                                                            </span>
                                                        </td>
                                                        <td><span class="consumer-ops-quadrant-badge q{{ $quadrant }}">{{ $quadrant > 0 ? 'Q'.$quadrant : '-' }}</span></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3">TOTAL {{ strtoupper((string) data_get($branch, 'label', '-')) }}</td>
                                                    <td class="is-number">{{ $formatInteger(data_get($totals, 'target_deb', 0)) }}</td>
                                                    <td class="is-number">{{ $formatAmount(data_get($totals, 'target_os', 0)) }}</td>
                                                    <td class="is-number">{{ $formatInteger(data_get($totals, 'new_deb', 0)) }}</td>
                                                    <td class="is-number">{{ $formatAmount(data_get($totals, 'new_os', 0)) }}</td>
                                                    <td class="is-number">{{ $formatInteger(data_get($totals, 'net_deb', 0)) }}</td>
                                                    <td class="is-number">{{ $formatAmount(data_get($totals, 'net_os', 0)) }}</td>
                                                    <td><strong>{{ $formatPercent(data_get($totals, 'achievement_os')) }}</strong><br><small>Deb {{ $formatPercent(data_get($totals, 'achievement_deb')) }}</small></td>
                                                    <td>-</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <div class="consumer-ops-coverage">
                                        <span><strong>{{ $formatInteger(count($currentRows)) }}</strong> RM ditampilkan pada posisi bulan berjalan</span>
                                        @if((int) data_get($product, 'coverage.unclassified', 0) > 0)
                                            <span class="consumer-ops-coverage__warning"><i class="fas fa-info-circle" aria-hidden="true"></i> {{ $formatInteger(data_get($product, 'coverage.unclassified', 0)) }} RM belum memiliki target produk</span>
                                        @endif
                                    </div>
                                @else
                                    <div class="consumer-ops-empty" role="status"><div><i class="fas fa-chart-pie" aria-hidden="true"></i>Data {{ $productUi['title'] }} belum tersedia untuk {{ data_get($branch, 'label', '-') }}.</div></div>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <div class="consumer-ops-empty" role="status"><div><i class="fas fa-chart-pie" aria-hidden="true"></i>Data kuadran RM belum tersedia.</div></div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
    @include('dashboard.partials.pn-mismatch', ['pnMismatch' => data_get($consumerOperations, 'pn_mismatch', []), 'pnSegment' => 'consumer'])
</div>
