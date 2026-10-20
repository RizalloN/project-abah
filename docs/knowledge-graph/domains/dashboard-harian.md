# Domain: dashboard-harian

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-harian --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 2 |
| file | 18 |
| method | 319 |
| route | 10 |
| class | 11 |
| table | 2 |
| view | 6 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DashboardHarianSnapshotService` | class | 210 | `app/Support/DashboardHarianSnapshotService.php:15` |
| `DashboardHarianController` | class | 51 | `app/Http/Controllers/DashboardHarianController.php:21` |
| `HourlyDpkDashboardService` | class | 40 | `app/Support/HourlyDpkDashboardService.php:11` |
| `normalizeDate` | method | 38 | `app/Support/DashboardHarianSnapshotService.php:5460` |
| `dashboard_harian_snapshots` | table | 27 | - |
| `fillDashboardHarianSheet` | method | 23 | `app/Http/Controllers/DashboardHarianController.php:719` |
| `normalizeFilterValues` | method | 23 | `app/Support/DashboardHarianSnapshotService.php:5526` |
| `rebuild` | method | 23 | `app/Support/DashboardHarianSnapshotService.php:274` |
| `buildKeragaanUkerPayload` | method | 22 | `app/Support/DashboardHarianSnapshotService.php:866` |
| `resolveEffectivePeriod` | method | 22 | `app/Support/DashboardHarianSnapshotService.php:659` |
| `buildPeriodSnapshot` | method | 20 | `app/Support/DashboardHarianSnapshotService.php:487` |
| `normalizeKancaLabel` | method | 20 | `app/Support/DashboardHarianSnapshotService.php:4477` |
| `syncDuePeriods` | method | 20 | `app/Support/DashboardHarianSnapshotService.php:320` |
| `OptimizedDashboardHarianSnapshotService` | class | 19 | `app/Support/OptimizedDashboardHarianSnapshotServiceV2.php:21` |
| `buildAggregatedRowsForPeriod` | method | 18 | `app/Support/DashboardHarianSnapshotService.php:2425` |
| `buildDashboardPayloadWithCustomPeriods` | method | 18 | `app/Support/DashboardHarianSnapshotService.php:1831` |
| `payload` | method | 18 | `app/Support/HourlyDpkDashboardService.php:38` |
| `keragaanUker` | method | 17 | `app/Http/Controllers/DashboardHarianController.php:149` |
| `slugKey` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:4472` |
| `exportExcel` | method | 16 | `app/Http/Controllers/DashboardHarianController.php:256` |
| `loadMetricsForPeriods` | method | 16 | `app/Support/DashboardHarianSnapshotService.php:2226` |
| `__invoke` | method | 15 | `app/Http/Controllers/KeragaanPdfController.php:16` |
| `buildPeriodSnapshotUnlocked` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:529` |
| `buildSourceMetadata` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:5079` |
| `fetchLoanAggregates` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:3053` |
| `finalizeMetrics` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:4077` |
| `index` | method | 15 | `app/Http/Controllers/DashboardHarianController.php:30` |
| `RebuildDashboardHarianSnapshotJob` | class | 14 | `app/Jobs/RebuildDashboardHarianSnapshotJob.php:26` |
| `resolveEffectiveRkaPeriod` | method | 14 | `app/Support/DashboardHarianSnapshotService.php:787` |
| `timeseries` | method | 14 | `app/Http/Controllers/DashboardHarianController.php:68` |

## Route Nodes

- `dashboard.harian` - `dashboard-harian`
- `dashboard.harian.data` - `dashboard-harian/data`
- `dashboard.harian.export` - `dashboard-harian/export`
- `dashboard.harian.keragaan-uker` - `dashboard-harian/keragaan-uker`
- `dashboard.harian.keragaan-uker.data` - `dashboard-harian/keragaan-uker/data`
- `dashboard.harian.keragaan-uker.export-pdf` - `dashboard-harian/keragaan-uker/export-pdf`
- `dashboard.harian.timeseries` - `dashboard-harian/timeseries`
- `dashboard.harian.timeseries.data` - `dashboard-harian/timeseries/data`
- `report.dashboard-dana.hourly-dpk` - `report/dashboard-dana/hourly-dpk`
- `report.dashboard-dana.hourly-dpk.export-pdf` - `report/dashboard-dana/hourly-dpk/export-pdf`

## Class Nodes

- `DashboardHarianController` - `app/Http/Controllers/DashboardHarianController.php`
- `DashboardHarianSnapshotDirtyPeriodQueue` - `app/Support/DashboardHarianSnapshotDirtyPeriodQueue.php`
- `DashboardHarianSnapshotService` - `app/Support/DashboardHarianSnapshotService.php`
- `HourlyDpkDashboardService` - `app/Support/HourlyDpkDashboardService.php`
- `KeragaanPdfController` - `app/Http/Controllers/KeragaanPdfController.php`
- `KeragaanPdfGeography` - `app/Support/KeragaanPdfGeography.php`
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
- `report.dashboard-harian-keragaan-pdf` - `resources/views/report/dashboard-harian-keragaan-pdf.blade.php`
- `report.dashboard-harian-keragaan-uker` - `resources/views/report/dashboard-harian-keragaan-uker.blade.php`
- `report.dashboard-harian-timeseries` - `resources/views/report/dashboard-harian-timeseries.blade.php`

## Table Nodes

- `dashboard_harian_snapshots`
- `hourly_dpk`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| dashboard-harian -> core (calls) | 83 |
| dashboard-harian -> access-control (protected_by) | 60 |
| dashboard-harian -> core (accepts) | 23 |
| import -> dashboard-harian (calls) | 12 |
| import -> dashboard-harian (accepts) | 10 |
| core -> dashboard-harian (calls) | 10 |
| jobs-snapshots -> dashboard-harian (calls) | 9 |
| dashboard-harian -> import (checks_table) | 8 |
| database -> dashboard-harian (checks_table) | 7 |
| dashboard-harian -> core (instantiates) | 7 |
| dashboard-harian -> access-control (calls) | 6 |
| dashboard-harian -> import (reads_table) | 6 |
| database -> dashboard-harian (alters_table) | 5 |
| dashboard-harian -> platform (calls) | 5 |
| dashboard-harian -> dashboard-pinjaman (reads_table) | 5 |
| jobs-snapshots -> dashboard-harian (writes_table) | 4 |
| dashboard-harian -> core (extends) | 4 |
| dashboard-harian -> jobs-snapshots (uses_trait) | 4 |
| platform -> dashboard-harian (references_route) | 4 |
| dashboard-harian -> platform (extends_view) | 4 |
| jobs-snapshots -> dashboard-harian (injects) | 3 |
| dashboard-pinjaman -> dashboard-harian (reads_table) | 3 |
| jobs-snapshots -> dashboard-harian (accepts) | 3 |
| dashboard-harian -> core (checks_table) | 3 |
| dashboard-harian -> jobs-snapshots (uses_queue) | 2 |
| jobs-snapshots -> dashboard-harian (reads_table) | 2 |
| dashboard-simpanan -> dashboard-harian (references_route) | 2 |
| dashboard-harian -> dashboard-pinjaman (checks_table) | 2 |
| core -> dashboard-harian (injects) | 2 |
| dashboard-simpanan -> dashboard-harian (contains) | 2 |
