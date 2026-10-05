# Domain: marketshare

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=marketshare --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 1 |
| file | 13 |
| method | 111 |
| route | 4 |
| class | 11 |
| table | 1 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `CrasMappingService` | class | 30 | `app/Support/CrasMappingService.php:11` |
| `CrasLpgPortfolioService` | class | 20 | `app/Support/CrasLpgPortfolioService.php:9` |
| `CrasSourceServiceTest` | class | 15 | `tests/Unit/CrasSourceServiceTest.php:12` |
| `payload` | method | 13 | `app/Support/CrasLpgPortfolioService.php:43` |
| `payload` | method | 13 | `app/Support/CrasMappingService.php:92` |
| `handle` | method | 12 | `app/Console/Commands/SyncCrasLpgReferenceCommand.php:18` |
| `test_mapping_refresh_atomically_replaces_cache_only_with_valid_workbook` | method | 12 | `tests/Unit/RemoteDashboardSourceTest.php:55` |
| `SyncCrasLpgReferenceCommand` | class | 11 | `app/Console/Commands/SyncCrasLpgReferenceCommand.php:10` |
| `payload` | method | 11 | `app/Support/MarketShareSektoralReport.php:101` |
| `CrasLpgReference` | class | 10 | `app/Support/CrasLpgReference.php:8` |
| `MarketShareSektoralReport` | class | 10 | `app/Support/MarketShareSektoralReport.php:5` |
| `mappedRows` | method | 10 | `app/Support/CrasLpgPortfolioService.php:119` |
| `movementSeries` | method | 10 | `app/Support/CrasMappingService.php:182` |
| `aggregateInPhp` | method | 9 | `app/Support/CrasLpgPortfolioService.php:207` |
| `aggregateUnits` | method | 9 | `app/Support/CrasMappingService.php:493` |
| `normalize` | method | 9 | `app/Support/CrasLpgReference.php:56` |
| `payload` | method | 9 | `app/Support/MarketShareArea6Report.php:119` |
| `QueueWorkerCrashBackoffTest` | class | 8 | `tests/Unit/QueueWorkerCrashBackoffTest.php:11` |
| `aggregateUnitsInPhp` | method | 8 | `app/Support/CrasMappingService.php:570` |
| `createXlsx` | method | 8 | `tests/Unit/CrasSourceServiceTest.php:180` |
| `validRow` | method | 8 | `tests/Unit/CrasSourceServiceTest.php:152` |
| `MarketShareArea6Report` | class | 7 | `app/Support/MarketShareArea6Report.php:5` |
| `aggregateUnitsWithSql` | method | 7 | `app/Support/CrasMappingService.php:544` |
| `applyRegionFilter` | method | 7 | `app/Support/CrasMappingService.php:661` |
| `createUtf16Tsv` | method | 7 | `tests/Unit/CrasSourceServiceTest.php:164` |
| `reapUntilExited` | method | 7 | `tests/Unit/QueueWorkerCrashBackoffTest.php:82` |
| `test_backoff_is_reset_only_after_child_is_observed_running_past_startup_window` | method | 7 | `tests/Unit/QueueWorkerCrashBackoffTest.php:45` |
| `test_mapping_request_queues_refresh_without_calling_google` | method | 7 | `tests/Unit/RemoteDashboardSourceTest.php:43` |
| `CrasReportManagementTest` | class | 6 | `tests/Unit/CrasReportManagementTest.php:10` |
| `MarketShareSektoralReportTest` | class | 6 | `tests/Unit/MarketShareSektoralReportTest.php:8` |

## Route Nodes

- `public-workbooks.market-share` - `workbooks/market-share.xlsx`
- `public-workbooks.market-share-mapping` - `workbooks/market-share-mapping.xlsx`
- `public-workbooks.market-share-mapping.token` - `workbooks/market-share-mapping/{token}/market-share-mapping.xlsx`
- `public-workbooks.market-share.token` - `workbooks/market-share/{token}/market-share.xlsx`

## Class Nodes

- `CrasLpgPortfolioService` - `app/Support/CrasLpgPortfolioService.php`
- `CrasLpgReference` - `app/Support/CrasLpgReference.php`
- `CrasMappingService` - `app/Support/CrasMappingService.php`
- `CrasReportManagementTest` - `tests/Unit/CrasReportManagementTest.php`
- `CrasSourceServiceTest` - `tests/Unit/CrasSourceServiceTest.php`
- `MarketShareArea6Report` - `app/Support/MarketShareArea6Report.php`
- `MarketShareArea6ReportTest` - `tests/Unit/MarketShareArea6ReportTest.php`
- `MarketShareSektoralReport` - `app/Support/MarketShareSektoralReport.php`
- `MarketShareSektoralReportTest` - `tests/Unit/MarketShareSektoralReportTest.php`
- `QueueWorkerCrashBackoffTest` - `tests/Unit/QueueWorkerCrashBackoffTest.php`
- `SyncCrasLpgReferenceCommand` - `app/Console/Commands/SyncCrasLpgReferenceCommand.php`

## Command Nodes

- `cras-lpg:sync-reference` - `app/Console/Commands/SyncCrasLpgReferenceCommand.php`

## Table Nodes

- `cras`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| marketshare -> core (calls) | 24 |
| marketshare -> core (instantiates) | 19 |
| marketshare -> core (accepts) | 10 |
| marketshare -> access-control (protected_by) | 8 |
| tests -> marketshare (contains) | 8 |
| dashboard-simpanan -> marketshare (calls) | 6 |
| marketshare -> import (instantiates) | 6 |
| access-control -> marketshare (references_route) | 4 |
| marketshare -> tests (extends) | 4 |
| marketshare -> dashboard-simpanan (references_route) | 3 |
| marketshare -> core (writes_table) | 3 |
| presentation -> marketshare (contains) | 3 |
| dashboard-simpanan -> marketshare (references_route) | 2 |
| marketshare -> bank-pipeline (calls) | 2 |
| marketshare -> access-control (calls) | 2 |
| marketshare -> presentation (instantiates) | 2 |
| marketshare -> jobs-snapshots (accepts) | 2 |
| marketshare -> jobs-snapshots (instantiates) | 2 |
| marketshare -> dashboard-simpanan (instantiates) | 2 |
| marketshare -> core (extends) | 2 |
| bank-pipeline -> marketshare (contains) | 2 |
| marketshare -> access-control (defines_table) | 1 |
| marketshare -> core (reads_table) | 1 |
| marketshare -> tests (calls) | 1 |
| marketshare -> jobs-snapshots (calls) | 1 |
| marketshare -> dashboard-pinjaman (contains) | 1 |
