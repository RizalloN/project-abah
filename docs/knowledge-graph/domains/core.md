# Domain: core

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=core --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 23 |
| file | 116 |
| function | 1 |
| method | 659 |
| unresolved_symbol | 381 |
| route | 16 |
| class | 61 |
| table | 23 |
| view | 44 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `nama_report` | table | 181 | - |
| `ReportDataSyncService` | class | 85 | `app/Support/ReportDataSyncService.php:19` |
| `LandingMicroPerformanceService` | class | 79 | `app/Support/LandingMicroPerformanceService.php:16` |
| `rka` | table | 71 | - |
| `normalize` | method | 70 | `app/Support/StrictDateParser.php:29` |
| `ManagedReportManagementService` | class | 66 | `app/Support/ManagedReportManagementService.php:11` |
| `LandingSmeOperationalService` | class | 53 | `app/Support/LandingSmeOperationalService.php:14` |
| `Controller` | class | 52 | `app/Http/Controllers/Controller.php:7` |
| `RkaLookupService` | class | 46 | `app/Support/RkaLookupService.php:11` |
| `apply` | method | 43 | `app/Support/SargableDateFilter.php:9` |
| `DataPhReportController` | class | 40 | `app/Http/Controllers/Report/DataPhReportController.php:15` |
| `KinerjaNonPtpReportController` | class | 39 | `app/Http/Controllers/Report/KinerjaNonPtpReportController.php:17` |
| `FileManagementController` | class | 33 | `app/Http/Controllers/Admin/FileManagementController.php:24` |
| `LandingConsumerOperationalService` | class | 32 | `app/Support/LandingConsumerOperationalService.php:15` |
| `syncImportedTable` | method | 31 | `app/Support/ReportDataSyncService.php:135` |
| `dly_kap_resegmentasi` | table | 27 | - |
| `LinkManagementController` | class | 26 | `app/Http/Controllers/Admin/LinkManagementController.php:17` |
| `index` | method | 26 | `app/Http/Controllers/Report/DataPhReportController.php:23` |
| `analyzeTable` | method | 24 | `app/Support/ReportDataSyncService.php:21` |
| `performance_targets` | table | 24 | - |
| `drive.index` | view | 23 | `resources/views/drive/index.blade.php:1` |
| `ShadowColumnRuleEngine` | class | 22 | `app/Services/Shadow/ShadowColumnRuleEngine.php:8` |
| `SppgReportService` | class | 21 | `app/Services/Reports/SppgReportService.php:15` |
| `index` | method | 21 | `app/Http/Controllers/Report/KinerjaNonPtpReportController.php:52` |
| `performance_pis_per_produk` | table | 21 | - |
| `buildPayload` | method | 20 | `app/Support/LandingMicroPerformanceService.php:479` |
| `PruneReportDailyHistoryCommand` | class | 19 | `app/Console/Commands/PruneReportDailyHistoryCommand.php:19` |
| `fetchPlafondRealizationRows` | method | 19 | `app/Support/LandingMicroPerformanceService.php:1254` |
| `quadrantPayload` | method | 18 | `app/Support/LandingConsumerOperationalService.php:436` |
| `buildMantriPerformance` | method | 17 | `app/Support/LandingMicroPerformanceService.php:1994` |

## Route Nodes

