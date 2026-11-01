<?php

use App\Http\Controllers\DashboardSimpananController;
use App\Services\Presentation\PresentationSegmentDataService;
use App\Services\Presentation\PresentationWilayahDataService;
use App\Support\DashboardHarianSnapshotService;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->originalConnection = Config::get('database.default');
    $this->originalDatabase = Config::get('database.connections.sqlite.database');
    $this->tempDatabase = tempnam(sys_get_temp_dir(), 'abah_segments_');
    Config::set('database.default', 'sqlite');
    Config::set('database.connections.sqlite.database', $this->tempDatabase);
    DB::purge('sqlite');
    DB::reconnect('sqlite');

    Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
        $table->date('snapshot_period');
        $table->string('kanca_key');
        $table->string('kanca_label');
        $table->string('unit_key');
        $table->string('unit_label');
        foreach ([
            'total_simpanan', 'total_casa', 'giro_ritel', 'giro_mikro', 'tabungan_ritel', 'tabungan_mikro', 'deposito_ritel',
            'total_os_non_commercial', 'total_sml_abs_non_commercial', 'total_npl_abs_non_commercial',
            'sme_os', 'consumer_os', 'micro_os', 'sme_sml', 'micro_sml', 'sme_npl', 'micro_npl',
            'kupedes_os', 'kupedes_sml', 'kupedes_npl', 'kecil_non_cashcoll_os',
            'rec_dh_total', 'rec_dh_small', 'rec_dh_consumer', 'rec_dh_micro',
        ] as $column) {
            $table->decimal($column, 20, 2)->nullable();
        }
    });

    // Two periods; each branch row equals the sum of its outlet rows, as in production.
    $outlet = fn (string $period, string $kanca, string $kancaLabel, string $unit, string $unitLabel, float $simpanan, float $os, float $npl, float $recovery): array => [
        'snapshot_period' => $period, 'kanca_key' => $kanca, 'kanca_label' => $kancaLabel, 'unit_key' => $unit, 'unit_label' => $unitLabel,
        'total_simpanan' => $simpanan, 'total_casa' => $simpanan * 0.8, 'tabungan_mikro' => $simpanan * 0.7, 'deposito_ritel' => $simpanan * 0.3,
        'total_os_non_commercial' => $os, 'micro_os' => $os * 0.6, 'sme_os' => $os * 0.4, 'kupedes_os' => $os * 0.6, 'kecil_non_cashcoll_os' => $os * 0.4,
        'total_sml_abs_non_commercial' => $npl * 2, 'total_npl_abs_non_commercial' => $npl, 'micro_npl' => $npl, 'kupedes_npl' => $npl,
        'rec_dh_total' => $recovery, 'rec_dh_micro' => $recovery,
    ];
    $rows = [];
    foreach (['2026-09-30' => 1.0, '2026-10-25' => 1.1] as $period => $growth) {
        $madiun = [
            $outlet($period, 'kc-madiun', 'KC Madiun', 'kc-madiun-detail', 'KC Madiun', 300e9 * $growth, 200e9, 4e9, 1e9),
            $outlet($period, 'kc-madiun', 'KC Madiun', 'unit-dolopo-madiun', 'UNIT Dolopo Madiun', 100e9 * $growth, 300e9, 9e9, 2e9),
        ];
        $ngawi = [$outlet($period, 'kc-ngawi', 'KC Ngawi', 'unit-kedunggalar-ngawi', 'UNIT Kedunggalar Ngawi', 200e9 * $growth, 100e9, 1e9, 0.5e9)];
        foreach ([[$madiun, 'kc-madiun', 'KC Madiun'], [$ngawi, 'kc-ngawi', 'KC Ngawi']] as [$outlets, $key, $label]) {
            $branch = ['snapshot_period' => $period, 'kanca_key' => $key, 'kanca_label' => $label, 'unit_key' => $key, 'unit_label' => $label];
            foreach (array_keys($outlets[0]) as $column) {
                if (! isset($branch[$column])) {
                    $branch[$column] = array_sum(array_column($outlets, $column));
                }
            }
            array_push($rows, $branch, ...$outlets);
        }
    }
    DB::table('dashboard_harian_snapshots')->insert($rows);

    Schema::create('lw325_ph', function (Blueprint $table): void {
        foreach (['periode', 'acctno', 'kanca', 'unit', 'segmen_dashboard', 'produk_dashboard', 'flag_klaim'] as $column) {
            $table->string($column)->nullable();
        }
        $table->decimal('pokok', 20, 2)->nullable();
        $table->decimal('clmamt', 20, 2)->nullable();
    });
    foreach (['2026-08-31' => [10e9, 20e9, 5e9], '2026-09-30' => [9.5e9, 19e9, 4.75e9], '2026-10-25' => [8.5e9, 17e9, 4.25e9]] as $period => $amounts) {
        foreach ([['1', 'KC Madiun', 'KC Madiun'], ['2', 'KC Madiun', 'UNIT Dolopo Madiun'], ['3', 'KC Ngawi', 'UNIT Kedunggalar Ngawi']] as $index => [$account, $branch, $unit]) {
            DB::table('lw325_ph')->insert(['periode' => $period, 'acctno' => $account, 'kanca' => $branch, 'unit' => $unit, 'segmen_dashboard' => 'Micro', 'produk_dashboard' => 'Kupedes', 'flag_klaim' => 'N', 'pokok' => $amounts[$index], 'clmamt' => 0]);
        }
    }
});

