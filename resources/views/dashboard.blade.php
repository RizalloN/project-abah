@extends('layouts.admin')
@section('title', 'A-SIX | Dashboard Area 6')
@section('content')
@php
$hero = data_get($dashboard ?? [], 'hero', []);
$metrics = data_get($dashboard ?? [], 'metrics', []);
$liveReports = is_array(data_get($dashboard ?? [], 'live_reports')) ? data_get($dashboard ?? [], 'live_reports') : [];
$digitalCards = is_array(data_get($dashboard ?? [], 'digital_performance.cards')) ? data_get($dashboard ?? [], 'digital_performance.cards') : [];
$timeseries = data_get($dashboard ?? [], 'timeseries', ['labels'=>[],'simpanan'=>[],'pinjaman'=>[]]);
$landingSummary = data_get($dashboard ?? [], 'landing_summary', []);
$landingProfit = data_get($landingSummary, 'profit', []);
$landingDecision = data_get($landingSummary, 'decision', []);
$landingRealization = data_get($landingSummary, 'realization', []);
$area6Portfolio = data_get($dashboard ?? [], 'area6_portfolio', []);
$area6Cards = is_array(data_get($area6Portfolio, 'cards')) ? data_get($area6Portfolio, 'cards') : [];
$area6Rankings = is_array(data_get($area6Portfolio, 'rankings')) ? data_get($area6Portfolio, 'rankings') : [];
$area6RankingModes = is_array(data_get($area6Portfolio, 'ranking_modes')) ? data_get($area6Portfolio, 'ranking_modes') : [];
$area6DefaultScope = data_get($area6Portfolio, 'default_scope', 'cabang_konsol');
$area6ScopePayloads = is_array(data_get($area6Portfolio, 'scopes')) ? data_get($area6Portfolio, 'scopes') : [];
if (empty($area6ScopePayloads)) {
  $area6ScopePayloads = [$area6DefaultScope => $area6Portfolio];
}
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
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 36px;
  font-size: 0.62rem;
  font-weight: 700;
  color: var(--c-blue);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  border: 0;
  background: transparent;
  padding: 0 0.4rem;
  cursor: pointer;
  transition: color 0.15s ease;
}
.kpi-card .kc-link:hover {
  color: var(--c-blue-d);
  text-decoration: underline;
}

