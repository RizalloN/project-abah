# Domain: import

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=import --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 8 |
| file | 136 |
| method | 2150 |
| route | 173 |
| class | 112 |
| trait | 7 |
| interface | 1 |
| table | 26 |
| view | 16 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `ImportExcelController` | class | 390 | `app/Http/Controllers/Import/ImportExcelController.php:78` |
| `ImportIndexController` | class | 159 | `app/Http/Controllers/Import/ImportIndexController.php:31` |
| `ImportFileController` | class | 126 | `app/Http/Controllers/Import/ImportFileController.php:32` |
| `import_jobs` | table | 120 | - |
| `ImportProgressService` | class | 104 | `app/Services/Import/ImportProgressService.php:13` |
| `ImportReportPhController` | class | 103 | `app/Http/Controllers/Import/ImportReportPhController.php:33` |
| `ImportPerformancePisPerProdukController` | class | 83 | `app/Http/Controllers/Import/ImportPerformancePisPerProdukController.php:23` |
| `ImportSimpananMultiPnCsvController` | class | 67 | `app/Http/Controllers/Import/ImportSimpananMultiPnCsvController.php:17` |
| `ImportCognosRecoveryController` | class | 62 | `app/Http/Controllers/Import/ImportCognosRecoveryController.php:20` |
| `ImportExecutionService` | class | 57 | `app/Services/Import/ImportExecutionService.php:16` |
| `ImportCognosPhController` | class | 55 | `app/Http/Controllers/Import/ImportCognosPhController.php:20` |
| `processImport` | method | 53 | `app/Http/Controllers/Import/ImportFileController.php:5512` |
| `import.index` | view | 49 | `resources/views/import/index.blade.php:1` |
| `EnsureImportedSnapshotsFreshJob` | class | 46 | `app/Jobs/EnsureImportedSnapshotsFreshJob.php:31` |
| `processStagedCsvStream` | method | 45 | `app/Http/Controllers/Import/ImportExcelController.php:14812` |
| `QueueWorkerControlService` | class | 42 | `app/Services/Import/QueueWorkerControlService.php:10` |
| `preview` | method | 42 | `app/Http/Controllers/Import/ImportFileController.php:3135` |
| `previewExcel` | method | 42 | `app/Http/Controllers/Import/ImportExcelController.php:13207` |
| `ImportJobManagementController` | class | 41 | `app/Http/Controllers/Import/ImportJobManagementController.php:19` |
| `processFastPathBulkCsvStream` | method | 41 | `app/Http/Controllers/Import/ImportExcelController.php:9353` |
| `import.index` | route | 40 | - |
| `Gi405RecDhImportExcelController` | class | 39 | `app/Http/Controllers/Import/Gi405RecDhImportExcelController.php:20` |
| `initializeQueuedImportJobForExecution` | method | 39 | `app/Http/Controllers/Import/ImportExcelController.php:13685` |
| `ExcelStagingService` | class | 37 | `app/Services/Import/ExcelStagingService.php:7` |
| `buildImportContext` | method | 37 | `app/Http/Controllers/Import/ImportExcelController.php:3728` |
| `initExcelImport` | method | 36 | `app/Http/Controllers/Import/ImportExcelController.php:14139` |
| `processImportStream` | method | 36 | `app/Http/Controllers/Import/ImportFileController.php:4983` |
| `detectCsvDelimiter` | method | 35 | `app/Http/Controllers/Import/ImportExcelController.php:1004` |
| `previewFilterOptions` | method | 35 | `app/Http/Controllers/Import/ImportFileController.php:3589` |
| `ImportCasaBrilinkController` | class | 33 | `app/Http/Controllers/Import/ImportCasaBrilinkController.php:22` |

## Route Nodes

