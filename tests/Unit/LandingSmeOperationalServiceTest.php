<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmReportController;
use App\Support\LandingSmeOperationalService;
use App\Support\UserBranchScope;
use Mockery;
use Tests\TestCase;

class LandingSmeOperationalServiceTest extends TestCase
{
    public function test_parsers_keep_only_area_6_kc_and_requested_kcp_units(): void
    {
        $service = $this->service();
        $hotHeader = [
            'KODE KANCA', 'KANCA', 'KODE UKER', 'UKER', 'NAMA RM',
            'BELUM OTS PEMUTUS A', '', 'BELUM OTS PEMUTUS B', '',
            'ANALISA RM MAK', '', 'REVIEW SBM', '', 'MENUNGGU PUTUSAN', '',
            'SUDAH DIPUTUS', '', 'REALISASI AGUSTUS', '', 'BATAL', '',
        ];
        $hot = $service->parseHotProspectCsv($this->csv([
            $hotHeader,
            ['0045', 'KC Madiun', '00045', 'KC Madiun', 'RM A', 1, 100, 2, 250, 3, 300, 4, 400, 5, 500, 6, 600, 7, 700, 8, 800],
            ['0045', 'KC Madiun', '00552', 'KCP Caruban', 'RM B', 2, 200, 1, 125, 1, 150, 1, 175, 1, 200, 1, 225, 1, 250, 1, 275],
            ['0099', 'KC Lain', '00999', 'UKER LAIN', 'RM C', 9, 900, 9, 900, 9, 900, 9, 900, 9, 900, 9, 900, 9, 900, 9, 900],
        ]));

        $this->assertCount(2, $hot['records']);
        $this->assertSame('KC MADIUN', $hot['records'][1]['branch']);
        $this->assertSame(['deb' => 3, 'amount_juta' => 350.0], $hot['records'][0]['statuses']['belum_ots']);
        $this->assertSame(['deb' => 4, 'amount_juta' => 400.0], $hot['records'][0]['statuses']['verifikasi_adk']);

        $rtlHeader = ['AREA HEAD', 'KODE KANCA', 'KODE UKER', 'UKER'];
        $rtlData = ['AREA 6', '0045', '02109', 'KCP Dolopo'];
        for ($vendor = 1; $vendor <= 15; $vendor++) {
            array_push(
                $rtlHeader,
                'TOTAL PIPELINE '.$vendor,
                'SUDAH TARIK SLIK',
                'BELUM TARIK SLIK',
                'SLIK HIJAU',
                'SLIK MERAH',
                'OTS Sudah OTS',
                'Belum OTS',
                'Alamat Tidak Ditemukan',
                'Kondisi Usaha Layak',
                'Tidak Layak',
                'PEMBIAYAAN Berminat',
                'Tidak Berminat'
            );
            array_push($rtlData, $vendor, 0, 0, 0, 0, 100 + $vendor, 0, 0, 0, 0, 200 + $vendor, 0);
        }

        $rtl = $service->parseRtlPipelineCsv($this->csv([$rtlHeader, $rtlData]));

        $this->assertCount(1, $rtl['records']);
        $this->assertSame(
            ['pipeline' => 1, 'ots' => 101, 'interested' => 201],
            $rtl['records'][0]['vendors']['petrokimia']
        );
        $this->assertSame(
            ['pipeline' => 6, 'ots' => 106, 'interested' => 206],
            $rtl['records'][0]['vendors']['hipmi']
        );
        $this->assertSame(
            ['pipeline' => 14, 'ots' => 114, 'interested' => 214],
            $rtl['records'][0]['vendors']['pupuk_indonesia']
        );
        $this->assertSame(
            ['pipeline' => 15, 'ots' => 115, 'interested' => 215],
            $rtl['records'][0]['vendors']['kios_pupuk_lengkap']
        );
    }

