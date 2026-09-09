# Domain: import

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=import --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 8 |
| file | 149 |
| function | 2 |
| method | 2221 |
| unresolved_symbol | 20 |
| route | 151 |
| class | 125 |
| trait | 7 |
| interface | 1 |
| table | 16 |
| view | 11 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `ImportExcelController` | class | 374 | `app/Http/Controllers/Import/ImportExcelController.php:71` |
| `ImportIndexController` | class | 162 | `app/Http/Controllers/Import/ImportIndexController.php:31` |
| `import_jobs` | table | 152 | - |
| `ImportFileController` | class | 144 | `app/Http/Controllers/Import/ImportFileController.php:30` |
| `ImportProgressService` | class | 97 | `app/Services/Import/ImportProgressService.php:13` |
| `ImportReportPhController` | class | 97 | `app/Http/Controllers/Import/ImportReportPhController.php:30` |
| `ImportPerformancePisPerProdukController` | class | 95 | `app/Http/Controllers/Import/ImportPerformancePisPerProdukController.php:23` |
| `ImportSimpananMultiPnCsvController` | class | 93 | `app/Http/Controllers/Import/ImportSimpananMultiPnCsvController.php:17` |
| `ImportCognosRecoveryController` | class | 72 | `app/Http/Controllers/Import/ImportCognosRecoveryController.php:20` |
| `ImportExecutionService` | class | 64 | `app/Services/Import/ImportExecutionService.php:17` |
| `ImportCognosPhController` | class | 63 | `app/Http/Controllers/Import/ImportCognosPhController.php:20` |
| `EnsureImportedSnapshotsFreshJob` | class | 52 | `app/Jobs/EnsureImportedSnapshotsFreshJob.php:29` |
| `processImport` | method | 50 | `app/Http/Controllers/Import/ImportFileController.php:5310` |
| `import.index` | view | 46 | `resources/views/import/index.blade.php:1` |
| `ImportSimpananMultiPnCsvControllerTest` | class | 45 | `tests/Unit/ImportSimpananMultiPnCsvControllerTest.php:21` |
| `Gi405RecDhImportExcelController` | class | 43 | `app/Http/Controllers/Import/Gi405RecDhImportExcelController.php:20` |
| `ImportCleanupService` | class | 42 | `app/Services/Import/ImportCleanupService.php:14` |
| `ImportJobManagementController` | class | 42 | `app/Http/Controllers/Import/ImportJobManagementController.php:16` |
| `previewExcel` | method | 41 | `app/Http/Controllers/Import/ImportExcelController.php:11949` |
| `import.index` | route | 39 | - |
| `preview` | method | 39 | `app/Http/Controllers/Import/ImportFileController.php:2964` |
| `ExcelStagingService` | class | 37 | `app/Services/Import/ExcelStagingService.php:5` |
| `processStagedCsvStream` | method | 37 | `app/Http/Controllers/Import/ImportExcelController.php:13441` |
| `initializeQueuedImportJobForExecution` | method | 36 | `app/Http/Controllers/Import/ImportExcelController.php:12379` |
| `ImportCasaBrilinkController` | class | 35 | `app/Http/Controllers/Import/ImportCasaBrilinkController.php:22` |
| `MySqlBulkLoadService` | class | 35 | `app/Services/Import/MySqlBulkLoadService.php:11` |
| `buildImportContext` | method | 35 | `app/Http/Controllers/Import/ImportExcelController.php:3300` |
| `detectCsvDelimiter` | method | 35 | `app/Http/Controllers/Import/ImportExcelController.php:880` |
| `previewFilterOptions` | method | 35 | `app/Http/Controllers/Import/ImportFileController.php:3400` |
| `processFastPathBulkCsvStream` | method | 35 | `app/Http/Controllers/Import/ImportExcelController.php:8233` |

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
- `import.preview` - `import/preview`
- `import.preview.direct` - `import/preview/direct`
- `import.preview.dynamic-filter-options` - `import/preview/dynamic-filter-options`

## Class Nodes

