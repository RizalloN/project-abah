<?php

namespace Tests\Unit;

use App\Support\DashboardHarianSnapshotService;
use Tests\TestCase;

class DashboardHarianSnapshotServiceTest extends TestCase
{
    public function test_npl_rka_metric_definitions_follow_latest_mapping(): void
    {
        $service = new DashboardHarianSnapshotService();

        $reflection = new \ReflectionMethod($service, 'dashboardRkaMetricDefinitions');
        $reflection->setAccessible(true);

        $definitions = $reflection->invoke($service);

        $this->assertSame(['NPL % Total', 'DPK % Total'], $definitions['total_npl_pct_non_commercial']['mata_anggaran']);
        $this->assertSame(['NPL Rp Kecil Non Cash Collateral', 'DPK Rp Kecil Non Cash Collateral'], $definitions['kecil_non_cashcoll_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Kecil Cash Collateral', 'DPK Rp Kecil Cash Collateral'], $definitions['cashcoll_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Medium', 'DPK Rp Medium'], $definitions['medium_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Briguna', 'DPK Rp Briguna'], $definitions['briguna_konsumer_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KPR', 'DPK Rp KPR'], $definitions['kpr_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KKB', 'DPK Rp KKB'], $definitions['kkb_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Mikro', 'DPK Rp Mikro'], $definitions['micro_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Briguna Mikro', 'DPK Rp Briguna Mikro'], $definitions['briguna_mikro_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Kupedes Komersial', 'DPK Rp Kupedes Komersial'], $definitions['kupedes_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KUR Mikro', 'DPK Rp KUR Mikro'], $definitions['kur_mikro_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KUR Kecil', 'DPK Rp KUR Kecil'], $definitions['kur_kecil_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KPP', 'DPK Rp KPP'], $definitions['kur_kpp_npl']['mata_anggaran']);
    }

    public function test_finalize_rka_metrics_keeps_raw_total_os_value(): void
    {
        $service = new DashboardHarianSnapshotService();

        $reflection = new \ReflectionMethod($service, 'finalizeRkaMetrics');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($service, [
            'total_simpanan' => 2000.0,
            'total_os' => 12345.0,
            'total_sml_pct_non_commercial' => 12.5,
            'total_npl_pct_non_commercial' => 3.5,
            'kecil_non_cashcoll_os' => 100.0,
            'cashcoll_os' => 50.0,
            'medium_os' => 25.0,
            'briguna_konsumer_os' => 10.0,
            'kpr_os' => 5.0,
            'kkb_os' => 5.0,
            'micro_os' => 20.0,
            'giro_ritel' => 300.0,
            'tabungan_ritel' => 200.0,
            'giro_mikro' => 180.0,
            'tabungan_mikro' => 120.0,
            'kecil_non_cashcoll_npl' => 11.0,
            'cashcoll_npl' => 4.0,
            'medium_npl' => 100.0,
            'briguna_konsumer_npl' => 7.0,
            'kpr_npl' => 3.0,
            'kkb_npl' => 2.0,
            'micro_npl' => 20.0,
        ]);

        $this->assertSame(12345.0, $result['total_os']);
        $this->assertSame(215.0, $result['total_os_non_commercial']);
        $this->assertSame(12.5, $result['total_sml_pct_non_commercial']);
        $this->assertSame(3.5, $result['total_npl_pct_non_commercial']);
        $this->assertSame(15.0, $result['sme_npl']);
        $this->assertSame(12.0, $result['consumer_npl']);
        $this->assertSame(47.0, $result['total_npl_abs_non_commercial']);
        $this->assertSame(500.0, $result['casa_ritel']);
        $this->assertSame(300.0, $result['casa_mikro']);
        $this->assertSame(800.0, $result['total_casa']);
        $this->assertSame(40.0, $result['casa_pct']);
    }
}
