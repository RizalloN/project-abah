# Domain: dashboard-simpanan

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-simpanan --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 1 |
| file | 44 |
| function | 4 |
| method | 621 |
| route | 37 |
| class | 31 |
| unresolved_symbol | 1 |
| table | 10 |
| view | 10 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DashboardSimpananController` | class | 360 | `app/Http/Controllers/DashboardSimpananController.php:48` |
| `simpanan_multipn` | table | 85 | - |
| `RasioCasaDebiturController` | class | 71 | `app/Http/Controllers/RasioCasaDebiturController.php:20` |
| `ssa_simpanan` | table | 55 | - |
| `DashboardDanaService` | class | 37 | `app/Support/DashboardDanaService.php:11` |
| `buildDashboardPayloadFresh` | method | 36 | `app/Http/Controllers/DashboardSimpananController.php:9074` |
| `formatCurrencyCompact` | method | 36 | `app/Http/Controllers/DashboardSimpananController.php:14280` |
| `RekeningDormantController` | class | 35 | `app/Http/Controllers/RekeningDormantController.php:19` |
| `dashboardBranchNames` | method | 30 | `app/Http/Controllers/DashboardSimpananController.php:14184` |
| `DashboardSimpananHarianSnapshotSourceTest` | class | 28 | `tests/Unit/DashboardSimpananHarianSnapshotSourceTest.php:16` |
| `reportCacheVersion` | method | 28 | `app/Http/Controllers/DashboardSimpananController.php:14343` |
| `buildArea6PortfolioLandingFresh` | method | 25 | `app/Http/Controllers/DashboardSimpananController.php:9753` |
| `dashboard` | route | 22 | - |
| `fetchData` | method | 22 | `app/Http/Controllers/RasioCasaDebiturController.php:63` |
| `buildLandingSimpananPayloadFresh` | method | 21 | `app/Http/Controllers/DashboardSimpananController.php:204` |
| `area6HarianSnapshotSummaryQuery` | method | 20 | `app/Http/Controllers/DashboardSimpananController.php:11852` |
| `effectiveDashboardBranchScope` | method | 20 | `app/Http/Controllers/DashboardSimpananController.php:14163` |
| `readMarketShareMappingWorkbookPreview` | method | 19 | `app/Http/Controllers/DashboardSimpananController.php:3390` |
| `buildDigitalCard` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:13967` |
| `dashboardBranchDisplayNames` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:14206` |
| `dashboard_simpanan_snapshots` | table | 18 | - |
| `formatInteger` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:14258` |
| `marketShareMappingIndex` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:2130` |
| `LandingSimpananCardTest` | class | 17 | `tests/Unit/LandingSimpananCardTest.php:10` |
| `SsaSimpananBusinessSegmentBackfillService` | class | 17 | `app/Services/SsaSimpananBusinessSegmentBackfillService.php:11` |
| `buildBrimoPerformanceCard` | method | 17 | `app/Http/Controllers/DashboardSimpananController.php:13091` |
| `buildQlolaPerformanceCard` | method | 17 | `app/Http/Controllers/DashboardSimpananController.php:13417` |
| `computeSummarySnapshot` | method | 17 | `app/Http/Controllers/RasioCasaDebiturController.php:576` |
| `configureLandingBranchScope` | method | 17 | `app/Http/Controllers/DashboardSimpananController.php:14137` |
| `formatPeriodLabel` | method | 17 | `app/Http/Controllers/DashboardSimpananController.php:14313` |

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
- `report.data.rasiocasa` - `report/data/rasiocasa`
- `report.data.rasiocasa-per-rm` - `report/data/rasiocasa-per-rm`
- `report.data.rekening-dormant` - `report/data/rekening-dormant`
- `report.rasiocasa.debitur` - `report/rekening-transaksi-debitur`
- `report.rasiocasa.filters-per-rm` - `report/rekening-transaksi-debitur/filters-per-rm`
- `report.rekening-dormant` - `report/rekening-transaksi-debitur/rekening-dormant`
- `report.rekening-dormant.filters` - `report/rekening-transaksi-debitur/rekening-dormant/filters`

## Class Nodes

- `BackfillSsaSimpananBusinessSegmentCommand` - `app/Console/Commands/BackfillSsaSimpananBusinessSegmentCommand.php`
- `DashboardDanaService` - `app/Support/DashboardDanaService.php`
- `DashboardSimpananController` - `app/Http/Controllers/DashboardSimpananController.php`
- `DashboardSimpananControllerSnapshotGateTest` - `tests/Unit/DashboardSimpananControllerSnapshotGateTest.php`
- `DashboardSimpananDeltaColorTest` - `tests/Unit/DashboardSimpananDeltaColorTest.php`
- `DashboardSimpananHarianSnapshotSourceTest` - `tests/Unit/DashboardSimpananHarianSnapshotSourceTest.php`
- `EnsureDashboardSimpananSnapshotJob` - `app/Jobs/EnsureDashboardSimpananSnapshotJob.php`
- `EnsureDashboardSimpananSnapshotJobTest` - `tests/Unit/EnsureDashboardSimpananSnapshotJobTest.php`
- `EnsureRasioCasaSnapshotJob` - `app/Jobs/EnsureRasioCasaSnapshotJob.php`
- `EnsureRekeningDormantSnapshotJob` - `app/Jobs/EnsureRekeningDormantSnapshotJob.php`
- `LandingSimpananCardTest` - `tests/Unit/LandingSimpananCardTest.php`
- `OptimizedDashboardDanaService` - `app/Support/OptimizedDashboardDanaService.php`
- `RasioCasaDebiturController` - `app/Http/Controllers/RasioCasaDebiturController.php`
- `RasioCasaDebiturControllerSnapshotDeferralTest` - `tests/Unit/RasioCasaDebiturControllerSnapshotDeferralTest.php`
- `RasioCasaDebiturPerRmAllUnitTest` - `tests/Unit/RasioCasaDebiturPerRmAllUnitTest.php`
- `RasioCasaDebiturPerUkerTest` - `tests/Unit/RasioCasaDebiturPerUkerTest.php`
- `RebuildDormantPeriodJob` - `app/Jobs/RebuildDormantPeriodJob.php`
- `RebuildSimpananPeriodJob` - `app/Jobs/RebuildSimpananPeriodJob.php`
- `RebuildSnapshotDormantBatch` - `app/Jobs/RebuildSnapshotDormantBatch.php`
- `RekeningDormantController` - `app/Http/Controllers/RekeningDormantController.php`
- `RekeningDormantControllerPeriodResolutionTest` - `tests/Unit/RekeningDormantControllerPeriodResolutionTest.php`
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
- `report.Rasiocasadebitur` - `resources/views/report/Rasiocasadebitur.blade.php`
- `report.dashboard-dana` - `resources/views/report/dashboard-dana.blade.php`
- `report.dashboard-dana-cras-mapping` - `resources/views/report/dashboard-dana-cras-mapping.blade.php`
- `report.dashboard-dana-market-share` - `resources/views/report/dashboard-dana-market-share.blade.php`
- `report.dashboard-dana-market-share-area6` - `resources/views/report/dashboard-dana-market-share-area6.blade.php`
- `report.dashboard-dana-market-share-sektoral` - `resources/views/report/dashboard-dana-market-share-sektoral.blade.php`
- `report.dashboard-dana._market_share_geography` - `resources/views/report/dashboard-dana/_market_share_geography.blade.php`
- `report.partials.rasio-casa-unit-table` - `resources/views/report/partials/rasio-casa-unit-table.blade.php`
- `report.rekening-dormant` - `resources/views/report/rekening-dormant.blade.php`

## Table Nodes

- `dashboard_simpanan_branch_snapshots`
- `dashboard_simpanan_snapshots`
- `rasio_casa_debitur_snapshots`
- `rasio_casa_debitur_uker_snapshots`
- `rekening_dormant`
- `rekening_dormant_snapshots`
- `simpanan_multipn`
- `simpanan_scope_resolution`
- `ssa_simpanan`
- `ssa_simpanan_snapshots`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| dashboard-simpanan -> core (calls) | 220 |
| dashboard-simpanan -> access-control (protected_by) | 185 |
| dashboard-simpanan -> core (accepts) | 59 |
| dashboard-simpanan -> core (instantiates) | 55 |
| dashboard-simpanan -> access-control (calls) | 28 |
| dashboard-simpanan -> jobs-snapshots (uses_trait) | 24 |
| dashboard-simpanan -> dashboard-pinjaman (reads_table) | 22 |
| dashboard-harian -> dashboard-simpanan (writes_table) | 19 |
| import -> dashboard-simpanan (writes_table) | 19 |
| database -> dashboard-simpanan (checks_table) | 15 |
| dashboard-simpanan -> tests (extends) | 14 |
| jobs-snapshots -> dashboard-simpanan (reads_table) | 13 |
| dashboard-simpanan -> tests (calls) | 13 |
| platform -> dashboard-simpanan (references_route) | 12 |
| dashboard-simpanan -> dashboard-pinjaman (checks_table) | 11 |
| jobs-snapshots -> dashboard-simpanan (writes_table) | 10 |
| dashboard-simpanan -> core (uses_trait) | 10 |
| dashboard-simpanan -> dashboard-harian (writes_table) | 9 |
| dashboard-simpanan -> jobs-snapshots (implements) | 9 |
| core -> dashboard-simpanan (references_route) | 9 |
| database -> dashboard-simpanan (alters_table) | 8 |
| dashboard-simpanan -> platform (calls) | 8 |
| dashboard-simpanan -> jobs-snapshots (calls) | 8 |
| presentation -> dashboard-simpanan (references_route) | 8 |
| dashboard-simpanan -> platform (extends_view) | 8 |
| access-control -> dashboard-simpanan (references_route) | 7 |
| database -> dashboard-simpanan (defines_table) | 7 |
| dashboard-simpanan -> import (checks_table) | 7 |
| dashboard-simpanan -> import (reads_table) | 7 |
| import -> dashboard-simpanan (reads_table) | 7 |
