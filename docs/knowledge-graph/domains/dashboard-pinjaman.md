# Domain: dashboard-pinjaman

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-pinjaman --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 1 |
| file | 67 |
| function | 3 |
| method | 639 |
| route | 33 |
| class | 22 |
| table | 5 |
| view | 35 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `daily_loan_dinamis` | table | 276 | - |
| `DashboardPinjamanReportController` | class | 191 | `app/Http/Controllers/DashboardPinjamanReportController.php:28` |
| `KinerjaRmReportController` | class | 134 | `app/Http/Controllers/Report/KinerjaRmReportController.php:22` |
| `lw325_ph` | table | 121 | - |
| `KinerjaRmMikroReportController` | class | 95 | `app/Http/Controllers/Report/KinerjaRmMikroReportController.php:18` |
| `ssa_pinjaman` | table | 62 | - |
| `KinerjaRmSnapshotPeriodResolutionTest` | class | 42 | `tests/Unit/KinerjaRmSnapshotPeriodResolutionTest.php:19` |
| `DashboardPinjamanKreditService` | class | 40 | `app/Support/DashboardPinjamanKreditService.php:11` |
| `DashboardPinjamanRecoveryMetricsTest` | class | 36 | `tests/Unit/DashboardPinjamanRecoveryMetricsTest.php:16` |
| `DashboardPinjamanChartPeriodikService` | class | 33 | `app/Support/DashboardPinjamanChartPeriodikService.php:14` |
| `index` | method | 33 | `app/Http/Controllers/Report/KinerjaRmReportController.php:732` |
| `dashboard_pinjaman_snapshots` | table | 32 | - |
| `KinerjaRmMikroPeriodResolutionTest` | class | 27 | `tests/Unit/KinerjaRmMikroPeriodResolutionTest.php:19` |
| `invokePrivateMethod` | method | 27 | `tests/Unit/KinerjaRmSnapshotPeriodResolutionTest.php:1516` |
| `snapshotRow` | method | 24 | `tests/Unit/KinerjaRmSnapshotPeriodResolutionTest.php:1421` |
| `DashboardPinjamanKreditServiceTest` | class | 22 | `tests/Unit/DashboardPinjamanKreditServiceTest.php:13` |
| `fetchBranchRows` | method | 22 | `app/Http/Controllers/Report/KinerjaRmReportController.php:2066` |
| `reportCacheVersion` | method | 22 | `app/Http/Controllers/DashboardPinjamanReportController.php:4369` |
| `fetchRetailRealizationPerformance` | method | 21 | `app/Http/Controllers/Report/KinerjaRmReportController.php:1629` |
| `report.dashboard-pinjaman.kinerjarmmikro` | view | 20 | `resources/views/report/dashboard-pinjaman/kinerjarmmikro.blade.php:1` |
| `Lw321DailyLoanSyncService` | class | 19 | `app/Support/Lw321DailyLoanSyncService.php:9` |
| `invokePrivateMethod` | method | 19 | `tests/Unit/KinerjaRmMikroPeriodResolutionTest.php:1068` |
| `KinerjaRmFormattingTest` | class | 18 | `tests/Unit/KinerjaRmFormattingTest.php:11` |
| `buildChartPayload` | method | 18 | `app/Support/DashboardPinjamanChartPeriodikService.php:69` |
| `Lw321DailyLoanMapper` | class | 17 | `app/Support/Lw321DailyLoanMapper.php:7` |
| `index` | method | 17 | `app/Http/Controllers/Report/KinerjaRmMikroReportController.php:77` |
| `ugNplData` | method | 17 | `app/Http/Controllers/DashboardPinjamanReportController.php:430` |
| `ugNplIndex` | method | 17 | `app/Http/Controllers/DashboardPinjamanReportController.php:397` |
| `resolveSmallArrearsSelectedPeriod` | method | 16 | `app/Http/Controllers/DashboardPinjamanReportController.php:3181` |
| `sixMonthArrearsExport` | method | 16 | `app/Http/Controllers/DashboardPinjamanReportController.php:478` |

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

