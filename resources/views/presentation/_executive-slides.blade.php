@php
  $presentationStory = [
    ['key' => 'cover', 'label' => 'Cover'],
    ['key' => 'agenda', 'label' => 'Daftar Isi'],
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
@endphp

@foreach($presentationStory as $index => $story)
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
@endforeach