- `bod-boc.import-preview` - `bod-boc/import-preview`
- `bod-boc.import-template` - `bod-boc/import-template`
- `import.backend.daily-loan.local-file` - `import/backend/daily-loan/local-file`
- `import.brimo.preview` - `import/brimo/preview`
- `import.brimo.process` - `import/brimo/process`
- `import.brimo.upload` - `import/brimo/upload`
- `import.casabrilink.filter-options` - `import/casa-brilink/preview/filter-options`
- `import.casabrilink.filtered-rows` - `import/casa-brilink/preview/filtered-rows`
- `import.casabrilink.init` - `import/casa-brilink/init`
- `import.casabrilink.prepare-preview` - `import/casa-brilink/prepare-preview`
- `import.casabrilink.preview` - `import/casa-brilink/preview`
- `import.casabrilink.preview.refresh` - `import/casa-brilink/preview`
- `import.casabrilink.process` - `import/casa-brilink/process`
- `import.casabrilink.stream` - `import/casa-brilink/stream`
- `import.casabrilink.upload` - `import/casa-brilink/upload`
- `import.cleanup-artifacts` - `import/cleanup-artifacts`
- `import.cognos-ph.filter-options` - `import/cognos-ph/preview/filter-options`
- `import.cognos-ph.filtered-rows` - `import/cognos-ph/preview/filtered-rows`
- `import.cognos-ph.init` - `import/cognos-ph/init`
- `import.cognos-ph.prepare-preview` - `import/cognos-ph/prepare-preview`
- `import.cognos-ph.preview` - `import/cognos-ph/preview`
- `import.cognos-ph.preview.refresh` - `import/cognos-ph/preview`
- `import.cognos-ph.process` - `import/cognos-ph/process`
- `import.cognos-ph.stream` - `import/cognos-ph/stream`
- `import.cognos-ph.upload` - `import/cognos-ph/upload`
- `import.cognos-recovery.filter-options` - `import/cognos-recovery/preview/filter-options`
- `import.cognos-recovery.filtered-rows` - `import/cognos-recovery/preview/filtered-rows`
- `import.cognos-recovery.init` - `import/cognos-recovery/init`
- `import.cognos-recovery.prepare-preview` - `import/cognos-recovery/prepare-preview`
- `import.cognos-recovery.preview` - `import/cognos-recovery/preview`
- `import.cognos-recovery.preview.refresh` - `import/cognos-recovery/preview`
- `import.cognos-recovery.process` - `import/cognos-recovery/process`
- `import.cognos-recovery.stream` - `import/cognos-recovery/stream`
- `import.cognos-recovery.upload` - `import/cognos-recovery/upload`
- `import.cras.filter-options` - `import/cras/preview/filter-options`
- `import.cras.filtered-rows` - `import/cras/preview/filtered-rows`
- `import.cras.init` - `import/cras/init`
- `import.cras.prepare-preview` - `import/cras/prepare-preview`
- `import.cras.preview` - `import/cras/preview`
- `import.cras.stream` - `import/cras/stream`
- `import.cras.upload` - `import/cras/upload`
- `import.cras.upload-chunk` - `import/cras/upload-chunk`
- `import.cras.upload-chunk.finalize` - `import/cras/upload-chunk/finalize`
- `import.cras.upload-chunk.init` - `import/cras/upload-chunk/init`
- `import.dailyloan.chunk` - `import-excel/daily-loan-dinamis/chunk`
- `import.dailyloan.init` - `import-excel/daily-loan-dinamis/init`
- `import.dailyloan.prepare-preview` - `import-excel/daily-loan-dinamis/prepare-preview`
- `import.dailyloan.preview` - `import-excel/daily-loan-dinamis/preview`
- `import.dailyloan.stream` - `import-excel/daily-loan-dinamis/stream`
- `import.dailyloan.upload` - `import-excel/daily-loan-dinamis/upload`
- `import.dailyloan.upload-chunk` - `import-excel/daily-loan-dinamis/upload-chunk`
- `import.dailyloan.upload-chunk.finalize` - `import-excel/daily-loan-dinamis/upload-chunk/finalize`
- `import.dailyloan.upload-chunk.init` - `import-excel/daily-loan-dinamis/upload-chunk/init`
- `import.excel.chunk` - `import-excel/chunk`
- `import.excel.init` - `import-excel/init`
- `import.excel.prepare-preview` - `import-excel/prepare-preview`
- `import.excel.preview` - `import-excel/preview`
- `import.excel.stream` - `import-excel/stream`
- `import.excel.upload` - `import-excel/upload`
- `import.excel.upload-chunk` - `import-excel/upload-chunk`
- `import.excel.upload-chunk.finalize` - `import-excel/upload-chunk/finalize`
- `import.excel.upload-chunk.init` - `import-excel/upload-chunk/init`
- `import.gi405.chunk` - `import-excel/gi405-rec-dh/chunk`
- `import.gi405.init` - `import-excel/gi405-rec-dh/init`
- `import.gi405.prepare-preview` - `import-excel/gi405-rec-dh/prepare-preview`
- `import.gi405.preview` - `import-excel/gi405-rec-dh/preview`
- `import.gi405.stream` - `import-excel/gi405-rec-dh/stream`
- `import.gi405.upload` - `import-excel/gi405-rec-dh/upload`
- `import.index` - `import`
- `import.init` - `import/init`
- `import.jobs.status` - `import/jobs/{jobId}/status`
- `import.performancepis.filter-options` - `import/performance-pis/preview/filter-options`
- `import.performancepis.filtered-rows` - `import/performance-pis/preview/filtered-rows`
- `import.performancepis.init` - `import/performance-pis/init`
- `import.performancepis.prepare-preview` - `import/performance-pis/prepare-preview`
- `import.performancepis.preview` - `import/performance-pis/preview`
- `import.performancepis.preview.refresh` - `import/performance-pis/preview`
- `import.performancepis.process` - `import/performance-pis/process`
- `import.performancepis.stream` - `import/performance-pis/stream`
- `import.performancepis.upload` - `import/performance-pis/upload`

