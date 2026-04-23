<?php

uses(\Tests\TestCase::class);

use App\Support\ReportSnapshotBuilder;
use App\Support\DashboardPinjamanChartPeriodikService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Cache::flush();

    Schema::dropIfExists('daily_loan_dinamis');
    Schema::dropIfExists('loan_type');

    Schema::create('loan_type', function (Blueprint $table) {
        $table->string('uniqueid_namareport', 255)->primary();
        $table->string('loan_type', 100);
        $table->string('pola_pembayaran', 150)->nullable();
        $table->timestamps();
    });

    Schema::create('daily_loan_dinamis', function (Blueprint $table) {
        $table->string('uniqueid_namareport', 255)->primary();
        $table->date('periode')->index();
        $table->string('cabang1', 150)->nullable()->index();
        $table->string('unit1', 150)->nullable()->index();
        $table->string('branch1', 180)->nullable()->index();
        $table->string('ln_type', 100)->nullable();
        $table->decimal('baki_debet1', 20, 2)->default(0);
        $table->string('segmen_dashboard', 100)->nullable();
        $table->string('produk_dashboard', 150)->nullable();
        $table->string('nomor_rekening1', 50)->nullable();
        $table->timestamps();
    });

    Schema::create('dashboard_pinjaman_chart_periodik_snapshots', function (Blueprint $table) {
        $table->string('uniqueid_dpcs', 255)->primary();
        $table->date('periode')->index();
        $table->string('source_uniqueid_namareport', 255)->nullable();
        $table->string('account_number', 50)->nullable();
        $table->decimal('baki_debet1', 20, 2)->default(0);
        $table->string('ln_type', 100)->nullable();
        $table->string('loan_type', 100)->nullable();
        $table->string('pola_pembayaran', 150)->nullable();
        $table->string('segmen_dashboard', 100)->nullable();
        $table->string('produk_dashboard', 150)->nullable();
        $table->string('cabang1', 150)->nullable()->index();
        $table->string('unit1', 150)->nullable()->index();
        $table->string('branch1', 180)->nullable()->index();
        $table->timestamps();
        $table->index(['periode', 'cabang1', 'branch1', 'unit1'], 'idx_dpcp_period_cabang_branch_unit');
    });

    DB::table('loan_type')->insert([
        ['uniqueid_namareport' => 'lt-01', 'loan_type' => 'LT01', 'pola_pembayaran' => 'BULANAN', 'created_at' => now(), 'updated_at' => now()],
        ['uniqueid_namareport' => 'lt-02', 'loan_type' => 'LT02', 'pola_pembayaran' => 'MUSIMAN', 'created_at' => now(), 'updated_at' => now()],
        ['uniqueid_namareport' => 'lt-03', 'loan_type' => 'LT03', 'pola_pembayaran' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $rows = [
        ['uniqueid_namareport' => 'dld-01', 'periode' => '2026-04-15', 'cabang1' => 'KC PONOROGO', 'unit1' => 'NGRAYUN', 'branch1' => '3887', 'ln_type' => 'LT01', 'baki_debet1' => 1000, 'segmen_dashboard' => 'MICRO', 'produk_dashboard' => 'KUPEDES', 'nomor_rekening1' => 'ACC-001', 'created_at' => now(), 'updated_at' => now()],
        ['uniqueid_namareport' => 'dld-02', 'periode' => '2026-04-16', 'cabang1' => 'KC PONOROGO', 'unit1' => 'NGRAYUN', 'branch1' => '3887', 'ln_type' => 'LT02', 'baki_debet1' => 2000, 'segmen_dashboard' => 'MICRO', 'produk_dashboard' => 'KUPEDES', 'nomor_rekening1' => 'ACC-002', 'created_at' => now(), 'updated_at' => now()],
        ['uniqueid_namareport' => 'dld-03', 'periode' => '2026-04-17', 'cabang1' => 'KC PONOROGO', 'unit1' => 'NGRAYUN', 'branch1' => '3887', 'ln_type' => 'LT01', 'baki_debet1' => 3000, 'segmen_dashboard' => 'MICRO', 'produk_dashboard' => 'KUPEDES', 'nomor_rekening1' => 'ACC-003', 'created_at' => now(), 'updated_at' => now()],
        ['uniqueid_namareport' => 'dld-04', 'periode' => '2026-04-17', 'cabang1' => 'KC PONOROGO', 'unit1' => 'NGRAYUN', 'branch1' => '3887', 'ln_type' => 'LT01', 'baki_debet1' => 4000, 'segmen_dashboard' => 'MICRO', 'produk_dashboard' => 'KUPEDES', 'nomor_rekening1' => 'ACC-004', 'created_at' => now(), 'updated_at' => now()],
        ['uniqueid_namareport' => 'dld-05', 'periode' => '2026-04-18', 'cabang1' => 'KC PONOROGO', 'unit1' => 'NGRAYUN', 'branch1' => '3887', 'ln_type' => 'LT03', 'baki_debet1' => 5000, 'segmen_dashboard' => 'MICRO', 'produk_dashboard' => 'KUPEDES', 'nomor_rekening1' => 'ACC-005', 'created_at' => now(), 'updated_at' => now()],
        ['uniqueid_namareport' => 'dld-06', 'periode' => '2026-04-19', 'cabang1' => 'KC MADIUN', 'unit1' => 'BALEREJO', 'branch1' => '3883', 'ln_type' => 'LT01', 'baki_debet1' => 6000, 'segmen_dashboard' => 'SME', 'produk_dashboard' => 'BRIGUNA-KONSUMER', 'nomor_rekening1' => 'ACC-006', 'created_at' => now(), 'updated_at' => now()],
        ['uniqueid_namareport' => 'dld-07', 'periode' => '2026-04-20', 'cabang1' => 'KC PONOROGO', 'unit1' => 'NGRAYUN', 'branch1' => '3887', 'ln_type' => 'LT01', 'baki_debet1' => 7000, 'segmen_dashboard' => 'MICRO', 'produk_dashboard' => 'KUPEDES', 'nomor_rekening1' => 'ACC-007', 'created_at' => now(), 'updated_at' => now()],
        ['uniqueid_namareport' => 'dld-08', 'periode' => '2026-04-20', 'cabang1' => 'KC PONOROGO', 'unit1' => 'NGRAYUN', 'branch1' => '3887', 'ln_type' => 'LT02', 'baki_debet1' => 8000, 'segmen_dashboard' => 'MICRO', 'produk_dashboard' => 'KUPEDES', 'nomor_rekening1' => 'ACC-008', 'created_at' => now(), 'updated_at' => now()],
        ['uniqueid_namareport' => 'dld-09', 'periode' => '2026-04-20', 'cabang1' => 'KC PONOROGO', 'unit1' => 'NGRAYUN', 'branch1' => '3887', 'ln_type' => 'LT02', 'baki_debet1' => 9000, 'segmen_dashboard' => 'MICRO', 'produk_dashboard' => 'KUPEDES', 'nomor_rekening1' => 'ACC-009', 'created_at' => now(), 'updated_at' => now()],
        ['uniqueid_namareport' => 'dld-10', 'periode' => '2026-04-20', 'cabang1' => 'KC PONOROGO', 'unit1' => 'PONOROGO', 'branch1' => '3890', 'ln_type' => 'LT03', 'baki_debet1' => 10000, 'segmen_dashboard' => 'MICRO', 'produk_dashboard' => 'KUPEDES', 'nomor_rekening1' => 'ACC-010', 'created_at' => now(), 'updated_at' => now()],
        ['uniqueid_namareport' => 'dld-11', 'periode' => '2026-04-21', 'cabang1' => 'KC MADIUN', 'unit1' => 'BALEREJO', 'branch1' => '3883', 'ln_type' => 'LT02', 'baki_debet1' => 11000, 'segmen_dashboard' => 'SME', 'produk_dashboard' => 'BRIGUNA-KONSUMER', 'nomor_rekening1' => 'ACC-011', 'created_at' => now(), 'updated_at' => now()],
    ];

    DB::table('daily_loan_dinamis')->insert($rows);

    app(ReportSnapshotBuilder::class)->rebuildChartPeriodik(null, true);
    Schema::dropIfExists('daily_loan_dinamis');
    Schema::dropIfExists('loan_type');
});

afterEach(function () {
    Schema::dropIfExists('daily_loan_dinamis');
    Schema::dropIfExists('loan_type');
    Schema::dropIfExists('dashboard_pinjaman_chart_periodik_snapshots');
    Cache::flush();
});

it('builds the periodik payload with Area 6 default scope and snapshot-based unit options', function () {
    $service = app(DashboardPinjamanChartPeriodikService::class);

    expect(DB::table('dashboard_pinjaman_chart_periodik_snapshots')->count())->toBeGreaterThan(0);

    $payload = $service->buildIndexPayload(null);

    expect($payload['selected_branch'])->toBe('all');
    expect($payload['selected_branch_label'])->toBe('Area 6 - All');
    expect($payload['selected_period'])->toBe('2026-04-21');
    expect($payload['selected_period_label'])->toBe('21/04/2026');
    expect($payload['branch_options'])->toHaveCount(5);
    expect($payload['unit_options'])->not()->toBeEmpty();
    expect(collect($payload['unit_options'])->pluck('value')->all())->toContain('KC MADIUN||BALEREJO');
});

it('builds unit options from the snapshot for the requested period', function () {
    $service = app(DashboardPinjamanChartPeriodikService::class);

    $payload = $service->buildFilterPayload('2026-04-20', 'KC PONOROGO');

    expect($payload['selected_period'])->toBe('2026-04-20');
    expect(collect($payload['unit_options'])->pluck('value')->all())->toBe([
        'KC PONOROGO||NGRAYUN',
        'KC PONOROGO||PONOROGO',
    ]);
});

it('returns unit options and chart counts for a selected branch and unit', function () {
    $service = app(DashboardPinjamanChartPeriodikService::class);

    $filters = $service->buildFilterPayload('2026-04-20', 'KC PONOROGO');

    expect($filters['selected_branch'])->toBe('KC PONOROGO');
    expect($filters['selected_branch_label'])->toBe('KC PONOROGO');
    expect(collect($filters['unit_options'])->pluck('value')->all())->toBe([
        'KC PONOROGO||NGRAYUN',
        'KC PONOROGO||PONOROGO',
    ]);

    $chart = $service->buildChartPayload('2026-04-20', 'KC PONOROGO', ['KC PONOROGO||NGRAYUN']);

    expect($chart['selected_period'])->toBe('2026-04-20');
    expect($chart['selected_branch'])->toBe('KC PONOROGO');
    expect($chart['selected_unit_label'])->toBe('NGRAYUN');
    expect($chart['summary']['total_rekening'])->toBe(3);
    expect($chart['summary']['pattern_count'])->toBe(2);
    expect($chart['summary']['top_pattern'])->toBe('MUSIMAN');
    expect($chart['summary']['top_pattern_count'])->toBe(2);
    expect($chart['trend']['labels'])->toBe([
        '15/04/2026',
        '16/04/2026',
        '17/04/2026',
        '18/04/2026',
        '19/04/2026',
        '20/04/2026',
    ]);
    expect($chart['pie']['labels'])->toBe([
        'MUSIMAN',
        'BULANAN',
    ]);
    expect($chart['pie']['values'])->toBe([2, 1]);
    expect(collect($chart['trend']['datasets'])->firstWhere('label', 'BULANAN')['data'])->toBe([1, 0, 2, 0, 0, 1]);
    expect(collect($chart['trend']['datasets'])->firstWhere('label', 'MUSIMAN')['data'])->toBe([0, 1, 0, 0, 0, 2]);
});

it('normalizes composite unit keys from the request format', function () {
    $service = app(DashboardPinjamanChartPeriodikService::class);

    $chart = $service->buildChartPayload('2026-04-20', null, ['KC PONOROGO||NGRAYUN']);

    expect($chart['selected_units'])->toHaveCount(1);
    expect($chart['selected_units'][0]['branch'])->toBe('KC PONOROGO');
    expect($chart['selected_units'][0]['unit'])->toBe('NGRAYUN');
    expect($chart['summary']['branch_count'])->toBe(4);
    expect($chart['summary']['unit_count'])->toBe(1);
});
