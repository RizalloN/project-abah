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
        $table->string('nama_debitur1')->nullable();
        $table->date('tgl_realisasi')->nullable();
        $table->decimal('plafon', 20, 2)->nullable();
        $table->decimal('baki_debet1', 20, 2)->nullable();
        $table->integer('kolek')->nullable();
        $table->integer('umur_tunggakan')->nullable();
        $table->string('flag_restruk')->nullable();
        $table->decimal('tunggakan_pokok', 20, 2)->nullable();
        $table->decimal('tunggakan_bunga', 20, 2)->nullable();
        $table->decimal('tunggakan_penalti', 20, 2)->nullable();
        $table->timestamps();
    });

    Cache::flush();
});

afterEach(function () {
    Schema::dropIfExists('daily_loan_dinamis');
    Schema::dropIfExists('users');
});

function seedSixMonthArrearsRows(): void
{
    DB::table('daily_loan_dinamis')->insert([
        [
            'uniqueid_namareport' => 'six-month-qualify-sml2',
            'periode' => '2026-05-31',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-001',
            'nama_debitur1' => 'Debitur A',
            'tgl_realisasi' => '2025-11-15',
            'plafon' => 100000000,
            'baki_debet1' => 80000000,
            'kolek' => 2,
            'umur_tunggakan' => 45,
            'flag_restruk' => 'N',
            'tunggakan_pokok' => 1500000,
            'tunggakan_bunga' => 0,
            'tunggakan_penalti' => 0,
        ],
        [
            'uniqueid_namareport' => 'six-month-qualify-d2',
            'periode' => '2026-05-31',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-002',
            'nama_debitur1' => 'Debitur B',
            'tgl_realisasi' => '2025-11-30',
            'plafon' => 200000000,
            'baki_debet1' => 120000000,
            'kolek' => 4,
            'umur_tunggakan' => 160,
            'flag_restruk' => 'N',
            'tunggakan_pokok' => 2500000,
            'tunggakan_bunga' => 0,
            'tunggakan_penalti' => 0,
        ],
        [
            'uniqueid_namareport' => 'six-month-exclude-month',
            'periode' => '2026-05-31',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-003',
            'nama_debitur1' => 'Debitur C',
            'tgl_realisasi' => '2025-10-31',
            'plafon' => null,
            'baki_debet1' => null,
            'kolek' => 2,
            'umur_tunggakan' => 20,
            'flag_restruk' => null,
            'tunggakan_pokok' => 3000000,
            'tunggakan_bunga' => 0,
            'tunggakan_penalti' => 0,
        ],
        [
            'uniqueid_namareport' => 'six-month-exclude-kolek',
            'periode' => '2026-05-31',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-004',
            'nama_debitur1' => 'Debitur D',
            'tgl_realisasi' => '2025-11-20',
            'plafon' => null,
            'baki_debet1' => null,
            'kolek' => 1,
            'umur_tunggakan' => 0,
            'flag_restruk' => 'Y',
            'tunggakan_pokok' => 4000000,
            'tunggakan_bunga' => 0,
            'tunggakan_penalti' => 0,
        ],
        [
            'uniqueid_namareport' => 'six-month-qualify-total-tunggakan',
            'periode' => '2026-05-31',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-005',
            'nama_debitur1' => 'Debitur E',
            'tgl_realisasi' => '2025-11-25',
            'plafon' => null,
            'baki_debet1' => null,
            'kolek' => 3,
            'umur_tunggakan' => 90,
            'flag_restruk' => null,
            'tunggakan_pokok' => 0,
            'tunggakan_bunga' => 100000,
            'tunggakan_penalti' => 50000,
        ],
        [
            'uniqueid_namareport' => 'six-month-qualify-january',
            'periode' => '2026-05-31',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-006',
            'nama_debitur1' => 'Debitur F',
            'tgl_realisasi' => '2026-01-10',
            'plafon' => 70000000,
            'baki_debet1' => 60000000,
            'kolek' => 2,
            'umur_tunggakan' => 20,
            'flag_restruk' => null,
            'tunggakan_pokok' => 750000,
            'tunggakan_bunga' => 0,
            'tunggakan_penalti' => 0,
        ],
        [
            'uniqueid_namareport' => 'six-month-qualify-april',
            'periode' => '2026-05-31',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-007',
            'nama_debitur1' => 'Debitur G',
            'tgl_realisasi' => '2026-04-30',
            'plafon' => 60000000,
            'baki_debet1' => 50000000,
            'kolek' => 3,
            'umur_tunggakan' => 95,
            'flag_restruk' => null,
            'tunggakan_pokok' => 500000,
            'tunggakan_bunga' => 0,
            'tunggakan_penalti' => 0,
        ],
        [
            'uniqueid_namareport' => 'six-month-qualify-current-month',
            'periode' => '2026-05-31',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
            'nomor_rekening1' => 'LN-008',
            'nama_debitur1' => 'Debitur H',
            'tgl_realisasi' => '2026-05-01',
            'plafon' => 50000000,
            'baki_debet1' => 40000000,
            'kolek' => 2,
            'umur_tunggakan' => 35,
            'flag_restruk' => null,
            'tunggakan_pokok' => 250000,
            'tunggakan_bunga' => 0,
            'tunggakan_penalti' => 0,
        ],
    ]);
}

