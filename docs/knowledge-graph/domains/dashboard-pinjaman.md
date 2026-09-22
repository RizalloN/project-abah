# Domain: dashboard-pinjaman

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-pinjaman --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 3 |
| file | 100 |
| function | 3 |
| method | 978 |
| route | 35 |
| class | 53 |
| table | 16 |
| view | 37 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `daily_loan_dinamis` | table | 298 | - |
| `DashboardPinjamanReportController` | class | 194 | `app/Http/Controllers/DashboardPinjamanReportController.php:28` |
| `KinerjaRmReportController` | class | 142 | `app/Http/Controllers/Report/KinerjaRmReportController.php:22` |
| `lw325_ph` | table | 121 | - |
| `KinerjaRmMikroReportController` | class | 97 | `app/Http/Controllers/Report/KinerjaRmMikroReportController.php:19` |
| `ssa_pinjaman` | table | 63 | - |
| `brihc_pemasar` | table | 60 | - |
| `KinerjaRmSnapshotPeriodResolutionTest` | class | 46 | `tests/Unit/KinerjaRmSnapshotPeriodResolutionTest.php:19` |
| `DashboardPinjamanKreditService` | class | 40 | `app/Support/DashboardPinjamanKreditService.php:11` |
| `SyncBrihcReferenceCommand` | class | 38 | `app/Console/Commands/SyncBrihcReferenceCommand.php:17` |
| `DashboardPinjamanRecoveryMetricsTest` | class | 36 | `tests/Unit/DashboardPinjamanRecoveryMetricsTest.php:16` |
| `DashboardPinjamanChartPeriodikService` | class | 33 | `app/Support/DashboardPinjamanChartPeriodikService.php:14` |
| `index` | method | 33 | `app/Http/Controllers/Report/KinerjaRmReportController.php:843` |
| `dashboard_pinjaman_snapshots` | table | 32 | - |
| `invokePrivateMethod` | method | 30 | `tests/Unit/KinerjaRmSnapshotPeriodResolutionTest.php:1798` |
| `MicroPipelineSyncService` | class | 29 | `app/Services/Reports/MicroPipelineSyncService.php:16` |
| `KinerjaRmMikroPeriodResolutionTest` | class | 27 | `tests/Unit/KinerjaRmMikroPeriodResolutionTest.php:19` |
| `brihc` | table | 26 | - |
| `LandingLoanAnalyticsService` | class | 25 | `app/Support/LandingLoanAnalyticsService.php:14` |
| `SmallRmRealizationCalculator` | class | 25 | `app/Support/SmallRmRealizationCalculator.php:10` |
| `snapshotRow` | method | 25 | `tests/Unit/KinerjaRmSnapshotPeriodResolutionTest.php:1703` |
| `sync` | method | 24 | `app/Services/Reports/MicroPipelineSyncService.php:52` |
| `fetchBranchRows` | method | 23 | `app/Http/Controllers/Report/KinerjaRmReportController.php:2180` |
| `ConsumerRmPositionHistoryStore` | class | 22 | `app/Support/ConsumerRmPositionHistoryStore.php:12` |
| `DashboardPinjamanKreditServiceTest` | class | 22 | `tests/Unit/DashboardPinjamanKreditServiceTest.php:13` |
| `RunOffReportService` | class | 22 | `app/Services/Reports/RunOffReportService.php:12` |
| `fetchRetailRealizationPerformance` | method | 22 | `app/Http/Controllers/Report/KinerjaRmReportController.php:1742` |
| `reportCacheVersion` | method | 22 | `app/Http/Controllers/DashboardPinjamanReportController.php:4560` |
| `LandingLoanRiskCacheService` | class | 20 | `app/Support/LandingLoanRiskCacheService.php:11` |
| `report.dashboard-pinjaman.kinerjarmmikro` | view | 20 | `resources/views/report/dashboard-pinjaman/kinerjarmmikro.blade.php:1` |

## Route Nodes

