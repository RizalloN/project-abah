# Domain: dashboard-harian

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=dashboard-harian --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 2 |
| file | 28 |
| method | 445 |
| route | 10 |
| class | 21 |
| table | 2 |
| view | 6 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DashboardHarianSnapshotService` | class | 272 | `app/Support/DashboardHarianSnapshotService.php:15` |
| `dashboard_harian_snapshots` | table | 95 | - |
| `DashboardHarianSnapshotServiceTest` | class | 64 | `tests/Unit/DashboardHarianSnapshotServiceTest.php:15` |
| `DashboardHarianController` | class | 60 | `app/Http/Controllers/DashboardHarianController.php:21` |
| `createSourceMetadataTables` | method | 55 | `tests/Unit/DashboardHarianSnapshotServiceTest.php:2991` |
| `HourlyDpkDashboardService` | class | 43 | `app/Support/HourlyDpkDashboardService.php:11` |
| `normalizeDate` | method | 36 | `app/Support/DashboardHarianSnapshotService.php:5333` |
| `fillDashboardHarianSheet` | method | 23 | `app/Http/Controllers/DashboardHarianController.php:615` |
| `normalizeFilterValues` | method | 23 | `app/Support/DashboardHarianSnapshotService.php:5399` |
| `buildKeragaanUkerPayload` | method | 22 | `app/Support/DashboardHarianSnapshotService.php:809` |
| `rebuild` | method | 21 | `app/Support/DashboardHarianSnapshotService.php:274` |
| `normalizeKancaLabel` | method | 20 | `app/Support/DashboardHarianSnapshotService.php:4363` |
| `syncDuePeriods` | method | 20 | `app/Support/DashboardHarianSnapshotService.php:312` |
| `OptimizedDashboardHarianSnapshotService` | class | 19 | `app/Support/OptimizedDashboardHarianSnapshotServiceV2.php:21` |
| `buildAggregatedRowsForPeriod` | method | 18 | `app/Support/DashboardHarianSnapshotService.php:2347` |
| `payload` | method | 18 | `app/Support/HourlyDpkDashboardService.php:38` |
| `resolveEffectivePeriod` | method | 18 | `app/Support/DashboardHarianSnapshotService.php:638` |
| `buildDashboardPayload` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:1765` |
| `buildPeriodSnapshot` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:473` |
| `slugKey` | method | 17 | `app/Support/DashboardHarianSnapshotService.php:4358` |
| `DashboardHarianKeragaanPdfPayloadTest` | class | 16 | `tests/Unit/DashboardHarianKeragaanPdfPayloadTest.php:13` |
| `keragaanUker` | method | 16 | `app/Http/Controllers/DashboardHarianController.php:148` |
| `loadMetricsForPeriods` | method | 16 | `app/Support/DashboardHarianSnapshotService.php:2148` |
| `RebuildDashboardHarianSnapshotJob` | class | 15 | `app/Jobs/RebuildDashboardHarianSnapshotJob.php:26` |
| `__invoke` | method | 15 | `app/Http/Controllers/KeragaanPdfController.php:16` |
| `buildPeriodSnapshotUnlocked` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:509` |
| `fetchLoanAggregates` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:2975` |
| `finalizeMetrics` | method | 15 | `app/Support/DashboardHarianSnapshotService.php:3963` |
| `hourly_dpk` | table | 15 | - |
| `DashboardHarianLdrRkaFormattingTest` | class | 14 | `tests/Unit/DashboardHarianLdrRkaFormattingTest.php:15` |

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
- `DashboardHarianKeragaanPdfPayloadTest` - `tests/Unit/DashboardHarianKeragaanPdfPayloadTest.php`
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
- `KeragaanPdfController` - `app/Http/Controllers/KeragaanPdfController.php`
- `KeragaanPdfExportTest` - `tests/Feature/KeragaanPdfExportTest.php`
- `KeragaanPdfGeography` - `app/Support/KeragaanPdfGeography.php`
- `KeragaanPdfGeographyTest` - `tests/Unit/KeragaanPdfGeographyTest.php`
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
| dashboard-harian -> core (calls) | 89 |
| dashboard-harian -> core (instantiates) | 71 |
| dashboard-harian -> access-control (protected_by) | 50 |
| dashboard-harian -> dashboard-pinjaman (writes_table) | 28 |
| dashboard-harian -> core (accepts) | 24 |
| dashboard-harian -> dashboard-simpanan (writes_table) | 19 |
| import -> dashboard-harian (calls) | 16 |
| dashboard-harian -> core (writes_table) | 15 |
| dashboard-pinjaman -> dashboard-harian (writes_table) | 13 |
| dashboard-harian -> tests (calls) | 12 |
| import -> dashboard-harian (accepts) | 10 |
| core -> dashboard-harian (calls) | 10 |
| dashboard-simpanan -> dashboard-harian (writes_table) | 10 |
| dashboard-harian -> tests (extends) | 10 |
| dashboard-harian -> import (reads_table) | 8 |
| database -> dashboard-harian (checks_table) | 7 |
| jobs-snapshots -> dashboard-harian (calls) | 7 |
| dashboard-harian -> access-control (calls) | 7 |
| dashboard-harian -> import (checks_table) | 7 |
| dashboard-harian -> core (defines_table) | 7 |
| dashboard-harian -> platform (calls) | 6 |
| import -> dashboard-harian (instantiates) | 6 |
| import -> dashboard-harian (writes_table) | 6 |
| database -> dashboard-harian (alters_table) | 5 |
| dashboard-harian -> dashboard-pinjaman (reads_table) | 5 |
| dashboard-harian -> import (contains) | 5 |
| tests -> dashboard-harian (contains) | 5 |
| dashboard-pinjaman -> dashboard-harian (reads_table) | 4 |
| import -> dashboard-harian (reads_table) | 4 |
| jobs-snapshots -> dashboard-harian (writes_table) | 4 |
