# Domain: jobs-snapshots

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=jobs-snapshots --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 17 |
| file | 74 |
| function | 2 |
| method | 571 |
| unresolved_symbol | 17 |
| queue | 5 |
| route | 1 |
| class | 70 |
| trait | 1 |
| table | 11 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `ReportSnapshotBuilder` | class | 144 | `app/Support/ReportSnapshotBuilder.php:12` |
| `performance_rm_snapshots` | table | 85 | - |
| `jobs` | table | 71 | - |
| `ManagedReportSnapshotRebuildCoordinator` | class | 38 | `app/Support/ManagedReportSnapshotRebuildCoordinator.php:15` |
| `PerformanceRmIncrementalSnapshotTest` | class | 38 | `tests/Unit/PerformanceRmIncrementalSnapshotTest.php:16` |
| `SnapshotBatchAggregator` | class | 34 | `app/Support/SnapshotBatchAggregator.php:11` |
| `insertDailyLoanRow` | method | 32 | `tests/Unit/PerformanceRmIncrementalSnapshotTest.php:985` |
| `snapshots-parallel` | queue | 32 | - |
| `capture` | method | 29 | `app/Support/SnapshotSourceSignatureService.php:61` |
| `ValidateSnapshotDataIntegrityCommand` | class | 26 | `app/Console/Commands/ValidateSnapshotDataIntegrityCommand.php:12` |
| `handle` | method | 26 | `app/Jobs/RunManagedReportSnapshotRebuildJob.php:52` |
| `EnsureQueueWorkerRunning` | class | 25 | `app/Console/Commands/EnsureQueueWorkerRunning.php:12` |
| `SnapshotDirtyPeriodService` | class | 24 | `app/Support/SnapshotDirtyPeriodService.php:11` |
| `RefreshRemoteDashboardSourcesJob` | class | 23 | `app/Jobs/RefreshRemoteDashboardSourcesJob.php:21` |
| `SnapshotJobRetryWindow` | trait | 23 | `app/Jobs/SnapshotJobRetryWindow.php:7` |
| `SnapshotSourceSignatureService` | class | 23 | `app/Support/SnapshotSourceSignatureService.php:10` |
| `BackfillShadowColumnsCommand` | class | 22 | `app/Console/Commands/BackfillShadowColumnsCommand.php:14` |
| `RunManagedReportSnapshotRebuildJob` | class | 22 | `app/Jobs/RunManagedReportSnapshotRebuildJob.php:23` |
| `isFresh` | method | 21 | `app/Support/SnapshotSourceSignatureService.php:359` |
| `registerSyncRequest` | method | 21 | `app/Support/SnapshotBatchAggregator.php:22` |
| `SmartPartialSnapshotRebuildJob` | class | 20 | `app/Jobs/SmartPartialSnapshotRebuildJob.php:20` |
| `RebuildSnapshotHarianBatch` | class | 19 | `app/Jobs/RebuildSnapshotHarianBatch.php:19` |
| `RebuildSnapshotPerformanceRmBatch` | class | 19 | `app/Jobs/RebuildSnapshotPerformanceRmBatch.php:20` |
| `RebuildSnapshotRasioBatch` | class | 19 | `app/Jobs/RebuildSnapshotRasioBatch.php:19` |
| `SnapshotAuditService` | class | 19 | `app/Support/SnapshotAuditService.php:10` |
| `ValidatePerformanceRmSnapshotsCommand` | class | 19 | `app/Console/Commands/ValidatePerformanceRmSnapshotsCommand.php:12` |
| `DistributedShadowBackfillJob` | class | 18 | `app/Jobs/DistributedShadowBackfillJob.php:15` |
| `queue` | method | 18 | `app/Support/ManagedReportSnapshotRebuildCoordinator.php:28` |
| `ExecuteBatchedSnapshotJob` | class | 16 | `app/Jobs/ExecuteBatchedSnapshotJob.php:15` |
| `ProcessShadowBackfillJob` | class | 16 | `app/Jobs/ProcessShadowBackfillJob.php:16` |

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
- `ExecuteBatchedSnapshotJob` - `app/Jobs/ExecuteBatchedSnapshotJob.php`
- `ExecuteBatchedSnapshotJobTest` - `tests/Unit/ExecuteBatchedSnapshotJobTest.php`
- `FlushDueSnapshotBatches` - `app/Console/Commands/FlushDueSnapshotBatches.php`
- `GeneratePresentationPowerPointJob` - `app/Jobs/GeneratePresentationPowerPointJob.php`
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
- `RebuildChartPeriodikPeriodJob` - `app/Jobs/RebuildChartPeriodikPeriodJob.php`
- `RebuildDashboardPeriodJob` - `app/Jobs/RebuildDashboardPeriodJob.php`
- `RebuildHarianPeriodJob` - `app/Jobs/RebuildHarianPeriodJob.php`
- `RebuildRasioPeriodJob` - `app/Jobs/RebuildRasioPeriodJob.php`
- `RebuildRecoverySnapshot` - `app/Console/Commands/RebuildRecoverySnapshot.php`
- `RebuildSnapshotHarianBatch` - `app/Jobs/RebuildSnapshotHarianBatch.php`
- `RebuildSnapshotPerformanceRmBatch` - `app/Jobs/RebuildSnapshotPerformanceRmBatch.php`
- `RebuildSnapshotPerformanceRmBatchTest` - `tests/Unit/RebuildSnapshotPerformanceRmBatchTest.php`
- `RebuildSnapshotRasioBatch` - `app/Jobs/RebuildSnapshotRasioBatch.php`
- `RebuildSnapshotSimpleBatch` - `app/Jobs/RebuildSnapshotSimpleBatch.php`
- `RefreshRemoteDashboardSourcesJob` - `app/Jobs/RefreshRemoteDashboardSourcesJob.php`
- `ReportSnapshotBuilder` - `app/Support/ReportSnapshotBuilder.php`
- `ReportSnapshotBuilderDashboardBucketTest` - `tests/Unit/ReportSnapshotBuilderDashboardBucketTest.php`
- `RunLoadDataJob` - `app/Jobs/RunLoadDataJob.php`
- `RunManagedReportDeleteJob` - `app/Jobs/RunManagedReportDeleteJob.php`
- `RunManagedReportLoadJob` - `app/Jobs/RunManagedReportLoadJob.php`
- `RunManagedReportRecoveryJob` - `app/Jobs/RunManagedReportRecoveryJob.php`
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
- `WarmReportCacheJob` - `app/Jobs/WarmReportCacheJob.php`

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

