<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        test()->markTestSkipped('Test ini hanya boleh berjalan di SQLite. Bukan di MySQL production. Periksa phpunit.xml.');
    }

    Schema::dropIfExists('daily_loan_dinamis');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('pn')->unique();
        $table->string('role')->default('user');
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('daily_loan_dinamis', function (Blueprint $table) {
        $table->string('uniqueid_namareport')->primary();
        $table->date('periode')->nullable();
        $table->string('cabang1')->nullable();
        $table->string('unit1')->nullable();
        $table->string('nomor_rekening1')->nullable();
        $table->string('status_rekening1')->nullable();
        $table->decimal('baki_debet1', 20, 2)->nullable();
        $table->integer('kolek')->nullable();
        $table->integer('umur_tunggakan')->nullable();
        $table->decimal('tunggakan_pokok', 20, 2)->nullable();
        $table->decimal('tunggakan_bunga', 20, 2)->nullable();
        $table->decimal('tunggakan_penalti', 20, 2)->nullable();
        $table->string('kol_adk1')->nullable();
        $table->string('kolek_detail')->nullable();
        $table->date('tanggal_menunggak')->nullable();
        $table->date('tgl_akad_restruk')->nullable();
        $table->string('npl_method')->nullable();
        $table->string('flag_restruk')->nullable();
        $table->timestamps();
        foreach (dailyLoanHelperOutputColumns() as $column) {
            $table->string($column)->nullable();
        }

        $table->index(['periode', 'cabang1', 'unit1'], 'idx_test_period_branch_unit');
    });

    Cache::flush();
});

afterEach(function () {
    Schema::dropIfExists('daily_loan_dinamis');
    Schema::dropIfExists('users');
});

function dailyLoanHelperOutputColumns(): array
{
    return [
        'segmen_kinerja',
        'produk_kinerja',
        'cabang_normalized',
        'unit_normalized',
        'branch_normalized',
        'rm_normalized',
        'pn_pemutus_normalized',
        'cifno_clean',
        'shadow_built_at',
        'cabang_normalized_gc',
        'unit_normalized_gc',
        'branch_normalized_gc',
        'rm_normalized_gc',
        'pn_pemutus_normalized_gc',
        'cifno_clean_gc',
    ];
}

function seedSmallArrearsRows(): void
{
    DB::table('daily_loan_dinamis')->insert([
        [
            'uniqueid_namareport' => 'curr-kc-mdn-a',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-001',
            'tunggakan_pokok' => 30000,
            'tunggakan_bunga' => 20000,
            'tunggakan_penalti' => 10000,
        ],
        [
            'uniqueid_namareport' => 'curr-kc-mdn-a-duplicate',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-001',
            'tunggakan_pokok' => 10000,
            'tunggakan_bunga' => 5000,
            'tunggakan_penalti' => 5000,
        ],
        [
            'uniqueid_namareport' => 'curr-kc-mdn-b-over',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit B',
            'nomor_rekening1' => 'LN-002',
            'tunggakan_pokok' => 50000,
            'tunggakan_bunga' => 20000,
            'tunggakan_penalti' => 40000,
        ],
        [
            'uniqueid_namareport' => 'curr-kc-ngw-c',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Ngawi',
            'unit1' => 'Unit C',
            'nomor_rekening1' => 'LN-003',
            'tunggakan_pokok' => 10000,
            'tunggakan_bunga' => 5000,
            'tunggakan_penalti' => 0,
        ],
        [
            'uniqueid_namareport' => 'dtd-kc-mdn-a',
            'periode' => '2026-04-21',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-001',
            'tunggakan_pokok' => 20000,
            'tunggakan_bunga' => 10000,
            'tunggakan_penalti' => 5000,
        ],
        [
            'uniqueid_namareport' => 'dtd-kc-mdn-b',
            'periode' => '2026-04-21',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit B',
            'nomor_rekening1' => 'LN-004',
            'tunggakan_pokok' => 10000,
            'tunggakan_bunga' => 10000,
            'tunggakan_penalti' => 10000,
        ],
        [
            'uniqueid_namareport' => 'ytd-kc-mdn-a',
            'periode' => '2025-12-31',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-001',
            'tunggakan_pokok' => 10000,
            'tunggakan_bunga' => 10000,
            'tunggakan_penalti' => 10000,
        ],
        [
            'uniqueid_namareport' => 'mtd-kc-mdn-a',
            'periode' => '2026-03-31',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-001',
            'tunggakan_pokok' => 10000,
            'tunggakan_bunga' => 10000,
            'tunggakan_penalti' => 10000,
        ],
    ]);
}

it('uses native daily loan periods units balances and exports for small arrears only', function () {
    $base = ['cabang1' => 'KC Madiun', 'unit1' => 'Native Unit', 'nomor_rekening1' => 'NATIVE', 'tunggakan_pokok' => 10000];
    foreach ([
        ['uniqueid_namareport' => 'DAILY:YTD', 'periode' => '2025-12-30'],
        ['uniqueid_namareport' => 'DAILY:MTD', 'periode' => '2026-03-30'],
        ['uniqueid_namareport' => 'DAILY:CURRENT', 'periode' => '2026-04-22'],
        ['uniqueid_namareport' => 'LW321PN:YTD', 'periode' => '2025-12-31'],
        ['uniqueid_namareport' => 'LW321PN:MTD', 'periode' => '2026-03-31'],
        ['uniqueid_namareport' => 'LW321PN:MIXED', 'periode' => '2026-04-22', 'unit1' => 'LW Unit', 'nomor_rekening1' => 'LW-MIXED'],
        ['uniqueid_namareport' => 'LW321PN:LATEST', 'periode' => '2026-04-23', 'unit1' => 'LW Unit'],
    ] as $row) {
        DB::table('daily_loan_dinamis')->insert(array_merge($base, $row));
    }

    $this->actingAs(User::factory()->create());
    $this->get('/report/dashboard-pinjaman/tunggakan-kecil')
        ->assertOk()
        ->assertViewHas('selectedPeriod', '2026-04-22')
        ->assertViewHas('availablePeriods', fn ($periods) => $periods->all() === ['2026-04-22', '2026-03-30', '2025-12-30']);
    $this->getJson('/report/dashboard-pinjaman/tunggakan-kecil/data?'.http_build_query(['cabang1' => ['KC Madiun']]))
        ->assertOk()
        ->assertJsonPath('selected_period', '2026-04-22')
        ->assertJsonPath('labels.ytd', '2025-12-30')
        ->assertJsonPath('labels.mtd', '2026-03-30')
        ->assertJsonPath('total.current', 1)
        ->assertJsonPath('total.ytd', 1)
        ->assertJsonPath('total.mtd', 1)
        ->assertJsonPath('total.current_tunggakan', 10000);
    $filters = $this->getJson('/report/dashboard-pinjaman/tunggakan-kecil/filters?'.http_build_query(['periode' => '2026-04-22', 'cabang1' => ['KC Madiun']]))->assertOk();
    expect($filters->json('unit_options'))->toContain('Native Unit')->not->toContain('LW Unit');
    $this->getJson('/report/dashboard-pinjaman/tunggakan-kecil/data?'.http_build_query(['periode' => '2026-04-23', 'cabang1' => ['KC Madiun']]))
        ->assertOk()->assertJsonPath('selected_period', '2026-04-23')->assertJsonPath('total.current', 0);

    $controller = new \App\Http\Controllers\DashboardPinjamanReportController();
    $export = (new ReflectionMethod($controller, 'buildSmallArrearsExportQuery'))->invoke($controller, '2026-04-22', ['KC Madiun'], []);
    expect($export->pluck('nomor_rekening1')->all())->toBe(['NATIVE']);

    // UG/NPL and six-month reports retain their shared unrestricted source helpers.
    $allPeriods = (new ReflectionMethod($controller, 'fetchPeriods'))->invoke($controller);
    expect($allPeriods->first())->toBe('2026-04-23');
    $allUnits = (new ReflectionMethod($controller, 'fetchSmallArrearsDistinctValues'))->invoke($controller, 'unit1', '2026-04-22', ['KC Madiun']);
    expect($allUnits->all())->toContain('LW Unit', 'Native Unit');
});

