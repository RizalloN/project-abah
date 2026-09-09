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
  $branches = is_array(data_get($area6Portfolio, 'branches')) ? data_get($area6Portfolio, 'branches') : [];
  $periodLabel = data_get($area6Portfolio, 'period_label', 'Belum ada data');
@endphp

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,700&display=swap');

:root {
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
  
  --c-red: #dc2626;
  --c-red-l: #ef4444;
  --c-red-subtle: #fef2f2;
  --c-red-border: #fecaca;
  
  --c-purple: #7c3aed;
  --c-purple-l: #8b5cf6;
  --c-purple-subtle: #f5f3ff;
  
  --c-surface: #ffffff;
  --c-surf: #f8fafc;
  --c-border: #cbd5e1;
  --c-border-strong: #94a3b8;
  --c-text-main: #0f172a;
  --c-text-muted: #64748b;
  
  --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.05);
  --shadow-md: 0 4px 16px -2px rgba(15, 23, 42, 0.07), 0 2px 4px -1px rgba(15, 23, 42, 0.03);
  --shadow-hover: 0 14px 30px -4px rgba(8, 87, 195, 0.14), 0 6px 12px -2px rgba(15, 23, 42, 0.06);
  
  --r-sm: 6px;
  --r-md: 10px;
  --r-lg: 14px;
  --r-xl: 18px;
  --r-2xl: 22px;
}

.db-shell {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  color: var(--c-text-main);
  background: #f1f5f9;
  min-height: 100vh;
  padding: 0.85rem 1.25rem 2.5rem;
}

/* ── SLEEK EXECUTIVE HERO BANNER ── */
.simpanan-hero {
  position: relative;
  background: linear-gradient(135deg, #051833 0%, #0c2b5e 50%, #0754bd 100%);
  border-radius: var(--r-xl);
  padding: 1.15rem 1.65rem;
  margin-bottom: 1.15rem;
  box-shadow: 0 10px 28px -4px rgba(7, 84, 189, 0.3), 0 3px 8px rgba(0, 0, 0, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.15);
  overflow: hidden;
  color: #ffffff;
}

.simpanan-hero__ambient {
  position: absolute;
  top: -80px;
  right: 120px;
  width: 380px;
  height: 260px;
  background: radial-gradient(circle, rgba(56, 189, 248, 0.25) 0%, rgba(2, 132, 199, 0) 70%);
  pointer-events: none;
}

.simpanan-hero__content {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
}

.simpanan-hero__left {
  flex: 1 1 auto;
  min-width: 0;
}

.simpanan-hero__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #7dd3fc;
  background: rgba(56, 189, 248, 0.12);
  border: 1px solid rgba(125, 211, 252, 0.25);
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  margin-bottom: 0.45rem;
  text-transform: uppercase;
}

.simpanan-hero__pulse {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #38bdf8;
  box-shadow: 0 0 8px #38bdf8;
  animation: heroPulse 2s infinite;
}

@keyframes heroPulse {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(56, 189, 248, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(56, 189, 248, 0); }
}

.simpanan-hero__title {
  font-size: 1.45rem;
  font-weight: 900;
  letter-spacing: -0.02em;
  color: #ffffff;
  margin: 0 0 0.35rem;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.65rem;
  line-height: 1.2;
}

.simpanan-hero__badge {
  font-size: 0.75rem;
  font-weight: 800;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #ffffff;
  padding: 0.2rem 0.65rem;
  border-radius: 9999px;
  letter-spacing: 0;
  box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.simpanan-hero__subtitle {
  font-size: 0.8rem;
  color: #cbd5e1;
  max-width: 640px;
  line-height: 1.4;
  margin: 0 0 0.85rem;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.simpanan-hero__controls {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.65rem;
}

.hero-control-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.22);
  border-radius: var(--r-md);
  padding: 0.35rem 0.75rem;
  color: #ffffff;
  font-size: 0.78rem;
  font-weight: 700;
  transition: all 0.15s ease;
  cursor: pointer;
  line-height: 1;
}

.hero-control-pill:hover {
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.4);
}

.hero-control-pill i {
  color: #7dd3fc;
  font-size: 0.8rem;
}

