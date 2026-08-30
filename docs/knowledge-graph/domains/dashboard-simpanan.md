# Domain: dashboard-simpanan

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-simpanan --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 1 |
| file | 27 |
| function | 4 |
| method | 436 |
| unresolved_symbol | 5 |
| route | 26 |
| class | 18 |
| table | 6 |
| view | 6 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DashboardSimpananController` | class | 320 | `app/Http/Controllers/DashboardSimpananController.php:43` |
| `simpanan_multipn` | table | 83 | - |
| `ssa_simpanan` | table | 46 | - |
| `buildDashboardPayloadFresh` | method | 37 | `app/Http/Controllers/DashboardSimpananController.php:7186` |
| `DashboardDanaService` | class | 36 | `app/Support/DashboardDanaService.php:11` |
| `formatCurrencyCompact` | method | 36 | `app/Http/Controllers/DashboardSimpananController.php:11910` |
| `reportCacheVersion` | method | 28 | `app/Http/Controllers/DashboardSimpananController.php:11973` |
| `dashboardBranchNames` | method | 26 | `app/Http/Controllers/DashboardSimpananController.php:11824` |
| `DashboardSimpananHarianSnapshotSourceTest` | class | 25 | `tests/Unit/DashboardSimpananHarianSnapshotSourceTest.php:16` |
| `buildArea6PortfolioLandingFresh` | method | 25 | `app/Http/Controllers/DashboardSimpananController.php:7859` |
| `dashboard` | route | 21 | - |
| `area6HarianSnapshotSummaryQuery` | method | 19 | `app/Http/Controllers/DashboardSimpananController.php:9532` |
| `formatInteger` | method | 19 | `app/Http/Controllers/DashboardSimpananController.php:11888` |
| `readMarketShareMappingWorkbookPreview` | method | 19 | `app/Http/Controllers/DashboardSimpananController.php:1639` |
| `buildDigitalCard` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:11638` |
| `dashboardBranchDisplayNames` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:11836` |
| `dashboard_simpanan_snapshots` | table | 18 | - |
| `marketShareMappingIndex` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:379` |
| `SsaSimpananBusinessSegmentBackfillService` | class | 17 | `app/Services/SsaSimpananBusinessSegmentBackfillService.php:11` |
| `formatPeriodLabel` | method | 17 | `app/Http/Controllers/DashboardSimpananController.php:11943` |
| `buildBrimoPerformanceCard` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:10794` |
| `buildQlolaPerformanceCard` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:11105` |
| `effectiveDashboardBranchScope` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:11803` |
| `index` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:89` |
| `EnsureDashboardSimpananSnapshotJob` | class | 15 | `app/Jobs/EnsureDashboardSimpananSnapshotJob.php:18` |
| `SsaSimpananSnapshotBuilder` | class | 15 | `app/Support/SsaSimpananSnapshotBuilder.php:8` |
| `buildDigitalPerformance` | method | 15 | `app/Http/Controllers/DashboardSimpananController.php:10599` |
| `buildPayrollPerformanceCard` | method | 15 | `app/Http/Controllers/DashboardSimpananController.php:10967` |
| `percentChange` | method | 15 | `app/Http/Controllers/DashboardSimpananController.php:11870` |
| `resolveArea6DailyLoanPeriod` | method | 15 | `app/Http/Controllers/DashboardSimpananController.php:9309` |

## Route Nodes

- `dashboard` - `dashboard`
- `dashboard.area6-data` - `dashboard/area6-data`
- `dashboard.loan-analytics` - `dashboard/loan-analytics`
- `dashboard.micro-one-time-nominatives` - `dashboard/micro-one-time-nominatives`
- `dashboard.micro-performance` - `dashboard/micro-performance`
- `dashboard.presentation` - `dashboard/presentation`
- `dashboard.presentation-data` - `dashboard/presentation-data`
- `dashboard.presentation-data.detail` - `dashboard/presentation-data/detail/{section}`
- `dashboard.presentation-data.summary` - `dashboard/presentation-data/summary`
- `dashboard.presentation-kts-data` - `dashboard/presentation-kts-data`
- `dashboard.presentation.export-pptx` - `dashboard/presentation/export-pptx`
- `dashboard.presentation.export-pptx.download` - `dashboard/presentation/export-pptx/{token}/download`
- `dashboard.presentation.export-pptx.start` - `dashboard/presentation/export-pptx/start`
- `dashboard.presentation.export-pptx.status` - `dashboard/presentation/export-pptx/{token}/status`
- `dashboard.sme-operations` - `dashboard/sme-operations`
- `dashboard.sme-vendor-nominatives` - `dashboard/sme-vendor-nominatives`
- `report.dashboard-dana` - `report/dashboard-dana`
- `report.dashboard-dana.data` - `report/dashboard-dana/data`
- `report.dashboard-dana.market-share` - `report/dashboard-dana/market-share`
- `report.dashboard-dana.market-share.area6` - `report/dashboard-dana/market-share/area-6`
- `report.dashboard-dana.market-share.instansi` - `report/dashboard-dana/market-share/instansi`
- `report.dashboard-dana.market-share.instansi.data` - `report/dashboard-dana/market-share/instansi/data`
- `report.dashboard-dana.market-share.mapping` - `report/dashboard-dana/market-share/mapping`
- `report.dashboard-dana.market-share.mapping-cras` - `report/dashboard-dana/market-share/mapping-cras`
- `report.dashboard-dana.market-share.mapping-cras.data` - `report/dashboard-dana/market-share/mapping-cras/data`
- `report.dashboard-dana.market-share.sektoral` - `report/dashboard-dana/market-share/sektoral`

