# Domain: dashboard-simpanan

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-simpanan --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 1 |
| file | 31 |
| function | 4 |
| method | 470 |
| unresolved_symbol | 5 |
| route | 29 |
| class | 21 |
| table | 6 |
| view | 7 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DashboardSimpananController` | class | 347 | `app/Http/Controllers/DashboardSimpananController.php:47` |
| `simpanan_multipn` | table | 83 | - |
| `ssa_simpanan` | table | 50 | - |
| `DashboardDanaService` | class | 36 | `app/Support/DashboardDanaService.php:11` |
| `buildDashboardPayloadFresh` | method | 36 | `app/Http/Controllers/DashboardSimpananController.php:7862` |
| `formatCurrencyCompact` | method | 36 | `app/Http/Controllers/DashboardSimpananController.php:12993` |
| `dashboardBranchNames` | method | 30 | `app/Http/Controllers/DashboardSimpananController.php:12897` |
| `reportCacheVersion` | method | 28 | `app/Http/Controllers/DashboardSimpananController.php:13056` |
| `DashboardSimpananHarianSnapshotSourceTest` | class | 26 | `tests/Unit/DashboardSimpananHarianSnapshotSourceTest.php:16` |
| `buildArea6PortfolioLandingFresh` | method | 25 | `app/Http/Controllers/DashboardSimpananController.php:8541` |
| `dashboard` | route | 23 | - |
| `area6HarianSnapshotSummaryQuery` | method | 19 | `app/Http/Controllers/DashboardSimpananController.php:10640` |
| `effectiveDashboardBranchScope` | method | 19 | `app/Http/Controllers/DashboardSimpananController.php:12876` |
| `readMarketShareMappingWorkbookPreview` | method | 19 | `app/Http/Controllers/DashboardSimpananController.php:2179` |
| `buildDigitalCard` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:12711` |
| `dashboardBranchDisplayNames` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:12919` |
| `dashboard_simpanan_snapshots` | table | 18 | - |
| `formatInteger` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:12971` |
| `marketShareMappingIndex` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:919` |
| `SsaSimpananBusinessSegmentBackfillService` | class | 17 | `app/Services/SsaSimpananBusinessSegmentBackfillService.php:11` |
| `formatPeriodLabel` | method | 17 | `app/Http/Controllers/DashboardSimpananController.php:13026` |
| `buildArea6ScopeSegmentPerformance` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:9569` |
| `buildBrimoPerformanceCard` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:11867` |
| `buildQlolaPerformanceCard` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:12178` |
| `configureLandingBranchScope` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:12850` |
| `index` | method | 16 | `app/Http/Controllers/DashboardSimpananController.php:99` |
| `EnsureDashboardSimpananSnapshotJob` | class | 15 | `app/Jobs/EnsureDashboardSimpananSnapshotJob.php:18` |
| `SsaSimpananSnapshotBuilder` | class | 15 | `app/Support/SsaSimpananSnapshotBuilder.php:8` |
| `buildArea6PortfolioScopePayload` | method | 15 | `app/Http/Controllers/DashboardSimpananController.php:9039` |
| `buildDigitalPerformance` | method | 15 | `app/Http/Controllers/DashboardSimpananController.php:11672` |

## Route Nodes

- `dashboard` - `dashboard`
- `dashboard.area6-data` - `dashboard/area6-data`
- `dashboard.consumer-operations` - `dashboard/consumer-operations`
- `dashboard.loan-analytics` - `dashboard/loan-analytics`
- `dashboard.micro-one-time-nominatives` - `dashboard/micro-one-time-nominatives`
- `dashboard.micro-performance` - `dashboard/micro-performance`
- `dashboard.micro-pipeline` - `dashboard/micro-pipeline`
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
| dashboard-simpanan -> core (calls) | 171 |
| dashboard-simpanan -> access-control (protected_by) | 145 |
| dashboard-simpanan -> core (accepts) | 51 |
| dashboard-simpanan -> core (instantiates) | 45 |
| dashboard-harian -> dashboard-simpanan (writes_table) | 19 |
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
| dashboard-simpanan -> marketshare (calls) | 6 |
| dashboard-simpanan -> core (renders) | 6 |
| import -> dashboard-simpanan (reads_table) | 6 |
| tests -> dashboard-simpanan (defines_table) | 6 |
| import -> dashboard-simpanan (defines_table) | 6 |
| dashboard-simpanan -> core (extends_view) | 6 |
| dashboard-simpanan -> import (reads_table) | 5 |