it('excludes normalized lw321pn from mismatch periods filters summary and export', function () {
    $base = ['cabang1' => 'KC Madiun', 'unit1' => 'Native Unit', 'status_rekening1' => '1', 'baki_debet1' => 100, 'kolek' => 3, 'umur_tunggakan' => 10];
    foreach ([
        ['uniqueid_namareport' => 'DAILY:MISMATCH', 'periode' => '2026-04-22', 'nomor_rekening1' => 'NATIVE'],
        ['uniqueid_namareport' => 'LW321PN:MISMATCH', 'periode' => '2026-04-22', 'nomor_rekening1' => 'LW-MIXED'],
        ['uniqueid_namareport' => 'LW321PN:BRANCH', 'periode' => '2026-04-22', 'nomor_rekening1' => 'LW-BRANCH', 'cabang1' => 'KC LW Only'],
        ['uniqueid_namareport' => 'LW321PN:LATEST', 'periode' => '2026-04-23', 'nomor_rekening1' => 'LW-LATEST'],
    ] as $row) {
        DB::table('daily_loan_dinamis')->insert(array_merge($base, $row));
    }
    $this->actingAs(User::factory()->create());
    $this->get('/report/dashboard-pinjaman/kolek-tidak-sesuai')
        ->assertOk()
        ->assertViewHas('selectedPeriod', '2026-04-22')
        ->assertViewHas('periods', fn ($periods) => $periods->all() === ['2026-04-22']);
    $filters = $this->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/filters?periode=2026-04-22')->assertOk();
    expect($filters->json('branches'))->toContain('KC Madiun')->not->toContain('KC LW Only');
    $this->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/data?'.http_build_query(['cabang1' => ['KC Madiun']]))
        ->assertOk()->assertJsonPath('selected_period', '2026-04-22')
        ->assertJsonPath('audit.scanned_rows', 1)->assertJsonPath('audit.mismatch_rows', 1);

    $response = $this->get('/report/dashboard-pinjaman/kolek-tidak-sesuai/export?'.http_build_query(['periode' => '2026-04-22', 'cabang1' => ['KC Madiun']]))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'native_mismatch_export_').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    try {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, true);
    } finally {
        @unlink($path);
    }
    $accountColumn = array_search('nomor_rekening1', $rows[1], true);
    expect(collect($rows)->skip(1)->pluck($accountColumn)->all())->toBe(['NATIVE']);
});

it('aggregates current total and raw posisi counts by branch office', function () {
    seedSmallArrearsRows();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/tunggakan-kecil/data?' . http_build_query([
            'periode' => '2026-04-22',
        ]));

    $response->assertOk();
    $response->assertJsonPath('group_label', 'BRANCH OFFICE');
    $response->assertJsonPath('selected_period', '2026-04-22');
    $response->assertJsonPath('selected_branches.0', 'AREA_6_ALL');
    $response->assertJsonPath('is_area_all', true);
    $response->assertJsonPath('labels.ytd', '2025-12-31');
    $response->assertJsonPath('labels.mtd', '2026-03-31');
    $response->assertJsonPath('rows.0.label', 'KC Madiun');
    $response->assertJsonPath('rows.0.ytd', 1);
    $response->assertJsonPath('rows.0.mtd', 1);
    $response->assertJsonPath('rows.0.current', 1);
    $response->assertJsonPath('rows.0.ytd_tunggakan', 30000);
    $response->assertJsonPath('rows.0.mtd_tunggakan', 30000);
    $response->assertJsonPath('rows.0.current_tunggakan', 80000);
    $response->assertJsonPath('rows.0.total_tunggakan', 80000);
    $response->assertJsonPath('rows.1.label', 'KC Magetan');
    $response->assertJsonPath('rows.1.current', 0);
    $response->assertJsonPath('rows.2.label', 'KC Ngawi');
    $response->assertJsonPath('rows.2.ytd', 0);
    $response->assertJsonPath('rows.2.mtd', 0);
    $response->assertJsonPath('rows.2.current', 1);
    $response->assertJsonPath('rows.2.total_tunggakan', 15000);
    $response->assertJsonPath('rows.3.label', 'KC Ponorogo');
    $response->assertJsonPath('rows.3.current', 0);
    $response->assertJsonPath('total.ytd', 1);
    $response->assertJsonPath('total.mtd', 1);
    $response->assertJsonPath('total.current', 2);
    $response->assertJsonPath('total.ytd_tunggakan', 30000);
    $response->assertJsonPath('total.mtd_tunggakan', 30000);
    $response->assertJsonPath('total.current_tunggakan', 95000);
    $response->assertJsonPath('total.total_tunggakan', 95000);
});

it('switches grouping to uker when unit filter is selected', function () {
    seedSmallArrearsRows();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/tunggakan-kecil/data?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Madiun'],
            'unit1' => ['Unit A'],
        ]));

    $response->assertOk();
    $response->assertJsonPath('group_label', 'UKER');
    $response->assertJsonPath('rows.0.label', 'Unit A');
    $response->assertJsonPath('rows.0.ytd', 1);
    $response->assertJsonPath('rows.0.mtd', 1);
    $response->assertJsonPath('rows.0.current', 1);
    $response->assertJsonPath('rows.0.ytd_tunggakan', 30000);
    $response->assertJsonPath('rows.0.mtd_tunggakan', 30000);
    $response->assertJsonPath('rows.0.current_tunggakan', 80000);
    $response->assertJsonPath('rows.0.total_tunggakan', 80000);
    $response->assertJsonPath('total.ytd', 1);
    $response->assertJsonPath('total.mtd', 1);
    $response->assertJsonPath('total.current', 1);
    $response->assertJsonPath('total.ytd_tunggakan', 30000);
    $response->assertJsonPath('total.mtd_tunggakan', 30000);
    $response->assertJsonPath('total.current_tunggakan', 80000);
    $response->assertJsonPath('total.total_tunggakan', 80000);
});

it('keeps branch scope when all uker is selected for a branch', function () {
    seedSmallArrearsRows();

    DB::table('daily_loan_dinamis')->insert([
        [
            'uniqueid_namareport' => 'curr-kc-mdn-b-small',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit B',
            'nomor_rekening1' => 'LN-005',
            'tunggakan_pokok' => 10000,
            'tunggakan_bunga' => 5000,
            'tunggakan_penalti' => 5000,
        ],
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/tunggakan-kecil/data?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Madiun'],
            'unit1' => ['ALL_UKER'],
        ]));

    $response->assertOk();
    $response->assertJsonPath('group_label', 'UKER');
    $response->assertJsonPath('selected_units.0', 'ALL_UKER');
    $response->assertJsonPath('rows.0.label', 'Unit A');
    $response->assertJsonPath('rows.0.current', 1);
    $response->assertJsonPath('rows.0.total_tunggakan', 80000);
    $response->assertJsonPath('rows.1.label', 'Unit B');
    $response->assertJsonPath('rows.1.current', 1);
    $response->assertJsonPath('rows.1.total_tunggakan', 20000);
    $response->assertJsonPath('total.current', 2);
    $response->assertJsonPath('total.total_tunggakan', 100000);
});

