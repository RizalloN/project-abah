<?php

use Tests\TestCase;

uses(TestCase::class);

function renderPresentationStory(string $layout): string
{
    return view('presentation._executive-slides', [
        'presentationLayout' => $layout,
        'selectedPeriod' => '2026-10-15',
        'presentationScopeLabel' => 'Area 6 Konsol',
        'presentationPeriodLabel' => '15 Oct 2026',
        'presentationLedgerLabels' => ['simpanan' => 'Simpanan', 'os' => 'Outstanding', 'ldr' => 'LDR', 'sml' => 'SML', 'npl' => 'NPL'],
        'presentationChapters' => [
            ['key' => 'pembuka', 'label' => 'Pembuka', 'start' => 0, 'end' => 1, 'ground' => '#071A3D', 'opener' => false],
            ['key' => 'pasar', 'label' => 'Pasar', 'start' => 2, 'end' => 3, 'ground' => '#0A2550', 'opener' => true, 'span' => 1.5, 'anchor' => 'lead', 'title' => 'Posisi kita di pasar', 'lede' => 'Lede.', 'figures' => []],
            ['key' => 'dana', 'label' => 'Dana', 'start' => 4, 'end' => 6, 'ground' => '#062F63', 'opener' => true, 'span' => 1.5, 'anchor' => 'trail', 'title' => 'Dana yang kita himpun', 'lede' => 'Lede.', 'figures' => ['simpanan']],
            ['key' => 'pinjaman', 'label' => 'Pinjaman', 'start' => 7, 'end' => 10, 'ground' => '#082C3C', 'opener' => true, 'span' => 1.5, 'anchor' => 'lead', 'title' => 'Kredit yang kita salurkan', 'lede' => 'Lede.', 'figures' => ['os', 'ldr']],
            ['key' => 'kualitas', 'label' => 'Kualitas', 'start' => 11, 'end' => 12, 'ground' => '#2A0C14', 'opener' => true, 'span' => 2.2, 'anchor' => 'split', 'title' => 'Kualitas kredit', 'lede' => 'Lede.', 'figures' => ['sml', 'npl']],
            ['key' => 'tren', 'label' => 'Tren', 'start' => 13, 'end' => 13, 'ground' => '#0E1828', 'opener' => true, 'span' => 1.4, 'anchor' => 'trail', 'title' => 'Arah pergerakan', 'lede' => 'Lede.', 'figures' => []],
            ['key' => 'aksi', 'label' => 'Aksi', 'start' => 14, 'end' => 14, 'ground' => '#0A3E8C', 'opener' => true, 'span' => 1.4, 'anchor' => 'lead', 'title' => 'Prioritas aksi', 'lede' => 'Lede.', 'figures' => []],
        ],
    ])->render();
}

it('keeps all fifteen sections in their original order as native report sections', function (): void {
    $html = renderPresentationStory('scroll');

    preg_match_all('/id="pres-slide-(\d+)"/', $html, $slides);
    preg_match_all('/data-panel-index="(\d+)"/', $html, $panels);

    expect(array_map('intval', $slides[1]))->toBe(range(0, 14))
        ->and(array_map('intval', $panels[1]))->toBe(range(0, 14))
        ->and(substr_count($html, 'class="pres-report-section'))->toBe(15)
        ->and($html)->not->toContain('apple-slide')
        ->not->toContain('pres-scroll-frame');
});

it('opens each chapter before its first panel and pins it as a scroll act', function (): void {
    $html = renderPresentationStory('scroll');

    foreach (['pasar' => 2, 'dana' => 4, 'pinjaman' => 7, 'kualitas' => 11, 'tren' => 13, 'aksi' => 14] as $chapter => $firstSlide) {
        $openerAt = strpos($html, 'id="pres-chapter-'.$chapter.'"');
        expect($openerAt)->not->toBeFalse()
            ->and($openerAt)->toBeLessThan(strpos($html, 'id="pres-slide-'.$firstSlide.'"'));
    }

    expect($html)
        ->toContain('data-sc-act="pin"')
        ->toContain('data-sc-span="2.2"') // the quality chapter owns the most scroll of the openers; the orbit descent is the page peak
        ->toContain('data-ledger-value="npl"')
        ->toContain('id="pres-close"')
        ->not->toContain('—');
});

