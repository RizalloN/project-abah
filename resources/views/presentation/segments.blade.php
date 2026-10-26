<!DOCTYPE html>
@php
  $segmentPeriodLabel = $selectedPeriod ? \Carbon\Carbon::parse($selectedPeriod)->locale('id')->translatedFormat('d M Y') : '-';
  $segmentScopeLabel = collect($segmentScopes)->firstWhere('key', $segmentScope)['label'] ?? 'Area 6 Konsol';
  $branchOnly = count($segmentScopes) === 1;

  // The two families of segments the reader can pick from; order here is the order of the presentation.
  $segmentGroups = [
    ['key' => 'wilayah', 'label' => 'Wilayah', 'lede' => 'Posisi kita di pasar dan di peta.', 'segments' => [
      ['key' => 'marketshare', 'label' => 'Marketshare', 'meta' => 'Simpanan dan pinjaman BRI dibanding industri, per cabang.'],
      ['key' => 'mapping', 'label' => 'Mapping Wilayah', 'meta' => 'Potensi, nasabah existing, dan penetrasi per unit.'],
      ['key' => 'sektoral', 'label' => 'Penetrasi Sektoral', 'meta' => 'Potensi sektor dan rincian subsektor CRAS.'],
    ]],
    ['key' => 'kinerja', 'label' => 'Kinerja', 'lede' => 'Angka bisnis dari snapshot harian.', 'segments' => [
      ['key' => 'simpanan', 'label' => 'Kinerja Simpanan', 'meta' => 'Giro, tabungan, deposito; ritel dan mikro.'],
      ['key' => 'pinjaman', 'label' => 'Kinerja Pinjaman', 'meta' => 'SME, Konsumer, Mikro, dan setiap produknya.'],
      ['key' => 'kualitas', 'label' => 'Kinerja Kualitas', 'meta' => 'SML dan NPL per segmen dan produk.'],
      ['key' => 'recovery', 'label' => 'Kinerja Recovery DH', 'meta' => 'Recovery daftar hitam per segmen.'],
    ]],
  ];

  $segmentConfig = [
    'period' => $selectedPeriod,
    'periodLabel' => $segmentPeriodLabel,
    'scope' => $segmentScope,
    'scopes' => $segmentScopes,
    'branchOnly' => $branchOnly,
    'groups' => $segmentGroups,
    'segmentsUrl' => route('dashboard.presentation-data.segments'),
    'wilayahUrl' => route('dashboard.presentation-data.wilayah'),
    'crasUrl' => route('report.dashboard-dana.market-share.mapping-cras.data'),
    'geographyUrl' => asset('data/marketshare-area6-kecamatan.geojson'),
    'presentationUrl' => route('dashboard.presentation'),
    'dashboardUrl' => route('dashboard', ['periode' => $selectedPeriod]),
    'keynoteUrl' => route('dashboard.presentation', ['layout' => 'keynote', 'periode' => $selectedPeriod]),
  ];
@endphp
<html lang="id" class="pres-segments">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Presentasi Kinerja, {{ $segmentPeriodLabel }}</title>
  <meta name="description" content="Presentasi bersegmen posisi {{ $segmentPeriodLabel }}: marketshare, mapping wilayah, penetrasi sektoral, simpanan, pinjaman, kualitas, dan recovery DH.">
  <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
  <meta name="theme-color" content="#0b0b0d">
  @vite('resources/css/presentation/presentation-segments.css')
