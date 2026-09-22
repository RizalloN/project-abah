# Domain: almafacts

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=almafacts --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 10 |
| method | 113 |
| route | 5 |
| class | 4 |
| table | 1 |
| view | 6 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `AlmafactsDashboardController` | class | 101 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:20` |
| `AlmafactsKpiSheetTest` | class | 23 | `tests/Unit/AlmafactsKpiSheetTest.php:14` |
| `ssa_almafacts` | table | 23 | - |
| `financialHighlight` | method | 18 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:270` |
| `labaRugi` | method | 18 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:233` |
| `kpi` | method | 15 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:305` |
| `kpiView` | method | 14 | `tests/Unit/AlmafactsKpiSheetTest.php:493` |
| `timeseries` | method | 12 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2490` |
| `parseKpiSheetCsv` | method | 11 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:719` |
| `cachedKpiSheetPayload` | method | 10 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:428` |
| `fetchKpiSheetPayload` | method | 10 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:540` |
| `getTimeseriesPayload` | method | 10 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2583` |
| `report.dashboard-almafacts.kpi` | route | 10 | - |
| `test_delete_management_removes_ssa_almafacts_by_period_and_branch` | method | 10 | `tests/Unit/ManagedReportDeleteTest.php:1114` |
| `financialSnapshots` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1941` |
| `report.dashboard-almafacts.financial-highlight` | route | 9 | - |
| `report.dashboard-almafacts.kinerja-laba-rugi` | route | 9 | - |
| `rkaRows` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1636` |
| `FinancialHighlightPresentationTest` | class | 8 | `tests/Unit/FinancialHighlightPresentationTest.php:10` |
| `fetchFinancialAlmafactsMetrics` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2032` |
| `fetchKpiRmSmeDashboardPayload` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:600` |
| `financialAssetQualityNominals` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2070` |
| `normalizeUnitName` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1741` |
| `refreshKpiSourceCaches` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:362` |
| `report.almafacts.kinerja-laba-rugi` | view | 8 | `resources/views/report/almafacts/kinerja-laba-rugi.blade.php:1` |
| `report.dashboard-almafacts.timeseries` | route | 8 | - |
| `report.dashboard-almafacts.timeseries.data` | route | 8 | - |
| `test_kpi_page_keeps_june_and_july_sources_in_separate_periods` | method | 8 | `tests/Unit/AlmafactsKpiSheetTest.php:344` |
| `test_kpi_page_reads_warmed_cache_without_remote_http_call` | method | 8 | `tests/Unit/AlmafactsKpiSheetTest.php:328` |
| `buildRows` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1536` |

## Route Nodes

- `report.dashboard-almafacts.financial-highlight` - `report/dashboard-almafacts/financial-highlight`
- `report.dashboard-almafacts.kinerja-laba-rugi` - `report/dashboard-almafacts/kinerja-laba-rugi`
- `report.dashboard-almafacts.kpi` - `report/dashboard-almafacts/kpi/{sheet?}`
- `report.dashboard-almafacts.timeseries` - `report/dashboard-almafacts/timeseries`
- `report.dashboard-almafacts.timeseries.data` - `report/dashboard-almafacts/timeseries/data`

## Class Nodes

- `AlmafactsDashboardController` - `app/Http/Controllers/Report/AlmafactsDashboardController.php`
- `AlmafactsKpiSheetTest` - `tests/Unit/AlmafactsKpiSheetTest.php`
- `FinancialHighlightPresentationTest` - `tests/Unit/FinancialHighlightPresentationTest.php`
- `KinerjaLabaRugiAchievementTest` - `tests/Unit/KinerjaLabaRugiAchievementTest.php`

## View Nodes

- `report.almafacts.financial-highlight` - `resources/views/report/almafacts/financial-highlight.blade.php`
- `report.almafacts.kinerja-laba-rugi` - `resources/views/report/almafacts/kinerja-laba-rugi.blade.php`
- `report.almafacts.kpi` - `resources/views/report/almafacts/kpi.blade.php`
- `report.almafacts.kpi-rm-sme` - `resources/views/report/almafacts/kpi-rm-sme.blade.php`
- `report.almafacts.partials.kpi-unified-theme` - `resources/views/report/almafacts/partials/kpi-unified-theme.blade.php`
- `report.almafacts.timeseries` - `resources/views/report/almafacts/timeseries.blade.php`

## Table Nodes

- `ssa_almafacts`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| almafacts -> core (calls) | 69 |
| almafacts -> access-control (protected_by) | 25 |
| almafacts -> access-control (instantiates) | 7 |
| almafacts -> core (instantiates) | 7 |
| almafacts -> core (accepts) | 6 |
| almafacts -> platform (extends_view) | 5 |
| platform -> almafacts (references_route) | 4 |
| almafacts -> core (includes_view) | 4 |
| almafacts -> core (checks_table) | 3 |
| almafacts -> core (reads_table) | 3 |
| almafacts -> tests (extends) | 3 |
| database -> almafacts (checks_table) | 2 |
| dashboard-simpanan -> almafacts (reads_table) | 2 |
| almafacts -> access-control (calls) | 2 |
| almafacts -> jobs-snapshots (calls) | 2 |
| tests -> almafacts (contains) | 2 |
| core -> almafacts (accepts) | 1 |
| core -> almafacts (calls) | 1 |
| almafacts -> jobs-snapshots (dispatches) | 1 |
| jobs-snapshots -> almafacts (accepts) | 1 |
| jobs-snapshots -> almafacts (calls) | 1 |
| almafacts -> tests (calls) | 1 |
| dashboard-pinjaman -> almafacts (calls) | 1 |
| almafacts -> core (writes_table) | 1 |
| kpi -> almafacts (instantiates) | 1 |
| almafacts -> core (extends) | 1 |
| almafacts -> dashboard-pinjaman (contains) | 1 |
