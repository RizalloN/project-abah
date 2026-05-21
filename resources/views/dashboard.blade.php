@extends('layouts.admin')
@section('title', 'A-SIX | Dashboard Area 6')
@section('content')
@php
$hero = data_get($dashboard ?? [], 'hero', []);
$metrics = data_get($dashboard ?? [], 'metrics', []);
$liveReports = is_array(data_get($dashboard ?? [], 'live_reports')) ? data_get($dashboard ?? [], 'live_reports') : [];
$digitalCards = is_array(data_get($dashboard ?? [], 'digital_performance.cards')) ? data_get($dashboard ?? [], 'digital_performance.cards') : [];
$timeseries = data_get($dashboard ?? [], 'timeseries', ['labels'=>[],'simpanan'=>[],'pinjaman'=>[]]);
$area6Portfolio = data_get($dashboard ?? [], 'area6_portfolio', []);
$area6Cards = is_array(data_get($area6Portfolio, 'cards')) ? data_get($area6Portfolio, 'cards') : [];
$area6Rankings = is_array(data_get($area6Portfolio, 'rankings')) ? data_get($area6Portfolio, 'rankings') : [];
$area6RankingModes = is_array(data_get($area6Portfolio, 'ranking_modes')) ? data_get($area6Portfolio, 'ranking_modes') : [];
$area6DefaultScope = data_get($area6Portfolio, 'default_scope', 'ritel');
$digitalUpdatedAt = data_get($dashboard ?? [], 'digital_performance.updated_at');
$simpananReport = collect($liveReports)->firstWhere('key', 'simpanan') ?? [];
$pinjamanReport = collect($liveReports)->firstWhere('key', 'pinjaman') ?? [];
$portfolioReport = collect($liveReports)->firstWhere('key', 'portfolio') ?? [];
$tsLabels = json_encode(data_get($timeseries,'labels',[]));
$tsSimpanan = json_encode(data_get($timeseries,'simpanan',[]));
$tsPinjaman = json_encode(data_get($timeseries,'pinjaman',[]));
@endphp

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900&display=swap');

:root {
  --c-blue: #0857c3; --c-blue-d: #053b82; --c-blue-l: #307fe2;
  --c-teal: #0f766e; --c-red: #dc2626; --c-amber: #d97706;
  --c-green: #059669; --c-purple: #7c3aed; --c-pink: #db2777;
  --c-surf: #f1f6ff; --c-border: rgba(8,87,195,.12);
  --shadow: 0 8px 24px -12px rgba(4,42,95,.28);
  --r: 14px;
}
.db-shell { font-family:'Inter',sans-serif; padding:0 0 1rem; }

