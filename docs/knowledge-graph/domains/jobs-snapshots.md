# Domain: jobs-snapshots

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=jobs-snapshots --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 16 |
| file | 65 |
| method | 534 |
| unresolved_symbol | 18 |
| queue | 6 |
| route | 3 |
| class | 63 |
| trait | 1 |
| table | 11 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `ReportSnapshotBuilder` | class | 129 | `app/Support/ReportSnapshotBuilder.php:12` |
| `jobs` | table | 62 | - |
| `ManagedReportSnapshotRebuildCoordinator` | class | 38 | `app/Support/ManagedReportSnapshotRebuildCoordinator.php:15` |
| `EnsureQueueWorkerRunning` | class | 33 | `app/Console/Commands/EnsureQueueWorkerRunning.php:24` |
| `snapshots-parallel` | queue | 33 | - |
| `SnapshotBatchAggregator` | class | 28 | `app/Support/SnapshotBatchAggregator.php:11` |
| `ValidateSnapshotDataIntegrityCommand` | class | 27 | `app/Console/Commands/ValidateSnapshotDataIntegrityCommand.php:13` |
| `handle` | method | 26 | `app/Jobs/RunManagedReportSnapshotRebuildJob.php:52` |
| `SnapshotJobRetryWindow` | trait | 24 | `app/Jobs/SnapshotJobRetryWindow.php:7` |
| `RefreshRemoteDashboardSourcesJob` | class | 23 | `app/Jobs/RefreshRemoteDashboardSourcesJob.php:21` |
| `SnapshotSourceSignatureService` | class | 23 | `app/Support/SnapshotSourceSignatureService.php:10` |
| `BackfillShadowColumnsCommand` | class | 22 | `app/Console/Commands/BackfillShadowColumnsCommand.php:15` |
| `registerSyncRequest` | method | 21 | `app/Support/SnapshotBatchAggregator.php:22` |
| `ValidatePerformanceRmSnapshotsCommand` | class | 20 | `app/Console/Commands/ValidatePerformanceRmSnapshotsCommand.php:13` |
| `RunManagedReportSnapshotRebuildJob` | class | 19 | `app/Jobs/RunManagedReportSnapshotRebuildJob.php:23` |
| `SnapshotDirtyPeriodService` | class | 19 | `app/Support/SnapshotDirtyPeriodService.php:11` |
| `DistributedShadowBackfillJob` | class | 18 | `app/Jobs/DistributedShadowBackfillJob.php:15` |
| `RebuildSnapshotHarianBatch` | class | 18 | `app/Jobs/RebuildSnapshotHarianBatch.php:19` |
| `RebuildSnapshotRasioBatch` | class | 18 | `app/Jobs/RebuildSnapshotRasioBatch.php:19` |
| `SmartPartialSnapshotRebuildJob` | class | 18 | `app/Jobs/SmartPartialSnapshotRebuildJob.php:20` |
| `SnapshotAuditService` | class | 18 | `app/Support/SnapshotAuditService.php:10` |
| `queue` | method | 18 | `app/Support/ManagedReportSnapshotRebuildCoordinator.php:28` |
| `ProcessShadowBackfillJob` | class | 17 | `app/Jobs/ProcessShadowBackfillJob.php:19` |
| `WarmReportCacheJob` | class | 17 | `app/Jobs/WarmReportCacheJob.php:15` |
| `performance_rm_snapshots` | table | 17 | - |
| `RebuildSnapshotPerformanceRmBatch` | class | 16 | `app/Jobs/RebuildSnapshotPerformanceRmBatch.php:20` |
| `RebuildSnapshotSimpleBatch` | class | 16 | `app/Jobs/RebuildSnapshotSimpleBatch.php:19` |
| `capture` | method | 16 | `app/Support/SnapshotSourceSignatureService.php:64` |
| `dispatchDailyLoanParallelRebuild` | method | 16 | `app/Support/ParallelSnapshotBatchCoordinator.php:101` |
| `dispatchParallelRebuild` | method | 16 | `app/Support/ParallelSnapshotBatchCoordinator.php:48` |

## Route Nodes

- `file-management.snapshots.latest-check` - `file-management/snapshots/latest-check`
- `job-management.runtime.control` - `job-management/runtime/control`
- `job-management.runtime.status` - `job-management/runtime`

## Class Nodes