afterEach(function (): void {
    DB::disconnect('sqlite');
    Config::set('database.default', $this->originalConnection);
    Config::set('database.connections.sqlite.database', $this->originalDatabase);
    DB::purge('sqlite');
    if (is_file($this->tempDatabase)) {
        unlink($this->tempDatabase);
    }
});

it('breaks the Area down per branch from branch rows only', function (): void {
    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'area6'));

    expect($data['available'])->toBeTrue()
        ->and($data['scope']['entity_level'])->toBe('branch')
        ->and(array_column($data['entities'], 'key'))->toBe(['kc-madiun', 'kc-ngawi'])
        ->and($data['simpanan']['total']['value'])->toEqual(660e9)
        ->and($data['simpanan']['total']['deltas']['mtd']['value'])->toEqual(60e9)
        ->and($data['simpanan']['total']['deltas']['mtd']['formatted'])->toBe('+Rp60,00 M')
        ->and($data['pinjaman']['total']['formatted'])->toBe('Rp600,00 M')
        ->and($data['kualitas']['total']['npl_ratio']['formatted'])->toBe('2,33%')
        ->and($data['recovery']['total']['value'])->toEqual(3.5e9)
        ->and($data['compare']['mtd']['period'])->toBe('2026-09-30');
});

it('breaks a branch down per outlet and keeps the branch total', function (): void {
    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'madiun'));

    $rows = collect($data['simpanan']['rows'])->keyBy('key');

    expect($data['scope']['entity_level'])->toBe('unit')
        ->and(array_column($data['entities'], 'kind'))->toBe(['kc', 'unit'])
        ->and($data['entities'][0]['label'])->toBe('KC Madiun (kantor cabang)')
        ->and($data['simpanan']['total']['value'])->toEqual(440e9)
        ->and($rows['unit-dolopo-madiun']['values']['total']['share'])->toEqual(25.0)
        ->and($rows['unit-dolopo-madiun']['values']['total']['mtd']['value'])->toEqual(10e9)
        ->and(collect($data['kualitas']['rows'])->firstWhere('key', 'unit-dolopo-madiun')['npl_ratio']['formatted'])->toBe('3,00%');
});

