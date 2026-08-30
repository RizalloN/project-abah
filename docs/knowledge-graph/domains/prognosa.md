# Domain: prognosa

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=prognosa --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 6 |
| method | 144 |
| route | 1 |
| class | 5 |
| view | 1 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `PrognosaWeeklyController` | class | 63 | `app/Http/Controllers/PrognosaWeeklyController.php:20` |
| `PresentationPrognosaWeeklyService` | class | 46 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:20` |
| `PrognosaWeeklySpreadsheetTest` | class | 25 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:18` |
| `LandingPrognosaCardService` | class | 17 | `app/Support/LandingPrognosaCardService.php:7` |
| `buildDashboardAlignedRows` | method | 17 | `app/Http/Controllers/PrognosaWeeklyController.php:665` |
| `buildDashboardAlignedPayload` | method | 13 | `app/Http/Controllers/PrognosaWeeklyController.php:564` |
| `index` | method | 12 | `app/Http/Controllers/PrognosaWeeklyController.php:146` |
| `parseCsvSources` | method | 12 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:302` |
| `test_weekly_prognosa_consolidates_exactly_four_branch_sheets` | method | 12 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:26` |
| `test_weekly_prognosa_locks_sheet_to_authenticated_branch` | method | 12 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:110` |
| `cleanCell` | method | 11 | `app/Http/Controllers/PrognosaWeeklyController.php:526` |
| `dateFromCell` | method | 11 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:1092` |
| `fetchSheet` | method | 11 | `app/Http/Controllers/PrognosaWeeklyController.php:227` |
| `parseSpreadsheet` | method | 11 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:771` |
| `test_weekly_position_is_refreshed_while_forecast_target_stays_cached` | method | 11 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:188` |
| `weeklyController` | method | 11 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:494` |
| `weeklyPrognosaCsvFixture` | method | 11 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:717` |
| `LandingPrognosaCardServiceTest` | class | 10 | `tests/Unit/LandingPrognosaCardServiceTest.php:11` |
| `resolvePositionColumns` | method | 10 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:837` |
| `weeklyDailyPayload` | method | 10 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:513` |
| `consolidateAreaRows` | method | 9 | `app/Http/Controllers/PrognosaWeeklyController.php:1077` |
| `localFallbackPayload` | method | 9 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:238` |
| `matrixFromCsvResponse` | method | 9 | `app/Http/Controllers/PrognosaWeeklyController.php:313` |
| `metricRows` | method | 9 | `app/Services/Presentation/PresentationPrognosaWeeklyService.php:922` |
| `prognosa.weekly` | route | 9 | - |
| `test_weekly_prognosa_allows_an_explicit_week_override` | method | 9 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:143` |
| `test_weekly_prognosa_displays_dash_when_rka_is_not_available` | method | 9 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:298` |
| `test_weekly_prognosa_ignores_an_invalid_week_override` | method | 9 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:171` |
| `test_weekly_prognosa_uses_complete_read_only_run_off_snapshot_for_every_scope` | method | 9 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:316` |
| `test_weekly_prognosa_uses_scoped_workbook_fallback_with_real_report_coordinates` | method | 9 | `tests/Unit/PrognosaWeeklySpreadsheetTest.php:219` |

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
| prognosa -> core (calls) | 89 |
| prognosa -> core (instantiates) | 37 |
| prognosa -> core (accepts) | 23 |
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
