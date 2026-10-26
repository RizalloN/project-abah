<?php

use App\Models\User;
use App\Support\SectorPotentialService;
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
    $this->app->instance(SectorPotentialService::class, new class extends SectorPotentialService
    {
        public function payload(?string $branchKey = null): array
        {
            return ['ready' => false, 'period' => null, 'units' => [], 'totals' => []];
        }
    });

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
        $table->string('flag_movement_kualitas')->nullable();
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

it('uses the parent sector population of each UKER for filtered subsector coverage', function (): void {
    DB::table('cras')->where('cras_uuid', 'madiun-pertanian')->update([
        'cras_periode' => '2026-09-30', 'sektor_ekonomi' => 'PERDAGANGAN BESAR DAN ECERAN',
        'sub_sektor_ekonomi' => 'SUBSEKTOR SUMBER', 'jumlah_debitur' => '120',
    ]);
    $this->app->instance(SectorPotentialService::class, new class extends SectorPotentialService
    {
        public function payload(?string $branchKey = null): array
        {
            return ['ready' => true, 'period' => '2026-09-30', 'units' => [
                ['code' => '03883', 'branch' => 'madiun', 'values' => ['trade' => ['potential' => 100.0]]],
            ], 'totals' => ['trade' => ['potential' => 1000.0]]];
        }
    });
    $user = User::factory()->create();
    $response = $this->actingAs($user)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', [
        'periode' => '2026-09-30', 'wilayah' => 'madiun', 'sektor' => 'PERDAGANGAN BESAR DAN ECERAN', 'sub_sektor' => 'SUBSEKTOR SUMBER',
    ]));
    $response->assertOk()
        ->assertJsonPath('units.0.values.jumlah_debitur', 120)
        ->assertJsonPath('units.0.sector_parent_potential', 100)
        ->assertJsonPath('units.0.sector_parent_coverage.existing', 120)
        ->assertJsonPath('units.0.sector_parent_coverage.penetration_raw', 120)
        ->assertJsonPath('units.0.sector_parent_coverage.penetration', 100)
        ->assertJsonPath('units.0.sector_parent_coverage.normalization_applied', true)
        ->assertJsonPath('units.0.sector_parent_coverage.penetration_excess', 20)
        ->assertJsonPath('units.0.denominator_basis', 'potensi_kategori_sektor_induk_desa_milik_uker')
        ->assertJsonPath('sector_reference_ready', true)
        ->assertJsonPath('sector_reference_units.0.values.intra', 120)
        ->assertJsonPath('sector_reference_units.0.values.extra', 0)
        ->assertJsonPath('sector_reference_units.0.sector_parent_coverage.penetration', 100)
        ->assertJsonPath('sector_potential.selected_context.parent_group.key', 'trade')
        ->assertJsonPath('sector_potential.selected_context.subsector_parent_coverage.potential', 1000)
        ->assertJsonPath('sector_potential.selected_context.subsector_parent_coverage.penetration', 12)
        ->assertJsonPath('sector_potential.selected_context.subsector_potential', null);
});

