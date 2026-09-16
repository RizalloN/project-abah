# Domain: dashboard-harian

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-harian --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 2 |
| file | 21 |
| method | 387 |
| unresolved_symbol | 1 |
| route | 9 |
| class | 15 |
| table | 2 |
| view | 5 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DashboardHarianSnapshotService` | class | 254 | `app/Support/DashboardHarianSnapshotService.php:15` |
| `dashboard_harian_snapshots` | table | 87 | - |
| `DashboardHarianSnapshotServiceTest` | class | 61 | `tests/Unit/DashboardHarianSnapshotServiceTest.php:15` |
| `DashboardHarianController` | class | 55 | `app/Http/Controllers/DashboardHarianController.php:20` |
| `createSourceMetadataTables` | method | 52 | `tests/Unit/DashboardHarianSnapshotServiceTest.php:2742` |
| `HourlyDpkDashboardService` | class | 43 | `app/Support/HourlyDpkDashboardService.php:11` |
| `normalizeDate` | method | 35 | `app/Support/DashboardHarianSnapshotService.php:5024` |
| `fillDashboardHarianSheet` | method | 23 | `app/Http/Controllers/DashboardHarianController.php:605` |
| `buildKeragaanUkerPayload` | method | 22 | `app/Support/DashboardHarianSnapshotService.php:803` |
| `normalizeFilterValues` | method | 22 | `app/Support/DashboardHarianSnapshotService.php:5090` |
| `rebuild` | method | 21 | `app/Support/DashboardHarianSnapshotService.php:268` |
| `syncDuePeriods` | method | 20 | `app/Support/DashboardHarianSnapshotService.php:306` |
| `OptimizedDashboardHarianSnapshotService` | class | 19 | `app/Support/OptimizedDashboardHarianSnapshotServiceV2.php:21` |
| `buildAggregatedRowsForPeriod` | method | 18 | `app/Support/DashboardHarianSnapshotService.php:2046` |
| `normalizeKancaLabel` | method | 18 | `app/Support/DashboardHarianSnapshotService.php:4054` |
| `payload` | method | 18 | `app/Support/HourlyDpkDashboardService.php:38` |
| `buildDashboardPayload` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:1552` |
| `buildPeriodSnapshot` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:467` |
| `resolveEffectivePeriod` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:632` |
| `RebuildDashboardHarianSnapshotJob` | class | 15 | `app/Jobs/RebuildDashboardHarianSnapshotJob.php:26` |
| `buildPeriodSnapshotUnlocked` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:503` |
| `fetchLoanAggregates` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:2674` |
| `hourly_dpk` | table | 15 | - |
| `keragaanUker` | method | 15 | `app/Http/Controllers/DashboardHarianController.php:147` |
| `loadMetricsForPeriods` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:1928` |
| `slugKey` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:4049` |
| `DashboardHarianLdrRkaFormattingTest` | class | 14 | `tests/Unit/DashboardHarianLdrRkaFormattingTest.php:15` |
| `exportExcel` | method | 14 | `app/Http/Controllers/DashboardHarianController.php:231` |
| `finalizeMetrics` | method | 14 | `app/Support/DashboardHarianSnapshotService.php:3662` |
| `index` | method | 14 | `app/Http/Controllers/DashboardHarianController.php:29` |

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
| dashboard-harian -> core (calls) | 78 |
| dashboard-harian -> core (instantiates) | 63 |
| dashboard-harian -> access-control (protected_by) | 45 |
| dashboard-harian -> dashboard-pinjaman (writes_table) | 29 |
| dashboard-harian -> core (accepts) | 20 |
| dashboard-harian -> dashboard-simpanan (writes_table) | 20 |
| dashboard-harian -> core (writes_table) | 18 |
| import -> dashboard-harian (calls) | 14 |
| dashboard-pinjaman -> dashboard-harian (writes_table) | 13 |
| core -> dashboard-harian (calls) | 12 |
| dashboard-harian -> core (defines_table) | 11 |
| import -> dashboard-harian (accepts) | 10 |
| dashboard-harian -> core (checks_table) | 10 |
| dashboard-harian -> tests (instantiates) | 9 |
| dashboard-simpanan -> dashboard-harian (writes_table) | 9 |
| dashboard-harian -> core (reads_table) | 8 |
| database -> dashboard-harian (checks_table) | 7 |
| jobs-snapshots -> dashboard-harian (calls) | 6 |
| import -> dashboard-harian (writes_table) | 6 |
| dashboard-harian -> tests (extends) | 6 |
| database -> dashboard-harian (alters_table) | 5 |
| dashboard-harian -> dashboard-pinjaman (reads_table) | 5 |
| jobs-snapshots -> dashboard-harian (writes_table) | 4 |
| dashboard-harian -> dashboard-simpanan (calls) | 4 |
| dashboard-harian -> jobs-snapshots (uses_trait) | 4 |
| core -> dashboard-harian (references_route) | 4 |
| dashboard-harian -> core (extends_view) | 4 |
| jobs-snapshots -> dashboard-harian (reads_table) | 3 |
| dashboard-pinjaman -> dashboard-harian (reads_table) | 3 |
| import -> dashboard-harian (reads_table) | 3 |
