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
        $table->string('status_rekening')->nullable();
        $table->string('produk')->nullable();
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
            'status_rekening' => 'AKTIF',
            'produk' => 'KUPEDES',
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
            'status_rekening' => 'AKTIF',
            'produk' => 'KUR MIKRO',
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
            'status_rekening' => 'AKTIF',
            'produk' => 'KUPEDES',
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
        [
            'cras_uuid' => 'ngawi-npl',
            'cras_periode' => '2026-06-30',
            'ket_kanca' => 'KC Ngawi',
            'br_number' => '06429',
            'ket_unit_kerja' => 'UNIT NGAWI',
            'status_rekening' => 'AKTIF',
            'produk' => 'KUPEDES',
            'sektor_ekonomi' => 'PERDAGANGAN',
            'sub_sektor_ekonomi' => 'GROSIR',
            'loan_type' => 'KUPEDES',
            'segmen' => 'MIKRO',
            'ket_produk_tiering' => 'TIER 2',
            'kualitas' => 'NPL',
            'plafond' => '2,700,000',
            'baki_debet' => '2,400,000',
            'jumlah_debitur' => '3',
            'jumlah_rekening' => '3',
            'biaya_ckpn' => '90,000',
            'ckpn_mo' => '85,000',
            'realisasi_ph' => '40,000',
            'recovery_total' => '18,000',
            'saldo_ph' => '15,000',
            'tunggakan_bunga' => '12,000',
            'tunggakan_kecil' => '3,000',
            'tunggakan_pokok' => '35,000',
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
        ->assertSee('Marketshare CRAS LPG')
        ->assertSee('Sektor Ekonomi')
        ->assertSee('Sub Sektor Ekonomi')
        ->assertSee('Loan Type')
        ->assertSee('Produk Tiering')
        ->assertSee('Tunggakan Pokok')
        ->assertSee('Fokus Peta dan Peringkat')
        ->assertSee('Filter Portofolio Lanjutan')
        ->assertSee('Wilayah yang Perlu Dilihat')
        ->assertSee('NPL Terbesar')
        ->assertSee('SML Terbesar')
        ->assertSee('id="crasPortfolioMap"', false)
        ->assertSee('Sector Acceptance Criteria LPG')
        ->assertSee('Marketshare CRAS LPG');
});

it('maps Micro and Small with segment-specific SAC colors and excludes Briguna Mikro', function (): void {
    DB::table('cras')->insert([
        [
            'cras_uuid' => 'lpg-micro-transport',
            'cras_periode' => '2026-05-31',
            'ket_kanca' => 'KC Madiun',
            'br_number' => '03883',
            'ket_unit_kerja' => 'UNIT CARUBAN',
            'status_rekening' => 'AKTIF',
            'produk' => 'KUPEDES',
            'sektor_ekonomi' => 'TRANSPORTASI',
            'sub_sektor_ekonomi' => 'Angkutan Jalan Raya',
            'loan_type' => 'KUPEDES',
            'segmen' => 'Micro',
            'ket_produk_tiering' => 'TIER 1',
            'kualitas' => '3',
            'plafond' => '1,500,000,000',
            'baki_debet' => '1,200,000,000',
            'jumlah_debitur' => '2',
            'jumlah_rekening' => '2',
        ],
        [
            'cras_uuid' => 'lpg-small-transport',
            'cras_periode' => '2026-05-31',
            'ket_kanca' => 'KC Madiun',
            'br_number' => '00045',
            'ket_unit_kerja' => 'KC MADIUN',
            'status_rekening' => 'AKTIF',
            'produk' => 'SMALL',
            'sektor_ekonomi' => 'TRANSPORTASI',
            'sub_sektor_ekonomi' => 'Angkutan Jalan Raya',
            'loan_type' => 'KMK',
            'segmen' => 'Small',
            'ket_produk_tiering' => 'KECIL',
            'kualitas' => '2',
            'plafond' => '4,500,000,000',
            'baki_debet' => '4,000,000,000',
            'jumlah_debitur' => '1',
            'jumlah_rekening' => '1',
        ],
        [
            'cras_uuid' => 'lpg-briguna-excluded',
            'cras_periode' => '2026-05-31',
            'ket_kanca' => 'KC Madiun',
            'br_number' => '03883',
            'ket_unit_kerja' => 'UNIT CARUBAN',
            'status_rekening' => 'AKTIF',
            'produk' => 'BRIGUNA MIKRO',
            'sektor_ekonomi' => 'TRANSPORTASI',
            'sub_sektor_ekonomi' => 'Angkutan Jalan Raya',
            'loan_type' => 'BRIGUNA',
            'segmen' => 'Micro',
            'ket_produk_tiering' => 'BRIGUNA MIKRO',
            'kualitas' => '1',
            'plafond' => '500,000,000',
            'baki_debet' => '450,000,000',
            'jumlah_debitur' => '1',
            'jumlah_rekening' => '1',
        ],
    ]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', [
        'periode' => '2026-05-31',
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('lpg.ready', true)
        ->assertJsonCount(2, 'lpg.rows')
        ->assertJsonPath('lpg.rows.0.segment', 'micro')
        ->assertJsonPath('lpg.rows.0.industry_sector', 'Transportasi')
        ->assertJsonPath('lpg.rows.0.industry_sub_sector', 'Angkutan darat')
        ->assertJsonPath('lpg.rows.0.color', 'kuning')
        ->assertJsonPath('lpg.rows.1.segment', 'small')
        ->assertJsonPath('lpg.rows.1.color', 'hijau_muda')
        ->assertJsonPath('lpg.coverage.eligible_rows', 2)
        ->assertJsonPath('lpg.coverage.mapped_rows', 2)
        ->assertJsonPath('lpg.coverage.mapping_ratio', 100)
        ->assertJsonPath('lpg.metrics.baki_debet', 5200000000)
        ->assertJsonPath('lpg.metrics.sml', 4000000000)
        ->assertJsonPath('lpg.metrics.npl', 1200000000)
        ->assertJsonCount(1, 'lpg.industry_rows')
        ->assertJsonPath('lpg.industry_rows.0.baki_debet', 5200000000)
        ->assertJsonPath('lpg.industry_rows.0.sml', 4000000000)
        ->assertJsonPath('lpg.industry_rows.0.npl', 1200000000)
        ->assertJsonPath('lpg.industry_rows.0.npl_ratio', 23.08)
        ->assertJsonCount(2, 'lpg.industry_rows.0.sac_categories');

    $this->actingAs($user)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', [
        'periode' => '2026-05-31',
        'lpg_color' => 'kuning',
    ]))
        ->assertOk()
        ->assertJsonPath('lpg.filters.selected.color', 'kuning')
        ->assertJsonCount(1, 'lpg.rows')
        ->assertJsonPath('lpg.rows.0.segment', 'micro')
        ->assertJsonPath('lpg.rows.0.color', 'kuning');

    $this->actingAs($user)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', [
        'periode' => '2026-05-31',
        'lpg_sort' => 'npl_ratio_desc',
    ]))
        ->assertOk()
        ->assertJsonPath('lpg.filters.selected.sort', 'npl_ratio_desc')
        ->assertJsonCount(1, 'lpg.industry_rows')
        ->assertJsonPath('lpg.industry_rows.0.npl_ratio', 23.08);
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
        ->assertJsonPath('filters.options.sub_sektor.1.value', 'PADI')
        ->assertJsonPath('filters.options.loan_type.1.value', 'KUPEDES')
        ->assertJsonPath('filters.options.kualitas.1.value', '1')
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

