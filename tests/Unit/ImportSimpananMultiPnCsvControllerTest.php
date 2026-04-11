<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportSimpananMultiPnCsvController;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class ImportSimpananMultiPnCsvControllerTest extends TestCase
{
    public function test_direct_csv_load_plan_keeps_posisi_assignment_in_set_clause(): void
    {
        $controller = new ImportSimpananMultiPnCsvController();

        Schema::shouldReceive('getColumnListing')
            ->once()
            ->with('simpanan_multipn')
            ->andReturn([
                'id',
                'posisi',
                'cifno',
                'no_rekening',
                'status',
                'jenis_simpanan',
                'saldo_idr',
                'created_at',
                'updated_at',
            ]);

        $csvPath = storage_path('framework/testing/simpanan_fast_import_test.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'No;Posisi;CIFNO;No Rekening;Status;Jenis Simpanan;Saldo IDR',
            '1;04-04-2026;PQ32242;6,36001E+14;9;TABUNGAN;500',
        ]));

        try {
            $plan = $this->invokeMethod($controller, 'buildDirectCsvLoadPlan', [
                $csvPath,
                ['No', 'Posisi', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR'],
                [0, 1, 2, 3, 4, 5, 6],
            ]);
        } finally {
            @unlink($csvPath);
        }

        $posisiClause = collect($plan['set_clauses'] ?? [])->first(fn (string $clause) => str_contains($clause, '`posisi`'));

        $this->assertNotNull($posisiClause);
        $this->assertStringContainsString('`posisi` = CASE', $posisiClause);
    }

    private function invokeMethod(object $target, string $method, array $arguments)
    {
        $reflection = new ReflectionClass($target);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invokeArgs($target, $arguments);
    }
}
