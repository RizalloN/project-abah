# Domain: core

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=core --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 28 |
| file | 162 |
| method | 850 |
| unresolved_symbol | 399 |
| route | 25 |
| class | 90 |
| trait | 1 |
| table | 48 |
| view | 65 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `nama_report` | table | 181 | - |
| `ReportDataSyncService` | class | 81 | `app/Support/ReportDataSyncService.php:19` |
| `RasioCasaDebiturController` | class | 71 | `app/Http/Controllers/RasioCasaDebiturController.php:20` |
| `jobs` | table | 71 | - |
| `rka` | table | 68 | - |
| `LandingMicroPerformanceService` | class | 66 | `app/Support/LandingMicroPerformanceService.php:16` |
| `ManagedReportManagementService` | class | 66 | `app/Support/ManagedReportManagementService.php:11` |
| `layouts.admin` | view | 65 | `resources/views/layouts/admin.blade.php:1` |
| `normalize` | method | 65 | `app/Support/StrictDateParser.php:29` |
| `Controller` | class | 52 | `app/Http/Controllers/Controller.php:7` |
| `layouts.sidebar` | view | 52 | `resources/views/layouts/sidebar.blade.php:1` |
| `LandingSmeOperationalService` | class | 51 | `app/Support/LandingSmeOperationalService.php:14` |
| `RkaLookupService` | class | 46 | `app/Support/RkaLookupService.php:11` |
| `apply` | method | 43 | `app/Support/SargableDateFilter.php:9` |
| `KinerjaNonPtpReportController` | class | 39 | `app/Http/Controllers/Report/KinerjaNonPtpReportController.php:17` |
| `User` | class | 39 | `app/Models/User.php:10` |
| `DataPhReportController` | class | 38 | `app/Http/Controllers/Report/DataPhReportController.php:15` |
| `RekeningDormantController` | class | 35 | `app/Http/Controllers/RekeningDormantController.php:19` |
| `FileManagementController` | class | 33 | `app/Http/Controllers/Admin/FileManagementController.php:24` |
| `SyncBrihcReferenceCommand` | class | 33 | `app/Console/Commands/SyncBrihcReferenceCommand.php:16` |
| `gi405_recovery` | table | 33 | - |
| `syncImportedTable` | method | 30 | `app/Support/ReportDataSyncService.php:134` |
| `users` | table | 29 | - |
| `cognos_recovery` | table | 28 | - |
| `brihc` | table | 25 | - |
| `index` | method | 25 | `app/Http/Controllers/Report/DataPhReportController.php:23` |
| `analyzeTable` | method | 24 | `app/Support/ReportDataSyncService.php:21` |
| `dly_kap_resegmentasi` | table | 23 | - |
| `drive.index` | view | 23 | `resources/views/drive/index.blade.php:1` |
| `LandingLoanAnalyticsService` | class | 22 | `app/Support/LandingLoanAnalyticsService.php:14` |

## Route Nodes

- `file-management.destroy` - `file-management/delete`
- `file-management.download` - `file-management/download`
- `file-management.index` - `file-management`
- `generated::cRBCRPMLp0tYCLls` - `up`
- `home` - `/`
- `link-management.index` - `link-management`
- `link-management.update` - `link-management`
- `report.data` - `report/data`
- `report.data.newpayroll` - `report/data/newpayroll`
- `report.data.rasiocasa` - `report/data/rasiocasa`
- `report.data.rasiocasa-per-rm` - `report/data/rasiocasa-per-rm`
- `report.data.rekening-dormant` - `report/data/rekening-dormant`
- `report.edc` - `report/optimalisasi-digital/edc`
- `report.index`
- `report.kinerja.newpayroll` - `report/peningkatan-payroll-berkualitas/kinerja-new-payroll`
- `report.kolaborasi.referral` - `report/kolaborasi-perusahaan-anak/program-referral-partner-perusahaan-anak`
- `report.qlola` - `report/optimalisasi-digital/qlola`
- `report.qris` - `report/optimalisasi-digital/qris`
- `report.qris.ukers` - `report/data/qris/ukers`
- `report.rasiocasa.debitur` - `report/rekening-transaksi-debitur`
- `report.rasiocasa.filters-per-rm` - `report/rekening-transaksi-debitur/filters-per-rm`
- `report.rekening-dormant` - `report/rekening-transaksi-debitur/rekening-dormant`
- `report.rekening-dormant.filters` - `report/rekening-transaksi-debitur/rekening-dormant/filters`
- `sheet`
- `token`

## Class Nodes