- `ActiveImportJobCounter` - `app/Services/Import/ActiveImportJobCounter.php`
- `BrilinkReportService` - `app/Services/Reports/BrilinkReportService.php`
- `BrimoReportService` - `app/Services/Reports/BrimoReportService.php`
- `ChunkReadFilter` - `app/Http/Controllers/Import/ImportExcelController.php`
- `CognosPhImportStrategy` - `app/Services/Import/Strategies/CognosPhImportStrategy.php`
- `CognosRecoveryImportStrategy` - `app/Services/Import/Strategies/CognosRecoveryImportStrategy.php`
- `ConfiguredExcelImportStrategy` - `app/Services/Import/Strategies/ConfiguredExcelImportStrategy.php`
- `CrasImportStrategy` - `app/Services/Import/Strategies/CrasImportStrategy.php`
- `CrasSourceService` - `app/Services/Import/CrasSourceService.php`
- `CriticalDashboardImportLogicContractTest` - `tests/Unit/CriticalDashboardImportLogicContractTest.php`
- `CsvAutoRepairService` - `app/Services/Import/CsvAutoRepairService.php`
- `CsvAutoRepairServiceTest` - `tests/Unit/CsvAutoRepairServiceTest.php`
- `CsvFileProfileService` - `app/Services/Import/CsvFileProfileService.php`
- `DailyLoanImportStrategy` - `app/Services/Import/Strategies/DailyLoanImportStrategy.php`
- `DeferSnapshotJobsDuringImport` - `app/Jobs/Middleware/DeferSnapshotJobsDuringImport.php`
- `DeferSnapshotJobsDuringImportTest` - `tests/Unit/DeferSnapshotJobsDuringImportTest.php`
- `DirectLargeFileLoadService` - `app/Services/Import/DirectLargeFileLoadService.php`
- `DlyKapResegmentasiCsvImporter` - `app/Services/Import/DlyKapResegmentasiCsvImporter.php`
- `DlyKapResegmentasiImportStrategy` - `app/Services/Import/Strategies/DlyKapResegmentasiImportStrategy.php`
- `EnsureImportedSnapshotsFreshJob` - `app/Jobs/EnsureImportedSnapshotsFreshJob.php`
- `EnsureImportedSnapshotsFreshJobTest` - `tests/Unit/EnsureImportedSnapshotsFreshJobTest.php`
- `ExcelImportJobService` - `app/Services/Import/ExcelImportJobService.php`
- `ExcelQueuedImportService` - `app/Services/Import/ExcelQueuedImportService.php`
- `ExcelStagingService` - `app/Services/Import/ExcelStagingService.php`
- `FixImportNotificationSyncCommand` - `app/Console/Commands/FixImportNotificationSyncCommand.php`
- `GenericCsvImportStrategy` - `app/Services/Import/Strategies/GenericCsvImportStrategy.php`
- `Gi405RecDhImportExcelController` - `app/Http/Controllers/Import/Gi405RecDhImportExcelController.php`
- `Gi405RecDhImportExcelControllerTest` - `tests/Unit/Gi405RecDhImportExcelControllerTest.php`
- `Gi405RecDhImportStrategy` - `app/Services/Import/Strategies/Gi405RecDhImportStrategy.php`
- `HourlyDpkImportStrategy` - `app/Services/Import/Strategies/HourlyDpkImportStrategy.php`
- `ImportCasaBrilinkController` - `app/Http/Controllers/Import/ImportCasaBrilinkController.php`
- `ImportCasaBrilinkControllerTest` - `tests/Unit/ImportCasaBrilinkControllerTest.php`
- `ImportCleanupController` - `app/Http/Controllers/Import/ImportCleanupController.php`
- `ImportCleanupControllerTest` - `tests/Unit/ImportCleanupControllerTest.php`
- `ImportCleanupService` - `app/Services/Import/ImportCleanupService.php`
- `ImportCleanupServiceTest` - `tests/Unit/ImportCleanupServiceTest.php`
- `ImportCognosPhController` - `app/Http/Controllers/Import/ImportCognosPhController.php`
- `ImportCognosPhControllerTest` - `tests/Unit/ImportCognosPhControllerTest.php`
- `ImportCognosRecoveryController` - `app/Http/Controllers/Import/ImportCognosRecoveryController.php`
- `ImportCognosRecoveryControllerTest` - `tests/Unit/ImportCognosRecoveryControllerTest.php`
- `ImportCrasController` - `app/Http/Controllers/Import/ImportCrasController.php`
- `ImportDailyLoanBackendController` - `app/Http/Controllers/Import/ImportDailyLoanBackendController.php`
- `ImportDataFixtureTest` - `tests/Unit/ImportDataFixtureTest.php`
- `ImportDlyKapResegmentasiCommand` - `app/Console/Commands/ImportDlyKapResegmentasiCommand.php`
- `ImportDuplicateGuardService` - `app/Services/Import/ImportDuplicateGuardService.php`
- `ImportExcelController` - `app/Http/Controllers/Import/ImportExcelController.php`
- `ImportExcelControllerBrihcTest` - `tests/Unit/ImportExcelControllerBrihcTest.php`
- `ImportExcelControllerDailyLoanCsvTest` - `tests/Unit/ImportExcelControllerDailyLoanCsvTest.php`
- `ImportExcelControllerDailyLoanDuplicateGuardTest` - `tests/Unit/ImportExcelControllerDailyLoanDuplicateGuardTest.php`
- `ImportExcelControllerDecimalNormalizationTest` - `tests/Unit/ImportExcelControllerDecimalNormalizationTest.php`
- `ImportExcelControllerFastPathEligibilityTest` - `tests/Unit/ImportExcelControllerFastPathEligibilityTest.php`
- `ImportExcelControllerGi405RecDhTest` - `tests/Unit/ImportExcelControllerGi405RecDhTest.php`
- `ImportExcelControllerHourlyDpkTest` - `tests/Unit/ImportExcelControllerHourlyDpkTest.php`
- `ImportExcelControllerLocaleDateScopeTest` - `tests/Unit/ImportExcelControllerLocaleDateScopeTest.php`
- `ImportExcelControllerLw321PnTest` - `tests/Unit/ImportExcelControllerLw321PnTest.php`
- `ImportExcelControllerLw325CsvRepairTest` - `tests/Unit/ImportExcelControllerLw325CsvRepairTest.php`
- `ImportExcelControllerRkaManualKancaTest` - `tests/Unit/ImportExcelControllerRkaManualKancaTest.php`
- `ImportExcelControllerSnapshotReplaceTest` - `tests/Unit/ImportExcelControllerSnapshotReplaceTest.php`
- `ImportExcelControllerSsaAlmafactsTest` - `tests/Unit/ImportExcelControllerSsaAlmafactsTest.php`
- `ImportExcelControllerSsaPinjamanTest` - `tests/Unit/ImportExcelControllerSsaPinjamanTest.php`
- `ImportExcelControllerSsaSimpananTest` - `tests/Unit/ImportExcelControllerSsaSimpananTest.php`
- `ImportExcelControllerWilayahMbmTest` - `tests/Unit/ImportExcelControllerWilayahMbmTest.php`
- `ImportExcelQueuedFallbackTest` - `tests/Unit/ImportExcelQueuedFallbackTest.php`
- `ImportExecutionService` - `app/Services/Import/ImportExecutionService.php`
- `ImportExecutionServiceTest` - `tests/Unit/ImportExecutionServiceTest.php`
- `ImportFileBrimoController` - `app/Http/Controllers/Import/ImportFileBrimoController.php`
- `ImportFileBrimoControllerTest` - `tests/Unit/ImportFileBrimoControllerTest.php`
- `ImportFileController` - `app/Http/Controllers/Import/ImportFileController.php`
- `ImportFileControllerTest` - `tests/Unit/ImportFileControllerTest.php`
- `ImportHealthCheckCommand` - `app/Console/Commands/ImportHealthCheckCommand.php`
- `ImportHealthCheckCommandTest` - `tests/Unit/ImportHealthCheckCommandTest.php`
- `ImportIndexController` - `app/Http/Controllers/Import/ImportIndexController.php`
- `ImportJobManagementController` - `app/Http/Controllers/Import/ImportJobManagementController.php`
- `ImportJobManagementControllerTest` - `tests/Unit/ImportJobManagementControllerTest.php`
- `ImportJobStatusController` - `app/Http/Controllers/Import/ImportJobStatusController.php`
- `ImportJobStatusControllerTest` - `tests/Unit/ImportJobStatusControllerTest.php`
- `ImportL1133Command` - `app/Console/Commands/ImportL1133Command.php`
- `ImportNotificationSyncService` - `app/Services/Import/ImportNotificationSyncService.php`
- `ImportPerformancePisPerProdukController` - `app/Http/Controllers/Import/ImportPerformancePisPerProdukController.php`
- `ImportPerformancePisPerProdukControllerTest` - `tests/Unit/ImportPerformancePisPerProdukControllerTest.php`

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
- `import.partials.report-management-scripts` - `resources/views/import/partials/report-management-scripts.blade.php`
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
- `brimo_scope_resolution`
- `casa_brilink_edc`
- `casa_brilink_web`
- `import_jobs`
- `import_jobsku`
- `jumlah_merchant_detail`
- `jumlah_merchant_qris`
- `jumlah_merchant_qris_detail`
- `merchant_qris`
- `merchant_qris_volume`
- `sv_merchant`
- `user_brimo_fin`
- `user_brimo_rpt_v2`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| import -> core (calls) | 781 |
| import -> access-control (protected_by) | 776 |
| import -> core (instantiates) | 357 |
| import -> core (accepts) | 179 |
| import -> jobs-snapshots (calls) | 73 |
| import -> tests (instantiates) | 61 |
| import -> core (reads_table) | 51 |
| import -> tests (extends) | 48 |
| import -> core (writes_table) | 47 |
| import -> core (defines_table) | 35 |
| database -> import (checks_table) | 34 |
| import -> dashboard-pinjaman (writes_table) | 27 |
| import -> core (extends) | 25 |
| tests -> import (contains) | 24 |
| import -> jobs-snapshots (instantiates) | 18 |
| core -> import (calls) | 17 |
| import -> jobs-snapshots (uses_trait) | 17 |
| database -> import (defines_table) | 16 |
| import -> core (checks_table) | 15 |
| import -> dashboard-harian (calls) | 14 |
| jobs-snapshots -> import (instantiates) | 14 |
| import -> dashboard-simpanan (writes_table) | 14 |
| import -> dashboard-pinjaman (defines_table) | 13 |
| database -> import (uses_table) | 11 |
| import -> jobs-snapshots (accepts) | 11 |
| import -> dashboard-pinjaman (reads_table) | 11 |
| tests -> import (instantiates) | 11 |
| core -> import (reads_table) | 10 |
| input-management -> import (references_route) | 10 |
| import -> dashboard-harian (accepts) | 10 |