it('excludes zero arrears and includes exactly one hundred thousand', function () {
    seedSmallArrearsRows();

    DB::table('daily_loan_dinamis')->insert([
        [
            'uniqueid_namareport' => 'curr-kc-mdn-zero',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-ZERO',
            'tunggakan_pokok' => 0,
            'tunggakan_bunga' => 0,
            'tunggakan_penalti' => 0,
        ],
        [
            'uniqueid_namareport' => 'curr-kc-mdn-exact-100k',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-100K',
            'tunggakan_pokok' => 50000,
            'tunggakan_bunga' => 30000,
            'tunggakan_penalti' => 20000,
        ],
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/tunggakan-kecil/data?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Madiun'],
            'unit1' => ['Unit A'],
        ]));

    $response->assertOk();
    $response->assertJsonPath('rows.0.current', 2);
    $response->assertJsonPath('rows.0.total_tunggakan', 180000);
    $response->assertJsonPath('total.current', 2);
    $response->assertJsonPath('total.total_tunggakan', 180000);
});

it('counts kolek mismatch only for status one or three with positive baki debet', function () {
    DB::table('daily_loan_dinamis')->insert([
        [
            'uniqueid_namareport' => 'mismatch-status-1-positive',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Ponorogo',
            'unit1' => 'Unit Ponorogo',
            'nomor_rekening1' => 'KTS-001',
            'status_rekening1' => '1',
            'baki_debet1' => 100,
            'kolek' => 3,
            'umur_tunggakan' => 10,
        ],
        [
            'uniqueid_namareport' => 'mismatch-status-3-positive',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Ponorogo',
            'unit1' => 'Unit Ponorogo',
            'nomor_rekening1' => 'KTS-002',
            'status_rekening1' => '3',
            'baki_debet1' => 200,
            'kolek' => 4,
            'umur_tunggakan' => 10,
        ],
        [
            'uniqueid_namareport' => 'excluded-status-2-positive',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Ponorogo',
            'unit1' => 'Unit Ponorogo',
            'nomor_rekening1' => 'KTS-003',
            'status_rekening1' => '2',
            'baki_debet1' => 300,
            'kolek' => 5,
            'umur_tunggakan' => 10,
        ],
        [
            'uniqueid_namareport' => 'excluded-status-1-zero-baki',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Ponorogo',
            'unit1' => 'Unit Ponorogo',
            'nomor_rekening1' => 'KTS-004',
            'status_rekening1' => '1',
            'baki_debet1' => 0,
            'kolek' => 5,
            'umur_tunggakan' => 10,
        ],
        [
            'uniqueid_namareport' => 'matched-status-1-positive',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Ponorogo',
            'unit1' => 'Unit Ponorogo',
            'nomor_rekening1' => 'KTS-005',
            'status_rekening1' => '1',
            'baki_debet1' => 400,
            'kolek' => 2,
            'umur_tunggakan' => 10,
        ],
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/data?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Ponorogo'],
            'refresh' => true,
        ]));

    $response->assertOk();
    $response->assertJsonPath('audit.rule', 'kolek_vs_umur_tunggakan_v4');
    $response->assertJsonPath('audit.scanned_rows', 3);
    $response->assertJsonPath('audit.matched_rows', 1);
    $response->assertJsonPath('audit.mismatch_rows', 2);
    $response->assertJsonPath('audit.total_outstanding_balance', 300);
    $response->assertJsonPath('summary_rows.0.label', 'Unit Ponorogo');
    $response->assertJsonPath('summary_rows.0.mismatch_count', 2);
    $response->assertJsonPath('summary_rows.0.kolek_membaik_count', 2);
    $response->assertJsonPath('summary_rows.0.outstanding_balance', 300);
});

it('excludes daily loan helper columns from kolek mismatch export', function () {
    DB::table('daily_loan_dinamis')->insert(array_merge([
        'uniqueid_namareport' => 'mismatch-helper-columns-export',
        'periode' => '2026-04-22',
        'cabang1' => 'KC Ponorogo',
        'unit1' => 'Unit Ponorogo',
        'nomor_rekening1' => 'KTS-HELPER-001',
        'status_rekening1' => '1',
        'baki_debet1' => 100,
        'kolek' => 3,
        'umur_tunggakan' => 10,
    ], array_fill_keys(dailyLoanHelperOutputColumns(), 'helper-only')));

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/report/dashboard-pinjaman/kolek-tidak-sesuai/export?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Ponorogo'],
        ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = tempnam(sys_get_temp_dir(), 'kolek_mismatch_export_') . '.xlsx';
    file_put_contents($path, $response->streamedContent());

    try {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
    } finally {
        @unlink($path);
    }

    $headers = array_values($rows[1]);
    expect($headers)->toContain('periode');
    expect($headers)->toContain('kolek_seharusnya');
    expect($headers)->toContain('keterangan');

    foreach (dailyLoanHelperOutputColumns() as $helperColumn) {
        expect($headers)->not->toContain($helperColumn);
    }

    expect(collect($rows)->skip(1)->pluck('D')->all())->toContain('KTS-HELPER-001');
});

it('keeps user selected period and returns zero totals when no data exists on that date', function () {
    seedSmallArrearsRows();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/tunggakan-kecil/data?' . http_build_query([
            'periode' => '2026-04-24',
            'cabang1' => ['KC Madiun'],
        ]));

    $response->assertOk();
    $response->assertJsonPath('selected_period', '2026-04-24');
    $response->assertJsonPath('group_label', 'UKER');
    $response->assertJsonPath('rows.0.label', 'Unit A');
    $response->assertJsonPath('rows.0.ytd', 0);
    $response->assertJsonPath('rows.0.mtd', 0);
    $response->assertJsonPath('rows.0.current', 0);
    $response->assertJsonPath('rows.0.total_tunggakan', 0);
    $response->assertJsonPath('total.ytd', 0);
    $response->assertJsonPath('total.mtd', 0);
    $response->assertJsonPath('total.current', 0);
    $response->assertJsonPath('total.total_tunggakan', 0);
});

it('returns area 6 default branch selector and disables unit options until branch is chosen', function () {
    seedSmallArrearsRows();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/tunggakan-kecil/filters?' . http_build_query([
            'periode' => '2026-04-22',
        ]));

    $response->assertOk();
    $response->assertJsonPath('selected_branches.0', 'AREA_6_ALL');
    $response->assertJsonPath('effective_branches.0', 'KC Madiun');
    $response->assertJsonPath('effective_branches.1', 'KC Magetan');
    $response->assertJsonPath('effective_branches.2', 'KC Ngawi');
    $response->assertJsonPath('effective_branches.3', 'KC Ponorogo');
    $response->assertJsonPath('is_area_all', true);
    $response->assertJsonPath('branch_options.0', 'AREA_6_ALL');
    $response->assertJsonCount(0, 'unit_options');
});