- `report.dashboard-pinjaman` - `report/dashboard-pinjaman`
- `report.dashboard-pinjaman.analisa-ug-npl` - `report/dashboard-pinjaman/analisa-ug-npl`
- `report.dashboard-pinjaman.analisa-ug-npl.data` - `report/dashboard-pinjaman/analisa-ug-npl/data`
- `report.dashboard-pinjaman.chart-periodik` - `report/dashboard-pinjaman/chart-periodik`
- `report.dashboard-pinjaman.chart-periodik.data` - `report/dashboard-pinjaman/chart-periodik/data`
- `report.dashboard-pinjaman.chart-periodik.filters` - `report/dashboard-pinjaman/chart-periodik/filters`
- `report.dashboard-pinjaman.data` - `report/dashboard-pinjaman/data`
- `report.dashboard-pinjaman.data-ph` - `report/dashboard-pinjaman/data-ph`
- `report.dashboard-pinjaman.data-ph.nominatif` - `report/dashboard-pinjaman/data-ph/nominatif`
- `report.dashboard-pinjaman.filters` - `report/dashboard-pinjaman/filters`
- `report.dashboard-pinjaman.kinerja-non-ptp` - `report/dashboard-pinjaman/kinerja-non-ptp`
- `report.dashboard-pinjaman.kinerjarm` - `report/dashboard-pinjaman/kinerjarm`
- `report.dashboard-pinjaman.kinerjarm.history` - `report/dashboard-pinjaman/kinerjarm/history`
- `report.dashboard-pinjaman.kinerjarmmikro` - `report/dashboard-pinjaman/kinerjarmmikro`
- `report.dashboard-pinjaman.kolek-tidak-sesuai` - `report/dashboard-pinjaman/kolek-tidak-sesuai`
- `report.dashboard-pinjaman.kolek-tidak-sesuai.data` - `report/dashboard-pinjaman/kolek-tidak-sesuai/data`
- `report.dashboard-pinjaman.kolek-tidak-sesuai.export` - `report/dashboard-pinjaman/kolek-tidak-sesuai/export`
- `report.dashboard-pinjaman.kolek-tidak-sesuai.filters` - `report/dashboard-pinjaman/kolek-tidak-sesuai/filters`
- `report.dashboard-pinjaman.kredit` - `report/dashboard-pinjaman/kredit`
- `report.dashboard-pinjaman.kredit.data` - `report/dashboard-pinjaman/kredit/data`
- `report.dashboard-pinjaman.matrix` - `report/dashboard-pinjaman/matrix-pergeseran-kolek`
- `report.dashboard-pinjaman.matrix.detail` - `report/dashboard-pinjaman/matrix-pergeseran-kolek/detail`
- `report.dashboard-pinjaman.matrix.export` - `report/dashboard-pinjaman/matrix-pergeseran-kolek/export`
- `report.dashboard-pinjaman.realisasi-6-bulan-menunggak` - `report/dashboard-pinjaman/realisasi-6-bulan-menunggak`
- `report.dashboard-pinjaman.realisasi-6-bulan-menunggak.data` - `report/dashboard-pinjaman/realisasi-6-bulan-menunggak/data`
- `report.dashboard-pinjaman.realisasi-6-bulan-menunggak.export` - `report/dashboard-pinjaman/realisasi-6-bulan-menunggak/export`
- `report.dashboard-pinjaman.realisasi-6-bulan-menunggak.filters` - `report/dashboard-pinjaman/realisasi-6-bulan-menunggak/filters`
- `report.dashboard-pinjaman.run-off` - `report/dashboard-pinjaman/run-off`
- `report.dashboard-pinjaman.summary` - `report/dashboard-pinjaman/summary`
- `report.dashboard-pinjaman.tunggakan-kecil` - `report/dashboard-pinjaman/tunggakan-kecil`
- `report.dashboard-pinjaman.tunggakan-kecil.data` - `report/dashboard-pinjaman/tunggakan-kecil/data`
- `report.dashboard-pinjaman.tunggakan-kecil.export` - `report/dashboard-pinjaman/tunggakan-kecil/export`
- `report.dashboard-pinjaman.tunggakan-kecil.filters` - `report/dashboard-pinjaman/tunggakan-kecil/filters`
- `report.data.newpayroll` - `report/data/newpayroll`
- `report.kinerja.newpayroll` - `report/peningkatan-payroll-berkualitas/kinerja-new-payroll`

## Class Nodes

