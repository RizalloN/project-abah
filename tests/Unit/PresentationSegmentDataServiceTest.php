<?php

use App\Services\Presentation\PresentationSegmentDataService;
use App\Services\Presentation\PresentationWilayahDataService;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
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
                if (!isset($branch[$column])) {
                    $branch[$column] = array_sum(array_column($outlets, $column));
                }
            }
            array_push($rows, $branch, ...$outlets);
        }
    }
    DB::table('dashboard_harian_snapshots')->insert($rows);
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

it('never lets a branch user open another scope', function (): void {
    $service = app(PresentationSegmentDataService::class);
    $ngawi = UserBranchScope::forKey('ngawi');

    $data = UserBranchScope::runAs($ngawi, fn () => $service->build('2026-10-25', 'area6'));

    expect($data['scope']['key'])->toBe('ngawi')
        ->and(array_column($data['scopes'], 'key'))->toBe(['ngawi'])
        ->and(array_column($data['entities'], 'key'))->toBe(['unit-kedunggalar-ngawi']);
});

it('replaces cached scope choices with the current callers allowed branches', function (): void {
    $this->mock(\App\Support\DashboardHarianSnapshotService::class)
        ->shouldReceive('fetchPeriods')->once()->andReturn(collect(['2026-10-25']));
    $originalCache = Config::get('cache.default');
    Config::set('cache.default', 'array');
    $cacheKey = 'presentation_segments:v2:2026-10-25:madiun';
    \Illuminate\Support\Facades\Cache::put($cacheKey, [
        'available' => true,
        'scope' => ['key' => 'madiun'],
        'scopes' => [['key' => 'area6'], ['key' => 'madiun'], ['key' => 'ngawi']],
        'entities' => [['key' => 'unit-dolopo-madiun']],
    ], 60);
    try {
        $response = UserBranchScope::runAs(UserBranchScope::forKey('madiun'), fn () =>
            app(\App\Http\Controllers\DashboardSimpananController::class)->presentationSegmentData(
                \Illuminate\Http\Request::create('/dashboard/presentation-data/segments', 'GET', ['periode' => '2026-10-25', 'scope' => 'ngawi']),
                app(PresentationSegmentDataService::class)
            )
        );
        $payload = $response->getData(true);
        expect(array_column($payload['scopes'], 'key'))->toBe(['madiun'])
            ->and($payload['scope']['key'])->toBe('madiun')
            ->and(array_column($payload['entities'], 'key'))->toBe(['unit-dolopo-madiun']);
    } finally {
        \Illuminate\Support\Facades\Cache::forget($cacheKey);
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
    $service = new PresentationWilayahDataService();

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
    DB::table('dashboard_harian_snapshots')
        ->where('snapshot_period', '2026-09-30')
        ->where('kanca_key', 'kc-madiun')
        ->whereColumn('unit_key', '<>', 'kanca_key')
        ->update(['rec_dh_total' => null]);

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
    $data = (new PresentationWilayahDataService())->marketShare(['dpk' => $segment], []);
    $rows = collect($data['groups']['simpanan'][0]['rows'])->keyBy('key');

    expect($data['available'])->toBeTrue()
        ->and($rows['madiun']['bri'])->toBe(120.0)
        ->and($rows['madiun']['industry'])->toBe(100.0)
        ->and($rows['madiun']['share'])->toBeNull()
        ->and($rows['madiun']['others'])->toBeNull()
        ->and($rows['ngawi']['others'])->toBe(0.0)
        ->and($rows['ngawi']['share'])->toBe(0.0);
});