- `AnalyzeDeleteAuditsCommand` - `app/Console/Commands/AnalyzeDeleteAuditsCommand.php`
- `AppLayout` - `app/View/Components/AppLayout.php`
- `BatchDuplicateValidationService` - `app/Support/BatchDuplicateValidationService.php`
- `BenchmarkDeletePerformanceCommand` - `app/Console/Commands/BenchmarkDeletePerformanceCommand.php`
- `BodBoc` - `app/Models/BodBoc.php`
- `BreakdownRkaSyncService` - `app/Services/Rka/BreakdownRkaSyncService.php`
- `CacheMaintenanceService` - `app/Services/CacheMaintenanceService.php`
- `Controller` - `app/Http/Controllers/Controller.php`
- `DashboardCrossAlignmentGuard` - `app/Support/DashboardCrossAlignmentGuard.php`
- `DataPhReportController` - `app/Http/Controllers/Report/DataPhReportController.php`
- `DigitalPerformanceController` - `app/Http/Controllers/Report/DigitalPerformanceController.php`
- `EdcReportService` - `app/Services/Reports/EdcReportService.php`
- `FileManagementController` - `app/Http/Controllers/Admin/FileManagementController.php`
- `FileManagementDownloadController` - `app/Http/Controllers/Admin/FileManagementDownloadController.php`
- `GuestLayout` - `app/View/Components/GuestLayout.php`
- `InputRekanan` - `app/Models/InputRekanan.php`
- `JobHealthService` - `app/Services/JobHealthService.php`
- `Kernel` - `app/Console/Kernel.php`
- `KinerjaNonPtpReportController` - `app/Http/Controllers/Report/KinerjaNonPtpReportController.php`
- `KolaborasiReportController` - `app/Http/Controllers/Report/KolaborasiReportController.php`
- `KolaborasiReportService` - `app/Services/Reports/KolaborasiReportService.php`
- `LandingLoanAnalyticsService` - `app/Support/LandingLoanAnalyticsService.php`
- `LandingMicroPerformanceService` - `app/Support/LandingMicroPerformanceService.php`
- `LandingSmeOperationalService` - `app/Support/LandingSmeOperationalService.php`
- `LinkManagementController` - `app/Http/Controllers/Admin/LinkManagementController.php`
- `LoanQualityBucketMapper` - `app/Support/LoanQualityBucketMapper.php`
- `LogMaintenanceService` - `app/Services/LogMaintenanceService.php`
- `MaintainApplicationCacheCommand` - `app/Console/Commands/MaintainApplicationCacheCommand.php`
- `MaintainApplicationLogsCommand` - `app/Console/Commands/MaintainApplicationLogsCommand.php`
- `ManagedReportDeleteRecoveryService` - `app/Support/ManagedReportDeleteRecoveryService.php`
- `ManagedReportLoadCoordinator` - `app/Support/ManagedReportLoadCoordinator.php`
- `ManagedReportLoadStore` - `app/Support/ManagedReportLoadStore.php`
- `ManagedReportManagementService` - `app/Support/ManagedReportManagementService.php`
- `ManagedReportRecoveryCoordinator` - `app/Support/ManagedReportRecoveryCoordinator.php`
- `ManagedReportRecoveryStore` - `app/Support/ManagedReportRecoveryStore.php`
- `NamaReport` - `app/Models/NamaReport.php`
- `NewPayrollReportController` - `app/Http/Controllers/Report/NewPayrollReportController.php`
- `NewPayrollReportService` - `app/Services/Reports/NewPayrollReportService.php`
- `OptimizationValidator` - `scripts/validate_optimization_impact.php`
- `OptimizedBulkDeleteService` - `app/Support/OptimizedBulkDeleteService.php`
- `OptimizedDuplicateCleanupService` - `app/Support/OptimizedDuplicateCleanupService.php`
- `OptimizedRkaLookupService` - `app/Support/OptimizedRkaLookupService.php`
- `PartitionMaintenanceService` - `app/Support/PartitionMaintenanceService.php`
- `PruneReportDailyHistoryCommand` - `app/Console/Commands/PruneReportDailyHistoryCommand.php`
- `PublicAccessHealthService` - `app/Services/Network/PublicAccessHealthService.php`
- `QlolaReportService` - `app/Services/Reports/QlolaReportService.php`
- `QrisReportService` - `app/Services/Reports/QrisReportService.php`
- `RasioCasaDebiturController` - `app/Http/Controllers/RasioCasaDebiturController.php`
- `RebuildChartPeriodikPeriodJob` - `app/Jobs/RebuildChartPeriodikPeriodJob.php`
- `RebuildDashboardPeriodJob` - `app/Jobs/RebuildDashboardPeriodJob.php`
- `RebuildDormantPeriodJob` - `app/Jobs/RebuildDormantPeriodJob.php`
- `RebuildHarianPeriodJob` - `app/Jobs/RebuildHarianPeriodJob.php`
- `RebuildPerformanceRmCommand` - `app/Console/Commands/RebuildPerformanceRmCommand.php`
- `RebuildRasioPeriodJob` - `app/Jobs/RebuildRasioPeriodJob.php`
- `RecoverSafeDumpCommand` - `app/Console/Commands/RecoverSafeDumpCommand.php`
- `RefreshRemoteDashboardSourcesCommand` - `app/Console/Commands/RefreshRemoteDashboardSourcesCommand.php`
- `RefreshRemoteDashboardSourcesJob` - `app/Jobs/RefreshRemoteDashboardSourcesJob.php`
- `RekeningDormantController` - `app/Http/Controllers/RekeningDormantController.php`
- `ReportCacheVersion` - `app/Support/ReportCacheVersion.php`
- `ReportDataSyncService` - `app/Support/ReportDataSyncService.php`
- `ReportFilterService` - `app/Services/Reports/ReportFilterService.php`
- `ReportIndexHintResolver` - `app/Support/ReportIndexHintResolver.php`
- `RewriteSsaPinjamanYtdReferenceCommand` - `app/Console/Commands/RewriteSsaPinjamanYtdReferenceCommand.php`
- `RkaLookupService` - `app/Support/RkaLookupService.php`
- `RunLoadDataJob` - `app/Jobs/RunLoadDataJob.php`
- `RunManagedReportDeleteJob` - `app/Jobs/RunManagedReportDeleteJob.php`
- `RunManagedReportLoadJob` - `app/Jobs/RunManagedReportLoadJob.php`
- `RunManagedReportRecoveryJob` - `app/Jobs/RunManagedReportRecoveryJob.php`
- `RunOffReportController` - `app/Http/Controllers/Report/RunOffReportController.php`
- `RunOffReportService` - `app/Services/Reports/RunOffReportService.php`
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