- `ConsumerRmKanwilAudit` - `app/Support/ConsumerRmKanwilAudit.php`
- `ConsumerRmKanwilAuditTest` - `tests/Unit/ConsumerRmKanwilAuditTest.php`
- `ConsumerRmPositionHistoryStore` - `app/Support/ConsumerRmPositionHistoryStore.php`
- `ConsumerRmPositionHistoryStoreTest` - `tests/Unit/ConsumerRmPositionHistoryStoreTest.php`
- `ConsumerRmRealizationCalculator` - `app/Support/ConsumerRmRealizationCalculator.php`
- `DailyLoanManualSegmentRule` - `app/Support/DailyLoanManualSegmentRule.php`
- `DailyLoanManualSegmentRuleTest` - `tests/Unit/DailyLoanManualSegmentRuleTest.php`
- `DashboardPinjamanChartPeriodikService` - `app/Support/DashboardPinjamanChartPeriodikService.php`
- `DashboardPinjamanKreditCacheTest` - `tests/Unit/DashboardPinjamanKreditCacheTest.php`
- `DashboardPinjamanKreditCaptureViewTest` - `tests/Unit/DashboardPinjamanKreditCaptureViewTest.php`
- `DashboardPinjamanKreditService` - `app/Support/DashboardPinjamanKreditService.php`
- `DashboardPinjamanKreditServiceTest` - `tests/Unit/DashboardPinjamanKreditServiceTest.php`
- `DashboardPinjamanMatrixViewTest` - `tests/Unit/DashboardPinjamanMatrixViewTest.php`
- `DashboardPinjamanRecoveryMetricsTest` - `tests/Unit/DashboardPinjamanRecoveryMetricsTest.php`
- `DashboardPinjamanReportController` - `app/Http/Controllers/DashboardPinjamanReportController.php`
- `KinerjaRmFormattingTest` - `tests/Unit/KinerjaRmFormattingTest.php`
- `KinerjaRmMikroPeriodResolutionTest` - `tests/Unit/KinerjaRmMikroPeriodResolutionTest.php`
- `KinerjaRmMikroReportController` - `app/Http/Controllers/Report/KinerjaRmMikroReportController.php`
- `KinerjaRmReportController` - `app/Http/Controllers/Report/KinerjaRmReportController.php`
- `KinerjaRmSnapshotPeriodResolutionTest` - `tests/Unit/KinerjaRmSnapshotPeriodResolutionTest.php`
- `LandingLoanAnalyticsService` - `app/Support/LandingLoanAnalyticsService.php`
- `LandingLoanAnalyticsServiceTest` - `tests/Unit/LandingLoanAnalyticsServiceTest.php`
- `LandingLoanRiskCacheService` - `app/Support/LandingLoanRiskCacheService.php`
- `LandingLoanRiskCacheServiceTest` - `tests/Unit/LandingLoanRiskCacheServiceTest.php`
- `LandingMicroPipelineService` - `app/Support/LandingMicroPipelineService.php`
- `LoanQualityBucketMapper` - `app/Support/LoanQualityBucketMapper.php`
- `LoanQualityBucketMapperSqlParityTest` - `tests/Unit/LoanQualityBucketMapperSqlParityTest.php`
- `LoanQualityBucketMapperTest` - `tests/Unit/LoanQualityBucketMapperTest.php`
- `Lw321DailyLoanMapper` - `app/Support/Lw321DailyLoanMapper.php`
- `Lw321DailyLoanMapperTest` - `tests/Unit/Lw321DailyLoanMapperTest.php`
- `Lw321DailyLoanSyncService` - `app/Support/Lw321DailyLoanSyncService.php`
- `Lw321DailyLoanSyncServiceTest` - `tests/Unit/Lw321DailyLoanSyncServiceTest.php`
- `Lw325IndexOptimizationMigrationTest` - `tests/Unit/Lw325IndexOptimizationMigrationTest.php`
- `Lw325PhPolarsProcessorTest` - `tests/Unit/Lw325PhPolarsProcessorTest.php`
- `MicroPipelineSyncService` - `app/Services/Reports/MicroPipelineSyncService.php`
- `MicroPipelineSyncTest` - `tests/Feature/MicroPipelineSyncTest.php`
- `NewPayrollReportController` - `app/Http/Controllers/Report/NewPayrollReportController.php`
- `NewPayrollReportService` - `app/Services/Reports/NewPayrollReportService.php`
- `NewPayrollReportServiceTest` - `tests/Unit/NewPayrollReportServiceTest.php`
- `RebuildLoanChartPeriodikSnapshotJob` - `app/Jobs/RebuildLoanChartPeriodikSnapshotJob.php`
- `RebuildLoanDashboardSnapshotJob` - `app/Jobs/RebuildLoanDashboardSnapshotJob.php`
- `RewriteSsaPinjamanYtdReferenceCommand` - `app/Console/Commands/RewriteSsaPinjamanYtdReferenceCommand.php`
- `RunOffReportController` - `app/Http/Controllers/Report/RunOffReportController.php`
- `RunOffReportControllerTest` - `tests/Unit/RunOffReportControllerTest.php`
- `RunOffReportService` - `app/Services/Reports/RunOffReportService.php`
- `SmallRmRealizationCalculator` - `app/Support/SmallRmRealizationCalculator.php`
- `SmallRmRealizationCalculatorTest` - `tests/Unit/SmallRmRealizationCalculatorTest.php`
- `SsaPinjamanDailyLoanRewriteService` - `app/Services/SsaPinjamanDailyLoanRewriteService.php`
- `SsaPinjamanDailyLoanRewriteServiceTest` - `tests/Unit/SsaPinjamanDailyLoanRewriteServiceTest.php`
- `SyncBrihcReferenceCommand` - `app/Console/Commands/SyncBrihcReferenceCommand.php`
- `SyncBrihcReferenceCommandTest` - `tests/Feature/SyncBrihcReferenceCommandTest.php`
- `SyncMicroPipelineCommand` - `app/Console/Commands/SyncMicroPipelineCommand.php`
- `WarmLandingLoanRiskCacheJob` - `app/Jobs/WarmLandingLoanRiskCacheJob.php`