it('includes nominal YoY YtD and MtD changes for each unit without replacing the legacy MtD field', function (): void {
    $rows = DB::table('dashboard_harian_snapshots')->where('snapshot_period', '2026-09-30')->get();
    foreach (['2025-10-25' => 0.5, '2025-12-31' => 0.8] as $period => $factor) {
        foreach ($rows as $row) {
            $copy = (array) $row;
            $copy['snapshot_period'] = $period;
            $copy['total_simpanan'] *= $factor;
            $copy['total_os_non_commercial'] *= $factor;
            DB::table('dashboard_harian_snapshots')->insert($copy);
        }
    }
    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'madiun'));
    $savings = collect($data['simpanan']['rows'])->firstWhere('key', 'unit-dolopo-madiun')['values']['total'];
    $loans = collect($data['pinjaman']['rows'])->firstWhere('key', 'unit-dolopo-madiun')['values']['total'];

    expect($savings['deltas']['mtd']['value'])->toEqual(10e9)
        ->and($savings['deltas']['ytd']['value'])->toEqual(30e9)
        ->and($savings['deltas']['yoy']['value'])->toEqual(60e9)
        ->and($savings['mtd'])->toBe($savings['deltas']['mtd'])
        ->and($loans['deltas']['mtd']['value'])->toEqual(0)
        ->and($loans['deltas']['ytd']['value'])->toEqual(60e9)
        ->and($loans['deltas']['yoy']['value'])->toEqual(150e9);
});

it('retains actual previous-month SML and NPL ratios and percentage-point changes at every quality level', function (): void {
    DB::table('dashboard_harian_snapshots')->where('snapshot_period', '2026-09-30')->where('unit_key', 'unit-dolopo-madiun')->update(['total_npl_abs_non_commercial' => 6e9, 'total_sml_abs_non_commercial' => 3e9, 'kupedes_npl' => 3.6e9]);
    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'madiun'));
    $unit = collect($data['kualitas']['rows'])->firstWhere('key', 'unit-dolopo-madiun');
    $product = collect($data['kualitas']['products'])->firstWhere('key', 'mikro')['products'][0];

    expect($unit['npl_ratio']['value'])->toBe(3.0)
        ->and($unit['npl_ratio']['previous']['mtd']['value'])->toBe(2.0)
        ->and($unit['npl_ratio']['deltas']['mtd']['value'])->toBe(1.0)
        ->and($unit['sml_ratio']['value'])->toBe(6.0)
        ->and($unit['sml_ratio']['previous']['mtd']['value'])->toBe(1.0)
        ->and($unit['sml_ratio']['deltas']['mtd']['value'])->toBe(5.0)
        ->and($unit['npl_ratio']['previous']['yoy']['value'])->toBeNull()
        ->and($product['npl_ratio']['previous']['mtd']['value'])->toBe(2.5333)
        ->and($data['kualitas']['total']['npl_ratio']['previous']['mtd']['value'])->toBe(2.0)
        ->and($data['kualitas']['total']['npl_ratio']['value'])->toBe(2.6);
});