it('filters unit selector based on selected branch offices', function () {
    seedSmallArrearsRows();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/tunggakan-kecil/filters?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Ngawi'],
        ]));

    $response->assertOk();
    $response->assertJsonPath('selected_branches.0', 'KC Ngawi');
    $response->assertJsonPath('is_area_all', false);
    $response->assertJsonCount(2, 'unit_options');
    $response->assertJsonPath('unit_options.0', 'ALL_UKER');
    $response->assertJsonPath('unit_options.1', 'Unit C');
    $response->assertJsonPath('selected_units.0', 'ALL_UKER');
});

it('streams small arrears export as excel workbook for selected unit', function () {
    seedSmallArrearsRows();

    DB::table('daily_loan_dinamis')->insert([
        [
            'uniqueid_namareport' => 'curr-kc-mdn-zero-export',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-ZERO-EXPORT',
            'tunggakan_pokok' => 0,
            'tunggakan_bunga' => 0,
            'tunggakan_penalti' => 0,
        ],
        [
            'uniqueid_namareport' => 'curr-kc-mdn-exact-100k-export',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-100K-EXPORT',
            'tunggakan_pokok' => 50000,
            'tunggakan_bunga' => 30000,
            'tunggakan_penalti' => 20000,
        ],
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/report/dashboard-pinjaman/tunggakan-kecil/export?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Madiun'],
            'unit1' => ['Unit A'],
        ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('content-disposition'))->toContain('tunggakan-kecil_20260422_KC-Madiun_Unit-A.xlsx');

    $content = $response->streamedContent();
    expect(substr($content, 0, 2))->toBe('PK');

    $path = tempnam(sys_get_temp_dir(), 'small_arrears_export_') . '.xlsx';
    file_put_contents($path, $content);

    try {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
    } finally {
        @unlink($path);
    }

    expect(array_values($rows[1]))->toContain('periode');
    expect(array_values($rows[1]))->toContain('total_tunggakan_terhitung');
    foreach (dailyLoanHelperOutputColumns() as $helperColumn) {
        expect(array_values($rows[1]))->not->toContain($helperColumn);
    }

    $accountValues = collect($rows)->skip(1)->pluck('D')->all();
    expect($accountValues)->toContain('LN-001');
    expect($accountValues)->toContain('LN-100K-EXPORT');
    expect($accountValues)->not->toContain('LN-ZERO-EXPORT');
    expect(collect($rows)->skip(1)->pluck('C')->all())->not->toContain('Unit B');
    expect(collect($rows)->skip(1)->pluck('B')->all())->not->toContain('KC Ngawi');
});

it('renders kolek tidak sesuai index view successfully', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/report/dashboard-pinjaman/kolek-tidak-sesuai');

    $response->assertOk();
    $response->assertViewIs('report.dashboard-pinjaman.mismatch');
    $response->assertSee('Kolek Tidak Sesuai');
});

it('correctly handles leading zero kolek values and falls back to kol_adk1', function () {
    DB::table('daily_loan_dinamis')->insert([
        [
            'uniqueid_namareport' => 'kts-leading-zero-kolek',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'KTS-LZ-01',
            'status_rekening1' => '1',
            'baki_debet1' => 15000000,
            'kolek' => '02',
            'kol_adk1' => '02',
            'umur_tunggakan' => 0,
            'tunggakan_pokok' => 0,
            'tunggakan_bunga' => 0,
        ],
        [
            'uniqueid_namareport' => 'kts-fallback-koladk',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'KTS-FB-02',
            'status_rekening1' => '1',
            'baki_debet1' => 20000000,
            'kolek' => null,
            'kol_adk1' => '3',
            'umur_tunggakan' => 0,
            'tunggakan_pokok' => 0,
            'tunggakan_bunga' => 0,
        ],
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/data?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Madiun'],
            'refresh' => true,
        ]));

    $response->assertOk();
    $response->assertJsonPath('audit.mismatch_rows', 2);
    $response->assertJsonPath('summary_rows.0.mismatch_count', 2);
    $response->assertJsonPath('summary_rows.0.kolek_membaik_count', 2);
});

it('guards against non-criteria records such as empty account, closed account, or non-positive baki debet', function () {
    DB::table('daily_loan_dinamis')->insert([
        [
            'uniqueid_namareport' => 'kts-valid-row',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Ngawi',
            'unit1' => 'Unit C',
            'nomor_rekening1' => 'KTS-VALID-1',
            'status_rekening1' => '1',
            'baki_debet1' => 10000000,
            'kolek' => '1',
            'umur_tunggakan' => 25,
            'tunggakan_pokok' => 500000,
            'tunggakan_bunga' => 100000,
        ],
        [
            'uniqueid_namareport' => 'kts-empty-norek',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Ngawi',
            'unit1' => 'Unit C',
            'nomor_rekening1' => '   ',
            'status_rekening1' => '1',
            'baki_debet1' => 10000000,
            'kolek' => '1',
            'umur_tunggakan' => 25,
            'tunggakan_pokok' => 500000,
            'tunggakan_bunga' => 100000,
        ],
        [
            'uniqueid_namareport' => 'kts-total-dummy-norek',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Ngawi',
            'unit1' => 'Unit C',
            'nomor_rekening1' => 'TOTAL UNIT C',
            'status_rekening1' => '1',
            'baki_debet1' => 10000000,
            'kolek' => '1',
            'umur_tunggakan' => 25,
            'tunggakan_pokok' => 500000,
            'tunggakan_bunga' => 100000,
        ],
        [
            'uniqueid_namareport' => 'kts-status-closed-2',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Ngawi',
            'unit1' => 'Unit C',
            'nomor_rekening1' => 'KTS-CLOSED-2',
            'status_rekening1' => '2',
            'baki_debet1' => 5000000,
            'kolek' => '2',
            'umur_tunggakan' => 0,
            'tunggakan_pokok' => 0,
            'tunggakan_bunga' => 0,
        ],
        [
            'uniqueid_namareport' => 'kts-zero-baki',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Ngawi',
            'unit1' => 'Unit C',
            'nomor_rekening1' => 'KTS-ZERO-BAKI',
            'status_rekening1' => '1',
            'baki_debet1' => 0,
            'kolek' => '2',
            'umur_tunggakan' => 0,
            'tunggakan_pokok' => 0,
            'tunggakan_bunga' => 0,
        ],
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/data?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Ngawi'],
            'refresh' => true,
        ]));

    $response->assertOk();
    $response->assertJsonPath('audit.mismatch_rows', 1);
    $response->assertJsonPath('summary_rows.0.mismatch_count', 1);
    $response->assertJsonPath('summary_rows.0.memburuk_count', 1);
    $response->assertJsonPath('summary_rows.0.outstanding_balance', 10000000);
});

