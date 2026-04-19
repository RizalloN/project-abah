<?php

use App\Support\SnapshotAuditCoordinator;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(ConsoleKernel::class);
$kernel->bootstrap();

echo "QUEUE OVERVIEW\n";
echo str_repeat('-', 60) . "\n";

$jobs = DB::table('jobs')
    ->select('id', 'queue', 'attempts', 'reserved_at', 'available_at', 'payload')
    ->orderBy('id')
    ->get();

foreach ($jobs as $job) {
    $payload = json_decode($job->payload, true);
    $displayName = data_get($payload, 'displayName');
    $commandName = data_get($payload, 'data.commandName');
    $jobName = $displayName ?? $commandName ?? 'unknown';
    $payloadContainsClass = str_contains($job->payload, 'EnsureDashboardSimpananSnapshotJob');

    echo sprintf(
        "#%d queue=%s attempts=%d reserved_at=%s available_at=%s job=%s payload_has_class=%s\n",
        $job->id,
        $job->queue,
        (int) $job->attempts,
        $job->reserved_at ?? 'null',
        $job->available_at ?? 'null',
        $jobName,
        $payloadContainsClass ? 'yes' : 'no'
    );
}

echo "\n";
echo 'pending_jobs=' . DB::table('jobs')->count() . PHP_EOL;
echo 'reserved_jobs=' . DB::table('jobs')->whereNotNull('reserved_at')->count() . PHP_EOL;
echo 'failed_jobs=' . DB::table('failed_jobs')->count() . PHP_EOL;
echo 'job_batches=' . DB::table('job_batches')->count() . PHP_EOL;

$batchRows = DB::table('job_batches')
    ->select('id', 'name', 'total_jobs', 'pending_jobs', 'failed_jobs', 'created_at', 'cancelled_at', 'finished_at')
    ->orderByDesc('created_at')
    ->get();

foreach ($batchRows as $batch) {
    echo sprintf(
        "batch id=%s name=%s total=%d pending=%d failed=%d created_at=%s finished_at=%s cancelled_at=%s\n",
        $batch->id,
        $batch->name,
        (int) $batch->total_jobs,
        (int) $batch->pending_jobs,
        (int) $batch->failed_jobs,
        $batch->created_at ?? 'null',
        $batch->finished_at ?? 'null',
        $batch->cancelled_at ?? 'null'
    );
}

echo "\nSCHEMA CHECK\n";
echo str_repeat('-', 60) . "\n";

$schemaTargets = [
    'daily_loan_dinamis',
    'dashboard_pinjaman_snapshots',
    'simpanan_multipn',
    'dashboard_simpanan_snapshots',
    'ssa_simpanan',
    'ssa_simpanan_snapshots',
    'ssa_pinjaman',
    'ssa_pinjaman_snapshots',
    'lw325_ph',
    'lw325_ph_snapshots',
];

foreach ($schemaTargets as $table) {
    $exists = Schema::hasTable($table);
    echo $table . ' exists=' . ($exists ? 'yes' : 'no');
    if ($exists) {
        $columns = Schema::getColumnListing($table);
        echo ' columns=' . implode(',', array_slice($columns, 0, 12));
        if (count($columns) > 12) {
            echo ',...';
        }
    }
    echo PHP_EOL;
}

echo "\nSNAPSHOT AUDIT\n";
echo str_repeat('-', 60) . "\n";

$coordinator = app(SnapshotAuditCoordinator::class);

$targets = [
    ['daily_loan_dinamis', null],
    ['simpanan_multipn', null],
    ['ssa_simpanan', null],
    ['ssa_pinjaman', null],
    ['lw325_ph', null],
];

foreach ($targets as [$table, $period]) {
    $result = $coordinator->runAudit($table, $period);

    $summary = $result['summary'] ?? [];
    echo sprintf(
        "%s => status=%s total_issues=%d critical=%d warnings=%d periods_checked=%d periods_with_issues=%d action=%s\n",
        $table,
        $result['status'] ?? 'unknown',
        (int) ($summary['total_issues'] ?? 0),
        (int) ($summary['critical_issues'] ?? 0),
        (int) ($summary['warnings'] ?? 0),
        (int) ($result['total_periods_checked'] ?? 0),
        (int) ($result['periods_with_issues'] ?? 0),
        $summary['recommended_action'] ?? 'n/a'
    );
}

echo "\nDIRECT SNAPSHOT CHECK\n";
echo str_repeat('-', 60) . "\n";