it('adds the owned-village AKTIF plus PH cohort while preserving generic CRAS metrics and filters', function (): void {
    DB::table('cras')->where('cras_uuid', 'madiun-pertanian')->update([
        'cras_periode' => '2026-09-30', 'jumlah_debitur' => '10', 'sektor_ekonomi' => 'PERDAGANGAN BESAR DAN ECERAN',
    ]);
    $row = (array) DB::table('cras')->where('cras_uuid', 'madiun-pertanian')->first();
    DB::table('cras')->insert([
        array_replace($row, ['cras_uuid' => 'owned-ph-positive', 'status_rekening' => 'PH', 'sektor_ekonomi' => '', 'jumlah_debitur' => '4', 'jumlah_rekening' => '4', 'baki_debet' => '90', 'saldo_ph' => '60.25']),
        array_replace($row, ['cras_uuid' => 'owned-ph-fallback', 'status_rekening' => 'PH', 'sektor_ekonomi' => '', 'jumlah_debitur' => '3', 'jumlah_rekening' => '3', 'baki_debet' => '40.5', 'saldo_ph' => '0']),
        array_replace($row, ['cras_uuid' => 'owned-consumer', 'segmen' => 'Consumer', 'jumlah_debitur' => '200', 'jumlah_rekening' => '200', 'baki_debet' => '1']),
        array_replace($row, ['cras_uuid' => 'unowned-kc', 'br_number' => '00045', 'ket_unit_kerja' => 'KC MADIUN', 'jumlah_debitur' => '7', 'jumlah_rekening' => '7', 'baki_debet' => '0']),
    ]);
    $this->app->instance(SectorPotentialService::class, new class extends SectorPotentialService
    {
        public function payload(?string $branchKey = null): array
        {
            return ['ready' => true, 'period' => '2026-09-30', 'units' => [
                ['code' => '03883', 'branch' => 'madiun', 'village_keys' => ['KABUPATEN MADIUN|CARUBAN|DESA'], 'district_codes' => ['35.19.01'], 'potential_partial' => true, 'missing_population_village_count' => 1, 'values' => ['trade' => ['potential' => 100.0]]],
            ], 'totals' => ['trade' => ['potential' => 100.0]]];
        }
    });
    $user = User::factory()->create();
    $parameters = ['periode' => '2026-09-30', 'wilayah' => 'madiun'];
    $this->actingAs($user)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', $parameters))
        ->assertOk()
        ->assertJsonPath('metrics.jumlah_debitur', 224)
        ->assertJsonCount(2, 'units')
        ->assertJsonPath('sector_reference_ready', true)
        ->assertJsonCount(1, 'sector_reference_units')
        ->assertJsonPath('sector_reference_units.0.code', '03883')
        ->assertJsonPath('sector_reference_units.0.values.jumlah_debitur', 17)
        ->assertJsonPath('sector_reference_units.0.values.intra', 10)
        ->assertJsonPath('sector_reference_units.0.values.extra', 7)
        ->assertJsonPath('sector_reference_units.0.values.jumlah_rekening', 10)
        ->assertJsonPath('sector_reference_units.0.values.baki_debet', 1250000)
        ->assertJsonPath('sector_reference_units.0.values.credit_extra', 100.75)
        ->assertJsonPath('sector_reference_units.0.potential_partial', true)
        ->assertJsonPath('sector_reference_units.0.missing_population_village_count', 1)
        ->assertJsonPath('sector_reference_metrics.jumlah_debitur', 17)
        ->assertJsonPath('sector_reference_metrics.intra', 10)
        ->assertJsonPath('sector_reference_metrics.extra', 7);

    $this->actingAs($user)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', $parameters + ['segmen' => 'Consumer']))
        ->assertOk()
        ->assertJsonPath('metrics.jumlah_debitur', 200)
        ->assertJsonCount(1, 'units')
        ->assertJsonCount(0, 'sector_reference_units')
        ->assertJsonPath('sector_reference_metrics.jumlah_debitur', 0);
});

it('never compares a different report month against the pinned September village denominator', function (): void {
    $this->app->instance(SectorPotentialService::class, new class extends SectorPotentialService
    {
        public function payload(?string $branchKey = null): array
        {
            return ['ready' => true, 'period' => '2026-09-30', 'units' => [
                ['code' => '03883', 'branch' => 'madiun', 'values' => ['trade' => ['potential' => 100.0]]],
            ], 'totals' => ['trade' => ['potential' => 100.0]]];
        }
    });
    $user = User::factory()->create();
    $this->actingAs($user)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', ['periode' => '2026-06-30', 'wilayah' => 'madiun']))
        ->assertOk()
        ->assertJsonPath('metrics.jumlah_debitur', 2)
        ->assertJsonPath('sector_reference_ready', false)
        ->assertJsonCount(0, 'sector_reference_units')
        ->assertJsonPath('sector_reference_metrics.jumlah_debitur', null)
        ->assertJsonPath('units.0.sector_parent_potential', null)
        ->assertJsonPath('sector_potential.selected_context.reference_ready', false)
        ->assertJsonPath('sector_potential.selected_context.subsector_parent_coverage.penetration', null);
});

