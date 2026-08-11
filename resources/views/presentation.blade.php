<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kinerja Area 6 - Madiun, Magetan, Ngawi, Ponorogo</title>

  <!-- FontAwesome -->
  <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
  <!-- Google Font: Inter -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <!-- Chart.js local asset for reliable offline/local loading -->
  <script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
  <!-- Leaflet local assets keep the market-share map available without a CDN. -->
  <link rel="stylesheet" href="{{ asset('vendor/leaflet-1.9.4/leaflet.css') }}">
  <script src="{{ asset('vendor/leaflet-1.9.4/leaflet.js') }}"></script>
  <link rel="manifest" href="{{ asset('manifest-presentation.webmanifest') }}">
  <meta name="theme-color" content="#0857c3">

  @vite('resources/css/presentation/presentation.css')
</head>
<body>

  <!-- Apple Presentation Mode Container -->
  <div class="apple-presentation-mode active" id="apple-presentation-mode">
    <div class="pres-executive-grid" aria-hidden="true"></div>
    <div class="pres-executive-scan" aria-hidden="true"></div>
    <div class="pres-executive-edge left" aria-hidden="true"></div>
    <div class="pres-executive-edge right" aria-hidden="true"></div>

    <!-- Top Bar -->
    <div class="pres-top-bar">
      <div class="pres-title-brand">
        <!-- Swap: Kembali ke Dashboard button now on the top left -->
        <a href="{{ route('dashboard', ['periode' => $selectedPeriod]) }}" class="pres-nav-btn-back" title="Kembali ke Dashboard Utama" style="margin-right: 0.25rem;">
          <i class="fas fa-arrow-left"></i> <span class="pres-back-text">Kembali ke Dashboard</span>
        </a>
        <div class="pres-logo-divider" style="height: 18px; margin: 0 0.5rem 0 0.75rem; align-self: center;"></div>
        <div class="pres-title-lbl">Kinerja Area 6 <span>- Madiun, Magetan, Ngawi, Ponorogo</span></div>
      </div>
      <div class="pres-controls-right">
        <label class="pres-global-scope-control" title="Pilih cakupan angka untuk seluruh deck">
          <i class="fas fa-building" aria-hidden="true"></i>
          <span>
            <small>Cakupan deck</small>
            <select id="pres-global-scope-selector" aria-label="Pilih cakupan presentasi"></select>
          </span>
        </label>
        <label class="pres-prognosa-control" id="pres-prognosa-control" title="Tampilkan prognosa tertulis terbaru pada matriks perbandingan">
          <input type="checkbox" id="pres-prognosa-toggle" aria-label="Gunakan Prognosa Weekly">
          <span class="pres-prognosa-switch" aria-hidden="true"><i></i></span>
          <span class="pres-prognosa-copy">
            <small>Prognosa</small>
            <strong id="pres-prognosa-state">Nonaktif</strong>
          </span>
        </label>
        <button type="button" class="pres-ppt-export-btn" title="Unduh PowerPoint siap pakai" onclick="document.getElementById('ppt-export-dialog').showModal()">
          <i class="fas fa-file-powerpoint" aria-hidden="true"></i>
          <span>Unduh PPT</span>
        </button>
        <div class="pres-quick-tools" role="toolbar" aria-label="Peralatan presentasi">
          <button type="button" id="pres-compare-btn" title="Bandingkan dengan periode lain (C)" aria-label="Bandingkan periode">
            <i class="fas fa-columns" aria-hidden="true"></i>
          </button>
          <button type="button" id="pres-note-btn" title="Catatan slide (N)" aria-label="Catatan slide">
            <i class="fas fa-sticky-note" aria-hidden="true"></i>
          </button>
          <button type="button" id="pres-share-btn" title="Salin tautan slide aktif" aria-label="Salin tautan slide">
            <i class="fas fa-link" aria-hidden="true"></i>
          </button>
          <button type="button" id="pres-theme-btn" title="Ubah tema (D)" aria-label="Ubah tema">
            <i class="fas fa-adjust" aria-hidden="true"></i>
          </button>
          <button type="button" id="pres-print-btn" title="Cetak atau simpan PDF" aria-label="Cetak presentasi">
            <i class="fas fa-print" aria-hidden="true"></i>
          </button>
        </div>
        <div class="pres-live-chip" title="Data presentasi aktif dari payload dashboard">
          <span class="pres-live-dot"></span>
          <span id="pres-live-status">Live Dashboard</span>
        </div>
        <!-- co-branding Danantara & BRI appears exactly once on the top right -->
        <div class="pres-logos-wrapper">
          <img class="pres-logo-brand logo-danantara" src="{{ asset('images/danantara-logo-template.png') }}" alt="Danantara">
          <div class="pres-logo-divider"></div>
          <!-- Custom SVG 130 Tahun BRI Badge -->
          <svg class="bri-130-badge" viewBox="0 0 145 40" style="height: 20px; width: auto; display: block; overflow: visible;">
            <path d="M 14 10 L 20 6 L 20 34 M 14 34 L 26 34" fill="none" stroke="#0857c3" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M 32 6 L 48 6 L 40 19 C 46 19 50 23 52 28 C 52 33 48 35 42 35 C 36 35 34 32 34 31" fill="none" stroke="#e61c24" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M 68 6 C 58 6 56 19 56 21 C 58 23 60 35 70 35 C 80 35 82 23 82 21 C 82 19 80 6 70 6 Z" fill="none" stroke="#0857c3" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M 66 21 C 68 17 72 17 74 21 C 76 25 70 29 70 29 C 70 29 64 25 66 21 Z" fill="#e61c24" opacity="0.85"></path>
            <text x="88" y="21" font-family="'Inter', sans-serif" font-weight="900" font-size="12" fill="#0857c3">TAHUN</text>
            <text x="88" y="32" font-family="'Inter', sans-serif" font-weight="900" font-size="9" fill="#e61c24" letter-spacing="0.05em">1895-2025</text>
          </svg>
          <div class="pres-logo-divider"></div>
          <img class="pres-logo-brand logo-bri" src="{{ asset('images/bri-logo-template.png') }}" alt="BRI">
        </div>

        <!-- Sembunyikan tanggal periode selector secara visual agar JS page loader tidak error -->
        <select id="pres-periode-selector" class="pres-date-picker-select" style="display: none;">
          @foreach($periods as $p)
            <option value="{{ $p }}" {{ $p === $selectedPeriod ? 'selected' : '' }}>
              {{ \Carbon\Carbon::parse($p)->translatedFormat('d M Y') }}
            </option>
          @endforeach
        </select>
      </div>
    </div>

    <dialog class="pres-export-dialog" id="ppt-export-dialog">
      <form class="pres-export-form" id="pres-export-form" method="POST" action="{{ route('dashboard.presentation.export-pptx') }}">
        @csrf
        <input type="hidden" name="periode" value="{{ $selectedPeriod }}">
        <div class="pres-export-head">
          <h2>Siapkan PowerPoint</h2>
          <p>Deck memakai template BRI, data periode aktif, tabel editable, grafik, dan conditional formatting.</p>
        </div>
        <div class="pres-export-body">
          <label class="pres-export-field">
            <span>Judul cover</span>
            <input type="text" name="title" maxlength="140" value="Performance Review - Area 6 Region 13" required>
          </label>
          <label class="pres-export-field pres-export-scope-field">
            <span>Cakupan seluruh presentasi</span>
            <select name="global_scope" id="pres-export-global-scope">
              @foreach(['area6' => 'Area 6 Konsolidasi', 'KC Madiun' => 'KC Madiun', 'KC Magetan' => 'KC Magetan', 'KC Ngawi' => 'KC Ngawi', 'KC Ponorogo' => 'KC Ponorogo'] as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
              @endforeach
            </select>
          </label>
          <label class="pres-export-prognosa">
            <input type="hidden" name="use_prognosa" value="0">
            <input type="checkbox" name="use_prognosa" id="pres-export-use-prognosa" value="1">
            <span>
              <strong>Gunakan Prognosa Weekly</strong>
              <small id="pres-export-prognosa-note">Tambahkan posisi prognosa tertulis terbaru dan delta terhadap posisi aktual.</small>
            </span>
          </label>
          <div class="pres-export-grid">
            <section class="pres-export-group">
              <h3>Funding / Dana</h3>
              <label class="pres-export-field"><span>Cabang timeseries</span><select name="funding_scope">@foreach(['area6' => 'Area 6 Konsolidasi', 'KC Madiun' => 'KC Madiun', 'KC Magetan' => 'KC Magetan', 'KC Ngawi' => 'KC Ngawi', 'KC Ponorogo' => 'KC Ponorogo'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
              <label class="pres-export-field"><span>Produk timeseries</span><select name="funding_product"><option value="total">Total Simpanan</option><option value="giro">Giro</option><option value="tabungan">Tabungan</option><option value="deposito">Deposito</option><option value="casa">CASA</option></select></label>
            </section>
            <section class="pres-export-group">
              <h3>SME</h3>
              <label class="pres-export-field"><span>Cabang timeseries</span><select name="sme_scope">@foreach(['area6' => 'Area 6 Konsolidasi', 'KC Madiun' => 'KC Madiun', 'KC Magetan' => 'KC Magetan', 'KC Ngawi' => 'KC Ngawi', 'KC Ponorogo' => 'KC Ponorogo'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
              <label class="pres-export-field"><span>Produk timeseries</span><select name="sme_product"><option value="total">Total SME</option><option value="non_cashcoll">Kredit Non Cashcoll</option><option value="cashcoll">Kredit Cashcoll</option></select></label>
            </section>
            <section class="pres-export-group">
              <h3>Konsumer</h3>
              <label class="pres-export-field"><span>Cabang timeseries</span><select name="consumer_scope">@foreach(['area6' => 'Area 6 Konsolidasi', 'KC Madiun' => 'KC Madiun', 'KC Magetan' => 'KC Magetan', 'KC Ngawi' => 'KC Ngawi', 'KC Ponorogo' => 'KC Ponorogo'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
              <label class="pres-export-field"><span>Produk timeseries</span><select name="consumer_product"><option value="total">Total Konsumer</option><option value="briguna">Briguna</option><option value="kpr">KPR</option><option value="kkb">KKB</option></select></label>
            </section>
          </div>
          <div class="pres-export-progress" id="pres-export-progress" hidden aria-live="polite">
            <div class="pres-export-progress-head">
              <span id="pres-export-progress-icon"><i class="fas fa-layer-group" aria-hidden="true"></i></span>
              <div>
                <strong id="pres-export-progress-title">Menyiapkan ekspor</strong>
                <small id="pres-export-progress-message">Deck akan diproses oleh worker tanpa memblokir presentasi.</small>
              </div>
              <b id="pres-export-progress-value">0%</b>
            </div>
            <div class="pres-export-progress-track" aria-hidden="true"><span id="pres-export-progress-bar"></span></div>
          </div>
        </div>
        <div class="pres-export-actions">
          <button type="button" class="pres-export-cancel" onclick="this.closest('dialog').close()">Batal</button>
          <button type="submit" class="pres-ppt-export-btn" id="pres-export-submit"><i class="fas fa-download" aria-hidden="true"></i><span>Buat dan Unduh PPT</span></button>
        </div>
      </form>
    </dialog>

    <dialog class="pres-tool-dialog pres-compare-dialog" id="pres-compare-dialog">
      <div class="pres-tool-dialog-head">
        <div>
          <span>Compare mode</span>
          <h2>Perbandingan Periode</h2>
        </div>
        <button type="button" data-close-dialog aria-label="Tutup perbandingan"><i class="fas fa-times" aria-hidden="true"></i></button>
      </div>
      <div class="pres-tool-dialog-controls">
        <label>
          <span>Periode pembanding</span>
          <select id="pres-compare-period"></select>
        </label>
        <button type="button" id="pres-compare-refresh"><i class="fas fa-sync-alt" aria-hidden="true"></i><span>Bandingkan</span></button>
      </div>
      <div class="pres-compare-content" id="pres-compare-content" aria-live="polite"></div>
    </dialog>

    <dialog class="pres-tool-dialog pres-drilldown-dialog" id="pres-drilldown-dialog">
      <div class="pres-tool-dialog-head">
        <div>
          <span>Drill-down</span>
          <h2 id="pres-drilldown-title">Detail Data</h2>
        </div>
        <button type="button" data-close-dialog aria-label="Tutup detail"><i class="fas fa-times" aria-hidden="true"></i></button>
      </div>
      <div class="pres-drilldown-content" id="pres-drilldown-content"></div>
    </dialog>

    <dialog class="pres-tool-dialog pres-note-dialog" id="pres-note-dialog">
      <div class="pres-tool-dialog-head">
        <div>
          <span>Presenter notes</span>
          <h2 id="pres-note-title">Catatan Slide</h2>
        </div>
        <button type="button" data-close-dialog aria-label="Tutup catatan"><i class="fas fa-times" aria-hidden="true"></i></button>
      </div>
      <label class="pres-note-field">
        <span>Catatan pribadi pada perangkat ini</span>
        <textarea id="pres-note-text" rows="8" maxlength="4000"></textarea>
      </label>
      <div class="pres-note-actions">
        <button type="button" id="pres-note-clear"><i class="fas fa-trash-alt" aria-hidden="true"></i><span>Hapus</span></button>
        <button type="button" id="pres-note-save"><i class="fas fa-save" aria-hidden="true"></i><span>Simpan</span></button>
      </div>
    </dialog>

    <div class="pres-toast" id="pres-toast" role="status" aria-live="polite"></div>

    <!-- Slides Viewport -->
    <div class="pres-slides-container">
      @include('presentation._executive-slides')
      @if(false)
      <!-- Slide 1: Welcome Intro -->
      <div class="apple-slide active" id="pres-slide-0">
        <div class="pres-cover-layout">
          <div class="pres-glass-card pres-cover-lead animate-fade-in slide-delay-1">
            <div>
              <div class="pres-cover-eyebrow">Micro Directorate – Micro Sales Management Group</div>
              <h1 class="pres-cover-title" style="color: #0857c3;">Materi Pendukung Asistensi</h1>
              <p class="pres-cover-subtitle" style="font-style: italic; color: #0857c3; font-weight: 700; font-size: 1.25rem; margin-top: 0.35rem; margin-bottom: 1rem;">
                Regional 6 / Madiun
              </p>
              <p style="font-size: 0.85rem; color: #475569; line-height: 1.5; margin-bottom: 1.5rem;">
                Evaluasi harian kinerja simpanan, OS non-commercial, kualitas kredit (SML, NPL, LAR), audit Kolek Tidak Sesuai (KTS), dan implementasi 8 strategi produk dana digital.
              </p>
              <div class="pres-cover-strip">
                <div class="pres-cover-stat">
                  <div>
                    <span>Periode</span>
                    <strong id="pres-cover-period">-</strong>
                  </div>
                  <i class="far fa-calendar-alt" style="color: #0857c3; font-size: 1.15rem; opacity: 0.85; flex-shrink: 0;"></i>
                </div>
                <div class="pres-cover-stat">
                  <div>
                    <span>Data Loan</span>
                    <strong id="pres-cover-loan-period">-</strong>
                  </div>
                  <i class="far fa-clock" style="color: #e61c24; font-size: 1.15rem; opacity: 0.85; flex-shrink: 0;"></i>
                </div>
                <div class="pres-cover-stat">
                  <div>
                    <span>Audit KTS</span>
                    <strong id="pres-cover-kts">-</strong>
                  </div>
                  <i class="fas fa-users" style="color: #0857c3; font-size: 1.15rem; opacity: 0.85; flex-shrink: 0;"></i>
                </div>
                <div class="pres-cover-stat">
                  <div>
                    <span>Digital Strategy</span>
                    <strong id="pres-cover-digital-count">-</strong>
                  </div>
                  <i class="fas fa-bullseye" style="color: #e61c24; font-size: 1.15rem; opacity: 0.85; flex-shrink: 0;"></i>
                </div>
              </div>
            </div>
            <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
              <button type="button" class="pres-splash-accent-btn" id="pres-start-slides-btn" style="margin-top: 0; align-self: flex-start;">
                Masuk ke Deck Kinerja <i class="fas fa-arrow-right"></i>
              </button>
              <span style="font-size: 0.72rem; color: #64748b; font-weight: 600;">PT Bank Rakyat Indonesia (Persero) Tbk.</span>
            </div>
          </div>
          <!-- Right side: Widescreen Framed Building Image -->
          <div class="pres-cover-image-container animate-fade-in slide-delay-2">
            <img class="pres-cover-image-frame" src="{{ asset('images/ppt-template/cover-base.png') }}" alt="Gedung Kantor Pusat BRI">
          </div>
          <!-- Hidden container to keep pres-cover-board for JS compatibility -->
          <div id="pres-cover-board" style="display: none;"></div>
        </div>
      </div>

      <!-- Slide 2: Dana Simpanan (DPK) -->
      <div class="apple-slide" id="pres-slide-1">
        <div class="animate-fade-in slide-delay-1">
          <div class="pres-section-eyebrow">Konsol Area 6 & Unit Cabang</div>
          <h2 class="pres-text-gradient-silver" style="font-size:2.05rem; font-weight:850; margin:0.25rem 0 0; line-height:1.12;">
            Kinerja dan Kualitas OS
          </h2>
        </div>
        <div class="pres-control-bar animate-fade-in slide-delay-2">
          <div class="pres-control-group" id="pres-metric-toggle" aria-label="Pilih metrik">
            <button type="button" class="pres-toggle-btn active" data-metric="simpanan">Simpanan</button>
            <button type="button" class="pres-toggle-btn" data-metric="os">OS</button>
            <button type="button" class="pres-toggle-btn" data-metric="sml">SML</button>
            <button type="button" class="pres-toggle-btn" data-metric="npl">NPL</button>
          </div>
          <div style="display:flex; align-items:center; gap:0.45rem;">
            <select class="pres-compact-select" id="pres-scope-select" aria-label="Pilih cabang"></select>
            <div class="pres-control-group" id="pres-view-toggle" aria-label="Pilih tampilan">
              <button type="button" class="pres-toggle-btn active" data-view="table"><i class="fas fa-table"></i></button>
              <button type="button" class="pres-toggle-btn" data-view="chart"><i class="fas fa-chart-line"></i></button>
            </div>
          </div>
        </div>
        <div class="pres-explorer-grid animate-fade-in slide-delay-3">
          <div class="pres-explorer-side">
            <div class="pres-mini-stat-grid">
              <div class="pres-mini-stat">
                <span>Angka terbaru</span>
                <strong id="pres-explorer-latest">-</strong>
              </div>
              <div class="pres-mini-stat">
                <span>Jumlah baris</span>
                <strong id="pres-explorer-count">-</strong>
              </div>
              <div class="pres-mini-stat">
                <span>YtD</span>
                <strong id="pres-explorer-ytd">-</strong>
              </div>
              <div class="pres-mini-stat">
                <span>MtM / MtD</span>
                <strong id="pres-explorer-mtm-mtd">-</strong>
              </div>
            </div>
            <div class="pres-glass-card" style="padding:1rem; min-height:0;">
              <div class="pres-section-eyebrow" style="font-size:0.66rem;">Timeseries Area 6</div>
              <div class="pres-chart-wrap" style="height:270px;">
                <canvas id="pres-explorer-chart"></canvas>
              </div>
            </div>
          </div>
          <div class="pres-glass-card" style="padding:1rem; min-height:0; display:flex; flex-direction:column;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:0.7rem;">
              <div>
                <div class="pres-section-eyebrow" style="font-size:0.66rem;" id="pres-explorer-caption">Area 6 Konsol</div>
                <h3 id="pres-explorer-title" style="margin:0.18rem 0 0; font-size:1.18rem; font-weight:850; color:#0f172a;">Simpanan</h3>
              </div>
              <div style="font-size:0.72rem; font-weight:750; color:#64748b;" id="pres-explorer-periods">-</div>
            </div>
            <div class="pres-table-scroll" id="pres-explorer-table-wrap">
              <table class="pres-table-dense">
                <thead id="pres-explorer-thead">
                  <tr>
                    <th>Unit/Cabang</th>
                    <th style="text-align:right;">Terbaru</th>
                    <th style="text-align:right;">YtD</th>
                    <th style="text-align:right;">MtM</th>
                    <th style="text-align:right;">MtD</th>
                    <th style="text-align:right;">Rasio</th>
                  </tr>
                </thead>
                <tbody id="pres-explorer-tbody"></tbody>
              </table>
            </div>
            <div class="pres-chart-wrap hidden" id="pres-explorer-bar-wrap" style="height:420px;">
              <canvas id="pres-explorer-bar-chart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 3: Kredit Pinjaman (OS) -->
      <div class="apple-slide" id="pres-slide-2">
        <div class="animate-fade-in slide-delay-1" style="font-size:0.9rem; font-weight:700; color:#0071e3; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.5rem;">
          Kinerja OS
        </div>
        <h2 class="animate-fade-in slide-delay-2 pres-text-gradient-silver" style="font-size:2.2rem; font-weight:800; margin:0 0 1.5rem 0; letter-spacing:-0.02em;">
          Kinerja OS per Segmentasi
        </h2>
        <div class="pres-grid-2col" style="height: calc(100% - 5.2rem); align-items:stretch;">
          <!-- Left: Big Number & KPI -->
          <div class="pres-glass-card animate-fade-in slide-delay-3" style="display:flex; flex-direction:column; justify-content:center;">
            <div style="font-size:0.85rem; font-weight:600; color:rgba(0,0,0,0.5); text-transform:uppercase; letter-spacing:0.05em;">Total OS Non Commercial</div>
            <div class="pres-text-gradient-blue pres-kpi-huge-number" id="pres-kredit-total-volume">Rp -</div>
            <div>
              <span class="pres-kpi-sub-trend pos" id="pres-kredit-total-trend">
                <i class="fas fa-arrow-up"></i> -% MtM
              </span>
            </div>
            <div class="pres-rka-strip">
              <div>
                <span>RKA OS</span>
                <strong id="pres-kredit-rka">Data belum tersedia</strong>
              </div>
              <div>
                <span>Penc. RKA</span>
                <strong id="pres-kredit-achievement">Data belum tersedia</strong>
              </div>
            </div>

            <!-- Segment summary status bar -->
            <div style="margin-top:1.6rem;">
              <div style="font-size:0.85rem; font-weight:600; color:rgba(0,0,0,0.5); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.75rem;">Kontribusi OS 4 Kantor Cabang</div>
              <table class="pres-table-dense" style="font-size:0.8rem;">
                <thead>
                  <tr>
                    <th>Kantor Cabang</th>
                    <th style="text-align:right;">Volume OS</th>
                    <th style="text-align:right;">RKA</th>
                    <th style="text-align:right;">Penc. RKA</th>
                    <th style="text-align:right;">Porsi Area 6</th>
                  </tr>
                </thead>
                <tbody id="pres-kredit-branch-shares-tbody">
                  <!-- Dynamic Branch Shares -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- Right: Segment performance explorer -->
          <div class="pres-glass-card pres-segment-panel animate-fade-in slide-delay-4" style="padding:1.25rem; display:flex; flex-direction:column; justify-content:space-between; flex-grow:1; min-height:0;">
            <div style="display:flex; flex-direction:column; height:100%; min-height:0;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem;">
                <div>
                  <span style="font-size:0.75rem; font-weight:700; color:#0071e3; text-transform:uppercase; letter-spacing:0.05em;">OS per Segmen Bisnis</span>
                  <h3 id="pres-seg-explorer-title" style="margin:0.15rem 0 0 0; font-size:1.35rem; font-weight:850; color:#1d1d1f;" class="pres-text-gradient-blue">OS SME</h3>
                </div>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                  <!-- Segment Toggler -->
                  <div class="pres-control-group" id="pres-seg-metric-toggle" style="margin-right: 0.25rem;">
                    <button type="button" class="pres-toggle-btn active" data-seg-metric="sme_os">SME</button>
                    <button type="button" class="pres-toggle-btn" data-seg-metric="micro_os">Mikro</button>
                    <button type="button" class="pres-toggle-btn" data-seg-metric="consumer_os">Konsumer</button>
                  </div>
                  <!-- Scope Dropdown -->
                  <select class="pres-compact-select" id="pres-seg-scope-select" style="min-width: 145px; font-weight: 700;" aria-label="Pilih cabang"></select>
                </div>
              </div>

              <!-- Subtitle & Comparison periods display -->
              <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.7rem; font-weight:600; color:#64748b; margin-bottom:0.6rem; padding-bottom:0.4rem; border-bottom:1px solid rgba(0,0,0,0.06);">
                <span id="pres-seg-explorer-caption" style="color:#0071e3; font-weight:800;">Area 6 Konsol</span>
                <span id="pres-seg-explorer-periods">-</span>
              </div>

              <div class="pres-segment-summary-grid" id="pres-seg-summary-grid">
                <div class="pres-segment-summary-item">
                  <span>Total Posisi</span>
                  <strong id="pres-seg-total-latest">-</strong>
                </div>
                <div class="pres-segment-summary-item">
                  <span>RKA</span>
                  <strong id="pres-seg-total-rka">-</strong>
                </div>
                <div class="pres-segment-summary-item">
                  <span>Penc. RKA</span>
                  <strong id="pres-seg-total-ach">-</strong>
                </div>
                <div class="pres-segment-summary-item">
                  <span>Jumlah Outlet</span>
                  <strong id="pres-seg-total-outlet">-</strong>
                </div>
              </div>

              <!-- Scrollable Table -->
              <div class="pres-table-scroll" style="overflow-y:auto; flex-grow:1; min-height:0;">
                <table class="pres-table-dense" style="font-size:0.78rem; width:100%; border-collapse: collapse;">
                  <thead>
                    <tr style="background: rgba(0, 113, 227, 0.04);">
                      <th id="pres-seg-first-col-label" style="padding: 0.45rem 0.55rem; border-top-left-radius: 6px; border-bottom-left-radius: 6px;">Outlet</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem;">Terbaru</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem;">YtD</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem;">MtM</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem;">MtD</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem;">RKA</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem;">Gap RKA</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem; border-top-right-radius: 6px; border-bottom-right-radius: 6px;">Penc. RKA</th>
                    </tr>
                  </thead>
                  <tbody id="pres-seg-explorer-tbody"></tbody>
                </table>
              </div>
            </div>
            <div class="pres-seg-footnote" id="pres-seg-footnote">
              <i class="fas fa-info-circle"></i>
              <span>RKA merujuk pada Rencana Kerja Anggaran bulanan berjalan.</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 4: Tren Historis (Timeseries) -->
      <div class="apple-slide" id="pres-slide-3">
        <div class="animate-fade-in slide-delay-1" style="font-size:0.9rem; font-weight:700; color:#0071e3; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.5rem;">
          Tren Performa Historis Harian
        </div>
        <h2 class="animate-fade-in slide-delay-2 pres-text-gradient-silver" style="font-size:2.2rem; font-weight:800; margin:0 0 1.25rem 0; letter-spacing:-0.02em;">
          Tren Realisasi Simpanan, OS, SML, dan NPL
        </h2>
        <div class="pres-grid-2col pres-timeseries-grid animate-fade-in slide-delay-3">
          <!-- Left Glass Card: Simpanan vs OS -->
          <div class="pres-glass-card pres-timeseries-card">
            <div class="pres-timeseries-card-header" style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <span style="font-size:0.8rem; font-weight:600; color:rgba(0,0,0,0.5); text-transform:uppercase; letter-spacing:0.05em;">Dana vs Kredit Area 6</span>
                <h3 style="margin:0.25rem 0 0 0; font-size:1.3rem; font-weight:800; color:#1d1d1f;">Realisasi Simpanan vs Pinjaman (OS)</h3>
              </div>
              <div style="font-size:0.8rem; color:rgba(0,0,0,0.5); font-weight:500;">
                Unit: <strong style="color:#0071e3;">Rp Juta</strong>
              </div>
            </div>
            <!-- Chart Canvas Container -->
            <div class="pres-timeseries-chart-box">
              <canvas id="pres-timeseries-chart-dana" style="width:100%; height:100%;"></canvas>
            </div>
          </div>

          <!-- Right Glass Card: OS, SML, NPL -->
          <div class="pres-glass-card pres-timeseries-card">
            <div class="pres-timeseries-card-header" style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <span style="font-size:0.8rem; font-weight:600; color:rgba(0,0,0,0.5); text-transform:uppercase; letter-spacing:0.05em;">Kualitas Kredit Area 6</span>
                <h3 style="margin:0.25rem 0 0 0; font-size:1.3rem; font-weight:800; color:#1d1d1f;">Rasio & Tren OS, SML, dan NPL</h3>
              </div>
              <div style="font-size:0.8rem; color:rgba(0,0,0,0.5); font-weight:500;">
                Unit: <strong style="color:#0071e3;">Rp Juta</strong>
              </div>
            </div>
            <!-- Chart Canvas Container -->
            <div class="pres-timeseries-chart-box">
              <canvas id="pres-timeseries-chart-quality" style="width:100%; height:100%;"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 5: LAR & Kualitas Kredit -->
      <div class="apple-slide" id="pres-slide-4">
        <div class="animate-fade-in slide-delay-1" style="font-size:0.9rem; font-weight:700; color:#0071e3; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.5rem;">
          SML, NPL & Risk Management
        </div>
        <h2 class="animate-fade-in slide-delay-2 pres-text-gradient-silver" style="font-size:2.2rem; font-weight:800; margin:0 0 1.25rem 0; letter-spacing:-0.02em;">
          SML dan NPL per Kantor Cabang
        </h2>
        <div class="pres-glass-card pres-panel-card animate-fade-in slide-delay-3" style="margin-bottom:1.05rem;">
          <div class="pres-panel-header pres-panel-header-cyan">
            <span><span class="pres-panel-icon"><i class="fas fa-chart-pie"></i></span> Komposisi Kualitas OS</span>
            <span class="pres-panel-subtitle" id="pres-risk-subtitle">Struktur SML dan NPL Area 6</span>
          </div>
          <div class="pres-panel-body">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:0.85rem;">
              <h3 style="margin:0; font-size:1.35rem; font-weight:900;" class="pres-text-gradient-blue" id="pres-lar-share-title">Portofolio Pinjaman</h3>
              <div style="display:flex; align-items:center; gap:0.45rem;">
                <span style="font-size:0.68rem; font-weight:850; color:#64748b; text-transform:uppercase; letter-spacing:0.04em;">Tampilan</span>
                <select class="pres-compact-select" id="pres-risk-scope-select" style="min-width: 145px; font-weight: 800;" aria-label="Pilih cabang"></select>
              </div>
            </div>
            <div class="pres-risk-composition-layout">
              <div class="pres-donut-shell">
                <div class="pres-donut-canvas-wrapper">
                  <canvas id="pres-risk-composition-chart"></canvas>
                  <div class="pres-donut-center">
                    <div>
                      <strong id="pres-risk-donut-center">-</strong>
                      <span>LAR</span>
                    </div>
                  </div>
                </div>
              </div>
              <div style="min-width:0;">
                <div class="pres-risk-metric-grid">
                  <div class="pres-risk-metric">
                    <span>Lancar</span>
                    <strong style="color:#10b981;" id="pres-lar-healthy-val">-</strong>
                  </div>
                  <div class="pres-risk-metric">
                    <span>Lancar Restruk</span>
                    <strong style="color:#1155c8;" id="pres-lar-restruk-val">-</strong>
                  </div>
                  <div class="pres-risk-metric">
                    <span>SML</span>
                    <strong style="color:#f59e0b;" id="pres-lar-sml-val">-</strong>
                  </div>
                  <div class="pres-risk-metric">
                    <span>NPL</span>
                    <strong style="color:#ef4444;" id="pres-lar-npl-val">-</strong>
                  </div>
                  <div class="pres-risk-metric">
                    <span>LAR</span>
                    <strong style="color:#1155c8;" id="pres-lar-ratio-val">-</strong>
                  </div>
                </div>
                <div class="pres-spectrum-bar" style="margin-top:0.85rem;">
                  <div class="pres-spectrum-segment" id="pres-spectrum-healthy" style="background:#10b981;"></div>
                  <div class="pres-spectrum-segment" id="pres-spectrum-restruk" style="background:#1155c8;"></div>
                  <div class="pres-spectrum-segment" id="pres-spectrum-sml" style="background:#f59e0b;"></div>
                  <div class="pres-spectrum-segment" id="pres-spectrum-npl" style="background:#ef4444;"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Comparative Quality Table (Full Width for Premium UI/UX) -->
        <div class="pres-glass-card pres-panel-card animate-fade-in slide-delay-4" style="overflow-x:auto;">
          <div class="pres-panel-header">
            <span><span class="pres-panel-icon"><i class="fas fa-table"></i></span> Komparasi Komposisi & Rasio</span>
            <span class="pres-panel-subtitle">Cabang Area 6</span>
          </div>
          <div class="pres-panel-body" style="padding:0.95rem;">
          <table class="pres-table-dense" style="font-size:0.82rem; width:100%; border-collapse: collapse;">
            <thead>
              <tr style="background: rgba(0, 113, 227, 0.04);">
                <th style="padding: 0.6rem 0.8rem; border-top-left-radius: 8px; border-bottom-left-radius: 8px; font-weight: 700;">KANTOR CABANG</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700;">TOTAL OS</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700;">SML NOMINAL</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700;">SML RASIO</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700;">NPL NOMINAL</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700;">NPL RASIO</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700;">LAR NOMINAL</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">LAR RASIO</th>
              </tr>
            </thead>
            <tbody id="pres-quality-comparison-tbody">
              <!-- Dynamic comparative rows populated by JS -->
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <!-- Slide 6: Kolek Tidak Sesuai (KTS) -->
      <div class="apple-slide" id="pres-slide-5">
        <div class="animate-fade-in slide-delay-1" style="font-size:0.9rem; font-weight:700; color:#0071e3; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.5rem;">
          KTS Detail & Audit Alert
        </div>
        <div class="animate-fade-in slide-delay-2" style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:0.9rem;">
          <h2 class="pres-text-gradient-silver" style="font-size:2.05rem; font-weight:850; margin:0; line-height:1.12;">
            KTS Membaik & Memburuk
          </h2>
          <div class="pres-control-bar" style="margin:0;">
            <div class="pres-control-group" id="pres-kts-category-toggle">
              <button type="button" class="pres-toggle-btn active" data-kts-category="membaik">Membaik</button>
              <button type="button" class="pres-toggle-btn" data-kts-category="memburuk">Memburuk</button>
            </div>
            <div class="pres-control-group" id="pres-kts-scope-toggle">
              <button type="button" class="pres-toggle-btn active" data-kts-scope="ritel">Ritel</button>
              <button type="button" class="pres-toggle-btn" data-kts-scope="micro">Micro</button>
            </div>
          </div>
        </div>
        <div class="pres-kts-grid animate-fade-in slide-delay-3">
          <div class="pres-kts-summary">
            <div class="pres-mini-stat">
              <span>Total rekening</span>
              <strong id="pres-kts-total-count">-</strong>
            </div>
            <div class="pres-mini-stat">
              <span>OS terdampak</span>
              <strong id="pres-kts-total-os">-</strong>
            </div>
            <div class="pres-mini-stat">
              <span>Periode</span>
              <strong id="pres-kts-period">-</strong>
            </div>
            <div class="pres-glass-card" style="padding:1rem;">
              <div class="pres-section-eyebrow" style="font-size:0.66rem;">Kategori</div>
              <p id="pres-kts-note" style="margin:0.45rem 0 0; color:#475569; font-size:0.82rem; line-height:1.55;">
                KTS dipisahkan berdasarkan arah selisih kolektibilitas aktual terhadap kolektibilitas seharusnya.
              </p>
            </div>
          </div>
          <div class="pres-glass-card" style="display:flex; flex-direction:column; overflow:hidden; padding:1rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.65rem;">
              <div>
                <div class="pres-section-eyebrow" style="font-size:0.66rem;" id="pres-kts-caption">KTS Membaik</div>
                <h3 style="margin:0.15rem 0 0; font-size:1.15rem; font-weight:850; color:#0f172a;" id="pres-kts-title">Ritel</h3>
              </div>
            </div>
            <div class="pres-table-scroll" style="max-height:450px;">
              <table class="pres-table-dense">
                <thead>
                  <tr>
                    <th style="width:45px; padding-left:1.25rem;">#</th>
                    <th>Debitur / Rekening</th>
                    <th>Unit Kerja</th>
                    <th style="text-align:center;">Kolek Aktual vs Seharusnya</th>
                    <th style="text-align:right;">Baki Debet</th>
                  </tr>
                </thead>
                <tbody id="pres-kts-tbody">
                  <!-- Dynamic rows -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 7: 8 Strategi Dana & Digital -->
      <div class="apple-slide" id="pres-slide-6">
        <div class="animate-fade-in slide-delay-1" style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:0.85rem;">
          <div>
            <div class="pres-section-eyebrow">8 Strategi Dana & Digital</div>
            <h2 class="pres-text-gradient-silver" style="font-size:2.05rem; font-weight:850; margin:0.25rem 0 0; line-height:1.12;">
              Kinerja Strategi Realtime
            </h2>
          </div>
          <div class="pres-control-group" id="pres-digital-view-toggle">
            <button type="button" class="pres-toggle-btn active" data-digital-view="table"><i class="fas fa-table"></i></button>
            <button type="button" class="pres-toggle-btn" data-digital-view="timeseries"><i class="fas fa-chart-line"></i></button>
          </div>
        </div>
        <div class="pres-digital-layout animate-fade-in slide-delay-2">
          <div class="pres-digital-list" id="pres-digital-cards-grid">
            <!-- 8 cards will be mapped dynamically here -->
          </div>
          <div class="pres-glass-card" style="padding:1rem; min-height:0;">
            <div id="pres-digital-table-wrap" class="pres-table-scroll" style="max-height:560px;">
              <table class="pres-table-dense">
                <thead>
                  <tr>
                    <th>Strategi</th>
                    <th style="text-align:right;">Angka terbaru</th>
                    <th style="text-align:right;">Volume/Pendukung</th>
                    <th style="text-align:right;">Growth</th>
                    <th>Sumber</th>
                  </tr>
                </thead>
                <tbody id="pres-digital-tbody"></tbody>
              </table>
            </div>
            <div id="pres-digital-chart-wrap" class="pres-chart-wrap hidden" style="height:560px;">
              <canvas id="pres-digital-chart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 8: Outlook & Summary -->
      <div class="apple-slide" id="pres-slide-7">
      <!-- Slide 8: Outlook & Strategic Priorities -->
      <div class="apple-slide" id="pres-slide-7">
        <div class="animate-fade-in slide-delay-1" style="font-size:0.9rem; font-weight:700; color:#0071e3; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.5rem;">
          Arahan Eksekutif & Prioritas Kerja
        </div>
        <h2 class="animate-fade-in slide-delay-2 pres-text-gradient-silver" style="font-size:2.2rem; font-weight:800; margin:0 0 1.75rem 0; letter-spacing:-0.02em;">
          Strategi dan Target Kerja Area 6 - Madiun
        </h2>

        <!-- Widescreen Priorities Grid -->
        <div class="pres-priority-grid animate-fade-in slide-delay-3" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; width: 100%;">
          <!-- Priority 1 -->
          <div class="pres-priority-card animate-fade-in slide-delay-3">
            <div class="pres-priority-icon-wrapper casa">1</div>
            <div>
              <h3 style="margin:0 0 0.5rem 0; font-size:1.15rem; font-weight:800; color:#0f172a;">Akselerasi Simpanan Berbiaya Murah (CASA)</h3>
              <p style="margin:0; font-size:0.88rem; color:#475569; line-height:1.6;">
                Fokus akuisisi mesin EDC, sebaran QRIS merchant, aktivasi Brimo massal, pengelolaan Rekening Dormant, perluasan Payroll instansi, serta pemanfaatan platform QLola korporasi secara maksimal.
              </p>
            </div>
          </div>

          <!-- Priority 2 -->
          <div class="pres-priority-card animate-fade-in slide-delay-4">
            <div class="pres-priority-icon-wrapper quality">2</div>
            <div>
              <h3 style="margin:0 0 0.5rem 0; font-size:1.15rem; font-weight:800; color:#0f172a;">Penataan Kualitas Kredit Sejak Dini</h3>
              <p style="margin:0; font-size:0.88rem; color:#475569; line-height:1.6;">
                Akselerasi pipeline Whitelist Restrukturisasi, penanganan SML agresif, pencegahan dini saldo NPL baru, serta penyelarasan dan perbaikan data audit KTS secara konsisten.
              </p>
            </div>
          </div>

          <!-- Priority 3 -->
          <div class="pres-priority-card animate-fade-in slide-delay-5">
            <div class="pres-priority-icon-wrapper prod">3</div>
            <div>
              <h3 style="margin:0 0 0.5rem 0; font-size:1.15rem; font-weight:800; color:#0f172a;">Penguatan Produktivitas Mantri & RM</h3>
              <p style="margin:0; font-size:0.88rem; color:#475569; line-height:1.6;">
                Evaluasi harian putusan kredit BOH & MBM, peningkatan sebaran digital tools untuk optimalisasi portofolio, serta pembinaan kompetensi Mantri dan RM dalam memitigasi risiko kredit.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 9: Executive Closing (Terima Kasih) -->
      <div class="apple-slide" id="pres-slide-8">
        <div class="pres-cover-layout">
          <div class="pres-glass-card pres-cover-lead animate-fade-in slide-delay-1" style="background: transparent !important; border: none !important; box-shadow: none !important; display: flex; flex-direction: column; justify-content: space-between; padding: 1rem 0 !important; border-top: none !important;">
            <div>
              <div class="pres-cover-eyebrow" style="color: #3b82f6 !important; font-weight: 800;">PT Bank Rakyat Indonesia (Persero) Tbk.</div>
              <h1 class="pres-cover-title" style="color: #ffffff !important; font-size: clamp(2.5rem, 5vw, 4.5rem) !important; margin-top: 1rem; border-bottom: 4px solid #3b82f6; display: inline-block; padding-bottom: 0.5rem; line-height: 1.1;">
                Terima kasih!
              </h1>
              <p class="pres-cover-subtitle" style="color: rgba(255, 255, 255, 0.7) !important; font-size: 1.15rem; line-height: 1.6; margin-top: 1.5rem; max-width: 32rem;">
                Presentasi laporan konsolidasi Kinerja Harian Area 6 telah selesai dirangkum. Seluruh data disajikan secara realtime dari sistem core banking dan dashboard analitik.
              </p>
            </div>
            <div style="margin-top: 2rem;">
              <button type="button" class="pres-splash-accent-btn" id="pres-finish-close-btn" style="background: #e61c24 !important; border-color: #b91c1c !important; box-shadow: 0 10px 25px rgba(230, 28, 36, 0.35) !important;">
                Tutup & Selesai <i class="fas fa-check-circle" style="margin-left: 0.35rem;"></i>
              </button>
            </div>
          </div>
          <!-- Right side: Framed Building Image with White Border -->
          <div class="pres-cover-image-container animate-fade-in slide-delay-2">
            <img class="pres-cover-image-frame" src="{{ asset('images/ppt-template/cover-base.png') }}" alt="Gedung Kantor Pusat BRI">
          </div>
        </div>
      </div>
      @endif
    </div>

    <!-- Bottom Bar -->
    <div class="pres-bottom-bar">
      <div class="pres-nav-buttons-container">
        <button type="button" class="pres-nav-btn" id="pres-prev-btn" title="Slide Sebelumnya (Left Arrow)">
          <i class="fas fa-chevron-left"></i>
        </button>
      </div>

      <!-- Page dots indicator -->
      <div style="display: flex; flex-direction: column; align-items: center; gap: 0.35rem;">
        <div class="pres-paginator" id="pres-paginator-dots">
          @for ($i = 0; $i < 15; $i++)
            <div class="pres-dot {{ $i === 0 ? 'active' : '' }}" data-index="{{ $i }}"></div>
          @endfor
        </div>
        <div class="pres-slide-counter-badge" id="pres-slide-counter-badge">Slide 1 dari 15</div>
      </div>

      <div class="pres-nav-buttons-container">
        <div class="pres-autoplay-panel" aria-label="Kontrol autoplay presentasi">
          <button type="button" class="pres-autoplay-btn" id="pres-autoplay-btn" title="Putar otomatis">
            <i class="fas fa-play"></i>
          </button>
          <div class="pres-auto-meta">
            <div class="pres-auto-label">
              <span>Autoplay</span>
              <span id="pres-auto-state">OFF</span>
            </div>
            <div class="pres-auto-progress" aria-hidden="true">
              <span class="pres-auto-progress-fill" id="pres-auto-progress-fill"></span>
            </div>
          </div>
        </div>
        <button type="button" class="pres-nav-btn" id="pres-next-btn" title="Slide Selanjutnya (Right Arrow / Spacebar)">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Standalone Loader Overlay -->
  <div class="dashboard-loading-overlay active" id="dashboard-global-loader">
    <div class="loading-spinner-container">
      <div class="loading-ring"></div>
      <div class="loading-ring-inner"></div>
    </div>
    <div class="dashboard-loading-text">Memuat Data Presentasi...</div>

    <!-- Beautiful progress bar container -->
    <div style="width: 280px; height: 6px; background: rgba(0,0,0,0.06); border-radius: 4px; overflow: hidden; margin-top: 1.25rem; border: 1px solid rgba(0,0,0,0.05); position: relative;">
      <div id="loading-progress-bar" style="height: 100%; width: 0%; background: linear-gradient(90deg, #0071e3, #1f8bfd); border-radius: 4px; transition: width 0.4s ease;"></div>
    </div>
    <div id="loading-progress-percent" style="font-size: 0.8rem; font-weight: 700; color: #0071e3; margin-top: 0.4rem;">0%</div>

    <div class="dashboard-loading-sub" id="dashboard-loading-status" style="margin-top: 0.5rem; text-align: center; font-weight: 500; min-height: 18px;">Menyiapkan slide dari data server...</div>
  </div>

  @php
    $presentationConfig = [
      'selectedPeriod' => $selectedPeriod,
      'periods' => collect($periods ?? [])->values()->all(),
      'dataUrl' => route('dashboard.presentation-data'),
      'summaryDataUrl' => route('dashboard.presentation-data.summary'),
      'detailDataUrl' => route('dashboard.presentation-data.detail', ['section' => '__SECTION__']),
      'ktsDataUrl' => route('dashboard.presentation-kts-data'),
      'dashboardUrl' => route('dashboard', ['periode' => $selectedPeriod]),
      'serverData' => $presentationPayload ?? null,
      'csrfToken' => csrf_token(),
      'serviceWorkerUrl' => asset('presentation-sw.js'),
      'manifestUrl' => asset('manifest-presentation.webmanifest'),
      'exportStartUrl' => route('dashboard.presentation.export-pptx.start'),
    ];
  @endphp
  <script>
    window.__PRESENTATION_CONFIG__ = @json($presentationConfig);
  </script>
  @vite('resources/js/presentation/pres-engine.js')
</body>
</html>
