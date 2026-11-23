<style>
/* Consolidated loan desk: share the blue/white language of the segment desks. */
.db-shell.landing-overview-active {
  --overview-ink: #12365c;
  --overview-muted: #526b87;
  --overview-blue: #0754bd;
  --overview-line: #d3e1ef;
  --overview-surface: #f2f7fd;
}
.db-shell.landing-overview-active .area6-panel {
  
  background: #f2f7fd;
  border-color: #c6d9ed;
  border-radius: 22px;
}
.db-shell.landing-overview-active .area6-head { background: #fff; }
.db-shell.landing-overview-active .area6-scope-content { padding: 1.25rem; }
.loan-overview-hero {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  overflow: hidden;
  padding: 1.7rem 1.8rem;
  margin-bottom: 1.2rem;
  color: #fff;
  background: linear-gradient(110deg, #063476, #0754bd);
  border-radius: 18px 6px 18px 6px;
}
.loan-overview-hero::after {
  content: '';
  position: absolute;
  right: -65px;
  top: -115px;
  width: 290px;
  height: 290px;
  border: 40px solid rgba(139, 220, 255, .12);
  border-radius: 50%;
  pointer-events: none;
}
.loan-overview-hero__copy, .loan-overview-hero__context { position: relative; z-index: 1; min-width: 0; }
.loan-overview-hero__label { display: inline-flex; align-items: center; gap: .5rem; color: #c6e8ff; font-size: .76rem; font-weight: 650; }
.loan-overview-hero h2 { margin: .45rem 0; color: #fff; font-size: clamp(1.4rem, 2.2vw, 2rem); font-weight: 800; letter-spacing: -.035em; line-height: 1.2; }
.loan-overview-hero p { max-width: 520px; margin: 0; color: #d8eaff; font-size: .82rem; line-height: 1.6; }
.loan-overview-hero__context { display: grid; flex-shrink: 0; gap: .65rem; padding: 1rem 1.1rem; background: rgba(255,255,255,.09); border: 1px solid rgba(205,233,255,.3); border-radius: 12px; }
.loan-overview-hero__context span, .loan-overview-hero__context strong { display: flex; align-items: center; gap: .6rem; font-size: .78rem; overflow-wrap: anywhere; }
.loan-overview-hero__context span { color: #d8eaff; }
.loan-overview-hero__context strong { color: #fff; font-weight: 750; }
.db-shell.landing-overview-active .ap-week-toolbar { margin: 0 0 1.2rem; border-color: #cbddef; background: #fff; border-radius: 12px; }
.db-shell.landing-overview-active .area6-panel .area6-segment-container { padding: 0 !important; margin-top: 1.25rem; gap: 1rem; }
.db-shell.landing-overview-active :is(.area6-segment-card, .total-composition-card, .trend-position-card, .perf-rka-card) { min-width: 0; border: 1px solid #cfdfef; border-radius: 16px; box-shadow: none; background: #fff; }
.db-shell.landing-overview-active .asc-header { min-height: 65px; gap: .7rem; padding: 1rem; color: var(--overview-ink); background: #fff; border-bottom: 1px solid #dfebf5; }
.db-shell.landing-overview-active .asc-header-icon { display: grid; place-items: center; width: 34px; height: 34px; color: #0754bd; background: #edf4ff; border-radius: 9px; box-shadow: none; }
.db-shell.landing-overview-active .asc-header-title { color: var(--overview-ink); font-size: .82rem; letter-spacing: 0; text-shadow: none; }
.db-shell.landing-overview-active :is(.asc-table, .perf-table) { font-variant-numeric: tabular-nums; }
.db-shell.landing-overview-active :is(.asc-val-num, .asc-target-num, .asc-ref-num, .perf-mono-cell, .tcc-total-val) { font-family: inherit; }
.db-shell.landing-overview-active .asc-table thead th { padding: .8rem .65rem; color: #234c75; background: #edf4fc; border-color: #d6e3f1; font-size: .64rem; }
.db-shell.landing-overview-active .asc-table thead tr:first-child th:not(:first-child) { color: #fff; background: #0b4c9b; }
.db-shell.landing-overview-active .asc-table tbody td { height: 62px; border-color: #e5edf6; }
.db-shell.landing-overview-active .asc-tr-total td { background-color: #eef5ff; }
.db-shell.landing-overview-active :is(.asc-seg-name, .asc-val-num, .asc-target-num, .asc-ref-num) { font-size: .75rem; }
.db-shell.landing-overview-active .asc-bar { opacity: .1; }
.db-shell.landing-overview-active .tcc-body { padding: 1.1rem; }
.db-shell.landing-overview-active .tcc-total-badge { background: #edf4fc; border-color: #d4e3f2; border-radius: 12px; }
.db-shell.landing-overview-active .tpc-body { padding: 1.2rem .4rem; }
.db-shell.landing-overview-active .trend-col-title { min-height: 2.6em; font-size: .65rem; line-height: 1.4; }
.db-shell.landing-overview-active .perf-table thead th { color: #335a7e; background: #f0f6fd; border-color: #dce8f4; font-size: .64rem; }
.db-shell.landing-overview-active .perf-table td { padding: .8rem .5rem; font-size: .72rem; }
.db-shell.landing-overview-active .cabang-performance-grid { gap: 1rem; }
.db-shell.landing-overview-active :is(.perf-panel-card, .rank-card) { overflow: hidden; background: #fff; border: 1px solid #cfdfef; border-radius: 16px; box-shadow: none; }
.db-shell.landing-overview-active :is(.perf-panel-head, .rank-card-head) { padding: 1rem 1.15rem; background: #edf4fc; border-bottom: 1px solid #dce8f4; }
.db-shell.landing-overview-active :is(.perf-panel-title, .rank-card-title) { color: var(--overview-ink); font-size: .87rem; letter-spacing: -.01em; }
.db-shell.landing-overview-active .perf-panel-subtitle { color: var(--overview-muted); font-size: .72rem; }
.db-shell.landing-overview-active .perf-bar-branch { color: #234c75; font-size: .76rem; }
.db-shell.landing-overview-active .perf-bar-value { font-size: .76rem; font-variant-numeric: tabular-nums; }
.db-shell.landing-overview-active .perf-bar-track { height: 7px; background: #edf3fa; border-radius: 4px; }
.db-shell.landing-overview-active .rank-row { padding: .85rem 1rem; border-color: #e3edf6; }
.db-shell.landing-overview-active .rank-name { color: var(--overview-ink); font-size: .78rem; }
.db-shell.landing-overview-active .rank-meta { color: var(--overview-muted); font-size: .65rem; }
.db-shell.landing-overview-active .rank-val { color: #0754bd; font-size: .88rem; font-variant-numeric: tabular-nums; }
.db-shell.landing-overview-active .rank-no { color: #0754bd; background: #edf4ff; border-color: #d6e5f6; }

/* Defined before the shell paints, including a useful settled/error state. */
.db-shell .loan-overview-loading { grid-column: 1 / -1; width: 100%; min-width: 0; }
.loan-overview-loading__status { display: flex; align-items: center; gap: .85rem; padding: 1rem 1.15rem; margin-bottom: 1rem; color: #12365c; background: #fff; border: 1px solid #cfdfef; border-radius: 12px; }
.loan-overview-loading__status > div { min-width: 0; flex: 1; }
.loan-overview-loading__icon { display: grid; flex: 0 0 40px; width: 40px; height: 40px; place-items: center; color: #0754bd; background: #edf4ff; border-radius: 11px; }
.loan-overview-loading__status h3 { margin: 0 0 .2rem; color: #12365c; font-size: .88rem; font-weight: 750; }
.loan-overview-loading__status p { margin: 0; color: #526b87; font-size: .74rem; line-height: 1.5; }
.loan-overview-loading__status button { flex-shrink: 0; min-height: 40px; padding: .55rem .8rem; color: #0754bd; background: #edf4ff; border: 1px solid #bdd4f0; border-radius: 8px; font: inherit; font-size: .74rem; font-weight: 700; cursor: pointer; }
.loan-overview-loading__status button[hidden] { display: none; }
.a6-skel-card-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.65rem; max-width: 1080px; margin: 0 auto; }
.a6-skel-card { min-width: 0; min-height: 350px; padding: 0.85rem; background: #fff; border: 1px solid #cfdfef; border-top: 3px solid #a6c5e6; border-radius: 12px; }
.a6-skel-card__label { display: block; min-height: 2.2em; color: #526b87; font-size: .72rem; font-weight: 750; line-height: 1.3; text-align: center; }
.a6-skel-line { position: relative; display: block; overflow: hidden; height: 14px; background: #e5edf7; border-radius: 5px; margin: 0 auto; }
.a6-skel-line::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,.8), transparent); transform: translateX(-100%); animation: loanOverviewShimmer 1.6s ease-in-out 3; }
.a6-skel-line--value { width: 70%; height: 26px; margin: 0.6rem auto 0.4rem; }
.a6-skel-line--caption { width: 35%; height: 10px; margin: 0 auto; }
.a6-skel-card__metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; margin-top: 1rem; }
.a6-skel-card__metrics .a6-skel-line { height: 36px; }
.a6-skel-card__deltas { display: grid; grid-template-columns: 1fr 1fr; gap: 0.25rem; margin-top: 1rem; }
.a6-skel-card__deltas .a6-skel-line { height: 30px; }
.loan-overview-loading.is-settled .a6-skel-line::after { animation: none; }
@keyframes loanOverviewShimmer { to { transform: translateX(100%); } }

/* Opaque light surface prevents old content bleeding through navigation. */
#dashboard-global-loader { background: #edf4fc !important; backdrop-filter: none !important; -webkit-backdrop-filter: none !important; }
#dashboard-global-loader .dashboard-loading-card { width: min(420px, 100%); padding: 1.8rem; background: #fff; border: 1px solid #cbddef; border-radius: 20px; box-shadow: 0 18px 55px -32px rgba(6,52,118,.35); }
#dashboard-global-loader .dashboard-loading-top { flex-direction: column; gap: 1.2rem; }
#dashboard-global-loader .dashboard-loading-text { color: #12365c; animation: none; font-size: 1.1rem; line-height: 1.4; }
#dashboard-global-loader .dashboard-loading-sub { color: #526b87; font-size: .8rem; line-height: 1.6; }

.db-shell.landing-overview-active .area6-scope-btn.active .area6-scope-btn__copy small,
.db-shell.landing-overview-active .ap-week-btn.active small { color: #e2efff !important; }
@container portfolio-cards (max-width: 1280px) {
  .db-shell.landing-overview-active .area6-panel .area6-card-grid, .a6-skel-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; max-width: none !important; margin: 0 auto !important; }
}
.db-shell.landing-overview-active.landing-narrow .area6-segment-container { grid-template-columns: minmax(0, 1fr) !important; }
.db-shell.landing-overview-active.landing-narrow .loan-overview-hero { align-items: flex-start; flex-direction: column; gap: 1rem; }
.db-shell.landing-overview-active.landing-narrow .loan-overview-hero__context { display: flex; flex-wrap: wrap; gap: .75rem 1.5rem; width: 100%; }
@container portfolio-cards (max-width: 680px) {
  .db-shell.landing-overview-active .area6-panel .area6-card-grid, .a6-skel-card-grid { grid-template-columns: minmax(0, 1fr) !important; max-width: none !important; margin: 0 auto !important; }
}
@container portfolio-cards (max-width: 280px) {
  .db-shell.landing-overview-active .ap-grid-2:not(:first-child) { grid-template-columns: minmax(0, 1fr) !important; }
  .db-shell.landing-overview-active .ap-grid-2:not(:first-child) .ap-metric-label { min-height: 0; }
}
@media (max-width: 767.98px) {
  .db-shell.landing-overview-active .area6-scope-content { padding: .8rem; }
  .loan-overview-hero { align-items: flex-start; flex-direction: column; padding: 1.3rem; gap: 1rem; }
  .loan-overview-hero__context { display: flex; flex-wrap: wrap; width: 100%; flex-shrink: 1; }
  .loan-overview-hero__context span, .loan-overview-hero__context strong { font-size: .72rem; }
  .db-shell.landing-overview-active .area6-panel .area6-card-grid, .a6-skel-card-grid { grid-template-columns: minmax(0, 1fr) !important; max-width: none !important; margin: 0 auto !important; }
  .loan-overview-loading__status { flex-wrap: wrap; padding: .9rem; }
  .loan-overview-loading__status button { min-height: 44px; margin-left: auto; }
  .a6-skel-card { min-height: 350px; }
}
@media (prefers-reduced-motion: reduce) {
  .a6-skel-line::after, #dashboard-global-loader .loading-ring, #dashboard-global-loader .loading-ring-inner { animation: none; }
  #dashboard-global-loader { transition: none !important; }
}
</style>
@include('dashboard.partials.portfolio-card-styles')
