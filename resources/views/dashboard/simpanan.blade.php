@extends('layouts.admin')
@section('title', 'A-SIX | Landing Page Simpanan')
@section('content')
@php
  $area6Portfolio = data_get($dashboard ?? [], 'area6_portfolio', []);
  $area6Cards = is_array(data_get($area6Portfolio, 'cards')) ? data_get($area6Portfolio, 'cards') : [];
  $area6DefaultScope = data_get($area6Portfolio, 'default_scope', 'area6');
  $area6ScopePayloads = is_array(data_get($area6Portfolio, 'scopes')) ? data_get($area6Portfolio, 'scopes') : [];
  if (empty($area6ScopePayloads)) {
    $area6ScopePayloads = [$area6DefaultScope => $area6Portfolio];
  }
  $periodLabel = data_get($area6Portfolio, 'period_label', 'Belum ada data');
  $trend = data_get($dashboard ?? [], 'trend', data_get($area6Portfolio, 'trend', []));
  $trendSvg = data_get($trend, 'svg', []);
  $trendDates = (array) data_get($trend, 'labels', ['31 Des 25', '08 Agt 26', '31 Agt 26', '08 Sep 26']);
  $monthlyTimeseries = data_get($dashboard ?? [], 'monthly_timeseries', data_get($area6Portfolio, 'monthly_timeseries', []));
  $monthlyTimeseriesByScope = data_get($dashboard ?? [], 'monthly_timeseries_by_scope', data_get($area6Portfolio, 'monthly_timeseries_by_scope', []));
  $digitalChannelStrategy = data_get($dashboard ?? [], 'digital_channel_strategy', data_get($area6Portfolio, 'digital_channel_strategy', []));
  $casaDebiturStrategy = data_get($dashboard ?? [], 'casa_debitur_strategy', data_get($area6Portfolio, 'casa_debitur_strategy', []));
  $dormantStrategy = data_get($dashboard ?? [], 'dormant_strategy', data_get($area6Portfolio, 'dormant_strategy', []));
  $payrollQualityStrategy = data_get($dashboard ?? [], 'payroll_quality_strategy', data_get($area6Portfolio, 'payroll_quality_strategy', []));
  $payrollSummary = (array) data_get($payrollQualityStrategy, 'summary', []);
  $payrollRows = (array) data_get($payrollQualityStrategy, 'rows', []);
  $payrollSheetUrl = data_get($payrollQualityStrategy, 'sheet_url', 'https://docs.google.com/spreadsheets/d/1xvNFVQpykLkIVqMuiHG_wCdsf3zJbbhGAxcWJ4-09_0/edit?usp=sharing');
  $perusahaanAnakStrategy = data_get($dashboard ?? [], 'perusahaan_anak_strategy', data_get($area6Portfolio, 'perusahaan_anak_strategy', []));
  $perusahaanAnakSummary = (array) data_get($perusahaanAnakStrategy, 'summary', []);
  $perusahaanAnakRows = (array) data_get($perusahaanAnakStrategy, 'rows', []);
  $perusahaanAnakSheetUrl = data_get($perusahaanAnakStrategy, 'sheet_url', 'https://docs.google.com/spreadsheets/d/1qhPev4QD6gUaVrdqcGJALmJdHu6S1CaEXKz0hKiBZmQ/edit?usp=sharing');
  $ecosystemStrategy = data_get($dashboard ?? [], 'ecosystem_value_chain_strategy', data_get($area6Portfolio, 'ecosystem_value_chain_strategy', []));
  $ecosystemSummary = (array) data_get($ecosystemStrategy, 'summary', []);
  $ecosystemRecords = (array) data_get($ecosystemStrategy, 'records', []);
  $ecosystemBranches = (array) data_get($ecosystemSummary, 'branches', []);
  $ecosystemPillars = (array) data_get($ecosystemSummary, 'ecosystems', []);
  $analyticsByScope = data_get($dashboard ?? [], 'analytics_by_scope', data_get($area6Portfolio, 'analytics_by_scope', []));

  // Pre-calculate SVG Trend per scope for client-side instant switching
  $trendScopesSvg = [];
  foreach ($area6ScopePayloads as $scKey => $scVal) {
    $trendScopesSvg[$scKey] = data_get($scVal, 'trend.svg', []);
  }

  $scopePresentation = [
    'area6' => ['badge' => 'Konsolidasi', 'icon' => 'fa-globe-asia', 'label' => 'Area 6'],
    'ritel' => ['badge' => 'Retail Banking', 'icon' => 'fa-store-alt', 'label' => 'Ritel'],
    'micro' => ['badge' => 'Micro Banking', 'icon' => 'fa-store', 'label' => 'Mikro'],
    'wholesale' => ['badge' => 'Wholesale Banking', 'icon' => 'fa-building', 'label' => 'Wholesale'],
  ];
@endphp

<!-- Chart.js local asset with CDN fallback -->
<script src="{{ asset('vendor/chartjs/chart.min.js') }}" onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/chart.js';"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,700&display=swap');

:root {
  /* Brand Nusantara & Cakrawala Palette */
  --c-blue: #0857c3;
  --c-blue-d: #053b82;
  --c-cakrawala: #307fe2;
  --c-cakrawala-l: #4f96ee;
  --c-cakrawala-subtle: #eff6fe;
  --c-cakrawala-border: #93c2fa;
  
  --c-teal: #0f766e;
  --c-teal-l: #0d9488;
  --c-teal-subtle: #f0fdfa;
  --c-teal-border: #99f6e4;
  
  --c-purple: #7c3aed;
  --c-purple-l: #8b5cf6;
  --c-purple-subtle: #f5f3ff;
  --c-purple-border: #ddd6fe;

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
  
  /* Surfaces & High-Contrast Typography */
  --c-surface: #ffffff;
  --c-surf: #f8fafc;
  --c-surf-elevated: #f1f5f9;
  --c-border: #e2e8f0;
  --c-border-strong: #cbd5e1;
  --c-text-main: #0f172a;
  --c-text-muted: #475569;
  --c-text-subtle: #64748b;
  
  /* Modern Executive Elevation Shadows */
  --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
  --shadow-sm: 0 2px 4px rgba(15, 23, 42, 0.03), 0 1px 2px rgba(15, 23, 42, 0.02);
  --shadow-md: 0 6px 18px -3px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
  --shadow-lg: 0 16px 32px -4px rgba(15, 23, 42, 0.08), 0 6px 16px -2px rgba(15, 23, 42, 0.04);
  --shadow-hover: 0 16px 32px -6px rgba(48, 127, 226, 0.2), 0 6px 12px -2px rgba(15, 23, 42, 0.04);
  
  /* Radii */
  --r-sm: 8px;
  --r-md: 12px;
  --r-lg: 16px;
  --r-xl: 20px;
  --r-2xl: 24px;
}

.db-shell {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  color: var(--c-text-main);
  background: #f1f5f9;
  min-height: 100vh;
  padding: 0.85rem 1.25rem 2.5rem;
  -webkit-font-smoothing: antialiased;
}

/* ── EXECUTIVE HERO BANNER: VIBRANT BIRU NUSANTARA & BIRU CAKRAWALA ── */
.simpanan-hero {
  position: relative;
  background: linear-gradient(135deg, #003b75 0%, #00529c 100%);
  border: 1px solid rgba(219, 229, 239, 0.92);
  border-radius: var(--r-xl);
  padding: 1.25rem 1.75rem;
  margin-bottom: 1.35rem;
  box-shadow: 0 4px 20px rgba(0, 70, 133, 0.08);
  overflow: hidden;
  color: #ffffff;
}

.simpanan-hero::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: #307fe2;
}

.simpanan-hero__ambient {
  position: absolute;
  top: -90px;
  right: 80px;
  width: 440px;
  height: 290px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0) 70%);
  pointer-events: none;
}

.simpanan-hero__content {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.75rem;
}

.simpanan-hero__left {
  flex: 1 1 auto;
  min-width: 0;
}

.simpanan-hero__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.68rem;
  font-weight: 850;
  letter-spacing: 0.08em;
  color: #e0f2fe;
  background: rgba(48, 127, 226, 0.28);
  border: 1px solid rgba(147, 194, 250, 0.45);
  padding: 0.25rem 0.8rem;
  border-radius: 9999px;
  margin-bottom: 0.5rem;
  text-transform: uppercase;
}

.simpanan-hero__pulse {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #67e8f9;
  box-shadow: 0 0 10px #38bdf8;
  animation: heroPulse 2s infinite;
}

@keyframes heroPulse {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(103, 232, 249, 0.8); }
  70% { transform: scale(1.05); box-shadow: 0 0 0 6px rgba(103, 232, 249, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(103, 232, 249, 0); }
}

.simpanan-hero__title {
  font-size: 1.45rem;
  font-weight: 900;
  letter-spacing: -0.025em;
  color: #ffffff;
  margin: 0 0 0.35rem;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
  line-height: 1.2;
}

.simpanan-hero__badge {
  font-size: 0.75rem;
  font-weight: 850;
  background: rgba(48, 127, 226, 0.35);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.35);
  padding: 0.22rem 0.8rem;
  border-radius: 9999px;
  letter-spacing: 0;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.simpanan-hero__subtitle {
  font-size: 0.82rem;
  color: #e2e8f0;
  max-width: 680px;
  line-height: 1.45;
  margin: 0 0 0.95rem;
  font-weight: 500;
}

.simpanan-hero__controls {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.hero-control-pill {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: rgba(255, 255, 255, 0.16);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: var(--r-md);
  padding: 0.4rem 0.85rem;
  color: #ffffff;
  font-size: 0.76rem;
  font-weight: 750;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  cursor: pointer;
  line-height: 1;
}

.hero-control-pill:hover {
  background: rgba(48, 127, 226, 0.3);
  border-color: #93c2fa;
}

.hero-control-pill i {
  color: #7dd3fc;
  font-size: 0.82rem;
}

.hero-control-label {
  color: #cbd5e1;
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
}

.hero-control-select {
  border: none;
  background: transparent;
  color: #ffffff;
  font-size: 0.78rem;
  font-weight: 800;
  outline: none;
  cursor: pointer;
  font-family: inherit;
  padding-right: 0.3rem;
}

.hero-control-select option {
  color: #0f172a;
  background: #ffffff;
}

.hero-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  background: rgba(16, 185, 129, 0.22);
  border: 1px solid rgba(16, 185, 129, 0.4);
  color: #6ee7b7;
  font-size: 0.74rem;
  font-weight: 800;
  padding: 0.42rem 0.85rem;
  border-radius: var(--r-md);
  line-height: 1;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.hero-status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #34d399;
  box-shadow: 0 0 6px #34d399;
}

/* Modern Vector Illustration in Biru Nusantara & Cakrawala */
.simpanan-hero__visual {
  flex: 0 0 210px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.simpanan-hero__svg {
  width: 210px;
  height: 110px;
  filter: drop-shadow(0 8px 18px rgba(0, 0, 0, 0.35));
}

/* ── AREA 6 PORTFOLIO PANEL ── */
.area6-panel {
  margin: 0 0 1.5rem;
  background: #ffffff;
  border: 1.5px solid var(--c-border);
  box-shadow: var(--shadow-md);
  border-radius: var(--r-xl);
  overflow: hidden;
}

.area6-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.95rem 1.45rem;
  background: #ffffff;
  border-bottom: 1.5px solid #e2e8f0;
}

.area6-head-left {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  flex-wrap: wrap;
}

.area6-title {
  font-size: 1.12rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -0.02em;
  margin: 0;
}

.area6-periods {
  display: inline-flex;
  align-items: center;
}

.area6-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.28rem 0.8rem;
  background: #eff6ff;
  border: 1px solid var(--c-cakrawala-border);
  color: #0857c3;
  font-size: 0.72rem;
  font-weight: 800;
  white-space: nowrap;
  border-radius: 9999px;
}

.area6-pill i {
  color: var(--c-cakrawala);
  font-size: 0.75rem;
}

.area6-scope-toggle {
  display: inline-flex;
  gap: 0.35rem;
  padding: 0.25rem;
  background: #f1f5f9;
  border: 1.5px solid #cbd5e1;
  border-radius: 12px;
}

.area6-scope-btn {
  border: 0;
  min-height: 34px;
  padding: 0.35rem 0.95rem;
  background: transparent;
  color: #475569;
  font-size: 0.75rem;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  border-radius: 8px;
  letter-spacing: 0.01em;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.area6-scope-btn:hover {
  color: #004685;
  background: #f1f5f9;
}

.area6-scope-btn.active {
  background: #004685;
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(0, 70, 133, 0.25);
  font-weight: 800;
}

/* ── PROGNOSA WEEK TOOLBAR ── */
.ap-week-toolbar {
  padding: 0.7rem 1.45rem;
  background: #f8fafc;
  border-bottom: 1.5px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.ap-week-toolbar__copy {
  font-size: 0.76rem;
  font-weight: 850;
  color: #334155;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.ap-week-toolbar__copy i {
  color: var(--c-cakrawala);
}

.ap-week-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  padding: 0.22rem;
  border-radius: 10px;
}

.ap-week-btn {
  border: 1px solid transparent;
  background: transparent;
  border-radius: 7px;
  padding: 0.28rem 0.8rem;
  font-weight: 800;
  font-size: 0.74rem;
  color: #475569;
  cursor: pointer;
  transition: all 0.18s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  line-height: 1.2;
}

.ap-week-btn small {
  font-size: 0.65rem;
  font-weight: 650;
  color: #64748b;
}

.ap-week-btn:hover:not(:disabled) {
  color: #004685;
  background: #f1f5f9;
}

.ap-week-btn.active {
  background: #004685;
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(0, 70, 133, 0.2);
}

.ap-week-btn.active small {
  color: #e2e8f0;
}

.ap-week-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* ── THREE HIGH-CONTRAST REFERENCE CARDS: STRICTLY 1 ROW ── */
.area6-card-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
  padding: 1.25rem;
  align-items: stretch;
}

/* Force 3 cards to remain in ONE single row across all desktop & laptop viewports */
.area6-card-grid--three {
  display: grid !important;
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  gap: 1rem;
  padding: 1.2rem;
  align-items: stretch;
}

@media (max-width: 640px) {
  .area6-card-grid--three {
    grid-template-columns: 1fr !important;
    gap: 1rem;
    padding: 0.85rem;
  }
}

.area6-card-premium {
  border: 1.5px solid var(--c-border);
  width: 100%;
  padding: 0;
  text-align: left;
  background: #ffffff;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.22s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
  border-radius: var(--r-xl);
  box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
  min-width: 0;
}

.area6-card-premium:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-hover);
  border-color: #94a3b8;
}

