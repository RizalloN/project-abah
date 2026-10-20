<?php

namespace Tests\Unit;

use App\Support\Lw321DailyLoanSyncService;
use App\Support\MantriSourceInputStore;
use App\Support\MicroNettDisbursementCalculator;
use App\Support\ReportCacheVersion;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MantriManualFormulaTest extends TestCase
{
    private array $inputDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            foreach ([
                'periode', 'tgl_realisasi', 'cabang_normalized', 'cabang1', 'unit_normalized', 'unit1',
                'branch_normalized', 'branch1', 'pn_pemrakarsa1', 'pn_pengelola1', 'nomor_rekening1',
                'cifno', 'segmen_kinerja', 'produk_kinerja', 'description', 'uniqueid_namareport',
            ] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
        });
    }

    public static function reportPositions(): array
    {
        return [['2026-09-19'], ['2026-10-17'], ['2026-12-19']];
    }

    protected function tearDown(): void
    {
        foreach ($this->inputDirectories as $directory) {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            if (is_file($directory.'/.write.lock')) {
                unlink($directory.'/.write.lock');
            }
            rmdir($directory);
        }
        parent::tearDown();
    }

    #[DataProvider('reportPositions')]
    public function test_nonnegative_net_uses_initiator_and_preserves_full_closed_eligible_month_end_exposure(string $period): void
    {
        $date = Carbon::parse($period);
        $previous = $date->copy()->startOfMonth()->subDay()->toDateString();
        $previousBooking = Carbon::parse($previous)->startOfMonth()->toDateString();
        $booking = $date->copy()->startOfMonth()->addDays(4)->toDateString();

        DB::table('daily_loan_dinamis')->insert([
            $this->row($previous, $previousBooking, 'CLOSED-MICRO', 100_000_000),
            $this->row($previous, $previousBooking, 'CLOSED-GBT', 25_000_000, [
                'produk_kinerja' => 'BRIGUNAMIKRO', 'description' => 'KREDIT MIKRO - GBT',
            ]),
            $this->row($previous, $previousBooking, 'STILL-PRESENT', 50_000_000),
            $this->row($previous, $previousBooking, 'CLOSED-KUR-RITEL', 90_000_000, [
                'produk_kinerja' => 'KURKECIL', 'description' => 'Kredit Mikro - KUR Ritel 2015',
            ]),
            $this->row($previous, $previousBooking, 'CLOSED-SMALL', 60_000_000, [
                'segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'COMMERCIAL',
            ]),
            $this->row($previous, $previousBooking, 'OUTSIDE-BASELINE', 80_000_000, [
                'cabang1' => 'KC Kediri', 'cabang_normalized' => 'KC KEDIRI',
            ]),
            $this->row($period, $previousBooking, 'STILL-PRESENT', 50_000_000, ['baki_debet1' => 0]),
            $this->row($period, $booking, 'NEW-BOOKING', 100_000_000.25),
        ]);

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts($period);
        $this->assertCount(1, $rows);
        $this->assertSame('NEW-BOOKING', $rows[0]['account_key']);
        $this->assertSame('00000901 - INITIATOR', $rows[0]['owner']);
        $this->assertSame(125_000_000.0, $rows[0]['previous_os']);
        $this->assertSame(0.0, $rows[0]['net_amount']);
        $this->assertSame('daily_loan_dinamis', $rows[0]['baseline_source']);
        $this->assertSame($booking, $rows[0]['realization_date']);
        $this->assertSame($period, $rows[0]['periode']);
    }

    public function test_presence_in_any_current_segment_or_branch_prevents_false_closure(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-11-30', '2026-11-05', 'MOVED-OUTSIDE', 30_000_000),
            $this->row('2026-11-30', '2026-11-05', 'MOVED-TO-BRIGUNA', 40_000_000),
            $this->row('2026-12-19', '2026-11-05', 'MOVED-OUTSIDE', 30_000_000, [
                'cabang1' => 'KC Kediri', 'cabang_normalized' => 'KC KEDIRI',
                'segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'COMMERCIAL', 'baki_debet1' => 0,
            ]),
            $this->row('2026-12-19', '2026-11-05', 'MOVED-TO-BRIGUNA', 40_000_000, [
                'produk_kinerja' => 'BRIGUNAMIKRO', 'description' => 'KREDIT MIKRO - GBT', 'baki_debet1' => 0,
            ]),
            $this->row('2026-12-19', '2026-12-05', 'NEW-BOOKING', 100_000_000),
        ]);

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-12-19');
        $this->assertCount(1, $rows);
        $this->assertSame(0.0, $rows[0]['previous_os']);
        $this->assertSame(100_000_000.0, $rows[0]['net_amount']);
    }

    public function test_month_to_date_booking_survives_when_absent_from_current_position(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-11-30', '2026-11-05', 'CLOSED-BASELINE', 50_000_000),
            $this->row('2026-12-08', '2026-12-05', '12345', 100_000_000),
            $this->row('2026-12-10', '2026-12-05', '00012345', 100_000_000),
            $this->row('2026-12-19', '2026-11-05', 'POSITION-ANCHOR', 1, ['cifno' => 'OTHER-CIF']),
        ]);

        $this->inputStore()->captureDailyLoanPeriod('2026-12-08');
        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-12-19');
        $this->assertCount(1, $rows);
        $this->assertSame('12345', $rows[0]['account_key']);
        $this->assertSame(100_000_000.0, $rows[0]['plafon']);
        $this->assertSame(50_000_000.0, $rows[0]['previous_os']);
        $this->assertSame(50_000_000.0, $rows[0]['net_amount']);
        $this->assertSame('2026-12-05', $rows[0]['realization_date']);
    }

    public function test_latest_observed_initiator_wins_and_future_positions_are_ignored(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-12-08', '2026-12-05', '00000042', 100_000_000, [
                'pn_pemrakarsa1' => '00000801 - Previous Initiator',
            ]),
            $this->row('2026-12-19', '2026-12-05', '42', 120_000_000, [
                'pn_pemrakarsa1' => '00000802 - Current Initiator',
            ]),
            $this->row('2026-12-19', '2026-12-05', '00042', 120_000_000, [
                'pn_pemrakarsa1' => '00000803 - Latest Initiator',
                'unit_normalized' => 'UNIT LATEST', 'unit1' => 'Unit Latest',
            ]),
            $this->row('2026-12-20', '2026-12-05', '42', 999_000_000, [
                'pn_pemrakarsa1' => '00000804 - Future Initiator',
            ]),
            $this->row('2026-12-19', '2026-12-20', 'FUTURE-BOOKING', 999_000_000),
        ]);

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-12-19');
        $this->assertCount(1, $rows);
        $this->assertSame('42', $rows[0]['account_key']);
        $this->assertSame('00000803 - LATEST INITIATOR', $rows[0]['owner']);
        $this->assertSame('UNIT LATEST', $rows[0]['unit']);
        $this->assertSame(120_000_000.0, $rows[0]['plafon']);
        $this->assertSame(120_000_000.0, $rows[0]['net_amount']);
    }

    public function test_each_booking_uses_full_cif_baseline_and_is_floored_before_pn_aggregation(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-11-30', '2026-11-05', 'CLOSED-BASELINE', 150_000_000),
            $this->row('2026-12-19', '2026-12-05', 'NEW-A', 100_000_000),
            $this->row('2026-12-19', '2026-12-06', 'NEW-B', 50_000_000),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $rows = collect($calculator->mantriAccounts('2026-12-19'))->keyBy('account_key');
        $this->assertCount(2, $rows);
        $this->assertSame(150_000_000.0, $rows['NEW-A']['previous_os']);
        $this->assertSame(150_000_000.0, $rows['NEW-B']['previous_os']);
        $this->assertSame(0.0, $rows['NEW-A']['net_amount']);
        $this->assertSame(0.0, $rows['NEW-B']['net_amount']);
        $metrics = $calculator->mantri('2026-12-19');
        $this->assertCount(1, $metrics);
        $this->assertSame(2, $metrics[0]['realisasi_deb']);
        $this->assertSame(0.0, $metrics[0]['realisasi_os']);
    }

    public static function approvedOwnershipCases(): array
    {
        return [
            'complete approved lineage' => [[], true],
            'different account' => [['account' => '99999'], false],
            'different CIF' => [['cif' => 'DIFFERENT-CIF'], false],
            'different booking date' => [['realization_date' => '2026-12-06'], false],
            'future evidence' => [['as_of' => '2026-12-20'], false],
            'zero identity' => [['pn' => '00000000'], false],
            'unmarked old owner evidence is not an initiator source' => [['owner_basis' => null], false],
            'obsolete SinglePN owner evidence' => [['owner_basis' => 'pn_pengelola1', 'source_owner_column' => 'AK'], false],
            'explicit AM evidence from an earlier available position' => [['owner_basis' => null, 'source_owner_column' => 'AM', 'as_of' => '2026-12-16'], true],
        ];
    }

    #[DataProvider('approvedOwnershipCases')]
    public function test_approved_booking_owner_requires_exact_account_cif_date_and_available_evidence(array $overrides, bool $approved): void
    {
        DB::table('daily_loan_dinamis')->insert($this->row('2026-12-19', '2026-12-05', '12345', 100_000_000, [
            'pn_pemrakarsa1' => '00000802 - Current Initiator',
        ]));
        $store = $this->inputStore();
        $store->write('2026-12-19', [
            'source' => ['source_kind' => 'manual_workbook', 'file' => 'synthetic-approved-lineage.xlsx'],
            'complete_presence' => true, 'presence' => ['12345'], 'bookings' => [], 'unclassified_accounts' => [],
            'owner_evidence' => [array_replace([
                'account' => '00012345', 'cif' => 'EXAMPLE-CIF', 'realization_date' => '2026-12-05',
                'pn' => '801', 'name' => 'Approved Booking Owner', 'as_of' => '2026-12-19',
                'owner_basis' => 'pn_pemrakarsa1',
            ], $overrides)],
        ]);

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-12-19');
        $this->assertCount(1, $rows);
        $this->assertSame($approved ? '00000801 - APPROVED BOOKING OWNER' : '00000802 - CURRENT INITIATOR', $rows[0]['owner']);
        $this->assertSame($approved ? 'mantri_origination_ledger' : 'daily_loan_dinamis', $rows[0]['identity_source']);
        $this->assertSame('KC MADIUN', $rows[0]['cabang']);
        $this->assertSame('UNIT EXAMPLE', $rows[0]['unit']);
        $this->assertSame('2026-12-05', $rows[0]['realization_date']);
        $this->assertSame(100_000_000.0, $rows[0]['net_amount']);
    }

    public static function blankInitiators(): array
    {
        return [[null], [''], [' - '], ['00000000 - Missing']];
    }

    #[DataProvider('blankInitiators')]
    public function test_blank_initiator_is_not_guessed_from_a_nonblank_manager(?string $initiator): void
    {
        DB::table('daily_loan_dinamis')->insert($this->row('2026-12-19', '2026-12-05', '12345', 100_000_000, [
            'pn_pemrakarsa1' => $initiator, 'pn_pengelola1' => '00000802 - Current Manager',
        ]));

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-12-19');
        $this->assertCount(1, $rows);
        $this->assertSame('RM LAIN', $rows[0]['owner']);
        $this->assertSame('daily_loan_dinamis', $rows[0]['identity_source']);
        $this->assertSame(100_000_000.0, $rows[0]['net_amount']);
    }

    public function test_explicit_am_source_from_october_16_remains_available_for_a_later_report(): void
    {
        DB::table('daily_loan_dinamis')->insert($this->row('2026-10-17', '2026-10-05', '12345', 100_000_000, [
            'pn_pemrakarsa1' => null, 'pn_pengelola1' => '00000802 - Current Manager',
        ]));
        $this->inputStore()->write('2026-10-16', [
            'source' => ['source_kind' => 'manual_workbook', 'source_owner_column' => 'AM'],
            'complete_presence' => false, 'presence' => [], 'bookings' => [], 'unclassified_accounts' => [],
            'owner_evidence' => [
                ['account' => '12345', 'cif' => 'EXAMPLE-CIF', 'realization_date' => '2026-10-05',
                    'pn' => '801', 'name' => 'Approved Initiator', 'as_of' => '2026-10-16'],
            ],
        ]);

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-10-17');
        $this->assertCount(1, $rows);
        $this->assertSame('00000801 - APPROVED INITIATOR', $rows[0]['owner']);
        $this->assertSame('mantri_origination_ledger', $rows[0]['identity_source']);
        $this->assertSame('UNIT EXAMPLE', $rows[0]['unit']);
    }

    public function test_unclassified_baseline_is_account_level_and_full_presence_prevents_false_closure(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-11-30', '2026-11-05', '5001', 20_000_000),
            $this->row('2026-12-19', '2026-12-05', '6001', 100_000_000),
        ]);
        $store = $this->inputStore();
        $unknown = fn (string $account, float $balance): array => $this->row('2026-11-30', '2026-11-05', $account, $balance, [
            'segmen_kinerja' => null, 'produk_kinerja' => null, 'description' => null,
        ]);
        $store->write('2026-11-30', [
            'source' => ['source_kind' => 'manual_workbook_baseline'], 'complete_presence' => false,
            'presence' => [], 'bookings' => [], 'unclassified_accounts' => [
                $unknown('5001', 999_000_000),
                $unknown('5002', 40_000_000),
                $unknown('5003', 30_000_000),
            ],
        ]);
        $store->write('2026-12-19', [
            'source' => ['source_kind' => 'manual_workbook_position'], 'complete_presence' => true,
            'presence' => ['6001', '5003'], 'bookings' => [], 'unclassified_accounts' => [],
        ]);

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-12-19');
        $this->assertCount(1, $rows);
        $this->assertSame(60_000_000.0, $rows[0]['previous_os']);
        $this->assertSame(40_000_000.0, $rows[0]['net_amount']);
        $this->assertSame('mantri_source_input', $rows[0]['baseline_source']);
        $this->assertSame(['2026-12-19'], $store->periods());
    }

    public function test_complete_archived_position_supplies_a_missing_database_day_and_preserves_unknown_bookings(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-09-30', '2026-09-05', '9001', 50_000_000),
            $this->row('2026-10-16', '2026-10-05', '1001', 100_000_000),
        ]);
        $store = $this->inputStore();
        $position = [
            'source' => ['source_kind' => 'manual_workbook_position'], 'complete_presence' => true,
            'presence' => ['1001', '1002'], 'unclassified_accounts' => [], 'bookings' => [
                $this->row('2026-10-17', '2026-10-05', '1001', 100_000_000, [
                    'pn_pemrakarsa1' => '00000802 - Current Initiator',
                ]),
                $this->row('2026-10-17', '2026-10-16', '1002', 70_000_000, [
                    'cifno' => 'UNKNOWN-PRODUCT-CIF', 'description' => null,
                    'segmen_kinerja' => null, 'produk_kinerja' => null,
                    'pn_pemrakarsa1' => '00000803 - Unknown Product Initiator',
                ]),
            ],
        ];
        $store->write('2026-10-17', $position);
        $fingerprint = $store->fingerprint('2026-10-17');
        $future = $position;
        $future['bookings'] = [$this->row('2026-10-19', '2026-10-05', '1001', 900_000_000, [
            'pn_pemrakarsa1' => '00000804 - Future Initiator',
        ])];
        $store->write('2026-10-19', $future);

        $rows = collect(app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-10-17'))->keyBy('account_key');
        $this->assertCount(2, $rows);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-10-17')->count());
        $this->assertSame('00000802 - CURRENT INITIATOR', $rows['1001']['owner']);
        $this->assertSame(50_000_000.0, $rows['1001']['previous_os']);
        $this->assertSame(50_000_000.0, $rows['1001']['net_amount']);
        $this->assertSame('00000803 - UNKNOWN PRODUCT INITIATOR', $rows['1002']['owner']);
        $this->assertSame(70_000_000.0, $rows['1002']['net_amount']);
        $this->assertNull($store->read('2026-10-17')['bookings'][1]['produk_kinerja']);
        $this->assertSame($fingerprint, $store->fingerprint('2026-10-17'));
    }

    public function test_next_month_and_import_use_their_own_closed_baseline_and_do_not_leak_future_credit(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-10-19', '2026-10-05', '7000', 999_000_000),
            $this->row('2026-10-30', '2026-10-05', 'OLDER-BASELINE', 999_000_000),
            $this->row('2026-10-31', '2026-10-05', '9001', 60_000_000),
            $this->row('2026-10-31', '2026-10-05', '9002', 20_000_000, [
                'produk_kinerja' => 'BRIGUNAMIKRO', 'description' => 'KREDIT MIKRO - GBT',
            ]),
            $this->row('2026-10-31', '2026-10-05', '9003', 30_000_000),
            $this->row('2026-10-31', '2026-10-05', '9004', 90_000_000, [
                'produk_kinerja' => 'KURKECIL', 'description' => 'Kredit Mikro - KUR Ritel 2015',
            ]),
            $this->row('2026-11-10', '2026-11-05', '8001', 50_000_000),
            $this->row('2026-11-19', '2026-10-05', '9003', 30_000_000, ['baki_debet1' => 0]),
            $this->row('2026-11-19', '2026-11-19', '8002', 100_000_000),
            $this->row('2026-11-20', '2026-11-05', '8001', 900_000_000, [
                'pn_pemrakarsa1' => '00000803 - Future Initiator',
            ]),
        ]);
        $store = $this->inputStore();
        $store->captureDailyLoanPeriod('2026-11-10');
        $store->write('2026-10-17', [
            'source' => ['source_kind' => 'manual_workbook_position'], 'complete_presence' => true,
            'presence' => ['8001'], 'bookings' => [], 'unclassified_accounts' => [], 'owner_evidence' => [
                ['account' => '8001', 'cif' => 'EXAMPLE-CIF', 'realization_date' => '2026-10-05',
                    'pn' => '899', 'name' => 'Prior Month Owner', 'as_of' => '2026-10-17', 'owner_basis' => 'pn_pemrakarsa1'],
            ],
        ]);
        $store->write('2026-11-20', [
            'source' => ['source_kind' => 'next_import_position'], 'complete_presence' => true,
            'presence' => ['8001', '8002', '9003'], 'bookings' => [], 'unclassified_accounts' => [], 'owner_evidence' => [
                ['account' => '8001', 'cif' => 'EXAMPLE-CIF', 'realization_date' => '2026-11-05',
                    'pn' => '804', 'name' => 'Approved New Month Owner', 'as_of' => '2026-11-20', 'owner_basis' => 'pn_pemrakarsa1'],
            ],
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $before = collect($calculator->mantriAccounts('2026-11-19'))->keyBy('account_key');
        $this->assertEqualsCanonicalizing(['8001', '8002'], $before->keys()->map('strval')->all());
        $this->assertSame(80_000_000.0, $before['8001']['previous_os']);
        $this->assertSame(0.0, $before['8001']['net_amount']);
        $this->assertSame(20_000_000.0, $before['8002']['net_amount']);
        $this->assertSame('00000901 - INITIATOR', $before['8001']['owner']);
        $this->assertSame(150_000_000.0, $before->sum('plafon'));
        $this->assertSame(20_000_000.0, $before->sum('net_amount'));
        $after = collect($calculator->mantriAccounts('2026-11-20'))->keyBy('account_key');
        $this->assertEqualsCanonicalizing(['8001', '8002'], $after->keys()->map('strval')->all());
        $this->assertSame(80_000_000.0, $after['8001']['previous_os']);
        $this->assertSame(820_000_000.0, $after['8001']['net_amount']);
        $this->assertSame('00000804 - APPROVED NEW MONTH OWNER', $after['8001']['owner']);
        $this->assertSame('mantri_origination_ledger', $after['8001']['identity_source']);
        $this->assertSame(840_000_000.0, $after->sum('net_amount'));
    }

    public function test_overlapping_report_dates_reuse_position_reads_and_import_version_refreshes_them(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-09-30', '2026-09-05', '9001', 50_000_000),
            $this->row('2026-10-13', '2026-10-05', '1001', 100_000_000),
            $this->row('2026-10-17', '2026-09-05', 'POSITION-ANCHOR', 1, ['cifno' => 'OTHER-CIF']),
            $this->row('2026-10-19', '2026-10-19', '1002', 80_000_000, ['cifno' => 'OTHER-CIF']),
        ]);
        $this->inputStore()->captureDailyLoanPeriod('2026-10-13');
        $calculator = app(MicroNettDisbursementCalculator::class);
        $first = $calculator->mantriAccounts('2026-10-17');
        $this->assertCount(1, $first);
        $this->assertSame(50_000_000.0, $first[0]['net_amount']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $second = $calculator->mantriAccounts('2026-10-19');
            $this->assertCount(2, $second);
            $this->assertSame(130_000_000.0, collect($second)->sum('net_amount'));
            $reads = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "daily_loan_dinamis"')
                && str_contains($query['query'], '"plafon"'))->values();
            $this->assertCount(1, $reads);
            $this->assertContains('2026-10-19', $reads[0]['bindings']);
            $this->assertNotContains('2026-10-13', $reads[0]['bindings']);
            $this->assertNotContains('2026-10-17', $reads[0]['bindings']);
            DB::table('daily_loan_dinamis')->where('periode', '2026-10-13')->update(['plafon' => 120_000_000]);
            ReportCacheVersion::bump('pinjaman');
            $afterImport = $calculator->mantriAccounts('2026-10-19');
            $this->assertCount(2, $afterImport);
            $this->assertSame(150_000_000.0, collect($afterImport)->sum('net_amount'));
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_warming_a_later_report_does_not_leak_future_booking_dates_into_cached_earlier_positions(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-10-17', '2026-10-17', '1001', 50_000_000),
            $this->row('2026-10-17', '2026-10-18', '1002', 100_000_000),
            $this->row('2026-10-19', '2026-09-05', 'POSITION-ANCHOR', 1, ['cifno' => 'OTHER-CIF']),
        ]);
        $this->inputStore()->captureDailyLoanPeriod('2026-10-17');
        $calculator = app(MicroNettDisbursementCalculator::class);
        $later = $calculator->mantriAccounts('2026-10-19');
        $this->assertCount(2, $later);
        $this->assertSame(150_000_000.0, collect($later)->sum('net_amount'));
        $earlier = $calculator->mantriAccounts('2026-10-17');
        $this->assertCount(1, $earlier);
        $this->assertSame('1001', $earlier[0]['account_key']);
        $this->assertSame(50_000_000.0, $earlier[0]['net_amount']);
    }

    public function test_owner_evidence_supplement_does_not_declare_a_missing_position_complete(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-10-17', '2026-10-17', '1001', 50_000_000, ['pn_pengelola1' => '00000802 - Current Manager']),
            $this->row('2026-10-20', '2026-10-17', '1001', 50_000_000, ['pn_pengelola1' => '00000802 - Current Manager']),
        ]);
        $store = $this->inputStore();
        $store->write('2026-10-19', [
            'source' => ['source_kind' => 'manual_owner_evidence'], 'complete_presence' => false,
            'presence' => [], 'bookings' => [], 'unclassified_accounts' => [], 'owner_evidence' => [
                ['account' => '1001', 'cif' => 'EXAMPLE-CIF', 'realization_date' => '2026-10-17',
                    'pn' => '801', 'name' => 'Approved Booking Owner', 'as_of' => '2026-10-19', 'owner_basis' => 'pn_pemrakarsa1'],
            ],
        ]);
        $calculator = app(MicroNettDisbursementCalculator::class);
        $this->assertSame([], $store->periods());
        $this->assertSame([], $calculator->mantriAccounts('2026-10-19'));
        $later = $calculator->mantriAccounts('2026-10-20');
        $this->assertCount(1, $later);
        $this->assertSame('00000801 - APPROVED BOOKING OWNER', $later[0]['owner']);
        $this->assertSame('mantri_origination_ledger', $later[0]['identity_source']);
        $this->assertSame(50_000_000.0, $later[0]['net_amount']);
    }

    public static function rawSinglePnCases(): array
    {
        return [
            'explicit raw manager blank does not replace the initiator' => [true, true, '00000901 - INITIATOR'],
            'native initiator has source precedence' => [false, true, '00000901 - INITIATOR'],
            'legacy archive manager evidence does not alter the initiator' => [true, false, '00000901 - INITIATOR'],
        ];
    }

    #[DataProvider('rawSinglePnCases')]
    public function test_raw_singlepn_evidence_does_not_change_initiator_attribution(bool $materialized, bool $rawAvailable, string $owner): void
    {
        $raw = $this->row('2026-10-19', '2026-10-05', '1001', 100_000_000, [
            'pn_pengelola1' => '00000802 - Current Manager',
            'uniqueid_namareport' => ($materialized ? Lw321DailyLoanSyncService::TARGET_ID_PREFIX : 'NATIVE:').'synthetic-booking',
        ]);
        DB::table('daily_loan_dinamis')->insert($raw);
        $archive = $raw;
        $archive['pn_pengelola1'] = null;
        $archive['pn_pengelola_singlepn'] = null;
        $archive['raw_singlepn_available'] = $rawAvailable;
        $this->inputStore()->write('2026-10-19', [
            'source' => ['source_kind' => 'lw321_import'], 'complete_presence' => true,
            'presence' => ['1001'], 'bookings' => [$archive], 'unclassified_accounts' => [],
        ]);

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-10-19');
        $this->assertCount(1, $rows);
        $this->assertSame($owner, $rows[0]['owner']);
        $this->assertSame(100_000_000.0, $rows[0]['plafon']);
        $this->assertSame(100_000_000.0, $rows[0]['net_amount']);
        $this->assertSame('KUPEDES', $rows[0]['product']);
    }

    public static function unknownBaselineSources(): array
    {
        return [
            'automatic raw import has unit-only Micro evidence' => ['lw321_import', 50_000_000.0, 50_000_000.0],
            'manual cohort explicitly verifies unknown KC account' => ['manual_workbook_baseline', 120_000_000.0, 0.0],
        ];
    }

    #[DataProvider('unknownBaselineSources')]
    public function test_next_month_unknown_baseline_respects_verified_cohort_and_keeps_classified_gbt(string $sourceKind, float $baseline, float $net): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-10-31', '2026-10-05', 'CLASSIFIED-GBT', 20_000_000, [
                'produk_kinerja' => 'BRIGUNAMIKRO', 'description' => 'KREDIT MIKRO - GBT', 'unit1' => 'KC Madiun',
                'uniqueid_namareport' => Lw321DailyLoanSyncService::TARGET_ID_PREFIX.'classified-baseline',
            ]),
            $this->row('2026-11-19', '2026-11-05', 'NEW-BOOKING', 100_000_000),
        ]);
        $unknown = fn (string $account, float $balance, string $unit): array => $this->row('2026-10-31', '2026-10-05', $account, $balance, [
            'description' => null, 'produk_kinerja' => null, 'segmen_kinerja' => null, 'unit1' => $unit,
        ]);
        $store = $this->inputStore();
        $store->write('2026-10-31', [
            'source' => ['source_kind' => $sourceKind], 'complete_presence' => false,
            'presence' => [], 'bookings' => [], 'unclassified_accounts' => [
                $unknown('UNKNOWN-UNIT', 30_000_000, 'Unit Source'),
                $unknown('UNKNOWN-KC', 70_000_000, 'KC Madiun'),
            ],
        ]);
        $store->write('2026-11-19', [
            'source' => ['source_kind' => 'lw321_import'], 'complete_presence' => true,
            'presence' => ['NEW-BOOKING'], 'bookings' => [], 'unclassified_accounts' => [],
        ]);

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-11-19');
        $this->assertCount(1, $rows);
        $this->assertSame($baseline, $rows[0]['previous_os']);
        $this->assertSame($net, $rows[0]['net_amount']);
        $this->assertSame('mantri_source_input', $rows[0]['baseline_source']);
    }

    public static function excludedClassificationCorrections(): array
    {
        return [
            'GBT product correction' => ['BRIGUNAMIKRO', 'KREDIT MIKRO - GBT'],
            'KUR Kecil product correction' => ['KURKECIL', 'Kredit Mikro - KUR Ritel 2015'],
            'canonical native KUR Ritel token correction' => ['KURRITEL', 'Kredit Usaha Rakyat Ritel'],
            'KUR Ritel description correction' => ['KURMIKRO', 'Kredit Mikro - KUR Ritel 2015'],
        ];
    }

    #[DataProvider('excludedClassificationCorrections')]
    public function test_latest_classification_can_remove_an_older_eligible_booking_without_rewriting_earlier_reports(string $product, string $description): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-10-13', '2026-10-05', '1001', 100_000_000),
            $this->row('2026-10-17', '2026-09-05', 'POSITION-ANCHOR', 1, ['cifno' => 'OTHER-CIF']),
            $this->row('2026-10-19', '2026-10-05', '1001', 100_000_000, [
                'produk_kinerja' => $product, 'description' => $description,
            ]),
            $this->row('2026-10-19', '2026-10-19', '1002', 80_000_000, ['cifno' => 'OTHER-CIF']),
        ]);
        $this->inputStore()->captureDailyLoanPeriod('2026-10-13');
        $calculator = app(MicroNettDisbursementCalculator::class);
        $earlier = $calculator->mantriAccounts('2026-10-17');
        $this->assertCount(1, $earlier);
        $this->assertSame('1001', $earlier[0]['account_key']);
        $this->assertSame(100_000_000.0, $earlier[0]['net_amount']);
        $corrected = $calculator->mantriAccounts('2026-10-19');
        $this->assertCount(1, $corrected);
        $this->assertSame('1002', $corrected[0]['account_key']);
        $this->assertSame(80_000_000.0, $corrected[0]['net_amount']);
    }

    #[DataProvider('excludedClassificationCorrections')]
    public function test_same_position_native_classification_supersedes_eligible_archived_lw321_booking(string $product, string $description): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-10-19', '2026-10-05', '1001', 100_000_000, [
                'uniqueid_namareport' => 'NATIVE:corrected-booking', 'produk_kinerja' => $product, 'description' => $description,
            ]),
            $this->row('2026-10-19', '2026-10-19', '1002', 80_000_000, [
                'uniqueid_namareport' => 'NATIVE:other-booking', 'cifno' => 'OTHER-CIF',
            ]),
        ]);
        $this->inputStore()->write('2026-10-19', [
            'source' => ['source_kind' => 'lw321_import'], 'complete_presence' => true,
            'presence' => ['1001', '1002'], 'unclassified_accounts' => [], 'bookings' => [
                $this->row('2026-10-19', '2026-10-05', '1001', 100_000_000),
            ],
        ]);

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-10-19');
        $this->assertCount(1, $rows);
        $this->assertSame('1002', $rows[0]['account_key']);
        $this->assertSame(80_000_000.0, $rows[0]['net_amount']);
    }

    public static function currentRawSourcePrecedenceCases(): array
    {
        return [
            'native source overrides raw despite matching signature' => [true, 2],
            'native source overrides changed raw generation' => [true, 3],
            'materialized source keeps full raw presence despite signature drift' => [false, 3],
        ];
    }

    #[DataProvider('currentRawSourcePrecedenceCases')]
    public function test_current_native_source_supersedes_raw_archive_but_materialized_source_keeps_full_presence(bool $native, int $capturedRows): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-09-30', '2026-09-05', '9001', 60_000_000),
            $this->row('2026-10-19', '2026-10-05', '1001', 100_000_000, [
                'uniqueid_namareport' => ($native ? 'NATIVE:' : Lw321DailyLoanSyncService::TARGET_ID_PREFIX).'corrected-booking',
                'pn_pengelola1' => '00000802 - Current Manager',
            ]),
            $this->row('2026-10-19', '2026-09-05', '9999', 1, [
                'uniqueid_namareport' => ($native ? 'NATIVE:' : Lw321DailyLoanSyncService::TARGET_ID_PREFIX).'position-anchor',
                'cifno' => 'OTHER-CIF',
            ]),
        ]);
        $store = $this->inputStore();
        $store->write('2026-10-17', [
            'source' => ['source_kind' => 'lw321_import', 'daily_signature' => ['row_count' => 1, 'max_updated_at' => null]],
            'complete_presence' => true, 'presence' => ['1003'], 'unclassified_accounts' => [], 'bookings' => [
                $this->row('2026-10-17', '2026-10-02', '1003', 30_000_000, ['cifno' => 'OTHER-CIF']),
            ],
        ]);
        $store->write('2026-10-19', [
            'source' => ['source_kind' => 'lw321_import', 'daily_signature' => ['row_count' => $capturedRows, 'max_updated_at' => null]],
            'complete_presence' => true, 'presence' => ['9001', '1001', '1009'], 'unclassified_accounts' => [], 'bookings' => [
                $this->row('2026-10-19', '2026-10-05', '1001', $native ? 900_000_000 : 100_000_000),
                $this->row('2026-10-19', '2026-10-19', '1009', 70_000_000, ['cifno' => 'OTHER-CIF']),
            ],
            'owner_evidence' => [
                ['account' => '1001', 'cif' => 'EXAMPLE-CIF', 'realization_date' => '2026-10-05',
                    'pn' => '801', 'name' => 'Approved Booking Owner', 'as_of' => '2026-10-19', 'owner_basis' => 'pn_pemrakarsa1'],
            ],
        ]);

        $rows = collect(app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-10-19'))->keyBy('account_key');
        $this->assertEqualsCanonicalizing($native ? ['1001', '1003'] : ['1001', '1003', '1009'], $rows->keys()->map('strval')->all());
        $this->assertSame(100_000_000.0, $rows['1001']['plafon']);
        $this->assertSame($native ? 60_000_000.0 : 0.0, $rows['1001']['previous_os']);
        $this->assertSame($native ? 40_000_000.0 : 100_000_000.0, $rows['1001']['net_amount']);
        $this->assertSame('00000801 - APPROVED BOOKING OWNER', $rows['1001']['owner']);
        $this->assertSame('mantri_origination_ledger', $rows['1001']['identity_source']);
        $this->assertSame(30_000_000.0, $rows['1003']['net_amount']);
        $this->assertSame($native ? 130_000_000.0 : 200_000_000.0, $rows->sum('plafon'));
        $this->assertSame($native ? 70_000_000.0 : 200_000_000.0, $rows->sum('net_amount'));
    }

    public static function previousRawSourcePrecedenceCases(): array
    {
        return [
            'native month end supersedes old raw baseline' => [true, 70_000_000.0, 30_000_000.0],
            'materialized month end keeps verified raw unknown supplement' => [false, 1_069_000_000.0, 0.0],
        ];
    }

    #[DataProvider('previousRawSourcePrecedenceCases')]
    public function test_next_month_baseline_respects_native_precedence_without_classifying_unknown_kc_exposure(bool $native, float $baseline, float $net): void
    {
        $prefix = $native ? 'NATIVE:' : Lw321DailyLoanSyncService::TARGET_ID_PREFIX;
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-10-31', '2026-10-05', 'CURRENT-UNIT-UNKNOWN', 50_000_000, [
                'uniqueid_namareport' => $prefix.'unknown-unit', 'unit1' => 'UNIT BASELINE', 'unit_normalized' => 'UNIT BASELINE',
                'segmen_kinerja' => null, 'produk_kinerja' => null, 'description' => null,
            ]),
            $this->row('2026-10-31', '2026-10-05', 'CURRENT-GBT-KC', 20_000_000, [
                'uniqueid_namareport' => $prefix.'classified-gbt', 'unit1' => 'KC Madiun', 'unit_normalized' => 'KC MADIUN',
                'produk_kinerja' => 'BRIGUNAMIKRO', 'description' => 'KREDIT MIKRO - GBT',
            ]),
            $this->row('2026-10-31', '2026-10-05', 'CURRENT-KC-UNKNOWN', 37_000_000, [
                'uniqueid_namareport' => $prefix.'unknown-kc', 'unit1' => 'KC Madiun', 'unit_normalized' => 'KC MADIUN',
                'segmen_kinerja' => null, 'produk_kinerja' => null, 'description' => null,
            ]),
            $this->row('2026-11-19', '2026-11-05', 'NEW-BOOKING', 100_000_000),
        ]);
        $this->inputStore()->write('2026-10-31', [
            'source' => ['source_kind' => 'lw321_import'], 'complete_presence' => false,
            'presence' => [], 'bookings' => [], 'unclassified_accounts' => [
                $this->row('2026-10-31', '2026-10-05', 'RAW-ONLY-UNIT', 999_000_000, [
                    'segmen_kinerja' => null, 'produk_kinerja' => null, 'description' => null,
                    'unit1' => 'UNIT OLD RAW', 'unit_normalized' => 'UNIT OLD RAW',
                ]),
            ],
        ]);

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-11-19');
        $this->assertCount(1, $rows);
        $this->assertSame($baseline, $rows[0]['previous_os']);
        $this->assertSame($net, $rows[0]['net_amount']);
        $this->assertSame($native ? 'daily_loan_dinamis' : 'mantri_source_input', $rows[0]['baseline_source']);
        $unknownRows = DB::table('daily_loan_dinamis')->where('periode', '2026-10-31')
            ->whereIn('nomor_rekening1', ['CURRENT-UNIT-UNKNOWN', 'CURRENT-KC-UNKNOWN'])->get();
        $this->assertCount(2, $unknownRows);
        $this->assertSame([null], $unknownRows->pluck('segmen_kinerja')->unique()->values()->all());
        $this->assertSame([null], $unknownRows->pluck('produk_kinerja')->unique()->values()->all());
    }

    public function test_raw_archived_classified_micro_and_gbt_at_kc_are_kept_when_month_end_database_rows_are_absent(): void
    {
        DB::table('daily_loan_dinamis')->insert($this->row('2026-11-19', '2026-11-05', 'NEW-BOOKING', 100_000_000));
        $this->inputStore()->write('2026-10-31', [
            'source' => ['source_kind' => 'lw321_import'], 'complete_presence' => false,
            'presence' => [], 'bookings' => [], 'unclassified_accounts' => [
                $this->row('2026-10-31', '2026-10-05', 'UNKNOWN-KC', 30_000_000, [
                    'unit1' => 'KC Madiun', 'description' => null, 'segmen_kinerja' => null, 'produk_kinerja' => null,
                ]),
            ],
            'baseline_accounts' => [
                $this->row('2026-10-31', '2026-10-05', 'CLASSIFIED-GBT-KC', 20_000_000, [
                    'unit1' => 'KC Madiun', 'description' => 'KREDIT MIKRO - GBT', 'produk_kinerja' => 'BRIGUNAMIKRO',
                ]),
                $this->row('2026-10-31', '2026-10-05', 'CLASSIFIED-MICRO-KC', 40_000_000, ['unit1' => 'KC Madiun']),
                $this->row('2026-10-31', '2026-10-05', 'UNKNOWN-UNIT', 10_000_000, [
                    'description' => null, 'segmen_kinerja' => null, 'produk_kinerja' => null,
                ]),
            ],
        ]);

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-11-19');
        $this->assertCount(1, $rows);
        $this->assertSame(70_000_000.0, $rows[0]['previous_os']);
        $this->assertSame(30_000_000.0, $rows[0]['net_amount']);
        $this->assertSame('mantri_source_input', $rows[0]['baseline_source']);
    }

    public static function canonicalNativeBaselineCases(): array
    {
        return [
            'classified Micro canonical branch' => ['MICRO', 'KUPEDES', 'KREDIT MIKRO - KUPEDES'],
            'unclassified canonical Unit scope' => [null, null, null],
        ];
    }

    #[DataProvider('canonicalNativeBaselineCases')]
    public function test_native_month_end_baseline_uses_its_canonical_branch_and_unit(?string $segment, ?string $product, ?string $description): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-10-31', '2026-10-05', 'CANONICAL-BASELINE', 30_000_000, [
                'cabang1' => '', 'unit1' => '', 'cabang_normalized' => 'KC MADIUN', 'unit_normalized' => 'UNIT CANONICAL',
                'segmen_kinerja' => $segment, 'produk_kinerja' => $product, 'description' => $description,
            ]),
            $this->row('2026-11-19', '2026-11-05', 'NEW-BOOKING', 100_000_000),
        ]);
        $store = $this->inputStore();
        $saved = $store->captureDailyLoanPeriod('2026-10-31');
        $this->assertCount(1, $saved['baseline_accounts']);
        $baseline = iterator_to_array($store->baselineAccounts($saved));
        $this->assertSame('KC MADIUN', strtoupper((string) $baseline[0]['cabang1']));

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-11-19');
        $this->assertCount(1, $rows);
        $this->assertSame(30_000_000.0, $rows[0]['previous_os']);
        $this->assertSame(70_000_000.0, $rows[0]['net_amount']);
    }

    public static function observedInitiatorCases(): array
    {
        return [
            'same booking later null preserves observed initiator' => [null, 'EXAMPLE-CIF', '2026-12-05', '00000801 - OBSERVED INITIATOR'],
            'same booking later malformed blank preserves observed initiator' => [' - ', 'EXAMPLE-CIF', '2026-12-05', '00000801 - OBSERVED INITIATOR'],
            'same booking later explicit correction wins' => ['00000803 - Corrected Initiator', 'EXAMPLE-CIF', '2026-12-05', '00000803 - CORRECTED INITIATOR'],
            'changed CIF does not inherit another origination' => [null, 'DIFFERENT-CIF', '2026-12-05', 'RM LAIN'],
            'changed booking date does not inherit another origination' => [null, 'EXAMPLE-CIF', '2026-12-06', 'RM LAIN'],
        ];
    }

    #[DataProvider('observedInitiatorCases')]
    public function test_previous_initiator_evidence_only_survives_a_blank_update_of_the_same_booking(?string $laterInitiator, string $laterCif, string $laterBooking, string $expectedOwner): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-12-08', '2026-12-05', '00012345', 100_000_000, [
                'pn_pemrakarsa1' => '00000801 - Observed Initiator', 'pn_pengelola1' => '00000902 - Earlier Manager',
            ]),
            $this->row('2026-12-19', $laterBooking, '12345', 100_000_000, [
                'pn_pemrakarsa1' => $laterInitiator, 'pn_pengelola1' => '00000903 - Latest Manager', 'cifno' => $laterCif,
            ]),
        ]);

        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-12-19');
        $this->assertCount(1, $rows);
        $this->assertSame($expectedOwner, $rows[0]['owner']);
        $this->assertSame($laterCif, $rows[0]['cif_key']);
        $this->assertSame($laterBooking, $rows[0]['realization_date']);
        $this->assertSame(100_000_000.0, $rows[0]['net_amount']);
    }

    private function inputStore(): MantriSourceInputStore
    {
        $directory = sys_get_temp_dir().'/mantri-manual-formula-'.bin2hex(random_bytes(8));
        $this->inputDirectories[] = $directory;
        $store = new MantriSourceInputStore($directory);
        $this->app->instance(MantriSourceInputStore::class, $store);

        return $store;
    }

    private function row(string $period, string $bookingDate, string $account, float $amount, array $overrides = []): array
    {
        return array_merge([
            'periode' => $period, 'tgl_realisasi' => $bookingDate,
            'cabang_normalized' => 'KC MADIUN', 'cabang1' => 'KC Madiun',
            'unit_normalized' => 'UNIT EXAMPLE', 'unit1' => 'Unit Example',
            'branch_normalized' => '45', 'branch1' => '45',
            'pn_pemrakarsa1' => '00000901 - Initiator', 'pn_pengelola1' => '00000801 - SinglePN',
            'nomor_rekening1' => $account, 'cifno' => 'EXAMPLE-CIF',
            'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KUPEDES',
            'description' => 'KREDIT MIKRO - KUPEDES', 'plafon' => $amount, 'baki_debet1' => $amount,
            'uniqueid_namareport' => null,
        ], $overrides);
    }
}
