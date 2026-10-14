# Domain: core

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=core --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 15 |
| file | 107 |
| method | 703 |
| unresolved_symbol | 344 |
| route | 15 |
| class | 67 |
| table | 15 |
| view | 44 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `nama_report` | table | 145 | - |
| `LandingMicroPerformanceService` | class | 84 | `app/Support/LandingMicroPerformanceService.php:16` |
| `ReportDataSyncService` | class | 71 | `app/Support/ReportDataSyncService.php:19` |
| `normalize` | method | 71 | `app/Support/StrictDateParser.php:29` |
| `LandingSmeOperationalService` | class | 64 | `app/Support/LandingSmeOperationalService.php:16` |
| `Controller` | class | 53 | `app/Http/Controllers/Controller.php:7` |
| `ManagedReportManagementService` | class | 51 | `app/Support/ManagedReportManagementService.php:11` |
| `apply` | method | 41 | `app/Support/SargableDateFilter.php:9` |
| `LandingConsumerOperationalService` | class | 39 | `app/Support/LandingConsumerOperationalService.php:15` |
| `DataPhReportController` | class | 37 | `app/Http/Controllers/Report/DataPhReportController.php:15` |
| `KinerjaNonPtpReportController` | class | 36 | `app/Http/Controllers/Report/KinerjaNonPtpReportController.php:17` |
| `RkaLookupService` | class | 33 | `app/Support/RkaLookupService.php:11` |
| `rka` | table | 32 | - |
| `syncImportedTable` | method | 31 | `app/Support/ReportDataSyncService.php:135` |
| `FileManagementController` | class | 29 | `app/Http/Controllers/Admin/FileManagementController.php:24` |
| `index` | method | 26 | `app/Http/Controllers/Report/DataPhReportController.php:23` |
| `analyzeTable` | method | 24 | `app/Support/ReportDataSyncService.php:21` |
| `MicroNettDisbursementCalculator` | class | 23 | `app/Support/MicroNettDisbursementCalculator.php:13` |
| `buildPayload` | method | 23 | `app/Support/LandingMicroPerformanceService.php:517` |
| `drive.index` | view | 23 | `resources/views/drive/index.blade.php:1` |
| `ShadowColumnRuleEngine` | class | 22 | `app/Services/Shadow/ShadowColumnRuleEngine.php:8` |
| `SppgReportService` | class | 21 | `app/Services/Reports/SppgReportService.php:15` |
| `index` | method | 21 | `app/Http/Controllers/Report/KinerjaNonPtpReportController.php:52` |
| `LinkManagementController` | class | 20 | `app/Http/Controllers/Admin/LinkManagementController.php:17` |
| `fetchPlafondRealizationRows` | method | 20 | `app/Support/LandingMicroPerformanceService.php:1317` |
| `quadrantPayload` | method | 20 | `app/Support/LandingConsumerOperationalService.php:629` |
| `PruneReportDailyHistoryCommand` | class | 19 | `app/Console/Commands/PruneReportDailyHistoryCommand.php:19` |
| `branchLabel` | method | 18 | `app/Support/LandingMicroPerformanceService.php:3494` |
| `buildMantriPerformance` | method | 17 | `app/Support/LandingMicroPerformanceService.php:2248` |
| `handle` | method | 17 | `app/Console/Commands/PruneReportDailyHistoryCommand.php:58` |

## Route Nodes

- `file-management.destroy` - `file-management/delete`
- `file-management.download` - `file-management/download`
- `file-management.index` - `file-management`
- `generated::SdvcnUDqWZA1cuy6` - `up`
- `home` - `/`
- `link-management.index` - `link-management`
- `link-management.update` - `link-management`
- `report.data` - `report/data`
- `report.edc` - `report/optimalisasi-digital/edc`
- `report.kolaborasi.referral` - `report/kolaborasi-perusahaan-anak/program-referral-partner-perusahaan-anak`
- `report.qlola` - `report/optimalisasi-digital/qlola`
- `report.qris` - `report/optimalisasi-digital/qris`
- `report.qris.ukers` - `report/data/qris/ukers`
- `sheet`
- `token`

## Class Nodes