.area6-card-premium[data-metric="tabungan"]:hover { border-color: var(--c-cakrawala-border); }
.area6-card-premium[data-metric="deposito"]:hover { border-color: #5eead4; }
.area6-card-premium[data-metric="giro"]:hover { border-color: #c4b5fd; }

/* Integrated Header Bar */
.ap-header {
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.1rem;
  border-top-left-radius: calc(var(--r-xl) - 1.5px);
  border-top-right-radius: calc(var(--r-xl) - 1.5px);
  position: relative;
}

.ap-header-left {
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
  min-width: 0;
}

.ap-badge {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 0.9rem;
  background: rgba(255, 255, 255, 0.25);
  border: 1px solid rgba(255, 255, 255, 0.38);
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.12);
  flex-shrink: 0;
}

.ap-header-title {
  color: #ffffff;
  font-size: 0.88rem;
  font-weight: 900;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin: 0;
  line-height: 1.1;
  white-space: nowrap;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.ap-header-tag {
  font-size: 0.62rem;
  font-weight: 850;
  color: #ffffff;
  background: rgba(0, 0, 0, 0.22);
  padding: 2px 8px;
  border-radius: 6px;
  letter-spacing: 0.05em;
  flex-shrink: 0;
}

/* Header & Badge Themes: Tabungan (Biru BRI), Deposito (Teal), Giro (Purple) */
.ap-header.bg-tabungan, .ap-badge.bg-tabungan { background: linear-gradient(135deg, #003b75 0%, #00529c 100%) !important; }
.ap-header.bg-deposito, .ap-badge.bg-deposito { background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%) !important; }
.ap-header.bg-giro, .ap-badge.bg-giro { background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%) !important; }

/* Card Body */
.ap-body {
  padding: 1.1rem 1.05rem 0.95rem;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}

/* 2-Column Metrics Grid */
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
  padding: 0.25rem 0.15rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  min-width: 0;
}

.ap-metric-label {
  font-size: 0.65rem;
  font-weight: 750;
  color: #475569;
  margin-bottom: 0.2rem;
  text-align: center;
  line-height: 1.2;
  letter-spacing: 0.01em;
  min-height: 2.2em;
  display: flex;
  align-items: center;
  justify-content: center;
  word-break: normal;
}

.ap-metric-val {
  font-size: clamp(1.05rem, 1.25vw, 1.35rem);
  font-weight: 900;
  color: #0f172a;
  line-height: 1.12;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.ap-metric-sub {
  font-size: 0.58rem;
  font-weight: 700;
  color: #94a3b8;
  margin-top: 0.15rem;
  line-height: 1;
}

.ap-metric-pct-val,
.ap-metric-gap-val {
  font-size: clamp(1.05rem, 1.25vw, 1.35rem);
  font-weight: 900;
  line-height: 1.12;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
  white-space: nowrap;
}

/* Status Colors */
.text-green-flat { color: #15803d !important; }
.text-amber-flat { color: #d97706 !important; }
.text-red-flat { color: #dc2626 !important; }
.text-muted-flat { color: #64748b !important; }

/* Weekly Prognosa Strip */
.ap-prognosa-strip {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0;
  margin-top: 0.75rem;
  padding: 0.55rem 0.4rem;
  border-radius: 11px;
  border: 1.5px solid #bfdbfe;
  border-left: 4px solid var(--c-cakrawala);
  background: linear-gradient(135deg, #f8fbff 0%, #eff6fe 100%);
  box-shadow: 0 2px 6px rgba(48, 127, 226, 0.06);
}

.area6-card-premium[data-metric="tabungan"] .ap-prognosa-strip {
  border-color: var(--c-cakrawala-border);
  border-left-color: var(--c-cakrawala);
  background: linear-gradient(135deg, #f8fbff 0%, #eff6fe 100%);
}

.area6-card-premium[data-metric="deposito"] .ap-prognosa-strip {
  border-color: #99f6e4;
  border-left-color: #0f766e;
  background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);
}

.area6-card-premium[data-metric="giro"] .ap-prognosa-strip {
  border-color: #ddd6fe;
  border-left-color: #7c3aed;
  background: linear-gradient(135deg, #faf5ff 0%, #ede9fe 100%);
}

.ap-prognosa-item {
  min-width: 0;
  padding: 0 0.35rem;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.ap-prognosa-item + .ap-prognosa-item {
  border-left: 1px solid rgba(0, 0, 0, 0.08);
}

.ap-prognosa-label {
  color: #475569;
  font-size: 0.62rem;
  font-weight: 800;
  letter-spacing: 0.01em;
  text-transform: uppercase;
  line-height: 1.2;
  text-align: center;
  min-height: 2em;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ap-prognosa-value {
  margin-top: 0.15rem;
  color: #0f172a;
  font-size: clamp(0.9rem, 1.1vw, 1.1rem);
  font-weight: 900;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
  line-height: 1.1;
  text-align: center;
  white-space: nowrap;
}

.ap-prognosa-unit {
  margin-top: 0.12rem;
  color: #64748b;
  font-size: 0.58rem;
  font-weight: 700;
  line-height: 1.2;
  text-align: center;
}

/* Dashed Divider */
.ap-dashed-divider {
  border: 0;
  border-top: 1px dashed #cbd5e1;
  margin: 0.75rem 0 0.65rem;
}

/* Row 4 - Deltas */
.ap-deltas {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.35rem;
  margin-top: auto;
}

.ap-delta-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.4rem 0.15rem;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  text-align: center;
  min-width: 0;
}

.ap-delta-item:hover {
  background: #ffffff;
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05);
  border-color: #cbd5e1;
}

.ap-delta-label {
  font-size: 0.6rem;
  font-weight: 850;
  color: #475569;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  margin-bottom: 0.18rem;
  line-height: 1;
}

.ap-delta-val {
  font-size: clamp(0.65rem, 0.8vw, 0.78rem);
  font-weight: 900;
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.ap-delta-arrow {
  font-size: 0.68rem;
  margin-top: 0.12rem;
  line-height: 1;
}

/* ── EXECUTIVE ANALYTICS SEKAT STACK ── */
.simpanan-sekat-stack {
  display: flex;
  flex-direction: column;
  gap: 1.35rem;
  margin-top: 1.5rem;
}

/* Sekat Shared Header with Biru Cakrawala Accent */
.asc-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.95rem 1.45rem;
  background: #ffffff;
  border-bottom: 1.5px solid #e2e8f0;
}

.asc-header-left {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.asc-header-icon {
  width: 38px;
  height: 38px;
  border-radius: 11px;
  background: #004685;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
  box-shadow: 0 2px 6px rgba(0, 70, 133, 0.2);
  flex-shrink: 0;
}

.asc-header-title {
  font-size: 1.05rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -0.01em;
  margin: 0;
}

/* ── SEKAT 1: TREND POSISI CARD ── */
.trend-position-card {
  background: #ffffff;
  border: 1.5px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-md);
  overflow: hidden;
}

.tpc-body {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.25rem;
  padding: 1.35rem;
}

@media (max-width: 992px) {
  .tpc-body {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
}

.trend-col {
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 14px;
  padding: 1.1rem;
  display: flex;
  flex-direction: column;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.trend-col:hover {
  border-color: var(--c-cakrawala-border);
  background: #ffffff;
  box-shadow: var(--shadow-sm);
}

.trend-col-title {
  font-size: 0.85rem;
  font-weight: 900;
  text-align: center;
  margin-bottom: 0.85rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.text-tab-blue { color: var(--c-cakrawala); }
.text-dep-teal { color: #0f766e; }
.text-giro-purple { color: #7c3aed; }

.trend-chart-wrapper {
  width: 100%;
  height: 110px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.trend-chart-wrapper svg {
  width: 100%;
  height: 100%;
  overflow: visible;
}

.trend-dates-row {
  display: flex;
  justify-content: space-between;
  margin-top: 0.85rem;
  padding: 0 0.4rem;
}

.trend-date-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.trend-date-label .date-part {
  font-size: 0.68rem;
  font-weight: 750;
  color: #334155;
  line-height: 1.2;
}

.trend-date-label .year-part {
  font-size: 0.62rem;
  font-weight: 650;
  color: #64748b;
  line-height: 1;
  margin-top: 1px;
}

/* ── SEKAT 2: TIMESERIES SIMPANAN PER BULAN ── */
.simpanan-sekat-card--monthly {
  background: #ffffff;
  border: 1.5px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-md);
  overflow: hidden;
}

.ssc-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.95rem 1.45rem;
  background: #ffffff;
  border-bottom: 1.5px solid #e2e8f0;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.ssc-head-left {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.ssc-icon {
  width: 38px;
  height: 38px;
  border-radius: 11px;
  background: #004685;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
  box-shadow: 0 2px 6px rgba(0, 70, 133, 0.2);
  flex-shrink: 0;
}

.ssc-title {
  font-size: 1.05rem;
  font-weight: 900;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.01em;
}

.scc-monthly-legend {
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
  flex-wrap: wrap;
}

.scc-leg-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.72rem;
  font-weight: 750;
  color: #475569;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  padding: 0.3rem 0.75rem;
  border-radius: 9999px;
  box-shadow: var(--shadow-xs);
}

.scc-leg-dot {
  width: 9px;
  height: 9px;
  border-radius: 3px;
  display: inline-block;
}

.scc-monthly-canvas-wrap {
  height: 300px;
  width: 100%;
  position: relative;
  padding: 1.25rem 1.45rem 1.45rem;
}

/* ── SEKAT 3: 1. OPTIMALISASI DIGITAL CHANNEL (CLEAN EXECUTIVE UI) ── */
.simpanan-digital-card {
  background: #ffffff;
  border: 1.5px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.digital-channel-triggers {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.dc-trigger-btn {
  padding: 0.4rem 0.9rem;
  border-radius: 8px;
  font-size: 0.74rem;
  font-weight: 700;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  cursor: pointer;
  transition: all 0.15s ease;
  letter-spacing: 0.02em;
}

.dc-trigger-btn:hover {
  border-color: #004685;
  color: #004685;
  background: #f8fafc;
}

.dc-trigger-btn.active {
  background: #004685;
  color: #ffffff;
  border-color: #004685;
  box-shadow: 0 2px 6px rgba(0, 70, 133, 0.22);
}

.dc-active-channel-badge {
  display: inline-flex;
  align-items: center;
  background: #eff6ff;
  color: #004685;
  border: 1px solid #bfdbfe;
  font-weight: 700;
  font-size: 0.72rem;
  padding: 0.2rem 0.65rem;
  border-radius: 6px;
  letter-spacing: 0.01em;
}

/* Executive Dynamic Table Directly Under Header */
.dc-table-container {
  padding: 1.15rem 1.35rem 1.35rem;
}

.dc-table-wrapper {
  border-radius: 10px;
  border: 1px solid #cbd5e1;
  overflow: hidden;
  box-shadow: var(--shadow-xs);
}

.dc-table {
  width: 100%;
  font-size: 0.78rem;
  border-collapse: collapse;
}

.dc-table th {
  background: #004685;
  color: #ffffff;
  font-weight: 700;
  padding: 0.65rem 0.85rem;
  border: 1px solid rgba(255, 255, 255, 0.15);
  font-size: 0.68rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  white-space: nowrap;
}

.dc-table th.col-highlight {
  background: #003666;
  color: #ffffff;
  font-weight: 800;
  border-left: 1.5px solid rgba(255, 255, 255, 0.25);
  border-right: 1.5px solid rgba(255, 255, 255, 0.25);
}

.dc-table td {
  padding: 0.65rem 0.85rem;
  border: 1px solid #e2e8f0;
  vertical-align: middle;
  white-space: nowrap;
}

.dc-table td.col-highlight {
  background: #f0f7ff;
  font-weight: 800;
  color: #004685;
  border-left: 1.5px solid #bfdbfe;
  border-right: 1.5px solid #bfdbfe;
}

.dc-table tbody tr:nth-child(even) {
  background: #f8fafc;
}

.dc-table tbody tr:hover {
  background: #f1f5f9;
}

.dc-table tfoot tr.total-row {
  background: #f8fafc;
  font-weight: 800;
  border-top: 2px solid #004685;
}

.dc-table tfoot tr.total-row td {
  border-top: 2px solid #004685;
  color: #0f172a;
  background: #f8fafc;
}

.dc-table tfoot tr.total-row td.col-highlight {
  background: #eaf2fc;
  color: #004685;
  font-weight: 800;
}

.dc-delta-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.2rem 0.55rem;
  border-radius: 5px;
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1.2;
}

.dc-delta-pill--green {
  background: #ecfdf5;
  color: #059669;
  border: 1px solid #a7f3d0;
}

.dc-delta-pill--red {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.dc-delta-pill--neutral {
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

/* ── SEKAT 4: 2. REKENING TRANSAKSI DEBITUR & REKENING DORMANT (EPIC DUAL-PANEL) ── */
.simpanan-debitur-dormant-section {
  background: #ffffff;
  border: 1.5px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.sdd-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.25rem;
  padding: 1.15rem 1.35rem 1.35rem;
}

@media (min-width: 1100px) {
  .sdd-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.sdd-panel {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  padding: 1.1rem;
  display: flex;
  flex-direction: column;
  gap: 0.95rem;
  box-shadow: var(--shadow-xs);
}

.sdd-panel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding-bottom: 0.65rem;
  border-bottom: 1.5px solid #f1f5f9;
}

.sdd-panel__title {
  font-size: 0.86rem;
  font-weight: 850;
  color: #0f172a;
  letter-spacing: 0.02em;
  display: flex;
  align-items: center;
}

.sdd-toggle {
  display: inline-flex;
  align-items: center;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  padding: 2px;
  border-radius: 7px;
}

.sdd-toggle-btn {
  border: none;
  background: transparent;
  padding: 0.22rem 0.65rem;
  font-size: 0.72rem;
  font-weight: 750;
  color: #475569;
  border-radius: 5px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.sdd-toggle-btn.active {
  background: #004685;
  color: #ffffff;
  box-shadow: 0 1px 3px rgba(0, 70, 133, 0.2);
}

.dormant-count-badge {
  display: inline-flex;
  align-items: center;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
  font-size: 0.72rem;
  font-weight: 800;
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
}

/* 3 Stat Boxes */
.sdd-stat-strip {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.65rem;
}

.sdd-stat-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  padding: 0.6rem 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.sdd-stat-box.highlight {
  background: #eff6ff;
  border-color: #bfdbfe;
}

.sdd-stat-label {
  font-size: 0.62rem;
  font-weight: 800;
  color: #64748b;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.sdd-stat-val {
  font-size: 0.98rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -0.02em;
  line-height: 1.15;
}

.sdd-stat-val.text-navy {
  color: #004685;
}

/* Mini Bar inside Table */
.sdd-table-wrap {
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  overflow: hidden;
}

.casa-ratio-cell {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.55rem;
}

.casa-ratio-mini-bar {
  width: 44px;
  height: 6px;
  background: #e2e8f0;
  border-radius: 3px;
  overflow: hidden;
  flex-shrink: 0;
}

.casa-ratio-mini-fill {
  height: 100%;
  background: #004685;
  border-radius: 3px;
}

/* --- Sekat 5: 3. Peningkatan Payroll Berkualitas (Unified Executive Container) --- */
.simpanan-payroll-card {
  background: #ffffff;
  border: 1.5px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.payroll-card-body {
  padding: 1.15rem 1.35rem 1.35rem;
}

/* --- Sekat 6: 4. Ecosystem & Value Chain (Unified Executive Container) --- */
.simpanan-ecosystem-card {
  background: #ffffff;
  border: 1.5px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.ecosystem-card-body {
  padding: 1.15rem 1.35rem 1.35rem;
}

/* --- Sekat 7: 5. Perusahaan Anak (Unified Executive Container) --- */
.simpanan-perusahaan-anak-card {
  background: #ffffff;
  border: 1.5px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.perusahaan-anak-card-body {
  padding: 1.15rem 1.35rem 1.35rem;
}

.eco-pillar-strip {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 0.65rem;
  margin-bottom: 1.15rem;
}

@media (max-width: 1200px) {
  .eco-pillar-strip {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 600px) {
  .eco-pillar-strip {
    grid-template-columns: repeat(2, 1fr);
  }
}

.eco-pillar-chip {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.55rem 0.75rem;
  display: flex;
  flex-direction: column;
  transition: all 0.15s ease;
}

.eco-pillar-chip:hover {
  background: #ffffff;
  border-color: #93c5fd;
  box-shadow: 0 2px 6px rgba(0, 70, 133, 0.08);
}

.eco-pillar-chip__title {
  font-size: 0.66rem;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  margin-bottom: 0.25rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.eco-pillar-chip__val {
  font-size: 0.92rem;
  font-weight: 900;
  color: #004685;
  line-height: 1.2;
}

.eco-pillar-chip__sub {
  font-size: 0.65rem;
  font-weight: 600;
  color: #94a3b8;
  margin-top: 0.1rem;
}

.badge-eco-pill {
  font-size: 0.68rem;
  font-weight: 750;
  padding: 0.2rem 0.5rem;
  border-radius: 5px;
  border: 1px solid transparent;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.badge-eco-pendidikan { background: #ecfeff; color: #0891b2; border-color: #a5f3fc; }
.badge-eco-rs { background: #fff1f2; color: #e11d48; border-color: #fecdd3; }
.badge-eco-masjid { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
.badge-eco-kristen { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.badge-eco-katolik { background: #faf5ff; color: #9333ea; border-color: #e9d5ff; }
.badge-eco-ponpes { background: #fffbeb; color: #d97706; border-color: #fde68a; }

.payroll-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.payroll-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.75rem;
  margin-bottom: 1.15rem;
}

@media (max-width: 900px) {
  .payroll-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 540px) {
  .payroll-kpi-grid {
    grid-template-columns: 1fr;
  }
}

.payroll-kpi-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.75rem 1rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.payroll-kpi-box::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background: #004685;
}

.payroll-kpi-box--accent::before {
  background: #16a34a;
}

.payroll-kpi-label {
  font-size: 0.64rem;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 0.2rem;
}

.payroll-kpi-value {
  font-size: 1.25rem;
  font-weight: 900;
  color: #004685;
  letter-spacing: -0.02em;
  line-height: 1.15;
}

.payroll-kpi-box--accent .payroll-kpi-value {
  color: #15803d;
}

.payroll-kpi-sub {
  font-size: 0.68rem;
  font-weight: 600;
  color: #64748b;
  margin-top: 0.15rem;
}

.payroll-branch-table-container {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  overflow: hidden;
  background: #ffffff;
}

.payroll-branch-table {
  width: 100%;
  margin-bottom: 0;
  border-collapse: separate;
  border-spacing: 0;
}

.payroll-branch-table thead th {
  background: #004685 !important;
  color: #ffffff !important;
  font-size: 0.74rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0.65rem 0.85rem;
  border-bottom: 2px solid #003366;
  white-space: nowrap;
}

.payroll-branch-table tbody td {
  padding: 0.65rem 0.85rem;
  font-size: 0.8rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.payroll-branch-row {
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.payroll-branch-row:hover {
  background-color: #eff6ff !important;
}

.payroll-branch-table tfoot td {
  background: #eff6ff !important;
  font-size: 0.82rem;
  font-weight: 800;
  border-top: 2px solid #cbd5e1;
  padding: 0.68rem 0.85rem;
}

.btn-payroll-detail {
  font-size: 0.73rem;
  font-weight: 700;
  border-radius: 6px;
  padding: 0.26rem 0.7rem;
  border: 1px solid #93c5fd;
  color: #004685;
  background: #ffffff;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.btn-payroll-detail:hover {
  background: #004685;
  color: #ffffff;
  border-color: #004685;
}

/* Modal Styling */
.modal-payroll-nominatif .modal-content {
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #cbd5e1;
}

.modal-payroll-nominatif .modal-header {
  background: #004685;
  color: #ffffff;
  padding: 0.85rem 1.25rem;
  border-bottom: 1px solid #003366;
}

.modal-payroll-nominatif .modal-header .close {
  color: #ffffff;
  opacity: 0.85;
  text-shadow: none;
}

.modal-payroll-nominatif .modal-header .close:hover {
  opacity: 1;
}

.modal-tab-btn {
  font-size: 0.73rem;
  font-weight: 700;
  padding: 0.24rem 0.65rem;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
}

.modal-tab-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.modal-tab-btn.active {
  background: #004685;
  color: #ffffff;
  border-color: #004685;
}

.badge-segmen {
  display: inline-block;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.badge-kc-pill {
  display: inline-block;
  font-size: 0.66rem;
  font-weight: 700;
  padding: 0.18rem 0.5rem;
  border-radius: 5px;
  background: #eff6ff;
  color: #004685;
  border: 1px solid #bfdbfe;
  white-space: nowrap;
}

.badge-status-new {
  display: inline-block;
  font-size: 0.64rem;
  font-weight: 800;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde68a;
}

.badge-status-existing {
  display: inline-block;
  font-size: 0.64rem;
  font-weight: 700;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  background: #f8fafc;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.badge-visit {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.66rem;
  font-weight: 700;
  padding: 0.18rem 0.45rem;
  border-radius: 5px;
  white-space: nowrap;
}

.badge-visit--yes {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.badge-visit--no {
  background: #f1f5f9;
  color: #94a3b8;
  border: 1px solid #e2e8f0;
}

/* Final visual cohesion with the Pinjaman landing, while retaining Funding identity. */
.db-shell {
  container: simpanan-landing / inline-size;
  min-height: auto;
  padding: 0 0 2rem;
  background: transparent;
}

.simpanan-hero {
  isolation: isolate;
  padding: 1.35rem 1.65rem;
  border-color: rgba(147, 194, 250, 0.36);
  background: linear-gradient(118deg, rgba(0, 59, 117, 0.98) 0%, rgba(0, 82, 156, 0.97) 56%, rgba(48, 127, 226, 0.94) 100%);
  box-shadow: 0 14px 32px -20px rgba(0, 59, 117, 0.7), var(--shadow-sm);
}

.simpanan-hero::after {
  content: "";
  position: absolute;
  inset: 0;
  z-index: -1;
  opacity: 0.2;
  pointer-events: none;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.13) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.13) 1px, transparent 1px);
  background-size: 32px 32px;
  mask-image: linear-gradient(90deg, transparent 8%, #000 72%, transparent 100%);
}

.simpanan-hero__eyebrow,
.simpanan-hero__badge,
.hero-control-pill,
.hero-status-pill {
  box-shadow: none;
}

.simpanan-hero__title {
  text-wrap: balance;
}

.simpanan-hero__subtitle {
  max-width: 62rem;
  text-wrap: pretty;
}

.hero-control-pill,
.hero-status-pill {
  min-height: 42px;
}

.hero-control-pill {
  background: rgba(255, 255, 255, 0.13);
  backdrop-filter: none;
  -webkit-backdrop-filter: none;
}

.hero-control-select {
  width: 100%;
  min-width: 0;
  min-height: 36px;
}

.area6-panel,
.trend-position-card,
.simpanan-sekat-card--monthly,
.simpanan-digital-card,
.simpanan-debitur-dormant-section,
.simpanan-payroll-card,
.simpanan-ecosystem-card,
.simpanan-perusahaan-anak-card {
  border: 1px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-sm);
}

.area6-panel:hover,
.trend-position-card:hover,
.simpanan-sekat-card--monthly:hover,
.simpanan-digital-card:hover,
.simpanan-debitur-dormant-section:hover,
.simpanan-payroll-card:hover,
.simpanan-ecosystem-card:hover,
.simpanan-perusahaan-anak-card:hover {
  border-color: var(--c-border-strong);
  box-shadow: var(--shadow-md);
}

.area6-head,
.asc-header,
.ssc-head {
  min-height: 64px;
  padding: 0.85rem 1.2rem;
  background: linear-gradient(90deg, #ffffff 0%, #f8fbff 100%);
  border-bottom: 1px solid var(--c-border);
}

.area6-head {
  align-items: center;
}

.area6-head-left {
  min-width: 0;
}

.area6-title,
.asc-header-title,
.ssc-title {
  color: #10213a;
  line-height: 1.3;
  text-wrap: balance;
}

.asc-header-icon,
.ssc-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #004685 0%, #307fe2 100%);
  box-shadow: 0 5px 12px -6px rgba(0, 70, 133, 0.75);
}

.simpanan-sekat-stack {
  gap: 1.1rem;
  margin-top: 1.1rem;
}

.area6-scope-toggle {
  display: grid;
  grid-template-columns: repeat(4, minmax(92px, 1fr));
  width: auto;
  max-width: 100%;
  padding: 0.25rem;
  overflow: visible;
}

.area6-scope-btn {
  min-height: 40px;
  justify-content: center;
  padding: 0.45rem 0.72rem;
}

.area6-scope-btn.active {
  background: linear-gradient(135deg, #004685 0%, #0754bd 100%);
}

.area6-card-grid,
.area6-card-grid--three {
  gap: 0.9rem;
  padding: 1rem;
}

.area6-card-premium {
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-sm);
}

.area6-card-premium:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.ap-header {
  border-radius: calc(var(--r-lg) - 1px) calc(var(--r-lg) - 1px) 0 0;
}

.ap-metric-val,
.ap-metric-pct-val,
.ap-metric-gap-val,
.ap-delta-val,
.sdd-stat-val,
.payroll-kpi-value,
.eco-pillar-chip__val,
.dc-table td,
.payroll-branch-table td {
  font-variant-numeric: tabular-nums;
}

.dc-trigger-btn,
.sdd-toggle-btn,
.btn-payroll-detail,
.modal-tab-btn {
  min-height: 36px;
}

.ap-week-btn {
  min-height: 36px;
}

.dc-trigger-btn.active,
.sdd-toggle-btn.active,
.modal-tab-btn.active {
  background: linear-gradient(135deg, #004685 0%, #0754bd 100%);
}

.dc-table-wrapper,
.sdd-table-wrap,
.payroll-branch-table-container {
  max-width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-gutter: stable;
}

.dc-table,
.payroll-branch-table {
  min-width: 760px;
}

.modal-payroll-nominatif .modal-body {
  overflow: auto !important;
}

.modal-payroll-nominatif .modal-body table {
  min-width: 920px;
}

.db-shell :where(.hero-control-pill, .area6-scope-btn, .ap-week-btn, .dc-trigger-btn, .sdd-toggle-btn, .btn-payroll-detail, .btn-open-nominatif, .btn-open-ecosystem-nominatif, .btn-open-pa-nominatif):focus-visible,
.modal-payroll-nominatif :where(button, input, select):focus-visible {
  outline: 3px solid rgba(48, 127, 226, 0.38);
  outline-offset: 2px;
}

.simpanan-hero :where(.hero-control-pill, .hero-control-select):focus-visible {
  outline-color: rgba(255, 255, 255, 0.82);
}

@container simpanan-landing (max-width: 1100px) {
  .area6-card-grid--three {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  }

  .area6-card-grid--three > .area6-card-premium:last-child {
    grid-column: 1 / -1;
    width: min(100%, calc(50% - 0.45rem));
    justify-self: center;
  }

  .tpc-body {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .tpc-body > .trend-col:last-child {
    grid-column: 1 / -1;
  }

  .simpanan-hero__visual {
    flex-basis: 180px;
  }

  .simpanan-hero__svg {
    width: 180px;
  }
}

@container simpanan-landing (max-width: 780px) {
  .simpanan-hero__content,
  .area6-head {
    flex-direction: column;
    align-items: stretch;
  }

  .simpanan-hero__visual {
    display: none;
  }

  .area6-scope-toggle {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
  }

  .ap-week-toolbar,
  .asc-header,
  .ssc-head,
  .sdd-panel__head,
  .payroll-toolbar {
    align-items: stretch;
  }

  .digital-channel-triggers {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    width: 100%;
  }

  .dc-trigger-btn {
    min-width: 0;
    padding-inline: 0.45rem;
    overflow-wrap: anywhere;
  }

  .tpc-body {
    grid-template-columns: 1fr;
  }

  .tpc-body > .trend-col:last-child {
    grid-column: auto;
  }

  .scc-monthly-canvas-wrap {
    height: 260px;
    padding: 1rem 0.85rem 1.15rem;
  }

  .dc-table-container,
  .sdd-grid,
  .payroll-card-body,
  .ecosystem-card-body,
  .perusahaan-anak-card-body {
    padding: 0.85rem;
  }
}

@container simpanan-landing (max-width: 620px) {
  .simpanan-hero {
    padding: 1.1rem;
    border-radius: var(--r-lg);
  }

  .simpanan-hero__controls {
    display: grid;
    grid-template-columns: 1fr;
  }

  .hero-control-pill,
  .hero-status-pill {
    width: 100%;
  }

  .hero-control-pill {
    display: grid;
    grid-template-columns: 18px minmax(0, 1fr);
    grid-template-rows: auto auto;
    align-items: center;
    column-gap: 0.45rem;
    min-height: 58px;
    overflow: hidden;
  }

  .hero-control-pill > i {
    grid-column: 1;
    grid-row: 1 / 3;
  }

  .hero-control-label,
  .hero-control-select {
    grid-column: 2;
  }

  .hero-control-label {
    align-self: end;
    line-height: 1.2;
  }

  .hero-control-select {
    flex: 1 1 auto;
    align-self: start;
    max-width: 100%;
    min-height: 36px;
  }

  .area6-card-grid--three {
    grid-template-columns: 1fr !important;
    padding: 0.75rem;
  }

  .area6-card-grid--three > .area6-card-premium:last-child {
    grid-column: auto;
    width: 100%;
  }

  .area6-head,
  .asc-header,
  .ssc-head {
    padding: 0.8rem;
  }

  .asc-header-left,
  .ssc-head-left {
    align-items: flex-start;
  }

  .asc-header-title,
  .ssc-title,
  .area6-title {
    font-size: 0.94rem;
  }

  .ap-week-toggle,
  .digital-channel-triggers {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
  }

  .ap-week-btn {
    min-width: 0;
    justify-content: center;
  }

  .ap-deltas,
  .sdd-stat-strip,
  .payroll-kpi-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .scc-monthly-legend {
    gap: 0.35rem;
  }

  .scc-leg-chip {
    padding: 0.28rem 0.5rem;
    white-space: normal;
  }
}

@container simpanan-landing (max-width: 360px) {
  .simpanan-hero__eyebrow {
    width: 100%;
    border-radius: 12px;
    line-height: 1.4;
  }
}

@media (max-width: 767.98px) {
  .db-shell {
    padding-bottom: 1.25rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .db-shell *,
  .db-shell *::before,
  .db-shell *::after {
    scroll-behavior: auto !important;
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
</style>

<div class="db-shell">
  {{-- EXECUTIVE HERO COMMAND BANNER (VIBRANT BIRU NUSANTARA & BIRU CAKRAWALA) --}}
  <div class="simpanan-hero">
    <div class="simpanan-hero__ambient" aria-hidden="true"></div>
    <div class="simpanan-hero__content">
      <div class="simpanan-hero__left">
        <div class="simpanan-hero__eyebrow">
          <span class="simpanan-hero__pulse"></span>
          <span>FUNDING &amp; TREASURY INTELLIGENCE &middot; DPK AREA 6</span>
        </div>
        <h1 class="simpanan-hero__title">
          Landing Page Simpanan
          <span class="simpanan-hero__badge">{{ $landingBranchLabel ?? 'Area 6' }}</span>
        </h1>
        <p class="simpanan-hero__subtitle">
          Monitoring terpadu keragaan portofolio Tabungan, Deposito, dan Giro serta evaluasi Prognosa Mingguan dengan arsitektur data terintegrasi.
        </p>

        {{-- CONTROLS BAR --}}
        <div class="simpanan-hero__controls">
          @if(!empty($landingBranchOptions))
          <label class="hero-control-pill" title="{{ !empty($landingBranchLocked) ? 'Cabang dikunci sesuai wilayah user' : 'Ubah lingkup cabang' }}">
            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
            <span class="hero-control-label">Cabang:</span>
            <select id="landing-branch-selector" class="hero-control-select" {{ !empty($landingBranchLocked) ? 'disabled' : '' }}>
              @foreach($landingBranchOptions as $branchKey => $branchLabel)
                <option value="{{ $branchKey }}" {{ $branchKey === ($selectedLandingBranch ?? 'area6') ? 'selected' : '' }}>{{ $branchLabel }}</option>
              @endforeach
            </select>
          </label>
          @endif

          @if(!empty($periods) && count($periods) > 0)
          <label class="hero-control-pill" title="Pilih tanggal posisi">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
            <span class="hero-control-label">Posisi:</span>
            <select class="hero-control-select" id="periode-selector">
              @foreach($periods as $p)
                <option value="{{ $p }}" {{ $p === $selectedPeriod ? 'selected' : '' }}>
                  {{ \Carbon\Carbon::parse($p)->translatedFormat('d M Y') }}
                </option>
              @endforeach
            </select>
          </label>
          @endif

          <div class="hero-status-pill">
            <span class="hero-status-dot"></span>
            <span>Live Snapshot Terkini</span>
          </div>
        </div>
      </div>

      {{-- HERO RIGHT: HIGH-CONTRAST VECTOR ARTWORK IN BIRU NUSANTARA & CAKRAWALA --}}
      <div class="simpanan-hero__visual" aria-hidden="true">
        <svg viewBox="0 0 240 120" fill="none" xmlns="http://www.w3.org/2000/svg" class="simpanan-hero__svg">
          <defs>
            <linearGradient id="vaultGradBlue" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#307fe2" />
              <stop offset="100%" stop-color="#052453" />
            </linearGradient>
            <linearGradient id="doorGradBlue" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#38bdf8" />
              <stop offset="100%" stop-color="#307fe2" />
            </linearGradient>
            <linearGradient id="goldGradBlue" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#fde047" />
              <stop offset="100%" stop-color="#ca8a04" />
            </linearGradient>
            <filter id="glowBlue" x="-20%" y="-20%" width="140%" height="140%">
              <feGaussianBlur stdDeviation="5" result="blur" />
              <feComposite in="SourceGraphic" in2="blur" operator="over" />
            </filter>
          </defs>

          <!-- Ambient Platform Glow -->
          <ellipse cx="140" cy="105" rx="80" ry="12" fill="#307fe2" opacity="0.35" filter="url(#glowBlue)" />
          <ellipse cx="140" cy="102" rx="72" ry="10" fill="#0c2b5e" stroke="#38bdf8" stroke-width="1.2" stroke-opacity="0.7" />

          <!-- Dynamic Financial Trend Line -->
          <path d="M 20 85 Q 50 70, 85 55 T 140 35 T 200 22 T 230 15" fill="none" stroke="url(#doorGradBlue)" stroke-width="2.5" stroke-linecap="round" />
          <path d="M 20 85 Q 50 70, 85 55 T 140 35 T 200 22 T 230 15 L 230 102 L 20 102 Z" fill="url(#doorGradBlue)" opacity="0.14" />

          <!-- Modern Executive Vault Isometric -->
          <g>
            <rect x="90" y="38" width="95" height="66" rx="12" fill="url(#vaultGradBlue)" stroke="#67e8f9" stroke-width="1.5" stroke-opacity="0.8" />
            <rect x="96" y="44" width="83" height="54" rx="8" fill="#051630" />

            <!-- Vault Door -->
            <circle cx="138" cy="71" r="22" fill="url(#doorGradBlue)" stroke="#bae6fd" stroke-width="2" />
            <circle cx="138" cy="71" r="16" fill="#0c2340" stroke="#38bdf8" stroke-width="1" />
            <line x1="138" y1="55" x2="138" y2="87" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" />
            <line x1="122" y1="71" x2="154" y2="71" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" />
            <circle cx="138" cy="71" r="5" fill="url(#goldGradBlue)" stroke="#ffffff" stroke-width="1" />

            <!-- Status LEDs -->
            <circle cx="106" cy="52" r="2.5" fill="#22c55e" filter="url(#glowBlue)" />
            <circle cx="114" cy="52" r="2.5" fill="#67e8f9" />
          </g>

          <!-- Golden Coins Stack -->
          <g transform="translate(-10, 5)">
            <ellipse cx="70" cy="98" rx="14" ry="4.5" fill="#a16207" />
            <rect x="56" y="90" width="28" height="8" fill="url(#goldGradBlue)" />
            <ellipse cx="70" cy="90" rx="14" ry="4.5" fill="#fef08a" stroke="#ca8a04" stroke-width="0.8" />
            <rect x="56" y="82" width="28" height="8" fill="url(#goldGradBlue)" />
            <ellipse cx="70" cy="82" rx="14" ry="4.5" fill="#fef08a" stroke="#ca8a04" stroke-width="0.8" />
            <text x="70" y="85" font-size="6.5" font-weight="900" fill="#854d0e" text-anchor="middle" font-family="Arial">Rp</text>
          </g>

          <!-- Floating Badges -->
          <g transform="translate(160, 10)">
            <rect width="66" height="22" rx="6" fill="#082348" stroke="#38bdf8" stroke-width="1.2" />
            <circle cx="11" cy="11" r="6" fill="url(#doorGradBlue)" />
            <text x="22" y="10" font-size="6" font-weight="800" fill="#7dd3fc" font-family="'Inter', sans-serif">TABUNGAN</text>
            <text x="22" y="17" font-size="6.5" font-weight="900" fill="#ffffff" font-family="'Inter', sans-serif">REALTIME</text>
          </g>

          <circle cx="45" cy="40" r="2" fill="#38bdf8" filter="url(#glowBlue)" />
          <circle cx="195" cy="48" r="2" fill="#34d399" filter="url(#glowBlue)" />
        </svg>
      </div>
    </div>
  </div>

  {{-- AREA 6 PORTFOLIO PANEL --}}
  <section class="area6-panel">
    <div class="area6-head">
      <div class="area6-head-left">
        <h2 class="area6-title" id="simpanan-scope-title">Portofolio Simpanan {{ $landingBranchLabel ?? 'Area 6' }}</h2>
        <div class="area6-periods">
          <span class="area6-pill"><i class="fas fa-calendar-day"></i> Posisi: {{ $periodLabel }}</span>
        </div>
      </div>
      <div class="area6-scope-toggle" role="group" aria-label="Pilihan lingkup kinerja simpanan">
        @foreach($area6ScopePayloads as $scopeKey => $scopePayload)
          <button type="button"
                  class="area6-scope-btn {{ $scopeKey === $area6DefaultScope ? 'active' : '' }}"
                  data-area6-scope="{{ $scopeKey }}"
                  aria-pressed="{{ $scopeKey === $area6DefaultScope ? 'true' : 'false' }}"
                  data-scope-title="Portofolio Simpanan {{ data_get($scopePayload, 'label', strtoupper($scopeKey)) }}"
                  data-scope-subtitle="{{ data_get($scopePayload, 'description', '') }}">
            <span><i class="fas {{ $scopeKey === 'area6' ? 'fa-globe-asia' : ($scopeKey === 'ritel' ? 'fa-store-alt' : ($scopeKey === 'micro' ? 'fa-store' : 'fa-building')) }}"></i></span>
            <span>{{ data_get($scopePayload, 'label', strtoupper($scopeKey)) }}</span>
          </button>
        @endforeach
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
          <i class="fas fa-calendar-check"></i>
          <span>Evaluasi Prognosa Mingguan:</span>
        </div>
        <div class="ap-week-toggle" role="tablist" aria-label="Pilih pekan prognosa {{ strtoupper($contentScopeKey) }}">
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
          @php
            $key = data_get($card, 'key');
            $pctColor = data_get($card, 'pct_color', 'green');
            $gapColor = data_get($card, 'gap_color', 'green');
            $deltas = data_get($card, 'deltas', []);
          @endphp
          <div class="area6-card-premium" data-metric="{{ $key }}">
            {{-- Integrated Header Bar --}}
            <div class="ap-header bg-{{ $key }}">
              <div class="ap-header-left">
                <div class="ap-badge bg-{{ $key }}">
                  <i class="{{ data_get($card, 'icon') }}"></i>
                </div>
                <h3 class="ap-header-title">{{ data_get($card, 'header_title') }}</h3>
              </div>
              <span class="ap-header-tag">AREA 6</span>
            </div>

            <div class="ap-body">
              {{-- Row 1: Realization vs Target --}}
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

              {{-- Row 2: Achievement % vs Gap --}}
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

              {{-- Row 3: Weekly Prognosa Strip W1 - W5 --}}
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

              <hr class="ap-dashed-divider">

              {{-- Row 4: Deltas Panel (DtD, MtD, MtM, YtD) --}}
              <div class="ap-deltas">
                @foreach(['dtd' => 'DtD', 'mtd' => 'MtD', 'mom' => 'MtM', 'ytd' => 'YtD'] as $dKey => $dLabel)
                  @php
                    $delta = data_get($deltas, $dKey, []);
                    $deltaVal = trim((string) data_get($delta, 'value', '-'));
                    $cleanNum = preg_replace('/[^\d]/', '', $deltaVal);
                    $isZero = ($cleanNum === '0' || $deltaVal === '-' || $deltaVal === '');

                    $isNegative = !$isZero && (
                        str_starts_with($deltaVal, '(')
                        || str_starts_with($deltaVal, '-')
                        || (is_numeric(data_get($delta, 'raw')) && (float) data_get($delta, 'raw') < 0)
                    );

                    $isPositive = !$isZero && (
                        str_starts_with($deltaVal, '+')
                        || (is_numeric(data_get($delta, 'raw')) && (float) data_get($delta, 'raw') > 0)
                    );

                    if ($isNegative) {
                      $deltaColor = 'red';
                      $deltaArrow = 'down';
                    } elseif ($isPositive) {
                      $deltaColor = 'green';
                      $deltaArrow = 'up';
                    } else {
                      $deltaColor = 'muted';
                      $deltaArrow = 'minus';
                    }
                  @endphp
                  <div class="ap-delta-item">
                    <div class="ap-delta-label">{{ $dLabel }}</div>
                    <div class="ap-delta-val text-{{ $deltaColor }}-flat">{{ $deltaVal }}</div>
                    <div class="ap-delta-arrow text-{{ $deltaColor }}-flat">
                      <i class="fas {{ $deltaArrow === 'minus' ? 'fa-minus' : 'fa-arrow-' . $deltaArrow }}"></i>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        @empty
          <div class="text-center p-4">Data simpanan belum tersedia untuk lingkup ini.</div>
        @endforelse
      </div>
    </div>
    @endforeach
  </section>

  {{-- EXECUTIVE ANALYTICS SECTION: SEKAT TREN POSISI, SEKAT TIMESERIES BULANAN, & OPTIMALISASI DIGITAL CHANNEL --}}
  <section class="simpanan-sekat-stack" aria-label="Analisis Tren, Timeseries, dan Kanal Digital Simpanan">
    {{-- SEKAT 1: TREND POSISI (3 KOLOM SEJAJAR: TABUNGAN, DEPOSITO, GIRO) --}}
    <div class="trend-position-card" id="sekat-trend-posisi">
      <div class="asc-header">
        <div class="asc-header-left">
          <div class="asc-header-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <h3 class="asc-header-title">TREND POSISI (Rp Juta)</h3>
        </div>
      </div>
      <div class="tpc-body">
        {{-- Column 1: TABUNGAN (Aksen Biru Cakrawala) --}}
        <div class="trend-col">
          <div class="trend-col-title text-tab-blue">TABUNGAN</div>
          <div class="trend-chart-wrapper">
            <svg viewBox="0 0 110 50" id="simpanan-trend-svg-tabungan">
              @php
                $tabSvg = data_get($trendSvg, 'tabungan', []);
                $tabPath = data_get($tabSvg, 'path', '');
                $tabPoints = data_get($tabSvg, 'points', []);
              @endphp
              <path id="svg-path-tabungan" d="{{ $tabPath }}" fill="none" stroke="#307fe2" stroke-width="1.8" />
              @foreach($tabPoints as $idx => $pt)
                <text id="svg-text-tabungan-{{ $idx }}" x="{{ $pt['x'] }}" y="{{ $pt['y'] - 6 }}" text-anchor="middle" font-size="3.8" font-weight="bold" fill="#0857c3" font-family="inherit">{{ $pt['val_fmt'] }}</text>
                <circle id="svg-circle-tabungan-{{ $idx }}" cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="2.8" fill="#307fe2" stroke="#ffffff" stroke-width="1" />
              @endforeach
            </svg>
          </div>
          <div class="trend-dates-row" id="trend-dates-tabungan">
            @foreach($trendDates as $idx => $d)
              @php
                $parts = explode(' ', trim($d));
                if (count($parts) >= 3) {
                    $dayMonth = $parts[0] . ' ' . $parts[1];
                    $year = "'" . ltrim($parts[2], "'");
                } elseif (count($parts) === 2) {
                    $dayMonth = $parts[0];
                    $year = "'" . ltrim($parts[1], "'");
                } else {
                    $dayMonth = $d;
                    $year = '';
                }
              @endphp
              <span class="trend-date-label" id="trend-date-tabungan-{{ $idx }}">
                <span class="date-part">{{ $dayMonth }}</span>
                @if($year)
                  <span class="year-part">{{ $year }}</span>
                @endif
              </span>
            @endforeach
          </div>
        </div>

        {{-- Column 2: DEPOSITO --}}
        <div class="trend-col">
          <div class="trend-col-title text-dep-teal">DEPOSITO</div>
          <div class="trend-chart-wrapper">
            <svg viewBox="0 0 110 50" id="simpanan-trend-svg-deposito">
              @php
                $depSvg = data_get($trendSvg, 'deposito', []);
                $depPath = data_get($depSvg, 'path', '');
                $depPoints = data_get($depSvg, 'points', []);
              @endphp
              <path id="svg-path-deposito" d="{{ $depPath }}" fill="none" stroke="#0f766e" stroke-width="1.8" />
              @foreach($depPoints as $idx => $pt)
                <text id="svg-text-deposito-{{ $idx }}" x="{{ $pt['x'] }}" y="{{ $pt['y'] - 6 }}" text-anchor="middle" font-size="3.8" font-weight="bold" fill="#0f766e" font-family="inherit">{{ $pt['val_fmt'] }}</text>
                <circle id="svg-circle-deposito-{{ $idx }}" cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="2.8" fill="#0f766e" stroke="#ffffff" stroke-width="1" />
              @endforeach
            </svg>
          </div>
          <div class="trend-dates-row" id="trend-dates-deposito">
            @foreach($trendDates as $idx => $d)
              @php
                $parts = explode(' ', trim($d));
                if (count($parts) >= 3) {
                    $dayMonth = $parts[0] . ' ' . $parts[1];
                    $year = "'" . ltrim($parts[2], "'");
                } elseif (count($parts) === 2) {
                    $dayMonth = $parts[0];
                    $year = "'" . ltrim($parts[1], "'");
                } else {
                    $dayMonth = $d;
                    $year = '';
                }
              @endphp
              <span class="trend-date-label" id="trend-date-deposito-{{ $idx }}">
                <span class="date-part">{{ $dayMonth }}</span>
                @if($year)
                  <span class="year-part">{{ $year }}</span>
                @endif
              </span>
            @endforeach
          </div>
        </div>

        {{-- Column 3: GIRO --}}
        <div class="trend-col">
          <div class="trend-col-title text-giro-purple">GIRO</div>
          <div class="trend-chart-wrapper">
            <svg viewBox="0 0 110 50" id="simpanan-trend-svg-giro">
              @php
                $giroSvg = data_get($trendSvg, 'giro', []);
                $giroPath = data_get($giroSvg, 'path', '');
                $giroPoints = data_get($giroSvg, 'points', []);
              @endphp
              <path id="svg-path-giro" d="{{ $giroPath }}" fill="none" stroke="#7c3aed" stroke-width="1.8" />
              @foreach($giroPoints as $idx => $pt)
                <text id="svg-text-giro-{{ $idx }}" x="{{ $pt['x'] }}" y="{{ $pt['y'] - 6 }}" text-anchor="middle" font-size="3.8" font-weight="bold" fill="#7c3aed" font-family="inherit">{{ $pt['val_fmt'] }}</text>
                <circle id="svg-circle-giro-{{ $idx }}" cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="2.8" fill="#7c3aed" stroke="#ffffff" stroke-width="1" />
              @endforeach
            </svg>
          </div>
          <div class="trend-dates-row" id="trend-dates-giro">
            @foreach($trendDates as $idx => $d)
              @php
                $parts = explode(' ', trim($d));
                if (count($parts) >= 3) {
                    $dayMonth = $parts[0] . ' ' . $parts[1];
                    $year = "'" . ltrim($parts[2], "'");
                } elseif (count($parts) === 2) {
                    $dayMonth = $parts[0];
                    $year = "'" . ltrim($parts[1], "'");
                } else {
                    $dayMonth = $d;
                    $year = '';
                }
              @endphp
              <span class="trend-date-label" id="trend-date-giro-{{ $idx }}">
                <span class="date-part">{{ $dayMonth }}</span>
                @if($year)
                  <span class="year-part">{{ $year }}</span>
                @endif
              </span>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    {{-- SEKAT 2: TIMESERIES SIMPANAN PER BULAN --}}
    <article class="simpanan-sekat-card simpanan-sekat-card--monthly">
      <header class="ssc-head">
        <div class="ssc-head-left">
          <div class="ssc-icon ssc-icon--monthly" aria-hidden="true"><i class="fas fa-calendar-alt"></i></div>
          <div class="ssc-title-wrap">
            <h3 class="ssc-title">Timeseries Simpanan per Bulan</h3>
          </div>
        </div>
        <div class="scc-monthly-legend">
          <span class="scc-leg-chip"><span class="scc-leg-dot" style="background:#94a3b8;"></span>H-1 Akhir Bulan</span>
          <span class="scc-leg-chip"><span class="scc-leg-dot" style="background:#307fe2;"></span>H Akhir Bulan (Biru Cakrawala)</span>
          <span class="scc-leg-chip"><span class="scc-leg-dot" style="background:#10b981;border-radius:50%;"></span>Delta Tutup</span>
        </div>
      </header>

      <div class="ssc-body">
        <div class="scc-monthly-canvas-wrap">
          <canvas id="simpananMonthlyChart"></canvas>
        </div>
      </div>
    </article>

    {{-- SEKAT 3: 1. OPTIMALISASI DIGITAL CHANNEL (RINGKAS, ELEGAN, BIRU CAKRAWALA) --}}
    <article class="simpanan-digital-card" id="sekat-digital-channel">
      <div class="asc-header" style="justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div class="asc-header-left">
          <div class="asc-header-icon">
            <i class="fas fa-network-wired"></i>
          </div>
          <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
            <h3 class="asc-header-title">1. OPTIMALISASI DIGITAL CHANNEL</h3>
            @php
              $initialChannelKey = 'edc';
              $initialChannel = $digitalChannelStrategy[$initialChannelKey] ?? (reset($digitalChannelStrategy) ?: []);
              $initDates = data_get($initialChannel, 'dates', []);
              $initBranches = data_get($initialChannel, 'branches', []);
              $initTotal = data_get($initialChannel, 'total', []);
            @endphp
            <span class="dc-active-channel-badge" id="dc-active-channel-badge">{{ data_get($initialChannel, 'metric_label', 'Merchant EDC Aktif') }}</span>
          </div>
        </div>

        {{-- 6 TRIGGER BUTTONS --}}
        <div class="digital-channel-triggers" id="digitalChannelTriggers">
          <button type="button" class="dc-trigger-btn active" data-channel="edc" aria-pressed="true">EDC</button>
          <button type="button" class="dc-trigger-btn" data-channel="qris" aria-pressed="false">QRIS</button>
          <button type="button" class="dc-trigger-btn" data-channel="casa_merchant" aria-pressed="false">CASA MERCHANT</button>
          <button type="button" class="dc-trigger-btn" data-channel="brimo" aria-pressed="false">BRIMO</button>
          <button type="button" class="dc-trigger-btn" data-channel="brilink" aria-pressed="false">BRILINK</button>
          <button type="button" class="dc-trigger-btn" data-channel="qlola" aria-pressed="false">QLOLA</button>
        </div>
      </div>

      {{-- DYNAMIC TABLE DENGAN SENTUHAN BIRU CAKRAWALA --}}
      <div class="dc-table-container">
        <div class="dc-table-wrapper table-responsive">
          <table class="table table-sm dc-table mb-0" id="dcDynamicTable">
            <thead>
              <tr>
                <th class="text-center" style="width: 50px;">NO</th>
                <th>UNIT KERJA / CABANG</th>
                <th class="text-right" id="th-ytd">{{ data_get($initDates, 'ytd', '-') }}</th>
                <th class="text-right" id="th-mtd">{{ data_get($initDates, 'mtd', '-') }}</th>
                <th class="text-right col-highlight" id="th-current">{{ data_get($initDates, 'current', '-') }}</th>
                <th class="text-right">DELTA MTD</th>
                <th class="text-right">DELTA YTD</th>
                <th class="text-right">TARGET RKA</th>
              </tr>
            </thead>
            <tbody id="dcTableBody">
              @forelse($initBranches as $b)
                <tr>
                  <td class="text-center text-muted font-weight-bold">{{ $b['no'] ?? '-' }}</td>
                  <td style="font-weight: 800; color: #0f172a;">
                    <i class="fas fa-building text-muted mr-1" style="font-size: 0.75rem;"></i>
                    {{ $b['branch'] ?? '-' }}
                  </td>
                  <td class="text-right font-weight-bold text-muted">{{ $b['ytd'] ?? '-' }}</td>
                  <td class="text-right font-weight-bold text-muted">{{ $b['mtd'] ?? '-' }}</td>
                  <td class="text-right col-highlight" style="font-size: 0.88rem;">{{ $b['current'] ?? '-' }}</td>
                  <td class="text-right">
                    @php
                      $dMtdRaw = (float) ($b['d_mtd_raw'] ?? 0);
                      $dMtdClass = $dMtdRaw > 0 ? 'green' : ($dMtdRaw < 0 ? 'red' : 'neutral');
                    @endphp
                    <span class="dc-delta-pill dc-delta-pill--{{ $dMtdClass }}">
                      @if($dMtdRaw > 0)<i class="fas fa-caret-up"></i>@elseif($dMtdRaw < 0)<i class="fas fa-caret-down"></i>@else<i class="fas fa-minus"></i>@endif
                      {{ $b['d_mtd'] ?? '-' }}
                    </span>
                  </td>
                  <td class="text-right">
                    @php
                      $dYtdRaw = (float) ($b['d_ytd_raw'] ?? 0);
                      $dYtdClass = $dYtdRaw > 0 ? 'green' : ($dYtdRaw < 0 ? 'red' : 'neutral');
                    @endphp
                    <span class="dc-delta-pill dc-delta-pill--{{ $dYtdClass }}">
                      @if($dYtdRaw > 0)<i class="fas fa-caret-up"></i>@elseif($dYtdRaw < 0)<i class="fas fa-caret-down"></i>@else<i class="fas fa-minus"></i>@endif
                      {{ $b['d_ytd'] ?? '-' }}
                    </span>
                  </td>
                  <td class="text-right text-muted font-weight-bold">{{ $b['rka'] ?? '-' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="text-center text-muted p-4">Data kanal digital belum tersedia.</td>
                </tr>
              @endforelse
            </tbody>
            <tfoot id="dcTableFoot">
              @if(!empty($initTotal))
                <tr class="total-row">
                  <td colspan="2" class="text-center font-weight-bold">
                    <i class="fas fa-globe-asia text-primary mr-1"></i>
                    TOTAL AREA 6 KONSOLIDASI
                  </td>
                  <td class="text-right font-weight-bold">{{ $initTotal['ytd'] ?? '-' }}</td>
                  <td class="text-right font-weight-bold">{{ $initTotal['mtd'] ?? '-' }}</td>
                  <td class="text-right col-highlight font-weight-bold" style="font-size: 0.92rem; color: #0857c3;">{{ $initTotal['current'] ?? '-' }}</td>
                  <td class="text-right">
                    @php
                      $totMtdRaw = (float) ($initTotal['d_mtd_raw'] ?? 0);
                      $totMtdClass = $totMtdRaw > 0 ? 'green' : ($totMtdRaw < 0 ? 'red' : 'neutral');
                    @endphp
                    <span class="dc-delta-pill dc-delta-pill--{{ $totMtdClass }}">
                      @if($totMtdRaw > 0)<i class="fas fa-caret-up"></i>@elseif($totMtdRaw < 0)<i class="fas fa-caret-down"></i>@else<i class="fas fa-minus"></i>@endif
                      {{ $initTotal['d_mtd'] ?? '-' }}
                    </span>
                  </td>
                  <td class="text-right">
                    @php
                      $totYtdRaw = (float) ($initTotal['d_ytd_raw'] ?? 0);
                      $totYtdClass = $totYtdRaw > 0 ? 'green' : ($totYtdRaw < 0 ? 'red' : 'neutral');
                    @endphp
                    <span class="dc-delta-pill dc-delta-pill--{{ $totYtdClass }}">
                      @if($totYtdRaw > 0)<i class="fas fa-caret-up"></i>@elseif($totYtdRaw < 0)<i class="fas fa-caret-down"></i>@else<i class="fas fa-minus"></i>@endif
                      {{ $initTotal['d_ytd'] ?? '-' }}
                    </span>
                  </td>
                  <td class="text-right text-muted font-weight-bold">{{ $initTotal['rka'] ?? '-' }}</td>
                </tr>
              @endif
            </tfoot>
          </table>
        </div>
      </div>
    </article>

    {{-- SEKAT 4: 2. REKENING TRANSAKSI DEBITUR & REKENING DORMANT (EPIC EXECUTIVE DUAL-PANEL) --}}
    <article class="simpanan-debitur-dormant-section" id="sekat-rekening-debitur-dormant">
      <div class="asc-header" style="justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div class="asc-header-left">
          <div class="asc-header-icon">
            <i class="fas fa-hand-holding-usd"></i>
          </div>
          <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
            <h3 class="asc-header-title">2. REKENING TRANSAKSI DEBITUR & REKENING DORMANT</h3>
            <span class="dc-active-channel-badge">CASA DEBITUR & AKTIVASI DORMANT</span>
          </div>
        </div>
        <div class="text-muted" style="font-size: 0.76rem; font-weight: 700;">
          <i class="far fa-calendar-alt mr-1"></i> Posisi: {{ data_get($casaDebiturStrategy, 'period_label', '-') }}
        </div>
      </div>

      <div class="sdd-grid">
        {{-- PANEL 1: RASIO CASA DEBITUR --}}
        <div class="sdd-panel">
          <div class="sdd-panel__head">
            <div class="sdd-panel__title">
              <i class="fas fa-percentage text-primary mr-1"></i> RASIO CASA DEBITUR
            </div>
            <div class="sdd-toggle" id="casaViewToggle">
              <button type="button" class="sdd-toggle-btn active" data-target="casa-table-cabang" aria-pressed="true">Cabang</button>
              <button type="button" class="sdd-toggle-btn" data-target="casa-table-segmen" aria-pressed="false">Segmen</button>
            </div>
          </div>

          {{-- 3 KPI Strip --}}
          <div class="sdd-stat-strip">
            <div class="sdd-stat-box">
              <span class="sdd-stat-label">TOTAL OS PINJAMAN</span>
              <span class="sdd-stat-val">{{ data_get($casaDebiturStrategy, 'total.os_fmt', '-') }}</span>
            </div>
            <div class="sdd-stat-box">
              <span class="sdd-stat-label">TOTAL SALDO CASA</span>
              <span class="sdd-stat-val text-primary">{{ data_get($casaDebiturStrategy, 'total.casa_fmt', '-') }}</span>
            </div>
            <div class="sdd-stat-box highlight">
              <span class="sdd-stat-label">RASIO CASA KONSOL</span>
              <span class="sdd-stat-val text-navy">{{ data_get($casaDebiturStrategy, 'total.ratio_fmt', '-') }}</span>
            </div>
          </div>

          {{-- Table Cabang View --}}
          <div class="sdd-table-wrap table-responsive" id="casa-table-cabang">
            <table class="table table-sm dc-table mb-0">
              <thead>
                <tr>
                  <th class="text-center" style="width: 40px;">NO</th>
                  <th>UNIT KERJA / CABANG</th>
                  <th class="text-right">OUTSTANDING</th>
                  <th class="text-right">SALDO CASA</th>
                  <th class="text-right col-highlight" style="width: 140px;">RASIO CASA</th>
                </tr>
              </thead>
              <tbody>
                @forelse(data_get($casaDebiturStrategy, 'branches', []) as $cb)
                  @php
                    $ratioVal = (float) ($cb['ratio'] ?? 0);
                    $barWidth = min(100, round(($ratioVal / 20) * 100));
                  @endphp
                  <tr>
                    <td class="text-center text-muted font-weight-bold">{{ $cb['no'] ?? '-' }}</td>
                    <td style="font-weight: 800; color: #0f172a;">
                      <i class="fas fa-building text-muted mr-1" style="font-size: 0.72rem;"></i>
                      {{ $cb['branch'] ?? '-' }}
                    </td>
                    <td class="text-right font-weight-bold text-muted">{{ $cb['os_fmt'] ?? '-' }}</td>
                    <td class="text-right font-weight-bold text-primary">{{ $cb['casa_fmt'] ?? '-' }}</td>
                    <td class="text-right col-highlight">
                      <div class="casa-ratio-cell">
                        <span class="font-weight-bold">{{ $cb['ratio_fmt'] ?? '-' }}</span>
                        <div class="casa-ratio-mini-bar">
                          <div class="casa-ratio-mini-fill" style="width: {{ $barWidth }}%;"></div>
                        </div>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted p-3">Data rasio CASA debitur belum tersedia.</td></tr>
                @endforelse
              </tbody>
              <tfoot>
                <tr class="total-row">
                  <td colspan="2" class="text-center font-weight-bold">
                    <i class="fas fa-globe-asia text-primary mr-1"></i> TOTAL AREA 6 KONSOL
                  </td>
                  <td class="text-right font-weight-bold">{{ data_get($casaDebiturStrategy, 'total.os_fmt', '-') }}</td>
                  <td class="text-right font-weight-bold text-primary">{{ data_get($casaDebiturStrategy, 'total.casa_fmt', '-') }}</td>
                  <td class="text-right col-highlight font-weight-bold" style="font-size: 0.92rem; color: #004685;">
                    {{ data_get($casaDebiturStrategy, 'total.ratio_fmt', '-') }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          {{-- Table Segmen View (Default Hidden) --}}
          <div class="sdd-table-wrap table-responsive" id="casa-table-segmen" style="display: none;">
            <table class="table table-sm dc-table mb-0">
              <thead>
                <tr>
                  <th class="text-center" style="width: 40px;">NO</th>
                  <th>SEGMEN BISNIS</th>
                  <th class="text-right">OUTSTANDING</th>
                  <th class="text-right">SALDO CASA</th>
                  <th class="text-right col-highlight" style="width: 140px;">RASIO CASA</th>
                </tr>
              </thead>
              <tbody>
                @forelse(data_get($casaDebiturStrategy, 'segments', []) as $cs)
                  @php
                    $ratioVal = (float) ($cs['ratio'] ?? 0);
                    $barWidth = min(100, round(($ratioVal / 20) * 100));
                  @endphp
                  <tr>
                    <td class="text-center text-muted font-weight-bold">{{ $cs['no'] ?? '-' }}</td>
                    <td style="font-weight: 800; color: #0f172a;">
                      <i class="fas fa-layer-group text-muted mr-1" style="font-size: 0.72rem;"></i>
                      {{ $cs['segment'] ?? '-' }}
                    </td>
                    <td class="text-right font-weight-bold text-muted">{{ $cs['os_fmt'] ?? '-' }}</td>
                    <td class="text-right font-weight-bold text-primary">{{ $cs['casa_fmt'] ?? '-' }}</td>
                    <td class="text-right col-highlight">
                      <div class="casa-ratio-cell">
                        <span class="font-weight-bold">{{ $cs['ratio_fmt'] ?? '-' }}</span>
                        <div class="casa-ratio-mini-bar">
                          <div class="casa-ratio-mini-fill" style="width: {{ $barWidth }}%;"></div>
                        </div>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted p-3">Data segmen belum tersedia.</td></tr>
                @endforelse
              </tbody>
              <tfoot>
                <tr class="total-row">
                  <td colspan="2" class="text-center font-weight-bold">
                    <i class="fas fa-globe-asia text-primary mr-1"></i> TOTAL AREA 6 KONSOL
                  </td>
                  <td class="text-right font-weight-bold">{{ data_get($casaDebiturStrategy, 'total.os_fmt', '-') }}</td>
                  <td class="text-right font-weight-bold text-primary">{{ data_get($casaDebiturStrategy, 'total.casa_fmt', '-') }}</td>
                  <td class="text-right col-highlight font-weight-bold" style="font-size: 0.92rem; color: #004685;">
                    {{ data_get($casaDebiturStrategy, 'total.ratio_fmt', '-') }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        {{-- PANEL 2: RETENSI & AKTIVASI REKENING DORMANT --}}
        <div class="sdd-panel">
          <div class="sdd-panel__head">
            <div class="sdd-panel__title">
              <i class="fas fa-user-clock text-primary mr-1"></i> RETENSI & AKTIVASI REKENING DORMANT
            </div>
            <span class="dormant-count-badge">
              <i class="fas fa-shield-alt mr-1"></i> {{ data_get($dormantStrategy, 'total.current_fmt', '-') }} Rek
            </span>
          </div>

          {{-- 3 KPI Strip --}}
          <div class="sdd-stat-strip">
            <div class="sdd-stat-box">
              <span class="sdd-stat-label">POSISI TERKINI ({{ data_get($dormantStrategy, 'dates.current', '-') }})</span>
              <span class="sdd-stat-val text-navy">{{ data_get($dormantStrategy, 'total.current_fmt', '-') }}</span>
            </div>
            <div class="sdd-stat-box">
              <span class="sdd-stat-label">DELTA MTD (vs {{ data_get($dormantStrategy, 'dates.mtd', '-') }})</span>
              @php
                $totDMtd = (int) data_get($dormantStrategy, 'total.d_mtd', 0);
                $totDMtdClass = $totDMtd < 0 ? 'green' : ($totDMtd > 0 ? 'red' : 'neutral');
              @endphp
              <span class="dc-delta-pill dc-delta-pill--{{ $totDMtdClass }}" style="font-size: 0.85rem;">
                @if($totDMtd < 0)<i class="fas fa-caret-down"></i>@elseif($totDMtd > 0)<i class="fas fa-caret-up"></i>@else<i class="fas fa-minus"></i>@endif
                {{ data_get($dormantStrategy, 'total.d_mtd_fmt', '-') }}
              </span>
            </div>
            <div class="sdd-stat-box highlight">
              <span class="sdd-stat-label">DELTA YTD (vs {{ data_get($dormantStrategy, 'dates.ytd', '-') }})</span>
              @php
                $totDYtd = (int) data_get($dormantStrategy, 'total.d_ytd', 0);
                $totDYtdClass = $totDYtd < 0 ? 'green' : ($totDYtd > 0 ? 'red' : 'neutral');
              @endphp
              <span class="dc-delta-pill dc-delta-pill--{{ $totDYtdClass }}" style="font-size: 0.85rem;">
                @if($totDYtd < 0)<i class="fas fa-caret-down"></i>@elseif($totDYtd > 0)<i class="fas fa-caret-up"></i>@else<i class="fas fa-minus"></i>@endif
                {{ data_get($dormantStrategy, 'total.d_ytd_fmt', '-') }}
              </span>
            </div>
          </div>

          {{-- Table Dormant View --}}
          <div class="sdd-table-wrap table-responsive">
            <table class="table table-sm dc-table mb-0">
              <thead>
                <tr>
                  <th class="text-center" style="width: 40px;">NO</th>
                  <th>UNIT KERJA / CABANG</th>
                  <th class="text-right">{{ data_get($dormantStrategy, 'dates.ytd', '31 Des 25') }}</th>
                  <th class="text-right">{{ data_get($dormantStrategy, 'dates.mtd', '31 Agt 26') }}</th>
                  <th class="text-right col-highlight">{{ data_get($dormantStrategy, 'dates.current', '04 Sep 26') }}</th>
                  <th class="text-right">DELTA MTD</th>
                  <th class="text-right">DELTA YTD</th>
                </tr>
              </thead>
              <tbody>
                @forelse(data_get($dormantStrategy, 'branches', []) as $db)
                  <tr>
                    <td class="text-center text-muted font-weight-bold">{{ $db['no'] ?? '-' }}</td>
                    <td style="font-weight: 800; color: #0f172a;">
                      <i class="fas fa-building text-muted mr-1" style="font-size: 0.72rem;"></i>
                      {{ $db['branch'] ?? '-' }}
                    </td>
                    <td class="text-right font-weight-bold text-muted">{{ $db['ytd_fmt'] ?? '-' }}</td>
                    <td class="text-right font-weight-bold text-muted">{{ $db['mtd_fmt'] ?? '-' }}</td>
                    <td class="text-right col-highlight" style="font-size: 0.86rem;">{{ $db['current_fmt'] ?? '-' }}</td>
                    <td class="text-right">
                      @php
                        $dMtd = (int) ($db['d_mtd'] ?? 0);
                        $dMtdClass = $dMtd < 0 ? 'green' : ($dMtd > 0 ? 'red' : 'neutral');
                      @endphp
                      <span class="dc-delta-pill dc-delta-pill--{{ $dMtdClass }}">
                        @if($dMtd < 0)<i class="fas fa-caret-down"></i>@elseif($dMtd > 0)<i class="fas fa-caret-up"></i>@else<i class="fas fa-minus"></i>@endif
                        {{ $db['d_mtd_fmt'] ?? '-' }}
                      </span>
                    </td>
                    <td class="text-right">
                      @php
                        $dYtd = (int) ($db['d_ytd'] ?? 0);
                        $dYtdClass = $dYtd < 0 ? 'green' : ($dYtd > 0 ? 'red' : 'neutral');
                      @endphp
                      <span class="dc-delta-pill dc-delta-pill--{{ $dYtdClass }}">
                        @if($dYtd < 0)<i class="fas fa-caret-down"></i>@elseif($dYtd > 0)<i class="fas fa-caret-up"></i>@else<i class="fas fa-minus"></i>@endif
                        {{ $db['d_ytd_fmt'] ?? '-' }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="7" class="text-center text-muted p-3">Data rekening dormant belum tersedia.</td></tr>
                @endforelse
              </tbody>
              <tfoot>
                <tr class="total-row">
                  <td colspan="2" class="text-center font-weight-bold">
                    <i class="fas fa-globe-asia text-primary mr-1"></i> TOTAL AREA 6 KONSOL
                  </td>
                  <td class="text-right font-weight-bold">{{ data_get($dormantStrategy, 'total.ytd_fmt', '-') }}</td>
                  <td class="text-right font-weight-bold">{{ data_get($dormantStrategy, 'total.mtd_fmt', '-') }}</td>
                  <td class="text-right col-highlight font-weight-bold" style="font-size: 0.92rem; color: #004685;">
                    {{ data_get($dormantStrategy, 'total.current_fmt', '-') }}
                  </td>
                  <td class="text-right">
                    <span class="dc-delta-pill dc-delta-pill--{{ $totDMtdClass }}">
                      @if($totDMtd < 0)<i class="fas fa-caret-down"></i>@elseif($totDMtd > 0)<i class="fas fa-caret-up"></i>@else<i class="fas fa-minus"></i>@endif
                      {{ data_get($dormantStrategy, 'total.d_mtd_fmt', '-') }}
                    </span>
                  </td>
                  <td class="text-right">
                    <span class="dc-delta-pill dc-delta-pill--{{ $totDYtdClass }}">
                      @if($totDYtd < 0)<i class="fas fa-caret-down"></i>@elseif($totDYtd > 0)<i class="fas fa-caret-up"></i>@else<i class="fas fa-minus"></i>@endif
                      {{ data_get($dormantStrategy, 'total.d_ytd_fmt', '-') }}
                    </span>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </article>

    {{-- SEKAT 5: 3. PENINGKATAN PAYROLL BERKUALITAS (UNIFIED EXECUTIVE CONTAINER) --}}
    <article class="simpanan-payroll-card simpanan-payroll-section" id="sekat-peningkatan-payroll">
      <div class="asc-header" style="justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div class="asc-header-left">
          <div class="asc-header-icon">
            <i class="fas fa-id-card-alt"></i>
          </div>
          <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
            <h3 class="asc-header-title">3. PENINGKATAN PAYROLL BERKUALITAS</h3>
            <span class="dc-active-channel-badge">PIPELINE FUNDING & CASA</span>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge" style="background: #eff6ff; color: #004685; border: 1px solid #bfdbfe; font-size: 0.72rem; font-weight: 700; padding: 0.35rem 0.65rem; border-radius: 6px;">
            <i class="fas fa-sort-amount-down mr-1"></i> Urut Potensi Tertinggi
          </span>
        </div>
      </div>

      <div class="payroll-card-body">
      <!-- KPI Strip: 4 Metrik Utama Ringkas -->
      <div class="payroll-kpi-grid">
        <div class="payroll-kpi-box">
          <span class="payroll-kpi-label"><i class="fas fa-building mr-1"></i> Total Mitra Pipeline</span>
          <span class="payroll-kpi-value">{{ data_get($payrollSummary, 'total_perusahaan', 0) }} <span style="font-size: 0.8rem; font-weight: 600; color: #64748b;">Instansi</span></span>
          <span class="payroll-kpi-sub">Wilayah Madiun Raya</span>
        </div>
        <div class="payroll-kpi-box">
          <span class="payroll-kpi-label"><i class="fas fa-users mr-1"></i> Total Potensi Pegawai</span>
          <span class="payroll-kpi-value">{{ data_get($payrollSummary, 'total_potensi_fmt', '0') }} <span style="font-size: 0.8rem; font-weight: 600; color: #64748b;">Pegawai</span></span>
          <span class="payroll-kpi-sub">Total Pegawai: {{ data_get($payrollSummary, 'total_pegawai_fmt', '0') }} Org</span>
        </div>
        <div class="payroll-kpi-box payroll-kpi-box--accent">
          <span class="payroll-kpi-label" style="color: #166534;"><i class="fas fa-user-check mr-1"></i> Realisasi Akuisisi</span>
          <span class="payroll-kpi-value">{{ data_get($payrollSummary, 'total_realisasi_fmt', '0') }} <span style="font-size: 0.8rem; font-weight: 600; color: #166534;">Rekening</span></span>
          <span class="payroll-kpi-sub" style="color: #15803d;">Existing: {{ data_get($payrollSummary, 'total_existing_fmt', '0') }} Rek</span>
        </div>
        <div class="payroll-kpi-box">
          <span class="payroll-kpi-label"><i class="fas fa-clipboard-check mr-1"></i> Rasio Kunjungan</span>
          <span class="payroll-kpi-value">{{ data_get($payrollSummary, 'persen_kunjungan', 0) }}%</span>
          <span class="payroll-kpi-sub">{{ data_get($payrollSummary, 'total_kunjungan', 0) }} dari {{ data_get($payrollSummary, 'total_perusahaan', 0) }} Mitra Terkunjungi</span>
        </div>
      </div>

      <!-- Tabel Pipeline Berdasarkan Cabang (Area 6 Madiun) -->
      @php
        $payrollBranches = (array) data_get($payrollSummary, 'branches', []);
        $payrollBranchOrder = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
      @endphp
      <div class="payroll-branch-table-container">
        <table class="table payroll-branch-table" id="payrollBranchTable">
          <thead>
            <tr>
              <th style="width: 40px;" class="text-center">#</th>
              <th>Kantor Cabang (KC)</th>
              <th class="text-center">Jumlah Mitra</th>
              <th class="text-right">Total Pegawai</th>
              <th class="text-right" style="color: #93c5fd !important;">Potensi Payroll</th>
              <th class="text-right" style="color: #86efac !important;">Realisasi Akuisisi</th>
              <th class="text-center">Kunjungan</th>
              <th class="text-center" style="width: 175px;">Aksi</th>
            </tr>
          </thead>
          <tbody id="payrollBranchTableBody">
            @foreach($payrollBranchOrder as $idx => $bName)
              @php
                $bData = $payrollBranches[$bName] ?? [
                  'perusahaan' => 0, 'pegawai' => 0, 'pegawai_fmt' => '0',
                  'potensi' => 0, 'potensi_fmt' => '0',
                  'realisasi' => 0, 'realisasi_fmt' => '0',
                  'kunjungan' => 0, 'persen_kunjungan' => 0, 'kunjungan_fmt' => '0 / 0 (0%)'
                ];
              @endphp
              <tr class="payroll-branch-row" data-kc="{{ $bName }}">
                <td class="text-center font-weight-bold text-muted">{{ $idx + 1 }}</td>
                <td>
                  <span class="badge-kc-pill" style="font-size: 0.78rem; padding: 0.28rem 0.65rem;">
                    <i class="fas fa-building mr-1"></i> {{ $bName }}
                  </span>
                </td>
                <td class="text-center font-weight-bold">
                  <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:0.75rem; padding: 0.25rem 0.55rem; border-radius: 5px;">
                    {{ $bData['perusahaan'] ?? 0 }} Instansi
                  </span>
                </td>
                <td class="text-right font-weight-bold text-secondary">{{ $bData['pegawai_fmt'] ?? '0' }}</td>
                <td class="text-right font-weight-bold" style="color: #004685; font-size: 0.88rem;">{{ $bData['potensi_fmt'] ?? '0' }}</td>
                <td class="text-right font-weight-bold text-success" style="font-size: 0.88rem;">{{ $bData['realisasi_fmt'] ?? '0' }}</td>
                <td class="text-center font-weight-bold" style="font-size: 0.76rem;">
                  <span class="badge {{ ($bData['persen_kunjungan'] ?? 0) >= 80 ? 'badge-visit--yes' : 'badge-visit--no' }}" style="padding: 0.22rem 0.55rem;">
                    {{ $bData['kunjungan_fmt'] ?? '-' }}
                  </span>
                </td>
                <td class="text-center">
                  <button type="button" class="btn btn-payroll-detail btn-open-nominatif" data-kc="{{ $bName }}">
                    <i class="fas fa-list-ul mr-1"></i> Lihat Nominatif
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot id="payrollBranchFoot">
            <tr class="font-weight-bold">
              <td colspan="2" class="text-center" style="color: #004685; font-size: 0.82rem;">
                <i class="fas fa-calculator mr-1"></i> TOTAL AREA 6 KONSOL
              </td>
              <td class="text-center" style="color: #0369a1;">
                {{ data_get($payrollSummary, 'total_perusahaan', 0) }} Instansi
              </td>
              <td class="text-right text-secondary">{{ data_get($payrollSummary, 'total_pegawai_fmt', '0') }}</td>
              <td class="text-right" style="color: #004685; font-size: 0.9rem;">{{ data_get($payrollSummary, 'total_potensi_fmt', '0') }}</td>
              <td class="text-right text-success" style="font-size: 0.9rem;">{{ data_get($payrollSummary, 'total_realisasi_fmt', '0') }}</td>
              <td class="text-center" style="font-size: 0.78rem;">
                {{ data_get($payrollSummary, 'total_kunjungan', 0) }} / {{ data_get($payrollSummary, 'total_perusahaan', 0) }} ({{ data_get($payrollSummary, 'persen_kunjungan', 0) }}%)
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-payroll-detail btn-open-nominatif" data-kc="ALL" style="background:#004685; color:#ffffff; border-color:#004685;">
                  <i class="fas fa-table mr-1"></i> Semua Nominatif
                </button>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </article>

  {{-- SEKAT 6: 4. ECOSYSTEM & VALUE CHAIN (UNIFIED EXECUTIVE CONTAINER) --}}
  <article class="simpanan-ecosystem-card simpanan-sekat-card" id="sekat-ecosystem-value-chain">
    <div class="asc-header" style="justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
      <div class="asc-header-left">
        <div class="asc-header-icon">
          <i class="fas fa-sitemap"></i>
        </div>
        <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
          <h3 class="asc-header-title">4. ECOSYSTEM & VALUE CHAIN</h3>
          <span class="dc-active-channel-badge">CASA & DEPOSITO EKOSISTEM</span>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge" style="background: #eff6ff; color: #004685; border: 1px solid #bfdbfe; font-size: 0.72rem; font-weight: 700; padding: 0.35rem 0.65rem; border-radius: 6px;">
          <i class="fas fa-coins mr-1"></i> Posisi Saldo September 2026
        </span>
      </div>
    </div>

    <div class="ecosystem-card-body">
      <!-- KPI Strip: 4 Metrik Utama Ringkas -->
      <div class="payroll-kpi-grid">
        <div class="payroll-kpi-box">
          <span class="payroll-kpi-label"><i class="fas fa-wallet mr-1"></i> Total Rekening Kelolaan</span>
          <span class="payroll-kpi-value">{{ data_get($ecosystemSummary, 'total_accounts_fmt', '2.069') }} <span style="font-size: 0.8rem; font-weight: 600; color: #64748b;">Rekening</span></span>
          <span class="payroll-kpi-sub">Wilayah Madiun Raya (4 KC)</span>
        </div>
        <div class="payroll-kpi-box">
          <span class="payroll-kpi-label"><i class="fas fa-coins mr-1"></i> Total Saldo Terakhir</span>
          <span class="payroll-kpi-value">{{ data_get($ecosystemSummary, 'total_balance_fmt', 'Rp 84,30 M') }}</span>
          <span class="payroll-kpi-sub">CASA &amp; Deposito Ekosistem</span>
        </div>
        <div class="payroll-kpi-box payroll-kpi-box--accent">
          <span class="payroll-kpi-label" style="color: #166534;"><i class="fas fa-chart-pie mr-1"></i> Rata-Rata Saldo / Rek</span>
          <span class="payroll-kpi-value">{{ data_get($ecosystemSummary, 'avg_balance_fmt', 'Rp 40,74 Jt') }}</span>
          <span class="payroll-kpi-sub" style="color: #15803d;">Rasio Efektivitas Saldo</span>
        </div>
        <div class="payroll-kpi-box">
          <span class="payroll-kpi-label"><i class="fas fa-trophy mr-1"></i> Pilar Dominan</span>
          <span class="payroll-kpi-value" style="font-size: 1.15rem;">{{ data_get($ecosystemSummary, 'dominant_ecosystem', 'Pendidikan') }}</span>
          <span class="payroll-kpi-sub">{{ data_get($ecosystemSummary, 'dominant_ecosystem_saldo', 'Rp 58,64 M') }} ({{ data_get($ecosystemSummary, 'dominant_ecosystem_share', '69,6%') }})</span>
        </div>
      </div>

      <!-- 6 Pilar Ekosistem Mini-Strip -->
      <div class="eco-pillar-strip">
        @php
          $pillarIcons = [
            'Pendidikan' => ['icon' => 'fa-graduation-cap', 'color' => '#0891b2', 'badge' => 'badge-eco-pendidikan'],
            'Rumah Sakit' => ['icon' => 'fa-hospital', 'color' => '#e11d48', 'badge' => 'badge-eco-rs'],
            'Masjid' => ['icon' => 'fa-mosque', 'color' => '#16a34a', 'badge' => 'badge-eco-masjid'],
            'Kristen' => ['icon' => 'fa-church', 'color' => '#2563eb', 'badge' => 'badge-eco-kristen'],
            'Katolik' => ['icon' => 'fa-cross', 'color' => '#9333ea', 'badge' => 'badge-eco-katolik'],
            'Ponpes' => ['icon' => 'fa-kaaba', 'color' => '#d97706', 'badge' => 'badge-eco-ponpes'],
          ];
        @endphp
        @foreach($pillarIcons as $pKey => $pMeta)
          @php
            $pData = data_get($ecosystemPillars, $pKey, ['saldo_fmt' => 'Rp 0', 'count_fmt' => '0', 'label' => $pKey]);
          @endphp
          <div class="eco-pillar-chip">
            <div class="eco-pillar-chip__title">
              <span><i class="fas {{ $pMeta['icon'] }} mr-1" style="color: {{ $pMeta['color'] }};"></i> {{ $pKey }}</span>
              <span class="badge {{ $pMeta['badge'] }}">{{ data_get($pData, 'count_fmt', '0') }} Rek</span>
            </div>
            <div class="eco-pillar-chip__val" style="color: {{ $pMeta['color'] }};">{{ data_get($pData, 'saldo_fmt', 'Rp 0') }}</div>
            <div class="eco-pillar-chip__sub">{{ data_get($pData, 'label', $pKey) }}</div>
          </div>
        @endforeach
      </div>

      <!-- Tabel Konsolidasi Level Cabang (Area 6 Madiun) -->
      @php
        $ecoBranchOrder = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
      @endphp
      <div class="payroll-branch-table-container">
        <table class="table payroll-branch-table ecosystem-branch-table mb-0" id="ecosystemBranchTable">
          <thead>
            <tr>
              <th style="width: 40px;" class="text-center">#</th>
              <th>Kantor Cabang (KC)</th>
              <th class="text-center">Total Rekening</th>
              <th class="text-right" style="color: #93c5fd !important;">Total Saldo Kelolaan</th>
              <th class="text-right" style="color: #86efac !important;">Rata-Rata Saldo</th>
              <th>Pilar Dominan</th>
              <th class="text-center" style="width: 175px;">Aksi</th>
            </tr>
          </thead>
          <tbody id="ecosystemBranchTableBody">
            @foreach($ecoBranchOrder as $idx => $bName)
              @php
                $eb = $ecosystemBranches[$bName] ?? [
                  'rekening_fmt' => '0',
                  'saldo_fmt' => 'Rp 0',
                  'avg_saldo_fmt' => 'Rp 0',
                  'dominant_ecosystem' => '-',
                  'dominant_saldo_fmt' => 'Rp 0'
                ];
              @endphp
              <tr class="ecosystem-branch-row" data-kc="{{ $bName }}">
                <td class="text-center font-weight-bold text-muted">{{ $idx + 1 }}</td>
                <td>
                  <span class="badge-kc-pill" style="font-size: 0.78rem; padding: 0.28rem 0.65rem;">
                    <i class="fas fa-building mr-1"></i> {{ $bName }}
                  </span>
                </td>
                <td class="text-center font-weight-bold">
                  <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:0.75rem; padding: 0.25rem 0.55rem; border-radius: 5px;">
                    {{ $eb['rekening_fmt'] ?? '0' }} Rekening
                  </span>
                </td>
                <td class="text-right font-weight-bold" style="color: #004685; font-size: 0.88rem;">{{ $eb['saldo_fmt'] ?? 'Rp 0' }}</td>
                <td class="text-right font-weight-bold text-success" style="font-size: 0.88rem;">{{ $eb['avg_saldo_fmt'] ?? 'Rp 0' }}</td>
                <td>
                  <span class="font-weight-bold text-dark">{{ $eb['dominant_ecosystem'] ?? '-' }}</span>
                  <small class="text-muted ml-1">({{ $eb['dominant_saldo_fmt'] ?? 'Rp 0' }})</small>
                </td>
                <td class="text-center">
                  <button type="button" class="btn btn-payroll-detail btn-open-ecosystem-nominatif" data-kc="{{ $bName }}">
                    <i class="fas fa-list-ul mr-1"></i> Lihat Nominatif
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr class="total-row" style="background:#eff6ff !important; border-top: 2px solid #cbd5e1;">
              <td colspan="2" class="font-weight-bold text-dark" style="font-size: 0.82rem;">
                <i class="fas fa-globe-asia text-primary mr-1"></i> TOTAL AREA 6 KONSOL
              </td>
              <td class="text-center font-weight-bold text-primary" style="font-size: 0.84rem;">
                {{ data_get($ecosystemSummary, 'total_accounts_fmt', '2.069') }} Rekening
              </td>
              <td class="text-right font-weight-bold" style="color: #004685; font-size: 0.95rem;">
                {{ data_get($ecosystemSummary, 'total_balance_fmt', 'Rp 84,30 M') }}
              </td>
              <td class="text-right font-weight-bold text-success" style="font-size: 0.95rem;">
                {{ data_get($ecosystemSummary, 'avg_balance_fmt', 'Rp 40,74 Jt') }}
              </td>
              <td class="font-weight-bold text-dark">
                {{ data_get($ecosystemSummary, 'dominant_ecosystem', 'Pendidikan') }}
                <small class="text-primary font-weight-bold">({{ data_get($ecosystemSummary, 'dominant_ecosystem_saldo', 'Rp 58,64 M') }})</small>
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-payroll-detail btn-open-ecosystem-nominatif" data-kc="ALL" style="background:#004685; color:#ffffff; border-color:#004685;">
                  <i class="fas fa-table mr-1"></i> Semua Nominatif ({{ data_get($ecosystemSummary, 'total_accounts_fmt', '2.069') }})
                </button>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </article>

  {{-- SEKAT 7: 5. PERUSAHAAN ANAK (UNIFIED EXECUTIVE CONTAINER) --}}
  <article class="simpanan-perusahaan-anak-card simpanan-sekat-card" id="sekat-perusahaan-anak">
    <div class="asc-header" style="justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
      <div class="asc-header-left">
        <div class="asc-header-icon">
          <i class="fas fa-handshake"></i>
        </div>
        <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
          <h3 class="asc-header-title">5. PERUSAHAAN ANAK</h3>
          <span class="dc-active-channel-badge">SINERGI PERUSAHAAN ANAK</span>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge" style="background: #eff6ff; color: #004685; border: 1px solid #bfdbfe; font-size: 0.72rem; font-weight: 700; padding: 0.35rem 0.65rem; border-radius: 6px;">
          <i class="fas fa-coins mr-1"></i> Posisi Saldo September 2026
        </span>
      </div>
    </div>

    <div class="payroll-card-body perusahaan-anak-card-body">
      <!-- KPI Strip: 4 Metrik Utama Ringkas -->
      <div class="payroll-kpi-grid">
        <div class="payroll-kpi-box">
          <span class="payroll-kpi-label"><i class="fas fa-building mr-1"></i> Total Pipeline Mitra</span>
          <span class="payroll-kpi-value">{{ data_get($perusahaanAnakSummary, 'total_pipeline', 0) }} <span style="font-size: 0.8rem; font-weight: 600; color: #64748b;">Mitra</span></span>
          <span class="payroll-kpi-sub">Wilayah Madiun Raya (4 KC)</span>
        </div>
        <div class="payroll-kpi-box payroll-kpi-box--accent">
          <span class="payroll-kpi-label" style="color: #166534;"><i class="fas fa-check-circle mr-1"></i> Sudah Terakuisisi</span>
          <span class="payroll-kpi-value" style="color: #166534;">{{ data_get($perusahaanAnakSummary, 'total_sudah', 0) }} <span style="font-size: 0.8rem; font-weight: 600; color: #166534;">Mitra</span></span>
          <span class="payroll-kpi-sub" style="color: #15803d;">Rasio: {{ data_get($perusahaanAnakSummary, 'persen_akuisisi_fmt', '0%') }}</span>
        </div>
        <div class="payroll-kpi-box">
          <span class="payroll-kpi-label"><i class="fas fa-clock mr-1"></i> Belum Terakuisisi</span>
          <span class="payroll-kpi-value" style="color: #d97706;">{{ data_get($perusahaanAnakSummary, 'total_belum', 0) }} <span style="font-size: 0.8rem; font-weight: 600; color: #d97706;">Mitra</span></span>
          <span class="payroll-kpi-sub">Potensi Tindak Lanjut</span>
        </div>
        <div class="payroll-kpi-box">
          <span class="payroll-kpi-label"><i class="fas fa-coins mr-1"></i> Total Saldo CIF Posisi Terkini</span>
          <span class="payroll-kpi-value" style="color: #004685;">{{ data_get($perusahaanAnakSummary, 'total_saldo_september_fmt', 'Rp 0') }}</span>
          <span class="payroll-kpi-sub">Posisi 6 September 2026</span>
        </div>
      </div>

      <!-- Tabel Konsolidasi Cabang Perusahaan Anak (Area 6 Madiun) -->
      @php
        $paBranches = (array) data_get($perusahaanAnakSummary, 'branches', []);
        $paBranchOrder = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
      @endphp
      <div class="payroll-branch-table-container">
        <table class="table payroll-branch-table mb-0" id="perusahaanAnakBranchTable">
          <thead>
            <tr>
              <th style="width: 40px;" class="text-center">#</th>
              <th>Kantor Cabang (KC)</th>
              <th class="text-center">Total Pipeline</th>
              <th class="text-center" style="color: #86efac !important;">Terakuisisi</th>
              <th class="text-center" style="color: #fde68a !important;">Belum Terakuisisi</th>
              <th class="text-center">Rasio Akuisisi</th>
              <th class="text-right" style="color: #93c5fd !important;">Saldo Terkini</th>
              <th class="text-center" style="width: 175px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach($paBranchOrder as $idx => $bName)
              @php
                $pb = $paBranches[$bName] ?? [
                  'total_pipeline' => 0, 'total_sudah' => 0, 'total_belum' => 0,
                  'persen_akuisisi' => 0, 'persen_akuisisi_fmt' => '0%',
                  'saldo_september' => 0, 'saldo_september_fmt' => 'Rp 0'
                ];
              @endphp
              <tr class="payroll-branch-row pa-branch-row" data-kc="{{ $bName }}">
                <td class="text-center font-weight-bold text-muted">{{ $idx + 1 }}</td>
                <td>
                  <span class="badge-kc-pill" style="font-size: 0.78rem; padding: 0.28rem 0.65rem;">
                    <i class="fas fa-building mr-1"></i> {{ $bName }}
                  </span>
                </td>
                <td class="text-center font-weight-bold">
                  <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:0.75rem; padding: 0.25rem 0.55rem; border-radius: 5px;">
                    {{ $pb['total_pipeline'] ?? 0 }} Mitra
                  </span>
                </td>
                <td class="text-center font-weight-bold text-success">{{ $pb['total_sudah'] ?? 0 }}</td>
                <td class="text-center font-weight-bold text-warning">{{ $pb['total_belum'] ?? 0 }}</td>
                <td class="text-center font-weight-bold">
                  <span class="badge {{ ($pb['total_sudah'] ?? 0) > 0 ? 'badge-visit--yes' : 'badge-visit--no' }}" style="padding: 0.22rem 0.55rem;">
                    {{ $pb['persen_akuisisi_fmt'] ?? '0%' }}
                  </span>
                </td>
                <td class="text-right font-weight-bold" style="color: #004685; font-size: 0.88rem;">{{ $pb['saldo_september_fmt'] ?? 'Rp 0' }}</td>
                <td class="text-center">
                  <button type="button" class="btn btn-payroll-detail btn-open-pa-nominatif" data-kc="{{ $bName }}">
                    <i class="fas fa-list-ul mr-1"></i> Lihat Nominatif
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr class="font-weight-bold" style="background:#eff6ff !important; border-top: 2px solid #cbd5e1;">
              <td colspan="2" class="text-center" style="color: #004685; font-size: 0.82rem;">
                <i class="fas fa-globe-asia text-primary mr-1"></i> TOTAL AREA 6 KONSOL
              </td>
              <td class="text-center" style="color: #0369a1;">
                {{ data_get($perusahaanAnakSummary, 'total_pipeline', 0) }} Mitra
              </td>
              <td class="text-center text-success">{{ data_get($perusahaanAnakSummary, 'total_sudah', 0) }}</td>
              <td class="text-center text-warning">{{ data_get($perusahaanAnakSummary, 'total_belum', 0) }}</td>
              <td class="text-center">
                <span class="badge badge-visit--yes" style="padding: 0.22rem 0.55rem;">
                  {{ data_get($perusahaanAnakSummary, 'persen_akuisisi_fmt', '0%') }}
                </span>
              </td>
              <td class="text-right" style="color: #004685; font-size: 0.92rem;">
                {{ data_get($perusahaanAnakSummary, 'total_saldo_september_fmt', 'Rp 0') }}
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-payroll-detail btn-open-pa-nominatif" data-kc="ALL" style="background:#004685; color:#ffffff; border-color:#004685;">
                  <i class="fas fa-table mr-1"></i> Semua Nominatif ({{ data_get($perusahaanAnakSummary, 'total_pipeline', 0) }})
                </button>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </article>
</section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Store Data from PHP
  window.simpananDigitalChannelData = @json($digitalChannelStrategy ?? []);
  window.simpananTrendSvgByScope = @json($trendScopesSvg ?? []);
  window.simpananMonthlyDataByScope = @json($monthlyTimeseriesByScope ?? []);

  // 1. Prognosa Week Selection
  function selectLandingPrognosaWeek(weekLabel) {
    document.querySelectorAll('[data-prognosa-week-control]').forEach(control => {
      const selectedButton = control.querySelector(`[data-prognosa-week-select="${weekLabel}"]:not(:disabled)`);
      control.querySelectorAll('[data-prognosa-week-select]').forEach(button => {
        const active = button === selectedButton;
        button.classList.toggle('active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
    });

    document.querySelectorAll('[data-prognosa-week-panel]').forEach(panel => {
      panel.hidden = panel.getAttribute('data-prognosa-week-panel') !== weekLabel;
    });
  }
  window.selectLandingPrognosaWeek = selectLandingPrognosaWeek;

  document.querySelectorAll('[data-prognosa-week-select]').forEach(button => {
    button.addEventListener('click', () => {
      selectLandingPrognosaWeek(button.getAttribute('data-prognosa-week-select'));
    });
  });

  // 2. Scope Switcher & SVG Trend updater
  function updateSimpananTrendSvg(scopeKey) {
    const scopeSvg = window.simpananTrendSvgByScope ? window.simpananTrendSvgByScope[scopeKey] : null;
    if (!scopeSvg) return;

    ['tabungan', 'deposito', 'giro'].forEach(prod => {
      const prodData = scopeSvg[prod];
      if (!prodData) return;

      const pathEl = document.getElementById('svg-path-' + prod);
      if (pathEl && prodData.path) {
        pathEl.setAttribute('d', prodData.path);
      }

      if (Array.isArray(prodData.points)) {
        prodData.points.forEach((pt, idx) => {
          const textEl = document.getElementById('svg-text-' + prod + '-' + idx);
          const circleEl = document.getElementById('svg-circle-' + prod + '-' + idx);
          if (textEl) {
            textEl.setAttribute('x', pt.x);
            textEl.setAttribute('y', pt.y - 6);
            textEl.textContent = pt.val_fmt;
          }
          if (circleEl) {
            circleEl.setAttribute('cx', pt.x);
            circleEl.setAttribute('cy', pt.y);
          }
        });
      }
    });
  }

  const scopeButtons = document.querySelectorAll('[data-area6-scope]');
  const scopeContents = document.querySelectorAll('[data-area6-content-scope]');
  const scopeTitle = document.getElementById('simpanan-scope-title');

  scopeButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      const targetScope = this.getAttribute('data-area6-scope');
      scopeButtons.forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-pressed', 'false');
      });
      this.classList.add('active');
      this.setAttribute('aria-pressed', 'true');

      if (scopeTitle && this.getAttribute('data-scope-title')) {
        scopeTitle.textContent = this.getAttribute('data-scope-title');
      }

      scopeContents.forEach(c => {
        if (c.getAttribute('data-area6-content-scope') === targetScope) {
          c.classList.remove('d-none');
        } else {
          c.classList.add('d-none');
        }
      });

      // Update SVG sparklines for this scope
      updateSimpananTrendSvg(targetScope);

      // Update Monthly Chart
      if (window.simpananMonthlyChartInstance && window.simpananMonthlyDataByScope && window.simpananMonthlyDataByScope[targetScope]) {
        const mData = window.simpananMonthlyDataByScope[targetScope];
        window.simpananMonthlyChartInstance.data.labels = mData.labels || [];
        window.simpananMonthlyChartInstance.data.datasets[0].data = mData.h1_values || [];
        window.simpananMonthlyChartInstance.data.datasets[1].data = mData.h_values || [];
        window.simpananMonthlyChartInstance.data.datasets[2].data = mData.deltas || [];
        window.simpananMonthlyChartInstance.update();
      }
    });
  });

  // 3. Digital Channel Switcher
  function renderDigitalChannel(chKey) {
    const data = window.simpananDigitalChannelData ? window.simpananDigitalChannelData[chKey] : null;
    if (!data) return;

    // Update active channel badge
    const badgeEl = document.getElementById('dc-active-channel-badge');
    if (badgeEl) {
      badgeEl.textContent = data.metric_label || data.label || chKey.toUpperCase();
    }

    // Update table headers
    const thYtd = document.getElementById('th-ytd');
    const thMtd = document.getElementById('th-mtd');
    const thCur = document.getElementById('th-current');
    if (thYtd && data.dates) thYtd.textContent = data.dates.ytd || '-';
    if (thMtd && data.dates) thMtd.textContent = data.dates.mtd || '-';
    if (thCur && data.dates) thCur.textContent = data.dates.current || '-';

    // Update table rows
    const tbody = document.getElementById('dcTableBody');
    if (tbody) {
      if (!data.branches || data.branches.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted p-4">Data kanal digital belum tersedia.</td></tr>';
      } else {
        let rowsHtml = '';
        data.branches.forEach(b => {
          const dMtdRaw = parseFloat(b.d_mtd_raw || 0);
          const dMtdClass = dMtdRaw > 0 ? 'green' : (dMtdRaw < 0 ? 'red' : 'neutral');
          const dMtdIcon = dMtdRaw > 0 ? '<i class="fas fa-caret-up"></i> ' : (dMtdRaw < 0 ? '<i class="fas fa-caret-down"></i> ' : '<i class="fas fa-minus"></i> ');

          const dYtdRaw = parseFloat(b.d_ytd_raw || 0);
          const dYtdClass = dYtdRaw > 0 ? 'green' : (dYtdRaw < 0 ? 'red' : 'neutral');
          const dYtdIcon = dYtdRaw > 0 ? '<i class="fas fa-caret-up"></i> ' : (dYtdRaw < 0 ? '<i class="fas fa-caret-down"></i> ' : '<i class="fas fa-minus"></i> ');

          rowsHtml += `<tr>
            <td class="text-center text-muted font-weight-bold">${b.no || '-'}</td>
            <td style="font-weight: 800; color: #0f172a;">
              <i class="fas fa-building text-muted mr-1" style="font-size: 0.75rem;"></i>
              ${b.branch || '-'}
            </td>
            <td class="text-right font-weight-bold text-muted">${b.ytd || '-'}</td>
            <td class="text-right font-weight-bold text-muted">${b.mtd || '-'}</td>
            <td class="text-right col-highlight" style="font-size: 0.88rem;">${b.current || '-'}</td>
            <td class="text-right">
              <span class="dc-delta-pill dc-delta-pill--${dMtdClass}">
                ${dMtdIcon}${b.d_mtd || '-'}
              </span>
            </td>
            <td class="text-right">
              <span class="dc-delta-pill dc-delta-pill--${dYtdClass}">
                ${dYtdIcon}${b.d_ytd || '-'}
              </span>
            </td>
            <td class="text-right text-muted font-weight-bold">${b.rka || '-'}</td>
          </tr>`;
        });
        tbody.innerHTML = rowsHtml;
      }
    }

    // Update table foot
    const tfoot = document.getElementById('dcTableFoot');
    if (tfoot && data.total) {
      const tot = data.total;
      const tMtdRaw = parseFloat(tot.d_mtd_raw || 0);
      const tMtdClass = tMtdRaw > 0 ? 'green' : (tMtdRaw < 0 ? 'red' : 'neutral');
      const tMtdIcon = tMtdRaw > 0 ? '<i class="fas fa-caret-up"></i> ' : (tMtdRaw < 0 ? '<i class="fas fa-caret-down"></i> ' : '<i class="fas fa-minus"></i> ');

      const tYtdRaw = parseFloat(tot.d_ytd_raw || 0);
      const tYtdClass = tYtdRaw > 0 ? 'green' : (tYtdRaw < 0 ? 'red' : 'neutral');
      const tYtdIcon = tYtdRaw > 0 ? '<i class="fas fa-caret-up"></i> ' : (tYtdRaw < 0 ? '<i class="fas fa-caret-down"></i> ' : '<i class="fas fa-minus"></i> ');

      tfoot.innerHTML = `<tr class="total-row">
        <td colspan="2" class="text-center font-weight-bold">
          <i class="fas fa-globe-asia text-primary mr-1"></i>
          TOTAL AREA 6 KONSOLIDASI
        </td>
        <td class="text-right font-weight-bold">${tot.ytd || '-'}</td>
        <td class="text-right font-weight-bold">${tot.mtd || '-'}</td>
        <td class="text-right col-highlight font-weight-bold" style="font-size: 0.92rem; color: #0857c3;">${tot.current || '-'}</td>
        <td class="text-right">
          <span class="dc-delta-pill dc-delta-pill--${tMtdClass}">
            ${tMtdIcon}${tot.d_mtd || '-'}
          </span>
        </td>
        <td class="text-right">
          <span class="dc-delta-pill dc-delta-pill--${tYtdClass}">
            ${tYtdIcon}${tot.d_ytd || '-'}
          </span>
        </td>
        <td class="text-right text-muted font-weight-bold">${tot.rka || '-'}</td>
      </tr>`;
    }
  }

  document.querySelectorAll('.dc-trigger-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.dc-trigger-btn').forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-pressed', 'false');
      });
      this.classList.add('active');
      this.setAttribute('aria-pressed', 'true');
      const chKey = this.getAttribute('data-channel');
      renderDigitalChannel(chKey);
    });
  });

  // 4. Initialize Monthly Timeseries Chart (Chart.js)
  function initSimpananMonthlyChart() {
    const monthlyCanvas = document.getElementById('simpananMonthlyChart');
    if (!monthlyCanvas) return;

    if (typeof Chart === 'undefined') {
      setTimeout(initSimpananMonthlyChart, 50);
      return;
    }

    const initMonthlyData = window.simpananMonthlyDataByScope ? (window.simpananMonthlyDataByScope['area6'] || {}) : {};
    const ctx = monthlyCanvas.getContext('2d');

    window.simpananMonthlyChartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: (initMonthlyData.labels && initMonthlyData.labels.length > 0) ? initMonthlyData.labels : ['Mar 26', 'Apr 26', 'Mei 26', 'Jun 26', 'Jul 26', 'Agt 26', 'Sep 26'],
        datasets: [
          {
            type: 'bar',
            label: 'H-1 Akhir Bulan',
            data: (initMonthlyData.h1_values && initMonthlyData.h1_values.length > 0) ? initMonthlyData.h1_values : [],
            backgroundColor: '#94a3b8',
            borderRadius: 6,
            barPercentage: 0.65,
            categoryPercentage: 0.55,
            yAxisID: 'y'
          },
          {
            type: 'bar',
            label: 'H Akhir Bulan',
            data: (initMonthlyData.h_values && initMonthlyData.h_values.length > 0) ? initMonthlyData.h_values : [],
            backgroundColor: '#307fe2',
            hoverBackgroundColor: '#0857c3',
            borderRadius: 6,
            barPercentage: 0.65,
            categoryPercentage: 0.55,
            yAxisID: 'y'
          },
          {
            type: 'line',
            label: 'Delta Tutup',
            data: (initMonthlyData.deltas && initMonthlyData.deltas.length > 0) ? initMonthlyData.deltas : [],
            borderColor: '#10b981',
            backgroundColor: '#10b981',
            borderWidth: 2.5,
            pointBackgroundColor: '#10b981',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 4.5,
            pointHoverRadius: 6.5,
            tension: 0.35,
            fill: false,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false
        },
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.94)',
            titleFont: { size: 12, family: 'Inter', weight: 'bold' },
            bodyFont: { size: 11, family: 'Inter' },
            padding: 10,
            cornerRadius: 8,
            callbacks: {
              label: function(context) {
                let label = context.dataset.label || '';
                let val = context.parsed.y;
                if (val !== null) {
                  label += ': ' + Number(val).toLocaleString('id-ID') + ' Jt';
                }
                return label;
              }
            }
          }
        },
        scales: {
          x: {
            grid: {
              display: false
            },
            ticks: {
              font: { family: 'Inter', size: 11, weight: '600' },
              color: '#475569'
            }
          },
          y: {
            position: 'left',
            grid: {
              color: '#f1f5f9'
            },
            ticks: {
              font: { family: 'Inter', size: 10 },
              color: '#64748b',
              callback: function(value) {
                return Number(value).toLocaleString('id-ID');
              }
            }
          },
          y1: {
            position: 'right',
            grid: {
              drawOnChartArea: false
            },
            ticks: {
              font: { family: 'Inter', size: 10 },
              color: '#10b981',
              callback: function(value) {
                return (value > 0 ? '+' : '') + Number(value).toLocaleString('id-ID');
              }
            }
          }
        }
      }
    });
  }

  // Start Chart initialization
  initSimpananMonthlyChart();

  // 5. Selectors (Date & Branch)
  const dateSelector = document.getElementById('periode-selector');
  if (dateSelector) {
    dateSelector.addEventListener('change', function() {
      const targetUrl = new URL(window.location.href);
      targetUrl.searchParams.set('periode', this.value);
      targetUrl.searchParams.delete('_area6');
      window.location.href = targetUrl.toString();
    });
  }

  const branchSelector = document.getElementById('landing-branch-selector');
  if (branchSelector && !branchSelector.disabled) {
    branchSelector.addEventListener('change', function() {
      const targetUrl = new URL(window.location.href);
      targetUrl.searchParams.set('cabang', this.value);
      targetUrl.searchParams.delete('_area6');
      window.location.href = targetUrl.toString();
    });
  }

  // 6. CASA View Toggle (Cabang vs Segmen)
  const casaButtons = document.querySelectorAll('#casaViewToggle .sdd-toggle-btn');
  casaButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      casaButtons.forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-pressed', 'false');
      });
      this.classList.add('active');
      this.setAttribute('aria-pressed', 'true');
      const targetId = this.getAttribute('data-target');
      const tableCabang = document.getElementById('casa-table-cabang');
      const tableSegmen = document.getElementById('casa-table-segmen');
      if (targetId === 'casa-table-segmen') {
        if (tableCabang) tableCabang.style.display = 'none';
        if (tableSegmen) tableSegmen.style.display = 'block';
      } else {
        if (tableCabang) tableCabang.style.display = 'block';
        if (tableSegmen) tableSegmen.style.display = 'none';
      }
    });
  });

  // 7. Payroll Quality Strategy (Per Cabang + Modal Nominatif)
  const payrollModal = document.getElementById('payrollNominatifModal');
  const payrollModalBranchTitle = document.getElementById('payrollModalBranchTitle');
  const payrollModalSearch = document.getElementById('payrollModalSearch');
  const payrollModalRows = Array.from(document.querySelectorAll('.payroll-modal-row'));
  const payrollModalCountText = document.getElementById('payrollModalCountText');
  const payrollModalSummaryText = document.getElementById('payrollModalSummaryText');
  const modalTabButtons = document.querySelectorAll('.modal-tab-btn');

  let activeModalKc = 'ALL';

  function filterModalNominatif() {
    if (!payrollModalRows.length) return;
    const query = (payrollModalSearch ? payrollModalSearch.value : '').trim().toLowerCase();

    let visibleCount = 0;
    let sumPotensi = 0;
    let sumRealisasi = 0;

    payrollModalRows.forEach(row => {
      const rowKc = row.getAttribute('data-kc') || '';
      const rowNama = row.getAttribute('data-nama') || '';
      const rowCif = row.getAttribute('data-cif') || '';

      const matchKc = (activeModalKc === 'ALL' || rowKc.toLowerCase().includes(activeModalKc.toLowerCase()));
      const matchQuery = (!query || rowNama.includes(query) || rowCif.includes(query));

      if (matchKc && matchQuery) {
        row.style.display = '';
        visibleCount++;
        const noCol = row.querySelector('.modal-col-no');
        if (noCol) noCol.textContent = visibleCount;

        sumPotensi += parseInt(row.getAttribute('data-potensi') || '0', 10);
        sumRealisasi += parseInt(row.getAttribute('data-realisasi') || '0', 10);
      } else {
        row.style.display = 'none';
      }
    });

    if (payrollModalCountText) {
      payrollModalCountText.textContent = `Menampilkan ${visibleCount} dari ${payrollModalRows.length} mitra`;
    }
    if (payrollModalSummaryText) {
      payrollModalSummaryText.textContent = `Potensi: ${sumPotensi.toLocaleString('id-ID')} Pegawai | Realisasi: ${sumRealisasi.toLocaleString('id-ID')} Rek`;
    }
  }

  function openPayrollNominatif(targetKc) {
    activeModalKc = targetKc || 'ALL';

    if (payrollModalBranchTitle) {
      payrollModalBranchTitle.textContent = activeModalKc === 'ALL' ? 'Semua Cabang (Konsolidasi)' : activeModalKc;
    }

    modalTabButtons.forEach(btn => {
      const btnKc = btn.getAttribute('data-kc');
      btn.classList.toggle('active', btnKc === activeModalKc);
    });

    if (payrollModalSearch) {
      payrollModalSearch.value = '';
    }

    filterModalNominatif();

    if (typeof $ !== 'undefined' && $(payrollModal).modal) {
      $(payrollModal).modal('show');
    } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(payrollModal).show();
    } else if (payrollModal) {
      payrollModal.classList.add('show');
      payrollModal.style.display = 'block';
    }
  }

  document.querySelectorAll('.btn-open-nominatif').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      const kc = this.getAttribute('data-kc') || 'ALL';
      openPayrollNominatif(kc);
    });
  });

  document.querySelectorAll('.payroll-branch-row').forEach(row => {
    row.addEventListener('click', function() {
      const kc = this.getAttribute('data-kc') || 'ALL';
      openPayrollNominatif(kc);
    });
  });

  modalTabButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      activeModalKc = this.getAttribute('data-kc') || 'ALL';
      modalTabButtons.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      if (payrollModalBranchTitle) {
        payrollModalBranchTitle.textContent = activeModalKc === 'ALL' ? 'Semua Cabang (Konsolidasi)' : activeModalKc;
      }
      filterModalNominatif();
    });
  });

  if (payrollModalSearch) {
    payrollModalSearch.addEventListener('input', filterModalNominatif);
  }

  // 6. Ecosystem & Value Chain Modal Handlers
  window.ecosystemNominatifData = @json($ecosystemRecords ?? []);
  const ecosystemModal = document.getElementById('ecosystemNominatifModal');
  const ecoModalBranchTitle = document.getElementById('ecosystemModalBranchTitle');
  const ecoModalTableBody = document.getElementById('ecosystemModalTableBody');
  const ecoModalCountText = document.getElementById('ecosystemModalCountText');
  const ecoModalSummaryText = document.getElementById('ecosystemModalSummaryText');
  const ecoModalPagination = document.getElementById('ecosystemModalPagination');
  const ecoModalSearch = document.getElementById('ecosystemModalSearch');
  const ecoModalFilterCategory = document.getElementById('ecosystemModalFilterCategory');
  const ecoModalTabButtons = document.querySelectorAll('#ecosystemModalTabs .modal-tab-btn');

  let activeEcoBranch = 'ALL';
  let activeEcoCategory = 'ALL';
  let activeEcoSearch = '';
  let activeEcoPage = 1;
  const ecoPageSize = 25;
  let filteredEcoRecords = [];

  function formatIdrCurrency(amount) {
    return 'Rp ' + Math.round(amount).toLocaleString('id-ID');
  }

  function filterAndRenderEcosystemNominatif() {
    const rawList = window.ecosystemNominatifData || [];
    const query = activeEcoSearch.trim().toLowerCase();

    filteredEcoRecords = rawList.filter(item => {
      const matchBranch = (activeEcoBranch === 'ALL' || item.cabang === activeEcoBranch);
      const matchCat = (activeEcoCategory === 'ALL' || item.ekosistem === activeEcoCategory);
      const matchSearch = !query ||
        (item.nama && item.nama.toLowerCase().includes(query)) ||
        (item.norek && item.norek.toLowerCase().includes(query));
      return matchBranch && matchCat && matchSearch;
    });

    activeEcoPage = 1;
    renderEcoCurrentPage();
  }

  function renderEcoCurrentPage() {
    if (!ecoModalTableBody) return;

    const total = filteredEcoRecords.length;
    const totalPages = Math.ceil(total / ecoPageSize) || 1;
    if (activeEcoPage > totalPages) activeEcoPage = totalPages;
    if (activeEcoPage < 1) activeEcoPage = 1;

    const startIdx = (activeEcoPage - 1) * ecoPageSize;
    const endIdx = Math.min(startIdx + ecoPageSize, total);
    const pageItems = filteredEcoRecords.slice(startIdx, endIdx);

    let totalSaldoVisible = 0;
    for (let i = 0; i < total; i++) {
      totalSaldoVisible += (filteredEcoRecords[i].saldo || 0);
    }

    if (total === 0) {
      ecoModalTableBody.innerHTML = `
        <tr>
          <td colspan="6" class="text-center py-4 text-muted">
            Tidak ada data nasabah ekosistem yang sesuai filter.
          </td>
        </tr>`;
    } else {
      let html = '';
      const badgeClasses = {
        'Pendidikan': 'badge-eco-pendidikan',
        'Rumah Sakit': 'badge-eco-rs',
        'Masjid': 'badge-eco-masjid',
        'Kristen': 'badge-eco-kristen',
        'Katolik': 'badge-eco-katolik',
        'Ponpes': 'badge-eco-ponpes'
      };

      pageItems.forEach((r, idx) => {
        const rowNo = startIdx + idx + 1;
        const bCls = badgeClasses[r.ekosistem] || 'badge-light';
        const label = r.labelEkosistem || r.ekosistem;
        const saldoFmt = r.saldo_fmt || formatIdrCurrency(r.saldo || 0);

        html += `
          <tr>
            <td class="text-center font-weight-bold text-muted" style="font-size: 0.72rem;">${rowNo}</td>
            <td><span class="badge-kc-pill">${r.cabang || '-'}</span></td>
            <td><span class="badge ${bCls} badge-eco-pill">${label}</span></td>
            <td class="font-weight-bold text-dark">${r.nama || '-'}</td>
            <td class="text-muted" style="font-size: 0.72rem; font-family: monospace;">${r.norek || '-'}</td>
            <td class="text-right font-weight-bold" style="color: #004685; font-size: 0.85rem;">${saldoFmt}</td>
          </tr>`;
      });
      ecoModalTableBody.innerHTML = html;
    }

    if (ecoModalCountText) {
      ecoModalCountText.textContent = total > 0
        ? `Menampilkan ${startIdx + 1} - ${endIdx} dari ${total.toLocaleString('id-ID')} nasabah`
        : 'Menampilkan 0 nasabah';
    }

    if (ecoModalSummaryText) {
      ecoModalSummaryText.textContent = `Total Saldo: ${formatIdrCurrency(totalSaldoVisible)}`;
    }

    renderEcoPaginationControls(totalPages);
  }

  function renderEcoPaginationControls(totalPages) {
    if (!ecoModalPagination) return;
    if (totalPages <= 1) {
      ecoModalPagination.innerHTML = '';
      return;
    }

    let phtml = '';
    // Prev
    phtml += `<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" ${activeEcoPage === 1 ? 'disabled' : ''} onclick="window.changeEcoPage(${activeEcoPage - 1})"><i class="fas fa-chevron-left"></i></button>`;

    // Window of pages
    const maxBtns = 5;
    let startP = Math.max(1, activeEcoPage - 2);
    let endP = Math.min(totalPages, startP + maxBtns - 1);
    if (endP - startP < maxBtns - 1) {
      startP = Math.max(1, endP - maxBtns + 1);
    }

    for (let p = startP; p <= endP; p++) {
      const active = p === activeEcoPage;
      phtml += `<button type="button" class="btn btn-sm ${active ? 'btn-primary' : 'btn-outline-secondary'} py-0 px-2 font-weight-bold" style="font-size: 0.72rem; ${active ? 'background:#004685; border-color:#004685;' : ''}" onclick="window.changeEcoPage(${p})">${p}</button>`;
    }

    // Next
    phtml += `<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" ${activeEcoPage === totalPages ? 'disabled' : ''} onclick="window.changeEcoPage(${activeEcoPage + 1})"><i class="fas fa-chevron-right"></i></button>`;

    ecoModalPagination.innerHTML = phtml;
  }

  window.changeEcoPage = function(newPage) {
    activeEcoPage = newPage;
    renderEcoCurrentPage();
    const modalBody = ecosystemModal ? ecosystemModal.querySelector('.modal-body') : null;
    if (modalBody) modalBody.scrollTop = 0;
  };

  function openEcosystemNominatif(targetKc) {
    activeEcoBranch = targetKc || 'ALL';

    if (ecoModalBranchTitle) {
      ecoModalBranchTitle.textContent = activeEcoBranch === 'ALL' ? 'Semua Cabang (Konsolidasi)' : activeEcoBranch;
    }

    ecoModalTabButtons.forEach(btn => {
      const btnKc = btn.getAttribute('data-kc');
      btn.classList.toggle('active', btnKc === activeEcoBranch);
    });

    if (ecoModalSearch) {
      ecoModalSearch.value = '';
      activeEcoSearch = '';
    }

    if (ecoModalFilterCategory) {
      ecoModalFilterCategory.value = 'ALL';
      activeEcoCategory = 'ALL';
    }

    filterAndRenderEcosystemNominatif();

    if (typeof $ !== 'undefined' && $(ecosystemModal).modal) {
      $(ecosystemModal).modal('show');
    } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(ecosystemModal).show();
    } else if (ecosystemModal) {
      ecosystemModal.classList.add('show');
      ecosystemModal.style.display = 'block';
    }
  }

  document.querySelectorAll('.btn-open-ecosystem-nominatif').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      const kc = this.getAttribute('data-kc') || 'ALL';
      openEcosystemNominatif(kc);
    });
  });

  document.querySelectorAll('.ecosystem-branch-row').forEach(row => {
    row.addEventListener('click', function() {
      const kc = this.getAttribute('data-kc') || 'ALL';
      openEcosystemNominatif(kc);
    });
  });

  ecoModalTabButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      activeEcoBranch = this.getAttribute('data-kc') || 'ALL';
      ecoModalTabButtons.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      if (ecoModalBranchTitle) {
        ecoModalBranchTitle.textContent = activeEcoBranch === 'ALL' ? 'Semua Cabang (Konsolidasi)' : activeEcoBranch;
      }
      filterAndRenderEcosystemNominatif();
    });
  });

  if (ecoModalFilterCategory) {
    ecoModalFilterCategory.addEventListener('change', function() {
      activeEcoCategory = this.value;
      filterAndRenderEcosystemNominatif();
    });
  }

  if (ecoModalSearch) {
    ecoModalSearch.addEventListener('input', function() {
      activeEcoSearch = this.value;
      filterAndRenderEcosystemNominatif();
    });
  }

  // 7. Perusahaan Anak Modal Handlers
  window.perusahaanAnakRowsData = @json($perusahaanAnakRows ?? []);
  const paModal = document.getElementById('perusahaanAnakNominatifModal');
  const paModalBranchTitle = document.getElementById('paModalBranchTitle');
  const paModalTableBody = document.getElementById('perusahaanAnakModalTableBody');
  const paModalCountText = document.getElementById('paModalCountText');
  const paModalSummaryText = document.getElementById('paModalSummaryText');
  const paModalSearch = document.getElementById('paModalSearch');
  const paModalFilterEntity = document.getElementById('paModalFilterEntity');
  const paModalFilterStatus = document.getElementById('paModalFilterStatus');
  const paModalTabButtons = document.querySelectorAll('#paModalTabs .modal-tab-btn');

  let activePaBranch = 'ALL';
  let activePaEntity = 'ALL';
  let activePaStatus = 'ALL';
  let activePaSearch = '';

  function filterAndRenderPerusahaanAnakNominatif() {
    const rawList = window.perusahaanAnakRowsData || [];
    const query = activePaSearch.trim().toLowerCase();

    const filtered = rawList.filter(item => {
      const bName = item.kc || item.branchOffice || '';
      const entName = item.perusahaan_anak || item.perusahaanAnak || '';
      const stName = item.status || '';

      const matchBranch = (activePaBranch === 'ALL' || bName === activePaBranch);
      const matchEntity = (activePaEntity === 'ALL' || entName === activePaEntity);
      const matchStatus = (activePaStatus === 'ALL' || stName === activePaStatus);
      const matchSearch = !query ||
        ((item.partner || item.namaPartner || '').toLowerCase().includes(query)) ||
        ((item.bidang_usaha || item.bidangUsaha || '').toLowerCase().includes(query)) ||
        ((item.cif || item.cifNo || '').toLowerCase().includes(query));
      return matchBranch && matchEntity && matchStatus && matchSearch;
    });

    if (!paModalTableBody) return;

    if (filtered.length === 0) {
      paModalTableBody.innerHTML = `
        <tr>
          <td colspan="9" class="text-center py-4 text-muted">
            Tidak ada data mitra perusahaan anak yang sesuai filter.
          </td>
        </tr>`;
    } else {
      let html = '';
      let totalSaldo = 0;
      filtered.forEach((r, idx) => {
        const sepVal = Number(r.saldo_september || r.saldoSeptember || 0);
        totalSaldo += sepVal;
        const isSudah = r.status === 'Sudah Terakuisisi';
        const badgeCls = isSudah ? 'badge-visit--yes' : 'badge-visit--no';
        const sepColor = sepVal > 0 ? 'color: #004685;' : '';

        html += `
          <tr>
            <td class="text-center font-weight-bold text-muted" style="font-size: 0.72rem;">${idx + 1}</td>
            <td>
              <div class="font-weight-bold text-dark">${r.partner || r.namaPartner || '-'}</div>
              <small class="text-muted">${r.bidang_usaha || r.bidangUsaha || '-'}</small>
            </td>
            <td><span class="badge-kc-pill">${r.kc || r.branchOffice || '-'}</span></td>
            <td><span class="badge" style="background:#eff6ff; color:#004685; font-size:0.75rem; font-weight:700;">${r.perusahaan_anak || r.perusahaanAnak || '-'}</span></td>
            <td class="text-center">
              <span class="badge ${badgeCls}">${r.status}</span>
            </td>
            <td style="font-family: monospace; font-size: 0.75rem;">${r.cif || r.cifNo || '-'}</td>
            <td class="text-right text-muted" style="font-size: 0.75rem;">${r.saldo_juli_fmt || 'Rp 0'}</td>
            <td class="text-right text-muted" style="font-size: 0.75rem;">${r.saldo_agustus_fmt || 'Rp 0'}</td>
            <td class="text-right font-weight-bold" style="${sepColor}; font-size: 0.85rem;">${r.saldo_september_fmt || 'Rp 0'}</td>
          </tr>`;
      });
      paModalTableBody.innerHTML = html;

      if (paModalSummaryText) {
        paModalSummaryText.textContent = `Total Saldo (Sep): Rp ${Math.round(totalSaldo).toLocaleString('id-ID')}`;
      }
    }

    if (paModalCountText) {
      paModalCountText.textContent = `Menampilkan ${filtered.length} mitra`;
    }
  }

  function openPaNominatif(targetKc) {
    activePaBranch = targetKc || 'ALL';

    if (paModalBranchTitle) {
      paModalBranchTitle.textContent = activePaBranch === 'ALL' ? 'Semua Cabang (Konsolidasi)' : activePaBranch;
    }

    paModalTabButtons.forEach(btn => {
      const btnKc = btn.getAttribute('data-kc');
      btn.classList.toggle('active', btnKc === activePaBranch);
    });

    if (paModalSearch) {
      paModalSearch.value = '';
      activePaSearch = '';
    }

    if (paModalFilterEntity) {
      paModalFilterEntity.value = 'ALL';
      activePaEntity = 'ALL';
    }

    if (paModalFilterStatus) {
      paModalFilterStatus.value = 'ALL';
      activePaStatus = 'ALL';
    }

    filterAndRenderPerusahaanAnakNominatif();

    if (typeof $ !== 'undefined' && $(paModal).modal) {
      $(paModal).modal('show');
    } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(paModal).show();
    } else if (paModal) {
      paModal.classList.add('show');
      paModal.style.display = 'block';
    }
  }

  document.querySelectorAll('.btn-open-pa-nominatif').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      const kc = this.getAttribute('data-kc') || 'ALL';
      openPaNominatif(kc);
    });
  });

  document.querySelectorAll('.pa-branch-row').forEach(row => {
    row.addEventListener('click', function() {
      const kc = this.getAttribute('data-kc') || 'ALL';
      openPaNominatif(kc);
    });
  });

  paModalTabButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      paModalTabButtons.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      activePaBranch = this.getAttribute('data-kc') || 'ALL';
      if (paModalBranchTitle) {
        paModalBranchTitle.textContent = activePaBranch === 'ALL' ? 'Semua Cabang (Konsolidasi)' : activePaBranch;
      }
      filterAndRenderPerusahaanAnakNominatif();
    });
  });

  if (paModalFilterEntity) {
    paModalFilterEntity.addEventListener('change', function() {
      activePaEntity = this.value;
      filterAndRenderPerusahaanAnakNominatif();
    });
  }

  if (paModalFilterStatus) {
    paModalFilterStatus.addEventListener('change', function() {
      activePaStatus = this.value;
      filterAndRenderPerusahaanAnakNominatif();
    });
  }

  if (paModalSearch) {
    paModalSearch.addEventListener('input', function() {
      activePaSearch = this.value;
      filterAndRenderPerusahaanAnakNominatif();
    });
  }

  document.querySelectorAll('[data-dismiss="modal"], [data-bs-dismiss="modal"]').forEach(btn => {
    btn.addEventListener('click', function() {
      if (payrollModal) {
        if (typeof $ !== 'undefined' && $(payrollModal).modal) {
          $(payrollModal).modal('hide');
        } else {
          payrollModal.classList.remove('show');
          payrollModal.style.display = 'none';
        }
      }
      if (ecosystemModal) {
        if (typeof $ !== 'undefined' && $(ecosystemModal).modal) {
          $(ecosystemModal).modal('hide');
        } else {
          ecosystemModal.classList.remove('show');
          ecosystemModal.style.display = 'none';
        }
      }
      if (paModal) {
        if (typeof $ !== 'undefined' && $(paModal).modal) {
          $(paModal).modal('hide');
        } else {
          paModal.classList.remove('show');
          paModal.style.display = 'none';
        }
      }
    });
  });
});
</script>
@endsection