it('uses Data PH principal movements with Consumer claims and global account matching instead of GI405 snapshots', function (): void {
    DB::table('lw325_ph')->delete();
    $row = fn (string $period, string $account, string $unit, string $segment, string $product, float $principal, string $flag = 'N', float $claim = 0, string $branch = 'KC Madiun'): array => ['periode' => $period, 'acctno' => $account, 'kanca' => $branch, 'unit' => $unit, 'segmen_dashboard' => $segment, 'produk_dashboard' => $product, 'pokok' => $principal, 'flag_klaim' => $flag, 'clmamt' => $claim];
    DB::table('lw325_ph')->insert([
        $row('2026-09-30', '0001', 'UNIT Dolopo Madiun', 'Micro', 'Kupedes', 100),
        $row('2026-09-30', '0002', 'KC Madiun', 'Consumer', 'Briguna', 500),
        $row('2026-09-30', '0003', 'KC Madiun', 'Konsumer', 'KPR', 200),
        $row('2026-09-30', '0004', 'UNIT Dolopo Madiun', 'Small', 'KMK', 80, 'Y'),
        $row('2026-09-30', '0005', 'UNIT Dolopo Madiun', 'Micro', 'Kupedes', 100),
        $row('2026-09-30', '0006', 'UNIT Dolopo Madiun', 'Consumer', 'KKB', 90),
        $row('2026-10-25', '1', '03888 -- UNIT Dolopo Madiun', 'Micro', 'Kupedes', 70, 'Y', 50),
        $row('2026-10-25', '2', 'KC Madiun', 'Consumer', 'Briguna', 400),
        $row('2026-10-25', '4', 'UNIT Dolopo Madiun', 'Small', 'KMK', 50, 'Y'),
        $row('2026-10-25', '00005', 'UNIT Kedunggalar Ngawi', 'Micro', 'Kupedes', 100, 'N', 0, 'KC Ngawi'),
        $row('2026-10-25', '7', 'KC Madiun', 'Consumer', 'Briguna', 70, 'Y', 45),
    ]);
    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-26', 'madiun'));
    $segments = collect($data['recovery']['segments'])->keyBy('key');
    $units = collect($data['recovery']['rows'])->keyBy('key');

    expect($data['recovery']['source']['label'])->toBe('Data PH')
        ->and($data['recovery']['source']['table'])->toBe('lw325_ph')
        ->and($data['recovery']['source']['period'])->toBe('2026-10-25')
        ->and($data['recovery']['source']['baseline_period'])->toBe('2026-09-30')
        ->and($data['recovery']['total']['value'])->toBe(405.0)
        ->and($segments['mikro']['value'])->toBe(30.0)
        ->and($segments['kecil']['value'])->toBe(30.0)
        ->and($segments['konsumer']['value'])->toBe(345.0)
        ->and($units['unit-dolopo-madiun']['values']['total']['value'])->toBe(60.0)
        ->and($units['kc-madiun-detail']['values']['konsumer']['value'])->toBe(345.0);

    $ngawi = UserBranchScope::runAs(UserBranchScope::forKey('ngawi'), fn () => app(PresentationSegmentDataService::class)->build('2026-10-26', 'madiun'));
    expect($ngawi['scope']['key'])->toBe('ngawi')
        ->and($ngawi['recovery']['total']['value'])->toBe(0.0)
        ->and(array_column($ngawi['recovery']['rows'], 'key'))->toBe(['unit-kedunggalar-ngawi']);
});

it('uses Cognos fallback like Data PH and never fills missing PH data with snapshot GI405 values', function (): void {
    Schema::create('cognos_recovery', function (Blueprint $table): void {
        foreach (['periode', 'cabang', 'unit_kerja', 'segmen_2', 'produk'] as $column) {
            $table->string($column);
        }
        $table->decimal('total_recovery', 20, 2);
    });
    DB::table('lw325_ph')->where('periode', '2026-09-30')->delete();
    DB::table('cognos_recovery')->insert(['periode' => '2026-10-25', 'cabang' => 'KC Madiun', 'unit_kerja' => 'KC Madiun', 'segmen_2' => 'Consumer', 'produk' => 'Lainnya', 'total_recovery' => 125]);
    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'madiun'));
    expect($data['recovery']['source']['table'])->toBe('cognos_recovery')
        ->and($data['recovery']['total']['value'])->toBe(125.0)
        ->and(collect($data['recovery']['segments'])->firstWhere('key', 'konsumer')['value'])->toBe(125.0);

    Schema::drop('lw325_ph');
    Schema::drop('cognos_recovery');
    $missing = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'madiun'));
    expect($missing['recovery']['total']['value'])->toBeNull()
        ->and($missing['recovery']['period'])->toBeNull()
        ->and($missing['recovery']['rows'])->toBe([]);
});

