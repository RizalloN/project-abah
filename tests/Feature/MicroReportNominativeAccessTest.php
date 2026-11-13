<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PipelinePrewashService;
use Tests\TestCase;

class MicroReportNominativeAccessTest extends TestCase
{
    public function test_anonymous_requests_cannot_open_nominatives(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
    }

    public function test_kc_account_cannot_override_scope_with_area_or_another_kc(): void
    {
        $this->signIn('madiun');
        $this->source();
        foreach (['area6', 'magetan'] as $cabang) {
            $this->getJson($this->url(['cabang' => $cabang]))
                ->assertOk()->assertJsonPath('total', 1)
                ->assertJsonPath('data.0.cabang', 'KC Madiun');
        }
        $this->getJson($this->url(['cabang' => 'area6', 'branch' => '0049']))->assertForbidden();
        $this->getJson($this->url(['level' => 'unit', 'unit' => '88']))
            ->assertOk()->assertJsonPath('total', 0);
    }

    public function test_area_account_kc_filter_applies_to_all_returned_rows(): void
    {
        $this->signIn('area6');
        $this->source();
        $this->getJson($this->url(['cabang' => 'magetan']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.cabang', 'KC Magetan');
        $this->getJson($this->url(['cabang' => 'madiun', 'branch' => '0049']))->assertForbidden();
    }

    public function test_forged_report_invalid_kc_and_missing_group_identifiers_are_rejected(): void
    {
        $this->signIn('area6');
        foreach ([['report' => 'forged'], ['cabang' => 'unknown'], ['branch' => 'UNKNOWN'], ['level' => 'unit'], ['level' => 'mbm'], ['metric' => 'ksbk'], ['periode' => '2026-02-30'], ['page' => 0]] as $invalid) {
            $this->getJson($this->url($invalid))->assertUnprocessable();
        }
    }

    private function signIn(string $scope): void
    {
        $user = new User(['name' => 'TEST', 'pn' => '99999999', 'role' => 'user', 'branch_scope' => $scope]);
        $user->id = 12345;
        $this->actingAs($user);
    }

    private function url(array $overrides = []): string
    {
        return route('dashboard.micro-report-nominatives', [...['report' => 'prewash', 'periode' => '2026-11-10'], ...$overrides]);
    }

    private function source(): void
    {
        $this->app->instance(PipelinePrewashService::class, new class
        {
            public function nominativeRows(string $period, ?array $scope): array
            {
                $rows = [];
                foreach ([['001', 'KC MADIUN', '77'], ['002', 'KC MAGETAN', '88']] as [$id, $branch, $unit]) {
                    $rows[] = [
                        'uniqueid_namareport' => $id, 'nama_kanca' => $branch, 'branch' => $unit,
                        'nama_unit' => 'UNIT TEST', 'cif' => '000123', 'plafond' => '100.25',
                        'plafond_real' => '0.00', 'status' => 'Belum', 'kunjungan' => 'NOT',
                    ];
                }

                return ['meta' => ['available' => true, 'year' => 2026], 'rows' => $rows];
            }
        });
    }
}
