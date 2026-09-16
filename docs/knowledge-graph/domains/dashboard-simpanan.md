# Domain: dashboard-simpanan

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-simpanan --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 1 |
| file | 31 |
| function | 4 |
| method | 492 |
| unresolved_symbol | 5 |
| route | 30 |
| class | 21 |
| table | 6 |
| view | 7 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DashboardSimpananController` | class | 357 | `app/Http/Controllers/DashboardSimpananController.php:48` |
| `simpanan_multipn` | table | 83 | - |
| `ssa_simpanan` | table | 54 | - |
| `DashboardDanaService` | class | 37 | `app/Support/DashboardDanaService.php:11` |
| `buildDashboardPayloadFresh` | method | 36 | `app/Http/Controllers/DashboardSimpananController.php:9066` |
| `formatCurrencyCompact` | method | 36 | `app/Http/Controllers/DashboardSimpananController.php:14197` |
| `dashboardBranchNames` | method | 30 | `app/Http/Controllers/DashboardSimpananController.php:14101` |
| `DashboardSimpananHarianSnapshotSourceTest` | class | 28 | `tests/Unit/DashboardSimpananHarianSnapshotSourceTest.php:16` |
| `reportCacheVersion` | method | 28 | `app/Http/Controllers/DashboardSimpananController.php:14260` |
| `buildArea6PortfolioLandingFresh` | method | 25 | `app/Http/Controllers/DashboardSimpananController.php:9745` |
| `dashboard` | route | 23 | - |
| `buildLandingSimpananPayloadFresh` | method | 21 | `app/Http/Controllers/DashboardSimpananController.php:204` |
| `area6HarianSnapshotSummaryQuery` | method | 20 | `app/Http/Controllers/DashboardSimpananController.php:11844` |
| `effectiveDashboardBranchScope` | method | 20 | `app/Http/Controllers/DashboardSimpananController.php:14080` |
| `readMarketShareMappingWorkbookPreview` | method | 19 | `app/Http/Controllers/DashboardSimpananController.php:3382` |
| `buildDigitalCard` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:13915` |
| `dashboardBranchDisplayNames` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:14123` |
| `dashboard_simpanan_snapshots` | table | 18 | - |
| `formatInteger` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:14175` |
| `marketShareMappingIndex` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:2122` |
| `LandingSimpananCardTest` | class | 17 | `tests/Unit/LandingSimpananCardTest.php:10` |
| `SsaSimpananBusinessSegmentBackfillService` | class | 17 | `app/Services/SsaSimpananBusinessSegmentBackfillService.php:11` |
| `configureLandingBranchScope` | method | 17 | `app/Http/Controllers/DashboardSimpananController.php:14054` |
| `formatPeriodLabel` | method | 17 | `app/Http/Controllers/DashboardSimpananController.php:14230` |
| `buildArea6ScopeSegmentPerformance` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:10773` |
| `buildBrimoPerformanceCard` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:13071` |
| `buildQlolaPerformanceCard` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:13382` |
| `index` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:100` |
| `EnsureDashboardSimpananSnapshotJob` | class | 15 | `app/Jobs/EnsureDashboardSimpananSnapshotJob.php:18` |
| `SsaSimpananSnapshotBuilder` | class | 15 | `app/Support/SsaSimpananSnapshotBuilder.php:8` |

## Route Nodes

- `dashboard` - `dashboard`
- `dashboard.area6-data` - `dashboard/area6-data`
- `dashboard.consumer-operations` - `dashboard/consumer-operations`
- `dashboard.loan-analytics` - `dashboard/loan-analytics`
- `dashboard.micro-one-time-nominatives` - `dashboard/micro-one-time-nominatives`
- `dashboard.micro-performance` - `dashboard/micro-performance`
- `dashboard.micro-pipeline` - `dashboard/micro-pipeline`
- `dashboard.pn-mismatch-nominatives` - `dashboard/pn-mismatch-nominatives`
- `dashboard.presentation` - `dashboard/presentation`
- `dashboard.presentation-data` - `dashboard/presentation-data`
- `dashboard.presentation-data.detail` - `dashboard/presentation-data/detail/{section}`
- `dashboard.presentation-data.summary` - `dashboard/presentation-data/summary`
- `dashboard.presentation-kts-data` - `dashboard/presentation-kts-data`
- `dashboard.presentation.export-pptx` - `dashboard/presentation/export-pptx`
- `dashboard.presentation.export-pptx.download` - `dashboard/presentation/export-pptx/{token}/download`
- `dashboard.presentation.export-pptx.start` - `dashboard/presentation/export-pptx/start`
- `dashboard.presentation.export-pptx.status` - `dashboard/presentation/export-pptx/{token}/status`
- `dashboard.simpanan` - `dashboard/simpanan`
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
- `DashboardSimpananDeltaColorTest` - `tests/Unit/DashboardSimpananDeltaColorTest.php`
- `DashboardSimpananHarianSnapshotSourceTest` - `tests/Unit/DashboardSimpananHarianSnapshotSourceTest.php`
- `EnsureDashboardSimpananSnapshotJob` - `app/Jobs/EnsureDashboardSimpananSnapshotJob.php`
- `EnsureDashboardSimpananSnapshotJobTest` - `tests/Unit/EnsureDashboardSimpananSnapshotJobTest.php`
- `LandingSimpananCardTest` - `tests/Unit/LandingSimpananCardTest.php`
- `OptimizedDashboardDanaService` - `app/Support/OptimizedDashboardDanaService.php`
- `RebuildSimpananPeriodJob` - `app/Jobs/RebuildSimpananPeriodJob.php`
- `ReportSnapshotBuilderSimpananFreshnessTest` - `tests/Unit/ReportSnapshotBuilderSimpananFreshnessTest.php`
- `SimpananMultiPnExcelProcessorScriptTest` - `tests/Unit/SimpananMultiPnExcelProcessorScriptTest.php`
- `SimpananMultiPnSnapshotGate` - `app/Support/SimpananMultiPnSnapshotGate.php`
- `SimpananMultiPnSnapshotGateTest` - `tests/Unit/SimpananMultiPnSnapshotGateTest.php`
- `SimpananMultiPnSourcePreservationTest` - `tests/Unit/SimpananMultiPnSourcePreservationTest.php`
- `SimpananMultipnIndexCompactionMigrationTest` - `tests/Unit/SimpananMultipnIndexCompactionMigrationTest.php`
- `SsaSimpananBusinessSegmentBackfillService` - `app/Services/SsaSimpananBusinessSegmentBackfillService.php`
- `SsaSimpananSnapshotBuilder` - `app/Support/SsaSimpananSnapshotBuilder.php`
- `SsaSimpananSnapshotBuilderGuardTest` - `tests/Unit/SsaSimpananSnapshotBuilderGuardTest.php`
- `WarmDashboardSimpananCacheJob` - `app/Jobs/WarmDashboardSimpananCacheJob.php`

## Command Nodes

- `ssa-simpanan:backfill-business-segment` - `app/Console/Commands/BackfillSsaSimpananBusinessSegmentCommand.php`

## View Nodes

- `dashboard.simpanan` - `resources/views/dashboard/simpanan.blade.php`
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
| dashboard-simpanan -> core (calls) | 190 |
| dashboard-simpanan -> access-control (protected_by) | 150 |
| dashboard-simpanan -> core (accepts) | 53 |
| dashboard-simpanan -> core (instantiates) | 49 |
| dashboard-harian -> dashboard-simpanan (writes_table) | 20 |
| core -> dashboard-simpanan (references_route) | 19 |
| dashboard-simpanan -> access-control (calls) | 17 |
| dashboard-simpanan -> dashboard-pinjaman (reads_table) | 14 |
| import -> dashboard-simpanan (writes_table) | 14 |
| jobs-snapshots -> dashboard-simpanan (reads_table) | 12 |
| database -> dashboard-simpanan (checks_table) | 10 |
| dashboard-simpanan -> dashboard-pinjaman (checks_table) | 10 |
| dashboard-simpanan -> jobs-snapshots (uses_trait) | 10 |
| dashboard-simpanan -> tests (extends) | 10 |
| dashboard-simpanan -> core (references_route) | 9 |
| dashboard-simpanan -> dashboard-harian (writes_table) | 9 |
| core -> dashboard-simpanan (reads_table) | 8 |
| jobs-snapshots -> dashboard-simpanan (writes_table) | 8 |
| presentation -> dashboard-simpanan (references_route) | 8 |
| access-control -> dashboard-simpanan (references_route) | 7 |
| dashboard-simpanan -> import (checks_table) | 7 |
| tests -> dashboard-simpanan (contains) | 7 |
| database -> dashboard-simpanan (alters_table) | 6 |
| dashboard-simpanan -> jobs-snapshots (reads_table) | 6 |
| dashboard-simpanan -> marketshare (calls) | 6 |
| dashboard-simpanan -> core (renders) | 6 |
| import -> dashboard-simpanan (reads_table) | 6 |
| tests -> dashboard-simpanan (defines_table) | 6 |
| import -> dashboard-simpanan (defines_table) | 6 |
| dashboard-simpanan -> core (extends_view) | 6 |