    public function test_extension_and_restructuring_parsers_preserve_status_pairs(): void
    {
        $service = $this->service();
        $extensionHeader = [
            'UKER', 'KODE UKER', 'KANCA INDUK', '', 'TOTAL NOMINATIF', '', '', '',
            '', '', '', '', '', '', '', '', '', '', '', '', '', '',
        ];
        $extensionGroups = [
            '', '', '', '', '', '', 'SUDAH ISI', 'BELUM ISI',
            'ANALISA RM', '', 'MENUNGGU PUTUSAN', '', 'SUDAH DIPUTUS', '',
            'SUDAH DIPERPANJANG', '', 'LUNAS', '', 'BELUM TL', '', 'TIDAK DIPERPANJANG', '',
        ];
        $extensionData = [
            'KCP Sudirman Ponorogo', '02204', 'KC Ponorogo', '', 10, 12500, 8, 2,
            1, 100, 2, 200, 3, 300, 4, 400, 5, 500, 6, 600, 7, 700,
        ];
        $extension = $service->parseExtensionCsv($this->csv([
            $extensionHeader,
            $extensionGroups,
            $extensionData,
        ]));

        $this->assertCount(1, $extension['records']);
        $this->assertSame('KC PONOROGO', $extension['records'][0]['branch']);
        $this->assertSame(['deb' => 10, 'amount_juta' => 12500.0], $extension['records'][0]['total']);
        $this->assertSame(['filled' => 8, 'missing' => 2], $extension['records'][0]['attendance']);
        $this->assertSame(['deb' => 7, 'amount_juta' => 700.0], $extension['records'][0]['statuses']['tidak_diperpanjang']);

        $restructuring = $service->parseRestructuringCsv($this->csv([
            ['UKER', 'NAMA RM KUALITAS', 'NOREK', 'NASABAH', 'DATA LENGKAP', 'TANGGAL', 'TANGGAL 2', 'KETERANGAN'],
            ['Madiun', 'RM A', '0045.01.123', 'NASABAH A', '', '', '', 'Proses Pengerjaan'],
            ['KC Magetan', 'RM B', '0049.02.456', 'NASABAH B', '', '', '', 'Revisi ADK'],
            ['Ngawi', 'RM C', '0057.03.789', 'NASABAH C', '', '', '', 'Menunggu Putusan'],
            ['Ponorogo', 'RM D', '0070.04.012', 'NASABAH D', '', '', '', 'Sudah Akad'],
            ['Madiun', 'RM F', '0045.09.999', 'NASABAH F', '', '', '', '250/SML/Nego Nasabah'],
            ['Malang', 'RM E', '0001.00.000', 'NASABAH E', '', '', '', 'Analisa'],
        ]));

        $this->assertCount(5, $restructuring['records']);
        $this->assertSame(
            ['analisa_rm', 'verifikasi_adk', 'menunggu_putusan', 'sudah_diputus', 'analisa_rm'],
            array_column($restructuring['records'], 'status')
        );
        $this->assertSame('4501123', $restructuring['records'][0]['account']);
        $this->assertSame(250.0, $restructuring['records'][4]['source_amount_juta']);
    }

    public function test_rtl_parser_flattens_merged_three_row_headers(): void
    {
        $service = $this->service();
        $groupHeader = ['AREA HEAD', 'KODE KANCA', 'KODE UKER', 'UKER'];
        $metricHeader = ['', '', '', ''];
        $leafHeader = ['', '', '', ''];
        $data = ['AREA 6', '0045', '00045', 'KC Madiun'];

        for ($vendor = 1; $vendor <= 15; $vendor++) {
            array_push($groupHeader, 'VENDOR '.$vendor, '', '', '', '', '', '', '', '', '', '', '');
            array_push($metricHeader, 'TOTAL PIPELINE', '', '', '', '', 'OTS', '', '', '', '', 'PEMBIAYAAN', '');
            array_push($leafHeader, '', '', '', '', '', 'Sudah OTS', 'Belum OTS', '', '', '', 'Berminat', 'Tidak Berminat');
            array_push($data, $vendor, 0, 0, 0, 0, 10 + $vendor, 0, 0, 0, 0, 20 + $vendor, 0);
        }

        $rtl = $service->parseRtlPipelineCsv($this->csv([$groupHeader, $metricHeader, $leafHeader, $data]));

        $this->assertCount(1, $rtl['records']);
        $this->assertSame(
            ['pipeline' => 1, 'ots' => 11, 'interested' => 21],
            $rtl['records'][0]['vendors']['petrokimia']
        );
        $this->assertSame(
            ['pipeline' => 14, 'ots' => 24, 'interested' => 34],
            $rtl['records'][0]['vendors']['pupuk_indonesia']
        );
    }