it('keeps the Data PH exact month-end and PH-first YoY comparison periods', function (): void {
    DB::table('lw325_ph')->insert(['periode' => '2025-04-30', 'acctno' => 'old-source', 'kanca' => 'KC Madiun', 'unit' => 'KC Madiun', 'segmen_dashboard' => 'Micro', 'produk_dashboard' => 'Kupedes', 'pokok' => 100]);
    Schema::create('cognos_recovery', function (Blueprint $table): void {
        foreach (['periode', 'cabang', 'unit_kerja', 'segmen_2', 'produk'] as $column) {
            $table->string($column);
        }
        $table->decimal('total_recovery', 20, 2);
    });
    foreach (['2025-04-30' => 10, '2025-09-30' => 99, '2025-12-31' => 20] as $period => $amount) {
        DB::table('cognos_recovery')->insert(['periode' => $period, 'cabang' => 'KC Madiun', 'unit_kerja' => 'KC Madiun', 'segmen_2' => 'Micro', 'produk' => 'Kupedes', 'total_recovery' => $amount]);
    }
    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'madiun'));
    expect($data['recovery']['compare']['mtd']['period'])->toBe('2026-09-30')
        ->and($data['recovery']['compare']['ytd']['period'])->toBe('2025-12-31')
        ->and($data['recovery']['compare']['yoy']['period'])->toBe('2025-04-30')
        ->and($data['recovery']['total']['deltas']['yoy']['value'])->toBe(2999999990.0);

    DB::table('lw325_ph')->where('periode', '2026-09-30')->update(['periode' => '2026-09-29']);
    $notMonthEnd = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'madiun'));
    expect($notMonthEnd['recovery']['compare']['mtd']['period'])->toBeNull()
        ->and($notMonthEnd['recovery']['total']['deltas']['mtd'])->toBeNull()
        ->and($notMonthEnd['recovery']['source']['baseline_period'])->toBe('2026-09-29');
});

it('never lets a branch user open another scope', function (): void {
    $service = app(PresentationSegmentDataService::class);
    $ngawi = UserBranchScope::forKey('ngawi');

    $data = UserBranchScope::runAs($ngawi, fn () => $service->build('2026-10-25', 'area6'));

    expect($data['scope']['key'])->toBe('ngawi')
        ->and(array_column($data['scopes'], 'key'))->toBe(['ngawi'])
        ->and(array_column($data['entities'], 'key'))->toBe(['unit-kedunggalar-ngawi']);
});

it('replaces cached scope choices with the current callers allowed branches', function (): void {
    $this->mock(DashboardHarianSnapshotService::class)
        ->shouldReceive('fetchPeriods')->once()->andReturn(collect(['2026-10-25']));
    $originalCache = Config::get('cache.default');
    Config::set('cache.default', 'array');
    $cacheKey = 'presentation_segments:v3-data-ph:2026-10-25:madiun';
    Cache::put($cacheKey, [
        'available' => true,
        'scope' => ['key' => 'madiun'],
        'scopes' => [['key' => 'area6'], ['key' => 'madiun'], ['key' => 'ngawi']],
        'entities' => [['key' => 'unit-dolopo-madiun']],
    ], 60);
    try {
        $response = UserBranchScope::runAs(UserBranchScope::forKey('madiun'), fn () => app(DashboardSimpananController::class)->presentationSegmentData(
            Request::create('/dashboard/presentation-data/segments', 'GET', ['periode' => '2026-10-25', 'scope' => 'ngawi']),
            app(PresentationSegmentDataService::class)
        )
        );
        $payload = $response->getData(true);
        expect(array_column($payload['scopes'], 'key'))->toBe(['madiun'])
            ->and($payload['scope']['key'])->toBe('madiun')
            ->and(array_column($payload['entities'], 'key'))->toBe(['unit-dolopo-madiun']);
    } finally {
        Cache::forget($cacheKey);
        Config::set('cache.default', $originalCache);
    }
});

