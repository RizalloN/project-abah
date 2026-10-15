# Domain: almafacts

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=almafacts --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 7 |
| method | 86 |
| route | 5 |
| class | 1 |
| table | 1 |
| view | 6 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `AlmafactsDashboardController` | class | 91 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:21` |
| `ssa_almafacts` | table | 19 | - |
| `financialHighlight` | method | 18 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:279` |
| `labaRugi` | method | 18 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:242` |
| `kpi` | method | 16 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:314` |
| `timeseries` | method | 12 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2562` |
| `cachedKpiSheetPayload` | method | 11 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:439` |
| `fetchKpiSheetPayload` | method | 11 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:599` |
| `parseKpiSheetCsv` | method | 11 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:781` |
| `report.dashboard-almafacts.kpi` | route | 11 | - |
| `getTimeseriesPayload` | method | 10 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2655` |
| `report.dashboard-almafacts.financial-highlight` | route | 10 | - |
| `report.dashboard-almafacts.kinerja-laba-rugi` | route | 10 | - |
| `financialSnapshots` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2013` |
| `refreshKpiSourceCaches` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:372` |
| `report.dashboard-almafacts.timeseries` | route | 9 | - |
| `report.dashboard-almafacts.timeseries.data` | route | 9 | - |
| `rkaRows` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1708` |
| `fetchFinancialAlmafactsMetrics` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2104` |
| `fetchKpiRmSmeDashboardPayload` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:662` |
| `financialAssetQualityNominals` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2142` |
| `normalizeUnitName` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1813` |
| `buildRows` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1608` |
| `financialUnitOptions` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1920` |
| `persistKpiSheetPayload` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:499` |
| `report.almafacts.kinerja-laba-rugi` | view | 7 | `resources/views/report/almafacts/kinerja-laba-rugi.blade.php:1` |
| `rkaPeriodOptions` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1501` |
| `timeseriesData` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2615` |
| `applyFinancialFilters` | method | 6 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2302` |
| `branchOptions` | method | 6 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1582` |

## Route Nodes

- `report.dashboard-almafacts.financial-highlight` - `report/dashboard-almafacts/financial-highlight`
- `report.dashboard-almafacts.kinerja-laba-rugi` - `report/dashboard-almafacts/kinerja-laba-rugi`
- `report.dashboard-almafacts.kpi` - `report/dashboard-almafacts/kpi/{sheet?}`
- `report.dashboard-almafacts.timeseries` - `report/dashboard-almafacts/timeseries`
- `report.dashboard-almafacts.timeseries.data` - `report/dashboard-almafacts/timeseries/data`

## Class Nodes

- `AlmafactsDashboardController` - `app/Http/Controllers/Report/AlmafactsDashboardController.php`

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
| almafacts -> core (calls) | 31 |
| almafacts -> access-control (protected_by) | 30 |
| almafacts -> core (accepts) | 6 |
| almafacts -> platform (extends_view) | 5 |
| platform -> almafacts (references_route) | 4 |
| almafacts -> core (includes_view) | 4 |
| almafacts -> core (checks_table) | 3 |
| almafacts -> core (reads_table) | 3 |
| database -> almafacts (checks_table) | 2 |
| dashboard-simpanan -> almafacts (reads_table) | 2 |
| almafacts -> access-control (calls) | 2 |
| core -> almafacts (accepts) | 1 |
| core -> almafacts (calls) | 1 |
| almafacts -> jobs-snapshots (dispatches) | 1 |
| jobs-snapshots -> almafacts (accepts) | 1 |
| jobs-snapshots -> almafacts (calls) | 1 |
| almafacts -> core (extends) | 1 |
