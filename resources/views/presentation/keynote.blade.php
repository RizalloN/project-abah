<!DOCTYPE html>
@php
  $presentationBranchScope = \App\Support\UserBranchScope::current();
  $presentationExportScopes = $presentationBranchScope !== null
    ? [$presentationBranchScope['label'] => $presentationBranchScope['label']]
    : ['area6' => 'Area 6 Konsolidasi', 'KC Madiun' => 'KC Madiun', 'KC Magetan' => 'KC Magetan', 'KC Ngawi' => 'KC Ngawi', 'KC Ponorogo' => 'KC Ponorogo'];
  $presentationScopeLabel = $presentationBranchScope['label'] ?? 'Area 6 Konsol';
  $presentationTitle = $presentationBranchScope !== null ? $presentationBranchScope['label'] : 'Area 6';
  $presentationPeriodLabel = $selectedPeriod ? \Carbon\Carbon::parse($selectedPeriod)->locale('id')->translatedFormat('d M Y') : '-';
  $presentationPeriodLong = $selectedPeriod ? \Carbon\Carbon::parse($selectedPeriod)->locale('id')->translatedFormat('d F Y') : '-';

  // Report order is the meeting agenda and stays identical to the slide deck and the PPT.
  $presentationStory = [
    ['key' => 'cover', 'label' => 'Pembuka'],
    ['key' => 'agenda', 'label' => 'Agenda'],
    ['key' => 'market-share-area6', 'label' => 'Market share', 'progressive' => 'marketshare'],
    ['key' => 'market-share-mapping', 'label' => 'Penetrasi wilayah', 'progressive' => 'marketshare'],
    ['key' => 'funding-summary', 'label' => 'Posisi simpanan'],
    ['key' => 'funding-product', 'label' => 'Simpanan per produk'],
    ['key' => 'strategies', 'label' => 'Delapan strategi', 'progressive' => 'digital'],
    ['key' => 'loan-summary', 'label' => 'Posisi pinjaman'],
    ['key' => 'loan-sme', 'label' => 'SME'],
    ['key' => 'loan-consumer', 'label' => 'Konsumer'],
    ['key' => 'loan-micro', 'label' => 'Mikro', 'progressive' => 'micro'],
    ['key' => 'quality-sml', 'label' => 'SML'],
    ['key' => 'quality-npl', 'label' => 'NPL'],
    ['key' => 'timeseries', 'label' => 'Timeseries', 'progressive' => 'timeseries'],
    ['key' => 'closing', 'label' => 'Prioritas aksi', 'progressive' => 'digital'],
  ];

  // Each chapter opens with a statement: one real figure, one line, and the relief beside it.
  $presentationChapters = [
    ['key' => 'pembuka', 'label' => 'Pembuka', 'start' => 0, 'end' => 1, 'ground' => 'light', 'statement' => false],
    ['key' => 'pasar', 'label' => 'Pasar', 'start' => 2, 'end' => 3, 'ground' => 'light', 'statement' => true,
      'title' => 'Posisi kita di pasar.', 'lede' => 'Pangsa BRI dibanding bank lain, lalu penetrasi nasabah di setiap kecamatan.',
      'figure' => 'pasar', 'figureLabel' => 'pangsa DPK BRI', 'relief' => 'Tinggi setiap kecamatan adalah penetrasi nasabah BRI.'],
    ['key' => 'dana', 'label' => 'Dana', 'start' => 4, 'end' => 6, 'ground' => 'paper', 'statement' => true,
      'title' => 'Dana yang kita himpun.', 'lede' => 'Total simpanan, rinciannya per produk, lalu delapan strategi dana dan digital.',
      'figure' => 'simpanan', 'figureLabel' => 'total simpanan', 'relief' => 'Tinggi setiap wilayah adalah simpanan cabangnya.'],
    ['key' => 'pinjaman', 'label' => 'Pinjaman', 'start' => 7, 'end' => 10, 'ground' => 'light', 'statement' => true,
      'title' => 'Kredit yang kita salurkan.', 'lede' => 'Outstanding per cabang dan segmen: SME, Konsumer, dan Mikro.',
      'figure' => 'os', 'figureLabel' => 'outstanding', 'relief' => 'Tinggi setiap wilayah adalah outstanding cabangnya.'],
    ['key' => 'kualitas', 'label' => 'Kualitas', 'start' => 11, 'end' => 12, 'ground' => 'dark', 'statement' => true, 'theatre' => true,
      'title' => 'Kualitas kredit.', 'lede' => 'Di mana risiko berkumpul: porsi SML dan NPL per cabang dan segmen.',
      'figure' => 'sml', 'figureLabel' => 'SML', 'relief' => 'Cahaya di dalam setiap wilayah naik setinggi rasio cabangnya.'],
    ['key' => 'tren', 'label' => 'Tren', 'start' => 13, 'end' => 13, 'ground' => 'paper', 'statement' => true,
      'title' => 'Arah pergerakan.', 'lede' => 'Seluruh indikator dalam satu garis waktu terintegrasi.',
      'figure' => 'os-ytd', 'figureLabel' => 'perubahan outstanding sejak awal tahun', 'relief' => 'Wilayah naik atau turun mengikuti perubahan outstanding YtD.'],
    ['key' => 'aksi', 'label' => 'Aksi', 'start' => 14, 'end' => 14, 'ground' => 'light', 'statement' => true,
      'title' => 'Apa yang kita kerjakan berikutnya.', 'lede' => 'Lima agenda manajemen dari angka di atas.',
      'figure' => 'aksi', 'figureLabel' => 'agenda prioritas', 'relief' => ''],
  ];
  $chapterOpeners = collect($presentationChapters)->filter(fn ($chapter) => $chapter['statement'])->keyBy('start');
  $panelChapter = [];
  $panelGround = [];
  foreach ($presentationChapters as $chapter) {
    foreach (range($chapter['start'], $chapter['end']) as $chapterIndex) {
      $panelChapter[$chapterIndex] = $chapter['key'];
      $panelGround[$chapterIndex] = $chapter['ground'];
    }
  }
  $navChapters = collect($presentationChapters)->filter(fn ($chapter) => $chapter['statement'])->values();
