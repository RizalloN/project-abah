@php
  $presentationStory = [
    ['key' => 'cover', 'label' => 'Cover'],
    ['key' => 'agenda', 'label' => 'Daftar Isi'],
    ['key' => 'market-share-area6', 'label' => 'Market Share Area 6', 'progressive' => 'marketshare'],
    ['key' => 'market-share-mapping', 'label' => 'Mapping Market Share', 'progressive' => 'marketshare'],
    ['key' => 'funding-summary', 'label' => 'Summary Funding'],
    ['key' => 'funding-product', 'label' => 'Funding per Produk'],
    ['key' => 'strategies', 'label' => '8 Strategi', 'progressive' => 'digital'],
    ['key' => 'loan-summary', 'label' => 'Outstanding Summary'],
    ['key' => 'loan-sme', 'label' => 'Pinjaman SME'],
    ['key' => 'loan-consumer', 'label' => 'Pinjaman Konsumer'],
    ['key' => 'loan-micro', 'label' => 'Highlight Mikro', 'progressive' => 'micro'],
    ['key' => 'quality-sml', 'label' => 'Kualitas SML'],
    ['key' => 'quality-npl', 'label' => 'Kualitas NPL'],
    ['key' => 'timeseries', 'label' => 'Timeseries Terintegrasi', 'progressive' => 'timeseries'],
    ['key' => 'closing', 'label' => 'Prioritas Aksi', 'progressive' => 'digital'],
  ];
  $isScrollLayout = ($presentationLayout ?? 'slides') === 'scroll';
  $chapterOpeners = collect($presentationChapters ?? [])->filter(fn ($chapter) => $chapter['opener'] ?? false)->keyBy('start');
  $panelChapter = [];
  foreach ($presentationChapters ?? [] as $chapter) {
    foreach (range($chapter['start'], $chapter['end']) as $chapterIndex) {
      $panelChapter[$chapterIndex] = $chapter['key'];
    }
  }
@endphp

