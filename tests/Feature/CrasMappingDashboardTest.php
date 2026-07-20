<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $databasePath = database_path('testing-cras-mapping.sqlite');
    if (! file_exists($databasePath)) {
        touch($databasePath);
    }

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $databasePath);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
    Cache::flush();

    Schema::dropIfExists('users');
    Schema::dropIfExists('cras');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('pn')->unique();
        $table->string('role')->default('user');
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('cras', function (Blueprint $table): void {
        $table->string('cras_uuid')->primary();
        $table->date('cras_periode');
        $table->string('ket_kanca');
        $table->string('br_number');
        $table->string('ket_unit_kerja');
        $table->string('sektor_ekonomi')->nullable();
        $table->string('sub_sektor_ekonomi')->nullable();
        $table->string('loan_type')->nullable();
        $table->string('segmen')->nullable();
        $table->string('ket_produk_tiering')->nullable();
        $table->string('kualitas')->nullable();
        foreach ([
            'plafond',
            'baki_debet',
            'jumlah_debitur',
            'jumlah_rekening',
            'biaya_ckpn',
            'ckpn_mo',
            'realisasi_ph',
            'recovery_total',
            'saldo_ph',
            'tunggakan_bunga',
            'tunggakan_kecil',
            'tunggakan_pokok',
        ] as $column) {
            $table->text($column)->nullable();
        }
    });

    DB::table('cras')->insert([
        [
            'cras_uuid' => 'madiun-pertanian',
            'cras_periode' => '2026-06-30',
            'ket_kanca' => 'KC Madiun',
            'br_number' => '03883',
            'ket_unit_kerja' => 'UNIT CARUBAN',
            'sektor_ekonomi' => 'PERTANIAN',
            'sub_sektor_ekonomi' => 'PADI',
            'loan_type' => 'KUPEDES',
            'segmen' => 'MIKRO',
            'ket_produk_tiering' => 'TIER 1',
            'kualitas' => '1',
            'plafond' => '1,500,000',
            'baki_debet' => '1,250,000',
            'jumlah_debitur' => '2',
            'jumlah_rekening' => '3',
            'biaya_ckpn' => '50,000',
            'ckpn_mo' => '45,000',
            'realisasi_ph' => '20,000',
            'recovery_total' => '10,000',
            'saldo_ph' => '8,000',
            'tunggakan_bunga' => '5,000',
            'tunggakan_kecil' => '1,000',
            'tunggakan_pokok' => '15,000',
        ],
        [
            'cras_uuid' => 'ponorogo-perdagangan',
            'cras_periode' => '2026-06-30',
            'ket_kanca' => 'KC Ponorogo',
            'br_number' => '02204',
            'ket_unit_kerja' => 'KCP SUDIRMAN PONOROGO',
            'sektor_ekonomi' => 'PERDAGANGAN',
            'sub_sektor_ekonomi' => 'ECERAN',
            'loan_type' => 'KUR',
            'segmen' => 'MIKRO',
            'ket_produk_tiering' => 'TIER 2',
            'kualitas' => '2',
            'plafond' => '2,000,000',
            'baki_debet' => '1,800,000',
            'jumlah_debitur' => '1',
            'jumlah_rekening' => '1',
            'biaya_ckpn' => '70,000',
            'ckpn_mo' => '65,000',
            'realisasi_ph' => '30,000',
            'recovery_total' => '12,000',
            'saldo_ph' => '9,000',
            'tunggakan_bunga' => '7,000',
            'tunggakan_kecil' => '2,000',
            'tunggakan_pokok' => '20,000',
        ],
        [
            'cras_uuid' => 'magetan-industri',
            'cras_periode' => '2026-06-30',
            'ket_kanca' => 'KC Magetan',
            'br_number' => '03410',
            'ket_unit_kerja' => 'UNIT MAGETAN',
            'sektor_ekonomi' => 'INDUSTRI',
            'sub_sektor_ekonomi' => 'PENGOLAHAN',
            'loan_type' => 'KUPEDES',
            'segmen' => 'MIKRO',
            'ket_produk_tiering' => 'TIER 1',
            'kualitas' => '1',
            'plafond' => '900,000',
            'baki_debet' => '750,000',
            'jumlah_debitur' => '1',
            'jumlah_rekening' => '1',
            'biaya_ckpn' => '30,000',
            'ckpn_mo' => '25,000',
            'realisasi_ph' => '10,000',
            'recovery_total' => '5,000',
            'saldo_ph' => '4,000',
            'tunggakan_bunga' => '2,000',
            'tunggakan_kecil' => '500',
            'tunggakan_pokok' => '6,000',
        ],
    ]);
});