## Class Nodes

- `ActiveImportJobCounter` - `app/Services/Import/ActiveImportJobCounter.php`
- `BrilinkReportService` - `app/Services/Reports/BrilinkReportService.php`
- `BrimoReportService` - `app/Services/Reports/BrimoReportService.php`
- `ChunkReadFilter` - `app/Http/Controllers/Import/ImportExcelController.php`
- `CognosPhImportStrategy` - `app/Services/Import/Strategies/CognosPhImportStrategy.php`
- `CognosRecoveryImportStrategy` - `app/Services/Import/Strategies/CognosRecoveryImportStrategy.php`
- `ColumnFormatMemory` - `app/Services/Import/SchemaDrift/ColumnFormatMemory.php`
- `ColumnValueProfiler` - `app/Support/Import/ColumnValueProfiler.php`
- `ConfiguredExcelImportStrategy` - `app/Services/Import/Strategies/ConfiguredExcelImportStrategy.php`
- `ControlCenterController` - `app/Http/Controllers/Import/ControlCenterController.php`
- `CrasImportStrategy` - `app/Services/Import/Strategies/CrasImportStrategy.php`
- `CrasSourceService` - `app/Services/Import/CrasSourceService.php`
- `CrosstabNormalizationNotice` - `app/Services/Import/Crosstab/CrosstabNormalizationNotice.php`
- `CrosstabProfile` - `app/Services/Import/Crosstab/CrosstabProfile.php`
- `CrosstabProfileRegistry` - `app/Services/Import/Crosstab/CrosstabProfileRegistry.php`
- `CsvAutoRepairService` - `app/Services/Import/CsvAutoRepairService.php`
- `CsvFileProfileService` - `app/Services/Import/CsvFileProfileService.php`
- `DailyLoanImportStrategy` - `app/Services/Import/Strategies/DailyLoanImportStrategy.php`
- `DataPhGi405RecoveryService` - `app/Services/Reports/DataPhGi405RecoveryService.php`
- `DeferSnapshotJobsDuringImport` - `app/Jobs/Middleware/DeferSnapshotJobsDuringImport.php`
- `DirectLargeFileLoadService` - `app/Services/Import/DirectLargeFileLoadService.php`
- `DlyKapResegmentasiCsvImporter` - `app/Services/Import/DlyKapResegmentasiCsvImporter.php`
- `DlyKapResegmentasiImportStrategy` - `app/Services/Import/Strategies/DlyKapResegmentasiImportStrategy.php`
- `EnsureImportedSnapshotsFreshJob` - `app/Jobs/EnsureImportedSnapshotsFreshJob.php`
- `ExcelImportJobService` - `app/Services/Import/ExcelImportJobService.php`
- `ExcelQueuedImportService` - `app/Services/Import/ExcelQueuedImportService.php`
- `ExcelStagingService` - `app/Services/Import/ExcelStagingService.php`
- `FixImportNotificationSyncCommand` - `app/Console/Commands/FixImportNotificationSyncCommand.php`
- `GenericCsvImportStrategy` - `app/Services/Import/Strategies/GenericCsvImportStrategy.php`
- `Gi405RecDhImportExcelController` - `app/Http/Controllers/Import/Gi405RecDhImportExcelController.php`
- `Gi405RecDhImportStrategy` - `app/Services/Import/Strategies/Gi405RecDhImportStrategy.php`
- `Gi405SingleRowImportStrategy` - `app/Services/Import/Strategies/Gi405SingleRowImportStrategy.php`
- `Gi405SingleRowRecoverySource` - `app/Support/Gi405SingleRowRecoverySource.php`
- `Gi405SingleRowValueNormalizer` - `app/Services/Import/Gi405SingleRowValueNormalizer.php`
- `HourlyDpkImportStrategy` - `app/Services/Import/Strategies/HourlyDpkImportStrategy.php`
- `ImportCasaBrilinkController` - `app/Http/Controllers/Import/ImportCasaBrilinkController.php`
- `ImportCleanupController` - `app/Http/Controllers/Import/ImportCleanupController.php`
- `ImportCleanupService` - `app/Services/Import/ImportCleanupService.php`
- `ImportCognosPhController` - `app/Http/Controllers/Import/ImportCognosPhController.php`
- `ImportCognosRecoveryController` - `app/Http/Controllers/Import/ImportCognosRecoveryController.php`
- `ImportCrasController` - `app/Http/Controllers/Import/ImportCrasController.php`
- `ImportDailyLoanBackendController` - `app/Http/Controllers/Import/ImportDailyLoanBackendController.php`
- `ImportDlyKapResegmentasiCommand` - `app/Console/Commands/ImportDlyKapResegmentasiCommand.php`
- `ImportDuplicateGuardService` - `app/Services/Import/ImportDuplicateGuardService.php`
- `ImportExcelController` - `app/Http/Controllers/Import/ImportExcelController.php`
- `ImportExecutionService` - `app/Services/Import/ImportExecutionService.php`
- `ImportFileBrimoController` - `app/Http/Controllers/Import/ImportFileBrimoController.php`
- `ImportFileController` - `app/Http/Controllers/Import/ImportFileController.php`
- `ImportHeaderAliases` - `app/Support/Import/ImportHeaderAliases.php`
- `ImportHealthCheckCommand` - `app/Console/Commands/ImportHealthCheckCommand.php`
- `ImportIndexController` - `app/Http/Controllers/Import/ImportIndexController.php`
- `ImportJobManagementController` - `app/Http/Controllers/Import/ImportJobManagementController.php`
- `ImportJobStatusController` - `app/Http/Controllers/Import/ImportJobStatusController.php`
- `ImportL1133Command` - `app/Console/Commands/ImportL1133Command.php`
- `ImportMauBrimoController` - `app/Http/Controllers/Import/ImportMauBrimoController.php`
- `ImportNotificationSyncService` - `app/Services/Import/ImportNotificationSyncService.php`
- `ImportPerformancePisPerProdukController` - `app/Http/Controllers/Import/ImportPerformancePisPerProdukController.php`
- `ImportPeriodGuardService` - `app/Services/Import/ImportPeriodGuardService.php`
- `ImportPipelineService` - `app/Services/Import/ImportPipelineService.php`
- `ImportPreviewErrorHandler` - `app/Support/ImportPreviewErrorHandler.php`
- `ImportProgressService` - `app/Services/Import/ImportProgressService.php`
- `ImportReferensiUkerCommand` - `app/Console/Commands/ImportReferensiUkerCommand.php`
- `ImportReportPhController` - `app/Http/Controllers/Import/ImportReportPhController.php`
- `ImportSchemaDriftController` - `app/Http/Controllers/Import/ImportSchemaDriftController.php`
- `ImportSessionContext` - `app/Support/Import/ImportSessionContext.php`
- `ImportSimpananMultiPnCsvController` - `app/Http/Controllers/Import/ImportSimpananMultiPnCsvController.php`
- `ImportSourceSampler` - `app/Services/Import/SchemaDrift/ImportSourceSampler.php`
- `ImportStrategyFactory` - `app/Services/Import/ImportStrategyFactory.php`
- `L1133CsvImporter` - `app/Services/Import/L1133CsvImporter.php`
- `L1133ImportStrategy` - `app/Services/Import/Strategies/L1133ImportStrategy.php`
- `Lw321PnImportStrategy` - `app/Services/Import/Strategies/Lw321PnImportStrategy.php`
- `Lw325PhImportStrategy` - `app/Services/Import/Strategies/Lw325PhImportStrategy.php`
- `MauBrimoImportPolicy` - `app/Services/Import/MauBrimoImportPolicy.php`
- `MySqlBulkLoadService` - `app/Services/Import/MySqlBulkLoadService.php`
- `OptimizedCsvImporter` - `app/Services/Import/OptimizedCsvImporter.php`
- `PerformanceBrimoController` - `app/Http/Controllers/PerformanceBrimoController.php`
- `PerformancePisImportStrategy` - `app/Services/Import/Strategies/PerformancePisImportStrategy.php`
- `PrepareCsvStagingJob` - `app/Jobs/PrepareCsvStagingJob.php`
- `ProcessPolarsImportPhJob` - `app/Jobs/ProcessPolarsImportPhJob.php`
- `QueueSupervisorProcessRunner` - `app/Services/Import/QueueSupervisorProcessRunner.php`