- `AnalyzeDeleteAuditsCommand` - `app/Console/Commands/AnalyzeDeleteAuditsCommand.php`
- `BatchDuplicateValidationService` - `app/Support/BatchDuplicateValidationService.php`
- `BenchmarkDeletePerformanceCommand` - `app/Console/Commands/BenchmarkDeletePerformanceCommand.php`
- `BreakdownRkaSyncService` - `app/Services/Rka/BreakdownRkaSyncService.php`
- `ConsumerKanwilReference` - `app/Support/ConsumerKanwilReference.php`
- `Controller` - `app/Http/Controllers/Controller.php`
- `ConvertDocumentToMarkdownCommand` - `app/Console/Commands/ConvertDocumentToMarkdownCommand.php`
- `DashboardCrossAlignmentGuard` - `app/Support/DashboardCrossAlignmentGuard.php`
- `DataPhReportController` - `app/Http/Controllers/Report/DataPhReportController.php`
- `DigitalPerformanceController` - `app/Http/Controllers/Report/DigitalPerformanceController.php`
- `DocumentMarkdownService` - `app/Services/DocumentMarkdownService.php`
- `EdcReportService` - `app/Services/Reports/EdcReportService.php`
- `FileManagementController` - `app/Http/Controllers/Admin/FileManagementController.php`
- `FileManagementDownloadController` - `app/Http/Controllers/Admin/FileManagementDownloadController.php`
- `JobHealthService` - `app/Services/JobHealthService.php`
- `Kernel` - `app/Console/Kernel.php`
- `KinerjaNonPtpReportController` - `app/Http/Controllers/Report/KinerjaNonPtpReportController.php`
- `KolaborasiReportController` - `app/Http/Controllers/Report/KolaborasiReportController.php`
- `KolaborasiReportService` - `app/Services/Reports/KolaborasiReportService.php`
- `LandingConsumerOperationalService` - `app/Support/LandingConsumerOperationalService.php`
- `LandingMicroPerformanceService` - `app/Support/LandingMicroPerformanceService.php`
- `LandingPayloadState` - `app/Support/LandingPayloadState.php`
- `LandingPnMismatchService` - `app/Support/LandingPnMismatchService.php`
- `LandingRmKurDistribution` - `app/Support/LandingRmKurDistribution.php`
- `LandingSmeOperationalService` - `app/Support/LandingSmeOperationalService.php`
- `LinkManagementController` - `app/Http/Controllers/Admin/LinkManagementController.php`
- `MaintainApplicationLogsCommand` - `app/Console/Commands/MaintainApplicationLogsCommand.php`
- `ManagedReportDeleteRecoveryService` - `app/Support/ManagedReportDeleteRecoveryService.php`
- `ManagedReportLoadCoordinator` - `app/Support/ManagedReportLoadCoordinator.php`
- `ManagedReportLoadStore` - `app/Support/ManagedReportLoadStore.php`
- `ManagedReportManagementService` - `app/Support/ManagedReportManagementService.php`
- `ManagedReportRecoveryCoordinator` - `app/Support/ManagedReportRecoveryCoordinator.php`
- `ManagedReportRecoveryStore` - `app/Support/ManagedReportRecoveryStore.php`
- `MicroNettDisbursementCalculator` - `app/Support/MicroNettDisbursementCalculator.php`
- `MicroRmRosterReference` - `app/Support/MicroRmRosterReference.php`
- `NamaReport` - `app/Models/NamaReport.php`
- `OptimizedBulkDeleteService` - `app/Support/OptimizedBulkDeleteService.php`
- `OptimizedDuplicateCleanupService` - `app/Support/OptimizedDuplicateCleanupService.php`
- `OptimizedRkaLookupService` - `app/Support/OptimizedRkaLookupService.php`
- `PruneReportDailyHistoryCommand` - `app/Console/Commands/PruneReportDailyHistoryCommand.php`
- `PublicAccessHealthService` - `app/Services/Network/PublicAccessHealthService.php`
- `QlolaReportService` - `app/Services/Reports/QlolaReportService.php`
- `QrisReportService` - `app/Services/Reports/QrisReportService.php`
- `RebuildPerformanceRmCommand` - `app/Console/Commands/RebuildPerformanceRmCommand.php`
- `RecoverSafeDumpCommand` - `app/Console/Commands/RecoverSafeDumpCommand.php`
- `RefreshRemoteDashboardSourcesCommand` - `app/Console/Commands/RefreshRemoteDashboardSourcesCommand.php`
- `ReportDataSyncService` - `app/Support/ReportDataSyncService.php`
- `ReportFilterService` - `app/Services/Reports/ReportFilterService.php`
- `ReportIndexHintResolver` - `app/Support/ReportIndexHintResolver.php`
- `RestoreDatabasePartCommand` - `app/Console/Commands/RestoreDatabasePartCommand.php`
- `RkaLookupService` - `app/Support/RkaLookupService.php`
- `SafeSqlDumpRecoveryService` - `app/Services/SafeSqlDumpRecoveryService.php`
- `SargableDateFilter` - `app/Support/SargableDateFilter.php`
- `ScheduledRebuildPerformanceRmCommand` - `app/Console/Commands/ScheduledRebuildPerformanceRmCommand.php`
- `SchedulerHeartbeatCommand` - `app/Console/Commands/SchedulerHeartbeatCommand.php`
- `ShadowColumnRuleEngine` - `app/Services/Shadow/ShadowColumnRuleEngine.php`
- `ShadowStatusCommand` - `app/Console/Commands/ShadowStatusCommand.php`
- `ShadowValidateConsistencyCommand` - `app/Console/Commands/ShadowValidateConsistencyCommand.php`
- `SimulateDeleteScenarioCommand` - `app/Console/Commands/SimulateDeleteScenarioCommand.php`
- `SppgReportService` - `app/Services/Reports/SppgReportService.php`
- `SpreadsheetFileFormatDetector` - `app/Support/SpreadsheetFileFormatDetector.php`
- `StreamedFileLineCounter` - `app/Support/StreamedFileLineCounter.php`
- `StrictDateParser` - `app/Support/StrictDateParser.php`
- `SyncRkaBreakdownCommand` - `app/Console/Commands/SyncRkaBreakdownCommand.php`
- `TrustedSpreadsheetUrl` - `app/Rules/TrustedSpreadsheetUrl.php`
- `TuneDatabasePerformanceCommand` - `app/Console/Commands/TuneDatabasePerformanceCommand.php`
- `ValidateShadowColumnsCommand` - `app/Console/Commands/ValidateShadowColumnsCommand.php`