- `failed_jobs`
- `failed_snapshot_dirty_periods`
- `job_batches`
- `jobs`
- `performance_rm_cabang_snapshots`
- `performance_rm_snapshots`
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
| jobs-snapshots -> core (calls) | 140 |
| import -> jobs-snapshots (calls) | 76 |
| dashboard-pinjaman -> jobs-snapshots (writes_table) | 35 |
| jobs-snapshots -> core (uses_trait) | 31 |
| jobs-snapshots -> dashboard-pinjaman (reads_table) | 27 |
| tests -> jobs-snapshots (contains) | 27 |
| jobs-snapshots -> core (instantiates) | 24 |
| dashboard-simpanan -> jobs-snapshots (uses_trait) | 24 |
| jobs-snapshots -> core (accepts) | 21 |
| jobs-snapshots -> dashboard-pinjaman (writes_table) | 21 |
| import -> jobs-snapshots (reads_table) | 20 |
| jobs-snapshots -> tests (calls) | 19 |
| dashboard-pinjaman -> jobs-snapshots (calls) | 18 |
| import -> jobs-snapshots (instantiates) | 17 |
| import -> jobs-snapshots (uses_trait) | 17 |
| jobs-snapshots -> core (extends) | 16 |
| import -> jobs-snapshots (writes_table) | 15 |
| jobs-snapshots -> import (instantiates) | 15 |
| jobs-snapshots -> tests (extends) | 15 |
| database -> jobs-snapshots (checks_table) | 14 |
| jobs-snapshots -> dashboard-simpanan (reads_table) | 13 |
| database -> jobs-snapshots (defines_table) | 12 |
| import -> jobs-snapshots (checks_table) | 11 |
| import -> jobs-snapshots (accepts) | 11 |
| dashboard-pinjaman -> jobs-snapshots (uses_trait) | 11 |
| core -> jobs-snapshots (calls) | 10 |
| jobs-snapshots -> presentation (calls) | 10 |
| jobs-snapshots -> dashboard-simpanan (writes_table) | 10 |
| tests -> jobs-snapshots (calls) | 10 |
| jobs-snapshots -> import (contains) | 10 |