afterEach(function (): void {
    Cache::flush();
    Schema::dropIfExists('cras');
    Schema::dropIfExists('users');
    DB::purge('sqlite');
    @unlink(database_path('testing-cras-mapping.sqlite'));
});

it('renders the CRAS mapping workspace and navigation entry', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('report.dashboard-dana.market-share.mapping-cras'))
        ->assertOk()
        ->assertSee('Mapping Portofolio SSA CRAS')
        ->assertSee('Sektor Ekonomi')
        ->assertSee('Sub Sektor Ekonomi')
        ->assertSee('Loan Type')
        ->assertSee('Produk Tiering')
        ->assertSee('Tunggakan Pokok')
        ->assertSee('id="crasPortfolioMap"', false)
        ->assertSee('Mapping CRAS');
});

it('aggregates text metrics and applies all CRAS portfolio filters', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', [
        'wilayah' => 'madiun',
        'sektor' => 'PERTANIAN',
        'sub_sektor' => 'PADI',
        'loan_type' => 'KUPEDES',
        'segmen' => 'MIKRO',
        'produk_tiering' => 'TIER 1',
        'kualitas' => '1',
        'metric' => 'total_tunggakan',
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('ready', true)
        ->assertJsonPath('filters.selected.wilayah', 'madiun')
        ->assertJsonPath('filters.selected.sektor', 'PERTANIAN')
        ->assertJsonPath('heatmap.selected', 'total_tunggakan')
        ->assertJsonPath('coverage.source_row_count', 1)
        ->assertJsonPath('coverage.total_unit_count', 1)
        ->assertJsonPath('coverage.mapped_unit_count', 1)
        ->assertJsonPath('units.0.code', '03883')
        ->assertJsonPath('units.0.district_codes.0', '35.19.11')
        ->assertJsonPath('metrics.plafond', 1500000)
        ->assertJsonPath('metrics.baki_debet', 1250000)
        ->assertJsonPath('metrics.jumlah_debitur', 2)
        ->assertJsonPath('metrics.ckpn_mo', 45000)
        ->assertJsonPath('metrics.realisasi_ph', 20000)
        ->assertJsonPath('metrics.recovery_total', 10000)
        ->assertJsonPath('metrics.saldo_ph', 8000)
        ->assertJsonPath('metrics.tunggakan_bunga', 5000)
        ->assertJsonPath('metrics.tunggakan_kecil', 1000)
        ->assertJsonPath('metrics.tunggakan_pokok', 15000)
        ->assertJsonPath('metrics.total_tunggakan', 21000);
});

it('forces a restricted user to their own CRAS branch', function (): void {
    $user = User::factory()->create(['pn' => '0049']);

    $response = $this->actingAs($user)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', [
        'wilayah' => 'ponorogo',
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('ready', true)
        ->assertJsonPath('filters.selected.wilayah', 'magetan')
        ->assertJsonPath('coverage.source_row_count', 1)
        ->assertJsonPath('metrics.plafond', 900000)
        ->assertJsonPath('metrics.baki_debet', 750000)
        ->assertJsonPath('units.0.branch', 'KC Magetan');
});

it('keeps responsive map and table guardrails in the CRAS view', function (): void {
    $source = file_get_contents(resource_path('views/report/dashboard-dana-cras-mapping.blade.php'));

    expect($source)
        ->toContain('@media (max-width: 1199.98px)')
        ->toContain('@media (max-width: 767.98px)')
        ->toContain('@media (max-width: 340px)')
        ->toContain('overflow: auto')
        ->toContain('map.invalidateSize')
        ->toContain('window.L.map');
});