## Command Nodes

- `benchmark:analyze-audits` - `app/Console/Commands/AnalyzeDeleteAuditsCommand.php`
- `benchmark:delete-performance` - `app/Console/Commands/BenchmarkDeletePerformanceCommand.php`
- `benchmark:simulate-delete` - `app/Console/Commands/SimulateDeleteScenarioCommand.php`
- `dashboard-sources:refresh` - `app/Console/Commands/RefreshRemoteDashboardSourcesCommand.php`
- `database:performance-tune` - `app/Console/Commands/TuneDatabasePerformanceCommand.php`
- `database:restore-part` - `app/Console/Commands/RestoreDatabasePartCommand.php`
- `db:recover-safe-dump` - `app/Console/Commands/RecoverSafeDumpCommand.php`
- `doc:markdown` - `app/Console/Commands/ConvertDocumentToMarkdownCommand.php`
- `reports:prune-daily-history` - `app/Console/Commands/PruneReportDailyHistoryCommand.php`
- `rka:sync-breakdown` - `app/Console/Commands/SyncRkaBreakdownCommand.php`
- `scheduler:heartbeat` - `app/Console/Commands/SchedulerHeartbeatCommand.php`
- `shadow:status` - `app/Console/Commands/ShadowStatusCommand.php`
- `shadow:validate` - `app/Console/Commands/ValidateShadowColumnsCommand.php`
- `shadow:validate-consistency` - `app/Console/Commands/ShadowValidateConsistencyCommand.php`
- `tinker`

## View Nodes

