<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardHarianController;
use App\Models\User;
use App\Support\DashboardHarianSnapshotService;
use App\Support\KeragaanPdfGeography;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use Tests\TestCase;

class KeragaanPdfExportTest extends TestCase
{
    private const BRANCHES = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

    public function test_export_requires_authentication(): void
    {
        $this->get($this->url())->assertRedirect(route('login'));
    }

    public function test_area_export_uses_all_four_branches_and_renders_branding_and_geography(): void
    {
        $this->expectReport(self::BRANCHES, true);

        $response = $this->actingAs($this->user())->get($this->url());

        $response->assertOk()
            ->assertViewIs('report.dashboard-harian-keragaan-pdf')
            ->assertSee('PT Bank Rakyat Indonesia (PERSERO) Tbk')
            ->assertSee('Performance Report Area 6 Madiun - Region 13 Malang')
            ->assertSee('Performance Report data 30 Sep 26')
            ->assertSeeInOrder(['alt="Danantara"', 'alt="BRI"'], false)
            ->assertSee('aria-label="Peta fixture"', false)
            ->assertSee('Ringkasan KPI Area 6 Madiun')
            ->assertDontSee('Daftar wilayah layanan dan unit kerja')
            ->assertDontSee('Nomor pada peta');
        foreach (self::BRANCHES as $branch) {
            $response->assertSee($branch.' Unit Fixture');
        }
        $response->assertViewHas('report', fn (array $report): bool => $report['area_scope'] === true
            && count($report['geography']['branches']) === 4);
    }

    public function test_area_user_can_export_one_branch_at_unit_level(): void
    {
        $this->expectReport(['KC Magetan'], false);

        $this->actingAs($this->user())->get($this->url(['kanca' => 'KC Magetan']))
            ->assertOk()
            ->assertSee('Performance Report KC Magetan - Region 13 Malang')
            ->assertSee('KC Magetan Unit Fixture')
            ->assertDontSee('KC Madiun')
            ->assertDontSee('KC Ngawi')
            ->assertDontSee('KC Ponorogo')
            ->assertViewHas('report', fn (array $report): bool => $report['area_scope'] === false);
    }

    public function test_branch_user_cannot_expand_export_with_area_or_other_branch_input(): void
    {
        foreach (['area6', 'all', 'KC Ngawi'] as $requestedScope) {
            $this->expectReport(['KC Madiun'], false);

            $this->actingAs($this->user('madiun'))->get($this->url(['kanca' => $requestedScope]))
                ->assertOk()
                ->assertSee('Performance Report KC Madiun - Region 13 Malang')
                ->assertSee('KC Madiun Unit Fixture')
                ->assertDontSee('KC Magetan')
                ->assertDontSee('KC Ngawi')
                ->assertDontSee('KC Ponorogo');
        }
    }

    public function test_invalid_branch_and_periods_are_rejected_before_loading_data(): void
    {
        $this->mock(DashboardHarianSnapshotService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('buildKeragaanPdfPayload');
        });
        $this->mock(KeragaanPdfGeography::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('build');
        });

        foreach ([['kanca' => 'KC Surabaya'], ['posisi_terakhir' => '2026-02-30'], ['posisi_rka' => '2026-13']] as $invalid) {
            $this->actingAs($this->user())->getJson($this->url($invalid))
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
    }

    public function test_area_selection_resolves_all_branches_without_a_stale_unit_filter(): void
    {
        $method = new \ReflectionMethod(DashboardHarianController::class, 'resolveKeragaanUkerFilters');
        $request = Request::create('/', 'GET', ['kanca' => 'area6', 'unit_kerja' => 'Unit Lama']);

        [$branches, $unit] = $method->invoke(app(DashboardHarianController::class), $request);
        $this->assertEqualsCanonicalizing(self::BRANCHES, $branches);
        $this->assertNull($unit);
    }

    private function expectReport(array $branches, bool $area): void
    {
        $this->mock(DashboardHarianSnapshotService::class, function (MockInterface $mock) use ($branches, $area): void {
            $mock->shouldReceive('buildKeragaanPdfPayload')->once()
                ->with('2026-09-30', '2026-09', $branches, $area)
                ->andReturn(['period' => '2026-09-30', 'sections' => [], 'active_offices' => [['unit_key' => 'active-fixture']]]);
        });
        $this->mock(KeragaanPdfGeography::class, function (MockInterface $mock) use ($branches): void {
            $mock->shouldReceive('build')->once()->with($branches, [['unit_key' => 'active-fixture']])->andReturn([
                'ready' => true,
                'svg' => '<svg aria-label="Peta fixture"><path d="M0 0 L10 10 Z"/></svg>',
                'maps' => array_map(fn ($branch) => ['label' => $branch, 'svg' => '<svg aria-label="Peta fixture"><text>'.$branch.' Unit Fixture</text></svg>'], $branches),
                'branches' => array_map(fn ($branch) => ['label' => $branch, 'color' => '#78b8de', 'unit_count' => 1], $branches),
                'districts' => array_map(fn ($branch) => [
                    'number' => 1, 'name' => 'Wilayah Fixture', 'branch_label' => $branch,
                    'units' => [['name' => $branch.' Unit Fixture', 'code' => '99999']],
                ], $branches),
                'unmapped_units' => [], 'disclosure' => 'Wilayah layanan unit kerja.', 'source' => 'Fixture',
            ]);
        });
    }

    private function user(string $scope = 'area6'): User
    {
        return new User(['pn' => 'pdf-test', 'name' => 'PDF Test', 'role' => 'user', 'branch_scope' => $scope]);
    }

    private function url(array $query = []): string
    {
        return route('dashboard.harian.keragaan-uker.export-pdf', array_replace([
            'kanca' => 'area6', 'posisi_terakhir' => '2026-09-30', 'posisi_rka' => '2026-09',
        ], $query));
    }
}
