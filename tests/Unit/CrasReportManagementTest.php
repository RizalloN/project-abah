<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Support\ManagedReportManagementService;
use ReflectionClass;
use Tests\TestCase;

class CrasReportManagementTest extends TestCase
{
    public function test_cras_management_uses_shadow_period_and_exact_branch_column(): void
    {
        $service = app(ManagedReportManagementService::class);

        $this->assertSame(
            ['cras_periode', 'ket_kanca'],
            $service->resolveManagementScopeColumns('cras', [
                'cras_uuid',
                'cras_periode',
                'month_day_year_of_posisi',
                'ket_kanca',
            ])
        );
    }

    public function test_cras_delete_uses_the_composite_scope_index_and_uuid(): void
    {
        $reflection = new ReflectionClass(ImportIndexController::class);
        $hints = $reflection->getConstant('DELETE_INDEX_HINTS');

        $this->assertSame([
            'index' => 'idx_cras_period_branch_uuid',
            'period' => 'cras_periode',
            'kanca' => 'ket_kanca',
            'identity' => 'cras_uuid',
            'chunk_size' => 50000,
        ], $hints['cras']);
    }
}
