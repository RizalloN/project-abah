# Domain: dashboard-pinjaman

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-pinjaman --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 3 |
| file | 79 |
| method | 806 |
| route | 37 |
| class | 34 |
| table | 14 |
| view | 45 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DashboardPinjamanReportController` | class | 161 | `app/Http/Controllers/DashboardPinjamanReportController.php:28` |
| `KinerjaRmReportController` | class | 105 | `app/Http/Controllers/Report/KinerjaRmReportController.php:24` |
| `daily_loan_dinamis` | table | 99 | - |
| `KinerjaRmMikroReportController` | class | 87 | `app/Http/Controllers/Report/KinerjaRmMikroReportController.php:19` |
| `SyncBrihcReferenceCommand` | class | 52 | `app/Console/Commands/SyncBrihcReferenceCommand.php:17` |
| `lw325_ph` | table | 51 | - |
| `DashboardPinjamanKreditService` | class | 40 | `app/Support/DashboardPinjamanKreditService.php:11` |
| `Lw321DailyLoanSyncService` | class | 34 | `app/Support/Lw321DailyLoanSyncService.php:9` |
| `index` | method | 34 | `app/Http/Controllers/Report/KinerjaRmReportController.php:863` |
| `DashboardPinjamanChartPeriodikService` | class | 33 | `app/Support/DashboardPinjamanChartPeriodikService.php:14` |
| `LandingLoanAnalyticsService` | class | 33 | `app/Support/LandingLoanAnalyticsService.php:14` |
| `MantriIncentiveService` | class | 32 | `app/Services/Reports/MantriIncentiveService.php:29` |
| `brihc_pemasar` | table | 31 | - |
| `MicroPipelineSyncService` | class | 29 | `app/Services/Reports/MicroPipelineSyncService.php:16` |
| `ConsumerRmPositionHistoryStore` | class | 26 | `app/Support/ConsumerRmPositionHistoryStore.php:12` |
| `Lw321DailyLoanMapper` | class | 26 | `app/Support/Lw321DailyLoanMapper.php:7` |
| `RunOffReportService` | class | 24 | `app/Services/Reports/RunOffReportService.php:12` |
| `sync` | method | 24 | `app/Services/Reports/MicroPipelineSyncService.php:52` |
| `fetchBranchRows` | method | 23 | `app/Http/Controllers/Report/KinerjaRmReportController.php:2333` |
| `fetchRetailRealizationPerformance` | method | 23 | `app/Http/Controllers/Report/KinerjaRmReportController.php:1792` |
| `handle` | method | 22 | `app/Console/Commands/SyncBrihcReferenceCommand.php:75` |
| `reportCacheVersion` | method | 22 | `app/Http/Controllers/DashboardPinjamanReportController.php:4587` |
| `report.dashboard-pinjaman.kinerjarmmikro` | view | 21 | `resources/views/report/dashboard-pinjaman/kinerjarmmikro.blade.php:1` |
| `LandingLoanRiskCacheService` | class | 20 | `app/Support/LandingLoanRiskCacheService.php:11` |
| `nettWorkbookPayload` | method | 20 | `app/Http/Controllers/Report/KinerjaRmMikroReportController.php:206` |
| `ssa_pinjaman` | table | 19 | - |
| `SmallRmRealizationCalculator` | class | 18 | `app/Support/SmallRmRealizationCalculator.php:10` |
| `buildChartPayload` | method | 18 | `app/Support/DashboardPinjamanChartPeriodikService.php:69` |
| `readSourceRows` | method | 18 | `app/Console/Commands/SyncBrihcReferenceCommand.php:224` |
| `calculate` | method | 17 | `app/Support/SmallRmRealizationCalculator.php:40` |

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
- `report.kpi.insentif-mantri` - `report/kpi/insentif-mantri`
- `report.kpi.insentif-mantri.export` - `report/kpi/insentif-mantri/nominatif`

## Class Nodes

- `CaptureConsumerRmPositionHistoryJob` - `app/Jobs/CaptureConsumerRmPositionHistoryJob.php`
- `ConsumerRmKanwilAudit` - `app/Support/ConsumerRmKanwilAudit.php`
- `ConsumerRmPositionHistoryStore` - `app/Support/ConsumerRmPositionHistoryStore.php`
- `ConsumerRmRealizationCalculator` - `app/Support/ConsumerRmRealizationCalculator.php`
- `DailyLoanManualSegmentRule` - `app/Support/DailyLoanManualSegmentRule.php`
- `DashboardPinjamanChartPeriodikService` - `app/Support/DashboardPinjamanChartPeriodikService.php`
- `DashboardPinjamanKreditService` - `app/Support/DashboardPinjamanKreditService.php`
- `DashboardPinjamanReportController` - `app/Http/Controllers/DashboardPinjamanReportController.php`
- `InsentifMantriReportController` - `app/Http/Controllers/Report/InsentifMantriReportController.php`
- `KinerjaRmMikroReportController` - `app/Http/Controllers/Report/KinerjaRmMikroReportController.php`
- `KinerjaRmReportController` - `app/Http/Controllers/Report/KinerjaRmReportController.php`
- `LandingLoanAnalyticsService` - `app/Support/LandingLoanAnalyticsService.php`
- `LandingLoanRiskCacheService` - `app/Support/LandingLoanRiskCacheService.php`
- `LandingMicroPipelineService` - `app/Support/LandingMicroPipelineService.php`
- `LoanQualityBucketMapper` - `app/Support/LoanQualityBucketMapper.php`
- `Lw321DailyLoanMapper` - `app/Support/Lw321DailyLoanMapper.php`
- `Lw321DailyLoanSyncService` - `app/Support/Lw321DailyLoanSyncService.php`
- `MantriIncentiveService` - `app/Services/Reports/MantriIncentiveService.php`
- `MantriPerformanceBasis` - `app/Support/MantriPerformanceBasis.php`
- `MantriProductivityReference` - `app/Support/MantriProductivityReference.php`
- `MicroPipelineSyncService` - `app/Services/Reports/MicroPipelineSyncService.php`
- `NewPayrollReportController` - `app/Http/Controllers/Report/NewPayrollReportController.php`
- `NewPayrollReportService` - `app/Services/Reports/NewPayrollReportService.php`
- `RebuildLoanChartPeriodikSnapshotJob` - `app/Jobs/RebuildLoanChartPeriodikSnapshotJob.php`
- `RebuildLoanDashboardSnapshotJob` - `app/Jobs/RebuildLoanDashboardSnapshotJob.php`
- `RewriteSsaPinjamanYtdReferenceCommand` - `app/Console/Commands/RewriteSsaPinjamanYtdReferenceCommand.php`
- `RunOffReportController` - `app/Http/Controllers/Report/RunOffReportController.php`
- `RunOffReportService` - `app/Services/Reports/RunOffReportService.php`
- `SmallRmRealizationCalculator` - `app/Support/SmallRmRealizationCalculator.php`
- `SsaPinjamanDailyLoanRewriteService` - `app/Services/SsaPinjamanDailyLoanRewriteService.php`
- `SyncBrihcReferenceCommand` - `app/Console/Commands/SyncBrihcReferenceCommand.php`
- `SyncMicroPipelineCommand` - `app/Console/Commands/SyncMicroPipelineCommand.php`
- `WarmKinerjaRmRitelCacheJob` - `app/Jobs/WarmKinerjaRmRitelCacheJob.php`
- `WarmLandingLoanRiskCacheJob` - `app/Jobs/WarmLandingLoanRiskCacheJob.php`

## Command Nodes

- `micro-pipeline:sync` - `app/Console/Commands/SyncMicroPipelineCommand.php`
- `reference:sync-brihc` - `app/Console/Commands/SyncBrihcReferenceCommand.php`
- `ssa-pinjaman:rewrite-ytd-reference` - `app/Console/Commands/RewriteSsaPinjamanYtdReferenceCommand.php`

## View Nodes

- `dashboard.partials.loan-quality-timeseries` - `resources/views/dashboard/partials/loan-quality-timeseries.blade.php`
- `report.dashboard-pinjaman` - `resources/views/report/dashboard-pinjaman.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials._nett_workbook` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_nett_workbook.blade.php`
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
- `report.dashboard-pinjaman._kinerjarmmikro_partials.micro-mantri-nett-sheets` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/micro-mantri-nett-sheets.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials.micro-mantri-nett-values` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/micro-mantri-nett-values.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials.micro-nett-drilldown` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/micro-nett-drilldown.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials.micro-nett-styles` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/micro-nett-styles.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials.micro-rm-nett-sheets` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/micro-rm-nett-sheets.blade.php`
- `report.dashboard-pinjaman._kinerjarmmikro_partials.micro-rm-nett-values` - `resources/views/report/dashboard-pinjaman/_kinerjarmmikro_partials/micro-rm-nett-values.blade.php`
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
- `report.kpi.insentif-mantri` - `resources/views/report/kpi/insentif-mantri.blade.php`

## Table Nodes

- `brihc`
- `brihc_pemasar`
- `daily_loan_dinamis`
- `dashboard_pinjaman_chart_periodik_snapshots`
- `dashboard_pinjaman_snapshots`
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
| dashboard-pinjaman -> core (calls) | 268 |
| dashboard-pinjaman -> access-control (protected_by) | 222 |
| dashboard-pinjaman -> core (accepts) | 102 |
| dashboard-pinjaman -> core (instantiates) | 47 |
| database -> dashboard-pinjaman (checks_table) | 26 |
| core -> dashboard-pinjaman (reads_table) | 23 |
| dashboard-pinjaman -> access-control (calls) | 22 |
| dashboard-simpanan -> dashboard-pinjaman (reads_table) | 22 |
| jobs-snapshots -> dashboard-pinjaman (reads_table) | 18 |
| dashboard-pinjaman -> jobs-snapshots (uses_trait) | 17 |
| core -> dashboard-pinjaman (checks_table) | 16 |
| database -> dashboard-pinjaman (alters_table) | 15 |
| dashboard-pinjaman -> platform (calls) | 15 |
| dashboard-pinjaman -> platform (extends_view) | 15 |
| platform -> dashboard-pinjaman (references_route) | 14 |
| database -> dashboard-pinjaman (defines_table) | 11 |
| dashboard-simpanan -> dashboard-pinjaman (checks_table) | 11 |
| dashboard-pinjaman -> core (reads_table) | 10 |
| dashboard-pinjaman -> core (extends) | 9 |
| dashboard-pinjaman -> jobs-snapshots (calls) | 8 |
| dashboard-pinjaman -> jobs-snapshots (uses_queue) | 8 |
| dashboard-pinjaman -> jobs-snapshots (implements) | 8 |
| dashboard-pinjaman -> core (checks_table) | 7 |
| dashboard-pinjaman -> core (uses_trait) | 7 |
| jobs-snapshots -> dashboard-pinjaman (calls) | 6 |
| dashboard-pinjaman -> jobs-snapshots (instantiates) | 6 |
| database -> dashboard-pinjaman (uses_table) | 5 |
| dashboard-harian -> dashboard-pinjaman (reads_table) | 5 |
| jobs-snapshots -> dashboard-pinjaman (checks_table) | 4 |
| dashboard-simpanan -> dashboard-pinjaman (references_route) | 4 |
