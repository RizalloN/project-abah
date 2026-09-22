<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerSsaSourceHeaderGuardTest extends TestCase
{
    public function test_ssa_header_guard_defers_renamed_known_width_to_value_validation_processor(): void
    {
        $controller = app(ImportExcelController::class);
        $method = new ReflectionMethod(ImportExcelController::class, 'isDetectedHeaderValidForTable');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, array_fill(0, 7, 'Nama Baru'), 'ssa_simpanan'));
        $this->assertFalse($method->invoke($controller, array_fill(0, 13, 'Nama Baru'), 'ssa_pinjaman'));
        $this->assertTrue($method->invoke($controller, array_fill(0, 14, 'Nama Baru'), 'ssa_pinjaman'));
        $this->assertFalse($method->invoke($controller, array_fill(0, 12, 'Nama Baru'), 'ssa_pinjaman'));
    }

    public function test_ssa_source_contract_requires_segmen_lama_for_pinjaman_export(): void
    {
        $controller = app(ImportExcelController::class);
        $method = new ReflectionMethod(ImportExcelController::class, 'assertSsaSourceHeaderContract');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('segmen_lama');
        $method->invoke($controller, 'ssa_pinjaman', [
            'Month, Day, Year of Periode', 'Nama Cabang', 'Nama Uker', 'Produk',
            'Produk_Dashboard', 'Segmen', 'SEGMEN_2025', 'Segmen_Dashboard',
            'Kolektabilitas One Obligor', 'Flag Restruk', 'Baki Debet',
            'Jumlah Debitur Aktif', 'Jumlah Rekening Aktif',
        ]);
    }
}
