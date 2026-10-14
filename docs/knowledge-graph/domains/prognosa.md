# Domain: prognosa

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=prognosa --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 4 |
| method | 134 |
| route | 1 |
| class | 3 |
| view | 1 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `PrognosaWeeklyController` | class | 65 | `app/Http/Controllers/PrognosaWeeklyController.php:20` |
| `PresentationPrognosaWeeklyService` | class | 57 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:20` |
| `LandingPrognosaCardService` | class | 20 | `app/Support/LandingPrognosaCardService.php:9` |
| `buildDashboardAlignedRows` | method | 19 | `app/Http/Controllers/PrognosaWeeklyController.php:831` |
| `cleanCell` | method | 14 | `app/Http/Controllers/PrognosaWeeklyController.php:581` |
| `buildDashboardAlignedPayload` | method | 13 | `app/Http/Controllers/PrognosaWeeklyController.php:686` |
| `index` | method | 12 | `app/Http/Controllers/PrognosaWeeklyController.php:137` |
| `parseMatrices` | method | 12 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:342` |
| `dateFromCell` | method | 11 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1462` |
| `decorateCard` | method | 11 | `app/Support/LandingPrognosaCardService.php:186` |
| `fetchSheet` | method | 11 | `app/Http/Controllers/PrognosaWeeklyController.php:219` |
| `parseLegacySpreadsheet` | method | 11 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1124` |
| `localFallbackPayload` | method | 10 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:240` |
| `normaliseLabel` | method | 10 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1535` |
| `parseWorksheetMatrix` | method | 10 | `app/Http/Controllers/PrognosaWeeklyController.php:400` |
| `resolvePositionColumns` | method | 10 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1190` |
| `matrixFromCsvResponse` | method | 9 | `app/Http/Controllers/PrognosaWeeklyController.php:305` |
| `metricRows` | method | 9 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1292` |
| `prognosa.weekly` | route | 9 | - |
| `resolveCsvLayout` | method | 9 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:437` |
| `consolidateAreaRows` | method | 8 | `app/Http/Controllers/PrognosaWeeklyController.php:1349` |
| `csvMetricRows` | method | 8 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:629` |
| `dateFromText` | method | 8 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1473` |
| `downloadCsvAndParse` | method | 8 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:116` |
| `findRows` | method | 8 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1372` |
| `formatIndonesianDate` | method | 8 | `app/Http/Controllers/PrognosaWeeklyController.php:1658` |
| `formattedCell` | method | 8 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1528` |
| `normalizeSourceMatrix` | method | 8 | `app/Http/Controllers/PrognosaWeeklyController.php:474` |
| `structuredWorksheetMatrix` | method | 8 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1091` |
| `worksheetDisplayValue` | method | 8 | `app/Http/Controllers/PrognosaWeeklyController.php:422` |

## Route Nodes

- `prognosa.weekly` - `prognosa/weekly/{sheet?}`

## Class Nodes

- `LandingPrognosaCardService` - `app/Support/LandingPrognosaCardService.php`
- `PresentationPrognosaWeeklyService` - `app/Services/Presentation/PresentationPrognosaWeeklyService.php`
- `PrognosaWeeklyController` - `app/Http/Controllers/PrognosaWeeklyController.php`

## View Nodes

- `report.prognosa-weekly` - `resources/views/report/prognosa-weekly.blade.php`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| prognosa -> core (calls) | 61 |
| prognosa -> core (accepts) | 28 |
| prognosa -> core (instantiates) | 25 |
| prognosa -> access-control (protected_by) | 5 |
| prognosa -> access-control (calls) | 2 |
| prognosa -> dashboard-harian (injects) | 1 |
| prognosa -> core (extends) | 1 |
| platform -> prognosa (references_route) | 1 |
| prognosa -> platform (extends_view) | 1 |
