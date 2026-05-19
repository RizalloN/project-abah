<?php

use App\Services\Reports\BrilinkReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Cache::flush();

    Schema::dropIfExists('brilink_web_laporan_summary_transaksi_brilink_web');
    Schema::dropIfExists('casa_brilink_web');
    Schema::dropIfExists('casa_brilink_edc');
    Schema::dropIfExists('rka');

    Schema::create('brilink_web_laporan_summary_transaksi_brilink_web', function (Blueprint $table): void {
        $table->id();
        $table->string('cabang')->nullable();
        $table->string('uker')->nullable();
        $table->string('periode')->nullable();
        $table->string('merchant_name')->nullable();
        $table->string('merchant_code')->nullable();
        $table->string('outlet_code')->nullable();
        $table->bigInteger('total_transaksi')->nullable();
        $table->decimal('total_nominal', 18, 2)->nullable();
        $table->decimal('total_fee', 18, 2)->nullable();
        $table->decimal('total_fee_bri', 18, 2)->nullable();
    });

    foreach (['casa_brilink_web', 'casa_brilink_edc'] as $tableName) {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->date('periode')->nullable();
            $table->string('mbdesc')->nullable();
            $table->string('brdesc')->nullable();
            $table->decimal('jml_nominal_casa', 18, 2)->nullable();
        });
    }

    Schema::create('rka', function (Blueprint $table): void {
        $table->string('kanca')->nullable();
        $table->string('desc_uker')->nullable();
        $table->string('mata_anggaran')->nullable();
        $table->decimal('may', 18, 2)->nullable();
    });
});

it('returns active brilink user summary from merchants with monthly nominal at least fifty thousand', function (): void {
    DB::table('brilink_web_laporan_summary_transaksi_brilink_web')->insert([
        [
            'cabang' => 'KC MADIUN',
            'uker' => 'UNIT A',
            'periode' => 'May 2026',
            'merchant_name' => 'Merchant Aktif A',
            'merchant_code' => 'M001',
            'outlet_code' => 'O001',
            'total_transaksi' => 5,
            'total_nominal' => 50000,
            'total_fee' => 0,
            'total_fee_bri' => 0,
        ],
        [
            'cabang' => 'KC MADIUN',
            'uker' => 'UNIT A',
            'periode' => 'May 2026',
            'merchant_name' => 'Merchant Belum Aktif',
            'merchant_code' => 'M002',
            'outlet_code' => 'O002',
            'total_transaksi' => 3,
            'total_nominal' => 49999,
            'total_fee' => 0,
            'total_fee_bri' => 0,
        ],
        [
            'cabang' => 'KC MADIUN',
            'uker' => 'UNIT B',
            'periode' => 'May 2026',
            'merchant_name' => 'Merchant Aktif B',
            'merchant_code' => 'M003',
            'outlet_code' => 'O003',
            'total_transaksi' => 8,
            'total_nominal' => 75000,
            'total_fee' => 0,
            'total_fee_bri' => 0,
        ],
    ]);

    $request = Request::create('/report/data', 'POST', [
        'periode_bulan' => '2026-05',
        'branch_office' => ['KC MADIUN'],
        'tab' => 'brilink',
    ]);

    $payload = app(BrilinkReportService::class)->handle($request)->getData(true);

    expect($payload['status'])->toBe('success')
        ->and($payload['active_user_summary'])->toMatchArray([
            'count' => 2,
            'threshold' => 50000,
            'period' => 'May 2026',
            'scope' => 'Cabang terpilih',
        ])
        ->and($payload['total']['agen']['curr'])->toBe(3)
        ->and($payload['total']['active_user']['curr'])->toBe(2)
        ->and(collect($payload['data'])->pluck('active_user.curr', 'branch')->all())->toBe([
            'UNIT A' => 1,
            'UNIT B' => 1,
        ]);
});
