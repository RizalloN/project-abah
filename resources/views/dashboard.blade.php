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
  --c-blue: #0857c3;
  --c-blue-d: #053b82;
  --c-blue-l: #307fe2;
  --c-teal: #0f766e;
  --c-red: #dc2626;
  --c-amber: #d97706;
  --c-green: #059669;
  --c-purple: #7c3aed;
  --c-pink: #db2777;
  --c-surf: #f8fafc;
  --c-border: #e2e8f0;
  --shadow-sm: 0 1px 3px rgba(15,23,42,0.03), 0 1px 2px rgba(15,23,42,0.02);
  --shadow-md: 0 4px 12px -2px rgba(15,23,42,0.06), 0 2px 6px -1px rgba(15,23,42,0.03);
  --shadow-lg: 0 12px 24px -4px rgba(15,23,42,0.08), 0 4px 12px -2px rgba(15,23,42,0.04);
  --r-sm: 6px;
  --r-md: 10px;
  --r-lg: 14px;
  --r-xl: 18px;
}

.db-shell {
  font-family: 'Inter', sans-serif;
  padding: 0 0 1rem;
  color: #0f172a;
}

/* ── HEADER ── */
.db-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  margin-bottom: 1.25rem;
  background: #ffffff;
  border: 1px solid var(--c-border);
  border-top: 3px solid var(--c-blue);
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-sm);
}
.db-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.db-logo {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, var(--c-blue), var(--c-blue-l));
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--r-md);
}
.db-logo img {
  width: 26px;
  height: 26px;
  object-fit: contain;
}
.db-title {
  font-size: 0.95rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.01em;
}
.db-subtitle {
  font-size: 0.65rem;
  color: #64748b;
  margin-top: 0.05rem;
}
.db-meta {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.db-meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.3rem 0.8rem;
  background: #f1f6ff;
  border: 1px solid rgba(8, 87, 195, 0.15);
  font-size: 0.62rem;
  font-weight: 700;
  color: var(--c-blue);
  border-radius: 30px;
}
.db-now {
  font-size: 0.62rem;
  color: #64748b;
  font-weight: 500;
}

/* ── KPI STRIP ── */
.kpi-strip {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0.8rem;
  margin-bottom: 0.8rem;
}
.kpi-card {
  padding: 1.1rem 1.25rem 1rem;
  min-height: 108px;
  position: relative;
  overflow: hidden;
  border: 1px solid var(--c-border);
  background: #ffffff;
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-sm);
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
  border-left: 4px solid #cbd5e1 !important;
}
.kpi-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
  border-color: #cbd5e1;
}

.kpi-card .kc-label {
  font-size: 0.62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
  margin-bottom: 0.3rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}
