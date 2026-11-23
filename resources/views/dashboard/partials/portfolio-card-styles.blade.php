<style>
/* Shared portfolio cards for Pinjaman segments and Simpanan. */
.db-shell.landing-cards-unified {
  --overview-ink: #12365c;
  --overview-muted: #526b87;
}
.db-shell.landing-cards-unified .area6-panel { container: portfolio-cards / inline-size; }
.db-shell.landing-cards-unified .area6-card-grid--three { --portfolio-columns: 3; }
.db-shell.landing-cards-unified .area6-panel .area6-scope-content .area6-card-grid {
  display: grid !important;
  width: 100%;
  max-width: none;
  margin: 0 auto;
  padding: 0 !important;
  gap: 1rem !important;
  grid-template-columns: repeat(var(--portfolio-columns, 4), minmax(0, 1fr)) !important;
}
.db-shell.landing-cards-unified .area6-card-premium {
  --metric-accent: #0754bd;
  --metric-soft: #edf4ff;
  position: relative !important;
  min-width: 0;
  margin: 0;
  overflow: hidden;
  color: var(--overview-ink);
  background: #fff;
  border: 1px solid #cfdfef;
  border-top: 3px solid var(--metric-accent);
  border-radius: 12px;
  box-shadow: 0 4px 14px -10px rgba(6,52,118,.22);
  transition: box-shadow .2s ease, border-color .2s ease, transform .2s ease;
  text-align: center;
  display: flex;
  flex-direction: column;
}
.db-shell.landing-cards-unified .area6-card-premium[data-metric="sml"] { --metric-accent: #08769b; --metric-soft: #edf8fc; }
.db-shell.landing-cards-unified .area6-card-premium[data-metric="npl"] { --metric-accent: #ad3454; --metric-soft: #fdf0f4; }
.db-shell.landing-cards-unified .area6-card-premium[data-metric="recovery"] { --metric-accent: #087b69; --metric-soft: #edf9f5; }
.db-shell.landing-cards-unified .area6-card-premium:hover { transform: translateY(-2px); border-color: var(--metric-accent); box-shadow: 0 8px 22px -12px rgba(6,52,118,.3); }

/* Header: Rata Tengah, Ringkas, dan Elegan */
.db-shell.landing-cards-unified .area6-card-premium .ap-header {
  position: relative;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  text-align: center !important;
  min-height: 40px !important;
  height: 40px !important;
  padding: 0.35rem 2.2rem !important;
  background: #fff !important;
  border-radius: 0;
  border-bottom: 1px solid #e4edf6;
}
.db-shell.landing-cards-unified .ap-header-title {
  color: var(--overview-ink) !important;
  font-size: .72rem;
  font-weight: 800;
  letter-spacing: .02em;
  line-height: 1.25;
  text-align: center;
  text-shadow: none;
  width: 100%;
}
.db-shell.landing-cards-unified .ap-badge {
  position: absolute !important;
  top: 8px !important;
  left: 10px !important;
  width: 24px !important;
  height: 24px !important;
  color: var(--metric-accent) !important;
  background: var(--metric-soft) !important;
  border: 0;
  border-radius: 6px;
  box-shadow: none;
  font-size: .74rem !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  margin: 0 !important;
  transform: none !important;
  z-index: 2;
}
.db-shell.landing-cards-unified .area6-card-premium:hover .ap-badge { transform: scale(1.08) !important; }

/* Body: Padding Pas, Rata Tengah, Padat */
.db-shell.landing-cards-unified .area6-card-premium .ap-body {
  padding: 0.65rem 0.75rem !important;
  background: #fff;
  display: flex;
  flex-direction: column;
  text-align: center;
}

/* Row 1: Realisasi vs Target (RKA) */
.db-shell.landing-cards-unified .ap-grid-2:first-child {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 0.25rem !important;
  margin-bottom: 0.35rem !important;
  text-align: center !important;
}
.db-shell.landing-cards-unified .ap-grid-2::after { display: none; }

.db-shell.landing-cards-unified .ap-grid-2:first-child .ap-metric-col:first-child {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  width: 100% !important;
  padding: 0.1rem 0 !important;
  text-align: center !important;
}
.db-shell.landing-cards-unified .ap-grid-2:first-child .ap-metric-col:first-child .ap-metric-label {
  font-size: .67rem !important;
  font-weight: 650;
  color: var(--overview-muted);
  text-align: center !important;
  line-height: 1.2;
  margin-bottom: 0.1rem;
  min-height: 0 !important;
}
.db-shell.landing-cards-unified .ap-grid-2:first-child .ap-metric-col:first-child .ap-metric-val {
  color: var(--metric-accent) !important;
  font-size: 1.32rem !important;
  font-weight: 800;
  letter-spacing: -.025em !important;
  line-height: 1.15 !important;
  text-align: center !important;
  margin: 0.05rem 0 !important;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.db-shell.landing-cards-unified .ap-grid-2:first-child .ap-metric-col:first-child .ap-metric-sub {
  color: #60748a;
  font-size: .58rem;
  text-align: center !important;
  margin-top: 0.05rem;
}

/* Target RKA: Rata Tengah Horizontal Chip/Pill */
.db-shell.landing-cards-unified .ap-grid-2:first-child .ap-metric-col + .ap-metric-col {
  display: flex !important;
  flex-direction: row !important;
  align-items: center !important;
  justify-content: center !important;
  flex-wrap: wrap !important;
  gap: 0.2rem 0.4rem !important;
  width: 100% !important;
  padding: 0.28rem 0.45rem !important;
  margin-top: 0.2rem !important;
  border-top: 1px solid #e3edf7 !important;
  background: #f8fbff !important;
  border-radius: 7px !important;
  text-align: center !important;
}
.db-shell.landing-cards-unified .ap-grid-2:first-child .ap-metric-col + .ap-metric-col .ap-metric-label {
  font-size: .63rem !important;
  font-weight: 650;
  color: var(--overview-muted);
  text-align: center !important;
  margin: 0 !important;
  min-height: 0 !important;
  line-height: 1.2;
}
.db-shell.landing-cards-unified .ap-grid-2:first-child .ap-metric-col + .ap-metric-col .ap-metric-val {
  font-size: .8rem !important;
  font-weight: 750;
  color: var(--overview-ink) !important;
  letter-spacing: -.01em;
  margin: 0 !important;
  line-height: 1.2;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.db-shell.landing-cards-unified .ap-grid-2:first-child .ap-metric-col + .ap-metric-col .ap-metric-sub {
  font-size: .58rem;
  color: #60748a;
  text-align: center !important;
  margin: 0 !important;
  line-height: 1.2;
}

/* Row 2: Achievement % vs Gap - Rata Tengah Simetris */
.db-shell.landing-cards-unified .ap-grid-2:not(:first-child) {
  display: grid !important;
  grid-template-columns: 1fr 1fr !important;
  gap: 0.4rem !important;
  margin-top: 0.3rem !important;
  text-align: center !important;
}
.db-shell.landing-cards-unified .ap-grid-2:not(:first-child) .ap-metric-col {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  text-align: center !important;
  padding: 0.35rem 0.25rem !important;
  background: #fbfdff !important;
  border: 1px solid #e7f0f9 !important;
  border-radius: 8px !important;
  min-width: 0 !important;
}
.db-shell.landing-cards-unified .ap-grid-2:not(:first-child) .ap-metric-label {
  font-size: .61rem !important;
  font-weight: 650;
  color: var(--overview-muted);
  text-align: center !important;
  line-height: 1.2;
  margin-bottom: 0.1rem;
  min-height: 2.3em !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
}
.db-shell.landing-cards-unified .ap-metric-pct-val,
.db-shell.landing-cards-unified .ap-metric-gap-val {
  font-size: .95rem !important;
  font-weight: 800;
  text-align: center !important;
  margin: 0.05rem 0 !important;
  line-height: 1.15 !important;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.db-shell.landing-cards-unified .ap-grid-2:not(:first-child) .ap-metric-sub {
  font-size: .56rem;
  color: #60748a;
  text-align: center !important;
  margin-top: 0.05rem;
}

/* Prognosa Strip: Rata Tengah, Ringkas */
.db-shell .area6-scope-content .ap-prognosa-strip[hidden] { display: none !important; }
.db-shell.landing-cards-unified .ap-prognosa-strip {
  display: grid !important;
  grid-template-columns: 1fr 1fr !important;
  gap: 0.3rem !important;
  margin-top: 0.35rem !important;
  padding: 0.35rem 0.45rem !important;
  background: var(--metric-soft) !important;
  border: 1px solid #dce7f1 !important;
  border-radius: 8px !important;
  text-align: center !important;
}
.db-shell.landing-cards-unified .ap-prognosa-item {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  text-align: center !important;
  min-width: 0 !important;
  padding: 0.1rem !important;
}
.db-shell.landing-cards-unified .ap-prognosa-item + .ap-prognosa-item {
  border-left: 1px solid rgba(0, 0, 0, 0.06) !important;
}
.db-shell.landing-cards-unified .ap-prognosa-label {
  min-height: 2.4em;
  color: #526b87;
  font-size: .57rem !important;
  font-weight: 700;
  line-height: 1.2 !important;
  text-align: center !important;
  text-transform: uppercase;
  margin-bottom: 0.05rem;
}
.db-shell.landing-cards-unified .ap-prognosa-value {
  font-size: .88rem !important;
  font-weight: 800;
  text-align: center !important;
  margin: 0.05rem 0 !important;
  line-height: 1.15 !important;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.db-shell.landing-cards-unified .ap-prognosa-unit {
  min-height: 2.4em;
  color: #526b87;
  font-size: .54rem !important;
  text-align: center !important;
  line-height: 1.2;
}

/* Dashed Divider */
.db-shell.landing-cards-unified .ap-dashed-divider {
  margin: 0.35rem 0 !important;
  border: 0 !important;
  border-top: 1px dashed #cbd5e1 !important;
}

/* Deltas Grid: Rata Tengah, Kompak */
.db-shell.landing-cards-unified .ap-deltas {
  display: grid !important;
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  gap: 0.25rem !important;
  text-align: center !important;
}
.db-shell.landing-cards-unified .ap-delta-item {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 0.25rem 0.2rem !important;
  background: #f8fbff !important;
  border: 1px solid #e3edf7 !important;
  border-radius: 6px !important;
  text-align: center !important;
  min-width: 0 !important;
}
.db-shell.landing-cards-unified .ap-delta-label {
  color: #526b87;
  font-size: .57rem !important;
  font-weight: 750;
  text-align: center !important;
  margin-bottom: 0.05rem;
  text-transform: uppercase;
}
.db-shell.landing-cards-unified .ap-delta-val {
  font-size: .72rem !important;
  font-weight: 750;
  text-align: center !important;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.db-shell.landing-cards-unified .ap-delta-arrow {
  font-size: .56rem !important;
  text-align: center !important;
  margin-top: 0.05rem;
}

/* Footer Link: Rata Tengah */
.db-shell.landing-cards-unified .loan-overview-card-link {
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 0.35rem !important;
  margin-top: auto !important;
  padding: 0.42rem 0.65rem !important;
  color: #0754bd !important;
  background: #f7faff !important;
  border-top: 1px solid #e0eaf4 !important;
  font-size: .67rem !important;
  font-weight: 750;
  text-align: center !important;
}
.db-shell.landing-cards-unified .loan-overview-card-link i {
  font-size: .62rem !important;
  transition: transform .18s ease;
}
.db-shell.landing-cards-unified .area6-card-premium:hover .loan-overview-card-link i {
  transform: translateX(3px);
}
.db-shell.landing-cards-unified .area6-card-premium[data-metric="tabungan"] { --metric-accent: #0754bd; --metric-soft: #edf4ff; }
.db-shell.landing-cards-unified .area6-card-premium[data-metric="deposito"] { --metric-accent: #08769b; --metric-soft: #edf8fc; }
.db-shell.landing-cards-unified .area6-card-premium[data-metric="giro"] { --metric-accent: #6350a2; --metric-soft: #f3f0fb; }
.db-shell.landing-cards-unified .area6-card-premium[data-metric="casa"] { --metric-accent: #087b69; --metric-soft: #edf9f5; }
@container portfolio-cards (max-width: 1280px) {
  .db-shell.landing-cards-unified .area6-panel .area6-scope-content .area6-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
}
@container portfolio-cards (min-width: 1000px) {
  .db-shell.landing-cards-unified .area6-panel .area6-scope-content .area6-card-grid--three { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
}
@container portfolio-cards (max-width: 680px) {
  .db-shell.landing-cards-unified .area6-panel .area6-scope-content .area6-card-grid { grid-template-columns: minmax(0, 1fr) !important; }
}
@container portfolio-cards (max-width: 280px) {
  .db-shell.landing-cards-unified .ap-grid-2:not(:first-child) { grid-template-columns: minmax(0, 1fr) !important; }
  .db-shell.landing-cards-unified .ap-grid-2:not(:first-child) .ap-metric-label { min-height: 0; }
}
@media (prefers-reduced-motion: reduce) {
  .db-shell.landing-cards-unified .area6-card-premium,
  .db-shell.landing-cards-unified .loan-overview-card-link i { transition: none !important; }
}
</style>