.hero-control-label {
  color: #94a3b8;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.hero-control-select {
  border: none;
  background: transparent;
  color: #ffffff;
  font-size: 0.8rem;
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
  gap: 0.4rem;
  background: rgba(16, 185, 129, 0.18);
  border: 1px solid rgba(16, 185, 129, 0.35);
  color: #6ee7b7;
  font-size: 0.74rem;
  font-weight: 800;
  padding: 0.35rem 0.75rem;
  border-radius: var(--r-md);
  line-height: 1;
}

.hero-status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #34d399;
  box-shadow: 0 0 6px #34d399;
}

/* Compact Proportional SVG Illustration */
.simpanan-hero__visual {
  flex: 0 0 210px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.simpanan-hero__svg {
  width: 210px;
  height: 110px;
  filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.3));
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
  padding: 0.85rem 1.35rem;
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
  font-size: 1.15rem;
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
  gap: 0.4rem;
  padding: 0.28rem 0.75rem;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  color: #334155;
  font-size: 0.72rem;
  font-weight: 800;
  white-space: nowrap;
  border-radius: 9999px;
}

.area6-pill i {
  color: var(--c-blue);
  font-size: 0.75rem;
}

.area6-scope-toggle {
  display: inline-flex;
  gap: 0.25rem;
  padding: 0.22rem;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
}

.area6-scope-btn {
  border: 0;
  min-height: 32px;
  padding: 0.35rem 0.95rem;
  background: transparent;
  color: #475569;
  font-size: 0.75rem;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.18s ease;
  border-radius: 8px;
  letter-spacing: 0.01em;
}

.area6-scope-btn:hover {
  color: #0857c3;
  background: rgba(255, 255, 255, 0.85);
}

.area6-scope-btn.active {
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(8, 87, 195, 0.3);
  font-weight: 900;
}

/* ── PROGNOSA WEEK TOOLBAR ── */
.ap-week-toolbar {
  padding: 0.65rem 1.35rem;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.65rem;
}

.ap-week-toolbar__copy {
  font-size: 0.76rem;
  font-weight: 800;
  color: #334155;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.ap-week-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  padding: 0.2rem;
  border-radius: 10px;
}

.ap-week-btn {
  border: 1px solid transparent;
  background: transparent;
  border-radius: 7px;
  padding: 0.25rem 0.75rem;
  font-weight: 800;
  font-size: 0.74rem;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  line-height: 1.2;
}

.ap-week-btn small {
  font-size: 0.65rem;
  font-weight: 600;
  color: #94a3b8;
}

.ap-week-btn:hover:not(:disabled) {
  color: #0857c3;
  background: #f1f5f9;
}

.ap-week-btn.active {
  background: #0857c3;
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(8, 87, 195, 0.25);
}

.ap-week-btn.active small {
  color: #bfdbfe;
}

.ap-week-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* ── THREE HIGH-CONTRAST REFERENCE CARDS ── */
.area6-card-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1.25rem;
  padding: 1.25rem;
  align-items: stretch;
}

.area6-card-grid--three {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

@media (max-width: 1199px) {
  .area6-card-grid,
  .area6-card-grid--three {
    grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)) !important;
    gap: 1rem;
    padding: 1rem;
  }
}

@media (max-width: 767px) {
  .area6-card-grid,
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
}

.area6-card-premium:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-hover);
  border-color: #94a3b8;
}