@endphp
<html lang="id" class="pres-scroll-layout pres-keynote">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
  <title>Kinerja {{ $presentationTitle }}, {{ $presentationPeriodLabel }}</title>
  <meta name="description" content="Laporan kinerja {{ $presentationScopeLabel }} posisi {{ $presentationPeriodLong }}: pasar, dana, pinjaman, kualitas, tren, dan prioritas aksi.">

  <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('vendor/leaflet-1.9.4/leaflet.css') }}">
  <script src="{{ asset('vendor/leaflet-1.9.4/leaflet.js') }}"></script>
  <link rel="manifest" href="{{ asset('manifest-presentation.webmanifest') }}">
  <meta name="theme-color" content="#0b0b0d">

  @vite('resources/css/presentation/presentation-keynote.css')
</head>
<body class="kn">
  <a class="kn-skip" href="#pres-slide-1">Lewati ke agenda</a>

  <div class="apple-presentation-mode active kn-root" id="apple-presentation-mode" data-layout="scroll" data-keynote>
    {{-- Local navigation: title, chapters, period, one action, and every tool behind one menu. --}}
    <header class="pres-top-bar kn-nav" data-kn-nav>
      <div class="kn-nav__inner">
        <a href="{{ route('dashboard', ['periode' => $selectedPeriod]) }}" class="kn-nav__back pres-nav-btn-back" title="Kembali ke dashboard">
          <i class="fas fa-chevron-left" aria-hidden="true"></i><span class="pres-back-text">Dashboard</span>
        </a>
        <p class="kn-nav__title">Kinerja {{ $presentationTitle }}</p>
        <nav class="kn-nav__chapters" aria-label="Bab laporan">
          <ol>
            @foreach($navChapters as $chapter)
              <li>
                <a href="#pres-chapter-{{ $chapter['key'] }}" class="kn-nav__chapter" data-kn-chapter-link="{{ $chapter['key'] }}" data-spine-chapter="{{ $chapter['key'] }}" data-spine-target="{{ $chapter['start'] }}">{{ $chapter['label'] }}</a>
              </li>
            @endforeach
          </ol>
        </nav>
        <div class="kn-nav__end">
          <p class="kn-nav__period"><span class="kn-sr">Posisi data </span>{{ $presentationPeriodLabel }}</p>
          <button type="button" class="kn-button kn-button--primary kn-nav__ppt" onclick="document.getElementById('ppt-export-dialog').showModal()">
            Unduh PPT
          </button>
          <div class="kn-menu" data-kn-menu>
            <button type="button" class="kn-menu__toggle" aria-haspopup="true" aria-expanded="false" aria-controls="kn-menu-panel" data-kn-menu-toggle>
              <span class="kn-sr">Alat presentasi</span>
              <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
            </button>
            <div class="kn-menu__panel" id="kn-menu-panel" role="menu" hidden data-kn-menu-panel>
              <div class="pres-quick-tools kn-menu__group" role="group" aria-label="Alat presentasi">
                <button type="button" role="menuitem" id="pres-compare-btn"><i class="fas fa-columns" aria-hidden="true"></i><span>Bandingkan periode</span><kbd>C</kbd></button>
                <button type="button" role="menuitem" id="pres-note-btn"><i class="fas fa-sticky-note" aria-hidden="true"></i><span>Catatan presenter</span><kbd>N</kbd></button>
                <button type="button" role="menuitem" id="pres-share-btn"><i class="fas fa-link" aria-hidden="true"></i><span>Salin tautan bagian ini</span></button>
                <button type="button" role="menuitem" id="pres-print-btn"><i class="fas fa-print" aria-hidden="true"></i><span>Cetak atau simpan PDF</span></button>
                <button type="button" role="menuitem" data-spine-autoplay aria-pressed="false"><i class="fas fa-play" aria-hidden="true"></i><span>Putar otomatis</span></button>
              </div>
              <p class="kn-menu__meta">
                <span class="kn-menu__scope">Cakupan <strong id="pres-global-scope-label">{{ $presentationScopeLabel }}</strong></span>
                <span class="pres-live-chip"><span class="pres-live-dot" aria-hidden="true"></span><span id="pres-live-status">Live Dashboard</span></span>
              </p>
              <p class="kn-menu__brand">
                <img src="{{ asset('images/danantara-logo-template.png') }}" alt="Danantara Indonesia">
                <img src="{{ asset('images/bri-logo-template.png') }}" alt="BRI">
              </p>
            </div>
          </div>
        </div>
      </div>
      <div class="kn-nav__progress" aria-hidden="true"><i data-kn-progress></i></div>
      <button type="button" id="pres-theme-btn" hidden tabindex="-1" aria-hidden="true"></button>
      <select id="pres-periode-selector" class="pres-date-picker-select" hidden tabindex="-1" aria-hidden="true">
        @foreach($periods as $p)
          <option value="{{ $p }}" {{ $p === $selectedPeriod ? 'selected' : '' }}>{{ \Carbon\Carbon::parse($p)->locale('id')->translatedFormat('d M Y') }}</option>
        @endforeach
      </select>
    </header>

    <dialog class="pres-export-dialog kn-dialog" id="ppt-export-dialog">
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
              @foreach($presentationExportScopes as $value => $label)
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
              <label class="pres-export-field"><span>Cabang timeseries</span><select name="funding_scope">@foreach($presentationExportScopes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
              <label class="pres-export-field"><span>Produk timeseries</span><select name="funding_product"><option value="total">Total Simpanan</option><option value="giro">Giro</option><option value="tabungan">Tabungan</option><option value="deposito">Deposito</option><option value="casa">CASA</option></select></label>
            </section>
            <section class="pres-export-group">
              <h3>SME</h3>
              <label class="pres-export-field"><span>Cabang timeseries</span><select name="sme_scope">@foreach($presentationExportScopes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
              <label class="pres-export-field"><span>Produk timeseries</span><select name="sme_product"><option value="total">Total SME</option><option value="non_cashcoll">Kredit Non Cashcoll</option><option value="cashcoll">Kredit Cashcoll</option></select></label>
            </section>
            <section class="pres-export-group">
              <h3>Konsumer</h3>
              <label class="pres-export-field"><span>Cabang timeseries</span><select name="consumer_scope">@foreach($presentationExportScopes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
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

    <dialog class="pres-tool-dialog pres-compare-dialog kn-dialog" id="pres-compare-dialog">
      <div class="pres-tool-dialog-head">
        <div>
          <span>Perbandingan</span>
          <h2>Perbandingan periode</h2>
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

    <dialog class="pres-tool-dialog pres-drilldown-dialog kn-dialog" id="pres-drilldown-dialog">
      <div class="pres-tool-dialog-head">
        <div>
          <span>Rincian</span>
          <h2 id="pres-drilldown-title">Detail data</h2>
        </div>
        <button type="button" data-close-dialog aria-label="Tutup detail"><i class="fas fa-times" aria-hidden="true"></i></button>
      </div>
      <div class="pres-drilldown-content" id="pres-drilldown-content"></div>
    </dialog>

    <dialog class="pres-tool-dialog pres-note-dialog kn-dialog" id="pres-note-dialog">
      <div class="pres-tool-dialog-head">
        <div>
          <span>Presenter</span>
          <h2 id="pres-note-title">Catatan bagian</h2>
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

    <div class="pres-toast kn-toast" id="pres-toast" role="status" aria-live="polite"></div>

    <main class="pres-slides-container kn-report" id="kn-report">
      @foreach($presentationStory as $index => $story)
        @if($chapterOpeners->has($index))
          @php $chapter = $chapterOpeners->get($index); @endphp
          <section
            class="pres-chapter kn-statement kn-statement--{{ $chapter['key'] }} kn-ground--{{ $chapter['ground'] }} {{ !empty($chapter['theatre']) ? 'kn-statement--theatre' : '' }}"
            id="pres-chapter-{{ $chapter['key'] }}"
            data-chapter="{{ $chapter['key'] }}"
            data-chapter-start="{{ $chapter['start'] }}"
            data-kn-statement="{{ $chapter['key'] }}"
            aria-labelledby="pres-chapter-{{ $chapter['key'] }}-title"
          >
            <div class="kn-statement__track">
              <div class="kn-statement__stage" data-relief-slot="{{ $chapter['key'] }}" aria-hidden="true">
                <div class="kn-relief-poster" data-relief-poster="{{ $chapter['key'] }}"></div>
              </div>
              <div class="kn-statement__copy">
                <p class="kn-statement__chapter">{{ $chapter['label'] }}</p>
                <h2 class="kn-statement__title" id="pres-chapter-{{ $chapter['key'] }}-title" data-kn-lines>{{ $chapter['title'] }}</h2>
                @if(!empty($chapter['theatre']))
                  <div class="kn-beats" data-kn-beats>
                    <div class="kn-beat is-current" data-kn-beat="sml">
                      <p class="kn-figure"><span class="kn-figure__value" data-figure="sml">Memuat</span></p>
                      <p class="kn-figure__label">Special Mention Loan, <span data-figure-ratio="sml">rasio memuat</span> dari outstanding.</p>
                    </div>
                    <div class="kn-beat" data-kn-beat="npl">
                      <p class="kn-figure"><span class="kn-figure__value" data-figure="npl">Memuat</span></p>
                      <p class="kn-figure__label">Non-Performing Loan, <span data-figure-ratio="npl">rasio memuat</span> dari outstanding.</p>
                    </div>
                  </div>
                @else
                  <p class="kn-figure"><span class="kn-figure__value" data-figure="{{ $chapter['figure'] }}">Memuat</span></p>
                  <p class="kn-figure__label"><span data-figure-label="{{ $chapter['figure'] }}">{{ ucfirst($chapter['figureLabel']) }}</span><span class="kn-figure__trend" data-figure-trend="{{ $chapter['figure'] }}"></span></p>
                @endif
                <p class="kn-statement__lede">{{ $chapter['lede'] }}</p>
                @if(!empty($chapter['relief']))
                  <p class="kn-statement__note" data-figure-note="{{ $chapter['figure'] }}">{{ $chapter['relief'] }}</p>
                @endif
                <ul class="kn-statement__contents" aria-label="Isi bab {{ $chapter['label'] }}">
                  @foreach(range($chapter['start'], $chapter['end']) as $chapterSlide)
                    <li><a href="#pres-slide-{{ $chapterSlide }}" data-spine-target="{{ $chapterSlide }}">{{ $presentationStory[$chapterSlide]['label'] }}<i class="fas fa-arrow-down" aria-hidden="true"></i></a></li>
                  @endforeach
                </ul>
              </div>
            </div>
          </section>
        @endif

        <section
          class="pres-report-section kn-sheet kn-ground--{{ $panelGround[$index] ?? 'light' }} {{ isset($story['progressive']) ? 'is-section-loading' : '' }}"
          id="pres-slide-{{ $index }}"
          data-panel-index="{{ $index }}"
          data-chapter="{{ $panelChapter[$index] ?? 'pembuka' }}"
          data-story-key="{{ $story['key'] }}"
          data-story-label="{{ $story['label'] }}"
          @isset($story['progressive']) data-progressive-section="{{ $story['progressive'] }}" aria-busy="true" @endisset
          @if($index === 0) data-relief-slot-host="hero" @endif
          aria-label="{{ $story['label'] }}"
        >
          <div class="rp-skeleton" aria-hidden="true"><span></span><span></span><span></span></div>
        </section>
      @endforeach

      <section class="kn-close kn-ground--dark" id="pres-close" data-chapter="penutup" aria-labelledby="pres-close-title">
        <div class="kn-close__stage" data-relief-slot="close" aria-hidden="true">
          <div class="kn-relief-poster" data-relief-poster="close"></div>
        </div>
        <div class="kn-close__copy">
          <h2 class="kn-close__title" id="pres-close-title">
            Kinerja {{ $presentationTitle }}.<br>
            <span class="kn-close__period">Posisi {{ $presentationPeriodLong }}.</span>
          </h2>
          <p class="kn-close__actions">
            <button type="button" class="kn-button kn-button--primary kn-button--large" onclick="document.getElementById('ppt-export-dialog').showModal()">Unduh PPT</button>
            <a class="kn-link" href="{{ route('dashboard', ['periode' => $selectedPeriod]) }}">Kembali ke dashboard<i class="fas fa-chevron-right" aria-hidden="true"></i></a>
            <a class="kn-link" href="#pres-slide-0" data-spine-target="0">Baca dari awal<i class="fas fa-chevron-right" aria-hidden="true"></i></a>
          </p>
          <p class="kn-close__brand">
            <img src="{{ asset('images/danantara-logo-template.png') }}" alt="Danantara Indonesia">
            <img src="{{ asset('images/bri-logo-template.png') }}" alt="Bank Rakyat Indonesia">
          </p>
        </div>
      </section>
    </main>

    {{-- Controls the shared engine still binds; the scroll report drives them from the menu. --}}
    <div class="pres-bottom-bar kn-legacy" hidden aria-hidden="true">
      <button type="button" class="pres-nav-btn" id="pres-prev-btn" tabindex="-1"></button>
      <div class="pres-paginator" id="pres-paginator-dots"></div>
      <div class="pres-slide-counter-badge" id="pres-slide-counter-badge"></div>
      <button type="button" class="pres-autoplay-btn" id="pres-autoplay-btn" tabindex="-1"></button>
      <span id="pres-auto-state"></span>
      <span class="pres-auto-progress-fill" id="pres-auto-progress-fill"></span>
      <button type="button" class="pres-nav-btn" id="pres-next-btn" tabindex="-1"></button>
    </div>
  </div>

  <div class="dashboard-loading-overlay active kn-loader" id="dashboard-global-loader" role="status" aria-live="polite">
    <p class="kn-loader__title">Kinerja {{ $presentationTitle }}</p>
    <div class="kn-loader__track" aria-hidden="true"><i id="loading-progress-bar"></i></div>
    <p class="kn-loader__meta"><span class="dashboard-loading-sub" id="dashboard-loading-status">Menyiapkan laporan</span><span id="loading-progress-percent">0%</span></p>
    <span class="dashboard-loading-text kn-sr">Memuat data presentasi</span>
  </div>

  @php
    $presentationConfig = [
      'selectedPeriod' => $selectedPeriod,
      'branchScope' => $presentationBranchScope['label'] ?? null,
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
      'geographyUrl' => asset('data/marketshare-area6-kecamatan.geojson'),
      'keynote' => true,
    ];
  @endphp
  <script>
    window.__PRESENTATION_CONFIG__ = @json($presentationConfig);
  </script>
  @vite('resources/js/presentation/pres-engine.js')
</body>
</html>