    public function test_vendor_nominative_parser_flattens_headers_and_keeps_only_area_6_rows(): void
    {
        $service = $this->service();
        $parsed = $service->parseRtlVendorMatrix([
            ['VENDOR PETROKIMIA'],
            ['', '', '', 'ISI DI SINI'],
            ['NO', 'KODE KANCA', 'KANCA KONSOL', 'UKER (KCP/KC)', 'NAMA DISTRIBUTOR', 'ALAMAT', 'CIF', 'GIRO', '', 'Nama RM PIC', 'SUDAH TARIK SLIK', 'KONDISI SLIK', 'Sudah OTS', 'Pembiayaan (Minat/Tidak Berminat)', 'Plafond (Juta)'],
            ['', '', '', '', '', '', '', 'Norek Giro', 'Saldo', '', '', '', '', '', ''],
            [1, '0045', 'KC Madiun', 'KC', 'DISTRIBUTOR MADIUN', 'Jl. Madiun', 'CIF01', '123456', '500', 'RM A', 'Sudah', 'Slik Hijau', 'Sudah OTS', 'Berminat', '1200'],
            [2, '0049', 'KC Magetan', 'KCP', 'DISTRIBUTOR MAGETAN', 'Jl. Magetan', '', '', '', 'RM B', 'Belum', '', 'Belum OTS', 'Tidak Berminat', ''],
            [3, '0033', 'KC Kediri', 'KC', 'DISTRIBUTOR LUAR AREA', 'Jl. Kediri', '', '', '', 'RM C', 'Sudah', 'Slik Hijau', 'Sudah OTS', 'Berminat', '900'],
        ]);

        $this->assertCount(2, $parsed['rows']);
        $this->assertSame(['KC MADIUN', 'KC MAGETAN'], array_column($parsed['rows'], 'branch'));
        $labels = array_column($parsed['columns'], 'label');
        $this->assertContains('NAMA DISTRIBUTOR', $labels);
        $this->assertContains('GIRO - Norek Giro', $labels);
        $this->assertContains('GIRO - Saldo', $labels);
        $this->assertContains('Pembiayaan (Minat/Tidak Berminat)', $labels);
    }

