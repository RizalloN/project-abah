# Domain: marketshare

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=marketshare --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 1 |
| file | 7 |
| method | 79 |
| route | 4 |
| class | 6 |
| table | 1 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `CrasMappingService` | class | 31 | `app/Support/CrasMappingService.php:11` |
| `CrasLpgPortfolioService` | class | 20 | `app/Support/CrasLpgPortfolioService.php:9` |
| `payload` | method | 15 | `app/Support/CrasMappingService.php:92` |
| `payload` | method | 13 | `app/Support/CrasLpgPortfolioService.php:43` |
| `handle` | method | 12 | `app/Console/Commands/SyncCrasLpgReferenceCommand.php:18` |
| `SyncCrasLpgReferenceCommand` | class | 11 | `app/Console/Commands/SyncCrasLpgReferenceCommand.php:10` |
| `CrasLpgReference` | class | 10 | `app/Support/CrasLpgReference.php:8` |
| `MarketShareSektoralReport` | class | 10 | `app/Support/MarketShareSektoralReport.php:5` |
| `mappedRows` | method | 10 | `app/Support/CrasLpgPortfolioService.php:119` |
| `movementSeries` | method | 10 | `app/Support/CrasMappingService.php:261` |
| `aggregateInPhp` | method | 9 | `app/Support/CrasLpgPortfolioService.php:207` |
| `aggregateUnits` | method | 9 | `app/Support/CrasMappingService.php:572` |
| `normalize` | method | 9 | `app/Support/CrasLpgReference.php:56` |
| `aggregateUnitsInPhp` | method | 8 | `app/Support/CrasMappingService.php:649` |
| `payload` | method | 8 | `app/Support/MarketShareArea6Report.php:119` |
| `payload` | method | 8 | `app/Support/MarketShareSektoralReport.php:101` |
| `MarketShareArea6Report` | class | 7 | `app/Support/MarketShareArea6Report.php:5` |
| `aggregateUnitsWithSql` | method | 7 | `app/Support/CrasMappingService.php:623` |
| `applyFilters` | method | 7 | `app/Support/CrasMappingService.php:730` |
| `applyRegionFilter` | method | 7 | `app/Support/CrasMappingService.php:740` |
| `parentComparisonGroup` | method | 7 | `app/Support/CrasMappingService.php:216` |
| `insights` | method | 6 | `app/Support/MarketShareArea6Report.php:156` |
| `marketShare` | method | 6 | `app/Http/Controllers/PublicWorkbookController.php:16` |
| `marketShareMapping` | method | 6 | `app/Http/Controllers/PublicWorkbookController.php:27` |
| `percentage` | method | 6 | `app/Support/CrasLpgPortfolioService.php:415` |
| `resolve` | method | 6 | `app/Support/CrasLpgReference.php:29` |
| `aggregateWithSql` | method | 5 | `app/Support/CrasLpgPortfolioService.php:183` |
| `applyRegionFilter` | method | 5 | `app/Support/CrasLpgPortfolioService.php:432` |
| `data` | method | 5 | `app/Support/CrasLpgReference.php:72` |
| `decorateRow` | method | 5 | `app/Support/MarketShareSektoralReport.php:217` |

## Route Nodes

- `public-workbooks.market-share` - `workbooks/market-share.xlsx`
- `public-workbooks.market-share-mapping` - `workbooks/market-share-mapping.xlsx`
- `public-workbooks.market-share-mapping.token` - `workbooks/market-share-mapping/{token}/market-share-mapping.xlsx`
- `public-workbooks.market-share.token` - `workbooks/market-share/{token}/market-share.xlsx`

## Class Nodes

- `CrasLpgPortfolioService` - `app/Support/CrasLpgPortfolioService.php`
- `CrasLpgReference` - `app/Support/CrasLpgReference.php`
- `CrasMappingService` - `app/Support/CrasMappingService.php`
- `MarketShareArea6Report` - `app/Support/MarketShareArea6Report.php`
- `MarketShareSektoralReport` - `app/Support/MarketShareSektoralReport.php`
- `SyncCrasLpgReferenceCommand` - `app/Console/Commands/SyncCrasLpgReferenceCommand.php`

## Command Nodes

- `cras-lpg:sync-reference` - `app/Console/Commands/SyncCrasLpgReferenceCommand.php`

## Table Nodes

- `cras`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| marketshare -> core (calls) | 14 |
| marketshare -> core (accepts) | 11 |
| dashboard-simpanan -> marketshare (calls) | 9 |
| marketshare -> access-control (protected_by) | 8 |
| dashboard-simpanan -> marketshare (references_route) | 2 |
| marketshare -> bank-pipeline (calls) | 2 |
| marketshare -> access-control (calls) | 2 |
| core -> marketshare (reads_table) | 2 |
| bank-pipeline -> marketshare (contains) | 2 |
| presentation -> marketshare (contains) | 2 |
| marketshare -> presentation (calls) | 1 |
| core -> marketshare (checks_table) | 1 |
| marketshare -> core (extends) | 1 |
