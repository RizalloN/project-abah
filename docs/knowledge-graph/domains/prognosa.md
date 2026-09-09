# Domain: prognosa

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=prognosa --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 6 |
| method | 172 |
| route | 1 |
| class | 5 |
| view | 1 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `PrognosaWeeklyController` | class | 67 | `app/Http/Controllers/PrognosaWeeklyController.php:20` |
| `PresentationPrognosaWeeklyService` | class | 57 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:20` |
| `PrognosaWeeklySpreadsheetTest` | class | 29 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:19` |
| `LandingPrognosaCardService` | class | 26 | `app/Support/LandingPrognosaCardService.php:7` |
| `buildDashboardAlignedRows` | method | 19 | `app/Http/Controllers/PrognosaWeeklyController.php:831` |
| `LandingPrognosaCardServiceTest` | class | 14 | `tests/Unit/LandingPrognosaCardServiceTest.php:11` |
| `cleanCell` | method | 14 | `app/Http/Controllers/PrognosaWeeklyController.php:581` |
| `buildDashboardAlignedPayload` | method | 13 | `app/Http/Controllers/PrognosaWeeklyController.php:686` |
| `weeklyController` | method | 13 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:592` |
| `index` | method | 12 | `app/Http/Controllers/PrognosaWeeklyController.php:137` |
| `parseMatrices` | method | 12 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:336` |
| `test_weekly_prognosa_locks_sheet_to_authenticated_branch` | method | 12 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:106` |
| `test_weekly_prognosa_reads_dashboard_layout_with_indicator_in_column_a` | method | 12 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:198` |
| `test_weekly_prognosa_reads_the_official_area_sheet` | method | 12 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:27` |
| `weeklyDailyPayload` | method | 12 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:610` |
| `weeklyPrognosaCsvFixture` | method | 12 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:813` |
| `dateFromCell` | method | 11 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1408` |
| `decorateCard` | method | 11 | `app/Support/LandingPrognosaCardService.php:123` |
| `fetchSheet` | method | 11 | `app/Http/Controllers/PrognosaWeeklyController.php:219` |
| `parseLegacySpreadsheet` | method | 11 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1087` |
| `test_weekly_position_is_refreshed_while_forecast_target_stays_cached` | method | 11 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:241` |
| `card` | method | 10 | `tests/Unit/LandingPrognosaCardServiceTest.php:345` |
| `localFallbackPayload` | method | 10 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:238` |
| `metric` | method | 10 | `tests/Unit/LandingPrognosaCardServiceTest.php:355` |
| `normaliseLabel` | method | 10 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1481` |
| `parseWorksheetMatrix` | method | 10 | `app/Http/Controllers/PrognosaWeeklyController.php:400` |
| `resolvePositionColumns` | method | 10 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1153` |
| `test_weekly_prognosa_reads_week_five_from_the_new_shifted_layout` | method | 10 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:167` |
| `matrixFromCsvResponse` | method | 9 | `app/Http/Controllers/PrognosaWeeklyController.php:305` |
| `metricRows` | method | 9 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1238` |

## Route Nodes

- `prognosa.weekly` - `prognosa/weekly/{sheet?}`

## Class Nodes

- `LandingPrognosaCardService` - `app/Support/LandingPrognosaCardService.php`
- `LandingPrognosaCardServiceTest` - `tests/Unit/LandingPrognosaCardServiceTest.php`
- `PresentationPrognosaWeeklyService` - `app/Services/Presentation/PresentationPrognosaWeeklyService.php`
- `PrognosaWeeklyController` - `app/Http/Controllers/PrognosaWeeklyController.php`
- `PrognosaWeeklySpreadsheetTest` - `tests/Unit/PrognosaWeeklySpreadsheetTest.php`

## View Nodes

- `report.prognosa-weekly` - `resources/views/report/prognosa-weekly.blade.php`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| prognosa -> core (calls) | 108 |
| prognosa -> core (instantiates) | 43 |
| prognosa -> core (accepts) | 28 |
| prognosa -> access-control (protected_by) | 5 |
| prognosa -> access-control (calls) | 2 |
| prognosa -> tests (extends) | 2 |
| presentation -> prognosa (contains) | 2 |
| prognosa -> dashboard-harian (injects) | 1 |
| prognosa -> presentation (calls) | 1 |
| prognosa -> core (extends) | 1 |
| tests -> prognosa (contains) | 1 |
| core -> prognosa (references_route) | 1 |
| prognosa -> core (extends_view) | 1 |
