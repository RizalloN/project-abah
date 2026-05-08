<?php

use App\Models\User;
use App\Services\Reports\KinerjaPtpReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    useKinerjaPtpSqliteConnection();

    Schema::dropIfExists('lw321_npd');
    Schema::dropIfExists('lw321_npdd');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('pn')->unique();
        $table->string('role')->default('user');
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    createKinerjaPtpTable('lw321_npd', 'm_min_1_os');
    createKinerjaPtpTable('lw321_npdd', 'os');
});

afterEach(function (): void {
    useKinerjaPtpSqliteConnection();

    Schema::dropIfExists('lw321_npd');
    Schema::dropIfExists('lw321_npdd');
    Schema::dropIfExists('users');
});

function useKinerjaPtpSqliteConnection(): void
{
    $path = database_path('testing-kinerja-ptp.sqlite');

    if (!file_exists($path)) {
        touch($path);
    }

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $path);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
}

function createKinerjaPtpTable(string $tableName, string $amountColumn): void
{
    Schema::create($tableName, function (Blueprint $table) use ($amountColumn): void {
        $table->string('uniqueid_namareport')->primary();
        $table->date('periode')->nullable();
        $table->string('billing')->nullable();
        $table->string('kanca')->nullable();
        $table->string('bc')->nullable();
        $table->string('mbm')->nullable();
        $table->string('uker')->nullable();
        $table->string('mantri')->nullable();
        $table->string('no_rekening')->nullable();
        $table->decimal($amountColumn, 22, 2)->nullable();
        $table->decimal('wba', 22, 2)->nullable();
        $table->string('now_kol')->nullable();
        $table->decimal('now_os', 22, 2)->nullable();
        $table->string('ptp')->nullable();
    });
}

function seedKinerjaPtpRows(): void
{
    DB::table('lw321_npd')->insert([
        ptpRow('npd-1', 'Belum', 'Tetap', '1', 'UNIT A', 1000, 100, 900),
        ptpRow('npd-2', 'Today', 'Today', '1', 'UNIT A', 2000, 200, 2000),
        ptpRow('npd-3', 'Sudah', 'Membaik', 'Lunas', 'UNIT A', 3000, 300, 0),
        ptpRow('npd-4', 'Sudah', 'Tetap', '1', 'UNIT A', 4000, 400, 3900),
        ptpRow('npd-5', 'Sudah', 'Memburuk', '2', 'UNIT A', 5000, 500, 4800),
        ptpRow('npd-6', 'Sudah', 'Membaik', 'Lunas', 'KC TEST', 9999, 999, 0),
    ]);

    DB::table('lw321_npdd')->insert([
        ptpRow('npdd-1', 'Sudah', 'LUNAS', 'Lunas', 'UNIT B', 7000000000, 700000000, 0, 'lw321_npdd'),
    ]);
}

function ptpRow(
    string $id,
    string $billing,
    string $ptp,
    string $nowKol,
    string $uker,
    int $amount,
    int $wba,
    int $nowOs,
    string $table = 'lw321_npd'
): array {
    $amountColumn = $table === 'lw321_npdd' ? 'os' : 'm_min_1_os';

    return [
        'uniqueid_namareport' => $id,
        'periode' => '2026-05-06',
        'billing' => $billing,
        'kanca' => 'KC Madiun',
        'bc' => '3883',
        'mbm' => 'MBM One',
        'uker' => $uker,
        'mantri' => 'Mantri One',
        'no_rekening' => 'REK-' . $id,
        $amountColumn => $amount,
        'wba' => $wba,
        'now_kol' => $nowKol,
        'now_os' => $nowOs,
        'ptp' => $ptp,
    ];
}

it('aggregates kinerja ptp npd by mbm using the requested billing buckets', function (): void {
    seedKinerjaPtpRows();

    $service = app(KinerjaPtpReportService::class);
    $payload = $service->payload('npd', 'per_mbm', '2026-05-06');
    $row = $payload['rows']->first();

    expect($row['bo'])->toBe('KC Madiun')
        ->and($row['mbm'])->toBe('MBM One')
        ->and((int) $row['total_rek'])->toBe(5)
        ->and((float) $row['total_rupiah'])->toBe(15000.0)
        ->and((float) $row['total_runoff'])->toBe(1500.0)
        ->and((int) $row['sudah_billing_rek'])->toBe(3)
        ->and((float) $row['sudah_billing_rupiah'])->toBe(12000.0)
        ->and((float) $row['sudah_billing_runoff'])->toBe(1400.0)
        ->and((int) $row['belum_muncul_rek'])->toBe(2)
        ->and((float) $row['belum_muncul_rupiah'])->toBe(3000.0)
        ->and((float) $row['belum_muncul_runoff'])->toBe(100.0)
        ->and((int) $row['sudah_bayar_rek'])->toBe(2)
        ->and((float) $row['sudah_bayar_rupiah'])->toBe(7200.0)
        ->and((int) $row['belum_bayar_rek'])->toBe(1)
        ->and((float) $row['belum_bayar_rupiah'])->toBe(4800.0)
        ->and((float) $row['success_rate'])->toBe(60.0)
        ->and((int) $row['today_rek'])->toBe(1)
        ->and((float) $row['today_rupiah'])->toBe(2000.0);
});

it('renders the kinerja ptp view with npdd selector', function (): void {
    seedKinerjaPtpRows();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/report/dashboard-pinjaman/kinerja-ptp?' . http_build_query([
            'jenis' => 'npdd',
            'level' => 'per_uker',
            'periode' => '2026-05-06',
        ]))
        ->assertOk()
        ->assertSee('Kinerja PTP')
        ->assertSee('PTP NPDD Micro')
        ->assertSee('UNIT B')
        ->assertSee('7.000');
});
