<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
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
        $table->decimal('tunggakan_pokok', 20, 2)->nullable();
        $table->decimal('tunggakan_bunga', 20, 2)->nullable();
        $table->decimal('tunggakan_penalti', 20, 2)->nullable();
        $table->timestamps();

        $table->index(['periode', 'cabang1', 'unit1'], 'idx_test_period_branch_unit');
    });

    Cache::flush();
});

afterEach(function () {
    Schema::dropIfExists('daily_loan_dinamis');
    Schema::dropIfExists('users');
});

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

it('aggregates current total and raw posisi counts by branch office', function () {
    seedSmallArrearsRows();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson(route('report.dashboard-pinjaman.tunggakan-kecil.data', [
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
    $response->assertJsonPath('total.total_tunggakan', 95000);
});

it('switches grouping to uker when unit filter is selected', function () {
    seedSmallArrearsRows();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson(route('report.dashboard-pinjaman.tunggakan-kecil.data', [
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
    $response->assertJsonPath('rows.0.total_tunggakan', 80000);
    $response->assertJsonPath('total.ytd', 1);
    $response->assertJsonPath('total.mtd', 1);
    $response->assertJsonPath('total.current', 1);
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
        ->getJson(route('report.dashboard-pinjaman.tunggakan-kecil.data', [
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

it('keeps user selected period and returns zero totals when no data exists on that date', function () {
    seedSmallArrearsRows();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson(route('report.dashboard-pinjaman.tunggakan-kecil.data', [
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
        ->getJson(route('report.dashboard-pinjaman.tunggakan-kecil.filters', [
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
        ->getJson(route('report.dashboard-pinjaman.tunggakan-kecil.filters', [
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