/* --- LANDING EXECUTIVE SUMMARY --- */
.landing-summary {
  margin: 0.85rem 0 1.25rem;
  background: #ffffff;
  border: 1px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-md);
  overflow: hidden;
}
.landing-summary-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.05rem 1.25rem;
  background: #f8fafc;
  border-bottom: 1px solid var(--c-border);
}
.landing-summary-title {
  font-size: 1rem;
  font-weight: 850;
  color: #0f172a;
}
.landing-summary-sub {
  margin-top: 0.12rem;
  font-size: 0.7rem;
  color: #64748b;
}
.landing-summary-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.38rem;
  padding: 0.36rem 0.78rem;
  background: #eef6ff;
  border: 1px solid #bfdbfe;
  color: var(--c-blue);
  font-size: 0.65rem;
  font-weight: 800;
  border-radius: 999px;
  white-space: nowrap;
}
.landing-summary-grid {
  display: grid;
  grid-template-columns: 1.04fr 1fr 1.12fr;
  gap: 0.9rem;
  padding: 1rem;
}
.landing-summary-card {
  min-width: 0;
  border: 1px solid var(--c-border);
  background: #ffffff;
  border-radius: var(--r-xl);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.landing-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.85rem 1.15rem;
  background: linear-gradient(135deg, #0f4cba 0%, #1e40af 100%);
  border-bottom: none;
}
.landing-card-title {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  font-size: 0.78rem;
  font-weight: 800;
  color: #ffffff;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.landing-card-icon-wrap {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  background-color: rgba(255, 255, 255, 0.18);
  color: #ffffff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
}
.landing-card-period {
  font-size: 0.65rem;
  font-weight: 700;
  color: #e2e8f0;
  background: rgba(255, 255, 255, 0.15);
  padding: 0.25rem 0.6rem;
  border-radius: var(--r-md);
  white-space: nowrap;
}
.landing-main-value {
  padding: 0.95rem 0.95rem 0.35rem;
}
.landing-main-value .value {
  font-size: 1.42rem;
  font-weight: 900;
  line-height: 1;
  color: #0f172a;
}
.landing-main-value .meta {
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  margin-top: 0.45rem;
  font-size: 0.66rem;
  font-weight: 800;
}
.landing-branch-list,
.landing-decision-list,
.landing-segment-list {
  display: grid;
  gap: 0.55rem;
  padding: 0.75rem 0.95rem 0.95rem;
}
.landing-decision-summary-strip {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.45rem;
  padding: 0.75rem 0.95rem 0;
}
.landing-decision-chip {
  min-width: 0;
  border: 1px solid #dbeafe;
  background: linear-gradient(180deg, #f8fbff 0%, #eff6ff 100%);
  border-radius: var(--r-md);
  padding: 0.5rem 0.62rem;
}
.landing-decision-chip span {
  display: block;
  color: #64748b;
  font-size: 0.58rem;
  font-weight: 800;
  letter-spacing: 0.045em;
  text-transform: uppercase;
}
.landing-decision-chip strong {
  display: block;
  margin-top: 0.15rem;
  color: #0f172a;
  font-size: 0.78rem;
  font-weight: 900;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.landing-branch-row,
.landing-decision-row,
.landing-segment-row {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  min-height: 52px;
  padding: 0.6rem 0.8rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: var(--r-md);
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.landing-branch-row:hover,
.landing-decision-row:hover,
.landing-segment-row:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(15, 23, 42, 0.08);
  border-color: var(--c-blue);
  background: #ffffff;
}
.landing-branch-icon,
.landing-decision-icon,
.landing-segment-icon {
  flex: 0 0 36px;
  width: 36px;
  height: 36px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: var(--c-blue);
  border-radius: var(--r-md);
  font-size: 0.9rem;
  transition: all 0.25s ease;
}
.landing-branch-row:hover .landing-branch-icon,
.landing-decision-row:hover .landing-decision-icon,
.landing-segment-row:hover .landing-segment-icon {
  background: var(--c-blue);
  color: #ffffff;
  border-color: var(--c-blue);
}
.landing-branch-body,
.landing-decision-body,
.landing-segment-body {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.landing-row-label-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
  width: 100%;
}
.landing-row-label {
  min-width: 0;
  font-size: 0.72rem;
  font-weight: 800;
  color: #334155;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.landing-row-sub {
  font-size: 0.61rem;
  font-weight: 700;
  color: #64748b;
  white-space: nowrap;
}
.landing-row-value {
  flex-shrink: 0;
  text-align: right;
  font-size: 0.78rem;
  font-weight: 900;
  color: #0f172a;
}
.landing-progress-container {
  height: 5px;
  background: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
  width: 100%;
}
.landing-progress-bar {
  height: 100%;
  border-radius: inherit;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
.landing-progress-bar.bg-primary {
  background-color: var(--c-blue) !important;
}
.landing-progress-bar.bg-success {
  background-color: #10b981 !important;
}
.landing-progress-bar.bg-green {
  background-color: #10b981 !important;
}
.landing-progress-bar.bg-amber {
  background-color: #f59e0b !important;
}
.landing-progress-bar.bg-red {
  background-color: #ef4444 !important;
}
.landing-progress-bar.bg-blue {
  background-color: #3b82f6 !important;
}
.landing-decision-note {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  width: fit-content;
  max-width: 100%;
  margin-top: 0.1rem;
  padding: 0.17rem 0.45rem;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  background: #eff6ff;
  color: var(--c-blue);
  font-size: 0.56rem;
  font-weight: 850;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.landing-scope-caption {
  margin: 0.85rem 0.95rem 0;
  display: inline-flex;
  align-items: center;
  width: fit-content;
  padding: 0.28rem 0.72rem;
  border-radius: 999px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: var(--c-blue);
  font-size: 0.66rem;
  font-weight: 850;
}
.landing-decision-row,
.landing-segment-row {
  justify-content: flex-start;
}
.landing-decision-body,
.landing-segment-body {
  flex: 1 1 auto;
  min-width: 0;
}
.landing-realization-tabs {
  display: inline-flex;
  gap: 0.22rem;
  padding: 0.18rem;
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 999px;
}
.landing-realization-tab {
  border: 0;
  background: transparent;
  color: rgba(255, 255, 255, 0.85);
  font-size: 0.6rem;
  font-weight: 850;
  cursor: pointer;
  padding: 0.25rem 0.55rem;
  border-radius: 999px;
  transition: all 0.2s ease;
}
.landing-realization-tab:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.08);
}
.landing-realization-tab.active {
  background: #ffffff;
  color: var(--c-blue);
  box-shadow: var(--shadow-sm);
}
.landing-empty {
  padding: 1rem;
  color: #64748b;
  font-size: 0.72rem;
  font-weight: 700;
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

/* ── PREMIUM AREA 6 CARDS (MOCKUP STYLE) ── */
.area6-panel .area6-card-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1.25rem;
  padding: 1.5rem;
}

.area6-card-premium {
  border: 1px solid var(--c-border);
  appearance: none;
  width: 100%;
  padding: 0;
  text-align: left;
  background: #ffffff;
  position: relative;
  overflow: visible; /* to allow floating badge overflow */
  display: flex;
  flex-direction: column;
  cursor: pointer;
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
  border-radius: var(--r-xl);
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
}

.area6-card-premium:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
  border-color: #cbd5e1;
}

/* Header Banner */
.ap-header {
  position: relative;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-top-left-radius: calc(var(--r-xl) - 1px);
  border-top-right-radius: calc(var(--r-xl) - 1px);
  padding: 0 1rem;
}

.ap-header-title {
  color: #ffffff;
  font-size: 0.76rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-align: center;
}

/* Header Colors */
.ap-header.bg-os {
  background-color: #0f4cba;
}
.ap-header.bg-sml {
  background-color: #1e72e8;
}
.ap-header.bg-npl {
  background-color: #29b6f6;
}

/* Overlapping Badge */
.ap-badge {
  position: absolute;
  top: -12px;
  left: 12px;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 3px solid #ffffff;
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
  color: #ffffff;
  font-size: 1.05rem;
  z-index: 10;
}

.ap-badge.bg-os {
  background-color: #0f4cba;
}
.ap-badge.bg-sml {
  background-color: #1e72e8;
}
.ap-badge.bg-npl {
  background-color: #29b6f6;
}

/* Body Content */
.ap-body {
  padding: 1.25rem 1.25rem 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}

/* Row Layouts */
.ap-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  text-align: center;
  position: relative;
}

.ap-grid-2::after {
  content: "";
  position: absolute;
  top: 10%;
  bottom: 10%;
  left: 50%;
  width: 1px;
  background-color: #e2e8f0;
}

/* Metric Items */
.ap-metric-col {
  padding: 0.5rem 0.25rem;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.ap-metric-label {
  font-size: 0.7rem;
  font-weight: 700;
  color: #334155;
  margin-bottom: 0.25rem;
  text-align: center;
  line-height: 1.25;
}

.ap-metric-val {
  font-size: 1.45rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
  letter-spacing: -0.02em;
}

.ap-metric-sub {
  font-size: 0.65rem;
  font-weight: 700;
  color: #64748b;
  margin-top: 0.15rem;
}

/* Row 2 Styles */
.ap-metric-pct-val {
  font-size: 1.45rem;
  font-weight: 800;
  line-height: 1.1;
}

.ap-metric-gap-val {
  font-size: 1.45rem;
  font-weight: 800;
  line-height: 1.1;
}

/* Dynamic Flat colors */
.text-green-flat {
  color: #10b981 !important;
}
.text-amber-flat {
  color: #f59e0b !important;
}
.text-red-flat {
  color: #ef4444 !important;
}

/* Dashed Divider */
.ap-dashed-divider {
  border: 0;
  border-top: 1.5px dashed #cbd5e1;
  margin: 0.75rem 0;
}

/* Row 3 - Deltas */
.ap-deltas {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  text-align: center;
}

.ap-delta-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.ap-delta-label {
  font-size: 0.72rem;
  font-weight: 800;
  color: #334155;
  margin-bottom: 0.25rem;
}

.ap-delta-val {
  font-size: 0.8rem;
  font-weight: 700;
  line-height: 1;
}

.ap-delta-arrow {
  font-size: 0.95rem;
  margin-top: 0.2rem;
  line-height: 1;
}

/* ── AREA 6 SEGMENT PERFORMANCE CARD (MOCKUP STYLE) ── */
.area6-segment-container {
  display: grid;
  grid-template-columns: 2.2fr 1fr;
  gap: 1.25rem;
  padding: 0 1.25rem 1.25rem;
}
@media (max-width: 1100px) {
  .landing-summary-grid {
    grid-template-columns: 1fr;
  }
  .area6-segment-container {
    grid-template-columns: 1fr;
  }
}
.area6-segment-card {
  background: #ffffff;
  border: 1px solid var(--c-border);
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
  border-radius: var(--r-xl);
  overflow: hidden;
  margin: 0 1.5rem 1.5rem;
}
.area6-segment-container .area6-segment-card {
  margin: 0;
}

/* ── TOTAL COMPOSITION CARD (MOCKUP STYLE) ── */
.total-composition-card {
  background: #ffffff;
  border: 1px solid var(--c-border);
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
  border-radius: var(--r-xl);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.tcc-body {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 100%;
  flex-grow: 1;
  gap: 1.25rem;
}
.tcc-chart-row {
  display: flex;
  align-items: center;
  justify-content: space-around;
  gap: 1rem;
}
.composition-donut {
  width: 140px;
  height: 140px;
  border-radius: 50%;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: inset 0 0 10px rgba(0,0,0,0.06), 0 4px 10px rgba(0,0,0,0.05);
}
.composition-donut::before {
  content: "";
  position: absolute;
  width: 90px;
  height: 90px;
  background: #ffffff;
  border-radius: 50%;
  z-index: 1;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.donut-center {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.donut-center-pct {
  font-size: 1.05rem;
  font-weight: 850;
  color: #0f172a;
}
.donut-center-label {
  font-size: 0.55rem;
  font-weight: 700;
  color: #64748b;
  letter-spacing: 0.05em;
}
.tcc-legends {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  min-width: 110px;
}
.tcc-legend-item {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
}
.tcc-legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  margin-top: 0.35rem;
  flex-shrink: 0;
}
.tcc-legend-dot.bg-os { background-color: #a855f7; }
.tcc-legend-dot.bg-sml { background-color: #1e72e8; }
.tcc-legend-dot.bg-npl { background-color: #ef4444; }

.tcc-legend-info {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}
.tcc-legend-name {
  font-size: 0.7rem;
  font-weight: 800;
  color: #475569;
}
.tcc-legend-val {
  font-size: 0.85rem;
  font-weight: 800;
  color: #0f172a;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}
.tcc-legend-pct {
  font-size: 0.7rem;
  font-weight: 700;
  color: #64748b;
}
.tcc-total-badge {
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: var(--r-lg);
  padding: 0.75rem 1rem;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  margin-top: auto;
}
.tcc-total-label {
  font-size: 0.62rem;
  font-weight: 800;
  color: #475569;
  letter-spacing: 0.08em;
}
.tcc-total-val {
  font-size: 1.15rem;
  font-weight: 900;
  color: #0f4cba;
}
.asc-header {
  background-color: #0f4cba;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem 1.5rem;
  border-top-left-radius: calc(var(--r-xl) - 1px);
  border-top-right-radius: calc(var(--r-xl) - 1px);
}
.asc-header-icon {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background-color: rgba(255, 255, 255, 0.2);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}
.asc-header-title {
  color: #ffffff;
  font-size: 0.85rem;
  font-weight: 800;
  letter-spacing: 0.05em;
}
.asc-body-wrapper {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.asc-body {
  min-width: 900px;
  background: #ffffff;
}
.asc-grid-cols {
  display: grid;
  grid-template-columns: minmax(180px, 1.2fr) 1.6fr 1.6fr 1.6fr;
  align-items: end;
  padding: 0.95rem 1.5rem;
  background-color: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
}
.asc-col-spacer {
  /* empty space above the segment names */
}
/* Thin vertical separators between OS, SML, and NPL columns */
.asc-grid-cols > div:nth-child(2),
.asc-row > div:nth-child(2) {
  border-right: 1px solid #e2e8f0;
  padding-right: 1.25rem;
}
.asc-grid-cols > div:nth-child(3),
.asc-row > div:nth-child(3) {
  border-right: 1px solid #e2e8f0;
  padding-left: 1.25rem;
  padding-right: 1.25rem;
}
.asc-grid-cols > div:nth-child(4),
.asc-row > div:nth-child(4) {
  padding-left: 1.25rem;
}

.asc-col-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0 0.5rem;
}
.asc-col-title {
  font-size: 0.75rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: 0.05em;
  margin-bottom: 0.65rem;
  text-align: center;
}
.asc-col-legend {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  font-size: 0.65rem;
  font-weight: 700;
  color: #64748b;
  padding-bottom: 0.15rem;
}
.legend-item {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}
.legend-box {
  width: 10px;
  height: 6px;
  border-radius: 2px;
}
.legend-box.bg-os-blue { background-color: #0f4cba; }
.legend-box.bg-sml-blue { background-color: #1e72e8; }
.legend-box.bg-npl-blue { background-color: #29b6f6; }
.legend-box.bg-gray { background-color: #e2e8f0; }

.legend-item-pct {
  width: 65px;
  text-align: right;
}
.asc-row {
  display: grid;
  grid-template-columns: minmax(180px, 1.2fr) 1.6fr 1.6fr 1.6fr;
  align-items: center;
  padding: 0.85rem 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  transition: background-color 0.15s ease;
}
.asc-row:hover {
  background-color: #f8fafc;
}
.asc-seg-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.asc-seg-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background-color: #f1f5f9;
  color: #475569;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  transition: all 0.2s ease;
}
.asc-row:hover .asc-seg-icon {
  background-color: var(--c-blue);
  color: #ffffff;
  transform: scale(1.05);
}
.asc-seg-name {
  font-size: 0.8rem;
  font-weight: 750;
  color: #1e293b;
}
.asc-metric-group {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0 0.5rem;
}
.asc-bar-container {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1 1 0;
  min-width: 60px;
  max-width: 120px;
}
.asc-bar {
  height: 6px;
  border-radius: 3px;
}
.asc-bar.bg-os-blue { background-color: #0f4cba; }
.asc-bar.bg-sml-blue { background-color: #1e72e8; }
.asc-bar.bg-npl-blue { background-color: #29b6f6; }
.asc-bar.bg-gray { background-color: #e2e8f0; }

.asc-value {
  font-size: 0.8rem;
  font-weight: 700;
  color: #1e293b;
  width: 85px;
  text-align: right;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}
.asc-pct {
  font-size: 0.8rem;
  font-weight: 800;
  width: 65px;
  text-align: right;
}
.asc-total-row {
  background-color: #fcfdfe;
  border-top: 2px solid #cbd5e1;
  border-bottom: 0;
}
.asc-total-row .asc-seg-name {
  font-size: 0.85rem;
  font-weight: 850;
  color: #0f172a;
}
.asc-target-total {
  font-size: 0.8rem;
  font-weight: 700;
  width: 85px;
  text-align: right;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
  color: #64748b;
}

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

/* ── AREA 6 SKELETON LOADER ── */
#area6-loading-overlay {
  grid-column: 1 / -1;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2.5rem 1.5rem 2rem;
  gap: 0.6rem;
}
#area6-loading-overlay .a6-skel-title {
  font-size: 0.72rem;
  font-weight: 700;
  color: #64748b;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 0.25rem;
}
#area6-progress-track {
  width: min(360px, 90%);
  height: 7px;
  background: #e2e8f0;
  border-radius: 6px;
  overflow: hidden;
  position: relative;
}
#area6-progress-fill {
  height: 100%;
  width: 0%;
  border-radius: 6px;
  background: linear-gradient(90deg, #0071e3, #38bdf8);
  transition: width 0.35s ease;
  position: relative;
}
#area6-progress-fill::after {
  content: '';
  position: absolute;
  top: 0; right: 0; bottom: 0;
  width: 40px;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.55));
  animation: shimmer-pulse 1.2s ease-in-out infinite;
}
@keyframes shimmer-pulse {
  0%, 100% { opacity: 0.6; }
  50%       { opacity: 1; }
}
#area6-progress-pct {
  font-size: 0.75rem;
  font-weight: 800;
  color: #0071e3;
  min-width: 36px;
  text-align: center;
}
#area6-loading-status {
  font-size: 0.65rem;
  color: #94a3b8;
  font-weight: 500;
  min-height: 16px;
  text-align: center;
}
.a6-skel-card-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  padding: 1.2rem 1.5rem;
  width: 100%;
}
.a6-skel-card {
  height: 130px;
  border-radius: 12px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: skel-wave 1.6s ease-in-out infinite;
}
@keyframes skel-wave {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── MAIN GRID ── */
.main-grid {
  display: grid;
  grid-template-columns: minmax(420px, 1fr) minmax(0, 1.55fr);
  gap: 1rem;
  align-items: stretch;
}

/* ── CHART PANEL ── */
.chart-panel {
  background: #ffffff;
  border: 1px solid var(--c-border);
  padding: 1.25rem;
  min-height: 380px;
  position: relative;
  display: flex;
  flex-direction: column;
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
  height: auto !important;
  min-height: 300px;
  flex: 1 1 300px;
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

/* ── TREND POSISI & PERFORMANCE VS RKA DOUBLE CARD LAYOUT ── */
.trend-position-card {
  background: #ffffff;
  border: 1px solid var(--c-border);
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
  border-radius: var(--r-xl);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  height: 100%;
}
.trend-position-card .asc-header {
  background-color: #0f4cba;
}
.tpc-body {
  padding: 1.5rem 0;
  display: flex;
  height: 100%;
  align-items: stretch;
}
.trend-col {
  flex: 1 1 0;
  padding: 0 1.25rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
}
.trend-col:not(:last-child)::after {
  content: "";
  position: absolute;
  right: 0;
  top: 10%;
  height: 80%;
  width: 1px;
  background-color: #e2e8f0;
}
.trend-col-title {
  font-size: 0.72rem;
  font-weight: 800;
  margin-bottom: 1.25rem;
  text-align: center;
  letter-spacing: 0.03em;
}
.text-os-blue { color: #0f4cba; }
.text-sml-blue { color: #00a3ff; }
.text-npl-red { color: #ef4444; }

.trend-chart-wrapper {
  width: 100%;
  max-width: 260px;
  margin: 0 auto;
}
.trend-chart-wrapper svg {
  width: 100%;
  height: auto;
  overflow: visible;
}
.trend-dates-row {
  display: flex;
  justify-content: space-between;
  width: 100%;
  max-width: 260px;
  margin: 0.75rem auto 0;
  padding: 0 9.09%;
}
.trend-date-label {
  font-size: 0.58rem;
  font-weight: 600;
  color: #64748b;
  text-align: center;
  transform: translateX(-50%);
  width: 0;
  line-height: 1.2;
}
.trend-date-label strong {
  display: block;
  font-size: 0.65rem;
  font-weight: 800;
  color: #1e293b;
  margin-bottom: 2px;
  white-space: nowrap;
}
.trend-date-label .date-part {
  display: block;
  white-space: nowrap;
  color: #475569;
}
.trend-date-label .year-part {
  display: block;
  white-space: nowrap;
  color: #94a3b8;
  font-size: 0.52rem;
  margin-top: 1px;
}

.perf-rka-card {
  background: #ffffff;
  border: 1px solid var(--c-border);
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
  border-radius: var(--r-xl);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  height: 100%;
}
.perf-rka-card .asc-header {
  background-color: #0f4cba;
}
.prc-body {
  padding: 1.25rem;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.perf-table-wrapper {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.perf-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 500px;
}
.perf-table th {
  font-size: 0.65rem;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 0.6rem 0.4rem;
  border-bottom: 2px solid #e2e8f0;
  text-align: center;
  line-height: 1.3;
}
.perf-table td {
  font-size: 0.75rem;
  padding: 0.8rem 0.4rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  text-align: center;
}
.perf-table tr:last-child td {
  border-bottom: none;
}
.perf-indicator-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 800;
  color: #1e293b;
  text-align: left;
}
.perf-indicator-icon {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
}
.bg-icon-os { background-color: #e0f2fe; color: #0f4cba; }
.bg-icon-sml { background-color: #e0f7fa; color: #00a3ff; }
.bg-icon-npl { background-color: #fee2e2; color: #ef4444; }

.perf-mono-cell {
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
  font-weight: 700;
  color: #334155;
}
.perf-pct-cell {
  font-weight: 800;
}
.perf-status-circle {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 0.6rem;
}
.bg-status-red { background-color: #ef4444; }
.bg-status-green { background-color: #22c55e; }
.bg-status-amber { background-color: #f59e0b; }

@media (max-width: 767.98px) {
  .tpc-body {
    flex-direction: column;
    gap: 1.5rem;
    padding: 1.5rem 0;
  }
  .trend-col:not(:last-child)::after {
    display: none;
  }
  .trend-col {
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #f1f5f9;
  }
  .trend-col:last-child {
    padding-bottom: 0;
    border-bottom: none;
  }
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
  .landing-summary-head { align-items: flex-start; flex-direction: column; }
  .landing-summary-grid { padding: 0.8rem; }
  .landing-card-head { align-items: flex-start; flex-direction: column; }
  .landing-realization-tabs { flex-wrap: wrap; border-radius: var(--r-md); }
  .landing-branch-row,
  .landing-decision-row,
  .landing-segment-row { align-items: flex-start; }
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

/* ── BRANCH PERFORMANCE BARS ── */
.cabang-performance-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1.25rem;
  padding: 0 1.25rem 1.25rem;
}
.perf-panel-card {
  background: #ffffff;
  border: 1px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
}
.perf-panel-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
  border-color: #cbd5e1;
}
.perf-panel-head {
  padding: 1.1rem 1.4rem 0.8rem;
  border-bottom: 1px solid #f1f5f9;
  background: #f8fafc;
}
.perf-panel-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  font-size: 0.88rem;
  font-weight: 800;
  color: #0f172a;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.perf-panel-badge {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.perf-panel-badge.bg-simp { background-color: var(--c-blue); }
.perf-panel-badge.bg-pinj { background-color: var(--c-teal); }
.perf-panel-badge.bg-sml { background-color: var(--c-amber); }
.perf-panel-badge.bg-npl { background-color: var(--c-red); }

.perf-panel-subtitle {
  font-size: 0.65rem;
  color: #64748b;
  margin-top: 0.2rem;
}
.perf-panel-body {
  padding: 1.25rem 1.4rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.perf-bar-row {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.perf-bar-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.perf-bar-branch {
  font-size: 0.76rem;
  font-weight: 700;
  color: #1e293b;
}
.perf-bar-value {
  font-size: 0.76rem;
  font-weight: 800;
  color: #0f172a;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}
.perf-bar-track {
  height: 8px;
  background: #f1f5f9;
  border-radius: 4px;
  overflow: hidden;
  position: relative;
}
.perf-bar-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}
.perf-bar-fill.bg-simp-grad {
  background: linear-gradient(90deg, var(--c-blue) 0%, var(--c-blue-l) 100%);
}
.perf-bar-fill.bg-pinj-grad {
  background: linear-gradient(90deg, var(--c-teal) 0%, #14b8a6 100%);
}
.perf-bar-fill.bg-sml-grad {
  background: linear-gradient(90deg, #d97706 0%, #f59e0b 100%);
}
.perf-bar-fill.bg-npl-grad {
  background: linear-gradient(90deg, #dc2626 0%, #f87171 100%);
}

@media (max-width: 767.98px) {
  .cabang-performance-grid {
    grid-template-columns: 1fr;
  }
}

/* Custom Date Picker & PPT Button */
.db-date-picker-container {
  position: relative;
  display: inline-flex;
  align-items: center;
}
.db-date-picker-select {
  appearance: none;
  -webkit-appearance: none;
  background: #ffffff;
  border: 1px solid rgba(8, 87, 195, 0.2);
  border-radius: 6px;
  padding: 0.35rem 1.8rem 0.35rem 0.8rem;
  font-size: 0.65rem;
  font-weight: 700;
  color: var(--c-blue);
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: all 0.2s ease;
  font-family: inherit;
}
.db-date-picker-select:hover {
  border-color: var(--c-blue);
  background-color: #f8faff;
  box-shadow: 0 2px 4px rgba(8, 87, 195, 0.08);
}
.db-date-picker-select:focus {
  outline: none;
  border-color: var(--c-blue);
  box-shadow: 0 0 0 2px rgba(8, 87, 195, 0.15);
}
.db-date-picker-icon {
  position: absolute;
  right: 0.65rem;
  font-size: 0.65rem;
  color: var(--c-blue);
  pointer-events: none;
}
.db-ppt-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.8rem;
  background: linear-gradient(135deg, #d24726 0%, #b83b1d 100%);
  border: none;
  border-radius: 6px;
  font-size: 0.65rem;
  font-weight: 700;
  color: #ffffff;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(210, 71, 38, 0.15);
  transition: all 0.2s ease;
  font-family: inherit;
}
.db-ppt-btn:hover {
  background: linear-gradient(135deg, #e05230 0%, #c44324 100%);
  box-shadow: 0 4px 8px rgba(210, 71, 38, 0.25);
  transform: translateY(-1px);
}
.db-ppt-btn:active {
  transform: translateY(0);
}

/* PPT Loading Overlay */
.ppt-loading-overlay {
  position: fixed !important;
  top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important;
  background: rgba(15, 23, 42, 0.75) !important;
  backdrop-filter: blur(12px) !important;
  -webkit-backdrop-filter: blur(12px) !important;
  z-index: 999999 !important;
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  color: #ffffff !important;
  font-family: 'Inter', sans-serif !important;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.35s cubic-bezier(0.16, 1, 0.3, 1) !important;
  box-sizing: border-box !important;
}
.ppt-loading-overlay.active {
  opacity: 1 !important;
  pointer-events: auto !important;
}
.ppt-loading-card {
  width: min(360px, 100%);
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.ppt-spinner-container {
  position: relative;
  width: 80px;
  height: 80px;
  margin-bottom: 1.5rem;
}
.ppt-ring {
  box-sizing: border-box;
  display: block;
  position: absolute;
  width: 80px;
  height: 80px;
  border: 4px solid transparent;
  border-radius: 50%;
  border-top-color: #d24726; /* PPT Orange */
  animation: loading-spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
}
.ppt-ring-inner {
  box-sizing: border-box;
  display: block;
  position: absolute;
  width: 60px;
  height: 60px;
  top: 10px;
  left: 10px;
  border: 4px solid transparent;
  border-radius: 50%;
  border-bottom-color: #ffd07b; /* Danantara Gold */
  animation: loading-spin-reverse 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
}
.ppt-loading-text {
  font-family: 'Inter', sans-serif !important;
  font-size: 1.1rem !important;
  font-weight: 700 !important;
  color: #ffffff !important;
  letter-spacing: 0.05em !important;
  text-transform: uppercase !important;
  animation: loading-pulse 1.8s ease-in-out infinite;
  margin-bottom: 0.25rem;
}
.ppt-loading-sub {
  font-family: 'Inter', sans-serif !important;
  font-size: 0.75rem !important;
  color: #cbd5e1 !important;
  font-weight: 500 !important;
  margin-top: 0.25rem;
  line-height: 1.4;
}

/* Global Dashboard Loading Overlay */
.dashboard-loading-overlay {
  position: fixed !important;
  top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important;
  background: rgba(15, 23, 42, 0.75) !important;
  backdrop-filter: blur(12px) !important;
  -webkit-backdrop-filter: blur(12px) !important;
  z-index: 1000000 !important;
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 1.25rem !important;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.35s cubic-bezier(0.16, 1, 0.3, 1) !important;
  box-sizing: border-box !important;
}
.dashboard-loading-overlay.active {
  opacity: 1 !important;
  pointer-events: auto !important;
}
.dashboard-loading-card {
  width: min(360px, 100%);
  padding: 0;
  background: transparent;
  border: none;
  border-radius: 0;
  box-shadow: none;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.dashboard-loading-top {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
}
.loading-spinner-container {
  position: relative;
  width: 80px;
  height: 80px;
  margin-bottom: 1.5rem;
}
.loading-ring {
  box-sizing: border-box;
  display: block;
  position: absolute;
  width: 80px;
  height: 80px;
  border: 4px solid transparent;
  border-radius: 50%;
  border-top-color: #0071e3; /* BRI Blue */
  animation: loading-spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
}
.loading-ring-inner {
  box-sizing: border-box;
  display: block;
  position: absolute;
  width: 60px;
  height: 60px;
  top: 10px;
  left: 10px;
  border: 4px solid transparent;
  border-radius: 50%;
  border-bottom-color: #ffd07b; /* Danantara Gold */
  animation: loading-spin-reverse 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
}
.dashboard-loading-text {
  font-family: 'Inter', sans-serif !important;
  font-size: 1.1rem !important;
  font-weight: 700 !important;
  color: #ffffff !important;
  letter-spacing: 0.05em !important;
  text-transform: uppercase !important;
  animation: loading-pulse 1.8s ease-in-out infinite;
}
.dashboard-loading-sub {
  font-family: 'Inter', sans-serif !important;
  font-size: 0.75rem !important;
  color: #cbd5e1 !important;
  font-weight: 500 !important;
  margin-top: 0.25rem;
  line-height: 1.4;
}
@keyframes loading-spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
@keyframes loading-spin-reverse {
  0% { transform: rotate(360deg); }
  100% { transform: rotate(0deg); }
}
@keyframes loading-pulse {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 1; }
}
@keyframes loading-scan {
  0% { transform: translateX(-110%); }
  55% { transform: translateX(78%); }
  100% { transform: translateX(240%); }
}
@media (max-width: 640px) {
  .dashboard-loading-card {
    width: min(320px, 100%);
  }
  .loading-spinner-container {
    width: 46px;
    height: 46px;
  }
}

/* --- APPLE STYLE PRESENTATION MODE --- */
.db-pres-btn {
  background: linear-gradient(135deg, #1e293b, #0f172a);
  color: #f8fafc;
  border: 1px solid rgba(255,255,255,0.15);
  padding: 0.45rem 0.9rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.db-pres-btn:hover {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  border-color: rgba(255,255,255,0.25);
  color: #ffffff;
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(37,99,235,0.25);
}
.apple-presentation-mode {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: #000000;
  z-index: 15000;
  display: none;
  overflow: hidden;
  font-family: 'Inter', sans-serif;
  color: #f8fafc;
  box-sizing: border-box;
  padding: 5rem 2rem 5rem 2rem;
  user-select: none;
}
.apple-presentation-mode.active {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.apple-presentation-mode::before, .apple-presentation-mode::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  filter: blur(120px);
  opacity: 0.18;
  z-index: 1;
  pointer-events: none;
}
.apple-presentation-mode::before {
  width: 600px;
  height: 600px;
  background: radial-gradient(circle, #3b82f6 0%, transparent 70%);
  top: -200px;
  left: -200px;
  animation: ambient-pulse-1 25s infinite alternate ease-in-out;
}
.apple-presentation-mode::after {
  width: 700px;
  height: 700px;
  background: radial-gradient(circle, #8b5cf6 0%, transparent 70%);
  bottom: -250px;
  right: -250px;
  animation: ambient-pulse-2 30s infinite alternate ease-in-out;
}
@keyframes ambient-pulse-1 {
  0% { transform: translate(0, 0) scale(1); opacity: 0.12; }
  50% { transform: translate(120px, 80px) scale(1.15); opacity: 0.22; }
  100% { transform: translate(-40px, 120px) scale(0.9); opacity: 0.12; }
}
@keyframes ambient-pulse-2 {
  0% { transform: translate(0, 0) scale(1.1); opacity: 0.15; }
  50% { transform: translate(-100px, -80px) scale(0.85); opacity: 0.25; }
  100% { transform: translate(60px, -140px) scale(1.05); opacity: 0.15; }
}
.pres-top-bar {
  position: absolute;
  top: 1.5rem;
  left: 3rem;
  right: 3rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 10;
}
.pres-bottom-bar {
  position: absolute;
  bottom: 1.5rem;
  left: 3rem;
  right: 3rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 10;
}
.pres-title-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.pres-logo-img {
  height: 24px;
  filter: brightness(0) invert(1);
}
.pres-title-lbl {
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: rgba(255, 255, 255, 0.9);
}
.pres-title-lbl span {
  color: #3b82f6;
  font-weight: 400;
}
.pres-controls-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.pres-meta-chip {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  padding: 0.35rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.7);
  font-weight: 500;
}
.pres-close-btn {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: #ffffff;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.pres-close-btn:hover {
  background: rgba(239, 68, 68, 0.2);
  border-color: rgba(239, 68, 68, 0.4);
  color: #f87171;
  transform: scale(1.05);
}
.pres-slides-container {
  position: relative;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 5;
}
.apple-slide {
  position: absolute;
  width: 100%;
  height: 100%;
  max-width: 1360px;
  max-height: 760px;
  opacity: 0;
  visibility: hidden;
  transform: translateX(120px) scale(0.96);
  transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex;
  flex-direction: column;
  justify-content: center;
  box-sizing: border-box;
}
.apple-slide.active {
  opacity: 1;
  visibility: visible;
  transform: translateX(0) scale(1);
}
.apple-slide.prev {
  opacity: 0;
  visibility: hidden;
  transform: translateX(-120px) scale(0.96);
}
.apple-slide.active .animate-fade-in {
  opacity: 1 !important;
  transform: translateY(0) !important;
}
.apple-slide .animate-fade-in {
  opacity: 0;
  transform: translateY(20px);
  transition: all 0.7s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-delay-1 { transition-delay: 0.1s !important; }
.slide-delay-2 { transition-delay: 0.2s !important; }
.slide-delay-3 { transition-delay: 0.31s !important; }
.slide-delay-4 { transition-delay: 0.42s !important; }
.slide-delay-5 { transition-delay: 0.53s !important; }
.slide-delay-6 { transition-delay: 0.64s !important; }

.pres-glass-card {
  background: rgba(255, 255, 255, 0.015);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 20px;
  padding: 1.75rem;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.pres-glass-card:hover {
  background: rgba(255, 255, 255, 0.035);
  border-color: rgba(59, 130, 246, 0.25);
  box-shadow: 0 25px 60px rgba(59, 130, 246, 0.08);
  transform: translateY(-2px);
}
.pres-glass-card-red:hover {
  border-color: rgba(239, 68, 68, 0.3) !important;
  box-shadow: 0 25px 60px rgba(239, 68, 68, 0.08) !important;
}
.pres-text-gradient-silver {
  background: linear-gradient(135deg, #ffffff 20%, #a1a1aa 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.pres-text-gradient-blue {
  background: linear-gradient(135deg, #60a5fa 20%, #1d4ed8 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.pres-text-gradient-orange {
  background: linear-gradient(135deg, #fdba74 20%, #c2410c 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.pres-text-gradient-red {
  background: linear-gradient(135deg, #fca5a5 20%, #b91c1c 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.pres-text-gradient-green {
  background: linear-gradient(135deg, #86efac 20%, #15803d 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.pres-progress-container {
  width: 100%;
  height: 8px;
  background: rgba(255, 255, 255, 0.07);
  border-radius: 4px;
  overflow: hidden;
  margin: 0.5rem 0;
}
.pres-progress-bar {
  height: 100%;
  border-radius: 4px;
  width: 0;
  transition: width 1.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
}
.pres-spectrum-bar {
  width: 100%;
  height: 16px;
  background: rgba(255, 255, 255, 0.06);
  border-radius: 8px;
  display: flex;
  overflow: hidden;
  margin: 1.25rem 0;
  border: 1px solid rgba(255, 255, 255, 0.08);
}
.pres-spectrum-segment {
  height: 100%;
  width: 0;
  transition: width 1.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
}
.pres-grid-2x4 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  grid-template-rows: repeat(2, 1fr);
  gap: 1.25rem;
  width: 100%;
  height: 100%;
}
.pres-paginator {
  display: flex;
  gap: 0.6rem;
  align-items: center;
}
.pres-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.22);
  cursor: pointer;
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.pres-dot:hover {
  background: rgba(255, 255, 255, 0.45);
}
.pres-dot.active {
  background: #3b82f6;
  width: 28px;
  border-radius: 5px;
  box-shadow: 0 0 12px rgba(59, 130, 246, 0.7);
}
.pres-nav-btn {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: rgba(255, 255, 255, 0.85);
  width: 38px;
  height: 38px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.pres-nav-btn:hover {
  background: rgba(255, 255, 255, 0.18);
  color: #ffffff;
  border-color: rgba(255, 255, 255, 0.25);
  transform: translateY(-1px);
}
.pres-nav-buttons-container {
  display: flex;
  gap: 0.5rem;
}
.pres-kpi-huge-number {
  font-family: 'Inter', sans-serif;
  font-size: 5.5rem;
  font-weight: 850;
  line-height: 1;
  letter-spacing: -0.04em;
  margin-top: 0.5rem;
}
.pres-kpi-sub-trend {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.4rem 0.85rem;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 700;
  margin-top: 0.75rem;
  border: 1px solid transparent;
}
.pres-kpi-sub-trend.pos {
  background: rgba(16, 185, 129, 0.12);
  color: #34d399;
  border-color: rgba(16, 185, 129, 0.2);
}
.pres-kpi-sub-trend.neg {
  background: rgba(239, 68, 68, 0.12);
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.2);
}
.pres-table-dense {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}
.pres-table-dense th {
  border-bottom: 1px solid rgba(255, 255, 255, 0.15);
  padding: 0.6rem 0.5rem;
  text-align: left;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.5);
  text-transform: uppercase;
  font-size: 0.72rem;
  letter-spacing: 0.05em;
}
.pres-table-dense td {
  padding: 0.5rem 0.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  color: rgba(255, 255, 255, 0.9);
}
.pres-table-dense tr:last-child td {
  border-bottom: none;
}
.pres-grid-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  width: 100%;
}
.pres-splash-accent-btn {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #ffffff !important;
  border: none;
  padding: 0.8rem 2.2rem;
  border-radius: 30px;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 8px 24px rgba(37, 99, 235, 0.3);
  margin-top: 1.5rem;
}
.pres-splash-accent-btn:hover {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 12px 32px rgba(37, 99, 235, 0.45);
}

/* Cross-device responsive refinement layer */
.db-shell {
  width: 100%;
  max-width: 1840px;
  margin-left: auto;
  margin-right: auto;
}
.db-header,
.db-brand,
.db-meta,
.area6-panel,
.chart-panel,
.digital-panel,
.rank-card,
.area6-card-premium,
.perf-panel-card,
.trend-position-card,
.perf-rka-card,
.total-composition-card {
  min-width: 0;
}
.db-brand > div:last-child {
  min-width: 0;
}
.db-title,
.db-subtitle,
.area6-title,
.area6-sub,
.dp-title,
.cp-title {
  overflow-wrap: anywhere;
}
.db-meta {
  flex-wrap: wrap;
  justify-content: flex-end;
}
.db-date-picker-select,
.db-pres-btn,
.db-ppt-btn,
.db-meta-chip {
  min-height: 38px;
}
.db-pres-btn,
.db-ppt-btn {
  white-space: nowrap;
}
.kpi-strip {
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 190px), 1fr));
}
.area6-card-grid {
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)) !important;
}
.area6-ranking-grid {
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)) !important;
}
.dp-grid {
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)) !important;
}
.cabang-performance-grid {
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 360px), 1fr));
}
.area6-scope-toggle {
  max-width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.area6-scope-btn,
.area6-pill {
  white-space: nowrap;
}
.asc-body-wrapper,
.perf-table-wrapper,
.source-modal-items {
  max-width: 100%;
}
.asc-body-wrapper,
.perf-table-wrapper {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.chart-panel canvas {
  max-width: 100%;
}

@media (min-width: 1600px) {
  .db-shell {
    max-width: 1920px;
  }
  .area6-card-grid,
  .area6-ranking-grid,
  .cabang-performance-grid {
    gap: 1.25rem;
  }
}

@media (max-width: 1199.98px) {
  .db-header {
    padding: 1rem;
  }
  .area6-panel .area6-card-grid,
  .area6-card-grid,
  .area6-ranking-grid,
  .cabang-performance-grid,
  .area6-segment-container {
    padding-left: 1rem;
    padding-right: 1rem;
  }
}

@media (max-width: 991.98px) {
  .db-header {
    align-items: flex-start;
    flex-direction: column;
  }
  .db-meta {
    justify-content: flex-start;
    width: 100%;
  }
  .db-date-picker-container,
  .db-date-picker-select {
    width: auto;
    max-width: 100%;
  }
  .main-grid {
    grid-template-columns: 1fr;
  }
  .area6-head {
    align-items: flex-start;
    flex-direction: column;
  }
  .area6-head-actions {
    align-items: flex-start;
    width: 100%;
  }
  .area6-periods {
    justify-content: flex-start;
  }
  .tcc-chart-row {
    justify-content: flex-start;
    flex-wrap: wrap;
  }
  .apple-presentation-mode {
    padding: 4.75rem 1rem 4.5rem;
  }
  .pres-top-bar,
  .pres-bottom-bar {
    left: 1rem;
    right: 1rem;
  }
  .pres-grid-2col,
  .pres-grid-2x4 {
    grid-template-columns: 1fr;
    grid-template-rows: auto;
    height: auto;
  }
  .apple-slide {
    max-height: none;
    overflow-y: auto;
    justify-content: flex-start;
    padding-bottom: 1rem;
  }
}

@media (max-width: 767.98px) {
  .db-shell {
    padding-top: 0 !important;
  }
  .db-header,
  .area6-panel,
  .chart-panel,
  .digital-panel,
  .rank-card,
  .area6-card-premium,
  .perf-panel-card,
  .trend-position-card,
  .perf-rka-card,
  .total-composition-card {
    border-radius: var(--r-lg);
  }
  .db-logo {
    width: 36px;
    height: 36px;
  }
  .db-logo img {
    width: 23px;
    height: 23px;
  }
  .db-title {
    font-size: 0.88rem;
  }
  .db-subtitle {
    font-size: 0.62rem;
  }
  .db-date-picker-container,
  .db-pres-btn,
  .db-ppt-btn,
  .db-meta-chip,
  .db-now {
    flex: 1 1 auto;
  }
  .db-date-picker-select,
  .db-pres-btn,
  .db-ppt-btn,
  .db-meta-chip {
    width: 100%;
    justify-content: center;
  }
  .kpi-card,
  .area6-card,
  .dc {
    min-height: auto;
  }
  .area6-head,
  .area6-card-grid,
  .area6-segment-container,
  .area6-ranking-grid,
  .cabang-performance-grid,
  .chart-panel,
  .digital-panel {
    padding-left: 0.85rem;
    padding-right: 0.85rem;
  }
  .area6-card-grid,
  .area6-ranking-grid,
  .cabang-performance-grid {
    gap: 0.85rem;
  }
  .ap-body,
  .perf-panel-body,
  .prc-body,
  .tcc-body {
    padding: 1rem;
  }
  .ap-grid-2,
  .ap-deltas,
  .dc-stats {
    gap: 0.45rem;
  }
  .ap-metric-val,
  .ap-metric-pct-val,
  .ap-metric-gap-val,
  .dc-val,
  .tcc-total-val {
    font-size: clamp(1rem, 7vw, 1.35rem);
  }
  .chart-panel .cp-header,
  .dp-header {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.55rem;
  }
  .chart-panel .cp-legend {
    flex-wrap: wrap;
  }
  .rank-row {
    grid-template-columns: 24px minmax(0, 1fr);
  }
  .rank-val {
    grid-column: 2;
    text-align: left;
    white-space: normal;
  }
  .source-item-card {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.75rem;
  }
  .source-item-right {
    margin-left: 0;
    text-align: left;
  }
}

@media (max-width: 575.98px) {
  .db-header {
    padding: 0.85rem;
    margin-bottom: 0.85rem;
  }
  .db-brand {
    align-items: flex-start;
  }
  .db-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.45rem;
  }
  .db-date-picker-container {
    grid-column: 1 / -1;
  }
  .db-now {
    grid-column: 1 / -1;
    font-size: 0.58rem;
  }
  .kpi-strip,
  .main-grid,
  .area6-panel,
  .digital-panel {
    margin-bottom: 0.85rem;
  }
  .kpi-card {
    padding: 0.95rem;
  }
  .kpi-card .kc-val,
  .area6-card .ac-value {
    font-size: clamp(1.15rem, 8vw, 1.45rem);
  }
  .area6-panel .area6-card-grid,
  .area6-card-grid,
  .area6-ranking-grid,
  .area6-segment-container,
  .cabang-performance-grid {
    padding: 0.75rem;
  }
  .area6-head {
    padding: 0.9rem;
  }
  .area6-scope-toggle {
    width: 100%;
  }
  .area6-scope-btn {
    flex: 1 0 auto;
    padding-left: 0.7rem;
    padding-right: 0.7rem;
  }
  .asc-header,
  .perf-panel-head {
    padding: 0.8rem 0.95rem;
  }
  .chart-panel,
  .digital-panel {
    padding: 0.95rem;
  }
  .chart-panel {
    min-height: 300px;
  }
  .chart-panel canvas {
    height: 230px !important;
  }
  .dc-stats,
  .ap-deltas {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .tcc-chart-row {
    justify-content: center;
  }
  .composition-donut {
    width: 118px;
    height: 118px;
  }
  .composition-donut::before {
    width: 76px;
    height: 76px;
  }
  .apple-presentation-mode {
    padding: 4.25rem 0.75rem 4rem;
  }
  .pres-top-bar,
  .pres-bottom-bar {
    left: 0.75rem;
    right: 0.75rem;
    gap: 0.5rem;
    flex-wrap: wrap;
  }
  .pres-controls-right {
    flex-wrap: wrap;
    justify-content: flex-end;
  }
  .pres-kpi-huge-number {
    font-size: clamp(2.5rem, 16vw, 4rem);
  }
  .pres-glass-card {
    padding: 1rem;
    border-radius: 12px;
  }
}

@media (max-width: 380px) {
  .db-meta {
    grid-template-columns: 1fr;
  }
  .db-pres-btn,
  .db-ppt-btn {
    font-size: 0.62rem;
  }
  .rank-list {
    padding-left: 0.55rem;
    padding-right: 0.55rem;
  }
}

/* Landing page tablet and constrained-width guard */
.db-shell,
.db-shell * {
  box-sizing: border-box;
}
.db-shell {
  overflow-x: clip;
}
.db-shell :where(.kpi-card, .area6-card-premium, .area6-card, .chart-panel, .digital-panel, .dc, .area6-segment-card, .total-composition-card) {
  max-width: 100%;
}
.area6-card-premium {
  min-width: 0;
}
.ap-header {
  min-width: 0;
  padding-left: 3.15rem;
  padding-right: 0.75rem;
}
.ap-header-title {
  width: 100%;
  max-width: 100%;
  line-height: 1.08;
  white-space: normal;
  overflow-wrap: break-word;
}
.ap-body,
.ap-metric-col,
.ap-delta-item,
.dc,
.dc-stat,
.dc-foot {
  min-width: 0;
}
.ap-metric-val,
.ap-metric-pct-val,
.ap-metric-gap-val,
.dc-val,
.dc-stat-val {
  max-width: 100%;
  overflow-wrap: anywhere;
}
.chart-panel canvas {
  height: 360px !important;
  min-height: 0;
}

@media (min-width: 768px) and (max-width: 1399.98px) {
  .db-shell {
    padding-left: 0;
    padding-right: 0;
  }
  .db-header,
  .landing-summary,
  .area6-panel,
  .main-grid {
    width: 100%;
    max-width: 100%;
  }
  .landing-summary-head,
  .area6-head {
    padding: 0.95rem 1rem;
  }
  .landing-summary-grid,
  .area6-panel .area6-card-grid,
  .area6-card-grid,
  .area6-segment-container,
  .area6-ranking-grid {
    padding: 1rem;
    gap: 0.9rem;
  }
  .area6-panel .area6-card-grid,
  .area6-card-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  }
  .area6-card-premium {
    border-radius: var(--r-lg);
  }
  .ap-header {
    height: 40px;
  }
  .ap-header-title {
    font-size: 0.68rem;
    letter-spacing: 0.02em;
  }
  .ap-badge {
    width: 38px;
    height: 38px;
    top: -10px;
    left: 10px;
    font-size: 0.95rem;
  }
  .ap-body {
    padding: 1rem 0.9rem 0.85rem;
  }
  .ap-metric-label,
  .ap-delta-label {
    font-size: 0.62rem;
  }
  .ap-metric-val,
  .ap-metric-pct-val,
  .ap-metric-gap-val {
    font-size: 1.12rem;
    letter-spacing: 0;
  }
  .ap-metric-sub {
    font-size: 0.58rem;
  }
  .ap-delta-val {
    font-size: 0.68rem;
  }
  .ap-delta-arrow {
    font-size: 0.8rem;
  }
  .main-grid {
    grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.25fr);
    gap: 0.9rem;
  }
  .chart-panel,
  .digital-panel {
    padding: 1rem;
    border-radius: var(--r-lg);
  }
  .chart-panel {
    min-height: 0;
  }
  .chart-panel canvas {
    height: 340px !important;
  }
  .dp-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 0.75rem;
  }
  .dc {
    min-height: 0;
    padding: 0.9rem;
  }
  .dc-val {
    font-size: 1.35rem;
    letter-spacing: 0;
  }
  .dc-stats {
    gap: 0.45rem;
  }
  .dc-stat {
    padding: 0.5rem;
  }
}

@media (min-width: 768px) and (max-width: 1100px) {
  .area6-panel .area6-card-grid,
  .area6-card-grid,
  .main-grid {
    grid-template-columns: 1fr !important;
  }
  .ap-header-title {
    font-size: 0.72rem;
  }
  .chart-panel canvas {
    height: 300px !important;
  }
}

@media (max-width: 767.98px) {
  .db-shell {
    overflow-x: hidden;
  }
  .landing-summary-grid,
  .area6-panel .area6-card-grid,
  .area6-card-grid,
  .area6-segment-container,
  .area6-ranking-grid,
  .main-grid,
  .dp-grid {
    width: 100%;
    max-width: 100%;
    grid-template-columns: 1fr !important;
  }
  .ap-header {
    height: auto;
    min-height: 38px;
    padding-top: 0.45rem;
    padding-bottom: 0.45rem;
  }
  .ap-header-title {
    font-size: 0.66rem;
    letter-spacing: 0.02em;
  }
  .ap-body {
    padding: 0.9rem 0.85rem 0.8rem;
  }
  .ap-metric-val,
  .ap-metric-pct-val,
  .ap-metric-gap-val,
  .dc-val {
    font-size: 1.18rem;
    letter-spacing: 0;
  }
  .chart-panel canvas {
    height: 240px !important;
  }
}

/* Container-aware landing system. JS toggles these classes from the real content width. */
.db-shell.landing-compact {
  max-width: 100% !important;
}
.db-shell.landing-compact .db-header,
.db-shell.landing-compact .landing-summary,
.db-shell.landing-compact .area6-panel,
.db-shell.landing-compact .main-grid {
  border-radius: var(--r-lg);
}
.db-shell.landing-compact .kpi-strip {
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
  gap: 0.65rem;
}
.db-shell.landing-compact .kpi-card {
  min-height: 92px;
  padding: 0.85rem 0.95rem;
}
.db-shell.landing-compact .area6-head {
  align-items: flex-start;
  flex-direction: column;
  gap: 0.75rem;
}
.db-shell.landing-compact .area6-head-actions,
.db-shell.landing-compact .area6-periods {
  align-items: flex-start;
  justify-content: flex-start;
  width: 100%;
}
.db-shell.landing-compact .area6-scope-toggle {
  max-width: 100%;
}
.db-shell.landing-compact .area6-panel .area6-card-grid,
.db-shell.landing-compact .area6-card-grid {
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)) !important;
  gap: 0.85rem !important;
  padding: 0.95rem !important;
}
.db-shell.landing-compact .area6-card-premium {
  min-height: 0;
  overflow: hidden;
  border-radius: var(--r-lg);
}
.db-shell.landing-compact .ap-header {
  min-height: 38px;
  height: auto;
  padding: 0.48rem 0.7rem 0.48rem 3rem;
}
.db-shell.landing-compact .ap-header-title {
  font-size: 0.78rem !important;
  line-height: 1.14 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-compact .ap-badge {
  width: 36px;
  height: 36px;
  top: -8px;
  left: 10px;
  border-width: 2px;
  font-size: 0.9rem;
}
.db-shell.landing-compact .ap-body {
  padding: 0.9rem 0.85rem 0.8rem !important;
}
.db-shell.landing-compact .ap-grid-2 {
  gap: 0.25rem;
}
.db-shell.landing-compact .ap-metric-col {
  padding: 0.35rem 0.2rem;
}
.db-shell.landing-compact .ap-metric-label,
.db-shell.landing-compact .ap-delta-label {
  font-size: 0.6rem !important;
  line-height: 1.2;
}
.db-shell.landing-compact .ap-metric-val,
.db-shell.landing-compact .ap-metric-pct-val,
.db-shell.landing-compact .ap-metric-gap-val {
  font-size: 1.08rem !important;
  line-height: 1.12 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-compact .ap-metric-sub {
  font-size: 0.56rem !important;
}
.db-shell.landing-compact .ap-dashed-divider {
  margin: 0.55rem 0;
}
.db-shell.landing-compact .ap-deltas {
  gap: 0.3rem;
}
.db-shell.landing-compact .ap-delta-val {
  font-size: 0.66rem !important;
}
.db-shell.landing-compact .ap-delta-arrow {
  font-size: 0.78rem !important;
}
.db-shell.landing-compact .area6-segment-container {
  grid-template-columns: 1fr !important;
  padding: 0 0.95rem 0.95rem !important;
}
.db-shell.landing-compact .main-grid {
  grid-template-columns: 1fr !important;
  gap: 0.9rem;
}
.db-shell.landing-compact .chart-panel {
  min-height: 0 !important;
}
.db-shell.landing-compact .chart-panel canvas {
  height: 280px !important;
}
.db-shell.landing-compact .dp-grid {
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) !important;
  gap: 0.7rem;
}
.db-shell.landing-compact .dc {
  min-height: 0 !important;
  padding: 0.85rem !important;
}
.db-shell.landing-compact .dc-val {
  font-size: 1.25rem !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-compact .dc-stats {
  gap: 0.4rem;
}
.db-shell.landing-compact .dc-stat {
  min-width: 0;
  padding: 0.45rem !important;
}
.db-shell.landing-narrow .kpi-strip,
.db-shell.landing-narrow .landing-summary-grid,
.db-shell.landing-narrow .area6-panel .area6-card-grid,
.db-shell.landing-narrow .area6-card-grid,
.db-shell.landing-narrow .dp-grid,
.db-shell.landing-narrow .main-grid {
  grid-template-columns: 1fr !important;
}
.db-shell.landing-narrow .db-header,
.db-shell.landing-narrow .landing-summary-head,
.db-shell.landing-narrow .area6-head,
.db-shell.landing-narrow .chart-panel,
.db-shell.landing-narrow .digital-panel {
  padding-left: 0.8rem !important;
  padding-right: 0.8rem !important;
}
.db-shell.landing-narrow .chart-panel canvas {
  height: 220px !important;
}
.db-shell.landing-narrow .dc-stats,
.db-shell.landing-narrow .ap-deltas {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
}

/* Final landing density system: desktop / tablet / mobile / short viewport */
body.dashboard-landing-page .content-wrapper .container-fluid {
  max-width: 100%;
}
.db-shell.landing-tablet,
.db-shell.landing-mobile,
.db-shell.landing-short {
  --landing-space: 0.85rem;
  --landing-card-radius: 12px;
  --landing-panel-pad: 0.95rem;
}
.db-shell.landing-tablet .db-header,
.db-shell.landing-mobile .db-header,
.db-shell.landing-short .db-header {
  padding: 0.9rem 1rem !important;
  margin-bottom: 0.85rem !important;
  border-radius: var(--landing-card-radius) !important;
}
.db-shell.landing-tablet .kpi-strip,
.db-shell.landing-mobile .kpi-strip,
.db-shell.landing-short .kpi-strip {
  gap: 0.65rem !important;
  margin-bottom: 0.75rem !important;
}
.db-shell.landing-tablet .kpi-strip {
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
}
.db-shell.landing-mobile .kpi-strip {
  grid-template-columns: 1fr !important;
}
.db-shell.landing-tablet .kpi-card,
.db-shell.landing-mobile .kpi-card,
.db-shell.landing-short .kpi-card {
  min-height: 0 !important;
  padding: 0.8rem 0.9rem !important;
}
.db-shell.landing-tablet .kpi-card .kc-label,
.db-shell.landing-mobile .kpi-card .kc-label,
.db-shell.landing-short .kpi-card .kc-label {
  font-size: 0.58rem !important;
  letter-spacing: 0.035em !important;
}
.db-shell.landing-tablet .kpi-card .kc-val,
.db-shell.landing-mobile .kpi-card .kc-val,
.db-shell.landing-short .kpi-card .kc-val {
  font-size: 1.05rem !important;
  line-height: 1.12 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-tablet .landing-summary,
.db-shell.landing-tablet .area6-panel,
.db-shell.landing-tablet .chart-panel,
.db-shell.landing-tablet .digital-panel,
.db-shell.landing-mobile .landing-summary,
.db-shell.landing-mobile .area6-panel,
.db-shell.landing-mobile .chart-panel,
.db-shell.landing-mobile .digital-panel,
.db-shell.landing-short .landing-summary,
.db-shell.landing-short .area6-panel,
.db-shell.landing-short .chart-panel,
.db-shell.landing-short .digital-panel {
  margin: 0.8rem 0 !important;
  border-radius: var(--landing-card-radius) !important;
}
.db-shell.landing-tablet .landing-summary-head,
.db-shell.landing-tablet .area6-head,
.db-shell.landing-mobile .landing-summary-head,
.db-shell.landing-mobile .area6-head,
.db-shell.landing-short .landing-summary-head,
.db-shell.landing-short .area6-head {
  padding: 0.85rem var(--landing-panel-pad) !important;
  gap: 0.65rem !important;
}
.db-shell.landing-tablet .landing-summary-title,
.db-shell.landing-tablet .area6-title,
.db-shell.landing-tablet .cp-title,
.db-shell.landing-tablet .dp-title,
.db-shell.landing-mobile .landing-summary-title,
.db-shell.landing-mobile .area6-title,
.db-shell.landing-mobile .cp-title,
.db-shell.landing-mobile .dp-title,
.db-shell.landing-short .landing-summary-title,
.db-shell.landing-short .area6-title,
.db-shell.landing-short .cp-title,
.db-shell.landing-short .dp-title {
  font-size: 0.98rem !important;
  line-height: 1.18 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-tablet .area6-scope-toggle,
.db-shell.landing-mobile .area6-scope-toggle,
.db-shell.landing-short .area6-scope-toggle {
  display: flex !important;
  flex-wrap: wrap !important;
  width: 100% !important;
  border-radius: 12px !important;
  overflow: visible !important;
}
.db-shell.landing-tablet .area6-scope-btn,
.db-shell.landing-mobile .area6-scope-btn,
.db-shell.landing-short .area6-scope-btn {
  flex: 1 1 8rem !important;
  min-height: 32px !important;
  padding: 0.34rem 0.58rem !important;
  text-align: center !important;
}
.db-shell.landing-tablet .area6-periods,
.db-shell.landing-mobile .area6-periods,
.db-shell.landing-short .area6-periods {
  display: grid !important;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
  width: 100% !important;
  gap: 0.38rem !important;
}
.db-shell.landing-tablet .area6-pill,
.db-shell.landing-mobile .area6-pill,
.db-shell.landing-short .area6-pill {
  min-width: 0 !important;
  justify-content: center !important;
  white-space: normal !important;
  text-align: center !important;
  border-radius: 10px !important;
  font-size: 0.6rem !important;
}
.db-shell.landing-tablet .area6-panel .area6-card-grid,
.db-shell.landing-tablet .area6-card-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  gap: 0.8rem !important;
  padding: var(--landing-panel-pad) !important;
}
.db-shell.landing-mobile .area6-panel .area6-card-grid,
.db-shell.landing-mobile .area6-card-grid {
  grid-template-columns: 1fr !important;
  gap: 0.7rem !important;
  padding: 0.75rem !important;
}
.db-shell.landing-short .area6-panel .area6-card-grid,
.db-shell.landing-short .area6-card-grid {
  gap: 0.7rem !important;
}
.db-shell.landing-tablet .area6-card-premium,
.db-shell.landing-mobile .area6-card-premium,
.db-shell.landing-short .area6-card-premium {
  overflow: hidden !important;
  min-height: 0 !important;
  border-radius: var(--landing-card-radius) !important;
  box-shadow: var(--shadow-sm) !important;
}
.db-shell.landing-tablet .area6-card-premium:hover,
.db-shell.landing-mobile .area6-card-premium:hover,
.db-shell.landing-short .area6-card-premium:hover {
  transform: none !important;
}
.db-shell.landing-tablet .ap-badge,
.db-shell.landing-mobile .ap-badge,
.db-shell.landing-short .ap-badge {
  width: 30px !important;
  height: 30px !important;
  top: 7px !important;
  left: 8px !important;
  border-width: 0 !important;
  box-shadow: none !important;
  font-size: 0.78rem !important;
}
.db-shell.landing-tablet .ap-header,
.db-shell.landing-mobile .ap-header,
.db-shell.landing-short .ap-header {
  min-height: 44px !important;
  height: auto !important;
  justify-content: flex-start !important;
  padding: 0.52rem 0.65rem 0.52rem 2.85rem !important;
  border-radius: 0 !important;
}
.db-shell.landing-tablet .ap-header-title,
.db-shell.landing-mobile .ap-header-title,
.db-shell.landing-short .ap-header-title {
  font-size: 0.82rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
  text-align: left !important;
  text-wrap: balance;
}
.db-shell.landing-tablet .ap-body,
.db-shell.landing-mobile .ap-body,
.db-shell.landing-short .ap-body {
  padding: 0.75rem !important;
}
.db-shell.landing-tablet .ap-grid-2,
.db-shell.landing-mobile .ap-grid-2,
.db-shell.landing-short .ap-grid-2 {
  gap: 0.2rem !important;
}
.db-shell.landing-tablet .ap-metric-col,
.db-shell.landing-mobile .ap-metric-col,
.db-shell.landing-short .ap-metric-col {
  padding: 0.3rem 0.15rem !important;
}
.db-shell.landing-tablet .ap-metric-label,
.db-shell.landing-tablet .ap-delta-label,
.db-shell.landing-mobile .ap-metric-label,
.db-shell.landing-mobile .ap-delta-label,
.db-shell.landing-short .ap-metric-label,
.db-shell.landing-short .ap-delta-label {
  font-size: 0.58rem !important;
  line-height: 1.18 !important;
}
.db-shell.landing-tablet .ap-metric-val,
.db-shell.landing-tablet .ap-metric-pct-val,
.db-shell.landing-tablet .ap-metric-gap-val,
.db-shell.landing-mobile .ap-metric-val,
.db-shell.landing-mobile .ap-metric-pct-val,
.db-shell.landing-mobile .ap-metric-gap-val,
.db-shell.landing-short .ap-metric-val,
.db-shell.landing-short .ap-metric-pct-val,
.db-shell.landing-short .ap-metric-gap-val {
  font-size: 0.98rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-tablet .ap-metric-sub,
.db-shell.landing-mobile .ap-metric-sub,
.db-shell.landing-short .ap-metric-sub {
  font-size: 0.54rem !important;
}
.db-shell.landing-tablet .ap-deltas,
.db-shell.landing-short .ap-deltas {
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  gap: 0.24rem !important;
}
.db-shell.landing-mobile .ap-deltas {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
}
.db-shell.landing-tablet .ap-delta-val,
.db-shell.landing-mobile .ap-delta-val,
.db-shell.landing-short .ap-delta-val {
  font-size: 0.62rem !important;
}
.db-shell.landing-tablet .main-grid,
.db-shell.landing-mobile .main-grid,
.db-shell.landing-short .main-grid {
  grid-template-columns: 1fr !important;
  gap: 0.8rem !important;
}
.db-shell.landing-tablet .chart-panel,
.db-shell.landing-tablet .digital-panel,
.db-shell.landing-mobile .chart-panel,
.db-shell.landing-mobile .digital-panel,
.db-shell.landing-short .chart-panel,
.db-shell.landing-short .digital-panel {
  padding: var(--landing-panel-pad) !important;
}
.db-shell.landing-tablet .chart-panel canvas {
  height: 260px !important;
}
.db-shell.landing-mobile .chart-panel canvas,
.db-shell.landing-short .chart-panel canvas {
  height: 210px !important;
}
.db-shell.landing-tablet .dp-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  gap: 0.7rem !important;
}
.db-shell.landing-mobile .dp-grid {
  grid-template-columns: 1fr !important;
}
.db-shell.landing-tablet .dc,
.db-shell.landing-mobile .dc,
.db-shell.landing-short .dc {
  min-height: 0 !important;
  padding: 0.75rem !important;
  border-radius: var(--landing-card-radius) !important;
}
.db-shell.landing-tablet .dc-val,
.db-shell.landing-mobile .dc-val,
.db-shell.landing-short .dc-val {
  font-size: 1.15rem !important;
  line-height: 1.12 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-tablet .dc-stats,
.db-shell.landing-mobile .dc-stats,
.db-shell.landing-short .dc-stats {
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  gap: 0.38rem !important;
}
.db-shell.landing-mobile .dc-stats {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
}
.db-shell.landing-tablet .dc-stat,
.db-shell.landing-mobile .dc-stat,
.db-shell.landing-short .dc-stat {
  padding: 0.42rem !important;
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
      @if(!empty($periods) && count($periods) > 0)
      <div class="db-date-picker-container">
        <select class="db-date-picker-select" id="periode-selector">
          @foreach($periods as $p)
            <option value="{{ $p }}" {{ $p === $selectedPeriod ? 'selected' : '' }}>
              {{ \Carbon\Carbon::parse($p)->translatedFormat('d M Y') }}
            </option>
          @endforeach
        </select>
        <i class="fas fa-calendar-alt db-date-picker-icon"></i>
      </div>
      @endif

      <button type="button" class="db-pres-btn mr-2" id="enter-presentation-btn">
        <i class="fas fa-desktop"></i> Mode Presentasi
      </button>

      <button type="button" class="db-ppt-btn" id="export-ppt-btn">
        <i class="fas fa-file-powerpoint"></i> Unduh PPT
      </button>

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
        {{ data_get($simpananReport,'trend','0%') }} MtM
      </span>
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($simpananReport,"detail_payload",[]))' data-link="{{ data_get($simpananReport,'link','#') }}" data-link-label="{{ data_get($simpananReport,'link_label','Buka report') }}">Detail <i class="fas fa-info-circle"></i></button>
    </div>

    {{-- PINJAMAN --}}
    <div class="kpi-card pinjaman">
      <div class="kc-live"></div>
      <div class="kc-label"><i class="fas fa-hand-holding-usd mr-1"></i>OS</div>
      <div class="kc-val">{{ data_get($pinjamanReport,'value','–') }}</div>
      <div class="kc-sub">{{ data_get($pinjamanReport,'meta','–') }}</div>
      @php $pm = (float)str_replace(['+','%',','],['','','.'],data_get($pinjamanReport,'trend','0')); @endphp
      <span class="kc-delta {{ $pm>=0?'pos':'neg' }}">
        <i class="fas {{ $pm>=0?'fa-arrow-up':'fa-arrow-down' }}"></i>
        {{ data_get($pinjamanReport,'trend','0%') }} MtM
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
    @php $m6 = collect($digitalCards)->firstWhere('key', 'casa'); @endphp
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
        <div class="area6-title">{{ !empty($userBranchScope) ? 'Kinerja ' . $userBranchScope['label'] : data_get($area6Portfolio, 'title', 'Ringkasan Area 6') }}</div>
        <div class="area6-sub">{{ !empty($userBranchScope) ? 'Ringkasan posisi dan kinerja ' . $userBranchScope['label'] . '.' : data_get($area6Portfolio, 'subtitle', 'Ringkasan lintas report Area 6.') }}</div>
      </div>
      <div class="area6-head-actions">
        @if(!empty($area6RankingModes) && empty($userBranchScope))
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

    @foreach($area6ScopePayloads as $contentScopeKey => $contentPortfolio)
    @php
      $contentCards = is_array(data_get($contentPortfolio, 'cards')) ? data_get($contentPortfolio, 'cards') : [];
    @endphp
    <div class="area6-scope-content {{ $contentScopeKey === $area6DefaultScope ? '' : 'd-none' }}" data-area6-content-scope="{{ $contentScopeKey }}">
    <div class="area6-card-grid">
      @forelse($contentCards as $card)
        @if(in_array(data_get($card, 'key'), ['os', 'sml', 'npl']))
          @php
            $key = data_get($card, 'key');
            $pctColor = data_get($card, 'pct_color');
            $gapColor = data_get($card, 'gap_color');
            $deltas = data_get($card, 'deltas', []);
          @endphp
          <button type="button"
                  class="area6-card-premium dashboard-detail-trigger"
                  data-detail='@json(data_get($card, "detail_payload", []))'
                  data-link="{{ data_get($card, 'link', '#') }}"
                  data-link-label="{{ data_get($card, 'link_label', 'Lihat detail') }}">
            
            <!-- Floating Badge -->
            <div class="ap-badge bg-{{ $key }}">
              <i class="{{ data_get($card, 'icon') }}"></i>
            </div>
            
            <!-- Header Banner -->
            <div class="ap-header bg-{{ $key }}">
              <div class="ap-header-title">{{ data_get($card, 'header_title') }}</div>
            </div>
            
            <!-- Card Body -->
            <div class="ap-body">
              <!-- Row 1: Realization vs Target -->
              <div class="ap-grid-2 mb-2">
                <div class="ap-metric-col">
                  <div class="ap-metric-label">{{ data_get($card, 'realization_label') }}</div>
                  <div class="ap-metric-val">{{ data_get($card, 'realization_value') }}</div>
                  <div class="ap-metric-sub">Rp Juta</div>
                </div>
                <div class="ap-metric-col">
                  <div class="ap-metric-label">{{ data_get($card, 'target_label') }}</div>
                  <div class="ap-metric-val">{{ data_get($card, 'target_value') }}</div>
                  <div class="ap-metric-sub">Rp Juta</div>
                </div>
              </div>
              
              <!-- Row 2: Achievement % vs Gap -->
              <div class="ap-grid-2">
                <div class="ap-metric-col">
                  <div class="ap-metric-label">{{ data_get($card, 'pct_label') }}</div>
                  <div class="ap-metric-pct-val text-{{ $pctColor }}-flat">{{ data_get($card, 'pct_value') }}</div>
                </div>
                <div class="ap-metric-col">
                  <div class="ap-metric-label">{{ data_get($card, 'gap_label') }}</div>
                  <div class="ap-metric-gap-val text-{{ $gapColor }}-flat">{{ data_get($card, 'gap_value') }}</div>
                  <div class="ap-metric-sub">Rp Juta</div>
                </div>
              </div>
              
              <!-- Dashed Divider -->
              <hr class="ap-dashed-divider">
              
              <!-- Row 3: Deltas -->
              <div class="ap-deltas">
                @foreach(['dtd' => 'DtD', 'mtd' => 'MtD', 'mom' => 'MtM', 'ytd' => 'YtD'] as $dKey => $dLabel)
                  @php $delta = data_get($deltas, $dKey, []); @endphp
                  <div class="ap-delta-item">
                    <div class="ap-delta-label">{{ $dLabel }}</div>
                    <div class="ap-delta-val text-{{ data_get($delta, 'color', 'red') }}-flat">
                      {{ data_get($delta, 'value', '-') }}
                    </div>
                    <div class="ap-delta-arrow text-{{ data_get($delta, 'color', 'red') }}-flat">
                      <i class="fas fa-arrow-{{ data_get($delta, 'type', 'up') }}"></i>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
            
          </button>
        @elseif(data_get($card, 'key') !== 'casa')
          <!-- Fallback/Legacy styling if other non-casa cards are returned -->
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
        @endif
      @empty
        @if(empty($area6Cards))
        {{-- AJAX skeleton loader for area6 when cache is cold --}}
        <div id="area6-loading-overlay">
          <div class="a6-skel-title">⏳ Memuat Ringkasan Area 6...</div>
          <div style="display:flex; align-items:center; gap:0.6rem; width:min(380px,90%)">
            <div id="area6-progress-track" style="flex:1">
              <div id="area6-progress-fill"></div>
            </div>
            <div id="area6-progress-pct">0%</div>
          </div>
          <div id="area6-loading-status">Menghubungkan ke server...</div>
          <div class="a6-skel-card-grid">
            <div class="a6-skel-card"></div>
            <div class="a6-skel-card"></div>
            <div class="a6-skel-card"></div>
          </div>
        </div>
        @else
        <div class="rank-empty">Ringkasan Area 6 belum tersedia.</div>
        @endif
      @endforelse
    </div>

    {{-- KINERJA PER SEGMEN CARD --}}
    @php
      $segmentPerf = data_get($contentPortfolio, 'segment_performance');
      $segments = data_get($segmentPerf, 'segments', []);
      $totalPerf = data_get($segmentPerf, 'total', []);
      $rkaMonthYear = data_get($segmentPerf, 'rka_month_year', 'Mei 26');
    @endphp

    @if(!empty($segments))
    <div class="area6-segment-container">
      {{-- KINERJA PER SEGMEN --}}
      <div class="area6-segment-card">
        <!-- Card Header -->
        <div class="asc-header">
          <div class="asc-header-icon">
            <i class="fas fa-chart-bar"></i>
          </div>
          <div class="asc-header-title">KINERJA PER SEGMEN (Rp Juta)</div>
        </div>
        
        <!-- Card Body / Content with horizontal scrolling wrapper for responsiveness -->
        <div class="asc-body-wrapper">
          <div class="asc-body">
            <!-- Main Column Headers -->
            <div class="asc-grid-cols">
              <div class="asc-col-spacer"></div>
              
              <!-- OS Column Header -->
              <div class="asc-col-header">
                <div class="asc-col-title">OUTSTANDING (OS)</div>
                <div class="asc-col-legend">
                  <span class="legend-item"><span class="legend-box bg-os-blue"></span>Pencapaian</span>
                  <span class="legend-item"><span class="legend-box bg-gray"></span>RKA {{ $rkaMonthYear }}</span>
                  <span class="legend-item-pct">% Penc.</span>
                </div>
              </div>
              
              <!-- SML Column Header -->
              <div class="asc-col-header">
                <div class="asc-col-title">SPECIAL MENTION LOAN (SML)</div>
                <div class="asc-col-legend">
                  <span class="legend-item"><span class="legend-box bg-sml-blue"></span>Pencapaian</span>
                  <span class="legend-item"><span class="legend-box bg-gray"></span>RKA {{ $rkaMonthYear }}</span>
                  <span class="legend-item-pct">% Penc.</span>
                </div>
              </div>
              
              <!-- NPL Column Header -->
              <div class="asc-col-header">
                <div class="asc-col-title">NON-PERFORMING LOAN (NPL)</div>
                <div class="asc-col-legend">
                  <span class="legend-item"><span class="legend-box bg-npl-blue"></span>Pencapaian</span>
                  <span class="legend-item"><span class="legend-box bg-gray"></span>RKA {{ $rkaMonthYear }}</span>
                  <span class="legend-item-pct">% Penc.</span>
                </div>
              </div>
            </div>
            
            <!-- Rows for Segments -->
            @foreach($segments as $seg)
            <div class="asc-row">
              <!-- Segment Name & Icon -->
              <div class="asc-seg-info">
                <div class="asc-seg-icon">
                  <i class="{{ data_get($seg, 'icon') }}"></i>
                </div>
                <div class="asc-seg-name">{{ data_get($seg, 'label') }}</div>
              </div>
              
              <!-- OS Metric Group -->
              @php $os = data_get($seg, 'os', []); @endphp
              <div class="asc-metric-group">
                <div class="asc-bar-container">
                  <div class="asc-bar bg-os-blue" style="width: {{ data_get($os, 'penc_bar_width', 0) }}%;"></div>
                  <div class="asc-bar bg-gray" style="width: {{ data_get($os, 'rka_bar_width', 0) }}%;"></div>
                </div>
                <div class="asc-value">{{ data_get($os, 'realization_fmt', '–') }}</div>
                <div class="asc-pct text-{{ data_get($os, 'pct_color', 'muted') }}-flat">{{ data_get($os, 'pct_fmt', '–') }}</div>
              </div>
              
              <!-- SML Metric Group -->
              @php $sml = data_get($seg, 'sml', []); @endphp
              <div class="asc-metric-group">
                <div class="asc-bar-container">
                  <div class="asc-bar bg-sml-blue" style="width: {{ data_get($sml, 'penc_bar_width', 0) }}%;"></div>
                  <div class="asc-bar bg-gray" style="width: {{ data_get($sml, 'rka_bar_width', 0) }}%;"></div>
                </div>
                <div class="asc-value">{{ data_get($sml, 'realization_fmt', '–') }}</div>
                <div class="asc-pct text-{{ data_get($sml, 'pct_color', 'muted') }}-flat">{{ data_get($sml, 'pct_fmt', '–') }}</div>
              </div>
              
              <!-- NPL Metric Group -->
              @php $npl = data_get($seg, 'npl', []); @endphp
              <div class="asc-metric-group">
                <div class="asc-bar-container">
                  <div class="asc-bar bg-npl-blue" style="width: {{ data_get($npl, 'penc_bar_width', 0) }}%;"></div>
                  <div class="asc-bar bg-gray" style="width: {{ data_get($npl, 'rka_bar_width', 0) }}%;"></div>
                </div>
                <div class="asc-value">{{ data_get($npl, 'realization_fmt', '–') }}</div>
                <div class="asc-pct text-{{ data_get($npl, 'pct_color', 'muted') }}-flat">{{ data_get($npl, 'pct_fmt', '–') }}</div>
              </div>
            </div>
            @endforeach
            
            <!-- Total Row -->
            @if(!empty($totalPerf))
            <div class="asc-row asc-total-row">
              <div class="asc-seg-info">
                <div class="asc-seg-name">TOTAL</div>
              </div>
              
              <!-- Total OS -->
              @php $totOs = data_get($totalPerf, 'os', []); @endphp
              <div class="asc-metric-group">
                <div class="asc-value font-weight-bold" style="flex: 1 1 0; text-align: right; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;">{{ data_get($totOs, 'realization_fmt', '–') }}</div>
                <div class="asc-target-total">{{ data_get($totOs, 'target_fmt', '–') }}</div>
                <div class="asc-pct text-{{ data_get($totOs, 'pct_color', 'muted') }}-flat">{{ data_get($totOs, 'pct_fmt', '–') }}</div>
              </div>
              
              <!-- Total SML -->
              @php $totSml = data_get($totalPerf, 'sml', []); @endphp
              <div class="asc-metric-group">
                <div class="asc-value font-weight-bold" style="flex: 1 1 0; text-align: right; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;">{{ data_get($totSml, 'realization_fmt', '–') }}</div>
                <div class="asc-target-total">{{ data_get($totSml, 'target_fmt', '–') }}</div>
                <div class="asc-pct text-{{ data_get($totSml, 'pct_color', 'muted') }}-flat">{{ data_get($totSml, 'pct_fmt', '–') }}</div>
              </div>
              
              <!-- Total NPL -->
              @php $totNpl = data_get($totalPerf, 'npl', []); @endphp
              <div class="asc-metric-group">
                <div class="asc-value font-weight-bold" style="flex: 1 1 0; text-align: right; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;">{{ data_get($totNpl, 'realization_fmt', '–') }}</div>
                <div class="asc-target-total">{{ data_get($totNpl, 'target_fmt', '–') }}</div>
                <div class="asc-pct text-{{ data_get($totNpl, 'pct_color', 'muted') }}-flat">{{ data_get($totNpl, 'pct_fmt', '–') }}</div>
              </div>
            </div>
            @endif
          </div>
        </div>
      </div>

      {{-- KOMPOSISI TOTAL --}}
      @php
        $composition = data_get($segmentPerf, 'composition', []);
        $osComp = data_get($composition, 'os', []);
        $smlComp = data_get($composition, 'sml', []);
        $nplComp = data_get($composition, 'npl', []);
        $totalComp = data_get($composition, 'total', []);
        
        $osRaw = (float) data_get($osComp, 'raw_pct', 0.0);
        $smlRaw = (float) data_get($smlComp, 'raw_pct', 0.0);
        $nplRaw = (float) data_get($nplComp, 'raw_pct', 0.0);
        
        $healthyAngle = (float) data_get($composition, 'angles.healthy', 100.0 - $osRaw);
        $lrAngle = (float) data_get($composition, 'angles.lr', 100.0 - $smlRaw - $nplRaw);
        $smlAngleNew = (float) data_get($composition, 'angles.sml', 100.0 - $nplRaw);
      @endphp
      <div class="total-composition-card">
        <!-- Card Header -->
        <div class="asc-header">
          <div class="asc-header-icon">
            <i class="fas fa-chart-pie"></i>
          </div>
          <div class="asc-header-title">KOMPOSISI TOTAL (Rp Juta)</div>
        </div>
        
        <!-- Card Body -->
        <div class="tcc-body">
          <div class="tcc-chart-row">
            <!-- Conic-gradient Donut Chart -->
            <div class="composition-donut" style="background: conic-gradient(#0f4cba 0% {{ $healthyAngle }}%, #a855f7 {{ $healthyAngle }}% {{ $lrAngle }}%, #1e72e8 {{ $lrAngle }}% {{ $smlAngleNew }}%, #ef4444 {{ $smlAngleNew }}% 100%);">
              <div class="donut-center">
                <span class="donut-center-pct">{{ data_get($composition, 'center.pct', data_get($osComp, 'pct', '0,00%')) }}</span>
              </div>
            </div>
            
            <!-- Legends -->
            <div class="tcc-legends">
              <!-- OS Legend -->
              <div class="tcc-legend-item">
                <div class="tcc-legend-dot bg-os"></div>
                <div class="tcc-legend-info">
                  <span class="tcc-legend-name">{{ data_get($osComp, 'name', 'LAR') }}</span>
                  <span class="tcc-legend-val">{{ data_get($osComp, 'value', '–') }}</span>
                  <span class="tcc-legend-pct">({{ data_get($osComp, 'pct', '–') }})</span>
                </div>
              </div>
              
              <!-- SML Legend -->
              <div class="tcc-legend-item">
                <div class="tcc-legend-dot bg-sml"></div>
                <div class="tcc-legend-info">
                  <span class="tcc-legend-name">SML</span>
                  <span class="tcc-legend-val">{{ data_get($smlComp, 'value', '–') }}</span>
                  <span class="tcc-legend-pct">({{ data_get($smlComp, 'pct', '–') }})</span>
                </div>
              </div>
              
              <!-- NPL Legend -->
              <div class="tcc-legend-item">
                <div class="tcc-legend-dot bg-npl"></div>
                <div class="tcc-legend-info">
                  <span class="tcc-legend-name">NPL</span>
                  <span class="tcc-legend-val">{{ data_get($nplComp, 'value', '–') }}</span>
                  <span class="tcc-legend-pct">({{ data_get($nplComp, 'pct', '–') }})</span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Total Badge -->
          <div class="tcc-total-badge">
            <span class="tcc-total-label">TOTAL PORTOFOLIO KREDIT</span>
            <span class="tcc-total-val">{{ data_get($totalComp, 'value', '–') }} <span style="font-size: 0.75rem; font-weight: 700;">Rp Juta</span></span>
          </div>
        </div>
      </div>
    </div>
    @endif

    {{-- TREND POSISI & PERFORMANCE VS RKA DOUBLE CARDS ROW ── --}}
    @php
      $overallTrends = data_get($contentPortfolio, 'overall_trends');
      $trendDates = data_get($overallTrends, 'dates', []);
      $osTrend = data_get($overallTrends, 'os', []);
      $smlTrend = data_get($overallTrends, 'sml', []);
      $nplTrend = data_get($overallTrends, 'npl', []);
    @endphp

    @if(!empty($overallTrends))
    <div class="area6-segment-container mt-4">
      {{-- TREND POSISI CARD --}}
      <div class="trend-position-card">
        <div class="asc-header">
          <div class="asc-header-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <div class="asc-header-title">TREND POSISI (Rp Juta)</div>
        </div>
        <div class="tpc-body">
          {{-- Column 1: OS --}}
          <div class="trend-col">
            <div class="trend-col-title text-os-blue">OUTSTANDING (OS)</div>
            <div class="trend-chart-wrapper">
              <svg viewBox="0 0 110 50">
                <!-- Trend line path -->
                @if(data_get($osTrend, 'path'))
                  <path d="{{ data_get($osTrend, 'path') }}" fill="none" stroke="#0f4cba" stroke-width="1.8" />
                @endif
                <!-- Trend points and text labels -->
                @foreach(data_get($osTrend, 'points', []) as $idx => $pt)
                  <!-- Value Text Above Point -->
                  <text x="{{ $pt['x'] }}" y="{{ $pt['y'] - 6 }}" text-anchor="middle" font-size="3.8" font-weight="bold" fill="#0f4cba" font-family="inherit">{{ $pt['val_fmt'] }}</text>
                  <!-- Circle Dot -->
                  <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="2.8" fill="#0f4cba" stroke="#ffffff" stroke-width="1" />
                @endforeach
              </svg>
            </div>
            <!-- Date Labels below SVGs -->
            <div class="trend-dates-row">
              @foreach($trendDates as $d)
                @php
                  $parts = explode(' (', str_replace(')', '', $d));
                  $prefix = $parts[0] ?? '';
                  $datePart = $parts[1] ?? '';
                  $datePartClean = str_replace(['/', ' '], '-', $datePart);
                  $dateParts = explode('-', $datePartClean);
                  if (count($dateParts) === 3) {
                      $dayMonth = $dateParts[0] . ' ' . $dateParts[1];
                      $year = "'" . $dateParts[2];
                  } else {
                      $dayMonth = $datePart;
                      $year = '';
                  }
                @endphp
                <span class="trend-date-label">
                  <span class="date-part" style="font-weight: 700; color: #1e293b;">{{ $dayMonth }}</span>
                  @if($year)
                    <span class="year-part">{{ $year }}</span>
                  @endif
                </span>
              @endforeach
            </div>
          </div>

          {{-- Column 2: SML --}}
          <div class="trend-col">
            <div class="trend-col-title text-sml-blue">SPECIAL MENTION LOAN (SML)</div>
            <div class="trend-chart-wrapper">
              <svg viewBox="0 0 110 50">
                <!-- Trend line path -->
                @if(data_get($smlTrend, 'path'))
                  <path d="{{ data_get($smlTrend, 'path') }}" fill="none" stroke="#00a3ff" stroke-width="1.8" />
                @endif
                <!-- Trend points and text labels -->
                @foreach(data_get($smlTrend, 'points', []) as $idx => $pt)
                  <!-- Value Text Above Point -->
                  <text x="{{ $pt['x'] }}" y="{{ $pt['y'] - 6 }}" text-anchor="middle" font-size="3.8" font-weight="bold" fill="#00a3ff" font-family="inherit">{{ $pt['val_fmt'] }}</text>
                  <!-- Circle Dot -->
                  <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="2.8" fill="#00a3ff" stroke="#ffffff" stroke-width="1" />
                @endforeach
              </svg>
            </div>
            <!-- Date Labels below SVGs -->
            <div class="trend-dates-row">
              @foreach($trendDates as $d)
                @php
                  $parts = explode(' (', str_replace(')', '', $d));
                  $prefix = $parts[0] ?? '';
                  $datePart = $parts[1] ?? '';
                  $datePartClean = str_replace(['/', ' '], '-', $datePart);
                  $dateParts = explode('-', $datePartClean);
                  if (count($dateParts) === 3) {
                      $dayMonth = $dateParts[0] . ' ' . $dateParts[1];
                      $year = "'" . $dateParts[2];
                  } else {
                      $dayMonth = $datePart;
                      $year = '';
                  }
                @endphp
                <span class="trend-date-label">
                  <span class="date-part" style="font-weight: 700; color: #1e293b;">{{ $dayMonth }}</span>
                  @if($year)
                    <span class="year-part">{{ $year }}</span>
                  @endif
                </span>
              @endforeach
            </div>
          </div>

          {{-- Column 3: NPL --}}
          <div class="trend-col">
            <div class="trend-col-title text-npl-red">NON-PERFORMING LOAN (NPL)</div>
            <div class="trend-chart-wrapper">
              <svg viewBox="0 0 110 50">
                <!-- Trend line path -->
                @if(data_get($nplTrend, 'path'))
                  <path d="{{ data_get($nplTrend, 'path') }}" fill="none" stroke="#ef4444" stroke-width="1.8" />
                @endif
                <!-- Trend points and text labels -->
                @foreach(data_get($nplTrend, 'points', []) as $idx => $pt)
                  <!-- Value Text Above Point -->
                  <text x="{{ $pt['x'] }}" y="{{ $pt['y'] - 6 }}" text-anchor="middle" font-size="3.8" font-weight="bold" fill="#ef4444" font-family="inherit">{{ $pt['val_fmt'] }}</text>
                  <!-- Circle Dot -->
                  <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="2.8" fill="#ef4444" stroke="#ffffff" stroke-width="1" />
                @endforeach
              </svg>
            </div>
            <!-- Date Labels below SVGs -->
            <div class="trend-dates-row">
              @foreach($trendDates as $d)
                @php
                  $parts = explode(' (', str_replace(')', '', $d));
                  $prefix = $parts[0] ?? '';
                  $datePart = $parts[1] ?? '';
                  $datePartClean = str_replace(['/', ' '], '-', $datePart);
                  $dateParts = explode('-', $datePartClean);
                  if (count($dateParts) === 3) {
                      $dayMonth = $dateParts[0] . ' ' . $dateParts[1];
                      $year = "'" . $dateParts[2];
                  } else {
                      $dayMonth = $datePart;
                      $year = '';
                  }
                @endphp
                <span class="trend-date-label">
                  <span class="date-part" style="font-weight: 700; color: #1e293b;">{{ $dayMonth }}</span>
                  @if($year)
                    <span class="year-part">{{ $year }}</span>
                  @endif
                </span>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      {{-- PERFORMANCE VS RKA CARD --}}
      <div class="perf-rka-card">
        <div class="asc-header">
          <div class="asc-header-icon">
            <i class="fas fa-crosshairs"></i>
          </div>
          <div class="asc-header-title"><i>Performance Vs RKA</i></div>
        </div>
        <div class="prc-body">
          <div class="perf-table-wrapper">
            <table class="perf-table">
              <thead>
                <tr>
                  <th style="text-align: left;">Indikator</th>
                  <th>Posisi<br><small style="text-transform: none;">(sd {{ data_get($segmentPerf, 'period_format', '19 Mei 2026') }})</small><br><small style="color: #64748b; text-transform: none;">(Rp Juta)</small></th>
                  <th>RKA {{ data_get($segmentPerf, 'rka_month_year', 'Mei 26') }}<br><small style="color: #64748b; text-transform: none;">(Rp Juta)</small></th>
                  <th>% Penc. RKA<br><small style="color: #64748b; text-transform: none;">{{ data_get($segmentPerf, 'rka_month_year', 'Mei 26') }}</small></th>
                  <th>Gap thd RKA<br><small style="color: #64748b; text-transform: none;">{{ data_get($segmentPerf, 'rka_month_year', 'Mei 26') }} (Rp Juta)</small></th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {{-- OS Row --}}
                <tr>
                  <td class="perf-indicator-cell">
                    <div class="perf-indicator-icon bg-icon-os">
                      <i class="fas fa-chart-line"></i>
                    </div>
                    <span>OS</span>
                  </td>
                  <td class="perf-mono-cell">{{ data_get($osTrend, 'latest', '–') }}</td>
                  <td class="perf-mono-cell">{{ data_get($osTrend, 'rka', '–') }}</td>
                  <td class="perf-pct-cell text-{{ data_get($osTrend, 'pct_color', 'red') }}-flat">{{ data_get($osTrend, 'pct', '–') }}</td>
                  <td class="perf-mono-cell text-{{ data_get($osTrend, 'gap_color', 'red') }}-flat">{{ data_get($osTrend, 'gap', '–') }}</td>
                  <td>
                    <div class="perf-status-circle bg-status-{{ data_get($osTrend, 'gap_color', 'red') }}">
                      <i class="fas fa-arrow-{{ data_get($osTrend, 'status_arrow', 'down') }}"></i>
                    </div>
                  </td>
                </tr>
                {{-- SML Row --}}
                <tr>
                  <td class="perf-indicator-cell">
                    <div class="perf-indicator-icon bg-icon-sml">
                      <i class="fas fa-search"></i>
                    </div>
                    <span>SML</span>
                  </td>
                  <td class="perf-mono-cell">{{ data_get($smlTrend, 'latest', '–') }}</td>
                  <td class="perf-mono-cell">{{ data_get($smlTrend, 'rka', '–') }}</td>
                  <td class="perf-pct-cell text-{{ data_get($smlTrend, 'pct_color', 'red') }}-flat">{{ data_get($smlTrend, 'pct', '–') }}</td>
                  <td class="perf-mono-cell text-{{ data_get($smlTrend, 'gap_color', 'red') }}-flat">{{ data_get($smlTrend, 'gap', '–') }}</td>
                  <td>
                    <div class="perf-status-circle bg-status-{{ data_get($smlTrend, 'gap_color', 'red') }}">
                      <i class="fas fa-arrow-{{ data_get($smlTrend, 'status_arrow', 'down') }}"></i>
                    </div>
                  </td>
                </tr>
                {{-- NPL Row --}}
                <tr>
                  <td class="perf-indicator-cell">
                    <div class="perf-indicator-icon bg-icon-npl">
                      <i class="fas fa-shield-alt"></i>
                    </div>
                    <span>NPL</span>
                  </td>
                  <td class="perf-mono-cell">{{ data_get($nplTrend, 'latest', '–') }}</td>
                  <td class="perf-mono-cell">{{ data_get($nplTrend, 'rka', '–') }}</td>
                  <td class="perf-pct-cell text-{{ data_get($nplTrend, 'pct_color', 'red') }}-flat">{{ data_get($nplTrend, 'pct', '–') }}</td>
                  <td class="perf-mono-cell text-{{ data_get($nplTrend, 'gap_color', 'red') }}-flat">{{ data_get($nplTrend, 'gap', '–') }}</td>
                  <td>
                    <div class="perf-status-circle bg-status-{{ data_get($nplTrend, 'gap_color', 'red') }}">
                      <i class="fas fa-arrow-{{ data_get($nplTrend, 'status_arrow', 'down') }}"></i>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    @endif
    </div>
    @endforeach

    @if(!empty($area6RankingModes))
      @foreach($area6RankingModes as $scopeKey => $scopePayload)
        @if(!empty(data_get($scopePayload, 'branches', [])))
          @php
            $branches = data_get($scopePayload, 'branches', []);
            if (!empty($userBranchScope)) {
                $userBranchLabel = strtolower($userBranchScope['label']);
                $userBranchPlain = strtolower($userBranchScope['plain_label'] ?? '');
                $branches = array_values(array_filter($branches, function($b) use ($userBranchLabel, $userBranchPlain) {
                    $bName = strtolower(data_get($b, 'name', ''));
                    return $bName === $userBranchLabel
                        || $bName === $userBranchPlain
                        || str_contains($bName, $userBranchPlain);
                }));
            }
            $hideSimpananPanel = (bool) data_get($scopePayload, 'hide_simpanan', false);
            $scopeDescription = data_get($scopePayload, 'description', '');
          @endphp
          <div class="cabang-performance-grid area6-ranking-mode {{ $scopeKey === $area6DefaultScope ? '' : 'd-none' }}" data-area6-ranking-scope="{{ $scopeKey }}">
            {{-- PANEL 1: SIMPANAN --}}
            @unless($hideSimpananPanel)
            <div class="perf-panel-card tone-blue">
              <div class="perf-panel-head">
                <div class="perf-panel-title">
                  <span>Performa Simpanan</span>
                  <i class="perf-panel-badge bg-simp"></i>
                </div>
                <div class="perf-panel-subtitle">Total dana simpanan per cabang Area 6</div>
              </div>
              <div class="perf-panel-body">
                @forelse($branches as $b)
                <div class="perf-bar-row">
                  <div class="perf-bar-label-row">
                    <span class="perf-bar-branch">{{ data_get($b, 'name', '-') }}</span>
                    <span class="perf-bar-value">{{ data_get($b, 'simpanan_fmt', 'Rp0') }} <span style="font-size: 0.7rem; color: #64748b; font-weight: bold; margin-left: 0.25rem;">({{ data_get($b, 'simpanan_share_fmt', '0,00%') }})</span></span>
                  </div>
                  <div class="perf-bar-track">
                    <div class="perf-bar-fill bg-simp-grad" style="width: {{ data_get($b, 'simpanan_width', 0) }}%;"></div>
                  </div>
                </div>
                @empty
                <div class="rank-empty">Data tidak tersedia.</div>
                @endforelse
              </div>
            </div>
            @endunless

            {{-- PANEL 2: PINJAMAN --}}
            <div class="perf-panel-card tone-teal">
              <div class="perf-panel-head">
                <div class="perf-panel-title">
                  <span>Performa Pinjaman</span>
                  <i class="perf-panel-badge bg-pinj"></i>
                </div>
                <div class="perf-panel-subtitle">{{ $scopeDescription ?: 'Total outstanding pinjaman (OS) per cabang' }}</div>
              </div>
              <div class="perf-panel-body">
                @forelse($branches as $b)
                <div class="perf-bar-row">
                  <div class="perf-bar-label-row">
                    <span class="perf-bar-branch">{{ data_get($b, 'name', '-') }}</span>
                    <span class="perf-bar-value">{{ data_get($b, 'pinjaman_fmt', 'Rp0') }} <span style="font-size: 0.7rem; color: #64748b; font-weight: bold; margin-left: 0.25rem;">({{ data_get($b, 'pinjaman_share_fmt', '0,00%') }})</span></span>
                  </div>
                  <div class="perf-bar-track">
                    <div class="perf-bar-fill bg-pinj-grad" style="width: {{ data_get($b, 'pinjaman_width', 0) }}%;"></div>
                  </div>
                </div>
                @empty
                <div class="rank-empty">Data tidak tersedia.</div>
                @endforelse
              </div>
            </div>

            {{-- PANEL 3: SML --}}
            <div class="perf-panel-card tone-amber">
              <div class="perf-panel-head">
                <div class="perf-panel-title">
                  <span>Performa SML</span>
                  <i class="perf-panel-badge bg-sml"></i>
                </div>
                <div class="perf-panel-subtitle">{{ $scopeKey === 'ritel' ? 'Rasio SML & nominal absolute non-commercial per cabang ritel' : 'Rasio SML & nominal absolute non-commercial per cabang' }}</div>
              </div>
              <div class="perf-panel-body">
                @forelse($branches as $b)
                <div class="perf-bar-row">
                  <div class="perf-bar-label-row">
                    <span class="perf-bar-branch">{{ data_get($b, 'name', '-') }}</span>
                    <span class="perf-bar-value">
                      <span class="text-amber-flat font-weight-bold">{{ data_get($b, 'sml_pct_fmt', '0,00%') }}</span>
                      <span style="font-size: 0.65rem; color:#64748b; margin-left: 0.25rem;">({{ data_get($b, 'sml_abs_fmt', 'Rp0') }} | share {{ data_get($b, 'sml_share_fmt', '0,00%') }})</span>
                    </span>
                  </div>
                  <div class="perf-bar-track">
                    <div class="perf-bar-fill bg-sml-grad" style="width: {{ data_get($b, 'sml_pct_width', 0) }}%;"></div>
                  </div>
                </div>
                @empty
                <div class="rank-empty">Data tidak tersedia.</div>
                @endforelse
              </div>
            </div>

            {{-- PANEL 4: NPL --}}
            <div class="perf-panel-card tone-red">
              <div class="perf-panel-head">
                <div class="perf-panel-title">
                  <span>Performa NPL</span>
                  <i class="perf-panel-badge bg-npl"></i>
                </div>
                <div class="perf-panel-subtitle">{{ $scopeKey === 'ritel' ? 'Rasio NPL & nominal absolute non-commercial per cabang ritel' : 'Rasio NPL & nominal absolute non-commercial per cabang' }}</div>
              </div>
              <div class="perf-panel-body">
                @forelse($branches as $b)
                <div class="perf-bar-row">
                  <div class="perf-bar-label-row">
                    <span class="perf-bar-branch">{{ data_get($b, 'name', '-') }}</span>
                    <span class="perf-bar-value">
                      <span class="text-red-flat font-weight-bold">{{ data_get($b, 'npl_pct_fmt', '0,00%') }}</span>
                      <span style="font-size: 0.65rem; color:#64748b; margin-left: 0.25rem;">({{ data_get($b, 'npl_abs_fmt', 'Rp0') }} | share {{ data_get($b, 'npl_share_fmt', '0,00%') }})</span>
                    </span>
                  </div>
                  <div class="perf-bar-track">
                    <div class="perf-bar-fill bg-npl-grad" style="width: {{ data_get($b, 'npl_pct_width', 0) }}%;"></div>
                  </div>
                </div>
                @empty
                <div class="rank-empty">Data tidak tersedia.</div>
                @endforelse
              </div>
            </div>
          </div>
        @else
          <div class="area6-ranking-grid area6-ranking-mode {{ $scopeKey === $area6DefaultScope ? '' : 'd-none' }}" data-area6-ranking-scope="{{ $scopeKey }}">
            @forelse(data_get($scopePayload, 'rankings', []) as $group)
            <div class="rank-card tone-{{ data_get($group, 'tone', 'blue') }}">
              <div class="rank-card-head">
                <div class="rank-card-title">
                  <span>{{ data_get($group, 'title', 'Ranking') }}</span>
                  <i class="rank-badge"></i>
                </div>
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
        @endif
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

  {{-- LANDING EXECUTIVE SUMMARY --}}
  <section class="landing-summary">
    <div class="landing-summary-head">
      <div>
        <div class="landing-summary-title">{{ data_get($landingSummary, 'title', 'Ringkasan Eksekutif Area 6') }}</div>
        <div class="landing-summary-sub">{{ data_get($landingSummary, 'subtitle', 'Laba rugi, putusan mikro, dan realisasi segmen pada periode aktif.') }}</div>
      </div>
      <div class="landing-summary-badge">
        <i class="fas fa-layer-group"></i>
        Ikut Trigger Kinerja Area
      </div>
    </div>

    <div class="landing-summary-grid">
      <div class="landing-summary-card">
        <div class="landing-card-head">
          <div class="landing-card-title">
            <span class="landing-card-icon-wrap"><i class="fas fa-balance-scale"></i></span>
            <span>Laba Rugi</span>
          </div>
          <div class="landing-card-period">{{ data_get($landingProfit, 'period_label', 'Belum ada data') }}</div>
        </div>
        @if(data_get($landingProfit, 'available'))
          <div class="landing-main-value">
            <div class="value">{{ data_get($landingProfit, 'total_fmt', 'Rp0') }}</div>
            <div class="meta {{ data_get($landingProfit, 'delta_class', 'text-muted') }}">
              <i class="fas fa-chart-line"></i>
              {{ data_get($landingProfit, 'delta_fmt', '0,0%') }} vs periode sebelumnya
            </div>
          </div>
          <div class="landing-branch-list">
            @php
              $totalProfit = (float) data_get($landingProfit, 'total', 1);
              if ($totalProfit <= 0) $totalProfit = 1;
            @endphp
            @foreach(data_get($landingProfit, 'branches', []) as $branch)
              @php
                $branchNominal = (float) data_get($branch, 'nominal', 0);
                $pctContribution = min(100, max(0, ($branchNominal / $totalProfit) * 100));
              @endphp
              <div class="landing-branch-row">
                <div class="landing-branch-icon"><i class="fas fa-building"></i></div>
                <div class="landing-branch-body">
                  <div class="landing-row-label-row">
                    <span class="landing-row-label">{{ data_get($branch, 'name', '-') }}</span>
                    <span class="landing-row-sub">Kontribusi {{ number_format($pctContribution, 1, ',', '.') }}%</span>
                  </div>
                  <div class="landing-progress-container">
                    <div class="landing-progress-bar bg-primary" style="width: {{ $pctContribution }}%;"></div>
                  </div>
                </div>
                <div class="landing-row-value">{{ data_get($branch, 'nominal_fmt', 'Rp0') }}</div>
              </div>
            @endforeach
          </div>
        @else
          <div class="landing-empty">Data laba setelah pajak belum tersedia.</div>
        @endif
      </div>

      <div class="landing-summary-card">
        <div class="landing-card-head">
          <div class="landing-card-title">
            <span class="landing-card-icon-wrap"><i class="fas fa-stamp"></i></span>
            <span>Rekap Putusan</span>
          </div>
          <div class="landing-card-period">{{ data_get($landingDecision, 'period_label', 'Belum ada data') }}</div>
        </div>
        @if(data_get($landingDecision, 'available'))
          <div class="landing-decision-summary-strip">
            <div class="landing-decision-chip">
              <span>Total Putusan</span>
              <strong>{{ data_get($landingDecision, 'total_deb_fmt', '0 deb') }} | {{ data_get($landingDecision, 'total_nominal_fmt', 'Rp0') }}</strong>
            </div>
            <div class="landing-decision-chip">
              <span>KUR Ritel 2015</span>
              <strong>{{ data_get($landingDecision, 'kur_ritel_deb_fmt', '0 deb') }} | {{ data_get($landingDecision, 'kur_ritel_nominal_fmt', 'Rp0') }}</strong>
            </div>
          </div>
          <div class="landing-decision-list">
            @php
              $decisionItems = collect(data_get($landingDecision, 'items', []));
              $totalDecisionNominal = (float) $decisionItems->sum('nominal');
              if ($totalDecisionNominal <= 0) $totalDecisionNominal = 1;
            @endphp
            @foreach($decisionItems as $item)
              @php
                $itemNominal = (float) data_get($item, 'nominal', 0);
                $pctContribution = min(100, max(0, ($itemNominal / $totalDecisionNominal) * 100));
              @endphp
              <div class="landing-decision-row">
                <div class="landing-decision-icon"><i class="{{ data_get($item, 'icon', 'fas fa-user-check') }}"></i></div>
                <div class="landing-decision-body">
                  <div class="landing-row-label-row">
                    <span class="landing-row-label">{{ data_get($item, 'label', '-') }}</span>
                    <span class="landing-row-sub">{{ data_get($item, 'deb_fmt', '0 deb') }}</span>
                  </div>
                  <div class="landing-progress-container">
                    <div class="landing-progress-bar bg-success" style="width: {{ $pctContribution }}%;"></div>
                  </div>
                  @if(data_get($item, 'kur_ritel_note'))
                    <div class="landing-decision-note">
                      <i class="fas fa-check-circle"></i>
                      <span>{{ data_get($item, 'kur_ritel_note') }}</span>
                    </div>
                  @endif
                </div>
                <div class="landing-row-value">{{ data_get($item, 'nominal_fmt', 'Rp0') }}</div>
              </div>
            @endforeach
          </div>
        @else
          <div class="landing-empty">Data putusan RM Mikro belum tersedia.</div>
        @endif
      </div>

      <div class="landing-summary-card">
        @php
          $realizationScopes = data_get($landingRealization, 'scopes', []);
          $realizationDefaultScope = data_get($landingRealization, 'default_scope', $area6DefaultScope);
        @endphp
        <div class="landing-card-head">
          <div class="landing-card-title">
            <span class="landing-card-icon-wrap"><i class="fas fa-chart-bar"></i></span>
            <span>Realisasi Segmen</span>
          </div>
          <div class="landing-card-period">Sinkron dengan Kinerja Area</div>
        </div>

        @if(!empty($realizationScopes))
          @foreach($realizationScopes as $scopeKey => $scopePayload)
            <div class="landing-realization-panel {{ $scopeKey === $realizationDefaultScope ? '' : 'd-none' }}" data-landing-realization-panel="{{ $scopeKey }}">
              <div class="landing-scope-caption">{{ data_get($scopePayload, 'label', strtoupper($scopeKey)) }}</div>
              <div class="landing-segment-list">
                @foreach(data_get($scopePayload, 'segments', []) as $segment)
                  @php
                    $targetVal = (float) data_get($segment, 'target', 0);
                    $realVal = (float) data_get($segment, 'realization', 0);
                    $pctReal = $targetVal > 0 ? min(100, ($realVal / $targetVal) * 100) : 0;
                    $color = data_get($segment, 'pct_color', 'blue');
                  @endphp
                  <div class="landing-segment-row">
                    <div class="landing-segment-icon"><i class="{{ data_get($segment, 'icon', 'fas fa-chart-line') }}"></i></div>
                    <div class="landing-segment-body">
                      <div class="landing-row-label-row">
                        <span class="landing-row-label">{{ data_get($segment, 'label', '-') }}</span>
                        <span class="landing-row-sub">Target {{ data_get($segment, 'target_fmt', 'Rp0') }} | {{ data_get($segment, 'pct_fmt', '0,00%') }}</span>
                      </div>
                      <div class="landing-progress-container">
                        <div class="landing-progress-bar bg-{{ $color }}" style="width: {{ $pctReal }}%;"></div>
                      </div>
                    </div>
                    <div class="landing-row-value">{{ data_get($segment, 'realization_fmt', 'Rp0') }}</div>
                  </div>
                @endforeach
              </div>
            </div>
          @endforeach
        @else
          <div class="landing-empty">Data realisasi segmen belum tersedia.</div>
        @endif
      </div>
    </div>
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
  <!-- PPT Loading Overlay -->
  <div class="ppt-loading-overlay" id="ppt-loading-overlay">
    <div class="ppt-loading-card">
      <div class="ppt-spinner-container">
        <div class="ppt-ring"></div>
        <div class="ppt-ring-inner"></div>
      </div>
      <div class="ppt-loading-text">Menyiapkan Dokumen PPT...</div>
      <div class="ppt-loading-sub">Sedang menyusun visual presentasi executive.</div>
    </div>
  </div>
  <!-- Global Dashboard Loading Overlay -->
  <div class="dashboard-loading-overlay" id="dashboard-global-loader">
    <div class="dashboard-loading-card" role="status" aria-live="polite" aria-label="Memuat data Dashboard Area 6">
      <div class="dashboard-loading-top">
        <div class="loading-spinner-container" aria-hidden="true">
          <div class="loading-ring"></div>
          <div class="loading-ring-inner"></div>
        </div>
        <div>
          <div class="dashboard-loading-text" id="dashboard-loading-title">Memuat data Dashboard Area 6</div>
          <div class="dashboard-loading-sub" id="dashboard-loading-sub">Mengambil snapshot simpanan, pinjaman, harian, dan 8 strategi digital.</div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
<script>
// Relocate loading overlays to document body immediately to bypass parent transitions/transforms and guarantee perfect centering
(function() {
  const relocateLoaders = () => {
    const pptOverlay = document.getElementById('ppt-loading-overlay');
    if (pptOverlay && pptOverlay.parentNode !== document.body) {
      document.body.appendChild(pptOverlay);
    }
    const globalLoader = document.getElementById('dashboard-global-loader');
    if (globalLoader && globalLoader.parentNode !== document.body) {
      document.body.appendChild(globalLoader);
    }
  };
  relocateLoaders();
  document.addEventListener('DOMContentLoaded', relocateLoaders);
  window.addEventListener('load', relocateLoaders);
})();

// Keep the landing page proportional to the actual available content width,
// not only to the device viewport. This covers tablet landscape + open sidebar.
(function() {
  const shell = document.querySelector('.db-shell');
  if (!shell) return;

  document.body.classList.add('dashboard-landing-page');

  let raf = null;

  const syncLandingDensity = () => {
    const shellWidth = shell.getBoundingClientRect().width || window.innerWidth || 0;
    const viewportWidth = window.visualViewport?.width || window.innerWidth || shellWidth;
    const viewportHeight = window.visualViewport?.height || window.innerHeight || 0;
    const effectiveWidth = Math.min(shellWidth, viewportWidth);
    const coarseQuery = window.matchMedia ? window.matchMedia('(pointer: coarse)') : null;
    const isTouch = coarseQuery ? coarseQuery.matches : false;
    const isShort = viewportHeight > 0 && viewportHeight <= 760;
    const isMobile = effectiveWidth <= 700;
    const isTablet = !isMobile && (effectiveWidth <= 1280 || (isTouch && effectiveWidth <= 1680));
    const isCompact = isMobile || isTablet || isShort || effectiveWidth <= 1500;
    const isNarrow = isMobile || effectiveWidth <= 880;

    shell.classList.toggle('landing-compact', isCompact);
    shell.classList.toggle('landing-narrow', isNarrow);
    shell.classList.toggle('landing-tablet', isTablet);
    shell.classList.toggle('landing-mobile', isMobile);
    shell.classList.toggle('landing-short', isShort && !isMobile);
    shell.dataset.landingWidth = String(Math.round(effectiveWidth));
    shell.dataset.landingHeight = String(Math.round(viewportHeight));

    const chartCanvas = document.getElementById('timeseriesChart');
    if (chartCanvas) {
      const chartHeight = isMobile ? 210 : (isTablet || isShort ? 260 : (isCompact ? 300 : 360));
      chartCanvas.style.setProperty('height', `${chartHeight}px`, 'important');
    }

    if (window.timeseriesArea6Chart && typeof window.timeseriesArea6Chart.resize === 'function') {
      window.timeseriesArea6Chart.resize();
    }
  };

  const scheduleSync = () => {
    if (raf) window.cancelAnimationFrame(raf);
    raf = window.requestAnimationFrame(syncLandingDensity);
  };

  scheduleSync();
  window.addEventListener('resize', scheduleSync, { passive: true });
  window.addEventListener('orientationchange', scheduleSync, { passive: true });
  window.setTimeout(scheduleSync, 250);
  window.setTimeout(scheduleSync, 900);

  if (window.ResizeObserver) {
    const observer = new ResizeObserver(scheduleSync);
    observer.observe(shell);
    const wrapper = document.querySelector('.content-wrapper');
    if (wrapper) observer.observe(wrapper);
  }

  if (window.MutationObserver) {
    const mutationObserver = new MutationObserver(scheduleSync);
    mutationObserver.observe(document.body, { attributes: true, attributeFilter: ['class', 'style'] });
  }
})();

document.addEventListener('DOMContentLoaded', function() {
  const pptOverlay = document.getElementById('ppt-loading-overlay');
  const globalLoader = document.getElementById('dashboard-global-loader');
  const loaderTitle = document.getElementById('dashboard-loading-title');
  const loaderSub = document.getElementById('dashboard-loading-sub');
  const loaderStartedAt = window.performance ? performance.now() : Date.now();
  let loaderHidden = false;
  let loaderWindowReady = document.readyState === 'complete';
  let loaderFontsReady = !document.fonts;
  let loaderChartReady = false;
  let loaderReadyCheckScheduled = false;

  const setDashboardLoaderCopy = (title, subtitle) => {
    if (loaderTitle && title) loaderTitle.textContent = title;
    if (loaderSub && subtitle) loaderSub.textContent = subtitle;
  };

  const hideDashboardLoader = () => {
    if (!globalLoader || loaderHidden) {
      return;
    }

    loaderHidden = true;
    const now = window.performance ? performance.now() : Date.now();
    const elapsed = now - loaderStartedAt;
    const delay = Math.max(0, 850 - elapsed);
    window.setTimeout(() => {
      globalLoader.classList.remove('active');
    }, delay);
  };

  const isDashboardPaintReady = () => {
    const requiredSelectors = ['.db-header', '.kpi-strip', '.area6-panel'];
    const requiredReady = requiredSelectors.every(selector => {
      const element = document.querySelector(selector);
      return element && element.offsetWidth > 0 && element.offsetHeight > 0;
    });

    return requiredReady && document.querySelectorAll('.kpi-card').length >= 3;
  };

  const canHideDashboardLoader = () => {
    return loaderWindowReady
      && loaderFontsReady
      && loaderChartReady
      && isDashboardPaintReady();
  };

  const scheduleDashboardLoaderCheck = () => {
    if (!globalLoader || loaderHidden || loaderReadyCheckScheduled) {
      return;
    }

    loaderReadyCheckScheduled = true;
    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        loaderReadyCheckScheduled = false;
        if (canHideDashboardLoader()) {
          setDashboardLoaderCopy(
            'Dashboard siap ditampilkan',
            'Kartu, grafik, dan ringkasan Area 6 sudah selesai dirender.'
          );
          hideDashboardLoader();
          return;
        }

        window.setTimeout(scheduleDashboardLoaderCheck, 120);
      });
    });
  };

  if (globalLoader && globalLoader.classList.contains('active')) {
    setDashboardLoaderCopy(
      'Memuat data Dashboard Area 6',
      'Mengambil snapshot simpanan, pinjaman, harian, dan 8 strategi digital.'
    );

    if (!loaderWindowReady) {
      window.addEventListener('load', () => {
        loaderWindowReady = true;
        scheduleDashboardLoaderCheck();
      }, { once: true });
    }

    if (document.fonts) {
      document.fonts.ready
        .then(() => {
          loaderFontsReady = true;
          scheduleDashboardLoaderCheck();
        })
        .catch(() => {
          loaderFontsReady = true;
          scheduleDashboardLoaderCheck();
        });
    }

    window.setTimeout(() => {
      if (!loaderHidden) {
        setDashboardLoaderCopy(
          'Masih menyusun data dashboard',
          'Proses tetap berjalan. Loader akan hilang setelah tampilan siap sepenuhnya.'
        );
      }
    }, 5000);

    scheduleDashboardLoaderCheck();
  }

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
      loaderChartReady = true;
      scheduleDashboardLoaderCheck();
      return;
    }

    try {
      const chart = window.timeseriesArea6Chart = new Chart(ctx.getContext('2d'), {
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
              yAxisID: 'y',
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
              ticks:{ font:{size:10}, color:'#64748b', callback: value => 'Rp'+Number(value).toFixed(1)+'T' }
            }
          }
        }
      });

      chartPanel?.classList.remove('is-empty');
      window.setTimeout(() => {
        chart.resize();
        loaderChartReady = true;
        scheduleDashboardLoaderCheck();
      }, 120);
    } catch (error) {
      markChartEmpty();
      loaderChartReady = true;
      scheduleDashboardLoaderCheck();
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

      document.querySelectorAll('.area6-scope-content').forEach(panel => {
        panel.classList.toggle('d-none', panel.getAttribute('data-area6-content-scope') !== scope);
      });

      document.querySelectorAll('.landing-realization-panel').forEach(panel => {
        panel.classList.toggle('d-none', panel.getAttribute('data-landing-realization-panel') !== scope);
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

  // Date Selector redirect
  const dateSelector = document.getElementById('periode-selector');
  if (dateSelector) {
    dateSelector.addEventListener('change', function() {
      const globalLoader = document.getElementById('dashboard-global-loader');
      setDashboardLoaderCopy(
        'Mengambil data periode ' + this.options[this.selectedIndex].text,
        'Snapshot dan grafik sedang dihitung ulang untuk periode yang dipilih.'
      );
      if (globalLoader) globalLoader.classList.add('active');
      window.location.href = '?periode=' + this.value;
    });
  }

  // Load pptxgenjs locally
  const loadPptxGen = () => {
    return new Promise((resolve, reject) => {
      if (window.PptxGenJS || window.pptxgen) {
        resolve(window.PptxGenJS || window.pptxgen);
        return;
      }
      const script = document.createElement('script');
      script.src = '{{ asset("vendor/pptxgen.bundle.js") }}';
      script.onload = () => resolve(window.PptxGenJS || window.pptxgen);
      script.onerror = () => reject(new Error('Gagal memuat library PPTX.'));
      document.head.appendChild(script);
    });
  };

  // PPT Export generation
  const pptData = {
    selectedPeriod: @json($selectedPeriod),
    simpanan: {
      value: @json(data_get($simpananReport, 'value', '–')),
      meta: @json(data_get($simpananReport, 'meta', '–')),
      trend: @json(data_get($simpananReport, 'trend', '0%'))
    },
    pinjaman: {
      value: @json(data_get($pinjamanReport, 'value', '–')),
      meta: @json(data_get($pinjamanReport, 'meta', '–')),
      trend: @json(data_get($pinjamanReport, 'trend', '0%'))
    },
    portfolio: {
      value: @json(data_get($portfolioReport, 'value', '–')),
      meta: @json(data_get($portfolioReport, 'meta', '–')),
      trend: @json(data_get($portfolioReport, 'trend', '0%'))
    },
    branches: @json(array_values(data_get($area6RankingModes, 'cabang_konsol.branches', []))),
    segments: @json($segments),
    totalPerf: @json($totalPerf),
    rkaMonthYear: @json($rkaMonthYear),
    composition: @json(data_get($contentPortfolio, 'composition', []))
  };

  const PRESENTATION_DATA_URL = @json(route('dashboard.presentation-data'));
  const SELECTED_PRESENTATION_PERIOD = @json($selectedPeriod);

  const buildPresentationPayloadUrl = (period = SELECTED_PRESENTATION_PERIOD, options = {}) => {
    const url = new URL(PRESENTATION_DATA_URL, window.location.origin);
    if (period) {
      url.searchParams.set('periode', period);
    }
    if (options.warmOnly) {
      url.searchParams.set('warm', '1');
    }
    if (options.fresh) {
      url.searchParams.set('fresh', '1');
      url.searchParams.set('_ts', String(Date.now()));
    }
    return url;
  };
  const PPT_NA = 'Data belum tersedia';
  const PPT_THEME = {
    blue: '0857C3',
    blueDark: '063D87',
    orange: 'F58220',
    red: 'DC2626',
    green: '059669',
    teal: '0F766E',
    slate: '0F172A',
    muted: '64748B',
    border: 'CBD5E1',
    soft: 'F8FAFC',
    white: 'FFFFFF',
    font: 'Arial'
  };

  const safePptText = (value, fallback = PPT_NA) => {
    if (value === null || value === undefined || value === '') return fallback;
    if (typeof value === 'number' && !Number.isFinite(value)) return fallback;
    return String(value)
      .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '')
      .replace(/\b(?:NaN|Infinity|-Infinity|undefined|null)\b/g, fallback);
  };

  const safePptNumber = (value, fallback = 0) => {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
  };

  const safePptSize = (value, fallback = 0.01) => {
    return Math.max(safePptNumber(value, fallback), 0.01);
  };

  const fetchPresentationPayload = async () => {
    const url = buildPresentationPayloadUrl(SELECTED_PRESENTATION_PERIOD, { fresh: true });

    const response = await fetch(url.toString(), {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    });

    if (!response.ok) {
      throw new Error(`Gagal mengambil data presentasi (${response.status}).`);
    }

    const payload = await response.json();

    if (!payload || typeof payload !== 'object' || !payload.meta) {
      throw new Error('Payload presentasi tidak valid.');
    }

    return payload;
  };

  const imageToDataUri = async (url) => {
    if (!url) return null;

    try {
      const response = await fetch(url, { cache: 'force-cache' });
      if (!response.ok) return null;
      const blob = await response.blob();

      return await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
      });
    } catch (error) {
      console.warn('Logo presentasi gagal dimuat:', error);
      return null;
    }
  };

  const addPptRect = (pptx, slide, x, y, w, h, color, lineColor = color) => {
    slide.addShape(pptx.shapes.RECTANGLE, {
      x: safePptNumber(x),
      y: safePptNumber(y),
      w: safePptSize(w),
      h: safePptSize(h),
      fill: { color },
      line: { color: lineColor, transparency: lineColor === color ? 100 : 0, width: 0.6 }
    });
  };

  const addPptChrome = (pptx, slide, payload, title, subtitle, logos) => {
    addPptRect(pptx, slide, 0, 0, 13.33, 7.5, 'F6F8FB');
    addPptRect(pptx, slide, 0, 0, 13.33, 0.72, PPT_THEME.white, 'E2E8F0');
    addPptRect(pptx, slide, 0, 0.72, 13.33, 0.05, PPT_THEME.orange);
    addPptRect(pptx, slide, 0, 0.77, 13.33, 0.02, PPT_THEME.blue);

    if (logos.bri) {
      slide.addImage({ data: logos.bri, x: 0.32, y: 0.17, w: 0.86, h: 0.38 });
    }
    if (logos.danantara) {
      slide.addImage({ data: logos.danantara, x: 10.94, y: 0.14, w: 1.84, h: 0.43 });
    }

    slide.addText(title, {
      x: 1.38, y: 0.16, w: 8.95, h: 0.28,
      fontFace: PPT_THEME.font, fontSize: 13.8, bold: true, color: PPT_THEME.blueDark,
      margin: 0
    });
    slide.addText(subtitle || 'Area 6 - Region Malang', {
      x: 1.39, y: 0.47, w: 8.94, h: 0.17,
      fontFace: PPT_THEME.font, fontSize: 7.1, color: PPT_THEME.muted,
      margin: 0
    });
    slide.addText(`Periode: ${safePptText(payload?.meta?.period_label || payload?.meta?.period, '-')}`, {
      x: 0.42, y: 7.05, w: 4.1, h: 0.2,
      fontFace: PPT_THEME.font, fontSize: 7, color: PPT_THEME.muted,
      margin: 0
    });
    slide.addText('Source: dashboard realtime dan snapshot report existing', {
      x: 4.72, y: 7.05, w: 4.4, h: 0.2,
      fontFace: PPT_THEME.font, fontSize: 7, color: PPT_THEME.muted,
      align: 'center',
      margin: 0
    });
    slide.addText('A-SIX Area 6', {
      x: 10.2, y: 7.05, w: 2.72, h: 0.2,
      fontFace: PPT_THEME.font, fontSize: 7, color: PPT_THEME.muted,
      align: 'right',
      margin: 0
    });
  };

  const addPptSectionLabel = (slide, text, x, y, w, color = PPT_THEME.blue) => {
    slide.addText(text, {
      x, y, w, h: 0.24,
      fontFace: PPT_THEME.font, fontSize: 8.5, color,
      bold: true, margin: 0
    });
  };

  const addPptMetricCard = (pptx, slide, card, x, y, w, h, color = PPT_THEME.blue) => {
    addPptRect(pptx, slide, x, y, w, h, PPT_THEME.white, 'D9E2EF');
    addPptRect(pptx, slide, x, y, 0.08, h, color);
    slide.addText(safePptText(card?.label, 'Metric').toUpperCase(), {
      x: x + 0.2, y: y + 0.15, w: w - 0.38, h: 0.2,
      fontFace: PPT_THEME.font, fontSize: 6.9, color, bold: true, margin: 0,
      fit: 'shrink'
    });
    slide.addText(safePptText(card?.value), {
      x: x + 0.2, y: y + 0.47, w: w - 0.38, h: 0.44,
      fontFace: PPT_THEME.font, fontSize: 15.5, color: PPT_THEME.slate, bold: true,
      fit: 'shrink', margin: 0
    });
    const metaText = card?.ratio ? `${card.ratio} | ${safePptText(card.meta, '-')}` : safePptText(card?.meta, '-');
    slide.addText(metaText, {
      x: x + 0.2, y: y + 0.98, w: w - 0.38, h: 0.2,
      fontFace: PPT_THEME.font, fontSize: 6.8, color: PPT_THEME.muted,
      fit: 'shrink', margin: 0
    });
    if (card?.trend) {
      const trendColor = String(card.trend).trim().startsWith('-') ? PPT_THEME.red : PPT_THEME.green;
      slide.addText(`Trend ${card.trend}`, {
        x: x + 0.2, y: y + h - 0.32, w: w - 0.38, h: 0.18,
        fontFace: PPT_THEME.font, fontSize: 6.8, color: trendColor, bold: true,
        margin: 0
      });
    }
  };

  const pptCell = (text, options = {}) => ({
    text: safePptText(text, '-'),
    options: {
      fontFace: PPT_THEME.font,
      fontSize: 7.2,
      color: PPT_THEME.slate,
      margin: 0.04,
      valign: 'mid',
      ...options
    }
  });

  const addPptUnavailable = (pptx, slide, x, y, w, h, message = PPT_NA) => {
    addPptRect(pptx, slide, x, y, w, h, PPT_THEME.white, 'D9E2EF');
    slide.addText(message, {
      x: x + 0.12, y: y + (h / 2) - 0.12, w: w - 0.24, h: 0.24,
      fontFace: PPT_THEME.font, fontSize: 8, color: PPT_THEME.muted, align: 'center',
      margin: 0
    });
  };

  const addPptTable = (pptx, slide, headers, rows, mapper, x, y, w, h, colW) => {
    if (!rows || rows.length === 0) {
      addPptUnavailable(pptx, slide, x, y, w, h);
      return;
    }

    const tableRows = [
      headers.map((header) => pptCell(header, {
        fill: PPT_THEME.blue,
        color: PPT_THEME.white,
        bold: true,
        fontSize: 6.6
      }))
    ];

    rows.forEach((row, index) => {
      const bg = index % 2 === 0 ? PPT_THEME.soft : PPT_THEME.white;
      tableRows.push(mapper(row, index).map((cell) => {
        if (typeof cell === 'object' && cell !== null && Object.prototype.hasOwnProperty.call(cell, 'text')) {
          return pptCell(cell.text, { fill: bg, ...(cell.options || {}) });
        }
        return pptCell(cell, { fill: bg });
      }));
    });

    slide.addTable(tableRows, {
      x, y, w, h,
      colW,
      border: { pt: 0.35, color: 'D9E2EF' },
      margin: 0.02
    });
  };

  const addPptLine = (pptx, slide, x1, y1, x2, y2, color, width = 1.2) => {
    const startX = safePptNumber(x1);
    const startY = safePptNumber(y1);
    const endX = safePptNumber(x2, startX);
    const endY = safePptNumber(y2, startY);
    const thickness = Math.max(safePptNumber(width, 1.2) / 95, 0.012);
    const horizontalW = Math.abs(endX - startX);
    const verticalH = Math.abs(endY - startY);

    if (horizontalW > 0.015) {
      addPptRect(pptx, slide, Math.min(startX, endX), startY - (thickness / 2), horizontalW, thickness, color);
    }

    if (verticalH > 0.015) {
      addPptRect(pptx, slide, endX - (thickness / 2), Math.min(startY, endY), thickness, verticalH, color);
    }

    if (horizontalW <= 0.015 && verticalH <= 0.015) {
      addPptRect(pptx, slide, startX - (thickness / 2), startY - (thickness / 2), thickness, thickness, color);
    }
  };

  const addPptLineChart = (pptx, slide, timeseries, x, y, w, h) => {
    const labels = Array.isArray(timeseries?.labels) ? timeseries.labels : [];
    const series = Array.isArray(timeseries?.series) ? timeseries.series : [];
    const activeSeries = series
      .filter((item) => Array.isArray(item.values) && item.values.length > 0)
      .slice(0, 4);
    if (!labels.length || !activeSeries.length) {
      addPptUnavailable(pptx, slide, x, y, w, h);
      return;
    }

    addPptRect(pptx, slide, x, y, w, h, PPT_THEME.white, 'D9E2EF');
    const plotX = x + 0.45;
    const plotY = y + 0.35;
    const plotW = w - 0.85;
    const plotH = h - 0.92;
    const palette = [PPT_THEME.blue, PPT_THEME.teal, 'D97706', PPT_THEME.red];
    const values = activeSeries.flatMap((item) => item.values.map((value) => safePptNumber(value, 0)));
    const maxValue = Math.max(...values, 1);
    const minValue = Math.min(...values, 0);
    const range = Math.max(maxValue - minValue, 1);

    addPptLine(pptx, slide, plotX, plotY + plotH, plotX + plotW, plotY + plotH, 'D9E2EF', 0.8);
    addPptLine(pptx, slide, plotX, plotY, plotX, plotY + plotH, 'D9E2EF', 0.8);

    activeSeries.forEach((item, sIndex) => {
      const color = palette[sIndex % palette.length];
      const points = item.values.slice(0, labels.length).map((value, index) => {
        const px = plotX + (labels.length <= 1 ? plotW / 2 : (plotW / (labels.length - 1)) * index);
        const py = plotY + plotH - ((safePptNumber(value, 0) - minValue) / range) * plotH;
        return { x: px, y: py };
      });

      points.forEach((point, index) => {
        if (index > 0) {
          addPptLine(pptx, slide, points[index - 1].x, points[index - 1].y, point.x, point.y, color, 1.15);
        }
        addPptRect(pptx, slide, point.x - 0.025, point.y - 0.025, 0.05, 0.05, color);
      });

      slide.addText(safePptText(item.label, item.key), {
        x: x + 0.38 + (sIndex % 2) * 2.9,
        y: y + h - 0.4 + Math.floor(sIndex / 2) * 0.18,
        w: 2.55,
        h: 0.14,
        fontFace: PPT_THEME.font,
        fontSize: 6.2,
        color,
        margin: 0
      });
    });

    labels.forEach((label, index) => {
      const px = plotX + (labels.length <= 1 ? plotW / 2 : (plotW / (labels.length - 1)) * index);
      slide.addText(safePptText(label, '-'), {
        x: px - 0.24,
        y: plotY + plotH + 0.08,
        w: 0.48,
        h: 0.14,
        fontFace: PPT_THEME.font,
        fontSize: 5.5,
        color: PPT_THEME.muted,
        align: 'center',
        margin: 0
      });
    });

    slide.addText(`Unit: ${safePptText(timeseries?.unit, 'Rp Juta')}`, {
      x: x + w - 1.35,
      y: y + 0.12,
      w: 1.1,
      h: 0.15,
      fontFace: PPT_THEME.font,
      fontSize: 6,
      color: PPT_THEME.muted,
      align: 'right',
      margin: 0
    });
  };

  const pptRows = (rows, limit = 5) => (Array.isArray(rows) ? rows.slice(0, limit) : []);

  const renderPptSummarySlide = (pptx, payload, logos) => {
    const slide = pptx.addSlide();
    addPptChrome(pptx, slide, payload, 'Ringkasan Performa Area 6 - Region Malang', 'Landing page realtime', logos);
    slide.addText('Materi Pendukung Asistensi', {
      x: 0.58, y: 1.15, w: 6.2, h: 0.34,
      fontFace: PPT_THEME.font, fontSize: 17, color: PPT_THEME.slate, bold: true,
      margin: 0
    });
    slide.addText(`Generated: ${safePptText(payload?.meta?.generated_at, '-')}`, {
      x: 8.2, y: 1.2, w: 4.55, h: 0.2,
      fontFace: PPT_THEME.font, fontSize: 7, color: PPT_THEME.muted, align: 'right',
      margin: 0
    });

    const cards = Array.isArray(payload?.summary?.cards) ? payload.summary.cards : [];
    const colors = [PPT_THEME.blue, PPT_THEME.blueDark, PPT_THEME.teal, PPT_THEME.orange, PPT_THEME.red];
    cards.slice(0, 5).forEach((card, index) => {
      addPptMetricCard(pptx, slide, card, 0.58 + index * 2.54, 1.72, 2.26, 1.58, colors[index] || PPT_THEME.blue);
    });

    addPptSectionLabel(slide, 'Highlight Delta Landing Page', 0.58, 3.62, 4.6);
    const highlights = Array.isArray(payload?.summary?.highlights) ? payload.summary.highlights : [];
    slide.addText(highlights.length ? highlights.map((item) => `- ${safePptText(item, '-')}`).join('\n') : PPT_NA, {
      x: 0.58, y: 3.98, w: 5.75, h: 1.55,
      fontFace: PPT_THEME.font, fontSize: 8.2, color: PPT_THEME.slate,
      breakLine: false, margin: 0.04, fit: 'shrink'
    });

    addPptSectionLabel(slide, 'Komposisi Total OS', 6.72, 3.62, 4.6);
    const composition = payload?.performance_overview?.composition || {};
    ['os', 'sml', 'npl'].forEach((key, index) => {
      const item = composition?.[key] || {};
      const y = 3.95 + index * 0.48;
      addPptRect(pptx, slide, 6.72, y + 0.03, 0.12, 0.12, colors[index] || PPT_THEME.blue);
      slide.addText(key.toUpperCase(), {
        x: 6.96, y, w: 0.72, h: 0.17,
        fontFace: PPT_THEME.font, fontSize: 7.4, bold: true, color: PPT_THEME.slate, margin: 0
      });
      slide.addText(`${safePptText(item.value, '-')} (${safePptText(item.pct, '-')})`, {
        x: 7.75, y, w: 3.15, h: 0.17,
        fontFace: PPT_THEME.font, fontSize: 7.4, color: PPT_THEME.muted, margin: 0
      });
    });
  };

  const renderPptOverviewSlide = (pptx, payload, logos) => {
    const slide = pptx.addSlide();
    addPptChrome(pptx, slide, payload, 'Performance Overview Dashboard Harian', 'Snapshot dan timeseries realisasi', logos);
    addPptSectionLabel(slide, 'Kinerja Per Segment (Rp Juta)', 0.55, 1.12, 4.2);
    const segments = pptRows(payload?.performance_overview?.segments, 4);
    const total = payload?.performance_overview?.total;
    const tableRows = total && Object.keys(total).length ? [...segments, { label: 'TOTAL AREA 6', ...total, isTotal: true }] : segments;
    addPptTable(pptx, slide, ['Segment', 'OS', '% OS', 'SML', 'NPL'], tableRows, (row) => [
      { text: safePptText(row.label, 'Total'), options: { bold: true, fontSize: row.isTotal ? 7 : 6.7 } },
      { text: row.os?.realization_fmt || row.os?.value || '-', options: { align: 'right' } },
      { text: row.os?.pct_fmt || '-', options: { align: 'right', bold: true } },
      { text: row.sml?.realization_fmt || row.sml?.value || '-', options: { align: 'right' } },
      { text: row.npl?.realization_fmt || row.npl?.value || '-', options: { align: 'right' } }
    ], 0.55, 1.48, 5.75, 2.2, [1.55, 1.05, 0.75, 1.15, 1.15]);

    addPptSectionLabel(slide, 'Timeseries Realisasi', 6.65, 1.12, 4.2);
    addPptLineChart(pptx, slide, payload?.timeseries, 6.65, 1.48, 6.05, 2.2);

    addPptSectionLabel(slide, 'Cabang Konsolidasi', 0.55, 4.02, 4.2);
    addPptTable(pptx, slide, ['Cabang', 'Simpanan', 'OS', 'SML %', 'NPL %'], pptRows(payload?.performance_overview?.branches, 5), (row) => [
      { text: row.name || '-', options: { bold: true } },
      { text: row.simpanan_fmt || '-', options: { align: 'right' } },
      { text: row.pinjaman_fmt || '-', options: { align: 'right' } },
      { text: row.sml_pct_fmt || '-', options: { align: 'right' } },
      { text: row.npl_pct_fmt || '-', options: { align: 'right' } }
    ], 0.55, 4.36, 12.15, 2.15, [2.7, 2.25, 2.25, 1.8, 1.8]);
  };

  const renderPptDecisionSlide = (pptx, payload, logos) => {
    const slide = pptx.addSlide();
    addPptChrome(pptx, slide, payload, 'Evaluasi Putusan', 'Kinerja RM Mikro - unit per pemutus', logos);
    const total = payload?.micro?.decision?.total || {};
    addPptMetricCard(pptx, slide, { label: 'Total Debitur', value: total.total_deb, meta: 'MTD debitur' }, 0.55, 1.22, 2.3, 1.22, PPT_THEME.blue);
    addPptMetricCard(pptx, slide, { label: 'Total Plafon/OS', value: total.total_os_fmt, meta: 'MTD outstanding' }, 3.05, 1.22, 2.65, 1.22, PPT_THEME.teal);
    addPptSectionLabel(slide, 'Top Unit Per Pemutus', 0.55, 2.82, 4.2);
    addPptTable(pptx, slide, ['Unit', 'KAUNIT', 'MBM', 'PINCA', 'RMBH', 'Deb', 'OS'], pptRows(payload?.micro?.decision?.rows, 8), (row) => [
      { text: row.unit || '-', options: { bold: true } },
      { text: row.kaunit_deb ?? '-', options: { align: 'right' } },
      { text: row.mbm_deb ?? '-', options: { align: 'right' } },
      { text: row.pinca_deb ?? '-', options: { align: 'right' } },
      { text: row.rmbh_deb ?? '-', options: { align: 'right' } },
      { text: row.total_deb ?? '-', options: { align: 'right', bold: true } },
      { text: row.total_os_fmt || '-', options: { align: 'right' } }
    ], 0.55, 3.18, 12.15, 3.35, [3.0, 1.0, 0.9, 0.9, 0.9, 0.8, 2.1]);
  };

  const renderPptProductivitySlide = (pptx, payload, logos) => {
    const slide = pptx.addSlide();
    addPptChrome(pptx, slide, payload, 'Produktivitas Mikro', 'Produktivitas Mantri dan RM KUR Mikro', logos);
    addPptSectionLabel(slide, 'Produktivitas Mantri', 0.55, 1.12, 4.2);
    addPptTable(pptx, slide, ['Mantri', 'Unit', 'Deb', 'OS', 'Ket'], pptRows(payload?.micro?.mantri_productivity?.rows, 8), (row) => [
      { text: row.nama_mantri || '-', options: { bold: true } },
      row.unit || '-',
      { text: row.realisasi_deb ?? '-', options: { align: 'right' } },
      { text: row.realisasi_os_fmt || '-', options: { align: 'right' } },
      row.ket || '-'
    ], 0.55, 1.48, 5.95, 4.85, [1.75, 1.35, 0.55, 1.15, 1.15]);

    addPptSectionLabel(slide, 'Produktivitas RM Mikro', 6.78, 1.12, 4.2);
    addPptTable(pptx, slide, ['RM', 'Unit', 'Deb', 'OS'], pptRows(payload?.micro?.rm_kur_micro?.rows, 8), (row) => [
      { text: row.nama || '-', options: { bold: true } },
      row.unit || '-',
      { text: row.realisasi_deb ?? row.total_deb ?? '-', options: { align: 'right' } },
      { text: row.realisasi_os_fmt || row.total_os_fmt || '-', options: { align: 'right' } }
    ], 6.78, 1.48, 5.92, 4.85, [1.85, 1.55, 0.65, 1.55]);
  };

  const renderPptQualitySlide = (pptx, payload, logos) => {
    const slide = pptx.addSlide();
    addPptChrome(pptx, slide, payload, 'Kinerja SML dan NPL Area 6 - Region Malang', 'Nominal dan rasio dari snapshot landing', logos);
    const smlCard = payload?.quality?.sml?.card || {};
    const nplCard = payload?.quality?.npl?.card || {};
    addPptMetricCard(pptx, slide, { label: 'SML Nominal', value: smlCard.realization_value ? `Rp ${smlCard.realization_value} Juta` : PPT_NA, meta: smlCard.pct_value || '-' }, 0.55, 1.15, 2.9, 1.22, PPT_THEME.orange);
    addPptMetricCard(pptx, slide, { label: 'NPL Nominal', value: nplCard.realization_value ? `Rp ${nplCard.realization_value} Juta` : PPT_NA, meta: nplCard.pct_value || '-' }, 3.72, 1.15, 2.9, 1.22, PPT_THEME.red);

    addPptSectionLabel(slide, 'Top SML Nominal', 0.55, 2.72, 3.8, PPT_THEME.orange);
    addPptTable(pptx, slide, ['Unit', 'Cabang', 'Nominal', 'Rasio'], pptRows([...(payload?.quality?.sml?.ritel_nominal || []), ...(payload?.quality?.sml?.micro_nominal || [])], 5), (row) => [
      { text: row.name || row.unit || '-', options: { bold: true } },
      row.branch || row.cabang || '-',
      { text: row.value || row.amount || '-', options: { align: 'right' } },
      { text: row.secondary || row.ratio || '-', options: { align: 'right' } }
    ], 0.55, 3.08, 5.95, 3.18, [2.2, 1.35, 1.2, 1.0]);

    addPptSectionLabel(slide, 'Top NPL Nominal', 6.78, 2.72, 3.8, PPT_THEME.red);
    addPptTable(pptx, slide, ['Unit', 'Cabang', 'Nominal', 'Rasio'], pptRows([...(payload?.quality?.npl?.ritel_nominal || []), ...(payload?.quality?.npl?.micro_nominal || [])], 5), (row) => [
      { text: row.name || row.unit || '-', options: { bold: true } },
      row.branch || row.cabang || '-',
      { text: row.value || row.amount || '-', options: { align: 'right' } },
      { text: row.secondary || row.ratio || '-', options: { align: 'right' } }
    ], 6.78, 3.08, 5.92, 3.18, [2.2, 1.35, 1.2, 1.0]);
  };

  const renderPptKtsSlide = (pptx, payload, logos) => {
    const slide = pptx.addSlide();
    addPptChrome(pptx, slide, payload, 'Kolek Tidak Sesuai', 'Top 5 KTS Ritel dan Micro dari daily_loan_dinamis', logos);
    addPptSectionLabel(slide, 'Top 5 KTS Ritel', 0.55, 1.22, 4.2, PPT_THEME.orange);
    addPptTable(pptx, slide, ['Unit Kerja', 'Cabang', 'Rekening', 'OS'], pptRows(payload?.kts?.ritel, 5), (row) => [
      { text: row.name || row.unit || '-', options: { bold: true } },
      row.branch || row.cabang || '-',
      { text: row.value || row.count || '-', options: { align: 'right', bold: true } },
      { text: row.secondary || row.amount || '-', options: { align: 'right' } }
    ], 0.55, 1.62, 5.95, 3.25, [2.35, 1.35, 0.95, 1.1]);

    addPptSectionLabel(slide, 'Top 5 KTS Micro', 6.78, 1.22, 4.2, PPT_THEME.teal);
    addPptTable(pptx, slide, ['Unit Kerja', 'Cabang', 'Rekening', 'OS'], pptRows(payload?.kts?.micro, 5), (row) => [
      { text: row.name || row.unit || '-', options: { bold: true } },
      row.branch || row.cabang || '-',
      { text: row.value || row.count || '-', options: { align: 'right', bold: true } },
      { text: row.secondary || row.amount || '-', options: { align: 'right' } }
    ], 6.78, 1.62, 5.92, 3.25, [2.35, 1.35, 0.95, 1.1]);
  };

  const renderPptDigitalSlide = (pptx, payload, logos) => {
    const slide = pptx.addSlide();
    addPptChrome(pptx, slide, payload, '8 Strategi Dana dan Digital', 'EDC, QRIS, QLola, BRIMO, BRILink, CASA, Dormant, Payroll', logos);
    const cards = Array.isArray(payload?.digital_strategy?.cards) ? payload.digital_strategy.cards : [];
    const colors = [PPT_THEME.blue, PPT_THEME.teal, PPT_THEME.orange, PPT_THEME.blueDark, PPT_THEME.green, PPT_THEME.red, '7C3AED', 'BE123C'];
    cards.slice(0, 8).forEach((card, index) => {
      const col = index % 4;
      const row = Math.floor(index / 4);
      const x = 0.55 + col * 3.08;
      const y = 1.34 + row * 2.35;
      addPptRect(pptx, slide, x, y, 2.72, 1.78, PPT_THEME.white, 'D9E2EF');
      addPptRect(pptx, slide, x, y, 2.72, 0.08, colors[index] || PPT_THEME.blue);
      slide.addText(safePptText(card.title, '-'), {
        x: x + 0.16, y: y + 0.2, w: 2.38, h: 0.24,
        fontFace: PPT_THEME.font, fontSize: 8, bold: true, color: colors[index] || PPT_THEME.blue,
        margin: 0, fit: 'shrink'
      });
      slide.addText(safePptText(card.current_value), {
        x: x + 0.16, y: y + 0.58, w: 2.38, h: 0.4,
        fontFace: PPT_THEME.font, fontSize: 15, bold: true, color: PPT_THEME.slate,
        margin: 0, fit: 'shrink'
      });
      slide.addText(safePptText(card.secondary_value, '-'), {
        x: x + 0.16, y: y + 1.05, w: 2.38, h: 0.22,
        fontFace: PPT_THEME.font, fontSize: 7.2, color: PPT_THEME.muted,
        margin: 0, fit: 'shrink'
      });
      slide.addText(`Trend ${safePptText(card.trend, '-')}`, {
        x: x + 0.16, y: y + 1.42, w: 2.38, h: 0.18,
        fontFace: PPT_THEME.font, fontSize: 6.8, color: PPT_THEME.muted,
        margin: 0
      });
    });

    if (!cards.length) {
      addPptUnavailable(pptx, slide, 0.55, 1.34, 12.15, 4.5);
    }
  };

  const RO_THEME = {
    blue: '0057C2',
    blue2: '0070C0',
    darkBlue: '003A8C',
    cyan: '00AEEF',
    cyan2: '26BDEB',
    red: 'E30613',
    green: '00A651',
    amber: 'FFC000',
    gray: '7F7F7F',
    darkText: '002060',
    line: 'B7C9EA',
    paleBlue: 'DDF3F8',
    paleGray: 'F2F2F2',
    white: 'FFFFFF'
  };

  const roCell = (text, options = {}) => ({
    text: safePptText(text, '-'),
    options: {
      fontFace: PPT_THEME.font,
      fontSize: 6,
      color: RO_THEME.darkText,
      margin: 0.02,
      breakLine: false,
      fit: 'shrink',
      valign: 'mid',
      ...options
    }
  });

  const pptSummaryCardByKey = (payload, key) => {
    const cards = Array.isArray(payload?.summary?.cards) ? payload.summary.cards : [];
    return cards.find((card) => card?.key === key) || {};
  };

  const parsePptNumber = (value) => {
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;
    const raw = String(value ?? '').trim();
    if (!raw || raw === '-' || raw === PPT_NA) return null;
    const normalized = raw
      .replace(/\(([^)]+)\)/g, '-$1')
      .replace(/[^\d,.-]/g, '')
      .replace(/\./g, '')
      .replace(',', '.');
    const number = Number(normalized);
    return Number.isFinite(number) ? number : null;
  };

  const formatPptInt = (value, fallback = '-') => {
    const number = safePptNumber(value, NaN);
    return Number.isFinite(number)
      ? Math.round(number).toLocaleString('id-ID')
      : fallback;
  };

  const formatPptSigned = (value, fallback = '-') => {
    const number = safePptNumber(value, NaN);
    if (!Number.isFinite(number)) return fallback;
    const formatted = formatPptInt(Math.abs(number));
    return number < 0 ? `(${formatted})` : formatted;
  };

  const formatPptJutaFromRaw = (value, fallback = '-') => {
    const number = safePptNumber(value, NaN);
    return Number.isFinite(number) ? formatPptInt(number / 1000000) : fallback;
  };

  const getRoMetric = (payload, key) => {
    const total = payload?.performance_overview?.total?.[key] || {};
    const card = pptSummaryCardByKey(payload, key === 'os' ? 'os' : key);
    const realization = total.realization_fmt || formatPptJutaFromRaw(card.value_raw) || card.value || '-';
    const target = total.target_fmt || '-';
    const pct = total.pct_fmt || card.ratio || (String(card.value || '').includes('%') ? card.value : '-');
    const realNumber = parsePptNumber(realization);
    const targetNumber = parsePptNumber(target);
    const gapNumber = realNumber !== null && targetNumber !== null
      ? (key === 'os' ? realNumber - targetNumber : targetNumber - realNumber)
      : null;

    return {
      key,
      label: key === 'os' ? 'OUTSTANDING (OS)' : (key === 'sml' ? 'SPECIAL MENTION LOAN (SML)' : 'NON-PERFORMING LOAN (NPL)'),
      shortLabel: key.toUpperCase(),
      realization,
      target,
      pct,
      gap: gapNumber === null ? '-' : formatPptSigned(gapNumber),
      trend: card.trend || '-',
      color: key === 'os' ? RO_THEME.blue : (key === 'sml' ? RO_THEME.cyan : RO_THEME.cyan2)
    };
  };

  const pctTextColor = (pctText, metricKey = 'os') => {
    const pct = parsePptNumber(pctText);
    if (pct === null) return RO_THEME.darkText;
    if (metricKey === 'os') {
      if (pct >= 100) return RO_THEME.green;
      if (pct >= 95) return RO_THEME.amber;
      return RO_THEME.red;
    }
    if (pct >= 100) return RO_THEME.green;
    if (pct >= 80) return RO_THEME.amber;
    return RO_THEME.red;
  };

  const roSlideNo = (slide, no) => {
    slide.addText(String(no), {
      x: 12.92, y: 7.23, w: 0.22, h: 0.12,
      fontFace: PPT_THEME.font, fontSize: 6.4, color: '000000',
      align: 'right', margin: 0
    });
  };

  const addRoChrome = (pptx, slide, payload, title, logos, slideNo) => {
    addPptRect(pptx, slide, 0, 0, 13.33, 7.5, RO_THEME.white);
    if (logos.danantara) {
      slide.addImage({ data: logos.danantara, x: 0.6, y: 0.27, w: 1.08, h: 0.36 });
    }
    if (logos.bri) {
      slide.addImage({ data: logos.bri, x: 11.84, y: 0.24, w: 0.82, h: 0.38 });
    }
    slide.addText(title, {
      x: 1.75, y: 0.17, w: 9.9, h: 0.42,
      fontFace: PPT_THEME.font, fontSize: 18.6,
      bold: true, italic: String(title).includes(' - '),
      color: RO_THEME.blue,
      align: 'center',
      margin: 0,
      fit: 'shrink'
    });
    addPptRect(pptx, slide, 0.6, 0.77, 12.15, 0.015, RO_THEME.line);
    roSlideNo(slide, slideNo);
  };

  const addRoPanel = (pptx, slide, x, y, w, h, lineColor = RO_THEME.cyan) => {
    addPptRect(pptx, slide, x, y, w, h, RO_THEME.white, lineColor);
  };

  const addRoRibbon = (pptx, slide, text, x, y, w, color = RO_THEME.cyan, fontSize = 9.2) => {
    addPptRect(pptx, slide, x, y, w, 0.28, color);
    slide.addText(text, {
      x: x + 0.1, y: y + 0.05, w: w - 0.16, h: 0.16,
      fontFace: PPT_THEME.font, fontSize, bold: true, color: RO_THEME.white,
      margin: 0, fit: 'shrink'
    });
  };

  const addRoMetricCard = (pptx, slide, metric, periodLabel, x, y, w, h) => {
    addRoPanel(pptx, slide, x, y, w, h, 'D9E2EF');
    addPptRect(pptx, slide, x, y, w, 0.3, metric.color);
    slide.addText(metric.label, {
      x: x + 0.1, y: y + 0.08, w: w - 0.2, h: 0.12,
      fontFace: PPT_THEME.font, fontSize: 6.6, bold: true,
      color: RO_THEME.white, align: 'center', margin: 0, fit: 'shrink'
    });

    slide.addText(`${metric.shortLabel} per ${safePptText(periodLabel, '-')}`, {
      x: x + 0.22, y: y + 0.55, w: 1.45, h: 0.16,
      fontFace: PPT_THEME.font, fontSize: 6.2, bold: true, color: RO_THEME.darkText,
      align: 'center', margin: 0, fit: 'shrink'
    });
    slide.addText(metric.realization, {
      x: x + 0.18, y: y + 0.78, w: 1.52, h: 0.34,
      fontFace: PPT_THEME.font, fontSize: 15, bold: true, color: RO_THEME.darkText,
      align: 'center', margin: 0, fit: 'shrink'
    });
    slide.addText('Rp Juta', {
      x: x + 0.45, y: y + 1.12, w: 0.95, h: 0.12,
      fontFace: PPT_THEME.font, fontSize: 5.8, bold: true, color: RO_THEME.darkText,
      align: 'center', margin: 0
    });

    slide.addText('RKA Mei 26', {
      x: x + w - 1.62, y: y + 0.55, w: 1.35, h: 0.16,
      fontFace: PPT_THEME.font, fontSize: 6.2, bold: true, color: RO_THEME.darkText,
      align: 'center', margin: 0, fit: 'shrink'
    });
    slide.addText(metric.target, {
      x: x + w - 1.72, y: y + 0.8, w: 1.55, h: 0.3,
      fontFace: PPT_THEME.font, fontSize: 13.2, bold: true, color: RO_THEME.darkText,
      align: 'center', margin: 0, fit: 'shrink'
    });
    slide.addText('Rp Juta', {
      x: x + w - 1.4, y: y + 1.12, w: 0.95, h: 0.12,
      fontFace: PPT_THEME.font, fontSize: 5.8, bold: true, color: RO_THEME.darkText,
      align: 'center', margin: 0
    });

    addPptRect(pptx, slide, x + w / 2, y + 0.48, 0.01, 0.86, 'E5E7EB');
    addPptRect(pptx, slide, x + 0.16, y + 1.45, w - 0.32, 0.01, 'E5E7EB');

    slide.addText('% Penc. RKA Mei 26', {
      x: x + 0.2, y: y + 1.58, w: 1.45, h: 0.16,
      fontFace: PPT_THEME.font, fontSize: 5.8, bold: true, color: RO_THEME.darkText,
      align: 'center', margin: 0, fit: 'shrink'
    });
    slide.addText(metric.pct, {
      x: x + 0.25, y: y + 1.81, w: 1.35, h: 0.26,
      fontFace: PPT_THEME.font, fontSize: 12.5, bold: true,
      color: pctTextColor(metric.pct, metric.key), align: 'center', margin: 0, fit: 'shrink'
    });

    slide.addText('Gap thd RKA Mei 26', {
      x: x + w - 1.7, y: y + 1.58, w: 1.45, h: 0.16,
      fontFace: PPT_THEME.font, fontSize: 5.8, bold: true, color: RO_THEME.darkText,
      align: 'center', margin: 0, fit: 'shrink'
    });
    slide.addText(metric.gap, {
      x: x + w - 1.7, y: y + 1.82, w: 1.45, h: 0.22,
      fontFace: PPT_THEME.font, fontSize: 8.5, bold: true,
      color: String(metric.gap).includes('(') ? RO_THEME.red : RO_THEME.green,
      align: 'center', margin: 0, fit: 'shrink'
    });
  };

  const addRoMiniBar = (pptx, slide, x, y, w, value, target, color) => {
    const real = Math.max(parsePptNumber(value) || 0, 0);
    const rka = Math.max(parsePptNumber(target) || 0, 0);
    const max = Math.max(real, rka, 1);
    addPptRect(pptx, slide, x, y, w, 0.055, 'D9E2EF');
    addPptRect(pptx, slide, x, y, w * Math.min(real / max, 1), 0.055, color);
    addPptRect(pptx, slide, x, y + 0.09, w, 0.055, 'E5E7EB');
    addPptRect(pptx, slide, x, y + 0.09, w * Math.min(rka / max, 1), 0.055, 'CBD5E1');
  };

  const addRoProductPanel = (pptx, slide, payload, x, y, w, h) => {
    addRoPanel(pptx, slide, x, y, w, h, 'D9E2EF');
    addRoRibbon(pptx, slide, 'KINERJA PER SEGMEN (Rp Juta)', x, y, 3.15, RO_THEME.blue2, 7.2);
    const segments = Array.isArray(payload?.performance_overview?.segments) ? payload.performance_overview.segments : [];
    const total = payload?.performance_overview?.total || {};
    const rows = [...segments.slice(0, 3), { label: 'TOTAL', ...total, isTotal: true }];
    const metricXs = [
      { key: 'os', label: 'OUTSTANDING (OS)', x: x + 1.55, color: RO_THEME.blue },
      { key: 'sml', label: 'SPECIAL MENTION LOAN (SML)', x: x + 3.8, color: RO_THEME.cyan },
      { key: 'npl', label: 'NON-PERFORMING LOAN (NPL)', x: x + 6.05, color: RO_THEME.cyan2 },
    ];

    slide.addText('Segment', { x: x + 0.15, y: y + 0.52, w: 1.1, h: 0.14, fontFace: PPT_THEME.font, fontSize: 5.6, bold: true, color: RO_THEME.darkText, margin: 0 });
    metricXs.forEach((metric) => {
      slide.addText(metric.label, {
        x: metric.x, y: y + 0.48, w: 1.9, h: 0.14,
        fontFace: PPT_THEME.font, fontSize: 4.9, bold: true,
        color: RO_THEME.darkText, align: 'center', margin: 0, fit: 'shrink'
      });
    });

    rows.forEach((row, index) => {
      const yy = y + 0.78 + index * 0.33;
      const bg = row.isTotal ? 'E6F7FC' : (index % 2 === 0 ? 'F8FAFC' : RO_THEME.white);
      addPptRect(pptx, slide, x + 0.08, yy - 0.04, w - 0.16, 0.28, bg);
      slide.addText(safePptText(row.label, '-').replace(/^OS\s+/i, ''), {
        x: x + 0.16, y: yy + 0.02, w: 1.18, h: 0.12,
        fontFace: PPT_THEME.font, fontSize: 5.6, bold: true, color: RO_THEME.darkText,
        margin: 0, fit: 'shrink'
      });
      metricXs.forEach((metric) => {
        const data = row?.[metric.key] || {};
        addRoMiniBar(pptx, slide, metric.x, yy + 0.01, 0.7, data.realization_fmt || data.value || '-', data.target_fmt || '-', metric.color);
        slide.addText(data.realization_fmt || data.value || '-', {
          x: metric.x + 0.78, y: yy - 0.02, w: 0.54, h: 0.12,
          fontFace: PPT_THEME.font, fontSize: 4.8, bold: true, color: RO_THEME.darkText,
          align: 'right', margin: 0, fit: 'shrink'
        });
        slide.addText(data.pct_fmt || '-', {
          x: metric.x + 1.38, y: yy - 0.02, w: 0.45, h: 0.12,
          fontFace: PPT_THEME.font, fontSize: 4.6, bold: true, color: pctTextColor(data.pct_fmt, metric.key),
          align: 'right', margin: 0, fit: 'shrink'
        });
      });
    });
  };

  const addRoCompositionPanel = (pptx, slide, payload, x, y, w, h) => {
    addRoPanel(pptx, slide, x, y, w, h, 'D9E2EF');
    addRoRibbon(pptx, slide, 'KOMPOSISI TOTAL (Rp Juta)', x, y, 2.65, RO_THEME.blue2, 7.2);
    const composition = payload?.performance_overview?.composition || {};
    const items = [
      { key: 'os', label: 'LAR', color: RO_THEME.blue },
      { key: 'sml', label: 'SML', color: RO_THEME.cyan },
      { key: 'npl', label: 'NPL', color: RO_THEME.red },
    ];
    const ellipse = pptx.ShapeType?.ellipse || 'ellipse';
    try {
      slide.addShape(ellipse, {
        x: x + 0.38, y: y + 0.62, w: 1.45, h: 1.45,
        fill: { color: RO_THEME.blue },
        line: { color: RO_THEME.blue, transparency: 100 }
      });
      slide.addShape(ellipse, {
        x: x + 0.74, y: y + 0.98, w: 0.73, h: 0.73,
        fill: { color: RO_THEME.white },
        line: { color: RO_THEME.white, transparency: 100 }
      });
    } catch (error) {
      addPptRect(pptx, slide, x + 0.55, y + 0.78, 1.05, 1.05, RO_THEME.blue);
    }
    slide.addText(safePptText(composition?.center?.pct || composition?.os?.pct, '-'), {
      x: x + 0.68, y: y + 1.2, w: 0.84, h: 0.16,
      fontFace: PPT_THEME.font, fontSize: 8.4, bold: true, color: RO_THEME.darkText,
      align: 'center', margin: 0, fit: 'shrink'
    });

    items.forEach((item, index) => {
      const row = composition?.[item.key] || {};
      const yy = y + 0.68 + index * 0.45;
      addPptRect(pptx, slide, x + 2.15, yy + 0.03, 0.12, 0.12, item.color);
      slide.addText(item.label, {
        x: x + 2.38, y: yy, w: 0.55, h: 0.13,
        fontFace: PPT_THEME.font, fontSize: 5.8, bold: true, color: RO_THEME.darkText,
        margin: 0
      });
      slide.addText(`${safePptText(row.value, '-')} (${safePptText(row.pct, '-')})`, {
        x: x + 2.95, y: yy, w: 0.92, h: 0.13,
        fontFace: PPT_THEME.font, fontSize: 5.6, color: RO_THEME.darkText,
        margin: 0, fit: 'shrink'
      });
    });
    addPptRect(pptx, slide, x + 1.98, y + h - 0.5, w - 2.18, 0.34, 'F8FAFC', 'E5E7EB');
    slide.addText(`TOTAL PORTOFOLIO KREDIT\n${safePptText(composition?.total?.value, '-')}`, {
      x: x + 2.05, y: y + h - 0.44, w: w - 2.32, h: 0.23,
      fontFace: PPT_THEME.font, fontSize: 6.2, bold: true, color: RO_THEME.darkText,
      align: 'center', margin: 0, fit: 'shrink'
    });
  };

  const getPptSeries = (payload, key) => {
    const series = Array.isArray(payload?.timeseries?.series) ? payload.timeseries.series : [];
    return series.find((item) => item?.key === key) || { key, label: key, values: [], display_values: [] };
  };

  const addRoTrendPanel = (pptx, slide, title, series, labels, x, y, w, h, color = RO_THEME.blue) => {
    addRoPanel(pptx, slide, x, y, w, h, color);
    addRoRibbon(pptx, slide, title, x + 0.12, y + 0.18, Math.min(w - 0.32, 2.45), color, 8);
    const values = Array.isArray(series?.values) ? series.values.map((value) => safePptNumber(value, 0)) : [];
    if (!values.length) {
      slide.addText(PPT_NA, {
        x: x + 0.2, y: y + h / 2 - 0.08, w: w - 0.4, h: 0.16,
        fontFace: PPT_THEME.font, fontSize: 7, color: RO_THEME.gray,
        align: 'center', margin: 0
      });
      return;
    }
    const chartX = x + 0.22;
    const chartY = y + 0.72;
    const chartW = w - 0.72;
    const chartH = h - 1.04;
    const min = Math.min(...values, 0);
    const max = Math.max(...values, 1);
    const range = Math.max(max - min, 1);
    const points = values.map((value, index) => ({
      x: chartX + (values.length <= 1 ? chartW / 2 : (chartW / (values.length - 1)) * index),
      y: chartY + chartH - ((value - min) / range) * chartH
    }));

    addPptLine(pptx, slide, chartX, chartY + chartH, chartX + chartW, chartY + chartH, 'E5E7EB', 0.8);
    points.forEach((point, index) => {
      if (index > 0) {
        addPptLine(pptx, slide, points[index - 1].x, points[index - 1].y, point.x, point.y, color, 1.25);
      }
      addPptRect(pptx, slide, point.x - 0.025, point.y - 0.025, 0.05, 0.05, color);
    });

    const latest = values[values.length - 1];
    slide.addText(formatPptInt(latest), {
      x: chartX + chartW - 0.58, y: chartY + 0.1, w: 0.72, h: 0.18,
      fontFace: PPT_THEME.font, fontSize: 7.2, bold: true, color: RO_THEME.white,
      fill: { color }, align: 'center', margin: 0, fit: 'shrink'
    });
    (labels || []).slice(0, values.length).forEach((label, index) => {
      const px = chartX + (values.length <= 1 ? chartW / 2 : (chartW / (values.length - 1)) * index);
      slide.addText(String(index + 1), {
        x: px - 0.07, y: chartY + chartH + 0.05, w: 0.14, h: 0.08,
        fontFace: PPT_THEME.font, fontSize: 3.6, color: RO_THEME.darkText,
        align: 'center', margin: 0
      });
    });
  };

  const addRoPerformanceTable = (pptx, slide, payload, x, y, w, h) => {
    const rows = [
      ['Indikator', 'Posisi', 'RKA Mei 26', '% Penc.', 'Gap thd RKA'],
      ...['os', 'sml', 'npl'].map((key) => {
        const metric = getRoMetric(payload, key);
        return [metric.shortLabel, metric.realization, metric.target, metric.pct, metric.gap];
      })
    ];
    addRoPanel(pptx, slide, x, y, w, h, 'D9E2EF');
    addRoRibbon(pptx, slide, 'Performance Vs RKA', x, y, w, RO_THEME.blue2, 6.8);
    slide.addTable(rows.map((row, rowIndex) => row.map((cell, colIndex) => roCell(cell, {
      fill: rowIndex === 0 ? 'F8FAFC' : RO_THEME.white,
      bold: rowIndex === 0 || colIndex === 0,
      align: colIndex === 0 ? 'center' : 'right',
      color: rowIndex > 0 && colIndex === 3 ? pctTextColor(cell, rows[rowIndex][0].toLowerCase()) : RO_THEME.darkText,
      fontSize: rowIndex === 0 ? 4.8 : 5.2
    }))), {
      x: x + 0.08, y: y + 0.36, w: w - 0.16, h: h - 0.44,
      colW: [0.62, 0.86, 0.86, 0.72, 0.86],
      border: { pt: 0.25, color: 'E5E7EB' },
      margin: 0.01
    });
  };

  const renderRoCoverSlide = (pptx, payload, logos) => {
    const slide = pptx.addSlide();
    if (logos.coverBase) {
      slide.addImage({ data: logos.coverBase, x: 0, y: 0, w: 13.33, h: 7.5 });
      addPptRect(pptx, slide, 0.45, 2.72, 6.75, 1.38, RO_THEME.white);
    } else {
      addPptRect(pptx, slide, 0, 0, 13.33, 7.5, RO_THEME.white);
      if (logos.danantara) slide.addImage({ data: logos.danantara, x: 0.58, y: 0.42, w: 1.35, h: 0.46 });
      if (logos.bri) slide.addImage({ data: logos.bri, x: 2.6, y: 0.42, w: 1.04, h: 0.46 });
      addPptRect(pptx, slide, 7.42, 0.95, 5.0, 5.55, 'F2F8FF', RO_THEME.blue);
    }

    slide.addText('Materi Pendukung Asistensi', {
      x: 0.62, y: 3.04, w: 6.45, h: 0.45,
      fontFace: PPT_THEME.font, fontSize: 25, bold: true,
      color: RO_THEME.blue, margin: 0, fit: 'shrink'
    });
    addPptRect(pptx, slide, 0.62, 3.52, 6.32, 0.015, RO_THEME.line);
    slide.addText('Area 6 - Region Malang', {
      x: 0.62, y: 3.62, w: 4.85, h: 0.26,
      fontFace: PPT_THEME.font, fontSize: 14.2, italic: true,
      color: RO_THEME.blue, margin: 0, fit: 'shrink'
    });
  };

  const renderRoSelayangSlide = (pptx, payload, logos, slideNo) => {
    const slide = pptx.addSlide();
    addRoChrome(pptx, slide, payload, 'Selayang Pandang, Area 6 - Region Malang', logos, slideNo);
    const metrics = ['os', 'sml', 'npl'].map((key) => getRoMetric(payload, key));
    metrics.forEach((metric, index) => addRoMetricCard(pptx, slide, metric, payload?.meta?.period_label, 0.62 + index * 4.06, 0.95, 3.78, 2.18));
    addRoProductPanel(pptx, slide, payload, 0.62, 3.28, 7.9, 2.18);
    addRoCompositionPanel(pptx, slide, payload, 8.68, 3.28, 4.05, 2.18);
    addRoPanel(pptx, slide, 0.62, 5.66, 7.0, 1.2, 'D9E2EF');
    addRoRibbon(pptx, slide, 'TREND POSISI (Rp Juta)', 0.62, 5.66, 2.4, RO_THEME.blue2, 6.8);
    const labels = payload?.timeseries?.labels || [];
    addRoTrendPanel(pptx, slide, 'OS', getPptSeries(payload, 'os_total'), labels, 0.82, 5.98, 2.0, 0.72, RO_THEME.blue);
    addRoTrendPanel(pptx, slide, 'SML', getPptSeries(payload, 'sml_nominal'), labels, 2.96, 5.98, 2.0, 0.72, RO_THEME.cyan);
    addRoTrendPanel(pptx, slide, 'NPL', getPptSeries(payload, 'npl_nominal'), labels, 5.1, 5.98, 2.0, 0.72, RO_THEME.red);
    addRoPerformanceTable(pptx, slide, payload, 7.8, 5.66, 4.93, 1.2);
  };

  const addRoOverviewBlock = (pptx, slide, payload, metricKey, x, y, w, h) => {
    const labelColor = metricKey === 'os' ? RO_THEME.blue2 : (metricKey === 'sml' ? RO_THEME.blue2 : RO_THEME.blue2);
    addPptRect(pptx, slide, x, y, 0.38, h, labelColor);
    slide.addText(metricKey.toUpperCase(), {
      x: x - 0.06, y: y + h / 2 - 0.11, w: 0.5, h: 0.22,
      fontFace: PPT_THEME.font, fontSize: 14, bold: true, color: RO_THEME.white,
      rotate: 270, align: 'center', margin: 0
    });

    const segments = Array.isArray(payload?.performance_overview?.segments) ? payload.performance_overview.segments : [];
    const total = payload?.performance_overview?.total || {};
    const rows = [
      ['Regional Office', 'Kinerja', 'Posisi', 'RKA Mei 26', 'Delta', '%Penc.', 'Gap thd RKA'],
      ...segments.slice(0, 3).map((segment) => {
        const metric = segment?.[metricKey] || {};
        const real = metric.realization_fmt || metric.value || '-';
        const target = metric.target_fmt || '-';
        const rn = parsePptNumber(real);
        const tn = parsePptNumber(target);
        const gap = rn !== null && tn !== null ? (metricKey === 'os' ? rn - tn : tn - rn) : null;
        return ['Area 6', safePptText(segment.label, '-').replace(/^OS\s+/i, ''), real, target, gap === null ? '-' : formatPptSigned(gap), metric.pct_fmt || '-', gap === null ? '-' : formatPptSigned(gap)];
      }),
      (() => {
        const metric = total?.[metricKey] || {};
        const real = metric.realization_fmt || '-';
        const target = metric.target_fmt || '-';
        const rn = parsePptNumber(real);
        const tn = parsePptNumber(target);
        const gap = rn !== null && tn !== null ? (metricKey === 'os' ? rn - tn : tn - rn) : null;
        return ['Area 6', `Total ${metricKey.toUpperCase()}`, real, target, gap === null ? '-' : formatPptSigned(gap), metric.pct_fmt || '-', gap === null ? '-' : formatPptSigned(gap)];
      })()
    ];

    slide.addTable(rows.map((row, rowIndex) => row.map((cell, colIndex) => roCell(cell, {
      fill: rowIndex === 0 ? RO_THEME.blue2 : (rowIndex === rows.length - 1 ? 'E5E5E5' : (rowIndex % 2 ? RO_THEME.white : 'F6F6F6')),
      color: rowIndex === 0 ? RO_THEME.white : (rowIndex > 0 && [4, 6].includes(colIndex) && String(cell).includes('(') ? RO_THEME.red : RO_THEME.darkText),
      bold: rowIndex === 0 || rowIndex === rows.length - 1 || colIndex === 1,
      align: colIndex <= 1 ? 'left' : 'right',
      fontSize: rowIndex === 0 ? 5.4 : 5.15
    }))), {
      x: x + 0.62, y, w: w - 0.62, h,
      colW: [1.35, 2.05, 1.15, 1.15, 1.1, 0.82, 1.18],
      border: { pt: 0.2, color: 'D9E2EF' },
      margin: 0.01
    });
  };

  const renderRoOverviewSlide = (pptx, payload, logos, slideNo) => {
    const slide = pptx.addSlide();
    addRoChrome(pptx, slide, payload, `Performance Overview Area 6 sd ${safePptText(payload?.meta?.period_label, '-')}`, logos, slideNo);
    addRoOverviewBlock(pptx, slide, payload, 'os', 0.42, 0.98, 12.4, 1.65);
    addRoOverviewBlock(pptx, slide, payload, 'sml', 0.42, 2.88, 12.4, 1.65);
    addRoOverviewBlock(pptx, slide, payload, 'npl', 0.42, 4.78, 12.4, 1.65);
  };

  const renderRoTrendlineSlide = (pptx, payload, logos, slideNo) => {
    const slide = pptx.addSlide();
    addRoChrome(pptx, slide, payload, 'Trendline Realisasi - Area 6 Region Malang', logos, slideNo);
    const labels = payload?.timeseries?.labels || [];
    addRoTrendPanel(pptx, slide, 'Realisasi Simpanan', getPptSeries(payload, 'simpanan_total'), labels, 0.55, 0.95, 5.95, 2.75, RO_THEME.blue);
    addRoTrendPanel(pptx, slide, 'Realisasi OS', getPptSeries(payload, 'os_total'), labels, 6.74, 0.95, 5.95, 2.75, RO_THEME.cyan);
    addRoTrendPanel(pptx, slide, 'Realisasi SML', getPptSeries(payload, 'sml_nominal'), labels, 0.55, 4.02, 5.95, 2.45, RO_THEME.cyan2);
    addRoTrendPanel(pptx, slide, 'Realisasi NPL', getPptSeries(payload, 'npl_nominal'), labels, 6.74, 4.02, 5.95, 2.45, RO_THEME.red);
  };

  const addRoDenseTable = (pptx, slide, headers, rows, x, y, w, h, colW, options = {}) => {
    if (!rows.length) {
      addPptUnavailable(pptx, slide, x, y, w, h);
      return;
    }
    const tableRows = [
      headers.map((header) => roCell(header, {
        fill: options.headerFill || RO_THEME.blue2,
        color: RO_THEME.white,
        bold: true,
        align: 'center',
        fontSize: options.headerFontSize || 5.6
      })),
      ...rows.map((row, index) => row.map((cell, colIndex) => roCell(cell, {
        fill: index === rows.length - 1 && options.totalLast ? RO_THEME.blue2 : (index % 2 === 0 ? RO_THEME.white : 'F4FBFD'),
        color: index === rows.length - 1 && options.totalLast ? RO_THEME.white : RO_THEME.darkText,
        bold: colIndex === 0 || index === rows.length - 1,
        align: colIndex === 0 ? 'left' : 'right',
        fontSize: options.fontSize || 5.4
      })))
    ];
    slide.addTable(tableRows, {
      x, y, w, h,
      colW,
      border: { pt: 0.2, color: 'D9E2EF' },
      margin: 0.01
    });
  };

  const renderRoDecisionSlide = (pptx, payload, logos, slideNo) => {
    const slide = pptx.addSlide();
    addRoChrome(pptx, slide, payload, `Evaluasi Putusan Area 6 - ${safePptText(payload?.meta?.daily_loan_period_label || payload?.meta?.period_label, '-')}`, logos, slideNo);
    const rows = pptRows(payload?.micro?.decision?.rows, 22).map((row) => [
      row.cabang || row.unit || '-',
      row.unit || '-',
      row.kaunit_deb ?? '-',
      row.mbm_deb ?? '-',
      row.pinca_deb ?? '-',
      row.rmbh_deb ?? '-',
      row.total_deb ?? '-',
      row.total_os_fmt || '-'
    ]);
    const total = payload?.micro?.decision?.total || {};
    rows.push(['Grand Total', '-', '-', '-', '-', '-', total.total_deb ?? '-', total.total_os_fmt || '-']);
    addRoDenseTable(pptx, slide, ['Branch Office', 'Unit', 'KAUNIT', 'MBM', 'PINCA', 'RMBH', 'Deb', 'OS'], rows, 1.05, 0.92, 11.1, 5.95, [2.15, 2.35, 0.75, 0.75, 0.75, 0.75, 0.7, 1.35], { totalLast: true, fontSize: 5.2 });
  };

  const renderRoMantriSlide = (pptx, payload, logos, slideNo) => {
    const slide = pptx.addSlide();
    addRoChrome(pptx, slide, payload, `Produktivitas Mantri - ${safePptText(payload?.meta?.daily_loan_period_label || payload?.meta?.period_label, '-')}`, logos, slideNo);
    const rows = pptRows(payload?.micro?.mantri_productivity?.rows, 24).map((row) => [
      row.cabang || '-',
      row.nama_mantri || '-',
      row.unit || '-',
      row.realisasi_deb ?? '-',
      row.realisasi_os_fmt || '-',
      row.ratas_mantri_hk ?? '-',
      row.tiket_size ?? '-',
      row.ket || '-'
    ]);
    const total = payload?.micro?.mantri_productivity?.total || {};
    rows.push(['Total', '-', '-', total.realisasi_deb ?? '-', total.realisasi_os_fmt || '-', '-', '-', '-']);
    addRoDenseTable(pptx, slide, ['Branch Office', 'Mantri', 'Unit', 'Deb', 'OS', 'Ratas/HK', 'Ticket', 'Ket'], rows, 0.85, 0.9, 11.75, 6.1, [1.55, 2.05, 1.55, 0.58, 1.2, 0.72, 0.72, 1.05], { totalLast: true, fontSize: 4.95 });
  };

  const renderRoRmMikroSlide = (pptx, payload, logos, slideNo) => {
    const slide = pptx.addSlide();
    addRoChrome(pptx, slide, payload, `Produktivitas RM Mikro - ${safePptText(payload?.meta?.daily_loan_period_label || payload?.meta?.period_label, '-')}`, logos, slideNo);
    const rows = pptRows(payload?.micro?.rm_kur_micro?.rows, 24).map((row) => [
      row.cabang || '-',
      row.nama || '-',
      row.unit || '-',
      row.total_deb ?? '-',
      row.total_os_fmt || '-',
      row.realisasi_deb ?? '-',
      row.realisasi_os_fmt || '-'
    ]);
    const total = payload?.micro?.rm_kur_micro?.total || {};
    rows.push(['TOTAL', '-', '-', total.total_deb ?? '-', total.total_os_fmt || '-', total.realisasi_deb ?? '-', total.realisasi_os_fmt || '-']);
    addRoDenseTable(pptx, slide, ['Branch Office', 'RM', 'Unit', 'Total Deb', 'Total OS', 'Real Deb', 'Real OS'], rows, 1.1, 0.9, 11.0, 6.1, [1.8, 2.35, 1.8, 0.9, 1.35, 0.9, 1.35], { totalLast: true, fontSize: 5 });
  };

  const renderRoQualitySlide = (pptx, payload, logos, type, slideNo) => {
    const slide = pptx.addSlide();
    const title = `Kinerja ${type.toUpperCase()} Area 6 sd ${safePptText(payload?.meta?.period_label, '-')}`;
    addRoChrome(pptx, slide, payload, title, logos, slideNo);
    const quality = payload?.quality?.[type] || {};
    const card = quality.card || {};
    const color = type === 'sml' ? RO_THEME.cyan : RO_THEME.red;
    const seriesKey = type === 'sml' ? 'sml_nominal' : 'npl_nominal';
    const labels = payload?.timeseries?.labels || [];

    addPptRect(pptx, slide, 0.48, 0.93, 4.72, 6.0, RO_THEME.paleBlue, RO_THEME.paleBlue);
    addRoTrendPanel(pptx, slide, `Timeseries ${type.toUpperCase()}`, getPptSeries(payload, seriesKey), labels, 0.5, 1.05, 4.45, 2.55, color);
    addRoRibbon(pptx, slide, `${type.toUpperCase()} Concern`, 0.5, 3.75, 2.65, color, 8.8);
    const concern = type === 'sml'
      ? [
          'Perbaikan SML perlu dikawal dari bucket terbesar.',
          'Eksekusi tunggakan kecil dan restrukturisasi prioritas.',
          `Nominal ${safePptText(card.realization_value || card.value, '-')} | Rasio ${safePptText(card.pct_value || card.ratio, '-')}`,
        ]
      : [
          'Perburukan NPL perlu dimonitor harian.',
          'Pastikan TL New NPL selesai di minggu pertama.',
          `Nominal ${safePptText(card.realization_value || card.value, '-')} | Rasio ${safePptText(card.pct_value || card.ratio, '-')}`,
        ];
    concern.forEach((text, index) => {
      slide.addText(text, {
        x: 0.92, y: 4.18 + index * 0.46, w: 3.8, h: 0.28,
        fontFace: PPT_THEME.font, fontSize: 8.2, bold: index === 0,
        color: RO_THEME.blue2, margin: 0, fit: 'shrink'
      });
      addPptRect(pptx, slide, 0.72, 4.22 + index * 0.46, 0.08, 0.08, color);
    });

    const rows = [
      ...pptRows(quality.ritel_nominal, 8),
      ...pptRows(quality.micro_nominal, 8),
    ].slice(0, 18).map((row) => [
      row.name || row.unit || '-',
      row.branch || row.cabang || '-',
      row.value || row.amount || '-',
      row.secondary || row.ratio || '-'
    ]);
    rows.push(['Total', 'Area 6', card.realization_value || card.value || '-', card.pct_value || card.ratio || '-']);
    addRoDenseTable(pptx, slide, ['Branch Office / Unit', 'Scope', 'Nominal', 'Rasio'], rows, 5.35, 0.98, 7.25, 5.95, [2.45, 1.25, 1.45, 1.0], { totalLast: true, fontSize: 5.25, headerFill: RO_THEME.blue });
  };

  const renderRoKtsSlide = (pptx, payload, logos, slideNo) => {
    const slide = pptx.addSlide();
    addRoChrome(pptx, slide, payload, `Kolek Tidak Sesuai (KTS) - ${safePptText(payload?.kts?.period_label || payload?.meta?.period_label, '-')}`, logos, slideNo);
    const makeRows = (rows) => pptRows(rows, 14).map((row) => [
      row.name || row.unit || '-',
      row.branch || row.cabang || '-',
      row.value || row.count || '-',
      row.secondary || row.amount || '-'
    ]);
    addRoDenseTable(pptx, slide, ['Unit Kerja', 'Cabang', 'Rek', 'OS'], makeRows(payload?.kts?.ritel), 0.82, 1.05, 5.65, 5.65, [2.05, 1.25, 0.7, 1.05], { fontSize: 5.25 });
    addRoDenseTable(pptx, slide, ['Unit Kerja', 'Cabang', 'Rek', 'OS'], makeRows(payload?.kts?.micro), 6.85, 1.05, 5.65, 5.65, [2.05, 1.25, 0.7, 1.05], { fontSize: 5.25, headerFill: RO_THEME.cyan });
  };

  const renderRoDigitalSlide = (pptx, payload, logos, slideNo) => {
    const slide = pptx.addSlide();
    addRoChrome(pptx, slide, payload, '8 Strategi Dana dan Digital - Area 6', logos, slideNo);
    const cards = Array.isArray(payload?.digital_strategy?.cards) ? payload.digital_strategy.cards : [];
    const colors = [RO_THEME.blue, RO_THEME.cyan, RO_THEME.blue2, RO_THEME.darkBlue, RO_THEME.green, RO_THEME.red, '7C3AED', 'BE123C'];
    cards.slice(0, 8).forEach((card, index) => {
      const col = index % 4;
      const row = Math.floor(index / 4);
      const x = 0.72 + col * 3.05;
      const y = 1.05 + row * 2.72;
      addRoPanel(pptx, slide, x, y, 2.65, 2.18, 'D9E2EF');
      addRoRibbon(pptx, slide, safePptText(card.title, '-'), x, y, 2.65, colors[index] || RO_THEME.blue, 7.4);
      slide.addText(safePptText(card.current_value), {
        x: x + 0.18, y: y + 0.62, w: 2.28, h: 0.42,
        fontFace: PPT_THEME.font, fontSize: 16, bold: true,
        color: RO_THEME.darkText, align: 'center', margin: 0, fit: 'shrink'
      });
      slide.addText(safePptText(card.current_label || card.secondary_value, '-'), {
        x: x + 0.18, y: y + 1.14, w: 2.28, h: 0.22,
        fontFace: PPT_THEME.font, fontSize: 7, color: RO_THEME.darkText,
        align: 'center', margin: 0, fit: 'shrink'
      });
      slide.addText(`Trend ${safePptText(card.trend, '-')}`, {
        x: x + 0.18, y: y + 1.68, w: 2.28, h: 0.18,
        fontFace: PPT_THEME.font, fontSize: 6.8, bold: true,
        color: String(card.trend || '').trim().startsWith('-') ? RO_THEME.red : RO_THEME.green,
        align: 'center', margin: 0, fit: 'shrink'
      });
    });
    if (!cards.length) addPptUnavailable(pptx, slide, 0.72, 1.05, 11.9, 5.6);
  };

  const exportPresentationDeck = async () => {
    const [PptxGen, payload] = await Promise.all([
      loadPptxGen(),
      fetchPresentationPayload()
    ]);
    const logos = {
      bri: await imageToDataUri(payload?.assets?.bri_logo),
      danantara: await imageToDataUri(payload?.assets?.danantara_logo),
      coverBase: await imageToDataUri(payload?.assets?.cover_base)
    };
    const pptx = new PptxGen();
    pptx.layout = 'LAYOUT_16x9';
    pptx.author = 'A-SIX Area 6';
    pptx.company = 'BRI Area 6 - Region Malang';
    pptx.subject = 'Dashboard realtime Area 6 - Region Malang';
    pptx.title = 'Ringkasan Performa Area 6 - Region Malang';
    pptx.lang = 'id-ID';

    renderRoCoverSlide(pptx, payload, logos);
    renderRoSelayangSlide(pptx, payload, logos, 2);
    renderRoOverviewSlide(pptx, payload, logos, 3);
    renderRoTrendlineSlide(pptx, payload, logos, 4);
    renderRoDecisionSlide(pptx, payload, logos, 5);
    renderRoMantriSlide(pptx, payload, logos, 6);
    renderRoRmMikroSlide(pptx, payload, logos, 7);
    renderRoQualitySlide(pptx, payload, logos, 'sml', 8);
    renderRoQualitySlide(pptx, payload, logos, 'npl', 9);
    renderRoKtsSlide(pptx, payload, logos, 10);
    renderRoDigitalSlide(pptx, payload, logos, 11);

    const filePeriod = safePptText(payload?.meta?.period, SELECTED_PRESENTATION_PERIOD || 'Snapshot').replace(/[^0-9A-Za-z_-]+/g, '_');
    await pptx.writeFile({ fileName: `Area_6_Region_Malang_${filePeriod}.pptx` });
  };

  const exportBtn = document.getElementById('export-ppt-btn');
  const loadingOverlay = document.getElementById('ppt-loading-overlay');

  if (exportBtn) {
    exportBtn.addEventListener('click', async function() {
      const originalLabel = exportBtn.innerHTML;
      if (loadingOverlay) loadingOverlay.classList.add('active');
      exportBtn.disabled = true;
      exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Menyiapkan PPT...</span>';

      try {
        await exportPresentationDeck();
        return;

        const PptxGen = await loadPptxGen();
        let pptx = new PptxGen();
        pptx.layout = 'LAYOUT_16x9';

        // Slide 1: Title Slide (Dark Theme)
        let slide1 = pptx.addSlide();
        slide1.addShape(pptx.shapes.RECTANGLE, { x: 0, y: 0, w: 13.33, h: 7.5, fill: { color: '0857c3' } });
        slide1.addShape(pptx.shapes.RECTANGLE, { x: 0, y: 0, w: 0.25, h: 7.5, fill: { color: 'd97706' } });
        
        slide1.addText("A-SIX AREA 6 — PRESENTASI KINERJA", {
          x: 1.0, y: 2.2, w: 11.33, h: 1.2,
          fontSize: 34, color: 'FFFFFF', align: 'center', fontFace: 'Inter', bold: true
        });
        slide1.addText("Simpanan, OS, SML, dan NPL Realtime", {
          x: 1.0, y: 3.4, w: 11.33, h: 0.8,
          fontSize: 18, color: 'E2E8F0', align: 'center', fontFace: 'Inter'
        });
        slide1.addText(`Periode Laporan: ${pptData.selectedPeriod || 'Live Snapshot'}`, {
          x: 1.0, y: 4.8, w: 11.33, h: 0.8,
          fontSize: 14, color: 'ffd07b', align: 'center', fontFace: 'Inter', bold: true
        });

        // Slide 2: Ringkasan KPI Utama (Two Column Layout)
        let slide2 = pptx.addSlide();
        slide2.addText("RINGKASAN KPI UTAMA AREA 6", {
          x: 0.75, y: 0.6, w: 11.83, h: 0.6,
          fontSize: 22, color: '0857C3', fontFace: 'Inter', bold: true
        });
        
        // Simpanan Card (Left)
        slide2.addShape(pptx.shapes.RECTANGLE, { x: 0.75, y: 1.6, w: 5.64, h: 4.8, fill: { color: 'F8FAFC' }, line: { color: 'E2E8F0', width: 1 } });
        slide2.addShape(pptx.shapes.RECTANGLE, { x: 0.75, y: 1.6, w: 5.64, h: 0.1, fill: { color: '0857C3' } });
        slide2.addText("TOTAL SIMPANAN", {
          x: 1.15, y: 2.0, w: 4.84, h: 0.5,
          fontSize: 14, color: '0857C3', fontFace: 'Inter', bold: true
        });
        slide2.addText(pptData.simpanan.value, {
          x: 1.15, y: 2.7, w: 4.84, h: 1.0,
          fontSize: 44, color: '0F172A', fontFace: 'Inter', bold: true
        });
        slide2.addText("Realisasi Saldo Dana Simpanan (Realtime)", {
          x: 1.15, y: 3.8, w: 4.84, h: 0.4,
          fontSize: 12, color: '64748B', fontFace: 'Inter'
        });
        slide2.addText(pptData.simpanan.trend + " MtM Growth", {
          x: 1.15, y: 4.4, w: 4.84, h: 0.5,
          fontSize: 14, color: pptData.simpanan.trend.startsWith('-') ? 'DC2626' : '059669', fontFace: 'Inter', bold: true
        });
        slide2.addText("Volume: " + pptData.simpanan.meta, {
          x: 1.15, y: 5.0, w: 4.84, h: 0.4,
          fontSize: 11, color: '64748B', fontFace: 'Inter', italic: true
        });

        // Pinjaman Card (Right)
        slide2.addShape(pptx.shapes.RECTANGLE, { x: 6.94, y: 1.6, w: 5.64, h: 4.8, fill: { color: 'F8FAFC' }, line: { color: 'E2E8F0', width: 1 } });
        slide2.addShape(pptx.shapes.RECTANGLE, { x: 6.94, y: 1.6, w: 5.64, h: 0.1, fill: { color: '0F766E' } });
        slide2.addText("TOTAL OUTSTANDING KREDIT (OS)", {
          x: 7.34, y: 2.0, w: 4.84, h: 0.5,
          fontSize: 14, color: '0F766E', fontFace: 'Inter', bold: true
        });
        slide2.addText(pptData.pinjaman.value, {
          x: 7.34, y: 2.7, w: 4.84, h: 1.0,
          fontSize: 44, color: '0F172A', fontFace: 'Inter', bold: true
        });
        slide2.addText("Realisasi OS", {
          x: 7.34, y: 3.8, w: 4.84, h: 0.4,
          fontSize: 12, color: '64748B', fontFace: 'Inter'
        });
        slide2.addText(pptData.pinjaman.trend + " MtM Growth", {
          x: 7.34, y: 4.4, w: 4.84, h: 0.5,
          fontSize: 14, color: pptData.pinjaman.trend.startsWith('-') ? 'DC2626' : '059669', fontFace: 'Inter', bold: true
        });
        slide2.addText("Volume: " + pptData.pinjaman.meta, {
          x: 7.34, y: 5.0, w: 4.84, h: 0.4,
          fontSize: 11, color: '64748B', fontFace: 'Inter', italic: true
        });

        // Slide 3: Komposisi Portofolio & Kualitas Kredit
        let slide3 = pptx.addSlide();
        slide3.addText("KOMPOSISI PORTOFOLIO & KUALITAS KREDIT", {
          x: 0.75, y: 0.6, w: 11.83, h: 0.6,
          fontSize: 22, color: '0857C3', fontFace: 'Inter', bold: true
        });

        // Outstanding Card
        slide3.addShape(pptx.shapes.RECTANGLE, { x: 0.75, y: 1.8, w: 3.64, h: 4.5, fill: { color: 'F8FAFC' }, line: { color: 'E2E8F0', width: 1 } });
        slide3.addShape(pptx.shapes.RECTANGLE, { x: 0.75, y: 1.8, w: 3.64, h: 0.08, fill: { color: '0857C3' } });
        slide3.addText("OUTSTANDING KREDIT", {
          x: 1.0, y: 2.2, w: 3.14, h: 0.4,
          fontSize: 12, color: '0857C3', fontFace: 'Inter', bold: true
        });
        slide3.addText(pptData.portfolio.value, {
          x: 1.0, y: 2.8, w: 3.14, h: 0.8,
          fontSize: 32, color: '0F172A', fontFace: 'Inter', bold: true
        });
        slide3.addText(pptData.portfolio.meta, {
          x: 1.0, y: 3.8, w: 3.14, h: 0.8,
          fontSize: 12, color: '64748B', fontFace: 'Inter'
        });

        // SML Card
        slide3.addShape(pptx.shapes.RECTANGLE, { x: 4.84, y: 1.8, w: 3.64, h: 4.5, fill: { color: 'F8FAFC' }, line: { color: 'E2E8F0', width: 1 } });
        slide3.addShape(pptx.shapes.RECTANGLE, { x: 4.84, y: 1.8, w: 3.64, h: 0.08, fill: { color: 'D97706' } });
        slide3.addText("SPECIAL MENTION LOAN (SML)", {
          x: 5.09, y: 2.2, w: 3.14, h: 0.4,
          fontSize: 12, color: 'D97706', fontFace: 'Inter', bold: true
        });
        const smlCompObj = pptData.composition.sml || {};
        slide3.addText(smlCompObj.pct || '–', {
          x: 5.09, y: 2.8, w: 3.14, h: 0.8,
          fontSize: 32, color: 'D97706', fontFace: 'Inter', bold: true
        });
        slide3.addText(`Volume SML: Rp ${smlCompObj.value || '–'} Jt`, {
          x: 5.09, y: 3.8, w: 3.14, h: 0.8,
          fontSize: 12, color: '64748B', fontFace: 'Inter'
        });

        // NPL Card
        slide3.addShape(pptx.shapes.RECTANGLE, { x: 8.94, y: 1.8, w: 3.64, h: 4.5, fill: { color: 'F8FAFC' }, line: { color: 'E2E8F0', width: 1 } });
        slide3.addShape(pptx.shapes.RECTANGLE, { x: 8.94, y: 1.8, w: 3.64, h: 0.08, fill: { color: 'DC2626' } });
        slide3.addText("NON-PERFORMING LOAN (NPL)", {
          x: 9.19, y: 2.2, w: 3.14, h: 0.4,
          fontSize: 12, color: 'DC2626', fontFace: 'Inter', bold: true
        });
        const nplCompObj = pptData.composition.npl || {};
        slide3.addText(nplCompObj.pct || '–', {
          x: 9.19, y: 2.8, w: 3.14, h: 0.8,
          fontSize: 32, color: 'DC2626', fontFace: 'Inter', bold: true
        });
        slide3.addText(`Volume NPL: Rp ${nplCompObj.value || '–'} Jt`, {
          x: 9.19, y: 3.8, w: 3.14, h: 0.8,
          fontSize: 12, color: '64748B', fontFace: 'Inter'
        });

        // Slide 4: Kinerja Per Segmen Table
        let slide4 = pptx.addSlide();
        slide4.addText(`KINERJA PORTFOLIO PER SEGMEN (RKA ${pptData.rkaMonthYear})`, {
          x: 0.75, y: 0.6, w: 11.83, h: 0.6,
          fontSize: 22, color: '0857C3', fontFace: 'Inter', bold: true
        });

        let segmentTableRows = [
          [
            { text: "SEGMEN KREDIT", options: { fill: "0857C3", color: "FFFFFF", bold: true, fontSize: 11, fontFace: "Inter" } },
            { text: "OS REALISASI (Rp Jt)", options: { fill: "0857C3", color: "FFFFFF", bold: true, align: "right", fontSize: 11, fontFace: "Inter" } },
            { text: "PENC. OS %", options: { fill: "0857C3", color: "FFFFFF", bold: true, align: "right", fontSize: 11, fontFace: "Inter" } },
            { text: "SML REALISASI (Rp Jt)", options: { fill: "0857C3", color: "FFFFFF", bold: true, align: "right", fontSize: 11, fontFace: "Inter" } },
            { text: "PENC. SML %", options: { fill: "0857C3", color: "FFFFFF", bold: true, align: "right", fontSize: 11, fontFace: "Inter" } },
            { text: "NPL REALISASI (Rp Jt)", options: { fill: "0857C3", color: "FFFFFF", bold: true, align: "right", fontSize: 11, fontFace: "Inter" } },
            { text: "PENC. NPL %", options: { fill: "0857C3", color: "FFFFFF", bold: true, align: "right", fontSize: 11, fontFace: "Inter" } }
          ]
        ];

        (pptData.segments || []).forEach((seg, idx) => {
          const bg = idx % 2 === 0 ? "F8FAFC" : "FFFFFF";
          const os = seg.os || {};
          const sml = seg.sml || {};
          const npl = seg.npl || {};
          
          segmentTableRows.push([
            { text: String(seg.label || '-').toUpperCase(), options: { fill: bg, fontSize: 10, bold: true, fontFace: "Inter" } },
            { text: os.realization_fmt || '–', options: { fill: bg, align: "right", fontSize: 10, fontFace: "Inter" } },
            { text: os.pct_fmt || '–', options: { fill: bg, align: "right", fontSize: 10, bold: true, fontFace: "Inter" } },
            { text: sml.realization_fmt || '–', options: { fill: bg, align: "right", fontSize: 10, fontFace: "Inter" } },
            { text: sml.pct_fmt || '–', options: { fill: bg, align: "right", fontSize: 10, bold: true, fontFace: "Inter" } },
            { text: npl.realization_fmt || '–', options: { fill: bg, align: "right", fontSize: 10, fontFace: "Inter" } },
            { text: npl.pct_fmt || '–', options: { fill: bg, align: "right", fontSize: 10, bold: true, fontFace: "Inter" } }
          ]);
        });

        if (pptData.totalPerf && Object.keys(pptData.totalPerf).length > 0) {
          const tOs = pptData.totalPerf.os || {};
          const tSml = pptData.totalPerf.sml || {};
          const tNpl = pptData.totalPerf.npl || {};
          
          segmentTableRows.push([
            { text: "TOTAL AREA 6", options: { fill: "f1f5f9", bold: true, fontSize: 10, fontFace: "Inter" } },
            { text: tOs.realization_fmt || '–', options: { fill: "f1f5f9", bold: true, align: "right", fontSize: 10, fontFace: "Inter" } },
            { text: tOs.pct_fmt || '–', options: { fill: "f1f5f9", bold: true, align: "right", fontSize: 10, fontFace: "Inter" } },
            { text: tSml.realization_fmt || '–', options: { fill: "f1f5f9", bold: true, align: "right", fontSize: 10, fontFace: "Inter" } },
            { text: tSml.pct_fmt || '–', options: { fill: "f1f5f9", bold: true, align: "right", fontSize: 10, fontFace: "Inter" } },
            { text: tNpl.realization_fmt || '–', options: { fill: "f1f5f9", bold: true, align: "right", fontSize: 10, fontFace: "Inter" } },
            { text: tNpl.pct_fmt || '–', options: { fill: "f1f5f9", bold: true, align: "right", fontSize: 10, fontFace: "Inter" } }
          ]);
        }

        slide4.addTable(segmentTableRows, {
          x: 0.75, y: 1.6, w: 11.83, h: 4.8,
          border: { pt: 0.5, color: "cbd5e1" }
        });

        // Slide 5: Performa Kantor Cabang Konsolidasi
        let slide5 = pptx.addSlide();
        slide5.addText("PERFORMA KANTOR CABANG KONSOLIDASI (AREA 6)", {
          x: 0.75, y: 0.6, w: 11.83, h: 0.6,
          fontSize: 22, color: '0857C3', fontFace: 'Inter', bold: true
        });

        let branchTableRows = [
          [
            { text: "KANTOR CABANG", options: { fill: "0857C3", color: "FFFFFF", bold: true, fontSize: 11, fontFace: "Inter" } },
            { text: "SIMPANAN VOLUME", options: { fill: "0857C3", color: "FFFFFF", bold: true, align: "right", fontSize: 11, fontFace: "Inter" } },
            { text: "PENCAPAIAN SIMPANAN %", options: { fill: "0857C3", color: "FFFFFF", bold: true, align: "right", fontSize: 11, fontFace: "Inter" } },
            { text: "PINJAMAN VOLUME (OS)", options: { fill: "0857C3", color: "FFFFFF", bold: true, align: "right", fontSize: 11, fontFace: "Inter" } },
            { text: "PENCAPAIAN OS %", options: { fill: "0857C3", color: "FFFFFF", bold: true, align: "right", fontSize: 11, fontFace: "Inter" } },
            { text: "SML RATIO %", options: { fill: "0857C3", color: "FFFFFF", bold: true, align: "right", fontSize: 11, fontFace: "Inter" } },
            { text: "NPL RATIO %", options: { fill: "0857C3", color: "FFFFFF", bold: true, align: "right", fontSize: 11, fontFace: "Inter" } }
          ]
        ];

        (pptData.branches || []).forEach((b, idx) => {
          const bg = idx % 2 === 0 ? "F8FAFC" : "FFFFFF";
          branchTableRows.push([
            { text: String(b.name || '-').toUpperCase(), options: { fill: bg, fontSize: 10, bold: true, fontFace: "Inter" } },
            { text: b.simpanan_fmt || '–', options: { fill: bg, align: "right", fontSize: 10, fontFace: "Inter" } },
            { text: b.simpanan_share_fmt || '–', options: { fill: bg, align: "right", fontSize: 10, bold: true, fontFace: "Inter" } },
            { text: b.pinjaman_fmt || '–', options: { fill: bg, align: "right", fontSize: 10, fontFace: "Inter" } },
            { text: b.pinjaman_share_fmt || '–', options: { fill: bg, align: "right", fontSize: 10, bold: true, fontFace: "Inter" } },
            { text: b.sml_pct_fmt || '–', options: { fill: bg, align: "right", fontSize: 10, fontFace: "Inter" } },
            { text: b.npl_pct_fmt || '–', options: { fill: bg, align: "right", fontSize: 10, fontFace: "Inter" } }
          ]);
        });

        slide5.addTable(branchTableRows, {
          x: 0.75, y: 1.6, w: 11.83, h: 4.8,
          border: { pt: 0.5, color: "cbd5e1" }
        });

        // Save Presentation
        await pptx.writeFile({ fileName: `Laporan_A-SIX_Area_6_${pptData.selectedPeriod || 'Snapshot'}` });
      } catch (err) {
        console.error("Gagal mengekspor PPT:", err);
        alert("Terjadi kesalahan saat menyusun PPT: " + err.message);
      } finally {
        if (loadingOverlay) loadingOverlay.classList.remove('active');
        exportBtn.disabled = false;
        exportBtn.innerHTML = originalLabel;
      }
    });
  }

  // --- Presentation Mode Controller ---
  const enterPresBtn = document.getElementById('enter-presentation-btn');
  if (enterPresBtn) {
    enterPresBtn.addEventListener('click', function() {
      const globalLoader = document.getElementById('dashboard-global-loader');
      setDashboardLoaderCopy(
        'Menyiapkan Mode Presentasi',
        'Membuka halaman presentasi...'
      );
      if (globalLoader) globalLoader.classList.add('active');
      enterPresBtn.disabled = true;
      
      const selectedPeriod = document.getElementById('periode-selector') ? document.getElementById('periode-selector').value : '';
      const url = new URL("{{ route('dashboard.presentation') }}", window.location.origin);
      if (selectedPeriod) {
        url.searchParams.set('periode', selectedPeriod);
      }
      window.location.href = url.toString();
    });
  }

  // --- Area 6 AJAX Skeleton Loader ---
  (function() {
    const overlay = document.getElementById('area6-loading-overlay');
    if (!overlay) return; // data sudah tersedia, tidak perlu fetch

    const fillEl   = document.getElementById('area6-progress-fill');
    const pctEl    = document.getElementById('area6-progress-pct');
    const statusEl = document.getElementById('area6-loading-status');

    let progress = 0;
    let ttfbDone = false;
    let active   = true;

    // Phase 1: asymptotic crawl to ~74% while server processes (TTFB)
    const startTs = performance.now();
    const crawl = (now) => {
      if (!active || ttfbDone) return;
      progress = Math.min(74, 74 * (1 - Math.exp(-(now - startTs) / 1800)));
      if (fillEl)  fillEl.style.width  = progress + '%';
      if (pctEl)   pctEl.textContent   = Math.round(progress) + '%';
      requestAnimationFrame(crawl);
    };
    requestAnimationFrame(crawl);

    // Status text cycling during wait
    const phases = [
      { ms:    0, text: 'Menghubungkan ke server...' },
      { ms:  800, text: 'Mengambil data portofolio kredit...' },
      { ms: 2000, text: 'Menghitung rasio SML & NPL...' },
      { ms: 3800, text: 'Menyusun ranking cabang & segmen...' },
      { ms: 5500, text: 'Menyelesaikan paket data Area 6...' },
    ];
    const timers = phases.map(p =>
      setTimeout(() => {
        if (active && !ttfbDone && statusEl) statusEl.textContent = p.text;
      }, p.ms)
    );

    // Build endpoint URL
    const periodeSel = document.getElementById('periode-selector');
    const periode    = periodeSel ? periodeSel.value : '';
    const area6Url   = new URL("{{ route('dashboard.area6-data') }}", window.location.origin);
    if (periode) area6Url.searchParams.set('periode', periode);

    fetch(area6Url.toString(), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      cache: 'no-store'
    }).then(function(response) {
      if (!response.ok) throw new Error('HTTP ' + response.status);

      // Phase 2: track real download progress
      ttfbDone = true;
      timers.forEach(clearTimeout);
      if (statusEl) statusEl.textContent = 'Mengunduh data ringkasan...';

      const reader = response.body.getReader();
      const contentLength = +response.headers.get('Content-Length');
      let received = 0, chunks = [];

      function read() {
        return reader.read().then(function(result) {
          if (result.done) return;
          chunks.push(result.value);
          received += result.value.length;
          if (contentLength > 0) {
            const frac = received / contentLength;
            const p = progress + (98 - progress) * frac;
            if (fillEl) fillEl.style.width = p + '%';
            if (pctEl) pctEl.textContent = Math.round(p) + '%';
          } else {
            progress = Math.min(97, progress + (100 - progress) * 0.12);
            if (fillEl) fillEl.style.width = progress + '%';
            if (pctEl) pctEl.textContent = Math.round(progress) + '%';
          }
          return read();
        });
      }

      return read();

    }).then(function() {
      // Concatenate chunks to get the JSON string
      let position = 0;
      let joined = new Uint8Array(received);
      for(let chunk of chunks) {
        joined.set(chunk, position);
        position += chunk.length;
      }
      
      let parsedData = {};
      try {
        parsedData = JSON.parse(new TextDecoder("utf-8").decode(joined));
      } catch(e) {
        console.error('[Area6 Loader] JSON parse failed', e);
      }

      active = false;
      if (fillEl)  fillEl.style.width  = '100%';
      if (pctEl)   pctEl.textContent   = '100%';

      const portfolio = parsedData.area6_portfolio || {};
      const cards = portfolio.cards || [];

      if (cards.length > 0) {
        if (statusEl) statusEl.textContent = 'Selesai! Memperbarui tampilan...';
        setTimeout(function() {
          const url = new URL(window.location.href);
          if (periode) url.searchParams.set('periode', periode);
          url.searchParams.delete('_area6');
          url.searchParams.set('_area6', Date.now());
          window.location.href = url.toString();
        }, 500);
      } else {
        if (statusEl) statusEl.textContent = 'Ringkasan data kosong atau belum siap.';
        setTimeout(function() {
          if (overlay) {
            overlay.style.transition = 'opacity 0.4s ease';
            overlay.style.opacity = '0';
            setTimeout(() => overlay.remove(), 400);
          }
        }, 1500);
      }

    }).catch(function(err) {
      active = false;
      timers.forEach(clearTimeout);
      console.error('[Area6 Loader]', err);
      if (statusEl) statusEl.textContent = 'Gagal memuat data. Coba segarkan halaman.';
      if (fillEl) fillEl.style.background = '#ef4444';
    });
  })();

});
</script>
@endsection
