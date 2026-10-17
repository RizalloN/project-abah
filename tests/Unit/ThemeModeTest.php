<?php

namespace Tests\Unit;

use Tests\TestCase;

class ThemeModeTest extends TestCase
{
    public function test_theme_partial_renders_pre_paint_bootstrap_tokens_and_engine(): void
    {
        $html = view('layouts.partials.theme')->render();

        $this->assertStringContainsString("localStorage.getItem('asix-theme')", $html);
        $this->assertStringContainsString("root.setAttribute('data-theme'", $html);
        $this->assertStringContainsString('data-theme-follow-system', $html);
        $this->assertStringContainsString('id="asix-theme-tokens" data-theme-skip', $html);
        $this->assertStringContainsString(':root[data-theme="dark"] {', $html);
        $this->assertStringContainsString('--app-surface: #142238;', $html);
        $this->assertStringContainsString('js/theme/theme-engine.js', $html);
        $this->assertStringContainsString('defer data-theme-skip', $html);
        // The bootstrap script must run before any stylesheet paints the page.
        $this->assertLessThan(strpos($html, '<style'), strpos($html, '<script data-theme-skip>'));
    }

    public function test_every_full_page_loads_the_theme_and_follows_the_system_preference(): void
    {
        foreach ([
            'views/layouts/admin.blade.php',
            'views/auth/login.blade.php',
            'views/errors/503.blade.php',
            'views/errors/database-unavailable.blade.php',
        ] as $view) {
            $source = file_get_contents(resource_path($view));

            $this->assertStringContainsString('<html lang="id" data-theme-follow-system="1">', $source, $view);
            $this->assertStringContainsString("@include('layouts.partials.theme')", $source, $view);
            $this->assertLessThan(
                strpos($source, '<link rel="stylesheet"') ?: strpos($source, '<style'),
                strpos($source, "@include('layouts.partials.theme')"),
                $view.' must include the theme before its own styles'
            );
        }
    }

    public function test_toggle_is_an_accessible_button_in_navbar_and_login(): void
    {
        foreach (['views/layouts/admin.blade.php', 'views/auth/login.blade.php'] as $view) {
            $source = file_get_contents(resource_path($view));

            $this->assertMatchesRegularExpression(
                '/<button type="button" class="theme-toggle[^"]*" data-theme-toggle aria-pressed="false" aria-label="Ganti ke mode gelap"/',
                $source,
                $view
            );
        }
    }

    public function test_engine_exposes_api_and_keeps_light_values_restorable(): void
    {
        $engine = file_get_contents(public_path('js/theme/theme-engine.js'));

        $this->assertStringContainsString('window.AsixTheme = {', $engine);
        foreach (['get:', 'set:', 'toggle:', 'onChange:', 'refreshCharts:'] as $method) {
            $this->assertStringContainsString($method, $engine);
        }
        // Dark mode only ever writes colour longhands, never widths or layout.
        $this->assertStringContainsString("'border-top': 'border-top-color'", $engine);
        $this->assertStringContainsString("out.push(['background-image'", $engine);
        // Chart.js options are themed by path so a light switch restores them.
        $this->assertStringContainsString('function readPath(obj, path)', $engine);
        $this->assertStringContainsString("id: 'asixTheme'", $engine);
        $this->assertStringContainsString('inlineOriginals.forEach', $engine);
        $this->assertStringContainsString("prefers-color-scheme: dark", $engine);
    }

    public function test_landing_motion_respects_reduced_motion_and_never_hides_content_without_js(): void
    {
        $landing = file_get_contents(resource_path('views/dashboard.blade.php'));
        $motion = file_get_contents(resource_path('views/dashboard/partials/landing-motion.blade.php'));

        $this->assertStringContainsString("@include('dashboard.partials.landing-motion')", $landing);
        // Content is hidden only once the script has opted in.
        $this->assertStringContainsString(':root.landing-motion-ready .landing-reveal:not(.is-revealed)', $motion);
        $this->assertStringContainsString("root.classList.add('landing-motion-ready')", $motion);
        $this->assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", $motion);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $motion);
        // Safety net reveals anything in view if an observer never fires.
        $this->assertStringContainsString("querySelectorAll('.landing-reveal:not(.is-revealed)')", $motion);
        // Digital focus cards adapt to their own panel width.
        $this->assertStringContainsString('container-name: asix-digital;', $motion);
        $this->assertStringContainsString('@container asix-digital (max-width: 900px)', $motion);
    }
}
