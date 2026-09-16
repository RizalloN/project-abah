# Domain: marketshare

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=marketshare --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 1 |
| file | 12 |
| method | 100 |
| route | 4 |
| class | 10 |
| table | 1 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `CrasMappingService` | class | 26 | `app/Support/CrasMappingService.php:11` |
| `CrasLpgPortfolioService` | class | 20 | `app/Support/CrasLpgPortfolioService.php:9` |
| `CrasSourceServiceTest` | class | 15 | `tests/Unit/CrasSourceServiceTest.php:12` |
| `payload` | method | 13 | `app/Support/CrasLpgPortfolioService.php:43` |
| `handle` | method | 12 | `app/Console/Commands/SyncCrasLpgReferenceCommand.php:18` |
| `payload` | method | 12 | `app/Support/CrasMappingService.php:70` |
| `test_mapping_refresh_atomically_replaces_cache_only_with_valid_workbook` | method | 12 | `tests/Unit/RemoteDashboardSourceTest.php:55` |
| `SyncCrasLpgReferenceCommand` | class | 11 | `app/Console/Commands/SyncCrasLpgReferenceCommand.php:10` |
| `payload` | method | 11 | `app/Support/MarketShareSektoralReport.php:101` |
| `CrasLpgReference` | class | 10 | `app/Support/CrasLpgReference.php:8` |
| `MarketShareSektoralReport` | class | 10 | `app/Support/MarketShareSektoralReport.php:5` |
| `mappedRows` | method | 10 | `app/Support/CrasLpgPortfolioService.php:119` |
| `aggregateInPhp` | method | 9 | `app/Support/CrasLpgPortfolioService.php:207` |
| `aggregateUnits` | method | 9 | `app/Support/CrasMappingService.php:273` |
| `normalize` | method | 9 | `app/Support/CrasLpgReference.php:56` |
| `payload` | method | 9 | `app/Support/MarketShareArea6Report.php:119` |
| `aggregateUnitsInPhp` | method | 8 | `app/Support/CrasMappingService.php:350` |
| `createXlsx` | method | 8 | `tests/Unit/CrasSourceServiceTest.php:180` |
| `validRow` | method | 8 | `tests/Unit/CrasSourceServiceTest.php:152` |
| `MarketShareArea6Report` | class | 7 | `app/Support/MarketShareArea6Report.php:5` |
| `aggregateUnitsWithSql` | method | 7 | `app/Support/CrasMappingService.php:324` |
| `createUtf16Tsv` | method | 7 | `tests/Unit/CrasSourceServiceTest.php:164` |
| `test_mapping_request_queues_refresh_without_calling_google` | method | 7 | `tests/Unit/RemoteDashboardSourceTest.php:43` |
| `CrasReportManagementTest` | class | 6 | `tests/Unit/CrasReportManagementTest.php:10` |
| `MarketShareSektoralReportTest` | class | 6 | `tests/Unit/MarketShareSektoralReportTest.php:8` |
| `applyFilters` | method | 6 | `app/Support/CrasMappingService.php:431` |
| `applyRegionFilter` | method | 6 | `app/Support/CrasMappingService.php:441` |
| `insights` | method | 6 | `app/Support/MarketShareArea6Report.php:156` |
| `percentage` | method | 6 | `app/Support/CrasLpgPortfolioService.php:415` |
| `public-workbooks.market-share-mapping.token` | route | 6 | - |

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
- `SyncCrasLpgReferenceCommand` - `app/Console/Commands/SyncCrasLpgReferenceCommand.php`

## Command Nodes

- `cras-lpg:sync-reference` - `app/Console/Commands/SyncCrasLpgReferenceCommand.php`

## Table Nodes

- `cras`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| marketshare -> core (calls) | 22 |
| marketshare -> core (instantiates) | 13 |
| marketshare -> core (accepts) | 8 |
| marketshare -> access-control (protected_by) | 8 |
| tests -> marketshare (contains) | 8 |
| dashboard-simpanan -> marketshare (calls) | 6 |
| marketshare -> import (instantiates) | 6 |
| access-control -> marketshare (references_route) | 4 |
| marketshare -> bank-pipeline (dispatches_to) | 4 |
| marketshare -> dashboard-simpanan (references_route) | 3 |
| marketshare -> core (writes_table) | 3 |
| marketshare -> tests (extends) | 3 |
| presentation -> marketshare (contains) | 3 |
| dashboard-simpanan -> marketshare (references_route) | 2 |
| marketshare -> access-control (calls) | 2 |
| marketshare -> presentation (instantiates) | 2 |
| marketshare -> dashboard-simpanan (instantiates) | 2 |
| marketshare -> core (extends) | 2 |
| marketshare -> core (defines_table) | 1 |
| marketshare -> core (reads_table) | 1 |
| marketshare -> jobs-snapshots (calls) | 1 |
| marketshare -> dashboard-pinjaman (contains) | 1 |
