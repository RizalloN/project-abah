<?php

namespace Tests\Unit;

use App\Support\LandingPnMismatchService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LandingPnMismatchServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();
        Cache::flush();

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->date('periode');
            $table->string('segmen_dashboard');
            $table->string('cabang1');
            $table->string('pn_pengelola1');
            $table->string('pn_name1')->nullable();
            $table->string('nomor_rekening1');
            $table->string('nama_debitur1');
            $table->string('unit1');
            $table->string('produk_dashboard');
        });
        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            $table->id();
            $table->string('pernr');
            $table->string('completename');
        });
        DB::table('brihc_pemasar')->insert([
            ['pernr' => '00123456', 'completename' => 'Nama Resmi'],
            ['pernr' => '00123457', 'completename' => 'Nama Lain'],
        ]);
    }

    public function test_only_mismatched_nominatives_in_selected_segment_period_and_branch_are_returned(): void
    {
        foreach ([
            ['SMALL', 'KC MADIUN', '00123456 - Nama Resmi', 'Nama Resmi', 'REK-OK'],
            ['SMALL', 'KC MADIUN', '00123456 - NAMA  RESMI', 'NAMA  RESMI', 'REK-CASE-OK'],
            ['SMALL', 'KC MADIUN', '00123457 - ', '', 'REK-NAME-BLANK'],
            ['SMALL', 'KC MADIUN', '00123456 - Nama Lama', 'Nama Lama', 'REK-MISMATCH'],
            ['SMALL', 'KC MADIUN', '00999999 - Tidak Ada', 'Tidak Ada', 'REK-UNKNOWN'],
            ['SMALL', 'KC NGAWI', '00123457 - Nama Lama', 'Nama Lama', 'REK-NGAWI'],
            ['CONSUMER', 'KC MADIUN', '00123456 - Nama Lama', 'Nama Lama', 'REK-CONSUMER'],
            ['MICRO', 'KC MADIUN', '00123456 - Nama Lama', 'Nama Lama', 'REK-MICRO'],
        ] as [$segment, $branch, $manager, $name, $account]) {
            DB::table('daily_loan_dinamis')->insert([
                'periode' => '2026-08-22', 'segmen_dashboard' => $segment, 'cabang1' => $branch,
                'pn_pengelola1' => $manager, 'pn_name1' => $name, 'nomor_rekening1' => $account,
                'nama_debitur1' => 'Debitur '.$account, 'unit1' => 'Unit 1', 'produk_dashboard' => 'Commercial',
            ]);
        }
        $service = app(LandingPnMismatchService::class);
        $scope = ['upper_label' => 'KC MADIUN'];
        $summary = $service->summary('sme', '2026-08-22', $scope);
        $this->assertTrue($summary['available']);
        $this->assertSame(2, $summary['total']);
        $this->assertSame(['KC MADIUN'], array_column($summary['branches'], 'branch'));
        $details = $service->nominatives('sme', '2026-08-22', $scope, 'KC MADIUN', 1);
        $this->assertSame(2, $details['total']);
        $this->assertSame(['REK-MISMATCH', 'REK-UNKNOWN'], array_column($details['data'], 'rekening'));
        $this->assertSame('Nama Resmi', $details['data'][0]['nama_brihc']);
        $this->assertSame('Tidak ditemukan di BRIHC', $details['data'][1]['nama_brihc']);
        $this->assertSame(0, $service->nominatives('sme', '2026-08-22', $scope, 'KC NGAWI', 1)['total']);
        $this->assertSame(1, $service->summary('consumer', '2026-08-22', $scope)['total']);
        $this->assertSame(1, $service->summary('micro', '2026-08-22', $scope)['total']);
        $this->assertSame(0, $service->summary('sme', '2026-08-21', $scope)['total']);
    }

    public function test_nominative_table_renders_double_click_trigger_and_modal(): void
    {
        $html = view('dashboard.partials.pn-mismatch', [
            'pnSegment' => 'micro',
            'pnMismatch' => ['available' => true, 'period' => '2026-08-22', 'total' => 2,
                'branches' => [['branch' => 'KC MADIUN', 'count' => 2]]],
        ])->render();

        $this->assertStringContainsString('PN Tidak Sesuai BRIHC', $html);
        $this->assertStringContainsString('data-pn-mismatch-open', $html);
        $this->assertStringContainsString('data-pn-mismatch-modal', $html);
        $this->assertStringContainsString('KC MADIUN', $html);
        $this->assertStringContainsString('2 rekening', $html);
    }

    public function test_sme_landing_no_longer_renders_pn_mismatch_card(): void
    {
        $html = view('dashboard.partials.sme-operations', ['smeOperations' => []])->render();

        $this->assertStringNotContainsString('data-pn-mismatch-section', $html);
        $this->assertStringNotContainsString('PN Tidak Sesuai BRIHC', $html);
        $this->assertStringContainsString('Sebaran Realisasi RM per Cabang', $html);
        $this->assertStringContainsString("@include('dashboard.partials.pn-mismatch'",
            file_get_contents(resource_path('views/dashboard/partials/consumer-operations.blade.php')));
        $this->assertStringContainsString("@include('dashboard.partials.pn-mismatch'",
            file_get_contents(resource_path('views/dashboard/partials/micro-performance.blade.php')));
    }
}