## Trait Nodes

- `IdReusable` - `app/Traits/IdReusable.php`

## Command Nodes

- `benchmark:analyze-audits` - `app/Console/Commands/AnalyzeDeleteAuditsCommand.php`
- `benchmark:delete-performance` - `app/Console/Commands/BenchmarkDeletePerformanceCommand.php`
- `benchmark:simulate-delete` - `app/Console/Commands/SimulateDeleteScenarioCommand.php`
- `cache:maintenance` - `app/Console/Commands/MaintainApplicationCacheCommand.php`
- `config:clear`
- `dashboard-sources:refresh` - `app/Console/Commands/RefreshRemoteDashboardSourcesCommand.php`
- `database:performance-tune` - `app/Console/Commands/TuneDatabasePerformanceCommand.php`
- `db:recover-safe-dump` - `app/Console/Commands/RecoverSafeDumpCommand.php`
- `key:generate`
- `logs:maintenance` - `app/Console/Commands/MaintainApplicationLogsCommand.php`
- `migrate`
- `migrate:`
- `optimize`
- `optimize:clear`
- `package:discover`
- `reference:sync-brihc` - `app/Console/Commands/SyncBrihcReferenceCommand.php`
- `report:warm-cache` - `app/Console/Commands/WarmDashboardCache.php`
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
- `components.application-logo` - `resources/views/components/application-logo.blade.php`
- `components.danger-button` - `resources/views/components/danger-button.blade.php`
- `components.dropdown` - `resources/views/components/dropdown.blade.php`
- `components.dropdown-link` - `resources/views/components/dropdown-link.blade.php`
- `components.dropdown.link`
- `components.guest.layout`
- `components.input-error` - `resources/views/components/input-error.blade.php`
- `components.input-label` - `resources/views/components/input-label.blade.php`
- `components.input.error`
- `components.input.label`
- `components.modal` - `resources/views/components/modal.blade.php`
- `components.nav-link` - `resources/views/components/nav-link.blade.php`
- `components.nav.link`
- `components.primary-button` - `resources/views/components/primary-button.blade.php`
- `components.primary.button`
- `components.responsive-nav-link` - `resources/views/components/responsive-nav-link.blade.php`
- `components.responsive.nav.link`
- `components.secondary-button` - `resources/views/components/secondary-button.blade.php`
- `components.slot`
- `components.text-input` - `resources/views/components/text-input.blade.php`
- `components.text.input`
- `dashboard` - `resources/views/dashboard.blade.php`
- `dashboard.partials.loan-quality-timeseries` - `resources/views/dashboard/partials/loan-quality-timeseries.blade.php`
- `dashboard.partials.micro-performance` - `resources/views/dashboard/partials/micro-performance.blade.php`
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
- `layouts.admin` - `resources/views/layouts/admin.blade.php`
- `layouts.app` - `resources/views/layouts/app.blade.php`
- `layouts.guest` - `resources/views/layouts/guest.blade.php`
- `layouts.navigation` - `resources/views/layouts/navigation.blade.php`
- `layouts.sidebar` - `resources/views/layouts/sidebar.blade.php`
- `report.Rasiocasadebitur` - `resources/views/report/Rasiocasadebitur.blade.php`
- `report._bri-report-ui` - `resources/views/report/_bri-report-ui.blade.php`
- `report.data-ph` - `resources/views/report/data-ph.blade.php`
- `report.index`
- `report.kinerja-new-payroll` - `resources/views/report/kinerja-new-payroll.blade.php`
- `report.partials.floating-scrollbar` - `resources/views/report/partials/floating-scrollbar.blade.php`
- `report.partials.kejar-laba-metrics` - `resources/views/report/partials/kejar-laba-metrics.blade.php`
- `report.partials.rasio-casa-unit-table` - `resources/views/report/partials/rasio-casa-unit-table.blade.php`
- `report.partials.sticky-table-viewport-script` - `resources/views/report/partials/sticky-table-viewport-script.blade.php`
- `report.partials.sticky-table-viewport-style` - `resources/views/report/partials/sticky-table-viewport-style.blade.php`
- `report.performance-edc` - `resources/views/report/performance-edc.blade.php`
- `report.performance-qlola` - `resources/views/report/performance-qlola.blade.php`
- `report.performance-qris` - `resources/views/report/performance-qris.blade.php`
- `report.program-referral-partner-perusahaan-anak` - `resources/views/report/program-referral-partner-perusahaan-anak.blade.php`
- `report.rekening-dormant` - `resources/views/report/rekening-dormant.blade.php`
- `report.rekening-new-payroll` - `resources/views/report/rekening-new-payroll.blade.php`
- `report.saldo-new-payroll` - `resources/views/report/saldo-new-payroll.blade.php`
- `report.sppg` - `resources/views/report/sppg.blade.php`
- `welcome` - `resources/views/welcome.blade.php`