- `admin.file-management` - `resources/views/admin/file-management.blade.php`
- `admin.link-management` - `resources/views/admin/link-management.blade.php`
- `components.dropdown` - `resources/views/components/dropdown.blade.php`
- `components.dropdown-link` - `resources/views/components/dropdown-link.blade.php`
- `components.input-error` - `resources/views/components/input-error.blade.php`
- `components.input-label` - `resources/views/components/input-label.blade.php`
- `components.input.error`
- `components.input.label`
- `components.nav-link` - `resources/views/components/nav-link.blade.php`
- `components.primary-button` - `resources/views/components/primary-button.blade.php`
- `components.primary.button`
- `components.responsive-nav-link` - `resources/views/components/responsive-nav-link.blade.php`
- `components.text-input` - `resources/views/components/text-input.blade.php`
- `components.text.input`
- `dashboard` - `resources/views/dashboard.blade.php`
- `dashboard.partials.consumer-operations` - `resources/views/dashboard/partials/consumer-operations.blade.php`
- `dashboard.partials.landing-page-recovery` - `resources/views/dashboard/partials/landing-page-recovery.blade.php`
- `dashboard.partials.micro-performance` - `resources/views/dashboard/partials/micro-performance.blade.php`
- `dashboard.partials.pn-mismatch` - `resources/views/dashboard/partials/pn-mismatch.blade.php`
- `dashboard.partials.sme-operations` - `resources/views/dashboard/partials/sme-operations.blade.php`
- `dashboard.partials.tariff-relief` - `resources/views/dashboard/partials/tariff-relief.blade.php`
- `drive.document-preview` - `resources/views/drive/document-preview.blade.php`
- `drive.index` - `resources/views/drive/index.blade.php`
- `drive.office-editor` - `resources/views/drive/office-editor.blade.php`
- `drive.spreadsheet-editor` - `resources/views/drive/spreadsheet-editor.blade.php`
- `errors.503` - `resources/views/errors/503.blade.php`
- `errors.database-unavailable` - `resources/views/errors/database-unavailable.blade.php`
- `input.index` - `resources/views/input/index.blade.php`
- `input.partials.form` - `resources/views/input/partials/form.blade.php`
- `input.partials.hero` - `resources/views/input/partials/hero.blade.php`
- `input.partials.history` - `resources/views/input/partials/history.blade.php`
- `input.partials.preview` - `resources/views/input/partials/preview.blade.php`
- `input.partials.scripts` - `resources/views/input/partials/scripts.blade.php`
- `input.partials.styles` - `resources/views/input/partials/styles.blade.php`
- `report._bri-report-ui` - `resources/views/report/_bri-report-ui.blade.php`
- `report.data-ph` - `resources/views/report/data-ph.blade.php`
- `report.partials.floating-scrollbar` - `resources/views/report/partials/floating-scrollbar.blade.php`
- `report.partials.sticky-table-viewport-script` - `resources/views/report/partials/sticky-table-viewport-script.blade.php`
- `report.partials.sticky-table-viewport-style` - `resources/views/report/partials/sticky-table-viewport-style.blade.php`
- `report.performance-edc` - `resources/views/report/performance-edc.blade.php`
- `report.performance-qlola` - `resources/views/report/performance-qlola.blade.php`
- `report.performance-qris` - `resources/views/report/performance-qris.blade.php`
- `report.program-referral-partner-perusahaan-anak` - `resources/views/report/program-referral-partner-perusahaan-anak.blade.php`
- `report.sppg` - `resources/views/report/sppg.blade.php`

## Table Nodes

- `current_timestamp`
- `dly_kap_resegmentasi`
- `external_report_links`
- `l1133`
- `lw321_npd`
- `lw321_npdd`
- `lw321pn`
- `migrations`
- `nama_report`
- `performance_pis_per_produk`
- `performance_targets`
- `referensi_uker`
- `report_sync_audits`
- `rka`
- `wilayah_mbm`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| import -> core (calls) | 562 |
| import -> core (instantiates) | 271 |
| dashboard-pinjaman -> core (calls) | 250 |
| dashboard-simpanan -> core (calls) | 199 |
| import -> core (accepts) | 189 |
| bank-pipeline -> core (calls) | 169 |
| jobs-snapshots -> core (calls) | 102 |
| dashboard-pinjaman -> core (accepts) | 93 |
| dashboard-harian -> core (calls) | 79 |
| database -> core (instantiates) | 73 |
| bank-pipeline -> core (accepts) | 64 |
| database -> core (checks_table) | 62 |
| prognosa -> core (calls) | 61 |
| database -> core (writes_table) | 60 |
| presentation -> core (calls) | 60 |
| dashboard-simpanan -> core (accepts) | 59 |
| core -> access-control (protected_by) | 58 |
| access-control -> core (calls) | 57 |
| dashboard-pinjaman -> core (instantiates) | 44 |
| bank-pipeline -> core (instantiates) | 39 |
| jobs-snapshots -> core (uses_trait) | 33 |
| almafacts -> core (calls) | 31 |
| database -> core (calls) | 30 |
| platform -> core (calls) | 28 |
| prognosa -> core (accepts) | 28 |
| core -> bank-pipeline (references_route) | 28 |
| access-control -> core (accepts) | 27 |
| input-management -> core (calls) | 25 |
| prognosa -> core (instantiates) | 25 |
| presentation -> core (accepts) | 24 |