## Interface Nodes

- `ImportStrategyInterface` - `app/Services/Import/Strategies/ImportStrategyInterface.php`

## Trait Nodes

- `AllocatesGapIds` - `app/Http/Controllers/Import/Concerns/AllocatesGapIds.php`
- `AuthorizesImportSourceFiles` - `app/Http/Controllers/Import/Concerns/AuthorizesImportSourceFiles.php`
- `AuthorizesSessionImportStorageFiles` - `app/Http/Controllers/Import/Concerns/AuthorizesSessionImportStorageFiles.php`
- `BuildsArea6PreviewFilters` - `app/Http/Controllers/Import/Concerns/BuildsArea6PreviewFilters.php`
- `GeneratesFileIdentifiers` - `app/Http/Controllers/Import/Concerns/GeneratesFileIdentifiers.php`
- `ServesMappedCsvPreviewFilters` - `app/Http/Controllers/Import/Concerns/ServesMappedCsvPreviewFilters.php`
- `SmartCsvImportSupport` - `app/Http/Controllers/Import/Concerns/SmartCsvImportSupport.php`

## Command Nodes

- `import:dly-kap-resegmentasi` - `app/Console/Commands/ImportDlyKapResegmentasiCommand.php`
- `import:fix-notification-sync` - `app/Console/Commands/FixImportNotificationSyncCommand.php`
- `import:health-check` - `app/Console/Commands/ImportHealthCheckCommand.php`
- `import:l1133` - `app/Console/Commands/ImportL1133Command.php`
- `import:referensi-uker` - `app/Console/Commands/ImportReferensiUkerCommand.php`
- `import:run-job` - `app/Console/Commands/RunImportJobCommand.php`
- `import:warm-preview-index` - `app/Console/Commands/WarmImportPreviewIndexCommand.php`
- `test:import-optimization` - `app/Console/Commands/TestImportOptimization.php`