it('does not serve the legacy GI405 snapshot recovery cache after the Data PH source change', function (): void {
    $this->mock(DashboardHarianSnapshotService::class)
        ->shouldReceive('fetchPeriods')->once()->andReturn(collect(['2026-10-25']));
    $originalCache = Config::get('cache.default');
    Config::set('cache.default', 'array');
    $oldKey = 'presentation_segments:v2:2026-10-25:madiun';
    $currentKey = 'presentation_segments:v3-data-ph:2026-10-25:madiun';
    Cache::forget($currentKey);
    Cache::put($oldKey, ['recovery' => ['total' => ['value' => 999], 'source' => ['label' => 'GI405']]], 60);
    try {
        $response = UserBranchScope::runAs(UserBranchScope::forKey('madiun'), fn () => app(DashboardSimpananController::class)->presentationSegmentData(
            Request::create('/dashboard/presentation-data/segments', 'GET', ['periode' => '2026-10-25', 'scope' => 'madiun']),
            app(PresentationSegmentDataService::class)
        ));
        $data = $response->getData(true);
        expect($data['recovery']['source']['label'])->toBe('Data PH')
            ->and($data['recovery']['source']['table'])->toBe('lw325_ph')
            ->and($data['recovery']['total']['value'])->toEqual(3e9);
    } finally {
        Cache::forget($oldKey);
        Cache::forget($currentKey);
        Config::set('cache.default', $originalCache);
    }
});

it('reshapes market share rows and limits them to a branch', function (): void {
    $segment = [
        'label' => 'Total DPK', 'kind' => 'deposit',
        'headers' => ['columns' => ['Mei-25', 'Dec-25', 'Mei-26', 'YTD %', 'Mei-25', 'Dec-25', 'Mei-26', 'YTD %', 'a', 'b', 'c', 'd', 'Mei-25', 'Dec-25', 'Mei-26', 'YoY', 'YTD']],
        'rows' => [
            ['branch' => 'KC Madiun', 'total' => false, 'values' => ['4,039', '3,975', '4,205', '5.8%', '13,586', '14,098', '14,362', '1.9%', '', '', '', '', '29.7%', '28.2%', '29.3%', '-0.44%', '1.09%']],
            ['branch' => 'Area 6', 'total' => true, 'values' => ['13,502', '13,646', '14,140', '3.6%', '34,423', '35,427', '36,461', '2.9%', '', '', '', '', '39.2%', '38.5%', '38.8%', '(0.44%)', '0.26%']],
        ],
    ];
    $service = new PresentationWilayahDataService;

    $area = $service->marketShare(['dpk' => $segment], ['unit' => 'Rp dalam Miliar']);
    $branch = $service->marketShare(['dpk' => $segment], [], 'madiun');

    expect($area['groups']['simpanan'][0]['rows'][1])->toMatchArray(['key' => null, 'total' => true, 'bri' => 14140.0, 'industry' => 36461.0, 'others' => 22321.0, 'share' => 38.8, 'share_prev' => 38.5])
        ->and($area['groups']['simpanan'][0]['period_now'])->toBe('Mei-26')
        ->and($branch['groups']['simpanan'][0]['rows'])->toHaveCount(1)
        ->and($branch['groups']['simpanan'][0]['rows'][0]['key'])->toBe('madiun');
});

it('retains an unknown source category so a remaining category cannot appear to be the full composition', function (): void {
    DB::table('dashboard_harian_snapshots')->where('snapshot_period', '2026-10-25')->update(['giro_ritel' => null]);
    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'area6'));
    $ritel = collect($data['simpanan']['segments'])->firstWhere('key', 'ritel');

    expect($ritel)->not->toBeNull()
        ->and($ritel['value'])->toBeNull()
        ->and($ritel['formatted'])->toBe('-');
});

it('keeps a null source amount unavailable without masking known outlet values', function (): void {
    DB::table('dashboard_harian_snapshots')
        ->where('snapshot_period', '2026-10-25')
        ->where('unit_key', 'unit-dolopo-madiun')
        ->update(['total_simpanan' => null]);

    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'madiun'));
    $rows = collect($data['simpanan']['rows'])->keyBy('key');

    expect($data['available'])->toBeTrue()
        ->and($data['simpanan']['total']['value'])->toBeNull()
        ->and($data['simpanan']['total']['formatted'])->toBe('-')
        ->and($data['simpanan']['total']['deltas']['mtd'])->toBeNull()
        ->and($rows['unit-dolopo-madiun']['values']['total']['value'])->toBeNull()
        ->and($rows['kc-madiun-detail']['values']['total']['value'])->toEqual(330e9)
        ->and($rows['kc-madiun-detail']['values']['total']['share'])->toBeNull()
        ->and($data['pinjaman']['total']['value'])->toEqual(500e9)
        ->and($data['simpanan']['timeseries']['series'][0]['values'])->toBe([400e9, null]);
});

