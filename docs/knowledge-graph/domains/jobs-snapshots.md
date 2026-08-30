# Domain: jobs-snapshots

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=jobs-snapshots --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 17 |
| file | 69 |
| function | 6 |
| method | 529 |
| unresolved_symbol | 38 |
| queue | 5 |
| route | 1 |
| class | 65 |
| trait | 1 |
| table | 12 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `ReportSnapshotBuilder` | class | 136 | `app/Support/ReportSnapshotBuilder.php:14` |
| `performance_rm_snapshots` | table | 52 | - |
| `ManagedReportSnapshotRebuildCoordinator` | class | 38 | `app/Support/ManagedReportSnapshotRebuildCoordinator.php:15` |
| `SnapshotBatchAggregator` | class | 34 | `app/Support/SnapshotBatchAggregator.php:11` |
| `snapshots-parallel` | queue | 32 | - |
| `capture` | method | 26 | `app/Support/SnapshotSourceSignatureService.php:58` |
| `handle` | method | 26 | `app/Jobs/RunManagedReportSnapshotRebuildJob.php:52` |
| `ValidateSnapshotDataIntegrityCommand` | class | 25 | `app/Console/Commands/ValidateSnapshotDataIntegrityCommand.php:11` |
| `EnsureQueueWorkerRunning` | class | 24 | `app/Console/Commands/EnsureQueueWorkerRunning.php:11` |
| `SnapshotDirtyPeriodService` | class | 24 | `app/Support/SnapshotDirtyPeriodService.php:11` |
| `SnapshotJobRetryWindow` | trait | 23 | `app/Jobs/SnapshotJobRetryWindow.php:7` |
| `BackfillShadowColumnsCommand` | class | 22 | `app/Console/Commands/BackfillShadowColumnsCommand.php:14` |
| `SnapshotSourceSignatureService` | class | 22 | `app/Support/SnapshotSourceSignatureService.php:10` |
| `registerSyncRequest` | method | 22 | `app/Support/SnapshotBatchAggregator.php:22` |
| `RunManagedReportSnapshotRebuildJob` | class | 21 | `app/Jobs/RunManagedReportSnapshotRebuildJob.php:23` |
| `isFresh` | method | 21 | `app/Support/SnapshotSourceSignatureService.php:238` |
| `SmartPartialSnapshotRebuildJob` | class | 20 | `app/Jobs/SmartPartialSnapshotRebuildJob.php:20` |
| `RebuildSnapshotPerformanceRmBatch` | class | 19 | `app/Jobs/RebuildSnapshotPerformanceRmBatch.php:20` |
| `SnapshotAuditService` | class | 19 | `app/Support/SnapshotAuditService.php:10` |
| `ValidatePerformanceRmSnapshotsCommand` | class | 19 | `app/Console/Commands/ValidatePerformanceRmSnapshotsCommand.php:10` |
| `DistributedShadowBackfillJob` | class | 18 | `app/Jobs/DistributedShadowBackfillJob.php:15` |
| `RebuildSnapshotHarianBatch` | class | 18 | `app/Jobs/RebuildSnapshotHarianBatch.php:19` |
| `RebuildSnapshotRasioBatch` | class | 18 | `app/Jobs/RebuildSnapshotRasioBatch.php:19` |
| `queue` | method | 18 | `app/Support/ManagedReportSnapshotRebuildCoordinator.php:28` |
| `PerformanceRmIncrementalSnapshotTest` | class | 17 | `tests/Unit/PerformanceRmIncrementalSnapshotTest.php:13` |
| `ExecuteBatchedSnapshotJob` | class | 16 | `app/Jobs/ExecuteBatchedSnapshotJob.php:15` |
| `ProcessShadowBackfillJob` | class | 16 | `app/Jobs/ProcessShadowBackfillJob.php:16` |
| `RebuildLoanChartPeriodikSnapshotJob` | class | 16 | `app/Jobs/RebuildLoanChartPeriodikSnapshotJob.php:18` |
| `RebuildLoanDashboardSnapshotJob` | class | 16 | `app/Jobs/RebuildLoanDashboardSnapshotJob.php:18` |
| `RebuildSnapshotDormantBatch` | class | 16 | `app/Jobs/RebuildSnapshotDormantBatch.php:19` |

