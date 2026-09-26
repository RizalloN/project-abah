# Domain: dashboard-harian

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-harian --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 2 |
| file | 22 |
| method | 404 |
| route | 9 |
| class | 16 |
| table | 2 |
| view | 5 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DashboardHarianSnapshotService` | class | 260 | `app/Support/DashboardHarianSnapshotService.php:15` |
| `dashboard_harian_snapshots` | table | 93 | - |
| `DashboardHarianSnapshotServiceTest` | class | 64 | `tests/Unit/DashboardHarianSnapshotServiceTest.php:15` |
| `DashboardHarianController` | class | 58 | `app/Http/Controllers/DashboardHarianController.php:21` |
| `createSourceMetadataTables` | method | 55 | `tests/Unit/DashboardHarianSnapshotServiceTest.php:2967` |
| `HourlyDpkDashboardService` | class | 43 | `app/Support/HourlyDpkDashboardService.php:11` |
| `normalizeDate` | method | 36 | `app/Support/DashboardHarianSnapshotService.php:5126` |
| `fillDashboardHarianSheet` | method | 23 | `app/Http/Controllers/DashboardHarianController.php:605` |
| `normalizeFilterValues` | method | 23 | `app/Support/DashboardHarianSnapshotService.php:5192` |
| `buildKeragaanUkerPayload` | method | 22 | `app/Support/DashboardHarianSnapshotService.php:809` |
| `rebuild` | method | 21 | `app/Support/DashboardHarianSnapshotService.php:274` |
| `syncDuePeriods` | method | 20 | `app/Support/DashboardHarianSnapshotService.php:312` |
| `OptimizedDashboardHarianSnapshotService` | class | 19 | `app/Support/OptimizedDashboardHarianSnapshotServiceV2.php:21` |
| `normalizeKancaLabel` | method | 19 | `app/Support/DashboardHarianSnapshotService.php:4156` |
| `buildAggregatedRowsForPeriod` | method | 18 | `app/Support/DashboardHarianSnapshotService.php:2140` |
| `payload` | method | 18 | `app/Support/HourlyDpkDashboardService.php:38` |
| `buildDashboardPayload` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:1558` |
| `buildPeriodSnapshot` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:473` |
| `resolveEffectivePeriod` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:638` |
| `loadMetricsForPeriods` | method | 16 | `app/Support/DashboardHarianSnapshotService.php:1941` |
| `slugKey` | method | 16 | `app/Support/DashboardHarianSnapshotService.php:4151` |
| `RebuildDashboardHarianSnapshotJob` | class | 15 | `app/Jobs/RebuildDashboardHarianSnapshotJob.php:26` |
| `buildPeriodSnapshotUnlocked` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:509` |
| `fetchLoanAggregates` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:2768` |
| `hourly_dpk` | table | 15 | - |
| `keragaanUker` | method | 15 | `app/Http/Controllers/DashboardHarianController.php:142` |
| `DashboardHarianLdrRkaFormattingTest` | class | 14 | `tests/Unit/DashboardHarianLdrRkaFormattingTest.php:15` |
| `exportExcel` | method | 14 | `app/Http/Controllers/DashboardHarianController.php:226` |
| `finalizeMetrics` | method | 14 | `app/Support/DashboardHarianSnapshotService.php:3756` |
| `index` | method | 14 | `app/Http/Controllers/DashboardHarianController.php:30` |

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
- `DashboardHarianKeragaanUkerViewTest` - `tests/Unit/DashboardHarianKeragaanUkerViewTest.php`
- `DashboardHarianLdrRkaFormattingTest` - `tests/Unit/DashboardHarianLdrRkaFormattingTest.php`
- `DashboardHarianResponsiveViewTest` - `tests/Unit/DashboardHarianResponsiveViewTest.php`
- `DashboardHarianSnapshotDirtyPeriodQueue` - `app/Support/DashboardHarianSnapshotDirtyPeriodQueue.php`
- `DashboardHarianSnapshotDirtyPeriodQueueTest` - `tests/Unit/DashboardHarianSnapshotDirtyPeriodQueueTest.php`
- `DashboardHarianSnapshotLookupIndexMigrationTest` - `tests/Unit/DashboardHarianSnapshotLookupIndexMigrationTest.php`
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
| dashboard-harian -> core (calls) | 79 |
| dashboard-harian -> core (instantiates) | 66 |
| dashboard-harian -> access-control (protected_by) | 45 |
| dashboard-harian -> dashboard-pinjaman (writes_table) | 28 |
| dashboard-harian -> core (accepts) | 23 |
| dashboard-harian -> dashboard-simpanan (writes_table) | 19 |
| import -> dashboard-harian (calls) | 16 |
| dashboard-harian -> core (writes_table) | 14 |
| dashboard-pinjaman -> dashboard-harian (writes_table) | 13 |
| import -> dashboard-harian (accepts) | 10 |
| core -> dashboard-harian (calls) | 10 |
| dashboard-simpanan -> dashboard-harian (writes_table) | 9 |
| dashboard-harian -> import (reads_table) | 8 |
| database -> dashboard-harian (checks_table) | 7 |
| jobs-snapshots -> dashboard-harian (calls) | 7 |
| dashboard-harian -> import (checks_table) | 7 |
| dashboard-harian -> tests (calls) | 7 |
| dashboard-harian -> tests (extends) | 7 |
| dashboard-harian -> platform (calls) | 6 |
| dashboard-harian -> core (defines_table) | 6 |
| import -> dashboard-harian (instantiates) | 6 |
| import -> dashboard-harian (writes_table) | 6 |
| database -> dashboard-harian (alters_table) | 5 |
| dashboard-harian -> dashboard-pinjaman (reads_table) | 5 |
| dashboard-harian -> import (contains) | 5 |
| dashboard-pinjaman -> dashboard-harian (reads_table) | 4 |
| dashboard-harian -> access-control (calls) | 4 |
| import -> dashboard-harian (reads_table) | 4 |
| jobs-snapshots -> dashboard-harian (writes_table) | 4 |
| dashboard-harian -> dashboard-simpanan (calls) | 4 |
