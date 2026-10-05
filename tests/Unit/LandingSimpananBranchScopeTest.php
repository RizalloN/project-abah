<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Models\User;
use App\Services\Presentation\PresentationFundingStrategyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class LandingSimpananBranchScopeTest extends TestCase
{
    private DashboardSimpananController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['dashboard_harian_snapshots', 'rasio_casa_debitur_snapshots', 'rasio_casa_debitur_uker_snapshots', 'rekening_dormant_snapshots', 'jumlah_merchant_detail'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->date('snapshot_period');
            $table->string('kanca_key');
            $table->string('unit_key');
            $table->string('kanca_label');
            $table->string('unit_label');
            $table->decimal('total_simpanan', 20, 2)->default(0);
            $table->decimal('tabungan_ritel', 20, 2)->default(0);
            $table->decimal('tabungan_mikro', 20, 2)->default(0);
            $table->decimal('tabungan_wholesale', 20, 2)->default(0);
            $table->decimal('deposito_ritel', 20, 2)->default(0);
            $table->decimal('deposito_mikro', 20, 2)->default(0);
            $table->decimal('deposito_wholesale', 20, 2)->default(0);
            $table->decimal('giro_ritel', 20, 2)->default(0);
            $table->decimal('giro_mikro', 20, 2)->default(0);
            $table->decimal('giro_wholesale', 20, 2)->default(0);
        });

        Schema::create('rasio_casa_debitur_snapshots', function (Blueprint $table): void {
            $table->date('loan_period');
            $table->string('branch_key');
            $table->string('branch_label')->nullable();
            $table->string('segment_key');
            $table->decimal('os_amount', 20, 2)->default(0);
            $table->decimal('casa_amount', 20, 2)->default(0);
        });

        Schema::create('rasio_casa_debitur_uker_snapshots', function (Blueprint $table): void {
            $table->date('loan_period');
            $table->string('source_branch_key');
            $table->string('uker_key');
            $table->string('uker_label');
            $table->string('segment_key');
            $table->decimal('os_amount', 20, 2)->default(0);
            $table->decimal('casa_amount', 20, 2)->default(0);
        });

        Schema::create('rekening_dormant_snapshots', function (Blueprint $table): void {
            $table->date('posisi');
            $table->string('branch_label');
            $table->string('unit_kerja')->nullable();
            $table->integer('dormant_count')->default(0);
        });

        Schema::create('jumlah_merchant_detail', function (Blueprint $table): void {
            $table->date('POSISI');
            $table->string('NAMA_KANCA');
            $table->string('NAMA_UKER');
            $table->string('MID')->nullable();
        });

        $this->controller = app(DashboardSimpananController::class);
        (new ReflectionMethod($this->controller, 'configureLandingBranchFromKey'))
            ->invoke($this->controller, 'madiun');
    }

    protected function tearDown(): void
    {
        foreach (['dashboard_harian_snapshots', 'rasio_casa_debitur_snapshots', 'rasio_casa_debitur_uker_snapshots', 'rekening_dormant_snapshots', 'jumlah_merchant_detail'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_branch_scope_uses_non_zero_unit_rows_for_simpanan_breakdown(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->harianRow('KC MADIUN', 'madiun', 'UNIT A', 'unit-a', 150, 100, 30, 20),
            $this->harianRow('KC MADIUN', 'madiun', 'UNIT ZERO', 'unit-zero', 0, 0, 0, 0),
            $this->harianRow('KC NGAWI', 'ngawi', 'UNIT LUAR', 'unit-luar', 999, 999, 0, 0),
        ]);

        $result = (new ReflectionMethod($this->controller, 'buildSimpananBranchesBreakdown'))
            ->invoke($this->controller, '2026-09-08');

        $this->assertCount(1, $result);
        $this->assertSame('UNIT A', $result[0]['name']);
        $this->assertSame(150.0, $result[0]['simpanan']);
    }

    public function test_branch_scope_uses_casa_unit_snapshot_and_excludes_zero_or_other_branch(): void
    {
        DB::table('rasio_casa_debitur_uker_snapshots')->insert([
            $this->casaUnitRow('KC MADIUN', 'UNIT A', 'total', 1000, 200),
            $this->casaUnitRow('KC MADIUN', 'UNIT A', 'mikro', 600, 120),
            $this->casaUnitRow('KC MADIUN', 'UNIT ZERO', 'total', 0, 0),
            $this->casaUnitRow('KC NGAWI', 'UNIT LUAR', 'total', 9000, 4000),
        ]);

        $result = (new ReflectionMethod($this->controller, 'buildCasaDebiturStrategyPayload'))
            ->invoke($this->controller, '2026-09-08');

        $this->assertSame('Unit Kerja', $result['dimension_label']);
        $this->assertSame('Total KC Madiun', $result['total_label']);
        $this->assertCount(1, $result['branches']);
        $this->assertSame('UNIT A', $result['branches'][0]['branch']);
        $this->assertSame(1000.0, $result['total']['os']);
    }

    public function test_branch_scope_uses_dormant_units_and_excludes_zero_or_other_branch(): void
    {
        DB::table('rekening_dormant_snapshots')->insert([
            ['posisi' => '2025-12-31', 'branch_label' => 'KC Madiun', 'unit_kerja' => 'UNIT A', 'dormant_count' => 4],
            ['posisi' => '2026-08-31', 'branch_label' => 'KC Madiun', 'unit_kerja' => 'UNIT A', 'dormant_count' => 3],
            ['posisi' => '2026-09-08', 'branch_label' => 'KC Madiun', 'unit_kerja' => 'UNIT A', 'dormant_count' => 2],
            ['posisi' => '2026-09-08', 'branch_label' => 'KC Madiun', 'unit_kerja' => 'UNIT ZERO', 'dormant_count' => 0],
            ['posisi' => '2026-09-08', 'branch_label' => 'KC Ngawi', 'unit_kerja' => 'UNIT LUAR', 'dormant_count' => 99],
        ]);

        $result = (new ReflectionMethod($this->controller, 'buildDormantStrategyPayload'))
            ->invoke($this->controller, '2026-09-08');

        $this->assertSame('Unit Kerja', $result['dimension_label']);
        $this->assertSame('Total KC Madiun', $result['total_label']);
        $this->assertCount(1, $result['branches']);
        $this->assertSame('UNIT A', $result['branches'][0]['branch']);
        $this->assertSame(2, $result['total']['current']);
    }

    public function test_cached_strategy_payloads_are_recalculated_inside_selected_branch(): void
    {
        $payroll = [
            'summary' => ['branches' => []],
            'rows' => [
                ['kc' => 'KC Madiun', 'pegawai' => 10, 'potensi' => 8, 'existing' => 2, 'realisasi' => 3, 'kunjungan' => true],
                ['kc' => 'KC Ngawi', 'pegawai' => 99, 'potensi' => 90, 'existing' => 9, 'realisasi' => 40, 'kunjungan' => true],
            ],
        ];
        $scopedPayroll = (new ReflectionMethod($this->controller, 'scopePayrollQualityStrategyPayload'))
            ->invoke($this->controller, $payroll);

        $this->assertCount(1, $scopedPayroll['rows']);
        $this->assertSame(8, $scopedPayroll['summary']['total_potensi']);
        $this->assertSame(['KC Madiun'], array_keys($scopedPayroll['summary']['branches']));

        $ecosystem = [
            'summary' => [
                'ecosystems' => ['Pendidikan' => ['label' => 'Pendidikan']],
                'branches' => [
                    'KC Madiun' => ['branch' => 'KC Madiun', 'rekening' => 1, 'rekening_fmt' => '1'],
                    'KC Ngawi' => ['branch' => 'KC Ngawi', 'rekening' => 1, 'rekening_fmt' => '1'],
                ],
            ],
            'records' => [
                ['cabang' => 'KC Madiun', 'ekosistem' => 'Pendidikan', 'saldo' => 100],
                ['cabang' => 'KC Ngawi', 'ekosistem' => 'Pendidikan', 'saldo' => 900],
            ],
        ];
        $scopedEcosystem = (new ReflectionMethod($this->controller, 'scopeEcosystemValueChainStrategyPayload'))
            ->invoke($this->controller, $ecosystem);

        $this->assertCount(1, $scopedEcosystem['records']);
        $this->assertSame(100.0, $scopedEcosystem['summary']['totalBalance']);
        $this->assertSame(['KC Madiun'], array_keys($scopedEcosystem['summary']['branches']));
    }

    public function test_digital_channel_unit_breakdown_uses_selected_branch_and_removes_zero_units(): void
    {
        DB::table('jumlah_merchant_detail')->insert([
            ['POSISI' => '2025-12-31', 'NAMA_KANCA' => 'KC MADIUN', 'NAMA_UKER' => 'UNIT A', 'MID' => 'MID-1'],
            ['POSISI' => '2026-08-31', 'NAMA_KANCA' => 'KC MADIUN', 'NAMA_UKER' => 'UNIT A', 'MID' => 'MID-1'],
            ['POSISI' => '2026-09-08', 'NAMA_KANCA' => 'KC MADIUN', 'NAMA_UKER' => 'UNIT A', 'MID' => 'MID-1'],
            ['POSISI' => '2026-09-08', 'NAMA_KANCA' => 'KC MADIUN', 'NAMA_UKER' => 'UNIT A', 'MID' => 'MID-2'],
            ['POSISI' => '2026-09-08', 'NAMA_KANCA' => 'KC MADIUN', 'NAMA_UKER' => 'UNIT ZERO', 'MID' => null],
            ['POSISI' => '2026-09-08', 'NAMA_KANCA' => 'KC NGAWI', 'NAMA_UKER' => 'UNIT LUAR', 'MID' => 'MID-9'],
        ]);

        $result = app(PresentationFundingStrategyService::class)
            ->buildDigitalUnitBreakdown('2026-09-08', 'KC MADIUN');

        $this->assertCount(1, $result['edc']['rows']);
        $this->assertSame('UNIT A', $result['edc']['rows'][0]['branch']);
        $this->assertSame('2', $result['edc']['rows'][0]['current']);
        $this->assertSame('+1', $result['edc']['rows'][0]['d_mtd']);
    }

    public function test_branch_view_renders_locked_scope_units_without_other_area_branches(): void
    {
        $this->actingAs(new User(['pn' => '0045', 'name' => 'User Cabang Madiun', 'role' => 'user']));

        $dashboard = [
            'area6_portfolio' => [
                'cards' => [],
                'default_scope' => 'area6',
                'scopes' => [],
                'period_label' => '08 Sep 2026',
            ],
            'digital_channel_strategy' => [],
            'casa_debitur_strategy' => [
                'dimension_label' => 'Unit Kerja',
                'total_label' => 'Total KC Madiun',
                'total' => ['os_fmt' => 'Rp 1,00 T', 'casa_fmt' => 'Rp 200,00 M', 'ratio_fmt' => '20,00%'],
                'branches' => [['no' => 1, 'branch' => 'UNIT A', 'os_fmt' => 'Rp 1,00 T', 'casa_fmt' => 'Rp 200,00 M', 'ratio' => 20, 'ratio_fmt' => '20,00%']],
                'segments' => [],
            ],
            'dormant_strategy' => [
                'dimension_label' => 'Unit Kerja',
                'total_label' => 'Total KC Madiun',
                'dates' => ['ytd' => '31 Des 25', 'mtd' => '31 Agt 26', 'current' => '08 Sep 26'],
                'total' => ['ytd_fmt' => '4', 'mtd_fmt' => '3', 'current_fmt' => '2', 'd_mtd' => -1, 'd_mtd_fmt' => '-1', 'd_ytd' => -2, 'd_ytd_fmt' => '-2'],
                'branches' => [['no' => 1, 'branch' => 'UNIT A', 'ytd_fmt' => '4', 'mtd_fmt' => '3', 'current_fmt' => '2', 'd_mtd' => -1, 'd_mtd_fmt' => '-1', 'd_ytd' => -2, 'd_ytd_fmt' => '-2']],
            ],
            'payroll_quality_strategy' => ['summary' => ['branches' => []], 'rows' => [], 'dimension_label' => 'Cabang', 'total_label' => 'Total KC Madiun'],
            'perusahaan_anak_strategy' => ['summary' => ['branches' => [], 'entities' => []], 'rows' => [], 'dimension_label' => 'Cabang', 'total_label' => 'Total KC Madiun'],
            'ecosystem_value_chain_strategy' => ['summary' => ['branches' => [], 'ecosystems' => []], 'records' => [], 'dimension_label' => 'Cabang', 'total_label' => 'Total KC Madiun'],
        ];

        $html = view('dashboard.simpanan', [
            'dashboard' => $dashboard,
            'periods' => collect(['2026-09-08']),
            'selectedPeriod' => '2026-09-08',
            'landingBranchOptions' => ['madiun' => 'KC Madiun'],
            'selectedLandingBranch' => 'madiun',
            'landingBranchLocked' => true,
            'landingBranchLabel' => 'KC Madiun',
        ])->render();

        $this->assertStringContainsString('Cabang User', $html);
        $this->assertMatchesRegularExpression('/id="landing-branch-selector"[^>]*disabled/', $html);
        $this->assertStringContainsString('UNIT A', $html);
        $this->assertStringContainsString('TOTAL KC MADIUN', $html);
        $this->assertStringNotContainsString('KC Ngawi', $html);
        $this->assertStringNotContainsString('KC Ponorogo', $html);
    }

    private function harianRow(string $branch, string $branchKey, string $unit, string $unitKey, float $total, float $tabungan, float $deposito, float $giro): array
    {
        return [
            'snapshot_period' => '2026-09-08',
            'kanca_key' => $branchKey,
            'unit_key' => $unitKey,
            'kanca_label' => $branch,
            'unit_label' => $unit,
            'total_simpanan' => $total,
            'tabungan_ritel' => $tabungan,
            'deposito_ritel' => $deposito,
            'giro_ritel' => $giro,
        ];
    }

    private function casaUnitRow(string $branch, string $unit, string $segment, float $os, float $casa): array
    {
        return [
            'loan_period' => '2026-09-08',
            'source_branch_key' => $branch,
            'uker_key' => strtolower(str_replace(' ', '-', $unit)),
            'uker_label' => $unit,
            'segment_key' => $segment,
            'os_amount' => $os,
            'casa_amount' => $casa,
        ];
    }
}