## View Nodes

- `import.index` - `resources/views/import/index.blade.php`
- `import.job-management` - `resources/views/import/job-management.blade.php`
- `import.partials.control-center` - `resources/views/import/partials/control-center.blade.php`
- `import.partials.crosstab-normalization-notice` - `resources/views/import/partials/crosstab-normalization-notice.blade.php`
- `import.partials.filter-early-click` - `resources/views/import/partials/filter-early-click.blade.php`
- `import.partials.report-management-scripts` - `resources/views/import/partials/report-management-scripts.blade.php`
- `import.partials.schema-drift-panel` - `resources/views/import/partials/schema-drift-panel.blade.php`
- `import.partials.swal-modern` - `resources/views/import/partials/swal-modern.blade.php`
- `import.preview` - `resources/views/import/preview.blade.php`
- `import.preview_excel` - `resources/views/import/preview_excel.blade.php`
- `import.report-management` - `resources/views/import/report-management.blade.php`
- `import.select-file` - `resources/views/import/select-file.blade.php`
- `input.bod-boc-import-preview` - `resources/views/input/bod-boc-import-preview.blade.php`
- `input.import-preview` - `resources/views/input/import-preview.blade.php`
- `report.performance-brilink` - `resources/views/report/performance-brilink.blade.php`
- `report.performance-brimo` - `resources/views/report/performance-brimo.blade.php`

