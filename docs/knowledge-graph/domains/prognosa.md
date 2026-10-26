# Domain: prognosa

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=prognosa --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 5 |
| method | 142 |
| route | 2 |
| class | 4 |
| view | 1 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `PrognosaWeeklyController` | class | 72 | `app/Http/Controllers/PrognosaWeeklyController.php:25` |
| `PresentationPrognosaWeeklyService` | class | 57 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:20` |
| `LandingPrognosaCardService` | class | 20 | `app/Support/LandingPrognosaCardService.php:9` |
| `buildDashboardAlignedRows` | method | 19 | `app/Http/Controllers/PrognosaWeeklyController.php:1157` |
| `fillExportSheet` | method | 16 | `app/Http/Controllers/PrognosaWeeklyController.php:926` |
| `buildDashboardAlignedPayload` | method | 14 | `app/Http/Controllers/PrognosaWeeklyController.php:804` |
| `cleanCell` | method | 14 | `app/Http/Controllers/PrognosaWeeklyController.php:699` |
| `exportExcel` | method | 13 | `app/Http/Controllers/PrognosaWeeklyController.php:188` |
| `parseMatrices` | method | 12 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:342` |
| `dateFromCell` | method | 11 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1462` |
| `decorateCard` | method | 11 | `app/Support/LandingPrognosaCardService.php:186` |
| `fetchSheet` | method | 11 | `app/Http/Controllers/PrognosaWeeklyController.php:337` |
| `parseLegacySpreadsheet` | method | 11 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1124` |
| `prognosa.weekly` | route | 11 | - |
| `resolveAlignedPayload` | method | 11 | `app/Http/Controllers/PrognosaWeeklyController.php:222` |
| `index` | method | 10 | `app/Http/Controllers/PrognosaWeeklyController.php:152` |
| `localFallbackPayload` | method | 10 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:240` |
| `normaliseLabel` | method | 10 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1535` |
| `parseWorksheetMatrix` | method | 10 | `app/Http/Controllers/PrognosaWeeklyController.php:518` |
| `resolvePositionColumns` | method | 10 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1190` |
| `formatIndonesianDate` | method | 9 | `app/Http/Controllers/PrognosaWeeklyController.php:1986` |
| `matrixFromCsvResponse` | method | 9 | `app/Http/Controllers/PrognosaWeeklyController.php:423` |
| `metricRows` | method | 9 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1292` |
| `prognosa.weekly.export` | route | 9 | - |
| `resolveCsvLayout` | method | 9 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:437` |
| `consolidateAreaRows` | method | 8 | `app/Http/Controllers/PrognosaWeeklyController.php:1677` |
| `csvMetricRows` | method | 8 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:629` |
| `dateFromText` | method | 8 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1473` |
| `downloadCsvAndParse` | method | 8 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:116` |
| `findRows` | method | 8 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1372` |

## Route Nodes

- `prognosa.weekly` - `prognosa/weekly/{sheet?}`
- `prognosa.weekly.export` - `prognosa/weekly-export`

## Class Nodes

- `LandingPrognosaCardService` - `app/Support/LandingPrognosaCardService.php`
- `PresentationPrognosaWeeklyService` - `app/Services/Presentation/PresentationPrognosaWeeklyService.php`
- `PrognosaCalendarWeek` - `app/Support/PrognosaCalendarWeek.php`
- `PrognosaWeeklyController` - `app/Http/Controllers/PrognosaWeeklyController.php`

## View Nodes

- `report.prognosa-weekly` - `resources/views/report/prognosa-weekly.blade.php`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| prognosa -> core (calls) | 79 |
| prognosa -> core (accepts) | 34 |
| prognosa -> core (instantiates) | 28 |
| prognosa -> access-control (protected_by) | 12 |
| prognosa -> access-control (calls) | 2 |
| prognosa -> dashboard-harian (injects) | 1 |
| prognosa -> dashboard-harian (accepts) | 1 |
| prognosa -> dashboard-harian (calls) | 1 |
| prognosa -> core (extends) | 1 |
| platform -> prognosa (references_route) | 1 |
| prognosa -> platform (extends_view) | 1 |