it('detects positive arrears as mismatch even when umur tunggakan is zero, and categorizes restrukturisasi properly', function () {
    DB::table('daily_loan_dinamis')->insert([
        [
            'uniqueid_namareport' => 'kts-arrears-zero-umur',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Magetan',
            'unit1' => 'Unit M',
            'nomor_rekening1' => 'KTS-ARR-0',
            'status_rekening1' => '1',
            'baki_debet1' => 50000000,
            'kolek' => '1',
            'umur_tunggakan' => 0,
            'tunggakan_pokok' => 1500000,
            'tunggakan_bunga' => 500000,
            'tgl_akad_restruk' => null,
            'npl_method' => null,
            'flag_restruk' => null,
        ],
        [
            'uniqueid_namareport' => 'kts-restruk-under-90d',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Magetan',
            'unit1' => 'Unit M',
            'nomor_rekening1' => 'KTS-RESTRUK-30D',
            'status_rekening1' => '3',
            'baki_debet1' => 30000000,
            'kolek' => '2',
            'umur_tunggakan' => 0,
            'tunggakan_pokok' => 0,
            'tunggakan_bunga' => 0,
            'tgl_akad_restruk' => '2026-03-22', // 31 days ago (<= 90 days)
            'npl_method' => 'N',
            'flag_restruk' => 'Y',
        ],
        [
            'uniqueid_namareport' => 'kts-restruk-over-90d',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Magetan',
            'unit1' => 'Unit M',
            'nomor_rekening1' => 'KTS-RESTRUK-120D',
            'status_rekening1' => '3',
            'baki_debet1' => 20000000,
            'kolek' => '2',
            'umur_tunggakan' => 0,
            'tunggakan_pokok' => 0,
            'tunggakan_bunga' => 0,
            'tgl_akad_restruk' => '2025-12-01', // > 90 days ago
            'npl_method' => 'N',
            'flag_restruk' => 'Y',
        ],
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/data?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Magetan'],
            'refresh' => true,
        ]));

    $response->assertOk();
    $response->assertJsonPath('audit.mismatch_rows', 3);
    $response->assertJsonPath('summary_rows.0.mismatch_count', 3);
    $response->assertJsonPath('summary_rows.0.memburuk_count', 1);
    $response->assertJsonPath('summary_rows.0.belum_waktunya_penyesuaian_count', 1);
    $response->assertJsonPath('summary_rows.0.kolek_membaik_count', 1);

    // Test exact 1:1 match between summary and export
    $exportResponse = $this
        ->actingAs($user)
        ->get('/report/dashboard-pinjaman/kolek-tidak-sesuai/export?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Magetan'],
            'unit1' => 'Unit M',
        ]));

    $exportResponse->assertOk();

    $path = tempnam(sys_get_temp_dir(), 'kts_parity_export_') . '.xlsx';
    file_put_contents($path, $exportResponse->streamedContent());

    try {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $excelRows = $sheet->toArray(null, true, true, true);
    } finally {
        @unlink($path);
    }

    // Data rows exclude header row
    $dataRowCount = count($excelRows) - 1;
    expect($dataRowCount)->toBe(3);

    $headerRow = $excelRows[1];
    $norekCol = array_search('nomor_rekening1', $headerRow, true);
    expect($norekCol)->not->toBeFalse();

    $accounts = collect($excelRows)->skip(1)->pluck($norekCol)->all();
    expect($accounts)->toContain('KTS-ARR-0');
    expect($accounts)->toContain('KTS-RESTRUK-30D');
    expect($accounts)->toContain('KTS-RESTRUK-120D');
});

it('treats arrears falling due on the period date as lancar, not kolek tidak sesuai', function () {
    DB::table('daily_loan_dinamis')->insert([
        [
            'uniqueid_namareport' => 'kts-due-today',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Magetan',
            'unit1' => 'Unit M',
            'nomor_rekening1' => 'KTS-DUE-TODAY',
            'status_rekening1' => '1',
            'baki_debet1' => 700000000,
            'kolek' => '1',
            'umur_tunggakan' => 0,
            'tunggakan_pokok' => 698590572,
            'tunggakan_bunga' => 0,
            'tanggal_menunggak' => '2026-04-22',
            'npl_method' => 'Y',
            'flag_restruk' => 'N',
        ],
        [
            'uniqueid_namareport' => 'kts-stale-umur-zero',
            'periode' => '2026-04-22',
            'cabang1' => 'KC Magetan',
            'unit1' => 'Unit M',
            'nomor_rekening1' => 'KTS-STALE-UMUR',
            'status_rekening1' => '1',
            'baki_debet1' => 10000000,
            'kolek' => '1',
            'umur_tunggakan' => 0,
            'tunggakan_pokok' => 500000,
            'tunggakan_bunga' => 0,
            'tanggal_menunggak' => '2026-04-12',
            'npl_method' => 'Y',
            'flag_restruk' => 'N',
        ],
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/data?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Magetan'],
            'refresh' => true,
        ]));

    $response->assertOk();
    $response->assertJsonPath('audit.scanned_rows', 2);
    $response->assertJsonPath('audit.matched_rows', 1);
    $response->assertJsonPath('audit.mismatch_rows', 1);
    $response->assertJsonPath('summary_rows.0.memburuk_count', 1);
    $response->assertJsonPath('summary_rows.0.outstanding_balance', 10000000);
});

it('keeps restruk within 90 days as belum waktunya penyesuaian when only penalty remains', function () {
    DB::table('daily_loan_dinamis')->insert([
        'uniqueid_namareport' => 'kts-restruk-penalty-only',
        'periode' => '2026-04-22',
        'cabang1' => 'KC Magetan',
        'unit1' => 'Unit M',
        'nomor_rekening1' => 'KTS-RESTRUK-PENALTY',
        'status_rekening1' => '1',
        'baki_debet1' => 168117828,
        'kolek' => '2',
        'umur_tunggakan' => -29,
        'tunggakan_pokok' => 0,
        'tunggakan_bunga' => 0,
        'tunggakan_penalti' => 8,
        'tgl_akad_restruk' => '2026-03-28',
        'npl_method' => 'N',
        'flag_restruk' => 'Y',
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/data?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Magetan'],
            'refresh' => true,
        ]));

    $response->assertOk();
    $response->assertJsonPath('audit.mismatch_rows', 1);
    $response->assertJsonPath('summary_rows.0.belum_waktunya_penyesuaian_count', 1);
    $response->assertJsonPath('summary_rows.0.kolek_membaik_count', 0);
});

it('excludes penalty-only residue from small arrears summary and export', function () {
    seedSmallArrearsRows();

    DB::table('daily_loan_dinamis')->insert([
        'uniqueid_namareport' => 'curr-kc-mdn-penalty-only',
        'periode' => '2026-04-22',
        'cabang1' => 'KC Madiun',
        'unit1' => 'Unit A',
        'nomor_rekening1' => 'LN-PENALTY-ONLY',
        'umur_tunggakan' => 0,
        'kolek' => 1,
        'tunggakan_pokok' => 0,
        'tunggakan_bunga' => 0,
        'tunggakan_penalti' => 2500,
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/tunggakan-kecil/data?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Madiun'],
            'unit1' => ['Unit A'],
        ]));

    $response->assertOk();
    $response->assertJsonPath('rows.0.current', 1);
    $response->assertJsonPath('rows.0.total_tunggakan', 80000);

    $export = $this
        ->actingAs($user)
        ->get('/report/dashboard-pinjaman/tunggakan-kecil/export?' . http_build_query([
            'periode' => '2026-04-22',
            'cabang1' => ['KC Madiun'],
            'unit1' => ['Unit A'],
        ]));

    $path = tempnam(sys_get_temp_dir(), 'small_arrears_penalty_') . '.xlsx';
    file_put_contents($path, $export->streamedContent());

    try {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, true);
    } finally {
        @unlink($path);
    }

    expect(collect($rows)->skip(1)->pluck('D')->all())->not->toContain('LN-PENALTY-ONLY');
});

