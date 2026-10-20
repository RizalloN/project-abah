# Domain: dashboard-simpanan

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-simpanan --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 1 |
| file | 28 |
| method | 536 |
| route | 37 |
| class | 16 |
| table | 8 |
| view | 12 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DashboardSimpananController` | class | 347 | `app/Http/Controllers/DashboardSimpananController.php:48` |
| `RasioCasaDebiturController` | class | 63 | `app/Http/Controllers/RasioCasaDebiturController.php:20` |
| `simpanan_multipn` | table | 41 | - |
| `buildDashboardPayloadFresh` | method | 38 | `app/Http/Controllers/DashboardSimpananController.php:9663` |
| `DashboardDanaService` | class | 37 | `app/Support/DashboardDanaService.php:11` |
| `formatCurrencyCompact` | method | 37 | `app/Http/Controllers/DashboardSimpananController.php:14941` |
| `RekeningDormantController` | class | 34 | `app/Http/Controllers/RekeningDormantController.php:19` |
| `dashboardBranchNames` | method | 32 | `app/Http/Controllers/DashboardSimpananController.php:14845` |
| `reportCacheVersion` | method | 32 | `app/Http/Controllers/DashboardSimpananController.php:15028` |
| `effectiveDashboardBranchScope` | method | 27 | `app/Http/Controllers/DashboardSimpananController.php:14824` |
| `buildArea6PortfolioLandingFresh` | method | 26 | `app/Http/Controllers/DashboardSimpananController.php:10401` |
| `buildLandingSimpananPayloadFresh` | method | 25 | `app/Http/Controllers/DashboardSimpananController.php:218` |
| `dashboard` | route | 23 | - |
| `fetchData` | method | 22 | `app/Http/Controllers/RasioCasaDebiturController.php:63` |
| `area6HarianSnapshotSummaryQuery` | method | 20 | `app/Http/Controllers/DashboardSimpananController.php:12500` |
| `dashboardBranchDisplayNames` | method | 20 | `app/Http/Controllers/DashboardSimpananController.php:14867` |
| `dashboardScopeLabel` | method | 19 | `app/Http/Controllers/DashboardSimpananController.php:14874` |
| `readMarketShareMappingWorkbookPreview` | method | 19 | `app/Http/Controllers/DashboardSimpananController.php:3772` |
| `buildDigitalCard` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:14628` |
| `formatInteger` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:14919` |
| `marketShareMappingIndex` | method | 18 | `app/Http/Controllers/DashboardSimpananController.php:2512` |
| `SsaSimpananBusinessSegmentBackfillService` | class | 17 | `app/Services/SsaSimpananBusinessSegmentBackfillService.php:11` |
| `SsaSimpananSnapshotBuilder` | class | 17 | `app/Support/SsaSimpananSnapshotBuilder.php:8` |
| `buildBrimoPerformanceCard` | method | 17 | `app/Http/Controllers/DashboardSimpananController.php:13752` |
| `buildQlolaPerformanceCard` | method | 17 | `app/Http/Controllers/DashboardSimpananController.php:14078` |
| `computeSummarySnapshot` | method | 17 | `app/Http/Controllers/RasioCasaDebiturController.php:576` |
| `configureLandingBranchScope` | method | 17 | `app/Http/Controllers/DashboardSimpananController.php:14798` |
| `formatPeriodLabel` | method | 17 | `app/Http/Controllers/DashboardSimpananController.php:14998` |
| `reportCacheVersion` | method | 17 | `app/Http/Controllers/RasioCasaDebiturController.php:2322` |
| `RebuildSnapshotDormantBatch` | class | 16 | `app/Jobs/RebuildSnapshotDormantBatch.php:19` |

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
- `EnsureDashboardSimpananSnapshotJob` - `app/Jobs/EnsureDashboardSimpananSnapshotJob.php`
- `EnsureRasioCasaSnapshotJob` - `app/Jobs/EnsureRasioCasaSnapshotJob.php`
- `EnsureRekeningDormantSnapshotJob` - `app/Jobs/EnsureRekeningDormantSnapshotJob.php`
- `OptimizedDashboardDanaService` - `app/Support/OptimizedDashboardDanaService.php`
- `RasioCasaDebiturController` - `app/Http/Controllers/RasioCasaDebiturController.php`
- `RebuildDormantPeriodJob` - `app/Jobs/RebuildDormantPeriodJob.php`
- `RebuildSimpananPeriodJob` - `app/Jobs/RebuildSimpananPeriodJob.php`
- `RebuildSnapshotDormantBatch` - `app/Jobs/RebuildSnapshotDormantBatch.php`
- `RekeningDormantController` - `app/Http/Controllers/RekeningDormantController.php`
- `SimpananMultiPnSnapshotGate` - `app/Support/SimpananMultiPnSnapshotGate.php`
- `SsaSimpananBusinessSegmentBackfillService` - `app/Services/SsaSimpananBusinessSegmentBackfillService.php`
- `SsaSimpananSnapshotBuilder` - `app/Support/SsaSimpananSnapshotBuilder.php`
- `WarmDashboardSimpananCacheJob` - `app/Jobs/WarmDashboardSimpananCacheJob.php`

## Command Nodes

- `ssa-simpanan:backfill-business-segment` - `app/Console/Commands/BackfillSsaSimpananBusinessSegmentCommand.php`

## View Nodes

- `dashboard.partials.simpanan-landing-styles` - `resources/views/dashboard/partials/simpanan-landing-styles.blade.php`
- `dashboard.partials.simpanan-section-styles` - `resources/views/dashboard/partials/simpanan-section-styles.blade.php`
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
- `rekening_dormant_snapshots`
- `simpanan_multipn`
- `ssa_simpanan`
- `ssa_simpanan_snapshots`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| dashboard-simpanan -> access-control (protected_by) | 222 |
| dashboard-simpanan -> core (calls) | 201 |
| dashboard-simpanan -> core (accepts) | 59 |
| dashboard-simpanan -> access-control (calls) | 28 |
| dashboard-simpanan -> jobs-snapshots (uses_trait) | 24 |
| dashboard-simpanan -> dashboard-pinjaman (reads_table) | 22 |
| dashboard-simpanan -> core (instantiates) | 16 |
| database -> dashboard-simpanan (checks_table) | 15 |
| platform -> dashboard-simpanan (references_route) | 12 |
| dashboard-simpanan -> dashboard-pinjaman (checks_table) | 11 |
| dashboard-simpanan -> core (uses_trait) | 10 |
| jobs-snapshots -> dashboard-simpanan (reads_table) | 9 |
| dashboard-simpanan -> platform (calls) | 9 |
| dashboard-simpanan -> jobs-snapshots (implements) | 9 |
| core -> dashboard-simpanan (references_route) | 9 |
| database -> dashboard-simpanan (alters_table) | 8 |
| dashboard-simpanan -> platform (extends_view) | 8 |
| presentation -> dashboard-simpanan (references_route) | 8 |
| database -> dashboard-simpanan (defines_table) | 7 |
| dashboard-simpanan -> import (reads_table) | 7 |
| dashboard-simpanan -> jobs-snapshots (calls) | 7 |
| import -> dashboard-simpanan (calls) | 6 |
| dashboard-simpanan -> marketshare (calls) | 6 |
| dashboard-simpanan -> jobs-snapshots (accepts) | 6 |
| access-control -> dashboard-simpanan (references_route) | 5 |
| dashboard-simpanan -> import (checks_table) | 5 |
| dashboard-simpanan -> core (renders) | 5 |
| dashboard-simpanan -> jobs-snapshots (uses_queue) | 5 |
| jobs-snapshots -> dashboard-simpanan (writes_table) | 5 |
| jobs-snapshots -> dashboard-simpanan (contains) | 5 |