## Command Nodes

- `micro-pipeline:sync` - `app/Console/Commands/SyncMicroPipelineCommand.php`
- `reference:sync-brihc` - `app/Console/Commands/SyncBrihcReferenceCommand.php`
- `ssa-pinjaman:rewrite-ytd-reference` - `app/Console/Commands/RewriteSsaPinjamanYtdReferenceCommand.php`

## View Nodes

- `dashboard.partials.loan-quality-timeseries` - `resources/views/dashboard/partials/loan-quality-timeseries.blade.php`
- `report.dashboard-pinjaman` - `resources/views/report/dashboard-pinjaman.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_extreme_low` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_mantri_extreme_low.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_kuadran` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_mantri_kuadran.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_pdwk` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_mantri_pdwk.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_produktivitas` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_mantri_produktivitas.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_rekap` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_mantri_rekap.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_unit_pemutus` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_mantri_unit_pemutus.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._table_per_rm` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_per_rm.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._table_per_uker` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_per_uker.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._table_rekap` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_rekap.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._table_series_bulanan` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_series_bulanan.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._table_series_harian` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_series_harian.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._table_tiering` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_tiering.blade.php`
- `report.dashboard-pinjaman._partials._loading_stub` - `resources/views/report/dashboard-pinjaman/_partials/_loading_stub.blade.php`
- `report.dashboard-pinjaman._partials._scripts_dropdown` - `resources/views/report/dashboard-pinjaman/_partials/_scripts_dropdown.blade.php`
- `report.dashboard-pinjaman._partials._scripts_shared` - `resources/views/report/dashboard-pinjaman/_partials/_scripts_shared.blade.php`
- `report.dashboard-pinjaman._partials._styles` - `resources/views/report/dashboard-pinjaman/_partials/_styles.blade.php`
- `report.dashboard-pinjaman._partials._styles_dropdown` - `resources/views/report/dashboard-pinjaman/_partials/_styles_dropdown.blade.php`
- `report.dashboard-pinjaman.analisa-ug-npl` - `resources/views/report/dashboard-pinjaman/analisa-ug-npl.blade.php`
- `report.dashboard-pinjaman.chart-periodik` - `resources/views/report/dashboard-pinjaman/chart-periodik.blade.php`
- `report.dashboard-pinjaman.kinerja-non-ptp` - `resources/views/report/dashboard-pinjaman/kinerja-non-ptp.blade.php`
- `report.dashboard-pinjaman.kinerjarmmikro` - `resources/views/report/dashboard-pinjaman/kinerjarmmikro.blade.php`
- `report.dashboard-pinjaman.kredit` - `resources/views/report/dashboard-pinjaman/kredit.blade.php`
- `report.dashboard-pinjaman.matrix` - `resources/views/report/dashboard-pinjaman/matrix.blade.php`
- `report.dashboard-pinjaman.mismatch` - `resources/views/report/dashboard-pinjaman/mismatch.blade.php`
- `report.dashboard-pinjaman.realisasi-6-bulan-menunggak` - `resources/views/report/dashboard-pinjaman/realisasi-6-bulan-menunggak.blade.php`
- `report.dashboard-pinjaman.run-off` - `resources/views/report/dashboard-pinjaman/run-off.blade.php`
- `report.dashboard-pinjaman.summary` - `resources/views/report/dashboard-pinjaman/summary.blade.php`
- `report.dashboard-pinjaman.tunggakan-kecil` - `resources/views/report/dashboard-pinjaman/tunggakan-kecil.blade.php`
- `report.kinerja-new-payroll` - `resources/views/report/kinerja-new-payroll.blade.php`
- `report.kinerjarm` - `resources/views/report/kinerjarm.blade.php`
- `report.kinerjarm-detail-modal` - `resources/views/report/kinerjarm-detail-modal.blade.php`
- `report.kinerjarm-performance-table-section` - `resources/views/report/kinerjarm-performance-table-section.blade.php`
- `report.kinerjarm-quality-series-section` - `resources/views/report/kinerjarm-quality-series-section.blade.php`
- `report.kinerjarm-table` - `resources/views/report/kinerjarm-table.blade.php`
- `report.kinerjarm-table-section` - `resources/views/report/kinerjarm-table-section.blade.php`

