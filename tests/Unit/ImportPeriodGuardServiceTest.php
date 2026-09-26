<?php

namespace Tests\Unit;

use App\Services\Import\ImportPeriodGuardService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImportPeriodGuardServiceTest extends TestCase
{
    #[DataProvider('activePeriodicReportProvider')]
    public function test_every_active_periodic_report_has_an_explicit_guard_policy(
        string $tableName,
        string $expectedColumn,
        string $expectedType
    ): void {
        $this->assertSame(
            ['column' => $expectedColumn, 'type' => $expectedType],
            (new ImportPeriodGuardService())->policyFor($tableName)
        );
    }

    public static function activePeriodicReportProvider(): array
    {
        return [
            'daily loan' => ['daily_loan_dinamis', 'periode', 'date'],
            'lw321pn' => ['lw321pn', 'periode', 'date'],
            'lw325 ph' => ['lw325_ph', 'periode', 'date'],
            'simpanan multipn' => ['simpanan_multipn', 'posisi', 'date'],
            'hourly dpk' => ['hourly_dpk', 'posisi', 'date'],
            'ssa simpanan' => ['ssa_simpanan', 'Month_Day_Year_of_Posisi', 'date'],
            'ssa pinjaman' => ['ssa_pinjaman', 'month_day_year_of_periode', 'date'],
            'ssa almafacts' => ['ssa_almafacts', 'month_day_year_of_posisi', 'date'],
            'gi405 recovery' => ['gi405_recovery', 'periode', 'date'],
            'cognos ph' => ['cognos_ph', 'periode', 'date'],
            'cognos recovery' => ['cognos_recovery', 'periode', 'date'],
            'dly kap resegmentasi' => ['dly_kap_resegmentasi', 'periode', 'date'],
            'l1133' => ['l1133', 'periode', 'date'],
            'merchant detail' => ['jumlah_merchant_detail', 'POSISI', 'date'],
            'merchant qris detail' => ['jumlah_merchant_qris_detail', 'POSISI', 'date'],
            'sv merchant' => ['sv_merchant', 'POSISI', 'date'],
            'brimo rpt' => ['user_brimo_rpt_v2', 'posisi', 'date'],
            'user brimo fin' => ['user_brimo_fin', 'posisi', 'date'],
            'brimo fin' => ['brimo_fin', 'posisi', 'date'],
            'brimo fin all' => ['brimo_fin_all', 'posisi', 'date'],
            'performance pis' => ['performance_pis_per_produk', 'posisi', 'date'],
            'input rekanan' => ['input_rekanan', 'periode', 'date'],
            'bod boc' => ['bod_boc', 'periode', 'date'],
            'cras' => ['cras', 'cras_periode', 'date'],
            'casa brilink web' => ['casa_brilink_web', 'periode', 'date'],
            'casa brilink edc' => ['casa_brilink_edc', 'periode', 'date'],
            'ibbisniz corp' => ['ibbisniz_corp', 'periode', 'date'],
            'usak ibbiz uker' => ['usak_ibbiz_uker', 'periode', 'date'],
            'rka' => ['rka', 'tahun', 'year'],
        ];
    }

    #[DataProvider('activeExemptReportProvider')]
    public function test_active_non_daily_reports_are_explicitly_exempt(string $tableName): void
    {
        $this->assertNull((new ImportPeriodGuardService())->policyFor($tableName));
    }

    public static function activeExemptReportProvider(): array
    {
        return [
            'brilink summary text month' => ['brilink_web_laporan_summary_transaksi_brilink_web'],
            'brihc' => ['brihc'],
            'wilayah mbm' => ['wilayah_mbm'],
            'business cluster' => ['business_cluster'],
            'brihc pemasar' => ['brihc_pemasar'],
        ];
    }

    public function test_merchant_posisi_cannot_be_replaced_by_monthly_periode_column(): void
    {
        $guard = new ImportPeriodGuardService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('kolom periode wajib `POSISI` tidak dipetakan');

        $guard->assertMappedColumns('jumlah_merchant_detail', ['PERIODE', 'TID', 'NAMA_KANCA']);
    }

    public function test_merchant_row_requires_valid_posisi_even_when_periode_is_present(): void
    {
        $guard = new ImportPeriodGuardService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nilai periode `POSISI` kosong pada baris 17');

        $guard->assertRow('jumlah_merchant_qris_detail', [
            'PERIODE' => '2026-09',
            'POSISI' => null,
            'MBDESC' => 'KC Madiun',
        ], 17);
    }

    public function test_valid_date_accepts_case_insensitive_row_key(): void
    {
        (new ImportPeriodGuardService())->assertRow('jumlah_merchant_detail', [
            'posisi' => '24 Sep 26',
        ], 2);

        $this->addToAssertionCount(1);
    }

    #[DataProvider('blankPeriodProvider')]
    public function test_blank_period_values_are_rejected(mixed $value): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nilai periode `POSISI` kosong');

        (new ImportPeriodGuardService())->assertRow('jumlah_merchant_detail', [
            'POSISI' => $value,
        ]);
    }

    public static function blankPeriodProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'whitespace' => [" \t "],
            'mysql null marker' => ['\\N'],
        ];
    }

    #[DataProvider('invalidDateProvider')]
    public function test_invalid_dates_are_rejected(string $value): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nilai periode `POSISI` tidak valid');

        (new ImportPeriodGuardService())->assertRow('jumlah_merchant_detail', [
            'POSISI' => $value,
        ]);
    }

    public static function invalidDateProvider(): array
    {
        return [
            'zero date' => ['0000-00-00'],
            'impossible date' => ['2026-02-30'],
            'numeric month only' => ['2026-09'],
            'text month only' => ['September 2026'],
        ];
    }

    public function test_year_policy_accepts_valid_year_and_rejects_invalid_year(): void
    {
        $guard = new ImportPeriodGuardService();
        $guard->assertRow('rka', ['TAHUN' => '2026']);
        $this->addToAssertionCount(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nilai periode `tahun` tidak valid');
        $guard->assertRow('rka', ['tahun' => '26']);
    }

    public function test_csv_guard_rejects_an_invalid_period_in_the_middle_before_load(): void
    {
        $csvPath = $this->writeTemporaryCsv([
            ['2026-09-22', 'TID-001'],
            ['2026-02-30', 'TID-002'],
            ['2026-09-24', 'TID-003'],
        ]);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('pada baris 2');

            (new ImportPeriodGuardService())->assertCsvRows(
                $csvPath,
                'jumlah_merchant_detail',
                ['POSISI', 'TID']
            );
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_csv_guard_accepts_all_valid_rows_and_skips_whole_blank_row(): void
    {
        $csvPath = $this->writeTemporaryCsv([
            ['2026-09-22', 'TID-001'],
            ['', ''],
            ['24 Sep 26', 'TID-003'],
        ]);

        try {
            (new ImportPeriodGuardService())->assertCsvRows(
                $csvPath,
                'jumlah_merchant_detail',
                ['posisi', 'TID']
            );
            $this->addToAssertionCount(1);
        } finally {
            @unlink($csvPath);
        }
    }

    private function writeTemporaryCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'period_guard_');
        if ($path === false) {
            $this->fail('Tidak dapat membuat file CSV sementara.');
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            @unlink($path);
            $this->fail('Tidak dapat membuka file CSV sementara.');
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }
}
