<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\RunOffReportController;
use App\Models\User;
use App\Services\Reports\RunOffReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RunOffReportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Schema::dropIfExists('daily_loan_dinamis');
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
            $table->string('description')->nullable();
            $table->decimal('npb_pokok_la', 20, 2)->nullable();
            $table->date('next_pmt_date')->nullable();
            $table->date('next_pmt_int_date')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('daily_loan_dinamis');

        parent::tearDown();
    }

    public function test_it_calculates_total_remaining_and_paid_from_daily_loan(): void
    {
        $this->seedRunOffFixtures();

        $report = app(RunOffReportService::class)->build();

        $this->assertNull($report['error']);
        $this->assertSame('2026-07-25', $report['latest_period']);
        $this->assertSame('2026-06-30', $report['baseline_period']);

        $microArea = collect($report['rows'])->first(fn (array $row): bool =>
            $row['category'] === 'MICRO TOTAL' && $row['branch'] === 'Area 6'
        );
        $this->assertSame(3, $microArea['baseline_accounts']);
        $this->assertSame(60000, $microArea['baseline_amount_cents']);
        $this->assertSame(1, $microArea['remaining_accounts']);
        $this->assertSame(10000, $microArea['remaining_amount_cents']);
        $this->assertSame(2, $microArea['paid_accounts']);
        $this->assertSame(50000, $microArea['paid_amount_cents']);

        $microMadiun = collect($report['rows'])->first(fn (array $row): bool =>
            $row['category'] === 'MICRO TOTAL' && $row['branch'] === 'KC Madiun'
        );
        $this->assertSame(1, $microMadiun['remaining_accounts']);
        $this->assertSame(10000, $microMadiun['remaining_amount_cents']);
        $this->assertSame(1, $microMadiun['paid_accounts']);
        $this->assertSame(20000, $microMadiun['paid_amount_cents']);

        $microMagetan = collect($report['rows'])->first(fn (array $row): bool =>
            $row['category'] === 'MICRO TOTAL' && $row['branch'] === 'KC Magetan'
        );
        $this->assertSame(1, $microMagetan['paid_accounts']);
        $this->assertSame(30000, $microMagetan['paid_amount_cents']);

        $this->assertFalse(collect($report['rows'])->contains(
            fn (array $row): bool => str_contains($row['category'], 'MEDIUM')
        ));
    }

    public function test_it_uses_the_baseline_principal_schedule_and_baseline_amount(): void
    {
        $this->seedRunOffFixtures();

        $report = app(RunOffReportService::class)->build();
        $rows = collect($report['rows']);

        $consumer = $rows->first(fn (array $row): bool =>
            $row['category'] === 'CONSUMER TOTAL' && $row['branch'] === 'Area 6'
        );
        $small = $rows->first(fn (array $row): bool =>
            $row['category'] === 'SMALL TOTAL' && $row['branch'] === 'Area 6'
        );

        $this->assertSame(0, $consumer['remaining_accounts']);
        $this->assertSame(0, $consumer['remaining_amount_cents']);
        $this->assertSame(1, $small['remaining_accounts']);
        $this->assertSame(50000, $small['remaining_amount_cents']);
    }

    public function test_branch_scope_returns_only_the_assigned_branch(): void
    {
        $this->seedRunOffFixtures();

        $report = app(RunOffReportService::class)->build([
            'label' => 'KC Madiun',
            'plain_label' => 'Madiun',
        ]);

        $this->assertNotEmpty($report['rows']);
        $this->assertSame(['KC Madiun'], collect($report['rows'])->pluck('branch')->unique()->values()->all());
        $this->assertFalse(collect($report['rows'])->contains(fn (array $row): bool => $row['is_summary']));
    }

    public function test_it_requires_the_exact_previous_month_end_position(): void
    {
        DB::table('daily_loan_dinamis')->insert($this->row(
            '2026-07-25', 'KC Madiun', 'CUR-1', 'Micro', 'Kupedes', '80.00', '2026-07-30', '2026-08-01'
        ));
        DB::table('daily_loan_dinamis')->insert($this->row(
            '2026-06-29', 'KC Madiun', 'BASE-1', 'Micro', 'Kupedes', '100.00', null, null
        ));

        $report = app(RunOffReportService::class)->build();

        $this->assertSame('2026-06-30', $report['baseline_period']);
        $this->assertStringContainsString('30 Jun 2026', $report['error']);
        $this->assertSame([], $report['rows']);
    }

    public function test_controller_renders_three_metric_groups(): void
    {
        $this->seedRunOffFixtures();
        $this->actingAs(User::factory()->make(['name' => 'Area Enam', 'pn' => '9999']));

        $view = (new RunOffReportController())->index(
            new Request(['refresh' => true]),
            app(RunOffReportService::class)
        );
        $rendered = $view->render();

        $this->assertStringContainsString('Run OFF Total', $rendered);
        $this->assertStringContainsString('Sisa Run OFF', $rendered);
        $this->assertStringContainsString('Sudah Bayar', $rendered);
        $this->assertStringContainsString('Daily Loan Dinamis', $rendered);
        $this->assertStringNotContainsString('docs.google.com', $rendered);
    }

    private function seedRunOffFixtures(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-06-30', 'KC Madiun', 'MIC-1', 'Micro', 'Cash Collateral', '100.00', '2026-07-05', '2026-07-04', 'KUPEDES'),
            $this->row('2026-06-30', 'KC Madiun', 'MIC-2', 'Micro', 'Kupedes', '200.00', '2026-07-10', '2026-07-09'),
            $this->row('2026-06-30', 'KC Magetan', 'MIC-3', 'Micro', 'Kupedes', '300.00', '2026-07-15', '2026-07-14'),
            $this->row('2026-06-30', 'KC Madiun', 'MIC-OUT', 'Micro', 'Kupedes', '700.00', '2026-08-01', '2026-07-31'),
            $this->row('2026-06-30', 'KC Madiun', 'CON-1', 'Consumer', 'Briguna-Konsumer', '400.00', '2026-07-20', null),
            $this->row('2026-06-30', 'KC Madiun', 'SML-1', 'Small', 'Commercial', '500.00', '2026-07-22', null),
            $this->row('2026-06-30', 'KC Madiun', 'MED-1', 'Medium', 'Medium', '600.00', '2026-07-18', null),
            $this->row('2026-07-25', 'KC Madiun', 'MIC-1', 'Micro', 'Kupedes', '80.00', '2026-07-30', '2026-08-01'),
            $this->row('2026-07-25', 'KC Madiun', 'MIC-2', 'Micro', 'Kupedes', '150.00', '2026-08-10', '2026-07-20'),
            $this->row('2026-07-25', 'KC Magetan', 'MIC-3', 'Micro', 'Kupedes', '280.00', '2026-08-05', '2026-08-06'),
            $this->row('2026-07-25', 'KC Madiun', 'MIC-OUT', 'Micro', 'Kupedes', '650.00', '2026-07-31', null),
            $this->row('2026-07-25', 'KC Madiun', 'CON-1', 'Consumer', 'Briguna-Konsumer', '350.00', null, '2026-07-15'),
            $this->row('2026-07-25', 'KC Madiun', 'SML-1', 'Small', 'Commercial', '450.00', '2026-07-18', null),
            $this->row('2026-07-25', 'KC Madiun', 'MED-1', 'Medium', 'Medium', '550.00', '2026-07-18', null),
        ]);
    }

    private function row(
        string $period,
        string $branch,
        string $account,
        string $segment,
        string $product,
        string $amount,
        ?string $principalDate,
        ?string $interestDate,
        ?string $description = null
    ): array {
        return [
            'periode' => $period,
            'cabang1' => $branch,
            'nomor_rekening1' => $account,
            'segmen_dashboard' => $segment,
            'produk_dashboard' => $product,
            'description' => $description ?? ($segment === 'Micro' ? strtoupper($product) : null),
            'npb_pokok_la' => $amount,
            'next_pmt_date' => $principalDate,
            'next_pmt_int_date' => $interestDate,
        ];
    }
}