it('returns year to date mismatch points from exact month ends and the selected native position', function () {
    $base = [
        'periode' => '2026-01-31',
        'cabang1' => 'KC Madiun',
        'unit1' => 'Unit M',
        'status_rekening1' => '1',
        'baki_debet1' => 100.25,
        'kolek' => 1,
        'umur_tunggakan' => 10,
        'tunggakan_pokok' => 10,
        'tunggakan_bunga' => 0,
    ];
    foreach ([
        ['uniqueid_namareport' => 'ts-native-one', 'nomor_rekening1' => 'NATIVE-ONE'],
        ['uniqueid_namareport' => 'ts-native-two', 'nomor_rekening1' => 'NATIVE-TWO', 'baki_debet1' => 200.5, 'kolek' => null, 'kol_adk1' => '1'],
        ['uniqueid_namareport' => 'LW321PN:ts-mixed', 'nomor_rekening1' => 'LW-MIXED', 'baki_debet1' => 900],
        ['uniqueid_namareport' => 'ts-better', 'nomor_rekening1' => 'BETTER', 'kolek' => 3],
        ['uniqueid_namareport' => 'ts-waiting', 'nomor_rekening1' => 'WAITING', 'kolek' => 2, 'umur_tunggakan' => 0, 'tunggakan_pokok' => 0, 'flag_restruk' => 'Y', 'tgl_akad_restruk' => '2026-01-15'],
        ['uniqueid_namareport' => 'ts-closed', 'nomor_rekening1' => 'CLOSED', 'status_rekening1' => '2'],
        ['uniqueid_namareport' => 'ts-no-account', 'nomor_rekening1' => 'TOTAL SOURCE'],
        ['uniqueid_namareport' => 'ts-no-balance', 'nomor_rekening1' => 'ZERO', 'baki_debet1' => 0],
        ['uniqueid_namareport' => 'ts-other-branch', 'nomor_rekening1' => 'OTHER', 'cabang1' => 'KC Ngawi', 'baki_debet1' => 777],
        ['uniqueid_namareport' => 'ts-due-today', 'nomor_rekening1' => 'DUE-TODAY', 'periode' => '2026-02-28', 'umur_tunggakan' => 0, 'tanggal_menunggak' => '2026-02-28'],
        ['uniqueid_namareport' => 'LW321PN:ts-only', 'nomor_rekening1' => 'LW-ONLY', 'periode' => '2026-03-31'],
        ['uniqueid_namareport' => 'ts-other-date', 'nomor_rekening1' => 'OTHER-DATE', 'periode' => '2026-04-30', 'cabang1' => 'KC Ngawi'],
        ['uniqueid_namareport' => 'ts-not-month-end', 'nomor_rekening1' => 'EARLY-MAY', 'periode' => '2026-05-30', 'baki_debet1' => 9999],
        ['uniqueid_namareport' => 'ts-closed-date', 'nomor_rekening1' => 'CLOSED-DATE', 'periode' => '2026-06-30', 'status_rekening1' => '2'],
        ['uniqueid_namareport' => 'ts-last', 'nomor_rekening1' => 'LAST', 'periode' => '2026-10-21', 'baki_debet1' => 400.75],
        ['uniqueid_namareport' => 'ts-later-current', 'nomor_rekening1' => 'LATER', 'periode' => '2026-10-22', 'baki_debet1' => 9999],
        ['uniqueid_namareport' => 'ts-before', 'nomor_rekening1' => 'BEFORE', 'periode' => '2025-12-31', 'baki_debet1' => 9999],
        ['uniqueid_namareport' => 'ts-after', 'nomor_rekening1' => 'AFTER', 'periode' => '2026-11-01', 'baki_debet1' => 9999],
    ] as $row) {
        DB::table('daily_loan_dinamis')->insert(array_merge($base, $row));
    }

    $this->actingAs(User::factory()->create());
    $response = $this->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/timeseries?'.http_build_query([
        'periode' => '2026-10-21',
        'cabang1' => ['KC Madiun'],
    ]))->assertOk()->assertJsonPath('selected_period', '2026-10-21')->assertJsonPath('selected_branch', 'KC Madiun');
    $series = collect($response->json('timeseries'))->keyBy('month');
    expect($series)->toHaveCount(10);
    expect($series->keys()->first())->toBe('2026-01');
    expect($series->keys()->last())->toBe('2026-10');
    expect($series['2026-01']['period'])->toBe('2026-01-31');
    expect($series['2026-01']['available'])->toBeTrue();
    expect($series['2026-01']['memburuk_count'])->toBe(2);
    expect((float) $series['2026-01']['memburuk_balance'])->toBe(300.75);
    expect($series['2026-02']['period'])->toBe('2026-02-28');
    expect($series['2026-02']['available'])->toBeTrue();
    expect($series['2026-02']['memburuk_count'])->toBe(0);
    expect((float) $series['2026-02']['memburuk_balance'])->toBe(0.0);
    expect($series['2026-06']['available'])->toBeTrue();
    expect($series['2026-06']['memburuk_count'])->toBe(0);
    foreach (['2026-03', '2026-04', '2026-05'] as $missingMonth) {
        expect($series[$missingMonth]['available'])->toBeFalse();
        expect($series[$missingMonth]['memburuk_count'])->toBeNull();
        expect($series[$missingMonth]['memburuk_balance'])->toBeNull();
    }
    expect($series['2026-03']['period'])->toBeNull();
    expect($series['2026-05']['period'])->toBeNull();
    expect($series['2026-10']['period'])->toBe('2026-10-21');
    expect($series['2026-10']['memburuk_count'])->toBe(1);
    expect((float) $series['2026-10']['memburuk_balance'])->toBe(400.75);
    $this->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/data?'.http_build_query([
        'periode' => '2026-01-31', 'cabang1' => ['KC Madiun'],
    ]))->assertOk()->assertJsonPath('summary_rows.0.memburuk_count', 2)->assertJsonPath('summary_rows.0.memburuk_os', 300.75);
});

it('returns an empty mismatch timeseries when only normalized lw321pn source exists', function () {
    DB::table('daily_loan_dinamis')->insert([
        'uniqueid_namareport' => 'LW321PN:ts-empty',
        'periode' => '2026-10-18',
        'cabang1' => 'KC Madiun',
        'nomor_rekening1' => 'LW-ONLY',
        'status_rekening1' => '1',
        'kolek' => 1,
        'umur_tunggakan' => 10,
        'baki_debet1' => 100,
    ]);
    $this->actingAs(User::factory()->create())
        ->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/timeseries?periode=2026-10-18')
        ->assertOk()->assertJsonPath('selected_period', null)->assertJsonPath('timeseries', []);
});

it('enforces branch scope and isolates cached mismatch timeseries for area and restricted users', function () {
    $base = ['periode' => '2026-10-18', 'unit1' => 'Unit A', 'status_rekening1' => '1', 'kolek' => 1, 'umur_tunggakan' => 10, 'tunggakan_pokok' => 10];
    foreach ([
        ['uniqueid_namareport' => 'ts-scope-madiun', 'nomor_rekening1' => 'MDN', 'cabang1' => 'KC Madiun', 'baki_debet1' => 100],
        ['uniqueid_namareport' => 'ts-scope-ngawi', 'nomor_rekening1' => 'NGW', 'cabang1' => 'KC Ngawi', 'baki_debet1' => 200],
    ] as $row) {
        DB::table('daily_loan_dinamis')->insert(array_merge($base, $row));
    }
    $url = '/report/dashboard-pinjaman/kolek-tidak-sesuai/timeseries?periode=2026-10-18';
    $this->actingAs(User::factory()->create(['pn' => '90001234']))
        ->getJson($url)->assertOk()->assertJsonPath('timeseries.9.memburuk_count', 2)->assertJsonPath('timeseries.9.memburuk_balance', 300);
    $this->actingAs(User::factory()->create(['pn' => '0045']))
        ->getJson($url.'&'.http_build_query(['cabang1' => ['KC Ngawi']]))
        ->assertOk()->assertJsonPath('selected_branch', 'KC Madiun')
        ->assertJsonPath('timeseries.9.memburuk_count', 1)->assertJsonPath('timeseries.9.memburuk_balance', 100);
});

