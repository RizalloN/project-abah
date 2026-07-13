      <!-- Slide 1: Executive Cover -->
      <div class="apple-slide active" id="pres-slide-0">
        <div class="bri-cover-hero">
          <div class="bri-cover-copy animate-fade-in slide-delay-1">
            <div>
              <div class="pres-cover-eyebrow">Micro Directorate - Micro Sales Management Group</div>
              <h1 class="bri-cover-title">Materi Pendukung Asistensi</h1>
              <p class="pres-cover-subtitle" style="color:#0857c3; font-weight:900; font-size:1.18rem; margin-top:0.75rem;">
                Area 6 - Madiun, Magetan, Ngawi, Ponorogo
              </p>
              <p style="margin:1rem 0 0; color:#475569; font-size:0.92rem; line-height:1.55; font-weight:650;">
                Executive performance deck untuk membaca pinjaman, simpanan, profitabilitas, kualitas kredit, KTS, produktivitas mikro, dan strategi digital dalam satu alur presentasi.
              </p>
              <div class="bri-cover-meta">
                <div class="pres-cover-stat">
                  <div><span>Periode</span><strong id="pres-cover-period">-</strong></div>
                  <i class="far fa-calendar-alt" style="color:#0857c3;"></i>
                </div>
                <div class="pres-cover-stat">
                  <div><span>Data Loan</span><strong id="pres-cover-loan-period">-</strong></div>
                  <i class="far fa-clock" style="color:#e61c24;"></i>
                </div>
                <div class="pres-cover-stat">
                  <div><span>Audit KTS</span><strong id="pres-cover-kts">-</strong></div>
                  <i class="fas fa-users" style="color:#0857c3;"></i>
                </div>
                <div class="pres-cover-stat">
                  <div><span>Digital Strategy</span><strong id="pres-cover-digital-count">-</strong></div>
                  <i class="fas fa-bullseye" style="color:#e61c24;"></i>
                </div>
              </div>
            </div>
            <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-top:1.4rem;">
              <button type="button" class="pres-splash-accent-btn" id="pres-start-slides-btn" style="margin-top:0;">
                Masuk ke Deck Kinerja <i class="fas fa-arrow-right"></i>
              </button>
              <span style="font-size:0.72rem; color:#64748b; font-weight:800;">PT Bank Rakyat Indonesia (Persero) Tbk.</span>
            </div>
          </div>
          <div class="bri-cover-photo animate-fade-in slide-delay-2">
            <img src="{{ asset('images/bri-area6-building.png') }}" alt="Gedung BRI Area 6">
          </div>
          <div id="pres-cover-board" style="display:none;"></div>
        </div>
      </div>

      <!-- Slide 2: Highlight Pinjaman Area 6 -->
      <div class="apple-slide" id="pres-slide-1">
        <div class="bri-deck-title-row">
          <div>
            <div class="bri-deck-kicker">Area 6 Loan Highlight</div>
            <h2 class="bri-deck-title">Highlight Pinjaman Area 6</h2>
            <p class="bri-deck-subtitle">Format executive dashboard: OS, SML, NPL, pencapaian RKA, gap, dan pergerakan YtD/MtM/MtD.</p>
          </div>
        </div>
        <div class="bri-highlight-grid">
          <div class="bri-dashboard-card">
            <div class="card-head">Outstanding (OS)</div>
            <div class="metric-main">
              <div><span>OS Posisi</span><strong id="pres-kredit-total-volume">Rp -</strong></div>
              <div><span>RKA</span><strong id="pres-kredit-rka">-</strong></div>
            </div>
            <div style="text-align:center; padding:0 1rem 0.5rem;">
              <span class="pres-kpi-sub-trend pos" id="pres-kredit-total-trend"><i class="fas fa-arrow-up"></i> -% MtM</span>
            </div>
            <div class="achievement"><span>% Penc. RKA</span><strong id="pres-kredit-achievement">-</strong></div>
            <div class="metric-deltas">
              <div><span>YtD</span><strong id="pres-loan-os-ytd">-</strong></div>
              <div><span>MtM</span><strong id="pres-loan-os-mtm">-</strong></div>
              <div><span>MtD</span><strong id="pres-loan-os-mtd">-</strong></div>
            </div>
          </div>
          <div class="bri-dashboard-card">
            <div class="card-head cyan">Special Mention Loan (SML)</div>
            <div class="metric-main">
              <div><span>SML Posisi</span><strong id="pres-loan-sml-value">Rp -</strong></div>
              <div><span>Rasio</span><strong id="pres-loan-sml-ratio">-</strong></div>
            </div>
            <div class="achievement"><span>Growth/Risk</span><strong id="pres-loan-sml-status">-</strong></div>
            <div class="metric-deltas">
              <div><span>YtD</span><strong id="pres-loan-sml-ytd">-</strong></div>
              <div><span>MtM</span><strong id="pres-loan-sml-mtm">-</strong></div>
              <div><span>MtD</span><strong id="pres-loan-sml-mtd">-</strong></div>
            </div>
          </div>
          <div class="bri-dashboard-card">
            <div class="card-head red">Non Performing Loan (NPL)</div>
            <div class="metric-main">
              <div><span>NPL Posisi</span><strong id="pres-loan-npl-value">Rp -</strong></div>
              <div><span>Rasio</span><strong id="pres-loan-npl-ratio">-</strong></div>
            </div>
            <div class="achievement"><span>Growth/Risk</span><strong id="pres-loan-npl-status">-</strong></div>
            <div class="metric-deltas">
              <div><span>YtD</span><strong id="pres-loan-npl-ytd">-</strong></div>
              <div><span>MtM</span><strong id="pres-loan-npl-mtm">-</strong></div>
              <div><span>MtD</span><strong id="pres-loan-npl-mtd">-</strong></div>
            </div>
          </div>
        </div>
        <div class="bri-panel-grid">
          <div class="bri-inner-panel">
            <div class="bri-blue-panel-title"><i class="fas fa-chart-bar"></i> Kinerja Per Produk Kredit</div>
            <div class="bri-panel-body" id="pres-loan-product-rows"></div>
          </div>
          <div class="bri-inner-panel">
            <div class="bri-blue-panel-title"><i class="fas fa-chart-pie"></i> Komposisi Total</div>
            <div class="bri-panel-body">
              <div class="bri-donut-lite" id="pres-loan-mini-donut"></div>
              <div id="pres-loan-composition-summary" style="margin-top:0.75rem;"></div>
              <div class="pres-table-scroll" style="max-height:170px; margin-top:0.7rem;">
                <table class="pres-table-dense">
                  <thead>
                    <tr>
                      <th>Kantor Cabang</th>
                      <th style="text-align:right;">OS</th>
                      <th style="text-align:right;">RKA</th>
                      <th style="text-align:right;">Penc.</th>
                      <th style="text-align:right;">Porsi</th>
                    </tr>
                  </thead>
                  <tbody id="pres-kredit-branch-shares-tbody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 3: Highlight Simpanan Area 6 -->
      <div class="apple-slide bri-saving-highlight-slide" id="pres-slide-2">
        <div class="bri-deck-title-row">
          <div>
            <div class="bri-deck-kicker">Area 6 Funding Highlight</div>
            <h2 class="bri-deck-title">Highlight Simpanan Area 6</h2>
            <p class="bri-deck-subtitle">Total simpanan, giro, tabungan, deposito, dan CASA ditata setara dengan dashboard pinjaman.</p>
          </div>
        </div>
        <div class="bri-highlight-grid" id="pres-saving-cards"></div>
        <div class="bri-panel-grid">
          <div class="bri-inner-panel">
            <div class="bri-blue-panel-title"><i class="fas fa-chart-simple"></i> Komposisi Simpanan</div>
            <div class="bri-panel-body">
              <div class="bri-raised-bars" id="pres-saving-bar-stage"></div>
            </div>
          </div>
          <div class="bri-inner-panel">
            <div class="bri-blue-panel-title"><i class="fas fa-table"></i> Ringkasan Komposisi</div>
            <div class="bri-panel-body" id="pres-saving-summary-table"></div>
          </div>
        </div>
      </div>

      <!-- Slide 4: Kinerja Pinjaman per Produk -->
      <div class="apple-slide" id="pres-slide-3">
        <div class="bri-deck-title-row">
          <div>
            <div class="bri-deck-kicker">Product Performance</div>
            <h2 class="bri-deck-title">Kinerja Pinjaman per Produk</h2>
            <p class="bri-deck-subtitle">Kupedes, KUR Mikro, Briguna Mikro, KPP, dan KUR Kecil dengan OS, SML, dan NPL.</p>
          </div>
        </div>
        <div class="bri-inner-panel" style="height:calc(100% - 5.2rem);">
          <div class="bri-blue-panel-title"><i class="fas fa-layer-group"></i> Product Board (Rp Juta)</div>
          <div class="bri-panel-body" style="height:calc(100% - 34px); display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div id="pres-loan-product-bars" style="overflow:auto;"></div>
            <div class="pres-table-scroll">
              <table class="pres-table-dense">
                <thead>
                  <tr>
                    <th>Produk</th>
                    <th style="text-align:right;">OS</th>
                    <th style="text-align:right;">SML</th>
                    <th style="text-align:right;">NPL</th>
                  </tr>
                </thead>
                <tbody id="pres-loan-product-table"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 5: Komposisi Pinjaman -->
      <div class="apple-slide" id="pres-slide-4">
        <div class="bri-deck-title-row">
          <div>
            <div class="bri-deck-kicker">Loan Composition</div>
            <h2 class="bri-deck-title">Komposisi Portofolio Pinjaman</h2>
            <p class="bri-deck-subtitle">OS sehat, SML, dan NPL diringkas dalam satu panel risk composition.</p>
          </div>
        </div>
        <div class="bri-panel-grid" style="height:calc(100% - 5.2rem);">
          <div class="bri-inner-panel">
            <div class="bri-blue-panel-title"><i class="fas fa-chart-pie"></i> Total Portfolio Credit</div>
            <div class="bri-panel-body" style="display:grid; place-items:center; height:calc(100% - 34px);">
              <div class="bri-donut-lite" id="pres-loan-composition-donut" style="width:310px; height:310px;"></div>
            </div>
          </div>
          <div class="bri-inner-panel">
            <div class="bri-blue-panel-title"><i class="fas fa-list-check"></i> Status Portfolio</div>
            <div class="bri-panel-body" id="pres-loan-composition-legend"></div>
          </div>
        </div>
      </div>

      <!-- Slide 6: Trend Posisi Pinjaman -->
      <div class="apple-slide" id="pres-slide-5">
        <div class="bri-deck-title-row">
          <div>
            <div class="bri-deck-kicker">Trendline Performance</div>
            <h2 class="bri-deck-title">Trend Posisi Pinjaman dan Kualitas</h2>
            <p class="bri-deck-subtitle">Pergerakan simpanan, OS, SML, dan NPL pada titik YtD, MtM, MtD, dan posisi berjalan.</p>
          </div>
        </div>
        <div class="pres-grid-2col pres-timeseries-grid animate-fade-in slide-delay-3">
          <div class="pres-glass-card pres-timeseries-card">
            <div class="pres-timeseries-card-header"><span>Dana vs Kredit Area 6</span></div>
            <div class="pres-timeseries-chart-box"><canvas id="pres-timeseries-chart-dana"></canvas></div>
          </div>
          <div class="pres-glass-card pres-timeseries-card">
            <div class="pres-timeseries-card-header"><span>OS, SML, dan NPL</span></div>
            <div class="pres-timeseries-chart-box"><canvas id="pres-timeseries-chart-quality"></canvas></div>
          </div>
        </div>
      </div>

      <!-- Slide 7: Komposisi Simpanan -->
      <div class="apple-slide" id="pres-slide-6">
        <div class="bri-deck-title-row">
          <div>
            <div class="bri-deck-kicker">Funding Mix</div>
            <h2 class="bri-deck-title">Komposisi Simpanan dan CASA</h2>
            <p class="bri-deck-subtitle">Giro, tabungan, deposito, dan CASA ditampilkan sebagai komposisi nominal dan rasio.</p>
          </div>
        </div>
        <div class="bri-panel-grid" style="height:calc(100% - 5.2rem);">
          <div class="bri-inner-panel">
            <div class="bri-blue-panel-title"><i class="fas fa-chart-column"></i> Komposisi Simpanan</div>
            <div class="bri-panel-body"><div class="bri-raised-bars" id="pres-saving-composition-bars"></div></div>
          </div>
          <div class="bri-inner-panel">
            <div class="bri-blue-panel-title"><i class="fas fa-scale-balanced"></i> CASA Lens</div>
            <div class="bri-panel-body" id="pres-saving-composition-table"></div>
          </div>
        </div>
      </div>

      <!-- Slide 8: Branch War Room -->
      <div class="apple-slide" id="pres-slide-7">
        <div class="bri-deck-title-row">
          <div>
            <div class="bri-deck-kicker">Branch War Room</div>
            <h2 class="bri-deck-title">Perbandingan 4 Kantor Cabang Area 6</h2>
            <p class="bri-deck-subtitle">Madiun, Magetan, Ngawi, dan Ponorogo dibandingkan dari pinjaman, simpanan, SML, NPL, dan kontribusi.</p>
          </div>
        </div>
        <div class="bri-branch-grid" id="pres-branch-war-room"></div>
      </div>

      <!-- Slide 9: Profitability Almafacts -->
      <div class="apple-slide" id="pres-slide-8">
        <div class="bri-deck-title-row">
          <div>
            <div class="bri-deck-kicker">Almafacts Profitability</div>
            <h2 class="bri-deck-title">Profitability dan Financial Highlight</h2>
            <p class="bri-deck-subtitle">Laba Setelah Pajak, PPOP, NIM, BOPO, CER, ROA, dan CASA dari `ssa_almafacts`.</p>
          </div>
          <div class="pres-slide-counter-badge" id="pres-financial-period">-</div>
        </div>
        <div class="bri-finance-grid" id="pres-financial-cards"></div>
        <div class="bri-inner-panel" style="margin-top:0.85rem;">
          <div class="bri-blue-panel-title"><i class="fas fa-building"></i> Laba Setelah Pajak per Cabang</div>
          <div class="bri-panel-body" id="pres-financial-branches"></div>
        </div>
      </div>

      <!-- Slide 10: Performance vs RKA -->
      <div class="apple-slide" id="pres-slide-9">
        <div class="bri-deck-title-row">
          <div>
            <div class="bri-deck-kicker">Performance vs RKA</div>
            <h2 class="bri-deck-title">Kinerja dan Kualitas OS</h2>
          </div>
          <div class="pres-control-bar animate-fade-in slide-delay-2">
            <div class="pres-control-group" id="pres-metric-toggle">
              <button type="button" class="pres-toggle-btn active" data-metric="simpanan">Simpanan</button>
              <button type="button" class="pres-toggle-btn" data-metric="os">OS</button>
              <button type="button" class="pres-toggle-btn" data-metric="sml">SML</button>
              <button type="button" class="pres-toggle-btn" data-metric="npl">NPL</button>
            </div>
            <select class="pres-compact-select" id="pres-scope-select"></select>
            <div class="pres-control-group" id="pres-view-toggle">
              <button type="button" class="pres-toggle-btn active" data-view="table"><i class="fas fa-table"></i></button>
              <button type="button" class="pres-toggle-btn" data-view="chart"><i class="fas fa-chart-line"></i></button>
            </div>
          </div>
        </div>
        <div class="pres-explorer-grid">
          <div class="pres-explorer-side">
            <div class="pres-mini-stat-grid">
              <div class="pres-mini-stat"><span>Angka terbaru</span><strong id="pres-explorer-latest">-</strong></div>
              <div class="pres-mini-stat"><span>Jumlah baris</span><strong id="pres-explorer-count">-</strong></div>
              <div class="pres-mini-stat"><span>YtD</span><strong id="pres-explorer-ytd">-</strong></div>
              <div class="pres-mini-stat"><span>MtM / MtD</span><strong id="pres-explorer-mtm-mtd">-</strong></div>
            </div>
            <div class="pres-glass-card" style="padding:1rem;"><div class="pres-chart-wrap" style="height:270px;"><canvas id="pres-explorer-chart"></canvas></div></div>
          </div>
          <div class="pres-glass-card" style="padding:1rem; display:flex; flex-direction:column;">
            <div style="display:flex; justify-content:space-between; gap:1rem; margin-bottom:0.7rem;">
              <div><div class="pres-section-eyebrow" id="pres-explorer-caption">Area 6 Konsol</div><h3 id="pres-explorer-title" style="margin:0.18rem 0 0; font-size:1.18rem; font-weight:850;">Simpanan</h3></div>
              <div id="pres-explorer-periods" style="font-size:0.72rem; font-weight:750; color:#64748b;">-</div>
            </div>
            <div class="pres-table-scroll" id="pres-explorer-table-wrap"><table class="pres-table-dense"><thead id="pres-explorer-thead"></thead><tbody id="pres-explorer-tbody"></tbody></table></div>
            <div class="pres-chart-wrap hidden" id="pres-explorer-bar-wrap" style="height:420px;"><canvas id="pres-explorer-bar-chart"></canvas></div>
          </div>
        </div>
      </div>

      <!-- Slide 11: Segmentasi OS -->
      <div class="apple-slide" id="pres-slide-10">
        <div class="bri-deck-title-row">
          <div><div class="bri-deck-kicker">Segment Explorer</div><h2 class="bri-deck-title">Segmentasi OS SME, Mikro, dan Konsumer</h2></div>
          <div style="display:flex; align-items:center; gap:0.5rem;">
            <div class="pres-control-group" id="pres-seg-metric-toggle">
              <button type="button" class="pres-toggle-btn active" data-seg-metric="sme_os">SME</button>
              <button type="button" class="pres-toggle-btn" data-seg-metric="micro_os">Mikro</button>
              <button type="button" class="pres-toggle-btn" data-seg-metric="consumer_os">Konsumer</button>
            </div>
            <select class="pres-compact-select" id="pres-seg-scope-select"></select>
          </div>
        </div>
        <div class="pres-glass-card pres-segment-panel" style="padding:1.1rem; height:calc(100% - 5.2rem); display:flex; flex-direction:column;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.7rem;">
            <div><span id="pres-seg-explorer-caption" style="color:#0857c3; font-weight:900;">Area 6 Konsol</span><h3 id="pres-seg-explorer-title" class="pres-text-gradient-blue" style="margin:0.15rem 0 0;">OS SME</h3></div>
            <span id="pres-seg-explorer-periods" style="font-size:0.72rem; font-weight:800; color:#64748b;">-</span>
          </div>
          <div class="pres-segment-summary-grid" id="pres-seg-summary-grid">
            <div class="pres-segment-summary-item"><span>Total Posisi</span><strong id="pres-seg-total-latest">-</strong></div>
            <div class="pres-segment-summary-item"><span>RKA</span><strong id="pres-seg-total-rka">-</strong></div>
            <div class="pres-segment-summary-item"><span>Penc. RKA</span><strong id="pres-seg-total-ach">-</strong></div>
            <div class="pres-segment-summary-item"><span>Jumlah Outlet</span><strong id="pres-seg-total-outlet">-</strong></div>
          </div>
          <div class="pres-table-scroll" style="flex:1; min-height:0;"><table class="pres-table-dense"><thead><tr><th id="pres-seg-first-col-label">Outlet</th><th style="text-align:right;">Terbaru</th><th style="text-align:right;">YtD</th><th style="text-align:right;">MtM</th><th style="text-align:right;">MtD</th><th style="text-align:right;">RKA</th><th style="text-align:right;">Gap RKA</th><th style="text-align:right;">Penc. RKA</th></tr></thead><tbody id="pres-seg-explorer-tbody"></tbody></table></div>
          <div class="pres-seg-footnote" id="pres-seg-footnote"><i class="fas fa-info-circle"></i><span>RKA merujuk pada Rencana Kerja Anggaran bulanan berjalan.</span></div>
        </div>
      </div>

      <!-- Slide 12: Risk Radar -->
      <div class="apple-slide" id="pres-slide-11">
        <div class="bri-deck-title-row">
          <div><div class="bri-deck-kicker">Risk Radar</div><h2 class="bri-deck-title">SML, NPL, LAR, dan Kualitas Kredit</h2></div>
          <select class="pres-compact-select" id="pres-risk-scope-select"></select>
        </div>
        <div class="pres-glass-card pres-panel-card" style="margin-bottom:1rem;">
          <div class="pres-panel-header pres-panel-header-cyan"><span><span class="pres-panel-icon"><i class="fas fa-chart-pie"></i></span> Komposisi Kualitas OS</span><span class="pres-panel-subtitle" id="pres-risk-subtitle">Area 6</span></div>
          <div class="pres-panel-body">
            <div class="pres-risk-composition-layout">
              <div class="pres-donut-shell"><div class="pres-donut-canvas-wrapper"><canvas id="pres-risk-composition-chart"></canvas><div class="pres-donut-center"><div><strong id="pres-risk-donut-center">-</strong><span>LAR</span></div></div></div></div>
              <div>
                <h3 id="pres-lar-share-title" class="pres-text-gradient-blue" style="margin:0 0 0.8rem;">Portofolio Pinjaman</h3>
                <div class="pres-risk-metric-grid">
                  <div class="pres-risk-metric"><span>Lancar</span><strong id="pres-lar-healthy-val">-</strong></div>
                  <div class="pres-risk-metric"><span>Lancar Restruk</span><strong id="pres-lar-restruk-val">-</strong></div>
                  <div class="pres-risk-metric"><span>SML</span><strong id="pres-lar-sml-val">-</strong></div>
                  <div class="pres-risk-metric"><span>NPL</span><strong id="pres-lar-npl-val">-</strong></div>
                  <div class="pres-risk-metric"><span>LAR</span><strong id="pres-lar-ratio-val">-</strong></div>
                </div>
                <div class="pres-spectrum-bar" style="margin-top:0.85rem;"><div class="pres-spectrum-segment" id="pres-spectrum-healthy" style="background:#10b981;"></div><div class="pres-spectrum-segment" id="pres-spectrum-restruk" style="background:#1155c8;"></div><div class="pres-spectrum-segment" id="pres-spectrum-sml" style="background:#f59e0b;"></div><div class="pres-spectrum-segment" id="pres-spectrum-npl" style="background:#ef4444;"></div></div>
              </div>
            </div>
          </div>
        </div>
        <div class="pres-glass-card pres-panel-card">
          <div class="pres-panel-header"><span><span class="pres-panel-icon"><i class="fas fa-table"></i></span> Komparasi Komposisi & Rasio</span><span class="pres-panel-subtitle">Cabang Area 6</span></div>
          <div class="pres-panel-body" style="padding:0.95rem;"><div class="pres-table-scroll" style="max-height:230px;"><table class="pres-table-dense"><thead><tr><th>Kantor Cabang</th><th style="text-align:right;">Total OS</th><th style="text-align:right;">SML Nominal</th><th style="text-align:right;">SML Rasio</th><th style="text-align:right;">NPL Nominal</th><th style="text-align:right;">NPL Rasio</th><th style="text-align:right;">LAR Nominal</th><th style="text-align:right;">LAR Rasio</th></tr></thead><tbody id="pres-quality-comparison-tbody"></tbody></table></div></div>
        </div>
      </div>

      <!-- Slide 13: SML Deep Dive -->
      <div class="apple-slide" id="pres-slide-12">
        <div class="bri-deck-title-row"><div><div class="bri-deck-kicker">SML Deep Dive</div><h2 class="bri-deck-title">Ranking SML Nominal dan Rasio</h2><p class="bri-deck-subtitle">Ranking ritel dan mikro untuk early warning kualitas kredit.</p></div></div>
        <div class="bri-deep-grid" id="pres-sml-deep-grid"></div>
      </div>

      <!-- Slide 14: NPL Deep Dive -->
      <div class="apple-slide" id="pres-slide-13">
        <div class="bri-deck-title-row"><div><div class="bri-deck-kicker">NPL Deep Dive</div><h2 class="bri-deck-title">Ranking NPL Nominal dan Rasio</h2><p class="bri-deck-subtitle">Area prioritas penanganan kualitas kredit.</p></div></div>
        <div class="bri-deep-grid" id="pres-npl-deep-grid"></div>
      </div>

      <!-- Slide 15: KTS & Produktivitas Mikro -->
      <div class="apple-slide" id="pres-slide-14">
        <div class="bri-deck-title-row">
          <div><div class="bri-deck-kicker">KTS & Productivity Intelligence</div><h2 class="bri-deck-title">KTS, Produktivitas Mantri, dan Decision Engine</h2></div>
          <div class="pres-control-bar" style="margin:0;">
            <div class="pres-control-group" id="pres-kts-category-toggle"><button type="button" class="pres-toggle-btn active" data-kts-category="membaik">Membaik</button><button type="button" class="pres-toggle-btn" data-kts-category="memburuk">Memburuk</button></div>
            <div class="pres-control-group" id="pres-kts-scope-toggle"><button type="button" class="pres-toggle-btn active" data-kts-scope="ritel">Ritel</button><button type="button" class="pres-toggle-btn" data-kts-scope="micro">Micro</button></div>
          </div>
        </div>
        <div class="pres-kts-grid">
          <div class="pres-kts-summary">
            <div class="pres-mini-stat"><span>Total rekening</span><strong id="pres-kts-total-count">-</strong></div>
            <div class="pres-mini-stat"><span>OS terdampak</span><strong id="pres-kts-total-os">-</strong></div>
            <div class="pres-mini-stat"><span>Periode</span><strong id="pres-kts-period">-</strong></div>
            <div class="pres-glass-card" style="padding:1rem;"><div class="pres-section-eyebrow" id="pres-kts-caption">KTS Membaik</div><h3 id="pres-kts-title" style="margin:0.2rem 0 0;">Ritel</h3><p id="pres-kts-note" style="margin:0.45rem 0 0; color:#475569; font-size:0.82rem; line-height:1.55;">KTS dipisahkan berdasarkan arah selisih kolektibilitas aktual terhadap kolektibilitas seharusnya.</p></div>
            <div id="pres-micro-productivity-grid"></div>
          </div>
          <div class="pres-glass-card" style="display:flex; flex-direction:column; overflow:hidden; padding:1rem;">
            <div class="pres-table-scroll" style="max-height:520px;"><table class="pres-table-dense"><thead><tr><th>#</th><th>Debitur / Rekening</th><th>Unit Kerja</th><th style="text-align:center;">Kolek Aktual vs Seharusnya</th><th style="text-align:right;">Baki Debet</th></tr></thead><tbody id="pres-kts-tbody"></tbody></table></div>
          </div>
        </div>
      </div>

      <!-- Slide 16: Digital Strategy & Closing -->
      <div class="apple-slide" id="pres-slide-15">
        <div class="bri-deck-title-row">
          <div><div class="bri-deck-kicker">Digital Strategy & Executive Closing</div><h2 class="bri-deck-title" style="color:#ffffff;">8 Strategi Dana Digital dan Prioritas Aksi</h2><p class="bri-deck-subtitle" style="color:rgba(255,255,255,0.74);">EDC, QRIS, QLola, BRImo, BRILink, CASA, Dormant, Payroll, dan fokus kerja berikutnya.</p></div>
          <div class="pres-control-group" id="pres-digital-view-toggle"><button type="button" class="pres-toggle-btn active" data-digital-view="table"><i class="fas fa-table"></i></button><button type="button" class="pres-toggle-btn" data-digital-view="timeseries"><i class="fas fa-chart-line"></i></button></div>
        </div>
        <div class="pres-digital-layout" style="height:calc(100% - 6rem);">
          <div class="pres-digital-list" id="pres-digital-cards-grid"></div>
          <div class="pres-glass-card" style="padding:1rem; min-height:0;">
            <div id="pres-digital-table-wrap" class="pres-table-scroll" style="max-height:440px;"><table class="pres-table-dense"><thead><tr><th>Strategi</th><th style="text-align:right;">Angka terbaru</th><th style="text-align:right;">Volume/Pendukung</th><th style="text-align:right;">Growth</th><th>Sumber</th></tr></thead><tbody id="pres-digital-tbody"></tbody></table></div>
            <div id="pres-digital-chart-wrap" class="pres-chart-wrap hidden" style="height:440px;"><canvas id="pres-digital-chart"></canvas></div>
            <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-top:0.9rem;">
              <div style="color:#475569; font-size:0.84rem; font-weight:800;">Prioritas: CASA, kualitas kredit, KTS, produktivitas mikro, dan digital acquisition.</div>
              <button type="button" class="pres-splash-accent-btn" id="pres-finish-close-btn" style="margin:0; background:#e61c24 !important; border-color:#b91c1c !important;">Tutup & Selesai <i class="fas fa-check-circle"></i></button>
            </div>
          </div>
        </div>
      </div>