    public function test_vendor_nominatives_reads_requested_xlsx_sheet_and_honours_locked_branch(): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()
            ->setTitle('VENDOR PETROKIMIA')
            ->fromArray([
                ['VENDOR PETROKIMIA'],
                ['', '', '', 'ISI DI SINI'],
                ['NO', 'KODE KANCA', 'KANCA KONSOL', 'UKER (KCP/KC)', 'NAMA DISTRIBUTOR', 'Nama RM PIC', 'Sudah OTS'],
                [1, '0045', 'KC Madiun', 'KC', 'DISTRIBUTOR MADIUN', 'RM A', 'Sudah OTS'],
                [2, '0049', 'KC Magetan', 'KC', 'DISTRIBUTOR MAGETAN', 'RM B', 'Belum OTS'],
            ]);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'rtl_vendor_test_');
        $this->assertNotFalse($temporaryFile);

        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($temporaryFile);
            \Illuminate\Support\Facades\Http::fake([
                '*' => \Illuminate\Support\Facades\Http::response(file_get_contents($temporaryFile), 200),
            ]);

            $payload = $this->service()->vendorNominatives(
                'petrokimia',
                UserBranchScope::forKey('madiun'),
                true
            );

            $this->assertTrue($payload['available']);
            $this->assertSame('KC MADIUN', $payload['scope_label']);
            $this->assertSame(1, $payload['row_count']);
            $this->assertSame('KC MADIUN', data_get($payload, 'rows.0.branch'));
            $this->assertContains('NAMA DISTRIBUTOR', array_column($payload['columns'], 'label'));
        } finally {
            $spreadsheet->disconnectWorksheets();
            if (is_string($temporaryFile) && is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }

    public function test_branch_scope_aggregates_kc_and_its_requested_kcp_only(): void
    {
        $service = $this->service();
        $baseStatus = static fn (int $deb, float $amount): array => [
            'belum_ots' => ['deb' => $deb, 'amount_juta' => $amount],
            'analisa_rm' => ['deb' => 0, 'amount_juta' => 0],
            'verifikasi_adk' => ['deb' => 0, 'amount_juta' => 0],
            'menunggu_putusan' => ['deb' => 0, 'amount_juta' => 0],
            'sudah_diputus' => ['deb' => 0, 'amount_juta' => 0],
            'realisasi' => ['deb' => 0, 'amount_juta' => 0],
            'batal' => ['deb' => 0, 'amount_juta' => 0],
        ];
        $external = [
            'hot_prospects' => [
                'records' => [
                    ['branch' => 'KC MADIUN', 'unit_code' => '45', 'rm' => 'RM A', 'statuses' => $baseStatus(2, 300)],
                    ['branch' => 'KC MADIUN', 'unit_code' => '552', 'rm' => 'RM B', 'statuses' => $baseStatus(3, 450)],
                    ['branch' => 'KC NGAWI', 'unit_code' => '57', 'rm' => 'RM C', 'statuses' => $baseStatus(9, 900)],
                ],
                'meta' => ['available' => true],
            ],
            'rtl_pipeline' => [
                'records' => [
                    [
                        'branch' => 'KC MADIUN',
                        'unit_code' => '45',
                        'vendors' => ['petrokimia' => ['pipeline' => 2, 'ots' => 1, 'interested' => 1]],
                    ],
                    [
                        'branch' => 'KC MADIUN',
                        'unit_code' => '552',
                        'vendors' => ['petrokimia' => ['pipeline' => 3, 'ots' => 2, 'interested' => 2]],
                    ],
                    [
                        'branch' => 'KC NGAWI',
                        'unit_code' => '57',
                        'vendors' => ['petrokimia' => ['pipeline' => 9, 'ots' => 8, 'interested' => 7]],
                    ],
                ],
                'meta' => ['available' => true],
            ],
            'extension' => ['records' => [], 'meta' => ['available' => true]],
            'restructuring' => ['records' => [], 'meta' => ['available' => true]],
        ];
        $quadrants = [
            'period' => '2026-08-22',
            'period_label' => '22 Agu 2026',
            'branches' => [
                [
                    'branch' => 'KC MADIUN',
                    'total_rm' => 2,
                    'quadrants' => [
                        1 => ['count' => 1, 'percentage' => 50],
                        2 => ['count' => 0, 'percentage' => 0],
                        3 => ['count' => 0, 'percentage' => 0],
                        4 => ['count' => 1, 'percentage' => 50],
                    ],
                    'rms' => [['rm' => 'RM A', 'quadrant' => 1], ['rm' => 'RM B', 'quadrant' => 4]],
                ],
            ],
        ];

        $payload = $service->buildScopedPayload($external, $quadrants, UserBranchScope::forKey('madiun'));

        $this->assertSame('branch', $payload['meta']['scope']);
        $this->assertSame(2, $payload['quadrants']['total_rm']);
        $this->assertSame(5, $payload['hot_prospects']['statuses'][0]['deb']);
        $this->assertSame(750.0, $payload['hot_prospects']['statuses'][0]['amount_juta']);
        $this->assertSame(2, $payload['hot_prospects']['unit_count']);
        $this->assertSame(5, $payload['rtl_pipeline']['total_pipeline']);
        $this->assertSame(3, $payload['rtl_pipeline']['total_ots']);
        $this->assertSame(3, $payload['rtl_pipeline']['total_interested']);
        $this->assertSame(5, $payload['rtl_pipeline']['vendors'][0]['pipeline']);
        $this->assertSame(['KC MADIUN'], array_column($payload['rtl_pipeline']['vendors'][0]['branches'], 'branch'));
        $this->assertStringContainsString('sheet=VENDOR%20PETROKIMIA', $payload['rtl_pipeline']['vendors'][0]['source_url']);
    }

    public function test_calendar_week_ends_on_saturday_and_rolls_over_on_sunday(): void
    {
        $service = $this->service();

        $weekOne = $service->calendarWeek('2026-10-01');
        $weekTwo = $service->calendarWeek('2026-10-04');
        $augustWeekFive = $service->calendarWeek('2026-08-28');

        $this->assertSame(1, $weekOne['number']);
        $this->assertSame('2026-10-01', $weekOne['start']);
        $this->assertSame('2026-10-03', $weekOne['end']);
        $this->assertSame(2, $weekTwo['number']);
        $this->assertSame('2026-10-04', $weekTwo['start']);
        $this->assertSame('2026-10-10', $weekTwo['end']);
        $this->assertSame(5, $augustWeekFive['number']);
        $this->assertSame('2026-08-29', $augustWeekFive['end']);
    }

    public function test_branch_scope_recalculates_realization_tiers_and_unproductive_rm(): void
    {
        $service = $this->service();
        $quadrants = [
            'period' => '2026-08-28',
            'branches' => [],
            'realization_tiers' => [
                'available' => true,
                'basis' => 'Closing',
                'totals' => [
                    'lt_500' => ['label' => '< Rp 500 jt'],
                    '500_1000' => ['label' => 'Rp 500 - <1.000 jt'],
                    '1000_1600' => ['label' => 'Rp 1.000 - <1.600 jt'],
                    'gte_1600' => ['label' => '≥ Rp 1.600 jt'],
                ],
                'branches' => [
                    [
                        'branch' => 'KC MADIUN',
                        'total_rm' => 2,
                        'tiers' => [
                            'lt_500' => ['rm_count' => 1, 'amount' => 400_000_000, 'rms' => [['rm' => 'RM A', 'unit_code' => '45', 'unit' => 'KC MADIUN', 'realization_rp' => 400_000_000]]],
                            '500_1000' => ['rm_count' => 1, 'amount' => 700_000_000],
                            '1000_1600' => ['rm_count' => 0, 'amount' => 0],
                            'gte_1600' => ['rm_count' => 0, 'amount' => 0],
                        ],
                    ],
                    [
                        'branch' => 'KC NGAWI',
                        'total_rm' => 1,
                        'tiers' => [
                            'lt_500' => ['rm_count' => 0, 'amount' => 0],
                            '500_1000' => ['rm_count' => 0, 'amount' => 0],
                            '1000_1600' => ['rm_count' => 0, 'amount' => 0],
                            'gte_1600' => ['rm_count' => 1, 'amount' => 1_800_000_000],
                        ],
                    ],
                ],
            ],
            'unproductive' => [
                'available' => true,
                'period_label' => 'Mar 26 - Agu 26',
                'totals' => [
                    'month_1' => ['label' => '1 bulan'],
                    'month_3' => ['label' => '3 bulan berturut-turut'],
                    'month_6' => ['label' => '6 bulan berturut-turut'],
                ],
                'branches' => [
                    [
                        'branch' => 'KC MADIUN',
                        'total_rm' => 2,
                        'metrics' => [
                            'month_1' => ['count' => 2, 'rms' => [['rm' => 'RM A'], ['rm' => 'RM B']]],
                            'month_3' => ['count' => 1, 'rms' => [['rm' => 'RM B']]],
                            'month_6' => ['count' => 0, 'rms' => []],
                        ],
                    ],
                    [
                        'branch' => 'KC NGAWI',
                        'total_rm' => 1,
                        'metrics' => [
                            'month_1' => ['count' => 1, 'rms' => [['rm' => 'RM C']]],
                            'month_3' => ['count' => 1, 'rms' => [['rm' => 'RM C']]],
                            'month_6' => ['count' => 1, 'rms' => [['rm' => 'RM C']]],
                        ],
                    ],
                ],
            ],
        ];
        $external = [
            'hot_prospects' => ['records' => [], 'meta' => ['available' => true]],
            'rtl_pipeline' => ['records' => [], 'meta' => ['available' => true]],
            'extension' => ['records' => [], 'meta' => ['available' => true]],
            'restructuring' => ['records' => [], 'meta' => ['available' => true]],
            'kanwil_decisions' => ['records' => [], 'meta' => ['available' => true]],
        ];

        $areaPayload = $service->buildScopedPayload($external, $quadrants);
        $this->assertCount(3, data_get($areaPayload, 'unproductive.totals.month_1.rms'));
        $this->assertSame('KC MADIUN', data_get($areaPayload, 'unproductive.totals.month_1.rms.0.branch'));
        $this->assertSame('KC NGAWI', data_get($areaPayload, 'unproductive.totals.month_1.rms.2.branch'));

        $payload = $service->buildScopedPayload($external, $quadrants, UserBranchScope::forKey('madiun'));

        $this->assertSame(2, data_get($payload, 'realization_tiers.total_rm'));
        $this->assertSame(1, data_get($payload, 'realization_tiers.totals.lt_500.rm_count'));
        $this->assertSame(50.0, data_get($payload, 'realization_tiers.totals.lt_500.percentage'));
        $this->assertSame('RM A', data_get($payload, 'realization_tiers.totals.lt_500.rms.0.rm'));
        $this->assertSame('KC MADIUN', data_get($payload, 'realization_tiers.totals.lt_500.rms.0.branch'));
        $this->assertSame(2, data_get($payload, 'unproductive.totals.month_1.count'));
        $this->assertSame(1, data_get($payload, 'unproductive.totals.month_3.count'));
        $this->assertSame('RM B', data_get($payload, 'unproductive.totals.month_3.rms.0.rm'));
        $this->assertSame('KC MADIUN', data_get($payload, 'unproductive.totals.month_3.rms.0.branch'));
    }

    public function test_restructuring_uses_only_sheet_amounts_without_legacy_unit_breakdown(): void
    {
        $service = $this->service();
        $external = [
            'hot_prospects' => ['records' => [], 'meta' => ['available' => true]],
            'rtl_pipeline' => ['records' => [], 'meta' => ['available' => true]],
            'extension' => ['records' => [], 'meta' => ['available' => true]],
            'restructuring' => [
                'records' => [
                    ['branch' => 'KC MADIUN', 'rm' => 'RM A', 'status' => 'analisa_rm', 'source_amount_juta' => 250.0],
                    ['branch' => 'KC MADIUN', 'rm' => 'RM B', 'status' => 'verifikasi_adk', 'source_amount_juta' => null],
                    ['branch' => 'KC NGAWI', 'rm' => 'RM C', 'status' => 'menunggu_putusan', 'source_amount_juta' => 500.0],
                ],
                'meta' => ['available' => true, 'label' => 'Pipeline Restruk'],
            ],
        ];
        $quadrants = ['branches' => []];

        $areaPayload = $service->buildScopedPayload($external, $quadrants);
        $branchPayload = $service->buildScopedPayload($external, $quadrants, UserBranchScope::forKey('madiun'));

        $this->assertSame(250.0, data_get($areaPayload, 'restructuring.statuses.0.amount_juta'));
        $this->assertSame(1, data_get($areaPayload, 'restructuring.statuses.0.resolved_deb'));
        $this->assertFalse((bool) data_get($areaPayload, 'restructuring.statuses.1.amount_available'));
        $this->assertArrayNotHasKey('daily_loan_period', data_get($areaPayload, 'restructuring'));
        $this->assertArrayNotHasKey('groups', data_get($areaPayload, 'restructuring'));

        $this->assertSame(2, data_get($branchPayload, 'restructuring.record_count'));
        $this->assertArrayNotHasKey('groups', data_get($branchPayload, 'restructuring'));
    }

    public function test_kanwil_decision_parser_and_scope_follow_the_four_area_6_branches(): void
    {
        $service = $this->service();
        $parsed = $service->parseKanwilDecisionCsv($this->csv([
            ['Monitoring Restukturisasi Putusan RO Malang'],
            [],
            ['NO', 'KODE UKER', 'UKER', 'NAMA NASABAH', 'REK', 'RESTRUK KE', 'PEMUTUS', 'STATUS PAKET', 'Tanggal Kirim Ke Kanwil', 'Dikirim Melalui', 'DIO DARI ADK KANWIL', 'WORD PTK'],
            [1, '0045', 'KC Madiun', 'NASABAH A', '0045.01.1', 1, 'KANWIL', 'Sudah Diputus', '2026-08-20', 'Email', 'Ada', 'Ada'],
            [2, '0049', 'KC Magetan', 'NASABAH B', '0049.01.2', 2, 'KANWIL', '', '2026-08-21', 'BRIMEN', '', 'Ada'],
            [3, '0057', 'KC Ngawi', 'NASABAH C', '0057.01.3', 1, 'KANWIL', '', '', '', '', ''],
            [4, '0001', 'KC Malang', 'NASABAH D', '0001.01.4', 1, 'KANWIL', 'Diputus', '2026-08-20', 'Email', 'Ada', 'Ada'],
        ]));

        $this->assertCount(3, $parsed['records']);
        $this->assertTrue($parsed['records'][0]['is_decided']);
        $this->assertTrue($parsed['records'][1]['is_sent']);

        $external = [
            'hot_prospects' => ['records' => [], 'meta' => ['available' => true]],
            'rtl_pipeline' => ['records' => [], 'meta' => ['available' => true]],
            'extension' => ['records' => [], 'meta' => ['available' => true]],
            'restructuring' => ['records' => [], 'meta' => ['available' => true]],
            'kanwil_decisions' => array_merge($parsed, ['meta' => ['available' => true]]),
        ];
        $payload = $service->buildScopedPayload($external, ['branches' => []]);

        $this->assertSame(3, data_get($payload, 'kanwil_decisions.record_count'));
        $this->assertSame(2, data_get($payload, 'kanwil_decisions.sent_count'));
        $this->assertSame(1, data_get($payload, 'kanwil_decisions.decided_count'));
        $this->assertSame(
            ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'],
            array_column(data_get($payload, 'kanwil_decisions.branches'), 'branch')
        );
    }

    private function service(): LandingSmeOperationalService
    {
        return new LandingSmeOperationalService(Mockery::mock(KinerjaRmReportController::class));
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }
}
