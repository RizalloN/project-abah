<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaNonPtpReportController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class KinerjaNonPtpReportControllerTest extends TestCase
{
    public function test_earliest_due_sql_matches_excel_min_due_date_formula(): void
    {
        $controller = new KinerjaNonPtpReportController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('earliestDueSql');
        $method->setAccessible(true);

        $sql = $method->invoke($controller, 'd', '2026-04-30');

        $this->assertStringContainsString('d.next_pmt_date', $sql);
        $this->assertStringContainsString('d.next_pmt_int_date', $sql);
        $this->assertStringContainsString('WHEN', $sql);
        $this->assertStringNotContainsString('DATE_ADD', $sql);
        $this->assertStringNotContainsString('MAKEDATE', $sql);
    }

    public function test_excel_status_sql_excludes_realisasi_non_lancar_and_non_bulanan_before_ptp_status(): void
    {
        $controller = new KinerjaNonPtpReportController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('excelStatusSql');
        $method->setAccessible(true);

        $sql = $method->invoke($controller, 'd', '2026-04-30');

        $this->assertStringContainsString("'2026-04-01'", $sql);
        $this->assertStringContainsString("'2026-04-30'", $sql);
        $this->assertStringContainsString("'REALISASI BULAN INI'", $sql);
        $this->assertStringContainsString("'NON LANCAR'", $sql);
        $this->assertStringContainsString("'NON BULANAN'", $sql);
        $this->assertStringContainsString("'NON PTP'", $sql);
        $this->assertStringContainsString("'PTP'", $sql);
    }

    public function test_repayment_pattern_uses_principal_and_interest_frequency_for_bulanan(): void
    {
        $controller = new KinerjaNonPtpReportController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('repaymentPatternSql');
        $method->setAccessible(true);

        $sql = $method->invoke($controller, 'd');

        $this->assertStringContainsString('d.freq_payment', $sql);
        $this->assertStringContainsString('d.freq_int_payment', $sql);
        $this->assertStringContainsString("THEN 'BULANAN'", $sql);
        $this->assertStringContainsString("THEN '1 X ANGSURAN'", $sql);
        $this->assertStringNotContainsString("freq_payment, 0) AS UNSIGNED) = 1 THEN 'BULANAN'", $sql);
    }
}
