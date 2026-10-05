<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use ReflectionMethod;
use Tests\TestCase;

class LandingMbmDateInteractionTest extends TestCase
{
    public function test_selected_date_keeps_micro_active_and_scopes_the_fragment_url(): void
    {
        $request = Request::create('/dashboard', 'GET', ['periode' => '2026-09-30', 'landing_scope' => 'micro']);
        $this->app->instance('request', $request);
        Auth::setUser((new User())->forceFill(['name' => 'Test User', 'role' => 'admin']));
        $controller = app(DashboardSimpananController::class);
        $dashboard = (new ReflectionMethod($controller, 'emptyDashboard'))->invoke($controller);
        $dashboard['area6_portfolio']['default_scope'] = 'sme';
        $dashboard['area6_portfolio']['available'] = true;
        $dashboard['area6_portfolio']['scopes'] = ['sme' => [], 'micro' => []];
        $dashboard['area6_portfolio']['ranking_modes'] = ['sme' => [], 'micro' => []];
        $html = view('dashboard', [
            'dashboard' => $dashboard,
            'periods' => collect(['2026-10-02', '2026-09-30']),
            'selectedPeriod' => '2026-09-30',
            'selectedLandingBranch' => 'area6',
            'landingBranchOptions' => [],
            'landingBranchLabel' => 'Area 6',
            'landingBranchLocked' => false,
        ])->render();

        $this->assertSame(1, preg_match('/class="area6-scope-btn active"\s+data-area6-scope="micro"/', $html), 'Micro scope must remain selected.');
        $this->assertStringContainsString('data-applied-period="2026-09-30"', $html);
        $this->assertStringContainsString('micro-performance?periode=2026-09-30', $html);
        $this->assertStringContainsString("targetUrl.searchParams.set('landing_scope', activeScope)", $html);
    }

    public function test_incomplete_identity_warning_does_not_hide_available_mbm_ranking(): void
    {
        $this->app->instance('request', Request::create('/dashboard/micro-performance', 'GET', ['periode' => '2026-09-30']));
        $html = view('dashboard.partials.micro-performance', ['microPerformance' => [
            'meta' => ['available' => true, 'period' => '2026-09-30', 'period_label' => '30 Sep 2026',
                'identity_coverage' => ['total_accounts' => 10, 'unresolved_accounts' => 2]],
            'decision_ranking' => ['available' => true, 'default_metric' => 'plafond', 'metrics' => [
                'plafond' => ['key' => 'plafond', 'label' => 'Plafon', 'top' => [
                    ['pn' => '21668', 'name' => 'MBM Uji', 'branch' => 'KC PONOROGO', 'amount' => 1000000, 'deb' => 1],
                ], 'bottom' => []],
            ]],
        ]])->render();

        $this->assertStringContainsString('data-requested-period="2026-09-30"', $html);
        $this->assertStringContainsString('data-period="2026-09-30"', $html);
        $this->assertStringContainsString('MBM Uji', $html);
        $this->assertStringContainsString('Ranking tersedia', $html);
        $this->assertStringNotContainsString('Data pemutus MBM belum tersedia', $html);
    }
}
