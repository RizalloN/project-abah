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
@import url('https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,700&display=swap');

:root {
  /* Brand & Vibrant Palette */
  --c-blue: #0857c3;
  --c-blue-d: #053b82;
  --c-blue-l: #2563eb;
  --c-blue-subtle: #eff6ff;
  --c-blue-border: #bfdbfe;
  
  --c-teal: #0f766e;
  --c-teal-l: #0d9488;
  --c-teal-subtle: #f0fdfa;
  
  --c-emerald: #059669;
  --c-emerald-l: #10b981;
  --c-emerald-subtle: #ecfdf5;
  --c-emerald-border: #a7f3d0;
  
  --c-amber: #d97706;
  --c-amber-l: #f59e0b;
  --c-amber-subtle: #fffbeb;
  --c-amber-border: #fde68a;
  
  --c-red: #dc2626;
  --c-red-l: #ef4444;
  --c-red-subtle: #fef2f2;
  --c-red-border: #fecaca;
  
  --c-purple: #7c3aed;
  --c-purple-l: #8b5cf6;
  --c-purple-subtle: #f5f3ff;
  --c-purple-border: #ddd6fe;
  
  --c-cyan: #0891b2;
  --c-cyan-l: #06b6d4;
  --c-cyan-subtle: #ecfeff;
  --c-cyan-border: #a5f3fc;
  
  --c-pink: #db2777;
  --c-orange: #ea580c;
  --c-orange-l: #f97316;
  
  /* Neutrals & Surfaces */
  --c-surface: #ffffff;
  --c-surf: #f8fafc;
  --c-surf-elevated: #f1f5f9;
  --c-border: #e2e8f0;
  --c-border-strong: #cbd5e1;
  --c-text-main: #0f172a;
  --c-text-muted: #64748b;
  --c-text-subtle: #94a3b8;
  
  /* Shadows & Depth */
  --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
  --shadow-sm: 0 2px 4px rgba(15, 23, 42, 0.03), 0 1px 2px rgba(15, 23, 42, 0.02);
  --shadow-md: 0 6px 18px -3px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
  --shadow-lg: 0 16px 32px -4px rgba(15, 23, 42, 0.08), 0 6px 16px -2px rgba(15, 23, 42, 0.04);
  --shadow-hover: 0 20px 36px -6px rgba(8, 87, 195, 0.12), 0 8px 16px -3px rgba(15, 23, 42, 0.06);
  
  /* Border Radii */
  --r-sm: 8px;
  --r-md: 12px;
  --r-lg: 16px;
  --r-xl: 22px;
  --r-2xl: 26px;
  --r-pill: 9999px;
}

.db-shell {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  padding: 0 0 1.25rem;
  color: var(--c-text-main);
  -webkit-font-smoothing: antialiased;
}

/* ── HEADER ── */
.db-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.15rem 1.65rem;
  margin-bottom: 1.25rem;
  background: #ffffff;
  border: 1px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-md);
  position: relative;
  overflow: hidden;
}

.db-header::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #0857c3 0%, #06b6d4 50%, #10b981 100%);
}

.db-brand {
  display: flex;
  align-items: center;
  gap: 0.95rem;
}

.db-logo {
  width: 44px;
  height: 44px;
  background: linear-gradient(135deg, #0857c3 0%, #2563eb 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 14px;
  box-shadow: 0 6px 16px -2px rgba(8, 87, 195, 0.4);
  padding: 8px;
  flex-shrink: 0;
  transition: transform 0.2s ease;
}

.db-logo:hover {
  transform: scale(1.05);
}

.db-logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.db-title {
  font-size: 1.05rem;
  font-weight: 900;
  color: var(--c-text-main);
  letter-spacing: -0.02em;
  line-height: 1.25;
}

.db-subtitle {
  font-size: 0.72rem;
  font-weight: 550;
  color: var(--c-text-muted);
  margin-top: 0.15rem;
}

.db-meta {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex-wrap: wrap;
}

/* Branch Picker */
.db-branch-picker {
  position: relative;
  display: inline-flex;
  align-items: center;
  margin: 0;
  cursor: pointer;
}

.db-branch-picker i {
  position: absolute;
  left: 0.75rem;
  font-size: 0.72rem;
  color: var(--c-blue);
  pointer-events: none;
  z-index: 2;
}

.db-branch-picker select {
  appearance: none;
  -webkit-appearance: none;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  border-radius: 12px;
  padding: 0.45rem 1.6rem 0.45rem 2rem;
  font-size: 0.72rem;
  font-weight: 750;
  color: #1e293b;
  cursor: pointer;
  box-shadow: var(--shadow-xs);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  min-height: 38px;
  font-family: inherit;
}

.db-branch-picker select:hover {
  border-color: var(--c-blue);
  background-color: var(--c-blue-subtle);
}

.db-branch-picker select:focus {
  outline: none;
  border-color: var(--c-blue);
  box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.16);
}

/* Date Picker */
.db-date-picker-container {
  position: relative;
  display: inline-flex;
  align-items: center;
}

.db-date-picker-select {
  appearance: none;
  -webkit-appearance: none;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  border-radius: 12px;
  padding: 0.45rem 2rem 0.45rem 0.85rem;
  font-size: 0.72rem;
  font-weight: 750;
  color: #1e293b;
  cursor: pointer;
  box-shadow: var(--shadow-xs);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  min-height: 38px;
  font-family: inherit;
}

.db-date-picker-select:hover {
  border-color: var(--c-blue);
  background-color: var(--c-blue-subtle);
}

.db-date-picker-select:focus {
  outline: none;
  border-color: var(--c-blue);
  box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.16);
}

.db-date-picker-icon {
  position: absolute;
  right: 0.75rem;
  font-size: 0.72rem;
  color: var(--c-blue);
  pointer-events: none;
}

/* Presentation & PPT Buttons */
.db-pres-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.45rem 1rem;
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 12px;
  font-size: 0.72rem;
  font-weight: 750;
  color: #f8fafc;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  min-height: 38px;
  white-space: nowrap;
}

.db-pres-btn:hover {
  background: linear-gradient(135deg, #0857c3 0%, #2563eb 100%);
  color: #ffffff;
  transform: translateY(-2px);
  box-shadow: 0 8px 18px -2px rgba(8, 87, 195, 0.45);
}

.db-ppt-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.45rem 1rem;
  background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
  border: 0;
  border-radius: 12px;
  font-size: 0.72rem;
  font-weight: 800;
  color: #ffffff;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(234, 88, 12, 0.3);
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  min-height: 38px;
  white-space: nowrap;
}

.db-ppt-btn:hover {
  background: linear-gradient(135deg, #c2410c 0%, #ea580c 100%);
  transform: translateY(-2px);
  box-shadow: 0 8px 20px -2px rgba(234, 88, 12, 0.45);
}

.db-meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.42rem 0.85rem;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  font-size: 0.7rem;
  font-weight: 800;
  color: #15803d;
  border-radius: var(--r-pill);
  min-height: 38px;
  white-space: nowrap;
}

.db-now {
  font-size: 0.7rem;
  color: var(--c-text-muted);
  font-weight: 600;
  white-space: nowrap;
}

/* ── KPI STRIP ── */
.kpi-strip {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0.85rem;
  margin-bottom: 1.25rem;
}

.kpi-card {
  padding: 1.15rem 1.15rem 1rem;
  min-height: 128px;
  position: relative;
  overflow: hidden;
  border: 1px solid var(--c-border);
  background: #ffffff;
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.22s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
}

.kpi-card::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #94a3b8, #cbd5e1);
  border-top-left-radius: var(--r-xl);
  border-top-right-radius: var(--r-xl);
}

.kpi-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-hover);
  border-color: #cbd5e1;
}

.kpi-card .kc-label {
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #475569;
  margin-bottom: 0.35rem;
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.kc-icon-box {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.82rem;
  margin-right: 0.2rem;
  flex-shrink: 0;
  box-shadow: 0 2px 5px rgba(0,0,0,0.04);
}

.kpi-card.simpanan .kc-icon-box { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.kpi-card.pinjaman .kc-icon-box { background: #eff6ff; color: #0857c3; border: 1px solid #bfdbfe; }
.kpi-card.portfolio .kc-icon-box { background: #f5f3ff; color: #6366f1; border: 1px solid #ddd6fe; }
.kpi-card:nth-child(4) .kc-icon-box { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
.kpi-card:nth-child(5) .kc-icon-box { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.kpi-card:nth-child(6) .kc-icon-box { background: #ecfeff; color: #0284c7; border: 1px solid #a5f3fc; }

.kpi-card .kc-val {
  font-size: 1.42rem;
  font-weight: 900;
  line-height: 1.15;
  color: #0f172a;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
}

.kpi-card .kc-sub {
  font-size: 0.65rem;
  font-weight: 600;
  color: #64748b;
  margin-top: 0.3rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kpi-card .kc-delta {
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  font-size: 0.66rem;
  font-weight: 800;
  padding: 0.22rem 0.6rem;
  margin-top: 0.45rem;
  border-radius: var(--r-sm);
  width: fit-content;
}

/* Card Specific Accents */
.kpi-card.simpanan::before {
  background: linear-gradient(90deg, #059669 0%, #10b981 100%);
}
.kpi-card.simpanan .kc-label {
  color: #059669;
}

.kpi-card.pinjaman::before {
  background: linear-gradient(90deg, #0857c3 0%, #3b82f6 100%);
}
.kpi-card.pinjaman .kc-label {
  color: #0857c3;
}

.kpi-card.portfolio::before {
  background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%);
}
.kpi-card.portfolio .kc-label {
  color: #6366f1;
}

.kpi-card:nth-child(4)::before {
  background: linear-gradient(90deg, #d97706 0%, #f59e0b 100%);
}
.kpi-card:nth-child(5)::before {
  background: linear-gradient(90deg, #059669 0%, #34d399 100%);
}
.kpi-card:nth-child(6)::before {
  background: linear-gradient(90deg, #0284c7 0%, #06b6d4 100%);
}

.kc-live {
  position: absolute;
  top: 0.85rem;
  right: 0.85rem;
  width: 8px;
  height: 8px;
  background: #10b981;
  border-radius: 50%;
  box-shadow: 0 0 10px #10b981;
  animation: pulse-live 1.8s infinite;
}

@keyframes pulse-live {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(0.85); }
}

.kpi-card .kc-link {
  position: absolute;
  bottom: 0.65rem;
  right: 0.8rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 36px;
  gap: 0.28rem;
  font-size: 0.66rem;
  font-weight: 800;
  color: var(--c-blue);
  text-decoration: none;
  border: 0;
  background: var(--c-blue-subtle);
  border-radius: 8px;
  padding: 0 0.4rem;
  cursor: pointer;
  transition: all 0.18s ease;
}

.kpi-card .kc-link:hover {
  background: var(--c-blue);
  color: #ffffff;
  text-decoration: none;
  transform: translateY(-1px);
}

/* Delta Badges */
.pos { color: #15803d; background: #dcfce7; }
.neg { color: #b91c1c; background: #fee2e2; }
.neu { color: #475569; background: #f1f5f9; }

/* ── AREA 6 PORTFOLIO PANEL ── */
.area6-panel {
  margin: 1.25rem 0;
  background: #ffffff;
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-md);
  border-radius: var(--r-2xl);
  overflow: hidden;
}

.area6-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.25rem;
  padding: 1.25rem 1.65rem;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  border-bottom: 1px solid var(--c-border);
}

.area6-title {
  font-size: 1.2rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -0.02em;
}

.area6-sub {
  margin-top: 0.18rem;
  font-size: 0.75rem;
  font-weight: 550;
  color: #64748b;
}

.area6-head-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.65rem;
}

.landing-scope-stage {
  display: inline-flex;
  align-items: center;
}

.area6-scope-toggle {
  display: inline-flex;
  gap: 0.3rem;
  padding: 0.25rem;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
}

.area6-scope-btn {
  border: 0;
  min-height: 36px;
  padding: 0.45rem 1.15rem;
  background: transparent;
  color: #475569;
  font-size: 0.76rem;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  border-radius: 10px;
  letter-spacing: 0.02em;
}

.area6-scope-btn:hover {
  color: #0857c3;
  background: rgba(255, 255, 255, 0.7);
}

.area6-scope-btn.active {
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(8, 87, 195, 0.3);
  font-weight: 900;
  transform: translateY(-1px);
}

.landing-scope-visual {
  display: none !important;
}

.area6-periods {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}

.area6-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
  padding: 0.32rem 0.78rem;
  background: #ffffff;
  border: 1px solid #d4e0ee;
  color: #334e68;
  font-size: 0.7rem;
  font-weight: 700;
  white-space: nowrap;
  border-radius: 9999px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
  transition: all 0.2s ease;
}

.area6-pill:hover {
  border-color: #cbd5e1;
  color: #0f172a;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.06);
}

.area6-pill i {
  color: #0754bd;
  font-size: 0.74rem;
}

/* ── PREMIUM AREA 6 CARDS ── */
.area6-card-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1.15rem;
  padding: 1.5rem;
}

.area6-card-grid--three {
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
}

.area6-card-premium {
  border: 1.5px solid var(--c-border);
  appearance: none;
  width: 100%;
  padding: 0;
  text-align: left;
  background: #ffffff;
  position: relative;
  overflow: visible;
  display: flex;
  flex-direction: column;
  cursor: pointer;
  transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.22s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
  border-radius: var(--r-xl);
  box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
  font-family: inherit;
}

.area6-card-premium:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-hover);
  border-color: #cbd5e1;
}

.area6-card-premium[data-metric="os"]:hover {
  border-color: #93c5fd;
  box-shadow: 0 20px 36px -6px rgba(8, 87, 195, 0.16), 0 8px 16px -3px rgba(15, 23, 42, 0.06);
}

.area6-card-premium[data-metric="sml"]:hover {
  border-color: #7dd3fc;
  box-shadow: 0 20px 36px -6px rgba(2, 132, 199, 0.16), 0 8px 16px -3px rgba(15, 23, 42, 0.06);
}

.area6-card-premium[data-metric="npl"]:hover {
  border-color: #fca5a5;
  box-shadow: 0 20px 36px -6px rgba(220, 38, 38, 0.16), 0 8px 16px -3px rgba(15, 23, 42, 0.06);
}

.area6-card-premium[data-metric="recovery"]:hover {
  border-color: #6ee7b7;
  box-shadow: 0 20px 36px -6px rgba(5, 150, 105, 0.16), 0 8px 16px -3px rgba(15, 23, 42, 0.06);
}

/* Header Banner */
.ap-header {
  position: relative;
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-top-left-radius: calc(var(--r-xl) - 1.5px);
  border-top-right-radius: calc(var(--r-xl) - 1.5px);
  padding: 0 1rem;
}

.ap-header-title {
  color: #ffffff;
  font-size: 0.86rem;
  font-weight: 900;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  text-align: center;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
}

/* Header & Badge Themes */
.ap-header.bg-os, .ap-badge.bg-os {
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%);
}
.ap-header.bg-sml, .ap-badge.bg-sml {
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
}
.ap-header.bg-npl, .ap-badge.bg-npl {
  background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
}
.ap-header.bg-recovery, .ap-badge.bg-recovery {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
}

/* Floating Icon Badge */
.ap-badge {
  position: absolute;
  top: -14px;
  left: 16px;
  width: 46px;
  height: 46px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 3px solid #ffffff;
  box-shadow: 0 8px 20px -2px rgba(0, 0, 0, 0.28), 0 2px 6px rgba(0, 0, 0, 0.1);
  color: #ffffff;
  font-size: 1.2rem;
  z-index: 10;
  transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

.area6-card-premium:hover .ap-badge {
  transform: scale(1.08) translateY(-2px);
}

/* Card Body Content */
.ap-body {
  padding: 1.35rem 1.25rem 1.15rem;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}

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
  background: linear-gradient(180deg, transparent 0%, #e2e8f0 30%, #e2e8f0 70%, transparent 100%);
}

.ap-metric-col {
  padding: 0.45rem 0.25rem;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.ap-metric-label {
  font-size: 0.66rem;
  font-weight: 750;
  color: #475569;
  margin-bottom: 0.25rem;
  text-align: center;
  line-height: 1.2;
  letter-spacing: 0.02em;
}

.ap-metric-val {
  font-size: 1.54rem;
  font-weight: 900;
  color: #0f172a;
  line-height: 1.1;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
}

.ap-metric-sub {
  font-size: 0.62rem;
  font-weight: 700;
  color: #94a3b8;
  margin-top: 0.15rem;
}

.ap-metric-pct-val,
.ap-metric-gap-val {
  font-size: 1.54rem;
  font-weight: 900;
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
}

/* Weekly Toolbar & Prognosa Week Buttons */
.ap-week-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin: 0 1.5rem 0.85rem;
  padding: 0.45rem 1rem 0.45rem 1.15rem;
  border: 1.5px solid #bfdbfe;
  border-left: 4.5px solid #0857c3;
  border-radius: 14px;
  background: linear-gradient(135deg, #f8fbff 0%, #eff6ff 100%);
  box-shadow: 0 2px 8px -2px rgba(8, 87, 195, 0.08);
}

.ap-week-toolbar__copy {
  display: flex;
  align-items: center;
  min-width: 0;
}

.ap-week-toolbar__copy span {
  color: #0857c3;
  font-size: 0.78rem;
  font-weight: 850;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  white-space: nowrap;
}

.ap-week-toggle {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.45rem;
  flex-wrap: nowrap;
}

.ap-week-btn {
  min-height: 38px;
  min-width: 82px;
  padding: 0.3rem 0.65rem;
  color: #334155;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  border-radius: 10px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
}

.ap-week-btn span {
  font-size: 0.74rem;
  font-weight: 850;
  letter-spacing: -0.01em;
  line-height: 1.1;
  white-space: nowrap;
}

.ap-week-btn small {
  font-size: 0.58rem;
  font-weight: 700;
  color: #64748b;
  margin-top: 0.1rem;
  line-height: 1;
  white-space: nowrap;
}

.ap-week-btn:hover:not(:disabled) {
  border-color: #0857c3;
  color: #0857c3;
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(8, 87, 195, 0.14);
}

.ap-week-btn.active {
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%);
  border-color: #0857c3;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(8, 87, 195, 0.3);
  transform: translateY(-1px);
}

.ap-week-btn.active small {
  color: #dbeafe;
}

.ap-week-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  background: #f8fafc;
  border-color: #e2e8f0;
}

/* Weekly Prognosa Strip */
.ap-prognosa-strip {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0;
  margin-top: 0.85rem;
  padding: 0.75rem 0.6rem;
  border: 1.5px solid #bfdbfe;
  border-left: 4.5px solid #0857c3;
  border-radius: 14px;
  background: linear-gradient(135deg, #f8fbff 0%, #eff6ff 100%);
  box-shadow: 0 2px 6px rgba(8, 87, 195, 0.04);
}

.area6-card-premium[data-metric="sml"] .ap-prognosa-strip {
  border-color: #bae6fd;
  border-left-color: #0284c7;
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
}

.area6-card-premium[data-metric="npl"] .ap-prognosa-strip {
  border-color: #fecaca;
  border-left-color: #dc2626;
  background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
}

.area6-card-premium[data-metric="recovery"] .ap-prognosa-strip {
  border-color: #a7f3d0;
  border-left-color: #059669;
  background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
}

.ap-prognosa-item {
  min-width: 0;
  padding: 0 0.5rem;
  text-align: center;
}

.ap-prognosa-item + .ap-prognosa-item {
  border-left: 1px solid rgba(0, 0, 0, 0.08);
}

.ap-prognosa-label {
  color: #475569;
  font-size: 0.64rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.ap-prognosa-value {
  margin-top: 0.22rem;
  color: #0f172a;
  font-size: 1.15rem;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
}

.ap-prognosa-unit {
  margin-top: 0.15rem;
  color: #64748b;
  font-size: 0.6rem;
  font-weight: 700;
}

/* Flat Status Colors */
.text-green-flat { color: #15803d !important; }
.text-amber-flat { color: #d97706 !important; }
.text-red-flat { color: #dc2626 !important; }
.text-muted-flat { color: #64748b !important; }

/* Dashed Divider */
.ap-dashed-divider {
  border: 0;
  border-top: 1px dashed #cbd5e1;
  margin: 0.95rem 0 0.85rem;
}

/* Row 3 - Deltas */
.ap-deltas {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.45rem;
}

.ap-delta-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.5rem 0.25rem;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.ap-delta-item:hover {
  background: #ffffff;
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05);
  border-color: #cbd5e1;
}

.ap-delta-label {
  font-size: 0.62rem;
  font-weight: 850;
  color: #475569;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 0.25rem;
}

.ap-delta-val {
  font-size: 0.78rem;
  font-weight: 850;
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}

.ap-delta-arrow {
  font-size: 0.75rem;
  margin-top: 0.15rem;
  line-height: 1;
}

/* ── KINERJA PER SEGMEN CARD ── */
.area6-segment-container {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1.25rem;
  padding: 0 1.5rem 1.5rem;
  align-items: stretch;
}

.area6-segment-card {
  background: #ffffff;
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-sm);
  border-radius: var(--r-xl);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.asc-header {
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%);
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.95rem 1.5rem;
}

.asc-header-icon {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background-color: rgba(255, 255, 255, 0.2);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
}

.asc-header-title {
  color: #ffffff;
  font-size: 0.88rem;
  font-weight: 850;
  letter-spacing: 0.05em;
}

.asc-body-wrapper {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  width: 100%;
  border-radius: 0 0 16px 16px;
  background: #ffffff;
}

.asc-table {
  width: 100%;
  min-width: 960px;
  border-collapse: collapse;
  text-align: right;
  font-size: 0.76rem;
}

.asc-table th,
.asc-table td {
  padding: 0.65rem 0.75rem;
  vertical-align: middle;
  box-sizing: border-box;
}

.asc-th-seg,
.asc-td-seg {
  text-align: left !important;
  min-width: 170px;
  padding-left: 1.35rem !important;
}

.asc-th-seg {
  font-size: 0.72rem;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
  border-right: 1.5px solid #e2e8f0;
}

.asc-th-metric {
  text-align: center !important;
  font-size: 0.74rem;
  font-weight: 850;
  letter-spacing: -0.01em;
  padding: 0.75rem 0.5rem 0.35rem !important;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.asc-th-metric:not(:last-child) {
  border-right: 1.5px solid #e2e8f0;
}

.asc-th-os { color: #0857c3; }
.asc-th-sml { color: #0284c7; }
.asc-th-npl { color: #dc2626; }

.asc-sub-tr th {
  background: #f8fafc;
  font-size: 0.62rem;
  color: #64748b;
  font-weight: 700;
  border-bottom: 2px solid #e2e8f0;
  padding: 0.35rem 0.65rem 0.65rem !important;
  white-space: nowrap;
}

.asc-sub-tr th:nth-child(3),
.asc-sub-tr th:nth-child(6) {
  border-right: 1.5px solid #e2e8f0;
}

.asc-th-sub {
  display: table-cell;
}

.asc-th-penc,
.asc-th-rka,
.asc-th-pct {
  text-align: right;
}

.asc-td-seg {
  border-right: 1.5px solid #f1f5f9;
}

.asc-seg-info {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  min-width: 0;
}

.asc-seg-icon {
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: #f1f5f9;
  color: #0857c3;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  flex-shrink: 0;
  box-shadow: inset 0 0 0 1px rgba(8, 87, 195, 0.12);
}

.asc-seg-name {
  font-size: 0.78rem;
  font-weight: 800;
  color: #1e293b;
  white-space: nowrap;
}

.asc-tr-data {
  border-bottom: 1px solid #f1f5f9;
  transition: background-color 0.15s ease;
}

.asc-tr-data:hover {
  background-color: #f8fafc;
}

.asc-td-val,
.asc-td-target {
  position: relative;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
  font-size: 0.75rem;
  min-width: 100px;
}

.asc-val-num {
  position: relative;
  z-index: 2;
  font-weight: 800;
  color: #0f172a;
  font-variant-numeric: tabular-nums;
  display: block;
}

.asc-target-num {
  position: relative;
  z-index: 2;
  font-weight: 600;
  color: #64748b;
  font-variant-numeric: tabular-nums;
  display: block;
}

.asc-td-pct {
  min-width: 80px;
  font-weight: 850;
  font-variant-numeric: tabular-nums;
  font-size: 0.76rem;
  text-align: right;
}

.asc-td-pct:not(:last-child) {
  border-right: 1.5px solid #f1f5f9;
}

.asc-bar {
  height: 4px;
  border-radius: 999px;
  margin-bottom: 4px;
  margin-left: auto;
  transition: width 0.4s ease;
}

.asc-tr-total {
  background: #f8fafc;
  border-top: 2px solid #cbd5e1;
  border-bottom: none;
  font-weight: 850;
}

.asc-tr-total td {
  padding-top: 0.95rem;
  padding-bottom: 0.95rem;
}

.asc-total-label {
  font-size: 0.84rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: 0.04em;
}

.asc-tr-total .asc-val-num {
  font-size: 0.82rem;
  font-weight: 900;
}

.asc-tr-total .asc-target-num {
  font-size: 0.76rem;
  font-weight: 750;
  color: #475569;
}

.asc-tr-total .asc-td-pct {
  font-size: 0.82rem;
  font-weight: 900;
}

.asc-tr-total .asc-td-seg {
  border-right: 1.5px solid #e2e8f0;
}

.asc-tr-total .asc-td-pct:not(:last-child) {
  border-right: 1.5px solid #e2e8f0;
}

.legend-item {
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
}

.legend-box {
  width: 8px;
  height: 8px;
  border-radius: 2px;
  flex-shrink: 0;
  display: inline-block;
  vertical-align: middle;
  margin-right: 2px;
}

.bg-os-blue { background: #0857c3; }
.bg-sml-blue { background: #0284c7; }
.bg-npl-blue { background: #dc2626; }
.bg-gray { background: #cbd5e1; }
.bg-ytd { background: #64748b; }
.bg-mtd { background: #0ea5e9; }
.asc-table--micro-products { min-width: 1460px; }
.asc-th-ref {
  min-width: 92px;
  color: #315776 !important;
  background: #edf5fc !important;
  border-left: 1px solid #d7e4f0;
}
.asc-td-ref {
  position: relative;
  min-width: 92px;
  color: #31516d;
  background: #f7fbff;
  text-align: right;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
  font-size: 0.75rem;
}
.asc-ref-num {
  position: relative;
  z-index: 2;
  font-weight: 700;
  color: #334155;
  font-variant-numeric: tabular-nums;
  display: block;
}
.asc-tr-total .asc-ref-num {
  font-size: 0.80rem;
  font-weight: 850;
  color: #1e293b;
}

.bg-tone-green { background: #059669 !important; }
.bg-tone-red { background: #dc2626 !important; }
.bg-tone-neutral { background: #94a3b8 !important; }

.text-tone-green { color: #059669 !important; font-weight: 800 !important; }
.text-tone-red { color: #dc2626 !important; font-weight: 800 !important; }
.text-tone-neutral { color: #64748b !important; font-weight: 700 !important; }

/* ── TOTAL COMPOSITION CARD ── */
.total-composition-card {
  background: #ffffff;
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-sm);
  border-radius: var(--r-xl);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.tcc-body {
  padding: 1.4rem;
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
  gap: 1.25rem;
}

.composition-donut {
  width: 140px;
  height: 140px;
  border-radius: 50%;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: inset 0 0 10px rgba(0,0,0,0.06), 0 4px 12px rgba(0,0,0,0.06);
}

.composition-donut::before {
  content: "";
  position: absolute;
  width: 92px;
  height: 92px;
  background: #ffffff;
  border-radius: 50%;
  z-index: 1;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
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
  font-size: 1.15rem;
  font-weight: 900;
  color: #0f172a;
}

.donut-center-label {
  font-size: 0.58rem;
  font-weight: 800;
  color: #64748b;
  letter-spacing: 0.06em;
}

.tcc-legends {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  min-width: 120px;
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

.tcc-legend-dot.bg-os { background: #0857c3; }
.tcc-legend-dot.bg-lr { background: #7c3aed; }
.tcc-legend-dot.bg-sml { background: #0284c7; }
.tcc-legend-dot.bg-npl { background: #dc2626; }

.tcc-legend-name {
  font-size: 0.72rem;
  font-weight: 800;
  color: #475569;
}

.tcc-legend-val {
  font-size: 0.88rem;
  font-weight: 900;
  color: #0f172a;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}

.tcc-total-badge {
  background: linear-gradient(135deg, #f0f7ff 0%, #e2eeff 100%);
  border: 1.2px solid #bcd8f7;
  border-radius: 10px;
  padding: 0.38rem 0.85rem;
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 1px 4px rgba(8, 87, 195, 0.04);
  margin-top: auto;
}

.tcc-total-label {
  font-size: 0.65rem;
  font-weight: 850;
  color: #334e68;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.tcc-total-val {
  font-size: 1.05rem;
  font-weight: 900;
  color: #0857c3;
  letter-spacing: -0.01em;
  font-variant-numeric: tabular-nums;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}

.tcc-total-unit {
  font-size: 0.78rem;
  font-weight: 800;
  color: #0857c3;
  margin-left: 0.15rem;
}

/* ── HORIZONTAL COMPOSITION CHART (MICRO SCOPE) ── */
.total-composition-card--micro .tcc-body {
  padding: 0.65rem 0.85rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 0.45rem;
  height: 100%;
}

.tcc-horizontal-chart {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  width: 100%;
  padding: 0.35rem 0;
}

.tcc-horizontal-row {
  display: grid;
  grid-template-columns: 52px 1fr auto;
  align-items: center;
  gap: 0.85rem;
  min-width: 0;
}

.tcc-horizontal-label {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.74rem;
  font-weight: 850;
  color: #334155;
  letter-spacing: 0.02em;
}

.tcc-horizontal-marker {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
}

.tcc-horizontal-marker.lar,
.tcc-horizontal-marker.os {
  background: #0857c3;
  box-shadow: 0 0 0 2px rgba(8, 87, 195, 0.2);
}

.tcc-horizontal-marker.lr,
.tcc-horizontal-marker.restruk {
  background: #7c3aed;
  box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.2);
}

.tcc-horizontal-marker.sml {
  background: #0284c7;
  box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.2);
}

.tcc-horizontal-marker.npl {
  background: #dc2626;
  box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.2);
}

.tcc-horizontal-track {
  height: 10px;
  background: #f1f5f9;
  border-radius: 999px;
  overflow: hidden;
  position: relative;
  box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.06);
}

.tcc-horizontal-bar {
  display: block;
  height: 100%;
  border-radius: 999px;
  min-width: 6px;
  transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.tcc-horizontal-bar.lar,
.tcc-horizontal-bar.os {
  background: linear-gradient(90deg, #0857c3 0%, #2563eb 100%);
  box-shadow: 0 2px 6px rgba(8, 87, 195, 0.3);
}

.tcc-horizontal-bar.lr,
.tcc-horizontal-bar.restruk {
  background: linear-gradient(90deg, #7c3aed 0%, #a855f7 100%);
  box-shadow: 0 2px 6px rgba(124, 58, 237, 0.3);
}

.tcc-horizontal-bar.sml {
  background: linear-gradient(90deg, #0284c7 0%, #38bdf8 100%);
  box-shadow: 0 2px 6px rgba(2, 132, 199, 0.3);
}

.tcc-horizontal-bar.npl {
  background: linear-gradient(90deg, #dc2626 0%, #f87171 100%);
  box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
}

.tcc-horizontal-value {
  display: inline-flex;
  align-items: baseline;
  justify-content: flex-end;
  gap: 0.45rem;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
  min-width: 130px;
  text-align: right;
}

.tcc-horizontal-value span {
  font-size: 0.82rem;
  font-weight: 850;
  color: #0f172a;
  font-variant-numeric: tabular-nums;
}

.tcc-horizontal-value small {
  font-size: 0.72rem;
  font-weight: 800;
  color: #64748b;
  font-variant-numeric: tabular-nums;
}

/* ── COMPACT STRUCTURED MICRO QUALITY MATRIX ── */
.tcc-quality-matrix {
  display: grid;
  min-width: 0;
  overflow: hidden;
  border: 1px solid #cce0f5;
  border-radius: 12px;
  background: #ffffff;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
}
.tcc-quality-row {
  --quality-tone: #0754bd;
  display: grid;
  min-width: 0;
  grid-template-columns: 88px 1fr 1fr;
  align-items: center;
  border-bottom: 1px solid #eaf0f7;
  transition: background-color 0.12s ease;
}
.tcc-quality-row:hover:not(.tcc-quality-row--head):not(.tcc-quality-row--total) {
  background-color: #f7faff;
}
.tcc-quality-row > span {
  display: flex;
  min-width: 0;
  min-height: 25px;
  flex-direction: column;
  justify-content: center;
  padding: 0.16rem 0.48rem;
  white-space: nowrap;
}
.tcc-quality-row > span + span {
  border-left: 1px solid #eef3f9;
}
.tcc-quality-row--head {
  color: #ffffff;
  background: linear-gradient(135deg, #073f8b 0%, #0d5bbd 100%);
  font-size: 0.64rem;
  font-weight: 850;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  border-bottom: 0;
}
.tcc-quality-row--head > span {
  min-height: 26px;
  padding: 0.22rem 0.48rem;
  align-items: flex-end;
  text-align: right;
  white-space: nowrap;
}
.tcc-quality-row--head > span:first-child {
  align-items: flex-start;
  text-align: left;
}
.tcc-quality-group {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.4rem;
  padding: 0.14rem 0.48rem;
  color: #1e3a5f;
  background: #edf5fd;
  border-top: 1px solid #d8e6f7;
  border-bottom: 1px solid #d8e6f7;
  font-size: 0.58rem;
  font-weight: 850;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  line-height: 1.2;
}
.tcc-quality-group small {
  color: #587593;
  font-size: 0.52rem;
  font-weight: 650;
  text-transform: none;
  letter-spacing: 0;
  background: rgba(255, 255, 255, 0.8);
  padding: 0.05rem 0.32rem;
  border-radius: 3px;
  border: 1px solid #d8e6f7;
}
.tcc-quality-name {
  flex-direction: row !important;
  align-items: center;
  gap: 0.38rem;
  color: #1e293b;
  font-size: 0.72rem;
  font-weight: 850;
  white-space: nowrap;
}
.tcc-quality-name i {
  width: 4px;
  height: 14px;
  flex: 0 0 4px;
  border-radius: 999px;
  background: var(--quality-tone);
}
.tcc-cell-bar {
  height: 3.5px;
  border-radius: 999px;
  margin-bottom: 2px;
  margin-left: auto;
  transition: width 0.3s ease;
}
.tcc-quality-value.is-up .tcc-cell-bar--mtd {
  background: #dc2626;
}
.tcc-quality-value.is-down .tcc-cell-bar--mtd {
  background: #059669;
}
.tcc-quality-value.is-flat .tcc-cell-bar--mtd {
  background: #94a3b8;
}
.tcc-quality-row--total .tcc-quality-value.is-up .tcc-cell-bar--mtd {
  background: #f87171;
}
.tcc-quality-row--total .tcc-quality-value.is-down .tcc-cell-bar--mtd {
  background: #34d399;
}
.tcc-val-row {
  display: flex;
  align-items: baseline;
  justify-content: flex-end;
  gap: 0.35rem;
  width: 100%;
}
.tcc-val-row b {
  color: #0f172a;
  font-size: 0.76rem;
  font-weight: 850;
  font-variant-numeric: tabular-nums;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}
.tcc-val-row small {
  color: #64748b;
  font-size: 0.62rem;
  font-weight: 750;
  font-variant-numeric: tabular-nums;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}
.tcc-quality-value {
  align-items: flex-end;
  text-align: right;
}
.tcc-quality-value b {
  color: #0f172a;
  font-size: 0.76rem;
  font-weight: 850;
  font-variant-numeric: tabular-nums;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}
.tcc-quality-value small {
  color: #64748b;
  font-size: 0.56rem;
  font-weight: 700;
  margin-top: 1px;
  font-variant-numeric: tabular-nums;
}
.tcc-quality-value.is-up b {
  color: #dc2626;
}
.tcc-quality-value.is-down b {
  color: #059669;
}
.tcc-quality-row.tone-purple { --quality-tone: #7c3aed; }
.tcc-quality-row.tone-blue { --quality-tone: #0857c3; }
.tcc-quality-row.tone-cyan { --quality-tone: #0284c7; }
.tcc-quality-row.tone-amber { --quality-tone: #d97706; }
.tcc-quality-row.tone-orange { --quality-tone: #ea580c; }
.tcc-quality-row.tone-red { --quality-tone: #dc3545; }
.tcc-quality-row.tone-crimson { --quality-tone: #9f1239; }
.tcc-quality-row--total {
  color: #ffffff;
  background: linear-gradient(135deg, #072d63 0%, #0c438c 100%);
  border-bottom: 0;
  font-weight: 850;
}
.tcc-quality-row--total > span {
  min-height: 28px;
  padding: 0.2rem 0.48rem;
}
.tcc-quality-row--total b {
  color: #ffffff;
  font-size: 0.78rem;
  font-weight: 900;
}
.tcc-quality-row--total .tcc-quality-name {
  color: #ffffff;
  font-size: 0.72rem;
  white-space: nowrap;
}
.tcc-quality-empty {
  padding: 0.75rem;
  color: #667b91;
  text-align: center;
  font-size: 0.68rem;
}

/* ── TREND POSISI & PERFORMANCE VS RKA ── */
.area6-trend-perf-container {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
}

.trend-position-card,
.perf-rka-card {
  background: #ffffff;
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-sm);
  border-radius: var(--r-xl);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.tpc-body {
  padding: 1.25rem 0.5rem;
  display: flex;
  height: 100%;
  align-items: stretch;
}

.trend-col {
  flex: 1 1 0;
  min-width: 0;
  padding: 0 0.85rem;
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
  background-color: var(--c-border);
}

.trend-col-title {
  font-size: 0.74rem;
  font-weight: 850;
  margin-bottom: 1.15rem;
  text-align: center;
  letter-spacing: 0.03em;
  white-space: nowrap;
}

.text-os-blue { color: #0857c3; }
.text-sml-blue { color: #0284c7; }
.text-npl-red { color: #dc2626; }

.trend-chart-wrapper {
  width: 100%;
  max-width: 270px;
  margin: 0 auto;
}

.trend-dates-row {
  display: flex;
  justify-content: space-between;
  width: 100%;
  max-width: 270px;
  margin: 0.85rem auto 0;
  padding: 0 7%;
}

.trend-date-label {
  font-size: 0.62rem;
  font-weight: 700;
  color: #475569;
  text-align: center;
  transform: translateX(-50%);
  width: 0;
  line-height: 1.2;
}

.prc-body {
  padding: 1.15rem 1.25rem;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.perf-table-wrapper {
  max-width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.perf-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 480px;
}

.perf-table th {
  font-size: 0.68rem;
  font-weight: 800;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  padding: 0.6rem 0.35rem;
  border-bottom: 2px solid var(--c-border);
  text-align: center;
  line-height: 1.25;
  white-space: nowrap;
}

.perf-table td {
  font-size: 0.76rem;
  padding: 0.75rem 0.35rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  text-align: center;
  white-space: nowrap;
}

.perf-table tr:hover td {
  background: #f8fafc;
}

.perf-indicator-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 850;
  color: #0f172a;
  text-align: left;
}

.perf-indicator-icon {
  width: 26px;
  height: 26px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  flex-shrink: 0;
}

.bg-icon-os { background: #eff6ff; color: #0857c3; }
.bg-icon-sml { background: #f0f9ff; color: #0284c7; }
.bg-icon-npl { background: #fef2f2; color: #dc2626; }

.perf-mono-cell {
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
  font-weight: 800;
  color: #334155;
  font-variant-numeric: tabular-nums;
  font-size: 0.76rem;
}

.perf-pct-cell {
  font-weight: 900;
  font-variant-numeric: tabular-nums;
  font-size: 0.76rem;
}

.perf-status-circle {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 0.62rem;
  box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}

.bg-status-red { background: #dc2626; }
.bg-status-green { background: #15803d; }
.bg-status-amber, .bg-status-yellow { background: #d97706; }

/* ── LEADERBOARD & RANKINGS ── */
.cabang-performance-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1.25rem;
  padding: 0 1.5rem 1.5rem;
}

.perf-panel-card {
  background: #ffffff;
  border: 1px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

.perf-panel-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-hover);
}

.perf-panel-head {
  padding: 1.15rem 1.5rem 0.95rem;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.perf-panel-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  font-size: 0.92rem;
  font-weight: 900;
  color: #0f172a;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.perf-panel-badge {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  box-shadow: 0 0 8px currentColor;
}
.perf-panel-badge.bg-simp { background-color: #059669; color: #059669; }
.perf-panel-badge.bg-pinj { background-color: #0857c3; color: #0857c3; }
.perf-panel-badge.bg-sml { background-color: #d97706; color: #d97706; }
.perf-panel-badge.bg-npl { background-color: #dc2626; color: #dc2626; }

.perf-panel-subtitle {
  font-size: 0.68rem;
  color: #64748b;
  margin-top: 0.2rem;
  font-weight: 550;
}

.perf-panel-body {
  padding: 1.35rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}

.perf-bar-row {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.perf-bar-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.perf-bar-branch {
  font-size: 0.78rem;
  font-weight: 800;
  color: #1e293b;
}

.perf-bar-value {
  font-size: 0.78rem;
  font-weight: 900;
  color: #0f172a;
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}

.perf-bar-track {
  height: 9px;
  background: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
}

.perf-bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

.perf-bar-fill.bg-simp-grad {
  background: linear-gradient(90deg, #059669 0%, #10b981 100%);
}
.perf-bar-fill.bg-pinj-grad {
  background: linear-gradient(90deg, #0857c3 0%, #3b82f6 100%);
}
.perf-bar-fill.bg-sml-grad {
  background: linear-gradient(90deg, #d97706 0%, #f59e0b 100%);
}
.perf-bar-fill.bg-npl-grad {
  background: linear-gradient(90deg, #dc2626 0%, #f87171 100%);
}

.area6-ranking-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.85rem;
  padding: 0 1.5rem 1.5rem;
}

.rank-card {
  border: 1px solid var(--c-border);
  background: #ffffff;
  border-radius: var(--r-xl);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.rank-card-head {
  padding: 1rem 1.25rem 0.85rem;
  border-bottom: 1px solid #f1f5f9;
  background: #f8fafc;
}

.rank-card-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.84rem;
  font-weight: 850;
  color: #0f172a;
}

.rank-list {
  padding: 0.75rem 1rem;
}

.rank-row {
  display: grid;
  grid-template-columns: 28px minmax(0, 1fr) auto;
  gap: 0.65rem;
  align-items: center;
  padding: 0.6rem 0;
  border-bottom: 1px solid #f1f5f9;
}

.rank-row:last-child {
  border-bottom: 0;
}

.rank-no {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f1f5f9;
  color: #334155;
  font-size: 0.7rem;
  font-weight: 850;
  border-radius: 8px;
}

.rank-row:nth-child(1) .rank-no {
  background: linear-gradient(135deg, #fbbf24, #d97706);
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(217, 119, 6, 0.3);
}
.rank-row:nth-child(2) .rank-no {
  background: linear-gradient(135deg, #94a3b8, #64748b);
  color: #ffffff;
}
.rank-row:nth-child(3) .rank-no {
  background: linear-gradient(135deg, #d97706, #b45309);
  color: #ffffff;
}

.rank-name {
  font-size: 0.76rem;
  font-weight: 800;
  color: #0f172a;
}

.rank-meta {
  font-size: 0.64rem;
  color: #64748b;
  margin-top: 0.05rem;
}

.rank-val {
  text-align: right;
  font-size: 0.76rem;
  font-weight: 900;
  color: #0f172a;
}

/* ── LANDING EXECUTIVE SUMMARY ── */
.landing-summary {
  margin: 1.25rem 0;
  background: #ffffff;
  border: 1px solid var(--c-border);
  border-radius: var(--r-2xl);
  box-shadow: var(--shadow-md);
  overflow: hidden;
}

.landing-summary-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.65rem;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  border-bottom: 1px solid var(--c-border);
}

.landing-summary-title {
  font-size: 1.15rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -0.02em;
}

.landing-summary-sub {
  margin-top: 0.18rem;
  font-size: 0.75rem;
  font-weight: 550;
  color: #64748b;
}

.landing-summary-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
  padding: 0.42rem 0.95rem;
  background: #eff6ff;
  border: 1.5px solid #bfdbfe;
  color: #0857c3;
  font-size: 0.7rem;
  font-weight: 850;
  border-radius: var(--r-pill);
  white-space: nowrap;
}

.landing-summary-grid {
  display: grid;
  grid-template-columns: 1.05fr 1fr 1.12fr;
  gap: 1.15rem;
  padding: 1.35rem;
}

.landing-summary-card {
  min-width: 0;
  border: 1px solid var(--c-border);
  background: #ffffff;
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.landing-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.95rem 1.25rem;
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%);
}

.landing-card-title {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  font-size: 0.82rem;
  font-weight: 850;
  color: #ffffff;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.landing-card-icon-wrap {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}

.landing-card-period {
  font-size: 0.68rem;
  font-weight: 750;
  color: #e0f2fe;
  background: rgba(255, 255, 255, 0.16);
  padding: 0.28rem 0.7rem;
  border-radius: var(--r-pill);
  white-space: nowrap;
}

.landing-main-value {
  padding: 1.15rem 1.25rem 0.5rem;
}

.landing-main-value .value {
  font-size: 1.55rem;
  font-weight: 900;
  line-height: 1.1;
  color: #0f172a;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
}

.landing-main-value .meta {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  margin-top: 0.45rem;
  font-size: 0.72rem;
  font-weight: 850;
}

.landing-branch-list,
.landing-decision-list,
.landing-segment-list {
  display: grid;
  gap: 0.65rem;
  padding: 0.85rem 1.15rem 1.15rem;
}

.landing-decision-summary-strip {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.6rem;
  padding: 0.95rem 1.15rem 0;
}

.landing-decision-chip {
  min-width: 0;
  border: 1.5px solid #bfdbfe;
  background: linear-gradient(180deg, #f8fbff 0%, #eff6ff 100%);
  border-radius: var(--r-md);
  padding: 0.65rem 0.8rem;
}

.landing-decision-chip span {
  display: block;
  color: #475569;
  font-size: 0.62rem;
  font-weight: 850;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.landing-decision-chip strong {
  display: block;
  margin-top: 0.25rem;
  color: #0f172a;
  font-size: 0.84rem;
  font-weight: 900;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-variant-numeric: tabular-nums;
}

.landing-branch-row,
.landing-decision-row,
.landing-segment-row {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  min-height: 54px;
  padding: 0.7rem 0.95rem;
  background: #f8fafc;
  border: 1px solid var(--c-border);
  border-radius: var(--r-md);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.landing-branch-row:hover,
.landing-decision-row:hover,
.landing-segment-row:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
  border-color: var(--c-blue);
  background: #ffffff;
}

.landing-branch-icon,
.landing-decision-icon,
.landing-segment-icon {
  flex: 0 0 38px;
  width: 38px;
  height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: var(--c-blue);
  border-radius: 10px;
  font-size: 0.95rem;
  transition: all 0.22s ease;
}

.landing-branch-row:hover .landing-branch-icon,
.landing-decision-row:hover .landing-decision-icon,
.landing-segment-row:hover .landing-segment-icon {
  background: var(--c-blue);
  color: #ffffff;
  border-color: var(--c-blue);
}

.landing-row-label-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
  width: 100%;
}

.landing-row-label {
  font-size: 0.76rem;
  font-weight: 850;
  color: #1e293b;
}

.landing-row-sub {
  font-size: 0.65rem;
  font-weight: 750;
  color: #64748b;
}

.landing-row-value {
  font-size: 0.84rem;
  font-weight: 900;
  color: #0f172a;
  font-variant-numeric: tabular-nums;
}

.landing-progress-container {
  height: 6px;
  background: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
  width: 100%;
}

.landing-progress-bar {
  height: 100%;
  border-radius: inherit;
  transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.landing-progress-bar.bg-primary { background: linear-gradient(90deg, #0857c3, #3b82f6) !important; }
.landing-progress-bar.bg-success, .landing-progress-bar.bg-green { background: linear-gradient(90deg, #059669, #10b981) !important; }
.landing-progress-bar.bg-amber { background: linear-gradient(90deg, #d97706, #f59e0b) !important; }
.landing-progress-bar.bg-red { background: linear-gradient(90deg, #dc2626, #ef4444) !important; }
.landing-progress-bar.bg-blue { background: linear-gradient(90deg, #0284c7, #06b6d4) !important; }

.landing-scope-caption {
  margin: 1rem 1.15rem 0;
  display: inline-flex;
  align-items: center;
  padding: 0.32rem 0.85rem;
  border-radius: var(--r-pill);
  background: #eff6ff;
  border: 1.5px solid #bfdbfe;
  color: #0857c3;
  font-size: 0.7rem;
  font-weight: 850;
}

.landing-empty {
  padding: 1.5rem 1.25rem;
  color: #64748b;
  font-size: 0.76rem;
  font-weight: 700;
  text-align: center;
}

/* ── MAIN GRID (CHART & DIGITAL) ── */
.main-grid {
  display: grid;
  grid-template-columns: minmax(420px, 1fr) minmax(0, 1.55fr);
  gap: 1.25rem;
  align-items: stretch;
  margin-top: 1.25rem;
}

.chart-panel {
  background: #ffffff;
  border: 1px solid var(--c-border);
  padding: 1.4rem;
  min-height: 390px;
  position: relative;
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-sm);
  border-radius: var(--r-2xl);
}

.cp-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.cp-title {
  font-size: 0.95rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -0.01em;
}

.cp-legend {
  display: flex;
  gap: 0.95rem;
}

.cp-leg-item {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.68rem;
  font-weight: 750;
  color: #475569;
}

.cp-leg-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.digital-panel {
  background: #ffffff;
  border: 1px solid var(--c-border);
  padding: 1.4rem;
  box-shadow: var(--shadow-sm);
  border-radius: var(--r-2xl);
}

.dp-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.15rem;
  border-bottom: 1px solid var(--c-border);
  padding-bottom: 0.75rem;
}

.dp-title {
  font-size: 0.95rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -0.01em;
}

.dp-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.95rem;
}

/* 8 Digital Strategy Cards */
.dc {
  padding: 1.15rem 1rem 1rem;
  color: #0f172a;
  position: relative;
  overflow: hidden;
  cursor: pointer;
  text-decoration: none;
  display: flex;
  flex-direction: column;
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  border: 1px solid var(--c-border) !important;
  background: #ffffff !important;
  text-align: left;
  width: 100%;
  min-height: 200px;
  font-family: inherit;
  appearance: none;
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-xs);
}

.dc:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-hover);
  border-color: #cbd5e1 !important;
}

.dc-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.32rem;
  padding: 0.28rem 0.65rem;
  font-size: 0.62rem;
  font-weight: 850;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  margin-bottom: 0.6rem;
  width: fit-content;
  border-radius: var(--r-sm);
}

.dc-val {
  font-size: 1.45rem;
  font-weight: 900;
  line-height: 1.1;
  color: #0f172a;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
}

.dc-label {
  font-size: 0.72rem;
  color: #475569;
  font-weight: 750;
  margin-bottom: 0.25rem;
}

.dc-sub {
  font-size: 0.66rem;
  font-weight: 600;
  color: #64748b;
}

.dc-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.3rem;
  margin-top: 0.75rem;
}

.dc-stat {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 0.4rem 0.45rem;
  border-radius: 8px;
}

.dc-stat-lbl {
  font-size: 0.58rem;
  color: #64748b;
  font-weight: 700;
}

.dc-stat-val {
  font-size: 0.76rem;
  font-weight: 850;
  color: #0f172a;
  font-variant-numeric: tabular-nums;
}

.dc-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 0.75rem;
}

.dc-trend {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.62rem;
  font-weight: 800;
  padding: 0.22rem 0.6rem;
  background: #f1f5f9;
  color: #334155;
  border-radius: var(--r-sm);
}

.dc-link {
  font-size: 0.65rem;
  font-weight: 800;
  color: var(--c-blue);
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

/* Digital Card Accents */
.dc-edc { border-top: 3px solid #0857c3 !important; }
.dc-edc .dc-badge { background: #eff6ff; color: #0857c3; border: 1px solid #bfdbfe; }

.dc-qris { border-top: 3px solid #0891b2 !important; }
.dc-qris .dc-badge { background: #ecfeff; color: #0891b2; border: 1px solid #a5f3fc; }

.dc-qlola { border-top: 3px solid #7c3aed !important; }
.dc-qlola .dc-badge { background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; }

.dc-brimo { border-top: 3px solid #2563eb !important; }
.dc-brimo .dc-badge { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }

.dc-brilink { border-top: 3px solid #059669 !important; }
.dc-brilink .dc-badge { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }

.dc-casa { border-top: 3px solid #d97706 !important; }
.dc-casa .dc-badge { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }

.dc-dormant { border-top: 3px solid #dc2626 !important; }
.dc-dormant .dc-badge { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

.dc-payroll { border-top: 3px solid #475569 !important; }
.dc-payroll .dc-badge { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }

.dc-stub { opacity: 0.65; filter: grayscale(0.2); }

/* ── MODAL SOURCE ── */
.dashboard-source-modal { z-index: 2070; }
.modal-backdrop.dashboard-source-backdrop { z-index: 2060; background: #070d18; }
.modal-backdrop.dashboard-source-backdrop.show { opacity: 0.65; }
.dashboard-source-modal .modal-content {
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-lg);
  border-radius: var(--r-2xl);
  overflow: hidden;
}
.dashboard-source-modal .modal-header,
.dashboard-source-modal .modal-footer {
  border-color: #e2e8f0;
  background: #f8fafc;
}
.dashboard-source-modal .modal-title {
  font-size: 1.05rem;
  font-weight: 900;
  color: #0f172a;
}
.source-modal-meta {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
  margin-bottom: 0.95rem;
}
.source-modal-chip {
  border: 1.5px solid #cbd5e1;
  padding: 0.75rem 0.85rem;
  background: #f8fafc;
  border-radius: 12px;
}
.source-modal-chip span {
  display: block;
  font-size: 0.62rem;
  font-weight: 850;
  color: #64748b;
  text-transform: uppercase;
}
.source-modal-chip strong {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.82rem;
  color: #0f172a;
  font-weight: 800;
}
.source-modal-note {
  font-size: 0.76rem;
  color: #334155;
  background: #f0f7ff;
  border: 1.5px solid #bfdbfe;
  padding: 0.85rem 1rem;
  margin-bottom: 0.85rem;
  border-radius: 12px;
}
.source-item-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: var(--shadow-xs);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  overflow: hidden;
}
.source-item-card::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 4px;
  background: #0857c3;
}
.source-item-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
  border-color: #bfdbfe;
}
.source-item-icon {
  width: 38px;
  height: 38px;
  background: #eff6ff;
  color: #0857c3;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
}

/* ── RESPONSIVENESS ── */
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
  .landing-summary-grid { grid-template-columns: 1fr; }
  .area6-segment-container,
  .area6-trend-perf-container { grid-template-columns: 1fr !important; }
}
@media (max-width: 767.98px) {
  .db-header { align-items: flex-start; flex-direction: column; gap: 0.85rem; padding: 1.1rem; }
  .db-meta { width: 100%; }
  .kpi-strip { grid-template-columns: 1fr; }
  .area6-head { align-items: flex-start; flex-direction: column; }
  .area6-head-actions { align-items: flex-start; width: 100%; }
  .landing-scope-stage { width: 100%; flex-direction: column; align-items: flex-start; }
  .area6-scope-toggle { width: 100%; justify-content: space-between; }
  .area6-card-grid, .area6-ranking-grid { grid-template-columns: 1fr; }
  .cabang-performance-grid { grid-template-columns: 1fr; }
  .dp-grid { grid-template-columns: 1fr; }
  .tpc-body { flex-direction: column; gap: 1.5rem; }
  .trend-col:not(:last-child)::after { display: none; }
  .trend-col { border-bottom: 1px solid #f1f5f9; padding-bottom: 1.25rem; }
  .trend-col:last-child { border-bottom: none; }
}
@media (max-width: 575.98px) {
  .db-date-picker-select, .db-pres-btn, .db-ppt-btn, .db-meta-chip { width: 100%; justify-content: center; }
  .source-modal-meta { grid-template-columns: 1fr; }
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
  grid-template-columns: repeat(6, minmax(0, 1fr));
}
.area6-card-grid {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.area6-ranking-grid {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.dp-grid {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.cabang-performance-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
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
  .area6-segment-container,
  .area6-trend-perf-container {
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
  .area6-trend-perf-container,
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
  .area6-trend-perf-container,
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
  .area6-trend-perf-container,
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
  .area6-trend-perf-container,
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
.db-shell.landing-compact .area6-segment-container,
.db-shell.landing-compact .area6-trend-perf-container {
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
  min-height: 36px !important;
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

/* Landing executive redesign */
.db-shell {
  --landing-ink: #0b1739;
  --landing-blue: #0b57d0;
  --landing-cyan: #2e9ee8;
  --landing-recovery: #0f9f75;
  padding: 0.5rem;
  background:
    radial-gradient(circle at 4% 0%, rgba(46, 158, 232, 0.12), transparent 24rem),
    linear-gradient(180deg, #f7faff 0%, #f8fafc 42%, #f4f7fb 100%);
  border-radius: 24px;
}
.db-header {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  padding: 1.15rem 1.35rem;
  color: #ffffff;
  background:
    linear-gradient(118deg, rgba(7, 31, 78, 0.98) 0%, rgba(9, 79, 174, 0.96) 58%, rgba(34, 145, 210, 0.92) 100%);
  border: 1px solid rgba(255, 255, 255, 0.22);
  border-top: 0;
  border-radius: 20px;
  box-shadow: 0 18px 46px -28px rgba(8, 45, 112, 0.85);
}
.db-header::before,
.db-header::after {
  content: "";
  position: absolute;
  z-index: -1;
  border-radius: 999px;
  pointer-events: none;
}
.db-header::before {
  width: 220px;
  height: 220px;
  top: -145px;
  right: 18%;
  background: rgba(255, 255, 255, 0.13);
}
.db-header::after {
  width: 150px;
  height: 150px;
  right: -45px;
  bottom: -105px;
  border: 28px solid rgba(255, 255, 255, 0.09);
}
.db-logo {
  width: 46px;
  height: 46px;
  flex: 0 0 46px;
  background: rgba(255, 255, 255, 0.16);
  border: 1px solid rgba(255, 255, 255, 0.28);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(8px);
}
.db-logo img {
  width: 30px;
  height: 30px;
}
.db-title {
  color: #ffffff;
  font-size: clamp(1rem, 1.5vw, 1.28rem);
  letter-spacing: -0.025em;
}
.db-subtitle {
  color: rgba(235, 245, 255, 0.82);
  font-size: 0.7rem;
}
.db-header .db-date-picker-select {
  min-width: 132px;
  color: #0b469d;
  border-color: rgba(255, 255, 255, 0.42);
  box-shadow: 0 7px 20px -12px rgba(1, 20, 54, 0.7);
}
.db-header .db-meta-chip {
  color: #e9fff6;
  background: rgba(9, 45, 94, 0.32);
  border-color: rgba(183, 234, 214, 0.3);
}
.db-header .db-now {
  color: rgba(239, 247, 255, 0.78);
}
.kpi-strip {
  gap: 0.7rem;
}
.kpi-card {
  min-height: 118px;
  border: 1px solid rgba(203, 213, 225, 0.76);
  border-left-width: 1px !important;
  border-radius: 16px;
  box-shadow: 0 14px 35px -30px rgba(15, 45, 92, 0.72);
}
.kpi-card::before {
  content: "";
  position: absolute;
  inset: 0 auto 0 0;
  width: 4px;
  background: #94a3b8;
}
.kpi-card.simpanan,
.kpi-card.pinjaman,
.kpi-card.portfolio {
  border-left-width: 1px !important;
}
.kpi-card.simpanan::before { background: var(--landing-blue); }
.kpi-card.pinjaman::before { background: #087eaa; }
.kpi-card.portfolio::before { background: var(--landing-recovery); }
.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 18px 38px -26px rgba(15, 45, 92, 0.58);
}
.area6-panel {
  border-color: rgba(191, 204, 224, 0.8);
  border-radius: 22px;
  box-shadow: 0 24px 55px -42px rgba(12, 43, 93, 0.7);
}
.area6-head {
  background:
    linear-gradient(110deg, rgba(239, 246, 255, 0.96), rgba(248, 250, 252, 0.96) 55%, rgba(236, 253, 245, 0.72));
  border-bottom-color: rgba(191, 204, 224, 0.72);
}
.area6-title {
  color: var(--landing-ink);
  letter-spacing: -0.025em;
}
.area6-scope-toggle {
  background: rgba(226, 232, 240, 0.78);
  border-color: rgba(148, 163, 184, 0.38);
}
.area6-scope-btn.active {
  color: #ffffff;
  background: linear-gradient(135deg, #0c4fb7, #1378d3);
  box-shadow: 0 8px 20px -12px rgba(5, 62, 148, 0.9);
}
.area6-panel .area6-card-grid {
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  gap: 1rem;
}
.area6-card-premium {
  border-color: rgba(191, 204, 224, 0.78);
  box-shadow: 0 16px 36px -30px rgba(8, 42, 90, 0.75);
}
.area6-card-premium:focus-visible {
  outline: 3px solid rgba(46, 158, 232, 0.34);
  outline-offset: 3px;
}
.ap-header {
  min-height: 48px;
  height: auto;
  background-image: linear-gradient(125deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0));
}
.ap-header.bg-os,
.ap-badge.bg-os {
  background-color: #104bb5;
}
.ap-header.bg-sml,
.ap-badge.bg-sml {
  background-color: #cc7a08;
}
.ap-header.bg-npl,
.ap-badge.bg-npl {
  background-color: #cf3442;
}
.ap-header.bg-recovery,
.ap-badge.bg-recovery {
  background-color: var(--landing-recovery);
}
.area6-card-premium[data-metric="recovery"] .ap-body {
  background: linear-gradient(180deg, rgba(236, 253, 245, 0.62), #ffffff 45%);
}
.area6-card-premium.is-entering {
  animation: landingCardEnter 0.32s both;
  animation-delay: var(--landing-card-delay, 0ms);
}
@keyframes landingCardEnter {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}
@media (min-width: 1101px) and (max-width: 1399.98px) {
  .area6-panel .area6-card-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  }
}
@media (max-width: 991.98px) {
  .db-header {
    align-items: stretch;
  }
  .db-meta {
    justify-content: flex-start;
  }
}
@media (max-width: 767.98px) {
  .db-shell {
    padding: 0.25rem;
    border-radius: 16px;
  }
  .db-header {
    border-radius: 16px;
  }
  .area6-panel .area6-card-grid {
    grid-template-columns: 1fr !important;
  }
}
@media (prefers-reduced-motion: reduce) {
  .area6-card-premium.is-entering {
    animation: none;
  }
}

/* Scope desk visibility: hide Area 6 ranking, executive summary, and main grid when specific scopes are selected. */
.db-shell.sme-operations-active .area6-ranking-mode,
.db-shell.sme-operations-active .landing-summary,
.db-shell.sme-operations-active .main-grid,
.db-shell.micro-performance-active .area6-ranking-mode,
.db-shell.micro-performance-active .landing-summary,
.db-shell.micro-performance-active .main-grid,
.db-shell.consumer-active .area6-ranking-mode,
.db-shell.consumer-active .landing-summary,
.db-shell.consumer-active .main-grid {
  display: none !important;
}
.db-shell.sme-operations-active .area6-panel {
  overflow: visible;
}
.db-shell.sme-operations-active .area6-head {
  border-bottom-color: #b8c9df;
}
.sme-operations-dashboard {
  display: none;
  min-height: 360px;
  background: #edf4fc;
}
.db-shell.sme-operations-active .sme-operations-dashboard:not(.d-none) {
  display: block;
}
.sme-ops {
  --sme-blue: #064da8;
  --sme-blue-deep: #073574;
  --sme-cyan: #087fbf;
  --sme-ink: #10213a;
  --sme-muted: #5b6b81;
  --sme-line: #cbd8e8;
  display: grid;
  gap: 0.9rem;
  padding: 0.9rem;
  color: var(--sme-ink);
  font-variant-numeric: tabular-nums;
}
.sme-ops *,
.sme-ops *::before,
.sme-ops *::after {
  box-sizing: border-box;
  letter-spacing: 0;
}
.sme-ops-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  min-width: 0;
  padding: 0.85rem 1rem;
  color: #ffffff;
  background: var(--sme-blue-deep);
  border: 1px solid #062c61;
  border-left: 5px solid #19a7df;
  border-radius: 8px;
}
.sme-ops-hero__identity {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  min-width: 0;
}
.sme-ops-hero__identity > div {
  min-width: 0;
}
.sme-ops-hero__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 38px;
  width: 38px;
  height: 38px;
  color: #073574;
  background: #ffffff;
  border-radius: 7px;
}
.sme-ops-eyebrow,
.sme-ops-kicker {
  display: block;
  color: #5d718c;
  font-size: 0.66rem;
  font-weight: 800;
  line-height: 1.25;
  text-transform: uppercase;
}
.sme-ops-eyebrow {
  color: #8ed8f5;
}
.sme-ops-hero h2,
.sme-ops-section h3,
.sme-ops-status-item h4,
.sme-ops-vendor-item h4,
.sme-ops-restruct-row h4 {
  margin: 0;
  overflow-wrap: anywhere;
}
.sme-ops-hero h2 {
  margin-top: 0.15rem;
  font-size: 1rem;
  font-weight: 800;
  line-height: 1.25;
}
.sme-ops-hero p {
  margin: 0.2rem 0 0;
  color: #cbdcf2;
  font-size: 0.7rem;
  line-height: 1.35;
}
.sme-ops-refresh {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  gap: 0.4rem;
  min-height: 36px;
  padding: 0.45rem 0.7rem;
  color: #073574;
  background: #ffffff;
  border: 1px solid #ffffff;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 800;
  cursor: pointer;
}
.sme-ops-refresh:hover {
  color: #ffffff;
  background: #087fbf;
  border-color: #59bce6;
}
.sme-ops-refresh:focus-visible,
.sme-ops-source:focus-visible {
  outline: 3px solid rgba(25, 167, 223, 0.34);
  outline-offset: 2px;
}
.sme-ops-refresh:disabled {
  cursor: wait;
  opacity: 0.74;
}
.sme-ops-refresh.is-loading i {
  animation: smeOpsSpin 0.75s linear infinite;
}
@keyframes smeOpsSpin {
  to { transform: rotate(360deg); }
}
.sme-ops-section {
  min-width: 0;
  padding: 0.85rem;
  background: #ffffff;
  border: 1px solid var(--sme-line);
  border-top: 3px solid var(--sme-blue);
  border-radius: 8px;
  box-shadow: 0 12px 28px -26px rgba(7, 53, 116, 0.8);
}
.sme-ops-section__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-width: 0;
  margin-bottom: 0.75rem;
}
.sme-ops-section__head > div:first-child {
  min-width: 0;
}
.sme-ops-section h3 {
  margin-top: 0.12rem;
  color: #10213a;
  font-size: 0.95rem;
  font-weight: 800;
  line-height: 1.25;
}
.sme-ops-section__meta,
.sme-ops-section__actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 0.45rem;
  min-width: 0;
}
.sme-ops-section__meta span,
.sme-ops-section__meta strong,
.sme-ops-total {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  min-height: 30px;
  padding: 0.32rem 0.55rem;
  border: 1px solid #d4dfec;
  border-radius: 5px;
  background: #f3f7fc;
  color: #42546c;
  font-size: 0.68rem;
  white-space: nowrap;
}
.sme-ops-section__meta strong {
  color: #ffffff;
  background: var(--sme-blue);
  border-color: var(--sme-blue);
}
.sme-ops-total {
  flex-direction: column;
  align-items: flex-start;
  gap: 0;
  min-width: 104px;
  color: #5d718c;
  line-height: 1.2;
}
.sme-ops-total strong {
  color: var(--sme-blue-deep);
  font-size: 0.96rem;
}
.sme-ops-source {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  min-height: 30px;
  max-width: 100%;
  padding: 0.32rem 0.55rem;
  border: 1px solid #b9c8db;
  border-radius: 5px;
  color: #38506d;
  background: #ffffff;
  font-size: 0.66rem;
  font-weight: 700;
  line-height: 1.2;
  text-decoration: none;
}
.sme-ops-source:hover {
  color: #ffffff;
  background: var(--sme-blue);
  border-color: var(--sme-blue);
  text-decoration: none;
}
.sme-ops-source.is-live > i:first-child {
  color: #0aa36f;
  font-size: 0.46rem;
}
.sme-ops-source.is-stale > i:first-child,
.sme-ops-source.is-unavailable > i:first-child {
  color: #cc7108;
}
.sme-ops-source--icon {
  width: 34px;
  padding: 0;
}
.sme-ops-source--icon > i:first-child {
  font-size: 0.72rem !important;
}
.sme-ops-quadrant-summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.55rem;
  margin-bottom: 0.65rem;
}
.sme-ops-q-summary {
  position: relative;
  min-width: 0;
  padding: 0.6rem 0.7rem;
  overflow: hidden;
  background: #f7f9fc;
  border: 1px solid #d8e1ec;
  border-left: 4px solid #71839a;
  border-radius: 6px;
}
.sme-ops-q-summary::after {
  content: "";
  position: absolute;
  right: -8px;
  bottom: -12px;
  width: 44px;
  height: 44px;
  border: 8px solid rgba(100, 116, 139, 0.08);
  border-radius: 50%;
}
.sme-ops-q-summary > span,
.sme-ops-q-summary > strong,
.sme-ops-q-summary > small {
  position: relative;
  z-index: 1;
  display: block;
}
.sme-ops-q-summary > span {
  color: #53647a;
  font-size: 0.65rem;
  font-weight: 750;
}
.sme-ops-q-summary > strong {
  margin: 0.12rem 0;
  color: #10213a;
  font-size: 1.2rem;
  line-height: 1;
}
.sme-ops-q-summary > small {
  color: #6a7a8e;
  font-size: 0.62rem;
}
.sme-ops-q-summary.q1 { border-left-color: #0a9869; background: #effbf7; }
.sme-ops-q-summary.q2 { border-left-color: #0879c9; background: #eef7ff; }
.sme-ops-q-summary.q3 { border-left-color: #c67608; background: #fff8e9; }
.sme-ops-q-summary.q4 { border-left-color: #d03849; background: #fff2f3; }
.sme-ops-branch-list,
.sme-ops-rm-list,
.sme-ops-compact-list,
.sme-ops-restruct-list {
  display: grid;
  gap: 0.4rem;
}
.sme-ops-branch-row {
  display: grid;
  grid-template-columns: minmax(180px, 0.8fr) minmax(420px, 1.7fr);
  align-items: center;
  gap: 0.65rem;
  min-width: 0;
  padding: 0.48rem;
  background: #f8fafc;
  border: 1px solid #dbe4ee;
  border-radius: 6px;
}
.sme-ops-branch-row__name {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  min-width: 0;
}
.sme-ops-branch-row__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 32px;
  width: 32px;
  height: 32px;
  color: #ffffff;
  background: var(--sme-blue);
  border-radius: 5px;
}
.sme-ops-branch-row__name > div {
  min-width: 0;
}
.sme-ops-branch-row__name strong,
.sme-ops-branch-row__name span {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sme-ops-branch-row__name strong {
  color: #17345a;
  font-size: 0.72rem;
}
.sme-ops-branch-row__name span {
  margin-top: 0.12rem;
  color: #6a7a8e;
  font-size: 0.62rem;
}
.sme-ops-branch-row__quadrants {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.35rem;
  min-width: 0;
}
.sme-ops-branch-q {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 0.3rem;
  min-width: 0;
  padding: 0.36rem 0.42rem;
  background: #ffffff;
  border: 1px solid #d9e2ed;
  border-bottom: 3px solid #8393a8;
  border-radius: 5px;
}
.sme-ops-branch-q > span {
  color: #607086;
  font-size: 0.61rem;
  font-weight: 800;
}
.sme-ops-branch-q > strong {
  color: #10213a;
  font-size: 0.82rem;
  text-align: center;
}
.sme-ops-branch-q > small {
  color: #6a7a8e;
  font-size: 0.58rem;
}
.sme-ops-branch-q.q1 { border-bottom-color: #0a9869; }
.sme-ops-branch-q.q2 { border-bottom-color: #0879c9; }
.sme-ops-branch-q.q3 { border-bottom-color: #c67608; }
.sme-ops-branch-q.q4 { border-bottom-color: #d03849; }
.sme-ops-rm-list {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.sme-ops-rm-row {
  display: grid;
  grid-template-columns: 26px minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.48rem;
  min-width: 0;
  padding: 0.45rem 0.5rem;
  border: 1px solid #dbe4ee;
  border-radius: 5px;
  background: #f8fafc;
}
.sme-ops-rm-row__number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  color: #44617f;
  background: #e5edf7;
  border-radius: 4px;
  font-size: 0.62rem;
  font-weight: 800;
}
.sme-ops-rm-row__identity {
  min-width: 0;
}
.sme-ops-rm-row__identity strong,
.sme-ops-rm-row__identity span {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sme-ops-rm-row__identity strong {
  color: #1a3557;
  font-size: 0.7rem;
}
.sme-ops-rm-row__identity span {
  margin-top: 0.1rem;
  color: #6b7b90;
  font-size: 0.6rem;
}
.sme-ops-q-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 30px;
  min-height: 25px;
  border-radius: 4px;
  color: #ffffff;
  background: #71839a;
  font-size: 0.64rem;
  font-weight: 850;
}
.sme-ops-q-badge.q1 { background: #087f5b; }
.sme-ops-q-badge.q2 { background: #0874b8; }
.sme-ops-q-badge.q3 { background: #b76705; }
.sme-ops-q-badge.q4 { background: #c72e40; }
.sme-ops-status-grid {
  display: grid;
  gap: 0.5rem;
}
.sme-ops-status-grid--seven {
  grid-template-columns: repeat(7, minmax(0, 1fr));
}
.sme-ops-status-item {
  min-width: 0;
  padding: 0.55rem;
  background: #f7f9fc;
  border: 1px solid #d9e2ed;
  border-radius: 6px;
}
.sme-ops-status-item__head {
  display: flex;
  align-items: flex-start;
  gap: 0.4rem;
  min-height: 36px;
  margin-bottom: 0.48rem;
}
.sme-ops-status-item__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 27px;
  width: 27px;
  height: 27px;
  color: var(--sme-blue);
  background: #e5effb;
  border-radius: 5px;
}
.sme-ops-status-item h4 {
  color: #344963;
  font-size: 0.66rem;
  font-weight: 750;
  line-height: 1.28;
}
.sme-ops-pair {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.35rem;
}
.sme-ops-pair > div {
  min-width: 0;
  padding-top: 0.38rem;
  border-top: 1px solid #d9e2ed;
}
.sme-ops-pair span,
.sme-ops-pair strong {
  display: block;
}
.sme-ops-pair span {
  color: #6a7a8e;
  font-size: 0.56rem;
}
.sme-ops-pair strong {
  margin-top: 0.12rem;
  overflow-wrap: anywhere;
  color: #10213a;
  font-size: 0.72rem;
}
.sme-ops-status-item[data-status="realisasi"] {
  background: #effaf6;
  border-color: #b6decf;
}
.sme-ops-status-item[data-status="batal"] {
  background: #fff4f4;
  border-color: #efc4c8;
}
.sme-ops-vendor-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.48rem;
}
.sme-ops-vendor-item {
  display: grid;
  grid-template-columns: 32px minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.48rem;
  min-width: 0;
  min-height: 58px;
  padding: 0.48rem 0.55rem;
  background: #f8fafc;
  border: 1px solid #d9e2ed;
  border-radius: 6px;
}
.sme-ops-vendor-item__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  color: #ffffff;
  background: var(--sme-blue);
  border-radius: 5px;
}
.sme-ops-vendor-item > div {
  min-width: 0;
}
.sme-ops-vendor-item h4 {
  color: #263c59;
  font-size: 0.66rem;
  font-weight: 750;
  line-height: 1.25;
}
.sme-ops-vendor-item > div > span {
  display: block;
  margin-top: 0.08rem;
  color: #718197;
  font-size: 0.56rem;
}
.sme-ops-vendor-item > strong {
  min-width: 31px;
  color: var(--sme-blue-deep);
  font-size: 0.94rem;
  text-align: right;
}
.sme-ops-two-column {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.9rem;
  min-width: 0;
}
.sme-ops-attendance {
  padding: 0.62rem;
  margin-bottom: 0.52rem;
  color: #ffffff;
  background: var(--sme-blue-deep);
  border-radius: 6px;
}
.sme-ops-attendance__copy {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: baseline;
  gap: 0.2rem 0.5rem;
}
.sme-ops-attendance__copy > span {
  font-size: 0.68rem;
  font-weight: 750;
}
.sme-ops-attendance__copy > strong {
  font-size: 0.95rem;
}
.sme-ops-attendance__copy > small {
  grid-column: 1 / -1;
  color: #c8daf0;
  font-size: 0.59rem;
}
.sme-ops-progress {
  height: 7px;
  margin-top: 0.45rem;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 4px;
}
.sme-ops-progress > span {
  display: block;
  height: 100%;
  background: #24c99b;
  border-radius: inherit;
}
.sme-ops-compact-row {
  display: grid;
  grid-template-columns: minmax(140px, 1fr) auto minmax(84px, auto);
  align-items: center;
  gap: 0.45rem;
  min-width: 0;
  padding: 0.42rem 0.48rem;
  background: #f8fafc;
  border: 1px solid #dbe4ee;
  border-radius: 5px;
}
.sme-ops-compact-row > span {
  min-width: 0;
  overflow-wrap: anywhere;
  color: #334a67;
  font-size: 0.65rem;
  font-weight: 700;
}
.sme-ops-compact-row > strong,
.sme-ops-compact-row > b {
  color: #53657b;
  font-size: 0.64rem;
  white-space: nowrap;
}
.sme-ops-compact-row > b {
  color: var(--sme-blue-deep);
  text-align: right;
}
.sme-ops-compact-row > b::after {
  content: " Rp Jt";
  color: #718197;
  font-size: 0.53rem;
  font-weight: 600;
}
.sme-ops-restruct-row {
  display: grid;
  grid-template-columns: 28px minmax(110px, 1fr) auto minmax(88px, auto);
  align-items: center;
  gap: 0.45rem;
  min-width: 0;
  padding: 0.47rem 0.5rem;
  background: #f8fafc;
  border: 1px solid #dbe4ee;
  border-radius: 5px;
}
.sme-ops-restruct-row__step {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  color: #ffffff;
  background: var(--sme-blue);
  border-radius: 4px;
  font-size: 0.58rem;
  font-weight: 800;
}
.sme-ops-restruct-row > div {
  min-width: 0;
}
.sme-ops-restruct-row h4 {
  color: #334a67;
  font-size: 0.65rem;
  line-height: 1.2;
}
.sme-ops-restruct-row > div > small {
  display: block;
  margin-top: 0.1rem;
  color: #718197;
  font-size: 0.56rem;
  overflow-wrap: anywhere;
}
.sme-ops-restruct-row > strong,
.sme-ops-restruct-row > b {
  color: #53657b;
  font-size: 0.68rem;
  white-space: nowrap;
}
.sme-ops-restruct-row > b {
  color: var(--sme-blue-deep);
  text-align: right;
}
.sme-ops-restruct-row > strong small,
.sme-ops-restruct-row > b small {
  color: #718197;
  font-size: 0.52rem;
  font-weight: 600;
}
.sme-ops-footnote,
.sme-ops-cache-note {
  margin: 0.55rem 0 0;
  color: #64758b;
  font-size: 0.59rem;
  line-height: 1.4;
}
.sme-ops-cache-note {
  margin: 0;
  padding: 0.6rem 0.75rem;
  color: #794c08;
  background: #fff8e8;
  border: 1px solid #ebd39f;
  border-radius: 6px;
}
.sme-ops-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  min-height: 84px;
  padding: 0.8rem;
  color: #66778d;
  background: #f8fafc;
  border: 1px dashed #bdcad9;
  border-radius: 6px;
  font-size: 0.68rem;
  text-align: center;
}
.sme-ops-loader {
  display: grid;
  gap: 0.7rem;
  padding: 0.9rem;
}
.sme-ops-loader__bar,
.sme-ops-loader__block,
.sme-ops-loader__tile {
  position: relative;
  overflow: hidden;
  background: #dbe6f2;
  border-radius: 6px;
}
.sme-ops-loader__bar::after,
.sme-ops-loader__block::after,
.sme-ops-loader__tile::after {
  content: "";
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.72), transparent);
  animation: smeOpsShimmer 1.25s infinite;
}
.sme-ops-loader__bar { height: 58px; background: #afc4dc; }
.sme-ops-loader__block { height: 158px; }
.sme-ops-loader__tiles {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.55rem;
}
.sme-ops-loader__tile { height: 74px; }
.sme-ops-loader__copy {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  color: #3e5774;
  font-size: 0.7rem;
  font-weight: 700;
}
@keyframes smeOpsShimmer {
  to { transform: translateX(100%); }
}
.sme-ops-load-error {
  display: grid;
  justify-items: center;
  gap: 0.45rem;
  min-height: 260px;
  padding: 2rem 1rem;
  text-align: center;
}
.sme-ops-load-error > i {
  color: #d03849;
  font-size: 1.8rem;
}
.sme-ops-load-error h3 {
  margin: 0;
  color: #1f344f;
  font-size: 0.9rem;
}
.sme-ops-load-error p {
  max-width: 520px;
  margin: 0;
  color: #64758b;
  font-size: 0.68rem;
}
.sme-ops-load-error button {
  min-height: 34px;
  padding: 0.42rem 0.7rem;
  color: #ffffff;
  background: var(--c-blue);
  border: 0;
  border-radius: 5px;
  font-size: 0.68rem;
  font-weight: 750;
}
@media (max-width: 1399.98px) {
  .sme-ops-status-grid--seven { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .sme-ops-vendor-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 1099.98px) {
  .sme-ops-two-column { grid-template-columns: 1fr; }
  .sme-ops-branch-row { grid-template-columns: 1fr; }
  .sme-ops-vendor-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 767.98px) {
  .sme-ops { padding: 0.65rem; gap: 0.7rem; }
  .area6-scope-toggle {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.3rem;
    border-radius: 8px;
  }
  .area6-scope-btn {
    width: 100%;
    min-width: 0;
    padding-right: 0.5rem;
    padding-left: 0.5rem;
    border-radius: 6px;
  }
  .sme-ops-hero,
  .sme-ops-section__head { align-items: flex-start; }
  .sme-ops-section__head { flex-direction: column; }
  .sme-ops-section__meta,
  .sme-ops-section__actions { justify-content: flex-start; width: 100%; }
  .sme-ops-quadrant-summary,
  .sme-ops-branch-row__quadrants,
  .sme-ops-status-grid--seven,
  .sme-ops-loader__tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .sme-ops-rm-list,
  .sme-ops-vendor-grid { grid-template-columns: 1fr; }
  .sme-ops-compact-row {
    grid-template-columns: minmax(0, 1fr) auto;
  }
  .sme-ops-compact-row > b {
    grid-column: 1 / -1;
    padding-top: 0.28rem;
    border-top: 1px solid #dbe4ee;
    text-align: left;
  }
  .sme-ops-restruct-row {
    grid-template-columns: 28px minmax(0, 1fr) auto;
  }
  .sme-ops-restruct-row > b {
    grid-column: 2 / -1;
    padding-top: 0.3rem;
    border-top: 1px solid #dbe4ee;
    text-align: left;
  }
}
@media (max-width: 575.98px) {
  .sme-ops-hero { align-items: flex-start; }
  .sme-ops-hero__icon { display: none; }
  .sme-ops-refresh span { display: none; }
  .sme-ops-refresh { width: 36px; padding: 0; }
  .sme-ops-quadrant-summary,
  .sme-ops-status-grid--seven { grid-template-columns: 1fr; }
  .sme-ops-branch-row__quadrants { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .sme-ops-source:not(.sme-ops-source--icon) { width: 100%; }
  .sme-ops-total { min-width: 0; flex: 1 1 120px; }
}
@media (prefers-reduced-motion: reduce) {
  .sme-ops-refresh.is-loading i,
  .sme-ops-loader__bar::after,
  .sme-ops-loader__block::after,
  .sme-ops-loader__tile::after {
    animation: none;
  }
}

/* SME landing language v2: bold banking colors, structured data bands, no legacy admin panels. */
.db-shell.sme-operations-active .sme-operations-dashboard {
  margin-top: 1rem;
  background: #eef5fd;
  border-top: 1px solid #c9d9eb;
}
.sme-ops {
  --sme-nusantara: #0754bd;
  --sme-nusantara-dark: #063476;
  --sme-cakrawala: #13a7e2;
  --sme-azure: #0877d1;
  --sme-green: #009b72;
  --sme-amber: #e38000;
  --sme-red: #d62f49;
  --sme-ink: #10223f;
  --sme-muted: #5c6e86;
  --sme-line: #cbd9e9;
  gap: 1rem;
  padding: 1rem;
  background: #eef5fd;
}
.sme-ops-intro {
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.9rem;
  min-width: 0;
  min-height: 86px;
  padding: 1rem 1.1rem;
  overflow: hidden;
  color: #ffffff;
  background: var(--sme-nusantara);
  border: 1px solid #06489f;
  border-radius: 8px;
}
.sme-ops-intro::after {
  content: "";
  position: absolute;
  top: -48px;
  right: 22%;
  width: 190px;
  height: 180px;
  transform: rotate(24deg);
  background: rgba(19, 167, 226, 0.24);
  pointer-events: none;
}
.sme-ops-intro__mark {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  color: var(--sme-nusantara);
  background: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.8);
  border-radius: 8px;
  font-size: 1.25rem;
}
.sme-ops-intro__mark span {
  position: absolute;
  right: -7px;
  bottom: -7px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  color: #ffffff;
  background: var(--sme-amber);
  border: 3px solid var(--sme-nusantara);
  border-radius: 50%;
  font-size: 0.58rem;
}
.sme-ops-intro__copy,
.sme-ops-intro__actions {
  position: relative;
  z-index: 1;
  min-width: 0;
}
.sme-ops-intro__copy h2 {
  margin: 0.14rem 0 0;
  color: #ffffff;
  font-size: 1.14rem;
  font-weight: 850;
  line-height: 1.25;
}
.sme-ops-intro__copy p {
  margin: 0.22rem 0 0;
  color: #dcecff;
  font-size: 0.72rem;
  line-height: 1.4;
}
.sme-ops-intro .sme-ops-eyebrow {
  color: #9ee4ff;
  font-size: 0.64rem;
}
.sme-ops-intro__actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.55rem;
}
.sme-ops-live-label {
  display: inline-flex;
  align-items: center;
  gap: 0.38rem;
  min-height: 36px;
  padding: 0.42rem 0.65rem;
  color: #ffffff;
  background: rgba(3, 42, 95, 0.44);
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 6px;
  font-size: 0.64rem;
  font-weight: 750;
  white-space: nowrap;
}
.sme-ops-live-label i {
  color: #6cf0bf;
  font-size: 0.48rem;
}
.sme-ops .sme-ops-refresh {
  min-height: 38px;
  padding: 0.48rem 0.75rem;
  color: #063476;
  background: #ffffff;
  border: 1px solid #ffffff;
  border-radius: 6px;
  font-size: 0.7rem;
  transition: color 160ms ease, background 160ms ease, transform 160ms ease;
}
.sme-ops .sme-ops-refresh:hover {
  color: #ffffff;
  background: #073f8f;
  border-color: #9ee4ff;
  transform: translateY(-1px);
}
.sme-ops-feature {
  min-width: 0;
  padding: 1rem;
  background: #ffffff;
  border: 1px solid var(--sme-line);
  border-radius: 8px;
  box-shadow: 0 16px 30px -28px rgba(6, 52, 118, 0.72);
}
.sme-ops-feature-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  min-width: 0;
  margin-bottom: 0.9rem;
}
.sme-ops-section-heading {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  min-width: 0;
}
.sme-ops-section-heading > div {
  min-width: 0;
}
.sme-ops-section-heading h3,
.sme-ops-vendor-item h4,
.sme-ops-status-item h4,
.sme-ops-restruct-step h4 {
  margin: 0;
  overflow-wrap: anywhere;
}
.sme-ops-section-heading h3 {
  margin-top: 0.12rem;
  color: var(--sme-ink);
  font-size: 1rem;
  font-weight: 850;
  line-height: 1.25;
}
.sme-ops-section-heading p {
  margin: 0.18rem 0 0;
  max-width: 650px;
  color: var(--sme-muted);
  font-size: 0.66rem;
  line-height: 1.45;
}
.sme-ops-section-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 38px;
  width: 38px;
  height: 38px;
  color: #ffffff;
  background: var(--sme-nusantara);
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 850;
}
.sme-ops-feature-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 38px;
  width: 38px;
  height: 38px;
  color: var(--sme-nusantara);
  background: #e8f3ff;
  border: 1px solid #c6ddf7;
  border-radius: 50%;
}
.sme-ops-kicker {
  color: #517092;
  font-size: 0.61rem;
}
.sme-ops .sme-ops-source {
  flex: 0 0 auto;
  min-height: 34px;
  padding: 0.4rem 0.62rem;
  color: #34516f;
  background: #f7faff;
  border: 1px solid #bfd0e3;
  border-radius: 6px;
  font-size: 0.62rem;
  white-space: nowrap;
}
.sme-ops .sme-ops-source:hover {
  color: #ffffff;
  background: var(--sme-nusantara);
  border-color: var(--sme-nusantara);
}
.sme-ops .sme-ops-source--icon {
  width: 36px;
  padding: 0;
}
.sme-ops-feature--quadrant {
  display: grid;
  grid-template-columns: minmax(245px, 0.28fr) minmax(0, 0.72fr);
  gap: 0;
  padding: 0;
  overflow: hidden;
  border-color: #9ebce0;
}
.sme-ops-rm-visual {
  position: relative;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 390px;
  padding: 1rem 1rem 0;
  overflow: hidden;
  color: #ffffff;
  background: var(--sme-nusantara-dark);
}
.sme-ops-rm-visual::after {
  content: "";
  position: absolute;
  right: -72px;
  bottom: 72px;
  width: 210px;
  height: 84px;
  transform: rotate(-28deg);
  background: var(--sme-cakrawala);
  opacity: 0.22;
  pointer-events: none;
}
.sme-ops-section-heading--light {
  position: relative;
  z-index: 2;
  align-items: flex-start;
}
.sme-ops-section-heading--light .sme-ops-section-number {
  color: #063476;
  background: #ffffff;
}
.sme-ops-section-heading--light h3,
.sme-ops-section-heading--light p {
  color: #ffffff;
}
.sme-ops-section-heading--light .sme-ops-kicker {
  color: #8fe2ff;
}
.sme-ops-rm-illustration {
  position: relative;
  z-index: 1;
  display: block;
  align-self: center;
  width: min(100%, 245px);
  height: auto;
  margin: auto auto 0;
  object-fit: contain;
  object-position: center bottom;
}
.sme-ops-rm-total {
  position: relative;
  z-index: 2;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: 0.1rem 0.6rem;
  padding: 0.7rem;
  margin: -0.2rem 0 0.8rem;
  color: #ffffff;
  background: rgba(3, 35, 80, 0.86);
  border: 1px solid rgba(255, 255, 255, 0.26);
  border-radius: 6px;
}
.sme-ops-rm-total > span {
  font-size: 0.63rem;
  font-weight: 700;
}
.sme-ops-rm-total > strong {
  grid-row: 1 / 3;
  grid-column: 2;
  font-size: 1.4rem;
  line-height: 1;
}
.sme-ops-rm-total > small {
  color: #a9dcff;
  font-size: 0.56rem;
}
.sme-ops-rm-total > small i {
  margin-right: 0.28rem;
}
.sme-ops-rm-content {
  min-width: 0;
  padding: 1rem;
  background: #ffffff;
}
.sme-ops .sme-ops-quadrant-summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.6rem;
  margin-bottom: 0.75rem;
}
.sme-ops .sme-ops-q-summary {
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr);
  align-items: center;
  gap: 0.52rem;
  min-width: 0;
  min-height: 88px;
  padding: 0.65rem;
  overflow: hidden;
  color: #ffffff;
  border: 0;
  border-radius: 7px;
  box-shadow: none;
}
.sme-ops .sme-ops-q-summary::after {
  display: none;
}
.sme-ops .sme-ops-q-summary.q1 { background: #008965; }
.sme-ops .sme-ops-q-summary.q2 { background: #0674d8; }
.sme-ops .sme-ops-q-summary.q3 { background: #df7900; }
.sme-ops .sme-ops-q-summary.q4 { background: #d7324a; }
.sme-ops-q-summary__icon {
  display: inline-flex !important;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  color: #ffffff !important;
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.26);
  border-radius: 50%;
}
.sme-ops-q-summary > div {
  min-width: 0;
}
.sme-ops-q-summary > div > span,
.sme-ops-q-summary > div > strong,
.sme-ops-q-summary > div > small {
  display: block;
  color: #ffffff;
}
.sme-ops-q-summary > div > span {
  font-size: 0.65rem;
  font-weight: 800;
}
.sme-ops-q-summary > div > strong {
  margin: 0.08rem 0;
  font-size: 1.25rem;
  line-height: 1;
}
.sme-ops-q-summary > div > small {
  color: rgba(255, 255, 255, 0.82);
  font-size: 0.55rem;
}
.sme-ops .sme-ops-branch-list,
.sme-ops .sme-ops-rm-list {
  display: grid;
  gap: 0.48rem;
}
.sme-ops .sme-ops-branch-row {
  display: grid;
  grid-template-columns: minmax(170px, 0.7fr) minmax(0, 1.5fr);
  align-items: center;
  gap: 0.65rem;
  min-width: 0;
  padding: 0.55rem;
  background: #f5f9fe;
  border: 1px solid #d2dfed;
  border-left: 4px solid var(--sme-nusantara);
  border-radius: 6px;
}
.sme-ops .sme-ops-branch-row__icon {
  color: #ffffff;
  background: var(--sme-nusantara);
  border-radius: 50%;
}
.sme-ops .sme-ops-branch-row__quadrants {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.35rem;
}
.sme-ops .sme-ops-branch-q {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.15rem 0.4rem;
  min-width: 0;
  padding: 0.45rem 0.52rem;
  background: #ffffff;
  border: 1px solid #d4dfec;
  border-top: 3px solid #8798ac;
  border-radius: 6px;
}
.sme-ops .sme-ops-branch-q > span {
  flex: 1 1 100%;
  color: #4c617a;
  font-size: 0.62rem;
  font-weight: 850;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: -0.01em;
}
.sme-ops .sme-ops-branch-q > strong {
  color: var(--sme-ink);
  font-size: 0.92rem;
  font-weight: 850;
  font-variant-numeric: tabular-nums;
  line-height: 1.1;
}
.sme-ops .sme-ops-branch-q > small {
  color: #667991;
  font-size: 0.58rem;
  font-weight: 750;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.sme-ops .sme-ops-branch-q.q1 { border-top-color: #008965; }
.sme-ops .sme-ops-branch-q.q2 { border-top-color: #0674d8; }
.sme-ops .sme-ops-branch-q.q3 { border-top-color: #df7900; }
.sme-ops .sme-ops-branch-q.q4 { border-top-color: #d7324a; }
.sme-ops .sme-ops-rm-list {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.sme-ops .sme-ops-rm-row {
  grid-template-columns: 28px minmax(0, 1fr) auto;
  min-height: 52px;
  border-left: 3px solid #13a7e2;
}
.sme-ops .sme-ops-q-badge {
  min-width: 78px;
  min-height: 28px;
  padding: 0.3rem 0.46rem;
  border-radius: 5px;
  font-size: 0.58rem;
  white-space: nowrap;
}
.sme-ops-feature--hot {
  border-top: 5px solid var(--sme-amber);
}
.sme-ops-feature--hot .sme-ops-feature-icon {
  color: #ffffff;
  background: var(--sme-amber);
  border-color: var(--sme-amber);
}
.sme-ops-status-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
  gap: 0.65rem;
}
.sme-ops .sme-ops-status-item {
  position: relative;
  min-width: 0;
  min-height: 126px;
  padding: 0.7rem;
  overflow: hidden;
  background: #ffffff;
  border: 1px solid #cbd8e7;
  border-top: 5px solid var(--status-color, var(--sme-nusantara));
  border-radius: 7px;
  box-shadow: 0 12px 22px -22px rgba(16, 34, 63, 0.9);
}
.sme-ops .sme-ops-status-item::after {
  content: "";
  position: absolute;
  right: -26px;
  bottom: -31px;
  width: 82px;
  height: 58px;
  transform: rotate(-28deg);
  background: var(--status-color, var(--sme-nusantara));
  opacity: 0.08;
  pointer-events: none;
}
.sme-ops-status-item[data-status="belum_ots"] { --status-color: #e38000; }
.sme-ops-status-item[data-status="analisa_rm"] { --status-color: #0754bd; }
.sme-ops-status-item[data-status="verifikasi_adk"] { --status-color: #008fbd; }
.sme-ops-status-item[data-status="menunggu_putusan"] { --status-color: #c95f00; }
.sme-ops-status-item[data-status="sudah_diputus"] { --status-color: #694bc3; }
.sme-ops-status-item[data-status="realisasi"] { --status-color: #009b72; }
.sme-ops-status-item[data-status="batal"] { --status-color: #d62f49; }
.sme-ops .sme-ops-status-item__head {
  align-items: center;
  min-height: 42px;
  margin-bottom: 0.58rem;
}
.sme-ops .sme-ops-status-item__icon {
  flex: 0 0 34px;
  width: 34px;
  height: 34px;
  color: #ffffff;
  background: var(--status-color, var(--sme-nusantara));
  border-radius: 50%;
}
.sme-ops .sme-ops-status-item h4 {
  color: #263d5b;
  font-size: 0.68rem;
  font-weight: 800;
  line-height: 1.25;
}
.sme-ops-data-pair {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  min-width: 0;
}
.sme-ops-data-pair > div {
  min-width: 0;
  padding-right: 0.55rem;
}
.sme-ops-data-pair > div + div {
  padding-right: 0;
  padding-left: 0.55rem;
  border-left: 1px solid #cbd8e7;
}
.sme-ops-data-pair span,
.sme-ops-data-pair strong {
  display: block;
}
.sme-ops-data-pair span {
  color: #6a7c93;
  font-size: 0.56rem;
  font-weight: 700;
}
.sme-ops-data-pair strong {
  margin-top: 0.12rem;
  overflow-wrap: anywhere;
  color: var(--sme-ink);
  font-size: 0.85rem;
  line-height: 1.2;
}
.sme-ops-feature--rtl {
  border-top: 5px solid var(--sme-cakrawala);
}
.sme-ops-feature--rtl .sme-ops-feature-icon {
  color: #ffffff;
  background: var(--sme-cakrawala);
  border-color: var(--sme-cakrawala);
}
.sme-ops-rtl-totals {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin-bottom: 0.75rem;
  overflow: hidden;
  color: #ffffff;
  background: var(--sme-nusantara-dark);
  border: 1px solid #052a60;
  border-radius: 7px;
}
.sme-ops-rtl-totals > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.7rem;
  min-width: 0;
  min-height: 54px;
  padding: 0.65rem 0.8rem;
}
.sme-ops-rtl-totals > div + div {
  border-left: 1px solid rgba(255, 255, 255, 0.2);
}
.sme-ops-rtl-totals span {
  color: #bcd9f9;
  font-size: 0.62rem;
  font-weight: 750;
}
.sme-ops-rtl-totals strong {
  font-size: 1.08rem;
}
.sme-ops .sme-ops-vendor-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem;
}
.sme-ops .sme-ops-vendor-item {
  display: grid;
  grid-template-columns: minmax(120px, 0.9fr) minmax(0, 1.1fr);
  align-items: center;
  gap: 0.6rem;
  min-width: 0;
  min-height: 64px;
  padding: 0.48rem 0.58rem;
  background: #ffffff;
  border: 1px solid #cbd8e7;
  border-left: 4px solid var(--vendor-color, var(--sme-nusantara));
  border-radius: 7px;
}
.sme-ops .sme-ops-vendor-item.tone-1 { --vendor-color: #0754bd; }
.sme-ops .sme-ops-vendor-item.tone-2 { --vendor-color: #008fbd; }
.sme-ops .sme-ops-vendor-item.tone-3 { --vendor-color: #e38000; }
.sme-ops .sme-ops-vendor-item.tone-4 { --vendor-color: #009b72; }
.sme-ops-vendor-item__identity {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}
.sme-ops .sme-ops-vendor-item__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 36px;
  width: 36px;
  height: 36px;
  color: #ffffff;
  background: var(--vendor-color, var(--sme-nusantara));
  border-radius: 8px;
  font-size: 0.95rem;
}
.sme-ops .sme-ops-vendor-item h4 {
  min-width: 0;
  color: #203a5c;
  font-size: 0.66rem;
  font-weight: 850;
  line-height: 1.28;
}
.sme-ops-vendor-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  min-width: 0;
  border-left: 1px solid #d5e0ec;
}
.sme-ops-vendor-metrics > div {
  min-width: 0;
  padding: 0 0.45rem;
}
.sme-ops-vendor-metrics > div:first-child {
  padding-left: 0;
}
.sme-ops-vendor-metrics > div:last-child {
  padding-right: 0;
}
.sme-ops-vendor-metrics > div + div {
  border-left: 1px solid #d5e0ec;
}
.sme-ops-vendor-metrics span,
.sme-ops-vendor-metrics strong {
  display: block;
}
.sme-ops-vendor-metrics span {
  color: #667991;
  font-size: 0.5rem;
  font-weight: 700;
  line-height: 1.25;
}
.sme-ops-vendor-metrics strong {
  margin-top: 0.08rem;
  color: var(--vendor-color, var(--sme-nusantara-dark));
  font-size: 0.86rem;
}
.sme-ops .sme-ops-two-column {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}
.sme-ops-feature--extension {
  border-top: 5px solid var(--sme-green);
}
.sme-ops-feature--extension .sme-ops-feature-icon {
  color: #ffffff;
  background: var(--sme-green);
  border-color: var(--sme-green);
}
.sme-ops-extension-layout {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 0.8rem;
  min-height: 116px;
  padding: 0.75rem;
  margin-bottom: 0.65rem;
  color: #ffffff;
  background: var(--sme-nusantara-dark);
  border-radius: 7px;
}
.sme-ops-attendance-ring {
  display: grid;
  place-items: center;
  width: 90px;
  height: 90px;
  padding: 8px;
  background: conic-gradient(#29d3a2 var(--attendance), rgba(255, 255, 255, 0.18) 0);
  border-radius: 50%;
}
.sme-ops-attendance-ring > div {
  display: grid;
  place-items: center;
  width: 100%;
  height: 100%;
  color: #ffffff;
  background: var(--sme-nusantara-dark);
  border-radius: 50%;
}
.sme-ops-attendance-ring strong,
.sme-ops-attendance-ring span {
  display: block;
  text-align: center;
}
.sme-ops-attendance-ring strong {
  align-self: end;
  font-size: 0.9rem;
}
.sme-ops-attendance-ring span {
  align-self: start;
  color: #bcd9f9;
  font-size: 0.52rem;
}
.sme-ops-attendance-copy span,
.sme-ops-attendance-copy strong,
.sme-ops-attendance-copy small {
  display: block;
}
.sme-ops-attendance-copy span {
  color: #9fdcff;
  font-size: 0.62rem;
  font-weight: 750;
}
.sme-ops-attendance-copy strong {
  margin-top: 0.15rem;
  font-size: 1.05rem;
}
.sme-ops-attendance-copy small {
  margin-top: 0.15rem;
  color: #c9dcf2;
  font-size: 0.58rem;
}
.sme-ops-process-list {
  display: grid;
}
.sme-ops-process-row {
  display: grid;
  grid-template-columns: 30px minmax(105px, 1fr) minmax(180px, auto);
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
  min-height: 48px;
  padding: 0.44rem 0;
  border-bottom: 1px solid #d9e3ee;
}
.sme-ops-process-row:last-child {
  border-bottom: 0;
}
.sme-ops-process-row__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  color: var(--sme-green);
  background: #e5f8f2;
  border-radius: 50%;
}
.sme-ops-process-row__label {
  min-width: 0;
  color: #334b68;
  font-size: 0.64rem;
  font-weight: 750;
  overflow-wrap: anywhere;
}
.sme-ops-process-row__metrics {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  min-width: 0;
}
.sme-ops-process-row__metrics > span {
  min-width: 0;
  padding: 0 0.5rem;
  color: #697c93;
  font-size: 0.54rem;
}
.sme-ops-process-row__metrics > span + span {
  border-left: 1px solid #cbd8e7;
}
.sme-ops-process-row__metrics strong {
  display: block;
  overflow-wrap: anywhere;
  color: var(--sme-ink);
  font-size: 0.72rem;
}
.sme-ops-feature--restructuring {
  border-top: 5px solid var(--sme-red);
}
.sme-ops-feature--restructuring .sme-ops-feature-icon {
  color: #ffffff;
  background: var(--sme-red);
  border-color: var(--sme-red);
}
.sme-ops-restruct-timeline {
  position: relative;
  display: grid;
  gap: 0.55rem;
  min-width: 0;
}
.sme-ops-restruct-timeline::before {
  content: "";
  position: absolute;
  top: 22px;
  bottom: 22px;
  left: 19px;
  width: 2px;
  background: #b9cce2;
}
.sme-ops-restruct-step {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr);
  align-items: center;
  gap: 0.55rem;
  min-width: 0;
}
.sme-ops-restruct-step__number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  color: #ffffff;
  background: var(--sme-red);
  border: 4px solid #ffffff;
  border-radius: 50%;
  box-shadow: 0 0 0 1px #c8d7e7;
  font-size: 0.58rem;
  font-weight: 850;
}
.sme-ops-restruct-step__body {
  display: grid;
  grid-template-columns: minmax(130px, 1fr) minmax(170px, 0.8fr);
  align-items: center;
  gap: 0.6rem;
  min-width: 0;
  padding: 0.58rem 0.65rem;
  background: #f7faff;
  border: 1px solid #d5e0ec;
  border-left: 4px solid var(--sme-red);
  border-radius: 6px;
}
.sme-ops-restruct-step h4 {
  color: #304967;
  font-size: 0.65rem;
  font-weight: 800;
}
.sme-ops-restruct-step small {
  display: block;
  margin-top: 0.12rem;
  color: #6c7f96;
  font-size: 0.54rem;
}
.sme-ops .sme-ops-footnote {
  margin: 0.65rem 0 0;
  padding: 0.5rem 0.6rem;
  color: #536a84;
  background: #eef5fd;
  border-left: 3px solid var(--sme-nusantara);
  border-radius: 4px;
  font-size: 0.57rem;
}
.sme-ops-restruct-breakdown-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 0.7rem;
  margin-top: 0.75rem;
  padding: 0.55rem 0.6rem;
  color: #ffffff;
  background: var(--sme-nusantara-dark);
  border-radius: 6px 6px 0 0;
}
.sme-ops-restruct-breakdown-head span,
.sme-ops-restruct-breakdown-head strong {
  display: block;
}
.sme-ops-restruct-breakdown-head span {
  color: #a9d8ff;
  font-size: 0.55rem;
  font-weight: 800;
  text-transform: uppercase;
}
.sme-ops-restruct-breakdown-head strong {
  margin-top: 0.12rem;
  font-size: 0.7rem;
}
.sme-ops-restruct-breakdown-head small {
  color: #d9ebff;
  font-size: 0.54rem;
  font-weight: 700;
  text-align: right;
}
.sme-ops-restruct-breakdown-head small i {
  margin-right: 0.25rem;
}
.sme-ops-restruct-groups {
  display: grid;
  max-height: 420px;
  overflow-y: auto;
  overscroll-behavior: contain;
  border: 1px solid #cbd8e7;
  border-top: 0;
  border-radius: 0 0 6px 6px;
  scrollbar-width: thin;
}
.sme-ops-restruct-group {
  display: grid;
  grid-template-columns: minmax(145px, 0.75fr) minmax(320px, 1.5fr);
  align-items: center;
  min-width: 0;
  background: #ffffff;
}
.sme-ops-restruct-group + .sme-ops-restruct-group {
  border-top: 1px solid #dbe5f0;
}
.sme-ops-restruct-group__identity {
  display: flex;
  align-items: center;
  gap: 0.48rem;
  min-width: 0;
  padding: 0.55rem 0.6rem;
}
.sme-ops-restruct-group__identity > span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 32px;
  width: 32px;
  height: 32px;
  color: #ffffff;
  background: #0754bd;
  border-radius: 6px;
}
.sme-ops-restruct-group__identity > div {
  min-width: 0;
}
.sme-ops-restruct-group__identity strong,
.sme-ops-restruct-group__identity small {
  display: block;
  overflow-wrap: anywhere;
}
.sme-ops-restruct-group__identity strong {
  color: #203a5c;
  font-size: 0.63rem;
  font-weight: 850;
}
.sme-ops-restruct-group__identity small {
  margin-top: 0.12rem;
  color: #6a7d93;
  font-size: 0.53rem;
}
.sme-ops-restruct-group__statuses {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  min-width: 0;
  border-left: 1px solid #dbe5f0;
}
.sme-ops-restruct-group__statuses > div {
  display: grid;
  align-content: center;
  min-width: 0;
  min-height: 52px;
  padding: 0.38rem 0.45rem;
}
.sme-ops-restruct-group__statuses > div + div {
  border-left: 1px solid #e3ebf4;
}
.sme-ops-restruct-group__statuses span,
.sme-ops-restruct-group__statuses strong,
.sme-ops-restruct-group__statuses small {
  display: block;
  max-width: 100%;
  overflow-wrap: anywhere;
}
.sme-ops-restruct-group__statuses span {
  color: #687c94;
  font-size: 0.48rem;
  font-weight: 750;
}
.sme-ops-restruct-group__statuses strong {
  margin-top: 0.08rem;
  color: #d82d4c;
  font-size: 0.78rem;
}
.sme-ops-restruct-group__statuses small {
  margin-top: 0.06rem;
  color: #60758d;
  font-size: 0.48rem;
  font-variant-numeric: tabular-nums;
}
.sme-ops .sme-ops-cache-note {
  border-left: 4px solid var(--sme-amber);
}
@media (max-width: 1399.98px) {
  .sme-ops .sme-ops-quadrant-summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .sme-ops .sme-ops-vendor-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 1279.98px) {
  .sme-ops-feature--quadrant {
    grid-template-columns: minmax(0, 1fr);
  }
  .sme-ops-rm-visual {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(140px, 190px);
    min-height: 200px;
    padding: 1rem 1.2rem 0.8rem;
  }
  .sme-ops-rm-illustration {
    grid-column: 2;
    grid-row: 1 / 3;
    align-self: end;
    width: min(100%, 180px);
    max-height: 200px;
  }
  .sme-ops-rm-total {
    align-self: end;
    margin: 0.4rem 0 0;
  }
  .sme-ops .sme-ops-quadrant-summary {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
  .sme-ops .sme-ops-branch-row {
    grid-template-columns: minmax(180px, 0.7fr) minmax(0, 1.5fr);
  }
  .sme-ops .sme-ops-branch-row__quadrants {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
  .sme-ops .sme-ops-two-column {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 899.98px) {
  .sme-ops-intro {
    grid-template-columns: auto minmax(0, 1fr);
  }
  .sme-ops-intro__actions {
    grid-column: 1 / -1;
    justify-content: flex-start;
    padding-left: 62px;
  }
  .sme-ops .sme-ops-quadrant-summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .sme-ops .sme-ops-branch-row {
    grid-template-columns: 1fr;
  }
  .sme-ops .sme-ops-rm-list {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 767.98px) {
  .sme-ops {
    gap: 0.75rem;
    padding: 0.7rem;
  }
  .sme-ops-intro,
  .sme-ops-feature {
    border-radius: 7px;
  }
  .sme-ops-feature {
    padding: 0.8rem;
  }
  .sme-ops-feature-head {
    flex-direction: column;
    gap: 0.65rem;
  }
  .sme-ops .sme-ops-source:not(.sme-ops-source--icon) {
    width: 100%;
  }
  .sme-ops .sme-ops-branch-row__quadrants {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .sme-ops-status-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .sme-ops .sme-ops-vendor-grid {
    grid-template-columns: 1fr;
  }
  .sme-ops-restruct-group {
    grid-template-columns: 1fr;
  }
  .sme-ops-restruct-group__statuses {
    border-top: 1px solid #dbe5f0;
    border-left: 0;
  }
  .sme-ops-process-row {
    grid-template-columns: 30px minmax(0, 1fr);
  }
  .sme-ops-process-row__metrics {
    grid-column: 2;
  }
}
@media (max-width: 575.98px) {
  .sme-ops-intro {
    grid-template-columns: 42px minmax(0, 1fr);
    padding: 0.8rem;
  }
  .sme-ops-intro__mark {
    width: 42px;
    height: 42px;
    font-size: 1rem;
  }
  .sme-ops-intro__copy h2 {
    font-size: 0.96rem;
  }
  .sme-ops-intro__actions {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 40px;
    width: 100%;
    padding-left: 0;
  }
  .sme-ops-live-label {
    min-width: 0;
    white-space: normal;
  }
  .sme-ops .sme-ops-refresh {
    width: 40px;
    padding: 0;
  }
  .sme-ops .sme-ops-refresh span {
    display: none;
  }
  .sme-ops-section-heading {
    display: grid;
    grid-template-columns: 34px 34px minmax(0, 1fr);
    gap: 0.45rem;
    width: 100%;
  }
  .sme-ops-section-heading--light {
    grid-template-columns: 34px minmax(0, 1fr);
  }
  .sme-ops-section-number,
  .sme-ops-feature-icon {
    width: 34px;
    height: 34px;
    flex-basis: 34px;
  }
  .sme-ops-rm-visual {
    grid-template-columns: minmax(0, 1fr) 126px;
    min-height: 220px;
    padding: 0.8rem;
  }
  .sme-ops-rm-illustration {
    width: 126px;
    max-height: 188px;
  }
  .sme-ops-rm-total {
    grid-template-columns: minmax(0, 1fr) auto;
    padding: 0.55rem;
  }
  .sme-ops-rm-content {
    padding: 0.75rem;
  }
  .sme-ops .sme-ops-quadrant-summary,
  .sme-ops-status-grid,
  .sme-ops .sme-ops-vendor-grid {
    grid-template-columns: 1fr;
  }
  .sme-ops .sme-ops-q-summary {
    min-height: 76px;
  }
  .sme-ops .sme-ops-branch-row__quadrants {
    grid-template-columns: 1fr;
  }
  .sme-ops .sme-ops-branch-q {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    align-items: center;
  }
  .sme-ops .sme-ops-branch-q > span {
    flex-basis: auto;
  }
  .sme-ops-rtl-totals {
    grid-template-columns: 1fr;
  }
  .sme-ops-rtl-totals > div + div {
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    border-left: 0;
  }
  .sme-ops-vendor-metrics span {
    min-height: 0;
  }
  .sme-ops .sme-ops-vendor-item {
    grid-template-columns: minmax(80px, 0.75fr) minmax(0, 1.25fr);
    gap: 0.25rem;
    min-height: 58px;
    padding: 0.42rem 0.38rem;
  }
  .sme-ops-vendor-item__identity {
    gap: 0.3rem;
  }
  .sme-ops .sme-ops-vendor-item__icon {
    flex-basis: 28px;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    font-size: 0.78rem;
  }
  .sme-ops .sme-ops-vendor-item h4 {
    overflow-wrap: anywhere;
    font-size: 0.58rem;
  }
  .sme-ops-vendor-metrics > div {
    padding: 0 0.14rem;
  }
  .sme-ops-vendor-metrics span {
    font-size: 0.44rem;
  }
  .sme-ops-vendor-metrics strong {
    font-size: 0.74rem;
  }
  .sme-ops-restruct-breakdown-head {
    align-items: flex-start;
    flex-direction: column;
  }
  .sme-ops-restruct-breakdown-head small {
    text-align: left;
  }
  .sme-ops-restruct-group__statuses {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .sme-ops-restruct-group__statuses > div:nth-child(3) {
    border-left: 0;
  }
  .sme-ops-restruct-group__statuses > div:nth-child(n + 3) {
    border-top: 1px solid #e3ebf4;
  }
  .sme-ops-extension-layout {
    grid-template-columns: 80px minmax(0, 1fr);
  }
  .sme-ops-attendance-ring {
    width: 76px;
    height: 76px;
  }
  .sme-ops-restruct-step__body {
    grid-template-columns: 1fr;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sme-ops .sme-ops-refresh {
    transition: none;
  }
  .sme-ops .sme-ops-refresh:hover {
    transform: none;
  }
}
/* Landing branch control and Micro Performance Desk. */
.db-branch-picker {
  position: relative;
  display: inline-flex;
  align-items: center;
  min-width: 190px;
}
.db-branch-picker select {
  width: 100%;
  min-height: 42px;
  padding: 0.55rem 2.25rem 0.55rem 2.25rem;
  border: 1px solid #b9cbe2;
  border-radius: 10px;
  color: #0b3f88;
  background: #fff;
  font: 700 0.72rem/1.2 'Inter', sans-serif;
  appearance: none;
  cursor: pointer;
}
.db-branch-picker select:disabled { cursor: not-allowed; color: #52647b; background: #edf2f8; }
.db-branch-picker > i { position: absolute; left: 0.8rem; color: #1263bd; pointer-events: none; }
.db-branch-picker::after { content: '\f078'; position: absolute; right: 0.8rem; color: #60748c; font: 900 0.68rem/1 'Font Awesome 5 Free'; pointer-events: none; }
.db-branch-picker:focus-within select { outline: 3px solid rgba(19, 120, 211, 0.2); border-color: #1378d3; }

.landing-scope-stage {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  width: auto;
  max-width: 100%;
}
.area6-head > div:first-child { flex: 1 1 auto; min-width: 0; max-width: 520px; }
.area6-head .area6-head-actions { flex: 0 0 auto; margin-left: auto; min-width: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 0.55rem; }
.area6-head .area6-periods { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 0.45rem; width: 100%; }
.db-shell .landing-scope-stage .area6-scope-toggle {
  display: inline-grid !important;
  grid-template-columns: repeat(4, minmax(122px, auto));
  width: auto !important;
  overflow: visible !important;
  border-radius: 12px !important;
}
.landing-scope-stage .area6-scope-btn { min-width: 0; width: 100%; text-align: left; }
.landing-scope-visual {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 82px;
  padding: 0.35rem 0.75rem;
  min-height: 74px;
  overflow: hidden;
  border: 1px solid rgba(102, 150, 210, 0.32);
  border-radius: 16px;
  background: linear-gradient(120deg, rgba(7, 84, 189, 0.08), rgba(19, 167, 226, 0.13));
}
.landing-scope-visual__copy { position: relative; z-index: 2; max-width: 220px; padding: 0.8rem 0 0.8rem 1rem; }
.landing-scope-visual__copy span { display: block; color: #0b4e9d; font-size: 0.62rem; font-weight: 800; letter-spacing: 0.09em; text-transform: uppercase; }
.landing-scope-visual__copy strong { display: block; margin-top: 0.2rem; color: #10213a; font-size: 0.78rem; line-height: 1.35; }
.landing-scope-visual svg { width: 190px; height: 82px; flex: 0 0 190px; }

.db-shell.micro-performance-active .area6-ranking-mode,
.db-shell.micro-performance-active .landing-summary,
.db-shell.micro-performance-active .main-grid,
.db-shell.consumer-active .area6-ranking-mode,
.db-shell.consumer-active .landing-summary,
.db-shell.consumer-active .main-grid { display: none !important; }
.micro-performance-dashboard { display: none; min-height: 360px; margin-top: 1rem; }
.db-shell.micro-performance-active .micro-performance-dashboard:not(.d-none) { display: block; }
.consumer-operations-dashboard { display: none; min-height: 360px; margin-top: 1rem; }
.db-shell.consumer-active .consumer-operations-dashboard:not(.d-none) { display: block; }
.micro-ops {
  --micro-nusantara: #0754bd;
  --micro-deep: #063476;
  --micro-cakrawala: #13a7e2;
  --micro-ink: #10213a;
  --micro-muted: #5d6e84;
  --micro-line: #ccd9e9;
  display: grid;
  gap: 1rem;
  padding: 1rem;
  color: var(--micro-ink);
  background: #edf4fc;
  border: 1px solid #c8d8eb;
  border-radius: 20px;
}
.micro-ops-hero {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  overflow: hidden;
  padding: 1.15rem 1.25rem;
  color: #fff;
  background:
    radial-gradient(circle at 88% 20%, rgba(83, 207, 255, 0.35), transparent 24%),
    linear-gradient(125deg, #052d68, #0754bd 58%, #087fc8);
  border-radius: 16px;
  box-shadow: 0 18px 36px -28px rgba(2, 38, 92, 0.9);
}
.micro-ops-hero::after { content: ''; position: absolute; right: -42px; bottom: -86px; width: 220px; height: 220px; border: 28px solid rgba(255,255,255,0.08); border-radius: 50%; }
.micro-ops-hero__identity { position: relative; z-index: 1; display: flex; align-items: center; gap: 0.85rem; min-width: 0; }
.micro-ops-hero__icon { display: grid; flex: 0 0 50px; width: 50px; height: 50px; place-items: center; color: #053879; background: #fff; border-radius: 14px; font-size: 1.25rem; }
.micro-ops-eyebrow { display: block; color: #1870c8; font-size: 0.62rem; font-weight: 900; letter-spacing: 0.11em; text-transform: uppercase; }
.micro-ops-hero .micro-ops-eyebrow { color: #9ee4ff; }
.micro-ops-hero h2 { margin: 0.15rem 0 0; color: #fff; font-size: clamp(1.12rem, 1.55vw, 1.45rem); font-weight: 850; letter-spacing: -0.025em; }
.micro-ops-hero p { margin: 0.22rem 0 0; color: #d8ebff; font-size: 0.72rem; line-height: 1.5; }
.micro-ops-hero__meta { position: relative; z-index: 1; display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 0.45rem; }
.micro-ops-hero__meta > span,
.micro-ops-refresh { display: inline-flex; align-items: center; gap: 0.38rem; min-height: 38px; padding: 0.45rem 0.7rem; color: #fff; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25); border-radius: 9px; font-size: 0.66rem; font-weight: 750; }
.micro-ops-refresh { cursor: pointer; }
.micro-ops-refresh:hover { background: #fff; color: #0754bd; }
.micro-ops-refresh:focus-visible { outline: 3px solid rgba(255,255,255,0.38); outline-offset: 2px; }
.micro-ops-refresh.is-loading i { animation: microSpin 0.8s linear infinite; }
@keyframes microSpin { to { transform: rotate(360deg); } }

.micro-ops-section {
  overflow: hidden;
  background: #fff;
  border: 1px solid var(--micro-line);
  border-radius: 15px;
  box-shadow: 0 14px 34px -30px rgba(3, 44, 100, 0.9);
}
.micro-ops-section__head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.1rem; border-bottom: 1px solid #d8e3ef; background: linear-gradient(110deg, #f7fbff, #fff); }
.micro-ops-section__head > div:first-child { position: relative; padding-left: 2.65rem; }
.micro-ops-section__number { position: absolute; top: 0; left: 0; display: grid; width: 34px; height: 34px; place-items: center; color: #fff; background: linear-gradient(135deg, var(--micro-nusantara), var(--micro-cakrawala)); border-radius: 10px; font-size: 0.68rem; font-weight: 900; }
.micro-ops-section h3 { margin: 0.14rem 0 0; color: var(--micro-ink); font-size: clamp(0.98rem, 1.25vw, 1.18rem); font-weight: 850; letter-spacing: -0.02em; }
.micro-ops-section__total { color: #0754bd; font-size: 1.2rem; font-weight: 900; font-variant-numeric: tabular-nums; }
.micro-ops-section__total-group { display: flex; flex-direction: column; align-items: flex-end; }
.micro-ops-section__total-group strong { color: #0754bd; font-size: 1.2rem; font-weight: 900; font-variant-numeric: tabular-nums; }
.micro-ops-section__total-group span { color: var(--micro-muted); font-size: 0.66rem; font-weight: 700; }
.micro-ops-source-chip,
.micro-ops-comparison { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.38rem 0.6rem; color: #0a579f; background: #e8f4ff; border: 1px solid #bfdbf5; border-radius: 8px; font-size: 0.64rem; font-weight: 800; }

.micro-product-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.7rem; padding: 0.9rem; }
.micro-product-card { min-width: 0; padding: 0.82rem; background: linear-gradient(145deg, #fff, #f4f9ff); border: 1px solid #d2dfed; border-top: 3px solid #0c67c9; border-radius: 11px; }
.micro-product-card__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem; }
.micro-product-card__head span { color: #263b56; font-size: 0.7rem; font-weight: 800; overflow-wrap: anywhere; }
.micro-product-card__head strong { flex: 0 0 auto; color: #0878bf; font-size: 0.66rem; }
.micro-product-card__value { margin-top: 0.55rem; color: #0b376f; font-size: 1rem; font-weight: 900; font-variant-numeric: tabular-nums; }
.micro-product-card__bar { height: 5px; margin: 0.55rem 0 0.4rem; overflow: hidden; background: #dbe7f3; border-radius: 99px; }
.micro-product-card__bar span { display: block; height: 100%; background: linear-gradient(90deg, #0754bd, #13a7e2); border-radius: inherit; }
.micro-product-card small { color: var(--micro-muted); font-size: 0.62rem; font-weight: 650; }

.micro-realization-type-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; padding: 0.9rem 0.9rem 0; }
.micro-realization-type-card { display: flex; align-items: center; gap: 0.8rem; padding: 0.9rem; border: 1px solid #cddced; border-radius: 12px; background: #f8fbff; }
.micro-realization-type-card.type-baru { border-left: 4px solid #0754bd; }
.micro-realization-type-card.type-suplesi { border-left: 4px solid #00a67a; }
.micro-realization-type-card__icon { display: grid; flex: 0 0 40px; width: 40px; height: 40px; place-items: center; color: #0754bd; background: #e5f1ff; border-radius: 11px; }
.type-suplesi .micro-realization-type-card__icon { color: #007d5c; background: #dcf7ef; }
.micro-realization-type-card span { color: var(--micro-muted); font-size: 0.65rem; font-weight: 750; }
.micro-realization-type-card strong { display: block; margin-top: 0.08rem; color: var(--micro-ink); font-size: 1.05rem; font-weight: 900; }
.micro-realization-type-card small { display: block; margin-top: 0.14rem; color: #60758d; font-size: 0.62rem; }

.micro-ops-subhead { display: flex; align-items: flex-end; justify-content: space-between; gap: 0.8rem; margin: 0.9rem 0 0.45rem; }
.micro-ops-subhead span { display: block; color: #1372c8; font-size: 0.55rem; font-weight: 900; letter-spacing: 0.09em; }
.micro-ops-subhead h4 { margin: 0.12rem 0 0; color: #16385f; font-size: 0.88rem; font-weight: 900; }
.micro-ops-subhead small { color: #657b94; font-size: 0.62rem; font-weight: 700; text-align: right; }
.micro-ops-table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.micro-ops-table { width: 100%; min-width: 620px; border-collapse: separate; border-spacing: 0; color: #24364f; font-size: 0.68rem; }
.micro-ops-table th,
.micro-ops-table td { padding: 0.68rem 0.75rem; border-bottom: 1px solid #dde6f0; vertical-align: middle; }
.micro-ops-table thead th { position: sticky; top: 0; z-index: 1; color: #fff; background: #0b438e; font-size: 0.61rem; font-weight: 850; letter-spacing: 0.04em; text-transform: uppercase; }
.micro-ops-table tbody th { min-width: 150px; color: #183453; font-weight: 800; text-align: left; }
.micro-ops-table tbody tr:nth-child(even) { background: #f7faff; }
.micro-ops-table tbody tr:hover { background: #edf6ff; }
.micro-ops-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.micro-ops-table .strong { color: #0754bd; font-weight: 850; }
.micro-ops-table .compact { font-size: 0.61rem; }
.micro-ops-table .override { color: #b34b00; background: rgba(255, 238, 214, 0.48); }
.micro-ops-table .empty { padding: 1.2rem; color: #6b7b90; text-align: center; }
.micro-share-badge { display: inline-flex; padding: 0.22rem 0.42rem; color: #0754bd; background: #e5f1ff; border-radius: 6px; font-weight: 800; }

.micro-decision-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.8rem; padding: 0.9rem; }
.micro-decision-card { min-width: 0; overflow: hidden; border: 1px solid #cad9ea; border-radius: 12px; background: #fff; }
.micro-decision-card__head { display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; padding: 0.8rem 0.9rem; color: #fff; background: linear-gradient(120deg, #063b82, #0873c9); }
.micro-decision-card.role-sboh .micro-decision-card__head { background: linear-gradient(120deg, #07567c, #0b93b5); }
.micro-decision-card.role-mbm .micro-decision-card__head { background: linear-gradient(120deg, #5a3d9e, #7f60c7); }
.micro-decision-card.role-ka_unit .micro-decision-card__head { background: linear-gradient(120deg, #00765b, #00a67a); }
.micro-decision-card__head span { display: block; color: #dbeeff; font-size: 0.6rem; font-weight: 700; }
.micro-decision-card__head h4 { margin: 0.12rem 0 0; color: #fff; font-size: 1.1rem; font-weight: 900; }
.micro-decision-card__head > div:last-child { text-align: right; }
.micro-decision-card__head strong { display: block; font-size: 0.88rem; font-weight: 900; }
.micro-ops-table--decision { min-width: 700px; }
.micro-ops-table--decision thead th { background: #eef4fb; color: #36506e; }

.micro-pattern-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.8rem; padding: 0.9rem; }
.micro-pattern-card { display: grid; grid-template-columns: 44px 1fr auto; align-items: center; gap: 0.75rem; padding: 1rem; border: 1px solid #cbdbea; border-radius: 13px; background: linear-gradient(135deg, #f8fbff, #edf6ff); }
.micro-pattern-card.pattern-musiman { background: linear-gradient(135deg, #f2fbf6, #e8f8ef); }
.micro-pattern-card__visual { display: grid; width: 44px; height: 44px; place-items: center; color: #0754bd; background: #dcecff; border-radius: 12px; font-size: 1.05rem; }
.pattern-musiman .micro-pattern-card__visual { color: #007455; background: #d4f2e5; }
.micro-pattern-card span { display: block; color: var(--micro-muted); font-size: 0.65rem; font-weight: 750; }
.micro-pattern-card strong { display: block; margin-top: 0.08rem; font-size: 1rem; font-weight: 900; }
.micro-pattern-card small { display: block; color: #6a7b90; font-size: 0.61rem; }
.micro-pattern-card b { color: #0754bd; font-size: 1.05rem; font-weight: 900; }

.micro-burden-grid,
.micro-branch-burden-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem; padding: 0.9rem; }
.micro-burden-card,
.micro-branch-burden { min-width: 0; overflow: hidden; border: 1px solid #cedbea; border-radius: 12px; }
.micro-burden-card__head { display: flex; align-items: baseline; gap: 0.4rem; padding: 0.7rem 0.8rem; color: #fff; background: #0754bd; }
.micro-burden-card.metric-sml .micro-burden-card__head { background: #c66a00; }
.micro-burden-card.metric-npl .micro-burden-card__head { background: #b52e48; }
.micro-burden-card__head span { font-size: 0.85rem; font-weight: 900; }
.micro-burden-card__head strong { font-size: 0.66rem; }
.micro-burden-card__head small { margin-left: auto; color: rgba(255,255,255,0.85); font-size: 0.58rem; }
.micro-burden-list { margin: 0; padding: 0; list-style: none; }
.micro-burden-list li { display: grid; grid-template-columns: 24px minmax(0, 1fr) auto; align-items: center; gap: 0.55rem; min-height: 54px; padding: 0.55rem 0.7rem; border-bottom: 1px solid #e0e8f1; }
.micro-burden-list li:last-child { border-bottom: 0; }
.micro-burden-list__rank { display: grid; width: 24px; height: 24px; place-items: center; color: #0754bd; background: #e3effd; border-radius: 7px; font-size: 0.62rem; font-weight: 900; }
.micro-burden-list strong { display: block; color: #203754; font-size: 0.66rem; line-height: 1.25; overflow-wrap: anywhere; }
.micro-burden-list small { display: block; margin-top: 0.08rem; color: #718198; font-size: 0.56rem; }
.micro-burden-list .empty { display: block; padding: 1rem; color: #718198; font-size: 0.65rem; text-align: center; }
.micro-delta { white-space: nowrap; font-size: 0.62rem; font-weight: 850; font-variant-numeric: tabular-nums; }
.micro-delta.positive { color: #007b59; }
.micro-delta.negative { color: #b4233e; }
.micro-delta.neutral { color: #6b7c90; }
.micro-branch-burden { display: flex; flex-direction: column; gap: 0.28rem; padding: 0.9rem; border-top: 4px solid #0754bd; background: #f8fbff; }
.micro-branch-burden.metric-sml { border-top-color: #c66a00; }
.micro-branch-burden.metric-npl { border-top-color: #b52e48; }
.micro-branch-burden > span { color: #687b91; font-size: 0.61rem; font-weight: 800; }
.micro-branch-burden > strong { color: #183654; font-size: 0.92rem; font-weight: 900; }
.micro-branch-burden > b { margin-top: 0.3rem; font-size: 0.76rem; }
.micro-ops-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 260px; padding: 2rem; color: #58708d; text-align: center; }
.micro-ops-empty > i { margin-bottom: 0.7rem; color: #0b65b8; font-size: 2rem; }
.micro-ops-empty strong { color: #183d67; font-size: 1rem; }
.micro-ops-empty span { margin-top: 0.25rem; font-size: 0.72rem; }
.micro-ops-inline-empty { padding: 1.4rem; color: #6a7b90; font-size: 0.7rem; text-align: center; }

.micro-ops-loader { display: grid; gap: 0.7rem; padding: 1rem; }
.micro-ops-loader__hero,
.micro-ops-loader__block { position: relative; overflow: hidden; background: #dfe8f3; border-radius: 14px; }
.micro-ops-loader__hero { height: 94px; }
.micro-ops-loader__block { height: 190px; }
.micro-ops-loader__hero::after,
.micro-ops-loader__block::after { content: ''; position: absolute; inset: 0; transform: translateX(-100%); background: linear-gradient(90deg, transparent, rgba(255,255,255,0.7), transparent); animation: microShimmer 1.4s infinite; }
@keyframes microShimmer { to { transform: translateX(100%); } }
.micro-ops-load-error { display: grid; min-height: 280px; place-items: center; align-content: center; gap: 0.55rem; padding: 2rem; color: #536981; text-align: center; background: #f7fbff; border: 1px solid #ccdaea; border-radius: 16px; }
.micro-ops-load-error > i { color: #c13a50; font-size: 2rem; }
.micro-ops-load-error h3 { margin: 0; color: #173b66; font-size: 1rem; }
.micro-ops-load-error p { margin: 0; font-size: 0.7rem; }
.micro-ops-load-error button { min-height: 40px; padding: 0.5rem 0.8rem; color: #fff; background: #0754bd; border: 0; border-radius: 9px; font-size: 0.7rem; font-weight: 800; }

/* Readability uplift for the operational SME trigger. */
.sme-ops-section-heading h3,
.sme-ops-feature-head h3 { font-size: clamp(1.02rem, 1.3vw, 1.28rem) !important; line-height: 1.25 !important; }
.sme-ops-section-heading p,
.sme-ops-feature-head p { font-size: 0.72rem !important; line-height: 1.5 !important; }
.sme-ops-vendor-item h4,
.sme-ops-process-row__label,
.sme-ops-restruct-step h4 { font-size: 0.73rem !important; line-height: 1.35 !important; }
.sme-ops-kanwil-head { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: 0.9rem; padding: 0.75rem 0.85rem; background: linear-gradient(120deg, #e8f3ff, #f7fbff); border: 1px solid #c3d8ef; border-radius: 10px 10px 0 0; }
.sme-ops-kanwil-head h4 { margin: 0.12rem 0 0; color: #123d70; font-size: 0.86rem; font-weight: 900; }
.sme-ops-kanwil-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-right: 1px solid #d4e0ed; border-left: 1px solid #d4e0ed; }
.sme-ops-kanwil-summary > div { padding: 0.65rem; border-right: 1px solid #d4e0ed; background: #fff; }
.sme-ops-kanwil-summary > div:last-child { border-right: 0; }
.sme-ops-kanwil-summary span { display: block; color: #63758a; font-size: 0.58rem; font-weight: 750; }
.sme-ops-kanwil-summary strong { display: block; margin-top: 0.12rem; color: #0754bd; font-size: 1rem; font-weight: 900; }
.sme-ops-kanwil-table-wrap { overflow-x: auto; border: 1px solid #d4e0ed; border-radius: 0 0 10px 10px; }
.sme-ops-kanwil-table { width: 100%; min-width: 590px; border-collapse: collapse; font-size: 0.65rem; }
.sme-ops-kanwil-table th,
.sme-ops-kanwil-table td { padding: 0.62rem 0.7rem; border-bottom: 1px solid #e0e8f1; text-align: right; font-variant-numeric: tabular-nums; }
.sme-ops-kanwil-table thead th { color: #fff; background: #0b438e; font-size: 0.58rem; font-weight: 850; text-transform: uppercase; }
.sme-ops-kanwil-table th:first-child { min-width: 120px; text-align: left; }
.sme-ops-kanwil-table tbody th { color: #1d3b5c; font-weight: 800; }
.sme-ops-kanwil-table tbody tr:nth-child(even) { background: #f6faff; }

@media (max-width: 1199.98px) {
  .landing-scope-stage { grid-template-columns: 1fr; }
  .landing-scope-visual { justify-content: center; }
  .micro-decision-grid { grid-template-columns: 1fr; }
  .micro-burden-grid { grid-template-columns: 1fr; }
}
@media (max-width: 767.98px) {
  .db-branch-picker { width: 100%; min-width: 0; }
  .landing-scope-visual { display: none; }
  .db-shell .landing-scope-stage .area6-scope-toggle { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-ops { padding: 0.65rem; gap: 0.7rem; border-radius: 14px; }
  .micro-ops-hero,
  .micro-ops-section__head { align-items: flex-start; flex-direction: column; }
  .micro-ops-subhead { align-items: flex-start; flex-direction: column; gap: 0.2rem; }
  .micro-ops-subhead small { text-align: left; }
  .micro-ops-hero__meta { justify-content: flex-start; }
  .micro-ops-section__total-group { align-items: flex-start; }
  .micro-realization-type-grid,
  .micro-pattern-grid,
  .micro-branch-burden-grid { grid-template-columns: 1fr; }
  .micro-product-grid { grid-template-columns: 1fr; }
  .sme-ops-kanwil-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-decision-grid,
  .micro-burden-grid,
  .micro-branch-burden-grid { padding: 0.65rem; }
}
@media (prefers-reduced-motion: reduce) {
  .micro-ops-refresh.is-loading i,
  .micro-ops-loader__hero::after,
  .micro-ops-loader__block::after { animation: none; }
}

/* Micro visual language v3: bold banking intelligence, geometric bento, readable data. */
.db-shell .area6-panel .area6-card-grid.area6-card-grid--three {
  width: min(100%, 1180px);
  margin-inline: auto;
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  justify-content: center;
}
.db-shell.micro-performance-active #area6-scope-subtitle { display: none; }
.micro-performance-dashboard { margin-top: 1.25rem; }
.micro-ops {
  --micro-midnight: #041e49;
  --micro-deep: #063476;
  --micro-nusantara: #0754bd;
  --micro-cakrawala: #13a7e2;
  --micro-sky: #bfeeff;
  --micro-surface: #f3f8ff;
  --micro-ink: #0c2340;
  --micro-muted: #51657d;
  --micro-line: #b8cce4;
  position: relative;
  isolation: isolate;
  overflow: hidden;
  gap: 1.25rem;
  padding: 1.25rem;
  color: var(--micro-ink);
  background:
    linear-gradient(rgba(7, 84, 189, 0.045) 1px, transparent 1px),
    linear-gradient(90deg, rgba(7, 84, 189, 0.045) 1px, transparent 1px),
    linear-gradient(145deg, #f8fbff 0%, #eaf4ff 54%, #f4fbff 100%);
  background-size: 32px 32px, 32px 32px, auto;
  border: 1px solid #9fc1e7;
  border-radius: 28px 8px 28px 8px;
  box-shadow: 0 28px 70px -48px rgba(2, 39, 91, 0.82);
}
.micro-ops::before,
.micro-ops::after {
  content: '';
  position: absolute;
  z-index: -1;
  pointer-events: none;
}
.micro-ops::before {
  top: 300px;
  right: -110px;
  width: 330px;
  height: 330px;
  border: 52px solid rgba(19, 167, 226, 0.08);
  border-radius: 50%;
}
.micro-ops::after {
  bottom: 4%;
  left: -100px;
  width: 260px;
  height: 150px;
  background: linear-gradient(135deg, rgba(7, 84, 189, 0.1), rgba(19, 167, 226, 0));
  transform: skewX(-24deg);
}
.micro-ops > * { position: relative; z-index: 1; }
.micro-ops-hero {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(285px, 0.75fr);
  align-items: stretch;
  min-height: 246px;
  padding: 1.5rem;
  background:
    linear-gradient(116deg, rgba(3, 28, 68, 0.98) 0%, rgba(6, 52, 118, 0.98) 49%, rgba(7, 84, 189, 0.96) 100%);
  border: 1px solid rgba(157, 225, 255, 0.34);
  border-radius: 24px 6px 24px 6px;
  box-shadow: 0 26px 54px -32px rgba(2, 30, 76, 0.92);
}
.micro-ops-hero::before {
  content: '';
  position: absolute;
  inset: 0 43% 0 auto;
  width: 120px;
  background: linear-gradient(135deg, transparent 12%, rgba(19, 167, 226, 0.12) 12% 54%, transparent 54%);
  pointer-events: none;
}
.micro-ops-hero::after {
  right: -70px;
  bottom: -115px;
  width: 300px;
  height: 300px;
  border: 44px solid rgba(255, 255, 255, 0.075);
}
.micro-ops-hero__copy {
  position: relative;
  z-index: 2;
  display: flex;
  min-width: 0;
  flex-direction: column;
  justify-content: space-between;
  gap: 1.25rem;
}
.micro-ops-hero__identity { align-items: flex-start; gap: 1rem; }
.micro-ops-hero__icon {
  width: 58px;
  height: 58px;
  flex-basis: 58px;
  color: #fff;
  background: linear-gradient(145deg, #13a7e2, #0873ca);
  border: 1px solid rgba(255, 255, 255, 0.56);
  border-radius: 6px 18px 6px 18px;
  box-shadow: 10px 10px 0 rgba(2, 27, 68, 0.36);
  font-size: 1.35rem;
}
.micro-ops-eyebrow { color: #0754bd; font-size: 0.72rem; line-height: 1.35; }
.micro-ops-hero .micro-ops-eyebrow { color: #8de4ff; }
.micro-ops-hero h2 {
  max-width: 760px;
  margin-top: 0.3rem;
  font-size: clamp(1.45rem, 2.25vw, 2.05rem);
  line-height: 1.13;
}
.micro-ops-hero p {
  max-width: 690px;
  margin-top: 0.55rem;
  color: #dceeff;
  font-size: 0.86rem;
  line-height: 1.55;
}
.micro-ops-hero__meta { justify-content: flex-start; gap: 0.55rem; }
.micro-ops-hero__meta > span,
.micro-ops-refresh {
  min-height: 44px;
  padding: 0.58rem 0.78rem;
  color: #f6fbff;
  background: rgba(2, 29, 70, 0.5);
  border-color: rgba(158, 228, 255, 0.34);
  border-radius: 6px 12px 6px 12px;
  font-size: 0.74rem;
  line-height: 1.3;
}
.micro-ops-refresh { transition: color 160ms ease, background-color 160ms ease, box-shadow 160ms ease; }
.micro-ops-refresh:hover { color: #063476; background: #fff; box-shadow: 0 10px 24px -16px rgba(0, 0, 0, 0.9); }
.micro-ops-hero__visual {
  position: relative;
  z-index: 1;
  display: grid;
  min-width: 0;
  min-height: 205px;
  place-items: center;
  overflow: hidden;
  background:
    linear-gradient(145deg, rgba(255, 255, 255, 0.13), rgba(19, 167, 226, 0.08));
  border: 1px solid rgba(183, 235, 255, 0.26);
  border-radius: 30px 6px 30px 6px;
}
.micro-ops-hero__visual::before {
  content: '';
  position: absolute;
  inset: 13px;
  border: 1px dashed rgba(176, 232, 255, 0.24);
  border-radius: 22px 4px 22px 4px;
}
.micro-ops-hero__visual svg { width: min(100%, 340px); height: 198px; }
.micro-ops-hero__visual-tag {
  position: absolute;
  right: 0.8rem;
  bottom: 0.75rem;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.42rem 0.62rem;
  color: #052d68;
  background: #fff;
  border-radius: 4px 10px 4px 10px;
  box-shadow: 0 10px 24px -16px rgba(1, 18, 48, 0.9);
  font-size: 0.69rem;
  font-weight: 850;
}
.micro-ops-hero__visual-tag i { color: #13a7e2; }
.micro-ops-command-ribbon {
  display: grid;
  grid-template-columns: minmax(225px, 1.2fr) repeat(3, minmax(145px, 1fr));
  align-items: stretch;
  overflow: hidden;
  color: #fff;
  background: var(--micro-midnight);
  border: 1px solid #0b4f9e;
  border-radius: 6px 20px 6px 20px;
  box-shadow: 0 18px 40px -32px rgba(2, 34, 81, 0.92);
}
.micro-ops-command-ribbon__lead,
.micro-ops-command-ribbon__metric { min-width: 0; padding: 0.9rem 1rem; }
.micro-ops-command-ribbon__lead {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  overflow: hidden;
  background: linear-gradient(120deg, #0754bd, #087fc8);
}
.micro-ops-command-ribbon__lead::after {
  content: '';
  position: absolute;
  top: -54px;
  right: -24px;
  width: 90px;
  height: 130px;
  background: rgba(123, 222, 255, 0.18);
  transform: rotate(24deg);
}
.micro-ops-command-ribbon__icon {
  display: grid;
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  place-items: center;
  color: #052d68;
  background: #8de4ff;
  border-radius: 4px 13px 4px 13px;
}
.micro-ops-command-ribbon small,
.micro-ops-command-ribbon span { display: block; color: #9fc5eb; font-size: 0.69rem; font-weight: 750; line-height: 1.35; }
.micro-ops-command-ribbon__lead small { color: #a9e8ff; letter-spacing: 0.1em; }
.micro-ops-command-ribbon strong { display: block; margin-top: 0.16rem; color: #fff; font-size: 0.92rem; font-weight: 900; line-height: 1.3; font-variant-numeric: tabular-nums; }
.micro-ops-command-ribbon__metric { display: flex; flex-direction: column; justify-content: center; border-left: 1px solid rgba(151, 205, 245, 0.18); }
.micro-ops-command-ribbon__metric strong { font-size: clamp(0.92rem, 1.25vw, 1.13rem); }
.micro-ops-section {
  --micro-section-accent: var(--micro-nusantara);
  position: relative;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.98);
  border: 0;
  border-left: 7px solid var(--micro-section-accent);
  border-radius: 6px 22px 6px 22px;
  box-shadow:
    0 22px 46px -38px rgba(3, 41, 92, 0.92),
    inset 0 0 0 1px rgba(160, 187, 220, 0.66);
}
.micro-ops-section--realization { --micro-section-accent: #087fc8; }
.micro-ops-section--decision { --micro-section-accent: #13a7e2; }
.micro-ops-section--pattern { --micro-section-accent: #0b6ea8; }
.micro-ops-section--burden { --micro-section-accent: #063476; }
.micro-ops-section::after {
  content: '';
  position: absolute;
  z-index: 0;
  top: -52px;
  right: -28px;
  width: 132px;
  height: 92px;
  background: linear-gradient(135deg, rgba(19, 167, 226, 0.18), rgba(19, 167, 226, 0));
  transform: rotate(18deg);
  pointer-events: none;
}
.micro-ops-section > * { position: relative; z-index: 1; }
.micro-ops-section__head {
  min-height: 82px;
  padding: 1rem 1.15rem;
  background:
    linear-gradient(100deg, #eaf4ff 0%, #f9fcff 55%, #eaf9ff 100%);
  border-bottom: 2px solid #c4d9ee;
}
.micro-ops-section__head > div:first-child { padding-left: 3.15rem; }
.micro-ops-section__number {
  width: 40px;
  height: 40px;
  color: #fff;
  background: linear-gradient(145deg, var(--micro-deep), var(--micro-cakrawala));
  border-radius: 4px 14px 4px 14px;
  box-shadow: 6px 6px 0 rgba(7, 84, 189, 0.12);
  font-size: 0.76rem;
}
.micro-ops-section h3 { margin-top: 0.18rem; font-size: clamp(1.08rem, 1.4vw, 1.34rem); line-height: 1.25; }
.micro-ops-section__total,
.micro-ops-section__total-group strong { color: var(--micro-deep); font-size: clamp(1.15rem, 1.7vw, 1.48rem); }
.micro-ops-section__total-group span { font-size: 0.73rem; }
.micro-ops-source-chip,
.micro-ops-comparison { padding: 0.48rem 0.68rem; color: #084d91; border-color: #abccec; border-radius: 4px 10px 4px 10px; font-size: 0.72rem; }
.micro-product-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.85rem; padding: 1.1rem; }
.micro-product-card {
  --product-accent: #0754bd;
  --product-accent-soft: rgba(7, 84, 189, 0.18);
  position: relative;
  display: grid;
  grid-template-columns: 46px minmax(0, 1fr);
  grid-template-areas:
    'icon head'
    'icon value'
    'bar bar'
    'meta meta';
  align-content: center;
  min-height: 142px;
  gap: 0.3rem 0.75rem;
  overflow: hidden;
  padding: 1rem;
  background: linear-gradient(145deg, #f9fcff, #eaf4ff);
  border: 0;
  border-radius: 18px 4px 18px 4px;
  box-shadow: inset 0 0 0 1px #c3d7ec;
}
.micro-product-card::after {
  content: '';
  position: absolute;
  top: -32px;
  right: -30px;
  width: 92px;
  height: 76px;
  background: var(--product-accent-soft);
  transform: rotate(28deg);
}
.micro-product-card.accent-2 { --product-accent: #13a7e2; --product-accent-soft: rgba(19, 167, 226, 0.18); }
.micro-product-card.accent-3 { --product-accent: #087fc8; --product-accent-soft: rgba(8, 127, 200, 0.18); }
.micro-product-card.accent-4 { --product-accent: #0b6ea8; --product-accent-soft: rgba(11, 110, 168, 0.18); }
.micro-product-card.accent-5 { --product-accent: #063476; --product-accent-soft: rgba(6, 52, 118, 0.18); }
.micro-product-card.is-featured {
  grid-column: span 2;
  min-height: 158px;
  background: linear-gradient(125deg, #052d68, #0754bd 68%, #0a8fcd);
  box-shadow: none;
}
.micro-product-card__icon {
  grid-area: icon;
  display: grid;
  width: 46px;
  height: 46px;
  place-items: center;
  color: #fff;
  background: var(--product-accent);
  border-radius: 4px 14px 4px 14px;
  font-size: 1rem;
}
.micro-product-card.is-featured .micro-product-card__icon { color: #052d68; background: #8de4ff; }
.micro-product-card__head { grid-area: head; align-items: center; }
.micro-product-card__head span { color: #1a3657; font-size: 0.79rem; line-height: 1.35; }
.micro-product-card__head strong { color: var(--product-accent); font-size: 0.78rem; }
.micro-product-card__value { grid-area: value; margin: 0; color: #092f65; font-size: clamp(1.08rem, 1.45vw, 1.32rem); line-height: 1.2; }
.micro-product-card__bar { grid-area: bar; height: 7px; margin: 0.55rem 0 0.2rem; background: #cbdced; }
.micro-product-card__bar span { background: linear-gradient(90deg, var(--product-accent), #49c8f5); }
.micro-product-card small { grid-area: meta; color: #566c84; font-size: 0.7rem; }
.micro-product-card.is-featured .micro-product-card__head span,
.micro-product-card.is-featured .micro-product-card__value { color: #fff; }
.micro-product-card.is-featured .micro-product-card__head strong { color: #8de4ff; }
.micro-product-card.is-featured small { color: #cbeaff; }
.micro-product-card.is-featured .micro-product-card__bar { background: rgba(255, 255, 255, 0.2); }
.micro-realization-type-grid { gap: 0.9rem; padding: 1.05rem 1.05rem 0; }
.micro-realization-type-card {
  position: relative;
  min-height: 132px;
  overflow: hidden;
  padding: 1rem 1.1rem;
  color: #fff;
  background: linear-gradient(125deg, #063476, #0754bd);
  border: 0;
  border-radius: 4px 20px 4px 20px;
}
.micro-realization-type-card::after {
  content: '';
  position: absolute;
  right: -52px;
  bottom: -68px;
  width: 150px;
  height: 150px;
  border: 26px solid rgba(255, 255, 255, 0.1);
  border-radius: 50%;
}
.micro-realization-type-card.type-baru { border: 0; }
.micro-realization-type-card.type-suplesi { border: 0; background: linear-gradient(125deg, #075a92, #13a7e2); }
.micro-realization-type-card__icon,
.type-suplesi .micro-realization-type-card__icon { color: #063476; background: #bfeeff; border-radius: 4px 13px 4px 13px; }
.micro-realization-type-card span,
.micro-realization-type-card small { color: #d9edff; font-size: 0.73rem; }
.micro-realization-type-card strong { color: #fff; font-size: 1.24rem; }
.micro-ops-subhead { margin: 1.1rem 1.05rem 0.55rem; }
.micro-ops-subhead span { font-size: 0.66rem; }
.micro-ops-subhead h4 { font-size: 1rem; }
.micro-ops-subhead small { font-size: 0.72rem; }
.micro-ops-table { min-width: 680px; font-size: 0.76rem; }
.micro-ops-table th,
.micro-ops-table td { padding: 0.76rem 0.82rem; }
.micro-ops-table thead th { z-index: 3; color: #fff; background: #063476; font-size: 0.68rem; line-height: 1.35; }
.micro-ops-table--decision thead th { color: #fff; background: #063476; }
.micro-ops-table tbody th { font-size: 0.76rem; }
.micro-ops-table .compact { font-size: 0.7rem; }
.micro-share-badge { padding: 0.28rem 0.48rem; border-radius: 4px 8px 4px 8px; font-size: 0.72rem; }
.micro-decision-grid { gap: 1rem; padding: 1.05rem; }
.micro-decision-card {
  border: 0;
  border-left: 6px solid #13a7e2;
  border-radius: 4px 18px 4px 18px;
  box-shadow: inset 0 0 0 1px #bfd2e7, 0 16px 32px -28px rgba(2, 44, 96, 0.9);
}
.micro-decision-card__head { min-height: 86px; padding: 0.9rem 1rem; background: linear-gradient(115deg, #052d68, #0754bd); }
.micro-decision-card.role-sboh .micro-decision-card__head { background: linear-gradient(115deg, #075a92, #13a7e2); }
.micro-decision-card.role-mbm .micro-decision-card__head { background: linear-gradient(115deg, #041e49, #0b5fae); }
.micro-decision-card.role-ka_unit .micro-decision-card__head { background: linear-gradient(115deg, #0754bd, #0a91d2); }
.micro-decision-card__head span { font-size: 0.7rem; }
.micro-decision-card__head h4 { font-size: 1.25rem; }
.micro-decision-card__head strong { font-size: 1rem; }
.micro-ops-table--decision { min-width: 760px; }
.micro-pattern-grid { gap: 1rem; padding: 1.05rem; }
.micro-pattern-card {
  position: relative;
  min-height: 122px;
  overflow: hidden;
  padding: 1.05rem;
  color: #fff;
  background: linear-gradient(125deg, #063476, #087fc8);
  border: 0;
  border-radius: 18px 4px 18px 4px;
}
.micro-pattern-card.pattern-musiman { background: linear-gradient(125deg, #052d68, #0754bd 68%, #13a7e2); }
.micro-pattern-card::after { content: ''; position: absolute; top: -35px; right: -25px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); transform: rotate(28deg); }
.micro-pattern-card__visual,
.pattern-musiman .micro-pattern-card__visual { color: #063476; background: #bfeeff; border-radius: 4px 14px 4px 14px; }
.micro-pattern-card span,
.micro-pattern-card small { color: #d8edff; font-size: 0.73rem; }
.micro-pattern-card strong { color: #fff; font-size: 1.16rem; }
.micro-pattern-card b { color: #8de4ff; font-size: 1.25rem; }
.micro-pattern-card__copy { position: relative; z-index: 1; min-width: 0; }
.micro-pattern-card__breakdown {
  position: relative;
  z-index: 1;
  display: grid;
  grid-column: 1 / -1;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
  padding-top: 0.75rem;
  border-top: 1px solid rgba(206, 241, 255, 0.3);
}
.micro-pattern-card__detail {
  min-width: 0;
  padding: 0.56rem 0.62rem;
  background: rgba(2, 39, 89, 0.3);
  border: 1px solid rgba(191, 238, 255, 0.25);
  border-radius: 4px 10px 4px 10px;
}
.micro-pattern-card__detail span,
.micro-pattern-card__detail small { color: #d8edff; font-size: 0.62rem; line-height: 1.35; }
.micro-pattern-card__detail strong { margin-top: 0.16rem; color: #fff; font-size: 0.82rem; line-height: 1.25; white-space: nowrap; }
.micro-pattern-card__detail small { margin-top: 0.1rem; color: #a9dcf3; }
.micro-burden-grid,
.micro-branch-burden-grid { gap: 0.9rem; padding: 1.05rem; }
.micro-burden-card,
.micro-branch-burden { border: 0; border-radius: 4px 17px 4px 17px; box-shadow: inset 0 0 0 1px #c2d3e6; }
.micro-burden-card__head { min-height: 54px; padding: 0.75rem 0.85rem; background: linear-gradient(115deg, #052d68, #0754bd); }
.micro-burden-card.metric-sml .micro-burden-card__head { background: linear-gradient(115deg, #7c4300, #d97706); }
.micro-burden-card.metric-npl .micro-burden-card__head { background: linear-gradient(115deg, #7e1830, #c92f47); }
.micro-burden-card__head span { font-size: 0.94rem; }
.micro-burden-card__head strong { font-size: 0.72rem; }
.micro-burden-card__head small { font-size: 0.68rem; }
.micro-burden-list li { min-height: 60px; padding: 0.65rem 0.78rem; }
.micro-burden-list__rank { border-radius: 4px 8px 4px 8px; font-size: 0.7rem; }
.micro-burden-list strong { font-size: 0.74rem; line-height: 1.35; }
.micro-burden-list small { font-size: 0.66rem; }
.micro-delta { font-size: 0.7rem; }
.micro-branch-burden { min-height: 118px; justify-content: center; border-top: 0; border-left: 6px solid #0754bd; background: linear-gradient(135deg, #f9fcff, #eaf4ff); }
.micro-branch-burden.metric-sml { border-top: 0; border-left-color: #d97706; }
.micro-branch-burden.metric-npl { border-top: 0; border-left-color: #c92f47; }
.micro-branch-burden > span { font-size: 0.7rem; }
.micro-branch-burden > strong { font-size: 1.06rem; }
.micro-branch-burden > b { font-size: 0.82rem; }

@media (min-width: 768px) and (max-width: 1199.98px) {
  .db-shell .area6-panel .area6-card-grid.area6-card-grid--three { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
  .db-shell .area6-panel .area6-card-grid.area6-card-grid--three > :last-child {
    width: min(100%, 390px);
    grid-column: 1 / -1;
    justify-self: center;
  }
  .micro-ops-hero { grid-template-columns: minmax(0, 1fr) 275px; }
  .micro-ops-command-ribbon { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-ops-command-ribbon__metric:nth-child(3) { border-left: 0; border-top: 1px solid rgba(151, 205, 245, 0.18); }
  .micro-ops-command-ribbon__metric:nth-child(4) { border-top: 1px solid rgba(151, 205, 245, 0.18); }
  .micro-product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-product-card.is-featured { grid-column: span 2; }
}
@media (max-width: 767.98px) {
  .db-shell .area6-panel .area6-card-grid.area6-card-grid--three { width: 100%; grid-template-columns: 1fr !important; }
  .landing-scope-visual { display: flex; min-height: 58px; justify-content: center; }
  .landing-scope-visual__copy { display: none; }
  .landing-scope-visual svg { width: 128px; height: 58px; flex-basis: 128px; }
  .micro-ops { gap: 0.8rem; padding: 0.7rem; border-radius: 18px 5px 18px 5px; }
  .micro-ops-hero { grid-template-columns: 1fr; min-height: 0; padding: 1rem; }
  .micro-ops-hero__copy { gap: 0.9rem; }
  .micro-ops-hero__identity { gap: 0.75rem; }
  .micro-ops-hero__icon { width: 50px; height: 50px; flex-basis: 50px; box-shadow: 6px 6px 0 rgba(2, 27, 68, 0.3); }
  .micro-ops-hero h2 { font-size: 1.38rem; }
  .micro-ops-hero p { font-size: 0.8rem; }
  .micro-ops-hero__visual { min-height: 154px; }
  .micro-ops-hero__visual svg { width: 250px; height: 150px; }
  .micro-ops-hero__visual-tag { right: 0.55rem; bottom: 0.5rem; font-size: 0.65rem; }
  .micro-ops-command-ribbon { grid-template-columns: 1fr; }
  .micro-ops-command-ribbon__metric { border-top: 1px solid rgba(151, 205, 245, 0.18); border-left: 0; }
  .micro-ops-section__head { gap: 0.65rem; padding: 0.85rem; }
  .micro-ops-section__head > div:first-child { padding-left: 2.9rem; }
  .micro-ops-section h3 { font-size: 1.08rem; }
  .micro-product-grid { grid-template-columns: 1fr; padding: 0.75rem; }
  .micro-product-card.is-featured { grid-column: span 1; }
  .micro-product-card { min-height: 136px; }
  .micro-realization-type-grid,
  .micro-pattern-grid,
  .micro-decision-grid,
  .micro-burden-grid,
  .micro-branch-burden-grid { padding: 0.75rem; }
  .micro-ops-table { font-size: 0.72rem; }
  .micro-ops-table th,
  .micro-ops-table td { padding: 0.68rem 0.72rem; }
}
@media (prefers-reduced-motion: reduce) {
  .micro-ops-refresh { transition: none; }
}

/* Micro productivity stage v4: two-track realization and sticky Mantri matrices. */
.micro-realization-type-grid--primary { align-items: stretch; }
.micro-realization-type-card.type-plafond,
.micro-realization-type-card.type-nett {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 0.9rem;
  min-height: 148px;
  border: 0;
}
.micro-realization-type-card.type-plafond {
  background: linear-gradient(125deg, #041e49 0%, #0754bd 70%, #087fc8 100%);
}
.micro-realization-type-card.type-nett {
  grid-template-columns: auto minmax(0, 1fr) minmax(210px, 0.8fr);
  background: linear-gradient(125deg, #075a92 0%, #087fc8 54%, #13a7e2 100%);
}
.micro-realization-type-card.type-nett .micro-realization-type-card__icon {
  color: #075a92;
  background: #d9f6ff;
}
.micro-nett-type-list {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.45rem;
}
.micro-nett-type-list > span {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.12rem;
  padding: 0.58rem 0.65rem;
  color: #e8f8ff;
  background: rgba(3, 37, 83, 0.32);
  border: 1px solid rgba(205, 242, 255, 0.24);
  border-radius: 4px 11px 4px 11px;
  font-size: 0.65rem;
  line-height: 1.3;
}
.micro-nett-type-list > span b {
  color: #fff;
  font-size: 0.7rem;
  white-space: normal;
}
.micro-ops-table tfoot th,
.micro-ops-table tfoot td {
  padding: 0.76rem 0.82rem;
  color: #052d68;
  background: #e7f2ff;
  border-top: 2px solid #a7c8e9;
  font-weight: 900;
}
.micro-ops-section--mantri { --micro-section-accent: #0754bd; }
.micro-mantri-stage {
  position: relative;
  display: grid;
  min-height: 220px;
  grid-template-columns: minmax(0, 1.35fr) minmax(250px, 0.65fr);
  align-items: stretch;
  overflow: hidden;
  color: #fff;
  background:
    linear-gradient(112deg, rgba(4, 30, 73, 0.99) 0%, rgba(7, 84, 189, 0.98) 64%, rgba(19, 167, 226, 0.95) 100%);
  border-bottom: 1px solid #9edcff;
}
.micro-mantri-stage::before,
.micro-mantri-stage::after {
  content: '';
  position: absolute;
  pointer-events: none;
}
.micro-mantri-stage::before {
  top: -82px;
  left: 36%;
  width: 230px;
  height: 300px;
  background: rgba(80, 207, 252, 0.11);
  transform: rotate(26deg);
}
.micro-mantri-stage::after {
  right: -90px;
  bottom: -150px;
  width: 310px;
  height: 310px;
  border: 42px solid rgba(255, 255, 255, 0.09);
  border-radius: 50%;
}
.micro-mantri-stage__copy {
  position: relative;
  z-index: 2;
  display: flex;
  min-width: 0;
  flex-direction: column;
  justify-content: center;
  padding: 1.5rem 1.6rem;
}
.micro-mantri-stage .micro-ops-section__number {
  position: static;
  margin-bottom: 0.9rem;
  color: #052d68;
  background: #8de4ff;
  box-shadow: 7px 7px 0 rgba(1, 29, 70, 0.34);
}
.micro-mantri-stage .micro-ops-eyebrow { color: #8de4ff; }
.micro-mantri-stage h3 {
  margin: 0.22rem 0 0;
  color: #fff;
  font-size: clamp(1.35rem, 2vw, 1.9rem);
}
.micro-mantri-stage__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
  margin-top: 1.05rem;
}
.micro-mantri-stage__chips span {
  display: inline-flex;
  min-height: 38px;
  align-items: center;
  gap: 0.48rem;
  padding: 0.48rem 0.72rem;
  color: #f7fcff;
  background: rgba(2, 35, 81, 0.45);
  border: 1px solid rgba(178, 234, 255, 0.33);
  border-radius: 4px 11px 4px 11px;
  font-size: 0.73rem;
  font-weight: 850;
}
.micro-mantri-stage__chips i { color: #8de4ff; }
.micro-mantri-stage__visual {
  position: relative;
  z-index: 2;
  display: grid;
  min-height: 190px;
  place-items: center;
  padding: 0.8rem 1rem;
}
.micro-mantri-stage__visual::before {
  content: '';
  position: absolute;
  inset: 1rem;
  background: rgba(255, 255, 255, 0.08);
  border: 1px dashed rgba(204, 242, 255, 0.32);
  border-radius: 24px 5px 24px 5px;
}
.micro-mantri-stage__visual svg { position: relative; width: min(100%, 260px); height: 170px; }
.micro-mantri-table-wrap {
  position: relative;
  max-width: 100%;
  max-height: 430px;
  overflow: auto;
  overscroll-behavior: contain;
  scrollbar-gutter: stable;
  background: #fff;
  border: 1px solid #b8cee5;
}
.micro-mantri-table-wrap--summary { max-height: 500px; border-inline: 0; }
.micro-mantri-table {
  width: 100%;
  min-width: 960px;
  border-collapse: separate;
  border-spacing: 0;
  color: #10233f;
  background: #fff;
  font-size: 0.72rem;
  font-variant-numeric: tabular-nums;
}
.micro-mantri-table th,
.micro-mantri-table td {
  min-height: 44px;
  padding: 0.68rem 0.62rem;
  border-right: 1px solid #c5d7e9;
  border-bottom: 1px solid #d4e1ee;
  text-align: center;
  line-height: 1.35;
  white-space: nowrap;
}
.micro-mantri-table thead tr:first-child th {
  position: sticky;
  z-index: 10;
  top: 0;
  height: 50px;
  color: #fff;
  background: #052d68;
  border-bottom-color: #75bce9;
  font-size: 0.68rem;
  font-weight: 900;
  letter-spacing: 0.015em;
  vertical-align: middle;
}
.micro-mantri-table thead tr:nth-child(2) th {
  position: sticky;
  z-index: 9;
  top: 50px;
  height: 44px;
  color: #fff;
  background: #0754bd;
  font-size: 0.66rem;
}
.micro-mantri-table tbody tr:nth-child(even) td,
.micro-mantri-table tbody tr:nth-child(even) th { background-color: #f3f8ff; }
.micro-mantri-table tbody tr:hover td,
.micro-mantri-table tbody tr:hover th { background-color: #e6f4ff; }
.micro-mantri-table tbody th { color: #052d68; font-weight: 900; text-align: left; }
.micro-mantri-table .strong { color: #0754bd; font-weight: 900; }
.micro-mantri-table tfoot th,
.micro-mantri-table tfoot td {
  position: sticky;
  z-index: 4;
  bottom: 0;
  color: #fff;
  background: #063476;
  border-top: 2px solid #71ccec;
  border-bottom: 0;
  font-weight: 900;
}
.micro-mantri-table--summary thead tr:first-child th:nth-child(1),
.micro-mantri-table--summary tbody td:nth-child(1) { position: sticky; left: 0; width: 54px; min-width: 54px; }
.micro-mantri-table--summary thead tr:first-child th:nth-child(2),
.micro-mantri-table--summary tbody td:nth-child(2) { position: sticky; left: 54px; width: 76px; min-width: 76px; }
.micro-mantri-table--summary thead tr:first-child th:nth-child(3),
.micro-mantri-table--summary tbody th { position: sticky; left: 130px; width: 164px; min-width: 164px; }
.micro-mantri-table--summary thead tr:first-child th:nth-child(-n+3) { z-index: 13; background: #052d68; }
.micro-mantri-table--summary tbody td:nth-child(1),
.micro-mantri-table--summary tbody td:nth-child(2),
.micro-mantri-table--summary tbody th { z-index: 3; background: #fff; }
.micro-mantri-table--summary tbody tr:nth-child(even) td:nth-child(1),
.micro-mantri-table--summary tbody tr:nth-child(even) td:nth-child(2),
.micro-mantri-table--summary tbody tr:nth-child(even) th { background: #f3f8ff; }
.micro-mantri-table--summary tfoot th:first-child { position: sticky; z-index: 6; left: 0; text-align: left; }
.micro-tier-panels { display: grid; gap: 1rem; padding: 1.05rem; }
.micro-tier-panel {
  overflow: hidden;
  background: #fff;
  border: 1px solid #adc8e4;
  border-radius: 5px 18px 5px 18px;
  box-shadow: 0 18px 34px -30px rgba(3, 40, 90, 0.9);
}
.micro-tier-panel > header {
  display: flex;
  min-height: 54px;
  align-items: center;
  gap: 0.65rem;
  padding: 0.72rem 0.9rem;
  color: #fff;
  background: linear-gradient(110deg, #052d68, #0754bd);
}
.micro-tier-panel--contract > header { background: linear-gradient(110deg, #075a92, #13a7e2); }
.micro-tier-panel > header i {
  display: grid;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  place-items: center;
  color: #063476;
  background: #bfeeff;
  border-radius: 4px 10px 4px 10px;
}
.micro-tier-panel > header h4 { margin: 0; color: #fff; font-size: 0.88rem; }
.micro-tier-panel__heading { min-width: 0; }
.micro-mantri-table--tiers { min-width: 1050px; }
.micro-mantri-table--tiers thead tr:first-child th:first-child,
.micro-mantri-table--tiers tbody th,
.micro-mantri-table--tiers tfoot th {
  position: sticky;
  z-index: 12;
  left: 0;
  width: 170px;
  min-width: 170px;
}
.micro-mantri-table--tiers tbody th { z-index: 3; background: #fff; }
.micro-mantri-table--tiers tbody tr:nth-child(even) th { background: #f3f8ff; }
.micro-mantri-table--tiers tfoot th { z-index: 6; background: #063476; }
.micro-mantri-table thead .tone-none { background: #ad173b !important; }
.micro-mantri-table thead .tone-extreme_low { background: #8f3b10 !important; }
.micro-mantri-table thead .tone-low { background: #8a5a00 !important; }
.micro-mantri-table thead .tone-mid { background: #087eaf !important; }
.micro-mantri-table thead .tone-high { background: #0754bd !important; }
.micro-mantri-table tbody .tone-none { color: #9c1637; background-color: #fff0f3; }
.micro-mantri-table tbody .tone-extreme_low { color: #81320b; background-color: #fff2e8; }
.micro-mantri-table tbody .tone-low { color: #765000; background-color: #fff8df; }
.micro-mantri-table tbody .tone-mid { color: #06668f; background-color: #eaf9ff; }
.micro-mantri-table tbody .tone-high { color: #06469d; background-color: #e9f2ff; font-weight: 850; }
.micro-mantri-table--tiers td[data-micro-mantri-tier-count] { cursor: pointer; touch-action: manipulation; }
.micro-mantri-table--tiers td[data-micro-mantri-tier-count]:hover { box-shadow: inset 0 0 0 2px rgba(7, 84, 189, .32); }
.micro-mantri-table--tiers td[data-micro-mantri-tier-count]:focus-visible { position: relative; z-index: 2; outline: 3px solid #65b8ff; outline-offset: -3px; }
.micro-ops-empty--compact { min-height: 100px; border-radius: 0; }

/* Micro operations v5: decision ranking, realization need, and daily productivity. */
.micro-ops-section--ranking { --micro-section-accent: #0b78c9; }
.micro-ops-section--need { --micro-section-accent: #00a5c8; }
.micro-ranking-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.micro-ranking-toggle {
  display: inline-flex;
  flex: 0 0 auto;
  gap: 0.25rem;
  padding: 0.25rem;
  background: #dbeafa;
  border: 1px solid #a9c9e8;
  border-radius: 6px 14px 6px 14px;
}
.micro-ranking-toggle button {
  min-height: 44px;
  padding: 0.55rem 0.8rem;
  color: #34506d;
  background: transparent;
  border: 0;
  border-radius: 4px 10px 4px 10px;
  font-size: 0.73rem;
  font-weight: 900;
  line-height: 1.25;
  white-space: nowrap;
}
.micro-ranking-toggle button:hover { color: #052d68; background: rgba(255, 255, 255, 0.65); }
.micro-ranking-toggle button:focus-visible { outline: 3px solid rgba(19, 167, 226, 0.34); outline-offset: 2px; }
.micro-ranking-toggle button.active { color: #fff; background: #0754bd; box-shadow: 0 10px 22px -15px rgba(4, 42, 96, 0.95); }
.micro-ranking-panel { padding: 1rem; }
.micro-ranking-panel[hidden] { display: none !important; }
.micro-ranking-intro {
  position: relative;
  display: grid;
  grid-template-columns: 48px minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.8rem;
  min-height: 78px;
  overflow: hidden;
  padding: 0.85rem 1rem;
  color: #fff;
  background: linear-gradient(112deg, #041e49, #0754bd 68%, #0b8fd0);
  border-radius: 5px 16px 5px 16px;
}
.micro-ranking-intro::after {
  content: '';
  position: absolute;
  right: -36px;
  bottom: -72px;
  width: 160px;
  height: 160px;
  border: 25px solid rgba(255, 255, 255, 0.09);
  border-radius: 50%;
}
.micro-ranking-intro__icon {
  display: grid;
  width: 48px;
  height: 48px;
  place-items: center;
  color: #052d68;
  background: #8de4ff;
  border-radius: 4px 14px 4px 14px;
  font-size: 1.08rem;
}
.micro-ranking-intro > div { min-width: 0; }
.micro-ranking-intro strong,
.micro-ranking-intro small { display: block; position: relative; z-index: 1; overflow-wrap: anywhere; }
.micro-ranking-intro strong { color: #fff; font-size: 0.9rem; line-height: 1.3; }
.micro-ranking-intro small { margin-top: 0.16rem; color: #cceaff; font-size: 0.69rem; line-height: 1.4; }
.micro-ranking-podium { display: flex; align-items: flex-end; gap: 5px; height: 46px; padding-right: 0.5rem; }
.micro-ranking-podium i { display: block; width: 16px; background: #8de4ff; border-radius: 3px 3px 0 0; }
.micro-ranking-podium i:nth-child(1) { height: 25px; opacity: 0.68; }
.micro-ranking-podium i:nth-child(2) { height: 43px; }
.micro-ranking-podium i:nth-child(3) { height: 33px; opacity: 0.82; }
.micro-ranking-lists { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-top: 1rem; }
.micro-ranking-column { min-width: 0; }
.micro-ranking-column > header { display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.55rem; }
.micro-ranking-column > header > span {
  display: grid;
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  place-items: center;
  color: #fff;
  background: #087fc8;
  border-radius: 4px 11px 4px 11px;
}
.micro-ranking-column.is-bottom > header > span { background: #c75a08; }
.micro-ranking-column > header strong,
.micro-ranking-column > header small { display: block; }
.micro-ranking-column > header strong { color: #052d68; font-size: 0.82rem; }
.micro-ranking-column > header small { color: #62778e; font-size: 0.65rem; }
.micro-ranking-column ol { display: grid; gap: 0.5rem; margin: 0; padding: 0; list-style: none; }
.micro-ranking-column li {
  display: grid;
  grid-template-columns: 28px 40px minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.55rem;
  min-height: 68px;
  padding: 0.62rem 0.72rem;
  background: #f5faff;
  border: 1px solid #bed3e8;
  border-left: 4px solid #0b87cc;
  border-radius: 4px 12px 4px 12px;
}
.micro-ranking-column.is-bottom li { border-left-color: #d97706; background: #fffaf1; }
.micro-ranking-column li.empty { display: block; min-height: 58px; color: #64748b; font-size: 0.72rem; }
.micro-ranking-position {
  display: grid;
  width: 28px;
  height: 28px;
  place-items: center;
  color: #fff;
  background: #0754bd;
  border-radius: 4px 8px 4px 8px;
  font-size: 0.68rem;
  font-weight: 900;
}
.is-bottom .micro-ranking-position { background: #b94b06; }
.micro-ranking-avatar {
  display: grid;
  width: 40px;
  height: 40px;
  place-items: center;
  color: #0754bd;
  background: #dceeff;
  border-radius: 50%;
}
.is-bottom .micro-ranking-avatar { color: #a64206; background: #ffe7c7; }
.micro-ranking-person { min-width: 0; }
.micro-ranking-person strong,
.micro-ranking-person small { display: block; overflow-wrap: anywhere; }
.micro-ranking-person strong { color: #102c50; font-size: 0.74rem; line-height: 1.35; }
.micro-ranking-person small { margin-top: 0.1rem; color: #647991; font-size: 0.62rem; line-height: 1.35; }
.micro-ranking-column li > b { color: #0754bd; font-size: 0.76rem; font-variant-numeric: tabular-nums; white-space: nowrap; }
.micro-ranking-column.is-bottom li > b { color: #a64206; }

.micro-need-stage {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(240px, 0.8fr) 190px;
  align-items: stretch;
  min-height: 172px;
  overflow: hidden;
  color: #fff;
  background: linear-gradient(112deg, #041e49 0%, #0754bd 57%, #0aa1d5 100%);
}
.micro-need-stage::before {
  content: '';
  position: absolute;
  inset: -95px auto auto 42%;
  width: 220px;
  height: 290px;
  background: rgba(100, 220, 255, 0.12);
  transform: rotate(26deg);
}
.micro-need-stage__copy,
.micro-need-stage__daily { position: relative; z-index: 1; min-width: 0; padding: 1.15rem 1.25rem; }
.micro-need-stage__copy { display: grid; grid-template-columns: 52px minmax(0, 1fr); align-items: center; gap: 0.85rem; }
.micro-need-stage__icon {
  display: grid;
  width: 52px;
  height: 52px;
  place-items: center;
  color: #052d68;
  background: #8de4ff;
  border-radius: 5px 16px 5px 16px;
  box-shadow: 7px 7px 0 rgba(2, 28, 68, 0.3);
  font-size: 1.15rem;
}
.micro-need-stage span,
.micro-need-stage strong,
.micro-need-stage small { display: block; overflow-wrap: anywhere; }
.micro-need-stage__copy span,
.micro-need-stage__daily span { color: #a9e8ff; font-size: 0.68rem; font-weight: 900; text-transform: uppercase; }
.micro-need-stage__copy strong { margin-top: 0.18rem; color: #fff; font-size: clamp(1.35rem, 2.4vw, 2rem); line-height: 1.15; }
.micro-need-stage__copy small,
.micro-need-stage__daily small { margin-top: 0.28rem; color: #d7edff; font-size: 0.68rem; line-height: 1.45; }
.micro-need-stage__daily { display: flex; flex-direction: column; justify-content: center; background: rgba(2, 31, 74, 0.35); border-left: 1px solid rgba(177, 232, 255, 0.25); }
.micro-need-stage__daily strong { margin-top: 0.25rem; color: #8de4ff; font-size: clamp(1.08rem, 1.7vw, 1.42rem); line-height: 1.2; }
.micro-need-visual { position: relative; z-index: 1; display: grid; min-height: 150px; place-items: center; }
.micro-need-visual > span { display: grid; width: 70px; height: 70px; place-items: center; color: #0754bd; background: #fff; border-radius: 50%; font-size: 1.5rem; box-shadow: 0 15px 36px -22px rgba(0,0,0,.9); }
.micro-need-visual > i { position: absolute; display: block; width: 12px; background: #8de4ff; bottom: 30px; border-radius: 3px 3px 0 0; }
.micro-need-visual > i:nth-of-type(1) { right: 36px; height: 28px; opacity: .55; }
.micro-need-visual > i:nth-of-type(2) { right: 18px; height: 45px; opacity: .8; }
.micro-need-visual > i:nth-of-type(3) { right: 0; height: 62px; }
.micro-need-equation {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr) auto minmax(0, 1fr);
  align-items: center;
  gap: 0.6rem;
  padding: 1rem;
  background: #f4f9ff;
}
.micro-need-equation > div { min-width: 0; min-height: 104px; padding: 0.8rem 0.9rem; background: #fff; border: 1px solid #bdd3e8; border-left: 5px solid #0754bd; border-radius: 4px 13px 4px 13px; }
.micro-need-equation > div.tone-runoff { border-left-color: #0795ba; }
.micro-need-equation > div.tone-total { color: #fff; background: #063476; border-color: #063476; border-left-color: #65d7ff; }
.micro-need-equation span,
.micro-need-equation strong,
.micro-need-equation small { display: block; overflow-wrap: anywhere; }
.micro-need-equation > div > span { color: #536a82; font-size: 0.66rem; font-weight: 900; text-transform: uppercase; }
.micro-need-equation > div > strong { margin-top: 0.24rem; color: #063476; font-size: 1rem; line-height: 1.25; }
.micro-need-equation > div > small { margin-top: 0.2rem; color: #64778c; font-size: 0.62rem; line-height: 1.4; }
.micro-need-equation > div.tone-total > span,
.micro-need-equation > div.tone-total > small { color: #cce8ff; }
.micro-need-equation > div.tone-total > strong { color: #fff; }
.micro-need-operator { color: #0754bd; font-size: 1.25rem; font-weight: 900; }
.micro-ops-empty--compact small { display: block; max-width: 720px; margin-top: 0.3rem; color: #64748b; font-size: 0.7rem; line-height: 1.45; }
.micro-need-filter { display: flex; max-width: min(100%, 680px); gap: 0.35rem; overflow-x: auto; padding: 0.22rem; border: 1px solid #c9dcef; background: #f4f8fc; scrollbar-width: thin; }
.micro-need-filter button { min-height: 42px; flex: 0 0 auto; padding: 0.58rem 0.78rem; border: 1px solid transparent; color: #486079; background: transparent; font-size: 0.7rem; font-weight: 850; white-space: nowrap; transition: background-color .16s ease, color .16s ease, border-color .16s ease; }
.micro-need-filter button:hover { color: #063476; border-color: #b8d6f1; background: #fff; }
.micro-need-filter button:focus-visible { outline: 3px solid rgba(19, 167, 226, .3); outline-offset: 1px; }
.micro-need-filter button.is-active { color: #fff; border-color: #0754bd; background: #0754bd; box-shadow: 0 6px 14px -10px rgba(7, 84, 189, .8); }

.micro-pattern-card__detail.is-periodic { grid-column: 1 / -1; }
.micro-pattern-card.has-breakdown,
.micro-pattern-card.has-breakdown + .micro-pattern-card { grid-column: 1 / -1; }
.micro-pattern-card.has-breakdown + .micro-pattern-card { min-height: 104px; }
.micro-pattern-card__detail.is-one-time { grid-column: 1 / -1; }
.micro-frequency-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0.42rem; margin-top: 0.55rem; }
.micro-frequency-item { min-width: 0; padding: 0.52rem; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(191, 238, 255, 0.22); border-radius: 4px 9px 4px 9px; }
.micro-frequency-item b,
.micro-frequency-item span,
.micro-frequency-item strong { display: block; overflow-wrap: anywhere; }
.micro-frequency-item b { color: #8de4ff; font-size: 0.66rem; }
.micro-frequency-item span { margin-top: 0.12rem; color: #e1f4ff; font-size: 0.6rem; }
.micro-frequency-item strong { margin-top: 0.14rem; color: #fff; font-size: 0.68rem; white-space: normal; }

/* Collapsible freq item */
.micro-frequency-item.has-terms { padding: 0; overflow: hidden; }
.micro-freq-header { display: flex; align-items: flex-start; gap: 0.4rem; justify-content: space-between;
  width: 100%; padding: 0.52rem; background: none; border: none; cursor: pointer; text-align: left; }
.micro-freq-header:hover { background: rgba(255,255,255,0.07); }
.micro-freq-header:focus-visible { outline: 2px solid rgba(141,228,255,.6); outline-offset: -2px; }
.micro-freq-main { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.micro-freq-chevron { font-size: 0.55rem; color: #8de4ff; margin-top: 0.32rem; flex-shrink: 0;
  transition: transform 0.22s cubic-bezier(0.4,0,0.2,1); }
.micro-frequency-item.is-open > .micro-freq-header .micro-freq-chevron { transform: rotate(180deg); }
.micro-freq-term-drawer { margin: 0 0.46rem 0.46rem; border-top: 1px solid rgba(191,238,255,0.18);
  padding-top: 0.4rem; display: flex; flex-direction: column; gap: 0.25rem; }
.micro-freq-term-item { display: grid; grid-template-columns: 1fr auto;
  grid-template-rows: auto auto; column-gap: 0.4rem; row-gap: 0.04rem;
  padding: 0.3rem 0.42rem; background: rgba(255,255,255,0.07);
  border-radius: 4px 8px 4px 8px; border: 1px solid rgba(191,238,255,0.13); }
.micro-freq-term-item b { color: #a8daff; font-size: 0.6rem; grid-column: 1; grid-row: 1; }
.micro-freq-term-item span { color: #d8edff; font-size: 0.56rem; grid-column: 1; grid-row: 2; }
.micro-freq-term-item strong { color: #fff; font-size: 0.62rem; font-weight: 800;
  grid-column: 2; grid-row: 1 / span 2; align-self: center; text-align: right; white-space: nowrap; }
.micro-term-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.48rem; margin-top: 0.58rem; }
.micro-term-item { min-width: 0; display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 0.55rem; padding: 0.62rem; border: 1px solid rgba(191, 238, 255, .28); background: rgba(255,255,255,.11); }
.micro-term-item:focus-visible { outline: 3px solid rgba(141, 228, 255, .55); outline-offset: 2px; }
.micro-term-item > div { min-width: 0; }
.micro-term-item b,
.micro-term-item span,
.micro-term-item strong { display: block; overflow-wrap: break-word; word-break: normal; }
.micro-term-item b { color: #8de4ff; font-size: .68rem; }
.micro-term-item span { margin-top: .12rem; color: #e1f4ff; font-size: .6rem; line-height: 1.35; }
.micro-term-item strong { margin-top: .15rem; color: #fff; font-size: .7rem; }
.micro-term-item button { min-height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: .34rem; padding: .5rem .58rem; border: 1px solid rgba(191, 238, 255, .38); color: #073e8e; background: #bfeeff; font-size: .62rem; font-weight: 900; white-space: nowrap; }
.micro-term-item button span { color: inherit; font-size: inherit; }
.micro-term-item button:hover { background: #fff; }
.micro-term-item button:focus-visible { outline: 3px solid rgba(191, 238, 255, .52); outline-offset: 2px; }

.micro-mantri-table--summary { min-width: 1280px; }
.micro-mantri-table thead .scope-daily { background: #087fc8 !important; }
.micro-mantri-table thead .scope-cumulative { background: #0754bd !important; }
.micro-mantri-table tbody .micro-cell-daily { color: #066aa0; background-color: #edfaff; font-weight: 900; }
.micro-performance-dashboard,
.micro-ops,
.micro-ops-section,
.micro-ranking-panel,
.micro-ranking-lists,
.micro-need-stage,
.micro-pattern-grid,
.micro-burden-grid,
.micro-mantri-stage { min-width: 0; max-width: 100%; }
.micro-performance-dashboard,
.micro-ops { width: 100%; }
.micro-ops-section--inactive { --micro-section-accent: #d97706; }
.micro-inactive-head-meta {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.12rem;
  text-align: right;
}
.micro-inactive-head-meta strong { color: #063476; font-size: 1rem; font-weight: 900; }
.micro-inactive-head-meta span { color: #64748b; font-size: 0.69rem; font-weight: 750; }
.micro-inactive-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 1rem;
}
.micro-inactive-metric {
  --inactive-accent: #087fc8;
  --inactive-soft: #e5f5ff;
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr) auto;
  grid-template-areas: 'icon copy value' 'icon copy share';
  align-items: center;
  gap: 0.2rem 0.7rem;
  min-width: 0;
  min-height: 94px;
  padding: 0.82rem;
  overflow: hidden;
  background: linear-gradient(135deg, #fff, var(--inactive-soft));
  border: 1px solid #bfd4e8;
  border-left: 5px solid var(--inactive-accent);
  border-radius: 5px 16px 5px 16px;
}
.micro-inactive-metric.tone-month_3 { --inactive-accent: #d97706; --inactive-soft: #fff5df; }
.micro-inactive-metric.tone-month_6 { --inactive-accent: #c92f47; --inactive-soft: #fff0f2; }
.micro-inactive-metric.is-disabled { filter: saturate(0.35); opacity: 0.72; }
.micro-inactive-metric__icon {
  grid-area: icon;
  display: grid;
  width: 44px;
  height: 44px;
  place-items: center;
  color: #fff;
  background: var(--inactive-accent);
  border-radius: 4px 13px 4px 13px;
}
.micro-inactive-metric > div { grid-area: copy; min-width: 0; }
.micro-inactive-metric > div span,
.micro-inactive-metric > div strong { display: block; overflow-wrap: anywhere; }
.micro-inactive-metric > div span { color: #64748b; font-size: 0.62rem; font-weight: 750; text-transform: uppercase; }
.micro-inactive-metric > div strong { margin-top: 0.12rem; color: #16324f; font-size: 0.78rem; line-height: 1.28; }
.micro-inactive-metric > b { grid-area: value; color: var(--inactive-accent); font-size: 1.5rem; font-weight: 950; line-height: 1; font-variant-numeric: tabular-nums; }
.micro-inactive-metric > small { grid-area: share; color: #52677d; font-size: 0.65rem; font-weight: 800; text-align: right; }
.micro-inactive-table-wrap { width: calc(100% - 2rem); margin: 0 1rem 1rem; overflow-x: auto; border: 1px solid #c9d8e7; }
.micro-inactive-table { width: 100%; min-width: 680px; border-collapse: collapse; color: #29425c; font-size: 0.74rem; }
.micro-inactive-table th,
.micro-inactive-table td { padding: 0.72rem 0.78rem; border-right: 1px solid #dce6ef; border-bottom: 1px solid #dce6ef; text-align: center; }
.micro-inactive-table th:last-child,
.micro-inactive-table td:last-child { border-right: 0; }
.micro-inactive-table thead th { color: #fff; background: #063476; font-size: 0.67rem; text-transform: uppercase; }
.micro-inactive-table tbody th { color: #103a68; background: #edf5fd; text-align: left; }
.micro-inactive-table tbody tr:last-child th,
.micro-inactive-table tbody tr:last-child td { border-bottom: 0; }
.micro-inactive-table td strong,
.micro-inactive-table td small { display: block; }
.micro-inactive-table td strong { color: #0a55a6; font-size: 0.84rem; }
.micro-inactive-table td small { margin-top: 0.12rem; color: #6b7f92; font-size: 0.62rem; }
.micro-inactive-details {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 0 1rem 1rem;
}
.micro-inactive-detail { --inactive-accent: #087fc8; min-width: 0; border: 1px solid #c7d8e8; border-top: 4px solid var(--inactive-accent); background: #fff; }
.micro-inactive-detail.tone-month_3 { --inactive-accent: #d97706; }
.micro-inactive-detail.tone-month_6 { --inactive-accent: #c92f47; }
.micro-inactive-detail summary {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto 18px;
  align-items: center;
  gap: 0.55rem;
  min-height: 52px;
  padding: 0.68rem 0.75rem;
  color: #183753;
  background: #f7fbff;
  cursor: pointer;
  list-style: none;
}
.micro-inactive-detail summary::-webkit-details-marker { display: none; }
.micro-inactive-detail summary:focus-visible { outline: 3px solid rgba(8, 127, 200, 0.3); outline-offset: -3px; }
.micro-inactive-detail summary span { min-width: 0; overflow-wrap: anywhere; font-size: 0.7rem; font-weight: 850; }
.micro-inactive-detail summary strong { color: var(--inactive-accent); font-size: 0.73rem; white-space: nowrap; }
.micro-inactive-detail summary i { color: #6d8297; transition: transform .16s ease; }
.micro-inactive-detail[open] summary i { transform: rotate(180deg); }
.micro-inactive-detail ol { max-height: 330px; margin: 0; padding: 0; overflow-y: auto; list-style: none; }
.micro-inactive-detail li {
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.55rem;
  min-width: 0;
  padding: 0.62rem 0.7rem;
  border-top: 1px solid #e1e9f1;
}
.micro-inactive-avatar { display: grid; width: 34px; height: 34px; place-items: center; color: #0754bd; background: #e1effd; border-radius: 4px 10px 4px 10px; }
.micro-inactive-detail li > div { min-width: 0; }
.micro-inactive-detail li strong,
.micro-inactive-detail li small { display: block; overflow-wrap: anywhere; }
.micro-inactive-detail li strong { color: #173754; font-size: 0.7rem; line-height: 1.3; }
.micro-inactive-detail li small { margin-top: 0.12rem; color: #708398; font-size: 0.6rem; line-height: 1.35; }
.micro-inactive-category { padding: 0.24rem 0.38rem; color: #0754bd; background: #e8f2fd; font-size: 0.58rem; font-weight: 850; white-space: nowrap; }
.micro-inactive-detail li.is-empty { display: block; color: #708398; font-size: 0.68rem; text-align: center; }

/* Pipeline Mikro: executive summary plus an on-demand nominative browser. */
.micro-ops-section--pipeline { --micro-section-accent: #008a72; }
.micro-pipeline-head-actions { display: flex; min-width: 0; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .55rem; }
.micro-pipeline-sync { display: inline-flex; align-items: center; gap: .38rem; color: #60758a; font-size: .75rem; font-weight: 750; }
.micro-pipeline-open { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: .42rem; padding: .62rem .82rem; border: 1px solid #007b68; color: #fff; background: #007b68; font-size: .78rem; font-weight: 900; }
.micro-pipeline-open:hover { background: #006353; }
.micro-pipeline-open:focus-visible { outline: 3px solid rgba(0, 138, 114, .28); outline-offset: 2px; }
.micro-pipeline-dataset-title { display: flex; align-items: baseline; gap: .55rem; padding: .8rem 1rem 0; color: #60758a; }
.micro-pipeline-dataset-title span { padding: .22rem .42rem; color: #fff; background: #0754bd; font-size: .62rem; font-weight: 950; letter-spacing: .08em; }
.micro-pipeline-dataset-title strong { color: #173f66; font-size: .78rem; font-weight: 850; }
.micro-pipeline-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .7rem; padding: 1rem; }
.micro-pipeline-kpis article { --pipeline-tone: #0754bd; min-width: 0; min-height: 102px; padding: .78rem .82rem; border: 1px solid #ccdae7; border-top: 4px solid var(--pipeline-tone); background: #fff; }
.micro-pipeline-kpis .tone-done { --pipeline-tone: #008a72; }
.micro-pipeline-kpis .tone-plan { --pipeline-tone: #d97706; }
.micro-pipeline-kpis .tone-pending { --pipeline-tone: #c92f47; }
.micro-pipeline-kpis .tone-real { --pipeline-tone: #00a5c8; }
.micro-pipeline-kpis span,
.micro-pipeline-kpis strong,
.micro-pipeline-kpis small { display: block; min-width: 0; overflow-wrap: anywhere; }
.micro-pipeline-kpis span { color: #65788d; font-size: .72rem; font-weight: 850; text-transform: uppercase; }
.micro-pipeline-kpis strong { margin-top: .22rem; color: var(--pipeline-tone); font-size: clamp(1.15rem, 1.7vw, 1.5rem); font-weight: 950; line-height: 1.05; font-variant-numeric: tabular-nums; }
.micro-pipeline-kpis small { margin-top: .3rem; color: #51677d; font-size: .72rem; font-weight: 700; line-height: 1.4; }
.micro-slik-card { position: relative; display: grid; grid-template-columns: minmax(150px, .65fr) minmax(260px, 1fr) minmax(260px, 1.35fr) auto; align-items: center; gap: .85rem; min-width: 0; margin: 0 1rem 1rem; padding: .82rem; overflow: hidden; color: #fff; background: linear-gradient(118deg, #063476 0%, #0754bd 52%, #008a72 100%); box-shadow: 0 10px 24px rgba(4, 52, 118, .14); }
.micro-slik-card::after { position: absolute; width: 180px; height: 180px; right: -80px; top: -105px; border: 24px solid rgba(145, 221, 237, .16); border-radius: 50%; content: ''; pointer-events: none; }
.micro-slik-card__identity { display: flex; align-items: center; gap: .65rem; min-width: 0; }
.micro-slik-card__icon { display: grid; width: 42px; height: 42px; flex: 0 0 42px; place-items: center; color: #063476; background: #91dded; font-size: 1rem; }
.micro-slik-card__identity span, .micro-slik-card__identity h4 { display: block; min-width: 0; }
.micro-slik-card__identity div > span { color: #91dded; font-size: .62rem; font-weight: 950; letter-spacing: .09em; }
.micro-slik-card__identity h4 { margin: .14rem 0 0; color: #fff; font-size: 1rem; font-weight: 950; }
.micro-slik-card__metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); min-width: 0; border: 1px solid rgba(255,255,255,.18); background: rgba(1,35,80,.22); }
.micro-slik-card__metrics > div { min-width: 0; padding: .5rem .58rem; border-right: 1px solid rgba(255,255,255,.16); }
.micro-slik-card__metrics > div:last-child { border-right: 0; }
.micro-slik-card__metrics span, .micro-slik-card__metrics strong { display: block; overflow-wrap: anywhere; }
.micro-slik-card__metrics span { color: #bfeaf3; font-size: .58rem; font-weight: 850; text-transform: uppercase; }
.micro-slik-card__metrics strong { margin-top: .12rem; color: #fff; font-size: .8rem; font-weight: 950; font-variant-numeric: tabular-nums; }
.micro-slik-card__branches { display: grid; gap: .3rem; min-width: 0; }
.micro-slik-branch { display: grid; grid-template-columns: minmax(78px, .75fr) minmax(80px, 1fr) 34px minmax(74px, auto); align-items: center; gap: .42rem; min-width: 0; font-size: .62rem; }
.micro-slik-branch > span { overflow: hidden; color: #fff; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
.micro-slik-branch > div { height: 5px; overflow: hidden; background: rgba(255,255,255,.2); border-radius: 99px; }
.micro-slik-branch > div i { display: block; height: 100%; background: #91dded; border-radius: inherit; }
.micro-slik-branch strong, .micro-slik-branch small { color: #fff; font-weight: 850; font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
.micro-slik-branch small { color: #cdeff5; }
.micro-slik-card__open { position: relative; z-index: 1; min-height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: .35rem; padding: .55rem .7rem; border: 1px solid rgba(255,255,255,.5); color: #063476; background: #fff; font-size: .68rem; font-weight: 950; }
.micro-slik-card__open:hover { background: #91dded; }
.micro-pipeline-overview { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.25fr); gap: .85rem; padding: 0 1rem 1rem; }
.micro-pipeline-panel { min-width: 0; border: 1px solid #c9d9e7; background: #fff; }
.micro-pipeline-panel__head { min-height: 58px; display: flex; min-width: 0; align-items: center; justify-content: space-between; gap: .8rem; padding: .68rem .8rem; border-bottom: 1px solid #d8e3ed; background: #f5f9fd; }
.micro-pipeline-panel__head > div { min-width: 0; }
.micro-pipeline-panel__head span,
.micro-pipeline-panel__head h4,
.micro-pipeline-panel__head small { display: block; overflow-wrap: anywhere; }
.micro-pipeline-panel__head span { color: #008a72; font-size: .7rem; font-weight: 900; }
.micro-pipeline-panel__head h4 { margin: .12rem 0 0; color: #143b61; font-size: .95rem; font-weight: 900; }
.micro-pipeline-panel__head > small { color: #687d91; font-size: .72rem; font-weight: 750; text-align: right; }
.micro-pipeline-group-list { padding: .15rem .8rem .45rem; }
.micro-pipeline-group-row { display: grid; grid-template-columns: minmax(150px, 1fr) minmax(110px, 1fr) 95px; align-items: center; gap: .65rem; min-width: 0; padding: .58rem 0; border-bottom: 1px solid #e3ebf2; }
.micro-pipeline-group-row:last-child { border-bottom: 0; }
.micro-pipeline-group-row__identity,
.micro-pipeline-group-row__status { min-width: 0; }
.micro-pipeline-group-row__identity strong,
.micro-pipeline-group-row__identity small,
.micro-pipeline-group-row__status b,
.micro-pipeline-group-row__status span { display: block; overflow-wrap: anywhere; }
.micro-pipeline-group-row__identity strong { color: #183c5d; font-size: .78rem; font-weight: 850; }
.micro-pipeline-group-row__identity small { margin-top: .1rem; color: #6a7e91; font-size: .68rem; font-weight: 650; }
.micro-pipeline-group-row__status { text-align: right; }
.micro-pipeline-group-row__status b { color: #008a72; font-size: .82rem; font-variant-numeric: tabular-nums; font-weight: 900; }
.micro-pipeline-group-row__status span { color: #718397; font-size: .66rem; font-weight: 650; }

.micro-pipeline-segmented-bar { height: 7px; overflow: hidden; background: #e6eef5; border-radius: 999px; display: flex; width: 100%; min-width: 0; }
.micro-pipeline-segmented-bar .is-done { height: 100%; background: linear-gradient(90deg, #008a72, #00a5c8); transition: width 0.3s ease; }
.micro-pipeline-segmented-bar .is-plan { height: 100%; background: #d97706; transition: width 0.3s ease; }

/* Interactive Sumber Pipeline Accordion */
.micro-pipeline-source-accordion { padding: .45rem .75rem .65rem; display: flex; flex-direction: column; gap: .42rem; }
.micro-pipeline-source-card { border: 1px solid #d4e2ed; border-radius: 6px; background: #fbfdff; transition: all .2s ease; overflow: hidden; }
.micro-pipeline-source-card:hover { border-color: #a8cae6; box-shadow: 0 3px 10px rgba(7, 84, 189, .06); }
.micro-pipeline-source-card.is-open { border-color: #0754bd; background: #fff; box-shadow: 0 4px 14px rgba(7, 84, 189, .1); }
.micro-pipeline-source-card__actions { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: stretch; min-width: 0; }
.micro-pipeline-source-btn { display: grid; grid-template-columns: 28px minmax(0, 1fr) auto; align-items: center; gap: .65rem; width: 100%; padding: .55rem .65rem; background: none; border: none; cursor: pointer; text-align: left; transition: background .15s ease; }
.micro-pipeline-source-btn:hover { background: rgba(7, 84, 189, .03); }
.micro-pipeline-source-btn:focus-visible { outline: 2px solid #0754bd; outline-offset: -2px; }
.micro-pipeline-source-detail { display: inline-flex; min-width: 112px; align-items: center; justify-content: center; gap: .34rem; padding: .55rem .6rem; border: 0; border-left: 1px solid #d4e2ed; color: #0754bd; background: #edf6fd; cursor: pointer; font-size: .66rem; font-weight: 900; line-height: 1.2; text-align: center; transition: background .15s ease, color .15s ease; }
.micro-pipeline-source-detail:hover { color: #fff; background: #0754bd; }
.micro-pipeline-source-detail:focus-visible { outline: 3px solid rgba(7, 84, 189, .3); outline-offset: -3px; }

.micro-pipeline-source-card__rank { display: grid; width: 28px; height: 28px; place-items: center; color: #fff; background: #0754bd; font-size: .68rem; font-weight: 900; border-radius: 4px; }
.micro-pipeline-source-card.is-open .micro-pipeline-source-card__rank { background: #008a72; }

.micro-pipeline-source-card__main { display: flex; flex-direction: column; gap: .25rem; min-width: 0; }
.micro-pipeline-source-card__title { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .4rem; }
.micro-pipeline-source-card__title strong { color: #143b61; font-size: .78rem; font-weight: 900; }
.micro-pipeline-source-card__title small { color: #62778c; font-size: .68rem; font-weight: 700; }
.micro-pipeline-source-card__stats { color: #6c8094; font-size: .67rem; }
.micro-pipeline-source-card__stats b { color: #008a72; font-weight: 850; }

.micro-source-chevron { font-size: .65rem; color: #768d9f; transition: transform .22s cubic-bezier(.4, 0, .2, 1); }
.micro-pipeline-source-card.is-open .micro-source-chevron { transform: rotate(180deg); color: #0754bd; }

/* Executive Summary Drawer */
.micro-pipeline-source-drawer { border-top: 1px solid #e1ebf4; padding: .65rem .75rem .75rem; background: #f8fbfe; display: flex; flex-direction: column; gap: .6rem; }
.micro-source-drawer__kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .4rem; }
.micro-source-drawer__kpi { padding: .45rem .5rem; border: 1px solid #d4e2ed; border-radius: 4px; background: #fff; }
.micro-source-drawer__kpi.is-done { border-top: 3px solid #008a72; }
.micro-source-drawer__kpi.is-plan { border-top: 3px solid #d97706; }
.micro-source-drawer__kpi.is-pending { border-top: 3px solid #c92f47; }
.micro-source-drawer__kpi.is-potensi { border-top: 3px solid #0754bd; }
.micro-source-drawer__kpi span { display: block; color: #687e93; font-size: .58rem; font-weight: 850; text-transform: uppercase; }
.micro-source-drawer__kpi strong { display: block; margin-top: .15rem; color: #143b61; font-size: .84rem; font-weight: 900; }
.micro-source-drawer__kpi small { display: block; margin-top: .1rem; color: #73899d; font-size: .62rem; }

.micro-source-drawer__section { display: flex; flex-direction: column; gap: .3rem; }
.micro-source-drawer__section-head { display: flex; align-items: center; justify-content: space-between; color: #0754bd; font-size: .68rem; font-weight: 900; text-transform: uppercase; }
.micro-source-subgrid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .35rem; }
.micro-source-subitem { display: flex; flex-direction: column; gap: .22rem; padding: .4rem .52rem; border: 1px solid #dce7f0; border-radius: 4px; background: #fff; }
.micro-source-subitem__title { display: flex; flex-direction: column; }
.micro-source-subitem__title b { color: #193f65; font-size: .68rem; line-height: 1.25; }
.micro-source-subitem__title span { color: #6b8094; font-size: .6rem; }
.micro-source-subitem__progress { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: .45rem; margin-top: .12rem; }
.micro-source-subitem__progress strong { color: #008a72; font-size: .68rem; font-weight: 900; }
.micro-pipeline-empty { display: flex; align-items: center; gap: .7rem; min-height: 112px; margin: 1rem; padding: 1rem; color: #526b82; background: #f2f7fb; border: 1px dashed #b7cce0; }
.micro-pipeline-empty > i { color: #008a72; font-size: 1.5rem; }
.micro-pipeline-empty strong,
.micro-pipeline-empty span { display: block; }
.micro-pipeline-empty strong { color: #183b5c; font-size: .8rem; }
.micro-pipeline-empty span { margin-top: .18rem; font-size: .68rem; }
body.micro-pipeline-modal-open { overflow: hidden; }
.micro-pipeline-modal[hidden] { display: none !important; }
.micro-pipeline-modal { position: fixed; z-index: 1095; inset: 0; display: grid; place-items: center; padding: 1rem; }
.micro-pipeline-modal__backdrop { position: absolute; inset: 0; background: rgba(3, 26, 54, .76); backdrop-filter: blur(4px); }
.micro-pipeline-modal__dialog { position: relative; display: grid; grid-template-rows: auto auto auto minmax(0, 1fr) auto; width: min(1500px, 96vw); height: min(880px, 94vh); overflow: hidden; color: #263d55; background: #fff; border: 1px solid #9fb9d1; box-shadow: 0 24px 70px rgba(0, 20, 47, .35); }
.micro-pipeline-modal__dialog > header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; color: #fff; background: #063476; }
.micro-pipeline-modal__dialog > header span,
.micro-pipeline-modal__dialog > header h3 { display: block; }
.micro-pipeline-modal__dialog > header span { color: #91dded; font-size: .61rem; font-weight: 900; }
.micro-pipeline-modal__dialog > header h3 { margin: .12rem 0 0; color: #fff; font-size: 1.02rem; }
.micro-pipeline-modal__dialog > header > div:last-child { display: flex; gap: .42rem; }
.micro-pipeline-modal__dialog > header a,
.micro-pipeline-modal__dialog > header button,
.micro-pipeline-modal__dialog > footer button { width: 42px; height: 42px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,.35); color: #fff; background: rgba(255,255,255,.1); }
.micro-pipeline-filters { display: grid; grid-template-columns: minmax(120px, .55fr) minmax(135px, .65fr) minmax(160px, .8fr) minmax(150px, .75fr) minmax(220px, 1.2fr) auto; align-items: end; gap: .7rem; padding: .72rem 1rem; border-bottom: 1px solid #d7e2ec; background: #f5f9fd; }
.micro-pipeline-filters label { display: grid; min-width: 0; gap: .26rem; }
.micro-pipeline-filters label > span { color: #526a80; font-size: .68rem; font-weight: 850; text-transform: uppercase; }
.micro-pipeline-filters select,
.micro-pipeline-filters input { width: 100%; min-width: 0; min-height: 44px; padding: .55rem .65rem; border: 1px solid #b9cde0; color: #193b5b; background: #fff; font-size: .78rem; font-weight: 700; }
.micro-pipeline-filters > button { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: .4rem; padding: .58rem .84rem; border: 1px solid #0754bd; color: #fff; background: #0754bd; font-size: .78rem; font-weight: 900; }
.micro-pipeline-modal__meta { display: flex; align-items: center; justify-content: space-between; gap: .7rem; padding: .5rem 1rem; color: #5e7388; background: #fff; border-bottom: 1px solid #dce5ed; font-size: .72rem; font-weight: 800; }
.micro-pipeline-modal__table-wrap { min-height: 0; overflow: auto; }
.micro-pipeline-modal__table { width: 100%; min-width: 1180px; border-collapse: separate; border-spacing: 0; table-layout: fixed; font-size: .75rem; }
.micro-pipeline-modal__table th,
.micro-pipeline-modal__table td { min-width: 0; padding: .65rem .7rem; border-right: 1px solid #dce5ed; border-bottom: 1px solid #dce5ed; vertical-align: top; overflow-wrap: anywhere; line-height: 1.4; }
.micro-pipeline-modal__table th { position: sticky; z-index: 2; top: 0; color: #fff; background: #0754bd; font-size: .7rem; text-align: left; text-transform: uppercase; }
.micro-pipeline-modal__table th:nth-child(1) { width: 16%; }
.micro-pipeline-modal__table th:nth-child(2) { width: 16%; }
.micro-pipeline-modal__table th:nth-child(3) { width: 12%; }
.micro-pipeline-modal__table th:nth-child(4) { width: 11%; }
.micro-pipeline-modal__table th:nth-child(5) { width: 10%; }
.micro-pipeline-modal__table th:nth-child(6) { width: 21%; }
.micro-pipeline-modal__table th:nth-child(7) { width: 14%; }
.micro-pipeline-modal__table tbody tr:nth-child(even) { background: #f6f9fc; }
.micro-pipeline-modal__table td strong,
.micro-pipeline-modal__table td small { display: block; overflow-wrap: anywhere; }
.micro-pipeline-modal__table td strong { color: #153c61; }
.micro-pipeline-modal__table td small { margin-top: .14rem; color: #6b7e91; font-size: .68rem; }
.micro-pipeline-modal__table .is-loading,
.micro-pipeline-modal__table .is-empty { padding: 2.2rem; color: #657b90; text-align: center; }
.micro-pipeline-modal__dialog > footer { display: flex; align-items: center; justify-content: space-between; gap: .7rem; padding: .55rem 1rem; color: #536a80; background: #eef4f9; font-size: .72rem; font-weight: 800; }
.micro-pipeline-modal__dialog > footer > div { display: flex; gap: .35rem; }
.micro-pipeline-modal__dialog > footer button { color: #0754bd; border-color: #aec6dc; background: #fff; }
.micro-pipeline-modal__dialog > footer button:disabled { color: #9baaba; background: #eef2f5; cursor: not-allowed; }
body.micro-pipeline-source-modal-open { overflow: hidden; }
.micro-pipeline-source-modal[hidden] { display: none !important; }
.micro-pipeline-source-modal { position: fixed; z-index: 1100; inset: 0; display: grid; place-items: center; padding: 1rem; }
.micro-pipeline-source-modal__backdrop { position: absolute; inset: 0; background: rgba(3, 26, 54, .76); backdrop-filter: blur(4px); }
.micro-pipeline-source-modal__dialog { position: relative; display: grid; grid-template-rows: auto auto minmax(0, 1fr) auto; width: min(1180px, 96vw); max-height: min(760px, 92dvh); overflow: hidden; color: #263d55; background: #fff; border: 1px solid #9fb9d1; box-shadow: 0 24px 70px rgba(0, 20, 47, .35); }
.micro-pipeline-source-modal__dialog > header { display: flex; align-items: center; justify-content: space-between; gap: .8rem; padding: .8rem 1rem; color: #fff; background: #063476; }
.micro-pipeline-source-modal__dialog > header span,
.micro-pipeline-source-modal__dialog > header h3 { display: block; }
.micro-pipeline-source-modal__dialog > header span { color: #91dded; font-size: .64rem; font-weight: 900; }
.micro-pipeline-source-modal__dialog > header h3 { margin: .12rem 0 0; color: #fff; font-size: 1.04rem; font-weight: 900; overflow-wrap: anywhere; }
.micro-pipeline-source-modal__dialog > header button { width: 42px; height: 42px; display: grid; flex: 0 0 auto; place-items: center; border: 1px solid rgba(255,255,255,.35); color: #fff; background: rgba(255,255,255,.1); }
.micro-pipeline-source-modal__summary { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .55rem; padding: .75rem 1rem; border-bottom: 1px solid #dce5ed; background: #f5f9fd; }
.micro-pipeline-source-modal__summary > div { min-width: 0; padding: .5rem .58rem; border: 1px solid #d6e3ef; border-top: 3px solid #0754bd; background: #fff; }
.micro-pipeline-source-modal__summary > div:nth-child(2) { border-top-color: #008a72; }
.micro-pipeline-source-modal__summary > div:nth-child(3) { border-top-color: #d97706; }
.micro-pipeline-source-modal__summary > div:nth-child(4) { border-top-color: #c92f47; }
.micro-pipeline-source-modal__summary > div:nth-child(5) { border-top-color: #00a5c8; }
.micro-pipeline-source-modal__summary span,
.micro-pipeline-source-modal__summary strong { display: block; min-width: 0; overflow-wrap: anywhere; }
.micro-pipeline-source-modal__summary span { color: #65798e; font-size: .62rem; font-weight: 850; text-transform: uppercase; }
.micro-pipeline-source-modal__summary strong { margin-top: .18rem; color: #173d60; font-size: .93rem; font-weight: 950; font-variant-numeric: tabular-nums; }
.micro-pipeline-source-modal__table-wrap { min-height: 0; overflow: auto; }
.micro-pipeline-source-modal__table { width: 100%; min-width: 900px; border-collapse: separate; border-spacing: 0; font-size: .78rem; }
.micro-pipeline-source-modal__table th,
.micro-pipeline-source-modal__table td { padding: .68rem .72rem; border-right: 1px solid #dce5ed; border-bottom: 1px solid #dce5ed; vertical-align: middle; line-height: 1.35; }
.micro-pipeline-source-modal__table th { position: sticky; z-index: 2; top: 0; color: #fff; background: #0754bd; font-size: .68rem; font-weight: 900; text-align: left; text-transform: uppercase; }
.micro-pipeline-source-modal__table th:nth-child(1), .micro-pipeline-source-modal__table td:nth-child(1) { width: 52px; text-align: center; }
.micro-pipeline-source-modal__table th:nth-child(2) { min-width: 190px; }
.micro-pipeline-source-modal__table tbody tr:nth-child(even) { background: #f7fafc; }
.micro-pipeline-source-modal__table td:nth-child(n+3):nth-child(-n+6) { color: #173d60; font-weight: 850; font-variant-numeric: tabular-nums; text-align: right; }
.micro-pipeline-source-modal__table td:last-child { color: #173d60; font-weight: 850; font-variant-numeric: tabular-nums; text-align: right; }
.micro-pipeline-source-progress { display: grid; grid-template-columns: minmax(80px, 1fr) auto; align-items: center; gap: .5rem; min-width: 130px; }
.micro-pipeline-source-progress .micro-pipeline-segmented-bar { height: 8px; }
.micro-pipeline-source-progress strong { color: #008a72; font-size: .78rem; font-variant-numeric: tabular-nums; }
.micro-pipeline-source-modal__empty { padding: 2rem !important; color: #667d92; text-align: center; }
.micro-pipeline-source-modal__dialog > footer { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .65rem 1rem; color: #536a80; background: #eef4f9; font-size: .74rem; font-weight: 800; }
.micro-pipeline-source-modal__dialog > footer button { min-height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: .4rem; padding: .55rem .75rem; border: 1px solid #007b68; color: #fff; background: #007b68; font-size: .72rem; font-weight: 900; }
.micro-pipeline-source-modal__dialog > footer button:hover { background: #006353; }

@media (max-width: 1100px) {
  .micro-realization-layout { grid-template-columns: minmax(0, 1fr); }
  .micro-realization-type-card.type-nett { grid-template-columns: auto minmax(0, 1fr); }
  .micro-nett-type-list { grid-column: 1 / -1; }
  .micro-need-stage { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-need-visual { display: none; }
  .micro-frequency-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .micro-term-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-inactive-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-inactive-metric:last-child { grid-column: 1 / -1; }
  .micro-inactive-details { grid-template-columns: 1fr; }
  .micro-pipeline-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .micro-slik-card { grid-template-columns: minmax(180px, .65fr) minmax(320px, 1.35fr) auto; }
  .micro-slik-card__branches { grid-column: 1 / -1; grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-pipeline-overview { grid-template-columns: minmax(0, 1fr); }
  .micro-pipeline-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-pipeline-source-modal__summary { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 767.98px) {
  .micro-ops-section__head,
  .micro-ops-section__head > div:first-child,
  .micro-ranking-toggle { min-width: 0; max-width: 100%; width: 100%; }
  .micro-realization-type-grid--primary { grid-template-columns: 1fr; }
  .micro-realization-type-card.type-plafond,
  .micro-realization-type-card.type-nett { min-height: 132px; }
  .micro-nett-type-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-mantri-stage { min-height: 0; grid-template-columns: 1fr; }
  .micro-mantri-stage__copy { padding: 1.1rem; }
  .micro-mantri-stage__visual { min-height: 150px; }
  .micro-mantri-stage__visual svg { height: 145px; }
  .micro-tier-panels { padding: 0.75rem; }
  .micro-mantri-table th,
  .micro-mantri-table td { padding: 0.62rem 0.56rem; }
  .micro-pattern-card { gap: 0.55rem; }
  .micro-pattern-card__copy strong { font-size: 1rem; white-space: nowrap; }
  .micro-pattern-card b { font-size: 1.12rem; }
  .micro-pattern-card__breakdown { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-ranking-head { align-items: stretch; flex-direction: column; }
  .micro-ranking-toggle { display: grid; width: 100%; grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-ranking-toggle button { min-width: 0; overflow-wrap: anywhere; white-space: normal; }
  .micro-ranking-panel { padding: 0.75rem; }
  .micro-ranking-intro { grid-template-columns: 44px minmax(0, 1fr); }
  .micro-ranking-intro__icon { width: 44px; height: 44px; }
  .micro-ranking-podium { display: none; }
  .micro-ranking-lists { grid-template-columns: 1fr; }
  .micro-ranking-column li { grid-template-columns: 28px 40px minmax(0, 1fr); }
  .micro-ranking-person { grid-column: 3; }
  .micro-ranking-column li > b { grid-column: 3; grid-row: 2; justify-self: start; overflow-wrap: anywhere; white-space: normal; }
  .micro-need-stage { grid-template-columns: 1fr; }
  .micro-need-filter { width: 100%; max-width: 100%; }
  .micro-need-stage__daily { border-top: 1px solid rgba(177, 232, 255, 0.25); border-left: 0; }
  .micro-need-equation { grid-template-columns: 1fr; padding: 0.75rem; }
  .micro-need-operator { text-align: center; line-height: 1; }
  .micro-frequency-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-term-grid { grid-template-columns: 1fr; }
  .micro-inactive-head-meta { width: 100%; align-items: flex-start; text-align: left; }
  .micro-inactive-metrics { grid-template-columns: 1fr; padding: 0.75rem; }
  .micro-inactive-metric:last-child { grid-column: auto; }
  .micro-inactive-table-wrap { width: calc(100% - 1.5rem); margin: 0 0.75rem 0.75rem; }
  .micro-inactive-details { padding: 0 0.75rem 0.75rem; }
  .micro-pattern-card { grid-template-columns: 44px minmax(0, 1fr); }
  .micro-pattern-card > b { grid-column: 2; grid-row: 2; justify-self: start; }
  .micro-burden-card__head { align-items: flex-start; flex-wrap: wrap; }
  .micro-burden-card__head small { width: 100%; margin-left: 0; }
  .micro-burden-list li { grid-template-columns: 24px minmax(0, 1fr); }
  .micro-burden-list .micro-delta { grid-column: 2; justify-self: start; overflow-wrap: anywhere; white-space: normal; }
  .micro-mantri-stage__chips span { min-width: 0; max-width: 100%; overflow-wrap: anywhere; white-space: normal; }
  .micro-pipeline-head-actions { width: 100%; align-items: stretch; justify-content: flex-start; }
  .micro-pipeline-open { width: 100%; }
  .micro-pipeline-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: .75rem; }
  .micro-pipeline-kpis article:last-child { grid-column: 1 / -1; }
  .micro-pipeline-dataset-title { padding: .75rem .75rem 0; }
  .micro-slik-card { grid-template-columns: 1fr; margin: 0 .75rem .75rem; padding: .75rem; }
  .micro-slik-card__metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-slik-card__metrics > div:nth-child(2) { border-right: 0; }
  .micro-slik-card__metrics > div:nth-child(-n+2) { border-bottom: 1px solid rgba(255,255,255,.16); }
  .micro-slik-card__branches { grid-column: auto; grid-template-columns: 1fr; }
  .micro-slik-card__open { width: 100%; }
  .micro-pipeline-overview { padding: 0 .75rem .75rem; }
  .micro-pipeline-group-row { grid-template-columns: minmax(0, 1fr) 62px; }
  .micro-pipeline-progress { grid-column: 1 / -1; grid-row: 2; }
  .micro-pipeline-group-row__status { grid-column: 2; grid-row: 1; }
  .micro-pipeline-panel--actions { margin: 0 .75rem .75rem; }
  .micro-pipeline-modal { padding: 0; }
  .micro-pipeline-modal__dialog { width: 100vw; height: 100dvh; }
  .micro-pipeline-source-card__actions { grid-template-columns: 1fr; }
  .micro-pipeline-source-detail { min-height: 44px; border-top: 1px solid #d4e2ed; border-left: 0; }
  .micro-pipeline-source-modal { padding: 0; }
  .micro-pipeline-source-modal__dialog { width: 100vw; max-height: 100dvh; height: 100dvh; }
  .micro-pipeline-source-modal__summary { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: .65rem .75rem; }
  .micro-pipeline-source-modal__summary > div:last-child { grid-column: 1 / -1; }
  .micro-pipeline-source-modal__dialog > footer { align-items: stretch; flex-direction: column; }
  .micro-pipeline-source-modal__dialog > footer button { width: 100%; }
  .micro-pipeline-filters { grid-template-columns: minmax(0, 1fr); }
  .micro-pipeline-filters > button { width: 100%; }
}
@media (max-width: 419.98px) {
  .micro-realization-type-card.type-nett { grid-template-columns: 1fr; }
  .micro-realization-type-card.type-nett .micro-realization-type-card__icon { grid-row: auto; }
  .micro-nett-type-list { grid-column: auto; grid-template-columns: 1fr; }
  .micro-mantri-stage__chips { display: grid; grid-template-columns: 1fr; }
  .micro-ranking-column li { grid-template-columns: 28px 36px minmax(0, 1fr); }
  .micro-ranking-avatar { width: 36px; height: 36px; }
  .micro-ranking-column li > b { grid-column: 3; grid-row: 2; justify-self: start; white-space: normal; }
  .micro-need-stage__copy { grid-template-columns: 46px minmax(0, 1fr); padding: 1rem; }
  .micro-need-stage__icon { width: 46px; height: 46px; }
  .micro-term-item { grid-template-columns: 1fr; }
  .micro-term-item button { width: 100%; }
  .micro-inactive-metric { grid-template-columns: 40px minmax(0, 1fr) auto; padding: 0.7rem; }
  .micro-inactive-metric__icon { width: 40px; height: 40px; }
  .micro-inactive-metric > b { font-size: 1.25rem; }
  .micro-inactive-detail li { grid-template-columns: 32px minmax(0, 1fr); }
  .micro-inactive-category { grid-column: 2; justify-self: start; }
  .micro-slik-branch { grid-template-columns: minmax(0, 1fr) auto; }
  .micro-slik-branch > span { grid-column: 1; grid-row: 1; }
  .micro-slik-branch > div { grid-column: 1; grid-row: 2; }
  .micro-slik-branch > strong { grid-column: 2; grid-row: 1; }
  .micro-slik-branch > small { grid-column: 2; grid-row: 2; }
}
@media (max-width: 319.98px) {
  .micro-ops { padding: 0.5rem; }
  .micro-ops-hero { padding: 0.8rem; }
  .micro-ops-hero__identity > div { min-width: 0; }
  .micro-ops-hero h2,
  .micro-ops-section h3 { overflow-wrap: anywhere; }
  .micro-ops-hero h2 { font-size: 1.14rem; }
  .micro-ops-hero__visual svg { width: 210px; }
  .micro-ranking-toggle { grid-template-columns: 1fr; }
  .micro-frequency-grid { grid-template-columns: 1fr; }
  .micro-pipeline-kpis { grid-template-columns: minmax(0, 1fr); }
  .micro-pipeline-kpis article:last-child { grid-column: auto; }
  .micro-pipeline-panel__head { align-items: flex-start; flex-direction: column; }
  .micro-pipeline-panel__head > small { text-align: left; }
}
@media (max-width: 359.98px) {
  .micro-pattern-card { grid-template-columns: 44px minmax(0, 1fr); }
  .micro-pattern-card > b { grid-column: 2; grid-row: 2; justify-self: start; }
  .micro-pattern-card__copy strong { font-size: 0.9rem; }
  .micro-pattern-card__breakdown { grid-template-columns: 1fr; }
}

/* Compact Micro workspace: operational context, realization matrix, and PDWK status. */
.micro-ops-context {
  display: flex;
  min-width: 0;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: .45rem;
}
.micro-ops-context > span,
.micro-ops-context .micro-ops-refresh {
  min-height: 42px;
  display: inline-flex;
  align-items: center;
  gap: .42rem;
  padding: .55rem .72rem;
  border: 1px solid #bad2e9;
  color: #18466f;
  background: #fff;
  font-size: .7rem;
  font-weight: 850;
}
.micro-ops-context .micro-ops-refresh {
  color: #fff;
  border-color: #0754bd;
  background: #0754bd;
}
.micro-realization-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr);
  align-items: stretch;
  gap: 1rem;
  padding: 1rem;
}
.micro-realization-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .75rem;
  align-content: stretch;
}
.micro-realization-summary-grid .micro-realization-type-card {
  min-width: 0;
  min-height: 126px;
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr);
  align-content: start;
  gap: .65rem;
  padding: .85rem;
}
.micro-realization-summary-grid .micro-realization-type-card.type-nett .micro-nett-type-list {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .35rem;
  margin-top: .4rem;
}
.micro-realization-type-card.type-runoff {
  background: linear-gradient(125deg, #073b75 0%, #0873ba 58%, #11a5d8 100%);
}
.micro-realization-type-card.type-ph {
  grid-template-columns: 40px minmax(0, 1fr);
  align-content: start;
  gap: .65rem;
  background: linear-gradient(125deg, #082f64 0%, #0754bd 58%, #0a8fd1 100%);
}
.micro-realization-type-card.type-runoff .micro-realization-type-card__icon,
.micro-realization-type-card.type-ph .micro-realization-type-card__icon {
  color: #064079;
  background: #c9f0ff;
}
.micro-ph-card__head { position: relative; z-index: 1; min-width: 0; }
.micro-ph-card__head > span,
.micro-ph-card__head > small { display: block; overflow-wrap: anywhere; }
.micro-ph-card__metrics {
  position: relative;
  z-index: 1;
  display: grid;
  min-width: 0;
  grid-column: 1 / -1;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .35rem;
  margin-top: .4rem;
}
.micro-ph-card__metrics > span {
  min-width: 0;
  padding: .42rem .52rem;
  border: 1px solid rgba(205, 242, 255, .25);
  background: rgba(3, 37, 83, .3);
  border-radius: 4px 9px 4px 9px;
}
.micro-ph-card__metrics b,
.micro-ph-card__metrics strong,
.micro-ph-card__metrics small { display: block; overflow-wrap: anywhere; }
.micro-ph-card__metrics b { color: #9fe8ff; font-size: .6rem; }
.micro-ph-card__metrics strong { margin-top: .06rem; font-size: .8rem; }
.micro-ph-card__metrics small { margin-top: .04rem; font-size: .56rem; }
.micro-realization-products {
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #c8daec;
  border-radius: 4px 18px 4px 18px;
  background: #fff;
  box-shadow: 0 4px 16px -8px rgba(2, 44, 96, .12);
}
.micro-realization-products .micro-ops-subhead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 0;
  padding: .65rem .85rem;
  border-top: 0;
  border-bottom: 1px solid #dce8f4;
  background: linear-gradient(115deg, #f4f8fe, #fff);
}
.micro-realization-products .micro-ops-subhead h4 {
  margin: 0;
  color: #062d67;
  font-size: .88rem;
  font-weight: 850;
}
.micro-mantri-roster-strip {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1px;
  border-bottom: 1px solid #dce8f4;
  background: #dce8f4;
}
.micro-mantri-roster-strip > span {
  display: flex;
  min-width: 0;
  align-items: center;
  justify-content: space-between;
  gap: .4rem;
  padding: .5rem .58rem;
  background: #f7fbff;
}
.micro-mantri-roster-strip small {
  color: #58718c;
  font-size: .58rem;
  font-weight: 800;
  line-height: 1.2;
}
.micro-mantri-roster-strip strong {
  flex: 0 0 auto;
  color: #0754bd;
  font-size: .78rem;
  font-weight: 950;
  font-variant-numeric: tabular-nums;
}
.micro-realization-products .micro-ops-table-wrap {
  flex: 1 1 auto;
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.micro-realization-products .micro-ops-table {
  width: 100%;
  min-width: 520px;
  height: 100%;
  border-collapse: separate;
  border-spacing: 0;
}
.micro-realization-products .micro-ops-table th,
.micro-realization-products .micro-ops-table td {
  padding: .44rem .55rem;
  font-size: .72rem;
  border-bottom: 1px solid #e2ecf6;
  vertical-align: middle;
}
.micro-realization-products .micro-ops-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  padding: .5rem .55rem;
  color: #fff;
  background: #063476;
  font-size: .62rem;
  font-weight: 850;
  letter-spacing: .02em;
  white-space: nowrap;
}
.micro-realization-products .micro-ops-table tbody th {
  color: #123456;
  font-weight: 800;
  white-space: nowrap;
}
.micro-realization-products .micro-ops-table tfoot th,
.micro-realization-products .micro-ops-table tfoot td {
  padding: .5rem .55rem;
  font-weight: 900;
  background: #eef6fd;
  border-top: 2px solid #b8d4ee;
  border-bottom: 0;
}
.micro-mantri-count {
  display: inline-flex;
  min-width: 32px;
  min-height: 28px;
  align-items: center;
  justify-content: center;
  padding: .2rem .42rem;
  border: 1px solid transparent;
  font-weight: 900;
}
.micro-mantri-count.is-realized { color: #006b51; border-color: #9de0cd; background: #e4f8f1; }
.micro-mantri-count.is-pending { color: #a23a2b; border-color: #f2b9af; background: #fff0ed; }
.micro-kupedes-pending {
  margin: 0 1rem 1rem;
  overflow: hidden;
  border: 1px solid #bdd7f0;
  border-radius: 8px;
  background: #f8fbff;
  box-shadow: 0 12px 28px -22px rgba(2, 44, 96, .45);
}
.micro-kupedes-pending__head {
  display: flex;
  min-width: 0;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: .85rem 1rem;
  color: #fff;
  background: linear-gradient(105deg, #063476 0%, #0754bd 62%, #0799d3 100%);
}
.micro-kupedes-pending__head > div {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: .55rem;
}
.micro-kupedes-pending__head h4 {
  margin: 0;
  overflow-wrap: anywhere;
  font-size: .94rem;
  font-weight: 850;
}
.micro-kupedes-pending__head p {
  margin: .18rem 0 0;
  color: rgba(255, 255, 255, .8);
  font-size: .7rem;
  line-height: 1.4;
}
.micro-kupedes-pending__icon {
  display: inline-flex;
  width: 30px;
  height: 30px;
  flex: 0 0 30px;
  align-items: center;
  justify-content: center;
  border-radius: 4px 10px 4px 10px;
  color: #063476;
  background: #bcecff;
}
.micro-kupedes-pending__headline-stats {
  display: grid;
  grid-template-columns: auto auto;
  flex: 0 0 auto;
  gap: .5rem;
  align-items: center;
  padding: .36rem .48rem;
  border: 1px solid rgba(255, 255, 255, .3);
  border-radius: 6px;
  background: rgba(0, 29, 78, .24);
}
.micro-kupedes-pending__headline-stats strong,
.micro-kupedes-pending__headline-stats span {
  display: block;
  white-space: nowrap;
  font-size: .67rem;
  font-weight: 800;
}
.micro-kupedes-pending__headline-stats strong {
  padding-right: .5rem;
  border-right: 1px solid rgba(255, 255, 255, .26);
}
.micro-kupedes-pending__headline-stats strong b {
  margin-right: .15rem;
  font-size: .9rem;
}
.micro-kupedes-pending__headline-stats span { color: rgba(255, 255, 255, .8); }
.micro-kupedes-pending__summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: .7rem;
  padding: .8rem;
}
.micro-kupedes-branch {
  min-width: 0;
  border: 1px solid #cddff0;
  border-top: 3px solid #0875d1;
  border-radius: 7px;
  background: #fff;
}
.micro-kupedes-branch__head {
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr) auto;
  gap: .55rem;
  align-items: center;
  padding: .68rem .72rem .6rem;
  border-bottom: 1px solid #e3edf7;
}
.micro-kupedes-branch__icon {
  display: inline-flex;
  width: 34px;
  height: 34px;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  color: #0754bd;
  background: #e6f3ff;
}
.micro-kupedes-branch__head div { min-width: 0; }
.micro-kupedes-branch__head div > span,
.micro-kupedes-branch__metrics span {
  display: block;
  color: #637a93;
  font-size: .6rem;
  font-weight: 800;
  letter-spacing: .04em;
  text-transform: uppercase;
}
.micro-kupedes-branch__head div > strong {
  display: block;
  overflow: hidden;
  color: #0a2f61;
  font-size: .78rem;
  font-weight: 900;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.micro-kupedes-branch__head > b {
  display: inline-flex;
  min-width: 32px;
  min-height: 32px;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  color: #fff;
  background: #0754bd;
  font-size: .84rem;
}
.micro-kupedes-branch__metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  padding: .55rem .35rem;
}
.micro-kupedes-branch__metrics > div {
  min-width: 0;
  padding: 0 .4rem;
  border-right: 1px solid #e3edf7;
}
.micro-kupedes-branch__metrics > div:last-child { border-right: 0; }
.micro-kupedes-branch__metrics strong {
  display: block;
  margin-top: .1rem;
  color: #123b6d;
  font-size: .82rem;
  font-weight: 900;
}
.micro-kupedes-branch__details { border-top: 1px solid #e3edf7; }
.micro-kupedes-branch__details summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .75rem;
  min-height: 40px;
  padding: .42rem .72rem;
  cursor: pointer;
  color: #174a82;
  font-size: .7rem;
  font-weight: 800;
  list-style: none;
  touch-action: manipulation;
}
.micro-kupedes-branch__details summary::-webkit-details-marker { display: none; }
.micro-kupedes-branch__details summary > span:last-child { color: #5c7590; font-size: .65rem; }
.micro-kupedes-branch__details[open] summary { background: #f2f8fe; }
.micro-kupedes-branch__details[open] summary i { transform: rotate(180deg); }
.micro-kupedes-branch__people {
  display: grid;
  gap: .36rem;
  margin: 0;
  padding: .48rem .72rem .7rem;
  border-top: 1px solid #e8f0f8;
  list-style: none;
}
.micro-kupedes-branch__people li {
  display: flex;
  min-width: 0;
  align-items: center;
  justify-content: space-between;
  gap: .7rem;
  padding-bottom: .34rem;
  border-bottom: 1px dashed #d8e5f1;
}
.micro-kupedes-branch__people li:last-child { padding-bottom: 0; border-bottom: 0; }
.micro-kupedes-branch__people li > div { min-width: 0; }
.micro-kupedes-branch__people strong,
.micro-kupedes-branch__people span { display: block; overflow-wrap: anywhere; }
.micro-kupedes-branch__people strong { color: #143b6d; font-size: .72rem; }
.micro-kupedes-branch__people span { margin-top: .08rem; color: #627991; font-size: .62rem; }
.micro-kupedes-branch__people b { flex: 0 0 auto; color: #0754bd; font-size: .64rem; white-space: nowrap; }
.micro-kupedes-pending__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  min-height: 90px;
  padding: 1rem;
  color: #08715a;
  background: #f1fcf7;
  font-size: .8rem;
}
.micro-kupedes-pending__empty i { font-size: 1.05rem; }
@media (max-width: 575.98px) {
  .micro-kupedes-pending { margin-right: .65rem; margin-left: .65rem; }
  .micro-kupedes-pending__head { align-items: stretch; flex-direction: column; padding: .72rem; }
  .micro-kupedes-pending__head > div { width: 100%; }
  .micro-kupedes-pending__headline-stats { width: 100%; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .2rem; }
  .micro-kupedes-pending__headline-stats strong,
  .micro-kupedes-pending__headline-stats span { overflow-wrap: anywhere; white-space: normal; }
  .micro-kupedes-pending__headline-stats strong { padding: 0; border-right: 0; }
  .micro-kupedes-pending__summary { grid-template-columns: minmax(0, 1fr); padding: .65rem; }
  .micro-kupedes-branch__metrics { gap: .15rem; }
  .micro-kupedes-branch__metrics > div { padding: 0 .28rem; }
  .micro-kupedes-branch__metrics span { font-size: .56rem; }
}

.micro-pdwk-limit {
  padding: 1rem 1rem 0;
  border-top: 1px solid #d6e3f0;
  background: #f5f9fe;
}
.micro-pdwk-limit__toolbar {
  display: flex;
  min-width: 0;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: .75rem;
}
.micro-pdwk-limit__toolbar > div:first-child { min-width: 0; }
.micro-pdwk-limit__toolbar strong,
.micro-pdwk-limit__toolbar small { display: block; overflow-wrap: anywhere; }
.micro-pdwk-limit__toolbar strong { color: #062d67; font-size: .9rem; }
.micro-pdwk-limit__toolbar small { margin-top: .1rem; color: #637a93; font-size: .66rem; }
.micro-pdwk-role-toggle {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  flex: 0 0 auto;
  gap: .22rem;
  padding: .22rem;
  border: 1px solid #c4d8eb;
  background: #e8f1fb;
}
.micro-pdwk-role-toggle button {
  min-width: 96px;
  min-height: 40px;
  border: 0;
  color: #47627e;
  background: transparent;
  font-size: .7rem;
  font-weight: 900;
}
.micro-pdwk-role-toggle button:hover { color: #052d68; background: #fff; }
.micro-pdwk-role-toggle button:focus-visible { outline: 3px solid rgba(19,167,226,.35); outline-offset: 1px; }
.micro-pdwk-role-toggle button.is-active { color: #fff; background: #0754bd; }
.micro-pdwk-status-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: .65rem;
}
.micro-pdwk-status-grid[hidden] { display: none !important; }
.micro-pdwk-status {
  --pdwk-tone: #1fa21a;
  --pdwk-soft: #effbea;
  position: relative;
  min-width: 0;
  overflow: hidden;
  border: 2px solid var(--pdwk-tone);
  border-radius: 4px 14px 4px 14px;
  background: #fff;
}
.micro-pdwk-status.tone-yellow { --pdwk-tone: #d9a900; --pdwk-soft: #fff8d7; }
.micro-pdwk-status.tone-orange { --pdwk-tone: #ef7600; --pdwk-soft: #fff0df; }
.micro-pdwk-status.tone-red { --pdwk-tone: #e63745; --pdwk-soft: #fff0f2; }
.micro-pdwk-status > header { position: relative; padding: .62rem 3.55rem .62rem .7rem; color: #fff; background: var(--pdwk-tone); text-align: center; }
.micro-pdwk-status > header strong,
.micro-pdwk-status > header small { display: block; overflow-wrap: anywhere; }
.micro-pdwk-status > header strong { font-size: .82rem; }
.micro-pdwk-status > header small { margin-top: .12rem; font-size: .56rem; line-height: 1.35; }
.micro-pdwk-status__detail {
  position: absolute;
  top: 50%;
  right: .55rem;
  display: inline-grid;
  width: 44px;
  height: 44px;
  place-items: center;
  transform: translateY(-50%);
  border: 1px solid rgba(255,255,255,.58);
  border-radius: 6px;
  background: rgba(4, 29, 70, .18);
  color: #fff;
  font-size: .74rem;
}
.micro-pdwk-status__detail:hover { background: rgba(4, 29, 70, .36); }
.micro-pdwk-status__detail:focus-visible { outline: 3px solid rgba(255,255,255,.8); outline-offset: 2px; }
.micro-pdwk-status__body { display: grid; grid-template-columns: 94px minmax(0, 1fr); align-items: center; gap: .65rem; padding: .7rem; background: linear-gradient(145deg, #fff, var(--pdwk-soft)); }
.micro-pdwk-donut {
  display: grid;
  width: 88px;
  height: 88px;
  place-items: center;
  border-radius: 50%;
  background: conic-gradient(var(--pdwk-tone) var(--pdwk-share), #d9dee5 0);
}
.micro-pdwk-donut::before { content: ''; grid-area: 1 / 1; width: 62px; height: 62px; border-radius: 50%; background: #fff; }
.micro-pdwk-donut > div { z-index: 1; grid-area: 1 / 1; text-align: center; }
.micro-pdwk-donut strong,
.micro-pdwk-donut span { display: block; }
.micro-pdwk-donut strong { color: #10243f; font-size: 1.05rem; }
.micro-pdwk-donut span { color: #63758b; font-size: .56rem; font-weight: 800; }
.micro-pdwk-status__metrics { display: grid; min-width: 0; gap: .38rem; }
.micro-pdwk-status__metrics > div { min-width: 0; padding-left: .55rem; border-left: 2px solid var(--pdwk-tone); }
.micro-pdwk-status__metrics span,
.micro-pdwk-status__metrics strong,
.micro-pdwk-status__metrics small { display: block; overflow-wrap: anywhere; }
.micro-pdwk-status__metrics span { color: #63758b; font-size: .55rem; font-weight: 850; text-transform: uppercase; }
.micro-pdwk-status__metrics strong { margin-top: .08rem; color: #10243f; font-size: .76rem; }
.micro-pdwk-status__metrics small { color: var(--pdwk-tone); font-size: .58rem; font-weight: 900; }
.micro-pdwk-status__hint {
  display: block;
  padding: 0 .7rem .56rem;
  background: linear-gradient(145deg, #fff, var(--pdwk-soft));
  color: #61738b;
  font-size: .56rem;
  font-weight: 800;
  text-align: center;
}
.micro-pdwk-status__hint i { margin-right: .2rem; color: var(--pdwk-tone); }
.micro-pdwk-modal { max-width: min(680px, calc(100vw - 24px)); }
.micro-pdwk-modal__lead { margin: 0 0 .8rem; color: #526b87; font-size: .84rem; line-height: 1.5; text-align: left; }
.micro-pdwk-modal__summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-bottom: .8rem; border: 1px solid #d8e4f1; border-radius: 8px; overflow: hidden; }
.micro-pdwk-modal__summary > div { min-width: 0; padding: .7rem .75rem; background: #f6f9fd; text-align: left; }
.micro-pdwk-modal__summary > div + div { border-left: 1px solid #d8e4f1; }
.micro-pdwk-modal__summary span,
.micro-pdwk-modal__summary strong { display: block; overflow-wrap: anywhere; }
.micro-pdwk-modal__summary span { color: #687d95; font-size: .64rem; font-weight: 850; text-transform: uppercase; }
.micro-pdwk-modal__summary strong { margin-top: .14rem; color: #092f68; font-size: 1rem; }
.micro-pdwk-modal__section { margin-top: .75rem; text-align: left; }
.micro-pdwk-modal__section h4 { margin: 0 0 .4rem; color: #123a71; font-size: .78rem; font-weight: 900; }
.micro-pdwk-modal__section p { margin: 0; color: #6a7d93; font-size: .74rem; }
.micro-pdwk-modal__list { display: grid; max-height: min(360px, 42vh); overflow: auto; border: 1px solid #d8e4f1; border-radius: 8px; background: #fff; }
.micro-pdwk-modal__row { display: grid; grid-template-columns: 54px minmax(0, 1fr); gap: .6rem; align-items: center; min-width: 0; padding: .55rem .65rem; border-bottom: 1px solid #e5edf5; }
.micro-pdwk-modal__row:last-child { border-bottom: 0; }
.micro-pdwk-modal__row:nth-child(even) { background: #f8fbfe; }
.micro-pdwk-modal__pn { color: #0d5bb7; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .69rem; font-weight: 900; }
.micro-pdwk-modal__name,
.micro-pdwk-modal__unit { display: block; overflow-wrap: anywhere; }
.micro-pdwk-modal__name { color: #152d4f; font-size: .78rem; font-weight: 850; }
.micro-pdwk-modal__unit { margin-top: .08rem; color: #6b7f96; font-size: .68rem; }
.micro-mantri-tier-modal { max-width: min(820px, calc(100vw - 24px)); }
.micro-mantri-tier-modal__lead { margin: 0 0 .8rem; color: #526b87; font-size: .82rem; line-height: 1.5; text-align: left; }
.micro-mantri-tier-modal__grid { display: grid; gap: .65rem; text-align: left; }
.micro-mantri-tier-modal__bucket { overflow: hidden; border: 1px solid #d8e4f1; border-radius: 8px; background: #fff; }
.micro-mantri-tier-modal__bucket > header { display: flex; align-items: center; justify-content: space-between; gap: .65rem; padding: .5rem .65rem; color: #123a71; background: #f5f9fd; }
.micro-mantri-tier-modal__bucket > header strong { font-size: .76rem; }
.micro-mantri-tier-modal__bucket > header span { color: #526b87; font-size: .67rem; font-weight: 800; text-align: right; }
.micro-mantri-tier-modal__bucket.tone-none > header { color: #861633; background: #fff0f3; }
.micro-mantri-tier-modal__bucket.tone-extreme_low > header { color: #81320b; background: #fff2e8; }
.micro-mantri-tier-modal__bucket.tone-low > header { color: #765000; background: #fff8df; }
.micro-mantri-tier-modal__bucket.tone-mid > header { color: #06668f; background: #eaf9ff; }
.micro-mantri-tier-modal__bucket.tone-high > header { color: #06469d; background: #e9f2ff; }
.micro-mantri-tier-modal__list { max-height: min(290px, 36vh); overflow: auto; }
.micro-mantri-tier-modal__row { display: grid; grid-template-columns: 60px minmax(0, 1fr) auto; gap: .6rem; align-items: center; padding: .56rem .65rem; border-top: 1px solid #e5edf5; }
.micro-mantri-tier-modal__row:nth-child(even) { background: #f8fbfe; }
.micro-mantri-tier-modal__pn { color: #0d5bb7; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .68rem; font-weight: 900; }
.micro-mantri-tier-modal__name,
.micro-mantri-tier-modal__unit,
.micro-mantri-tier-modal__amount { display: block; overflow-wrap: anywhere; }
.micro-mantri-tier-modal__name { color: #152d4f; font-size: .76rem; font-weight: 850; }
.micro-mantri-tier-modal__unit { margin-top: .08rem; color: #6b7f96; font-size: .67rem; }
.micro-mantri-tier-modal__amount { color: #0754bd; font-size: .72rem; font-weight: 900; text-align: right; white-space: nowrap; }
.micro-mantri-tier-modal__amount small { display: block; margin-top: .08rem; color: #6b7f96; font-size: .6rem; font-weight: 700; }
.micro-mantri-tier-modal__empty { margin: 0; padding: .65rem; color: #6a7d93; font-size: .72rem; }
.micro-term-item { grid-template-columns: 1fr; }

@media (max-width: 1180px) {
  .micro-realization-type-card.type-ph { grid-template-columns: 42px minmax(0, 1fr); }
  .micro-ph-card__metrics { grid-column: 1 / -1; }
  .micro-pdwk-status-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 767.98px) {
  .micro-ops-context { width: 100%; justify-content: flex-start; }
  .micro-realization-layout { padding: .75rem; }
  .micro-realization-summary-grid { grid-template-columns: minmax(0, 1fr); }
  .micro-mantri-roster-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .micro-pdwk-limit__toolbar { align-items: stretch; flex-direction: column; }
  .micro-pdwk-role-toggle { width: 100%; }
  .micro-pdwk-role-toggle button { min-width: 0; }
}
@media (max-width: 479.98px) {
  .micro-ops-context > span,
  .micro-ops-context .micro-ops-refresh { flex: 1 1 100%; justify-content: center; }
  .micro-pdwk-status-grid { grid-template-columns: 1fr; }
  .micro-realization-summary-grid .micro-realization-type-card.type-nett,
  .micro-realization-summary-grid .micro-realization-type-card.type-ph { grid-template-columns: minmax(0, 1fr); }
  .micro-realization-summary-grid .micro-realization-type-card.type-nett .micro-realization-type-card__icon { grid-row: auto; }
  .micro-realization-summary-grid .micro-realization-type-card.type-nett .micro-nett-type-list,
  .micro-ph-card__metrics { grid-column: auto; grid-template-columns: minmax(0, 1fr); }
  .micro-pdwk-status__body { grid-template-columns: 82px minmax(0, 1fr); }
  .micro-pdwk-donut { width: 78px; height: 78px; }
  .micro-pdwk-donut::before { width: 55px; height: 55px; }
  .micro-pdwk-modal__summary { grid-template-columns: 1fr; }
  .micro-pdwk-modal__summary > div + div { border-top: 1px solid #d8e4f1; border-left: 0; }
  .micro-mantri-tier-modal__row { grid-template-columns: 52px minmax(0, 1fr); }
  .micro-mantri-tier-modal__amount { grid-column: 2; text-align: left; }
}
/* Landing analytics v1: EOM quality, SME pricing, and operational drill-down. */
.loan-analytics-card {
  position: relative;
  isolation: isolate;
  margin-top: 1rem;
  overflow: hidden;
  border: 1px solid #b9d8f5;
  border-radius: 20px;
  background: linear-gradient(145deg, #ffffff 0%, #f7fbff 58%, #eef8ff 100%);
  box-shadow: 0 16px 38px -28px rgba(3, 47, 109, .58);
}
.loan-analytics-shape { position: absolute; z-index: -1; pointer-events: none; }
.loan-analytics-shape--one {
  top: -84px; right: -62px; width: 250px; height: 190px;
  border-radius: 48% 0 56% 52%; transform: rotate(-9deg);
  background: linear-gradient(135deg, rgba(7,84,189,.13), rgba(19,167,226,.05));
}
.loan-analytics-shape--two {
  bottom: 26px; left: -90px; width: 210px; height: 80px;
  border: 18px solid rgba(19,167,226,.06); border-radius: 999px; transform: rotate(-13deg);
}
.loan-analytics-head {
  position: relative; display: flex; align-items: center; justify-content: space-between;
  gap: 1rem; padding: 1.15rem 1.3rem .9rem;
}
.loan-analytics-title { display: flex; align-items: center; gap: .8rem; min-width: 0; }
.loan-analytics-icon {
  display: grid; place-items: center; flex: 0 0 46px; height: 46px; color: #fff;
  border-radius: 14px 14px 14px 5px; background: linear-gradient(145deg, #063d91, #0877d1);
  box-shadow: 0 10px 24px -14px rgba(5,61,145,.9); font-size: 1.05rem;
}
.loan-analytics-icon--cyan { background: linear-gradient(145deg, #0754bd, #13a7e2); }
.loan-analytics-kicker { color: #0870bd; font-size: .7rem; font-weight: 850; letter-spacing: .12em; text-transform: uppercase; }
.loan-analytics-title h3 { margin: .14rem 0 0; color: #082b5b; font-size: clamp(1rem, 1.5vw, 1.32rem); font-weight: 850; }
.loan-analytics-latest, .tariff-delta {
  display: grid; justify-items: end; padding: .58rem .76rem; border: 1px solid #c8def3;
  border-radius: 12px; background: rgba(255,255,255,.8); color: #46617d;
}
.loan-analytics-latest span, .tariff-delta span { font-size: .66rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.loan-analytics-latest strong { color: #073b82; font-size: .9rem; }
.loan-quality-layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 1rem; padding: 0 1.3rem 1rem; }
.loan-quality-chart-wrap { position: relative; min-width: 0; height: 320px; padding: .45rem .25rem .1rem; }
.loan-quality-legend {
  align-self: stretch; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .45rem; padding: .75rem;
  border: 1px solid #d6e5f4; border-radius: 16px; background: rgba(255,255,255,.9);
}
.loan-quality-legend__item { display: flex; align-items: center; gap: .45rem; width: 100%; padding: .42rem .55rem; border: 1.5px solid transparent; border-radius: 10px; background: #f6faff; text-align: left; transition: all .18s ease; cursor: pointer; }
.loan-quality-legend__item:hover { border-color: #9dccf2; background: #eef7ff; transform: translateY(-1px); }
.loan-quality-legend__item.is-active { border-color: var(--legend-color, #0754bd); background: #ffffff; box-shadow: 0 4px 14px -4px rgba(7, 84, 189, 0.28); transform: translateY(-1px); }
.loan-quality-legend__item:focus-visible { outline: 3px solid #13a7e2; outline-offset: 2px; }
.loan-quality-legend__swatch { width: 14px; height: 5px; border-radius: 999px; background: var(--legend-color); flex-shrink: 0; box-shadow: 0 0 0 3px color-mix(in srgb, var(--legend-color) 14%, transparent); }
.loan-quality-legend__item div { display: grid; grid-template-columns: 1fr auto; align-items: baseline; gap: .35rem; width: 100%; min-width: 0; }
.loan-quality-legend__item span { color: #475569; font-size: .68rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.loan-quality-legend__item strong { color: #0f172a; font-size: .78rem; font-weight: 850; font-variant-numeric: tabular-nums; text-align: right; }
.loan-quality-legend > small { grid-column: 1 / -1; color: #72869a; font-size: .68rem; font-weight: 700; text-align: right; }
.loan-analytics-details { border-top: 1px solid #d9e7f4; background: rgba(255,255,255,.72); }
.loan-analytics-details summary { cursor: pointer; padding: .72rem 1.3rem; color: #315576; font-size: .76rem; font-weight: 800; list-style: none; }
.loan-analytics-details summary::-webkit-details-marker { display: none; }
.loan-analytics-details summary i { margin-right: .48rem; color: #0b74c9; }
.loan-analytics-table-wrap { max-height: 300px; overflow: auto; border-top: 1px solid #e2edf7; }
.loan-analytics-table { width: 100%; min-width: 620px; border-collapse: separate; border-spacing: 0; color: #203a59; font-size: .76rem; }
.loan-analytics-table th, .loan-analytics-table td { padding: .68rem .9rem; border-bottom: 1px solid #e2edf7; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.loan-analytics-table th:first-child { position: sticky; left: 0; z-index: 1; text-align: left; background: #f7fbff; }
.loan-analytics-table thead th { position: sticky; top: 0; z-index: 2; color: #fff; background: #073d86; font-size: .68rem; letter-spacing: .05em; text-transform: uppercase; }
.loan-analytics-table thead th:first-child { z-index: 3; background: #073d86; }
.tariff-overview { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .65rem; padding: 0 1.3rem .8rem; }
.tariff-overview > div { display: grid; min-width: 0; padding: .7rem .78rem; border-left: 3px solid #0754bd; border-radius: 4px 11px 11px 4px; background: #f5faff; }
.tariff-overview > div:nth-child(2) { border-left-color: #00a6d6; }
.tariff-overview > div:nth-child(3) { border-left-color: #008f78; }
.tariff-overview > div:nth-child(4) { border-left-color: #e38000; }
.tariff-overview span { color: #607892; font-size: .62rem; font-weight: 850; text-transform: uppercase; }
.tariff-overview strong { overflow: hidden; color: #082f68; font-size: 1rem; font-variant-numeric: tabular-nums; text-overflow: ellipsis; white-space: nowrap; }
.tariff-overview strong.is-positive { color: #008866; }
.tariff-overview strong.is-warning { color: #c96900; }
.tariff-overview small { overflow: hidden; color: #71869b; font-size: .62rem; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.tariff-series-chart-wrap { position: relative; height: 300px; margin: 0 1.3rem 1rem; padding: .7rem .55rem .25rem; border: 1px solid #d7e7f5; border-radius: 14px; background: rgba(255,255,255,.84); }
.tariff-detail-table { min-width: 980px; }
.tariff-delta strong { color: #0754bd; font-size: 1.05rem; font-variant-numeric: tabular-nums; }
.tariff-delta .tariff-delta__unit { color: #607b97; font-size: .58rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
.tariff-delta.is-down strong { color: #008866; }
.tariff-delta.is-up strong { color: #d97706; }
.tariff-delta small { color: #71849a; font-size: .64rem; }
.sme-ops-week-badge { display: grid; grid-template-columns: auto auto; align-items: center; column-gap: .42rem; padding: .52rem .68rem; border: 1px solid rgba(255,255,255,.32); border-radius: 12px; color: #fff; background: rgba(255,255,255,.13); }
.sme-ops-week-badge i { grid-row: 1 / 3; color: #80dcff; }
.sme-ops-week-badge span { font-size: .72rem; font-weight: 900; }
.sme-ops-week-badge strong { font-size: .62rem; font-weight: 650; opacity: .84; }
.sme-ops-feature--tiers, .sme-ops-feature--inactive { background: linear-gradient(145deg, #fff, #f5faff); }
.sme-ops-tier-total { display: grid; justify-items: end; padding: .45rem .7rem; border-radius: 10px; color: #57708c; background: #e9f5ff; }
.sme-ops-tier-total span { font-size: .62rem; font-weight: 800; text-transform: uppercase; }
.sme-ops-tier-total strong { color: #0754bd; font-size: 1.15rem; }
.sme-ops-tier-table-wrap, .sme-ops-inactive-table-wrap { overflow: auto; border: 1px solid #d5e4f2; border-radius: 14px; }
.sme-ops-tier-table, .sme-ops-inactive-table { width: 100%; min-width: 860px; border-collapse: separate; border-spacing: 0; color: #173a60; font-size: .75rem; }
.sme-ops-tier-table th, .sme-ops-tier-table td, .sme-ops-inactive-table th, .sme-ops-inactive-table td { padding: .72rem .76rem; border-right: 1px solid #e0ebf5; border-bottom: 1px solid #e0ebf5; text-align: center; }
.sme-ops-tier-table thead th, .sme-ops-inactive-table thead th { position: sticky; top: 0; z-index: 2; color: #fff; background: #073d86; font-size: .66rem; letter-spacing: .035em; text-transform: uppercase; }
.sme-ops-tier-table th:first-child, .sme-ops-inactive-table th:first-child { position: sticky; left: 0; z-index: 1; text-align: left; background: #f5faff; }
.sme-ops-tier-table thead th:first-child, .sme-ops-inactive-table thead th:first-child { z-index: 3; background: #073d86; }
.sme-ops-tier-table tbody th i { margin-right: .45rem; color: #0c7ac8; }
.sme-ops-tier-table td { background: #fff; }
.sme-ops-tier-table td strong { display: block; color: #082f68; font-size: .82rem; }
.sme-ops-tier-table td span { color: #698099; font-size: .66rem; }
.sme-ops-tier-cell { display: grid; width: 100%; min-height: 44px; place-content: center; gap: .12rem; padding: 0; border: 0; color: inherit; background: transparent; cursor: pointer; }
.sme-ops-tier-cell:hover { background: #eef7ff; }
.sme-ops-tier-cell:focus-visible { outline: 3px solid rgba(19, 167, 226, .28); outline-offset: -3px; }
.sme-ops-tier-table tfoot th, .sme-ops-tier-table tfoot td { color: #fff; background: #0b5ba7; font-weight: 800; }
.sme-ops-tier-table tfoot th:first-child { background: #083f83; }
.sme-ops-tier-table .is-total { color: #0754bd; background: #eaf5ff; font-size: .9rem; font-weight: 900; }
.sme-ops-tier-table tfoot .is-total { color: #fff; background: #083f83; }
.sme-ops-vendor-item { cursor: pointer; text-align: left; }
.sme-ops-vendor-item:focus-visible { outline: 3px solid #13a7e2; outline-offset: 3px; }
.sme-ops-inactive-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .8rem; }
.sme-ops-inactive-card { position: relative; overflow: hidden; padding: 1rem; border: 1px solid #cfe1f2; border-radius: 15px; background: #fff; cursor: pointer; transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
.sme-ops-inactive-card:hover { border-color: #7fbcea; box-shadow: 0 14px 30px -26px #073b82; transform: translateY(-1px); }
.sme-ops-inactive-card:focus-visible { outline: 3px solid #13a7e2; outline-offset: 3px; }
.sme-ops-inactive-card::before { position: absolute; top: 0; right: 0; width: 86px; height: 8px; content: ''; border-radius: 0 0 0 12px; background: #13a7e2; }
.sme-ops-inactive-card.tone-2::before { background: #f59e0b; }
.sme-ops-inactive-card.tone-3::before { background: #ef4444; }
.sme-ops-inactive-card > span { color: #53708f; font-size: .72rem; font-weight: 850; }
.sme-ops-inactive-card > span i { margin-right: .38rem; color: #0877ca; }
.sme-ops-inactive-card > strong { display: block; margin: .45rem 0; color: #073b82; font-size: 1.55rem; }
.sme-ops-inactive-card > strong small { font-size: .7rem; }
.sme-ops-inactive-meter { height: 7px; overflow: hidden; border-radius: 99px; background: #e4edf6; }
.sme-ops-inactive-meter span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #0754bd, #13a7e2); }
.sme-ops-inactive-card > small { color: #72859a; font-size: .65rem; }
.sme-ops-inactive-open { display: inline-flex; align-items: center; justify-content: center; gap: .38rem; min-height: 36px; margin-top: .7rem; padding: .42rem .62rem; border: 1px solid #bdd8ee; border-radius: 9px; color: #0754bd; background: #eef7ff; font-size: .68rem; font-weight: 850; }
.sme-ops-inactive-open:hover { border-color: #6fb7ea; background: #e2f2ff; }
.sme-ops-inactive-open:focus-visible, .sme-ops-inactive-cell:focus-visible { outline: 3px solid #13a7e2; outline-offset: 2px; }
.sme-ops-inactive-table-wrap { margin-top: .85rem; }
.sme-ops-inactive-table td { background: #fff; font-weight: 800; font-variant-numeric: tabular-nums; }
.sme-ops-inactive-cell { min-width: 44px; min-height: 34px; padding: .35rem .6rem; border: 1px solid #c7ddef; border-radius: 8px; color: #0754bd; background: #f3f9ff; font: inherit; font-weight: 900; cursor: pointer; }
.sme-ops-inactive-cell:hover { border-color: #69afe2; background: #e7f4ff; }
.sme-ops-feature--frequency { overflow: hidden; border-top: 5px solid #0754bd; background: linear-gradient(145deg, #fff, #f2f8ff); }
.sme-ops-feature--frequency .sme-ops-feature-icon { color: #fff; border-color: #0754bd; background: #0754bd; }
.sme-ops-data-source { display: inline-flex; align-items: center; flex: 0 0 auto; gap: .42rem; min-height: 36px; padding: .48rem .68rem; border: 1px solid #b9d8ef; border-radius: 9px; color: #0754bd; background: #eef7ff; font-size: .66rem; font-weight: 850; white-space: nowrap; }
.sme-ops-data-source i { color: #00a6d6; }
.sme-ops-frequency-summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: .9rem; overflow: hidden; border: 1px solid #0a4e9e; border-radius: 12px; color: #fff; background: linear-gradient(110deg, #063b82, #0754bd 62%, #0879c9); }
.sme-ops-frequency-summary > div { display: grid; min-width: 0; gap: .16rem; padding: .78rem 1rem; }
.sme-ops-frequency-summary > div + div { border-left: 1px solid rgba(255,255,255,.24); }
.sme-ops-frequency-summary span { color: #cfeaff; font-size: .62rem; font-weight: 850; letter-spacing: .045em; text-transform: uppercase; }
.sme-ops-frequency-summary strong { min-width: 0; overflow-wrap: anywhere; color: #fff; font-size: 1.15rem; font-variant-numeric: tabular-nums; line-height: 1.2; }
.sme-ops-frequency-summary strong small { color: #bde4ff; font-size: .65rem; font-weight: 750; }
.sme-ops-frequency-grid { display: grid; grid-template-columns: repeat(var(--sme-frequency-columns, 4), minmax(0, 1fr)); margin-top: .75rem; overflow: hidden; border: 1px solid #c9ddef; border-radius: 12px; background: #fff; }
.sme-ops-frequency-item { --frequency-accent: #0754bd; display: grid; min-width: 0; gap: .72rem; padding: .82rem; border-top: 4px solid var(--frequency-accent); border-right: 1px solid #c9ddef; border-bottom: 1px solid #c9ddef; background: #fff; }
.sme-ops-frequency-item.tone-2 { --frequency-accent: #00a6d6; }
.sme-ops-frequency-item.tone-3 { --frequency-accent: #009b75; }
.sme-ops-frequency-item.tone-4 { --frequency-accent: #e38000; }
.sme-ops-frequency-identity { display: grid; grid-template-columns: 44px minmax(0, 1fr); align-items: center; gap: .62rem; min-width: 0; }
.sme-ops-frequency-badge { display: grid; width: 44px; height: 44px; place-items: center; border-radius: 50%; color: #fff; background: var(--frequency-accent); box-shadow: 0 10px 22px -15px var(--frequency-accent); font-size: .82rem; font-weight: 900; font-variant-numeric: tabular-nums; }
.sme-ops-frequency-identity > div { min-width: 0; }
.sme-ops-frequency-identity small { color: #71879c; font-size: .58rem; font-weight: 850; letter-spacing: .04em; text-transform: uppercase; }
.sme-ops-frequency-identity h4 { margin: .08rem 0 0; overflow-wrap: anywhere; color: #0a315f; font-size: .8rem; font-weight: 900; line-height: 1.25; }
.sme-ops-frequency-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); min-width: 0; border-top: 1px solid #d9e7f3; }
.sme-ops-frequency-metrics > div { display: grid; min-width: 0; align-content: start; gap: .1rem; padding-top: .58rem; }
.sme-ops-frequency-metrics > div + div { padding-left: .65rem; border-left: 1px solid #d9e7f3; }
.sme-ops-frequency-metrics span { color: #687f96; font-size: .57rem; font-weight: 850; text-transform: uppercase; }
.sme-ops-frequency-metrics strong { min-width: 0; overflow-wrap: anywhere; color: var(--frequency-accent); font-size: .92rem; font-weight: 900; font-variant-numeric: tabular-nums; line-height: 1.25; }
.sme-ops-frequency-metrics small { color: #8294a7; font-size: .57rem; font-weight: 700; }
.sme-ops-frequency-note { display: flex; align-items: flex-start; gap: .42rem; margin: .7rem 0 0; padding: .55rem .65rem; border-left: 3px solid #13a7e2; border-radius: 4px 8px 8px 4px; color: #59738e; background: #eaf6ff; font-size: .64rem; font-weight: 700; line-height: 1.45; }
.sme-ops-frequency-note i { flex: 0 0 auto; margin-top: .12rem; color: #0877ca; }
.sme-vendor-modal[hidden] { display: none !important; }
.sme-vendor-modal { position: fixed; inset: 0; z-index: 1080; display: grid; place-items: center; padding: 1rem; }
.sme-vendor-modal__backdrop { position: absolute; inset: 0; width: 100%; border: 0; background: rgba(2,18,43,.72); backdrop-filter: blur(5px); }
.sme-vendor-modal__dialog { position: relative; display: flex; flex-direction: column; width: min(1440px, 97vw); max-height: min(820px, 92vh); overflow: hidden; border: 1px solid rgba(142,203,255,.55); border-radius: 20px; background: #fff; box-shadow: 0 34px 80px -32px #020d22; }
.sme-vendor-modal__dialog > header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.15rem; color: #fff; background: linear-gradient(120deg, #052f70, #0754bd 64%, #13a7e2); }
.sme-vendor-modal__dialog > header span { font-size: .64rem; font-weight: 850; letter-spacing: .12em; text-transform: uppercase; opacity: .78; }
.sme-vendor-modal__dialog > header h3 { margin: .12rem 0 0; color: #fff; font-size: 1.2rem; }
.sme-vendor-modal__close { display: grid; place-items: center; width: 38px; height: 38px; border: 1px solid rgba(255,255,255,.36); border-radius: 50%; color: #fff; background: rgba(255,255,255,.12); }
.sme-vendor-modal__close:focus-visible { outline: 3px solid #fff; outline-offset: 2px; }
.sme-vendor-modal__summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; padding: .85rem 1.15rem; background: #eef7ff; }
.sme-vendor-modal__summary > div { display: grid; padding: .55rem .7rem; border-radius: 10px; background: #fff; }
.sme-vendor-modal__summary span { color: #687e94; font-size: .62rem; font-weight: 800; text-transform: uppercase; }
.sme-vendor-modal__summary strong { color: #0754bd; font-size: 1rem; }
.sme-vendor-modal__summary strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sme-vendor-modal__toolbar { display: flex; align-items: center; justify-content: space-between; gap: .8rem; padding: .68rem 1.15rem; border-top: 1px solid #dceaf6; border-bottom: 1px solid #dceaf6; background: #fff; }
.sme-vendor-modal__toolbar label { display: flex; align-items: center; gap: .5rem; width: min(480px, 100%); padding: .48rem .65rem; border: 1px solid #c8dced; border-radius: 10px; color: #60809e; background: #f7fbff; }
.sme-vendor-modal__toolbar input { min-width: 0; width: 100%; border: 0; outline: 0; color: #15395f; background: transparent; font-size: .75rem; }
.sme-vendor-modal__toolbar label:focus-within { border-color: #13a7e2; box-shadow: 0 0 0 3px rgba(19,167,226,.15); }
.sme-vendor-modal__toolbar > strong { flex: 0 0 auto; color: #0754bd; font-size: .72rem; }
.sme-vendor-modal__table-wrap { flex: 1 1 auto; min-height: 220px; overflow: auto; overscroll-behavior: contain; }
.sme-vendor-modal table { width: max-content; min-width: 100%; border-collapse: separate; border-spacing: 0; font-size: .72rem; }
.sme-vendor-modal th, .sme-vendor-modal td { min-width: 118px; max-width: 280px; padding: .68rem .78rem; border-right: 1px solid #e2edf7; border-bottom: 1px solid #e2edf7; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
.sme-vendor-modal tbody tr:nth-child(even) td { background: #f8fbfe; }
.sme-vendor-modal tbody tr:hover td { background: #eef7ff; }
.sme-vendor-modal thead th { position: sticky; top: 0; z-index: 2; color: #fff; background: #083f83; font-size: .66rem; text-transform: uppercase; }
.sme-vendor-modal th:first-child, .sme-vendor-modal td:first-child { position: sticky; left: 0; z-index: 1; min-width: 64px; background: #f6faff; }
.sme-vendor-modal thead th:first-child { z-index: 3; background: #083f83; }
.sme-vendor-modal__loading, .sme-vendor-modal__empty { padding: 2.4rem 1rem !important; color: #667e98; text-align: center !important; }
.sme-vendor-modal__dialog > footer { display: flex; align-items: center; justify-content: space-between; gap: .8rem; padding: .8rem 1.15rem; border-top: 1px solid #dce8f3; color: #708399; font-size: .68rem; }
.sme-vendor-modal__dialog > footer a { padding: .55rem .7rem; border-radius: 9px; color: #fff; background: #0754bd; font-weight: 800; }
.sme-vendor-modal__dialog > footer a i { margin-left: .4rem; }
body.sme-vendor-modal-open { overflow: hidden; }
.sme-unproductive-modal .sme-vendor-modal__dialog { width: min(940px, 96vw); }
.sme-unproductive-modal .sme-vendor-modal__summary { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.sme-unproductive-modal table { min-width: 860px; }
.sme-unproductive-modal th:first-child, .sme-unproductive-modal td:first-child { min-width: 58px; }
.loan-analytics-lazy-slot.is-loading {
  position: relative; min-height: 180px; margin-top: 1rem; overflow: hidden;
  border: 1px solid #c9dff2; border-radius: 20px;
  background: linear-gradient(110deg, #f2f7fc 8%, #ffffff 18%, #f2f7fc 33%);
  background-size: 220% 100%; animation: loanAnalyticsShimmer 1.35s linear infinite;
}
.loan-analytics-lazy-slot.is-loading::before {
  position: absolute; top: 28px; left: 28px; width: min(420px, 62%); height: 24px;
  content: ''; border-radius: 8px; background: #dbe9f5; box-shadow: 0 48px 0 #e5eff7, 0 88px 0 #e5eff7;
}
.loan-analytics-lazy-slot.is-loading::after {
  position: absolute; top: 26px; right: 28px; width: 96px; height: 96px;
  content: ''; border: 15px solid #dfedf7; border-radius: 50%;
}
.loan-analytics-lazy-slot.is-empty { display: none; }
.loan-analytics-lazy-slot.is-error {
  margin-top: 1rem; padding: .75rem 1rem; border: 1px solid #f2cf99; border-radius: 13px;
  color: #87520a; background: #fff8e8; font-size: .75rem; font-weight: 700;
}
.loan-analytics-lazy-slot.is-error button { margin-left: .55rem; border: 0; color: #0754bd; background: transparent; font-weight: 850; }
@keyframes loanAnalyticsShimmer { to { background-position-x: -220%; } }
@media (max-width: 991.98px) {
  .loan-quality-layout { grid-template-columns: 1fr; }
  .loan-quality-legend { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .loan-quality-legend > small { grid-column: 1 / -1; }
  .tariff-overview { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .sme-ops-inactive-grid { grid-template-columns: 1fr; }
  .sme-ops-frequency-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 575.98px) {
  .loan-analytics-head { align-items: flex-start; padding: .95rem; }
  .loan-analytics-latest, .tariff-delta { flex: 0 0 auto; padding: .42rem .5rem; }
  .loan-analytics-latest span, .tariff-delta span { display: none; }
  .loan-quality-layout { padding: 0 .75rem .8rem; }
  .loan-quality-chart-wrap { height: 245px; }
  .loan-quality-legend { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .tariff-overview { grid-template-columns: 1fr; padding: 0 .75rem .8rem; }
  .tariff-series-chart-wrap { height: 245px; margin: 0 .75rem .8rem; }
  .sme-ops-week-badge { width: 100%; }
  .sme-ops-data-source { max-width: 100%; white-space: normal; }
  .sme-ops-frequency-summary, .sme-ops-frequency-grid { grid-template-columns: 1fr; }
  .sme-ops-frequency-summary > div + div { border-top: 1px solid rgba(255,255,255,.24); border-left: 0; }
  .sme-vendor-modal { padding: .35rem; }
  .sme-vendor-modal__dialog { width: 100%; max-height: 95vh; border-radius: 15px; }
  .sme-vendor-modal__dialog > footer { align-items: stretch; flex-direction: column; }
  .sme-vendor-modal__dialog > footer a { text-align: center; }
}
@media (max-width: 359.98px) {
  .loan-analytics-head { flex-direction: column; }
  .loan-analytics-title { width: 100%; }
  .loan-analytics-latest, .tariff-delta { align-self: flex-start; justify-items: start; max-width: 100%; }
  .loan-quality-legend { grid-template-columns: 1fr; }
  .loan-quality-legend__item strong { font-size: .84rem; }
  .sme-ops-feature--frequency .sme-ops-section-heading { grid-template-columns: 34px 34px; justify-content: start; }
  .sme-ops-feature--frequency .sme-ops-section-heading > div { grid-column: 1 / -1; width: 100%; }
  .sme-ops-feature--frequency .sme-ops-section-heading h3 { overflow-wrap: normal; word-break: normal; }
}

/* Area scope and executive insights: bold Nusantara identity with dense operational readability. */
.db-shell .landing-scope-stage .area6-scope-toggle {
  gap: .35rem;
  padding: .35rem;
  border: 1px solid #cbd5e1;
  border-radius: 12px !important;
  background: #f1f5f9;
  box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
}
.landing-scope-stage .area6-scope-btn {
  display: flex;
  align-items: center;
  gap: .55rem;
  min-height: 48px;
  padding: .4rem .72rem;
  border: 1px solid transparent;
  border-radius: 8px;
  color: #334155;
  text-align: left;
  transition: all .2s cubic-bezier(.16, 1, .3, 1);
  cursor: pointer;
}
.landing-scope-stage .area6-scope-btn:hover {
  border-color: #cbd5e1;
  background: #fff;
  color: #0754bd;
  box-shadow: 0 2px 6px -1px rgba(7, 84, 189, .12);
  transform: translateY(-1px);
}
.landing-scope-stage .area6-scope-btn:focus-visible {
  outline: 3px solid rgba(19, 167, 226, .32);
  outline-offset: 2px;
}
.landing-scope-stage .area6-scope-btn.active {
  border-color: transparent;
  background: linear-gradient(135deg, #0754bd 0%, #004685 100%);
  color: #fff;
  box-shadow: 0 4px 14px -2px rgba(7, 84, 189, .42);
  transform: translateY(-1px);
}
.area6-scope-btn__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 32px;
  width: 32px;
  height: 32px;
  border: 1px solid #cbd5e1;
  border-radius: 7px;
  background: #fff;
  color: #0754bd;
  font-size: .88rem;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
  transition: all .2s ease;
}
.area6-scope-btn.active .area6-scope-btn__icon {
  border-color: rgba(255, 255, 255, .3);
  background: rgba(255, 255, 255, .18);
  color: #fff;
  box-shadow: none;
}
.area6-scope-btn__copy { display: block; min-width: 0; }
.area6-scope-btn__copy strong,
.area6-scope-btn__copy small { display: block; overflow-wrap: anywhere; letter-spacing: 0; }
.area6-scope-btn__copy strong { font-size: .76rem; font-weight: 800; line-height: 1.2; }
.area6-scope-btn__copy small { margin-top: .12rem; color: #64748b; font-size: .6rem; font-weight: 600; line-height: 1.2; }
.area6-scope-btn.active .area6-scope-btn__copy small { color: rgba(255, 255, 255, 0.88); }

@media (min-width: 992px) {
  .db-shell .area6-head,
  .db-shell.landing-compact .area6-head {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 1.5rem !important;
  }
  .db-shell .area6-head > div:first-child,
  .db-shell.landing-compact .area6-head > div:first-child {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    max-width: 520px !important;
  }
  .db-shell .area6-head-actions,
  .db-shell.landing-compact .area6-head-actions {
    flex: 0 0 auto !important;
    margin-left: auto !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: flex-end !important;
    gap: 0.55rem !important;
    width: auto !important;
  }
  .db-shell .landing-scope-stage,
  .db-shell.landing-compact .landing-scope-stage {
    display: flex !important;
    justify-content: flex-end !important;
    align-items: center !important;
    width: auto !important;
    max-width: 100% !important;
  }
  .db-shell .landing-scope-stage .area6-scope-toggle,
  .db-shell.landing-compact .landing-scope-stage .area6-scope-toggle {
    display: inline-grid !important;
    grid-template-columns: repeat(4, minmax(122px, auto)) !important;
    width: auto !important;
  }
  .db-shell .area6-periods,
  .db-shell.landing-compact .area6-periods {
    display: flex !important;
    flex-wrap: wrap !important;
    justify-content: flex-end !important;
    align-items: center !important;
    gap: 0.45rem !important;
    width: 100% !important;
  }
}
@media (max-width: 991.98px) {
  .db-shell .area6-head {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 0.85rem !important;
  }
  .db-shell .area6-head > div:first-child {
    max-width: 100% !important;
    width: 100% !important;
  }
  .db-shell .area6-head-actions {
    width: 100% !important;
    align-items: stretch !important;
    margin-left: 0 !important;
  }
  .db-shell .landing-scope-stage {
    width: 100% !important;
    display: block !important;
  }
  .db-shell .landing-scope-stage .area6-scope-toggle {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    width: 100% !important;
  }
  .db-shell .area6-periods {
    justify-content: flex-start !important;
    width: 100% !important;
  }
}

.landing-insights {
  border: 1px solid #aac3df;
  border-radius: 8px;
  background: #edf5fd;
  box-shadow: 0 16px 34px -30px rgba(3, 37, 78, .6);
}
.landing-insights__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.15rem;
  background: #063476;
  color: #fff;
}
.landing-insights__heading { display: flex; align-items: center; gap: .8rem; min-width: 0; }
.landing-insights__mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 42px;
  width: 42px;
  height: 42px;
  border: 1px solid rgba(255, 255, 255, .4);
  border-radius: 7px;
  background: #0d78d5;
  font-size: 1.05rem;
}
.landing-insights__eyebrow { display: block; color: #77d8ff; font-size: .64rem; font-weight: 900; text-transform: uppercase; }
.landing-insights__heading h2 { margin: .12rem 0 0; color: #fff; font-size: 1.02rem; font-weight: 900; letter-spacing: 0; }
.landing-insights__heading p { margin: .2rem 0 0; color: #d4e8ff; font-size: .7rem; line-height: 1.4; }
.landing-insights__status {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  flex: 0 0 auto;
  min-height: 36px;
  padding: .45rem .7rem;
  border: 1px solid rgba(255, 255, 255, .32);
  border-radius: 6px;
  background: rgba(255, 255, 255, .1);
  color: #fff;
  font-size: .68rem;
  font-weight: 800;
}
.landing-insights__grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: .85rem;
  padding: .85rem;
}
.landing-insight {
  min-width: 0;
  overflow: hidden;
  border: 1px solid #c3d3e5;
  border-radius: 8px;
  background: #fff;
}
.landing-insight--decision { grid-column: 1 / -1; }
.landing-insight--profit { grid-column: span 5; }
.landing-insight--realization { grid-column: span 7; }
.landing-insight__head {
  position: relative;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: .75rem;
  min-height: 84px;
  padding: .85rem 7.5rem .85rem .9rem;
  border-bottom: 1px solid #d5e0ec;
  background: #f5f9fd;
}
.landing-insight__title { display: flex; align-items: flex-start; gap: .65rem; min-width: 0; }
.landing-insight__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 38px;
  width: 38px;
  height: 38px;
  border-radius: 7px;
  background: #0754bd;
  color: #fff;
}
.landing-insight__title > div { min-width: 0; }
.landing-insight__title small { display: block; color: #0876c9; font-size: .6rem; font-weight: 900; }
.landing-insight__title h3 { margin: .12rem 0 0; color: #10243f; font-size: .94rem; font-weight: 900; letter-spacing: 0; }
.landing-insight__title p { margin: .18rem 0 0; max-width: 620px; color: #61758d; font-size: .68rem; line-height: 1.4; }
.landing-insight__period {
  position: relative;
  z-index: 2;
  flex: 0 0 auto;
  padding: .3rem .5rem;
  border: 1px solid #b9cde4;
  border-radius: 5px;
  background: #fff;
  color: #36536f;
  font-size: .62rem;
  font-weight: 800;
  white-space: nowrap;
}
.landing-insight__illustration {
  position: absolute;
  right: .8rem;
  bottom: 0;
  display: flex;
  align-items: flex-end;
  gap: 5px;
  width: 102px;
  height: 58px;
  padding: 0 .35rem;
  color: #0b67c5;
  opacity: .22;
  pointer-events: none;
}
.landing-insight__illustration i { align-self: center; font-size: 2rem; }
.landing-insight__illustration span { display: block; width: 10px; border-radius: 2px 2px 0 0; background: currentColor; }
.landing-insight__illustration span:nth-of-type(1) { height: 20px; }
.landing-insight__illustration span:nth-of-type(2) { height: 34px; }
.landing-insight__illustration span:nth-of-type(3) { height: 48px; }
.landing-insight__illustration--profit { color: #00a67a; }
.landing-insight__illustration--decision { color: #0754bd; }
.landing-insight__illustration--realization { color: #13a7e2; }

.landing-decision-overview {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  border-bottom: 1px solid #d5e0ec;
  background: #063476;
}
.landing-decision-overview > div { min-width: 0; padding: .7rem .9rem; border-right: 1px solid rgba(255,255,255,.18); }
.landing-decision-overview > div:last-child { border-right: 0; }
.landing-decision-overview span,
.landing-decision-overview strong,
.landing-decision-overview small { display: block; overflow-wrap: anywhere; }
.landing-decision-overview span { color: #8bdcff; font-size: .6rem; font-weight: 900; text-transform: uppercase; }
.landing-decision-overview strong { margin-top: .18rem; color: #fff; font-size: 1rem; }
.landing-decision-overview small { margin-top: .08rem; color: #d5e9ff; font-size: .68rem; font-weight: 700; }
.landing-decision-overview .is-override { background: #7b2f25; }
.landing-decision-overview .is-override span { color: #ffd19b; }
.landing-authority-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .7rem; padding: .75rem; }
.landing-authority {
  --authority: #0754bd;
  min-width: 0;
  overflow: hidden;
  border: 1px solid #c9d7e7;
  border-left: 4px solid var(--authority);
  border-radius: 6px;
  background: #fff;
}
.landing-authority.tone-cyan { --authority: #0095c8; }
.landing-authority.tone-amber { --authority: #d97706; }
.landing-authority.tone-green { --authority: #008c68; }
.landing-authority > header { display: flex; align-items: center; gap: .55rem; padding: .6rem .7rem; background: #f5f8fc; border-bottom: 1px solid #d9e3ee; }
.landing-authority__icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 32px; width: 32px; height: 32px; border-radius: 6px; background: var(--authority); color: #fff; }
.landing-authority > header div { min-width: 0; }
.landing-authority h4 { margin: 0; color: #10243f; font-size: .78rem; font-weight: 900; letter-spacing: 0; overflow-wrap: anywhere; }
.landing-authority > header p { margin: .12rem 0 0; color: #60748a; font-size: .65rem; font-weight: 700; }
.landing-authority__rules { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border-bottom: 1px solid #d9e3ee; }
.landing-authority__rules > div { min-width: 0; padding: .55rem .65rem; border-right: 1px solid #d9e3ee; }
.landing-authority__rules > div:last-child { border-right: 0; }
.landing-authority__rules span,
.landing-authority__rules strong,
.landing-authority__rules small { display: block; overflow-wrap: anywhere; }
.landing-authority__rules span { color: var(--authority); font-size: .58rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.03em; }
.landing-authority__rules strong { margin-top: .14rem; color: #0f172a; font-size: .74rem; font-weight: 850; line-height: 1.3; }
.landing-authority__rules small { margin-top: .18rem; color: #5d7087; font-size: .62rem; font-weight: 750; }
.landing-authority__rules .is-override { background: #fff7ed; }
.landing-authority__rules .is-override span { color: #b45309; }
.landing-authority__branches { padding: .3rem .55rem .45rem; }
.landing-authority__branch-head,
.landing-authority__branch-row { display: grid; grid-template-columns: minmax(120px, 1.35fr) repeat(2, minmax(88px, 1fr)); gap: .45rem; align-items: center; }
.landing-authority__branch-head { padding: .25rem .35rem; color: #718197; font-size: .55rem; font-weight: 900; text-transform: uppercase; }
.landing-authority__branch-row { padding: .4rem .35rem; border-top: 1px solid #e4ebf3; }
.landing-authority__branch-row > span { min-width: 0; }
.landing-authority__branch-row strong,
.landing-authority__branch-row b,
.landing-authority__branch-row small { display: block; overflow-wrap: anywhere; }
.landing-authority__branch-row strong { color: #18314f; font-size: .65rem; }
.landing-authority__branch-row b { color: var(--authority); font-size: .68rem; }
.landing-authority__branch-row small { margin-top: .08rem; color: #687b91; font-size: .58rem; }
.landing-authority__branch-row .is-override b { color: #b45309; }

.landing-profit-total { display: grid; grid-template-columns: 1.25fr .75fr; border-bottom: 1px solid #d7e2ed; background: #063476; }
.landing-profit-total > div { min-width: 0; padding: .75rem .85rem; border-right: 1px solid rgba(255,255,255,.18); }
.landing-profit-total > div:last-child { border-right: 0; }
.landing-profit-total span,
.landing-profit-total strong { display: block; overflow-wrap: anywhere; }
.landing-profit-total span { color: #9bdcff; font-size: .6rem; font-weight: 850; }
.landing-profit-total strong { margin-top: .16rem; color: #fff; font-size: 1rem; font-variant-numeric: tabular-nums; }
.landing-profit-branches { display: grid; gap: .55rem; padding: .75rem; }
.landing-profit-branch { min-width: 0; }
.landing-profit-branch > div:first-child { display: flex; justify-content: space-between; gap: .6rem; }
.landing-profit-branch span { color: #354d69; font-size: .66rem; font-weight: 750; overflow-wrap: anywhere; }
.landing-profit-branch strong { color: #10243f; font-size: .68rem; white-space: nowrap; }
.landing-profit-track { height: 6px; margin-top: .28rem; overflow: hidden; border-radius: 3px; background: #dce7f2; }
.landing-profit-track span { display: block; height: 100%; border-radius: inherit; background: #00a67a; }
.landing-profit-branch small { display: block; margin-top: .16rem; color: #6c7e92; font-size: .58rem; }

.landing-realization-total { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: .55rem; padding: .65rem .8rem; border-bottom: 1px solid #d6e2ee; background: #eaf5ff; }
.landing-realization-total span { color: #45607c; font-size: .62rem; font-weight: 900; text-transform: uppercase; }
.landing-realization-total strong { color: #0754bd; font-size: 1rem; }
.landing-realization-total small { color: #36536f; font-size: .66rem; font-weight: 800; }
.landing-realization-list { display: grid; gap: .45rem; padding: .7rem; }
.landing-realization-row {
  --segment: #0754bd;
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr) minmax(90px, auto);
  align-items: center;
  gap: .55rem;
  min-width: 0;
  padding: .5rem .55rem;
  border: 1px solid #d3deea;
  border-left: 3px solid var(--segment);
  border-radius: 6px;
  background: #fff;
}
.landing-realization-row.tone-cyan { --segment: #0095c8; }
.landing-realization-row.tone-green { --segment: #008c68; }
.landing-realization-row.tone-amber { --segment: #d97706; }
.landing-realization-row.tone-slate { --segment: #52677f; }
.landing-realization-row__icon { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; background: #eaf2fb; background: color-mix(in srgb, var(--segment) 13%, white); color: var(--segment); }
.landing-realization-row__main { min-width: 0; }
.landing-realization-row__main > div:first-child { display: flex; justify-content: space-between; gap: .55rem; }
.landing-realization-row__main strong { color: #18314f; font-size: .68rem; overflow-wrap: anywhere; }
.landing-realization-row__main span { color: #667a90; font-size: .6rem; font-weight: 750; }
.landing-realization-track { height: 6px; margin-top: .3rem; overflow: hidden; border-radius: 3px; background: #e0e9f2; }
.landing-realization-track span { display: block; height: 100%; border-radius: inherit; background: var(--segment); }
.landing-realization-row__value { min-width: 0; text-align: right; }
.landing-realization-row__value strong,
.landing-realization-row__value span { display: block; overflow-wrap: anywhere; }
.landing-realization-row__value strong { color: #10243f; font-size: .72rem; }
.landing-realization-row__value span { margin-top: .1rem; color: var(--segment); font-size: .6rem; font-weight: 900; }
.landing-insight__note { display: flex; align-items: flex-start; gap: .35rem; margin: 0; padding: .55rem .75rem; border-top: 1px solid #d9e3ee; background: #f5f8fc; color: #5c7189; font-size: .6rem; line-height: 1.4; }
.landing-insight__note i { margin-top: .1rem; color: #0876c9; }

@media (max-width: 1199.98px) {
  .landing-insight--profit,
  .landing-insight--realization { grid-column: 1 / -1; }
}
@media (max-width: 991.98px) {
  .landing-authority-grid { grid-template-columns: 1fr; }
  .landing-insight__head { padding-right: 6.5rem; }
}
@media (max-width: 767.98px) {
  .landing-scope-stage { grid-template-columns: 1fr; }
  .db-shell .landing-scope-stage .area6-scope-toggle { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .landing-scope-visual { min-height: 66px; }
  .landing-insights__head { align-items: flex-start; flex-direction: column; }
  .landing-insights__status { align-self: stretch; justify-content: center; }
  .landing-decision-overview { grid-template-columns: 1fr; }
  .landing-decision-overview > div { border-right: 0; border-bottom: 1px solid rgba(255,255,255,.18); }
  .landing-decision-overview > div:last-child { border-bottom: 0; }
}
@media (max-width: 575.98px) {
  .landing-insights__grid { padding: .55rem; gap: .55rem; }
  .landing-insights__head { padding: .85rem; }
  .landing-insights__heading { align-items: flex-start; }
  .landing-insights__heading p { font-size: .66rem; }
  .landing-insight__head { min-height: 0; padding: .75rem; flex-direction: column; }
  .landing-insight__period { align-self: flex-start; }
  .landing-insight__illustration { display: none; }
  .landing-authority__rules { grid-template-columns: 1fr; }
  .landing-authority__rules > div { border-right: 0; border-bottom: 1px solid #d9e3ee; }
  .landing-authority__rules > div:last-child { border-bottom: 0; }
  .landing-profit-total { grid-template-columns: 1fr; }
  .landing-profit-total > div { border-right: 0; border-bottom: 1px solid rgba(255,255,255,.18); }
  .landing-profit-total > div:last-child { border-bottom: 0; }
}
@media (max-width: 479.98px) {
  .landing-authority__branch-head { display: none; }
  .landing-authority__branch-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .landing-authority__branch-row > span:first-child { grid-column: 1 / -1; padding-bottom: .25rem; border-bottom: 1px dashed #cbd8e6; }
  .landing-realization-row { grid-template-columns: 32px minmax(0, 1fr); }
  .landing-realization-row__value { grid-column: 2; display: flex; justify-content: space-between; gap: .4rem; text-align: left; }
  .landing-realization-total { grid-template-columns: 1fr auto; }
  .landing-realization-total small { grid-column: 1 / -1; }
}
@media (max-width: 319.98px) {
  .area6-scope-btn__copy small { display: none; }
  .landing-scope-stage .area6-scope-btn { min-height: 48px; padding: .35rem; }
  .area6-scope-btn__icon { flex-basis: 30px; width: 30px; height: 30px; }

  .sme-ops-rm-visual {
    grid-template-columns: minmax(0, 1fr);
    min-height: 0;
  }
  .sme-ops-rm-illustration {
    grid-column: 1;
    grid-row: auto;
    width: min(100%, 140px);
    max-height: 160px;
  }
  .sme-ops .sme-ops-vendor-item {
    grid-template-columns: minmax(0, 1fr);
    gap: .5rem;
    padding: .55rem;
  }
  .sme-ops .sme-ops-vendor-item h4 {
    font-size: .62rem;
    overflow-wrap: break-word;
  }
  .sme-ops-vendor-metrics {
    padding-top: .45rem;
    border-top: 1px solid #d5e0ec;
    border-left: 0;
  }
}
@media (max-width: 575.98px) {
  .sme-ops-intro__actions > .sme-ops-week-badge {
    grid-column: 1 / -1;
    width: 100%;
  }
  .sme-ops-intro__actions > .sme-ops-live-label { grid-column: 1; }
  .sme-ops-intro__actions > .sme-ops-refresh { grid-column: 2; }
}
.chart-empty { display: none; }
.chart-panel.is-empty canvas { display: none !important; }
.chart-panel.is-empty .chart-empty {
  display: flex;
  min-height: 210px;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  color: #64748b;
  font-size: .75rem;
  text-align: center;
}

/* Landing responsive cohesion: follow the actual content width after the sidebar. */
.db-shell :where(
  .ap-week-toolbar,
  .ap-week-toolbar__copy,
  .ap-week-toggle,
  .landing-scope-stage,
  .sme-ops-intro,
  .sme-ops-feature,
  .sme-ops-vendor-grid,
  .sme-ops-vendor-item,
  .sme-ops-vendor-item__identity,
  .sme-ops-vendor-metrics,
  .micro-ops,
  .micro-ops-hero,
  .micro-ops-command-ribbon,
  .micro-realization-layout,
  .micro-realization-products,
  .micro-mantri-stage,
  .micro-need-stage,
  .loan-analytics-card,
  .loan-analytics-title,
  .loan-quality-layout,
  .loan-quality-legend,
  .tariff-overview
) {
  min-width: 0;
  max-width: 100%;
}

.db-shell .ap-week-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}
.db-shell .ap-week-toggle {
  display: flex;
  align-items: center;
  gap: .45rem;
  width: auto;
  min-width: 0;
}
.db-shell .trend-dates-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: .25rem;
  padding-inline: 0;
}
.db-shell .trend-date-label {
  width: auto;
  min-width: 0;
  transform: none;
  overflow-wrap: anywhere;
}

.db-shell.landing-tablet .ap-week-toolbar,
.db-shell.landing-narrow .ap-week-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: .55rem;
  margin-inline: .9rem;
  padding: .45rem .85rem;
}
.db-shell.landing-tablet .ap-week-toggle,
.db-shell.landing-narrow .ap-week-toggle {
  display: flex;
  flex-wrap: nowrap;
  overflow-x: auto;
  width: auto;
}
.db-shell.landing-mobile .ap-week-toolbar {
  margin-inline: .65rem;
  padding: .45rem .75rem;
  flex-direction: column;
  align-items: flex-start;
}
.db-shell.landing-mobile .ap-week-toggle {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(65px, 1fr));
  width: 100%;
  gap: .35rem;
}
.db-shell.landing-tablet .ap-week-btn,
.db-shell.landing-mobile .ap-week-btn {
  min-width: 0;
  min-height: 36px;
  padding-inline: .35rem;
}

/* Scope and SME operations: reflow at the effective shell width. */
.db-shell.landing-narrow .landing-scope-stage {
  grid-template-columns: minmax(0, 1fr);
}
.db-shell.landing-narrow .sme-ops-intro {
  grid-template-columns: auto minmax(0, 1fr);
}
.db-shell.landing-narrow .sme-ops-intro__actions {
  grid-column: 1 / -1;
  width: 100%;
  justify-content: flex-start;
  flex-wrap: wrap;
  padding-left: 62px;
}
.db-shell.landing-tablet .sme-ops-feature--quadrant,
.db-shell.landing-narrow .sme-ops-feature--quadrant {
  grid-template-columns: minmax(0, 1fr);
}
.db-shell.landing-tablet .sme-ops-rm-visual,
.db-shell.landing-narrow .sme-ops-rm-visual {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(126px, 180px);
  min-height: 220px;
  padding-bottom: .8rem;
}
.db-shell.landing-tablet .sme-ops-rm-illustration,
.db-shell.landing-narrow .sme-ops-rm-illustration {
  grid-column: 2;
  grid-row: 1 / 3;
  align-self: end;
  width: min(100%, 180px);
  max-height: 220px;
}
.db-shell.landing-tablet .sme-ops-rm-total,
.db-shell.landing-narrow .sme-ops-rm-total {
  align-self: end;
  margin: 0;
}
.db-shell.landing-tablet .sme-ops .sme-ops-quadrant-summary {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.db-shell.landing-tablet .sme-ops .sme-ops-branch-row {
  grid-template-columns: minmax(180px, 0.7fr) minmax(0, 1.5fr);
}
.db-shell.landing-tablet .sme-ops .sme-ops-branch-row__quadrants {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.db-shell.landing-narrow .sme-ops .sme-ops-rm-list,
.db-shell.landing-narrow .sme-ops .sme-ops-vendor-grid {
  grid-template-columns: minmax(0, 1fr);
}
.db-shell.landing-narrow .sme-ops .sme-ops-quadrant-summary,
.db-shell.landing-narrow .sme-ops .sme-ops-branch-row__quadrants {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.db-shell.landing-narrow .sme-ops .sme-ops-branch-row {
  grid-template-columns: minmax(0, 1fr);
}
.db-shell.landing-narrow .sme-ops .sme-ops-vendor-item {
  grid-template-columns: minmax(0, 1fr);
  gap: .58rem;
  min-height: 0;
  padding: .68rem;
}
.db-shell.landing-narrow .sme-ops-vendor-metrics {
  padding-top: .55rem;
  border-top: 1px solid #d5e0ec;
  border-left: 0;
}
.db-shell.landing-narrow .sme-ops .sme-ops-vendor-item h4 {
  font-size: .72rem;
  overflow-wrap: break-word;
}
.db-shell.landing-narrow .sme-ops-vendor-metrics span {
  min-height: 0;
  font-size: .62rem;
}
.db-shell.landing-narrow .sme-ops-vendor-metrics strong {
  font-size: .82rem;
}
.db-shell.landing-narrow .sme-ops-frequency-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.db-shell.landing-mobile .sme-ops-intro__actions {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 44px;
  padding-left: 0;
}
.db-shell.landing-mobile .sme-ops-intro__actions > .sme-ops-week-badge {
  grid-column: 1 / -1;
}
.db-shell.landing-mobile .sme-ops-rm-visual {
  grid-template-columns: minmax(0, 1fr) 126px;
  min-height: 220px;
}
.db-shell.landing-mobile .sme-ops-rm-illustration {
  width: 126px;
  max-height: 188px;
}
.db-shell.landing-mobile .sme-ops-frequency-summary,
.db-shell.landing-mobile .sme-ops-frequency-grid {
  grid-template-columns: minmax(0, 1fr);
}
.db-shell.landing-mobile .sme-ops-frequency-summary > div + div {
  border-top: 1px solid rgba(255, 255, 255, .24);
  border-left: 0;
}

/* Micro operations: preserve illustrations while preventing compressed desktop grids. */
.db-shell.landing-narrow .micro-ops-hero {
  grid-template-columns: minmax(0, 1fr);
  min-height: 0;
  padding: 1rem;
}
.db-shell.landing-narrow .micro-ops-hero__copy {
  gap: .9rem;
}
.db-shell.landing-narrow .micro-ops-hero__visual {
  min-height: 154px;
}
.db-shell.landing-narrow .micro-ops-hero__visual svg {
  width: min(100%, 250px);
  height: 150px;
}
.db-shell.landing-narrow .micro-ops-command-ribbon {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.db-shell.landing-narrow .micro-ops-command-ribbon__metric:nth-child(3) {
  border-left: 0;
}
.db-shell.landing-narrow .micro-ops-command-ribbon__metric:nth-child(n + 3) {
  border-top: 1px solid rgba(151, 205, 245, .18);
}
.db-shell.landing-narrow .micro-realization-layout,
.db-shell.landing-narrow .micro-mantri-stage {
  grid-template-columns: minmax(0, 1fr);
}
.db-shell.landing-narrow .micro-realization-layout {
  padding: .75rem;
}
.db-shell.landing-narrow .micro-realization-stack {
  grid-template-rows: auto;
}
.db-shell.landing-narrow .micro-mantri-stage {
  min-height: 0;
}
.db-shell.landing-narrow .micro-mantri-stage__copy {
  padding: 1.1rem;
}
.db-shell.landing-narrow .micro-mantri-stage__visual {
  min-height: 150px;
}
.db-shell.landing-narrow .micro-mantri-stage__visual svg {
  height: 145px;
}
.db-shell.landing-narrow .micro-need-stage {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.db-shell.landing-narrow .micro-need-visual {
  display: none;
}
.db-shell.landing-narrow .micro-frequency-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.db-shell.landing-narrow .micro-pdwk-status-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.db-shell.landing-mobile .micro-ops-command-ribbon,
.db-shell.landing-mobile .micro-realization-type-grid--primary,
.db-shell.landing-mobile .micro-need-stage,
.db-shell.landing-mobile .micro-frequency-grid,
.db-shell.landing-mobile .micro-pdwk-status-grid {
  grid-template-columns: minmax(0, 1fr);
}
.db-shell.landing-mobile .micro-ops-command-ribbon__metric {
  border-top: 1px solid rgba(151, 205, 245, .18);
  border-left: 0;
}
.db-shell.landing-mobile .micro-pdwk-limit__toolbar,
.db-shell.landing-mobile .micro-ops-section__head {
  align-items: stretch;
  flex-direction: column;
}
.db-shell.landing-mobile .micro-pdwk-role-toggle {
  width: 100%;
}
.db-shell.landing-mobile .micro-pdwk-role-toggle button {
  min-width: 0;
}

/* Wide data tables keep their own scroll surface and compact frozen identity columns. */
.db-shell.landing-narrow .micro-mantri-table-wrap {
  max-height: none;
  overflow-x: auto;
  overflow-y: visible;
  -webkit-overflow-scrolling: touch;
  scrollbar-gutter: auto;
}
.db-shell.landing-narrow .micro-mantri-table--summary thead tr:first-child th:nth-child(1),
.db-shell.landing-narrow .micro-mantri-table--summary tbody td:nth-child(1) {
  position: sticky;
  left: 0;
  width: 42px;
  min-width: 42px;
}
.db-shell.landing-narrow .micro-mantri-table--summary thead tr:first-child th:nth-child(2),
.db-shell.landing-narrow .micro-mantri-table--summary tbody td:nth-child(2) {
  position: sticky;
  left: 42px;
  width: 64.5px;
  min-width: 64.5px;
  max-width: 64.5px;
}
.db-shell.landing-narrow .micro-mantri-table--summary thead tr:first-child th:nth-child(3),
.db-shell.landing-narrow .micro-mantri-table--summary tbody th {
  left: 106.5px;
  width: 136px;
  min-width: 136px;
}
.db-shell.landing-mobile .micro-mantri-table th,
.db-shell.landing-mobile .micro-mantri-table td {
  padding: .58rem .5rem;
}

/* Consumer/SME/Micro time-series cards remain readable inside constrained shells. */
.db-shell.landing-narrow .loan-quality-layout {
  grid-template-columns: minmax(0, 1fr);
  padding: 0 .8rem .85rem;
}
.db-shell.landing-narrow .loan-quality-legend {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-content: stretch;
}
.db-shell.landing-narrow .loan-quality-legend > small {
  grid-column: 1 / -1;
}
.db-shell .loan-quality-legend__item,
.db-shell .loan-quality-legend__item div {
  min-width: 0;
}
.db-shell .loan-quality-legend__item span,
.db-shell .loan-quality-legend__item strong {
  overflow-wrap: anywhere;
}
.db-shell.landing-narrow .tariff-overview {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  padding-inline: .8rem;
}
.db-shell.landing-narrow .tariff-series-chart-wrap {
  margin-inline: .8rem;
}
.db-shell.landing-mobile .loan-analytics-head {
  align-items: flex-start;
  flex-direction: column;
  padding: .9rem .8rem;
}
.db-shell.landing-mobile .loan-analytics-title {
  width: 100%;
}
.db-shell.landing-mobile .loan-analytics-latest,
.db-shell.landing-mobile .tariff-delta {
  align-self: flex-start;
  justify-items: start;
  max-width: 100%;
}
.db-shell.landing-mobile .loan-quality-legend,
.db-shell.landing-mobile .tariff-overview {
  grid-template-columns: minmax(0, 1fr);
}
.db-shell.landing-mobile .loan-quality-chart-wrap,
.db-shell.landing-mobile .tariff-series-chart-wrap {
  height: 245px;
}

@media (max-width: 419.98px) {
  .db-shell.landing-mobile .micro-realization-stack .micro-realization-type-card.type-nett {
    grid-template-columns: minmax(0, 1fr);
  }
  .db-shell.landing-mobile .micro-realization-stack .micro-realization-type-card.type-nett .micro-realization-type-card__icon {
    grid-row: auto;
  }
  .db-shell.landing-mobile .micro-realization-stack .micro-realization-type-card.type-nett .micro-nett-type-list {
    grid-column: auto;
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 319.98px) {
  .db-shell.landing-mobile .ap-prognosa-strip {
    grid-template-columns: minmax(0, 1fr);
  }
  .db-shell.landing-mobile .ap-prognosa-item {
    padding: .5rem .25rem;
  }
  .db-shell.landing-mobile .ap-prognosa-item + .ap-prognosa-item {
    border-top: 1px solid rgba(0, 0, 0, .08);
    border-left: 0;
  }
  .db-shell.landing-mobile .sme-ops-rm-visual {
    grid-template-columns: minmax(0, 1fr);
    min-height: 0;
  }
  .db-shell.landing-mobile .sme-ops-rm-illustration {
    grid-column: 1;
    grid-row: auto;
    justify-self: center;
    width: min(100%, 140px);
    max-height: 160px;
  }
  .db-shell.landing-mobile .sme-ops .sme-ops-quadrant-summary,
  .db-shell.landing-mobile .sme-ops .sme-ops-branch-row__quadrants {
    grid-template-columns: minmax(0, 1fr);
  }
  .db-shell.landing-mobile .micro-mantri-table--summary thead tr:first-child th:nth-child(-n + 3),
  .db-shell.landing-mobile .micro-mantri-table--summary tbody td:nth-child(-n + 2),
  .db-shell.landing-mobile .micro-mantri-table--summary tbody th,
  .db-shell.landing-mobile .micro-mantri-table--summary tfoot th:first-child {
    position: static;
    left: auto;
  }
}

@media (orientation: landscape) and (max-height: 520px) {
  .db-shell.landing-narrow .micro-ops-hero__visual,
  .db-shell.landing-narrow .micro-mantri-stage__visual {
    min-height: 132px;
  }
  .db-shell.landing-narrow .micro-ops-hero__visual svg,
  .db-shell.landing-narrow .micro-mantri-stage__visual svg {
    height: 128px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .loan-analytics-card *, .sme-ops-vendor-item *, .sme-vendor-modal * { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; }
}
</style>

<div class="db-shell pt-2 {{ $area6DefaultScope === 'sme' ? 'sme-operations-active' : '' }} {{ $area6DefaultScope === 'micro' ? 'micro-performance-active' : '' }} {{ $area6DefaultScope === 'consumer' ? 'consumer-active' : '' }}"
     data-loan-analytics-url="{{ route('dashboard.loan-analytics', ['periode' => $selectedPeriod, 'cabang' => $selectedLandingBranch ?? 'area6']) }}"
     data-sme-vendor-nominatives-url="{{ route('dashboard.sme-vendor-nominatives', ['cabang' => $selectedLandingBranch ?? 'area6']) }}">
  {{-- HEADER --}}
  <div class="db-header">
    <div class="db-brand">
      <div class="db-logo">
        <img src="{{ asset('images/a-six-logo.svg') }}" alt="A-SIX">
      </div>
      <div>
        <div class="db-title">A-SIX {{ $landingBranchLabel ?? 'Area 6' }} — Dashboard Realtime</div>
        <div class="db-subtitle">Ringkasan posisi keuangan, portofolio, dan 8 strategi digital</div>
      </div>
    </div>
    <div class="db-meta">
      @if(!empty($landingBranchOptions))
      <label class="db-branch-picker" title="{{ !empty($landingBranchLocked) ? 'Cabang dikunci sesuai wilayah user' : 'Ubah lingkup angka landing page' }}">
        <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
        <span class="sr-only">Pilih cabang landing page</span>
        <select id="landing-branch-selector" {{ !empty($landingBranchLocked) ? 'disabled' : '' }}>
          @foreach($landingBranchOptions as $branchKey => $branchLabel)
            <option value="{{ $branchKey }}" {{ $branchKey === ($selectedLandingBranch ?? 'area6') ? 'selected' : '' }}>{{ $branchLabel }}</option>
          @endforeach
        </select>
      </label>
      @endif

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
      <div class="kc-label"><span class="kc-icon-box"><i class="fas fa-piggy-bank"></i></span>Dana Simpanan</div>
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
      <div class="kc-label"><span class="kc-icon-box"><i class="fas fa-hand-holding-usd"></i></span>OS</div>
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
      <div class="kc-label"><span class="kc-icon-box"><i class="fas fa-layer-group"></i></span>LDR (Loan to Deposit Ratio)</div>
      <div class="kc-val">{{ data_get($portfolioReport,'value','–') }}</div>
      <div class="kc-sub" style="max-width:150px;white-space:normal;font-size:.58rem;">{{ data_get($portfolioReport,'meta','–') }}</div>
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($portfolioReport,"detail_payload",[]))' data-link="{{ data_get($portfolioReport,'link','#') }}" data-link-label="{{ data_get($portfolioReport,'link_label','Lihat report') }}">Detail <i class="fas fa-info-circle"></i></button>
    </div>

    {{-- Card 4: Growth Simpanan MoM --}}
    @php $m4 = data_get($metrics, 2); @endphp
    @if($m4)
    <div class="kpi-card">
      <div class="kc-label"><span class="kc-icon-box"><i class="{{ data_get($m4,'icon','fas fa-wallet') }}"></i></span>{{ data_get($m4,'label','–') }}</div>
      <div class="kc-val" style="font-size:1.32rem;">{{ data_get($m4,'value','–') }}</div>
      <div class="kc-sub {{ data_get($m4,'delta_class','text-muted') }}" style="font-size:0.62rem;font-weight:700;">{{ data_get($m4,'delta','–') }}</div>
    </div>
    @endif

    {{-- Card 5: Growth Pinjaman MoM --}}
    @php $m5 = data_get($metrics, 3); @endphp
    @if($m5)
    <div class="kpi-card">
      <div class="kc-label"><span class="kc-icon-box"><i class="{{ data_get($m5,'icon','fas fa-database') }}"></i></span>{{ data_get($m5,'label','–') }}</div>
      <div class="kc-val" style="font-size:1.32rem;">{{ data_get($m5,'value','–') }}</div>
      <div class="kc-sub {{ data_get($m5,'delta_class','text-muted') }}" style="font-size:0.62rem;font-weight:700;">{{ data_get($m5,'delta','–') }}</div>
    </div>
    @endif

    {{-- Card 6: Rasio CASA (Harian) --}}
    @php $m6 = collect($digitalCards)->firstWhere('key', 'casa'); @endphp
    @if($m6)
    <div class="kpi-card">
      <div class="kc-label"><span class="kc-icon-box"><i class="fas fa-percentage"></i></span>CASA Ratio (Harian)</div>
      <div class="kc-val" style="font-size:1.32rem;">{{ data_get($m6,'value','–') }}</div>
      <div class="kc-sub" style="font-size:0.62rem;color:#64748b;font-weight:500;">{{ data_get($m6,'meta','–') }}</div>
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($m6,"detail_payload",[]))' data-link="{{ data_get($m6,'link','#') }}" data-link-label="{{ data_get($m6,'link_label','Lihat detail') }}">Detail <i class="fas fa-info-circle"></i></button>
    </div>
    @else
    <div class="kpi-card">
      <div class="kc-label"><span class="kc-icon-box"><i class="fas fa-percentage"></i></span>CASA Ratio (Harian)</div>
      <div class="kc-val" style="font-size:1.32rem;">–</div>
      <div class="kc-sub" style="font-size:0.62rem;color:#64748b;">Data belum tersedia</div>
    </div>
    @endif
  </div>

  {{-- AREA 6 PORTFOLIO SUMMARY --}}
  <section class="area6-panel">
    <div class="area6-head">
      <div>
        <div class="area6-title" id="area6-scope-title">Kinerja {{ $landingBranchLabel ?? 'Area 6' }}</div>
        <div class="area6-sub" id="area6-scope-subtitle">Ringkasan posisi dan kinerja {{ $landingBranchLabel ?? 'Area 6' }}.</div>
      </div>
      <div class="area6-head-actions">
        <div class="landing-scope-stage">
        @if(!empty($area6RankingModes))
        @php
          $scopePresentation = [
            'area6' => ['icon' => 'fas fa-globe-asia', 'caption' => 'Konsolidasi', 'title' => 'Kinerja '.$landingBranchLabel, 'subtitle' => 'Posisi, kualitas, putusan, dan realisasi seluruh segmen.'],
            'sme' => ['icon' => 'fas fa-briefcase', 'caption' => 'Small Business', 'title' => 'SME Operating Desk', 'subtitle' => 'Kuadran RM, prospek, pipeline, perpanjangan, dan restrukturisasi.'],
            'consumer' => ['icon' => 'fas fa-users', 'caption' => 'Consumer Banking', 'title' => 'Kinerja Konsumer', 'subtitle' => 'Posisi dan kualitas portofolio konsumer pada lingkup aktif.'],
            'micro' => ['icon' => 'fas fa-store', 'caption' => 'Micro Banking', 'title' => 'Kinerja Mikro', 'subtitle' => 'Realisasi, PDWK, produktivitas, dan kebutuhan kinerja Mikro.'],
          ];
        @endphp
        <div class="area6-scope-toggle" role="group" aria-label="Pilihan lingkup kinerja">
          @foreach($area6RankingModes as $scopeKey => $scopePayload)
          @php $scopeUi = $scopePresentation[$scopeKey] ?? ['icon' => 'fas fa-layer-group', 'caption' => 'Portfolio', 'title' => 'Kinerja '.$landingBranchLabel, 'subtitle' => 'Ringkasan kinerja pada lingkup aktif.']; @endphp
          <button type="button"
                  class="area6-scope-btn {{ $scopeKey === $area6DefaultScope ? 'active' : '' }}"
                  data-area6-scope="{{ $scopeKey }}"
                  data-scope-title="{{ $scopeUi['title'] }}"
                  data-scope-subtitle="{{ $scopeUi['subtitle'] }}">
            <span class="area6-scope-btn__icon"><i class="{{ $scopeUi['icon'] }}" aria-hidden="true"></i></span>
            <span class="area6-scope-btn__copy">
              <strong>{{ data_get($scopePayload, 'label', strtoupper($scopeKey)) }}</strong>
              <small>{{ $scopeUi['caption'] }}</small>
            </span>
          </button>
          @endforeach
        </div>
        @endif
        <div class="landing-scope-visual" aria-hidden="true"></div>
        </div>
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
      $scopePrognosa = (array) data_get($contentPortfolio, 'prognosa', []);
      $scopePrognosaWeeks = (array) data_get($scopePrognosa, 'weeks', []);
      $scopeActiveWeek = (string) data_get($scopePrognosa, 'active_week', data_get($area6Portfolio, 'prognosa.week_label', 'W1'));
    @endphp
    <div class="area6-scope-content {{ $contentScopeKey === $area6DefaultScope ? '' : 'd-none' }}" data-area6-content-scope="{{ $contentScopeKey }}">
    @if(!empty($scopePrognosa['available']) && !empty($scopePrognosaWeeks))
    <div class="ap-week-toolbar" data-prognosa-week-control="{{ $contentScopeKey }}">
      <div class="ap-week-toolbar__copy">
        <span><i class="fas fa-calendar-check"></i> Prognosa Mingguan</span>
      </div>
      <div class="ap-week-toggle" role="tablist" aria-label="Pilih week prognosa {{ strtoupper($contentScopeKey) }}">
        @foreach($scopePrognosaWeeks as $weekOption)
          @php
            $weekOptionLabel = (string) data_get($weekOption, 'week_label', 'W1');
            $weekOptionAvailable = (bool) data_get($weekOption, 'available', false);
            $weekOptionActive = $weekOptionLabel === $scopeActiveWeek;
          @endphp
          <button type="button"
                  role="tab"
                  class="ap-week-btn {{ $weekOptionActive ? 'active' : '' }}"
                  data-prognosa-week-select="{{ $weekOptionLabel }}"
                  aria-selected="{{ $weekOptionActive ? 'true' : 'false' }}"
                  {{ !$weekOptionAvailable ? 'disabled' : '' }}>
            <span>{{ data_get($weekOption, 'label', $weekOptionLabel) }}</span>
            <small>{{ data_get($weekOption, 'position_label', data_get($weekOption, 'cutoff_label', '-')) }}</small>
          </button>
        @endforeach
      </div>
    </div>
    @endif
    <div class="area6-card-grid {{ count($contentCards) === 3 ? 'area6-card-grid--three' : '' }}">
      @forelse($contentCards as $card)
        @if(in_array(data_get($card, 'key'), ['os', 'sml', 'npl', 'recovery']))
          @php
            $key = data_get($card, 'key');
            $pctColor = data_get($card, 'pct_color');
            $gapColor = data_get($card, 'gap_color');
            $deltas = data_get($card, 'deltas', []);
          @endphp
          <button type="button"
                  class="area6-card-premium dashboard-detail-trigger"
                  data-metric="{{ $key }}"
                  aria-label="Lihat detail {{ data_get($card, 'header_title', strtoupper($key)) }}"
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

              @if(data_get($card, 'prognosa.available'))
                @php
                  $cardPrognosaWeeks = (array) data_get($card, 'prognosa.weeks', []);
                  $cardActiveWeek = (string) data_get($card, 'prognosa.active_week', data_get($card, 'prognosa.week_label', 'W1'));
                @endphp
                @foreach($cardPrognosaWeeks as $cardWeekLabel => $cardWeek)
                  @continue(empty($cardWeek['available']))
                  <div class="ap-prognosa-strip"
                       data-prognosa-week-panel="{{ $cardWeekLabel }}"
                       aria-label="{{ data_get($cardWeek, 'label', 'Prognosa mingguan') }}"
                       {{ $cardWeekLabel !== $cardActiveWeek ? 'hidden' : '' }}>
                    <div class="ap-prognosa-item">
                      <div class="ap-prognosa-label">{{ data_get($cardWeek, 'label') }}</div>
                      <div class="ap-prognosa-value">{{ data_get($cardWeek, 'value') }}</div>
                      <div class="ap-prognosa-unit">Rp Juta</div>
                    </div>
                    <div class="ap-prognosa-item">
                      <div class="ap-prognosa-label">{{ data_get($cardWeek, 'achievement_label') }} &middot; {{ data_get($cardWeek, 'actual_label', '-') }}</div>
                      <div class="ap-prognosa-value text-{{ data_get($cardWeek, 'achievement_color', 'muted') }}-flat">
                        {{ data_get($cardWeek, 'achievement') }}
                      </div>
                      <div class="ap-prognosa-unit">
                        {{ data_get($cardWeek, 'actual_available') ? 'Posisi ' . data_get($cardWeek, 'actual_value') . ' Rp Juta' : 'Posisi belum tersedia' }}
                      </div>
                    </div>
                  </div>
                @endforeach
              @endif
              
              <!-- Dashed Divider -->
              <hr class="ap-dashed-divider">
              
              <!-- Row 3: Deltas -->
              <div class="ap-deltas">
                @foreach(['dtd' => 'DtD', 'mtd' => 'MtD', 'mom' => 'MtM', 'ytd' => 'YtD'] as $dKey => $dLabel)
                  @php
                    $delta = data_get($deltas, $dKey, []);
                    $deltaVal = trim((string) data_get($delta, 'value', '-'));
                    $deltaType = (string) data_get($delta, 'type', 'up');
                    $deltaRaw = data_get($delta, 'raw');

                    $cleanNum = preg_replace('/[^\d]/', '', $deltaVal);
                    $isZero = ($cleanNum === '0' || $deltaVal === '-' || $deltaVal === '');

                    $isNegative = !$isZero && (
                        str_starts_with($deltaVal, '(')
                        || $deltaType === 'down'
                        || (is_numeric($deltaRaw) && (float) $deltaRaw < 0)
                    );

                    $isPositive = !$isZero && (
                        str_starts_with($deltaVal, '+')
                        || ($deltaType === 'up' && !str_starts_with($deltaVal, '('))
                        || (is_numeric($deltaRaw) && (float) $deltaRaw > 0)
                    );

                    if (in_array($key, ['sml', 'npl'], true)) {
                      // SML & NPL (Risiko Kredit):
                      // Jika nilainya turun (*) adalah hijau dan jika naik adalah merah
                      if ($isNegative) {
                        $deltaColor = 'green';
                        $deltaArrow = 'down';
                      } elseif ($isPositive) {
                        $deltaColor = 'red';
                        $deltaArrow = 'up';
                      } else {
                        $deltaColor = 'green';
                        $deltaArrow = $deltaType === 'down' ? 'down' : ($deltaType === 'up' ? 'up' : 'minus');
                      }
                    } else {
                      // OS / Recovery / Portofolio Aset:
                      // Jika nilainya turun (*) adalah merah dan jika naik adalah hijau
                      if ($isNegative) {
                        $deltaColor = 'red';
                        $deltaArrow = 'down';
                      } elseif ($isPositive) {
                        $deltaColor = 'green';
                        $deltaArrow = 'up';
                      } else {
                        $deltaColor = 'green';
                        $deltaArrow = $deltaType === 'down' ? 'down' : ($deltaType === 'up' ? 'up' : 'minus');
                      }
                    }
                  @endphp
                  <div class="ap-delta-item">
                    <div class="ap-delta-label">{{ $dLabel }}</div>
                    <div class="ap-delta-val text-{{ $deltaColor }}-flat">
                      {{ $deltaVal }}
                    </div>
                    <div class="ap-delta-arrow text-{{ $deltaColor }}-flat">
                      <i class="fas {{ $deltaArrow === 'minus' ? 'fa-minus' : 'fa-arrow-' . $deltaArrow }}"></i>
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
      $segmentPerformanceTitle = $contentScopeKey === 'micro'
        ? 'KINERJA PRODUK MIKRO (Rp Juta)'
        : 'KINERJA PER SEGMEN (Rp Juta)';
      $metricColumnSpan = $contentScopeKey === 'micro' ? 5 : 3;
      $referencePositions = (array) data_get($segmentPerf, 'reference_positions', []);
      $ytdMonthYear = data_get($referencePositions, 'ytd.month_year', 'Des 25');
      $mtdMonthYear = data_get($referencePositions, 'mtd.month_year', 'Jul 26');
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
          <div class="asc-header-title">{{ $segmentPerformanceTitle }}</div>
        </div>
        
        <!-- Card Body / Content with horizontal scrolling wrapper for responsiveness -->
        <div class="asc-body-wrapper">
          <table class="asc-table {{ $contentScopeKey === 'micro' ? 'asc-table--micro-products' : '' }}">
            <thead>
              <tr>
                <th rowspan="2" class="asc-th-seg">Segmen</th>
                <th colspan="{{ $metricColumnSpan }}" class="asc-th-metric asc-th-os">OUTSTANDING (OS)</th>
                <th colspan="{{ $metricColumnSpan }}" class="asc-th-metric asc-th-sml">SPECIAL MENTION LOAN (SML)</th>
                <th colspan="{{ $metricColumnSpan }}" class="asc-th-metric asc-th-npl">NON-PERFORMING LOAN (NPL)</th>
              </tr>
              <tr class="asc-sub-tr">
                <!-- OS Subheaders -->
                <th class="asc-th-sub asc-th-penc"><span class="legend-box bg-os-blue"></span> Pencapaian</th>
                @if($contentScopeKey === 'micro')
                  <th class="asc-th-sub asc-th-ref" title="{{ data_get($referencePositions, 'ytd.label', 'Posisi YTD') }}"><span class="legend-box bg-ytd"></span> YTD {{ $ytdMonthYear }}</th>
                  <th class="asc-th-sub asc-th-ref" title="{{ data_get($referencePositions, 'mtd.label', 'Posisi MTD') }}"><span class="legend-box bg-mtd"></span> MTD {{ $mtdMonthYear }}</th>
                @endif
                <th class="asc-th-sub asc-th-rka"><span class="legend-box bg-gray"></span> RKA {{ $rkaMonthYear }}</th>
                <th class="asc-th-sub asc-th-pct">% Penc.</th>
                <!-- SML Subheaders -->
                <th class="asc-th-sub asc-th-penc"><span class="legend-box bg-sml-blue"></span> Pencapaian</th>
                @if($contentScopeKey === 'micro')
                  <th class="asc-th-sub asc-th-ref" title="{{ data_get($referencePositions, 'ytd.label', 'Posisi YTD') }}"><span class="legend-box bg-ytd"></span> YTD {{ $ytdMonthYear }}</th>
                  <th class="asc-th-sub asc-th-ref" title="{{ data_get($referencePositions, 'mtd.label', 'Posisi MTD') }}"><span class="legend-box bg-mtd"></span> MTD {{ $mtdMonthYear }}</th>
                @endif
                <th class="asc-th-sub asc-th-rka"><span class="legend-box bg-gray"></span> RKA {{ $rkaMonthYear }}</th>
                <th class="asc-th-sub asc-th-pct">% Penc.</th>
                <!-- NPL Subheaders -->
                <th class="asc-th-sub asc-th-penc"><span class="legend-box bg-npl-blue"></span> Pencapaian</th>
                @if($contentScopeKey === 'micro')
                  <th class="asc-th-sub asc-th-ref" title="{{ data_get($referencePositions, 'ytd.label', 'Posisi YTD') }}"><span class="legend-box bg-ytd"></span> YTD {{ $ytdMonthYear }}</th>
                  <th class="asc-th-sub asc-th-ref" title="{{ data_get($referencePositions, 'mtd.label', 'Posisi MTD') }}"><span class="legend-box bg-mtd"></span> MTD {{ $mtdMonthYear }}</th>
                @endif
                <th class="asc-th-sub asc-th-rka"><span class="legend-box bg-gray"></span> RKA {{ $rkaMonthYear }}</th>
                <th class="asc-th-sub asc-th-pct">% Penc.</th>
              </tr>
            </thead>
            <tbody>
              @foreach($segments as $seg)
              @php
                $os = data_get($seg, 'os', []);
                $sml = data_get($seg, 'sml', []);
                $npl = data_get($seg, 'npl', []);
              @endphp
              <tr class="asc-tr-data">
                <!-- Segment Label -->
                <td class="asc-td-seg">
                  <div class="asc-seg-info">
                    <div class="asc-seg-icon">
                      <i class="{{ data_get($seg, 'icon') }}"></i>
                    </div>
                    <span class="asc-seg-name">{{ data_get($seg, 'label') }}</span>
                  </div>
                </td>

                <!-- OS Cells -->
                <td class="asc-td-val">
                  <div class="asc-bar bg-os-blue" style="width: {{ min(100, (float)data_get($os, 'penc_bar_width', 0)) }}%;"></div>
                  <span class="asc-val-num">{{ data_get($os, 'realization_fmt', '–') }}</span>
                </td>
                @if($contentScopeKey === 'micro')
                  <td class="asc-td-ref">
                    <div class="asc-bar bg-tone-{{ data_get($os, 'ytd_tone', 'neutral') }}" style="width: {{ min(100, (float)data_get($os, 'ytd_bar_width', 0)) }}%;"></div>
                    <span class="asc-ref-num text-tone-{{ data_get($os, 'ytd_tone', 'neutral') }}">{{ data_get($os, 'ytd_position_fmt', '-') }}</span>
                  </td>
                  <td class="asc-td-ref">
                    <div class="asc-bar bg-tone-{{ data_get($os, 'mtd_tone', 'neutral') }}" style="width: {{ min(100, (float)data_get($os, 'mtd_bar_width', 0)) }}%;"></div>
                    <span class="asc-ref-num text-tone-{{ data_get($os, 'mtd_tone', 'neutral') }}">{{ data_get($os, 'mtd_position_fmt', '-') }}</span>
                  </td>
                @endif
                <td class="asc-td-target">
                  <div class="asc-bar bg-gray" style="width: {{ min(100, (float)data_get($os, 'rka_bar_width', 0)) }}%;"></div>
                  <span class="asc-target-num">{{ data_get($os, 'target_fmt', '–') }}</span>
                </td>
                <td class="asc-td-pct text-{{ data_get($os, 'pct_color', 'muted') }}-flat">
                  {{ data_get($os, 'pct_fmt', '–') }}
                </td>

                <!-- SML Cells -->
                <td class="asc-td-val">
                  <div class="asc-bar bg-sml-blue" style="width: {{ min(100, (float)data_get($sml, 'penc_bar_width', 0)) }}%;"></div>
                  <span class="asc-val-num">{{ data_get($sml, 'realization_fmt', '–') }}</span>
                </td>
                @if($contentScopeKey === 'micro')
                  <td class="asc-td-ref">
                    <div class="asc-bar bg-tone-{{ data_get($sml, 'ytd_tone', 'neutral') }}" style="width: {{ min(100, (float)data_get($sml, 'ytd_bar_width', 0)) }}%;"></div>
                    <span class="asc-ref-num text-tone-{{ data_get($sml, 'ytd_tone', 'neutral') }}">{{ data_get($sml, 'ytd_position_fmt', '-') }}</span>
                  </td>
                  <td class="asc-td-ref">
                    <div class="asc-bar bg-tone-{{ data_get($sml, 'mtd_tone', 'neutral') }}" style="width: {{ min(100, (float)data_get($sml, 'mtd_bar_width', 0)) }}%;"></div>
                    <span class="asc-ref-num text-tone-{{ data_get($sml, 'mtd_tone', 'neutral') }}">{{ data_get($sml, 'mtd_position_fmt', '-') }}</span>
                  </td>
                @endif
                <td class="asc-td-target">
                  <div class="asc-bar bg-gray" style="width: {{ min(100, (float)data_get($sml, 'rka_bar_width', 0)) }}%;"></div>
                  <span class="asc-target-num">{{ data_get($sml, 'target_fmt', '–') }}</span>
                </td>
                <td class="asc-td-pct text-{{ data_get($sml, 'pct_color', 'muted') }}-flat">
                  {{ data_get($sml, 'pct_fmt', '–') }}
                </td>

                <!-- NPL Cells -->
                <td class="asc-td-val">
                  <div class="asc-bar bg-npl-blue" style="width: {{ min(100, (float)data_get($npl, 'penc_bar_width', 0)) }}%;"></div>
                  <span class="asc-val-num">{{ data_get($npl, 'realization_fmt', '–') }}</span>
                </td>
                @if($contentScopeKey === 'micro')
                  <td class="asc-td-ref">
                    <div class="asc-bar bg-tone-{{ data_get($npl, 'ytd_tone', 'neutral') }}" style="width: {{ min(100, (float)data_get($npl, 'ytd_bar_width', 0)) }}%;"></div>
                    <span class="asc-ref-num text-tone-{{ data_get($npl, 'ytd_tone', 'neutral') }}">{{ data_get($npl, 'ytd_position_fmt', '-') }}</span>
                  </td>
                  <td class="asc-td-ref">
                    <div class="asc-bar bg-tone-{{ data_get($npl, 'mtd_tone', 'neutral') }}" style="width: {{ min(100, (float)data_get($npl, 'mtd_bar_width', 0)) }}%;"></div>
                    <span class="asc-ref-num text-tone-{{ data_get($npl, 'mtd_tone', 'neutral') }}">{{ data_get($npl, 'mtd_position_fmt', '-') }}</span>
                  </td>
                @endif
                <td class="asc-td-target">
                  <div class="asc-bar bg-gray" style="width: {{ min(100, (float)data_get($npl, 'rka_bar_width', 0)) }}%;"></div>
                  <span class="asc-target-num">{{ data_get($npl, 'target_fmt', '–') }}</span>
                </td>
                <td class="asc-td-pct text-{{ data_get($npl, 'pct_color', 'muted') }}-flat">
                  {{ data_get($npl, 'pct_fmt', '–') }}
                </td>
              </tr>
              @endforeach

              <!-- Total Row -->
              @if(!empty($totalPerf))
              @php
                $totOs = data_get($totalPerf, 'os', []);
                $totSml = data_get($totalPerf, 'sml', []);
                $totNpl = data_get($totalPerf, 'npl', []);
              @endphp
              <tr class="asc-tr-total">
                <td class="asc-td-seg">
                  <span class="asc-total-label">TOTAL</span>
                </td>

                <!-- Total OS -->
                <td class="asc-td-val font-weight-bold text-os-blue">
                  <div class="asc-bar bg-os-blue" style="width: {{ min(100, (float)data_get($totOs, 'penc_bar_width', 0)) }}%;"></div>
                  <span class="asc-val-num">{{ data_get($totOs, 'realization_fmt', '–') }}</span>
                </td>
                @if($contentScopeKey === 'micro')
                  <td class="asc-td-ref font-weight-bold">
                    <div class="asc-bar bg-tone-{{ data_get($totOs, 'ytd_tone', 'neutral') }}" style="width: {{ min(100, (float)data_get($totOs, 'ytd_bar_width', 0)) }}%;"></div>
                    <span class="asc-ref-num font-weight-bold text-tone-{{ data_get($totOs, 'ytd_tone', 'neutral') }}">{{ data_get($totOs, 'ytd_position_fmt', '-') }}</span>
                  </td>
                  <td class="asc-td-ref font-weight-bold">
                    <div class="asc-bar bg-tone-{{ data_get($totOs, 'mtd_tone', 'neutral') }}" style="width: {{ min(100, (float)data_get($totOs, 'mtd_bar_width', 0)) }}%;"></div>
                    <span class="asc-ref-num font-weight-bold text-tone-{{ data_get($totOs, 'mtd_tone', 'neutral') }}">{{ data_get($totOs, 'mtd_position_fmt', '-') }}</span>
                  </td>
                @endif
                <td class="asc-td-target font-weight-bold">
                  <div class="asc-bar bg-gray" style="width: {{ min(100, (float)data_get($totOs, 'rka_bar_width', 0)) }}%;"></div>
                  <span class="asc-target-num">{{ data_get($totOs, 'target_fmt', '–') }}</span>
                </td>
                <td class="asc-td-pct font-weight-bold text-{{ data_get($totOs, 'pct_color', 'muted') }}-flat">
                  {{ data_get($totOs, 'pct_fmt', '–') }}
                </td>

                <!-- Total SML -->
                <td class="asc-td-val font-weight-bold text-sml-blue">
                  <div class="asc-bar bg-sml-blue" style="width: {{ min(100, (float)data_get($totSml, 'penc_bar_width', 0)) }}%;"></div>
                  <span class="asc-val-num">{{ data_get($totSml, 'realization_fmt', '–') }}</span>
                </td>
                @if($contentScopeKey === 'micro')
                  <td class="asc-td-ref font-weight-bold">
                    <div class="asc-bar bg-tone-{{ data_get($totSml, 'ytd_tone', 'neutral') }}" style="width: {{ min(100, (float)data_get($totSml, 'ytd_bar_width', 0)) }}%;"></div>
                    <span class="asc-ref-num font-weight-bold text-tone-{{ data_get($totSml, 'ytd_tone', 'neutral') }}">{{ data_get($totSml, 'ytd_position_fmt', '-') }}</span>
                  </td>
                  <td class="asc-td-ref font-weight-bold">
                    <div class="asc-bar bg-tone-{{ data_get($totSml, 'mtd_tone', 'neutral') }}" style="width: {{ min(100, (float)data_get($totSml, 'mtd_bar_width', 0)) }}%;"></div>
                    <span class="asc-ref-num font-weight-bold text-tone-{{ data_get($totSml, 'mtd_tone', 'neutral') }}">{{ data_get($totSml, 'mtd_position_fmt', '-') }}</span>
                  </td>
                @endif
                <td class="asc-td-target font-weight-bold">
                  <div class="asc-bar bg-gray" style="width: {{ min(100, (float)data_get($totSml, 'rka_bar_width', 0)) }}%;"></div>
                  <span class="asc-target-num">{{ data_get($totSml, 'target_fmt', '–') }}</span>
                </td>
                <td class="asc-td-pct font-weight-bold text-{{ data_get($totSml, 'pct_color', 'muted') }}-flat">
                  {{ data_get($totSml, 'pct_fmt', '–') }}
                </td>

                <!-- Total NPL -->
                <td class="asc-td-val font-weight-bold text-npl-red">
                  <div class="asc-bar bg-npl-blue" style="width: {{ min(100, (float)data_get($totNpl, 'penc_bar_width', 0)) }}%;"></div>
                  <span class="asc-val-num">{{ data_get($totNpl, 'realization_fmt', '–') }}</span>
                </td>
                @if($contentScopeKey === 'micro')
                  <td class="asc-td-ref font-weight-bold">
                    <div class="asc-bar bg-tone-{{ data_get($totNpl, 'ytd_tone', 'neutral') }}" style="width: {{ min(100, (float)data_get($totNpl, 'ytd_bar_width', 0)) }}%;"></div>
                    <span class="asc-ref-num font-weight-bold text-tone-{{ data_get($totNpl, 'ytd_tone', 'neutral') }}">{{ data_get($totNpl, 'ytd_position_fmt', '-') }}</span>
                  </td>
                  <td class="asc-td-ref font-weight-bold">
                    <div class="asc-bar bg-tone-{{ data_get($totNpl, 'mtd_tone', 'neutral') }}" style="width: {{ min(100, (float)data_get($totNpl, 'mtd_bar_width', 0)) }}%;"></div>
                    <span class="asc-ref-num font-weight-bold text-tone-{{ data_get($totNpl, 'mtd_tone', 'neutral') }}">{{ data_get($totNpl, 'mtd_position_fmt', '-') }}</span>
                  </td>
                @endif
                <td class="asc-td-target font-weight-bold">
                  <div class="asc-bar bg-gray" style="width: {{ min(100, (float)data_get($totNpl, 'rka_bar_width', 0)) }}%;"></div>
                  <span class="asc-target-num">{{ data_get($totNpl, 'target_fmt', '–') }}</span>
                </td>
                <td class="asc-td-pct font-weight-bold text-{{ data_get($totNpl, 'pct_color', 'muted') }}-flat">
                  {{ data_get($totNpl, 'pct_fmt', '–') }}
                </td>
              </tr>
              @endif
            </tbody>
          </table>
        </div>
      </div>

      {{-- KOMPOSISI TOTAL --}}
      @php
        $composition = data_get($segmentPerf, 'composition', []);
        $osComp = data_get($composition, 'os', []);
        $lrComp = data_get($composition, 'restruk', []);
        $smlComp = data_get($composition, 'sml', []);
        $nplComp = data_get($composition, 'npl', []);
        $totalComp = data_get($composition, 'total', []);
        $microQuality = (array) data_get($composition, 'micro_quality', []);
        
        $osRaw = (float) data_get($osComp, 'raw_pct', 0.0);
        $lrRaw = (float) data_get($lrComp, 'raw_pct', 0.0);
        $smlRaw = (float) data_get($smlComp, 'raw_pct', 0.0);
        $nplRaw = (float) data_get($nplComp, 'raw_pct', 0.0);
        
        $healthyAngle = (float) data_get($composition, 'angles.healthy', 100.0 - $osRaw);
        $lrAngle = (float) data_get($composition, 'angles.lr', 100.0 - $smlRaw - $nplRaw);
        $smlAngleNew = (float) data_get($composition, 'angles.sml', 100.0 - $nplRaw);
        $compositionBars = [
          ['key' => 'lar', 'label' => data_get($osComp, 'name', 'LAR'), 'metric' => $osComp],
          ['key' => 'lr', 'label' => 'LR', 'metric' => $lrComp],
          ['key' => 'sml', 'label' => 'SML', 'metric' => $smlComp],
          ['key' => 'npl', 'label' => 'NPL', 'metric' => $nplComp],
        ];
        $compositionBarMax = max($osRaw, $lrRaw, $smlRaw, $nplRaw, 1.0);
      @endphp
      <div class="total-composition-card {{ $contentScopeKey === 'micro' ? 'total-composition-card--micro' : '' }}">
        <!-- Card Header -->
        <div class="asc-header">
          <div class="asc-header-icon">
            <i class="fas {{ $contentScopeKey === 'micro' ? 'fa-chart-bar' : 'fa-chart-pie' }}"></i>
          </div>
          <div class="asc-header-title">KOMPOSISI TOTAL (Rp Juta)</div>
        </div>
        
        <!-- Card Body -->
        <div class="tcc-body">
          @if($contentScopeKey === 'micro')
            <div class="tcc-quality-matrix" role="table" aria-label="Komposisi kualitas portofolio Mikro posisi dan MTD">
              <div class="tcc-quality-row tcc-quality-row--head" role="row">
                <span role="columnheader">Kualitas</span>
                <span role="columnheader">{{ data_get($microQuality, 'position_label', 'Posisi') }}</span>
                <span role="columnheader">{{ data_get($microQuality, 'previous_position_label', data_get($microQuality, 'mtd_label', '31 Jul 26')) }}</span>
              </div>
              @forelse((array) data_get($microQuality, 'items', []) as $qualityIndex => $quality)
                @if($qualityIndex === 0 || data_get($microQuality, 'items.'.($qualityIndex - 1).'.group') !== data_get($quality, 'group'))
                  <div class="tcc-quality-group">{{ data_get($quality, 'group') }} <small>{{ data_get($quality, 'group') === 'NPL' ? 'SSA Pinjaman' : 'Daily Loan Dinamis' }}</small></div>
                @endif
                <div class="tcc-quality-row tone-{{ data_get($quality, 'tone', 'blue') }}" role="row">
                  <span class="tcc-quality-name" role="rowheader"><i aria-hidden="true"></i>{{ data_get($quality, 'label', '-') }}</span>
                  <span class="tcc-quality-value" role="cell">
                    <div class="tcc-cell-bar" style="width: {{ data_get($quality, 'bar_width', 0) }}%; background: var(--quality-tone);"></div>
                    <div class="tcc-val-row">
                      <b>{{ data_get($quality, 'position_fmt', '0') }}</b>
                      <small>{{ data_get($quality, 'portfolio_pct_fmt', '0,00%') }}</small>
                    </div>
                  </span>
                  <span class="tcc-quality-value" role="cell">
                    <div class="tcc-cell-bar" style="width: {{ data_get($quality, 'previous_bar_width', 0) }}%; background: var(--quality-tone);"></div>
                    <div class="tcc-val-row">
                      <b>{{ data_get($quality, 'previous_position_fmt', '0') }}</b>
                      <small>{{ data_get($quality, 'previous_portfolio_pct_fmt', '0,00%') }}</small>
                    </div>
                  </span>
                </div>
              @empty
                <div class="tcc-quality-empty">Rincian kualitas Mikro belum tersedia.</div>
              @endforelse
              <div class="tcc-quality-row tcc-quality-row--total" role="row">
                <span class="tcc-quality-name" role="rowheader"><i aria-hidden="true" style="background: #38bdf8;"></i>TOTAL LAR</span>
                <span class="tcc-quality-value" role="cell">
                  <div class="tcc-cell-bar" style="width: 100%; background: #38bdf8;"></div>
                  <div class="tcc-val-row">
                    <b>{{ data_get($microQuality, 'total.position_fmt', '0') }}</b>
                    <small style="color: #bde4ff;">{{ data_get($microQuality, 'total.portfolio_pct_fmt', '0,00%') }}</small>
                  </div>
                </span>
                <span class="tcc-quality-value" role="cell">
                  <div class="tcc-cell-bar" style="width: 100%; background: #38bdf8;"></div>
                  <div class="tcc-val-row">
                    <b>{{ data_get($microQuality, 'total.previous_position_fmt', '0') }}</b>
                    <small style="color: #bde4ff;">{{ data_get($microQuality, 'total.previous_portfolio_pct_fmt', '0,00%') }}</small>
                  </div>
                </span>
              </div>
            </div>
          @else
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

              <!-- LR Legend -->
              <div class="tcc-legend-item">
                <div class="tcc-legend-dot bg-lr"></div>
                <div class="tcc-legend-info">
                  <span class="tcc-legend-name">LR</span>
                  <span class="tcc-legend-val">{{ data_get($lrComp, 'value', 'â€“') }}</span>
                  <span class="tcc-legend-pct">({{ data_get($lrComp, 'pct', 'â€“') }})</span>
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
          @endif
          
          <!-- Total Badge -->
          <div class="tcc-total-badge">
            <span class="tcc-total-label">TOTAL PORTOFOLIO KREDIT</span>
            <span class="tcc-total-val">{{ data_get($totalComp, 'value', '–') }} <span class="tcc-total-unit">Rp Juta</span></span>
          </div>
        </div>
      </div>
    </div>
    @endif

    @if(in_array($contentScopeKey, ['sme', 'consumer', 'micro'], true))
      <div class="loan-analytics-lazy-slot"
           data-loan-analytics-slot="quality"
           data-loan-analytics-scope="{{ $contentScopeKey }}"
           aria-live="polite"></div>
    @endif

    {{-- TREND POSISI & PERFORMANCE VS RKA DOUBLE CARDS ROW ── --}}
    @if($contentScopeKey === 'sme')
      <div class="loan-analytics-lazy-slot"
           data-loan-analytics-slot="tariff"
           data-loan-analytics-scope="{{ $contentScopeKey }}"
           aria-live="polite"></div>
    @endif

    @php
      $overallTrends = data_get($contentPortfolio, 'overall_trends');
      $trendDates = data_get($overallTrends, 'dates', []);
      $osTrend = data_get($overallTrends, 'os', []);
      $smlTrend = data_get($overallTrends, 'sml', []);
      $nplTrend = data_get($overallTrends, 'npl', []);
    @endphp

    @if(!empty($overallTrends))
    <div class="area6-segment-container area6-trend-perf-container mt-4">
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
                  <th rowspan="2" style="text-align: left; vertical-align: middle;">Indikator</th>
                  <th rowspan="2" style="vertical-align: middle;">Posisi<br><small style="text-transform: none;">(sd {{ data_get($segmentPerf, 'period_format', '19 Mei 2026') }})</small><br><small style="color: #64748b; text-transform: none;">(Rp Juta)</small></th>
                  <th rowspan="2" style="vertical-align: middle;">RKA {{ data_get($segmentPerf, 'rka_month_year', 'Mei 26') }}<br><small style="color: #64748b; text-transform: none;">(Rp Juta)</small></th>
                  <th colspan="2" style="text-align: center; border-bottom: 1.5px solid #cbd5e1; background: #f8fafc; border-radius: 8px 8px 0 0;">% Pencapaian RKA</th>
                  <th rowspan="2" style="vertical-align: middle;">Gap thd RKA<br><small style="color: #64748b; text-transform: none;">{{ data_get($segmentPerf, 'rka_month_year', 'Mei 26') }} (Rp Juta)</small></th>
                  <th rowspan="2" style="vertical-align: middle;">Status</th>
                </tr>
                <tr>
                  <th style="font-size: 0.68rem; color: #475569; text-transform: uppercase; background: #f8fafc; border-top: none; border-right: 1px solid #e2e8f0; font-weight: 800;">
                    {{ data_get($segmentPerf, 'previous_rka_month_year', 'M-1') }}
                  </th>
                  <th style="font-size: 0.68rem; color: #0857c3; text-transform: uppercase; background: #f8fafc; border-top: none; font-weight: 800;">
                    {{ data_get($segmentPerf, 'rka_month_year', 'Bulan Berjalan') }}
                  </th>
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
                  <td class="perf-pct-cell text-{{ data_get($totalPerf, 'os.previous_pct_color', 'muted') }}-flat" style="border-right: 1px solid #f1f5f9;">{{ data_get($totalPerf, 'os.previous_pct_fmt', '–') }}</td>
                  <td class="perf-pct-cell text-{{ data_get($osTrend, 'pct_color', 'red') }}-flat">{{ data_get($osTrend, 'pct', '–') }}</td>
                  <td class="perf-mono-cell text-{{ data_get($osTrend, 'gap_color', 'red') }}-flat">{{ data_get($osTrend, 'gap', '–') }}</td>
                  <td>
                    <div class="perf-status-circle bg-status-{{ data_get($osTrend, 'status_bg', 'red') }}"
                         title="Pencapaian: {{ data_get($osTrend, 'pct', '–') }} vs {{ data_get($segmentPerf, 'previous_rka_month_year', 'M-1') }}: {{ data_get($totalPerf, 'os.previous_pct_fmt', '–') }}">
                      @if(data_get($osTrend, 'status_arrow') === 'minus' || data_get($osTrend, 'status_arrow') === 'flat')
                        <i class="fas fa-minus"></i>
                      @else
                        <i class="fas fa-arrow-{{ data_get($osTrend, 'status_arrow', 'down') }}"></i>
                      @endif
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
                  <td class="perf-pct-cell text-{{ data_get($totalPerf, 'sml.previous_pct_color', 'muted') }}-flat" style="border-right: 1px solid #f1f5f9;">{{ data_get($totalPerf, 'sml.previous_pct_fmt', '–') }}</td>
                  <td class="perf-pct-cell text-{{ data_get($smlTrend, 'pct_color', 'red') }}-flat">{{ data_get($smlTrend, 'pct', '–') }}</td>
                  <td class="perf-mono-cell text-{{ data_get($smlTrend, 'gap_color', 'red') }}-flat">{{ data_get($smlTrend, 'gap', '–') }}</td>
                  <td>
                    <div class="perf-status-circle bg-status-{{ data_get($smlTrend, 'status_bg', 'red') }}"
                         title="Pencapaian: {{ data_get($smlTrend, 'pct', '–') }} vs {{ data_get($segmentPerf, 'previous_rka_month_year', 'M-1') }}: {{ data_get($totalPerf, 'sml.previous_pct_fmt', '–') }}">
                      @if(data_get($smlTrend, 'status_arrow') === 'minus' || data_get($smlTrend, 'status_arrow') === 'flat')
                        <i class="fas fa-minus"></i>
                      @else
                        <i class="fas fa-arrow-{{ data_get($smlTrend, 'status_arrow', 'down') }}"></i>
                      @endif
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
                  <td class="perf-pct-cell text-{{ data_get($totalPerf, 'npl.previous_pct_color', 'muted') }}-flat" style="border-right: 1px solid #f1f5f9;">{{ data_get($totalPerf, 'npl.previous_pct_fmt', '–') }}</td>
                  <td class="perf-pct-cell text-{{ data_get($nplTrend, 'pct_color', 'red') }}-flat">{{ data_get($nplTrend, 'pct', '–') }}</td>
                  <td class="perf-mono-cell text-{{ data_get($nplTrend, 'gap_color', 'red') }}-flat">{{ data_get($nplTrend, 'gap', '–') }}</td>
                  <td>
                    <div class="perf-status-circle bg-status-{{ data_get($nplTrend, 'status_bg', 'red') }}"
                         title="Pencapaian: {{ data_get($nplTrend, 'pct', '–') }} vs {{ data_get($segmentPerf, 'previous_rka_month_year', 'M-1') }}: {{ data_get($totalPerf, 'npl.previous_pct_fmt', '–') }}">
                      @if(data_get($nplTrend, 'status_arrow') === 'minus' || data_get($nplTrend, 'status_arrow') === 'flat')
                        <i class="fas fa-minus"></i>
                      @else
                        <i class="fas fa-arrow-{{ data_get($nplTrend, 'status_arrow', 'down') }}"></i>
                      @endif
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

    <div id="sme-operations-dashboard"
         class="sme-operations-dashboard d-none"
         data-url="{{ route('dashboard.sme-operations', ['periode' => $selectedPeriod, 'cabang' => $selectedLandingBranch ?? 'area6']) }}"
         aria-live="polite"
         aria-busy="true">
      <div class="sme-ops-loader" data-sme-operations-loader>
        <div class="sme-ops-loader__bar"></div>
        <div class="sme-ops-loader__copy"><i class="fas fa-circle-notch fa-spin"></i>Menyiapkan SME Operating Desk...</div>
        <div class="sme-ops-loader__tiles">
          <div class="sme-ops-loader__tile"></div>
          <div class="sme-ops-loader__tile"></div>
          <div class="sme-ops-loader__tile"></div>
          <div class="sme-ops-loader__tile"></div>
        </div>
        <div class="sme-ops-loader__block"></div>
      </div>
    </div>

    <div id="micro-performance-dashboard"
         class="micro-performance-dashboard d-none"
         data-url="{{ route('dashboard.micro-performance', ['periode' => $selectedPeriod, 'cabang' => $selectedLandingBranch ?? 'area6']) }}"
         aria-live="polite"
         aria-busy="true">
      <div class="micro-ops-loader" data-micro-performance-loader>
        <div class="micro-ops-loader__hero"></div>
        <div class="micro-ops-loader__block"></div>
        <div class="micro-ops-loader__block"></div>
      </div>
    </div>

    <div id="consumer-operations-dashboard"
         class="consumer-operations-dashboard d-none"
         data-url="{{ route('dashboard.consumer-operations', ['periode' => $selectedPeriod, 'cabang' => $selectedLandingBranch ?? 'area6']) }}"
         aria-live="polite"
         aria-busy="true">
      <div class="micro-ops-loader" data-consumer-operations-loader>
        <div class="micro-ops-loader__hero"></div>
        <div class="micro-ops-loader__block"></div>
        <div class="micro-ops-loader__block"></div>
      </div>
    </div>

    @if(!empty($area6RankingModes))
      @foreach($area6RankingModes as $scopeKey => $scopePayload)
        @if($scopeKey === 'consumer')
          @continue
        @endif
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

  {{-- AREA INSIGHTS --}}
  <section class="landing-summary landing-insights" aria-labelledby="landing-insights-title">
    <header class="landing-insights__head">
      <div class="landing-insights__heading">
        <span class="landing-insights__mark"><i class="fas fa-chart-pie" aria-hidden="true"></i></span>
        <div>
          <span class="landing-insights__eyebrow">AREA PERFORMANCE INTELLIGENCE</span>
          <h2 id="landing-insights-title">{{ data_get($landingSummary, 'title', 'Ringkasan Eksekutif Area 6') }}</h2>
          <p>Laba terkini, kewenangan putusan PDWK, dan realisasi MTD per segmen dalam satu alur baca.</p>
        </div>
      </div>
      <div class="landing-insights__status"><i class="fas fa-shield-alt" aria-hidden="true"></i> Rekening terdeduplikasi</div>
    </header>

    <div class="landing-insights__grid">
      <article class="landing-insight landing-insight--decision">
        <header class="landing-insight__head">
          <div class="landing-insight__title">
            <span class="landing-insight__icon"><i class="fas fa-stamp" aria-hidden="true"></i></span>
            <div><small>AUTHORITY MONITOR</small><h3>Putusan BOH &amp; PDWK</h3><p>Kewenangan aktual pemutus dibanding plafon setiap rekening.</p></div>
          </div>
          <span class="landing-insight__period">{{ data_get($landingDecision, 'period_label', 'Belum ada data') }}</span>
          <div class="landing-insight__illustration landing-insight__illustration--decision" aria-hidden="true">
            <i class="fas fa-gavel"></i><span></span><span></span><span></span>
          </div>
        </header>
        @if(data_get($landingDecision, 'available'))
          <div class="landing-decision-overview">
            <div><span>Total Putusan</span><strong>{{ data_get($landingDecision, 'total_deb_fmt', '0 deb') }}</strong><small>{{ data_get($landingDecision, 'total_nominal_fmt', 'Rp0') }}</small></div>
            <div class="is-override"><span>Override</span><strong>{{ data_get($landingDecision, 'override_deb_fmt', '0 deb') }}</strong><small>{{ data_get($landingDecision, 'override_nominal_fmt', 'Rp0') }}</small></div>
            <div><span>Sesuai PDWK</span><strong>{{ data_get($landingDecision, 'pdwk_deb_fmt', '0 deb') }}</strong><small>{{ data_get($landingDecision, 'pdwk_nominal_fmt', 'Rp0') }}</small></div>
          </div>
          <div class="landing-authority-grid">
            @foreach(data_get($landingDecision, 'items', []) as $item)
              <section class="landing-authority tone-{{ data_get($item, 'tone', 'navy') }}">
                <header>
                  <span class="landing-authority__icon"><i class="{{ data_get($item, 'icon', 'fas fa-user-check') }}" aria-hidden="true"></i></span>
                  <div><h4>{{ data_get($item, 'label', '-') }}</h4><p>{{ data_get($item, 'deb_fmt', '0 deb') }} &middot; {{ data_get($item, 'nominal_fmt', 'Rp0') }}</p></div>
                </header>
                <div class="landing-authority__rules">
                  <div class="is-override">
                    <span>Override</span>
                    @if(filled(data_get($item, 'override_rule')) && data_get($item, 'override_rule') !== '-')
                      <strong>{{ data_get($item, 'override_rule') }}</strong>
                    @endif
                    <small>{{ data_get($item, 'override_deb_fmt', '0 deb') }} &middot; {{ data_get($item, 'override_nominal_fmt', 'Rp0') }}</small>
                  </div>
                  <div>
                    <span>PDWK</span>
                    @if(filled(data_get($item, 'pdwk_rule')) && data_get($item, 'pdwk_rule') !== '-')
                      <strong>{{ data_get($item, 'pdwk_rule') }}</strong>
                    @endif
                    <small>{{ data_get($item, 'pdwk_deb_fmt', '0 deb') }} &middot; {{ data_get($item, 'pdwk_nominal_fmt', 'Rp0') }}</small>
                  </div>
                </div>
                <div class="landing-authority__branches">
                  <div class="landing-authority__branch-head"><span>Cabang</span><span>Override</span><span>PDWK</span></div>
                  @foreach(data_get($item, 'branches', []) as $branch)
                    <div class="landing-authority__branch-row">
                      <span><strong>{{ data_get($branch, 'branch', '-') }}</strong><small>{{ data_get($branch, 'deb_fmt', '0 deb') }} &middot; {{ data_get($branch, 'nominal_fmt', 'Rp0') }}</small></span>
                      <span class="is-override"><b>{{ data_get($branch, 'override_deb_fmt', '0 deb') }}</b><small>{{ data_get($branch, 'override_nominal_fmt', 'Rp0') }}</small></span>
                      <span><b>{{ data_get($branch, 'pdwk_deb_fmt', '0 deb') }}</b><small>{{ data_get($branch, 'pdwk_nominal_fmt', 'Rp0') }}</small></span>
                    </div>
                  @endforeach
                </div>
              </section>
            @endforeach
          </div>
          <p class="landing-insight__note"><i class="fas fa-info-circle" aria-hidden="true"></i>{{ data_get($landingDecision, 'note') }}</p>
        @else
          <div class="landing-empty">Data putusan BOH dan PDWK belum tersedia.</div>
        @endif
      </article>

      <article class="landing-insight landing-insight--profit">
        <header class="landing-insight__head">
          <div class="landing-insight__title">
            <span class="landing-insight__icon"><i class="fas fa-balance-scale" aria-hidden="true"></i></span>
            <div><small>FINANCIAL OUTCOME</small><h3>Laba Setelah Pajak</h3><p>Kontribusi laba pada wilayah aktif.</p></div>
          </div>
          <span class="landing-insight__period">{{ data_get($landingProfit, 'period_label', 'Belum ada data') }}</span>
          <div class="landing-insight__illustration landing-insight__illustration--profit" aria-hidden="true"><i class="fas fa-chart-line"></i><span></span><span></span><span></span></div>
        </header>
        @if(data_get($landingProfit, 'available'))
          <div class="landing-profit-total">
            <div><span>Posisi laba</span><strong>{{ data_get($landingProfit, 'total_fmt', 'Rp0') }}</strong></div>
            <div class="{{ data_get($landingProfit, 'delta_class', 'text-muted') }}"><span>vs {{ data_get($landingProfit, 'previous_period_label', 'periode lalu') }}</span><strong>{{ data_get($landingProfit, 'delta_fmt', '0,0%') }}</strong></div>
          </div>
          <div class="landing-profit-branches">
            @foreach(data_get($landingProfit, 'branches', []) as $branch)
              @php $pctContribution = min(100, max(0, (float) data_get($branch, 'contribution_pct', 0))); @endphp
              <div class="landing-profit-branch">
                <div><span>{{ data_get($branch, 'name', '-') }}</span><strong>{{ data_get($branch, 'nominal_fmt', 'Rp0') }}</strong></div>
                <div class="landing-profit-track"><span style="width: {{ $pctContribution }}%"></span></div>
                <small>{{ number_format($pctContribution, 1, ',', '.') }}% kontribusi absolut</small>
              </div>
            @endforeach
          </div>
        @else
          <div class="landing-empty">Data laba setelah pajak belum tersedia.</div>
        @endif
      </article>

      <article class="landing-insight landing-insight--realization">
        @php
          $realizationScopes = data_get($landingRealization, 'scopes', []);
          $realizationDefaultScope = data_get($landingRealization, 'default_scope', $area6DefaultScope);
        @endphp
        <header class="landing-insight__head">
          <div class="landing-insight__title">
            <span class="landing-insight__icon"><i class="fas fa-rocket" aria-hidden="true"></i></span>
            <div><small>MONTH TO DATE</small><h3>Realisasi Seluruh Segmen</h3><p>Plafon transaksi bulan berjalan, satu rekening dihitung satu kali.</p></div>
          </div>
          <span class="landing-insight__period">{{ data_get($landingRealization, 'period_label', 'Belum ada data') }}</span>
          <div class="landing-insight__illustration landing-insight__illustration--realization" aria-hidden="true"><i class="fas fa-chart-bar"></i><span></span><span></span><span></span></div>
        </header>
        @if(!empty($realizationScopes))
          <div class="landing-realization-total"><span>Total MTD</span><strong>{{ data_get($landingRealization, 'total_nominal_fmt', 'Rp0') }}</strong><small>{{ data_get($landingRealization, 'total_deb_fmt', '0 deb') }}</small></div>
          @foreach($realizationScopes as $scopeKey => $scopePayload)
            <div class="landing-realization-panel {{ $scopeKey === $realizationDefaultScope ? '' : 'd-none' }}" data-landing-realization-panel="{{ $scopeKey }}">
              <div class="landing-realization-list">
                @foreach(data_get($scopePayload, 'segments', []) as $segment)
                  @php $share = min(100, max(0, (float) data_get($segment, 'share', 0))); @endphp
                  <div class="landing-realization-row tone-{{ data_get($segment, 'tone', 'blue') }}">
                    <span class="landing-realization-row__icon"><i class="{{ data_get($segment, 'icon', 'fas fa-layer-group') }}" aria-hidden="true"></i></span>
                    <div class="landing-realization-row__main">
                      <div><strong>{{ data_get($segment, 'label', '-') }}</strong><span>{{ data_get($segment, 'deb_fmt', '0 deb') }}</span></div>
                      <div class="landing-realization-track"><span style="width: {{ $share }}%"></span></div>
                    </div>
                    <div class="landing-realization-row__value"><strong>{{ data_get($segment, 'nominal_fmt', 'Rp0') }}</strong><span>{{ data_get($segment, 'share_fmt', '0,00%') }}</span></div>
                  </div>
                @endforeach
              </div>
            </div>
          @endforeach
          <p class="landing-insight__note"><i class="fas fa-database" aria-hidden="true"></i>{{ data_get($landingRealization, 'source') }}</p>
        @else
          <div class="landing-empty">Data realisasi segmen belum tersedia.</div>
        @endif
      </article>
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
  let lastChartResizeSignature = '';

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
    const activeScope = document.querySelector('.area6-scope-btn.active')?.getAttribute('data-area6-scope') || '';
    const chartResizeSignature = [
      Math.round(effectiveWidth),
      Math.round(viewportHeight),
      activeScope,
      Number(isMobile),
      Number(isTablet),
      Number(isShort),
    ].join(':');
    const shouldResizeCharts = chartResizeSignature !== lastChartResizeSignature;
    lastChartResizeSignature = chartResizeSignature;

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

    if (shouldResizeCharts) {
      if (window.timeseriesArea6Chart && typeof window.timeseriesArea6Chart.resize === 'function') {
        window.timeseriesArea6Chart.resize();
      }

      if (window.dashboardLandingCharts instanceof Map) {
        window.dashboardLandingCharts.forEach(chart => {
          if (chart?.canvas?.offsetParent && typeof chart.resize === 'function') chart.resize();
        });
      }
    }
  };

  const scheduleSync = () => {
    if (raf) window.cancelAnimationFrame(raf);
    raf = window.requestAnimationFrame(syncLandingDensity);
  };

  scheduleSync();
  window.addEventListener('resize', scheduleSync, { passive: true });
  window.addEventListener('orientationchange', scheduleSync, { passive: true });
  document.addEventListener('landing:scopechange', scheduleSync);
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

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[char]));

  const landingAnalyticsCharts = new Map();
  window.dashboardLandingCharts = landingAnalyticsCharts;
  const landingAdaptiveScaleBounds = datasets => {
    const values = (Array.isArray(datasets) ? datasets : [])
      .filter(dataset => !dataset.hidden)
      .flatMap(dataset => Array.isArray(dataset.data) ? dataset.data : [])
      .map(Number)
      .filter(Number.isFinite);
    if (!values.length) return {};

    const dataMin = Math.min(...values);
    const dataMax = Math.max(...values);
    const maxAbs = Math.max(Math.abs(dataMin), Math.abs(dataMax), 1);
    const rawStep = maxAbs / 20;
    const magnitude = 10 ** Math.floor(Math.log10(Math.max(rawStep, 1)));
    const normalizedStep = rawStep / magnitude;
    const niceFactor = normalizedStep <= 1 ? 1 : (normalizedStep <= 2 ? 2 : (normalizedStep <= 5 ? 5 : 10));
    const step = niceFactor * magnitude;
    let min = Math.floor(Math.max(0, dataMin * .94) / step) * step;
    let max = Math.ceil(dataMax * 1.12 / step) * step;

    if (max <= min) max = min + step;

    return { min, max };
  };

  const applyLandingAdaptiveScale = chart => {
    if (!chart?.options?.scales?.y || chart.$isTariffChart) return;
    const bounds = landingAdaptiveScaleBounds(chart.data?.datasets || []);
    if (!Number.isFinite(bounds.min) || !Number.isFinite(bounds.max)) return;
    chart.options.scales.y.min = bounds.min;
    chart.options.scales.y.max = bounds.max;
  };

  const initializeLandingAnalyticsCharts = root => {
    if (!root || !window.Chart) return;

    root.querySelectorAll('[data-loan-quality-config], [data-tariff-relief-config]').forEach(configNode => {
      if (configNode.dataset.chartReady === '1') return;

      let config;
      try {
        config = JSON.parse(configNode.textContent || '{}');
      } catch (error) {
        return;
      }

      const canvas = document.getElementById(config.canvasId || '');
      if (!canvas || canvas.offsetParent === null) return;

      const isTariff = configNode.hasAttribute('data-tariff-relief-config');
      const valueType = config.valueType || (isTariff ? 'percent' : 'currency_juta');
      const isPercentage = valueType === 'percent';
      const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
      const datasets = (Array.isArray(config.datasets) ? config.datasets : []).map(dataset => ({
        ...dataset,
        borderWidth: isTariff ? 2.8 : (dataset.label === 'LAR' ? 3.2 : 2.35),
        pointRadius: 3.5,
        pointHoverRadius: 6,
        pointBorderWidth: 2,
        pointBackgroundColor: '#ffffff',
        pointBorderColor: dataset.borderColor,
        spanGaps: false,
        fill: false,
        tension: .32,
      }));

      const chart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: { labels: config.labels || [], datasets },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: reducedMotion ? false : { duration: 520 },
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: {
              display: isTariff && !config.compact,
              position: 'top',
              align: 'end',
              labels: { color: '#385573', usePointStyle: true, boxWidth: 8, padding: 16, font: { size: 11, weight: '700' } },
            },
            tooltip: {
              backgroundColor: 'rgba(15, 23, 42, .96)',
              padding: 12,
              cornerRadius: 10,
              boxPadding: 6,
              usePointStyle: true,
              titleFont: { size: 12, weight: '800' },
              bodyFont: { size: 11, weight: '600' },
              callbacks: {
                title: items => {
                  const index = items?.[0]?.dataIndex ?? -1;
                  if (isTariff && config.points?.[index]) {
                    const pt = config.points[index];
                    return `Posisi Closing: ${pt.closing_period_label || pt.label || '-'}`;
                  }
                  return config.periodLabels?.[index] && config.periodLabels[index] !== '-'
                    ? config.periodLabels[index]
                    : (items?.[0]?.label || '-');
                },
                label: item => {
                  if (isTariff && config.points?.[item.dataIndex]) {
                    const pt = config.points[item.dataIndex];
                    const fmt = val => Number(val || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
                    if (item.datasetIndex === 0) {
                      return ` OS H-1 (${pt.previous_period_label || 'H-1'}): ${fmt(pt.previous_os)} Rp Juta`;
                    }
                    return ` OS Akhir Bulan (${pt.closing_period_label || 'Akhir Bulan'}): ${fmt(pt.closing_os)} Rp Juta`;
                  }
                  const value = Number(item.parsed.y || 0);
                  return isPercentage
                    ? ` ${item.dataset.label}: ${value.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`
                    : ` ${item.dataset.label}: ${value.toLocaleString('id-ID', { maximumFractionDigits: 0 })} Rp Juta`;
                },
                afterBody: items => {
                  if (!isTariff) return [];
                  const index = items?.[0]?.dataIndex ?? -1;
                  const pt = config.points?.[index];
                  if (!pt) return [];
                  const fmt = val => Number(val || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
                  const realisasiVal = pt.daily_realization_available ? `${fmt(pt.daily_realization)} Rp Juta` : '-';
                  const adjusted = Number(pt.adjusted_delta_os ?? (pt.delta_os - pt.daily_realization));
                  const deltaSign = adjusted > 0 ? '+' : '';
                  return [
                    ` Realisasi SME (Hari Closing): ${realisasiVal}`,
                    ` Kelonggaran Tarik: ${deltaSign}${fmt(adjusted)} Rp Juta (Delta OS - Realisasi)`
                  ];
                },
              },
            },
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { color: '#617891', font: { size: 10, weight: '700' }, maxRotation: 0 },
            },
            y: {
              beginAtZero: false,
              grid: { color: 'rgba(115, 147, 179, .14)', drawBorder: false },
              ticks: {
                color: '#617891',
                font: { size: 10 },
                callback: value => isPercentage
                  ? `${Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`
                  : Number(value).toLocaleString('id-ID', { notation: 'compact', maximumFractionDigits: 1 }),
              },
            },
          },
        },
      });

      chart.$isTariffChart = isTariff;
      applyLandingAdaptiveScale(chart);
      chart.update('none');

      configNode.dataset.chartReady = '1';
      landingAnalyticsCharts.set(config.canvasId, chart);
    });
  };

  const dashboardShell = document.querySelector('.db-shell');
  const smeOperationsDashboard = document.getElementById('sme-operations-dashboard');
  const microPerformanceDashboard = document.getElementById('micro-performance-dashboard');
  const consumerOperationsDashboard = document.getElementById('consumer-operations-dashboard');
  const area6ScopeTitle = document.getElementById('area6-scope-title');
  const area6ScopeSubtitle = document.getElementById('area6-scope-subtitle');
  const defaultScopeTitle = area6ScopeTitle?.textContent || '';
  const defaultScopeSubtitle = area6ScopeSubtitle?.textContent || '';
  let smeOperationsRequest = null;
  let microPerformanceRequest = null;
  let consumerOperationsRequest = null;
  const loanAnalyticsRequests = new Map();
  let lastSmeVendorTrigger = null;
  let smeVendorRequest = null;
  let activeSmeVendorColumns = [];
  let activeSmeVendorRows = [];
  let lastSmeUnproductiveTrigger = null;
  let activeSmeUnproductiveRows = [];
  let microPipelineRequest = null;
  let microPipelinePayload = null;
  let lastMicroPipelineTrigger = null;
  let lastMicroPipelineSourceTrigger = null;
  let lastConsumerQuadrantTrigger = null;
  let activeMicroPipelineSource = null;

  document.addEventListener('click', event => {
    const toggle = event.target.closest('[data-loan-quality-toggle]');
    if (!toggle) return;

    const chartId = toggle.dataset.loanQualityToggle || '';
    const metric = toggle.dataset.loanQualityMetric || '';
    const chart = landingAnalyticsCharts.get(chartId);
    if (!chart || !metric) return;

    chart.data.datasets.forEach(dataset => {
      dataset.hidden = dataset.metricKey !== metric;
    });
    applyLandingAdaptiveScale(chart);
    document.querySelectorAll('[data-loan-quality-toggle]').forEach(button => {
      if (button.dataset.loanQualityToggle !== chartId) return;
      const isActive = button.dataset.loanQualityMetric === metric;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
    chart.update();
  });

  const loanAnalyticsSlots = scope => Array.from(document.querySelectorAll(
    `[data-loan-analytics-slot][data-loan-analytics-scope="${scope}"]`
  ));

  const loadLoanAnalytics = (scope, forceRefresh = false) => {
    if (!['sme', 'consumer', 'micro'].includes(scope) || !dashboardShell?.dataset.loanAnalyticsUrl) {
      return Promise.resolve();
    }

    const slots = loanAnalyticsSlots(scope);
    if (!slots.length) return Promise.resolve();
    if (!forceRefresh && slots.every(slot => slot.dataset.loaded === '1')) return Promise.resolve();
    if (loanAnalyticsRequests.has(scope)) return loanAnalyticsRequests.get(scope);

    slots.forEach(slot => {
      slot.classList.remove('is-error', 'is-empty');
      slot.classList.add('is-loading');
      slot.setAttribute('aria-busy', 'true');
      if (forceRefresh) delete slot.dataset.loaded;
    });

    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 90000);
    const requestUrl = new URL(dashboardShell.dataset.loanAnalyticsUrl, window.location.origin);
    requestUrl.searchParams.set('scope', scope);
    requestUrl.searchParams.set('_', String(Date.now()));
    if (forceRefresh) requestUrl.searchParams.set('refresh', '1');

    const request = fetch(requestUrl.toString(), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      cache: 'no-store',
      signal: controller.signal,
    })
      .then(async response => {
        const payload = await response.json();
        if (!response.ok || payload?.scope !== scope) {
          throw new Error(payload?.message || 'Respons analitik pinjaman tidak valid.');
        }

        slots.forEach(slot => {
          const section = slot.dataset.loanAnalyticsSlot;
          const html = section === 'tariff' ? payload.tariff_html : payload.quality_html;
          slot.innerHTML = typeof html === 'string' ? html : '';
          slot.classList.toggle('is-empty', !slot.innerHTML.trim());
          slot.classList.remove('is-loading', 'is-error');
          slot.dataset.loaded = '1';
          slot.setAttribute('aria-busy', 'false');
          if (slot.offsetParent !== null) initializeLandingAnalyticsCharts(slot);
        });
      })
      .catch(error => {
        const message = error?.name === 'AbortError'
          ? 'Analitik belum selesai dalam 90 detik.'
          : (error?.message || 'Analitik pinjaman belum dapat dimuat.');
        slots.forEach(slot => {
          slot.classList.remove('is-loading', 'is-empty');
          slot.classList.add('is-error');
          slot.setAttribute('aria-busy', 'false');
          slot.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${escapeHtml(message)}<button type="button" data-loan-analytics-retry="${scope}">Coba lagi</button>`;
        });
      })
      .finally(() => {
        window.clearTimeout(timeoutId);
        loanAnalyticsRequests.delete(scope);
      });

    loanAnalyticsRequests.set(scope, request);

    return request;
  };

  const closeSmeVendorModal = () => {
    const modal = document.querySelector('[data-sme-vendor-modal]');
    if (!modal || modal.hidden) return;
    modal.hidden = true;
    document.body.classList.remove('sme-vendor-modal-open');
    smeVendorRequest?.abort?.();
    smeVendorRequest = null;
    activeSmeVendorColumns = [];
    activeSmeVendorRows = [];
    lastSmeVendorTrigger?.focus?.();
    lastSmeVendorTrigger = null;
  };

  const prepareSmeVendorModal = () => {
    const localModal = smeOperationsDashboard?.querySelector('[data-sme-vendor-modal]');
    if (!localModal) return;
    const existing = document.querySelector('body > [data-sme-vendor-modal]');
    if (existing && existing !== localModal) existing.remove();
    document.body.appendChild(localModal);
  };

  const closeSmeUnproductiveModal = () => {
    const modal = document.querySelector('[data-sme-unproductive-modal]');
    if (!modal || modal.hidden) return;
    modal.hidden = true;
    activeSmeUnproductiveRows = [];
    if (document.querySelector('[data-sme-vendor-modal]:not([hidden])') === null) {
      document.body.classList.remove('sme-vendor-modal-open');
    }
    lastSmeUnproductiveTrigger?.focus?.();
    lastSmeUnproductiveTrigger = null;
  };

  const prepareSmeUnproductiveModal = () => {
    const localModal = smeOperationsDashboard?.querySelector('[data-sme-unproductive-modal]');
    if (!localModal) return;
    const existing = document.querySelector('body > [data-sme-unproductive-modal]');
    if (existing && existing !== localModal) existing.remove();
    document.body.appendChild(localModal);
  };

  const renderSmeUnproductiveRows = (modal, query = '') => {
    const body = modal?.querySelector('[data-sme-unproductive-modal-body]');
    const count = modal?.querySelector('[data-sme-unproductive-result-count]');
    if (!body) return;

    const keyword = String(query || '').trim().toLocaleLowerCase('id-ID');
    const rows = keyword === ''
      ? activeSmeUnproductiveRows
      : activeSmeUnproductiveRows.filter(row => [row.branch, row.unit_code, row.unit, row.rm]
          .some(value => String(value || '').toLocaleLowerCase('id-ID').includes(keyword)));

    const hasRealization = activeSmeUnproductiveRows.some(row => Number.isFinite(row.realization_rp));
    modal.querySelector('[data-sme-rm-realization-head]')?.toggleAttribute('hidden', !hasRealization);
    body.innerHTML = rows.length
      ? rows.map((row, index) => `<tr>
          <td>${(index + 1).toLocaleString('id-ID')}</td>
          <td>${escapeHtml(row.branch || '-')}</td>
          <td>${escapeHtml(row.unit_code || '-')}</td>
          <td>${escapeHtml(row.unit || '-')}</td>
          <td><strong>${escapeHtml(row.rm || '-')}</strong></td>
          ${hasRealization ? `<td><strong>${(Number(row.realization_rp || 0) / 1000000).toLocaleString('id-ID', { maximumFractionDigits: 0 })}</strong> <small>Rp Juta</small></td>` : ''}
        </tr>`).join('')
      : `<tr><td colspan="${hasRealization ? 6 : 5}" class="sme-vendor-modal__empty">${keyword ? 'RM tidak ditemukan.' : 'Tidak ada RM pada kategori ini.'}</td></tr>`;
    if (count) count.textContent = `${rows.length.toLocaleString('id-ID')} RM`;
  };

  const openSmeUnproductiveModal = trigger => {
    const source = trigger?.closest?.('[data-sme-unproductive-detail]') || trigger;
    if (!source) return;

    let detail;
    try {
      detail = JSON.parse(source.getAttribute('data-sme-unproductive-detail') || '{}');
    } catch (error) {
      return;
    }

    const modal = document.querySelector('[data-sme-unproductive-modal]');
    if (!modal) return;
    closeSmeVendorModal();

    activeSmeUnproductiveRows = (Array.isArray(detail.rows) ? detail.rows : [])
      .map(row => ({
        branch: String(row?.branch || detail.scope || '-'),
        unit_code: String(row?.unit_code || '-'),
        unit: String(row?.unit || '-'),
        rm: String(row?.rm || '-'),
        realization_rp: row?.realization_rp === null || row?.realization_rp === undefined
          ? Number.NaN
          : Number(row.realization_rp),
      }))
      .sort((left, right) => `${left.branch}|${left.unit_code.padStart(10, '0')}|${left.rm}`
        .localeCompare(`${right.branch}|${right.unit_code.padStart(10, '0')}|${right.rm}`, 'id', { numeric: true }));

    const title = modal.querySelector('[data-sme-unproductive-modal-title]');
    const summary = modal.querySelector('[data-sme-unproductive-modal-summary]');
    const search = modal.querySelector('[data-sme-unproductive-search]');
    if (title) title.textContent = `${detail.title || 'RM Tidak Produktif'} - ${detail.metric || '-'}`;
    if (summary) {
      summary.innerHTML = [
        ['Wilayah', detail.scope || '-'],
        ['Kategori', detail.metric || '-'],
        ['Jumlah RM', activeSmeUnproductiveRows.length.toLocaleString('id-ID')],
      ].map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
    }
    if (search) search.value = '';

    lastSmeUnproductiveTrigger = source;
    modal.hidden = false;
    document.body.classList.add('sme-vendor-modal-open');
    renderSmeUnproductiveRows(modal);
    window.requestAnimationFrame(() => modal.querySelector('[data-sme-unproductive-close]:not([tabindex="-1"])')?.focus());
  };

  const renderSmeVendorNominatives = (modal, query = '') => {
    const body = modal?.querySelector('[data-sme-vendor-modal-body]');
    const count = modal?.querySelector('[data-sme-vendor-result-count]');
    if (!body) return;

    const keyword = String(query || '').trim().toLocaleLowerCase('id-ID');
    const rows = keyword === ''
      ? activeSmeVendorRows
      : activeSmeVendorRows.filter(row => (row.values || []).some(value => String(value || '').toLocaleLowerCase('id-ID').includes(keyword)));
    const colspan = Math.max(activeSmeVendorColumns.length, 1);
    body.innerHTML = rows.length
      ? rows.map(row => `<tr>${activeSmeVendorColumns.map((column, index) => `<td>${escapeHtml(row.values?.[index] || '-')}</td>`).join('')}</tr>`).join('')
      : `<tr><td colspan="${colspan}" class="sme-vendor-modal__empty">${keyword ? 'Nominatif tidak ditemukan.' : 'Nominatif Area 6 belum tersedia.'}</td></tr>`;
    if (count) count.textContent = `${rows.length.toLocaleString('id-ID')} baris`;
  };

  const openSmeVendorModal = async trigger => {
    const card = trigger?.closest?.('[data-sme-vendor-detail]') || trigger;
    if (!card) return;

    let vendor;
    try {
      vendor = JSON.parse(card.getAttribute('data-sme-vendor-detail') || '{}');
    } catch (error) {
      return;
    }

    const modal = document.querySelector('[data-sme-vendor-modal]');
    if (!modal) return;
    closeSmeUnproductiveModal();
    const title = modal.querySelector('[data-sme-vendor-modal-title]');
    const summary = modal.querySelector('[data-sme-vendor-modal-summary]');
    const head = modal.querySelector('[data-sme-vendor-modal-head]');
    const body = modal.querySelector('[data-sme-vendor-modal-body]');
    const link = modal.querySelector('[data-sme-vendor-modal-link]');
    const search = modal.querySelector('[data-sme-vendor-search]');

    if (title) title.textContent = vendor.label || 'Detail Vendor';
    if (summary) {
      summary.innerHTML = [
        ['Pipeline', vendor.pipeline],
        ['Sudah OTS', vendor.ots],
        ['Berminat', vendor.interested],
      ].map(([label, value]) => `<div><span>${label}</span><strong>${Number(value || 0).toLocaleString('id-ID')}</strong></div>`).join('');
    }
    if (head) head.innerHTML = '<th>Nominatif</th>';
    if (body) body.innerHTML = '<tr><td class="sme-vendor-modal__loading"><i class="fas fa-circle-notch fa-spin"></i> Memuat worksheet vendor...</td></tr>';
    if (search) {
      search.value = '';
      search.disabled = true;
    }
    if (link) {
      link.href = String(vendor.source_url || '').startsWith('https://') ? vendor.source_url : '#';
      link.setAttribute('aria-label', `Buka sheet ${vendor.source_sheet || vendor.label || 'vendor'}`);
    }

    lastSmeVendorTrigger = card;
    modal.hidden = false;
    document.body.classList.add('sme-vendor-modal-open');
    window.requestAnimationFrame(() => modal.querySelector('[data-sme-vendor-close]:not([tabindex="-1"])')?.focus());

    if (!dashboardShell?.dataset.smeVendorNominativesUrl || !vendor.key) return;
    smeVendorRequest?.abort?.();
    const controller = new AbortController();
    smeVendorRequest = controller;
    const requestUrl = new URL(dashboardShell.dataset.smeVendorNominativesUrl, window.location.origin);
    requestUrl.searchParams.set('vendor', vendor.key);
    requestUrl.searchParams.set('_', String(Date.now()));

    try {
      const response = await fetch(requestUrl.toString(), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store',
        signal: controller.signal,
      });
      const payload = await response.json();
      if (!response.ok || payload?.vendor?.key !== vendor.key) {
        throw new Error(payload?.message || 'Respons nominatif vendor tidak valid.');
      }

      activeSmeVendorColumns = Array.isArray(payload.columns) ? payload.columns : [];
      activeSmeVendorRows = Array.isArray(payload.rows) ? payload.rows : [];
      if (head) {
        head.innerHTML = activeSmeVendorColumns.length
          ? activeSmeVendorColumns.map(column => `<th scope="col">${escapeHtml(column.label || '-')}</th>`).join('')
          : '<th>Nominatif</th>';
      }
      if (summary) {
        summary.innerHTML = [
          ['Nominatif', payload.row_count],
          ['Wilayah', payload.scope_label],
          ['Worksheet', payload.vendor?.sheet],
        ].map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value ?? '-')}</strong></div>`).join('');
      }
      if (link && String(payload.vendor?.source_url || '').startsWith('https://')) {
        link.href = payload.vendor.source_url;
      }
      if (search) search.disabled = false;
      renderSmeVendorNominatives(modal);
    } catch (error) {
      if (error?.name === 'AbortError') return;
      activeSmeVendorColumns = [];
      activeSmeVendorRows = [];
      if (head) head.innerHTML = '<th>Nominatif</th>';
      if (body) body.innerHTML = `<tr><td class="sme-vendor-modal__empty"><i class="fas fa-exclamation-circle"></i> ${escapeHtml(error?.message || 'Nominatif vendor belum dapat dimuat.')}</td></tr>`;
      if (search) search.disabled = true;
    } finally {
      if (smeVendorRequest === controller) smeVendorRequest = null;
    }
  };

  const smeOperationsErrorHtml = message => `
    <div class="sme-ops-load-error" role="alert">
      <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
      <h3>SME Operating Desk belum dapat dimuat</h3>
      <p>${escapeHtml(message || 'Koneksi ke sumber data belum berhasil. Silakan coba lagi.')}</p>
      <button type="button" data-sme-operations-retry><i class="fas fa-redo mr-1"></i>Coba lagi</button>
    </div>`;

  const setSmeRefreshState = isLoading => {
    smeOperationsDashboard?.querySelectorAll('[data-sme-operations-refresh]').forEach(button => {
      button.disabled = isLoading;
      button.classList.toggle('is-loading', isLoading);
    });
  };

  const microPerformanceErrorHtml = message => `
    <div class="micro-ops-load-error" role="alert">
      <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
      <h3>Micro Performance Desk belum dapat dimuat</h3>
      <p>${escapeHtml(message || 'Perhitungan Daily Loan Dinamis belum berhasil.')}</p>
      <button type="button" data-micro-performance-retry><i class="fas fa-redo mr-1"></i>Coba lagi</button>
    </div>`;

  const setMicroRefreshState = isLoading => {
    microPerformanceDashboard?.querySelectorAll('[data-micro-performance-refresh]').forEach(button => {
      button.disabled = isLoading;
      button.classList.toggle('is-loading', isLoading);
    });
  };

  const setMicroBillingMetric = (section, metric) => {
    if (!section || !['os', 'deb'].includes(metric)) return;

    section.dataset.billingMetric = metric;
    section.querySelectorAll('[data-billing-metric]').forEach(button => {
      const active = button.getAttribute('data-billing-metric') === metric;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    section.querySelectorAll('[data-metric-display]').forEach(element => {
      element.classList.toggle('d-none', element.getAttribute('data-metric-display') !== metric);
    });
  };

  const setMicroBillingView = (section, view) => {
    if (!section || !['m0', 'm1', 'compare'].includes(view)) return;

    section.dataset.billingView = view;
    section.querySelectorAll('[data-billing-view]').forEach(button => {
      const active = button.getAttribute('data-billing-view') === view;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    section.querySelectorAll('.card-view-content').forEach(element => {
      element.classList.toggle('d-none', !element.classList.contains(`view-${view}`));
    });

    const grid = section.querySelector('.micro-billing-calendar-grid');
    if (grid) {
      grid.classList.remove('view-mode-m0', 'view-mode-m1', 'view-mode-compare');
      grid.classList.add(`view-mode-${view}`);
    }
  };

  const initializeMicroBilling = container => {
    const section = container?.querySelector('[data-micro-billing-section]');
    if (!section) return;

    section.querySelectorAll('[data-billing-metric]').forEach(metricButton => {
      if (metricButton.dataset.billingControlBound !== '1') {
        metricButton.addEventListener('click', event => {
          event.preventDefault();
          event.stopPropagation();
          setMicroBillingMetric(section, metricButton.getAttribute('data-billing-metric'));
        });
        metricButton.dataset.billingControlBound = '1';
      }
    });
    section.querySelectorAll('[data-billing-view]').forEach(viewButton => {
      if (viewButton.dataset.billingControlBound !== '1') {
        viewButton.addEventListener('click', event => {
          event.preventDefault();
          event.stopPropagation();
          setMicroBillingView(section, viewButton.getAttribute('data-billing-view'));
        });
        viewButton.dataset.billingControlBound = '1';
      }
    });

    const metric = section.querySelector('[data-billing-metric].is-active')?.getAttribute('data-billing-metric') || 'os';
    const view = section.querySelector('[data-billing-view].is-active')?.getAttribute('data-billing-view') || 'm0';
    setMicroBillingMetric(section, metric);
    setMicroBillingView(section, view);
  };

  const consumerOperationsErrorHtml = message => `
    <div class="micro-ops-load-error" role="alert">
      <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
      <h3>Consumer Growth Command Center belum dapat dimuat</h3>
      <p>${escapeHtml(message || 'Koneksi ke sumber pipeline atau snapshot RM belum berhasil.')}</p>
      <button type="button" data-consumer-operations-retry><i class="fas fa-redo mr-1"></i>Coba lagi</button>
    </div>`;

  const setConsumerRefreshState = isLoading => {
    consumerOperationsDashboard?.querySelectorAll('[data-consumer-operations-refresh]').forEach(button => {
      button.disabled = isLoading;
      button.classList.toggle('is-loading', isLoading);
    });
  };

  const openConsumerQuadrantDetails = trigger => {
    if (!trigger || trigger.disabled || !window.Swal) return;

    let rmDetails = [];
    try {
      rmDetails = JSON.parse(trigger.dataset.consumerQuadrantRms || '[]');
    } catch (error) {
      rmDetails = [];
    }

    const quadrant = String(trigger.dataset.consumerQuadrant || '-');
    const product = String(trigger.dataset.consumerQuadrantProductLabel || 'Kuadran RM');
    const branch = String(trigger.dataset.consumerQuadrantBranchLabel || '-');
    const period = String(trigger.dataset.consumerQuadrantPeriodLabel || '-');
    const rows = Array.isArray(rmDetails) ? rmDetails : [];
    const list = rows.length
      ? `<div class="consumer-quadrant-modal__list">${rows.map((rm, index) => `
          <div class="consumer-quadrant-modal__row">
            <span class="consumer-quadrant-modal__number">${index + 1}</span>
            <span>
              <strong class="consumer-quadrant-modal__name">${escapeHtml(rm?.name || '-')}</strong>
              <small class="consumer-quadrant-modal__branch">${escapeHtml(rm?.branch || branch)}</small>
            </span>
          </div>`).join('')}</div>`
      : '<p class="consumer-quadrant-modal__lead">Tidak ada RM pada kuadran yang dipilih.</p>';

    lastConsumerQuadrantTrigger = trigger;
    window.Swal.fire({
      title: `${escapeHtml(product)} - Kuadran ${escapeHtml(quadrant)}`,
      html: `<p class="consumer-quadrant-modal__lead"><strong>${escapeHtml(branch)}</strong> &middot; ${escapeHtml(period)} &middot; ${rows.length.toLocaleString('id-ID')} RM</p>${list}`,
      confirmButtonText: 'Tutup',
      width: 680,
      customClass: { popup: 'consumer-quadrant-modal' },
      didClose: () => {
        lastConsumerQuadrantTrigger?.focus?.();
        lastConsumerQuadrantTrigger = null;
      },
    });
  };

  const formatMicroPipelineAmount = value => {
    const amount = Number(value || 0);
    const juta = Math.abs(amount) / 1000000;
    const digits = juta >= 100 ? 0 : (juta >= 10 ? 1 : 2);
    return `${amount < 0 ? '-' : ''}Rp ${juta.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: digits })} jt`;
  };

  const microPipelineStatusHtml = row => {
    const status = ['done', 'scheduled', 'pending'].includes(row?.visit_status) ? row.visit_status : 'pending';
    const icon = status === 'done' ? 'check-circle' : (status === 'scheduled' ? 'calendar-check' : 'clock');
    const months = status === 'done' ? row?.visit_months : row?.planned_months;
    const monthText = Array.isArray(months) && months.length ? months.join(', ') : '-';
    const detail = status === 'done'
      ? `DONE: ${escapeHtml(monthText)}`
      : (status === 'scheduled' ? `PLAN: ${escapeHtml(monthText)}` : 'Belum ada DONE / PLAN');
    return `<span class="micro-pipeline-status is-${status}"><i class="fas fa-${icon}" aria-hidden="true"></i>${escapeHtml(row?.visit_status_label || '-')}</span><small>${detail}</small>`;
  };

  const renderMicroPipelineRows = (modal, payload) => {
    const body = modal?.querySelector('[data-micro-pipeline-body]');
    if (!body) return;
    const rows = Array.isArray(payload?.data) ? payload.data : [];
    const meta = payload?.meta || {};
    body.innerHTML = rows.length ? rows.map(row => `<tr>
      <td><strong>${escapeHtml(row.debtor_name || '-')}</strong><small>CIF ${escapeHtml(row.cif || '-')}</small></td>
      <td><strong>${escapeHtml(row.unit_name || '-')}</strong><small>${escapeHtml(row.branch_name || '-')} &middot; ${escapeHtml(row.mantri_name || '-')}</small></td>
      <td>${escapeHtml(row.source_pipeline || '-')}</td>
      <td><strong>${escapeHtml(row.recommended_product || '-')}</strong>${row.dataset === 'slik_hijau' ? '' : `<small>OS ${escapeHtml(formatMicroPipelineAmount(row.outstanding))}</small>`}</td>
      <td><strong>${escapeHtml(formatMicroPipelineAmount(row.plafond))}</strong></td>
      <td>${escapeHtml(row.description || '-')}</td>
      <td>${microPipelineStatusHtml(row)}</td>
    </tr>`).join('') : '<tr><td colspan="7" class="is-empty">Nominatif tidak ditemukan untuk filter ini.</td></tr>';

    const count = modal.querySelector('[data-micro-pipeline-result-count]');
    const pageInfo = modal.querySelector('[data-micro-pipeline-page-info]');
    if (count) count.textContent = `${Number(meta.total || 0).toLocaleString('id-ID')} nominatif`;
    if (pageInfo) pageInfo.textContent = `Baris ${Number(meta.from || 0).toLocaleString('id-ID')}-${Number(meta.to || 0).toLocaleString('id-ID')} dari ${Number(meta.total || 0).toLocaleString('id-ID')} | Halaman ${Number(meta.page || 1)} dari ${Number(meta.last_page || 1)}`;
    modal.querySelectorAll('[data-micro-pipeline-page]').forEach(button => {
      button.disabled = button.dataset.microPipelinePage === 'prev'
        ? Number(meta.page || 1) <= 1
        : Number(meta.page || 1) >= Number(meta.last_page || 1);
    });
    microPipelinePayload = payload;
  };

  const prepareMicroPipelineModal = () => {
    const localModal = microPerformanceDashboard?.querySelector('[data-micro-pipeline-modal]');
    if (!localModal) return null;
    const existing = document.querySelector('body > [data-micro-pipeline-modal]');
    if (existing && existing !== localModal) existing.remove();
    const initialNode = microPerformanceDashboard.querySelector('[data-micro-pipeline-initial]');
    try {
      microPipelinePayload = JSON.parse(initialNode?.textContent || '{}');
    } catch (error) {
      microPipelinePayload = { data: [], meta: { page: 1, last_page: 1, total: 0, from: 0, to: 0 } };
    }
    document.body.appendChild(localModal);
    renderMicroPipelineRows(localModal, microPipelinePayload);
    return localModal;
  };

  const closeMicroPipelineModal = () => {
    const modal = document.querySelector('body > [data-micro-pipeline-modal]');
    if (!modal || modal.hidden) return;
    modal.hidden = true;
    document.body.classList.remove('micro-pipeline-modal-open');
    microPipelineRequest?.abort?.();
    microPipelineRequest = null;
    lastMicroPipelineTrigger?.focus?.();
    lastMicroPipelineTrigger = null;
  };

  const loadMicroPipelineRows = async (modal, page = 1) => {
    if (!modal?.dataset.url) return;
    const form = modal.querySelector('[data-micro-pipeline-filters]');
    const body = modal.querySelector('[data-micro-pipeline-body]');
    if (!form || !body) return;

    microPipelineRequest?.abort?.();
    const controller = new AbortController();
    microPipelineRequest = controller;
    const requestUrl = new URL(modal.dataset.url, window.location.origin);
    const formData = new FormData(form);
    for (const [key, value] of formData.entries()) {
      if (String(value).trim() !== '') requestUrl.searchParams.set(key, String(value));
    }
    requestUrl.searchParams.set('page', String(Math.max(1, Number(page) || 1)));
    requestUrl.searchParams.set('per_page', '20');
    body.innerHTML = '<tr><td colspan="7" class="is-loading"><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Memuat nominatif pipeline...</td></tr>';
    modal.setAttribute('aria-busy', 'true');

    try {
      const response = await fetch(requestUrl.toString(), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store',
        signal: controller.signal,
      });
      const payload = await response.json();
      if (!response.ok || !Array.isArray(payload?.data)) {
        throw new Error(payload?.message || 'Respons nominatif pipeline tidak valid.');
      }
      renderMicroPipelineRows(modal, payload);
    } catch (error) {
      if (error?.name === 'AbortError') return;
      body.innerHTML = `<tr><td colspan="7" class="is-empty"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> ${escapeHtml(error?.message || 'Nominatif pipeline belum dapat dimuat.')}</td></tr>`;
    } finally {
      if (microPipelineRequest === controller) microPipelineRequest = null;
      modal.setAttribute('aria-busy', 'false');
    }
  };

  const openMicroPipelineModal = trigger => {
    const modal = document.querySelector('body > [data-micro-pipeline-modal]') || prepareMicroPipelineModal();
    if (!modal) return;
    const dataset = trigger?.getAttribute('data-micro-pipeline-open') === 'slik_hijau' ? 'slik_hijau' : 'prewash';
    const form = modal.querySelector('[data-micro-pipeline-filters]');
    const datasetSelect = form?.querySelector('[name="dataset"]');
    const statusSelect = form?.querySelector('[name="status"]');
    const sourceSelect = form?.querySelector('[name="source"]');
    const productSelect = form?.querySelector('[name="product"]');
    if (datasetSelect) datasetSelect.value = dataset;
    if (statusSelect) statusSelect.value = dataset === 'slik_hijau' ? 'all' : 'open';
    if (sourceSelect) sourceSelect.value = '';
    if (productSelect) productSelect.value = '';
    const sheet = dataset === 'slik_hijau' ? modal.dataset.slikHijauSheet : modal.dataset.prewashSheet;
    const sourceUrl = dataset === 'slik_hijau' ? modal.dataset.slikHijauUrl : modal.dataset.prewashUrl;
    const sheetLabel = modal.querySelector('[data-micro-pipeline-sheet]');
    const sourceLink = modal.querySelector('[data-micro-pipeline-source-link]');
    if (sheetLabel) sheetLabel.textContent = `Sheet: ${sheet || '-'}`;
    if (sourceLink && sourceUrl) sourceLink.href = sourceUrl;
    lastMicroPipelineTrigger = trigger;
    modal.hidden = false;
    document.body.classList.add('micro-pipeline-modal-open');
    if (dataset === 'prewash') renderMicroPipelineRows(modal, microPipelinePayload || {});
    loadMicroPipelineRows(modal, 1);
    window.requestAnimationFrame(() => modal.querySelector('[data-micro-pipeline-close]:not(.micro-pipeline-modal__backdrop)')?.focus());
  };

  const formatMicroPipelineRate = value => `${Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;

  const prepareMicroPipelineSourceModal = () => {
    const localModal = microPerformanceDashboard?.querySelector('[data-micro-pipeline-source-modal]');
    if (!localModal) return null;
    const existing = document.querySelector('body > [data-micro-pipeline-source-modal]');
    if (existing && existing !== localModal) existing.remove();
    document.body.appendChild(localModal);

    return localModal;
  };

  const renderMicroPipelineSourceProgress = (modal, source) => {
    const title = modal?.querySelector('[data-micro-pipeline-source-title]');
    const heading = modal?.querySelector('[data-micro-pipeline-source-group-heading]');
    const summary = modal?.querySelector('[data-micro-pipeline-source-summary]');
    const body = modal?.querySelector('[data-micro-pipeline-source-body]');
    const meta = modal?.querySelector('[data-micro-pipeline-source-meta]');
    if (!modal || !summary || !body) return;

    const groupLabel = String(source?.group_label || 'Cabang');
    const total = Number(source?.total || 0);
    const done = Number(source?.done || 0);
    const scheduled = Number(source?.scheduled || 0);
    const pending = Number(source?.pending || 0);
    const groups = (Array.isArray(source?.groups) ? source.groups : [])
      .slice()
      .sort((left, right) => String(left?.label || '').localeCompare(String(right?.label || ''), 'id', { numeric: true }));

    if (title) title.textContent = `${source?.label || 'Sumber Pipeline'} - Progress per ${groupLabel}`;
    if (heading) heading.textContent = groupLabel;
    if (meta) meta.textContent = `${groups.length.toLocaleString('id-ID')} ${groupLabel.toLowerCase()} pada sumber ${source?.label || '-'}`;
    summary.innerHTML = [
      ['Total Pipeline', total.toLocaleString('id-ID')],
      ['Selesai Dikunjungi', done.toLocaleString('id-ID')],
      ['Terjadwal', scheduled.toLocaleString('id-ID')],
      ['Belum Dikunjungi', pending.toLocaleString('id-ID')],
      ['Potensi Plafon', formatMicroPipelineAmount(source?.potential_plafond)],
    ].map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
    body.innerHTML = groups.length
      ? groups.map((group, index) => {
          const groupTotal = Number(group?.total || 0);
          const groupDone = Number(group?.done || 0);
          const groupScheduled = Number(group?.scheduled || 0);
          const groupPending = Number(group?.pending || 0);
          const progress = Number(group?.visit_rate || 0);
          return `<tr>
            <td>${(index + 1).toLocaleString('id-ID')}</td>
            <td><strong>${escapeHtml(group?.label || '-')}</strong></td>
            <td>${groupTotal.toLocaleString('id-ID')}</td>
            <td>${groupDone.toLocaleString('id-ID')}</td>
            <td>${groupScheduled.toLocaleString('id-ID')}</td>
            <td>${groupPending.toLocaleString('id-ID')}</td>
            <td><div class="micro-pipeline-source-progress"><div class="micro-pipeline-segmented-bar" aria-label="Progress ${escapeHtml(group?.label || '-')}: ${formatMicroPipelineRate(progress)}"><span class="is-done" style="width: ${Math.max(0, Math.min(100, progress))}%"></span></div><strong>${formatMicroPipelineRate(progress)}</strong></div></td>
            <td>${escapeHtml(formatMicroPipelineAmount(group?.potential_plafond))}</td>
          </tr>`;
        }).join('')
      : `<tr><td colspan="8" class="micro-pipeline-source-modal__empty">Belum ada distribusi ${escapeHtml(groupLabel.toLowerCase())} untuk sumber ini.</td></tr>`;
  };

  const closeMicroPipelineSourceModal = () => {
    const modal = document.querySelector('body > [data-micro-pipeline-source-modal]');
    if (!modal || modal.hidden) return;
    modal.hidden = true;
    document.body.classList.remove('micro-pipeline-source-modal-open');
    activeMicroPipelineSource = null;
    lastMicroPipelineSourceTrigger?.focus?.();
    lastMicroPipelineSourceTrigger = null;
  };

  const openMicroPipelineSourceModal = trigger => {
    if (!trigger) return;
    let source;
    try {
      source = JSON.parse(trigger.getAttribute('data-micro-pipeline-source-detail') || '{}');
    } catch (error) {
      return;
    }
    if (!source || typeof source !== 'object') return;

    const modal = document.querySelector('body > [data-micro-pipeline-source-modal]') || prepareMicroPipelineSourceModal();
    if (!modal) return;
    lastMicroPipelineSourceTrigger = trigger;
    activeMicroPipelineSource = source;
    renderMicroPipelineSourceProgress(modal, source);
    modal.hidden = false;
    document.body.classList.add('micro-pipeline-source-modal-open');
    window.requestAnimationFrame(() => modal.querySelector('[data-micro-pipeline-source-close]:not(.micro-pipeline-source-modal__backdrop)')?.focus());
  };

  const loadSmeOperations = (forceRefresh = false) => {
    if (!smeOperationsDashboard || !smeOperationsDashboard.dataset.url) {
      return Promise.resolve();
    }

    if (!forceRefresh && smeOperationsDashboard.dataset.loaded === '1') {
      return Promise.resolve();
    }

    if (smeOperationsRequest) {
      return smeOperationsRequest;
    }

    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 35000);
    const requestUrl = new URL(smeOperationsDashboard.dataset.url, window.location.origin);
    requestUrl.searchParams.set('_', String(Date.now()));
    if (forceRefresh) {
      requestUrl.searchParams.set('refresh', '1');
    }

    smeOperationsDashboard.setAttribute('aria-busy', 'true');
    setSmeRefreshState(true);
    smeOperationsRequest = fetch(requestUrl.toString(), {
      credentials: 'same-origin',
      headers: {
        'Accept': 'text/html',
        'X-Requested-With': 'XMLHttpRequest'
      },
      cache: 'no-store',
      signal: controller.signal
    })
      .then(async response => {
        const html = await response.text();
        if (!response.ok || !html.includes('data-sme-operations-ready="1"')) {
          throw new Error('Respons SME tidak lengkap atau sesi login telah berakhir.');
        }

        closeSmeVendorModal();
        closeSmeUnproductiveModal();
        document.querySelector('body > [data-sme-vendor-modal]')?.remove();
        document.querySelector('body > [data-sme-unproductive-modal]')?.remove();
        smeOperationsDashboard.innerHTML = html;
        smeOperationsDashboard.dataset.loaded = '1';
        prepareSmeVendorModal();
        prepareSmeUnproductiveModal();
      })
      .catch(error => {
        if (forceRefresh && smeOperationsDashboard.dataset.loaded === '1') {
          const refreshButton = smeOperationsDashboard.querySelector('[data-sme-operations-refresh]');
          if (refreshButton) {
            refreshButton.title = 'Pembaruan gagal. Data terakhir tetap ditampilkan.';
          }
          return;
        }

        const message = error?.name === 'AbortError'
          ? 'Proses sinkronisasi melewati batas waktu. Data sumber dapat diperbarui kembali beberapa saat lagi.'
          : (error?.message || 'Koneksi ke sumber data belum berhasil.');
        smeOperationsDashboard.innerHTML = smeOperationsErrorHtml(message);
        delete smeOperationsDashboard.dataset.loaded;
      })
      .finally(() => {
        window.clearTimeout(timeoutId);
        smeOperationsDashboard.setAttribute('aria-busy', 'false');
        setSmeRefreshState(false);
        smeOperationsRequest = null;
      });

    return smeOperationsRequest;
  };

  const loadMicroPerformance = (forceRefresh = false) => {
    if (!microPerformanceDashboard || !microPerformanceDashboard.dataset.url) {
      return Promise.resolve();
    }
    if (!forceRefresh && microPerformanceDashboard.dataset.loaded === '1') {
      return Promise.resolve();
    }
    if (microPerformanceRequest) {
      return microPerformanceRequest;
    }

    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 90000);
    const requestUrl = new URL(microPerformanceDashboard.dataset.url, window.location.origin);
    requestUrl.searchParams.set('_', String(Date.now()));
    if (forceRefresh) {
      requestUrl.searchParams.set('refresh', '1');
    }

    microPerformanceDashboard.setAttribute('aria-busy', 'true');
    setMicroRefreshState(true);
    microPerformanceRequest = fetch(requestUrl.toString(), {
      credentials: 'same-origin',
      headers: {
        'Accept': 'text/html',
        'X-Requested-With': 'XMLHttpRequest'
      },
      cache: 'no-store',
      signal: controller.signal
    })
      .then(async response => {
        const html = await response.text();
        if (!response.ok || !html.includes('data-micro-performance-ready="1"')) {
          throw new Error('Respons Mikro tidak lengkap atau sesi login telah berakhir.');
        }
        closeMicroPipelineModal();
        document.querySelector('body > [data-micro-pipeline-modal]')?.remove();
        closeMicroPipelineSourceModal();
        document.querySelector('body > [data-micro-pipeline-source-modal]')?.remove();
        microPerformanceDashboard.innerHTML = html;
        microPerformanceDashboard.dataset.loaded = '1';
        initializeMicroBilling(microPerformanceDashboard);
        prepareMicroPipelineModal();
      })
      .catch(error => {
        if (forceRefresh && microPerformanceDashboard.dataset.loaded === '1') {
          return;
        }
        const message = error?.name === 'AbortError'
          ? 'Perhitungan melewati batas waktu. Silakan coba kembali setelah cache selesai disiapkan.'
          : (error?.message || 'Perhitungan Daily Loan Dinamis belum berhasil.');
        microPerformanceDashboard.innerHTML = microPerformanceErrorHtml(message);
        delete microPerformanceDashboard.dataset.loaded;
      })
      .finally(() => {
        window.clearTimeout(timeoutId);
        microPerformanceDashboard.setAttribute('aria-busy', 'false');
        setMicroRefreshState(false);
        microPerformanceRequest = null;
      });

    return microPerformanceRequest;
  };

  const loadConsumerOperations = (forceRefresh = false) => {
    if (!consumerOperationsDashboard || !consumerOperationsDashboard.dataset.url) {
      return Promise.resolve();
    }
    if (!forceRefresh && consumerOperationsDashboard.dataset.loaded === '1') {
      return Promise.resolve();
    }
    if (consumerOperationsRequest) {
      return consumerOperationsRequest;
    }

    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 35000);
    const requestUrl = new URL(consumerOperationsDashboard.dataset.url, window.location.origin);
    requestUrl.searchParams.set('_', String(Date.now()));
    if (forceRefresh) {
      requestUrl.searchParams.set('refresh', '1');
    }

    consumerOperationsDashboard.setAttribute('aria-busy', 'true');
    setConsumerRefreshState(true);
    consumerOperationsRequest = fetch(requestUrl.toString(), {
      credentials: 'same-origin',
      headers: {
        'Accept': 'text/html',
        'X-Requested-With': 'XMLHttpRequest'
      },
      cache: 'no-store',
      signal: controller.signal
    })
      .then(async response => {
        const html = await response.text();
        if (!response.ok || !html.includes('data-consumer-operations-ready="1"')) {
          throw new Error('Respons Konsumer tidak lengkap atau sesi login telah berakhir.');
        }
        consumerOperationsDashboard.innerHTML = html;
        consumerOperationsDashboard.dataset.loaded = '1';
      })
      .catch(error => {
        if (forceRefresh && consumerOperationsDashboard.dataset.loaded === '1') {
          const refreshButton = consumerOperationsDashboard.querySelector('[data-consumer-operations-refresh]');
          if (refreshButton) refreshButton.title = 'Pembaruan gagal. Data terakhir tetap ditampilkan.';
          return;
        }
        const message = error?.name === 'AbortError'
          ? 'Sinkronisasi melewati batas waktu. Silakan coba lagi; cache terakhir tidak dihapus.'
          : (error?.message || 'Dashboard Konsumer belum berhasil dimuat.');
        consumerOperationsDashboard.innerHTML = consumerOperationsErrorHtml(message);
        delete consumerOperationsDashboard.dataset.loaded;
      })
      .finally(() => {
        window.clearTimeout(timeoutId);
        consumerOperationsDashboard.setAttribute('aria-busy', 'false');
        setConsumerRefreshState(false);
        consumerOperationsRequest = null;
      });

    return consumerOperationsRequest;
  };

  const syncLandingScopeMode = scope => {
    const isSme = scope === 'sme';
    const isMicro = scope === 'micro';
    const isConsumer = scope === 'consumer';
    const activeScopeButton = document.querySelector(`.area6-scope-btn[data-area6-scope="${scope}"]`);
    dashboardShell?.classList.toggle('sme-operations-active', isSme);
    dashboardShell?.classList.toggle('micro-performance-active', isMicro);
    dashboardShell?.classList.toggle('consumer-active', isConsumer);
    smeOperationsDashboard?.classList.toggle('d-none', !isSme);
    microPerformanceDashboard?.classList.toggle('d-none', !isMicro);
    consumerOperationsDashboard?.classList.toggle('d-none', !isConsumer);

    document.querySelectorAll('.scope-illu').forEach(el => {
      el.classList.toggle('d-none', el.getAttribute('data-illu-scope') !== scope);
    });

    if (area6ScopeTitle) {
      area6ScopeTitle.textContent = activeScopeButton?.dataset.scopeTitle || defaultScopeTitle;
    }
    if (area6ScopeSubtitle) {
      area6ScopeSubtitle.textContent = activeScopeButton?.dataset.scopeSubtitle || defaultScopeSubtitle;
    }

    if (isSme) {
      loadSmeOperations(false);
    }
    if (isMicro) {
      loadMicroPerformance(false);
    }
    if (isConsumer) {
      loadConsumerOperations(false);
    }
    if (['sme', 'consumer', 'micro'].includes(scope)) {
      loadLoanAnalytics(scope, false);
    }
  };

  const selectLandingPrognosaWeek = weekLabel => {
    if (!/^W[1-5]$/.test(String(weekLabel || ''))) {
      return;
    }

    document.querySelectorAll('[data-prognosa-week-control]').forEach(control => {
      const selectedButton = control.querySelector(`[data-prognosa-week-select="${weekLabel}"]:not(:disabled)`);
      if (!selectedButton) {
        return;
      }

      control.querySelectorAll('[data-prognosa-week-select]').forEach(button => {
        const active = button === selectedButton;
        button.classList.toggle('active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
    });

    document.querySelectorAll('[data-prognosa-week-panel]').forEach(panel => {
      panel.hidden = panel.getAttribute('data-prognosa-week-panel') !== weekLabel;
    });
  };

  document.querySelectorAll('[data-prognosa-week-select]').forEach(button => {
    button.addEventListener('click', event => {
      event.preventDefault();
      selectLandingPrognosaWeek(button.getAttribute('data-prognosa-week-select'));
    });
  });

  document.querySelectorAll('.area6-scope-btn').forEach(button => {
    button.setAttribute('aria-pressed', button.classList.contains('active') ? 'true' : 'false');

    button.addEventListener('click', function() {
      const scope = this.getAttribute('data-area6-scope');

      document.querySelectorAll('.area6-scope-btn').forEach(item => {
        item.classList.toggle('active', item === this);
        item.setAttribute('aria-pressed', item === this ? 'true' : 'false');
      });

      document.querySelectorAll('.area6-ranking-mode').forEach(panel => {
        panel.classList.toggle('d-none', panel.getAttribute('data-area6-ranking-scope') !== scope);
      });

      document.querySelectorAll('.area6-scope-content').forEach(panel => {
        const isActive = panel.getAttribute('data-area6-content-scope') === scope;
        panel.classList.toggle('d-none', !isActive);

        if (isActive) {
          panel.querySelectorAll('.area6-card-premium').forEach((card, index) => {
            card.style.setProperty('--landing-card-delay', `${index * 45}ms`);
            card.classList.remove('is-entering');
            window.requestAnimationFrame(() => card.classList.add('is-entering'));
          });
          window.requestAnimationFrame(() => initializeLandingAnalyticsCharts(panel));
        }
      });

      document.querySelectorAll('.landing-realization-panel').forEach(panel => {
        panel.classList.toggle('d-none', panel.getAttribute('data-landing-realization-panel') !== scope);
      });

      syncLandingScopeMode(scope);
      document.dispatchEvent(new CustomEvent('landing:scopechange', { detail: { scope } }));
    });
  });

  const initialArea6Scope = document.querySelector('.area6-scope-btn.active')?.getAttribute('data-area6-scope') || '';
  syncLandingScopeMode(initialArea6Scope);
  const initialAnalyticsPanel = document.querySelector(`.area6-scope-content[data-area6-content-scope="${initialArea6Scope}"]`);
  window.requestAnimationFrame(() => initializeLandingAnalyticsCharts(initialAnalyticsPanel));

  const smeScopeButton = document.querySelector('.area6-scope-btn[data-area6-scope="sme"]');
  ['pointerenter', 'focus'].forEach(eventName => {
    smeScopeButton?.addEventListener(eventName, () => loadSmeOperations(false), { passive: true });
  });
  const microScopeButton = document.querySelector('.area6-scope-btn[data-area6-scope="micro"]');
  ['pointerenter', 'focus'].forEach(eventName => {
    microScopeButton?.addEventListener(eventName, () => loadMicroPerformance(false), { passive: true });
  });
  const consumerScopeButton = document.querySelector('.area6-scope-btn[data-area6-scope="consumer"]');
  ['pointerenter', 'focus'].forEach(eventName => {
    consumerScopeButton?.addEventListener(eventName, () => loadConsumerOperations(false), { passive: true });
  });
  document.querySelectorAll('.area6-scope-btn[data-area6-scope="sme"], .area6-scope-btn[data-area6-scope="consumer"], .area6-scope-btn[data-area6-scope="micro"]').forEach(button => {
    ['pointerenter', 'focus'].forEach(eventName => {
      button.addEventListener(eventName, () => loadLoanAnalytics(button.dataset.area6Scope, false), { passive: true });
    });
  });

  document.addEventListener('click', event => {
    const retry = event.target.closest('[data-loan-analytics-retry]');
    if (!retry) return;
    event.preventDefault();
    loadLoanAnalytics(retry.dataset.loanAnalyticsRetry, true);
  });

  consumerOperationsDashboard?.addEventListener('click', event => {
    const refreshButton = event.target.closest('[data-consumer-operations-refresh]');
    const retryButton = event.target.closest('[data-consumer-operations-retry]');
    const pipelineTab = event.target.closest('[data-consumer-pipeline-tab]');
    const quadrantTab = event.target.closest('[data-consumer-quadrant-tab]');
    const quadrantDetail = event.target.closest('[data-consumer-quadrant-detail]');

    if (quadrantDetail) {
      const isKeyboardActivation = event.detail === 0;
      const isTouchDevice = window.matchMedia?.('(hover: none), (pointer: coarse)').matches;
      if (isKeyboardActivation || isTouchDevice) {
        event.preventDefault();
        openConsumerQuadrantDetails(quadrantDetail);
      }
      return;
    }

    if (refreshButton) {
      event.preventDefault();
      loadConsumerOperations(true);
      return;
    }
    if (retryButton) {
      event.preventDefault();
      loadConsumerOperations(false);
      return;
    }
    if (pipelineTab) {
      const key = pipelineTab.dataset.consumerPipelineTab || '';
      const section = pipelineTab.closest('.consumer-ops-section');
      section?.querySelectorAll('[data-consumer-pipeline-tab]').forEach(button => {
        const active = button === pipelineTab;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
        button.tabIndex = active ? 0 : -1;
      });
      section?.querySelectorAll('[data-consumer-pipeline-panel]').forEach(panel => {
        panel.hidden = panel.dataset.consumerPipelinePanel !== key;
      });
      return;
    }
    if (quadrantTab) {
      const branch = quadrantTab.dataset.consumerQuadrantTab || '';
      const product = quadrantTab.dataset.consumerQuadrantProduct || '';
      const card = quadrantTab.closest('.consumer-ops-product');
      card?.querySelectorAll('[data-consumer-quadrant-tab]').forEach(button => {
        const active = button.dataset.consumerQuadrantTab === branch
          && button.dataset.consumerQuadrantProduct === product;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
        button.tabIndex = active ? 0 : -1;
      });
      card?.querySelectorAll('[data-consumer-quadrant-panel]').forEach(panel => {
        panel.hidden = panel.dataset.consumerQuadrantPanel !== branch
          || panel.dataset.consumerQuadrantProduct !== product;
      });
    }
  });

  consumerOperationsDashboard?.addEventListener('dblclick', event => {
    const quadrantDetail = event.target.closest('[data-consumer-quadrant-detail]');
    if (!quadrantDetail) return;
    event.preventDefault();
    openConsumerQuadrantDetails(quadrantDetail);
  });

  consumerOperationsDashboard?.addEventListener('keydown', event => {
    const tab = event.target.closest('[data-consumer-pipeline-tab], [data-consumer-quadrant-tab]');
    if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    const group = Array.from(tab.closest('[role="tablist"]')?.querySelectorAll('[role="tab"]') || []);
    if (group.length === 0) return;
    event.preventDefault();
    const index = group.indexOf(tab);
    const nextIndex = event.key === 'Home'
      ? 0
      : (event.key === 'End'
        ? group.length - 1
        : (index + (event.key === 'ArrowRight' ? 1 : -1) + group.length) % group.length);
    group[nextIndex]?.click();
    group[nextIndex]?.focus();
  });

  smeOperationsDashboard?.addEventListener('click', event => {
    const refreshButton = event.target.closest('[data-sme-operations-refresh]');
    const retryButton = event.target.closest('[data-sme-operations-retry]');
    const unproductiveButton = event.target.closest('[data-sme-unproductive-open]');
    if (unproductiveButton) {
      event.preventDefault();
      event.stopPropagation();
      openSmeUnproductiveModal(unproductiveButton);
    } else if (refreshButton) {
      event.preventDefault();
      loadSmeOperations(true);
    } else if (retryButton) {
      event.preventDefault();
      loadSmeOperations(false);
    }
  });

  smeOperationsDashboard?.addEventListener('dblclick', event => {
    const unproductiveDetail = event.target.closest('[data-sme-unproductive-detail]');
    if (unproductiveDetail) {
      event.preventDefault();
      openSmeUnproductiveModal(unproductiveDetail);
      return;
    }

    const vendorCard = event.target.closest('[data-sme-vendor-detail]');
    if (!vendorCard) return;
    event.preventDefault();
    openSmeVendorModal(vendorCard);
  });

  smeOperationsDashboard?.addEventListener('keydown', event => {
    const unproductiveDetail = event.target.closest('[data-sme-unproductive-detail]');
    if (unproductiveDetail && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      openSmeUnproductiveModal(unproductiveDetail);
      return;
    }

    const vendorCard = event.target.closest('[data-sme-vendor-detail]');
    if (!vendorCard) return;
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      openSmeVendorModal(vendorCard);
    }
  });

  document.addEventListener('input', event => {
    const unproductiveSearch = event.target.closest('[data-sme-unproductive-search]');
    if (unproductiveSearch) {
      renderSmeUnproductiveRows(unproductiveSearch.closest('[data-sme-unproductive-modal]'), unproductiveSearch.value);
      return;
    }

    const search = event.target.closest('[data-sme-vendor-search]');
    if (!search) return;
    renderSmeVendorNominatives(search.closest('[data-sme-vendor-modal]'), search.value);
  });

  document.addEventListener('click', event => {
    if (event.target.closest('[data-sme-unproductive-close]')) {
      event.preventDefault();
      closeSmeUnproductiveModal();
      return;
    }

    if (event.target.closest('[data-sme-vendor-close]')) {
      event.preventDefault();
      closeSmeVendorModal();
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      closeSmeVendorModal();
      closeSmeUnproductiveModal();
      closeMicroPipelineModal();
      closeMicroPipelineSourceModal();
    }
  });

  const pdwkPeopleRowsHtml = people => (Array.isArray(people) ? people : []).map(person => `
    <div class="micro-pdwk-modal__row">
      <span class="micro-pdwk-modal__pn">${escapeHtml(person?.pn || '-')}</span>
      <span>
        <strong class="micro-pdwk-modal__name">${escapeHtml(person?.name || '-')}</strong>
        <small class="micro-pdwk-modal__unit">${escapeHtml(person?.unit || person?.branch || '-')}</small>
      </span>
    </div>
  `).join('');

  const openMicroPdwkPeople = card => {
    if (!card || !window.Swal) return;

    const wrapper = card.closest('[data-micro-pdwk-limit]');
    const detailNode = wrapper?.querySelector('[data-micro-pdwk-details]');
    let roles = [];
    try {
      roles = JSON.parse(detailNode?.textContent || '[]');
    } catch (error) {
      roles = [];
    }

    const role = roles.find(item => String(item?.key || '') === String(card.dataset.microPdwkRoleKey || ''));
    const status = (role?.statuses || []).find(item => String(item?.key || '') === String(card.dataset.microPdwkStatusKey || ''));
    if (!role || !status) return;

    const activePeople = Array.isArray(status.people) ? status.people : [];
    const referencePeople = Array.isArray(status.reference_people) ? status.reference_people : [];
    const metricValues = card.querySelectorAll('.micro-pdwk-status__metrics strong');
    const activeSection = activePeople.length
      ? `<section class="micro-pdwk-modal__section">
          <h4>Pemutus bertransaksi bulan berjalan (${activePeople.length})</h4>
          <div class="micro-pdwk-modal__list">${pdwkPeopleRowsHtml(activePeople)}</div>
        </section>`
      : '<section class="micro-pdwk-modal__section"><h4>Pemutus bertransaksi bulan berjalan</h4><p>Belum ada realisasi Mikro pada status ini.</p></section>';
    const referenceSection = referencePeople.length
      ? `<section class="micro-pdwk-modal__section">
          <h4>Daftar limit PDWK dari workbook (${referencePeople.length})</h4>
          <div class="micro-pdwk-modal__list">${pdwkPeopleRowsHtml(referencePeople)}</div>
        </section>`
      : '<section class="micro-pdwk-modal__section"><h4>Daftar limit PDWK dari workbook</h4><p>Tidak ada pemutus pada status ini di workbook referensi.</p></section>';

    window.Swal.fire({
      title: `${escapeHtml(role.label || 'Pemutus')} - ${escapeHtml(status.label || 'Status PDWK')}`,
      html: `<p class="micro-pdwk-modal__lead">Nama dan limit PDWK hanya menggunakan workbook per 31 Juli 2026. Putusan dan plafon tetap mengacu pada realisasi Mikro bulan berjalan.</p>
        <div class="micro-pdwk-modal__summary">
          <div><span>Pemutus aktif</span><strong>${Number(status.pemutus || 0).toLocaleString('id-ID')}</strong></div>
          <div><span>Plafon realisasi</span><strong>${escapeHtml(metricValues[1]?.textContent?.trim() || '-')}</strong></div>
        </div>
        ${activeSection}${referenceSection}`,
      confirmButtonText: 'Tutup',
      width: 680,
      customClass: { popup: 'micro-pdwk-modal' },
    });
  };

  const formatMicroMantriTierAmount = value => {
    const amount = Number(value || 0);
    const juta = amount / 1000000;
    const digits = Number.isInteger(juta) ? 0 : 1;

    return `Rp ${juta.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: digits })} jt`;
  };

  const microMantriTierPeopleHtml = people => (Array.isArray(people) ? people : []).map(person => `
    <div class="micro-mantri-tier-modal__row">
      <span class="micro-mantri-tier-modal__pn">${escapeHtml(person?.pn || '-')}</span>
      <span>
        <strong class="micro-mantri-tier-modal__name">${escapeHtml(person?.name || '-')}</strong>
        <small class="micro-mantri-tier-modal__unit">${escapeHtml(person?.unit || person?.branch || '-')}</small>
      </span>
      <strong class="micro-mantri-tier-modal__amount">${escapeHtml(formatMicroMantriTierAmount(person?.net_amount))}<small>${Number(person?.net_deb || 0).toLocaleString('id-ID')} rekening</small></strong>
    </div>
  `).join('');

  const openMicroMantriTierPeople = cell => {
    if (!cell || !window.Swal) return;

    const row = cell.closest('[data-micro-mantri-tier-row]');
    if (!row) return;
    const tier = String(row.dataset.microMantriTier || '');
    const branchCode = String(row.dataset.microMantriBranchCode || '');
    const bucketKey = String(cell.dataset.microMantriBucket || '');
    const mantriSection = row.closest('.micro-ops-section--mantri');
    const detailsNode = mantriSection?.querySelector('[data-micro-mantri-tier-details]');
    let rows = [];
    try {
      rows = JSON.parse(detailsNode?.textContent || '[]');
    } catch (error) {
      rows = [];
    }

    const detail = rows.find(item => String(item?.branch_code || '') === branchCode);
    const buckets = Array.isArray(detail?.tiers?.[tier]?.buckets) ? detail.tiers[tier].buckets : [];
    const bucket = buckets.find(item => String(item?.key || '') === bucketKey);
    if (!detail || !bucket) return;

    const tierLabel = tier === 'contract' ? 'Mantri Kontrak' : 'Mantri PT Only';
    const key = String(bucket?.key || 'none').replace(/[^a-z_]/g, '');
    const people = Array.isArray(bucket?.people) ? bucket.people : [];
    const count = Number(bucket?.mantri || people.length || 0);
    const share = Number(bucket?.share || 0).toLocaleString('id-ID', { maximumFractionDigits: 1 });
    const bucketHtml = `<section class="micro-mantri-tier-modal__bucket tone-${key}">
      <header><strong>${escapeHtml(bucket?.label || '-')}</strong><span>${count.toLocaleString('id-ID')} Mantri · ${share}%</span></header>
      ${people.length
        ? `<div class="micro-mantri-tier-modal__list">${microMantriTierPeopleHtml(people)}</div>`
        : '<p class="micro-mantri-tier-modal__empty">Tidak ada Mantri pada kategori ini.</p>'}
    </section>`;

    window.Swal.fire({
      title: 'Nominatif Nett Disbursement',
      html: `<p class="micro-mantri-tier-modal__lead"><strong>${escapeHtml(tierLabel)}</strong> · ${escapeHtml(detail.branch || branchCode)}. Nominatif memakai nilai akumulatif nett disbursement pada periode yang sedang dipilih.</p><div class="micro-mantri-tier-modal__grid">${bucketHtml}</div>`,
      confirmButtonText: 'Tutup',
      width: 820,
      customClass: { popup: 'micro-mantri-tier-modal' },
    });
  };

  microPerformanceDashboard?.addEventListener('click', event => {
    const refreshButton = event.target.closest('[data-micro-performance-refresh]');
    const retryButton = event.target.closest('[data-micro-performance-retry]');
    const rankingButton = event.target.closest('[data-micro-ranking-metric]');
    const needFilter = event.target.closest('[data-micro-need-filter]');
    const pdwkRoleButton = event.target.closest('[data-micro-pdwk-role]');
    const pdwkDetailButton = event.target.closest('[data-micro-pdwk-open-detail]');
    const pipelineButton = event.target.closest('[data-micro-pipeline-open]');
    const pipelineSourceDetailButton = event.target.closest('[data-micro-pipeline-source-detail]');
    const freqToggle = event.target.closest('[data-freq-toggle]');
    const sourceToggle = event.target.closest('[data-source-toggle]');
    const billingMetricButton = event.target.closest('[data-billing-metric]');
    const billingViewButton = event.target.closest('[data-billing-view]');
    if (billingMetricButton) {
      event.preventDefault();
      setMicroBillingMetric(
        billingMetricButton.closest('[data-micro-billing-section]'),
        billingMetricButton.getAttribute('data-billing-metric')
      );
    } else if (billingViewButton) {
      event.preventDefault();
      setMicroBillingView(
        billingViewButton.closest('[data-micro-billing-section]'),
        billingViewButton.getAttribute('data-billing-view')
      );
    } else if (pipelineSourceDetailButton) {
      event.preventDefault();
      openMicroPipelineSourceModal(pipelineSourceDetailButton);
    } else if (freqToggle) {
      event.preventDefault();
      const item = freqToggle.closest('.micro-frequency-item');
      if (!item) return;
      const drawer = item.querySelector('.micro-freq-term-drawer');
      if (!drawer) return;
      const isOpen = item.classList.toggle('is-open');
      freqToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen) {
        drawer.removeAttribute('hidden');
      } else {
        drawer.setAttribute('hidden', '');
      }
    } else if (sourceToggle) {
      event.preventDefault();
      const card = sourceToggle.closest('.micro-pipeline-source-card');
      if (!card) return;
      const drawer = card.querySelector('.micro-pipeline-source-drawer');
      if (!drawer) return;
      const isOpen = card.classList.toggle('is-open');
      sourceToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen) {
        drawer.removeAttribute('hidden');
      } else {
        drawer.setAttribute('hidden', '');
      }
    } else if (pipelineButton) {
      event.preventDefault();
      openMicroPipelineModal(pipelineButton);
    } else if (pdwkDetailButton) {
      event.preventDefault();
      openMicroPdwkPeople(pdwkDetailButton.closest('[data-micro-pdwk-status]'));
    } else if (pdwkRoleButton) {
      event.preventDefault();
      const wrapper = pdwkRoleButton.closest('[data-micro-pdwk-limit]');
      const role = pdwkRoleButton.getAttribute('data-micro-pdwk-role');
      wrapper?.querySelectorAll('[data-micro-pdwk-role]').forEach(button => {
        const active = button === pdwkRoleButton;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      wrapper?.querySelectorAll('[data-micro-pdwk-panel]').forEach(panel => {
        const isMatch = panel.getAttribute('data-micro-pdwk-panel') === role;
        if (isMatch) {
          panel.removeAttribute('hidden');
        } else {
          panel.setAttribute('hidden', '');
        }
      });
    } else if (needFilter) {
      event.preventDefault();
      const section = needFilter.closest('.micro-ops-section--need');
      const key = needFilter.getAttribute('data-micro-need-filter');
      section?.querySelectorAll('[data-micro-need-filter]').forEach(button => {
        const active = button === needFilter;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      section?.querySelectorAll('[data-micro-need-panel]').forEach(panel => {
        const isMatch = panel.getAttribute('data-micro-need-panel') === key;
        if (isMatch) {
          panel.removeAttribute('hidden');
        } else {
          panel.setAttribute('hidden', '');
        }
      });
    } else if (rankingButton) {
      event.preventDefault();
      const ranking = rankingButton.closest('[data-micro-ranking]');
      const metric = rankingButton.getAttribute('data-micro-ranking-metric');
      ranking?.querySelectorAll('[data-micro-ranking-metric]').forEach(button => {
        const active = button === rankingButton;
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      ranking?.querySelectorAll('[data-micro-ranking-panel]').forEach(panel => {
        const isMatch = panel.getAttribute('data-micro-ranking-panel') === metric;
        if (isMatch) {
          panel.removeAttribute('hidden');
        } else {
          panel.setAttribute('hidden', '');
        }
      });
    } else if (refreshButton) {
      event.preventDefault();
      loadMicroPerformance(true);
    } else if (retryButton) {
      event.preventDefault();
      loadMicroPerformance(false);
    }
  });

  document.addEventListener('submit', event => {
    const form = event.target.closest('[data-micro-pipeline-filters]');
    if (!form) return;
    event.preventDefault();
    loadMicroPipelineRows(form.closest('[data-micro-pipeline-modal]'), 1);
  });

  document.addEventListener('change', event => {
    const datasetSelect = event.target.closest('[data-micro-pipeline-filters] [name="dataset"]');
    if (!datasetSelect) return;
    const form = datasetSelect.closest('[data-micro-pipeline-filters]');
    const isSlikHijau = datasetSelect.value === 'slik_hijau';
    const status = form?.querySelector('[name="status"]');
    const source = form?.querySelector('[name="source"]');
    const product = form?.querySelector('[name="product"]');
    if (status) status.value = isSlikHijau ? 'all' : 'open';
    if (source) source.value = '';
    if (product) product.value = '';
  });

  document.addEventListener('click', event => {
    const sourceCloseButton = event.target.closest('[data-micro-pipeline-source-close]');
    if (sourceCloseButton) {
      event.preventDefault();
      closeMicroPipelineSourceModal();
      return;
    }
    const sourceNominativeButton = event.target.closest('[data-micro-pipeline-source-open-nominatives]');
    if (sourceNominativeButton) {
      event.preventDefault();
      const source = String(activeMicroPipelineSource?.label || '');
      const modal = document.querySelector('body > [data-micro-pipeline-modal]') || prepareMicroPipelineModal();
      const sourceSelect = modal?.querySelector('[data-micro-pipeline-filters] [name="source"]');
      if (!modal || !sourceSelect) return;
      sourceSelect.value = source;
      closeMicroPipelineSourceModal();
      openMicroPipelineModal(sourceNominativeButton);
      loadMicroPipelineRows(modal, 1);
      return;
    }
    const closeButton = event.target.closest('[data-micro-pipeline-close]');
    if (closeButton) {
      event.preventDefault();
      closeMicroPipelineModal();
      return;
    }
    const pageButton = event.target.closest('[data-micro-pipeline-page]');
    if (!pageButton || pageButton.disabled) return;
    event.preventDefault();
    const currentPage = Number(microPipelinePayload?.meta?.page || 1);
    loadMicroPipelineRows(pageButton.closest('[data-micro-pipeline-modal]'), pageButton.dataset.microPipelinePage === 'prev' ? currentPage - 1 : currentPage + 1);
  });

  microPerformanceDashboard?.addEventListener('dblclick', event => {
    if (event.target.closest('button, a, input, select, textarea')) return;

    const pdwkCard = event.target.closest('[data-micro-pdwk-status]');
    const mantriTierCount = event.target.closest('[data-micro-mantri-tier-count]');
    if (mantriTierCount) {
      event.preventDefault();
      openMicroMantriTierPeople(mantriTierCount);
    } else if (pdwkCard) {
      openMicroPdwkPeople(pdwkCard);
    }
  });

  microPerformanceDashboard?.addEventListener('keydown', event => {
    const mantriTierCount = event.target.closest('[data-micro-mantri-tier-count]');
    if (!mantriTierCount || !['Enter', ' '].includes(event.key)) return;

    event.preventDefault();
    openMicroMantriTierPeople(mantriTierCount);
  });

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
  const branchSelector = document.getElementById('landing-branch-selector');
  if (branchSelector && !branchSelector.disabled) {
    branchSelector.addEventListener('change', function() {
      const globalLoader = document.getElementById('dashboard-global-loader');
      setDashboardLoaderCopy(
        'Mengubah lingkup ke ' + this.options[this.selectedIndex].text,
        'Seluruh angka landing page sedang disesuaikan dengan cabang terpilih.'
      );
      if (globalLoader) globalLoader.classList.add('active');
      const targetUrl = new URL(window.location.href);
      targetUrl.searchParams.set('cabang', this.value);
      targetUrl.searchParams.delete('_area6');
      window.location.href = targetUrl.toString();
    });
  }

  const dateSelector = document.getElementById('periode-selector');
  if (dateSelector) {
    dateSelector.addEventListener('change', function() {
      const globalLoader = document.getElementById('dashboard-global-loader');
      setDashboardLoaderCopy(
        'Mengambil data periode ' + this.options[this.selectedIndex].text,
        'Snapshot dan grafik sedang dihitung ulang untuk periode yang dipilih.'
      );
      if (globalLoader) globalLoader.classList.add('active');
      const targetUrl = new URL(window.location.href);
      targetUrl.searchParams.set('periode', this.value);
      targetUrl.searchParams.delete('_area6');
      window.location.href = targetUrl.toString();
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
    if (branchSelector?.value) area6Url.searchParams.set('cabang', branchSelector.value);

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