it('keeps worsening timeseries equal to the table across kolek thresholds and derived aging', function () {
    $base = [
        'periode' => '2026-10-22', 'cabang1' => 'KC Madiun', 'unit1' => 'Unit Boundary',
        'status_rekening1' => '1', 'baki_debet1' => 100.25, 'kolek' => 1,
        'umur_tunggakan' => 0, 'tunggakan_pokok' => 0, 'tunggakan_bunga' => 0,
    ];
    foreach ([1 => 0, 2 => 90, 3 => 120, 4 => 180] as $kolek => $threshold) {
        foreach ([$threshold, $threshold + 1] as $age) {
            DB::table('daily_loan_dinamis')->insert(array_merge($base, [
                'uniqueid_namareport' => "ts-boundary-{$kolek}-{$age}",
                'nomor_rekening1' => "BOUNDARY-{$kolek}-{$age}",
                'kolek' => $kolek, 'umur_tunggakan' => $age,
            ]));
        }
    }
    foreach ([
        ['id' => 'actual-five', 'kolek' => 5, 'umur_tunggakan' => 181],
        ['id' => 'null-age-derived', 'kolek' => 2, 'umur_tunggakan' => null, 'tunggakan_pokok' => 10, 'tanggal_menunggak' => '2026-06-01'],
        ['id' => 'zero-age-derived', 'kolek' => 3, 'umur_tunggakan' => 0, 'tunggakan_bunga' => 10, 'tanggal_menunggak' => '2026-05-01'],
        ['id' => 'negative-age-derived', 'kolek' => 4, 'umur_tunggakan' => -1, 'tunggakan_pokok' => 10, 'tanggal_menunggak' => '2026-01-01'],
        ['id' => 'null-age-no-arrears', 'kolek' => 1, 'umur_tunggakan' => null, 'tanggal_menunggak' => '2026-01-01'],
        ['id' => 'negative-no-arrears', 'kolek' => 1, 'umur_tunggakan' => -1, 'tanggal_menunggak' => '2026-01-01'],
        ['id' => 'fallback-kolek', 'kolek' => null, 'kol_adk1' => '2', 'umur_tunggakan' => 91],
        ['id' => 'text-kolek', 'kolek' => 'KOL 2', 'umur_tunggakan' => 91],
        ['id' => 'padded-kolek', 'kolek' => '02', 'umur_tunggakan' => 91],
        ['id' => 'improving', 'kolek' => 3, 'umur_tunggakan' => 10],
        ['id' => 'waiting', 'kolek' => 2, 'umur_tunggakan' => 0, 'flag_restruk' => 'Y', 'tgl_akad_restruk' => '2026-10-01'],
    ] as $row) {
        $id = $row['id'];
        unset($row['id']);
        DB::table('daily_loan_dinamis')->insert(array_merge($base, [
            'uniqueid_namareport' => 'ts-boundary-'.$id, 'nomor_rekening1' => 'BOUNDARY-'.$id,
        ], $row));
    }

    $this->actingAs(User::factory()->create());
    $query = http_build_query(['periode' => '2026-10-22', 'cabang1' => ['KC Madiun']]);
    $table = $this->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/data?'.$query)->assertOk();
    $series = $this->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/timeseries?'.$query)->assertOk();
    $tableCount = collect($table->json('summary_rows'))->sum('memburuk_count');
    $tableBalance = collect($table->json('summary_rows'))->sum('memburuk_os');
    expect($tableCount)->toBe(10);
    expect((float) $tableBalance)->toBe(1002.5);
    expect(collect($series->json('timeseries'))->sum('memburuk_count'))->toBe($tableCount);
    expect((float) collect($series->json('timeseries'))->sum('memburuk_balance'))->toBe((float) $tableBalance);
});

it('loads individual year to date mismatch points and isolates the latest position cache', function () {
    $base = ['cabang1' => 'KC Madiun', 'unit1' => 'Unit A', 'status_rekening1' => '1', 'kolek' => 1, 'umur_tunggakan' => 10];
    foreach ([
        ['uniqueid_namareport' => 'ts-point-january', 'nomor_rekening1' => 'JAN', 'periode' => '2026-01-31', 'baki_debet1' => 100],
        ['uniqueid_namareport' => 'ts-point-earlier', 'nomor_rekening1' => 'EARLIER', 'periode' => '2026-10-18', 'baki_debet1' => 200],
        ['uniqueid_namareport' => 'ts-point-latest', 'nomor_rekening1' => 'LATEST', 'periode' => '2026-10-21', 'baki_debet1' => 300],
        ['uniqueid_namareport' => 'LW321PN:ts-point-newest', 'nomor_rekening1' => 'LW', 'periode' => '2026-10-22', 'baki_debet1' => 9999],
    ] as $row) {
        DB::table('daily_loan_dinamis')->insert(array_merge($base, $row));
    }
    $this->actingAs(User::factory()->create());
    $url = '/report/dashboard-pinjaman/kolek-tidak-sesuai/timeseries?';
    $query = ['periode' => '2026-10-22', 'cabang1' => ['KC Madiun']];
    $full = $this->getJson($url.http_build_query($query))->assertOk()->assertJsonPath('selected_period', '2026-10-21');
    expect($full->json('timeseries'))->toHaveCount(10);
    $single = $this->getJson($url.http_build_query($query + ['point' => '2026-01']))->assertOk();
    expect($single->json('timeseries'))->toHaveCount(1);
    $single->assertJsonPath('timeseries.0.month', '2026-01')->assertJsonPath('timeseries.0.period', '2026-01-31')->assertJsonPath('timeseries.0.memburuk_balance', 100);
    $this->getJson($url.http_build_query(['periode' => '2026-10-18', 'cabang1' => ['KC Madiun'], 'point' => '2026-10']))
        ->assertOk()->assertJsonPath('timeseries.0.period', '2026-10-18')->assertJsonPath('timeseries.0.memburuk_balance', 200);
    $this->getJson($url.http_build_query($query + ['point' => '2026-10']))
        ->assertOk()->assertJsonPath('timeseries.0.period', '2026-10-21')->assertJsonPath('timeseries.0.memburuk_balance', 300);
    foreach (['2026-11', '2025-12', '2026-00', 'invalid'] as $invalidMonth) {
        $this->getJson($url.http_build_query($query + ['point' => $invalidMonth]))->assertUnprocessable();
    }
});

