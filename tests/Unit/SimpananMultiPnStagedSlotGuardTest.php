<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\ImportDuplicateGuardService;
use App\Services\Import\MySqlBulkLoadService;
use Mockery;
use Tests\TestCase;

class SimpananMultiPnStagedSlotGuardTest extends TestCase
{
    private const HEADERS = ['Posisi', 'Kantor Cabang', 'CIFNO', 'No Rekening', 'Jenis Simpanan', 'Saldo IDR'];

    public function test_selected_rows_hold_only_their_exact_slots_until_load_finishes(): void
    {
        $this->runImport([
            ['30-09-2026', 'KC A', 'C1', '001', 'TABUNGAN', '10'],
            ['31-08-2026', 'KC B', 'C2', '002', 'TABUNGAN', '20'],
            ['30-09-2026', 'KC A', 'C3', '003', 'TABUNGAN', '30'],
        ], [], [
            ['posisi' => '2026-08-31', 'kantor_cabang' => 'KC B'],
            ['posisi' => '2026-09-30', 'kantor_cabang' => 'KC A'],
        ], 3);
    }

    public function test_filtered_out_branch_is_not_locked_or_checked(): void
    {
        $this->runImport([
            ['30-09-2026', 'KC A', 'C1', '001', 'TABUNGAN', '10'],
            ['30-09-2026', 'KC B', 'C2', '002', 'TABUNGAN', '20'],
        ], [1 => ['KC A']], [
            ['posisi' => '2026-09-30', 'kantor_cabang' => 'KC A'],
        ], 1);
    }

    public function test_occupied_slot_blocks_load_and_releases_all_acquired_locks(): void
    {
        $this->runImport([
            ['30-09-2026', 'KC A', 'C1', '001', 'TABUNGAN', '10'],
            ['30-09-2026', 'KC B', 'C2', '002', 'TABUNGAN', '20'],
        ], [], [
            ['posisi' => '2026-09-30', 'kantor_cabang' => 'KC A'],
            ['posisi' => '2026-09-30', 'kantor_cabang' => 'KC B'],
        ], 2, 'occupied');
    }

    public function test_load_failure_releases_slot_locks(): void
    {
        $this->runImport([
            ['30-09-2026', 'KC A', 'C1', '001', 'TABUNGAN', '10'],
        ], [], [
            ['posisi' => '2026-09-30', 'kantor_cabang' => 'KC A'],
        ], 1, 'load failed');
    }

    private function runImport(array $rows, array $filters, array $expectedSlots, int $expectedRows, ?string $failure = null): void
    {
        $held = [];
        $released = [];
        $checked = [];
        $guard = Mockery::mock(ImportDuplicateGuardService::class);
        $guard->shouldReceive('acquireAdvisoryLock')->times(count($expectedSlots))
            ->andReturnUsing(function (string $table, array $slot) use (&$held): string {
                $this->assertSame('simpanan_multipn', $table);
                $name = json_encode($slot);
                $held[$name] = $slot;
                return $name;
            });
        $guard->shouldReceive('assertSlotEmpty')->times(count($expectedSlots))
            ->andReturnUsing(function (string $table, array $slot) use (&$checked, $failure, $expectedSlots): void {
                $checked[] = $slot;
                if ($failure === 'occupied' && count($checked) === count($expectedSlots)) {
                    throw new \RuntimeException('occupied');
                }
            });
        $guard->shouldReceive('releaseAdvisoryLock')->times(count($expectedSlots))
            ->andReturnUsing(function (?string $name) use (&$held, &$released): void {
                $released[] = $held[$name];
                unset($held[$name]);
            });
        $this->app->instance(ImportDuplicateGuardService::class, $guard);

        $bulk = Mockery::mock(MySqlBulkLoadService::class);
        if ($failure === 'occupied') {
            $bulk->shouldNotReceive('loadCsvIntoMysql');
        } else {
            $bulk->shouldReceive('supportsNativeBulkLoad')->once()->andReturnTrue();
            $bulk->shouldReceive('loadCsvIntoMysql')->once()
                ->andReturnUsing(function (string $path) use (&$held, $expectedSlots, $expectedRows, $failure): int {
                    $this->assertSame($expectedSlots, array_values($held));
                    $handle = fopen($path, 'rb');
                    $count = 0;
                    while (fgetcsv($handle) !== false) {
                        $count++;
                    }
                    fclose($handle);
                    $this->assertSame($expectedRows, $count);
                    if ($failure !== null) {
                        throw new \RuntimeException($failure);
                    }
                    return $count;
                });
        }
        $this->app->instance(MySqlBulkLoadService::class, $bulk);

        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return ['uniqueid_SMPN', 'posisi', 'kantor_cabang', 'CIFNO', 'no_rekening', 'jenis_simpanan', 'saldo_idr', 'created_at', 'updated_at'];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return ['saldo_idr' => ['scale' => 2]];
            }

            public function importForTest(string $path, array $headers, array $filters, int $rows): bool
            {
                return $this->processStagedCsvStream(static function (): void {}, $path, 'simpanan_multipn', $filters, $headers, 0, $rows, ',', true);
            }
        };
        $path = tempnam(sys_get_temp_dir(), 'multipn_slot_');
        $handle = fopen($path, 'wb');
        fputcsv($handle, self::HEADERS);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        try {
            try {
                $this->assertTrue($controller->importForTest($path, self::HEADERS, $filters, count($rows)));
                $this->assertNull($failure, 'Expected import failure was not raised.');
            } catch (\RuntimeException $e) {
                $this->assertNotNull($failure);
                $this->assertSame($failure, $e->getMessage());
            }
            $this->assertSame($expectedSlots, $checked);
            $this->assertSame([], $held);
            $this->assertSame(array_reverse($expectedSlots), $released);
        } finally {
            unlink($path);
        }
    }
}
