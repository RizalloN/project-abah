# Domain: almafacts

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=almafacts --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 6 |
| method | 100 |
| route | 5 |
| class | 2 |
| table | 1 |
| view | 4 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `AlmafactsDashboardController` | class | 95 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:19` |
| `ssa_almafacts` | table | 23 | - |
| `AlmafactsKpiSheetTest` | class | 19 | `tests/Unit/AlmafactsKpiSheetTest.php:14` |
| `financialHighlight` | method | 18 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:263` |
| `labaRugi` | method | 18 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:226` |
| `kpi` | method | 16 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:298` |
| `kpiView` | method | 13 | `tests/Unit/AlmafactsKpiSheetTest.php:419` |
| `timeseries` | method | 12 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2348` |
| `parseKpiSheetCsv` | method | 11 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:598` |
| `cachedKpiSheetPayload` | method | 10 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:413` |
| `getTimeseriesPayload` | method | 10 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2441` |
| `test_delete_management_removes_ssa_almafacts_by_period_and_branch` | method | 10 | `tests/Unit/ManagedReportDeleteTest.php:1113` |
| `fetchKpiSheetPayload` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:525` |
| `financialSnapshots` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1799` |
| `report.dashboard-almafacts.financial-highlight` | route | 9 | - |
| `report.dashboard-almafacts.kinerja-laba-rugi` | route | 9 | - |
| `report.dashboard-almafacts.kpi` | route | 9 | - |
| `rkaRows` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1509` |
| `fetchFinancialAlmafactsMetrics` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1890` |
| `financialAssetQualityNominals` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1928` |
| `normalizeUnitName` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1614` |
| `refreshKpiSourceCaches` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:347` |
| `report.dashboard-almafacts.timeseries` | route | 8 | - |
| `report.dashboard-almafacts.timeseries.data` | route | 8 | - |
| `test_kpi_page_reads_warmed_cache_without_remote_http_call` | method | 8 | `tests/Unit/AlmafactsKpiSheetTest.php:325` |
| `financialUnitOptions` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1706` |
| `persistKpiSheetPayload` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:466` |
| `report.almafacts.kinerja-laba-rugi` | view | 7 | `resources/views/report/almafacts/kinerja-laba-rugi.blade.php:1` |
| `rkaPeriodOptions` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1304` |
| `test_kpi_branch_filter_is_locked_to_the_authenticated_user_branch` | method | 7 | `tests/Unit/AlmafactsKpiSheetTest.php:260` |

## Route Nodes

- `report.dashboard-almafacts.financial-highlight` - `report/dashboard-almafacts/financial-highlight`
- `report.dashboard-almafacts.kinerja-laba-rugi` - `report/dashboard-almafacts/kinerja-laba-rugi`
- `report.dashboard-almafacts.kpi` - `report/dashboard-almafacts/kpi/{sheet?}`
- `report.dashboard-almafacts.timeseries` - `report/dashboard-almafacts/timeseries`
- `report.dashboard-almafacts.timeseries.data` - `report/dashboard-almafacts/timeseries/data`

## Class Nodes

- `AlmafactsDashboardController` - `app/Http/Controllers/Report/AlmafactsDashboardController.php`
- `AlmafactsKpiSheetTest` - `tests/Unit/AlmafactsKpiSheetTest.php`

## View Nodes

- `report.almafacts.financial-highlight` - `resources/views/report/almafacts/financial-highlight.blade.php`
- `report.almafacts.kinerja-laba-rugi` - `resources/views/report/almafacts/kinerja-laba-rugi.blade.php`
- `report.almafacts.kpi` - `resources/views/report/almafacts/kpi.blade.php`
- `report.almafacts.timeseries` - `resources/views/report/almafacts/timeseries.blade.php`

## Table Nodes

- `ssa_almafacts`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| almafacts -> core (calls) | 67 |
| almafacts -> access-control (protected_by) | 25 |
| almafacts -> core (accepts) | 6 |
| almafacts -> core (instantiates) | 6 |
| core -> almafacts (references_route) | 4 |
| almafacts -> core (extends_view) | 4 |
| almafacts -> core (checks_table) | 3 |
| almafacts -> core (reads_table) | 3 |
| presentation -> almafacts (instantiates) | 3 |
| almafacts -> core (includes_view) | 3 |
| database -> almafacts (checks_table) | 2 |
| core -> almafacts (accepts) | 2 |
| core -> almafacts (calls) | 2 |
| dashboard-simpanan -> almafacts (reads_table) | 2 |
| almafacts -> access-control (calls) | 2 |
| almafacts -> jobs-snapshots (calls) | 2 |
| presentation -> almafacts (contains) | 2 |
| tests -> almafacts (contains) | 2 |
| almafacts -> core (dispatches) | 1 |
| almafacts -> tests (instantiates) | 1 |
| presentation -> almafacts (reads_table) | 1 |
| almafacts -> core (writes_table) | 1 |
| kpi -> almafacts (instantiates) | 1 |
| almafacts -> core (extends) | 1 |
| almafacts -> tests (extends) | 1 |
