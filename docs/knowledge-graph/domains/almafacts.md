# Domain: almafacts

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=almafacts --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 7 |
| method | 84 |
| route | 5 |
| class | 1 |
| table | 1 |
| view | 6 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `AlmafactsDashboardController` | class | 89 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:20` |
| `ssa_almafacts` | table | 19 | - |
| `financialHighlight` | method | 18 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:270` |
| `labaRugi` | method | 18 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:233` |
| `kpi` | method | 15 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:305` |
| `timeseries` | method | 12 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2490` |
| `parseKpiSheetCsv` | method | 11 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:719` |
| `report.dashboard-almafacts.kpi` | route | 11 | - |
| `cachedKpiSheetPayload` | method | 10 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:428` |
| `fetchKpiSheetPayload` | method | 10 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:540` |
| `getTimeseriesPayload` | method | 10 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2583` |
| `report.dashboard-almafacts.financial-highlight` | route | 10 | - |
| `report.dashboard-almafacts.kinerja-laba-rugi` | route | 10 | - |
| `financialSnapshots` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1941` |
| `report.dashboard-almafacts.timeseries` | route | 9 | - |
| `report.dashboard-almafacts.timeseries.data` | route | 9 | - |
| `rkaRows` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1636` |
| `fetchFinancialAlmafactsMetrics` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2032` |
| `fetchKpiRmSmeDashboardPayload` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:600` |
| `financialAssetQualityNominals` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2070` |
| `normalizeUnitName` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1741` |
| `refreshKpiSourceCaches` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:362` |
| `buildRows` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1536` |
| `financialUnitOptions` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1848` |
| `persistKpiSheetPayload` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:481` |
| `report.almafacts.kinerja-laba-rugi` | view | 7 | `resources/views/report/almafacts/kinerja-laba-rugi.blade.php:1` |
| `rkaPeriodOptions` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1429` |
| `timeseriesData` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2543` |
| `applyFinancialFilters` | method | 6 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2230` |
| `branchOptions` | method | 6 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1510` |

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