it('filters six month realization arrears across m six through selected period and maps kolek detail', function () {
    seedSixMonthArrearsRows();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/report/dashboard-pinjaman/realisasi-6-bulan-menunggak/data?' . http_build_query([
            'periode' => '2026-05-31',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
        ]));

    $response->assertOk();
    $response->assertJsonPath('selected_period', '2026-05-31');
    $response->assertJsonPath('target_month_label', 'November 2025 - May 2026');
    $response->assertJsonPath('summary.debitur', 6);
    $response->assertJsonPath('summary.outstanding', 350000000);
    $response->assertJsonPath('summary.total_tunggakan', 5650000);
    $response->assertJsonPath('rows.0.nomor_rekening1', 'LN-001');
    $response->assertJsonPath('rows.0.kolek_detail', 'SML 2');
    $response->assertJsonPath('rows.1.nomor_rekening1', 'LN-002');
    $response->assertJsonPath('rows.1.kolek_detail', 'D2');
    $response->assertJsonPath('rows.2.nomor_rekening1', 'LN-005');
    $response->assertJsonPath('rows.2.total_tunggakan', 150000);
    $response->assertJsonPath('rows.3.nomor_rekening1', 'LN-006');
    $response->assertJsonPath('rows.3.kolek_detail', 'SML 1');
    $response->assertJsonPath('rows.4.nomor_rekening1', 'LN-007');
    $response->assertJsonPath('rows.4.kolek_detail', 'KL');
    $response->assertJsonPath('rows.5.nomor_rekening1', 'LN-008');

    $accounts = collect($response->json('rows'))->pluck('nomor_rekening1')->all();
    expect($accounts)->toBe(['LN-001', 'LN-002', 'LN-005', 'LN-006', 'LN-007', 'LN-008']);
});

it('streams six month arrears export with kolek detail as the last column', function () {
    seedSixMonthArrearsRows();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/report/dashboard-pinjaman/realisasi-6-bulan-menunggak/export?' . http_build_query([
            'periode' => '2026-05-31',
            'cabang1' => 'KC Madiun',
            'unit1' => 'Unit A',
        ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('content-disposition'))->toContain('realisasi-6-bulan-menunggak_20260531_KC-Madiun_Unit-A.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'six_month_arrears_export_') . '.xlsx';
    file_put_contents($path, $response->streamedContent());

    try {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
    } finally {
        @unlink($path);
    }

    $headers = array_values($rows[1]);
    expect(end($headers))->toBe('kolek_detail');
    expect($headers)->toContain('total_tunggakan');
    expect($headers)->toContain('bulan_realisasi_target');
    expect(collect($rows)->skip(1)->pluck('D')->all())->toContain('LN-001');
    expect(collect($rows)->skip(1)->pluck('D')->all())->toContain('LN-002');
});