.area6-card-premium[data-metric="tabungan"]:hover { border-color: #93c5fd; }
.area6-card-premium[data-metric="deposito"]:hover { border-color: #5eead4; }
.area6-card-premium[data-metric="giro"]:hover { border-color: #c4b5fd; }

/* Integrated Header Bar */
.ap-header {
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.15rem;
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
  width: 30px;
  height: 30px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 0.95rem;
  background: rgba(255, 255, 255, 0.22);
  border: 1px solid rgba(255, 255, 255, 0.35);
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
  flex-shrink: 0;
}

.ap-header-title {
  color: #ffffff;
  font-size: 0.92rem;
  font-weight: 900;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin: 0;
  line-height: 1.1;
  white-space: nowrap;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
}

.ap-header-tag {
  font-size: 0.65rem;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.9);
  background: rgba(0, 0, 0, 0.2);
  padding: 3px 8px;
  border-radius: 6px;
  letter-spacing: 0.05em;
  flex-shrink: 0;
}

/* Header & Badge Themes */
.ap-header.bg-tabungan, .ap-badge.bg-tabungan { background: linear-gradient(135deg, #0857c3 0%, #1d4ed8 100%) !important; }
.ap-header.bg-deposito, .ap-badge.bg-deposito { background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%) !important; }
.ap-header.bg-giro, .ap-badge.bg-giro { background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%) !important; }

/* Card Body */
.ap-body {
  padding: 1.2rem 1.15rem 1rem;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}

/* Row 1 & 2: Two-column clean grid with vertical divider */
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
  padding: 0.35rem 0.25rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  min-width: 0;
}

.ap-metric-label {
  font-size: 0.68rem;
  font-weight: 750;
  color: #475569;
  margin-bottom: 0.25rem;
  text-align: center;
  line-height: 1.25;
  letter-spacing: 0.02em;
  min-height: 2.3em;
  display: flex;
  align-items: center;
  justify-content: center;
  word-break: normal;
}

.ap-metric-val {
  font-size: clamp(1.15rem, 1.45vw, 1.54rem);
  font-weight: 900;
  color: #0f172a;
  line-height: 1.12;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.ap-metric-sub {
  font-size: 0.62rem;
  font-weight: 700;
  color: #94a3b8;
  margin-top: 0.18rem;
  line-height: 1;
}

.ap-metric-pct-val,
.ap-metric-gap-val {
  font-size: clamp(1.15rem, 1.45vw, 1.54rem);
  font-weight: 900;
  line-height: 1.12;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
  white-space: nowrap;
}

/* Flat Status Colors */
.text-green-flat { color: #15803d !important; }
.text-amber-flat { color: #d97706 !important; }
.text-red-flat { color: #dc2626 !important; }
.text-muted-flat { color: #64748b !important; }

/* Weekly Prognosa Strip */
.ap-prognosa-strip {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0;
  margin-top: 0.85rem;
  padding: 0.65rem 0.5rem;
  border-radius: 12px;
  border: 1.5px solid #bfdbfe;
  border-left: 4.5px solid #0857c3;
  background: linear-gradient(135deg, #f8fbff 0%, #eff6ff 100%);
  box-shadow: 0 2px 6px rgba(8, 87, 195, 0.04);
}

.area6-card-premium[data-metric="tabungan"] .ap-prognosa-strip {
  border-color: #bfdbfe;
  border-left-color: #0857c3;
  background: linear-gradient(135deg, #f8fbff 0%, #eff6ff 100%);
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
  padding: 0 0.4rem;
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
  font-size: 0.64rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  line-height: 1.25;
  text-align: center;
  min-height: 2.2em;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ap-prognosa-value {
  margin-top: 0.15rem;
  color: #0f172a;
  font-size: clamp(0.95rem, 1.2vw, 1.18rem);
  font-weight: 900;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
  line-height: 1.1;
  text-align: center;
  white-space: nowrap;
}

.ap-prognosa-unit {
  margin-top: 0.15rem;
  color: #64748b;
  font-size: 0.6rem;
  font-weight: 700;
  line-height: 1.2;
  text-align: center;
}

/* Dashed Divider */
.ap-dashed-divider {
  border: 0;
  border-top: 1px dashed #cbd5e1;
  margin: 0.85rem 0 0.75rem;
}

/* Row 3 - Deltas */
.ap-deltas {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.4rem;
  margin-top: auto;
}

.ap-delta-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  padding: 0.45rem 0.2rem;
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
  font-size: 0.62rem;
  font-weight: 850;
  color: #475569;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 0.2rem;
  line-height: 1;
}

.ap-delta-val {
  font-size: clamp(0.68rem, 0.85vw, 0.82rem);
  font-weight: 900;
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.ap-delta-arrow {
  font-size: 0.7rem;
  margin-top: 0.15rem;
  line-height: 1;
}

/* ── COMPACT BRANCHES BREAKDOWN TABLE ── */
.simpanan-table-card {
  margin: 1.25rem 0 1.5rem;
  background: #ffffff;
  border: 1.5px solid var(--c-border);
  box-shadow: var(--shadow-md);
  border-radius: var(--r-xl);
  overflow: hidden;
}

.st-head {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.85rem 1.35rem;
  background: #ffffff;
  border-bottom: 1.5px solid #e2e8f0;
}

.st-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  box-shadow: 0 2px 6px rgba(8, 87, 195, 0.2);
}

.st-title {
  font-size: 1.05rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -0.01em;
}

.simpanan-table-wrapper {
  overflow-x: auto;
}

.simpanan-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
  text-align: left;
}

.simpanan-table th {
  background: #f8fafc;
  color: #334155;
  font-weight: 800;
  padding: 0.7rem 1rem;
  border-bottom: 1.5px solid #cbd5e1;
  white-space: nowrap;
  text-transform: uppercase;
  font-size: 0.7rem;
  letter-spacing: 0.03em;
}

.simpanan-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  color: #1e293b;
  font-weight: 600;
  white-space: nowrap;
}

.simpanan-table tr:hover td {
  background: #f8fafc;
}

.simpanan-table .num {
  text-align: right;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
}

@media (max-width: 992px) {
  .area6-card-grid--three {
    grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
  }
  .simpanan-hero__content {
    flex-direction: column;
    align-items: flex-start;
  }
  .simpanan-hero__visual {
    width: 100%;
    justify-content: center;
    margin-top: 0.5rem;
  }
  .area6-head {
    flex-direction: column;
    align-items: flex-start;
  }
  .area6-scope-toggle {
    width: 100%;
    overflow-x: auto;
  }
}
</style>

<div class="db-shell">
  {{-- EXECUTIVE HERO COMMAND BANNER --}}
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
          Monitoring terpadu keragaan portofolio Tabungan, Deposito, dan Giro serta evaluasi Prognosa Mingguan.
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

      {{-- HERO RIGHT: COMPACT PROPORTIONAL SVG ILLUSTRATION --}}
      <div class="simpanan-hero__visual" aria-hidden="true">
        <svg viewBox="0 0 240 120" fill="none" xmlns="http://www.w3.org/2000/svg" class="simpanan-hero__svg">
          <defs>
            <linearGradient id="vaultGradSmall" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#1e3a8a" />
              <stop offset="100%" stop-color="#071b3b" />
            </linearGradient>
            <linearGradient id="doorGradSmall" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#38bdf8" />
              <stop offset="100%" stop-color="#0284c7" />
            </linearGradient>
            <linearGradient id="goldGradSmall" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#fde047" />
              <stop offset="100%" stop-color="#ca8a04" />
            </linearGradient>
            <filter id="glowSmall" x="-20%" y="-20%" width="140%" height="140%">
              <feGaussianBlur stdDeviation="6" result="blur" />
              <feComposite in="SourceGraphic" in2="blur" operator="over" />
            </filter>
          </defs>

          <!-- Ambient Platform Glow -->
          <ellipse cx="140" cy="105" rx="80" ry="12" fill="#0284c7" opacity="0.22" filter="url(#glowSmall)" />
          <ellipse cx="140" cy="102" rx="72" ry="10" fill="#0b2347" stroke="#38bdf8" stroke-width="1" stroke-opacity="0.4" />

          <!-- Dynamic Financial Trend Line -->
          <path d="M 20 85 Q 50 70, 85 55 T 140 35 T 200 22 T 230 15" fill="none" stroke="url(#doorGradSmall)" stroke-width="2.5" stroke-linecap="round" />
          <path d="M 20 85 Q 50 70, 85 55 T 140 35 T 200 22 T 230 15 L 230 102 L 20 102 Z" fill="url(#doorGradSmall)" opacity="0.08" />

          <!-- Modern Digital Vault Isometric -->
          <g>
            <rect x="90" y="38" width="95" height="66" rx="12" fill="url(#vaultGradSmall)" stroke="#38bdf8" stroke-width="1.5" stroke-opacity="0.6" />
            <rect x="96" y="44" width="83" height="54" rx="8" fill="#051630" />

            <!-- Vault Door -->
            <circle cx="138" cy="71" r="22" fill="url(#doorGradSmall)" stroke="#7dd3fc" stroke-width="2" />
            <circle cx="138" cy="71" r="16" fill="#0c2340" stroke="#38bdf8" stroke-width="1" />
            <line x1="138" y1="55" x2="138" y2="87" stroke="#f8fafc" stroke-width="2.5" stroke-linecap="round" />
            <line x1="122" y1="71" x2="154" y2="71" stroke="#f8fafc" stroke-width="2.5" stroke-linecap="round" />
            <circle cx="138" cy="71" r="5" fill="url(#goldGradSmall)" stroke="#ffffff" stroke-width="1" />

            <!-- Status LEDs -->
            <circle cx="106" cy="52" r="2.5" fill="#22c55e" filter="url(#glowSmall)" />
            <circle cx="114" cy="52" r="2.5" fill="#38bdf8" />
          </g>

          <!-- Golden Coins Stack -->
          <g transform="translate(-10, 5)">
            <ellipse cx="70" cy="98" rx="14" ry="4.5" fill="#a16207" />
            <rect x="56" y="90" width="28" height="8" fill="url(#goldGradSmall)" />
            <ellipse cx="70" cy="90" rx="14" ry="4.5" fill="#fef08a" stroke="#ca8a04" stroke-width="0.8" />
            <rect x="56" y="82" width="28" height="8" fill="url(#goldGradSmall)" />
            <ellipse cx="70" cy="82" rx="14" ry="4.5" fill="#fef08a" stroke="#ca8a04" stroke-width="0.8" />
            <text x="70" y="85" font-size="6.5" font-weight="900" fill="#854d0e" text-anchor="middle" font-family="Arial">Rp</text>
          </g>

          <!-- Floating Badges -->
          <g transform="translate(160, 10)">
            <rect width="65" height="22" rx="6" fill="#082348" stroke="#38bdf8" stroke-width="1" />
            <circle cx="11" cy="11" r="6" fill="url(#doorGradSmall)" />
            <text x="22" y="10" font-size="6" font-weight="800" fill="#7dd3fc" font-family="'Inter', sans-serif">TABUNGAN</text>
            <text x="22" y="17" font-size="6.5" font-weight="900" fill="#ffffff" font-family="'Inter', sans-serif">REALTIME</text>
          </g>

          <circle cx="45" cy="40" r="1.5" fill="#38bdf8" filter="url(#glowSmall)" />
          <circle cx="195" cy="48" r="1.5" fill="#34d399" filter="url(#glowSmall)" />
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
          <i class="fas fa-calendar-check" style="color:var(--c-blue);"></i>
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

    {{-- TABEL RINCIAN PER CABANG --}}
    @if(!empty($branches))
    <div class="simpanan-table-card">
      <div class="st-head">
        <div class="st-icon"><i class="fas fa-building"></i></div>
        <div>
          <h3 class="st-title">Keragaan Simpanan per Cabang Konsolidasi</h3>
          <div style="font-size:0.75rem;color:#64748b;font-weight:500;">Rincian posisi Total Simpanan, Tabungan, Deposito, Giro, dan CASA per unit kantor cabang.</div>
        </div>
      </div>
      <div class="simpanan-table-wrapper">
        <table class="simpanan-table">
          <thead>
            <tr>
              <th>Cabang</th>
              <th class="num">Total Simpanan</th>
              <th class="num">Share Area</th>
              <th class="num">Tabungan</th>
              <th class="num">Deposito</th>
              <th class="num">Giro</th>
              <th class="num">CASA Total</th>
              <th class="num">Rasio CASA</th>
            </tr>
          </thead>
          <tbody>
            @foreach($branches as $b)
            <tr>
              <td><strong>{{ data_get($b, 'name') }}</strong></td>
              <td class="num">{{ data_get($b, 'simpanan_fmt') }}</td>
              <td class="num"><span style="color:#0857c3;font-weight:800;">{{ data_get($b, 'share_pct_fmt') }}</span></td>
              <td class="num">{{ data_get($b, 'tabungan_fmt') }}</td>
              <td class="num">{{ data_get($b, 'deposito_fmt') }}</td>
              <td class="num">{{ data_get($b, 'giro_fmt') }}</td>
              <td class="num" style="color:#7c3aed;font-weight:900;">{{ data_get($b, 'casa_fmt') }}</td>
              <td class="num"><span style="background:#f5f3ff;border:1px solid #ddd6fe;color:#6d28d9;padding:2px 8px;border-radius:10px;font-weight:900;">{{ data_get($b, 'casa_ratio_fmt') }}</span></td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const scopeButtons = document.querySelectorAll('[data-area6-scope]');
  const scopeContents = document.querySelectorAll('[data-area6-content-scope]');
  const scopeTitle = document.getElementById('simpanan-scope-title');

  scopeButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      const targetScope = this.getAttribute('data-area6-scope');
      scopeButtons.forEach(b => b.classList.remove('active'));
      this.classList.add('active');

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
    });
  });

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

  document.querySelectorAll('[data-prognosa-week-select]').forEach(button => {
    button.addEventListener('click', () => {
      selectLandingPrognosaWeek(button.getAttribute('data-prognosa-week-select'));
    });
  });

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
});
</script>
@endsection