## Route Nodes

- `file-management.snapshots.latest-check` - `file-management/snapshots/latest-check`

## Class Nodes

- `BackfillShadowColumnsCommand` - `app/Console/Commands/BackfillShadowColumnsCommand.php`
- `BackfillShadowColumnsCommandTest` - `tests/Unit/BackfillShadowColumnsCommandTest.php`
- `CustomBatchRepository` - `app/Queue/CustomBatchRepository.php`
- `CustomDatabaseConnector` - `app/Queue/Connectors/CustomDatabaseConnector.php`
- `CustomDatabaseQueue` - `app/Queue/CustomDatabaseQueue.php`
- `CustomFailedJobProvider` - `app/Queue/CustomFailedJobProvider.php`
- `DistributedShadowBackfillJob` - `app/Jobs/DistributedShadowBackfillJob.php`
- `DrainSnapshotDirtyPeriodsCommand` - `app/Console/Commands/DrainSnapshotDirtyPeriodsCommand.php`
- `EnsureDashboardSnapshotJob` - `app/Jobs/EnsureDashboardSnapshotJob.php`
- `EnsurePerformanceRmSnapshotJob` - `app/Jobs/EnsurePerformanceRmSnapshotJob.php`
- `EnsureQueueWorkerRunning` - `app/Console/Commands/EnsureQueueWorkerRunning.php`
- `EnsureRasioCasaSnapshotJob` - `app/Jobs/EnsureRasioCasaSnapshotJob.php`
- `EnsureRekeningDormantSnapshotJob` - `app/Jobs/EnsureRekeningDormantSnapshotJob.php`
- `ExecuteBatchedSnapshotJob` - `app/Jobs/ExecuteBatchedSnapshotJob.php`
- `ExecuteBatchedSnapshotJobTest` - `tests/Unit/ExecuteBatchedSnapshotJobTest.php`
- `FlushDueSnapshotBatches` - `app/Console/Commands/FlushDueSnapshotBatches.php`
- `LatestSnapshotRecoveryService` - `app/Support/LatestSnapshotRecoveryService.php`
- `LatestSnapshotRecoveryServiceTest` - `tests/Unit/LatestSnapshotRecoveryServiceTest.php`
- `ManageSnapshotBatches` - `app/Console/Commands/ManageSnapshotBatches.php`
- `ManagedReportSnapshotRebuildCoordinator` - `app/Support/ManagedReportSnapshotRebuildCoordinator.php`
- `ManagedReportSnapshotRebuildStore` - `app/Support/ManagedReportSnapshotRebuildStore.php`
- `ParallelSnapshotBatchCoordinator` - `app/Support/ParallelSnapshotBatchCoordinator.php`
- `PerformanceRmIncrementalSnapshotTest` - `tests/Unit/PerformanceRmIncrementalSnapshotTest.php`
- `ProcessShadowBackfillJob` - `app/Jobs/ProcessShadowBackfillJob.php`
- `ProcessShadowBackfillJobTest` - `tests/Unit/ProcessShadowBackfillJobTest.php`
- `ProcessSnapshotDirtyPeriodJob` - `app/Jobs/ProcessSnapshotDirtyPeriodJob.php`
- `QueueWorkerPoolTest` - `tests/Unit/QueueWorkerPoolTest.php`
- `RasioCasaDebiturControllerSnapshotDeferralTest` - `tests/Unit/RasioCasaDebiturControllerSnapshotDeferralTest.php`
- `RebuildLoanChartPeriodikSnapshotJob` - `app/Jobs/RebuildLoanChartPeriodikSnapshotJob.php`
- `RebuildLoanDashboardSnapshotJob` - `app/Jobs/RebuildLoanDashboardSnapshotJob.php`
- `RebuildRecoverySnapshot` - `app/Console/Commands/RebuildRecoverySnapshot.php`
- `RebuildSnapshotDormantBatch` - `app/Jobs/RebuildSnapshotDormantBatch.php`
- `RebuildSnapshotHarianBatch` - `app/Jobs/RebuildSnapshotHarianBatch.php`
- `RebuildSnapshotPerformanceRmBatch` - `app/Jobs/RebuildSnapshotPerformanceRmBatch.php`
- `RebuildSnapshotPerformanceRmBatchTest` - `tests/Unit/RebuildSnapshotPerformanceRmBatchTest.php`
- `RebuildSnapshotRasioBatch` - `app/Jobs/RebuildSnapshotRasioBatch.php`
- `RebuildSnapshotSimpleBatch` - `app/Jobs/RebuildSnapshotSimpleBatch.php`
- `ReportSnapshotBuilder` - `app/Support/ReportSnapshotBuilder.php`
- `ReportSnapshotBuilderDashboardBucketTest` - `tests/Unit/ReportSnapshotBuilderDashboardBucketTest.php`
- `RunManagedReportSnapshotRebuildJob` - `app/Jobs/RunManagedReportSnapshotRebuildJob.php`
- `ScheduleSnapshotBatchFlush` - `app/Console/Commands/ScheduleSnapshotBatchFlush.php`
- `ShadowBackfillFailedNotification` - `app/Notifications/ShadowBackfillFailedNotification.php`
- `ShadowBackfillStatusCommand` - `app/Console/Commands/ShadowBackfillStatusCommand.php`
- `ShadowBackfillTableCommand` - `app/Console/Commands/ShadowBackfillTableCommand.php`
- `SmartPartialSnapshotRebuildJob` - `app/Jobs/SmartPartialSnapshotRebuildJob.php`
- `SmartPartialSnapshotRebuildJobTest` - `tests/Unit/SmartPartialSnapshotRebuildJobTest.php`
- `SnapshotAuditCoordinator` - `app/Support/SnapshotAuditCoordinator.php`
- `SnapshotAuditService` - `app/Support/SnapshotAuditService.php`
- `SnapshotAuditServiceTest` - `tests/Unit/SnapshotAuditServiceTest.php`
- `SnapshotBatchAggregator` - `app/Support/SnapshotBatchAggregator.php`
- `SnapshotBatchAggregatorTest` - `tests/Unit/SnapshotBatchAggregatorTest.php`
- `SnapshotBatchConfig` - `app/Support/SnapshotBatchConfig.php`
- `SnapshotDirtyPeriodService` - `app/Support/SnapshotDirtyPeriodService.php`
- `SnapshotDirtyPeriodServiceTest` - `tests/Unit/SnapshotDirtyPeriodServiceTest.php`
- `SnapshotDirtyTriggerMigrationTest` - `tests/Unit/SnapshotDirtyTriggerMigrationTest.php`
- `SnapshotForceSyncCommand` - `app/Console/Commands/SnapshotForceSyncCommand.php`
- `SnapshotIntegrityGuard` - `app/Support/SnapshotIntegrityGuard.php`
- `SnapshotIntegrityGuardTest` - `tests/Unit/SnapshotIntegrityGuardTest.php`
- `SnapshotJobDeferralTest` - `tests/Unit/SnapshotJobDeferralTest.php`
- `SnapshotQueryOptimizer` - `app/Support/SnapshotQueryOptimizer.php`
- `SnapshotSourceSignatureService` - `app/Support/SnapshotSourceSignatureService.php`
- `SnapshotWorkerResilienceTest` - `tests/Unit/SnapshotWorkerResilienceTest.php`
- `SpySnapshotFlagPdo` - `tests/Unit/ImportSimpananMultiPnCsvControllerTest.php`
- `ValidatePerformanceRmSnapshotsCommand` - `app/Console/Commands/ValidatePerformanceRmSnapshotsCommand.php`
- `ValidateSnapshotDataIntegrityCommand` - `app/Console/Commands/ValidateSnapshotDataIntegrityCommand.php`