it('leaves the classic slide deck markup untouched', function (): void {
    $html = renderPresentationStory('slides');

    expect($html)
        ->toContain('id="pres-slide-14"')
        ->toContain('apple-slide')
        ->not->toContain('pres-report-section')
        ->not->toContain('pres-chapter')
        ->not->toContain('pres-close');
});

it('routes navigation through the scroll deck without moving the page on refreshes', function (): void {
    $engine = file_get_contents(resource_path('js/presentation/pres-engine.js'));
    $interactions = file_get_contents(resource_path('js/presentation/pres-interactions.js'));
    $view = file_get_contents(resource_path('views/presentation.blade.php'));

    expect($engine)
        ->toContain("presMode?.dataset.layout === 'scroll'")
        ->toContain('scrollDeck.scrollToIndex(index)')
        ->toContain('scrollDeck?.update(data, deckState.scope)')
        ->toContain('activateSlide(currentSlideIndex)')
        ->and($interactions)->toContain("root?.dataset?.layout === 'scroll' ? null")
        ->and($view)
        ->toContain("request()->query('layout') === 'slides' ? 'slides' : 'scroll'")
        ->toContain('data-layout="{{ $presentationLayout }}"');
});

it('ships the scroll-craft engine unmodified with its licence', function (): void {
    $engine = resource_path('js/presentation/vendor/scrollcraft.js');

    expect(file_exists($engine))->toBeTrue()
        ->and(file_exists(resource_path('js/presentation/vendor/scrollcraft.LICENSE')))->toBeTrue()
        ->and(file_get_contents($engine))->toContain('global.ScrollCraft = { mount: mount');
});

it('rebuilds every section with the scroll renderer while reading data through the deck accessors', function (): void {
    $renderer = file_get_contents(resource_path('js/presentation/pres-scroll-report.js'));
    $engine = file_get_contents(resource_path('js/presentation/pres-engine.js'));
    $deck = file_get_contents(resource_path('js/presentation/pres-structured-deck.js'));

    expect($renderer)
        ->toContain('export class ScrollReportRenderer extends StructuredDeckRenderer')
        ->toContain('this.comparisonScope()')
        ->toContain('this.timeseriesSeries(')
        ->toContain('this.marketShareAreaPayload()')
        ->toContain('Posisi historis')
        ->toContain('data-market-journey')
        ->toContain('data-market-scene')
        ->toContain('data-mapping-cue')
        ->toContain('data-psd-timeseries-keys')
        ->not->toContain('data-psd-funding-product')
        ->not->toContain('data-psd-micro-view')
        ->not->toContain('data-psd-trend-group')
        ->not->toContain('data-psd-marketshare-segment')
        ->not->toContain('<select')
        ->not->toContain('—');

    foreach (['scrollCover', 'scrollAgenda', 'scrollMarketShare', 'scrollMapping', 'scrollFundingSummary', 'scrollFundingProducts',
        'scrollStrategies', 'scrollLoanSummary', "scrollSegment('sme')", "scrollSegment('consumer')", 'scrollMicro',
        "scrollQuality('sml')", "scrollQuality('npl')", 'scrollTimeseries', 'scrollPriorities'] as $section) {
        expect($renderer)->toContain("() => this.{$section}");
    }

    expect($engine)->toContain('scrollLayout ? ScrollReportRenderer : StructuredDeckRenderer')
        ->and($deck)->toContain('comparisonPresentationState,');
});

it('lands count-ups on the exact formatted figure and keeps motion optional', function (): void {
    $driver = file_get_contents(resource_path('js/presentation/pres-scroll.js'));
    $styles = file_get_contents(resource_path('css/presentation/pres-scroll.css'));

    expect($driver)
        ->toContain("element.textContent = t >= 1 ? finalText : format(figure.value * eased)")
        ->toContain('reduceMotion()')
        ->and($styles)
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->toContain('.pres-report-section .is-blue')
        ->not->toMatch('/^\.is-(blue|q4)\s*\{/m');
});

it('gives every chapter opener a 3D world while keeping the CSS sculptures as the no-WebGL poster', function (): void {
    $html = renderPresentationStory('scroll');

    foreach (['pasar', 'dana', 'pinjaman', 'kualitas', 'tren', 'aksi'] as $chapter) {
        expect($html)->toContain('data-world="'.$chapter.'"');
    }

    expect($html)
        ->toContain('class="pres-chapter__art pres-world__fallback" data-chapter-art="pinjaman"')
        ->toContain('class="pres-chapter__art pres-world__fallback" data-chapter-art="kualitas"');
});