it('keeps known zero amounts and suppresses ratios without a positive base', function (): void {
    DB::table('dashboard_harian_snapshots')
        ->where('snapshot_period', '2026-10-25')
        ->where('kanca_key', 'kc-madiun')
        ->whereColumn('unit_key', '<>', 'kanca_key')
        ->update(['total_os_non_commercial' => 0, 'total_npl_abs_non_commercial' => 0]);

    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'madiun'));

    expect($data['pinjaman']['total']['value'])->toBe(0.0)
        ->and($data['pinjaman']['total']['formatted'])->toBe('Rp0')
        ->and($data['kualitas']['total']['npl_ratio']['value'])->toBeNull()
        ->and($data['kualitas']['total']['npl_ratio']['formatted'])->toBe('-')
        ->and($data['kualitas']['total']['npl_ratio']['deltas']['mtd'])->toBeNull()
        ->and($data['kualitas']['rows'][0]['npl_ratio']['value'])->toBeNull();
});

it('ignores structurally absent optional columns while retaining actual null components', function (): void {
    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'madiun'));
    $products = collect($data['simpanan']['products'])->keyBy('key');

    // This fixture has deposito_ritel, but no deposito_mikro/wholesale columns.
    expect($products['deposito']['value'])->toEqual(132e9)
        ->and($products['deposito']['deltas']['mtd']['value'])->toEqual(12e9)
        // tabungan_ritel exists in this schema but its source values are NULL.
        ->and($products['tabungan']['value'])->toBeNull()
        ->and($products['tabungan']['formatted'])->toBe('-');
});

it('keeps a missing comparison unavailable and does not fabricate a decline', function (): void {
    // September has no preceding PH month; the GI405-derived snapshot must not fill that gap.
    DB::table('lw325_ph')->where('periode', '2026-08-31')->delete();

    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'madiun'));

    expect($data['recovery']['total']['value'])->toEqual(3e9)
        ->and($data['recovery']['total']['deltas']['mtd'])->toBeNull()
        ->and($data['recovery']['rows'][0]['values']['total']['mtd'])->toBeNull();
});

it('does not manufacture zero balances when a selected scope has no current snapshot', function (): void {
    $data = UserBranchScope::runAs(null, fn () => app(PresentationSegmentDataService::class)->build('2026-10-25', 'magetan'));

    expect($data['available'])->toBeFalse()
        ->and($data['entities'])->toBe([])
        ->and($data['simpanan']['total']['value'])->toBeNull()
        ->and($data['recovery']['total']['value'])->toBeNull()
        ->and($data['kualitas']['total']['npl_ratio']['value'])->toBeNull();
});

it('keeps market amounts when their source share is absent and avoids inventing other-bank values', function (): void {
    $segment = [
        'label' => 'Total DPK', 'kind' => 'deposit',
        'rows' => [
            ['branch' => 'KC Madiun', 'values' => [2 => '120', 6 => '100', 14 => '-']],
            ['branch' => 'KC Ngawi', 'values' => [2 => '0', 6 => '0', 14 => '0.0%']],
        ],
    ];
    $data = (new PresentationWilayahDataService)->marketShare(['dpk' => $segment], []);
    $rows = collect($data['groups']['simpanan'][0]['rows'])->keyBy('key');

    expect($data['available'])->toBeTrue()
        ->and($rows['madiun']['bri'])->toBe(120.0)
        ->and($rows['madiun']['industry'])->toBe(100.0)
        ->and($rows['madiun']['share'])->toBeNull()
        ->and($rows['madiun']['others'])->toBeNull()
        ->and($rows['ngawi']['others'])->toBe(0.0)
        ->and($rows['ngawi']['share'])->toBe(0.0);
});