</head>
<body class="kn sg">
  <a class="kn-skip" href="#sg-launcher">Lewati ke pilihan segmen</a>

  <header class="kn-nav sg-nav is-on-dark" data-sg-nav>
    <div class="kn-nav__inner">
      <a href="{{ route('dashboard', ['periode' => $selectedPeriod]) }}" class="kn-nav__back" title="Kembali ke dashboard" aria-label="Kembali ke dashboard">
        <i class="fas fa-chevron-left" aria-hidden="true"></i><span>Dashboard</span>
      </a>
      <p class="kn-nav__title">Kinerja <span data-sg-scope-label>{{ $segmentScopeLabel }}</span></p>
      <nav class="kn-nav__chapters sg-nav__segments" aria-label="Segmen presentasi" data-sg-nav-chapters hidden>
        <ol data-sg-nav-segments></ol>
      </nav>
      <div class="kn-nav__end">
        <p class="kn-nav__period"><span class="kn-sr">Posisi data </span>{{ $segmentPeriodLabel }}</p>
        <button type="button" class="kn-button kn-button--primary kn-nav__ppt" data-sg-open-launcher hidden>Pilih segmen</button>
      </div>
    </div>
    <div class="kn-nav__progress" aria-hidden="true"><i data-sg-progress></i></div>
  </header>

  <main class="sg-main" id="sg-main">
    <section class="sg-launcher" id="sg-launcher" aria-labelledby="sg-launcher-title" data-territory-track="pin">
      <div class="sg-launcher__sticky">
        <div class="sg-launcher__stage" data-territory="cover" data-territory-pose="hero" aria-hidden="true"><div class="sg-poster" data-territory-poster></div></div>
        <div class="sg-launcher__head">
          <p class="sg-kicker">Mode presentasi &middot; Posisi {{ $segmentPeriodLabel }}</p>
          <h1 class="sg-launcher__title" id="sg-launcher-title">Pilih yang ingin<br>Anda presentasikan.</h1>
        </div>
      </div>
      <form class="sg-panel" data-sg-form aria-label="Pilihan presentasi">
        <fieldset class="sg-panel__scope">
          <legend>{{ $branchOnly ? 'Cakupan' : 'Filter area atau cabang' }}</legend>
          <div class="sg-scopes" role="radiogroup">
            @foreach($segmentScopes as $scope)
              <label class="sg-scope">
                <input type="radio" name="scope" value="{{ $scope['key'] }}" @checked($scope['key'] === $segmentScope)>
                <span><strong>{{ $scope['label'] }}</strong><small>{{ $scope['key'] === 'area6' ? 'Rincian per cabang' : 'Rincian per unit' }}</small></span>
              </label>
            @endforeach
          </div>
        </fieldset>
        @foreach($segmentGroups as $group)
          <fieldset class="sg-panel__group" data-sg-group="{{ $group['key'] }}">
            <legend>
              <span>{{ $group['label'] }}</span>
              <button type="button" class="sg-toggle-all" data-sg-toggle-group="{{ $group['key'] }}">Pilih semua</button>
            </legend>
            <p class="sg-panel__lede">{{ $group['lede'] }}</p>
            <div class="sg-cards">
              @foreach($group['segments'] as $segment)
                <label class="sg-card" data-sg-card="{{ $segment['key'] }}">
                  <input type="checkbox" name="segmen" value="{{ $segment['key'] }}">
                  <span class="sg-card__check" aria-hidden="true"><i class="fas fa-check"></i></span>
                  <strong>{{ $segment['label'] }}</strong>
                  <small>{{ $segment['meta'] }}</small>
                </label>
              @endforeach
            </div>
          </fieldset>
        @endforeach
        <div class="sg-panel__actions" data-sg-actions>
          <p class="sg-panel__summary" data-sg-summary role="status" aria-live="polite">Pilih paling sedikit satu segmen terlebih dahulu untuk memulai presentasi.</p>
          <button type="submit" class="kn-button kn-button--primary kn-button--large sg-start" disabled>Mulai presentasi<i class="fas fa-arrow-down" aria-hidden="true"></i></button>
        </div>
        <p class="sg-panel__foot">
          <a class="kn-link" href="{{ route('dashboard.presentation', ['layout' => 'keynote', 'periode' => $selectedPeriod]) }}">Versi keynote dan ekspor PowerPoint<i class="fas fa-chevron-right" aria-hidden="true"></i></a>
        </p>
      </form>
    </section>

    <div class="sg-deck" id="sg-deck" data-sg-deck aria-live="polite" hidden aria-hidden="true"></div>
  </main>

  <div class="dashboard-loading-overlay active kn-loader" id="sg-loader" role="status" aria-live="polite">
    <p class="kn-loader__title">Mode presentasi</p>
    <div class="kn-loader__track" aria-hidden="true"><i data-sg-loader-bar></i></div>
    <p class="kn-loader__meta"><span class="dashboard-loading-sub" data-sg-loader-status>Menyiapkan data</span><span data-sg-loader-percent>0%</span></p>
  </div>

  <script>
    window.__SEGMENT_CONFIG__ = @json($segmentConfig);
  </script>
  @vite('resources/js/presentation/segments/main.js')
</body>
</html>