it('returns pending cached mismatch points without inventing zero and reuses calculated values', function () {
    $base = ['cabang1' => 'KC Madiun', 'unit1' => 'Unit A', 'status_rekening1' => '1', 'kolek' => 1, 'umur_tunggakan' => 10];
    foreach ([
        ['uniqueid_namareport' => 'ts-cached-january', 'nomor_rekening1' => 'JAN', 'periode' => '2026-01-31', 'baki_debet1' => 100.25],
        ['uniqueid_namareport' => 'ts-cached-october', 'nomor_rekening1' => 'OCT', 'periode' => '2026-10-21', 'baki_debet1' => 300.5],
    ] as $row) {
        DB::table('daily_loan_dinamis')->insert(array_merge($base, $row));
    }
    $this->actingAs(User::factory()->create());
    $url = '/report/dashboard-pinjaman/kolek-tidak-sesuai/timeseries?';
    $query = ['periode' => '2026-10-21', 'cabang1' => ['KC Madiun']];
    $cachedUrl = $url.http_build_query($query + ['cached' => 1]);
    $cold = $this->getJson($cachedUrl)->assertOk()->assertJsonPath('status', 'partial');
    $coldPoints = collect($cold->json('timeseries'))->keyBy('month');
    expect($coldPoints)->toHaveCount(10);
    foreach (['2026-01', '2026-10'] as $month) {
        expect($coldPoints[$month]['pending'])->toBeTrue();
        expect($coldPoints[$month]['memburuk_count'])->toBeNull();
        expect($coldPoints[$month]['memburuk_balance'])->toBeNull();
    }
    expect($coldPoints['2026-02']['pending'] ?? false)->toBeFalse();
    expect($coldPoints['2026-02']['available'])->toBeFalse();

    $this->getJson($url.http_build_query($query + ['point' => '2026-01']))->assertOk();
    $partial = $this->getJson($cachedUrl)->assertOk()->assertJsonPath('status', 'partial');
    $partialPoints = collect($partial->json('timeseries'))->keyBy('month');
    expect($partialPoints['2026-01']['pending'] ?? false)->toBeFalse();
    expect($partialPoints['2026-01']['memburuk_count'])->toBe(1);
    expect((float) $partialPoints['2026-01']['memburuk_balance'])->toBe(100.25);
    expect($partialPoints['2026-10']['pending'])->toBeTrue();

    $full = $this->getJson($url.http_build_query($query))->assertOk();
    $warm = $this->getJson($cachedUrl)->assertOk()->assertJsonPath('status', 'ready');
    expect(collect($warm->json('timeseries'))->contains(fn (array $point) => $point['pending'] ?? false))->toBeFalse();
    expect($warm->json('timeseries'))->toBe($full->json('timeseries'));
});

it('checks cached mismatch points without scanning every native source period', function () {
    $base = ['cabang1' => 'KC Madiun', 'unit1' => 'Unit A', 'status_rekening1' => '1', 'kolek' => 1, 'umur_tunggakan' => 10];
    foreach ([
        ['uniqueid_namareport' => 'ts-query-january', 'nomor_rekening1' => 'JAN', 'periode' => '2026-01-31', 'baki_debet1' => 100],
        ['uniqueid_namareport' => 'ts-query-october', 'nomor_rekening1' => 'OCT', 'periode' => '2026-10-21', 'baki_debet1' => 300],
    ] as $row) {
        DB::table('daily_loan_dinamis')->insert(array_merge($base, $row));
    }
    $this->actingAs(User::factory()->create());
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $url = '/report/dashboard-pinjaman/kolek-tidak-sesuai/timeseries?';
    $query = ['periode' => '2026-10-21', 'cabang1' => ['KC Madiun']];
    $this->getJson($url.http_build_query($query + ['cached' => 1]))->assertOk()->assertJsonPath('status', 'partial');
    $coldQueries = $queries;
    $this->getJson($url.http_build_query($query))->assertOk();
    $queries = [];
    $this->getJson($url.http_build_query($query + ['cached' => 1]))->assertOk()->assertJsonPath('status', 'ready');
    expect($coldQueries)->not->toBeEmpty();
    expect($queries)->not->toBeEmpty();
    foreach (array_merge($coldQueries, $queries) as $sql) {
        $normalized = strtolower(str_replace(['`', '"'], '', $sql));
        $isGlobalPeriodScan = preg_match('/select\s+distinct\s+periode\s+from\s+daily_loan_dinamis\b/', $normalized)
            && ! preg_match('/\bperiode\s*=\s*\?/', $normalized);
        $this->assertFalse((bool) $isGlobalPeriodScan, $sql);
    }
});

it('keeps historical mismatch months and branch totals aligned when the selected period changes', function () {
    $base = ['unit1' => 'Unit A', 'status_rekening1' => '1', 'kolek' => 1, 'umur_tunggakan' => 10];
    foreach (['KC Madiun' => 1, 'KC Ngawi' => 10] as $branch => $multiplier) {
        foreach (['2026-01-31' => 100, '2026-09-30' => 200, '2026-10-21' => 300] as $period => $balance) {
            $id = $branch.'-'.$period;
            DB::table('daily_loan_dinamis')->insert(array_merge($base, [
                'uniqueid_namareport' => $id, 'nomor_rekening1' => $id,
                'cabang1' => $branch, 'periode' => $period, 'baki_debet1' => $balance * $multiplier,
            ]));
        }
    }
    $this->actingAs(User::factory()->create());
    $url = '/report/dashboard-pinjaman/kolek-tidak-sesuai/timeseries?';
    foreach (['KC Madiun' => 1, 'KC Ngawi' => 10] as $branch => $multiplier) {
        foreach (['2026-10-21' => [10, 600], '2026-09-30' => [9, 300]] as $period => [$months, $balance]) {
            $response = $this->getJson($url.http_build_query(['periode' => $period, 'cabang1' => [$branch]]))
                ->assertOk()->assertJsonPath('selected_period', $period)->assertJsonPath('selected_branch', $branch);
            $points = collect($response->json('timeseries'));
            expect($points)->toHaveCount($months);
            expect($points->first()['month'])->toBe('2026-01');
            expect($points->last()['month'])->toBe(substr($period, 0, 7));
            expect($points->last()['period'])->toBe($period);
            expect($points->sum('memburuk_count'))->toBe($months === 10 ? 3 : 2);
            expect((float) $points->sum('memburuk_balance'))->toBe((float) ($balance * $multiplier));
        }
    }
});

it('includes padded source branch labels in mismatch points exactly as the existing table does', function () {
    $base = ['periode' => '2026-10-21', 'unit1' => 'Unit A', 'status_rekening1' => '1', 'kolek' => 1, 'umur_tunggakan' => 10];
    foreach ([' KC Madiun ' => 100.25, 'KC Madiun' => 200.5, 'KC Ngawi' => 999] as $branch => $balance) {
        DB::table('daily_loan_dinamis')->insert(array_merge($base, [
            'uniqueid_namareport' => 'ts-padded-'.$branch, 'nomor_rekening1' => 'PADDED-'.$branch,
            'cabang1' => $branch, 'baki_debet1' => $balance,
        ]));
    }
    $this->actingAs(User::factory()->create());
    $query = http_build_query(['periode' => '2026-10-21', 'cabang1' => ['KC Madiun']]);
    $table = $this->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/data?'.$query)->assertOk();
    $series = $this->getJson('/report/dashboard-pinjaman/kolek-tidak-sesuai/timeseries?'.$query.'&point=2026-10')->assertOk();
    expect(collect($table->json('summary_rows'))->sum('memburuk_count'))->toBe(2);
    expect((float) collect($table->json('summary_rows'))->sum('memburuk_os'))->toBe(300.75);
    $series->assertJsonPath('timeseries.0.memburuk_count', 2)->assertJsonPath('timeseries.0.memburuk_balance', 300.75);
});

