# Domain: almafacts

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=almafacts --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 7 |
| method | 89 |
| route | 5 |
| class | 1 |
| table | 1 |
| view | 6 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `AlmafactsDashboardController` | class | 94 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:21` |
| `ssa_almafacts` | table | 20 | - |
| `financialHighlight` | method | 18 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:280` |
| `labaRugi` | method | 18 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:243` |
| `kpi` | method | 16 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:315` |
| `getTimeseriesPayload` | method | 12 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2663` |
| `timeseries` | method | 12 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2569` |
| `cachedKpiSheetPayload` | method | 11 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:440` |
| `fetchKpiSheetPayload` | method | 11 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:605` |
| `parseKpiSheetCsv` | method | 11 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:787` |
| `report.dashboard-almafacts.kpi` | route | 11 | - |
| `report.dashboard-almafacts.financial-highlight` | route | 10 | - |
| `report.dashboard-almafacts.kinerja-laba-rugi` | route | 10 | - |
| `financialSnapshots` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2020` |
| `refreshKpiSourceCaches` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:373` |
| `report.dashboard-almafacts.timeseries` | route | 9 | - |
| `report.dashboard-almafacts.timeseries.data` | route | 9 | - |
| `rkaRows` | method | 9 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1715` |
| `fetchFinancialAlmafactsMetrics` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2111` |
| `fetchKpiRmSmeDashboardPayload` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:668` |
| `financialAssetQualityNominals` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2149` |
| `getRatioTimeseriesPayload` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2959` |
| `normalizeUnitName` | method | 8 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1820` |
| `buildRows` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1615` |
| `financialUnitOptions` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1927` |
| `persistKpiSheetPayload` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:500` |
| `report.almafacts.kinerja-laba-rugi` | view | 7 | `resources/views/report/almafacts/kinerja-laba-rugi.blade.php:1` |
| `rkaPeriodOptions` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1508` |
| `timeseriesData` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:2622` |
| `unitKey` | method | 7 | `app/Http/Controllers/Report/AlmafactsDashboardController.php:1810` |

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
| almafacts -> core (calls) | 33 |
| almafacts -> access-control (protected_by) | 30 |
| almafacts -> core (accepts) | 6 |
| almafacts -> platform (extends_view) | 5 |
| platform -> almafacts (references_route) | 4 |
| almafacts -> core (includes_view) | 4 |
| almafacts -> access-control (calls) | 3 |
| almafacts -> core (checks_table) | 3 |
| almafacts -> core (reads_table) | 3 |
| database -> almafacts (checks_table) | 2 |
| dashboard-simpanan -> almafacts (reads_table) | 2 |
| core -> almafacts (accepts) | 1 |
| core -> almafacts (calls) | 1 |
| almafacts -> jobs-snapshots (dispatches) | 1 |
| jobs-snapshots -> almafacts (accepts) | 1 |
| jobs-snapshots -> almafacts (calls) | 1 |
| almafacts -> core (extends) | 1 |
