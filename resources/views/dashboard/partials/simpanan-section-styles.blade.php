<style>
/* The section surfaces and data bands follow the Pinjaman landing components. */
.db-shell .simpanan-sekat-stack {
  display: grid;
  gap: 1.25rem;
  min-width: 0;
  margin-top: 1.25rem;
}
.db-shell :is(.trend-position-card, .simpanan-sekat-card, .simpanan-digital-card,
  .simpanan-debitur-dormant-section, .simpanan-payroll-card,
  .simpanan-ecosystem-card, .simpanan-perusahaan-anak-card) {
  min-width: 0;
  background: #fff;
  border: 1px solid var(--c-border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}
.db-shell :is(.simpanan-ecosystem-card, .simpanan-perusahaan-anak-card) {
  margin-top: 1.25rem;
}
.db-shell :is(.asc-header, .ssc-head) {
  gap: .85rem;
  padding: .95rem 1.5rem;
  background: linear-gradient(135deg, #0857c3 0%, #1e40af 100%);
  border-bottom: 0;
  color: #fff;
}
.db-shell :is(.asc-header-left, .ssc-head-left) { min-width: 0; gap: .85rem; }
.db-shell :is(.asc-header-icon, .ssc-icon) {
  width: 32px;
  height: 32px;
  flex: 0 0 32px;
  border-radius: 10px;
  background: rgba(255, 255, 255, .2);
  color: #fff;
  box-shadow: none;
  font-size: .95rem;
}
.db-shell :is(.asc-header-title, .ssc-title) {
  color: #fff;
  font-size: .88rem;
  font-weight: 850;
  letter-spacing: .05em;
  overflow-wrap: anywhere;
}
.db-shell .asc-header-subtitle { color: rgba(235, 245, 255, .82); }
.db-shell .asc-header > .text-muted { color: rgba(235, 245, 255, .82) !important; }
.db-shell .asc-header .area6-pill {
  color: #fff;
  border-color: rgba(255, 255, 255, .3);
  background: rgba(255, 255, 255, .14);
}
.db-shell .tpc-body { display: flex; align-items: stretch; padding: 1.25rem .5rem; }
.db-shell .trend-col {
  flex: 1 1 0;
  min-width: 0;
  padding: 0 .85rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
}
.db-shell .trend-col:not(:last-child)::after {
  content: "";
  position: absolute;
  right: 0;
  top: 10%;
  height: 80%;
  width: 1px;
  background: var(--c-border);
}
.db-shell .trend-col-title {
  font-size: .74rem;
  font-weight: 850;
  margin-bottom: 1.15rem;
  text-align: center;
  letter-spacing: .03em;
}
.db-shell .text-tab-blue { color: #0857c3; }
.db-shell .text-dep-teal { color: #0f766e; }
.db-shell .text-giro-purple { color: #0284c7; }
.db-shell .trend-chart-wrapper { width: 100%; max-width: 270px; margin: 0 auto; }
.db-shell .trend-chart-wrapper svg { display: block; width: 100%; height: auto; }
.db-shell .trend-dates-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: .25rem;
  width: 100%;
  max-width: 270px;
  margin: .85rem auto 0;
}
.db-shell .trend-date-label {
  min-width: 0;
  font-size: .62rem;
  font-weight: 700;
  color: #475569;
  text-align: center;
  line-height: 1.2;
  overflow-wrap: anywhere;
}
.db-shell .trend-date-label :is(.date-part, .year-part) { display: block; }
.db-shell .ssc-body { padding: 1.25rem 1.5rem; min-width: 0; }
.db-shell .scc-monthly-canvas-wrap { position: relative; height: 340px; min-width: 0; }
.db-shell :is(.scc-monthly-controls, .scc-monthly-legend) {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: .55rem;
  min-width: 0;
}
.db-shell .scc-monthly-controls { justify-content: flex-end; }
.db-shell .scc-leg-chip { display: inline-flex; align-items: center; gap: .35rem; font-size: .65rem; }
.db-shell .scc-leg-dot { display: inline-block; width: 9px; height: 9px; flex: 0 0 9px; border-radius: 2px; }
.db-shell :is(.scc-segment-toggle, .sdd-toggle) {
  display: inline-flex;
  flex-wrap: wrap;
  gap: .25rem;
  padding: .22rem;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #f1f5f9;
}
.db-shell :is(.scc-segment-btn, .dc-trigger-btn) {
  min-height: 34px;
  border: 1px solid transparent;
  border-radius: 8px;
  padding: .35rem .75rem;
  background: transparent;
  color: #475569;
  font: 800 .72rem/1.3 'Inter', sans-serif;
  cursor: pointer;
}
.db-shell .scc-segment-btn.active {
  color: #fff;
  background: linear-gradient(135deg, #0857c3, #1e40af);
}
.db-shell .digital-channel-triggers { display: flex; gap: .35rem; flex-wrap: wrap; }
.db-shell .dc-trigger-btn {
  color: #fff;
  border-color: rgba(255, 255, 255, .28);
  background: rgba(255, 255, 255, .1);
}
.db-shell .dc-trigger-btn:hover { background: rgba(255, 255, 255, .2); }
.db-shell .dc-trigger-btn.active { color: #0857c3; border-color: #fff; background: #fff; }
.db-shell .dc-active-channel-badge {
  display: inline-flex;
  border: 1px solid rgba(255, 255, 255, .3);
  border-radius: 8px;
  padding: .25rem .5rem;
  background: rgba(255, 255, 255, .14);
  color: #fff;
  font-size: .62rem;
  font-weight: 700;
}
.db-shell .scc-monthly-dates { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: .65rem; margin-top: 1rem; }
.db-shell .scc-monthly-date-card { padding: .65rem; border: 1px solid var(--c-border); border-radius: 8px; color: #475569; background: #f8fafc; font-size: .65rem; line-height: 1.6; }
.db-shell .scc-monthly-date-card strong { display: block; color: #0b1739; margin-bottom: .2rem; }
.db-shell .dc-table-container { padding: 1.25rem 1.5rem; }
.db-shell :is(.dc-table-wrapper, .sdd-table-wrap, .payroll-branch-table-container) { max-width: 100%; }
.db-shell :is(.dc-table, .payroll-branch-table) { width: 100%; margin: 0; font-variant-numeric: tabular-nums; }
.db-shell :is(.dc-table, .payroll-branch-table) td { padding: .65rem .75rem; font-size: .74rem; vertical-align: middle; border-bottom: 1px solid #e2e8f0; }
.db-shell :is(.dc-table, .payroll-branch-table) tfoot { background: #eff6ff; }
.db-shell .dc-table .col-highlight { background: rgba(8, 87, 195, .06); font-weight: 800; }
.db-shell .dc-delta-pill { display: inline-flex; align-items: center; gap: .25rem; padding: .2rem .4rem; border-radius: 6px; font-size: .7rem; font-weight: 800; white-space: nowrap; }
.db-shell .dc-delta-pill--green { color: #15803d; background: #dcfce7; }
.db-shell .dc-delta-pill--red { color: #b91c1c; background: #fee2e2; }
.db-shell .dc-delta-pill--neutral { color: #475569; background: #f1f5f9; }
.db-shell .sdd-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; padding: 1.25rem 1.5rem; }
.db-shell .sdd-panel { min-width: 0; display: flex; flex-direction: column; gap: 1rem; }
.db-shell .sdd-panel + .sdd-panel { padding-left: 1.25rem; border-left: 1px solid var(--c-border); }
.db-shell .sdd-panel__head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .65rem; }
.db-shell .sdd-panel__title { color: #0b1739; font-size: .75rem; font-weight: 850; }
.db-shell .dormant-count-badge { color: #0857c3; font-size: .7rem; font-weight: 800; }
.db-shell .casa-ratio-cell { display: flex; align-items: center; justify-content: flex-end; gap: .45rem; }
.db-shell .casa-ratio-mini-bar { width: 45px; height: 5px; background: #e2e8f0; border-radius: 5px; overflow: hidden; }
.db-shell .casa-ratio-mini-fill { height: 100%; background: #0857c3; }
.db-shell :is(.payroll-kpi-box, .eco-pillar-chip, .sdd-stat-box) { min-width: 0; }
.db-shell :is(.payroll-kpi-value, .sdd-stat-val) { overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
.db-shell .badge-eco-pill,
.modal-payroll-nominatif :is(.badge-segmen, .badge-status-new, .badge-status-existing) { display: inline-block; padding: .2rem .45rem; border: 1px solid #d8e5f7; border-radius: 6px; background: #eff6ff; color: #0857c3; font-size: .66rem; font-weight: 700; }
.modal-payroll-nominatif .modal-body { overflow-x: auto; }
.modal-payroll-nominatif .modal-body table { min-width: 850px; }
@container simpanan-landing (max-width: 1100px) {
  .db-shell .sdd-grid { grid-template-columns: minmax(0, 1fr); }
  .db-shell .sdd-panel + .sdd-panel { padding: 1.25rem 0 0; border-left: 0; border-top: 1px solid var(--c-border); }
  .db-shell .payroll-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .db-shell .eco-pillar-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .db-shell .scc-monthly-controls { justify-content: flex-start; }
}
@container simpanan-landing (max-width: 620px) {
  .db-shell :is(.asc-header, .ssc-head) { padding: .85rem 1rem; align-items: flex-start; }
  .db-shell :is(.asc-header-left, .ssc-head-left) { align-items: flex-start; }
  .db-shell .tpc-body { flex-direction: column; padding: 1rem; gap: 1.25rem; }
  .db-shell .trend-col { padding: 0 0 1.25rem; }
  .db-shell .trend-col:not(:last-child)::after { top: auto; bottom: 0; left: 0; width: 100%; height: 1px; }
  .db-shell .trend-col:last-child { padding-bottom: 0; }
  .db-shell .scc-monthly-canvas-wrap { height: 280px; }
  .db-shell :is(.ssc-body, .dc-table-container, .sdd-grid, .payroll-card-body, .ecosystem-card-body, .perusahaan-anak-card-body) { padding: 1rem; }
  .db-shell :is(.payroll-kpi-grid, .sdd-stat-strip) { grid-template-columns: minmax(0, 1fr); }
  .db-shell .eco-pillar-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .db-shell .digital-channel-triggers { width: 100%; }
  .db-shell .dc-trigger-btn { flex: 1 1 auto; }
}
</style>