- `file-management.destroy` - `file-management/delete`
- `file-management.download` - `file-management/download`
- `file-management.index` - `file-management`
- `generated::5LjUxzLlRXQhDNdE` - `up`
- `home` - `/`
- `link-management.index` - `link-management`
- `link-management.update` - `link-management`
- `report.data` - `report/data`
- `report.edc` - `report/optimalisasi-digital/edc`
- `report.index`
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
- `DashboardCrossAlignmentGuard` - `app/Support/DashboardCrossAlignmentGuard.php`
- `DataPhReportController` - `app/Http/Controllers/Report/DataPhReportController.php`
- `DigitalPerformanceController` - `app/Http/Controllers/Report/DigitalPerformanceController.php`
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
- `LandingPnMismatchService` - `app/Support/LandingPnMismatchService.php`
- `LandingSmeOperationalService` - `app/Support/LandingSmeOperationalService.php`
- `LinkManagementController` - `app/Http/Controllers/Admin/LinkManagementController.php`
- `MaintainApplicationLogsCommand` - `app/Console/Commands/MaintainApplicationLogsCommand.php`
- `ManagedReportDeleteRecoveryService` - `app/Support/ManagedReportDeleteRecoveryService.php`
- `ManagedReportLoadCoordinator` - `app/Support/ManagedReportLoadCoordinator.php`
- `ManagedReportLoadStore` - `app/Support/ManagedReportLoadStore.php`
- `ManagedReportManagementService` - `app/Support/ManagedReportManagementService.php`
- `ManagedReportRecoveryCoordinator` - `app/Support/ManagedReportRecoveryCoordinator.php`
- `ManagedReportRecoveryStore` - `app/Support/ManagedReportRecoveryStore.php`
- `NamaReport` - `app/Models/NamaReport.php`
- `OptimizationValidator` - `scripts/validate_optimization_impact.php`
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
- `db:recover-safe-dump` - `app/Console/Commands/RecoverSafeDumpCommand.php`
- `key:generate`
- `migrate`
- `migrate:`
- `optimize`
- `optimize:clear`
- `package:discover`
- `reports:prune-daily-history` - `app/Console/Commands/PruneReportDailyHistoryCommand.php`
- `rka:sync-breakdown` - `app/Console/Commands/SyncRkaBreakdownCommand.php`
- `schedule:work`
- `scheduler:heartbeat` - `app/Console/Commands/SchedulerHeartbeatCommand.php`
- `serve`
- `shadow:status` - `app/Console/Commands/ShadowStatusCommand.php`
- `shadow:validate` - `app/Console/Commands/ValidateShadowColumnsCommand.php`
- `shadow:validate-consistency` - `app/Console/Commands/ShadowValidateConsistencyCommand.php`
- `test`
- `tinker`
- `vendor:publish`

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
- `report.index`
- `report.partials.floating-scrollbar` - `resources/views/report/partials/floating-scrollbar.blade.php`
- `report.partials.sticky-table-viewport-script` - `resources/views/report/partials/sticky-table-viewport-script.blade.php`
- `report.partials.sticky-table-viewport-style` - `resources/views/report/partials/sticky-table-viewport-style.blade.php`
- `report.performance-edc` - `resources/views/report/performance-edc.blade.php`
- `report.performance-qlola` - `resources/views/report/performance-qlola.blade.php`
- `report.performance-qris` - `resources/views/report/performance-qris.blade.php`
- `report.program-referral-partner-perusahaan-anak` - `resources/views/report/program-referral-partner-perusahaan-anak.blade.php`
- `report.sppg` - `resources/views/report/sppg.blade.php`

## Table Nodes

- `bulk_load_column_listing_test`
- `bulk_load_php_fallback_test`
- `current_timestamp`
- `dated_rows`
- `dly_kap_resegmentasi`
- `external_report_links`
- `l1133`
- `large_report_fixture`
- `last_page_report_fixture`
- `lw321_npd`
- `lw321_npdd`
- `lw321pn`
- `migrations`
- `nama_report`
- `performance_pis_per_produk`
- `performance_targets`
- `period_scope_resolution`
- `referensi_uker`
- `report_sync_audits`
- `rka`
- `tanggal_scope_resolution`
- `uker_scope_resolution`
- `wilayah_mbm`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| import -> core (calls) | 779 |
| import -> core (instantiates) | 360 |
| dashboard-pinjaman -> core (calls) | 303 |
| dashboard-simpanan -> core (calls) | 220 |
| import -> core (accepts) | 182 |
| bank-pipeline -> core (calls) | 169 |
| jobs-snapshots -> core (calls) | 140 |
| dashboard-pinjaman -> core (instantiates) | 106 |
| prognosa -> core (calls) | 105 |
| dashboard-pinjaman -> core (accepts) | 88 |
| tests -> core (calls) | 79 |
| dashboard-harian -> core (calls) | 75 |
| tests -> core (instantiates) | 74 |
| database -> core (instantiates) | 72 |
| almafacts -> core (calls) | 69 |
| bank-pipeline -> core (instantiates) | 64 |
| bank-pipeline -> core (accepts) | 64 |
| dashboard-harian -> core (instantiates) | 62 |
| database -> core (checks_table) | 61 |
| access-control -> core (calls) | 60 |
| presentation -> core (calls) | 60 |
| dashboard-simpanan -> core (accepts) | 59 |
| database -> core (writes_table) | 58 |
| core -> access-control (protected_by) | 58 |
| database -> core (calls) | 56 |
| dashboard-simpanan -> core (instantiates) | 53 |
| prognosa -> core (instantiates) | 32 |
| tests -> core (writes_table) | 32 |
| jobs-snapshots -> core (uses_trait) | 31 |
| prognosa -> core (accepts) | 28 |