$dashboardPinjamanPeriod = DB::table('dashboard_pinjaman_snapshots')->max('periode');
if ($dashboardPinjamanPeriod) {
    $loanSource = DB::table('daily_loan_dinamis')->where('periode', $dashboardPinjamanPeriod);
    $loanSnapshot = DB::table('dashboard_pinjaman_snapshots')->where('periode', $dashboardPinjamanPeriod);

    $loanSourceSummary = (clone $loanSource)
        ->selectRaw('COUNT(*) as row_count')
        ->selectRaw('COALESCE(SUM(COALESCE(plafon, 0)), 0) as total_plafon')
        ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as total_baki_debet')
        ->selectRaw('COUNT(DISTINCT nomor_rekening1) as distinct_accounts')
        ->selectRaw('COUNT(DISTINCT CIFNO) as distinct_debitur')
        ->first();

    $loanSnapshotSummary = (clone $loanSnapshot)
        ->selectRaw('COUNT(*) as row_count')
        ->selectRaw('COALESCE(SUM(COALESCE(loan_balance, 0)), 0) as total_loan_balance')
        ->selectRaw('COUNT(DISTINCT account_number) as distinct_accounts')
        ->first();

    echo "dashboard_pinjaman_snapshots period={$dashboardPinjamanPeriod}\n";
    echo sprintf(
        "  source_rows=%d snapshot_rows=%d source_plafon=%.2f source_baki=%0.2f snapshot_loan=%0.2f\n",
        (int) ($loanSourceSummary->row_count ?? 0),
        (int) ($loanSnapshotSummary->row_count ?? 0),
        (float) ($loanSourceSummary->total_plafon ?? 0),
        (float) ($loanSourceSummary->total_baki_debet ?? 0),
        (float) ($loanSnapshotSummary->total_loan_balance ?? 0)
    );
    echo sprintf(
        "  source_accounts=%d snapshot_accounts=%d source_debitur=%d\n",
        (int) ($loanSourceSummary->distinct_accounts ?? 0),
        (int) ($loanSnapshotSummary->distinct_accounts ?? 0),
        (int) ($loanSourceSummary->distinct_debitur ?? 0)
    );
}

$dashboardSimpananPeriod = DB::table('dashboard_simpanan_snapshots')->max('snapshot_period');
if ($dashboardSimpananPeriod) {
    $simpananSource = DB::table('simpanan_multipn')->where('posisi', $dashboardSimpananPeriod);
    $simpananSnapshot = DB::table('dashboard_simpanan_snapshots')->where('snapshot_period', $dashboardSimpananPeriod);

    $simpananSourceSummary = (clone $simpananSource)
        ->selectRaw('COUNT(*) as row_count')
        ->selectRaw('COALESCE(SUM(COALESCE(saldo_idr, 0)), 0) as total_balance')
        ->selectRaw('COUNT(DISTINCT no_rekening) as account_count')
        ->selectRaw('COUNT(DISTINCT CIFNO) as cif_count')
        ->selectRaw('COUNT(DISTINCT kantor_cabang) as branch_count')
        ->selectRaw('COUNT(DISTINCT unit_kerja) as unit_count')
        ->selectRaw('MAX(updated_at) as source_updated_at')
        ->first();

    $simpananSnapshotSummary = (clone $simpananSnapshot)
        ->selectRaw('COUNT(*) as row_count')
        ->selectRaw('COALESCE(SUM(COALESCE(total_balance, 0)), 0) as total_balance')
        ->selectRaw('MAX(source_row_count) as source_row_count')
        ->selectRaw('MAX(source_updated_at) as source_updated_at')
        ->first();

    echo "dashboard_simpanan_snapshots period={$dashboardSimpananPeriod}\n";
    echo sprintf(
        "  source_rows=%d snapshot_rows=%d source_balance=%.2f snapshot_balance=%.2f\n",
        (int) ($simpananSourceSummary->row_count ?? 0),
        (int) ($simpananSnapshotSummary->row_count ?? 0),
        (float) ($simpananSourceSummary->total_balance ?? 0),
        (float) ($simpananSnapshotSummary->total_balance ?? 0)
    );
    echo sprintf(
        "  source_accounts=%d source_cif=%d source_branch=%d source_unit=%d snapshot_source_row_count=%d\n",
        (int) ($simpananSourceSummary->account_count ?? 0),
        (int) ($simpananSourceSummary->cif_count ?? 0),
        (int) ($simpananSourceSummary->branch_count ?? 0),
        (int) ($simpananSourceSummary->unit_count ?? 0),
        (int) ($simpananSnapshotSummary->source_row_count ?? 0)
    );
    echo sprintf(
        "  source_updated_at=%s snapshot_source_updated_at=%s\n",
        (string) ($simpananSourceSummary->source_updated_at ?? 'null'),
        (string) ($simpananSnapshotSummary->source_updated_at ?? 'null')
    );
}