- `AuditAndHealSnapshotsJob` - `app/Jobs/AuditAndHealSnapshotsJob.php`
- `AuditSnapshotsCommand` - `app/Console/Commands/AuditSnapshotsCommand.php`
- `BackfillShadowColumnsCommand` - `app/Console/Commands/BackfillShadowColumnsCommand.php`
- `CustomBatchRepository` - `app/Queue/CustomBatchRepository.php`
- `CustomDatabaseConnector` - `app/Queue/Connectors/CustomDatabaseConnector.php`
- `CustomDatabaseQueue` - `app/Queue/CustomDatabaseQueue.php`
- `CustomFailedJobProvider` - `app/Queue/CustomFailedJobProvider.php`
- `DiscoverShadowBackfillJob` - `app/Jobs/DiscoverShadowBackfillJob.php`
- `DistributedShadowBackfillJob` - `app/Jobs/DistributedShadowBackfillJob.php`
- `DrainSnapshotDirtyPeriodsCommand` - `app/Console/Commands/DrainSnapshotDirtyPeriodsCommand.php`
- `EnsureDashboardSnapshotJob` - `app/Jobs/EnsureDashboardSnapshotJob.php`
- `EnsurePerformanceRmSnapshotJob` - `app/Jobs/EnsurePerformanceRmSnapshotJob.php`
- `EnsureQueueWorkerRunning` - `app/Console/Commands/EnsureQueueWorkerRunning.php`
- `ExecuteBatchedSnapshotJob` - `app/Jobs/ExecuteBatchedSnapshotJob.php`
- `FlushDueSnapshotBatches` - `app/Console/Commands/FlushDueSnapshotBatches.php`
- `GeneratePresentationPowerPointJob` - `app/Jobs/GeneratePresentationPowerPointJob.php`
- `LandingCacheWorkerGuard` - `app/Support/LandingCacheWorkerGuard.php`
- `LatestSnapshotRecoveryService` - `app/Support/LatestSnapshotRecoveryService.php`
- `ManageSnapshotBatches` - `app/Console/Commands/ManageSnapshotBatches.php`
- `ManagedReportSnapshotRebuildCoordinator` - `app/Support/ManagedReportSnapshotRebuildCoordinator.php`
- `ManagedReportSnapshotRebuildStore` - `app/Support/ManagedReportSnapshotRebuildStore.php`
- `ParallelSnapshotBatchCoordinator` - `app/Support/ParallelSnapshotBatchCoordinator.php`
- `PeriodSnapshotSyncService` - `app/Support/PeriodSnapshotSyncService.php`
- `ProcessShadowBackfillJob` - `app/Jobs/ProcessShadowBackfillJob.php`
- `ProcessSnapshotDirtyPeriodJob` - `app/Jobs/ProcessSnapshotDirtyPeriodJob.php`
- `QueueWorkerWatchdog` - `app/Console/Commands/QueueWorkerWatchdog.php`
- `RebuildChartPeriodikPeriodJob` - `app/Jobs/RebuildChartPeriodikPeriodJob.php`
- `RebuildDashboardPeriodJob` - `app/Jobs/RebuildDashboardPeriodJob.php`
- `RebuildHarianPeriodJob` - `app/Jobs/RebuildHarianPeriodJob.php`
- `RebuildRasioPeriodJob` - `app/Jobs/RebuildRasioPeriodJob.php`
- `RebuildRecoverySnapshot` - `app/Console/Commands/RebuildRecoverySnapshot.php`
- `RebuildSnapshotHarianBatch` - `app/Jobs/RebuildSnapshotHarianBatch.php`
- `RebuildSnapshotPerformanceRmBatch` - `app/Jobs/RebuildSnapshotPerformanceRmBatch.php`
- `RebuildSnapshotRasioBatch` - `app/Jobs/RebuildSnapshotRasioBatch.php`
- `RebuildSnapshotSimpleBatch` - `app/Jobs/RebuildSnapshotSimpleBatch.php`
- `RefreshRemoteDashboardSourcesJob` - `app/Jobs/RefreshRemoteDashboardSourcesJob.php`
- `ReportSnapshotBuilder` - `app/Support/ReportSnapshotBuilder.php`
- `RunLoadDataJob` - `app/Jobs/RunLoadDataJob.php`
- `RunManagedReportDeleteJob` - `app/Jobs/RunManagedReportDeleteJob.php`
- `RunManagedReportLoadJob` - `app/Jobs/RunManagedReportLoadJob.php`
- `RunManagedReportRecoveryJob` - `app/Jobs/RunManagedReportRecoveryJob.php`
- `RunManagedReportSnapshotRebuildJob` - `app/Jobs/RunManagedReportSnapshotRebuildJob.php`
- `ScheduleSnapshotBatchFlush` - `app/Console/Commands/ScheduleSnapshotBatchFlush.php`
- `ShadowBackfillFailedNotification` - `app/Notifications/ShadowBackfillFailedNotification.php`
- `ShadowBackfillStatusCommand` - `app/Console/Commands/ShadowBackfillStatusCommand.php`
- `ShadowBackfillTableCommand` - `app/Console/Commands/ShadowBackfillTableCommand.php`
- `SkipSupersededDirtyClaim` - `app/Jobs/Middleware/SkipSupersededDirtyClaim.php`
- `SmartPartialSnapshotRebuildJob` - `app/Jobs/SmartPartialSnapshotRebuildJob.php`
- `SnapshotAuditAndHealService` - `app/Services/Snapshot/SnapshotAuditAndHealService.php`
- `SnapshotAuditCoordinator` - `app/Support/SnapshotAuditCoordinator.php`
- `SnapshotAuditService` - `app/Support/SnapshotAuditService.php`
- `SnapshotBatchAggregator` - `app/Support/SnapshotBatchAggregator.php`
- `SnapshotBatchConfig` - `app/Support/SnapshotBatchConfig.php`
- `SnapshotDirtyPeriodService` - `app/Support/SnapshotDirtyPeriodService.php`
- `SnapshotForceSyncCommand` - `app/Console/Commands/SnapshotForceSyncCommand.php`
- `SnapshotIntegrityGuard` - `app/Support/SnapshotIntegrityGuard.php`
- `SnapshotQueryOptimizer` - `app/Support/SnapshotQueryOptimizer.php`
- `SnapshotSourceSignatureService` - `app/Support/SnapshotSourceSignatureService.php`
- `ValidatePerformanceRmSnapshotsCommand` - `app/Console/Commands/ValidatePerformanceRmSnapshotsCommand.php`
- `ValidateSnapshotDataIntegrityCommand` - `app/Console/Commands/ValidateSnapshotDataIntegrityCommand.php`
- `WarmLandingMicroPerformanceJob` - `app/Jobs/WarmLandingMicroPerformanceJob.php`
- `WarmLandingSmeQuadrantsJob` - `app/Jobs/WarmLandingSmeQuadrantsJob.php`
- `WarmReportCacheJob` - `app/Jobs/WarmReportCacheJob.php`

