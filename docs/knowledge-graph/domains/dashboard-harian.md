# Domain: dashboard-harian

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-harian --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 2 |
| file | 20 |
| method | 368 |
| unresolved_symbol | 1 |
| route | 9 |
| class | 14 |
| table | 2 |
| view | 5 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DashboardHarianSnapshotService` | class | 241 | `app/Support/DashboardHarianSnapshotService.php:15` |
| `dashboard_harian_snapshots` | table | 81 | - |
| `DashboardHarianSnapshotServiceTest` | class | 55 | `tests/Unit/DashboardHarianSnapshotServiceTest.php:14` |
| `DashboardHarianController` | class | 49 | `app/Http/Controllers/DashboardHarianController.php:20` |
| `createSourceMetadataTables` | method | 47 | `tests/Unit/DashboardHarianSnapshotServiceTest.php:2149` |
| `HourlyDpkDashboardService` | class | 43 | `app/Support/HourlyDpkDashboardService.php:11` |
| `normalizeDate` | method | 35 | `app/Support/DashboardHarianSnapshotService.php:4609` |
| `fillDashboardHarianSheet` | method | 23 | `app/Http/Controllers/DashboardHarianController.php:544` |
| `buildKeragaanUkerPayload` | method | 21 | `app/Support/DashboardHarianSnapshotService.php:738` |
| `rebuild` | method | 21 | `app/Support/DashboardHarianSnapshotService.php:203` |
| `normalizeFilterValues` | method | 20 | `app/Support/DashboardHarianSnapshotService.php:4675` |
| `syncDuePeriods` | method | 20 | `app/Support/DashboardHarianSnapshotService.php:241` |
| `OptimizedDashboardHarianSnapshotService` | class | 19 | `app/Support/OptimizedDashboardHarianSnapshotServiceV2.php:21` |
| `buildAggregatedRowsForPeriod` | method | 18 | `app/Support/DashboardHarianSnapshotService.php:1621` |
| `payload` | method | 18 | `app/Support/HourlyDpkDashboardService.php:38` |
| `buildPeriodSnapshot` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:402` |
| `normalizeKancaLabel` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:3629` |
| `resolveEffectivePeriod` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:567` |
| `RebuildDashboardHarianSnapshotJob` | class | 15 | `app/Jobs/RebuildDashboardHarianSnapshotJob.php:26` |
| `buildPeriodSnapshotUnlocked` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:438` |
| `fetchLoanAggregates` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:2249` |
| `keragaanUker` | method | 15 | `app/Http/Controllers/DashboardHarianController.php:147` |
| `DashboardHarianLdrRkaFormattingTest` | class | 14 | `tests/Unit/DashboardHarianLdrRkaFormattingTest.php:13` |
| `buildDashboardPayload` | method | 14 | `app/Support/DashboardHarianSnapshotService.php:1360` |
| `exportExcel` | method | 14 | `app/Http/Controllers/DashboardHarianController.php:231` |
| `index` | method | 14 | `app/Http/Controllers/DashboardHarianController.php:29` |
| `loadMetricsForPeriods` | method | 14 | `app/Support/DashboardHarianSnapshotService.php:1503` |
| `resolveEffectiveRkaPeriod` | method | 14 | `app/Support/DashboardHarianSnapshotService.php:684` |
| `slugKey` | method | 14 | `app/Support/DashboardHarianSnapshotService.php:3624` |
| `buildSourceMetadata` | method | 13 | `app/Support/DashboardHarianSnapshotService.php:4233` |

## Route Nodes

- `dashboard.harian` - `dashboard-harian`
- `dashboard.harian.data` - `dashboard-harian/data`
- `dashboard.harian.export` - `dashboard-harian/export`
- `dashboard.harian.keragaan-uker` - `dashboard-harian/keragaan-uker`
- `dashboard.harian.keragaan-uker.data` - `dashboard-harian/keragaan-uker/data`
- `dashboard.harian.timeseries` - `dashboard-harian/timeseries`
- `dashboard.harian.timeseries.data` - `dashboard-harian/timeseries/data`
- `report.dashboard-dana.hourly-dpk` - `report/dashboard-dana/hourly-dpk`
- `report.dashboard-dana.hourly-dpk.export-pdf` - `report/dashboard-dana/hourly-dpk/export-pdf`

## Class Nodes