## Class Nodes

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
- `Lw321DailyLoanMapper` - `app/Support/Lw321DailyLoanMapper.php`
- `Lw321DailyLoanMapperTest` - `tests/Unit/Lw321DailyLoanMapperTest.php`
- `Lw321DailyLoanSyncService` - `app/Support/Lw321DailyLoanSyncService.php`
- `Lw321DailyLoanSyncServiceTest` - `tests/Unit/Lw321DailyLoanSyncServiceTest.php`
- `Lw325PhPolarsProcessorTest` - `tests/Unit/Lw325PhPolarsProcessorTest.php`
- `SsaPinjamanDailyLoanRewriteService` - `app/Services/SsaPinjamanDailyLoanRewriteService.php`
- `SsaPinjamanDailyLoanRewriteServiceTest` - `tests/Unit/SsaPinjamanDailyLoanRewriteServiceTest.php`

## Command Nodes

- `ssa-pinjaman:rewrite-ytd-reference` - `app/Console/Commands/RewriteSsaPinjamanYtdReferenceCommand.php`

## View Nodes

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
- `report.kinerjarm` - `resources/views/report/kinerjarm.blade.php`
- `report.kinerjarm-detail-modal` - `resources/views/report/kinerjarm-detail-modal.blade.php`
- `report.kinerjarm-performance-table-section` - `resources/views/report/kinerjarm-performance-table-section.blade.php`
- `report.kinerjarm-quality-series-section` - `resources/views/report/kinerjarm-quality-series-section.blade.php`
- `report.kinerjarm-table` - `resources/views/report/kinerjarm-table.blade.php`
- `report.kinerjarm-table-section` - `resources/views/report/kinerjarm-table-section.blade.php`

## Table Nodes

- `daily_loan_dinamis`
- `dashboard_pinjaman_chart_periodik_snapshots`
- `dashboard_pinjaman_snapshots`
- `lw325_ph`
- `ssa_pinjaman`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| dashboard-pinjaman -> core (calls) | 217 |
| dashboard-pinjaman -> access-control (protected_by) | 165 |
| dashboard-pinjaman -> core (accepts) | 64 |
| dashboard-pinjaman -> core (instantiates) | 64 |
| tests -> dashboard-pinjaman (writes_table) | 45 |
| dashboard-pinjaman -> jobs-snapshots (writes_table) | 34 |
| dashboard-harian -> dashboard-pinjaman (writes_table) | 28 |
| dashboard-pinjaman -> core (writes_table) | 27 |
| jobs-snapshots -> dashboard-pinjaman (reads_table) | 27 |
| import -> dashboard-pinjaman (writes_table) | 27 |
| core -> dashboard-pinjaman (reads_table) | 24 |
| tests -> dashboard-pinjaman (contains) | 24 |
| jobs-snapshots -> dashboard-pinjaman (writes_table) | 22 |
| tests -> dashboard-pinjaman (defines_table) | 19 |
| database -> dashboard-pinjaman (checks_table) | 16 |
| dashboard-simpanan -> dashboard-pinjaman (reads_table) | 14 |
| core -> dashboard-pinjaman (references_route) | 14 |
| core -> dashboard-pinjaman (checks_table) | 13 |
| dashboard-pinjaman -> jobs-snapshots (calls) | 13 |
| dashboard-pinjaman -> dashboard-harian (writes_table) | 13 |
| import -> dashboard-pinjaman (defines_table) | 13 |
| dashboard-pinjaman -> core (extends_view) | 13 |
| tests -> dashboard-pinjaman (reads_table) | 12 |
| dashboard-pinjaman -> tests (extends) | 12 |
| database -> dashboard-pinjaman (alters_table) | 11 |
| import -> dashboard-pinjaman (reads_table) | 11 |
| dashboard-simpanan -> dashboard-pinjaman (checks_table) | 10 |
| dashboard-pinjaman -> tests (calls) | 10 |
| dashboard-pinjaman -> core (defines_table) | 9 |
| dashboard-pinjaman -> access-control (calls) | 9 |
