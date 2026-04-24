<?php

namespace Tests\Unit;

use App\Support\SnapshotAuditService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SnapshotAuditServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
    }

    public function test_daily_loan_audit_uses_current_dashboard_pinjaman_snapshot_schema(): void
    {
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->date('periode');
            $table->decimal('baki_debet1', 20, 2)->default(0);
            $table->string('nomor_rekening1')->nullable();
        });

        Schema::create('dashboard_pinjaman_snapshots', function (Blueprint $table): void {
            $table->string('uniqueid_dps')->primary();
            $table->date('periode');
            $table->decimal('loan_balance', 20, 2)->default(0);
            $table->string('account_number')->nullable();
        });

        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-04-20', 'baki_debet1' => 1000, 'nomor_rekening1' => 'A1'],
            ['periode' => '2026-04-20', 'baki_debet1' => 2500, 'nomor_rekening1' => 'A2'],
        ]);
        DB::table('dashboard_pinjaman_snapshots')->insert([
            ['uniqueid_dps' => 's1', 'periode' => '2026-04-20', 'loan_balance' => 1000, 'account_number' => 'A1'],
            ['uniqueid_dps' => 's2', 'periode' => '2026-04-20', 'loan_balance' => 2500, 'account_number' => 'A2'],
        ]);

        $result = (new SnapshotAuditService())->auditSnapshot('daily_loan_dinamis', '2026-04-20');

        $this->assertSame('clean', $result['status'], json_encode($result['discrepancies'] ?? []));
        $this->assertSame(1, $result['total_periods_checked']);
    }

    public function test_simpanan_audit_uses_current_simpanan_snapshot_schema(): void
    {
        Schema::create('simpanan_multipn', function (Blueprint $table): void {
            $table->id();
            $table->date('posisi');
            $table->decimal('saldo_idr', 20, 2)->default(0);
            $table->string('CIFNO')->nullable();
        });

        Schema::create('dashboard_simpanan_snapshots', function (Blueprint $table): void {
            $table->string('uniqueid_dss')->primary();
            $table->date('snapshot_period');
            $table->decimal('total_balance', 20, 2)->default(0);
            $table->integer('source_row_count')->default(0);
            $table->integer('cif_count')->default(0);
        });

        DB::table('simpanan_multipn')->insert([
            ['posisi' => '2026-04-20', 'saldo_idr' => 700, 'CIFNO' => 'C1'],
            ['posisi' => '2026-04-20', 'saldo_idr' => 300, 'CIFNO' => 'C2'],
        ]);
        DB::table('dashboard_simpanan_snapshots')->insert([
            'uniqueid_dss' => 'dss-2026-04-20',
            'snapshot_period' => '2026-04-20',
            'total_balance' => 1000,
            'source_row_count' => 2,
            'cif_count' => 2,
        ]);

        $result = (new SnapshotAuditService())->auditSnapshot('simpanan_multipn', '2026-04-20');

        $this->assertSame('clean', $result['status'], json_encode($result['discrepancies'] ?? []));
        $this->assertSame(1, $result['total_periods_checked']);
    }
}