## Table Nodes

- `brilink_web_laporan_summary_transaksi_brilink_web`
- `brimo_fin`
- `brimo_fin_all`
- `casa_brilink_edc`
- `casa_brilink_web`
- `cognos_ph`
- `cognos_recovery`
- `gi405_rec_dh`
- `gi405_recovery`
- `gi405_singlerow`
- `ibbisniz_corp`
- `import_column_format_profiles`
- `import_jobs`
- `import_jobsku`
- `import_schema_drift_events`
- `import_source_header_baselines`
- `jumlah_merchant_detail`
- `jumlah_merchant_qris`
- `jumlah_merchant_qris_detail`
- `mau_brimo_r_all`
- `merchant_qris`
- `merchant_qris_volume`
- `sv_merchant`
- `usak_ibbiz_uker`
- `user_brimo_fin`
- `user_brimo_rpt_v2`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| import -> access-control (protected_by) | 1065 |
| import -> core (calls) | 659 |
| import -> core (instantiates) | 301 |
| import -> core (accepts) | 226 |
| database -> import (checks_table) | 47 |
| import -> jobs-snapshots (calls) | 46 |
| import -> core (reads_table) | 29 |
| database -> import (defines_table) | 28 |
| import -> core (extends) | 26 |
| core -> import (reads_table) | 23 |
| import -> jobs-snapshots (reads_table) | 22 |
| core -> import (calls) | 18 |
| import -> jobs-snapshots (uses_trait) | 17 |
| import -> jobs-snapshots (checks_table) | 16 |
| jobs-snapshots -> import (instantiates) | 16 |
| jobs-snapshots -> import (calls) | 13 |
| import -> dashboard-harian (calls) | 12 |
| database -> import (uses_table) | 11 |
| import -> jobs-snapshots (accepts) | 11 |
| database -> import (alters_table) | 10 |
| core -> import (injects) | 10 |
| import -> dashboard-harian (accepts) | 10 |
| import -> platform (extends_view) | 10 |
| jobs-snapshots -> import (accepts) | 8 |
| import -> core (checks_table) | 8 |
| import -> platform (calls) | 8 |
| dashboard-harian -> import (checks_table) | 8 |
| dashboard-simpanan -> import (reads_table) | 7 |
| import -> database (calls) | 7 |
| import -> jobs-snapshots (implements) | 7 |