it('resolves an unambiguous parent from actual filtered SSA rows and does not guess across parents', function (): void {
    DB::table('cras')->where('cras_uuid', 'madiun-pertanian')->update([
        'cras_periode' => '2026-09-30', 'sektor_ekonomi' => 'PERDAGANGAN BESAR DAN ECERAN', 'sub_sektor_ekonomi' => 'NAMA SAMA',
    ]);
    DB::table('cras')->where('cras_uuid', 'ngawi-npl')->update([
        'cras_periode' => '2026-09-30', 'sektor_ekonomi' => 'INDUSTRI PENGOLAHAN', 'sub_sektor_ekonomi' => 'NAMA SAMA',
    ]);
    $this->app->instance(SectorPotentialService::class, new class extends SectorPotentialService
    {
        public function payload(?string $branchKey = null): array
        {
            return ['ready' => true, 'period' => '2026-09-30', 'units' => [
                ['code' => '03883', 'branch' => 'madiun', 'values' => ['trade' => ['potential' => 100.0]]],
                ['code' => '06429', 'branch' => 'ngawi', 'values' => ['industry' => ['potential' => 200.0]]],
            ], 'totals' => ['trade' => ['potential' => 1000.0], 'industry' => ['potential' => 2000.0]]];
        }
    });
    $areaUser = User::factory()->create();
    $parameters = ['periode' => '2026-09-30', 'sektor' => 'all', 'sub_sektor' => 'NAMA SAMA'];
    $this->actingAs($areaUser)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', $parameters + ['wilayah' => 'madiun']))
        ->assertOk()
        ->assertJsonPath('sector_potential.selected_context.parent_group.key', 'trade')
        ->assertJsonPath('units.0.sector_parent_potential', 100)
        ->assertJsonPath('units.0.sector_parent_coverage.penetration', 2);
    $this->actingAs($areaUser)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', $parameters + ['wilayah' => 'all']))
        ->assertOk()
        ->assertJsonPath('sector_potential.selected_context.parent_group', null)
        ->assertJsonPath('units.0.sector_parent_potential', null)
        ->assertJsonPath('units.1.sector_parent_potential', null);

    $branchUser = User::factory()->create(['pn' => '0045']);
    $this->actingAs($branchUser)->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', $parameters + ['wilayah' => 'ngawi']))
        ->assertOk()
        ->assertJsonPath('filters.selected.wilayah', 'madiun')
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('sector_potential.selected_context.parent_group.key', 'trade')
        ->assertJsonPath('units.0.sector_parent_potential', 100);
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
        ->assertSee('Series Movement Kualitas UG dan DG')
        ->assertSee('1. Pilih Segmen')
        ->assertSee('2. Pilih Produk')
        ->assertSee('Semua Produk Small')
        ->assertSee('Consumer')
        ->assertSee('id="crasPortfolioMap"', false)
        ->assertSee('Sector Acceptance Criteria LPG')
        ->assertSee('Marketshare CRAS LPG');
});