## Table Nodes

- `brihc`
- `brihc_pemasar`
- `daily_loan_dinamis`
- `dashboard_pinjaman_chart_periodik_snapshots`
- `dashboard_pinjaman_snapshots`
- `loan_quality_bucket_samples`
- `loan_scope_resolution`
- `loan_type`
- `lw325_ph`
- `micro_pipeline_records`
- `micro_pipeline_syncs`
- `performance_kurkecil_mikro`
- `performance_mantri`
- `performance_new_payroll_snapshots`
- `pinjaman`
- `ssa_pinjaman`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| dashboard-pinjaman -> core (calls) | 315 |
| dashboard-pinjaman -> access-control (protected_by) | 175 |
| dashboard-pinjaman -> core (instantiates) | 106 |
| dashboard-pinjaman -> core (accepts) | 89 |
| dashboard-pinjaman -> tests (calls) | 42 |
| tests -> dashboard-pinjaman (writes_table) | 35 |
| dashboard-pinjaman -> jobs-snapshots (writes_table) | 35 |
| tests -> dashboard-pinjaman (contains) | 34 |
| import -> dashboard-pinjaman (writes_table) | 33 |
| dashboard-harian -> dashboard-pinjaman (writes_table) | 28 |
| jobs-snapshots -> dashboard-pinjaman (reads_table) | 27 |
| database -> dashboard-pinjaman (checks_table) | 26 |
| core -> dashboard-pinjaman (reads_table) | 25 |
| dashboard-pinjaman -> access-control (calls) | 25 |
| dashboard-simpanan -> dashboard-pinjaman (reads_table) | 22 |
| dashboard-pinjaman -> tests (extends) | 22 |
| jobs-snapshots -> dashboard-pinjaman (writes_table) | 21 |
| tests -> dashboard-pinjaman (defines_table) | 19 |
| dashboard-pinjaman -> jobs-snapshots (calls) | 18 |
| core -> dashboard-pinjaman (checks_table) | 16 |
| dashboard-pinjaman -> core (writes_table) | 16 |
| database -> dashboard-pinjaman (alters_table) | 15 |
| dashboard-pinjaman -> platform (calls) | 14 |
| dashboard-pinjaman -> platform (extends_view) | 14 |
| dashboard-pinjaman -> dashboard-harian (writes_table) | 13 |
| import -> dashboard-pinjaman (defines_table) | 13 |
| platform -> dashboard-pinjaman (references_route) | 13 |
| import -> dashboard-pinjaman (reads_table) | 12 |
| tests -> dashboard-pinjaman (reads_table) | 12 |
| database -> dashboard-pinjaman (defines_table) | 11 |