it('calculates NPL and SML exposure for regional mapping and ranking', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', [
        'wilayah' => 'all',
        'metric' => 'npl_os',
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('ready', true)
        ->assertJsonPath('heatmap.selected', 'npl_os')
        ->assertJsonPath('heatmap.options.0.key', 'baki_debet')
        ->assertJsonPath('heatmap.options.1.key', 'npl_os')
        ->assertJsonPath('heatmap.options.2.key', 'sml_os')
        ->assertJsonPath('coverage.source_row_count', 4)
        ->assertJsonPath('metrics.baki_debet', 6200000)
        ->assertJsonPath('metrics.npl_os', 2400000)
        ->assertJsonPath('metrics.sml_os', 1800000)
        ->assertJsonPath('metrics.npl_debitur', 3)
        ->assertJsonPath('metrics.sml_debitur', 1)
        ->assertJsonPath('metrics.npl_ratio', 38.71)
        ->assertJsonPath('metrics.sml_ratio', 29.03)
        ->assertJsonPath('units.2.branch', 'KC Ngawi')
        ->assertJsonPath('units.2.values.npl_os', 2400000)
        ->assertJsonPath('units.2.values.npl_ratio', 100)
        ->assertJsonPath('units.3.branch', 'KC Ponorogo')
        ->assertJsonPath('units.3.values.sml_os', 1800000)
        ->assertJsonPath('units.3.values.sml_ratio', 100);
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
        ->assertJsonCount(1, 'filters.options.wilayah')
        ->assertJsonPath('filters.options.wilayah.0.value', 'magetan')
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
        ->toContain('overflow-x: auto')
        ->toContain('overflow-y: visible')
        ->toContain('map.invalidateSize')
        ->toContain('window.L.map')
        ->toContain('data-cras-ranking-mode="district"')
        ->toContain('data-cras-view-trigger="mapping"')
        ->toContain('data-cras-view-trigger="sac"')
        ->toContain('data-cras-view-panel="mapping"')
        ->toContain('data-cras-view-panel="sac"')
        ->toContain('data-cras-lpg-sort="npl_ratio_desc"')
        ->toContain('data-cras-lpg-table')
        ->toContain('function sortedDistricts')
        ->toContain('function renderLpg')
        ->toContain('function renderInsights')
        ->toContain("npl: ['#fff1f3'")
        ->toContain("sml: ['#fff8e8'");
});