## Trait Nodes

- `SnapshotJobRetryWindow` - `app/Jobs/SnapshotJobRetryWindow.php`

## Command Nodes

- `queue:ensure-running` - `app/Console/Commands/EnsureQueueWorkerRunning.php`
- `queue:restart`
- `queue:restart:`
- `queue:work`
- `reports:snapshot:drain-dirty` - `app/Console/Commands/DrainSnapshotDirtyPeriodsCommand.php`
- `shadow:backfill` - `app/Console/Commands/BackfillShadowColumnsCommand.php`
- `shadow:backfill-status` - `app/Console/Commands/ShadowBackfillStatusCommand.php`
- `shadow:backfill-table` - `app/Console/Commands/ShadowBackfillTableCommand.php`
- `snapshot:flush-due-batches` - `app/Console/Commands/FlushDueSnapshotBatches.php`
- `snapshot:force-sync` - `app/Console/Commands/SnapshotForceSyncCommand.php`
- `snapshot:manage-batches` - `app/Console/Commands/ManageSnapshotBatches.php`
- `snapshot:rebuild-recovery` - `app/Console/Commands/RebuildRecoverySnapshot.php`
- `snapshot:rebuild-rm` - `app/Console/Commands/RebuildPerformanceRmCommand.php`
- `snapshot:rebuild-rm-scheduled` - `app/Console/Commands/ScheduledRebuildPerformanceRmCommand.php`
- `snapshot:setup-batch-flush-schedule` - `app/Console/Commands/ScheduleSnapshotBatchFlush.php`
- `snapshot:validate-integrity` - `app/Console/Commands/ValidateSnapshotDataIntegrityCommand.php`
- `snapshot:validate-rm` - `app/Console/Commands/ValidatePerformanceRmSnapshotsCommand.php`

