@extends('layouts.admin')
@section('title', 'A-SIX | Dashboard Simpanan')
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
  $isBranchLanding = ($selectedLandingBranch ?? 'area6') !== 'area6';
  $landingScopeLabel = $landingBranchLabel ?? 'Area 6';
  $landingTotalLabel = 'TOTAL ' . strtoupper($landingScopeLabel);

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
  /* Brand & Vibrant BRI Nusantara Palette (Harmonized with Landing Pinjaman) */
  --c-blue: var(--bri-nusantara, #0857c3);
  --c-blue-d: var(--bri-ink, #053b82);
  --c-blue-l: var(--bri-cakrawala, #307fe2);
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
  --c-surface: var(--app-surface, #ffffff);
  --c-surf: var(--app-surface-soft, #f7faff);
  --c-surf-elevated: #eef4fc;
  --c-border: var(--app-line, #d8e5f7);
  --c-border-strong: var(--app-line-strong, #b9cfee);
  --c-text-main: var(--app-ink, #0b1f3a);
  --c-text-muted: var(--app-muted, #526987);
  --c-text-subtle: #94a3b8;
  
  /* Shadows & Depth */
  --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
  --shadow-sm: 0 2px 4px rgba(15, 23, 42, 0.03), 0 1px 2px rgba(15, 23, 42, 0.02);
  --shadow-md: 0 6px 18px -3px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
  --shadow-lg: 0 16px 32px -4px rgba(15, 23, 42, 0.08), 0 6px 16px -2px rgba(15, 23, 42, 0.04);
  --shadow-hover: 0 20px 36px -6px rgba(8, 87, 195, 0.12), 0 8px 16px -3px rgba(15, 23, 42, 0.06);
  
  /* Border Radii (Identik Pinjaman) */
  --r-sm: 8px;
  --r-md: 12px;
  --r-lg: 16px;
  --r-xl: 16px;
  --r-2xl: 20px;
  --r-pill: 9999px;
}

.db-shell {
  container: simpanan-landing / inline-size;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  padding: 0 0 1.25rem;
  color: var(--c-text-main);
  -webkit-font-smoothing: antialiased;
}

/* ── HEADER (IDENTIK DENGAN LANDING PINJAMAN) ── */
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
  gap: 1.25rem;
  flex-wrap: wrap;
}

.db-header::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #053b82 0%, #0857c3 54%, #71c5e8 100%);
  z-index: 5;
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

/* Buttons */
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
  color: #f8fafc !important;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  min-height: 38px;
  white-space: nowrap;
  text-decoration: none !important;
}

.db-pres-btn:hover {
  background: linear-gradient(135deg, #0857c3 0%, #2563eb 100%);
  color: #ffffff !important;
  transform: translateY(-2px);
  box-shadow: 0 8px 18px -2px rgba(8, 87, 195, 0.45);
}

.db-ppt-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.45rem 1rem;
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
  border: 0;
  border-radius: 12px;
  font-size: 0.72rem;
  font-weight: 800;
  color: #ffffff !important;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(2, 132, 199, 0.3);
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  min-height: 38px;
  white-space: nowrap;
  text-decoration: none !important;
}

.db-ppt-btn:hover {
  background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
  transform: translateY(-2px);
  box-shadow: 0 8px 20px -2px rgba(2, 132, 199, 0.45);
  color: #ffffff !important;
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

/* ── KPI STRIP (IDENTIK DENGAN LANDING PINJAMAN) ── */
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
.kpi-card.casa .kc-icon-box { background: #eff6ff; color: #0857c3; border: 1px solid #bfdbfe; }
.kpi-card.rasio-casa .kc-icon-box { background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; }
.kpi-card.tabungan .kc-icon-box { background: #ecfeff; color: #0284c7; border: 1px solid #a5f3fc; }
.kpi-card.deposito .kc-icon-box { background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; }
.kpi-card.giro .kc-icon-box { background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; }

.kpi-card.simpanan::before { background: linear-gradient(90deg, #059669 0%, #10b981 100%); }
.kpi-card.casa::before { background: linear-gradient(90deg, #0857c3 0%, #3b82f6 100%); }
.kpi-card.rasio-casa::before { background: linear-gradient(90deg, #7c3aed 0%, #a855f7 100%); }
.kpi-card.tabungan::before { background: linear-gradient(90deg, #0284c7 0%, #06b6d4 100%); }
.kpi-card.deposito::before { background: linear-gradient(90deg, #0f766e 0%, #14b8a6 100%); }
.kpi-card.giro::before { background: linear-gradient(90deg, #7c3aed 0%, #6366f1 100%); }

.kpi-card.simpanan .kc-label { color: #059669; }
.kpi-card.casa .kc-label { color: #0857c3; }
.kpi-card.rasio-casa .kc-label { color: #7c3aed; }
.kpi-card.tabungan .kc-label { color: #0284c7; }
.kpi-card.deposito .kc-label { color: #0f766e; }
.kpi-card.giro .kc-label { color: #7c3aed; }

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
  min-height: 32px;
  gap: 0.28rem;
  font-size: 0.66rem;
  font-weight: 800;
  color: var(--c-blue);
  text-decoration: none !important;
  border: 0;
  background: var(--c-blue-subtle);
  border-radius: 8px;
  padding: 0 0.5rem;
  cursor: pointer;
  transition: all 0.18s ease;
}

.kpi-card .kc-link:hover {
  background: var(--c-blue);
  color: #ffffff !important;
  transform: translateY(-1px);
}

.pos { color: #15803d; background: #dcfce7; }
.neg { color: #b91c1c; background: #fee2e2; }
.neu { color: #475569; background: #f1f5f9; }

/* ── AREA 6 PORTFOLIO PANEL (IDENTIK DENGAN LANDING PINJAMAN) ── */
.area6-panel {
  margin: 1.25rem 0;
  background: #ffffff;
  border: 1px solid rgba(191, 204, 224, 0.8);
  box-shadow: 0 24px 55px -42px rgba(12, 43, 93, 0.7);
  border-radius: 20px;
  overflow: visible;
}

.area6-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.25rem;
  padding: 1.25rem 1.65rem;
  background: linear-gradient(110deg, rgba(239, 246, 255, 0.96), rgba(248, 250, 252, 0.96) 55%, rgba(236, 253, 245, 0.72));
  border-bottom: 1px solid rgba(191, 204, 224, 0.72);
}

.area6-title {
  font-size: 1.2rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -0.025em;
  margin: 0;
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
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
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

.area6-scope-btn__icon {
  font-size: 0.82rem;
}

.area6-scope-btn__copy {
  display: flex;
  flex-direction: column;
  text-align: left;
  line-height: 1.15;
}

.area6-scope-btn__copy strong {
  font-size: 0.76rem;
  font-weight: 800;
}

.area6-scope-btn__copy small {
  font-size: 0.62rem;
  font-weight: 600;
  opacity: 0.85;
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
  color: #0857c3;
  font-size: 0.74rem;
}

/* ── PROGNOSA WEEK TOOLBAR ── */
.ap-week-toolbar {
  padding: 0.75rem 1.65rem;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
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
  color: var(--c-blue);
}

.ap-week-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  padding: 0.22rem;
  border-radius: 12px;
}

.ap-week-btn {
  border: 1px solid transparent;
  background: transparent;
  border-radius: 8px;
  padding: 0.32rem 0.85rem;
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
  color: #0857c3;
  background: #eff6ff;
}

.ap-week-btn.active {
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%);
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

/* ── PREMIUM AREA 6 CARDS (IDENTIK DENGAN LANDING PINJAMAN) ── */
.area6-card-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1.15rem;
  padding: 1.5rem;
}

.area6-card-grid--three {
  display: grid !important;
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  gap: 1.15rem;
  padding: 1.5rem;
}

.area6-card-grid--four {
  display: grid !important;
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  gap: 1.15rem;
  padding: 1.5rem;
}

.area6-card-premium {
  border: 1.5px solid rgba(191, 204, 224, 0.78);
  appearance: none;
  width: 100%;
  padding: 0;
  text-align: left;
  background: #ffffff;
  position: relative;
  overflow: visible;
  display: flex;
  flex-direction: column;
  transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.22s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
  border-radius: var(--r-xl);
  box-shadow: 0 16px 36px -30px rgba(8, 42, 90, 0.75);
  font-family: inherit;
}

.area6-card-premium:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 38px -18px rgba(8, 67, 150, 0.28);
  border-color: #cbd5e1;
}

.area6-card-premium[data-metric="tabungan"]:hover {
  border-color: #93c5fd;
  box-shadow: 0 20px 36px -6px rgba(8, 87, 195, 0.16), 0 8px 16px -3px rgba(15, 23, 42, 0.06);
}

.area6-card-premium[data-metric="deposito"]:hover {
  border-color: #5eead4;
  box-shadow: 0 20px 36px -6px rgba(15, 118, 110, 0.16), 0 8px 16px -3px rgba(15, 23, 42, 0.06);
}

.area6-card-premium[data-metric="giro"]:hover {
  border-color: #c4b5fd;
  box-shadow: 0 20px 36px -6px rgba(124, 58, 237, 0.16), 0 8px 16px -3px rgba(15, 23, 42, 0.06);
}

.area6-card-premium[data-metric="casa"]:hover {
  border-color: #a5b4fc;
  box-shadow: 0 20px 36px -6px rgba(79, 70, 229, 0.18), 0 8px 16px -3px rgba(15, 23, 42, 0.06);
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
.ap-header.bg-tabungan, .ap-badge.bg-tabungan {
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%) !important;
}
.ap-header.bg-deposito, .ap-badge.bg-deposito {
  background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%) !important;
}
.ap-header.bg-giro, .ap-badge.bg-giro {
  background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%) !important;
}
.ap-header.bg-casa, .ap-badge.bg-casa {
  background: linear-gradient(135deg, #4338ca 0%, #6366f1 100%) !important;
}

/* Floating Icon Badge (Identik dengan Pinjaman: top -14px, 46x46px, 3px white border) */
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
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
}

.text-green-flat { color: #15803d; }
.text-red-flat { color: #dc2626; }
.text-muted-flat { color: #64748b; }

.ap-prognosa-strip {
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.55rem 0.75rem;
  margin-top: 0.65rem;
  text-align: center;
}

.ap-prognosa-col {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.ap-prognosa-col:first-child {
  border-right: 1px solid #e2e8f0;
}

.ap-prognosa-label {
  font-size: 0.62rem;
  font-weight: 750;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.ap-prognosa-val {
  font-size: 1.05rem;
  font-weight: 900;
  color: #0f172a;
  margin-top: 0.15rem;
  font-variant-numeric: tabular-nums;
}

/* Deltas Strip */
.ap-deltas-strip {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.45rem;
  margin-top: 0.75rem;
  padding-top: 0.65rem;
  border-top: 1px solid #f1f5f9;
}

.ap-delta-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
}

.ap-delta-label {
  font-size: 0.58rem;
  font-weight: 800;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.ap-delta-val {
  font-size: 0.78rem;
  font-weight: 850;
  margin-top: 0.1rem;
  font-variant-numeric: tabular-nums;
}

.ap-card-footer {
  margin-top: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.ap-card-footer a {
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--c-blue);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  transition: all 0.18s ease;
}

.ap-card-footer a:hover {
  color: var(--c-blue-d);
  transform: translateX(2px);
}

/* ── SEKAT CONTAINERS (IDENTIK DENGAN LANDING PINJAMAN) ── */
.asc-card,
.ssc-card {
  margin: 1.25rem 0;
  background: #ffffff;
  border: 1px solid rgba(191, 204, 224, 0.8);
  box-shadow: 0 16px 36px -28px rgba(12, 43, 93, 0.45);
  border-radius: 20px;
  overflow: hidden;
  transition: box-shadow 0.22s ease, border-color 0.2s ease;
}

.asc-card:hover,
.ssc-card:hover {
  border-color: #cbd5e1;
  box-shadow: 0 20px 42px -24px rgba(8, 87, 195, 0.18);
}

.asc-header,
.ssc-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.25rem;
  padding: 1.15rem 1.65rem;
  background: linear-gradient(110deg, rgba(239, 246, 255, 0.92), rgba(248, 250, 252, 0.96) 60%, rgba(241, 245, 249, 0.8));
  border-bottom: 1px solid rgba(191, 204, 224, 0.72);
  flex-wrap: wrap;
}

.asc-header-left,
.ssc-head-left {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.asc-header-icon,
.ssc-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
  flex-shrink: 0;
  background: linear-gradient(135deg, #0857c3 0%, #2563eb 100%);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(8, 87, 195, 0.3);
}

.ssc-icon--monthly {
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
  box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
}

.asc-header-title,
.ssc-title {
  font-size: 1.08rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -0.02em;
  margin: 0;
  line-height: 1.25;
}

.asc-header-subtitle {
  font-size: 0.72rem;
  font-weight: 550;
  color: #64748b;
  margin-top: 0.15rem;
}

.asc-header-actions,
.ssc-head-actions {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

/* ── TABLE WRAPPERS & STYLING (IDENTIK LANDING PINJAMAN) ── */
.dc-table-wrapper,
.sdd-table-wrap,
.payroll-branch-table-container {
  border-radius: 14px;
  border: 1px solid var(--c-border);
  overflow-x: auto;
  overflow-y: hidden;
  background: #ffffff;
  box-shadow: var(--shadow-xs);
}

.dc-table thead th,
.sdd-table thead th,
.table.payroll-branch-table thead th,
.payroll-branch-table thead th {
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%) !important;
  color: #ffffff !important;
  font-size: 0.72rem;
  font-weight: 850;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0.75rem 0.9rem;
  border: 1px solid rgba(255, 255, 255, 0.12);
  white-space: nowrap;
}

.dc-table tbody tr:hover,
.sdd-table tbody tr:hover,
.payroll-branch-row:hover {
  background-color: #eff6ff !important;
}

/* ── SEKAT 3: DIGITAL CHANNEL TABS ── */
.dc-nav {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.65rem 1.65rem;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  overflow-x: auto;
}

.dc-nav-tab {
  border: 1px solid transparent;
  background: transparent;
  border-radius: 10px;
  padding: 0.42rem 0.95rem;
  font-weight: 800;
  font-size: 0.74rem;
  color: #475569;
  cursor: pointer;
  transition: all 0.18s ease;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.dc-nav-tab:hover {
  color: #0857c3;
  background: #eff6ff;
}

.dc-nav-tab.active {
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%);
  color: #ffffff;
  box-shadow: 0 3px 8px rgba(8, 87, 195, 0.28);
}

/* ── SEKAT 4: CASA DEBITUR & DORMANT ── */
.sdd-toggle-wrap {
  display: inline-flex;
  gap: 0.25rem;
  padding: 0.22rem;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}

.sdd-toggle-btn {
  border: 0;
  min-height: 34px;
  padding: 0.35rem 0.95rem;
  background: transparent;
  color: #475569;
  font-size: 0.74rem;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.18s ease;
  border-radius: 9px;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.sdd-toggle-btn.active {
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%);
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(8, 87, 195, 0.25);
}

.sdd-stat-strip {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
}

.sdd-stat-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 0.75rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.sdd-stat-box.highlight {
  background: #eff6ff;
  border-color: #bfdbfe;
}

.sdd-stat-label {
  font-size: 0.64rem;
  font-weight: 800;
  color: #64748b;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.sdd-stat-val {
  font-size: 1.12rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -0.02em;
  line-height: 1.15;
}

.sdd-stat-val.text-navy {
  color: #0857c3;
}

/* ── SEKAT 5, 6, 7: PAYROLL, ECOSYSTEM, & PERUSAHAAN ANAK ── */
.payroll-card-body,
.ecosystem-card-body,
.perusahaan-anak-card-body {
  padding: 1.25rem 1.5rem 1.5rem;
}

.payroll-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.85rem;
  margin-bottom: 1.25rem;
}

.payroll-kpi-box {
  background: #ffffff;
  border: 1px solid var(--c-border);
  border-radius: 14px;
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow-xs);
  transition: all 0.2s ease;
}

.payroll-kpi-box:hover {
  border-color: #cbd5e1;
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.payroll-kpi-box::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background: linear-gradient(180deg, #0857c3, #307fe2);
}

.payroll-kpi-box--accent::before {
  background: linear-gradient(180deg, #059669, #10b981);
}

.payroll-kpi-label {
  font-size: 0.66rem;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 0.25rem;
}

.payroll-kpi-value {
  font-size: 1.35rem;
  font-weight: 900;
  color: #0f172a;
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

.eco-pillar-strip {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}

.eco-pillar-chip {
  background: #ffffff;
  border: 1px solid var(--c-border);
  border-radius: 12px;
  padding: 0.75rem 0.95rem;
  display: flex;
  flex-direction: column;
  transition: all 0.2s ease;
  box-shadow: var(--shadow-xs);
}

.eco-pillar-chip:hover {
  background: #ffffff;
  border-color: #93c5fd;
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
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
  font-size: 1.05rem;
  font-weight: 900;
  color: #0857c3;
  line-height: 1.2;
}

.eco-pillar-chip__sub {
  font-size: 0.65rem;
  font-weight: 600;
  color: #94a3b8;
  margin-top: 0.1rem;
}

.btn-payroll-detail {
  font-size: 0.74rem;
  font-weight: 800;
  border-radius: 8px;
  padding: 0.35rem 0.75rem;
  border: 1.5px solid #bfdbfe;
  color: #0857c3;
  background: #eff6ff;
  transition: all 0.18s ease;
  white-space: nowrap;
  cursor: pointer;
}

.btn-payroll-detail:hover {
  background: #0857c3;
  color: #ffffff !important;
  border-color: #0857c3;
  transform: translateY(-1px);
}

/* ── MODALS (IDENTIK DENGAN EXECUTIVE DIALOGS PINJAMAN) ── */
.modal-payroll-nominatif .modal-content {
  border-radius: 20px;
  overflow: hidden;
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-lg);
}

.modal-payroll-nominatif .modal-header {
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%) !important;
  color: #ffffff;
  padding: 1.1rem 1.5rem !important;
  border-bottom: 0;
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
  font-weight: 750;
  padding: 0.28rem 0.75rem;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
  min-height: 34px;
}

.modal-tab-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.modal-tab-btn.active {
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%);
  color: #ffffff;
  border-color: #0857c3;
  box-shadow: 0 2px 6px rgba(8, 87, 195, 0.25);
  font-weight: 800;
}

.badge-kc-pill {
  display: inline-block;
  font-size: 0.74rem;
  font-weight: 800;
  padding: 0.25rem 0.65rem;
  border-radius: 8px;
  background: #eff6ff;
  color: #0857c3;
  border: 1px solid #bfdbfe;
  white-space: nowrap;
}

.badge-visit {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.7rem;
  font-weight: 800;
  padding: 0.22rem 0.55rem;
  border-radius: 6px;
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

@media (max-width: 1200px) {
  .eco-pillar-strip {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (min-width: 992px) and (max-width: 1399.98px) {
  .area6-card-grid,
  .area6-card-grid--four {
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
  .kpi-strip {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  .area6-card-grid,
  .area6-card-grid--three,
  .area6-card-grid--four {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  }
  .payroll-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 767.98px) {
  .db-shell {
    padding: 0.25rem;
  }
  .db-header {
    border-radius: 16px;
  }
  .kpi-strip {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .area6-card-grid,
  .area6-card-grid--three,
  .area6-card-grid--four {
    grid-template-columns: 1fr !important;
  }
  .area6-head {
    flex-direction: column;
    align-items: stretch;
  }
  .area6-head-actions {
    align-items: flex-start;
  }
  .payroll-kpi-grid {
    grid-template-columns: 1fr;
  }
  .eco-pillar-strip {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
@include('dashboard.partials.simpanan-landing-styles')
@include('dashboard.partials.simpanan-section-styles')

<div class="db-shell">
  {{-- EXECUTIVE HEADER (HARMONIZED WITH LANDING PINJAMAN) --}}
    {{-- EXECUTIVE HEADER (IDENTIK DENGAN LANDING PINJAMAN) --}}
  <div class="db-header">
    <div class="db-brand">
      <div class="db-logo">
        <img src="{{ asset('images/a-six-logo.svg') }}" alt="A-SIX">
      </div>
      <div>
        <div class="db-title">A-SIX {{ $landingBranchLabel ?? 'Area 6' }} — Dashboard Simpanan</div>
        <div class="db-subtitle">Ringkasan posisi simpanan, pencapaian RKA, dan strategi penghimpunan dana</div>
      </div>
    </div>

    <div class="db-meta">
      @if(!empty($landingBranchOptions))
      <label class="db-branch-picker" title="{{ !empty($landingBranchLocked) ? 'Cabang dikunci sesuai wilayah user' : 'Ubah lingkup cabang' }}">
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
      <div class="db-date-picker-container" title="Pilih tanggal posisi">
        <select class="db-date-picker-select" id="periode-selector" data-applied-period="{{ $selectedPeriod }}">
          @foreach($periods as $p)
            <option value="{{ $p }}" {{ $p === $selectedPeriod ? 'selected' : '' }}>
              {{ \Carbon\Carbon::parse($p)->translatedFormat('d M Y') }}
            </option>
          @endforeach
        </select>
        <i class="fas fa-calendar-alt db-date-picker-icon"></i>
      </div>
      @endif

      <a href="{{ route('dashboard.presentation', ['periode' => $selectedPeriod]) }}" class="db-pres-btn" id="enter-presentation-btn">
        <i class="fas fa-desktop"></i> Mode Presentasi
      </a>

      <a href="{{ route('report.dashboard-dana') }}" class="db-ppt-btn">
        <i class="fas fa-chart-line"></i> Dashboard Dana
      </a>

      <span class="db-meta-chip">
        <i class="fas fa-circle" style="color:#4ade80;font-size:.5rem;"></i> Live Snapshot
      </span>
      <span class="db-now" id="db-clock"></span>
    </div>
  </div>

  {{-- KPI STRIP: Total Simpanan | Saldo CASA | Rasio CASA | Tabungan | Deposito | Giro (IDENTIK PINJAMAN) --}}
  <div class="kpi-strip">
    @php
      $kpiSummary = data_get($dashboard ?? [], 'kpi_summary', []);
      $totKpi = data_get($kpiSummary, 'total_simpanan', []);
      $casaKpi = data_get($kpiSummary, 'casa', []);
      $rootCards = data_get($area6Portfolio, 'cards', []);
      $tabKpiCard = collect($rootCards)->firstWhere('key', 'tabungan') ?? [];
      $depKpiCard = collect($rootCards)->firstWhere('key', 'deposito') ?? [];
      $giroKpiCard = collect($rootCards)->firstWhere('key', 'giro') ?? [];

      $totDeltaMtm = (string) data_get($totKpi, 'trend_mtm', '0%');
      $totDeltaPos = !str_starts_with($totDeltaMtm, '-') && !str_starts_with($totDeltaMtm, '(');

      $tabDeltaMtm = (string) data_get($tabKpiCard, 'deltas.mom.value', '0%');
      $tabDeltaPos = !str_starts_with($tabDeltaMtm, '-') && !str_starts_with($tabDeltaMtm, '(');

      $depDeltaMtm = (string) data_get($depKpiCard, 'deltas.mom.value', '0%');
      $depDeltaPos = !str_starts_with($depDeltaMtm, '-') && !str_starts_with($depDeltaMtm, '(');

      $giroDeltaMtm = (string) data_get($giroKpiCard, 'deltas.mom.value', '0%');
      $giroDeltaPos = !str_starts_with($giroDeltaMtm, '-') && !str_starts_with($giroDeltaMtm, '(');
    @endphp

    {{-- CARD 1: TOTAL SIMPANAN --}}
    <div class="kpi-card simpanan">
      <div class="kc-live"></div>
      <div class="kc-label"><span class="kc-icon-box"><i class="fas fa-piggy-bank"></i></span>Total Simpanan</div>
      <div class="kc-val">{{ data_get($totKpi, 'value', '0') }} <small style="font-size:0.75rem;font-weight:700;color:#64748b;">Rp Jt</small></div>
      <div class="kc-sub">Target {{ data_get($totKpi, 'target', '-') }} Rp Jt ({{ data_get($totKpi, 'pct', '0%') }})</div>
      <span class="kc-delta {{ $totDeltaPos ? 'pos' : 'neg' }}">
        <i class="fas {{ $totDeltaPos ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
        {{ $totDeltaMtm }} MtM
      </span>
      <a href="{{ route('report.dashboard-dana') }}" class="kc-link">Detail <i class="fas fa-info-circle"></i></a>
    </div>

    {{-- CARD 2: SALDO CASA --}}
    <div class="kpi-card casa">
      <div class="kc-live"></div>
      <div class="kc-label"><span class="kc-icon-box"><i class="fas fa-wallet"></i></span>Saldo CASA</div>
      <div class="kc-val">{{ data_get($casaKpi, 'value', '0') }} <small style="font-size:0.75rem;font-weight:700;color:#64748b;">Rp Jt</small></div>
      <div class="kc-sub">Tabungan + Giro &middot; {{ $landingScopeLabel }}</div>
      <span class="kc-delta pos">
        <i class="fas fa-shield-alt"></i> Low Cost Funds
      </span>
      <a href="#sekat-rekening-debitur-dormant" class="kc-link">Detail <i class="fas fa-info-circle"></i></a>
    </div>

    {{-- CARD 3: RASIO CASA --}}
    <div class="kpi-card rasio-casa">
      <div class="kc-label"><span class="kc-icon-box"><i class="fas fa-chart-pie"></i></span>Rasio CASA</div>
      <div class="kc-val">{{ data_get($casaKpi, 'ratio', '0%') }}</div>
      <div class="kc-sub">Tab {{ data_get($casaKpi, 'tabungan_share', '0%') }} &middot; Giro {{ data_get($casaKpi, 'giro_share', '0%') }}</div>
      <span class="kc-delta neu">
        <i class="fas fa-layer-group"></i> Komposisi DPK
      </span>
      <a href="#sekat-trend-posisi" class="kc-link">Detail <i class="fas fa-info-circle"></i></a>
    </div>

    {{-- CARD 4: TABUNGAN --}}
    <div class="kpi-card tabungan">
      <div class="kc-label"><span class="kc-icon-box"><i class="fas fa-coins"></i></span>Tabungan</div>
      <div class="kc-val">{{ data_get($tabKpiCard, 'realization_value', '0') }} <small style="font-size:0.75rem;font-weight:700;color:#64748b;">Rp Jt</small></div>
      <div class="kc-sub">Capaian: {{ data_get($tabKpiCard, 'pct_value', '0%') }} RKA</div>
      <span class="kc-delta {{ $tabDeltaPos ? 'pos' : 'neg' }}">
        <i class="fas {{ $tabDeltaPos ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
        {{ $tabDeltaMtm }} MtM
      </span>
      <a href="#area6-portfolio-panel" class="kc-link">Detail <i class="fas fa-info-circle"></i></a>
    </div>

    {{-- CARD 5: DEPOSITO --}}
    <div class="kpi-card deposito">
      <div class="kc-label"><span class="kc-icon-box"><i class="fas fa-money-check-alt"></i></span>Deposito</div>
      <div class="kc-val">{{ data_get($depKpiCard, 'realization_value', '0') }} <small style="font-size:0.75rem;font-weight:700;color:#64748b;">Rp Jt</small></div>
      <div class="kc-sub">Capaian: {{ data_get($depKpiCard, 'pct_value', '0%') }} RKA</div>
      <span class="kc-delta {{ $depDeltaPos ? 'pos' : 'neg' }}">
        <i class="fas {{ $depDeltaPos ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
        {{ $depDeltaMtm }} MtM
      </span>
      <a href="#area6-portfolio-panel" class="kc-link">Detail <i class="fas fa-info-circle"></i></a>
    </div>

    {{-- CARD 6: GIRO --}}
    <div class="kpi-card giro">
      <div class="kc-label"><span class="kc-icon-box"><i class="fas fa-file-invoice-dollar"></i></span>Giro</div>
      <div class="kc-val">{{ data_get($giroKpiCard, 'realization_value', '0') }} <small style="font-size:0.75rem;font-weight:700;color:#64748b;">Rp Jt</small></div>
      <div class="kc-sub">Capaian: {{ data_get($giroKpiCard, 'pct_value', '0%') }} RKA</div>
      <span class="kc-delta {{ $giroDeltaPos ? 'pos' : 'neg' }}">
        <i class="fas {{ $giroDeltaPos ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
        {{ $giroDeltaMtm }} MtM
      </span>
      <a href="#area6-portfolio-panel" class="kc-link">Detail <i class="fas fa-info-circle"></i></a>
    </div>
  </div>

{{-- AREA 6 PORTFOLIO PANEL --}}
  <section class="area6-panel" id="area6-portfolio-panel">
    <div class="area6-head">
  <div>
    <h2 class="area6-title" id="simpanan-scope-title">Kinerja Simpanan {{ $landingBranchLabel ?? 'Area 6' }}</h2>
    <div class="area6-sub" id="simpanan-scope-subtitle">Ringkasan posisi dan kinerja Tabungan, Deposito, Giro, dan CASA pada lingkup aktif.</div>
  </div>
  <div class="area6-head-actions">
    <div class="landing-scope-stage">
      <div class="area6-scope-toggle" role="group" aria-label="Pilihan lingkup kinerja simpanan">
        @foreach($area6ScopePayloads as $scopeKey => $scopePayload)
          @php
            $pres = $scopePresentation[$scopeKey] ?? ['icon' => 'fa-layer-group', 'label' => strtoupper($scopeKey), 'badge' => 'Portfolio'];
          @endphp
          <button type="button"
                  class="area6-scope-btn {{ $scopeKey === $area6DefaultScope ? 'active' : '' }}"
                  data-area6-scope="{{ $scopeKey }}"
                  aria-pressed="{{ $scopeKey === $area6DefaultScope ? 'true' : 'false' }}"
                  data-scope-title="Kinerja Simpanan {{ data_get($scopePayload, 'label', strtoupper($scopeKey)) }}"
                  data-scope-subtitle="{{ data_get($scopePayload, 'description', '') }}">
            <span class="area6-scope-btn__icon"><i class="fas {{ $pres['icon'] }}"></i></span>
            <span class="area6-scope-btn__copy">
              <strong>{{ data_get($scopePayload, 'label', strtoupper($scopeKey)) }}</strong>
              <small>{{ $pres['badge'] }}</small>
            </span>
          </button>
        @endforeach
      </div>
    </div>
    <div class="area6-periods">
      <span class="area6-pill"><i class="fas fa-calendar-day"></i> Posisi: {{ $periodLabel }}</span>
      <span class="area6-pill"><i class="fas fa-university"></i> Produk: Tabungan, Deposito, Giro, CASA</span>
      <span class="area6-pill"><i class="fas fa-database"></i> Simpanan Harian</span>
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
          <i class="fas fa-calendar-check"></i>
          <span>Prognosa Mingguan</span>
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

      <div class="area6-card-grid {{ count($contentCards) === 3 ? 'area6-card-grid--three' : (count($contentCards) === 4 ? 'area6-card-grid--four' : '') }}">
        @forelse($contentCards as $card)
          @php
            $key = data_get($card, 'key');
            $pctColor = data_get($card, 'pct_color', 'green');
            $gapColor = data_get($card, 'gap_color', 'green');
            $deltas = data_get($card, 'deltas', []);
          @endphp
          <div class="area6-card-premium" data-metric="{{ $key }}">
            <!-- Floating Badge (Identik dengan Pinjaman) -->
            <div class="ap-badge bg-{{ $key }}">
              <i class="{{ data_get($card, 'icon') }}"></i>
            </div>

            <!-- Header Banner (Identik dengan Pinjaman) -->
            <div class="ap-header bg-{{ $key }}">
              <div class="ap-header-title">{{ data_get($card, 'header_title') }}</div>
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
          <div>
            <h3 class="asc-header-title">TREND POSISI SIMPANAN</h3>
            <div class="asc-header-subtitle">Historis posisi saldo Tabungan, Deposito, dan Giro (Rp Juta)</div>
          </div>
        </div>
        <div class="asc-header-actions">
          <span class="area6-pill"><i class="fas fa-coins"></i> Evaluasi Saldo DPK</span>
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
              <path id="svg-path-giro" d="{{ $giroPath }}" fill="none" stroke="#0284c7" stroke-width="1.8" />
              @foreach($giroPoints as $idx => $pt)
                <text id="svg-text-giro-{{ $idx }}" x="{{ $pt['x'] }}" y="{{ $pt['y'] - 6 }}" text-anchor="middle" font-size="3.8" font-weight="bold" fill="#0284c7" font-family="inherit">{{ $pt['val_fmt'] }}</text>
                <circle id="svg-circle-giro-{{ $idx }}" cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="2.8" fill="#0284c7" stroke="#ffffff" stroke-width="1" />
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
            <h3 class="ssc-title">Tren Simpanan Bulanan</h3>
            <div class="asc-header-subtitle">Pergerakan saldo akhir bulan dan delta penutupan</div>
          </div>
        </div>
        <div class="scc-monthly-controls">
          @if($isBranchLanding)
            <div class="scc-segment-toggle" role="group" aria-label="Segmen timeseries simpanan">
              <button type="button" class="scc-segment-btn active" data-monthly-segment="ritel" aria-pressed="true">Ritel</button>
              <button type="button" class="scc-segment-btn" data-monthly-segment="micro" aria-pressed="false">Mikro</button>
            </div>
          @endif
          <div class="scc-monthly-legend">
            @if($isBranchLanding)
              <span class="scc-leg-chip"><span class="scc-leg-dot" style="background:#cbd5e1;"></span>H-2</span>
              <span class="scc-leg-chip"><span class="scc-leg-dot" style="background:#94a3b8;"></span>H-1</span>
              <span class="scc-leg-chip"><span class="scc-leg-dot" style="background:#307fe2;"></span>Posisi akhir</span>
              <span class="scc-leg-chip"><span class="scc-leg-dot" style="background:#10b981;border-radius:50%;"></span>Delta H vs H-1</span>
            @else
              <span class="scc-leg-chip"><span class="scc-leg-dot" style="background:#94a3b8;"></span>H-1 Akhir Bulan</span>
              <span class="scc-leg-chip"><span class="scc-leg-dot" style="background:#307fe2;"></span>Posisi Akhir Bulan</span>
              <span class="scc-leg-chip"><span class="scc-leg-dot" style="background:#10b981;border-radius:50%;"></span>Delta Tutup</span>
            @endif
          </div>
        </div>
      </header>

      <div class="ssc-body">
        <div class="scc-monthly-canvas-wrap">
          <canvas id="simpananMonthlyChart"></canvas>
        </div>
        @if($isBranchLanding)
          <div class="scc-monthly-dates" id="simpananMonthlyDates" aria-live="polite"></div>
        @endif
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
            <div class="asc-header-subtitle">Ringkasan pencapaian EDC, QRIS, CASA Merchant, BRImo, BRILink, dan Qlola</div>
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
                <th>{{ strtoupper(data_get($initialChannel, 'dimension_label', 'Cabang')) }}</th>
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
                    {{ strtoupper(data_get($initialChannel, 'total_label', $landingTotalLabel)) }}
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
            <h3 class="asc-header-title">2. REKENING TRANSAKSI DEBITUR &amp; REKENING DORMANT</h3>
            <div class="asc-header-subtitle">Optimalisasi CASA debitur pinjaman dan aktivasi rekening dormant</div>
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
              <button type="button" class="sdd-toggle-btn active" data-target="casa-table-cabang" aria-pressed="true">{{ data_get($casaDebiturStrategy, 'dimension_label', 'Cabang') }}</button>
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
                  <th>{{ strtoupper(data_get($casaDebiturStrategy, 'dimension_label', 'Cabang')) }}</th>
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
                    <i class="fas fa-calculator text-primary mr-1"></i> {{ strtoupper(data_get($casaDebiturStrategy, 'total_label', $landingTotalLabel)) }}
                  </td>
                  <td class="text-right font-weight-bold">{{ data_get($casaDebiturStrategy, 'total.os_fmt', '-') }}</td>
                  <td class="text-right font-weight-bold text-primary">{{ data_get($casaDebiturStrategy, 'total.casa_fmt', '-') }}</td>
                  <td class="text-right col-highlight font-weight-bold" style="font-size: 0.92rem; color: #0857c3;">
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
                    <i class="fas fa-calculator text-primary mr-1"></i> {{ strtoupper(data_get($casaDebiturStrategy, 'total_label', $landingTotalLabel)) }}
                  </td>
                  <td class="text-right font-weight-bold">{{ data_get($casaDebiturStrategy, 'total.os_fmt', '-') }}</td>
                  <td class="text-right font-weight-bold text-primary">{{ data_get($casaDebiturStrategy, 'total.casa_fmt', '-') }}</td>
                  <td class="text-right col-highlight font-weight-bold" style="font-size: 0.92rem; color: #0857c3;">
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
                  <th>{{ strtoupper(data_get($dormantStrategy, 'dimension_label', 'Cabang')) }}</th>
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
                    <i class="fas fa-calculator text-primary mr-1"></i> {{ strtoupper(data_get($dormantStrategy, 'total_label', $landingTotalLabel)) }}
                  </td>
                  <td class="text-right font-weight-bold">{{ data_get($dormantStrategy, 'total.ytd_fmt', '-') }}</td>
                  <td class="text-right font-weight-bold">{{ data_get($dormantStrategy, 'total.mtd_fmt', '-') }}</td>
                  <td class="text-right col-highlight font-weight-bold" style="font-size: 0.92rem; color: #0857c3;">
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
            <div class="asc-header-subtitle">Pipeline instansi/perusahaan, akuisisi rekening gaji, dan potensi saldo DPK</div>
            <span class="dc-active-channel-badge">PIPELINE FUNDING & CASA</span>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge" style="background: #eff6ff; color: #0857c3; border: 1px solid #bfdbfe; font-size: 0.72rem; font-weight: 700; padding: 0.35rem 0.65rem; border-radius: 6px;">
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
          <span class="payroll-kpi-sub">Lingkup {{ $landingScopeLabel }}</span>
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
        $payrollBranchOrder = array_keys(array_filter($payrollBranches, fn ($branch) => (int) data_get($branch, 'perusahaan', 0) > 0));
      @endphp
      <div class="payroll-branch-table-container">
        <table class="table payroll-branch-table" id="payrollBranchTable">
          <thead>
            <tr>
              <th style="width: 40px;" class="text-center">#</th>
              <th>{{ data_get($payrollQualityStrategy, 'dimension_label', 'Kantor Cabang (KC)') }}</th>
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
                <td class="text-right font-weight-bold" style="color: #0857c3; font-size: 0.88rem;">{{ $bData['potensi_fmt'] ?? '0' }}</td>
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
              <td colspan="2" class="text-center" style="color: #0857c3; font-size: 0.82rem;">
                <i class="fas fa-calculator mr-1"></i> {{ strtoupper(data_get($payrollQualityStrategy, 'total_label', $landingTotalLabel)) }}
              </td>
              <td class="text-center" style="color: #0369a1;">
                {{ data_get($payrollSummary, 'total_perusahaan', 0) }} Instansi
              </td>
              <td class="text-right text-secondary">{{ data_get($payrollSummary, 'total_pegawai_fmt', '0') }}</td>
              <td class="text-right" style="color: #0857c3; font-size: 0.9rem;">{{ data_get($payrollSummary, 'total_potensi_fmt', '0') }}</td>
              <td class="text-right text-success" style="font-size: 0.9rem;">{{ data_get($payrollSummary, 'total_realisasi_fmt', '0') }}</td>
              <td class="text-center" style="font-size: 0.78rem;">
                {{ data_get($payrollSummary, 'total_kunjungan', 0) }} / {{ data_get($payrollSummary, 'total_perusahaan', 0) }} ({{ data_get($payrollSummary, 'persen_kunjungan', 0) }}%)
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-payroll-detail btn-open-nominatif" data-kc="ALL" style="background:#0857c3; color:#ffffff; border-color:#0857c3;">
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
          <h3 class="asc-header-title">4. ECOSYSTEM &amp; VALUE CHAIN</h3>
            <div class="asc-header-subtitle">Pemetaan ekosistem 6 pilar bisnis dan potensi simpanan</div>
          <span class="dc-active-channel-badge">CASA & DEPOSITO EKOSISTEM</span>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge" style="background: #eff6ff; color: #0857c3; border: 1px solid #bfdbfe; font-size: 0.72rem; font-weight: 700; padding: 0.35rem 0.65rem; border-radius: 6px;">
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
          <span class="payroll-kpi-sub">Lingkup {{ $landingScopeLabel }}</span>
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
        $ecoBranchOrder = array_keys(array_filter($ecosystemBranches, fn ($branch) => (int) data_get($branch, 'rekening', 0) > 0));
      @endphp
      <div class="payroll-branch-table-container">
        <table class="table payroll-branch-table ecosystem-branch-table mb-0" id="ecosystemBranchTable">
          <thead>
            <tr>
              <th style="width: 40px;" class="text-center">#</th>
              <th>{{ data_get($ecosystemStrategy, 'dimension_label', 'Kantor Cabang (KC)') }}</th>
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
                <td class="text-right font-weight-bold" style="color: #0857c3; font-size: 0.88rem;">{{ $eb['saldo_fmt'] ?? 'Rp 0' }}</td>
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
                <i class="fas fa-calculator text-primary mr-1"></i> {{ strtoupper(data_get($ecosystemStrategy, 'total_label', $landingTotalLabel)) }}
              </td>
              <td class="text-center font-weight-bold text-primary" style="font-size: 0.84rem;">
                {{ data_get($ecosystemSummary, 'total_accounts_fmt', '2.069') }} Rekening
              </td>
              <td class="text-right font-weight-bold" style="color: #0857c3; font-size: 0.95rem;">
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
                <button type="button" class="btn btn-payroll-detail btn-open-ecosystem-nominatif" data-kc="ALL" style="background:#0857c3; color:#ffffff; border-color:#0857c3;">
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
            <div class="asc-header-subtitle">Kolaborasi mitra anak perusahaan BRI Group dan penyerapan simpanan</div>
          <span class="dc-active-channel-badge">SINERGI PERUSAHAAN ANAK</span>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge" style="background: #eff6ff; color: #0857c3; border: 1px solid #bfdbfe; font-size: 0.72rem; font-weight: 700; padding: 0.35rem 0.65rem; border-radius: 6px;">
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
          <span class="payroll-kpi-sub">Lingkup {{ $landingScopeLabel }}</span>
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
          <span class="payroll-kpi-value" style="color: #0857c3;">{{ data_get($perusahaanAnakSummary, 'total_saldo_september_fmt', 'Rp 0') }}</span>
          <span class="payroll-kpi-sub">Posisi 6 September 2026</span>
        </div>
      </div>

      <!-- Tabel Konsolidasi Cabang Perusahaan Anak (Area 6 Madiun) -->
      @php
        $paBranches = (array) data_get($perusahaanAnakSummary, 'branches', []);
        $paBranchOrder = array_keys(array_filter($paBranches, fn ($branch) => (int) data_get($branch, 'total_pipeline', 0) > 0));
      @endphp
      <div class="payroll-branch-table-container">
        <table class="table payroll-branch-table mb-0" id="perusahaanAnakBranchTable">
          <thead>
            <tr>
              <th style="width: 40px;" class="text-center">#</th>
              <th>{{ data_get($perusahaanAnakStrategy, 'dimension_label', 'Kantor Cabang (KC)') }}</th>
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
                <td class="text-right font-weight-bold" style="color: #0857c3; font-size: 0.88rem;">{{ $pb['saldo_september_fmt'] ?? 'Rp 0' }}</td>
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
              <td colspan="2" class="text-center" style="color: #0857c3; font-size: 0.82rem;">
                <i class="fas fa-calculator text-primary mr-1"></i> {{ strtoupper(data_get($perusahaanAnakStrategy, 'total_label', $landingTotalLabel)) }}
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
              <td class="text-right" style="color: #0857c3; font-size: 0.92rem;">
                {{ data_get($perusahaanAnakSummary, 'total_saldo_september_fmt', 'Rp 0') }}
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-payroll-detail btn-open-pa-nominatif" data-kc="ALL" style="background:#0857c3; color:#ffffff; border-color:#0857c3;">
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
  window.simpananMonthlyBranchMode = @json($isBranchLanding);
  window.simpananMonthlySelectedSegment = 'ritel';

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
      const scopeSubtitle = document.getElementById('simpanan-scope-subtitle');
      if (scopeSubtitle && this.getAttribute('data-scope-subtitle')) {
        scopeSubtitle.textContent = this.getAttribute('data-scope-subtitle');
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
      if (!window.simpananMonthlyBranchMode && window.simpananMonthlyChartInstance && window.simpananMonthlyDataByScope && window.simpananMonthlyDataByScope[targetScope]) {
        const mData = window.simpananMonthlyDataByScope[targetScope];
        window.simpananMonthlyChartInstance.data.labels = mData.labels || [];
        window.simpananMonthlyChartInstance.data.datasets[1].data = mData.h1_values || [];
        window.simpananMonthlyChartInstance.data.datasets[2].data = mData.h_values || [];
        window.simpananMonthlyChartInstance.data.datasets[3].data = mData.deltas || [];
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
          <i class="fas fa-calculator text-primary mr-1"></i>
          ${(data.total_label || 'TOTAL {{ strtoupper($landingScopeLabel) }}').toUpperCase()}
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

    const initialMonthlyScope = window.simpananMonthlyBranchMode ? 'ritel' : 'area6';
    const initMonthlyData = window.simpananMonthlyDataByScope ? (window.simpananMonthlyDataByScope[initialMonthlyScope] || {}) : {};
    const ctx = monthlyCanvas.getContext('2d');

    window.simpananMonthlyChartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: (initMonthlyData.labels && initMonthlyData.labels.length > 0) ? initMonthlyData.labels : ['Mar 26', 'Apr 26', 'Mei 26', 'Jun 26', 'Jul 26', 'Agt 26', 'Sep 26'],
        datasets: [
          {
            type: 'bar',
            label: 'H-2',
            data: window.simpananMonthlyBranchMode && initMonthlyData.h2_values ? initMonthlyData.h2_values : [],
            backgroundColor: '#cbd5e1',
            borderRadius: 6,
            barPercentage: 0.65,
            categoryPercentage: 0.55,
            yAxisID: 'y',
            hidden: !window.simpananMonthlyBranchMode
          },
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
                const scopeKey = window.simpananMonthlyBranchMode ? window.simpananMonthlySelectedSegment : 'area6';
                const item = window.simpananMonthlyDataByScope?.[scopeKey]?.items?.[context.dataIndex] || {};
                const dateKey = context.datasetIndex === 0 ? 'h2_date' : (context.datasetIndex === 1 ? 'h1_date' : (context.datasetIndex === 2 ? 'h_date' : null));
                if (window.simpananMonthlyBranchMode && dateKey && item[dateKey]) {
                  label += ' (' + item[dateKey] + ')';
                }
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

  function updateMonthlyChartForSegment(segment) {
    const chart = window.simpananMonthlyChartInstance;
    const data = window.simpananMonthlyDataByScope?.[segment];
    if (!chart || !data) return;

    window.simpananMonthlySelectedSegment = segment;
    chart.data.labels = data.labels || [];
    chart.data.datasets[0].data = data.h2_values || [];
    chart.data.datasets[1].data = data.h1_values || [];
    chart.data.datasets[2].data = data.h_values || [];
    chart.data.datasets[3].data = data.deltas || [];
    chart.update();
    renderMonthlyPositionDates(data);
  }

  function renderMonthlyPositionDates(data) {
    const container = document.getElementById('simpananMonthlyDates');
    if (!container) return;

    container.innerHTML = (data.items || []).map(item => `
      <div class="scc-monthly-date-card">
        <strong>${item.month || '-'}</strong>
        H-2: ${item.h2_date || '-'}<br>
        H-1: ${item.h1_date || '-'}<br>
        Posisi akhir: ${item.h_date || '-'}
      </div>
    `).join('');
  }

  document.querySelectorAll('[data-monthly-segment]').forEach(button => {
    button.addEventListener('click', function() {
      document.querySelectorAll('[data-monthly-segment]').forEach(item => {
        const active = item === this;
        item.classList.toggle('active', active);
        item.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      updateMonthlyChartForSegment(this.getAttribute('data-monthly-segment'));
    });
  });

  // Start Chart initialization
  initSimpananMonthlyChart();
  if (window.simpananMonthlyBranchMode) {
    renderMonthlyPositionDates(window.simpananMonthlyDataByScope?.ritel || {});
  }

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

  // Live snapshot clock updater
  function updateSimpananClock() {
    const clockEl = document.getElementById('db-clock');
    if (!clockEl) return;
    const now = new Date();
    clockEl.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
  }
  setInterval(updateSimpananClock, 1000);
  updateSimpananClock();

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
            <td class="text-right font-weight-bold" style="color: #0857c3; font-size: 0.85rem;">${saldoFmt}</td>
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
      phtml += `<button type="button" class="btn btn-sm ${active ? 'btn-primary' : 'btn-outline-secondary'} py-0 px-2 font-weight-bold" style="font-size: 0.72rem; ${active ? 'background:#0857c3; border-color:#0857c3;' : ''}" onclick="window.changeEcoPage(${p})">${p}</button>`;
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
        const sepColor = sepVal > 0 ? 'color: #0857c3;' : '';

        html += `
          <tr>
            <td class="text-center font-weight-bold text-muted" style="font-size: 0.72rem;">${idx + 1}</td>
            <td>
              <div class="font-weight-bold text-dark">${r.partner || r.namaPartner || '-'}</div>
              <small class="text-muted">${r.bidang_usaha || r.bidangUsaha || '-'}</small>
            </td>
            <td><span class="badge-kc-pill">${r.kc || r.branchOffice || '-'}</span></td>
            <td><span class="badge" style="background:#eff6ff; color:#0857c3; font-size:0.75rem; font-weight:700;">${r.perusahaan_anak || r.perusahaanAnak || '-'}</span></td>
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
@include('dashboard.partials.landing-page-recovery')
@endsection

@push('modals')
<!-- Modal Detail Nominatif Pipeline Payroll -->
<div class="modal fade modal-payroll-nominatif" id="payrollNominatifModal" tabindex="-1" role="dialog" aria-labelledby="payrollModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%); color: #ffffff; padding: 1.1rem 1.5rem;">
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
          @foreach($payrollBranchOrder as $branchName)
            <button type="button" class="modal-tab-btn" data-kc="{{ $branchName }}">
              {{ $branchName }} ({{ data_get($payrollBranches, $branchName.'.perusahaan', 0) }})
            </button>
          @endforeach
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
              <th>{{ data_get($payrollQualityStrategy, 'dimension_label', 'Cabang (KC)') }}</th>
              <th>Segmen</th>
              <th>Status</th>
              <th class="text-right">Pegawai</th>
              <th class="text-right" style="color: #0857c3;">Potensi</th>
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
                <td class="text-right font-weight-bold" style="color: #0857c3;">{{ $r['potensi_fmt'] ?? '0' }}</td>
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
      <div class="modal-header d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%); color: #ffffff; padding: 1.1rem 1.5rem;">
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
          @foreach($ecoBranchOrder as $branchName)
            <button type="button" class="modal-tab-btn" data-kc="{{ $branchName }}">
              {{ $branchName }} ({{ data_get($ecosystemBranches, $branchName.'.rekening_fmt', 0) }})
            </button>
          @endforeach
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
              <th>{{ data_get($ecosystemStrategy, 'dimension_label', 'Kantor Cabang') }}</th>
              <th>Segmen Ekosistem</th>
              <th>Nama Nasabah</th>
              <th>Nomor Rekening</th>
              <th class="text-right" style="color: #0857c3;">Posisi Saldo Terakhir</th>
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
      <div class="modal-header d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%); color: #ffffff; padding: 1.1rem 1.5rem;">
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
          @foreach($paBranchOrder as $branchName)
            <button type="button" class="modal-tab-btn" data-kc="{{ $branchName }}">
              {{ $branchName }} ({{ data_get($paBranches, $branchName.'.total_pipeline', 0) }})
            </button>
          @endforeach
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
              <th>{{ data_get($perusahaanAnakStrategy, 'dimension_label', 'Kantor Cabang') }}</th>
              <th>Entitas Anak</th>
              <th class="text-center">Status Akuisisi</th>
              <th>CIF NO</th>
              <th class="text-right">Saldo 31 Juli</th>
              <th class="text-right">Saldo 31 Agt</th>
              <th class="text-right" style="color: #0857c3;">Saldo 6 Sep</th>
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