## Table Nodes

- `brihc`
- `brihc_pemasar`
- `bulk_load_column_listing_test`
- `bulk_load_php_fallback_test`
- `cache`
- `cache_locks`
- `cognos_ph`
- `cognos_recovery`
- `current_timestamp`
- `dated_rows`
- `dly_kap_resegmentasi`
- `external_report_links`
- `failed_jobs`
- `gi405_rec_dh`
- `gi405_recovery`
- `gi405_singlerow`
- `ibbisniz_corp`
- `input_rekanan`
- `job_batches`
- `jobs`
- `l1133`
- `large_report_fixture`
- `last_page_report_fixture`
- `loan_quality_bucket_samples`
- `loan_scope_resolution`
- `loan_type`
- `lw321_npd`
- `lw321_npdd`
- `lw321pn`
- `migrations`
- `nama_report`
- `password_reset_tokens`
- `performance_kurkecil_mikro`
- `performance_mantri`
- `performance_pis_per_produk`
- `performance_targets`
- `period_scope_resolution`
- `pinjaman`
- `referensi_uker`
- `report_sync_audits`
- `rka`
- `sessions`
- `tanggal_scope_resolution`
- `uker_scope_resolution`
- `usak_ibbiz_uker`
- `user_audit_log`
- `users`
- `wilayah_mbm`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| import -> core (calls) | 771 |
| import -> core (instantiates) | 353 |
| dashboard-pinjaman -> core (calls) | 193 |
| import -> core (accepts) | 179 |
| bank-pipeline -> core (calls) | 171 |
| dashboard-simpanan -> core (calls) | 157 |
| jobs-snapshots -> core (calls) | 142 |
| core -> access-control (protected_by) | 103 |
| tests -> core (instantiates) | 95 |
| prognosa -> core (calls) | 89 |
| database -> core (checks_table) | 87 |
| dashboard-harian -> core (calls) | 74 |
| tests -> core (calls) | 74 |
| database -> core (instantiates) | 68 |
| almafacts -> core (calls) | 67 |
| access-control -> core (calls) | 66 |
| bank-pipeline -> core (accepts) | 66 |
| bank-pipeline -> core (instantiates) | 65 |
| presentation -> core (calls) | 61 |
| database -> core (writes_table) | 60 |
| dashboard-pinjaman -> core (accepts) | 59 |
| dashboard-harian -> core (instantiates) | 54 |
| dashboard-pinjaman -> core (instantiates) | 54 |
| import -> core (reads_table) | 49 |
| dashboard-simpanan -> core (accepts) | 47 |
| import -> core (writes_table) | 46 |
| database -> core (calls) | 46 |
| dashboard-simpanan -> core (instantiates) | 39 |
| tests -> core (writes_table) | 39 |
| core -> jobs-snapshots (uses_trait) | 38 |