## Trait Nodes

- `SnapshotJobRetryWindow` - `app/Jobs/SnapshotJobRetryWindow.php`

## Command Nodes

- `queue:ensure-running` - `app/Console/Commands/EnsureQueueWorkerRunning.php`
- `queue:watchdog` - `app/Console/Commands/QueueWorkerWatchdog.php`
- `reports:snapshot:drain-dirty` - `app/Console/Commands/DrainSnapshotDirtyPeriodsCommand.php`
- `shadow:backfill` - `app/Console/Commands/BackfillShadowColumnsCommand.php`
- `shadow:backfill-status` - `app/Console/Commands/ShadowBackfillStatusCommand.php`
- `shadow:backfill-table` - `app/Console/Commands/ShadowBackfillTableCommand.php`
- `snapshot:audit-heal` - `app/Console/Commands/AuditSnapshotsCommand.php`
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

- `default`
- `imports-high`
- `remote-sources`
- `reports-low`
- `snapshots-parallel`
- `snapshots-priority`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| jobs-snapshots -> core (calls) | 114 |
| import -> jobs-snapshots (calls) | 46 |
| jobs-snapshots -> core (uses_trait) | 35 |
| dashboard-simpanan -> jobs-snapshots (uses_trait) | 24 |
| jobs-snapshots -> core (accepts) | 23 |
| import -> jobs-snapshots (reads_table) | 22 |
| jobs-snapshots -> access-control (protected_by) | 20 |
| jobs-snapshots -> dashboard-pinjaman (reads_table) | 18 |
| dashboard-pinjaman -> jobs-snapshots (uses_trait) | 17 |
| import -> jobs-snapshots (uses_trait) | 17 |
| jobs-snapshots -> core (instantiates) | 16 |
| import -> jobs-snapshots (checks_table) | 16 |
| jobs-snapshots -> import (instantiates) | 16 |
| jobs-snapshots -> core (extends) | 16 |
| database -> jobs-snapshots (checks_table) | 14 |
| jobs-snapshots -> import (calls) | 13 |
| core -> jobs-snapshots (calls) | 13 |
| database -> jobs-snapshots (defines_table) | 12 |
| import -> jobs-snapshots (accepts) | 11 |
| core -> jobs-snapshots (contains) | 10 |
| jobs-snapshots -> dashboard-harian (calls) | 9 |
| core -> jobs-snapshots (reads_table) | 9 |
| jobs-snapshots -> dashboard-simpanan (reads_table) | 9 |
| jobs-snapshots -> platform (calls) | 9 |
| jobs-snapshots -> presentation (calls) | 9 |
| dashboard-simpanan -> jobs-snapshots (implements) | 9 |
| jobs-snapshots -> import (accepts) | 8 |
| dashboard-pinjaman -> jobs-snapshots (calls) | 8 |
| dashboard-pinjaman -> jobs-snapshots (uses_queue) | 8 |
| dashboard-pinjaman -> jobs-snapshots (implements) | 8 |