it('opens on Area 6 geography and keeps the penetration map tied to the report data', function (): void {
    $renderer = file_get_contents(resource_path('js/presentation/pres-scroll-report.js'));
    $director = file_get_contents(resource_path('js/presentation/worlds/director.js'));
    $globe = file_get_contents(resource_path('js/presentation/worlds/globe.js'));
    $engine = file_get_contents(resource_path('js/presentation/pres-engine.js'));

    expect($renderer)
        ->toContain('data-world="area6"')
        ->toContain('data-world="penetration"')
        ->toContain('data-world-track="pin"')
        ->not->toContain('data-area6-district')
        ->toContain('Bank lain (agregat)')
        ->toContain('Potensi per cabang.')
        ->not->toContain('Mulai dari Bumi')
        ->not->toContain('Setiap prisma')
        ->toContain('worldData()')
        ->toContain('this.marketShareDistrictValues(code, units, \'total\')')
        ->not->toContain('—')
        ->and($engine)->toContain('worldSource: () => structuredDeck.worldData?.()')
        // Three.js stays out of the main bundle and loads only when a world nears the viewport.
        ->and($director)->toContain("import('./engine.js')")
        ->toContain('supportsWebGL()')
        ->toContain('pres-worlds-off')
        ->and($globe)->toContain('territoryFlight')
        ->and(file_get_contents(resource_path('js/presentation/worlds/territory-flight.js')))->toContain('earth_day_4096.jpg');

    $geography = json_decode(file_get_contents(public_path('data/marketshare-area6-kecamatan.geojson')), true, flags: JSON_THROW_ON_ERROR);
    $features = collect($geography['features']);
    $codes = $features->pluck('properties.KDCPUM');
    expect($features)->toHaveCount(76)
        ->and($codes->unique())->toHaveCount(76)
        ->and($features->countBy('properties.KDPKAB')->sortKeys()->all())->toBe([
            '35.02' => 21, '35.19' => 15, '35.20' => 18, '35.21' => 19, '35.77' => 3,
        ])
        ->and(file_exists(public_path('images/area6-regions.svg')))->toBeTrue();
});

it('keeps the depth layer readable without motion', function (): void {
    $depth = file_get_contents(resource_path('css/presentation/pres-depth.css'));
    $bundle = file_get_contents(resource_path('css/presentation/presentation.css'));

    expect($bundle)->toContain('@import "./pres-depth.css";')
        ->and($depth)
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->toContain('html.pres-scroll-layout #pres-slide-3.pres-report-section { overflow: clip; }')
        ->toContain('.rp-orbit__beat { position: static; opacity: 1; transform: none; }')
        ->not->toContain('—');
});

it('serves the keynote by default while the deck and the classic scroll stay reachable', function (): void {
    $controller = file_get_contents(app_path('Http/Controllers/DashboardSimpananController.php'));

    expect($controller)
        ->toContain("in_array(\$layout, ['slides', 'classic'], true) ? 'presentation' : 'presentation.keynote'")
        ->and(file_get_contents(base_path('vite.config.js')))
        ->toContain('resources/css/presentation/presentation-keynote.css');

    $html = view('presentation.keynote', [
        'selectedPeriod' => '2026-10-24',
        'periods' => ['2026-10-24', '2026-10-23'],
        'presentationPayload' => null,
    ])->render();

    preg_match_all('/id="pres-slide-(\d+)"/', $html, $slides);

    expect($html)
        ->toContain('data-keynote')
        ->toContain('id="ppt-export-dialog"')
        ->toContain('id="pres-compare-dialog"')
        ->toContain('id="pres-note-dialog"')
        ->and(array_values(array_unique(array_map('intval', $slides[1]))))->toBe(range(0, 14));

    foreach (['pasar', 'dana', 'pinjaman', 'kualitas', 'tren', 'aksi'] as $chapter) {
        expect($html)->toContain('id="pres-chapter-'.$chapter.'"')
            ->toContain('data-kn-chapter-link="'.$chapter.'"');
    }
});
