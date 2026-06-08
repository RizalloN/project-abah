<?php

namespace Tests\Unit;

use App\Services\Reports\BusinessClusterReportService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BusinessClusterReportServiceTest extends TestCase
{
    public function test_counts_bri_status_by_kategori_and_keeps_status_in_details(): void
    {
        $service = new BusinessClusterReportService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('countKategoriFromCsv');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'KC Madiun', implode("\n", [
            'Nama Usaha,Alamat Lengkap,Kota/Kabupaten,Kategori,Sudah/Blm BRI',
            'Toko A,Jl A,Madiun,Kuliner,Sudah',
            'Toko B,Jl B,Madiun,Kuliner,Blm',
            'Toko C,Jl C,Madiun,Fashion,Belum BRI',
        ]));

        $rows = $result['rows']->keyBy('kategori');

        $this->assertSame(2, $rows['Kuliner']['jumlah']);
        $this->assertSame(1, $rows['Kuliner']['sudah_bri']);
        $this->assertSame(1, $rows['Kuliner']['belum_bri']);
        $this->assertSame('Sudah di BRI', $rows['Kuliner']['details'][0]['status_bri']);
        $this->assertSame('sudah', $rows['Kuliner']['details'][0]['status_bri_key']);

        $this->assertSame(1, $rows['Fashion']['jumlah']);
        $this->assertSame(0, $rows['Fashion']['sudah_bri']);
        $this->assertSame(1, $rows['Fashion']['belum_bri']);
        $this->assertSame('belum', $rows['Fashion']['details'][0]['status_bri_key']);
    }
}