@foreach($presentationStory as $index => $story)
  @if($isScrollLayout && $chapterOpeners->has($index))
    @php $chapter = $chapterOpeners->get($index); @endphp
    <section
      class="pres-chapter pres-chapter--{{ $chapter['key'] }} pres-chapter--{{ $chapter['anchor'] }}"
      id="pres-chapter-{{ $chapter['key'] }}"
      data-sc-act="pin"
      data-sc-span="{{ $chapter['span'] }}"
      data-sc-drift="{{ $chapter['ground'] }}"
      data-chapter="{{ $chapter['key'] }}"
      data-chapter-start="{{ $chapter['start'] }}"
      aria-labelledby="pres-chapter-{{ $chapter['key'] }}-title"
    >
      <div class="sc-stage pres-chapter__stage">
        {{-- A 3D sculpture built from this chapter's figures; the CSS art stays as the no-WebGL poster. --}}
        <div class="pres-world pres-world--{{ $chapter['key'] }}" data-world="{{ $chapter['key'] }}">
          @if(in_array($chapter['key'], ['pinjaman', 'kualitas'], true))
            <div class="pres-chapter__art pres-world__fallback" data-chapter-art="{{ $chapter['key'] }}" data-sp></div>
          @endif
        </div>
        <div class="pres-chapter__inner">
          <h2 class="pres-chapter__title" id="pres-chapter-{{ $chapter['key'] }}-title" data-sc-kinetic="lines" data-sc-cue="0">{{ $chapter['title'] }}</h2>
          <p class="pres-chapter__lede" data-sc-cue="0.14">{{ $chapter['lede'] }}</p>
          @if(!empty($chapter['figures']))
            <dl class="pres-chapter__figures" data-sc-cue="0.26">
              @foreach($chapter['figures'] as $figureKey)
                <div class="pres-figure pres-figure--{{ $figureKey }}">
                  <dt data-ledger-label="{{ $figureKey }}">{{ $presentationLedgerLabels[$figureKey] }}</dt>
                  <dd class="pres-figure__value" data-ledger-value="{{ $figureKey }}">Memuat</dd>
                  <dd class="pres-figure__trend" data-ledger-trend="{{ $figureKey }}"></dd>
                </div>
              @endforeach
            </dl>
          @endif
          <ol class="pres-chapter__contents" data-sc-cue="0.38" aria-label="Isi bab {{ $chapter['label'] }}">
            @foreach(range($chapter['start'], $chapter['end']) as $chapterSlide)
              <li><a href="#slide={{ $chapterSlide }}" data-spine-target="{{ $chapterSlide }}">{{ $presentationStory[$chapterSlide]['label'] }}</a></li>
            @endforeach
          </ol>
        </div>
      </div>
    </section>
  @endif

  @if($isScrollLayout)
    <section
      class="pres-report-section pres-report-section--{{ $story['key'] }} {{ isset($story['progressive']) ? 'is-section-loading' : '' }}"
      id="pres-slide-{{ $index }}"
      data-panel-index="{{ $index }}"
      data-chapter="{{ $panelChapter[$index] ?? 'pembuka' }}"
      data-story-key="{{ $story['key'] }}"
      data-story-label="{{ $story['label'] }}"
      @isset($story['progressive']) data-progressive-section="{{ $story['progressive'] }}" aria-busy="true" @endisset
      @if($index === 0) data-sc-drift="{{ $presentationChapters[0]['ground'] ?? '#071A3D' }}" data-sc-act="flow" @endif
      aria-label="{{ $story['label'] }}"
    >
      <div class="rp-skeleton" aria-hidden="true"><span></span><span></span><span></span></div>
    </section>
  @else
  <section
    class="apple-slide pres-structured-shell {{ $index === 0 ? 'active' : '' }} {{ isset($story['progressive']) ? 'is-section-loading' : '' }}"
    id="pres-slide-{{ $index }}"
    data-story-key="{{ $story['key'] }}"
    data-story-label="{{ $story['label'] }}"
    @isset($story['progressive']) data-progressive-section="{{ $story['progressive'] }}" aria-busy="true" @endisset
  >
    <div class="pres-structured-loading" aria-hidden="true">
      <div class="pres-structured-loading-kicker"></div>
      <div class="pres-structured-loading-title"></div>
      <div class="pres-structured-loading-grid">
        <span></span><span></span><span></span><span></span>
      </div>
    </div>
  </section>
  @endif
@endforeach

@if($isScrollLayout)
  <section class="pres-close" id="pres-close" data-sc-act="flow" data-sc-drift="#0B4FB0" aria-labelledby="pres-close-title">
    <div class="pres-close__inner">
      <h2 class="pres-close__title" id="pres-close-title" data-sc-in>
        Kinerja <span data-ledger-scope>{{ $presentationScopeLabel }}</span>
        per {{ $presentationPeriodLabel }}
      </h2>
      <dl class="pres-close__ledger" data-sc-in data-sc-stagger="70">
        @foreach($presentationLedgerLabels as $ledgerKey => $ledgerLabel)
          <div class="pres-close__figure">
            <dt>{{ $ledgerLabel }}</dt>
            <dd data-ledger-value="{{ $ledgerKey }}">Memuat</dd>
          </div>
        @endforeach
      </dl>
      <div class="pres-close__actions">
        <button type="button" class="pres-close__action pres-close__action--primary" onclick="document.getElementById('ppt-export-dialog').showModal()">
          <i class="fas fa-file-powerpoint" aria-hidden="true"></i><span>Unduh PPT</span>
        </button>
        <a class="pres-close__action" href="{{ route('dashboard', ['periode' => $selectedPeriod]) }}">
          <i class="fas fa-arrow-left" aria-hidden="true"></i><span>Kembali ke Dashboard</span>
        </a>
        <button type="button" class="pres-close__action" data-spine-target="0">
          <i class="fas fa-arrow-up" aria-hidden="true"></i><span>Baca dari awal</span>
        </button>
      </div>
    </div>
  </section>
@endif