.kpi-card .kc-val {
  font-size: 1.32rem;
  font-weight: 800;
  line-height: 1.1;
  color: #0f172a;
}
.kpi-card .kc-sub {
  font-size: 0.62rem;
  color: #64748b;
  margin-top: 0.25rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.kpi-card .kc-delta {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.62rem;
  font-weight: 700;
  padding: 0.2rem 0.55rem;
  margin-top: 0.4rem;
  border-radius: var(--r-sm);
}

/* KPI Card Accents */
.kpi-card.simpanan {
  border-left: 4px solid var(--c-blue) !important;
}
.kpi-card.simpanan .kc-label {
  color: var(--c-blue);
}
.kpi-card.simpanan .kc-val {
  color: #0f172a;
  font-size: 1.5rem;
}
.kpi-card.simpanan .kc-sub {
  color: #475569;
}

.kpi-card.pinjaman {
  border-left: 4px solid #1177a3 !important;
}
.kpi-card.pinjaman .kc-label {
  color: #1177a3;
}
.kpi-card.pinjaman .kc-val {
  color: #0f172a;
  font-size: 1.5rem;
}
.kpi-card.pinjaman .kc-sub {
  color: #475569;
}

.kpi-card.portfolio {
  border-left: 4px solid var(--c-teal) !important;
}
.kpi-card.portfolio .kc-label {
  color: var(--c-teal);
}
.kpi-card.portfolio .kc-val {
  color: #0f172a;
  font-size: 1.5rem;
}
.kpi-card.portfolio .kc-sub {
  color: #475569;
}

.kc-live {
  position: absolute;
  top: 0.7rem;
  right: 0.8rem;
  width: 7px;
  height: 7px;
  background: #22c55e;
  border-radius: 50%;
  animation: pulse-live 1.8s infinite;
}
@keyframes pulse-live {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.kpi-card .kc-link {
  position: absolute;
  bottom: 0.65rem;
  right: 0.8rem;
  font-size: 0.62rem;
  font-weight: 700;
  color: var(--c-blue);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  border: 0;
  background: transparent;
  padding: 0;
  cursor: pointer;
  transition: color 0.15s ease;
}
.kpi-card .kc-link:hover {
  color: var(--c-blue-d);
  text-decoration: underline;
}

/* ── AREA 6 PORTFOLIO PANEL ── */
.area6-panel {
  margin: 1.25rem 0;
  background: #ffffff;
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-md);
  border-radius: var(--r-xl);
  overflow: hidden;
}
.area6-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.1rem;
  padding: 1.1rem 1.5rem;
  background: #f8fafc;
  border-bottom: 1px solid var(--c-border);
}
.area6-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: #0f172a;
}
.area6-sub {
  margin-top: 0.15rem;
  font-size: 0.72rem;
  color: #64748b;
}
.area6-head-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.5rem;
}
.area6-periods {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.38rem;
}
.area6-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.34rem;
  padding: 0.35rem 0.75rem;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: var(--c-blue);
  font-size: 0.65rem;
  font-weight: 700;
  white-space: nowrap;
  border-radius: 30px;
}
.area6-scope-toggle {
  display: inline-flex;
  gap: 0.25rem;
  padding: 0.25rem;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  border-radius: 30px;
}
.area6-scope-btn {
  border: 0;
  padding: 0.38rem 0.85rem;
  background: transparent;
  color: #475569;
  font-size: 0.68rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  border-radius: 30px;
}
.area6-scope-btn.active {
  background: #ffffff;
  color: var(--c-blue);
  box-shadow: var(--shadow-sm);
  border: 1px solid #cbd5e1;
}

.area6-card-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.8rem;
  padding: 1.25rem;
}
.area6-card {
  border: 1px solid var(--c-border);
  appearance: none;
  width: 100%;
  min-height: 140px;
  padding: 1.1rem 1rem;
  text-align: left;
  background: #ffffff;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  cursor: pointer;
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
  border-radius: var(--r-lg);
}
.area6-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
  border-color: #cbd5e1;
}
.area6-card .ac-icon {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f1f5f9;
  margin-bottom: 0.6rem;
  font-size: 0.9rem;
  color: #475569;
  border-radius: var(--r-md);
}
.area6-card .ac-label {
  font-size: 0.7rem;
  color: #475569;
  font-weight: 600;
  line-height: 1.25;
  min-height: 1.55rem;
}
.area6-card .ac-value {
  font-size: 1.35rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
  margin-top: 0.2rem;
  word-break: break-word;
}
.area6-card .ac-meta {
  margin-top: auto;
  padding-top: 0.5rem;
  font-size: 0.65rem;
  color: #64748b;
  line-height: 1.3;
}