it('builds dependent Small and Consumer product movement series for area and branch scope', function (): void {
    $base = [
        'br_number' => '00045',
        'ket_unit_kerja' => 'KC MADIUN',
        'status_rekening' => 'AKTIF',
        'sektor_ekonomi' => 'PERDAGANGAN',
        'sub_sektor_ekonomi' => 'ECERAN',
        'loan_type' => 'KMK',
        'ket_produk_tiering' => 'KECIL',
        'kualitas' => '1',
    ];

    DB::table('cras')->insert([
        $base + ['cras_uuid' => 'series-cashcoll-ug-madiun', 'cras_periode' => '2026-01-31', 'ket_kanca' => 'KC Madiun', 'segmen' => 'Small', 'produk' => 'Cashcoll', 'flag_movement_kualitas' => 'UG', 'baki_debet' => '100'],
        $base + ['cras_uuid' => 'series-cashcoll-ug-ngawi', 'cras_periode' => '2026-01-31', 'ket_kanca' => 'KC Ngawi', 'segmen' => 'Small', 'produk' => 'Cashcoll', 'flag_movement_kualitas' => 'UG', 'baki_debet' => '50'],
        $base + ['cras_uuid' => 'series-cashcoll-dg-madiun', 'cras_periode' => '2026-08-31', 'ket_kanca' => 'KC Madiun', 'segmen' => 'Small', 'produk' => 'Cashcoll', 'flag_movement_kualitas' => 'DG', 'baki_debet' => '200'],
        $base + ['cras_uuid' => 'series-small-ug-madiun', 'cras_periode' => '2026-01-31', 'ket_kanca' => 'KC Madiun', 'segmen' => 'Small', 'produk' => 'Small', 'flag_movement_kualitas' => 'UG', 'baki_debet' => '300'],
        $base + ['cras_uuid' => 'series-consumer-dg-madiun', 'cras_periode' => '2026-08-31', 'ket_kanca' => 'KC Madiun', 'segmen' => 'Consumer', 'produk' => 'BRIGUNA', 'flag_movement_kualitas' => 'DG', 'baki_debet' => '400'],
        $base + ['cras_uuid' => 'series-consumer-dg-ngawi', 'cras_periode' => '2026-08-31', 'ket_kanca' => 'KC Ngawi', 'segmen' => 'Consumer', 'produk' => 'KPR', 'flag_movement_kualitas' => 'DG', 'baki_debet' => '600'],
        $base + ['cras_uuid' => 'series-kur-small-excluded', 'cras_periode' => '2026-01-31', 'ket_kanca' => 'KC Madiun', 'segmen' => 'Small', 'produk' => 'KUR-Small', 'flag_movement_kualitas' => 'UG', 'baki_debet' => '900'],
        array_merge($base, ['cras_uuid' => 'series-ph-excluded', 'cras_periode' => '2026-01-31', 'ket_kanca' => 'KC Madiun', 'status_rekening' => 'PH', 'segmen' => 'Small', 'produk' => 'Small', 'flag_movement_kualitas' => 'UG', 'baki_debet' => '1000']),
        $base + ['cras_uuid' => 'series-stay-excluded', 'cras_periode' => '2026-01-31', 'ket_kanca' => 'KC Madiun', 'segmen' => 'Consumer', 'produk' => 'Konsumtif Lainnya', 'flag_movement_kualitas' => 'STAY', 'baki_debet' => '2000'],
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', ['periode' => '2026-08-31']))
        ->assertOk()
        ->assertJsonPath('movement_series.ready', true)
        ->assertJsonPath('movement_series.scope.label', 'Seluruh Area 6')
        ->assertJsonCount(8, 'movement_series.periods')
        ->assertJsonPath('movement_series.periods.0.value', '2026-01-31')
        ->assertJsonPath('movement_series.periods.7.value', '2026-08-31')
        ->assertJsonCount(2, 'movement_series.segments')
        ->assertJsonPath('movement_series.segments.0.key', 'small')
        ->assertJsonCount(4, 'movement_series.segments.0.products')
        ->assertJsonPath('movement_series.segments.0.products.0.label', 'Semua Produk Small')
        ->assertJsonPath('movement_series.segments.0.products.0.rows.0.values.2026-01-31', 1350)
        ->assertJsonPath('movement_series.segments.0.products.1.label', 'Cashcoll')
        ->assertJsonPath('movement_series.segments.0.products.1.rows.0.values.2026-01-31', 150)
        ->assertJsonPath('movement_series.segments.0.products.2.label', 'KUR-Small')
        ->assertJsonPath('movement_series.segments.0.products.2.rows.0.values.2026-01-31', 900)
        ->assertJsonPath('movement_series.segments.0.products.3.label', 'Small')
        ->assertJsonPath('movement_series.segments.0.products.3.rows.0.values.2026-01-31', 300)
        ->assertJsonPath('movement_series.segments.1.key', 'consumer')
        ->assertJsonCount(4, 'movement_series.segments.1.products')
        ->assertJsonPath('movement_series.segments.1.products.0.rows.1.values.2026-08-31', 1000)
        ->assertJsonPath('movement_series.segments.1.products.1.label', 'BRIGUNA')
        ->assertJsonPath('movement_series.segments.1.products.1.rows.1.values.2026-08-31', 400)
        ->assertJsonPath('movement_series.segments.1.products.2.label', 'Konsumtif Lainnya')
        ->assertJsonPath('movement_series.segments.1.products.2.rows.0.values.2026-01-31', 0)
        ->assertJsonPath('movement_series.segments.1.products.3.label', 'KPR')
        ->assertJsonPath('movement_series.segments.1.products.3.rows.1.values.2026-08-31', 600);

    $this->actingAs($user)
        ->getJson(route('report.dashboard-dana.market-share.mapping-cras.data', [
            'periode' => '2026-08-31',
            'wilayah' => 'madiun',
        ]))
        ->assertOk()
        ->assertJsonPath('movement_series.scope.label', 'KC Madiun')
        ->assertJsonPath('movement_series.segments.0.products.0.rows.0.values.2026-01-31', 1300)
        ->assertJsonPath('movement_series.segments.1.products.0.rows.1.values.2026-08-31', 400)
        ->assertJsonPath('movement_series.segments.1.products.3.label', 'KPR')
        ->assertJsonPath('movement_series.segments.1.products.3.rows.1.values.2026-08-31', 0);
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