- `DashboardHarianController` - `app/Http/Controllers/DashboardHarianController.php`
- `DashboardHarianLdrRkaFormattingTest` - `tests/Unit/DashboardHarianLdrRkaFormattingTest.php`
- `DashboardHarianResponsiveViewTest` - `tests/Unit/DashboardHarianResponsiveViewTest.php`
- `DashboardHarianSnapshotDirtyPeriodQueue` - `app/Support/DashboardHarianSnapshotDirtyPeriodQueue.php`
- `DashboardHarianSnapshotDirtyPeriodQueueTest` - `tests/Unit/DashboardHarianSnapshotDirtyPeriodQueueTest.php`
- `DashboardHarianSnapshotService` - `app/Support/DashboardHarianSnapshotService.php`
- `DashboardHarianSnapshotServiceTest` - `tests/Unit/DashboardHarianSnapshotServiceTest.php`
- `HourlyDpkDashboardService` - `app/Support/HourlyDpkDashboardService.php`
- `HourlyDpkDashboardServiceTest` - `tests/Unit/HourlyDpkDashboardServiceTest.php`
- `OptimizedDashboardHarianSnapshotService` - `app/Support/OptimizedDashboardHarianSnapshotServiceV2.php`
- `OptimizedDashboardHarianSnapshotServiceV3` - `app/Support/OptimizedDashboardHarianSnapshotServiceV4.php`
- `RebuildDashboardHarianCommand` - `app/Console/Commands/RebuildDashboardHarianCommand.php`
- `RebuildDashboardHarianSnapshotJob` - `app/Jobs/RebuildDashboardHarianSnapshotJob.php`
- `SyncDashboardHarianSnapshot` - `app/Console/Commands/SyncDashboardHarianSnapshot.php`

## Command Nodes

- `snapshot:rebuild-harian` - `app/Console/Commands/RebuildDashboardHarianCommand.php`
- `snapshot:sync-harian-dashboard` - `app/Console/Commands/SyncDashboardHarianSnapshot.php`

## View Nodes

- `report.dashboard-dana-hourly-dpk` - `resources/views/report/dashboard-dana-hourly-dpk.blade.php`
- `report.dashboard-dana-hourly-dpk-pdf` - `resources/views/report/dashboard-dana-hourly-dpk-pdf.blade.php`
- `report.dashboard-harian` - `resources/views/report/dashboard-harian.blade.php`
- `report.dashboard-harian-keragaan-uker` - `resources/views/report/dashboard-harian-keragaan-uker.blade.php`
- `report.dashboard-harian-timeseries` - `resources/views/report/dashboard-harian-timeseries.blade.php`

## Table Nodes

- `dashboard_harian_snapshots`
- `hourly_dpk`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| dashboard-harian -> core (calls) | 74 |
| dashboard-harian -> core (instantiates) | 54 |
| dashboard-harian -> access-control (protected_by) | 45 |
| dashboard-harian -> dashboard-pinjaman (writes_table) | 26 |
| dashboard-harian -> core (accepts) | 18 |
| dashboard-harian -> dashboard-simpanan (writes_table) | 17 |
| dashboard-harian -> core (writes_table) | 15 |
| import -> dashboard-harian (calls) | 14 |
| core -> dashboard-harian (calls) | 12 |
| dashboard-pinjaman -> dashboard-harian (writes_table) | 12 |
| import -> dashboard-harian (accepts) | 10 |
| dashboard-harian -> core (checks_table) | 10 |
| dashboard-harian -> tests (instantiates) | 9 |
| dashboard-harian -> core (defines_table) | 9 |
| dashboard-harian -> core (reads_table) | 8 |
| dashboard-simpanan -> dashboard-harian (writes_table) | 8 |
| database -> dashboard-harian (checks_table) | 7 |
| jobs-snapshots -> dashboard-harian (calls) | 6 |
| import -> dashboard-harian (writes_table) | 6 |
| database -> dashboard-harian (alters_table) | 5 |
| dashboard-harian -> dashboard-pinjaman (reads_table) | 5 |
| dashboard-harian -> tests (extends) | 5 |
| jobs-snapshots -> dashboard-harian (writes_table) | 4 |
| dashboard-harian -> dashboard-simpanan (calls) | 4 |
| dashboard-harian -> jobs-snapshots (uses_trait) | 4 |
| core -> dashboard-harian (references_route) | 4 |
| dashboard-harian -> core (extends_view) | 4 |
| jobs-snapshots -> dashboard-harian (reads_table) | 3 |
| dashboard-pinjaman -> dashboard-harian (reads_table) | 3 |
| dashboard-harian -> core (joins_table) | 3 |