/* Area 6 Tone Styling */
.area6-card.tone-blue { border-left: 4px solid var(--c-blue) !important; }
.area6-card.tone-blue .ac-icon { background: #eff6ff; color: var(--c-blue); }
.area6-card.tone-red { border-left: 4px solid var(--c-red) !important; }
.area6-card.tone-red .ac-icon { background: #fef2f2; color: var(--c-red); }
.area6-card.tone-green { border-left: 4px solid var(--c-green) !important; }
.area6-card.tone-green .ac-icon { background: #f0fdf4; color: var(--c-green); }
.area6-card.tone-amber { border-left: 4px solid var(--c-amber) !important; }
.area6-card.tone-amber .ac-icon { background: #fffbeb; color: var(--c-amber); }
.area6-card.tone-purple { border-left: 4px solid var(--c-purple) !important; }
.area6-card.tone-purple .ac-icon { background: #f5f3ff; color: var(--c-purple); }
.area6-card.tone-orange { border-left: 4px solid #f97316 !important; }
.area6-card.tone-orange .ac-icon { background: #fff7ed; color: #f97316; }
.area6-card.tone-teal { border-left: 4px solid var(--c-teal) !important; }
.area6-card.tone-teal .ac-icon { background: #f0fdfa; color: var(--c-teal); }

/* ── RANKINGS ── */
.area6-ranking-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.72rem;
  padding: 0 1.25rem 1.25rem;
}
.rank-card {
  border: 1px solid var(--c-border);
  background: #ffffff;
  overflow: hidden;
  min-width: 0;
  box-shadow: var(--shadow-sm);
  border-radius: var(--r-lg);
}
.rank-card-head {
  padding: 0.92rem 1.1rem 0.72rem;
  border-bottom: 1px solid #f1f5f9;
  background: #f8fafc;
}
.rank-card-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  font-size: 0.8rem;
  font-weight: 800;
  color: #0f172a;
}
.rank-card-title span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rank-card-hint {
  font-size: 0.62rem;
  color: #64748b;
  margin-top: 0.14rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rank-badge {
  flex: 0 0 auto;
  width: 8px;
  height: 8px;
  background: var(--c-blue);
  border-radius: 50%;
}
.rank-card.tone-red .rank-badge { background: var(--c-red); }
.rank-card.tone-amber .rank-badge { background: var(--c-amber); }
.rank-card.tone-orange .rank-badge { background: #f97316; }
.rank-card.tone-teal .rank-badge { background: var(--c-teal); }
.rank-card.tone-slate .rank-badge { background: #64748b; }

.rank-list {
  padding: 0.6rem 0.8rem 0.8rem;
}
.rank-row {
  display: grid;
  grid-template-columns: 26px minmax(0, 1fr) auto;
  gap: 0.5rem;
  align-items: center;
  padding: 0.55rem 0.26rem;
  border-bottom: 1px solid #f1f5f9;
}
.rank-row:last-child {
  border-bottom: 0;
}
.rank-no {
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f1f5f9;
  color: #475569;
  font-size: 0.65rem;
  font-weight: 800;
  border-radius: var(--r-sm);
}
.rank-main {
  min-width: 0;
}
.rank-name {
  font-size: 0.72rem;
  font-weight: 700;
  color: #0f172a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rank-meta {
  font-size: 0.6rem;
  color: #64748b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-top: 0.05rem;
}
.rank-val {
  text-align: right;
  font-size: 0.72rem;
  font-weight: 800;
  color: #0f172a;
  white-space: nowrap;
}
.rank-sub {
  font-size: 0.58rem;
  color: #64748b;
  margin-top: 0.04rem;
}
.rank-empty {
  padding: 1.5rem 1rem;
  font-size: 0.65rem;
  color: #94a3b8;
  text-align: center;
}

/* ── MAIN GRID ── */
.main-grid {
  display: grid;
  grid-template-columns: minmax(280px, 0.95fr) minmax(0, 2.05fr);
  gap: 1rem;
  align-items: start;
}

/* ── CHART PANEL ── */
.chart-panel {
  background: #ffffff;
  border: 1px solid var(--c-border);
  padding: 1.25rem;
  min-height: 380px;
  position: relative;
  box-shadow: var(--shadow-md);
  border-radius: var(--r-xl);
}
.chart-panel .cp-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.8rem;
}
.chart-panel .cp-title {
  font-size: 0.85rem;
  font-weight: 800;
  color: #0f172a;
}
.chart-panel .cp-legend {
  display: flex;
  gap: 0.85rem;
}
.chart-panel .cp-leg-item {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.62rem;
  font-weight: 700;
  color: #475569;
}
.chart-panel .cp-leg-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.chart-panel canvas {
  width: 100% !important;
  height: 300px !important;
}
.chart-empty {
  display: none;
  position: absolute;
  left: 1rem;
  right: 1rem;
  top: 5.2rem;
  bottom: 1rem;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: #64748b;
  border: 1px dashed #cbd5e1;
  background: #f8fafc;
  font-size: 0.72rem;
  font-weight: 700;
  border-radius: var(--r-lg);
}
.chart-panel.is-empty .chart-empty {
  display: flex;
}
.chart-panel.is-empty canvas {
  opacity: 0;
}

/* ── DIGITAL PANEL ── */
.digital-panel {
  background: #ffffff;
  border: 1px solid var(--c-border);
  padding: 1.25rem;
  box-shadow: var(--shadow-md);
  border-radius: var(--r-xl);
}
.dp-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--c-border);
  padding-bottom: 0.6rem;
}
.dp-title {
  font-size: 0.85rem;
  font-weight: 800;
  color: #0f172a;
}
.dp-updated {
  font-size: 0.62rem;
  color: #64748b;
}
.dp-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.8rem;
}

/* Digital Card Component */
.dc {
  padding: 1.1rem 1rem;
  color: #0f172a;
  position: relative;
  overflow: hidden;
  cursor: pointer;
  text-decoration: none;
  display: flex;
  flex-direction: column;
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
  border: 1px solid var(--c-border) !important;
  background: #ffffff !important;
  text-align: left;
  width: 100%;
  min-height: 192px;
  font: inherit;
  appearance: none;
  border-radius: var(--r-lg);
}
.dc:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
  color: #0f172a;
  border-color: #cbd5e1 !important;
}

.dc-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  padding: 0.22rem 0.6rem;
  font-size: 0.58rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 0.5rem;
  width: fit-content;
  border: 1px solid rgba(0,0,0,0.06);
  border-radius: var(--r-sm);
}

.dc-val {
  font-size: 1.35rem;
  font-weight: 800;
  line-height: 1.1;
  color: #0f172a;
}
.dc-label {
  font-size: 0.68rem;
  color: #475569;
  font-weight: 600;
  margin-bottom: 0.2rem;
}
.dc-sub {
  font-size: 0.65rem;
  color: #64748b;
}

.dc-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.25rem;
  margin-top: 0.6rem;
}
.dc-stat {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 0.35rem 0.4rem;
  border-radius: var(--r-sm);
}
.dc-stat-lbl {
  font-size: 0.55rem;
  color: #64748b;
  font-weight: 500;
}
.dc-stat-val {
  font-size: 0.72rem;
  font-weight: 700;
  color: #0f172a;
}

.dc-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 0.6rem;
}
.dc-trend {
  display: inline-flex;
  align-items: center;
  gap: 0.22rem;
  font-size: 0.58rem;
  font-weight: 700;
  padding: 0.2rem 0.55rem;
  margin-top: auto;
  margin-bottom: 0.1rem;
  background: #f1f5f9;
  color: #475569;
  border-radius: var(--r-sm);
}
.dc-trend .fa-arrow-up { color: #16a34a; }
.dc-trend .fa-arrow-down { color: #dc2626; }

.dc-link {
  font-size: 0.6rem;
  font-weight: 700;
  color: var(--c-blue);
  display: inline-flex;
  align-items: center;
  gap: 0.22rem;
}
.dc-link:hover {
  color: var(--c-blue-d);
  text-decoration: underline;
}

/* Digital Accent Styling */
.dc-edc { border-left: 4px solid var(--c-blue) !important; }
.dc-edc .dc-badge { background: #eff6ff; color: var(--c-blue); border-color: rgba(8, 87, 195, 0.15); }
.dc-qris { border-left: 4px solid #12a5c3 !important; }
.dc-qris .dc-badge { background: #ecfeff; color: #0891b2; border-color: rgba(8, 145, 178, 0.15); }
.dc-qlola { border-left: 4px solid var(--c-purple) !important; }
.dc-qlola .dc-badge { background: #f5f3ff; color: var(--c-purple); border-color: rgba(124, 58, 237, 0.15); }
.dc-brimo { border-left: 4px solid #3b82f6 !important; }
.dc-brimo .dc-badge { background: #eff6ff; color: #2563eb; border-color: rgba(37, 99, 237, 0.15); }
.dc-brilink { border-left: 4px solid var(--c-green) !important; }
.dc-brilink .dc-badge { background: #f0fdf4; color: #16a34a; border-color: rgba(22, 163, 74, 0.15); }
.dc-casa { border-left: 4px solid var(--c-amber) !important; }
.dc-casa .dc-badge { background: #fffbeb; color: #d97706; border-color: rgba(217, 119, 6, 0.15); }
.dc-dormant { border-left: 4px solid var(--c-red) !important; }
.dc-dormant .dc-badge { background: #fef2f2; color: #dc2626; border-color: rgba(220, 38, 38, 0.15); }
.dc-payroll { border-left: 4px solid #6b7280 !important; }
.dc-payroll .dc-badge { background: #f9fafb; color: #4b5563; border-color: rgba(75, 85, 99, 0.15); }

.dc-stub { opacity: 0.7; filter: grayscale(0.3); }

/* Delta Statuses */
.pos { color: #16a34a; background: #f0fdf4; }
.neg { color: #dc2626; background: #fef2f2; }
.neu { color: #475569; background: #f1f5f9; }

/* ── MODALS ── */
.dashboard-source-modal { z-index: 2070; }
.modal-backdrop.dashboard-source-backdrop { z-index: 2060; background: #0f172a; }
.modal-backdrop.dashboard-source-backdrop.show { opacity: 0.4; }
.dashboard-source-modal .modal-content { border: 1px solid var(--c-border); box-shadow: var(--shadow-lg); border-radius: var(--r-xl); }
.dashboard-source-modal .modal-header,
.dashboard-source-modal .modal-footer { border-color: #e2e8f0; background: #f8fafc; }
.dashboard-source-modal .modal-title { font-size: 1rem; font-weight: 800; color: #0f172a; }
.dashboard-source-modal .btn { font-weight: 700; font-size: 0.72rem; }

.source-modal-meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.6rem; margin-bottom: 0.8rem; }
.source-modal-chip { border: 1px solid var(--c-border); padding: 0.6rem 0.75rem; background: #f8fafc; border-radius: var(--r-md); }
.source-modal-chip span { display: block; font-size: 0.58rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
.source-modal-chip strong { display: block; margin-top: 0.15rem; font-size: 0.74rem; color: #0f172a; word-break: break-word; }
.source-modal-note { font-size: 0.72rem; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.7rem 0.85rem; margin-bottom: 0.7rem; border-radius: var(--r-md); }
.source-modal-items { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem; }
.source-item-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.9rem 1.15rem;
  background: #ffffff;
  border: 1px solid rgba(8, 87, 195, 0.08);
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-sm);
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  overflow: hidden;
}
.source-item-card::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--c-blue);
  opacity: 0.85;
}
.source-item-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
  border-color: rgba(8, 87, 195, 0.18);
  background: #fcfdfe;
}
.source-item-left {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  min-width: 0;
}
.source-item-icon {
  width: 34px;
  height: 34px;
  background: rgba(8, 87, 195, 0.06);
  color: var(--c-blue);
  border-radius: var(--r-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  flex-shrink: 0;
  transition: all 0.2s ease;
}
.source-item-card:hover .source-item-icon {
  background: var(--c-blue);
  color: #ffffff;
  transform: scale(1.04);
}
.source-item-info {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.source-item-label {
  font-size: 0.78rem;
  font-weight: 750;
  color: #1e293b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.source-item-source-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
  font-size: 0.6rem;
  font-weight: 700;
  color: #64748b;
  background: #f8fafc;
  padding: 0.15rem 0.45rem;
  border-radius: 5px;
  border: 1px solid #e2e8f0;
  width: fit-content;
}
.source-item-source-pill i {
  font-size: 0.55rem;
  color: #94a3b8;
}
.source-item-right {
  text-align: right;
  flex-shrink: 0;
  margin-left: 0.5rem;
}
.source-item-value {
  font-size: 1.15rem;
  font-weight: 850;
  color: #0f172a;
  line-height: 1;
}

/* ── RESPONSIVE RULES ── */
@media (max-width: 1399.98px) {
  .area6-card-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 1199.98px) {
  .kpi-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .main-grid { grid-template-columns: 1fr; }
  .dp-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .area6-ranking-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 991.98px) {
  .dp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .area6-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 767.98px) {
  .db-header { align-items: flex-start; flex-direction: column; gap: 0.55rem; }
  .db-meta { flex-wrap: wrap; gap: 0.42rem; }
  .kpi-strip { grid-template-columns: 1fr; }
  .kpi-card .kc-sub { white-space: normal; }
  .area6-head { align-items: flex-start; flex-direction: column; }
  .area6-head-actions { align-items: flex-start; }
  .area6-periods { justify-content: flex-start; }
  .area6-card-grid, .area6-ranking-grid { grid-template-columns: 1fr; }
  .dp-grid { grid-template-columns: 1fr; }
  .dc { min-height: 174px; }
  .chart-panel { min-height: 330px; }
  .chart-panel canvas { height: 250px !important; }
  .source-modal-meta { grid-template-columns: 1fr; }
}
@media (max-width: 575.98px) {
  .db-shell { padding-bottom: 0.25rem; }
  .dc { min-height: 0; }
  .dc-foot { gap: 0.5rem; }
  .dashboard-source-modal .modal-dialog { margin: 0.65rem; }
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
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($simpananReport,"detail_payload",[]))' data-link="{{ data_get($simpananReport,'link','#') }}" data-link-label="{{ data_get($simpananReport,'link_label','Buka report') }}">Detail <i class="fas fa-info-circle"></i></button>
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
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($pinjamanReport,"detail_payload",[]))' data-link="{{ data_get($pinjamanReport,'link','#') }}" data-link-label="{{ data_get($pinjamanReport,'link_label','Buka report') }}">Detail <i class="fas fa-info-circle"></i></button>
    </div>

    {{-- PORTFOLIO --}}
    <div class="kpi-card portfolio">
      <div class="kc-label"><i class="fas fa-layer-group mr-1"></i>LDR (Loan to Deposit Ratio)</div>
      <div class="kc-val">{{ data_get($portfolioReport,'value','–') }}</div>
      <div class="kc-sub" style="max-width:150px;white-space:normal;font-size:.58rem;">{{ data_get($portfolioReport,'meta','–') }}</div>
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($portfolioReport,"detail_payload",[]))' data-link="{{ data_get($portfolioReport,'link','#') }}" data-link-label="{{ data_get($portfolioReport,'link_label','Lihat report') }}">Detail <i class="fas fa-info-circle"></i></button>
    </div>

    {{-- Card 4: Growth Simpanan MoM --}}
    @php $m4 = data_get($metrics, 2); @endphp
    @if($m4)
    <div class="kpi-card">
      <div class="kc-label"><i class="{{ data_get($m4,'icon','fas fa-wallet') }} mr-1" style="color:var(--c-amber);"></i>{{ data_get($m4,'label','–') }}</div>
      <div class="kc-val" style="font-size:1.32rem;">{{ data_get($m4,'value','–') }}</div>
      <div class="kc-sub {{ data_get($m4,'delta_class','text-muted') }}" style="font-size:0.62rem;font-weight:700;">{{ data_get($m4,'delta','–') }}</div>
    </div>
    @endif

    {{-- Card 5: Growth Pinjaman MoM --}}
    @php $m5 = data_get($metrics, 3); @endphp
    @if($m5)
    <div class="kpi-card">
      <div class="kc-label"><i class="{{ data_get($m5,'icon','fas fa-database') }} mr-1" style="color:var(--c-green);"></i>{{ data_get($m5,'label','–') }}</div>
      <div class="kc-val" style="font-size:1.32rem;">{{ data_get($m5,'value','–') }}</div>
      <div class="kc-sub {{ data_get($m5,'delta_class','text-muted') }}" style="font-size:0.62rem;font-weight:700;">{{ data_get($m5,'delta','–') }}</div>
    </div>
    @endif

    {{-- Card 6: Rasio CASA (Harian) --}}
    @php $m6 = collect($area6Cards)->firstWhere('key', 'casa'); @endphp
    @if($m6)
    <div class="kpi-card">
      <div class="kc-label"><i class="fas fa-percentage mr-1" style="color:var(--c-blue);"></i>CASA Ratio (Harian)</div>
      <div class="kc-val" style="font-size:1.32rem;">{{ data_get($m6,'value','–') }}</div>
      <div class="kc-sub" style="font-size:0.62rem;color:#64748b;font-weight:500;">{{ data_get($m6,'meta','–') }}</div>
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($m6,"detail_payload",[]))' data-link="{{ data_get($m6,'link','#') }}" data-link-label="{{ data_get($m6,'link_label','Lihat detail') }}">Detail <i class="fas fa-info-circle"></i></button>
    </div>
    @else
    <div class="kpi-card">
      <div class="kc-label"><i class="fas fa-percentage mr-1" style="color:var(--c-blue);"></i>CASA Ratio (Harian)</div>
      <div class="kc-val" style="font-size:1.32rem;">–</div>
      <div class="kc-sub" style="font-size:0.62rem;color:#64748b;">Data belum tersedia</div>
    </div>
    @endif
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
          <div class="source-modal-items" id="sourceModalItems">
            <div class="text-muted small text-center py-3">Detail belum tersedia.</div>
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

      const container = document.getElementById('sourceModalItems');
      const getMetricIcon = label => {
        const lbl = String(label || '').toLowerCase();
        if (lbl.includes('saldo') || lbl.includes('outstanding') || lbl.includes('os')) return 'fa-wallet';
        if (lbl.includes('rekening') || lbl.includes('account')) return 'fa-file-invoice-dollar';
        if (lbl.includes('cif')) return 'fa-users';
        if (lbl.includes('cabang') || lbl.includes('kanca') || lbl.includes('unit')) return 'fa-building';
        if (lbl.includes('ldr')) return 'fa-balance-scale';
        return 'fa-chart-line';
      };
      
      container.innerHTML = rows.length
        ? rows.map(row => {
            const icon = getMetricIcon(row.label);
            const sourceText = row.source || detail.source_table || '-';
            return `
              <div class="source-item-card">
                <div class="source-item-left">
                  <div class="source-item-icon">
                    <i class="fas ${icon}"></i>
                  </div>
                  <div class="source-item-info">
                    <div class="source-item-label" title="${escapeHtml(row.label || '-')}">${escapeHtml(row.label || '-')}</div>
                    <div class="source-item-source-pill">
                      <i class="fas fa-database"></i>
                      <span>${escapeHtml(sourceText)}</span>
                    </div>
                  </div>
                </div>
                <div class="source-item-right">
                  <div class="source-item-value">${escapeHtml(row.value || '-')}</div>
                </div>
              </div>
            `;
          }).join('')
        : '<div class="text-muted small text-center py-3">Detail belum tersedia.</div>';

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