@push('modals')
<!-- Modal Detail Nominatif Pipeline Payroll -->
<div class="modal fade modal-payroll-nominatif" id="payrollNominatifModal" tabindex="-1" role="dialog" aria-labelledby="payrollModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header d-flex align-items-center justify-content-between" style="background: #004685; color: #ffffff; padding: 0.85rem 1.25rem;">
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-users-rectangle mr-2" style="font-size: 1.15rem;"></i>
          <div>
            <h5 class="modal-title font-weight-bold mb-0" id="payrollModalLabel" style="font-size: 1rem; color: #ffffff;">
              Detail Nominatif Pipeline Payroll — <span id="payrollModalBranchTitle">Semua Cabang</span>
            </h5>
            <small class="text-white-50" style="font-size: 0.72rem;">Daftar mitra instansi, potensi pegawai, realisasi & status kunjungan (Urut Potensi Tertinggi)</small>
          </div>
        </div>
        <button type="button" class="close text-white opacity-75" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="outline: none;">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- Quick Modal Switcher Bar -->
      <div class="bg-light px-3 py-2 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex flex-wrap align-items-center gap-1" id="payrollModalTabs">
          <button type="button" class="modal-tab-btn" data-kc="ALL">
            Semua ({{ count($payrollRows) }})
          </button>
          <button type="button" class="modal-tab-btn" data-kc="KC Madiun">
            KC Madiun ({{ data_get($payrollSummary, 'branches.KC Madiun.perusahaan', 0) }})
          </button>
          <button type="button" class="modal-tab-btn" data-kc="KC Magetan">
            KC Magetan ({{ data_get($payrollSummary, 'branches.KC Magetan.perusahaan', 0) }})
          </button>
          <button type="button" class="modal-tab-btn" data-kc="KC Ngawi">
            KC Ngawi ({{ data_get($payrollSummary, 'branches.KC Ngawi.perusahaan', 0) }})
          </button>
          <button type="button" class="modal-tab-btn" data-kc="KC Ponorogo">
            KC Ponorogo ({{ data_get($payrollSummary, 'branches.KC Ponorogo.perusahaan', 0) }})
          </button>
        </div>
        <div style="min-width: 200px;">
          <input type="text" id="payrollModalSearch" class="form-control form-control-sm" placeholder="Cari nama perusahaan / CIF..." style="font-size: 0.75rem; border-radius: 6px;">
        </div>
      </div>

      <div class="modal-body p-0" style="max-height: 520px; overflow-y: auto;">
        <table class="table table-hover table-striped mb-0" id="payrollModalTable" style="font-size: 0.78rem;">
          <thead class="thead-light" style="position: sticky; top: 0; z-index: 5; background: #f8fafc;">
            <tr>
              <th style="width: 40px;" class="text-center">#</th>
              <th>Nama Perusahaan / Mitra</th>
              <th>Cabang (KC)</th>
              <th>Segmen</th>
              <th>Status</th>
              <th class="text-right">Pegawai</th>
              <th class="text-right" style="color: #004685;">Potensi</th>
              <th class="text-right" style="color: #16a34a;">Realisasi</th>
              <th class="text-center">Kunjungan</th>
              <th style="min-width: 200px;">Hasil Kunjungan / Catatan</th>
            </tr>
          </thead>
          <tbody id="payrollModalTableBody">
            @forelse($payrollRows as $idx => $r)
              <tr class="payroll-modal-row"
                  data-kc="{{ $r['kc'] ?? '' }}"
                  data-nama="{{ strtolower($r['nama'] ?? '') }}"
                  data-cif="{{ strtolower($r['cif'] ?? '') }}"
                  data-pegawai="{{ $r['pegawai'] ?? 0 }}"
                  data-potensi="{{ $r['potensi'] ?? 0 }}"
                  data-realisasi="{{ $r['realisasi'] ?? 0 }}"
                  data-kunjungan="{{ !empty($r['kunjungan']) ? '1' : '0' }}">
                <td class="text-center font-weight-bold text-muted modal-col-no" style="font-size: 0.72rem;">{{ $idx + 1 }}</td>
                <td>
                  <div class="font-weight-bold text-dark">{{ $r['nama'] ?? '-' }}</div>
                  @if(!empty($r['cif']) && $r['cif'] !== '-')
                    <div class="text-muted" style="font-size: 0.65rem;">CIF: {{ $r['cif'] }}</div>
                  @endif
                </td>
                <td><span class="badge-kc-pill">{{ $r['kc'] ?? '-' }}</span></td>
                <td><span class="badge-segmen">{{ $r['segmen'] ?? 'Umum' }}</span></td>
                <td>
                  @if(strtolower($r['status'] ?? '') === 'new')
                    <span class="badge-status-new">New</span>
                  @else
                    <span class="badge-status-existing">Existing</span>
                  @endif
                </td>
                <td class="text-right font-weight-bold text-secondary">{{ $r['pegawai_fmt'] ?? '0' }}</td>
                <td class="text-right font-weight-bold" style="color: #004685;">{{ $r['potensi_fmt'] ?? '0' }}</td>
                <td class="text-right font-weight-bold text-success">{{ $r['realisasi_fmt'] ?? '0' }}</td>
                <td class="text-center">
                  @if(!empty($r['kunjungan']))
                    <span class="badge-visit badge-visit--yes"><i class="fas fa-check"></i> Sudah</span>
                  @else
                    <span class="badge-visit badge-visit--no"><i class="fas fa-minus"></i> Belum</span>
                  @endif
                </td>
                <td class="text-muted" style="font-size: 0.73rem;">
                  {{ $r['catatan'] ?? '-' }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" class="text-center py-4 text-muted">
                  Tidak ada data nominatif pipeline.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="modal-footer d-flex align-items-center justify-content-between py-2 px-3 bg-light">
        <div class="d-flex align-items-center gap-3">
          <span id="payrollModalCountText" class="text-muted font-weight-bold small">
            Menampilkan {{ count($payrollRows) }} mitra
          </span>
          <span class="badge badge-light border text-secondary font-weight-bold" id="payrollModalSummaryText">
            Potensi: {{ data_get($payrollSummary, 'total_potensi_fmt', '0') }} Pegawai | Realisasi: {{ data_get($payrollSummary, 'total_realisasi_fmt', '0') }} Rek
          </span>
        </div>
        <button type="button" class="btn btn-secondary btn-sm px-3 font-weight-bold" data-dismiss="modal" data-bs-dismiss="modal">
          Tutup
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Detail Nominatif Ecosystem & Value Chain -->
<div class="modal fade modal-payroll-nominatif modal-ecosystem-nominatif" id="ecosystemNominatifModal" tabindex="-1" role="dialog" aria-labelledby="ecosystemModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header d-flex align-items-center justify-content-between" style="background: #004685; color: #ffffff; padding: 0.85rem 1.25rem;">
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-sitemap mr-2" style="font-size: 1.15rem;"></i>
          <div>
            <h5 class="modal-title font-weight-bold mb-0" id="ecosystemModalLabel" style="font-size: 1rem; color: #ffffff;">
              Detail Nominatif Nasabah Ecosystem & Value Chain — <span id="ecosystemModalBranchTitle">Semua Cabang</span>
            </h5>
            <small class="text-white-50" style="font-size: 0.72rem;">Daftar rekening nasabah 6 pilar ekosistem (Urut Saldo Terbesar)</small>
          </div>
        </div>
        <button type="button" class="close text-white opacity-75" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="outline: none;">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- Toolbar: Tab Cabang, Select Ekosistem, Live Search -->
      <div class="bg-light px-3 py-2 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex flex-wrap align-items-center gap-1" id="ecosystemModalTabs">
          <button type="button" class="modal-tab-btn active" data-kc="ALL">
            Semua ({{ count($ecosystemRecords) }})
          </button>
          <button type="button" class="modal-tab-btn" data-kc="KC Madiun">
            KC Madiun ({{ data_get($ecosystemBranches, 'KC Madiun.rekening_fmt', 0) }})
          </button>
          <button type="button" class="modal-tab-btn" data-kc="KC Magetan">
            KC Magetan ({{ data_get($ecosystemBranches, 'KC Magetan.rekening_fmt', 0) }})
          </button>
          <button type="button" class="modal-tab-btn" data-kc="KC Ngawi">
            KC Ngawi ({{ data_get($ecosystemBranches, 'KC Ngawi.rekening_fmt', 0) }})
          </button>
          <button type="button" class="modal-tab-btn" data-kc="KC Ponorogo">
            KC Ponorogo ({{ data_get($ecosystemBranches, 'KC Ponorogo.rekening_fmt', 0) }})
          </button>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <select id="ecosystemModalFilterCategory" class="form-control form-control-sm" style="font-size: 0.75rem; border-radius: 6px; width: auto;">
            <option value="ALL">Semua Ekosistem (6 Pilar)</option>
            <option value="Pendidikan">Lembaga Pendidikan ({{ data_get($ecosystemPillars, 'Pendidikan.count_fmt', 0) }})</option>
            <option value="Rumah Sakit">Rumah Sakit ({{ data_get($ecosystemPillars, 'Rumah Sakit.count_fmt', 0) }})</option>
            <option value="Masjid">Masjid ({{ data_get($ecosystemPillars, 'Masjid.count_fmt', 0) }})</option>
            <option value="Kristen">Kristen ({{ data_get($ecosystemPillars, 'Kristen.count_fmt', 0) }})</option>
            <option value="Katolik">Katolik ({{ data_get($ecosystemPillars, 'Katolik.count_fmt', 0) }})</option>
            <option value="Ponpes">Pondok Pesantren ({{ data_get($ecosystemPillars, 'Ponpes.count_fmt', 0) }})</option>
          </select>
          <div style="min-width: 220px;">
            <input type="text" id="ecosystemModalSearch" class="form-control form-control-sm" placeholder="Cari nama nasabah / norek..." style="font-size: 0.75rem; border-radius: 6px;">
          </div>
        </div>
      </div>

      <div class="modal-body p-0" style="max-height: 520px; overflow-y: auto;">
        <table class="table table-hover table-striped mb-0" id="ecosystemModalTable" style="font-size: 0.78rem;">
          <thead class="thead-light" style="position: sticky; top: 0; z-index: 5; background: #f8fafc;">
            <tr>
              <th style="width: 45px;" class="text-center">#</th>
              <th>Kantor Cabang</th>
              <th>Segmen Ekosistem</th>
              <th>Nama Nasabah</th>
              <th>Nomor Rekening</th>
              <th class="text-right" style="color: #004685;">Posisi Saldo Terakhir</th>
            </tr>
          </thead>
          <tbody id="ecosystemModalTableBody">
            <!-- Rendered by JavaScript for instant responsiveness -->
          </tbody>
        </table>
      </div>

      <div class="modal-footer d-flex align-items-center justify-content-between py-2 px-3 bg-light">
        <div class="d-flex align-items-center gap-3">
          <span id="ecosystemModalCountText" class="text-muted font-weight-bold small">
            Menampilkan 0 nasabah
          </span>
          <span class="badge badge-light border text-secondary font-weight-bold" id="ecosystemModalSummaryText">
            Total Saldo: Rp 0
          </span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div id="ecosystemModalPagination" class="d-flex align-items-center gap-1">
            <!-- Pagination buttons -->
          </div>
          <button type="button" class="btn btn-secondary btn-sm px-3 font-weight-bold ml-2" data-dismiss="modal" data-bs-dismiss="modal">
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Detail Nominatif Perusahaan Anak -->
<div class="modal fade modal-payroll-nominatif modal-perusahaan-anak-nominatif" id="perusahaanAnakNominatifModal" tabindex="-1" role="dialog" aria-labelledby="perusahaanAnakModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header d-flex align-items-center justify-content-between" style="background: #004685; color: #ffffff; padding: 0.85rem 1.25rem;">
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-handshake mr-2" style="font-size: 1.15rem;"></i>
          <div>
            <h5 class="modal-title font-weight-bold mb-0" id="perusahaanAnakModalLabel" style="font-size: 1rem; color: #ffffff;">
              Detail Nominatif Mitra Sinergi Perusahaan Anak — <span id="paModalBranchTitle">Semua Cabang</span>
            </h5>
            <small class="text-white-50" style="font-size: 0.72rem;">Daftar partner / vendor anak perusahaan (Urut Terakuisisi &amp; Saldo Terbesar)</small>
          </div>
        </div>
        <button type="button" class="close text-white opacity-75" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="outline: none;">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- Toolbar: Tab Cabang, Select Entitas, Select Status, Live Search -->
      <div class="bg-light px-3 py-2 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex flex-wrap align-items-center gap-1" id="paModalTabs">
          <button type="button" class="modal-tab-btn active" data-kc="ALL">
            Semua ({{ count($perusahaanAnakRows) }})
          </button>
          <button type="button" class="modal-tab-btn" data-kc="KC Madiun">
            KC Madiun ({{ data_get($perusahaanAnakSummary, 'branches.KC Madiun.total_pipeline', 0) }})
          </button>
          <button type="button" class="modal-tab-btn" data-kc="KC Magetan">
            KC Magetan ({{ data_get($perusahaanAnakSummary, 'branches.KC Magetan.total_pipeline', 0) }})
          </button>
          <button type="button" class="modal-tab-btn" data-kc="KC Ngawi">
            KC Ngawi ({{ data_get($perusahaanAnakSummary, 'branches.KC Ngawi.total_pipeline', 0) }})
          </button>
          <button type="button" class="modal-tab-btn" data-kc="KC Ponorogo">
            KC Ponorogo ({{ data_get($perusahaanAnakSummary, 'branches.KC Ponorogo.total_pipeline', 0) }})
          </button>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <select id="paModalFilterEntity" class="form-control form-control-sm" style="font-size: 0.75rem; border-radius: 6px; width: auto;">
            <option value="ALL">Semua Entitas</option>
            @foreach((array) data_get($perusahaanAnakSummary, 'entities', []) as $ent)
              <option value="{{ $ent }}">{{ $ent }}</option>
            @endforeach
          </select>
          <select id="paModalFilterStatus" class="form-control form-control-sm" style="font-size: 0.75rem; border-radius: 6px; width: auto;">
            <option value="ALL">Semua Status</option>
            <option value="Sudah Terakuisisi">Sudah Terakuisisi</option>
            <option value="Belum Terakuisisi">Belum Terakuisisi</option>
          </select>
          <div style="min-width: 220px;">
            <input type="text" id="paModalSearch" class="form-control form-control-sm" placeholder="Cari partner / CIF / usaha..." style="font-size: 0.75rem; border-radius: 6px;">
          </div>
        </div>
      </div>

      <div class="modal-body p-0" style="max-height: 520px; overflow-y: auto;">
        <table class="table table-hover table-striped mb-0" id="perusahaanAnakModalTable" style="font-size: 0.78rem;">
          <thead class="thead-light" style="position: sticky; top: 0; z-index: 5; background: #f8fafc;">
            <tr>
              <th style="width: 40px;" class="text-center">#</th>
              <th>Nama Partner / Vendor</th>
              <th>Kantor Cabang</th>
              <th>Entitas Anak</th>
              <th class="text-center">Status Akuisisi</th>
              <th>CIF NO</th>
              <th class="text-right">Saldo 31 Juli</th>
              <th class="text-right">Saldo 31 Agt</th>
              <th class="text-right" style="color: #004685;">Saldo 6 Sep</th>
            </tr>
          </thead>
          <tbody id="perusahaanAnakModalTableBody">
            <!-- Rendered by JavaScript -->
          </tbody>
        </table>
      </div>

      <div class="modal-footer d-flex align-items-center justify-content-between py-2 px-3 bg-light">
        <div class="d-flex align-items-center gap-3">
          <span id="paModalCountText" class="text-muted font-weight-bold small">
            Menampilkan 0 data
          </span>
          <span class="badge badge-light border text-secondary font-weight-bold" id="paModalSummaryText">
            Total Saldo: Rp 0
          </span>
        </div>
        <button type="button" class="btn btn-secondary btn-sm px-3 font-weight-bold" data-dismiss="modal" data-bs-dismiss="modal">
          Tutup
        </button>
      </div>
    </div>
  </div>
</div>
@endpush