## Table Nodes

- `failed_snapshot_dirty_periods`
- `performance_new_payroll_snapshots`
- `performance_rm_cabang_snapshots`
- `performance_rm_snapshots`
- `rasio_casa_debitur_snapshots`
- `rasio_casa_debitur_uker_snapshots`
- `rekening_dormant_snapshots`
- `shadow_backfill_checkpoints`
- `shadow_backfill_failures`
- `shadow_backfill_metrics`
- `snapshot_dirty_periods`
- `snapshot_source_signatures`

## Queue Nodes

- `imports-high`
- `remote-sources`
- `reports-low`
- `snapshots-parallel`
- `snapshots-priority`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| jobs-snapshots -> core (calls) | 142 |
| import -> jobs-snapshots (calls) | 73 |
| core -> jobs-snapshots (uses_trait) | 38 |
| jobs-snapshots -> core (instantiates) | 33 |
| jobs-snapshots -> dashboard-pinjaman (reads_table) | 30 |
| jobs-snapshots -> core (uses_trait) | 27 |
| tests -> jobs-snapshots (contains) | 26 |
| dashboard-pinjaman -> jobs-snapshots (writes_table) | 22 |
| import -> jobs-snapshots (instantiates) | 18 |
| jobs-snapshots -> core (accepts) | 17 |
| core -> jobs-snapshots (calls) | 17 |
| import -> jobs-snapshots (uses_trait) | 17 |
| jobs-snapshots -> dashboard-pinjaman (writes_table) | 16 |
| jobs-snapshots -> core (extends) | 16 |
| jobs-snapshots -> tests (extends) | 16 |
| jobs-snapshots -> core (writes_table) | 14 |
| jobs-snapshots -> import (instantiates) | 14 |
| core -> jobs-snapshots (uses_queue) | 13 |
| core -> jobs-snapshots (implements) | 13 |
| database -> jobs-snapshots (checks_table) | 12 |
| database -> jobs-snapshots (defines_table) | 12 |
| jobs-snapshots -> dashboard-simpanan (reads_table) | 12 |
| jobs-snapshots -> core (reads_table) | 12 |
| import -> jobs-snapshots (accepts) | 11 |
| core -> jobs-snapshots (contains) | 10 |
| tests -> jobs-snapshots (calls) | 10 |
| dashboard-simpanan -> jobs-snapshots (uses_trait) | 10 |
| jobs-snapshots -> import (contains) | 10 |
| dashboard-pinjaman -> jobs-snapshots (calls) | 9 |
| database -> jobs-snapshots (alters_table) | 8 |