/* ── KPI STRIP ── */
.kpi-strip { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:.8rem; margin-bottom:.8rem; }
.kpi-card { border-radius:var(--r); padding:.86rem 1rem .78rem; min-height:104px; position:relative; overflow:hidden; border:1px solid var(--c-border); background:#fff; transition:transform .18s,box-shadow .18s; }
.kpi-card:hover { transform:translateY(-2px); box-shadow:var(--shadow); }
.kpi-card .kc-label { font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#64748b; margin-bottom:.18rem; }
.kpi-card .kc-val { font-size:1.32rem; font-weight:800; line-height:1.05; color:#0f172a; }
.kpi-card .kc-sub { font-size:.62rem; color:#64748b; margin-top:.15rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.kpi-card .kc-delta { display:inline-flex; align-items:center; gap:.25rem; font-size:.65rem; font-weight:700; padding:.18rem .5rem; border-radius:999px; margin-top:.28rem; }
.kpi-card.simpanan { background:linear-gradient(145deg,#053b82,#0857c3); border:none; }
.kpi-card.simpanan .kc-label,.kpi-card.simpanan .kc-sub { color:rgba(255,255,255,.7); }
.kpi-card.simpanan .kc-val { color:#fff; font-size:1.6rem; }
.kpi-card.pinjaman { background:linear-gradient(145deg,#0a4f68,#1177a3); border:none; }
.kpi-card.pinjaman .kc-label,.kpi-card.pinjaman .kc-sub { color:rgba(255,255,255,.7); }
.kpi-card.pinjaman .kc-val { color:#fff; font-size:1.6rem; }
.kpi-card.portfolio { background:linear-gradient(145deg,#134e4a,#0f766e); border:none; }
.kpi-card.portfolio .kc-label,.kpi-card.portfolio .kc-sub { color:rgba(255,255,255,.7); }
.kpi-card.portfolio .kc-val { color:#fff; font-size:1.6rem; }
.kc-live { position:absolute; top:.55rem; right:.65rem; width:7px; height:7px; border-radius:999px; background:#4ade80; box-shadow:0 0 0 0 rgba(74,222,128,.45); animation:pulse-live 1.8s infinite; }
@keyframes pulse-live { 0%,100%{box-shadow:0 0 0 0 rgba(74,222,128,.45)} 70%{box-shadow:0 0 0 8px rgba(74,222,128,0)} }
.kpi-card .kc-link { position:absolute; bottom:.5rem; right:.7rem; font-size:.6rem; font-weight:700; color:rgba(255,255,255,.8); text-decoration:none; display:inline-flex; align-items:center; gap:.25rem; }
.kpi-card .kc-link:hover { color:#fff; }
.kpi-card button.kc-link { border:0; background:transparent; padding:0; cursor:pointer; }

/* ── CHART + DIGITAL GRID ── */
.area6-panel { margin:.8rem 0; border-radius:16px; background:#fff; border:1px solid var(--c-border); box-shadow:0 18px 42px -30px rgba(4,42,95,.4); overflow:hidden; }
.area6-head { display:flex; align-items:center; justify-content:space-between; gap:1.1rem; padding:1.12rem 1.2rem; background:linear-gradient(135deg,#f8fbff 0%,#eaf3ff 100%); border-bottom:1px solid var(--c-border); }
.area6-title { font-size:1.12rem; font-weight:900; color:#0f172a; letter-spacing:0; }
.area6-sub { margin-top:.2rem; font-size:.74rem; color:#64748b; }
.area6-head-actions { display:flex; flex-direction:column; align-items:flex-end; gap:.5rem; }
.area6-periods { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.38rem; }
.area6-pill { display:inline-flex; align-items:center; gap:.34rem; padding:.36rem .72rem; border-radius:999px; background:#fff; border:1px solid rgba(8,87,195,.14); color:#2563eb; font-size:.68rem; font-weight:850; white-space:nowrap; }
.area6-scope-toggle { display:inline-flex; gap:.25rem; padding:.22rem; border-radius:999px; background:#eaf2ff; border:1px solid rgba(37,99,235,.14); }
.area6-scope-btn { border:0; border-radius:999px; padding:.42rem .82rem; background:transparent; color:#475569; font-size:.7rem; font-weight:900; cursor:pointer; }
.area6-scope-btn.active { background:#fff; color:#0f4aa4; box-shadow:0 6px 16px -12px rgba(15,23,42,.5); }
.area6-card-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.84rem; padding:1rem; }
.area6-card { border:0; appearance:none; width:100%; min-height:150px; border-radius:14px; padding:1rem .95rem .9rem; text-align:left; color:#fff; position:relative; overflow:hidden; display:flex; flex-direction:column; cursor:pointer; transition:transform .16s, box-shadow .16s; }
.area6-card:hover { transform:translateY(-3px); box-shadow:0 18px 34px -22px rgba(15,23,42,.6); }
.area6-card::after { content:''; position:absolute; top:-38px; right:-32px; width:108px; height:108px; border-radius:999px; background:rgba(255,255,255,.13); pointer-events:none; }
.area6-card .ac-icon { width:38px; height:38px; display:flex; align-items:center; justify-content:center; border-radius:10px; background:rgba(255,255,255,.17); margin-bottom:.66rem; font-size:1rem; }
.area6-card .ac-label { font-size:.72rem; color:rgba(255,255,255,.78); line-height:1.25; min-height:1.55rem; }
.area6-card .ac-value { font-size:1.5rem; font-weight:900; line-height:1.08; margin-top:.2rem; word-break:break-word; }
.area6-card .ac-meta { margin-top:auto; padding-top:.56rem; font-size:.67rem; color:rgba(255,255,255,.72); line-height:1.35; }
.area6-card.tone-blue { background:linear-gradient(145deg,#064c9d,#1d87ff); }
.area6-card.tone-red { background:linear-gradient(145deg,#991b1b,#ef4444); }
.area6-card.tone-green { background:linear-gradient(145deg,#166534,#22c55e); }
.area6-card.tone-amber { background:linear-gradient(145deg,#92400e,#f59e0b); }
.area6-card.tone-purple { background:linear-gradient(145deg,#5b21b6,#8b5cf6); }
.area6-card.tone-orange { background:linear-gradient(145deg,#9a3412,#f97316); }
.area6-card.tone-teal { background:linear-gradient(145deg,#115e59,#14b8a6); }
.area6-ranking-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.72rem; padding:0 1rem 1rem; }
.rank-card { border:1px solid #dbe7f5; border-radius:14px; background:#fff; overflow:hidden; min-width:0; box-shadow:0 10px 28px -26px rgba(15,23,42,.35); }
.rank-card-head { padding:.82rem .9rem .62rem; border-bottom:1px solid #edf2f7; background:#fbfdff; }
.rank-card-title { display:flex; align-items:center; justify-content:space-between; gap:.5rem; font-size:.84rem; font-weight:900; color:#0f172a; }
.rank-card-title span { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.rank-card-hint { font-size:.64rem; color:#64748b; margin-top:.14rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.rank-badge { flex:0 0 auto; width:8px; height:8px; border-radius:999px; background:#3b82f6; }
.rank-card.tone-red .rank-badge { background:#ef4444; }
.rank-card.tone-amber .rank-badge { background:#f59e0b; }
.rank-card.tone-orange .rank-badge { background:#f97316; }
.rank-card.tone-teal .rank-badge { background:#14b8a6; }
.rank-card.tone-slate .rank-badge { background:#64748b; }
.rank-list { padding:.5rem .66rem .62rem; }
.rank-row { display:grid; grid-template-columns:30px minmax(0,1fr) auto; gap:.58rem; align-items:center; padding:.58rem .26rem; border-bottom:1px solid #f1f5f9; }
.rank-row:last-child { border-bottom:0; }
.rank-no { width:25px; height:25px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:#eff6ff; color:#1d4ed8; font-size:.66rem; font-weight:900; }
.rank-main { min-width:0; }
.rank-name { font-size:.76rem; font-weight:850; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.rank-meta { font-size:.62rem; color:#64748b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-top:.06rem; }
.rank-val { text-align:right; font-size:.74rem; font-weight:900; color:#0f172a; white-space:nowrap; }
.rank-sub { font-size:.6rem; color:#64748b; margin-top:.06rem; }
.rank-empty { padding:.9rem .7rem; font-size:.62rem; color:#94a3b8; text-align:center; }
.main-grid { display:grid; grid-template-columns:minmax(280px,.95fr) minmax(0,2.05fr); gap:.8rem; align-items:start; }
.chart-panel { border-radius:16px; background:#fff; border:1px solid var(--c-border); padding:1.05rem; min-height:380px; position:relative; box-shadow:0 12px 30px -28px rgba(15,23,42,.35); }
.chart-panel .cp-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:.6rem; }
.chart-panel .cp-title { font-size:.86rem; font-weight:850; color:#0f172a; }
.chart-panel .cp-legend { display:flex; gap:.85rem; }
.chart-panel .cp-leg-item { display:flex; align-items:center; gap:.35rem; font-size:.6rem; font-weight:600; color:#64748b; }
.chart-panel .cp-leg-dot { width:8px; height:8px; border-radius:999px; }
.chart-panel canvas { width:100% !important; height:300px !important; }
.chart-empty { display:none; position:absolute; left:1rem; right:1rem; top:5.2rem; bottom:1rem; align-items:center; justify-content:center; text-align:center; color:#64748b; border:1px dashed #dbe7f5; border-radius:12px; background:#f8fbff; font-size:.74rem; font-weight:700; }
.chart-panel.is-empty .chart-empty { display:flex; }
.chart-panel.is-empty canvas { opacity:0; }

/* ── DIGITAL GRID ── */
.digital-panel { border-radius:16px; background:#fff; border:1px solid var(--c-border); padding:.86rem; box-shadow:0 12px 30px -28px rgba(15,23,42,.35); }
.dp-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:.55rem; }
.dp-title { font-size:.86rem; font-weight:850; color:#0f172a; }
.dp-updated { font-size:.58rem; color:#94a3b8; }
.dp-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.66rem; }
.dc { border-radius:14px; padding:.96rem .9rem .82rem; color:#fff; position:relative; overflow:hidden; cursor:pointer; text-decoration:none; display:flex; flex-direction:column; transition:transform .16s,box-shadow .16s; border:0; text-align:left; width:100%; min-height:192px; font:inherit; appearance:none; }
.dc:hover { transform:translateY(-2px); box-shadow:0 12px 28px -16px rgba(4,42,95,.5); color:#fff; }
.dc::before { content:''; position:absolute; inset:-40% -30% auto auto; width:120px; height:120px; border-radius:999px; background:rgba(255,255,255,.1); pointer-events:none; }
.dc-edc { background:linear-gradient(145deg,#0a3ea1,#1d87ff); }
.dc-qris { background:linear-gradient(145deg,#08506c,#12a5c3); }
.dc-qlola { background:linear-gradient(145deg,#7c3aed,#a855f7); }
.dc-brimo { background:linear-gradient(145deg,#272e8f,#3b82f6); }
.dc-brilink { background:linear-gradient(145deg,#0d6b4d,#22c55e); }
.dc-casa { background:linear-gradient(145deg,#b45309,#f59e0b); }
.dc-dormant { background:linear-gradient(145deg,#991b1b,#ef4444); }
.dc-payroll { background:linear-gradient(145deg,#374151,#6b7280); }
.dc-badge { display:inline-flex; align-items:center; gap:.28rem; padding:.2rem .45rem; border-radius:999px; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.18); font-size:.55rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; margin-bottom:.35rem; width:fit-content; }
.dc-val { font-size:1.42rem; font-weight:900; line-height:1.05; }
.dc-label { font-size:.7rem; color:rgba(255,255,255,.73); margin-bottom:.24rem; }
.dc-sub { font-size:.68rem; color:rgba(255,255,255,.7); }
.dc-trend { display:inline-flex; align-items:center; gap:.22rem; font-size:.58rem; font-weight:700; padding:.16rem .4rem; border-radius:999px; background:rgba(255,255,255,.14); margin-top:auto; margin-bottom:.1rem; }
.dc-foot { display:flex; justify-content:space-between; align-items:center; margin-top:.3rem; }
.dc-link { font-size:.58rem; font-weight:700; color:rgba(255,255,255,.85); display:inline-flex; align-items:center; gap:.22rem; }
.dc-link:hover { color:#fff; }
.dc:focus-visible, .kpi-card button.kc-link:focus-visible { outline:2px solid rgba(255,255,255,.8); outline-offset:2px; }
.dc-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:.25rem; margin-top:.4rem; }
.dc-stat { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.1); border-radius:7px; padding:.25rem .3rem; }
.dc-stat-lbl { font-size:.57rem; color:rgba(255,255,255,.65); }
.dc-stat-val { font-size:.7rem; font-weight:700; }
.dc-stub { opacity:.72; filter:grayscale(.3); }
.dc-stub::after { content:'–'; }

/* ── HEADER ── */
.db-header { display:flex; align-items:center; justify-content:space-between; padding:.5rem .25rem .55rem; margin-bottom:.55rem; border-bottom:1px solid var(--c-border); }
.db-brand { display:flex; align-items:center; gap:.65rem; }
.db-logo { width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,#053b82,#307fe2); display:flex; align-items:center; justify-content:center; }
.db-logo img { width:26px; height:26px; object-fit:contain; }
.db-title { font-size:.88rem; font-weight:800; color:#0f172a; letter-spacing:-.01em; }
.db-subtitle { font-size:.6rem; color:#64748b; margin-top:.05rem; }
.db-meta { display:flex; align-items:center; gap:.75rem; }
.db-meta-chip { display:inline-flex; align-items:center; gap:.35rem; padding:.28rem .65rem; border-radius:999px; background:#f1f6ff; border:1px solid var(--c-border); font-size:.62rem; font-weight:700; color:#2563eb; }
.db-now { font-size:.6rem; color:#94a3b8; }

/* delta colors */
.pos { color:#059669; background:#ecfdf5; }
.neg { color:#dc2626; background:#fef2f2; }
.neu { color:#64748b; background:#f1f5f9; }
.source-modal-meta { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.55rem; margin-bottom:.75rem; }
.source-modal-chip { border:1px solid var(--c-border); border-radius:10px; padding:.55rem .65rem; background:#f8fbff; }
.source-modal-chip span { display:block; font-size:.58rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.06em; }
.source-modal-chip strong { display:block; margin-top:.15rem; font-size:.74rem; color:#0f172a; word-break:break-word; }
.source-modal-note { font-size:.72rem; color:#475569; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:.6rem .7rem; margin-bottom:.7rem; }
.source-modal-table th { font-size:.68rem; text-transform:uppercase; color:#64748b; letter-spacing:.04em; border-top:0; }
.source-modal-table td { font-size:.78rem; vertical-align:middle; }
.dashboard-source-modal { z-index:2070; }
.modal-backdrop.dashboard-source-backdrop { z-index:2060; background:#0f172a; }
.modal-backdrop.dashboard-source-backdrop.show { opacity:.36; }
.dashboard-source-modal .modal-content { border:0; border-radius:14px; box-shadow:0 24px 70px -36px rgba(15,23,42,.75); }
.dashboard-source-modal .modal-header,
.dashboard-source-modal .modal-footer { border-color:#e5eef8; }
.dashboard-source-modal .modal-title { font-size:1rem; font-weight:850; color:#0f172a; }
.dashboard-source-modal .btn { border-radius:8px; font-weight:800; }
@media (max-width: 1199.98px) {
  .kpi-strip { grid-template-columns:repeat(3,minmax(0,1fr)); }
  .main-grid { grid-template-columns:1fr; }
  .dp-grid { grid-template-columns:repeat(4,minmax(0,1fr)); }
}
@media (max-width: 1199.98px) {
  .area6-card-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
  .area6-ranking-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media (max-width: 767.98px) {
  .db-header { align-items:flex-start; flex-direction:column; gap:.55rem; }
  .db-meta { flex-wrap:wrap; gap:.42rem; }
  .kpi-strip { grid-template-columns:1fr; }
  .kpi-card .kc-sub { white-space:normal; }
  .area6-head { align-items:flex-start; flex-direction:column; }
  .area6-head-actions { align-items:flex-start; }
  .area6-periods { justify-content:flex-start; }
  .area6-card-grid, .area6-ranking-grid { grid-template-columns:1fr; }
  .dp-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .dc { min-height:174px; }
  .chart-panel { min-height:330px; }
  .chart-panel canvas { height:250px !important; }
  .source-modal-meta { grid-template-columns:1fr; }
}
@media (max-width: 575.98px) {
  .db-shell { padding-bottom:.25rem; }
  .area6-panel, .chart-panel, .digital-panel { border-radius:12px; }
  .dp-grid { grid-template-columns:1fr; }
  .dc { min-height:0; }
  .dc-foot { gap:.5rem; }
  .dashboard-source-modal .modal-dialog { margin:.65rem; }
}
</style>

<div class="db-shell pt-2">
  {{-- HEADER --}}
  <div class="db-header">
    <div class="db-brand">
      <div class="db-logo">
        <img src="{{ asset('images/a-six-logo.svg') }}" alt="A-SIX">
      </div>
      <div>
        <div class="db-title">A-SIX Area 6 — Dashboard Realtime</div>
        <div class="db-subtitle">Ringkasan posisi keuangan, portofolio, dan 8 strategi digital</div>
      </div>
    </div>
    <div class="db-meta">
      <span class="db-meta-chip"><i class="fas fa-circle" style="color:#4ade80;font-size:.5rem;"></i> Live Snapshot</span>
      @if($digitalUpdatedAt)
      <span class="db-now">Updated: {{ $digitalUpdatedAt }} WIB</span>
      @endif
      <span class="db-now" id="db-clock"></span>
    </div>
  </div>

  {{-- KPI STRIP: Simpanan | Pinjaman | Portfolio | Growth Simp | Growth Pinj | Coverage --}}
  <div class="kpi-strip">
    {{-- SIMPANAN --}}
    <div class="kpi-card simpanan">
      <div class="kc-live"></div>
      <div class="kc-label"><i class="fas fa-piggy-bank mr-1"></i>Dana Simpanan</div>
      <div class="kc-val">{{ data_get($simpananReport,'value','–') }}</div>
      <div class="kc-sub">{{ data_get($simpananReport,'meta','–') }}</div>
      @php $sm = (float)str_replace(['+','%',','],['','','.'],data_get($simpananReport,'trend','0')); @endphp
      <span class="kc-delta {{ $sm>=0?'pos':'neg' }}">
        <i class="fas {{ $sm>=0?'fa-arrow-up':'fa-arrow-down' }}"></i>
        {{ data_get($simpananReport,'trend','0%') }} MoM
      </span>
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($simpananReport,"detail_payload",[]))' data-link="{{ data_get($simpananReport,'link','#') }}" data-link-label="{{ data_get($simpananReport,'link_label','Buka report') }}">Detail <i class="fas fa-table"></i></button>
    </div>

    {{-- PINJAMAN --}}
    <div class="kpi-card pinjaman">
      <div class="kc-live"></div>
      <div class="kc-label"><i class="fas fa-hand-holding-usd mr-1"></i>Kredit Pinjaman</div>
      <div class="kc-val">{{ data_get($pinjamanReport,'value','–') }}</div>
      <div class="kc-sub">{{ data_get($pinjamanReport,'meta','–') }}</div>
      @php $pm = (float)str_replace(['+','%',','],['','','.'],data_get($pinjamanReport,'trend','0')); @endphp
      <span class="kc-delta {{ $pm>=0?'pos':'neg' }}">
        <i class="fas {{ $pm>=0?'fa-arrow-up':'fa-arrow-down' }}"></i>
        {{ data_get($pinjamanReport,'trend','0%') }} MoM
      </span>
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($pinjamanReport,"detail_payload",[]))' data-link="{{ data_get($pinjamanReport,'link','#') }}" data-link-label="{{ data_get($pinjamanReport,'link_label','Buka report') }}">Detail <i class="fas fa-table"></i></button>
    </div>

    {{-- PORTFOLIO --}}
    <div class="kpi-card portfolio">
      <div class="kc-label"><i class="fas fa-layer-group mr-1"></i>LDR (Loan to Deposit Ratio)</div>
      <div class="kc-val">{{ data_get($portfolioReport,'value','–') }}</div>
      <div class="kc-sub" style="max-width:150px;white-space:normal;font-size:.58rem;">{{ data_get($portfolioReport,'meta','–') }}</div>
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($portfolioReport,"detail_payload",[]))' data-link="{{ data_get($portfolioReport,'link','#') }}" data-link-label="{{ data_get($portfolioReport,'link_label','Lihat report') }}">Detail <i class="fas fa-table"></i></button>
    </div>

    {{-- 3 METRIC CARDS --}}
    @foreach(array_slice($metrics,0,3) as $m)
    <div class="kpi-card">
      <div class="kc-label">{{ data_get($m,'label','–') }}</div>
      <div class="kc-val" style="font-size:1.05rem;">{{ data_get($m,'value','–') }}</div>
      <div class="kc-sub {{ data_get($m,'delta_class','text-muted') }}">{{ data_get($m,'delta','–') }}</div>
    </div>
    @endforeach
  </div>

  {{-- AREA 6 PORTFOLIO SUMMARY --}}
  <section class="area6-panel">
    <div class="area6-head">
      <div>
        <div class="area6-title">{{ data_get($area6Portfolio, 'title', 'Ringkasan Area 6') }}</div>
        <div class="area6-sub">{{ data_get($area6Portfolio, 'subtitle', 'Ringkasan lintas report Area 6.') }}</div>
      </div>
      <div class="area6-head-actions">
        @if(!empty($area6RankingModes))
        <div class="area6-scope-toggle" role="group" aria-label="Pilihan level ranking Area 6">
          @foreach($area6RankingModes as $scopeKey => $scopePayload)
          <button type="button"
                  class="area6-scope-btn {{ $scopeKey === $area6DefaultScope ? 'active' : '' }}"
                  data-area6-scope="{{ $scopeKey }}">
            {{ data_get($scopePayload, 'label', strtoupper($scopeKey)) }}
          </button>
          @endforeach
        </div>
        @endif
        <div class="area6-periods">
          <span class="area6-pill"><i class="fas fa-calendar-day"></i> Harian: {{ data_get($area6Portfolio, 'period_label', 'Belum ada data') }}</span>
          <span class="area6-pill"><i class="fas fa-chart-line"></i> Pinjaman: {{ data_get($area6Portfolio, 'loan_period_label', 'Belum ada data') }}</span>
          <span class="area6-pill"><i class="fas fa-database"></i> Detail: {{ data_get($area6Portfolio, 'loan_detail_period_label', data_get($area6Portfolio, 'loan_period_label', 'Belum ada data')) }}</span>
        </div>
      </div>
    </div>

    <div class="area6-card-grid">
      @forelse($area6Cards as $card)
      <button type="button"
              class="area6-card tone-{{ data_get($card, 'tone', 'blue') }} dashboard-detail-trigger"
              data-detail='@json(data_get($card, "detail_payload", []))'
              data-link="{{ data_get($card, 'link', '#') }}"
              data-link-label="{{ data_get($card, 'link_label', 'Lihat detail') }}">
        <div class="ac-icon"><i class="{{ data_get($card, 'icon', 'fas fa-chart-bar') }}"></i></div>
        <div class="ac-label">{{ data_get($card, 'label', '-') }}</div>
        <div class="ac-value">{{ data_get($card, 'value', '-') }}</div>
        <div class="ac-meta">{{ data_get($card, 'meta', '-') }}</div>
      </button>
      @empty
      <div class="rank-empty">Ringkasan Area 6 belum tersedia.</div>
      @endforelse
    </div>

    @if(!empty($area6RankingModes))
      @foreach($area6RankingModes as $scopeKey => $scopePayload)
      <div class="area6-ranking-grid area6-ranking-mode {{ $scopeKey === $area6DefaultScope ? '' : 'd-none' }}" data-area6-ranking-scope="{{ $scopeKey }}">
        @forelse(data_get($scopePayload, 'rankings', []) as $group)
        <div class="rank-card tone-{{ data_get($group, 'tone', 'blue') }}">
          <div class="rank-card-head">
            <div class="rank-card-title">
              <span>{{ data_get($group, 'title', 'Ranking') }}</span>
              <i class="rank-badge"></i>
            </div>
            <div class="rank-card-hint">{{ data_get($group, 'hint', 'Area 6') }}</div>
          </div>
          <div class="rank-list">
            @forelse(data_get($group, 'rows', []) as $row)
            <div class="rank-row">
              <div class="rank-no">{{ data_get($row, 'rank', $loop->iteration) }}</div>
              <div class="rank-main">
                <div class="rank-name" title="{{ data_get($row, 'label', '-') }}">{{ data_get($row, 'label', '-') }}</div>
                <div class="rank-meta" title="{{ data_get($row, 'meta', '-') }}">{{ data_get($row, 'meta', '-') }}</div>
              </div>
              <div class="rank-val">
                {{ data_get($row, 'value', '-') }}
                @if(data_get($row, 'sub'))
                <div class="rank-sub">{{ data_get($row, 'sub') }}</div>
                @endif
              </div>
            </div>
            @empty
            <div class="rank-empty">Data ranking belum tersedia.</div>
            @endforelse
          </div>
        </div>
        @empty
        <div class="rank-empty">Ranking {{ data_get($scopePayload, 'label', strtoupper($scopeKey)) }} belum tersedia.</div>
        @endforelse
      </div>
      @endforeach
    @else
      <div class="area6-ranking-grid">
      @forelse($area6Rankings as $group)
      <div class="rank-card tone-{{ data_get($group, 'tone', 'blue') }}">
        <div class="rank-card-head">
          <div class="rank-card-title">
            <span>{{ data_get($group, 'title', 'Ranking') }}</span>
            <i class="rank-badge"></i>
          </div>
          <div class="rank-card-hint">{{ data_get($group, 'hint', 'Area 6') }}</div>
        </div>
        <div class="rank-list">
          @forelse(data_get($group, 'rows', []) as $row)
          <div class="rank-row">
            <div class="rank-no">{{ data_get($row, 'rank', $loop->iteration) }}</div>
            <div class="rank-main">
              <div class="rank-name" title="{{ data_get($row, 'label', '-') }}">{{ data_get($row, 'label', '-') }}</div>
              <div class="rank-meta" title="{{ data_get($row, 'meta', '-') }}">{{ data_get($row, 'meta', '-') }}</div>
            </div>
            <div class="rank-val">
              {{ data_get($row, 'value', '-') }}
              @if(data_get($row, 'sub'))
              <div class="rank-sub">{{ data_get($row, 'sub') }}</div>
              @endif
            </div>
          </div>
          @empty
          <div class="rank-empty">Belum ada data ranking.</div>
          @endforelse
        </div>
      </div>
      @empty
      <div class="rank-empty">Ranking Area 6 belum tersedia.</div>
      @endforelse
      </div>
    @endif
  </section>

  {{-- MAIN GRID: Chart + Digital --}}
  <div class="main-grid">
    {{-- TIMESERIES CHART --}}
    <div class="chart-panel">
      <div class="cp-header">
        <div>
          <div class="cp-title">Tren Posisi 6 Periode</div>
          <div style="font-size:.58rem;color:#94a3b8;margin-top:.1rem;">Simpanan vs Pinjaman (dalam Rp Triliun)</div>
        </div>
        <div class="cp-legend">
          <div class="cp-leg-item"><div class="cp-leg-dot" style="background:#3b82f6;"></div>Simpanan</div>
          <div class="cp-leg-item"><div class="cp-leg-dot" style="background:#ef4444;"></div>Pinjaman</div>
        </div>
      </div>
      <canvas id="timeseriesChart"></canvas>
      <div class="chart-empty" id="timeseriesChartEmpty">Grafik belum tersedia.</div>
    </div>

    {{-- 8 DIGITAL CARDS --}}
    <div class="digital-panel">
      <div class="dp-header">
        <div class="dp-title"><i class="fas fa-bolt mr-1" style="color:#f59e0b;"></i>8 Fokus Kinerja Digital Area 6</div>
        @if($digitalUpdatedAt)
        <div class="dp-updated"><i class="fas fa-sync-alt mr-1"></i>{{ $digitalUpdatedAt }} WIB</div>
        @endif
      </div>
      <div class="dp-grid">
        @php
        $toneMap = [
          'edc'=>'dc-edc','qris'=>'dc-qris','qlola'=>'dc-qlola','brimo'=>'dc-brimo',
          'brilink'=>'dc-brilink','casa'=>'dc-casa','dormant'=>'dc-dormant','payroll'=>'dc-payroll',
        ];
        $iconMap = [
          'edc'=>'fa-credit-card','qris'=>'fa-qrcode','qlola'=>'fa-university','brimo'=>'fa-mobile-alt',
          'brilink'=>'fa-network-wired','casa'=>'fa-percentage','dormant'=>'fa-bed','payroll'=>'fa-briefcase',
        ];
        @endphp
        @forelse($digitalCards as $dc)
        @php
          $key = data_get($dc,'key','edc');
          $tone = $toneMap[$key] ?? 'dc-edc';
          $icon = $iconMap[$key] ?? 'fa-chart-bar';
          $isStub = data_get($dc,'is_stub',false);
          $tv = (float)data_get($dc,'trend_value',0);
        @endphp
        <button type="button" class="dc dashboard-detail-trigger {{ $tone }} {{ $isStub?'dc-stub':'' }}" data-detail='@json(data_get($dc,"detail_payload",[]))' data-link="{{ data_get($dc,'link','#') }}" data-link-label="{{ data_get($dc,'link_label','Buka report') }}">
          <div class="dc-badge"><i class="fas {{ $icon }}"></i> {{ data_get($dc,'badge','–') }}</div>
          <div class="dc-label">{{ data_get($dc,'current_label','–') }}</div>
          <div class="dc-val">{{ data_get($dc,'current_value','–') }}</div>
          <div class="dc-sub">{{ data_get($dc,'secondary_label','–') }}: {{ data_get($dc,'secondary_value','–') }}</div>
          <div class="dc-stats">
            @foreach(array_slice(data_get($dc,'stats',[]),0,3) as $st)
            <div class="dc-stat">
              <div class="dc-stat-lbl">{{ data_get($st,'label','–') }}</div>
              <div class="dc-stat-val">{{ data_get($st,'value','–') }}</div>
            </div>
            @endforeach
          </div>
          <div class="dc-foot">
            <span class="dc-trend">
              <i class="fas {{ $tv>=0?'fa-arrow-up':'fa-arrow-down' }}"></i>
              {{ data_get($dc,'trend','0%') }}
            </span>
            <span class="dc-link">{{ data_get($dc,'link_label','Detail') }} <i class="fas fa-arrow-right"></i></span>
          </div>
        </button>
        @empty
        <div class="col-12 text-muted small">Data performance digital belum tersedia.</div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="modal fade dashboard-source-modal" id="dashboardSourceModal" tabindex="-1" role="dialog" aria-labelledby="dashboardSourceModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0" id="dashboardSourceModalTitle">Detail sumber data</h5>
          </div>
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="source-modal-meta">
            <div class="source-modal-chip"><span>Periode</span><strong id="sourceModalPeriod">-</strong></div>
            <div class="source-modal-chip"><span>Tabel sumber</span><strong id="sourceModalTable">-</strong></div>
            <div class="source-modal-chip"><span>Report</span><strong id="sourceModalReport">-</strong></div>
          </div>
          <div class="source-modal-note" id="sourceModalNote">-</div>
          <div class="table-responsive">
            <table class="table table-sm source-modal-table mb-0">
              <thead>
                <tr>
                  <th>Metrik</th>
                  <th>Nilai tampil</th>
                  <th>Sumber</th>
                </tr>
              </thead>
              <tbody id="sourceModalRows">
                <tr><td colspan="3" class="text-muted">Detail belum tersedia.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <a href="#" class="btn btn-sm btn-primary" id="sourceModalLink">Buka report</a>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Clock
  const clock = document.getElementById('db-clock');
  if (clock) {
    const tick = () => { clock.textContent = new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit',second:'2-digit'})+' WIB'; };
    tick(); setInterval(tick, 1000);
  }

  // Timeseries Chart
  const labels = @json(data_get($timeseries,'labels',[]));
  const normalizeChartValue = value => {
    if (typeof value === 'number') {
      return Number.isFinite(value) ? value : 0;
    }

    const normalized = String(value ?? '0')
      .replace(/\s/g, '')
      .replace(',', '.');

    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
  };
  const simp = @json(data_get($timeseries,'simpanan',[])).map(normalizeChartValue);
  const pinj = @json(data_get($timeseries,'pinjaman',[])).map(normalizeChartValue);

  const ctx = document.getElementById('timeseriesChart');
  const chartPanel = ctx ? ctx.closest('.chart-panel') : null;
  const hasChartData = labels.length && (simp.some(value => value > 0) || pinj.some(value => value > 0));
  const markChartEmpty = () => {
    if (chartPanel) {
      chartPanel.classList.add('is-empty');
    }
  };
  const renderTimeseriesChart = () => {
    if (!ctx || !window.Chart || !hasChartData) {
      markChartEmpty();
      return;
    }

    try {
      const chart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
          labels: labels,
          datasets: [
            {
              label: 'Simpanan (Rp T)',
              data: simp,
              borderColor: '#3b82f6',
              backgroundColor: 'rgba(59,130,246,.12)',
              borderWidth: 2.5,
              pointRadius: 4,
              pointHoverRadius: 6,
              pointBackgroundColor: '#fff',
              pointBorderColor: '#3b82f6',
              pointBorderWidth: 2,
              fill: true,
              tension: .38,
              yAxisID: 'y',
            },
            {
              label: 'Pinjaman (Rp T)',
              data: pinj,
              borderColor: '#ef4444',
              backgroundColor: 'rgba(239,68,68,.08)',
              borderWidth: 2.5,
              pointRadius: 4,
              pointHoverRadius: 6,
              pointBackgroundColor: '#fff',
              pointBorderColor: '#ef4444',
              pointBorderWidth: 2,
              fill: true,
              tension: .38,
              yAxisID: 'y2',
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode:'index', intersect:false },
          plugins: {
            legend: { display:false },
            tooltip: {
              backgroundColor:'rgba(15,23,42,.92)',
              titleFont:{ size:11, weight:'700' },
              bodyFont:{ size:10.5 },
              padding:10,
              cornerRadius:10,
              callbacks: {
                label: item => {
                  const value = item.parsed.y;
                  return ' ' + item.dataset.label + ': Rp' + (value ? value.toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:3}) : '0') + ' T';
                }
              }
            }
          },
          scales: {
            x: {
              grid:{ color:'rgba(148,163,184,.12)' },
              ticks:{ font:{size:10,weight:'600'}, color:'#64748b' }
            },
            y: {
              position:'left',
              grid:{ color:'rgba(148,163,184,.12)' },
              ticks:{ font:{size:10}, color:'#3b82f6', callback: value => 'Rp'+Number(value).toFixed(1)+'T' }
            },
            y2: {
              position:'right',
              grid:{ drawOnChartArea:false },
              ticks:{ font:{size:10}, color:'#ef4444', callback: value => 'Rp'+Number(value).toFixed(1)+'T' }
            }
          }
        }
      });

      chartPanel?.classList.remove('is-empty');
      window.setTimeout(() => chart.resize(), 120);
    } catch (error) {
      markChartEmpty();
    }
  };

  window.requestAnimationFrame(renderTimeseriesChart);

  const sourceModal = document.getElementById('dashboardSourceModal');
  if (sourceModal && sourceModal.parentElement !== document.body) {
    document.body.appendChild(sourceModal);
  }

  const clearSourceModalState = () => {
    document.querySelectorAll('.modal-backdrop.dashboard-source-backdrop').forEach(backdrop => backdrop.remove());
    if (!document.querySelector('.modal.show')) {
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');
    }
  };

  if (window.jQuery && sourceModal && typeof window.jQuery.fn.modal === 'function') {
    const $sourceModal = window.jQuery(sourceModal);
    $sourceModal.on('shown.bs.modal', function () {
      window.jQuery('.modal-backdrop').last().addClass('dashboard-source-backdrop');
    });
    $sourceModal.on('hidden.bs.modal', clearSourceModalState);
    window.jQuery(window).on('pagehide', clearSourceModalState);
  }

  document.querySelectorAll('.area6-scope-btn').forEach(button => {
    button.addEventListener('click', function() {
      const scope = this.getAttribute('data-area6-scope');

      document.querySelectorAll('.area6-scope-btn').forEach(item => {
        item.classList.toggle('active', item === this);
      });

      document.querySelectorAll('.area6-ranking-mode').forEach(panel => {
        panel.classList.toggle('d-none', panel.getAttribute('data-area6-ranking-scope') !== scope);
      });
    });
  });

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[char]));

  document.querySelectorAll('.dashboard-detail-trigger').forEach(trigger => {
    trigger.addEventListener('click', function(event) {
      event.preventDefault();

      let detail = {};
      try {
        detail = JSON.parse(this.getAttribute('data-detail') || '{}') || {};
      } catch (error) {
        detail = {};
      }

      const title = detail.title || 'Detail sumber data';
      const rows = Array.isArray(detail.rows) ? detail.rows : [];
      document.getElementById('dashboardSourceModalTitle').textContent = title;
      document.getElementById('sourceModalReport').textContent = title;
      document.getElementById('sourceModalPeriod').textContent = detail.period || '-';
      document.getElementById('sourceModalTable').textContent = detail.source_table || '-';
      document.getElementById('sourceModalNote').textContent = detail.note || 'Detail sumber belum tersedia.';

      const tbody = document.getElementById('sourceModalRows');
      tbody.innerHTML = rows.length
        ? rows.map(row => `
            <tr>
              <td>${escapeHtml(row.label || '-')}</td>
              <td class="font-weight-bold">${escapeHtml(row.value || '-')}</td>
              <td><code>${escapeHtml(row.source || detail.source_table || '-')}</code></td>
            </tr>
          `).join('')
        : '<tr><td colspan="3" class="text-muted">Detail belum tersedia.</td></tr>';

      const link = document.getElementById('sourceModalLink');
      link.href = this.getAttribute('data-link') || '#';
      link.textContent = this.getAttribute('data-link-label') || 'Buka report';

      if (window.jQuery && sourceModal && typeof window.jQuery.fn.modal === 'function') {
        clearSourceModalState();
        window.jQuery(sourceModal).modal({
          backdrop: true,
          keyboard: true,
          focus: true,
          show: true,
        });
      }
    });
  });
});
</script>
@endsection