## Class Nodes

- `BackfillSsaSimpananBusinessSegmentCommand` - `app/Console/Commands/BackfillSsaSimpananBusinessSegmentCommand.php`
- `DashboardDanaService` - `app/Support/DashboardDanaService.php`
- `DashboardSimpananController` - `app/Http/Controllers/DashboardSimpananController.php`
- `DashboardSimpananControllerSnapshotGateTest` - `tests/Unit/DashboardSimpananControllerSnapshotGateTest.php`
- `DashboardSimpananHarianSnapshotSourceTest` - `tests/Unit/DashboardSimpananHarianSnapshotSourceTest.php`
- `EnsureDashboardSimpananSnapshotJob` - `app/Jobs/EnsureDashboardSimpananSnapshotJob.php`
- `EnsureDashboardSimpananSnapshotJobTest` - `tests/Unit/EnsureDashboardSimpananSnapshotJobTest.php`
- `OptimizedDashboardDanaService` - `app/Support/OptimizedDashboardDanaService.php`
- `RebuildSimpananPeriodJob` - `app/Jobs/RebuildSimpananPeriodJob.php`
- `ReportSnapshotBuilderSimpananFreshnessTest` - `tests/Unit/ReportSnapshotBuilderSimpananFreshnessTest.php`
- `SimpananMultiPnExcelProcessorScriptTest` - `tests/Unit/SimpananMultiPnExcelProcessorScriptTest.php`
- `SimpananMultiPnSnapshotGate` - `app/Support/SimpananMultiPnSnapshotGate.php`
- `SimpananMultiPnSnapshotGateTest` - `tests/Unit/SimpananMultiPnSnapshotGateTest.php`
- `SimpananMultiPnSourcePreservationTest` - `tests/Unit/SimpananMultiPnSourcePreservationTest.php`
- `SsaSimpananBusinessSegmentBackfillService` - `app/Services/SsaSimpananBusinessSegmentBackfillService.php`
- `SsaSimpananSnapshotBuilder` - `app/Support/SsaSimpananSnapshotBuilder.php`
- `SsaSimpananSnapshotBuilderGuardTest` - `tests/Unit/SsaSimpananSnapshotBuilderGuardTest.php`
- `WarmDashboardSimpananCacheJob` - `app/Jobs/WarmDashboardSimpananCacheJob.php`

## Command Nodes

- `ssa-simpanan:backfill-business-segment` - `app/Console/Commands/BackfillSsaSimpananBusinessSegmentCommand.php`

## View Nodes

- `report.dashboard-dana` - `resources/views/report/dashboard-dana.blade.php`
- `report.dashboard-dana-cras-mapping` - `resources/views/report/dashboard-dana-cras-mapping.blade.php`
- `report.dashboard-dana-market-share` - `resources/views/report/dashboard-dana-market-share.blade.php`
- `report.dashboard-dana-market-share-area6` - `resources/views/report/dashboard-dana-market-share-area6.blade.php`
- `report.dashboard-dana-market-share-sektoral` - `resources/views/report/dashboard-dana-market-share-sektoral.blade.php`
- `report.dashboard-dana._market_share_geography` - `resources/views/report/dashboard-dana/_market_share_geography.blade.php`

## Table Nodes

- `dashboard_simpanan_branch_snapshots`
- `dashboard_simpanan_snapshots`
- `simpanan_multipn`
- `simpanan_scope_resolution`
- `ssa_simpanan`
- `ssa_simpanan_snapshots`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| dashboard-simpanan -> core (calls) | 157 |
| dashboard-simpanan -> access-control (protected_by) | 130 |
| dashboard-simpanan -> core (accepts) | 47 |
| dashboard-simpanan -> core (instantiates) | 39 |
| dashboard-harian -> dashboard-simpanan (writes_table) | 17 |
| core -> dashboard-simpanan (references_route) | 16 |
| dashboard-simpanan -> access-control (calls) | 15 |
| dashboard-simpanan -> dashboard-pinjaman (reads_table) | 14 |
| import -> dashboard-simpanan (writes_table) | 14 |
| jobs-snapshots -> dashboard-simpanan (reads_table) | 12 |
| dashboard-simpanan -> dashboard-pinjaman (checks_table) | 12 |
| database -> dashboard-simpanan (checks_table) | 10 |
| dashboard-simpanan -> jobs-snapshots (uses_trait) | 10 |
| dashboard-simpanan -> core (references_route) | 9 |
| core -> dashboard-simpanan (reads_table) | 8 |
| jobs-snapshots -> dashboard-simpanan (writes_table) | 8 |
| dashboard-simpanan -> dashboard-harian (writes_table) | 8 |
| presentation -> dashboard-simpanan (references_route) | 8 |
| dashboard-simpanan -> tests (extends) | 8 |
| access-control -> dashboard-simpanan (references_route) | 7 |
| dashboard-simpanan -> import (checks_table) | 7 |
| database -> dashboard-simpanan (alters_table) | 6 |
| dashboard-simpanan -> marketshare (calls) | 6 |
| import -> dashboard-simpanan (reads_table) | 6 |
| import -> dashboard-simpanan (defines_table) | 6 |
| tests -> dashboard-simpanan (contains) | 6 |
| dashboard-simpanan -> import (reads_table) | 5 |
| dashboard-simpanan -> core (renders) | 5 |
| tests -> dashboard-simpanan (defines_table) | 5 |
| jobs-snapshots -> dashboard-simpanan (defines_table) | 5 |
