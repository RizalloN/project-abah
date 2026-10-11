{{-- Geometry, palette and density copied from the current landing Pinjaman. --}}
<style>
:root {
  --r-2xl: 16px;
}
.db-ppt-btn {
  background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
  color: #ffffff;
  box-shadow: 0 4px 14px rgba(234, 88, 12, 0.3);
}
.db-ppt-btn:hover {
  background: linear-gradient(135deg, #c2410c 0%, #ea580c 100%);
  box-shadow: 0 8px 20px -2px rgba(234, 88, 12, 0.45);
}
.kpi-card.pinjaman .kc-icon-box { background: #eff6ff; color: #0857c3; border: 1px solid #bfdbfe; }
.kpi-card.portfolio .kc-icon-box { background: #f5f3ff; color: #6366f1; border: 1px solid #ddd6fe; }
.kpi-card:nth-child(4) .kc-icon-box { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
.kpi-card:nth-child(5) .kc-icon-box { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.kpi-card:nth-child(6) .kc-icon-box { background: #ecfeff; color: #0284c7; border: 1px solid #a5f3fc; }
.kpi-card.pinjaman .kc-label {
  color: #0857c3;
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
.kpi-card .kc-link {
  min-height: 36px;
  text-decoration: none;
  padding: 0 0.4rem;
}
.kpi-card .kc-link:hover {
  color: #ffffff;
  text-decoration: none;
}
.area6-panel {
  border: 1px solid var(--c-border);
  overflow: hidden;
}
.area6-head {
  border-bottom: 1px solid var(--c-border);
}
.area6-pill i {
  color: #0754bd;
}
.area6-card-premium {
  border: 1.5px solid var(--c-border);
  cursor: pointer;
}
.area6-card-premium:hover {
  box-shadow: var(--shadow-hover);
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
.area6-card-premium[data-metric="casa"]:hover {
  border-color: #a5b4fc;
  box-shadow: 0 20px 36px -6px rgba(79, 70, 229, 0.18), 0 8px 16px -3px rgba(15, 23, 42, 0.06);
}
.ap-week-toolbar {
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
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex;
  flex-direction: column;
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
  margin-top: 0.1rem;
  line-height: 1;
  white-space: nowrap;
}
.ap-week-btn:hover:not(:disabled) {
  border-color: #0857c3;
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(8, 87, 195, 0.14);
}
.ap-week-btn.active {
  border-color: #0857c3;
  box-shadow: 0 4px 12px rgba(8, 87, 195, 0.3);
  transform: translateY(-1px);
}
.ap-week-btn.active small {
  color: #dbeafe;
}
.ap-week-btn:disabled {
  opacity: 0.45;
  background: #f8fafc;
  border-color: #e2e8f0;
}
.ap-prognosa-strip {
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
.area6-card-premium[data-metric="giro"] .ap-prognosa-strip {
  border-color: #bae6fd;
  border-left-color: #0284c7;
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
}
.area6-card-premium[data-metric="npl"] .ap-prognosa-strip {
  border-color: #fecaca;
  border-left-color: #dc2626;
  background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
}
.area6-card-premium[data-metric="deposito"] .ap-prognosa-strip {
  border-color: #a7f3d0;
  border-left-color: #059669;
  background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
}
.area6-card-premium[data-metric="casa"] .ap-prognosa-strip {
  border-color: #c7d2fe;
  border-left-color: #4f46e5;
  background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
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
.text-green-flat { color: #15803d !important; }
.text-red-flat { color: #dc2626 !important; }
.text-muted-flat { color: #64748b !important; }
.ap-dashed-divider {
  border: 0;
  border-top: 1px dashed #cbd5e1;
  margin: 0.95rem 0 0.85rem;
}
.ap-deltas {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.45rem;
}
.ap-delta-item {
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
  margin-bottom: 0.25rem;
}
.ap-delta-val {
  line-height: 1.1;
}
.ap-delta-arrow {
  font-size: 0.75rem;
  margin-top: 0.15rem;
  line-height: 1;
}
@media (max-width: 1399.98px) {
  .area6-card-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 1199.98px) {
  .kpi-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 991.98px) {
  .area6-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 767.98px) {
  .db-header { align-items: flex-start; flex-direction: column; gap: 0.85rem; padding: 1.1rem; }
  .db-meta { width: 100%; }
  .kpi-strip { grid-template-columns: 1fr; }
  .area6-head { align-items: flex-start; }
  .area6-head-actions { width: 100%; }
  .landing-scope-stage { width: 100%; flex-direction: column; align-items: flex-start; }
  .area6-scope-toggle { width: 100%; justify-content: space-between; }
}
@media (max-width: 575.98px) {
  .db-date-picker-select { width: 100%; justify-content: center; }
  .db-pres-btn { width: 100%; justify-content: center; }
  .db-ppt-btn { width: 100%; justify-content: center; }
  .db-meta-chip { width: 100%; justify-content: center; }
}
.db-pres-btn {
  background: linear-gradient(135deg, #1e293b, #0f172a);
  color: #f8fafc;
  border: 1px solid rgba(255,255,255,0.15);
  padding: 0.45rem 0.9rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
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
.db-shell {
  width: 100%;
  max-width: 1840px;
  margin-left: auto;
  margin-right: auto;
}
.db-header {
  min-width: 0;
}
.db-brand {
  min-width: 0;
}
.db-meta {
  min-width: 0;
}
.area6-panel {
  min-width: 0;
}
.db-brand > div:last-child {
  min-width: 0;
}
.db-title {
  overflow-wrap: anywhere;
}
.db-subtitle {
  overflow-wrap: anywhere;
}
.area6-title {
  overflow-wrap: anywhere;
}
.area6-sub {
  overflow-wrap: anywhere;
}
.db-meta {
  justify-content: flex-end;
}
.area6-card-grid {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.area6-scope-toggle {
  max-width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.area6-scope-btn {
  white-space: nowrap;
}
@media (min-width: 1600px) {
  .db-shell {
    max-width: 1920px;
  }
  .area6-card-grid {
    gap: 1.25rem;
  }
}
@media (max-width: 1199.98px) {
  .db-header {
    padding: 1rem;
  }
  .area6-panel .area6-card-grid {
    padding-left: 1rem;
    padding-right: 1rem;
  }
  .area6-card-grid {
    padding-left: 1rem;
    padding-right: 1rem;
  }
}
@media (max-width: 991.98px) {
  .db-header {
    flex-direction: column;
  }
  .db-meta {
    width: 100%;
  }
  .db-date-picker-container {
    width: auto;
    max-width: 100%;
  }
  .db-date-picker-select {
    width: auto;
    max-width: 100%;
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
}
@media (max-width: 767.98px) {
  .db-shell {
    padding-top: 0 !important;
  }
  .area6-panel {
    border-radius: var(--r-lg);
  }
  .area6-card-premium {
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
  .db-date-picker-container {
    flex: 1 1 auto;
  }
  .db-pres-btn {
    flex: 1 1 auto;
  }
  .db-ppt-btn {
    flex: 1 1 auto;
  }
  .db-meta-chip {
    flex: 1 1 auto;
  }
  .db-now {
    flex: 1 1 auto;
  }
  .db-date-picker-select {
    width: 100%;
    justify-content: center;
  }
  .db-pres-btn {
    width: 100%;
    justify-content: center;
  }
  .db-ppt-btn {
    width: 100%;
    justify-content: center;
  }
  .db-meta-chip {
    width: 100%;
    justify-content: center;
  }
  .kpi-card {
    min-height: auto;
  }
  .area6-head {
    padding-left: 0.85rem;
    padding-right: 0.85rem;
  }
  .area6-card-grid {
    padding-left: 0.85rem;
    padding-right: 0.85rem;
  }
  .area6-card-grid {
    gap: 0.85rem;
  }
  .ap-grid-2 {
    gap: 0.45rem;
  }
  .ap-deltas {
    gap: 0.45rem;
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
  .kpi-strip {
    margin-bottom: 0.85rem;
  }
  .area6-panel {
    margin-bottom: 0.85rem;
  }
  .kpi-card {
    padding: 0.95rem;
  }
  .kpi-card .kc-val {
    font-size: clamp(1.15rem, 8vw, 1.45rem);
  }
  .area6-panel .area6-card-grid {
    padding: 0.75rem;
  }
  .area6-card-grid {
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
  .ap-deltas {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 380px) {
  .db-meta {
    grid-template-columns: 1fr;
  }
  .db-pres-btn {
    font-size: 0.62rem;
  }
  .db-ppt-btn {
    font-size: 0.62rem;
  }
}
.db-shell {
  box-sizing: border-box;
}
.db-shell * {
  box-sizing: border-box;
}
.db-shell {
  overflow-x: clip;
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
.ap-body {
  min-width: 0;
}
.ap-metric-col {
  min-width: 0;
}
.ap-delta-item {
  min-width: 0;
}
.ap-metric-val {
  max-width: 100%;
  overflow-wrap: anywhere;
}
.ap-metric-pct-val {
  max-width: 100%;
  overflow-wrap: anywhere;
}
.ap-metric-gap-val {
  max-width: 100%;
  overflow-wrap: anywhere;
}
@media (min-width: 768px) and (max-width: 1399.98px) {
  .db-shell {
    padding-left: 0;
    padding-right: 0;
  }
  .db-header {
    width: 100%;
    max-width: 100%;
  }
  .area6-panel {
    width: 100%;
    max-width: 100%;
  }
  .area6-head {
    padding: 0.95rem 1rem;
  }
  .area6-panel .area6-card-grid {
    padding: 1rem;
    gap: 0.9rem;
  }
  .area6-card-grid {
    padding: 1rem;
    gap: 0.9rem;
  }
  .area6-panel .area6-card-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  }
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
  .ap-metric-label {
    font-size: 0.62rem;
  }
  .ap-delta-label {
    font-size: 0.62rem;
  }
  .ap-metric-val {
    font-size: 1.12rem;
    letter-spacing: 0;
  }
  .ap-metric-pct-val {
    font-size: 1.12rem;
    letter-spacing: 0;
  }
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
}
@media (min-width: 768px) and (max-width: 1100px) {
  .area6-panel .area6-card-grid {
    grid-template-columns: 1fr !important;
  }
  .area6-card-grid {
    grid-template-columns: 1fr !important;
  }
  .ap-header-title {
    font-size: 0.72rem;
  }
}
@media (max-width: 767.98px) {
  .db-shell {
    overflow-x: hidden;
  }
  .area6-panel .area6-card-grid {
    width: 100%;
    max-width: 100%;
  }
  .area6-card-grid {
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
  .ap-metric-val {
    font-size: 1.18rem;
    letter-spacing: 0;
  }
  .ap-metric-pct-val {
    font-size: 1.18rem;
    letter-spacing: 0;
  }
  .ap-metric-gap-val {
    font-size: 1.18rem;
    letter-spacing: 0;
  }
}
.db-shell.landing-compact {
  max-width: 100% !important;
}
.db-shell.landing-compact .db-header {
  border-radius: var(--r-lg);
}
.db-shell.landing-compact .area6-panel {
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
.db-shell.landing-compact .area6-head-actions {
  align-items: flex-start;
  justify-content: flex-start;
  width: 100%;
}
.db-shell.landing-compact .area6-periods {
  align-items: flex-start;
  justify-content: flex-start;
  width: 100%;
}
.db-shell.landing-compact .area6-scope-toggle {
  max-width: 100%;
}
.db-shell.landing-compact .area6-panel .area6-card-grid {
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)) !important;
  gap: 0.85rem !important;
  padding: 0.95rem !important;
}
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
.db-shell.landing-compact .ap-metric-label {
  font-size: 0.6rem !important;
  line-height: 1.2;
}
.db-shell.landing-compact .ap-delta-label {
  font-size: 0.6rem !important;
  line-height: 1.2;
}
.db-shell.landing-compact .ap-metric-val {
  font-size: 1.08rem !important;
  line-height: 1.12 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-compact .ap-metric-pct-val {
  font-size: 1.08rem !important;
  line-height: 1.12 !important;
  letter-spacing: 0 !important;
}
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
.db-shell.landing-narrow .kpi-strip {
  grid-template-columns: 1fr !important;
}
.db-shell.landing-narrow .area6-panel .area6-card-grid {
  grid-template-columns: 1fr !important;
}
.db-shell.landing-narrow .area6-card-grid {
  grid-template-columns: 1fr !important;
}
.db-shell.landing-narrow .db-header {
  padding-left: 0.8rem !important;
  padding-right: 0.8rem !important;
}
.db-shell.landing-narrow .area6-head {
  padding-left: 0.8rem !important;
  padding-right: 0.8rem !important;
}
.db-shell.landing-narrow .ap-deltas {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
}
.db-shell.landing-tablet {
  --landing-space: 0.85rem;
  --landing-card-radius: 12px;
  --landing-panel-pad: 0.95rem;
}
.db-shell.landing-mobile {
  --landing-space: 0.85rem;
  --landing-card-radius: 12px;
  --landing-panel-pad: 0.95rem;
}
.db-shell.landing-short {
  --landing-space: 0.85rem;
  --landing-card-radius: 12px;
  --landing-panel-pad: 0.95rem;
}
.db-shell.landing-tablet .db-header {
  padding: 0.9rem 1rem !important;
  margin-bottom: 0.85rem !important;
  border-radius: var(--landing-card-radius) !important;
}
.db-shell.landing-mobile .db-header {
  padding: 0.9rem 1rem !important;
  margin-bottom: 0.85rem !important;
  border-radius: var(--landing-card-radius) !important;
}
.db-shell.landing-short .db-header {
  padding: 0.9rem 1rem !important;
  margin-bottom: 0.85rem !important;
  border-radius: var(--landing-card-radius) !important;
}
.db-shell.landing-tablet .kpi-strip {
  gap: 0.65rem !important;
  margin-bottom: 0.75rem !important;
}
.db-shell.landing-mobile .kpi-strip {
  gap: 0.65rem !important;
  margin-bottom: 0.75rem !important;
}
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
.db-shell.landing-tablet .kpi-card {
  min-height: 0 !important;
  padding: 0.8rem 0.9rem !important;
}
.db-shell.landing-mobile .kpi-card {
  min-height: 0 !important;
  padding: 0.8rem 0.9rem !important;
}
.db-shell.landing-short .kpi-card {
  min-height: 0 !important;
  padding: 0.8rem 0.9rem !important;
}
.db-shell.landing-tablet .kpi-card .kc-label {
  font-size: 0.58rem !important;
  letter-spacing: 0.035em !important;
}
.db-shell.landing-mobile .kpi-card .kc-label {
  font-size: 0.58rem !important;
  letter-spacing: 0.035em !important;
}
.db-shell.landing-short .kpi-card .kc-label {
  font-size: 0.58rem !important;
  letter-spacing: 0.035em !important;
}
.db-shell.landing-tablet .kpi-card .kc-val {
  font-size: 1.05rem !important;
  line-height: 1.12 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-mobile .kpi-card .kc-val {
  font-size: 1.05rem !important;
  line-height: 1.12 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-short .kpi-card .kc-val {
  font-size: 1.05rem !important;
  line-height: 1.12 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-tablet .area6-panel {
  margin: 0.8rem 0 !important;
  border-radius: var(--landing-card-radius) !important;
}
.db-shell.landing-mobile .area6-panel {
  margin: 0.8rem 0 !important;
  border-radius: var(--landing-card-radius) !important;
}
.db-shell.landing-short .area6-panel {
  margin: 0.8rem 0 !important;
  border-radius: var(--landing-card-radius) !important;
}
.db-shell.landing-tablet .area6-head {
  padding: 0.85rem var(--landing-panel-pad) !important;
  gap: 0.65rem !important;
}
.db-shell.landing-mobile .area6-head {
  padding: 0.85rem var(--landing-panel-pad) !important;
  gap: 0.65rem !important;
}
.db-shell.landing-short .area6-head {
  padding: 0.85rem var(--landing-panel-pad) !important;
  gap: 0.65rem !important;
}
.db-shell.landing-tablet .area6-title {
  font-size: 0.98rem !important;
  line-height: 1.18 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-mobile .area6-title {
  font-size: 0.98rem !important;
  line-height: 1.18 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-short .area6-title {
  font-size: 0.98rem !important;
  line-height: 1.18 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-tablet .area6-scope-toggle {
  display: flex !important;
  flex-wrap: wrap !important;
  width: 100% !important;
  border-radius: 12px !important;
  overflow: visible !important;
}
.db-shell.landing-mobile .area6-scope-toggle {
  display: flex !important;
  flex-wrap: wrap !important;
  width: 100% !important;
  border-radius: 12px !important;
  overflow: visible !important;
}
.db-shell.landing-short .area6-scope-toggle {
  display: flex !important;
  flex-wrap: wrap !important;
  width: 100% !important;
  border-radius: 12px !important;
  overflow: visible !important;
}
.db-shell.landing-tablet .area6-scope-btn {
  flex: 1 1 8rem !important;
  min-height: 36px !important;
  padding: 0.34rem 0.58rem !important;
  text-align: center !important;
}
.db-shell.landing-mobile .area6-scope-btn {
  flex: 1 1 8rem !important;
  min-height: 36px !important;
  padding: 0.34rem 0.58rem !important;
  text-align: center !important;
}
.db-shell.landing-short .area6-scope-btn {
  flex: 1 1 8rem !important;
  min-height: 36px !important;
  padding: 0.34rem 0.58rem !important;
  text-align: center !important;
}
.db-shell.landing-tablet .area6-periods {
  display: grid !important;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
  width: 100% !important;
  gap: 0.38rem !important;
}
.db-shell.landing-mobile .area6-periods {
  display: grid !important;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
  width: 100% !important;
  gap: 0.38rem !important;
}
.db-shell.landing-short .area6-periods {
  display: grid !important;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
  width: 100% !important;
  gap: 0.38rem !important;
}
.db-shell.landing-tablet .area6-pill {
  min-width: 0 !important;
  justify-content: center !important;
  white-space: normal !important;
  text-align: center !important;
  border-radius: 10px !important;
  font-size: 0.6rem !important;
}
.db-shell.landing-mobile .area6-pill {
  min-width: 0 !important;
  justify-content: center !important;
  white-space: normal !important;
  text-align: center !important;
  border-radius: 10px !important;
  font-size: 0.6rem !important;
}
.db-shell.landing-short .area6-pill {
  min-width: 0 !important;
  justify-content: center !important;
  white-space: normal !important;
  text-align: center !important;
  border-radius: 10px !important;
  font-size: 0.6rem !important;
}
.db-shell.landing-tablet .area6-panel .area6-card-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  gap: 0.8rem !important;
  padding: var(--landing-panel-pad) !important;
}
.db-shell.landing-tablet .area6-card-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  gap: 0.8rem !important;
  padding: var(--landing-panel-pad) !important;
}
.db-shell.landing-mobile .area6-panel .area6-card-grid {
  grid-template-columns: 1fr !important;
  gap: 0.7rem !important;
  padding: 0.75rem !important;
}
.db-shell.landing-mobile .area6-card-grid {
  grid-template-columns: 1fr !important;
  gap: 0.7rem !important;
  padding: 0.75rem !important;
}
.db-shell.landing-short .area6-panel .area6-card-grid {
  gap: 0.7rem !important;
}
.db-shell.landing-short .area6-card-grid {
  gap: 0.7rem !important;
}
.db-shell.landing-tablet .area6-card-premium {
  overflow: hidden !important;
  min-height: 0 !important;
  border-radius: var(--landing-card-radius) !important;
  box-shadow: var(--shadow-sm) !important;
}
.db-shell.landing-mobile .area6-card-premium {
  overflow: hidden !important;
  min-height: 0 !important;
  border-radius: var(--landing-card-radius) !important;
  box-shadow: var(--shadow-sm) !important;
}
.db-shell.landing-short .area6-card-premium {
  overflow: hidden !important;
  min-height: 0 !important;
  border-radius: var(--landing-card-radius) !important;
  box-shadow: var(--shadow-sm) !important;
}
.db-shell.landing-tablet .area6-card-premium:hover {
  transform: none !important;
}
.db-shell.landing-mobile .area6-card-premium:hover {
  transform: none !important;
}
.db-shell.landing-short .area6-card-premium:hover {
  transform: none !important;
}
.db-shell.landing-tablet .ap-badge {
  width: 30px !important;
  height: 30px !important;
  top: 7px !important;
  left: 8px !important;
  border-width: 0 !important;
  box-shadow: none !important;
  font-size: 0.78rem !important;
}
.db-shell.landing-mobile .ap-badge {
  width: 30px !important;
  height: 30px !important;
  top: 7px !important;
  left: 8px !important;
  border-width: 0 !important;
  box-shadow: none !important;
  font-size: 0.78rem !important;
}
.db-shell.landing-short .ap-badge {
  width: 30px !important;
  height: 30px !important;
  top: 7px !important;
  left: 8px !important;
  border-width: 0 !important;
  box-shadow: none !important;
  font-size: 0.78rem !important;
}
.db-shell.landing-tablet .ap-header {
  min-height: 44px !important;
  height: auto !important;
  justify-content: flex-start !important;
  padding: 0.52rem 0.65rem 0.52rem 2.85rem !important;
  border-radius: 0 !important;
}
.db-shell.landing-mobile .ap-header {
  min-height: 44px !important;
  height: auto !important;
  justify-content: flex-start !important;
  padding: 0.52rem 0.65rem 0.52rem 2.85rem !important;
  border-radius: 0 !important;
}
.db-shell.landing-short .ap-header {
  min-height: 44px !important;
  height: auto !important;
  justify-content: flex-start !important;
  padding: 0.52rem 0.65rem 0.52rem 2.85rem !important;
  border-radius: 0 !important;
}
.db-shell.landing-tablet .ap-header-title {
  font-size: 0.82rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
  text-align: left !important;
  text-wrap: balance;
}
.db-shell.landing-mobile .ap-header-title {
  font-size: 0.82rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
  text-align: left !important;
  text-wrap: balance;
}
.db-shell.landing-short .ap-header-title {
  font-size: 0.82rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
  text-align: left !important;
  text-wrap: balance;
}
.db-shell.landing-tablet .ap-body {
  padding: 0.75rem !important;
}
.db-shell.landing-mobile .ap-body {
  padding: 0.75rem !important;
}
.db-shell.landing-short .ap-body {
  padding: 0.75rem !important;
}
.db-shell.landing-tablet .ap-grid-2 {
  gap: 0.2rem !important;
}
.db-shell.landing-mobile .ap-grid-2 {
  gap: 0.2rem !important;
}
.db-shell.landing-short .ap-grid-2 {
  gap: 0.2rem !important;
}
.db-shell.landing-tablet .ap-metric-col {
  padding: 0.3rem 0.15rem !important;
}
.db-shell.landing-mobile .ap-metric-col {
  padding: 0.3rem 0.15rem !important;
}
.db-shell.landing-short .ap-metric-col {
  padding: 0.3rem 0.15rem !important;
}
.db-shell.landing-tablet .ap-metric-label {
  font-size: 0.58rem !important;
  line-height: 1.18 !important;
}
.db-shell.landing-tablet .ap-delta-label {
  font-size: 0.58rem !important;
  line-height: 1.18 !important;
}
.db-shell.landing-mobile .ap-metric-label {
  font-size: 0.58rem !important;
  line-height: 1.18 !important;
}
.db-shell.landing-mobile .ap-delta-label {
  font-size: 0.58rem !important;
  line-height: 1.18 !important;
}
.db-shell.landing-short .ap-metric-label {
  font-size: 0.58rem !important;
  line-height: 1.18 !important;
}
.db-shell.landing-short .ap-delta-label {
  font-size: 0.58rem !important;
  line-height: 1.18 !important;
}
.db-shell.landing-tablet .ap-metric-val {
  font-size: 0.98rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-tablet .ap-metric-pct-val {
  font-size: 0.98rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-tablet .ap-metric-gap-val {
  font-size: 0.98rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-mobile .ap-metric-val {
  font-size: 0.98rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-mobile .ap-metric-pct-val {
  font-size: 0.98rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-mobile .ap-metric-gap-val {
  font-size: 0.98rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-short .ap-metric-val {
  font-size: 0.98rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-short .ap-metric-pct-val {
  font-size: 0.98rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-short .ap-metric-gap-val {
  font-size: 0.98rem !important;
  line-height: 1.1 !important;
  letter-spacing: 0 !important;
}
.db-shell.landing-tablet .ap-metric-sub {
  font-size: 0.54rem !important;
}
.db-shell.landing-mobile .ap-metric-sub {
  font-size: 0.54rem !important;
}
.db-shell.landing-short .ap-metric-sub {
  font-size: 0.54rem !important;
}
.db-shell.landing-tablet .ap-deltas {
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  gap: 0.24rem !important;
}
.db-shell.landing-short .ap-deltas {
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  gap: 0.24rem !important;
}
.db-shell.landing-mobile .ap-deltas {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
}
.db-shell.landing-tablet .ap-delta-val {
  font-size: 0.62rem !important;
}
.db-shell.landing-mobile .ap-delta-val {
  font-size: 0.62rem !important;
}
.db-shell.landing-short .ap-delta-val {
  font-size: 0.62rem !important;
}
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
  isolation: isolate;
  padding: 1.15rem 1.35rem;
  color: #ffffff;
  background:
    linear-gradient(118deg, rgba(7, 31, 78, 0.98) 0%, rgba(9, 79, 174, 0.96) 58%, rgba(34, 145, 210, 0.92) 100%);
  border: 1px solid rgba(255, 255, 255, 0.22);
  border-top: 0;
  border-radius: 20px;
  box-shadow: 0 18px 46px -28px rgba(8, 45, 112, 0.85);
}
.db-header::before {
  z-index: -1;
  border-radius: 999px;
  pointer-events: none;
}
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
  inset: 0 auto 0 0;
  width: 4px;
  background: #94a3b8;
}
.kpi-card.simpanan {
  border-left-width: 1px !important;
}
.kpi-card.pinjaman {
  border-left-width: 1px !important;
}
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
}
.area6-head {
  border-bottom-color: rgba(191, 204, 224, 0.72);
}
.area6-title {
  color: var(--landing-ink);
}
.area6-scope-toggle {
  background: rgba(226, 232, 240, 0.78);
  border-color: rgba(148, 163, 184, 0.38);
}
.area6-scope-btn.active {
  background: linear-gradient(135deg, #0c4fb7, #1378d3);
  box-shadow: 0 8px 20px -12px rgba(5, 62, 148, 0.9);
}
.area6-panel .area6-card-grid {
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  gap: 1rem;
}
.area6-card-premium {
  border-color: rgba(191, 204, 224, 0.78);
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
.area6-card-premium[data-metric="deposito"] .ap-body {
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
@media (max-width: 767.98px) {
  .db-shell {
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
@media (max-width: 767.98px) {
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
}
.db-branch-picker {
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
}
.db-branch-picker select:disabled { cursor: not-allowed; color: #52647b; background: #edf2f8; }
.db-branch-picker > i { position: absolute; left: 0.8rem; color: #1263bd; pointer-events: none; }
.db-branch-picker::after { content: '\f078'; position: absolute; right: 0.8rem; color: #60748c; font: 900 0.68rem/1 'Font Awesome 5 Free'; pointer-events: none; }
.db-branch-picker:focus-within select { outline: 3px solid rgba(19, 120, 211, 0.2); border-color: #1378d3; }
.landing-scope-stage {
  display: flex;
  justify-content: flex-end;
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
}
.landing-scope-stage .area6-scope-btn { min-width: 0; width: 100%; }
@media (max-width: 1199.98px) {
  .landing-scope-stage { grid-template-columns: 1fr; }
}
@media (max-width: 767.98px) {
  .db-branch-picker { width: 100%; min-width: 0; }
}
.db-shell .area6-panel .area6-card-grid.area6-card-grid--three {
  width: 100%;
  margin-inline: auto;
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  justify-content: center;
}
.db-shell .area6-panel .area6-card-grid.area6-card-grid--four {
  width: 100%;
  margin-inline: auto;
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  justify-content: center;
}
.db-shell.landing-tablet .area6-panel .area6-card-grid.area6-card-grid--three,
.db-shell.landing-tablet .area6-panel .area6-card-grid.area6-card-grid--four {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
}
.db-shell.landing-mobile .area6-panel .area6-card-grid.area6-card-grid--three,
.db-shell.landing-mobile .area6-panel .area6-card-grid.area6-card-grid--four {
  grid-template-columns: 1fr !important;
}
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
.area6-scope-btn__copy strong { display: block; overflow-wrap: anywhere; letter-spacing: 0; }
.area6-scope-btn__copy small { display: block; overflow-wrap: anywhere; letter-spacing: 0; }
.area6-scope-btn__copy strong { font-size: .76rem; line-height: 1.2; }
.area6-scope-btn__copy small { margin-top: .12rem; color: #64748b; font-size: .6rem; line-height: 1.2; }
.area6-scope-btn.active .area6-scope-btn__copy small { color: rgba(255, 255, 255, 0.88); }
@media (min-width: 992px) {
  .db-shell .area6-head {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 1.5rem !important;
  }
  .db-shell.landing-compact .area6-head {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 1.5rem !important;
  }
  .db-shell .area6-head > div:first-child {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    max-width: 520px !important;
  }
  .db-shell.landing-compact .area6-head > div:first-child {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    max-width: 520px !important;
  }
  .db-shell .area6-head-actions {
    flex: 0 0 auto !important;
    margin-left: auto !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: flex-end !important;
    gap: 0.55rem !important;
    width: auto !important;
  }
  .db-shell.landing-compact .area6-head-actions {
    flex: 0 0 auto !important;
    margin-left: auto !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: flex-end !important;
    gap: 0.55rem !important;
    width: auto !important;
  }
  .db-shell .landing-scope-stage {
    display: flex !important;
    justify-content: flex-end !important;
    align-items: center !important;
    width: auto !important;
    max-width: 100% !important;
  }
  .db-shell.landing-compact .landing-scope-stage {
    display: flex !important;
    justify-content: flex-end !important;
    align-items: center !important;
    width: auto !important;
    max-width: 100% !important;
  }
  .db-shell .landing-scope-stage .area6-scope-toggle {
    display: inline-grid !important;
    grid-template-columns: repeat(4, minmax(122px, auto)) !important;
    width: auto !important;
  }
  .db-shell.landing-compact .landing-scope-stage .area6-scope-toggle {
    display: inline-grid !important;
    grid-template-columns: repeat(4, minmax(122px, auto)) !important;
    width: auto !important;
  }
  .db-shell .area6-periods {
    display: flex !important;
    flex-wrap: wrap !important;
    justify-content: flex-end !important;
    align-items: center !important;
    gap: 0.45rem !important;
    width: 100% !important;
  }
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
@media (max-width: 767.98px) {
  .landing-scope-stage { grid-template-columns: 1fr; }
  .db-shell .landing-scope-stage .area6-scope-toggle { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 319.98px) {
  .area6-scope-btn__copy small { display: none; }
  .landing-scope-stage .area6-scope-btn { min-height: 48px; padding: .35rem; }
  .area6-scope-btn__icon { flex-basis: 30px; width: 30px; height: 30px; }
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
.db-shell.landing-tablet .ap-week-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: .55rem;
  margin-inline: .9rem;
  padding: .45rem .85rem;
}
.db-shell.landing-narrow .ap-week-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: .55rem;
  margin-inline: .9rem;
  padding: .45rem .85rem;
}
.db-shell.landing-tablet .ap-week-toggle {
  display: flex;
  flex-wrap: nowrap;
  overflow-x: auto;
  width: auto;
}
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
.db-shell.landing-tablet .ap-week-btn {
  min-width: 0;
  min-height: 36px;
  padding-inline: .35rem;
}
.db-shell.landing-mobile .ap-week-btn {
  min-width: 0;
  min-height: 36px;
  padding-inline: .35rem;
}
.db-shell.landing-narrow .landing-scope-stage {
  grid-template-columns: minmax(0, 1fr);
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
}
.db-shell :where(button, select, input, textarea, a[href], [role="button"]):focus-visible {
  outline: 3px solid rgba(48, 127, 226, 0.42) !important;
  outline-offset: 2px;
}
.db-shell button {
  max-width: 100%;
}
.db-shell [role="button"] {
  max-width: 100%;
}
@media (max-width: 359.98px) {
  .db-shell button {
    white-space: normal;
    overflow-wrap: anywhere;
  }
  .db-shell [role="button"] {
    white-space: normal;
    overflow-wrap: anywhere;
  }
}
@media (prefers-reduced-motion: reduce) {
  .db-shell {
    scroll-behavior: auto !important;
    transition-duration: .01ms !important;
    animation-duration: .01ms !important;
    animation-iteration-count: 1 !important;
  }
  .db-shell * {
    scroll-behavior: auto !important;
    transition-duration: .01ms !important;
    animation-duration: .01ms !important;
    animation-iteration-count: 1 !important;
  }
}

/* Remove inherited legacy spacing from the growth tiles. */
.db-shell .area6-card-premium .ap-delta-item { flex: 0 1 auto; }
.db-shell .area6-card-premium .ap-delta-val { margin-top: 0; }

/* Product banners use the same palette and surfaces as landing Pinjaman. */
.db-shell .ap-header.bg-tabungan, .db-shell .ap-badge.bg-tabungan {
  background: #104bb5 linear-gradient(135deg, #0857c3 0%, #1e40af 100%) !important;
}
.db-shell .ap-header.bg-deposito, .db-shell .ap-badge.bg-deposito {
  background: #0f9f75 linear-gradient(135deg, #059669 0%, #047857 100%) !important;
}
.db-shell .ap-header.bg-giro, .db-shell .ap-badge.bg-giro {
  background: #cc7a08 linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
}
@media (max-width: 767.98px) {
  .db-shell { padding: .25rem; }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const shell = document.querySelector('.db-shell');
  if (!shell) return;
  document.body.classList.add('dashboard-landing-page');
  let frame;
  let lastSize = '';
  const syncDensity = () => {
    const width = Math.min(shell.getBoundingClientRect().width, window.visualViewport?.width || innerWidth);
    const height = window.visualViewport?.height || innerHeight;
    const mobile = width <= 700;
    const tablet = !mobile && (width <= 1280 || (matchMedia('(pointer: coarse)').matches && width <= 1680));
    const short = height <= 760;
    shell.classList.toggle('landing-compact', mobile || tablet || short || width <= 1500);
    shell.classList.toggle('landing-narrow', mobile || width <= 880);
    shell.classList.toggle('landing-tablet', tablet);
    shell.classList.toggle('landing-mobile', mobile);
    shell.classList.toggle('landing-short', short && !mobile);
    const size = `${Math.round(width)}:${Math.round(height)}`;
    if (size !== lastSize) {
      lastSize = size;
      window.simpananMonthlyChartInstance?.resize();
    }
  };
  const schedule = () => {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(syncDensity);
  };
  schedule();
  addEventListener('resize', schedule, { passive: true });
  addEventListener('orientationchange', schedule, { passive: true });
  if (window.ResizeObserver) new ResizeObserver(schedule).observe(shell);
});
</script>
