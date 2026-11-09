<?php

namespace Tests\Unit;

use App\Support\LandingMicroPipelineService;
use Tests\TestCase;

class LandingMicroPipelineServiceTest extends TestCase
{
    public function test_hot_prospects_rm_kur_parser_and_scoping(): void
    {
        $csv = implode("\n", [
            '"HOT PROSPEK KUR KECIL NOVEMBER 2026 NO ","KANCA ","PN ","NAMA ","BC UKER ","UKER ","TOTAL PIPELINE NOV 2026 Deb ","Rp.Juta ","PROSES PRAKARSA Deb ","Rp.Juta ","MENUNGGU PUTUSAN Deb ","Rp.Juta ","SUDAH REALISASI Deb ","Rp.Juta "',
            '"1","MADIUN","121911","Indrawan Adhitia","2109","02109--KCP DOLOPO","9","2,550","4","1,250","0","0","0","0"',
            '"2","MAGETAN","151454","Bibi Zia Respita","552","00552--KCP Barat","6","1,550","2","550","0","0","0","0"',
            '"3","NGAWI","218297","Abi Choirul Anwar","45","00045--KC Ngawi","7","2,090","2","560","1","250","0","0"',
            '"4","PONOROGO","334455","Catur Priyatnanda","110","00110--KC Ponorogo","8","2,500","2","500","0","0","1","225"',
        ]);

        $service = app(LandingMicroPipelineService::class);
        $source = LandingMicroPipelineService::HOT_PROSPECTS_SOURCE;
        $parsed = $service->parseHotProspectsCsv($csv, $source);

        $this->assertTrue($parsed['available']);
        $this->assertSame('November 2026', $parsed['period_label']);
        $this->assertCount(4, $parsed['records']);

        // Area 6 (all)
        $scopedArea = $service->scopeHotProspects($parsed, null);
        $this->assertSame(4, $scopedArea['rm_count']);
        $this->assertSame(30, data_get($scopedArea, 'summary.total.deb')); // 9 + 6 + 7 + 8
        $this->assertSame(8690.0, data_get($scopedArea, 'summary.total.amount_juta')); // 2550 + 1550 + 2090 + 2500
        $this->assertSame(10, data_get($scopedArea, 'summary.prakarsa.deb')); // 4 + 2 + 2 + 2
        $this->assertSame(2860.0, data_get($scopedArea, 'summary.prakarsa.amount_juta')); // 1250 + 550 + 560 + 500
        $this->assertSame(1, data_get($scopedArea, 'summary.putusan.deb'));
        $this->assertSame(250.0, data_get($scopedArea, 'summary.putusan.amount_juta'));
        $this->assertSame(1, data_get($scopedArea, 'summary.realisasi.deb'));
        $this->assertSame(225.0, data_get($scopedArea, 'summary.realisasi.amount_juta'));

        // KC Ponorogo
        $scopedPonorogo = $service->scopeHotProspects($parsed, ['key' => 'ponorogo', 'label' => 'KC Ponorogo']);
        $this->assertSame(1, $scopedPonorogo['rm_count']);
        $this->assertSame(8, data_get($scopedPonorogo, 'summary.total.deb'));
        $this->assertSame(2500.0, data_get($scopedPonorogo, 'summary.total.amount_juta'));
        $this->assertSame(1, data_get($scopedPonorogo, 'summary.realisasi.deb'));
        $this->assertSame(225.0, data_get($scopedPonorogo, 'summary.realisasi.amount_juta'));

        // KC Magetan
        $scopedMagetan = $service->scopeHotProspects($parsed, ['key' => 'magetan', 'label' => 'KC Magetan']);
        $this->assertSame(1, $scopedMagetan['rm_count']);
        $this->assertSame(6, data_get($scopedMagetan, 'summary.total.deb'));
        $this->assertSame(1550.0, data_get($scopedMagetan, 'summary.total.amount_juta'));
        $this->assertSame(0, data_get($scopedMagetan, 'summary.realisasi.deb'));
    }
}
